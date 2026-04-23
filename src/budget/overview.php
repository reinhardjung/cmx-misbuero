<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_is_screen')) {
	function cmx_budget_overview_is_screen(): bool {
		if (!\is_admin()) {
			return false;
		}

		$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
		if (!$screen) {
			return false;
		}

		return (string) ($screen->id ?? '') === 'edit-budget';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_is_active')) {
	function cmx_budget_overview_is_active(): bool {
		if (!cmx_budget_overview_is_screen()) {
			return false;
		}

		return isset($_GET['cmx_budget_view']) && \sanitize_key((string) \wp_unslash($_GET['cmx_budget_view'])) === 'uebersicht';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_timezone')) {
	function cmx_budget_overview_timezone(): \DateTimeZone {
		if (\function_exists('wp_timezone')) {
			return \wp_timezone();
		}

		$timezone = \function_exists('wp_timezone_string') ? (string) \wp_timezone_string() : '';
		if ($timezone === '') {
			$timezone = 'Europe/Zurich';
		}

		return new \DateTimeZone($timezone);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_now')) {
	function cmx_budget_overview_now(): \DateTimeImmutable {
		return new \DateTimeImmutable('now', cmx_budget_overview_timezone());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_money')) {
	function cmx_budget_overview_money(float $value): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')) {
			return (string) cmx_format_swiss_number($value, 2);
		}

		return \number_format($value, 2, ',', "'");
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_signed_money')) {
	function cmx_budget_overview_signed_money(float $value): string {
		$prefix = $value > 0 ? '+' : '';
		return $prefix . cmx_budget_overview_money($value);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_budget_taxonomy')) {
	function cmx_budget_overview_budget_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_budget_admin_taxonomy')) {
			return (string) cmx_budget_admin_taxonomy();
		}

		if (\defined(__NAMESPACE__ . '\\TAX_BUDGET_KATEGORIEN')) {
			return (string) \constant(__NAMESPACE__ . '\\TAX_BUDGET_KATEGORIEN');
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_tax_key')) {
			return (string) cmx_tax_key('budget', 'Kategorien');
		}

		return 'budget_kategorien';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_belege_taxonomy')) {
	function cmx_budget_overview_belege_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_belege_taxonomy')) {
			return (string) cmx_belege_taxonomy();
		}
		if (\taxonomy_exists('belege_kategorien')) {
			return 'belege_kategorien';
		}
		if (\taxonomy_exists('beleg_kategorie')) {
			return 'beleg_kategorie';
		}
		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_contact_id')) {
	function cmx_budget_overview_contact_id(int $post_id): int {
		$key = \defined(__NAMESPACE__ . '\\CMX_BUDGET_KONTAKT_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BUDGET_KONTAKT_META')
			: '_cmx_budget_kontakt_id';
		return (int) \get_post_meta($post_id, $key, true);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_plan_amount')) {
	function cmx_budget_overview_plan_amount(int $post_id): float {
		$betrag = (string) \get_post_meta($post_id, CMX_BUDGET_KOSTEN_BETRAG_META, true);
		$anteil_betrag = (string) \get_post_meta($post_id, CMX_BUDGET_KOSTEN_ANTEIL_BETRAG_META, true);
		$anteil = (string) \get_post_meta($post_id, CMX_BUDGET_KOSTEN_ANTEIL_META, true);

		if (\trim($anteil_betrag) === '' && \trim($betrag) !== '' && \trim($anteil) !== '') {
			$anteil_betrag = \function_exists(__NAMESPACE__ . '\\cmx_budget_kosten_calculate_anteil_betrag')
				? (string) cmx_budget_kosten_calculate_anteil_betrag($betrag, $anteil)
				: $anteil_betrag;
		}

		$raw = \trim($anteil_betrag) !== '' ? $anteil_betrag : $betrag;
		if ($raw === '') {
			return 0.0;
		}

		$normalized = \function_exists(__NAMESPACE__ . '\\cmx_budget_kosten_normalize_decimal')
			? (string) cmx_budget_kosten_normalize_decimal($raw)
			: (string) $raw;

		return (float) $normalized;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_period_key')) {
	function cmx_budget_overview_period_key(int $post_id): string {
		$value = (string) \get_post_meta($post_id, CMX_BUDGET_KOSTEN_ZAHLBAR_PRO_META, true);
		$value = \sanitize_key($value);
		if (!\in_array($value, ['monat', 'quartal', 'halbjaehrlich', 'jaehrlich'], true)) {
			$value = 'monat';
		}
		return $value;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_period_factor')) {
	function cmx_budget_overview_period_factor(string $period): int {
		$map = [
			'monat'         => 1,
			'quartal'       => 3,
			'halbjaehrlich' => 6,
			'jaehrlich'     => 12,
		];

		return (int) ($map[$period] ?? 1);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_period_range')) {
	function cmx_budget_overview_period_range(string $period, ?\DateTimeImmutable $now = null): array {
		$now = $now ?: cmx_budget_overview_now();
		$tz = $now->getTimezone();
		$year = (int) $now->format('Y');
		$month = (int) $now->format('n');

		switch ($period) {
			case 'quartal':
				$quarter = (int) \floor(($month - 1) / 3) + 1;
				$start_month = (($quarter - 1) * 3) + 1;
				$start = new \DateTimeImmutable(\sprintf('%04d-%02d-01 00:00:00', $year, $start_month), $tz);
				$end = $start->modify('+2 months')->modify('last day of this month')->setTime(23, 59, 59);
				$label = 'Q' . $quarter . ' ' . $year;
				break;

			case 'halbjaehrlich':
				$half = $month <= 6 ? 1 : 2;
				$start_month = $half === 1 ? 1 : 7;
				$start = new \DateTimeImmutable(\sprintf('%04d-%02d-01 00:00:00', $year, $start_month), $tz);
				$end = $start->modify('+5 months')->modify('last day of this month')->setTime(23, 59, 59);
				$label = $half . '. Halbjahr ' . $year;
				break;

			case 'jaehrlich':
				$start = new \DateTimeImmutable(\sprintf('%04d-01-01 00:00:00', $year), $tz);
				$end = $start->modify('last day of December')->setTime(23, 59, 59);
				$label = (string) $year;
				break;

			case 'monat':
			default:
				$start = $now->modify('first day of this month')->setTime(0, 0, 0);
				$end = $now->modify('last day of this month')->setTime(23, 59, 59);
				$label = \wp_date('F Y', $start->getTimestamp(), $tz);
				break;
		}

		return [
			'start'      => $start,
			'end'        => $end,
			'start_date' => $start->format('Y-m-d'),
			'end_date'   => $end->format('Y-m-d'),
			'label'      => $label,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_monthly_plan_amount')) {
	function cmx_budget_overview_monthly_plan_amount(int $post_id): float {
		$factor = max(1, cmx_budget_overview_period_factor(cmx_budget_overview_period_key($post_id)));
		return (float) \round(cmx_budget_overview_plan_amount($post_id) / $factor, 2);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_matching_beleg_term_slugs')) {
	function cmx_budget_overview_matching_beleg_term_slugs(int $budget_id): array {
		static $term_map = null;

		$budget_tax = cmx_budget_overview_budget_taxonomy();
		$belege_tax = cmx_budget_overview_belege_taxonomy();
		if ($budget_tax === '' || $belege_tax === '' || !\taxonomy_exists($budget_tax) || !\taxonomy_exists($belege_tax)) {
			return [];
		}

		$budget_terms = \get_the_terms($budget_id, $budget_tax);
		if (!\is_array($budget_terms) || \is_wp_error($budget_terms) || $budget_terms === []) {
			return [];
		}

		if ($term_map === null) {
			$term_map = [];
			$belege_terms = \get_terms([
				'taxonomy'   => $belege_tax,
				'hide_empty' => false,
			]);
			if (\is_array($belege_terms) && !\is_wp_error($belege_terms)) {
				foreach ($belege_terms as $term) {
					if (!$term instanceof \WP_Term) {
						continue;
					}
					$slug = (string) $term->slug;
					$name_key = \sanitize_title((string) $term->name);
					if ($slug !== '') {
						$term_map[$slug] = $slug;
					}
					if ($name_key !== '') {
						$term_map[$name_key] = $slug;
					}
				}
			}
		}

		$matched = [];
		foreach ($budget_terms as $term) {
			if (!$term instanceof \WP_Term) {
				continue;
			}
			$candidates = [
				(string) $term->slug,
				\sanitize_title((string) $term->name),
			];
			foreach ($candidates as $candidate) {
				if ($candidate !== '' && isset($term_map[$candidate])) {
					$matched[] = (string) $term_map[$candidate];
				}
			}
		}

		return \array_values(\array_unique(\array_filter($matched)));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_matching_beleg_ids')) {
	function cmx_budget_overview_matching_beleg_ids(int $budget_id, array $range): array {
		static $cache = [];

		$cache_key = $budget_id . '|' . ($range['start_date'] ?? '') . '|' . ($range['end_date'] ?? '');
		if (isset($cache[$cache_key])) {
			return $cache[$cache_key];
		}

		$contact_id = cmx_budget_overview_contact_id($budget_id);
		$category_slugs = cmx_budget_overview_matching_beleg_term_slugs($budget_id);
		if ($contact_id <= 0 && $category_slugs === []) {
			$cache[$cache_key] = [];
			return $cache[$cache_key];
		}

		$date_meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_RNG_DATUM')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_RNG_DATUM')
			: '_cmx_beleg_rng_datum';
		$contact_meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID')
			: '_cmx_beleg_kontakt_id';

		$args = [
			'post_type'              => 'belege',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => [
				'relation' => 'AND',
				[
					'key'     => $date_meta_key,
					'value'   => [(string) ($range['start_date'] ?? ''), (string) ($range['end_date'] ?? '')],
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				],
			],
		];

		if ($contact_id > 0) {
			$args['meta_query'][] = [
				'key'     => $contact_meta_key,
				'value'   => $contact_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			];
		}

		$belege_tax = cmx_budget_overview_belege_taxonomy();
		if ($category_slugs !== [] && $belege_tax !== '') {
			$args['tax_query'] = [
				[
					'taxonomy' => $belege_tax,
					'field'    => 'slug',
					'terms'    => $category_slugs,
				],
			];
		}

		$ids = \get_posts($args);
		$cache[$cache_key] = \array_values(\array_map('intval', (array) $ids));
		return $cache[$cache_key];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_beleg_side')) {
	function cmx_budget_overview_beleg_side(int $beleg_id): string {
		static $cache = [];

		if (isset($cache[$beleg_id])) {
			return $cache[$beleg_id];
		}

		$post = \get_post($beleg_id);
		if (!$post instanceof \WP_Post) {
			$cache[$beleg_id] = '';
			return '';
		}

		$raw_direction = \sanitize_key((string) \get_post_meta(
			$beleg_id,
			\defined(__NAMESPACE__ . '\\CMX_BELEG_META_RICHTUNG') ? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_RICHTUNG') : '_cmx_beleg_richtung',
			true
		));

		$legacy_income = ['einnahme', 'einnahmen', 'income', 'revenues'];
		$legacy_expense = ['ausgabe', 'ausgaben', 'expense', 'expenses'];
		if (\in_array($raw_direction, $legacy_income, true)) {
			$cache[$beleg_id] = 'income';
			return $cache[$beleg_id];
		}
		if (\in_array($raw_direction, $legacy_expense, true)) {
			$cache[$beleg_id] = 'expense';
			return $cache[$beleg_id];
		}

		$normalized_direction = \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_normalize_richtung')
			? (string) cmxbu_beleg_export_normalize_richtung($raw_direction)
			: $raw_direction;

		$beleg_type = 'rechnung';
		if (\function_exists(__NAMESPACE__ . '\\cmx_get_beleg_type')) {
			[, $beleg_type] = cmx_get_beleg_type($post);
		}
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_pdf_effective_type')) {
			$beleg_type = (string) cmxbu_get_beleg_pdf_effective_type($beleg_id, (string) $beleg_type);
		}

		$map = \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_direction_side_map')
			? (array) cmxbu_beleg_export_direction_side_map((string) $beleg_type)
			: [];

		$side = (string) ($map[$normalized_direction] ?? '');
		if ($side === '') {
			if ($normalized_direction === 'ausgang') {
				$side = 'income';
			} elseif ($normalized_direction === 'eingang') {
				$side = 'expense';
			}
		}

		$cache[$beleg_id] = $side;
		return $cache[$beleg_id];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_beleg_total')) {
	function cmx_budget_overview_beleg_total(int $beleg_id): float {
		static $cache = [];

		if (isset($cache[$beleg_id])) {
			return $cache[$beleg_id];
		}

		$total = 0.0;
		$post = \get_post($beleg_id);
		if ($post instanceof \WP_Post && \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_mwst_context') && \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_calc')) {
			$beleg_type = 'rechnung';
			if (\function_exists(__NAMESPACE__ . '\\cmx_get_beleg_type')) {
				[, $beleg_type] = cmx_get_beleg_type($post);
			}
			if (\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_effective_type')) {
				$beleg_type = (string) cmxbu_beleg_export_effective_type($post, (string) $beleg_type);
			}
			$mwst_ctx = (array) cmxbu_beleg_export_mwst_context($beleg_id, (string) $beleg_type);
			$calc = (array) cmxbu_beleg_export_calc(
				$beleg_id,
				(float) ($mwst_ctx['rate'] ?? 0.0),
				!empty($mwst_ctx['is_brutto'])
			);
			$total = (float) ($calc['total'] ?? 0.0);
		}

		if ($total <= 0.0) {
			$manual = (string) \get_post_meta($beleg_id, '_cmx_beleg_summe_override', true);
			if (\trim($manual) !== '') {
				$normalized = \function_exists(__NAMESPACE__ . '\\cmx_budget_kosten_normalize_decimal')
					? (string) cmx_budget_kosten_normalize_decimal($manual)
					: $manual;
				$total = (float) $normalized;
			}
		}

		if ($total <= 0.0 && \function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
			$calc = (array) cmxbu_get_beleg_positionen_calc($beleg_id);
			$total = (float) ($calc['total'] ?? 0.0);
		}

		$cache[$beleg_id] = (float) \round($total, 2);
		return $cache[$beleg_id];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_row_metrics')) {
	function cmx_budget_overview_row_metrics(int $post_id): array {
		static $cache = [];

		if (isset($cache[$post_id])) {
			return $cache[$post_id];
		}

		$plan = cmx_budget_overview_plan_amount($post_id);
		$type = \function_exists(__NAMESPACE__ . '\\cmx_budget_admin_cost_type')
			? (string) cmx_budget_admin_cost_type($post_id)
			: 'einnahme';
		$period = cmx_budget_overview_period_key($post_id);
		$range = cmx_budget_overview_period_range($period);
		$wanted_side = $type === 'ausgabe' ? 'expense' : 'income';

		$actual = 0.0;
		$matched_ids = [];
		foreach (cmx_budget_overview_matching_beleg_ids($post_id, $range) as $beleg_id) {
			if (cmx_budget_overview_beleg_side((int) $beleg_id) !== $wanted_side) {
				continue;
			}
			$matched_ids[] = (int) $beleg_id;
			$actual += cmx_budget_overview_beleg_total((int) $beleg_id);
		}
		$actual = (float) \round($actual, 2);
		$diff = (float) \round($actual - $plan, 2);
		$status = cmx_budget_overview_status_data($plan, $actual, $type);

		$cache[$post_id] = [
			'plan'             => $plan,
			'actual'           => $actual,
			'diff'             => $diff,
			'type'             => $type,
			'period'           => $period,
			'period_label'     => (string) ($range['label'] ?? ''),
			'matched_belege'   => $matched_ids,
			'matched_count'    => \count($matched_ids),
			'monthly_plan'     => cmx_budget_overview_monthly_plan_amount($post_id),
			'status'           => $status,
		];

		return $cache[$post_id];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_status_data')) {
	function cmx_budget_overview_status_data(float $plan, float $actual, string $type): array {
		$plan = max(0.0, (float) $plan);
		$actual = max(0.0, (float) $actual);
		$type = $type === 'ausgabe' ? 'ausgabe' : 'einnahme';

		if ($plan <= 0.0) {
			if ($actual <= 0.0) {
				return ['slug' => 'green', 'label' => 'Grün', 'text' => 'im Plan'];
			}
			return $type === 'ausgabe'
				? ['slug' => 'red', 'label' => 'Rot', 'text' => 'kritisch']
				: ['slug' => 'green', 'label' => 'Grün', 'text' => 'über Plan'];
		}

		$ratio = $actual / $plan;
		if ($type === 'einnahme') {
			if ($ratio >= 0.95) {
				return ['slug' => 'green', 'label' => 'Grün', 'text' => 'im Plan'];
			}
			if ($ratio >= 0.80) {
				return ['slug' => 'yellow', 'label' => 'Gelb', 'text' => 'leichte Abweichung'];
			}
			return ['slug' => 'red', 'label' => 'Rot', 'text' => 'kritisch'];
		}

		if ($ratio <= 1.05) {
			return ['slug' => 'green', 'label' => 'Grün', 'text' => 'im Plan'];
		}
		if ($ratio <= 1.20) {
			return ['slug' => 'yellow', 'label' => 'Gelb', 'text' => 'leichte Abweichung'];
		}
		return ['slug' => 'red', 'label' => 'Rot', 'text' => 'kritisch'];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_status_badge_html')) {
	function cmx_budget_overview_status_badge_html(array $status): string {
		$slug = (string) ($status['slug'] ?? 'green');
		$label = (string) ($status['label'] ?? 'Grün');
		$text = (string) ($status['text'] ?? '');

		return '<span class="cmx-budget-status cmx-budget-status-' . \esc_attr($slug) . '"' . ($text !== '' ? ' title="' . \esc_attr($text) . '"' : '') . '><span class="cmx-budget-status-dot" aria-hidden="true"></span>' . \esc_html($label) . '</span>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_budget_ids')) {
	function cmx_budget_overview_budget_ids(): array {
		static $ids = null;

		if ($ids !== null) {
			return $ids;
		}

		$ids = \array_map('intval', (array) \get_posts([
			'post_type'              => 'budget',
			'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		]));

		return $ids;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_month_actuals')) {
	function cmx_budget_overview_month_actuals(): array {
		static $cache = null;

		if ($cache !== null) {
			return $cache;
		}

		$range = cmx_budget_overview_period_range('monat');
		$date_meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_RNG_DATUM')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_RNG_DATUM')
			: '_cmx_beleg_rng_datum';

		$beleg_ids = \array_map('intval', (array) \get_posts([
			'post_type'              => 'belege',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => [
				[
					'key'     => $date_meta_key,
					'value'   => [(string) $range['start_date'], (string) $range['end_date']],
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				],
			],
		]));

		$totals = [
			'income'  => 0.0,
			'expense' => 0.0,
		];
		foreach ($beleg_ids as $beleg_id) {
			$side = cmx_budget_overview_beleg_side($beleg_id);
			if (!isset($totals[$side])) {
				continue;
			}
			$totals[$side] += cmx_budget_overview_beleg_total($beleg_id);
		}

		$cache = [
			'income'  => (float) \round($totals['income'], 2),
			'expense' => (float) \round($totals['expense'], 2),
		];

		return $cache;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_forecast_totals')) {
	function cmx_budget_overview_forecast_totals(): array {
		$month_actuals = cmx_budget_overview_month_actuals();
		$now = cmx_budget_overview_now();
		$days_in_month = (int) $now->format('t');
		$current_day = max(1, (int) $now->format('j'));
		$factor = $days_in_month / $current_day;

		return [
			'income'  => (float) \round(((float) $month_actuals['income']) * $factor, 2),
			'expense' => (float) \round(((float) $month_actuals['expense']) * $factor, 2),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_month_plan_totals')) {
	function cmx_budget_overview_month_plan_totals(): array {
		static $cache = null;

		if ($cache !== null) {
			return $cache;
		}

		$totals = [
			'income'  => 0.0,
			'expense' => 0.0,
		];

		foreach (cmx_budget_overview_budget_ids() as $post_id) {
			$type = \function_exists(__NAMESPACE__ . '\\cmx_budget_admin_cost_type')
				? (string) cmx_budget_admin_cost_type((int) $post_id)
				: 'einnahme';
			$key = $type === 'ausgabe' ? 'expense' : 'income';
			$totals[$key] += cmx_budget_overview_monthly_plan_amount((int) $post_id);
		}

		$cache = [
			'income'  => (float) \round($totals['income'], 2),
			'expense' => (float) \round($totals['expense'], 2),
		];

		return $cache;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_parse_date_ts')) {
	function cmx_budget_overview_parse_date_ts(string $raw): int {
		$raw = \trim($raw);
		if ($raw === '') {
			return 0;
		}

		if (\ctype_digit($raw) && \strlen($raw) >= 9 && \strlen($raw) <= 11) {
			return (int) $raw;
		}

		if (\ctype_digit($raw) && \strlen($raw) === 8) {
			$raw = \substr($raw, 0, 4) . '-' . \substr($raw, 4, 2) . '-' . \substr($raw, 6, 2);
		}

		foreach (['Y-m-d', 'd.m.Y', 'Y/m/d', 'd/m/Y'] as $format) {
			$date = \DateTimeImmutable::createFromFormat('!' . $format, $raw, cmx_budget_overview_timezone());
			if (!$date instanceof \DateTimeImmutable) {
				continue;
			}
			$errors = \DateTimeImmutable::getLastErrors();
			if (\is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
				continue;
			}
			return $date->setTime(0, 0, 0)->getTimestamp();
		}

		$timestamp = \strtotime($raw);
		return $timestamp ? (int) $timestamp : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_due_raw')) {
	function cmx_budget_overview_due_raw(int $beleg_id): string {
		$keys = [];

		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_FAELLIG')) {
			$keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_FAELLIG');
		}

		$keys = \array_merge($keys, [
			'_cmx_beleg_faelligkeitsdatum',
			'_cmx_beleg_faellig_am',
			'cmx_beleg_faelligkeitsdatum',
			'cmx_beleg_faellig_am',
		]);

		foreach (\array_values(\array_unique(\array_filter($keys))) as $key) {
			$value = \trim((string) \get_post_meta($beleg_id, $key, true));
			if ($value !== '') {
				return $value;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_is_unpaid_beleg')) {
	function cmx_budget_overview_is_unpaid_beleg(int $beleg_id): bool {
		$paid_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM')
			: '_cmx_beleg_bezahlt_am';
		$keys = \array_values(\array_unique(\array_filter([
			$paid_key,
			\ltrim($paid_key, '_'),
			'_cmx_beleg_bezahlt_am',
			'cmx_beleg_bezahlt_am',
		])));

		foreach ($keys as $key) {
			$value = \trim((string) \get_post_meta($beleg_id, $key, true));
			if ($value === '' || $value === '0' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
				continue;
			}
			if (cmx_budget_overview_parse_date_ts($value) > 0) {
				return false;
			}
		}

		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_beleg_effective_type')) {
	function cmx_budget_overview_beleg_effective_type(int $beleg_id): string {
		static $cache = [];

		if (isset($cache[$beleg_id])) {
			return $cache[$beleg_id];
		}

		$type = '';
		$post = \get_post($beleg_id);
		if ($post instanceof \WP_Post && \function_exists(__NAMESPACE__ . '\\cmx_get_beleg_type')) {
			[, $type] = cmx_get_beleg_type($post);
		}

		$type = \sanitize_key((string) $type);
		if ($type === '') {
			$type = 'rechnung';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_pdf_effective_type')) {
			$type = \sanitize_key((string) cmxbu_get_beleg_pdf_effective_type($beleg_id, $type));
		}

		$cache[$beleg_id] = $type;
		return $type;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_liquidity_side')) {
	function cmx_budget_overview_liquidity_side(int $beleg_id): string {
		if (!cmx_budget_overview_is_unpaid_beleg($beleg_id)) {
			return '';
		}

		$side = cmx_budget_overview_beleg_side($beleg_id);
		$type = cmx_budget_overview_beleg_effective_type($beleg_id);

		if ($side === 'income' && \in_array($type, ['rechnung', 'rechnungen'], true)) {
			return 'income';
		}

		if ($side === 'expense' && \in_array($type, ['lieferantenrechnung', 'lieferantenrechnungen'], true)) {
			return 'expense';
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_liquidity_forecast_data')) {
	function cmx_budget_overview_liquidity_forecast_data(): array {
		static $cache = null;

		if ($cache !== null) {
			return $cache;
		}

		$now = cmx_budget_overview_now()->setTime(0, 0, 0);
		$month_start = $now->modify('first day of this month')->setTime(0, 0, 0);
		$today_ts = $now->getTimestamp();
		$next_30_ts = $now->modify('+30 days')->setTime(23, 59, 59)->getTimestamp();

		$rows = [];
		$month_rows = [];

		$rows['overdue'] = [
			'label'         => 'Überfällig',
			'income_count'  => 0,
			'expense_count' => 0,
			'income'        => 0.0,
			'expense'       => 0.0,
			'kind'          => 'overdue',
		];

		for ($index = 0; $index < 6; $index++) {
			$bucket_date = $month_start->modify('+' . $index . ' months');
			$key = $bucket_date->format('Y-m');
			$month_rows[$key] = [
				'label'         => \wp_date('F Y', $bucket_date->getTimestamp(), $bucket_date->getTimezone()),
				'income_count'  => 0,
				'expense_count' => 0,
				'income'        => 0.0,
				'expense'       => 0.0,
				'kind'          => 'month',
			];
		}

		$rows['later'] = [
			'label'         => 'Später',
			'income_count'  => 0,
			'expense_count' => 0,
			'income'        => 0.0,
			'expense'       => 0.0,
			'kind'          => 'later',
		];
		$rows['undated'] = [
			'label'         => 'Ohne Fälligkeit',
			'income_count'  => 0,
			'expense_count' => 0,
			'income'        => 0.0,
			'expense'       => 0.0,
			'kind'          => 'undated',
		];

		$summary = [
			'total_count'      => 0,
			'income_count'     => 0,
			'expense_count'    => 0,
			'income_open'      => 0.0,
			'expense_open'     => 0.0,
			'net_open'         => 0.0,
			'income_next_30'   => 0.0,
			'expense_next_30'  => 0.0,
			'net_next_30'      => 0.0,
			'income_overdue'   => 0.0,
			'expense_overdue'  => 0.0,
			'net_overdue'      => 0.0,
			'income_undated'   => 0.0,
			'expense_undated'  => 0.0,
		];

		$beleg_ids = \array_map('intval', (array) \get_posts([
			'post_type'              => 'belege',
			'post_status'            => ['publish', 'private', 'pending', 'future'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		]));

		foreach ($beleg_ids as $beleg_id) {
			if ($beleg_id <= 0) {
				continue;
			}

			$liquidity_side = cmx_budget_overview_liquidity_side($beleg_id);
			if ($liquidity_side === '') {
				continue;
			}

			$amount = (float) \round(cmx_budget_overview_beleg_total($beleg_id), 2);
			if ($amount <= 0.0) {
				continue;
			}

			$summary['total_count']++;
			$summary[$liquidity_side . '_count']++;
			$summary[$liquidity_side . '_open'] += $amount;

			$due_ts = cmx_budget_overview_parse_date_ts(cmx_budget_overview_due_raw($beleg_id));
			if ($due_ts >= $today_ts && $due_ts <= $next_30_ts) {
				$summary[$liquidity_side . '_next_30'] += $amount;
			}

			if ($due_ts <= 0) {
				$summary[$liquidity_side . '_undated'] += $amount;
				$rows['undated'][$liquidity_side . '_count']++;
				$rows['undated'][$liquidity_side] += $amount;
				continue;
			}

			if ($due_ts < $today_ts) {
				$summary[$liquidity_side . '_overdue'] += $amount;
				$rows['overdue'][$liquidity_side . '_count']++;
				$rows['overdue'][$liquidity_side] += $amount;
				continue;
			}

			$month_key = \wp_date('Y-m', $due_ts, cmx_budget_overview_timezone());
			if (isset($month_rows[$month_key])) {
				$month_rows[$month_key][$liquidity_side . '_count']++;
				$month_rows[$month_key][$liquidity_side] += $amount;
				continue;
			}

			$rows['later'][$liquidity_side . '_count']++;
			$rows['later'][$liquidity_side] += $amount;
		}

		foreach (['income_open', 'expense_open', 'income_next_30', 'expense_next_30', 'income_overdue', 'expense_overdue', 'income_undated', 'expense_undated'] as $key) {
			$summary[$key] = (float) \round((float) $summary[$key], 2);
		}
		$summary['net_open'] = (float) \round($summary['income_open'] - $summary['expense_open'], 2);
		$summary['net_next_30'] = (float) \round($summary['income_next_30'] - $summary['expense_next_30'], 2);
		$summary['net_overdue'] = (float) \round($summary['income_overdue'] - $summary['expense_overdue'], 2);

		foreach ($month_rows as $key => $row) {
			$month_rows[$key]['income'] = (float) \round((float) $row['income'], 2);
			$month_rows[$key]['expense'] = (float) \round((float) $row['expense'], 2);
			$month_rows[$key]['net'] = (float) \round((float) $month_rows[$key]['income'] - (float) $month_rows[$key]['expense'], 2);
			$month_rows[$key]['count'] = (int) $row['income_count'] + (int) $row['expense_count'];
		}

		foreach (['overdue', 'later', 'undated'] as $key) {
			$rows[$key]['income'] = (float) \round((float) $rows[$key]['income'], 2);
			$rows[$key]['expense'] = (float) \round((float) $rows[$key]['expense'], 2);
			$rows[$key]['net'] = (float) \round((float) $rows[$key]['income'] - (float) $rows[$key]['expense'], 2);
			$rows[$key]['count'] = (int) $rows[$key]['income_count'] + (int) $rows[$key]['expense_count'];
		}

		$forecast_rows = [];
		if ($summary['total_count'] > 0) {
			if ($rows['overdue']['count'] > 0) {
				$forecast_rows[] = $rows['overdue'];
			}
			$forecast_rows = \array_merge($forecast_rows, \array_values($month_rows));
			if ($rows['later']['count'] > 0) {
				$forecast_rows[] = $rows['later'];
			}
			if ($rows['undated']['count'] > 0) {
				$forecast_rows[] = $rows['undated'];
			}
		}

		$cache = [
			'summary' => $summary,
			'rows'    => $forecast_rows,
		];

		return $cache;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_rows')) {
	function cmx_budget_overview_rows(): array {
		$rows = [];

		foreach (cmx_budget_overview_budget_ids() as $post_id) {
			$post_id = (int) $post_id;
			$metrics = cmx_budget_overview_row_metrics($post_id);
			$contact = \function_exists(__NAMESPACE__ . '\\cmx_budget_admin_contact_display')
				? (array) cmx_budget_admin_contact_display($post_id)
				: ['label' => '', 'url' => ''];
			$rows[] = [
				'post_id'       => $post_id,
				'name'          => cmx_normalize_minus_sign((string) \get_the_title($post_id)),
				'categories'    => \function_exists(__NAMESPACE__ . '\\cmx_budget_admin_categories_display') ? (string) cmx_budget_admin_categories_display($post_id) : '',
				'contact_label' => (string) ($contact['label'] ?? ''),
				'period_label'  => \function_exists(__NAMESPACE__ . '\\cmx_budget_admin_zahlbar_pro_display') ? (string) cmx_budget_admin_zahlbar_pro_display($post_id) : '',
				'plan'          => (float) $metrics['plan'],
				'actual'        => (float) $metrics['actual'],
				'diff'          => (float) $metrics['diff'],
				'status'        => (array) $metrics['status'],
				'type'          => (string) $metrics['type'],
				'matched_count' => (int) $metrics['matched_count'],
			];
		}

		return $rows;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_alerts')) {
	function cmx_budget_overview_alerts(): array {
		$alerts = [];

		foreach (cmx_budget_overview_rows() as $row) {
			$name = (string) ($row['name'] ?? '');
			$plan = (float) ($row['plan'] ?? 0.0);
			$actual = (float) ($row['actual'] ?? 0.0);
			$type = (string) ($row['type'] ?? 'einnahme');
			if ($plan <= 0.0 || $name === '') {
				continue;
			}

			if ($type === 'ausgabe' && $actual > ($plan * 1.20)) {
				$alerts[] = [
					'slug' => 'red',
					'text' => $name . ' ' . \round((($actual / $plan) - 1) * 100) . '% über Budget',
				];
			}
			if ($type === 'einnahme' && $actual < ($plan * 0.80)) {
				$alerts[] = [
					'slug' => 'yellow',
					'text' => $name . ' deutlich unter Plan',
				];
			}
		}

		if ($alerts === []) {
			$alerts[] = [
				'slug' => 'green',
				'text' => 'Aktuell keine kritischen Abweichungen im Budget.',
			];
		}

		return \array_slice($alerts, 0, 8);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_dashboard_data')) {
	function cmx_budget_overview_dashboard_data(): array {
		$planned = cmx_budget_overview_month_plan_totals();
		$actual = cmx_budget_overview_month_actuals();
		$forecast = cmx_budget_overview_forecast_totals();

		$planned_profit = (float) \round($planned['income'] - $planned['expense'], 2);
		$actual_profit = (float) \round($actual['income'] - $actual['expense'], 2);
		$forecast_profit = (float) \round($forecast['income'] - $forecast['expense'], 2);

		return [
			'income' => [
				'label'    => 'Einnahmen geplant vs. real',
				'plan'     => (float) $planned['income'],
				'actual'   => (float) $actual['income'],
				'forecast' => (float) $forecast['income'],
				'status'   => cmx_budget_overview_status_data((float) $planned['income'], (float) $actual['income'], 'einnahme'),
			],
			'expense' => [
				'label'    => 'Ausgaben geplant vs. real',
				'plan'     => (float) $planned['expense'],
				'actual'   => (float) $actual['expense'],
				'forecast' => (float) $forecast['expense'],
				'status'   => cmx_budget_overview_status_data((float) $planned['expense'], (float) $actual['expense'], 'ausgabe'),
			],
			'profit' => [
				'label'    => 'Gewinn geplant vs. real',
				'plan'     => $planned_profit,
				'actual'   => $actual_profit,
				'forecast' => $forecast_profit,
				'status'   => cmx_budget_overview_status_data(max(0.0, $planned_profit), max(0.0, $actual_profit), 'einnahme'),
			],
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_bar_rows_html')) {
	function cmx_budget_overview_bar_rows_html(float $plan, float $actual, float $forecast): string {
		$scale = max(1.0, $plan, $actual, $forecast);
		$rows = [
			['label' => 'Plan', 'value' => $plan, 'class' => 'plan'],
			['label' => 'Ist', 'value' => $actual, 'class' => 'actual'],
			['label' => 'Forecast', 'value' => $forecast, 'class' => 'forecast'],
		];

		$html = '';
		foreach ($rows as $row) {
			$width = min(100.0, max(0.0, (((float) $row['value']) / $scale) * 100));
			$html .= '<div class="cmx-budget-overview-bar-row">';
			$html .= '<span class="cmx-budget-overview-bar-label">' . \esc_html((string) $row['label']) . '</span>';
			$html .= '<span class="cmx-budget-overview-bar-track"><span class="cmx-budget-overview-bar-fill is-' . \esc_attr((string) $row['class']) . '" style="width:' . \esc_attr((string) \round($width, 2)) . '%"></span></span>';
			$html .= '<span class="cmx-budget-overview-bar-value">' . \esc_html(cmx_budget_overview_money((float) $row['value'])) . '</span>';
			$html .= '</div>';
		}

		return $html;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_overview_render_html')) {
	function cmx_budget_overview_render_html(): string {
		$rows = cmx_budget_overview_rows();
		$cards = cmx_budget_overview_dashboard_data();
		$alerts = cmx_budget_overview_alerts();
		$liquidity = cmx_budget_overview_liquidity_forecast_data();
		$month_label = (string) (cmx_budget_overview_period_range('monat')['label'] ?? '');

		\ob_start();
		?>
		<div class="cmx-budget-overview">
			<div class="cmx-budget-overview-hero">
				<div>
					<h2>Budget Übersicht</h2>
					<p>Plan, Ist, Forecast und automatische Abweichungen auf Basis Deiner Budget-Zeilen und vorhandenen Belege.</p>
				</div>
				<div class="cmx-budget-overview-hero-meta"><?php echo \esc_html($month_label); ?></div>
			</div>

			<div class="cmx-budget-overview-cards">
				<?php foreach ($cards as $card) : ?>
					<div class="cmx-budget-overview-card">
						<div class="cmx-budget-overview-card-head">
							<h3><?php echo \esc_html((string) $card['label']); ?></h3>
							<?php echo cmx_budget_overview_status_badge_html((array) $card['status']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
						<div class="cmx-budget-overview-kpis">
							<div>
								<span>Plan</span>
								<strong><?php echo \esc_html(cmx_budget_overview_money((float) $card['plan'])); ?></strong>
							</div>
							<div>
								<span>Ist</span>
								<strong><?php echo \esc_html(cmx_budget_overview_money((float) $card['actual'])); ?></strong>
							</div>
							<div>
								<span>Forecast</span>
								<strong><?php echo \esc_html(cmx_budget_overview_money((float) $card['forecast'])); ?></strong>
							</div>
						</div>
						<div class="cmx-budget-overview-bars">
							<?php echo cmx_budget_overview_bar_rows_html((float) $card['plan'], (float) $card['actual'], (float) $card['forecast']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="cmx-budget-overview-bottom">
				<div class="cmx-budget-overview-panel">
					<div class="cmx-budget-overview-panel-head">
						<h3>Alerts / Hinweise</h3>
					</div>
					<ul class="cmx-budget-overview-alerts">
						<?php foreach ($alerts as $alert) : ?>
							<li class="is-<?php echo \esc_attr((string) ($alert['slug'] ?? 'green')); ?>">
								<span class="cmx-budget-overview-alert-dot" aria-hidden="true"></span>
								<span><?php echo \esc_html((string) ($alert['text'] ?? '')); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

				<div class="cmx-budget-overview-panel">
					<div class="cmx-budget-overview-panel-head">
						<h3>Budget vs. Ist</h3>
						<span><?php echo \esc_html(\count($rows)); ?> Positionen</span>
					</div>
					<table class="widefat striped cmx-budget-overview-table">
						<thead>
							<tr>
								<th>Name</th>
								<th>Kategorien</th>
								<th>Kontakt</th>
								<th>Zahlbar pro</th>
								<th>Plan</th>
								<th>Ist</th>
								<th>Differenz</th>
								<th>Status</th>
							</tr>
						</thead>
						<tbody>
							<?php if ($rows === []) : ?>
								<tr>
									<td colspan="8">Noch keine Budget-Zeilen vorhanden.</td>
								</tr>
							<?php else : ?>
								<?php foreach ($rows as $row) : ?>
									<tr>
										<td><a href="<?php echo \esc_url((string) \get_edit_post_link((int) $row['post_id'], '')); ?>"><?php echo \esc_html((string) $row['name']); ?></a></td>
										<td><?php echo \esc_html((string) $row['categories']); ?></td>
										<td><?php echo \esc_html((string) $row['contact_label']); ?></td>
										<td><?php echo \esc_html((string) $row['period_label']); ?></td>
										<td class="num"><?php echo \esc_html(cmx_budget_overview_money((float) $row['plan'])); ?></td>
										<td class="num"><?php echo \esc_html(cmx_budget_overview_money((float) $row['actual'])); ?></td>
										<td class="num"><?php echo \esc_html(cmx_budget_overview_signed_money((float) $row['diff'])); ?></td>
										<td><?php echo cmx_budget_overview_status_badge_html((array) $row['status']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

				<div class="cmx-budget-overview-panel cmx-budget-overview-panel-liquid">
					<div class="cmx-budget-overview-panel-head">
						<h3>Liquiditätsprognose</h3>
						<span><?php echo \esc_html((string) ((int) ($liquidity['summary']['total_count'] ?? 0))); ?> offene Belege</span>
					</div>
					<p class="cmx-budget-overview-panel-text">Erwartete Zahlungseingänge aus offenen Rechnungen und erwartete Zahlungsausgänge aus offenen Lieferantenrechnungen nach Fälligkeitsdatum.</p>

					<div class="cmx-budget-overview-liquid-kpis">
						<div class="cmx-budget-overview-liquid-kpi">
							<span>Erwartete Eingänge</span>
							<strong><?php echo \esc_html(cmx_budget_overview_money((float) ($liquidity['summary']['income_open'] ?? 0.0))); ?></strong>
							<small>30 Tage: <?php echo \esc_html(cmx_budget_overview_money((float) ($liquidity['summary']['income_next_30'] ?? 0.0))); ?></small>
						</div>
						<div class="cmx-budget-overview-liquid-kpi">
							<span>Erwartete Ausgänge</span>
							<strong><?php echo \esc_html(cmx_budget_overview_money((float) ($liquidity['summary']['expense_open'] ?? 0.0))); ?></strong>
							<small>30 Tage: <?php echo \esc_html(cmx_budget_overview_money((float) ($liquidity['summary']['expense_next_30'] ?? 0.0))); ?></small>
						</div>
						<div class="cmx-budget-overview-liquid-kpi">
							<span>Liquiditätssaldo</span>
							<strong><?php echo \esc_html(cmx_budget_overview_signed_money((float) ($liquidity['summary']['net_open'] ?? 0.0))); ?></strong>
							<small>30 Tage: <?php echo \esc_html(cmx_budget_overview_signed_money((float) ($liquidity['summary']['net_next_30'] ?? 0.0))); ?></small>
						</div>
					</div>

					<table class="widefat striped cmx-budget-overview-table cmx-budget-overview-liquid-table">
						<thead>
							<tr>
								<th>Zeitraum</th>
								<th class="num">Eingänge</th>
								<th class="num">Ausgänge</th>
								<th class="num">Saldo</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($liquidity['rows'])) : ?>
								<tr>
									<td colspan="4">Aktuell keine offenen Rechnungen oder Lieferantenrechnungen vorhanden.</td>
								</tr>
							<?php else : ?>
								<?php foreach ((array) $liquidity['rows'] as $forecast_row) : ?>
									<tr class="cmx-budget-overview-liquid-row is-<?php echo \esc_attr((string) ($forecast_row['kind'] ?? 'month')); ?>">
										<td><?php echo \esc_html((string) ($forecast_row['label'] ?? '')); ?></td>
										<td class="num"><?php echo \esc_html(cmx_budget_overview_money((float) ($forecast_row['income'] ?? 0.0))); ?></td>
										<td class="num"><?php echo \esc_html(cmx_budget_overview_money((float) ($forecast_row['expense'] ?? 0.0))); ?></td>
										<td class="num"><?php echo \esc_html(cmx_budget_overview_signed_money((float) ($forecast_row['net'] ?? 0.0))); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
		return (string) \ob_get_clean();
	}
}

\add_filter('views_edit-budget', function (array $views): array {
	$url = \add_query_arg([
		'post_type'       => 'budget',
		'cmx_budget_view' => 'uebersicht',
	], \admin_url('edit.php'));

	$class = cmx_budget_overview_is_active() ? ' class="current" aria-current="page"' : '';
	$views['cmx_budget_overview'] = '<a href="' . \esc_url($url) . '"' . $class . '>Übersicht</a>';
	return $views;
});

\add_filter('admin_body_class', function (string $classes): string {
	if (!cmx_budget_overview_is_active()) {
		return $classes;
	}

	return \trim($classes . ' cmx-budget-overview-active');
});

\add_action('manage_posts_extra_tablenav', function (string $which): void {
	if ($which !== 'top' || !cmx_budget_overview_is_active()) {
		return;
	}

	echo cmx_budget_overview_render_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
});

\add_action('admin_head-edit.php', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== 'budget') {
		return;
	}

	echo '<style>
		.cmx-budget-overview-active #posts-filter > .tablenav.top{
			height:auto;
			min-height:0;
			padding-top:0;
		}
		.cmx-budget-overview-active #posts-filter > .tablenav.top .alignleft.actions,
		.cmx-budget-overview-active #posts-filter > .tablenav.top .search-box,
		.cmx-budget-overview-active #posts-filter > .tablenav.top .tablenav-pages,
		.cmx-budget-overview-active #posts-filter > .tablenav.bottom,
		.cmx-budget-overview-active #posts-filter > .wp-list-table,
		.cmx-budget-overview-active #posts-filter > .displaying-num{
			display:none !important;
		}
		.cmx-budget-overview-active .cmx-budget-overview{
			display:block;
			clear:both;
			margin:0;
			padding:8px 0 0;
		}
		.cmx-budget-overview-hero,
		.cmx-budget-overview-card,
		.cmx-budget-overview-panel{
			background:#fff;
			border:1px solid #d6e2f0;
			border-radius:14px;
			box-shadow:0 14px 34px rgba(31,49,74,.08);
		}
		.cmx-budget-overview-hero{
			display:flex;
			align-items:flex-start;
			justify-content:space-between;
			gap:20px;
			padding:22px 24px;
			margin:0 0 18px;
		}
		.cmx-budget-overview-hero h2{
			margin:0 0 6px;
			font-size:28px;
			line-height:1.15;
		}
		.cmx-budget-overview-hero p{
			margin:0;
			max-width:760px;
			color:#5b6779;
			font-size:14px;
		}
		.cmx-budget-overview-hero-meta{
			padding:10px 14px;
			border-radius:999px;
			background:#eef4fb;
			color:#0a4b78;
			font-weight:600;
			white-space:nowrap;
		}
		.cmx-budget-overview-cards{
			display:grid;
			grid-template-columns:repeat(3, minmax(0, 1fr));
			gap:18px;
			margin:0 0 18px;
		}
		.cmx-budget-overview-card{
			padding:18px;
		}
		.cmx-budget-overview-card-head,
		.cmx-budget-overview-panel-head{
			display:flex;
			align-items:center;
			justify-content:space-between;
			gap:12px;
			margin:0 0 14px;
		}
		.cmx-budget-overview-card-head h3,
		.cmx-budget-overview-panel-head h3{
			margin:0;
			font-size:16px;
			line-height:1.2;
		}
		.cmx-budget-overview-kpis{
			display:grid;
			grid-template-columns:repeat(3, minmax(0, 1fr));
			gap:10px;
			margin:0 0 16px;
		}
		.cmx-budget-overview-kpis span{
			display:block;
			margin:0 0 4px;
			color:#64748b;
			font-size:12px;
			text-transform:uppercase;
			letter-spacing:.04em;
		}
		.cmx-budget-overview-kpis strong{
			display:block;
			font-size:18px;
			line-height:1.2;
		}
		.cmx-budget-overview-bar-row{
			display:grid;
			grid-template-columns:58px minmax(0,1fr) 82px;
			gap:10px;
			align-items:center;
			margin:0 0 8px;
		}
		.cmx-budget-overview-bar-row:last-child{
			margin-bottom:0;
		}
		.cmx-budget-overview-bar-label,
		.cmx-budget-overview-bar-value{
			font-size:12px;
			color:#5b6779;
			white-space:nowrap;
		}
		.cmx-budget-overview-bar-value{
			text-align:right;
		}
		.cmx-budget-overview-bar-track{
			position:relative;
			display:block;
			width:100%;
			height:10px;
			overflow:hidden;
			background:#edf2f7;
			border-radius:999px;
		}
		.cmx-budget-overview-bar-fill{
			display:block;
			height:100%;
			border-radius:999px;
		}
		.cmx-budget-overview-bar-fill.is-plan{background:#0f62fe}
		.cmx-budget-overview-bar-fill.is-actual{background:#2f855a}
		.cmx-budget-overview-bar-fill.is-forecast{background:#c97a11}
		.cmx-budget-overview-bottom{
			display:grid;
			grid-template-columns:minmax(280px, 340px) minmax(0,1fr);
			gap:18px;
		}
		.cmx-budget-overview-panel{
			padding:18px;
		}
		.cmx-budget-overview-panel-text{
			margin:-4px 0 16px;
			color:#5b6779;
			font-size:14px;
		}
		.cmx-budget-overview-alerts{
			margin:0;
			padding:0;
			list-style:none;
		}
		.cmx-budget-overview-alerts li{
			display:flex;
			align-items:flex-start;
			gap:10px;
			padding:10px 0;
			border-top:1px solid #edf2f7;
		}
		.cmx-budget-overview-alerts li:first-child{
			padding-top:0;
			border-top:0;
		}
		.cmx-budget-overview-alert-dot{
			flex:0 0 10px;
			width:10px;
			height:10px;
			margin-top:5px;
			border-radius:999px;
			background:#16a34a;
		}
		.cmx-budget-overview-alerts li.is-yellow .cmx-budget-overview-alert-dot{background:#f59e0b}
		.cmx-budget-overview-alerts li.is-red .cmx-budget-overview-alert-dot{background:#dc2626}
		.cmx-budget-overview-table{
			border:0;
			box-shadow:none;
		}
		.cmx-budget-overview-table th.num,
		.cmx-budget-overview-table td.num{
			text-align:right;
			white-space:nowrap;
		}
		.cmx-budget-overview-panel-liquid{
			margin-top:18px;
		}
		.cmx-budget-overview-liquid-kpis{
			display:grid;
			grid-template-columns:repeat(3, minmax(0, 1fr));
			gap:14px;
			margin:0 0 16px;
		}
		.cmx-budget-overview-liquid-kpi{
			padding:14px 16px;
			background:#f8fbff;
			border:1px solid #dce8f5;
			border-radius:12px;
		}
		.cmx-budget-overview-liquid-kpi span{
			display:block;
			margin:0 0 6px;
			color:#64748b;
			font-size:12px;
			text-transform:uppercase;
			letter-spacing:.04em;
		}
		.cmx-budget-overview-liquid-kpi strong{
			display:block;
			font-size:22px;
			line-height:1.15;
		}
		.cmx-budget-overview-liquid-kpi small{
			display:block;
			margin-top:8px;
			color:#64748b;
			font-size:12px;
			line-height:1.35;
		}
		.cmx-budget-overview-liquid-row.is-overdue td:first-child{
			color:#b42318;
			font-weight:700;
		}
		.cmx-budget-status{
			display:inline-flex;
			align-items:center;
			gap:6px;
			font-weight:600;
			white-space:nowrap;
		}
		.cmx-budget-status-dot{
			width:10px;
			height:10px;
			border-radius:999px;
			background:#16a34a;
		}
		.cmx-budget-status-yellow .cmx-budget-status-dot{background:#f59e0b}
		.cmx-budget-status-red .cmx-budget-status-dot{background:#dc2626}
		.wp-list-table .column-cmx_budget_ist_betrag,
		.wp-list-table .column-cmx_budget_differenz{width:120px;white-space:nowrap}
		.wp-list-table .column-cmx_budget_status{width:110px;white-space:nowrap}
		@media (max-width: 1280px){
			.cmx-budget-overview-cards{grid-template-columns:1fr}
			.cmx-budget-overview-bottom{grid-template-columns:1fr}
			.cmx-budget-overview-kpis{grid-template-columns:1fr}
			.cmx-budget-overview-liquid-kpis{grid-template-columns:1fr}
		}
	</style>';
});
