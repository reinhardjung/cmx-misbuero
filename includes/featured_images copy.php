<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * 1) Das Edit-Formular muss Uploads erlauben (enctype).
 */
add_action('post_edit_form_tag', function () {
    echo ' enctype="multipart/form-data"';
});

/**
 * 2) Metabox „Beitragsbild (CMX)“
 */
add_action('add_meta_boxes', function() {
    add_meta_box(
        'cmx_featured_image_box',
        'Beitragsbild (CMX)',
        __NAMESPACE__ . '\\render_cmx_featured_image_box',
        null,           // alle Post Types; wenn einschränken: 'post' o. CPT
        'side',
        'low'
    );
});

/**
 * Metabox-UI
 */
function render_cmx_featured_image_box(\WP_Post $post) {
    wp_nonce_field('cmx_set_featured_image', 'cmx_featured_image_nonce');

    $thumb_id = get_post_thumbnail_id($post->ID);
    $thumb    = $thumb_id ? wp_get_attachment_image($thumb_id, 'medium', false, ['style'=>'max-width:100%;height:auto;display:block;']) : '';

    echo '<div class="cmx-fi-box">';

    if ($thumb_id && $thumb) {
        echo '<div class="cmx-fi-preview" style="margin-bottom:8px;">' . $thumb . '</div>';
        echo '<label class="screen-reader-text" for="cmx_featured_image">Neues Bild wählen</label>';
        echo '<input type="file" id="cmx_featured_image" name="cmx_featured_image" accept="image/*" style="width:100%;" />';
        echo '<p style="margin-top:8px;"><label><input type="checkbox" name="cmx_featured_image_remove" value="1" /> Entfernen</label></p>';
    } else {
        echo '<em>Kein Bild gesetzt.</em>';
        echo '<p style="margin-top:8px;">';
        echo '<label class="screen-reader-text" for="cmx_featured_image">Bild wählen</label>';
        echo '<input type="file" id="cmx_featured_image" name="cmx_featured_image" accept="image/*" style="width:100%;" />';
        echo '</p>';
    }

    echo '<p style="color:#666;margin-top:8px;">Nach Auswahl einfach normal auf „Aktualisieren“ speichern.</p>';
    echo '</div>';
}

/**
 * 3) Upload-Verzeichnis für diesen gezielten Upload umbiegen
 *    -> nur während des einen Uploads aktiv!
 */
function cmx_upload_dir_featured(array $dirs): array {
    $subdir = '/misbuero/bilder';
    $dirs['subdir'] = $subdir;
    $dirs['path']   = wp_normalize_path($dirs['basedir'] . $subdir);
    $dirs['url']    = $dirs['baseurl'] . $subdir;

    if (!is_dir($dirs['path'])) {
        wp_mkdir_p($dirs['path']);
    }
    return $dirs;
}

/**
 * 4) Speichern beim normalen „Aktualisieren“
 */
add_action('save_post', function($post_id, $post) {
    // Standard-Guards
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (!isset($_POST['cmx_featured_image_nonce']) || !wp_verify_nonce($_POST['cmx_featured_image_nonce'], 'cmx_set_featured_image')) return;

    // Entfernen?
    if (!empty($_POST['cmx_featured_image_remove'])) {
        delete_post_thumbnail($post_id);
    }

    // Nichts hochgeladen?
    if (empty($_FILES['cmx_featured_image']) || empty($_FILES['cmx_featured_image']['name'])) {
        return;
    }

    // Nur Bildtypen erlauben
    $file = $_FILES['cmx_featured_image'];
    if (!empty($file['type']) && strpos($file['type'], 'image/') !== 0) {
        // kein Abbruch der Speicherung insgesamt; nur dieses Feld ignorieren
        error_log('[CMX] Upload abgewiesen, kein Bild: ' . $file['type']);
        return;
    }

    // Für diesen einen Upload das Ziel umbiegen
    add_filter('upload_dir', __NAMESPACE__ . '\\cmx_upload_dir_featured', 99);

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $overrides = ['test_form' => false, 'mimes' => null]; // WP prüft Mimes selbst
    $uploaded  = wp_handle_upload($file, $overrides);

    // Filter wieder entfernen
    remove_filter('upload_dir', __NAMESPACE__ . '\\cmx_upload_dir_featured', 99);

    if (!empty($uploaded['error'])) {
        error_log('[CMX] Featured-Image-Upload fehlgeschlagen: ' . $uploaded['error']);
        return;
    }

    // Attachment anlegen
    $attachment = [
        'post_mime_type' => $uploaded['type'] ?? 'image/jpeg',
        'post_title'     => sanitize_file_name(pathinfo($uploaded['file'], PATHINFO_FILENAME)),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];

    $attach_id = wp_insert_attachment($attachment, $uploaded['file'], $post_id);
    if (is_wp_error($attach_id)) {
        error_log('[CMX] Attachment insert error: ' . $attach_id->get_error_message());
        return;
    }

    // Meta generieren
    $attach_data = wp_generate_attachment_metadata($attach_id, $uploaded['file']);
    wp_update_attachment_metadata($attach_id, $attach_data);

    // Als Beitragsbild setzen
    set_post_thumbnail($post_id, $attach_id);

}, 10, 2);
