<?php namespace CLOUDMEISTER\CMX\MisBuero; defined('ABSPATH') || exit;

/**
 * Lokale Bildverwaltung NUR für CPT "artikel" (inkl. Metabox + Auto-Fetch von Bezugsquelle)
 * - Manuelles Hochladen in der Metabox
 * - AUTOMATISCHES Laden von der Bezugsquelle beim Speichern, wenn kein Upload erfolgt
 * - Bezugsquelle wird aus bekannten Metafeldern gelesen:
 *     _cmx_bezugsquelle_url, _cmx_bezugsquelle, _cmx_artikel_bezugsquelle, _cmx_artikel_bild_url
 *   (per Filter erweiterbar: 'cmx_li_source_fields')
 * - Speicher: /wp-content/uploads/misbuero/archiv/bilder/artikel/{post_title}.ext (+ Cache-Busting ?v=filemtime)
 * - NEU: Produkt-/Artikelbeschreibung aus der Bezugsquelle (og:description / twitter:description / meta description / JSON-LD) in den Editor schreiben – nur wenn leer
 */

/** Ziel-Unterordner relativ zu uploads/ */
if (!defined(__NAMESPACE__.'\\CMX_LOCAL_IMG_SUBDIR')) {
	define(__NAMESPACE__.'\\CMX_LOCAL_IMG_SUBDIR', '/misbuero/archiv/bilder');
}

/** Basis-Pfade/URLs */
if (!\function_exists(__NAMESPACE__.'\\cmx_li_base_path')) {
	function cmx_li_base_path(): string {
		$u = wp_get_upload_dir();
		return wp_normalize_path($u['basedir'] . CMX_LOCAL_IMG_SUBDIR . '/artikel');
	}
}
if (!\function_exists(__NAMESPACE__.'\\cmx_li_base_url')) {
	function cmx_li_base_url(): string {
		$u = wp_get_upload_dir();
		return rtrim($u['baseurl'], '/') . CMX_LOCAL_IMG_SUBDIR . '/artikel';
	}
}

/** Dateibasenamen aus Post-Titel ableiten */
if (!\function_exists(__NAMESPACE__.'\\cmx_li_basename_for_post')) {
	function cmx_li_basename_for_post(\WP_Post $post): string {
		$title_slug = strtolower((string) sanitize_title(get_the_title($post) ?: 'artikel'));
		return $title_slug !== '' ? $title_slug : 'artikel';
	}
}

if (!\function_exists(__NAMESPACE__.'\\cmx_li_gallery_meta_key')) {
	function cmx_li_gallery_meta_key(): string {
		return '_cmx_local_image_artikel_gallery';
	}
}

if (!\function_exists(__NAMESPACE__.'\\cmx_li_gallery_item_filename')) {
	function cmx_li_gallery_item_filename(array $item): string {
		$path = (string) ($item['path'] ?? '');
		$url = (string) ($item['url'] ?? '');
		$candidate = $path !== '' ? basename($path) : '';
		if ($candidate === '' && $url !== '') {
			$url_path = (string) parse_url($url, PHP_URL_PATH);
			$candidate = $url_path !== '' ? basename($url_path) : '';
		}
		return rawurldecode($candidate);
	}
}

if (!\function_exists(__NAMESPACE__.'\\cmx_li_gallery_normalize')) {
	function cmx_li_gallery_normalize(array $items): array {
		$out = [];
		foreach ($items as $item) {
			if (!\is_array($item)) {
				continue;
			}
			$id = \sanitize_key((string) ($item['id'] ?? ''));
			if ($id === '') {
				$id = 'img_' . \wp_generate_password(12, false, false);
			}
			$path = \trim((string) ($item['path'] ?? ''));
			$url = \trim((string) ($item['url'] ?? ''));
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

if (!\function_exists(__NAMESPACE__.'\\cmx_li_gallery_get')) {
	function cmx_li_gallery_get(int $post_id, string $meta_base): array {
		$items = \get_post_meta($post_id, cmx_li_gallery_meta_key(), true);
		$items = \is_array($items) ? cmx_li_gallery_normalize($items) : [];
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

if (!\function_exists(__NAMESPACE__.'\\cmx_li_gallery_update')) {
	function cmx_li_gallery_update(int $post_id, string $meta_base, array $items): void {
		$items = cmx_li_gallery_normalize($items);

		if ($items === []) {
			\delete_post_meta($post_id, cmx_li_gallery_meta_key());
			\delete_post_meta($post_id, $meta_base . '_path');
			\delete_post_meta($post_id, $meta_base . '_url');
			\clean_post_cache($post_id);
			return;
		}

		\update_post_meta($post_id, cmx_li_gallery_meta_key(), $items);
		\update_post_meta($post_id, $meta_base . '_path', (string) ($items[0]['path'] ?? ''));
		\update_post_meta($post_id, $meta_base . '_url', (string) ($items[0]['url'] ?? ''));
		\clean_post_cache($post_id);
	}
}

if (!\function_exists(__NAMESPACE__.'\\cmx_li_uploaded_files_from_request')) {
	function cmx_li_uploaded_files_from_request(array $files): array {
		$out = [];
		$names = (array) ($files['name'] ?? []);
		$tmp_names = (array) ($files['tmp_name'] ?? []);
		$errors = (array) ($files['error'] ?? []);
		$sizes = (array) ($files['size'] ?? []);
		$types = (array) ($files['type'] ?? []);

		foreach ($names as $index => $name) {
			if ((string) $name === '') {
				continue;
			}
			$out[] = [
				'name'     => $name,
				'tmp_name' => (string) ($tmp_names[$index] ?? ''),
				'error'    => (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE),
				'size'     => (int) ($sizes[$index] ?? 0),
				'type'     => (string) ($types[$index] ?? ''),
			];
		}

		return $out;
	}
}

if (!\function_exists(__NAMESPACE__.'\\cmx_li_upload_base_name')) {
	function cmx_li_upload_base_name(string $original_name, string $fallback = 'bild'): string {
		$base = (string) pathinfo($original_name, PATHINFO_FILENAME);
		$base = \sanitize_file_name($base);
		$base = \preg_replace('~\.[a-z0-9]+$~i', '', $base);
		$base = \trim((string) $base, "-_. \t\n\r\0\x0B");
		if ($base === '') {
			$base = \sanitize_file_name($fallback);
		}
		return $base !== '' ? \strtolower($base) : 'bild';
	}
}

if (!\function_exists(__NAMESPACE__.'\\cmx_li_next_numbered_target')) {
	function cmx_li_next_numbered_target(string $base_dir, string $base_name, string $ext): string {
		$plain_target = \wp_normalize_path($base_dir . '/' . $base_name . $ext);
		if (!\file_exists($plain_target)) {
			return $plain_target;
		}

		$counter = 1;
		do {
			$target = \wp_normalize_path($base_dir . '/' . $base_name . '-' . $counter . $ext);
			$counter++;
		} while (\file_exists($target));

		return $target;
	}
}

/** Bestehende Artikelbild-Datei auf aktuellen Titel umbenennen */
if (!\function_exists(__NAMESPACE__.'\\cmx_li_sync_filename_with_title')) {
	function cmx_li_sync_filename_with_title(int $post_id, \WP_Post $post, string $meta_base): void {
		$current_path = (string) get_post_meta($post_id, $meta_base . '_path', true);
		if ($current_path === '' || !is_file($current_path)) {
			return;
		}

		$base_dir = cmx_li_base_path();
		$base_url = cmx_li_base_url();
		if (!is_dir($base_dir)) {
			wp_mkdir_p($base_dir);
		}

		$ext = strtolower((string) pathinfo($current_path, PATHINFO_EXTENSION));
		if ($ext === '') {
			return;
		}
		$basename = cmx_li_basename_for_post($post);
		$target = wp_normalize_path($base_dir . '/' . $basename . '.' . $ext);
		$current_norm = wp_normalize_path($current_path);

		if ($current_norm !== $target) {
			if (is_file($target)) {
				@unlink($target);
			}
			$moved = @rename($current_norm, $target);
			if (!$moved) {
				$moved = @copy($current_norm, $target);
				if ($moved) {
					@unlink($current_norm);
				}
			}
			if (!$moved) {
				return;
			}
			@chmod($target, 0644);
			$current_norm = $target;
		}

		$version = @filemtime($current_norm) ?: time();
		$url = $base_url . '/' . rawurlencode($basename . '.' . $ext) . '?v=' . $version;
		update_post_meta($post_id, $meta_base . '_path', $current_norm);
		update_post_meta($post_id, $meta_base . '_url', $url);
		clean_post_cache($post_id);
	}
}

/** Edit-Form darf Dateien senden */
add_action('post_edit_form_tag', function () {
	echo ' enctype="multipart/form-data"';
});

/** Metabox NUR für "artikel" */
add_action('add_meta_boxes', function () {
	add_meta_box(
		'cmx_li_box_artikel',
		'Artikelbild',
		__NAMESPACE__.'\\cmx_li_render_box_artikel',
		'artikel',
		'side',
		'low'
	);
});

/** Metabox-Renderer mit Galerie, Reihenfolge und manuellem Entfernen */
if (!\function_exists(__NAMESPACE__.'\\cmx_li_render_box_artikel')) {
	function cmx_li_render_box_artikel(\WP_Post $post): void {
		wp_nonce_field('cmx_li_artikel_nonce', 'cmx_li_artikel_nonce');

		$meta_base = '_cmx_local_image_artikel';
		$gallery   = cmx_li_gallery_get((int) $post->ID, $meta_base);
		$primary   = $gallery[0] ?? null;
		$url       = \is_array($primary) ? (string) ($primary['url'] ?? '') : '';
		$box_id    = 'cmx-li-box-' . (int) $post->ID;
		$has_image = $url !== '';

		echo '<div id="' . esc_attr($box_id) . '" class="cmx-li-box">';
		echo '<div class="cmx-li-preview-frame" style="position:relative;margin-bottom:10px;border:1px dashed #c3c4c7;background:#fff;padding:10px;height:220px;display:flex;align-items:center;justify-content:center;cursor:pointer;overflow:hidden;transition:border-color .15s ease, background-color .15s ease;">';
		echo '<img class="cmx-li-preview-image" src="' . ($has_image ? esc_url($url) : '') . '" alt="" style="' . ($has_image ? 'display:block;' : 'display:none;') . 'max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain;">';
		echo '<div class="cmx-li-preview-empty" style="' . ($has_image ? 'display:none;' : 'display:block;') . 'color:#646970;font-style:italic;text-align:center;">Kein Artikelbild vorhanden.<br>Dateien hier ablegen oder klicken.</div>';
		echo '<div class="cmx-li-drop-hint" style="display:none;position:absolute;inset:0;padding:18px;background:rgba(240,246,252,0.96);color:#135e96;font-weight:600;font-size:14px;line-height:1.5;text-align:center;align-items:center;justify-content:center;pointer-events:none;">Jetzt los lassen um es als weiteres neues Bild zu speichern</div>';
		echo '</div>';

		echo '<input type="hidden" name="cmx_li_remove_ids" value="" class="cmx-li-remove-ids">';
		echo '<input type="hidden" name="cmx_li_order" value="" class="cmx-li-order">';
		echo '<input type="file" name="cmx_li_files[]" accept="image/*" multiple class="cmx-li-file-input" style="display:none;">';

		echo '<div class="cmx-li-file-list" style="margin:0 0 10px 0;">';
		$total_items = \count($gallery);
		foreach ($gallery as $index => $item) {
			$item_id = (string) ($item['id'] ?? '');
			$item_url = (string) ($item['url'] ?? '');
			$item_name = cmx_li_gallery_item_filename($item);
			if ($item_name === '') {
				$item_name = 'Bild';
			}
			$item_filetype = \wp_check_filetype($item_name);
			$item_mime = (string) ($item_filetype['type'] ?? '');
			if ($item_mime === '') {
				$item_mime = 'application/octet-stream';
			}
			$up_style = $index === 0 ? 'display:none;' : '';
			$down_style = $index === $total_items - 1 ? 'display:none;' : '';
			echo '<div class="cmx-li-file-row" data-id="' . esc_attr($item_id) . '" data-url="' . esc_url($item_url) . '" data-download-name="' . esc_attr($item_name) . '" data-download-type="' . esc_attr($item_mime) . '" draggable="true" title="' . esc_attr($item_name) . '" style="display:flex;align-items:center;gap:6px;padding:6px 0;border-top:1px solid #f0f0f1;transition:background-color .15s ease;">';
			echo '<span class="cmx-li-drag-handle" title="Ziehen zum Verschieben" aria-label="Ziehen zum Verschieben" style="display:flex;align-items:center;justify-content:center;flex:0 0 auto;color:#8c8f94;cursor:grab;"><span class="dashicons dashicons-menu" style="font-size:16px;width:16px;height:16px;line-height:16px;"></span></span>';
			if ($item_url !== '') {
				echo '<a class="cmx-li-file-name" href="' . esc_url($item_url) . '" download="' . esc_attr($item_name) . '" target="_blank" rel="noopener noreferrer" title="Bild herunterladen: ' . esc_attr($item_name) . '" style="min-width:0;flex:1 1 auto;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:inherit;text-decoration:none;">' . esc_html($item_name) . '</a>';
			} else {
				echo '<span class="cmx-li-file-name" title="' . esc_attr($item_name) . '" style="min-width:0;flex:1 1 auto;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . esc_html($item_name) . '</span>';
			}
			echo '<span class="cmx-li-file-actions" style="display:flex;align-items:center;gap:2px;margin-left:auto;">';
			echo '<button type="button" class="button-link cmx-li-move-up" aria-label="Nach oben" title="Nach oben" style="text-decoration:none;' . esc_attr($up_style) . '"><span class="dashicons dashicons-arrow-up-alt2" style="font-size:16px;width:16px;height:16px;line-height:16px;"></span></button>';
			echo '<button type="button" class="button-link cmx-li-move-down" aria-label="Nach unten" title="Nach unten" style="text-decoration:none;' . esc_attr($down_style) . '"><span class="dashicons dashicons-arrow-down-alt2" style="font-size:16px;width:16px;height:16px;line-height:16px;"></span></button>';
			echo '<button type="button" class="button-link-delete cmx-li-remove-file" aria-label="Bild entfernen" title="Bild entfernen"><span class="dashicons dashicons-trash" style="font-size:16px;width:16px;height:16px;line-height:16px;"></span></button>';
			echo '</span>';
			echo '</div>';
		}
		echo '</div>';

		echo '<p class="cmx-li-save-wrap" style="margin:10px 0 0 0;display:none;"><button type="submit" class="button button-primary cmx-li-save-button">Artikel speichern</button></p>';
		echo '</div>';

		echo '<script>
		(function(){
			var root = document.getElementById(' . wp_json_encode($box_id) . ');
			if (!root || root.dataset.cmxLiInit === "1") return;
			root.dataset.cmxLiInit = "1";

			var previewFrame = root.querySelector(".cmx-li-preview-frame");
			var previewImage = root.querySelector(".cmx-li-preview-image");
			var previewEmpty = root.querySelector(".cmx-li-preview-empty");
			var previewDropHint = root.querySelector(".cmx-li-drop-hint");
			var fileList = root.querySelector(".cmx-li-file-list");
			var fileInput = root.querySelector(".cmx-li-file-input");
			var removeIdsField = root.querySelector(".cmx-li-remove-ids");
			var orderField = root.querySelector(".cmx-li-order");
			var status = root.querySelector(".cmx-li-status");
			var saveWrap = root.querySelector(".cmx-li-save-wrap");
			var form = root.closest("form");
			var restoreKey = "cmx-li-scroll-restore:" + root.id;

			var draggedRow = null;
			var initialOrder = [];
			var hoveredPreviewUrl = "";

			function visibleRows() {
				return Array.prototype.slice.call(root.querySelectorAll(".cmx-li-file-row")).filter(function(row){
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

			function getRootTopAbsolute() {
				var rect = root.getBoundingClientRect();
				return rect.top + (window.pageYOffset || document.documentElement.scrollTop || 0);
			}

			function persistScrollRestore() {
				if (!window.sessionStorage) return;
				try {
					var payload = {
						offset: (window.pageYOffset || document.documentElement.scrollTop || 0) - getRootTopAbsolute()
					};
					window.sessionStorage.setItem(restoreKey, JSON.stringify(payload));
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
				if (previewDropHint) {
					previewDropHint.style.display = active ? "flex" : "none";
				}
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
				Array.prototype.slice.call(root.querySelectorAll(".cmx-li-file-row")).forEach(function(row){
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
					setStatus("Kein Artikelbild vorhanden.");
					return;
				}
				setStatus(rows.length + " Bild" + (rows.length === 1 ? "" : "er") + " gespeichert.");
			}

			function updateMoveButtons() {
				var rows = Array.prototype.slice.call(root.querySelectorAll(".cmx-li-file-row"));
				var visible = visibleRows();
				rows.forEach(function(row){
					var upBtn = row.querySelector(".cmx-li-move-up");
					var downBtn = row.querySelector(".cmx-li-move-down");
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
				var removeBtn = row.querySelector(".cmx-li-remove-file");
				if (removeBtn) {
					removeBtn.title = removing ? "Entfernen rückgängig" : "Bild entfernen";
					removeBtn.setAttribute("aria-label", removing ? "Entfernen rückgängig" : "Bild entfernen");
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
				refreshState();
			}

			function clearPendingRemovals() {
				Array.prototype.slice.call(root.querySelectorAll(".cmx-li-file-row")).forEach(function(row){
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
						e.dataTransfer.effectAllowed = "copyMove";
						try {
							e.dataTransfer.setData("text/plain", row.dataset.id || "");
						} catch (err) {}
						try {
							if (row.dataset.url) {
								e.dataTransfer.setData("text/uri-list", row.dataset.url);
								e.dataTransfer.setData("DownloadURL", (row.dataset.downloadType || "application/octet-stream") + ":" + (row.dataset.downloadName || "bild") + ":" + row.dataset.url);
							}
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
					refreshState();
				});
			}

			function submitForUpload() {
				if (!form) return;
				clearPendingRemovals();
				updateHiddenState();
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
				Array.prototype.slice.call(fileList.querySelectorAll(".cmx-li-file-row")).forEach(attachRowDnD);
				fileList.addEventListener("click", function(e){
					var removeBtn = e.target.closest ? e.target.closest(".cmx-li-remove-file") : null;
					if (removeBtn) {
						e.preventDefault();
						var row = removeBtn.closest(".cmx-li-file-row");
						if (row) toggleRemoveRow(row);
						return;
					}

					var upBtn = e.target.closest ? e.target.closest(".cmx-li-move-up") : null;
					if (upBtn) {
						e.preventDefault();
						var upRow = upBtn.closest(".cmx-li-file-row");
						if (upRow) moveRow(upRow, -1);
						return;
					}

					var downBtn = e.target.closest ? e.target.closest(".cmx-li-move-down") : null;
					if (downBtn) {
						e.preventDefault();
						var downRow = downBtn.closest(".cmx-li-file-row");
						if (downRow) moveRow(downRow, 1);
					}
				});
			}

			if (fileInput) {
				fileInput.addEventListener("change", function(){
					var files = fileInput.files && fileInput.files.length ? fileInput.files : null;
					if (!files) {
						return;
					}
					setStatus(files.length === 1 ? ("Neue Datei: " + files[0].name) : (files.length + " neue Bilder ausgewählt."));
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

/** Save-Handler NUR für "artikel" (inkl. Auto-Fetch von Bezugsquelle, wenn kein Upload) */
add_action('save_post_artikel', function ($post_id, $post, $update) {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!is_object($post) || $post->post_type !== 'artikel') return;
	if (!current_user_can('edit_post', $post_id)) return;
	if (empty($_POST['cmx_li_artikel_nonce']) || !wp_verify_nonce($_POST['cmx_li_artikel_nonce'], 'cmx_li_artikel_nonce')) return;

	$meta_base = '_cmx_local_image_artikel';
	$gallery = cmx_li_gallery_get($post_id, $meta_base);

	$remove_ids_raw = isset($_POST['cmx_li_remove_ids']) ? (string) wp_unslash($_POST['cmx_li_remove_ids']) : '';
	$remove_ids = array_values(array_filter(array_map('sanitize_key', explode(',', $remove_ids_raw))));
	if ($remove_ids !== []) {
		$keep = [];
		foreach ($gallery as $item) {
			$item_id = (string) ($item['id'] ?? '');
			if ($item_id !== '' && in_array($item_id, $remove_ids, true)) {
				$old_path = (string) ($item['path'] ?? '');
				if ($old_path !== '' && file_exists($old_path)) {
					@unlink($old_path);
				}
				continue;
			}
			$keep[] = $item;
		}
		$gallery = $keep;
	}

	$order_raw = isset($_POST['cmx_li_order']) ? (string) wp_unslash($_POST['cmx_li_order']) : '';
	$order_ids = array_values(array_filter(array_map('sanitize_key', explode(',', $order_raw))));
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

	$base_dir = cmx_li_base_path();
	$base_url = cmx_li_base_url();
	if (!is_dir($base_dir)) {
		if (!wp_mkdir_p($base_dir)) {
			error_log('[CMX] Konnte Zielordner nicht erstellen: '.$base_dir);
			return;
		}
	}

	$basename = cmx_li_basename_for_post($post);

	// 1) MANUELLER UPLOAD?
	if (!empty($_FILES['cmx_li_files']) && !empty($_FILES['cmx_li_files']['name'])) {
		$new_items = cmx_li_store_uploaded_files((array) $_FILES['cmx_li_files'], $base_dir, $base_url, $basename);
		if ($new_items === []) {
			error_log('[CMX] Manuelles Speichern fehlgeschlagen.');
		} else {
			$gallery = array_merge($gallery, $new_items);
		}
		cmx_li_gallery_update($post_id, $meta_base, $gallery);
		return; // manuell hat Vorrang
	}

	// 1b) Kein Upload: nur Reihenfolge/Entfernen in die Galerie übernehmen
	cmx_li_gallery_update($post_id, $meta_base, $gallery);

	// 2) KEIN Upload: Versuche AUTOMATISCH von Bezugsquelle zu laden
	$source_url = cmx_li_find_source_url($post_id);
	// if ($source_url) {
	// 	$img_url = cmx_li_resolve_image_url($source_url);
	// 	if ($img_url) {
	// 		if (!cmx_li_store_remote_image($img_url, $base_dir, $base_url, $basename, $post_id, $meta_base)) {
	// 			error_log('[CMX] Auto-Fetch: Speichern fehlgeschlagen: '.$img_url);
	// 		}
	// 	} else {
	// 		error_log('[CMX] Auto-Fetch: Keine Bild-URL aus Bezugsquelle ermittelbar: '.$source_url);
	// 	}

	// 	// NEU: Artikel-/Produktbeschreibung in Editor übernehmen – nur wenn leer
	// 	if (trim((string)$post->post_content) === '') {
	// 		$desc = cmx_li_fetch_page_description($source_url);
	// 		if ($desc) {
	// 			wp_update_post([
	// 				'ID'           => $post_id,
	// 				'post_content' => sanitize_textarea_field($desc),
	// 			]);
	// 		}
	// 	}
	// }

}, 10, 3);

/** --- Manuelles Speichern mehrerer Uploads --- */
if (!\function_exists(__NAMESPACE__.'\\cmx_li_store_uploaded_files')) {
	function cmx_li_store_uploaded_files(array $files, string $base_dir, string $base_url, string $basename): array {
		$allowed_mimes = [
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'gif'      => 'image/gif',
			'webp'     => 'image/webp',
			'avif'     => 'image/avif',
		];
		$stored = [];

		foreach (cmx_li_uploaded_files_from_request($files) as $file) {
			if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
				continue;
			}

			$check = wp_check_filetype_and_ext($file['tmp_name'] ?? '', $file['name'] ?? '', $allowed_mimes);
			if (empty($check['ext']) || empty($check['type'])) {
				continue;
			}

			$ext = '.' . strtolower((string) $check['ext']);
			$target_base = \function_exists(__NAMESPACE__ . '\\cmx_li_upload_base_name')
				? cmx_li_upload_base_name((string) ($file['name'] ?? ''), $basename)
				: $basename;
			$target = \function_exists(__NAMESPACE__ . '\\cmx_li_next_numbered_target')
				? cmx_li_next_numbered_target($base_dir, $target_base, $ext)
				: \wp_normalize_path($base_dir . '/' . $target_base . '-1' . $ext);

			if (!is_uploaded_file($file['tmp_name'])) {
				continue;
			}
			if (!@move_uploaded_file($file['tmp_name'], $target)) {
				continue;
			}
			@chmod($target, 0644);

			$version = @filemtime($target) ?: time();
			$url = $base_url . '/' . rawurlencode(basename($target)) . '?v=' . $version;
			$stored[] = [
				'id'   => 'img_' . wp_generate_password(12, false, false),
				'path' => $target,
				'url'  => $url,
			];
		}

		return $stored;
	}
}

/** --- Quelle aus Metafeldern finden --- */
if (!\function_exists(__NAMESPACE__.'\\cmx_li_find_source_url')) {
	function cmx_li_find_source_url(int $post_id): ?string {
		$fields = (array) apply_filters('cmx_li_source_fields', [
			'_cmx_bezugsquelle_url',
			'_cmx_bezugsquelle',
			'_cmx_artikel_bezugsquelle',
			'_cmx_artikel_bild_url',
		]);

		foreach ($fields as $key) {
			$val = trim((string) get_post_meta($post_id, $key, true));
			if ($val !== '' && preg_match('~^https?://~i', $val)) {
				return $val;
			}
		}
		return null;
	}
}

/** Alte Datei löschen */
if (!\function_exists(__NAMESPACE__.'\\cmx_li_delete_old')) {
	function cmx_li_delete_old(int $post_id, string $meta_base): void {
		$old = (string) get_post_meta($post_id, $meta_base . '_path', true);
		if ($old && file_exists($old)) {
			@unlink($old);
		}
	}
}
