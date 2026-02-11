<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

function cmx_is_telefonbuch_request(): bool {
	if (is_admin()) return false;
	$req_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
	$req_path = is_string($req_path) ? trim($req_path, '/') : '';
	return $req_path === 'telefonbuch' || str_starts_with($req_path, 'telefonbuch/') || \is_page('telefonbuch');
}

add_action('template_redirect', function () {
	if (!cmx_is_telefonbuch_request()) return;
	if (!\is_user_logged_in()) {
		if (!defined('DONOTCACHEPAGE')) {
			define('DONOTCACHEPAGE', true);
		}
		\nocache_headers();
		\auth_redirect();
		exit;
	}
}, 1);

add_filter('wp_get_nav_menu_items', function (array $items, $menu, $args) {
	if (\is_user_logged_in()) {
		return $items;
	}
	$filtered = [];
	foreach ($items as $item) {
		$url = isset($item->url) ? (string) $item->url : '';
		$path = parse_url($url, PHP_URL_PATH);
		$path = is_string($path) ? trim($path, '/') : '';
		if ($path === 'telefonbuch' || str_starts_with($path, 'telefonbuch/')) {
			continue;
		}
		$filtered[] = $item;
	}
	return $filtered;
}, 10, 3);

