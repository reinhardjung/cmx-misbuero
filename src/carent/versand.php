<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_MAIL_SENT_AT_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_MAIL_SENT_AT_META', '_cmx_carent_vertrag_mail_sent_at');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_MAIL_SENT_TO_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_MAIL_SENT_TO_META', '_cmx_carent_vertrag_mail_sent_to');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_MAIL_SENT_CC_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_MAIL_SENT_CC_META', '_cmx_carent_vertrag_mail_sent_cc');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_MAIL_SUBJECT_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_MAIL_SUBJECT_META', '_cmx_carent_vertrag_mail_subject');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_versand_email_list')) {
	function cmx_carent_versand_email_list(string $raw): array {
		$parts = \preg_split('/[\s,;]+/', \trim($raw)) ?: [];
		$emails = [];

		foreach ($parts as $part) {
			$email = \sanitize_email((string) $part);
			if ($email === '' || !\is_email($email)) {
				continue;
			}
			$emails[\strtolower($email)] = $email;
		}

		return \array_values($emails);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_versand_contact_email')) {
	function cmx_carent_versand_contact_email(int $post_id, array $data = []): string {
		$email = \sanitize_email((string) (($data['contact']['email'] ?? '') ?: ''));
		if (\is_email($email)) {
			return $email;
		}

		$kontakt_id = \defined(__NAMESPACE__ . '\\CMX_CARENT_KONTAKT_META')
			? (int) \get_post_meta($post_id, (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_KONTAKT_META'), true)
			: 0;
		if ($kontakt_id <= 0) {
			return '';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_email')) {
			$email = \sanitize_email((string) cmx_kommunikation_primary_email($kontakt_id));
			if (\is_email($email)) {
				return $email;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_versand_self_email')) {
	function cmx_carent_versand_self_email(array $data = []): string {
		$email = \sanitize_email((string) (($data['self']['email'] ?? '') ?: ''));
		if (\is_email($email)) {
			return $email;
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_primary_email')) {
			$email = \sanitize_email((string) cmx_email_self_contact_primary_email());
			if (\is_email($email)) {
				return $email;
			}
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_email_option_value')) {
			$email = \sanitize_email((string) cmx_email_option_value('email_address'));
			if (\is_email($email)) {
				return $email;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_versand_subject')) {
	function cmx_carent_versand_subject(int $post_id, array $data = []): string {
		$post_id = (int) $post_id;
		$title = \trim((string) ($data['title'] ?? ''));
		if ($title === '') {
			$title = \trim((string) \get_the_title($post_id));
		}
		if ($title === '') {
			$title = 'Vertrag #' . $post_id;
		}

		$vehicle = \trim((string) ($data['vehicle']['label'] ?? ''));
		$parts = ['Mietvertrag'];
		if ($title !== '') {
			$parts[] = $title;
		}
		if ($vehicle !== '') {
			$parts[] = $vehicle;
		}

		return \implode(' - ', $parts);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_versand_contact_greeting_name')) {
	function cmx_carent_versand_contact_greeting_name(array $data = []): string {
		$contact = (array) ($data['contact'] ?? []);
		$kontakt_id = isset($contact['id']) ? (int) $contact['id'] : 0;
		$vorname_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_VORNAME')
			? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_VORNAME')
			: '_cmx_kontakte_vorname';
		$nachname_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_NACHNAME')
			? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_NACHNAME')
			: '_cmx_kontakte_nachname';
		$vorname = $kontakt_id > 0 ? \trim((string) \get_post_meta($kontakt_id, $vorname_key, true)) : '';
		$nachname = $kontakt_id > 0 ? \trim((string) \get_post_meta($kontakt_id, $nachname_key, true)) : '';
		$full_name = \trim((string) \preg_replace('/\s+/u', ' ', $vorname . ' ' . $nachname));

		if ($full_name !== '') {
			return $full_name;
		}

		return \trim((string) ($contact['title'] ?? ''));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_versand_logo_html')) {
	function cmx_carent_versand_logo_html(int $width = 220, bool $prefer_outlook_embed = true): string {
		$width = \max(1, (int) $width);
		$logo_url = \function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_url')
			? (string) cmx_email_self_logo_url()
			: '';
		if ($logo_url === '') {
			return '';
		}

		$can_embed_inline = false;
		$embedded_cid = '';
		if (
			$prefer_outlook_embed
			&& \function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_can_embed_for_outlook')
			&& cmx_email_self_logo_can_embed_for_outlook()
		) {
			$can_embed_inline = true;
			$embedded_cid = \function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_outlook_cid')
				? (string) cmx_email_self_logo_outlook_cid()
				: 'cmx-self-logo@cmx-misbuero';
		}

		$src = $can_embed_inline ? ('cid:' . $embedded_cid) : $logo_url;
		$src_attr = $can_embed_inline ? \esc_attr($src) : \esc_url($src);
		$link_url = \function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_url')
			? (string) cmx_email_self_contact_url()
			: '';
		$img_style = 'display:block;width:' . $width . 'px;max-width:' . $width . 'px;min-width:' . $width . 'px;height:auto;border:0;outline:none;text-decoration:none;';
		$img_html = '<img src="' . $src_attr . '" alt="Das bin ich Logo" width="' . $width . '" style="' . \esc_attr($img_style) . '" border="0">';

		if ($link_url !== '') {
			$img_html = '<a href="' . \esc_url($link_url) . '" target="_blank" rel="noopener noreferrer" style="display:inline-block;width:' . $width . 'px;max-width:' . $width . 'px;line-height:0;font-size:0;text-decoration:none;border:0;outline:none;">' . $img_html . '</a>';
		}

		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;border-collapse:collapse;"><tr><td align="center" style="padding:0;line-height:0;font-size:0;text-align:center;">' . $img_html . '</td></tr></table>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_versand_message_html')) {
	function cmx_carent_versand_message_html(int $post_id, array $data, array $pdf): string {
		$post_id = (int) $post_id;
		$contact_name = cmx_carent_versand_contact_greeting_name($data);
		$self_name = \trim((string) (($data['self']['branding'] ?? '') ?: ($data['self']['title'] ?? '')));
		$vehicle = \trim((string) ($data['vehicle']['label'] ?? ''));
		$kennzeichen = \trim((string) ($data['vehicle']['kennzeichen'] ?? ''));
		$uebernahme = (array) ($data['transfer']['uebernahme'] ?? []);
		$uebernahme_when = \function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_format_datetime')
			? cmx_carent_vertrag_format_datetime((string) ($uebernahme['datum'] ?? ''), (string) ($uebernahme['uhrzeit'] ?? ''))
			: \trim((string) (($uebernahme['datum'] ?? '') . ' ' . ($uebernahme['uhrzeit'] ?? '')));
		$uebernahme_ort = \trim((string) ($uebernahme['ort'] ?? ''));
		$logo_html = cmx_carent_versand_logo_html(220, true);
		$greeting = $contact_name !== '' ? 'Guten Tag ' . $contact_name : 'Guten Tag';
		$contract_title = \trim((string) ($data['title'] ?? ''));
		if ($contract_title === '') {
			$contract_title = \trim((string) \get_the_title($post_id));
		}
		if ($contract_title === '') {
			$contract_title = 'Vertrag #' . $post_id;
		}

		$message = '';
		$message .= '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#1d2327;">';
		if ($logo_html !== '') {
			$message .= '<div style="margin:0 0 24px;">' . $logo_html . '</div>';
		}
		$message .= '<p style="margin:0 0 14px;">' . \esc_html($greeting) . '</p>';
		$message .= '<p style="margin:0 0 18px;">anbei erhalten Sie den aktuellen Mietvertrag als PDF im Anhang.</p>';
		$message .= '<p style="margin:0 0 6px;">Sonnige Gr&uuml;sse</p>';
		$message .= '<p style="margin:0;font-weight:700;">' . \esc_html($self_name !== '' ? $self_name : \get_bloginfo('name')) . '</p>';
		$message .= '</div>';

		return $message;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_send_vertrag_mail')) {
	function cmx_carent_send_vertrag_mail(int $post_id) {
		$post_id = (int) $post_id;
		$post = \get_post($post_id);
		if (!$post instanceof \WP_Post || (string) $post->post_type !== 'carent') {
			return new \WP_Error('invalid_post', 'Vertrag nicht gefunden.');
		}

		$sender_email = \function_exists(__NAMESPACE__ . '\\cmx_email_option_value')
			? \sanitize_email((string) cmx_email_option_value('email_address'))
			: '';
		if (!\is_email($sender_email)) {
			return new \WP_Error('missing_sender', 'Bitte hinterlege zuerst Deine E-Mail-Adresse in den Einstellungen.');
		}

		$data = \function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_collect_data')
			? (array) cmx_carent_vertrag_collect_data($post_id)
			: [];
		if ($data === []) {
			return new \WP_Error('missing_contract_data', 'Vertragsdaten konnten nicht geladen werden.');
		}

		$agb_accepted = !empty($data['transfer']['uebernahme']['agb_akzeptiert']);
		if (!$agb_accepted) {
			return new \WP_Error('missing_agb_acceptance', 'Bitte zuerst die AGB-Bestaetigung beim Mieter aktivieren.');
		}

		$to = cmx_carent_versand_contact_email($post_id, $data);
		if (!\is_email($to)) {
			return new \WP_Error('missing_contact_email', 'Beim Kontakt ist keine gueltige E-Mail-Adresse hinterlegt.');
		}

		$self_email = cmx_carent_versand_self_email($data);
		if (!\is_email($self_email)) {
			return new \WP_Error('missing_self_email', 'Beim "Das bin ich"-Kontakt ist keine gueltige E-Mail-Adresse hinterlegt.');
		}

		$pdf = \function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_generate_pdf')
			? cmx_carent_vertrag_generate_pdf($post_id, ['data' => $data])
			: new \WP_Error('pdf_unavailable', 'PDF-Erzeugung ist aktuell nicht verfuegbar.');
		if (\is_wp_error($pdf)) {
			return $pdf;
		}

		$pdf_path = \wp_normalize_path((string) ($pdf['abs_path'] ?? ''));
		if ($pdf_path === '' || !\is_file($pdf_path) || !\is_readable($pdf_path)) {
			return new \WP_Error('missing_pdf', 'Das Vertrags-PDF konnte nicht gelesen werden.');
		}

		$subject = cmx_carent_versand_subject($post_id, $data);
		$message = cmx_carent_versand_message_html($post_id, $data, (array) $pdf);
		$from_name = \function_exists(__NAMESPACE__ . '\\cmx_email_option_value')
			? \trim((string) cmx_email_option_value('email_name'))
			: '';
		$reply_to = \function_exists(__NAMESPACE__ . '\\cmx_email_option_value')
			? \sanitize_email((string) cmx_email_option_value('reply'))
			: '';
		$bcc = \function_exists(__NAMESPACE__ . '\\cmx_email_option_value')
			? cmx_carent_versand_email_list((string) cmx_email_option_value('email_bcc'))
			: [];

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . ($from_name !== '' ? $from_name . ' <' . $sender_email . '>' : $sender_email),
		];
		if (\is_email($reply_to)) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		$cc = [];
		if (\strcasecmp($self_email, $to) !== 0) {
			$cc[] = $self_email;
			$headers[] = 'Cc: ' . $self_email;
		}
		if ($bcc !== []) {
			$headers[] = 'Bcc: ' . \implode(', ', $bcc);
		}

		$had_sender_override = \array_key_exists('cmx_force_current_user_mail_sender', $GLOBALS);
		$previous_sender_override = $had_sender_override ? $GLOBALS['cmx_force_current_user_mail_sender'] : null;
		$had_mail_context = \array_key_exists('cmx_mail_context', $GLOBALS);
		$previous_mail_context = $had_mail_context ? $GLOBALS['cmx_mail_context'] : null;
		$wp_mail_failed_message = '';
		$wp_mail_failed_listener = static function ($error) use (&$wp_mail_failed_message): void {
			if (!$error instanceof \WP_Error) {
				return;
			}
			$msg = \trim((string) $error->get_error_message());
			if ($msg === '') {
				$all = \array_map('strval', (array) $error->get_error_messages());
				$msg = \trim(\implode(' | ', \array_filter($all, static fn(string $item): bool => $item !== '')));
			}
			$data = $error->get_error_data();
			if (\is_array($data) && !empty($data['phpmailer_exception_code'])) {
				$msg = $msg !== ''
					? ($msg . ' (Code ' . (string) $data['phpmailer_exception_code'] . ')')
					: ('PHPMailer-Fehler (Code ' . (string) $data['phpmailer_exception_code'] . ')');
			}
			$wp_mail_failed_message = $msg;
		};
		$embedded_logo_listener = static function ($phpmailer): void {
			if (!\function_exists(__NAMESPACE__ . '\\cmx_email_embed_self_logo_for_phpmailer')) {
				return;
			}
			cmx_email_embed_self_logo_for_phpmailer($phpmailer);
		};

		$GLOBALS['cmx_force_current_user_mail_sender'] = true;
		$GLOBALS['cmx_mail_context'] = 'carent_vertrag';
		\add_action('wp_mail_failed', $wp_mail_failed_listener, 10, 1);
		\add_action('phpmailer_init', $embedded_logo_listener, 100, 1);
		try {
			$sent = \wp_mail($to, $subject, $message, $headers, [$pdf_path]);
		} finally {
			\remove_action('wp_mail_failed', $wp_mail_failed_listener, 10);
			\remove_action('phpmailer_init', $embedded_logo_listener, 100);
			if ($had_sender_override) {
				$GLOBALS['cmx_force_current_user_mail_sender'] = $previous_sender_override;
			} else {
				unset($GLOBALS['cmx_force_current_user_mail_sender']);
			}
			if ($had_mail_context) {
				$GLOBALS['cmx_mail_context'] = $previous_mail_context;
			} else {
				unset($GLOBALS['cmx_mail_context']);
			}
		}

		if (!$sent) {
			$error_message = \trim((string) $wp_mail_failed_message);
			if ($error_message === '') {
				$error_message = 'E-Mail konnte nicht gesendet werden.';
			}
			return new \WP_Error('mail_failed', $error_message);
		}

		\update_post_meta($post_id, CMX_CARENT_VERTRAG_MAIL_SENT_AT_META, \current_time('mysql'));
		\update_post_meta($post_id, CMX_CARENT_VERTRAG_MAIL_SENT_TO_META, $to);
		\update_post_meta($post_id, CMX_CARENT_VERTRAG_MAIL_SENT_CC_META, \implode(', ', $cc));
		\update_post_meta($post_id, CMX_CARENT_VERTRAG_MAIL_SUBJECT_META, $subject);

		return [
			'post_id' => $post_id,
			'message' => 'Vertrag wurde per E-Mail versendet.',
			'to' => $to,
			'cc' => $cc,
			'bcc' => $bcc,
			'subject' => $subject,
			'pdf' => (array) $pdf,
			'sent_at' => (string) \current_time('mysql'),
		];
	}
}

\add_action('wp_ajax_cmx_carent_send_vertrag_mail', function (): void {
	if (!\current_user_can('edit_posts')) {
		\wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
	}
	if (!isset($_POST['_ajax_nonce']) || !\wp_verify_nonce((string) \wp_unslash($_POST['_ajax_nonce']), 'cmx_carent_send_vertrag_mail')) {
		\wp_send_json_error(['message' => 'Sicherheitspruefung fehlgeschlagen.'], 403);
	}

	$post_id = isset($_POST['post_id']) ? (int) \wp_unslash($_POST['post_id']) : 0;
	if ($post_id <= 0 || (string) \get_post_type($post_id) !== 'carent' || !\get_post_status($post_id) || !\current_user_can('edit_post', $post_id)) {
		\wp_send_json_error(['message' => 'Ungueltiger Vertrag.'], 400);
	}

	$result = cmx_carent_send_vertrag_mail($post_id);
	if (\is_wp_error($result)) {
		\wp_send_json_error([
			'message' => (string) $result->get_error_message(),
			'code' => (string) $result->get_error_code(),
		], 400);
	}

	\wp_send_json_success($result);
});
