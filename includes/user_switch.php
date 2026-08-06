<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Benutzerwechsel mit Rückkehr-Link
 * - Fügt "zu Benutzer wechseln" in der Benutzerliste hinzu
 * - Speichert ursprünglichen Admin im Cookie
 * - Zeigt in der Admin-Bar rechts den Rückkehr-Link an
 */

if (!\function_exists(__NAMESPACE__ . '\\cmx_user_switch_is_enabled')) {
	function cmx_user_switch_is_enabled(): bool {
		$option_name = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			: 'cmx_einstellungen';
		$options = (array) \get_option($option_name, []);
		$value = $options['support_user_switch'] ?? '';

		if (\is_bool($value)) {
			return $value;
		}

		return \in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_user_switch_cookie_path')) {
	function cmx_user_switch_cookie_path(): string {
		return \defined('COOKIEPATH') && (string) COOKIEPATH !== '' ? (string) COOKIEPATH : '/';
	}
}

if (!\defined(__NAMESPACE__ . '\\CMX_USER_SWITCH_ORIGINAL_META')) {
	\define(__NAMESPACE__ . '\\CMX_USER_SWITCH_ORIGINAL_META', '_cmx_user_switch_original');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_user_switch_expires_at')) {
	function cmx_user_switch_expires_at(): int {
		return \time() + (int) \DAY_IN_SECONDS;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_user_switch_cookie_paths')) {
	function cmx_user_switch_cookie_paths(): array {
		$paths = [
			cmx_user_switch_cookie_path(),
			\defined('SITECOOKIEPATH') && (string) SITECOOKIEPATH !== '' ? (string) SITECOOKIEPATH : '',
			\defined('ADMIN_COOKIE_PATH') && (string) ADMIN_COOKIE_PATH !== '' ? (string) ADMIN_COOKIE_PATH : '',
			'/',
		];

		return \array_values(\array_unique(\array_filter($paths, 'strlen')));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_user_switch_cookie_domain')) {
	function cmx_user_switch_cookie_domain(): string {
		return \defined('COOKIE_DOMAIN') && COOKIE_DOMAIN ? (string) COOKIE_DOMAIN : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_user_switch_signature')) {
	function cmx_user_switch_signature(int $original_user_id, int $switched_user_id): string {
		return \hash_hmac('sha256', $original_user_id . '|' . $switched_user_id, \wp_salt('auth'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_user_switch_set_original_cookie')) {
	function cmx_user_switch_set_original_cookie(int $original_user_id, int $switched_user_id): void {
		$expires = cmx_user_switch_expires_at();
		$domain = cmx_user_switch_cookie_domain();
		$secure = \is_ssl();
		$signature = cmx_user_switch_signature($original_user_id, $switched_user_id);

		foreach (cmx_user_switch_cookie_paths() as $path) {
			\setcookie('cmx_original_user', (string) $original_user_id, $expires, $path, $domain, $secure, true);
			\setcookie('cmx_original_user_sig', $signature, $expires, $path, $domain, $secure, true);
		}

		$_COOKIE['cmx_original_user'] = (string) $original_user_id;
		$_COOKIE['cmx_original_user_sig'] = $signature;

		\update_user_meta($switched_user_id, CMX_USER_SWITCH_ORIGINAL_META, [
			'original_user_id' => $original_user_id,
			'switched_user_id' => $switched_user_id,
			'signature'        => $signature,
			'expires'          => $expires,
		]);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_user_switch_clear_original_cookie')) {
	function cmx_user_switch_clear_original_cookie(): void {
		$domain = cmx_user_switch_cookie_domain();
		$secure = \is_ssl();

		foreach (cmx_user_switch_cookie_paths() as $path) {
			\setcookie('cmx_original_user', '', \time() - \HOUR_IN_SECONDS, $path, $domain, $secure, true);
			\setcookie('cmx_original_user_sig', '', \time() - \HOUR_IN_SECONDS, $path, $domain, $secure, true);
		}

		unset($_COOKIE['cmx_original_user'], $_COOKIE['cmx_original_user_sig']);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_user_switch_clear_original_meta')) {
	function cmx_user_switch_clear_original_meta(int $switched_user_id): void {
		if ($switched_user_id > 0) {
			\delete_user_meta($switched_user_id, CMX_USER_SWITCH_ORIGINAL_META);
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_user_switch_original_user_id_from_meta')) {
	function cmx_user_switch_original_user_id_from_meta(int $current_user_id): int {
		if ($current_user_id <= 0) {
			return 0;
		}

		$context = \get_user_meta($current_user_id, CMX_USER_SWITCH_ORIGINAL_META, true);
		if (!\is_array($context)) {
			return 0;
		}

		$original_user_id = isset($context['original_user_id']) ? (int) $context['original_user_id'] : 0;
		$switched_user_id = isset($context['switched_user_id']) ? (int) $context['switched_user_id'] : 0;
		$expires = isset($context['expires']) ? (int) $context['expires'] : 0;
		$signature = isset($context['signature']) ? (string) $context['signature'] : '';

		if ($expires > 0 && $expires < \time()) {
			cmx_user_switch_clear_original_meta($current_user_id);
			return 0;
		}
		if ($original_user_id <= 0 || $original_user_id === $current_user_id || ($switched_user_id > 0 && $switched_user_id !== $current_user_id) || $signature === '') {
			return 0;
		}

		$expected = cmx_user_switch_signature($original_user_id, $current_user_id);
		return \hash_equals($expected, $signature) ? $original_user_id : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_user_switch_original_user_id')) {
	function cmx_user_switch_original_user_id(): int {
		$current_user_id = (int) \get_current_user_id();
		if ($current_user_id <= 0) {
			return 0;
		}

		$original_user_id = isset($_COOKIE['cmx_original_user']) ? \absint((string) $_COOKIE['cmx_original_user']) : 0;
		$signature = isset($_COOKIE['cmx_original_user_sig']) ? (string) $_COOKIE['cmx_original_user_sig'] : '';
		if ($original_user_id > 0 && $original_user_id !== $current_user_id && $signature !== '') {
			$expected = cmx_user_switch_signature($original_user_id, $current_user_id);
			if (\hash_equals($expected, $signature)) {
				return $original_user_id;
			}
		}

		return cmx_user_switch_original_user_id_from_meta($current_user_id);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_user_switch_is_cloudmeister_switched')) {
	function cmx_user_switch_is_cloudmeister_switched(): bool {
		$current_user = \wp_get_current_user();
		if (!$current_user instanceof \WP_User || !$current_user->exists()) {
			return false;
		}

		if (\strtolower((string) $current_user->user_login) === 'cloudmeister') {
			return false;
		}

		$original_user_id = cmx_user_switch_original_user_id();
		if ($original_user_id <= 0) {
			return false;
		}

		$original_user = \get_user_by('id', $original_user_id);
		return $original_user instanceof \WP_User
			&& $original_user->exists()
			&& \strtolower((string) $original_user->user_login) === 'cloudmeister';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_user_switch_back_url')) {
	function cmx_user_switch_back_url(int $original_user_id): string {
		return \add_query_arg(
			[
				'action'   => 'cmx_switch_back',
				'_wpnonce' => \wp_create_nonce('cmx_switch_back_' . $original_user_id),
			],
			\admin_url('admin-post.php')
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_user_switch_back')) {
	function cmx_user_switch_back(): void {
		$orig_id = cmx_user_switch_original_user_id();
		if ($orig_id <= 0) {
			\wp_die('Ungültige oder abgelaufene Rückkehr-Anfrage.');
		}

		if (!\wp_verify_nonce($_GET['_wpnonce'] ?? '', 'cmx_switch_back_' . $orig_id)) {
			\wp_die('Ungültige Anfrage.');
		}

		$orig_user = \get_user_by('id', $orig_id);
		if (!$orig_user instanceof \WP_User || !$orig_user->exists()) {
			\wp_die('Ursprungsbenutzer nicht gefunden.');
		}

		$switched_user_id = (int) \get_current_user_id();

		\wp_clear_auth_cookie();
		\wp_set_current_user($orig_id);
		\wp_set_auth_cookie($orig_id, true, \is_ssl());
		cmx_user_switch_clear_original_meta($switched_user_id);
		cmx_user_switch_clear_original_cookie();
		\wp_safe_redirect(\admin_url('users.php'));
		exit;
	}
}

add_filter('user_row_actions', function($actions, $user) {
	if (!cmx_user_switch_is_enabled()) {
		unset($actions['cmx_switch_user']);
		return $actions;
	}

	if (current_user_can('manage_options') && get_current_user_id() !== $user->ID) {
		$url = add_query_arg(
			[
				'action'  => 'cmx_switch_user',
				'_wpnonce' => wp_create_nonce('cmx_switch_user_' . $user->ID),
				'user_id' => $user->ID,
			],
			admin_url('users.php')
		);
		$actions['cmx_switch_user'] = '<a href="' . esc_url($url) . '">zu Benutzer wechseln</a>';
	}
	return $actions;
}, 10, 2);

// Benutzerwechsel ausführen
add_action('admin_init', function() {
	if (
		isset($_GET['action'], $_GET['user_id']) &&
		$_GET['action'] === 'cmx_switch_user' &&
		current_user_can('manage_options')
	) {
		if (!cmx_user_switch_is_enabled()) {
			wp_safe_redirect(admin_url('users.php'));
			exit;
		}

		$user_id = absint($_GET['user_id']);
		if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'cmx_switch_user_' . $user_id)) {
			wp_die('Ungültige Anfrage.');
		}

		$user = get_user_by('id', $user_id);
		if (!$user instanceof \WP_User || !$user->exists()) {
			wp_die('Zielbenutzer nicht gefunden.');
		}

		$original_user_id = cmx_user_switch_original_user_id();
		if ($original_user_id <= 0) {
			$original_user_id = (int) get_current_user_id();
		}
		cmx_user_switch_set_original_cookie($original_user_id, $user_id);

		wp_clear_auth_cookie();
		wp_set_current_user($user_id);
		wp_set_auth_cookie($user_id, true, is_ssl());
		wp_safe_redirect(admin_url('index.php'));
		exit;
	}
});

// Rückkehr-Link im Admin-Menü anzeigen (rechts in der Toolbar)
add_action('admin_bar_menu', function($wp_admin_bar) {
	$orig_id = cmx_user_switch_original_user_id();
	if ($orig_id > 0 && current_user_can('read')) {
		$orig_user = get_user_by('id', $orig_id);
		if ($orig_user) {
			$url = cmx_user_switch_back_url($orig_id);
			$wp_admin_bar->add_node([
				'id'     => 'cmx_switch_back',
				'parent' => 'top-secondary',
				'title'  => '' . esc_html($orig_user->user_login),
				'href'   => $url,
			]);
		}
	}
}, 999);

add_action('admin_notices', function(): void {
	$orig_id = cmx_user_switch_original_user_id();
	if ($orig_id <= 0 || !current_user_can('read')) {
		return;
	}

	$orig_user = get_user_by('id', $orig_id);
	if (!$orig_user instanceof \WP_User || !$orig_user->exists()) {
		return;
	}

	$url = cmx_user_switch_back_url($orig_id);

	echo '<div class="notice notice-info cmx-switch-back-notice"><p>'
		. '<strong>Benutzerwechsel aktiv.</strong> '
		. '<a class="button button-primary" href="' . esc_url($url) . '">' . esc_html($orig_user->user_login) . '</a>'
		. '</p></div>';
}, 1);

\add_action('admin_post_cmx_switch_back', __NAMESPACE__ . '\\cmx_user_switch_back', 1);

// Rückkehr zum ursprünglichen Benutzer
add_action('admin_init', function() {
	if (
		isset($_GET['action']) &&
		$_GET['action'] === 'cmx_switch_back'
	) {
		cmx_user_switch_back();
	}
}, 1);
