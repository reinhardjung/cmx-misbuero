<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Dashboard-Widget: Kuchen Einnahmen/Ausgaben
 * Zeigt ein einfaches Kuchen-Diagramm für Rechnungen, Lieferantenrechnungen und Gutschriften.
 */

add_action('wp_dashboard_setup', function () {
	wp_add_dashboard_widget(
		'cmx_kuchen_ein_aus',
		'Kuchen: Rechnungen / Lieferanten / Gutschriften',
		__NAMESPACE__ . '\\cmx_render_kuchen_ein_aus'
	);
});

function cmx_enqueue_chartjs(): void {
	if (wp_script_is('cmx-chartjs', 'enqueued')) {
		return;
	}
	// Versuche lokale Vendor-Datei, fallback CDN
	$plugin_main = dirname(__DIR__, 2) . '/cmx-misbuero.php';
	$local = plugins_url('assets/vendor/chartjs/chart.umd.min.js', $plugin_main);
	wp_register_script('cmx-chartjs', $local, [], '4.4.1', true);
	wp_enqueue_script('cmx-chartjs');
}

function cmx_render_kuchen_ein_aus(): void {
	if (!current_user_can('edit_posts')) {
		echo '<p>' . esc_html__('Keine Berechtigung.', 'default') . '</p>';
		return;
	}

	if (!function_exists(__NAMESPACE__.'\\cmxbu_get_beleg_positionen_calc')) {
		echo '<p><em>Summen-Berechnung nicht verfügbar.</em></p>';
		return;
	}

	cmx_enqueue_chartjs();

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

	// Prozentwerte für conic-gradient
	$start = 0;
	$stops = [];
	foreach ($results as $slug => $data) {
		$share = ($total_sum > 0) ? ($data['sum'] / $total_sum) : 0;
		$deg   = $share * 360;
		$end   = $start + $deg;
		$stops[] = sprintf('%s %.2fdeg %.2fdeg', $data['color'], $start, $end);
		$start = $end;
	}
	$gradient = $stops ? 'conic-gradient(' . implode(',', $stops) . ')' : '#f5f5f5';

	echo '<style>
		.cmx-pie-wrap{display:flex;align-items:center;gap:16px;}
		.cmx-pie-canvas{width:180px;height:180px;}
		.cmx-pie-legend{flex:1;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px 12px;}
		.cmx-pie-item{display:flex;align-items:center;gap:6px;font-size:13px;}
		.cmx-pie-dot{width:12px;height:12px;border-radius:50%;}
		.cmx-pie-val{font-weight:600;}
	</style>';

	echo '<div class="cmx-pie-wrap">';
	echo '<canvas class="cmx-pie-canvas" id="cmxPieChart" aria-label="Kuchendiagramm"></canvas>';
	echo '<div class="cmx-pie-legend">';
	foreach ($results as $data) {
		echo '<div class="cmx-pie-item">';
		echo '<span class="cmx-pie-dot" style="background:'.esc_attr($data['color']).'"></span>';
		echo '<span>'.esc_html($data['label']).'</span>';
		echo '<span class="cmx-pie-val">CHF '.esc_html(number_format_i18n($data['sum'], 2)).'</span>';
		echo '<span style="color:#777;">'.esc_html($data['count']).' Stück</span>';
		echo '</div>';
	}
	echo '</div></div>';

	$labels = [];
	$data_vals = [];
	$colors = [];
	foreach ($results as $data) {
		$labels[]   = $data['label'];
		$data_vals[] = round($data['sum'], 2);
		$colors[]   = $data['color'];
	}

	echo '<script>';
	echo 'document.addEventListener("DOMContentLoaded",function(){';
	echo 'const ctx=document.getElementById("cmxPieChart"); if(!ctx||!window.Chart) return;';
	echo 'new Chart(ctx,{type:"pie",data:{labels:'.wp_json_encode($labels).',datasets:[{data:'.wp_json_encode($data_vals).',backgroundColor:'.wp_json_encode($colors).'}]},options:{plugins:{legend:{display:false}},responsive:true,maintainAspectRatio:false}});';
	echo '});';
	echo '</script>';
}
