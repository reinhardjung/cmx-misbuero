<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_carnet_settings_option_name')) {
	function cmx_carnet_settings_option_name(): string {
		return \defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			: 'cmx_einstellungen';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carnet_pdf_option_key')) {
	function cmx_carnet_pdf_option_key(): string {
		return 'carnet_pdf_attachment_id';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carnet_client_option_key')) {
	function cmx_carnet_client_option_key(): string {
		return 'carnet_email_client_id';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carnet_settings_is_enabled')) {
	function cmx_carnet_settings_is_enabled(): bool {
		if (\function_exists(__NAMESPACE__ . '\\cmx_system_is_carent_enabled')) {
			return cmx_system_is_carent_enabled();
		}

		$options = (array) \get_option(cmx_carnet_settings_option_name(), []);
		$key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_CARENT_KEY')
			? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_CARENT_KEY')
			: 'carent';

		return !empty($options[$key]);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carnet_client_options')) {
	function cmx_carnet_client_options(): array {
		if (!\function_exists(__NAMESPACE__ . '\\cmx_email_client_list')) {
			return [];
		}

		$list = [];
		foreach ((array) cmx_email_client_list() as $client) {
			if (!\is_array($client)) {
				continue;
			}

			$id = \sanitize_key((string) ($client['id'] ?? ''));
			if ($id === '') {
				continue;
			}

			$name = \trim((string) ($client['name'] ?? ''));
			$email = \sanitize_email((string) ($client['email'] ?? ''));
			$label = $name !== '' && $email !== ''
				? $name . ' <' . $email . '>'
				: ($email !== '' ? $email : ($name !== '' ? $name : $id));

			$list[$id] = $label;
		}

		return $list;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carnet_current_pdf_attachment_id')) {
	function cmx_carnet_current_pdf_attachment_id(): int {
		$options = (array) \get_option(cmx_carnet_settings_option_name(), []);
		return isset($options[cmx_carnet_pdf_option_key()]) ? (int) $options[cmx_carnet_pdf_option_key()] : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carnet_current_client_id')) {
	function cmx_carnet_current_client_id(): string {
		$options = (array) \get_option(cmx_carnet_settings_option_name(), []);
		return \sanitize_key((string) ($options[cmx_carnet_client_option_key()] ?? ''));
	}
}

\add_action('admin_init', function (): void {
	if (!cmx_carnet_settings_is_enabled()) {
		return;
	}

	$page = 'cmx_tab_carnet';

	\add_settings_section(
		'cmx_sec_carnet',
		'',
		'__return_false',
		$page
	);

	\add_settings_field(
		'cmx_carnet_pdf_attachment_id',
		'PDF',
		function (): void {
			$option_name = cmx_carnet_settings_option_name();
			$key = cmx_carnet_pdf_option_key();
			$attachment_id = cmx_carnet_current_pdf_attachment_id();
			$file_url = $attachment_id > 0 ? (string) \wp_get_attachment_url($attachment_id) : '';
			$file_name = $attachment_id > 0 ? (string) \basename((string) \get_attached_file($attachment_id)) : '';

			echo '<input type="hidden" id="cmx-carnet-pdf-attachment-id" name="' . \esc_attr($option_name . '[' . $key . ']') . '" value="' . \esc_attr((string) $attachment_id) . '">';
			echo '<button type="button" class="button" id="cmx-carnet-pdf-upload-button">PDF auswählen</button>';
			echo '<p class="description" id="cmx-carnet-pdf-current">';
			if ($file_url !== '' && $file_name !== '') {
				echo '<a href="' . \esc_url($file_url) . '" target="_blank" rel="noopener noreferrer">' . \esc_html($file_name) . '</a>';
			} else {
				echo 'Kein PDF ausgewählt.';
			}
			echo '</p>';
		},
		$page,
		'cmx_sec_carnet'
	);

	\add_settings_field(
		'cmx_carnet_email_client_id',
		'E-Mail Client',
		function (): void {
			$option_name = cmx_carnet_settings_option_name();
			$key = cmx_carnet_client_option_key();
			$current = cmx_carnet_current_client_id();
			$clients = cmx_carnet_client_options();

			echo '<select id="cmx-carnet-email-client-id" name="' . \esc_attr($option_name . '[' . $key . ']') . '"' . ($clients === [] ? ' disabled' : '') . '>';
			echo '<option value=""></option>';
			foreach ($clients as $client_id => $label) {
				echo '<option value="' . \esc_attr((string) $client_id) . '"' . \selected($current, (string) $client_id, false) . '>' . \esc_html((string) $label) . '</option>';
			}
			echo '</select>';

			if ($clients === []) {
				echo '<p class="description">Keine E-Mail Clients vorhanden.</p>';
			}
		},
		$page,
		'cmx_sec_carnet'
	);
});

\add_action('admin_enqueue_scripts', function (): void {
	$page = isset($_GET['page']) ? \sanitize_key((string) \wp_unslash($_GET['page'])) : '';
	$tab = isset($_GET['tab']) ? \sanitize_key((string) \wp_unslash($_GET['tab'])) : '';
	$settings_page = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
		? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
		: 'cmx-einstellungen';
	if ($page !== $settings_page || $tab !== 'carnet' || !cmx_carnet_settings_is_enabled()) {
		return;
	}

	\wp_enqueue_media();
});

\add_action('admin_footer', function (): void {
	$page = isset($_GET['page']) ? \sanitize_key((string) \wp_unslash($_GET['page'])) : '';
	$tab = isset($_GET['tab']) ? \sanitize_key((string) \wp_unslash($_GET['tab'])) : '';
	$settings_page = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
		? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
		: 'cmx-einstellungen';
	if ($page !== $settings_page || $tab !== 'carnet' || !cmx_carnet_settings_is_enabled()) {
		return;
	}
	?>
	<script>
	(function(){
		var button = document.getElementById('cmx-carnet-pdf-upload-button');
		var input = document.getElementById('cmx-carnet-pdf-attachment-id');
		var current = document.getElementById('cmx-carnet-pdf-current');
		if (!button || !input || !current || !window.wp || !wp.media) {
			return;
		}

		var frame;

		function escapeHtml(value) {
			return String(value || '').replace(/[&<>"']/g, function(char) {
				return {
					'&': '&amp;',
					'<': '&lt;',
					'>': '&gt;',
					'"': '&quot;',
					"'": '&#039;'
				}[char] || char;
			});
		}

		button.addEventListener('click', function(event) {
			event.preventDefault();

			if (!frame) {
				frame = wp.media({
					title: 'PDF auswählen',
					button: {
						text: 'PDF übernehmen'
					},
					library: {
						type: 'application/pdf'
					},
					multiple: false
				});

				frame.on('open', function() {
					try {
						if (frame.content && typeof frame.content.mode === 'function') {
							frame.content.mode('upload');
						}
					} catch (error) {}
				});

				frame.on('select', function() {
					var attachment = frame.state().get('selection').first().toJSON();
					var attachmentId = attachment && attachment.id ? String(attachment.id) : '';
					var url = attachment && attachment.url ? String(attachment.url) : '';
					var filename = attachment && attachment.filename ? String(attachment.filename) : '';

					input.value = attachmentId;
					if (url && filename) {
						current.innerHTML = '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(filename) + '</a>';
						return;
					}
					current.textContent = filename || 'Kein PDF ausgewählt.';
				});
			}

			frame.open();
		});
	})();
	</script>
	<?php
});

\add_filter('pre_update_option_' . CMX_SETTINGS_MAIN, function ($value, $old_value) {
	$value = \is_array($value) ? $value : [];
	$old_value = \is_array($old_value) ? $old_value : [];

	$pdf_key = cmx_carnet_pdf_option_key();
	$client_key = cmx_carnet_client_option_key();

	if (\array_key_exists($pdf_key, $value)) {
		$attachment_id = (int) $value[$pdf_key];
		if ($attachment_id > 0) {
			$is_attachment = \get_post_type($attachment_id) === 'attachment';
			$mime_type = (string) \get_post_mime_type($attachment_id);
			$value[$pdf_key] = ($is_attachment && $mime_type === 'application/pdf') ? $attachment_id : 0;
		} else {
			$value[$pdf_key] = 0;
		}
	} elseif (isset($old_value[$pdf_key])) {
		$value[$pdf_key] = (int) $old_value[$pdf_key];
	}

	if (\array_key_exists($client_key, $value)) {
		$client_id = \sanitize_key((string) $value[$client_key]);
		$allowed_ids = \array_keys(cmx_carnet_client_options());
		$value[$client_key] = \in_array($client_id, $allowed_ids, true) ? $client_id : '';
	} elseif (isset($old_value[$client_key])) {
		$value[$client_key] = \sanitize_key((string) $old_value[$client_key]);
	}

	return $value;
}, 20, 2);
