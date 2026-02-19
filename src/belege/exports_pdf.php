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
	$headers = \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_headers')
		? cmxbu_beleg_export_headers()
		: [
			'Belegnummer','Bezahlt am','Belegtyp','Kontakt','Zahlungsart',
			'Zahlungsgrund','MwSt-Satz','MwSt','Vorsteuer','Einnahmen','Ausgaben'
		];
	$rows = \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_rows_from_ids')
		? cmxbu_beleg_export_rows_from_ids($post_ids)
		: [];
	$range = \function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_requested_date_range')
		? (array) cmxbu_belege_export_requested_date_range()
		: ['from' => '', 'to' => ''];
	$preset_key = \function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_requested_preset')
		? (string) cmxbu_belege_export_requested_preset()
		: 'benutzerdefiniert';
	$presets = \function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_presets')
		? (array) cmxbu_belege_export_presets()
		: [];
	$preset_label = (string) ($presets[$preset_key] ?? 'Benutzerdefiniert');
	$fmt_date = static function(string $ymd): string {
		$ymd = \trim($ymd);
		if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
			$ts = \strtotime($ymd . ' 00:00:00');
			if ($ts) return \date('d.m.Y', $ts);
		}
		return $ymd;
	};
	$range_from = $fmt_date((string) ($range['from'] ?? ''));
	$range_to = $fmt_date((string) ($range['to'] ?? ''));
	$range_line = 'Zeitraum: ' . $preset_label . ' | Von: ' . $range_from . ' | Bis: ' . $range_to;
	// MwSt-Spalten (6,7,8) bewusst deutlich schmaler als Standardspalten (>=30% kleiner).
	$col_widths = [10, 9, 8, 13, 10, 11, 4.2, 4.2, 4.2, 13, 13.4];
	$colgroup_html = '';
	foreach ($headers as $idx => $head) {
		$width = (float) ($col_widths[$idx] ?? 9);
		$colgroup_html .= '<col style="width:' . \esc_attr((string) $width) . '%">';
	}

	$header_html = '';
	foreach ($headers as $idx => $head) {
		$align = ($idx >= 6) ? ' style="text-align:right;"' : '';
		$header_html .= '<th' . $align . '>' . \esc_html((string) $head) . '</th>';
	}

	$rows_html = '';
	foreach ($rows as $row) {
		$cells_html = '';
		foreach ($headers as $idx => $head) {
			$val = (string) ($row[$idx] ?? '');
			$align = ($idx >= 6) ? ' style="text-align:right;"' : '';
			$cells_html .= '<td' . $align . '>' . \esc_html($val) . '</td>';
		}
		$rows_html .= '<tr>' . $cells_html . '</tr>';
	}
	if ($rows_html === '') {
		$rows_html = '<tr><td colspan="' . (int) \count($headers) . '">Keine Daten im gewählten Zeitraum.</td></tr>';
	}

	$html = '<!doctype html><html><head><meta charset="utf-8"><style>
		body{font-family:DejaVu Sans, Arial, sans-serif;font-size:9px;color:#111}
		.doc-header{margin:0 0 10px 0}
		.doc-header-title{float:left;font-size:18px;font-weight:700;line-height:1.2}
		.doc-header-title .doc-header-subtitle{display:block;font-size:10px;font-weight:400;color:#444;margin-top:3px}
		.doc-header-logo{float:right;text-align:right}
		.doc-header::after{content:"";display:block;clear:both}
		.header-logo{max-width:150px;max-height:36px;height:auto;width:auto}
		table{width:100%;border-collapse:separate;border-spacing:0;table-layout:fixed}
		th,td{padding:6px;border:0}
		thead th{font-weight:700;background:#eceff1;text-align:left;white-space:nowrap}
		tbody td{word-wrap:break-word;border-top:1px solid #edf0f2}
		tbody tr:nth-child(odd) td{background:#f7f8fa}
		tbody tr:nth-child(even) td{background:#ffffff}
	</style></head><body>
	<div class="doc-header">
		<div class="doc-header-title">Milchbüchli<span class="doc-header-subtitle">'.\esc_html($range_line).'</span></div>
		<div class="doc-header-logo">'.$branding_logo_html.'</div>
	</div>
	<table>
	<colgroup>'.$colgroup_html.'</colgroup>
	<thead><tr>
	'.$header_html.'
	</tr></thead><tbody>'.$rows_html.'</tbody></table>
	</body></html>';

	$dom->loadHtml($html, 'UTF-8');
	$dom->setPaper('A4', 'landscape');
	$dom->render();

	$filename = cmxbu_belege_export_filename('pdf');
	header('Content-Type: application/pdf');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	echo $dom->output();
	exit;
});
