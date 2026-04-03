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

function cmx_artikel_import_row_value(array $row, array $row_l, string $key): string {
	if (isset($row[$key]) && \is_scalar($row[$key])) {
		return \trim((string) $row[$key]);
	}
	$key_l = \strtolower($key);
	if (isset($row_l[$key_l]) && \is_scalar($row_l[$key_l])) {
		return \trim((string) $row_l[$key_l]);
	}
	return '';
}

function cmx_artikel_import_bool_from_csv($value): bool {
	$value = \is_string($value) ? \strtolower(\trim($value)) : $value;
	return ($value === 1 || $value === '1' || $value === true || $value === 'true' || $value === 'ja' || $value === 'yes' || $value === 'y');
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

function cmx_artikel_import_sync_lieferanten_legacy_fields(int $post_id, array $rows): void {
	$first = \is_array($rows[0] ?? null) ? (array) $rows[0] : null;
	if ($first) {
		\update_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_LIEFERANT_ID') ? CMX_ARTIKEL_META_LIEFERANT_ID : '_cmx_art_lieferant_id', (int) ($first['lieferant_id'] ?? 0));
		\update_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_LIEFERANT_NR') ? CMX_ARTIKEL_META_LIEFERANT_NR : '_cmx_art_lieferant_nr', (string) ($first['lieferant_nr'] ?? ''));
		\update_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_BEZUGSQUELLE') ? CMX_ARTIKEL_META_BEZUGSQUELLE : '_cmx_artikel_bezugsquelle_url', (string) ($first['bezugsquelle'] ?? ''));
		\update_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_LIEFERZEIT') ? CMX_ARTIKEL_META_LIEFERZEIT : '_cmx_art_lieferzeit_tage', \max(0, (int) ($first['lieferzeit_tage'] ?? 0)));
		\update_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_LAGERBESTAND') ? CMX_ARTIKEL_META_LAGERBESTAND : '_cmx_artikel_lagerbestand', \max(0, (int) ($first['lagerbestand'] ?? 0)));
		$ek_first = (string) ($first['ek'] ?? '');
		if ($ek_first !== '' && \is_numeric($ek_first)) {
			\update_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_EK') ? CMX_ARTIKEL_META_EK : '_cmx_artikel_ek', \number_format((float) $ek_first, 2, '.', ''));
		}
		return;
	}

	\update_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_LIEFERANT_ID') ? CMX_ARTIKEL_META_LIEFERANT_ID : '_cmx_art_lieferant_id', 0);
	\update_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_LIEFERANT_NR') ? CMX_ARTIKEL_META_LIEFERANT_NR : '_cmx_art_lieferant_nr', '');
	\update_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_BEZUGSQUELLE') ? CMX_ARTIKEL_META_BEZUGSQUELLE : '_cmx_artikel_bezugsquelle_url', '');
	\update_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_LIEFERZEIT') ? CMX_ARTIKEL_META_LIEFERZEIT : '_cmx_art_lieferzeit_tage', 0);
	\update_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_LAGERBESTAND') ? CMX_ARTIKEL_META_LAGERBESTAND : '_cmx_artikel_lagerbestand', 0);
}

function cmx_artikel_import_collect_lieferanten_rows(int $post_id, array $row, array $row_l): array {
	$existing_rows = \function_exists(__NAMESPACE__ . '\\cmx_artikel_load_lieferanten_rows_unified')
		? (array) cmx_artikel_load_lieferanten_rows_unified($post_id)
		: [];
	$collected = [];
	$all_keys = \array_values(\array_unique(\array_merge(\array_keys($row), \array_keys($row_l))));
	foreach ($all_keys as $raw_key) {
		$key = \strtolower((string) $raw_key);
		if (!\preg_match('/^lieferant_(\d+)_(name|nr|ek|bezugsquelle|lieferzeit_tage|lagerbestand|notiz)$/', $key, $matches)) {
			continue;
		}
		$slot = (int) ($matches[1] ?? 0);
		if ($slot <= 0) {
			continue;
		}
		$field = (string) ($matches[2] ?? '');
		$collected[$slot][$field] = cmx_artikel_import_row_value($row, $row_l, (string) $raw_key);
	}

	$max_slot = \max(\count($existing_rows), $collected !== [] ? (int) \max(\array_keys($collected)) : 0);
	$out = [];
	for ($slot = 1; $slot <= $max_slot; $slot++) {
		$base = \is_array($existing_rows[$slot - 1] ?? null) ? (array) $existing_rows[$slot - 1] : [];
		$raw = \is_array($collected[$slot] ?? null) ? (array) $collected[$slot] : [];
		$name = (string) ($raw['name'] ?? '');
		$lieferant_id = $name !== ''
			? (int) cmx_artikel_import_find_lieferant_id_by_name($name)
			: (int) ($base['lieferant_id'] ?? 0);
		$row_out = [
			'lieferant_id' => \max(0, $lieferant_id),
			'lieferant_nr' => isset($raw['nr']) ? \sanitize_text_field((string) $raw['nr']) : (string) ($base['lieferant_nr'] ?? ''),
			'ek' => isset($raw['ek']) ? (string) $raw['ek'] : (string) ($base['ek'] ?? ''),
			'bezugsquelle' => isset($raw['bezugsquelle']) ? (string) $raw['bezugsquelle'] : (string) ($base['bezugsquelle'] ?? ''),
			'lieferzeit_tage' => isset($raw['lieferzeit_tage']) ? \max(0, (int) $raw['lieferzeit_tage']) : \max(0, (int) ($base['lieferzeit_tage'] ?? 0)),
			'lagerbestand' => isset($raw['lagerbestand']) ? \max(0, (int) $raw['lagerbestand']) : \max(0, (int) ($base['lagerbestand'] ?? 0)),
			'notiz' => isset($raw['notiz']) ? \sanitize_textarea_field((string) $raw['notiz']) : (string) ($base['notiz'] ?? ''),
		];
		if (
			(int) $row_out['lieferant_id'] <= 0
			&& (string) $row_out['lieferant_nr'] === ''
			&& (string) $row_out['ek'] === ''
			&& (string) $row_out['bezugsquelle'] === ''
			&& (int) $row_out['lieferzeit_tage'] === 0
			&& (int) $row_out['lagerbestand'] === 0
			&& (string) $row_out['notiz'] === ''
		) {
			continue;
		}
		$out[] = $row_out;
	}

	return $out;
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
		if (\preg_match('/^_cmx_art_lieferant_\d+_(id|nr|ek|bezugsquelle|lieferzeit_tage|lagerbestand|notiz)$/', (string)$meta_key)) {
			\delete_post_meta($post_id, $meta_key);
		}
	}
	\delete_post_meta($post_id, '_cmx_art_lieferanten_count');
}

function cmx_import_clear_variant_meta(int $post_id): void {
	if (\function_exists(__NAMESPACE__ . '\\cmx_artikel_variant_clear_flat_rows')) {
		cmx_artikel_variant_clear_flat_rows($post_id);
	} else {
		foreach (\array_keys((array) \get_post_meta($post_id)) as $meta_key) {
			if (\preg_match('/^_cmx_artikel_variant_\d+_(sku|anzahl|left_taxonomy|left_term_id|right_taxonomy|right_term_id|einheit_term_id|ek|aufwand|vk|belegtext|verkaufbar|katalog|woo_product_id|woo_variation_id|woo_variation_sku)$/', (string) $meta_key)) {
				\delete_post_meta($post_id, $meta_key);
			}
		}
		\delete_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VARIANT_COUNT') ? CMX_ARTIKEL_META_VARIANT_COUNT : '_cmx_artikel_variant_count');
	}
}

function cmx_artikel_import_decode_meta_value(string $raw) {
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

function cmx_artikel_import_resolve_term_id(string $taxonomy, string $slug = '', string $name = ''): int {
	$taxonomy = \sanitize_key($taxonomy);
	if ($taxonomy === '' || !\taxonomy_exists($taxonomy)) {
		return 0;
	}

	$slug = \sanitize_title($slug);
	$name = \trim($name);
	if ($slug !== '') {
		$term = \get_term_by('slug', $slug, $taxonomy);
		if ($term && !\is_wp_error($term) && isset($term->term_id)) {
			return (int) $term->term_id;
		}
	}
	if ($name !== '') {
		$term = \get_term_by('name', $name, $taxonomy);
		if ($term && !\is_wp_error($term) && isset($term->term_id)) {
			return (int) $term->term_id;
		}
		$created = \wp_insert_term($name, $taxonomy, $slug !== '' ? ['slug' => $slug] : []);
		if (!\is_wp_error($created) && isset($created['term_id'])) {
			return (int) $created['term_id'];
		}
	}
	return 0;
}

function cmx_artikel_import_collect_variant_rows(array $row, array $row_l): array {
	$collected = [];
	$all_keys = \array_values(\array_unique(\array_merge(\array_keys($row), \array_keys($row_l))));
	foreach ($all_keys as $raw_key) {
		$key = \strtolower((string) $raw_key);
		if (!\preg_match('/^variant_(\d+)_(sku|anzahl|left_taxonomy|left_term_slug|left_term_name|right_taxonomy|right_term_slug|right_term_name|einheit_slug|einheit_name|ek|aufwand|vk|belegtext|verkaufbar|katalog|woo_product_id|woo_variation_id|woo_variation_sku)$/', $key, $matches)) {
			continue;
		}
		$slot = (int) ($matches[1] ?? 0);
		if ($slot <= 0) {
			continue;
		}
		$field = (string) ($matches[2] ?? '');
		$collected[$slot][$field] = cmx_artikel_import_row_value($row, $row_l, (string) $raw_key);
	}

	$left_choices = \function_exists(__NAMESPACE__ . '\\cmx_artikel_variant_taxonomy_choices')
		? (array) cmx_artikel_variant_taxonomy_choices('Grössen')
		: [];
	$right_choices = \function_exists(__NAMESPACE__ . '\\cmx_artikel_variant_taxonomy_choices')
		? (array) cmx_artikel_variant_taxonomy_choices('Farben')
		: [];

	if ($collected === []) {
		$legacy_blob = cmx_artikel_import_row_value($row, $row_l, 'meta___cmx_artikel_variant_rows');
		$decoded = $legacy_blob !== '' ? cmx_artikel_import_decode_meta_value($legacy_blob) : null;
		if (\is_array($decoded)) {
			$out = [];
			foreach ($decoded as $variant_row) {
				if (!\is_array($variant_row)) {
					continue;
				}
				$out[] = \function_exists(__NAMESPACE__ . '\\cmx_artikel_variant_row_normalize')
					? (array) cmx_artikel_variant_row_normalize($variant_row, $left_choices, $right_choices)
					: (array) $variant_row;
			}
			return $out;
		}
		return [];
	}

	\ksort($collected, \SORT_NUMERIC);
	$out = [];
	foreach ($collected as $variant_row) {
		if (!\is_array($variant_row)) {
			continue;
		}
		$left_taxonomy = \sanitize_key((string) ($variant_row['left_taxonomy'] ?? ''));
		$right_taxonomy = \sanitize_key((string) ($variant_row['right_taxonomy'] ?? ''));
		$einheit_taxonomy = \defined(__NAMESPACE__ . '\\TAX_ARTIKEL_EINHEITEN') ? (string) TAX_ARTIKEL_EINHEITEN : '';

		$row_out = [
			'sku' => (string) ($variant_row['sku'] ?? ''),
			'anzahl' => (string) ($variant_row['anzahl'] ?? ''),
			'left_taxonomy' => $left_taxonomy,
			'left_term_id' => cmx_artikel_import_resolve_term_id($left_taxonomy, (string) ($variant_row['left_term_slug'] ?? ''), (string) ($variant_row['left_term_name'] ?? '')),
			'right_taxonomy' => $right_taxonomy,
			'right_term_id' => cmx_artikel_import_resolve_term_id($right_taxonomy, (string) ($variant_row['right_term_slug'] ?? ''), (string) ($variant_row['right_term_name'] ?? '')),
			'einheit_term_id' => cmx_artikel_import_resolve_term_id($einheit_taxonomy, (string) ($variant_row['einheit_slug'] ?? ''), (string) ($variant_row['einheit_name'] ?? '')),
			'ek' => (string) ($variant_row['ek'] ?? ''),
			'aufwand' => (string) ($variant_row['aufwand'] ?? ''),
			'vk' => (string) ($variant_row['vk'] ?? ''),
			'belegtext' => (string) ($variant_row['belegtext'] ?? ''),
			'verkaufbar' => cmx_artikel_import_bool_from_csv($variant_row['verkaufbar'] ?? '') ? 1 : 0,
			'katalog' => cmx_artikel_import_bool_from_csv($variant_row['katalog'] ?? '') ? 1 : 0,
			'woo_product_id' => (int) ($variant_row['woo_product_id'] ?? 0),
			'woo_variation_id' => (int) ($variant_row['woo_variation_id'] ?? 0),
			'woo_variation_sku' => (string) ($variant_row['woo_variation_sku'] ?? ''),
		];

		$out[] = \function_exists(__NAMESPACE__ . '\\cmx_artikel_variant_row_normalize')
			? (array) cmx_artikel_variant_row_normalize($row_out, $left_choices, $right_choices)
			: $row_out;
	}

	return $out;
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

function cmx_artikel_import_gallery_meta_key(): string {
	if (\function_exists('\\CLOUDMEISTER\\CMX\\MisBuero\\cmx_li_gallery_meta_key')) {
		return (string) \CLOUDMEISTER\CMX\MisBuero\cmx_li_gallery_meta_key();
	}
	return '_cmx_local_image_artikel_gallery';
}

function cmx_artikel_import_gallery_get(int $post_id): array {
	if (\function_exists('\\CLOUDMEISTER\\CMX\\MisBuero\\cmx_li_gallery_get')) {
		$items = \CLOUDMEISTER\CMX\MisBuero\cmx_li_gallery_get($post_id, '_cmx_local_image_artikel');
		return \is_array($items) ? \array_values(\array_filter($items, static function($item): bool {
			return \is_array($item);
		})) : [];
	}

	$legacy_path = (string) \get_post_meta($post_id, '_cmx_local_image_artikel_path', true);
	$legacy_url = (string) \get_post_meta($post_id, '_cmx_local_image_artikel_url', true);
	if ($legacy_path === '' && $legacy_url === '') {
		return [];
	}
	return [[
		'id' => 'legacy',
		'path' => $legacy_path,
		'url' => $legacy_url,
	]];
}

function cmx_artikel_import_delete_existing_gallery_images(int $post_id): void {
	$gallery = cmx_artikel_import_gallery_get($post_id);
	foreach ($gallery as $item) {
		$path = \wp_normalize_path((string) ($item['path'] ?? ''));
		if ($path !== '' && \is_file($path)) {
			@unlink($path);
		}
	}

	\delete_post_meta($post_id, cmx_artikel_import_gallery_meta_key());
	\delete_post_meta($post_id, '_cmx_local_image_artikel_path');
	\delete_post_meta($post_id, '_cmx_local_image_artikel_url');
}

function cmx_artikel_import_unique_target_path(string $base_dir, string $base_name, string $ext): string {
	$base_name = \sanitize_file_name($base_name);
	$base_name = \trim((string) \preg_replace('~\.[a-z0-9]+$~i', '', $base_name), "-_. \t\n\r\0\x0B");
	if ($base_name === '') {
		$base_name = 'bild';
	}
	$ext = \ltrim(\strtolower($ext), '.');
	$ext = $ext !== '' ? '.' . $ext : '';

	$target = \wp_normalize_path(\trailingslashit($base_dir) . $base_name . $ext);
	if (!\is_file($target)) {
		return $target;
	}

	$counter = 1;
	do {
		$target = \wp_normalize_path(\trailingslashit($base_dir) . $base_name . '-' . $counter . $ext);
		$counter++;
	} while (\is_file($target));

	return $target;
}

function cmx_artikel_import_copy_image_file_to_post(int $post_id, string $source_path): array {
	$source_path = \wp_normalize_path(\trim($source_path));
	if ($source_path === '' || !\is_readable($source_path) || !\is_file($source_path)) {
		return [];
	}

	$ext = \strtolower((string) \pathinfo($source_path, PATHINFO_EXTENSION));
	if (!\in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'ico'], true)) {
		return [];
	}

	$base_dir = cmx_artikel_import_image_base_path();
	if (!\is_dir($base_dir) && !\wp_mkdir_p($base_dir)) {
		return [];
	}

	$source_name = (string) \pathinfo($source_path, PATHINFO_FILENAME);
	$target = cmx_artikel_import_unique_target_path($base_dir, $source_name, $ext);
	if (!@copy($source_path, $target)) {
		return [];
	}

	@chmod($target, 0644);
	$version = @filemtime($target) ?: \time();
	$base_url = cmx_artikel_import_image_base_url();
	$url = \trailingslashit($base_url) . \rawurlencode(\basename($target)) . '?v=' . $version;

	return [
		'id' => 'img_' . \wp_generate_password(12, false, false),
		'path' => $target,
		'url' => $url,
	];
}

function cmx_artikel_import_update_gallery(int $post_id, array $items): void {
	if (\function_exists('\\CLOUDMEISTER\\CMX\\MisBuero\\cmx_li_gallery_update')) {
		\CLOUDMEISTER\CMX\MisBuero\cmx_li_gallery_update($post_id, '_cmx_local_image_artikel', $items);
		return;
	}

	$items = \array_values(\array_filter($items, static function($item): bool {
		return \is_array($item) && (((string) ($item['path'] ?? '')) !== '' || ((string) ($item['url'] ?? '')) !== '');
	}));
	if ($items === []) {
		\delete_post_meta($post_id, cmx_artikel_import_gallery_meta_key());
		\delete_post_meta($post_id, '_cmx_local_image_artikel_path');
		\delete_post_meta($post_id, '_cmx_local_image_artikel_url');
		return;
	}

	\update_post_meta($post_id, cmx_artikel_import_gallery_meta_key(), $items);
	\update_post_meta($post_id, '_cmx_local_image_artikel_path', (string) ($items[0]['path'] ?? ''));
	\update_post_meta($post_id, '_cmx_local_image_artikel_url', (string) ($items[0]['url'] ?? ''));
}

function cmx_artikel_import_apply_image(int $post_id, array $row, array $row_l, array $zip_image_map = []): void {
	if (empty($zip_image_map)) {
		return;
	}

	$raw_gallery = (string) ($row['gallery_image_zip_paths'] ?? ($row_l['gallery_image_zip_paths'] ?? ''));
	$candidates = [];
	if ($raw_gallery !== '') {
		$candidates = \array_values(\array_filter(\array_map('trim', \explode('|', $raw_gallery))));
	}
	if ($candidates === []) {
		$candidates = [
			(string) ($row['local_image_zip_path'] ?? ($row_l['local_image_zip_path'] ?? '')),
			(string) ($row['featured_image_zip_path'] ?? ($row_l['featured_image_zip_path'] ?? '')),
		];
	}

	$resolved_sources = [];
	foreach ($candidates as $candidate) {
		$candidate = \trim((string) $candidate);
		if ($candidate === '') {
			continue;
		}
		$normalized = \ltrim(\str_replace('\\', '/', $candidate), '/');
		$zip_source = $zip_image_map[$normalized] ?? $zip_image_map[\basename($normalized)] ?? '';
		if ($zip_source === '') {
			continue;
		}
		$resolved = \wp_normalize_path((string) $zip_source);
		if ($resolved === '' || isset($resolved_sources[$resolved])) {
			continue;
		}
		$resolved_sources[$resolved] = true;
	}

	if ($resolved_sources === []) {
		return;
	}

	cmx_artikel_import_delete_existing_gallery_images($post_id);

	$items = [];
	foreach (\array_keys($resolved_sources) as $source_path) {
		$item = cmx_artikel_import_copy_image_file_to_post($post_id, (string) $source_path);
		if ($item !== []) {
			$items[] = $item;
		}
	}

	if ($items !== []) {
		cmx_artikel_import_update_gallery($post_id, $items);
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_import_notice_key')) {
	function cmx_artikel_import_notice_key(): string {
		return 'cmx_import_notice_artikel';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_import_header_is_valid')) {
	function cmx_artikel_import_header_is_valid(array $header): bool {
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_import_redirect_notice')) {
	function cmx_artikel_import_redirect_notice(array $notice): void {
		\update_user_meta(\get_current_user_id(), cmx_artikel_import_notice_key(), $notice);
		\wp_safe_redirect(\add_query_arg([
			'post_type' => CMX_PT_ARTIKEL,
			'cmx_import_notice_artikel' => 1,
		], \admin_url('edit.php')));
		exit;
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

	$header = \fgetcsv($handle, 0, $sep, '"', '\\');
	if (!$header) {
		\fclose($handle);
		if ($cleanup_dir !== '') cmx_artikel_import_cleanup_dir($cleanup_dir);
		\add_action('admin_notices', function(){
			echo '<div class="notice notice-error"><p>Leere oder ungültige CSV.</p></div>';
		});
		return;
	}
	$header = \array_map('trim', $header);
	if (!cmx_artikel_import_header_is_valid($header)) {
		\fclose($handle);
		if ($cleanup_dir !== '') cmx_artikel_import_cleanup_dir($cleanup_dir);
		cmx_artikel_import_redirect_notice([
			'type' => 'error',
			'message' => 'Falsches Format',
		]);
	}

	$has_lieferanten_data_in_csv = false;
	$has_variant_data_in_csv = false;
	foreach ($header as $column) {
		if (
			\preg_match('/^meta__(?:_cmx_art_lieferant_\d+_(?:id|nr|ek|bezugsquelle|lieferzeit_tage|lagerbestand|notiz)|_cmx_art_lieferanten_count|_cmx_art_lieferanten_liste)$/', (string) $column)
			|| \preg_match('/^lieferant_\d+_(?:name|nr|ek|bezugsquelle|lieferzeit_tage|lagerbestand|notiz)$/i', (string) $column)
		) {
			$has_lieferanten_data_in_csv = true;
		}
		if ((string) $column === 'variant_count' || \preg_match('/^variant_\d+_/', (string) $column) || (string) $column === 'meta___cmx_artikel_variant_rows') {
			$has_variant_data_in_csv = true;
		}
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
		if (\is_wp_error($post_id)) {
			$notice['failed'][] = $title;
			continue;
		}

		if ($is_update && $has_lieferanten_data_in_csv) {
			cmx_import_clear_lieferanten_meta((int) $post_id);
		}
		if ($is_update && $has_variant_data_in_csv) {
			cmx_import_clear_variant_meta((int) $post_id);
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
				if (
					$meta_key === '_cmx_local_image_artikel_gallery'
					|| $meta_key === '_cmx_local_image_artikel_path'
					|| $meta_key === '_cmx_local_image_artikel_url'
					|| $meta_key === '_cmx_art_lieferanten_liste'
					|| $meta_key === '_cmx_artikel_variant_rows'
					|| \in_array($meta_key, ['_thumbnail_id', '_edit_lock', '_edit_last'], true)
				) {
					continue;
				}

				$meta_raw = \is_string($value) ? \trim($value) : '';
				$meta_value = $meta_raw;
				if ($meta_value === '') {
					\delete_post_meta($post_id, $meta_key);
				} else {
					$meta_value = cmx_artikel_import_decode_meta_value($meta_raw);
					\update_post_meta($post_id, $meta_key, $meta_value);
				}
			}
		}

		if ($has_lieferanten_data_in_csv && \function_exists(__NAMESPACE__ . '\\cmx_artikel_save_lieferanten_rows_unified')) {
			$lieferanten_rows = cmx_artikel_import_collect_lieferanten_rows((int) $post_id, $row, $row_l);
			cmx_artikel_save_lieferanten_rows_unified((int) $post_id, $lieferanten_rows);
			cmx_artikel_import_sync_lieferanten_legacy_fields((int) $post_id, $lieferanten_rows);
		} else {
			cmx_artikel_import_apply_lieferanten_names((int) $post_id, $row);
		}
		if ($has_variant_data_in_csv && \function_exists(__NAMESPACE__ . '\\cmx_artikel_variant_rows_persist')) {
			$variant_rows = cmx_artikel_import_collect_variant_rows($row, $row_l);
			cmx_artikel_variant_rows_persist((int) $post_id, $variant_rows);
		}
		cmx_artikel_import_apply_image((int) $post_id, $row, $row_l, $zip_image_map);

		$stored_title = \trim((string) \get_the_title($post_id));
		$label = $stored_title !== '' ? $stored_title : $title;
		if ($is_update) {
			$notice['updated'][] = $label;
		} else {
			$notice['imported'][] = $label;
		}
	}

	\fclose($handle);
	if ($cleanup_dir !== '') cmx_artikel_import_cleanup_dir($cleanup_dir);

	cmx_artikel_import_redirect_notice($notice);
});

/**
 * 4. Notice nach Redirect anzeigen
 */
\add_action('all_admin_notices', function() {
	if (empty($_GET['cmx_import_notice_artikel'])) return;
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	$current_post_type = '';
	if ($screen && !empty($screen->post_type)) {
		$current_post_type = (string) $screen->post_type;
	} elseif (!empty($_GET['post_type'])) {
		$current_post_type = \sanitize_key((string) \wp_unslash($_GET['post_type']));
	}
	if ($current_post_type !== CMX_PT_ARTIKEL) return;

	$notice = \get_user_meta(\get_current_user_id(), cmx_artikel_import_notice_key(), true);
	if (!$notice) return;

	\delete_user_meta(\get_current_user_id(), cmx_artikel_import_notice_key());
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
