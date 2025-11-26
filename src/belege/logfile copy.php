<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');


/**
 * ==========================================================================
 * FRONTEND: Beleg-Aufrufe tracken (Seitenaufruf)
 * ==========================================================================
 */
function cmxbu_track_beleg_views() {

	if (is_admin()) {
		return;
	}

	// nur Einzelbeleg im Frontend
	if (!is_singular('belege')) {
		return;
	}

	$post_id = get_queried_object_id();
	if (!$post_id) {
		return;
	}

	// 1) Counter erhöhen
	$current = (int) get_post_meta($post_id, '_cmx_beleg_views', true);
	update_post_meta($post_id, '_cmx_beleg_views', $current + 1);

	// 2) Log ergänzen
	$log = get_post_meta($post_id, '_cmx_beleg_views_log', true);
	if (!is_array($log)) {
		$log = [];
	}

	$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

	$log[] = [
		'time' => current_time('mysql'),
		'ip'   => $ip,
	];

	update_post_meta($post_id, '_cmx_beleg_views_log', $log);
}
add_action('template_redirect', __NAMESPACE__ . '\\cmxbu_track_beleg_views');




/**
 * ==========================================================================
 * DOWNLOAD-Tracking über ?beleg=TOKEN
 * ==========================================================================
 */
add_action('template_redirect', __NAMESPACE__ . '\\cmxbu_handle_beleg_download');
function cmxbu_handle_beleg_download() {
	if (empty($_GET['beleg'])) {
		return;
	}

	$token = sanitize_text_field((string) $_GET['beleg']);

	// Dauerhafte Speicherung via Option
	$data = get_option('beleg_' . $token);

	if (!$data || !isset($data['post_id'], $data['file'])) {
		wp_die('Ungültiger oder abgelaufener Download-Link.', 'Zugriff verweigert', ['response' => 403]);
	}

	$post = get_post((int) $data['post_id']);
	if (!$post || $post->post_type !== 'belege') {
		wp_die('Beleg nicht gefunden.', 'Fehler', ['response' => 404]);
	}

	// *** HIER: Aufruf loggen (Counter + Logfile) ***
	if (function_exists(__NAMESPACE__ . '\\cmxbu_log_beleg_view')) {
		cmxbu_log_beleg_view((int) $post->ID);
	}

	$base_dir  = rtrim(CMX_UPLOADS_MISBUERO, '/\\') . '/';
	$file_path = $base_dir . ltrim($data['file'], '/\\');

	if (!is_file($file_path)) {
		wp_die('Datei nicht vorhanden.', 'Fehler', ['response' => 404]);
	}

	// Output-Buffer leeren
	if (function_exists('ob_get_level')) {
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
	}

	nocache_headers();
	status_header(200);

	header('Content-Type: application/pdf');
	header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
	header('Content-Length: ' . filesize($file_path));
	header('Accept-Ranges: none');

	readfile($file_path);
	exit;
}




/**
 * ==========================================================================
 * ADMIN: Meta-Box registrieren
 * ==========================================================================
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
 * ==========================================================================
 * ADMIN: Meta-Box Inhalt
 * ==========================================================================
 */
function cmxbu_render_logfile_metabox(\WP_Post $post) {

	$views = (int) get_post_meta($post->ID, '_cmx_beleg_views', true);
	$log   = get_post_meta($post->ID, '_cmx_beleg_views_log', true);

	if (!is_array($log)) {
		$log = [];
	}

	echo '<div style="margin:8px 0; font-size:14px; line-height:1.4;">
		<strong>Beleg-Aufrufe:</strong><br>
		<span style="font-size:18px; font-weight:bold; color:#a42c24;">' . esc_html($views) . '</span>
	</div>';

	echo '<hr style="margin:10px 0;">';

	echo '<strong>Details:</strong><br>';

	if (empty($log)) {
		echo '<p style="font-size:12px; color:#666;">Noch keine Logeinträge.</p>';
		return;
	}

	echo '<div style="max-height:260px; overflow:auto; border:1px solid #ddd; padding:6px; background:#fafafa;">';

	foreach (array_reverse($log) as $entry) {
		$t  = esc_html($entry['time'] ?? '');
		$ip = esc_html($entry['ip'] ?? '');

		echo '<div style="margin-bottom:6px; border-bottom:1px dashed #ccc; padding-bottom:4px;">
				<div><strong>Zeit:</strong> ' . $t . '</div>
				<div><strong>IP:</strong> ' . $ip . '</div>
			</div>';
	}

	echo '</div>';
}
