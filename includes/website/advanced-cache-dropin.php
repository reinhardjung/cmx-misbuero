<?php
/**
 * CMX Mis Buero public-page cache drop-in.
 */

if (!defined('ABSPATH')) {
	return;
}

$cmx_method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($cmx_method !== 'GET' && $cmx_method !== 'HEAD') {
	return;
}

$cmx_query = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
if ($cmx_query !== '') {
	return;
}

foreach ($_COOKIE as $cmx_cookie_name => $cmx_cookie_value) {
	unset($cmx_cookie_value);
	$cmx_cookie_name = (string) $cmx_cookie_name;
	if (str_starts_with($cmx_cookie_name, 'wordpress_logged_in_')
		|| str_starts_with($cmx_cookie_name, 'wp-postpass_')
		|| str_starts_with($cmx_cookie_name, 'comment_author_')
	) {
		return;
	}
}

$cmx_path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$cmx_path = '/' . trim($cmx_path, '/');
$cmx_content_dir = defined('WP_CONTENT_DIR') ? (string) WP_CONTENT_DIR : __DIR__;

if ($cmx_path === '/favicon.ico') {
	$cmx_favicon = $cmx_content_dir . '/plugins/cmx-misbuero/assets/favicon.png';
	if (is_file($cmx_favicon) && is_readable($cmx_favicon)) {
		header('Content-Type: image/png');
		header('Content-Length: ' . (string) filesize($cmx_favicon));
		header('Cache-Control: public, max-age=604800');
		if ($cmx_method !== 'HEAD') {
			readfile($cmx_favicon);
		}
		exit;
	}
	return;
}

if ($cmx_path !== '/') {
	return;
}

$cmx_host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
$cmx_host = (string) preg_replace('~:\d+$~', '', $cmx_host);
$cmx_host = preg_replace('~[^a-z0-9._-]+~', '-', $cmx_host) ?: 'default';
$cmx_cache_file = $cmx_content_dir . '/cache/cmx-misbuero-public/' . $cmx_host . '/index.html';
if (!is_file($cmx_cache_file) || !is_readable($cmx_cache_file)) {
	return;
}

$cmx_plugin_file = $cmx_content_dir . '/plugins/cmx-misbuero/cmx-misbuero.php';
$cmx_css_file = $cmx_content_dir . '/plugins/cmx-misbuero/assets/css/website.css';
$cmx_renderer_file = $cmx_content_dir . '/plugins/cmx-misbuero/includes/website/class-website-renderer.php';
$cmx_template_file = $cmx_content_dir . '/plugins/cmx-misbuero/templates/website/home.php';
$cmx_login_file = $cmx_content_dir . '/plugins/cmx-misbuero/includes/login_manager.php';
$cmx_cache_mtime = (int) filemtime($cmx_cache_file);
foreach ([$cmx_plugin_file, $cmx_css_file, $cmx_renderer_file, $cmx_template_file, $cmx_login_file] as $cmx_dependency) {
	if (is_file($cmx_dependency) && (int) filemtime($cmx_dependency) > $cmx_cache_mtime) {
		return;
	}
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: public, max-age=300, stale-while-revalidate=3600');
header('X-CMX-Public-Cache: HIT');
if ($cmx_method !== 'HEAD') {
	readfile($cmx_cache_file);
}
exit;
