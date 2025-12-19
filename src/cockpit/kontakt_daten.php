<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Dashboard-Widget: Wichtiges Datum
 * Zeigt die nächsten 5 Kontakte mit gesetztem Datum (aufsteigend sortiert).
 */

if (!defined(__NAMESPACE__.'\\CMX_KONTAKTE_META_DATUM')) {
	define(__NAMESPACE__.'\\CMX_KONTAKTE_META_DATUM', '_cmx_kontakte_datum');
}
if (!defined(__NAMESPACE__.'\\CMX_KONTAKTE_META_PRIVAT')) {
	define(__NAMESPACE__.'\\CMX_KONTAKTE_META_PRIVAT', '_cmx_kontakte_privat');
}

add_action('wp_dashboard_setup', function () {
	\wp_add_dashboard_widget(
		'cmx_kontakt_wichtige_daten',
		'Wichtiges Datum',
		__NAMESPACE__ . '\\cmx_render_kontakt_wichtige_daten'
	);
});

function cmx_render_kontakt_wichtige_daten(): void {
	if (!current_user_can('edit_posts')) {
		echo '<p>' . esc_html__('Keine Berechtigung.', 'default') . '</p>';
		return;
	}

	$q = new \WP_Query([
		'post_type'      => 'kontakte',
		'post_status'    => ['publish','private'],
		'posts_per_page' => 5,
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_key'       => CMX_KONTAKTE_META_DATUM,
		'meta_type'      => 'DATE',
		'meta_query'     => [
			[
				'key'     => CMX_KONTAKTE_META_DATUM,
				'value'   => '',
				'compare' => '!=',
			],
		],
	]);

	echo '<style>
		#cmx_kontakt_wichtige_daten .cmx-wd-list { margin:0; padding:0; list-style:none; }
		#cmx_kontakt_wichtige_daten .cmx-wd-item { padding:6px 0; border-bottom:1px solid #f0f0f0; }
		#cmx_kontakt_wichtige_daten .cmx-wd-item:last-child { border-bottom:none; }
		#cmx_kontakt_wichtige_daten .cmx-wd-title { font-weight:600; display:block; }
		#cmx_kontakt_wichtige_daten .cmx-wd-date { color:#555; font-size:12px; }
	</style>';

	if (!$q->have_posts()) {
		echo '<p><em>' . esc_html__('Kein Datum hinterlegt.', 'default') . '</em></p>';
		return;
	}

	echo '<ul class="cmx-wd-list">';
	while ($q->have_posts()) {
		$q->the_post();
		$pid   = get_the_ID();
		$title = get_the_title($pid);
		$date  = (string) get_post_meta($pid, CMX_KONTAKTE_META_DATUM, true);
		$disp  = $date;
		if ($date !== '') {
			$tz = wp_timezone();
			$dt = \DateTime::createFromFormat('Y-m-d', $date, $tz);
			if ($dt) {
				$disp = wp_date('d.m.Y', $dt->getTimestamp(), $tz);
			}
		}

		$type_label = '';
		$privat = (bool) get_post_meta($pid, CMX_KONTAKTE_META_PRIVAT, true);
		if (!$privat) {
			// Fallback auf alten Key
			$privat = (bool) get_post_meta($pid, '_cmx_privat', true);
		}
		$type_label = $privat ? 'Geburtsdatum' : 'Firmengründung';

		echo '<li class="cmx-wd-item">';
		echo '<div style="display:flex;justify-content:space-between;align-items:center;">';
		echo '<a class="cmx-wd-title" href="' . esc_url(get_edit_post_link($pid)) . '">' . esc_html($title) . '</a>';
		echo '<span class="cmx-wd-type" style="font-size:12px;color:#777;">' . esc_html($type_label) . '</span>';
		echo '</div>';
		echo '<span class="cmx-wd-date">' . esc_html($disp) . '</span>';
		echo '</li>';
	}
	echo '</ul>';

	wp_reset_postdata();
}
