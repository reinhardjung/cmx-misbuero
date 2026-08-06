<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

if (!\function_exists(__NAMESPACE__ . '\\cmx_logout_redirect_url')) {
	function cmx_logout_redirect_url(): string {
		return (string) \home_url('/');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_alp_login_path')) {
	function cmx_alp_login_path(): string {
		return 'alp.php';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_alp_login_url')) {
	function cmx_alp_login_url(array $args = []): string {
		$url = (string) \home_url('/' . cmx_alp_login_path());
		return $args !== [] ? (string) \add_query_arg($args, $url) : $url;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_alp_rewrite_login_url')) {
	function cmx_alp_rewrite_login_url(string $url): string {
		$parts = \wp_parse_url($url);
		$path = isset($parts['path']) ? (string) $parts['path'] : '';
		if ($path === '' || \basename($path) !== 'wp-login.php') {
			return $url;
		}

		$query = isset($parts['query']) && (string) $parts['query'] !== '' ? '?' . (string) $parts['query'] : '';
		$fragment = isset($parts['fragment']) && (string) $parts['fragment'] !== '' ? '#' . (string) $parts['fragment'] : '';

		return cmx_alp_login_url() . $query . $fragment;
	}
}

\add_filter('site_url', static function ($url, $path = '', $scheme = null, $blog_id = null): string {
	$path = \ltrim((string) $path, '/');
	if ($path !== '' && \str_starts_with($path, 'wp-login.php')) {
		return cmx_alp_rewrite_login_url((string) $url);
	}
	return (string) $url;
}, 20, 4);

\add_filter('network_site_url', static function ($url, $path = '', $scheme = null): string {
	$path = \ltrim((string) $path, '/');
	if ($path !== '' && \str_starts_with($path, 'wp-login.php')) {
		return cmx_alp_rewrite_login_url((string) $url);
	}
	return (string) $url;
}, 20, 3);

\add_filter('login_url', static function ($login_url, $redirect = '', $force_reauth = false): string {
	$args = [];
	if ((string) $redirect !== '') {
		$args['redirect_to'] = (string) $redirect;
	}
	if (!empty($force_reauth)) {
		$args['reauth'] = '1';
	}
	return cmx_alp_login_url($args);
}, 20, 3);

if (!\function_exists(__NAMESPACE__ . '\\cmx_alp_login_entrypoint_content')) {
	function cmx_alp_login_entrypoint_content(): string {
		return "<?php\n"
			. "define('CMX_ALP_LOGIN', true);\n"
			. "require __DIR__ . '/wp-login.php';\n";
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_alp_login_ensure_entrypoint')) {
	function cmx_alp_login_ensure_entrypoint(): void {
		if (!\defined('ABSPATH')) {
			return;
		}

		$file = \rtrim((string) \constant('ABSPATH'), '/\\') . '/' . cmx_alp_login_path();
		$content = cmx_alp_login_entrypoint_content();
		if (\is_file($file)) {
			$current_content = (string) \file_get_contents($file);
			if ($current_content === $content) {
				return;
			}
			if (\strpos($current_content, "CMX_ALP_LOGIN") === false) {
				return;
			}
		}
		if (!\is_writable(\dirname($file))) {
			return;
		}

		\file_put_contents($file, $content, LOCK_EX);
	}
}

\add_action('init', __NAMESPACE__ . '\\cmx_alp_login_ensure_entrypoint', 1);

if (!\function_exists(__NAMESPACE__ . '\\cmx_request_path')) {
	function cmx_request_path(): string {
		$uri = isset($_SERVER['REQUEST_URI']) && \is_string($_SERVER['REQUEST_URI'])
			? (string) $_SERVER['REQUEST_URI']
			: '/';
		$path = (string) \wp_parse_url($uri, \PHP_URL_PATH);
		return $path !== '' ? $path : '/';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_is_alp_login_request')) {
	function cmx_is_alp_login_request(): bool {
		return \basename(cmx_request_path()) === cmx_alp_login_path();
	}
}

\add_action('template_redirect', static function (): void {
	if (!cmx_is_alp_login_request()) {
		return;
	}

	require_once \rtrim((string) \constant('ABSPATH'), '/\\') . '/wp-login.php';
	exit;
}, 1);

\add_action('login_init', static function (): void {
	if (cmx_is_alp_login_request() || \defined('CMX_ALP_LOGIN')) {
		return;
	}

	\wp_safe_redirect(\home_url('/'));
	exit;
}, 1);

\add_action('init', static function (): void {
	if (\is_user_logged_in() || \wp_doing_ajax() || \wp_doing_cron()) {
		return;
	}

	$path = \rtrim(cmx_request_path(), '/');
	$admin_path = (string) \wp_parse_url(\admin_url('/'), PHP_URL_PATH);
	$admin_path = \rtrim($admin_path !== '' ? $admin_path : '/wp-admin', '/');
	if ($path === $admin_path || \str_starts_with($path . '/', $admin_path . '/')) {
		\wp_safe_redirect(\home_url('/'));
		exit;
	}
}, 2);

\add_filter('auth_cookie_expiration', static function ($length, $user_id, $remember): int {
	return 3655 * \DAY_IN_SECONDS;
}, 20, 3);

\add_action('wp_login', static function ($user_login, $user): void {
	if (!$user instanceof \WP_User || !$user->exists()) {
		return;
	}

	\wp_set_auth_cookie((int) $user->ID, true, \is_ssl());
}, 999, 2);

if (!\function_exists(__NAMESPACE__ . '\\cmx_magic_login_instance_user')) {
	function cmx_magic_login_instance_user(): ?\WP_User {
		$subdomain = cmx_initial_password_host_subdomain();
		if ($subdomain !== '') {
			$user = \get_user_by('login', $subdomain);
			if ($user instanceof \WP_User && $user->exists()) {
				return $user;
			}
		}

		return null;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_magic_login_request_url')) {
	function cmx_magic_login_request_url(bool $cache_safe = false): string {
		$url = \add_query_arg('cmx_magic_login', 'request', \home_url('/'));
		if ($cache_safe) {
			return \add_query_arg('cmx_magic_public', '1', $url);
		}

		return \wp_nonce_url($url, 'cmx_magic_login_request', 'cmx_magic_nonce');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_magic_login_token_hash')) {
	function cmx_magic_login_token_hash(string $token): string {
		return \hash_hmac('sha256', $token, \wp_salt('auth'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_magic_login_redirect_status')) {
	function cmx_magic_login_redirect_status(string $status): void {
		$status = \sanitize_key($status);
		if ($status === 'expired') {
			\wp_safe_redirect(\home_url('/'));
			exit;
		}

		\wp_safe_redirect(\add_query_arg('cmx_magic_login_status', $status, \home_url('/')) . '#anmelden');
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_magic_login_status_message')) {
	function cmx_magic_login_status_message(): string {
		$status = isset($_GET['cmx_magic_login_status'])
			? \sanitize_key((string) \wp_unslash($_GET['cmx_magic_login_status']))
			: '';

		return cmx_magic_login_status_text($status);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_magic_login_status_text')) {
	function cmx_magic_login_status_text(string $status): string {
		return match ($status) {
			'sent'    => 'prüfen Sie ihr E-Mail Postfach',
			'wait'    => 'prüfen Sie ihr E-Mail Postfach',
			'expired' => 'Der Anmelde-Link ist abgelaufen. Bitte fordere unten einen neuen Link an.',
			'invalid' => 'Der Anmelde-Link ist ungültig. Bitte fordere unten einen neuen Link an.',
			'error'   => 'Der Anmelde-Link konnte nicht gesendet werden. Bitte prüfe die hinterlegte E-Mail-Adresse.',
			default   => '',
		};
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_magic_login_is_async_request')) {
	function cmx_magic_login_is_async_request(): bool {
		$async = isset($_GET['cmx_magic_async']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_magic_async'])) : '';
		if ($async === '1') {
			return true;
		}

		$requested_with = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? \strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) : '';
		return $requested_with === 'xmlhttprequest';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_magic_login_is_public_fetch_request')) {
	function cmx_magic_login_is_public_fetch_request(): bool {
		$public = isset($_GET['cmx_magic_public']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_magic_public'])) : '';
		if ($public !== '1') {
			return false;
		}

		$requested_with = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? \strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) : '';
		return $requested_with === 'xmlhttprequest';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_magic_login_send_status')) {
	function cmx_magic_login_send_status(string $status, int $http_status = 200, array $extra = []): void {
		if (cmx_magic_login_is_async_request()) {
			$payload = \array_merge([
				'status'  => \sanitize_key($status),
				'message' => cmx_magic_login_status_text($status),
			], $extra);
			if ($http_status >= 400) {
				\wp_send_json_error($payload, $http_status);
			}
			\wp_send_json_success($payload, $http_status);
		}

		cmx_magic_login_redirect_status($status);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_magic_login_finish_async_response')) {
	function cmx_magic_login_finish_async_response(array $payload, int $http_status = 200): void {
		\status_header($http_status);
		\nocache_headers();
		\header('Content-Type: application/json; charset=' . \get_option('blog_charset'));
		echo (string) \wp_json_encode([
			'success' => $http_status < 400,
			'data'    => $payload,
		]);

		if (\function_exists('fastcgi_finish_request')) {
			\fastcgi_finish_request();
			return;
		}

		while (\ob_get_level() > 0) {
			@\ob_end_flush();
		}
		@\flush();
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_magic_login_mask_email')) {
	function cmx_magic_login_mask_email(string $email): string {
		$email = \sanitize_email($email);
		if (!\is_email($email) || !\str_contains($email, '@')) {
			return '';
		}

		[$local, $domain] = \explode('@', $email, 2);
		$local = (string) $local;
		$domain = (string) $domain;
		$visible = \function_exists('mb_substr') ? (string) \mb_substr($local, 0, 2) : (string) \substr($local, 0, 2);

		return $visible . '***@' . $domain;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_magic_login_instance_smtp_configured')) {
	function cmx_magic_login_instance_smtp_configured(): bool {
		$options = (array) \get_option('cmx_einstellungen', []);
		$host = \trim((string) ($options['smtp_host'] ?? ''));
		$user = \sanitize_email((string) ($options['email_address'] ?? ''));
		$password = (string) ($options['email_password'] ?? '');
		if ($host !== '' && \is_email($user) && $password !== '') {
			return true;
		}

		return false;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_magic_login_wp_mail')) {
	function cmx_magic_login_wp_mail(string $to, string $subject, string $message, array $headers): bool {
		$recipient = \sanitize_email($to);
		if (!\is_email($recipient)) {
			\error_log('[CMX Magic Login] wp_mail skipped: invalid recipient.');
			return false;
		}
		if (!cmx_magic_login_instance_smtp_configured()) {
			\error_log('[CMX Magic Login] wp_mail skipped: wp-config SMTP settings are missing or incomplete.');
			return false;
		}

		$wp_mail_failed_message = '';
		$wp_mail_failed_listener = static function ($error) use (&$wp_mail_failed_message): void {
			if ($error instanceof \WP_Error) {
				$wp_mail_failed_message = $error->get_error_message();
			}
		};

		$had_context = \array_key_exists('cmx_mail_context', $GLOBALS);
		$previous_context = $had_context ? $GLOBALS['cmx_mail_context'] : null;
		$embedded_logo_listener = static function ($phpmailer): void {
			if (\function_exists(__NAMESPACE__ . '\\cmx_email_embed_self_logo_for_phpmailer')) {
				cmx_email_embed_self_logo_for_phpmailer($phpmailer);
			}
		};
		$GLOBALS['cmx_mail_context'] = 'magic_login';

		\add_action('wp_mail_failed', $wp_mail_failed_listener, 10, 1);
		\add_action('phpmailer_init', $embedded_logo_listener, 100, 1);
		try {
			$sent = (bool) \wp_mail($recipient, $subject, $message, $headers);
		} finally {
			\remove_action('wp_mail_failed', $wp_mail_failed_listener, 10);
			\remove_action('phpmailer_init', $embedded_logo_listener, 100);
			if ($had_context) {
				$GLOBALS['cmx_mail_context'] = $previous_context;
			} else {
				unset($GLOBALS['cmx_mail_context']);
			}
		}

		if ($sent) {
			\error_log('[CMX Magic Login] wp_mail accepted message for ' . $recipient . '.');
		} elseif ($wp_mail_failed_message !== '') {
			\error_log('[CMX Magic Login] wp_mail failed: ' . $wp_mail_failed_message);
		} else {
			\error_log('[CMX Magic Login] wp_mail failed without WordPress error message.');
		}

		return $sent;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_magic_login_send_mail')) {
	function cmx_magic_login_send_mail(\WP_User $user, string $url): bool {
		$site_name = \wp_specialchars_decode((string) \get_option('blogname'), ENT_QUOTES);
		$display_name = \trim((string) $user->display_name);
		if ($display_name === '') {
			$display_name = (string) $user->user_login;
		}

		$body_html  = '<p style="margin:0 0 14px;">Sali ' . \esc_html($display_name) . ',</p>';
		$body_html .= '<p style="margin:0 0 14px;">hier ist Dein Anmelde-Link für <strong>' . \esc_html($site_name) . '</strong>.</p>';
		$body_html .= '<p style="margin:0 0 14px;">Der Link ist 15 Minuten gültig und kann nur einmal verwendet werden.</p>';
		$body_html .= '<p style="margin:0 0 14px;">Falls der Button nicht funktioniert, öffne den Anmelde-Link direkt im Browser:<br><a href="' . \esc_url($url) . '" style="color:#0b57d0;text-decoration:underline;">Anmelde-Link im Browser öffnen</a></p>';
		$body_html .= '<p style="margin:0;">Wenn Du diese Anmeldung nicht gestartet hast, kannst Du diese E-Mail ignorieren.</p>';

		$message = \function_exists(__NAMESPACE__ . '\\cmx_passwort_mails_build_html')
			? cmx_passwort_mails_build_html('Anmelden', $body_html, [
				'url'   => $url,
				'label' => 'Jetzt anmelden',
			])
			: $body_html;
		$headers = \function_exists(__NAMESPACE__ . '\\cmx_passwort_mails_with_html_header')
			? cmx_passwort_mails_with_html_header([])
			: ['Content-Type: text/html; charset=UTF-8'];
		$subject = '[' . $site_name . '] Anmelden';

		return cmx_magic_login_wp_mail((string) $user->user_email, $subject, $message, $headers);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_magic_login_handle_request')) {
	function cmx_magic_login_handle_request(): void {
		if (\is_user_logged_in()) {
			if (cmx_magic_login_is_async_request()) {
				\wp_send_json_success([
					'status'   => 'logged_in',
					'message'  => '',
					'redirect' => \admin_url('/'),
				]);
			}
			\wp_safe_redirect(\admin_url('/'));
			exit;
		}

		if (!cmx_magic_login_is_public_fetch_request()) {
			$nonce = isset($_GET['cmx_magic_nonce']) ? (string) \wp_unslash($_GET['cmx_magic_nonce']) : '';
			if (!\wp_verify_nonce($nonce, 'cmx_magic_login_request')) {
				cmx_magic_login_send_status('invalid', 403);
			}
		}

		$user = cmx_magic_login_instance_user();
		if (!$user instanceof \WP_User || !$user->exists() || !\is_email((string) $user->user_email)) {
			$subdomain = \function_exists(__NAMESPACE__ . '\\cmx_initial_password_host_subdomain')
				? cmx_initial_password_host_subdomain()
				: '';
			\error_log('[CMX Magic Login] instance user missing or email invalid for username "' . $subdomain . '".');
			cmx_magic_login_send_status('error', 500, [
				'message' => 'Der WordPress-Benutzer „' . $subdomain . '“ fehlt oder besitzt keine gültige E-Mail-Adresse.',
			]);
		}

		if (!cmx_magic_login_instance_smtp_configured()) {
			\error_log('[CMX Magic Login] SMTP settings are missing or incomplete in Einstellungen > E-Mails.');
			cmx_magic_login_send_status('error', 503, [
				'message' => 'Bitte hinterlege unter Einstellungen → E-Mails die E-Mail-Adresse, das Kennwort und den SMTP-Host.',
			]);
		}

		$rate_limit_key = 'cmx_magic_login_sent_' . (int) $user->ID;
		if (\get_transient($rate_limit_key)) {
			cmx_magic_login_send_status('wait', 200, [
				'recipient_hint' => cmx_magic_login_mask_email((string) $user->user_email),
			]);
		}

		$token = \wp_generate_password(40, false, false);
		$expires = \time() + 15 * \MINUTE_IN_SECONDS;
		$url = \add_query_arg([
			'cmx_magic_login' => 'confirm',
			'uid'             => (int) $user->ID,
			'token'           => $token,
		], \home_url('/'));

		\update_user_meta((int) $user->ID, '_cmx_magic_login_hash', cmx_magic_login_token_hash($token));
		\update_user_meta((int) $user->ID, '_cmx_magic_login_expires', $expires);

		$recipient_hint = cmx_magic_login_mask_email((string) $user->user_email);
		if (cmx_magic_login_is_async_request()) {
			if (!cmx_magic_login_send_mail($user, $url)) {
				\delete_user_meta((int) $user->ID, '_cmx_magic_login_hash');
				\delete_user_meta((int) $user->ID, '_cmx_magic_login_expires');
				\delete_transient($rate_limit_key);
				cmx_magic_login_send_status('error', 502, [
					'message' => 'Der Mailserver hat den Magic-Link nicht angenommen. Bitte prüfe SMTP-Host, Benutzer, Passwort, Port und Verschlüsselung.',
				]);
			}

			\set_transient($rate_limit_key, '1', \MINUTE_IN_SECONDS);
			cmx_magic_login_send_status('sent', 200, [
				'recipient_hint' => $recipient_hint,
			]);
		}

		if (!cmx_magic_login_send_mail($user, $url)) {
			\delete_user_meta((int) $user->ID, '_cmx_magic_login_hash');
			\delete_user_meta((int) $user->ID, '_cmx_magic_login_expires');
			cmx_magic_login_send_status('error', 502, [
				'message' => 'Der Mailserver hat den Magic-Link nicht angenommen. Bitte prüfe SMTP-Host, Benutzer, Passwort, Port und Verschlüsselung.',
			]);
		}

		\set_transient($rate_limit_key, '1', \MINUTE_IN_SECONDS);
		cmx_magic_login_send_status('sent', 200, [
			'recipient_hint' => $recipient_hint,
		]);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_magic_login_handle_confirm')) {
	function cmx_magic_login_handle_confirm(): void {
		$user_id = isset($_GET['uid']) ? (int) $_GET['uid'] : 0;
		$token = isset($_GET['token']) ? (string) \wp_unslash($_GET['token']) : '';
		if ($user_id <= 0 || $token === '') {
			cmx_magic_login_redirect_status('invalid');
		}

		$user = \get_user_by('id', $user_id);
		$instance_user = cmx_magic_login_instance_user();
		if (!$user instanceof \WP_User || !$user->exists() || !$instance_user instanceof \WP_User || (int) $instance_user->ID !== (int) $user->ID) {
			cmx_magic_login_redirect_status('invalid');
		}

		$expires = (int) \get_user_meta($user_id, '_cmx_magic_login_expires', true);
		$stored_hash = (string) \get_user_meta($user_id, '_cmx_magic_login_hash', true);
		$token_hash = cmx_magic_login_token_hash($token);

		if ($expires <= \time()) {
			\delete_user_meta($user_id, '_cmx_magic_login_hash');
			\delete_user_meta($user_id, '_cmx_magic_login_expires');
			cmx_magic_login_redirect_status('expired');
		}
		if ($stored_hash === '' || !\hash_equals($stored_hash, $token_hash)) {
			cmx_magic_login_redirect_status('invalid');
		}

		\delete_user_meta($user_id, '_cmx_magic_login_hash');
		\delete_user_meta($user_id, '_cmx_magic_login_expires');

		\wp_set_current_user($user_id);
		\wp_set_auth_cookie($user_id, true, \is_ssl());
		\do_action('wp_login', (string) $user->user_login, $user);

		\wp_safe_redirect(\admin_url('/'));
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_magic_login_maybe_handle_request')) {
	function cmx_magic_login_maybe_handle_request(): void {
		$action = isset($_GET['cmx_magic_login'])
			? \sanitize_key((string) \wp_unslash($_GET['cmx_magic_login']))
			: '';
		if ($action === 'request') {
			cmx_magic_login_handle_request();
		}
		if ($action === 'confirm') {
			cmx_magic_login_handle_confirm();
		}
	}
}

\add_action('init', __NAMESPACE__ . '\\cmx_magic_login_maybe_handle_request', -1000);
\add_action('template_redirect', __NAMESPACE__ . '\\cmx_magic_login_maybe_handle_request', 5);

if (!\function_exists(__NAMESPACE__ . '\\cmx_redirect_parameter_requests_to_home')) {
	function cmx_redirect_parameter_requests_to_home(): void {
		if (\is_admin() || \wp_doing_ajax() || \wp_doing_cron()) {
			return;
		}
		if (empty($_GET) || !\in_array((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'HEAD'], true)) {
			return;
		}
		if (cmx_is_alp_login_request()) {
			return;
		}

		$magic_action = isset($_GET['cmx_magic_login'])
			? \sanitize_key((string) \wp_unslash($_GET['cmx_magic_login']))
			: '';
		if (\in_array($magic_action, ['request', 'confirm'], true)) {
			return;
		}
		$query_keys = \array_map('strval', \array_keys((array) $_GET));
		foreach ($query_keys as $query_key) {
			$key = \sanitize_key($query_key);
				if ($key === ''
					|| $key === 's'
					|| \str_starts_with($key, 'cmx')
					|| \str_starts_with($key, '_wp')
					|| \in_array($key, ['action', 'token', 'nonce', 'key', 'post_id', 'post', 'p', 'page_id', 'attachment_id', 'preview', 'preview_id', 'preview_nonce', 'beleg', 'kontakt', 'carent', 'carent_vertrag', 'download', 'file', 'path', 'id', 'pdf', 'vertrag', 'mietvertrag', 'number', 'account', 'dnid', 'callid'], true)
				) {
				return;
			}
		}
		$request_path = '/' . \trim((string) \wp_parse_url(cmx_request_path(), \PHP_URL_PATH), '/');
		$home_path = '/' . \trim((string) \wp_parse_url(\home_url('/'), \PHP_URL_PATH), '/');
		if ($request_path === '/') {
			$request_path = '/';
		}
		if ($home_path === '/') {
			$home_path = '/';
		}
		if ($request_path !== $home_path) {
			return;
		}

		\wp_safe_redirect(\home_url('/'));
		exit;
	}
}

\add_action('init', __NAMESPACE__ . '\\cmx_redirect_parameter_requests_to_home', -900);

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
		$host = \trim((string) ($options['smtp_host'] ?? ''));
		$user = \sanitize_email((string) ($options['email_address'] ?? ''));
		$password = (string) ($options['email_password'] ?? '');

		return $host !== '' && \is_email($user) && $password !== '';
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
		cmx_alp_login_path() . '?action=rp&key=' . \rawurlencode((string) $key) . '&login=' . \rawurlencode((string) $user->user_login) . '&cmx_initial=1',
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
