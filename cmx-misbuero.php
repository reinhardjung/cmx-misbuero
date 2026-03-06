<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Plugin Name: CLOUD Meister - Mis Büro
 * Plugin URI: https://misbuero.ch/wp-content/uploads/cmx-misbuero.zip
 * Description: Mis Büro by CLOUD Meister.
 * Version: 3.6.914
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

/**
 * Bei bestehenden CPT-Beiträgen niemals einen leeren Titel speichern.
 * Wenn kein neuer Titel übergeben wird, bleibt der bisherige Titel erhalten.
 */
\add_filter('wp_insert_post_data', static function (array $data, array $postarr, array $unsanitized_postarr, bool $update): array {
	static $manual_title_by_post = [];

	if (!$update) {
		return $data;
	}

	$post_id = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
	if ($post_id <= 0) {
		return $data;
	}

	$post_type = \sanitize_key((string) ($data['post_type'] ?? ($postarr['post_type'] ?? '')));
	if ($post_type === '') {
		return $data;
	}

	$ptype_obj = \get_post_type_object($post_type);
	if (!$ptype_obj instanceof \WP_Post_Type || !empty($ptype_obj->_builtin)) {
		return $data;
	}

	// Manuell gesetzter Titel im aktuellen Editor-Request hat immer Vorrang.
	$request_post_id = isset($_POST['post_ID']) ? (int) $_POST['post_ID'] : 0;
	$request_title = '';
	if (isset($_POST['post_title'])) {
		$request_title = \trim(\sanitize_text_field((string) \wp_unslash($_POST['post_title'])));
	}
	if (
		$request_post_id > 0
		&& $request_post_id === $post_id
		&& $request_title !== ''
		&& !isset($manual_title_by_post[$post_id])
	) {
		$manual_title_by_post[$post_id] = $request_title;
	}
	if (isset($manual_title_by_post[$post_id]) && $manual_title_by_post[$post_id] !== '') {
		$data['post_title'] = (string) $manual_title_by_post[$post_id];
		return $data;
	}

	$incoming_title = \trim((string) ($data['post_title'] ?? ''));
	if ($incoming_title !== '') {
		return $data;
	}

	$current = \get_post($post_id);
	if (!$current instanceof \WP_Post) {
		return $data;
	}
	if ((string) $current->post_type !== $post_type) {
		return $data;
	}

	$current_status = (string) $current->post_status;
	if ($current_status === 'auto-draft' || $current_status === 'trash') {
		return $data;
	}

	$current_title = \trim((string) $current->post_title);
	if ($current_title === '') {
		return $data;
	}

	$data['post_title'] = $current_title;
	if (empty($data['post_name'])) {
		$data['post_name'] = (string) $current->post_name;
	}

	return $data;
}, 999, 4);

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


		$parse_email_list = static function (string $raw): array {
			$parts = \preg_split('/[,\s;]+/', (string) $raw) ?: [];
			$clean = [];
			foreach ($parts as $part) {
				$candidate = \sanitize_email((string) $part);
				if (\is_email($candidate)) {
					$clean[$candidate] = $candidate;
				}
			}
			return \array_values($clean);
		};

		$smtp_mail_settings = static function () use ($parse_email_list): array {
			$opts = (array) \get_option('cmx_einstellungen', []);
			$host = \sanitize_text_field((string) ($opts['smtp_host'] ?? ''));
			$user = \sanitize_email((string) ($opts['email_address'] ?? ''));
			if (!\is_email($user)) {
				$user = '';
			}
			$pass = (string) ($opts['email_password'] ?? '');

			$alias_general = \sanitize_email((string) ($opts['email_alias'] ?? ''));
			if (!\is_email($alias_general)) {
				$alias_general = '';
			}
			$alias_belege = \sanitize_email((string) ($opts['email_alias_belege'] ?? ''));
			if (!\is_email($alias_belege)) {
				$alias_belege = '';
			}
			$reply = \sanitize_email((string) ($opts['reply'] ?? ''));
			if (!\is_email($reply)) {
				$reply = '';
			}

			return [
				'host' => $host,
				'user' => $user,
				'pass' => $pass,
				'port' => 587,
				'secure' => 'tls',
				'email_address' => $user,
				'alias_general' => $alias_general,
				'alias_belege' => $alias_belege,
				'reply' => $reply,
				'bcc' => $parse_email_list((string) ($opts['email_bcc'] ?? '')),
				'enabled' => ($host !== '' && $user !== '' && $pass !== ''),
			];
		};


		$resolve_mail_sender = static function () use ($sub, $smtp_mail_settings): array {
			$smtp = $smtp_mail_settings();
			$context = \sanitize_key((string) ($GLOBALS['cmx_mail_context'] ?? ''));
			$is_beleg_context = \str_starts_with($context, 'beleg');

			$from_email = $is_beleg_context ? (string) ($smtp['alias_belege'] ?? '') : (string) ($smtp['alias_general'] ?? '');
			if (!\is_email($from_email)) {
				$from_email = (string) ($smtp['email_address'] ?? '');
			}
			if (!\is_email($from_email)) {
				$admin = \sanitize_email((string) \get_option('admin_email'));
				if (\is_email($admin)) {
					$from_email = $admin;
				}
			}
			if (!\is_email($from_email)) {
				$site_host = (string) \wp_parse_url(\home_url('/'), \PHP_URL_HOST);
				$site_host = \strtolower((string) \preg_replace('~^www\.~i', '', \trim($site_host)));
				$fallback = $site_host !== '' ? \sanitize_email('no-reply@' . $site_host) : '';
				if (\is_email($fallback)) {
					$from_email = $fallback;
				}
			}

			$name = '';
			$site_name = \trim((string) \get_option('blogname'));
			if ($site_name === '') {
				$site_name = \trim((string) \get_bloginfo('name'));
			}
			if ($site_name !== '') {
				$customer_from_site = (string) \preg_replace('/^\s*mis\s*b(?:u|ue|ü)ro\s*[-–:]\s*/iu', '', $site_name);
				$customer_from_site = \trim((string) \preg_replace('/\s+/', ' ', (string) $customer_from_site));
				$name = 'Mis Büro - ' . ($customer_from_site !== '' ? $customer_from_site : $site_name);
			}
			if ($name === '') {
				$customer = \trim((string) \preg_replace('/[-_]+/', ' ', (string) $sub));
				$customer = \trim((string) \preg_replace('/\s+/', ' ', (string) $customer));
				$name = $customer !== '' ? ('Mis Büro - ' . $customer) : 'Mis Büro - CMX';
			}

			$reply_to_email = \sanitize_email((string) ($smtp['reply'] ?? ''));
			if (!\is_email($reply_to_email)) {
				$reply_to_email = $from_email;
			}
			$reply_to_name = $name;

			$force_current_user_sender = !empty($GLOBALS['cmx_force_current_user_mail_sender']);
			if ($force_current_user_sender && !\is_email((string) ($smtp['reply'] ?? ''))) {
				$current_user = \wp_get_current_user();
				$current_user_email = ($current_user instanceof \WP_User && $current_user->exists())
					? \sanitize_email((string) $current_user->user_email)
					: '';
				$current_user_name = ($current_user instanceof \WP_User && $current_user->exists())
					? \trim((string) $current_user->display_name)
					: '';
				if ($current_user_name === '' && $current_user instanceof \WP_User && $current_user->exists()) {
					$current_user_name = \trim((string) $current_user->user_login);
				}

				$preferred_reply_email = '';
				$preferred_reply_name = '';
				if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_me_contact_reply_to')) {
					$me_contact = (array) \cmxbu_get_me_contact_reply_to('');
					$candidate_email = \sanitize_email((string) ($me_contact['email'] ?? ''));
					if (\is_email($candidate_email)) {
						$preferred_reply_email = $candidate_email;
						$preferred_reply_name = \trim((string) ($me_contact['name'] ?? ''));
					}
				}
				if ($preferred_reply_email === '' && \is_email($current_user_email)) {
					$preferred_reply_email = $current_user_email;
					$preferred_reply_name = $current_user_name;
				}
				if (\is_email($preferred_reply_email)) {
					$reply_to_email = $preferred_reply_email;
					if ($preferred_reply_name !== '') {
						$reply_to_name = $preferred_reply_name;
					}
				}
			}

			$envelope_email = \is_email((string) ($smtp['email_address'] ?? ''))
				? (string) $smtp['email_address']
				: $from_email;

			return [
				'email' => (string) $from_email,
				'name'  => (string) $name,
				'reply_to_email' => (string) $reply_to_email,
				'reply_to_name' => (string) $reply_to_name,
				'envelope_email' => (string) $envelope_email,
			];
		};

		$get_configured_bcc = static function () use ($smtp_mail_settings): array {
			$smtp = $smtp_mail_settings();
			return \is_array($smtp['bcc'] ?? null) ? \array_values($smtp['bcc']) : [];
		};

		// Absender-Adresse global aus den E-Mail-Einstellungen setzen.
		add_filter('wp_mail_from', function($email) use ($resolve_mail_sender) {
			$sender = $resolve_mail_sender();
			return $sender['email'] !== '' ? $sender['email'] : $email;
		}, PHP_INT_MAX);

		// Absender-Name global aus den E-Mail-Einstellungen setzen.
		add_filter('wp_mail_from_name', function($name) use ($resolve_mail_sender) {
			$sender = $resolve_mail_sender();
			return $sender['name'] !== '' ? $sender['name'] : $name;
		}, PHP_INT_MAX);

	update_option('blogname', 'Mis Buero – ' . $sub);
	// update_option('blogdescription', 'Der neue Untertitel der Website');

		add_filter('wp_mail', function($args) use ($resolve_mail_sender, $get_configured_bcc) {
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
						return stripos($line, 'Reply-To:') !== 0
							&& stripos($line, 'From:') !== 0
							&& stripos($line, 'Bcc:') !== 0;
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
				$bcc_list = $get_configured_bcc();
				if (!empty($bcc_list)) {
					$headers[] = 'Bcc: ' . \implode(', ', $bcc_list);
				}

				$args['headers'] = $headers;

				return $args;
		}, PHP_INT_MAX);

		// Letzte Instanz: PHPMailer selbst überschreiben (falls SMTP-Plugins vorher From setzen)
		add_action('phpmailer_init', function($phpmailer) use ($resolve_mail_sender, $smtp_mail_settings, $get_configured_bcc): void {
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
			$existing_bcc = [];
			foreach ((array) $phpmailer->getBccAddresses() as $entry) {
				if (\is_array($entry) && isset($entry[0]) && \is_string($entry[0])) {
					$existing_bcc[\strtolower($entry[0])] = true;
				}
			}
			foreach ($get_configured_bcc() as $bcc_email) {
				$bcc_email = \sanitize_email((string) $bcc_email);
				if (!\is_email($bcc_email)) {
					continue;
				}
				if (isset($existing_bcc[\strtolower($bcc_email)])) {
					continue;
				}
				try {
					$phpmailer->addBCC($bcc_email);
					$existing_bcc[\strtolower($bcc_email)] = true;
				} catch (\Throwable $e) {
					// Do not abort sending if one BCC address is invalid.
				}
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
