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
	$row_items = \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_rows_from_ids')
		? cmxbu_beleg_export_rows_from_ids($post_ids, true)
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
	$range_line_html = 'Zeitraum: <strong>' . \esc_html($preset_label) . '</strong> | Von: <strong>' . \esc_html($range_from) . '</strong> | Bis: <strong>' . \esc_html($range_to) . '</strong>';
	$table_headers = \array_merge([''], $headers);
	// Erste Spalte: klickbarer [pdf]-Link, Belegnummer breiter + nowrap, Kontakt etwas kleiner.
	$col_widths = [0.05, 11.9, 8, 7, 16, 8, 9, 2, 2, 2, 14.25, 18.3];
	$col_classes = [
		'col-open',
		'col-belegnummer',
		'col-bezahlt-am',
		'col-belegtyp',
		'col-kontakt',
		'col-zahlungsart',
		'col-zahlungsgrund',
		'col-mwst-satz',
		'col-mwst',
		'col-vorsteuer',
		'col-einnahmen',
		'col-ausgaben',
	];
	$colgroup_html = '';
	foreach ($table_headers as $idx => $head) {
		$width = (float) ($col_widths[$idx] ?? 9);
		$class = (string) ($col_classes[$idx] ?? ('col-' . $idx));
		$colgroup_html .= '<col class="' . \esc_attr($class) . '" style="width:' . \esc_attr((string) $width) . '%">';
	}

	$header_html = '';
	foreach ($table_headers as $idx => $head) {
		$class = (string) ($col_classes[$idx] ?? ('col-' . $idx));
		$align = ($idx >= 7) ? ' style="text-align:right;"' : (($idx === 0) ? ' style="text-align:center;"' : '');
		$header_html .= '<th class="' . \esc_attr($class) . '"' . $align . '>' . \esc_html((string) $head) . '</th>';
	}

	$rows_html = '';
	$token_cache = [];
	$upload_cache = [];
	$ucfirst_utf8 = static function (string $text): string {
		$text = (string) $text;
		if ($text === '') return '';
		if (\function_exists('mb_substr') && \function_exists('mb_strtoupper')) {
			return \mb_strtoupper(\mb_substr($text, 0, 1, 'UTF-8'), 'UTF-8') . \mb_substr($text, 1, null, 'UTF-8');
		}
		return \strtoupper(\substr($text, 0, 1)) . \substr($text, 1);
	};
	foreach ((array) $row_items as $item) {
		$post_id = 0;
		$row = [];
		if (\is_array($item) && \array_key_exists('row', $item)) {
			$row = (array) ($item['row'] ?? []);
			$post_id = (int) ($item['post_id'] ?? 0);
		} else {
			$row = (array) $item;
		}
		$pdf_url = '';
		$upload_url = '';
		if ($post_id > 0 && \function_exists(__NAMESPACE__ . '\\cmxbu_get_stable_token')) {
			if (!\array_key_exists($post_id, $token_cache)) {
				$token_cache[$post_id] = (string) cmxbu_get_stable_token($post_id);
			}
			$token = (string) ($token_cache[$post_id] ?? '');
			if ($token !== '') {
				$pdf_url = (string) \add_query_arg('beleg', $token, \home_url('/'));
				if (!\array_key_exists($post_id, $upload_cache)) {
					$upload_cache[$post_id] = (
						\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_primary_upload_abs_path')
						&& (string) cmxbu_get_beleg_primary_upload_abs_path($post_id) !== ''
					);
				}
				if (!empty($upload_cache[$post_id])) {
					$upload_url = (string) \add_query_arg(
						['beleg' => $token, 'quelle' => 'upload'],
						\home_url('/')
					);
				}
			}
		}

		$display_row = \array_merge([''], $row);
		$cells_html = '';
		foreach ($table_headers as $idx => $head) {
			$class = (string) ($col_classes[$idx] ?? ('col-' . $idx));
			$align = ($idx >= 7) ? ' style="text-align:right;"' : (($idx === 0) ? ' style="text-align:center;"' : '');

				if ($idx === 0) {
					$icon_target = $upload_url !== '' ? $upload_url : $pdf_url;
					$icon_title = $upload_url !== '' ? 'Upload-Beleg anzeigen' : 'Beleg als PDF anzeigen';
						$icon_html = $icon_target !== ''
							? '<a class="pdf-icon-link" href="' . \esc_url($icon_target) . '" target="_blank" rel="noopener noreferrer" title="' . \esc_attr($icon_title) . '"><span class="pdf-link-text">[pdf]</span></a>'
							: '';
					$cells_html .= '<td class="' . \esc_attr($class) . '"' . $align . '>' . $icon_html . '</td>';
					continue;
			}

				$val = (string) ($display_row[$idx] ?? '');
				if ($class === 'col-belegtyp') {
					$val = $ucfirst_utf8($val);
				}
				$cell_html = \esc_html($val);
			if ($idx === 1 && $val !== '' && $pdf_url !== '') {
				$cell_html = '<a class="beleg-link" href="' . \esc_url($pdf_url) . '" target="_blank" rel="noopener noreferrer" title="Beleg als PDF anzeigen">' . \esc_html($val) . '</a>';
			}
			$cells_html .= '<td class="' . \esc_attr($class) . '"' . $align . '>' . $cell_html . '</td>';
		}
		$rows_html .= '<tr>' . $cells_html . '</tr>';
	}
	if ($rows_html === '') {
		$rows_html = '<tr><td colspan="' . (int) \count($table_headers) . '">Keine Daten im gewählten Zeitraum.</td></tr>';
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
		thead th{font-weight:700;background:transparent;text-align:left;white-space:normal}
		tbody td{word-wrap:break-word;border-top:1px solid #edf0f2}
		tbody tr:nth-child(odd) td{background:transparent}
		tbody tr:nth-child(even) td{background:#f7f8fa}
		thead th.col-mwst-satz, thead th.col-mwst, thead th.col-vorsteuer{font-size:8px}
		col.col-mwst-satz, col.col-mwst, col.col-vorsteuer{width:2% !important}
		col.col-kontakt{width:16% !important}
		th.col-kontakt, td.col-kontakt{width:16% !important}
		col.col-open{width:8px !important}
		col.col-belegnummer{width:11.9% !important}
		th.col-belegnummer, td.col-belegnummer{white-space:nowrap}
		td.col-kontakt{font-size:9.2px}
		th.col-open, td.col-open{text-align:center;width:8px !important;min-width:8px !important;max-width:8px !important;padding-left:0 !important;padding-right:0 !important;white-space:nowrap;overflow:hidden}
		.pdf-icon-link{display:inline-block;text-decoration:none;line-height:1}
		.pdf-link-text{display:inline-block;color:#a42c24;font-weight:700;font-size:6px;line-height:1;white-space:nowrap;letter-spacing:-0.5px;transform:scaleX(0.55);transform-origin:center center}
		.beleg-link{color:#111;text-decoration:underline}
		</style></head><body>
	<div class="doc-header">
		<div class="doc-header-title">Milchbüchli<span class="doc-header-subtitle">'.$range_line_html.'</span></div>
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
