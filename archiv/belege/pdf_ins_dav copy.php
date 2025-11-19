<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

use Dompdf\Dompdf;
use Dompdf\Options;

/** =========================
 * KONFIG
 * ========================= */
const CMX_PDF_DEBUG = false;

/** =========================
 * Logger (optional)
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
} else {
	cmx_pdf_log('FEHLER: composer autoload nicht gefunden.', ['path' => $autoload]);
}

/** =========================
 * Hooks
 * ========================= */
add_action('save_post_belege', __NAMESPACE__.'\\cmx_generate_document_on_save', 20, 3);

/**
 * Schweizer Bargeldrundung & MwSt-Gruppen werden in cmx_get_beleg_positionen / cmx_get_beleg_data unterstützt.
 * Hier konfigurieren wir nur die Optionen und geben der Vorlage ein einziges $tpl-Array.
 */
function cmx_generate_document_on_save(int $post_id, \WP_Post $post, bool $update): void {
	// 0) Schutz
	if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
	if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) return;
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
	if ($post->post_type !== 'belege') return;

	$allowed_status = ['publish','draft','pending','future'];
	if (!in_array($post->post_status, $allowed_status, true)) return;

	// 1) Belegtyp (Taxonomie) bestimmen
	$beleg_type = 'rechnung';
	foreach (['belege_kategorien','beleg_kategorie'] as $tax) {
		$slugs = wp_get_post_terms($post_id, $tax, ['fields' => 'slugs']);
		if (!is_wp_error($slugs) && !empty($slugs)) { $beleg_type = (string)$slugs[0]; break; }
	}

	// 2) Dateinamen vorbereiten
	$title_raw  = (string) get_the_title($post_id);
	$title_safe = $title_raw !== '' ? $title_raw : (string) $post_id;
	$basename   = sanitize_title($title_safe . '_' . $beleg_type);

	// 3) Upload-Ziel
	if (!defined('CMX_UPLOADS_MISBUERO')) {
		cmx_pdf_log('FEHLER: CMX_UPLOADS_MISBUERO nicht definiert.');
		return;
	}
	$base_dir = rtrim(CMX_UPLOADS_MISBUERO, '/').'/'.date('Y').'/';
	if (!wp_mkdir_p($base_dir) || !is_writable($base_dir)) {
		cmx_pdf_log('FEHLER: Upload-Verzeichnis Problem.', ['dir' => $base_dir]);
		return;
	}
	$html_path = $base_dir . $basename . '.html';
	$pdf_path  = $base_dir . $basename . '.pdf';

	// 4) Plugin-Optionen einlesen (Branding, Shop, Bank, Kontakt, Labels)
	$opts           = (array) get_option('cmx_einstellungen', []);
	$branding_logo  = isset($opts['beleg_logo_url']) ? esc_url($opts['beleg_logo_url']) : 'https://vorlage.misbuero.ch/wp-content/uploads/favicon.png';

	$shop = [
		'company' => $opts['company']      ?? 'CLOUD Meister',
		'strasse' => $opts['street']       ?? 'Beispielstrasse 1',
		'plz'     => $opts['zip']          ?? '8000',
		'ort'     => $opts['city']         ?? 'Zürich',
		'land'    => $opts['country']      ?? 'CH',
		'phone'   => $opts['phone']        ?? '+41 44 000 00 00',
		'email'   => $opts['email']        ?? 'invoice@example.ch',
		'support' => $opts['support_email']?? 'support@example.ch',
		'website' => $opts['website']      ?? 'https://cloudmeister.ch',
		'mwst_nr' => $opts['vat_no']       ?? '',
	];

	$bank = [
		[
			'bank_name' => $opts['bank_name'] ?? 'Bank',
			'iban'      => $opts['iban']      ?? 'CH00 0000 0000 0000 0000 0',
			'bic'       => $opts['bic']       ?? 'ABCDEFGH',
		]
	];

	$labels = array_replace([
		'doc_invoice'   => 'Rechnung',
		'doc_delivery'  => 'Lieferschein',
		'date'          => 'Datum',
		'due'           => 'Fällig bis',
		'period'        => 'Leistung für',
		'qty'           => 'Menge',
		'unit'          => 'Einheit',
		'item'          => 'Artikel',
		'desc'          => 'Beschreibung',
		'unit_price'    => 'Einzelpreis',
		'subtotal'      => 'Zwischensumme',
		'tax'           => 'MwSt',
		'total'         => 'Gesamtbetrag',
		'total_excl_vat'=> 'exkl. MwSt.',
		'tax_group'     => 'MwSt-Sätze',
		'note_payment'  => 'Hinweis zur Zahlung',
		'terms'         => 'AGB',
		'includes_fees' => 'Alle Preise beinhalten bereits sämtliche Bankgebühren.',
		'no_deduction'  => 'Der Gesamtbetrag ist ohne Abzug von Skonto oder Bankspesen vollständig zu entrichten.',
		'bank'          => 'Bank',
		'recipient'     => 'Empfänger',
		'contact'       => 'Kontakt',
	], (array) ($opts['labels'] ?? []));

	// 5) Rundungs-/Berechnungsoptionen (Schweiz: Bargeldrundung nur auf Total)
	$calc_opts = array_replace([
		'currency'               => $opts['currency'] ?? 'CHF',
		'round_mode'             => $opts['round_mode'] ?? 'cash_0_05',
		'cash_round_only_totals' => true,
		'round_decimals'         => 2,
		'round_lines'            => true,
		'round_totals'           => true,
		'round_strategy'         => 'per_line_then_total',
	], (array) ($opts['calc'] ?? []));

	// 6) Belegdaten + Positionen
	if (!function_exists(__NAMESPACE__.'\\cmx_get_beleg_data')) {
		// Falls nicht inkludiert: hier abbrechen (oder Datei includen)
		cmx_pdf_log('FEHLER: cmx_get_beleg_data() nicht verfügbar.');
		return;
	}
	$doc = cmx_get_beleg_data($post_id, $calc_opts);

	// 7) Platzhalter-Array für die Vorlage
	$tpl = [
		'branding' => [
			'logo'    => $branding_logo,
			'website' => $shop['website'],
		],
		'shop'     => $shop,
		'bank'     => $bank,
		'labels'   => $labels,
		'format'   => [
			'currency'   => $calc_opts['currency'],
			'decimals'   => (int) $calc_opts['round_decimals'],
			'thousands'  => "'",
			'decimal'    => '.',
			'date_fmt'   => 'Y-m-d',
		],
		'document' => [
			'type'        => $beleg_type,
			'number'      => (string) ($doc['_cmx_rechnungsnummer'] ?: $title_safe),
			'title'       => ($beleg_type === 'rechnung' ? $labels['doc_invoice'] : ucfirst($beleg_type)) . ' ' . (string) ($doc['_cmx_rechnungsnummer'] ?: $title_safe),
			'date'        => $doc['date_invoice'] ?: date('Y-m-d'),
			'due'         => $doc['date_due'] ?: '',
			'period'      => $doc['period_month'] ?: '',
			'description' => $doc['_cmx_beleg_beschreibung'] ?: '',
			'subtotal'    => (float) $doc['summen']['subtotal'],
			'tax_total'   => (float) $doc['summen']['tax_total'],
			'total'       => (float) $doc['summen']['total'],
		],
		'contact'  => [
			// Entweder strukturierter Kontakt oder raw-String
			'display' => (function($d){
				if (!empty($d['kontakt']['raw'])) return (string)$d['kontakt']['raw'];
				if (!empty($d['kontakt']['meta'])) {
					$m = $d['kontakt']['meta'];
					$lines = [];
					$lines[] = isset($d['kontakt']['title']) ? (string)$d['kontakt']['title'] : '';
					$street  = isset($m['_street'][0]) ? $m['_street'][0] : '';
					$zip     = isset($m['_zip'][0])    ? $m['_zip'][0]    : '';
					$city    = isset($m['_city'][0])   ? $m['_city'][0]   : '';
					$country = isset($m['_country'][0])? $m['_country'][0]: 'CH';
					if ($street) $lines[] = $street;
					$lines[] = trim($country.'-'.$zip.' '.$city);
					return implode("\n", array_filter($lines));
				}
				return '';
			})($doc),
		],
		'positions'=> array_map(function($p){
			return [
				'item'        => (string) ($p['title'] ?? ''),
				'desc'        => (string) ($p['raw']['description'] ?? ''),
				'qty'         => (float)  ($p['qty'] ?? 0),
				'unit'        => (string) ($p['unit'] ?? ''),
				'unit_price'  => (float)  ($p['unit_price'] ?? 0),
				'line_net'    => (float)  ($p['line_net'] ?? 0),
				'line_tax'    => (float)  ($p['line_tax'] ?? 0),
				'line_gross'  => (float)  ($p['line_gross'] ?? 0),
				'tax_rate'    => (float)  ($p['tax_rate'] ?? 0),
			];
		}, $doc['positionen'] ?? []),
		'tax_groups' => array_values(array_map(function($g){
			return [
				'rate'  => (float)$g['rate'],   // 0.077
				'net'   => (float)$g['net'],
				'tax'   => (float)$g['tax'],
				'gross' => (float)$g['gross'],
				'count' => (int)$g['count'],
			];
		}, $doc['tax_groups'] ?? [])),
		'legal' => [
			'terms_url'    => $shop['website'] ? rtrim($shop['website'], '/').'/agb/' : 'https://cloudmeister.ch/agb/',
			'include_fees' => true,
			'text_include_fees' => $labels['includes_fees'],
			'text_no_deduction' => $labels['no_deduction'],
		],
		'footer' => [
			'show_page_count' => true,
		],
	];

	// 8) Vorlage laden
	$tpl_dir  = trailingslashit(defined('CMX_PLUGIN_DIR') ? CMX_PLUGIN_DIR : plugin_dir_path(__FILE__)).'vorlagen/';
	$tpl_path = $tpl_dir . 'rechnung.php';
	if (!is_file($tpl_path)) {
		cmx_pdf_log('FEHLER: Vorlage rechnung.php nicht gefunden.', ['path' => $tpl_path]);
		return;
	}

	ob_start();
	// Für die Vorlage steht NUR $tpl zur Verfügung:
	/** @var array $tpl */
	include $tpl_path;
	$html = trim((string) ob_get_clean());

	if (mb_strlen($html, '8bit') < 50) {
		cmx_pdf_log('FEHLER: HTML leer/zu kurz.', ['tpl' => basename($tpl_path)]);
		return;
	}

	// Debug-HTML speichern
	if (CMX_PDF_DEBUG) {
		@file_put_contents($html_path, $html);
	}

	// 9) DOMPDF rendern
	try {
		$options = new Options();
		$options->set('isRemoteEnabled', true);

		$dompdf = new Dompdf($options);
		$dompdf->setPaper('A4', 'portrait');
		$dompdf->loadHtml($html);
		$dompdf->render();

		$pdf_output = $dompdf->output();
		$bytes = @file_put_contents($pdf_path, $pdf_output);
		if ($bytes === false) {
			cmx_pdf_log('FEHLER: PDF konnte nicht geschrieben werden.', ['path' => $pdf_path]);
			return;
		}
	} catch (\Throwable $e) {
		cmx_pdf_log('EXCEPTION in DOMPDF', ['msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
		return;
	}

	// Aufräumen
	if (!CMX_PDF_DEBUG && is_file($html_path)) {
		@unlink($html_path);
	}

	// Legacy abräumen (optional)
	$legacy_pdf = $base_dir . '_' . $beleg_type . '.pdf';
	if (is_file($legacy_pdf)) @unlink($legacy_pdf);
}
