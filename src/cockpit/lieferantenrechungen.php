<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Dashboard-Widget: Lieferantenrechnungen
 * Zeigt zwei Kennzahlen:
 * - bezahlt: Anzahl und Summe aller bezahlten Lieferantenrechnungen
 * - offen:   Anzahl und Summe aller offenen Lieferantenrechnungen
 */

add_action('wp_dashboard_setup', function () {
	wp_add_dashboard_widget(
		'cmx_lieferanten_widget',
		'Lieferantenrechnungen',
		__NAMESPACE__ . '\\cmx_render_lieferanten_widget'
	);
});

function cmx_render_lieferanten_widget(): void {
	if (!current_user_can('edit_posts')) {
		echo '<p>' . esc_html__('Keine Berechtigung.', 'default') . '</p>';
		return;
	}

	$taxonomy = 'belege_kategorien';
	$term_slug = 'lieferantenrechnung';

	// Helper zum Summieren
	$calc_total = function(array $args) {
		$q = new \WP_Query($args);
		$count = 0;
		$sum = 0.0;
		if ($q->have_posts() && function_exists(__NAMESPACE__.'\\cmxbu_get_beleg_positionen_calc')) {
			foreach ($q->posts as $pid) {
				$calc = cmxbu_get_beleg_positionen_calc($pid);
				$total = isset($calc['total']) ? (float)$calc['total'] : 0.0;
				$sum += $total;
				$count++;
			}
		}
		return [$count, $sum];
	};

	// Bezahlt: Meta vorhanden und nicht leer
	[$paid_count, $paid_sum] = $calc_total([
		'post_type'      => 'belege',
		'post_status'    => ['publish','private'],
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'fields'         => 'ids',
		'tax_query'      => [
			[
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => [$term_slug],
				'operator' => 'IN',
			],
		],
		'meta_query'     => [
			[
				'key'     => '_cmx_beleg_bezahlt_am',
				'compare' => 'EXISTS',
			],
			[
				'key'     => '_cmx_beleg_bezahlt_am',
				'value'   => '',
				'compare' => '!=',
			],
		],
	]);

	// Offen: kein oder leeres Bezahlt-Datum
	[$open_count, $open_sum] = $calc_total([
		'post_type'      => 'belege',
		'post_status'    => ['publish','private'],
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'fields'         => 'ids',
		'tax_query'      => [
			[
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => [$term_slug],
				'operator' => 'IN',
			],
		],
		'meta_query'     => [
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

	echo '<style>
		.cmx-lr-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:6px;}
		.cmx-lr-box{border:1px solid #e5e5e5;border-radius:6px;padding:10px;background:#fafafa;}
		.cmx-lr-title{font-size:12px;text-transform:uppercase;letter-spacing:.02em;color:#555;margin-bottom:6px;}
		.cmx-lr-val{font-size:14px;font-weight:600;line-height:1.4;}
		.cmx-lr-sub{color:#777;font-size:12px;}
	</style>';

	echo '<div class="cmx-lr-grid">';
	echo '  <div class="cmx-lr-box">';
	echo '    <div class="cmx-lr-title">Bezahlte</div>';
	echo '    <div class="cmx-lr-val">'.esc_html(number_format_i18n($paid_count)).'</div>';
	echo '    <div class="cmx-lr-sub">CHF '.esc_html(number_format_i18n($paid_sum, 2)).'</div>';
	echo '  </div>';
	echo '  <div class="cmx-lr-box">';
	echo '    <div class="cmx-lr-title">Offene</div>';
	echo '    <div class="cmx-lr-val">'.esc_html(number_format_i18n($open_count)).'</div>';
	echo '    <div class="cmx-lr-sub">CHF '.esc_html(number_format_i18n($open_sum, 2)).'</div>';
	echo '  </div>';
	echo '</div>';
}
