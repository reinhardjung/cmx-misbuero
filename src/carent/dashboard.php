<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_capability')) {
	function cmx_carent_dashboard_capability(): string {
		$obj = \get_post_type_object('carent');
		$cap = $obj ? (string) ($obj->cap->edit_posts ?? 'edit_posts') : 'edit_posts';
		return $cap !== '' ? $cap : 'edit_posts';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_meta_key')) {
	function cmx_carent_dashboard_meta_key(string $constant, string $fallback): string {
		return \defined(__NAMESPACE__ . '\\' . $constant)
			? (string) \constant(__NAMESPACE__ . '\\' . $constant)
			: $fallback;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_vehicle_articles_url')) {
	function cmx_carent_dashboard_vehicle_articles_url(): string {
		$post_type = \function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_post_type')
			? (string) cmx_carent_fahrzeug_post_type()
			: 'artikel';
		if ($post_type === '' || !\post_type_exists($post_type)) {
			$post_type = 'artikel';
		}

		$args = ['post_type' => $post_type];
		$taxonomy = '';
		if (\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_TYPEN')) {
			$taxonomy = (string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_TYPEN');
		}
		if ($taxonomy === '' && \function_exists(__NAMESPACE__ . '\\cmx_tax_typen')) {
			$taxonomy = (string) cmx_tax_typen();
		}
		if ($taxonomy === '' && \function_exists(__NAMESPACE__ . '\\cmx_artikel_taxonomy_slug')) {
			$taxonomy = (string) cmx_artikel_taxonomy_slug('Typen');
		}

		if ($taxonomy !== '' && \taxonomy_exists($taxonomy)) {
			$term = false;
			foreach (['autovermietung', 'mietfahrzeug'] as $slug) {
				$term = \get_term_by('slug', $slug, $taxonomy);
				if ($term && !\is_wp_error($term)) {
					break;
				}
			}
			if (!$term || \is_wp_error($term)) {
				foreach (['Autovermietung', 'Mietfahrzeug'] as $name) {
					$term = \get_term_by('name', $name, $taxonomy);
					if ($term && !\is_wp_error($term)) {
						break;
					}
				}
			}
			if ($term && !\is_wp_error($term)) {
				$args[$taxonomy] = (string) $term->slug;
			}
		}

		return \add_query_arg($args, \admin_url('edit.php'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_date_from_request')) {
	function cmx_carent_dashboard_date_from_request(string $key, string $fallback): string {
		$value = isset($_GET[$key]) ? \trim((string) \wp_unslash($_GET[$key])) : '';
		if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && \strtotime($value) !== false) {
			return $value;
		}

		return $fallback;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_preset_options')) {
	function cmx_carent_dashboard_preset_options(): array {
		return [
			'heute'                => 'Heute (heute bis heute)',
			'diesen_monat'         => 'Diesen Monat',
			'letzten_monat'        => 'Letzten Monat',
			'vorletzten_monat'     => 'Vorletzten Monat',
			'dieses_quartal'       => 'Dieses Quartal',
			'letztes_quartal'      => 'Letztes Quartal',
			'vorletztes_quartal'   => 'Vorletztes Quartal',
			'dieses_jahr'          => 'Dieses Jahr',
			'letztes_jahr'         => 'Letztes Jahr',
			'vorletztes_jahr'      => 'Vorletztes Jahr',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_requested_preset')) {
	function cmx_carent_dashboard_requested_preset(): string {
		$preset = isset($_GET['cmx_range']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_range'])) : 'diesen_monat';
		$legacy_map = [
			'today'        => 'heute',
			'this_month'   => 'diesen_monat',
			'this_quarter' => 'dieses_quartal',
			'this_year'    => 'dieses_jahr',
		];
		$preset = (string) ($legacy_map[$preset] ?? $preset);
		$options = cmx_carent_dashboard_preset_options();

		return isset($options[$preset]) ? $preset : 'diesen_monat';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_preset_range')) {
	function cmx_carent_dashboard_preset_range(string $preset): array {
		$now = (int) \current_time('timestamp');
		if ($now <= 0) {
			$now = \time();
		}

		switch ($preset) {
			case 'heute':
				return [\date('Y-m-d', $now), \date('Y-m-d', $now)];

			case 'letzten_monat':
				$base = \strtotime('first day of last month', $now) ?: $now;
				return [\date('Y-m-01', $base), \date('Y-m-t', $base)];

			case 'vorletzten_monat':
				$base = \strtotime('first day of -2 months', $now) ?: $now;
				return [\date('Y-m-01', $base), \date('Y-m-t', $base)];

			case 'dieses_quartal':
				$month = (int) \date('n', $now);
				$year = (int) \date('Y', $now);
				$quarter_start_month = ((int) \floor(($month - 1) / 3) * 3) + 1;
				$quarter_end_month = $quarter_start_month + 2;
				$from = \sprintf('%04d-%02d-01', $year, $quarter_start_month);
				$to = \date('Y-m-t', \strtotime(\sprintf('%04d-%02d-01', $year, $quarter_end_month)) ?: $now);
				return [$from, $to];

			case 'letztes_quartal':
			case 'vorletztes_quartal':
				$month = (int) \date('n', $now);
				$year = (int) \date('Y', $now);
				$quarter_start_month = ((int) \floor(($month - 1) / 3) * 3) + 1;
				$offset = $preset === 'letztes_quartal' ? -3 : -6;
				$start_ts = \strtotime($offset . ' months', \strtotime(\sprintf('%04d-%02d-01', $year, $quarter_start_month)) ?: $now) ?: $now;
				$end_ts = \strtotime('+2 months', $start_ts) ?: $start_ts;
				return [\date('Y-m-01', $start_ts), \date('Y-m-t', $end_ts)];

			case 'dieses_jahr':
				return [\date('Y-01-01', $now), \date('Y-12-31', $now)];

			case 'letztes_jahr':
				return [\date('Y-01-01', \strtotime('-1 year', $now) ?: $now), \date('Y-12-31', \strtotime('-1 year', $now) ?: $now)];

			case 'vorletztes_jahr':
				return [\date('Y-01-01', \strtotime('-2 years', $now) ?: $now), \date('Y-12-31', \strtotime('-2 years', $now) ?: $now)];

			case 'diesen_monat':
			default:
				return [\date('Y-m-01', $now), \date('Y-m-t', $now)];
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_range')) {
	function cmx_carent_dashboard_range(): array {
		$preset = cmx_carent_dashboard_requested_preset();
		[$default_from, $default_to] = cmx_carent_dashboard_preset_range($preset);
		$from = cmx_carent_dashboard_date_from_request('cmx_from', $default_from);
		$to = cmx_carent_dashboard_date_from_request('cmx_to', $default_to);

		if (\strtotime($from) > \strtotime($to)) {
			[$from, $to] = [$to, $from];
		}

		return [$from, $to];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_clean_date')) {
	function cmx_carent_dashboard_clean_date(mixed $value): string {
		$value = \trim((string) $value);
		if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && \strtotime($value) !== false) {
			return $value;
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_int_meta')) {
	function cmx_carent_dashboard_int_meta(int $post_id, string $meta_key): int {
		$value = \trim((string) \get_post_meta($post_id, $meta_key, true));
		if ($value === '') {
			return 0;
		}

		$value = \preg_replace('/[^\d-]+/', '', $value);
		return (int) $value;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_days_inclusive')) {
	function cmx_carent_dashboard_days_inclusive(string $from, string $to): int {
		$from_ts = \strtotime($from . ' 00:00:00');
		$to_ts = \strtotime($to . ' 00:00:00');
		if ($from_ts === false || $to_ts === false || $to_ts < $from_ts) {
			return 0;
		}

		return (int) \floor(($to_ts - $from_ts) / DAY_IN_SECONDS) + 1;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_format_date')) {
	function cmx_carent_dashboard_format_date(string $date): string {
		$timestamp = \strtotime($date . ' 00:00:00');
		return $timestamp !== false ? (string) \date_i18n('d.m.Y', $timestamp) : $date;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_vehicle_label')) {
	function cmx_carent_dashboard_vehicle_label(int $article_id, int $carent_id): string {
		if ($article_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_carent_admin_article_label')) {
			$label = \trim((string) cmx_carent_admin_article_label($article_id, $carent_id));
			if ($label !== '') {
				return $label;
			}
		}

		return $article_id > 0 ? \trim((string) \get_the_title($article_id)) : 'Ohne Fahrzeug';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_vehicle_image')) {
	function cmx_carent_dashboard_vehicle_image(int $article_id): string {
		if ($article_id <= 0) {
			return '<span class="dashicons dashicons-car"></span>';
		}

		$image_url = '';
		if (\function_exists(__NAMESPACE__ . '\\cmx_artikel_admin_image_src')) {
			$image_url = \trim((string) cmx_artikel_admin_image_src($article_id));
		}
		if ($image_url === '') {
			$image_url = \trim((string) \get_post_meta($article_id, '_cmx_local_image_artikel_url', true));
		}
		if ($image_url !== '') {
			return '<img class="cmx-carent-dashboard-vehicle-image" src="' . \esc_url($image_url) . '" alt="">';
		}
		if (\has_post_thumbnail($article_id)) {
			return (string) \get_the_post_thumbnail($article_id, 'thumbnail', [
				'class' => 'cmx-carent-dashboard-vehicle-image',
				'alt'   => '',
			]);
		}

		return '<span class="dashicons dashicons-car"></span>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_vehicle_article_ids')) {
	function cmx_carent_dashboard_vehicle_article_ids(): array {
		$ids = \function_exists(__NAMESPACE__ . '\\cmx_carent_admin_used_article_ids')
			? (array) cmx_carent_admin_used_article_ids()
			: [];

		$meta_keys = [
			\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_CHASSI_NR') ? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_CHASSI_NR') : '_cmx_artikel_carent_chassi_nr',
			\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KENNZEICHEN') ? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KENNZEICHEN') : '_cmx_artikel_carent_kennzeichen',
			\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KM_STAND') ? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KM_STAND') : '_cmx_artikel_carent_km_stand',
		];
		$meta_query = ['relation' => 'OR'];
		foreach (\array_values(\array_unique($meta_keys)) as $meta_key) {
			$meta_query[] = [
				'key'     => $meta_key,
				'value'   => '',
				'compare' => '!=',
			];
		}

		$article_ids = \get_posts([
			'post_type'              => 'artikel',
			'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => $meta_query,
		]);

		$ids = \array_merge($ids, \is_array($article_ids) ? $article_ids : []);
		return \array_values(\array_unique(\array_filter(\array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_color')) {
	function cmx_carent_dashboard_color(float $value, bool $inverse = false): string {
		if ($inverse) {
			if ($value <= 7) {
				return '#16a34a';
			}
			if ($value <= 14) {
				return '#f59e0b';
			}
			return '#ef4444';
		}

		if ($value >= 75) {
			return '#16a34a';
		}
		if ($value >= 50) {
			return '#f59e0b';
		}

		return '#ef4444';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_enqueue_chartjs')) {
	function cmx_carent_dashboard_enqueue_chartjs(): void {
		if (\function_exists(__NAMESPACE__ . '\\cmx_enqueue_chartjs')) {
			cmx_enqueue_chartjs();
			return;
		}

		if (\wp_script_is('cmx-chartjs', 'enqueued')) {
			return;
		}

		$plugin_main = \dirname(__DIR__, 2) . '/cmx-misbuero.php';
		$local = \plugins_url('vendor/mikuspetr/chartjs/chart.umd.min.js', $plugin_main);
		\wp_register_script('cmx-chartjs', $local, [], '4.4.1', true);
		\wp_enqueue_script('cmx-chartjs');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_collect_data')) {
	function cmx_carent_dashboard_collect_data(string $from, string $to): array {
		$range_days = \max(1, cmx_carent_dashboard_days_inclusive($from, $to));
		$from_ts = \strtotime($from . ' 00:00:00');
		$to_ts = \strtotime($to . ' 00:00:00');
		$from_ts = $from_ts !== false ? $from_ts : 0;
		$to_ts = $to_ts !== false ? $to_ts : $from_ts;

		$start_key = cmx_carent_dashboard_meta_key('CMX_CARENT_UEBERNAHME_DATUM_META', '_cmx_carent_uebernahme_datum');
		$end_key = cmx_carent_dashboard_meta_key('CMX_CARENT_RUECKGABE_DATUM_META', '_cmx_carent_rueckgabe_datum');
		$km_start_key = cmx_carent_dashboard_meta_key('CMX_CARENT_FAHRZEUG_KM_STAND_UEBERNAHME_META', '_cmx_carent_fahrzeug_km_stand_uebernahme');
		$km_end_key = cmx_carent_dashboard_meta_key('CMX_CARENT_FAHRZEUG_KM_STAND_RUECKGABE_META', '_cmx_carent_fahrzeug_km_stand_rueckgabe');

		$post_ids = \get_posts([
			'post_type'              => 'carent',
			'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		]);

		$vehicles = [];
		$total_km = 0;
		$total_bookings = 0;

		foreach ($post_ids as $post_id) {
			$post_id = (int) $post_id;
			$status = \function_exists(__NAMESPACE__ . '\\cmx_carent_admin_status_value')
				? (string) cmx_carent_admin_status_value($post_id)
				: \sanitize_key((string) \get_post_meta($post_id, '_cmx_carent_status', true));
			if ($status !== 'abgeschlossen') {
				continue;
			}

			$start_date = cmx_carent_dashboard_clean_date(\get_post_meta($post_id, $start_key, true));
			if ($start_date === '') {
				continue;
			}
			$end_date = cmx_carent_dashboard_clean_date(\get_post_meta($post_id, $end_key, true));
			if ($end_date === '') {
				$end_date = $start_date;
			}
			if (\strtotime($end_date) < \strtotime($start_date)) {
				$end_date = $start_date;
			}

			$start_ts = \strtotime($start_date . ' 00:00:00');
			$end_ts = \strtotime($end_date . ' 00:00:00');
			if ($start_ts === false || $end_ts === false || $start_ts > $to_ts || $end_ts < $from_ts) {
				continue;
			}

			$article_id = \function_exists(__NAMESPACE__ . '\\cmx_carent_admin_linked_article_id')
				? (int) cmx_carent_admin_linked_article_id($post_id)
				: (int) \get_post_meta($post_id, '_cmx_carent_fahrzeug_id', true);
			$key = $article_id > 0 ? 'article:' . $article_id : 'post:' . $post_id;

			if (!isset($vehicles[$key])) {
				$vehicles[$key] = [
					'article_id'   => $article_id,
					'label'        => cmx_carent_dashboard_vehicle_label($article_id, $post_id),
					'kennzeichen'  => \function_exists(__NAMESPACE__ . '\\cmx_carent_admin_kennzeichen_label') ? (string) cmx_carent_admin_kennzeichen_label($post_id) : '',
					'bookings'     => 0,
					'km_total'     => 0,
					'days'         => [],
					'contracts'    => [],
					'range_days'   => $range_days,
				];
			}

			$overlap_start = \max($start_ts, $from_ts);
			$overlap_end = \min($end_ts, $to_ts);
			for ($day_ts = $overlap_start; $day_ts <= $overlap_end; $day_ts += DAY_IN_SECONDS) {
				$vehicles[$key]['days'][\date('Y-m-d', $day_ts)] = true;
			}

			$km_start = cmx_carent_dashboard_int_meta($post_id, $km_start_key);
			$km_end = cmx_carent_dashboard_int_meta($post_id, $km_end_key);
			$is_complete_in_range = $start_ts >= $from_ts && $end_ts <= $to_ts;
			$driven_km = ($is_complete_in_range && $km_end > $km_start) ? $km_end - $km_start : 0;

			$vehicles[$key]['bookings']++;
			$vehicles[$key]['km_total'] += $driven_km;
			$vehicles[$key]['contracts'][] = $post_id;
			$total_km += $driven_km;
			$total_bookings++;
		}

		foreach ($vehicles as $key => $vehicle) {
			$rented_days = \min($range_days, \count((array) $vehicle['days']));
			$utilization = $range_days > 0 ? ($rented_days / $range_days) * 100 : 0;
			$bookings = (int) $vehicle['bookings'];
			$km_total = (int) $vehicle['km_total'];
			$vehicles[$key]['rented_days'] = $rented_days;
			$vehicles[$key]['idle_days'] = \max(0, $range_days - $rented_days);
			$vehicles[$key]['utilization'] = $utilization;
			$vehicles[$key]['avg_km'] = $bookings > 0 ? $km_total / $bookings : 0;
			$vehicles[$key]['avg_km_per_day'] = $rented_days > 0 ? $km_total / $rented_days : 0;
			unset($vehicles[$key]['days']);
		}

		\uasort($vehicles, static function (array $a, array $b): int {
			return \strnatcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
		});

		$fleet_vehicle_count = \count(cmx_carent_dashboard_vehicle_article_ids());
		$vehicle_count = \count($vehicles);
		$capacity_vehicle_count = \max($fleet_vehicle_count, $vehicle_count);
		$total_rented_days = \array_sum(\array_map(static fn(array $vehicle): int => (int) ($vehicle['rented_days'] ?? 0), $vehicles));
		$capacity_days = $capacity_vehicle_count * $range_days;
		$total_idle_days = \max(0, $capacity_days - $total_rented_days);

		return [
			'from'              => $from,
			'to'                => $to,
			'range_days'        => $range_days,
			'fleet_vehicle_count' => $capacity_vehicle_count,
			'capacity_days'     => $capacity_days,
			'vehicles'          => \array_values($vehicles),
			'total_km'          => $total_km,
			'total_bookings'    => $total_bookings,
			'total_idle_days'   => $total_idle_days,
			'avg_km'            => $total_bookings > 0 ? $total_km / $total_bookings : 0,
			'avg_km_per_day'    => $total_rented_days > 0 ? $total_km / $total_rented_days : 0,
			'utilization'       => $capacity_days > 0 ? ($total_rented_days / $capacity_days) * 100 : 0,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_kpi_chart_data')) {
	function cmx_carent_dashboard_kpi_chart_data(array $vehicles): array {
		$labels = [];
		$avg_km = [];
		$total_km = [];
		$idle_days = [];

		foreach ($vehicles as $vehicle) {
			$label = \trim((string) ($vehicle['label'] ?? ''));
			$labels[] = $label !== '' ? $label : 'Fahrzeug';
			$avg_km[] = \round((float) ($vehicle['avg_km_per_day'] ?? 0), 1);
			$total_km[] = (int) ($vehicle['km_total'] ?? 0);
			$idle_days[] = (int) ($vehicle['idle_days'] ?? 0);
		}

		if ($labels === []) {
			$labels = ['Keine Daten'];
			$avg_km = [0];
			$total_km = [0];
			$idle_days = [0];
		}

		return [
			'avgKm' => [
				'canvas' => 'cmx-carent-dashboard-chart-avg-km',
				'labels' => $labels,
				'values' => $avg_km,
				'color'  => '#2563eb',
			],
			'totalKm' => [
				'canvas' => 'cmx-carent-dashboard-chart-total-km',
				'labels' => $labels,
				'values' => $total_km,
				'color'  => '#6d28d9',
			],
			'idleDays' => [
				'canvas' => 'cmx-carent-dashboard-chart-idle-days',
				'labels' => $labels,
				'values' => $idle_days,
				'color'  => '#f59e0b',
			],
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_add_kpi_chart_script')) {
	function cmx_carent_dashboard_add_kpi_chart_script(array $charts): void {
		$payload = \wp_json_encode($charts);
		if (!\is_string($payload) || $payload === '') {
			return;
		}

		\wp_add_inline_script('cmx-chartjs', '
			(function(){
				var charts = ' . $payload . ';
				function initCaRentKpiCharts(){
					if (typeof Chart === "undefined" || !charts) return;
					Object.keys(charts).forEach(function(key){
						var config = charts[key] || {};
						var canvas = config.canvas ? document.getElementById(config.canvas) : null;
						if (!canvas) return;
						var color = config.color || "#2563eb";
						new Chart(canvas, {
							type: "line",
							data: {
								labels: config.labels || [],
								datasets: [{
									data: config.values || [],
									borderColor: color,
									backgroundColor: color,
									borderWidth: 2.5,
									pointRadius: 3.5,
									pointHoverRadius: 4,
									pointBorderWidth: 0,
									tension: 0.36,
									fill: false
								}]
							},
							options: {
								responsive: true,
								maintainAspectRatio: false,
								animation: false,
								plugins: {
									legend: { display: false },
									tooltip: {
										displayColors: false,
										backgroundColor: "#111827",
										titleFont: { weight: "700" },
										bodyFont: { weight: "700" }
									}
								},
								scales: {
									x: {
										display: false,
										grid: { display: false },
										border: { display: false }
									},
									y: {
										display: false,
										beginAtZero: true,
										grid: { display: false },
										border: { display: false }
									}
								}
							}
						});
					});
				}
				if (document.readyState === "loading") {
					document.addEventListener("DOMContentLoaded", initCaRentKpiCharts);
				} else {
					initCaRentKpiCharts();
				}
			})();
		', 'after');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_export')) {
	function cmx_carent_dashboard_export(): void {
		if (!\current_user_can(cmx_carent_dashboard_capability())) {
			\wp_die('Keine Berechtigung.');
		}

		\check_admin_referer('cmx_carent_dashboard_export');
		[$from, $to] = cmx_carent_dashboard_range();
		$data = cmx_carent_dashboard_collect_data($from, $to);

		\header('Content-Type: text/csv; charset=utf-8');
		\header('Content-Disposition: attachment; filename="carent-dashboard-' . $from . '-' . $to . '.csv"');

		$out = \fopen('php://output', 'w');
		if ($out === false) {
			exit;
		}
		\fputcsv($out, ['Fahrzeug', 'Kennzeichen', 'Gesamtauslastung Fahrzeug %', 'Vermietete Tage', 'Verfügbare Fahrzeugtage', 'Gefahrene km', 'Ø km pro Vermiettag', 'Standzeit %', 'Standzeit Tage', 'Anzahl Buchungen'], ';');
		foreach ((array) $data['vehicles'] as $vehicle) {
			$range_days = (int) ($data['range_days'] ?? 0);
			$idle_days = (int) ($vehicle['idle_days'] ?? 0);
			$idle_percent = $range_days > 0 ? ($idle_days / $range_days) * 100 : 0;

			\fputcsv($out, [
				(string) ($vehicle['label'] ?? ''),
				(string) ($vehicle['kennzeichen'] ?? ''),
				\number_format((float) ($vehicle['utilization'] ?? 0), 1, '.', ''),
				(int) ($vehicle['rented_days'] ?? 0),
				$range_days,
				(int) ($vehicle['km_total'] ?? 0),
				\number_format((float) ($vehicle['avg_km_per_day'] ?? 0), 1, '.', ''),
				\number_format($idle_percent, 1, '.', ''),
				$idle_days,
				(int) ($vehicle['bookings'] ?? 0),
			], ';');
		}
		\fclose($out);
		exit;
	}
}

\add_action('admin_post_cmx_carent_dashboard_export', __NAMESPACE__ . '\\cmx_carent_dashboard_export');

\add_action('admin_menu', function (): void {
	\add_submenu_page(
		'edit.php?post_type=carent',
		'CaRent Dashboard',
		'Dashboard',
		cmx_carent_dashboard_capability(),
		'cmx-carent-dashboard',
		__NAMESPACE__ . '\\cmx_carent_dashboard_render',
		5
	);
}, 20);

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_progress')) {
	function cmx_carent_dashboard_progress(float $value, string $color): string {
		$value = \max(0, \min(100, $value));
		return '<div class="cmx-carent-dashboard-progress"><span style="width:' . \esc_attr((string) $value) . '%;background:' . \esc_attr($color) . '"></span></div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_bar_chart')) {
	function cmx_carent_dashboard_bar_chart(array $vehicles, string $metric, string $label, string $unit, bool $inverse = false): void {
		$max = 0;
		foreach ($vehicles as $vehicle) {
			$max = \max($max, (float) ($vehicle[$metric] ?? 0));
		}
		$max = $max > 0 ? $max : 1;
		$items = $vehicles;

		echo '<section class="cmx-carent-dashboard-chart-card">';
		echo '<h2>' . \esc_html($label) . '</h2>';
		if ($items === []) {
			echo '<p class="cmx-carent-dashboard-empty">Keine Daten im gewählten Zeitraum.</p>';
		} else {
			echo '<div class="cmx-carent-dashboard-bars-scroll">';
			echo '<div class="cmx-carent-dashboard-bars">';
			foreach ($items as $vehicle) {
				$value = (float) ($vehicle[$metric] ?? 0);
				$height = \max(8, ($value / $max) * 100);
				$color = $metric === 'km_total' ? '#2563eb' : ($metric === 'idle_days' ? cmx_carent_dashboard_color($value, true) : cmx_carent_dashboard_color($value));
				$name = \trim((string) ($vehicle['label'] ?? ''));
				$short = $name !== '' ? \preg_replace('/\s+.*/', '', $name) : 'Fahrzeug';
				echo '<div class="cmx-carent-dashboard-bar-item">';
				echo '<div class="cmx-carent-dashboard-bar-value">' . \esc_html(\number_format_i18n($value, $metric === 'utilization' ? 0 : 0) . $unit) . '</div>';
				echo '<div class="cmx-carent-dashboard-bar-track"><span style="height:' . \esc_attr((string) $height) . '%;background:' . \esc_attr($color) . '"></span></div>';
				echo '<div class="cmx-carent-dashboard-bar-label">' . \esc_html((string) $short) . '</div>';
				echo '</div>';
			}
			echo '</div>';
			echo '</div>';
		}
		echo '</section>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_kpi_card')) {
	function cmx_carent_dashboard_kpi_card(string $class, string $icon, string $label, string $value, string $subline, float $progress = -1, string $chart_id = ''): void {
		echo '<section class="cmx-carent-dashboard-kpi cmx-carent-dashboard-kpi-' . \esc_attr($class) . '">';
		echo '<div class="cmx-carent-dashboard-kpi-icon"><span class="dashicons ' . \esc_attr($icon) . '"></span></div>';
		echo '<div class="cmx-carent-dashboard-kpi-body">';
		echo '<div class="cmx-carent-dashboard-kpi-label">' . \esc_html($label) . '</div>';
		echo '<div class="cmx-carent-dashboard-kpi-value">' . \esc_html($value) . '</div>';
		echo '<div class="cmx-carent-dashboard-kpi-subline">' . \esc_html($subline) . '</div>';
		if ($progress >= 0) {
			echo cmx_carent_dashboard_progress($progress, 'currentColor');
		} elseif ($chart_id !== '') {
			echo '<div class="cmx-carent-dashboard-kpi-chart"><canvas id="' . \esc_attr($chart_id) . '"></canvas></div>';
		} else {
			echo '<svg class="cmx-carent-dashboard-sparkline" viewBox="0 0 180 44" aria-hidden="true" focusable="false">';
			echo '<polyline points="2,32 18,14 34,28 50,22 66,10 82,26 98,30 114,18 130,24 146,16 162,28 178,12"></polyline>';
			echo '<circle cx="18" cy="14" r="2"></circle><circle cx="66" cy="10" r="2"></circle><circle cx="114" cy="18" r="2"></circle><circle cx="178" cy="12" r="2"></circle>';
			echo '</svg>';
		}
		echo '</div>';
		echo '</section>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_render')) {
	function cmx_carent_dashboard_render(): void {
		if (!\current_user_can(cmx_carent_dashboard_capability())) {
			\wp_die('Keine Berechtigung.');
		}

		\wp_enqueue_style('dashicons');
		cmx_carent_dashboard_enqueue_chartjs();
		[$from, $to] = cmx_carent_dashboard_range();
		$selected_preset = cmx_carent_dashboard_requested_preset();
		$data = cmx_carent_dashboard_collect_data($from, $to);
		$vehicles = (array) ($data['vehicles'] ?? []);
		$kpi_charts = cmx_carent_dashboard_kpi_chart_data($vehicles);
		cmx_carent_dashboard_add_kpi_chart_script($kpi_charts);
		$export_url = \wp_nonce_url(\add_query_arg([
			'action'   => 'cmx_carent_dashboard_export',
			'cmx_range' => $selected_preset,
			'cmx_from' => $from,
			'cmx_to'   => $to,
		], \admin_url('admin-post.php')), 'cmx_carent_dashboard_export');

		echo '<div class="wrap cmx-carent-dashboard">';
		echo '<style>
			.cmx-carent-dashboard{color:#111827}
			.cmx-carent-dashboard *{box-sizing:border-box}
			.cmx-carent-dashboard-shell{width:calc(100% - 20px);max-width:none;margin:18px 20px 0 0}
			.cmx-carent-dashboard-top{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;margin-bottom:22px}
			.cmx-carent-dashboard-title{display:flex;align-items:center;gap:16px}
			.cmx-carent-dashboard-title-icon{width:96px;height:96px;border-radius:18px;background:#eef2f7;color:#111827;display:flex;align-items:center;justify-content:center}
			.cmx-carent-dashboard-title-icon .dashicons{width:60px;height:60px;font-size:60px}
			.cmx-carent-dashboard h1{margin:0;font-size:30px;line-height:1.15;font-weight:800}
			.cmx-carent-dashboard-subtitle{margin:4px 0 0;color:#64748b;font-size:13px}
			.cmx-carent-dashboard-filter{display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap}
			.cmx-carent-dashboard-filter label{display:block;font-weight:700;font-size:12px;margin-bottom:6px}
			.cmx-carent-dashboard-filter select,.cmx-carent-dashboard-filter input[type=date]{min-height:38px;border:1px solid #d7dde8;border-radius:6px;background:#fff;padding:4px 10px}
			.cmx-carent-dashboard-filter select{min-width:170px}
			.cmx-carent-dashboard-button{min-height:38px;border:1px solid #d7dde8;border-radius:6px;background:#fff;color:#111827;text-decoration:none;display:inline-flex;align-items:center;gap:8px;padding:0 14px;font-weight:700}
			.cmx-carent-dashboard-button:hover{border-color:#94a3b8;color:#111827}
			.cmx-carent-dashboard-button.is-disabled{color:#94a3b8;background:#f8fafc;border-color:#e2e8f0;cursor:not-allowed;pointer-events:none}
			.cmx-carent-dashboard-button.is-disabled .dashicons{color:#94a3b8}
			.cmx-carent-dashboard-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:18px}
			.cmx-carent-dashboard-card,.cmx-carent-dashboard-kpi,.cmx-carent-dashboard-chart-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 24px rgba(15,23,42,.06)}
			.cmx-carent-dashboard-kpi{display:flex;gap:18px;padding:22px}
			.cmx-carent-dashboard-kpi-icon{width:58px;height:58px;border-radius:999px;color:#fff;display:flex;align-items:center;justify-content:center;flex:0 0 auto}
			.cmx-carent-dashboard-kpi-icon .dashicons{width:34px;height:34px;font-size:34px}
			.cmx-carent-dashboard-kpi-green{color:#16a34a}.cmx-carent-dashboard-kpi-green .cmx-carent-dashboard-kpi-icon{background:#16a34a}
			.cmx-carent-dashboard-kpi-blue{color:#2563eb}.cmx-carent-dashboard-kpi-blue .cmx-carent-dashboard-kpi-icon{background:#2563eb}
			.cmx-carent-dashboard-kpi-purple{color:#6d28d9}.cmx-carent-dashboard-kpi-purple .cmx-carent-dashboard-kpi-icon{background:#6d28d9}
			.cmx-carent-dashboard-kpi-orange{color:#f59e0b}.cmx-carent-dashboard-kpi-orange .cmx-carent-dashboard-kpi-icon{background:#f59e0b}
			.cmx-carent-dashboard-kpi-body{min-width:0;flex:1}
			.cmx-carent-dashboard-kpi-label{color:#111827;font-weight:700;font-size:13px}
			.cmx-carent-dashboard-kpi-value{font-size:28px;font-weight:800;line-height:1.2;margin:5px 0}
			.cmx-carent-dashboard-kpi-subline{color:#64748b;font-size:12px;margin-bottom:12px}
			.cmx-carent-dashboard-progress{height:6px;background:#e5e7eb;border-radius:999px;overflow:hidden}
			.cmx-carent-dashboard-progress span{display:block;height:100%;border-radius:999px}
			.cmx-carent-dashboard-kpi-chart{position:relative;width:100%;height:52px;margin-top:4px}
			.cmx-carent-dashboard-kpi-chart canvas{display:block;width:100%!important;height:52px!important}
			.cmx-carent-dashboard-sparkline{display:block;width:100%;height:44px;margin-top:4px;overflow:visible}
			.cmx-carent-dashboard-sparkline polyline{fill:none;stroke:currentColor;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}
			.cmx-carent-dashboard-sparkline circle{fill:currentColor;opacity:.9}
			.cmx-carent-dashboard-table-card{padding:18px;margin-bottom:18px}
			.cmx-carent-dashboard-card-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:12px}
			.cmx-carent-dashboard-card-head h2,.cmx-carent-dashboard-chart-card h2{font-size:18px;line-height:1.25;margin:0;font-weight:800}
			.cmx-carent-dashboard-card-title-link{color:#111827;text-decoration:none}
			.cmx-carent-dashboard-card-title-link:hover{color:#b91c1c}
			.cmx-carent-dashboard-table-scroll{max-height:424px;overflow-y:auto;overflow-x:auto}
			.cmx-carent-dashboard-table{width:100%;border-collapse:collapse}
			.cmx-carent-dashboard-table th{font-size:12px;color:#334155;text-align:left;border-bottom:1px solid #e2e8f0;padding:10px 10px;font-weight:800;position:sticky;top:0;background:#fff;z-index:1}
			.cmx-carent-dashboard-table td{padding:11px 10px;border-bottom:1px solid #eef2f7;vertical-align:middle}
			.cmx-carent-dashboard-vehicle{display:flex;align-items:center;gap:12px;min-width:230px}
			.cmx-carent-dashboard-vehicle-thumb{width:72px;height:46px;border-radius:8px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;overflow:hidden;color:#64748b;flex:0 0 auto}
			.cmx-carent-dashboard-vehicle-thumb .dashicons{font-size:28px;width:28px;height:28px}
			.cmx-carent-dashboard-vehicle-image{width:100%;height:100%;object-fit:cover}
			.cmx-carent-dashboard-vehicle-name{font-weight:800}
			.cmx-carent-dashboard-vehicle-meta,.cmx-carent-dashboard-cell-sub{font-size:12px;color:#64748b;margin-top:2px}
			.cmx-carent-dashboard-cell-main{font-weight:800;color:#111827}
			.cmx-carent-dashboard-cell-main.blue{color:#2563eb}.cmx-carent-dashboard-cell-main.purple{color:#6d28d9}.cmx-carent-dashboard-cell-main.orange{color:#f59e0b}
			.cmx-carent-dashboard-mini-progress{width:130px;margin-top:6px}
			.cmx-carent-dashboard-mini-progress .cmx-carent-dashboard-progress{height:5px}
			.cmx-carent-dashboard-charts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
			.cmx-carent-dashboard-chart-card{padding:18px;min-height:235px}
			.cmx-carent-dashboard-bars-scroll{overflow-x:auto;overflow-y:hidden}
			.cmx-carent-dashboard-bars{height:170px;display:flex;align-items:end;gap:18px;margin-top:16px;border-bottom:1px solid #e2e8f0;min-width:max-content}
			.cmx-carent-dashboard-bar-item{height:100%;display:flex;flex:0 0 calc((100% - 72px) / 5);min-width:86px;max-width:110px;flex-direction:column;align-items:center;justify-content:flex-end;gap:6px}
			.cmx-carent-dashboard-bar-value{font-size:11px;font-weight:800;color:#111827;white-space:nowrap}
			.cmx-carent-dashboard-bar-track{height:110px;width:34px;display:flex;align-items:flex-end;justify-content:center}
			.cmx-carent-dashboard-bar-track span{display:block;width:100%;border-radius:5px 5px 0 0}
			.cmx-carent-dashboard-bar-label{font-size:11px;color:#334155;max-width:82px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
			.cmx-carent-dashboard-empty{margin:18px 0;color:#64748b}
			@media (max-width:1200px){.cmx-carent-dashboard-grid,.cmx-carent-dashboard-charts{grid-template-columns:repeat(2,minmax(0,1fr))}.cmx-carent-dashboard-top{display:block}.cmx-carent-dashboard-filter{margin-top:16px}}
			@media (max-width:782px){.cmx-carent-dashboard-shell{width:calc(100% - 10px);margin-right:10px}.cmx-carent-dashboard-grid,.cmx-carent-dashboard-charts{grid-template-columns:1fr}.cmx-carent-dashboard-table{display:block;overflow-x:auto}.cmx-carent-dashboard-filter select,.cmx-carent-dashboard-filter input[type=date]{width:100%}}
		</style>';

		echo '<div class="cmx-carent-dashboard-shell">';
		echo '<div class="cmx-carent-dashboard-top">';
		echo '<div class="cmx-carent-dashboard-title">';
		echo '<div class="cmx-carent-dashboard-title-icon"><span class="dashicons dashicons-car"></span></div>';
		echo '<div><h1>Dashboard</h1><p class="cmx-carent-dashboard-subtitle">Übersicht der Fahrzeugnutzung für den gewählten Zeitraum</p></div>';
		echo '</div>';
		echo '<form class="cmx-carent-dashboard-filter" method="get">';
		echo '<input type="hidden" name="post_type" value="carent">';
		echo '<input type="hidden" name="page" value="cmx-carent-dashboard">';
		echo '<div><label for="cmx-carent-dashboard-range">Zeitraum</label><select id="cmx-carent-dashboard-range" name="cmx_range">';
		foreach (cmx_carent_dashboard_preset_options() as $value => $label) {
			echo '<option value="' . \esc_attr($value) . '" ' . \selected($selected_preset, $value, false) . '>' . \esc_html($label) . '</option>';
		}
		echo '</select></div>';
		echo '<div><label for="cmx-carent-dashboard-from">Von</label><input type="date" id="cmx-carent-dashboard-from" name="cmx_from" value="' . \esc_attr($from) . '"></div>';
		echo '<div><label for="cmx-carent-dashboard-to">Bis</label><input type="date" id="cmx-carent-dashboard-to" name="cmx_to" value="' . \esc_attr($to) . '"></div>';
		echo '<button class="cmx-carent-dashboard-button" type="submit"><span class="dashicons dashicons-filter"></span>Filter</button>';
		echo '</form>';
		echo '</div>';
		echo '<script>
			(function(){
				var form = document.querySelector(".cmx-carent-dashboard-filter");
				if (!form) return;
				var select = document.getElementById("cmx-carent-dashboard-range");
				var from = document.getElementById("cmx-carent-dashboard-from");
				var to = document.getElementById("cmx-carent-dashboard-to");
				var ranges = ' . \wp_json_encode(\array_combine(
					\array_keys(cmx_carent_dashboard_preset_options()),
					\array_map(
					static fn(string $preset): array => cmx_carent_dashboard_preset_range($preset),
					\array_keys(cmx_carent_dashboard_preset_options())
					)
				)) . ';
				if (select) {
					select.addEventListener("change", function(){
						var range = ranges[select.value] || null;
						if (range && from && to) {
							from.value = range[0] || "";
							to.value = range[1] || "";
						}
						form.submit();
					});
				}
				[from, to].forEach(function(field){
					if (!field) return;
					field.addEventListener("change", function(){
						if (select) select.dataset.manualRange = "1";
					});
				});
			})();
		</script>';

		echo '<div class="cmx-carent-dashboard-grid">';
		cmx_carent_dashboard_kpi_card('green', 'dashicons-dashboard', 'Gesamtauslastung (%)', \number_format_i18n((float) $data['utilization'], 0) . ' %', 'Vermiettage / Kalendertage', (float) $data['utilization']);
		cmx_carent_dashboard_kpi_card('blue', 'dashicons-chart-line', 'Ø km pro Vermiettag', \number_format_i18n((float) $data['avg_km_per_day'], 0) . ' km', 'gefahrene km / Vermiettage', -1, 'cmx-carent-dashboard-chart-avg-km');
		cmx_carent_dashboard_kpi_card('purple', 'dashicons-performance', 'Gefahrene km (gesamt)', \number_format_i18n((int) $data['total_km']) . ' km', 'Übergabe bis Rückgabe im Zeitraum', -1, 'cmx-carent-dashboard-chart-total-km');
		cmx_carent_dashboard_kpi_card('orange', 'dashicons-clock', 'Standtage Flotte', \number_format_i18n((int) $data['total_idle_days']) . ' Tage', 'nicht verbuchte Vermiettage', -1, 'cmx-carent-dashboard-chart-idle-days');
		echo '</div>';

		echo '<section class="cmx-carent-dashboard-card cmx-carent-dashboard-table-card">';
		$vehicle_articles_url = cmx_carent_dashboard_vehicle_articles_url();
		$export_button = $vehicles === []
			? '<span class="cmx-carent-dashboard-button is-disabled" aria-disabled="true"><span class="dashicons dashicons-download"></span>Exportieren</span>'
			: '<a class="cmx-carent-dashboard-button" href="' . \esc_url($export_url) . '"><span class="dashicons dashicons-download"></span>Exportieren</a>';
		echo '<div class="cmx-carent-dashboard-card-head"><h2><a class="cmx-carent-dashboard-card-title-link" href="' . \esc_url($vehicle_articles_url) . '">Fahrzeugübersicht</a></h2>' . $export_button . '</div>';
		if ($vehicles === []) {
			echo '<p class="cmx-carent-dashboard-empty">Keine abgeschlossenen Vermietungen im gewählten Zeitraum gefunden.</p>';
		} else {
			echo '<div class="cmx-carent-dashboard-table-scroll">';
			echo '<table class="cmx-carent-dashboard-table">';
			echo '<thead><tr>';
			echo '<th>Fahrzeug</th><th>Gesamtauslastung (%)<br><span class="cmx-carent-dashboard-cell-sub">(Fahrzeug-Vermiettage / verfügbare Fahrzeugtage)</span></th><th>Gefahrene km<br><span class="cmx-carent-dashboard-cell-sub">(im Zeitraum)</span></th><th>Ø km pro Vermiettag<br><span class="cmx-carent-dashboard-cell-sub">(Fahrzeug-km / Fahrzeug-Vermiettage)</span></th><th>Standzeit (%)<br><span class="cmx-carent-dashboard-cell-sub">(Tage ohne Vermietung / verfügbare Fahrzeugtage)</span></th><th>Anzahl Buchungen<br><span class="cmx-carent-dashboard-cell-sub">(im Zeitraum)</span></th>';
			echo '</tr></thead><tbody>';
			foreach ($vehicles as $vehicle) {
				$utilization = (float) ($vehicle['utilization'] ?? 0);
				$util_color = cmx_carent_dashboard_color($utilization);
				$idle_days = (int) ($vehicle['idle_days'] ?? 0);
				$idle_percent = (int) $data['range_days'] > 0 ? ($idle_days / (int) $data['range_days']) * 100 : 0;
				$idle_color = cmx_carent_dashboard_color((float) $idle_percent, true);
				$km_total = (int) ($vehicle['km_total'] ?? 0);
				$km_ratio = (int) $data['total_km'] > 0 ? ($km_total / (int) $data['total_km']) * 100 : 0;
				$avg_km = (float) ($vehicle['avg_km_per_day'] ?? 0);
				$avg_ratio = (float) $data['avg_km_per_day'] > 0 ? \min(100, ($avg_km / ((float) $data['avg_km_per_day'] * 1.5)) * 100) : 0;

				echo '<tr>';
				echo '<td><div class="cmx-carent-dashboard-vehicle"><div class="cmx-carent-dashboard-vehicle-thumb">' . cmx_carent_dashboard_vehicle_image((int) ($vehicle['article_id'] ?? 0)) . '</div><div><div class="cmx-carent-dashboard-vehicle-name">' . \esc_html((string) ($vehicle['label'] ?? '')) . '</div><div class="cmx-carent-dashboard-vehicle-meta">' . \esc_html((string) ($vehicle['kennzeichen'] ?? '')) . '</div></div></div></td>';
				echo '<td><div class="cmx-carent-dashboard-cell-main" style="color:' . \esc_attr($util_color) . '">' . \esc_html(\number_format_i18n($utilization, 0)) . ' %</div><div class="cmx-carent-dashboard-mini-progress">' . cmx_carent_dashboard_progress($utilization, $util_color) . '</div><div class="cmx-carent-dashboard-cell-sub">' . (int) ($vehicle['rented_days'] ?? 0) . ' / ' . (int) $data['range_days'] . ' Tage</div></td>';
				echo '<td><div class="cmx-carent-dashboard-cell-main blue">' . \esc_html(\number_format_i18n($km_total)) . ' km</div><div class="cmx-carent-dashboard-mini-progress">' . cmx_carent_dashboard_progress($km_ratio, '#2563eb') . '</div></td>';
				echo '<td><div class="cmx-carent-dashboard-cell-main purple">' . \esc_html(\number_format_i18n($avg_km, 0)) . ' km</div><div class="cmx-carent-dashboard-mini-progress">' . cmx_carent_dashboard_progress($avg_ratio, '#6d28d9') . '</div></td>';
				echo '<td><div class="cmx-carent-dashboard-cell-main orange" style="color:' . \esc_attr($idle_color) . '">' . \esc_html(\number_format_i18n($idle_percent, 0)) . ' %</div><div class="cmx-carent-dashboard-mini-progress">' . cmx_carent_dashboard_progress($idle_percent, $idle_color) . '</div><div class="cmx-carent-dashboard-cell-sub">' . \esc_html(\number_format_i18n($idle_days)) . ' Tage</div></td>';
				echo '<td><div class="cmx-carent-dashboard-cell-main">' . (int) ($vehicle['bookings'] ?? 0) . '</div><div class="cmx-carent-dashboard-cell-sub">Abgeschlossen</div></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
			echo '</div>';
		}
		echo '</section>';

		echo '<div class="cmx-carent-dashboard-charts">';
		cmx_carent_dashboard_bar_chart($vehicles, 'utilization', 'Auslastung pro Fahrzeug', ' %');
		cmx_carent_dashboard_bar_chart($vehicles, 'km_total', 'Gefahrene km pro Fahrzeug', ' km');
		cmx_carent_dashboard_bar_chart($vehicles, 'idle_days', 'Standzeit pro Fahrzeug (Tage)', ' Tage', true);
		echo '</div>';

		echo '</div></div>';
	}
}
