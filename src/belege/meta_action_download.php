<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * Frontend-Handler für Beleg-Anzeige per Token
 * URL: https://example.com/?beleg=TOKEN
 */
function cmxbu_is_beleg_social_preview_request(): bool {
	$user_agent = \strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
	if ($user_agent === '') {
		return false;
	}

	foreach (['whatsapp', 'facebookexternalhit', 'facebot', 'meta-externalagent'] as $crawler) {
		if (\str_contains($user_agent, $crawler)) {
			return true;
		}
	}

	return false;
}

function cmxbu_render_beleg_share_preview(int $post_id, string $token): void {
	$filename = \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_public_filename')
		? (string) cmxbu_beleg_public_filename($post_id)
		: 'Beleg.pdf';
	$title = (string) \preg_replace('/\.pdf$/i', '', $filename);
	if ($title === '') {
		$title = 'Beleg';
	}

	$logo_url = '';
	if (\function_exists(__NAMESPACE__ . '\\cmx_get_branding_logo')) {
		$logo_url = (string) cmx_get_branding_logo();
	} elseif (\function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_url')) {
		$logo_url = (string) cmx_email_self_logo_url();
	}
	$logo_url = \esc_url_raw($logo_url);

	$direct_args = [
		'beleg' => $token,
	];
	$source = \sanitize_key((string) ($_GET['quelle'] ?? ($_GET['source'] ?? '')));
	if ($source !== '') {
		$direct_args['quelle'] = $source;
	}
	$direct_url = (string) \add_query_arg(
		$direct_args,
		\home_url('/' . \rawurlencode($filename))
	);
	$share_url = $direct_url;
	$description = 'PDF-Dokument öffnen';

	\status_header(200);
	\nocache_headers();
	header('Content-Type: text/html; charset=UTF-8');
	echo '<!doctype html><html lang="de"><head><meta charset="utf-8">';
	echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
	echo '<meta name="robots" content="noindex,nofollow">';
	echo '<title>' . \esc_html($title) . '</title>';
	echo '<meta property="og:type" content="website">';
	echo '<meta property="og:title" content="' . \esc_attr($title) . '">';
	echo '<meta property="og:description" content="' . \esc_attr($description) . '">';
	echo '<meta property="og:url" content="' . \esc_url($share_url) . '">';
	if ($logo_url !== '') {
		echo '<meta property="og:image" content="' . \esc_url($logo_url) . '">';
		echo '<meta property="og:image:secure_url" content="' . \esc_url($logo_url) . '">';
	}
	echo '<meta name="twitter:card" content="summary">';
	echo '<meta name="twitter:title" content="' . \esc_attr($title) . '">';
	echo '<meta name="twitter:description" content="' . \esc_attr($description) . '">';
	if ($logo_url !== '') {
		echo '<meta name="twitter:image" content="' . \esc_url($logo_url) . '">';
	}
	echo '<style>body{font-family:Arial,sans-serif;background:#f6f7f7;color:#1d2327;margin:0;padding:32px 18px}.cmx-share{max-width:520px;margin:8vh auto;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:28px;text-align:center}.cmx-share img{display:block;max-width:180px;max-height:120px;width:auto;height:auto;margin:0 auto 20px}.cmx-share h1{font-size:22px;margin:0 0 18px}.cmx-share a{display:inline-block;padding:11px 18px;border-radius:5px;background:#1858a8;color:#fff;text-decoration:none;font-weight:600}</style>';
	echo '</head><body><main class="cmx-share">';
	if ($logo_url !== '') {
		echo '<img src="' . \esc_url($logo_url) . '" alt="Logo">';
	}
	echo '<h1>' . \esc_html($title) . '</h1>';
	echo '<a href="' . \esc_url($direct_url) . '">PDF öffnen</a>';
	echo '</main><script>window.location.replace(' . \wp_json_encode($direct_url) . ');</script></body></html>';
	exit;
}

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

	if (!empty($_GET['vorschau']) || cmxbu_is_beleg_social_preview_request()) {
		cmxbu_render_beleg_share_preview($post_id, $token);
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

	// Lesbare PDF-Pfade sind keine echten WordPress-Seiten. WordPress markiert
	// die Anfrage vor template_redirect als 404, obwohl der Beleg anschliessend
	// korrekt ausgeliefert wird. Der PDF-Viewer verwirft eine solche Antwort.
	\status_header(200);
	\nocache_headers();
	header('Content-Type: ' . $content_type);
	$download_filename = (!$is_upload_view && \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_public_filename'))
		? (string) cmxbu_beleg_public_filename($post_id)
		: \basename($file_abs_path);
	$download_filename = \str_replace(["\r", "\n", '"'], ['', '', "'"], $download_filename);
	header('Content-Disposition: inline; filename="' . $download_filename . '"; filename*=UTF-8\'\'' . \rawurlencode($download_filename));
	header('Content-Length: ' . \filesize($file_abs_path));

	\readfile($file_abs_path);
	exit;
}
// Vor WordPress-Canonical-Redirects ausliefern, aber nach den Offerten-Aktionen (Priorität 1).
\add_action('template_redirect', __NAMESPACE__ . '\\cmxbu_handle_beleg_download', 2);
