<?php
/**
 * Projekte: CSV-Export + PDF-Druck aus der Listenansicht
 */

namespace CLOUDMEISTER\CMX\Buero;

use Dompdf\Dompdf;
use Dompdf\Options;

defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_normalize_ref')) {
	function cmxpr_projekte_normalize_ref(string $raw_ref): string {
		$fallback = (string) \admin_url('edit.php?post_type=projekte');
		$ref = \trim(\rawurldecode($raw_ref));
		if ($ref === '') {
			return $fallback;
		}

		$ref = (string) \remove_query_arg(['cmx_projekte_print', 'cmx_projekte_print_error'], $ref);
		return (string) \wp_validate_redirect($ref, $fallback);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_current_list_ref')) {
	function cmxpr_projekte_current_list_ref(): string {
		$scheme = \is_ssl() ? 'https://' : 'http://';
		$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
		$uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
		return cmxpr_projekte_normalize_ref($scheme . $host . $uri);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_request_ref')) {
	function cmxpr_projekte_request_ref(): string {
		$raw = (string) ($_REQUEST['ref'] ?? '');
		if ($raw !== '') {
			return cmxpr_projekte_normalize_ref($raw);
		}
		return cmxpr_projekte_current_list_ref();
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_request_source')) {
	function cmxpr_projekte_request_source(): array {
		$ref_query = [];
		$ref = (string) ($_REQUEST['ref'] ?? '');
		if ($ref !== '') {
			$parts = \wp_parse_url(\rawurldecode($ref));
			if (!empty($parts['query'])) {
				\parse_str((string) $parts['query'], $ref_query);
			}
		}

		$source = \array_merge(\is_array($ref_query) ? $ref_query : [], $_REQUEST);
		$source['post_type'] = 'projekte';
		return \is_array($source) ? $source : [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_requested_selected_ids')) {
	function cmxpr_projekte_requested_selected_ids(): array {
		if (!isset($_REQUEST['post'])) {
			return [];
		}

		return \array_values(\array_filter(\array_map('intval', (array) $_REQUEST['post'])));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_print_normalize_date')) {
	function cmxpr_projekte_print_normalize_date(string $raw_date): string {
		$raw_date = \trim($raw_date);
		if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_date)) {
			return '';
		}

		[$y, $m, $d] = \array_map('intval', \explode('-', $raw_date));
		if (!\checkdate($m, $d, $y)) {
			return '';
		}

		return \sprintf('%04d-%02d-%02d', $y, $m, $d);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_print_presets')) {
	function cmxpr_projekte_print_presets(): array {
		return [
			'heute' => 'Heute (heute bis heute)',
			'diesen_monat' => 'Diesen Monat',
			'letzten_monat' => 'Letzten Monat',
			'vorletzten_monat' => 'Vorletzten Monat',
			'dieses_quartal' => 'Dieses Quartal',
			'letztes_quartal' => 'Letztes Quartal',
			'vorletztes_quartal' => 'Vorletztes Quartal',
			'dieses_jahr' => 'Dieses Jahr',
			'letztes_jahr' => 'Letztes Jahr',
			'vorletztes_jahr' => 'Vorletztes Jahr',
			'benutzerdefiniert' => 'Benutzerdefiniert',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_print_requested_preset')) {
	function cmxpr_projekte_print_requested_preset(): string {
		$preset = \sanitize_key((string) ($_REQUEST['cmx_projekte_range_preset'] ?? ''));
		$presets = cmxpr_projekte_print_presets();
		if ($preset !== '' && isset($presets[$preset])) {
			return $preset;
		}
		return 'dieses_jahr';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_print_now_datetime')) {
	function cmxpr_projekte_print_now_datetime(): \DateTimeImmutable {
		if (\function_exists('wp_timezone')) {
			return new \DateTimeImmutable('now', \wp_timezone());
		}
		return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_print_range_from_preset')) {
	function cmxpr_projekte_print_range_from_preset(string $preset): array {
		$now = cmxpr_projekte_print_now_datetime();
		$today = $now->format('Y-m-d');

		switch ($preset) {
			case 'heute':
				return ['from' => $today, 'to' => $today];
			case 'diesen_monat':
				return [
					'from' => $now->modify('first day of this month')->format('Y-m-d'),
					'to' => $now->modify('last day of this month')->format('Y-m-d'),
				];
			case 'letzten_monat':
				return [
					'from' => $now->modify('first day of last month')->format('Y-m-d'),
					'to' => $now->modify('last day of last month')->format('Y-m-d'),
				];
			case 'vorletzten_monat':
				return [
					'from' => $now->modify('first day of -2 months')->format('Y-m-d'),
					'to' => $now->modify('last day of -2 months')->format('Y-m-d'),
				];
			case 'dieses_quartal':
				$year = (int) $now->format('Y');
				$month = (int) $now->format('n');
				$q_start_month = ((int) \floor(($month - 1) / 3) * 3) + 1;
				$q_start = $now->setDate($year, $q_start_month, 1);
				$q_end = $q_start->modify('+2 months')->modify('last day of this month');
				return [
					'from' => $q_start->format('Y-m-d'),
					'to' => $q_end->format('Y-m-d'),
				];
			case 'letztes_quartal':
				$year = (int) $now->format('Y');
				$month = (int) $now->format('n');
				$q_start_month = ((int) \floor(($month - 1) / 3) * 3) + 1;
				$current_q_start = $now->setDate($year, $q_start_month, 1);
				$last_q_start = $current_q_start->modify('-3 months');
				$last_q_end = $current_q_start->modify('-1 day');
				return [
					'from' => $last_q_start->format('Y-m-d'),
					'to' => $last_q_end->format('Y-m-d'),
				];
			case 'vorletztes_quartal':
				$year = (int) $now->format('Y');
				$month = (int) $now->format('n');
				$q_start_month = ((int) \floor(($month - 1) / 3) * 3) + 1;
				$current_q_start = $now->setDate($year, $q_start_month, 1);
				$prev2_q_start = $current_q_start->modify('-6 months');
				$prev2_q_end = $current_q_start->modify('-3 months')->modify('-1 day');
				return [
					'from' => $prev2_q_start->format('Y-m-d'),
					'to' => $prev2_q_end->format('Y-m-d'),
				];
			case 'dieses_jahr':
				$year = (int) $now->format('Y');
				return [
					'from' => \sprintf('%04d-01-01', $year),
					'to' => \sprintf('%04d-12-31', $year),
				];
			case 'letztes_jahr':
				$year = ((int) $now->format('Y')) - 1;
				return [
					'from' => \sprintf('%04d-01-01', $year),
					'to' => \sprintf('%04d-12-31', $year),
				];
			case 'vorletztes_jahr':
				$year = ((int) $now->format('Y')) - 2;
				return [
					'from' => \sprintf('%04d-01-01', $year),
					'to' => \sprintf('%04d-12-31', $year),
				];
			default:
				return ['from' => '', 'to' => ''];
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_print_requested_date_range')) {
	function cmxpr_projekte_print_requested_date_range(): array {
		$from = cmxpr_projekte_print_normalize_date((string) ($_REQUEST['cmx_projekte_date_from'] ?? ''));
		$to = cmxpr_projekte_print_normalize_date((string) ($_REQUEST['cmx_projekte_date_to'] ?? ''));

		if ($from === '' || $to === '') {
			$preset = cmxpr_projekte_print_requested_preset();
			$preset_range = cmxpr_projekte_print_range_from_preset($preset);
			if ($from === '') {
				$from = (string) ($preset_range['from'] ?? '');
			}
			if ($to === '') {
				$to = (string) ($preset_range['to'] ?? '');
			}
		}

		if ($from !== '' && $to !== '' && $from > $to) {
			[$from, $to] = [$to, $from];
		}

		return ['from' => $from, 'to' => $to];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_pdf_section_options')) {
	function cmxpr_projekte_pdf_section_options(): array {
		return [
			'kunde' => 'Kunde',
			'status' => 'Status',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_pdf_default_sections')) {
	function cmxpr_projekte_pdf_default_sections(): array {
		return [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_requested_pdf_sections')) {
	function cmxpr_projekte_requested_pdf_sections(): array {
		$allowed = cmxpr_projekte_pdf_section_options();
		$present = !empty($_REQUEST['cmx_projekte_pdf_sections_present']);
		if (!$present) {
			return \array_values(\array_filter(
				cmxpr_projekte_pdf_default_sections(),
				static fn(string $key): bool => isset($allowed[$key])
			));
		}

		$raw = $_REQUEST['cmx_projekte_pdf_sections'] ?? [];
		if (!\is_array($raw)) {
			return [];
		}

		$selected = [];
		foreach ($raw as $value) {
			$key = \sanitize_key((string) $value);
			if ($key !== '' && isset($allowed[$key])) {
				$selected[$key] = true;
			}
		}

		return \array_keys($selected);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_print_verify_nonce')) {
	function cmxpr_projekte_print_verify_nonce(string $specific_action): bool {
		$nonce = (string) ($_REQUEST['_wpnonce'] ?? '');
		if ($nonce === '') {
			return false;
		}
		if (\wp_verify_nonce($nonce, 'cmx_print_projekte_range')) {
			return true;
		}
		return \wp_verify_nonce($nonce, $specific_action);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_print_require_date_range_or_redirect')) {
	function cmxpr_projekte_print_require_date_range_or_redirect(): array {
		$range = cmxpr_projekte_print_requested_date_range();
		if ($range['from'] !== '' && $range['to'] !== '') {
			return $range;
		}

		$args = [
			'post_type' => 'projekte',
			'cmx_projekte_print' => 1,
			'cmx_projekte_print_error' => 'missing_range',
			'ref' => cmxpr_projekte_request_ref(),
			'cmx_projekte_range_preset' => cmxpr_projekte_print_requested_preset(),
			'cmx_projekte_pdf_sections_present' => 1,
			'cmx_projekte_pdf_sections' => cmxpr_projekte_requested_pdf_sections(),
		];
		if ($range['from'] !== '') {
			$args['cmx_projekte_date_from'] = $range['from'];
		}
		if ($range['to'] !== '') {
			$args['cmx_projekte_date_to'] = $range['to'];
		}

		$target = (string) \add_query_arg($args, \admin_url('edit.php'));
		\wp_safe_redirect($target);
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_to_float')) {
	function cmxpr_projekte_to_float($value): float {
		if (\function_exists(__NAMESPACE__ . '\\cmx_projekt_decimal_to_float')) {
			return (float) cmx_projekt_decimal_to_float($value);
		}

		$text = \trim((string) $value);
		if ($text === '') {
			return 0.0;
		}

		$text = \str_replace(["\xC2\xA0", ' ', "'"], '', $text);
		$text = \str_replace(',', '.', $text);
		return \is_numeric($text) ? (float) $text : 0.0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_format_money')) {
	function cmxpr_projekte_format_money(float $amount): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')) {
			return (string) cmx_format_swiss_number($amount, 2);
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_projekt_format_swiss_number')) {
			return (string) cmx_projekt_format_swiss_number($amount, 2);
		}
		return \number_format($amount, 2, '.', "'");
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_format_date_display')) {
	function cmxpr_projekte_format_date_display(string $raw_date): string {
		$raw_date = cmxpr_projekte_print_normalize_date($raw_date);
		if ($raw_date === '') {
			return '';
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_format_ch_date')) {
			$formatted = (string) cmx_format_ch_date($raw_date);
			if ($formatted !== '') {
				return $formatted;
			}
		}
		$ts = \strtotime($raw_date . ' 00:00:00');
		return $ts ? (string) \date_i18n('d.m.Y', $ts) : $raw_date;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_term_list')) {
	function cmxpr_projekte_term_list(int $post_id, string $taxonomy): string {
		if ($taxonomy === '' || !\taxonomy_exists($taxonomy) || !\is_object_in_taxonomy('projekte', $taxonomy)) {
			return '';
		}

		$terms = \get_the_terms($post_id, $taxonomy);
		if (empty($terms) || \is_wp_error($terms)) {
			return '';
		}

		$names = [];
		foreach ($terms as $term) {
			if ($term instanceof \WP_Term) {
				$names[] = (string) $term->name;
			}
		}

		return \implode(', ', \array_filter($names));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_contact_label')) {
	function cmxpr_projekte_contact_label(int $post_id): string {
		$kontakt_meta_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKT_META') ? (string) CMX_KONTAKT_META : '_cmx_projekt_kontakt_id';
		$kontakt_id = (int) \get_post_meta($post_id, $kontakt_meta_key, true);
		if ($kontakt_id <= 0 || !\get_post_status($kontakt_id)) {
			return '';
		}

		$title = \trim((string) \get_the_title($kontakt_id));
		return $title !== '' ? $title : ('#' . $kontakt_id);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_url_value')) {
	function cmxpr_projekte_url_value(int $post_id): string {
		$url_meta_key = \defined(__NAMESPACE__ . '\\CMX_PROJ_URL_META') ? (string) CMX_PROJ_URL_META : '_cmx_projekt_url';
		return \trim((string) \get_post_meta($post_id, $url_meta_key, true));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_umsatz_total')) {
	function cmxpr_projekte_umsatz_total(int $post_id): float {
		if (\function_exists(__NAMESPACE__ . '\\cmx_proj_calc_umsatz_total')) {
			return (float) cmx_proj_calc_umsatz_total($post_id);
		}

		$meta_key = \defined(__NAMESPACE__ . '\\CMX_PROJ_UMSATZ_META') ? (string) CMX_PROJ_UMSATZ_META : '_cmx_projekt_umsatz_total';
		return cmxpr_projekte_to_float(\get_post_meta($post_id, $meta_key, true));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_deckungsbeitrag_item')) {
	function cmxpr_projekte_deckungsbeitrag_item(int $post_id): array {
		static $map = null;
		if ($map === null) {
			$map = \function_exists(__NAMESPACE__ . '\\cmx_admin_deckungsbeitrag_map')
				? (array) cmx_admin_deckungsbeitrag_map('projekte')
				: [];
		}

		$item = (array) ($map[$post_id] ?? []);
		$profit = (float) ($item['profit'] ?? 0.0);
		$margin = (float) ($item['margin'] ?? 0.0);

		return [
			'profit' => $profit,
			'margin' => $margin,
			'profit_display' => cmxpr_projekte_format_money($profit),
			'margin_display' => cmxpr_projekte_format_money($margin),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_group_items')) {
	function cmxpr_projekte_group_items(array $items, string $field, string $empty_label): array {
		$groups = [];

		foreach ($items as $item) {
			if (!\is_array($item)) {
				continue;
			}

			$label = \trim((string) ($item[$field] ?? ''));
			if ($label === '') {
				$label = $empty_label;
			}

			if (!isset($groups[$label])) {
				$groups[$label] = [];
			}
			$groups[$label][] = $item;
		}

		if (\count($groups) > 1) {
			\uksort($groups, static fn(string $left, string $right): int => \strnatcasecmp($left, $right));
		}

		return $groups;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_group_totals')) {
	function cmxpr_projekte_group_totals(array $items): array {
		$umsatz = 0.0;
		$deckungsbeitrag = 0.0;

		foreach ($items as $item) {
			if (!\is_array($item)) {
				continue;
			}
			$umsatz += (float) ($item['umsatz_value'] ?? 0.0);
			$deckungsbeitrag += (float) ($item['deckungsbeitrag_value'] ?? 0.0);
		}

		return [
			'umsatz' => $umsatz,
			'deckungsbeitrag' => $deckungsbeitrag,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_task_rows')) {
	function cmxpr_projekte_task_rows(int $post_id, array $range): array {
		$meta_key = \defined(__NAMESPACE__ . '\\CMX_PROJEKT_TASK_META') ? (string) CMX_PROJEKT_TASK_META : '_cmx_projekt_tasks';
		$vk_meta_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VK') ? (string) CMX_ARTIKEL_META_VK : '_cmx_artikel_vk';
		$raw = \get_post_meta($post_id, $meta_key, true);

		if (\is_string($raw)) {
			$decoded = \json_decode($raw, true);
			if (\json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
				$raw = $decoded;
			} else {
				$maybe = @\maybe_unserialize($raw);
				$raw = \is_array($maybe) ? $maybe : [];
			}
		}

		if (!\is_array($raw) || empty($raw)) {
			return [];
		}

		$from = (string) ($range['from'] ?? '');
		$to = (string) ($range['to'] ?? '');
		$rows = [];
		static $artikel_title_cache = [];
		static $artikel_vk_cache = [];

		foreach ($raw as $row) {
			if (!\is_array($row)) {
				continue;
			}

			$date = cmxpr_projekte_print_normalize_date((string) ($row['datum'] ?? ''));
			if ($from !== '' && $to !== '') {
				if ($date === '' || $date < $from || $date > $to) {
					continue;
				}
			}

			$artikel_id = (int) ($row['artikel_id'] ?? 0);
			if (!isset($artikel_title_cache[$artikel_id])) {
				$artikel_title_cache[$artikel_id] = $artikel_id > 0 ? (string) \get_the_title($artikel_id) : '';
			}
			if (!isset($artikel_vk_cache[$artikel_id])) {
				$artikel_vk_cache[$artikel_id] = $artikel_id > 0
					? cmxpr_projekte_to_float(\get_post_meta($artikel_id, $vk_meta_key, true))
					: 0.0;
			}

			$dauer = cmxpr_projekte_to_float($row['dauer'] ?? 0);
			$betrag = $dauer > 0 ? $dauer * (float) ($artikel_vk_cache[$artikel_id] ?? 0.0) : 0.0;
			$verrechenbar = \function_exists(__NAMESPACE__ . '\\cmx_projekt_truthy')
				? (bool) cmx_projekt_truthy($row['verrechenbar'] ?? false)
				: !empty($row['verrechenbar']);

			$rows[] = [
				'date' => $date,
				'date_display' => cmxpr_projekte_format_date_display($date),
				'time' => \trim((string) ($row['zeit'] ?? '')),
				'duration_value' => $dauer,
				'duration_display' => $dauer > 0 ? cmxpr_projekte_format_money($dauer) : '',
				'article' => (string) ($artikel_title_cache[$artikel_id] ?: ($artikel_id > 0 ? '#' . $artikel_id : '')),
				'chargeable' => $verrechenbar ? 'Ja' : 'Nein',
				'amount_value' => $betrag,
				'amount_display' => $betrag > 0 ? cmxpr_projekte_format_money($betrag) : '',
				'info' => \trim((string) ($row['info'] ?? '')),
			];
		}

		if (\count($rows) > 1) {
			\usort($rows, static function (array $left, array $right): int {
				$left_date = (string) ($left['date'] ?? '');
				$right_date = (string) ($right['date'] ?? '');
				if ($left_date !== $right_date) {
					return $left_date <=> $right_date;
				}

				$left_time = (string) ($left['time'] ?? '');
				$right_time = (string) ($right['time'] ?? '');
				return $left_time <=> $right_time;
			});
		}

		return $rows;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_projekte_matches_range')) {
	function cmxpr_projekte_matches_range(int $post_id, array $range, array $task_rows = []): bool {
		$from = (string) ($range['from'] ?? '');
		$to = (string) ($range['to'] ?? '');
		if ($from === '' || $to === '') {
			return true;
		}

		if (!empty($task_rows)) {
			return true;
		}

		$begin_meta_key = \defined(__NAMESPACE__ . '\\CMX_PROJ_BEG_META') ? (string) CMX_PROJ_BEG_META : '_cmx_projekt_beginn';
		$end_meta_key = \defined(__NAMESPACE__ . '\\CMX_PROJ_END_META') ? (string) CMX_PROJ_END_META : '_cmx_projekt_ende';
		$begin = cmxpr_projekte_print_normalize_date((string) \get_post_meta($post_id, $begin_meta_key, true));
		$end = cmxpr_projekte_print_normalize_date((string) \get_post_meta($post_id, $end_meta_key, true));

		if ($begin === '' && $end === '') {
			return false;
		}

		$start = $begin !== '' ? $begin : '1000-01-01';
		$finish = $end !== '' ? $end : '9999-12-31';
		return $finish >= $from && $start <= $to;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_collect_projekte_ids')) {
	function cmxpr_collect_projekte_ids(): array {
		$selected_ids = cmxpr_projekte_requested_selected_ids();
		$source = cmxpr_projekte_request_source();

		$query_vars = [
			'post_type' => 'projekte',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'orderby' => 'ID',
			'order' => 'ASC',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];

		if (!empty($selected_ids)) {
			$query_vars['post__in'] = $selected_ids;
			$query_vars['orderby'] = 'post__in';
			$query_vars['post_status'] = 'any';
		} else {
			$post_status = isset($source['post_status']) ? \sanitize_key((string) $source['post_status']) : '';
			if ($post_status !== '' && $post_status !== 'all') {
				$query_vars['post_status'] = $post_status;
			} else {
				$query_vars['post_status'] = ['publish', 'future', 'draft', 'pending', 'private'];
			}

			$current_view = isset($source['cmx_view']) ? \sanitize_key((string) $source['cmx_view']) : '';
			if (
				$current_view === 'deckungsbeitrag'
				&& \function_exists(__NAMESPACE__ . '\\cmx_admin_deckungsbeitrag_map')
			) {
				$deckungsbeitrag_ids = \array_values(\array_filter(
					\array_map('intval', \array_keys((array) cmx_admin_deckungsbeitrag_map('projekte')))
				));
				if (empty($deckungsbeitrag_ids)) {
					return [];
				}
				$query_vars['post__in'] = $deckungsbeitrag_ids;
				$query_vars['orderby'] = 'post__in';
			}

			foreach (['s', 'author', 'm'] as $field) {
				$value = $source[$field] ?? '';
				if ($value !== '' && $value !== '0' && $value !== '-1') {
					$query_vars[$field] = $value;
				}
			}

			$kunde_filter = isset($source['cmx_kunde_filter']) ? \absint(\wp_unslash($source['cmx_kunde_filter'])) : 0;
			if ($kunde_filter > 0) {
				$kontakt_meta_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKT_META') ? (string) CMX_KONTAKT_META : '_cmx_projekt_kontakt_id';
				$query_vars['meta_query'][] = [
					'key' => $kontakt_meta_key,
					'value' => $kunde_filter,
					'compare' => '=',
					'type' => 'NUMERIC',
				];
			}

			$tax_query = [];
			$taxonomies = \get_object_taxonomies('projekte', 'objects');
			foreach ($taxonomies as $taxonomy) {
				$keys = [];
				if (!empty($taxonomy->query_var) && \is_string($taxonomy->query_var)) {
					$keys[] = $taxonomy->query_var;
				}
				$keys[] = $taxonomy->name;
				$keys = \array_values(\array_unique(\array_filter($keys)));

				foreach ($keys as $key) {
					$value = $source[$key] ?? '';
					if ($value === '' || $value === '0' || $value === '-1') {
						continue;
					}
					$tax_query[] = [
						'taxonomy' => $taxonomy->name,
						'field' => \is_numeric($value) ? 'term_id' : 'slug',
						'terms' => [$value],
					];
					break;
				}
			}

			$selected_status = isset($source['cmx_status_filter']) ? \sanitize_text_field(\wp_unslash($source['cmx_status_filter'])) : '';
			if ($selected_status !== '' && $selected_status !== '0') {
				$status_tax = '';
				if (\function_exists(__NAMESPACE__ . '\\cmx_projekte_detect_status_taxonomy')) {
					$status_tax = (string) cmx_projekte_detect_status_taxonomy();
				}
				if ($status_tax === '') {
					foreach (['projekte_status', 'projekt_status', 'status'] as $candidate) {
						if (\taxonomy_exists($candidate) && \is_object_in_taxonomy('projekte', $candidate)) {
							$status_tax = $candidate;
							break;
						}
					}
				}
				if ($status_tax !== '' && \taxonomy_exists($status_tax) && \is_object_in_taxonomy('projekte', $status_tax)) {
					$tax_query[] = [
						'taxonomy' => $status_tax,
						'field' => 'slug',
						'terms' => [$selected_status],
					];
				}
			}

			if (!empty($tax_query)) {
				$query_vars['tax_query'] = \array_merge(['relation' => 'AND'], $tax_query);
			}
		}

		$query = new \WP_Query($query_vars);
		return \array_values(\array_map('intval', (array) $query->posts));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_build_pdf_project_items')) {
	function cmxpr_build_pdf_project_items(array $post_ids, array $range, array $sections, bool $selected_override): array {
		$category_tax = \function_exists(__NAMESPACE__ . '\\cmx_projekte_detect_taxonomy')
			? (string) cmx_projekte_detect_taxonomy()
			: '';
		$status_tax = \function_exists(__NAMESPACE__ . '\\cmx_projekte_detect_status_taxonomy')
			? (string) cmx_projekte_detect_status_taxonomy()
			: '';

		$items = [];
		foreach ($post_ids as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0 || !\get_post($post_id)) {
				continue;
			}

			if (!$selected_override && !cmxpr_projekte_matches_range($post_id, $range)) {
				continue;
			}

			$begin_meta_key = \defined(__NAMESPACE__ . '\\CMX_PROJ_BEG_META') ? (string) CMX_PROJ_BEG_META : '_cmx_projekt_beginn';
			$end_meta_key = \defined(__NAMESPACE__ . '\\CMX_PROJ_END_META') ? (string) CMX_PROJ_END_META : '_cmx_projekt_ende';
			$begin = cmxpr_projekte_print_normalize_date((string) \get_post_meta($post_id, $begin_meta_key, true));
			$end = cmxpr_projekte_print_normalize_date((string) \get_post_meta($post_id, $end_meta_key, true));
			$title = \trim((string) \get_the_title($post_id));
			if ($title === '') {
				$title = '#' . $post_id;
			}

			$umsatz = cmxpr_projekte_umsatz_total($post_id);
			$deckungsbeitrag = cmxpr_projekte_deckungsbeitrag_item($post_id);

			$items[] = [
				'id' => $post_id,
				'title' => $title,
				'kunde' => cmxpr_projekte_contact_label($post_id),
				'kategorie' => cmxpr_projekte_term_list($post_id, $category_tax),
				'status' => cmxpr_projekte_term_list($post_id, $status_tax),
				'begin' => cmxpr_projekte_format_date_display($begin),
				'end' => cmxpr_projekte_format_date_display($end),
				'umsatz_value' => $umsatz,
				'umsatz_display' => cmxpr_projekte_format_money($umsatz),
				'deckungsbeitrag_value' => (float) ($deckungsbeitrag['profit'] ?? 0.0),
				'deckungsbeitrag_display' => (string) ($deckungsbeitrag['profit_display'] ?? ''),
				'deckungsbeitrag_margin' => (float) ($deckungsbeitrag['margin'] ?? 0.0),
				'deckungsbeitrag_margin_display' => (string) ($deckungsbeitrag['margin_display'] ?? ''),
			];
		}

		return $items;
	}
}

/* =========================================================
 * 1) "ausdrucken"-Link hinter Deckungsbeitrag
 * ========================================================= */
\add_filter('views_edit-projekte', function (array $views): array {
	if (!\current_user_can('edit_posts')) {
		return $views;
	}

	$url = (string) \add_query_arg([
		'post_type' => 'projekte',
		'cmx_projekte_print' => 1,
		'ref' => cmxpr_projekte_current_list_ref(),
	], \admin_url('edit.php'));

	$is_current = !empty($_GET['cmx_projekte_print']);
	$link = '<a href="' . \esc_url($url) . '"' . ($is_current ? ' class="current" aria-current="page"' : '') . '>ausdrucken</a>';

	$new_views = [];
	$inserted = false;

	foreach ($views as $key => $html) {
		$new_views[$key] = $html;
		if ($key === 'cmx_deckungsbeitrag' && !$inserted) {
			$new_views['cmx_print_projekte_list'] = $link;
			$inserted = true;
		}
	}

	if (!$inserted) {
		$new_views['cmx_print_projekte_list'] = $link;
	}

	return $new_views;
}, 40);

/* =========================================================
 * 2) PDF-Druck-Handler
 * ========================================================= */
\add_action('admin_post_cmx_print_projekte_list_pdf', function (): void {
	if (!\current_user_can('edit_posts')) {
		\wp_die('Keine Berechtigung.');
	}
	if (!cmxpr_projekte_print_verify_nonce('cmx_print_projekte_list_pdf')) {
		\wp_die('Ungültige Anfrage.');
	}

	$range = cmxpr_projekte_print_require_date_range_or_redirect();
	$sections = cmxpr_projekte_requested_pdf_sections();
	$selected_override = !empty(cmxpr_projekte_requested_selected_ids());
	$post_ids = cmxpr_collect_projekte_ids();
	$items = cmxpr_build_pdf_project_items($post_ids, $range, $sections, $selected_override);
	$include_customer_lists = \in_array('kunde', $sections, true);
	$include_status_lists = \in_array('status', $sections, true);
	$preset_key = cmxpr_projekte_print_requested_preset();
	$presets = cmxpr_projekte_print_presets();
	$preset_label = (string) ($presets[$preset_key] ?? 'Benutzerdefiniert');
	$range_from = cmxpr_projekte_format_date_display((string) ($range['from'] ?? ''));
	$range_to = cmxpr_projekte_format_date_display((string) ($range['to'] ?? ''));
	$overview_total_umsatz = 0.0;
	$overview_total_db = 0.0;

	foreach ($items as $item) {
		$overview_total_umsatz += (float) ($item['umsatz_value'] ?? 0.0);
		$overview_total_db += (float) ($item['deckungsbeitrag_value'] ?? 0.0);
	}

	$customer_groups = $include_customer_lists
		? cmxpr_projekte_group_items($items, 'kunde', 'Ohne Kunde')
		: [];
	$status_groups = $include_status_lists
		? cmxpr_projekte_group_items($items, 'status', 'Ohne Status')
		: [];

	$branding_logo = \function_exists(__NAMESPACE__ . '\\cmx_get_branding_logo') ? (string) cmx_get_branding_logo() : '';
	if (\function_exists(__NAMESPACE__ . '\\cmxbu_prepare_png_for_dompdf')) {
		$branding_logo = (string) cmxbu_prepare_png_for_dompdf($branding_logo);
	}

	$generated_at = \function_exists('wp_date')
		? (string) \wp_date('d.m.Y H:i')
		: (string) \date_i18n('d.m.Y H:i');

	$overview_colspan = 8;
	$overview_total_label_colspan = 6;

	$options = new Options();
	$options->set('isRemoteEnabled', true);
	$dompdf = new Dompdf($options);

	\ob_start();
	?>
	<!doctype html>
	<html lang="de">
		<head>
		<meta charset="utf-8">
		<style>
			body{font-family:DejaVu Sans, sans-serif;font-size:11px;color:#111;line-height:1.35}
			.header-table,.report-table{width:100%;border-collapse:collapse}
			.header-table td{vertical-align:top}
			.header-title{font-size:24px;font-weight:700}
			.header-meta{margin-top:4px;font-size:11px;color:#555}
			.header-logo{text-align:right}
			.header-logo img{max-height:54px;max-width:180px}
			.section-title{margin:18px 0 8px;font-size:16px;font-weight:700}
			.report-table th,.report-table td{border:none;padding:6px 7px;vertical-align:top}
			.report-table th{background:none;font-size:10px;text-transform:uppercase;letter-spacing:.04em;padding-bottom:10px;border-bottom:1px solid #d6d9de;text-align:left}
			.report-table th.num{text-align:right}
			.report-table tfoot td{background:none;font-weight:700;padding-top:10px}
			.report-table tfoot td.total-amount{border-top:1px solid #d6d9de;padding-top:8px}
			.num{text-align:right;white-space:nowrap}
			.muted{color:#6b7280}
			.page-break{page-break-before:always}
			.project-title{font-weight:700}
			.db-meta{display:block;margin-top:2px;font-size:9px;color:#6b7280}
		</style>
	</head>
	<body>
		<table class="header-table">
			<tr>
				<td>
					<div class="header-title">Projekte</div>
					<div class="header-meta">
						Zeitraum: <strong><?php echo \esc_html($preset_label); ?></strong>
						| Von: <strong><?php echo \esc_html($range_from); ?></strong>
						| Bis: <strong><?php echo \esc_html($range_to); ?></strong>
						| Erstellt: <strong><?php echo \esc_html($generated_at); ?></strong>
					</div>
				</td>
				<td class="header-logo">
					<?php if ($branding_logo !== ''): ?>
						<img src="<?php echo \esc_url($branding_logo); ?>" alt="Logo">
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<div class="section-title">Projektübersicht</div>
			<table class="report-table">
			<thead>
				<tr>
					<th>Projekt</th>
					<th>Kunde</th>
					<th>Kategorie</th>
					<th>Status</th>
					<th>Beginn</th>
					<th>Ende</th>
					<th class="num">Umsatz</th>
					<th class="num">Deckungsbeitrag</th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($items)): ?>
					<tr>
						<td colspan="<?php echo (int) $overview_colspan; ?>">Keine Projekte im gewählten Zeitraum gefunden.</td>
					</tr>
				<?php else: ?>
					<?php foreach ($items as $item): ?>
						<tr>
							<td>
								<span class="project-title"><?php echo \esc_html((string) ($item['title'] ?? '')); ?></span>
							</td>
							<td><?php echo \esc_html((string) ($item['kunde'] ?? '')); ?></td>
							<td><?php echo \esc_html((string) ($item['kategorie'] ?? '')); ?></td>
							<td><?php echo \esc_html((string) ($item['status'] ?? '')); ?></td>
							<td><?php echo \esc_html((string) ($item['begin'] ?? '')); ?></td>
							<td><?php echo \esc_html((string) ($item['end'] ?? '')); ?></td>
							<td class="num"><?php echo \esc_html((string) ($item['umsatz_display'] ?? '')); ?></td>
							<td class="num">
								<?php echo \esc_html((string) ($item['deckungsbeitrag_display'] ?? '')); ?>
								<?php if ((string) ($item['deckungsbeitrag_display'] ?? '') !== ''): ?>
									<span class="db-meta"><?php echo \esc_html(cmxpr_projekte_format_money((float) ($item['deckungsbeitrag_margin'] ?? 0.0))); ?>%</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="<?php echo (int) $overview_total_label_colspan; ?>"></td>
					<td class="num total-amount"><?php echo \esc_html(cmxpr_projekte_format_money($overview_total_umsatz)); ?></td>
					<td class="num total-amount"><?php echo \esc_html(cmxpr_projekte_format_money($overview_total_db)); ?></td>
				</tr>
			</tfoot>
		</table>

		<?php if ($include_customer_lists): ?>
			<?php foreach ($customer_groups as $group_label => $group_items): ?>
				<?php $group_totals = cmxpr_projekte_group_totals($group_items); ?>
				<div class="section-title page-break"><?php echo \esc_html((string) $group_label); ?></div>
				<table class="report-table">
					<thead>
						<tr>
							<th>Projekt</th>
							<th>Kunde</th>
							<th>Kategorie</th>
							<th>Status</th>
							<th>Beginn</th>
							<th>Ende</th>
							<th class="num">Umsatz</th>
							<th class="num">Deckungsbeitrag</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($group_items as $item): ?>
							<tr>
								<td><span class="project-title"><?php echo \esc_html((string) ($item['title'] ?? '')); ?></span></td>
								<td><?php echo \esc_html((string) ($item['kunde'] ?? '')); ?></td>
								<td><?php echo \esc_html((string) ($item['kategorie'] ?? '')); ?></td>
								<td><?php echo \esc_html((string) ($item['status'] ?? '')); ?></td>
								<td><?php echo \esc_html((string) ($item['begin'] ?? '')); ?></td>
								<td><?php echo \esc_html((string) ($item['end'] ?? '')); ?></td>
								<td class="num"><?php echo \esc_html((string) ($item['umsatz_display'] ?? '')); ?></td>
								<td class="num">
									<?php echo \esc_html((string) ($item['deckungsbeitrag_display'] ?? '')); ?>
									<?php if ((string) ($item['deckungsbeitrag_display'] ?? '') !== ''): ?>
										<span class="db-meta"><?php echo \esc_html(cmxpr_projekte_format_money((float) ($item['deckungsbeitrag_margin'] ?? 0.0))); ?>%</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<td colspan="6"></td>
							<td class="num total-amount"><?php echo \esc_html(cmxpr_projekte_format_money((float) ($group_totals['umsatz'] ?? 0.0))); ?></td>
							<td class="num total-amount"><?php echo \esc_html(cmxpr_projekte_format_money((float) ($group_totals['deckungsbeitrag'] ?? 0.0))); ?></td>
						</tr>
					</tfoot>
				</table>
			<?php endforeach; ?>
		<?php endif; ?>

		<?php if ($include_status_lists): ?>
			<?php foreach ($status_groups as $group_label => $group_items): ?>
				<?php $group_totals = cmxpr_projekte_group_totals($group_items); ?>
				<div class="section-title page-break"><?php echo \esc_html((string) $group_label); ?></div>
				<table class="report-table">
					<thead>
						<tr>
							<th>Projekt</th>
							<th>Kunde</th>
							<th>Kategorie</th>
							<th>Status</th>
							<th>Beginn</th>
							<th>Ende</th>
							<th class="num">Umsatz</th>
							<th class="num">Deckungsbeitrag</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($group_items as $item): ?>
							<tr>
								<td><span class="project-title"><?php echo \esc_html((string) ($item['title'] ?? '')); ?></span></td>
								<td><?php echo \esc_html((string) ($item['kunde'] ?? '')); ?></td>
								<td><?php echo \esc_html((string) ($item['kategorie'] ?? '')); ?></td>
								<td><?php echo \esc_html((string) ($item['status'] ?? '')); ?></td>
								<td><?php echo \esc_html((string) ($item['begin'] ?? '')); ?></td>
								<td><?php echo \esc_html((string) ($item['end'] ?? '')); ?></td>
								<td class="num"><?php echo \esc_html((string) ($item['umsatz_display'] ?? '')); ?></td>
								<td class="num">
									<?php echo \esc_html((string) ($item['deckungsbeitrag_display'] ?? '')); ?>
									<?php if ((string) ($item['deckungsbeitrag_display'] ?? '') !== ''): ?>
										<span class="db-meta"><?php echo \esc_html(cmxpr_projekte_format_money((float) ($item['deckungsbeitrag_margin'] ?? 0.0))); ?>%</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<td colspan="6"></td>
							<td class="num total-amount"><?php echo \esc_html(cmxpr_projekte_format_money((float) ($group_totals['umsatz'] ?? 0.0))); ?></td>
							<td class="num total-amount"><?php echo \esc_html(cmxpr_projekte_format_money((float) ($group_totals['deckungsbeitrag'] ?? 0.0))); ?></td>
						</tr>
					</tfoot>
				</table>
			<?php endforeach; ?>
		<?php endif; ?>
	</body>
	</html>
	<?php
	$html = (string) \ob_get_clean();

	$dompdf->loadHtml($html, 'UTF-8');
	$dompdf->setPaper('A4', 'landscape');
	$dompdf->render();

	$filename = \function_exists(__NAMESPACE__ . '\\cmx_export_filename')
		? (string) cmx_export_filename('projekte-ausdrucken', 'pdf')
		: ('projekte-ausdrucken-' . (
			\function_exists(__NAMESPACE__ . '\\cmx_export_now_stamp')
				? (string) cmx_export_now_stamp()
				: (\function_exists('\\wp_date')
					? (string) \wp_date('Ymd-His')
					: (\function_exists('\\date_i18n')
						? (string) \date_i18n('Ymd-His')
						: (string) \date('Ymd-His')))
		) . '.pdf');

	\header('Content-Type: application/pdf');
	\header('Content-Disposition: attachment; filename="' . $filename . '"');
	echo $dompdf->output();
	exit;
});

/* =========================================================
 * 5) Druck-Panel in der Projekte-Listenansicht
 * ========================================================= */
\add_action('all_admin_notices', function (): void {
	global $typenow;
	if ($typenow !== 'projekte' || empty($_GET['cmx_projekte_print'])) {
		return;
	}
	if (!\current_user_can('edit_posts')) {
		return;
	}

	$range = cmxpr_projekte_print_requested_date_range();
	$preset = cmxpr_projekte_print_requested_preset();
	$presets = cmxpr_projekte_print_presets();
	$ref = cmxpr_projekte_request_ref();
	$cancel_url = cmxpr_projekte_normalize_ref($ref);
	$has_error = !empty($_GET['cmx_projekte_print_error']);
	$section_options = cmxpr_projekte_pdf_section_options();
	$section_selected = \array_fill_keys(cmxpr_projekte_requested_pdf_sections(), true);
	?>
	<div class="notice notice-info" style="padding:20px;margin-top:15px;">
		<h2>Projektstatistik ausdrucken</h2>
		<p>
			Das PDF enthält immer eine Projektübersicht.
			Über die Zusatzoptionen kannst Du zusätzliche Listen nach Kunde oder Status erzeugen.
		</p>
		<?php if ($has_error): ?>
			<p style="color:#b32d2e;"><strong>Bitte Datum von und Datum bis ausfüllen.</strong></p>
		<?php endif; ?>

		<form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>" id="cmx-projekte-print-form">
			<?php \wp_nonce_field('cmx_print_projekte_range'); ?>
			<input type="hidden" name="ref" value="<?php echo \esc_attr($ref); ?>">
			<input type="hidden" name="cmx_projekte_pdf_sections_present" value="1">

			<div style="margin-top:1em;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
				<div style="display:flex;align-items:center;gap:8px;">
					<label for="cmx_projekte_range_preset" style="font-weight:600;">Zeitraum</label>
					<select id="cmx_projekte_range_preset" name="cmx_projekte_range_preset">
						<?php foreach ($presets as $value => $label): ?>
							<option value="<?php echo \esc_attr($value); ?>" <?php selected($preset, $value); ?>><?php echo \esc_html($label); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div style="display:flex;align-items:center;gap:8px;">
					<label for="cmx_projekte_date_from" style="font-weight:600;">Datum von</label>
					<input type="date" id="cmx_projekte_date_from" name="cmx_projekte_date_from" value="<?php echo \esc_attr((string) $range['from']); ?>" required>
				</div>
				<div style="display:flex;align-items:center;gap:8px;">
					<label for="cmx_projekte_date_to" style="font-weight:600;">Datum bis</label>
					<input type="date" id="cmx_projekte_date_to" name="cmx_projekte_date_to" value="<?php echo \esc_attr((string) $range['to']); ?>" required>
				</div>
			</div>

			<div style="margin-top:12px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
				<span style="color:#555;">Zusätzliche Listen</span>
				<?php foreach ($section_options as $section_key => $section_label): ?>
					<label style="display:inline-flex;align-items:center;gap:6px;">
						<input
							type="checkbox"
							name="cmx_projekte_pdf_sections[]"
							value="<?php echo \esc_attr($section_key); ?>"
							<?php checked(isset($section_selected[$section_key])); ?>
						>
						<span><?php echo \esc_html($section_label); ?></span>
					</label>
				<?php endforeach; ?>
			</div>

			<div class="submit" style="margin-top:16px;">
				<!-- <p style="margin:0 0 8px;">Ausgabe</p> -->
				<button type="submit" name="action" value="cmx_print_projekte_list_pdf" class="button button-primary">PDF</button>
				<a href="<?php echo \esc_url($cancel_url); ?>" class="button">Abbrechen</a>
			</div>
		</form>
	</div>
	<?php
});

/* =========================================================
 * 6) JS: Markierte Projekte für PDF mitsenden + Datumslogik
 * ========================================================= */
\add_action('admin_footer-edit.php', function (): void {
	if (($_GET['post_type'] ?? '') !== 'projekte') {
		return;
	}

	?>
	<script>
	document.addEventListener('DOMContentLoaded', function(){
		function selectedPostIds(){
			return Array.prototype.slice.call(document.querySelectorAll('tbody input[name="post[]"]:checked'))
				.map(function(el){ return parseInt(el.value, 10); })
				.filter(function(v){ return !isNaN(v) && v > 0; });
		}

		function syncSelectedPostsIntoForm(form){
			if (!form) return;
			Array.prototype.slice.call(form.querySelectorAll('input[data-cmx-selected="1"]')).forEach(function(el){
				el.remove();
			});
			selectedPostIds().forEach(function(id){
				var input = document.createElement('input');
				input.type = 'hidden';
				input.name = 'post[]';
				input.value = String(id);
				input.setAttribute('data-cmx-selected', '1');
				form.appendChild(input);
			});
		}

		var printForm = document.getElementById('cmx-projekte-print-form');
		if (printForm) {
			printForm.addEventListener('submit', function(){
				syncSelectedPostsIntoForm(printForm);
			});
		}

		var preset = document.getElementById('cmx_projekte_range_preset');
		var fromField = document.getElementById('cmx_projekte_date_from');
		var toField = document.getElementById('cmx_projekte_date_to');

		function pad2(n){ return (n < 10 ? '0' : '') + n; }
		function ymd(date){
			return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
		}
		function applyPreset(value){
			if (!fromField || !toField) return;
			var now = new Date();
			var from = '';
			var to = '';
			var y = now.getFullYear();
			var m = now.getMonth();

			switch (value) {
				case 'heute':
					from = ymd(now);
					to = ymd(now);
					break;
				case 'diesen_monat':
					from = ymd(new Date(y, m, 1));
					to = ymd(new Date(y, m + 1, 0));
					break;
				case 'letzten_monat':
					from = ymd(new Date(y, m - 1, 1));
					to = ymd(new Date(y, m, 0));
					break;
				case 'vorletzten_monat':
					from = ymd(new Date(y, m - 2, 1));
					to = ymd(new Date(y, m - 1, 0));
					break;
				case 'dieses_quartal':
					var qStartMonth = Math.floor(m / 3) * 3;
					from = ymd(new Date(y, qStartMonth, 1));
					to = ymd(new Date(y, qStartMonth + 3, 0));
					break;
				case 'letztes_quartal':
					var thisQStartMonth = Math.floor(m / 3) * 3;
					var thisQStart = new Date(y, thisQStartMonth, 1);
					var lastQStart = new Date(thisQStart.getFullYear(), thisQStart.getMonth() - 3, 1);
					var lastQEnd = new Date(thisQStart.getFullYear(), thisQStart.getMonth(), 0);
					from = ymd(lastQStart);
					to = ymd(lastQEnd);
					break;
				case 'vorletztes_quartal':
					var thisQStartMonth2 = Math.floor(m / 3) * 3;
					var thisQStart2 = new Date(y, thisQStartMonth2, 1);
					var prev2QStart = new Date(thisQStart2.getFullYear(), thisQStart2.getMonth() - 6, 1);
					var prev2QEnd = new Date(thisQStart2.getFullYear(), thisQStart2.getMonth() - 3, 0);
					from = ymd(prev2QStart);
					to = ymd(prev2QEnd);
					break;
				case 'dieses_jahr':
					from = y + '-01-01';
					to = y + '-12-31';
					break;
				case 'letztes_jahr':
					from = (y - 1) + '-01-01';
					to = (y - 1) + '-12-31';
					break;
				case 'vorletztes_jahr':
					from = (y - 2) + '-01-01';
					to = (y - 2) + '-12-31';
					break;
				default:
					return;
			}

			if (from) fromField.value = from;
			if (to) toField.value = to;
		}

		if (preset) {
			preset.addEventListener('change', function(){
				if (preset.value === 'benutzerdefiniert') return;
				applyPreset(preset.value);
			});
		}

		function markCustomIfManual(){
			if (!preset) return;
			if (preset.value !== 'benutzerdefiniert') {
				preset.value = 'benutzerdefiniert';
			}
		}

		if (fromField) fromField.addEventListener('change', markCustomIfManual);
		if (toField) toField.addEventListener('change', markCustomIfManual);

		if (preset && preset.value !== 'benutzerdefiniert' && ((!fromField || !fromField.value) || (!toField || !toField.value))) {
			applyPreset(preset.value);
		}
	});
	</script>
	<?php
});
