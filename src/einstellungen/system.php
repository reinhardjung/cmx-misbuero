<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

\add_action('wp_ajax_cmx_test_nextcloud_url', __NAMESPACE__ . '\\cmx_test_nextcloud_url_ajax');
function cmx_test_nextcloud_url_ajax(): void {
	if (!\current_user_can('manage_options')) {
		\wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
	}

	\check_ajax_referer('cmx_test_nextcloud_url', 'nonce');

	$url = isset($_POST['url']) ? \sanitize_text_field(\wp_unslash((string) $_POST['url'])) : '';
	$result = cmx_test_nextcloud_url($url);

	if ($result['ok']) {
		\wp_send_json_success([
			'message' => $result['message'],
			'data'    => $result['data'],
		]);
	}

	\wp_send_json_error([
		'message' => $result['message'],
		'data'    => $result['data'],
	], $result['status']);
}

function cmx_test_nextcloud_url(string $url): array {
	$url = \trim($url);
	if ($url === '') {
		return [
			'ok'      => false,
			'status'  => 400,
			'message' => 'Bitte zuerst eine URL eintragen.',
			'data'    => [],
		];
	}

	if (!\preg_match('~^https?://~i', $url)) {
		$url = 'https://' . $url;
	}

	$base_url = \esc_url_raw(\untrailingslashit($url));
	if ($base_url === '' || !\wp_http_validate_url($base_url)) {
		return [
			'ok'      => false,
			'status'  => 400,
			'message' => 'Die URL ist ungültig.',
			'data'    => [],
		];
	}

	$status_url = $base_url . '/status.php';
	$response = \wp_remote_get($status_url, [
		'timeout'     => 10,
		'redirection' => 3,
		'headers'     => [
			'Accept' => 'application/json',
		],
	]);

	if (\is_wp_error($response)) {
		return [
			'ok'      => false,
			'status'  => 502,
			'message' => 'Abruf fehlgeschlagen: ' . $response->get_error_message(),
			'data'    => ['status_url' => $status_url],
		];
	}

	$http_code = (int) \wp_remote_retrieve_response_code($response);
	$body = (string) \wp_remote_retrieve_body($response);
	$data = \json_decode($body, true);
	$is_valid = \is_array($data)
		&& \array_key_exists('installed', $data)
		&& \array_key_exists('maintenance', $data)
		&& \array_key_exists('needsDbUpgrade', $data)
		&& (\array_key_exists('versionstring', $data) || \array_key_exists('version', $data));

	if ($http_code !== 200 || !$is_valid) {
		return [
			'ok'      => false,
			'status'  => $http_code > 0 ? $http_code : 400,
			'message' => 'Keine Nextcloud-Statusantwort erkannt.',
			'data'    => [
				'status_url' => $status_url,
				'http_code'  => $http_code,
				'body'       => \is_string($body) ? \mb_substr(\trim(\wp_strip_all_tags($body)), 0, 220) : '',
			],
		];
	}

	$product = isset($data['productname']) ? (string) $data['productname'] : 'Nextcloud';
	$version = isset($data['versionstring']) ? (string) $data['versionstring'] : ((string) ($data['version'] ?? ''));
	$maintenance = !empty($data['maintenance']) ? 'Wartungsmodus aktiv' : 'bereit';
	$installed = !empty($data['installed']) ? 'installiert' : 'noch nicht installiert';

	return [
		'ok'      => true,
		'status'  => 200,
		'message' => \trim($product . ' erkannt' . ($version !== '' ? ' (' . $version . ')' : '') . ' - ' . $installed . ', ' . $maintenance),
		'data'    => [
			'status_url' => $status_url,
			'http_code'  => $http_code,
			'product'    => $product,
			'version'    => $version,
		],
	];
}

function cmx_normalize_nextcloud_chat_room_id($value): string {
	$value = \trim(\wp_unslash((string) $value));
	if ($value === '') {
		return '';
	}

	$candidate = \preg_replace('~[\\r\\n]+~', '', $value);
	if (!\is_string($candidate)) {
		$candidate = $value;
	}
	$candidate = \trim($candidate);
	$candidate = \preg_replace('~/+$~', '', $candidate);
	if (!\is_string($candidate)) {
		$candidate = $value;
	}

	if (\preg_match('~^https?://~i', $candidate)) {
		$path = (string) (\wp_parse_url($candidate, \PHP_URL_PATH) ?? '');
		if ($path !== '') {
			$parts = \array_values(\array_filter(\array_map('trim', \explode('/', $path))));
			if ($parts !== []) {
				$candidate = \rawurldecode((string) \end($parts));
			}
		}
	} elseif (\strpos($candidate, '/') !== false) {
		$parts = \array_values(\array_filter(\array_map('trim', \explode('/', $candidate))));
		if ($parts !== []) {
			$candidate = (string) \end($parts);
		}
	}

	return \sanitize_text_field(\trim($candidate));
}

\add_action('admin_init', __NAMESPACE__ . '\\cmx_register_system_tab');
function cmx_register_system_tab(): void {
	\add_settings_section(
		'cmx_sec_system',
		__('System', 'default'),
		'__return_false',
		'cmx_tab_system'
	);

	\register_setting(
		'cmx_einstellungen',
		'mis_buero_nextcloud_url',
		[
			'type'              => 'string',
			'sanitize_callback' => static function ($value): string {
				if ($value === null) {
					$value = \get_option('mis_buero_nextcloud_url', '');
				}
				return \esc_url_raw((string) $value);
			},
		]
	);

	\register_setting(
		'cmx_einstellungen',
		'mis_buero_nextcloud_chat_room',
		[
			'type'              => 'string',
			'sanitize_callback' => static function ($value): string {
				if ($value === null) {
					$value = \get_option('mis_buero_nextcloud_chat_room', '');
				}
				return cmx_normalize_nextcloud_chat_room_id($value);
			},
		]
	);

	\add_settings_field(
		'mis_buero_nextcloud',
		'Nextcloud',
		function (): void {
			$url = (string) \get_option('mis_buero_nextcloud_url', '');
			$chat_room = (string) \get_option('mis_buero_nextcloud_chat_room', '');
			$host = (string) (\wp_parse_url(\home_url('/'), \PHP_URL_HOST) ?? '');
			$instance = '';
			if ($host !== '') {
				$parts = \explode('.', \strtolower($host));
				$instance = \sanitize_title((string) ($parts[0] ?? ''));
			}
			if ($instance === '' && \defined('CMX_DOMAIN')) {
				$instance = \sanitize_title((string) \constant('CMX_DOMAIN'));
			}
			$placeholder = 'https://' . ($instance !== '' ? $instance : '{DeineInstanz}') . '.misbuero.cloud';
			$nonce = \wp_create_nonce('cmx_test_nextcloud_url');
			echo '<div style="display:flex;flex-direction:column;gap:18px;max-width:960px;">';
			echo '<label style="display:flex;flex-direction:column;gap:4px;">';
			echo '<span>URL</span>';
			echo '<div style="display:flex;align-items:center;gap:8px;">';
			echo '<input type="url" name="mis_buero_nextcloud_url" class="regular-text" value="' . \esc_attr($url) . '" placeholder="' . \esc_attr($placeholder) . '" id="cmx-nextcloud-url">';
			echo '<button type="button" class="button button-secondary" id="cmx-nextcloud-test" data-nonce="' . \esc_attr($nonce) . '">Prüfen</button>';
			echo '<span class="spinner" id="cmx-nextcloud-test-spinner" style="float:none;margin:0;"></span>';
			echo '</div>';
			echo '<span class="description" id="cmx-nextcloud-test-result" style="display:block;min-height:20px;padding-top:2px;white-space:nowrap;overflow-x:auto;overflow-y:hidden;"></span>';
			echo '</label>';
			echo '<label style="display:flex;flex-direction:column;gap:6px;padding-bottom:6px;">';
			echo '<span>Chat Room ID {token}</span>';
			echo '<div style="display:flex;flex-direction:column;align-items:flex-start;gap:4px;">';
			echo '<input type="text" name="mis_buero_nextcloud_chat_room" class="regular-text" value="' . \esc_attr($chat_room) . '" id="cmx-nextcloud-chat-room" placeholder="9bidw5t8">';
			echo '<a class="description" id="cmx-nextcloud-chat-room-link" href="#" target="_blank" rel="noopener noreferrer" style="' . ($url !== '' && $chat_room !== '' ? 'display:block;min-height:18px;' : 'display:block;min-height:18px;visibility:hidden;') . '"></a>';
			echo '</div>';
			echo '</label>';
			echo '</div>';
			?>
			<script>
			document.addEventListener('DOMContentLoaded', function () {
				const button = document.getElementById('cmx-nextcloud-test');
				const input = document.getElementById('cmx-nextcloud-url');
				const chatRoomInput = document.getElementById('cmx-nextcloud-chat-room');
				const chatRoomLink = document.getElementById('cmx-nextcloud-chat-room-link');
				const result = document.getElementById('cmx-nextcloud-test-result');
				const spinner = document.getElementById('cmx-nextcloud-test-spinner');
				if (!button || !input || !chatRoomInput || !chatRoomLink || !result || !spinner || typeof ajaxurl === 'undefined') {
					return;
				}
				const normalizeBaseUrl = function (value) {
					return (value || '').trim().replace(/\/+$/, '');
				};
				const normalizeChatRoomId = function (value) {
					let raw = (value || '').trim().replace(/\/+$/, '');
					if (!raw) {
						return '';
					}
					if (/^https?:\/\//i.test(raw)) {
						try {
							const parsed = new URL(raw);
							const parts = (parsed.pathname || '').split('/').map(function (part) {
								return part.trim();
							}).filter(Boolean);
							if (parts.length) {
								raw = decodeURIComponent(parts[parts.length - 1] || '');
							}
						} catch (error) {}
					} else if (raw.indexOf('/') !== -1) {
						const parts = raw.split('/').map(function (part) {
							return part.trim();
						}).filter(Boolean);
						if (parts.length) {
							raw = parts[parts.length - 1] || raw;
						}
					}
					return raw.trim();
				};
				const updateChatRoomPreview = function () {
					const roomId = normalizeChatRoomId(chatRoomInput.value || '');
					const baseUrl = normalizeBaseUrl(input.value || '');
					try {
						if (baseUrl) {
							window.localStorage.setItem('cmx_nextcloud_url', baseUrl);
						} else {
							window.localStorage.removeItem('cmx_nextcloud_url');
						}
						if (roomId) {
							window.localStorage.setItem('cmx_nextcloud_chat_room', roomId);
						} else {
							window.localStorage.removeItem('cmx_nextcloud_chat_room');
						}
					} catch (error) {}
					if (!baseUrl || !roomId) {
						chatRoomLink.style.visibility = 'hidden';
						chatRoomLink.textContent = '';
						chatRoomLink.removeAttribute('href');
						return;
					}
					const linkUrl = baseUrl + '/index.php/call/' + encodeURIComponent(roomId);
					chatRoomLink.href = linkUrl;
					chatRoomLink.textContent = linkUrl;
					chatRoomLink.style.visibility = 'visible';
				};
				const setResult = function (message, state) {
					result.textContent = message;
					if (state === 'error') {
						result.style.color = '#b32d2e';
						return;
					}
					if (state === 'success') {
						result.style.color = '#2f6f3e';
						return;
					}
					result.style.color = '#2271b1';
				};
				input.addEventListener('input', updateChatRoomPreview);
				chatRoomInput.addEventListener('input', updateChatRoomPreview);
				chatRoomInput.addEventListener('blur', function () {
					chatRoomInput.value = normalizeChatRoomId(chatRoomInput.value || '');
					updateChatRoomPreview();
				});
				chatRoomInput.value = normalizeChatRoomId(chatRoomInput.value || '');
				updateChatRoomPreview();
				button.addEventListener('click', function () {
					const url = (input.value || '').trim();
					const form = new URLSearchParams();
					form.set('action', 'cmx_test_nextcloud_url');
					form.set('nonce', button.getAttribute('data-nonce') || '');
					form.set('url', url);
					button.disabled = true;
					spinner.classList.add('is-active');
					setResult('Prüfe Nextcloud ...', 'info');
					fetch(ajaxurl, {
						method: 'POST',
						credentials: 'same-origin',
						headers: {'Content-Type': 'application/x-www-form-urlencoded'},
						body: form.toString()
					}).then(function (response) {
						return response.json().catch(function () {
							return {success: false, data: {message: 'Ungültige Serverantwort.'}};
						});
					}).then(function (payload) {
						const message = payload && payload.data && payload.data.message ? payload.data.message : 'Prüfung fehlgeschlagen.';
						setResult(message, payload && payload.success ? 'success' : 'error');
					}).catch(function (error) {
						setResult(error && error.message ? error.message : 'Prüfung fehlgeschlagen.', 'error');
					}).finally(function () {
						button.disabled = false;
						spinner.classList.remove('is-active');
					});
				});
			});
			</script>
			<?php
		},
		'cmx_tab_system',
		'cmx_sec_system'
	);

	\register_setting(
		'cmx_einstellungen',
		'mis_buero_openai_key',
		[
			'type'              => 'string',
			'sanitize_callback' => static function ($value): string {
				if ($value === null) {
					$value = \get_option('mis_buero_openai_key', '');
				}
				return \sanitize_text_field((string) $value);
			},
		]
	);

	\add_settings_field(
		'mis_buero_openai_key',
		'Paddle API Key',
		function (): void {
			$val = (string) \get_option('mis_buero_openai_key', '');
			echo '<input type="text" name="mis_buero_openai_key" class="regular-text" value="' . \esc_attr($val) . '">';
			echo '<p class="description">Wird für OCR und Produkttexte verwendet</p>';
		},
		'cmx_tab_system',
		'cmx_sec_system'
	);

	if (\function_exists(__NAMESPACE__ . '\\cmx_system_is_cloudmeister_user') && cmx_system_is_cloudmeister_user()) {
		\add_settings_field(
			'cmx_system_max_workplaces',
			'Max. Arbeitsplätze',
			function (): void {
				$option_name = \function_exists(__NAMESPACE__ . '\\cmx_system_settings_option_name')
					? (string) cmx_system_settings_option_name()
					: 'cmx_einstellungen';
				$key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_MAX_WORKPLACES_KEY')
					? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_MAX_WORKPLACES_KEY')
					: 'max_workplaces';
				$options = (array) \get_option($option_name, []);
				$value = isset($options[$key]) ? (int) $options[$key] : 1;
				if ($value <= 0) {
					$value = 1;
				}
				echo '<input type="number" min="1" step="1" name="' . \esc_attr($option_name) . '[' . \esc_attr($key) . ']" value="' . \esc_attr((string) $value) . '" class="small-text">';
			},
			'cmx_tab_system',
			'cmx_sec_system'
		);
	}
}

\add_filter('pre_update_option_' . 'cmx_einstellungen', function ($value, $old_value) {
	$value = \is_array($value) ? $value : [];
	$old_value = \is_array($old_value) ? $old_value : [];
	$key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_MAX_WORKPLACES_KEY')
		? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_MAX_WORKPLACES_KEY')
		: 'max_workplaces';

	if (\function_exists(__NAMESPACE__ . '\\cmx_system_is_cloudmeister_user') && !cmx_system_is_cloudmeister_user()) {
		$value[$key] = isset($old_value[$key]) ? (int) $old_value[$key] : 1;
		return $value;
	}

	$max = isset($value[$key]) ? (int) $value[$key] : (isset($old_value[$key]) ? (int) $old_value[$key] : 1);
	$value[$key] = $max > 0 ? $max : 1;
	return $value;
}, 10, 2);
