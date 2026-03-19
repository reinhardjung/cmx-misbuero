<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!defined(__NAMESPACE__.'\\CMX_PT_KONTAKTE')) {
	define(__NAMESPACE__.'\\CMX_PT_KONTAKTE', 'kontakte');
}

/** ===== Konstanten gemäss Export ===== */
if (!defined(__NAMESPACE__.'\\CMX_KONTAKTE_META_VORNAME'))  define(__NAMESPACE__.'\\CMX_KONTAKTE_META_VORNAME','_cmx_kontakte_vorname');
if (!defined(__NAMESPACE__.'\\CMX_KONTAKTE_META_NACHNAME')) define(__NAMESPACE__.'\\CMX_KONTAKTE_META_NACHNAME','_cmx_kontakte_nachname');
if (!defined(__NAMESPACE__.'\\CMX_KONTAKTE_META_URL'))      define(__NAMESPACE__.'\\CMX_KONTAKTE_META_URL','_cmx_kontakte_url');
if (!defined(__NAMESPACE__.'\\CMX_KONTAKTE_META_PRIVAT'))   define(__NAMESPACE__.'\\CMX_KONTAKTE_META_PRIVAT','_cmx_kontakte_privat');
if (!defined(__NAMESPACE__.'\\CMX_KONTAKTE_META_DATUM'))    define(__NAMESPACE__.'\\CMX_KONTAKTE_META_DATUM','_cmx_kontakte_datum');

if (!defined(__NAMESPACE__.'\\CMX_RECHNUNG_META_STRASSE')) define(__NAMESPACE__.'\\CMX_RECHNUNG_META_STRASSE','_cmx_rechnung_strasse');
if (!defined(__NAMESPACE__.'\\CMX_RECHNUNG_META_ZUSATZ'))  define(__NAMESPACE__.'\\CMX_RECHNUNG_META_ZUSATZ','_cmx_rechnung_zusatz');
if (!defined(__NAMESPACE__.'\\CMX_RECHNUNG_META_PLZ'))     define(__NAMESPACE__.'\\CMX_RECHNUNG_META_PLZ','_cmx_rechnung_plz');
if (!defined(__NAMESPACE__.'\\CMX_RECHNUNG_META_ORT'))     define(__NAMESPACE__.'\\CMX_RECHNUNG_META_ORT','_cmx_rechnung_ort');
if (!defined(__NAMESPACE__.'\\CMX_RECHNUNG_META_LAND'))    define(__NAMESPACE__.'\\CMX_RECHNUNG_META_LAND','_cmx_rechnung_land');

if (!defined(__NAMESPACE__.'\\CMX_LIEFER_META_STRASSE'))   define(__NAMESPACE__.'\\CMX_LIEFER_META_STRASSE','_cmx_liefer_strasse');
if (!defined(__NAMESPACE__.'\\CMX_LIEFER_META_ZUSATZ'))    define(__NAMESPACE__.'\\CMX_LIEFER_META_ZUSATZ','_cmx_liefer_zusatz');
if (!defined(__NAMESPACE__.'\\CMX_LIEFER_META_PLZ'))       define(__NAMESPACE__.'\\CMX_LIEFER_META_PLZ','_cmx_liefer_plz');
if (!defined(__NAMESPACE__.'\\CMX_LIEFER_META_ORT'))       define(__NAMESPACE__.'\\CMX_LIEFER_META_ORT','_cmx_liefer_ort');
if (!defined(__NAMESPACE__.'\\CMX_LIEFER_META_LAND'))      define(__NAMESPACE__.'\\CMX_LIEFER_META_LAND','_cmx_liefer_land');

function cmx_import_find_existing_kontakt_id_by_title(string $title): int {
	global $wpdb;
	$title = \trim($title);
	if ($title === '') return 0;
	$sql = $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s AND post_status <> 'trash' ORDER BY ID ASC LIMIT 1",
		CMX_PT_KONTAKTE,
		$title
	);
	$id = (int) $wpdb->get_var($sql);
	return $id > 0 ? $id : 0;
}


/**
 * 1) Import-Link oben in der Liste einfügen
 */
\add_filter('views_edit-' . CMX_PT_KONTAKTE, function(array $views){
	if (!\current_user_can('edit_posts')) return $views;
	$url = \add_query_arg(['cmx_import' => 1]);
	$views['cmx_import_kontakte'] = '<a href="' . esc_url($url) . '">importieren</a>';
	return $views;
});

/**
 * 2) Inline-Formular oberhalb der Tabelle einblenden (wenn ?cmx_import=1)
 */
\add_action('all_admin_notices', function() {
	global $typenow;
	if ($typenow !== CMX_PT_KONTAKTE || empty($_GET['cmx_import'])) return;
	if (!\current_user_can('edit_posts')) return;
	?>
	<div class="notice notice-info" style="padding:20px;margin-top:15px;">
		<h2>Kontakte Import</h2>
		<p>Wähle Deine Mis Büro <code>CSV- oder ZIP-Datei</code> aus, welche Du zuvor exportiert hast.</p>
		<p><code>DEMO-Daten</code> <a href="https://misbuero.ch/wp-content/uploads/demo_kontakte.csv">https://misbuero.ch/wp-content/uploads/demo_kontakte.csv</a> runterladen (danach diese CSV-Datei auswählen zum importieren)</p>
		<form method="post" enctype="multipart/form-data" action="">
			<?php \wp_nonce_field('cmx_kontakte_import'); ?>
			<input type="hidden" name="cmx_do_import_kontakte" value="1">
				<table class="form-table" role="presentation" style="margin-top:1em;">
					<tbody>
						<tr>
							<th scope="row"><label for="cmx_update_mode">Existierende überschreiben?</label></th>
							<td>
								<label>
									<input type="checkbox" id="cmx_update_mode" name="update_mode" value="1">
									Ja, Kontakte mit gleichem Namen aktualisieren
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
				<a href="<?php echo esc_url(admin_url('edit.php?post_type=' . CMX_PT_KONTAKTE)); ?>" class="button">Abbrechen</a>
			</p>
		</form>
	</div>
	<?php
});

/** ==== Helpers ==== */
function cmx_bool_from_csv($v): bool {
	$v = is_string($v) ? strtolower(trim($v)) : $v;
	return ($v===1 || $v==='1' || $v===true || $v==='true' || $v==='ja' || $v==='yes' || $v==='y');
}
function cmx_normalize_url(?string $url): string {
	$url = trim((string)$url);
	if ($url === '') return '';
	if (!preg_match('~^https?://~i', $url)) $url = 'https://' . ltrim($url, '/');
	return $url;
}
/** Werte aus CSV spaltenübergreifend robust splitten (Komma oder Pipe) */
function cmx_split_values($val): array {
	if ($val === null || $val === '') return [];
	$val = (string)$val;
	$parts = preg_split('/[|,]/', $val);
	$parts = array_map(static fn($v)=>trim((string)$v), (array)$parts);
	return array_values(array_filter($parts, static fn($v)=>$v!==''));
}
function cmx_assign_stufe(int $post_id, string $slug = '', string $name = ''): void {
	$tax_candidates = ['kontakte_stufen','stufen','kontakte_stufe','kontakt_stufen'];
	$tax_found = null; foreach ($tax_candidates as $tx) { if (\taxonomy_exists($tx)) { $tax_found = $tx; break; } }
	if (!$tax_found) return;
	if ($slug !== '') {
		$term = \get_term_by('slug', $slug, $tax_found);
		if ($term && !\is_wp_error($term)) { \wp_set_object_terms($post_id, [(int)$term->term_id], $tax_found, false); return; }
	}
	if ($name !== '') {
		$term = \get_term_by('name', $name, $tax_found);
		if ($term && !\is_wp_error($term)) { \wp_set_object_terms($post_id, [(int)$term->term_id], $tax_found, false); return; }
		$created = \wp_insert_term($name, $tax_found, ['slug' => sanitize_title($name)]);
		if (!\is_wp_error($created) && isset($created['term_id'])) \wp_set_object_terms($post_id, [(int)$created['term_id']], $tax_found, false);
	}
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_import_notice_key')) {
	function cmx_kontakte_import_notice_key(): string {
		return 'cmx_import_notice_kontakte';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_import_header_is_valid')) {
	function cmx_kontakte_import_header_is_valid(array $header): bool {
		$normalized = [];
		foreach ($header as $column) {
			$key = \function_exists('\\mb_strtolower')
				? (string) \mb_strtolower(\trim((string) $column), 'UTF-8')
				: (string) \strtolower(\trim((string) $column));
			if ($key !== '') {
				$normalized[$key] = true;
			}
		}

		foreach (['titel', 'vorname', 'nachname', 'rechnung_strasse', 'liefer_strasse', 'telefon_1', 'email_1'] as $required) {
			if (!isset($normalized[$required])) {
				return false;
			}
		}

		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_import_redirect_notice')) {
	function cmx_kontakte_import_redirect_notice(array $notice): void {
		\update_user_meta(\get_current_user_id(), cmx_kontakte_import_notice_key(), $notice);
		\wp_safe_redirect(\add_query_arg([
			'post_type' => CMX_PT_KONTAKTE,
			'cmx_import_notice_kontakte' => 1,
		], \admin_url('edit.php')));
		exit;
	}
}

/**
 * Kategorien-Taxonomien für Kontakte erkennen:
 *  - bevorzuge Taxonomien, deren Name 'kategor' enthält
 *  - plus bekannte Kandidaten
 */
function cmx_detect_category_taxonomies_for_kontakte(): array {
	$out = [];
	$all = \get_object_taxonomies(CMX_PT_KONTAKTE, 'objects');
	foreach ($all as $tax) {
		$name = strtolower($tax->name);
		if (strpos($name, 'kategor') !== false) $out[$tax->name] = $tax;
	}
	foreach (['kundenkategorie','kontakte_kundenkategorie','kontakt_kundenkategorie','kontakte_kategorien','kontakt_kategorien','category'] as $pref) {
		if (\taxonomy_exists($pref)) $out[$pref] = \get_taxonomy($pref);
	}
	return array_values($out);
}

/**
 * Kategorien zuweisen – akzeptiert Namen/Slugs/IDs (gemischt), legt fehlende an.
 */
function cmx_assign_categories(int $post_id, array $values, ?string $explicit_tax = null): void {
	$values = array_values(array_filter(array_map('trim',$values), fn($v)=>$v!==''));
	if (!$values) return;

	$tax_list = $explicit_tax && \taxonomy_exists($explicit_tax)
		? [\get_taxonomy($explicit_tax)]
		: cmx_detect_category_taxonomies_for_kontakte();

	if (!$tax_list) return;

	foreach ($tax_list as $tax) {
		if (!$tax) continue;
		$term_ids = [];
		foreach ($values as $v) {
			$term = null;
			if (is_numeric($v)) {
				$term = \get_term((int)$v, $tax->name);
			} else {
				$term = \get_term_by('slug', $v, $tax->name);
				if (!$term || \is_wp_error($term)) $term = \get_term_by('name', $v, $tax->name);
			}
			if ($term && !\is_wp_error($term)) {
				$term_ids[] = (int)$term->term_id;
			} else {
				$created = \wp_insert_term($v, $tax->name, ['slug'=>sanitize_title($v)]);
				if (!\is_wp_error($created) && isset($created['term_id'])) $term_ids[] = (int)$created['term_id'];
			}
		}
		if ($term_ids) \wp_set_object_terms($post_id, $term_ids, $tax->name, false);
	}
}

function cmx_kontakte_import_cleanup_dir(string $dir): void {
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
			cmx_kontakte_import_cleanup_dir($path);
		} elseif (\is_file($path)) {
			@unlink($path);
		}
	}

	@rmdir($dir);
}

function cmx_kontakte_import_extract_zip(string $zip_file) {
	if (!\class_exists('\\ZipArchive')) {
		return new \WP_Error('zip_missing', 'ZIP-Import nicht verfügbar (ZipArchive fehlt).');
	}

	$tmpDir = \function_exists('\\wp_tempnam') ? \wp_tempnam('cmx-kontakte-import-') : \tempnam(\sys_get_temp_dir(), 'cmx-kontakte-import-');
	if (!$tmpDir) {
		return new \WP_Error('zip_temp', 'Temporärer Import-Ordner konnte nicht erstellt werden.');
	}
	if (\is_file($tmpDir)) {
		@unlink($tmpDir);
	}
	if (!\wp_mkdir_p($tmpDir)) {
		return new \WP_Error('zip_temp', 'Temporärer Import-Ordner konnte nicht erstellt werden.');
	}

	$zip = new \ZipArchive();
	if ($zip->open($zip_file) !== true) {
		cmx_kontakte_import_cleanup_dir($tmpDir);
		return new \WP_Error('zip_open', 'ZIP-Datei konnte nicht geöffnet werden.');
	}
	if (!$zip->extractTo($tmpDir)) {
		$zip->close();
		cmx_kontakte_import_cleanup_dir($tmpDir);
		return new \WP_Error('zip_extract', 'ZIP-Datei konnte nicht entpackt werden.');
	}
	$zip->close();

	$csvPath = '';
	$imageMap = [];
	$iterator = new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::SKIP_DOTS)
	);
	foreach ($iterator as $fileInfo) {
		if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) continue;
		$path = \wp_normalize_path($fileInfo->getPathname());
		$rel = \ltrim(\str_replace('\\', '/', (string) \substr($path, \strlen(\wp_normalize_path($tmpDir)))), '/');
		$ext = \strtolower((string) $fileInfo->getExtension());
		if ($ext === 'csv' && $csvPath === '') {
			$csvPath = $path;
		}
		if (\in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'bmp', 'ico'], true)) {
			$imageMap[$rel] = $path;
			$base = \basename($rel);
			if (!isset($imageMap[$base])) {
				$imageMap[$base] = $path;
			}
		}
	}

	if ($csvPath === '') {
		cmx_kontakte_import_cleanup_dir($tmpDir);
		return new \WP_Error('zip_csv_missing', 'In der ZIP wurde keine CSV-Datei gefunden.');
	}

	return [
		'csv_path' => $csvPath,
		'image_map' => $imageMap,
		'extract_dir' => $tmpDir,
	];
}

function cmx_kontakte_import_copy_logo_file_to_contact(int $post_id, string $source_path): bool {
	$source_path = \wp_normalize_path(\trim($source_path));
	if ($source_path === '' || !\is_readable($source_path) || !\is_file($source_path)) {
		return false;
	}

	$base_dir = cmx_local_base_path();
	if (!\is_dir($base_dir) && !\wp_mkdir_p($base_dir)) {
		return false;
	}

	$basename = 'kontakt-' . (int) $post_id . '-' . \basename($source_path);
	$target = \wp_normalize_path(\trailingslashit($base_dir) . $basename);
	if (!@copy($source_path, $target)) {
		return false;
	}

	@chmod($target, 0644);
	$version = @filemtime($target) ?: time();
	$base_url = cmx_local_base_url();
	$url_out = \trailingslashit($base_url) . \rawurlencode($basename) . '?v=' . $version;
	\update_post_meta($post_id, '_cmx_local_image_kontakte_path', $target);
	\update_post_meta($post_id, '_cmx_local_image_kontakte_url', $url_out);
	return true;
}

function cmx_kontakte_import_apply_logo(int $post_id, array $row, array $row_l, array $zip_image_map = []): void {
	$logo_zip_path = isset($row['logo_zip_path']) ? \trim((string) $row['logo_zip_path']) : (isset($row_l['logo_zip_path']) ? \trim((string) $row_l['logo_zip_path']) : '');
	if ($logo_zip_path !== '') {
		$normalized = \ltrim(\str_replace('\\', '/', $logo_zip_path), '/');
		$zip_source = $zip_image_map[$normalized] ?? $zip_image_map[\basename($normalized)] ?? '';
		if ($zip_source !== '' && cmx_kontakte_import_copy_logo_file_to_contact($post_id, (string) $zip_source)) {
			return;
		}
	}

	$logo_url  = isset($row['logo_url'])  ? \trim((string)$row['logo_url'])  : (isset($row_l['logo_url'])  ? \trim((string)$row_l['logo_url'])  : '');
	$logo_path = isset($row['logo_path']) ? \trim((string)$row['logo_path']) : (isset($row_l['logo_path']) ? \trim((string)$row_l['logo_path']) : '');
	if ($logo_url !== '' && \function_exists(__NAMESPACE__.'\\cmx_download_to_local_and_save_meta')) {
		$res = cmx_download_to_local_and_save_meta($post_id, $logo_url);
		if (!\is_wp_error($res)) {
			return;
		}
	}

	if ($logo_path !== '' && cmx_kontakte_import_copy_logo_file_to_contact($post_id, $logo_path)) {
		return;
	}
}


/**
 * 3) Import ausführen und nach Erfolg zurückleiten
 */
\add_action('load-edit.php', function() {
	global $typenow;
	if ($typenow !== CMX_PT_KONTAKTE) return;
	if (empty($_POST['cmx_do_import_kontakte']) || !\check_admin_referer('cmx_kontakte_import')) return;

	if (empty($_FILES['csv_file']['tmp_name'])) {
		\add_action('admin_notices', function(){ echo '<div class="notice notice-error"><p>Keine Datei ausgewählt.</p></div>'; });
		return;
	}

	$file = $_FILES['csv_file']['tmp_name'];
	$file_name = isset($_FILES['csv_file']['name']) ? (string) $_FILES['csv_file']['name'] : '';
	$file_ext = \strtolower((string) \pathinfo($file_name, PATHINFO_EXTENSION));
	$sep  = ';';
	$update_mode = !empty($_POST['update_mode']); // optional
	$import_file = $file;
	$zip_image_map = [];
	$cleanup_dir = '';

	if ($file_ext === 'zip') {
		$zip_import = cmx_kontakte_import_extract_zip($file);
		if (\is_wp_error($zip_import)) {
			$message = (string) $zip_import->get_error_message();
			\add_action('admin_notices', static function() use ($message){ echo '<div class="notice notice-error"><p>' . \esc_html($message) . '</p></div>'; });
			return;
		}
		$import_file = (string) ($zip_import['csv_path'] ?? '');
		$zip_image_map = (array) ($zip_import['image_map'] ?? []);
		$cleanup_dir = (string) ($zip_import['extract_dir'] ?? '');
	}

	$h = @\fopen($import_file, 'r');
	if (!$h) {
		if ($cleanup_dir !== '') cmx_kontakte_import_cleanup_dir($cleanup_dir);
		\add_action('admin_notices', function(){ echo '<div class="notice notice-error"><p>Datei konnte nicht gelesen werden.</p></div>'; });
		return;
	}

	// UTF-8 BOM
	$first = fread($h, 3);
	if ($first !== "\xEF\xBB\xBF") fseek($h, 0);

	$header = \fgetcsv($h, 0, $sep);
	if (!$header) {
		\fclose($h);
		if ($cleanup_dir !== '') cmx_kontakte_import_cleanup_dir($cleanup_dir);
		\add_action('admin_notices', function(){ echo '<div class="notice notice-error"><p>Leere oder ungültige CSV.</p></div>'; });
		return;
	}
	$header = array_map('trim', $header);
	if (!cmx_kontakte_import_header_is_valid($header)) {
		\fclose($h);
		if ($cleanup_dir !== '') cmx_kontakte_import_cleanup_dir($cleanup_dir);
		cmx_kontakte_import_redirect_notice([
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

	while (($line = \fgetcsv($h, 0, $sep)) !== false) {
		$row_number++;
		if (!array_filter($line, static fn($v) => $v !== null && $v !== '')) continue;
		$row = @array_combine($header, $line);
		if (!$row) {
			$notice['skipped'][] = 'Zeile ' . $row_number;
			continue;
		}
		$row_l = array_change_key_case($row, CASE_LOWER);

		$title = sanitize_text_field($row['Titel'] ?? ($row['post_title'] ?? ($row_l['titel'] ?? '')));
		if (!$title) {
			$notice['skipped'][] = 'Zeile ' . $row_number;
			continue;
		}

		$post_date = !empty($row['Erstellt_am']) ? sanitize_text_field($row['Erstellt_am']) : (!empty($row_l['erstellt_am']) ? sanitize_text_field($row_l['erstellt_am']) : current_time('mysql'));

		$postarr = [
			'post_type'   => CMX_PT_KONTAKTE,
			'post_title'  => $title,
			'post_status' => 'publish',
			'post_date'   => $post_date,
		];

		if (!empty($row['post_name']))   $postarr['post_name'] = sanitize_title($row['post_name']);
		if (!empty($row['post_author'])) {
			$author = $row['post_author'];
			if (is_numeric($author)) $postarr['post_author'] = (int)$author;
			else { $u = \get_user_by('login', (string)$author); if ($u) $postarr['post_author'] = (int)$u->ID; }
		}

		$is_update = false;
		if ($update_mode) {
			$existing_id = cmx_import_find_existing_kontakt_id_by_title($title);
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

		/* === Metas gemäss Export === */
		\update_post_meta($post_id, CMX_KONTAKTE_META_VORNAME,  (string)($row['vorname'] ?? ($row_l['vorname'] ?? '')));
		\update_post_meta($post_id, CMX_KONTAKTE_META_NACHNAME, (string)($row['nachname'] ?? ($row_l['nachname'] ?? '')));
		$priv_val = $row['privat'] ?? ($row_l['privat'] ?? '');
		\update_post_meta($post_id, CMX_KONTAKTE_META_PRIVAT,   ($priv_val !== '' && cmx_bool_from_csv($priv_val)) ? '1' : '0');
		\update_post_meta($post_id, CMX_KONTAKTE_META_URL,      cmx_normalize_url($row['url'] ?? ($row_l['url'] ?? '')));
		if (!empty($row['datum']) || !empty($row_l['datum'])) \update_post_meta($post_id, CMX_KONTAKTE_META_DATUM, (string)($row['datum'] ?? ($row_l['datum'] ?? '')));

		$rechnung_land_raw = (string) ($row['rechnung_land_slug'] ?? ($row_l['rechnung_land_slug'] ?? ''));
		if ($rechnung_land_raw === '') $rechnung_land_raw = (string) ($row['rechnung_land_label'] ?? ($row_l['rechnung_land_label'] ?? ''));
		if ($rechnung_land_raw === '') $rechnung_land_raw = (string) ($row['_cmx_rechnung_land'] ?? ($row_l['_cmx_rechnung_land'] ?? ''));
		$rechnung_land = \function_exists(__NAMESPACE__ . '\\cmx_kontakte_resolve_country_option_value')
			? (string) cmx_kontakte_resolve_country_option_value($rechnung_land_raw)
			: (
				\function_exists(__NAMESPACE__ . '\\cmx_kontakte_normalize_country_meta_value')
					? (string) cmx_kontakte_normalize_country_meta_value($rechnung_land_raw)
					: \strtolower($rechnung_land_raw)
			);

		$liefer_land_raw = (string) ($row['liefer_land_slug'] ?? ($row_l['liefer_land_slug'] ?? ''));
		if ($liefer_land_raw === '') $liefer_land_raw = (string) ($row['liefer_land_label'] ?? ($row_l['liefer_land_label'] ?? ''));
		if ($liefer_land_raw === '') $liefer_land_raw = (string) ($row['_cmx_liefer_land'] ?? ($row_l['_cmx_liefer_land'] ?? ''));
		$liefer_land = \function_exists(__NAMESPACE__ . '\\cmx_kontakte_resolve_country_option_value')
			? (string) cmx_kontakte_resolve_country_option_value($liefer_land_raw)
			: (
				\function_exists(__NAMESPACE__ . '\\cmx_kontakte_normalize_country_meta_value')
					? (string) cmx_kontakte_normalize_country_meta_value($liefer_land_raw)
					: \strtolower($liefer_land_raw)
			);

		\update_post_meta($post_id, CMX_RECHNUNG_META_STRASSE, (string)($row['rechnung_strasse'] ?? ($row_l['rechnung_strasse'] ?? '')));
		\update_post_meta($post_id, CMX_RECHNUNG_META_ZUSATZ,  (string)($row['rechnung_zusatz'] ?? ($row_l['rechnung_zusatz'] ?? '')));
		\update_post_meta($post_id, CMX_RECHNUNG_META_PLZ,     (string)($row['rechnung_plz'] ?? ($row_l['rechnung_plz'] ?? '')));
		\update_post_meta($post_id, CMX_RECHNUNG_META_ORT,     (string)($row['rechnung_ort'] ?? ($row_l['rechnung_ort'] ?? '')));
		\update_post_meta($post_id, CMX_RECHNUNG_META_LAND,    $rechnung_land);

		\update_post_meta($post_id, CMX_LIEFER_META_STRASSE, (string)($row['liefer_strasse'] ?? ($row_l['liefer_strasse'] ?? '')));
		\update_post_meta($post_id, CMX_LIEFER_META_ZUSATZ,  (string)($row['liefer_zusatz'] ?? ($row_l['liefer_zusatz'] ?? '')));
		\update_post_meta($post_id, CMX_LIEFER_META_PLZ,     (string)($row['liefer_plz'] ?? ($row_l['liefer_plz'] ?? '')));
		\update_post_meta($post_id, CMX_LIEFER_META_ORT,     (string)($row['liefer_ort'] ?? ($row_l['liefer_ort'] ?? '')));
		\update_post_meta($post_id, CMX_LIEFER_META_LAND,    $liefer_land);

		cmx_kontakte_import_apply_logo($post_id, $row, $row_l, $zip_image_map);

		// Stufe
		cmx_assign_stufe($post_id, (string)($row['stufe_slug'] ?? ''), (string)($row['stufe_name'] ?? ''));

		// Kommunikation
		for ($i=1;$i<=3;$i++){
			if (!empty($row['telefon_'.$i])) \update_post_meta($post_id, '_cmx_telefon_'.$i, (string)$row['telefon_'.$i]);
			if (!empty($row['email_'.$i]))   \update_post_meta($post_id, '_cmx_email_'.$i,   (string)$row['email_'.$i]);
		}
		$bundle = ['telefon'=>[], 'email'=>[]];
		for ($i=1;$i<=3;$i++){
			$bundle['telefon'][$i] = ['value'=>(string)($row['telefon_'.$i] ?? ''), 'label'=>''];
			$bundle['email'][$i]   = ['value'=>(string)($row['email_'.$i] ?? ''),   'label'=>''];
		}
		$tel_tax='kontakte_telefone'; $mail_tax='kontakte_emails';
		if (!empty($row['telefon_label_1'])) $bundle['telefon'][1]['label'] = (function($n,$t){ $term=\get_term_by('name',$n,$t); return $term&&!is_wp_error($term)?$term->slug:''; })((string)$row['telefon_label_1'],$tel_tax);
		if (!empty($row['telefon_label_2'])) $bundle['telefon'][2]['label'] = (function($n,$t){ $term=\get_term_by('name',$n,$t); return $term&&!is_wp_error($term)?$term->slug:''; })((string)$row['telefon_label_2'],$tel_tax);
		if (!empty($row['telefon_label_3'])) $bundle['telefon'][3]['label'] = (function($n,$t){ $term=\get_term_by('name',$n,$t); return $term&&!is_wp_error($term)?$term->slug:''; })((string)$row['telefon_label_3'],$tel_tax);
		if (!empty($row['email_label_1']))   $bundle['email'][1]['label']   = (function($n,$t){ $term=\get_term_by('name',$n,$t); return $term&&!is_wp_error($term)?$term->slug:''; })((string)$row['email_label_1'],$mail_tax);
		if (!empty($row['email_label_2']))   $bundle['email'][2]['label']   = (function($n,$t){ $term=\get_term_by('name',$n,$t); return $term&&!is_wp_error($term)?$term->slug:''; })((string)$row['email_label_2'],$mail_tax);
		if (!empty($row['email_label_3']))   $bundle['email'][3]['label']   = (function($n,$t){ $term=\get_term_by('name',$n,$t); return $term&&!is_wp_error($term)?$term->slug:''; })((string)$row['email_label_3'],$mail_tax);
		\update_post_meta($post_id, '_cmx_kommunikation', $bundle);

		/* === KATEGORIEN (NEU – kompatibel zum Export) =======================
		 * Unterstützte CSV-Spalten:
		 *  - 'kategorien' (Namen/Slugs/IDs, Komma ODER Pipe-getrennt)
		 *  - 'kundenkategorien', 'kundenkategorie'
		 *  - 'kategorien_slugs' (Pipe/Komma)
		 *  - 'kategorien_names' (Pipe/Komma)
		 *  - 'kategorien_ids'   (Pipe/Komma)
		 *  - 'category__{taxonomy}' (Werte Komma/Pipe)
		 *  - zusätzlich generisch: 'tax__{taxonomy}' (bleibt erhalten)
		 * =================================================================== */

		// 1) Sammelspalten ohne explizite Taxonomie
		$catVals = [];
		foreach (['kategorien','kundenkategorien','kundenkategorie'] as $ck) {
			if (!empty($row[$ck])) { $catVals = cmx_split_values($row[$ck]); break; }
		}
		// Falls keine, probiere die expliziten Export-Spalten
		if (!$catVals && !empty($row['kategorien_slugs'])) $catVals = cmx_split_values($row['kategorien_slugs']);
		if (!$catVals && !empty($row['kategorien_names'])) $catVals = cmx_split_values($row['kategorien_names']);
		if (!$catVals && !empty($row['kategorien_ids']))   $catVals = cmx_split_values($row['kategorien_ids']);

		if ($catVals) {
			// Auto-Detect passende Kategorie-Taxonomien
			cmx_assign_categories($post_id, $catVals, null);
		}

		// 2) category__{taxonomy}
		foreach ($row as $col => $val) {
			if (strpos($col, 'category__') === 0 && $val!=='') {
				$tx = substr($col, 10);
				if ($tx !== '') {
					$vals = cmx_split_values($val);
					cmx_assign_categories($post_id, $vals, $tx);
				}
			}
		}

		/* === Generische Spalten: tax__/meta__ (unverändert) =============== */
		foreach ($row as $key => $val) {
			if ($val === '' || $val === null) continue;

			// Taxo: tax__taxonomy (Namen/Slugs/IDs; legt fehlende an)
			if (strpos($key, 'tax__') === 0) {
				$tax = substr($key, 5);
				if ($tax) {
					$parts = cmx_split_values($val);
					$term_ids = [];
					foreach ($parts as $p) {
						if ($p === '') continue;
						if (is_numeric($p)) {
							$term = \get_term((int)$p, $tax);
							if ($term && !\is_wp_error($term)) { $term_ids[] = (int)$term->term_id; continue; }
						}
						$term = \get_term_by('slug', $p, $tax);
						if (!$term || \is_wp_error($term)) $term = \get_term_by('name', $p, $tax);
						if ($term && !\is_wp_error($term)) $term_ids[] = (int)$term->term_id;
						else {
							$created = \wp_insert_term($p, $tax, ['slug'=>sanitize_title($p)]);
							if (!\is_wp_error($created) && isset($created['term_id'])) $term_ids[] = (int)$created['term_id'];
						}
					}
					if ($term_ids) \wp_set_object_terms($post_id, $term_ids, $tax, false);
				}
			}

			// Meta: meta__key
			if (strpos($key, 'meta__') === 0) {
				$meta_key = substr($key, 6);
				if ($meta_key !== '') \update_post_meta($post_id, $meta_key, $val);
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

	\fclose($h);
	if ($cleanup_dir !== '') cmx_kontakte_import_cleanup_dir($cleanup_dir);

	cmx_kontakte_import_redirect_notice($notice);
});

/**
 * 4) Notice nach Redirect anzeigen
 */
\add_action('all_admin_notices', function() {
	if (empty($_GET['cmx_import_notice_kontakte'])) return;
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	$current_post_type = '';
	if ($screen && !empty($screen->post_type)) {
		$current_post_type = (string) $screen->post_type;
	} elseif (!empty($_GET['post_type'])) {
		$current_post_type = \sanitize_key((string) \wp_unslash($_GET['post_type']));
	}
	if ($current_post_type !== CMX_PT_KONTAKTE) return;

	$notice = \get_user_meta(\get_current_user_id(), cmx_kontakte_import_notice_key(), true);
	if (!$notice) return;
	\delete_user_meta(\get_current_user_id(), cmx_kontakte_import_notice_key());
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
