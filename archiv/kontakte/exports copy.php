<?php
/**
 * Plugin Name: CMX – Kontakte Export ZIP (CSV + vCard)
 * Description: Export-Button (neben „Filtern“) -> ZIP mit kontakte.csv + kontakte.vcf. CSV enthält ALLE Felder aus der Metabox „Kommunikation“ (meta___cmx_kommunikation) in separaten Spalten: {gruppe}_{n}__typ / {gruppe}_{n}__wert. Robust: sauberes Header-Streaming, keine korrupten ZIPs.
 * Version: 3.0.1
 * Author: CLOUDMEISTER
 */

namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit;

const CMX_PT            = 'kontakte';
const CMX_EXPORT_GET    = 'cmx_export';
const CMX_NONCE_ACT     = 'cmx_export_filters_action';
const CMX_COMM_META_KEY = 'meta___cmx_kommunikation';


// if (!defined(__NAMESPACE__.'\\CMX_COMM_META_KEY')) {
//     define(__NAMESPACE__.'\\CMX_COMM_META_KEY', 'meta___cmx_kommunikation');
// }

/* =========================
 * UI: Export-Button
 * ========================= */
\add_action('admin_enqueue_scripts', __NAMESPACE__.'\\cmx_enqueue_export_js');
function cmx_enqueue_export_js($hook): void {
	if ($hook !== 'edit.php') return;
	if (($_GET['post_type'] ?? '') !== CMX_PT) return;

	\wp_enqueue_script('jquery');
	$nonce = \wp_create_nonce(CMX_NONCE_ACT);
	\wp_add_inline_script('jquery', sprintf(<<<'JS'
jQuery(function($){
	const $filterBtn = $('#post-query-submit');
	const $form      = $('#posts-filter');
	if (!$filterBtn.length || !$form.length) return;

	const $exportBtn = $('<button/>', {
		id: 'cmx-export-btn',
		type: 'button',
		class: 'button button-primary',
		text: 'EXPORT',
		css: { marginLeft: '8px' }
	}).insertAfter($filterBtn);

	$exportBtn.on('click', function(e){
		e.preventDefault();
		$form.find('input[name="%1$s"], input[name="cmx_ids"], input[name="_wpnonce"]').remove();
		$form.append($('<input>', {type:'hidden', name:'%1$s', value:'1'}));
		$form.append($('<input>', {type:'hidden', name:'_wpnonce', value:%2$s}));

		const ids = [];
		$('#the-list input[type="checkbox"][name="post[]"]:checked').each(function(){
			const v = $(this).val();
			if (v && /^\d+$/.test(v)) ids.push(v);
		});
		if (ids.length) $form.append($('<input>', {type:'hidden', name:'cmx_ids', value:ids.join(',')}));
		$form.attr('method','GET').attr('action', window.location.pathname).trigger('submit');
	});
});
JS, CMX_EXPORT_GET, \wp_json_encode($nonce)));
}

/* =========================
 * Controller
 * ========================= */
\add_action('load-edit.php', __NAMESPACE__.'\\cmx_maybe_export');
function cmx_maybe_export(): void {
	if (($_GET['post_type'] ?? '') !== CMX_PT) return;
	if (empty($_GET[CMX_EXPORT_GET])) return;

	if (!\current_user_can('edit_posts')) \wp_die('Insufficient permissions.');
	\check_admin_referer(CMX_NONCE_ACT);

	$marked_ids = [];
	if (!empty($_GET['cmx_ids'])) {
		$marked_ids = array_values(array_filter(array_map('intval', explode(',', (string)$_GET['cmx_ids']))));
	}

	$explicit_status = null;
	if (isset($_GET['post_status'])) {
		$ps = sanitize_key(wp_unslash($_GET['post_status']));
		if ($ps && $ps !== 'all') $explicit_status = $ps;
	}
	$default_status = $explicit_status ?: 'publish';

	// Markierte zuerst
	if ($marked_ids) {
		$posts = cmx_fetch_posts([
			'post_type'      => CMX_PT,
			'post_status'    => $default_status,
			'post__in'       => $marked_ids,
			'orderby'        => 'post__in',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		]);
		cmx_stream_zip_export($posts);
	}

	// Gefilterte wie Liste
	require_once ABSPATH.'wp-admin/includes/class-wp-list-table.php';
	require_once ABSPATH.'wp-admin/includes/class-wp-posts-list-table.php';
	$screen = \get_current_screen();
	if (!$screen || $screen->id !== 'edit-'.CMX_PT) {
		$posts = cmx_fetch_posts([
			'post_type'      => CMX_PT,
			'post_status'    => $default_status,
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		]);
		cmx_stream_zip_export($posts);
	}

	$GLOBALS['cmx_doing_export'] = true;
	\add_action('pre_get_posts', __NAMESPACE__.'\\cmx_force_all_rows_for_export', 999);

	$table = new \WP_Posts_List_Table(['screen'=>$screen]);
	$table->prepare_items();

	global $wp_query;
	$posts = is_object($wp_query) ? ($wp_query->posts ?? []) : [];

	if ($posts) {
		$post_ids = array_map('intval', \wp_list_pluck($posts, 'ID'));
		$posts    = cmx_fetch_posts([
			'post_type'      => CMX_PT,
			'post_status'    => $default_status,
			'post__in'       => $post_ids,
			'orderby'        => 'post__in',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		]);
		cmx_stream_zip_export($posts);
	}

	// Fallback
	$posts = cmx_fetch_posts([
		'post_type'      => CMX_PT,
		'post_status'    => $default_status,
		'posts_per_page' => -1,
		'no_found_rows'  => true,
	]);
	cmx_stream_zip_export($posts);
}

function cmx_force_all_rows_for_export(\WP_Query $q): void {
	if (empty($GLOBALS['cmx_doing_export'])) return;
	if (!$q->is_main_query()) return;
	if ((string)$q->get('post_type') !== CMX_PT) return;
	$q->set('posts_per_page', -1);
	$q->set('no_found_rows', true);
	if (!($q->get('post_status'))) $q->set('post_status', 'publish');
}

function cmx_fetch_posts(array $args): array {
	$q = new \WP_Query($args);
	return $q->posts ?? [];
}

/* =========================
 * ZIP streamen – CSV + VCF (robust)
 * ========================= */
function cmx_stream_zip_export(array $posts): void {
	$tax_objects = \get_object_taxonomies(CMX_PT, 'objects');
	$tax_names   = array_keys($tax_objects);

	// Schema aus meta___cmx_kommunikation ermitteln
	$groups      = []; // slugs
	$group_slots = []; // max Anzahl Einträge pro Gruppe
	$meta_keys   = []; // übrige Metas (ohne Kommunikations-Meta)

	foreach ($posts as $p) {
		$all  = \get_post_meta($p->ID);
		$kbox = cmx_parse_kbox($all[CMX_COMM_META_KEY] ?? []);

		foreach ($kbox as $label => $items) {
			$slug = cmx_slug($label);
			$groups[$slug] = true;
			$group_slots[$slug] = max((int)($group_slots[$slug] ?? 0), count($items));
		}

		foreach (array_keys($all) as $mk) {
			if ($mk === CMX_COMM_META_KEY) continue;
			if (cmx_meta_is_exportable($mk)) $meta_keys[$mk] = true;
		}
	}

	$groups    = array_keys($groups);
	$meta_keys = (array)\apply_filters('cmx_kontakte_export_meta_keys', array_keys($meta_keys));

	$csv = cmx_render_csv($posts, $tax_names, $meta_keys, $groups, $group_slots);
	$vcf = cmx_render_vcf($posts, $tax_names);

	$filename = 'kontakte-export_'.gmdate('Y-m-d_H-i-s').'.zip';

	// Output-Buffer bereinigen, GZIP-Kompression aus
	cmx_prepare_output();

	// Wenn ZipArchive verfügbar: ZIP in temp-Datei erstellen
	if (class_exists('\ZipArchive')) {
		$tmp = \wp_tempnam($filename);
		if (!$tmp) {
			cmx_stream_fallback_csv($csv); // Fallback
		}
		$zip = new \ZipArchive();
		$flags = \ZipArchive::CREATE | \ZipArchive::OVERWRITE;
		$res = $zip->open($tmp, $flags);
		if ($res !== true) {
			@unlink($tmp);
			cmx_stream_fallback_csv($csv);
		}

		$zip->addFromString('kontakte.csv', $csv);
		$zip->addFromString('kontakte.vcf', $vcf);
		$zip->close();

		clearstatcache(true, $tmp);
		if (!is_file($tmp)) {
			cmx_stream_fallback_csv($csv);
		}

		// Binär streamen
		cmx_stream_file($tmp, $filename, 'application/zip');
		@unlink($tmp);
		exit;
	}

	// Fallback: nur CSV streamen
	cmx_stream_fallback_csv($csv);
}

/**
 * Fallback nur CSV (falls ZipArchive fehlt) – robustes Streaming
 */
function cmx_stream_fallback_csv(string $csv): void {
	$fname = 'kontakte-export_'.gmdate('Y-m-d_H-i-s').'.csv';

	cmx_prepare_output();

	\nocache_headers();
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="'.$fname.'"');
	header('Content-Transfer-Encoding: binary');

	// Länge optional (UTF-8 + BOM bereits im String enthalten)
	header('Content-Length: '.strlen($csv));

	// Direkt ausgeben
	echo $csv;
	exit;
}

/**
 * Gemeinsame Vorbereitung: Output-Buffer leeren, zlib aus
 */
function cmx_prepare_output(): void {
	// Zlib-Komprimierung kann zu korrupten ZIPs führen
	if (function_exists('ini_get') && function_exists('ini_set')) {
		$z = ini_get('zlib.output_compression');
		if ($z) @ini_set('zlib.output_compression', 'Off');
	}

	// Alle offenen Output-Puffer beenden
	while (ob_get_level() > 0) {
		@ob_end_clean();
	}

	// Sicherheitshalber keine bereits gesendeten Daten
	if (headers_sent()) {
		// Wenn schon Header gesendet wurden, brechen wir kontrolliert ab
		// (lieber saubere Meldung als korrupte ZIP)
		wp_die('Es wurden bereits Header gesendet – Export kann nicht korrekt gestreamt werden.');
	}
}

/**
 * Datei binär streamen (für ZIP)
 */
function cmx_stream_file(string $path, string $download_name, string $mime): void {
	\nocache_headers();
	header('Content-Type: '.$mime);
	header('Content-Disposition: attachment; filename="'.$download_name.'"');
	header('Content-Transfer-Encoding: binary');

	$size = filesize($path);
	if ($size !== false) header('Content-Length: '.$size);

	$fp = fopen($path, 'rb');
	if ($fp) {
		// Große Dateien effizient senden
		while (!feof($fp)) {
			echo fread($fp, 8192);
		}
		fclose($fp);
	} else {
		// Fallback
		readfile($path);
	}
	exit;
}

/* =========================
 * CSV – jede sichtbare Zelle als Spalte
 * ========================= */
function cmx_render_csv(array $posts, array $tax_names, array $meta_keys, array $groups, array $group_slots): string {
	$fp = fopen('php://temp', 'w+');
	fwrite($fp, "\xEF\xBB\xBF");

	$header = ['ID','post_title','post_name','post_status','post_date','post_modified'];
	foreach ($tax_names as $tn) $header[] = 'tax__'.$tn;

	// Spalten für Kommunikation
	foreach ($groups as $slug) {
		$max = max(1, (int)($group_slots[$slug] ?? 1));
		for ($i=1; $i <= $max; $i++) {
			$header[] = $slug.'_'.$i.'__typ';
			$header[] = $slug.'_'.$i.'__wert';
		}
	}

	foreach ($meta_keys as $mk) $header[] = 'meta__'.$mk;
	fputcsv($fp, $header, ';');

	// Zeilen
	foreach ($posts as $p) {
		$row = [$p->ID, $p->post_title, $p->post_name, $p->post_status, $p->post_date, $p->post_modified];

		foreach ($tax_names as $tn) {
			$terms = \get_the_terms($p->ID, $tn);
			$row[] = (\is_wp_error($terms) || !$terms) ? '' : implode('|', \wp_list_pluck($terms, 'name'));
		}

		$all  = \get_post_meta($p->ID);
		$kbox = cmx_parse_kbox($all[CMX_COMM_META_KEY] ?? []);

		foreach ($groups as $slug) {
			$label = cmx_unslug($slug, $kbox);
			$items = $kbox[$label] ?? [];
			$max   = max(1, (int)($group_slots[$slug] ?? 1));
			for ($i=0; $i < $max; $i++) {
				$it = $items[$i] ?? ['typ'=>'', 'wert'=>''];
				$row[] = (string)($it['typ']  ?? '');
				$row[] = (string)($it['wert'] ?? '');
			}
		}

		foreach ($meta_keys as $mk) {
			$val = \get_post_meta($p->ID, $mk, true);
			if (is_array($val)) {
				$flat = [];
				array_walk_recursive($val, function($v) use (&$flat){ $flat[] = (string)$v; });
				$row[] = implode('|', $flat);
			} else {
				$row[] = (string)$val;
			}
		}

		fputcsv($fp, $row, ';');
	}

	rewind($fp);
	return stream_get_contents($fp);
}

/* =========================
 * vCard – minimal, Helper definiert
 * ========================= */
function cmx_render_vcf(array $posts, array $tax_names): string {
	$out = [];
	foreach ($posts as $p) {
		$fn = $p->post_title ?: ('Kontakt-'.$p->ID);
		$lines = [];
		$lines[] = 'BEGIN:VCARD';
		$lines[] = 'VERSION:3.0';
		$lines[] = cmx_vcf_text('FN', $fn);
		$uid = 'cmx-kontakte-'.\get_current_blog_id().'-'.$p->ID.'@'.parse_url(home_url(), PHP_URL_HOST);
		$lines[] = cmx_vcf_text('UID', $uid);
		$lines[] = 'END:VCARD';
		$out[] = implode("\r\n", $lines);
	}
	return implode("\r\n", $out)."\r\n";
}
function cmx_vcf_text(string $prop, string $value): string {
	$repl = ['\\'=>'\\\\', "\n"=>'\\n', "\r"=>'', ','=>'\,', ';'=>'\;'];
	return cmx_vcf_fold($prop.':'.strtr($value, $repl));
}
function cmx_vcf_line(string $prop, array $parts): string {
	$esc = array_map(function($p){
		$repl = ['\\'=>'\\\\', "\n"=>'\\n', "\r"=>'', ','=>'\,', ';'=>'\;'];
		return strtr((string)$p, $repl);
	}, $parts);
	return cmx_vcf_fold($prop.':'.implode(';', $esc));
}
function cmx_vcf_fold(string $line): string {
	$max = 75; $out = '';
	while (strlen($line) > $max) { $out .= substr($line, 0, $max)."\r\n".' '; $line = substr($line, $max); }
	return $out.$line;
}

/**
 * Parser für meta___cmx_kommunikation – robust:
 * - akzeptiert PHP-serialisierte Strings, JSON, Arrays, und Plaintext mit Pipes
 * - wandelt zu Struktur [ 'Gruppe' => [ ['typ'=>'..','wert'=>'..'], ... ] ]
 */
function cmx_parse_kbox($raw_meta_values): array {
    $values = is_array($raw_meta_values) ? $raw_meta_values : [$raw_meta_values];
    $out = [];

    foreach ($values as $raw) {
        // 1) PHP-serialisiert?
        if (is_string($raw) && function_exists('maybe_unserialize')) {
            $maybe = @maybe_unserialize($raw);
            if ($maybe !== false && $maybe !== $raw) {
                $raw = $maybe;
            }
        }

        // 2) JSON?
        if (is_string($raw) && strlen($raw) > 1 && ($raw[0] === '{' || $raw[0] === '[')) {
            $dec = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $raw = $dec;
            }
        }

        // 3) Reiner Pipe-Stream (Dein Fall) → direkt parsen
        if (is_string($raw) && strpos($raw, '|') !== false && strpos($raw, ':') === false) {
            $stream = cmx_parse_pipe_stream($raw);
            $out = cmx_merge_groups($out, $stream);
            continue;
        }

        // 4) Strukturierte Arrays (Gruppen, Items, etc.)
        if (is_array($raw)) {
            // assoc: ['Direkt anrufen'=>[...], 'Mail schreiben'=>[...]]
            if (is_assoc($raw)) {
                foreach ($raw as $label => $items) {
                    $label = trim((string)$label);
                    if ($label === '') continue;
                    $list = cmx_items_from_any($items, $label);
                    if ($list) {
                        $out[$label] = array_merge($out[$label] ?? [], $list);
                    }
                }
            } else {
                // numerische Liste: kann gemischte Einträge enthalten
                foreach ($raw as $entry) {
                    // einfacher String mit Pipes?
                    if (!is_array($entry)) {
                        $s = cmx_clean_token($entry);
                        if ($s !== '' && strpos($s, '|') !== false) {
                            $stream = cmx_parse_pipe_stream($s);
                            $out = cmx_merge_groups($out, $stream);
                        }
                        continue;
                    }

                    // Gruppenobjekt mit items
                    $label = (string)($entry['label'] ?? $entry['gruppe'] ?? $entry['title'] ?? $entry['row'] ?? '');
                    if ($label !== '' && isset($entry['items'])) {
                        $list = cmx_items_from_any($entry['items'], $label);
                        if ($list) $out[$label] = array_merge($out[$label] ?? [], $list);
                        continue;
                    }

                    // flacher Datensatz mit Gruppe
                    $grp = (string)($entry['gruppe'] ?? $entry['label'] ?? '');
                    if ($grp !== '' && (isset($entry['typ']) || isset($entry['type']) || isset($entry['wert']) || isset($entry['value']))) {
                        $out[$grp] = array_merge($out[$grp] ?? [], [ cmx_norm_item($entry, $grp) ]);
                        continue;
                    }

                    // ungekeyte Paar-Arrays (z. B. ["Mobil","0787 222 444"])
                    if (!is_assoc($entry) && count($entry) >= 2) {
                        $out['Kommunikation'] = array_merge($out['Kommunikation'] ?? [], [ cmx_norm_item($entry, 'Kommunikation') ]);
                        continue;
                    }

                    // *_1 / *_2 Paare
                    $pairs = cmx_pairs_from_flat($entry, 'Kommunikation');
                    if ($pairs) {
                        $grp = $grp ?: 'Kommunikation';
                        $out[$grp] = array_merge($out[$grp] ?? [], $pairs);
                    }
                }
            }
            continue;
        }

        // 5) Freitext-Zeilen „Typ: Wert“ oder „Gruppe | Typ: Wert“
        $lines = preg_split('/\n+/', str_replace("\r", '', (string)$raw));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (preg_match('/^\s*([^|:]+)\s*\|\s*([^:]+)\s*:\s*(.+)$/u', $line, $m)) {
                $out[trim($m[1])][] = ['typ'=>trim($m[2]), 'wert'=>trim($m[3])];
            } elseif (preg_match('/^\s*([^:]+)\s*:\s*(.+)$/u', $line, $m)) {
                $out['Kommunikation'][] = ['typ'=>trim($m[1]), 'wert'=>trim($m[2])];
            } else {
                $out['Kommunikation'][] = ['typ'=>'', 'wert'=>$line];
            }
        }
    }

    // Duplikate entfernen (typ|wert), Reihenfolge bewahren
    foreach ($out as $label => $items) {
        $unique = []; $seen = [];
        foreach ($items as $it) {
            $key = (string)($it['typ'] ?? '').'|'.(string)($it['wert'] ?? '');
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $unique[] = ['typ'=>(string)($it['typ'] ?? ''), 'wert'=>(string)($it['wert'] ?? '')];
        }
        $out[$label] = $unique;
    }

    return $out;
}


/**
 * Spezieller Parser für Deinen Pipe-Stream:
 * "|044 341 80 00||044 341 88 00||0787 222 444||0|info@...|1||0|..."
 * → baut Gruppen „Direkt anrufen“ (Telefon/Mobil) und „Mail schreiben“ (E-Mail) sowie „Web“.
 */
function cmx_parse_pipe_stream(string $raw): array {
    $parts = explode('|', str_replace("\r", '', $raw));
    $tokens = [];
    foreach ($parts as $p) {
        $t = cmx_clean_token($p); // entfernt '', '0', '1', 'auswählen'
        if ($t !== '') $tokens[] = $t;
    }

    $out = [
        'Direkt anrufen' => [],
        'Mail schreiben' => [],
        'Web'            => [],
    ];

    foreach ($tokens as $t) {
        if (filter_var($t, FILTER_VALIDATE_EMAIL)) {
            $out['Mail schreiben'][] = ['typ'=>'E-Mail', 'wert'=>$t];
        } elseif (preg_match('#^https?://#i', $t)) {
            $out['Web'][] = ['typ'=>'URL', 'wert'=>$t];
        } elseif (preg_match('/[0-9]{3,}/', $t)) {
            // Telefon-Nummern (CH-Heuristik: 07.. = Mobil)
            $digits = preg_replace('/\D+/', '', $t);
            $typ = (preg_match('/^07[0-9]/', $digits)) ? 'Mobil' : 'Telefon';
            $out['Direkt anrufen'][] = ['typ'=>$typ, 'wert'=>$t];
        }
    }

    // Leere Gruppen entfernen
    foreach (['Direkt anrufen','Mail schreiben','Web'] as $g) {
        if (empty($out[$g])) unset($out[$g]);
    }
    return $out;
}

/**
 * Meta-Filter: sorgt dafür, dass das Rohfeld NICHT in der CSV landet.
 * (sonst würdest Du die Pipe-Kette weiter als "meta___cmx_kommunikation" sehen)
 */
function cmx_meta_is_exportable(string $key): bool {
    if ($key === CMX_COMM_META_KEY) return false; // nie doppelt exportieren
    $deny_prefixes = ['_edit_', '_oembed_', '_yoast_wpseo_', '_wc_', '_wp_', '_thumbnail_id'];
    foreach ($deny_prefixes as $pre) if (strpos($key, $pre) === 0) return false;
    $deny_exact = ['_revisions'];
    return !in_array($key, $deny_exact, true);
}

function cmx_phone_type_ch(string $num): string {
	$digits = preg_replace('/\D+/', '', $num);
	if (preg_match('/^07[0-9]/', $digits)) return 'Mobil';
	return 'Telefon';
}
function cmx_merge_groups(array $base, array $add): array {
	foreach ($add as $label => $items) {
		if (!$items) continue;
		$base[$label] = array_merge($base[$label] ?? [], $items);
	}
	return $base;
}

/* =========================
 * Parser-Hilfen & Normalisierung
 * ========================= */
function cmx_clean_token($v): string {
	$t = trim((string)$v);
	if ($t === '' || $t === '0' || $t === '1') return '';
	if (mb_strtolower($t) === 'auswählen') return '';
	return $t;
}
function cmx_is_contact_value(string $s): bool {
	if ($s === '') return false;
	if (filter_var($s, FILTER_VALIDATE_EMAIL)) return true;
	if (preg_match('#^https?://#i', $s)) return true;
	if (preg_match('/[0-9]{3,}/', $s)) return true;
	return false;
}
function cmx_typ_from_group(string $group_label, string $wert): string {
	$g = mb_strtolower($group_label);
	if (strpos($g, 'mail') !== false || strpos($g, 'e-mail') !== false || strpos($g, 'email') !== false) return 'E-Mail';
	if (strpos($g, 'anrufen') !== false || strpos($g, 'telefon') !== false || strpos($g, 'call') !== false) return 'Telefon';
	if (strpos($g, 'whatsapp') !== false) return 'WhatsApp';
	if (strpos($g, 'signal') !== false) return 'Signal';
	if (strpos($g, 'sms') !== false) return 'SMS';
	if (strpos($g, 'web') !== false || strpos($g, 'url') !== false || strpos($g, 'website') !== false) return 'URL';
	if (filter_var($wert, FILTER_VALIDATE_EMAIL)) return 'E-Mail';
	if (preg_match('#^https?://#i', $wert))   return 'URL';
	if (cmx_is_contact_value($wert))          return 'Telefon';
	return '';
}
function cmx_extract_sequential_pairs(array $arr, string $group_label = ''): array {
	$out = [];
	$buf_label = '';
	$vals = array_values($arr);
	$tokens = [];
	foreach ($vals as $v) { $t = cmx_clean_token($v); if ($t !== '') $tokens[] = $t; }
	foreach ($tokens as $t) {
		if (cmx_is_contact_value($t)) {
			$wert = $t;
			$typ  = $buf_label !== '' ? $buf_label : cmx_typ_from_group($group_label, $wert);
			if ($typ === 'Telefon') { $typ = cmx_phone_type_ch($wert); }
			$out[] = ['typ' => $typ, 'wert' => $wert];
			$buf_label = '';
		} else {
			$buf_label = $t;
		}
	}
	return $out;
}
function cmx_items_from_any($items, string $group_label = ''): array {
	$out = [];
	if (!is_array($items)) return $out;

	if (!is_assoc($items)) {
		foreach ($items as $it) {
			if (is_array($it)) {
				if (!is_assoc($it)) {
					$out = array_merge($out, cmx_extract_sequential_pairs($it, $group_label));
				} else {
					$out[] = cmx_norm_item($it, $group_label);
				}
			} else {
				$s = cmx_clean_token($it);
				if ($s !== '') {
					$typ = cmx_typ_from_group($group_label, $s);
					if ($typ === 'Telefon') { $typ = cmx_phone_type_ch($s); }
					$out[] = ['typ' => $typ, 'wert' => $s];
				}
			}
		}
		return $out;
	}

	$probe = cmx_norm_item($items, $group_label);
	if ($probe['typ'] !== '' || $probe['wert'] !== '') {
		$out[] = $probe;
	} else {
		$out = array_merge($out, cmx_pairs_from_flat($items, $group_label));
	}
	return $out;
}
function cmx_norm_item(array $it, string $group_label = ''): array {
	$typ  = $it['typ']   ?? $it['type']  ?? $it['label'] ?? $it['kanal'] ?? $it['icon'] ?? $it['mode'] ?? $it['medium'] ?? $it['art'] ?? '';
	$wert = $it['wert']  ?? $it['value'] ?? $it['val']   ?? $it['text']  ?? $it['content'] ?? $it['nummer'] ?? $it['phone'] ?? $it['email'] ?? $it['url'] ?? '';

	$typ  = cmx_clean_token($typ);
	if (is_array($wert)) {
		$cand = [];
		array_walk_recursive($wert, function($v) use (&$cand){ $cand[] = cmx_clean_token($v); });
		$wert = '';
		foreach ($cand as $c) { if (cmx_is_contact_value($c)) { $wert = $c; break; } }
		if ($wert === '' && $cand) $wert = $cand[0];
	} else {
		$wert = cmx_clean_token($wert);
	}

	if ($typ === '' && $wert === '' && !is_assoc($it)) {
		$pairs = cmx_extract_sequential_pairs($it, $group_label);
		return $pairs[0] ?? ['typ'=>'', 'wert'=>''];
	}

	if ($typ === '') $typ = cmx_typ_from_group($group_label, $wert);
	if ($typ === 'Telefon') { $typ = cmx_phone_type_ch($wert); }

	return ['typ'=>$typ, 'wert'=>$wert];
}
function cmx_pairs_from_flat(array $arr, string $group_label = ''): array {
	$out = []; $idxs = [];
	foreach ($arr as $k => $v) {
		if (preg_match('/_(\d+)$/', (string)$k, $m)) $idxs[(int)$m[1]] = true;
	}
	if (!$idxs) return [];
	ksort($idxs);
	foreach (array_keys($idxs) as $i) {
		$typ_raw  = $arr['typ_'.$i]  ?? $arr['type_'.$i]  ?? $arr['label_'.$i] ?? '';
		$wert_raw = $arr['wert_'.$i] ?? $arr['value_'.$i] ?? $arr['val_'.$i]   ?? '';
		$typ  = cmx_clean_token($typ_raw);
		$wert = is_array($wert_raw) ? '' : cmx_clean_token($wert_raw);

		if ($wert === '') {
			foreach (['email_'.$i, 'phone_'.$i, 'nummer_'.$i, 'url_'.$i, 'text_'.$i] as $alt) {
				if (!empty($arr[$alt])) { $wert = cmx_clean_token($arr[$alt]); if ($wert !== '') break; }
			}
		}
		if ($typ === '') $typ = cmx_typ_from_group($group_label, $wert);
		if ($typ === 'Telefon') { $typ = cmx_phone_type_ch($wert); }
		if ($wert !== '') $out[] = ['typ'=>$typ, 'wert'=>$wert];
	}
	return $out;
}
function is_assoc(array $arr): bool { return array_keys($arr) !== range(0, count($arr) - 1); }

/* =========================
 * Slug/Unslug & Meta-Filter
 * ========================= */
function cmx_slug(string $label): string {
	$slug = strtolower(trim($label));
	if (function_exists('transliterator_transliterate')) {
		$slug = transliterator_transliterate('Any-Latin; Latin-ASCII', $slug);
	} elseif (function_exists('iconv')) {
		$slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
	}
	$slug = preg_replace('/[^a-z0-9]+/u', '_', $slug);
	return trim($slug, '_') ?: 'kommunikation';
}
function cmx_unslug(string $slug, array $kbox): string {
	foreach (array_keys($kbox) as $label) { if (cmx_slug($label) === $slug) return $label; }
	return $slug;
}
// function cmx_meta_is_exportable(string $key): bool {
// 	if ($key === CMX_COMM_META_KEY) return false; // nie doppelt exportieren
// 	$deny_prefixes = ['_edit_', '_oembed_', '_yoast_wpseo_', '_wc_', '_wp_', '_thumbnail_id'];
// 	foreach ($deny_prefixes as $pre) if (strpos($key, $pre) === 0) return false;
// 	$deny_exact = ['_revisions'];
// 	return !in_array($key, $deny_exact, true);
// }
