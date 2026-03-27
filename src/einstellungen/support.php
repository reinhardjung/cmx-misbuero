<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

/* ------------------------------------------------------------
 * SUPPORT-TAB REGISTRIEREN
 * ------------------------------------------------------------ */
add_action('admin_init', function() {
	add_settings_section(
		'cmx_sec_support',                        // ID
		'Support',                                // Titel
		__NAMESPACE__ . '\\cmx_render_support_tab', // Callback
		'cmx_tab_support'                         // Page-ID
	);
});


/* ------------------------------------------------------------
 * SUPPORT-TAB INHALT
 * ------------------------------------------------------------ */
function cmx_render_support_tab(): void {

	/* SUPPORT SENDEN */
	if (isset($_POST['cmx_support_submit'])) {

		check_admin_referer('cmx_support_nonce');

		$support_mail = 'support@misbuero.ch';
		$current_user = wp_get_current_user();

		$to      = sanitize_email($support_mail);
		$subject = sanitize_text_field($_POST['support_subjet'] ?? '');
		$body    = wp_kses_post($_POST['support_description'] ?? '');

		$from_email = sanitize_email((string) $current_user->user_email);
		$from_name  = trim((string) $current_user->display_name);
		if ($from_name === '') {
			$from_name = trim((string) $current_user->user_firstname . ' ' . (string) $current_user->user_lastname);
		}
		if ($from_name === '') {
			$from_name = (string) $current_user->user_login;
		}
		$from_name = trim((string) preg_replace('/[\r\n]+/', ' ', $from_name));

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
		];
		if (is_email($from_email)) {
			if ($from_name !== '') {
				$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
				$headers[] = 'Reply-To: ' . $from_name . ' <' . $from_email . '>';
			} else {
				$headers[] = 'From: ' . $from_email;
				$headers[] = 'Reply-To: ' . $from_email;
			}
		}

		$attachments = [];
		if (!empty($_FILES['support_file']['name'])) {
			$upload = wp_handle_upload($_FILES['support_file'], ['test_form' => false]);
			if (empty($upload['error']) && !empty($upload['file'])) {
				$attachments[] = $upload['file'];
			}
		}

		wp_mail($to, $subject, wpautop($body), $headers, $attachments);

		add_settings_error(
			'cmx_support_msg',
			'cmx_support_sent',
			__('Deine Anfrage wurde verschickt und wird schnellstmöglichst von uns bearbeitet.'),
			'updated'
		);
	}

	settings_errors('cmx_support_msg');

	$support_mail = 'support@misbuero.ch';
	$current_user = wp_get_current_user();
	$help_reload_url = admin_url('admin.php?page=cmx-einstellungen&tab=general');
	?>

	<h3 class="title">Support-Ticket erstellen</h3>

	<p>
		Hast Du
		<br>A: Deine Fragen schon
		<a href="https://www.youtube.com/@MisBuero" target="_blank">auf YoutTube </a>
		nachgeschaut?
		<br>B: Beim drücken und halten Deines Mauszeigers (5 Sekunden) auf ein Eingabefeld zeigt Dir eine konkrete Hilfe an. <a href="<?php echo esc_url($help_reload_url); ?>" target="_blank" rel="noopener noreferrer">Hilfetexte neu laden kannst Du hier.</a>
	</p>

	<form action="" method="post" enctype="multipart/form-data">
		<?php wp_nonce_field('cmx_support_nonce'); ?>

		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th><label>Absender</label></th>
				<td>
					<input type="text" class="regular-text"
						value="<?php echo esc_attr($current_user->user_email); ?>"
						readonly>
				</td>
			</tr>

			<tr>
				<th><label>Empfänger</label></th>
				<td>
					<input type="text" class="regular-text"
						value="<?php echo esc_attr($support_mail); ?>"
						readonly>
				</td>
			</tr>

			<tr>
				<th><label for="support_subjet">Betreff</label></th>
				<td>
					<input type="text" name="support_subjet"
						class="regular-text"
						style="width:700px;"
						required>
				</td>
			</tr>

			<tr>
				<th><label for="support_description">Beschreibung</label></th>
				<td>
					<textarea name="support_description"
						rows="12" cols="120"
						required></textarea>
				</td>
			</tr>

			<tr>
				<th><label for="support_file">Screenshot</label></th>
				<td>
					<input type="file" name="support_file"
						accept="application/pdf,image/jpeg,image/png">
				</td>
			</tr>

			</tbody>
		</table>

		<p class="submit">
			<button type="submit"
				name="cmx_support_submit"
				class="button button-primary">
				…und ab die Post!
			</button>
		</p>

	</form>

	<?php
}
