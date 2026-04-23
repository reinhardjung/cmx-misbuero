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

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_pdf_delete_field_name')) {
	function cmx_carent_pdf_delete_field_name(): string {
		return 'cmx_carent_pdf_delete';
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_pdf_abs_path_from_rel')) {
	function cmx_carent_pdf_abs_path_from_rel(string $file_rel): string {
		$file_rel = \ltrim(\trim($file_rel), '/');
		if ($file_rel === '') {
			return '';
		}

		$uploads_root = \trailingslashit(\wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads')));
		$file_abs = \wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads/' . $file_rel));
		if ($file_abs === '' || !\str_starts_with($file_abs, $uploads_root)) {
			return '';
		}

		return $file_abs;
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
			echo '<input type="hidden" id="cmx-carent-pdf-delete" name="' . \esc_attr(cmx_carent_pdf_delete_field_name()) . '" value="0">';
			echo '<p class="description" id="cmx-carent-pdf-current">';
			if ($file_url !== '' && $file_name !== '') {
				echo '<span id="cmx-carent-pdf-current-row" style="display:inline-flex;align-items:center;gap:8px;">';
				echo '<a href="' . \esc_url($file_url) . '" target="_blank" rel="noopener noreferrer">' . \esc_html($file_name) . '</a>';
				echo '<button type="button" class="button-link-delete" id="cmx-carent-pdf-delete-button" title="PDF löschen" aria-label="PDF löschen" style="display:inline-flex;align-items:center;justify-content:center;min-height:18px;padding:0;border:0;background:none;line-height:1;vertical-align:middle;">';
				echo '<span class="dashicons dashicons-trash" aria-hidden="true" style="font-size:16px;width:16px;height:16px;"></span>';
				echo '</button>';
				echo '</span>';
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

\add_action('admin_print_footer_scripts', function (): void {
	$page = isset($_GET['page']) ? \sanitize_key((string) \wp_unslash($_GET['page'])) : '';
	$tab = isset($_GET['tab']) ? \sanitize_key((string) \wp_unslash($_GET['tab'])) : '';
	$settings_page = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
		? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
		: 'cmx-einstellungen';
	if ($page !== $settings_page || $tab !== 'carent' || !cmx_carent_settings_is_enabled()) {
		return;
	}
	?>
	<script>
	(function(){
		var button = document.getElementById('cmx-carent-pdf-delete-button');
		var flag = document.getElementById('cmx-carent-pdf-delete');
		var current = document.getElementById('cmx-carent-pdf-current');
		var fileInput = document.getElementById('cmx-carent-pdf-file');
		if (!button || !flag || !current) {
			return;
		}

		button.addEventListener('click', function(event){
			event.preventDefault();
			flag.value = '1';
			if (fileInput) {
				fileInput.value = '';
			}
			current.textContent = 'Noch kein PDF ausgewählt.';
		});
	})();
	</script>
	<?php
});

\add_filter('pre_update_option_' . CMX_SETTINGS_MAIN, function ($value, $old_value) {
	$value = \is_array($value) ? $value : [];
	$old_value = \is_array($old_value) ? $old_value : [];

	$pdf_key = cmx_carent_pdf_option_key();
	$pdf_legacy_key = cmx_carent_pdf_legacy_option_key();
	$client_key = cmx_carent_client_option_key();

	$uploaded_file = $_FILES[cmx_carent_pdf_upload_field_name()] ?? null;
	$has_uploaded_file = \is_array($uploaded_file) && !empty($uploaded_file['name']);
	$delete_pdf = isset($_POST[cmx_carent_pdf_delete_field_name()])
		&& !\is_array($_POST[cmx_carent_pdf_delete_field_name()])
		&& (string) \wp_unslash($_POST[cmx_carent_pdf_delete_field_name()]) === '1';

	if ($has_uploaded_file) {
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
	} elseif ($delete_pdf) {
		$old_file_rel = \trim((string) ($old_value[$pdf_key] ?? ''));
		$old_file_abs = cmx_carent_pdf_abs_path_from_rel($old_file_rel);
		if ($old_file_abs !== '' && \is_file($old_file_abs)) {
			@unlink($old_file_abs);
		}

		$old_attachment_id = isset($old_value[$pdf_legacy_key]) ? (int) $old_value[$pdf_legacy_key] : 0;
		if ($old_attachment_id > 0 && \get_post_type($old_attachment_id) === 'attachment') {
			\wp_delete_attachment($old_attachment_id, true);
		}

		$value[$pdf_key] = '';
		$value[$pdf_legacy_key] = 0;
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
