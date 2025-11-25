<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


$BildIsDa = true;
var_dump($BildIsDa); exit;


/**
 * Ziel: Niemals remote laden, wenn bereits ein lokales Logo vorhanden ist.
 * – Prüfkriterien: _cmx_local_image_kontakte_path (existiert & Datei vorhanden) ODER _cmx_local_image_kontakte_url (nicht leer)
 */

/** Basis-Konstanten */
if (!defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_URL')) {
	define(__NAMESPACE__ . '\\CMX_KONTAKTE_META_URL', 'cmx_kontakte_meta_url'); // Webseite des Kontakts
}
if (!defined(__NAMESPACE__ . '\\CMX_LOCAL_IMG_SUBDIR')) {
	define(__NAMESPACE__ . '\\CMX_LOCAL_IMG_SUBDIR', '/misbuero/bilder');
}

/** Upload-Pfade */
function cmx_local_base_path(): string {
	$u = \wp_get_upload_dir();
	return \wp_normalize_path($u['basedir'] . CMX_LOCAL_IMG_SUBDIR);
}
function cmx_local_base_url(): string {
	$u = \wp_get_upload_dir();
	return rtrim($u['baseurl'], '/') . CMX_LOCAL_IMG_SUBDIR;
}

/** Prüfen, ob bereits ein lokales Logo existiert (reicht aus, um REMOTE strikt zu blocken) */
function cmx_has_local_logo(int $post_id): bool {
	$path = (string) \get_post_meta($post_id, '_cmx_local_image_kontakte_path', true);
	$url  = (string) \get_post_meta($post_id, '_cmx_local_image_kontakte_url', true);

	if ($path !== '' && @file_exists($path)) return true; // echte Datei vorhanden
	if ($url !== '') return true; // URL bereits gesetzt ⇒ als „vorhanden“ behandeln
	return false;
}

/**
 * Öffentliche API: Logo nur dann remote holen, wenn KEIN lokales Logo existiert.
 * Gibt array('url','path') zurück oder \WP_Error.
 */
function cmx_fetch_logo_from_url(int $post_id) {
	if ($post_id <= 0) return new \WP_Error('bad_post', 'Ungültige Post-ID');

	/* Harter Early-Return: Sobald ein lokales Logo existiert → KEIN Netz */
	if (cmx_has_local_logo($post_id)) {
		return [
			'url'  => (string) \get_post_meta($post_id, '_cmx_local_image_kontakte_url', true),
			'path' => (string) \get_post_meta($post_id, '_cmx_local_image_kontakte_path', true),
		];
	}

	/* Ab hier: Erstversuch eines Fetch – nur wenn wirklich noch nichts existiert */

	// 1) URL aus Kontakt (keine Netzaktivität)
	$site_url = trim((string) \get_post_meta($post_id, CMX_KONTAKTE_META_URL, true));
	if ($site_url === '') return new \WP_Error('no_url', 'Keine URL im Kontakt hinterlegt');
	if (!preg_match('~^https?://~i', $site_url)) $site_url = 'https://' . ltrim($site_url, '/');

	$origin = cmx_get_origin($site_url);
	if ($origin === '') return new \WP_Error('bad_url', 'Ungültige URL');

	// 2) Kandidaten definieren (NUR Standardpfade, KEIN HTML-Download, um extrem schnell zu bleiben)
	$candidates = [];
	foreach ([
		'apple-touch-icon.png',
		'android-chrome-512x512.png',
		'android-chrome-192x192.png',
		'favicon-32x32.png',
		'favicon-16x16.png',
		'favicon.ico',
	] as $p) {
		$candidates[] = rtrim($origin, '/') . '/' . ltrim($p, '/');
	}

	// 3) Schnelltest per HEAD und dann Download – erster Erfolg gewinnt
	foreach ($candidates as $url) {
		$head = cmx_head_is_image_fast($url); // sehr kurze Timeouts
		if (\is_wp_error($head)) continue;

		$dl = cmx_download_to_local_and_save_meta($post_id, $url);
		if (!\is_wp_error($dl)) {
			\update_post_meta($post_id, '_cmx_logo_src', \esc_url_raw($url));
			return $dl; // ['url'=>..., 'path'=>...]
		}
	}

	return new \WP_Error('cmx_import_failed', 'Kein Kandidat verwertbar');
}

/** Save-Hook: Nur ausführen, wenn KEIN lokales Logo existiert. Manuelle Zuweisung im selben Request respektieren. */
\add_action('save_post_kontakte', function($post_id, $post, $update) {
	if (\wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
	if (!\current_user_can('edit_post', $post_id)) return;

	// Wenn im selben Request manuell gesetzt wurde → nichts tun
	if (isset($_POST['_cmx_local_image_kontakte_url']) || isset($_POST['_cmx_local_image_kontakte_path'])) return;

	// Wenn bereits lokal vorhanden → nichts tun (damit garantiert KEINE Remote-Calls stattfinden)
	if (cmx_has_local_logo($post_id)) return;

	// Sonst genau ein Versuch
	$res = cmx_fetch_logo_from_url((int) $post_id);
	if (\is_wp_error($res)) {
		\error_log('[CMX Logo] ' . $res->get_error_code() . ': ' . $res->get_error_message());
	}
}, 20, 3);

/** Download & lokale Speicherung (kein Attachment) */
function cmx_download_to_local_and_save_meta(int $post_id, string $image_url) {
	$tmp = \download_url($image_url, 8);
	if (\is_wp_error($tmp)) return $tmp;

	// Content-Type/Extension minimal bestimmen (GET mit kleinem Timeout)
	$ext = '.jpg';
	$resp = \wp_remote_get($image_url, ['timeout'=>4, 'redirection'=>1, 'sslverify'=>(bool)\apply_filters('cmx_logo_sslverify', true)]);
	if (!\is_wp_error($resp)) {
		$ct  = strtolower((string) \wp_remote_retrieve_header($resp, 'content-type'));
		if (strpos($ct, 'image/png') === 0) $ext = '.png';
		elseif (strpos($ct, 'image/webp') === 0) $ext = '.webp';
		elseif (strpos($ct, 'image/avif') === 0) $ext = '.avif';
		elseif (strpos($ct, 'image/gif')  === 0) $ext = '.gif';
		elseif (strpos($ct, 'image/x-icon') === 0 || strpos($ct, 'image/vnd.microsoft.icon') === 0) $ext = '.ico';
	}

	$base_dir = cmx_local_base_path();
	$base_url = cmx_local_base_url();
	if (!is_dir($base_dir)) \wp_mkdir_p($base_dir);

	$post     = \get_post($post_id);
	$title    = $post ? get_the_title($post) : '';
	$slug     = sanitize_title($title ?: 'kontakt');
	$basename = $slug . '_' . $post_id;
	$target   = \wp_normalize_path($base_dir . '/' . $basename . $ext);

	// Varianten entfernen
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

/** Sehr schneller HEAD-Check (1s Timeout), um Kandidaten zu validieren */
function cmx_head_is_image_fast(string $url, int $max_bytes = 2097152) {
	$args = [
		'timeout'     => 1,
		'redirection' => 0,
		'method'      => 'HEAD',
		'user-agent'  => 'CMX-LogoFetcher/fast (+WordPress)',
		'sslverify'   => (bool) \apply_filters('cmx_logo_sslverify', true),
	];
	$res = \wp_remote_request($url, $args);
	if (\is_wp_error($res)) return $res;
	$code = (int) \wp_remote_retrieve_response_code($res);
	if ($code < 200 || $code >= 400) return new \WP_Error('head_http_' . $code, 'HEAD HTTP ' . $code);
	$ct = (string) \wp_remote_retrieve_header($res, 'content-type');
	if ($ct && stripos($ct, 'image/') !== 0) return new \WP_Error('not_image', 'Kein Bild-Content-Type (' . $ct . ')');
	$cl = (int) \wp_remote_retrieve_header($res, 'content-length');
	if ($cl > 0 && $cl > $max_bytes) return new \WP_Error('too_big', 'Bild zu groß (' . $cl . ' Bytes)');
	return true;
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
