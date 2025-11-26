<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * Frontend-Handler für Beleg-Download per Token
 * URL: https://example.com/?beleg=TOKEN
 */
function cmxbu_handle_beleg_download(): void {

	if (empty($_GET['beleg'])) {
		return;
	}

	$token = \sanitize_text_field($_GET['beleg']);

	// Mapping: Token → post_id
	$data = \get_option('cmx_beleg_token_data_' . $token);

	if (!$data || empty($data['post_id'])) {
		\wp_die('Ungültiger Link.');
	}

	$post_id = (int) $data['post_id'];
	$post    = \get_post($post_id);

	if (!$post || $post->post_type !== 'belege') {
		\wp_die('Beleg nicht gefunden.');
	}

	// Logging (optional)
	if (\function_exists(__NAMESPACE__ . '\\cmxbu_log_beleg_view')) {
		cmxbu_log_beleg_view($post_id);
	}

	// PDF-Pfade berechnen
	[, $pdf_abs_path] = cmxbu_get_beleg_pdf_paths($post);

	if (!is_file($pdf_abs_path)) {
		\wp_die('PDF nicht gefunden.');
	}

	// Output-Puffer leeren
	while (\ob_get_level()) {
		\ob_end_clean();
	}

	\nocache_headers();
	header('Content-Type: application/pdf');
	header('Content-Disposition: attachment; filename="' . basename($pdf_abs_path) . '"');
	header('Content-Length: ' . filesize($pdf_abs_path));

	readfile($pdf_abs_path);
	exit;
}
\add_action('template_redirect', __NAMESPACE__ . '\\cmxbu_handle_beleg_download');
