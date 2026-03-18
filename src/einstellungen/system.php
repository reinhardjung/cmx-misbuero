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
