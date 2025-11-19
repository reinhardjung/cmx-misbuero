<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

use Dompdf\Dompdf;
use Dompdf\Options;


require_once CMX_PLUGIN_DIR . 'src/buchhaltung/banken.php';


/** =========================
 * KONFIG & LOG (keine HTML-Reste)
 * ========================= */
if (!defined(__NAMESPACE__.'\\CMX_PDF_DEBUG')) {
	define(__NAMESPACE__.'\\CMX_PDF_DEBUG', true);
}
if (!function_exists(__NAMESPACE__.'\\cmxbu_log')) {
	function cmxbu_log(string $msg, array $ctx = []): void {
		if (!CMX_PDF_DEBUG) return;
		error_log('[CMX PDF] '.$msg.($ctx ? ' | '.json_encode($ctx, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : ''));
	}
}

// function mytheme_enqueue_local_fonts() {
//     wp_enqueue_style(
//         'asap-fonts',
//         get_stylesheet_directory_uri() . '/asap/asap.css',
//         [],
//         null
//     );
// }
// add_action('wp_enqueue_scripts', 'mytheme_enqueue_local_fonts');


/** =========================
 * Composer Autoload
 * ========================= */
$autoload = trailingslashit(defined('CMX_PLUGIN_DIR') ? CMX_PLUGIN_DIR : plugin_dir_path(__FILE__)) . 'vendor/autoload.php';
if (is_file($autoload)) { require_once $autoload; cmxbu_log('Composer autoload geladen.'); }

/** =========================
 * Helper (eindeutig: cmxbu_*)
 * ========================= */
if (!function_exists(__NAMESPACE__.'\\cmxbu_parse_date_ymd')) {
	function cmxbu_parse_date_ymd($val): ?string {
		if ($val === null || $val === '') return null;
		if (is_int($val) || (is_string($val) && ctype_digit($val))) { $ts=(int)$val; return $ts>0 ? gmdate('Y-m-d',$ts) : null; }
		if (is_string($val) && preg_match('~^\s*(\d{1,2})\.(\d{1,2})\.(\d{4})\s*$~',$val,$m)) {
			return sprintf('%04d-%02d-%02d',(int)$m[3],(int)$m[2],(int)$m[1]);
		}
		$ts=strtotime((string)$val); return $ts?date('d.m.Y',$ts):null;
	}
}
if (!function_exists(__NAMESPACE__.'\\cmxbu_first_meta')) {
	function cmxbu_first_meta(int $post_id, array $keys): ?string {
		foreach ($keys as $k) {
			$v = get_post_meta($post_id, $k, true);
			if ($v !== '' && $v !== null) return (string)$v;
		}
		return null;
	}
}


if (!function_exists(__NAMESPACE__.'\\cmxbu_beleg_get_dates')) {
function cmxbu_beleg_get_dates(int $post_id): array {
		$inv_raw = cmxbu_first_meta($post_id, ['_cmx_beleg_rng_datum','_cmx_rechnungsdatum','_invoice_date','_date']);
		$date_invoice = cmxbu_parse_date_ymd($inv_raw) ?: date('d.m.Y');

		// $due_raw = cmxbu_first_meta($post_id, ['_cmx_beleg_faellig_am','_cmx_faellig_am','_faellig_bis','_due_date','_due']);
		$due_raw = cmxbu_first_meta($post_id, ['_cmx_beleg_faelligkeitsdatum']);
		$date_due = cmxbu_parse_date_ymd($due_raw);
		// if (!$date_due) {<?php


if (!function_exists(__NAMESPACE__ . '\\cmxbu_get_me_contact')) {
	function cmxbu_get_me_contact(): array {

		$q = get_posts([
			'post_type'       => ['kontakte','kontakt','contact'],
			'post_status'     => ['publish','private'],
			'posts_per_page'  => 1,
			'tax_query'       => [
				'relation' => 'OR',
				['taxonomy'=>'kontakte_kategorien','field'=>'slug','terms'=>['das-bin-ich','ich']],
				['taxonomy'=>'kontakte_kategorien','field'=>'name','terms'=>['Das bin ich']],
			],
			'no_found_rows'    => true,
			'suppress_filters' => true,
		]);

		if (empty($q)) return [];

		$p  = $q[0];
		$id = (int)$p->ID;

		// alle Meta abrufen
		$m = get_post_meta($id);

		/** docu rju 2025-11-10: Best Dump */
		// var_dump(get_post_meta($id)); exit;
		return [
			'company' => get_post_meta($id, '_company', true) ?: $p->post_title,
			'strasse' => get_post_meta($id, '_cmx_rechnung_strasse', true),
			'plz'     => get_post_meta($id, '_cmx_rechnung_plz', true),
			'ort'     => get_post_meta($id, '_cmx_rechnung_ort', true),
			'land'    => get_post_meta($id, '_cmx_rechnung_land', true),
			'phone'   => get_post_meta($id, '_cmx_telefon_1', true),
			'email'   => get_post_meta($id, '_cmx_email_1', true),
			'website' => get_post_meta($id, '_cmx_kontakte_url', true),
		];
	}
}


		// 	$opts = (array)get_option('cmx_einstellungen', []);
		// 	$days = isset($opts['due_days']) ? (int)$opts['due_days'] : 10;
		// 	$date_due = date('Y-m-d', strtotime($date_invoice.' +'.$days.' days'));
		// }

		$period_raw = cmxbu_first_meta($post_id, ['_cmx_beleg_leistungsmonat']);
		$monate = [
				1 => 'Januar', 2 => 'Februar', 3 => 'März',
				4 => 'April', 5 => 'Mai', 6 => 'Juni',
				7 => 'Juli', 8 => 'August', 9 => 'September',
				10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
		];
		$period = isset($monate[$period_raw]) ? $monate[$period_raw] : '';

		$waehrung = cmxbu_first_meta($post_id, ['_cmx_beleg_waehrung']);

		return ['date_invoice'=>$date_invoice,'date_due'=>$date_due,'period'=>$period,'currency'=>$waehrung];
	}
}

/** ------- Notizen (Sanitizing/De-Escaping) ------- */
if (!function_exists(__NAMESPACE__.'\\cmxbu_kses_allow')) {
	function cmxbu_kses_allow(): array {
		return [
			'a'=>['href'=>[], 'title'=>[], 'target'=>[], 'rel'=>[]],
			'br'=>[], 'em'=>[], 'i'=>[], 'strong'=>[], 'b'=>[], 'u'=>[],
			'p'=>['style'=>[]], 'ul'=>[], 'ol'=>[], 'li'=>[], 'code'=>[], 'pre'=>[],
			'span'=>['style'=>[]],
		];
	}
}
if (!function_exists(__NAMESPACE__.'\\cmxbu_deep_unslash')) {
	function cmxbu_deep_unslash($v) {
		if (is_array($v)) return array_map('CLOUDMEISTER\\CMX\\Buero\\cmxbu_deep_unslash', $v);
		return is_string($v) ? wp_unslash($v) : $v;
	}
}
if (!function_exists(__NAMESPACE__.'\\cmxbu_sanitize_note_html')) {
	function cmxbu_sanitize_note_html($val): array {
		if (is_array($val)) $val = implode("\n", array_map('strval', $val));
		$raw  = is_string($val) ? wp_unslash($val) : '';
		$html = $raw !== '' ? wp_kses($raw, cmxbu_kses_allow()) : '';
		$text = trim(wp_strip_all_tags($html !== '' ? $html : $raw));
		if ($html === '' && $text !== '') $html = nl2br(esc_html($text));
		return ['html'=>$html, 'text'=>$text, 'raw'=>$raw];
	}
}

/** =========================
 * Kontakt „Das bin ich“ & bevorzugte Bank
 * ========================= */
if (!function_exists(__NAMESPACE__ . '\\cmxbu_meta_first')) {
	/**
	 * Liefert den ersten NICHT-leeren Meta-Wert aus einer Key-Liste.
	 */
	function cmxbu_meta_first(int $post_id, array $keys, string $default = ''): string {
		foreach ($keys as $key) {
			$val = get_post_meta($post_id, $key, true);
			if (is_array($val)) {
				$val = reset($val);
			}
			$val = is_string($val) ? trim($val) : (string) $val;
			if ($val !== '') return $val;
		}
		return $default;
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmxbu_take_first_token')) {
	/**
	 * Nimmt bei Mehrfachwerten (durch Komma/Semikolon/Zeilenumbruch getrennt) den ersten Eintrag.
	 */
	function cmxbu_take_first_token(string $val): string {
		if ($val === '') return '';
		$parts = preg_split('/[;,\\r\\n]+/', $val);
		$first = trim((string)($parts[0] ?? ''));
		return $first;
	}
}



if (!function_exists(__NAMESPACE__.'\\cmxbu_get_preferred_bank')) {
	function cmxbu_get_preferred_bank(): array {
		$opts = (array)get_option('cmx_einstellungen', []);
		if (!empty($opts['bank_preferred_id'])) {
			$pid=(int)$opts['bank_preferred_id'];
			if ($pid>0 && ($p=get_post($pid))) {
				$m=get_post_meta($pid);
				return ['bank_name'=>$m['_bank_name'][0]??$p->post_title, 'iban'=>$m['_iban'][0]??'', 'bic'=>$m['_bic'][0]??''];
			}
		}
		$q = get_posts([
			'post_type'=>['bank','banken'],
			'post_status'=>['publish','private'],
			'posts_per_page'=>1,
			'meta_query'=>[
				'relation'=>'OR',
				['key'=>'_cmx_bank_preferred','value'=>['1','yes','true','on'],'compare'=>'IN'],
				['key'=>'_bank_preferred','value'=>['1','yes','true','on'],'compare'=>'IN'],
			],
			'no_found_rows'=>true,'suppress_filters'=>true,
		]);
		if (!empty($q)) { $p=$q[0]; $m=get_post_meta($p->ID);
			return ['bank_name'=>$m['_bank_name'][0]??$p->post_title, 'iban'=>$m['_iban'][0]??'', 'bic'=>$m['_bic'][0]??''];
		}
		return ['bank_name'=>$opts['bank_name']??'Bank', 'iban'=>$opts['iban']??'', 'bic'=>$opts['bic']??''];
	}
}

/** =========================
 * Artikel-Belegtext (robust)
 * ========================= */
if (!function_exists(__NAMESPACE__.'\\cmxbu_get_article_belegtext')) {
	function cmxbu_get_article_belegtext(int $artikel_id): string {
		if ($artikel_id<=0) return '';
		$keys = [
			'cmx_artikel_belegtext','_cmx_artikel_belegtext',
			'cmx_artikel_beleg_text','_cmx_artikel_beleg_text', // <— NEU: exakter Key laut Vorgabe
			'belegtext','_belegtext','cmx_belegtext','_cmx_belegtext',
			'artikel_belegtext','_artikel_belegtext','beleg_text','_beleg_text'
		];
		foreach ($keys as $key) {
			$val = get_post_meta($artikel_id, $key, true);
			if (is_string($val) && $val !== '' && strpos($val, 'field_') !== 0) return $val;
		}
		$meta = get_post_meta($artikel_id);
		foreach ($keys as $k) {
			if (!empty($meta[$k][0]) && strpos((string)$meta[$k][0], 'field_') !== 0) return (string)$meta[$k][0];
		}
		return '';
	}
}
/** =========================
 * Positionen + Berechnung
 * ========================= */
if (!function_exists(__NAMESPACE__.'\\cmxbu_get_beleg_positionen_calc')) {
	function cmxbu_get_beleg_positionen_calc(int $post_id, array $opts=[]): array {
		$opts = array_replace([
			'round_decimals'=>2,
			'round_lines'=>true,
			'round_totals'=>true,
			'currency'=>'CHF',
		], $opts);

		$raw = get_post_meta($post_id, '_cmx_beleg_positionen', true);
		if (is_string($raw) && $raw!=='') {
			$tmp = json_decode($raw, true);
			if (json_last_error()!==JSON_ERROR_NONE) $tmp = @unserialize($raw);
			$rows = is_array($tmp) ? $tmp : [];
		} elseif (is_array($raw)) {
			$rows = $raw;
		} else {
			$rows = [];
		}
		if (isset($_POST['cmx_positionen']) && is_array($_POST['cmx_positionen'])) {
			$rows = cmxbu_deep_unslash($_POST['cmx_positionen']);
		}

		$norm_minus = static function(string $s): string {
			return str_replace(["\xE2\x88\x92", "−"], "-", $s);
		};
		$to_float = static function($v) use ($norm_minus): float {
			if (!is_string($v)) $v = (string)($v ?? '');
			$v = $norm_minus($v);
			$v = str_replace(["'", ' '], '', $v);
			$v = str_replace(',', '.', $v);
			return is_numeric($v) ? (float)$v : 0.0;
		};
		$to_str = static function($v): string { return is_string($v) ? $v : ''; };

		$out = ['positionen'=>[], 'subtotal'=>0.0, 'total'=>0.0, 'any_discount'=>false];

		foreach ($rows as $i=>$r) {
			if (!is_array($r)) $r=['artikel_id'=>0,'menge'=>1,'preis'=>0.0,'rabatt'=>'','beschreibung'=>''];

			$artikel_id = (int)($r['artikel_id'] ?? 0);

			// C: Gespeicherten Titel bevorzugen; nur wenn leer, aus Artikel holen
			$title_saved = $to_str($r['artikel_name'] ?? $r['item'] ?? $r['title'] ?? '');
			$title       = $title_saved !== '' ? $title_saved : ($artikel_id ? (get_the_title($artikel_id) ?: '') : '');

			$artnr      = '';

			if ($artikel_id>0) {
				$artnr = get_post_meta($artikel_id,'cmx_artikel_sku',true);
				if ($artnr==='' || $artnr===null) $artnr = get_post_meta($artikel_id,'_cmx_artikel_sku',true);
				if ($artnr==='' || $artnr===null) $artnr = get_post_meta($artikel_id,'_cmx_artikel_nr',true);
				if ($artnr==='' || $artnr===null) $artnr = get_post_meta($artikel_id,'_sku',true);
				$artnr = is_string($artnr)?$artnr:'';
			}

			$qty      = $to_float($r['menge'] ?? 1);
			$uprice   = $to_float($r['preis'] ?? 0);
			$rabatt_r = $to_str($r['rabatt'] ?? '');
			$note_in  = $r['beschreibung'] ?? '';

			// B: Zusatznotiz der Position (HTML-saniert)
			$note = cmxbu_sanitize_note_html($note_in);

			// A: Artikel-Belegtext laden (inkl. exaktem Key cmx_artikel_beleg_text)
			$belegtext_raw = '';
			if ($artikel_id) {
				$belegtext_raw = cmxbu_get_article_belegtext($artikel_id);
			} else {
				$pos_sku = $to_str($r['sku'] ?? $r['artnr'] ?? $r['artikelnummer'] ?? $r['nr'] ?? '');
				if ($pos_sku !== '') {
					$sku_post = get_posts([
						'post_type'=>['artikel','product','produkt'],
						'post_status'=>['publish','private','draft'],
						'fields'=>'ids','no_found_rows'=>true,'posts_per_page'=>1,
						'meta_query'=>[
							'relation'=>'OR',
							['key'=>'cmx_artikel_sku', 'value'=>$pos_sku,'compare'=>'='],
							['key'=>'_cmx_artikel_sku','value'=>$pos_sku,'compare'=>'='],
							['key'=>'_cmx_artikel_nr', 'value'=>$pos_sku,'compare'=>'='],
							['key'=>'_sku',            'value'=>$pos_sku,'compare'=>'='],
						],
					]);
					if (!empty($sku_post)) {
						$artikel_id = (int)$sku_post[0];
						if ($title === '') $title = get_the_title($artikel_id) ?: '';
						$belegtext_raw = cmxbu_get_article_belegtext($artikel_id);
					}
				}
			}
			$belegtext_arr  = cmxbu_sanitize_note_html($belegtext_raw);
			$belegtext_html = $belegtext_arr['html'];

			if (trim($rabatt_r) !== '') $out['any_discount'] = true;

			$line_subtotal = $qty * $uprice;

			$has_discount=false; $disc_amount=0.0; $disc_display='';
			$txt = strtolower(trim($rabatt_r));
			if ($txt !== '') {
				if (preg_match('~([\-−]?\d+[.,]?\d*)\s*%~u', $txt, $m)) {
					$pct = abs($to_float($m[1]));
					if ($pct>0) {
						$disc_amount = abs($line_subtotal) * ($pct/100);
						$disc_display = rtrim(rtrim(number_format($pct,2,'.',''), '0'), '.').'%';
						$has_discount = true;
					}
				} else {
					$clean = preg_replace('~(chf|fr\.?)~i','',$txt);
					$val = abs($to_float($clean));
					if ($val>0) {
						$disc_amount = min($val, abs($line_subtotal));
						$disc_display= number_format($val,2,'.',"'").' '.$opts['currency'];
						$has_discount= true;
					}
				}
			}

			$line_total = ($line_subtotal >= 0)
				? ($line_subtotal - $disc_amount)
				: ($line_subtotal + $disc_amount);

			if (!empty($opts['round_lines'])) $line_total = round($line_total, (int)$opts['round_decimals']);

			$out['positionen'][] = [
				'index'                 => $i,
				'article_number'        => $artnr,
				'title'                 => $title,
				'qty'                   => $qty,
				'unit_price'            => round($uprice,(int)$opts['round_decimals']),
				'line_total'            => $line_total,
				'discount'              => $disc_display,
				'has_discount'          => $has_discount,
				'desc_text'             => $note['text'],          // Zusatznotiz
				'desc_html'             => $note['html'],
				'desc_raw'              => $note['raw'],
				'article_belegtext_html'=> $belegtext_html,        // Beleg-Text (CPT Artikel)
			];

			$out['subtotal'] += $line_total;
		}

		if (!empty($opts['round_totals'])) $out['subtotal'] = round($out['subtotal'], (int)$opts['round_decimals']);
		$out['total'] = $out['subtotal']; // MwSt = 0
		return $out;
	}
}

/** =========================
 * Generator (save_post_belege)
 * ========================= */
add_action('save_post_belege', __NAMESPACE__.'\\cmxbu_generate_document_on_save', 999, 3);
	function cmxbu_generate_document_on_save(int $post_id, \WP_Post $post, bool $update): void {
		if ($post->post_type !== 'belege') return;
		if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) { cmxbu_log('ABBRUCH: Revision/Autosave.',compact('post_id')); return; }
		if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) { cmxbu_log('ABBRUCH: DOING_AUTOSAVE.'); return; }
		if (!current_user_can('edit_post',$post_id)) { cmxbu_log('ABBRUCH: Permission.'); return; }

		static $in_progress=[]; if (!empty($in_progress[$post_id])) { cmxbu_log('ABBRUCH: bereits in Arbeit.',compact('post_id')); return; }
		$in_progress[$post_id]=true;

		// $adr = get_post_meta($post_id);
		$cmx_beleg_adress = nl2br(get_post_meta($post_id)['_cmx_beleg_kontakt_addr'][0]);
		// var_dump($cmx_beleg_adress); exit;

		// Belegtyp (z. B. rechnung, angebot, lieferantenrechnung, gutschrift, ...)
		$beleg_type='rechnung';
		foreach (['belege_kategorien','beleg_kategorie'] as $tax) {
			$slugs=wp_get_post_terms($post_id,$tax,['fields'=>'slugs']);
			if (!is_wp_error($slugs) && !empty($slugs)) { $beleg_type=(string)$slugs[0]; break; }
		}

		// Upload-Ziel
		if (!defined('CMX_UPLOADS_MISBUERO') || !CMX_UPLOADS_MISBUERO) {
			$up=wp_get_upload_dir();
			if (!defined(__NAMESPACE__.'\\CMX_UPLOADS_MISBUERO')) define(__NAMESPACE__.'\\CMX_UPLOADS_MISBUERO', trailingslashit($up['basedir']).'misbuero/');
		}
		$base_dir=rtrim(CMX_UPLOADS_MISBUERO,'/').'/'.date('Y').'/';
		if (!wp_mkdir_p($base_dir) || !is_writable($base_dir)) { cmxbu_log('FEHLER: Upload-Verzeichnis',['dir'=>$base_dir]); return; }


		// Dateinamen
		$title_raw=(string)get_the_title($post_id);
		$title_safe = ($title_raw !== '') ? $title_raw : (string)$post_id;
		$basename=sanitize_title($title_safe.'_'.$beleg_type);
		$html_path=$base_dir.$basename.'.html';  // wird am Ende gelöscht
		$pdf_path =$base_dir.$basename.'.pdf';


		// Optionen/Labels
		$opts=(array)get_option('cmx_einstellungen',[]);
		$branding_logo = isset($opts['beleg_logo_url']) ? esc_url($opts['beleg_logo_url']) : 'https://vorlage.misbuero.ch/wp-content/uploads/favicon.png';
		$labels = array_replace([
			'doc_invoice'=>'Rechnung','date'=>'Datum','due'=>'Fällig bis','period'=>'Leistung für',
			'subject'=>'Betreff','desc'=>'Beschreibung','item'=>'Artikel','artnr'=>'SKU',
			'qty'=>'Menge','unit_price'=>'Einzelpreis','discount'=>'Rabatt','line_total'=>'Summe','total'=>'Gesamtbetrag',
			'recipient'=>'Empfänger','bank'=>'Bank','contact'=>'Kontakt',
		], (array)($opts['labels']??[]));

		// Titel je nach Belegtyp ** Meine Helper Funktion nutzen
		$type_map = [
			'rechnung'             => 'Rechnung',
			'angebot'              => 'Angebot',
			'lieferantenrechnung'  => 'Lieferantenrechnung',
			'gutschrift'           => 'Gutschrift',
			'mahnung'              => 'Mahnung',
		];
		$doc_label = $type_map[strtolower($beleg_type)] ?? ucfirst($beleg_type);

		// Schweizer Format
		$fmt = ['currency'=>$opts['currency']??'CHF','decimals'=>2,'decimal'=>',','thousands'=>"'" ];

		// Daten
		$dates = cmxbu_beleg_get_dates($post_id);
		$calc  = cmxbu_get_beleg_positionen_calc($post_id, ['round_decimals'=>2,'round_lines'=>true,'round_totals'=>true,'currency'=>$fmt['currency']]);
		$me    = cmxbu_get_me_contact(); // var_dump(cmxbu_get_me_contact()); exit;

		$bank  = cmxbu_get_preferred_bank();
// var_dump($dates['currency']); exit;



		// Platzhalter für Vorlage
		$tpl = [
			'branding'=>['logo'=>$branding_logo,'website'=>$opts['website']??''],
			'labels'=>$labels,
			'format'=>$fmt,
			'document'=>[
				'type'=>$beleg_type,
				'number'=>$title_safe,
				'title'=>$doc_label.' '.$title_safe,              // <- C
				'date'=> date('d.m.Y', strtotime($dates['date_invoice'])),
				'due'=>date('d.m.Y', strtotime($dates['date_due'])),
				'currency'=>$dates['currency'],

				'period'=>$dates['period'],
				'subtotal'=>$calc['subtotal'],
				'total'=>$calc['total'],
				'subject'=> (string)get_post_meta($post_id,'_cmx_beleg_betreff',true),
				'description'=>(string)get_post_meta($post_id,'_cmx_beleg_beschreibung',true), // <- A (wird im Template direkt unter Betreff ausgegeben)
			],
			'positions'=>array_map(function($p){
				return [
					'article_number'=>(string)($p['article_number'] ?? ''),
					'item'=>(string)($p['title'] ?? ''),
					'qty'=>(float)($p['qty'] ?? 0),
					'unit_price'=>(float)($p['unit_price'] ?? 0),
					'line_total'=>(float)($p['line_total'] ?? 0),
					'discount'=>(string)($p['discount'] ?? ''),
					'has_discount'=>(bool)($p['has_discount'] ?? false),
					'desc_text'=>(string)($p['desc_text'] ?? ''),
					'desc_html'=>(string)($p['desc_html'] ?? ''),
					'desc_raw' =>(string)($p['desc_raw']  ?? ''),
					'article_belegtext_html'=>(string)($p['article_belegtext_html'] ?? ''), // <- B (Template zeigt diese Spalte)
				];
			}, $calc['positionen']),
			'any_discount'=> (bool)($calc['any_discount'] ?? false),
			'me'=>$me,
			'bank'=>$bank,
		];

		// Vorlage laden & rendern
		$tpl_dir  = trailingslashit(defined('CMX_PLUGIN_DIR') ? CMX_PLUGIN_DIR : plugin_dir_path(__FILE__)) . 'src/belege/';
		// error_log($tpl_dir);
		$tpl_path = $tpl_dir . 'vorlage_html.php'; // ein gemeinsames Template (gilt auch für Angebot/Lfr.-Rechnung etc.)
		if (!is_file($tpl_path)) { cmxbu_log('FEHLER: Vorlage fehlt.',['path'=>$tpl_path]); return; }

		ob_start(); include $tpl_path; $html=trim((string)ob_get_clean());
		if (mb_strlen($html,'8bit')<50) { cmxbu_log('FEHLER: HTML leer/zu kurz.'); return; }

		try{
			$opt=new Options();
			$opt->set('isRemoteEnabled', true);
			$opt->set('isHtml5ParserEnabled', true);
			$opt->set('defaultFont', 'DejaVu Sans');

			$dom=new Dompdf($opt);
			$dom->setPaper('A4','portrait');
			$dom->loadHtml($html, 'UTF-8');
			$dom->render();

			$canvas = $dom->getCanvas();
			$font   = $dom->getFontMetrics()->getFont('Helvetica', 'normal');

			// Gesamtseitenzahl ermitteln
			$page_count = $dom->getCanvas()->get_page_count();

			// Nur anzeigen, wenn mehr als eine Seite vorhanden ist
			if ($page_count > 1) {
					$text = "Seite {PAGE_NUM} von {PAGE_COUNT}";

					// Position leicht nach oben versetzt (z. B. über Footer)
					$x = 520;   // X-Position rechts
					$y = 800;   // Y-Position (tiefer = weiter unten, höherer Wert = näher am Seitenrand unten)

					$canvas->page_text(
							$x,
							$y,
							$text,
							$font,
							7,        // A: kleinere Schriftgröße
							[0.5, 0.5, 0.5] // graue Farbe
					);
				}

			$belegname  = $tpl['document']['title'] ?? ''; // z. B. dein Belegtitel


$beleg_titel = $tpl['document']['title'] ?? 'Beleg';

$canvas = $dom->getCanvas();
$canvas->page_script(function($pageNum, $pageCount, $canvas, $fontMetrics) use ($beleg_titel) {
    if ($pageNum > 1) {
        // Schrift
        $font = $fontMetrics->getFont('Helvetica', 'bold');

        // Kopftext (innerhalb des reservierten Bereichs)
        $x = 50;   // links
        $y = 55;   // liegt oberhalb des Inhaltsbereichs (weil @page margin-top 90px reserviert)
        $canvas->text($x, $y, $beleg_titel, $font, 11, [0.1, 0.1, 0.1]);

        // Optional: Trennlinie unter dem Kopf
        $canvas->line(50, 70, 545, 70, [0.7, 0.7, 0.7]);
    }
});






// $dom->stream('beleg.pdf', ['Attachment' => false]);

			if (@file_put_contents($pdf_path,$dom->output())===false) { cmxbu_log('FEHLER: PDF konnte nicht geschrieben werden.',['path'=>$pdf_path]); return; }
			cmxbu_log('PDF erstellt',['pdf'=>$pdf_path]);
		}catch(\Throwable $e){ cmxbu_log('EXCEPTION DOMPDF',['msg'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]); }

		if (is_file($html_path)) { @unlink($html_path); cmxbu_log('HTML entfernt (Cleanup).',['path'=>$html_path]); }
	}
