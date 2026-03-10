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

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_chart_payload')) {
	function cmx_cockpit_view_monitor_chart_payload(): array {
		return [
			'labels' => ['Jan', 'Feb', 'Mrz', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug'],
			'datasets' => [
				[
					'label' => 'Umsatz',
					'data' => [12400, 13850, 13120, 14980, 16140, 15820, 17240, 18690],
					'yAxisID' => 'y',
					'borderColor' => '#4f86c6',
					'backgroundColor' => 'rgba(79,134,198,0.14)',
				],
				[
					'label' => 'Tickets',
					'data' => [42, 39, 44, 37, 34, 31, 28, 26],
					'yAxisID' => 'y1',
					'borderColor' => '#ef7d00',
					'backgroundColor' => 'rgba(239,125,0,0.14)',
				],
			],
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_view_main_page')) {
	function cmx_render_view_main_page(): void {
		$chart_payload = cmx_cockpit_view_monitor_chart_payload();
		$umsatz = (array) ($chart_payload['datasets'][0]['data'] ?? []);
		$tickets = (array) ($chart_payload['datasets'][1]['data'] ?? []);
		$current_umsatz = (float) ($umsatz[\array_key_last($umsatz)] ?? 0);
		$previous_umsatz = (float) ($umsatz[\array_key_last($umsatz) - 1] ?? 0);
		$delta_umsatz = $previous_umsatz > 0
			? (($current_umsatz - $previous_umsatz) / $previous_umsatz) * 100
			: 0.0;
		$current_tickets = (int) ($tickets[\array_key_last($tickets)] ?? 0);
		?>
		<div class="wrap mb-dashboard-wrap">
			<!-- <h1>Monitor</h1> -->
			<!-- <p class="mb-dashboard-intro">Akt. Daten</p> -->

			<div class="mb-grid">
				<section class="mb-card mb-card--hero mb-span-8">
					<!-- <span class="mb-note">Dashboard</span> -->
					<h2>Eigene Cockpit-Ansicht <? echo 'sdfgsdfg ' .UserDomain .' '; echo implode(', ', cmx_ini_get_value( 'vip', 'instanzen' )); ?></h2>
					<p>Hier kannst du dein eigenes Layout frei aufbauen. Das Grid ist auf 8 Spalten ausgelegt und kann Karten mit 1 bis 8 Spalten Breite darstellen.</p>
					<div class="mb-demo-linechart" aria-label="Demo-Line-Chart">
						<div class="mb-demo-linechart-canvas">
							<canvas id="cmx-monitor-multi-axis-chart" aria-label="Demo-Multi-Axis-Line-Chart"></canvas>
						</div>
						<div class="mb-demo-linechart-stats">
							<div><strong><?php echo \esc_html(\number_format($current_umsatz, 0, '.', '\'')); ?></strong><span>Umsatz aktuell</span></div>
							<div><strong><?php echo \esc_html(($delta_umsatz >= 0 ? '+' : '') . \number_format($delta_umsatz, 1, '.', '\'')); ?>%</strong><span>zum Vormonat</span></div>
							<div><strong><?php echo \esc_html((string) $current_tickets); ?></strong><span>Tickets aktuell</span></div>
							<div><strong><?php echo \esc_html((string) \count((array) ($chart_payload['labels'] ?? []))); ?></strong><span>Datenpunkte</span></div>
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
			if (!canvas || typeof Chart === "undefined") return;

			var ctx = canvas.getContext("2d");
			if (!ctx) return;

			var payload = ' . \wp_json_encode($chart_payload) . ';
			new Chart(ctx, {
				type: "line",
				data: {
					labels: payload.labels,
					datasets: [
						{
							label: payload.datasets[0].label,
							data: payload.datasets[0].data,
							yAxisID: payload.datasets[0].yAxisID,
							borderColor: payload.datasets[0].borderColor,
							backgroundColor: payload.datasets[0].backgroundColor,
							fill: true,
							tension: 0.35,
							borderWidth: 3,
							pointRadius: 4,
							pointHoverRadius: 5
						},
						{
							label: payload.datasets[1].label,
							data: payload.datasets[1].data,
							yAxisID: payload.datasets[1].yAxisID,
							borderColor: payload.datasets[1].borderColor,
							backgroundColor: payload.datasets[1].backgroundColor,
							fill: false,
							tension: 0.35,
							borderWidth: 3,
							pointRadius: 4,
							pointHoverRadius: 5
						}
					]
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
							padding: 10
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
								color: "#4f86c6"
							},
							title: {
								display: true,
								text: "Umsatz"
							}
						},
						y1: {
							type: "linear",
							position: "right",
							grid: {
								drawOnChartArea: false
							},
							ticks: {
								color: "#ef7d00"
							},
							title: {
								display: true,
								text: "Tickets"
							}
						}
					}
				}
			});
		};

		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", init);
			return;
		}
		init();
	})();';
	\wp_add_inline_script('cmx-chartjs', $chart_script, 'after');
});
