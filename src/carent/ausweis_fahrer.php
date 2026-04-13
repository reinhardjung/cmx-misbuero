<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_FUEHRERAUSWEIS_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_FUEHRERAUSWEIS_META', '_cmx_carent_fuehrerausweis_attachment_id');
}

\add_action('add_meta_boxes', function (): void {
	\add_meta_box(
		'cmx_carent_fuehrerausweis_box',
		\__('Führerausweis', 'cmx-misbuero'),
		__NAMESPACE__ . '\\cmx_render_carent_fuehrerausweis_metabox',
		'carent',
		'side',
		'default'
	);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_carent_fuehrerausweis_metabox')) {
	function cmx_render_carent_fuehrerausweis_metabox(\WP_Post $post): void {
		$attachment_id = (int) \get_post_meta($post->ID, CMX_CARENT_FUEHRERAUSWEIS_META, true);
		$image_url = $attachment_id > 0 ? (string) \wp_get_attachment_image_url($attachment_id, 'medium') : '';
		$attachment_url = $attachment_id > 0 ? (string) \wp_get_attachment_url($attachment_id) : '';
		$filename = $attachment_id > 0 ? (string) \basename((string) \get_attached_file($attachment_id)) : '';
		$ajax_nonce = (string) \wp_create_nonce('cmx_carent_fuehrerausweis_upload');

		\wp_nonce_field('cmx_carent_fuehrerausweis_save', 'cmx_carent_fuehrerausweis_nonce');

		echo '<div class="cmx-carent-fuehrerausweis-box">';
		echo '<input type="hidden" name="cmx_carent_fuehrerausweis_attachment_id" id="cmx_carent_fuehrerausweis_attachment_id" value="' . \esc_attr((string) $attachment_id) . '">';
		echo '<input type="file" id="cmx_carent_fuehrerausweis_file" accept="image/*" style="display:none;">';
		echo '<div id="cmx_carent_fuehrerausweis_dropzone" style="display:block;width:100%;cursor:pointer;">';
		if ($image_url !== '') {
			echo '<img id="cmx_carent_fuehrerausweis_preview" src="' . \esc_url($image_url) . '" alt="" style="display:block;width:100%;height:auto;border:1px solid #dcdcde;border-radius:6px;">';
		} else {
			echo '<div id="cmx_carent_fuehrerausweis_preview" style="display:flex;align-items:center;justify-content:center;min-height:140px;padding:12px;text-align:center;border:1px dashed #c3c4c7;border-radius:6px;color:#646970;background:#f6f7f7;">' . \esc_html__('Foto hier ablegen oder anklicken.', 'cmx-misbuero') . '</div>';
		}
		echo '</div>';
		echo '<p id="cmx_carent_fuehrerausweis_status" style="margin:10px 0 0;color:#50575e;">';
		if ($filename !== '' && $attachment_url !== '') {
			echo '<a href="' . \esc_url($attachment_url) . '" target="_blank" rel="noopener noreferrer">' . \esc_html($filename) . '</a>';
		} elseif ($filename !== '') {
			echo \esc_html($filename);
		}
		echo '</p>';
		echo '<p style="margin:8px 0 0;">';
		echo '<button type="button" class="button button-link-delete" id="cmx_carent_fuehrerausweis_remove"' . ($attachment_id > 0 ? '' : ' style="display:none;"') . '>' . \esc_html__('Entfernen', 'cmx-misbuero') . '</button>';
		echo '</p>';
		echo '</div>';

		echo '<script>
		(function(){
			var attachmentInput = document.getElementById("cmx_carent_fuehrerausweis_attachment_id");
			var fileInput = document.getElementById("cmx_carent_fuehrerausweis_file");
			var dropzone = document.getElementById("cmx_carent_fuehrerausweis_dropzone");
			var preview = document.getElementById("cmx_carent_fuehrerausweis_preview");
			var status = document.getElementById("cmx_carent_fuehrerausweis_status");
			var removeButton = document.getElementById("cmx_carent_fuehrerausweis_remove");
			if (!attachmentInput || !fileInput || !dropzone || !preview || !status || !removeButton) return;

			var ajaxUrl = ' . \wp_json_encode((string) \admin_url('admin-ajax.php')) . ';
			var ajaxNonce = ' . \wp_json_encode($ajax_nonce) . ';
			var postId = ' . (int) $post->ID . ';
			var emptyText = ' . \wp_json_encode((string) \__('Foto hier ablegen oder anklicken.', 'cmx-misbuero')) . ';

			function setIdle(){
				dropzone.style.opacity = "1";
			}

			function setBusy(text){
				status.textContent = text || "Upload läuft...";
				dropzone.style.opacity = ".6";
			}

			function renderStatus(label, fileUrl){
				var safeLabel = String(label || "");
				var safeUrl = String(fileUrl || "");
				if (!safeLabel) {
					status.textContent = "";
					return;
				}
				if (!safeUrl) {
					status.textContent = safeLabel;
					return;
				}
				status.innerHTML = "";
				var link = document.createElement("a");
				link.href = safeUrl;
				link.target = "_blank";
				link.rel = "noopener noreferrer";
				link.textContent = safeLabel;
				status.appendChild(link);
			}

			function renderEmpty(){
				attachmentInput.value = "";
				preview.outerHTML = "<div id=\"cmx_carent_fuehrerausweis_preview\" style=\"display:flex;align-items:center;justify-content:center;min-height:140px;padding:12px;text-align:center;border:1px dashed #c3c4c7;border-radius:6px;color:#646970;background:#f6f7f7;\">" + emptyText + "</div>";
				preview = document.getElementById("cmx_carent_fuehrerausweis_preview");
				status.textContent = "";
				removeButton.style.display = "none";
				setIdle();
			}

			function renderImage(id, url, label, fileUrl){
				attachmentInput.value = String(id || "");
				preview.outerHTML = "<img id=\"cmx_carent_fuehrerausweis_preview\" src=\"" + String(url || "").replace(/\"/g, "&quot;") + "\" alt=\"\" style=\"display:block;width:100%;height:auto;border:1px solid #dcdcde;border-radius:6px;\">";
				preview = document.getElementById("cmx_carent_fuehrerausweis_preview");
				renderStatus(label, fileUrl);
				removeButton.style.display = "";
				setIdle();
			}

			function uploadFile(file){
				if (!file) return;
				if (String(file.type || "").indexOf("image/") !== 0) {
					status.textContent = "Bitte nur Bilddateien hochladen.";
					return;
				}

				var data = new FormData();
				data.append("action", "cmx_carent_fuehrerausweis_upload");
				data.append("nonce", ajaxNonce);
				data.append("post_id", String(postId || 0));
				data.append("file", file);

				setBusy("Upload läuft: " + (file.name || ""));

				fetch(ajaxUrl, {
					method: "POST",
					credentials: "same-origin",
					body: data
				}).then(function(r){
					return r.json();
				}).then(function(json){
					if (!json || !json.success || !json.data) {
						var msg = (json && json.data && json.data.message) ? String(json.data.message) : "Upload fehlgeschlagen.";
						status.textContent = msg;
						setIdle();
						return;
					}
					renderImage(json.data.id || "", json.data.url || "", json.data.label || "", json.data.file_url || "");
				}).catch(function(){
					status.textContent = "Upload fehlgeschlagen.";
					setIdle();
				});
			}

			dropzone.addEventListener("click", function(e){
				if (e.target && e.target.id === "cmx_carent_fuehrerausweis_remove") return;
				fileInput.click();
			});

			fileInput.addEventListener("change", function(){
				if (fileInput.files && fileInput.files[0]) {
					uploadFile(fileInput.files[0]);
				}
				fileInput.value = "";
			});

			dropzone.addEventListener("dragover", function(e){
				e.preventDefault();
				dropzone.style.opacity = ".75";
			});

			dropzone.addEventListener("dragleave", function(e){
				e.preventDefault();
				setIdle();
			});

			dropzone.addEventListener("drop", function(e){
				e.preventDefault();
				setIdle();
				var files = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files : [];
				if (files.length) {
					uploadFile(files[0]);
				}
			});

			removeButton.addEventListener("click", function(e){
				e.preventDefault();
				e.stopPropagation();
				renderEmpty();
			});
		})();
		</script>';
	}
}

\add_action('wp_ajax_cmx_carent_fuehrerausweis_upload', function (): void {
	if (!\current_user_can('upload_files')) {
		\wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
	}

	$nonce = isset($_POST['nonce']) ? (string) \wp_unslash($_POST['nonce']) : '';
	if (!\wp_verify_nonce($nonce, 'cmx_carent_fuehrerausweis_upload')) {
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

\add_action('save_post_carent', function (int $post_id, \WP_Post $post): void {
	if ($post->post_type !== 'carent') {
		return;
	}
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (!isset($_POST['cmx_carent_fuehrerausweis_nonce']) || !\wp_verify_nonce((string) $_POST['cmx_carent_fuehrerausweis_nonce'], 'cmx_carent_fuehrerausweis_save')) {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

	$attachment_id = isset($_POST['cmx_carent_fuehrerausweis_attachment_id'])
		? (int) \wp_unslash($_POST['cmx_carent_fuehrerausweis_attachment_id'])
		: 0;

	if ($attachment_id <= 0) {
		\delete_post_meta($post_id, CMX_CARENT_FUEHRERAUSWEIS_META);
		return;
	}

	$mime = (string) \get_post_mime_type($attachment_id);
	if (!\str_starts_with($mime, 'image/')) {
		return;
	}

	\update_post_meta($post_id, CMX_CARENT_FUEHRERAUSWEIS_META, $attachment_id);
}, 10, 2);
