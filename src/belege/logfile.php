<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');


/**
 * Echte IP ermitteln (Proxy-sicher)
 */
function cmxbu_get_real_ip(): string {

	$keys = [
		'HTTP_CF_CONNECTING_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_REAL_IP',
		'HTTP_CLIENT_IP',
		'REMOTE_ADDR'
	];

	foreach ($keys as $key) {
		if (!empty($_SERVER[$key])) {
			$ip = trim(explode(',', $_SERVER[$key])[0]);
			if (filter_var($ip, FILTER_VALIDATE_IP)) {
				return $ip;
			}
		}
	}
	return '0.0.0.0';
}


/**
 * GEO-IP holen
 */
function cmxbu_fetch_geo_ip(string $ip): array {

	if (in_array($ip, ['127.0.0.1','::1','0.0.0.0'])) {
		return ['country'=>'Local','city'=>'Local','provider'=>'Localhost'];
	}

	$url = "https://ipapi.co/{$ip}/json/";

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 4);
	$res = curl_exec($ch);
	curl_close($ch);

	$data = json_decode($res, true);

	return [
		'country'  => $data['country_name'] ?? 'Unknown',
		'city'     => $data['city'] ?? 'Unknown',
		'provider' => $data['org'] ?? 'Unknown',
	];
}


/**
 * Logging-Funktion
 */
function cmxbu_log_beleg_view(int $post_id): void {

	$log = get_post_meta($post_id, '_cmx_beleg_views_log', true);
	if (!is_array($log)) { $log = []; }

	$ip  = cmxbu_get_real_ip();
	$geo = cmxbu_fetch_geo_ip($ip);

	$log[] = [
		'time'     => current_time('mysql'),
		'ip'       => $ip,
		'country'  => $geo['country'],
		'city'     => $geo['city'],
		'provider' => $geo['provider'],
	];

	update_post_meta($post_id, '_cmx_beleg_views_log', $log);

	$count = (int) get_post_meta($post_id, '_cmx_beleg_views', true);
	update_post_meta($post_id, '_cmx_beleg_views', $count + 1);
}


/**
 * Frontend-Tracking
 */
function cmxbu_track_beleg_views() {

	if (is_admin()) return;
	if (!is_singular('belege')) return;

	cmxbu_log_beleg_view( get_queried_object_id() );
}
add_action('template_redirect', __NAMESPACE__ . '\\cmxbu_track_beleg_views');



/**
 * Meta-Box
 */
function cmxbu_register_logfile_metabox() {
	add_meta_box(
		'cmxbu_logfile_box',
		'Aufrufe / Logfile',
		__NAMESPACE__ . '\\cmxbu_render_logfile_metabox',
		'belege',
		'side',
		'default'
	);
}
add_action('add_meta_boxes', __NAMESPACE__ . '\\cmxbu_register_logfile_metabox');



/**
 * Meta-Box Rendering
 */
function cmxbu_render_logfile_metabox(\WP_Post $post): void {

	$views = (int) get_post_meta($post->ID, '_cmx_beleg_views', true);
	$log   = get_post_meta($post->ID, '_cmx_beleg_views_log', true);

	if (!is_array($log)) $log = [];

	echo '<div style="margin:8px 0;">
		<strong>Beleg-Aufrufe:</strong><br>
		<span style="font-size:18px;color:#a42c24;font-weight:bold;">' . esc_html($views) . '</span>
	</div><hr>';

	echo '<div style="max-height:260px;overflow:auto;padding:6px;border:1px solid #ccc;">';

	foreach (array_reverse($log) as $entry) {

		// FALLBACKS für alte Einträge
		$time_raw  = $entry['time']     ?? '';
		$ip        = $entry['ip']       ?? '';
		$country   = $entry['country']  ?? '';
		$city      = $entry['city']     ?? '';
		$provider  = $entry['provider'] ?? '';

		// WordPress Datum
		$formatted = $time_raw
			? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($time_raw))
			: '';

		// Google Maps Link
		$gmaps = (!empty($city) && !empty($country))
			? esc_url('https://www.google.com/maps/search/?api=1&query=' . urlencode($city . ', ' . $country))
			: '';

		echo '<div style="margin-bottom:8px;border-bottom:1px dashed #ccc;padding-bottom:6px;">';

		echo '<div><strong>Zeit:</strong> ' . esc_html($formatted) . '</div>';
		echo '<div><strong>IP:</strong> '   . esc_html($ip) . '</div>';

		echo '<div><strong>Land/Stadt:</strong> '
			 . esc_html($country) . ' / ' . esc_html($city);

		if ($gmaps) {
			echo ' &nbsp;<a href="' . $gmaps . '" target="_blank">(Map)</a>';
		}

		echo '</div>';

		echo '<div><strong>Provider:</strong> ' . esc_html($provider) . '</div>';

		echo '</div>';
	}

	echo '</div>';
}
