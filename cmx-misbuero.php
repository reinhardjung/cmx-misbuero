<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Plugin Name: CLOUD Meister - Mis Büro
 * Plugin URI: https://cloudmeister.ch/cmx-misbuero/
 * Description: Mis Büro by CLOUD Meister.
60204.073244
 * Text Domain: cmx-misbuero
 * Domain Path: /languages
 * Author: CLOUD Meister
 * Author URI: https://cloudmeister.ch/
 * License: GPL2
 * Requires PHP: 8.2
 * Requires at least: 6.7.1
 */

function cmx_misbuero_is_local_env(): bool {
	$env = '';
	if (\function_exists('\\wp_get_environment_type')) {
		$env = \wp_get_environment_type();
	} elseif (\defined('WP_ENVIRONMENT_TYPE')) {
		$env = WP_ENVIRONMENT_TYPE;
	} elseif (\defined('WP_ENV')) {
		$env = WP_ENV;
	}

	return \is_string($env) && \strtolower($env) === 'local';
}

function cmx_misbuero_maybe_bump_local_version(): void {
	if (!cmx_misbuero_is_local_env()) {
		return;
	}

	$plugin_file = __FILE__;
	if (!\is_readable($plugin_file) || !\is_writable($plugin_file)) {
		return;
	}

	$contents = \file_get_contents($plugin_file);
	if ($contents === false) {
		return;
	}

	$version = \function_exists('\\wp_date') ? \wp_date('ymd.His') : \date('ymd.His');

	if (!\preg_match('/^\\s*\\*\\s*Version:\\s*([^\\r\\n]+)/m', $contents, $match)) {
		return;
	}

	$current = \trim((string) $match[1]);
	if ($current === $version) {
		return;
	}

	$updated = \preg_replace('/^(\\s*\\*\\s*Version:\\s*)([^\\r\\n]+)/m', '$1' . $version, $contents, 1);
	if ($updated === null || $updated === $contents) {
		return;
	}

	$tmp = $plugin_file . '.tmp';
	$bytes = \file_put_contents($tmp, $updated, LOCK_EX);
	if ($bytes === false) {
		@\unlink($tmp);
		return;
	}

	$perms = @\fileperms($plugin_file);
	if ($perms) {
		@\chmod($tmp, $perms & 0777);
	}

	if (!@\rename($tmp, $plugin_file)) {
		@\unlink($tmp);
	}
}

cmx_misbuero_maybe_bump_local_version();


$cmx_autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($cmx_autoload)) {
	require_once $cmx_autoload;
} else {
	error_log('[cmx-misbuero] vendor/autoload.php fehlt – bitte "composer install" ausführen oder den /vendor-Ordner bereitstellen.');
	add_action('admin_notices', function () {
		echo '<div class="notice notice-error"><p><strong>CMX Mis Buero:</strong> Der Ordner <code>/vendor</code> fehlt. Bitte <code>composer install</code> ausführen oder den Ordner hochladen, damit alle Funktionen (PDF/QR etc.) verfügbar sind.</p></div>';
	});
}
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/katalog_access.php';
require_once __DIR__ . '/includes/webdav.php';
require_once __DIR__ . '/includes/featured_images.php';
require_once __DIR__ . '/includes/dokumente.php';
require_once __DIR__ . '/includes/uploads.php';
require_once __DIR__ . '/includes/upload_form.php';
require_once __DIR__ . '/monitoring/anyboard/index.php';
// Login-spezifische Hooks (z. B. Passwort-Reset) müssen auch ohne eingeloggten Nutzer verfügbar sein.
require_once __DIR__ . '/includes/login_manager.php';
require_once __DIR__ . '/includes/login_ui.php';
require_once __DIR__ . '/includes/help_screens.php';


define('CMX_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CMX_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CMX_DOMAIN', explode('.', parse_url(home_url(), PHP_URL_HOST))[0]);
define('CMX_UPLOADS_MISBUERO', wp_get_upload_dir()['basedir'] . '/misbuero/');

register_activation_hook( __FILE__, __NAMESPACE__ . '\\cmx_misbuero_activate' );
function cmx_misbuero_activate(): void {
	MIS_BUERO_BELEG_UPLOAD::rewrite();
	flush_rewrite_rules( false );
}



add_action('init', function() {
	$myName = wp_get_current_user();

	if ( $myName && $myName->exists() ) {
		if ($myName->display_name !== "CLOUD Meister") {
			require_once __DIR__ . '/includes/presets.php';
		}
		require_once __DIR__ . '/includes/index.php';
	}

	// foreach (explode(',', 'kontakte,artikel,belege,aufwaende,Kassenbuch,dokumente,cockpit,projekte,einstellungen,postfach,bankabgleich') as $module) {
	// foreach (explode(',', 'kontakte,artikel,belege,ausgaben,cockpit,dokumente,projekte,einstellungen') as $module) {
	foreach (explode(',', 'kontakte,artikel,belege,cockpit,dokumente,projekte,einstellungen') as $module) {
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



	// Absender-Adresse dynamisch setzen
	add_filter('wp_mail_from', function($email) use ($sub) {
			return $sub . '@misbuero.ch';
	});

	// Absender-Name dynamisch setzen
	add_filter('wp_mail_from_name', function($name) use ($sub) {
			return 'Mis Buero - ' . ucfirst($sub);
	});

	update_option('blogname', 'Mis Buero – ' . $sub);
	// update_option('blogdescription', 'Der neue Untertitel der Website');

	add_filter('wp_mail', function($args) use ($sub) {

			$headers = $args['headers'] ?? [];

			if (!is_array($headers)) {
					$headers = preg_split('/\r\n|\r|\n/', $headers);
			}

			// $headers[] = 'Reply-To: ' . $sub . '@misbuero.ch';
			$headers[] = 'Reply-To: ' .get_user_meta( get_current_user_id(),'cmx_mail_backup',true);

			$args['headers'] = $headers;

			return $args;
	});


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
