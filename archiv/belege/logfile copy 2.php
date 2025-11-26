<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


// GEO-Daten holen (City, Country, Provider)
function cmxbu_fetch_geo_ip(string $ip): array {
	var_dump($_SERVER['REMOTE_ADDR']); exit;


	$url = "https://ipinfo.io/{$ip}/json"; // 2) ipinfo.io (No API-Key needed for basic data): $url = "https://ipinfo.io/31.165.222.102/json";
	$data = json_decode(@file_get_contents($url), true);

if (is_array($data) && !isset($data['error'])) {
		return [
			'country'  => $data['country'] ?? 'Unknown',
			'city'     => $data['city'] ?? 'Unknown',
			'provider' => $data['org'] ?? ($data['hostname'] ?? 'Unknown'),
		];
	}

	return ['country' => '','city' => '','provider' => '']; // Fallback
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
	var_dump($geo); exit;


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
function cmxbu_register_logfile_metabox() {
	add_meta_box(
		'cmxbu_logfile_box',
		'Aufrufe',
		__NAMESPACE__ . '\\cmxbu_render_logfile_metabox',
		'belege',
		'side',
		'default'
	);
}
add_action('add_meta_boxes', __NAMESPACE__ . '\\cmxbu_register_logfile_metabox');



// Meta-Box Anzeige
function cmxbu_render_logfile_metabox(\WP_Post $post): void {
	$views = (int) get_post_meta($post->ID, '_cmx_beleg_views', true);
	$log   = get_post_meta($post->ID, '_cmx_beleg_views_log', true);

	if (!is_array($log)) $log = [];

	// echo '<div style="margin:8px 0;"><strong>Beleg-Aufrufe:</strong><br><span style="font-size:18px;color:#a42c24;font-weight:bold;">' . esc_html($views) . '</span></div><hr>';
	echo '<div style="text-align:center; margin:8px 0;"><span style="font-size:18px;color:#a42c24;font-weight:bold;">' . esc_html($views) . '</span></div>';

	echo '<div style="max-height:260px;overflow:auto;padding:6px;border:1px solid #ccc;">';

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
				<div>'.esc_html($formatted).'</div>
				<div>'.esc_html($ip).'</div>
				<div>'.esc_html($country).' / '.esc_html($city).'</div>
				<div>'.esc_html($provider).'</div>
			  </div>';
	}

	echo '</div>';
}
