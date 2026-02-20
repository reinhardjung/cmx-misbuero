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
	$row_items = \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_rows_from_ids')
		? cmxbu_beleg_export_rows_from_ids($post_ids, true)
		: [];
	if (\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_sort_context_rows_by_paid_date')) {
		$row_items = cmxbu_beleg_export_sort_context_rows_by_paid_date((array) $row_items);
	}
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
	$render_rows = [];
	$sum_mwst = 0.0;
	$sum_vorsteuer = 0.0;
	$sum_einnahmen = 0.0;
	$sum_ausgaben = 0.0;
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
	$to_float = static function ($value): float {
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_to_float')) {
			return (float) cmxbu_beleg_export_to_float($value);
		}
		$txt = \trim((string) $value);
		if ($txt === '') return 0.0;
		$txt = \str_replace(["'", ' '], '', $txt);
		$txt = \str_replace(',', '.', $txt);
		return \is_numeric($txt) ? (float) $txt : 0.0;
	};
	$paid_date_to_ts = static function (string $display_date): int {
		$display_date = \trim(\str_replace("\xC2\xA0", ' ', $display_date));
		if ($display_date === '') return 0;
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_paid_date_timestamp_from_display')) {
			return (int) cmxbu_beleg_export_paid_date_timestamp_from_display($display_date);
		}
		if (\preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $display_date, $m)) {
			$d = (int) $m[1];
			$mo = (int) $m[2];
			$y = (int) $m[3];
			if (\checkdate($mo, $d, $y)) {
				$ts = \strtotime(\sprintf('%04d-%02d-%02d 00:00:00', $y, $mo, $d));
				return $ts ? (int) $ts : 0;
			}
		}
		$ts = \strtotime($display_date);
		return $ts ? (int) $ts : 0;
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
		$sum_mwst += $to_float($display_row[8] ?? 0);
		$sum_vorsteuer += $to_float($display_row[9] ?? 0);
		$sum_einnahmen += $to_float($display_row[10] ?? 0);
		$sum_ausgaben += $to_float($display_row[11] ?? 0);
		$is_ausgabe_row = $to_float($display_row[11] ?? 0) > 0.0;
		$icon_target = ($is_ausgabe_row && $upload_url !== '') ? $upload_url : '';
		$icon_title = 'Upload-Dokument anzeigen';
		$open_content = $icon_target !== ''
			? '<a class="pdf-icon-link" href="' . \esc_url($icon_target) . '" target="_blank" rel="noopener noreferrer" title="' . \esc_attr($icon_title) . '"><span class="pdf-link-text">[*]</span></a>'
			: '';

		$belegnummer = (string) ($display_row[1] ?? '');
		$belegnummer_content = \esc_html($belegnummer);
		if ($belegnummer !== '' && $pdf_url !== '') {
			$belegnummer_content = '<a class="beleg-link" href="' . \esc_url($pdf_url) . '" target="_blank" rel="noopener noreferrer" title="Beleg als PDF anzeigen">' . \esc_html($belegnummer) . '</a>';
		}

		$belegtyp = $ucfirst_utf8((string) ($display_row[3] ?? ''));

		$render_rows[] = [
			'row_idx' => \count($render_rows),
			'paid_ts' => $paid_date_to_ts((string) ($display_row[2] ?? '')),
			'open' => $open_content,
			'belegnummer' => $belegnummer_content,
			'bezahlt_am' => \esc_html((string) ($display_row[2] ?? '')),
			'belegtyp' => \esc_html($belegtyp),
			'kontakt' => \esc_html((string) ($display_row[4] ?? '')),
			'zahlungsart' => \esc_html((string) ($display_row[5] ?? '')),
			'zahlungsgrund' => \esc_html((string) ($display_row[6] ?? '')),
			'mwst_satz' => \esc_html((string) ($display_row[7] ?? '')),
			'mwst' => \esc_html((string) ($display_row[8] ?? '')),
			'vorsteuer' => \esc_html((string) ($display_row[9] ?? '')),
			'einnahmen' => \esc_html((string) ($display_row[10] ?? '')),
			'ausgaben' => \esc_html((string) ($display_row[11] ?? '')),
		];
	}
	if (\count($render_rows) > 1) {
		\usort($render_rows, static function (array $a, array $b): int {
			$ats = (int) ($a['paid_ts'] ?? 0);
			$bts = (int) ($b['paid_ts'] ?? 0);
			if ($ats !== $bts) return $bts <=> $ats; // newest paid date first
			$ai = (int) ($a['row_idx'] ?? 0);
			$bi = (int) ($b['row_idx'] ?? 0);
			return $ai <=> $bi;
		});
	}
	$sum_einnahmen_display = \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_format_money')
		? (string) cmxbu_beleg_export_format_money($sum_einnahmen)
		: \number_format((float) \round($sum_einnahmen, 2), 2, '.', "'");
	$sum_ausgaben_display = \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_format_money')
		? (string) cmxbu_beleg_export_format_money($sum_ausgaben)
		: \number_format((float) \round($sum_ausgaben, 2), 2, '.', "'");
	$sum_mwst_display = \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_format_money')
		? (string) cmxbu_beleg_export_format_money($sum_mwst)
		: \number_format((float) \round($sum_mwst, 2), 2, '.', "'");
	$sum_vorsteuer_display = \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_format_money')
		? (string) cmxbu_beleg_export_format_money($sum_vorsteuer)
		: \number_format((float) \round($sum_vorsteuer, 2), 2, '.', "'");
	$sum_diff = (float) $sum_einnahmen - (float) $sum_ausgaben;
	$sum_diff_display = \function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')
		? (string) cmx_format_swiss_number($sum_diff, 2)
		: \number_format((float) \round($sum_diff, 2), 2, '.', "'");
	$sum_xxx = (float) $sum_mwst - (float) $sum_vorsteuer;
	$sum_xxx_display = \function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')
		? (string) cmx_format_swiss_number($sum_xxx, 2)
		: \number_format((float) \round($sum_xxx, 2), 2, '.', "'");
	$sum_zzz = (float) $sum_diff - (float) $sum_xxx;
	$sum_zzz_display = \function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')
		? (string) cmx_format_swiss_number($sum_zzz, 2)
		: \number_format((float) \round($sum_zzz, 2), 2, '.', "'");
	ob_start();
	?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<style>
		body{font-family:DejaVu Sans, Arial, sans-serif;font-size:9px;color:#111}
		.doc-header{margin:0 0 10px 0}
		.doc-header-title{float:left;font-size:18px;font-weight:700;line-height:1.2}
		.doc-header-title .doc-header-subtitle{display:block;font-size:10px;font-weight:400;color:#444;margin-top:3px}
		.doc-header-logo{float:right;text-align:right}
		.doc-header::after{content:"";display:block;clear:both}
		.header-logo{max-width:150px;max-height:36px;height:auto;width:auto}
			table{width:100%;border-collapse:collapse;table-layout:auto}
			th,td{padding:6px;border:none}
			thead th{font-weight:700;background:transparent;text-align:left;white-space:normal}
			.line-row-cell{padding:0 !important;height:1px;line-height:1px;font-size:0;background:#000;border:none !important}
		tbody td{word-wrap:break-word}
		tbody tr:nth-child(odd) td{background:transparent}
		tbody tr:nth-child(even) td{background:#f7f8fa}
		thead th.col-mwst-satz, thead th.col-mwst, thead th.col-vorsteuer{font-size:8px}
		th.col-kontakt, td.col-kontakt{width:16% !important}
		th.col-belegnummer, td.col-belegnummer{white-space:nowrap}
		td.col-kontakt{font-size:9.2px}
		th.col-open, td.col-open{text-align:center}
		.pdf-icon-link{display:inline-block;text-decoration:none;line-height:1}
		.pdf-link-text{display:inline-block;color:#a42c24;font-weight:700;font-size:8px;line-height:1;white-space:nowrap}
		.beleg-link{color:#111;text-decoration:underline}
	</style>
</head>
<body>
	<div class="doc-header">
		<div class="doc-header-title">
			Milchbüchli
			<span class="doc-header-subtitle">
				Zeitraum: <strong><?= \esc_html($preset_label); ?></strong> | Von: <strong><?= \esc_html($range_from); ?></strong> | Bis: <strong><?= \esc_html($range_to); ?></strong>
			</span>
		</div>
		<div class="doc-header-logo">
			<?php if ($branding_logo !== ''): ?>
				<img class="header-logo" src="<?= \esc_url($branding_logo); ?>" alt="Das bin ich Logo">
			<?php endif; ?>
		</div>
	</div>

		<table>
			<thead>
			<tr>
				<th style="text-align:center;width:20px;"></th>
				<th style="width:80px;">Belegnummer</th>
				<th style="width:60px">Bezahlt am</th>
				<th style="width:70px;">Belegtyp</th>
				<th style="">Kontakt</th>
				<th style="width:80px">Zahlungsart</th>
				<th style="width:90px;">Zahlungsgrund</th>
				<th style="text-align:center;width:50px;">Satz</th>
				<th style="text-align:center;width:50px;">MwSt</th>
				<th style="text-align:center;width:50px">Vorsteuer</th>
				<th style="text-align:right;width:90px;">Einnahmen</th>
				<th style="text-align:right;width:90px;">Ausgaben</th>
			</tr>
			<tr>
				<th colspan="12" class="line-row-cell"></th>
			</tr>
			</thead>
			<tbody>
			<?php if (empty($render_rows)): ?>
				<tr>
					<td colspan="12">Keine Daten im gewählten Zeitraum.</td>
				</tr>
				<?php else: ?>
					<?php foreach ($render_rows as $row_view): ?>
						<tr>
							<td style="text-align:center;"><?= $row_view['open']; ?></td>
							<td><?= $row_view['belegnummer']; ?></td>
							<td><?= $row_view['bezahlt_am']; ?></td>
							<td><?= $row_view['belegtyp']; ?></td>
							<td><?= $row_view['kontakt']; ?></td>
							<td><?= $row_view['zahlungsart']; ?></td>
							<td><?= $row_view['zahlungsgrund']; ?></td>
							<td style="text-align:right;"><?= $row_view['mwst_satz']; ?></td>
							<td style="text-align:right;"><?= $row_view['mwst']; ?></td>
							<td style="text-align:right;"><?= $row_view['vorsteuer']; ?></td>
							<td style="text-align:right;"><?= $row_view['einnahmen']; ?></td>
							<td style="text-align:right;"><?= $row_view['ausgaben']; ?></td>
						</tr>
					<?php endforeach; ?>
					<?php endif; ?>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="12" class="line-row-cell"></td>
				</tr>
			</tfoot>
		</table>

	<table>
	<tr>
		<td></td>
		<td style="text-align:right;width:50px;"><?= $sum_mwst_display; ?></td>
		<td style="text-align:right;width:50px;"><?= $sum_vorsteuer_display; ?></td>
		<td style="text-align:right;width:90px;"><strong><?= $sum_einnahmen_display; ?></strong></td>
		<td style="text-align:right;width:90px;"><strong><?= $sum_ausgaben_display; ?></strong></td>
	</tr>
	</table>

	<table>
	<tr>
		<td></td>
		<td style="text-align:right;width:50px;"><strong>Summe</strong></td>
		<td style="text-align:right;width:100px;"><strong><?= $sum_diff_display; ?></strong></td>
	</tr>
	</table>

	<table>
	<tr>
		<td></td>
		<td style="text-align:right;width:50px;">Märlistüür</td>
		<td style="text-align:right;width:100px;"><?= $sum_xxx_display; ?></td>
	</tr>
	</table>

	<table>
	<tr>
		<td></td>
		<td style="text-align:right;width:50px;border-bottom:3px double #000;"><strong>Gewinn</strong></td>
		<td style="text-align:right;width:100px;border-bottom:3px double #000;"><strong><?= $sum_zzz_display; ?></strong></td>
	</tr>
	</table>

</body>
</html>

<?php
	$html = (string) \ob_get_clean();

	$dom->loadHtml($html, 'UTF-8');
	$dom->setPaper('A4', 'landscape');
	$dom->render();

	$filename = cmxbu_belege_export_filename('pdf');
	header('Content-Type: application/pdf');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	echo $dom->output();
	exit;
});
