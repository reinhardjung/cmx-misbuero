<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


add_action( 'admin_menu', __NAMESPACE__ . '\\cmx_remove_comments_menu', 999 );
function cmx_remove_comments_menu() {
	global $menu, $submenu;

	if (isset($menu[2][0])) { $menu[2][0] = 'Dashboard'; }	// "Dashboard" -> Übersicht
	if (isset($submenu['index.php'][0][0])) {
		$submenu['index.php'][0][0] = 'Übersicht';
	}

	remove_menu_page('edit.php');									// Beiträge
	remove_menu_page('upload.php');               // Medien
	remove_menu_page('edit-comments.php');        // Kommentare
	remove_menu_page('themes.php');               // Design / Themes
	remove_menu_page('plugins.php');              // Plugins
	remove_menu_page('tools.php');                // Werkzeuge
	remove_menu_page('options-general.php');      // Einstellungen
	remove_menu_page('edit.php?post_type=page');  // Seiten

	remove_menu_page('hello-elementor');
	remove_menu_page('elementor_library');
	remove_menu_page('wp-armour');
	remove_menu_page('analyticswp');
	remove_menu_page('page=fluent-snippets');

	remove_submenu_page('index.php', 'update-core.php');	// "Aktualisierungen"

	remove_menu_page('acpt-lite'); // Advanced Custom Post Types Lite
}



add_action('admin_bar_menu', __NAMESPACE__ . '\\remove_new_from_admin_bar', 999);
function remove_new_from_admin_bar(\WP_Admin_Bar $wp_admin_bar) : void {
	$wp_admin_bar->remove_node('wp-logo');
	$wp_admin_bar->remove_node('site-name');
	$wp_admin_bar->remove_node('new-content');
	$wp_admin_bar->remove_node('comments');

	// $wp_admin_bar->add_node(['id' => 'mis-buero','title' => 'Mis Büro - Dein Schweizer Online Büro','href' => admin_url(),'meta' => ['title' => 'Zur Übersicht von Mis Büro',],]);
	// $wp_admin_bar->add_node(['id' => 'mis-buero','title' => 'Mis Büro','href' => admin_url(),'meta' => ['title' => 'Zur Übersicht von Mis Büro',],]);
}


// add_action('admin_menu', function() {
//     global $menu;
//     unset($menu[4]); // typischerweise der erste Separator
// }, 9999);


/**
 * Entfernt den Adminbar-Eintrag "archive" (z. B. "View belege")
 */
\add_action('admin_bar_menu', function (\WP_Admin_Bar $wp_admin_bar) {
	if (!is_admin_bar_showing()) return;

	if ($wp_admin_bar->get_node('archive')) {
		$wp_admin_bar->remove_node('archive');
	}
}, 999);
