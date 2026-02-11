<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

use ChartsPhp\ChartsPhp;

/**
 * Dashboard-Widget: Kuchen Einnahmen/Ausgaben
 * Zeigt ein einfaches Kuchen-Diagramm für Rechnungen, Lieferantenrechnungen und Gutschriften.
 */

add_action('wp_dashboard_setup', function () {
	wp_add_dashboard_widget(
		'cmx_kuchen_ein_aus_nok',
		'noch offen',
		__NAMESPACE__ . '\\cmx_render_kuchen_ein_aus_nok'
	);
});

if (!function_exists(__NAMESPACE__ . '\\cmx_enqueue_chartjs')) {
	function cmx_enqueue_chartjs(): void {
		if (wp_script_is('cmx-chartjs', 'enqueued')) {
			return;
		}
		// Lokale Vendor-Datei (mikuspetr)
		$plugin_main = dirname(__DIR__, 2) . '/cmx-misbuero.php';
		$local = plugins_url('vendor/mikuspetr/chartjs/chart.umd.min.js', $plugin_main);
		wp_register_script('cmx-chartjs', $local, [], '4.4.1', true);
		wp_enqueue_script('cmx-chartjs');
	}
}

function cmx_render_kuchen_ein_aus_nok(): void {
	if (!current_user_can('edit_posts')) {
		echo '<p>' . esc_html__('Keine Berechtigung.', 'default') . '</p>';
		return;
	}

	if (!function_exists(__NAMESPACE__.'\\cmxbu_get_beleg_positionen_calc')) {
		echo '<p><em>Summen-Berechnung nicht verfügbar.</em></p>';
		return;
	}

	$taxonomy = 'belege_kategorien';
	$terms = [
		'rechnung'             => ['label' => 'Rechnungen',            'color' => '#1e88e5'],
		'lieferantenrechnung'  => ['label' => 'Lieferantenrechnungen', 'color' => '#e53935'],
		'gutschrift'           => ['label' => 'Gutschriften',          'color' => '#43a047'],
	];

	$results = [];
	$total_sum = 0.0;

	foreach ($terms as $slug => $meta) {
		$q = new \WP_Query([
			'post_type'      => 'belege',
			'post_status'    => ['publish','private'],
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'tax_query'      => [
				[
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => [$slug],
					'operator' => 'IN',
				],
			],
			'meta_query' => [
				'relation' => 'OR',
				[
					'key'     => '_cmx_beleg_bezahlt_am',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_cmx_beleg_bezahlt_am',
					'value'   => '',
					'compare' => '=',
				],
			],
		]);

		$count = 0;
		$sum   = 0.0;
		if ($q->have_posts()) {
			foreach ($q->posts as $pid) {
				$calc  = cmxbu_get_beleg_positionen_calc($pid);
				$sum  += isset($calc['total']) ? (float)$calc['total'] : 0.0;
				$count++;
			}
		}

		$results[$slug] = [
			'label' => $meta['label'],
			'color' => $meta['color'],
			'count' => $count,
			'sum'   => $sum,
		];
		$total_sum += $sum;
	}

	$labels    = array_values(array_column($results, 'label'));
	$data_vals = array_values(array_map(fn($r) => round($r['sum'], 2), $results));
	$colors    = array_values(array_column($results, 'color'));

	// Chart mittels ChartsPhp erzeugen (Pie)
	$chart = ChartsPhp::createChart('pie', $labels, [
		[
			'label'           => 'Summe',
			'data'            => array_values($data_vals),
			'backgroundColor' => array_values($colors),
		]
	], [
		'plugins' => [
			'legend' => ['display' => false],
		],
		'responsive' => true,
		'maintainAspectRatio' => false,
	]);

	echo '<style>
		.cmx-pie-wrap  { display:flex;align-items:flex-start;gap:20px; }
		.cmx-pie-left  { width:150px; height:150px; flex:none; margin-top:-70px; }
		.cmx-pie-right { flex:1; display:flex; flex-direction:column; gap:8px; padding-top:4px; }
		.cmx-row       { display:flex; flex-direction:column; gap:2px; font-size:12px; line-height:1.2; }
		.cmx-row-main  { display:flex; align-items:center; gap:6px; }
		.cmx-row-label { font-weight:600; }
		.cmx-dot       { width:9px; height:9px; border-radius:50%; flex:none; }
		.cmx-val       { font-weight:700; margin-left:15px; }
		.cmx-count     { color:#666; font-size:11px; margin-left:15px; }
	</style>';

	echo '<div class="cmx-pie-wrap">';
	echo '<div class="cmx-pie-left">'.$chart->renderHtml().'</div>';
	echo '<div class="cmx-pie-right">';
	foreach ($results as $data) {
		echo '<div class="cmx-row">';
		echo '  <div class="cmx-row-main">';
		echo '    <span class="cmx-dot" style="background:'.esc_attr($data['color']).'"></span>';
		echo '    <span class="cmx-row-label">'.esc_html($data['label']).'</span>';
		echo '  </div>';
		echo '  <div class="cmx-val">CHF '.esc_html(cmx_format_swiss_number($data['sum'], 2)).'</div>';
		echo '  <div class="cmx-count">'.esc_html($data['count']).' Stück</div>';
		echo '</div>';
	}
	echo '</div></div>';

	// ChartJS einbinden (lokale Vendor-Datei) + ChartsPhp-Script
	$plugin_main = dirname(__DIR__, 2) . '/cmx-misbuero.php';
	$chartjs_url = plugins_url('vendor/mikuspetr/chartjs/chart.umd.min.js', $plugin_main);
	echo '<script src="'.esc_url($chartjs_url).'"></script>';
	echo $chart->renderScript();
}
