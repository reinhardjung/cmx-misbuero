<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

require_once __DIR__ . '/upload_form.php';

if (!defined(__NAMESPACE__ . '\\CMX_BELEG_UPLOADS_META')) {
	define(__NAMESPACE__ . '\\CMX_BELEG_UPLOADS_META', '_cmx_belege_uploads');
}
if (!defined(__NAMESPACE__ . '\\CMX_BELEG_UPLOADS_UNLINKED_META')) {
	define(__NAMESPACE__ . '\\CMX_BELEG_UPLOADS_UNLINKED_META', '_cmx_belege_uploads_unlinked');
}

function cmx_belege_upload_normalize_rel_path(string $path): string {
	return ltrim(str_replace('\\', '/', $path), '/');
}

function cmx_belege_upload_unlinked_keys(int $post_id): array {
	$raw = (array) get_post_meta($post_id, CMX_BELEG_UPLOADS_UNLINKED_META, true);
	$keys = [];
	foreach ($raw as $key) {
		$key = trim((string) $key);
		if ($key !== '') {
			$keys[] = $key;
		}
	}
	return array_values(array_unique($keys));
}

function cmx_belege_upload_path_key(string $path): string {
	return 'path:' . cmx_belege_upload_normalize_rel_path($path);
}

function cmx_belege_upload_att_key(int $att_id): string {
	return 'att:' . (string) $att_id;
}

function cmx_belege_upload_is_unlinked(int $post_id, int $att_id = 0, string $path = ''): bool {
	$keys = cmx_belege_upload_unlinked_keys($post_id);
	if ($att_id > 0 && in_array(cmx_belege_upload_att_key($att_id), $keys, true)) {
		return true;
	}
	if ($path !== '' && in_array(cmx_belege_upload_path_key($path), $keys, true)) {
		return true;
	}
	return false;
}

function cmx_belege_upload_mark_unlinked(int $post_id, int $att_id = 0, string $path = ''): void {
	$keys = cmx_belege_upload_unlinked_keys($post_id);
	if ($att_id > 0) {
		$keys[] = cmx_belege_upload_att_key($att_id);
	}
	if ($path !== '') {
		$keys[] = cmx_belege_upload_path_key($path);
	}
	$keys = array_values(array_unique(array_filter($keys)));
	if ($keys) {
		update_post_meta($post_id, CMX_BELEG_UPLOADS_UNLINKED_META, $keys);
	} else {
		delete_post_meta($post_id, CMX_BELEG_UPLOADS_UNLINKED_META);
	}
}

function cmx_belege_upload_forget_unlinked(int $post_id, int $att_id = 0, string $path = ''): void {
	$remove = [];
	if ($att_id > 0) {
		$remove[] = cmx_belege_upload_att_key($att_id);
	}
	if ($path !== '') {
		$remove[] = cmx_belege_upload_path_key($path);
	}
	if (!$remove) {
		return;
	}
	$keys = array_values(array_diff(cmx_belege_upload_unlinked_keys($post_id), $remove));
	if ($keys) {
		update_post_meta($post_id, CMX_BELEG_UPLOADS_UNLINKED_META, $keys);
	} else {
		delete_post_meta($post_id, CMX_BELEG_UPLOADS_UNLINKED_META);
	}
}

function cmx_belege_upload_abs_path_from_rel(string $rel): string {
	return WP_CONTENT_DIR . '/uploads/' . cmx_belege_upload_normalize_rel_path($rel);
}

function cmx_belege_upload_rel_from_abs(string $abs): string {
	return cmx_belege_upload_normalize_rel_path(str_replace(trailingslashit(WP_CONTENT_DIR . '/uploads'), '', wp_normalize_path($abs)));
}

function cmx_belege_upload_allowed_abs_path(string $abs): bool {
	$norm = wp_normalize_path($abs);
	if (strpos($norm, wp_normalize_path(WP_CONTENT_DIR . '/uploads/misbuero/')) !== 0) {
		return false;
	}
	if (strpos($norm, '/belege/') !== false || strpos($norm, '/belegeingang/') !== false) {
		return true;
	}
	return function_exists(__NAMESPACE__ . '\\cmx_belege_is_carent_contract_abs_path') && cmx_belege_is_carent_contract_abs_path($norm);
}

function cmx_belege_upload_candidate(int $att_id = 0, string $path = ''): array {
	$file_abs = '';
	$file_rel = '';
	$url = '';
	if ($att_id > 0) {
		$file_abs = (string) get_attached_file($att_id);
		$file_rel = (string) get_post_meta($att_id, '_wp_attached_file', true);
		$url = (string) wp_get_attachment_url($att_id);
	} else {
		$file_rel = cmx_belege_upload_normalize_rel_path($path);
		$file_abs = cmx_belege_upload_abs_path_from_rel($file_rel);
		$url = content_url('/uploads/' . $file_rel);
	}
	if ($file_rel === '' || $file_abs === '' || !is_file($file_abs) || !cmx_belege_upload_allowed_abs_path($file_abs)) {
		return [];
	}
	$label = basename($file_rel);
	if ($label === '' && $att_id > 0) {
		$label = get_the_title($att_id) ?: ('#' . $att_id);
	}
	return [
		'att_id' => $att_id,
		'path' => $file_rel,
		'url' => $url,
		'label' => $label,
		'mtime' => (int) @filemtime($file_abs),
	];
}

function cmx_belege_upload_post_ids_by_reference(int $att_id = 0, string $path = ''): array {
	$path = cmx_belege_upload_normalize_rel_path($path);
	$post_ids = [];
	$query = new \WP_Query([
		'post_type' => 'belege',
		'post_status' => 'any',
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
		'meta_query' => [
			[
				'key' => CMX_BELEG_UPLOADS_META,
				'compare' => 'EXISTS',
			],
		],
	]);
	foreach ((array) $query->posts as $post_id) {
		$entries = (array) get_post_meta((int) $post_id, CMX_BELEG_UPLOADS_META, true);
		foreach ($entries as $entry) {
			if ($att_id > 0 && (string) $entry === (string) $att_id) {
				$post_ids[] = (int) $post_id;
				break;
			}
			if ($path !== '' && cmx_belege_upload_normalize_rel_path((string) $entry) === $path) {
				$post_ids[] = (int) $post_id;
				break;
			}
		}
	}
	return array_values(array_unique($post_ids));
}

function cmx_belege_upload_remove_reference_from_post(int $post_id, int $att_id = 0, string $path = ''): void {
	$path = cmx_belege_upload_normalize_rel_path($path);
	$existing = (array) get_post_meta($post_id, CMX_BELEG_UPLOADS_META, true);
	$filtered = [];
	foreach ($existing as $entry) {
		if ($att_id > 0 && (string) $entry === (string) $att_id) {
			continue;
		}
		if ($path !== '' && cmx_belege_upload_normalize_rel_path((string) $entry) === $path) {
			continue;
		}
		if ($entry !== '' && $entry !== null) {
			$filtered[] = $entry;
		}
	}
	update_post_meta($post_id, CMX_BELEG_UPLOADS_META, array_values($filtered));
}

function cmx_belege_upload_existing_candidates(int $current_post_id, string $query = '', int $limit = 30): array {
	$query = strtolower(trim($query));
	$candidates = [];
	$current = (array) get_post_meta($current_post_id, CMX_BELEG_UPLOADS_META, true);
	$current_keys = [];
	foreach ($current as $entry) {
		if (is_numeric($entry)) {
			$current_keys[] = cmx_belege_upload_att_key((int) $entry);
			$rel = (string) get_post_meta((int) $entry, '_wp_attached_file', true);
			if ($rel !== '') {
				$current_keys[] = cmx_belege_upload_path_key($rel);
			}
		} else {
			$current_keys[] = cmx_belege_upload_path_key((string) $entry);
		}
	}
	$current_keys = array_values(array_unique($current_keys));

	$add_candidate = function(array $candidate) use (&$candidates, $current_post_id, $current_keys, $query): void {
		if (!$candidate) {
			return;
		}
		$att_id = (int) ($candidate['att_id'] ?? 0);
		$path = (string) ($candidate['path'] ?? '');
		if ($att_id > 0 && in_array(cmx_belege_upload_att_key($att_id), $current_keys, true)) {
			return;
		}
		if ($path !== '' && in_array(cmx_belege_upload_path_key($path), $current_keys, true) && !cmx_belege_upload_is_unlinked($current_post_id, $att_id, $path)) {
			return;
		}
		$haystack = strtolower((string) ($candidate['label'] ?? '') . ' ' . $path);
		$key = $att_id > 0 ? cmx_belege_upload_att_key($att_id) : cmx_belege_upload_path_key($path);
		if ($key === 'path:') {
			return;
		}
		$refs = cmx_belege_upload_post_ids_by_reference($att_id, $path);
		$search_refs = [];
		foreach ($refs as $ref_id) {
			$title = get_the_title((int) $ref_id);
			if ($title !== '') {
				$search_refs[] = $title;
			}
		}
		if ($query !== '' && strpos($haystack . ' ' . strtolower(implode(' ', $search_refs)), $query) === false) {
			return;
		}
		$candidate['linked_count'] = count($refs);
		$candidates[$key] = $candidate;
	};

	$post_ids = get_posts([
		'post_type' => 'belege',
		'post_status' => 'any',
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
		'meta_key' => CMX_BELEG_UPLOADS_META,
	]);
	foreach ((array) $post_ids as $post_id) {
		foreach ((array) get_post_meta((int) $post_id, CMX_BELEG_UPLOADS_META, true) as $entry) {
			$add_candidate(cmx_belege_upload_candidate(is_numeric($entry) ? (int) $entry : 0, is_numeric($entry) ? '' : (string) $entry));
		}
	}

	foreach ([WP_CONTENT_DIR . '/uploads/misbuero/archiv/*/belege/*_upload_*'] as $pattern) {
		foreach (glob($pattern) ?: [] as $abs) {
			if (!is_file($abs)) {
				continue;
			}
			$base = basename($abs);
			if (!preg_match('/_upload_\\d{3}\\.(pdf|png|jpe?g)$/i', $base)) {
				continue;
			}
			$add_candidate(cmx_belege_upload_candidate(0, cmx_belege_upload_rel_from_abs($abs)));
		}
	}

	$items = array_values($candidates);
	usort($items, function(array $a, array $b): int {
		return (int) ($b['mtime'] ?? 0) <=> (int) ($a['mtime'] ?? 0);
	});
	return array_slice($items, 0, max(1, $limit));
}

function cmx_is_beleg_upload_request(): bool {
	return isset($_FILES['beleg_datei']);
}

function cmx_get_beleg_upload_stamp(): string {
	static $stamp = '';
	if ($stamp === '') {
		$stamp = wp_date('ymd-His');
	}
	return $stamp;
}

function cmx_beleg_extract_year_from_date_value($raw): int {
	$value = \trim((string) $raw);
	if ($value === '') {
		return 0;
	}

	$year = 0;
	if (\preg_match('/^\d{4}$/', $value)) {
		$year = (int) $value;
	} elseif (\preg_match('/^(\d{4})[-\/\.]\d{1,2}[-\/\.]\d{1,2}$/', $value, $m)) {
		$year = (int) $m[1];
	} elseif (\preg_match('/^\d{1,2}[-\/\.]\d{1,2}[-\/\.](\d{4})$/', $value, $m)) {
		$year = (int) $m[1];
	} else {
		$ts = \strtotime($value);
		if ($ts !== false) {
			$year = (int) \wp_date('Y', $ts);
		} elseif (\preg_match('/\b(19\d{2}|20\d{2}|21\d{2}|22\d{2})\b/', $value, $m)) {
			$year = (int) $m[1];
		}
	}

	return ($year >= 1900 && $year <= 2200) ? $year : 0;
}

function cmx_get_beleg_upload_year(int $post_id = 0): int {
	$default_year = (int) \wp_date('Y');

	$candidates = [];
	foreach (['cmx_beleg_rng_datum', 'beleg_datum'] as $key) {
		if (!isset($_POST[$key])) continue;
		$candidates[] = \sanitize_text_field((string) \wp_unslash($_POST[$key]));
	}

	if ($post_id > 0) {
		foreach (['_cmx_beleg_rng_datum', 'beleg_datum', '_cmx_rechnungsdatum', '_invoice_date', '_date'] as $meta_key) {
			$candidates[] = (string) \get_post_meta($post_id, $meta_key, true);
		}
		$post = \get_post($post_id);
		if ($post instanceof \WP_Post) {
			$candidates[] = (string) ($post->post_date ?? '');
			$candidates[] = (string) ($post->post_date_gmt ?? '');
		}
	}

	foreach ($candidates as $candidate) {
		$year = \function_exists(__NAMESPACE__ . '\\cmx_beleg_extract_year_from_date_value')
			? cmx_beleg_extract_year_from_date_value($candidate)
			: 0;
		if ($year > 0) {
			return $year;
		}
	}

	return $default_year;
}

function cmx_belege_upload_dir(int $year): array {
	$base = WP_CONTENT_DIR . '/uploads/misbuero/archiv/' . $year . '/belege';
	$url  = content_url('/uploads/misbuero/archiv/' . $year . '/belege');
	if (!is_dir($base)) {
		wp_mkdir_p($base);
	}
	return [$base, $url];
}

function cmx_belege_next_suffix(string $dir, string $prefix): int {
	if ($prefix === '' || !is_dir($dir)) {
		return 1;
	}
	$max = 0;
	foreach (glob($dir . '/' . $prefix . '_upload_*') ?: [] as $path) {
		$base = basename($path);
		if (preg_match('/_upload_([0-9]{3})/i', $base, $m)) {
			$num = (int) $m[1];
			if ($num > $max) {
				$max = $num;
			}
		}
	}
	return $max + 1;
}

function cmx_belege_is_carent_contract_abs_path(string $abs_path): bool {
	$norm = \wp_normalize_path($abs_path);
	if ($norm === '' || \strpos($norm, '/uploads/misbuero/') === false) {
		return false;
	}

	return (bool) \preg_match('#/uploads/misbuero/ca(?:e)?rent/vertraege/.+\.pdf$#i', $norm)
		|| (bool) \preg_match('#/uploads/misbuero/archiv/\d{4}/ca(?:e)?rent/vertraege/.+\.pdf$#i', $norm)
		|| (bool) \preg_match('#/uploads/misbuero/archiv/\d{4}/ca(?:e)?rent/[^/]+/mietvertrag\.pdf$#i', $norm);
}

\add_filter('upload_dir', function(array $dirs): array {
	if (!cmx_is_beleg_upload_request()) {
		return $dirs;
	}

	$year = \function_exists(__NAMESPACE__ . '\\cmx_get_beleg_upload_year')
		? cmx_get_beleg_upload_year(0)
		: (int) wp_date('Y');
	[$base, $url] = cmx_belege_upload_dir((int) $year);
	$dirs['path']    = $base;
	$dirs['basedir'] = $base;
	$dirs['url']     = $url;
	$dirs['baseurl'] = $url;
	$dirs['subdir']  = '';
	return $dirs;
}, 5);

\add_filter('wp_handle_upload_prefilter', function(array $file): array {
	if (!cmx_is_beleg_upload_request()) {
		return $file;
	}

	$stamp = cmx_get_beleg_upload_stamp();
	$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
	$file['name'] = $stamp . '_upload' . ($ext ? '.' . $ext : '');
	return $file;
});

\add_filter('wp_insert_post_data', function(array $data, array $postarr): array {
	if (!cmx_is_beleg_upload_request()) {
		return $data;
	}
	if (($data['post_type'] ?? '') !== 'belege') {
		return $data;
	}
	$stamp = cmx_get_beleg_upload_stamp();
	if ($stamp !== '') {
		$data['post_title'] = $stamp;
	}
	return $data;
}, 10, 2);

\add_action('add_meta_boxes', function($post_type) {
	if ((string)$post_type !== 'belege') {
		return;
	}
	$scanner_url = \admin_url('edit.php?post_type=scanner');
	$uploads_title = '<a href="' . \esc_url($scanner_url) . '" target="_blank" rel="noopener noreferrer" style="text-decoration:none;font-weight:700;font-size:14px;line-height:1.2;" onclick="event.stopPropagation();">Originalbeleg</a>';
	\add_meta_box(
		'cmx_uploads_box',
		$uploads_title,
		__NAMESPACE__ . '\\cmx_render_uploads_box',
		$post_type,
		'side',
		'high'
	);
}, 10, 1);

function cmx_render_uploads_box(\WP_Post $post): void {
	$nonce = wp_create_nonce('cmx_belege_upload');
	$docs = (array) get_post_meta($post->ID, CMX_BELEG_UPLOADS_META, true);
	$docs = array_values(array_filter($docs, function($v){ return $v !== '' && $v !== null; }));
	$docs = array_values(array_filter($docs, function($entry) use ($post): bool {
		return !cmx_belege_upload_is_unlinked((int) $post->ID, is_numeric($entry) ? (int) $entry : 0, is_numeric($entry) ? '' : (string) $entry);
	}));
	$post_slug = sanitize_title((string) get_the_title($post->ID));
	$clean_docs = [];
	if (!$docs) {
		$prefix = get_post_meta($post->ID, '_cmx_beleg_upload_prefix', true);
		$prefixes = [];
		if (is_string($prefix) && $prefix !== '') {
			$prefixes[] = $prefix;
		}
		if ($post_slug !== '' && $post_slug !== $prefix) {
			$prefixes[] = $post_slug;
		}
		$year = \function_exists(__NAMESPACE__ . '\\cmx_get_beleg_upload_year')
			? cmx_get_beleg_upload_year((int) $post->ID)
			: (int) wp_date('Y');
		[$dir_base] = cmx_belege_upload_dir((int) $year);
		foreach ($prefixes as $scan_prefix) {
			$found = [];
			foreach (glob($dir_base . '/' . $scan_prefix . '_upload_*') ?: [] as $path) {
				$rel = ltrim(str_replace(trailingslashit(WP_CONTENT_DIR . '/uploads'), '', $path), '/');
				if (cmx_belege_upload_is_unlinked((int) $post->ID, 0, $rel)) {
					continue;
				}
				$found[] = $rel;
			}
			if ($found) {
				$docs = $found;
				update_post_meta($post->ID, CMX_BELEG_UPLOADS_META, $docs);
				break;
			}
		}
	}
	if (!$docs) {
		$children = get_children([
			'post_parent' => $post->ID,
			'post_type' => 'attachment',
			'numberposts' => -1,
			'post_status' => 'inherit',
		]);
		$docs = $children ? array_map(function($p){ return (int) $p->ID; }, $children) : [];
		$docs = array_values(array_filter($docs, function($att_id) use ($post): bool {
			$rel = $att_id > 0 ? (string) get_post_meta((int) $att_id, '_wp_attached_file', true) : '';
			return !cmx_belege_upload_is_unlinked((int) $post->ID, (int) $att_id, $rel);
		}));
		if ($docs) {
			update_post_meta($post->ID, CMX_BELEG_UPLOADS_META, $docs);
		}
	}

	echo '<div id="cmx-belege-upload-box">';
	echo '<div id="cmx-belege-drop" style="border:2px dashed #ccd0d4;padding:10px;text-align:center;background:#fafafa;cursor:pointer;">';
	echo '<strong>Datei hier ablegen oder auswählen</strong><br><small>PDF, PNG, JPG</small>';
	echo '</div>';
	echo '<input type="file" id="cmx-belege-file" style="display:none" accept=".pdf,.png,.jpg,.jpeg">';
	echo '<div id="cmx-belege-upload-status" aria-live="polite" style="display:none;margin:8px 0 0;padding:8px 10px;border-left:4px solid #72aee6;background:#f0f6fc;color:#1d2327;font-size:12px;line-height:1.4;"></div>';
	echo '<div id="cmx-belege-existing-picker" style="margin-top:10px;border-top:1px solid #dcdcde;padding-top:8px;">';
	echo '<button type="button" class="button button-small" id="cmx-belege-existing-toggle" style="width:100%;">Bestehenden Originalbeleg auswählen</button>';
	echo '<div id="cmx-belege-existing-search-wrap" style="display:none;margin-top:8px;">';
	echo '<input type="search" id="cmx-belege-existing-search" class="widefat" placeholder="Dateiname oder Beleg-Nr. suchen ...">';
	echo '<div id="cmx-belege-existing-results" style="display:none;margin-top:6px;max-height:180px;overflow:auto;border:1px solid #c3c4c7;background:#fff;"></div>';
	echo '</div>';
	echo '</div>';
	echo '</div>';

	echo '<ul id="cmx-belege-existing" style="margin:6px 0 0 0;padding:0;list-style:none;max-height:160px;overflow:auto;width:100%;">';
	if ($docs) {
		foreach ($docs as $entry) {
			$att_id = is_numeric($entry) ? (int) $entry : 0;
			$file_rel = '';
			$url = '';
			$file_abs = '';
			if ($att_id) {
				$file_abs = get_attached_file($att_id);
				$file_rel = (string) get_post_meta($att_id, '_wp_attached_file', true);
				$url = wp_get_attachment_url($att_id);
			} else {
				$file_rel = ltrim((string) $entry, '/');
				$file_abs = WP_CONTENT_DIR . '/uploads/' . $file_rel;
				$url = content_url('/uploads/' . $file_rel);
			}
			if (cmx_belege_upload_is_unlinked((int) $post->ID, $att_id, $file_rel)) {
				continue;
			}
			if (!$file_abs || !is_file($file_abs)) {
				continue;
			}
			$norm = str_replace('\\', '/', $file_abs);
			$is_beleg_upload = strpos($norm, '/belege/') !== false || strpos($norm, '/belegeingang/') !== false;
			$is_carent_contract_link = cmx_belege_is_carent_contract_abs_path($norm);
			if (strpos($norm, '/uploads/misbuero/') === false || (!$is_beleg_upload && !$is_carent_contract_link)) {
				continue;
			}
			$file_base = $file_rel ? basename($file_rel) : '';
			$clean_docs[] = $att_id ?: $file_rel;
			$label = $file_base ?: ($att_id ? (get_the_title($att_id) ?: ('#' . $att_id)) : basename($file_abs));
			$data_attr = $att_id ? 'data-att-id="' . (int) $att_id . '"' : 'data-path="' . esc_attr($file_rel) . '"';
			$carent_edit_link = '';
			if ($is_carent_contract_link) {
				$carent_id = (int) get_post_meta($post->ID, '_cmx_carent_vermietung_id', true);
				if ($carent_id > 0 && get_post_type($carent_id) === 'carent') {
					$carent_edit_link = (string) get_edit_post_link($carent_id, 'raw');
				}
			}
			echo '<li ' . $data_attr . ' style="display:grid;grid-template-columns:' . ($carent_edit_link !== '' ? '26px ' : '') . '1fr 28px;align-items:center;gap:6px;width:100%;white-space:nowrap;">';
			if ($carent_edit_link !== '') {
				echo '<a href="' . esc_url($carent_edit_link) . '" class="dashicons dashicons-car" title="' . esc_attr__('Zum Vertrag', 'cmx-misbuero') . '" aria-label="' . esc_attr__('Zum Vertrag', 'cmx-misbuero') . '" style="text-decoration:none;color:#d63638;justify-self:center;font-size:24px;width:24px;height:24px;line-height:24px;"></a>';
			}
			if ($url) {
				echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr($label) . '" style="min-width:0;text-align:center;justify-self:stretch;overflow:hidden;text-overflow:ellipsis;">' . esc_html($label) . '</a>';
			} else {
				echo '<span title="' . esc_attr($label) . '" style="min-width:0;text-align:center;justify-self:stretch;overflow:hidden;text-overflow:ellipsis;">' . esc_html($label) . '</span>';
			}
			echo ' <button type="button" class="button-link cmx-belege-remove" style="color:#b32d2e;justify-self:end;padding:0;line-height:1;"><span class="dashicons dashicons-trash" style="color:#d63638;"></span></button>';
			echo '</li>';
		}
	}
	echo '</ul>';
	// Do not mutate stored meta here; only filter display.

		echo '<script>
		jQuery(function($){
			var $drop = $("#cmx-belege-drop");
			var $file = $("#cmx-belege-file");
			var $status = $("#cmx-belege-upload-status");
			var $existingToggle = $("#cmx-belege-existing-toggle");
			var $existingSearchWrap = $("#cmx-belege-existing-search-wrap");
			var $existingSearch = $("#cmx-belege-existing-search");
			var $existingResults = $("#cmx-belege-existing-results");
			var defaultDropHtml = $drop.html();
			var activeUploads = 0;
			var existingSearchTimer = null;
			var postUploadRedirectUrl = "";
			var shouldRedirectAfterUpload = /\/post-new\.php$/i.test(window.location.pathname || "");
			var postId = ' . (int) $post->ID . ';
			var nonce = ' . wp_json_encode($nonce) . ';
			var ajaxurl = ' . wp_json_encode(admin_url('admin-ajax.php')) . ';

			function setUploadStatus(message, type){
				type = type || "info";
				if (!$status.length) return;
				var colors = {
					info: ["#72aee6", "#f0f6fc"],
					success: ["#00a32a", "#edfaef"],
					error: ["#d63638", "#fcf0f1"]
				};
				var color = colors[type] || colors.info;
				$status.css({ display: "block", borderLeftColor: color[0], background: color[1] }).text(message);
			}

			function chooseOriginalbelegRemoveAction(label, done){
				var $overlay = $("<div>").css({
					position: "fixed",
					inset: 0,
					zIndex: 100000,
					background: "rgba(15,23,42,.42)",
					display: "flex",
					alignItems: "center",
					justifyContent: "center",
					padding: "20px"
				});
				var $dialog = $("<div>").attr({
					role: "dialog",
					"aria-modal": "true",
					"aria-label": "Originalbeleg entfernen"
				}).css({
					width: "min(460px, 100%)",
					background: "#fff",
					border: "1px solid #c3c4c7",
					borderRadius: "8px",
					boxShadow: "0 20px 60px rgba(0,0,0,.24)",
					padding: "18px"
				});
				$dialog.append($("<h3>").css({ margin: "0 0 10px", fontSize: "16px", lineHeight: 1.3 }).text("Originalbeleg entfernen"));
				$dialog.append($("<p>").css({ margin: "0 0 8px", color: "#1d2327" }).text(label || "Diese Datei"));
				$dialog.append($("<p>").css({ margin: "0 0 16px", color: "#50575e" }).text("Was soll mit dem Originalbeleg passieren?"));
				var $actions = $("<div>").css({ display: "flex", gap: "8px", justifyContent: "flex-end", flexWrap: "wrap" });
				var $unlink = $("<button type=\"button\" class=\"button\">").text("Nur Verlinkung aufheben");
				var $delete = $("<button type=\"button\" class=\"button button-primary\">").text("Datei löschen");
				var $cancel = $("<button type=\"button\" class=\"button\">").text("Abbrechen");
				$actions.append($cancel, $unlink, $delete);
				$dialog.append($actions);
				$overlay.append($dialog).appendTo(document.body);
				function close(action){
					$(document).off("keydown.cmxBelegeRemoveChoice");
					$overlay.remove();
					done(action || "");
				}
				$cancel.on("click", function(){ close(""); });
				$unlink.on("click", function(){ close("unlink"); });
				$delete.on("click", function(){ close("delete"); });
				$overlay.on("click", function(event){
					if (event.target === $overlay[0]) close("");
				});
				$(document).on("keydown.cmxBelegeRemoveChoice", function(event){
					if (event.key === "Escape") {
						close("");
					}
				});
				$unlink.trigger("focus");
			}

			function addOriginalbelegItem(item){
				if (!item) return;
				var attId = item.id || item.att_id || "";
				var path = item.path || "";
				var label = item.label || path || "Originalbeleg";
				var url = item.url || "#";
				var exists = false;
				$("#cmx-belege-existing li").each(function(){
					var $row = $(this);
					if (attId && String($row.data("att-id") || "") === String(attId)) exists = true;
					if (path && String($row.data("path") || "") === String(path)) exists = true;
				});
				if (exists) return;
				var $li = $("<li>").css({
					display: "grid",
					gridTemplateColumns: "1fr 28px",
					alignItems: "center",
					gap: "6px",
					width: "100%",
					whiteSpace: "nowrap"
				});
				if (attId) $li.attr("data-att-id", attId);
				if (path) $li.attr("data-path", path);
				var $link = $("<a>").attr({
					href: url,
					target: "_blank",
					rel: "noopener noreferrer",
					title: label
				}).css({
					minWidth: 0,
					textAlign: "center",
					justifySelf: "stretch",
					overflow: "hidden",
					textOverflow: "ellipsis"
				}).text(label);
				var $btn = $("<button type=\"button\">").addClass("button-link cmx-belege-remove").css({
					color: "#b32d2e",
					justifySelf: "end",
					padding: 0,
					lineHeight: 1
				}).html("<span class=\"dashicons dashicons-trash\" style=\"color:#d63638;\"></span>");
				$li.append($link).append($btn);
				$("#cmx-belege-existing").append($li);
			}

			function renderExistingResults(items){
				$existingResults.empty();
				if (!items || !items.length) {
					$existingResults.show().append($("<div>").css({ padding: "8px", color: "#646970" }).text("Keine passenden Originalbelege gefunden."));
					return;
				}
				items.forEach(function(item){
					var detail = item.linked_count && item.linked_count > 0 ? " · bereits " + item.linked_count + "x verlinkt" : "";
					var $button = $("<button type=\"button\">").addClass("button-link cmx-belege-existing-result").attr({
						"data-att-id": item.att_id || "",
						"data-path": item.path || ""
					}).css({
						display: "block",
						width: "100%",
						padding: "8px",
						borderBottom: "1px solid #dcdcde",
						textAlign: "left",
						color: "#1d2327"
					});
					$button.append($("<strong>").css({ display: "block", color: "#b32d2e" }).text(item.label || item.path || "Originalbeleg"));
					$button.append($("<small>").css({ color: "#646970" }).text((item.path || "") + detail));
					$existingResults.append($button);
				});
				$existingResults.show();
			}

			function searchExistingOriginalbelege(){
				var query = $.trim($existingSearch.val() || "");
				$.post(ajaxurl, { action: "cmx_belege_search_existing_uploads", post_id: postId, q: query, nonce: nonce }, function(resp){
					if (resp && resp.success && resp.data && resp.data.items) {
						renderExistingResults(resp.data.items);
					}
				});
			}

			function setUploadBusy(file, busy){
				if (busy) {
					activeUploads++;
					var isPdf = file && /\.pdf$/i.test(file.name || "");
					setUploadStatus(isPdf ? "PDF wird hochgeladen und analysiert. Das kann bis zu 30 Sekunden dauern ..." : "Datei wird hochgeladen ...", "info");
					$drop.css({ opacity: 0.65, pointerEvents: "none" }).html("<strong>Bitte warten ...</strong><br><small>Upload und Analyse laufen</small>");
					$file.prop("disabled", true);
					return;
				}
				activeUploads = Math.max(0, activeUploads - 1);
				if (activeUploads > 0) {
					return;
				}
				$drop.css({ opacity: 1, pointerEvents: "auto" }).html(defaultDropHtml);
				$file.prop("disabled", false);
				if (shouldRedirectAfterUpload && postUploadRedirectUrl) {
					window.setTimeout(function(){
						window.location.replace(postUploadRedirectUrl);
					}, 250);
				}
			}

		function uploadFile(file){
			var fd = new FormData();
			fd.append("action", "cmx_belege_upload_file");
			fd.append("post_id", postId);
			fd.append("nonce", nonce);
			fd.append("file", file);

			$.ajax({
				url: ajaxurl,
				type: "POST",
				data: fd,
				processData: false,
				contentType: false,
				beforeSend: function(){
					setUploadBusy(file, true);
				},
				success: function(resp){
						if (resp && resp.success && resp.data) {
							if (resp.data.title) {
								$("#title").val(resp.data.title);
								$("#title-prompt-text").addClass("screen-reader-text");
							}
							if (resp.data.edit_url) {
								postUploadRedirectUrl = resp.data.edit_url;
							}
							if (resp.data.notice) {
								setUploadStatus(resp.data.notice, "success");
								var $notice = $("#cmx-belege-upload-notice");
							if (!$notice.length) {
								$notice = $("<div id=\"cmx-belege-upload-notice\" class=\"notice notice-success is-dismissible\" style=\"margin:8px 0;\"><p></p></div>");
								$("#poststuff").before($notice);
							}
							$notice.find("p").text(resp.data.notice);
						}
						if (resp.data.betrag) {
							var $sum = $("#cmx-beleg-summe-input");
							if ($sum.length) {
								$sum.val(resp.data.betrag).attr("data-manual", "1").data("manual", "1").trigger("input").trigger("change");
							}
						}
						if (resp.data.datum) {
							$("#cmx_beleg_rng_datum").val(resp.data.datum).trigger("change");
						}
						if (resp.data.faellig_am) {
							$("#cmx_beleg_faelligkeitsdatum").val(resp.data.faellig_am).trigger("change");
						}
						if (resp.data.waehrung) {
							$("#cmx_beleg_waehrung_select").val(resp.data.waehrung).trigger("change");
						}
						if (resp.data.kontakt_label) {
							var contactInput = document.getElementById("cmx_kontakt_search");
							if (contactInput) {
								contactInput.value = resp.data.kontakt_label;
								contactInput.dispatchEvent(new Event("input", { bubbles: true }));
							}
							$("#cmx_kontakt_id").val(resp.data.kontakt_id || "");
							$("#cmx_kontakt_selected").val(resp.data.kontakt_id ? "1" : "0");
						}
						if (resp.data.kontakt_addr) {
							$("#cmx_kontakt_addr").val(resp.data.kontakt_addr).trigger("change");
						}
						if (resp.data.positionen_notiz_text) {
							var noteText = resp.data.positionen_notiz_text;
							var $note = $("textarea[name^=\"cmx_intern_notizen_rows\"][name$=\"[text]\"]").filter(function(){
								return $.trim($(this).val() || "") === "";
							}).first();
							if ($note.length) {
								$note.val(noteText).trigger("change");
								if (window.tinymce && $note.attr("id")) {
									var editor = window.tinymce.get($note.attr("id"));
									if (editor) {
										editor.setContent(noteText);
										editor.save();
									}
								}
							}
						}
						var label = resp.data.label || file.name;
						var url = resp.data.url || "";
						var attId = resp.data.id || "";
						var path = resp.data.path || "";
						var $li = $("<li>").attr("data-att-id", attId).css({
							display: "grid",
							gridTemplateColumns: "1fr 14px",
							alignItems: "center",
							gap: "4px",
							width: "100%",
							whiteSpace: "nowrap"
						});
						if (path) {
							$li.attr("data-path", path);
						}
						var $link = $("<a>").attr({
							href: url || "#",
							target: "_blank",
							rel: "noopener noreferrer",
							title: label
						}).css({
							minWidth: 0,
							textAlign: "center",
							justifySelf: "stretch",
							overflow: "hidden",
							textOverflow: "ellipsis"
						}).text(label);
						var $btn = $("<button>").addClass("button-link cmx-belege-remove").css({
							color: "#b32d2e",
							justifySelf: "end",
							padding: 0,
							lineHeight: 1
						}).text("X");
						$li.append($link).append($btn);
						var $existing = $("#cmx-belege-existing");
							if ($existing.length) {
								$existing.append($li);
							} else {
								$existing = $("<ul id=\"cmx-belege-existing\" style=\"margin:6px 0 0 0;padding:0;list-style:none;max-height:160px;overflow:auto;width:100%;\"></ul>");
								$existing.append($li);
								$("#cmx-belege-upload-box").append($existing);
							}
						} else {
							var $notice = $("#cmx-belege-upload-notice");
						if (!$notice.length) {
							$notice = $("<div id=\"cmx-belege-upload-notice\" class=\"notice notice-error is-dismissible\" style=\"margin:8px 0;\"><p></p></div>");
							$("#poststuff").before($notice);
						}
						var msg = (resp && resp.data && resp.data.message) ? resp.data.message : "Fehler beim Upload";
						setUploadStatus(msg + ": " + file.name, "error");
						$notice.find("p").text(msg + ": " + file.name);
					}
				},
				error: function(xhr){
					var $notice = $("#cmx-belege-upload-notice");
					if (!$notice.length) {
						$notice = $("<div id=\"cmx-belege-upload-notice\" class=\"notice notice-error is-dismissible\" style=\"margin:8px 0;\"><p></p></div>");
						$("#poststuff").before($notice);
					}
					var extra = "";
					if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						extra = xhr.responseJSON.data.message;
					} else if (xhr && xhr.responseText) {
						extra = xhr.responseText;
					}
					setUploadStatus((extra ? extra + " - " : "") + "Fehler beim Upload: " + file.name, "error");
					$notice.find("p").text((extra ? extra + " - " : "") + "Fehler beim Upload: " + file.name);
				},
				complete: function(){
					setUploadBusy(file, false);
				}
			});
		}

		$drop.on("click", function(){ $file.trigger("click"); });
		$drop.on("dragover", function(e){ e.preventDefault(); e.stopPropagation(); $drop.css("background","#f0f6fc"); });
		$drop.on("dragleave", function(e){ e.preventDefault(); e.stopPropagation(); $drop.css("background","#fafafa"); });
		$drop.on("drop", function(e){
			e.preventDefault(); e.stopPropagation(); $drop.css("background","#fafafa");
			var files = e.originalEvent.dataTransfer.files;
			if (!files || !files.length) return;
			for (var i=0; i<files.length; i++) uploadFile(files[i]);
		});

		$file.on("change", function(){
			var files = this.files || [];
			for (var i=0; i<files.length; i++) uploadFile(files[i]);
			$(this).val("");
		});

		$existingToggle.on("click", function(){
			$existingSearchWrap.toggle();
			if ($existingSearchWrap.is(":visible")) {
				$existingSearch.trigger("focus");
				searchExistingOriginalbelege();
			}
		});

		$existingSearch.on("input", function(){
			window.clearTimeout(existingSearchTimer);
			existingSearchTimer = window.setTimeout(searchExistingOriginalbelege, 250);
		});

		$existingResults.on("click", ".cmx-belege-existing-result", function(){
			var $btn = $(this);
			$.post(ajaxurl, {
				action: "cmx_belege_link_existing_upload",
				post_id: postId,
				att_id: $btn.data("att-id") || "",
				path: $btn.data("path") || "",
				nonce: nonce
			}, function(resp){
				if (resp && resp.success && resp.data && resp.data.item) {
					addOriginalbelegItem(resp.data.item);
					$existingResults.hide().empty();
					$existingSearch.val("");
				}
			});
		});

		$(document).off("click.cmxBelegeRemove", ".cmx-belege-remove").on("click.cmxBelegeRemove", ".cmx-belege-remove", function(event){
			event.preventDefault();
			event.stopPropagation();
			var $li = $(this).closest("li");
			var attId = $li.data("att-id");
			var path = $li.data("path");
			if (!attId && !path) return;
			var label = $.trim($li.find("a,span").first().attr("title") || $li.find("a,span").first().text() || "");
			chooseOriginalbelegRemoveAction(label, function(removeMode){
				if (!removeMode) return;
				$.post(ajaxurl, { action:"cmx_belege_remove_file", post_id: postId, att_id: attId, path: path, remove_mode: removeMode, nonce: nonce }, function(resp){
					if (resp && resp.success) {
						$li.remove();
					} else if (resp && resp.data && resp.data.needs_confirm && window.confirm(resp.data.message || "Dieser Originalbeleg ist noch anderweitig verlinkt. Trotzdem physisch löschen?")) {
						$.post(ajaxurl, { action:"cmx_belege_remove_file", post_id: postId, att_id: attId, path: path, remove_mode: removeMode, force_delete: "1", nonce: nonce }, function(forceResp){
							if (forceResp && forceResp.success) {
								$li.remove();
							}
						});
					}
				});
			});
		});
	});
	</script>';
}

add_action('wp_ajax_cmx_belege_upload_file', __NAMESPACE__ . '\\cmx_belege_upload_file');
function cmx_belege_upload_file(): void {
	if (!current_user_can('upload_files')) {
		wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
	}
	$nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';
	if (!wp_verify_nonce($nonce, 'cmx_belege_upload')) {
		wp_send_json_error(['message' => 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden.'], 403);
	}
	$post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
	if ($post_id <= 0 || get_post_type($post_id) !== 'belege') {
		wp_send_json_error(['message' => 'Ungültiger Beleg.'], 400);
	}
	if (empty($_FILES['file']) || !isset($_FILES['file']['tmp_name'])) {
		wp_send_json_error(['message' => 'Keine Datei empfangen.'], 400);
	}

	$allowed = ['pdf','png','jpg','jpeg'];
	$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
	if (!in_array($ext, $allowed, true)) {
		wp_send_json_error(['message' => 'Nur PDF, PNG oder JPG erlaubt.'], 400);
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$post = get_post($post_id);
	if (!$post) {
		wp_send_json_error(['message' => 'bad_post'], 400);
	}
	$year = \function_exists(__NAMESPACE__ . '\\cmx_get_beleg_upload_year')
		? cmx_get_beleg_upload_year($post_id)
		: (int) wp_date('Y');
	$post_title = $post->post_title;
	if ($post_title === '' || $post->post_status === 'auto-draft') {
		$post_title = wp_date('ymd-His');
	}
	wp_update_post([
		'ID' => $post_id,
		'post_title' => $post_title,
		'post_status' => 'publish',
		'post_date' => current_time('mysql'),
		'post_date_gmt' => get_gmt_from_date(current_time('mysql')),
		'edit_date' => true,
	]);
	$post_slug = sanitize_title($post_title);
	if ($post_slug === '') {
		$post_slug = wp_date('ymd-His');
	}
	update_post_meta($post_id, '_cmx_beleg_upload_prefix', $post_slug);

	[$dir_base, $base_url] = cmx_belege_upload_dir((int) $year);
	$upload_filter = function($dirs) use ($dir_base, $base_url) {
		$dirs['path']   = $dir_base;
		$dirs['basedir']= $dir_base;
		$dirs['url']    = $base_url;
		$dirs['baseurl']= $base_url;
		$dirs['subdir'] = '';
		return $dirs;
	};

	$no_sizes_filter = function($sizes) { return []; };
	$no_meta_sizes_filter = function($metadata, $attachment_id) {
		if (isset($metadata['sizes'])) $metadata['sizes'] = [];
		return $metadata;
	};
	$no_big_image = function() { return false; };

	add_filter('upload_dir', $upload_filter);
	add_filter('intermediate_image_sizes', $no_sizes_filter);
	add_filter('intermediate_image_sizes_advanced', $no_sizes_filter);
	add_filter('big_image_size_threshold', $no_big_image, 10, 0);
	$existing = (array) get_post_meta($post_id, CMX_BELEG_UPLOADS_META, true);
	$existing = array_values(array_filter($existing, function($v){ return $v !== '' && $v !== null; }));
	$next_suffix = cmx_belege_next_suffix($dir_base, $post_slug);
	$unique_cb = function(string $dir, string $name, string $ext) use ($post_slug, &$next_suffix): string {
		do {
			$suffix = '_' . str_pad((string) $next_suffix, 3, '0', STR_PAD_LEFT);
			$filename = $post_slug . '_upload' . $suffix . $ext;
			$next_suffix++;
		} while (file_exists($dir . '/' . $filename));
		return $filename;
	};

	$uploaded = wp_handle_upload($_FILES['file'], [
		'test_form' => false,
		'unique_filename_callback' => $unique_cb,
		'mimes' => [
			'pdf'  => 'application/pdf',
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
		],
	]);

	if (!isset($uploaded['file'])) {
		$error_message = '';
		if (isset($uploaded['error'])) {
			$error_message = \trim(\wp_strip_all_tags((string) $uploaded['error']));
		}
		if ($error_message === '') {
			$error_message = 'Upload fehlgeschlagen.';
		}
		remove_filter('big_image_size_threshold', $no_big_image, 10);
		remove_filter('intermediate_image_sizes_advanced', $no_sizes_filter);
		remove_filter('intermediate_image_sizes', $no_sizes_filter);
		remove_filter('upload_dir', $upload_filter);
		wp_send_json_error(['message' => $error_message], 500);
	}

	add_filter('wp_generate_attachment_metadata', $no_meta_sizes_filter, 10, 2);

	$rel = ltrim(str_replace(trailingslashit(WP_CONTENT_DIR . '/uploads'), '', $uploaded['file']), '/');
	remove_filter('wp_generate_attachment_metadata', $no_meta_sizes_filter, 10);
	remove_filter('big_image_size_threshold', $no_big_image, 10);
	remove_filter('intermediate_image_sizes_advanced', $no_sizes_filter);
	remove_filter('intermediate_image_sizes', $no_sizes_filter);
	remove_filter('upload_dir', $upload_filter);

	$existing[] = $rel;
	$existing = array_values(array_unique($existing));
	update_post_meta($post_id, CMX_BELEG_UPLOADS_META, $existing);
	if ($ext === 'pdf') {
		update_post_meta($post_id, '_cmx_beleg_import_flag', '1');
		update_post_meta($post_id, '_cmx_beleg_imported_at', current_time('mysql'));
	}

	$extraction = [];
	$notice = 'Beleg wurde gespeichert.';
	if ($ext === 'pdf' && class_exists(__NAMESPACE__ . '\\MIS_BUERO_BELEG_UPLOAD') && method_exists(__NAMESPACE__ . '\\MIS_BUERO_BELEG_UPLOAD', 'apply_pdf_extraction_to_beleg')) {
		$extraction = MIS_BUERO_BELEG_UPLOAD::apply_pdf_extraction_to_beleg($post_id, (string) $uploaded['file']);
		if (!empty($extraction['success'])) {
			$applied = is_array($extraction['applied'] ?? null) ? (array) $extraction['applied'] : [];
			$parts = [];
			if (!empty($applied['betrag'])) {
				$parts[] = 'Betrag';
			}
			if (!empty($applied['kontakt_label'])) {
				$parts[] = 'Kontaktvorschlag';
			}
			if (!empty($applied['positionen_notiz'])) {
				$parts[] = 'Positionen als Notiz';
			}
			$notice = $parts
				? 'Beleg wurde gespeichert. PDF übernommen: ' . implode(', ', $parts) . '.'
				: 'Beleg wurde gespeichert. PDF wurde geprüft, aber es wurden keine Werte erkannt.';
		} else {
			$message = trim((string) ($extraction['message'] ?? 'PDF konnte nicht geprüft werden.'));
			$notice = 'Beleg wurde gespeichert. ' . $message;
		}
	}

	$label = isset($uploaded['file']) && $uploaded['file'] !== ''
		? basename($uploaded['file'])
		: $post_slug . '_upload.' . $ext;
	$applied = is_array($extraction['applied'] ?? null) ? (array) $extraction['applied'] : [];
	if ($ext === 'pdf') {
		$extraction_data = is_array($extraction['data'] ?? null) ? (array) $extraction['data'] : [];
		$services_data = is_array($extraction_data['_services'] ?? null) ? (array) $extraction_data['_services'] : [];
		update_post_meta($post_id, '_cmx_beleg_pdf_extraction_last', [
			'time' => current_time('mysql'),
			'file' => $rel,
			'success' => !empty($extraction['success']) ? 1 : 0,
			'message' => (string) ($extraction['message'] ?? ''),
			'applied' => array_values(array_keys($applied)),
			'source' => (string) (($extraction_data['_source'] ?? '') ?: ''),
			'ocr_error' => (string) (($extraction_data['_ocr_error'] ?? $extraction_data['_error'] ?? '') ?: ''),
			'services_status' => (string) (($services_data['status'] ?? '') ?: ''),
			'services_endpoint' => (string) (($services_data['endpoint'] ?? '') ?: ''),
			'services_body_excerpt' => (string) (($services_data['body_excerpt'] ?? '') ?: ''),
		]);
	}
	wp_send_json_success([
		'id'    => 0,
		'url'   => content_url('/uploads/' . $rel),
		'label' => $label,
		'path'  => $rel,
		'title' => $post_title,
		'edit_url' => (string) (get_edit_post_link($post_id, 'raw') ?: admin_url('post.php?post=' . (int) $post_id . '&action=edit')),
		'notice' => $notice,
		'betrag' => (string) ($applied['betrag'] ?? ''),
		'datum' => (string) ($applied['datum'] ?? ''),
		'faellig_am' => (string) ($applied['faellig_am'] ?? ''),
		'waehrung' => (string) ($applied['waehrung'] ?? ''),
		'kontakt_id' => (string) ($applied['kontakt_id'] ?? ''),
		'kontakt_label' => (string) ($applied['kontakt_label'] ?? ''),
		'kontakt_addr' => (string) ($applied['kontakt_addr'] ?? ''),
		'positionen_notiz' => (string) ($applied['positionen_notiz'] ?? ''),
		'positionen_notiz_text' => (string) ($applied['positionen_notiz_text'] ?? ''),
	]);
}

add_action('wp_ajax_cmx_belege_search_existing_uploads', __NAMESPACE__ . '\\cmx_belege_search_existing_uploads');
function cmx_belege_search_existing_uploads(): void {
	if (!current_user_can('upload_files')) {
		wp_send_json_error(['message' => 'forbidden'], 403);
	}
	$nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';
	if (!wp_verify_nonce($nonce, 'cmx_belege_upload')) {
		wp_send_json_error(['message' => 'bad_nonce'], 403);
	}
	$post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
	if ($post_id <= 0 || get_post_type($post_id) !== 'belege') {
		wp_send_json_error(['message' => 'bad_params'], 400);
	}
	$query = isset($_POST['q']) ? sanitize_text_field((string) wp_unslash($_POST['q'])) : '';
	$items = cmx_belege_upload_existing_candidates($post_id, $query, 30);
	wp_send_json_success(['items' => $items]);
}

add_action('wp_ajax_cmx_belege_link_existing_upload', __NAMESPACE__ . '\\cmx_belege_link_existing_upload');
function cmx_belege_link_existing_upload(): void {
	if (!current_user_can('upload_files')) {
		wp_send_json_error(['message' => 'forbidden'], 403);
	}
	$nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';
	if (!wp_verify_nonce($nonce, 'cmx_belege_upload')) {
		wp_send_json_error(['message' => 'bad_nonce'], 403);
	}
	$post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
	$att_id = isset($_POST['att_id']) ? (int) $_POST['att_id'] : 0;
	$path = isset($_POST['path']) ? cmx_belege_upload_normalize_rel_path((string) wp_unslash($_POST['path'])) : '';
	if ($post_id <= 0 || get_post_type($post_id) !== 'belege') {
		wp_send_json_error(['message' => 'bad_params'], 400);
	}
	$candidate = cmx_belege_upload_candidate($att_id, $path);
	if (!$candidate) {
		wp_send_json_error(['message' => 'not_found'], 404);
	}
	$att_id = (int) ($candidate['att_id'] ?? 0);
	$path = (string) ($candidate['path'] ?? '');
	$entry = $att_id > 0 ? $att_id : $path;
	$existing = (array) get_post_meta($post_id, CMX_BELEG_UPLOADS_META, true);
	$found = false;
	foreach ($existing as $existing_entry) {
		if ((string) $existing_entry === (string) $entry) {
			$found = true;
			break;
		}
		if ($path !== '' && cmx_belege_upload_normalize_rel_path((string) $existing_entry) === $path) {
			$found = true;
			break;
		}
	}
	if (!$found) {
		$existing[] = $entry;
		update_post_meta($post_id, CMX_BELEG_UPLOADS_META, array_values(array_filter($existing, function($v){ return $v !== '' && $v !== null; })));
	}
	cmx_belege_upload_forget_unlinked($post_id, $att_id, $path);
	$candidate['linked_count'] = count(cmx_belege_upload_post_ids_by_reference($att_id, $path));
	wp_send_json_success(['item' => $candidate]);
}

add_action('wp_ajax_cmx_belege_remove_file', __NAMESPACE__ . '\\cmx_belege_remove_file');
function cmx_belege_remove_file(): void {
	if (!current_user_can('delete_posts')) {
		wp_send_json_error(['message' => 'forbidden'], 403);
	}
	$nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';
	if (!wp_verify_nonce($nonce, 'cmx_belege_upload')) {
		wp_send_json_error(['message' => 'bad_nonce'], 403);
	}
	$post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
	$att_id = isset($_POST['att_id']) ? (int) $_POST['att_id'] : 0;
	$path = isset($_POST['path']) ? cmx_belege_upload_normalize_rel_path((string) wp_unslash($_POST['path'])) : '';
	$remove_mode = isset($_POST['remove_mode']) ? \sanitize_key((string) $_POST['remove_mode']) : 'delete';
	$force_delete = !empty($_POST['force_delete']);
	if (!in_array($remove_mode, ['delete', 'unlink'], true)) {
		wp_send_json_error(['message' => 'bad_mode'], 400);
	}
	if ($post_id <= 0 || get_post_type($post_id) !== 'belege') {
		wp_send_json_error(['message' => 'bad_params'], 400);
	}

	$existing = (array) get_post_meta($post_id, CMX_BELEG_UPLOADS_META, true);
	$existing = array_values(array_filter($existing, function($v){ return $v !== '' && $v !== null; }));
	$att_path = '';
	if ($att_id > 0) {
		$att_path = (string) get_post_meta($att_id, '_wp_attached_file', true);
	}
	$effective_path = $path !== '' ? $path : $att_path;
	if ($remove_mode === 'delete') {
		$refs = cmx_belege_upload_post_ids_by_reference($att_id, $effective_path);
		if (count($refs) > 1 && !$force_delete) {
			wp_send_json_error([
				'needs_confirm' => true,
				'message' => 'Dieser Originalbeleg ist noch bei ' . count($refs) . ' Belegen verlinkt. Wirklich die Datei löschen und alle Verlinkungen entfernen?',
			], 409);
		}
	}
	if ($att_id) {
		$existing = array_values(array_diff($existing, [$att_id, (string) $att_id]));
	}
	if ($path !== '') {
		$existing = array_values(array_diff($existing, [$path]));
	}
	update_post_meta($post_id, CMX_BELEG_UPLOADS_META, $existing);
	if ($remove_mode === 'unlink') {
		cmx_belege_upload_mark_unlinked($post_id, $att_id, $effective_path);
	} else {
		foreach (cmx_belege_upload_post_ids_by_reference($att_id, $effective_path) as $ref_post_id) {
			if ((int) $ref_post_id !== $post_id) {
				cmx_belege_upload_remove_reference_from_post((int) $ref_post_id, $att_id, $effective_path);
			}
		}
		cmx_belege_upload_forget_unlinked($post_id, $att_id, $effective_path);
		if ($path !== '') {
			$abs = WP_CONTENT_DIR . '/uploads/' . ltrim($path, '/');
			$norm_abs = wp_normalize_path($abs);
			$is_carent_contract_link = cmx_belege_is_carent_contract_abs_path($norm_abs);
			if (!$is_carent_contract_link && is_file($abs)) {
				@unlink($abs);
			}
		}
		if ($att_id) {
			wp_delete_attachment($att_id, true);
		}
	}

	wp_send_json_success(['ok' => true]);
}
