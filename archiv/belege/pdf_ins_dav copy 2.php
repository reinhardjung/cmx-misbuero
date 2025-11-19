<?php
/**
 * Datei: src/belege/pdf_ins_dav.php (FIX)
 * - Fügt einen SHIM ein, falls cmx_get_beleg_data()/cmx_get_beleg_positionen() nicht geladen sind.
 * - Erstellt $tpl und rendert /vorlagen/rechnung.php.
 */
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

use Dompdf\Dompdf;
use Dompdf\Options;

/** =========================
 * KONFIG
 * ========================= */
if (!defined(__NAMESPACE__.'\\CMX_PDF_DEBUG')) {
	define(__NAMESPACE__.'\\CMX_PDF_DEBUG', true); // zum Testen aktiv lassen
}

/** =========================
 * Logger
 * ========================= */
if (!function_exists(__NAMESPACE__.'\\cmx_pdf_log')) {
	function cmx_pdf_log(string $msg, array $ctx = []): void {
		if (!CMX_PDF_DEBUG) return;
		$line = '[CMX PDF] ' . $msg;
		if ($ctx) $line .= ' | ' . json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		error_log($line);
	}
}

/** =========================
 * Composer Autoload
 * ========================= */
$autoload = trailingslashit(defined('CMX_PLUGIN_DIR') ? CMX_PLUGIN_DIR : plugin_dir_path(__FILE__)) . 'vendor/autoload.php';
if (is_file($autoload)) {
	require_once $autoload;
	cmx_pdf_log('Composer autoload geladen.');
}

/** =========================
 * Hook (spät)
 * ========================= */
add_action('save_post_belege', __NAMESPACE__.'\\cmx_generate_document_on_save', 999, 3);

/** =========================
 * SHIM: Fallback-Implementierungen laden/definieren
 * ========================= */
function cmx_try_load_beleg_helpers(): void {
	$base = trailingslashit(defined('CMX_PLUGIN_DIR') ? CMX_PLUGIN_DIR : plugin_dir_path(__FILE__));
	$candidates = [
		'src/belege/functions-belege.php',
		'src/belege/calc.php',
		'src/belege/index.php',
		'includes/helpers.php',
	];
	foreach ($candidates as $rel) {
		$path = $base . $rel;
		if (is_file($path)) { require_once $path; cmx_pdf_log('geladene Datei', ['file' => $rel]); }
	}
	// Wenn danach immer noch nicht vorhanden → SHIM definieren
	if (!function_exists(__NAMESPACE__.'\\cmx_get_beleg_positionen')) {
		/**
		 * Minimaler Positions-/Summen-Rechner mit MwSt.-Gruppierung.
		 */
		function cmx_get_beleg_positionen(int $post_id, array $opts = []): array {
			$opts = array_replace([
				'round_mode'             => 'half_up', // 'half_up'|'half_even'|'cash_0_05'
				'round_decimals'         => 2,
				'round_lines'            => true,
				'round_totals'           => true,
				'round_strategy'         => 'per_line_then_total',
				'cash_round_only_totals' => true,
			], $opts);

			$raw = get_post_meta($post_id, '_cmx_beleg_positionen', true);
			$rows = is_array($raw) ? $raw : (is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: @unserialize($raw) ?: []) : []);
			if (!is_array($rows)) $rows = [];

			$out = ['positionen'=>[], 'subtotal'=>0.0, 'tax_total'=>0.0, 'total'=>0.0, 'tax_groups'=>[], 'rounding'=>$opts];

			$round = function(float $n, string $what = 'line') use ($opts): float {
				$mode = $opts['round_mode'];
				$dec  = (int)$opts['round_decimals'];
				// Cashround nur fürs Total (Default)
				if ($mode === 'cash_0_05' && $what !== 'total') $mode = 'half_up';
				$php_mode = ($mode === 'half_even') ? PHP_ROUND_HALF_EVEN : PHP_ROUND_HALF_UP;
				$val = round($n, $dec, $php_mode);
				if ($mode === 'cash_0_05' && $what === 'total') {
					$inc = 0.05;
					$k = $val / $inc;
					$val = round($k) * $inc;
				}
				return $val;
			};

			$subtotal_raw = 0.0; $tax_raw = 0.0; $total_raw = 0.0;

			foreach ($rows as $i => $r) {
				if (!is_array($r)) $r = ['title'=>(string)$r];
				$title      = (string)($r['title'] ?? '');
				$qty        = isset($r['qty']) ? (float)$r['qty'] : (isset($r['amount']) ? (float)$r['amount'] : 1.0);
				$unit       = $r['unit'] ?? ($r['einheit'] ?? '');
				$unit_price = isset($r['unit_price']) ? (float)$r['unit_price'] : (isset($r['price']) ? (float)$r['price'] : 0.0);
				$discount   = isset($r['discount']) ? $r['discount'] : ($r['rabatt'] ?? 0);
				$tax_rate   = isset($r['tax_rate']) ? (float)$r['tax_rate'] : (isset($r['mwst']) ? (float)$r['mwst'] : 0.0);
				if ($tax_rate > 1) $tax_rate = $tax_rate / 100.0;

				// Rabatt als %, Anteil oder absolut
				$disc_abs = 0.0;
				if ($discount !== '' && $discount !== null) {
					$d = (float)$discount;
					if ($d > 0 && $d < 1) $disc_abs = $unit_price * $d;
					elseif ($d >= 1 && $d <= 100 && $d < $unit_price) $disc_abs = $unit_price * ($d/100);
					else $disc_abs = $d;
				}

				$net_unit = max($unit_price - $disc_abs, 0);
				$line_net = $qty * $net_unit;
				$line_tax = $line_net * $tax_rate;
				$line_gross = $line_net + $line_tax;

				if (!empty($opts['round_lines'])) {
					$line_net   = $round($line_net, 'line');
					$line_tax   = $round($line_tax, 'line');
					$line_gross = $round($line_gross, 'line');
				}

				$out['positionen'][] = [
					'index'=>$i,'title'=>$title,'qty'=>$qty,'unit'=>$unit,'unit_price'=>round($unit_price,2),
					'discount_per_unit'=>round($disc_abs,2),'price_net_per_unit'=>round($net_unit,2),
					'line_net'=>$line_net,'tax_rate'=>$tax_rate,'line_tax'=>$line_tax,'line_gross'=>$line_gross,'raw'=>$r,
				];

				$subtotal_raw += ($qty * $net_unit);
				$tax_raw      += ($qty * $net_unit) * $tax_rate;
				$total_raw    += ($qty * $net_unit) * (1 + $tax_rate);

				$key = sprintf('%.3f', $tax_rate);
				if (!isset($out['tax_groups'][$key])) $out['tax_groups'][$key] = ['rate'=>$tax_rate,'net'=>0,'tax'=>0,'gross'=>0,'count'=>0];
				$out['tax_groups'][$key]['net'] += $line_net;
				$out['tax_groups'][$key]['tax'] += $line_tax;
				$out['tax_groups'][$key]['gross'] += $line_gross;
				$out['tax_groups'][$key]['count']++;
			}

			if (($opts['round_strategy'] ?? 'per_line_then_total') === 'total_from_unrounded') {
				$out['subtotal']  = $subtotal_raw;
				$out['tax_total'] = $tax_raw;
				$out['total']     = $total_raw;
			} else {
				foreach ($out['positionen'] as $p) {
					$out['subtotal']  += $p['line_net'];
					$out['tax_total'] += $p['line_tax'];
					$out['total']     += $p['line_gross'];
				}
			}

			if (!empty($opts['round_totals'])) {
				$out['subtotal']  = $round($out['subtotal'], 'total_part');
				$out['tax_total'] = $round($out['tax_total'], 'total_part');
				$out['total']     = $round($out['total'], 'total');
			}

			// Gruppen runden
			foreach ($out['tax_groups'] as $k => $g) {
				$out['tax_groups'][$k]['net']   = $round($g['net'], 'group');
				$out['tax_groups'][$k]['tax']   = $round($g['tax'], 'group');
				$out['tax_groups'][$k]['gross'] = $round($g['gross'], 'group');
			}

			return $out;
		}
	}
	if (!function_exists(__NAMESPACE__.'\\cmx_get_beleg_data')) {
		/**
		 * Minimaler Datensammler für Beleg + Positionen (nutzt obigen SHIM).
		 */
		function cmx_get_beleg_data(int $post_id, array $opts = []): array {
			$post = get_post($post_id);
			if (!$post) return [];
			$keys = [
				'_cmx_rechnungsnummer','_cmx_beleg_betreff','_cmx_beleg_beschreibung',
				'_cmx_beleg_kontakt','_cmx_beleg_kontakt_addr','_cmx_beleg_positionen',
				'_cmx_beleg_waehrung','_cmx_beleg_rng_datum','_cmx_beleg_faellig_am',
				'_cmx_beleg_bezahlt_am','_cmx_beleg_leistungsmonat',
			];
			$d = [
				'ID'=>$post->ID,'post_title'=>$post->post_title,'post_content'=>$post->post_content,
				'post_date'=>$post->post_date,'post_status'=>$post->post_status,
			];
			foreach ($keys as $k) { $v = get_post_meta($post_id, $k, true); $d[$k] = ($v === '') ? null : $v; }

			$d['date_invoice']  = $d['_cmx_beleg_rng_datum'] ? date('Y-m-d', strtotime((string)$d['_cmx_beleg_rng_datum'])) : null;
			$d['date_due']      = $d['_cmx_beleg_faellig_am'] ? date('Y-m-d', strtotime((string)$d['_cmx_beleg_faellig_am'])) : null;
			$d['date_paid']     = $d['_cmx_beleg_bezahlt_am'] ? date('Y-m-d', strtotime((string)$d['_cmx_beleg_bezahlt_am'])) : null;
			$d['period_month']  = $d['_cmx_beleg_leistungsmonat'] ? date('Y-m', strtotime((string)$d['_cmx_beleg_leistungsmonat'])) : null;

			// Kontakt
			$d['kontakt'] = null;
			if (!empty($d['_cmx_beleg_kontakt']) && (int)$d['_cmx_beleg_kontakt'] > 0) {
				$kid = (int)$d['_cmx_beleg_kontakt'];
				if ($kp = get_post($kid)) {
					$d['kontakt'] = ['ID'=>$kp->ID,'title'=>$kp->post_title,'meta'=>get_post_meta($kp->ID)];
				}
			} elseif (!empty($d['_cmx_beleg_kontakt_addr'])) {
				$d['kontakt'] = ['raw'=>$d['_cmx_beleg_kontakt_addr']];
			}

			// Positionen berechnen
			$c = cmx_get_beleg_positionen($post_id, $opts);
			$d['positionen'] = $c['positionen'];
			$d['summen'] = ['subtotal'=>$c['subtotal'],'tax_total'=>$c['tax_total'],'total'=>$c['total']];
			$d['tax_groups'] = $c['tax_groups'];

			return $d;
		}
	}
}

/** =========================
 * Generator
 * ========================= */
function cmx_generate_document_on_save(int $post_id, \WP_Post $post, bool $update): void {
	if ($post->post_type !== 'belege') return;
	if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) { cmx_pdf_log('ABBRUCH: Revision/Autosave.', compact('post_id')); return; }
	if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) { cmx_pdf_log('ABBRUCH: DOING_AUTOSAVE.'); return; }
	if (!current_user_can('edit_post', $post_id)) { cmx_pdf_log('ABBRUCH: Permission.'); return; }

	static $in_progress = [];
	if (!empty($in_progress[$post_id])) { cmx_pdf_log('ABBRUCH: bereits in Arbeit.', compact('post_id')); return; }
	$in_progress[$post_id] = true;

	$allowed_status = ['publish','draft','pending','future','private'];
	if (!in_array($post->post_status, $allowed_status, true)) { cmx_pdf_log('ABBRUCH: Status', ['status'=>$post->post_status]); return; }

	// Belegtyp
	$beleg_type = 'rechnung';
	foreach (['belege_kategorien','beleg_kategorie'] as $tax) {
		$slugs = wp_get_post_terms($post_id, $tax, ['fields' => 'slugs']);
		if (!is_wp_error($slugs) && !empty($slugs)) { $beleg_type = (string)$slugs[0]; break; }
	}
	cmx_pdf_log('Belegtyp', ['type'=>$beleg_type]);

	// Zielverzeichnis
	if (!defined('CMX_UPLOADS_MISBUERO') || !CMX_UPLOADS_MISBUERO) {
		$up = wp_get_upload_dir();
		if (!defined(__NAMESPACE__.'\\CMX_UPLOADS_MISBUERO')) {
			define(__NAMESPACE__.'\\CMX_UPLOADS_MISBUERO', trailingslashit($up['basedir']).'misbuero/');
		}
	}
	$base_dir = rtrim(CMX_UPLOADS_MISBUERO, '/').'/'.date('Y').'/';
	if (!wp_mkdir_p($base_dir) || !is_writable($base_dir)) { cmx_pdf_log('FEHLER: Upload-Verzeichnis', ['dir'=>$base_dir]); return; }

	// Dateinamen
	$title_raw  = (string) get_the_title($post_id);
	$title_safe = $title_raw !== '' ? $title_raw : (string) $post_id;
	$basename   = sanitize_title($title_safe . '_' . $beleg_type);
	$html_path = $base_dir . $basename . '.html';
	$pdf_path  = $base_dir . $basename . '.pdf';

	// Optionen/Labels
	$opts = (array) get_option('cmx_einstellungen', []);
	$branding_logo = isset($opts['beleg_logo_url']) ? esc_url($opts['beleg_logo_url']) : 'https://vorlage.misbuero.ch/wp-content/uploads/favicon.png';
	$shop = [
		'company'=>$opts['company']??'CLOUD Meister','strasse'=>$opts['street']??'Beispielstrasse 1',
		'plz'=>$opts['zip']??'8000','ort'=>$opts['city']??'Zürich','land'=>$opts['country']??'CH',
		'phone'=>$opts['phone']??'+41 44 000 00 00','email'=>$opts['email']??'invoice@example.ch',
		'support'=>$opts['support_email']??'support@example.ch','website'=>$opts['website']??'https://cloudmeister.ch',
		'mwst_nr'=>$opts['vat_no']??'',
	];
	$bank = [[
		'bank_name'=>$opts['bank_name']??'Bank','iban'=>$opts['iban']??'CH00 0000 0000 0000 0000 0','bic'=>$opts['bic']??'ABCDEFGH',
	]];
	$labels = array_replace([
		'doc_invoice'=>'Rechnung','doc_delivery'=>'Lieferschein','date'=>'Datum','due'=>'Fällig bis','period'=>'Leistung für',
		'qty'=>'Menge','unit'=>'Einheit','item'=>'Artikel','desc'=>'Beschreibung','unit_price'=>'Einzelpreis',
		'subtotal'=>'Zwischensumme','tax'=>'MwSt','total'=>'Gesamtbetrag','total_excl_vat'=>'exkl. MwSt.','tax_group'=>'MwSt-Sätze',
		'note_payment'=>'Hinweis zur Zahlung','terms'=>'AGB','includes_fees'=>'Alle Preise beinhalten bereits sämtliche Bankgebühren.',
		'no_deduction'=>'Der Gesamtbetrag ist ohne Abzug von Skonto oder Bankspesen vollständig zu entrichten.','bank'=>'Bank','recipient'=>'Empfänger','contact'=>'Kontakt',
	], (array)($opts['labels']??[]));
	$calc_opts = array_replace([
		'currency'=>$opts['currency']??'CHF','round_mode'=>$opts['round_mode']??'cash_0_05','cash_round_only_totals'=>true,
		'round_decimals'=>2,'round_lines'=>true,'round_totals'=>true,'round_strategy'=>'per_line_then_total',
	], (array)($opts['calc']??[]));

	// Helfer sicherstellen (lädt SHIM wenn nötig)
	cmx_try_load_beleg_helpers();
	if (!function_exists(__NAMESPACE__.'\\cmx_get_beleg_data')) {
		cmx_pdf_log('FEHLER: cmx_get_beleg_data() nicht verfügbar – SHIM fehlgeschlagen.');
		return;
	}

	// Daten berechnen
	$doc = cmx_get_beleg_data($post_id, $calc_opts);

	$tpl = [
		'branding'=>['logo'=>$branding_logo,'website'=>$shop['website']],
		'shop'=>$shop,
		'bank'=>$bank,
		'labels'=>$labels,
		'format'=>['currency'=>$calc_opts['currency'],'decimals'=>(int)$calc_opts['round_decimals'],'thousands'=>"'",'decimal'=>'.','date_fmt'=>'Y-m-d'],
		'document'=>[
			'type'=>$beleg_type,
			'number'=>(string)($doc['_cmx_rechnungsnummer'] ?: $title_safe),
			'title'=>(($beleg_type==='rechnung')?$labels['doc_invoice']:ucfirst($beleg_type)) . ' ' . (string)($doc['_cmx_rechnungsnummer'] ?: $title_safe),
			'date'=>$doc['date_invoice'] ?: date('Y-m-d'),
			'due'=>$doc['date_due'] ?: '',
			'period'=>$doc['period_month'] ?: '',
			'description'=>$doc['_cmx_beleg_beschreibung'] ?: '',
			'subtotal'=>(float)($doc['summen']['subtotal'] ?? 0),
			'tax_total'=>(float)($doc['summen']['tax_total'] ?? 0),
			'total'=>(float)($doc['summen']['total'] ?? 0),
		],
		'contact'=>[
			'display'=>(function($d){
				if (!empty($d['kontakt']['raw'])) return (string)$d['kontakt']['raw'];
				if (!empty($d['kontakt']['meta'])) {
					$m = $d['kontakt']['meta']; $lines = [];
					$lines[] = $d['kontakt']['title'] ?? '';
					$street  = $m['_street'][0]  ?? '';
					$zip     = $m['_zip'][0]     ?? '';
					$city    = $m['_city'][0]    ?? '';
					$country = $m['_country'][0] ?? 'CH';
					if ($street) $lines[] = $street;
					$lines[] = trim($country.'-'.$zip.' '.$city);
					return implode("\n", array_filter($lines));
				}
				return '';
			})($doc),
		],
		'positions'=>array_map(function($p){
			return [
				'item'=>(string)($p['title'] ?? ''),'desc'=>(string)($p['raw']['description'] ?? ''),
				'qty'=>(float)($p['qty'] ?? 0),'unit'=>(string)($p['unit'] ?? ''),
				'unit_price'=>(float)($p['unit_price'] ?? 0),
				'line_net'=>(float)($p['line_net'] ?? 0),
				'line_tax'=>(float)($p['line_tax'] ?? 0),
				'line_gross'=>(float)($p['line_gross'] ?? 0),
				'tax_rate'=>(float)($p['tax_rate'] ?? 0),
			];
		}, $doc['positionen'] ?? []),
		'tax_groups'=>array_values(array_map(function($g){
			return ['rate'=>(float)$g['rate'],'net'=>(float)$g['net'],'tax'=>(float)$g['tax'],'gross'=>(float)$g['gross'],'count'=>(int)$g['count']];
		}, $doc['tax_groups'] ?? [])),
		'legal'=>[
			'terms_url'=>$shop['website'] ? rtrim($shop['website'], '/').'/agb/' : '',
			'include_fees'=>true,
			'text_include_fees'=>$labels['includes_fees'],
			'text_no_deduction'=>$labels['no_deduction'],
		],
		'footer'=>['show_page_count'=>true],
	];

	// Vorlage laden
	$tpl_dir  = trailingslashit(defined('CMX_PLUGIN_DIR') ? CMX_PLUGIN_DIR : plugin_dir_path(__FILE__)).'vorlagen/';
	$tpl_path = $tpl_dir . 'rechnung.php';
	if (!is_file($tpl_path)) { cmx_pdf_log('FEHLER: Vorlage rechnung.php nicht gefunden.', ['path'=>$tpl_path]); return; }

	ob_start();
	include $tpl_path; // erwartet $tpl
	$html = trim((string) ob_get_clean());
	if (mb_strlen($html, '8bit') < 50) { cmx_pdf_log('FEHLER: HTML leer/zu kurz.', ['tpl'=>basename($tpl_path)]); return; }
	if (CMX_PDF_DEBUG) { @file_put_contents($html_path, $html); cmx_pdf_log('HTML gespeichert.', ['path'=>$html_path]); }

	// DOMPDF rendern
	try {
		$options = new Options();
		$options->set('isRemoteEnabled', true);
		$dompdf = new Dompdf($options);
		$dompdf->setPaper('A4', 'portrait');
		$dompdf->loadHtml($html);
		$dompdf->render();

		$pdf_output = $dompdf->output();
		if (@file_put_contents($pdf_path, $pdf_output) === false) {
			cmx_pdf_log('FEHLER: PDF konnte nicht geschrieben werden.', ['path'=>$pdf_path]);
			return;
		}
		cmx_pdf_log('PDF erstellt.', ['pdf'=>$pdf_path]);
	} catch (\Throwable $e) {
		cmx_pdf_log('EXCEPTION in DOMPDF', ['msg'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]);
		return;
	}

	// Cleanup
	if (!CMX_PDF_DEBUG && is_file($html_path)) { @unlink($html_path); }
	$legacy_pdf = $base_dir . '_' . $beleg_type . '.pdf';
	if (is_file($legacy_pdf)) @unlink($legacy_pdf);
}
