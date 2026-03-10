<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


if (!in_array(UserDomain, cmx_ini_get_value('vip', 'instanzen'))) return;
// if (!in_array(UserDomain, cmx_ini_get_value('vip', 'instanzen'))) return;


if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_main_slug')) {
	function cmx_cockpit_view_main_slug(): string {
		return 'cmx-cockpit-dashboard';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_main_css')) {
	function cmx_cockpit_view_main_css(): string {
		return '
			.mb-dashboard-wrap{margin:20px 0 0;padding:0 20px 0 0;box-sizing:border-box}
			.mb-dashboard-intro{margin:6px 0 18px;color:#5c6773;font-size:14px}
			.mb-grid{
				display:grid;
				grid-template-columns:repeat(8,minmax(0,1fr));
				gap:18px;
				align-items:start;
			}
			.mb-card{
				background:#fff;
				border:1px solid #d7dce3;
				border-radius:14px;
				padding:18px;
				box-sizing:border-box;
				box-shadow:0 1px 2px rgba(16,24,40,.04),0 10px 24px rgba(16,24,40,.04);
			}
			.mb-card h2,
			.mb-card h3{
				margin:0 0 12px;
				font-size:15px;
				line-height:1.35;
				color:#162033;
			}
			.mb-card p{margin:0;color:#4e5968}
			.mb-card--hero{
				background:linear-gradient(135deg,#f6fbff 0%,#ffffff 56%,#f4f8ff 100%);
				border-color:#cfe0f7;
			}
			.mb-card--hero h2{
				font-size:20px;
				margin-bottom:8px;
			}
			.mb-card--hero p{
				max-width:780px;
				font-size:14px;
			}
			.mb-card--kpi{min-height:136px}
			.mb-kpi-value{
				display:block;
				margin-bottom:8px;
				font-size:32px;
				line-height:1.1;
				font-weight:700;
				color:#162033;
				letter-spacing:-.03em;
			}
			.mb-kpi-label{
				display:block;
				font-size:13px;
				color:#667085;
			}
			.mb-card--soft{
				background:linear-gradient(180deg,#ffffff 0%,#fbfcfe 100%);
			}
			.mb-card--example{
				background:linear-gradient(180deg,#ffffff 0%,#f8fbff 100%);
				border-style:dashed;
				border-color:#cfd9e7;
			}
			.mb-card--example p{
				font-size:13px;
				color:#5b6778;
			}
			.mb-card--chart{
				min-height:280px;
			}
			.mb-card--chart .mb-chart-placeholder{
				display:flex;
				align-items:center;
				justify-content:center;
				min-height:210px;
				border:1px dashed #c9d5e6;
				border-radius:12px;
				background:repeating-linear-gradient(
					135deg,
					#f8fbff,
					#f8fbff 14px,
					#f1f6fd 14px,
					#f1f6fd 28px
				);
				color:#56708f;
				font-size:14px;
			}
			.mb-demo-linechart{
				margin-top:18px;
				padding:14px 16px 12px;
				border:1px solid #d9e6f6;
				border-radius:12px;
				background:linear-gradient(180deg,#ffffff 0%,#f8fbff 100%);
			}
			.mb-demo-linechart-toolbar{
				display:flex;
				align-items:center;
				justify-content:space-between;
				gap:12px;
				flex-wrap:wrap;
				margin:0 0 14px;
			}
			.mb-demo-linechart-control{
				display:flex;
				align-items:center;
				gap:8px;
				font-size:13px;
				color:#344054;
			}
			.mb-demo-linechart-control select{
				min-width:112px;
				padding:6px 28px 6px 10px;
				border:1px solid #cfd9e7;
				border-radius:8px;
				background:#fff;
				color:#162033;
			}
			.mb-demo-linechart-control input[type="checkbox"]{
				margin:0;
			}
			.mb-demo-linechart-canvas{
				position:relative;
				height:260px;
			}
			.mb-demo-linechart-canvas canvas{
				display:block;
				width:100%;
				height:100%;
			}
			.mb-demo-linechart-stats{
				display:grid;
				grid-template-columns:repeat(4,minmax(0,1fr));
				gap:10px;
				margin-top:12px;
			}
			.mb-demo-linechart-stats strong{
				display:block;
				font-size:16px;
				line-height:1.15;
				color:#162033;
			}
			.mb-demo-linechart-stats span{
				display:block;
				margin-top:2px;
				font-size:12px;
				color:#667085;
			}
			.mb-list{
				margin:0;
				padding:0;
				list-style:none;
			}
			.mb-list li{
				display:flex;
				align-items:flex-start;
				justify-content:space-between;
				gap:12px;
				padding:10px 0;
				border-top:1px solid #eef2f6;
			}
			.mb-list li:first-child{padding-top:0;border-top:0}
			.mb-list strong{display:block;color:#162033}
			.mb-list span{display:block;color:#667085;font-size:12px}
			.mb-note{
				display:inline-flex;
				align-items:center;
				padding:4px 10px;
				border-radius:999px;
				background:#edf5ff;
				border:1px solid #d5e6fb;
				color:#2b5f9e;
				font-size:12px;
				font-weight:600;
			}
			.mb-meta{
				font-size:12px;
				color:#667085;
			}
			.mb-stat{
				font-size:26px;
				font-weight:700;
				line-height:1.1;
				color:#162033;
				margin:0 0 8px;
			}
			.mb-span-1{grid-column:span 1}
			.mb-span-2{grid-column:span 2}
			.mb-span-3{grid-column:span 3}
			.mb-span-4{grid-column:span 4}
			.mb-span-5{grid-column:span 5}
			.mb-span-6{grid-column:span 6}
			.mb-span-7{grid-column:span 7}
			.mb-span-8{grid-column:span 8}
			@media (max-width: 1600px){
				.mb-grid{grid-template-columns:repeat(6,minmax(0,1fr))}
				.mb-span-7,
				.mb-span-8{grid-column:span 6}
			}
			@media (max-width: 1280px){
				.mb-grid{grid-template-columns:repeat(4,minmax(0,1fr))}
				.mb-span-5,
				.mb-span-6,
				.mb-span-7,
				.mb-span-8{grid-column:span 4}
			}
			@media (max-width: 980px){
				.mb-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
				.mb-span-3,
				.mb-span-4,
				.mb-span-5,
				.mb-span-6,
				.mb-span-7,
				.mb-span-8{grid-column:span 2}
			}
			@media (max-width: 782px){
				.mb-grid{grid-template-columns:1fr}
				.mb-span-2,
				.mb-span-3,
				.mb-span-4,
				.mb-span-5,
				.mb-span-6,
				.mb-span-7,
				.mb-span-8{grid-column:span 1}
			}
			@media (max-width: 640px){
				.mb-demo-linechart-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
			}
		';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_month_labels')) {
	function cmx_cockpit_view_monitor_month_labels(): array {
		return ['Jan', 'Feb', 'Mrz', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_beleg_total')) {
	function cmx_cockpit_view_monitor_beleg_total(int $post_id): float {
		$total = 0.0;

		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
			$calc = (array) cmxbu_get_beleg_positionen_calc($post_id);
			if (isset($calc['total']) && \is_numeric($calc['total'])) {
				$total = (float) $calc['total'];
			}
		}

		$override_raw = \trim((string) \get_post_meta($post_id, '_cmx_beleg_summe_override', true));
		if ($override_raw !== '') {
			$normalized = \str_replace(['\'', ' '], '', \str_replace(',', '.', $override_raw));
			if (\is_numeric($normalized)) {
				$total = (float) $normalized;
			}
		}

		return \round($total, 2);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_beleg_timestamp')) {
	function cmx_cockpit_view_monitor_beleg_timestamp(int $post_id): int {
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_post_date')) {
			$normalized = (string) cmxbu_belege_export_post_date($post_id);
			$ts = $normalized !== '' ? \strtotime($normalized . ' 00:00:00') : false;
			if ($ts) {
				return (int) $ts;
			}
		}

		$date_keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_DATUM')) {
			$date_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_DATUM');
		}
		$date_keys = \array_merge($date_keys, [
			'_cmx_beleg_rng_datum',
			'beleg_datum',
			'_cmx_rechnungsdatum',
			'_invoice_date',
			'_date',
		]);

		foreach (\array_unique($date_keys) as $meta_key) {
			$raw = \trim((string) \get_post_meta($post_id, $meta_key, true));
			if ($raw === '') {
				continue;
			}

			foreach (['Y-m-d', 'd.m.Y', 'Ymd', 'Y-m-d H:i:s'] as $format) {
				$dt = \DateTime::createFromFormat($format, $raw);
				if ($dt instanceof \DateTime) {
					return (int) $dt->getTimestamp();
				}
			}

			$ts = \strtotime($raw);
			if ($ts) {
				return (int) $ts;
			}
		}

		$post = \get_post($post_id);
		if ($post instanceof \WP_Post) {
			$ts = \strtotime((string) $post->post_date);
			if ($ts) {
				return (int) $ts;
			}
		}

		return 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_chart_payload')) {
	function cmx_cockpit_view_monitor_chart_payload(): array {
		$labels = cmx_cockpit_view_monitor_month_labels();
		$years = [];
		$series = [];
		$counts = [];
		$post_type = \defined(__NAMESPACE__ . '\\CMX_PT_BELEGE')
			? (string) \constant(__NAMESPACE__ . '\\CMX_PT_BELEGE')
			: 'belege';

		$query = new \WP_Query([
			'post_type' => $post_type,
			'post_status' => 'any',
			'posts_per_page' => -1,
			'orderby' => 'date',
			'order' => 'ASC',
			'no_found_rows' => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		]);

		foreach ((array) $query->posts as $post) {
			if (!$post instanceof \WP_Post) {
				continue;
			}
			if (\in_array((string) $post->post_status, ['trash', 'auto-draft'], true)) {
				continue;
			}

			$timestamp = cmx_cockpit_view_monitor_beleg_timestamp((int) $post->ID);
			if ($timestamp <= 0) {
				continue;
			}

			$year = (int) \date('Y', $timestamp);
			$month_index = ((int) \date('n', $timestamp)) - 1;
			if ($month_index < 0 || $month_index > 11) {
				continue;
			}

			if (!isset($series[$year])) {
				$series[$year] = \array_fill(0, 12, 0.0);
				$counts[$year] = 0;
			}

			$series[$year][$month_index] += cmx_cockpit_view_monitor_beleg_total((int) $post->ID);
			$counts[$year] = (int) ($counts[$year] ?? 0) + 1;
		}

		\wp_reset_postdata();

		if ($series !== []) {
			\krsort($series, \SORT_NUMERIC);
		}

		foreach ($series as $year => $values) {
			$years[] = (int) $year;
			$series[$year] = \array_map(
				static fn($value): float => \round((float) $value, 2),
				(array) $values
			);
		}

		if ($years === []) {
			$current_year = (int) \wp_date('Y');
			$years = [$current_year];
			$series[$current_year] = \array_fill(0, 12, 0.0);
			$counts[$current_year] = 0;
		}

		return [
			'labels' => $labels,
			'years' => $years,
			'series' => $series,
			'counts' => $counts,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_view_main_page')) {
	function cmx_render_view_main_page(): void {
		$chart_payload = cmx_cockpit_view_monitor_chart_payload();
		$years = \array_values((array) ($chart_payload['years'] ?? []));
		$selected_year = (int) ($years[0] ?? \wp_date('Y'));
		$selected_series = (array) (($chart_payload['series'] ?? [])[$selected_year] ?? \array_fill(0, 12, 0.0));
		$selected_total = \array_sum($selected_series);
		$selected_count = (int) (($chart_payload['counts'] ?? [])[$selected_year] ?? 0);
		$compare_year = isset($years[1]) ? (int) $years[1] : 0;
		$compare_series = $compare_year > 0
			? (array) (($chart_payload['series'] ?? [])[$compare_year] ?? \array_fill(0, 12, 0.0))
			: \array_fill(0, 12, 0.0);
		$compare_total = \array_sum($compare_series);
		?>
		<div class="wrap mb-dashboard-wrap">
			<!-- <h1>Monitor</h1> -->
			<!-- <p class="mb-dashboard-intro">Akt. Daten</p> -->

			<div class="mb-grid">
				<section class="mb-card mb-card--hero mb-span-8">
					<!-- <span class="mb-note">Dashboard</span> -->
					<h2>Meine Umsätze</h2>
					<p>Deine Werte kannst Du auch gegenüber stellen.</p>
					<div class="mb-demo-linechart" aria-label="Demo-Line-Chart">
						<div class="mb-demo-linechart-toolbar">
							<label class="mb-demo-linechart-control">
								<span>Jahr</span>
								<select id="cmx-monitor-chart-year">
									<?php foreach ($years as $year_option) : ?>
										<option value="<?php echo \esc_attr((string) $year_option); ?>"<?php selected($year_option, $selected_year); ?>><?php echo \esc_html((string) $year_option); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label class="mb-demo-linechart-control">
								<input type="checkbox" id="cmx-monitor-chart-compare" <?php checked($compare_year > 0); ?>>
								<span>vorheriger Zeitraum</span>
							</label>
						</div>
						<div class="mb-demo-linechart-canvas">
							<canvas id="cmx-monitor-multi-axis-chart" aria-label="Demo-Multi-Axis-Line-Chart"></canvas>
						</div>
						<div class="mb-demo-linechart-stats">
							<div><strong id="cmx-monitor-stat-total"><?php echo \esc_html(\number_format($selected_total, 2, '.', '\'')); ?></strong><span id="cmx-monitor-stat-total-label">Umsatz <?php echo \esc_html((string) $selected_year); ?></span></div>
							<div><strong id="cmx-monitor-stat-compare"><?php echo \esc_html(\number_format($compare_total, 2, '.', '\'')); ?></strong><span id="cmx-monitor-stat-compare-label"><?php echo $compare_year > 0 ? \esc_html('Umsatz ' . $compare_year) : 'Vergleich'; ?></span></div>
							<div><strong id="cmx-monitor-stat-count"><?php echo \esc_html((string) $selected_count); ?></strong><span>Belege</span></div>
							<div><strong id="cmx-monitor-stat-mode"><?php echo $compare_year > 0 ? 'aktiv' : 'aus'; ?></strong><span>Vergleich</span></div>
						</div>
					</div>
				</section>

			</div>
		</div>
		<?php
	}
}

\add_action('admin_menu', function (): void {
	\add_dashboard_page(
		'Monitor',
		'Monitor',
		'edit_posts',
		cmx_cockpit_view_main_slug(),
		__NAMESPACE__ . '\\cmx_render_view_main_page'
	);
});

\add_action('admin_enqueue_scripts', function (string $hook): void {
	if ($hook !== 'dashboard_page_' . cmx_cockpit_view_main_slug()) {
		return;
	}

	if (\function_exists(__NAMESPACE__ . '\\cmx_enqueue_chartjs')) {
		cmx_enqueue_chartjs();
	}

	$handle = 'cmx-cockpit-view-main';
	\wp_register_style($handle, false, [], '1.0');
	\wp_enqueue_style($handle);
	\wp_add_inline_style($handle, cmx_cockpit_view_main_css());

	$chart_payload = cmx_cockpit_view_monitor_chart_payload();
	$chart_script = '(function(){
		var init = function(){
			var canvas = document.getElementById("cmx-monitor-multi-axis-chart");
			var yearSelect = document.getElementById("cmx-monitor-chart-year");
			var compareCheckbox = document.getElementById("cmx-monitor-chart-compare");
			if (!canvas || !yearSelect || !compareCheckbox || typeof Chart === "undefined") return;

			var ctx = canvas.getContext("2d");
			if (!ctx) return;

			var payload = ' . \wp_json_encode($chart_payload) . ';
			var totalEl = document.getElementById("cmx-monitor-stat-total");
			var totalLabelEl = document.getElementById("cmx-monitor-stat-total-label");
			var compareEl = document.getElementById("cmx-monitor-stat-compare");
			var compareLabelEl = document.getElementById("cmx-monitor-stat-compare-label");
			var countEl = document.getElementById("cmx-monitor-stat-count");
			var modeEl = document.getElementById("cmx-monitor-stat-mode");
			var yearOrder = Array.isArray(payload.years) ? payload.years.map(function(year){ return String(year); }) : [];
			var formatNumber = function(value){
				return new Intl.NumberFormat("de-CH", {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(Number(value || 0));
			};
			var formatAxisNumber = function(value){
				return new Intl.NumberFormat("de-CH", {minimumFractionDigits: 0, maximumFractionDigits: 0}).format(Number(value || 0));
			};
			var sumSeries = function(series){
				return (Array.isArray(series) ? series : []).reduce(function(sum, value){
					return sum + Number(value || 0);
				}, 0);
			};
			var countSeries = function(series){
				return (Array.isArray(series) ? series : []).reduce(function(sum, value){
					return sum + (Number(value || 0) !== 0 ? 1 : 0);
				}, 0);
			};
			var compareYearFor = function(selectedYear){
				var previousYear = String(Number(selectedYear || 0) - 1);
				if (!previousYear || previousYear === "0") return "";
				return payload.series && payload.series[previousYear] ? previousYear : "";
			};
			var chart = new Chart(ctx, {
				type: "line",
				data: {
					labels: payload.labels,
					datasets: []
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: {
						mode: "index",
						intersect: false
					},
					plugins: {
						legend: {
							position: "top",
							labels: {
								usePointStyle: true,
								boxWidth: 10,
								color: "#344054"
							}
						},
						tooltip: {
							backgroundColor: "rgba(22,32,51,0.92)",
							padding: 10,
							callbacks: {
								label: function(context){
									var label = context.dataset && context.dataset.label ? String(context.dataset.label) : "";
									return label + ": " + formatNumber(context.parsed && typeof context.parsed.y !== "undefined" ? context.parsed.y : 0);
								}
							}
						}
					},
					scales: {
						x: {
							grid: {
								color: "#eef3f8"
							},
							ticks: {
								color: "#667085"
							}
						},
						y: {
							type: "linear",
							position: "left",
							grid: {
								color: "#e7eef7"
							},
							ticks: {
								color: "#4f86c6",
								callback: function(value){
									return formatAxisNumber(value);
								}
							},
							title: {
								display: true,
								text: "Belegsumme"
							}
						}
					}
				}
			});

			var updateChart = function(){
				var selectedYear = String(yearSelect.value || "");
				var selectedSeries = (payload.series && payload.series[selectedYear]) ? payload.series[selectedYear] : [];
				var compareYear = compareCheckbox.checked ? compareYearFor(selectedYear) : "";
				var compareSeries = compareYear && payload.series && payload.series[compareYear] ? payload.series[compareYear] : [];
				var datasets = [
					{
						label: selectedYear,
						data: selectedSeries,
						yAxisID: "y",
						borderColor: "#4f86c6",
						backgroundColor: "rgba(79,134,198,0.14)",
						fill: false,
						tension: 0.35,
						borderWidth: 3,
						pointRadius: 4,
						pointHoverRadius: 5
					}
				];

				if (compareYear && compareSeries.length) {
					datasets.push({
						label: compareYear,
						data: compareSeries,
						yAxisID: "y",
						borderColor: "#ef7d00",
						backgroundColor: "rgba(239,125,0,0.10)",
						fill: false,
						tension: 0.35,
						borderWidth: 3,
						pointRadius: 4,
						pointHoverRadius: 5
					});
				}

				chart.data.datasets = datasets;
				chart.update();

				if (totalEl) totalEl.textContent = formatNumber(sumSeries(selectedSeries));
				if (totalLabelEl) totalLabelEl.textContent = "Umsatz " + selectedYear;
				if (compareEl) compareEl.textContent = compareYear ? formatNumber(sumSeries(compareSeries)) : "0.00";
				if (compareLabelEl) compareLabelEl.textContent = compareYear ? ("Umsatz " + compareYear) : "Vergleich";
				if (countEl) countEl.textContent = String((payload.counts && payload.counts[selectedYear]) ? payload.counts[selectedYear] : countSeries(selectedSeries));
				if (modeEl) modeEl.textContent = compareYear ? "aktiv" : "aus";
			};

			yearSelect.addEventListener("change", updateChart);
			compareCheckbox.addEventListener("change", updateChart);
			updateChart();
		};

		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", init);
			return;
		}
		init();
	})();';
	\wp_add_inline_script('cmx-chartjs', $chart_script, 'after');
});
