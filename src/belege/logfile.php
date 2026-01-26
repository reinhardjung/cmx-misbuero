<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


// GEO-Daten holen (City, Country, Provider)
function cmxbu_fetch_geo_ip(string $ip): array {

	// Private IP-Bereiche erkennen
	$is_private = preg_match('/^(10|127|172|192)\./', $_SERVER['REMOTE_ADDR'] ?? '');

	// Wenn REMOTE_ADDR öffentlich ist → diese nehmen, sonst die übergebene $ip
	$final_ip = $is_private ? $ip : ($_SERVER['REMOTE_ADDR'] ?? $ip);

	// Geo-Daten abrufen
	$url  = "https://ipinfo.io/{$final_ip}/json";
	$data = json_decode(@file_get_contents($url), true);

	if (is_array($data) && !isset($data['error'])) {
		return [
			'country'  => $data['country']  ?? 'Unknown',
			'city'     => $data['city']     ?? 'Unknown',
			'provider' => $data['org']      ?? ($data['hostname'] ?? 'Unknown'),
		];
	}

	// Fallback
	return [
		'country'  => '',
		'city'     => '',
		'provider' => ''
	];
}


// Logging einer Beleg-Ansicht
function cmxbu_log_beleg_view(int $post_id): void {
	if ($post_id <= 0) return;

	// Counter
	$count = (int) get_post_meta($post_id, '_cmx_beleg_views', true);
	update_post_meta($post_id, '_cmx_beleg_views', $count + 1);

	// Log laden
	$log = get_post_meta($post_id, '_cmx_beleg_views_log', true);
	if (!is_array($log)) $log = [];

	$log[] = [
		'time'     => current_time('mysql'),
	];

	update_post_meta($post_id, '_cmx_beleg_views_log', $log);
}



// Frontend-Aufrufe tracken
function cmxbu_track_beleg_views() {
	// fixme rju 2025-11-26: Evtl. wieder raus nehen?
	// if (is_admin()) return;
	if (!is_singular('belege')) return;
	cmxbu_log_beleg_view(get_queried_object_id());
}
add_action('template_redirect', __NAMESPACE__ . '\\cmxbu_track_beleg_views');



// Meta-Box registrieren
add_action('add_meta_boxes', __NAMESPACE__ . '\\cmxbu_register_logfile_metabox');
function cmxbu_register_logfile_metabox() {

	$post = get_post();
	if (!$post || $post->post_type !== 'belege') return;

	$views = (int) get_post_meta($post->ID, '_cmx_beleg_views', true);
	if ($views <= 0) return; // nur anzeigen, wenn es mindestens einen Log-Eintrag gibt

	add_meta_box(
		'cmxbu_logfile_box',
		'' . esc_html($views) . ' Aufrufe',
		__NAMESPACE__ . '\\cmxbu_render_logfile_metabox',
		$post->post_type,
		'side',
		'default'
	);
}


// Meta-Box Anzeige
function cmxbu_render_logfile_metabox(\WP_Post $post): void {
	$views = (int) get_post_meta($post->ID, '_cmx_beleg_views', true);
	$log   = get_post_meta($post->ID, '_cmx_beleg_views_log', true);

	if (!is_array($log)) $log = [];

	// echo '<div style="margin:8px 0;"><strong>Beleg-Aufrufe:</strong><br><span style="font-size:18px;color:#a42c24;font-weight:bold;">' . esc_html($views) . '</span></div><hr>';
	// echo '<div style="text-align:center; margin:8px 0;"><span style="font-size:18px;color:#a42c24;font-weight:bold;">' . esc_html($views) . '</span></div>';

	echo '<div style="max-height:260px; overflow:auto; padding:6px;">';
	echo '<table style="width:100%; border-collapse:collapse;">';
	echo '<thead><tr>';
	echo '<th style="text-align:left; padding:4px 0 6px 30px; border-bottom:1px solid #e0e0e0;">Datum</th>';
	echo '<th style="text-align:left; padding:4px 6px; border-bottom:1px solid #e0e0e0;">Uhrzeit</th>';
	echo '</tr></thead><tbody>';

	foreach (array_reverse($log) as $entry) {

		$time     = $entry['time']     ?? '';
		$ts = $time ? strtotime($time) : 0;
		$date = $ts ? date_i18n(get_option('date_format'), $ts) : '';
		$clock = $ts ? date_i18n('H:i', $ts) : '';

		// $gmaps = (!empty($city) && !empty($country) && strtolower($city) !== 'unknown')
		// 	? ' <a href="https://www.google.com/maps/search/?api=1&query='
		// 	  . urlencode($city.', '.$country)
		// 	  . '" target="_blank">(Map)</a>'
		// 	: '';

	// 	echo '<div style="margin-bottom:8px;border-bottom:1px dashed #ccc;padding-bottom:6px;">
	// 			<div><strong>Zeit:</strong> '.esc_html($formatted).'</div>
	// 			<div><strong>IP:</strong> '.esc_html($ip).'</div>
	// 			<div><strong>Land/Stadt:</strong> '.esc_html($country).' / '.esc_html($city).$gmaps.'</div>
	// 			<div><strong>Provider:</strong> '.esc_html($provider).'</div>
	// 		  </div>';
	// }
		if (!$ts) {
			continue;
		}
		echo '<tr>';
		echo '<td style="padding:4px 6px; border-bottom:1px dashed #e6e6e6;">' . esc_html($date) . '</td>';
		echo '<td style="padding:4px 0 6px 10px; border-bottom:1px dashed #e6e6e6;">' . esc_html($clock) . '</td>';
		echo '</tr>';
	}

	echo '</tbody></table>';
	echo '</div>';
}
