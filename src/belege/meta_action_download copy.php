<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * --------------------------------------------------------------
 * EINMALIG Token je Beleg erzeugen
 * --------------------------------------------------------------
 */
function cmxbu_get_or_create_download_token(int $post_id, string $pdf_rel_path): string {

	global $wpdb;

	// 1) Zuerst prüfen, ob bereits ein Token existiert
	$rows = $wpdb->get_results("
		SELECT option_name, option_value
		FROM {$wpdb->options}
		WHERE option_name LIKE 'beleg_%'
	");

	if ($rows) {
		foreach ($rows as $row) {

			$value = maybe_unserialize($row->option_value);

			if (!is_array($value)) continue;

			// vorhandenen Eintrag für diesen Beleg gefunden → direkt zurückgeben
			if (!empty($value['post_id']) && (int)$value['post_id'] === $post_id) {
				return str_replace('beleg_', '', $row->option_name);
			}
		}
	}

	// 2) KEIN Token gefunden → neuen erzeugen
	$token = wp_generate_password(20, false, false);

	update_option(
		'beleg_' . $token,
		[
			'post_id' => $post_id,
			'file'    => $pdf_rel_path,
		],
		false
	);

	return $token;
}



/**
 * --------------------------------------------------------------
 * Metabox: Download-Link
 * --------------------------------------------------------------
 */
function cmxbu_render_beleg_download_metabox(\WP_Post $post) {

	[$title, $type] = cmx_get_beleg_type($post);

	$pdf_rel_path = '20' . substr($title, 0, 2) . '/' . $title . '_' . $type . '.pdf';
	$base_dir     = rtrim(CMX_UPLOADS_MISBUERO, '/\\') . '/';
	$pdf_abs_path = $base_dir . ltrim($pdf_rel_path, '/\\');

	// PDF fehlt → Button deaktivieren
	if (!is_file($pdf_abs_path)) {
		echo '<a class="button button-secondary alignright" style="pointer-events:none;opacity:.5;">download</a>';
		return;
	}

	// Token laden oder erzeugen
	$token = cmxbu_get_or_create_download_token($post->ID, $pdf_rel_path);

	// Ausgabe der URL
	echo '<a href="' . home_url('/?beleg=' . $token) . '"
			target="_blank"
			class="button button-secondary alignright"
			style="color:#a42c24;border:1px solid #a42c24;background:transparent;">
			download
		  </a>';
}



/**
 * --------------------------------------------------------------
 * Handle: Download per Token
 * --------------------------------------------------------------
 */
function cmxbu_handle_beleg_download() {

	if (empty($_GET['beleg'])) return;

	$token = sanitize_text_field($_GET['beleg']);
	$data  = get_option('beleg_' . $token);

	if (!$data) wp_die('Ungültiger Link.');

	$post = get_post((int) $data['post_id']);
	if (!$post || $post->post_type !== 'belege') wp_die('Beleg fehlt.');

	// Logging
	if (function_exists(__NAMESPACE__ . '\\cmxbu_log_beleg_view')) {
		cmxbu_log_beleg_view($post->ID);
	}

	$file = rtrim(CMX_UPLOADS_MISBUERO, '/\\') . '/' . ltrim($data['file'], '/\\');

	if (!is_file($file)) wp_die('PDF nicht gefunden.');

	while (ob_get_level()) { ob_end_clean(); }

	nocache_headers();
	header('Content-Type: application/pdf');
	header('Content-Disposition: attachment; filename="' . basename($file) . '"');
	header('Content-Length: ' . filesize($file));

	readfile($file);
	exit;
}
add_action('template_redirect', __NAMESPACE__ . '\\cmxbu_handle_beleg_download');
