<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

if (!\function_exists(__NAMESPACE__ . '\\cmx_logout_redirect_url')) {
	function cmx_logout_redirect_url(): string {
		return (string) \home_url('/');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_initial_password_host_subdomain')) {
	function cmx_initial_password_host_subdomain(): string {
		$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
		if (!\is_string($host) || \trim($host) === '') {
			$host = (string) \wp_parse_url(\home_url('/'), \PHP_URL_HOST);
		}
		$host = \strtolower((string) \preg_replace('~^www\.~i', '', \trim((string) $host)));
		if ($host === '' || !\str_ends_with($host, '.misbuero.ch')) {
			return '';
		}
		$parts = \explode('.', $host);
		$subdomain = (string) ($parts[0] ?? '');
		return \preg_match('~^[a-z0-9-]+$~', $subdomain) ? $subdomain : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_initial_password_has_mail_transport')) {
	function cmx_initial_password_has_mail_transport(): bool {
		$options = (array) \get_option('cmx_einstellungen', []);
		$smtp_host = \trim((string) ($options['smtp_host'] ?? ''));
		$smtp_user = \sanitize_email((string) ($options['email_address'] ?? ''));
		$smtp_pass = (string) ($options['email_password'] ?? '');
		if ($smtp_host !== '' && \is_email($smtp_user) && $smtp_pass !== '') {
			return true;
		}

		return \is_executable('/usr/sbin/sendmail');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_initial_password_bootstrap_user')) {
	function cmx_initial_password_bootstrap_user(string $login_or_email): ?\WP_User {
		$subdomain = cmx_initial_password_host_subdomain();
		if ($subdomain === '' || cmx_initial_password_has_mail_transport()) {
			return null;
		}

		$login_or_email = \trim($login_or_email);
		if ($login_or_email === '') {
			return null;
		}

		$user = \get_user_by('login', $login_or_email);
		if (!$user instanceof \WP_User && \is_email($login_or_email)) {
			$user = \get_user_by('email', $login_or_email);
		}
		if (!$user instanceof \WP_User || !$user->exists()) {
			return null;
		}
		if (\strtolower((string) $user->user_login) !== $subdomain) {
			return null;
		}
		if (!\in_array('administrator', (array) $user->roles, true)) {
			return null;
		}
		if ((string) \get_user_meta((int) $user->ID, '_cmx_initial_password_set_at', true) !== '') {
			return null;
		}

		$until = (int) \get_user_meta((int) $user->ID, '_cmx_initial_password_bootstrap_until', true);
		if ($until > 0) {
			return \time() <= $until ? $user : null;
		}

		$registered_at = \strtotime((string) $user->user_registered);
		if ($registered_at <= 0 || $registered_at < (\time() - 7 * \DAY_IN_SECONDS)) {
			return null;
		}

		return $user;
	}
}

\add_action('login_form_lostpassword', function (): void {
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
		return;
	}

	$raw_login = isset($_POST['user_login']) ? \sanitize_text_field((string) \wp_unslash($_POST['user_login'])) : '';
	$user = cmx_initial_password_bootstrap_user($raw_login);
	if (!$user instanceof \WP_User) {
		return;
	}

	$key = \get_password_reset_key($user);
	if (\is_wp_error($key)) {
		return;
	}

	$url = \network_site_url(
		'wp-login.php?action=rp&key=' . \rawurlencode((string) $key) . '&login=' . \rawurlencode((string) $user->user_login) . '&cmx_initial=1',
		'login'
	);
	\wp_safe_redirect($url);
	exit;
}, 1);

\add_action('after_password_reset', function ($user): void {
	if (!$user instanceof \WP_User || !$user->exists()) {
		return;
	}

	\update_user_meta((int) $user->ID, '_cmx_initial_password_set_at', \current_time('mysql'));
	\delete_user_meta((int) $user->ID, '_cmx_initial_password_bootstrap_until');
}, 10, 1);

\add_action('wp_login', function ($user_login, $user): void {
	if (!$user instanceof \WP_User || !$user->exists()) {
		return;
	}
	if (\strtolower((string) $user->user_login) !== cmx_initial_password_host_subdomain()) {
		return;
	}
	if ((string) \get_user_meta((int) $user->ID, '_cmx_initial_password_set_at', true) !== '') {
		return;
	}

	\update_user_meta((int) $user->ID, '_cmx_initial_password_set_at', \current_time('mysql'));
	\delete_user_meta((int) $user->ID, '_cmx_initial_password_bootstrap_until');
}, 5, 2);

\add_filter('logout_redirect', function ($redirect_to, $requested_redirect_to, $user): string {
	return cmx_logout_redirect_url();
}, 20, 3);

/**
 * Passwort-Reset auch an die hinterlegte Backup-Mail senden.
 */
add_filter('retrieve_password_message', function($message, $key, $user_login, $user_data) {
    if (!($user_data instanceof \WP_User)) {
        return $message;
    }

    $backup_mail = get_user_meta($user_data->ID, 'cmx_mail_backup', true);
    if (!$backup_mail || !is_email($backup_mail)) {
        return $message;
    }

    // Doppelte Zustellung vermeiden, falls Backup = primäre Mail.
    if (strcasecmp($backup_mail, $user_data->user_email) === 0) {
        return $message;
    }

    // Betreff wie WordPress generieren, inklusive Titel-Filter.
    $title = sprintf(
        __('[%s] Password Reset'),
        wp_specialchars_decode(get_option('blogname'), ENT_QUOTES)
    );
	$title = apply_filters('retrieve_password_title', $title, $user_login, $user_data);

	$headers = [];
	if ((string) $message !== \wp_strip_all_tags((string) $message)) {
		if (\function_exists(__NAMESPACE__ . '\\cmx_passwort_mails_with_html_header')) {
			$headers = cmx_passwort_mails_with_html_header($headers);
		} else {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		}
	}

	wp_mail($backup_mail, $title, $message, $headers);

    return $message;
}, 10, 4);

/**
 * Beim ersten Login (oder User-Switch) Layout/Metabox-Settings vom User "cloudmeister" übernehmen.
 */
add_action('wp_login', __NAMESPACE__ . '\\cmx_copy_layout_from_cloudmeister', 10, 2);
add_action('set_current_user', __NAMESPACE__ . '\\cmx_maybe_copy_layout_from_cloudmeister', 20, 1);

function cmx_maybe_copy_layout_from_cloudmeister($user_id): void {
	if (!\is_user_logged_in()) {
		return;
	}
	$user = \wp_get_current_user();
	if (!$user instanceof \WP_User || !$user->exists()) {
		return;
	}
	cmx_copy_layout_from_cloudmeister($user->user_login, $user);
}

function cmx_copy_layout_from_cloudmeister(string $user_login, $user): void {
	$blog_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;

	if (!$user instanceof \WP_User) {
		return;
	}
	if ($user->user_login === 'cloudmeister') {
		return;
	}

	$flag_key = 'cmx_layout_copied_' . $blog_id;
	$flag_val = (string) get_user_meta($user->ID, $flag_key, true);

	if (\function_exists(__NAMESPACE__ . '\\cmx_layout_defaults_apply_to_user')) {
		$defaults_version = \CLOUDMEISTER\CMX\Buero\cmx_layout_defaults_version();
		$user_version = (string) get_user_meta($user->ID, 'cmx_layout_defaults_version', true);
		$has_layout = \CLOUDMEISTER\CMX\Buero\cmx_layout_defaults_user_has_layout($user->ID);
		if ($has_layout) {
			return;
		}
		if ($user_version !== $defaults_version) {
			$applied = \CLOUDMEISTER\CMX\Buero\cmx_layout_defaults_apply_to_user($user->ID);
			if ($applied) {
				update_user_meta($user->ID, $flag_key, $defaults_version);
				return;
			}
		} elseif ($flag_val === $defaults_version) {
			return;
		}
	} else {
		if ($flag_val !== '') {
			return;
		}
	}

	$source = get_user_by('login', 'cloudmeister');
	if (!$source || empty($source->ID)) {
		return;
	}

	global $wpdb;
	$blog_prefix = is_object($wpdb) ? (string) $wpdb->get_blog_prefix($blog_id) : '';

	$meta = get_user_meta($source->ID);
	$copied_any = false;
	foreach ($meta as $key => $values) {
		if (!preg_match('/(^|_)meta-box-order_|(^|_)metaboxhidden_|(^|_)closedpostboxes_|(^|_)screen_layout_/', $key)) {
			continue;
		}
		$value = is_array($values) ? ($values[0] ?? null) : $values;
		if ($value === null) {
			continue;
		}
		if (is_string($value)) {
			$maybe = maybe_unserialize($value);
			if ($maybe !== $value) {
				$value = $maybe;
			}
		}

		$is_meta_order = (strpos($key, 'meta-box-order_') !== false);
		$is_hidden = (strpos($key, 'metaboxhidden_') !== false);
		$is_closed = (strpos($key, 'closedpostboxes_') !== false);
		$is_screen_layout = (strpos($key, 'screen_layout_') !== false);

		if (($is_meta_order || $is_hidden || $is_closed) && !is_array($value)) {
			if (is_string($value)) {
				$parts = array_values(array_filter(array_map('trim', explode(',', $value))));
				$value = $parts;
			}
		}

		if ($is_meta_order && !is_array($value)) {
			if ($blog_prefix !== '' && strpos($key, $blog_prefix) === 0) {
				delete_user_meta($user->ID, $key);
			} else {
				delete_user_option($user->ID, $key, true);
			}
			continue;
		}
		if (($is_hidden || $is_closed) && !is_array($value)) {
			if ($blog_prefix !== '' && strpos($key, $blog_prefix) === 0) {
				delete_user_meta($user->ID, $key);
			} else {
				delete_user_option($user->ID, $key, true);
			}
			continue;
		}
		if ($is_screen_layout && is_array($value)) {
			$value = reset($value);
		}

		if ($blog_prefix !== '' && strpos($key, $blog_prefix) === 0) {
			update_user_meta($user->ID, $key, $value);
			$copied_any = true;
			continue;
		}

		update_user_option($user->ID, $key, $value, true);
		$copied_any = true;
	}

	if ($copied_any) {
		update_user_meta($user->ID, $flag_key, 'legacy');
	}
}

/**
 * Entfernt den Demo-User "vorlage", sobald die Instanz nicht mehr auf der Subdomain "vorlage.*" läuft.
 * Damit verhindern wir, dass der Platzhalter-Account in produktiven Umgebungen bestehen bleibt.
 */
// add_action('init', function () {
// 	$user = get_user_by('login', 'vorlage');
// 	if (!$user instanceof \WP_User) {
// 		return;
// 	}

// 	$host = parse_url(home_url(), PHP_URL_HOST);
// 	if (!$host) {
// 		return; // Keine Host-Info verfügbar, nichts tun.
// 	}

// 	$labels = explode('.', $host);
// 	$sub    = $labels[0] ?? '';

// 	if (strcasecmp($sub, 'vorlage') === 0) {
// 		return; // Bleibt auf der vorlage-Subdomain erlaubt.
// 	}

// 	if (!function_exists('wp_delete_user')) {
// 		require_once ABSPATH . 'wp-admin/includes/user.php';
// 	}

// 	wp_delete_user($user->ID);
// });
