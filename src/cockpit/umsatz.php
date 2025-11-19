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
	\wp_add_dashboard_widget('cmx_umsatz_widget','Ums&auml;tze',__NAMESPACE__ . '\\cmx_render_umsatz_widget');
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

	// Alle Kontakte mit gesetztem Umsatz holen (nur veröffentlichte)
	$q = new \WP_Query(['post_type' => 'kontakte','post_status' => 'publish','posts_per_page' => -1,'no_found_rows' => true,'meta_query' => [['key' => CMX_KONTAKTE_META_UMSATZ,'compare' => 'EXISTS',]],'fields' => 'ids',]);

	$count_items = 0;
	$sum         = 0.0;
	if ($q->have_posts()) {
		foreach ($q->posts as $pid) {
			$raw = (string) \get_post_meta($pid, CMX_KONTAKTE_META_UMSATZ, true);
			$raw = str_replace(',', '.', $raw);
			if ($raw === '' || !is_numeric($raw)) continue;
			$sum += (float) $raw;
			$count_items++;
		}
	}
	$avg = $count_items > 0 ? ($sum / $count_items) : 0.0;

	echo '<style>
		.cmx-umsatz-table													{ width:100%; border-collapse:collapse; }
		.cmx-umsatz-table th,.cmx-umsatz-table td	{ padding:6px 8px; text-align:left; }
		.cmx-umsatz-table tr:last-child td				{ border-bottom:none; }
		.cmx-umsatz-kpi														{ display:flex; gap:12px; margin:8px 0 14px; }
		.cmx-umsatz-kpi .k												{ flex:1; padding:10px; border:1px solid #e5e5e5; border-radius:6px; background:#fafafa; }
		.cmx-umsatz-kpi .k .h											{ font-size:11px; color:#555; margin-bottom:4px; text-transform:uppercase; letter-spacing:.02em; }
		.cmx-umsatz-kpi .k .v											{ font-size:14px; font-weight:600;  }
	</style>';

	echo '<div class="cmx-umsatz-kpi">';
	echo '  <div class="k"><div class="h">'.esc_html__('Kunden', 'default').'</div><div class="v">'.esc_html(number_format_i18n($count_items)).'</div></div>';
	echo '  <div class="k"><div class="h">'.esc_html__('Summe', 'default').'</div><div class="v">'.esc_html(number_format_i18n($sum, 2)).'</div></div>';
	echo '  <div class="k"><div class="h">'.esc_html__('Durchschnitt', 'default').'</div><div class="v">'.esc_html(number_format_i18n($avg, 2)).'</div></div>';
	echo '</div>';

	// Optional: Link zur Kontakte-Liste
	// $list_url = \admin_url('edit.php?post_type=kontakte');
	// echo '<p><a href="'.esc_url($list_url).'">'.esc_html__('Zu Kontakte', 'default').'</a></p>';
}
