<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

use Dompdf\Dompdf;
use Dompdf\Options;

/* ===== PDF Link ===== */
\add_action('admin_post_cmx_export_belege_list_pdf', function(){
	if (!\current_user_can('edit_posts')) \wp_die('Keine Berechtigung.');
	if (!cmxbu_belege_export_verify_nonce('cmx_export_belege_list_pdf')) \wp_die('Ungültige Anfrage.');
	cmxbu_belege_export_require_date_range_or_redirect();

	$post_ids = cmxbu_belege_export_collect_ids();

	$options = new Options();
	$options->set('isRemoteEnabled', true);
	$dom = new Dompdf($options);
	$branding_logo = \function_exists(__NAMESPACE__ . '\\cmx_get_branding_logo') ? (string) cmx_get_branding_logo() : '';
	$branding_logo_html = $branding_logo !== ''
		? '<img class="header-logo" src="'.\esc_url($branding_logo).'" alt="Das bin ich Logo">'
		: '';

	$rows_html = '';
	foreach ($post_ids as $pid) {
		$post = \get_post($pid);
		if (!$post) continue;

		$belegnr = (string) $post->post_title;
		$belegdatum = (string) \get_post_meta($pid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_RNG_DATUM') ? CMX_BELEG_META_RNG_DATUM : '_cmx_beleg_rng_datum', true);
		if ($belegdatum === '') {
			$belegdatum = \get_date_from_gmt((string) $post->post_date_gmt, 'Y-m-d');
		}

		$kontakt_label = (string) \get_post_meta($pid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_LABEL') ? CMX_BELEG_META_KONTAKT_LABEL : '_cmx_beleg_kontakt_label', true);
		$kontakt_id = (int) \get_post_meta($pid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ID') ? CMX_BELEG_META_KONTAKT_ID : '_cmx_beleg_kontakt_id', true);
		$kunde = $kontakt_label !== '' ? $kontakt_label : ($kontakt_id ? (\get_the_title($kontakt_id) ?: '') : '');

		$belegtyp = '';
		if (function_exists(__NAMESPACE__ . '\\cmx_get_beleg_type')) {
			[, $belegtyp] = cmx_get_beleg_type($post);
		}
		if ($belegtyp === '') {
			$tax = function_exists(__NAMESPACE__ . '\\cmx_belege_taxonomy') ? cmx_belege_taxonomy() : '';
			if ($tax && \taxonomy_exists($tax)) {
				$terms = \wp_get_post_terms($pid, $tax, ['fields' => 'slugs']);
				if (!\is_wp_error($terms) && !empty($terms)) $belegtyp = (string)$terms[0];
			}
		}

		$total = 0.0;
		if (function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
			$calc = cmxbu_get_beleg_positionen_calc($pid);
			$total = (float)($calc['total'] ?? 0);
			$has_positions = cmxbu_beleg_has_positions($calc);
			if (!$has_positions) {
				$override = (string) \get_post_meta($pid, '_cmx_beleg_summe_override', true);
				if ($override !== '') {
					$total = (float) cmx_norm_decimal($override);
				}
			}
		}
		$total_str = number_format((float)$total, 2, ',', "'");

		$rows_html .= '<tr>'
			.'<td>'.\esc_html($belegnr).'</td>'
			.'<td>'.\esc_html($belegtyp).'</td>'
			.'<td>'.\esc_html($belegdatum).'</td>'
			.'<td>'.\esc_html($kunde).'</td>'
			.'<td style="text-align:right;">'.\esc_html($total_str).'</td>'
			.'</tr>';
	}

	$html = '<!doctype html><html><head><meta charset="utf-8"><style>
		body{font-family:DejaVu Sans, Arial, sans-serif;font-size:11px;color:#111}
		.doc-header{margin:0 0 10px 0}
		.doc-header-title{float:left;font-size:18px;font-weight:700;line-height:1.2}
		.doc-header-logo{float:right;text-align:right}
		.doc-header::after{content:"";display:block;clear:both}
		.header-logo{max-width:150px;max-height:36px;height:auto;width:auto}
		table{width:100%;border-collapse:collapse}
		th,td{border:1px solid #ddd;padding:6px}
		th{background:#f3f4f6;text-align:left}
	</style></head><body>
	<div class="doc-header">
		<div class="doc-header-title">Milchbüchli</div>
		<div class="doc-header-logo">'.$branding_logo_html.'</div>
	</div>
	<table>
	<thead><tr>
	<th>Belegnummer</th><th>Belegtyp</th><th>Datum</th><th>Kunde</th><th>Total</th>
	</tr></thead><tbody>'.$rows_html.'</tbody></table>
	</body></html>';

	$dom->loadHtml($html, 'UTF-8');
	$dom->setPaper('A4', 'portrait');
	$dom->render();

	$filename = cmxbu_belege_export_filename('pdf');
	header('Content-Type: application/pdf');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	echo $dom->output();
	exit;
});
