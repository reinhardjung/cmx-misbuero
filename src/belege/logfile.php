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

	// $geo = cmxbu_fetch_geo_ip(preg_match('/^(10\.|127\.|172\.|192\.)/', $_SERVER['SERVER_ADDR'] ?? '') ? trim(@file_get_contents('https://api.ipify.org/')) : ($_SERVER['REMOTE_ADDR'] ?? ''));  // GEO-Daten holen
	$geo = cmxbu_fetch_geo_ip(preg_match('/^(10\.|127\.|172\.|192\.)/', $_SERVER['SERVER_ADDR'] ?? $_SERVER['REMOTE_ADDR']) ? trim(@file_get_contents('https://api.ipify.org/')) : ($_SERVER['REMOTE_ADDR'] ?? $_SERVER['REMOTE_ADDR']));  // GEO-Daten holen

	$log[] = [
		'time'     => current_time('mysql'),
		'ip'       => file_get_contents('https://api.ipify.org/'),
		'country'  => $geo['country'],
		'city'     => $geo['city'],
		'provider' => $geo['provider'],
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

	// Aufrufzähler holen (Beispiel: aus Post-Meta)
	$views = (int) get_post_meta(get_the_ID(), '_cmx_beleg_views', true);
	add_meta_box(
		'cmxbu_logfile_box',
		'' . esc_html($views) . ' Aufrufe',
		__NAMESPACE__ . '\\cmxbu_render_logfile_metabox',
		'belege',
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

	// echo '<div style="max-height:260px;overflow:auto;padding:6px;border:1px solid #ccc;">';
	echo '<div style="max-height:260px; overflow:auto; padding:6px;">';

	foreach (array_reverse($log) as $entry) {

		$time     = $entry['time']     ?? '';
		$ip       = $entry['ip']       ?? '';
		$country  = $entry['country']  ?? '';
		$city     = $entry['city']     ?? '';
		$provider = $entry['provider'] ?? '';

		$formatted = $time
			? date_i18n(get_option('date_format') . ' - ' . get_option('time_format'), strtotime($time))
			: '';

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
		echo '<div style="margin-bottom:8px;border-bottom:1px dashed #ccc;padding-bottom:6px;">
				<div>'.esc_html($formatted).' - '. esc_html($ip).'</div>
				<div>'.esc_html($country).'-'.esc_html($city).'</div>
				<div>'.esc_html($provider).'</div>
			  </div>';
	}

	echo '</div>';
}
