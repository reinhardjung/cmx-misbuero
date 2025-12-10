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
// Login-spezifische Hooks (z. B. Passwort-Reset) müssen auch ohne eingeloggten Nutzer verfügbar sein.
require_once __DIR__ . '/includes/login_manager.php';


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

	foreach (explode(',', 'kontakte,artikel,belege,buchhaltung,dokumente,cockpit,projekte,einstellungen') as $module) {
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


// Unterdatei mit der Render-Funktion einbinden
require_once __DIR__ . '/src/artikel/katalog.php';

// Shortcode-Registrierung zentral im Hauptfile
\add_action('init', function () {
	\add_shortcode('cmx_artikel_tabelle', __NAMESPACE__ . '\\cmx_render_artikel_tabelle');
});




add_action('plugins_loaded', __NAMESPACE__ . '\\cmx_check_and_create_subdomain_admin');
function cmx_check_and_create_subdomain_admin() {
		// 1. Domain zuverlässig auslesen
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    if (!$host) {
        return;
    }

    // www. entfernen
    $host = preg_replace('~^www\.~', '', $host);

    // 2. Domain-Teile extrahieren
    $parts = explode('.', $host);

    // Beispiel: kunde.misbuero.ch ⇒ ['kunde','misbuero','ch']
    if (count($parts) < 3) {
        $sub = 'checkCheck123'; // misbuero
        // return; // keine 3rd-Level Domain
    }

    // 3. 3rd-Level bestimmen
    $sub = $parts[0]; // "kunde"

    // Falls Subdomain ungültig → abbrechen
    if (!preg_match('~^[a-z0-9\-]+$~i', $sub)) {
        return;
    }

    // 4. E-Mail definieren
    $email = $sub . '@misbuero.ch';

    // Prüfen, ob User existiert
    if (username_exists($sub) || email_exists($email)) {
        return;
    }

    // 5. Sicheres Passwort
    $password = wp_generate_password(24, true, true);

    // 6. User anlegen
    $user_id = wp_insert_user([
        'user_login' => $sub,
        'user_email' => $email,
        'user_pass'  => $password,
        'role'       => 'administrator',
    ]);

    if (!is_wp_error($user_id)) {
        error_log("CMX: Subdomain-Admin '$sub' wurde erstellt (ID $user_id).");
    }
}
