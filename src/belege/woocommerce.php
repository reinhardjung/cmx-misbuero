<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_option_name')) {
	function cmx_woocommerce_option_name(): string {
		return \defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN') ? CMX_SETTINGS_MAIN : 'cmx_einstellungen';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_settings_defaults')) {
	function cmx_woocommerce_settings_defaults(): array {
		return [
			'misbuero_webhook_secret'                => '',
			'misbuero_webhook_secret_previous'       => '',
			'misbuero_webhook_secret_previous_until' => '0',
			'misbuero_webhook_token'                 => '',
			'misbuero_auto_mail'                     => '0',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_get_settings')) {
	function cmx_woocommerce_get_settings(): array {
		$options = \get_option(cmx_woocommerce_option_name(), []);
		$options = \is_array($options) ? $options : [];

		return \wp_parse_args($options, cmx_woocommerce_settings_defaults());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_get_setting')) {
	function cmx_woocommerce_get_setting(string $key, $default = '') {
		$settings = cmx_woocommerce_get_settings();

		if (\array_key_exists($key, $settings)) {
			return $settings[$key];
		}

		return $default;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_generate_webhook_secret')) {
	function cmx_woocommerce_generate_webhook_secret(): string {
		try {
			$random = \bin2hex(\random_bytes(24));
		} catch (\Throwable $exception) {
			$random = \strtolower(\wp_generate_password(48, false, false));
		}

		return 'wcwhsec_' . $random;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_generate_webhook_token')) {
	function cmx_woocommerce_generate_webhook_token(): string {
		try {
			$random = \bin2hex(\random_bytes(24));
		} catch (\Throwable $exception) {
			$random = \strtolower(\wp_generate_password(48, false, false));
		}

		return 'wcwhtok_' . $random;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_webhook_secret_rotation_window')) {
	function cmx_woocommerce_webhook_secret_rotation_window(): int {
		return 15 * MINUTE_IN_SECONDS;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_is_placeholder_webhook_secret')) {
	function cmx_woocommerce_is_placeholder_webhook_secret(string $secret): bool {
		return \trim($secret) === 'wcwhsec_demo_7f4a9c1d2e6b8f3a5c7d9e1f';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_needs_webhook_secret')) {
	function cmx_woocommerce_needs_webhook_secret(string $secret): bool {
		$secret = \trim($secret);

		return $secret === '' || cmx_woocommerce_is_placeholder_webhook_secret($secret);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_ensure_webhook_secret')) {
	function cmx_woocommerce_ensure_webhook_secret(): string {
		$secret = (string) cmx_woocommerce_get_setting('misbuero_webhook_secret', '');
		if (!cmx_woocommerce_needs_webhook_secret($secret)) {
			return \trim($secret);
		}

		$options = \get_option(cmx_woocommerce_option_name(), []);
		$options = \is_array($options) ? $options : [];
		$options['misbuero_webhook_secret'] = cmx_woocommerce_generate_webhook_secret();

		\update_option(cmx_woocommerce_option_name(), $options);

		return (string) $options['misbuero_webhook_secret'];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_ensure_webhook_token')) {
	function cmx_woocommerce_ensure_webhook_token(): string {
		$token = \trim((string) cmx_woocommerce_get_setting('misbuero_webhook_token', ''));
		if ($token !== '') {
			return $token;
		}

		$options = \get_option(cmx_woocommerce_option_name(), []);
		$options = \is_array($options) ? $options : [];
		$options['misbuero_webhook_token'] = cmx_woocommerce_generate_webhook_token();

		\update_option(cmx_woocommerce_option_name(), $options);

		return (string) $options['misbuero_webhook_token'];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_webhook_previous_secret_expires_at')) {
	function cmx_woocommerce_webhook_previous_secret_expires_at(?array $settings = null): int {
		$settings = \is_array($settings) ? \wp_parse_args($settings, cmx_woocommerce_settings_defaults()) : cmx_woocommerce_get_settings();
		$secret = \trim((string) ($settings['misbuero_webhook_secret_previous'] ?? ''));
		$until = \absint($settings['misbuero_webhook_secret_previous_until'] ?? 0);

		if ($secret === '' || $until <= \time()) {
			return 0;
		}

		return $until;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_has_previous_webhook_secret')) {
	function cmx_woocommerce_has_previous_webhook_secret(?array $settings = null): bool {
		return cmx_woocommerce_webhook_previous_secret_expires_at($settings) > 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_normalize_checkbox')) {
	function cmx_woocommerce_normalize_checkbox($value): string {
		if (\is_string($value)) {
			$value = \strtolower(\trim($value));
		}

		return (!empty($value) && !\in_array($value, ['0', 'false', 'off', 'no'], true)) ? '1' : '0';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_sanitize_settings')) {
	function cmx_woocommerce_sanitize_settings(array $settings, array $existing_settings = []): array {
		$defaults = cmx_woocommerce_settings_defaults();
		$settings = \wp_parse_args($settings, $defaults);
		$existing_settings = \wp_parse_args($existing_settings, $defaults);

		$secret = \sanitize_text_field((string) $settings['misbuero_webhook_secret']);
		$old_secret = \trim(\sanitize_text_field((string) $existing_settings['misbuero_webhook_secret']));
		if (cmx_woocommerce_needs_webhook_secret($secret)) {
			$secret = cmx_woocommerce_needs_webhook_secret($old_secret)
				? cmx_woocommerce_generate_webhook_secret()
				: $old_secret;
		}

		$secret = \trim($secret);
		$previous_secret = \trim(\sanitize_text_field((string) $existing_settings['misbuero_webhook_secret_previous']));
		$previous_until = \absint($existing_settings['misbuero_webhook_secret_previous_until'] ?? 0);
		$rotate_requested = cmx_woocommerce_normalize_checkbox($settings['misbuero_rotate_webhook_secret'] ?? '0') === '1';

		if ($rotate_requested) {
			$previous_secret = $old_secret;
			$previous_until = $previous_secret === ''
				? 0
				: (\time() + cmx_woocommerce_webhook_secret_rotation_window());
			$secret = cmx_woocommerce_generate_webhook_secret();
		} elseif ($secret !== $old_secret || $previous_until <= \time()) {
			$previous_secret = '';
			$previous_until = 0;
		}

		if ($previous_secret === '' || $previous_secret === $secret) {
			$previous_secret = '';
			$previous_until = 0;
		}

		$token = \trim(\sanitize_text_field((string) ($settings['misbuero_webhook_token'] ?? ($existing_settings['misbuero_webhook_token'] ?? ''))));
		if ($token === '') {
			$token = \trim((string) ($existing_settings['misbuero_webhook_token'] ?? ''));
		}
		if ($token === '') {
			$token = cmx_woocommerce_generate_webhook_token();
		}

		return [
			'misbuero_webhook_secret'                => $secret,
			'misbuero_webhook_secret_previous'       => $previous_secret,
			'misbuero_webhook_secret_previous_until' => (string) $previous_until,
			'misbuero_webhook_token'                 => $token,
			'misbuero_auto_mail'                     => cmx_woocommerce_normalize_checkbox($settings['misbuero_auto_mail']),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_webhook_route')) {
	function cmx_woocommerce_webhook_route(): string {
		return 'cmx-misbuero/v1/woocommerce/webhook';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_webhook_beleg_meta_key')) {
	function cmx_woocommerce_webhook_beleg_meta_key(): string {
		return 'cmx_woo_webhook';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_is_webhook_beleg')) {
	function cmx_woocommerce_is_webhook_beleg(int $post_id): bool {
		if ($post_id <= 0) {
			return false;
		}

		return (string) \get_post_meta($post_id, cmx_woocommerce_webhook_beleg_meta_key(), true) === '1';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_webhook_url')) {
	function cmx_woocommerce_webhook_url(): string {
		return \rest_url(cmx_woocommerce_webhook_route());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_webhook_delivery_url')) {
	function cmx_woocommerce_webhook_delivery_url(): string {
		$token = cmx_woocommerce_ensure_webhook_token();
		if ($token === '') {
			return cmx_woocommerce_webhook_url();
		}

		return (string) \add_query_arg('cmx_wc_token', $token, cmx_woocommerce_webhook_url());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_allows_internal_write')) {
	function cmx_woocommerce_allows_internal_write(): bool {
		return !empty($GLOBALS['cmx_woocommerce_internal_import']);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_request_float')) {
	function cmx_woocommerce_request_float($value): float {
		if (\is_numeric($value)) {
			return (float) $value;
		}

		return (float) \str_replace(',', '.', (string) $value);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_header')) {
	function cmx_woocommerce_header(\WP_REST_Request $request, string $name): string {
		$value = (string) $request->get_header($name);
		if ($value !== '') {
			return \trim($value);
		}

		$server_key = 'HTTP_' . \strtoupper(\str_replace('-', '_', $name));
		$fallbacks = [
			$_SERVER[$server_key] ?? '',
			$_SERVER['REDIRECT_' . $server_key] ?? '',
		];
		foreach ($fallbacks as $fallback) {
			$fallback = (string) $fallback;
			if ($fallback !== '') {
				return \trim($fallback);
			}
		}

		if (\function_exists('apache_request_headers')) {
			$headers = (array) \apache_request_headers();
			foreach ($headers as $header_name => $header_value) {
				if (\strcasecmp((string) $header_name, $name) === 0) {
					return \trim((string) $header_value);
				}
			}
		}

		return \trim($value);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_verify_signature')) {
	function cmx_woocommerce_verify_signature(string $payload, string $provided_signature, string $secret): bool {
		if ($payload === '' || $provided_signature === '' || $secret === '') {
			return false;
		}

		$expected = \base64_encode(\hash_hmac('sha256', $payload, $secret, true));

		return \hash_equals($expected, $provided_signature);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_active_webhook_secrets')) {
	function cmx_woocommerce_active_webhook_secrets(): array {
		$settings = cmx_woocommerce_get_settings();
		$secrets = [];
		$current = \trim((string) ($settings['misbuero_webhook_secret'] ?? ''));
		if ($current !== '') {
			$secrets[] = $current;
		}

		if (cmx_woocommerce_has_previous_webhook_secret($settings)) {
			$previous = \trim((string) ($settings['misbuero_webhook_secret_previous'] ?? ''));
			if ($previous !== '' && !\in_array($previous, $secrets, true)) {
				$secrets[] = $previous;
			}
		}

		return $secrets;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_validate_webhook_token')) {
	function cmx_woocommerce_validate_webhook_token(string $provided_token): bool {
		$provided_token = \trim($provided_token);
		if ($provided_token === '') {
			return false;
		}

		$stored_token = \trim((string) cmx_woocommerce_get_setting('misbuero_webhook_token', ''));
		if ($stored_token === '') {
			$stored_token = cmx_woocommerce_ensure_webhook_token();
		}

		return $stored_token !== '' && \hash_equals($stored_token, $provided_token);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_request_webhook_token')) {
	function cmx_woocommerce_request_webhook_token(\WP_REST_Request $request): string {
		$query = (array) $request->get_query_params();

		return \trim((string) ($query['cmx_wc_token'] ?? ''));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_verify_signature_against_active_secrets')) {
	function cmx_woocommerce_verify_signature_against_active_secrets(string $payload, string $provided_signature): bool {
		foreach (cmx_woocommerce_active_webhook_secrets() as $secret) {
			if (cmx_woocommerce_verify_signature($payload, $provided_signature, $secret)) {
				return true;
			}
		}

		return false;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_webhook_notice_option_name')) {
	function cmx_woocommerce_webhook_notice_option_name(): string {
		return 'cmx_woocommerce_last_webhook_notice';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_request_header_names')) {
	function cmx_woocommerce_request_header_names(\WP_REST_Request $request): array {
		$names = [];

		foreach ((array) $request->get_headers() as $header_name => $values) {
			$normalized = \strtolower(\trim((string) $header_name));
			if ($normalized !== '') {
				$names[] = $normalized;
			}
		}

		foreach ((array) $_SERVER as $server_key => $server_value) {
			if (!\is_string($server_key)) {
				continue;
			}
			if (\strpos($server_key, 'HTTP_') !== 0 && \strpos($server_key, 'REDIRECT_HTTP_') !== 0) {
				continue;
			}

			$header_key = $server_key;
			if (\strpos($header_key, 'REDIRECT_HTTP_') === 0) {
				$header_key = (string) \substr($header_key, 9);
			}
			if (\strpos($header_key, 'HTTP_') !== 0) {
				continue;
			}

			$normalized = \strtolower(\str_replace('_', '-', (string) \substr($header_key, 5)));
			if ($normalized !== '') {
				$names[] = $normalized;
			}
		}

		if (\function_exists('apache_request_headers')) {
			foreach ((array) \apache_request_headers() as $header_name => $header_value) {
				$normalized = \strtolower(\trim((string) $header_name));
				if ($normalized !== '') {
					$names[] = $normalized;
				}
			}
		}

		$names = \array_values(\array_unique(\array_filter($names, static function ($name): bool {
			return \is_string($name) && $name !== '';
		})));
		\sort($names, \SORT_STRING);

		return $names;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_human_webhook_message')) {
	function cmx_woocommerce_human_webhook_message(string $code): string {
		$map = [
			'webhook_secret_missing'  => 'Es ist kein Secret Key in Mis BuerO gespeichert.',
			'missing_signature_header'=> 'Der Header X-WC-Webhook-Signature fehlt beim Request.',
			'invalid_signature'       => 'Die Signatur passt nicht zum gespeicherten Secret Key.',
			'empty_payload'           => 'WooCommerce hat einen leeren Request-Body gesendet.',
			'invalid_json'            => 'Der Request-Body ist kein gueltiges JSON.',
			'ignored_non_order_topic' => 'Der Webhook wurde empfangen, aber das Thema ist keine Bestellung.',
			'beleg_insert_failed'     => 'Der Beleg konnte nicht angelegt werden.',
			'missing_order_id'        => 'Im Webhook fehlt eine Bestell-ID.',
		];

		return (string) ($map[$code] ?? $code);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_store_webhook_notice')) {
	function cmx_woocommerce_store_webhook_notice(array $data): void {
		$debug = \is_array($data['debug'] ?? null) ? $data['debug'] : [];
		$payload = [
			'code'        => \sanitize_key((string) ($data['code'] ?? '')),
			'message'     => \sanitize_text_field((string) ($data['message'] ?? '')),
			'hint'        => \sanitize_text_field((string) ($data['hint'] ?? '')),
			'http_status' => \absint($data['http_status'] ?? 0),
			'captured_at' => \absint($data['captured_at'] ?? \time()),
			'debug'       => [
				'topic'               => \sanitize_text_field((string) ($debug['topic'] ?? '')),
				'body_length'         => \absint($debug['body_length'] ?? 0),
				'signature_present'   => !empty($debug['signature_present']) ? '1' : '0',
				'signature_length'    => \absint($debug['signature_length'] ?? 0),
				'delivery_id'         => \sanitize_text_field((string) ($debug['delivery_id'] ?? '')),
				'webhook_id'          => \sanitize_text_field((string) ($debug['webhook_id'] ?? '')),
				'user_agent'          => \sanitize_text_field((string) ($debug['user_agent'] ?? '')),
				'header_names'        => \sanitize_text_field((string) ($debug['header_names'] ?? '')),
				'token_present'       => !empty($debug['token_present']) ? '1' : '0',
				'auth_method'         => \sanitize_text_field((string) ($debug['auth_method'] ?? '')),
			],
		];

		\update_option(cmx_woocommerce_webhook_notice_option_name(), $payload, false);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_get_webhook_notice')) {
	function cmx_woocommerce_get_webhook_notice(): array {
		$notice = \get_option(cmx_woocommerce_webhook_notice_option_name(), []);

		return \is_array($notice) ? $notice : [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_clear_webhook_notice')) {
	function cmx_woocommerce_clear_webhook_notice(): void {
		\delete_option(cmx_woocommerce_webhook_notice_option_name());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_webhook_debug_context')) {
	function cmx_woocommerce_webhook_debug_context(\WP_REST_Request $request, string $topic, string $body, string $signature, string $token = '', string $auth_method = ''): array {
		$header_names = cmx_woocommerce_request_header_names($request);

		return [
			'topic'             => $topic,
			'body_length'       => \strlen($body),
			'signature_present' => $signature !== '',
			'signature_length'  => \strlen($signature),
			'delivery_id'       => cmx_woocommerce_header($request, 'x-wc-webhook-delivery-id'),
			'webhook_id'        => cmx_woocommerce_header($request, 'x-wc-webhook-id'),
			'user_agent'        => cmx_woocommerce_header($request, 'user-agent'),
			'header_names'      => \implode(', ', $header_names),
			'token_present'     => $token !== '',
			'auth_method'       => $auth_method,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_error_response')) {
	function cmx_woocommerce_error_response(\WP_REST_Request $request, int $status, string $code, string $hint = '', array $debug = []): \WP_REST_Response {
		$message = cmx_woocommerce_human_webhook_message($code);
		cmx_woocommerce_store_webhook_notice([
			'code'        => $code,
			'message'     => $message,
			'hint'        => $hint,
			'http_status' => $status,
			'captured_at' => \time(),
			'debug'       => $debug,
		]);

		$response = [
			'success' => false,
			'message' => $code,
			'detail'  => $message,
		];
		if ($hint !== '') {
			$response['hint'] = $hint;
		}
		if ($debug !== []) {
			$response['debug'] = $debug;
		}

		return new \WP_REST_Response($response, $status);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_is_unsigned_probe_request')) {
	function cmx_woocommerce_is_unsigned_probe_request(string $topic, string $body, string $signature, string $auth_method): bool {
		if ($auth_method !== 'url_token' || $signature !== '' || $topic !== '') {
			return false;
		}

		$body = \trim($body);
		if ($body === '') {
			return true;
		}

		return \strlen($body) <= 64;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_import_error_status')) {
	function cmx_woocommerce_import_error_status(string $code): int {
		$map = [
			'missing_order_id' => 400,
		];

		return (int) ($map[$code] ?? 500);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_pick_date_ymd')) {
	function cmx_woocommerce_pick_date_ymd(array $source, array $keys): string {
		foreach ($keys as $key) {
			$raw = \trim((string) ($source[$key] ?? ''));
			if ($raw === '') {
				continue;
			}

			$timestamp = \strtotime($raw);
			if ($timestamp) {
				return \gmdate('Y-m-d', $timestamp);
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_parse_datetime')) {
	function cmx_woocommerce_parse_datetime(string $raw, \DateTimeZone $timezone): ?\DateTimeImmutable {
		$raw = \trim($raw);
		if ($raw === '') {
			return null;
		}

		$formats = [
			'Y-m-d\TH:i:sP',
			'Y-m-d\TH:i:s.uP',
			'Y-m-d\TH:i:s',
			'Y-m-d H:i:s',
			'Y-m-d',
		];

		foreach ($formats as $format) {
			$parsed = \DateTimeImmutable::createFromFormat($format, $raw, $timezone);
			if ($parsed instanceof \DateTimeImmutable) {
				return $parsed;
			}
		}

		try {
			return new \DateTimeImmutable($raw, $timezone);
		} catch (\Throwable $exception) {
			return null;
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_pick_datetime_values')) {
	function cmx_woocommerce_pick_datetime_values(array $source, array $keys): array {
		$wp_timezone = \function_exists('wp_timezone') ? \wp_timezone() : new \DateTimeZone('UTC');
		$utc_timezone = new \DateTimeZone('UTC');

		foreach ($keys as $key) {
			$raw = \trim((string) ($source[$key] ?? ''));
			if ($raw === '') {
				continue;
			}

			$is_gmt = \substr($key, -4) === '_gmt';
			$base_timezone = $is_gmt ? $utc_timezone : $wp_timezone;
			$parsed = cmx_woocommerce_parse_datetime($raw, $base_timezone);
			if (!$parsed instanceof \DateTimeImmutable) {
				continue;
			}

			$local = $parsed->setTimezone($wp_timezone);
			$gmt = $parsed->setTimezone($utc_timezone);

			return [
				'date'          => $local->format('Y-m-d'),
				'post_date'     => $local->format('Y-m-d H:i:s'),
				'post_date_gmt' => $gmt->format('Y-m-d H:i:s'),
				'time'          => $local->format('H:i:s'),
			];
		}

		return [
			'date'          => '',
			'post_date'     => '',
			'post_date_gmt' => '',
			'time'          => '',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_sync_beleg_post_datetime')) {
	function cmx_woocommerce_sync_beleg_post_datetime(int $post_id, array $datetime): void {
		if ($post_id <= 0) {
			return;
		}

		$post_date = \trim((string) ($datetime['post_date'] ?? ''));
		$post_date_gmt = \trim((string) ($datetime['post_date_gmt'] ?? ''));
		if ($post_date === '' || $post_date_gmt === '') {
			return;
		}

		$post = \get_post($post_id);
		if (!$post instanceof \WP_Post || $post->post_type !== 'belege') {
			return;
		}

		if ((string) $post->post_date === $post_date && (string) $post->post_date_gmt === $post_date_gmt) {
			return;
		}

		\wp_update_post([
			'ID'            => $post_id,
			'post_date'     => $post_date,
			'post_date_gmt' => $post_date_gmt,
			'edit_date'     => true,
		]);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_order_number')) {
	function cmx_woocommerce_order_number(array $order): string {
		$number = \trim((string) ($order['number'] ?? ''));

		if ($number !== '') {
			return $number;
		}

		$order_id = (int) ($order['id'] ?? 0);

		return $order_id > 0 ? (string) $order_id : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_billing_name_parts')) {
	function cmx_woocommerce_billing_name_parts(array $order): array {
		$billing = \is_array($order['billing'] ?? null) ? $order['billing'] : [];

		return [
			'company'    => \trim((string) ($billing['company'] ?? '')),
			'first_name' => \trim((string) ($billing['first_name'] ?? '')),
			'last_name'  => \trim((string) ($billing['last_name'] ?? '')),
			'email'      => \sanitize_email((string) ($billing['email'] ?? '')),
			'phone'      => \trim((string) ($billing['phone'] ?? '')),
			'address_1'  => \trim((string) ($billing['address_1'] ?? '')),
			'address_2'  => \trim((string) ($billing['address_2'] ?? '')),
			'postcode'   => \trim((string) ($billing['postcode'] ?? '')),
			'city'       => \trim((string) ($billing['city'] ?? '')),
			'country'    => \strtoupper(\trim((string) ($billing['country'] ?? ''))),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_contact_title')) {
	function cmx_woocommerce_contact_title(array $order): string {
		$parts = cmx_woocommerce_billing_name_parts($order);
		$full_name = \trim(\preg_replace('/\s+/', ' ', $parts['first_name'] . ' ' . $parts['last_name']));

		if ($parts['company'] !== '') {
			return $parts['company'];
		}

		if ($full_name !== '') {
			return $full_name;
		}

		if ($parts['email'] !== '') {
			return $parts['email'];
		}

		$order_number = cmx_woocommerce_order_number($order);

		return $order_number !== '' ? ('WooCommerce ' . $order_number) : 'WooCommerce Kunde';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_contact_address_line')) {
	function cmx_woocommerce_contact_address_line(array $parts): string {
		return \trim(\preg_replace('/\s+/', ' ', $parts['address_1'] . ' ' . $parts['address_2']));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_find_existing_contact_id')) {
	function cmx_woocommerce_find_existing_contact_id(array $order): int {
		$post_type = \function_exists(__NAMESPACE__ . '\\cmx_kontakte_cpt')
			? cmx_kontakte_cpt()
			: 'kontakte';
		if (!\post_type_exists($post_type)) {
			return 0;
		}

		$parts = cmx_woocommerce_billing_name_parts($order);
		$email = $parts['email'];

		if ($email !== '') {
			$email_keys = \function_exists(__NAMESPACE__ . '\\cmx_kontakte_search_email_meta_keys')
				? (array) cmx_kontakte_search_email_meta_keys()
				: ['_cmx_email_1', '_cmx_email_2', '_cmx_email_3', 'email', 'mail'];
			$email_keys = \array_values(\array_unique(\array_filter(\array_map('strval', $email_keys))));
			$meta_query = ['relation' => 'OR'];
			foreach ($email_keys as $key) {
				$meta_query[] = [
					'key'   => $key,
					'value' => $email,
				];
			}

			$ids = \get_posts([
				'post_type'      => $post_type,
				'post_status'    => ['publish', 'private', 'draft', 'pending', 'future'],
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => $meta_query,
			]);
			$found = (int) ($ids[0] ?? 0);
			if ($found > 0) {
				return $found;
			}
		}

		global $wpdb;
		$title = cmx_woocommerce_contact_title($order);
		if ($title === '' || !($wpdb instanceof \wpdb)) {
			return 0;
		}

		$sql = $wpdb->prepare(
			"SELECT ID
			FROM {$wpdb->posts}
			WHERE post_type = %s
			  AND post_status <> 'trash'
			  AND post_title = %s
			ORDER BY ID ASC
			LIMIT 1",
			$post_type,
			$title
		);

		return $sql ? (int) $wpdb->get_var($sql) : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_upsert_contact')) {
	function cmx_woocommerce_upsert_contact(array $order): int {
		$post_type = \function_exists(__NAMESPACE__ . '\\cmx_kontakte_cpt')
			? cmx_kontakte_cpt()
			: 'kontakte';
		if (!\post_type_exists($post_type)) {
			return 0;
		}

		$parts = cmx_woocommerce_billing_name_parts($order);
		$title = cmx_woocommerce_contact_title($order);
		$contact_id = cmx_woocommerce_find_existing_contact_id($order);

		if ($contact_id <= 0) {
			$inserted = \wp_insert_post([
				'post_type'   => $post_type,
				'post_status' => 'publish',
				'post_title'  => $title,
			], true);
			if (\is_wp_error($inserted) || (int) $inserted <= 0) {
				return 0;
			}
			$contact_id = (int) $inserted;
		}

		if (\get_post_type($contact_id) !== $post_type) {
			return 0;
		}

		if (\trim((string) \get_the_title($contact_id)) === '' && $title !== '') {
			\wp_update_post([
				'ID'         => $contact_id,
				'post_title' => $title,
				'post_name'  => \sanitize_title($title),
			]);
		}

		$private = $parts['company'] === '' ? '1' : '0';
		$full_address = cmx_woocommerce_contact_address_line($parts);

		\update_post_meta($contact_id, '_cmx_kontakte_vorname', $parts['first_name']);
		\update_post_meta($contact_id, '_cmx_kontakte_nachname', $parts['last_name']);
		\update_post_meta($contact_id, '_cmx_kontakte_privat', $private);
		\update_post_meta($contact_id, '_cmx_email_1', $parts['email']);
		\update_post_meta($contact_id, '_cmx_telefon_1', $parts['phone']);
		\update_post_meta($contact_id, '_cmx_rechnung_strasse', $full_address);
		\update_post_meta($contact_id, '_cmx_rechnung_plz', $parts['postcode']);
		\update_post_meta($contact_id, '_cmx_rechnung_ort', $parts['city']);
		\update_post_meta($contact_id, '_cmx_rechnung_land', $parts['country']);

		$bundle = \get_post_meta($contact_id, '_cmx_kommunikation', true);
		if (!\is_array($bundle)) {
			$bundle = ['telefon' => [], 'email' => []];
		}
		$bundle['telefon'][1] = [
			'label' => (string) ($bundle['telefon'][1]['label'] ?? ''),
			'value' => $parts['phone'],
		];
		$bundle['email'][1] = [
			'label' => (string) ($bundle['email'][1]['label'] ?? ''),
			'value' => $parts['email'],
			'valid' => \is_email($parts['email']) ? '1' : '0',
		];
		\update_post_meta($contact_id, '_cmx_kommunikation', $bundle);

		return $contact_id;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_line_amounts')) {
	function cmx_woocommerce_line_amounts(array $line, bool $prices_include_tax, int $quantity): array {
		$subtotal = cmx_woocommerce_request_float($line['subtotal'] ?? ($line['total'] ?? 0));
		$subtotal_tax = cmx_woocommerce_request_float($line['subtotal_tax'] ?? ($line['total_tax'] ?? 0));
		$total = cmx_woocommerce_request_float($line['total'] ?? $subtotal);
		$total_tax = cmx_woocommerce_request_float($line['total_tax'] ?? 0);

		$before_discount = $prices_include_tax ? ($subtotal + $subtotal_tax) : $subtotal;
		$after_discount = $prices_include_tax ? ($total + $total_tax) : $total;

		if ($quantity <= 0) {
			$quantity = 1;
		}

		$unit_price = $before_discount / $quantity;
		$discount = $before_discount - $after_discount;
		if ($discount < 0) {
			$discount = 0.0;
		}

		return [
			'qty'        => $quantity,
			'unit_price' => $unit_price,
			'discount'   => $discount,
			'total'      => $after_discount,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_build_line_row')) {
	function cmx_woocommerce_build_line_row(array $line, bool $prices_include_tax, string $fallback_name = ''): ?array {
		$name = \trim((string) ($line['name'] ?? $fallback_name));
		$quantity = (int) ($line['quantity'] ?? 1);
		if ($quantity <= 0) {
			$quantity = 1;
		}

		$amounts = cmx_woocommerce_line_amounts($line, $prices_include_tax, $quantity);
		$description = \trim((string) ($line['meta_summary'] ?? ''));

		if ($name === '' && \abs($amounts['total']) < 0.00001) {
			return null;
		}

		return [
			'artikel_id'   => 0,
			'artikel_name' => $name,
			'sku'          => \trim((string) ($line['sku'] ?? '')),
			'menge'        => \round((float) $amounts['qty'], 2),
			'einheit_id'   => 0,
			'unit'         => '',
			'preis'        => \round((float) $amounts['unit_price'], 2),
			'rabatt'       => $amounts['discount'] > 0
				? \number_format((float) $amounts['discount'], 2, '.', '')
				: '',
			'beschreibung' => $description,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_collect_position_rows')) {
	function cmx_woocommerce_collect_position_rows(array $order): array {
		$prices_include_tax = !empty($order['prices_include_tax']);
		$rows = [];

		foreach ((array) ($order['line_items'] ?? []) as $line) {
			if (!\is_array($line)) {
				continue;
			}

			$row = cmx_woocommerce_build_line_row($line, $prices_include_tax);
			if (\is_array($row)) {
				$rows[] = $row;
			}
		}

		foreach ((array) ($order['shipping_lines'] ?? []) as $line) {
			if (!\is_array($line)) {
				continue;
			}

			$shipping_line = [
				'name'        => \trim((string) ($line['method_title'] ?? ($line['name'] ?? 'Versand'))),
				'quantity'    => 1,
				'subtotal'    => $line['total'] ?? 0,
				'subtotal_tax'=> $line['total_tax'] ?? 0,
				'total'       => $line['total'] ?? 0,
				'total_tax'   => $line['total_tax'] ?? 0,
			];
			$row = cmx_woocommerce_build_line_row($shipping_line, $prices_include_tax, 'Versand');
			if (\is_array($row)) {
				$rows[] = $row;
			}
		}

		foreach ((array) ($order['fee_lines'] ?? []) as $line) {
			if (!\is_array($line)) {
				continue;
			}

			$fee_line = [
				'name'        => \trim((string) ($line['name'] ?? 'Gebuehr')),
				'quantity'    => 1,
				'subtotal'    => $line['total'] ?? 0,
				'subtotal_tax'=> $line['total_tax'] ?? 0,
				'total'       => $line['total'] ?? 0,
				'total_tax'   => $line['total_tax'] ?? 0,
			];
			$row = cmx_woocommerce_build_line_row($fee_line, $prices_include_tax, 'Gebuehr');
			if (\is_array($row)) {
				$rows[] = $row;
			}
		}

		return \array_values($rows);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_collect_tax_rates')) {
	function cmx_woocommerce_collect_tax_rates(array $order): array {
		$rates = [];

		foreach ((array) ($order['tax_lines'] ?? []) as $tax_line) {
			if (!\is_array($tax_line)) {
				continue;
			}
			$candidates = [
				$tax_line['rate_percent'] ?? '',
				$tax_line['label'] ?? '',
				$tax_line['rate_code'] ?? '',
				$tax_line['name'] ?? '',
			];
			foreach ($candidates as $candidate) {
				$rate = \function_exists(__NAMESPACE__ . '\\cmxbu_parse_tax_rate')
					? (float) cmxbu_parse_tax_rate($candidate)
					: 0.0;
				if ($rate > 0) {
					$rates[] = $rate;
					break;
				}
			}
		}

		foreach (['line_items', 'shipping_lines', 'fee_lines'] as $group_key) {
			foreach ((array) ($order[$group_key] ?? []) as $line) {
				if (!\is_array($line)) {
					continue;
				}

				$subtotal = cmx_woocommerce_request_float($line['subtotal'] ?? ($line['total'] ?? 0));
				$subtotal_tax = cmx_woocommerce_request_float($line['subtotal_tax'] ?? ($line['total_tax'] ?? 0));
				if ($subtotal > 0 && $subtotal_tax > 0) {
					$rates[] = $subtotal_tax / $subtotal;
				}
			}
		}

		$normalized = [];
		foreach ($rates as $rate) {
			$rate = (float) $rate;
			if ($rate <= 0) {
				continue;
			}
			$normalized[] = \round($rate, 4);
		}

		$normalized = \array_values(\array_unique($normalized));
		\sort($normalized, \SORT_NUMERIC);

		return $normalized;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_find_mwst_term_id')) {
	function cmx_woocommerce_find_mwst_term_id(float $target_rate): int {
		if ($target_rate <= 0 || !\taxonomy_exists('belege_mwst')) {
			return 0;
		}

		$terms = \get_terms([
			'taxonomy'   => 'belege_mwst',
			'hide_empty' => false,
		]);
		if (\is_wp_error($terms) || !\is_array($terms)) {
			return 0;
		}

		$best_id = 0;
		$best_delta = 1.0;
		foreach ($terms as $term) {
			if (!$term instanceof \WP_Term) {
				continue;
			}

			$data = \function_exists(__NAMESPACE__ . '\\cmxbu_get_mwst_term_data')
				? (array) cmxbu_get_mwst_term_data((int) $term->term_id)
				: ['rate' => 0.0];
			$term_rate = (float) ($data['rate'] ?? 0.0);
			if ($term_rate <= 0) {
				continue;
			}

			$delta = \abs($term_rate - $target_rate);
			if ($delta < 0.0001) {
				return (int) $term->term_id;
			}
			if ($delta < $best_delta) {
				$best_delta = $delta;
				$best_id = (int) $term->term_id;
			}
		}

		return $best_delta <= 0.001 ? $best_id : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_import_notes')) {
	function cmx_woocommerce_import_notes(array $order, string $topic, array $tax_rates): string {
		$order_number = cmx_woocommerce_order_number($order);
		$status = \trim((string) ($order['status'] ?? ''));
		$total = \trim((string) ($order['total'] ?? ''));
		$currency = \strtoupper(\trim((string) ($order['currency'] ?? '')));
		$lines = [
			'WooCommerce-Import',
			'Bestellung: #' . ($order_number !== '' ? $order_number : (string) ((int) ($order['id'] ?? 0))),
		];

		if ($topic !== '') {
			$lines[] = 'Webhook-Topic: ' . $topic;
		}
		if ($status !== '') {
			$lines[] = 'Woo-Status: ' . $status;
		}
		if ($total !== '') {
			$lines[] = 'Woo-Gesamt: ' . $total . ($currency !== '' ? (' ' . $currency) : '');
		}
		if (!empty($tax_rates)) {
			$lines[] = 'Steuersaetze: ' . \implode(', ', \array_map(static function (float $rate): string {
				return \number_format($rate * 100, 2, '.', '') . '%';
			}, $tax_rates));
		}
		if (\count($tax_rates) > 1) {
			$lines[] = 'Hinweis: Mehrere Steuersaetze erkannt. Der Beleg unterstuetzt aktuell nur einen globalen MwSt-Satz.';
		}

		return \implode("\n", $lines);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_map_beleg_status')) {
	function cmx_woocommerce_map_beleg_status(array $order): string {
		$status = \strtolower(\trim((string) ($order['status'] ?? '')));

		if (\in_array($status, ['completed', 'processing'], true)) {
			return 'bezahlt';
		}

		return 'offen';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_find_beleg_id_by_order')) {
	function cmx_woocommerce_find_beleg_id_by_order(int $order_id): int {
		if ($order_id <= 0) {
			return 0;
		}

		$ids = \get_posts([
			'post_type'      => 'belege',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => '_cmx_wc_order_id',
			'meta_value'     => (string) $order_id,
		]);

		return (int) ($ids[0] ?? 0);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_assign_rechnung_term')) {
	function cmx_woocommerce_assign_rechnung_term(int $post_id): void {
		if ($post_id <= 0 || !\function_exists(__NAMESPACE__ . '\\cmx_belege_kategorie_taxonomy')) {
			return;
		}

		$tax = (string) cmx_belege_kategorie_taxonomy();
		if ($tax === '' || !\taxonomy_exists($tax)) {
			return;
		}

		$term = \get_term_by('slug', 'rechnung', $tax);
		if (!$term || \is_wp_error($term)) {
			$term = \get_term_by('name', 'Rechnung', $tax);
		}
		if ($term && !\is_wp_error($term)) {
			\wp_set_post_terms($post_id, [(int) $term->term_id], $tax, false);
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_order_due_date')) {
	function cmx_woocommerce_order_due_date(string $invoice_date): string {
		$timestamp = \strtotime($invoice_date);
		if (!$timestamp) {
			return '';
		}

		$days = \function_exists(__NAMESPACE__ . '\\cmx_belege_default_due_days')
			? (int) cmx_belege_default_due_days((array) \get_option(cmx_woocommerce_option_name(), []))
			: 30;

		return \gmdate('Y-m-d', \strtotime('+' . \max(0, $days) . ' days', $timestamp));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_finalize_beleg')) {
	function cmx_woocommerce_finalize_beleg(int $post_id, bool $created): string {
		$invoice_no = \function_exists(__NAMESPACE__ . '\\cmx_ensure_rechnungsnummer')
			? (string) cmx_ensure_rechnungsnummer($post_id)
			: '';

		$current = \get_post($post_id);
		$current_title = $current instanceof \WP_Post ? \trim((string) $current->post_title) : '';
		if (($created || $current_title === '') && $invoice_no !== '') {
			\wp_update_post([
				'ID'         => $post_id,
				'post_title' => $invoice_no,
				'post_name'  => \sanitize_title($invoice_no),
			]);
			\update_post_meta($post_id, '_cmx_title_auto', 1);
		}

		return $invoice_no;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_generate_pdf')) {
	function cmx_woocommerce_generate_pdf(int $post_id): void {
		$post = \get_post($post_id);
		if (!$post instanceof \WP_Post || $post->post_type !== 'belege') {
			return;
		}
		if (!\function_exists(__NAMESPACE__ . '\\cmxbu_generate_document_on_save')) {
			return;
		}

		$previous = !empty($GLOBALS['cmx_woocommerce_internal_import']);
		$GLOBALS['cmx_woocommerce_internal_import'] = true;
		try {
			cmxbu_generate_document_on_save($post_id, $post, true);
		} finally {
			if ($previous) {
				$GLOBALS['cmx_woocommerce_internal_import'] = true;
			} else {
				unset($GLOBALS['cmx_woocommerce_internal_import']);
			}
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_auto_mail_enabled')) {
	function cmx_woocommerce_auto_mail_enabled(): bool {
		return cmx_woocommerce_normalize_checkbox(cmx_woocommerce_get_setting('misbuero_auto_mail', '0')) === '1';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_auto_mail_sent_meta_key')) {
	function cmx_woocommerce_auto_mail_sent_meta_key(): string {
		return '_cmx_wc_auto_mail_sent';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_auto_mail_sent_at_meta_key')) {
	function cmx_woocommerce_auto_mail_sent_at_meta_key(): string {
		return '_cmx_wc_auto_mail_sent_at';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_auto_mail_error_meta_key')) {
	function cmx_woocommerce_auto_mail_error_meta_key(): string {
		return '_cmx_wc_auto_mail_error';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_auto_mail_recipient_meta_key')) {
	function cmx_woocommerce_auto_mail_recipient_meta_key(): string {
		return '_cmx_wc_auto_mail_to';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_maybe_send_auto_mail')) {
	function cmx_woocommerce_maybe_send_auto_mail(int $beleg_id): array {
		if ($beleg_id <= 0) {
			return [
				'enabled' => false,
				'status'  => 'skipped',
			];
		}

		if (!cmx_woocommerce_auto_mail_enabled()) {
			return [
				'enabled' => false,
				'status'  => 'disabled',
			];
		}

		if ((string) \get_post_meta($beleg_id, cmx_woocommerce_auto_mail_sent_meta_key(), true) === '1') {
			return [
				'enabled'   => true,
				'status'    => 'already_sent',
				'recipient' => (string) \get_post_meta($beleg_id, cmx_woocommerce_auto_mail_recipient_meta_key(), true),
			];
		}

		if (!\function_exists(__NAMESPACE__ . '\\cmxbu_send_beleg_mail')) {
			require_once __DIR__ . '/meta_action_send.php';
		}

		if (!\function_exists(__NAMESPACE__ . '\\cmxbu_send_beleg_mail')) {
			return [
				'enabled' => true,
				'status'  => 'unavailable',
			];
		}

		$result = cmxbu_send_beleg_mail($beleg_id, ['regenerate_pdf' => false]);
		if (\is_wp_error($result)) {
			$message = \trim((string) $result->get_error_message());
			\update_post_meta($beleg_id, cmx_woocommerce_auto_mail_sent_meta_key(), '0');
			\update_post_meta($beleg_id, cmx_woocommerce_auto_mail_error_meta_key(), $message);

			return [
				'enabled' => true,
				'status'  => 'failed',
				'code'    => (string) $result->get_error_code(),
				'message' => $message,
			];
		}

		$recipient = \sanitize_email((string) ($result['to'] ?? ''));
		\update_post_meta($beleg_id, cmx_woocommerce_auto_mail_sent_meta_key(), '1');
		\update_post_meta($beleg_id, cmx_woocommerce_auto_mail_sent_at_meta_key(), (string) \current_time('mysql'));
		\update_post_meta($beleg_id, cmx_woocommerce_auto_mail_recipient_meta_key(), $recipient);
		\delete_post_meta($beleg_id, cmx_woocommerce_auto_mail_error_meta_key());

		return [
			'enabled'   => true,
			'status'    => 'sent',
			'recipient' => $recipient,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_current_admin_beleg_id')) {
	function cmx_woocommerce_current_admin_beleg_id(): int {
		$post_id = isset($_GET['post']) ? \absint(\wp_unslash($_GET['post'])) : 0;
		if ($post_id > 0) {
			return $post_id;
		}

		$post_id = isset($_POST['post_ID']) ? \absint(\wp_unslash($_POST['post_ID'])) : 0;
		return $post_id > 0 ? $post_id : 0;
	}
}

\add_action('all_admin_notices', __NAMESPACE__ . '\\cmx_render_woocommerce_auto_mail_notice');
if (!\function_exists(__NAMESPACE__ . '\\cmx_render_woocommerce_auto_mail_notice')) {
	function cmx_render_woocommerce_auto_mail_notice(): void {
		$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
		if (!$screen || (string) ($screen->post_type ?? '') !== 'belege' || (string) ($screen->base ?? '') !== 'post') {
			return;
		}

		$post_id = cmx_woocommerce_current_admin_beleg_id();
		if ($post_id <= 0 || !cmx_woocommerce_is_webhook_beleg($post_id)) {
			return;
		}

		$error = \trim((string) \get_post_meta($post_id, cmx_woocommerce_auto_mail_error_meta_key(), true));
		if ($error !== '') {
			echo '<div class="notice notice-error">';
			echo '<p><strong>' . \esc_html__('Woo Auto-Mail fehlgeschlagen.', 'cmx-misbuero') . '</strong> ' . \esc_html($error) . '</p>';
			echo '</div>';
			return;
		}

		if ((string) \get_post_meta($post_id, cmx_woocommerce_auto_mail_sent_meta_key(), true) !== '1') {
			return;
		}

		$parts = [];
		$recipient = \sanitize_email((string) \get_post_meta($post_id, cmx_woocommerce_auto_mail_recipient_meta_key(), true));
		if ($recipient !== '' && \is_email($recipient)) {
			$parts[] = 'an ' . $recipient;
		}
		$sent_at = \trim((string) \get_post_meta($post_id, cmx_woocommerce_auto_mail_sent_at_meta_key(), true));
		$sent_at_ts = $sent_at !== '' ? \strtotime($sent_at) : false;
		if ($sent_at_ts) {
			$parts[] = 'am ' . \wp_date('d.m.Y H:i', $sent_at_ts);
		}

		echo '<div class="notice notice-success">';
		echo '<p><strong>' . \esc_html__('Woo Auto-Mail wurde versendet.', 'cmx-misbuero') . '</strong>';
		if ($parts !== []) {
			echo ' ' . \esc_html(\implode(' ', $parts)) . '.';
		}
		echo '</p>';
		echo '</div>';
	}
}

\add_action('add_meta_boxes', __NAMESPACE__ . '\\cmx_register_woocommerce_status_metabox');
if (!\function_exists(__NAMESPACE__ . '\\cmx_register_woocommerce_status_metabox')) {
	function cmx_register_woocommerce_status_metabox(): void {
		$post_id = cmx_woocommerce_current_admin_beleg_id();
		if ($post_id <= 0 || !cmx_woocommerce_is_webhook_beleg($post_id)) {
			return;
		}

		\add_meta_box(
			'cmx-woocommerce-status',
			__('WooCommerce', 'cmx-misbuero'),
			__NAMESPACE__ . '\\cmx_render_woocommerce_status_metabox',
			'belege',
			'side',
			'high'
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_woocommerce_status_metabox')) {
	function cmx_render_woocommerce_status_metabox(\WP_Post $post): void {
		if ($post->post_type !== 'belege' || !cmx_woocommerce_is_webhook_beleg((int) $post->ID)) {
			echo '<p>' . \esc_html__('Kein WooCommerce-WebHook-Beleg.', 'cmx-misbuero') . '</p>';
			return;
		}

		$order_id = \trim((string) \get_post_meta($post->ID, '_cmx_wc_order_id', true));
		$order_number = \trim((string) \get_post_meta($post->ID, '_cmx_wc_order_number', true));
		$order_status = \trim((string) \get_post_meta($post->ID, '_cmx_wc_order_status', true));
		$recipient = \sanitize_email((string) \get_post_meta($post->ID, cmx_woocommerce_auto_mail_recipient_meta_key(), true));
		$sent_at = \trim((string) \get_post_meta($post->ID, cmx_woocommerce_auto_mail_sent_at_meta_key(), true));
		$error = \trim((string) \get_post_meta($post->ID, cmx_woocommerce_auto_mail_error_meta_key(), true));
		$mail_sent = (string) \get_post_meta($post->ID, cmx_woocommerce_auto_mail_sent_meta_key(), true) === '1';
		$auto_mail_enabled = cmx_woocommerce_auto_mail_enabled();

		echo '<div style="display:grid;gap:8px;">';
		echo '<p style="margin:0;"><strong>' . \esc_html__('Bestellung', 'cmx-misbuero') . ':</strong> ' . \esc_html($order_number !== '' ? ('#' . $order_number) : ($order_id !== '' ? ('#' . $order_id) : '—')) . '</p>';
		if ($order_status !== '') {
			echo '<p style="margin:0;"><strong>' . \esc_html__('Woo-Status', 'cmx-misbuero') . ':</strong> ' . \esc_html($order_status) . '</p>';
		}

		if ($error !== '') {
			echo '<div style="margin:4px 0 0 0;padding:10px 12px;border:1px solid #d63638;border-radius:8px;background:#fcf0f1;color:#8a2424;">';
			echo '<strong>' . \esc_html__('Auto-Mail fehlgeschlagen', 'cmx-misbuero') . '</strong>';
			echo '<div style="margin-top:4px;">' . \esc_html($error) . '</div>';
			echo '</div>';
		} elseif ($mail_sent) {
			echo '<div style="margin:4px 0 0 0;padding:10px 12px;border:1px solid #00a32a;border-radius:8px;background:#edfaef;color:#0f5132;">';
			echo '<strong>' . \esc_html__('Auto-Mail versendet', 'cmx-misbuero') . '</strong>';
			if ($recipient !== '' && \is_email($recipient)) {
				echo '<div style="margin-top:4px;">' . \esc_html__('Empfänger:', 'cmx-misbuero') . ' ' . \esc_html($recipient) . '</div>';
			}
			if ($sent_at !== '') {
				$sent_at_ts = \strtotime($sent_at);
				if ($sent_at_ts) {
					echo '<div style="margin-top:4px;">' . \esc_html__('Versendet am:', 'cmx-misbuero') . ' ' . \esc_html(\wp_date('d.m.Y H:i', $sent_at_ts)) . '</div>';
				}
			}
			echo '</div>';
		} elseif (!$auto_mail_enabled) {
			echo '<div style="margin:4px 0 0 0;padding:10px 12px;border:1px solid #dcdcde;border-radius:8px;background:#f6f7f7;color:#50575e;">';
			echo '<strong>' . \esc_html__('Auto-Mail ist deaktiviert', 'cmx-misbuero') . '</strong>';
			echo '<div style="margin-top:4px;">' . \esc_html__('Die Checkbox "Automatischer Mailversand" ist in den WooCommerce-Einstellungen aktuell nicht aktiv.', 'cmx-misbuero') . '</div>';
			echo '</div>';
		} else {
			echo '<div style="margin:4px 0 0 0;padding:10px 12px;border:1px solid #dcdcde;border-radius:8px;background:#f6f7f7;color:#50575e;">';
			echo '<strong>' . \esc_html__('Noch kein Versandstatus', 'cmx-misbuero') . '</strong>';
			echo '<div style="margin-top:4px;">' . \esc_html__('Für diesen Woo-Beleg wurde noch kein Auto-Mail-Status gespeichert.', 'cmx-misbuero') . '</div>';
			echo '</div>';
		}

		echo '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_import_order')) {
	function cmx_woocommerce_import_order(array $order, string $topic = ''): array {
		$order_id = (int) ($order['id'] ?? 0);
		if ($order_id <= 0) {
			return [
				'success' => false,
				'message' => 'missing_order_id',
			];
		}

		$beleg_id = cmx_woocommerce_find_beleg_id_by_order($order_id);
		$created = false;

		if ($beleg_id <= 0) {
			$inserted = \wp_insert_post([
				'post_type'   => 'belege',
				'post_status' => 'publish',
				'post_title'  => '',
				'meta_input'  => [
					'_cmx_title_auto' => 1,
				],
			], true);
			if (\is_wp_error($inserted) || (int) $inserted <= 0) {
				return [
					'success' => false,
					'message' => 'beleg_insert_failed',
				];
			}
			$beleg_id = (int) $inserted;
			$created = true;
		}

		$kontakt_id = cmx_woocommerce_upsert_contact($order);
		$contact_title = $kontakt_id > 0 ? (string) \get_the_title($kontakt_id) : cmx_woocommerce_contact_title($order);
		$contact_address = '';
		if ($kontakt_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_build_kontakt_postanschrift')) {
			$contact_address = (string) cmx_build_kontakt_postanschrift($kontakt_id);
		}

		$invoice_datetime = cmx_woocommerce_pick_datetime_values($order, ['date_created_gmt', 'date_created', 'date_paid_gmt', 'date_paid']);
		$invoice_date = (string) ($invoice_datetime['date'] ?? '');
		if ($invoice_date === '') {
			$invoice_date = (string) \wp_date('Y-m-d');
			$invoice_datetime['date'] = $invoice_date;
			$invoice_datetime['post_date'] = (string) \wp_date('Y-m-d H:i:s');
			$invoice_datetime['post_date_gmt'] = (string) \gmdate('Y-m-d H:i:s', \current_time('timestamp', true));
			$invoice_datetime['time'] = (string) \wp_date('H:i:s');
		}
		$paid_date = cmx_woocommerce_pick_date_ymd($order, ['date_paid_gmt', 'date_paid', 'date_completed_gmt', 'date_completed']);
		$due_date = cmx_woocommerce_order_due_date($invoice_date);
		$currency = \strtoupper(\trim((string) ($order['currency'] ?? 'CHF')));
		if (!\preg_match('/^[A-Z]{3}$/', $currency)) {
			$currency = 'CHF';
		}

		$positions = cmx_woocommerce_collect_position_rows($order);
		$tax_rates = cmx_woocommerce_collect_tax_rates($order);
		$mwst_term_id = 0;
		if (\count($tax_rates) === 1) {
			$mwst_term_id = cmx_woocommerce_find_mwst_term_id((float) $tax_rates[0]);
		}
		$is_brutto = !empty($order['prices_include_tax']) ? '1' : '0';
		$beleg_status = cmx_woocommerce_map_beleg_status($order);
		$order_number = cmx_woocommerce_order_number($order);

		\update_post_meta($beleg_id, '_cmx_wc_order_id', (string) $order_id);
		\update_post_meta($beleg_id, '_cmx_wc_order_number', $order_number);
		\update_post_meta($beleg_id, '_cmx_wc_order_status', \sanitize_key((string) ($order['status'] ?? '')));
		\update_post_meta($beleg_id, '_cmx_wc_webhook_topic', \sanitize_text_field($topic));
		\update_post_meta($beleg_id, cmx_woocommerce_webhook_beleg_meta_key(), '1');
		\update_post_meta($beleg_id, '_cmx_beleg_richtung', 'ausgang');
		\update_post_meta($beleg_id, '_cmx_beleg_kontakt_id', $kontakt_id > 0 ? $kontakt_id : '');
		\update_post_meta($beleg_id, '_cmx_beleg_kontakt_label', $contact_title);
		\update_post_meta($beleg_id, '_cmx_beleg_kontakt_addr', $contact_address);
		\update_post_meta($beleg_id, '_cmx_beleg_rng_datum', $invoice_date);
		\update_post_meta($beleg_id, '_cmx_beleg_faelligkeitsdatum', $due_date);
		\update_post_meta($beleg_id, '_cmx_beleg_status', $beleg_status);
		\update_post_meta($beleg_id, '_cmx_beleg_waehrung', $currency);
		\update_post_meta($beleg_id, '_cmx_beleg_betreff', 'WooCommerce Bestellung #' . ($order_number !== '' ? $order_number : (string) $order_id));
		\update_post_meta($beleg_id, '_cmx_beleg_positionen', $positions);
		\update_post_meta($beleg_id, '_cmx_beleg_is_brutto', $is_brutto);
		\update_post_meta($beleg_id, '_cmx_beleg_mwst_term', $mwst_term_id > 0 ? $mwst_term_id : '');
		\update_post_meta($beleg_id, '_cmx_beleg_intern_notizen', cmx_woocommerce_import_notes($order, $topic, $tax_rates));
		cmx_woocommerce_sync_beleg_post_datetime($beleg_id, $invoice_datetime);

		if ($paid_date !== '') {
			\update_post_meta($beleg_id, '_cmx_beleg_bezahlt_am', $paid_date);
		} else {
			\delete_post_meta($beleg_id, '_cmx_beleg_bezahlt_am');
		}

		if (!empty($positions)) {
			\delete_post_meta($beleg_id, '_cmx_beleg_summe_override');
		}

		cmx_woocommerce_assign_rechnung_term($beleg_id);
		$invoice_no = cmx_woocommerce_finalize_beleg($beleg_id, $created);
		cmx_woocommerce_generate_pdf($beleg_id);
		$auto_mail = cmx_woocommerce_maybe_send_auto_mail($beleg_id);

		$response = [
			'success'           => true,
			'beleg_id'          => $beleg_id,
			'created'           => $created,
			'rechnungsnummer'   => $invoice_no,
			'kontakt_id'        => $kontakt_id,
			'webhook_topic'     => $topic,
			'webhook_target'    => \function_exists(__NAMESPACE__ . '\\cmx_woocommerce_webhook_delivery_url')
				? cmx_woocommerce_webhook_delivery_url()
				: cmx_woocommerce_webhook_url(),
			'woocommerce_order' => $order_id,
		];

		if (!empty($auto_mail['enabled'])) {
			$response['auto_mail'] = $auto_mail;
		}

		return $response;
	}
}

\add_action('rest_api_init', static function (): void {
	\register_rest_route('cmx-misbuero/v1', '/woocommerce/webhook', [
		'methods'             => 'POST',
		'callback'            => __NAMESPACE__ . '\\cmx_woocommerce_rest_webhook_callback',
		'permission_callback' => '__return_true',
	]);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_rest_webhook_callback')) {
	function cmx_woocommerce_rest_webhook_callback(\WP_REST_Request $request): \WP_REST_Response {
		$secrets = cmx_woocommerce_active_webhook_secrets();
		if ($secrets === []) {
			return cmx_woocommerce_error_response(
				$request,
				503,
				'webhook_secret_missing',
				'Bitte den Secret Key in Mis BuerO speichern und denselben Wert im externen WooCommerce-WebHook eintragen.'
			);
		}

		$body = (string) $request->get_body();
		$topic = \strtolower(cmx_woocommerce_header($request, 'x-wc-webhook-topic'));
		if ($topic === 'ping') {
			cmx_woocommerce_clear_webhook_notice();
			return new \WP_REST_Response([
				'success' => true,
				'message' => 'pong',
			], 200);
		}

		$signature = cmx_woocommerce_header($request, 'x-wc-webhook-signature');
		$token = cmx_woocommerce_request_webhook_token($request);
		$token_valid = cmx_woocommerce_validate_webhook_token($token);
		$auth_method = '';
		if ($signature !== '' && cmx_woocommerce_verify_signature_against_active_secrets($body, $signature)) {
			$auth_method = 'signature';
		} elseif ($token_valid) {
			$auth_method = 'url_token';
		}

		$debug = cmx_woocommerce_webhook_debug_context($request, $topic, $body, $signature, $token, $auth_method);
		if ($signature === '' && !$token_valid) {
			return cmx_woocommerce_error_response(
				$request,
				401,
				'missing_signature_header',
				'Der WebHook ist angekommen, aber der Signatur-Header fehlt. Das deutet eher auf Server/Proxy/WAF hin. Falls Dein Host die X-WC-Header entfernt, bitte die neu angezeigte Webhook URL mit URL-Token im externen WooCommerce hinterlegen.',
				$debug
			);
		}

		if ($body === '') {
			if (cmx_woocommerce_is_unsigned_probe_request($topic, $body, $signature, $auth_method)) {
				cmx_woocommerce_clear_webhook_notice();
				return new \WP_REST_Response([
					'success'     => true,
					'message'     => 'accepted_unsigned_probe',
					'auth_method' => $auth_method,
				], 200);
			}

			return cmx_woocommerce_error_response(
				$request,
				400,
				'empty_payload',
				'WooCommerce oder ein Proxy hat keinen JSON-Body an die Delivery URL uebergeben.',
				$debug
			);
		}

		if ($signature !== '' && $auth_method === '') {
			return cmx_woocommerce_error_response(
				$request,
				401,
				'invalid_signature',
				'Secret Key, unveraenderter Request-Body und eventuell dazwischenliegende Proxies/WAF pruefen.',
				$debug
			);
		}

		if (cmx_woocommerce_is_unsigned_probe_request($topic, $body, $signature, $auth_method)) {
			cmx_woocommerce_clear_webhook_notice();
			return new \WP_REST_Response([
				'success'     => true,
				'message'     => 'accepted_unsigned_probe',
				'auth_method' => $auth_method,
			], 200);
		}

		$payload = \json_decode($body, true);
		if (!\is_array($payload)) {
			return cmx_woocommerce_error_response(
				$request,
				400,
				'invalid_json',
				'Der Body wurde empfangen, ist aber kein gueltiges JSON.',
				$debug
			);
		}

		if ($topic !== '' && \strpos($topic, 'order.') !== 0) {
			cmx_woocommerce_clear_webhook_notice();
			return new \WP_REST_Response([
				'success' => true,
				'message' => 'ignored_non_order_topic',
				'topic'   => $topic,
			], 200);
		}

		$result = cmx_woocommerce_import_order($payload, $topic);
		$status_code = !empty($result['success']) ? 200 : cmx_woocommerce_import_error_status((string) ($result['message'] ?? ''));
		if (!empty($result['success'])) {
			cmx_woocommerce_clear_webhook_notice();
		} else {
			return cmx_woocommerce_error_response(
				$request,
				$status_code,
				(string) ($result['message'] ?? 'webhook_import_failed'),
				'Der WebHook wurde verifiziert, aber beim Import in Mis BuerO ist ein Fehler aufgetreten.',
				$debug
			);
		}

		return new \WP_REST_Response($result, $status_code);
	}
}
