<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_settings_option_name')) {
	function cmx_carent_settings_option_name(): string {
		return \defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			: 'cmx_einstellungen';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_pdf_option_key')) {
	function cmx_carent_pdf_option_key(): string {
		return 'carent_pdf_file_rel';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_pdf_legacy_option_key')) {
	function cmx_carent_pdf_legacy_option_key(): string {
		return 'carent_pdf_attachment_id';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_pdf_upload_field_name')) {
	function cmx_carent_pdf_upload_field_name(): string {
		return 'cmx_carent_pdf_file';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_client_option_key')) {
	function cmx_carent_client_option_key(): string {
		return 'carent_email_client_id';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_settings_is_enabled')) {
	function cmx_carent_settings_is_enabled(): bool {
		if (\function_exists(__NAMESPACE__ . '\\cmx_system_is_carent_enabled')) {
			return cmx_system_is_carent_enabled();
		}

		$options = (array) \get_option(cmx_carent_settings_option_name(), []);
		$key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_CARENT_KEY')
			? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_CARENT_KEY')
			: 'carent';

		return !empty($options[$key]);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_client_options')) {
	function cmx_carent_client_options(): array {
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_current_pdf_rel')) {
	function cmx_carent_current_pdf_rel(): string {
		$options = (array) \get_option(cmx_carent_settings_option_name(), []);
		return \trim((string) ($options[cmx_carent_pdf_option_key()] ?? ''));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_current_pdf_legacy_attachment_id')) {
	function cmx_carent_current_pdf_legacy_attachment_id(): int {
		$options = (array) \get_option(cmx_carent_settings_option_name(), []);
		return isset($options[cmx_carent_pdf_legacy_option_key()]) ? (int) $options[cmx_carent_pdf_legacy_option_key()] : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_current_pdf_url')) {
	function cmx_carent_current_pdf_url(): string {
		$file_rel = cmx_carent_current_pdf_rel();
		if ($file_rel !== '') {
			$uploads_root = \trailingslashit(\wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads')));
			$file_abs = \wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads/' . ltrim($file_rel, '/')));
			if ($file_abs !== '' && \str_starts_with($file_abs, $uploads_root) && \is_file($file_abs)) {
				return (string) \content_url('/uploads/' . ltrim($file_rel, '/'));
			}
		}

		$attachment_id = cmx_carent_current_pdf_legacy_attachment_id();
		if ($attachment_id > 0) {
			return (string) \wp_get_attachment_url($attachment_id);
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_current_pdf_name')) {
	function cmx_carent_current_pdf_name(): string {
		$file_rel = cmx_carent_current_pdf_rel();
		if ($file_rel !== '') {
			return (string) \basename($file_rel);
		}

		$attachment_id = cmx_carent_current_pdf_legacy_attachment_id();
		if ($attachment_id > 0) {
			return (string) \basename((string) \get_attached_file($attachment_id));
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_current_client_id')) {
	function cmx_carent_current_client_id(): string {
		$options = (array) \get_option(cmx_carent_settings_option_name(), []);
		return \sanitize_key((string) ($options[cmx_carent_client_option_key()] ?? ''));
	}
}

\add_action('admin_init', function (): void {
	if (!cmx_carent_settings_is_enabled()) {
		return;
	}

	$page = 'cmx_tab_carent';

	\add_settings_section(
		'cmx_sec_carent',
		'',
		'__return_false',
		$page
	);

	\add_settings_field(
		'cmx_carent_pdf_attachment_id',
		'Deine AGB als PDF',
		function (): void {
			$file_url = cmx_carent_current_pdf_url();
			$file_name = cmx_carent_current_pdf_name();

			echo '<input type="file" id="cmx-carent-pdf-file" name="' . \esc_attr(cmx_carent_pdf_upload_field_name()) . '" accept=".pdf,application/pdf">';
			echo '<p class="description" id="cmx-carent-pdf-current">';
			if ($file_url !== '' && $file_name !== '') {
				echo '<a href="' . \esc_url($file_url) . '" target="_blank" rel="noopener noreferrer">' . \esc_html($file_name) . '</a>';
			} else {
				echo 'Noch kein PDF ausgewählt.';
			}
			echo '</p>';
		},
		$page,
		'cmx_sec_carent'
	);

	\add_settings_field(
		'cmx_carent_email_client_id',
		'E-Mail Client',
		function (): void {
			$option_name = cmx_carent_settings_option_name();
			$key = cmx_carent_client_option_key();
			$current = cmx_carent_current_client_id();
			$clients = cmx_carent_client_options();

			echo '<select id="cmx-carent-email-client-id" name="' . \esc_attr($option_name . '[' . $key . ']') . '"' . ($clients === [] ? ' disabled' : '') . '>';
			echo '<option value="">- Mailkonto auswählen -</option>';
			foreach ($clients as $client_id => $label) {
				echo '<option value="' . \esc_attr((string) $client_id) . '"' . \selected($current, (string) $client_id, false) . '>' . \esc_html((string) $label) . '</option>';
			}
			echo '</select>';

			if ($clients === []) {
				echo '<p class="description">Keine E-Mail Clients vorhanden.</p>';
			}
		},
		$page,
		'cmx_sec_carent'
	);
});

\add_filter('pre_update_option_' . CMX_SETTINGS_MAIN, function ($value, $old_value) {
	$value = \is_array($value) ? $value : [];
	$old_value = \is_array($old_value) ? $old_value : [];

	$pdf_key = cmx_carent_pdf_option_key();
	$pdf_legacy_key = cmx_carent_pdf_legacy_option_key();
	$client_key = cmx_carent_client_option_key();

	$uploaded_file = $_FILES[cmx_carent_pdf_upload_field_name()] ?? null;
	if (\is_array($uploaded_file) && !empty($uploaded_file['name'])) {
		$upload_dir_filter = static function (array $dirs): array {
			$subdir = '/misbuero/carent';
			$dirs['subdir'] = $subdir;
			$dirs['path'] = (string) (($dirs['basedir'] ?? '') . $subdir);
			$dirs['url'] = (string) (($dirs['baseurl'] ?? '') . $subdir);
			return $dirs;
		};

		if (!\function_exists('wp_handle_upload')) {
			require_once \ABSPATH . 'wp-admin/includes/file.php';
		}

		\add_filter('upload_dir', $upload_dir_filter);
		$uploaded = \wp_handle_upload($uploaded_file, [
			'test_form' => false,
			'mimes'     => ['pdf' => 'application/pdf'],
		]);
		\remove_filter('upload_dir', $upload_dir_filter);

		if (\is_array($uploaded) && empty($uploaded['error']) && !empty($uploaded['file'])) {
			$uploads_root = \trailingslashit(\wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads')));
			$file_abs = \wp_normalize_path((string) $uploaded['file']);
			if ($file_abs !== '' && \str_starts_with($file_abs, $uploads_root)) {
				$value[$pdf_key] = \ltrim(\str_replace($uploads_root, '', $file_abs), '/');
				$value[$pdf_legacy_key] = 0;
			}
		}
	} elseif (isset($old_value[$pdf_key])) {
		$value[$pdf_key] = \trim((string) $old_value[$pdf_key]);
	}

	if (!isset($value[$pdf_legacy_key]) && isset($old_value[$pdf_legacy_key])) {
		$value[$pdf_legacy_key] = (int) $old_value[$pdf_legacy_key];
	}

	if (\array_key_exists($client_key, $value)) {
		$client_id = \sanitize_key((string) $value[$client_key]);
		$allowed_ids = \array_keys(cmx_carent_client_options());
		$value[$client_key] = \in_array($client_id, $allowed_ids, true) ? $client_id : '';
	} elseif (isset($old_value[$client_key])) {
		$value[$client_key] = \sanitize_key((string) $old_value[$client_key]);
	}

	return $value;
}, 20, 2);
