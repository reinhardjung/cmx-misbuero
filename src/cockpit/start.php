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
		$path = \dirname(__DIR__, 2) . '/assets/icons/layout-dashboard.svg';
		$svg = \is_readable($path) ? \file_get_contents($path) : '';
		if (!\is_string($svg) || \trim($svg) === '') {
			return 'dashicons-dashboard';
		}

		$svg = \str_replace('currentColor', '#a7aaad', $svg);
		return 'data:image/svg+xml;base64,' . \base64_encode($svg);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_range')) {
	function cmx_start_dashboard_range(): array {
		$preset = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_requested_preset') ? cmx_cockpit_requested_preset() : 'dieses_jahr';
		$range = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_range_from_preset')
			? (array) cmx_cockpit_range_from_preset($preset)
			: ['from' => \date('Y-01-01'), 'to' => \date('Y-12-31')];

		$from = isset($_GET['cmx_start_from']) ? \trim((string) \wp_unslash($_GET['cmx_start_from'])) : (string) ($range['from'] ?? '');
		$to = isset($_GET['cmx_start_to']) ? \trim((string) \wp_unslash($_GET['cmx_start_to'])) : (string) ($range['to'] ?? '');
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
	function cmx_start_dashboard_new_customer_ids(array $range): array {
		if (!\post_type_exists('kontakte')) {
			return [];
		}
		$args = [
			'post_type' => 'kontakte',
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
		return \array_values(\array_filter(\array_map('intval', (array) $query->posts)));
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
		if ((string) ($range['from'] ?? '') !== '' && (string) ($range['to'] ?? '') !== '') {
			$args['date_query'] = [[
				'after' => (string) $range['from'],
				'before' => (string) $range['to'],
				'inclusive' => true,
			]];
		}

		$query = new \WP_Query($args);
		$sum = 0.0;
		foreach ((array) $query->posts as $post_id) {
			$sum += cmx_start_dashboard_post_amount((int) $post_id);
		}

		return $sum;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_recent_posts')) {
	function cmx_start_dashboard_recent_posts(string $post_type, int $limit = 5): array {
		if (!\post_type_exists($post_type)) {
			return [];
		}
		return \get_posts([
			'post_type' => $post_type,
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => $limit,
			'orderby' => 'date',
			'order' => 'DESC',
		]);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_data')) {
	function cmx_start_dashboard_data(): array {
		$range = cmx_start_dashboard_range();
		$preset = (string) ($range['preset'] ?? 'dieses_jahr');
			$summary = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_overview_revenue_summary')
				? (array) cmx_cockpit_overview_revenue_summary($preset, 'overview')
				: [];
			$open = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_faellige_rechnungen_data')
				? (array) cmx_cockpit_faellige_rechnungen_data()
				: ['total' => 0, 'items' => []];
			$open_items = (array) ($open['items'] ?? []);
			$open_amount = cmx_start_dashboard_sum_open_items($open_items);
			$open_offers = cmx_start_dashboard_open_offers($range);
			$new_customer_ids = cmx_start_dashboard_new_customer_ids($range);
			$new_customer_revenue = cmx_start_dashboard_customer_revenue($new_customer_ids, $range);

			return [
				'range' => $range,
				'umsatz' => (float) ($summary['einnahmen'] ?? 0),
				'ausgaben' => (float) ($summary['ausgaben'] ?? 0),
				'gewinn' => (float) ($summary['gewinn'] ?? ((float) ($summary['einnahmen'] ?? 0) - (float) ($summary['ausgaben'] ?? 0))),
				'offene_forderungen' => (int) ($open['total'] ?? 0),
				'offene_forderungen_betrag' => $open_amount,
				'offene_belege' => (int) ($open['total'] ?? 0),
				'offene_belege_betrag' => $open_amount,
				'offene_offerten' => (int) ($open_offers['count'] ?? 0),
				'offene_offerten_betrag' => (float) ($open_offers['amount'] ?? 0.0),
				'neue_kunden' => \count($new_customer_ids),
				'neue_kunden_betrag' => $new_customer_revenue,
				'buchungen' => cmx_start_dashboard_recent_posts('buchungen', 5),
			'dokumente' => cmx_start_dashboard_recent_posts('dokumente', 5),
			'artikel' => cmx_start_dashboard_recent_posts('artikel', 5),
			'kontakte' => cmx_start_dashboard_recent_posts('kontakte', 5),
			'belege' => cmx_start_dashboard_recent_posts('belege', 5),
			'projekte' => cmx_start_dashboard_recent_posts('projekte', 5),
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
		$income = \array_values((array) (($monitor['income_series'][$year] ?? null) ?: \array_fill(0, 12, 0)));
		$expense = \array_map(static fn($value): float => -1 * \abs((float) $value), \array_values((array) (($monitor['expense_series'][$year] ?? null) ?: \array_fill(0, 12, 0))));
		$current_total = \array_values((array) (($monitor['series'][$year] ?? null) ?: \array_fill(0, 12, 0)));
		$previous_total = \array_values((array) (($monitor['series'][$previous_year] ?? null) ?: \array_fill(0, 12, 0)));

		$open = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_faellige_rechnungen_data')
			? (array) cmx_cockpit_faellige_rechnungen_data()
			: ['items' => []];
		$buckets = [
			'0 - 30 Tage' => ['amount' => 0.0, 'count' => 0],
			'30 - 60 Tage' => ['amount' => 0.0, 'count' => 0],
			'60+ Tage' => ['amount' => 0.0, 'count' => 0],
		];
		$today = (int) \strtotime(\current_time('Y-m-d') . ' 00:00:00');
		foreach ((array) ($open['items'] ?? []) as $row) {
			$post_id = (int) ($row['id'] ?? 0);
			$amount = $post_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_cockpit_mahnwesen_amount_value')
				? (float) cmx_cockpit_mahnwesen_amount_value($post_id)
				: 0.0;
			$due_ts = (int) ($row['due_ts'] ?? 0);
			$age = ($today > 0 && $due_ts > 0) ? (int) \floor(\max(0, $today - $due_ts) / DAY_IN_SECONDS) : 0;
			$key = $age > 60 ? '60+ Tage' : ($age > 30 ? '30 - 60 Tage' : '0 - 30 Tage');
			$buckets[$key]['amount'] += $amount;
			$buckets[$key]['count']++;
		}
		$age_labels = \array_keys($buckets);
		$age_values = \array_map(static fn(array $bucket): float => \round((float) ($bucket['amount'] ?? 0), 2), \array_values($buckets));
		$age_counts = \array_map(static fn(array $bucket): int => (int) ($bucket['count'] ?? 0), \array_values($buckets));

		return [
			'labels' => $labels,
			'income' => \array_map('floatval', $income),
			'expense' => \array_map('floatval', $expense),
			'current' => \array_map('floatval', $current_total),
			'previous' => \array_map('floatval', $previous_total),
			'ageLabels' => $age_labels,
			'ageValues' => $age_values,
			'ageCounts' => $age_counts,
			'openTotal' => \array_sum($age_values),
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
			.cmx-start-filter{display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap}.cmx-start-filter label{display:block;font-weight:800;font-size:12px;margin-bottom:6px}
			.cmx-start-filter select,.cmx-start-filter input[type=date]{min-height:38px;border:1px solid #d7dde8;border-radius:6px;background:#fff;padding:4px 10px;min-width:150px}.cmx-start-button{min-height:38px;border:1px solid #d7dde8;border-radius:6px;background:#fff;color:#111827;text-decoration:none;display:inline-flex;align-items:center;gap:8px;padding:0 14px;font-weight:800}
			.cmx-start-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin-bottom:16px}.cmx-start-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 24px rgba(15,23,42,.06)}
			.cmx-start-kpi{padding:20px;min-height:148px;display:flex;flex-direction:column;justify-content:space-between}.cmx-start-kpi-head{display:flex;gap:14px;align-items:flex-start}.cmx-start-kpi .dashicons,.cmx-start-kpi-icon{font-size:40px;width:40px;height:40px;display:block;flex:0 0 auto}.cmx-start-kpi-icon--plain{width:34px;height:34px}.cmx-start-kpi-title{font-weight:800}.cmx-start-kpi-sub{font-size:12px;color:#475569;margin-top:4px}.cmx-start-kpi-values{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;margin-top:14px}.cmx-start-kpi-value{font-size:24px;font-weight:900}.cmx-start-kpi-amount{font-size:20px;font-weight:900;text-align:right;white-space:nowrap;color:#111827}
			.cmx-start-green{color:#16a34a}.cmx-start-blue{color:#0f6aa8}.cmx-start-purple{color:#6d28d9}.cmx-start-orange{color:#d97706}.cmx-start-red{color:#dc2626}
			.cmx-start-section{padding:18px;margin-bottom:16px}.cmx-start-section h2{font-size:18px;margin:0 0 14px;font-weight:900}.cmx-start-grid{display:grid;grid-template-columns:repeat(6,minmax(210px,1fr));gap:12px;overflow-x:auto;padding-bottom:2px}.cmx-start-activity-card{border:1px solid #e2e8f0;border-radius:10px;padding:16px;background:#fff;min-height:230px}.cmx-start-activity-card h3{font-size:15px;margin:0 0 14px;font-weight:900}.cmx-start-list{margin:0;padding:0;list-style:none}.cmx-start-list li{display:block;border-bottom:1px solid #eef2f7;padding:10px 0}.cmx-start-list li:last-child{border-bottom:0}.cmx-start-item-title{font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.cmx-start-item-meta{font-size:12px;color:#64748b;margin-top:3px;white-space:nowrap}
			.cmx-start-chart-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}.cmx-start-chart-card{border:1px solid #e2e8f0;border-radius:10px;padding:16px;min-height:310px}.cmx-start-chart-card h3{font-size:14px;margin:0 0 4px;font-weight:900}.cmx-start-chart-sub{font-size:12px;color:#475569;margin-bottom:12px}.cmx-start-chart-box{position:relative;height:230px}.cmx-start-chart-box canvas{width:100%!important;height:230px!important}
			@media(max-width:1400px){.cmx-start-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.cmx-start-chart-grid{grid-template-columns:1fr}}@media(max-width:900px){.cmx-start-top{display:block}.cmx-start-filter{margin-top:16px}.cmx-start-kpis{grid-template-columns:1fr}}
		';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_kpi')) {
	function cmx_start_dashboard_kpi(string $color, string $icon, string $title, string $sub, string $value, string $amount = ''): void {
		$icon_name = \str_starts_with($icon, 'asset:') ? \substr($icon, 6) : '';
		$icon_class = ($icon_name === 'gamepad-directional') ? 'cmx-start-kpi-icon cmx-start-kpi-icon--plain cmx-start-' . $color : 'cmx-start-kpi-icon cmx-start-' . $color;
		$icon_html = \str_starts_with($icon, 'asset:')
			? cmx_start_dashboard_icon_svg($icon_name, $icon_class, 'dashicons-admin-generic')
			: '<span class="dashicons ' . \esc_attr($icon) . ' cmx-start-' . \esc_attr($color) . '"></span>';
		echo '<section class="cmx-start-card cmx-start-kpi">';
		echo '<div class="cmx-start-kpi-head">' . $icon_html . '<div><div class="cmx-start-kpi-title">' . \esc_html($title) . '</div><div class="cmx-start-kpi-sub">' . \esc_html($sub) . '</div></div></div>';
		echo '<div class="cmx-start-kpi-values"><div class="cmx-start-kpi-value cmx-start-' . \esc_attr($color) . '">' . \esc_html($value) . '</div>';
		if ($amount !== '') {
			echo '<div class="cmx-start-kpi-amount">' . \esc_html($amount) . '</div>';
		}
		echo '</div>';
		echo '</section>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_render_posts')) {
	function cmx_start_dashboard_render_posts(array $posts): void {
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
			echo '<li><div><div class="cmx-start-item-title"><a href="' . \esc_url($url) . '">' . \esc_html(\get_the_title($post)) . '</a></div><div class="cmx-start-item-meta">' . \esc_html(\get_the_date('d.m.Y', $post)) . '</div></div></li>';
		}
		echo '</ul>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_render')) {
	function cmx_start_dashboard_render(): void {
		if (!\current_user_can(cmx_start_dashboard_capability())) {
			\wp_die('Keine Berechtigung.');
		}
		$data = cmx_start_dashboard_data();
		$range = (array) ($data['range'] ?? []);
		echo '<div class="wrap cmx-start"><div class="cmx-start-shell">';
		echo '<div class="cmx-start-top"><div class="cmx-start-title"><div class="cmx-start-icon">' . cmx_start_dashboard_icon_svg('layout-dashboard') . '</div><div><h1>Dashboard</h1><p class="cmx-start-sub">Übersicht der wichtigsten Kennzahlen Ihres Unternehmens</p></div></div>';
		echo '<form class="cmx-start-filter" method="get"><input type="hidden" name="page" value="' . \esc_attr(cmx_start_dashboard_slug()) . '"><div><label>Zeitraum</label><select name="cmx_cockpit_preset">';
		foreach ((array) cmx_cockpit_preset_options() as $key => $label) {
			echo '<option value="' . \esc_attr((string) $key) . '"' . \selected((string) ($range['preset'] ?? ''), (string) $key, false) . '>' . \esc_html((string) $label) . '</option>';
		}
		echo '</select></div><div><label>Von</label><input type="date" name="cmx_start_from" value="' . \esc_attr((string) ($range['from'] ?? '')) . '"></div><div><label>Bis</label><input type="date" name="cmx_start_to" value="' . \esc_attr((string) ($range['to'] ?? '')) . '"></div><button class="cmx-start-button" type="submit"><span class="dashicons dashicons-filter"></span>Filter</button></form></div>';
		echo '<div class="cmx-start-kpis">';
		cmx_start_dashboard_kpi('green', 'dashicons-chart-line', 'Umsatz', 'im gewählten Zeitraum', cmx_start_dashboard_amount((float) $data['umsatz']));
		cmx_start_dashboard_kpi('blue', 'asset:gamepad-directional', 'Offene Forderungen', 'Anzahl offener Rechnungen', \number_format_i18n((int) $data['offene_forderungen']), cmx_start_dashboard_optional_amount((float) $data['offene_forderungen_betrag']));
		$profit = (float) $data['gewinn'];
		cmx_start_dashboard_kpi($profit >= 0 ? 'green' : 'red', 'dashicons-money-alt', 'Gewinn / Verlust', 'im gewählten Zeitraum', cmx_start_dashboard_amount($profit));
		cmx_start_dashboard_kpi('orange', 'dashicons-media-document', 'Offene Belege', 'Anzahl', \number_format_i18n((int) $data['offene_belege']), cmx_start_dashboard_optional_amount((float) $data['offene_belege_betrag']));
		cmx_start_dashboard_kpi('red', 'dashicons-warning', 'Offene Offerten', 'Anzahl', \number_format_i18n((int) $data['offene_offerten']), cmx_start_dashboard_optional_amount((float) $data['offene_offerten_betrag']));
		cmx_start_dashboard_kpi('green', 'dashicons-businessperson', 'Neue Kunden', 'im gewählten Zeitraum', \number_format_i18n((int) $data['neue_kunden']), cmx_start_dashboard_optional_amount((float) $data['neue_kunden_betrag']));
		echo '</div>';
		echo '<section class="cmx-start-card cmx-start-section"><h2>Finanzübersicht</h2><div class="cmx-start-chart-grid">';
		echo '<div class="cmx-start-chart-card"><h3>Einnahmen / Ausgaben</h3><div class="cmx-start-chart-sub">im gewählten Zeitraum</div><div class="cmx-start-chart-box"><canvas id="cmx-start-income-expense-chart"></canvas></div></div>';
		echo '<div class="cmx-start-chart-card"><h3>Umsatzentwicklung</h3><div class="cmx-start-chart-sub">Monatlich im Vergleich</div><div class="cmx-start-chart-box"><canvas id="cmx-start-revenue-chart"></canvas></div></div>';
		echo '<div class="cmx-start-chart-card"><h3>Offene Rechnungen nach Alter</h3><div class="cmx-start-chart-sub">Total ' . \esc_html(cmx_start_dashboard_money((float) cmx_start_dashboard_chart_payload()['openTotal'])) . '</div><div class="cmx-start-chart-box"><canvas id="cmx-start-open-age-chart"></canvas></div></div>';
		echo '</div></section>';
		echo '<section class="cmx-start-card cmx-start-section"><h2>Aktivitäten</h2><div class="cmx-start-grid"><div class="cmx-start-activity-card"><h3>Buchungen</h3>';
		cmx_start_dashboard_render_posts((array) $data['buchungen']);
		echo '</div><div class="cmx-start-activity-card"><h3>Dokumente</h3>';
		cmx_start_dashboard_render_posts((array) $data['dokumente']);
		echo '</div><div class="cmx-start-activity-card"><h3>Artikel</h3>';
		cmx_start_dashboard_render_posts((array) $data['artikel']);
		echo '</div><div class="cmx-start-activity-card"><h3>Kontakte</h3>';
		cmx_start_dashboard_render_posts((array) $data['kontakte']);
		echo '</div><div class="cmx-start-activity-card"><h3>Belege</h3>';
		cmx_start_dashboard_render_posts((array) $data['belege']);
		echo '</div><div class="cmx-start-activity-card"><h3>Projekte</h3>';
		cmx_start_dashboard_render_posts((array) $data['projekte']);
		echo '</div></div></section>';
		echo '</div></div>';
	}
}

\add_action('admin_menu', function (): void {
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
	if (isset($menu[2][0])) {
		$menu[2][0] = 'Start';
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

\add_action('load-index.php', function (): void {
	if (empty($_GET['page'])) {
		\wp_safe_redirect(\admin_url('index.php?page=' . cmx_start_dashboard_slug()));
		exit;
	}
});

\add_action('admin_enqueue_scripts', function (string $hook): void {
	if ($hook !== 'dashboard_page_' . cmx_start_dashboard_slug()) {
		return;
	}
	if (\function_exists(__NAMESPACE__ . '\\cmx_enqueue_chartjs')) {
		cmx_enqueue_chartjs();
	}
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
		var commonOptions = {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				legend: {position: "top", align: "start", labels: {boxWidth: 12, boxHeight: 12, usePointStyle: true, pointStyle: "rectRounded"}},
				tooltip: {callbacks: {label: function(context){ return (context.dataset.label ? context.dataset.label + ": " : "") + money(context.parsed.y || context.parsed); }}}
			},
			scales: {
				x: {grid: {display: false}, ticks: {color: "#111827"}},
				y: {grid: {color: "#e5e7eb"}, ticks: {color: "#111827", callback: function(value){ return (Number(value) / 1000).toLocaleString("de-CH") + "k"; }}}
			}
		};
		var incomeExpense = document.getElementById("cmx-start-income-expense-chart");
		if (incomeExpense && window.Chart) {
			new Chart(incomeExpense, {
				type: "bar",
				data: {
					labels: payload.labels || [],
					datasets: [
						{label: "Einnahmen", data: payload.income || [], backgroundColor: "#10b981", borderRadius: 4, barPercentage: 0.65, categoryPercentage: 0.7},
						{label: "Ausgaben", data: payload.expense || [], backgroundColor: "#ef4444", borderRadius: 4, barPercentage: 0.65, categoryPercentage: 0.7}
					]
				},
				options: commonOptions
			});
		}
		var revenue = document.getElementById("cmx-start-revenue-chart");
		if (revenue && window.Chart) {
			new Chart(revenue, {
				type: "bar",
				data: {
					labels: payload.labels || [],
					datasets: [
						{label: "Dieses Jahr", data: payload.current || [], backgroundColor: "#0f7ad8", borderRadius: 4, barPercentage: 0.65, categoryPercentage: 0.72},
						{label: "Vorjahr", data: payload.previous || [], backgroundColor: "#b6c8e8", borderRadius: 4, barPercentage: 0.65, categoryPercentage: 0.72}
					]
				},
				options: commonOptions
			});
		}
		var age = document.getElementById("cmx-start-open-age-chart");
		if (age && window.Chart) {
			new Chart(age, {
				type: "doughnut",
				data: {
					labels: payload.ageLabels || [],
					datasets: [{data: payload.ageValues || [], backgroundColor: ["#10b981", "#f59e0b", "#ef4444"], borderWidth: 0}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					cutout: "58%",
					plugins: {
						legend: {position: "right", labels: {boxWidth: 12, boxHeight: 12, usePointStyle: true, pointStyle: "circle"}},
						tooltip: {callbacks: {label: function(context){ return context.label + ": " + money(context.parsed || 0); }}}
					}
				}
			});
		}
	})();';
	\wp_add_inline_script('cmx-chartjs', $script, 'after');
});
