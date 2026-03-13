<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


if (!defined(__NAMESPACE__.'\\CMX_PT_ARTIKEL')) {
	define(__NAMESPACE__.'\\CMX_PT_ARTIKEL', 'artikel');
}

function cmx_import_find_existing_artikel_id_by_title(string $title): int {
	global $wpdb;
	$title = \trim($title);
	if ($title === '') return 0;
	$sql = $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s AND post_status <> 'trash' ORDER BY ID ASC LIMIT 1",
		CMX_PT_ARTIKEL,
		$title
	);
	$id = (int) $wpdb->get_var($sql);
	return $id > 0 ? $id : 0;
}

function cmx_artikel_import_normalize_name(string $value): string {
	$value = \wp_strip_all_tags($value);
	$value = \trim((string) \preg_replace('/\s+/u', ' ', $value));
	if ($value === '') {
		return '';
	}
	return \function_exists('\\mb_strtolower')
		? (string) \mb_strtolower($value, 'UTF-8')
		: (string) \strtolower($value);
}

function cmx_artikel_import_find_lieferant_id_by_name(string $name): int {
	static $map = null;

	$name = \trim($name);
	if ($name === '') {
		return 0;
	}

	if ($map === null) {
		$map = [];
		$kontakt_pt = \function_exists(__NAMESPACE__ . '\\cmx_first_existing_kontakt_cpt_unified')
			? (string) cmx_first_existing_kontakt_cpt_unified()
			: '';

		if ($kontakt_pt !== '') {
			$lieferanten_ids = \function_exists(__NAMESPACE__ . '\\cmx_fetch_lieferanten_ids_unified')
				? \array_map('intval', (array) cmx_fetch_lieferanten_ids_unified($kontakt_pt))
				: [];

			if ($lieferanten_ids === []) {
				$lieferanten_ids = \array_map('intval', (array) \get_posts([
					'post_type'              => $kontakt_pt,
					'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'suppress_filters'       => true,
				]));
			}

			foreach ($lieferanten_ids as $kontakt_id) {
				$kontakt_id = (int) $kontakt_id;
				if ($kontakt_id <= 0) {
					continue;
				}
				$title = \trim((string) \get_the_title($kontakt_id));
				$normalized = cmx_artikel_import_normalize_name($title);
				if ($normalized !== '' && !isset($map[$normalized])) {
					$map[$normalized] = $kontakt_id;
				}
			}
		}
	}

	$normalized = cmx_artikel_import_normalize_name($name);
	return $normalized !== '' ? (int) ($map[$normalized] ?? 0) : 0;
}

function cmx_artikel_import_apply_lieferanten_names(int $post_id, array $row): void {
	$resolved_first_id = null;

	foreach ($row as $key => $value) {
		if (!\preg_match('/^lieferant_(\d+)_name$/i', (string) $key, $matches)) {
			continue;
		}

		$row_index = \max(0, ((int) ($matches[1] ?? 0)) - 1);
		$name = \trim((string) $value);
		$kontakt_id = $name !== ''
			? (int) cmx_artikel_import_find_lieferant_id_by_name($name)
			: 0;

		if (\function_exists(__NAMESPACE__ . '\\cmx_artikel_lieferanten_row_meta_key_unified')) {
			\update_post_meta($post_id, cmx_artikel_lieferanten_row_meta_key_unified($row_index, 'id'), $kontakt_id);
		} else {
			\update_post_meta($post_id, '_cmx_art_lieferant_' . $row_index . '_id', $kontakt_id);
		}

		if ($row_index === 0) {
			$resolved_first_id = $kontakt_id;
		}
	}

	if ($resolved_first_id !== null) {
		$legacy_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_LIEFERANT_ID')
			? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_LIEFERANT_ID')
			: '_cmx_art_lieferant_id';
		\update_post_meta($post_id, $legacy_key, (int) $resolved_first_id);
	}
}

function cmx_import_clear_lieferanten_meta(int $post_id): void {
	foreach (\array_keys((array)\get_post_meta($post_id)) as $meta_key) {
		if (\preg_match('/^_cmx_art_lieferant_\d+_(id|nr|ek|bezugsquelle|lieferzeit_tage|lagerbestand)$/', (string)$meta_key)) {
			\delete_post_meta($post_id, $meta_key);
		}
	}
	\delete_post_meta($post_id, '_cmx_art_lieferanten_count');
	\delete_post_meta($post_id, '_cmx_art_lieferanten_liste');
}

function cmx_artikel_import_cleanup_dir(string $dir): void {
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
			cmx_artikel_import_cleanup_dir($path);
		} elseif (\is_file($path)) {
			@unlink($path);
		}
	}

	@rmdir($dir);
}

function cmx_artikel_import_extract_zip(string $zip_file) {
	if (!\class_exists('\\ZipArchive')) {
		return new \WP_Error('zip_missing', 'ZIP-Import nicht verfügbar (ZipArchive fehlt).');
	}

	$tmp_dir = \function_exists('\\wp_tempnam') ? \wp_tempnam('cmx-artikel-import-') : \tempnam(\sys_get_temp_dir(), 'cmx-artikel-import-');
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
		cmx_artikel_import_cleanup_dir($tmp_dir);
		return new \WP_Error('zip_open', 'ZIP-Datei konnte nicht geöffnet werden.');
	}
	if (!$zip->extractTo($tmp_dir)) {
		$zip->close();
		cmx_artikel_import_cleanup_dir($tmp_dir);
		return new \WP_Error('zip_extract', 'ZIP-Datei konnte nicht entpackt werden.');
	}
	$zip->close();

	$csv_path = '';
	$image_map = [];
	$iterator = new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator($tmp_dir, \FilesystemIterator::SKIP_DOTS)
	);
	foreach ($iterator as $file_info) {
		if (!$file_info instanceof \SplFileInfo || !$file_info->isFile()) continue;
		$path = \wp_normalize_path($file_info->getPathname());
		$rel = \ltrim(\str_replace('\\', '/', (string) \substr($path, \strlen(\wp_normalize_path($tmp_dir)))), '/');
		$ext = \strtolower((string) $file_info->getExtension());
		if ($ext === 'csv' && $csv_path === '') {
			$csv_path = $path;
		}
		if (\in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'bmp', 'ico'], true)) {
			$image_map[$rel] = $path;
			$base = \basename($rel);
			if (!isset($image_map[$base])) {
				$image_map[$base] = $path;
			}
		}
	}

	if ($csv_path === '') {
		cmx_artikel_import_cleanup_dir($tmp_dir);
		return new \WP_Error('zip_csv_missing', 'In der ZIP wurde keine CSV-Datei gefunden.');
	}

	return [
		'csv_path'    => $csv_path,
		'image_map'   => $image_map,
		'extract_dir' => $tmp_dir,
	];
}

function cmx_artikel_import_image_base_path(): string {
	if (\function_exists('\\CLOUDMEISTER\\CMX\\MisBuero\\cmx_li_base_path')) {
		return \CLOUDMEISTER\CMX\MisBuero\cmx_li_base_path();
	}
	$uploads = \wp_get_upload_dir();
	return \wp_normalize_path($uploads['basedir'] . '/misbuero/archiv/bilder/artikel');
}

function cmx_artikel_import_image_base_url(): string {
	if (\function_exists('\\CLOUDMEISTER\\CMX\\MisBuero\\cmx_li_base_url')) {
		return \CLOUDMEISTER\CMX\MisBuero\cmx_li_base_url();
	}
	$uploads = \wp_get_upload_dir();
	return \rtrim((string) $uploads['baseurl'], '/') . '/misbuero/archiv/bilder/artikel';
}

function cmx_artikel_import_image_basename_for_post(int $post_id): string {
	$title = \strtolower((string) \sanitize_title(\get_the_title($post_id) ?: 'artikel'));
	return $title !== '' ? $title : ('artikel-' . (int) $post_id);
}

function cmx_artikel_import_copy_image_file_to_post(int $post_id, string $source_path): bool {
	$source_path = \wp_normalize_path(\trim($source_path));
	if ($source_path === '' || !\is_readable($source_path) || !\is_file($source_path)) {
		return false;
	}

	$ext = \strtolower((string) \pathinfo($source_path, PATHINFO_EXTENSION));
	if (!\in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'ico'], true)) {
		return false;
	}

	$base_dir = cmx_artikel_import_image_base_path();
	if (!\is_dir($base_dir) && !\wp_mkdir_p($base_dir)) {
		return false;
	}

	$basename = cmx_artikel_import_image_basename_for_post($post_id);
	foreach (['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'ico'] as $candidate_ext) {
		$existing = \wp_normalize_path(\trailingslashit($base_dir) . $basename . '.' . $candidate_ext);
		if (\is_file($existing)) {
			@unlink($existing);
		}
	}

	$target = \wp_normalize_path(\trailingslashit($base_dir) . $basename . '.' . $ext);
	if (!@copy($source_path, $target)) {
		return false;
	}

	@chmod($target, 0644);
	$version = @filemtime($target) ?: \time();
	$base_url = cmx_artikel_import_image_base_url();
	$url = \trailingslashit($base_url) . \rawurlencode(\basename($target)) . '?v=' . $version;

	\update_post_meta($post_id, '_cmx_local_image_artikel_path', $target);
	\update_post_meta($post_id, '_cmx_local_image_artikel_url', $url);
	return true;
}

function cmx_artikel_import_apply_image(int $post_id, array $row, array $row_l, array $zip_image_map = []): void {
	if (empty($zip_image_map)) {
		return;
	}

	$candidates = [
		(string) ($row['local_image_zip_path'] ?? ($row_l['local_image_zip_path'] ?? '')),
		(string) ($row['featured_image_zip_path'] ?? ($row_l['featured_image_zip_path'] ?? '')),
	];

	foreach ($candidates as $candidate) {
		$candidate = \trim($candidate);
		if ($candidate === '') continue;
		$normalized = \ltrim(\str_replace('\\', '/', $candidate), '/');
		$zip_source = $zip_image_map[$normalized] ?? $zip_image_map[\basename($normalized)] ?? '';
		if ($zip_source !== '' && cmx_artikel_import_copy_image_file_to_post($post_id, (string) $zip_source)) {
			return;
		}
	}
}

/**
 * 1. Import-Link oben in der Liste einfügen
 */
\add_filter('views_edit-' . CMX_PT_ARTIKEL, function(array $views){
	if (!\current_user_can('edit_posts')) {
		return $views;
	}

	$url = \add_query_arg(['cmx_import' => 1]);
	$views['cmx_import_artikel'] = '<a href="' . \esc_url($url) . '">importieren</a>';

	return $views;
});

/**
 * 2. Inline-Formular oberhalb der Tabelle einblenden (wenn ?cmx_import=1)
 */
\add_action('all_admin_notices', function() {
	global $typenow;
	if ($typenow !== CMX_PT_ARTIKEL || empty($_GET['cmx_import'])) {
		return;
	}

	if (!\current_user_can('edit_posts')) {
		return;
	}

	?>
	<div class="notice notice-info" style="padding:20px;margin-top:15px;">
		<h2>Artikel Import</h2>
		<p>Wähle Deine Mis Büro <code>CSV- oder ZIP-Datei</code> aus, welche Du zuvor exportiert hast. Achte darauf das Du bereits alle zugewiesenen Lieferanten hast!</p>
		<p><code>DEMO-Daten</code> <a href="https://misbuero.ch/wp-content/uploads/demo_artikel.csv">https://misbuero.ch/wp-content/uploads/demo_artikel.csv</a> runterladen (danach diese CSV-Datei auswählen zum importieren)</p>

		<form method="post" enctype="multipart/form-data" action="">
			<?php \wp_nonce_field('cmx_artikel_import'); ?>
			<input type="hidden" name="cmx_do_import" value="1">

			<table class="form-table" role="presentation" style="margin-top:1em;">
				<tbody>
					<tr>
						<th scope="row"><label for="cmx_update_mode">Existierende überschreiben?</label></th>
						<td>
							<label>
								<input type="checkbox" id="cmx_update_mode" name="update_mode" value="1">
								Ja, Artikel mit gleichem Namen aktualisieren
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
				<a href="<?php echo \esc_url(\admin_url('edit.php?post_type=' . CMX_PT_ARTIKEL)); ?>" class="button">Abbrechen</a>
			</p>
		</form>
	</div>
	<?php
});

/**
 * 3. Import ausführen und nach Erfolg zurück zur Artikelliste leiten
 */
\add_action('load-edit.php', function() {
	global $typenow;
	if ($typenow !== CMX_PT_ARTIKEL) return;

	if (empty($_POST['cmx_do_import']) || !\check_admin_referer('cmx_artikel_import')) return;

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
	$zip_image_map = [];
	$cleanup_dir = '';

	if ($file_ext === 'zip') {
		$zip_import = cmx_artikel_import_extract_zip($file);
		if (\is_wp_error($zip_import)) {
			$message = (string) $zip_import->get_error_message();
			\add_action('admin_notices', static function() use ($message) {
				echo '<div class="notice notice-error"><p>' . \esc_html($message) . '</p></div>';
			});
			return;
		}
		$import_file = (string) ($zip_import['csv_path'] ?? '');
		$zip_image_map = (array) ($zip_import['image_map'] ?? []);
		$cleanup_dir = (string) ($zip_import['extract_dir'] ?? '');
	}

	$handle = @\fopen($import_file, 'r');
	if (!$handle) {
		if ($cleanup_dir !== '') cmx_artikel_import_cleanup_dir($cleanup_dir);
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
		if ($cleanup_dir !== '') cmx_artikel_import_cleanup_dir($cleanup_dir);
		\add_action('admin_notices', function(){
			echo '<div class="notice notice-error"><p>Leere oder ungültige CSV.</p></div>';
		});
		return;
	}
	$header = \array_map('trim', $header);

	$has_lieferanten_meta_in_csv = false;
	foreach ($header as $column) {
		if (
			\preg_match('/^meta__(?:_cmx_art_lieferant_\d+_(?:id|nr|ek|bezugsquelle|lieferzeit_tage|lagerbestand)|_cmx_art_lieferanten_count|_cmx_art_lieferanten_liste)$/', (string) $column)
			|| \preg_match('/^lieferant_\d+_name$/i', (string) $column)
		) {
			$has_lieferanten_meta_in_csv = true;
			break;
		}
	}

	$imported = 0;
	$updated = 0;

	while (($line = \fgetcsv($handle, 0, $sep)) !== false) {
		if (!\array_filter($line, static fn($value) => $value !== null && $value !== '')) continue;

		$row = @\array_combine($header, $line);
		if (!$row) continue;
		$row_l = \array_change_key_case($row, CASE_LOWER);

		$title_raw = \trim((string) ($row['post_title'] ?? ($row_l['post_title'] ?? '')));
		if ($title_raw === '') continue;
		$title = \sanitize_text_field($title_raw);
		if ($title === '') continue;

		$postarr = [
			'post_type'   => CMX_PT_ARTIKEL,
			'post_title'  => $title,
			'post_name'   => \sanitize_title((string) ($row['post_slug'] ?? ($row_l['post_slug'] ?? ($row['post_name'] ?? ($row_l['post_name'] ?? $title))))),
			'post_status' => \sanitize_key((string) ($row['post_status'] ?? ($row_l['post_status'] ?? 'publish'))),
			'post_date'   => (string) ($row['post_date'] ?? ($row_l['post_date'] ?? \current_time('mysql'))),
		];

		$is_update = false;
		if ($update_mode) {
			$existing_id = cmx_import_find_existing_artikel_id_by_title($title);
			if ($existing_id > 0) {
				$postarr['ID'] = $existing_id;
				$is_update = true;
			}
		}

		$post_id = \wp_insert_post($postarr, true);
		if (\is_wp_error($post_id)) continue;

		if ($is_update && $has_lieferanten_meta_in_csv) {
			cmx_import_clear_lieferanten_meta((int) $post_id);
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
				if ($meta_key === '_cmx_local_image_artikel_path' || $meta_key === '_cmx_local_image_artikel_url' || $meta_key === '_thumbnail_id') {
					continue;
				}

				$meta_value = \is_string($value) ? \trim($value) : $value;
				if ($meta_value === '') {
					\delete_post_meta($post_id, $meta_key);
				} else {
					\update_post_meta($post_id, $meta_key, $meta_value);
				}
			}
		}

		cmx_artikel_import_apply_lieferanten_names((int) $post_id, $row);
		cmx_artikel_import_apply_image((int) $post_id, $row, $row_l, $zip_image_map);

		if ($is_update) {
			$updated++;
		} else {
			$imported++;
		}
	}

	\fclose($handle);
	if ($cleanup_dir !== '') cmx_artikel_import_cleanup_dir($cleanup_dir);

	\set_transient('cmx_import_notice_artikel', [
		'imported' => $imported,
		'updated'  => $updated,
	], 30);

	\wp_safe_redirect(\admin_url('edit.php?post_type=' . CMX_PT_ARTIKEL));
	exit;
});

/**
 * 4. Notice nach Redirect anzeigen
 */
\add_action('admin_notices', function() {
	global $typenow;
	if ($typenow !== CMX_PT_ARTIKEL) return;

	$notice = \get_transient('cmx_import_notice_artikel');
	if (!$notice) return;

	\delete_transient('cmx_import_notice_artikel');

	echo '<div class="notice notice-success is-dismissible"><p><strong>Import abgeschlossen:</strong> ' .
		\intval($notice['imported']) . ' neu, ' . \intval($notice['updated']) . ' aktualisiert.</p></div>';
});
