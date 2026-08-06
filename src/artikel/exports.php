<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/* ===== Export-Link in der Artikelliste ===== */
\add_filter('views_edit-artikel', function(array $views){
	if (!\current_user_can('edit_posts')) return $views;

	$args = $_GET ?? [];
	unset(
		$args['paged'],
		$args['action'],
		$args['action2'],
		$args['_wpnonce'],
		$args['_wp_http_referer'],
		$args['orderby'],
		$args['order']
	);
	$args['action'] = 'cmx_export_artikel_list';
	$args['cmx_export_format'] = 'zip';

	$url  = \wp_nonce_url(\add_query_arg($args, \admin_url('admin-post.php')), 'cmx_export_artikel_list');
	$link = '<a href="' . \esc_url($url) . '">exportieren</a>';

	$new_views = [];
	$inserted = false;
	foreach ($views as $key => $html) {
		$new_views[$key] = $html;
		if ($key === 'trash' && !$inserted) {
			$new_views['cmx_export_artikel_list'] = $link;
			$inserted = true;
		}
	}
	if (!$inserted) {
		foreach ($new_views as $key => $html) {
			if ($key === 'all' && !$inserted) {
				$new_views['cmx_export_artikel_list'] = $link;
				$inserted = true;
			}
		}
	}
	if (!$inserted) {
		$new_views['cmx_export_artikel_list'] = $link;
	}

	return $new_views;
});

/* ===== Export-Handler ===== */
\add_action('admin_post_cmx_export_artikel_list', function () {
	if (!\current_user_can('edit_posts')) \wp_die('Keine Berechtigung.');
	if (!\wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'cmx_export_artikel_list')) \wp_die('Ungültige Anfrage.');

	$post_ids = \function_exists(__NAMESPACE__ . '\\cmxal_collect_export_artikel_ids')
		? (array) cmxal_collect_export_artikel_ids()
		: [];
	$format = \function_exists(__NAMESPACE__ . '\\cmxal_requested_export_format')
		? (string) cmxal_requested_export_format()
		: 'zip';

	if ($format === 'zip') {
		cmxal_stream_artikel_export_zip_from_ids($post_ids);
	}

	cmxal_stream_artikel_csv_from_ids($post_ids);
});

/* ===== Helpers ===== */
if (!\function_exists(__NAMESPACE__ . '\\cmxal_requested_export_format')) {
	function cmxal_requested_export_format(): string {
		$format = isset($_REQUEST['cmx_export_format']) ? \sanitize_key((string) \wp_unslash($_REQUEST['cmx_export_format'])) : 'zip';
		return \in_array($format, ['csv', 'zip'], true) ? $format : 'zip';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxal_export_stamp')) {
	function cmxal_export_stamp(): string {
		$raw = isset($_REQUEST['cmx_export_stamp']) ? \sanitize_text_field((string) \wp_unslash($_REQUEST['cmx_export_stamp'])) : '';
		if (\preg_match('/^\d{8}-\d{6}$/', $raw)) {
			return $raw;
		}
		return \function_exists(__NAMESPACE__ . '\\cmx_export_now_stamp')
			? (string) cmx_export_now_stamp()
			: (\function_exists('\\wp_date')
				? (string) \wp_date('Ymd-His')
				: (\function_exists('\\date_i18n')
					? (string) \date_i18n('Ymd-His')
					: (string) \date('Ymd-His')));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxal_export_base_filename')) {
	function cmxal_export_base_filename(): string {
		$prefix = \function_exists(__NAMESPACE__ . '\\cmx_export_actor_prefix')
			? (string) cmx_export_actor_prefix()
			: 'misbuero';
		return $prefix . '-artikel-export-' . cmxal_export_stamp();
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxal_export_filename')) {
	function cmxal_export_filename(string $ext = 'csv'): string {
		$ext = \strtolower(\trim($ext, ". \t\n\r\0\x0B"));
		if ($ext === '') $ext = 'dat';
		return cmxal_export_base_filename() . '.' . $ext;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxal_collect_export_artikel_ids')) {
	function cmxal_query_filtered_artikel_ids_from_current_request(array $base_query_vars): array {
		$backup_get = $_GET ?? [];
		foreach ((array) $_REQUEST as $key => $value) {
			if (!\array_key_exists($key, $_GET)) {
				$_GET[$key] = $value;
			}
		}

		$query = new \WP_Query();
		$old_wp_query = $GLOBALS['wp_query'] ?? null;
		$old_wp_the_query = $GLOBALS['wp_the_query'] ?? null;
		$GLOBALS['wp_query'] = $query;
		$GLOBALS['wp_the_query'] = $query;

		try {
			$query->query($base_query_vars);
			return \array_values(\array_filter(\array_map('intval', (array) $query->posts)));
		} finally {
			$_GET = $backup_get;
			$GLOBALS['wp_query'] = $old_wp_query;
			$GLOBALS['wp_the_query'] = $old_wp_the_query;
		}
	}

	function cmxal_collect_export_artikel_ids(): array {
		$selected_ids = isset($_REQUEST['post']) ? \array_filter(\array_map('intval', (array) $_REQUEST['post'])) : [];

		$qv = [
			'post_type'      => 'artikel',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		];

		if ($selected_ids) {
			$qv['post__in'] = $selected_ids;
			$qv['orderby'] = 'post__in';
			$qv['post_status'] = 'any';
		} else {
			$post_status = isset($_REQUEST['post_status']) ? \sanitize_key((string) $_REQUEST['post_status']) : '';
			if ($post_status !== '' && $post_status !== 'all') {
				$qv['post_status'] = $post_status;
			} else {
				$qv['post_status'] = ['publish', 'future', 'draft', 'pending', 'private'];
			}

			foreach (['s', 'author', 'm'] as $key) {
				$value = $_REQUEST[$key] ?? '';
				if ($value !== '' && $value !== '0' && $value !== '-1') {
					$qv[$key] = $value;
				}
			}
			foreach (['orderby', 'order'] as $key) {
				$value = $_REQUEST[$key] ?? '';
				if ($value !== '') {
					$qv[$key] = \sanitize_text_field((string) $value);
				}
			}

			return \function_exists(__NAMESPACE__ . '\\cmxal_query_filtered_artikel_ids_from_current_request')
				? (array) cmxal_query_filtered_artikel_ids_from_current_request($qv)
				: [];
		}

		$q = new \WP_Query($qv);
		return \array_values(\array_filter(\array_map('intval', (array) $q->posts)));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxal_artikel_image_entries')) {
	function cmxal_get_artikel_gallery_items(int $post_id): array {
		if (\function_exists('\\CLOUDMEISTER\\CMX\\MisBuero\\cmx_li_gallery_get')) {
			$items = \CLOUDMEISTER\CMX\MisBuero\cmx_li_gallery_get($post_id, '_cmx_local_image_artikel');
			return \is_array($items) ? \array_values(\array_filter($items, static function($item): bool {
				return \is_array($item);
			})) : [];
		}

		$legacy_path = \trim((string) \get_post_meta($post_id, '_cmx_local_image_artikel_path', true));
		$legacy_url = \trim((string) \get_post_meta($post_id, '_cmx_local_image_artikel_url', true));
		if ($legacy_path === '' && $legacy_url === '') {
			return [];
		}

		return [[
			'id' => 'legacy',
			'path' => $legacy_path,
			'url' => $legacy_url,
		]];
	}

	function cmxal_local_file_from_url(string $url): string {
		$url = \trim($url);
		if ($url === '') {
			return '';
		}

		$uploads = \wp_get_upload_dir();
		$baseurl = isset($uploads['baseurl']) ? \rtrim((string) $uploads['baseurl'], '/') : '';
		$basedir = isset($uploads['basedir']) ? \wp_normalize_path((string) $uploads['basedir']) : '';
		if ($baseurl === '' || $basedir === '') {
			return '';
		}

		$path = (string) (\parse_url($url, PHP_URL_PATH) ?? '');
		if ($path === '') {
			return '';
		}

		$base_path = (string) (\parse_url($baseurl, PHP_URL_PATH) ?? '');
		$relative = '';
		if ($base_path !== '' && \strpos($path, $base_path) === 0) {
			$relative = \ltrim((string) \substr($path, \strlen($base_path)), '/');
		} else {
			$uploads_marker = '/wp-content/uploads/';
			$marker_pos = \strpos($path, $uploads_marker);
			if ($marker_pos !== false) {
				$relative = \ltrim((string) \substr($path, $marker_pos + \strlen($uploads_marker)), '/');
			}
		}
		if ($relative === '') {
			return '';
		}

		$file = \wp_normalize_path(\trailingslashit($basedir) . $relative);
		return \is_file($file) ? $file : '';
	}

	function cmxal_collect_candidate_image_paths(int $post_id): array {
		$candidates = [];
		$gallery_items = \function_exists(__NAMESPACE__ . '\\cmxal_get_artikel_gallery_items')
			? (array) cmxal_get_artikel_gallery_items($post_id)
			: [];

		foreach ($gallery_items as $index => $item) {
			$path = \trim((string) ($item['path'] ?? ''));
			$url = \trim((string) ($item['url'] ?? ''));
			if ($path === '' && $url === '') {
				continue;
			}
			$candidates[] = [
				'path' => $path,
				'url' => $url,
				'suffix' => $index === 0 ? 'bild' : ('bild-' . ($index + 1)),
			];
		}

		$local_path = \trim((string) \get_post_meta($post_id, '_cmx_local_image_artikel_path', true));
		if ($local_path !== '') {
			$candidates[] = ['path' => $local_path, 'url' => '', 'suffix' => 'bild'];
		}

		$local_url = \trim((string) \get_post_meta($post_id, '_cmx_local_image_artikel_url', true));
		if ($local_url !== '') {
			$resolved = cmxal_local_file_from_url($local_url);
			$candidates[] = ['path' => $resolved, 'url' => $local_url, 'suffix' => 'bild'];
		}

		$thumb_id = (int) \get_post_thumbnail_id($post_id);
		if ($thumb_id > 0) {
			$thumb_path = (string) \get_attached_file($thumb_id);
			if ($thumb_path !== '') {
				$candidates[] = ['path' => $thumb_path, 'url' => '', 'suffix' => 'featured'];
			}

			$thumb_url = (string) \wp_get_attachment_url($thumb_id);
			if ($thumb_url !== '') {
				$resolved = cmxal_local_file_from_url($thumb_url);
				$candidates[] = ['path' => $resolved, 'url' => $thumb_url, 'suffix' => 'featured'];
			}
		}

		return $candidates;
	}

	function cmxal_artikel_image_entries(int $post_id): array {
		$post_id = (int) $post_id;
		if ($post_id <= 0) {
			return [];
		}

		$post = \get_post($post_id);
		if (!$post instanceof \WP_Post) {
			return [];
		}

		$title = \function_exists(__NAMESPACE__ . '\\cmx_export_slugify')
			? (string) cmx_export_slugify((string) $post->post_title, 'artikel-' . $post_id)
			: ('artikel-' . $post_id);

		$candidates = \function_exists(__NAMESPACE__ . '\\cmxal_collect_candidate_image_paths')
			? (array) cmxal_collect_candidate_image_paths($post_id)
			: [];

		$entries = [];
		$used_names = [];
		$seen_real = [];
		foreach ($candidates as $candidate) {
			$path = (string) ($candidate['path'] ?? '');
			$url = \trim((string) ($candidate['url'] ?? ''));
			$real = '';
			if ($path !== '' && \is_file($path)) {
				$real = (string) \realpath($path);
				if ($real !== '' && isset($seen_real[$real])) {
					continue;
				}
			}

			$ext_source = $real !== '' ? $real : ((string) (\parse_url($url, PHP_URL_PATH) ?? ''));
			$ext = \strtolower((string) \pathinfo($ext_source, PATHINFO_EXTENSION));
			if ($ext === '') continue;

			$filename = $title . '-' . (string) ($candidate['suffix'] ?? 'bild') . '.' . $ext;
			$filename = \function_exists(__NAMESPACE__ . '\\cmx_export_slugify')
				? (string) cmx_export_slugify((string) \pathinfo($filename, PATHINFO_FILENAME), 'artikel-' . $post_id) . '.' . $ext
				: ('artikel-' . $post_id . '.' . $ext);

			if (isset($used_names[$filename])) {
				$filename = 'artikel-' . $post_id . '-' . (string) ($candidate['suffix'] ?? 'bild') . '.' . $ext;
			}

			$used_names[$filename] = true;
			if ($real !== '') {
				$seen_real[$real] = true;
			}
			$entries[] = [
				'abs_path'   => $real,
				'source_url' => $url,
				'zip_name'   => 'bilder/' . \ltrim($filename, '/'),
				'suffix'     => (string) ($candidate['suffix'] ?? 'bild'),
			];
		}

		return $entries;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxal_write_artikel_csv_to_handle')) {
	function cmxal_export_lieferanten_rows(int $post_id): array {
		if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_load_lieferanten_rows_unified')) {
			return [];
		}

		$rows = (array) cmx_artikel_load_lieferanten_rows_unified($post_id);
		return \array_values(\array_filter($rows, static function($row): bool {
			return \is_array($row);
		}));
	}

	function cmxal_export_lieferanten_name(int $kontakt_id): string {
		$kontakt_id = (int) $kontakt_id;
		if ($kontakt_id <= 0) {
			return '';
		}

		$title = \trim((string) \get_the_title($kontakt_id));
		return $title !== '' ? $title : '';
	}

	function cmxal_lieferanten_headers(int $max_rows): array {
		$headers = ['lieferanten_count'];
		for ($i = 1; $i <= \max(1, $max_rows); $i++) {
			$headers[] = 'lieferant_' . $i . '_name';
			$headers[] = 'lieferant_' . $i . '_nr';
			$headers[] = 'lieferant_' . $i . '_ek';
			$headers[] = 'lieferant_' . $i . '_bezugsquelle';
			$headers[] = 'lieferant_' . $i . '_lieferzeit_tage';
			$headers[] = 'lieferant_' . $i . '_lagerbestand';
			$headers[] = 'lieferant_' . $i . '_notiz';
		}
		return $headers;
	}

	function cmxal_lieferanten_row_values(array $rows, int $max_rows): array {
		$values = [(string) \count($rows)];
		for ($i = 0; $i < \max(1, $max_rows); $i++) {
			$row = \is_array($rows[$i] ?? null) ? (array) $rows[$i] : [];
			$kontakt_id = (int) ($row['lieferant_id'] ?? 0);
			$values[] = \function_exists(__NAMESPACE__ . '\\cmxal_export_lieferanten_name')
				? (string) cmxal_export_lieferanten_name($kontakt_id)
				: '';
			$values[] = (string) ($row['lieferant_nr'] ?? '');
			$values[] = (string) ($row['ek'] ?? '');
			$values[] = (string) ($row['bezugsquelle'] ?? '');
			$values[] = (string) ($row['lieferzeit_tage'] ?? '');
			$values[] = (string) ($row['lagerbestand'] ?? '');
			$values[] = (string) ($row['notiz'] ?? '');
		}
		return $values;
	}

	function cmxal_export_variant_rows(int $post_id): array {
		if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_variant_rows_load') || !\function_exists(__NAMESPACE__ . '\\cmx_artikel_variant_taxonomy_choices')) {
			return [];
		}

		return (array) cmx_artikel_variant_rows_load(
			$post_id,
			(array) cmx_artikel_variant_taxonomy_choices('Grössen'),
			(array) cmx_artikel_variant_taxonomy_choices('Farben')
		);
	}

	function cmxal_variant_headers(int $max_rows): array {
		$headers = ['variant_count'];
		for ($i = 1; $i <= \max(1, $max_rows); $i++) {
			$headers[] = 'variant_' . $i . '_sku';
			$headers[] = 'variant_' . $i . '_anzahl';
			$headers[] = 'variant_' . $i . '_left_taxonomy';
			$headers[] = 'variant_' . $i . '_left_term_slug';
			$headers[] = 'variant_' . $i . '_left_term_name';
			$headers[] = 'variant_' . $i . '_right_taxonomy';
			$headers[] = 'variant_' . $i . '_right_term_slug';
			$headers[] = 'variant_' . $i . '_right_term_name';
			$headers[] = 'variant_' . $i . '_einheit_slug';
			$headers[] = 'variant_' . $i . '_einheit_name';
			$headers[] = 'variant_' . $i . '_ek';
			$headers[] = 'variant_' . $i . '_aufwand';
			$headers[] = 'variant_' . $i . '_vk';
			$headers[] = 'variant_' . $i . '_belegtext';
			$headers[] = 'variant_' . $i . '_verkaufbar';
			$headers[] = 'variant_' . $i . '_katalog';
			$headers[] = 'variant_' . $i . '_website';
			$headers[] = 'variant_' . $i . '_onlineshop';
			$headers[] = 'variant_' . $i . '_pos';
			$headers[] = 'variant_' . $i . '_nicht_mehr_lieferbar';
			$headers[] = 'variant_' . $i . '_archiviert';
			$headers[] = 'variant_' . $i . '_woo_product_id';
			$headers[] = 'variant_' . $i . '_woo_variation_id';
			$headers[] = 'variant_' . $i . '_woo_variation_sku';
		}
		return $headers;
	}

	function cmxal_variant_term_data(string $taxonomy, int $term_id): array {
		$taxonomy = \sanitize_key($taxonomy);
		if ($taxonomy === '' || $term_id <= 0 || !\taxonomy_exists($taxonomy)) {
			return ['slug' => '', 'name' => ''];
		}
		$term = \get_term($term_id, $taxonomy);
		if (!$term || \is_wp_error($term)) {
			return ['slug' => '', 'name' => ''];
		}
		return [
			'slug' => (string) ($term->slug ?? ''),
			'name' => (string) ($term->name ?? ''),
		];
	}

	function cmxal_variant_row_values(array $rows, int $max_rows): array {
		$values = [(string) \count($rows)];
		for ($i = 0; $i < \max(1, $max_rows); $i++) {
			$row = \is_array($rows[$i] ?? null) ? (array) $rows[$i] : [];
			$left_taxonomy = \sanitize_key((string) ($row['left_taxonomy'] ?? ''));
			$right_taxonomy = \sanitize_key((string) ($row['right_taxonomy'] ?? ''));
			$left_term = cmxal_variant_term_data($left_taxonomy, (int) ($row['left_term_id'] ?? 0));
			$right_term = cmxal_variant_term_data($right_taxonomy, (int) ($row['right_term_id'] ?? 0));
			$einheit_term = cmxal_variant_term_data(\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_EINHEITEN') ? (string) TAX_ARTIKEL_EINHEITEN : '', (int) ($row['einheit_term_id'] ?? 0));

			$values[] = (string) ($row['sku'] ?? '');
			$values[] = (string) ($row['anzahl'] ?? '');
			$values[] = $left_taxonomy;
			$values[] = (string) ($left_term['slug'] ?? '');
			$values[] = (string) ($left_term['name'] ?? '');
			$values[] = $right_taxonomy;
			$values[] = (string) ($right_term['slug'] ?? '');
			$values[] = (string) ($right_term['name'] ?? '');
			$values[] = (string) ($einheit_term['slug'] ?? '');
			$values[] = (string) ($einheit_term['name'] ?? '');
			$values[] = (string) ($row['ek'] ?? '');
			$values[] = (string) ($row['aufwand'] ?? '');
			$values[] = (string) ($row['vk'] ?? '');
			$values[] = (string) ($row['belegtext'] ?? '');
			$values[] = !empty($row['verkaufbar']) ? '1' : '0';
			$values[] = !empty($row['katalog']) ? '1' : '0';
			$values[] = !empty($row['website']) ? '1' : '0';
			$values[] = !empty($row['onlineshop']) ? '1' : '0';
			$values[] = !empty($row['pos']) ? '1' : '0';
			$values[] = !empty($row['nicht_mehr_lieferbar']) ? '1' : '0';
			$values[] = !empty($row['archiviert']) ? '1' : '0';
			$values[] = (string) ($row['woo_product_id'] ?? '');
			$values[] = (string) ($row['woo_variation_id'] ?? '');
			$values[] = (string) ($row['woo_variation_sku'] ?? '');
		}
		return $values;
	}

	function cmxal_should_export_meta_key(string $meta_key): bool {
		$meta_key = (string) $meta_key;
		if (\in_array($meta_key, [
			'_cmx_local_image_artikel_gallery',
			'_cmx_local_image_artikel_path',
			'_cmx_local_image_artikel_url',
			'_thumbnail_id',
			'_cmx_artikel_variant_rows',
			'_cmx_artikel_variant_count',
			'_cmx_art_lieferanten_liste',
			'_cmx_art_lieferanten_count',
		], true)) {
			return false;
		}
		if (\preg_match('/^_cmx_artikel_variant_\d+_(sku|anzahl|left_taxonomy|left_term_id|right_taxonomy|right_term_id|einheit_term_id|ek|aufwand|vk|belegtext|verkaufbar|katalog|website|onlineshop|pos|nicht_mehr_lieferbar|archiviert|woo_product_id|woo_variation_id|woo_variation_sku)$/', $meta_key)) {
			return false;
		}
		if (\preg_match('/^_cmx_art_lieferant_\d+_(id|nr|ek|bezugsquelle|lieferzeit_tage|lagerbestand|notiz)$/', $meta_key)) {
			return false;
		}
		return true;
	}

	function cmxal_write_artikel_csv_to_handle($fh, array $ids): void {
		$base_headers = [
			'post_id',
			'post_title',
			'post_status',
			'post_date',
			'post_slug',
			'permalink',
			'local_image_url',
			'local_image_path',
			'local_image_zip_path',
			'gallery_image_zip_paths',
			'featured_image',
			'featured_image_path',
			'featured_image_zip_path',
		];

		$meta_keys = [];
		$tax_headers = [];
		$lieferanten_rows_map = [];
		$max_lieferanten_rows = 0;
		$variant_rows_map = [];
		$max_variant_rows = 0;
		foreach ($ids as $post_id) {
			$lieferanten_rows = \function_exists(__NAMESPACE__ . '\\cmxal_export_lieferanten_rows')
				? (array) cmxal_export_lieferanten_rows((int) $post_id)
				: [];
			$lieferanten_rows_map[(int) $post_id] = $lieferanten_rows;
			$max_lieferanten_rows = \max($max_lieferanten_rows, \count($lieferanten_rows));
			$variant_rows = \function_exists(__NAMESPACE__ . '\\cmxal_export_variant_rows')
				? (array) cmxal_export_variant_rows((int) $post_id)
				: [];
			$variant_rows_map[(int) $post_id] = $variant_rows;
			$max_variant_rows = \max($max_variant_rows, \count($variant_rows));

			foreach (\get_post_meta($post_id) as $meta_key => $values) {
				if (!cmxal_should_export_meta_key((string) $meta_key)) {
					continue;
				}
				$meta_keys[(string) $meta_key] = true;
			}
		}

		$taxonomies = \get_object_taxonomies('artikel', 'objects');
		foreach ($taxonomies as $taxonomy) {
			$tax_headers[] = 'tax__' . $taxonomy->name;
		}

		$lieferanten_headers = cmxal_lieferanten_headers($max_lieferanten_rows);
		$variant_headers = cmxal_variant_headers($max_variant_rows);

		$meta_headers = \array_map(static fn(string $key): string => 'meta__' . $key, \array_keys($meta_keys));
		$headers = \array_merge($base_headers, $lieferanten_headers, $variant_headers, $meta_headers, $tax_headers);
		\fputcsv($fh, $headers, ';', '"', '\\');

		foreach ($ids as $post_id) {
			$post = \get_post($post_id);
			if (!$post instanceof \WP_Post) continue;

			$thumb_id = (int) \get_post_thumbnail_id($post_id);
			$featured_url = $thumb_id > 0 ? (string) \wp_get_attachment_url($thumb_id) : '';
			$local_image_url = (string) \get_post_meta($post_id, '_cmx_local_image_artikel_url', true);
			$local_image_path = '';
			$featured_path = '';
			$candidate_paths = \function_exists(__NAMESPACE__ . '\\cmxal_collect_candidate_image_paths')
				? (array) cmxal_collect_candidate_image_paths((int) $post_id)
				: [];
			foreach ($candidate_paths as $candidate) {
				$suffix = (string) ($candidate['suffix'] ?? '');
				$path = (string) ($candidate['path'] ?? '');
				if ($path === '' || !\is_file($path)) continue;
				if ($suffix === 'bild' && $local_image_path === '') {
					$local_image_path = \wp_normalize_path($path);
				}
				if ($suffix === 'featured' && $featured_path === '') {
					$featured_path = \wp_normalize_path($path);
				}
			}
			$image_entries = \function_exists(__NAMESPACE__ . '\\cmxal_artikel_image_entries')
				? (array) cmxal_artikel_image_entries((int) $post_id)
				: [];
			$local_image_zip_path = '';
			$gallery_image_zip_paths = [];
			$featured_image_zip_path = '';
			foreach ($image_entries as $entry) {
				$suffix = (string) ($entry['suffix'] ?? '');
				$zip_name = (string) ($entry['zip_name'] ?? '');
				if ($suffix === 'bild' && $local_image_zip_path === '') {
					$local_image_zip_path = $zip_name;
				}
				if ($suffix === 'bild' || \strpos($suffix, 'bild-') === 0) {
					$gallery_image_zip_paths[] = $zip_name;
				}
				if ($suffix === 'featured' && $featured_image_zip_path === '') {
					$featured_image_zip_path = $zip_name;
				}
			}

			$row = [
				'post_id'                => (string) $post_id,
				'post_title'             => (string) $post->post_title,
				'post_status'            => (string) $post->post_status,
				'post_date'              => \get_date_from_gmt($post->post_date_gmt, 'Y-m-d H:i:s'),
				'post_slug'              => (string) $post->post_name,
				'permalink'              => (string) \get_permalink($post_id),
				'local_image_url'        => $local_image_url,
				'local_image_path'       => $local_image_path,
				'local_image_zip_path'   => $local_image_zip_path,
				'gallery_image_zip_paths'=> \implode(' | ', \array_values(\array_filter($gallery_image_zip_paths))),
				'featured_image'         => $featured_url,
				'featured_image_path'    => $featured_path,
				'featured_image_zip_path'=> $featured_image_zip_path,
			];

			$lieferanten_rows = $lieferanten_rows_map[(int) $post_id] ?? [];
			$variant_rows = $variant_rows_map[(int) $post_id] ?? [];
			$structured_values = \array_merge(
				cmxal_lieferanten_row_values($lieferanten_rows, $max_lieferanten_rows),
				cmxal_variant_row_values($variant_rows, $max_variant_rows)
			);
			$structured_headers = \array_merge($lieferanten_headers, $variant_headers);
			foreach ($structured_headers as $index => $header) {
				$row[$header] = $structured_values[$index] ?? '';
			}

			$all_meta = \get_post_meta($post_id);
			foreach ($meta_headers as $header) {
				$meta_key = \substr($header, 6);
				$values = $all_meta[$meta_key] ?? [];
				$normalized = \array_map(static function($value) {
					$value = \maybe_unserialize($value);
					if (\is_array($value) || \is_object($value)) {
						return \wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
					}
					return (string) $value;
				}, (array) $values);
				$row[$header] = \implode(' | ', $normalized);
			}

			foreach ($taxonomies as $taxonomy) {
				$key = 'tax__' . $taxonomy->name;
				$terms = \wp_get_post_terms($post_id, $taxonomy->name, ['fields' => 'names']);
				$row[$key] = \is_wp_error($terms) ? '' : \implode(', ', $terms);
			}

			$out = [];
			foreach ($headers as $header) {
				$value = $row[$header] ?? '';
				if (\is_array($value) || \is_object($value)) {
					$value = \wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				}
				$out[] = \str_replace(["\r", "\n"], ' ', (string) $value);
			}
			\fputcsv($fh, $out, ';', '"', '\\');
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxal_artikel_csv_string_from_ids')) {
	function cmxal_artikel_csv_string_from_ids(array $ids): string {
		$fh = \fopen('php://temp', 'w+');
		if ($fh === false) {
			return '';
		}

		\fwrite($fh, "\xEF\xBB\xBF");
		cmxal_write_artikel_csv_to_handle($fh, $ids);
		\rewind($fh);
		$content = (string) \stream_get_contents($fh);
		\fclose($fh);
		return $content;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxal_stream_artikel_csv_from_ids')) {
	function cmxal_stream_artikel_csv_from_ids(array $ids): void {
		\ignore_user_abort(true);
		if (\function_exists('set_time_limit')) @\set_time_limit(0);
		while (\ob_get_level() > 0) { @\ob_end_clean(); }
		\nocache_headers();

		$filename = \function_exists(__NAMESPACE__ . '\\cmxal_export_filename')
			? (string) cmxal_export_filename('csv')
			: ('artikel-export-' . cmxal_export_stamp() . '.csv');
		$content = \function_exists(__NAMESPACE__ . '\\cmxal_artikel_csv_string_from_ids')
			? (string) cmxal_artikel_csv_string_from_ids($ids)
			: '';

		\header('Content-Type: text/csv; charset=UTF-8');
		\header('Content-Disposition: attachment; filename="' . $filename . '"');
		\header('Pragma: no-cache');
		\header('Expires: 0');
		echo $content;
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxal_collect_artikel_image_entries')) {
	function cmxal_collect_artikel_image_entries(array $ids): array {
		$entries = [];
		$seen_sources = [];

		foreach ($ids as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0) continue;
			$artikel_entries = \function_exists(__NAMESPACE__ . '\\cmxal_artikel_image_entries')
				? (array) cmxal_artikel_image_entries($post_id)
				: [];
			foreach ($artikel_entries as $entry) {
				$real = (string) ($entry['abs_path'] ?? '');
				$url = (string) ($entry['source_url'] ?? '');
				$key = $real !== '' ? ('file:' . $real) : ($url !== '' ? ('url:' . $url) : '');
				if ($key === '' || isset($seen_sources[$key])) continue;
				if ($real !== '' && !\is_file($real) && $url === '') continue;
				$seen_sources[$key] = true;
				$entries[] = [
					'abs_path'   => $real,
					'source_url' => $url,
					'zip_name'   => (string) ($entry['zip_name'] ?? ''),
				];
			}
		}

		return $entries;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxal_fetch_image_binary')) {
	function cmxal_fetch_image_binary(string $url): string {
		$url = \trim($url);
		if ($url === '') {
			return '';
		}

		$local_file = \function_exists(__NAMESPACE__ . '\\cmxal_local_file_from_url')
			? (string) cmxal_local_file_from_url($url)
			: '';
		if ($local_file !== '' && \is_readable($local_file) && \is_file($local_file)) {
			$raw = \file_get_contents($local_file);
			return \is_string($raw) ? $raw : '';
		}

		if (\is_readable($url) && \is_file($url)) {
			$raw = \file_get_contents($url);
			return \is_string($raw) ? $raw : '';
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxal_stream_artikel_export_zip_from_ids')) {
	function cmxal_stream_artikel_export_zip_from_ids(array $ids): void {
		if (!\class_exists('\\ZipArchive')) {
			\wp_die('ZIP-Export nicht verfügbar (ZipArchive fehlt).');
		}

		\ignore_user_abort(true);
		if (\function_exists('set_time_limit')) @\set_time_limit(0);
		while (\ob_get_level() > 0) { @\ob_end_clean(); }
		\nocache_headers();

		$tmpZip = \function_exists('\\wp_tempnam') ? \wp_tempnam('artikel-export-') : \tempnam(\sys_get_temp_dir(), 'artikel-export-');
		if (!$tmpZip) {
			\wp_die('Temporäre ZIP-Datei konnte nicht erstellt werden.');
		}

		$zip = new \ZipArchive();
		if ($zip->open($tmpZip, \ZipArchive::OVERWRITE) !== true) {
			@unlink($tmpZip);
			\wp_die('ZIP-Datei konnte nicht erstellt werden.');
		}

		$csv_content = \function_exists(__NAMESPACE__ . '\\cmxal_artikel_csv_string_from_ids')
			? (string) cmxal_artikel_csv_string_from_ids($ids)
			: '';
		$csv_name = \function_exists(__NAMESPACE__ . '\\cmxal_export_filename')
			? (string) cmxal_export_filename('csv')
			: ('artikel-export-' . cmxal_export_stamp() . '.csv');
		if ($csv_content !== '') {
			$zip->addFromString($csv_name, $csv_content);
		}

		$entries = \function_exists(__NAMESPACE__ . '\\cmxal_collect_artikel_image_entries')
			? (array) cmxal_collect_artikel_image_entries($ids)
			: [];
		foreach ($entries as $entry) {
			$abs_path = (string) ($entry['abs_path'] ?? '');
			$source_url = (string) ($entry['source_url'] ?? '');
			$zip_name = (string) ($entry['zip_name'] ?? '');
			if ($zip_name === '') continue;
			if ($abs_path !== '' && \is_file($abs_path)) {
				$zip->addFile($abs_path, \ltrim($zip_name, '/'));
				continue;
			}
			if ($source_url !== '') {
				$binary = \function_exists(__NAMESPACE__ . '\\cmxal_fetch_image_binary')
					? (string) cmxal_fetch_image_binary($source_url)
					: '';
				if ($binary !== '') {
					$zip->addFromString(\ltrim($zip_name, '/'), $binary);
				}
			}
		}
		$zip->close();

		$filename = \function_exists(__NAMESPACE__ . '\\cmxal_export_filename')
			? (string) cmxal_export_filename('zip')
			: ('artikel-export-' . cmxal_export_stamp() . '.zip');
		\header('Content-Type: application/zip');
		\header('Content-Disposition: attachment; filename="' . $filename . '"');
		\header('Content-Length: ' . (string) \filesize($tmpZip));
		\header('Pragma: no-cache');
		\header('Expires: 0');
		\readfile($tmpZip);
		@unlink($tmpZip);
		exit;
	}
}

/* ===== JS: markierte Artikel per POST mitsenden ===== */
\add_action('admin_footer-edit.php', function () {
	if (($_GET['post_type'] ?? '') !== 'artikel') return;
	$action = \esc_js(\admin_url('admin-post.php'));
	$nonce = \esc_js(\wp_create_nonce('cmx_export_artikel_list'));
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		const exportLink = [...document.querySelectorAll('.subsubsub a')]
			.find(a => a.textContent.trim().toLowerCase() === 'exportieren' || /action=cmx_export_artikel_list/i.test(a.href));
		if (!exportLink) return;

		exportLink.addEventListener('click', function (e) {
			e.preventDefault();
			const form = document.createElement('form');
			form.method = 'POST';
			form.action = '<?php echo $action; ?>';

			const appendField = function (name, value) {
				if (!name || value === null || value === undefined || value === '') return;
				const input = document.createElement('input');
				input.type = 'hidden';
				input.name = String(name);
				input.value = String(value);
				form.appendChild(input);
			};

			appendField('action', 'cmx_export_artikel_list');
			appendField('_wpnonce', '<?php echo $nonce; ?>');
			appendField('cmx_export_format', 'zip');

			const params = new URLSearchParams(window.location.search);
			params.forEach(function (value, key) {
				if (!key || key === 'action' || key === 'action2' || key === '_wpnonce' || key === '_wp_http_referer') return;
				appendField(key, value);
			});

			const postsFilter = document.getElementById('posts-filter');
			if (postsFilter) {
				const data = new FormData(postsFilter);
				for (const pair of data.entries()) {
					const key = pair[0];
					const value = pair[1];
					if (!key || key === 'action' || key === 'action2' || key === '_wpnonce' || key === '_wp_http_referer') continue;
					appendField(key, value);
				}
			}

			document.body.appendChild(form);
			form.submit();
		});
	});
	</script>
	<?php
});
