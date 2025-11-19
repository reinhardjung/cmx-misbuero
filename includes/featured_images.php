<?php
namespace CLOUDMEISTER\CMX\MisBuero;
defined('ABSPATH') || exit;

/**
 * Lokale Bildverwaltung ohne Mediathek:
 * - Ablage: /wp-content/uploads/misbuero/bilder/
 * - Dateiname: {post_title}_{post_id}.ext
 * - Überschreiben bestehender Dateien
 * - Cache-Busting via ?v=filemtime
 * - Kontakte: Label "Logo" oder (bei _cmx_privat=1) "Foto"
 * - KEINE Checkbox "Privat" in der Metabox (nur Auswertung bestehender Meta)
 */

/** Basis-Unterordner relativ zu uploads/ */
if (!defined(__NAMESPACE__.'\\CMX_LOCAL_IMG_SUBDIR')) {
	define(__NAMESPACE__.'\\CMX_LOCAL_IMG_SUBDIR', '/misbuero/bilder');
}

/** CPT-Konfiguration */
function cmx_local_image_cpt_map(): array {
	$map = [
		// 'artikel'  => ['label' => 'Bild', 'meta' => '_cmx_local_image_artikel'],
		'kontakte' => ['label' => 'Logo', 'meta' => '_cmx_local_image_kontakte'], // dynamisch zu "Foto" bei _cmx_privat=1
	];
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
		echo '<div style="margin-bottom:8px;"><img src="' . $display_url . '" style="max-width:100%;height:auto;display:block;border:1px solid #ddd;padding:2px;background:#fff;" alt="" /></div>';
		echo '<input type="file" name="cmx_local_image_file" accept="image/*" style="width:100%;" />';
		echo '<p style="margin-top:8px;"><label><input type="checkbox" name="cmx_local_image_remove" value="1"> Entfernen</label></p>';
	} else {
		echo '<em>Kein ' . esc_html($label) . ' hinterlegt.</em>';
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
	if (!is_dir($base_dir)) {
		wp_mkdir_p($base_dir);
	}

	// Dateiname: {post_title}_{post_id}.ext
	$title_slug = sanitize_title(get_the_title($post) ?: $pt);
	$ext        = '.' . strtolower($check['ext']); // z.B. ".jpg"
	$basename   = $title_slug . '_' . (int)$post_id;
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
