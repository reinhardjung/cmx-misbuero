<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * --------------------------------------------------------------
 * Metabox: E-Mail senden
 * --------------------------------------------------------------
 */
function cmxbu_render_beleg_send_metabox(\WP_Post $post): void {

    $post_id   = (int) $post->ID;

		$has_pdf = true;
    // $post_info = cmxbu_get_beleg_pdf_for_send($post_id);
    // $has_pdf   = $post_info['found'];

		// $token = cmxbu_find_beleg_token_by_post($post_id);
/// var_dump($post_id); exit;


    // echo '<a href="' . esc_url(admin_url("admin-post.php?action=cmxbu_beleg_send&post_id={$post_id}")) . '"
    //         class="button button-secondary alignleft ' . (!$has_pdf ? 'disabled' : '') . '"
    //         style="pointer-events:none; opacity:0.5;">
    //         versenden
    //       </a>';
	if ($has_pdf) echo '<a href="' . esc_url( admin_url("admin-post.php?action=cmxbu_beleg_send&post_id={$post_id}") ) . '" class="button button-secondary alignleft">versenden</a>';
		else echo '<a href="#" class="button button-secondary alignleft disabled" style="pointer-events:none;opacity:0.5;">versenden</a>';

}


// /**
//  * --------------------------------------------------------------
//  * PDF-Info (FIX – korrekt!)
//  * --------------------------------------------------------------
//  */
// function cmxbu_get_beleg_pdf_for_send(int $post_id): array {
// 	// var_dump(get_post_meta($post_id, '_cmx_beleg_pdf_path', true)); exit;

// 	return [
// 		'found'    => get_post_meta($post_id, '_cmx_beleg_pdf_path', true),
// 		'type'     => 'local',
// 		'path'     => '',
// 		'filename' => '',
// 	];
// }


/**
 * --------------------------------------------------------------
 * Bestehenden Download-Link holen
 * --------------------------------------------------------------
 */
function cmxbu_get_existing_download_link(int $post_id): string {

    global $wpdb;

    $rows = $wpdb->get_results("
        SELECT option_name, option_value
        FROM {$wpdb->options}
        WHERE option_name LIKE 'beleg_%'
    ");

    if (!$rows) {
        return '';
    }

    foreach ($rows as $row) {

        $token = substr($row->option_name, strlen('beleg_'));

        // decode
        $data = @unserialize($row->option_value);
        if (!is_array($data)) {
            $data = json_decode($row->option_value, true);
        }

        if (!is_array($data)) {
            continue;
        }

        // richtiger Beleg?
        if (!empty($data['post_id']) && (int)$data['post_id'] === $post_id) {

            // RETURN → bestehender Token gefunden
            return home_url('/?beleg=' . $token);
        }
    }

    return '';
}



/**
 * --------------------------------------------------------------
 * Admin-Action: E-Mail versenden
 * --------------------------------------------------------------
 */
add_action('admin_post_cmxbu_beleg_send', __NAMESPACE__ . '\\cmxbu_handle_beleg_send');
function cmxbu_handle_beleg_send() {

    if (!isset($_GET['action']) || $_GET['action'] !== 'cmxbu_beleg_send') {
        return;
    }

    $post_id = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;
    if ($post_id <= 0) {
        wp_die('Ungültiger Beleg.');
    }

    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'belege') {
        wp_die('Beleg nicht gefunden.');
    }


    /**
     * 1) Kontakt + E-Mail laden
     */
    $kontakt_id = (int) get_post_meta($post_id)['_cmx_beleg_kontakt_id'][0];
    if (!$kontakt_id) {
        wp_die('Kein Kontakt verknüpft.');
    }

    $emails = (array) get_post_meta($kontakt_id, '_cmx_email_1', true);
    $email  = trim($emails[0] ?? '');

    if (!$email || !is_email($email)) {
        wp_die('Keine gültige E-Mail-Adresse gefunden.');
    }



    /**
     * 2) PDF laden
     */
    // $pdf = cmxbu_get_beleg_pdf_for_send($post_id);
    // if (!$pdf['found']) {
    //     wp_die('PDF nicht gefunden.');
    // }
    // $pdf_path = $pdf['path'];


    /**
     * 3) Bestehenden Download-Link holen
     */
    $download_link = cmxbu_get_existing_download_link($post_id);

    // Fallback, wenn kein Token existiert (sollte nicht passieren!)
    if (!$download_link) {

        // neuen Token erzeugen (nur als Notfall)
        [$title, $type] = cmx_get_beleg_type($post);

        $token = wp_generate_password(20, false, false);

        update_option(
            'beleg_' . $token,
            [
                'post_id' => $post_id,
                'file'    => str_replace(rtrim(CMX_UPLOADS_MISBUERO, '/\\') . '/', '', $pdf_path),
            ],
            false
        );

        $download_link = home_url('/?beleg=' . $token);
    }



    /**
     * 4) E-Mail-Text laden
     */
    [$title, $type] = cmx_get_beleg_type($post);

    $type_key  = $type;
    $ini_email = cmx_ini_get_value('E-Mails', $type_key);
    $text      = is_string($ini_email) ? $ini_email : '';

    if (!$text) {
        $text = "Hallo,\n\nIm Anhang findest Du Dein Dokument.";
    }

    $replace = [
        '{BELEGNUMMER}' => $title,
        '{DOWNLOAD}'    => $download_link,
        '{DATUM}'       => get_post_meta($post_id, '_cmx_datum', true),
    ];
    $message = str_replace(array_keys($replace), array_values($replace), $text);

    $message .= "\n\nDownload-Link:\n" . $download_link;



    /**
     * 5) Betreff
     */
    $subject = "Dein " . ucfirst($type) . " – " . $title;



    /**
     * 6) Senden
     */
    $headers = ['Content-Type: text/plain; charset=UTF-8'];

    wp_mail($email, $subject, $message, $headers);



    /**
     * 7) Zurück zum Beleg
     */
    wp_redirect(
        add_query_arg(
            'cmxbu_mail_sent',
            '1',
            admin_url('post.php?post=' . $post_id . '&action=edit')
        )
    );
    exit;
}
