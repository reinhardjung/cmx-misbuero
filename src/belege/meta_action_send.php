<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * Metabox-Teil: "versenden"-Button
 */
function cmxbu_render_beleg_send_metabox(\WP_Post $post): void {

	[, $pdf_abs_path] = cmxbu_get_beleg_pdf_paths($post);
	$has_pdf          = is_file($pdf_abs_path);
	$post_id          = (int) $post->ID;

	if ($has_pdf) {
		$href = \esc_url(\admin_url("admin-post.php?action=cmxbu_beleg_send&post_id={$post_id}"));
		echo '<a href="' . $href . '" class="button button-secondary alignleft">versenden</a>';
	} else {
		echo '<a href="#" class="button button-secondary alignleft disabled" style="pointer-events:none;opacity:0.5;">versenden</a>';
	}
}


/**
 * Handler: Beleg per E-Mail versenden
 * URL: /wp-admin/admin-post.php?action=cmxbu_beleg_send&post_id=123
 */
function cmxbu_handle_beleg_send(): void {

	if (empty($_GET['post_id'])) {
		\wp_die('Beleg-ID fehlt.');
	}

	$post_id = (int) $_GET['post_id'];
	$post    = \get_post($post_id);

	if (!$post || $post->post_type !== 'belege') {
		\wp_die('Beleg nicht gefunden.');
	}

	// PDF-Pfade
	[, $pdf_abs_path] = cmxbu_get_beleg_pdf_paths($post);
	if (!is_file($pdf_abs_path)) {
		\wp_die('PDF nicht gefunden.');
	}

	// Token → Download-Link
	$token        = cmxbu_get_stable_token($post_id);
	$download_url = \home_url('/?beleg=' . $token);


	/**
	 * --------------------------
	 * K O N T A K T   I D
	 * warningsicher
	 * --------------------------
	 */
	$kontakt_id = get_post_meta($post_id, '_cmx_beleg_kontakt_id', true);
	if (empty($kontakt_id)) {

		add_action('admin_notices', function () {
			?>
			<div class="notice notice-error is-dismissible">
				<p><strong>Kontakt / Adresse fehlt.</strong></p>
			</div>
			<?php
		});

		\wp_safe_redirect(\get_edit_post_link($post_id, ''));
		exit;
	}


	/**
	 * E-Mail-Adresse des Kontakts
	 */
	$to = \get_post_meta($kontakt_id, '_cmx_email_1', true);

	if (empty($to) || !\is_email($to)) {
		\wp_die('Keine gültige Empfänger-E-Mailadresse hinterlegt.');
	}

	$subject = 'Dein Beleg';
	$message = "Hallo,\n\nhier ist Dein Beleg:\n{$download_url}\n\nSonnige Grüsse\n";

	$sent = \wp_mail($to, $subject, $message);

	if (!$sent) {
		\wp_die('E-Mail konnte nicht gesendet werden.');
	}

	\wp_safe_redirect(\get_edit_post_link($post_id, ''));
	exit;
}
\add_action('admin_post_cmxbu_beleg_send', __NAMESPACE__ . '\\cmxbu_handle_beleg_send');
