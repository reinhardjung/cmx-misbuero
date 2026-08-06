<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * Dashboard-Widget: UMSATZ
 * - Zeigt eine kleine Übersicht über den erfassten Umsatz in CPT "kontakte"
 * - Passt die Widget-Überschrift exakt auf "UMSATZ"
 */

if (!defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_UMSATZ')) {
	define(__NAMESPACE__ . '\\CMX_KONTAKTE_META_UMSATZ', '_cmx_kontakte_umsatz');
}

\add_action('wp_dashboard_setup', __NAMESPACE__ . '\\cmx_register_umsatz_widget');
function cmx_register_umsatz_widget() {
	\wp_add_dashboard_widget('cmx_umsatz_widget','Rechnungen',__NAMESPACE__ . '\\cmx_render_umsatz_widget');
}

/**
 * Rendert das UMSATZ-Widget
 */
function cmx_render_umsatz_widget() {
	// Nur Nutzer mit Edit-Rechten sehen die Zahlen
	if (!\current_user_can('edit_posts')) {
		echo '<p>' . esc_html__('Keine Berechtigung.', 'default') . '</p>';
		return;
	}

	$range = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_requested_range')
		? (array) cmx_cockpit_requested_range()
		: ['from' => '', 'to' => ''];

	// Bezahlte Belege auswerten
	$q = new \WP_Query([
		'post_type'      => 'belege',
		'post_status'    => ['publish','private'],
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'tax_query'      => [
			[
				'taxonomy' => 'belege_kategorien',
				'field'    => 'slug',
				'terms'    => ['rechnung'],
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
		'fields' => 'ids',
	]);

	$count_belege   = 0;
	$sum_total      = 0.0;
	$kontakt_ids    = [];

	if ($q->have_posts() && function_exists(__NAMESPACE__.'\\cmxbu_get_beleg_positionen_calc')) {
		foreach ($q->posts as $bid) {
			if (
				\function_exists(__NAMESPACE__ . '\\cmx_cockpit_date_in_range')
				&& \function_exists(__NAMESPACE__ . '\\cmx_cockpit_paid_date')
				&& !cmx_cockpit_date_in_range(cmx_cockpit_paid_date((int) $bid), $range)
			) {
				continue;
			}

			$calc  = cmxbu_get_beleg_positionen_calc($bid);
			$total = isset($calc['total']) ? (float)$calc['total'] : 0.0;
			$sum_total += $total;
			$count_belege++;

			$kid = (int) \get_post_meta($bid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ID') ? CMX_BELEG_META_KONTAKT_ID : '_cmx_beleg_kontakt_id', true);
			if ($kid > 0) {
				$kontakt_ids[$kid] = true;
			}
		}
	}

	$count_kunden = count($kontakt_ids);
	$avg_beleg    = $count_belege > 0 ? ($sum_total / $count_belege) : 0.0;

	echo '<style>
		.cmx-umsatz-table													{ width:100%; border-collapse:collapse; }
		.cmx-umsatz-table th,.cmx-umsatz-table td	{ padding:6px 8px; text-align:left; }
		.cmx-umsatz-table tr:last-child td				{ border-bottom:none; }
		.cmx-umsatz-kpi														{ display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; margin:8px 0 14px; }
		.cmx-umsatz-kpi .k												{ padding:10px; border:1px solid #e5e5e5; border-radius:6px; background:#fafafa; }
		.cmx-umsatz-kpi .k .h											{ font-size:11px; color:#555; margin-bottom:4px; text-transform:uppercase; letter-spacing:.02em; }
		.cmx-umsatz-kpi .k .v											{ font-size:14px; font-weight:600;  }
	</style>';

	echo '<div class="cmx-umsatz-kpi">';
	echo '  <div class="k"><div class="h">Belege bezahlt</div><div class="v">'.esc_html(cmx_format_swiss_number($count_belege, 0)).'</div></div>';
	echo '  <div class="k"><div class="h">Kunden mit Belegen</div><div class="v">'.esc_html(cmx_format_swiss_number($count_kunden, 0)).'</div></div>';
	echo '  <div class="k"><div class="h">Umsatz gesamt</div><div class="v">CHF '.esc_html(cmx_format_swiss_number($sum_total, 2)).'</div></div>';
	echo '  <div class="k"><div class="h">Durchschnitt/Beleg</div><div class="v">CHF '.esc_html(cmx_format_swiss_number($avg_beleg, 2)).'</div></div>';
	echo '</div>';

	// Optional: Link zur Kontakte-Liste
	// $list_url = \admin_url('edit.php?post_type=kontakte');
	// echo '<p><a href="'.esc_url($list_url).'">'.esc_html__('Zu Kontakte', 'default').'</a></p>';
}
