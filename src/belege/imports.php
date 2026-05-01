<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_PT_BELEGE')) {
	\define(__NAMESPACE__ . '\\CMX_PT_BELEGE', 'belege');
}

function cmxbei_find_existing_beleg_id_by_title(string $title): int {
	global $wpdb;
	$title = \trim($title);
	if ($title === '') {
		return 0;
	}

	$sql = $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s AND post_status <> 'trash' ORDER BY ID ASC LIMIT 1",
		CMX_PT_BELEGE,
		$title
	);
	$id = (int) $wpdb->get_var($sql);
	return $id > 0 ? $id : 0;
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_standard_import_notice_lines')) {
	function cmx_standard_import_notice_lines(string $heading, array $notice): array {
		$sections = [
			'imported' => 'Importiert',
			'updated' => 'Aktualisiert',
			'skipped' => 'Übersprungen',
		];
		$lines = ['<strong>' . \esc_html($heading) . ':</strong>'];

		foreach ($sections as $key => $label) {
			$items = \array_values(\array_filter(\array_map('trim', (array) ($notice[$key] ?? []))));
			if ($items === []) {
				$lines[] = '<strong>' . \esc_html($label) . ' (0):</strong> -';
				continue;
			}
			$lines[] = '<strong>' . \esc_html($label) . ' (' . \count($items) . '):</strong> ' . \esc_html(\implode(', ', $items));
		}

		$failed = \array_values(\array_filter(\array_map('trim', (array) ($notice['failed'] ?? []))));
		if ($failed !== []) {
			$lines[] = '<strong>Fehlgeschlagen (' . \count($failed) . '):</strong> ' . \esc_html(\implode(', ', $failed));
		}

		return $lines;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbei_import_notice_key')) {
	function cmxbei_import_notice_key(): string {
		return 'cmx_import_notice_belege';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbei_import_header_is_valid')) {
	function cmxbei_import_header_is_valid(array $header): bool {
		$normalized = [];
		foreach ($header as $column) {
			$key = \function_exists('\\mb_strtolower')
				? (string) \mb_strtolower(\trim((string) $column), 'UTF-8')
				: (string) \strtolower(\trim((string) $column));
			if ($key !== '') {
				$normalized[$key] = true;
			}
		}

		foreach (['post_id', 'post_title', 'post_status', 'post_date', 'post_slug'] as $required) {
			if (!isset($normalized[$required])) {
				return false;
			}
		}

		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbei_import_redirect_notice')) {
	function cmxbei_import_redirect_notice(array $notice): void {
		\update_user_meta(\get_current_user_id(), cmxbei_import_notice_key(), $notice);
		\wp_safe_redirect(\add_query_arg([
			'post_type' => CMX_PT_BELEGE,
			'cmx_import_notice_belege' => 1,
		], \admin_url('edit.php')));
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbei_import_cleanup_dir')) {
	function cmxbei_import_cleanup_dir(string $dir): void {
		$dir = \wp_normalize_path($dir);
		if ($dir === '' || !\is_dir($dir)) {
			return;
		}

		$items = \scandir($dir);
		if (!\is_array($items)) {
			@rmdir($dir);
			return;
		}

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') continue;
			$path = \wp_normalize_path($dir . '/' . $item);
			if (\is_dir($path)) {
				cmxbei_import_cleanup_dir($path);
			} elseif (\is_file($path)) {
				@unlink($path);
			}
		}

		@rmdir($dir);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbei_import_extract_zip')) {
	function cmxbei_import_extract_zip(string $zip_file) {
		if (!\class_exists('\\ZipArchive')) {
			return new \WP_Error('zip_missing', 'ZIP-Import nicht verfügbar (ZipArchive fehlt).');
		}

		$tmp_dir = \function_exists('\\wp_tempnam') ? \wp_tempnam('cmx-belege-import-') : \tempnam(\sys_get_temp_dir(), 'cmx-belege-import-');
		if (!$tmp_dir) {
			return new \WP_Error('zip_temp', 'Temporärer Import-Ordner konnte nicht erstellt werden.');
		}
		if (\is_file($tmp_dir)) {
			@unlink($tmp_dir);
		}
		if (!\wp_mkdir_p($tmp_dir)) {
			return new \WP_Error('zip_temp', 'Temporärer Import-Ordner konnte nicht erstellt werden.');
		}

		$zip = new \ZipArchive();
		if ($zip->open($zip_file) !== true) {
			cmxbei_import_cleanup_dir($tmp_dir);
			return new \WP_Error('zip_open', 'ZIP-Datei konnte nicht geöffnet werden.');
		}
		if (!$zip->extractTo($tmp_dir)) {
			$zip->close();
			cmxbei_import_cleanup_dir($tmp_dir);
			return new \WP_Error('zip_extract', 'ZIP-Datei konnte nicht entpackt werden.');
		}
		$zip->close();

		$csv_path = '';
		$fallback_csv_path = '';
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($tmp_dir, \FilesystemIterator::SKIP_DOTS)
		);
		foreach ($iterator as $file_info) {
			if (!$file_info instanceof \SplFileInfo || !$file_info->isFile()) continue;
			if (\strtolower((string) $file_info->getExtension()) !== 'csv') continue;
			$candidate = \wp_normalize_path($file_info->getPathname());
			if ($fallback_csv_path === '') {
				$fallback_csv_path = $candidate;
			}

			$handle = @\fopen($candidate, 'r');
			if (!$handle) {
				continue;
			}

			$first = \fread($handle, 3);
			if ($first !== "\xEF\xBB\xBF") {
				\fseek($handle, 0);
			}
			$header = \fgetcsv($handle, 0, ';', '"', '\\');
			\fclose($handle);

			if ($header && cmxbei_import_header_is_valid(\array_map('trim', $header))) {
				$csv_path = $candidate;
				break;
			}
		}

		if ($csv_path === '' && $fallback_csv_path !== '') {
			$csv_path = $fallback_csv_path;
		}

		if ($csv_path === '') {
			cmxbei_import_cleanup_dir($tmp_dir);
			return new \WP_Error('zip_csv_missing', 'In der ZIP wurde keine CSV-Datei gefunden.');
		}

		return [
			'csv_path' => $csv_path,
			'extract_dir' => $tmp_dir,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbei_import_decode_meta_value')) {
	function cmxbei_import_decode_meta_value(string $raw) {
		$value = \trim($raw);
		if ($value === '') {
			return '';
		}

		$first = $value[0] ?? '';
		if (($first === '{' || $first === '[') && \function_exists('\\json_decode')) {
			$decoded = \json_decode($value, true);
			if (\json_last_error() === JSON_ERROR_NONE) {
				return $decoded;
			}
		}

		if (\function_exists('\\is_serialized') && \is_serialized($value)) {
			return \maybe_unserialize($value);
		}

		return $value;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbei_import_refresh_beleg_pdf')) {
	function cmxbei_import_refresh_beleg_pdf(int $post_id, bool $is_update): void {
		if (!\function_exists(__NAMESPACE__ . '\\cmxbu_generate_document_on_save')) {
			return;
		}

		$post = \get_post($post_id);
		if (!$post instanceof \WP_Post) {
			return;
		}

		$had_skip_flag = \array_key_exists('cmx_skip_beleg_pdf_generation', $GLOBALS);
		$previous_skip_flag = $had_skip_flag ? $GLOBALS['cmx_skip_beleg_pdf_generation'] : null;
		unset($GLOBALS['cmx_skip_beleg_pdf_generation']);

		try {
			cmxbu_generate_document_on_save($post_id, $post, $is_update);
		} finally {
			if ($had_skip_flag) {
				$GLOBALS['cmx_skip_beleg_pdf_generation'] = $previous_skip_flag;
			} else {
				unset($GLOBALS['cmx_skip_beleg_pdf_generation']);
			}
		}
	}
}

/* ===== Import-Link in der Belege-Liste ===== */
\add_filter('views_edit-' . CMX_PT_BELEGE, function(array $views): array {
	if (!\current_user_can('edit_posts')) {
		return $views;
	}

	$url = \add_query_arg(['post_type' => CMX_PT_BELEGE, 'cmx_import' => 1], \admin_url('edit.php'));
	$link = '<a href="' . \esc_url($url) . '">importieren</a>';

	if (\function_exists(__NAMESPACE__ . '\\cmxbel_view_insert_before')) {
		return cmxbel_view_insert_before($views, 'cmx_deckungsbeitrag', 'cmx_import_belege', $link);
	}

	$views['cmx_import_belege'] = $link;
	return $views;
}, 36);

/* ===== Eingang-Link in der Belege-Liste ===== */
\add_filter('views_edit-' . CMX_PT_BELEGE, function(array $views): array {
	if (!\current_user_can('edit_posts')) {
		return $views;
	}

	unset($views['pending']);

	$url = \add_query_arg(['post_type' => CMX_PT_BELEGE, 'cmx_belegeingang' => 1], \admin_url('edit.php'));
	$is_current = !empty($_GET['cmx_belegeingang'])
		|| (isset($_GET['post_status']) && \sanitize_key((string) \wp_unslash($_GET['post_status'])) === 'pending');
	$count_query = new \WP_Query([
		'post_type' => CMX_PT_BELEGE,
		'post_status' => 'pending',
		'posts_per_page' => 1,
		'fields' => 'ids',
		'no_found_rows' => false,
		'meta_query' => [
			[
				'key' => '_cmx_belegeingang_source',
				'value' => 'rest',
			],
			[
				'key' => '_cmx_belegeingang_status',
				'value' => 'pending',
			],
		],
	]);
	$count = (int) $count_query->found_posts;
	$label = __('Eingang', 'cmx');
	if ($count > 1) {
		$label .= ' (' . $count . ')';
	}
	$link = '<a href="' . \esc_url($url) . '"' . ($is_current ? ' class="current" aria-current="page"' : '') . '>' . \esc_html($label) . '</a>';

	if (\function_exists(__NAMESPACE__ . '\\cmxbel_view_insert_before')) {
		return cmxbel_view_insert_before($views, 'cmx_deckungsbeitrag', 'cmx_eingang_belege', $link);
	}

	$views['cmx_eingang_belege'] = $link;
	return $views;
}, 37);

/* ===== Inline-Formular ===== */
\add_action('all_admin_notices', function (): void {
	global $typenow;
	if ($typenow !== CMX_PT_BELEGE || empty($_GET['cmx_import'])) {
		return;
	}
	if (!\current_user_can('edit_posts')) {
		return;
	}
	?>
	<div class="notice notice-info" style="padding:20px;margin-top:15px;">
		<h2>Belege Import</h2>
		<p>Wähle Deine Mis Büro <code>CSV- oder ZIP-Datei</code> aus, welche Du zuvor exportiert hast.</p>

		<form method="post" enctype="multipart/form-data" action="">
			<?php \wp_nonce_field('cmx_belege_import'); ?>
			<input type="hidden" name="cmx_do_import" value="1">

			<table class="form-table" role="presentation" style="margin-top:1em;">
				<tbody>
					<tr>
						<th scope="row"><label for="cmx_update_mode">Existierende überschreiben?</label></th>
						<td>
							<label>
								<input type="checkbox" id="cmx_update_mode" name="update_mode" value="1">
								Ja, Belege mit gleichem Namen aktualisieren
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cmx_csv_file">CSV- oder ZIP-Datei</label></th>
						<td><input type="file" id="cmx_csv_file" name="csv_file" accept=".csv,.zip" required></td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">Import starten</button>
				<a href="<?php echo \esc_url(\admin_url('edit.php?post_type=' . CMX_PT_BELEGE)); ?>" class="button">Abbrechen</a>
			</p>
		</form>
	</div>
	<?php
});

/* ===== Import ausführen ===== */
\add_action('load-edit.php', function (): void {
	global $typenow;
	if ($typenow !== CMX_PT_BELEGE) return;
	if (empty($_POST['cmx_do_import']) || !\check_admin_referer('cmx_belege_import')) return;

	if (empty($_FILES['csv_file']['tmp_name'])) {
		\add_action('admin_notices', function () {
			echo '<div class="notice notice-error"><p>Keine Datei ausgewählt.</p></div>';
		});
		return;
	}

	$file = $_FILES['csv_file']['tmp_name'];
	$file_name = isset($_FILES['csv_file']['name']) ? (string) $_FILES['csv_file']['name'] : '';
	$file_ext = \strtolower((string) \pathinfo($file_name, PATHINFO_EXTENSION));
	$sep = ';';
	$update_mode = !empty($_POST['update_mode']);
	$import_file = $file;
	$cleanup_dir = '';

	if ($file_ext === 'zip') {
		$zip_import = cmxbei_import_extract_zip($file);
		if (\is_wp_error($zip_import)) {
			$message = (string) $zip_import->get_error_message();
			\add_action('admin_notices', static function () use ($message) {
				echo '<div class="notice notice-error"><p>' . \esc_html($message) . '</p></div>';
			});
			return;
		}
		$import_file = (string) ($zip_import['csv_path'] ?? '');
		$cleanup_dir = (string) ($zip_import['extract_dir'] ?? '');
	}

	$handle = @\fopen($import_file, 'r');
	if (!$handle) {
		if ($cleanup_dir !== '') cmxbei_import_cleanup_dir($cleanup_dir);
		\add_action('admin_notices', function () {
			echo '<div class="notice notice-error"><p>Datei konnte nicht gelesen werden.</p></div>';
		});
		return;
	}

	$first = \fread($handle, 3);
	if ($first !== "\xEF\xBB\xBF") {
		\fseek($handle, 0);
	}

	$header = \fgetcsv($handle, 0, $sep, '"', '\\');
	if (!$header) {
		\fclose($handle);
		if ($cleanup_dir !== '') cmxbei_import_cleanup_dir($cleanup_dir);
		\add_action('admin_notices', function () {
			echo '<div class="notice notice-error"><p>Leere oder ungültige CSV.</p></div>';
		});
		return;
	}

	$header = \array_map('trim', $header);
	if (!cmxbei_import_header_is_valid($header)) {
		\fclose($handle);
		if ($cleanup_dir !== '') cmxbei_import_cleanup_dir($cleanup_dir);
		cmxbei_import_redirect_notice([
			'type' => 'error',
			'message' => 'Falsches Format',
		]);
	}

	$notice = [
		'imported' => [],
		'updated' => [],
		'skipped' => [],
		'failed' => [],
	];
	$row_number = 1;

	while (($line = \fgetcsv($handle, 0, $sep, '"', '\\')) !== false) {
		$row_number++;
		if (!\array_filter($line, static fn($value) => $value !== null && $value !== '')) continue;

		$row = @\array_combine($header, $line);
		if (!$row) {
			$notice['skipped'][] = 'Zeile ' . $row_number;
			continue;
		}
		$row_l = \array_change_key_case($row, CASE_LOWER);

		$title_raw = \trim((string) ($row['post_title'] ?? ($row_l['post_title'] ?? '')));
		if ($title_raw === '') {
			$notice['skipped'][] = 'Zeile ' . $row_number;
			continue;
		}
		$title = \sanitize_text_field($title_raw);
		if ($title === '') {
			$notice['skipped'][] = 'Zeile ' . $row_number;
			continue;
		}

		$postarr = [
			'post_type' => CMX_PT_BELEGE,
			'post_title' => $title,
			'post_name' => \sanitize_title((string) ($row['post_slug'] ?? ($row_l['post_slug'] ?? ($row['post_name'] ?? ($row_l['post_name'] ?? $title))))),
			'post_status' => \sanitize_key((string) ($row['post_status'] ?? ($row_l['post_status'] ?? 'publish'))),
			'post_date' => (string) ($row['post_date'] ?? ($row_l['post_date'] ?? \current_time('mysql'))),
		];

		$is_update = false;
		if ($update_mode) {
			$existing_id = cmxbei_find_existing_beleg_id_by_title($title);
			if ($existing_id > 0) {
				$postarr['ID'] = $existing_id;
				$is_update = true;
			}
		}

		$had_skip_pdf_flag = \array_key_exists('cmx_skip_beleg_pdf_generation', $GLOBALS);
		$previous_skip_pdf_flag = $had_skip_pdf_flag ? $GLOBALS['cmx_skip_beleg_pdf_generation'] : null;
		$GLOBALS['cmx_skip_beleg_pdf_generation'] = true;

		$post_id = 0;
		$post_error = null;

		try {
			$post_id = \wp_insert_post($postarr, true);
			if (\is_wp_error($post_id)) {
				$post_error = $post_id;
			} else {
				foreach ($row as $key => $value) {
					if (\strpos($key, 'tax__') === 0) {
						$tax = \substr($key, 5);
						if ($tax === '') continue;

						$raw_terms = \trim((string) $value);
						if ($raw_terms !== '') {
							$terms = \array_map('trim', \explode(',', $raw_terms));
							\wp_set_object_terms($post_id, $terms, $tax, false);
						} elseif ($is_update) {
							\wp_set_object_terms($post_id, [], $tax, false);
						}
						continue;
					}

					if (\strpos($key, 'meta__') === 0) {
						$meta_key = \substr($key, 6);
						if ($meta_key === '' || \in_array($meta_key, ['_thumbnail_id', '_edit_lock', '_edit_last'], true)) {
							continue;
						}

						$meta_raw = \is_string($value) ? \trim($value) : '';
						if ($meta_raw === '') {
							\delete_post_meta($post_id, $meta_key);
							continue;
						}

						$meta_value = \function_exists(__NAMESPACE__ . '\\cmxbei_import_decode_meta_value')
							? cmxbei_import_decode_meta_value($meta_raw)
							: $meta_raw;
						\update_post_meta($post_id, $meta_key, $meta_value);
						continue;
					}
				}
			}
		} finally {
			if ($had_skip_pdf_flag) {
				$GLOBALS['cmx_skip_beleg_pdf_generation'] = $previous_skip_pdf_flag;
			} else {
				unset($GLOBALS['cmx_skip_beleg_pdf_generation']);
			}
		}

		if ($post_error instanceof \WP_Error) {
			$notice['failed'][] = $title;
			continue;
		}

		if ($post_id > 0 && \function_exists(__NAMESPACE__ . '\\cmxbei_import_refresh_beleg_pdf')) {
			cmxbei_import_refresh_beleg_pdf((int) $post_id, $is_update);
		}

		$stored_title = \trim((string) \get_the_title($post_id));
		$label = $stored_title !== '' ? $stored_title : $title;
		if ($is_update) {
			$notice['updated'][] = $label;
		} else {
			$notice['imported'][] = $label;
		}
	}

	\fclose($handle);
	if ($cleanup_dir !== '') cmxbei_import_cleanup_dir($cleanup_dir);

	cmxbei_import_redirect_notice($notice);
});

/* ===== Notice nach Redirect ===== */
\add_action('all_admin_notices', function (): void {
	if (empty($_GET['cmx_import_notice_belege'])) return;

	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	$current_post_type = '';
	if ($screen && !empty($screen->post_type)) {
		$current_post_type = (string) $screen->post_type;
	} elseif (!empty($_GET['post_type'])) {
		$current_post_type = \sanitize_key((string) \wp_unslash($_GET['post_type']));
	}
	if ($current_post_type !== CMX_PT_BELEGE) return;

	$notice = \get_user_meta(\get_current_user_id(), cmxbei_import_notice_key(), true);
	if (!$notice) return;

	\delete_user_meta(\get_current_user_id(), cmxbei_import_notice_key());
	$notice_type = \sanitize_key((string) ($notice['type'] ?? 'success'));
	if (!empty($notice['message'])) {
		echo '<div class="notice notice-' . \esc_attr($notice_type === 'error' ? 'error' : 'success') . ' is-dismissible"><p>' . \esc_html((string) $notice['message']) . '</p></div>';
		return;
	}

	$lines = cmx_standard_import_notice_lines('Import abgeschlossen', (array) $notice);
	echo '<div class="notice notice-success is-dismissible">';
	foreach ($lines as $line) {
		echo '<p>' . \wp_kses_post($line) . '</p>';
	}
	echo '</div>';
});
