<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

use Dompdf\Dompdf;
use Dompdf\Options;

if (!\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_csv_string_from_ids')) {
	function cmxbu_belege_export_csv_string_from_ids(array $ids): string {
		$fh = \fopen('php://temp', 'w+');
		if ($fh === false) {
			return '';
		}

		\fwrite($fh, "\xEF\xBB\xBF");
		$headers = \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_headers')
			? (array) cmxbu_beleg_export_headers()
			: [];
		if (!empty($headers)) {
			\fputcsv($fh, $headers, ';', '"', '\\');
		}

		$rows = \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_rows_from_ids')
			? (array) cmxbu_beleg_export_rows_from_ids($ids)
			: [];
		foreach ($rows as $row) {
			\fputcsv($fh, (array) $row, ';', '"', '\\');
		}

		\rewind($fh);
		$content = (string) \stream_get_contents($fh);
		\fclose($fh);
		return $content;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_pdf_binary_from_ids')) {
	function cmxbu_belege_export_pdf_binary_from_ids(array $post_ids): string {
			$options = new Options();
			$options->set('isRemoteEnabled', true);
			$dom = new Dompdf($options);
			$branding_logo = \function_exists(__NAMESPACE__ . '\\cmx_get_branding_logo') ? (string) cmx_get_branding_logo() : '';
			if (\function_exists(__NAMESPACE__ . '\\cmxbu_prepare_png_for_dompdf')) {
				$branding_logo = (string) cmxbu_prepare_png_for_dompdf((string) $branding_logo);
			}
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
			$context_sort_ts = 0;
			$context_sort_seq = 0;
			$row = [];
			if (\is_array($item) && \array_key_exists('row', $item)) {
				$row = (array) ($item['row'] ?? []);
				$post_id = (int) ($item['post_id'] ?? 0);
				$context_sort_ts = (int) ($item['sort_ts'] ?? 0);
				$context_sort_seq = (int) ($item['sort_seq'] ?? 0);
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
				? '<a class="pdf-icon-link" href="' . \esc_url($icon_target) . '" target="_blank" rel="noopener noreferrer" title="' . \esc_attr($icon_title) . '"><span class="pdf-link-text">[PDF]</span></a>'
				: '';

			$belegnummer = (string) ($display_row[1] ?? '');
			$belegnummer_content = \esc_html($belegnummer);
			if ($belegnummer !== '' && $pdf_url !== '') {
				$belegnummer_content = '<a class="beleg-link" href="' . \esc_url($pdf_url) . '" target="_blank" rel="noopener noreferrer" title="Beleg als PDF anzeigen">' . \esc_html($belegnummer) . '</a>';
			}

			$belegtyp = $ucfirst_utf8((string) ($display_row[3] ?? ''));
			$paid_ts = $context_sort_ts > 0
				? $context_sort_ts
				: $paid_date_to_ts((string) ($display_row[2] ?? ''));

				$render_rows[] = [
					'row_idx' => \count($render_rows),
					'paid_ts' => $paid_ts,
					'sort_seq' => $context_sort_seq,
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
					'mwst_value' => $to_float($display_row[8] ?? 0),
					'vorsteuer_value' => $to_float($display_row[9] ?? 0),
					'einnahmen_value' => $to_float($display_row[10] ?? 0),
					'ausgaben_value' => $to_float($display_row[11] ?? 0),
				];
			}
		if (\count($render_rows) > 1) {
			\usort($render_rows, static function (array $a, array $b): int {
				$ats = (int) ($a['paid_ts'] ?? 0);
				$bts = (int) ($b['paid_ts'] ?? 0);
				$a_has_date = $ats > 0;
				$b_has_date = $bts > 0;
				if ($a_has_date !== $b_has_date) {
					return $a_has_date ? -1 : 1; // rows without paid date go to the end
				}
				if ($ats !== $bts) return $ats <=> $bts; // oldest paid date first
				$aseq = (int) ($a['sort_seq'] ?? 0);
				$bseq = (int) ($b['sort_seq'] ?? 0);
				if ($aseq !== $bseq) return $aseq <=> $bseq;
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
		$mwst_groups = [];
		$belegtyp_groups = [];
		$zahlungsart_groups = [];
		$zahlungsgrund_groups = [];
		foreach ($render_rows as $row_view) {
			$row_mwst = (float) ($row_view['mwst_value'] ?? $to_float($row_view['mwst'] ?? 0));
			$row_vorsteuer = (float) ($row_view['vorsteuer_value'] ?? $to_float($row_view['vorsteuer'] ?? 0));
			$row_einnahmen = (float) ($row_view['einnahmen_value'] ?? $to_float($row_view['einnahmen'] ?? 0));
			$row_ausgaben = (float) ($row_view['ausgaben_value'] ?? $to_float($row_view['ausgaben'] ?? 0));

			$mwst_label = \trim(\str_replace("\xC2\xA0", ' ', (string) ($row_view['mwst_satz'] ?? '')));
			$mwst_is_without = ($mwst_label === '');
			$mwst_key = $mwst_is_without ? '__without_mwst__' : $mwst_label;
			if (!isset($mwst_groups[$mwst_key])) {
				$mwst_groups[$mwst_key] = [
					'label' => $mwst_is_without ? 'ohne MwSt' : $mwst_label,
					'sort_value' => $mwst_is_without ? 999999.0 : $to_float($mwst_label),
					'sort_without' => $mwst_is_without ? 1 : 0,
					'is_without_rate' => $mwst_is_without ? 1 : 0,
					'rows' => [],
					'sum_mwst' => 0.0,
					'sum_vorsteuer' => 0.0,
					'sum_einnahmen' => 0.0,
					'sum_ausgaben' => 0.0,
				];
			}
			$mwst_groups[$mwst_key]['rows'][] = $row_view;
			$mwst_groups[$mwst_key]['sum_mwst'] += $row_mwst;
			$mwst_groups[$mwst_key]['sum_vorsteuer'] += $row_vorsteuer;
			$mwst_groups[$mwst_key]['sum_einnahmen'] += $row_einnahmen;
			$mwst_groups[$mwst_key]['sum_ausgaben'] += $row_ausgaben;

			$belegtyp_label = \trim(\str_replace("\xC2\xA0", ' ', (string) ($row_view['belegtyp'] ?? '')));
			if ($belegtyp_label === '') {
				$belegtyp_label = 'Ohne Belegtyp';
			}
			if (!isset($belegtyp_groups[$belegtyp_label])) {
				$sort_label = \function_exists('mb_strtolower')
					? \mb_strtolower($belegtyp_label, 'UTF-8')
					: \strtolower($belegtyp_label);
				$belegtyp_groups[$belegtyp_label] = [
					'label' => $belegtyp_label,
					'sort_label' => $sort_label,
					'rows' => [],
					'sum_mwst' => 0.0,
					'sum_vorsteuer' => 0.0,
					'sum_einnahmen' => 0.0,
					'sum_ausgaben' => 0.0,
				];
			}
			$belegtyp_groups[$belegtyp_label]['rows'][] = $row_view;
			$belegtyp_groups[$belegtyp_label]['sum_mwst'] += $row_mwst;
			$belegtyp_groups[$belegtyp_label]['sum_vorsteuer'] += $row_vorsteuer;
			$belegtyp_groups[$belegtyp_label]['sum_einnahmen'] += $row_einnahmen;
			$belegtyp_groups[$belegtyp_label]['sum_ausgaben'] += $row_ausgaben;

			$zahlungsart_label = \trim(\str_replace("\xC2\xA0", ' ', (string) ($row_view['zahlungsart'] ?? '')));
			if ($zahlungsart_label === '') {
				$zahlungsart_label = 'Ohne Zahlungsart';
			}
			if (!isset($zahlungsart_groups[$zahlungsart_label])) {
				$sort_label = \function_exists('mb_strtolower')
					? \mb_strtolower($zahlungsart_label, 'UTF-8')
					: \strtolower($zahlungsart_label);
				$zahlungsart_groups[$zahlungsart_label] = [
					'label' => $zahlungsart_label,
					'sort_label' => $sort_label,
					'rows' => [],
					'sum_mwst' => 0.0,
					'sum_vorsteuer' => 0.0,
					'sum_einnahmen' => 0.0,
					'sum_ausgaben' => 0.0,
				];
			}
			$zahlungsart_groups[$zahlungsart_label]['rows'][] = $row_view;
			$zahlungsart_groups[$zahlungsart_label]['sum_mwst'] += $row_mwst;
			$zahlungsart_groups[$zahlungsart_label]['sum_vorsteuer'] += $row_vorsteuer;
			$zahlungsart_groups[$zahlungsart_label]['sum_einnahmen'] += $row_einnahmen;
			$zahlungsart_groups[$zahlungsart_label]['sum_ausgaben'] += $row_ausgaben;

			$zahlungsgrund_label = \trim(\str_replace("\xC2\xA0", ' ', (string) ($row_view['zahlungsgrund'] ?? '')));
			if ($zahlungsgrund_label === '') {
				$zahlungsgrund_label = 'Ohne Zahlungsgrund';
			}
			if (!isset($zahlungsgrund_groups[$zahlungsgrund_label])) {
				$sort_label = \function_exists('mb_strtolower')
					? \mb_strtolower($zahlungsgrund_label, 'UTF-8')
					: \strtolower($zahlungsgrund_label);
				$zahlungsgrund_groups[$zahlungsgrund_label] = [
					'label' => $zahlungsgrund_label,
					'sort_label' => $sort_label,
					'rows' => [],
					'sum_mwst' => 0.0,
					'sum_vorsteuer' => 0.0,
					'sum_einnahmen' => 0.0,
					'sum_ausgaben' => 0.0,
				];
			}
			$zahlungsgrund_groups[$zahlungsgrund_label]['rows'][] = $row_view;
			$zahlungsgrund_groups[$zahlungsgrund_label]['sum_mwst'] += $row_mwst;
			$zahlungsgrund_groups[$zahlungsgrund_label]['sum_vorsteuer'] += $row_vorsteuer;
			$zahlungsgrund_groups[$zahlungsgrund_label]['sum_einnahmen'] += $row_einnahmen;
			$zahlungsgrund_groups[$zahlungsgrund_label]['sum_ausgaben'] += $row_ausgaben;
		}
		$mwst_group_items = \array_values($mwst_groups);
		$has_regular_mwst_group = false;
		foreach ($mwst_group_items as $mwst_group_item) {
			if (empty($mwst_group_item['is_without_rate'])) {
				$has_regular_mwst_group = true;
				break;
			}
		}
		if (!$has_regular_mwst_group) {
			$mwst_group_items = [];
		}
		if (\count($mwst_group_items) > 1) {
			\usort($mwst_group_items, static function (array $a, array $b): int {
				$aw = (int) ($a['sort_without'] ?? 0);
				$bw = (int) ($b['sort_without'] ?? 0);
				if ($aw !== $bw) return $aw <=> $bw;
				$av = (float) ($a['sort_value'] ?? 0.0);
				$bv = (float) ($b['sort_value'] ?? 0.0);
				if ($av !== $bv) return $av <=> $bv;
				return \strcmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
			});
		}
		$belegtyp_group_items = \array_values($belegtyp_groups);
		if (\count($belegtyp_group_items) > 1) {
			\usort($belegtyp_group_items, static function (array $a, array $b): int {
				return \strcmp((string) ($a['sort_label'] ?? ''), (string) ($b['sort_label'] ?? ''));
			});
		}
		$zahlungsart_group_items = \array_values($zahlungsart_groups);
		if (\count($zahlungsart_group_items) > 1) {
			\usort($zahlungsart_group_items, static function (array $a, array $b): int {
				return \strcmp((string) ($a['sort_label'] ?? ''), (string) ($b['sort_label'] ?? ''));
			});
		}
		$zahlungsgrund_group_items = \array_values($zahlungsgrund_groups);
		if (\count($zahlungsgrund_group_items) > 1) {
			\usort($zahlungsgrund_group_items, static function (array $a, array $b): int {
				return \strcmp((string) ($a['sort_label'] ?? ''), (string) ($b['sort_label'] ?? ''));
			});
		}
		$belegtyp_pluralize = static function (string $label): string {
			$label = \trim(\str_replace("\xC2\xA0", ' ', $label));
			if ($label === '') return 'Belege';
			$normalized = \function_exists('mb_strtolower')
				? \mb_strtolower($label, 'UTF-8')
				: \strtolower($label);
			$map = [
				'rechnung' => 'Rechnungen',
				'rechnungen' => 'Rechnungen',
				'quittung' => 'Quittungen',
				'quittungen' => 'Quittungen',
				'gutschrift' => 'Gutschriften',
				'gutschriften' => 'Gutschriften',
				'ohne belegtyp' => 'Ohne Belegtyp',
			];
			return (string) ($map[$normalized] ?? $label);
		};
		$requested_appendix_types = \array_fill_keys(
			\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_requested_pdf_appendices')
				? cmxbu_belege_export_requested_pdf_appendices()
				: [],
			true
		);
		$list_sections = [];
		if (isset($requested_appendix_types['mwst'])) {
			foreach ($mwst_group_items as $group) {
				$title = !empty($group['is_without_rate'])
					? 'ohne MwSt'
					: (string) ($group['label'] ?? '');
				if (empty($group['is_without_rate']) && $title !== '' && \strpos($title, '%') === false) {
					$title .= '%';
				}
				if (empty($group['is_without_rate']) && $title !== '' && \stripos($title, 'mwst') === false) {
					$title .= ' MwSt';
				}
				$list_sections[] = [
					'title' => $title,
					'empty_label' => 'Keine Daten für diesen MwSt-Satz.',
					'rows' => (array) ($group['rows'] ?? []),
					'sum_mwst' => (float) ($group['sum_mwst'] ?? 0.0),
					'sum_vorsteuer' => (float) ($group['sum_vorsteuer'] ?? 0.0),
					'sum_einnahmen' => (float) ($group['sum_einnahmen'] ?? 0.0),
					'sum_ausgaben' => (float) ($group['sum_ausgaben'] ?? 0.0),
				];
			}
		}
		if (isset($requested_appendix_types['belegtyp'])) {
			foreach ($belegtyp_group_items as $group) {
				$title = (string) ($group['label'] ?? '');
				if ($title === '') {
					$title = 'Ohne Belegtyp';
				}
				$list_sections[] = [
					'title' => $belegtyp_pluralize($title),
					'empty_label' => 'Keine Daten für diesen Belegtyp.',
					'rows' => (array) ($group['rows'] ?? []),
					'sum_mwst' => (float) ($group['sum_mwst'] ?? 0.0),
					'sum_vorsteuer' => (float) ($group['sum_vorsteuer'] ?? 0.0),
					'sum_einnahmen' => (float) ($group['sum_einnahmen'] ?? 0.0),
					'sum_ausgaben' => (float) ($group['sum_ausgaben'] ?? 0.0),
				];
			}
		}
		if (isset($requested_appendix_types['zahlungsart'])) {
			foreach ($zahlungsart_group_items as $group) {
				$title = (string) ($group['label'] ?? '');
				if ($title === '') {
					$title = 'Ohne Zahlungsart';
				}
				$list_sections[] = [
					'title' => $title,
					'empty_label' => 'Keine Daten für diese Zahlungsart.',
					'rows' => (array) ($group['rows'] ?? []),
					'sum_mwst' => (float) ($group['sum_mwst'] ?? 0.0),
					'sum_vorsteuer' => (float) ($group['sum_vorsteuer'] ?? 0.0),
					'sum_einnahmen' => (float) ($group['sum_einnahmen'] ?? 0.0),
					'sum_ausgaben' => (float) ($group['sum_ausgaben'] ?? 0.0),
				];
			}
		}
		if (isset($requested_appendix_types['zahlungsgrund'])) {
			foreach ($zahlungsgrund_group_items as $group) {
				$title = (string) ($group['label'] ?? '');
				if ($title === '') {
					$title = 'Ohne Zahlungsgrund';
				}
				$list_sections[] = [
					'title' => $title,
					'empty_label' => 'Keine Daten für diesen Zahlungsgrund.',
					'rows' => (array) ($group['rows'] ?? []),
					'sum_mwst' => (float) ($group['sum_mwst'] ?? 0.0),
					'sum_vorsteuer' => (float) ($group['sum_vorsteuer'] ?? 0.0),
					'sum_einnahmen' => (float) ($group['sum_einnahmen'] ?? 0.0),
					'sum_ausgaben' => (float) ($group['sum_ausgaben'] ?? 0.0),
				];
			}
		}

		\ob_start();
		?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
		<style>
			body{font-family:DejaVu Sans, Arial, sans-serif;font-size:9px;color:#111}
			.doc-header{margin:0 0 10px 0}
			.doc-header-title{float:left;font-size:18px;font-weight:700;line-height:1.2}
			.doc-header-title .doc-header-overview{display:inline-block;margin-left:6px;font-size:13px;font-style:italic;font-weight:400;position:relative;top:3px}
			.doc-header-title .doc-header-subtitle{display:block;font-size:10px;font-weight:400;color:#444;margin-top:3px}
		.mwst-page{page-break-before:always}
		.doc-header-logo{float:right;text-align:right}
		.doc-header::after{content:"";display:block;clear:both}
		.header-logo{max-width:150px;max-height:36px;height:auto;width:auto}
		table{width:100%;border-collapse:collapse;table-layout:auto}
		th,td{padding:6px;border:none}
		thead{display:table-row-group}
		thead th{font-weight:700;background:transparent;text-align:left;white-space:normal}
		.line-row-cell{padding:0 !important;height:1px;line-height:1px;font-size:0;background:#000;border:none !important}
			.beleg-table tbody td{word-wrap:break-word}
			.beleg-table tbody tr:nth-child(odd) td{background:transparent}
			.beleg-table tbody tr:nth-child(even) td{background:#f7f8fa}
			thead th.col-mwst-satz, thead th.col-mwst, thead th.col-vorsteuer{font-size:8px}
			th.col-kontakt, td.col-kontakt{width:16% !important}
			th.col-belegnummer, td.col-belegnummer{white-space:nowrap}
			td.col-kontakt{font-size:9.2px}
			th.col-open, td.col-open{text-align:center}
			.result-block{page-break-inside:avoid;break-inside:avoid}
			.pdf-icon-link{display:inline-block;text-decoration:none;line-height:1}
			.pdf-link-text{display:inline-block;color:#a42c24;font-weight:700;font-size:8px;line-height:1;white-space:nowrap}
			.beleg-link{color:#111;text-decoration:underline}
		</style>
</head>
<body>
		<div class="doc-header">
			<div class="doc-header-title">
				Milchbüchli <span class="doc-header-overview">Übersicht</span>
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

		<?php
		$render_rows_indexed = \array_values((array) $render_rows);
		$render_row_count = \count($render_rows_indexed);
		$carry_last_row_with_result = ($render_row_count >= 20);
		$render_last_row = ($carry_last_row_with_result && $render_row_count > 0)
			? (array) $render_rows_indexed[$render_row_count - 1]
			: null;
		$render_main_rows = ($carry_last_row_with_result && $render_row_count > 1)
			? \array_slice($render_rows_indexed, 0, $render_row_count - 1)
			: $render_rows_indexed;
		$render_last_row_number = \is_array($render_last_row) ? (int) $render_row_count : null;
		?>
		<table class="beleg-table">
		<thead>
		<tr>
			<th style="text-align:right;width:26px;">#</th>
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
			<th colspan="13" class="line-row-cell"></th>
		</tr>
		</thead>
		<tbody>
			<?php if (empty($render_rows_indexed)): ?>
				<tr>
					<td colspan="13">Keine Daten im gewählten Zeitraum.</td>
				</tr>
			<?php else: ?>
				<?php foreach ($render_main_rows as $row_index => $row_view): ?>
					<tr>
						<td style="text-align:right;"><?= (int) $row_index + 1; ?></td>
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
			<?php if (!\is_array($render_last_row)): ?>
				<tr>
					<td colspan="13" class="line-row-cell"></td>
				</tr>
			<?php endif; ?>
		</tfoot>
	</table>

			<div class="result-block">
			<?php if (\is_array($render_last_row)): ?>
				<table class="beleg-table">
					<tbody>
						<tr class="beleg-last-row">
							<td style="text-align:right;width:26px;"><?= $render_last_row_number; ?></td>
							<td style="text-align:center;width:20px;"><?= $render_last_row['open']; ?></td>
							<td style="width:80px;"><?= $render_last_row['belegnummer']; ?></td>
							<td style="width:60px;"><?= $render_last_row['bezahlt_am']; ?></td>
							<td style="width:70px;"><?= $render_last_row['belegtyp']; ?></td>
							<td><?= $render_last_row['kontakt']; ?></td>
							<td style="width:80px;"><?= $render_last_row['zahlungsart']; ?></td>
							<td style="width:90px;"><?= $render_last_row['zahlungsgrund']; ?></td>
							<td style="text-align:right;width:50px;"><?= $render_last_row['mwst_satz']; ?></td>
							<td style="text-align:right;width:50px;"><?= $render_last_row['mwst']; ?></td>
							<td style="text-align:right;width:50px;"><?= $render_last_row['vorsteuer']; ?></td>
							<td style="text-align:right;width:90px;"><?= $render_last_row['einnahmen']; ?></td>
							<td style="text-align:right;width:90px;"><?= $render_last_row['ausgaben']; ?></td>
						</tr>
					</tbody>
				</table>
				<?php endif; ?>
			<?php if (\is_array($render_last_row)): ?>
				<table>
				<tr>
					<td colspan="13" class="line-row-cell"></td>
				</tr>
				</table>
			<?php endif; ?>
			<table>
			<tr>
				<td></td>
				<td style="text-align:right;width:50px;"><?= $sum_mwst_display; ?></td>
				<td style="text-align:right;width:50px;"><?= $sum_vorsteuer_display; ?></td>
				<td style="text-align:right;width:90px;"><strong><?= $sum_einnahmen_display; ?></strong></td>
				<td style="text-align:right;width:90px;"><strong><?= $sum_ausgaben_display; ?></strong></td>
			</tr>
			</table>
			<br>
			<table>
			<tr>
				<td></td>
				<td style="text-align:right;width:50px;"><strong>Ergebnis</strong></td>
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
		</div>

	<?php foreach ($list_sections as $section): ?>
		<?php
		$section_title = (string) ($section['title'] ?? '');
		$section_rows = (array) ($section['rows'] ?? []);
		$section_empty_label = (string) ($section['empty_label'] ?? 'Keine Daten.');
		$section_mwst_sum_display = \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_format_money')
			? (string) cmxbu_beleg_export_format_money((float) ($section['sum_mwst'] ?? 0.0))
			: \number_format((float) \round((float) ($section['sum_mwst'] ?? 0.0), 2), 2, '.', "'");
		$section_vorsteuer_sum_display = \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_format_money')
			? (string) cmxbu_beleg_export_format_money((float) ($section['sum_vorsteuer'] ?? 0.0))
			: \number_format((float) \round((float) ($section['sum_vorsteuer'] ?? 0.0), 2), 2, '.', "'");
		$section_einnahmen_sum_display = \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_format_money')
			? (string) cmxbu_beleg_export_format_money((float) ($section['sum_einnahmen'] ?? 0.0))
			: \number_format((float) \round((float) ($section['sum_einnahmen'] ?? 0.0), 2), 2, '.', "'");
		$section_ausgaben_sum_display = \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_format_money')
			? (string) cmxbu_beleg_export_format_money((float) ($section['sum_ausgaben'] ?? 0.0))
			: \number_format((float) \round((float) ($section['sum_ausgaben'] ?? 0.0), 2), 2, '.', "'");
		?>
		<div class="mwst-page">
			<div class="doc-header">
				<div class="doc-header-title">
					Milchbüchli <span class="doc-header-overview"><?= \esc_html($section_title); ?></span>
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

				<table class="beleg-table">
					<thead>
				<tr>
					<th style="text-align:right;width:26px;">#</th>
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
					<th colspan="13" class="line-row-cell"></th>
				</tr>
				</thead>
				<tbody>
				<?php if (empty($section_rows)): ?>
					<tr>
						<td colspan="13"><?= \esc_html($section_empty_label); ?></td>
					</tr>
				<?php else: ?>
					<?php foreach ($section_rows as $row_index => $row_view): ?>
						<tr>
							<td style="text-align:right;"><?= (int) $row_index + 1; ?></td>
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
						<td colspan="13" class="line-row-cell"></td>
					</tr>
					<tr>
						<td colspan="9"></td>
						<td style="text-align:right;"><strong><?= \esc_html($section_mwst_sum_display); ?></strong></td>
						<td style="text-align:right;"><strong><?= \esc_html($section_vorsteuer_sum_display); ?></strong></td>
						<td style="text-align:right;"><strong><?= \esc_html($section_einnahmen_sum_display); ?></strong></td>
						<td style="text-align:right;"><strong><?= \esc_html($section_ausgaben_sum_display); ?></strong></td>
					</tr>
				</tfoot>
			</table>
		</div>
	<?php endforeach; ?>

</body>
</html>
<?php
		$html = (string) \ob_get_clean();
		$dom->loadHtml($html, 'UTF-8');
		$dom->setPaper('A4', 'landscape');
		$dom->render();
		return (string) $dom->output();
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbu_belege_zip_unique_path')) {
	function cmxbu_belege_zip_unique_path(string $path, array &$used): string {
		$path = \ltrim(\str_replace('\\', '/', $path), '/');
		if ($path === '') {
			$path = 'datei';
		}
		$candidate = $path;
		$idx = 2;
		while (isset($used[$candidate])) {
			$ext = \pathinfo($path, \PATHINFO_EXTENSION);
			$name = \pathinfo($path, \PATHINFO_FILENAME);
			$dir = \pathinfo($path, \PATHINFO_DIRNAME);
			$dir = ($dir === '.' || $dir === '') ? '' : ($dir . '/');
			$candidate = $dir . $name . '-' . $idx;
			if ($ext !== '') {
				$candidate .= '.' . $ext;
			}
			$idx++;
		}
		$used[$candidate] = true;
		return $candidate;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbu_belege_zip_collect_upload_entries')) {
	function cmxbu_belege_zip_collect_upload_entries(array $post_ids): array {
		$entries = [];
		$seen_abs = [];
		$used_zip_paths = [];

		foreach ($post_ids as $raw_pid) {
			$post_id = (int) $raw_pid;
			if ($post_id <= 0) continue;
			$post = \get_post($post_id);
			if (!$post instanceof \WP_Post || $post->post_type !== 'belege') {
				continue;
			}

			$pdf_abs = '';
			if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_pdf_paths')) {
				[, $pdf_abs] = cmxbu_get_beleg_pdf_paths($post);
				$pdf_abs = (string) $pdf_abs;
			}
			$pdf_abs_norm = $pdf_abs !== '' ? \wp_normalize_path($pdf_abs) : '';

			$paths = [];
			if (\function_exists(__NAMESPACE__ . '\\cmxbu_collect_beleg_archive_paths')) {
				$paths = (array) cmxbu_collect_beleg_archive_paths($post_id, $post);
			}

			foreach ($paths as $abs_path_raw) {
				$abs_path = (string) $abs_path_raw;
				if ($abs_path === '' || !\is_file($abs_path)) {
					continue;
				}
				$abs_norm = \wp_normalize_path($abs_path);
				if ($pdf_abs_norm !== '' && $abs_norm === $pdf_abs_norm) {
					continue;
				}
				if (\function_exists(__NAMESPACE__ . '\\cmxbu_is_beleg_archive_abs_path') && !cmxbu_is_beleg_archive_abs_path($abs_path)) {
					continue;
				}
				if (isset($seen_abs[$abs_norm])) {
					continue;
				}
				$seen_abs[$abs_norm] = true;

				$base_name = \sanitize_file_name((string) \basename($abs_path));
				if ($base_name === '') {
					$base_name = 'upload';
				}
				$zip_path = cmxbu_belege_zip_unique_path('belege/' . $base_name, $used_zip_paths);
				$entries[] = [
					'abs' => $abs_path,
					'zip' => $zip_path,
				];
			}
		}

		return $entries;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbu_belege_zip_collect_beleg_pdf_entries')) {
	function cmxbu_belege_zip_collect_beleg_pdf_entries(array $post_ids): array {
		$entries = [];
		$seen_abs = [];
		$used_zip_paths = [];

		foreach ($post_ids as $raw_pid) {
			$post_id = (int) $raw_pid;
			if ($post_id <= 0) continue;
			$post = \get_post($post_id);
			if (!$post instanceof \WP_Post || $post->post_type !== 'belege') {
				continue;
			}
			if (!\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_pdf_paths')) {
				continue;
			}

			[, $pdf_abs] = cmxbu_get_beleg_pdf_paths($post);
			$pdf_abs = (string) $pdf_abs;
			if ($pdf_abs === '' || !\is_file($pdf_abs)) {
				continue;
			}
			if (\function_exists(__NAMESPACE__ . '\\cmxbu_is_beleg_archive_abs_path') && !cmxbu_is_beleg_archive_abs_path($pdf_abs)) {
				continue;
			}

			$pdf_abs_norm = \wp_normalize_path($pdf_abs);
			if (isset($seen_abs[$pdf_abs_norm])) {
				continue;
			}
			$seen_abs[$pdf_abs_norm] = true;

			$base_name = \sanitize_file_name((string) \basename($pdf_abs));
			if ($base_name === '') {
				$base_name = 'beleg-' . $post_id . '.pdf';
			}
			$zip_path = cmxbu_belege_zip_unique_path('rechnungen/' . $base_name, $used_zip_paths);
			$entries[] = [
				'abs' => $pdf_abs,
				'zip' => $zip_path,
			];
		}

		return $entries;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbu_belege_zip_int_list')) {
	function cmxbu_belege_zip_int_list($value): array {
		$out = [];
		$queue = [$value];

		while (!empty($queue)) {
			$item = \array_shift($queue);
			if (\is_array($item)) {
				foreach ($item as $sub) {
					$queue[] = $sub;
				}
				continue;
			}
			if (\is_numeric($item)) {
				$id = (int) $item;
				if ($id > 0) {
					$out[$id] = true;
				}
				continue;
			}
			if (!\is_string($item)) {
				continue;
			}

			$txt = \trim($item);
			if ($txt === '') {
				continue;
			}
			if (\preg_match('/^\d+(?:\s*,\s*\d+)+$/', $txt)) {
				foreach (\explode(',', $txt) as $part) {
					$id = (int) \trim($part);
					if ($id > 0) {
						$out[$id] = true;
					}
				}
				continue;
			}
			if (\preg_match('/^\d+$/', $txt)) {
				$id = (int) $txt;
				if ($id > 0) {
					$out[$id] = true;
				}
				continue;
			}

			$decoded = \json_decode($txt, true);
			if (\json_last_error() === JSON_ERROR_NONE && (\is_array($decoded) || \is_numeric($decoded) || \is_string($decoded))) {
				$queue[] = $decoded;
				continue;
			}

			$maybe = @\maybe_unserialize($txt);
			if (\is_array($maybe) || \is_numeric($maybe) || \is_string($maybe)) {
				$queue[] = $maybe;
			}
		}

		return \array_map('intval', \array_keys($out));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbu_belege_zip_positionen_rows')) {
	function cmxbu_belege_zip_positionen_rows(int $beleg_id): array {
		if ($beleg_id <= 0) {
			return [];
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_positionen_meta_array')) {
			return (array) cmx_beleg_positionen_meta_array($beleg_id);
		}

		$raw = \get_post_meta($beleg_id, '_cmx_beleg_positionen', true);
		if (\is_array($raw)) {
			return $raw;
		}
		if (\is_string($raw) && $raw !== '') {
			$tmp = \json_decode($raw, true);
			if (\json_last_error() === JSON_ERROR_NONE && \is_array($tmp)) {
				return $tmp;
			}
			$tmp = @\maybe_unserialize($raw);
			if (\is_array($tmp)) {
				return $tmp;
			}
		}
		return [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbu_belege_zip_related_entity_ids')) {
	function cmxbu_belege_zip_related_entity_ids(array $beleg_ids): array {
		$kontakt_ids = [];
		$artikel_ids = [];

		$kontakt_meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID')
			: '_cmx_beleg_kontakt_id';

		foreach ($beleg_ids as $raw_beleg_id) {
			$beleg_id = (int) $raw_beleg_id;
			if ($beleg_id <= 0 || (string) \get_post_type($beleg_id) !== 'belege') {
				continue;
			}

			$kontakt_id = (int) \get_post_meta($beleg_id, $kontakt_meta_key, true);
			if ($kontakt_id <= 0 && $kontakt_meta_key !== '_cmx_beleg_kontakt_id') {
				$kontakt_id = (int) \get_post_meta($beleg_id, '_cmx_beleg_kontakt_id', true);
			}
			if ($kontakt_id > 0) {
				$kontakt_ids[$kontakt_id] = true;
			}

			$rows = cmxbu_belege_zip_positionen_rows($beleg_id);
			foreach ($rows as $row) {
				if (!\is_array($row)) {
					continue;
				}
				$artikel_id = (int) ($row['artikel_id'] ?? 0);
				if ($artikel_id > 0) {
					$artikel_ids[$artikel_id] = true;
				}
			}
		}

		return [
			'kontakte' => \array_map('intval', \array_keys($kontakt_ids)),
			'artikel' => \array_map('intval', \array_keys($artikel_ids)),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbu_belege_zip_doc_ids_from_posts')) {
	function cmxbu_belege_zip_doc_ids_from_posts(array $post_ids): array {
		$uploads_meta_key = \defined(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
			: '_cmx_dokumente_uploads';

		$doc_ids = [];
		foreach ($post_ids as $raw_post_id) {
			$post_id = (int) $raw_post_id;
			if ($post_id <= 0) {
				continue;
			}
			$meta_val = \get_post_meta($post_id, $uploads_meta_key, true);
			foreach (cmxbu_belege_zip_int_list($meta_val) as $doc_id) {
				if ($doc_id <= 0 || (string) \get_post_type($doc_id) !== 'dokumente') {
					continue;
				}
				$doc_ids[$doc_id] = true;
			}
		}

		return \array_map('intval', \array_keys($doc_ids));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbu_belege_zip_doc_ids_from_relation_meta')) {
	function cmxbu_belege_zip_doc_ids_from_relation_meta(string $meta_key, array $related_ids): array {
		$meta_key = \trim($meta_key);
		$related_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $related_ids))));
		if ($meta_key === '' || empty($related_ids)) {
			return [];
		}

		$candidate_doc_ids = \get_posts([
			'post_type' => 'dokumente',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'fields' => 'ids',
			'posts_per_page' => -1,
			'no_found_rows' => true,
			'suppress_filters' => true,
			'meta_query' => [
				[
					'key' => $meta_key,
					'compare' => 'EXISTS',
				],
			],
		]);

		if (empty($candidate_doc_ids)) {
			return [];
		}

		$lookup = [];
		foreach ($related_ids as $rid) {
			if ($rid > 0) {
				$lookup[$rid] = true;
			}
		}

		$matched = [];
		foreach ((array) $candidate_doc_ids as $raw_doc_id) {
			$doc_id = (int) $raw_doc_id;
			if ($doc_id <= 0) {
				continue;
			}
			$rel_ids = cmxbu_belege_zip_int_list(\get_post_meta($doc_id, $meta_key, true));
			foreach ($rel_ids as $rid) {
				if (isset($lookup[(int) $rid])) {
					$matched[$doc_id] = true;
					break;
				}
			}
		}

		return \array_map('intval', \array_keys($matched));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbu_belege_zip_abs_upload_path_from_rel')) {
	function cmxbu_belege_zip_abs_upload_path_from_rel(string $file_rel): string {
		$file_rel = \ltrim(\str_replace('\\', '/', $file_rel), '/');
		if ($file_rel === '') {
			return '';
		}

		$uploads_root = \trailingslashit(\wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads')));
		$abs = \wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads/' . $file_rel));
		if ($abs === '' || !\str_starts_with($abs, $uploads_root) || !\is_file($abs)) {
			return '';
		}
		return $abs;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbu_belege_zip_doc_abs_paths_from_doc_id')) {
	function cmxbu_belege_zip_doc_abs_paths_from_doc_id(int $doc_id): array {
		if ($doc_id <= 0 || (string) \get_post_type($doc_id) !== 'dokumente') {
			return [];
		}

		$self_meta_key = \defined(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
			: '_cmx_dokumente_files';

		$abs_paths = [];
		$seen = [];

		$primary_rel = (string) \get_post_meta($doc_id, '_cmx_dokumente_file_path', true);
		$primary_abs = cmxbu_belege_zip_abs_upload_path_from_rel($primary_rel);
		if ($primary_abs !== '') {
			$norm = \wp_normalize_path($primary_abs);
			$seen[$norm] = true;
			$abs_paths[] = $primary_abs;
		}

		$self_files = (array) \get_post_meta($doc_id, $self_meta_key, true);
		foreach ($self_files as $entry) {
			$file_rel = '';
			if (\is_numeric($entry)) {
				$file_rel = (string) \get_post_meta((int) $entry, '_wp_attached_file', true);
			} elseif (\is_string($entry)) {
				$file_rel = $entry;
			}
			$abs = cmxbu_belege_zip_abs_upload_path_from_rel((string) $file_rel);
			if ($abs === '') {
				continue;
			}
			$norm = \wp_normalize_path($abs);
			if (isset($seen[$norm])) {
				continue;
			}
			$seen[$norm] = true;
			$abs_paths[] = $abs;
		}

		return $abs_paths;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbu_belege_zip_collect_dokumente_entries')) {
	function cmxbu_belege_zip_collect_dokumente_entries(array $beleg_ids): array {
		$entries = [];
		$used_zip_paths = [];
		$seen_abs = [];
		$doc_ids = [];

		$beleg_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $beleg_ids))));
		$related = cmxbu_belege_zip_related_entity_ids($beleg_ids);
		$kontakt_ids = (array) ($related['kontakte'] ?? []);
		$artikel_ids = (array) ($related['artikel'] ?? []);

		foreach (cmxbu_belege_zip_doc_ids_from_posts($beleg_ids) as $doc_id) {
			$doc_ids[(int) $doc_id] = true;
		}
		foreach (cmxbu_belege_zip_doc_ids_from_posts($kontakt_ids) as $doc_id) {
			$doc_ids[(int) $doc_id] = true;
		}
		foreach (cmxbu_belege_zip_doc_ids_from_posts($artikel_ids) as $doc_id) {
			$doc_ids[(int) $doc_id] = true;
		}

		$rel_map = \defined(__NAMESPACE__ . '\\CMX_DOK_REL_META')
			? (array) \constant(__NAMESPACE__ . '\\CMX_DOK_REL_META')
			: [];
		$rel_belege = (string) ($rel_map['belege'] ?? 'cmx_dokumente_belege');
		$rel_kontakte = (string) ($rel_map['kontakte'] ?? 'cmx_dokumente_kunden');
		$rel_artikel = (string) ($rel_map['artikel'] ?? 'cmx_dokumente_artikel');

		foreach (cmxbu_belege_zip_doc_ids_from_relation_meta($rel_belege, $beleg_ids) as $doc_id) {
			$doc_ids[(int) $doc_id] = true;
		}
		foreach (cmxbu_belege_zip_doc_ids_from_relation_meta($rel_kontakte, $kontakt_ids) as $doc_id) {
			$doc_ids[(int) $doc_id] = true;
		}
		foreach (cmxbu_belege_zip_doc_ids_from_relation_meta($rel_artikel, $artikel_ids) as $doc_id) {
			$doc_ids[(int) $doc_id] = true;
		}

		foreach (\array_keys($doc_ids) as $raw_doc_id) {
			$doc_id = (int) $raw_doc_id;
			if ($doc_id <= 0) {
				continue;
			}
			$paths = cmxbu_belege_zip_doc_abs_paths_from_doc_id($doc_id);
			foreach ($paths as $abs_path) {
				$abs_norm = \wp_normalize_path((string) $abs_path);
				if ($abs_norm === '' || isset($seen_abs[$abs_norm])) {
					continue;
				}
				$seen_abs[$abs_norm] = true;

				$base_name = \sanitize_file_name((string) \basename((string) $abs_path));
				if ($base_name === '') {
					$base_name = 'dokument-' . $doc_id;
				}
				$zip_path = cmxbu_belege_zip_unique_path('dokumente/' . $base_name, $used_zip_paths);
				$entries[] = [
					'abs' => (string) $abs_path,
					'zip' => $zip_path,
				];
			}
		}

		return $entries;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbu_belege_zip_download_filename')) {
	function cmxbu_belege_zip_download_filename(): string {
		$user_name = '';
		$user = \wp_get_current_user();
		if ($user instanceof \WP_User && $user->exists()) {
			$user_name = (string) $user->user_login;
			if ($user_name === '') {
				$user_name = (string) $user->display_name;
			}
		}
		$user_name = \sanitize_file_name($user_name);
		if ($user_name === '') {
			$user_name = 'user';
		}

		$range_stamp = \function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_range_stamp')
			? (string) cmxbu_belege_export_range_stamp()
			: '';
		$range_stamp = \sanitize_file_name($range_stamp);
		if ($range_stamp === '') {
			$range_stamp = \function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_now_stamp')
				? (string) cmxbu_belege_export_now_stamp()
				: (string) \date('Ymd-His');
			$range_stamp = \sanitize_file_name($range_stamp);
		}

		$prefix = \function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_site_prefix')
			? (string) cmxbu_belege_export_site_prefix()
			: 'misbuero';
		$prefix = \sanitize_file_name($prefix);
		if ($prefix === '') {
			$prefix = 'misbuero';
		}

		return $prefix . '_' . $user_name . '_' . $range_stamp . '.ZIP';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_build_zip_file_from_ids')) {
	function cmxbu_belege_export_build_zip_file_from_ids(array $post_ids, string $zip_abs_path): bool {
		if (!\class_exists('\\ZipArchive')) {
			return false;
		}

		$zip_abs_path = (string) $zip_abs_path;
		if ($zip_abs_path === '') {
			return false;
		}

		$csv_content = cmxbu_belege_export_csv_string_from_ids($post_ids);
		$import_csv_content = '';
		$import_csv_name = '';
		if (
			\function_exists(__NAMESPACE__ . '\\cmxbeg_collect_export_dataset')
			&& \function_exists(__NAMESPACE__ . '\\cmxbeg_csv_string_from_dataset')
			&& \function_exists(__NAMESPACE__ . '\\cmxbeg_export_filename')
		) {
			$import_dataset = (array) cmxbeg_collect_export_dataset($post_ids);
			$import_csv_content = (string) cmxbeg_csv_string_from_dataset($import_dataset);
			$import_csv_name = (string) cmxbeg_export_filename('csv');
		}
		$banana_csv_content = '';
		if (
			\function_exists(__NAMESPACE__ . '\\cmxbu_belege_csv_string')
			&& \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_banana_headers')
			&& \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_banana_rows_from_ids')
			&& \function_exists(__NAMESPACE__ . '\\cmxbu_belege_csv_export_filename_banana')
		) {
			$banana_csv_content = (string) cmxbu_belege_csv_string(
				cmxbu_beleg_export_banana_headers(),
				cmxbu_beleg_export_banana_rows_from_ids($post_ids)
			);
		}
		$pdf_binary = cmxbu_belege_export_pdf_binary_from_ids($post_ids);
		$beleg_pdf_entries = cmxbu_belege_zip_collect_beleg_pdf_entries($post_ids);
		$upload_entries = cmxbu_belege_zip_collect_upload_entries($post_ids);
		$dokumente_entries = cmxbu_belege_zip_collect_dokumente_entries($post_ids);

		$zip = new \ZipArchive();
		if ($zip->open($zip_abs_path, \ZipArchive::OVERWRITE) !== true) {
			return false;
		}

			$zip->addEmptyDir('rechnungen');
			$zip->addEmptyDir('belege');
			$zip->addEmptyDir('bank');
		$zip->addEmptyDir('export');
		$zip->addEmptyDir('dokumente');

		$zip->addFromString('export/' . cmxbu_belege_export_filename('csv'), $csv_content);
		if ($import_csv_content !== '' && $import_csv_name !== '') {
			$zip->addFromString('export/import/' . $import_csv_name, $import_csv_content);
		}
		if ($banana_csv_content !== '') {
			$zip->addFromString('export/' . cmxbu_belege_csv_export_filename_banana(), $banana_csv_content);
		}
		$zip->addFromString(cmxbu_belege_export_filename('pdf'), $pdf_binary);

		foreach ($beleg_pdf_entries as $entry) {
			$abs = (string) ($entry['abs'] ?? '');
			$zip_path = (string) ($entry['zip'] ?? '');
			if ($abs === '' || $zip_path === '' || !\is_file($abs)) {
				continue;
			}
			$zip->addFile($abs, $zip_path);
		}

		foreach ($upload_entries as $entry) {
			$abs = (string) ($entry['abs'] ?? '');
			$zip_path = (string) ($entry['zip'] ?? '');
			if ($abs === '' || $zip_path === '' || !\is_file($abs)) {
				continue;
			}
			$zip->addFile($abs, $zip_path);
		}

		foreach ($dokumente_entries as $entry) {
			$abs = (string) ($entry['abs'] ?? '');
			$zip_path = (string) ($entry['zip'] ?? '');
			if ($abs === '' || $zip_path === '' || !\is_file($abs)) {
				continue;
			}
			$zip->addFile($abs, $zip_path);
		}

		return ($zip->close() === true);
	}
}

/* ===== ZIP Link ===== */
\add_action('admin_post_cmx_export_belege_list', function(){
	if (!\current_user_can('edit_posts')) \wp_die('Keine Berechtigung.');
	if (!cmxbu_belege_export_verify_nonce('cmx_export_belege_list')) \wp_die('Ungültige Anfrage.');
	cmxbu_belege_export_require_date_range_or_redirect();

	if (!\class_exists('\\ZipArchive')) {
		\wp_die('ZIP-Export nicht verfügbar (ZipArchive fehlt).');
	}

	$post_ids = cmxbu_belege_export_collect_ids();

	$tmp_zip = \wp_tempnam('cmx-belege-export-zip');
	if (!\is_string($tmp_zip) || $tmp_zip === '') {
		\wp_die('ZIP-Datei konnte nicht erstellt werden.');
	}

	if (!cmxbu_belege_export_build_zip_file_from_ids($post_ids, $tmp_zip)) {
		@unlink($tmp_zip);
		\wp_die('ZIP-Datei konnte nicht erstellt werden.');
	}

	$download_name = \function_exists(__NAMESPACE__ . '\\cmxbu_belege_zip_download_filename')
		? (string) cmxbu_belege_zip_download_filename()
		: (string) cmxbu_belege_export_filename('zip');
	if (\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_zip_copy_store_from_temp')) {
		cmxbu_belege_export_zip_copy_store_from_temp($tmp_zip, $download_name);
	}
	$size = @\filesize($tmp_zip);

	\ignore_user_abort(true);
	if (\function_exists('set_time_limit')) {
		@\set_time_limit(0);
	}
	while (\ob_get_level() > 0) {
		@\ob_end_clean();
	}
	\nocache_headers();

	header('Content-Type: application/zip');
	header('Content-Disposition: attachment; filename="' . $download_name . '"');
	header('Pragma: no-cache');
	header('Expires: 0');
	if (\is_int($size) || \is_float($size)) {
		header('Content-Length: ' . (int) $size);
	}

	\readfile($tmp_zip);
	@unlink($tmp_zip);
	exit;
});
