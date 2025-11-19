<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Plugin Name: CLOUD Meister - Mis Büro
 * Plugin URI: https://cloudmeister.ch/cmx-misbuero/
 * Description: Mis Büro by Reiny und Demamels.
 * Version: 25.1027.6511-X
 * Text Domain: cmx-misbuero
 * Domain Path: /languages
 * Author: CLOUD Meister
 * Author URI: https://cloudmeister.ch/
 * License: GPL2
 * Requires PHP: 8.2
 * Requires at least: 6.7.1
 */


require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/webdav.php';
require_once __DIR__ . '/includes/featured_images.php';


define('CMX_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CMX_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CMX_DOMAIN', explode('.', parse_url(home_url(), PHP_URL_HOST))[0]);
define('CMX_UPLOADS_MISBUERO', wp_get_upload_dir()['basedir'] . '/misbuero/');



add_action('init', function() {
	$myName = wp_get_current_user();

	if ( $myName && $myName->exists() ) {
		if ($myName->display_name !== "CLOUD Meister") {
			require_once __DIR__ . '/includes/presets.php';
		}
		require_once __DIR__ . '/includes/index.php';
	}

	foreach (explode(',', 'kontakte,artikel,belege,buchhaltung,dokumente,einstellungen,cockpit,projekte') as $module) {
		require_once __DIR__ .'/src/' . trim($module) . '/index.php';
	}
});

// var_dump(CMX_PLUGIN_DIR); exit;

// add_action('wp_enqueue_scripts', function() {
//     wp_enqueue_style(
//         'style',
//         CMX_PLUGIN_URL . 'assets/style.css',
//         [],
//         filemtime(CMX_PLUGIN_DIR . 'assets/style.css')
//     );
// });
