<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_slug')) {
	function cmx_start_dashboard_slug(): string {
		return 'cmx-start-dashboard';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_capability')) {
	function cmx_start_dashboard_capability(): string {
		return 'read';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_hidden_for_current_user')) {
	function cmx_start_dashboard_hidden_for_current_user(): bool {
		if (\function_exists(__NAMESPACE__ . '\\cmx_system_is_cloudmeister_user')) {
			return cmx_system_is_cloudmeister_user();
		}

		$user = \wp_get_current_user();
		return ($user instanceof \WP_User) && $user->exists() && \strtolower((string) $user->user_login) === 'cloudmeister';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_url')) {
	function cmx_start_dashboard_url(): string {
		return \admin_url('index.php?page=' . cmx_start_dashboard_slug());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_money')) {
	function cmx_start_dashboard_money(float $value): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')) {
			return 'CHF ' . cmx_format_swiss_number($value, 2);
		}
		return 'CHF ' . \number_format($value, 2, '.', "'");
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_amount')) {
	function cmx_start_dashboard_amount(float $value): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')) {
			return cmx_format_swiss_number($value, 2);
		}
		return \number_format($value, 2, '.', "'");
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_optional_amount')) {
	function cmx_start_dashboard_optional_amount(float $value): string {
		return \abs($value) > 0.0001 ? cmx_start_dashboard_amount($value) : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_preset_options')) {
	function cmx_cockpit_preset_options(): array {
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
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_requested_preset')) {
	function cmx_cockpit_requested_preset(): string {
		$presets = cmx_cockpit_preset_options();
		$preset = \sanitize_key((string) ($_GET['cmx_cockpit_preset'] ?? ''));
		return isset($presets[$preset]) ? $preset : 'dieses_jahr';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_range_from_preset')) {
	function cmx_cockpit_range_from_preset(string $preset): array {
		$tz = \function_exists('wp_timezone') ? \wp_timezone() : new \DateTimeZone('UTC');
		$now = new \DateTimeImmutable('now', $tz);
		$today = $now->format('Y-m-d');

		switch ($preset) {
			case 'heute':
				return ['from' => $today, 'to' => $today];
			case 'diesen_monat':
				return ['from' => $now->modify('first day of this month')->format('Y-m-d'), 'to' => $now->modify('last day of this month')->format('Y-m-d')];
			case 'letzten_monat':
				return ['from' => $now->modify('first day of last month')->format('Y-m-d'), 'to' => $now->modify('last day of last month')->format('Y-m-d')];
			case 'vorletzten_monat':
				return ['from' => $now->modify('first day of -2 months')->format('Y-m-d'), 'to' => $now->modify('last day of -2 months')->format('Y-m-d')];
			case 'dieses_quartal':
			case 'letztes_quartal':
			case 'vorletztes_quartal':
				$offset = $preset === 'letztes_quartal' ? -3 : ($preset === 'vorletztes_quartal' ? -6 : 0);
				$base = $now->modify($offset . ' months');
				$year = (int) $base->format('Y');
				$month = (int) $base->format('n');
				$q_start_month = ((int) \floor(($month - 1) / 3) * 3) + 1;
				$q_start = $base->setDate($year, $q_start_month, 1);
				return ['from' => $q_start->format('Y-m-d'), 'to' => $q_start->modify('+2 months')->modify('last day of this month')->format('Y-m-d')];
			case 'letztes_jahr':
				$year = ((int) $now->format('Y')) - 1;
				return ['from' => \sprintf('%04d-01-01', $year), 'to' => \sprintf('%04d-12-31', $year)];
			case 'vorletztes_jahr':
				$year = ((int) $now->format('Y')) - 2;
				return ['from' => \sprintf('%04d-01-01', $year), 'to' => \sprintf('%04d-12-31', $year)];
			case 'dieses_jahr':
			default:
				$year = (int) $now->format('Y');
				return ['from' => \sprintf('%04d-01-01', $year), 'to' => \sprintf('%04d-12-31', $year)];
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_preset_options')) {
	function cmx_start_dashboard_preset_options(): array {
		$presets = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_preset_options') ? (array) cmx_cockpit_preset_options() : [];
		$presets['individuell'] = 'Individuell';
		return $presets;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_requested_preset')) {
	function cmx_start_dashboard_requested_preset(): string {
		$presets = cmx_start_dashboard_preset_options();
		$preset = \sanitize_key((string) ($_GET['cmx_cockpit_preset'] ?? ''));
		if ($preset === '' && (isset($_GET['cmx_start_from']) || isset($_GET['cmx_start_to']))) {
			return 'individuell';
		}
		return isset($presets[$preset]) ? $preset : 'dieses_jahr';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_parse_amount')) {
	function cmx_start_dashboard_parse_amount(string $value): float {
		if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_parse_decimal')) {
			return (float) cmx_cockpit_parse_decimal($value);
		}
		$value = \str_replace(["\xc2\xa0", ' ', 'CHF'], '', $value);
		$value = \preg_replace('/[^0-9,.\-]/', '', $value);
		if (!\is_string($value) || $value === '') {
			return 0.0;
		}
		if (\strpos($value, ',') !== false && \strpos($value, '.') !== false) {
			$value = \str_replace(',', '.', \str_replace('.', '', $value));
		} elseif (\strpos($value, ',') !== false) {
			$value = \str_replace(',', '.', $value);
		} else {
			$value = \str_replace("'", '', $value);
		}
		return \is_numeric($value) ? (float) $value : 0.0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_sum_open_items')) {
	function cmx_start_dashboard_sum_open_items(array $items): float {
		$total = 0.0;
		foreach ($items as $item) {
			if (!\is_array($item)) {
				continue;
			}
			$total += cmx_start_dashboard_parse_amount((string) ($item['amount_display'] ?? ''));
		}
		return $total;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_project_revenue')) {
	function cmx_start_dashboard_project_revenue(int $post_id): float {
		if ($post_id <= 0) {
			return 0.0;
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_proj_calc_umsatz_total')) {
			return (float) cmx_proj_calc_umsatz_total($post_id);
		}

		$meta_key = \defined('CMX_PROJ_UMSATZ_META') ? (string) \constant('CMX_PROJ_UMSATZ_META') : '_cmx_projekt_umsatz_total';
		return cmx_start_dashboard_parse_amount((string) \get_post_meta($post_id, $meta_key, true));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_post_amount')) {
	function cmx_start_dashboard_post_amount(int $post_id): float {
		if ($post_id <= 0) {
			return 0.0;
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_amount_display')) {
			return cmx_start_dashboard_parse_amount((string) cmx_cockpit_beleg_amount_display($post_id));
		}
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
			$calc = (array) cmxbu_get_beleg_positionen_calc($post_id);
			if (isset($calc['total']) && \is_numeric($calc['total'])) {
				return (float) $calc['total'];
			}
		}
		return cmx_start_dashboard_parse_amount((string) \get_post_meta($post_id, '_cmx_beleg_summe_override', true));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_open_offers')) {
	function cmx_start_dashboard_open_offers(array $range): array {
		if (!\post_type_exists('belege')) {
			return ['count' => 0, 'amount' => 0.0];
		}
		$args = [
			'post_type' => 'belege',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'meta_query' => [
				'relation' => 'OR',
				[
					'key' => '_cmx_beleg_offertenstatus',
					'compare' => 'NOT EXISTS',
				],
				[
					'key' => '_cmx_beleg_offertenstatus',
					'value' => ['', 'offen'],
					'compare' => 'IN',
				],
			],
		];
		if ((string) ($range['from'] ?? '') !== '' && (string) ($range['to'] ?? '') !== '') {
			$args['date_query'] = [[
				'after' => (string) $range['from'],
				'before' => (string) $range['to'],
				'inclusive' => true,
			]];
		}
		$tax = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_taxonomy') ? (string) cmx_cockpit_beleg_taxonomy() : '';
		if ($tax !== '' && \taxonomy_exists($tax)) {
			$args['tax_query'] = [[
				'taxonomy' => $tax,
				'field' => 'slug',
				'terms' => ['offerte', 'offerten'],
				'operator' => 'IN',
			]];
		} else {
			$args['s'] = 'Offerte';
		}

		$query = new \WP_Query($args);
		$amount = 0.0;
		foreach ((array) $query->posts as $post_id) {
			$amount += cmx_start_dashboard_post_amount((int) $post_id);
		}
		return ['count' => \count((array) $query->posts), 'amount' => $amount];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_icon_svg')) {
	function cmx_start_dashboard_icon_svg(string $name = 'layout-dashboard', string $class = 'cmx-start-dashboard-icon', string $fallback = 'dashicons-dashboard'): string {
		$name = \sanitize_file_name($name);
		$svg = ($name !== '' && \function_exists(__NAMESPACE__ . '\\cmx_icon')) ? cmx_icon($name) : '';
		if (!\is_string($svg) || \trim($svg) === '') {
			$path = \dirname(__DIR__, 2) . '/assets/icons/' . $name . '.svg';
			$svg = \is_readable($path) ? \file_get_contents($path) : '';
		}
		if (!\is_string($svg) || \trim($svg) === '') {
			return '<span class="dashicons ' . \esc_attr($fallback) . '" aria-hidden="true"></span>';
		}

		$svg = \preg_replace(
			'/<svg\b/',
			'<svg class="' . \esc_attr($class) . '" aria-hidden="true" focusable="false"',
			$svg,
			1
		);

		return \is_string($svg) ? $svg : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_menu_icon')) {
	function cmx_start_dashboard_menu_icon(): string {
		return 'dashicons-dashboard';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_range')) {
	function cmx_start_dashboard_range(): array {
		$preset = cmx_start_dashboard_requested_preset();
		$range = $preset !== 'individuell' && \function_exists(__NAMESPACE__ . '\\cmx_cockpit_range_from_preset')
			? (array) cmx_cockpit_range_from_preset($preset)
			: ['from' => \date('Y-01-01'), 'to' => \date('Y-12-31')];

		$from = $preset === 'individuell' && isset($_GET['cmx_start_from']) ? \trim((string) \wp_unslash($_GET['cmx_start_from'])) : (string) ($range['from'] ?? '');
		$to = $preset === 'individuell' && isset($_GET['cmx_start_to']) ? \trim((string) \wp_unslash($_GET['cmx_start_to'])) : (string) ($range['to'] ?? '');
		if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
			$from = (string) ($range['from'] ?? '');
		}
		if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
			$to = (string) ($range['to'] ?? '');
		}
		if ($from !== '' && $to !== '' && $from > $to) {
			[$from, $to] = [$to, $from];
		}

		return ['preset' => $preset, 'from' => $from, 'to' => $to];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_count_posts')) {
	function cmx_start_dashboard_count_posts(string $post_type, array $args = []): int {
		if (!\post_type_exists($post_type)) {
			return 0;
		}
		$query = new \WP_Query(\array_merge([
			'post_type' => $post_type,
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => 1,
			'fields' => 'ids',
			'no_found_rows' => false,
		], $args));
		return (int) $query->found_posts;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_new_customer_ids')) {
	function cmx_start_dashboard_customer_category_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_kundenkategorie_tax')) {
			$tax = (string) cmx_kundenkategorie_tax();
			if ($tax !== '' && \taxonomy_exists($tax)) {
				return $tax;
			}
		}
		foreach (['kontakte_kategorien', 'kontakte_kategorie', 'kundenkategorie', 'kontakt_kategorie'] as $tax) {
			if (\taxonomy_exists($tax)) {
				return $tax;
			}
		}
		return '';
	}

	function cmx_start_dashboard_customer_category_slugs(string $taxonomy): array {
		if ($taxonomy === '' || !\taxonomy_exists($taxonomy)) {
			return [];
		}
		$slugs = [];
		$terms = \get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
		if (\is_wp_error($terms) || !\is_array($terms)) {
			return [];
		}
		foreach ($terms as $term) {
			if (!$term instanceof \WP_Term) {
				continue;
			}
			$slug = \sanitize_title((string) $term->slug);
			$name = \sanitize_title((string) $term->name);
			if ($slug === 'kunde' || $slug === 'kunden' || $name === 'kunde' || $name === 'kunden') {
				$slugs[] = (string) $term->slug;
			}
		}
		return \array_values(\array_unique($slugs));
	}

	function cmx_start_dashboard_customer_category_term_ids(string $taxonomy): array {
		if ($taxonomy === '' || !\taxonomy_exists($taxonomy)) {
			return [];
		}
		$ids = [];
		$terms = \get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
		if (\is_wp_error($terms) || !\is_array($terms)) {
			return [];
		}
		foreach ($terms as $term) {
			if (!$term instanceof \WP_Term) {
				continue;
			}
			$slug = \sanitize_title((string) $term->slug);
			$name = \sanitize_title((string) $term->name);
			if ($slug === 'kunde' || $slug === 'kunden' || $name === 'kunde' || $name === 'kunden') {
				$ids[] = (int) $term->term_id;
			}
		}
		return \array_values(\array_unique(\array_filter($ids)));
	}

	function cmx_start_dashboard_supplier_category_slugs(string $taxonomy): array {
		if ($taxonomy === '' || !\taxonomy_exists($taxonomy)) {
			return [];
		}
		$slugs = [];
		$terms = \get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
		if (\is_wp_error($terms) || !\is_array($terms)) {
			return [];
		}
		foreach ($terms as $term) {
			if (!$term instanceof \WP_Term) {
				continue;
			}
			$slug = \sanitize_title((string) $term->slug);
			$name = \sanitize_title((string) $term->name);
			if ($slug === 'lieferant' || $slug === 'lieferanten' || $name === 'lieferant' || $name === 'lieferanten') {
				$slugs[] = (string) $term->slug;
			}
		}
		return \array_values(\array_unique($slugs));
	}

	function cmx_start_dashboard_contact_has_supplier_category(int $post_id, string $taxonomy, array $supplier_slugs): bool {
		if ($taxonomy === '' || $supplier_slugs === []) {
			return false;
		}
		$terms = \wp_get_post_terms($post_id, $taxonomy, ['fields' => 'slugs']);
		if (\is_wp_error($terms) || !\is_array($terms)) {
			return false;
		}
		return \array_intersect(\array_map('sanitize_title', $terms), \array_map('sanitize_title', $supplier_slugs)) !== [];
	}

	function cmx_start_dashboard_customer_created_date(int $post_id): string {
		$post_date = (string) \get_post_field('post_date', $post_id);
		return \preg_match('/^\d{4}-\d{2}-\d{2}/', $post_date) ? \substr($post_date, 0, 10) : '';
	}

	function cmx_start_dashboard_new_customer_date(int $post_id): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_kontakt_kunde_seit_value')) {
			$customer_since = cmx_start_dashboard_normalize_date((string) cmx_kontakt_kunde_seit_value($post_id));
			if ($customer_since !== '') {
				return $customer_since;
			}
		}

		$meta_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_KUNDE_SEIT') ? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_KUNDE_SEIT') : '_cmx_kontakte_kunde_seit';
		$customer_since = cmx_start_dashboard_normalize_date((string) \get_post_meta($post_id, $meta_key, true));
		if ($customer_since !== '') {
			return $customer_since;
		}

		return cmx_start_dashboard_customer_created_date($post_id);
	}

	function cmx_start_dashboard_contact_has_customer_category(int $post_id, string $taxonomy, array $customer_slugs): bool {
		if ($taxonomy === '' || $customer_slugs === []) {
			return false;
		}
		$terms = \wp_get_post_terms($post_id, $taxonomy, ['fields' => 'slugs']);
		if (\is_wp_error($terms) || !\is_array($terms)) {
			return false;
		}
		return \array_intersect(\array_map('sanitize_title', $terms), \array_map('sanitize_title', $customer_slugs)) !== [];
	}

	function cmx_start_dashboard_contact_has_customer_revenue_beleg(int $contact_id): bool {
		if ($contact_id <= 0 || !\post_type_exists('belege')) {
			return false;
		}
		$kontakt_keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID')) {
			$kontakt_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID');
		}
		$kontakt_keys = \array_values(\array_unique(\array_filter(\array_merge($kontakt_keys, ['_cmx_beleg_kontakt_id', 'cmx_beleg_kontakt_id']))));
		$meta_or = ['relation' => 'OR'];
		foreach ($kontakt_keys as $key) {
			$meta_or[] = [
				'key' => $key,
				'value' => $contact_id,
				'compare' => '=',
				'type' => 'NUMERIC',
			];
		}
		$query = new \WP_Query([
			'post_type' => 'belege',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => 20,
			'fields' => 'ids',
			'no_found_rows' => true,
			'meta_query' => [$meta_or],
		]);
		foreach ((array) $query->posts as $post_id) {
			if (cmx_start_dashboard_is_customer_revenue_beleg((int) $post_id)) {
				return true;
			}
		}
		return false;
	}

	function cmx_start_dashboard_beleg_contact_id(int $post_id): int {
		if ($post_id <= 0) {
			return 0;
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_contact_id')) {
			return (int) cmx_kontakt_beleg_contact_id($post_id);
		}
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_kontakt_id')) {
			$contact_id = (int) cmxbu_get_beleg_kontakt_id($post_id);
			if ($contact_id > 0) {
				return $contact_id;
			}
		}

		$kontakt_keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID')) {
			$kontakt_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID');
		}
		$kontakt_keys = \array_values(\array_unique(\array_filter(\array_merge($kontakt_keys, ['_cmx_beleg_kontakt_id', 'cmx_beleg_kontakt_id']))));
		foreach ($kontakt_keys as $meta_key) {
			$contact_id = (int) \get_post_meta($post_id, $meta_key, true);
			if ($contact_id > 0 && (string) \get_post_type($contact_id) === 'kontakte') {
				return $contact_id;
			}
		}

		return 0;
	}

	function cmx_start_dashboard_new_customer_ids(array $range): array {
		if (!\post_type_exists('kontakte')) {
			return [];
		}
		$taxonomy = cmx_start_dashboard_customer_category_taxonomy();
		$customer_slugs = cmx_start_dashboard_customer_category_slugs($taxonomy);
		$supplier_slugs = cmx_start_dashboard_supplier_category_slugs($taxonomy);
		if ($taxonomy === '' || $customer_slugs === []) {
			return [];
		}
		$tax_query = [
			[
				'taxonomy' => $taxonomy,
				'field' => 'slug',
				'terms' => $customer_slugs,
				'operator' => 'IN',
			],
		];
		if ($supplier_slugs !== []) {
			$tax_query['relation'] = 'AND';
			$tax_query[] = [
				'taxonomy' => $taxonomy,
				'field' => 'slug',
				'terms' => $supplier_slugs,
				'operator' => 'NOT IN',
			];
		}
		$query = new \WP_Query([
			'post_type' => 'kontakte',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'tax_query' => $tax_query,
		]);
		$ids = [];
		foreach ((array) $query->posts as $contact_id) {
			$contact_id = (int) $contact_id;
			if (
				$contact_id <= 0
				|| !cmx_start_dashboard_contact_has_customer_category($contact_id, $taxonomy, $customer_slugs)
				|| cmx_start_dashboard_contact_has_supplier_category($contact_id, $taxonomy, $supplier_slugs)
			) {
				continue;
			}
			$customer_date = cmx_start_dashboard_new_customer_date($contact_id);
			if ($customer_date === '' || !cmx_start_dashboard_date_in_range($customer_date, $range)) {
				continue;
			}
			$ids[] = $contact_id;
		}
		return \array_values(\array_unique($ids));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_new_customers_url')) {
	function cmx_start_dashboard_new_customers_url(): string {
		$args = ['post_type' => 'kontakte'];
		$taxonomy = cmx_start_dashboard_customer_category_taxonomy();
		$term_ids = cmx_start_dashboard_customer_category_term_ids($taxonomy);
		if ($term_ids !== []) {
			$args['filter_kundenkategorie'] = (string) $term_ids[0];
		}
		return (string) \add_query_arg($args, \admin_url('edit.php'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_customer_revenue')) {
	function cmx_start_dashboard_customer_revenue(array $customer_ids, array $range): float {
		$customer_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $customer_ids))));
		if ($customer_ids === [] || !\post_type_exists('belege')) {
			return 0.0;
		}

		$kontakt_keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID')) {
			$kontakt_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID');
		}
		$kontakt_keys = \array_values(\array_unique(\array_filter(\array_merge($kontakt_keys, ['_cmx_beleg_kontakt_id', 'cmx_beleg_kontakt_id']))));

		$meta_or = ['relation' => 'OR'];
		foreach ($kontakt_keys as $key) {
			$meta_or[] = [
				'key' => $key,
				'value' => $customer_ids,
				'compare' => 'IN',
				'type' => 'NUMERIC',
			];
		}

		$args = [
			'post_type' => 'belege',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'meta_query' => [$meta_or],
		];

		$query = new \WP_Query($args);
		$sum = 0.0;
		foreach ((array) $query->posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0 || !cmx_start_dashboard_is_customer_invoice_beleg($post_id)) {
				continue;
			}
			$paid_date = cmx_start_dashboard_beleg_paid_date($post_id);
			if ($paid_date === '' || !cmx_start_dashboard_date_in_range($paid_date, $range)) {
				continue;
			}
			$sum += cmx_start_dashboard_post_amount($post_id);
		}

		return $sum;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_recent_posts')) {
	function cmx_start_dashboard_recent_posts(string $post_type, int $limit = 5): array {
		if (!\post_type_exists($post_type)) {
			return [];
		}
		$args = [
			'post_type' => $post_type,
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => $limit,
			'orderby' => 'date',
			'order' => 'DESC',
		];
		if ($post_type === 'dokumente' && \function_exists(__NAMESPACE__ . '\\cmx_dokumente_admin_visible_meta_query')) {
			$args['meta_query'] = (array) cmx_dokumente_admin_visible_meta_query();
		}
		return \get_posts($args);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_active_project_status_slugs')) {
	function cmx_start_dashboard_active_project_status_slugs(): array {
		$tax = '';
		if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_projekte_status_taxonomy')) {
			$tax = (string) cmx_cockpit_projekte_status_taxonomy();
		}
		if ($tax === '' && \function_exists(__NAMESPACE__ . '\\cmx_projekte_detect_status_taxonomy')) {
			$tax = (string) cmx_projekte_detect_status_taxonomy();
		}
		if ($tax === '' || !\taxonomy_exists($tax) || !\is_object_in_taxonomy('projekte', $tax)) {
			return [];
		}
		$terms = \get_terms(['taxonomy' => $tax, 'hide_empty' => false]);
		if (\is_wp_error($terms) || !\is_array($terms)) {
			return [];
		}
		$active = [];
		foreach ($terms as $term) {
			if (!$term instanceof \WP_Term) {
				continue;
			}
			$slug = \sanitize_title((string) $term->slug);
			$name = \sanitize_title((string) $term->name);
			if (\in_array($slug, ['freigegeben', 'aktiv', 'active'], true) || \in_array($name, ['freigegeben', 'aktiv', 'active'], true)) {
				$active[] = (string) $term->slug;
			}
		}
		return \array_values(\array_unique(\array_filter($active)));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_date_overlaps_range')) {
	function cmx_start_dashboard_date_overlaps_range(string $start, string $end, array $range): bool {
		$range_start = (string) ($range['from'] ?? '');
		$range_end = (string) ($range['to'] ?? '');
		$valid = static fn(string $date): bool => \preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
		if (!$valid($range_start) || !$valid($range_end)) {
			return true;
		}
		$start = $valid($start) ? $start : '0001-01-01';
		$end = $valid($end) ? $end : '9999-12-31';
		return $start <= $range_end && $end >= $range_start;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_active_projects')) {
	function cmx_start_dashboard_active_projects(array $range, int $limit = 5): array {
		if (!\post_type_exists('projekte')) {
			return [];
		}
		$args = [
			'post_type' => 'projekte',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => -1,
			'orderby' => 'modified',
			'order' => 'DESC',
			'no_found_rows' => true,
		];
		$tax = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_projekte_status_taxonomy') ? (string) cmx_cockpit_projekte_status_taxonomy() : '';
		$active_slugs = cmx_start_dashboard_active_project_status_slugs();
		if ($tax !== '' && \taxonomy_exists($tax) && $active_slugs !== []) {
			$args['tax_query'] = [[
				'taxonomy' => $tax,
				'field' => 'slug',
				'terms' => $active_slugs,
				'operator' => 'IN',
			]];
		} elseif ($tax !== '' && \taxonomy_exists($tax)) {
			return [];
		}

		$begin_key = \defined(__NAMESPACE__ . '\\CMX_PROJ_BEG_META') ? (string) CMX_PROJ_BEG_META : '_cmx_projekt_beginn';
		$end_key = \defined(__NAMESPACE__ . '\\CMX_PROJ_END_META') ? (string) CMX_PROJ_END_META : '_cmx_projekt_ende';
		$projects = [];
		foreach (\get_posts($args) as $post) {
			if (!$post instanceof \WP_Post) {
				continue;
			}
			$start = \trim((string) \get_post_meta((int) $post->ID, $begin_key, true));
			$end = \trim((string) \get_post_meta((int) $post->ID, $end_key, true));
			if (!cmx_start_dashboard_date_overlaps_range($start, $end, $range)) {
				continue;
			}
			$projects[] = $post;
			if (\count($projects) >= $limit) {
				break;
			}
		}
		return $projects;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_contact_reminders')) {
	function cmx_start_dashboard_contact_reminders(int $limit = 5): array {
		if (!\post_type_exists('kontakte')) {
			return [];
		}

		$today = new \DateTimeImmutable(\current_time('Y-m-d'));
		$current_year = (int) $today->format('Y');
		$query = new \WP_Query([
			'post_type' => 'kontakte',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
		]);
		$entries = [];
		foreach ((array) $query->posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0) {
				continue;
			}
			foreach ([
				'Geburtstag' => (string) \get_post_meta($post_id, '_cmx_kontakte_geburtsdatum', true),
				'Firmenjubiläum' => (string) \get_post_meta($post_id, '_cmx_kontakte_firmengruendung', true),
			] as $type => $raw_date) {
				if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_date)) {
					continue;
				}
				$month_day = \substr($raw_date, 5);
				$event = \DateTimeImmutable::createFromFormat('!Y-m-d', \sprintf('%04d-%s', $current_year, $month_day));
				if (!$event instanceof \DateTimeImmutable) {
					continue;
				}
				if ($event < $today) {
					$event = $event->modify('+1 year');
				}
				$entries[] = [
					'id' => $post_id,
					'type' => $type,
					'date' => $event->format('Y-m-d'),
					'ts' => $event->getTimestamp(),
				];
			}
		}
		\usort($entries, static fn(array $a, array $b): int => ((int) ($a['ts'] ?? 0)) <=> ((int) ($b['ts'] ?? 0)));
		return \array_slice($entries, 0, \max(1, $limit));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_beleg_terms')) {
	function cmx_start_dashboard_beleg_terms(int $post_id): array {
		$terms = [];
		foreach (\get_object_taxonomies('belege', 'names') as $taxonomy) {
			$post_terms = \wp_get_post_terms($post_id, $taxonomy, ['fields' => 'slugs']);
			if (!\is_wp_error($post_terms)) {
				$terms = \array_merge($terms, (array) $post_terms);
			}
		}
		return \array_values(\array_unique(\array_map('sanitize_key', $terms)));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_beleg_direction')) {
	function cmx_start_dashboard_beleg_direction(int $post_id): string {
		$terms = cmx_start_dashboard_beleg_terms($post_id);
		if (\array_intersect($terms, ['lieferantenrechnung', 'ausgabe', 'ausgaben', 'expense', 'expenses'])) {
			return 'expense';
		}
		if (\array_intersect($terms, ['rechnung', 'rechnungen', 'quittung', 'quittungen', 'gutschrift', 'gutschriften', 'einnahme', 'einnahmen', 'invoice', 'receipt', 'credit'])) {
			return 'income';
		}

		$raw = \strtolower((string) \get_post_meta($post_id, '_cmx_beleg_typ', true));
		if (\str_contains($raw, 'liefer') || \str_contains($raw, 'ausgabe')) {
			return 'expense';
		}

		return 'income';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_is_customer_revenue_beleg')) {
	function cmx_start_dashboard_is_customer_revenue_beleg(int $post_id): bool {
		$terms = cmx_start_dashboard_beleg_terms($post_id);
		$direction = \sanitize_key((string) \get_post_meta($post_id, '_cmx_beleg_richtung', true));
		$type = \sanitize_key((string) \get_post_meta($post_id, '_cmx_beleg_typ', true));
		if (\array_intersect($terms, ['lieferantenrechnung', 'ausgabe', 'ausgaben', 'expense', 'expenses', 'offerte', 'offerten', 'angebot', 'angebote'])) {
			return false;
		}
		if (\array_intersect($terms, ['rechnung', 'rechnungen', 'quittung', 'quittungen', 'invoice', 'receipt'])) {
			return $direction !== 'eingang';
		}

		return $direction === 'ausgang' && ($type === '' || \str_contains($type, 'rechnung') || \str_contains($type, 'quittung'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_is_customer_invoice_beleg')) {
	function cmx_start_dashboard_is_customer_invoice_beleg(int $post_id): bool {
		$direction = \sanitize_key((string) \get_post_meta($post_id, '_cmx_beleg_richtung', true));
		if ($direction !== 'ausgang') {
			return false;
		}

		$terms = cmx_start_dashboard_beleg_terms($post_id);
		if (\array_intersect($terms, ['lieferantenrechnung', 'lieferantenrechnungen', 'lieferantenquittung', 'lieferantenquittungen', 'offerte', 'offerten', 'angebot', 'angebote', 'gutschrift', 'gutschriften'])) {
			return false;
		}
		if (\array_intersect($terms, ['rechnung', 'rechnungen', 'quittung', 'quittungen'])) {
			return true;
		}

		$type = \sanitize_key((string) \get_post_meta($post_id, '_cmx_beleg_typ', true));
		return $type === '' || $type === 'rechnung' || $type === 'rechnungen' || $type === 'quittung' || $type === 'quittungen';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_is_customer_open_invoice_beleg')) {
	function cmx_start_dashboard_is_customer_open_invoice_beleg(int $post_id): bool {
		$direction = \sanitize_key((string) \get_post_meta($post_id, '_cmx_beleg_richtung', true));
		if ($direction !== 'ausgang') {
			return false;
		}

		$terms = cmx_start_dashboard_beleg_terms($post_id);
		if (\array_intersect($terms, ['lieferantenrechnung', 'lieferantenrechnungen', 'lieferantenquittung', 'lieferantenquittungen', 'quittung', 'quittungen', 'offerte', 'offerten', 'angebot', 'angebote', 'gutschrift', 'gutschriften'])) {
			return false;
		}
		if (\array_intersect($terms, ['rechnung', 'rechnungen'])) {
			return true;
		}

		$type = \sanitize_key((string) \get_post_meta($post_id, '_cmx_beleg_typ', true));
		return $type === 'rechnung' || $type === 'rechnungen';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_is_supplier_expense_beleg')) {
	function cmx_start_dashboard_is_supplier_expense_beleg(int $post_id): bool {
		$terms = cmx_start_dashboard_beleg_terms($post_id);
		$direction = \sanitize_key((string) \get_post_meta($post_id, '_cmx_beleg_richtung', true));
		$type = \sanitize_key((string) \get_post_meta($post_id, '_cmx_beleg_typ', true));
		if (\array_intersect($terms, ['offerte', 'offerten', 'angebot', 'angebote', 'gutschrift', 'gutschriften'])) {
			return false;
		}
		if (\array_intersect($terms, ['lieferantenrechnung', 'lieferantenrechnungen', 'lieferantenquittung', 'lieferantenquittungen'])) {
			return true;
		}
		if (\array_intersect($terms, ['rechnung', 'rechnungen', 'quittung', 'quittungen', 'invoice', 'receipt'])) {
			return $direction === 'eingang';
		}
		if ($direction === 'eingang' && ($type === '' || \str_contains($type, 'rechnung') || \str_contains($type, 'quittung'))) {
			return true;
		}

		return false;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_normalize_date')) {
	function cmx_start_dashboard_normalize_date(string $date): string {
		$date = \trim($date);
		if ($date === '') {
			return '';
		}
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_normalize_payment_date')) {
			$normalized = (string) cmxbu_belege_export_normalize_payment_date($date);
			if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
				return $normalized;
			}
		}
		if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			[$year, $month, $day] = \array_map('intval', \explode('-', $date));
			return \checkdate($month, $day, $year) ? \sprintf('%04d-%02d-%02d', $year, $month, $day) : '';
		}
		$timestamp = \strtotime($date);
		return $timestamp ? \date('Y-m-d', $timestamp) : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_beleg_invoice_date')) {
	function cmx_start_dashboard_beleg_invoice_date(int $post_id): string {
		foreach (['_cmx_beleg_rng_datum', '_cmx_rechnungsdatum', '_invoice_date', '_date', 'beleg_datum'] as $meta_key) {
			$date = cmx_start_dashboard_normalize_date((string) \get_post_meta($post_id, $meta_key, true));
			if ($date !== '') {
				return $date;
			}
		}

		return cmx_start_dashboard_normalize_date((string) \get_the_date('Y-m-d', $post_id));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_beleg_paid_date')) {
	function cmx_start_dashboard_beleg_paid_date(int $post_id): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_paid_date')) {
			return cmx_start_dashboard_normalize_date((string) cmx_cockpit_paid_date($post_id));
		}
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_paid_date')) {
			return cmx_start_dashboard_normalize_date((string) cmxbu_belege_export_paid_date($post_id));
		}

		return cmx_start_dashboard_normalize_date((string) \get_post_meta($post_id, '_cmx_beleg_bezahlt_am', true));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_beleg_leistungsmonat')) {
	function cmx_start_dashboard_beleg_leistungsmonat(int $post_id): string {
		$meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_LEISTUNGSMONAT') ? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_LEISTUNGSMONAT') : '_cmx_beleg_leistungsmonat';
		$month = \trim((string) \get_post_meta($post_id, $meta_key, true));
		return \preg_match('/^(0[1-9]|1[0-2])$/', $month) ? $month : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_beleg_revenue_expense_chart_date')) {
	function cmx_start_dashboard_beleg_revenue_expense_chart_date(int $post_id): string {
		$invoice_date = cmx_start_dashboard_beleg_invoice_date($post_id);
		$month = cmx_start_dashboard_beleg_leistungsmonat($post_id);
		if ($invoice_date !== '' && $month !== '') {
			return \substr($invoice_date, 0, 4) . '-' . $month . '-01';
		}

		return $invoice_date;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_belege_ids_in_range')) {
	function cmx_start_dashboard_belege_ids_in_range(array $range): array {
		if (!\post_type_exists('belege')) {
			return [];
		}
		$args = [
			'post_type' => 'belege',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
		];
		if ((string) ($range['from'] ?? '') !== '' && (string) ($range['to'] ?? '') !== '') {
			$args['date_query'] = [[
				'after' => (string) $range['from'],
				'before' => (string) $range['to'],
				'inclusive' => true,
			]];
		}

		$query = new \WP_Query($args);
		return \array_values(\array_map('intval', (array) $query->posts));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_beleg_taxonomy')) {
	function cmx_start_dashboard_beleg_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_taxonomy')) {
			$tax = (string) cmx_cockpit_beleg_taxonomy();
			if ($tax !== '' && \taxonomy_exists($tax)) {
				return $tax;
			}
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_belege_taxonomy')) {
			$tax = (string) cmx_belege_taxonomy();
			if ($tax !== '' && \taxonomy_exists($tax)) {
				return $tax;
			}
		}
		foreach (['belege_kategorien', 'belege_kategorie', 'beleg_kategorien', 'beleg_kategorie'] as $tax) {
			if (\taxonomy_exists($tax)) {
				return $tax;
			}
		}
		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_invoice_term_slugs')) {
	function cmx_start_dashboard_invoice_term_slugs(): array {
		$tax = cmx_start_dashboard_beleg_taxonomy();
		if ($tax === '') {
			return ['rechnung', 'rechnungen'];
		}

		$slugs = [];
		$terms = \get_terms([
			'taxonomy' => $tax,
			'hide_empty' => false,
		]);
		if (!\is_wp_error($terms) && \is_array($terms)) {
			foreach ($terms as $term) {
				if (!($term instanceof \WP_Term)) {
					continue;
				}
				$slug = \sanitize_title((string) $term->slug);
				$name = \trim((string) $term->name);
				$name_lc = \function_exists('mb_strtolower') ? \mb_strtolower($name, 'UTF-8') : \strtolower($name);
				if ($slug === 'rechnung' || $slug === 'rechnungen' || $name_lc === 'rechnung' || $name_lc === 'rechnungen') {
					$slugs[] = $slug;
				}
			}
		}

		return !empty($slugs) ? \array_values(\array_unique($slugs)) : ['rechnung', 'rechnungen'];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_receipt_term_slugs')) {
	function cmx_start_dashboard_receipt_term_slugs(): array {
		$tax = cmx_start_dashboard_beleg_taxonomy();
		if ($tax === '') {
			return ['quittung', 'quittungen'];
		}

		$slugs = [];
		$terms = \get_terms([
			'taxonomy' => $tax,
			'hide_empty' => false,
		]);
		if (!\is_wp_error($terms) && \is_array($terms)) {
			foreach ($terms as $term) {
				if (!($term instanceof \WP_Term)) {
					continue;
				}
				$slug = \sanitize_title((string) $term->slug);
				$name = \trim((string) $term->name);
				$name_lc = \function_exists('mb_strtolower') ? \mb_strtolower($name, 'UTF-8') : \strtolower($name);
				if ($slug === 'quittung' || $slug === 'quittungen' || $name_lc === 'quittung' || $name_lc === 'quittungen') {
					$slugs[] = $slug;
				}
			}
		}

		return !empty($slugs) ? \array_values(\array_unique($slugs)) : ['quittung', 'quittungen'];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_supplier_invoice_term_slugs')) {
	function cmx_start_dashboard_supplier_invoice_term_slugs(): array {
		$tax = cmx_start_dashboard_beleg_taxonomy();
		if ($tax === '') {
			return ['lieferantenrechnung', 'lieferantenrechnungen'];
		}

		$slugs = [];
		$terms = \get_terms([
			'taxonomy' => $tax,
			'hide_empty' => false,
		]);
		if (!\is_wp_error($terms) && \is_array($terms)) {
			foreach ($terms as $term) {
				if (!($term instanceof \WP_Term)) {
					continue;
				}
				$slug = \sanitize_title((string) $term->slug);
				$name = \trim((string) $term->name);
				$name_lc = \function_exists('mb_strtolower') ? \mb_strtolower($name, 'UTF-8') : \strtolower($name);
				if (
					$slug === 'lieferantenrechnung'
					|| $slug === 'lieferantenrechnungen'
					|| $name_lc === 'lieferantenrechnung'
					|| $name_lc === 'lieferantenrechnungen'
				) {
					$slugs[] = $slug;
				}
			}
		}

		return !empty($slugs) ? \array_values(\array_unique($slugs)) : ['lieferantenrechnung', 'lieferantenrechnungen'];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_customer_revenue_term_slugs')) {
	function cmx_start_dashboard_customer_revenue_term_slugs(): array {
		return \array_values(\array_unique(\array_merge(cmx_start_dashboard_invoice_term_slugs(), cmx_start_dashboard_receipt_term_slugs())));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_supplier_invoice_query_term_slugs')) {
	function cmx_start_dashboard_supplier_invoice_query_term_slugs(): array {
		return \array_values(\array_unique(\array_merge(cmx_start_dashboard_supplier_invoice_term_slugs(), cmx_start_dashboard_invoice_term_slugs())));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_is_supplier_invoice_beleg')) {
	function cmx_start_dashboard_is_supplier_invoice_beleg(int $post_id): bool {
		$direction = \sanitize_key((string) \get_post_meta($post_id, '_cmx_beleg_richtung', true));
		if ($direction !== 'eingang') {
			return false;
		}

		$terms = cmx_start_dashboard_beleg_terms($post_id);
		if (\array_intersect($terms, ['lieferantenrechnung', 'lieferantenrechnungen'])) {
			return true;
		}
		if (\array_intersect($terms, ['rechnung', 'rechnungen', 'quittung', 'quittungen', 'lieferantenquittung', 'lieferantenquittungen', 'offerte', 'offerten', 'angebot', 'angebote', 'gutschrift', 'gutschriften'])) {
			$post = \get_post($post_id);
			if ($post instanceof \WP_Post && \function_exists(__NAMESPACE__ . '\\cmx_get_beleg_type') && \function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_pdf_effective_type')) {
				[, $raw_type] = cmx_get_beleg_type($post);
				$effective_type = (string) cmxbu_get_beleg_pdf_effective_type($post_id, (string) $raw_type);
				return \in_array(\sanitize_key($effective_type), ['lieferantenrechnung', 'lieferantenrechnungen'], true);
			}
			return (bool) \array_intersect($terms, ['rechnung', 'rechnungen']);
		}

		$type = \sanitize_key((string) \get_post_meta($post_id, '_cmx_beleg_typ', true));
		return $type === 'lieferantenrechnung' || $type === 'lieferantenrechnungen';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_supplier_due_matches')) {
	function cmx_start_dashboard_supplier_due_matches(string $due_raw, array $range): bool {
		$due_date = cmx_start_dashboard_normalize_date($due_raw);
		if ($due_date === '') {
			return true;
		}
		if (cmx_start_dashboard_date_in_range($due_date, $range)) {
			return true;
		}
		$today = (string) \current_time('Y-m-d');
		return $today !== '' && $due_date < $today;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_is_unpaid_beleg')) {
	function cmx_start_dashboard_is_unpaid_beleg(int $post_id): bool {
		if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_is_unpaid_beleg')) {
			return (bool) cmx_cockpit_is_unpaid_beleg($post_id);
		}
		$paid = (string) \get_post_meta($post_id, '_cmx_beleg_bezahlt_am', true);
		return $paid === '' || $paid === '0' || $paid === '0000-00-00';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_date_in_range')) {
	function cmx_start_dashboard_date_in_range(string $date, array $range): bool {
		if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_date_in_range')) {
			return (bool) cmx_cockpit_date_in_range($date, $range);
		}
		$date = \trim($date);
		$from = (string) ($range['from'] ?? '');
		$to = (string) ($range['to'] ?? '');
		if ($date === '' || !\preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			return true;
		}
		if ($from !== '' && $date < $from) {
			return false;
		}
		if ($to !== '' && $date > $to) {
			return false;
		}
		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_open_receivables_url')) {
	function cmx_start_dashboard_open_receivables_url(): string {
		return (string) \add_query_arg([
			'post_type' => 'belege',
			'cmx_bezahlfilter' => 'offen',
			'cmx_richtungfilter' => 'ausgang',
			'cmx_start_forderungenfilter' => 'offen',
		], \admin_url('edit.php'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_paid_revenue_url')) {
	function cmx_start_dashboard_paid_revenue_url(): string {
		$range = cmx_start_dashboard_range();
		$args = [
			'post_type' => 'belege',
			'cmx_bezahlfilter' => 'bezahlt',
			'cmx_richtungfilter' => 'ausgang',
			'cmx_start_umsatzfilter' => 'kundenumsatz',
		];
		if (!empty($range['from'])) {
			$args['cmx_start_from'] = (string) $range['from'];
		}
		if (!empty($range['to'])) {
			$args['cmx_start_to'] = (string) $range['to'];
		}
		return (string) \add_query_arg($args, \admin_url('edit.php'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_customer_revenue_summary')) {
	function cmx_start_dashboard_customer_revenue_summary(array $range): array {
		if (!\post_type_exists('belege')) {
			return ['count' => 0, 'amount' => 0.0];
		}

		$args = [
			'post_type' => 'belege',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'meta_query' => [
				[
					'key' => '_cmx_beleg_richtung',
					'value' => 'ausgang',
					'compare' => '=',
				],
			],
		];
		$tax = cmx_start_dashboard_beleg_taxonomy();
		if ($tax !== '') {
			$args['tax_query'] = [[
				'taxonomy' => $tax,
				'field' => 'slug',
				'terms' => cmx_start_dashboard_customer_revenue_term_slugs(),
				'operator' => 'IN',
			]];
		}
		$query = new \WP_Query($args);
		$count = 0;
		$total = 0.0;
		foreach ((array) $query->posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0 || !cmx_start_dashboard_is_customer_invoice_beleg($post_id)) {
				continue;
			}
			$paid_date = cmx_start_dashboard_beleg_paid_date($post_id);
			if ($paid_date === '' || !cmx_start_dashboard_date_in_range($paid_date, $range)) {
				continue;
			}
			$count++;
			$total += cmx_start_dashboard_post_amount($post_id);
		}

		return ['count' => $count, 'amount' => \round($total, 2)];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_paid_revenue')) {
	function cmx_start_dashboard_paid_revenue(array $range): float {
		$summary = cmx_start_dashboard_customer_revenue_summary($range);
		return (float) ($summary['amount'] ?? 0.0);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_supplier_expense_summary')) {
	function cmx_start_dashboard_supplier_expense_summary(array $range): array {
		if (!\post_type_exists('belege')) {
			return ['count' => 0, 'amount' => 0.0];
		}

		$query = new \WP_Query([
			'post_type' => 'belege',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
		]);
		$count = 0;
		$total = 0.0;
		foreach ((array) $query->posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0 || !cmx_start_dashboard_is_supplier_expense_beleg($post_id)) {
				continue;
			}
			$chart_date = cmx_start_dashboard_beleg_revenue_expense_chart_date($post_id);
			if ($chart_date === '' || !cmx_start_dashboard_date_in_range($chart_date, $range)) {
				continue;
			}
			$count++;
			$total += cmx_start_dashboard_post_amount($post_id);
		}

		return ['count' => $count, 'amount' => \round($total, 2)];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_open_receivables')) {
	function cmx_start_dashboard_open_receivables(array $range): array {
		if (!\post_type_exists('belege')) {
			return ['total' => 0, 'items' => [], 'list_url' => cmx_start_dashboard_open_receivables_url()];
		}

		$tax = cmx_start_dashboard_beleg_taxonomy();
		$term_slugs = cmx_start_dashboard_invoice_term_slugs();
		$args = [
			'post_type' => 'belege',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'meta_query' => [
				[
					'key' => '_cmx_beleg_richtung',
					'value' => 'ausgang',
					'compare' => '=',
				],
			],
		];
		if ($tax !== '') {
			$args['tax_query'] = [[
				'taxonomy' => $tax,
				'field' => 'slug',
				'terms' => $term_slugs,
				'operator' => 'IN',
			]];
		}

		$query = new \WP_Query($args);
		$items = [];
		foreach ((array) $query->posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0 || !cmx_start_dashboard_is_unpaid_beleg($post_id)) {
				continue;
			}
			$due_raw = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_due_raw')
				? (string) cmx_cockpit_due_raw($post_id)
				: (string) \get_post_meta($post_id, '_cmx_beleg_faellig_am', true);
			if (!cmx_start_dashboard_date_in_range($due_raw, $range)) {
				continue;
			}
			$items[] = [
				'id' => $post_id,
				'amount_display' => cmx_start_dashboard_amount(cmx_start_dashboard_post_amount($post_id)),
				'due_ts' => (int) \strtotime($due_raw),
			];
		}

		return [
			'total' => \count($items),
			'items' => $items,
			'list_url' => cmx_start_dashboard_open_receivables_url(),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_open_supplier_receivables')) {
	function cmx_start_dashboard_open_supplier_receivables(array $range): array {
		if (!\post_type_exists('belege')) {
			return ['total' => 0, 'items' => [], 'list_url' => cmx_start_dashboard_supplier_invoices_url()];
		}

		$tax = cmx_start_dashboard_beleg_taxonomy();
		$term_slugs = cmx_start_dashboard_supplier_invoice_query_term_slugs();
		$args = [
			'post_type' => 'belege',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'meta_query' => [
				[
					'key' => '_cmx_beleg_richtung',
					'value' => 'eingang',
					'compare' => '=',
				],
			],
		];
		if ($tax !== '') {
			$args['tax_query'] = [[
				'taxonomy' => $tax,
				'field' => 'slug',
				'terms' => $term_slugs,
				'operator' => 'IN',
			]];
		}

		$query = new \WP_Query($args);
		$items = [];
		foreach ((array) $query->posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0 || !cmx_start_dashboard_is_supplier_invoice_beleg($post_id) || !cmx_start_dashboard_is_unpaid_beleg($post_id)) {
				continue;
			}
			$due_raw = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_due_raw')
				? (string) cmx_cockpit_due_raw($post_id)
				: (string) \get_post_meta($post_id, '_cmx_beleg_faellig_am', true);
			if (!cmx_start_dashboard_supplier_due_matches($due_raw, $range)) {
				continue;
			}
			$items[] = [
				'id' => $post_id,
				'amount_display' => cmx_start_dashboard_amount(cmx_start_dashboard_post_amount($post_id)),
				'due_ts' => (int) \strtotime($due_raw),
			];
		}

		return [
			'total' => \count($items),
			'items' => $items,
			'list_url' => cmx_start_dashboard_supplier_invoices_url(),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_open_invoice_belege_url')) {
	function cmx_start_dashboard_open_invoice_belege_url(): string {
		$range = cmx_start_dashboard_range();
		$args = [
			'post_type' => 'belege',
			'cmx_bezahlfilter' => 'offen',
			'cmx_richtungfilter' => 'ausgang',
			'cmx_start_belegefilter' => 'offene_rechnungen',
		];
		if (!empty($range['from'])) {
			$args['cmx_start_from'] = (string) $range['from'];
		}
		if (!empty($range['to'])) {
			$args['cmx_start_to'] = (string) $range['to'];
		}
		return (string) \add_query_arg($args, \admin_url('edit.php'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_open_invoice_belege')) {
	function cmx_start_dashboard_open_invoice_belege(array $range): array {
		if (!\post_type_exists('belege')) {
			return ['total' => 0, 'items' => [], 'list_url' => cmx_start_dashboard_open_invoice_belege_url()];
		}

		$tax = cmx_start_dashboard_beleg_taxonomy();
		$term_slugs = cmx_start_dashboard_invoice_term_slugs();
		$args = [
			'post_type' => 'belege',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'meta_query' => [
				[
					'key' => '_cmx_beleg_richtung',
					'value' => 'ausgang',
					'compare' => '=',
				],
			],
		];
		if ($tax !== '') {
			$args['tax_query'] = [[
				'taxonomy' => $tax,
				'field' => 'slug',
				'terms' => $term_slugs,
				'operator' => 'IN',
			]];
		}

		$query = new \WP_Query($args);
		$items = [];
		foreach ((array) $query->posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0 || !cmx_start_dashboard_is_customer_open_invoice_beleg($post_id) || !cmx_start_dashboard_is_unpaid_beleg($post_id)) {
				continue;
			}
			$invoice_date = cmx_start_dashboard_beleg_invoice_date($post_id);
			if ($invoice_date === '' || !cmx_start_dashboard_date_in_range($invoice_date, $range)) {
				continue;
			}
			$due_raw = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_due_raw')
				? (string) cmx_cockpit_due_raw($post_id)
				: (string) \get_post_meta($post_id, '_cmx_beleg_faellig_am', true);
			$items[] = [
				'id' => $post_id,
				'amount_display' => cmx_start_dashboard_amount(cmx_start_dashboard_post_amount($post_id)),
				'due_ts' => (int) \strtotime($due_raw),
			];
		}

		return [
			'total' => \count($items),
			'items' => $items,
			'list_url' => cmx_start_dashboard_open_invoice_belege_url(),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_filter_open_receivables_list')) {
	function cmx_start_dashboard_filter_open_receivables_list(\WP_Query $query): void {
		if (!\is_admin() || !$query->is_main_query()) {
			return;
		}
		$post_type = $query->get('post_type');
		if ($post_type !== 'belege' && (!\is_array($post_type) || !\in_array('belege', $post_type, true))) {
			return;
		}
		$receivables_filter = isset($_GET['cmx_start_forderungenfilter']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_start_forderungenfilter'])) : '';
		$belege_filter = isset($_GET['cmx_start_belegefilter']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_start_belegefilter'])) : '';
		$revenue_filter = isset($_GET['cmx_start_umsatzfilter']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_start_umsatzfilter'])) : '';
		$supplier_filter = isset($_GET['cmx_start_lieferantenfilter']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_start_lieferantenfilter'])) : '';
		if ($receivables_filter !== 'offen' && $belege_filter !== 'offene_rechnungen' && !\in_array($revenue_filter, ['bezahlte_rechnungen', 'kundenumsatz'], true) && $supplier_filter !== 'offene_rechnungen') {
			return;
		}

		$tax = cmx_start_dashboard_beleg_taxonomy();
		if ($tax === '' && ($supplier_filter === 'offene_rechnungen' || $belege_filter === 'offene_rechnungen')) {
			$from = isset($_GET['cmx_start_from']) ? cmx_start_dashboard_normalize_date((string) \wp_unslash($_GET['cmx_start_from'])) : '';
			$to = isset($_GET['cmx_start_to']) ? cmx_start_dashboard_normalize_date((string) \wp_unslash($_GET['cmx_start_to'])) : '';
			$items = $supplier_filter === 'offene_rechnungen'
				? cmx_start_dashboard_open_supplier_receivables(['from' => $from, 'to' => $to])
				: cmx_start_dashboard_open_invoice_belege(['from' => $from, 'to' => $to]);
			$ids = [];
			foreach ((array) ($items['items'] ?? []) as $item) {
				$id = (int) ($item['id'] ?? 0);
				if ($id > 0) {
					$ids[] = $id;
				}
			}
			$query->set('post__in', !empty($ids) ? \array_values(\array_unique($ids)) : [0]);
			return;
		}
		if ($tax === '') {
			return;
		}
		$tax_query = $query->get('tax_query');
		if (!\is_array($tax_query)) {
			$tax_query = [];
		}
		if (!isset($tax_query['relation'])) {
			$tax_query['relation'] = 'AND';
		}
		$tax_query[] = [
			'taxonomy' => $tax,
			'field' => 'slug',
			'terms' => $supplier_filter === 'offene_rechnungen'
				? cmx_start_dashboard_supplier_invoice_query_term_slugs()
				: ($revenue_filter === 'kundenumsatz' ? cmx_start_dashboard_customer_revenue_term_slugs() : cmx_start_dashboard_invoice_term_slugs()),
			'operator' => 'IN',
		];
		$query->set('tax_query', $tax_query);

		if ($belege_filter === 'offene_rechnungen') {
			$from = isset($_GET['cmx_start_from']) ? cmx_start_dashboard_normalize_date((string) \wp_unslash($_GET['cmx_start_from'])) : '';
			$to = isset($_GET['cmx_start_to']) ? cmx_start_dashboard_normalize_date((string) \wp_unslash($_GET['cmx_start_to'])) : '';
			$items = cmx_start_dashboard_open_invoice_belege(['from' => $from, 'to' => $to]);
			$ids = [];
			foreach ((array) ($items['items'] ?? []) as $item) {
				$id = (int) ($item['id'] ?? 0);
				if ($id > 0) {
					$ids[] = $id;
				}
			}
			$query->set('post__in', !empty($ids) ? \array_values(\array_unique($ids)) : [0]);
			return;
		}

		if ($supplier_filter === 'offene_rechnungen') {
			$from = isset($_GET['cmx_start_from']) ? cmx_start_dashboard_normalize_date((string) \wp_unslash($_GET['cmx_start_from'])) : '';
			$to = isset($_GET['cmx_start_to']) ? cmx_start_dashboard_normalize_date((string) \wp_unslash($_GET['cmx_start_to'])) : '';
			$items = cmx_start_dashboard_open_supplier_receivables(['from' => $from, 'to' => $to]);
			$ids = [];
			foreach ((array) ($items['items'] ?? []) as $item) {
				$id = (int) ($item['id'] ?? 0);
				if ($id > 0) {
					$ids[] = $id;
				}
			}
			$query->set('post__in', !empty($ids) ? \array_values(\array_unique($ids)) : [0]);
			return;
		}

		if ($revenue_filter === 'kundenumsatz') {
			$from = isset($_GET['cmx_start_from']) ? cmx_start_dashboard_normalize_date((string) \wp_unslash($_GET['cmx_start_from'])) : '';
			$to = isset($_GET['cmx_start_to']) ? cmx_start_dashboard_normalize_date((string) \wp_unslash($_GET['cmx_start_to'])) : '';
			$meta_query = $query->get('meta_query');
			if (!\is_array($meta_query)) {
				$meta_query = [];
			}
			if (!isset($meta_query['relation'])) {
				$meta_query['relation'] = 'AND';
			}
			$meta_query[] = [
				'key' => '_cmx_beleg_richtung',
				'value' => 'ausgang',
				'compare' => '=',
			];
			if ($from !== '' || $to !== '') {
				$paid_range = [
					'key' => '_cmx_beleg_bezahlt_am',
					'type' => 'DATE',
				];
				if ($from !== '' && $to !== '') {
					$paid_range['value'] = [$from, $to];
					$paid_range['compare'] = 'BETWEEN';
				} elseif ($from !== '') {
					$paid_range['value'] = $from;
					$paid_range['compare'] = '>=';
				} else {
					$paid_range['value'] = $to;
					$paid_range['compare'] = '<=';
				}
				$meta_query[] = $paid_range;
			} else {
				$meta_query[] = [
					'key' => '_cmx_beleg_bezahlt_am',
					'value' => ['', '0', '0000-00-00', '0000-00-00 00:00:00'],
					'compare' => 'NOT IN',
				];
			}
			$query->set('meta_query', $meta_query);
		}
	}
}
\add_action('pre_get_posts', __NAMESPACE__ . '\\cmx_start_dashboard_filter_open_receivables_list', 60);

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_age_buckets')) {
	function cmx_start_dashboard_age_buckets(array $items): array {
		$buckets = [
			'0 - 30 Tage' => ['amount' => 0.0, 'count' => 0],
			'30 - 60 Tage' => ['amount' => 0.0, 'count' => 0],
			'60+ Tage' => ['amount' => 0.0, 'count' => 0],
		];
		$today = (int) \strtotime(\current_time('Y-m-d') . ' 00:00:00');
		foreach ($items as $row) {
			$amount = cmx_start_dashboard_parse_amount((string) ($row['amount_display'] ?? ''));
			$due_ts = (int) ($row['due_ts'] ?? 0);
			$age = ($today > 0 && $due_ts > 0) ? (int) \floor(\max(0, $today - $due_ts) / DAY_IN_SECONDS) : 0;
			$key = $age > 60 ? '60+ Tage' : ($age > 30 ? '30 - 60 Tage' : '0 - 30 Tage');
			$buckets[$key]['amount'] += $amount;
			$buckets[$key]['count']++;
		}

		$values = \array_map(static fn(array $bucket): float => \round((float) ($bucket['amount'] ?? 0), 2), \array_values($buckets));
		$counts = \array_map(static fn(array $bucket): int => (int) ($bucket['count'] ?? 0), \array_values($buckets));

		return [
			'labels' => \array_keys($buckets),
			'values' => $values,
			'counts' => $counts,
			'total' => \array_sum($values),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_summary_fallback')) {
	function cmx_start_dashboard_summary_fallback(array $range): array {
		$income = 0.0;
		$expense = 0.0;
		foreach (cmx_start_dashboard_belege_ids_in_range($range) as $post_id) {
			$amount = cmx_start_dashboard_post_amount($post_id);
			if (cmx_start_dashboard_beleg_direction($post_id) === 'expense') {
				$expense += $amount;
			} else {
				$income += $amount;
			}
		}
		return ['einnahmen' => $income, 'ausgaben' => $expense, 'gewinn' => $income - $expense];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_open_items_fallback')) {
	function cmx_start_dashboard_open_items_fallback(array $range): array {
		$items = [];
		foreach (cmx_start_dashboard_belege_ids_in_range($range) as $post_id) {
			$direction = cmx_start_dashboard_beleg_direction($post_id);
			if ($direction !== 'income') {
				continue;
			}
			$paid = (string) \get_post_meta($post_id, '_cmx_beleg_bezahlt_am', true);
			if ($paid !== '') {
				continue;
			}
			$items[] = [
				'id' => $post_id,
				'amount_display' => cmx_start_dashboard_amount(cmx_start_dashboard_post_amount($post_id)),
				'due_ts' => (int) \strtotime((string) \get_post_meta($post_id, '_cmx_beleg_faellig_am', true)),
			];
		}
		return [
			'total' => \count($items),
			'items' => $items,
			'list_url' => (string) \add_query_arg([
				'post_type' => 'belege',
				'cmx_bezahlfilter' => 'offen',
			], \admin_url('edit.php')),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_data')) {
	function cmx_start_dashboard_data(): array {
		$range = cmx_start_dashboard_range();
			$open = cmx_start_dashboard_open_supplier_receivables($range);
			$open_items = (array) ($open['items'] ?? []);
			$open_amount = cmx_start_dashboard_sum_open_items($open_items);
			$open_belege = cmx_start_dashboard_open_invoice_belege($range);
			$open_belege_items = (array) ($open_belege['items'] ?? []);
			$open_belege_amount = cmx_start_dashboard_sum_open_items($open_belege_items);
			$open_offers = cmx_start_dashboard_open_offers($range);
			$new_customer_ids = cmx_start_dashboard_new_customer_ids($range);
			$new_customer_revenue = cmx_start_dashboard_customer_revenue($new_customer_ids, $range);
			$revenue_summary = cmx_start_dashboard_customer_revenue_summary($range);
			$expense_summary = cmx_start_dashboard_supplier_expense_summary($range);
			$carent = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_carent_collect_data')
				? (array) cmx_cockpit_carent_collect_data()
				: [];

			return [
				'range' => $range,
				'umsatz' => (float) ($revenue_summary['amount'] ?? 0.0),
				'umsatz_belege' => (int) ($revenue_summary['count'] ?? 0),
				'ausgaben' => (float) ($expense_summary['amount'] ?? 0.0),
				'gewinn' => (float) ($revenue_summary['amount'] ?? 0.0) - (float) ($expense_summary['amount'] ?? 0.0),
				'offene_forderungen' => (int) ($open['total'] ?? 0),
				'offene_forderungen_betrag' => $open_amount,
				'offene_forderungen_url' => (string) ($open['list_url'] ?? ''),
				'offene_belege' => (int) ($open_belege['total'] ?? 0),
				'offene_belege_betrag' => $open_belege_amount,
				'offene_belege_url' => (string) ($open_belege['list_url'] ?? ''),
				'offene_offerten' => (int) ($open_offers['count'] ?? 0),
				'offene_offerten_betrag' => (float) ($open_offers['amount'] ?? 0.0),
				'neue_kunden' => \count($new_customer_ids),
				'neue_kunden_betrag' => $new_customer_revenue,
				'carent' => $carent,
			'dokumente' => cmx_start_dashboard_recent_posts('dokumente', 5),
			'artikel' => cmx_start_dashboard_recent_posts('artikel', 5),
			'kontakte' => cmx_start_dashboard_recent_posts('kontakte', 5),
				'belege' => cmx_start_dashboard_recent_posts('belege', 5),
				'projekte' => cmx_start_dashboard_active_projects($range, 5),
				'kontakte_erinnerungen' => cmx_start_dashboard_contact_reminders(5),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_chart_payload')) {
	function cmx_start_dashboard_chart_payload(): array {
		$monitor = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_chart_payload')
			? (array) cmx_cockpit_view_monitor_chart_payload()
			: [];
		$range = cmx_start_dashboard_range();
		$year = (int) \substr((string) ($range['from'] ?? \date('Y-01-01')), 0, 4);
		if ($year <= 0) {
			$year = (int) \date('Y');
		}
		$previous_year = $year - 1;
		$labels = (array) ($monitor['labels'] ?? ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']);
		$income = \array_fill(0, 12, 0.0);
		$expense = \array_fill(0, 12, 0.0);
		$all_income = \array_fill(0, 12, 0.0);
		$all_expense = \array_fill(0, 12, 0.0);
		$current_total = \array_fill(0, 12, 0.0);
		$previous_total = \array_fill(0, 12, 0.0);
		$current_expense_total = \array_fill(0, 12, 0.0);
		$previous_expense_total = \array_fill(0, 12, 0.0);
		$chart_year_range = ['from' => \sprintf('%04d-01-01', $year), 'to' => \sprintf('%04d-12-31', $year)];
		$previous_chart_year_range = ['from' => \sprintf('%04d-01-01', $previous_year), 'to' => \sprintf('%04d-12-31', $previous_year)];
		foreach (cmx_start_dashboard_belege_ids_in_range([]) as $post_id) {
			$chart_date = cmx_start_dashboard_beleg_revenue_expense_chart_date($post_id);
			if ($chart_date === '') {
				continue;
			}
			$amount = cmx_start_dashboard_post_amount($post_id);
			$is_customer_revenue = cmx_start_dashboard_is_customer_revenue_beleg($post_id);
			$is_supplier_expense = cmx_start_dashboard_is_supplier_expense_beleg($post_id);

			$paid_date = cmx_start_dashboard_beleg_paid_date($post_id);
			if ($paid_date !== '' && cmx_start_dashboard_date_in_range($chart_date, $chart_year_range)) {
				$paid_month = (int) \substr($chart_date, 5, 2);
				if ($paid_month >= 1 && $paid_month <= 12) {
					if ($is_supplier_expense) {
						$expense[$paid_month - 1] -= \abs($amount);
					} elseif ($is_customer_revenue) {
						$income[$paid_month - 1] += $amount;
					}
				}
			}

			if (cmx_start_dashboard_date_in_range($chart_date, $chart_year_range)) {
				$month = (int) \substr($chart_date, 5, 2);
				if ($month < 1 || $month > 12) {
					continue;
				}
				if ($is_customer_revenue) {
					$current_total[$month - 1] += $amount;
					$all_income[$month - 1] += $amount;
				} elseif ($is_supplier_expense) {
					$current_expense_total[$month - 1] += $amount;
					$all_expense[$month - 1] -= \abs($amount);
				}
			} elseif (cmx_start_dashboard_date_in_range($chart_date, $previous_chart_year_range)) {
				$month = (int) \substr($chart_date, 5, 2);
				if ($month >= 1 && $month <= 12) {
					if ($is_customer_revenue) {
						$previous_total[$month - 1] += $amount;
					}
					if ($is_supplier_expense) {
						$previous_expense_total[$month - 1] += $amount;
					}
				}
			}
		}

		$open = cmx_start_dashboard_open_receivables($range);
		$open_age = cmx_start_dashboard_age_buckets((array) ($open['items'] ?? []));
		$supplier_open = cmx_start_dashboard_open_supplier_receivables($range);
		$supplier_age = cmx_start_dashboard_age_buckets((array) ($supplier_open['items'] ?? []));

		return [
			'labels' => $labels,
			'income' => \array_map('floatval', $income),
			'expense' => \array_map('floatval', $expense),
			'allIncome' => \array_map('floatval', $all_income),
			'allExpense' => \array_map('floatval', $all_expense),
			'current' => \array_map('floatval', $current_total),
			'previous' => \array_map('floatval', $previous_total),
			'expenseCurrent' => \array_map('floatval', $current_expense_total),
			'expensePrevious' => \array_map('floatval', $previous_expense_total),
			'ageLabels' => (array) ($open_age['labels'] ?? []),
			'ageValues' => (array) ($open_age['values'] ?? []),
			'ageCounts' => (array) ($open_age['counts'] ?? []),
			'openTotal' => (float) ($open_age['total'] ?? 0.0),
			'openAgeUrl' => cmx_start_dashboard_open_receivables_url(),
			'supplierAgeLabels' => (array) ($supplier_age['labels'] ?? []),
			'supplierAgeValues' => (array) ($supplier_age['values'] ?? []),
			'supplierAgeCounts' => (array) ($supplier_age['counts'] ?? []),
			'supplierOpenTotal' => (float) ($supplier_age['total'] ?? 0.0),
			'supplierAgeUrl' => cmx_start_dashboard_supplier_invoices_url(),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_css')) {
	function cmx_start_dashboard_css(): string {
		return '
			.cmx-start{color:#111827}.cmx-start *{box-sizing:border-box}.cmx-start-shell{width:calc(100% - 20px);max-width:none;margin:18px 20px 0 0}
			.cmx-start-top{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;margin-bottom:22px}.cmx-start-title{display:flex;align-items:center;gap:16px}
			.cmx-start-icon{width:44px;height:44px;color:#111827;display:flex;align-items:center;justify-content:center}.cmx-start-icon svg{width:34px;height:34px;display:block}.cmx-start-icon .dashicons{width:34px;height:34px;font-size:34px}
			.cmx-start h1{margin:0;font-size:30px;line-height:1.15;font-weight:800}.cmx-start-sub{margin:7px 0 0;color:#334155;font-size:14px}
			.cmx-start-filter{display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap}.cmx-start-filter label{display:block;font-weight:800;font-size:12px;margin-bottom:6px}.cmx-start-filter-label-reset{color:#1d4f8f;text-decoration:none}.cmx-start-filter-label-reset:hover,.cmx-start-filter-label-reset:focus{color:#123b6d;text-decoration:none}
			.cmx-start-filter select,.cmx-start-filter input[type=date]{min-height:38px;border:1px solid #d7dde8;border-radius:6px;background:#fff;padding:4px 10px;min-width:150px}.cmx-start-button{min-height:38px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;color:#0f6aa8;text-decoration:none;display:inline-flex;align-items:center;gap:8px;padding:0 14px;font-weight:600}.cmx-start-button .dashicons{color:inherit}.cmx-start-button:hover,.cmx-start-button:focus{border-color:#0f6aa8;color:#0b4f7c;text-decoration:none}
			.cmx-start-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:16px}.cmx-start-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 24px rgba(15,23,42,.06)}
			.cmx-start-kpi{padding:20px;min-height:148px;display:flex;flex-direction:column;justify-content:space-between}.cmx-start-kpi-head{display:flex;gap:14px;align-items:flex-start}.cmx-start-kpi .dashicons,.cmx-start-kpi-icon{font-size:40px;width:40px;height:40px;display:block;flex:0 0 auto}.cmx-start-kpi .cmx-start-kpi-icon-car{font-size:46px;width:46px;height:46px;margin:-3px -3px 0 0}.cmx-start-kpi-icon--plain{width:34px;height:34px}.cmx-start-kpi-title{font-weight:800}.cmx-start-kpi-title a{color:#1d4f8f;text-decoration:none}.cmx-start-kpi-title a:hover,.cmx-start-kpi-title a:focus{color:#123b6d;text-decoration:underline;text-underline-offset:3px}.cmx-start-kpi-sub{font-size:12px;color:#475569;margin-top:4px}.cmx-start-kpi-values{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;margin-top:14px}.cmx-start-kpi-value{font-size:24px;font-weight:900}.cmx-start-kpi-amount{font-size:20px;font-weight:900;text-align:right;white-space:nowrap;color:#111827}.cmx-start-kpi-values a{text-decoration:none}.cmx-start-kpi-values a:hover,.cmx-start-kpi-values a:focus{text-decoration:underline;text-underline-offset:3px}
			.cmx-start-green{color:#16a34a}.cmx-start-blue{color:#0f6aa8}.cmx-start-purple{color:#6d28d9}.cmx-start-orange{color:#d97706}.cmx-start-red{color:#dc2626}
			.cmx-start-section{padding:18px;margin-bottom:16px;overflow:visible}.cmx-start-section h2{font-size:18px;margin:0 0 14px;font-weight:900}.cmx-start-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;overflow:visible;padding-bottom:2px;align-items:start}.cmx-start-activity-card{border:1px solid #e2e8f0;border-radius:10px;padding:16px 16px 4px;background:#fff;min-height:0;overflow:visible;position:relative}.cmx-start-activity-card:has(.cmx-start-activity-create-menu[open]){z-index:20}.cmx-start-activity-card h3{font-size:15px;margin:0 0 14px;font-weight:700;display:flex;align-items:center;gap:7px;position:relative}.cmx-start-activity-card h3 a{color:#1d4f8f;text-decoration:none}.cmx-start-activity-card h3 a:hover,.cmx-start-activity-card h3 a:focus{color:#123b6d;text-decoration:underline;text-underline-offset:3px}.cmx-start-activity-add{width:16px;height:16px;display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;color:#1d4f8f;text-decoration:none}.cmx-start-activity-add:hover,.cmx-start-activity-add:focus{color:#123b6d;text-decoration:none}.cmx-start-activity-add svg{width:16px;height:16px;display:block}.cmx-start-activity-create-menu{position:relative;display:inline-flex}.cmx-start-activity-create-menu summary{list-style:none;cursor:pointer}.cmx-start-activity-create-menu summary::-webkit-details-marker{display:none}.cmx-start-activity-create-options{position:absolute;top:calc(100% + 7px);left:0;z-index:1000;min-width:168px;max-height:260px;overflow:auto;padding:6px;background:#fff;border:1px solid #ccd0d4;border-radius:8px;box-shadow:0 16px 30px rgba(0,0,0,.18)}.cmx-start-activity-create-options a{display:block;padding:8px 12px;border-radius:6px;color:#1d2327;text-decoration:none;font-weight:500;white-space:nowrap}.cmx-start-activity-create-options a:hover,.cmx-start-activity-create-options a:focus{background:#f5d6cf;color:#1d2327;text-decoration:none}.cmx-start-activity-calendar{margin-left:auto;width:18px;height:18px;border:0;background:transparent;color:#1d4f8f;padding:0;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}.cmx-start-activity-calendar:hover,.cmx-start-activity-calendar:focus{color:#123b6d}.cmx-start-activity-calendar svg{width:18px;height:18px;display:block}.cmx-start-activity-calendar .cmx-start-calendar-heart,.cmx-start-activity-calendar .cmx-start-hand-coins{display:none}.cmx-start-activity-card.is-reminders .cmx-start-activity-calendar .cmx-start-calendar,.cmx-start-activity-card.is-amounts .cmx-start-activity-calendar .cmx-start-calendar{display:none}.cmx-start-activity-card.is-reminders .cmx-start-activity-calendar .cmx-start-calendar-heart,.cmx-start-activity-card.is-amounts .cmx-start-activity-calendar .cmx-start-hand-coins{display:block}.cmx-start-list{margin:0;padding:0;list-style:none}.cmx-start-list[hidden]{display:none}.cmx-start-list li{display:block;border-bottom:1px solid #eef2f7;padding:2.5px 0}.cmx-start-list li:last-child{border-bottom:0}.cmx-start-item-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:baseline}.cmx-start-item-link{text-decoration:none}.cmx-start-item-link:hover,.cmx-start-item-link:focus{text-decoration:none}.cmx-start-item-title{font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.cmx-start-item-link .cmx-start-item-title{color:#a9231d;text-decoration:underline}.cmx-start-item-link:hover .cmx-start-item-title,.cmx-start-item-link:focus .cmx-start-item-title{color:#1d4f8f}.cmx-start-item-meta{font-size:12px;color:#64748b;white-space:nowrap;text-align:right}
			.cmx-start-item-link .cmx-start-item-title.is-carent-closed{color:#16a34a}.cmx-start-item-link .cmx-start-item-title.is-carent-open{color:#dc2626}.cmx-start-item-link:hover .cmx-start-item-title.is-carent-closed,.cmx-start-item-link:focus .cmx-start-item-title.is-carent-closed{color:#15803d}.cmx-start-item-link:hover .cmx-start-item-title.is-carent-open,.cmx-start-item-link:focus .cmx-start-item-title.is-carent-open{color:#b91c1c}
				.cmx-start-chart-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}.cmx-start-chart-card{border:1px solid #e2e8f0;border-radius:10px;padding:16px;min-height:310px}.cmx-start-chart-title-row{display:flex;align-items:center;justify-content:space-between;gap:12px}.cmx-start-chart-card h3{font-size:14px;margin:0 0 4px;font-weight:900}.cmx-start-chart-card h3 a{color:#1d4f8f;text-decoration:none}.cmx-start-chart-card h3 a:hover,.cmx-start-chart-card h3 a:focus{color:#123b6d;text-decoration:underline;text-underline-offset:3px}.cmx-start-chart-title-toggle{border:0;background:transparent;color:#1e4f90;font:inherit;font-weight:900;padding:0;margin:0;cursor:pointer;text-align:left}.cmx-start-chart-title-toggle:hover,.cmx-start-chart-title-toggle:focus{color:#1e4f90;text-decoration:underline;text-underline-offset:3px}.cmx-start-chart-heading-toggle,.cmx-start-chart-toggle{width:20px;height:20px;border:0;background:transparent;color:#1d4f8f;padding:0;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}.cmx-start-chart-heading-toggle:hover,.cmx-start-chart-heading-toggle:focus,.cmx-start-chart-toggle:hover,.cmx-start-chart-toggle:focus{color:#123b6d}.cmx-start-chart-heading-toggle[aria-pressed=true]{color:#16a34a}.cmx-start-chart-heading-icon{width:20px;height:20px;color:inherit;display:block;flex:0 0 auto}.cmx-start-chart-toggle svg{width:18px;height:18px;display:block}.cmx-start-chart-sub{font-size:12px;color:#475569;margin-bottom:12px}.cmx-start-chart-box{position:relative;height:230px}.cmx-start-chart-legend{position:absolute;z-index:2;top:-4px;left:150px;display:flex;align-items:center;gap:12px;font-size:12px;color:#4b5563;line-height:1}.cmx-start-chart-legend-item{display:inline-flex;align-items:center;gap:5px}.cmx-start-chart-legend-dot{width:12px;height:12px;border-radius:4px;display:inline-block}.cmx-start-chart-box canvas{width:100%!important;height:230px!important}
			html.cmx-dark-mode body.wp-admin .cmx-start{color:#e5e7eb}
			html.cmx-dark-mode body.wp-admin .cmx-start-shell{background:transparent}
			html.cmx-dark-mode body.wp-admin .cmx-start h1,html.cmx-dark-mode body.wp-admin .cmx-start h2,html.cmx-dark-mode body.wp-admin .cmx-start h3,html.cmx-dark-mode body.wp-admin .cmx-start-icon,html.cmx-dark-mode body.wp-admin .cmx-start-kpi-amount{color:#f8fafc}
			html.cmx-dark-mode body.wp-admin .cmx-start-sub,html.cmx-dark-mode body.wp-admin .cmx-start-kpi-sub,html.cmx-dark-mode body.wp-admin .cmx-start-chart-sub,html.cmx-dark-mode body.wp-admin .cmx-start-item-meta,html.cmx-dark-mode body.wp-admin .cmx-start-chart-legend{color:#a7b4c7}
			html.cmx-dark-mode body.wp-admin .cmx-start-card{background:#172033;border-color:#334155;box-shadow:0 16px 40px rgba(0,0,0,.24)}
			html.cmx-dark-mode body.wp-admin .cmx-start-kpi,html.cmx-dark-mode body.wp-admin .cmx-start-section{background:#172033}
			html.cmx-dark-mode body.wp-admin .cmx-start-activity-card,html.cmx-dark-mode body.wp-admin .cmx-start-chart-card{background:#101826;border-color:#2f3d52;box-shadow:inset 0 1px 0 rgba(255,255,255,.03)}
			html.cmx-dark-mode body.wp-admin .cmx-start-list li{border-bottom-color:#263449}
			html.cmx-dark-mode body.wp-admin .cmx-start-filter label,html.cmx-dark-mode body.wp-admin .cmx-start-filter-label-reset{color:#bfdbfe}
			html.cmx-dark-mode body.wp-admin .cmx-start-filter select,html.cmx-dark-mode body.wp-admin .cmx-start-filter input[type=date],html.cmx-dark-mode body.wp-admin .cmx-start-button{background:#111827;border-color:#475569;color:#e5e7eb}
			html.cmx-dark-mode body.wp-admin .cmx-start-button:hover,html.cmx-dark-mode body.wp-admin .cmx-start-button:focus,html.cmx-dark-mode body.wp-admin .cmx-start-filter select:focus,html.cmx-dark-mode body.wp-admin .cmx-start-filter input[type=date]:focus{border-color:#93c5fd;color:#f8fafc;box-shadow:0 0 0 1px rgba(147,197,253,.24)}
			html.cmx-dark-mode body.wp-admin .cmx-start-kpi-title a,html.cmx-dark-mode body.wp-admin .cmx-start-activity-card h3 a,html.cmx-dark-mode body.wp-admin .cmx-start-activity-add,html.cmx-dark-mode body.wp-admin .cmx-start-activity-calendar,html.cmx-dark-mode body.wp-admin .cmx-start-chart-card h3 a,html.cmx-dark-mode body.wp-admin .cmx-start-chart-title-toggle,html.cmx-dark-mode body.wp-admin .cmx-start-chart-heading-toggle,html.cmx-dark-mode body.wp-admin .cmx-start-chart-toggle{color:#93c5fd}
			html.cmx-dark-mode body.wp-admin .cmx-start-kpi-title a:hover,html.cmx-dark-mode body.wp-admin .cmx-start-kpi-title a:focus,html.cmx-dark-mode body.wp-admin .cmx-start-activity-card h3 a:hover,html.cmx-dark-mode body.wp-admin .cmx-start-activity-card h3 a:focus,html.cmx-dark-mode body.wp-admin .cmx-start-activity-add:hover,html.cmx-dark-mode body.wp-admin .cmx-start-activity-add:focus,html.cmx-dark-mode body.wp-admin .cmx-start-activity-calendar:hover,html.cmx-dark-mode body.wp-admin .cmx-start-activity-calendar:focus,html.cmx-dark-mode body.wp-admin .cmx-start-chart-card h3 a:hover,html.cmx-dark-mode body.wp-admin .cmx-start-chart-card h3 a:focus,html.cmx-dark-mode body.wp-admin .cmx-start-chart-title-toggle:hover,html.cmx-dark-mode body.wp-admin .cmx-start-chart-title-toggle:focus,html.cmx-dark-mode body.wp-admin .cmx-start-chart-heading-toggle:hover,html.cmx-dark-mode body.wp-admin .cmx-start-chart-heading-toggle:focus,html.cmx-dark-mode body.wp-admin .cmx-start-chart-toggle:hover,html.cmx-dark-mode body.wp-admin .cmx-start-chart-toggle:focus{color:#bfdbfe}
			html.cmx-dark-mode body.wp-admin .cmx-start-item-link .cmx-start-item-title{color:#fca5a5}
			html.cmx-dark-mode body.wp-admin .cmx-start-item-link:hover .cmx-start-item-title,html.cmx-dark-mode body.wp-admin .cmx-start-item-link:focus .cmx-start-item-title{color:#bfdbfe}
			html.cmx-dark-mode body.wp-admin .cmx-start-green{color:#34d399}html.cmx-dark-mode body.wp-admin .cmx-start-blue{color:#38bdf8}html.cmx-dark-mode body.wp-admin .cmx-start-purple{color:#c4b5fd}html.cmx-dark-mode body.wp-admin .cmx-start-orange{color:#fbbf24}html.cmx-dark-mode body.wp-admin .cmx-start-red{color:#f87171}
			html.cmx-dark-mode body.wp-admin .cmx-start-activity-create-options{background:#111827;border-color:#475569;box-shadow:0 18px 36px rgba(0,0,0,.42)}
			html.cmx-dark-mode body.wp-admin .cmx-start-activity-create-options a{color:#e5e7eb}
			html.cmx-dark-mode body.wp-admin .cmx-start-activity-create-options a:hover,html.cmx-dark-mode body.wp-admin .cmx-start-activity-create-options a:focus{background:#253247;color:#f8fafc}
			@media(max-width:1400px){.cmx-start-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.cmx-start-chart-grid{grid-template-columns:1fr}}@media(max-width:900px){.cmx-start-top{display:block}.cmx-start-filter{margin-top:16px}.cmx-start-kpis{grid-template-columns:1fr}}
		';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_kpi')) {
	function cmx_start_dashboard_kpi(string $color, string $icon, string $title, string $sub, string $value, string $amount = '', string $title_url = '', string $icon_color = '', string $amount_color = '', string $value_url = '', string $amount_url = ''): void {
		$value_number = cmx_start_dashboard_parse_amount($value);
		$amount_number = $amount !== '' ? cmx_start_dashboard_parse_amount($amount) : 0.0;
		$value_decimals = \preg_match('/[.,]\d{2}$/', $value) ? 2 : 0;
		$amount_decimals = \preg_match('/[.,]\d{2}$/', $amount) ? 2 : 0;
		$icon_name = \str_starts_with($icon, 'asset:') ? \substr($icon, 6) : '';
		$icon_color = $icon_color !== '' ? $icon_color : $color;
		$icon_class = ($icon_name === 'gamepad-directional') ? 'cmx-start-kpi-icon cmx-start-kpi-icon--plain cmx-start-' . $icon_color : 'cmx-start-kpi-icon cmx-start-' . $icon_color;
		$dashicon_extra_class = $icon === 'dashicons-car' ? ' cmx-start-kpi-icon-car' : '';
		$icon_html = \str_starts_with($icon, 'asset:')
			? cmx_start_dashboard_icon_svg($icon_name, $icon_class, 'dashicons-admin-generic')
			: '<span class="dashicons ' . \esc_attr($icon) . ' cmx-start-' . \esc_attr($icon_color) . $dashicon_extra_class . '"></span>';
		$title_html = $title_url !== ''
			? '<a href="' . \esc_url($title_url) . '">' . \esc_html($title) . '</a>'
			: \esc_html($title);
		$value_tag = $value_url !== '' ? 'a' : 'div';
		$amount_tag = $amount_url !== '' ? 'a' : 'div';
		$value_href = $value_url !== '' ? ' href="' . \esc_url($value_url) . '"' : '';
		$amount_href = $amount_url !== '' ? ' href="' . \esc_url($amount_url) . '"' : '';

		echo '<section class="cmx-start-card cmx-start-kpi">';
		echo '<div class="cmx-start-kpi-head">' . $icon_html . '<div><div class="cmx-start-kpi-title">' . $title_html . '</div><div class="cmx-start-kpi-sub">' . \esc_html($sub) . '</div></div></div>';
		echo '<div class="cmx-start-kpi-values"><' . $value_tag . $value_href . ' class="cmx-start-kpi-value cmx-start-' . \esc_attr($color) . '" data-cmx-count-to="' . \esc_attr((string) $value_number) . '" data-cmx-count-decimals="' . \esc_attr((string) $value_decimals) . '">' . \esc_html($value) . '</' . $value_tag . '>';
		if ($amount !== '') {
			echo '<' . $amount_tag . $amount_href . ' class="cmx-start-kpi-amount' . ($amount_color !== '' ? ' cmx-start-' . \esc_attr($amount_color) : '') . '" data-cmx-count-to="' . \esc_attr((string) $amount_number) . '" data-cmx-count-decimals="' . \esc_attr((string) $amount_decimals) . '">' . \esc_html($amount) . '</' . $amount_tag . '>';
		}
		echo '</div>';
		echo '</section>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_render_posts')) {
	function cmx_start_dashboard_render_posts(array $posts, $tooltip_callback = null): void {
		if ($posts === []) {
			echo '<p class="cmx-start-item-meta">Keine Einträge vorhanden.</p>';
			return;
		}
		echo '<ul class="cmx-start-list">';
		foreach ($posts as $post) {
			if (!$post instanceof \WP_Post) {
				continue;
			}
			$url = (string) \get_edit_post_link((int) $post->ID, '');
			$tooltip = \is_callable($tooltip_callback) ? \trim((string) \call_user_func($tooltip_callback, (int) $post->ID)) : '';
			$title_attr = $tooltip !== '' ? ' title="' . \esc_attr($tooltip) . '"' : '';
			echo '<li><a class="cmx-start-item-row cmx-start-item-link" href="' . \esc_url($url) . '"' . $title_attr . '><span class="cmx-start-item-title">' . \esc_html(\get_the_title($post)) . '</span><span class="cmx-start-item-meta">' . \esc_html(\get_the_date('d.m.Y', $post)) . '</span></a></li>';
		}
		echo '</ul>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_booking_article_title')) {
	function cmx_start_dashboard_booking_article_title(int $post_id): string {
		$meta_key = \defined(__NAMESPACE__ . '\\CMX_BUCHUNGEN_META_ARTIKEL') ? CMX_BUCHUNGEN_META_ARTIKEL : '_cmx_buchung_artikel_id';
		$artikel_id = (int) \get_post_meta($post_id, $meta_key, true);
		if ($artikel_id <= 0 || (string) \get_post_type($artikel_id) !== 'artikel') {
			return '';
		}
		return \trim((string) \get_the_title($artikel_id));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_render_contact_activity')) {
	function cmx_start_dashboard_render_contact_activity(array $posts, array $reminders): void {
		cmx_start_dashboard_render_posts($posts);
		if ($reminders === []) {
			echo '<ul class="cmx-start-list cmx-start-reminder-list" hidden><li><div class="cmx-start-item-row"><div class="cmx-start-item-meta">Keine Erinnerungen vorhanden.</div></div></li></ul>';
			return;
		}
		echo '<ul class="cmx-start-list cmx-start-reminder-list" hidden>';
		foreach ($reminders as $entry) {
			$post_id = (int) ($entry['id'] ?? 0);
			$post = $post_id > 0 ? \get_post($post_id) : null;
			if (!$post instanceof \WP_Post) {
				continue;
			}
			$url = (string) \get_edit_post_link($post_id, '');
			$date = (string) ($entry['date'] ?? '');
			$type = (string) ($entry['type'] ?? '');
			$date_label = $date !== '' ? \wp_date('d.m.Y', \strtotime($date)) : '';
				echo '<li><a class="cmx-start-item-row cmx-start-item-link" href="' . \esc_url($url) . '"><span class="cmx-start-item-title">' . \esc_html(\get_the_title($post)) . '</span><span class="cmx-start-item-meta" title="' . \esc_attr($type) . '">' . \esc_html($date_label) . '</span></a></li>';
		}
		echo '</ul>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_render_belege_activity')) {
	function cmx_start_dashboard_render_belege_activity(array $posts): void {
		if ($posts === []) {
			echo '<p class="cmx-start-item-meta">Keine Einträge vorhanden.</p>';
			return;
		}
		echo '<ul class="cmx-start-list" data-cmx-belege-list>';
		foreach ($posts as $post) {
			if (!$post instanceof \WP_Post) {
				continue;
			}
			$post_id = (int) $post->ID;
			$url = (string) \get_edit_post_link($post_id, '');
			echo '<li><a class="cmx-start-item-row cmx-start-item-link" href="' . \esc_url($url) . '"><span class="cmx-start-item-title">' . \esc_html(\get_the_title($post)) . '</span><span class="cmx-start-item-meta"><span class="cmx-start-item-date">' . \esc_html(\get_the_date('d.m.Y', $post)) . '</span><span class="cmx-start-item-amount" hidden>' . \esc_html(cmx_start_dashboard_amount(cmx_start_dashboard_post_amount($post_id))) . '</span></span></a></li>';
		}
		echo '</ul>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_render_project_activity')) {
	function cmx_start_dashboard_render_project_activity(array $posts): void {
		if ($posts === []) {
			echo '<p class="cmx-start-item-meta">Keine Einträge vorhanden.</p>';
			return;
		}

		echo '<ul class="cmx-start-list">';
		foreach ($posts as $post) {
			if (!$post instanceof \WP_Post) {
				continue;
			}
			$post_id = (int) $post->ID;
			$url = (string) \get_edit_post_link($post_id, '');
			$amount = cmx_start_dashboard_amount(cmx_start_dashboard_project_revenue($post_id));
			echo '<li><a class="cmx-start-item-row cmx-start-item-link" href="' . \esc_url($url) . '"><span class="cmx-start-item-title">' . \esc_html(\get_the_title($post)) . '</span><span class="cmx-start-item-meta">' . \esc_html($amount) . '</span></a></li>';
		}
		echo '</ul>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_render_carent_activity')) {
	function cmx_start_dashboard_render_carent_activity(array $carent): void {
		$items = cmx_start_dashboard_carent_activity_items(5, $carent);
		if ($items === []) {
			echo '<p class="cmx-start-item-meta">Keine Einträge vorhanden.</p>';
			return;
		}

		echo '<ul class="cmx-start-list">';
		foreach ($items as $item) {
			$item = (array) $item;
			$title = \trim((string) ($item['title'] ?? ''));
			if ($title === '') {
				$title = 'Vertrag';
			}
			$url = (string) ($item['edit_url'] ?? '');
			$status = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_carent_normalize_status')
				? (string) cmx_cockpit_carent_normalize_status((string) ($item['status'] ?? 'offen'))
				: ((string) ($item['status'] ?? '') === 'abgeschlossen' ? 'abgeschlossen' : 'offen');
			$date_label = \trim((string) ($item['date_label'] ?? ''));
			if ($date_label !== '') {
				$date_label = \preg_replace('/\s+\d{1,2}:\d{2}$/', '', $date_label) ?: $date_label;
			}
			$title_class = $status === 'abgeschlossen' ? ' is-carent-closed' : ' is-carent-open';

			if ($url !== '') {
				echo '<li><a class="cmx-start-item-row cmx-start-item-link" href="' . \esc_url($url) . '"><span class="cmx-start-item-title' . \esc_attr($title_class) . '">' . \esc_html($title) . '</span><span class="cmx-start-item-meta">' . \esc_html($date_label) . '</span></a></li>';
			} else {
				echo '<li><div class="cmx-start-item-row"><span class="cmx-start-item-title' . \esc_attr($title_class) . '">' . \esc_html($title) . '</span><span class="cmx-start-item-meta">' . \esc_html($date_label) . '</span></div></li>';
			}
		}
		echo '</ul>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_carent_activity_items')) {
	function cmx_start_dashboard_carent_activity_items(int $limit, array $fallback_carent = []): array {
		$limit = \max(1, $limit);
		if (!\post_type_exists('carent')) {
			return \array_slice((array) ($fallback_carent['items'] ?? []), 0, $limit);
		}

		$status_meta_key = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_carent_status_meta_key')
			? (string) cmx_cockpit_carent_status_meta_key()
			: '_cmx_carent_status';

		$items = [];
		$seen = [];
		$queries = [
			[
				'relation' => 'OR',
				[
					'key' => $status_meta_key,
					'value' => 'abgeschlossen',
					'compare' => '!=',
				],
				[
					'key' => $status_meta_key,
					'compare' => 'NOT EXISTS',
				],
			],
			[
				[
					'key' => $status_meta_key,
					'value' => 'abgeschlossen',
					'compare' => '=',
				],
			],
		];

		foreach ($queries as $meta_query) {
			if (\count($items) >= $limit) {
				break;
			}

			$query = new \WP_Query([
				'post_type' => 'carent',
				'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
				'posts_per_page' => $limit,
				'orderby' => 'modified',
				'order' => 'DESC',
				'fields' => 'ids',
				'no_found_rows' => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query' => $meta_query,
			]);

			foreach ((array) $query->posts as $post_id) {
				$post_id = (int) $post_id;
				if ($post_id <= 0 || isset($seen[$post_id]) || \count($items) >= $limit) {
					continue;
				}
				$seen[$post_id] = true;
				$status = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_carent_normalize_status')
					? (string) cmx_cockpit_carent_normalize_status((string) \get_post_meta($post_id, $status_meta_key, true))
					: ((string) \get_post_meta($post_id, $status_meta_key, true) === 'abgeschlossen' ? 'abgeschlossen' : 'offen');
				$timestamp = (int) \get_post_modified_time('U', false, $post_id);
				if ($timestamp <= 0) {
					$timestamp = (int) \get_post_time('U', false, $post_id);
				}
				$items[] = [
					'id' => $post_id,
					'title' => \trim((string) \get_the_title($post_id)) ?: 'Vertrag #' . $post_id,
					'edit_url' => (string) \get_edit_post_link($post_id, ''),
					'status' => $status,
					'date_label' => $timestamp > 0 ? (string) \date_i18n('d.m.Y', $timestamp) : '',
				];
			}
		}

		return $items;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_supplier_invoices_url')) {
	function cmx_start_dashboard_supplier_invoices_url(): string {
		$range = cmx_start_dashboard_range();
		$args = [
			'post_type' => 'belege',
			'cmx_richtungfilter' => 'eingang',
			'cmx_bezahlfilter' => 'offen',
			'cmx_start_lieferantenfilter' => 'offene_rechnungen',
		];
		if (!empty($range['from'])) {
			$args['cmx_start_from'] = (string) $range['from'];
		}
		if (!empty($range['to'])) {
			$args['cmx_start_to'] = (string) $range['to'];
		}

		return (string) \add_query_arg($args, \admin_url('edit.php'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_open_belege_url')) {
	function cmx_start_dashboard_open_belege_url(): string {
		return (string) \add_query_arg([
			'post_type' => 'belege',
			'cmx_bezahlfilter' => 'offen',
		], \admin_url('edit.php'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_open_offers_url')) {
	function cmx_start_dashboard_open_offers_url(): string {
		$args = [
			'post_type' => 'belege',
			'cmx_offertenstatusfilter' => 'offen',
		];

		$tax = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_taxonomy') ? (string) cmx_cockpit_beleg_taxonomy() : '';
		if ($tax === '' && \function_exists(__NAMESPACE__ . '\\cmx_belege_taxonomy')) {
			$tax = (string) cmx_belege_taxonomy();
		}
		if ($tax !== '' && \taxonomy_exists($tax)) {
			$args[$tax] = 'offerte';
		}

		return (string) \add_query_arg($args, \admin_url('edit.php'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_filter_open_offers_list')) {
	function cmx_start_dashboard_filter_open_offers_list(\WP_Query $query): void {
		if (!\is_admin() || !$query->is_main_query()) {
			return;
		}

		$post_type = $query->get('post_type');
		if ($post_type !== 'belege' && (!\is_array($post_type) || !\in_array('belege', $post_type, true))) {
			return;
		}

		$filter = isset($_GET['cmx_offertenstatusfilter']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_offertenstatusfilter'])) : '';
		if ($filter !== 'offen') {
			return;
		}

		$meta_query = $query->get('meta_query');
		if (!\is_array($meta_query)) {
			$meta_query = [];
		}
		if (!isset($meta_query['relation'])) {
			$meta_query['relation'] = 'AND';
		}
		$meta_query[] = [
			'relation' => 'OR',
			[
				'key' => '_cmx_beleg_offertenstatus',
				'compare' => 'NOT EXISTS',
			],
			[
				'key' => '_cmx_beleg_offertenstatus',
				'value' => ['', 'offen'],
				'compare' => 'IN',
			],
		];
		$query->set('meta_query', $meta_query);

		$tax = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_taxonomy') ? (string) cmx_cockpit_beleg_taxonomy() : '';
		if ($tax === '' && \function_exists(__NAMESPACE__ . '\\cmx_belege_taxonomy')) {
			$tax = (string) cmx_belege_taxonomy();
		}
		if ($tax === '' || !\taxonomy_exists($tax)) {
			return;
		}

		$tax_query = $query->get('tax_query');
		if (!\is_array($tax_query)) {
			$tax_query = [];
		}
		if (!isset($tax_query['relation'])) {
			$tax_query['relation'] = 'AND';
		}
		$tax_query[] = [
			'taxonomy' => $tax,
			'field' => 'slug',
			'terms' => ['offerte', 'offerten'],
			'operator' => 'IN',
		];
		$query->set('tax_query', $tax_query);
	}
}
\add_action('pre_get_posts', __NAMESPACE__ . '\\cmx_start_dashboard_filter_open_offers_list', 60);

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_activity_heading')) {
	function cmx_start_dashboard_activity_heading(string $label, string $post_type, string $action_html = ''): void {
		$url = \post_type_exists($post_type)
			? \admin_url('edit.php?post_type=' . $post_type)
			: '';
		$new_url = \post_type_exists($post_type)
			? \admin_url('post-new.php?post_type=' . $post_type)
			: '';
		echo '<h3>';
		if ($new_url !== '') {
			echo cmx_start_dashboard_activity_create_control($label, $post_type, $new_url);
		}
		if ($url !== '') {
			echo '<a href="' . \esc_url($url) . '">' . \esc_html($label) . '</a>';
		} else {
			echo \esc_html($label);
		}
		echo $action_html;
		echo '</h3>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_activity_create_control')) {
	function cmx_start_dashboard_activity_create_control(string $label, string $post_type, string $new_url): string {
		if ($post_type === 'kontakte') {
			$taxonomy = '';
			if (\function_exists(__NAMESPACE__ . '\\cmx_kontakte_notice_category_taxonomies')) {
				$taxonomies = cmx_kontakte_notice_category_taxonomies();
				$taxonomy = isset($taxonomies[0]) ? (string) $taxonomies[0] : '';
			}
			if ($taxonomy === '' && \function_exists(__NAMESPACE__ . '\\cmx_tax_key')) {
				$taxonomy = (string) cmx_tax_key('kontakte', 'Kategorien');
			}

			$options = [];
			if ($taxonomy !== '' && \taxonomy_exists($taxonomy)) {
				$terms = \get_terms([
					'taxonomy' => $taxonomy,
					'hide_empty' => false,
					'orderby' => 'name',
					'order' => 'ASC',
				]);
				if (!\is_wp_error($terms)) {
					foreach ($terms as $term) {
						$options[(string) $term->slug] = (string) $term->name;
					}
				}
			}

			if ($options === []) {
				return '<a class="cmx-start-activity-add" href="' . \esc_url($new_url) . '" title="' . \esc_attr($label . ' erfassen') . '" aria-label="' . \esc_attr($label . ' erfassen') . '">' . cmx_start_dashboard_icon_svg('circle-plus', 'cmx-start-activity-add-icon', 'dashicons-plus-alt2') . '</a>';
			}

			$html = '<details class="cmx-start-activity-create-menu">';
			$html .= '<summary class="cmx-start-activity-add" title="' . \esc_attr($label . ' erfassen') . '" aria-label="' . \esc_attr($label . ' erfassen') . '">' . cmx_start_dashboard_icon_svg('circle-plus', 'cmx-start-activity-add-icon', 'dashicons-plus-alt2') . '</summary>';
			$html .= '<div class="cmx-start-activity-create-options">';
			foreach ($options as $slug => $option_label) {
				$href = \add_query_arg('cmx_kontakt_kategorie', $slug, $new_url);
				$html .= '<a href="' . \esc_url($href) . '">' . \esc_html($option_label) . '</a>';
			}
			$html .= '</div></details>';

			return $html;
		}

		if ($post_type !== 'belege') {
			return '<a class="cmx-start-activity-add" href="' . \esc_url($new_url) . '" title="' . \esc_attr($label . ' erfassen') . '" aria-label="' . \esc_attr($label . ' erfassen') . '">' . cmx_start_dashboard_icon_svg('circle-plus', 'cmx-start-activity-add-icon', 'dashicons-plus-alt2') . '</a>';
		}

		$options = [
			'rechnung' => 'Rechnung',
			'lieferschein' => 'Lieferschein',
			'quittung' => 'Quittung',
			'gutschrift' => 'Gutschrift',
			'offerte' => 'Offerte',
		];
		if (\post_type_exists('buchungen')) {
			$options['buchung'] = 'Buchung';
		}

		$html = '<details class="cmx-start-activity-create-menu">';
		$html .= '<summary class="cmx-start-activity-add" title="' . \esc_attr($label . ' erfassen') . '" aria-label="' . \esc_attr($label . ' erfassen') . '">' . cmx_start_dashboard_icon_svg('circle-plus', 'cmx-start-activity-add-icon', 'dashicons-plus-alt2') . '</summary>';
		$html .= '<div class="cmx-start-activity-create-options">';
		foreach ($options as $slug => $option_label) {
			$href = $slug === 'buchung'
				? \admin_url('post-new.php?post_type=buchungen')
				: \admin_url('post-new.php?post_type=belege&cmx_beleg_typ=' . $slug);
			$html .= '<a href="' . \esc_url($href) . '">' . \esc_html($option_label) . '</a>';
		}
		$html .= '</div></details>';

		return $html;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_render')) {
	function cmx_start_dashboard_render(): void {
		if (!\current_user_can(cmx_start_dashboard_capability())) {
			\wp_die('Keine Berechtigung.');
		}
		$data = cmx_start_dashboard_data();
		$range = (array) ($data['range'] ?? []);
		$filter_ranges = [];
		foreach (cmx_start_dashboard_preset_options() as $key => $label) {
			if ((string) $key === 'individuell') {
				continue;
			}
			$filter_ranges[(string) $key] = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_range_from_preset') ? (array) cmx_cockpit_range_from_preset((string) $key) : [];
		}
		echo '<div class="wrap cmx-start"><div class="cmx-start-shell">';
		echo '<div class="cmx-start-top"><div class="cmx-start-title"><div class="cmx-start-icon">' . cmx_start_dashboard_icon_svg('layout-dashboard') . '</div><div><h1>Dashboard</h1><p class="cmx-start-sub">Übersicht der wichtigsten Kennzahlen Deines Unternehmens</p></div></div>';
		echo '<form class="cmx-start-filter" method="get"><input type="hidden" name="page" value="' . \esc_attr(cmx_start_dashboard_slug()) . '"><div><label><a class="cmx-start-filter-label-reset" href="' . \esc_url(\admin_url('index.php?page=' . cmx_start_dashboard_slug())) . '">Zeitraum</a></label><select id="cmx-start-dashboard-preset" name="cmx_cockpit_preset">';
		foreach (cmx_start_dashboard_preset_options() as $key => $label) {
			echo '<option value="' . \esc_attr((string) $key) . '"' . \selected((string) ($range['preset'] ?? ''), (string) $key, false) . '>' . \esc_html((string) $label) . '</option>';
		}
		echo '</select></div><div><label>Von</label><input type="date" id="cmx-start-dashboard-from" name="cmx_start_from" value="' . \esc_attr((string) ($range['from'] ?? '')) . '"></div><div><label>Bis</label><input type="date" id="cmx-start-dashboard-to" name="cmx_start_to" value="' . \esc_attr((string) ($range['to'] ?? '')) . '"></div><button class="cmx-start-button" type="submit"><span class="dashicons dashicons-filter"></span>Filter</button></form></div>';
		echo '<script>
			(function(){
				var form = document.querySelector(".cmx-start-filter");
				if (!form) return;
				var select = document.getElementById("cmx-start-dashboard-preset");
				var from = document.getElementById("cmx-start-dashboard-from");
				var to = document.getElementById("cmx-start-dashboard-to");
				var ranges = ' . \wp_json_encode($filter_ranges) . ';
				if (select) {
					select.addEventListener("change", function(){
						if (select.value === "individuell") return;
						var range = ranges[select.value] || null;
						if (range && from && to) {
							from.value = range.from || "";
							to.value = range.to || "";
						}
						if (form.requestSubmit) {
							form.requestSubmit();
						} else {
							form.submit();
						}
					});
				}
				[from, to].forEach(function(field){
					if (!field) return;
					field.addEventListener("change", function(){
						if (select) select.value = "individuell";
					});
				});
			})();
		</script>';
		$carent_enabled = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_carent_is_enabled') && cmx_cockpit_carent_is_enabled();
		echo '<div class="cmx-start-kpis">';
		cmx_start_dashboard_kpi('green', 'dashicons-chart-line', 'Umsatz', 'im gewählten Zeitraum', \number_format_i18n((int) ($data['umsatz_belege'] ?? 0)), cmx_start_dashboard_amount((float) $data['umsatz']), cmx_start_dashboard_paid_revenue_url());
		$open_receivables_url = (string) ($data['offene_forderungen_url'] ?? '');
		cmx_start_dashboard_kpi('blue', 'asset:gamepad-directional', 'Verbindlichkeiten', 'noch von Dir zu zahlen', \number_format_i18n((int) $data['offene_forderungen']), cmx_start_dashboard_optional_amount((float) $data['offene_forderungen_betrag']), $open_receivables_url !== '' ? $open_receivables_url : cmx_start_dashboard_supplier_invoices_url(), 'blue', 'red');
		$profit = (float) $data['gewinn'];
		cmx_start_dashboard_kpi($profit >= 0 ? 'green' : 'red', 'dashicons-money-alt', 'Gewinn / Verlust', 'im gewählten Zeitraum', cmx_start_dashboard_amount($profit));
		$open_belege_url = (string) ($data['offene_belege_url'] ?? '');
		cmx_start_dashboard_kpi('orange', 'dashicons-media-document', 'Offene Rechungen', 'Anzahl', \number_format_i18n((int) $data['offene_belege']), cmx_start_dashboard_optional_amount((float) $data['offene_belege_betrag']), $open_belege_url !== '' ? $open_belege_url : cmx_start_dashboard_open_belege_url(), '', 'red');
		cmx_start_dashboard_kpi('red', 'dashicons-warning', 'Offene Offerten', 'Anzahl', \number_format_i18n((int) $data['offene_offerten']), cmx_start_dashboard_optional_amount((float) $data['offene_offerten_betrag']), cmx_start_dashboard_open_offers_url());
		cmx_start_dashboard_kpi('green', 'dashicons-businessperson', 'Neue Kunden', 'im gewählten Zeitraum', \number_format_i18n((int) $data['neue_kunden']), cmx_start_dashboard_optional_amount((float) $data['neue_kunden_betrag']), cmx_start_dashboard_new_customers_url());
		if ($carent_enabled) {
			$carent = (array) ($data['carent'] ?? []);
			$carent_url = \admin_url('edit.php?post_type=carent&page=cmx-carent-dashboard');
			$carent_closed_url = \add_query_arg(['post_type' => 'carent', 'cmx_carent_status' => 'abgeschlossen'], \admin_url('edit.php'));
			$carent_open_url = \add_query_arg(['post_type' => 'carent', 'cmx_carent_status' => 'offen'], \admin_url('edit.php'));
			cmx_start_dashboard_kpi('green', 'dashicons-car', 'CaRent', 'abgeschlossen / offen', \number_format_i18n((int) ($carent['closed_count'] ?? 0)), \number_format_i18n((int) ($carent['open_count'] ?? 0)), $carent_url, 'blue', 'red', $carent_closed_url, $carent_open_url);
		}
		echo '</div>';
		echo '<section class="cmx-start-card cmx-start-section"><h2>Aktivitäten</h2><div class="cmx-start-grid">';
			if (\post_type_exists('buchungen')) {
				echo '<div class="cmx-start-activity-card">';
				cmx_start_dashboard_activity_heading('Buchungen', 'buchungen');
				cmx_start_dashboard_render_posts(cmx_start_dashboard_recent_posts('buchungen', 5), __NAMESPACE__ . '\\cmx_start_dashboard_booking_article_title');
				echo '</div>';
			}
		echo '<div class="cmx-start-activity-card">';
		cmx_start_dashboard_activity_heading('Dokumente', 'dokumente');
		cmx_start_dashboard_render_posts((array) $data['dokumente']);
		echo '</div><div class="cmx-start-activity-card">';
		cmx_start_dashboard_activity_heading('Artikel', 'artikel');
		cmx_start_dashboard_render_posts((array) $data['artikel']);
		echo '</div><div class="cmx-start-activity-card">';
		cmx_start_dashboard_activity_heading('Kontakte', 'kontakte', '<button type="button" class="cmx-start-activity-calendar" data-cmx-contact-reminders-toggle title="Erinnerungen anzeigen" aria-label="Erinnerungen anzeigen" aria-pressed="false">' . cmx_start_dashboard_icon_svg('calendar', 'cmx-start-calendar', 'dashicons-calendar-alt') . cmx_start_dashboard_icon_svg('calendar-heart', 'cmx-start-calendar-heart', 'dashicons-heart') . '</button>');
		cmx_start_dashboard_render_contact_activity((array) $data['kontakte'], (array) ($data['kontakte_erinnerungen'] ?? []));
		echo '</div><div class="cmx-start-activity-card">';
			cmx_start_dashboard_activity_heading('Belege', 'belege', '<button type="button" class="cmx-start-activity-calendar cmx-start-activity-toggle" data-cmx-belege-amounts-toggle title="Beträge anzeigen" aria-label="Beträge anzeigen" aria-pressed="false">' . cmx_start_dashboard_icon_svg('calendar', 'cmx-start-calendar', 'dashicons-calendar-alt') . cmx_start_dashboard_icon_svg('hand-coins', 'cmx-start-hand-coins', 'dashicons-money-alt') . '</button>');
			cmx_start_dashboard_render_belege_activity((array) $data['belege']);
		echo '</div><div class="cmx-start-activity-card">';
		cmx_start_dashboard_activity_heading('Projekte', 'projekte');
		cmx_start_dashboard_render_project_activity((array) $data['projekte']);
		echo '</div>';
		if ($carent_enabled) {
			echo '<div class="cmx-start-activity-card">';
			cmx_start_dashboard_activity_heading('CaRent', 'carent');
			cmx_start_dashboard_render_carent_activity((array) ($data['carent'] ?? []));
			echo '</div>';
		}
			echo '</div></section>';
			$chart_payload = cmx_start_dashboard_chart_payload();
			echo '<section class="cmx-start-card cmx-start-section"><h2>Finanzübersicht</h2><div class="cmx-start-chart-grid">';
				echo '<div class="cmx-start-chart-card"><div class="cmx-start-chart-title-row"><h3><button type="button" class="cmx-start-chart-title-toggle" id="cmx-start-income-expense-scope-toggle" title="Umsatz / Aufwand anzeigen" aria-label="Umsatz / Aufwand anzeigen" aria-pressed="false">Einnahmen / Ausgaben</button></h3><button type="button" class="cmx-start-chart-heading-toggle" id="cmx-start-income-expense-mode-toggle" title="Ausgaben nach oben anzeigen" aria-label="Ausgaben nach oben anzeigen" aria-pressed="false">' . cmx_start_dashboard_icon_svg('chart-bar-decreasing', 'cmx-start-chart-heading-icon', 'dashicons-chart-bar') . '</button></div><div class="cmx-start-chart-sub">im gewählten Zeitraum</div><div class="cmx-start-chart-box"><div class="cmx-start-chart-legend"><span class="cmx-start-chart-legend-item"><span class="cmx-start-chart-legend-dot" style="background:#10b981"></span><span id="cmx-start-income-expense-income-label">Einnahmen</span></span><span class="cmx-start-chart-legend-item"><span class="cmx-start-chart-legend-dot" style="background:#ef4444"></span><span id="cmx-start-income-expense-expense-label">Ausgaben</span></span></div><canvas id="cmx-start-income-expense-chart"></canvas></div></div>';
				echo '<div class="cmx-start-chart-card"><h3><button type="button" class="cmx-start-chart-title-toggle" id="cmx-start-revenue-scope-toggle" title="Aufwandentwicklung anzeigen" aria-label="Aufwandentwicklung anzeigen" aria-pressed="false">Umsatzentwicklung</button></h3><div class="cmx-start-chart-sub">Monatlich im Vergleich</div><div class="cmx-start-chart-box"><div class="cmx-start-chart-legend"><span class="cmx-start-chart-legend-item"><span class="cmx-start-chart-legend-dot" style="background:#0f7ad8"></span>Dieses Jahr</span><span class="cmx-start-chart-legend-item"><span class="cmx-start-chart-legend-dot" style="background:#b6c8e8"></span>Vorjahr</span></div><canvas id="cmx-start-revenue-chart"></canvas></div></div>';
			echo '<div class="cmx-start-chart-card"><div class="cmx-start-chart-title-row"><h3><a id="cmx-start-open-age-link" href="' . \esc_url(cmx_start_dashboard_open_receivables_url()) . '">Offene Rechnungen nach Alter</a></h3><button type="button" class="cmx-start-chart-toggle" id="cmx-start-open-age-toggle" title="Offene Lieferantenrechnungen anzeigen" aria-label="Offene Lieferantenrechnungen anzeigen" aria-pressed="false">' . cmx_start_dashboard_icon_svg('user-round-cog', 'cmx-start-chart-toggle-icon', 'dashicons-admin-users') . '</button></div><div class="cmx-start-chart-sub" id="cmx-start-open-age-total">Total ' . \esc_html(cmx_start_dashboard_amount((float) ($chart_payload['openTotal'] ?? 0.0))) . '</div><div class="cmx-start-chart-box"><canvas id="cmx-start-open-age-chart"></canvas></div></div>';
			echo '</div></section>';
		echo '</div></div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_enqueue_chartjs')) {
	function cmx_start_dashboard_enqueue_chartjs(): void {
		if (\function_exists(__NAMESPACE__ . '\\cmx_enqueue_chartjs')) {
			cmx_enqueue_chartjs();
			return;
		}
		if (\wp_script_is('cmx-chartjs', 'enqueued')) {
			return;
		}
		$plugin_main = \dirname(__DIR__, 2) . '/mis-buero.php';
		if (!\is_readable($plugin_main)) {
			$plugin_main = \dirname(__DIR__, 2) . '/cmx-misbuero.php';
		}
		$local_file = \dirname(__DIR__, 2) . '/assets/chart.umd.min.js';
		$chartjs_url = \is_readable($local_file)
			? \plugins_url('assets/chart.umd.min.js', $plugin_main)
			: 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
		\wp_register_script('cmx-chartjs', $chartjs_url, [], '4.4.1', true);
		\wp_enqueue_script('cmx-chartjs');
	}
}

\add_action('admin_menu', function (): void {
	if (cmx_start_dashboard_hidden_for_current_user()) {
		return;
	}

	\add_dashboard_page(
		'Start',
		'Start',
		cmx_start_dashboard_capability(),
		cmx_start_dashboard_slug(),
		__NAMESPACE__ . '\\cmx_start_dashboard_render'
	);
}, 1);

\add_action('admin_menu', function (): void {
	global $menu, $submenu;
	if (cmx_start_dashboard_hidden_for_current_user()) {
		\remove_menu_page('index.php');
		\remove_submenu_page('index.php', cmx_start_dashboard_slug());
		return;
	}

	if (isset($menu[2][0])) {
		$menu[2][0] = 'Start';
	}
	if (isset($menu[2][2])) {
		$menu[2][2] = 'index.php?page=' . cmx_start_dashboard_slug();
	}
	if (isset($menu[2][6])) {
		$menu[2][6] = cmx_start_dashboard_menu_icon();
	}
	if (isset($submenu['index.php']) && \is_array($submenu['index.php'])) {
		foreach ($submenu['index.php'] as $index => $item) {
			$slug = (string) ($item[2] ?? '');
			if ($slug === 'index.php' || $slug === 'update-core.php' || $slug === 'cmx-cockpit-dashboard') {
				unset($submenu['index.php'][$index]);
			}
		}
	}
}, 999);

\add_action('admin_init', function (): void {
	if (!\is_admin()) {
		return;
	}
	if (\wp_doing_ajax() || \wp_doing_cron()) {
		return;
	}

	$pagenow = (string) ($GLOBALS['pagenow'] ?? '');
	$page = isset($_GET['page']) ? \sanitize_key((string) \wp_unslash($_GET['page'])) : '';
	if (cmx_start_dashboard_hidden_for_current_user()) {
		if ($pagenow === 'index.php' || $page === cmx_start_dashboard_slug()) {
			\wp_safe_redirect(\admin_url('users.php'));
			exit;
		}
		return;
	}

	if (!\current_user_can(cmx_start_dashboard_capability())) {
		return;
	}
	if ($pagenow !== 'index.php' || $page !== '') {
		return;
	}

	\wp_safe_redirect(cmx_start_dashboard_url());
	exit;
}, 1);

\add_action('admin_enqueue_scripts', function (string $hook): void {
	if ($hook !== 'dashboard_page_' . cmx_start_dashboard_slug()) {
		return;
	}
	cmx_start_dashboard_enqueue_chartjs();
	\wp_register_style('cmx-start-dashboard', false, [], '1.0');
	\wp_enqueue_style('cmx-start-dashboard');
	\wp_add_inline_style('cmx-start-dashboard', cmx_start_dashboard_css());

	$payload = cmx_start_dashboard_chart_payload();
	$script = '(function(){
		var payload = ' . \wp_json_encode($payload) . ';
		var money = function(value){
			var number = Number(value || 0);
			return "CHF " + number.toLocaleString("de-CH", {minimumFractionDigits: 2, maximumFractionDigits: 2});
		};
		var formatNumber = function(value, decimals){
			var number = Number(value || 0);
			return number.toLocaleString("de-CH", {minimumFractionDigits: decimals, maximumFractionDigits: decimals});
		};
		var cmxStartDarkModeActive = function(){
			return document.documentElement && document.documentElement.classList.contains("cmx-dark-mode");
		};
		var cmxStartChartColors = function(){
			return cmxStartDarkModeActive()
				? {text: "#dbeafe", muted: "#a7b4c7", grid: "#334155", zero: "#475569", empty: "#334155"}
				: {text: "#111827", muted: "#64748b", grid: "#e5e7eb", zero: "#cbd5e1", empty: "#e5e7eb"};
		};
		var animateNumbers = function(){
			var items = document.querySelectorAll("[data-cmx-count-to]");
			if (!items.length) return;
			var duration = 950;
			var steps = 5;
			var currentStep = 0;
			items.forEach(function(item){
				item.textContent = formatNumber(0, parseInt(item.dataset.cmxCountDecimals || "0", 10));
			});
			var timer = window.setInterval(function(){
				currentStep++;
				var progress = Math.min(1, currentStep / steps);
				var eased = 1 - Math.pow(1 - progress, 4);
				items.forEach(function(item){
					var target = Number(item.dataset.cmxCountTo || 0);
					var decimals = parseInt(item.dataset.cmxCountDecimals || "0", 10);
					item.textContent = formatNumber(target * eased, decimals);
				});
				if (currentStep >= steps) {
					window.clearInterval(timer);
					items.forEach(function(item){
						var target = Number(item.dataset.cmxCountTo || 0);
						var decimals = parseInt(item.dataset.cmxCountDecimals || "0", 10);
						item.textContent = formatNumber(target, decimals);
					});
				}
			}, duration / steps);
		};
		animateNumbers();
		document.querySelectorAll("[data-cmx-contact-reminders-toggle]").forEach(function(button){
			var storageKey = "cmxStartKontaktActivityView";
			var card = button.closest(".cmx-start-activity-card");
			if (!card) return;
			var defaultList = card.querySelector(".cmx-start-list:not(.cmx-start-reminder-list)");
			var reminderList = card.querySelector(".cmx-start-reminder-list");
			if (!defaultList || !reminderList) return;
			var setContactActivityView = function(showReminders, persist){
				var card = button.closest(".cmx-start-activity-card");
				if (!card) return;
				card.classList.toggle("is-reminders", showReminders);
				defaultList.hidden = showReminders;
				reminderList.hidden = !showReminders;
				button.setAttribute("aria-pressed", showReminders ? "true" : "false");
				button.setAttribute("title", showReminders ? "Kontakte anzeigen" : "Erinnerungen anzeigen");
				button.setAttribute("aria-label", showReminders ? "Kontakte anzeigen" : "Erinnerungen anzeigen");
				if (persist) {
					try { window.localStorage.setItem(storageKey, showReminders ? "reminders" : "contacts"); } catch (err) {}
				}
			};
			var savedView = "";
			try { savedView = String(window.localStorage.getItem(storageKey) || ""); } catch (err) {}
			if (savedView === "reminders") {
				setContactActivityView(true, false);
			}
				button.addEventListener("click", function(){
					setContactActivityView(!card.classList.contains("is-reminders"), true);
				});
			});
			document.querySelectorAll("[data-cmx-belege-amounts-toggle]").forEach(function(button){
				var storageKey = "cmxStartBelegeActivityView";
				var card = button.closest(".cmx-start-activity-card");
				if (!card) return;
				var dates = card.querySelectorAll(".cmx-start-item-date");
				var amounts = card.querySelectorAll(".cmx-start-item-amount");
				if (!dates.length || !amounts.length) return;
				var setBelegeActivityView = function(showAmounts, persist){
					card.classList.toggle("is-amounts", showAmounts);
					dates.forEach(function(item){ item.hidden = showAmounts; });
					amounts.forEach(function(item){ item.hidden = !showAmounts; });
					button.setAttribute("aria-pressed", showAmounts ? "true" : "false");
					button.setAttribute("title", showAmounts ? "Datum anzeigen" : "Beträge anzeigen");
					button.setAttribute("aria-label", showAmounts ? "Datum anzeigen" : "Beträge anzeigen");
					if (persist) {
						try { window.localStorage.setItem(storageKey, showAmounts ? "amounts" : "dates"); } catch (err) {}
					}
				};
				var savedView = "";
				try { savedView = String(window.localStorage.getItem(storageKey) || ""); } catch (err) {}
				if (savedView === "amounts") {
					setBelegeActivityView(true, false);
				}
				button.addEventListener("click", function(){
					setBelegeActivityView(!card.classList.contains("is-amounts"), true);
				});
			});
			var legendShiftUpPlugin = {
				id: "cmxLegendShiftUp",
				afterLayout: function(chart, args, options){
					var offset = Number(options && options.offset ? options.offset : 0);
					if (!offset || !chart.legend) return;
					chart.legend.$cmxBaseTop = chart.legend.top;
					chart.legend.$cmxBaseBottom = chart.legend.bottom;
				},
				beforeDraw: function(chart, args, options){
					var offset = Number(options && options.offset ? options.offset : 0);
					if (!offset || !chart.legend) return;
					if (typeof chart.legend.$cmxBaseTop === "number") {
						chart.legend.top = chart.legend.$cmxBaseTop - offset;
					}
					if (typeof chart.legend.$cmxBaseBottom === "number") {
						chart.legend.bottom = chart.legend.$cmxBaseBottom - offset;
					}
				}
			};
		var zeroData = function(values){
			return (values || []).map(function(){ return 0; });
		};
		var animateDatasetValues = function(chart, targetSets){
			window.setTimeout(function(){
				targetSets.forEach(function(values, index){
					if (chart.data.datasets[index]) {
						chart.data.datasets[index].data = values || [];
					}
				});
				chart.update();
			}, 120);
		};
		var setupCanvas = function(canvas){
			if (!canvas || !canvas.getContext) return null;
			var box = canvas.getBoundingClientRect();
			var dpr = window.devicePixelRatio || 1;
			var width = Math.max(1, Math.round((box.width || canvas.clientWidth || 460) * dpr));
			var height = Math.max(1, Math.round((box.height || canvas.clientHeight || 230) * dpr));
			canvas.width = width;
			canvas.height = height;
			var ctx = canvas.getContext("2d");
			ctx.setTransform(1, 0, 0, 1, 0, 0);
			ctx.clearRect(0, 0, width, height);
			ctx.scale(dpr, dpr);
			return {ctx: ctx, width: width / dpr, height: height / dpr};
		};
		var drawBarFallback = function(canvas, labels, datasets){
			var surface = setupCanvas(canvas);
			if (!surface) return;
			var ctx = surface.ctx;
			var width = surface.width;
			var height = surface.height;
			var chartTop = 42;
			var chartRight = 18;
			var chartBottom = 28;
			var chartLeft = 44;
			var values = [];
			(datasets || []).forEach(function(set){
				(set.data || []).forEach(function(value){ values.push(Number(value || 0)); });
			});
			var rawMax = Math.max.apply(Math, values.concat([1]));
			var rawMin = Math.min.apply(Math, values.concat([0]));
			var niceMax = Math.max(1, Math.ceil(rawMax / 100) * 100);
			var niceMin = Math.min(0, Math.floor(rawMin / 100) * 100);
			if (niceMin === niceMax) {
				niceMin = 0;
			}
			var range = Math.max(1, niceMax - niceMin);
			var plotWidth = Math.max(1, width - chartLeft - chartRight);
			var plotHeight = Math.max(1, height - chartTop - chartBottom);
			var yForValue = function(value){
				return chartTop + ((niceMax - Number(value || 0)) / range) * plotHeight;
			};
			var zeroY = yForValue(0);
			ctx.font = "11px sans-serif";
			ctx.textAlign = "right";
			ctx.textBaseline = "middle";
			var chartColors = cmxStartChartColors();
			ctx.strokeStyle = chartColors.grid;
			ctx.fillStyle = chartColors.muted;
			for (var i = 0; i <= 4; i++) {
				var y = chartTop + (plotHeight / 4) * i;
				var tickValue = niceMax - (range / 4) * i;
				ctx.beginPath();
				ctx.moveTo(chartLeft, y);
				ctx.lineTo(width - chartRight, y);
				ctx.stroke();
				ctx.fillText((tickValue / 1000).toLocaleString("de-CH", {maximumFractionDigits: 1}) + "k", chartLeft - 8, y);
			}
			ctx.strokeStyle = chartColors.zero;
			ctx.beginPath();
			ctx.moveTo(chartLeft, zeroY);
			ctx.lineTo(width - chartRight, zeroY);
			ctx.stroke();
			var labelCount = Math.max(1, (labels || []).length);
			var groupWidth = plotWidth / labelCount;
			var setCount = Math.max(1, (datasets || []).length);
			var barWidth = Math.max(3, Math.min(18, (groupWidth * 0.6) / setCount));
			(datasets || []).forEach(function(set, setIndex){
				ctx.fillStyle = set.color || "#0f7ad8";
				(labels || []).forEach(function(label, index){
					var value = Number((set.data || [])[index] || 0);
					var valueY = yForValue(value);
					var barHeight = Math.abs(zeroY - valueY);
					if (barHeight <= 0 && value !== 0) barHeight = 2;
					var groupStart = chartLeft + index * groupWidth + (groupWidth - (barWidth * setCount + 4 * (setCount - 1))) / 2;
					var x = groupStart + setIndex * (barWidth + 4);
					var y = value >= 0 ? zeroY - barHeight : zeroY;
					ctx.fillRect(x, y, barWidth, barHeight);
				});
			});
			ctx.fillStyle = chartColors.muted;
			ctx.textAlign = "center";
			ctx.textBaseline = "top";
			(labels || []).forEach(function(label, index){
				if (labelCount > 8 && index % 2 === 1) return;
				ctx.fillText(String(label || ""), chartLeft + index * groupWidth + groupWidth / 2, chartTop + plotHeight + 8);
			});
		};
		var drawDoughnutFallback = function(canvas, labels, values, colors){
			var surface = setupCanvas(canvas);
			if (!surface) return;
			var ctx = surface.ctx;
			var width = surface.width;
			var height = surface.height;
			var total = (values || []).reduce(function(sum, value){ return sum + Math.max(0, Number(value || 0)); }, 0);
			var centerX = Math.max(92, width * 0.38);
			var centerY = height / 2 + 8;
			var radius = Math.min(width * 0.25, height * 0.34);
			var ring = Math.max(22, radius * 0.42);
			var start = -Math.PI / 2;
			if (total <= 0) {
				ctx.beginPath();
				ctx.arc(centerX, centerY, radius - ring / 2, 0, Math.PI * 2);
				ctx.lineWidth = ring;
				ctx.strokeStyle = cmxStartChartColors().empty;
				ctx.stroke();
			} else {
				(values || []).forEach(function(raw, index){
					var value = Math.max(0, Number(raw || 0));
					if (value <= 0) return;
					var end = start + (value / total) * Math.PI * 2;
					ctx.beginPath();
					ctx.arc(centerX, centerY, radius - ring / 2, start, end);
					ctx.lineWidth = ring;
					ctx.strokeStyle = colors[index] || "#0f7ad8";
					ctx.stroke();
					start = end;
				});
			}
			ctx.font = "12px sans-serif";
			ctx.textAlign = "left";
			ctx.textBaseline = "middle";
			(labels || []).forEach(function(label, index){
				var x = Math.min(width - 130, centerX + radius + 32);
				var y = centerY - 28 + index * 24;
				ctx.fillStyle = colors[index] || "#0f7ad8";
				ctx.beginPath();
				ctx.arc(x, y, 5, 0, Math.PI * 2);
				ctx.fill();
				ctx.fillStyle = cmxStartChartColors().muted;
				ctx.fillText(String(label || "") + " " + money((values || [])[index] || 0), x + 14, y);
			});
		};
		var commonOptions = {
			responsive: true,
			maintainAspectRatio: false,
				animation: {
					duration: 950,
					easing: "easeOutQuart"
				},
				layout: {padding: {top: 24}},
				plugins: {
					legend: {display: false},
					cmxLegendShiftUp: {offset: 3},
					tooltip: {callbacks: {label: function(context){ return (context.dataset.label ? context.dataset.label + ": " : "") + money(context.parsed.y || context.parsed); }}}
				},
			scales: {
				x: {grid: {display: false}, ticks: {color: cmxStartChartColors().text}},
				y: {grid: {color: cmxStartChartColors().grid}, ticks: {color: cmxStartChartColors().text, callback: function(value){ return (Number(value) / 1000).toLocaleString("de-CH") + "k"; }}}
			}
		};
		var applyBarChartTheme = function(chart){
			if (!chart || !chart.options || !chart.options.scales) return;
			var colors = cmxStartChartColors();
			if (chart.options.scales.x && chart.options.scales.x.ticks) {
				chart.options.scales.x.ticks.color = colors.text;
			}
			if (chart.options.scales.y) {
				if (chart.options.scales.y.ticks) chart.options.scales.y.ticks.color = colors.text;
				if (chart.options.scales.y.grid) chart.options.scales.y.grid.color = colors.grid;
			}
			chart.update("none");
		};
		var applyDoughnutChartTheme = function(chart){
			if (!chart || !chart.options || !chart.options.plugins || !chart.options.plugins.legend) return;
			var colors = cmxStartChartColors();
			chart.options.plugins.legend.labels = Object.assign({}, chart.options.plugins.legend.labels || {}, {color: colors.muted});
			chart.update("none");
		};
		var incomeExpense = document.getElementById("cmx-start-income-expense-chart");
		if (incomeExpense) {
			var incomeExpenseToggle = document.getElementById("cmx-start-income-expense-mode-toggle");
			var incomeExpenseScopeToggle = document.getElementById("cmx-start-income-expense-scope-toggle");
			var incomeExpenseIncomeLabel = document.getElementById("cmx-start-income-expense-income-label");
			var incomeExpenseExpenseLabel = document.getElementById("cmx-start-income-expense-expense-label");
			var incomeExpenseStorageKey = "cmxStartIncomeExpenseMode";
			var incomeExpenseScopeStorageKey = "cmxStartIncomeExpenseScope";
			var paidIncomeData = payload.income || [];
			var paidExpenseData = payload.expense || [];
			var allIncomeData = payload.allIncome || paidIncomeData;
			var allExpenseData = payload.allExpense || paidExpenseData;
			var incomeExpenseScope = "paid";
			try { incomeExpenseScope = String(window.localStorage.getItem(incomeExpenseScopeStorageKey) || "paid"); } catch (err) {}
			if (incomeExpenseScope !== "all") incomeExpenseScope = "paid";
			var incomeExpenseMode = "signed";
			try { incomeExpenseMode = String(window.localStorage.getItem(incomeExpenseStorageKey) || "signed"); } catch (err) {}
			if (incomeExpenseMode !== "up") incomeExpenseMode = "signed";
			var incomeDataForScope = function(){
				return incomeExpenseScope === "all" ? allIncomeData : paidIncomeData;
			};
			var rawExpenseDataForScope = function(){
				return incomeExpenseScope === "all" ? allExpenseData : paidExpenseData;
			};
			var expenseDataForMode = function(){
				var values = rawExpenseDataForScope();
				return incomeExpenseMode === "up"
					? (values || []).map(function(value){ return Math.abs(Number(value || 0)); })
					: values;
			};
			var syncIncomeExpenseScope = function(){
				if (!incomeExpenseScopeToggle) return;
				var all = incomeExpenseScope === "all";
				incomeExpenseScopeToggle.textContent = all ? "Umsatz / Aufwand" : "Einnahmen / Ausgaben";
				incomeExpenseScopeToggle.setAttribute("aria-pressed", all ? "true" : "false");
				incomeExpenseScopeToggle.setAttribute("title", all ? "Einnahmen / Ausgaben anzeigen" : "Umsatz / Aufwand anzeigen");
				incomeExpenseScopeToggle.setAttribute("aria-label", all ? "Einnahmen / Ausgaben anzeigen" : "Umsatz / Aufwand anzeigen");
				if (incomeExpenseIncomeLabel) incomeExpenseIncomeLabel.textContent = all ? "Umsatz" : "Einnahmen";
				if (incomeExpenseExpenseLabel) incomeExpenseExpenseLabel.textContent = all ? "Aufwand" : "Ausgaben";
			};
			var syncIncomeExpenseToggle = function(){
				if (!incomeExpenseToggle) return;
				var upward = incomeExpenseMode === "up";
				var expenseLabel = incomeExpenseScope === "all" ? "Aufwand" : "Ausgaben";
				incomeExpenseToggle.setAttribute("aria-pressed", upward ? "true" : "false");
				incomeExpenseToggle.setAttribute("title", upward ? expenseLabel + " nach unten anzeigen" : expenseLabel + " nach oben anzeigen");
				incomeExpenseToggle.setAttribute("aria-label", upward ? expenseLabel + " nach unten anzeigen" : expenseLabel + " nach oben anzeigen");
			};
			syncIncomeExpenseScope();
			syncIncomeExpenseToggle();
			var renderIncomeExpenseFallback = function(){
				drawBarFallback(incomeExpense, payload.labels || [], [{data: incomeDataForScope(), color: "#10b981"}, {data: expenseDataForMode(), color: "#ef4444"}]);
			};
			var incomeExpenseChart = null;
			if (window.Chart) {
				try {
					incomeExpenseChart = new Chart(incomeExpense, {
						type: "bar",
						data: {
							labels: payload.labels || [],
							datasets: [
								{label: incomeExpenseScope === "all" ? "Umsatz" : "Einnahmen", data: zeroData(incomeDataForScope()), backgroundColor: "#10b981", borderRadius: 4, barPercentage: 0.65, categoryPercentage: 0.7},
								{label: "Ausgaben", data: zeroData(expenseDataForMode()), backgroundColor: "#ef4444", borderRadius: 4, barPercentage: 0.65, categoryPercentage: 0.7}
							]
						},
						options: commonOptions,
						plugins: [legendShiftUpPlugin]
					});
					incomeExpenseChart.data.datasets[1].label = incomeExpenseScope === "all" ? "Aufwand" : "Ausgaben";
					animateDatasetValues(incomeExpenseChart, [incomeDataForScope(), expenseDataForMode()]);
				} catch (err) {
					incomeExpenseChart = null;
					renderIncomeExpenseFallback();
				}
			} else {
				renderIncomeExpenseFallback();
			}
			if (incomeExpenseToggle) {
				incomeExpenseToggle.addEventListener("click", function(){
					incomeExpenseMode = incomeExpenseMode === "up" ? "signed" : "up";
					try { window.localStorage.setItem(incomeExpenseStorageKey, incomeExpenseMode); } catch (err) {}
					syncIncomeExpenseToggle();
					if (incomeExpenseChart) {
						incomeExpenseChart.data.datasets[1].data = expenseDataForMode();
						incomeExpenseChart.update();
					} else {
						renderIncomeExpenseFallback();
					}
				});
			}
			if (incomeExpenseScopeToggle) {
				incomeExpenseScopeToggle.addEventListener("click", function(){
					incomeExpenseScope = incomeExpenseScope === "all" ? "paid" : "all";
					try { window.localStorage.setItem(incomeExpenseScopeStorageKey, incomeExpenseScope); } catch (err) {}
					syncIncomeExpenseScope();
					syncIncomeExpenseToggle();
					if (incomeExpenseChart) {
						incomeExpenseChart.data.datasets[0].label = incomeExpenseScope === "all" ? "Umsatz" : "Einnahmen";
						incomeExpenseChart.data.datasets[1].label = incomeExpenseScope === "all" ? "Aufwand" : "Ausgaben";
						incomeExpenseChart.data.datasets[0].data = incomeDataForScope();
						incomeExpenseChart.data.datasets[1].data = expenseDataForMode();
						incomeExpenseChart.update();
					} else {
						renderIncomeExpenseFallback();
					}
				});
			}
		}
		var revenue = document.getElementById("cmx-start-revenue-chart");
		if (revenue) {
			var revenueScopeToggle = document.getElementById("cmx-start-revenue-scope-toggle");
			var revenueStorageKey = "cmxStartRevenueTrendScope";
			var revenueScope = "revenue";
			try { revenueScope = String(window.localStorage.getItem(revenueStorageKey) || "revenue"); } catch (err) {}
			if (revenueScope !== "expense") revenueScope = "revenue";
			var revenueCurrentData = payload.current || [];
			var revenuePreviousData = payload.previous || [];
			var expenseCurrentData = payload.expenseCurrent || [];
			var expensePreviousData = payload.expensePrevious || [];
			function revenueCurrentDataForScope(){
				return revenueScope === "expense" ? expenseCurrentData : revenueCurrentData;
			}
			function revenuePreviousDataForScope(){
				return revenueScope === "expense" ? expensePreviousData : revenuePreviousData;
			}
			function syncRevenueScope(){
				if (!revenueScopeToggle) return;
				var expenseScope = revenueScope === "expense";
				revenueScopeToggle.textContent = expenseScope ? "Aufwandentwicklung" : "Umsatzentwicklung";
				revenueScopeToggle.setAttribute("aria-pressed", expenseScope ? "true" : "false");
				revenueScopeToggle.setAttribute("title", expenseScope ? "Umsatzentwicklung anzeigen" : "Aufwandentwicklung anzeigen");
				revenueScopeToggle.setAttribute("aria-label", expenseScope ? "Umsatzentwicklung anzeigen" : "Aufwandentwicklung anzeigen");
			}
			function renderRevenueFallback(){
				drawBarFallback(revenue, payload.labels || [], [{data: revenueCurrentDataForScope(), color: "#0f7ad8"}, {data: revenuePreviousDataForScope(), color: "#b6c8e8"}]);
			}
			syncRevenueScope();
			var revenueChart = null;
			if (window.Chart) {
				try {
					revenueChart = new Chart(revenue, {
						type: "bar",
						data: {
							labels: payload.labels || [],
							datasets: [
								{label: "Dieses Jahr", data: zeroData(revenueCurrentDataForScope()), backgroundColor: "#0f7ad8", borderRadius: 4, barPercentage: 0.65, categoryPercentage: 0.72},
								{label: "Vorjahr", data: zeroData(revenuePreviousDataForScope()), backgroundColor: "#b6c8e8", borderRadius: 4, barPercentage: 0.65, categoryPercentage: 0.72}
							]
						},
						options: commonOptions,
						plugins: [legendShiftUpPlugin]
					});
					animateDatasetValues(revenueChart, [revenueCurrentDataForScope(), revenuePreviousDataForScope()]);
				} catch (err) {
					revenueChart = null;
					renderRevenueFallback();
				}
			} else {
				renderRevenueFallback();
			}
			if (revenueScopeToggle) {
				revenueScopeToggle.addEventListener("click", function(){
					revenueScope = revenueScope === "expense" ? "revenue" : "expense";
					try { window.localStorage.setItem(revenueStorageKey, revenueScope); } catch (err) {}
					syncRevenueScope();
					if (revenueChart) {
						revenueChart.data.datasets[0].data = revenueCurrentDataForScope();
						revenueChart.data.datasets[1].data = revenuePreviousDataForScope();
						revenueChart.update();
					} else {
						renderRevenueFallback();
					}
				});
			}
		}
			var age = document.getElementById("cmx-start-open-age-chart");
			if (age) {
				var ageLink = document.getElementById("cmx-start-open-age-link");
				var ageTotal = document.getElementById("cmx-start-open-age-total");
				var ageToggle = document.getElementById("cmx-start-open-age-toggle");
				var ageStorageKey = "cmxStartOpenAgeView";
				var ageMode = "customer";
				try { ageMode = String(window.localStorage.getItem(ageStorageKey) || "customer"); } catch (err) {}
				if (ageMode !== "supplier") ageMode = "customer";
				var getAgeConfig = function(mode){
					if (mode === "supplier") {
						return {
							title: "Offene Lieferantenrechnungen nach Alter",
							total: Number(payload.supplierOpenTotal || 0),
							url: payload.supplierAgeUrl || "",
							labels: payload.supplierAgeLabels || [],
							values: payload.supplierAgeValues || [],
							toggleTitle: "Offene Kundenrechnungen anzeigen"
						};
					}
					return {
						title: "Offene Rechnungen nach Alter",
						total: Number(payload.openTotal || 0),
						url: payload.openAgeUrl || "",
						labels: payload.ageLabels || [],
						values: payload.ageValues || [],
						toggleTitle: "Offene Lieferantenrechnungen anzeigen"
					};
				};
				var ageConfig = getAgeConfig(ageMode);
				var ageOffset = function(values){
					return (values || []).map(function(value, index){
						return index === 2 && Number(value || 0) > 0 ? 30 : 0;
					});
				};
				var syncAgeHeader = function(config){
					if (ageLink) {
						ageLink.textContent = config.title;
						if (config.url) ageLink.setAttribute("href", config.url);
					}
					if (ageTotal) {
						ageTotal.textContent = "Total " + formatNumber(config.total, 2);
					}
					if (ageToggle) {
						var supplier = ageMode === "supplier";
						ageToggle.setAttribute("aria-pressed", supplier ? "true" : "false");
						ageToggle.setAttribute("title", config.toggleTitle);
						ageToggle.setAttribute("aria-label", config.toggleTitle);
					}
				};
				syncAgeHeader(ageConfig);
				var ageChart = null;
				if (window.Chart) {
					try {
						ageChart = new Chart(age, {
							type: "doughnut",
							data: {
								labels: ageConfig.labels,
								datasets: [{data: zeroData(ageConfig.values), backgroundColor: ["#10b981", "#f59e0b", "#ef4444"], borderWidth: 0, offset: [0, 0, 0]}]
							},
							options: {
							responsive: true,
							maintainAspectRatio: false,
							cutout: "58%",
							animation: {
								animateRotate: true,
								animateScale: true,
								duration: 950,
								easing: "easeOutQuart"
							},
							plugins: {
								legend: {position: "right", labels: {boxWidth: 12, boxHeight: 12, usePointStyle: true, pointStyle: "circle", color: cmxStartChartColors().muted}},
								tooltip: {callbacks: {label: function(context){ return context.label + ": " + money(context.parsed || 0); }}}
							}
							}
						});
						animateDatasetValues(ageChart, [ageConfig.values]);
						window.setTimeout(function(){
							ageChart.data.datasets[0].offset = ageOffset(ageConfig.values);
							ageChart.update();
						}, 1120);
					} catch (err) {
						ageChart = null;
					}
				}
				if (!ageChart) {
					drawDoughnutFallback(age, ageConfig.labels || [], ageConfig.values || [], ["#10b981", "#f59e0b", "#ef4444"]);
				}
				if (ageToggle) {
					ageToggle.addEventListener("click", function(){
						ageMode = ageMode === "supplier" ? "customer" : "supplier";
						try { window.localStorage.setItem(ageStorageKey, ageMode); } catch (err) {}
						ageConfig = getAgeConfig(ageMode);
						syncAgeHeader(ageConfig);
						if (ageChart) {
							ageChart.data.labels = ageConfig.labels;
							ageChart.data.datasets[0].offset = [0, 0, 0];
							ageChart.data.datasets[0].data = zeroData(ageConfig.values);
							ageChart.update();
							animateDatasetValues(ageChart, [ageConfig.values]);
							window.setTimeout(function(){
								ageChart.data.datasets[0].offset = ageOffset(ageConfig.values);
								ageChart.update();
							}, 1120);
						} else {
							drawDoughnutFallback(age, ageConfig.labels || [], ageConfig.values || [], ["#10b981", "#f59e0b", "#ef4444"]);
						}
					});
				}
			}
			window.addEventListener("cmx-dark-mode-change", function(){
				applyBarChartTheme(typeof incomeExpenseChart !== "undefined" ? incomeExpenseChart : null);
				applyBarChartTheme(typeof revenueChart !== "undefined" ? revenueChart : null);
				applyDoughnutChartTheme(typeof ageChart !== "undefined" ? ageChart : null);
				if (typeof incomeExpenseChart === "undefined" || !incomeExpenseChart) {
					if (typeof incomeExpense !== "undefined" && incomeExpense) {
						drawBarFallback(incomeExpense, payload.labels || [], [{data: incomeDataForScope(), color: "#10b981"}, {data: expenseDataForMode(), color: "#ef4444"}]);
					}
				}
				if (typeof revenueChart === "undefined" || !revenueChart) {
					if (typeof revenue !== "undefined" && revenue) {
						drawBarFallback(revenue, payload.labels || [], [{data: revenueCurrentDataForScope(), color: "#0f7ad8"}, {data: revenuePreviousDataForScope(), color: "#b6c8e8"}]);
					}
				}
				if (typeof ageChart === "undefined" || !ageChart) {
					if (typeof age !== "undefined" && age && typeof ageConfig !== "undefined") {
						drawDoughnutFallback(age, ageConfig.labels || [], ageConfig.values || [], ["#10b981", "#f59e0b", "#ef4444"]);
					}
				}
			});
		})();';
	\wp_add_inline_script('cmx-chartjs', $script, 'after');
});
