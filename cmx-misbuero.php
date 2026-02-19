<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Plugin Name: CLOUD Meister - Mis Büro
 * Plugin URI: https://misbuero.ch/wp-content/uploads/cmx-misbuero.zip
 * Description: Mis Büro by CLOUD Meister.
 * Version: 2.19.2307
 * Text Domain: cmx-misbuero
 * Domain Path: /languages
 * Author: CLOUD Meister
 * Author URI: https://cloudmeister.ch/
 * License: GPL2
 * Requires PHP: 8.2
 * Requires at least: 6.7.1
 */

require_once __DIR__ . '/includes/cmx_version.php';


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
require_once __DIR__ . '/includes/notizen.php';
require_once __DIR__ . '/includes/projekte.php';
require_once __DIR__ . '/includes/katalog_access.php';
require_once __DIR__ . '/includes/telefonbuch_access.php';
require_once __DIR__ . '/includes/webdav.php';
require_once __DIR__ . '/includes/featured_images.php';
require_once __DIR__ . '/includes/dokumente.php';
require_once __DIR__ . '/includes/uploads.php';
require_once __DIR__ . '/includes/upload_form.php';
require_once __DIR__ . '/includes/startseite_fix.php';
require_once __DIR__ . '/monitoring/anyboard/index.php';
require_once __DIR__ . '/includes/passwort-mails.php';
// Login-spezifische Hooks (z. B. Passwort-Reset) müssen auch ohne eingeloggten Nutzer verfügbar sein.
require_once __DIR__ . '/includes/login_manager.php';
require_once __DIR__ . '/includes/login_ui.php';
require_once __DIR__ . '/includes/help_screens.php';
require_once __DIR__ . '/includes/layout_export.php';
require_once __DIR__ . '/includes/layout_defaults.php';


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



	$resolve_mail_sender = static function () use ($sub): array {
		$current_user = wp_get_current_user();

		$email = '';
		$name  = '';

		if ($current_user instanceof \WP_User && $current_user->exists()) {
			$user_email = sanitize_email((string) $current_user->user_email);
			if (is_email($user_email)) {
				$email = $user_email;
			}

			$name = trim((string) $current_user->display_name);
			if ($name === '') {
				$full = trim((string) $current_user->user_firstname . ' ' . (string) $current_user->user_lastname);
				if ($full !== '') {
					$name = $full;
				}
			}
			if ($name === '') {
				$name = (string) $current_user->user_login;
			}
		}

		if ($email === '') {
			$fallback = sanitize_email($sub . '@misbuero.ch');
			if (is_email($fallback)) {
				$email = $fallback;
			} else {
				$admin = sanitize_email((string) get_option('admin_email'));
				if (is_email($admin)) {
					$email = $admin;
				}
			}
		}

		if ($name === '') {
			$name = 'Mis Buero - ' . ucfirst($sub);
		}

		return [
			'email' => (string) $email,
			'name'  => (string) $name,
		];
	};

	$is_beleg_send_context = static function (): bool {
		return (($GLOBALS['cmx_mail_context'] ?? '') === 'beleg_send');
	};

	// Absender-Adresse pro eingeloggtem WP-User setzen (spät, damit wir gewinnen)
	add_filter('wp_mail_from', function($email) use ($resolve_mail_sender, $is_beleg_send_context) {
		if ($is_beleg_send_context()) {
			return $email;
		}
		$sender = $resolve_mail_sender();
		return $sender['email'] !== '' ? $sender['email'] : $email;
	}, PHP_INT_MAX);

	// Absender-Name pro eingeloggtem WP-User setzen (spät, damit wir gewinnen)
	add_filter('wp_mail_from_name', function($name) use ($resolve_mail_sender, $is_beleg_send_context) {
		if ($is_beleg_send_context()) {
			return $name;
		}
		$sender = $resolve_mail_sender();
		return $sender['name'] !== '' ? $sender['name'] : $name;
	}, PHP_INT_MAX);

	update_option('blogname', 'Mis Buero – ' . $sub);
	// update_option('blogdescription', 'Der neue Untertitel der Website');

	add_filter('wp_mail', function($args) use ($resolve_mail_sender, $is_beleg_send_context) {
			if ($is_beleg_send_context()) {
				return $args;
			}

			$headers = $args['headers'] ?? [];

			if (!is_array($headers)) {
					$headers = preg_split('/\r\n|\r|\n/', $headers);
			}

			$sender = $resolve_mail_sender();
			if (!empty($sender['email'])) {
				$headers = array_values(array_filter((array) $headers, static function ($h): bool {
					$line = (string) $h;
					return stripos($line, 'Reply-To:') !== 0 && stripos($line, 'From:') !== 0;
				}));
				$safe_name = trim((string) preg_replace('/[\r\n]+/', ' ', (string) $sender['name']));
				if ($safe_name !== '') {
					$headers[] = 'From: ' . $safe_name . ' <' . $sender['email'] . '>';
					$headers[] = 'Reply-To: ' . $safe_name . ' <' . $sender['email'] . '>';
				} else {
					$headers[] = 'From: ' . $sender['email'];
					$headers[] = 'Reply-To: ' . $sender['email'];
				}
			}

			$args['headers'] = $headers;

			return $args;
	}, PHP_INT_MAX);

	// Letzte Instanz: PHPMailer selbst überschreiben (falls SMTP-Plugins vorher From setzen)
	add_action('phpmailer_init', function($phpmailer) use ($resolve_mail_sender, $is_beleg_send_context): void {
		if ($is_beleg_send_context()) {
			return;
		}
		if (!$phpmailer instanceof \PHPMailer\PHPMailer\PHPMailer) {
			return;
		}
		$sender = $resolve_mail_sender();
		$email = sanitize_email((string) ($sender['email'] ?? ''));
		if (!is_email($email)) {
			return;
		}
		$name = trim((string) preg_replace('/[\r\n]+/', ' ', (string) ($sender['name'] ?? '')));

		try {
			$phpmailer->setFrom($email, $name, false);
		} catch (\Throwable $e) {
			$phpmailer->From = $email;
			if ($name !== '') {
				$phpmailer->FromName = $name;
			}
		}
		$phpmailer->Sender = $email;
		$phpmailer->clearReplyTos();
		$phpmailer->addReplyTo($email, $name);
	}, PHP_INT_MAX);


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
