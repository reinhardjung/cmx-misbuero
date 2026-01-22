<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/* ------------------------------------------------------------
 * Uploads: eigenstaendige Admin-Seite + Admin-Bar-Link
 * ------------------------------------------------------------ */

add_action('admin_menu', __NAMESPACE__ . '\\cmx_register_uploads_page');
function cmx_register_uploads_page(): void {
	add_submenu_page(
		null,
		'Uploads',
		'Uploads',
		'upload_files',
		'cmx-uploads',
		__NAMESPACE__ . '\\cmx_render_uploads_page'
	);
}

add_action('admin_bar_menu', __NAMESPACE__ . '\\cmx_add_uploads_adminbar', 120);
function cmx_add_uploads_adminbar(\WP_Admin_Bar $wp_admin_bar): void {
	if (!is_admin_bar_showing()) return;
	if (!current_user_can('upload_files')) return;

	$wp_admin_bar->add_node([
		'id'    => 'cmx-uploads',
		'title' => 'Uploads',
		'href'  => admin_url('admin.php?page=cmx-uploads'),
		'meta'  => [
			'title' => 'Dokumente hochladen',
		],
	]);
}

function cmx_render_uploads_page(): void {
	if (!current_user_can('upload_files')) {
		wp_die('Keine Berechtigung.');
	}

	$nonce = wp_create_nonce('cmx_uploads_upload');
	$list_url = admin_url('edit.php?post_type=dokumente');
	?>
	<div class="wrap">
		<h1>Uploads</h1>
		<p>Hier kannst du deine Dokumente schnell hochladen. Die Dateien werden unter <strong>Dokumente</strong> gespeichert.</p>
		<p><a class="button" href="<?php echo esc_url($list_url); ?>">Alle Dokumente ansehen</a></p>

		<div id="cmx-uploads-wrap" style="max-width:720px;">
			<div id="cmx-uploads-drop" style="border:2px dashed #ccd0d4;padding:18px;text-align:center;background:#fafafa;cursor:pointer;">
				<strong>Datei hier ablegen</strong><br><small>PDF, PNG, JPG</small>
			</div>
			<input type="file" id="cmx-uploads-file" style="display:none" multiple accept=".pdf,.png,.jpg,.jpeg">
			<div id="cmx-uploads-list" style="margin-top:12px;"></div>
		</div>
	</div>
	<script>
	jQuery(function($){
		var $drop = $("#cmx-uploads-drop");
		var $file = $("#cmx-uploads-file");
		var $list = $("#cmx-uploads-list");
		var nonce = <?php echo wp_json_encode($nonce); ?>;
		var ajaxurl = window.ajaxurl || <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;

		function addRow(text) {
			var $row = $("<div>").css({margin:"6px 0"}).text(text);
			$list.append($row);
			return $row;
		}

		function uploadFile(file){
			var fd = new FormData();
			fd.append("action", "cmx_uploads_upload_file");
			fd.append("nonce", nonce);
			fd.append("file", file);

			var $row = addRow("Upload: " + file.name);

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
							var $a = $("<a>").attr({
								href: resp.data.url,
								target: "_blank",
								rel: "noopener noreferrer"
							}).text(label);
							$row.empty().append($a);
						} else {
							$row.text(label);
						}
						if (resp.data.edit_url) {
							$row.append(" ").append(
								$("<a>").attr({
									href: resp.data.edit_url,
									target: "_blank",
									rel: "noopener noreferrer"
								}).text("[Bearbeiten]")
							);
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
	});
	</script>
	<?php
}

/* =========================================================
 * AJAX Upload (standalone)
 * ========================================================= */
add_action('wp_ajax_cmx_uploads_upload_file', __NAMESPACE__ . '\\cmx_uploads_upload_file');
function cmx_uploads_upload_file(): void {
	if (!current_user_can('upload_files')) wp_send_json_error(['message' => 'forbidden'], 403);
	$nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';
	if (!wp_verify_nonce($nonce, 'cmx_uploads_upload')) wp_send_json_error(['message' => 'bad_nonce'], 403);

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
	$base_name = sanitize_text_field(pathinfo($_FILES['file']['name'], PATHINFO_FILENAME));
	$base_slug = sanitize_key($base_name ?: 'upload');
	$doc_title = date('ymd-His', $ts) . '_' . $base_slug;

	$doc_id = wp_insert_post([
		'post_type'   => 'dokumente',
		'post_title'  => $doc_title,
		'post_status' => 'publish',
	], true);
	if (is_wp_error($doc_id) || !$doc_id) {
		wp_send_json_error(['message' => 'doc_create_failed'], 500);
	}

	$upload_filter = function($dirs) use ($year) {
		$base = WP_CONTENT_DIR . '/uploads/misbuero/' . $year . '/dokumente';
		$url  = content_url('/uploads/misbuero/' . $year . '/dokumente');
		if (!file_exists($base)) { wp_mkdir_p($base); }
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

	add_filter('upload_dir', $upload_filter);
	add_filter('intermediate_image_sizes', $no_sizes_filter_simple);
	add_filter('intermediate_image_sizes_advanced', $no_sizes_filter);
	add_filter('big_image_size_threshold', $no_big_image, 10, 0);
	$uploaded = wp_handle_upload($_FILES['file'], ['test_form' => false, 'mimes' => [
		'pdf'  => 'application/pdf',
		'png'  => 'image/png',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
	]]);

	if (!isset($uploaded['file'])) {
		remove_filter('big_image_size_threshold', $no_big_image, 10);
		remove_filter('intermediate_image_sizes_advanced', $no_sizes_filter);
		remove_filter('intermediate_image_sizes', $no_sizes_filter_simple);
		remove_filter('upload_dir', $upload_filter);
		wp_delete_post($doc_id, true);
		wp_send_json_error(['message' => 'upload_failed'], 500);
	}

	$attachment = [
		'post_mime_type' => $uploaded['type'] ?? '',
		'post_title'     => sanitize_text_field($doc_title),
		'post_content'   => '',
		'post_status'    => 'inherit',
		'post_parent'    => (int) $doc_id,
	];
	$att_id = wp_insert_attachment($attachment, $uploaded['file'], $doc_id);
	if ($att_id) {
		add_filter('wp_generate_attachment_metadata', $no_meta_sizes_filter, 10, 2);
		$meta = wp_generate_attachment_metadata($att_id, $uploaded['file']);
		remove_filter('wp_generate_attachment_metadata', $no_meta_sizes_filter, 10);
		if (is_array($meta)) {
			if (isset($meta['sizes'])) $meta['sizes'] = [];
			wp_update_attachment_metadata($att_id, $meta);
		}
	}

	remove_filter('big_image_size_threshold', $no_big_image, 10);
	remove_filter('intermediate_image_sizes_advanced', $no_sizes_filter);
	remove_filter('intermediate_image_sizes', $no_sizes_filter_simple);
	remove_filter('upload_dir', $upload_filter);

	// Datei umbenennen: YYMMDD-HHMMSS_slug.ext
	$base_dir = dirname($uploaded['file']);
	$new_base = sanitize_file_name($doc_title . '.' . $ext);
	$new_base = wp_unique_filename($base_dir, $new_base);
	$new_path = $base_dir . '/' . $new_base;
	if ($new_path !== $uploaded['file']) {
		$renamed = @rename($uploaded['file'], $new_path);
		if ($renamed) {
			$uploads_root = WP_CONTENT_DIR . '/uploads/';
			$rel = ltrim(str_replace($uploads_root, '', $new_path), '/');
			update_attached_file($att_id, $new_path);
			update_post_meta($att_id, '_wp_attached_file', $rel);
			wp_update_post([
				'ID'   => $att_id,
				'guid' => content_url('/uploads/' . $rel),
			]);
			$uploaded['file'] = $new_path;
		}
	}

	if (defined(__NAMESPACE__ . '\\CMX_DOK_SELF_META')) {
		$existing = (array) get_post_meta($doc_id, CMX_DOK_SELF_META, true);
		$existing = array_values(array_filter(array_map('intval', $existing)));
		$existing[] = (int) $att_id;
		$existing = array_values(array_unique($existing));
		update_post_meta($doc_id, CMX_DOK_SELF_META, $existing);
	}
	update_post_meta($doc_id, '_cmx_dokumente_attachment_id', (int) $att_id);

	wp_send_json_success([
		'id'       => (int) $doc_id,
		'url'      => $att_id ? wp_get_attachment_url($att_id) : '',
		'label'    => $base_name ?: $doc_title,
		'edit_url' => get_edit_post_link((int) $doc_id, 'raw'),
	]);
}
