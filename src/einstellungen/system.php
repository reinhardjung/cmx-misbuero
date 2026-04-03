<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_render_copyable_instance_link')) {
	function cmx_system_render_copyable_instance_link(string $base_id, string $url, string $open_label): void {
		$base_id = \sanitize_key($base_id);
		if ($base_id === '') {
			$base_id = 'cmx-system-link';
		}

		$copy_label = 'Link in Zwischenablage kopieren';
		$status_id = $base_id . '-status';
		$copy_id = $base_id . '-copy';
		$link_id = $base_id . '-link';

		echo '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">';
		echo '<button type="button" class="button button-secondary" id="' . \esc_attr($copy_id) . '" aria-label="' . \esc_attr($copy_label) . '" title="' . \esc_attr($copy_label) . '" data-copy-url="' . \esc_attr($url) . '" style="display:inline-flex;align-items:center;justify-content:center;min-width:38px;padding:0 10px;">';
		echo '<span class="dashicons dashicons-clipboard" aria-hidden="true" style="margin-top:2px;"></span>';
		echo '</button>';
		echo '<a id="' . \esc_attr($link_id) . '" href="' . \esc_url($url) . '" target="_blank" rel="noopener noreferrer" data-copy-url="' . \esc_attr($url) . '" title="' . \esc_attr($open_label) . '">' . \esc_html($url) . '</a>';
		echo '<span class="description" id="' . \esc_attr($status_id) . '" aria-live="polite" style="min-height:18px;"></span>';
		echo '</div>';
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function () {
			const copyButton = document.getElementById('<?php echo \esc_js($copy_id); ?>');
			const link = document.getElementById('<?php echo \esc_js($link_id); ?>');
			const status = document.getElementById('<?php echo \esc_js($status_id); ?>');
			if (!copyButton || !link || !status) {
				return;
			}

			let resetTimer = null;
			const setStatus = function (message, isError) {
				status.textContent = message || '';
				status.style.color = isError ? '#b32d2e' : '#2271b1';
				if (resetTimer) {
					window.clearTimeout(resetTimer);
				}
				if (message) {
					resetTimer = window.setTimeout(function () {
						status.textContent = '';
					}, 1800);
				}
			};

			const copyFallback = function (text) {
				const input = document.createElement('textarea');
				input.value = text;
				input.setAttribute('readonly', 'readonly');
				input.style.position = 'fixed';
				input.style.opacity = '0';
				document.body.appendChild(input);
				input.select();
				input.setSelectionRange(0, input.value.length);
				let ok = false;
				try {
					ok = document.execCommand('copy');
				} catch (error) {
					ok = false;
				}
				document.body.removeChild(input);
				return ok;
			};

			const copyUrl = function (text) {
				if (!text) {
					setStatus('Link fehlt.', true);
					return;
				}
				if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function' && window.isSecureContext) {
					navigator.clipboard.writeText(text).then(function () {
						setStatus('Link kopiert.', false);
					}).catch(function () {
						if (copyFallback(text)) {
							setStatus('Link kopiert.', false);
							return;
						}
						setStatus('Link konnte nicht kopiert werden.', true);
					});
					return;
				}
				if (copyFallback(text)) {
					setStatus('Link kopiert.', false);
					return;
				}
				setStatus('Link konnte nicht kopiert werden.', true);
			};

			copyButton.addEventListener('click', function () {
				copyUrl(copyButton.getAttribute('data-copy-url') || '');
			});

			link.addEventListener('click', function () {
				copyUrl(link.getAttribute('data-copy-url') || link.href || '');
			});
		});
		</script>
		<?php
	}
}

\add_action('admin_init', __NAMESPACE__ . '\\cmx_register_system_tab');
function cmx_register_system_tab(): void {
	\add_settings_section(
		'cmx_sec_system',
		__('System', 'default'),
		'__return_false',
		'cmx_tab_system'
	);

	\add_settings_field(
		'cmx_system_dokuscan',
		'DokuScan (WebDAV)',
		function (): void {
			cmx_system_render_copyable_instance_link(
				'cmx-system-dokuscan',
				(string) \home_url('/scanner/'),
				'DokuScan in neuem Tab öffnen und Link kopieren'
			);
		},
		'cmx_tab_system',
		'cmx_sec_system'
	);

	\add_settings_field(
		'cmx_system_archiv',
		'Archiv',
		function (): void {
			cmx_system_render_copyable_instance_link(
				'cmx-system-archiv',
				(string) \home_url('/archiv/'),
				'Archiv in neuem Tab öffnen und Link kopieren'
			);
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

	\add_settings_field(
		'cmx_system_debug_mode',
		'Debug-Mode',
		__NAMESPACE__ . '\\cmx_field_checkbox',
		'cmx_tab_system',
		'cmx_sec_system',
		[
			'key'   => \defined(__NAMESPACE__ . '\\CMX_SYSTEM_DEBUG_MODE_KEY')
				? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_DEBUG_MODE_KEY')
				: 'debug_mode',
			'label' => 'Debug-Modus aktivieren',
		]
	);

	if (\function_exists(__NAMESPACE__ . '\\cmx_system_is_cloudmeister_user') && cmx_system_is_cloudmeister_user()) {
		\add_settings_section(
			'cmx_sec_modules',
			'Module',
			'__return_false',
			'cmx_tab_system'
		);

		\add_settings_field(
			'cmx_system_pro_version',
			'PRO Version',
			function (): void {
				$option_name = \function_exists(__NAMESPACE__ . '\\cmx_system_settings_option_name')
					? (string) cmx_system_settings_option_name()
					: 'cmx_einstellungen';
				$key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_PRO_VERSION_KEY')
					? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_PRO_VERSION_KEY')
					: 'pro_version';
				$options = (array) \get_option($option_name, []);
				$checked = !empty($options[$key]);

				echo '<label>';
				echo '<input type="hidden" name="' . \esc_attr($option_name) . '[' . \esc_attr($key) . ']" value="0">';
				echo '<input type="checkbox" name="' . \esc_attr($option_name) . '[' . \esc_attr($key) . ']" value="1"' . \checked($checked, true, false) . '> ';
				echo 'E-Mail Client, Termine, VideoCalls';
				echo '</label>';
			},
			'cmx_tab_system',
			'cmx_sec_system'
		);

		\add_settings_field(
			'cmx_system_carent',
			'Carent',
			function (): void {
				$option_name = \function_exists(__NAMESPACE__ . '\\cmx_system_settings_option_name')
					? (string) cmx_system_settings_option_name()
					: 'cmx_einstellungen';
				$key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_CARENT_KEY')
					? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_CARENT_KEY')
					: 'carent';
				$options = (array) \get_option($option_name, []);
				$checked = !empty($options[$key]);

				echo '<label>';
				echo '<input type="hidden" name="' . \esc_attr($option_name) . '[' . \esc_attr($key) . ']" value="0">';
				echo '<input type="checkbox" name="' . \esc_attr($option_name) . '[' . \esc_attr($key) . ']" value="1"' . \checked($checked, true, false) . '> ';
				echo \esc_html__('CaRent aktivieren', 'cmx-misbuero');
				echo '</label>';
			},
			'cmx_tab_system',
			'cmx_sec_modules'
		);

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
	$pro_key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_PRO_VERSION_KEY')
		? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_PRO_VERSION_KEY')
		: 'pro_version';
	$carent_key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_CARENT_KEY')
		? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_CARENT_KEY')
		: 'carent';
	$debug_key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_DEBUG_MODE_KEY')
		? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_DEBUG_MODE_KEY')
		: 'debug_mode';

	if (\function_exists(__NAMESPACE__ . '\\cmx_system_is_cloudmeister_user') && !cmx_system_is_cloudmeister_user()) {
		$value[$key] = isset($old_value[$key]) ? (int) $old_value[$key] : 1;
		$value[$pro_key] = !empty($old_value[$pro_key]) ? '1' : '0';
		$value[$carent_key] = !empty($old_value[$carent_key]) ? '1' : '0';
		$value[$debug_key] = !empty($value[$debug_key]) ? '1' : '0';
		return $value;
	}

	$max = isset($value[$key]) ? (int) $value[$key] : (isset($old_value[$key]) ? (int) $old_value[$key] : 1);
	$value[$key] = $max > 0 ? $max : 1;
	$value[$pro_key] = !empty($value[$pro_key]) ? '1' : '0';
	$value[$carent_key] = !empty($value[$carent_key]) ? '1' : '0';
	$value[$debug_key] = !empty($value[$debug_key]) ? '1' : '0';
	return $value;
}, 10, 2);
