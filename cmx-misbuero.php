<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Plugin Name: CLOUD Meister - Mis Büro
 * Plugin URI: https://misbuero.ch/wp-content/uploads/cmx-misbuero.zip
 * Description: Mis Büro by CLOUD Meister.
 * Version: 3.2.7
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
	foreach (explode(',', 'kontakte,artikel,belege,cockpit,dokumente,projekte,einstellungen,scanner') as $module) {
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


	$smtp_mail_settings = static function (): array {
		$host = \defined('CMX_SMTP_HOST') ? \trim((string) CMX_SMTP_HOST) : '';
		$user = \defined('CMX_SMTP_USER') ? \trim((string) CMX_SMTP_USER) : '';
		$pass = \defined('CMX_SMTP_PASS') ? (string) CMX_SMTP_PASS : '';
		$port = \defined('CMX_SMTP_PORT') ? (int) CMX_SMTP_PORT : 587;
		if ($port <= 0) {
			$port = 587;
		}
		$secure = \defined('CMX_SMTP_SECURE') ? \strtolower(\trim((string) CMX_SMTP_SECURE)) : 'tls';
		if (!\in_array($secure, ['tls', 'ssl', ''], true)) {
			$secure = 'tls';
		}

		$from_email = \defined('CMX_MAIL_FROM_EMAIL') ? \sanitize_email((string) CMX_MAIL_FROM_EMAIL) : '';
		if (!\is_email($from_email)) {
			$from_email = '';
		}
		$from_name = \defined('CMX_MAIL_FROM_NAME') ? \trim((string) CMX_MAIL_FROM_NAME) : '';

		return [
			'host' => $host,
			'user' => $user,
			'pass' => $pass,
			'port' => $port,
			'secure' => $secure,
			'from_email' => $from_email,
			'from_name' => $from_name,
			'enabled' => ($host !== '' && $user !== '' && $pass !== ''),
		];
	};


	$resolve_mail_sender = static function () use ($sub, $smtp_mail_settings): array {
		$current_user = wp_get_current_user();
		$smtp = $smtp_mail_settings();

		$current_user_email = '';
		$current_user_name = '';
		$current_user_login = '';
		$me_contact_email = '';
		$me_contact_name = '';
		$site_host = (string) \wp_parse_url(\home_url('/'), \PHP_URL_HOST);
		$site_host = \strtolower((string) \preg_replace('~^www\.~i', '', \trim($site_host)));
		$site_labels = \array_values(\array_filter(\explode('.', $site_host), static function ($part): bool {
			return $part !== '';
		}));
		$site_root_domain = $site_host;
		if (\count($site_labels) >= 2) {
			$site_root_domain = $site_labels[\count($site_labels) - 2] . '.' . $site_labels[\count($site_labels) - 1];
		}
		if ($current_user instanceof \WP_User && $current_user->exists()) {
			$user_email = sanitize_email((string) $current_user->user_email);
			if (is_email($user_email)) {
				$current_user_email = $user_email;
			}
			$current_user_login = \sanitize_user((string) $current_user->user_login, true);
			$current_user_login = \strtolower(\trim($current_user_login));
			$current_user_name = \trim((string) $current_user->display_name);
			if ($current_user_name === '') {
				$full_name = \trim((string) $current_user->user_firstname . ' ' . (string) $current_user->user_lastname);
				if ($full_name !== '') {
					$current_user_name = $full_name;
				}
			}
			if ($current_user_name === '') {
				$current_user_name = \trim((string) $current_user->user_login);
			}
		}

		$base_from_email = '';
		if ($smtp['from_email'] !== '') {
			$base_from_email = (string) $smtp['from_email'];
		}

		if ($base_from_email === '' && !empty($smtp['enabled'])) {
			$smtp_user_email = sanitize_email((string) ($smtp['user'] ?? ''));
			if (is_email($smtp_user_email)) {
				$base_from_email = $smtp_user_email;
			}
		}

		if ($base_from_email === '') {
			$fallback_domain = $site_root_domain !== '' ? $site_root_domain : $site_host;
			$fallback = $fallback_domain !== '' ? sanitize_email('no-reply@' . $fallback_domain) : '';
			if (is_email($fallback)) {
				$base_from_email = $fallback;
			} else {
				$admin = sanitize_email((string) get_option('admin_email'));
				if (is_email($admin)) {
					$base_from_email = $admin;
				}
			}
		}

		if ($base_from_email === '' && $current_user_email !== '') {
			$base_from_email = $current_user_email;
		}

		$name = '';
		if ($smtp['from_name'] !== '') {
			$name = (string) $smtp['from_name'];
		}

		if ($name === '') {
			$site_name = trim((string) get_option('blogname'));
			if ($site_name === '') {
				$site_name = trim((string) get_bloginfo('name'));
			}
			if ($site_name !== '') {
				$customer_from_site = (string) preg_replace('/^\s*mis\s*b(?:u|ue|ü)ro\s*[-–:]\s*/iu', '', $site_name);
				$customer_from_site = trim((string) preg_replace('/\s+/', ' ', (string) $customer_from_site));
				if ($customer_from_site === '') {
					$customer_from_site = $site_name;
				}
				$name = 'Mis Büro - ' . $customer_from_site;
			}
		}

		if ($name === '') {
			$customer = trim((string) preg_replace('/[-_]+/', ' ', (string) $sub));
			$customer = trim((string) preg_replace('/\s+/', ' ', (string) $customer));
			if ($customer !== '' && strcasecmp($customer, 'checkCheck123') !== 0) {
				$name = 'Mis Büro - ' . $customer;
			} else {
				$name = 'Mis Büro - CMX';
			}
		}

		$force_current_user_sender = !empty($GLOBALS['cmx_force_current_user_mail_sender']);
		if ($force_current_user_sender && \function_exists(__NAMESPACE__ . '\\cmxbu_get_me_contact_reply_to')) {
			$me_contact = (array) cmxbu_get_me_contact_reply_to('');
			$candidate_email = \sanitize_email((string) ($me_contact['email'] ?? ''));
			if (\is_email($candidate_email)) {
				$me_contact_email = $candidate_email;
				$me_contact_name = \trim((string) ($me_contact['name'] ?? ''));
			}
		}
		$from_email = $base_from_email;
		$from_domain = \strtolower((string) \substr((string) \strrchr((string) $base_from_email, '@'), 1));
		$username_sender_email = '';
		if ($current_user_login !== '' && $site_root_domain !== '') {
			$username_sender_email = \sanitize_email($current_user_login . '@' . $site_root_domain);
		}
		$force_username_from = ($force_current_user_sender && \is_email($username_sender_email));
		$preferred_sender_email = $current_user_email;
		if ($force_username_from) {
			$preferred_sender_email = $username_sender_email;
		}
		$user_domain = \strtolower((string) \substr((string) \strrchr((string) $preferred_sender_email, '@'), 1));
		$from_labels = \array_values(\array_filter(\explode('.', $from_domain), static function ($part): bool {
			return $part !== '';
		}));
		$user_labels = \array_values(\array_filter(\explode('.', $user_domain), static function ($part): bool {
			return $part !== '';
		}));
		$from_root_domain = (\count($from_labels) >= 2)
			? ($from_labels[\count($from_labels) - 2] . '.' . $from_labels[\count($from_labels) - 1])
			: $from_domain;
		$user_root_domain = (\count($user_labels) >= 2)
			? ($user_labels[\count($user_labels) - 2] . '.' . $user_labels[\count($user_labels) - 1])
			: $user_domain;
		$can_use_user_email_as_from = (
			$preferred_sender_email !== ''
				&& (
					$force_username_from
					||
					($from_domain !== '' && $user_domain !== '' && $from_domain === $user_domain)
					|| ($from_root_domain !== '' && $user_root_domain !== '' && $from_root_domain === $user_root_domain)
				)
		);
		if ($can_use_user_email_as_from) {
			$from_email = $preferred_sender_email;
		} elseif ($from_email === '' && $preferred_sender_email !== '') {
			// Letzter Fallback, falls keine Basis-Adresse ermittelt werden konnte.
			$from_email = $preferred_sender_email;
		}

		if (!is_email($from_email)) {
			$from_email = $base_from_email;
		}
		$reply_to_email = $from_email;
		$reply_to_name = $name;
		if ($force_current_user_sender) {
			// Für Belegversand: "Das bin ich" primär, sonst aktueller User.
			$preferred_reply_email = $me_contact_email !== '' ? $me_contact_email : $current_user_email;
			$preferred_reply_name = $me_contact_name !== '' ? $me_contact_name : $current_user_name;
			if (\is_email($preferred_reply_email)) {
				$reply_to_email = $preferred_reply_email;
				if ($preferred_reply_name !== '') {
					$reply_to_name = $preferred_reply_name;
				}
			}
		}

		$envelope_email = is_email($base_from_email) ? $base_from_email : $from_email;

		return [
			'email' => (string) $from_email,
			'name'  => (string) $name,
			'reply_to_email' => (string) $reply_to_email,
			'reply_to_name' => (string) $reply_to_name,
			'envelope_email' => (string) $envelope_email,
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
			$from_email = sanitize_email((string) ($sender['email'] ?? ''));
			$reply_to_email = sanitize_email((string) ($sender['reply_to_email'] ?? ''));
			$from_name = trim((string) preg_replace('/[\r\n]+/', ' ', (string) ($sender['name'] ?? '')));
			$reply_to_name = trim((string) preg_replace('/[\r\n]+/', ' ', (string) ($sender['reply_to_name'] ?? '')));

			if (is_email($from_email)) {
				$headers = array_values(array_filter((array) $headers, static function ($h): bool {
					$line = (string) $h;
					return stripos($line, 'Reply-To:') !== 0 && stripos($line, 'From:') !== 0;
				}));

				if ($from_name !== '') {
					$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
				} else {
					$headers[] = 'From: ' . $from_email;
				}

				if (is_email($reply_to_email)) {
					if ($reply_to_name !== '') {
						$headers[] = 'Reply-To: ' . $reply_to_name . ' <' . $reply_to_email . '>';
					} else {
						$headers[] = 'Reply-To: ' . $reply_to_email;
					}
				}
			}

			$args['headers'] = $headers;

			return $args;
	}, PHP_INT_MAX);

	// Letzte Instanz: PHPMailer selbst überschreiben (falls SMTP-Plugins vorher From setzen)
	add_action('phpmailer_init', function($phpmailer) use ($resolve_mail_sender, $smtp_mail_settings, $is_beleg_send_context): void {
		if ($is_beleg_send_context()) {
			return;
		}
		if (!$phpmailer instanceof \PHPMailer\PHPMailer\PHPMailer) {
			return;
		}
		$sender = $resolve_mail_sender();
		$from_email = sanitize_email((string) ($sender['email'] ?? ''));
		if (!is_email($from_email)) {
			return;
		}
		$from_name = trim((string) preg_replace('/[\r\n]+/', ' ', (string) ($sender['name'] ?? '')));
		$reply_to_email = sanitize_email((string) ($sender['reply_to_email'] ?? ''));
		$reply_to_name = trim((string) preg_replace('/[\r\n]+/', ' ', (string) ($sender['reply_to_name'] ?? '')));
		$envelope_email = sanitize_email((string) ($sender['envelope_email'] ?? ''));
		if (!is_email($envelope_email)) {
			$envelope_email = $from_email;
		}
		$smtp = $smtp_mail_settings();

		if (!empty($smtp['enabled'])) {
			$phpmailer->isSMTP();
			$phpmailer->Host = (string) $smtp['host'];
			$phpmailer->Port = (int) $smtp['port'];
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = (string) $smtp['user'];
			$phpmailer->Password = (string) $smtp['pass'];
			$phpmailer->SMTPAutoTLS = true;
			$phpmailer->SMTPSecure = (string) $smtp['secure'];
		}

		try {
			$phpmailer->setFrom($from_email, $from_name, false);
		} catch (\Throwable $e) {
			$phpmailer->From = $from_email;
			if ($from_name !== '') {
				$phpmailer->FromName = $from_name;
			}
		}
		$phpmailer->Sender = $envelope_email;
		$phpmailer->clearReplyTos();
		if (is_email($reply_to_email)) {
			$phpmailer->addReplyTo($reply_to_email, $reply_to_name);
		}
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
