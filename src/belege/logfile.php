<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');


/**
 * Echte Besucher-IP holen (funktioniert hinter allen Proxys)
 */
function cmxbu_get_real_ip(): string {

	$headers = [
		'HTTP_CLIENT_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_FORWARDED',
		'HTTP_X_CLUSTER_CLIENT_IP',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
		'HTTP_CF_CONNECTING_IP',
		'HTTP_X_REAL_IP',
	];

	foreach ($headers as $key) {
		if (!empty($_SERVER[$key])) {
			$ip_list = explode(',', $_SERVER[$key]);

			foreach ($ip_list as $ip) {
				$ip = trim($ip);

				// Nur öffentliche IPs zulassen
				if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
					return $ip;
				}
			}
		}
	}

	// REMOTE_ADDR nur dann, wenn öffentlich
	if (isset($_SERVER['REMOTE_ADDR']) &&
		filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
	) {
		return $_SERVER['REMOTE_ADDR'];
	}

	return '0.0.0.0';
}



/**
 * GEO-Daten holen (City, Country, Provider)
 */
function cmxbu_fetch_geo_ip(string $ip): array {

	// Fallback für Local-IP
	if ($ip === '0.0.0.0') {
		return [
			'country'  => 'Unknown',
			'city'     => 'Unknown',
			'provider' => 'Unknown',
		];
	}

	$url = "https://ipapi.co/{$ip}/json/";

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 5);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	$response = curl_exec($ch);
	curl_close($ch);

	$data = json_decode($response, true);

	return [
		'country'  => $data['country_name'] ?? 'Unknown',
		'city'     => $data['city'] ?? 'Unknown',
		'provider' => $data['org'] ?? 'Unknown',
	];
}



/**
 * Logging einer Beleg-Ansicht
 */
function cmxbu_log_beleg_view(int $post_id): void {

	if ($post_id <= 0) return;

	// Counter
	$count = (int) get_post_meta($post_id, '_cmx_beleg_views', true);
	update_post_meta($post_id, '_cmx_beleg_views', $count + 1);

	// Log laden
	$log = get_post_meta($post_id, '_cmx_beleg_views_log', true);
	if (!is_array($log)) $log = [];

	$ip  = cmxbu_get_real_ip();      // ECHTE Besucher-IP 🔥
	$geo = cmxbu_fetch_geo_ip($ip);  // GEO-Daten holen 🔥

	$log[] = [
		'time'     => current_time('mysql'),
		'ip'       => $ip,
		'country'  => $geo['country'],
		'city'     => $geo['city'],
		'provider' => $geo['provider'],
	];

	update_post_meta($post_id, '_cmx_beleg_views_log', $log);
}



/**
 * Frontend-Aufrufe tracken
 */
function cmxbu_track_beleg_views() {
	if (is_admin()) return;
	if (!is_singular('belege')) return;

	cmxbu_log_beleg_view(get_queried_object_id());
}
add_action('template_redirect', __NAMESPACE__ . '\\cmxbu_track_beleg_views');



/**
 * Meta-Box registrieren
 */
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



/**
 * Meta-Box Anzeige
 */
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
			? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($time))
			: '';

		$gmaps = (!empty($city) && !empty($country) && strtolower($city) !== 'unknown')
			? ' <a href="https://www.google.com/maps/search/?api=1&query='
			  . urlencode($city.', '.$country)
			  . '" target="_blank">(Map)</a>'
			: '';

		echo '<div style="margin-bottom:8px;border-bottom:1px dashed #ccc;padding-bottom:6px;">
				<div><strong>Zeit:</strong> '.esc_html($formatted).'</div>
				<div><strong>IP:</strong> '.esc_html($ip).'</div>
				<div><strong>Land/Stadt:</strong> '.esc_html($country).' / '.esc_html($city).$gmaps.'</div>
				<div><strong>Provider:</strong> '.esc_html($provider).'</div>
			  </div>';
	}

	echo '</div>';
}


add_action('admin_notices', function () {
    if (!is_admin()) return;

    $test = wp_remote_get('https://ipapi.co/31.165.222.102/json/');

    echo '<div class="notice notice-info"><p><strong>GEO-API TEST:</strong><br>';

    if (is_wp_error($test)) {
        echo 'WP ERROR: ' . $test->get_error_message();
    } else {
        echo 'RESPONSE: ' . wp_remote_retrieve_body($test);
    }

    echo '</p></div>';
});
