<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

if (!defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_URL')) {
	define(__NAMESPACE__ . '\\CMX_KONTAKTE_META_URL', '_cmx_kontakte_url');
}

if (!defined(__NAMESPACE__ . '\\CMX_LOCAL_IMG_SUBDIR')) {
	define(__NAMESPACE__ . '\\CMX_LOCAL_IMG_SUBDIR', '/misbuero/archiv/bilder');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_local_base_path')) {
	function cmx_local_base_path(): string {
		$u = \wp_get_upload_dir();
		return \wp_normalize_path($u['basedir'] . CMX_LOCAL_IMG_SUBDIR . '/kontakte');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_local_base_url')) {
	function cmx_local_base_url(): string {
		$u = \wp_get_upload_dir();
		return \rtrim($u['baseurl'], '/') . CMX_LOCAL_IMG_SUBDIR . '/kontakte';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kl_meta_base')) {
	function cmx_kl_meta_base(): string {
		return '_cmx_local_image_kontakte';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kl_gallery_meta_key')) {
	function cmx_kl_gallery_meta_key(): string {
		return '_cmx_local_image_kontakte_gallery';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kl_manual_flag_meta_key')) {
	function cmx_kl_manual_flag_meta_key(): string {
		return '_cmx_local_image_kontakte_manual';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kl_label')) {
	function cmx_kl_label(int $post_id): string {
		$is_private = (bool) \get_post_meta($post_id, '_cmx_kontakte_privat', true);
		return $is_private ? 'Foto' : 'Logo';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kl_gallery_item_filename')) {
	function cmx_kl_gallery_item_filename(array $item): string {
		$path = (string) ($item['path'] ?? '');
		$url  = (string) ($item['url'] ?? '');
		$candidate = $path !== '' ? \basename($path) : '';
		if ($candidate === '' && $url !== '') {
			$url_path = (string) \parse_url($url, PHP_URL_PATH);
			$candidate = $url_path !== '' ? \basename($url_path) : '';
		}
		return \rawurldecode($candidate);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kl_gallery_normalize')) {
	function cmx_kl_gallery_normalize(array $items): array {
		$out = [];
		foreach ($items as $item) {
			if (!\is_array($item)) {
				continue;
			}
			$id   = \sanitize_key((string) ($item['id'] ?? ''));
			$path = \trim((string) ($item['path'] ?? ''));
			$url  = \trim((string) ($item['url'] ?? ''));

			if ($id === '') {
				$id = 'img_' . \wp_generate_password(12, false, false);
			}
			if ($path === '' && $url === '') {
				continue;
			}

			$out[] = [
				'id'   => $id,
				'path' => $path,
				'url'  => $url,
			];
		}
		return $out;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kl_gallery_get')) {
	function cmx_kl_gallery_get(int $post_id, string $meta_base = ''): array {
		if ($meta_base === '') {
			$meta_base = cmx_kl_meta_base();
		}

		$items = \get_post_meta($post_id, cmx_kl_gallery_meta_key(), true);
		$items = \is_array($items) ? cmx_kl_gallery_normalize($items) : [];
		if ($items !== []) {
			return $items;
		}

		$legacy_path = (string) \get_post_meta($post_id, $meta_base . '_path', true);
		$legacy_url  = (string) \get_post_meta($post_id, $meta_base . '_url', true);
		if ($legacy_path === '' && $legacy_url === '') {
			return [];
		}

		return [[
			'id'   => 'img_' . \wp_generate_password(12, false, false),
			'path' => $legacy_path,
			'url'  => $legacy_url,
		]];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kl_gallery_update')) {
	function cmx_kl_gallery_update(int $post_id, string $meta_base, array $items): void {
		$items = cmx_kl_gallery_normalize($items);

		if ($items === []) {
			\delete_post_meta($post_id, cmx_kl_gallery_meta_key());
			\delete_post_meta($post_id, $meta_base . '_path');
			\delete_post_meta($post_id, $meta_base . '_url');
			\clean_post_cache($post_id);
			return;
		}

		\update_post_meta($post_id, cmx_kl_gallery_meta_key(), $items);
		\update_post_meta($post_id, $meta_base . '_path', (string) ($items[0]['path'] ?? ''));
		\update_post_meta($post_id, $meta_base . '_url', (string) ($items[0]['url'] ?? ''));
		\clean_post_cache($post_id);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kl_uploaded_files_from_request')) {
	function cmx_kl_uploaded_files_from_request(array $files): array {
		$out = [];
		$names     = (array) ($files['name'] ?? []);
		$tmp_names = (array) ($files['tmp_name'] ?? []);
		$errors    = (array) ($files['error'] ?? []);
		$sizes     = (array) ($files['size'] ?? []);
		$types     = (array) ($files['type'] ?? []);

		foreach ($names as $index => $name) {
			if ((string) $name === '') {
				continue;
			}
			$out[] = [
				'name'     => $name,
				'tmp_name' => (string) ($tmp_names[$index] ?? ''),
				'error'    => (int) ($errors[$index] ?? \UPLOAD_ERR_NO_FILE),
				'size'     => (int) ($sizes[$index] ?? 0),
				'type'     => (string) ($types[$index] ?? ''),
			];
		}

		return $out;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kl_upload_base_name')) {
	function cmx_kl_upload_base_name(string $original_name, string $fallback = 'bild'): string {
		$base = (string) \pathinfo($original_name, \PATHINFO_FILENAME);
		$base = \sanitize_file_name($base);
		$base = \preg_replace('~\.[a-z0-9]+$~i', '', $base);
		$base = \trim((string) $base, "-_. \t\n\r\0\x0B");
		if ($base === '') {
			$base = \sanitize_file_name($fallback);
		}
		return $base !== '' ? \strtolower($base) : 'bild';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kl_next_numbered_target')) {
	function cmx_kl_next_numbered_target(string $base_dir, string $base_name, string $ext): string {
		$counter = 1;
		do {
			$target = \wp_normalize_path($base_dir . '/' . $base_name . '-' . $counter . $ext);
			$counter++;
		} while (\file_exists($target));

		return $target;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kl_store_uploaded_files')) {
	function cmx_kl_store_uploaded_files(array $files, string $base_dir, string $base_url): array {
		$allowed_mimes = [
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'gif'      => 'image/gif',
			'webp'     => 'image/webp',
			'avif'     => 'image/avif',
		];
		$stored = [];

		foreach (cmx_kl_uploaded_files_from_request($files) as $file) {
			if (($file['error'] ?? \UPLOAD_ERR_NO_FILE) !== \UPLOAD_ERR_OK) {
				continue;
			}

			$check = \wp_check_filetype_and_ext($file['tmp_name'] ?? '', $file['name'] ?? '', $allowed_mimes);
			if (empty($check['ext']) || empty($check['type'])) {
				continue;
			}

			$ext = '.' . \strtolower((string) $check['ext']);
			$target_base = cmx_kl_upload_base_name((string) ($file['name'] ?? ''), 'kontakt');
			$target = cmx_kl_next_numbered_target($base_dir, $target_base, $ext);

			if (!\is_uploaded_file($file['tmp_name'])) {
				continue;
			}
			if (!@\move_uploaded_file($file['tmp_name'], $target)) {
				continue;
			}
			@chmod($target, 0644);

			$version = @\filemtime($target) ?: \time();
			$url = $base_url . '/' . \rawurlencode(\basename($target)) . '?v=' . $version;
			$stored[] = [
				'id'   => 'img_' . \wp_generate_password(12, false, false),
				'path' => $target,
				'url'  => $url,
			];
		}

		return $stored;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_maybe_migrate_contact_logo_to_archiv_path')) {
	function cmx_maybe_migrate_contact_logo_to_archiv_path(int $post_id): void {
		$meta_base = cmx_kl_meta_base();
		$current_path = (string) \get_post_meta($post_id, $meta_base . '_path', true);
		$current_url  = (string) \get_post_meta($post_id, $meta_base . '_url', true);
		$gallery      = cmx_kl_gallery_get($post_id, $meta_base);

		if ($current_path !== '') {
			$current_path = \wp_normalize_path($current_path);
		}

		if ($current_path !== '' && \is_file($current_path)) {
			$target_dir = \wp_normalize_path(cmx_local_base_path());
			if ($target_dir !== '' && !\is_dir($target_dir)) {
				\wp_mkdir_p($target_dir);
			}

			if ($target_dir !== '' && \strpos($current_path, $target_dir . '/') !== 0) {
				$filename = \basename($current_path);
				if ($filename !== '') {
					$target = \wp_normalize_path(\trailingslashit($target_dir) . $filename);
					if ($target !== $current_path) {
						if (\is_file($target)) {
							@unlink($target);
						}

						$moved = @\rename($current_path, $target);
						if (!$moved) {
							$moved = @\copy($current_path, $target);
							if ($moved) {
								@unlink($current_path);
							}
						}

						if ($moved && \is_file($target)) {
							@chmod($target, 0644);
							$current_path = $target;
							$ver = @\filemtime($target) ?: \time();
							$current_url = \trailingslashit(cmx_local_base_url()) . \rawurlencode($filename) . '?v=' . $ver;
							\update_post_meta($post_id, $meta_base . '_path', $current_path);
							\update_post_meta($post_id, $meta_base . '_url', $current_url);
						}
					}
				}
			}
		}

		if ($gallery === [] && ($current_path !== '' || $current_url !== '')) {
			cmx_kl_gallery_update($post_id, $meta_base, [[
				'id'   => 'img_' . \wp_generate_password(12, false, false),
				'path' => $current_path,
				'url'  => $current_url,
			]]);
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_has_local_logo')) {
	function cmx_has_local_logo(int $post_id): bool {
		$gallery = cmx_kl_gallery_get($post_id, cmx_kl_meta_base());
		foreach ($gallery as $item) {
			$path = (string) ($item['path'] ?? '');
			if ($path === '' || !\is_file($path)) {
				continue;
			}

			$info = @\getimagesize($path);
			if (\is_array($info) && !empty($info['mime'])) {
				return true;
			}
		}

		return false;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_download_to_local_and_save_meta')) {
	function cmx_download_to_local_and_save_meta(int $post_id, string $image_url, ?float $timeout_seconds = null) {
		$timeout_seconds = ($timeout_seconds === null) ? 8.0 : (float) $timeout_seconds;
		if ($timeout_seconds <= 0.0) {
			return new \WP_Error('cmx_logo_timeout', 'Zeitlimit erreicht');
		}

		$request_timeout = \max(1, (int) \ceil($timeout_seconds));
		$tmp = \download_url($image_url, $request_timeout);
		if (\is_wp_error($tmp)) {
			return $tmp;
		}

		$info = @\getimagesize($tmp);
		if (!\is_array($info) || empty($info['mime'])) {
			@unlink($tmp);
			return new \WP_Error('invalid_image', 'Ungueltiges Bild oder 404 erhalten');
		}

		$map = [
			'image/png'                  => 'png',
			'image/webp'                 => 'webp',
			'image/avif'                 => 'avif',
			'image/gif'                  => 'gif',
			'image/x-icon'               => 'ico',
			'image/vnd.microsoft.icon'   => 'ico',
			'image/jpeg'                 => 'jpg',
			'image/bmp'                  => 'bmp',
		];

		$mime = \strtolower((string) $info['mime']);
		$ext  = '.' . ($map[$mime] ?? 'png');

		$host = (string) \parse_url($image_url, PHP_URL_HOST);
		if ($host === '') {
			$host = 'logo';
		}
		$host = \strtolower($host);
		$host = (string) \preg_replace('~[^a-z0-9.-]+~', '', $host);
		if ($host === '') {
			$host = 'logo';
		}

		$file = $host . '-' . (int) $post_id . $ext;

		$base_dir = cmx_local_base_path();
		$base_url = cmx_local_base_url();
		if (!\is_dir($base_dir)) {
			\wp_mkdir_p($base_dir);
		}

		$target = \wp_normalize_path($base_dir . '/' . $file);
		if (\file_exists($target)) {
			@unlink($target);
		}

		$stored = false;
		if (\is_readable($tmp)) {
			$stored = @\copy($tmp, $target);
			if ($stored) {
				@unlink($tmp);
			}
		}
		if (!$stored) {
			$stored = @\rename($tmp, $target);
		}
		if (!$stored || !\is_file($target)) {
			@unlink($tmp);
			return new \WP_Error('move_failed', 'Speichern fehlgeschlagen');
		}
		@chmod($target, 0644);

		$ver = @\filemtime($target) ?: \time();
		$url = $base_url . '/' . \rawurlencode($file) . '?v=' . $ver;
		cmx_kl_gallery_update($post_id, cmx_kl_meta_base(), [[
			'id'   => 'img_' . \wp_generate_password(12, false, false),
			'path' => $target,
			'url'  => $url,
		]]);
		\clean_post_cache($post_id);

		return ['url' => $url, 'path' => $target];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_get_origin')) {
	function cmx_get_origin(string $url): string {
		$p = \wp_parse_url($url);
		if (empty($p['host'])) {
			return '';
		}
		$scheme = !empty($p['scheme']) ? $p['scheme'] : 'https';
		$origin = $scheme . '://' . $p['host'];
		if (!empty($p['port'])) {
			$origin .= ':' . $p['port'];
		}
		return $origin;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_fetch_logo_from_url')) {
	function cmx_fetch_logo_from_url(int $post_id, float $max_wait_seconds = 2.0) {
		if ($post_id <= 0) {
			return new \WP_Error('bad_post', 'Ungueltige Post-ID');
		}

		cmx_maybe_migrate_contact_logo_to_archiv_path($post_id);

		if (cmx_has_local_logo($post_id)) {
			return [
				'url'  => (string) \get_post_meta($post_id, cmx_kl_meta_base() . '_url', true),
				'path' => (string) \get_post_meta($post_id, cmx_kl_meta_base() . '_path', true),
			];
		}

		$site_url = \trim((string) \get_post_meta($post_id, CMX_KONTAKTE_META_URL, true));
		if ($site_url === '') {
			return new \WP_Error('no_url', 'Keine URL im Kontakt vorhanden');
		}

		if (!\preg_match('~^https?://~i', $site_url)) {
			$site_url = 'https://' . \ltrim($site_url, '/');
		}

		$origin = cmx_get_origin($site_url);
		if ($origin === '') {
			return new \WP_Error('bad_url', 'Ungueltige URL');
		}

		$max_wait_seconds = \max(0.2, (float) $max_wait_seconds);
		$deadline = \microtime(true) + $max_wait_seconds;
		$candidates = [
			'/favicon-32x32.png',
			'/favicon-16x16.png',
			'/apple-touch-icon.png',
			'/android-chrome-192x192.png',
			'/android-chrome-512x512.png',
			'/favicon.png',
			'/favicon.ico',
		];

		foreach ($candidates as $candidate) {
			$remaining = $deadline - \microtime(true);
			if ($remaining <= 0 || $remaining < 1.0) {
				return new \WP_Error('cmx_logo_timeout', 'Logo-Suche nach 2 Sekunden abgebrochen');
			}

			$img_url = \rtrim($origin, '/') . $candidate;
			$dl = cmx_download_to_local_and_save_meta($post_id, $img_url, $remaining);
			if (!\is_wp_error($dl)) {
				\update_post_meta($post_id, '_cmx_logo_src', \esc_url_raw($img_url));
				return $dl;
			}
		}

		return new \WP_Error('cmx_import_failed', 'Kein brauchbares Icon gefunden');
	}
}

\add_action('post_edit_form_tag', function () {
	echo ' enctype="multipart/form-data"';
});

\add_action('add_meta_boxes_kontakte', function (\WP_Post $post): void {
	\remove_meta_box('cmx_local_image_box_kontakte', 'kontakte', 'side');
	\add_meta_box(
		'cmx_kl_box_kontakte',
		cmx_kl_label((int) $post->ID),
		__NAMESPACE__ . '\\cmx_kl_render_box_kontakte',
		'kontakte',
		'side',
		'low'
	);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_kl_render_box_kontakte')) {
	function cmx_kl_render_box_kontakte(\WP_Post $post): void {
		\wp_nonce_field('cmx_kl_kontakte_nonce', 'cmx_kl_kontakte_nonce');

		$label     = cmx_kl_label((int) $post->ID);
		$meta_base = cmx_kl_meta_base();
		$gallery   = cmx_kl_gallery_get((int) $post->ID, $meta_base);
		$primary   = $gallery[0] ?? null;
		$url       = \is_array($primary) ? (string) ($primary['url'] ?? '') : '';
		$box_id    = 'cmx-kl-box-' . (int) $post->ID;
		$has_image = $url !== '';
		$total     = \count($gallery);
		$status    = $total > 0 ? ($total . ' Bild' . ($total === 1 ? '' : 'er') . ' gespeichert.') : ('Kein ' . $label . ' vorhanden.');

		echo '<div id="' . \esc_attr($box_id) . '" class="cmx-kl-box">';
		echo '<div class="cmx-kl-preview-frame" style="position:relative;margin-bottom:10px;border:1px dashed #c3c4c7;background:#fff;padding:10px;height:220px;display:flex;align-items:center;justify-content:center;cursor:pointer;overflow:hidden;transition:border-color .15s ease, background-color .15s ease;">';
		echo '<img class="cmx-kl-preview-image" src="' . ($has_image ? \esc_url($url) : '') . '" alt="" style="' . ($has_image ? 'display:block;' : 'display:none;') . 'max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain;">';
		echo '<div class="cmx-kl-preview-empty" style="' . ($has_image ? 'display:none;' : 'display:block;') . 'color:#646970;font-style:italic;text-align:center;">Kein ' . \esc_html($label) . ' vorhanden.<br>Dateien hier ablegen oder klicken.</div>';
		echo '</div>';
		// echo '<p class="description cmx-kl-status" style="margin:0 0 10px 0;">' . \esc_html($status) . '</p>';
		echo '<input type="hidden" name="cmx_kl_remove_ids" value="" class="cmx-kl-remove-ids">';
		echo '<input type="hidden" name="cmx_kl_order" value="" class="cmx-kl-order">';
		echo '<input type="hidden" name="cmx_kl_change_state" value="0" class="cmx-kl-change-state">';
		echo '<input type="file" name="cmx_kl_files[]" accept="image/*" multiple class="cmx-kl-file-input" style="display:none;">';

		echo '<div class="cmx-kl-file-list" style="margin:0 0 10px 0;">';
		foreach ($gallery as $index => $item) {
			$item_id   = (string) ($item['id'] ?? '');
			$item_url  = (string) ($item['url'] ?? '');
			$item_name = cmx_kl_gallery_item_filename($item);
			if ($item_name === '') {
				$item_name = $label;
			}
			$up_style   = $index === 0 ? 'display:none;' : '';
			$down_style = $index === $total - 1 ? 'display:none;' : '';
			echo '<div class="cmx-kl-file-row" data-id="' . \esc_attr($item_id) . '" data-url="' . \esc_url($item_url) . '" draggable="true" title="' . \esc_attr($item_name) . '" style="display:flex;align-items:center;gap:6px;padding:6px 0;border-top:1px solid #f0f0f1;transition:background-color .15s ease;">';
			echo '<span class="cmx-kl-drag-handle" title="Ziehen zum Verschieben" aria-label="Ziehen zum Verschieben" style="display:flex;align-items:center;justify-content:center;flex:0 0 auto;color:#8c8f94;cursor:grab;"><span class="dashicons dashicons-menu" style="font-size:16px;width:16px;height:16px;line-height:16px;"></span></span>';
			echo '<span class="cmx-kl-file-name" title="' . \esc_attr($item_name) . '" style="min-width:0;flex:1 1 auto;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . \esc_html($item_name) . '</span>';
			echo '<span class="cmx-kl-file-actions" style="display:flex;align-items:center;gap:2px;margin-left:auto;">';
			echo '<button type="button" class="button-link cmx-kl-move-up" aria-label="Nach oben" title="Nach oben" style="text-decoration:none;' . \esc_attr($up_style) . '"><span class="dashicons dashicons-arrow-up-alt2" style="font-size:16px;width:16px;height:16px;line-height:16px;"></span></button>';
			echo '<button type="button" class="button-link cmx-kl-move-down" aria-label="Nach unten" title="Nach unten" style="text-decoration:none;' . \esc_attr($down_style) . '"><span class="dashicons dashicons-arrow-down-alt2" style="font-size:16px;width:16px;height:16px;line-height:16px;"></span></button>';
			echo '<button type="button" class="button-link-delete cmx-kl-remove-file" aria-label="Bild entfernen" title="Bild entfernen"><span class="dashicons dashicons-trash" style="font-size:16px;width:16px;height:16px;line-height:16px;"></span></button>';
			echo '</span>';
			echo '</div>';
		}
		echo '</div>';

		echo '<p class="cmx-kl-save-wrap" style="margin:10px 0 0 0;display:none;"><button type="submit" class="button button-primary cmx-kl-save-button">Kontakt speichern</button></p>';
		echo '</div>';

		echo '<script>
		(function(){
			var root = document.getElementById(' . \wp_json_encode($box_id) . ');
			if (!root || root.dataset.cmxKlInit === "1") return;
			root.dataset.cmxKlInit = "1";

			var label = ' . \wp_json_encode($label) . ';
			var previewFrame = root.querySelector(".cmx-kl-preview-frame");
			var previewImage = root.querySelector(".cmx-kl-preview-image");
			var previewEmpty = root.querySelector(".cmx-kl-preview-empty");
			var fileList = root.querySelector(".cmx-kl-file-list");
			var fileInput = root.querySelector(".cmx-kl-file-input");
			var removeIdsField = root.querySelector(".cmx-kl-remove-ids");
			var orderField = root.querySelector(".cmx-kl-order");
			var changeStateField = root.querySelector(".cmx-kl-change-state");
			var status = root.querySelector(".cmx-kl-status");
			var saveWrap = root.querySelector(".cmx-kl-save-wrap");
			var form = root.closest("form");
			var restoreKey = "cmx-kl-scroll-restore:" + root.id;

			var draggedRow = null;
			var initialOrder = [];
			var hoveredPreviewUrl = "";

			function visibleRows() {
				return Array.prototype.slice.call(root.querySelectorAll(".cmx-kl-file-row")).filter(function(row){
					return row.dataset.removed !== "1";
				});
			}

			function currentOrderIds() {
				return visibleRows().map(function(row){
					return row.dataset.id || "";
				}).filter(Boolean);
			}

			function markInitialState() {
				initialOrder = currentOrderIds();
			}

			function markChanged() {
				if (changeStateField) {
					changeStateField.value = "1";
				}
			}

			function getRootTopAbsolute() {
				var rect = root.getBoundingClientRect();
				return rect.top + (window.pageYOffset || document.documentElement.scrollTop || 0);
			}

			function persistScrollRestore() {
				if (!window.sessionStorage) return;
				try {
					window.sessionStorage.setItem(restoreKey, JSON.stringify({
						offset: (window.pageYOffset || document.documentElement.scrollTop || 0) - getRootTopAbsolute()
					}));
				} catch (err) {}
			}

			function restoreScrollPosition() {
				if (!window.sessionStorage) return;
				var raw = null;
				try {
					raw = window.sessionStorage.getItem(restoreKey);
				} catch (err) {
					return;
				}
				if (!raw) return;
				try {
					window.sessionStorage.removeItem(restoreKey);
				} catch (err) {}

				var payload = null;
				try {
					payload = JSON.parse(raw);
				} catch (err) {
					return;
				}
				if (!payload || typeof payload.offset !== "number") return;

				var targetY = Math.max(0, getRootTopAbsolute() + payload.offset);
				function scrollNow() {
					window.scrollTo(0, targetY);
				}
				window.requestAnimationFrame(scrollNow);
				window.setTimeout(scrollNow, 80);
				window.setTimeout(scrollNow, 220);
				window.setTimeout(scrollNow, 420);
			}

			function openPicker() {
				if (fileInput) fileInput.click();
			}

			function setDragState(active) {
				if (!previewFrame) return;
				previewFrame.style.borderColor = active ? "#2271b1" : "#c3c4c7";
				previewFrame.style.backgroundColor = active ? "#f0f6fc" : "#fff";
			}

			function setRowHover(row, active) {
				if (!row || row.dataset.removed === "1") return;
				row.style.backgroundColor = active ? "#eef7ff" : "transparent";
			}

			function setStatus(text) {
				if (status) status.textContent = text;
			}

			function updateHiddenState() {
				var removedIds = [];
				var orderIds = [];
				Array.prototype.slice.call(root.querySelectorAll(".cmx-kl-file-row")).forEach(function(row){
					if (row.dataset.removed === "1") {
						removedIds.push(row.dataset.id || "");
						return;
					}
					orderIds.push(row.dataset.id || "");
				});
				if (removeIdsField) removeIdsField.value = removedIds.filter(Boolean).join(",");
				if (orderField) orderField.value = orderIds.filter(Boolean).join(",");
			}

			function updatePreview() {
				var previewUrl = hoveredPreviewUrl;
				var rows = visibleRows();
				if (!previewUrl && rows.length) {
					previewUrl = rows[0].dataset.url || "";
				}
				if (!previewUrl) {
					if (previewImage) {
						previewImage.removeAttribute("src");
						previewImage.style.display = "none";
					}
					if (previewEmpty) previewEmpty.style.display = "block";
					return;
				}
				if (previewImage) {
					previewImage.src = previewUrl;
					previewImage.style.display = "block";
				}
				if (previewEmpty) previewEmpty.style.display = "none";
			}

			function updateStatusFromRows() {
				var rows = visibleRows();
				if (!rows.length) {
					setStatus("Kein " + label + " vorhanden.");
					return;
				}
				setStatus(rows.length + " Bild" + (rows.length === 1 ? "" : "er") + " gespeichert.");
			}

			function updateMoveButtons() {
				var rows = Array.prototype.slice.call(root.querySelectorAll(".cmx-kl-file-row"));
				var visible = visibleRows();
				rows.forEach(function(row){
					var upBtn = row.querySelector(".cmx-kl-move-up");
					var downBtn = row.querySelector(".cmx-kl-move-down");
					if (row.dataset.removed === "1") {
						if (upBtn) upBtn.style.display = "none";
						if (downBtn) downBtn.style.display = "none";
						return;
					}

					var visibleIndex = visible.indexOf(row);
					var isFirst = visibleIndex === 0;
					var isLast = visibleIndex === visible.length - 1;

					if (upBtn) upBtn.style.display = isFirst ? "none" : "";
					if (downBtn) downBtn.style.display = isLast ? "none" : "";
				});
			}

			function updateSaveButton() {
				if (!saveWrap) return;
				var hasRemoved = !!(removeIdsField && removeIdsField.value);
				var orderNow = currentOrderIds();
				var orderChanged = orderNow.join("|") !== initialOrder.join("|");
				saveWrap.style.display = (hasRemoved || orderChanged) ? "block" : "none";
			}

			function refreshState() {
				updateHiddenState();
				updatePreview();
				updateStatusFromRows();
				updateMoveButtons();
				updateSaveButton();
			}

			function setRemovedState(row, removing) {
				row.dataset.removed = removing ? "1" : "0";
				row.style.opacity = removing ? "0.45" : "1";
				row.style.textDecoration = removing ? "line-through" : "none";
				var removeBtn = row.querySelector(".cmx-kl-remove-file");
				if (removeBtn) {
					removeBtn.title = removing ? "Entfernen rueckgaengig" : "Bild entfernen";
					removeBtn.setAttribute("aria-label", removing ? "Entfernen rueckgaengig" : "Bild entfernen");
					removeBtn.innerHTML = removing
						? \'<span class="dashicons dashicons-undo" style="font-size:16px;width:16px;height:16px;line-height:16px;"></span>\'
						: \'<span class="dashicons dashicons-trash" style="font-size:16px;width:16px;height:16px;line-height:16px;"></span>\';
				}
			}

			function setHoveredPreview(row) {
				if (!row || row.dataset.removed === "1") {
					hoveredPreviewUrl = "";
					updatePreview();
					return;
				}
				hoveredPreviewUrl = row.dataset.url || "";
				updatePreview();
			}

			function toggleRemoveRow(row) {
				var removing = row.dataset.removed !== "1";
				setRemovedState(row, removing);
				markChanged();
				refreshState();
			}

			function clearPendingRemovals() {
				Array.prototype.slice.call(root.querySelectorAll(".cmx-kl-file-row")).forEach(function(row){
					if (row.dataset.removed === "1") {
						setRemovedState(row, false);
					}
				});
			}

			function moveRow(row, direction) {
				var target = direction < 0 ? row.previousElementSibling : row.nextElementSibling;
				while (target && target.dataset.removed === "1") {
					target = direction < 0 ? target.previousElementSibling : target.nextElementSibling;
				}
				if (!target || !fileList) return;
				if (direction < 0) {
					fileList.insertBefore(row, target);
				} else {
					fileList.insertBefore(target, row);
				}
				markChanged();
				refreshState();
			}

			function attachRowDnD(row) {
				if (!row || row.dataset.dragInit === "1") return;
				row.dataset.dragInit = "1";

				row.addEventListener("mouseenter", function(){
					setRowHover(row, true);
					setHoveredPreview(row);
				});
				row.addEventListener("mouseleave", function(){
					setRowHover(row, false);
					setHoveredPreview(null);
				});
				row.addEventListener("dragstart", function(e){
					if (row.dataset.removed === "1") {
						e.preventDefault();
						return;
					}
					draggedRow = row;
					row.style.opacity = "0.65";
					setHoveredPreview(null);
					if (e.dataTransfer) {
						e.dataTransfer.effectAllowed = "move";
						try {
							e.dataTransfer.setData("text/plain", row.dataset.id || "");
						} catch (err) {}
					}
				});
				row.addEventListener("dragend", function(){
					row.style.opacity = row.dataset.removed === "1" ? "0.45" : "1";
					draggedRow = null;
					setRowHover(row, false);
					setHoveredPreview(null);
				});
				row.addEventListener("dragover", function(e){
					if (!draggedRow || draggedRow === row || row.dataset.removed === "1") {
						return;
					}
					e.preventDefault();
					if (e.dataTransfer) {
						e.dataTransfer.dropEffect = "move";
					}
					setRowHover(row, true);
				});
				row.addEventListener("drop", function(e){
					if (!draggedRow || draggedRow === row || row.dataset.removed === "1" || !fileList) {
						return;
					}
					e.preventDefault();
					var rect = row.getBoundingClientRect();
					var before = e.clientY < rect.top + rect.height / 2;
					if (before) {
						fileList.insertBefore(draggedRow, row);
					} else {
						fileList.insertBefore(draggedRow, row.nextSibling);
					}
					markChanged();
					refreshState();
				});
			}

			function submitForUpload() {
				if (!form) return;
				clearPendingRemovals();
				updateHiddenState();
				markChanged();
				setStatus("Bilder werden hochgeladen ...");
				persistScrollRestore();
				form.submit();
			}

			if (previewFrame) {
				previewFrame.addEventListener("click", function(){
					openPicker();
				});
			}

			if (form) {
				form.addEventListener("submit", function(){
					persistScrollRestore();
				});
			}

			if (fileList) {
				Array.prototype.slice.call(fileList.querySelectorAll(".cmx-kl-file-row")).forEach(attachRowDnD);
				fileList.addEventListener("click", function(e){
					var removeBtn = e.target.closest ? e.target.closest(".cmx-kl-remove-file") : null;
					if (removeBtn) {
						e.preventDefault();
						var row = removeBtn.closest(".cmx-kl-file-row");
						if (row) toggleRemoveRow(row);
						return;
					}

					var upBtn = e.target.closest ? e.target.closest(".cmx-kl-move-up") : null;
					if (upBtn) {
						e.preventDefault();
						var upRow = upBtn.closest(".cmx-kl-file-row");
						if (upRow) moveRow(upRow, -1);
						return;
					}

					var downBtn = e.target.closest ? e.target.closest(".cmx-kl-move-down") : null;
					if (downBtn) {
						e.preventDefault();
						var downRow = downBtn.closest(".cmx-kl-file-row");
						if (downRow) moveRow(downRow, 1);
					}
				});
			}

			if (fileInput) {
				fileInput.addEventListener("change", function(){
					var files = fileInput.files && fileInput.files.length ? fileInput.files : null;
					if (!files) return;
					setStatus(files.length === 1 ? ("Neue Datei: " + files[0].name) : (files.length + " neue Bilder ausgewaehlt."));
					submitForUpload();
				});
			}

			if (previewFrame) {
				["dragenter", "dragover"].forEach(function(eventName){
					previewFrame.addEventListener(eventName, function(e){
						e.preventDefault();
						e.stopPropagation();
						setDragState(true);
					});
				});

				["dragleave", "dragend"].forEach(function(eventName){
					previewFrame.addEventListener(eventName, function(e){
						e.preventDefault();
						e.stopPropagation();
						setDragState(false);
					});
				});

				previewFrame.addEventListener("drop", function(e){
					e.preventDefault();
					e.stopPropagation();
					setDragState(false);

					var files = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files : null;
					if (!files || !files.length || !fileInput) {
						return;
					}

					if (window.DataTransfer) {
						var transfer = new DataTransfer();
						Array.prototype.slice.call(files).forEach(function(file){
							transfer.items.add(file);
						});
						fileInput.files = transfer.files;
					}
					fileInput.dispatchEvent(new Event("change", { bubbles: true }));
				});
			}

			markInitialState();
			refreshState();
			restoreScrollPosition();
			window.addEventListener("load", restoreScrollPosition);
		})();
		</script>';
	}
}

\add_action('save_post_kontakte', function ($post_id, $post, $update) {
	if (\defined('DOING_AUTOSAVE') && \DOING_AUTOSAVE) {
		return;
	}
	if (!\is_object($post) || $post->post_type !== 'kontakte') {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}
	if (empty($_POST['cmx_kl_kontakte_nonce']) || !\wp_verify_nonce((string) $_POST['cmx_kl_kontakte_nonce'], 'cmx_kl_kontakte_nonce')) {
		return;
	}

	$meta_base = cmx_kl_meta_base();
	cmx_maybe_migrate_contact_logo_to_archiv_path((int) $post_id);
	$gallery = cmx_kl_gallery_get((int) $post_id, $meta_base);

	$remove_ids_raw = isset($_POST['cmx_kl_remove_ids']) ? (string) \wp_unslash($_POST['cmx_kl_remove_ids']) : '';
	$remove_ids = \array_values(\array_filter(\array_map('sanitize_key', \explode(',', $remove_ids_raw))));
	if ($remove_ids !== []) {
		$keep = [];
		foreach ($gallery as $item) {
			$item_id = (string) ($item['id'] ?? '');
			if ($item_id !== '' && \in_array($item_id, $remove_ids, true)) {
				$old_path = (string) ($item['path'] ?? '');
				if ($old_path !== '' && \file_exists($old_path)) {
					@unlink($old_path);
				}
				continue;
			}
			$keep[] = $item;
		}
		$gallery = $keep;
	}

	$order_raw = isset($_POST['cmx_kl_order']) ? (string) \wp_unslash($_POST['cmx_kl_order']) : '';
	$order_ids = \array_values(\array_filter(\array_map('sanitize_key', \explode(',', $order_raw))));
	if ($order_ids !== []) {
		$by_id = [];
		foreach ($gallery as $item) {
			$item_id = (string) ($item['id'] ?? '');
			if ($item_id !== '') {
				$by_id[$item_id] = $item;
			}
		}

		$ordered = [];
		foreach ($order_ids as $item_id) {
			if (isset($by_id[$item_id])) {
				$ordered[] = $by_id[$item_id];
				unset($by_id[$item_id]);
			}
		}

		if ($by_id !== []) {
			foreach ($gallery as $item) {
				$item_id = (string) ($item['id'] ?? '');
				if ($item_id !== '' && isset($by_id[$item_id])) {
					$ordered[] = $by_id[$item_id];
					unset($by_id[$item_id]);
				}
			}
		}

		$gallery = $ordered;
	}

	$has_change_state = !empty($_POST['cmx_kl_change_state']);
	if ($has_change_state) {
		\update_post_meta($post_id, cmx_kl_manual_flag_meta_key(), '1');
	}

	$base_dir = cmx_local_base_path();
	$base_url = cmx_local_base_url();
	if (!\is_dir($base_dir)) {
		if (!\wp_mkdir_p($base_dir)) {
			\error_log('[CMX Kontakte] Konnte Zielordner nicht erstellen: ' . $base_dir);
			return;
		}
	}

	if (!empty($_FILES['cmx_kl_files']) && !empty($_FILES['cmx_kl_files']['name'])) {
		$new_items = cmx_kl_store_uploaded_files((array) $_FILES['cmx_kl_files'], $base_dir, $base_url);
		if ($new_items !== []) {
			$gallery = \array_merge($gallery, $new_items);
		}
		cmx_kl_gallery_update((int) $post_id, $meta_base, $gallery);
		return;
	}

	cmx_kl_gallery_update((int) $post_id, $meta_base, $gallery);
}, 10, 3);

\add_action('save_post_kontakte', function ($post_id, $post, $update) {
	if (\wp_is_post_revision($post_id)) {
		return;
	}
	if (\defined('DOING_AUTOSAVE') && \DOING_AUTOSAVE) {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

	cmx_maybe_migrate_contact_logo_to_archiv_path((int) $post_id);

	if (!empty($_POST['cmx_kl_change_state'])) {
		return;
	}
	if ((string) \get_post_meta($post_id, cmx_kl_manual_flag_meta_key(), true) === '1') {
		return;
	}
	if (!empty($_POST[cmx_kl_meta_base() . '_url']) || !empty($_POST[cmx_kl_meta_base() . '_path'])) {
		return;
	}
	if (cmx_has_local_logo((int) $post_id)) {
		return;
	}

	$res = cmx_fetch_logo_from_url((int) $post_id, 2.0);
	if (\is_wp_error($res)) {
		\error_log('[CMX Logo] ' . $res->get_error_code() . ': ' . $res->get_error_message());
	}
}, 20, 3);
