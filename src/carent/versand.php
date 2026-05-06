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
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_REMINDER_HOOK')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_REMINDER_HOOK', 'cmx_carent_send_rueckgabe_reminder');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_REMINDER_SCHEDULED_AT_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_REMINDER_SCHEDULED_AT_META', '_cmx_carent_rueckgabe_reminder_scheduled_at');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_REMINDER_SENT_AT_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_REMINDER_SENT_AT_META', '_cmx_carent_rueckgabe_reminder_sent_at');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_REMINDER_SENT_TO_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_REMINDER_SENT_TO_META', '_cmx_carent_rueckgabe_reminder_sent_to');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_REMINDER_SUBJECT_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_REMINDER_SUBJECT_META', '_cmx_carent_rueckgabe_reminder_subject');
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_versand_return_reminder_days')) {
	function cmx_carent_versand_return_reminder_days(): int {
		$options = (array) \get_option(\defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN') ? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN') : 'cmx_einstellungen', []);
		return isset($options['carent_mail_return_days']) ? \max(0, (int) $options['carent_mail_return_days']) : 14;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_versand_selected_client')) {
	function cmx_carent_versand_selected_client(): array {
		$options = (array) \get_option(\defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN') ? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN') : 'cmx_einstellungen', []);
		$selected_id = \sanitize_key((string) ($options['carent_email_client_id'] ?? ''));
		if ($selected_id === '' || !\function_exists(__NAMESPACE__ . '\\cmx_email_client_list')) {
			return [];
		}

		foreach ((array) cmx_email_client_list() as $client) {
			if (!\is_array($client) || \sanitize_key((string) ($client['id'] ?? '')) !== $selected_id) {
				continue;
			}

			$email = \sanitize_email((string) ($client['email'] ?? ''));
			if (!\is_email($email)) {
				return [];
			}

			return $client;
		}

		return [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_versand_sender_settings')) {
	function cmx_carent_versand_sender_settings(): array {
		$options = (array) \get_option(\defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN') ? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN') : 'cmx_einstellungen', []);
		$client = cmx_carent_versand_selected_client();

		if ($client !== []) {
			$email = \sanitize_email((string) ($client['email'] ?? ''));
			$name = \trim((string) ($client['name'] ?? ''));
			$host = \sanitize_text_field((string) ($client['smtp_host'] ?? ''));
			$password = (string) ($client['password'] ?? '');
			$port = (int) ($client['smtp_port'] ?? 587);
			if ($port <= 0 || $port > 65535) {
				$port = 587;
			}

			return [
				'email' => $email,
				'name' => $name,
				'reply' => $email,
				'bcc' => cmx_carent_versand_email_list((string) ($options['email_bcc'] ?? '')),
				'smtp_host' => $host,
				'smtp_user' => $email,
				'smtp_password' => $password,
				'smtp_port' => $port,
				'smtp_secure' => 'tls',
				'smtp_enabled' => ($host !== '' && \is_email($email) && $password !== ''),
				'selected_client' => true,
			];
		}

		$email = \function_exists(__NAMESPACE__ . '\\cmx_email_option_value')
			? \sanitize_email((string) cmx_email_option_value('email_address'))
			: '';
		$reply = \function_exists(__NAMESPACE__ . '\\cmx_email_option_value')
			? \sanitize_email((string) cmx_email_option_value('reply'))
			: '';
		if (!\is_email($reply)) {
			$reply = $email;
		}

		return [
			'email' => $email,
			'name' => \function_exists(__NAMESPACE__ . '\\cmx_email_option_value') ? \trim((string) cmx_email_option_value('email_name')) : '',
			'reply' => $reply,
			'bcc' => \function_exists(__NAMESPACE__ . '\\cmx_email_option_value') ? cmx_carent_versand_email_list((string) cmx_email_option_value('email_bcc')) : [],
			'smtp_host' => \function_exists(__NAMESPACE__ . '\\cmx_email_option_value') ? \sanitize_text_field((string) cmx_email_option_value('smtp_host')) : '',
			'smtp_user' => $email,
			'smtp_password' => \function_exists(__NAMESPACE__ . '\\cmx_email_option_value') ? (string) cmx_email_option_value('email_password') : '',
			'smtp_port' => 587,
			'smtp_secure' => 'tls',
			'smtp_enabled' => false,
			'selected_client' => false,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_versand_headers')) {
	function cmx_carent_versand_headers(array $sender, string $self_email, string $to, array &$cc): array {
		$sender_email = \sanitize_email((string) ($sender['email'] ?? ''));
		$from_name = \trim((string) \preg_replace('/[\r\n]+/', ' ', (string) ($sender['name'] ?? '')));
		$reply_to = \sanitize_email((string) ($sender['reply'] ?? ''));
		$bcc = \is_array($sender['bcc'] ?? null) ? (array) $sender['bcc'] : [];

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . ($from_name !== '' ? $from_name . ' <' . $sender_email . '>' : $sender_email),
		];
		if (\is_email($reply_to)) {
			$headers[] = 'Reply-To: ' . ($from_name !== '' ? $from_name . ' <' . $reply_to . '>' : $reply_to);
		}
		if (\is_email($self_email) && \strcasecmp($self_email, $to) !== 0) {
			$cc[] = $self_email;
			$headers[] = 'Cc: ' . $self_email;
		}
		if ($bcc !== []) {
			$headers[] = 'Bcc: ' . \implode(', ', $bcc);
		}

		return $headers;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_versand_add_bcc')) {
	function cmx_carent_versand_add_bcc(array $sender, string $email): array {
		$email = \sanitize_email($email);
		if (!\is_email($email)) {
			return $sender;
		}

		$bcc = \is_array($sender['bcc'] ?? null) ? (array) $sender['bcc'] : [];
		$seen = [];
		$clean = [];
		foreach ($bcc as $bcc_email) {
			$bcc_email = \sanitize_email((string) $bcc_email);
			if (!\is_email($bcc_email)) {
				continue;
			}
			$key = \strtolower($bcc_email);
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$clean[] = $bcc_email;
		}

		$key = \strtolower($email);
		if (!isset($seen[$key])) {
			$clean[] = $email;
		}

		$sender['bcc'] = $clean;
		return $sender;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_versand_add_sender_runtime')) {
	function cmx_carent_versand_add_sender_runtime(array $sender): array {
		$from_filter = static function ($email) use ($sender): string {
			$sender_email = \sanitize_email((string) ($sender['email'] ?? ''));
			return \is_email($sender_email) ? $sender_email : (string) $email;
		};
		$name_filter = static function ($name) use ($sender): string {
			$sender_name = \trim((string) \preg_replace('/[\r\n]+/', ' ', (string) ($sender['name'] ?? '')));
			return $sender_name !== '' ? $sender_name : (string) $name;
		};
		$phpmailer_listener = static function ($phpmailer) use ($sender): void {
			if (!$phpmailer instanceof \PHPMailer\PHPMailer\PHPMailer) {
				return;
			}

			$email = \sanitize_email((string) ($sender['email'] ?? ''));
			if (!\is_email($email)) {
				return;
			}
			$name = \trim((string) \preg_replace('/[\r\n]+/', ' ', (string) ($sender['name'] ?? '')));
			$reply = \sanitize_email((string) ($sender['reply'] ?? ''));

			if (!empty($sender['smtp_enabled'])) {
				$phpmailer->isSMTP();
				$phpmailer->Host = (string) ($sender['smtp_host'] ?? '');
				$phpmailer->Port = (int) ($sender['smtp_port'] ?? 587);
				$phpmailer->SMTPAuth = true;
				$phpmailer->Username = (string) ($sender['smtp_user'] ?? $email);
				$phpmailer->Password = (string) ($sender['smtp_password'] ?? '');
				$phpmailer->SMTPAutoTLS = true;
				$phpmailer->SMTPSecure = (string) ($sender['smtp_secure'] ?? 'tls');
			}

			try {
				$phpmailer->setFrom($email, $name, false);
			} catch (\Throwable $e) {
				$phpmailer->From = $email;
				if ($name !== '') {
					$phpmailer->FromName = $name;
				}
			}
			$phpmailer->Sender = $email;
			$phpmailer->clearReplyTos();
			if (\is_email($reply)) {
				$phpmailer->addReplyTo($reply, $name);
			}

			$existing_bcc = [];
			foreach ((array) $phpmailer->getBccAddresses() as $entry) {
				if (\is_array($entry) && isset($entry[0]) && \is_string($entry[0])) {
					$existing_bcc[\strtolower($entry[0])] = true;
				}
			}
			foreach ((array) ($sender['bcc'] ?? []) as $bcc_email) {
				$bcc_email = \sanitize_email((string) $bcc_email);
				if (!\is_email($bcc_email) || isset($existing_bcc[\strtolower($bcc_email)])) {
					continue;
				}
				try {
					$phpmailer->addBCC($bcc_email);
					$existing_bcc[\strtolower($bcc_email)] = true;
				} catch (\Throwable $e) {
					// Ignore invalid BCC entries without aborting the send.
				}
			}
		};

		\add_filter('wp_mail_from', $from_filter, \PHP_INT_MAX);
		\add_filter('wp_mail_from_name', $name_filter, \PHP_INT_MAX);
		\add_action('phpmailer_init', $phpmailer_listener, \PHP_INT_MAX, 1);

		return [$from_filter, $name_filter, $phpmailer_listener];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_versand_remove_sender_runtime')) {
	function cmx_carent_versand_remove_sender_runtime(array $runtime): void {
		if (isset($runtime[0]) && \is_callable($runtime[0])) {
			\remove_filter('wp_mail_from', $runtime[0], \PHP_INT_MAX);
		}
		if (isset($runtime[1]) && \is_callable($runtime[1])) {
			\remove_filter('wp_mail_from_name', $runtime[1], \PHP_INT_MAX);
		}
		if (isset($runtime[2]) && \is_callable($runtime[2])) {
			\remove_action('phpmailer_init', $runtime[2], \PHP_INT_MAX);
		}
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_versand_contact_name_parts')) {
	function cmx_carent_versand_contact_name_parts(array $data = []): array {
		$contact = (array) ($data['contact'] ?? []);
		$kontakt_id = isset($contact['id']) ? (int) $contact['id'] : 0;
		$vorname_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_VORNAME')
			? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_VORNAME')
			: '_cmx_kontakte_vorname';
		$nachname_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_NACHNAME')
			? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_NACHNAME')
			: '_cmx_kontakte_nachname';

		$first_name = \trim((string) ($contact['first_name'] ?? ''));
		$last_name = \trim((string) ($contact['last_name'] ?? ''));
		if ($first_name === '' && $kontakt_id > 0) {
			$first_name = \trim((string) \get_post_meta($kontakt_id, $vorname_key, true));
		}
		if ($last_name === '' && $kontakt_id > 0) {
			$last_name = \trim((string) \get_post_meta($kontakt_id, $nachname_key, true));
		}

		if ($first_name !== '' || $last_name !== '') {
			return [$first_name, $last_name];
		}

		$name = cmx_carent_versand_contact_greeting_name($data);
		$parts = \preg_split('/\s+/u', \trim($name)) ?: [];
		if (\count($parts) <= 1) {
			return [$name, ''];
		}

		return [
			\trim((string) \array_shift($parts)),
			\trim(\implode(' ', $parts)),
		];
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_versand_mail_template')) {
	function cmx_carent_versand_mail_template(string $key): string {
		$options = (array) \get_option(\defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN') ? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN') : 'cmx_einstellungen', []);
		$value = \trim((string) ($options[$key] ?? ''));
		if ($value !== '') {
			return $value;
		}

		if ($key === 'carent_mail_return_template') {
			return '<p>{anrede}</p><p>anbei erhalten Sie die Unterlagen zur Rückgabe des Mietvertrags als PDF im Anhang.</p><p>Sonnige Grüsse<br><strong>{firma}</strong></p>';
		}

		return '<p>{anrede}</p><p>anbei erhalten Sie den aktuellen Mietvertrag als PDF im Anhang.</p><p>Sonnige Grüsse<br><strong>{firma}</strong></p>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_versand_render_mail_template')) {
	function cmx_carent_versand_render_mail_template(string $template, array $values): string {
		$replace = [];
		foreach ($values as $key => $value) {
			$replace['{' . $key . '}'] = (string) $value;
		}

		return \strtr($template, $replace);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_versand_message_html')) {
	function cmx_carent_versand_message_html(int $post_id, array $data, array $pdf = [], string $template_key = 'carent_mail_contract_template'): string {
		$post_id = (int) $post_id;
		$contact_name = cmx_carent_versand_contact_greeting_name($data);
		$self_name = \trim((string) (($data['self']['branding'] ?? '') ?: ($data['self']['title'] ?? '')));
		$contact = (array) ($data['contact'] ?? []);
		$vehicle = \trim((string) ($data['vehicle']['label'] ?? ''));
		$kennzeichen = \trim((string) ($data['vehicle']['kennzeichen'] ?? ''));
		$uebernahme = (array) ($data['transfer']['uebernahme'] ?? []);
		$rueckgabe = (array) ($data['transfer']['rueckgabe'] ?? []);
		$uebernahme_when = \function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_format_datetime')
			? cmx_carent_vertrag_format_datetime((string) ($uebernahme['datum'] ?? ''), (string) ($uebernahme['uhrzeit'] ?? ''))
			: \trim((string) (($uebernahme['datum'] ?? '') . ' ' . ($uebernahme['uhrzeit'] ?? '')));
		$rueckgabe_when = \function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_format_datetime')
			? cmx_carent_vertrag_format_datetime((string) ($rueckgabe['datum'] ?? ''), (string) ($rueckgabe['uhrzeit'] ?? ''))
			: \trim((string) (($rueckgabe['datum'] ?? '') . ' ' . ($rueckgabe['uhrzeit'] ?? '')));
		$uebernahme_ort = \trim((string) ($uebernahme['ort'] ?? ''));
		$logo_html = cmx_carent_versand_logo_html(220, true);
		$greeting = $contact_name !== '' ? 'Guten Tag ' . $contact_name : 'Guten Tag';
		[$first_name, $last_name] = cmx_carent_versand_contact_name_parts($data);
		$contract_title = \trim((string) ($data['title'] ?? ''));
		if ($contract_title === '') {
			$contract_title = \trim((string) \get_the_title($post_id));
		}
		if ($contract_title === '') {
			$contract_title = 'Vertrag #' . $post_id;
		}
		$amount = \trim((string) ($data['vehicle']['summe'] ?? ''));
		if ($amount !== '') {
			$amount .= ' CHF';
		}
		$template = cmx_carent_versand_mail_template($template_key);
		$body = cmx_carent_versand_render_mail_template($template, [
			'anrede' => $greeting,
			'tageszeit' => 'Guten Tag',
			'vorname' => $first_name,
			'nachname' => $last_name,
			'firma' => $self_name !== '' ? $self_name : \get_bloginfo('name'),
			'vertrag' => $contract_title,
			'vertrags_id' => $contract_title,
			'fahrzeug' => $vehicle,
			'kennzeichen' => $kennzeichen,
			'uebernahme' => \trim($uebernahme_when . ($uebernahme_ort !== '' ? ' · ' . $uebernahme_ort : '')),
			'rueckgabe' => $rueckgabe_when,
			'betrag' => $amount,
			'logo' => $logo_html,
		]);

		$message = '';
		$message .= '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#1d2327;">';
		if ($logo_html !== '' && !\str_contains($body, $logo_html)) {
			$message .= '<div style="margin:0 0 24px;">' . $logo_html . '</div>';
		}
		$message .= \wp_kses_post($body);
		$message .= '</div>';

		return $message;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_versand_append_contact_note')) {
	function cmx_carent_versand_append_contact_note(int $post_id, array $data = []): bool {
		$post_id = (int) $post_id;
		if ($post_id <= 0) {
			return false;
		}

		$contact = (array) ($data['contact'] ?? []);
		$kontakt_id = isset($contact['id']) ? (int) $contact['id'] : 0;
		if ($kontakt_id <= 0 && \defined(__NAMESPACE__ . '\\CMX_CARENT_KONTAKT_META')) {
			$kontakt_id = (int) \get_post_meta($post_id, (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_KONTAKT_META'), true);
		}
		if ($kontakt_id <= 0 || (string) \get_post_type($kontakt_id) !== 'kontakte' || !\get_post_status($kontakt_id)) {
			return false;
		}

		if (
			(!\function_exists(__NAMESPACE__ . '\\cmx_notizen_load_rows')
				|| !\function_exists(__NAMESPACE__ . '\\cmx_notizen_meta_key_for_post_type')
				|| !\function_exists(__NAMESPACE__ . '\\cmx_notizen_normalize_row'))
			&& \is_file(\dirname(__DIR__, 2) . '/includes/notizen.php')
		) {
			require_once \dirname(__DIR__, 2) . '/includes/notizen.php';
		}

		if (
			!\function_exists(__NAMESPACE__ . '\\cmx_notizen_load_rows')
			|| !\function_exists(__NAMESPACE__ . '\\cmx_notizen_meta_key_for_post_type')
			|| !\function_exists(__NAMESPACE__ . '\\cmx_notizen_normalize_row')
			|| !\function_exists(__NAMESPACE__ . '\\cmx_notizen_now_date')
			|| !\function_exists(__NAMESPACE__ . '\\cmx_notizen_now_time')
		) {
			return false;
		}

		$contract_url = \function_exists(__NAMESPACE__ . '\\cmx_vermietung_manage_url')
			? (string) cmx_vermietung_manage_url($post_id)
			: '';
		if ($contract_url === '') {
			$contract_url = (string) \get_edit_post_link($post_id, 'raw');
		}

		$text = 'Autovermietung';
		if ($contract_url !== '') {
			$text = '<a href="' . \esc_url($contract_url) . '" target="_blank" rel="noopener noreferrer">Autovermietung</a>';
		}

		$row = cmx_notizen_normalize_row([
			'betreff' => 'Allgemein',
			'datum'   => cmx_notizen_now_date(),
			'zeit'    => cmx_notizen_now_time(),
			'text'    => $text,
			'quelle'  => '',
		]);
		if (!\is_array($row) || $row === []) {
			return false;
		}

		$rows = (array) cmx_notizen_load_rows($kontakt_id, 'kontakte');
		\array_unshift($rows, $row);
		\update_post_meta($kontakt_id, (string) cmx_notizen_meta_key_for_post_type('kontakte'), $rows);

		if (\function_exists(__NAMESPACE__ . '\\cmx_notizen_legacy_meta_keys')) {
			foreach ((array) cmx_notizen_legacy_meta_keys('kontakte') as $legacy_key) {
				\delete_post_meta($kontakt_id, (string) $legacy_key);
			}
		}

		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_schedule_rueckgabe_reminder')) {
	function cmx_carent_schedule_rueckgabe_reminder(int $post_id): bool {
		$post_id = (int) $post_id;
		if ($post_id <= 0 || (string) \get_post_type($post_id) !== 'carent') {
			return false;
		}

		\wp_clear_scheduled_hook(CMX_CARENT_RUECKGABE_REMINDER_HOOK, [$post_id]);

		$days = cmx_carent_versand_return_reminder_days();
		if ($days <= 0) {
			\delete_post_meta($post_id, CMX_CARENT_RUECKGABE_REMINDER_SCHEDULED_AT_META);
			return false;
		}

		$day_seconds = \defined('DAY_IN_SECONDS') ? (int) \DAY_IN_SECONDS : 86400;
		$timestamp = \time() + ($days * $day_seconds);
		$scheduled = \wp_schedule_single_event($timestamp, CMX_CARENT_RUECKGABE_REMINDER_HOOK, [$post_id]);
		if ($scheduled === false) {
			return false;
		}

		\update_post_meta($post_id, CMX_CARENT_RUECKGABE_REMINDER_SCHEDULED_AT_META, \wp_date('Y-m-d H:i:s', $timestamp));
		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_send_rueckgabe_reminder_mail')) {
	function cmx_carent_send_rueckgabe_reminder_mail(int $post_id) {
		$post_id = (int) $post_id;
		$post = \get_post($post_id);
		if (!$post instanceof \WP_Post || (string) $post->post_type !== 'carent') {
			return new \WP_Error('invalid_post', 'Vertrag nicht gefunden.');
		}
		if (\trim((string) \get_post_meta($post_id, CMX_CARENT_RUECKGABE_REMINDER_SENT_AT_META, true)) !== '') {
			return true;
		}

		$sender = cmx_carent_versand_sender_settings();
		$sender_email = \sanitize_email((string) ($sender['email'] ?? ''));
		if (!\is_email($sender_email)) {
			return new \WP_Error('missing_sender', 'Bitte hinterlege zuerst Deine E-Mail-Adresse in den Einstellungen.');
		}
		if (!empty($sender['selected_client']) && empty($sender['smtp_enabled'])) {
			return new \WP_Error('missing_sender_smtp', 'Beim ausgewählten Carent E-Mail Client fehlen SMTP Host oder Kennwort.');
		}

		$data = \function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_collect_data')
			? (array) cmx_carent_vertrag_collect_data($post_id)
			: [];
		if ($data === []) {
			return new \WP_Error('missing_contract_data', 'Vertragsdaten konnten nicht geladen werden.');
		}

		$to = cmx_carent_versand_contact_email($post_id, $data);
		if (!\is_email($to)) {
			return new \WP_Error('missing_contact_email', 'Beim Kontakt ist keine gueltige E-Mail-Adresse hinterlegt.');
		}

		$self_email = cmx_carent_versand_self_email($data);
		if (!\is_email($self_email)) {
			return new \WP_Error('missing_self_email', 'Beim "Das bin ich"-Kontakt ist keine gueltige E-Mail-Adresse hinterlegt.');
		}

		$subject = 'Rückgabe Reminder - ' . cmx_carent_versand_subject($post_id, $data);
		$message = cmx_carent_versand_message_html($post_id, $data, [], 'carent_mail_return_template');
		$cc = [];
		$bcc = \is_array($sender['bcc'] ?? null) ? \array_values((array) $sender['bcc']) : [];
		$headers = cmx_carent_versand_headers($sender, $self_email, $to, $cc);

		$had_sender_override = \array_key_exists('cmx_force_current_user_mail_sender', $GLOBALS);
		$previous_sender_override = $had_sender_override ? $GLOBALS['cmx_force_current_user_mail_sender'] : null;
		$had_mail_context = \array_key_exists('cmx_mail_context', $GLOBALS);
		$previous_mail_context = $had_mail_context ? $GLOBALS['cmx_mail_context'] : null;
		$embedded_logo_listener = static function ($phpmailer): void {
			if (!\function_exists(__NAMESPACE__ . '\\cmx_email_embed_self_logo_for_phpmailer')) {
				return;
			}
			cmx_email_embed_self_logo_for_phpmailer($phpmailer);
		};
		$sender_runtime = cmx_carent_versand_add_sender_runtime($sender);

		$GLOBALS['cmx_force_current_user_mail_sender'] = true;
		$GLOBALS['cmx_mail_context'] = 'carent_rueckgabe_reminder';
		\add_action('phpmailer_init', $embedded_logo_listener, 100, 1);
		try {
			$sent = \wp_mail($to, $subject, $message, $headers);
		} finally {
			\remove_action('phpmailer_init', $embedded_logo_listener, 100);
			cmx_carent_versand_remove_sender_runtime($sender_runtime);
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
			return new \WP_Error('mail_failed', 'Rückgabe-Reminder konnte nicht gesendet werden.');
		}

		\update_post_meta($post_id, CMX_CARENT_RUECKGABE_REMINDER_SENT_AT_META, \current_time('mysql'));
		\update_post_meta($post_id, CMX_CARENT_RUECKGABE_REMINDER_SENT_TO_META, $to);
		\update_post_meta($post_id, CMX_CARENT_RUECKGABE_REMINDER_SUBJECT_META, $subject);
		\delete_post_meta($post_id, CMX_CARENT_RUECKGABE_REMINDER_SCHEDULED_AT_META);

		return true;
	}
}

\add_action(CMX_CARENT_RUECKGABE_REMINDER_HOOK, __NAMESPACE__ . '\\cmx_carent_send_rueckgabe_reminder_mail', 10, 1);

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_send_vertrag_mail')) {
	function cmx_carent_send_vertrag_mail(int $post_id) {
		$post_id = (int) $post_id;
		$post = \get_post($post_id);
		if (!$post instanceof \WP_Post || (string) $post->post_type !== 'carent') {
			return new \WP_Error('invalid_post', 'Vertrag nicht gefunden.');
		}

		$sender = cmx_carent_versand_sender_settings();
		$sender_email = \sanitize_email((string) ($sender['email'] ?? ''));
		if (!\is_email($sender_email)) {
			return new \WP_Error('missing_sender', 'Bitte hinterlege zuerst Deine E-Mail-Adresse in den Einstellungen.');
		}
		if (!empty($sender['selected_client']) && empty($sender['smtp_enabled'])) {
			return new \WP_Error('missing_sender_smtp', 'Beim ausgewählten Carent E-Mail Client fehlen SMTP Host oder Kennwort.');
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
		$cc = [];
		$sender = cmx_carent_versand_add_bcc($sender, $sender_email);
		$sender = cmx_carent_versand_add_bcc($sender, $self_email);
		$bcc = \is_array($sender['bcc'] ?? null) ? \array_values((array) $sender['bcc']) : [];
		$headers = cmx_carent_versand_headers($sender, '', $to, $cc);

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
		$sender_runtime = cmx_carent_versand_add_sender_runtime($sender);

		$GLOBALS['cmx_force_current_user_mail_sender'] = true;
		$GLOBALS['cmx_mail_context'] = 'carent_vertrag';
		\add_action('wp_mail_failed', $wp_mail_failed_listener, 10, 1);
		\add_action('phpmailer_init', $embedded_logo_listener, 100, 1);
		try {
			$sent = \wp_mail($to, $subject, $message, $headers, [$pdf_path]);
		} finally {
			\remove_action('wp_mail_failed', $wp_mail_failed_listener, 10);
			\remove_action('phpmailer_init', $embedded_logo_listener, 100);
			cmx_carent_versand_remove_sender_runtime($sender_runtime);
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
		\delete_post_meta($post_id, CMX_CARENT_RUECKGABE_REMINDER_SENT_AT_META);
		\delete_post_meta($post_id, CMX_CARENT_RUECKGABE_REMINDER_SENT_TO_META);
		\delete_post_meta($post_id, CMX_CARENT_RUECKGABE_REMINDER_SUBJECT_META);
		$reminder_scheduled = cmx_carent_schedule_rueckgabe_reminder($post_id);
		$note_created = cmx_carent_versand_append_contact_note($post_id, $data);

		return [
			'post_id' => $post_id,
			'message' => 'Vertrag wurde per E-Mail versendet an ' . $to,
			'to' => $to,
			'cc' => $cc,
			'bcc' => $bcc,
			'subject' => $subject,
			'pdf' => (array) $pdf,
			'sent_at' => (string) \current_time('mysql'),
			'note_created' => $note_created,
			'reminder_scheduled' => $reminder_scheduled,
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
