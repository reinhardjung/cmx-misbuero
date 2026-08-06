<?php
namespace CLOUDMEISTER\CMX\MisBuero;
defined('ABSPATH') || exit;

/**
 * Lokale Bildverwaltung ohne Mediathek:
 * - Ablage: /wp-content/uploads/misbuero/archiv/bilder/{post_type}/
 * - Dateiname: {post_title}.ext (kleinbuchstaben)
 * - Überschreiben bestehender Dateien
 * - Cache-Busting via ?v=filemtime
 * - Kontakte: Label "Logo" oder (bei _cmx_privat=1) "Foto"
 * - KEINE Checkbox "Privat" in der Metabox (nur Auswertung bestehender Meta)
 */

/** Basis-Unterordner relativ zu uploads/ */
if (!defined(__NAMESPACE__.'\\CMX_LOCAL_IMG_SUBDIR')) {
	define(__NAMESPACE__.'\\CMX_LOCAL_IMG_SUBDIR', '/misbuero/archiv/bilder');
}

/** CPT-Konfiguration */
function cmx_local_image_cpt_map(): array {
	$map = [];
	return (array) apply_filters('cmx_local_image_cpt_map', $map);
}

/** Upload-Basis */
function cmx_local_base_path(): string {
	$u = wp_get_upload_dir();
	return wp_normalize_path($u['basedir'] . CMX_LOCAL_IMG_SUBDIR);
}
function cmx_local_base_url(): string {
	$u = wp_get_upload_dir();
	return rtrim($u['baseurl'], '/') . CMX_LOCAL_IMG_SUBDIR;
}

/** Ziel-Unterordner je CPT */
function cmx_local_image_subdir_for_post_type(string $post_type): string {
	$post_type = strtolower(trim($post_type));
	if ($post_type === 'kontakte') return 'kontakte';
	if ($post_type === 'artikel') return 'artikel';
	return sanitize_title($post_type);
}

/** Dateibasenamen aus Post-Titel ableiten */
function cmx_local_image_basename_for_post(\WP_Post $post): string {
	$post_type = strtolower((string)($post->post_type ?? ''));
	$title_slug = strtolower((string) sanitize_title(get_the_title($post) ?: $post_type));
	return $title_slug !== '' ? $title_slug : ($post_type !== '' ? $post_type : 'bild');
}

/**
 * Bringt ein Bild auf exakt target_w x target_h (Cover + zentrierter Beschnitt).
 * Vergrößert bei Bedarf ebenfalls.
 */
function cmx_local_image_normalize_to_fixed_size(string $path, int $target_w = 1920, int $target_h = 1080): bool {
	if ($path === '' || !is_file($path) || $target_w <= 0 || $target_h <= 0) {
		return false;
	}

	$info = @getimagesize($path);
	if (!is_array($info)) {
		return false;
	}

	$src_w = (int)($info[0] ?? 0);
	$src_h = (int)($info[1] ?? 0);
	$mime  = strtolower((string)($info['mime'] ?? ''));
	if ($src_w <= 0 || $src_h <= 0 || $mime === '') {
		return false;
	}

	// Bevorzugt Imagick (robust bei Formaten/Profilen), dann GD als Fallback.
	if (class_exists('\Imagick')) {
		try {
			$im = new \Imagick();
			$im->readImage($path);
			$im->setIteratorIndex(0);

			$iw = (int)$im->getImageWidth();
			$ih = (int)$im->getImageHeight();
			if ($iw > 0 && $ih > 0) {
				$scale = max($target_w / $iw, $target_h / $ih);
				$new_w = max($target_w, (int) ceil($iw * $scale));
				$new_h = max($target_h, (int) ceil($ih * $scale));

				$im->resizeImage($new_w, $new_h, \Imagick::FILTER_LANCZOS, 1.0, true);
				$crop_x = (int) floor(($new_w - $target_w) / 2);
				$crop_y = (int) floor(($new_h - $target_h) / 2);
				$im->cropImage($target_w, $target_h, $crop_x, $crop_y);
				$im->setImagePage(0, 0, 0, 0);

				if ($mime === 'image/jpeg') {
					$im->setImageFormat('jpeg');
					$im->setImageCompressionQuality(90);
				} elseif ($mime === 'image/png') {
					$im->setImageFormat('png');
				} elseif ($mime === 'image/webp') {
					$im->setImageFormat('webp');
					$im->setImageCompressionQuality(90);
				} elseif ($mime === 'image/avif') {
					$im->setImageFormat('avif');
					$im->setImageCompressionQuality(50);
				} elseif ($mime === 'image/gif') {
					$im->setImageFormat('gif');
				}

				$ok = (bool) $im->writeImage($path);
				$im->clear();
				$im->destroy();
				if ($ok) {
					return true;
				}
			}
		} catch (\Throwable $e) {
			// Fallback auf GD
		}
	}

	$src = null;
	if ($mime === 'image/jpeg') {
		$src = @imagecreatefromjpeg($path);
	} elseif ($mime === 'image/png') {
		$src = @imagecreatefrompng($path);
	} elseif ($mime === 'image/gif') {
		$src = @imagecreatefromgif($path);
	} elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
		$src = @imagecreatefromwebp($path);
	} elseif ($mime === 'image/avif' && function_exists('imagecreatefromavif')) {
		$src = @imagecreatefromavif($path);
	}
	if (!$src) {
		return false;
	}

	$preserve_alpha = in_array($mime, ['image/png', 'image/gif', 'image/webp', 'image/avif'], true);
	$scale = max($target_w / $src_w, $target_h / $src_h);
	$resized_w = max($target_w, (int) ceil($src_w * $scale));
	$resized_h = max($target_h, (int) ceil($src_h * $scale));

	$tmp = imagecreatetruecolor($resized_w, $resized_h);
	if (!$tmp) {
		imagedestroy($src);
		return false;
	}
	if ($preserve_alpha) {
		imagealphablending($tmp, false);
		imagesavealpha($tmp, true);
		$transparent = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
		imagefilledrectangle($tmp, 0, 0, $resized_w, $resized_h, $transparent);
	}
	imagecopyresampled($tmp, $src, 0, 0, 0, 0, $resized_w, $resized_h, $src_w, $src_h);

	$dst = imagecreatetruecolor($target_w, $target_h);
	if (!$dst) {
		imagedestroy($tmp);
		imagedestroy($src);
		return false;
	}
	if ($preserve_alpha) {
		imagealphablending($dst, false);
		imagesavealpha($dst, true);
		$transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
		imagefilledrectangle($dst, 0, 0, $target_w, $target_h, $transparent);
	}

	$crop_x = (int) floor(($resized_w - $target_w) / 2);
	$crop_y = (int) floor(($resized_h - $target_h) / 2);
	imagecopy($dst, $tmp, 0, 0, $crop_x, $crop_y, $target_w, $target_h);

	$saved = false;
	if ($mime === 'image/jpeg') {
		$saved = (bool) @imagejpeg($dst, $path, 90);
	} elseif ($mime === 'image/png') {
		$saved = (bool) @imagepng($dst, $path, 6);
	} elseif ($mime === 'image/gif') {
		$saved = (bool) @imagegif($dst, $path);
	} elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
		$saved = (bool) @imagewebp($dst, $path, 90);
	} elseif ($mime === 'image/avif' && function_exists('imageavif')) {
		$saved = (bool) @imageavif($dst, $path, 50);
	}

	imagedestroy($dst);
	imagedestroy($tmp);
	imagedestroy($src);

	return $saved;
}

/** Bestehende Datei auf aktuellen Post-Titel umbenennen */
function cmx_local_image_sync_filename_with_title(int $post_id, \WP_Post $post, string $meta_base): void {
	$current_path = (string) get_post_meta($post_id, $meta_base . '_path', true);
	if ($current_path === '' || !is_file($current_path)) {
		return;
	}

	$post_type = strtolower((string)($post->post_type ?? ''));
	$type_subdir = cmx_local_image_subdir_for_post_type($post_type);
	$base_dir = cmx_local_base_path();
	$base_url = cmx_local_base_url();
	if ($type_subdir !== '') {
		$base_dir = wp_normalize_path($base_dir . '/' . $type_subdir);
		$base_url = rtrim($base_url, '/') . '/' . rawurlencode($type_subdir);
	}
	if (!is_dir($base_dir)) {
		wp_mkdir_p($base_dir);
	}

	$ext = strtolower((string) pathinfo($current_path, PATHINFO_EXTENSION));
	if ($ext === '') {
		return;
	}
	$basename = cmx_local_image_basename_for_post($post);
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

/** Edit-Form darf Dateien senden */
add_action('post_edit_form_tag', function () {
	echo ' enctype="multipart/form-data"';
});

/** Metabox hinzufügen */
add_action('add_meta_boxes', function() {
	$map = cmx_local_image_cpt_map();
	foreach (array_keys($map) as $pt) {
		add_meta_box(
			"cmx_local_image_box_{$pt}",
			esc_html($map[$pt]['label']),
			__NAMESPACE__ . '\\cmx_render_local_image_box',
			$pt,
			'side',
			'low'
		);
	}
});

/** Metabox-UI (ohne Checkbox "Privat") */
function cmx_render_local_image_box(\WP_Post $post) {
	$map   = cmx_local_image_cpt_map();
	$pt    = $post->post_type;
	$conf  = $map[$pt] ?? ['label'=>'Bild', 'meta'=>'_cmx_local_image'];
	$metaB = $conf['meta'];

	$label = $conf['label'];
	if ($pt === 'kontakte') {
		$is_privat = (bool) get_post_meta($post->ID, '_cmx_privat', true);
		$label     = $is_privat ? 'Foto' : 'Logo';
	}

	wp_nonce_field('cmx_local_image_nonce', 'cmx_local_image_nonce');

	$url = (string) get_post_meta($post->ID, $metaB . '_url', true);

	echo '<div class="cmx-li-box">';

	// Nur Hinweis, welches Feld aktuell gilt (kein UI-Schalter)
	if ($pt === 'kontakte') {
		//echo '<p style="margin:0 0 8px;color:#666;">Aktuelles Feld: <strong>' . esc_html($label) . '</strong></p>';
	}

	// Bild-Preview + Upload
		if ($url) {
			$display_url = esc_url($url);
			$path = parse_url($display_url, PHP_URL_PATH);
			$filename = $path ? basename($path) : ($pt . '-' . (int) $post->ID . '.jpg');
			echo '<div style="margin-bottom:8px;"><a href="' . $display_url . '" download="' . esc_attr($filename) . '" title="Bild herunterladen" style="display:block;max-width:100%;"><img src="' . $display_url . '" style="max-width:100%;height:auto;display:block;border:1px solid #ddd;padding:2px;background:#fff;" alt="" /></a></div>';
			echo '<p style="margin:0 0 6px;color:#666;font-size:12px;">Ideale Grösse: 1920x1080px</p>';
			echo '<input type="file" name="cmx_local_image_file" accept="image/*" style="width:100%;" />';
			echo '<p style="margin-top:8px;"><label><input type="checkbox" name="cmx_local_image_remove" value="1"> Entfernen</label></p>';
		} else {
			echo '<em>Kein ' . esc_html($label) . ' hinterlegt.</em>';
			echo '<p style="margin:8px 0 6px;color:#666;font-size:12px;">Ideale Grösse: 1920x1080px</p>';
			echo '<p style="margin-top:8px;"><input type="file" name="cmx_local_image_file" accept="image/*" style="width:100%;" /></p>';
		}

	// echo '<p style="color:#666;margin-top:8px;">Nach Auswahl normal „Aktualisieren“ klicken. Datei wird lokal gespeichert (nicht in der Mediathek).</p>';
	echo '</div>';
}

/** Save-Handler (ohne Speicherung von _cmx_privat) */
add_action('save_post', function($post_id, $post) {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!current_user_can('edit_post', $post_id)) return;
	if (!isset($_POST['cmx_local_image_nonce']) || !wp_verify_nonce($_POST['cmx_local_image_nonce'], 'cmx_local_image_nonce')) return;

	$pt   = $post->post_type;
	$map  = cmx_local_image_cpt_map();
	if (!isset($map[$pt])) return; // nur konfigurierte CPTs

	$metaB = $map[$pt]['meta'];

	// Entfernen?
	if (!empty($_POST['cmx_local_image_remove'])) {
		cmx_delete_local_image_file_by_meta($post_id, $metaB);
		delete_post_meta($post_id, $metaB . '_url');
		delete_post_meta($post_id, $metaB . '_path');
		clean_post_cache($post_id);
	}

	// Kein neuer Upload?
	if (empty($_FILES['cmx_local_image_file']) || empty($_FILES['cmx_local_image_file']['name'])) {
		cmx_local_image_sync_filename_with_title($post_id, $post, $metaB);
		return;
	}

	$file = $_FILES['cmx_local_image_file'];

	// Nur Bildtypen zulassen
	$allowed_mimes = [
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
		'gif'      => 'image/gif',
		'webp'     => 'image/webp',
		'avif'     => 'image/avif',
		// 'svg'   => 'image/svg+xml', // optional, Vorsicht
	];

	$check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed_mimes);
	if (!$check['ext'] || !$check['type']) {
		error_log('[CMX] Abgewiesen: nicht erlaubter Bildtyp: '.$file['name']);
		return;
	}

	// Zielverzeichnis sicherstellen
	$base_dir = cmx_local_base_path();
	$base_url = cmx_local_base_url();
	$type_subdir = cmx_local_image_subdir_for_post_type($pt);
	if ($type_subdir !== '') {
		$base_dir = wp_normalize_path($base_dir . '/' . $type_subdir);
		$base_url = rtrim($base_url, '/') . '/' . rawurlencode($type_subdir);
	}
	if (!is_dir($base_dir)) {
		wp_mkdir_p($base_dir);
	}

	// Dateiname: {post_title}.ext (kleinbuchstaben)
	$title_slug = cmx_local_image_basename_for_post($post);
	$ext        = '.' . strtolower($check['ext']); // z.B. ".jpg"
	$basename   = $title_slug !== '' ? $title_slug : strtolower((string) $pt);
	$target     = wp_normalize_path($base_dir . '/' . $basename . $ext);

	// Existierende Varianten mit gleichem Basenamen entfernen (erzwingt Überschreiben)
	foreach (['jpg','jpeg','png','gif','webp','avif'] as $e) {
		$existing = $base_dir . '/' . $basename . '.' . $e;
		if (file_exists($existing)) {
			@unlink($existing);
		}
	}

	// Datei verschieben (ohne Attachment/Mediathek)
	if (!is_uploaded_file($file['tmp_name'])) {
		error_log('[CMX] Upload-Quelle ist keine hochgeladene Datei.');
		return;
	}
	if (!@move_uploaded_file($file['tmp_name'], $target)) {
		error_log('[CMX] move_uploaded_file fehlgeschlagen nach: ' . $target);
		return;
	}
	@chmod($target, 0644);
	if (!cmx_local_image_normalize_to_fixed_size($target, 1920, 1080)) {
		error_log('[CMX] Hinweis: Bild konnte nicht auf 1920x1080 normalisiert werden: ' . $target);
	}

	// Cache-Busting: ?v=filemtime
	$version = @filemtime($target) ?: time();
	$url     = $base_url . '/' . rawurlencode($basename . $ext) . '?v=' . $version;

	// Metas setzen
	update_post_meta($post_id, $metaB . '_path', $target);
	update_post_meta($post_id, $metaB . '_url',  $url);

	// WP-Caches bereinigen
	clean_post_cache($post_id);

}, 10, 2);

/** Altes Bild physisch löschen, wenn vorhanden */
function cmx_delete_local_image_file_by_meta(int $post_id, string $metaBase): void {
	$old = (string) get_post_meta($post_id, $metaBase . '_path', true);
	if ($old && file_exists($old)) {
		@unlink($old);
	}
}

/** Vorschau lokaler Bilder in ihrer tatsächlichen Originalgrösse. */
add_action('admin_footer-post.php', __NAMESPACE__ . '\\cmx_local_image_original_preview_script');
add_action('admin_footer-post-new.php', __NAMESPACE__ . '\\cmx_local_image_original_preview_script');
function cmx_local_image_original_preview_script(): void {
	?>
	<script>
	document.addEventListener("click", function (event) {
		var trigger = event.target && event.target.closest
			? event.target.closest(".cmx-local-image-original-preview")
			: null;
		if (!trigger) return;

		event.preventDefault();
		event.stopPropagation();
		var src = trigger.getAttribute("data-preview-url") || "";
		if (!src) return;

		var overlay = document.createElement("div");
		overlay.className = "cmx-local-image-original-overlay";
		overlay.setAttribute("role", "dialog");
		overlay.setAttribute("aria-modal", "true");
		overlay.setAttribute("aria-label", "Bildvorschau");
		overlay.style.cssText = "position:fixed;inset:0;z-index:1000000;overflow:auto;padding:48px;background:rgba(15,23,42,.82);box-sizing:border-box;text-align:center;";

		var close = document.createElement("button");
		close.type = "button";
		close.setAttribute("aria-label", "Vorschau schliessen");
		close.innerHTML = "&times;";
		close.style.cssText = "position:fixed;z-index:2;top:14px;right:18px;display:flex;align-items:center;justify-content:center;width:38px;height:38px;padding:0;border:1px solid #c3c4c7;border-radius:8px;background:#fff;color:#1d2327;font-size:26px;line-height:1;cursor:pointer;";

		var image = document.createElement("img");
		image.src = src;
		image.alt = "";
		image.style.cssText = "display:inline-block;width:auto;height:auto;max-width:none;max-height:none;background:#fff;box-shadow:0 18px 60px rgba(0,0,0,.45);vertical-align:top;";

		function closePreview() {
			document.removeEventListener("keydown", onKeydown);
			overlay.remove();
		}
		function onKeydown(keyEvent) {
			if (keyEvent.key === "Escape") closePreview();
		}

		close.addEventListener("click", closePreview);
		overlay.addEventListener("click", function (overlayEvent) {
			if (overlayEvent.target === overlay) closePreview();
		});
		overlay.appendChild(close);
		overlay.appendChild(image);
		document.body.appendChild(overlay);
		document.addEventListener("keydown", onKeydown);
		close.focus();
	});
	</script>
	<?php
}
