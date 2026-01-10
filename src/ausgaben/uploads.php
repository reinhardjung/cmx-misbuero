<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/* =========================================================
 * Ausgaben: Upload-Metabox (PDF/PNG/JPEG) -> /archiv/ausgaben/
 * ========================================================= */
if (!\defined(__NAMESPACE__ . '\\CMX_AUSGABEN_META_UPLOADS')) {
	\define(__NAMESPACE__ . '\\CMX_AUSGABEN_META_UPLOADS', '_cmx_ausgaben_uploads');
}

\add_action('add_meta_boxes', function($post_type) {
	if ($post_type !== 'ausgaben') return;
	\add_meta_box(
		'cmx_ausgaben_uploads',
		'Uploads',
		__NAMESPACE__ . '\\cmx_render_ausgaben_uploads_box',
		'ausgaben',
		'side',
		'high'
	);
}, 10, 1);

function cmx_render_ausgaben_uploads_box(\WP_Post $post): void {
	$nonce = \wp_create_nonce('cmx_ausgaben_upload');
	$files = (array) \get_post_meta($post->ID, CMX_AUSGABEN_META_UPLOADS, true);
	$files = array_values(array_filter(array_map('intval', $files)));

	echo '<div id="cmx-ausgaben-upload-box">';
	echo '<div id="cmx-ausgaben-drop" style="border:2px dashed #ccd0d4;padding:10px;text-align:center;background:#fafafa;cursor:pointer;">';
	echo '<strong>Datei hier ablegen</strong><br><small>PDF, PNG, JPG</small>';
	echo '</div>';
	echo '<input type="file" id="cmx-ausgaben-file" accept=\"application/pdf,image/png,image/jpeg\" style="display:none" multiple>';
	echo '<div id="cmx-ausgaben-list" style="margin-top:8px;max-height:160px;overflow:auto;"></div>';
	echo '</div>';

	// Liste bestehender Uploads
	if ($files) {
		echo '<ul id="cmx-ausgaben-existing" style="margin:6px 0 0 18px;max-height:160px;overflow:auto;">';
		foreach ($files as $att_id) {
			$url = \wp_get_attachment_url($att_id);
			$file_rel = (string) \get_post_meta($att_id, '_wp_attached_file', true);
			$label = $file_rel ? basename($file_rel) : (\get_the_title($att_id) ?: ('#' . $att_id));
			if ($url) {
				echo '<li><a href="' . \esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . \esc_html($label) . '</a></li>';
			}
		}
		echo '</ul>';
	}

	echo '<script>
	jQuery(function($){
		var $drop = $("#cmx-ausgaben-drop");
		var $file = $("#cmx-ausgaben-file");
		var $list = $("#cmx-ausgaben-list");
		var postId = ' . (int)$post->ID . ';
		var nonce = ' . \wp_json_encode($nonce) . ';
		var ajaxurl = ' . \wp_json_encode(\admin_url('admin-ajax.php')) . ';

		function setTitleReadonly(){
			var $title = $("#title");
			if ($title.length) {
				$title.prop("readonly", true).attr("placeholder", "wird autom. gesetzt");
				var $prompt = $("#title-prompt-text");
				if ($prompt.length) $prompt.text("wird autom. gesetzt");
			}
		}
		function syncTitlePrompt(){
			var $title = $("#title");
			var $prompt = $("#title-prompt-text");
			if ($title.length && $prompt.length) {
				if ($title.val()) { $prompt.hide(); } else { $prompt.show(); }
			}
		}

		function uploadFile(file){
			var fd = new FormData();
			fd.append("action", "cmx_ausgaben_upload_file");
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
							if (resp.data.title) {
								$("#title").val(resp.data.title);
								syncTitlePrompt();
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

		setTitleReadonly();
		syncTitlePrompt();
		$("#title").on("input change", syncTitlePrompt);
	});
	</script>';
}

/* =========================================================
 * AJAX Upload
 * ========================================================= */
\add_action('wp_ajax_cmx_ausgaben_upload_file', __NAMESPACE__ . '\\cmx_ausgaben_upload_file');
function cmx_ausgaben_upload_file(): void {
	if (!\current_user_can('edit_posts')) \wp_send_json_error(['message'=>'forbidden'], 403);
	$nonce = isset($_POST['nonce']) ? (string)$_POST['nonce'] : '';
	if (!\wp_verify_nonce($nonce, 'cmx_ausgaben_upload')) \wp_send_json_error(['message'=>'bad_nonce'], 403);

	$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
	if ($post_id <= 0 || \get_post_type($post_id) !== 'ausgaben') {
		\wp_send_json_error(['message'=>'bad_post'], 400);
	}

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

	$upload_filter = function($dirs) {
		$year = \gmdate('Y', \current_time('timestamp'));
		$base = WP_CONTENT_DIR . '/uploads/misbuero/' . $year . '/ausgaben';
		$url  = content_url('/uploads/misbuero/' . $year . '/ausgaben');
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
		\wp_send_json_error(['message'=>'upload_failed'], 500);
	}

	$attachment = [
		'post_mime_type' => $uploaded['type'] ?? '',
		'post_title'     => sanitize_text_field(pathinfo($uploaded['file'], PATHINFO_FILENAME)),
		'post_content'   => '',
		'post_status'    => 'inherit',
		'post_parent'    => $post_id,
	];
	$att_id = \wp_insert_attachment($attachment, $uploaded['file'], $post_id);
	if ($att_id) {
		\add_filter('wp_generate_attachment_metadata', $no_meta_sizes_filter, 10, 2);
		$meta = \wp_generate_attachment_metadata($att_id, $uploaded['file']);
		\remove_filter('wp_generate_attachment_metadata', $no_meta_sizes_filter, 10);
		if (is_array($meta)) {
			// Cleanup: delete any generated sizes or scaled originals
			$base_dir = dirname($uploaded['file']);
			$pi = pathinfo($uploaded['file']);
			$base_name = $pi['filename'] ?? '';
			$ext = $pi['extension'] ?? '';
			if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
				foreach ($meta['sizes'] as $s) {
					if (!empty($s['file'])) {
						$path = $base_dir . '/' . $s['file'];
						if (is_file($path)) @unlink($path);
					}
				}
				$meta['sizes'] = [];
			}
			$scaled = preg_replace('/(\\.[a-z0-9]+)$/i', '-scaled$1', $uploaded['file']);
			if ($scaled && $scaled !== $uploaded['file'] && is_file($scaled)) {
				@unlink($scaled);
			}
			if ($base_name !== '' && $ext !== '') {
				foreach (glob($base_dir . '/' . $base_name . '-*.' . $ext) as $p) {
					if (preg_match('/-\\d+x\\d+\\.' . preg_quote($ext, '/') . '$/i', $p)) {
						@unlink($p);
					}
				}
			}
			\wp_update_attachment_metadata($att_id, $meta);
		}
	}

	\remove_filter('big_image_size_threshold', $no_big_image, 10);
	\remove_filter('intermediate_image_sizes_advanced', $no_sizes_filter);
	\remove_filter('intermediate_image_sizes', $no_sizes_filter_simple);
	\remove_filter('upload_dir', $upload_filter);

	$existing = (array) \get_post_meta($post_id, CMX_AUSGABEN_META_UPLOADS, true);
	$existing = array_values(array_filter(array_map('intval', $existing)));
	$existing[] = (int)$att_id;
	$existing = array_values(array_unique($existing));
	\update_post_meta($post_id, CMX_AUSGABEN_META_UPLOADS, $existing);

	// Titel setzen, wenn leer oder auto-draft
	$curr = \get_post($post_id);
	if ($curr) {
		$title = (string)$curr->post_title;
		if ($title === '' || $curr->post_status === 'auto-draft') {
			$new_title = \gmdate('ymd-His', \current_time('timestamp'));
			$args = [
				'ID'         => $post_id,
				'post_title' => $new_title,
			];
			if ($curr->post_status === 'auto-draft') {
				$args['post_status'] = 'draft';
			}
			\wp_update_post($args);
			$title = $new_title;
		}
	}

	// Datei umbenennen: titel_originalname.ext
	if ($att_id && !empty($title)) {
		$orig_name = isset($_FILES['file']['name']) ? (string)$_FILES['file']['name'] : basename($uploaded['file']);
		$base_dir = dirname($uploaded['file']);
		$new_base = \sanitize_file_name($title . '_' . $orig_name);
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
	}

	\wp_send_json_success([
		'id'    => $att_id,
		'url'   => \wp_get_attachment_url($att_id),
		'label' => basename($uploaded['file']),
		'title' => isset($title) ? $title : '',
	]);
}
