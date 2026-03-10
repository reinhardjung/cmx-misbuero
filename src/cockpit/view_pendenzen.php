<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_slug')) {
	function cmx_cockpit_pendenzen_slug(): string {
		return 'cmx-cockpit-pendenzen';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_css')) {
	function cmx_cockpit_pendenzen_css(): string {
		return '
			.mb-pendenzen-wrap{margin:20px 0 0;padding:0 20px 0 0;box-sizing:border-box}
			.mb-pendenzen-intro{margin:6px 0 18px;color:#5c6773;font-size:14px}
			.mb-pendenzen-grid{
				display:grid;
				grid-template-columns:repeat(8,minmax(0,1fr));
				gap:18px;
				align-items:start;
			}
			.mb-pendenzen-card{
				background:#fff;
				border:1px solid #d7dce3;
				border-radius:14px;
				padding:18px;
				box-sizing:border-box;
				box-shadow:0 1px 2px rgba(16,24,40,.04),0 10px 24px rgba(16,24,40,.04);
			}
			.mb-pendenzen-card h2,
			.mb-pendenzen-card h3{
				margin:0 0 12px;
				font-size:15px;
				line-height:1.35;
				color:#162033;
			}
			.mb-pendenzen-card p{margin:0;color:#4e5968}
			.mb-pendenzen-card--hero{
				background:linear-gradient(135deg,#fffaf1 0%,#ffffff 54%,#f8fbff 100%);
				border-color:#eadbb8;
			}
			.mb-pendenzen-card--example{
				background:linear-gradient(180deg,#ffffff 0%,#fffaf3 100%);
				border-style:dashed;
				border-color:#ead7b6;
			}
			.mb-pendenzen-card--example p{
				font-size:13px;
				color:#6a6459;
			}
			.mb-pendenzen-badge{
				display:inline-flex;
				align-items:center;
				padding:4px 10px;
				border-radius:999px;
				background:#fff3d7;
				border:1px solid #f1d595;
				color:#8a6115;
				font-size:12px;
				font-weight:600;
				margin-bottom:10px;
			}
			.mb-pendenzen-kpi{
				display:block;
				margin-bottom:8px;
				font-size:32px;
				line-height:1.1;
				font-weight:700;
				color:#162033;
				letter-spacing:-.03em;
			}
			.mb-pendenzen-label{
				display:block;
				font-size:13px;
				color:#667085;
			}
			.mb-pendenzen-list{
				margin:0;
				padding:0;
				list-style:none;
			}
			.mb-pendenzen-list li{
				display:flex;
				align-items:flex-start;
				justify-content:space-between;
				gap:12px;
				padding:10px 0;
				border-top:1px solid #eef2f6;
			}
			.mb-pendenzen-list li:first-child{padding-top:0;border-top:0}
			.mb-pendenzen-list strong{display:block;color:#162033}
			.mb-pendenzen-list span{display:block;color:#667085;font-size:12px}
			.mb-pendenzen-note{
				font-size:12px;
				color:#667085;
				white-space:nowrap;
			}
			.mb-pendenzen-panel{
				min-height:230px;
			}
			.mb-pendenzen-panel .mb-pendenzen-placeholder{
				display:flex;
				align-items:center;
				justify-content:center;
				min-height:162px;
				border:1px dashed #d8dee8;
				border-radius:12px;
				background:linear-gradient(180deg,#fcfdff 0%,#f7f9fc 100%);
				color:#667085;
				font-size:14px;
			}
			.mb-pendenzen-stat{
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
				.mb-pendenzen-grid{grid-template-columns:repeat(6,minmax(0,1fr))}
				.mb-span-7,
				.mb-span-8{grid-column:span 6}
			}
			@media (max-width: 1280px){
				.mb-pendenzen-grid{grid-template-columns:repeat(4,minmax(0,1fr))}
				.mb-span-5,
				.mb-span-6,
				.mb-span-7,
				.mb-span-8{grid-column:span 4}
			}
			@media (max-width: 980px){
				.mb-pendenzen-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
				.mb-span-3,
				.mb-span-4,
				.mb-span-5,
				.mb-span-6,
				.mb-span-7,
				.mb-span-8{grid-column:span 2}
			}
			@media (max-width: 782px){
				.mb-pendenzen-grid{grid-template-columns:1fr}
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_pendenzen_page')) {
	function cmx_render_pendenzen_page(): void {
		?>
		<div class="wrap mb-pendenzen-wrap">
			<h1>Pendenzen</h1>
			<p class="mb-pendenzen-intro">Eigene Arbeitsansicht mit Karten, die 1 bis 8 Spalten breit sein können.</p>

			<div class="mb-pendenzen-grid">
				<section class="mb-pendenzen-card mb-pendenzen-card--hero mb-span-8">
					<span class="mb-pendenzen-badge">Pendenzen</span>
					<h2>Offene Aufgaben im Blick</h2>
					<p>Hier kannst du später deine offenen Rechnungen, Freigaben, Rückfragen und Prüfungen gezielt bündeln.</p>
				</section>

				<section class="mb-pendenzen-card mb-pendenzen-card--example mb-span-1">
					<h3>1 Spalte</h3>
					<p>Beispiel für <code>mb-span-1</code>.</p>
				</section>

				<section class="mb-pendenzen-card mb-pendenzen-card--example mb-span-2">
					<h3>2 Spalten</h3>
					<p>Beispiel für <code>mb-span-2</code>.</p>
				</section>

				<section class="mb-pendenzen-card mb-pendenzen-card--example mb-span-3">
					<h3>3 Spalten</h3>
					<p>Beispiel für <code>mb-span-3</code>.</p>
				</section>

				<section class="mb-pendenzen-card mb-pendenzen-card--example mb-span-4">
					<h3>4 Spalten</h3>
					<p>Beispiel für <code>mb-span-4</code>.</p>
				</section>

				<section class="mb-pendenzen-card mb-pendenzen-card--example mb-span-5">
					<h3>5 Spalten</h3>
					<p>Beispiel für <code>mb-span-5</code>.</p>
				</section>

				<section class="mb-pendenzen-card mb-pendenzen-card--example mb-span-6">
					<h3>6 Spalten</h3>
					<p>Beispiel für <code>mb-span-6</code>.</p>
				</section>

				<section class="mb-pendenzen-card mb-pendenzen-card--example mb-span-7">
					<h3>7 Spalten</h3>
					<p>Beispiel für <code>mb-span-7</code>.</p>
				</section>

				<section class="mb-pendenzen-card mb-pendenzen-card--example mb-span-8">
					<h3>8 Spalten</h3>
					<p>Beispiel für <code>mb-span-8</code>.</p>
				</section>

				<section class="mb-pendenzen-card mb-span-2">
					<span class="mb-pendenzen-kpi">12</span>
					<span class="mb-pendenzen-label">offene Pendenzen</span>
				</section>

				<section class="mb-pendenzen-card mb-span-2">
					<span class="mb-pendenzen-kpi">4</span>
					<span class="mb-pendenzen-label">überfällige Punkte</span>
				</section>

				<section class="mb-pendenzen-card mb-span-2">
					<span class="mb-pendenzen-kpi">7</span>
					<span class="mb-pendenzen-label">warten auf Antwort</span>
				</section>

				<section class="mb-pendenzen-card mb-span-2">
					<span class="mb-pendenzen-kpi">3</span>
					<span class="mb-pendenzen-label">heute abschliessen</span>
				</section>

				<section class="mb-pendenzen-card mb-pendenzen-panel mb-span-4">
					<h2>Dringend</h2>
					<ul class="mb-pendenzen-list">
						<li>
							<div>
								<strong>Freigabe Monatsabschluss</strong>
								<span>heute</span>
							</div>
							<span class="mb-pendenzen-note">Buchhaltung</span>
						</li>
						<li>
							<div>
								<strong>Lieferantenrechnung prüfen</strong>
								<span>morgen</span>
							</div>
							<span class="mb-pendenzen-note">Eingang</span>
						</li>
						<li>
							<div>
								<strong>Kundenrückfrage beantworten</strong>
								<span>diese Woche</span>
							</div>
							<span class="mb-pendenzen-note">Support</span>
						</li>
					</ul>
				</section>

				<section class="mb-pendenzen-card mb-pendenzen-panel mb-span-4">
					<h2>Geplant</h2>
					<div class="mb-pendenzen-placeholder">Hier kann später eine Prioritätenliste oder ein Kalenderblock hinein.</div>
				</section>

				<section class="mb-pendenzen-card mb-span-2">
					<h2>Rückfragen</h2>
					<div class="mb-pendenzen-stat">6</div>
					<p>offene Antworten von Kunden oder Lieferanten</p>
				</section>

				<section class="mb-pendenzen-card mb-span-2">
					<h2>Freigaben</h2>
					<div class="mb-pendenzen-stat">2</div>
					<p>warten auf Bestätigung oder Abschluss</p>
				</section>

				<section class="mb-pendenzen-card mb-span-4">
					<h2>Hinweise</h2>
					<p>Diese Seite ist als separates Dashboard vorbereitet und kann jetzt mit echten Pendenzen-Daten oder Widgets gefüllt werden.</p>
				</section>
			</div>
		</div>
		<?php
	}
}

\add_action('admin_menu', function (): void {
	\add_submenu_page(
		'index.php',
		'Pendenzen',
		'Pendenzen',
		'edit_posts',
		cmx_cockpit_pendenzen_slug(),
		__NAMESPACE__ . '\\cmx_render_pendenzen_page',
		1
	);
});

\add_action('admin_enqueue_scripts', function (string $hook): void {
	if ($hook !== 'dashboard_page_' . cmx_cockpit_pendenzen_slug()) {
		return;
	}

	$handle = 'cmx-cockpit-pendenzen';
	\wp_register_style($handle, false, [], '1.0');
	\wp_enqueue_style($handle);
	\wp_add_inline_style($handle, cmx_cockpit_pendenzen_css());
});
