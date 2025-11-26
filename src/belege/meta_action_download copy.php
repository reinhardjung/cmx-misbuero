<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');


/**
 * -------------------------------------------------------------------------
 * Render der Download-Button Meta-Box (wie zuvor)
 * -------------------------------------------------------------------------
 */
function cmxbu_render_beleg_download_metabox(\WP_Post $post) {
	[$title, $type] = cmx_get_beleg_type($post);

	$pdf_rel_path = '20' . substr($title, 0, 2) . '/' . $title . '_' . $type . '.pdf';

	$base_dir     = rtrim(CMX_UPLOADS_MISBUERO, '/\\') . '/';
	$pdf_abs_path = $base_dir . ltrim($pdf_rel_path, '/\\');

	if (!is_file($pdf_abs_path)) {
		echo '<a href="#" class="button button-secondary alignright" style="pointer-events:none; opacity:0.5; color:silver; border:silver solid 1px;">download</a>';
		return;
	}

	// Dauerhafter Token
	$token = wp_generate_password(20, false, false);

	update_option(
		'beleg_' . $token,
		[
			'post_id' => $post->ID,
			'file'    => $pdf_rel_path,
		],
		false
	);

	$download_url = home_url('/?beleg=' . $token);

	echo '<style>
		.my-transparent-btn:hover {
			background: #a42c24 !important;
			color: #ffffff !important;
			border-color: #a42c24 !important;
		}
	</style>';

	echo '<a href="' . esc_url($download_url) . '" target="_blank" class="button button-secondary alignright my-transparent-btn" style="color:#a42c24; border:#a42c24 solid 1px; background:transparent;">download</a>';
}



/**
 * -------------------------------------------------------------------------
 * Handler für den PDF-Download + Logging
 * -------------------------------------------------------------------------
 */
function cmxbu_handle_beleg_download() {

	if (empty($_GET['beleg'])) {
		return;
	}

	$token = sanitize_text_field((string) $_GET['beleg']);
	$data  = get_option('beleg_' . $token);

	if (!$data || !isset($data['post_id'], $data['file'])) {
		wp_die('Ungültiger oder abgelaufener Download-Link.', 'Zugriff verweigert', ['response' => 403]);
	}

	$post = get_post((int) $data['post_id']);
	if (!$post || $post->post_type !== 'belege') {
		wp_die('Beleg nicht gefunden.', 'Fehler', ['response' => 404]);
	}


	/**
	 * -----------------------------------------------------------------
	 * WICHTIG: Logging einbinden (Counter + Logfile)
	 * -----------------------------------------------------------------
	 */
	if (function_exists(__NAMESPACE__ . '\\cmxbu_log_beleg_view')) {
		cmxbu_log_beleg_view((int) $post->ID);
	}


	/**
	 * -----------------------------------------------------------------
	 * PDF ausliefern
	 * -----------------------------------------------------------------
	 */
	$base_dir  = rtrim(CMX_UPLOADS_MISBUERO, '/\\') . '/';
	$file_path = $base_dir . ltrim($data['file'], '/\\');

	if (!is_file($file_path)) {
		wp_die('Datei nicht vorhanden.', 'Fehler', ['response' => 404]);
	}

	// Output-Buffer leeren
	while (ob_get_level() > 0) {
		@ob_end_clean();
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
add_action('template_redirect', __NAMESPACE__ . '\\cmxbu_handle_beleg_download');



/**
 * -------------------------------------------------------------------------
 * CLEANUP CRON (falls Du es brauchst – optional)
 * -------------------------------------------------------------------------
 */
function cmxbu_schedule_token_cleanup(): void {
	if (!wp_next_scheduled('cmxbu_cleanup_tokens')) {
		wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'cmxbu_cleanup_tokens');
	}
}
add_action('init', __NAMESPACE__ . '\\cmxbu_schedule_token_cleanup');


function cmxbu_cleanup_tokens(): void {
	global $wpdb;

	$like = $wpdb->esc_like('beleg_') . '%';
	$table = $wpdb->options;

	$rows = $wpdb->get_results(
		$wpdb->prepare("SELECT option_name, option_value FROM {$table} WHERE option_name LIKE %s", $like)
	);

	if (empty($rows)) {
		return;
	}

	$base_dir = rtrim(CMX_UPLOADS_MISBUERO, '/\\') . '/';

	foreach ($rows as $row) {
		$option_value = maybe_unserialize($row->option_value);

		if (!is_array($option_value) || empty($option_value['file'])) {
			delete_option($row->option_name);
			continue;
		}

		$file_rel  = ltrim((string) $option_value['file'], '/\\');
		$file_path = $base_dir . $file_rel;

		if (!is_file($file_path)) {
			delete_option($row->option_name);
		}
	}
}
add_action('cmxbu_cleanup_tokens', __NAMESPACE__ . '\\cmxbu_cleanup_tokens');
