<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');
use Dompdf\Dompdf;
use Dompdf\Options;

if (!defined(__NAMESPACE__.'\\CMX_PDF_DEBUG')) { define(__NAMESPACE__.'\\CMX_PDF_DEBUG', true); }
if (!function_exists(__NAMESPACE__.'\\cmx_pdf_log')) {
	function cmx_pdf_log(string $msg, array $ctx = []): void {
		if (!CMX_PDF_DEBUG) return;
		error_log('[CMX PDF] '.$msg.($ctx ? ' | '.json_encode($ctx, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : ''));
	}
}

$autoload = trailingslashit(defined('CMX_PLUGIN_DIR') ? CMX_PLUGIN_DIR : plugin_dir_path(__FILE__)).'vendor/autoload.php';
if (is_file($autoload)) { require_once $autoload; cmx_pdf_log('Composer autoload geladen.'); }

add_action('save_post_belege', __NAMESPACE__.'\\cmx_generate_document_on_save', 999, 3);

/** ---- Fallback-Loader + SHIMs (vereinfacht, MwSt=0) ---- */
function cmx_try_load_beleg_helpers(): void {
	$base = trailingslashit(defined('CMX_PLUGIN_DIR') ? CMX_PLUGIN_DIR : plugin_dir_path(__FILE__));
	foreach (['src/belege/functions-belege.php','src/belege/calc.php','src/belege/index.php','includes/helpers.php'] as $rel) {
		$path = $base.$rel;
		if (is_file($path)) { require_once $path; cmx_pdf_log('geladene Datei', ['file'=>$rel]); }
	}
	// Positions-/Summen-Helper (MwSt wird ignoriert)
	if (!function_exists(__NAMESPACE__.'\\cmx_get_beleg_positionen')) {
		function cmx_get_beleg_positionen(int $post_id, array $opts=[]): array {
			$opts = array_replace([
				'round_decimals'=>2,'round_lines'=>true,'round_totals'=>true,
			], $opts);

			$raw = get_post_meta($post_id, '_cmx_beleg_positionen', true);
			if (is_string($raw) && $raw !== '') {
				$tmp = json_decode($raw, true);
				if (json_last_error() !== JSON_ERROR_NONE) $tmp = @unserialize($raw);
				$rows = is_array($tmp) ? $tmp : [];
			} elseif (is_array($raw)) { $rows = $raw; } else { $rows = []; }

			$nf = function($v){ return is_numeric($v) ? (float)$v : 0.0; };
			$sf = function($v){ return is_string($v) ? $v : ''; };

			$out = ['positionen'=>[], 'subtotal'=>0.0, 'tax_total'=>0.0, 'total'=>0.0, 'tax_groups'=>[]];

			foreach ($rows as $i => $r) {
				if (!is_array($r)) $r = ['title'=>(string)$r];

				// Robustes Mapping (de/en):
				$artnr   = $sf($r['sku'] ?? $r['artnr'] ?? $r['artikelnummer'] ?? $r['nr'] ?? '');
				$title   = $sf($r['title'] ?? $r['artikel'] ?? $r['name'] ?? '');
				$desc    = $sf($r['description'] ?? $r['beschreibung'] ?? $r['desc'] ?? '');
				$qty     = $nf($r['qty'] ?? $r['menge'] ?? $r['amount'] ?? 1);
				$unit    = $sf($r['unit'] ?? $r['einheit'] ?? '');
				$uprice  = $nf($r['unit_price'] ?? $r['einzelpreis'] ?? $r['preis'] ?? $r['price'] ?? 0);
				$discount= $r['discount'] ?? $r['rabatt'] ?? 0;

				// Rabatt → absolut pro Einheit
				$disc_abs = 0.0;
				if ($discount !== null && $discount !== '') {
					$dv = (float)$discount;
					if ($dv > 0 && $dv < 1)        $disc_abs = $uprice * $dv;          // 0.1 = 10%
					elseif ($dv >= 1 && $dv <=100) $disc_abs = $uprice * ($dv/100.0);   // 10 = 10%
					else                           $disc_abs = $dv;                      // absolut
				}
				$net_unit = max($uprice - $disc_abs, 0);
				$line_net = $qty * $net_unit; // MwSt=0 → Netto = Zeilensumme

				if (!empty($opts['round_lines'])) $line_net = round($line_net, (int)$opts['round_decimals']);

				$out['positionen'][] = [
					'index'=>$i,
					'article_number'=>$artnr,
					'title'=>$title,
					'desc'=>$desc,
					'qty'=>$qty,
					'unit'=>$unit,
					'unit_price'=>round($uprice, (int)$opts['round_decimals']),
					'line_total'=>$line_net,   // alias ohne MwSt
					'raw'=>$r,
				];

				$out['subtotal'] += $line_net;
			}

			if (!empty($opts['round_totals'])) {
				$out['subtotal'] = round($out['subtotal'], (int)$opts['round_decimals']);
			}
			$out['tax_total'] = 0.0;
			$out['total']     = $out['subtotal']; // MwSt=0

			return $out;
		}
	}
	if (!function_exists(__NAMESPACE__.'\\cmx_get_beleg_data')) {
		function cmx_get_beleg_data(int $post_id, array $opts=[]): array {
			$p = get_post($post_id);
			if (!$p) return [];
			$keys = [
				'_cmx_rechnungsnummer','_cmx_beleg_betreff','_cmx_beleg_beschreibung',
				'_cmx_beleg_kontakt','_cmx_beleg_kontakt_addr','_cmx_beleg_positionen',
				'_cmx_beleg_waehrung','_cmx_beleg_rng_datum','_cmx_beleg_faellig_am','_cmx_beleg_leistungsmonat',
			];
			$d = ['ID'=>$p->ID,'post_title'=>$p->post_title,'post_content'=>$p->post_content,'post_date'=>$p->post_date,'post_status'=>$p->post_status];
			foreach ($keys as $k) { $v = get_post_meta($post_id, $k, true); $d[$k] = ($v === '') ? null : $v; }

			$d['date_invoice'] = $d['_cmx_beleg_rng_datum'] ? date('Y-m-d', strtotime((string)$d['_cmx_beleg_rng_datum'])) : date('Y-m-d');
			// „Fällig bis“ Fallback: Option due_days oder +10 Tage
			$due_raw = $d['_cmx_beleg_faellig_am'] ?: null;
			if (!$due_raw) {
				$opts_local = (array)get_option('cmx_einstellungen', []);
				$days = isset($opts_local['due_days']) ? (int)$opts_local['due_days'] : 10;
				$due_raw = date('Y-m-d', strtotime($d['date_invoice'].' +'.$days.' days'));
			}
			$d['date_due'] = date('Y-m-d', strtotime((string)$due_raw));
			$d['period_month'] = $d['_cmx_beleg_leistungsmonat'] ? date('Y-m', strtotime((string)$d['_cmx_beleg_leistungsmonat'])) : '';

			// Kontaktanzeige
			if (!empty($d['_cmx_beleg_kontakt']) && (int)$d['_cmx_beleg_kontakt'] > 0) {
				if ($kp = get_post((int)$d['_cmx_beleg_kontakt'])) { $d['kontakt'] = ['title'=>$kp->post_title,'meta'=>get_post_meta($kp->ID)]; }
			} elseif (!empty($d['_cmx_beleg_kontakt_addr'])) {
				$d['kontakt'] = ['raw'=>$d['_cmx_beleg_kontakt_addr']];
			} else { $d['kontakt'] = ['raw'=>'']; }

			$calc = cmx_get_beleg_positionen($post_id, $opts);
			$d['positionen'] = $calc['positionen'];
			$d['summen']     = ['subtotal'=>$calc['subtotal'], 'tax_total'=>0.0, 'total'=>$calc['total']];
			$d['tax_groups'] = []; // entfernt

			return $d;
		}
	}
}

/** ---- Generator ---- */
function cmx_generate_document_on_save(int $post_id, \WP_Post $post, bool $update): void {
	if ($post->post_type !== 'belege') return;
	if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) { cmx_pdf_log('ABBRUCH: Revision/Autosave.', compact('post_id')); return; }
	if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) { cmx_pdf_log('ABBRUCH: DOING_AUTOSAVE.'); return; }
	if (!current_user_can('edit_post', $post_id)) { cmx_pdf_log('ABBRUCH: Permission.'); return; }

	static $in_progress = [];
	if (!empty($in_progress[$post_id])) { cmx_pdf_log('ABBRUCH: bereits in Arbeit.', compact('post_id')); return; }
	$in_progress[$post_id] = true;

	$beleg_type = 'rechnung';
	foreach (['belege_kategorien','beleg_kategorie'] as $tax) {
		$slugs = wp_get_post_terms($post_id, $tax, ['fields'=>'slugs']);
		if (!is_wp_error($slugs) && !empty($slugs)) { $beleg_type = (string)$slugs[0]; break; }
	}

	if (!defined('CMX_UPLOADS_MISBUERO') || !CMX_UPLOADS_MISBUERO) {
		$up = wp_get_upload_dir();
		if (!defined(__NAMESPACE__.'\\CMX_UPLOADS_MISBUERO')) define(__NAMESPACE__.'\\CMX_UPLOADS_MISBUERO', trailingslashit($up['basedir']).'misbuero/');
	}
	$base_dir = rtrim(CMX_UPLOADS_MISBUERO, '/').'/'.date('Y').'/';
	if (!wp_mkdir_p($base_dir) || !is_writable($base_dir)) { cmx_pdf_log('FEHLER: Upload-Verzeichnis', ['dir'=>$base_dir]); return; }

	$title_raw  = (string)get_the_title($post_id);
	$title_safe = $title_raw !== '' ? $title_raw : (string)$post_id;
	$basename   = sanitize_title($title_safe.'_'.$beleg_type);
	$html_path  = $base_dir.$basename.'.html';
	$pdf_path   = $base_dir.$basename.'.pdf';

	$opts = (array)get_option('cmx_einstellungen', []);
	$branding_logo = isset($opts['beleg_logo_url']) ? esc_url($opts['beleg_logo_url']) : 'https://vorlage.misbuero.ch/wp-content/uploads/favicon.png';
	$shop = [
		'company'=>$opts['company']??'CLOUD Meister','strasse'=>$opts['street']??'Beispielstrasse 1','plz'=>$opts['zip']??'8000',
		'ort'=>$opts['city']??'Zürich','land'=>$opts['country']??'CH','phone'=>$opts['phone']??'+41 44 000 00 00',
		'email'=>$opts['email']??'invoice@example.ch','support'=>$opts['support_email']??'support@example.ch',
		'website'=>$opts['website']??'https://cloudmeister.ch','mwst_nr'=>'', // MwSt entfernt
	];
	$bank = [[
		'bank_name'=>$opts['bank_name']??'Bank','iban'=>$opts['iban']??'CH00 0000 0000 0000 0000 0','bic'=>$opts['bic']??'ABCDEFGH',
	]];
	$labels = array_replace([
		'doc_invoice'=>'Rechnung','date'=>'Datum','due'=>'Fällig bis','period'=>'Leistung für',
		'item'=>'Artikel','artnr'=>'Artikel-Nr.','desc'=>'Beschreibung','qty'=>'Menge','unit_price'=>'Einzelpreis',
		'line_total'=>'Summe','total'=>'Gesamtbetrag','note_payment'=>'Hinweis zur Zahlung','terms'=>'AGB',
		'includes_fees'=>'Alle Preise beinhalten bereits sämtliche Bankgebühren.',
		'no_deduction'=>'Der Gesamtbetrag ist ohne Abzug von Skonto oder Bankspesen vollständig zu entrichten.',
		'recipient'=>'Empfänger','bank'=>'Bank','contact'=>'Kontakt',
	], (array)($opts['labels']??[]));

	$calc_opts = array_replace(['currency'=>$opts['currency']??'CHF','round_decimals'=>2,'round_lines'=>true,'round_totals'=>true], (array)($opts['calc']??[]));

	cmx_try_load_beleg_helpers();
	if (!function_exists(__NAMESPACE__.'\\cmx_get_beleg_data')) { cmx_pdf_log('FEHLER: cmx_get_beleg_data() nicht verfügbar.'); return; }

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
			'title'=>$labels['doc_invoice'].' '.(string)($doc['_cmx_rechnungsnummer'] ?: $title_safe),
			'date'=>$doc['date_invoice'] ?: date('Y-m-d'),
			'due'=>$doc['date_due'] ?: '',
			'period'=>$doc['period_month'] ?: '',
			'subject'=>$doc['_cmx_beleg_betreff'] ?: '',          // NEU: Betreff
			'description'=>$doc['_cmx_beleg_beschreibung'] ?: '', // NEU: Beschreibung
			'subtotal'=>(float)($doc['summen']['subtotal'] ?? 0),
			'total'=>(float)($doc['summen']['total'] ?? 0),       // MwSt=0
		],
		'contact'=>[
			'display'=>(function($d){
				if (!empty($d['kontakt']['raw'])) return (string)$d['kontakt']['raw'];
				if (!empty($d['kontakt']['meta'])) {
					$m = $d['kontakt']['meta']; $lines=[];
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
				'article_number'=>(string)($p['article_number'] ?? ''),
				'item'          =>(string)($p['title'] ?? ''),
				'desc'          =>(string)($p['desc'] ?? $p['raw']['description'] ?? ''),
				'qty'           =>(float) ($p['qty'] ?? 0),
				'unit_price'    =>(float) ($p['unit_price'] ?? 0),
				'line_total'    =>(float) ($p['line_total'] ?? $p['line_net'] ?? 0),
			];
		}, $doc['positionen'] ?? []),
	];

	$tpl_dir  = trailingslashit(defined('CMX_PLUGIN_DIR') ? CMX_PLUGIN_DIR : plugin_dir_path(__FILE__)).'vorlagen/';
	$tpl_path = $tpl_dir.'rechnung.php';
	if (!is_file($tpl_path)) { cmx_pdf_log('FEHLER: Vorlage rechnung.php nicht gefunden.', ['path'=>$tpl_path]); return; }

	ob_start(); include $tpl_path; $html = trim((string)ob_get_clean());
	if (mb_strlen($html, '8bit') < 50) { cmx_pdf_log('FEHLER: HTML leer/zu kurz.', ['tpl'=>basename($tpl_path)]); return; }
	if (CMX_PDF_DEBUG) { @file_put_contents($html_path, $html); cmx_pdf_log('HTML gespeichert', ['path'=>$html_path]); }

	try {
		$opt = new Options(); $opt->set('isRemoteEnabled', true);
		$dompdf = new Dompdf($opt); $dompdf->setPaper('A4', 'portrait'); $dompdf->loadHtml($html); $dompdf->render();
		if (@file_put_contents($pdf_path, $dompdf->output()) === false) { cmx_pdf_log('FEHLER: PDF konnte nicht geschrieben werden.', ['path'=>$pdf_path]); return; }
		cmx_pdf_log('PDF erstellt', ['pdf'=>$pdf_path]);
	} catch (\Throwable $e) { cmx_pdf_log('EXCEPTION in DOMPDF', ['msg'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]); return; }

	// Debug-HTML optional entfernen
	// if (!CMX_PDF_DEBUG && is_file($html_path)) { @unlink($html_path); }
}
