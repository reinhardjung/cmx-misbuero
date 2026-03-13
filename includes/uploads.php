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
	$uploads_title = '<a href="' . \esc_url($scanner_url) . '" target="_blank" rel="noopener noreferrer" style="text-decoration:none;font-weight:700;font-size:14px;line-height:1.2;" onclick="event.stopPropagation();">Uploads</a>';
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
		if ($docs) {
			update_post_meta($post->ID, CMX_BELEG_UPLOADS_META, $docs);
		}
	}

	echo '<div id="cmx-belege-upload-box">';
	echo '<div id="cmx-belege-drop" style="border:2px dashed #ccd0d4;padding:10px;text-align:center;background:#fafafa;cursor:pointer;">';
	echo '<strong>Datei hier ablegen oder auswählen</strong><br><small>PDF, PNG, JPG, CSV, XML</small>';
	echo '</div>';
	echo '<input type="file" id="cmx-belege-file" style="display:none" accept=".pdf,.png,.jpg,.jpeg">';
	echo '</div>';

	if ($docs) {
		echo '<ul id="cmx-belege-existing" style="margin:6px 0 0 0;padding:0;list-style:none;max-height:160px;overflow:auto;width:100%;">';
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
			if (!$file_abs || !is_file($file_abs)) {
				continue;
			}
			$norm = str_replace('\\', '/', $file_abs);
			if (strpos($norm, '/uploads/misbuero/') === false || strpos($norm, '/belege/') === false) {
				continue;
			}
			$file_base = $file_rel ? basename($file_rel) : '';
			$clean_docs[] = $att_id ?: $file_rel;
			$label = $file_base ?: ($att_id ? (get_the_title($att_id) ?: ('#' . $att_id)) : basename($file_abs));
			$data_attr = $att_id ? 'data-att-id="' . (int) $att_id . '"' : 'data-path="' . esc_attr($file_rel) . '"';
			echo '<li ' . $data_attr . ' style="display:grid;grid-template-columns:1fr 14px;align-items:center;gap:4px;width:100%;white-space:nowrap;">';
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
	// Do not mutate stored meta here; only filter display.

		echo '<script>
		jQuery(function($){
			var $drop = $("#cmx-belege-drop");
			var $file = $("#cmx-belege-file");
			var postId = ' . (int) $post->ID . ';
			var nonce = ' . wp_json_encode($nonce) . ';
			var ajaxurl = ' . wp_json_encode(admin_url('admin-ajax.php')) . ';
			var saveTimer = null;

			function triggerPostSave(){
				var form = document.getElementById("post");
				if (!form) return;
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
				if (saveTimer) window.clearTimeout(saveTimer);
				saveTimer = window.setTimeout(triggerPostSave, 450);
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
							queuePostSave();
						} else {
							var $notice = $("#cmx-belege-upload-notice");
						if (!$notice.length) {
							$notice = $("<div id=\"cmx-belege-upload-notice\" class=\"notice notice-error is-dismissible\" style=\"margin:8px 0;\"><p></p></div>");
							$("#poststuff").before($notice);
						}
						var msg = (resp && resp.data && resp.data.message) ? resp.data.message : "Fehler beim Upload";
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
					$notice.find("p").text((extra ? extra + " - " : "") + "Fehler beim Upload: " + file.name);
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
			var path = $li.data("path");
			if (!attId && !path) return;
			if (!confirm("Datei entfernen?")) return;
				$.post(ajaxurl, { action:"cmx_belege_remove_file", post_id: postId, att_id: attId, path: path, nonce: nonce }, function(resp){
					if (resp && resp.success) {
						$li.remove();
						queuePostSave();
					}
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
		remove_filter('big_image_size_threshold', $no_big_image, 10);
		remove_filter('intermediate_image_sizes_advanced', $no_sizes_filter);
		remove_filter('intermediate_image_sizes', $no_sizes_filter);
		remove_filter('upload_dir', $upload_filter);
		wp_send_json_error(['message' => 'Upload fehlgeschlagen.'], 500);
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

	$label = isset($uploaded['file']) && $uploaded['file'] !== ''
		? basename($uploaded['file'])
		: $post_slug . '_upload.' . $ext;
	wp_send_json_success([
		'id'    => 0,
		'url'   => content_url('/uploads/' . $rel),
		'label' => $label,
		'path'  => $rel,
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
	$path = isset($_POST['path']) ? (string) $_POST['path'] : '';
	if ($post_id <= 0 || get_post_type($post_id) !== 'belege') {
		wp_send_json_error(['message' => 'bad_params'], 400);
	}

	$existing = (array) get_post_meta($post_id, CMX_BELEG_UPLOADS_META, true);
	$existing = array_values(array_filter($existing, function($v){ return $v !== '' && $v !== null; }));
	if ($att_id) {
		$existing = array_values(array_diff($existing, [$att_id, (string) $att_id]));
	}
	if ($path !== '') {
		$existing = array_values(array_diff($existing, [$path]));
		$abs = WP_CONTENT_DIR . '/uploads/' . ltrim($path, '/');
		if (is_file($abs)) {
			@unlink($abs);
		}
	}
	update_post_meta($post_id, CMX_BELEG_UPLOADS_META, $existing);
	if ($att_id) {
		wp_delete_attachment($att_id, true);
	}

	wp_send_json_success(['ok' => true]);
}
