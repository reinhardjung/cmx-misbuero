<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!function_exists(__NAMESPACE__ . '\\cmx_katalog_online')) {
	function cmx_katalog_online(): bool {
		return (string) get_option('cmx_katalog_online', '0') === '1';
	}
}

add_action('template_redirect', function () {
	if (is_admin()) return;
	$req_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
	$req_path = is_string($req_path) ? trim($req_path, '/') : '';
	if ($req_path === 'katalog' || \is_page('katalog')) {
		if (!cmx_katalog_online() && !\is_user_logged_in()) {
			if (!defined('DONOTCACHEPAGE')) {
				define('DONOTCACHEPAGE', true);
			}
			\nocache_headers();
			\auth_redirect();
			exit;
		}
	}
}, 1);

add_filter('wp_get_nav_menu_items', function (array $items, $menu, $args) {
	if (cmx_katalog_online() || \is_user_logged_in()) {
		return $items;
	}
	$filtered = [];
	foreach ($items as $item) {
		$url = isset($item->url) ? (string) $item->url : '';
		$path = parse_url($url, PHP_URL_PATH);
		$path = is_string($path) ? trim($path, '/') : '';
		if ($path === 'katalog') {
			continue;
		}
		$filtered[] = $item;
	}
	return $filtered;
}, 10, 3);
