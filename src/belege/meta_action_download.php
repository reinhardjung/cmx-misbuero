<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * Frontend-Handler für Beleg-Anzeige per Token
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

	$source = \sanitize_key((string) ($_GET['quelle'] ?? ($_GET['source'] ?? '')));
	$is_upload_view = ($source === 'upload');
	$file_abs_path = '';
	$content_type = 'application/pdf';

	if (\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_track_source_beleg')) {
		$source_meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEGEINGANG_SOURCE_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEGEINGANG_SOURCE_META')
			: '_cmx_belegeingang_source';
		if ((string) \get_post_meta($post_id, $source_meta_key, true) === 'rest') {
			cmx_belegeingang_track_source_beleg($post_id, $is_upload_view ? 'target_upload_view' : 'target_pdf_view');
		}
	}

	if ($is_upload_view) {
		$file_abs_path = \function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_primary_upload_abs_path')
			? (string) cmxbu_get_beleg_primary_upload_abs_path($post_id)
			: '';
		if (!\is_file($file_abs_path)) {
			\wp_die('Upload-Beleg nicht gefunden.');
		}
		$mime_info = \wp_check_filetype(\basename($file_abs_path));
		$content_type = (string) ($mime_info['type'] ?? '');
		if ($content_type === '') {
			$content_type = 'application/octet-stream';
		}
	} else {
		[, $pdf_abs_path] = cmxbu_get_beleg_pdf_paths($post);
		if (!\is_file($pdf_abs_path)) {
			\wp_die('PDF nicht gefunden.');
		}
		$file_abs_path = $pdf_abs_path;
	}

	// Output-Puffer leeren
	while (\ob_get_level()) {
		\ob_end_clean();
	}

	\nocache_headers();
	header('Content-Type: ' . $content_type);
	header('Content-Disposition: inline; filename="' . \basename($file_abs_path) . '"');
	header('Content-Length: ' . \filesize($file_abs_path));

	\readfile($file_abs_path);
	exit;
}
\add_action('template_redirect', __NAMESPACE__ . '\\cmxbu_handle_beleg_download');
