<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/** Konstanten */
if (!defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_URL')) {
	define(__NAMESPACE__ . '\\CMX_KONTAKTE_META_URL', 'cmx_kontakte_meta_url');
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

/**
 * Prüft zuverlässig, ob ein lokales Logo existiert UND ob die Datei da ist.
 */
function cmx_has_local_logo(int $post_id): bool {
	$path = (string)get_post_meta($post_id, '_cmx_local_image_kontakte_path', true);

	if ($path === '' || !is_file($path)) {
		return false;
	}

	// Sicherstellen, dass es wirklich ein Bild ist
	$info = @getimagesize($path);
	if (!is_array($info) || empty($info['mime'])) {
		return false;
	}

	return true;
}

/**
 * Hauptfunktion: lädt NUR, wenn wirklich noch nichts lokal existiert.
 */
function cmx_fetch_logo_from_url(int $post_id) {
	if ($post_id <= 0) return new \WP_Error('bad_post', 'Ungültige Post-ID');

	// Wenn bereits ein Logo existiert → fertig
	if (cmx_has_local_logo($post_id)) {
		return [
			'url'  => (string)\get_post_meta($post_id, '_cmx_local_image_kontakte_url', true),
			'path' => (string)\get_post_meta($post_id, '_cmx_local_image_kontakte_path', true),
		];
	}

	// URL im Kontakt lesen
	$site_url = trim((string)\get_post_meta($post_id, CMX_KONTAKTE_META_URL, true));
	if ($site_url === '') return new \WP_Error('no_url', 'Keine URL im Kontakt vorhanden');

	if (!preg_match('~^https?://~i', $site_url)) {
		$site_url = 'https://' . ltrim($site_url, '/');
	}

	$origin = cmx_get_origin($site_url);
	if ($origin === '') return new \WP_Error('bad_url', 'Ungültige URL');

	// Kandidaten
	$candidates = [
		'/favicon-32x32.png',
		'/favicon-16x16.png',
		'/apple-touch-icon.png',
		'/android-chrome-192x192.png',
		'/android-chrome-512x512.png',
		'/favicon.png',
		'/favicon.ico',
	];

	foreach ($candidates as $c) {
		$img_url = rtrim($origin, '/') . $c;

		$dl = cmx_download_to_local_and_save_meta($post_id, $img_url);
		if (!\is_wp_error($dl)) {
			\update_post_meta($post_id, '_cmx_logo_src', esc_url_raw($img_url));
			return $dl;
		}
	}

	return new \WP_Error('cmx_import_failed', 'Kein brauchbares Icon gefunden');
}


/**
 * Save-Hook: nur dann laden, wenn wirklich kein Logo existiert.
 */
\add_action('save_post_kontakte', function($post_id, $post, $update) {

	if (\wp_is_post_revision($post_id)) return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!\current_user_can('edit_post', $post_id)) return;

	// Wenn im Request manuell gesetzt wurde → nichts tun
	if (!empty($_POST['_cmx_local_image_kontakte_url']) ||
	    !empty($_POST['_cmx_local_image_kontakte_path'])) {
		return;
	}

	// Wenn Logo existiert → niemals neu laden
	if (cmx_has_local_logo($post_id)) {
		return;
	}

	$res = cmx_fetch_logo_from_url((int)$post_id);
	if (\is_wp_error($res)) {
		error_log('[CMX Logo] ' . $res->get_error_code() . ': ' . $res->get_error_message());
	}

}, 20, 3);


/**
 * Laden & lokale Speicherung
 */
function cmx_download_to_local_and_save_meta(int $post_id, string $image_url) {

	$tmp = \download_url($image_url, 8);
	if (\is_wp_error($tmp)) return $tmp;

	// verhindern, dass 404/HTML-Dateien als PNG gespeichert werden
	$info = @getimagesize($tmp);
	if (!is_array($info) || empty($info['mime'])) {
		@unlink($tmp);
		return new \WP_Error('invalid_image', 'Ungültiges Bild oder 404 erhalten');
	}

	$map = [
		'image/png'  => 'png',
		'image/webp' => 'webp',
		'image/avif' => 'avif',
		'image/gif'  => 'gif',
		'image/x-icon' => 'ico',
		'image/vnd.microsoft.icon' => 'ico',
		'image/jpeg' => 'jpg',
		'image/bmp'  => 'bmp',
	];

	$ext = $map[strtolower($info['mime'])] ?? 'png';
	$ext = '.' . $ext;

	$base_dir = cmx_local_base_path();
	$base_url = cmx_local_base_url();
	if (!is_dir($base_dir)) wp_mkdir_p($base_dir);

	$post  = get_post($post_id);
	$title = $post ? get_the_title($post) : '';
	$slug  = sanitize_title($title ?: 'kontakt');
	$file  = $slug . '_' . $post_id . $ext;

	$target = wp_normalize_path($base_dir . '/' . $file);

	// vorhandene Varianten löschen
	foreach (['jpg','jpeg','png','gif','webp','avif','ico','bmp'] as $old) {
		$f = $base_dir . '/' . $slug . '_' . $post_id . '.' . $old;
		if (file_exists($f)) @unlink($f);
	}

	if (!@rename($tmp, $target)) {
		@unlink($tmp);
		return new \WP_Error('move_failed', 'Speichern fehlgeschlagen');
	}
	@chmod($target, 0644);

	$ver = filemtime($target) ?: time();
	$url = $base_url . '/' . rawurlencode($file) . '?v=' . $ver;

	update_post_meta($post_id, '_cmx_local_image_kontakte_path', $target);
	update_post_meta($post_id, '_cmx_local_image_kontakte_url',  $url);

	clean_post_cache($post_id);

	return ['url' => $url, 'path' => $target];
}


/** Domain extrahieren */
function cmx_get_origin(string $url): string {
	$p = wp_parse_url($url);
	if (empty($p['host'])) return '';
	$scheme = !empty($p['scheme']) ? $p['scheme'] : 'https';
	$origin = $scheme . '://' . $p['host'];
	if (!empty($p['port'])) $origin .= ':' . $p['port'];
	return $origin;
}
