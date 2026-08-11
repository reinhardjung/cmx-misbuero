<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_INFRASTRUKTUR_PENDING_JOBS_OPTION = '_cmx_infrastruktur_provisioning_jobs';

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_validate_instance_request')) {
	function cmx_infrastruktur_validate_instance_request(array $input) {
		$source_id = (int) ($input['post_id'] ?? $input['source_id'] ?? 0);
		$customer_name = \trim(\sanitize_text_field((string) ($input['customer_name'] ?? '')));
		$domain = \strtolower(\trim(\sanitize_text_field((string) ($input['domain'] ?? ''))));
		$domain = (string) \preg_replace('~^https?://~i', '', $domain);
		$domain = \rtrim((string) \strtok($domain, '/?#'), '.');
		$email = \sanitize_email((string) ($input['email'] ?? ''));
		$action = \sanitize_key((string) ($input['instance_action'] ?? $input['action'] ?? ''));
		if (\get_post_type($source_id) !== 'infrastruktur') {
			return new \WP_Error('cmx_instance_source_invalid', __('Der Infrastruktur-CPT wurde nicht gefunden.', 'cmx-misbuero'));
		}
		if (!\in_array($action, ['create', 'update', 'pause', 'delete'], true)) {
			return new \WP_Error('cmx_instance_action_invalid', __('Die Aktion ist ungültig.', 'cmx-misbuero'));
		}
		if ($customer_name === '' || \strlen($customer_name) > 200) {
			return new \WP_Error('cmx_instance_customer_invalid', __('Bitte einen gültigen Kundennamen eingeben.', 'cmx-misbuero'));
		}
		if ($domain === '' || \strlen($domain) > 253 || !\preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,63}$/', $domain)) {
			return new \WP_Error('cmx_instance_domain_invalid', __('Bitte eine gültige Domain eingeben.', 'cmx-misbuero'));
		}
		if ($email === '' || !\is_email($email)) {
			return new \WP_Error('cmx_instance_email_invalid', __('Bitte eine gültige E-Mail-Adresse eingeben.', 'cmx-misbuero'));
		}
		return ['source_id' => $source_id, 'customer_name' => $customer_name, 'domain' => $domain, 'email' => $email, 'action' => $action];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_provisioning_api_url')) {
	function cmx_infrastruktur_provisioning_api_url(): string {
		$url = \defined('CMX_MISBUERO_PROVISIONING_API_URL')
			? (string) \constant('CMX_MISBUERO_PROVISIONING_API_URL')
			: (string) \getenv('CMX_MISBUERO_PROVISIONING_API_URL');
		if ($url === '') {
			$url = (string) \get_option('mis_buero_provisioning_api_url', 'https://misbuero.cloudmeister.services/api/v1');
		}
		return \untrailingslashit(\esc_url_raw($url));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_provisioning_token')) {
	function cmx_infrastruktur_provisioning_token(): string {
		$token = \defined('CMX_MISBUERO_PROVISIONING_API_KEY')
			? (string) \constant('CMX_MISBUERO_PROVISIONING_API_KEY')
			: (string) \getenv('CMX_MISBUERO_PROVISIONING_API_KEY');
		if ($token === '') {
			$token = (string) \get_option('mis_buero_provisioning_api_key', '');
		}
		return \trim($token);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_woocommerce_token')) {
	function cmx_infrastruktur_woocommerce_token(): string {
		$token = \defined('CMX_MISBUERO_WOOCOMMERCE_API_KEY')
			? (string) \constant('CMX_MISBUERO_WOOCOMMERCE_API_KEY')
			: (string) \getenv('CMX_MISBUERO_WOOCOMMERCE_API_KEY');
		if ($token === '') {
			$token = (string) \get_option('mis_buero_woocommerce_api_key', '');
		}
		return \trim($token);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_provisioning_is_configured')) {
	function cmx_infrastruktur_provisioning_is_configured(int $source_id = 0): bool {
		return cmx_infrastruktur_provisioning_api_url() !== '' && cmx_infrastruktur_provisioning_token() !== '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_api_request')) {
	function cmx_infrastruktur_api_request(string $method, string $path, ?array $body = null, string $idempotency_key = '') {
		$url = cmx_infrastruktur_provisioning_api_url() . '/' . \ltrim($path, '/');
		$token = cmx_infrastruktur_provisioning_token();
		if ($token === '') {
			return new \WP_Error('cmx_provisioning_token_missing', __('Der Provisionierungs-API-Token fehlt.', 'cmx-misbuero'));
		}
		$args = [
			'method'      => \strtoupper($method),
			'timeout'     => 30,
			'redirection' => 0,
			'headers'     => [
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			],
		];
		if ($body !== null) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body'] = \wp_json_encode($body);
		}
		if ($idempotency_key !== '') {
			$args['headers']['Idempotency-Key'] = $idempotency_key;
		}
		$response = \wp_remote_request($url, $args);
		if (\is_wp_error($response)) {
			return $response;
		}
		$status = (int) \wp_remote_retrieve_response_code($response);
		$data = \json_decode((string) \wp_remote_retrieve_body($response), true);
		if (!\is_array($data)) {
			return new \WP_Error('cmx_provisioning_invalid_response', __('Die Provisionierungs-API lieferte keine gültige Antwort.', 'cmx-misbuero'));
		}
		if ($status < 200 || $status >= 300) {
			$message = (string) ($data['message'] ?? $data['error'] ?? __('Die Provisionierungs-API meldete einen Fehler.', 'cmx-misbuero'));
			return new \WP_Error('cmx_provisioning_api_error', $message, ['status' => $status]);
		}
		return $data;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_pending_jobs')) {
	function cmx_infrastruktur_pending_jobs(): array {
		$value = \get_option(CMX_INFRASTRUKTUR_PENDING_JOBS_OPTION, []);
		return \is_array($value) ? $value : [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_store_pending_job')) {
	function cmx_infrastruktur_store_pending_job(string $job_id, array $context): void {
		$jobs = cmx_infrastruktur_pending_jobs();
		$jobs[$job_id] = $context + ['job_id' => $job_id, 'created_at' => \time()];
		\update_option(CMX_INFRASTRUKTUR_PENDING_JOBS_OPTION, $jobs, false);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_submit_job')) {
	function cmx_infrastruktur_submit_job(array $input, string $idempotency_key = '') {
		$payload = cmx_infrastruktur_validate_instance_request($input);
		if (\is_wp_error($payload)) {
			return $payload;
		}
		if ($idempotency_key === '') {
			$idempotency_key = 'wp-' . \wp_generate_uuid4();
		}
		$response = cmx_infrastruktur_api_request('POST', 'wordpress-instances', [
			'customer_name'   => $payload['customer_name'],
			'domain'          => $payload['domain'],
			'email'           => $payload['email'],
			'action'          => $payload['action'],
			'idempotency_key' => $idempotency_key,
		], $idempotency_key);
		if (\is_wp_error($response)) {
			return $response;
		}
		$job_id = \sanitize_text_field((string) ($response['id'] ?? ''));
		if ($job_id === '') {
			return new \WP_Error('cmx_provisioning_job_missing', __('Die Provisionierungs-API lieferte keine Job-ID.', 'cmx-misbuero'));
		}
		cmx_infrastruktur_store_pending_job($job_id, [
			'source_id' => (int) $payload['source_id'],
			'action'    => $payload['action'],
			'domain'    => $payload['domain'],
			'email'     => $payload['email'],
		]);
		return $response;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_poll_job')) {
	function cmx_infrastruktur_poll_job(string $job_id) {
		$job_id = \sanitize_text_field($job_id);
		$jobs = cmx_infrastruktur_pending_jobs();
		$context = isset($jobs[$job_id]) && \is_array($jobs[$job_id]) ? $jobs[$job_id] : [];
		$response = cmx_infrastruktur_api_request('GET', 'jobs/' . \rawurlencode($job_id));
		if (\is_wp_error($response)) {
			return $response;
		}
		$status = \sanitize_key((string) ($response['status'] ?? ''));
		if ($status === 'succeeded' && $context !== []) {
			if ((string) ($context['action'] ?? '') === 'create') {
				$new_id = cmx_infrastruktur_duplicate_for_instance((int) $context['source_id'], (string) $context['domain'], (string) $context['email']);
				if (\is_wp_error($new_id)) {
					return $new_id;
				}
				$response['created_post_id'] = (int) $new_id;
				$response['edit_url'] = (string) \get_edit_post_link((int) $new_id, 'raw');
			}
			unset($jobs[$job_id]);
			\update_option(CMX_INFRASTRUKTUR_PENDING_JOBS_OPTION, $jobs, false);
		} elseif ($status === 'failed' && $context !== []) {
			unset($jobs[$job_id]);
			\update_option(CMX_INFRASTRUKTUR_PENDING_JOBS_OPTION, $jobs, false);
		}
		return $response;
	}
}

\add_filter('cron_schedules', function (array $schedules): array {
	$schedules['cmx_every_minute'] = ['interval' => 60, 'display' => __('Jede Minute', 'cmx-misbuero')];
	return $schedules;
});

\add_action('init', function (): void {
	if (!\wp_next_scheduled('cmx_infrastruktur_poll_jobs')) {
		\wp_schedule_event(\time() + 60, 'cmx_every_minute', 'cmx_infrastruktur_poll_jobs');
	}
});

\add_action('cmx_infrastruktur_poll_jobs', function (): void {
	foreach (\array_keys(cmx_infrastruktur_pending_jobs()) as $job_id) {
		cmx_infrastruktur_poll_job((string) $job_id);
	}
});

\add_action('wp_ajax_cmx_infrastruktur_instance_action', function (): void {
	$source_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
	if (!\current_user_can('edit_post', $source_id)) {
		\wp_send_json_error(['message' => __('Keine Berechtigung.', 'cmx-misbuero')], 403);
	}
	if (!\check_ajax_referer('cmx_infrastruktur_instance_action_' . $source_id, 'nonce', false)) {
		\wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen.', 'cmx-misbuero')], 403);
	}
	$input = [];
	foreach (['post_id', 'customer_name', 'domain', 'email', 'instance_action'] as $key) {
		$input[$key] = isset($_POST[$key]) ? \wp_unslash($_POST[$key]) : '';
	}
	$result = cmx_infrastruktur_submit_job($input);
	if (\is_wp_error($result)) {
		\wp_send_json_error(['message' => $result->get_error_message(), 'code' => $result->get_error_code()], 502);
	}
	\wp_send_json_success(['message' => __('Provisionierung wurde gestartet.', 'cmx-misbuero'), 'job' => $result, 'job_id' => (string) $result['id']]);
});

\add_action('wp_ajax_cmx_infrastruktur_instance_status', function (): void {
	$source_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
	if (!\current_user_can('edit_post', $source_id) || !\check_ajax_referer('cmx_infrastruktur_instance_action_' . $source_id, 'nonce', false)) {
		\wp_send_json_error(['message' => __('Keine Berechtigung.', 'cmx-misbuero')], 403);
	}
	$result = cmx_infrastruktur_poll_job((string) ($_POST['job_id'] ?? ''));
	if (\is_wp_error($result)) {
		\wp_send_json_error(['message' => $result->get_error_message()], 502);
	}
	\wp_send_json_success($result);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_rest_bearer_valid')) {
	function cmx_infrastruktur_rest_bearer_valid(\WP_REST_Request $request): bool {
		$token = cmx_infrastruktur_woocommerce_token();
		$header = \trim((string) $request->get_header('authorization'));
		return $token !== '' && \preg_match('/^Bearer\s+(.+)$/i', $header, $match) === 1 && \hash_equals($token, \trim((string) $match[1]));
	}
}

\add_action('rest_api_init', function (): void {
	$permission = function (\WP_REST_Request $request) {
		return cmx_infrastruktur_rest_bearer_valid($request)
			? true
			: new \WP_Error('cmx_instance_unauthorized', __('Keine Berechtigung.', 'cmx-misbuero'), ['status' => 401]);
	};
	\register_rest_route('cmx-misbuero/v1', '/wordpress-instances', [
		'methods' => \WP_REST_Server::CREATABLE,
		'permission_callback' => $permission,
		'callback' => function (\WP_REST_Request $request) {
			$params = (array) $request->get_json_params();
			$idempotency = \trim((string) ($request->get_header('idempotency-key') ?: ($params['idempotency_key'] ?? '')));
			$result = cmx_infrastruktur_submit_job($params, $idempotency);
			return \is_wp_error($result) ? $result : new \WP_REST_Response($result, 202);
		},
	]);
	\register_rest_route('cmx-misbuero/v1', '/wordpress-instances/jobs/(?P<job_id>[a-f0-9-]+)', [
		'methods' => \WP_REST_Server::READABLE,
		'permission_callback' => $permission,
		'callback' => function (\WP_REST_Request $request) {
			$result = cmx_infrastruktur_poll_job((string) $request['job_id']);
			return \is_wp_error($result) ? $result : new \WP_REST_Response($result, 200);
		},
	]);
});
