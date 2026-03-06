<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_PT_SCANNER')) {
	\define(__NAMESPACE__ . '\\CMX_PT_SCANNER', 'scanner');
}
if (!\defined(__NAMESPACE__ . '\\CMX_SCANNER_SYNC_SOURCE_REL_META')) {
	\define(__NAMESPACE__ . '\\CMX_SCANNER_SYNC_SOURCE_REL_META', '_cmx_scanner_source_rel');
}
if (!\defined(__NAMESPACE__ . '\\CMX_SCANNER_SYNC_UPLOADS_META')) {
	$uploads_meta_key = \defined(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
		? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
		: '_cmx_dokumente_uploads';
	\define(__NAMESPACE__ . '\\CMX_SCANNER_SYNC_UPLOADS_META', $uploads_meta_key);
}
if (!\defined(__NAMESPACE__ . '\\CMX_SCANNER_SYNC_DOC_FILE_META')) {
	\define(__NAMESPACE__ . '\\CMX_SCANNER_SYNC_DOC_FILE_META', '_cmx_dokumente_file_path');
}
if (!\defined(__NAMESPACE__ . '\\CMX_SCANNER_SYNC_DOC_SELF_META')) {
	$self_meta_key = \defined(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
		? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
		: '_cmx_dokumente_files';
	\define(__NAMESPACE__ . '\\CMX_SCANNER_SYNC_DOC_SELF_META', $self_meta_key);
}
if (!\defined(__NAMESPACE__ . '\\CMX_SCANNER_SYNC_DOC_REL_META')) {
	\define(__NAMESPACE__ . '\\CMX_SCANNER_SYNC_DOC_REL_META', 'cmx_dokumente_rel_scanner');
}
if (!\defined(__NAMESPACE__ . '\\CMX_SCANNER_SYNC_UPLOADED_TS_META')) {
	\define(__NAMESPACE__ . '\\CMX_SCANNER_SYNC_UPLOADED_TS_META', '_cmx_scanner_uploaded_ts');
}
if (!\defined(__NAMESPACE__ . '\\CMX_SCANNER_SYNC_FS_SIG_META')) {
	\define(__NAMESPACE__ . '\\CMX_SCANNER_SYNC_FS_SIG_META', '_cmx_scanner_fs_signature');
}

function cmx_scanner_sync_clear_fs_cache(string $path = ''): void {
	@\clearstatcache(true);
	if ($path !== '') {
		@\clearstatcache(true, $path);
	}
}

function cmx_scanner_sync_normalize_rel(string $rel): string {
	return \strtolower(\ltrim(\str_replace('\\', '/', $rel), '/'));
}

function cmx_scanner_sync_scanner_suffix(string $rel): string {
	$clean = \ltrim(\str_replace('\\', '/', $rel), '/');
	if ($clean === '') {
		return '';
	}

	if (\preg_match('#^scanner/(.+)$#i', $clean, $match) === 1) {
		$suffix = \ltrim((string) ($match[1] ?? ''), '/');
		return $suffix !== '' ? $suffix : '';
	}
	if (\preg_match('#^misbuero/scanner/(.+)$#i', $clean, $match) === 1) {
		$suffix = \ltrim((string) ($match[1] ?? ''), '/');
		return $suffix !== '' ? $suffix : '';
	}
	if (\preg_match('#^archiv/scanner/(.+)$#i', $clean, $match) === 1) {
		$suffix = \ltrim((string) ($match[1] ?? ''), '/');
		return $suffix !== '' ? $suffix : '';
	}
	if (\preg_match('#^misbuero/archiv/scanner/(.+)$#i', $clean, $match) === 1) {
		$suffix = \ltrim((string) ($match[1] ?? ''), '/');
		return $suffix !== '' ? $suffix : '';
	}

	return '';
}

function cmx_scanner_sync_is_scanner_rel(string $rel): bool {
	return cmx_scanner_sync_scanner_suffix($rel) !== '';
}

function cmx_scanner_sync_collect_delete_rels(int $post_id): array {
	$rels = [];

	$source_rel = (string) \get_post_meta($post_id, CMX_SCANNER_SYNC_SOURCE_REL_META, true);
	if ($source_rel !== '') {
		$rels[] = $source_rel;
	}

	$doc_ids = (array) \get_post_meta($post_id, CMX_SCANNER_SYNC_UPLOADS_META, true);
	$doc_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $doc_ids))));
	foreach ($doc_ids as $doc_id) {
		$file_rel = (string) \get_post_meta($doc_id, CMX_SCANNER_SYNC_DOC_FILE_META, true);
		if ($file_rel !== '') {
			$rels[] = $file_rel;
		}

		$self_files = (array) \get_post_meta($doc_id, CMX_SCANNER_SYNC_DOC_SELF_META, true);
		foreach ($self_files as $self_rel) {
			if (\is_string($self_rel) && $self_rel !== '') {
				$rels[] = $self_rel;
			}
		}
	}

	$map = [];
	foreach ($rels as $rel) {
		$rel = \ltrim(\str_replace('\\', '/', (string) $rel), '/');
		if ($rel === '' || !cmx_scanner_sync_is_scanner_rel($rel)) {
			continue;
		}
		$key = cmx_scanner_sync_normalize_rel($rel);
		if ($key === '') {
			continue;
		}
		if (!isset($map[$key])) {
			$map[$key] = $rel;
		}
	}

	return \array_values($map);
}

function cmx_scanner_sync_delete_file_from_rel(string $rel): void {
	$rel = \ltrim(\str_replace('\\', '/', $rel), '/');
	if ($rel === '' || !cmx_scanner_sync_is_scanner_rel($rel)) {
		return;
	}

	$suffix = cmx_scanner_sync_scanner_suffix($rel);
	if ($suffix === '') {
		return;
	}

	$variants = [
		$rel,
		'scanner/' . $suffix,
		'misbuero/scanner/' . $suffix,
		'misbuero/Scanner/' . $suffix,
		'archiv/scanner/' . $suffix,
		'misbuero/archiv/scanner/' . $suffix,
		'misbuero/archiv/Scanner/' . $suffix,
	];

	$uploads_base = \trailingslashit(\wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads')));
	foreach (\array_values(\array_unique($variants)) as $variant) {
		$abs = \wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads/' . \ltrim($variant, '/')));
		if ($abs === '' || !\str_starts_with($abs, $uploads_base) || !\is_file($abs)) {
			continue;
		}
		@unlink($abs);
	}
}

function cmx_scanner_sync_rel_variants(string $rel): array {
	$rel = \ltrim(\str_replace('\\', '/', $rel), '/');
	if ($rel === '' || !cmx_scanner_sync_is_scanner_rel($rel)) {
		return [];
	}

	$suffix = cmx_scanner_sync_scanner_suffix($rel);
	if ($suffix === '') {
		return [];
	}

	return \array_values(\array_unique([
		$rel,
		'scanner/' . $suffix,
		'misbuero/scanner/' . $suffix,
		'misbuero/Scanner/' . $suffix,
		'archiv/scanner/' . $suffix,
		'misbuero/archiv/scanner/' . $suffix,
		'misbuero/archiv/Scanner/' . $suffix,
	]));
}

function cmx_scanner_sync_rel_variants_with_self(string $rel): array {
	$rel = \ltrim(\str_replace('\\', '/', $rel), '/');
	if ($rel === '') {
		return [];
	}

	$variants = cmx_scanner_sync_rel_variants($rel);
	if (!\in_array($rel, $variants, true)) {
		$variants[] = $rel;
	}

	return \array_values(\array_unique(\array_filter($variants, static function ($value): bool {
		return \is_string($value) && $value !== '';
	})));
}

function cmx_scanner_sync_find_first_existing_abs_by_rel(string $rel): string {
	$upload_root = \trailingslashit(\wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads')));
	$variants = cmx_scanner_sync_rel_variants($rel);
	foreach ($variants as $variant) {
		$abs = \wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads/' . \ltrim($variant, '/')));
		if ($abs === '' || !\str_starts_with($abs, $upload_root) || !\is_file($abs)) {
			continue;
		}
		return $abs;
	}
	return '';
}

function cmx_scanner_sync_get_source_rel_for_post(int $post_id): string {
	$source_rel = (string) \get_post_meta($post_id, CMX_SCANNER_SYNC_SOURCE_REL_META, true);
	if ($source_rel !== '' && cmx_scanner_sync_is_scanner_rel($source_rel)) {
		return \ltrim(\str_replace('\\', '/', $source_rel), '/');
	}

	$doc_ids = (array) \get_post_meta($post_id, CMX_SCANNER_SYNC_UPLOADS_META, true);
	$doc_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $doc_ids))));
	foreach ($doc_ids as $doc_id) {
		$file_rel = (string) \get_post_meta($doc_id, CMX_SCANNER_SYNC_DOC_FILE_META, true);
		if ($file_rel !== '' && cmx_scanner_sync_is_scanner_rel($file_rel)) {
			return \ltrim(\str_replace('\\', '/', $file_rel), '/');
		}
	}

	return '';
}

function cmx_scanner_sync_get_uploaded_ts(int $post_id): int {
	$stored = (int) \get_post_meta($post_id, CMX_SCANNER_SYNC_UPLOADED_TS_META, true);

	$rel = cmx_scanner_sync_get_source_rel_for_post($post_id);
	if ($rel !== '') {
		$abs = cmx_scanner_sync_find_first_existing_abs_by_rel($rel);
		if ($abs !== '') {
			$mtime = @\filemtime($abs);
			if (\is_int($mtime) && $mtime > 0) {
				if ($stored !== $mtime) {
					\update_post_meta($post_id, CMX_SCANNER_SYNC_UPLOADED_TS_META, $mtime);
				}
				return $mtime;
			}
		}
	}

	if ($stored > 0) {
		return $stored;
	}

	$fallback = (int) \get_post_time('U', true, $post_id);
	return $fallback > 0 ? $fallback : 0;
}

function cmx_scanner_sync_collect_root_configs(): array {
	$configs = [
		[
			'root'   => \function_exists(__NAMESPACE__ . '\\cmx_dav_scanner_share_path')
				? \wp_normalize_path((string) cmx_dav_scanner_share_path())
				: '',
			'prefix' => 'misbuero/scanner',
		],
		[
			'root'   => \wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads/misbuero/scanner')),
			'prefix' => 'misbuero/scanner',
		],
		[
			'root'   => \wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads/misbuero/Scanner')),
			'prefix' => 'misbuero/scanner',
		],
		[
			'root'   => \wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads/scanner')),
			'prefix' => 'scanner',
		],
		[
			'root'   => \wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads/Scanner')),
			'prefix' => 'scanner',
		],
		[
			'root'   => \wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads/misbuero/archiv/scanner')),
			'prefix' => 'misbuero/scanner',
		],
		[
			'root'   => \wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads/misbuero/archiv/Scanner')),
			'prefix' => 'misbuero/scanner',
		],
	];

	$dedup = [];
	foreach ($configs as $config) {
		$root = isset($config['root']) ? (string) $config['root'] : '';
		$root = \rtrim(\str_replace('\\', '/', $root), '/');
		if ($root === '') {
			continue;
		}
		$key = \strtolower($root);
		if (!isset($dedup[$key])) {
			$dedup[$key] = [
				'root'   => $root,
				'prefix' => isset($config['prefix']) ? (string) $config['prefix'] : '',
			];
		}
	}

	return \array_values($dedup);
}

function cmx_scanner_sync_rel_from_abs_and_root(string $abs, string $root, string $prefix): string {
	$abs = \wp_normalize_path($abs);
	$root = \rtrim(\wp_normalize_path($root), '/');
	$prefix = \trim(\str_replace('\\', '/', $prefix), '/');
	if ($abs === '' || $root === '') {
		return '';
	}

	$rootWithSlash = $root . '/';
	if (\str_starts_with($abs, $rootWithSlash)) {
		$suffix = (string) \substr($abs, \strlen($rootWithSlash));
	} elseif (\str_starts_with(\strtolower($abs), \strtolower($rootWithSlash))) {
		$suffix = (string) \substr($abs, \strlen($rootWithSlash));
	} else {
		return '';
	}

	$suffix = \ltrim(\str_replace('\\', '/', $suffix), '/');
	if ($suffix === '') {
		return '';
	}

	return $prefix === '' ? $suffix : ($prefix . '/' . $suffix);
}

function cmx_scanner_sync_canonical_rel_from_uploads_abs(string $abs, string $upload_root): string {
	$abs = \wp_normalize_path((string) $abs);
	$upload_root = \trailingslashit(\wp_normalize_path((string) $upload_root));
	if ($abs === '' || $upload_root === '') {
		return '';
	}
	if (!\str_starts_with($abs, $upload_root) && !\str_starts_with(\strtolower($abs), \strtolower($upload_root))) {
		return '';
	}

	$rel = \ltrim((string) \substr($abs, \strlen($upload_root)), '/');
	$rel = \ltrim(\str_replace('\\', '/', $rel), '/');
	if ($rel === '') {
		return '';
	}

	if (\preg_match('#^(?:misbuero/)?scanner/(.+)$#i', $rel, $m) === 1) {
		$suffix = \ltrim((string) ($m[1] ?? ''), '/');
		return $suffix !== '' ? 'misbuero/scanner/' . $suffix : '';
	}
	if (\preg_match('#^(?:misbuero/)?archiv/scanner/(.+)$#i', $rel, $m) === 1) {
		$suffix = \ltrim((string) ($m[1] ?? ''), '/');
		return $suffix !== '' ? 'misbuero/scanner/' . $suffix : '';
	}

	return $rel;
}

function cmx_scanner_sync_is_allowed_file(string $path): bool {
	$ext = \strtolower((string) \pathinfo($path, \PATHINFO_EXTENSION));
	return $ext === 'pdf';
}

function cmx_scanner_sync_rel_is_allowed_file(string $rel): bool {
	$filename = (string) \basename($rel);
	if ($filename === '') {
		return false;
	}
	return cmx_scanner_sync_is_allowed_file($filename);
}

function cmx_scanner_sync_collect_files(): array {
	cmx_scanner_sync_clear_fs_cache();

	$upload_root = \trailingslashit(\wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads')));
	$root_configs = cmx_scanner_sync_collect_root_configs();

	$result = [];
	foreach ($root_configs as $root_config) {
		$root = isset($root_config['root']) ? (string) $root_config['root'] : '';
		$prefix = isset($root_config['prefix']) ? (string) $root_config['prefix'] : '';
		cmx_scanner_sync_clear_fs_cache($root);
		if ($root === '' || !\is_dir($root)) {
			continue;
		}

		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::LEAVES_ONLY
			);
		} catch (\Throwable $e) {
			continue;
		}

				foreach ($iterator as $entry) {
					if (!$entry instanceof \SplFileInfo || !$entry->isFile()) {
						continue;
					}

					$abs = \wp_normalize_path((string) $entry->getPathname());
					cmx_scanner_sync_clear_fs_cache($abs);
					if ($abs === '') {
						continue;
					}

				$base = (string) $entry->getBasename();
					if ($base === '' || $base[0] === '.') {
						continue;
					}

				if (!cmx_scanner_sync_is_allowed_file($abs)) {
					continue;
				}

				$rel = cmx_scanner_sync_rel_from_abs_and_root($abs, $root, $prefix);
				if ($rel === '') {
					$rel = cmx_scanner_sync_canonical_rel_from_uploads_abs($abs, $upload_root);
				}
				if ($rel === '') {
					continue;
				}

				$key = cmx_scanner_sync_normalize_rel($rel);
				if ($key === '') {
					continue;
			}

			$result[$key] = [
				'rel' => $rel,
				'abs' => $abs,
			];
		}
	}

	\ksort($result, \SORT_NATURAL | \SORT_FLAG_CASE);
	return \array_values($result);
}

function cmx_scanner_sync_build_fs_signature(string $abs): string {
	$abs = \wp_normalize_path($abs);
	if ($abs === '' || !\is_file($abs)) {
		return '';
	}
	cmx_scanner_sync_clear_fs_cache($abs);
	$size = @\filesize($abs);
	$mtime = @\filemtime($abs);
	$inode = @\fileinode($abs);
	return (string) $size . ':' . (string) $mtime . ':' . (string) $inode;
}

function cmx_scanner_sync_is_trashed_post(int $post_id): bool {
	return (string) \get_post_status($post_id) === 'trash';
}

function cmx_scanner_sync_make_title(string $rel): string {
	$filename = (string) \basename($rel);
	if ($filename === '') {
		return \wp_date('ymd-His') . ' scanner';
	}

	if (\function_exists(__NAMESPACE__ . '\\cmx_dok_sanitize_title_from_filename')) {
		$title = (string) cmx_dok_sanitize_title_from_filename($filename);
		if ($title !== '') {
			return $title;
		}
	}

	$name = (string) \pathinfo($filename, \PATHINFO_FILENAME);
	$name = \wp_strip_all_tags($name);
	$name = (string) \preg_replace('/[_\-]+/', ' ', $name);
	$name = (string) \preg_replace('/\s+/', ' ', $name);
	$name = \trim(\sanitize_text_field($name));

	return $name !== '' ? $name : \wp_date('ymd-His') . ' scanner';
}

function cmx_scanner_sync_get_scanner_ids(): array {
	$ids = \get_posts([
		'post_type'              => CMX_PT_SCANNER,
		'post_status'            => ['publish', 'draft', 'pending', 'private', 'future', 'trash'],
		'posts_per_page'         => -1,
		'fields'                 => 'ids',
		'cache_results'          => false,
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
		'suppress_filters'       => true,
	]);

	$out = [];
	foreach ((array) $ids as $id) {
		$id = (int) $id;
		if ($id > 0) {
			$out[] = $id;
		}
	}
	return $out;
}

function cmx_scanner_sync_build_path_map(array $scanner_ids): array {
	$map = [];

	foreach ($scanner_ids as $scanner_id) {
		$scanner_id = (int) $scanner_id;
		if ($scanner_id <= 0) {
			continue;
		}

		$source_rel = (string) \get_post_meta($scanner_id, CMX_SCANNER_SYNC_SOURCE_REL_META, true);
		foreach (cmx_scanner_sync_rel_variants_with_self($source_rel) as $source_variant) {
			$key = cmx_scanner_sync_normalize_rel($source_variant);
			if ($key !== '' && !isset($map[$key])) {
				$map[$key] = $scanner_id;
			}
		}

		$doc_ids = (array) \get_post_meta($scanner_id, CMX_SCANNER_SYNC_UPLOADS_META, true);
		$doc_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $doc_ids))));
		foreach ($doc_ids as $doc_id) {
			$file_rel = (string) \get_post_meta($doc_id, CMX_SCANNER_SYNC_DOC_FILE_META, true);
			if ($file_rel === '') {
				continue;
			}
			foreach (cmx_scanner_sync_rel_variants_with_self($file_rel) as $file_variant) {
				$key = cmx_scanner_sync_normalize_rel($file_variant);
				if ($key !== '' && !isset($map[$key])) {
					$map[$key] = $scanner_id;
				}
			}
		}
	}

	return $map;
}

function cmx_scanner_sync_find_doc_by_rel(string $rel): int {
	$variants = cmx_scanner_sync_rel_variants($rel);
	if ($rel !== '' && !\in_array($rel, $variants, true)) {
		$variants[] = $rel;
	}

	foreach (\array_values(\array_unique($variants)) as $variant) {
		$ids = \get_posts([
			'post_type'      => 'dokumente',
			'post_status'    => ['publish', 'draft', 'pending', 'private', 'future'],
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'cache_results'  => false,
			'no_found_rows'  => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => true,
			'meta_query'     => [[
				'key'     => CMX_SCANNER_SYNC_DOC_FILE_META,
				'value'   => $variant,
				'compare' => '=',
			]],
		]);
		if (!empty($ids)) {
			return (int) $ids[0];
		}
	}

	return 0;
}

function cmx_scanner_sync_has_existing_file_for_post(int $post_id, array $existing_file_keys): bool {
	$rels = cmx_scanner_sync_collect_delete_rels($post_id);
	foreach ($rels as $rel) {
		$key = cmx_scanner_sync_normalize_rel((string) $rel);
		if ($key !== '' && isset($existing_file_keys[$key])) {
			return true;
		}
	}
	return false;
}

function cmx_scanner_sync_delete_orphan_posts(array $scanner_ids, array $existing_file_keys): void {
	foreach ($scanner_ids as $scanner_id) {
		$scanner_id = (int) $scanner_id;
		if ($scanner_id <= 0) {
			continue;
		}

		$rels = cmx_scanner_sync_collect_delete_rels($scanner_id);
		if (empty($rels)) {
			continue;
		}
		$has_allowed_rel = false;
		foreach ($rels as $rel) {
			if (cmx_scanner_sync_rel_is_allowed_file((string) $rel)) {
				$has_allowed_rel = true;
				break;
			}
		}
		if (!$has_allowed_rel) {
			continue;
		}

		if (cmx_scanner_sync_has_existing_file_for_post($scanner_id, $existing_file_keys)) {
			continue;
		}

		\wp_delete_post($scanner_id, true);
	}
}

function cmx_scanner_sync_ensure_doc_link(int $scanner_id, string $rel, string $title): void {
	$scanner_docs = (array) \get_post_meta($scanner_id, CMX_SCANNER_SYNC_UPLOADS_META, true);
	$scanner_docs = \array_values(\array_unique(\array_filter(\array_map('intval', $scanner_docs))));

	$rel_key = cmx_scanner_sync_normalize_rel($rel);
	foreach ($scanner_docs as $doc_id) {
		$path = (string) \get_post_meta($doc_id, CMX_SCANNER_SYNC_DOC_FILE_META, true);
		if (cmx_scanner_sync_normalize_rel($path) === $rel_key) {
			return;
		}
	}

	$doc_id = cmx_scanner_sync_find_doc_by_rel($rel);
	if ($doc_id <= 0) {
		$doc_id = (int) \wp_insert_post([
			'post_type'   => 'dokumente',
			'post_title'  => $title,
			'post_status' => 'publish',
		], true);
		if ($doc_id <= 0 || \is_wp_error($doc_id)) {
			return;
		}
	}

	\update_post_meta($doc_id, CMX_SCANNER_SYNC_DOC_FILE_META, $rel);

	$self_files = (array) \get_post_meta($doc_id, CMX_SCANNER_SYNC_DOC_SELF_META, true);
	$self_files = \array_values(\array_filter($self_files, static function ($value): bool {
		return \is_string($value) && $value !== '';
	}));
	$self_files[] = $rel;
	$self_files = \array_values(\array_unique($self_files));
	\update_post_meta($doc_id, CMX_SCANNER_SYNC_DOC_SELF_META, $self_files);

	$rel_ids = (array) \get_post_meta($doc_id, CMX_SCANNER_SYNC_DOC_REL_META, true);
	$rel_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $rel_ids))));
	$rel_ids[] = $scanner_id;
	$rel_ids = \array_values(\array_unique($rel_ids));
	\update_post_meta($doc_id, CMX_SCANNER_SYNC_DOC_REL_META, $rel_ids);

	$scanner_docs[] = $doc_id;
	$scanner_docs = \array_values(\array_unique(\array_filter(\array_map('intval', $scanner_docs))));
	\update_post_meta($scanner_id, CMX_SCANNER_SYNC_UPLOADS_META, $scanner_docs);
}

function cmx_scanner_sync_files_to_cpt(): void {
	cmx_scanner_sync_clear_fs_cache();

	$files = cmx_scanner_sync_collect_files();

	$existing_file_keys = [];
	foreach ($files as $file) {
		$rel = (string) ($file['rel'] ?? '');
		if ($rel === '') {
			continue;
		}
		foreach (cmx_scanner_sync_rel_variants_with_self($rel) as $variant) {
			$key = cmx_scanner_sync_normalize_rel($variant);
			if ($key !== '') {
				$existing_file_keys[$key] = true;
			}
		}
	}

	$scanner_ids = cmx_scanner_sync_get_scanner_ids();
	cmx_scanner_sync_delete_orphan_posts($scanner_ids, $existing_file_keys);
	if (empty($files)) {
		return;
	}

	$scanner_ids = cmx_scanner_sync_get_scanner_ids();
	$path_map = cmx_scanner_sync_build_path_map($scanner_ids);

	foreach ($files as $file) {
		$rel = (string) ($file['rel'] ?? '');
		if ($rel === '') {
			continue;
		}
		$key = cmx_scanner_sync_normalize_rel($rel);
		if ($key === '') {
			continue;
		}

		$scanner_id = isset($path_map[$key]) ? (int) $path_map[$key] : 0;
		if ($scanner_id > 0 && cmx_scanner_sync_is_trashed_post($scanner_id)) {
			// Eintrag liegt im Papierkorb: nicht neu erzeugen und nicht überschreiben.
			$path_map[$key] = $scanner_id;
			continue;
		}
		if ($scanner_id <= 0) {
			$title = cmx_scanner_sync_make_title($rel);
			$inserted = \wp_insert_post([
				'post_type'   => CMX_PT_SCANNER,
				'post_title'  => $title,
				'post_name'   => \sanitize_title($title),
				'post_status' => 'publish',
			], true);
			if (\is_wp_error($inserted) || (int) $inserted <= 0) {
				continue;
			}
			$scanner_id = (int) $inserted;
		}

		\update_post_meta($scanner_id, CMX_SCANNER_SYNC_SOURCE_REL_META, $rel);
		$abs_path = (string) ($file['abs'] ?? '');
		$mtime = @\filemtime($abs_path);
		$signature = cmx_scanner_sync_build_fs_signature($abs_path);
		$stored_signature = (string) \get_post_meta($scanner_id, CMX_SCANNER_SYNC_FS_SIG_META, true);
		$mtime_ts = (\is_int($mtime) && $mtime > 0) ? (int) $mtime : 0;
		if ($signature !== '') {
			if ($signature !== $stored_signature) {
				if ($mtime_ts > 0) {
					\update_post_meta($scanner_id, CMX_SCANNER_SYNC_UPLOADED_TS_META, $mtime_ts);
				} else {
					\update_post_meta($scanner_id, CMX_SCANNER_SYNC_UPLOADED_TS_META, (int) \current_time('timestamp'));
				}
				\update_post_meta($scanner_id, CMX_SCANNER_SYNC_FS_SIG_META, $signature);
			}
		}

		if ($mtime_ts > 0) {
			$stored_uploaded_ts = (int) \get_post_meta($scanner_id, CMX_SCANNER_SYNC_UPLOADED_TS_META, true);
			if ($stored_uploaded_ts !== $mtime_ts) {
				\update_post_meta($scanner_id, CMX_SCANNER_SYNC_UPLOADED_TS_META, $mtime_ts);
			}
		}

		$title = (string) \get_the_title($scanner_id);
		if ($title === '') {
			$title = cmx_scanner_sync_make_title($rel);
			\wp_update_post([
				'ID'         => $scanner_id,
				'post_title' => $title,
				'post_name'  => \sanitize_title($title),
			]);
		}

		$auto_doc_links = (bool) \apply_filters('cmx_scanner_sync_auto_doc_links', false, $scanner_id, $rel, $title);
		if ($auto_doc_links) {
			cmx_scanner_sync_ensure_doc_link($scanner_id, $rel, $title);
		}
		$path_map[$key] = $scanner_id;
	}
}

$cmx_scanner_add_uploaded_column = static function (array $columns): array {
	$new = [];
	$inserted = false;
	foreach ($columns as $key => $label) {
		$new[$key] = $label;
		if ($key === 'title') {
			$new['cmx_scanner_uploaded_at'] = 'E-Mail / Scanner';
			$inserted = true;
		}
	}
	if (!$inserted) {
		$new['cmx_scanner_uploaded_at'] = 'E-Mail / Scanner';
	}
	return $new;
};
\add_filter('manage_edit-' . CMX_PT_SCANNER . '_columns', $cmx_scanner_add_uploaded_column, 50);
\add_filter('manage_' . CMX_PT_SCANNER . '_posts_columns', $cmx_scanner_add_uploaded_column, 50);

\add_action('manage_' . CMX_PT_SCANNER . '_posts_custom_column', function (string $column, int $post_id): void {
	if ($column !== 'cmx_scanner_uploaded_at') {
		return;
	}

	$ts = cmx_scanner_sync_get_uploaded_ts($post_id);
	if ($ts <= 0) {
		echo '';
		return;
	}

	echo \esc_html(\wp_date('d.m.Y H:i', $ts));
}, 10, 2);

\add_action('admin_head-edit.php', function (): void {
	if (!isset($_GET['post_type']) || (string) $_GET['post_type'] !== CMX_PT_SCANNER) {
		return;
	}
	echo '<style>
		.wp-list-table th.column-cmx_scanner_uploaded_at { width: 140px; }
		.wp-list-table td.column-cmx_scanner_uploaded_at { white-space: nowrap; }
	</style>';
});

function cmx_scanner_sync_maybe_run_for_admin_list(): void {
	static $didRun = false;
	if ($didRun) {
		return;
	}
	$didRun = true;
	cmx_scanner_sync_files_to_cpt();
}

\add_action('load-edit.php', function (): void {
	if (!\is_admin()) {
		return;
	}
	if (!isset($_GET['post_type']) || (string) $_GET['post_type'] !== CMX_PT_SCANNER) {
		return;
	}
	cmx_scanner_sync_maybe_run_for_admin_list();
}, 8);

\add_action('current_screen', function ($screen): void {
	if (!$screen instanceof \WP_Screen) {
		return;
	}
	if ((string) ($screen->base ?? '') !== 'edit') {
		return;
	}
	if ((string) ($screen->post_type ?? '') !== CMX_PT_SCANNER) {
		return;
	}
	cmx_scanner_sync_maybe_run_for_admin_list();
}, 8);

\add_action('before_delete_post', function (int $post_id): void {
	$post = \get_post($post_id);
	if (!$post instanceof \WP_Post || $post->post_type !== CMX_PT_SCANNER) {
		return;
	}

	$rels = cmx_scanner_sync_collect_delete_rels($post_id);
	foreach ($rels as $rel) {
		cmx_scanner_sync_delete_file_from_rel((string) $rel);
	}
}, 10, 1);
