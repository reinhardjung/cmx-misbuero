<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Tab: Support
 * Formular & E-Mail-Versand ausgelagert aus cmx_render_settings_page().
 */

function cmx_render_support_tab(): void {

	// POST-Handling nur auf dem Support-Tab
	if (isset($_POST['cmx_support_submit'])) {
		check_admin_referer('cmx_support_nonce');

		$support_mail = 'support@misbuero.ch';

		$to      = sanitize_email($support_mail);
		$subject = isset($_POST['support_subjet'])
			? sanitize_text_field($_POST['support_subjet'])
			: '';
		$body    = isset($_POST['support_description'])
			? wp_kses_post($_POST['support_description'])
			: '';

		$current_user = wp_get_current_user();

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $current_user->user_firstname . ' <' . $current_user->user_email . '>',
			'Reply-To: CLOUD Meister - Support <' . $support_mail . '>',
		];

		$attachments = [];
		if (!empty($_FILES['support_file']['name'])) {
			$upload = wp_handle_upload($_FILES['support_file'], ['test_form' => false]);
			if (!isset($upload['error']) && !empty($upload['file'])) {
				$attachments[] = $upload['file'];
			}
		}

		wp_mail($to, $subject, wpautop($body), $headers, $attachments);

		// Optional CC an den Benutzer, falls Du das wieder aktivieren willst
		if (!empty($_POST['support_cc'])) {
			$headers_cc = [
				'Content-Type: text/html; charset=UTF-8',
				'From: CLOUD Meister - Support <' . $support_mail . '>',
				'Reply-To: ' . $current_user->user_firstname . ' <' . $current_user->user_email . '>',
			];
			wp_mail($current_user->user_email, $subject, wpautop($body), $headers_cc, $attachments);
		}

		add_settings_error(
			'cmx_support_msg',
			'cmx_support_sent',
			__('Deine Anfrage wurde verschickt und wird schnellstmöglichst von mir bearbeitet.', 'default'),
			'updated'
		);
	}

	settings_errors('cmx_support_msg');

	$support_mail = 'support@misbuero.ch';
	$current_user = wp_get_current_user();
	?>
	<h2 class="title"><?php echo esc_html__('Support-Ticket', 'default'); ?></h2>
	<p>
		<?php
		echo wp_kses_post(
			'Hast Du Deine Fragen schon im <a href="https://misbuero.ch/handbuch/" target="_blank">Online-Handbuch</a> nachgeschaut?'
		);
		?>
	</p>

	<form action="" method="post" enctype="multipart/form-data">
		<?php wp_nonce_field('cmx_support_nonce'); ?>

		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th><label for="support_sender">Absender</label></th>
				<td>
					<input name="support_sender" id="support_sender" type="text"
						   value="<?php echo esc_attr($current_user->user_email); ?>"
						   class="regular-text code" readonly required>
					<p class="description">Wird vom System autom. ausgelesen</p>
				</td>
			</tr>
			<tr>
				<th><label for="support_recipient">Empfänger</label></th>
				<td>
					<input name="support_recipient" id="support_recipient" type="text"
						   value="<?php echo esc_attr($support_mail); ?>"
						   class="regular-text code" readonly required>
					<p class="description">Wird vom System autom. gesetzt</p>
				</td>
			</tr>
			<tr>
				<th><label for="support_subjet">Betreff</label></th>
				<td>
					<input name="support_subjet" id="support_subjet" type="text" value=""
						   class="regular-text code" style="width:700px;" required>
					<p class="description">
						z.B. <code>Wie kann ich meine Artikel pflegen?</code>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="support_description">Ausführliche Beschreibung</label></th>
				<td>
					<textarea name="support_description" rows="12" cols="120" id="support_description" required></textarea>
					<p class="description">
						Je genauer Deine Frage, desto schneller kann ich sie Dir beantworten.
					</p>
				</td>
			</tr>
			<!--
			<tr>
				<th><label for="support_cc">Kopie?</label></th>
				<td>
					<label>
						<input name="support_cc" id="support_cc" type="checkbox" checked>
						Ja, bitte Kopie an mich.
					</label>
				</td>
			</tr>
			-->
			<tr>
				<th><label for="support_file">ScreenShot?</label></th>
				<td>
					<input name="support_file" id="support_file" type="file"
						   accept="application/pdf,image/jpeg,image/png">
					<p class="description">Optional: Screenshot oder PDF anhängen.</p>
				</td>
			</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" name="cmx_support_submit" class="button button-primary">
				…und ab die Post!
			</button>
		</p>
	</form>
	<?php
}
