<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Dokumente-Metabox für alle CPTs (außer "dokumente")
 * Upload: PDF/PNG/JPG -> /archiv/{jahr}/dokumente/
 */

if (!\defined(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')) {
	\define(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META', '_cmx_dokumente_uploads');
}
if (!\defined(__NAMESPACE__ . '\\CMX_DOK_SELF_META')) {
	\define(__NAMESPACE__ . '\\CMX_DOK_SELF_META', '_cmx_dokumente_files');
}
if (!\defined(__NAMESPACE__ . '\\CMX_DOK_UPLOAD_NOTICE_PENDING_META')) {
	\define(__NAMESPACE__ . '\\CMX_DOK_UPLOAD_NOTICE_PENDING_META', '_cmx_dok_upload_notice_pending');
}
if (!\defined(__NAMESPACE__ . '\\CMX_DOK_UPLOAD_NOTICE_FLASH_USER_META')) {
	\define(__NAMESPACE__ . '\\CMX_DOK_UPLOAD_NOTICE_FLASH_USER_META', '_cmx_dok_upload_notice_flash');
}

function cmx_dok_upload_target_dir(string $post_type, int $year): array {
	if ($post_type === 'scanner') {
		$base = WP_CONTENT_DIR . '/uploads/misbuero/scanner';
		$url  = content_url('/uploads/misbuero/scanner');
	} else {
		$base = WP_CONTENT_DIR . '/uploads/misbuero/archiv/' . $year . '/dokumente';
		$url  = content_url('/uploads/misbuero/archiv/' . $year . '/dokumente');
	}
	if (!\is_dir($base)) {
		\wp_mkdir_p($base);
	}
	return [$base, $url];
}

function cmx_dok_sanitize_title_from_filename(string $filename): string {
	$base = (string) \pathinfo($filename, \PATHINFO_FILENAME);
	$base = \wp_strip_all_tags($base);
	$base = \preg_replace('/[_\-]+/', ' ', $base);
	$base = \preg_replace('/\s+/', ' ', (string) $base);
	$base = \trim((string) $base);
	$base = \sanitize_text_field($base);
	return $base;
}

function cmx_dok_is_placeholder_post_title(string $title): bool {
	$title = \trim((string) \wp_strip_all_tags($title));
	if ($title === '') {
		return true;
	}

	$normalized = \strtolower($title);
	$normalized = \str_replace(['_', ' '], '-', $normalized);

	return \str_contains($normalized, 'automatisch-gespeicherter-entwurf')
		|| \str_contains($normalized, 'auto-draft')
		|| \str_contains($normalized, 'autodraft');
}

function cmx_dok_is_allowed_post_type(string $post_type): bool {
	if ($post_type === 'dokumente') return true;
	$obj = \get_post_type_object($post_type);
	if (!$obj || empty($obj->show_ui)) return false;
	if (!empty($obj->_builtin)) return false;
	return true;
}

\add_action('add_meta_boxes', function($post_type) {
	if (!cmx_dok_is_allowed_post_type((string)$post_type)) return;
	$box_title = 'als Scan';
	if ((string) $post_type !== 'scanner') {
		$dokumente_url = \admin_url('edit.php?post_type=dokumente');
		$box_title = '<a href="' . \esc_url($dokumente_url) . '" target="_blank" rel="noopener noreferrer" style="text-decoration:none;font-weight:700;font-size:14px;line-height:1.2;" onclick="event.stopPropagation();">Dokumente</a>';
	}
	\add_meta_box(
		'cmx_dokumente_box',
		$box_title,
		__NAMESPACE__ . '\\cmx_render_dokumente_upload_box',
		$post_type,
		'side',
		'high'
	);
}, 10, 1);

function cmx_render_dokumente_upload_box(\WP_Post $post): void {
	$nonce = \wp_create_nonce('cmx_dokumente_upload');
	$is_dokumente = ($post->post_type === 'dokumente');
	$is_scanner = ($post->post_type === 'scanner');
	$docs = [];
	if ($is_dokumente) {
		$docs = (array) \get_post_meta($post->ID, CMX_DOK_SELF_META, true);
		$docs = array_values(array_filter($docs, function($v){ return $v !== '' && $v !== null; }));
	} elseif ($is_scanner) {
		$source_rel = (string) \get_post_meta($post->ID, '_cmx_scanner_source_rel', true);
		if ($source_rel === '' && \function_exists(__NAMESPACE__ . '\\cmx_scanner_sync_get_source_rel_for_post')) {
			$source_rel = (string) cmx_scanner_sync_get_source_rel_for_post((int) $post->ID);
		}
		$source_rel = \ltrim(\str_replace('\\', '/', $source_rel), '/');
		$docs = $source_rel !== '' ? [$source_rel] : [];
	} else {
		$docs = (array) \get_post_meta($post->ID, CMX_DOK_UPLOADS_META, true);
		$docs = array_values(array_filter(array_map('intval', $docs)));
	}

	echo '<div id="cmx-dokumente-upload-box">';
	echo '<div id="cmx-dokumente-drop" style="border:2px dashed #ccd0d4;padding:10px;text-align:center;background:#fafafa;cursor:pointer;">';
	echo '<strong>Datei hier ablegen</strong><br><small>PDF, PNG, JPG, CSV</small>';
	echo '</div>';
	echo '<input type="file" id="cmx-dokumente-file" style="display:none" multiple accept=".pdf,.png,.jpg,.jpeg">';
	echo '<div id="cmx-dokumente-list" style="margin-top:8px;max-height:160px;overflow:auto;"></div>';
	echo '</div>';

	if ($docs) {
		echo '<ul id="cmx-dokumente-existing" style="margin:6px 0 0 0;padding:0;list-style:none;max-height:160px;overflow:auto;width:100%;">';
		foreach ($docs as $doc_entry) {
			$doc_id = ($is_dokumente || $is_scanner) ? 0 : (int) $doc_entry;
			$file_rel = '';
			$edit_url = ($is_dokumente || $is_scanner) ? '' : \get_edit_post_link((int)$doc_id, 'raw');
			if ($is_dokumente) {
				if (is_numeric($doc_entry)) {
					$att_id = (int) $doc_entry;
					$file_rel = (string) \get_post_meta($att_id, '_wp_attached_file', true);
				} else {
					$file_rel = ltrim((string) $doc_entry, '/');
				}
			} elseif ($is_scanner) {
				$file_rel = \ltrim((string) $doc_entry, '/');
			} else {
				$file_rel = (string) \get_post_meta($doc_id, '_cmx_dokumente_file_path', true);
				if ($file_rel === '') {
					$att_id = (int) \get_post_meta($doc_id, '_cmx_dokumente_attachment_id', true);
					if ($att_id) {
						$file_rel = (string) \get_post_meta($att_id, '_wp_attached_file', true);
					}
				}
			}
			if ($file_rel === '') {
				continue;
			}
			$file_abs = WP_CONTENT_DIR . '/uploads/' . $file_rel;
			if (!is_file($file_abs)) {
				continue;
			}
			$url = content_url('/uploads/' . $file_rel);
			$file_base = basename($file_rel);
			$label = $file_base ?: ($doc_id ? (\get_the_title($doc_id) ?: ('#' . $doc_id)) : $file_rel);
			$data_attr = ($is_dokumente || $is_scanner)
				? 'data-path="' . \esc_attr($file_rel) . '"'
				: 'data-doc-id="' . (int) $doc_id . '"';
			$grid_cols = ($is_dokumente || $is_scanner) ? '1fr 14px' : '18px 1fr 14px';
			echo '<li ' . $data_attr . ' style="display:grid;grid-template-columns:' . $grid_cols . ';align-items:center;gap:4px;width:100%;white-space:nowrap;">';
			if (!$is_dokumente && !$is_scanner) {
				if ($edit_url) {
					echo '<a href="' . \esc_url($edit_url) . '" target="_blank" rel="noopener noreferrer" title="Dokument bearbeiten" style="text-decoration:none;justify-self:start;">';
					echo '<span style="display:inline-block;padding:0 3px;border:1px solid #ccd0d4;border-radius:2px;font-size:10px;line-height:1.2;">D</span>';
					echo '</a>';
				} else {
					echo '<span style="display:inline-block;padding:0 3px;border:1px solid #ccd0d4;border-radius:2px;font-size:10px;line-height:1.2;justify-self:start;">D</span>';
				}
			}
			if ($url) {
				echo '<a href="' . \esc_url($url) . '" target="_blank" rel="noopener noreferrer" title="' . \esc_attr($label) . '" style="min-width:0;text-align:center;justify-self:stretch;overflow:hidden;text-overflow:ellipsis;">' . \esc_html($label) . '</a>';
			} else {
				echo '<span title="' . \esc_attr($label) . '" style="min-width:0;text-align:center;justify-self:stretch;overflow:hidden;text-overflow:ellipsis;">' . \esc_html($label) . '</span>';
			}
			echo ' <button type="button" class="button-link cmx-dok-remove" style="color:#b32d2e;justify-self:end;padding:0;line-height:1;">X</button>';
			echo '</li>';
		}
		echo '</ul>';
	}

		echo '<script>
		jQuery(function($){
			var $drop = $("#cmx-dokumente-drop");
			var $file = $("#cmx-dokumente-file");
			var $list = $("#cmx-dokumente-list");
			var postId = ' . (int)$post->ID . ';
			var postType = ' . \wp_json_encode((string) $post->post_type) . ';
			var isDok = ' . ($is_dokumente ? 'true' : 'false') . ';
			var nonce = ' . \wp_json_encode($nonce) . ';
			var ajaxurl = ' . \wp_json_encode(\admin_url('admin-ajax.php')) . ';
			var autoSaveTypes = ["artikel", "kontakte", "belege", "projekte", "dokumente", "scanner"];
			var shouldAutoSave = autoSaveTypes.indexOf(postType) !== -1;
			var saveTimer = null;
			var pendingUploadNotice = false;
			var noticeFieldId = "cmx-dok-upload-saved-flag";

			function markUploadSavedNotice(){
				pendingUploadNotice = true;
				var form = document.getElementById("post");
				if (!form) return;
				var input = document.getElementById(noticeFieldId);
				if (!input) {
					input = document.createElement("input");
					input.type = "hidden";
					input.id = noticeFieldId;
					input.name = "cmx_dok_upload_saved";
					form.appendChild(input);
				}
				input.value = "1";
			}

			function triggerPostSave(){
				if (!shouldAutoSave) return;
				var form = document.getElementById("post");
				if (!form) return;
				if (pendingUploadNotice) {
					markUploadSavedNotice();
				}
				var btn = form.querySelector("#publish:not([disabled]), #save-post:not([disabled])");
				if (btn && typeof btn.click === "function") {
					btn.click();
					return;
				}
				if (typeof form.requestSubmit === "function") {
					form.requestSubmit();
					return;
				}
				form.submit();
			}

			function queuePostSave(){
				if (!shouldAutoSave) return;
				if (saveTimer) window.clearTimeout(saveTimer);
				saveTimer = window.setTimeout(triggerPostSave, 450);
			}

		function uploadFile(file){
			var fd = new FormData();
			fd.append("action", "cmx_dokumente_upload_file");
			fd.append("post_id", postId);
			fd.append("nonce", nonce);
			fd.append("file", file);

			var $row = $("<div>").text("Upload: " + file.name);
			$list.append($row);

			$.ajax({
				url: ajaxurl,
				type: "POST",
				data: fd,
				processData: false,
				contentType: false,
					success: function(resp){
						if (resp && resp.success && resp.data) {
							var label = resp.data.label || file.name;
							if (resp.data.title) {
								$("#title").val(resp.data.title);
								$("#title-prompt-text").addClass("screen-reader-text");
							}
							if (resp.data.url) {
								$row.html("<a target=\"_blank\" rel=\"noopener noreferrer\"></a>");
								$row.find("a").attr("href", resp.data.url).text(label);
							} else {
								$row.text(label);
							}
							markUploadSavedNotice();
							queuePostSave();
						} else {
							$row.text("Fehler beim Upload: " + file.name);
						}
				},
				error: function(){
					$row.text("Fehler beim Upload: " + file.name);
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

		$("#cmx-dokumente-existing").on("click", ".cmx-dok-remove", function(){
			var $li = $(this).closest("li");
			var docId = $li.data("doc-id");
			var path = $li.data("path");
			if (!docId && !path) return;
			if (!confirm("Dokument entfernen?")) return;
				$.post(ajaxurl, { action:"cmx_dokumente_remove_file", post_id: postId, doc_id: docId, path: path, nonce: nonce }, function(resp){
					if (resp && resp.success) {
						$li.remove();
						queuePostSave();
					}
				});
			});
	});
	</script>';
}

/* =========================================================
 * AJAX Upload
 * ========================================================= */
\add_action('wp_ajax_cmx_dokumente_upload_file', __NAMESPACE__ . '\\cmx_dokumente_upload_file');
function cmx_dokumente_upload_file(): void {
	if (!\current_user_can('upload_files')) \wp_send_json_error(['message'=>'forbidden'], 403);
	$nonce = isset($_POST['nonce']) ? (string)$_POST['nonce'] : '';
	if (!\wp_verify_nonce($nonce, 'cmx_dokumente_upload')) \wp_send_json_error(['message'=>'bad_nonce'], 403);

	$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
	if ($post_id <= 0) \wp_send_json_error(['message'=>'bad_post'], 400);
	$post_type = (string) \get_post_type($post_id);
	if (!cmx_dok_is_allowed_post_type($post_type)) {
		\wp_send_json_error(['message'=>'bad_post_type'], 400);
	}
	$is_dokumente = ($post_type === 'dokumente');
	$is_scanner = ($post_type === 'scanner');
	$scanner_title = '';

	if (empty($_FILES['file']) || !isset($_FILES['file']['tmp_name'])) {
		\wp_send_json_error(['message'=>'no_file'], 400);
	}

	$allowed = ['pdf','png','jpg','jpeg'];
	if ($is_scanner) {
		$allowed[] = 'csv';
	}
	$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
	if (!in_array($ext, $allowed, true)) {
		\wp_send_json_error(['message'=>'bad_type'], 400);
	}
	$incoming_filename = (string) ($_FILES['file']['name'] ?? '');
	$incoming_title = cmx_dok_sanitize_title_from_filename($incoming_filename);

	if ($post_type === 'scanner') {
		$scanner_title = cmx_dok_sanitize_title_from_filename((string) ($_FILES['file']['name'] ?? ''));
		if ($scanner_title === '') {
			$scanner_title = \wp_date('ymd-His') . ' scanner';
		}
		\wp_update_post([
			'ID'         => $post_id,
			'post_title' => $scanner_title,
			'post_name'  => \sanitize_title($scanner_title),
		]);
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$ts = \current_time('timestamp');
	$year = \date('Y', $ts);
	$post_obj = $post_id > 0 ? \get_post($post_id) : null;
	$source_title = $post_id > 0 ? \get_the_title($post_id) : '';
	if (!\is_string($source_title)) {
		$source_title = '';
	}
	$source_title = \trim($source_title);
	if ($post_type === 'scanner' && $scanner_title !== '') {
		$source_title = $scanner_title;
	}
	if (!$is_dokumente) {
		$normalized_title = \strtolower($source_title);
		$normalized_title = \str_replace(['_', ' '], '-', $normalized_title);
		if ($source_title === '' || \stripos($normalized_title, 'automatisch-gespeicherter-entwurf') !== false) {
			$source_title = \sanitize_key($post_type);
		}
	}
	if ($source_title === '') {
		$source_title = \wp_date('ymd-His') . '_' . \sanitize_key($post_type);
	}
	$doc_title = $is_dokumente
		? ($incoming_title !== '' ? $incoming_title : (string) $source_title)
		: ($is_scanner
			? \wp_date('ymd-His')
			: ($incoming_title !== '' ? $incoming_title : ($source_title !== '' ? (string) $source_title : \wp_date('ymd-His'))));
	$response_title = '';
	if ($is_dokumente) {
		if ($doc_title === '') {
			$doc_title = \wp_date('ymd-His');
		}

		$current_title = $post_obj ? \trim((string) $post_obj->post_title) : '';
		$current_slug = $post_obj ? (string) $post_obj->post_name : '';
		$new_slug = \sanitize_title($doc_title);
		if ($current_title !== $doc_title || $current_slug !== $new_slug) {
			\wp_update_post([
				'ID' => $post_id,
				'post_title' => $doc_title,
				'post_name' => $new_slug,
			]);
		}
		$response_title = $doc_title;
	}

	if ($is_scanner) {
		$response_title = $scanner_title;
	} elseif (!$is_dokumente && \in_array($post_type, ['artikel', 'kontakte', 'projekte'], true)) {
		$parent_title = $incoming_title !== '' ? $incoming_title : $doc_title;
		$current_title = $post_obj ? \trim((string) $post_obj->post_title) : '';
		$current_slug = $post_obj ? (string) $post_obj->post_name : '';
		if ($parent_title !== '' && cmx_dok_is_placeholder_post_title($current_title)) {
			$new_slug = \sanitize_title($parent_title);
			\wp_update_post([
				'ID'         => $post_id,
				'post_title' => $parent_title,
				'post_name'  => ($current_slug === '' || $current_slug === 'auto-draft') ? $new_slug : $current_slug,
			]);
			$response_title = $parent_title;
		}
	}

	$doc_id = $post_id;
	if (!$is_dokumente && !$is_scanner) {
		$doc_id = \wp_insert_post([
			'post_type'   => 'dokumente',
			'post_title'  => $doc_title,
			'post_status' => 'publish',
		], true);
		if (\is_wp_error($doc_id) || !$doc_id) {
			\wp_send_json_error(['message'=>'doc_create_failed'], 500);
		}
	}

	$upload_filter = function($dirs) use ($year, $post_type) {
		[$base, $url] = cmx_dok_upload_target_dir((string) $post_type, (int) $year);
		$dirs['path']   = $base;
		$dirs['basedir']= $base;
		$dirs['url']    = $url;
		$dirs['baseurl']= $url;
		$dirs['subdir'] = '';
		return $dirs;
	};

	$no_sizes_filter = function($sizes) { return []; };
	$no_sizes_filter_simple = function($sizes) { return []; };
	$no_big_image = function() { return false; };

	\add_filter('upload_dir', $upload_filter);
	\add_filter('intermediate_image_sizes', $no_sizes_filter_simple);
	\add_filter('intermediate_image_sizes_advanced', $no_sizes_filter);
	\add_filter('big_image_size_threshold', $no_big_image, 10, 0);
	$allowed_mimes = [
		'pdf'  => 'application/pdf',
		'png'  => 'image/png',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
	];
	if ($is_scanner) {
		$allowed_mimes['csv'] = 'text/csv';
	}
	$uploaded = \wp_handle_upload($_FILES['file'], ['test_form' => false, 'mimes' => $allowed_mimes]);

	if (!isset($uploaded['file'])) {
		\remove_filter('big_image_size_threshold', $no_big_image, 10);
		\remove_filter('intermediate_image_sizes_advanced', $no_sizes_filter);
		\remove_filter('intermediate_image_sizes', $no_sizes_filter_simple);
		\remove_filter('upload_dir', $upload_filter);
		if (!$is_dokumente && !$is_scanner) {
			\wp_delete_post($doc_id, true);
		}
		\wp_send_json_error(['message'=>'upload_failed'], 500);
	}

	\remove_filter('big_image_size_threshold', $no_big_image, 10);
	\remove_filter('intermediate_image_sizes_advanced', $no_sizes_filter);
	\remove_filter('intermediate_image_sizes', $no_sizes_filter_simple);
	\remove_filter('upload_dir', $upload_filter);

	// Datei umbenennen: {Dokument-Titel}.ext (Scanner bleibt Sonderfall mit Suffix).
	$base_dir = dirname($uploaded['file']);
	$new_base = \sanitize_file_name($doc_title . '.' . $ext);
	if ($post_type === 'scanner') {
		$new_base = \sanitize_file_name($doc_title . '_' . $source_title . '.' . $ext);
	}
	$new_base = \wp_unique_filename($base_dir, $new_base);
	$new_path = $base_dir . '/' . $new_base;
	if ($new_path !== $uploaded['file']) {
		$renamed = @rename($uploaded['file'], $new_path);
		if ($renamed) {
			$uploaded['file'] = $new_path;
		}
	}
	$uploads_root = WP_CONTENT_DIR . '/uploads/';
	$rel = ltrim(str_replace($uploads_root, '', $uploaded['file']), '/');
	$file_url = \content_url('/uploads/' . $rel);

	// Beziehung speichern
	if ($is_dokumente) {
		$existing = (array) \get_post_meta($doc_id, CMX_DOK_SELF_META, true);
		$existing = array_values(array_filter($existing, function($v){ return $v !== '' && $v !== null; }));
		$existing[] = $rel;
		$existing = array_values(array_unique($existing));
		\update_post_meta($doc_id, CMX_DOK_SELF_META, $existing);
		\update_post_meta($doc_id, '_cmx_dokumente_file_path', $rel);
	} elseif ($is_scanner) {
		// Scanner-Uploads bleiben im CPT "scanner" und erzeugen keinen Dokumente-Post.
		\update_post_meta($post_id, '_cmx_scanner_source_rel', $rel);
		$mtime = @\filemtime($uploaded['file']);
		if (\is_int($mtime) && $mtime > 0) {
			\update_post_meta($post_id, '_cmx_scanner_uploaded_ts', $mtime);
		}
	} else {
		$rel_key = 'cmx_dokumente_rel_' . \sanitize_key($post_type);
		if (\defined(__NAMESPACE__ . '\\CMX_DOK_REL_META')) {
			$map = \constant(__NAMESPACE__ . '\\CMX_DOK_REL_META');
			if (is_array($map) && isset($map[$post_type])) {
				$rel_key = $map[$post_type];
			}
		}
		\update_post_meta($doc_id, $rel_key, [(int)$post_id]);
		\update_post_meta($doc_id, '_cmx_dokumente_file_path', $rel);

		$self_files = (array) \get_post_meta($doc_id, CMX_DOK_SELF_META, true);
		$self_files = array_values(array_filter($self_files, function($v){ return $v !== '' && $v !== null; }));
		$self_files[] = $rel;
		$self_files = array_values(array_unique($self_files));
		\update_post_meta($doc_id, CMX_DOK_SELF_META, $self_files);

		$existing = (array) \get_post_meta($post_id, CMX_DOK_UPLOADS_META, true);
		$existing = array_values(array_filter(array_map('intval', $existing)));
		$existing[] = (int)$doc_id;
		$existing = array_values(array_unique($existing));
		\update_post_meta($post_id, CMX_DOK_UPLOADS_META, $existing);
	}

	\wp_send_json_success([
		'id'    => (int) $doc_id,
		'url'   => $file_url,
		'label' => basename($rel) ?: $doc_title,
		'title' => $response_title,
	]);
}

\add_action('save_post', function (int $post_id, \WP_Post $post): void {
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (\wp_is_post_autosave($post_id) || \wp_is_post_revision($post_id)) {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

	$pending = (string) \get_post_meta($post_id, CMX_DOK_UPLOAD_NOTICE_PENDING_META, true);
	if ($pending !== '1') {
		return;
	}

	$user_id = (int) \get_current_user_id();
	if ($user_id <= 0) {
		return;
	}

	\update_user_meta($user_id, CMX_DOK_UPLOAD_NOTICE_FLASH_USER_META, (string) \time());
	\delete_post_meta($post_id, CMX_DOK_UPLOAD_NOTICE_PENDING_META);
}, 30, 2);

\add_filter('redirect_post_location', function (string $location, int $post_id): string {
	if ($post_id <= 0) {
		return $location;
	}

	$marker = isset($_POST['cmx_dok_upload_saved'])
		? (string) \sanitize_text_field((string) \wp_unslash($_POST['cmx_dok_upload_saved']))
		: '';
	if ($marker !== '1') {
		return $location;
	}

	if (!\current_user_can('edit_post', $post_id)) {
		return $location;
	}

	$location = (string) \add_query_arg('cmx_dok_upload_saved', '1', $location);
	if ((string) \get_post_type($post_id) === 'scanner') {
		$location = (string) \remove_query_arg(['message', 'updated'], $location);
	}

	return $location;
}, 9999, 2);

\add_action('all_admin_notices', function (): void {
	$has_query_marker = isset($_GET['cmx_dok_upload_saved'])
		? (string) \sanitize_text_field((string) \wp_unslash($_GET['cmx_dok_upload_saved']))
		: '';
	$has_query_marker = ($has_query_marker === '1');

	$user_id = (int) \get_current_user_id();
	$has_flash_marker = false;
	if ($user_id > 0) {
		$flash_raw = (string) \get_user_meta($user_id, CMX_DOK_UPLOAD_NOTICE_FLASH_USER_META, true);
		$has_flash_marker = ($flash_raw !== '');
	}

	if (!$has_query_marker && !$has_flash_marker) {
		return;
	}

	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || !\in_array((string) $screen->base, ['post', 'edit'], true)) {
		return;
	}

	if ($user_id > 0 && $has_flash_marker) {
		\delete_user_meta($user_id, CMX_DOK_UPLOAD_NOTICE_FLASH_USER_META);
	}

	echo '<div class="notice notice-success is-dismissible"><p>Dokument wurde hochgeladen und gespeichert.</p></div>';
});

\add_action('wp_ajax_cmx_dokumente_remove_file', __NAMESPACE__ . '\\cmx_dokumente_remove_file');
function cmx_dokumente_remove_file(): void {
	if (!\current_user_can('delete_posts')) \wp_send_json_error(['message'=>'forbidden'], 403);
	$nonce = isset($_POST['nonce']) ? (string)$_POST['nonce'] : '';
	if (!\wp_verify_nonce($nonce, 'cmx_dokumente_upload')) \wp_send_json_error(['message'=>'bad_nonce'], 403);

	$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
	$doc_id = isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : 0;
	$path = isset($_POST['path']) ? (string)$_POST['path'] : '';
	if ($post_id <= 0) \wp_send_json_error(['message'=>'bad_params'], 400);
	$post_type = (string) \get_post_type($post_id);
	$is_dokumente = ($post_type === 'dokumente');
	$is_scanner = ($post_type === 'scanner');

	if ($is_dokumente) {
		if ($path === '') \wp_send_json_error(['message'=>'bad_params'], 400);
		$path_norm = \ltrim(\str_replace('\\', '/', (string) $path), '/');
		$files = (array) \get_post_meta($post_id, CMX_DOK_SELF_META, true);
		$files = array_values(array_filter($files, function($v){ return $v !== '' && $v !== null; }));
		$files = array_values(array_filter($files, function($v) use ($path_norm){
			$v_norm = \ltrim(\str_replace('\\', '/', (string) $v), '/');
			return $v_norm !== $path_norm;
		}));
		\update_post_meta($post_id, CMX_DOK_SELF_META, $files);

		// Nur Zuordnung entfernen, Datei und Dokument-Post bleiben bestehen.
		$current_primary = (string) \get_post_meta($post_id, '_cmx_dokumente_file_path', true);
		$current_primary_norm = \ltrim(\str_replace('\\', '/', $current_primary), '/');
		if ($current_primary_norm === $path_norm) {
			if (!empty($files)) {
				$new_primary = (string) \end($files);
				\update_post_meta($post_id, '_cmx_dokumente_file_path', $new_primary);
			} else {
				\delete_post_meta($post_id, '_cmx_dokumente_file_path');
			}
		}
	} elseif ($is_scanner) {
		$source_rel = (string) \get_post_meta($post_id, '_cmx_scanner_source_rel', true);
		$path = $path !== '' ? $path : $source_rel;
		$path = \ltrim(\str_replace('\\', '/', (string) $path), '/');
		if ($path === '') \wp_send_json_error(['message'=>'bad_params'], 400);

		// Nur Zuordnung entfernen, Datei bleibt bestehen.
		$source_norm = \ltrim(\str_replace('\\', '/', $source_rel), '/');
		if ($source_norm === $path || $source_norm === '') {
			\delete_post_meta($post_id, '_cmx_scanner_source_rel');
			\delete_post_meta($post_id, '_cmx_scanner_uploaded_ts');
			\delete_post_meta($post_id, '_cmx_scanner_fs_signature');
		}
	} else {
		if ($doc_id <= 0) \wp_send_json_error(['message'=>'bad_params'], 400);
		$docs = (array) \get_post_meta($post_id, CMX_DOK_UPLOADS_META, true);
		$docs = array_values(array_filter(array_map('intval', $docs)));
		$docs = array_values(array_diff($docs, [$doc_id]));
		\update_post_meta($post_id, CMX_DOK_UPLOADS_META, $docs);

		// Nur die Zuordnung zum aktuellen Post entfernen.
		// Das Dokument selbst (inkl. Datei) bleibt erhalten.
		$rel_key = 'cmx_dokumente_rel_' . \sanitize_key($post_type);
		if (\defined(__NAMESPACE__ . '\\CMX_DOK_REL_META')) {
			$map = \constant(__NAMESPACE__ . '\\CMX_DOK_REL_META');
			if (\is_array($map) && isset($map[$post_type]) && \is_string($map[$post_type]) && $map[$post_type] !== '') {
				$rel_key = (string) $map[$post_type];
			}
		}
		$doc_rel = (array) \get_post_meta($doc_id, $rel_key, true);
		$doc_rel = \array_values(\array_filter(\array_map('intval', $doc_rel)));
		$doc_rel = \array_values(\array_diff($doc_rel, [(int) $post_id]));
		if (empty($doc_rel)) {
			\delete_post_meta($doc_id, $rel_key);
		} else {
			\update_post_meta($doc_id, $rel_key, $doc_rel);
		}
	}

	\wp_send_json_success(['removed' => $doc_id ?: $path]);
}

function cmx_dokumente_delete_files(int $post_id): void {
	$file_rel = (string) \get_post_meta($post_id, '_cmx_dokumente_file_path', true);
	$files = (array) \get_post_meta($post_id, CMX_DOK_SELF_META, true);
	$files = array_values(array_filter($files, function($v){ return $v !== '' && $v !== null; }));
	if ($file_rel !== '') {
		$files[] = $file_rel;
	}
	$files = array_values(array_unique($files));
	foreach ($files as $rel) {
		$abs = WP_CONTENT_DIR . '/uploads/' . ltrim((string) $rel, '/');
		if (is_file($abs)) {
			@unlink($abs);
		}
	}
}

\add_action('before_delete_post', function(int $post_id) {
	$post = \get_post($post_id);
	if (!$post || $post->post_type !== 'dokumente') {
		return;
	}
	cmx_dokumente_delete_files($post_id);
}, 10, 1);

\add_action('trashed_post', function(int $post_id) {
	$post = \get_post($post_id);
	if (!$post || $post->post_type !== 'dokumente') {
		return;
	}
	cmx_dokumente_delete_files($post_id);
}, 10, 1);

if (!\defined(__NAMESPACE__ . '\\CMX_DOK_ADMIN_PDF_COLUMN')) {
	\define(__NAMESPACE__ . '\\CMX_DOK_ADMIN_PDF_COLUMN', 'cmx_related_doc_pdf');
}
if (!\defined(__NAMESPACE__ . '\\CMX_DOK_ADMIN_ICON_POST_TYPES')) {
	\define(__NAMESPACE__ . '\\CMX_DOK_ADMIN_ICON_POST_TYPES', ['artikel', 'kontakte', 'projekte', 'dokumente', 'scanner']);
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_dok_admin_icon_post_types')) {
	function cmx_dok_admin_icon_post_types(): array {
		$types = \defined(__NAMESPACE__ . '\\CMX_DOK_ADMIN_ICON_POST_TYPES')
			? \constant(__NAMESPACE__ . '\\CMX_DOK_ADMIN_ICON_POST_TYPES')
			: [];
		if (!\is_array($types)) {
			$types = [];
		}
		$types = \array_map(static function ($value): string {
			return \sanitize_key((string) $value);
		}, $types);
		$types = \array_values(\array_unique(\array_filter($types, static function ($value): bool {
			return $value !== '' && $value !== 'belege';
		})));
		return $types;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_dok_admin_upload_url_from_rel')) {
	function cmx_dok_admin_upload_url_from_rel(string $file_rel): string {
		$file_rel = \ltrim(\str_replace('\\', '/', $file_rel), '/');
		if ($file_rel === '') {
			return '';
		}

		$uploads_root = \trailingslashit(\wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads')));
		$abs = \wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads/' . $file_rel));
		if ($abs === '' || !\str_starts_with($abs, $uploads_root) || !\is_file($abs)) {
			return '';
		}

		return (string) \content_url('/uploads/' . $file_rel);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_dok_admin_doc_url_from_id')) {
	function cmx_dok_admin_doc_url_from_id(int $doc_id): string {
		if ($doc_id <= 0 || (string) \get_post_type($doc_id) !== 'dokumente') {
			return '';
		}

		$self_meta_key = \defined(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
			: '_cmx_dokumente_files';
		$self_files = (array) \get_post_meta($doc_id, $self_meta_key, true);
		$self_files = \array_values(\array_filter($self_files, static function ($value): bool {
			return (\is_string($value) && $value !== '') || \is_numeric($value);
		}));
		for ($i = \count($self_files) - 1; $i >= 0; $i--) {
			$entry = $self_files[$i];
			$file_rel = '';
			if (\is_numeric($entry)) {
				$file_rel = (string) \get_post_meta((int) $entry, '_wp_attached_file', true);
			} else {
				$file_rel = (string) $entry;
			}
			$url = cmx_dok_admin_upload_url_from_rel($file_rel);
			if ($url !== '') {
				return $url;
			}
		}

		$file_rel = (string) \get_post_meta($doc_id, '_cmx_dokumente_file_path', true);
		$url = cmx_dok_admin_upload_url_from_rel($file_rel);
		if ($url !== '') {
			return $url;
		}

		$edit_url = (string) \get_edit_post_link($doc_id, 'raw');
		return $edit_url !== '' ? $edit_url : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_dok_admin_latest_related_doc_url')) {
	function cmx_dok_admin_latest_related_doc_url(int $post_id): string {
		if ($post_id <= 0) {
			return '';
		}

		$uploads_meta_key = \defined(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
			: '_cmx_dokumente_uploads';
		$doc_ids = (array) \get_post_meta($post_id, $uploads_meta_key, true);
		$doc_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $doc_ids))));
		if (empty($doc_ids)) {
			return '';
		}

		for ($i = \count($doc_ids) - 1; $i >= 0; $i--) {
			$url = cmx_dok_admin_doc_url_from_id((int) $doc_ids[$i]);
			if ($url !== '') {
				return $url;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_dok_admin_latest_self_doc_url')) {
	function cmx_dok_admin_latest_self_doc_url(int $post_id): string {
		if ($post_id <= 0 || (string) \get_post_type($post_id) !== 'dokumente') {
			return '';
		}

		$self_meta_key = \defined(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
			: '_cmx_dokumente_files';
		$self_files = (array) \get_post_meta($post_id, $self_meta_key, true);
		$self_files = \array_values(\array_filter($self_files, static function ($value): bool {
			return (\is_string($value) && $value !== '') || \is_numeric($value);
		}));

		for ($i = \count($self_files) - 1; $i >= 0; $i--) {
			$entry = $self_files[$i];
			$file_rel = '';
			if (\is_numeric($entry)) {
				$file_rel = (string) \get_post_meta((int) $entry, '_wp_attached_file', true);
			} else {
				$file_rel = (string) $entry;
			}
			$url = cmx_dok_admin_upload_url_from_rel($file_rel);
			if ($url !== '') {
				return $url;
			}
		}

			$file_rel = (string) \get_post_meta($post_id, '_cmx_dokumente_file_path', true);
			$url = cmx_dok_admin_upload_url_from_rel($file_rel);
			if ($url !== '') {
				return $url;
			}
			return '';
		}
	}

if (!\function_exists(__NAMESPACE__ . '\\cmx_dok_admin_latest_doc_url_for_post')) {
	function cmx_dok_admin_latest_doc_url_for_post(int $post_id, string $post_type): string {
		$post_type = \sanitize_key($post_type);
		if ($post_type === 'dokumente') {
			return cmx_dok_admin_latest_self_doc_url($post_id);
		}
		return cmx_dok_admin_latest_related_doc_url($post_id);
	}
}

\add_action('init', function (): void {
	$column_key = \defined(__NAMESPACE__ . '\\CMX_DOK_ADMIN_PDF_COLUMN')
		? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_ADMIN_PDF_COLUMN')
		: 'cmx_related_doc_pdf';

	foreach (cmx_dok_admin_icon_post_types() as $post_type) {
		$append_column = static function (array $columns) use ($column_key): array {
			unset($columns[$column_key]);
			$columns[$column_key] = 'PDF';
			return $columns;
		};

		\add_filter('manage_edit-' . $post_type . '_columns', $append_column, 2000);
		\add_filter('manage_' . $post_type . '_posts_columns', $append_column, 2000);

		\add_action('manage_' . $post_type . '_posts_custom_column', static function (string $column, int $post_id) use ($column_key, $post_type): void {
			if ($column !== $column_key) {
				return;
			}

			$url = cmx_dok_admin_latest_doc_url_for_post($post_id, $post_type);
			if ($url === '') {
				echo '<span class="cmx-related-doc-pdf-placeholder" aria-hidden="true"></span>';
				return;
			}

			echo '<a href="' . \esc_url($url) . '" target="_blank" rel="noopener noreferrer" title="Letztes zugeordnetes Dokument anzeigen" class="cmx-related-doc-pdf-icon" aria-label="Letztes zugeordnetes Dokument anzeigen"><span class="dashicons dashicons-pdf" aria-hidden="true"></span></a>';
		}, 20, 2);
	}
}, 5);

\add_action('admin_head-edit.php', function (): void {
	$post_type = isset($_GET['post_type']) ? \sanitize_key((string) $_GET['post_type']) : '';
	if ($post_type === '' && \function_exists('get_current_screen')) {
		$screen = \get_current_screen();
		if ($screen && isset($screen->post_type)) {
			$post_type = \sanitize_key((string) $screen->post_type);
		}
	}

	$post_types = cmx_dok_admin_icon_post_types();
	if ($post_type === '' || !\in_array($post_type, $post_types, true)) {
		return;
	}

	$column_key = \defined(__NAMESPACE__ . '\\CMX_DOK_ADMIN_PDF_COLUMN')
		? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_ADMIN_PDF_COLUMN')
		: 'cmx_related_doc_pdf';

	\wp_enqueue_style('dashicons');

	echo '<style>
		.wp-list-table th.column-' . \esc_attr($column_key) . ' {
			width: 42px;
			text-align: center;
		}
		.wp-list-table td.column-' . \esc_attr($column_key) . ' {
			text-align: center;
			vertical-align: top;
		}
		.cmx-related-doc-pdf-icon {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			text-decoration: none;
			min-height: 20px;
			color: #111111;
		}
		.cmx-related-doc-pdf-icon .dashicons {
			width: 18px;
			height: 18px;
			font-size: 18px;
			line-height: 18px;
		}
		.cmx-related-doc-pdf-placeholder {
			display: inline-block;
			width: 18px;
			height: 18px;
		}
	</style>';
});
