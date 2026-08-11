<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Plugin Name: CLOUD Meister - Mis Büro
 * Plugin URI: https://misbuero.ch/
 * Description: Mis Büro by CLOUD Meister.
 * Version: 8.11.334
 * Text Domain: cmx-misbuero
 * Domain Path: /languages
 * Author: CLOUD Meister
 * Author URI: https://cloudmeister.ch/
 * License: GPL2
 * Requires PHP: 8.4
 * Requires at least: 7.0
 */

const CMX_ENABLE_HELP_ONCE = false;
const CMX_ENABLE_SAVE_PERF_LOG = false; // TEMP: Kontakte/Artikel Save-Performance messen.

if (!\defined('MIS_BUERO_SERVICES_URL')) {
	$cmx_misbuero_services_url = (string) (\getenv('MIS_BUERO_SERVICES_URL') ?: 'https://services.misbuero.ch');
	\define('MIS_BUERO_SERVICES_URL', \rtrim($cmx_misbuero_services_url, '/'));
}
if (!\defined('MIS_BUERO_SERVICES_API_KEY')) {
	$cmx_misbuero_services_key = (string) (\getenv('MIS_BUERO_SERVICES_API_KEY') ?: \getenv('CMX_MIS_BUERO_SERVICES_API_KEY') ?: '');
	if (\trim($cmx_misbuero_services_key) !== '') {
		\define('MIS_BUERO_SERVICES_API_KEY', \trim($cmx_misbuero_services_key));
	}
}

$cmx_version_file = __DIR__ . '/includes/cmx_version.php';
if (\is_file($cmx_version_file)) {
	require_once $cmx_version_file;
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_misbuero_require_boot_file')) {
	function cmx_misbuero_require_boot_file(string $relative_path): bool {
		$file = __DIR__ . '/' . \ltrim($relative_path, '/');
		if (\is_file($file)) {
			require_once $file;
			return true;
		}

		\error_log('[cmx-misbuero] Boot-Datei fehlt: ' . $relative_path);
		\add_action('admin_notices', static function () use ($relative_path): void {
			echo '<div class="notice notice-error"><p><strong>CMX Mis Buero:</strong> Die Datei <code>' . \esc_html($relative_path) . '</code> fehlt. Bitte Plugin-Deploy erneut ausführen.</p></div>';
		});

		return false;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_register_core_post_type_shells')) {
	function cmx_register_core_post_type_shells(): void {
		$post_types = [
			'kontakte'      => ['Kontakte', 'dashicons-businessman', 10, ['title']],
			'artikel'       => ['Artikel', 'dashicons-tag', 20, ['title', 'editor']],
			'belege'        => ['Belege', 'dashicons-media-text', 30, ['title']],
			'dokumente'     => ['Dokumente', 'dashicons-media-document', 60, ['title', 'editor']],
			'projekte'      => ['Projekte', 'dashicons-portfolio', 70, ['title', 'editor']],
			'buchungen'     => ['Buchungen', 'dashicons-calendar-alt', 90, ['title']],
			'infrastruktur' => ['Infrastruktur', 'dashicons-cloud', 100, ['title']],
			'zugangsdaten'  => ['Zugangsdaten', 'dashicons-admin-network', 110, ['title']],
			'scanner'       => ['Scanner', 'dashicons-media-archive', 100, ['title']],
			'carent'        => ['CaRent', 'dashicons-car', 120, ['title']],
		];

		if (\function_exists(__NAMESPACE__ . '\\cmx_system_is_carent_enabled') && !cmx_system_is_carent_enabled()) {
			unset($post_types['carent']);
		}

		foreach ($post_types as $post_type => $config) {
			[$label, $icon, $position, $supports] = $config;
			$is_sensitive_admin_type = $post_type === 'zugangsdaten';
			\register_post_type($post_type, [
				'labels' => [
					'name'          => $label,
					'singular_name' => $label,
					'add_new_item'  => 'Hinzufügen',
					'edit_item'     => 'Bearbeiten',
				],
				'menu_position'       => $position,
				'supports'            => $supports,
				'public'              => !$is_sensitive_admin_type,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_icon'           => $icon,
				'show_in_rest'        => !$is_sensitive_admin_type,
				'has_archive'         => !$is_sensitive_admin_type,
				'publicly_queryable'  => !$is_sensitive_admin_type,
				'exclude_from_search' => $is_sensitive_admin_type,
				'query_var'           => !$is_sensitive_admin_type,
				'rewrite'             => $is_sensitive_admin_type ? false : ['slug' => $post_type],
			]);
		}
	}
}

\add_action('init', __NAMESPACE__ . '\\cmx_register_core_post_type_shells', 0);


$cmx_autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($cmx_autoload)) {
	require_once $cmx_autoload;
} else {
	error_log('[cmx-misbuero] vendor/autoload.php fehlt – bitte "composer install" ausführen oder den /vendor-Ordner bereitstellen.');
	add_action('admin_notices', function () {
		echo '<div class="notice notice-error"><p><strong>CMX Mis Buero:</strong> Der Ordner <code>/vendor</code> fehlt. Bitte <code>composer install</code> ausführen oder den Ordner hochladen, damit alle Funktionen (PDF/QR etc.) verfügbar sind.</p></div>';
	});
}
foreach ([
	'src/PDF/bootstrap.php',
	'includes/flat_storage.php',
	'includes/helpers.php',
	'includes/ssh_keys.php',
	'includes/analytics.php',
	'includes/functions.php',
	'includes/notizen.php',
	'includes/projekte.php',
	'includes/katalog_access.php',
	'includes/webdav.php',
	'includes/featured_images.php',
	'includes/dokumente.php',
	'includes/uploads.php',
	'includes/upload_form.php',
	'includes/startseite_fix.php',
	'monitoring/anyboard/index.php',
	'includes/passwort-mails.php',
	// Login-spezifische Hooks (z. B. Passwort-Reset) müssen auch ohne eingeloggten Nutzer verfügbar sein.
	'includes/login_manager.php',
	'includes/login_ui.php',
	'includes/help_screens.php',
	'includes/layout_export.php',
	'includes/layout_defaults.php',
	'includes/cpt_transfer.php',
	'includes/system_users.php',
	'includes/website/class-website-presets.php',
	'includes/website/class-website-icons.php',
	'includes/website/class-website-settings.php',
	'includes/website/class-website-renderer.php',
	'src/kontakte/carddav.php',
] as $cmx_boot_file) {
	if (!cmx_misbuero_require_boot_file($cmx_boot_file)) {
		return;
	}
}
if (\class_exists(__NAMESPACE__ . '\\PDF\\SignatureService')) {
	register_activation_hook(__FILE__, [\CLOUDMEISTER\CMX\Buero\PDF\SignatureService::class, 'activate']);
}
\CLOUDMEISTER\CMX\Buero\Website\Settings::init();
\CLOUDMEISTER\CMX\Buero\Website\Renderer::init();
if (CMX_ENABLE_SAVE_PERF_LOG) {
	cmx_misbuero_require_boot_file('includes/save_perf_log.php');
}
if (!\is_admin()) {
	cmx_misbuero_require_boot_file('includes/page-404.php');
}

cmx_misbuero_require_boot_file('includes/once.php');

if (\is_admin()) {
	cmx_misbuero_require_boot_file('src/einstellungen/index.php');
}


define('CMX_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CMX_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CMX_DOMAIN', explode('.', parse_url(home_url(), PHP_URL_HOST))[0]);
define('CMX_UPLOADS_MISBUERO', wp_get_upload_dir()['basedir'] . '/misbuero/');

if (!\function_exists(__NAMESPACE__ . '\\cmx_user_domain_value')) {
	function cmx_user_domain_value(): string {
		$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
		if (!$host) {
			return '';
		}

		$host = (string) \preg_replace('~^www\.~', '', (string) $host);
		$parts = \explode('.', $host);

		if (\count($parts) < 3) {
			return (string) ($parts[0] ?? '');
		}

		return (string) ($parts[0] ?? '');
	}
}

if (!\defined('UserDomain')) {
	\define('UserDomain', cmx_user_domain_value());
}

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
	if ($post_type === 'kontakte') {
		return $data;
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
	\add_option('mis_buero_services_url', 'https://services.misbuero.ch');
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
	$modules = ['kontakte', 'artikel', 'belege', 'cockpit', 'dokumente', 'buchungen'];
	if (\function_exists(__NAMESPACE__ . '\\cmx_system_is_carent_enabled') && cmx_system_is_carent_enabled()) {
		$modules[] = 'carent';
	}
	$modules = \array_merge($modules, ['projekte', 'einstellungen', 'scanner', 'budget', 'infrastruktur', 'zugangsdaten']);
	foreach ($modules as $module) {
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
	if (!\is_admin()
		&& !(\defined('DOING_AJAX') && DOING_AJAX)
		&& !(\defined('DOING_CRON') && DOING_CRON)
		&& !(\defined('REST_REQUEST') && REST_REQUEST)
		&& \in_array((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'HEAD'], true)
	) {
		$request_path = (string) \wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), \PHP_URL_PATH);
		$request_path = '/' . \trim($request_path, '/');
		$query = \trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
		if ($query === '' && \in_array($request_path, ['/', '/favicon.ico'], true)) {
			return;
		}
	}

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
			$email_name = \sanitize_text_field((string) ($opts['email_name'] ?? ''));

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
				'email_name' => $email_name,
				'bcc' => $parse_email_list((string) ($opts['email_bcc'] ?? '')),
				'enabled' => ($host !== '' && $user !== '' && $pass !== ''),
			];
		};

			$mail_transport_settings = static function () use ($smtp_mail_settings): array {
				return $smtp_mail_settings();
			};

			$resolve_mail_sender = static function () use ($sub, $smtp_mail_settings): array {
				$context = \sanitize_key((string) ($GLOBALS['cmx_mail_context'] ?? ''));
				$is_beleg_context = \str_starts_with($context, 'beleg');
				$smtp = $smtp_mail_settings();

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
					$fallback_domain = \str_ends_with($site_host, '.misbuero.ch') || $site_host === 'misbuero.ch'
						? 'misbuero.ch'
						: $site_host;
					$fallback = $fallback_domain !== '' ? \sanitize_email('no-reply@' . $fallback_domain) : '';
					if (\is_email($fallback)) {
						$from_email = $fallback;
					}
				}

				$name = \trim((string) ($smtp['email_name'] ?? ''));
				$site_name = \trim((string) \get_option('blogname'));
				if ($name === '' && $site_name === '') {
					$site_name = \trim((string) \get_bloginfo('name'));
				}
				if ($name === '' && $site_name !== '') {
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

			$should_skip_configured_bcc = static function (): bool {
				$context = \sanitize_key((string) ($GLOBALS['cmx_mail_context'] ?? ''));
				return \in_array($context, ['magic_login', 'website_contact'], true);
			};

			$should_preserve_message_reply_to = static function (): bool {
				$context = \sanitize_key((string) ($GLOBALS['cmx_mail_context'] ?? ''));
				return $context === 'website_contact';
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

	$target_blogname = 'Mis Buero – ' . $sub;
	if ((string) \get_option('blogname') !== $target_blogname) {
		\update_option('blogname', $target_blogname);
	}
	// update_option('blogdescription', 'Der neue Untertitel der Website');

			add_filter('wp_mail', function($args) use ($resolve_mail_sender, $get_configured_bcc, $should_skip_configured_bcc, $should_preserve_message_reply_to) {
				$headers = $args['headers'] ?? [];

				if (!is_array($headers)) {
						$headers = preg_split('/\r\n|\r|\n/', $headers);
				}

			$sender = $resolve_mail_sender();
			$from_email = sanitize_email((string) ($sender['email'] ?? ''));
			$reply_to_email = sanitize_email((string) ($sender['reply_to_email'] ?? ''));
			$from_name = trim((string) preg_replace('/[\r\n]+/', ' ', (string) ($sender['name'] ?? '')));
				$reply_to_name = trim((string) preg_replace('/[\r\n]+/', ' ', (string) ($sender['reply_to_name'] ?? '')));
				$preserve_message_reply_to = $should_preserve_message_reply_to();

				if (is_email($from_email)) {
					$headers = array_values(array_filter((array) $headers, static function ($h) use ($preserve_message_reply_to): bool {
						$line = (string) $h;
						return ($preserve_message_reply_to || stripos($line, 'Reply-To:') !== 0)
							&& stripos($line, 'From:') !== 0
							&& stripos($line, 'Bcc:') !== 0;
					}));

					if ($from_name !== '') {
						$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
				} else {
					$headers[] = 'From: ' . $from_email;
				}

				if (!$preserve_message_reply_to && is_email($reply_to_email)) {
					if ($reply_to_name !== '') {
						$headers[] = 'Reply-To: ' . $reply_to_name . ' <' . $reply_to_email . '>';
					} else {
						$headers[] = 'Reply-To: ' . $reply_to_email;
						}
					}
				}
					$bcc_list = $should_skip_configured_bcc() ? [] : $get_configured_bcc();
					if (!empty($bcc_list)) {
						$headers[] = 'Bcc: ' . \implode(', ', $bcc_list);
					}

				$args['headers'] = $headers;

				return $args;
		}, PHP_INT_MAX);

		// Letzte Instanz: PHPMailer selbst überschreiben (falls SMTP-Plugins vorher From setzen)
			add_action('phpmailer_init', function($phpmailer) use ($resolve_mail_sender, $mail_transport_settings, $get_configured_bcc, $should_skip_configured_bcc, $should_preserve_message_reply_to): void {
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
		$smtp = $mail_transport_settings();

		if (!empty($smtp['enabled'])) {
			$phpmailer->isSMTP();
			$phpmailer->Host = (string) $smtp['host'];
			$phpmailer->Port = (int) $smtp['port'];
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = (string) $smtp['user'];
			$phpmailer->Password = (string) $smtp['pass'];
			$phpmailer->SMTPAutoTLS = true;
			$phpmailer->SMTPSecure = (string) $smtp['secure'];
		} elseif (!\is_executable('/usr/sbin/sendmail')) {
			$phpmailer->isSMTP();
			$phpmailer->Host = '127.0.0.1';
			$phpmailer->Port = 25;
			$phpmailer->SMTPAuth = false;
			$phpmailer->SMTPAutoTLS = false;
			$phpmailer->SMTPSecure = '';
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
			if (!$should_preserve_message_reply_to()) {
				$phpmailer->clearReplyTos();
				if (is_email($reply_to_email)) {
					$phpmailer->addReplyTo($reply_to_email, $reply_to_name);
				}
			}
			$existing_bcc = [];
			foreach ((array) $phpmailer->getBccAddresses() as $entry) {
				if (\is_array($entry) && isset($entry[0]) && \is_string($entry[0])) {
					$existing_bcc[\strtolower($entry[0])] = true;
				}
			}
				$configured_bcc = $should_skip_configured_bcc() ? [] : $get_configured_bcc();
				foreach ($configured_bcc as $bcc_email) {
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
			\update_user_meta((int) $user_id, '_cmx_initial_password_bootstrap_until', \time() + 7 * \DAY_IN_SECONDS);
			error_log("CMX: Subdomain-Admin '$sub' wurde erstellt (ID $user_id).");
    }
}
