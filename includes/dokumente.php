<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Dokumente-Metabox für alle CPTs (außer "dokumente")
 * Upload: PDF/PNG/JPG -> /archiv/{Jahr}/Dokumente/
 */

if (!\defined(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')) {
	\define(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META', '_cmx_dokumente_uploads');
}
if (!\defined(__NAMESPACE__ . '\\CMX_DOK_SELF_META')) {
	\define(__NAMESPACE__ . '\\CMX_DOK_SELF_META', '_cmx_dokumente_files');
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
	\add_meta_box(
		'cmx_dokumente_box',
		'<a href="'.\esc_url(\admin_url('edit.php?post_type=dokumente')).'" target="_blank" rel="noopener noreferrer" style="font-weight:700;font-size:inherit;text-decoration:none;">Dokumente</a>',
		__NAMESPACE__ . '\\cmx_render_dokumente_upload_box',
		$post_type,
		'side',
		'high'
	);
}, 10, 1);

function cmx_render_dokumente_upload_box(\WP_Post $post): void {
	$nonce = \wp_create_nonce('cmx_dokumente_upload');
	$is_dokumente = ($post->post_type === 'dokumente');
	$docs = [];
	if ($is_dokumente) {
		$docs = (array) \get_post_meta($post->ID, CMX_DOK_SELF_META, true);
		$docs = array_values(array_filter(array_map('intval', $docs)));
	} else {
		$docs = (array) \get_post_meta($post->ID, CMX_DOK_UPLOADS_META, true);
		$docs = array_values(array_filter(array_map('intval', $docs)));
	}

	echo '<div id="cmx-dokumente-upload-box">';
	echo '<div id="cmx-dokumente-drop" style="border:2px dashed #ccd0d4;padding:10px;text-align:center;background:#fafafa;cursor:pointer;">';
	echo '<strong>Datei hier ablegen</strong><br><small>PDF, PNG, JPG</small>';
	echo '</div>';
	echo '<input type="file" id="cmx-dokumente-file" style="display:none" multiple accept=".pdf,.png,.jpg,.jpeg">';
	echo '<div id="cmx-dokumente-list" style="margin-top:8px;max-height:160px;overflow:auto;"></div>';
	echo '</div>';

	if ($docs) {
		echo '<ul id="cmx-dokumente-existing" style="margin:6px 0 0 0;padding:0;list-style:none;max-height:160px;overflow:auto;width:100%;">';
		foreach ($docs as $doc_id) {
			$att_id = $is_dokumente ? (int)$doc_id : (int) \get_post_meta($doc_id, '_cmx_dokumente_attachment_id', true);
			$file_abs = $att_id ? \get_attached_file($att_id) : '';
			if (!$file_abs || !is_file($file_abs)) {
				continue;
			}
			$url = $att_id ? \wp_get_attachment_url($att_id) : '';
			$file_rel = $att_id ? (string) \get_post_meta($att_id, '_wp_attached_file', true) : '';
			$file_base = $file_rel ? basename($file_rel) : '';
			$label = $file_base ?: (\get_the_title($doc_id) ?: ('#' . $doc_id));
			$edit_url = $is_dokumente ? \get_edit_post_link((int)$post->ID, 'raw') : \get_edit_post_link((int)$doc_id, 'raw');
			echo '<li data-doc-id="' . (int)$doc_id . '" style="display:grid;grid-template-columns:18px 1fr 14px;align-items:center;gap:4px;width:100%;white-space:nowrap;">';
			if ($edit_url) {
				echo '<a href="' . \esc_url($edit_url) . '" target="_blank" rel="noopener noreferrer" title="Dokument bearbeiten" style="text-decoration:none;justify-self:start;">';
				echo '<span style="display:inline-block;padding:0 3px;border:1px solid #ccd0d4;border-radius:2px;font-size:10px;line-height:1.2;">D</span>';
				echo '</a>';
			} else {
				echo '<span style="display:inline-block;padding:0 3px;border:1px solid #ccd0d4;border-radius:2px;font-size:10px;line-height:1.2;justify-self:start;">D</span>';
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
		var isDok = ' . ($is_dokumente ? 'true' : 'false') . ';
		var nonce = ' . \wp_json_encode($nonce) . ';
		var ajaxurl = ' . \wp_json_encode(\admin_url('admin-ajax.php')) . ';

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
						if (resp.data.url) {
							$row.html("<a target=\"_blank\" rel=\"noopener noreferrer\"></a>");
							$row.find("a").attr("href", resp.data.url).text(label);
						} else {
							$row.text(label);
						}
						var $pub = $("#publish");
						if ($pub.length) {
							$pub.trigger("click");
						} else {
							var $form = $("#post");
							if ($form.length) $form.trigger("submit");
						}
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
			if (!docId) return;
			if (!confirm("Dokument entfernen?")) return;
			$.post(ajaxurl, { action:"cmx_dokumente_remove_file", post_id: postId, doc_id: docId, nonce: nonce }, function(resp){
				if (resp && resp.success) {
					$li.remove();
					var $pub = $("#publish");
					if ($pub.length) {
						$pub.trigger("click");
					} else {
						var $form = $("#post");
						if ($form.length) $form.trigger("submit");
					}
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

	if (empty($_FILES['file']) || !isset($_FILES['file']['tmp_name'])) {
		\wp_send_json_error(['message'=>'no_file'], 400);
	}

	$allowed = ['pdf','png','jpg','jpeg'];
	$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
	if (!in_array($ext, $allowed, true)) {
		\wp_send_json_error(['message'=>'bad_type'], 400);
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$ts = \current_time('timestamp');
	$year = \date('Y', $ts);
	$doc_title = \date('ymd-His', $ts) . '_' . \sanitize_key($post_type);

	$doc_id = $post_id;
	if (!$is_dokumente) {
		$doc_id = \wp_insert_post([
			'post_type'   => 'dokumente',
			'post_title'  => $doc_title,
			'post_status' => 'publish',
		], true);
		if (\is_wp_error($doc_id) || !$doc_id) {
			\wp_send_json_error(['message'=>'doc_create_failed'], 500);
		}
	}

	$upload_filter = function($dirs) use ($year) {
		$base = WP_CONTENT_DIR . '/uploads/misbuero/' . $year . '/dokumente';
		$url  = content_url('/uploads/misbuero/' . $year . '/dokumente');
		if (!file_exists($base)) { \wp_mkdir_p($base); }
		$dirs['path']   = $base;
		$dirs['basedir']= $base;
		$dirs['url']    = $url;
		$dirs['baseurl']= $url;
		$dirs['subdir'] = '';
		return $dirs;
	};

	$no_sizes_filter = function($sizes) { return []; };
	$no_sizes_filter_simple = function($sizes) { return []; };
	$no_meta_sizes_filter = function($metadata, $attachment_id) {
		if (isset($metadata['sizes'])) $metadata['sizes'] = [];
		return $metadata;
	};
	$no_big_image = function() { return false; };

	\add_filter('upload_dir', $upload_filter);
	\add_filter('intermediate_image_sizes', $no_sizes_filter_simple);
	\add_filter('intermediate_image_sizes_advanced', $no_sizes_filter);
	\add_filter('big_image_size_threshold', $no_big_image, 10, 0);
	$uploaded = \wp_handle_upload($_FILES['file'], ['test_form' => false, 'mimes' => [
		'pdf'  => 'application/pdf',
		'png'  => 'image/png',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
	]]);

	if (!isset($uploaded['file'])) {
		\remove_filter('big_image_size_threshold', $no_big_image, 10);
		\remove_filter('intermediate_image_sizes_advanced', $no_sizes_filter);
		\remove_filter('intermediate_image_sizes', $no_sizes_filter_simple);
		\remove_filter('upload_dir', $upload_filter);
		if (!$is_dokumente) {
			\wp_delete_post($doc_id, true);
		}
		\wp_send_json_error(['message'=>'upload_failed'], 500);
	}

	$attachment = [
		'post_mime_type' => $uploaded['type'] ?? '',
		'post_title'     => sanitize_text_field($doc_title),
		'post_content'   => '',
		'post_status'    => 'inherit',
		'post_parent'    => (int)$doc_id,
	];
	$att_id = \wp_insert_attachment($attachment, $uploaded['file'], $doc_id);
	if ($att_id) {
		\add_filter('wp_generate_attachment_metadata', $no_meta_sizes_filter, 10, 2);
		$meta = \wp_generate_attachment_metadata($att_id, $uploaded['file']);
		\remove_filter('wp_generate_attachment_metadata', $no_meta_sizes_filter, 10);
		if (is_array($meta)) {
			if (isset($meta['sizes'])) $meta['sizes'] = [];
			\wp_update_attachment_metadata($att_id, $meta);
		}
	}

	\remove_filter('big_image_size_threshold', $no_big_image, 10);
	\remove_filter('intermediate_image_sizes_advanced', $no_sizes_filter);
	\remove_filter('intermediate_image_sizes', $no_sizes_filter_simple);
	\remove_filter('upload_dir', $upload_filter);

	// Datei umbenennen: YYMMDD-HHMMSS_XY.ext (nur fuer CPT belege)
	$base_dir = dirname($uploaded['file']);
	$orig_base = pathinfo($_FILES['file']['name'], PATHINFO_FILENAME);
	$umlaut_map = [
		'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
		'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
	];
	$normalized = strtr($orig_base, $umlaut_map);
	$only_letters = preg_replace('/[^a-z]/i', '', $normalized);
	$only_digits = preg_replace('/[^0-9]/', '', $normalized);
	$short = substr($only_letters, 0, 8);
	if (strlen($short) < 8) {
		$need = 8 - strlen($short);
		$short .= substr($only_digits, 0, $need);
	}
	$short = $short !== '' ? $short : 'upload';
	$new_base = \sanitize_file_name($doc_title . '.' . $ext);
	if ($post_type === 'belege') {
		$new_base = \sanitize_file_name(\date('ymd-His', $ts) . '_' . $short . '.' . $ext);
	}
	$new_base = \wp_unique_filename($base_dir, $new_base);
	$new_path = $base_dir . '/' . $new_base;
	if ($new_path !== $uploaded['file']) {
		$renamed = @rename($uploaded['file'], $new_path);
		if ($renamed) {
			$uploads_root = WP_CONTENT_DIR . '/uploads/';
			$rel = ltrim(str_replace($uploads_root, '', $new_path), '/');
			\update_attached_file($att_id, $new_path);
			\update_post_meta($att_id, '_wp_attached_file', $rel);
			\wp_update_post([
				'ID'   => $att_id,
				'guid' => \content_url('/uploads/' . $rel),
			]);
			$uploaded['file'] = $new_path;
		}
	}

	// Beziehung speichern
	if ($is_dokumente) {
		$existing = (array) \get_post_meta($doc_id, CMX_DOK_SELF_META, true);
		$existing = array_values(array_filter(array_map('intval', $existing)));
		$existing[] = (int)$att_id;
		$existing = array_values(array_unique($existing));
		\update_post_meta($doc_id, CMX_DOK_SELF_META, $existing);
		\update_post_meta($doc_id, '_cmx_dokumente_attachment_id', (int)$att_id);
	} else {
		$rel_key = 'cmx_dokumente_rel_' . \sanitize_key($post_type);
		if (\defined(__NAMESPACE__ . '\\CMX_DOK_REL_META')) {
			$map = \constant(__NAMESPACE__ . '\\CMX_DOK_REL_META');
			if (is_array($map) && isset($map[$post_type])) {
				$rel_key = $map[$post_type];
			}
		}
		\update_post_meta($doc_id, $rel_key, [(int)$post_id]);
		\update_post_meta($doc_id, '_cmx_dokumente_attachment_id', (int)$att_id);

		$existing = (array) \get_post_meta($post_id, CMX_DOK_UPLOADS_META, true);
		$existing = array_values(array_filter(array_map('intval', $existing)));
		$existing[] = (int)$doc_id;
		$existing = array_values(array_unique($existing));
		\update_post_meta($post_id, CMX_DOK_UPLOADS_META, $existing);
	}

	\wp_send_json_success([
		'id'    => $doc_id,
		'url'   => $att_id ? \wp_get_attachment_url($att_id) : '',
		'label' => $doc_title,
	]);
}

\add_action('wp_ajax_cmx_dokumente_remove_file', __NAMESPACE__ . '\\cmx_dokumente_remove_file');
function cmx_dokumente_remove_file(): void {
	if (!\current_user_can('delete_posts')) \wp_send_json_error(['message'=>'forbidden'], 403);
	$nonce = isset($_POST['nonce']) ? (string)$_POST['nonce'] : '';
	if (!\wp_verify_nonce($nonce, 'cmx_dokumente_upload')) \wp_send_json_error(['message'=>'bad_nonce'], 403);

	$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
	$doc_id = isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : 0;
	if ($post_id <= 0 || $doc_id <= 0) \wp_send_json_error(['message'=>'bad_params'], 400);
	$post_type = (string) \get_post_type($post_id);
	$is_dokumente = ($post_type === 'dokumente');

	if ($is_dokumente) {
		$files = (array) \get_post_meta($post_id, CMX_DOK_SELF_META, true);
		$files = array_values(array_filter(array_map('intval', $files)));
		$files = array_values(array_diff($files, [$doc_id]));
		\update_post_meta($post_id, CMX_DOK_SELF_META, $files);
		\wp_delete_attachment($doc_id, true);
	} else {
		$docs = (array) \get_post_meta($post_id, CMX_DOK_UPLOADS_META, true);
		$docs = array_values(array_filter(array_map('intval', $docs)));
		$docs = array_values(array_diff($docs, [$doc_id]));
		\update_post_meta($post_id, CMX_DOK_UPLOADS_META, $docs);

		$att_id = (int) \get_post_meta($doc_id, '_cmx_dokumente_attachment_id', true);
		if ($att_id) {
			\wp_delete_attachment($att_id, true);
		}
		\wp_delete_post($doc_id, true);
	}

	\wp_send_json_success(['removed' => $doc_id]);
}
