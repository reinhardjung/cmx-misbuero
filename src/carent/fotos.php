<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_FOTOS_ROWS_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_FOTOS_ROWS_META', '_cmx_carent_fotos_rows');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fotos_taxonomy')) {
	function cmx_carent_fotos_taxonomy(): string {
		if (\defined(__NAMESPACE__ . '\\TAX_CARENT_FOTOS')) {
			return (string) \constant(__NAMESPACE__ . '\\TAX_CARENT_FOTOS');
		}

		return (string) cmx_tax_key('carent', 'Fotos');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fotos_term_options')) {
	function cmx_carent_fotos_term_options(): array {
		$taxonomy = cmx_carent_fotos_taxonomy();
		$terms = \get_terms([
			'taxonomy' => $taxonomy,
			'hide_empty' => false,
			'orderby' => 'name',
			'order' => 'ASC',
		]);

		if (\is_wp_error($terms) || !\is_array($terms)) {
			return [];
		}

		$options = [];
		foreach ($terms as $term) {
			if (!$term instanceof \WP_Term) {
				continue;
			}

			$options[] = [
				'id' => (int) $term->term_id,
				'label' => (string) $term->name,
			];
		}

		return $options;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fotos_normalize_rows')) {
	function cmx_carent_fotos_normalize_rows(mixed $raw_rows): array {
		if (!\is_array($raw_rows)) {
			return [];
		}

		$rows = [];
		foreach ($raw_rows as $raw_row) {
			if (!\is_array($raw_row)) {
				continue;
			}

			$term_id = isset($raw_row['term_id']) ? (int) $raw_row['term_id'] : 0;
			$attachment_id = isset($raw_row['attachment_id']) ? (int) $raw_row['attachment_id'] : 0;
			if ($term_id <= 0 && $attachment_id <= 0) {
				continue;
			}

			$rows[] = [
				'term_id' => $term_id > 0 ? $term_id : 0,
				'attachment_id' => $attachment_id > 0 ? $attachment_id : 0,
			];
		}

		return $rows;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fotos_rows')) {
	function cmx_carent_fotos_rows(int $post_id): array {
		$rows = cmx_carent_fotos_normalize_rows(\get_post_meta($post_id, CMX_CARENT_FOTOS_ROWS_META, true));
		if ($rows !== []) {
			return $rows;
		}

		$taxonomy = cmx_carent_fotos_taxonomy();
		$terms = \get_the_terms($post_id, $taxonomy);
		if (\is_wp_error($terms) || !\is_array($terms)) {
			return [];
		}

		$fallback_rows = [];
		foreach ($terms as $term) {
			if (!$term instanceof \WP_Term) {
				continue;
			}

			$fallback_rows[] = [
				'term_id' => (int) $term->term_id,
				'attachment_id' => 0,
			];
		}

		return $fallback_rows;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fotos_attachment_payload')) {
	function cmx_carent_fotos_attachment_payload(int $attachment_id): array {
		if ($attachment_id <= 0 || \get_post_type($attachment_id) !== 'attachment') {
			return [
				'id' => 0,
				'preview_url' => '',
				'file_url' => '',
				'label' => '',
			];
		}

		$preview_url = (string) \wp_get_attachment_image_url($attachment_id, 'medium');
		$file_url = (string) \wp_get_attachment_url($attachment_id);
		$label = (string) \basename((string) \get_attached_file($attachment_id));

		if ($preview_url === '' && $file_url !== '') {
			$preview_url = $file_url;
		}

		return [
			'id' => $attachment_id,
			'preview_url' => $preview_url,
			'file_url' => $file_url,
			'label' => $label,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fotos_empty_markup')) {
	function cmx_carent_fotos_empty_markup(): string {
		return '<div class="cmx-carent-fotos-empty">' . \esc_html__('Foto hier ablegen oder anklicken.', 'cmx-misbuero') . '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fotos_row_markup')) {
	function cmx_carent_fotos_row_markup(string $index, array $row, array $term_options): string {
		$term_id = isset($row['term_id']) ? (int) $row['term_id'] : 0;
		$attachment_id = isset($row['attachment_id']) ? (int) $row['attachment_id'] : 0;
		$attachment = cmx_carent_fotos_attachment_payload($attachment_id);

		$empty_markup = cmx_carent_fotos_empty_markup();

		\ob_start();
		?>
		<div class="cmx-carent-fotos-row" data-index="<?php echo \esc_attr($index); ?>">
			<div class="cmx-carent-fotos-row-head">
				<div class="cmx-carent-fotos-row-title"><?php echo \esc_html__('Foto', 'cmx-misbuero'); ?></div>
				<button type="button" class="button-link-delete cmx-carent-fotos-row-remove"><?php echo \esc_html__('Entfernen', 'cmx-misbuero'); ?></button>
			</div>
			<div class="cmx-carent-fotos-row-grid">
				<div class="cmx-carent-fotos-field">
					<!-- <label><?php echo \esc_html__('Foto-Taxonomie', 'cmx-misbuero'); ?></label> -->
					<select class="widefat" name="cmx_carent_fotos_rows[<?php echo \esc_attr($index); ?>][term_id]">
						<option value="0"><?php echo \esc_html__('Typ wählen', 'cmx-misbuero'); ?></option>
						<?php foreach ($term_options as $option) : ?>
							<option value="<?php echo (int) $option['id']; ?>"<?php selected($term_id, (int) $option['id']); ?>>
								<?php echo \esc_html((string) $option['label']); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="cmx-carent-fotos-field">
					<!-- <label><?php echo \esc_html__('Bild', 'cmx-misbuero'); ?></label> -->
					<input type="hidden" class="cmx-carent-fotos-attachment-id" name="cmx_carent_fotos_rows[<?php echo \esc_attr($index); ?>][attachment_id]" value="<?php echo \esc_attr((string) $attachment['id']); ?>">
					<input type="file" class="cmx-carent-fotos-file-input" accept="image/*" style="display:none;">
					<div class="cmx-carent-fotos-media">
						<div class="cmx-carent-fotos-preview" role="button" tabindex="0" aria-label="<?php echo \esc_attr__('Foto hochladen', 'cmx-misbuero'); ?>">
							<button type="button" class="cmx-carent-fotos-image-remove"<?php echo $attachment['id'] > 0 ? '' : ' style="display:none;"'; ?> aria-label="<?php echo \esc_attr__('Bild entfernen', 'cmx-misbuero'); ?>">
								<span class="dashicons dashicons-trash" aria-hidden="true"></span>
							</button>
							<?php if ($attachment['preview_url'] !== '') : ?>
								<img src="<?php echo \esc_url((string) $attachment['preview_url']); ?>" alt="" class="cmx-carent-fotos-preview-image">
							<?php else : ?>
								<?php echo $empty_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endif; ?>
						</div>
						<p class="cmx-carent-fotos-file">
							<?php if ($attachment['file_url'] !== '' && $attachment['label'] !== '') : ?>
								<a href="<?php echo \esc_url((string) $attachment['file_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html((string) $attachment['label']); ?></a>
							<?php endif; ?>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php

		return (string) \ob_get_clean();
	}
}

\add_action('admin_enqueue_scripts', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== 'carent') {
		return;
	}
});

\add_action('admin_head', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== 'carent') {
		return;
	}

	$taxonomy = cmx_carent_fotos_taxonomy();
	echo '<style>#' . \esc_html($taxonomy . 'div') . ',#' . \esc_html('tagsdiv-' . $taxonomy) . '{display:none !important;}</style>';
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_carent_fotos_section')) {
	function cmx_render_carent_fotos_section(\WP_Post $post, string $host_id = 'cmx_carent_fotos_rows_box', bool $compact = false): void {
		$rows = cmx_carent_fotos_rows((int) $post->ID);
		if ($rows === []) {
			$rows = [['term_id' => 0, 'attachment_id' => 0]];
		}
		$term_options = cmx_carent_fotos_term_options();
		$empty_attachment = cmx_carent_fotos_attachment_payload(0);
		$ajax_nonce = (string) \wp_create_nonce('cmx_carent_fotos_upload');
		$template_markup = cmx_carent_fotos_row_markup('__INDEX__', ['term_id' => 0, 'attachment_id' => 0], $term_options);

		\wp_nonce_field('cmx_carent_fotos_rows_save', 'cmx_carent_fotos_rows_nonce');

		$host_selector = '#' . \esc_attr($host_id);
		$row_grid_columns = $compact ? '1fr' : 'minmax(240px,320px) minmax(0,1fr)';
		$preview_min_height = $compact ? '140px' : '180px';

		echo '<style>
		' . $host_selector . ' .cmx-carent-fotos-box{display:grid;gap:14px}
		.cmx-carent-fotos-help{margin:0;color:#646970}
		.cmx-carent-fotos-rows{display:grid;gap:14px}
		.cmx-carent-fotos-row{border:1px solid #dcdcde;border-radius:10px;background:#fff;padding:14px}
		.cmx-carent-fotos-row-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px}
		.cmx-carent-fotos-row-title{font-weight:600}
		.cmx-carent-fotos-row-grid{display:grid;gap:14px;grid-template-columns:' . $row_grid_columns . '}
		.cmx-carent-fotos-field label{display:block;margin:0 0 6px;font-weight:600}
		.cmx-carent-fotos-media{display:grid;gap:10px}
		.cmx-carent-fotos-preview{position:relative;display:flex;align-items:center;justify-content:center;min-height:' . $preview_min_height . ';padding:12px;border:1px dashed #c3c4c7;border-radius:8px;background:#f6f7f7;cursor:pointer}
		.cmx-carent-fotos-preview.is-busy{opacity:.65}
		.cmx-carent-fotos-preview.is-dragover{border-color:#2271b1;background:#eef6ff}
		.cmx-carent-fotos-preview-image{display:block;max-width:100%;max-height:240px;height:auto;border-radius:6px}
		.cmx-carent-fotos-empty{color:#646970;text-align:center}
		.cmx-carent-fotos-image-remove{position:absolute;top:8px;right:8px;display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:0;border-radius:999px;background:rgba(255,255,255,.94);color:#b32d2e;box-shadow:0 1px 3px rgba(0,0,0,.16);cursor:pointer;z-index:2}
		.cmx-carent-fotos-image-remove:hover{background:#fff;color:#8a2424}
		.cmx-carent-fotos-image-remove .dashicons{width:18px;height:18px;font-size:18px;line-height:18px}
		.cmx-carent-fotos-file{margin:0;color:#646970;word-break:break-word}
		.cmx-carent-fotos-footer{display:flex;align-items:center;justify-content:flex-start}
		@media (max-width: 900px){
			.cmx-carent-fotos-row-grid{grid-template-columns:1fr}
		}
		</style>';

		echo '<div id="' . \esc_attr($host_id) . '" class="cmx-carent-fotos-box">';
		echo '<p class="cmx-carent-fotos-help">' . \esc_html__('Beliebige Fotos hinzufügen. Pro Zeile ein Typ und dann das passende Bild wählen.', 'cmx-misbuero') . '</p>';
		echo '<div id="' . \esc_attr($host_id) . '_rows" class="cmx-carent-fotos-rows">';
		foreach ($rows as $index => $row) {
			echo cmx_carent_fotos_row_markup((string) $index, $row, $term_options); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
		echo '<div class="cmx-carent-fotos-footer"><button type="button" class="button button-primary" id="' . \esc_attr($host_id) . '_add_row">' . \esc_html__('Weitere Fotos hinzufügen', 'cmx-misbuero') . '</button></div>';
		echo '<template id="' . \esc_attr($host_id) . '_row_template">' . $template_markup . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';

		echo '<script>
		(function(){
			var root = document.getElementById(' . \wp_json_encode($host_id . '_rows') . ');
			var addButton = document.getElementById(' . \wp_json_encode($host_id . '_add_row') . ');
			var template = document.getElementById(' . \wp_json_encode($host_id . '_row_template') . ');
			if (!root || !addButton || !template || root.dataset.cmxFotosBound === "1") return;
			root.dataset.cmxFotosBound = "1";

			var rowIndex = root.querySelectorAll(".cmx-carent-fotos-row").length;
			var emptyAttachment = ' . \wp_json_encode($empty_attachment) . ';
			var ajaxUrl = ' . \wp_json_encode((string) \admin_url('admin-ajax.php')) . ';
			var ajaxNonce = ' . \wp_json_encode($ajax_nonce) . ';
			var postId = ' . (int) $post->ID . ';

			function renderAttachment(row, payload){
				var attachmentInput = row.querySelector(".cmx-carent-fotos-attachment-id");
				var preview = row.querySelector(".cmx-carent-fotos-preview");
				var fileNode = row.querySelector(".cmx-carent-fotos-file");
				var removeImageButton = row.querySelector(".cmx-carent-fotos-image-remove");
				var data = payload || emptyAttachment;
				var previewUrl = String(data.preview_url || "");
				var fileUrl = String(data.file_url || "");
				var label = String(data.label || "");
				var attachmentId = String(data.id || "");

				if (attachmentInput) {
					attachmentInput.value = attachmentId;
				}
				if (preview) {
					if (previewUrl !== "") {
						preview.innerHTML = "<button type=\"button\" class=\"cmx-carent-fotos-image-remove\" aria-label=\"Bild entfernen\"><span class=\"dashicons dashicons-trash\" aria-hidden=\"true\"><\\/span><\\/button><img src=\"" + previewUrl.replace(/\"/g, "&quot;") + "\" alt=\"\" class=\"cmx-carent-fotos-preview-image\">";
					} else {
						preview.innerHTML = "<button type=\"button\" class=\"cmx-carent-fotos-image-remove\" aria-label=\"Bild entfernen\" style=\"display:none;\"><span class=\"dashicons dashicons-trash\" aria-hidden=\"true\"><\\/span><\\/button>' . \str_replace('"', '\"', \str_replace(["\r", "\n"], '', cmx_carent_fotos_empty_markup())) . '";
					}
					preview.classList.remove("is-busy");
					preview.classList.remove("is-dragover");
				}
				if (fileNode) {
					if (fileUrl !== "" && label !== "") {
						fileNode.innerHTML = "<a href=\"" + fileUrl.replace(/\"/g, "&quot;") + "\" target=\"_blank\" rel=\"noopener noreferrer\">" + label.replace(/</g, "&lt;").replace(/>/g, "&gt;") + "<\\/a>";
					} else {
						fileNode.textContent = "";
					}
				}
				removeImageButton = row.querySelector(".cmx-carent-fotos-image-remove");
				if (removeImageButton) {
					removeImageButton.style.display = attachmentId !== "" && attachmentId !== "0" ? "" : "none";
				}
			}

			function setBusy(row, text){
				var preview = row.querySelector(".cmx-carent-fotos-preview");
				var fileNode = row.querySelector(".cmx-carent-fotos-file");
				if (preview) {
					preview.classList.add("is-busy");
				}
				if (fileNode) {
					fileNode.textContent = text || "Upload läuft...";
				}
			}

			function setIdle(row){
				var preview = row.querySelector(".cmx-carent-fotos-preview");
				if (preview) {
					preview.classList.remove("is-busy");
					preview.classList.remove("is-dragover");
				}
			}

			function uploadFile(row, file){
				if (!row || !file) return;
				if (String(file.type || "").indexOf("image/") !== 0) {
					var fileNode = row.querySelector(".cmx-carent-fotos-file");
					if (fileNode) {
						fileNode.textContent = "Bitte nur Bilddateien hochladen.";
					}
					return;
				}

				var data = new FormData();
				data.append("action", "cmx_carent_fotos_upload");
				data.append("nonce", ajaxNonce);
				data.append("post_id", String(postId || 0));
				data.append("file", file);

				setBusy(row, "Upload läuft: " + (file.name || ""));

				fetch(ajaxUrl, {
					method: "POST",
					credentials: "same-origin",
					body: data
				}).then(function(response){
					return response.json();
				}).then(function(json){
					if (!json || !json.success || !json.data) {
						var msg = (json && json.data && json.data.message) ? String(json.data.message) : "Upload fehlgeschlagen.";
						var fileNode = row.querySelector(".cmx-carent-fotos-file");
						if (fileNode) {
							fileNode.textContent = msg;
						}
						setIdle(row);
						return;
					}
					renderAttachment(row, {
						id: json.data.id || 0,
						preview_url: json.data.url || "",
						file_url: json.data.file_url || "",
						label: json.data.label || ""
					});
				}).catch(function(){
					var fileNode = row.querySelector(".cmx-carent-fotos-file");
					if (fileNode) {
						fileNode.textContent = "Upload fehlgeschlagen.";
					}
					setIdle(row);
				});
			}

			function initRow(row){
				if (!row || row.dataset.cmxFotosRowBound === "1") return;
				row.dataset.cmxFotosRowBound = "1";

				var fileInput = row.querySelector(".cmx-carent-fotos-file-input");
				var preview = row.querySelector(".cmx-carent-fotos-preview");
				var removeButton = row.querySelector(".cmx-carent-fotos-row-remove");

				if (preview && fileInput) {
					preview.addEventListener("click", function(event){
						if (event.target && event.target.closest(".cmx-carent-fotos-image-remove")) {
							return;
						}
						event.preventDefault();
						fileInput.click();
					});

					preview.addEventListener("keydown", function(event){
						if (event.key !== "Enter" && event.key !== " ") return;
						event.preventDefault();
						fileInput.click();
					});

					preview.addEventListener("dragover", function(event){
						event.preventDefault();
						preview.classList.add("is-dragover");
					});

					preview.addEventListener("dragleave", function(event){
						event.preventDefault();
						preview.classList.remove("is-dragover");
					});

					preview.addEventListener("drop", function(event){
						event.preventDefault();
						preview.classList.remove("is-dragover");
						var files = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files : [];
						if (files.length) {
							uploadFile(row, files[0]);
						}
					});
				}

				if (fileInput) {
					fileInput.addEventListener("change", function(){
						if (fileInput.files && fileInput.files[0]) {
							uploadFile(row, fileInput.files[0]);
						}
						fileInput.value = "";
					});
				}

				row.addEventListener("click", function(event){
					var removeImageButton = event.target && event.target.closest ? event.target.closest(".cmx-carent-fotos-image-remove") : null;
					if (!removeImageButton) return;
					event.preventDefault();
					event.stopPropagation();
					renderAttachment(row, emptyAttachment);
				});

				if (removeButton) {
					removeButton.addEventListener("click", function(event){
						event.preventDefault();
						row.remove();
						if (!root.querySelector(".cmx-carent-fotos-row")) {
							addRow();
						}
					});
				}
			}

			function addRow(){
				var html = String(template.innerHTML || "").replace(/__INDEX__/g, String(rowIndex));
				rowIndex += 1;
				var wrapper = document.createElement("div");
				wrapper.innerHTML = html.trim();
				var row = wrapper.firstElementChild;
				if (!row) return;
				root.appendChild(row);
				initRow(row);
				renderAttachment(row, emptyAttachment);
			}

			root.querySelectorAll(".cmx-carent-fotos-row").forEach(function(row){
				initRow(row);
			});

			addButton.addEventListener("click", function(event){
				event.preventDefault();
				addRow();
			});
		})();
		</script>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_carent_fotos_metabox')) {
	function cmx_render_carent_fotos_metabox(\WP_Post $post): void {
		cmx_render_carent_fotos_section($post, 'cmx_carent_fotos_rows_box', false);
	}
}

\add_action('save_post_carent', function (int $post_id, \WP_Post $post): void {
	if ((string) $post->post_type !== 'carent') {
		return;
	}

	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	if (!isset($_POST['cmx_carent_fotos_rows_nonce']) || !\wp_verify_nonce((string) \wp_unslash($_POST['cmx_carent_fotos_rows_nonce']), 'cmx_carent_fotos_rows_save')) {
		return;
	}

	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

	$taxonomy = cmx_carent_fotos_taxonomy();
	$raw_rows = isset($_POST['cmx_carent_fotos_rows']) ? \wp_unslash($_POST['cmx_carent_fotos_rows']) : [];
	$rows = [];
	$term_ids = [];

	foreach ((array) $raw_rows as $raw_row) {
		if (!\is_array($raw_row)) {
			continue;
		}

		$term_id = isset($raw_row['term_id']) ? (int) $raw_row['term_id'] : 0;
		$attachment_id = isset($raw_row['attachment_id']) ? (int) $raw_row['attachment_id'] : 0;

		if ($term_id > 0) {
			$term = \get_term($term_id, $taxonomy);
			if (!$term || \is_wp_error($term)) {
				$term_id = 0;
			}
		}

		if ($attachment_id > 0) {
			$mime = (string) \get_post_mime_type($attachment_id);
			if (\strpos($mime, 'image/') !== 0) {
				$attachment_id = 0;
			}
		}

		if ($term_id <= 0 && $attachment_id <= 0) {
			continue;
		}

		$rows[] = [
			'term_id' => $term_id,
			'attachment_id' => $attachment_id,
		];

		if ($term_id > 0) {
			$term_ids[] = $term_id;
		}
	}

	if ($rows === []) {
		\delete_post_meta($post_id, CMX_CARENT_FOTOS_ROWS_META);
	} else {
		\update_post_meta($post_id, CMX_CARENT_FOTOS_ROWS_META, $rows);
	}

	$term_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $term_ids))));
	\wp_set_post_terms($post_id, $term_ids, $taxonomy, false);
}, 20, 2);

\add_action('wp_ajax_cmx_carent_fotos_upload', function (): void {
	if (!\current_user_can('upload_files')) {
		\wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
	}

	$nonce = isset($_POST['nonce']) ? (string) \wp_unslash($_POST['nonce']) : '';
	if (!\wp_verify_nonce($nonce, 'cmx_carent_fotos_upload')) {
		\wp_send_json_error(['message' => 'Sicherheitsprüfung fehlgeschlagen.'], 403);
	}

	$post_id = isset($_POST['post_id']) ? (int) \wp_unslash($_POST['post_id']) : 0;
	if ($post_id > 0 && \get_post_type($post_id) !== 'carent') {
		\wp_send_json_error(['message' => 'Ungültiger Eintrag.'], 400);
	}

	if (empty($_FILES['file']) || !isset($_FILES['file']['tmp_name'])) {
		\wp_send_json_error(['message' => 'Keine Datei empfangen.'], 400);
	}

	$file_type = \wp_check_filetype_and_ext((string) $_FILES['file']['tmp_name'], (string) $_FILES['file']['name']);
	$mime_type = (string) ($file_type['type'] ?? '');
	if (!\str_starts_with($mime_type, 'image/')) {
		\wp_send_json_error(['message' => 'Bitte nur Bilddateien hochladen.'], 400);
	}

	require_once \ABSPATH . 'wp-admin/includes/file.php';
	require_once \ABSPATH . 'wp-admin/includes/media.php';
	require_once \ABSPATH . 'wp-admin/includes/image.php';

	$attachment_id = \media_handle_upload('file', $post_id > 0 ? $post_id : 0);
	if (\is_wp_error($attachment_id)) {
		\wp_send_json_error(['message' => (string) $attachment_id->get_error_message()], 500);
	}

	$image_url = (string) \wp_get_attachment_image_url((int) $attachment_id, 'medium');
	if ($image_url === '') {
		$image_url = (string) \wp_get_attachment_url((int) $attachment_id);
	}
	$file_url = (string) \wp_get_attachment_url((int) $attachment_id);

	\wp_send_json_success([
		'id' => (int) $attachment_id,
		'url' => $image_url,
		'file_url' => $file_url,
		'label' => (string) \basename((string) \get_attached_file((int) $attachment_id)),
	]);
});
