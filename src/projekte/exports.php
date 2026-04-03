<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!defined(__NAMESPACE__ . '\\CMX_PT_PROJEKTE')) {
	define(__NAMESPACE__ . '\\CMX_PT_PROJEKTE', 'projekte');
}

/* ===== Export-Link in der Projektliste ===== */
\add_filter('views_edit-' . CMX_PT_PROJEKTE, function(array $views): array {
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
	$args['action'] = 'cmx_export_projekte_list';
	$args['cmx_export_format'] = 'zip';

	$url = \wp_nonce_url(\add_query_arg($args, \admin_url('admin-post.php')), 'cmx_export_projekte_list');
	$link = '<a href="' . \esc_url($url) . '">exportieren</a>';

	$new_views = [];
	$inserted = false;
	foreach ($views as $key => $html) {
		$new_views[$key] = $html;
		if ($key === 'trash' && !$inserted) {
			$new_views['cmx_export_projekte_list'] = $link;
			$inserted = true;
		}
	}
	if (!$inserted) {
		foreach ($new_views as $key => $html) {
			if ($key === 'all' && !$inserted) {
				$new_views['cmx_export_projekte_list'] = $link;
				$inserted = true;
			}
		}
	}
	if (!$inserted) {
		$new_views['cmx_export_projekte_list'] = $link;
	}

	return $new_views;
});

/* ===== Export-Handler ===== */
\add_action('admin_post_cmx_export_projekte_list', function (): void {
	if (!\current_user_can('edit_posts')) \wp_die('Keine Berechtigung.');
	if (!\wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'cmx_export_projekte_list')) \wp_die('Ungültige Anfrage.');

	$post_ids = \function_exists(__NAMESPACE__ . '\\cmxpr_collect_export_projekte_ids')
		? (array) cmxpr_collect_export_projekte_ids()
		: [];
	$format = \function_exists(__NAMESPACE__ . '\\cmxpr_requested_export_format')
		? (string) cmxpr_requested_export_format()
		: 'zip';

	if ($format === 'zip') {
		cmxpr_stream_projekte_export_zip_from_ids($post_ids);
	}

	cmxpr_stream_projekte_csv_from_ids($post_ids);
});

/* ===== Helpers ===== */
if (!\function_exists(__NAMESPACE__ . '\\cmxpr_requested_export_format')) {
	function cmxpr_requested_export_format(): string {
		$format = isset($_REQUEST['cmx_export_format']) ? \sanitize_key((string) \wp_unslash($_REQUEST['cmx_export_format'])) : 'zip';
		return \in_array($format, ['csv', 'zip'], true) ? $format : 'zip';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_export_stamp')) {
	function cmxpr_export_stamp(): string {
		$raw = isset($_REQUEST['cmx_export_stamp']) ? \sanitize_text_field((string) \wp_unslash($_REQUEST['cmx_export_stamp'])) : '';
		if (\preg_match('/^\d{8}-\d{6}$/', $raw)) {
			return $raw;
		}
		return \function_exists(__NAMESPACE__ . '\\cmx_export_now_stamp')
			? (string) cmx_export_now_stamp()
			: (string) \gmdate('Ymd-His');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_export_base_filename')) {
	function cmxpr_export_base_filename(): string {
		$prefix = \function_exists(__NAMESPACE__ . '\\cmx_export_actor_prefix')
			? (string) cmx_export_actor_prefix()
			: 'misbuero';
		return $prefix . '-projekte-export-' . cmxpr_export_stamp();
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_export_filename')) {
	function cmxpr_export_filename(string $ext = 'csv'): string {
		$ext = \strtolower(\trim($ext, ". \t\n\r\0\x0B"));
		if ($ext === '') $ext = 'dat';
		return cmxpr_export_base_filename() . '.' . $ext;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_requested_export_projekte_selected_ids')) {
	function cmxpr_requested_export_projekte_selected_ids(): array {
		return isset($_REQUEST['post'])
			? \array_values(\array_filter(\array_map('intval', (array) $_REQUEST['post'])))
			: [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_query_filtered_projekte_ids_from_current_request')) {
	function cmxpr_query_filtered_projekte_ids_from_current_request(array $base_query_vars): array {
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

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_collect_export_projekte_ids')) {
	function cmxpr_collect_export_projekte_ids(): array {
		$selected_ids = \function_exists(__NAMESPACE__ . '\\cmxpr_requested_export_projekte_selected_ids')
			? (array) cmxpr_requested_export_projekte_selected_ids()
			: [];

		$qv = [
			'post_type' => CMX_PT_PROJEKTE,
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
			foreach (['orderby', 'order', 'cmx_kunde_filter', 'cmx_status_filter', 'cmx_view', 'projekt_kategorie', 'projekte_status', 'status'] as $key) {
				$value = $_REQUEST[$key] ?? '';
				if ($value !== '') {
					$qv[$key] = \sanitize_text_field((string) $value);
				}
			}

			return \function_exists(__NAMESPACE__ . '\\cmxpr_query_filtered_projekte_ids_from_current_request')
				? (array) cmxpr_query_filtered_projekte_ids_from_current_request($qv)
				: [];
		}

		$q = new \WP_Query($qv);
		return \array_values(\array_filter(\array_map('intval', (array) $q->posts)));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_collect_export_dataset')) {
	function cmxpr_collect_export_dataset(array $post_ids): array {
		$post_ids = \array_values(\array_filter(\array_map('intval', $post_ids)));
		$taxonomies = \get_object_taxonomies(CMX_PT_PROJEKTE, 'objects');
		$base_headers = ['post_id', 'post_title', 'post_status', 'post_date', 'post_slug', 'permalink', 'featured_image'];
		$meta_keys = [];
		$tax_headers = [];

		foreach ($post_ids as $post_id) {
			foreach (\get_post_meta($post_id) as $key => $values) {
				$meta_keys[$key] = true;
			}
		}
		foreach ($taxonomies as $taxonomy) {
			$tax_headers[] = 'tax__' . $taxonomy->name;
		}

		$meta_headers = \array_map(static fn(string $key): string => 'meta__' . $key, \array_keys($meta_keys));
		$headers = \array_merge($base_headers, $meta_headers, $tax_headers);
		$rows = [];

		foreach ($post_ids as $post_id) {
			$post = \get_post($post_id);
			if (!$post) continue;

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
					$value = \maybe_unserialize($value);
					if (\is_array($value) || \is_object($value)) {
						return (string) \wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
					}
					return (string) $value;
				}, (array) $values);
				$row[$meta_header] = \implode(' | ', $normalized);
			}

			foreach ($taxonomies as $taxonomy) {
				$key = 'tax__' . $taxonomy->name;
				$terms = \wp_get_post_terms($post_id, $taxonomy->name, ['fields' => 'names']);
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

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_write_csv_dataset')) {
	function cmxpr_write_csv_dataset($fh, array $dataset): void {
		$headers = (array) ($dataset['headers'] ?? []);
		$rows = (array) ($dataset['rows'] ?? []);

		\fwrite($fh, "\xEF\xBB\xBF");
		\fputcsv($fh, $headers, ';', '"', '\\');

		foreach ($rows as $row) {
			if (!\is_array($row)) continue;
			$out = [];
			foreach ($headers as $header) {
				$value = $row[$header] ?? '';
				if (\is_array($value) || \is_object($value)) {
					$value = (string) \wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				}
				$out[] = \str_replace(["\r", "\n"], ' ', (string) $value);
			}
			\fputcsv($fh, $out, ';', '"', '\\');
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_stream_projekte_csv_from_ids')) {
	function cmxpr_stream_projekte_csv_from_ids(array $post_ids): void {
		$dataset = \function_exists(__NAMESPACE__ . '\\cmxpr_collect_export_dataset')
			? (array) cmxpr_collect_export_dataset($post_ids)
			: ['headers' => [], 'rows' => []];
		$filename = \function_exists(__NAMESPACE__ . '\\cmxpr_export_filename')
			? (string) cmxpr_export_filename('csv')
			: ('projekte-export-' . \gmdate('Ymd-His') . '.csv');

		\header('Content-Type: text/csv; charset=UTF-8');
		\header('Content-Disposition: attachment; filename="' . $filename . '"');
		\header('Pragma: no-cache');
		\header('Expires: 0');

		$fh = \fopen('php://output', 'w');
		cmxpr_write_csv_dataset($fh, $dataset);
		\fclose($fh);
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxpr_stream_projekte_export_zip_from_ids')) {
	function cmxpr_stream_projekte_export_zip_from_ids(array $post_ids): void {
		if (!\class_exists('\\ZipArchive')) {
			cmxpr_stream_projekte_csv_from_ids($post_ids);
		}

		$tmp_zip = \function_exists('\\wp_tempnam') ? \wp_tempnam('projekte-export-') : \tempnam(\sys_get_temp_dir(), 'projekte-export-');
		$tmp_csv = \function_exists('\\wp_tempnam') ? \wp_tempnam('projekte-export-csv-') : \tempnam(\sys_get_temp_dir(), 'projekte-export-csv-');
		if (!$tmp_zip || !$tmp_csv) {
			cmxpr_stream_projekte_csv_from_ids($post_ids);
		}

		$dataset = \function_exists(__NAMESPACE__ . '\\cmxpr_collect_export_dataset')
			? (array) cmxpr_collect_export_dataset($post_ids)
			: ['headers' => [], 'rows' => []];

		$csv_handle = \fopen($tmp_csv, 'w');
		if (!$csv_handle) {
			@unlink((string) $tmp_zip);
			@unlink((string) $tmp_csv);
			cmxpr_stream_projekte_csv_from_ids($post_ids);
		}
		cmxpr_write_csv_dataset($csv_handle, $dataset);
		\fclose($csv_handle);

		$zip = new \ZipArchive();
		if ($zip->open($tmp_zip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
			@unlink((string) $tmp_zip);
			@unlink((string) $tmp_csv);
			cmxpr_stream_projekte_csv_from_ids($post_ids);
		}

		$csv_name = \function_exists(__NAMESPACE__ . '\\cmxpr_export_filename')
			? (string) cmxpr_export_filename('csv')
			: ('projekte-export-' . \gmdate('Ymd-His') . '.csv');
		$zip->addFile($tmp_csv, $csv_name);
		$zip->close();

		$filename = \function_exists(__NAMESPACE__ . '\\cmxpr_export_filename')
			? (string) cmxpr_export_filename('zip')
			: ('projekte-export-' . \gmdate('Ymd-His') . '.zip');

		\header('Content-Type: application/zip');
		\header('Content-Disposition: attachment; filename="' . $filename . '"');
		\header('Content-Length: ' . (string) \filesize($tmp_zip));
		\header('Pragma: no-cache');
		\header('Expires: 0');
		\readfile($tmp_zip);
		@unlink($tmp_csv);
		@unlink($tmp_zip);
		exit;
	}
}

/* ===== JS: markierte Projekte + aktuelle Filter per POST mitsenden ===== */
\add_action('admin_footer-edit.php', function (): void {
	if (($_GET['post_type'] ?? '') !== CMX_PT_PROJEKTE) return;
	$action = \esc_js(\admin_url('admin-post.php'));
	$nonce = \esc_js(\wp_create_nonce('cmx_export_projekte_list'));
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		const exportLink = [...document.querySelectorAll('.subsubsub a')]
			.find(a => a.textContent.trim().toLowerCase() === 'exportieren' || /action=cmx_export_projekte_list/i.test(a.href));
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

			appendField('action', 'cmx_export_projekte_list');
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
