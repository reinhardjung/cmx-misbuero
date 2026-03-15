<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!defined(__NAMESPACE__ . '\\CMX_PT_PROJEKTE')) {
	define(__NAMESPACE__ . '\\CMX_PT_PROJEKTE', 'projekte');
}

function cmx_import_find_existing_projekt_id_by_title(string $title): int {
	global $wpdb;
	$title = \trim($title);
	if ($title === '') return 0;
	$sql = $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s AND post_status <> 'trash' ORDER BY ID ASC LIMIT 1",
		CMX_PT_PROJEKTE,
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

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_import_notice_key')) {
	function cmxpr_import_notice_key(): string {
		return 'cmx_import_notice_projekte';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_import_header_is_valid')) {
	function cmxpr_import_header_is_valid(array $header): bool {
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

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_import_redirect_notice')) {
	function cmxpr_import_redirect_notice(array $notice): void {
		\update_user_meta(\get_current_user_id(), cmxpr_import_notice_key(), $notice);
		\wp_safe_redirect(\add_query_arg([
			'post_type' => CMX_PT_PROJEKTE,
			'cmx_import_notice_projekte' => 1,
		], \admin_url('edit.php')));
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_import_cleanup_dir')) {
	function cmxpr_import_cleanup_dir(string $dir): void {
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
				cmxpr_import_cleanup_dir($path);
			} elseif (\is_file($path)) {
				@unlink($path);
			}
		}

		@rmdir($dir);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_import_extract_zip')) {
	function cmxpr_import_extract_zip(string $zip_file) {
		if (!\class_exists('\\ZipArchive')) {
			return new \WP_Error('zip_missing', 'ZIP-Import nicht verfügbar (ZipArchive fehlt).');
		}

		$tmp_dir = \function_exists('\\wp_tempnam') ? \wp_tempnam('cmx-projekte-import-') : \tempnam(\sys_get_temp_dir(), 'cmx-projekte-import-');
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
			cmxpr_import_cleanup_dir($tmp_dir);
			return new \WP_Error('zip_open', 'ZIP-Datei konnte nicht geöffnet werden.');
		}
		if (!$zip->extractTo($tmp_dir)) {
			$zip->close();
			cmxpr_import_cleanup_dir($tmp_dir);
			return new \WP_Error('zip_extract', 'ZIP-Datei konnte nicht entpackt werden.');
		}
		$zip->close();

		$csv_path = '';
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($tmp_dir, \FilesystemIterator::SKIP_DOTS)
		);
		foreach ($iterator as $file_info) {
			if (!$file_info instanceof \SplFileInfo || !$file_info->isFile()) continue;
			if (\strtolower((string) $file_info->getExtension()) !== 'csv') continue;
			$csv_path = \wp_normalize_path($file_info->getPathname());
			break;
		}

		if ($csv_path === '') {
			cmxpr_import_cleanup_dir($tmp_dir);
			return new \WP_Error('zip_csv_missing', 'In der ZIP wurde keine CSV-Datei gefunden.');
		}

		return [
			'csv_path' => $csv_path,
			'extract_dir' => $tmp_dir,
		];
	}
}

/* ===== Import-Link in der Projektliste ===== */
\add_filter('views_edit-' . CMX_PT_PROJEKTE, function(array $views): array {
	if (!\current_user_can('edit_posts')) {
		return $views;
	}

	$url = \add_query_arg(['cmx_import' => 1]);
	$views['cmx_import_projekte'] = '<a href="' . \esc_url($url) . '">importieren</a>';
	return $views;
});

/* ===== Inline-Formular ===== */
\add_action('all_admin_notices', function(): void {
	global $typenow;
	if ($typenow !== CMX_PT_PROJEKTE || empty($_GET['cmx_import'])) {
		return;
	}
	if (!\current_user_can('edit_posts')) {
		return;
	}
	?>
	<div class="notice notice-info" style="padding:20px;margin-top:15px;">
		<h2>Projekte Import</h2>
		<p>Wähle Deine Mis Büro <code>CSV- oder ZIP-Datei</code> aus, welche Du zuvor exportiert hast.</p>

		<form method="post" enctype="multipart/form-data" action="">
			<?php \wp_nonce_field('cmx_projekte_import'); ?>
			<input type="hidden" name="cmx_do_import" value="1">

			<table class="form-table" role="presentation" style="margin-top:1em;">
				<tbody>
					<tr>
						<th scope="row"><label for="cmx_update_mode">Existierende überschreiben?</label></th>
						<td>
							<label>
								<input type="checkbox" id="cmx_update_mode" name="update_mode" value="1">
								Ja, Projekte mit gleichem Namen aktualisieren
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
				<a href="<?php echo \esc_url(\admin_url('edit.php?post_type=' . CMX_PT_PROJEKTE)); ?>" class="button">Abbrechen</a>
			</p>
		</form>
	</div>
	<?php
});

/* ===== Import ausführen ===== */
\add_action('load-edit.php', function(): void {
	global $typenow;
	if ($typenow !== CMX_PT_PROJEKTE) return;
	if (empty($_POST['cmx_do_import']) || !\check_admin_referer('cmx_projekte_import')) return;

	if (empty($_FILES['csv_file']['tmp_name'])) {
		\add_action('admin_notices', function(){
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
		$zip_import = cmxpr_import_extract_zip($file);
		if (\is_wp_error($zip_import)) {
			$message = (string) $zip_import->get_error_message();
			\add_action('admin_notices', static function() use ($message) {
				echo '<div class="notice notice-error"><p>' . \esc_html($message) . '</p></div>';
			});
			return;
		}
		$import_file = (string) ($zip_import['csv_path'] ?? '');
		$cleanup_dir = (string) ($zip_import['extract_dir'] ?? '');
	}

	$handle = @\fopen($import_file, 'r');
	if (!$handle) {
		if ($cleanup_dir !== '') cmxpr_import_cleanup_dir($cleanup_dir);
		\add_action('admin_notices', function(){
			echo '<div class="notice notice-error"><p>Datei konnte nicht gelesen werden.</p></div>';
		});
		return;
	}

	$first = \fread($handle, 3);
	if ($first !== "\xEF\xBB\xBF") {
		\fseek($handle, 0);
	}

	$header = \fgetcsv($handle, 0, $sep);
	if (!$header) {
		\fclose($handle);
		if ($cleanup_dir !== '') cmxpr_import_cleanup_dir($cleanup_dir);
		\add_action('admin_notices', function(){
			echo '<div class="notice notice-error"><p>Leere oder ungültige CSV.</p></div>';
		});
		return;
	}
	$header = \array_map('trim', $header);
	if (!cmxpr_import_header_is_valid($header)) {
		\fclose($handle);
		if ($cleanup_dir !== '') cmxpr_import_cleanup_dir($cleanup_dir);
		cmxpr_import_redirect_notice([
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

	while (($line = \fgetcsv($handle, 0, $sep)) !== false) {
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
			'post_type' => CMX_PT_PROJEKTE,
			'post_title' => $title,
			'post_name' => \sanitize_title((string) ($row['post_slug'] ?? ($row_l['post_slug'] ?? ($row['post_name'] ?? ($row_l['post_name'] ?? $title))))),
			'post_status' => \sanitize_key((string) ($row['post_status'] ?? ($row_l['post_status'] ?? 'publish'))),
			'post_date' => (string) ($row['post_date'] ?? ($row_l['post_date'] ?? \current_time('mysql'))),
		];

		$is_update = false;
		if ($update_mode) {
			$existing_id = cmx_import_find_existing_projekt_id_by_title($title);
			if ($existing_id > 0) {
				$postarr['ID'] = $existing_id;
				$is_update = true;
			}
		}

		$post_id = \wp_insert_post($postarr, true);
		if (\is_wp_error($post_id)) {
			$notice['failed'][] = $title;
			continue;
		}

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
				$meta_value = \is_string($value) ? \trim($value) : $value;
				if ($meta_value === '') {
					\delete_post_meta($post_id, $meta_key);
				} else {
					\update_post_meta($post_id, $meta_key, $meta_value);
				}
			}
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
	if ($cleanup_dir !== '') cmxpr_import_cleanup_dir($cleanup_dir);

	cmxpr_import_redirect_notice($notice);
});

/* ===== Notice nach Redirect anzeigen ===== */
\add_action('all_admin_notices', function(): void {
	if (empty($_GET['cmx_import_notice_projekte'])) return;

	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	$current_post_type = '';
	if ($screen && !empty($screen->post_type)) {
		$current_post_type = (string) $screen->post_type;
	} elseif (!empty($_GET['post_type'])) {
		$current_post_type = \sanitize_key((string) \wp_unslash($_GET['post_type']));
	}
	if ($current_post_type !== CMX_PT_PROJEKTE) return;

	$notice = \get_user_meta(\get_current_user_id(), cmxpr_import_notice_key(), true);
	if (!$notice) return;

	\delete_user_meta(\get_current_user_id(), cmxpr_import_notice_key());
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
