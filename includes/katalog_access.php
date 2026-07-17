<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!function_exists(__NAMESPACE__ . '\\cmx_katalog_online')) {
	function cmx_katalog_online(): bool {
		return (string) get_option('cmx_katalog_online', '0') === '1';
	}
}

function cmx_is_katalog_request(): bool {
	if (is_admin()) return false;
	$uuid = isset($_GET['katalog']) ? \sanitize_text_field((string) \wp_unslash($_GET['katalog'])) : '';
	if (
		$uuid !== ''
		&& \function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_matches_uuid')
		&& cmx_artikel_liste_matches_uuid($uuid)
	) {
		return true;
	}
	$req_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
	$req_path = is_string($req_path) ? trim($req_path, '/') : '';
	return $req_path === 'katalog' || \is_page('katalog');
}

add_action('template_redirect', function () {
	if (!cmx_is_katalog_request()) return;
	if (!cmx_katalog_online() && !\is_user_logged_in()) {
		if (!defined('DONOTCACHEPAGE')) {
			define('DONOTCACHEPAGE', true);
		}
		\nocache_headers();
		\auth_redirect();
		exit;
	}
}, 1);

add_action('admin_bar_menu', function (\WP_Admin_Bar $bar) {
	if (!cmx_is_katalog_request()) {
		return;
	}
	$bar->remove_node('site-editor');
	$bar->remove_node('edit');
	$bar->remove_node('updates');
}, 999);

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
