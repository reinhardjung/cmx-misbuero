<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

if (!\function_exists(__NAMESPACE__ . '\\cmx_mail_outlook_head_html')) {
	function cmx_mail_outlook_head_html(): string {
		return '<!--[if mso]><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml><![endif]-->'
			. '<!--[if mso]><style type="text/css">body,table,td,p,a,div,span{font-family:Arial,sans-serif !important;}table{border-collapse:collapse !important;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;}a{text-decoration:none !important;}</style><![endif]-->';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_mail_button_html')) {
	function cmx_mail_button_html(string $url, string $label, string $background = '', string $color = '', int $width = 240, string $inner_html = '', int $label_offset_px = 0): string {
		$url = \esc_url($url);
		$label_esc = \esc_html($label);
		$width = \max(140, $width);
		$theme = \function_exists(__NAMESPACE__ . '\\cmx_email_theme_palette')
			? (array) cmx_email_theme_palette()
			: [];
		if ($background === '') {
			$background = (string) ($theme['button_background'] ?? '#a42c24');
		}
		if ($color === '') {
			$color = (string) ($theme['button_text'] ?? '#ffffff');
		}
		$button_mode = (string) ($theme['button_mode'] ?? 'button');
		$link_color = (string) ($theme['link_color'] ?? ($background !== '' ? $background : '#a42c24'));
		$label_html = $label_offset_px !== 0
			? '<span style="position:relative;top:' . $label_offset_px . 'px;">' . $label_esc . '</span>'
			: $label_esc;
		if ($button_mode === 'link') {
			return '<a href="' . $url . '" style="font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:14px;line-height:1.4;color:' . $link_color . ';text-decoration:underline;font-weight:600;">' . $inner_html . $label_html . '</a>';
		}

		return '<!--[if mso]>'
			. '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td>'
			. '<v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="' . $url . '" style="height:44px;v-text-anchor:middle;width:' . $width . 'px;" arcsize="12%" strokecolor="' . $background . '" fillcolor="' . $background . '">'
			. '<w:anchorlock/><center style="color:' . $color . ';font-family:Arial,sans-serif;font-size:14px;font-weight:700;">' . $label_esc . '</center>'
			. '</v:roundrect>'
			. '</td></tr></table>'
			. '<![endif]--><!--[if !mso]><!-->'
			. '<a href="' . $url . '" style="mso-hide:all;background:' . $background . ';color:' . $color . ';text-decoration:none;padding:12px 18px;border-radius:8px;display:inline-block;font-weight:600;">' . $inner_html . $label_html . '</a>'
			. '<!--<![endif]-->';
	}
}

/**
 * Passwort-Mails im gleichen Karten-Layout wie die uebrigen Systemmails.
 */
function cmx_passwort_mails_build_html(string $title, string $body_html, ?array $button = null): string {
	if (\strpos($body_html, '{logo}') !== false) {
		$logo_html = \function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_block_html')
			? (string) cmx_email_self_logo_block_html('margin:0 0 18px 0;')
			: '';
		$body_html = \str_replace('{logo}', $logo_html, $body_html);
	}

	$mail_theme = \function_exists(__NAMESPACE__ . '\\cmx_email_theme_palette')
		? (array) cmx_email_theme_palette()
		: [];
	$header_background = (string) ($mail_theme['header_background'] ?? '#b53a30');
	$header_gradient_start = (string) ($mail_theme['header_gradient_start'] ?? '#a42c24');
	$header_gradient_end = (string) ($mail_theme['header_gradient_end'] ?? '#d84a3a');
	$header_text = (string) ($mail_theme['header_text'] ?? '#ffffff');
	$header_plain = !empty($mail_theme['header_plain']);
	$header_border = (string) ($mail_theme['header_border'] ?? '#d8d8d8');
	$button_background = (string) ($mail_theme['button_background'] ?? '#a42c24');
	$button_text = (string) ($mail_theme['button_text'] ?? '#ffffff');
	$header_style = 'background:' . \esc_attr($header_background) . ';color:' . \esc_attr($header_text) . ';padding:24px 28px;border:1px solid #d8d8d8;border-bottom:none;border-radius:14px 14px 0 0;font-family:Arial, sans-serif;';
	if (!$header_plain && $header_gradient_start !== '' && $header_gradient_end !== '') {
		$header_style .= 'background-image:linear-gradient(135deg,' . \esc_attr($header_gradient_start) . ',' . \esc_attr($header_gradient_end) . ');';
	}
	if ($header_plain && $header_border !== '') {
		$header_style .= 'border-bottom:1px solid ' . \esc_attr($header_border) . ';';
	}
	$button_block_style = \function_exists(__NAMESPACE__ . '\\cmx_email_button_block_style')
		? (string) cmx_email_button_block_style('margin:18px 0 18px;', 'margin:18px 0 18px;')
		: 'margin:18px 0 18px;';
	$button_outlook_gap_html = \function_exists(__NAMESPACE__ . '\\cmx_email_button_outlook_gap_html')
		? (string) cmx_email_button_outlook_gap_html()
		: '';

	$button_html = '';
	if (\is_array($button) && !empty($button['url']) && !empty($button['label'])) {
		$button_html = '<p style="' . \esc_attr($button_block_style) . '">'
			. cmx_mail_button_html((string) $button['url'], (string) $button['label'], $button_background, $button_text, 230)
			. '</p>'
			. $button_outlook_gap_html;
	}

	$agb_footer_html = \function_exists(__NAMESPACE__ . '\\cmx_email_agb_footer_html')
		? (string) cmx_email_agb_footer_html('color:#7a7a7a;text-decoration:underline;')
		: '';
	$show_powered_by = \function_exists(__NAMESPACE__ . '\\cmx_powered_by_enabled') && cmx_powered_by_enabled();
	$show_footer_meta = ($agb_footer_html !== '' || $show_powered_by);
	$mail_head_html = cmx_mail_outlook_head_html();
	$branding_text = \function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_branding_text')
		? (string) cmx_email_self_contact_branding_text()
		: 'MIS BUERO';
	if (\trim($branding_text) === '') {
		$branding_text = 'MIS BUERO';
	}
	$header_logo_html = \function_exists(__NAMESPACE__ . '\\cmx_email_header_logo_html')
		? (string) cmx_email_header_logo_html('display:block;max-width:158px;width:100%;height:auto;max-height:89px;border:0;outline:none;text-decoration:none;margin:0 0 0 auto;', true)
		: '';
	$header_content_html = \function_exists(__NAMESPACE__ . '\\cmx_email_header_content_html')
		? (string) cmx_email_header_content_html(\esc_html($branding_text), \esc_html($title), '', '', $header_logo_html)
		: '<div style="font-size:12px;letter-spacing:1px;text-transform:uppercase;font-weight:700;color:' . \esc_attr($header_text) . ';">' . \esc_html($branding_text) . '</div>'
			. '<div style="font-size:24px;font-weight:700;margin:6px 0 4px;color:' . \esc_attr($header_text) . ';">' . \esc_html($title) . '</div>';

	return '<!doctype html><html lang="de" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">' . $mail_head_html . '</head><body style="margin:0;padding:0;background:#dcdcdc;">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;background:#dcdcdc;padding:24px 0;mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;">'
		. '<tr><td height="18" style="height:18px;line-height:18px;font-size:0;">&nbsp;</td></tr>'
		. '<tr><td align="center">'
		. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;">'
		. '<tr><td style="' . $header_style . '">'
		. $header_content_html
		. '</td></tr>'
		. '<tr><td style="background:#f2f2f2;padding:26px 28px 28px;border:1px solid #d8d8d8;border-top:none;border-radius:0 0 14px 14px;font-family:Arial, sans-serif;color:#202124;font-size:15px;line-height:1.5;">'
		. $body_html
		. $button_html
		. ($show_footer_meta
			? '<div style="border-top:1px solid #d8d8d8;margin-top:16px;padding-top:12px;font-size:12px;color:#7a7a7a;">'
				. ($agb_footer_html !== '' ? '<div style="margin:0 0 6px 0;">' . $agb_footer_html . '</div>' : '')
				. ($show_powered_by
					? '<div>Erstellt mit <a href="https://misbuero.ch/" style="color:#7a7a7a;text-decoration:underline;">MisBüro</a> – der einfachen Bürosoftware für Selbständige in der Schweiz.</div>'
					: '')
				. '</div>'
			: '')
		. '</td></tr>'
		. '</table>'
		. '</td></tr>'
		. '<tr><td height="50" style="height:50px;line-height:50px;font-size:0;background:#dcdcdc;">&nbsp;</td></tr>'
		. '</table>'
		. '</body></html>';
}

/**
 * Erzwingt den HTML-Header fuer wp_mail().
 *
 * @param string|array $headers
 * @return array
 */
function cmx_passwort_mails_with_html_header($headers): array {
	$header_list = [];
	if (\is_array($headers)) {
		$header_list = $headers;
	} elseif (\is_string($headers) && $headers !== '') {
		$header_list = \preg_split('/\r\n|\r|\n/', $headers) ?: [];
	}

	$has_content_type = false;
	foreach ($header_list as $header) {
		if (\stripos((string) $header, 'content-type:') === 0) {
			$has_content_type = true;
			break;
		}
	}

	if (!$has_content_type) {
		$header_list[] = 'Content-Type: text/html; charset=UTF-8';
	}

	return $header_list;
}

function cmx_passwort_mails_reset_url(string $key, string $user_login, \WP_User $user_data): string {
	$login_path = \function_exists(__NAMESPACE__ . '\\cmx_alp_login_path')
		? cmx_alp_login_path()
		: 'alp.php';
	$url = \network_site_url(
		$login_path . '?login=' . \rawurlencode($user_login) . '&key=' . \rawurlencode($key) . '&action=rp',
		'login'
	);

	$locale = (string) \get_user_locale($user_data);
	if ($locale !== '') {
		$url = (string) \add_query_arg('wp_lang', $locale, $url);
	}

	return $url;
}

function cmx_passwort_mails_request_ip(): string {
	$ip = $_SERVER['REMOTE_ADDR'] ?? '';
	$ip = \is_string($ip) ? \trim($ip) : '';

	return $ip;
}

add_filter('retrieve_password_message', __NAMESPACE__ . '\\cmx_passwort_mails_reset_message', 5, 4);
function cmx_passwort_mails_reset_message($message, $key, $user_login, $user_data) {
	if (!($user_data instanceof \WP_User)) {
		return $message;
	}

	$display_name = \trim((string) $user_data->display_name);
	if ($display_name === '') {
		$display_name = (string) $user_login;
	}

	$reset_url = cmx_passwort_mails_reset_url((string) $key, (string) $user_login, $user_data);
	$site_name = \wp_specialchars_decode((string) \get_option('blogname'), ENT_QUOTES);
	$mail_theme = \function_exists(__NAMESPACE__ . '\\cmx_email_theme_palette')
		? (array) cmx_email_theme_palette()
		: [];
	$link_color = (string) ($mail_theme['link_color'] ?? '#a42c24');

	$body_html  = '<p style="margin:0 0 14px;">Sali ' . \esc_html($display_name) . ',</p>';
	$body_html .= '<p style="margin:0 0 14px;">fuer Dein Konto auf <strong>' . \esc_html($site_name) . '</strong> wurde ein Passwort-Reset angefordert.</p>';
	$body_html .= '<p style="margin:0 0 14px;">Klicke auf den Button und vergebe ein neues Passwort.</p>';
	$body_html .= '<p style="margin:0 0 14px;">Falls der Button nicht funktioniert, nutze diesen Link:<br><a href="' . \esc_url($reset_url) . '" style="color:' . \esc_attr($link_color) . ';text-decoration:none;">' . \esc_html($reset_url) . '</a></p>';

	$request_ip = cmx_passwort_mails_request_ip();
	if ($request_ip !== '') {
		$body_html .= '<p style="margin:0 0 14px;">Anfrage-IP: <strong>' . \esc_html($request_ip) . '</strong></p>';
	}

	$body_html .= '<p style="margin:0;">Wenn Du diese Anfrage nicht gestartet hast, kannst Du diese E-Mail ignorieren.</p>';

	return cmx_passwort_mails_build_html(
		'Passwort zurücksetzen',
		$body_html,
		[
			'url'   => $reset_url,
			'label' => 'Passwort zurücksetzen',
		]
	);
}

add_filter('retrieve_password_notification_email', __NAMESPACE__ . '\\cmx_passwort_mails_reset_notification_email', 20, 4);
function cmx_passwort_mails_reset_notification_email($notification_email, $key, $user_login, $user_data) {
	if (!\is_array($notification_email)) {
		return $notification_email;
	}

	$notification_email['headers'] = cmx_passwort_mails_with_html_header($notification_email['headers'] ?? '');

	return $notification_email;
}

add_filter('password_change_email', __NAMESPACE__ . '\\cmx_passwort_mails_change_email', 10, 3);
function cmx_passwort_mails_change_email($pass_change_email, $user, $userdata) {
	if (!\is_array($pass_change_email) || !\is_array($user)) {
		return $pass_change_email;
	}

	$display_name = (string) ($user['display_name'] ?? $user['user_login'] ?? '');
	if ($display_name === '') {
		$display_name = (string) ($userdata['user_login'] ?? '');
	}

	$site_name = \wp_specialchars_decode((string) \get_option('blogname'), ENT_QUOTES);
	$site_url = (string) \home_url();
	$admin_mail = (string) \get_option('admin_email');
	$mail_theme = \function_exists(__NAMESPACE__ . '\\cmx_email_theme_palette')
		? (array) cmx_email_theme_palette()
		: [];
	$link_color = (string) ($mail_theme['link_color'] ?? '#a42c24');

	$body_html  = '<p style="margin:0 0 14px;">Sali ' . \esc_html($display_name) . ',</p>';
	$body_html .= '<p style="margin:0 0 14px;">Dein Passwort auf <strong>' . \esc_html($site_name) . '</strong> wurde erfolgreich geaendert.</p>';

	$configured_sender_html = \function_exists(__NAMESPACE__ . '\\cmx_email_sender_mailto_html')
		? (string) cmx_email_sender_mailto_html('color:' . $link_color . ';text-decoration:none;')
		: '';
	if ($configured_sender_html !== '') {
		$body_html .= '<p style="margin:0 0 14px;">Wenn Du das nicht warst, melde Dich bitte sofort bei uns:<br>' . $configured_sender_html . '</p>';
	} elseif (\is_email($admin_mail)) {
		$admin_mail_attr = \esc_attr($admin_mail);
		$admin_mail_html = \esc_html($admin_mail);
		$body_html .= '<p style="margin:0 0 14px;">Wenn Du das nicht warst, melde Dich bitte sofort bei uns:<br><a href="mailto:' . $admin_mail_attr . '" style="color:' . \esc_attr($link_color) . ';text-decoration:none;">' . $admin_mail_html . '</a></p>';
	}

	$body_html .= '<p style="margin:0;">Zurueck zur Webseite: <a href="' . \esc_url($site_url) . '" style="color:' . \esc_attr($link_color) . ';text-decoration:none;">' . \esc_html($site_url) . '</a></p>';

	$pass_change_email['message'] = cmx_passwort_mails_build_html('Passwort geaendert', $body_html);
	$pass_change_email['headers'] = cmx_passwort_mails_with_html_header($pass_change_email['headers'] ?? '');

	return $pass_change_email;
}
