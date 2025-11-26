<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');


/**
 * --------------------------------------------------------------
 * Metabox: E-Mail senden
 * --------------------------------------------------------------
 */
function cmxbu_render_beleg_send_metabox(\WP_Post $post): void {

	$post_id   = (int) $post->ID;
	$post_info = cmxbu_get_beleg_pdf_for_send($post_id);
	$has_pdf   = $post_info['found'];

	$href         = $has_pdf ? esc_url(admin_url("admin-post.php?action=cmxbu_beleg_send&post_id={$post_id}")) : '#';
	$disable_attr = $has_pdf ? '' : 'pointer-events:none; opacity:0.5;';

	echo '<a href="' . $href . '"
			class="button button-secondary alignleft ' . (!$has_pdf ? 'disabled' : '') . '"
			style="' . $disable_attr . '">
			versenden
		  </a>';
}


/**
 * --------------------------------------------------------------
 * PDF-Info für Versand holen
 * (bereits von Dir vorgegeben)
 * --------------------------------------------------------------
 */
function cmxbu_get_beleg_pdf_for_send(int $post_id): array {
	return [
		'found'    => !is_file(get_post_meta($post_id, '_cmx_beleg_pdf_path', true)),
		'type'     => 'local',
		'path'     => '',
		'filename' => '',
	];
}


/**
 * --------------------------------------------------------------
 * Admin-Action: E-Mail versenden
 * --------------------------------------------------------------
 */
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
	 * 1) Kontakt + erste E-Mail-Adresse laden
	 */
	$kontakt_id = (int) get_post_meta($post_id)['_cmx_beleg_kontakt_id'][0];
	if (!$kontakt_id) {
		wp_die('Kein Kontakt verknüpft.');
	}
// cmx_kommunikation[email_1]

	$emails = (array) get_post_meta($kontakt_id, '_cmx_email_1', true);

	$email  = trim($emails[0] ?? '');

	if (!$email || !is_email($email)) {
		wp_die('Keine gültige E-Mail-Adresse gefunden.');
	}

	/**
	 * 2) PDF laden
	 */
	$pdf = cmxbu_get_beleg_pdf_for_send($post_id);
	if (!$pdf['found']) {
		wp_die('PDF nicht gefunden.');
	}
	$pdf_path = $pdf['path'];


	/**
	 * 3) Download-Token erstellen (wie Download-Metabox)
	 */
	[$title, $type] = cmx_get_beleg_type($post);

	$token = wp_generate_password(20, false, false);

	// relative Datei für Token-Handler eintragen
	$rel = 'beleg_pdf_' . $token;
	update_option(
		'beleg_' . $token,
		[
			'post_id' => $post_id,
			'file'    => str_replace(rtrim(CMX_UPLOADS_MISBUERO, '/\\') . '/', '', $pdf_path),
		],
		false
	);

	$download_link = home_url('/?beleg=' . $token);


	/**
	 * 4) E-Mail-Text aus Einstellungen lesen
	 */
	$type_key  = $type; // angebot / gutschrift / lieferschein / rechnung
	$ini_email = cmx_ini_get_value('E-Mails', $type_key);
	$text      = is_string($ini_email) ? $ini_email : '';

	if (!$text) {
		$text = "Hallo,\n\nIm Anhang findest Du Dein Dokument.";
	}

	// Erlaubte Platzhalter ersetzen
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
	 * 6) E-Mail senden
	 */
	$headers = ['Content-Type: text/plain; charset=UTF-8'];

	wp_mail($email, $subject, $message, $headers);


	/**
	 * 7) Weiterleitung zurück zum Beleg
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
add_action('admin_post_cmxbu_beleg_send', __NAMESPACE__ . '\\cmxbu_handle_beleg_send');
