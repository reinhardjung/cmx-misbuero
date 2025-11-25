<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/** Konstanten */
if (!defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_URL')) {
	define(__NAMESPACE__ . '\\CMX_KONTAKTE_META_URL', 'cmx_kontakte_meta_url'); // Website/Domain im Kontakt
}
if (!defined(__NAMESPACE__ . '\\CMX_LOCAL_IMG_SUBDIR')) {
	define(__NAMESPACE__ . '\\CMX_LOCAL_IMG_SUBDIR', '/misbuero/bilder');
}

/** Upload-Basis */
function cmx_local_base_path(): string {
	$u = \wp_get_upload_dir();
	return \wp_normalize_path($u['basedir'] . CMX_LOCAL_IMG_SUBDIR);
}
function cmx_local_base_url(): string {
	$u = \wp_get_upload_dir();
	return rtrim($u['baseurl'], '/') . CMX_LOCAL_IMG_SUBDIR;
}

/** Nur „vorhanden“, wenn die lokale Datei wirklich existiert */
function cmx_has_local_logo(int $post_id): bool {
	$path = (string)\get_post_meta($post_id, '_cmx_local_image_kontakte_path', true);
	return ($path !== '' && @file_exists($path));
}

/** Hauptfunktion: nur laden, wenn noch nichts lokal existiert */
function cmx_fetch_logo_from_url(int $post_id) {
	if ($post_id <= 0) return new \WP_Error('bad_post', 'Ungültige Post-ID');

	// Early-Return: sobald eine lokale Datei existiert, KEIN Netz-Traffic
	if (cmx_has_local_logo($post_id)) {
		return [
			'url'  => (string)\get_post_meta($post_id, '_cmx_local_image_kontakte_url', true),
			'path' => (string)\get_post_meta($post_id, '_cmx_local_image_kontakte_path', true),
		];
	}

	// Quell-URL (nur DB-Lesen, kein Netz)
	$site_url = trim((string)\get_post_meta($post_id, CMX_KONTAKTE_META_URL, true));
	if ($site_url === '') return new \WP_Error('no_url', 'Keine URL im Kontakt hinterlegt');
	if (!preg_match('~^https?://~i', $site_url)) $site_url = 'https://' . ltrim($site_url, '/');

	$origin = cmx_get_origin($site_url);
	if ($origin === '') return new \WP_Error('bad_url', 'Ungültige URL');

	// Nur sehr gängige Standardpfade – schnell & robust, keine HTML-Requests
	$candidates = [
		rtrim($origin, '/') . '/favicon-32x32.png',
		rtrim($origin, '/') . '/favicon-16x16.png',
		rtrim($origin, '/') . '/apple-touch-icon.png',
		rtrim($origin, '/') . '/android-chrome-192x192.png',
		rtrim($origin, '/') . '/android-chrome-512x512.png',
		rtrim($origin, '/') . '/favicon.png',
		rtrim($origin, '/') . '/favicon.ico',
	];

	foreach ($candidates as $img_url) {
		$dl = cmx_download_to_local_and_save_meta($post_id, $img_url);
		if (!\is_wp_error($dl)) {
			\update_post_meta($post_id, '_cmx_logo_src', \esc_url_raw($img_url));
			return $dl; // ['url'=>..., 'path'=>...]
		}
	}

	return new \WP_Error('cmx_import_failed', 'Kein Standard-Icon gefunden oder speicherbar');
}

/** Save-Hook: nur versuchen, wenn KEIN lokales Logo existiert; manuelle Zuweisung respektieren */
\add_action('save_post_kontakte', function($post_id, $post, $update) {
	if (\wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
	if (!\current_user_can('edit_post', $post_id)) return;

	// Wenn im selben Request manuell gesetzt wurde → nichts tun
	if (isset($_POST['_cmx_local_image_kontakte_url']) || isset($_POST['_cmx_local_image_kontakte_path'])) return;

	// Nur wenn wirklich noch nichts lokal existiert → einmalig versuchen
	if (!cmx_has_local_logo($post_id)) {
		$res = cmx_fetch_logo_from_url((int)$post_id);
		if (\is_wp_error($res)) {
			\error_log('[CMX Logo] ' . $res->get_error_code() . ': ' . $res->get_error_message());
		}
	}
}, 20, 3);

/** Download & lokale Speicherung (kein Attachment, keine HEAD-Requests) */
function cmx_download_to_local_and_save_meta(int $post_id, string $image_url) {
	// Sehr kurze Timeouts, keine endlosen Redirects
	$tmp = \download_url($image_url, 8);
	if (\is_wp_error($tmp)) return $tmp;

	// Extension bevorzugt aus URL, sonst aus Dateiinhalt ableiten
	$pathPart = (string)(parse_url($image_url, PHP_URL_PATH) ?? '');
	$ext = strtolower(pathinfo($pathPart, PATHINFO_EXTENSION));
	if ($ext === '') {
		$info = @getimagesize($tmp);
		$mime = is_array($info) && isset($info['mime']) ? strtolower($info['mime']) : '';
		$map  = [
			'image/png'  => 'png',
			'image/webp' => 'webp',
			'image/avif' => 'avif',
			'image/gif'  => 'gif',
			'image/x-icon' => 'ico',
			'image/vnd.microsoft.icon' => 'ico',
			'image/jpeg' => 'jpg',
			'image/bmp'  => 'bmp',
		];
		$ext = $map[$mime] ?? 'png';
	}
	$ext = '.' . preg_replace('~[^a-z0-9]+~', '', $ext);

	$base_dir = cmx_local_base_path();
	$base_url = cmx_local_base_url();
	if (!is_dir($base_dir)) \wp_mkdir_p($base_dir);

	$post     = \get_post($post_id);
	$title    = $post ? get_the_title($post) : '';
	$slug     = sanitize_title($title ?: 'kontakt');
	$basename = $slug . '_' . $post_id;
	$target   = \wp_normalize_path($base_dir . '/' . $basename . $ext);

	// Alte Varianten wegräumen
	foreach (['jpg','jpeg','png','gif','webp','avif','ico','bmp'] as $e) {
		$existing = $base_dir . '/' . $basename . '.' . $e;
		if (file_exists($existing)) @unlink($existing);
	}

	if (!@rename($tmp, $target)) {
		@unlink($tmp);
		return new \WP_Error('move_failed', 'Zieldatei konnte nicht geschrieben werden');
	}
	@chmod($target, 0644);

	$ver = @filemtime($target) ?: time();
	$url = $base_url . '/' . rawurlencode($basename . $ext) . '?v=' . $ver;

	\update_post_meta($post_id, '_cmx_local_image_kontakte_path', $target);
	\update_post_meta($post_id, '_cmx_local_image_kontakte_url',  $url);
	\clean_post_cache($post_id);

	return ['url' => $url, 'path' => $target];
}

/** Hilfen */
function cmx_get_origin(string $url) : string {
	$p = \wp_parse_url($url);
	if (empty($p['host'])) return '';
	$scheme = !empty($p['scheme']) ? $p['scheme'] : 'https';
	$origin = $scheme . '://' . $p['host'];
	if (!empty($p['port'])) $origin .= ':' . $p['port'];
	return $origin;
}
