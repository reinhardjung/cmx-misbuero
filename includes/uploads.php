<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

require_once __DIR__ . '/upload_form.php';

if (!defined(__NAMESPACE__ . '\\CMX_BELEG_UPLOADS_META')) {
	define(__NAMESPACE__ . '\\CMX_BELEG_UPLOADS_META', '_cmx_belege_uploads');
}

function cmx_is_beleg_upload_request(): bool {
	return isset($_FILES['beleg_datei']);
}

function cmx_get_beleg_upload_stamp(): string {
	static $stamp = '';
	if ($stamp === '') {
		$stamp = date('ymd-His', current_time('timestamp'));
	}
	return $stamp;
}

\add_filter('upload_dir', function(array $dirs): array {
	if (!cmx_is_beleg_upload_request()) {
		return $dirs;
	}

	$year = date('Y', current_time('timestamp'));
	$base = WP_CONTENT_DIR . '/uploads/misbuero/' . $year . '/belege';
	$url  = content_url('/uploads/misbuero/' . $year . '/belege');
	if (!is_dir($base)) {
		wp_mkdir_p($base);
	}
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
	\add_meta_box(
		'cmx_uploads_box',
		'Uploads',
		__NAMESPACE__ . '\\cmx_render_uploads_box',
		$post_type,
		'side',
		'high'
	);
}, 10, 1);

function cmx_render_uploads_box(\WP_Post $post): void {
	$nonce = wp_create_nonce('cmx_belege_upload');
	$docs = (array) get_post_meta($post->ID, CMX_BELEG_UPLOADS_META, true);
	$docs = array_values(array_filter(array_map('intval', $docs)));
	$post_slug = sanitize_title((string) get_the_title($post->ID));
	$expected_prefix = $post_slug !== '' ? $post_slug . '_upload' : '';
	$clean_docs = [];

	echo '<div id="cmx-belege-upload-box">';
	echo '<div id="cmx-belege-drop" style="border:2px dashed #ccd0d4;padding:10px;text-align:center;background:#fafafa;cursor:pointer;">';
	echo '<strong>Datei hier ablegen</strong><br><small>PDF, PNG, JPG</small>';
	echo '</div>';
	echo '<input type="file" id="cmx-belege-file" style="display:none" accept=".pdf,.png,.jpg,.jpeg">';
	echo '</div>';

	if ($docs) {
		echo '<ul id="cmx-belege-existing" style="margin:6px 0 0 0;padding:0;list-style:none;max-height:160px;overflow:auto;width:100%;">';
		foreach ($docs as $att_id) {
			$file_abs = get_attached_file($att_id);
			if (!$file_abs || !is_file($file_abs)) {
				continue;
			}
			$norm = str_replace('\\', '/', $file_abs);
			if (strpos($norm, '/uploads/misbuero/') === false || strpos($norm, '/belege/') === false) {
				continue;
			}
			$url = wp_get_attachment_url($att_id);
			$file_rel = (string) get_post_meta($att_id, '_wp_attached_file', true);
			$file_base = $file_rel ? basename($file_rel) : '';
			if ($expected_prefix !== '' && $file_base !== '' && strpos($file_base, $expected_prefix) !== 0) {
				continue;
			}
			$clean_docs[] = (int) $att_id;
			$label = $file_base ?: (get_the_title($att_id) ?: ('#' . $att_id));
			echo '<li data-att-id="' . (int) $att_id . '" style="display:grid;grid-template-columns:1fr 14px;align-items:center;gap:4px;width:100%;white-space:nowrap;">';
			if ($url) {
				echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr($label) . '" style="min-width:0;text-align:center;justify-self:stretch;overflow:hidden;text-overflow:ellipsis;">' . esc_html($label) . '</a>';
			} else {
				echo '<span title="' . esc_attr($label) . '" style="min-width:0;text-align:center;justify-self:stretch;overflow:hidden;text-overflow:ellipsis;">' . esc_html($label) . '</span>';
			}
			echo ' <button type="button" class="button-link cmx-belege-remove" style="color:#b32d2e;justify-self:end;padding:0;line-height:1;">X</button>';
			echo '</li>';
		}
		echo '</ul>';
	}
	if ($clean_docs !== $docs) {
		update_post_meta($post->ID, CMX_BELEG_UPLOADS_META, $clean_docs);
	}

	echo '<script>
	jQuery(function($){
		var $drop = $("#cmx-belege-drop");
		var $file = $("#cmx-belege-file");
		var postId = ' . (int) $post->ID . ';
		var nonce = ' . wp_json_encode($nonce) . ';
		var ajaxurl = ' . wp_json_encode(admin_url('admin-ajax.php')) . ';

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
				success: function(resp){
					if (resp && resp.success && resp.data) {
						if (resp.data.title) {
							$("#title").val(resp.data.title);
							$("#title-prompt-text").addClass("screen-reader-text");
						}
						if (resp.data.notice) {
							var $notice = $("#cmx-belege-upload-notice");
							if (!$notice.length) {
								$notice = $("<div id=\"cmx-belege-upload-notice\" class=\"notice notice-success is-dismissible\" style=\"margin:8px 0;\"><p></p></div>");
								$("#poststuff").before($notice);
							}
							$notice.find("p").text(resp.data.notice);
						}
						var label = resp.data.label || file.name;
						var url = resp.data.url || "";
						var attId = resp.data.id || "";
						var $li = $("<li>").attr("data-att-id", attId).css({
							display: "grid",
							gridTemplateColumns: "1fr 14px",
							alignItems: "center",
							gap: "4px",
							width: "100%",
							whiteSpace: "nowrap"
						});
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
						$notice.find("p").text("Fehler beim Upload: " + file.name);
					}
				},
				error: function(){
					var $notice = $("#cmx-belege-upload-notice");
					if (!$notice.length) {
						$notice = $("<div id=\"cmx-belege-upload-notice\" class=\"notice notice-error is-dismissible\" style=\"margin:8px 0;\"><p></p></div>");
						$("#poststuff").before($notice);
					}
					$notice.find("p").text("Fehler beim Upload: " + file.name);
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

		$("#cmx-belege-existing").on("click", ".cmx-belege-remove", function(){
			var $li = $(this).closest("li");
			var attId = $li.data("att-id");
			if (!attId) return;
			if (!confirm("Datei entfernen?")) return;
			$.post(ajaxurl, { action:"cmx_belege_remove_file", post_id: postId, att_id: attId, nonce: nonce }, function(resp){
				if (resp && resp.success) {
					$li.remove();
				}
			});
		});
	});
	</script>';
}

add_action('wp_ajax_cmx_belege_upload_file', __NAMESPACE__ . '\\cmx_belege_upload_file');
function cmx_belege_upload_file(): void {
	if (!current_user_can('upload_files')) {
		wp_send_json_error(['message' => 'forbidden'], 403);
	}
	$nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';
	if (!wp_verify_nonce($nonce, 'cmx_belege_upload')) {
		wp_send_json_error(['message' => 'bad_nonce'], 403);
	}
	$post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
	if ($post_id <= 0 || get_post_type($post_id) !== 'belege') {
		wp_send_json_error(['message' => 'bad_post'], 400);
	}
	if (empty($_FILES['file']) || !isset($_FILES['file']['tmp_name'])) {
		wp_send_json_error(['message' => 'no_file'], 400);
	}

	$allowed = ['pdf','png','jpg','jpeg'];
	$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
	if (!in_array($ext, $allowed, true)) {
		wp_send_json_error(['message' => 'bad_type'], 400);
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$ts = current_time('timestamp');
	$year = date('Y', $ts);
	$post = get_post($post_id);
	if (!$post) {
		wp_send_json_error(['message' => 'bad_post'], 400);
	}
	$post_title = $post->post_title;
	if ($post_title === '' || $post->post_status === 'auto-draft') {
		$post_title = date('ymd-His', $ts);
	}
	wp_update_post([
		'ID' => $post_id,
		'post_title' => $post_title,
		'post_status' => 'publish',
	]);
	$post_slug = sanitize_title($post_title);

	$upload_filter = function($dirs) use ($year) {
		$base = WP_CONTENT_DIR . '/uploads/misbuero/' . $year . '/belege';
		$url  = content_url('/uploads/misbuero/' . $year . '/belege');
		if (!file_exists($base)) { wp_mkdir_p($base); }
		$dirs['path']   = $base;
		$dirs['basedir']= $base;
		$dirs['url']    = $url;
		$dirs['baseurl']= $url;
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
	$existing = array_values(array_filter(array_map('intval', $existing)));
	$max_suffix = 0;
	if ($post_slug !== '') {
		foreach ($existing as $att_id) {
			$file_abs = get_attached_file($att_id);
			if (!$file_abs || !is_file($file_abs)) {
				continue;
			}
			$file_base = basename($file_abs);
			if (preg_match('/^' . preg_quote($post_slug, '/') . '_upload_(\\d{3})\\./', $file_base, $m)) {
				$max_suffix = max($max_suffix, (int) $m[1]);
			}
		}
	}
	$suffix = '_' . str_pad((string) ($max_suffix + 1), 3, '0', STR_PAD_LEFT);

	$uploaded = wp_handle_upload($_FILES['file'], [
		'test_form' => false,
		'unique_filename_callback' => function($dir, $name, $ext) use ($post_slug, $suffix) {
			$base = $post_slug . '_upload' . $suffix;
			$filename = $base . $ext;
			$counter = 1;
			while (file_exists($dir . '/' . $filename)) {
				$filename = $base . '-' . $counter . $ext;
				$counter++;
			}
			return $filename;
		},
	]);

	if (!isset($uploaded['file'])) {
		remove_filter('big_image_size_threshold', $no_big_image, 10);
		remove_filter('intermediate_image_sizes_advanced', $no_sizes_filter);
		remove_filter('intermediate_image_sizes', $no_sizes_filter);
		remove_filter('upload_dir', $upload_filter);
		wp_send_json_error(['message' => 'upload_failed'], 500);
	}

	add_filter('wp_generate_attachment_metadata', $no_meta_sizes_filter, 10, 2);

	$attachment = [
		'post_mime_type' => $uploaded['type'] ?? '',
		'post_title'     => sanitize_text_field($post_title),
		'post_content'   => '',
		'post_status'    => 'inherit',
		'post_parent'    => (int) $post_id,
	];
	$att_id = wp_insert_attachment($attachment, $uploaded['file'], $post_id);
	if ($att_id) {
		$meta = wp_generate_attachment_metadata($att_id, $uploaded['file']);
		if (is_array($meta)) {
			if (isset($meta['sizes'])) {
				$meta['sizes'] = [];
			}
			wp_update_attachment_metadata($att_id, $meta);
		}
	}
	remove_filter('wp_generate_attachment_metadata', $no_meta_sizes_filter, 10);
	remove_filter('big_image_size_threshold', $no_big_image, 10);
	remove_filter('intermediate_image_sizes_advanced', $no_sizes_filter);
	remove_filter('intermediate_image_sizes', $no_sizes_filter);
	remove_filter('upload_dir', $upload_filter);

	$existing[] = (int) $att_id;
	$existing = array_values(array_unique($existing));
	update_post_meta($post_id, CMX_BELEG_UPLOADS_META, $existing);

	$label = $post_slug !== '' ? $post_slug . '_upload' . $suffix . '.' . $ext : $post_title . '_upload' . $suffix . '.' . $ext;
	wp_send_json_success([
		'id'    => $att_id,
		'url'   => $att_id ? wp_get_attachment_url($att_id) : '',
		'label' => $label,
		'title' => $post_title,
		'notice' => 'Beleg wurde gespeichert.',
	]);
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
	if ($post_id <= 0 || $att_id <= 0 || get_post_type($post_id) !== 'belege') {
		wp_send_json_error(['message' => 'bad_params'], 400);
	}

	$existing = (array) get_post_meta($post_id, CMX_BELEG_UPLOADS_META, true);
	$existing = array_values(array_filter(array_map('intval', $existing)));
	$existing = array_values(array_diff($existing, [$att_id]));
	update_post_meta($post_id, CMX_BELEG_UPLOADS_META, $existing);
	wp_delete_attachment($att_id, true);

	wp_send_json_success(['ok' => true]);
}
