<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_PT_BELEGE')) {
	\define(__NAMESPACE__ . '\\CMX_PT_BELEGE', 'belege');
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbel_view_insert_relative')) {
	function cmxbel_view_request_key(): string {
		foreach ([
			'cmx_belegeingang' => 'cmx_eingang_belege',
			'cmx_view' => 'cmx_deckungsbeitrag',
			'cmx_export' => 'cmx_milchbueechli_belege',
			'cmx_import_filter' => 'cmx_import_filter_belege',
			'cmx_import' => 'cmx_import_belege',
		] as $query_key => $view_key) {
			if (!empty($_GET[$query_key])) {
				if ($query_key === 'cmx_view' && \sanitize_key((string) \wp_unslash($_GET[$query_key])) !== 'deckungsbeitrag') {
					continue;
				}
				return $view_key;
			}
		}

		if (isset($_GET['post_status']) && \sanitize_key((string) \wp_unslash($_GET['post_status'])) === 'pending') {
			return 'cmx_eingang_belege';
		}

		return '';
	}

	function cmxbel_view_mark_current(string $html, bool $current): string {
		$html = (string) \preg_replace('/\s+aria-current=(["\'])page\1/i', '', $html);
		$html = (string) \preg_replace_callback('/<a\b([^>]*)>/i', static function (array $matches) use ($current): string {
			$attrs = (string) ($matches[1] ?? '');
			$attrs = (string) \preg_replace('/\sclass=(["\'])(.*?)\1/i', '', $attrs);
			$attrs = \trim($attrs);
			if (!$current) {
				return '<a' . ($attrs !== '' ? ' ' . $attrs : '') . '>';
			}
			return '<a' . ($attrs !== '' ? ' ' . $attrs : '') . ' class="current" aria-current="page">';
		}, $html, 1);

		return $html;
	}

	function cmxbel_views_normalize_current(array $views): array {
		$current_key = cmxbel_view_request_key();
		if ($current_key === '') {
			return $views;
		}

		foreach ($views as $key => $html) {
			$views[$key] = cmxbel_view_mark_current((string) $html, (string) $key === $current_key);
		}

		return $views;
	}

	function cmxbel_view_insert_relative(array $views, string $anchor_key, string $new_key, string $html, string $position = 'before'): array {
		$new_views = [];
		$inserted = false;

		foreach ($views as $key => $value) {
			if ($position === 'before' && $key === $anchor_key && !$inserted) {
				$new_views[$new_key] = $html;
				$inserted = true;
			}
			$new_views[$key] = $value;
			if ($position === 'after' && $key === $anchor_key && !$inserted) {
				$new_views[$new_key] = $html;
				$inserted = true;
			}
		}

		if (!$inserted) {
			$new_views[$new_key] = $html;
		}

		return $new_views;
	}

	function cmxbel_view_insert_before(array $views, string $anchor_key, string $new_key, string $html): array {
		return cmxbel_view_insert_relative($views, $anchor_key, $new_key, $html, 'before');
	}

	function cmxbel_view_insert_after(array $views, string $anchor_key, string $new_key, string $html): array {
		return cmxbel_view_insert_relative($views, $anchor_key, $new_key, $html, 'after');
	}
}

\add_filter('views_edit-' . CMX_PT_BELEGE, __NAMESPACE__ . '\\cmxbel_views_normalize_current', 999);

/* ===== Export-Link in der Belege-Liste ===== */
\add_filter('views_edit-' . CMX_PT_BELEGE, function(array $views): array {
	if (!\current_user_can('edit_posts')) {
		return $views;
	}

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
	$args['action'] = 'cmx_export_belege_transfer_list';
	$args['cmx_export_format'] = 'zip';

	$url = \wp_nonce_url(\add_query_arg($args, \admin_url('admin-post.php')), 'cmx_export_belege_transfer_list');
	$link = '<a href="' . \esc_url($url) . '">exportieren</a>';

	return cmxbel_view_insert_before($views, 'cmx_deckungsbeitrag', 'cmx_export_belege_transfer', $link);
}, 35);

/* ===== Export-Handler ===== */
\add_action('admin_post_cmx_export_belege_transfer_list', function (): void {
	if (!\current_user_can('edit_posts')) {
		\wp_die('Keine Berechtigung.');
	}
	if (!\wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'cmx_export_belege_transfer_list')) {
		\wp_die('Ungültige Anfrage.');
	}

	$post_ids = \function_exists(__NAMESPACE__ . '\\cmxbeg_collect_export_belege_ids')
		? (array) cmxbeg_collect_export_belege_ids()
		: [];
	$format = \function_exists(__NAMESPACE__ . '\\cmxbeg_requested_export_format')
		? (string) cmxbeg_requested_export_format()
		: 'zip';

	if ($format === 'zip') {
		cmxbeg_stream_belege_export_zip_from_ids($post_ids);
	}

	cmxbeg_stream_belege_csv_from_ids($post_ids);
});

/* ===== Helpers ===== */
if (!\function_exists(__NAMESPACE__ . '\\cmxbeg_requested_export_format')) {
	function cmxbeg_requested_export_format(): string {
		$format = isset($_REQUEST['cmx_export_format']) ? \sanitize_key((string) \wp_unslash($_REQUEST['cmx_export_format'])) : 'zip';
		return \in_array($format, ['csv', 'zip'], true) ? $format : 'zip';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbeg_export_stamp')) {
	function cmxbeg_export_stamp(): string {
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

if (!\function_exists(__NAMESPACE__ . '\\cmxbeg_export_base_filename')) {
	function cmxbeg_export_base_filename(): string {
		$prefix = \function_exists(__NAMESPACE__ . '\\cmx_export_actor_prefix')
			? (string) cmx_export_actor_prefix()
			: 'misbuero';
		return $prefix . '-belege-export-' . cmxbeg_export_stamp();
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbeg_export_filename')) {
	function cmxbeg_export_filename(string $ext = 'csv'): string {
		$ext = \strtolower(\trim($ext, ". \t\n\r\0\x0B"));
		if ($ext === '') {
			$ext = 'dat';
		}
		return cmxbeg_export_base_filename() . '.' . $ext;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbeg_requested_export_belege_selected_ids')) {
	function cmxbeg_requested_export_belege_selected_ids(): array {
		return isset($_REQUEST['post'])
			? \array_values(\array_filter(\array_map('intval', (array) $_REQUEST['post'])))
			: [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbeg_query_filtered_belege_ids_from_current_request')) {
	function cmxbeg_query_filtered_belege_ids_from_current_request(array $base_query_vars): array {
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
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbeg_build_tax_query_from_request')) {
	function cmxbeg_build_tax_query_from_request(array $source): array {
		$tax_query = [];
		$taxonomies = \get_object_taxonomies(CMX_PT_BELEGE, 'objects');

		foreach ($taxonomies as $taxonomy) {
			$candidates = \array_values(\array_unique(\array_filter([
				(string) ($taxonomy->query_var ?? ''),
				(string) ($taxonomy->name ?? ''),
				'filter_' . (string) ($taxonomy->name ?? ''),
				(!empty($taxonomy->query_var) ? ('filter_' . (string) $taxonomy->query_var) : ''),
			])));

			$value = null;
			foreach ($candidates as $param) {
				if (!\array_key_exists($param, $source)) {
					continue;
				}

				$tmp = $source[$param];
				if (\is_array($tmp)) {
					$tmp = \array_values(\array_filter($tmp, static fn($v): bool => $v !== '' && $v !== '0' && $v !== '-1'));
					if ($tmp !== []) {
						$value = $tmp;
						break;
					}
				} elseif ($tmp !== '' && $tmp !== '0' && $tmp !== '-1') {
					$value = $tmp;
					break;
				}
			}

			if ($value === null) {
				continue;
			}

			$field = \is_array($value)
				? (\is_numeric((string) \reset($value)) ? 'term_id' : 'slug')
				: (\is_numeric((string) $value) ? 'term_id' : 'slug');
			$tax_query[] = [
				'taxonomy' => (string) $taxonomy->name,
				'field' => $field,
				'terms' => \is_array($value) ? $value : [$value],
			];
		}

		return $tax_query;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbeg_collect_export_belege_ids')) {
	function cmxbeg_collect_export_belege_ids(): array {
		$selected_ids = \function_exists(__NAMESPACE__ . '\\cmxbeg_requested_export_belege_selected_ids')
			? (array) cmxbeg_requested_export_belege_selected_ids()
			: [];

		$qv = [
			'post_type' => CMX_PT_BELEGE,
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'orderby' => 'ID',
			'order' => 'ASC',
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

			$tax_query = \function_exists(__NAMESPACE__ . '\\cmxbeg_build_tax_query_from_request')
				? (array) cmxbeg_build_tax_query_from_request((array) $_REQUEST)
				: [];
			if ($tax_query !== []) {
				$qv['tax_query'] = \array_merge(['relation' => 'AND'], $tax_query);
			}

			return \function_exists(__NAMESPACE__ . '\\cmxbeg_query_filtered_belege_ids_from_current_request')
				? (array) cmxbeg_query_filtered_belege_ids_from_current_request($qv)
				: [];
		}

		$query = new \WP_Query($qv);
		return \array_values(\array_filter(\array_map('intval', (array) $query->posts)));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbeg_collect_export_dataset')) {
	function cmxbeg_collect_export_dataset(array $post_ids): array {
		$post_ids = \array_values(\array_filter(\array_map('intval', $post_ids)));
		$taxonomies = \get_object_taxonomies(CMX_PT_BELEGE, 'objects');
		\usort($taxonomies, static fn($left, $right): int => \strcmp((string) $left->name, (string) $right->name));

		$base_headers = ['post_id', 'post_title', 'post_status', 'post_date', 'post_slug', 'permalink', 'featured_image'];
		$meta_keys = [];
		$tax_headers = [];

		foreach ($post_ids as $post_id) {
			foreach (\get_post_meta($post_id) as $key => $values) {
				$meta_keys[(string) $key] = true;
			}
		}

		$meta_names = \array_keys($meta_keys);
		\natcasesort($meta_names);
		foreach ($taxonomies as $taxonomy) {
			$tax_headers[] = 'tax__' . (string) $taxonomy->name;
		}

		$meta_headers = \array_map(static fn(string $key): string => 'meta__' . $key, $meta_names);
		$headers = \array_merge($base_headers, $meta_headers, $tax_headers);
		$rows = [];

		foreach ($post_ids as $post_id) {
			$post = \get_post($post_id);
			if (!$post) {
				continue;
			}

			$row = [
				'post_id' => $post_id,
				'post_title' => $post->post_title,
				'post_status' => $post->post_status,
				'post_date' => \get_date_from_gmt($post->post_date_gmt, 'Y-m-d H:i:s'),
				'post_slug' => $post->post_name,
				'permalink' => \get_permalink($post_id),
				'featured_image' => \get_post_thumbnail_id($post_id) ? \wp_get_attachment_url(\get_post_thumbnail_id($post_id)) : '',
			];

			$all_meta = \get_post_meta($post_id);
			foreach ($meta_headers as $meta_header) {
				$key = \substr($meta_header, 6);
				$values = $all_meta[$key] ?? [];
				$normalized = \array_map(static function ($value): string {
					if (\is_array($value) || \is_object($value)) {
						return (string) \wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
					}
					return (string) $value;
				}, (array) $values);
				$row[$meta_header] = \implode(' | ', $normalized);
			}

			foreach ($taxonomies as $taxonomy) {
				$key = 'tax__' . (string) $taxonomy->name;
				$terms = \wp_get_post_terms($post_id, (string) $taxonomy->name, ['fields' => 'names']);
				$row[$key] = \is_wp_error($terms) ? '' : \implode(', ', $terms);
			}

			$rows[] = $row;
		}

		return [
			'headers' => $headers,
			'rows' => $rows,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbeg_write_csv_dataset')) {
	function cmxbeg_write_csv_dataset($handle, array $dataset): void {
		$headers = (array) ($dataset['headers'] ?? []);
		$rows = (array) ($dataset['rows'] ?? []);

		\fwrite($handle, "\xEF\xBB\xBF");
		\fputcsv($handle, $headers, ';', '"', '\\');

		foreach ($rows as $row) {
			$line = [];
			foreach ($headers as $header) {
				$line[] = (string) ($row[$header] ?? '');
			}
			\fputcsv($handle, $line, ';', '"', '\\');
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbeg_csv_string_from_dataset')) {
	function cmxbeg_csv_string_from_dataset(array $dataset): string {
		$handle = \fopen('php://temp', 'r+');
		if (!$handle) {
			return '';
		}

		cmxbeg_write_csv_dataset($handle, $dataset);
		\rewind($handle);
		$content = (string) \stream_get_contents($handle);
		\fclose($handle);
		return $content;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbeg_stream_belege_csv_from_ids')) {
	function cmxbeg_stream_belege_csv_from_ids(array $post_ids): void {
		$dataset = cmxbeg_collect_export_dataset($post_ids);

		\header('Content-Type: text/csv; charset=UTF-8');
		\header('Content-Disposition: attachment; filename="' . cmxbeg_export_filename('csv') . '"');
		\header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		\header('Pragma: no-cache');
		\header('Expires: 0');

		$handle = \fopen('php://output', 'w');
		if (!$handle) {
			exit;
		}

		cmxbeg_write_csv_dataset($handle, $dataset);
		\fclose($handle);
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbeg_stream_belege_export_zip_from_ids')) {
	function cmxbeg_stream_belege_export_zip_from_ids(array $post_ids): void {
		if (!\class_exists('\\ZipArchive')) {
			cmxbeg_stream_belege_csv_from_ids($post_ids);
		}

		$dataset = cmxbeg_collect_export_dataset($post_ids);
		$csv_content = cmxbeg_csv_string_from_dataset($dataset);
		$tmp_zip = \function_exists('\\wp_tempnam') ? \wp_tempnam('cmx-belege-transfer-zip') : \tempnam(\sys_get_temp_dir(), 'cmx-belege-transfer-zip');
		if (!\is_string($tmp_zip) || $tmp_zip === '') {
			cmxbeg_stream_belege_csv_from_ids($post_ids);
		}
		if (\is_file($tmp_zip)) {
			@unlink($tmp_zip);
		}

		$zip = new \ZipArchive();
		if ($zip->open($tmp_zip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
			@unlink($tmp_zip);
			cmxbeg_stream_belege_csv_from_ids($post_ids);
		}

		$zip->addFromString(cmxbeg_export_filename('csv'), $csv_content);
		$zip->close();

		$download_name = cmxbeg_export_filename('zip');
		$size = @\filesize($tmp_zip);

		\header('Content-Type: application/zip');
		\header('Content-Disposition: attachment; filename="' . $download_name . '"');
		if (\is_int($size) && $size > 0) {
			\header('Content-Length: ' . $size);
		}
		\header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		\header('Pragma: no-cache');
		\header('Expires: 0');

		\readfile($tmp_zip);
		@unlink($tmp_zip);
		exit;
	}
}

/* ===== JS: markierte Belege per POST mitsenden ===== */
\add_action('admin_footer-edit.php', function (): void {
	if (($_GET['post_type'] ?? '') !== CMX_PT_BELEGE) {
		return;
	}

	$action = \esc_js(\admin_url('admin-post.php'));
	$nonce = \esc_js(\wp_create_nonce('cmx_export_belege_transfer_list'));
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		const exportLink = [...document.querySelectorAll('.subsubsub a')]
			.find(function (link) {
				return /action=cmx_export_belege_transfer_list/i.test(link.href || '');
			});
		if (!exportLink) return;

		exportLink.addEventListener('click', function (event) {
			event.preventDefault();

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

			appendField('action', 'cmx_export_belege_transfer_list');
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
