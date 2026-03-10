<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

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
		';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_view_main_page')) {
	function cmx_render_view_main_page(): void {
		?>
		<div class="wrap mb-dashboard-wrap">
			<h1>Monitor</h1>
			<p class="mb-dashboard-intro">Eigenes Dashboard mit Karten, die 1 bis 8 Spalten breit sein können.</p>

			<div class="mb-grid">
				<section class="mb-card mb-card--hero mb-span-8">
					<span class="mb-note">Dashboard</span>
					<h2>Eigene Cockpit-Ansicht</h2>
					<p>Hier kannst du dein eigenes Layout frei aufbauen. Das Grid ist auf 8 Spalten ausgelegt und kann Karten mit 1 bis 8 Spalten Breite darstellen.</p>
				</section>

				<section class="mb-card mb-card--example mb-span-1">
					<h3>1 Spalte</h3>
					<p>Beispiel für <code>mb-span-1</code>.</p>
				</section>

				<section class="mb-card mb-card--example mb-span-2">
					<h3>2 Spalten</h3>
					<p>Beispiel für <code>mb-span-2</code>.</p>
				</section>

				<section class="mb-card mb-card--example mb-span-3">
					<h3>3 Spalten</h3>
					<p>Beispiel für <code>mb-span-3</code>.</p>
				</section>

				<section class="mb-card mb-card--example mb-span-4">
					<h3>4 Spalten</h3>
					<p>Beispiel für <code>mb-span-4</code>.</p>
				</section>

				<section class="mb-card mb-card--example mb-span-5">
					<h3>5 Spalten</h3>
					<p>Beispiel für <code>mb-span-5</code>.</p>
				</section>

				<section class="mb-card mb-card--example mb-span-6">
					<h3>6 Spalten</h3>
					<p>Beispiel für <code>mb-span-6</code>.</p>
				</section>

				<section class="mb-card mb-card--example mb-span-7">
					<h3>7 Spalten</h3>
					<p>Beispiel für <code>mb-span-7</code>.</p>
				</section>

				<section class="mb-card mb-card--example mb-span-8">
					<h3>8 Spalten</h3>
					<p>Beispiel für <code>mb-span-8</code>.</p>
				</section>

				<section class="mb-card mb-card--kpi mb-span-2">
					<span class="mb-kpi-value">CHF 1’240</span>
					<span class="mb-kpi-label">Umsatz heute</span>
				</section>

				<section class="mb-card mb-card--kpi mb-span-2">
					<span class="mb-kpi-value">18</span>
					<span class="mb-kpi-label">Offene Rechnungen</span>
				</section>

				<section class="mb-card mb-card--kpi mb-span-2">
					<span class="mb-kpi-value">7</span>
					<span class="mb-kpi-label">Neue Belege</span>
				</section>

				<section class="mb-card mb-card--kpi mb-span-2">
					<span class="mb-kpi-value">5</span>
					<span class="mb-kpi-label">Offene Buchungen</span>
				</section>

				<section class="mb-card mb-card--chart mb-span-4">
					<h2>Umsatzentwicklung</h2>
					<div class="mb-chart-placeholder">Hier kann später ein Chart oder Report hinein.</div>
				</section>

				<section class="mb-card mb-card--soft mb-span-4">
					<h2>Letzte Aktivitäten</h2>
					<ul class="mb-list">
						<li>
							<div>
								<strong>Rechnung #2026-001 erstellt</strong>
								<span>Heute, 09:14 Uhr</span>
							</div>
							<span class="mb-meta">neu</span>
						</li>
						<li>
							<div>
								<strong>Beleg importiert</strong>
								<span>Heute, 08:42 Uhr</span>
							</div>
							<span class="mb-meta">Scanner</span>
						</li>
						<li>
							<div>
								<strong>Zahlung zugeordnet</strong>
								<span>Gestern, 16:20 Uhr</span>
							</div>
							<span class="mb-meta">Bank</span>
						</li>
					</ul>
				</section>

				<section class="mb-card mb-span-2">
					<h2>Bankabgleich</h2>
					<div class="mb-stat">5</div>
					<p>offene Buchungen zur Kontrolle</p>
				</section>

				<section class="mb-card mb-span-2">
					<h2>Lieferanten</h2>
					<div class="mb-stat">3</div>
					<p>neue Dokumente zu prüfen</p>
				</section>

				<section class="mb-card mb-span-2">
					<h2>Export</h2>
					<div class="mb-stat">2</div>
					<p>Milchbüechli-Exporte bereit</p>
				</section>

				<section class="mb-card mb-card--soft mb-span-2">
					<h2>Hinweise</h2>
					<p>QR-Rechnungsmodul aktiv. Nächster Monatsabschluss steht noch aus.</p>
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

	$handle = 'cmx-cockpit-view-main';
	\wp_register_style($handle, false, [], '1.0');
	\wp_enqueue_style($handle);
	\wp_add_inline_style($handle, cmx_cockpit_view_main_css());
});
