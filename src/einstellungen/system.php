<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

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
				return \sanitize_text_field((string) $value);
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
			echo '<div style="display:flex;flex-direction:column;gap:10px;max-width:420px;">';
			echo '<label style="display:flex;flex-direction:column;gap:4px;">';
			echo '<span>URL</span>';
			echo '<input type="url" name="mis_buero_nextcloud_url" class="regular-text" value="' . \esc_attr($url) . '" placeholder="' . \esc_attr($placeholder) . '">';
			echo '</label>';
			echo '<label style="display:flex;flex-direction:column;gap:4px;">';
			echo '<span>Chat Room ID</span>';
			echo '<div style="display:flex;align-items:center;gap:8px;">';
			echo '<input type="text" name="mis_buero_nextcloud_chat_room" class="regular-text" value="' . \esc_attr($chat_room) . '">';
			echo '<span class="description">9bidw5t8</span>';
			echo '</div>';
			echo '</label>';
			echo '</div>';
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
