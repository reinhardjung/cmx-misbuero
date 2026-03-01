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

/** Metabox-Renderer (nur Datei-Upload/Entfernen; keine Buttons/Texte) */
if (!\function_exists(__NAMESPACE__.'\\cmx_li_render_box_artikel')) {
	function cmx_li_render_box_artikel(\WP_Post $post): void {
		wp_nonce_field('cmx_li_artikel_nonce', 'cmx_li_artikel_nonce');

		$meta_base = '_cmx_local_image_artikel';
		$url       = (string) get_post_meta($post->ID, $meta_base . '_url', true);

		echo '<div class="cmx-li-box">';
		if ($url) {
			$display_url = esc_url($url);
			$path = parse_url($display_url, PHP_URL_PATH);
			$filename = $path ? basename($path) : ('artikel-' . (int) $post->ID . '.jpg');
			echo '<div style="margin-bottom:8px;"><a href="'.$display_url.'" download="'.esc_attr($filename).'" title="Bild herunterladen"><img src="'.$display_url.'" style="max-width:100%;height:auto;display:block;border:1px solid #ddd;padding:2px;background:#fff;" alt="" /></a></div>';
			echo '<input type="file" name="cmx_li_file" accept="image/*" style="width:100%;">';
			echo '<p style="margin-top:8px;"><label><input type="checkbox" name="cmx_li_remove" value="1"> Entfernen</label></p>';
		} else {
			echo '<em>Kein Artikelbild vorhanden.</em>';
			echo '<p style="margin-top:8px;"><input type="file" name="cmx_li_file" accept="image/*" style="width:100%;"></p>';
		}
		echo '</div>';
	}
}

/** Save-Handler NUR für "artikel" (inkl. Auto-Fetch von Bezugsquelle, wenn kein Upload) */
add_action('save_post_artikel', function ($post_id, $post, $update) {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!is_object($post) || $post->post_type !== 'artikel') return;
	if (!current_user_can('edit_post', $post_id)) return;
	if (empty($_POST['cmx_li_artikel_nonce']) || !wp_verify_nonce($_POST['cmx_li_artikel_nonce'], 'cmx_li_artikel_nonce')) return;

	$meta_base = '_cmx_local_image_artikel';

	// Entfernen?
	if (!empty($_POST['cmx_li_remove'])) {
		if (\function_exists(__NAMESPACE__.'\\cmx_li_delete_old')) {
			cmx_li_delete_old($post_id, $meta_base);
		}
		delete_post_meta($post_id, $meta_base . '_url');
		delete_post_meta($post_id, $meta_base . '_path');
		clean_post_cache($post_id);

		// WICHTIG: Bei Entfernen KEIN Auto-Fetch (und kein Text-Fetch) im selben Save
		return;
	}

	$base_dir = cmx_li_base_path();
	$base_url = cmx_li_base_url();
	if (!is_dir($base_dir)) {
		if (!wp_mkdir_p($base_dir)) {
			error_log('[CMX] Konnte Zielordner nicht erstellen: '.$base_dir);
			return;
		}
	}

	// Ziel-Dateinamen vorbereiten: {post_title}.ext (kleinbuchstaben)
	$basename   = cmx_li_basename_for_post($post);

	// 1) MANUELLER UPLOAD?
	if (!empty($_FILES['cmx_li_file']) && !empty($_FILES['cmx_li_file']['name'])) {
		$file = $_FILES['cmx_li_file'];
		if (!cmx_li_store_uploaded_file($file, $base_dir, $base_url, $basename, $post_id, $meta_base)) {
			error_log('[CMX] Manuelles Speichern fehlgeschlagen.');
		}
		// Beschreibung nur setzen, wenn leer
		// if (trim((string)$post->post_content) === '') {
		// 	$source_url = cmx_li_find_source_url($post_id);
		// 	if ($source_url) {
		// 		$desc = cmx_li_fetch_page_description($source_url);
		// 		if ($desc) {
		// 			wp_update_post([
		// 				'ID'           => $post_id,
		// 				'post_content' => sanitize_textarea_field($desc),
		// 			]);
		// 		}
		// 	}
		// }
		return; // manuell hat Vorrang
	}

	// 1b) Kein Upload: vorhandene Datei an geänderten Titel anpassen
	cmx_li_sync_filename_with_title($post_id, $post, $meta_base);

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

/** --- Manuelles Speichern eines Uploads --- */
if (!\function_exists(__NAMESPACE__.'\\cmx_li_store_uploaded_file')) {
	function cmx_li_store_uploaded_file(array $file, string $base_dir, string $base_url, string $basename, int $post_id, string $meta_base): bool {
		$allowed_mimes = [
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'gif'      => 'image/gif',
			'webp'     => 'image/webp',
			'avif'     => 'image/avif',
		];
		$check = wp_check_filetype_and_ext($file['tmp_name'] ?? '', $file['name'] ?? '', $allowed_mimes);
		if (empty($check['ext']) || empty($check['type'])) {
			return false;
		}
		$ext    = '.' . strtolower($check['ext']);
		$target = wp_normalize_path($base_dir . '/' . $basename . $ext);

		// alte Varianten entfernen
		foreach (['jpg','jpeg','png','gif','webp','avif'] as $e) {
			$existing = $base_dir . '/' . $basename . '.' . $e;
			if (file_exists($existing)) @unlink($existing);
		}

		if (!is_uploaded_file($file['tmp_name'])) return false;
		if (!@move_uploaded_file($file['tmp_name'], $target)) return false;
		@chmod($target, 0644);

		$version = @filemtime($target) ?: time();
		$url     = $base_url . '/' . rawurlencode(basename($target)) . '?v=' . $version;

		update_post_meta($post_id, $meta_base . '_path', $target);
		update_post_meta($post_id, $meta_base . '_url',  $url);
		clean_post_cache($post_id);
		return true;
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

/** --- Produkt-/Artikelbeschreibung der Bezugsquelle holen --- */
// if (!\function_exists(__NAMESPACE__.'\\cmx_li_fetch_page_description')) {
// 	function cmx_li_fetch_page_description(string $url): ?string {
// 		$response = wp_remote_get($url, ['timeout' => 12, 'redirection' => 5]);
// 		if (is_wp_error($response)) return null;
// 		$html = wp_remote_retrieve_body($response);
// 		if (!is_string($html) || $html === '') return null;

// 		// 1) og:description
// 		if (preg_match('~<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)["\']~i', $html, $m)) {
// 			$desc = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
// 			if ($desc !== '') return $desc;
// 		}
// 		// 2) twitter:description
// 		if (preg_match('~<meta[^>]+name=["\']twitter:description["\'][^>]+content=["\']([^"\']+)["\']~i', $html, $m)) {
// 			$desc = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
// 			if ($desc !== '') return $desc;
// 		}
// 		// 3) meta name="description"
// 		if (preg_match('~<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']~i', $html, $m)) {
// 			$desc = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
// 			if ($desc !== '') return $desc;
// 		}
// 		// 4) JSON-LD (Product/Article) description
// 		if (preg_match_all('~<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>~is', $html, $scripts)) {
// 			foreach ($scripts[1] as $json) {
// 				$json = html_entity_decode($json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
// 				$data = json_decode($json, true);
// 				if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
// 					// einzelne oder @graph
// 					$nodes = isset($data['@graph']) && is_array($data['@graph']) ? $data['@graph'] : [$data];
// 					foreach ($nodes as $node) {
// 						if (!is_array($node)) continue;
// 						$type = $node['@type'] ?? '';
// 						if (is_array($type)) $type = implode(',', $type);
// 						if (stripos((string)$type, 'Product') !== false || stripos((string)$type, 'Article') !== false) {
// 							if (!empty($node['description']) && is_string($node['description'])) {
// 								$desc = trim($node['description']);
// 								if ($desc !== '') return $desc;
// 							}
// 						}
// 					}
// 				}
// 			}
// 		}
// 		// 5) Fallback: erster <p> mit etwas Länge
// 		if (preg_match('~<p[^>]*>(.*?)</p>~is', $html, $m)) {
// 			$plain = trim(wp_strip_all_tags($m[1]));
// 			if (mb_strlen($plain) >= 40) return $plain;
// 		}
// 		return null;
// 	}
// }

/**
 * --- Aus einer Bezugsquelle eine Bild-URL ermitteln ---
 * - Falls bereits direkte Bild-URL: zurückgeben
 * - Falls HTML-Seite: versuche og:image / twitter:image / <link rel="image_src">
 */
// if (!\function_exists(__NAMESPACE__.'\\cmx_li_resolve_image_url')) {
// 	function cmx_li_resolve_image_url(string $source): ?string {
// 		$source = trim($source);
// 		if ($source === '') return null;

// 		// Direktes Bild?
// 		$img_ext = '(jpe?g|png|gif|webp|avif|bmp)';
// 		if (preg_match('~\.'.$img_ext.'($|\?)~i', parse_url($source, PHP_URL_PATH) ?? '')) {
// 			return $source;
// 		}

// 		// HEAD prüfen (Content-Type)
// 		$head = wp_remote_head($source, ['timeout' => 10, 'redirection' => 5]);
// 		if (!is_wp_error($head)) {
// 			$ct = wp_remote_retrieve_header($head, 'content-type');
// 			if (is_string($ct) && stripos($ct, 'image/') === 0) {
// 				return $source; // Quelle ist bereits ein Bild
// 			}
// 		}

// 		// HTML holen und nach og:image / twitter:image / link[rel=image_src] suchen
// 		$resp = wp_remote_get($source, ['timeout' => 12, 'redirection' => 5]);
// 		if (is_wp_error($resp)) return null;

// 		$body = wp_remote_retrieve_body($resp);
// 		if (!is_string($body) || $body === '') return null;

// 		$patterns = [
// 			'~<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']~i',
// 			'~<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']~i',
// 			'~<link[^>]+rel=["\']image_src["\'][^>]+href=["\']([^"\']+)["\']~i',
// 		];
// 		foreach ($patterns as $p) {
// 			if (preg_match($p, $body, $m) && !empty($m[1])) {
// 				$img = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
// 				// Relativ-URL auflösen
// 				if (!preg_match('~^https?://~i', $img)) {
// 					$img = wp_make_link_relative($img) === $img
// 						? rtrim(cmx_li_origin($source), '/') . '/' . ltrim($img, '/')
// 						: $img;
// 				}
// 				return $img;
// 			}
// 		}
// 		return null;
// 	}
// }

/** --- Origin einer URL (Schema + Host + ggf. Port) --- */
// if (!\function_exists(__NAMESPACE__.'\\cmx_li_origin')) {
// 	function cmx_li_origin(string $url): string {
// 		$parts = wp_parse_url($url);
// 		if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return $url;
// 		$origin = $parts['scheme'] . '://' . $parts['host'];
// 		if (!empty($parts['port'])) $origin .= ':' . (int)$parts['port'];
// 		return $origin;
// 	}
// }

/** --- Remote-Bild speichern (download_url -> umbenennen) --- */
// if (!\function_exists(__NAMESPACE__.'\\cmx_li_store_remote_image')) {
// 	function cmx_li_store_remote_image(string $img_url, string $base_dir, string $base_url, string $basename, int $post_id, string $meta_base): bool {
// 		$tmp = download_url($img_url, 15);
// 		if (is_wp_error($tmp) || !is_string($tmp) || !file_exists($tmp)) {
// 			return false;
// 		}

// 		// Content-Type prüfen
// 		$headers = wp_getimagesize($tmp);
// 		$ext = null;
// 		if (is_array($headers) && !empty($headers['mime'])) {
// 			$mime = strtolower($headers['mime']);
// 			$map  = [
// 				'image/jpeg' => 'jpg',
// 				'image/png'  => 'png',
// 				'image/gif'  => 'gif',
// 				'image/webp' => 'webp',
// 				'image/avif' => 'avif',
// 				'image/bmp'  => 'bmp',
// 			];
// 			if (isset($map[$mime])) $ext = $map[$mime];
// 		}
// 		// Fallback: aus URL ableiten
// 		if (!$ext) {
// 			if (preg_match('~\.(jpe?g|png|gif|webp|avif|bmp)(?:$|\?)~i', parse_url($img_url, PHP_URL_PATH) ?? '', $m)) {
// 				$ext = strtolower($m[1] === 'jpeg' ? 'jpg' : $m[1]);
// 			} else {
// 				$ext = 'jpg';
// 			}
// 		}

// 		$target = wp_normalize_path($base_dir . '/' . $basename . '.' . $ext);

// 		// alte Varianten entfernen
// 		foreach (['jpg','jpeg','png','gif','webp','avif','bmp'] as $e) {
// 			$existing = $base_dir . '/' . $basename . '.' . $e;
// 			if (file_exists($existing)) @unlink($existing);
// 		}

// 		$ok = @rename($tmp, $target);
// 		if (!$ok) {
// 			@unlink($tmp);
// 			return false;
// 		}
// 		@chmod($target, 0644);

// 		$version = @filemtime($target) ?: time();
// 		$url     = $base_url . '/' . rawurlencode(basename($target)) . '?v=' . $version;

// 		update_post_meta($post_id, $meta_base . '_path', $target);
// 		update_post_meta($post_id, $meta_base . '_url',  $url);
// 		clean_post_cache($post_id);
// 		return true;
// 	}
// }

/** Alte Datei löschen */
if (!\function_exists(__NAMESPACE__.'\\cmx_li_delete_old')) {
	function cmx_li_delete_old(int $post_id, string $meta_base): void {
		$old = (string) get_post_meta($post_id, $meta_base . '_path', true);
		if ($old && file_exists($old)) {
			@unlink($old);
		}
	}
}
