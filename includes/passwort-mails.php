<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

/**
 * Passwort-Mails im gleichen Karten-Layout wie im Plugin cmx-plesk.
 */
function cmx_passwort_mails_build_html(string $title, string $body_html, ?array $button = null): string {
	$button_html = '';
	if (\is_array($button) && !empty($button['url']) && !empty($button['label'])) {
		$button_html = '<p style="margin:0 0 18px;">'
			. '<a href="' . \esc_url((string) $button['url']) . '" style="background:#b1342b;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:8px;display:inline-block;font-weight:600;">'
			. \esc_html((string) $button['label'])
			. '</a>'
			. '</p>';
	}

	$agb_footer_html = \function_exists(__NAMESPACE__ . '\\cmx_email_agb_footer_html')
		? (string) cmx_email_agb_footer_html('color:#7a7a7a;text-decoration:underline;')
		: '';

	return '<!doctype html><html><body style="margin:0;padding:0;background:#dcdcdc;">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#dcdcdc;padding:24px 0;">'
		. '<tr><td align="center">'
		. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;">'
		. '<tr><td style="background:#c0392b;color:#ffffff;padding:24px 28px;border-radius:14px 14px 0 0;font-family:Arial, sans-serif;">'
		. '<div style="font-size:12px;letter-spacing:1px;text-transform:uppercase;font-weight:700;">MIS BUERO</div>'
		. '<div style="font-size:24px;font-weight:700;margin:6px 0 4px;">' . \esc_html($title) . '</div>'
		. '</td></tr>'
		. '<tr><td style="background:#f2f2f2;padding:26px 28px 28px;border-radius:0 0 14px 14px;font-family:Arial, sans-serif;color:#202124;font-size:15px;line-height:1.5;">'
		. $body_html
		. $button_html
		. '<div style="border-top:1px solid #d8d8d8;margin-top:16px;padding-top:12px;font-size:12px;color:#7a7a7a;">'
		. ($agb_footer_html !== '' ? '<div style="margin:0 0 6px 0;">' . $agb_footer_html . '</div>' : '')
		. '<div>Diese E-Mail wurde von Mis Buero automatisch generiert.</div>'
		. '</div>'
		. '</td></tr>'
		. '</table>'
		. '</td></tr>'
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
	$url = \network_site_url(
		'wp-login.php?login=' . \rawurlencode($user_login) . '&key=' . \rawurlencode($key) . '&action=rp',
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

	$body_html  = '<p style="margin:0 0 14px;">Sali ' . \esc_html($display_name) . ',</p>';
	$body_html .= '<p style="margin:0 0 14px;">fuer Dein Konto auf <strong>' . \esc_html($site_name) . '</strong> wurde ein Passwort-Reset angefordert.</p>';
	$body_html .= '<p style="margin:0 0 14px;">Klicke auf den Button und vergebe ein neues Passwort.</p>';
	$body_html .= '<p style="margin:0 0 14px;">Falls der Button nicht funktioniert, nutze diesen Link:<br><a href="' . \esc_url($reset_url) . '" style="color:#b1342b;text-decoration:none;">' . \esc_html($reset_url) . '</a></p>';

	$request_ip = cmx_passwort_mails_request_ip();
	if ($request_ip !== '') {
		$body_html .= '<p style="margin:0 0 14px;">Anfrage-IP: <strong>' . \esc_html($request_ip) . '</strong></p>';
	}

	$body_html .= '<p style="margin:0;">Wenn Du diese Anfrage nicht gestartet hast, kannst Du diese E-Mail ignorieren.</p>';

	return cmx_passwort_mails_build_html(
		'Passwort zuruecksetzen',
		$body_html,
		[
			'url'   => $reset_url,
			'label' => 'Passwort zuruecksetzen',
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

	$body_html  = '<p style="margin:0 0 14px;">Sali ' . \esc_html($display_name) . ',</p>';
	$body_html .= '<p style="margin:0 0 14px;">Dein Passwort auf <strong>' . \esc_html($site_name) . '</strong> wurde erfolgreich geaendert.</p>';

	if (\is_email($admin_mail)) {
		$admin_mail_attr = \esc_attr($admin_mail);
		$admin_mail_html = \esc_html($admin_mail);
		$body_html .= '<p style="margin:0 0 14px;">Wenn Du das nicht warst, melde Dich bitte sofort bei uns:<br><a href="mailto:' . $admin_mail_attr . '" style="color:#b1342b;text-decoration:none;">' . $admin_mail_html . '</a></p>';
	}

	$body_html .= '<p style="margin:0;">Zurueck zur Webseite: <a href="' . \esc_url($site_url) . '" style="color:#b1342b;text-decoration:none;">' . \esc_html($site_url) . '</a></p>';

	$pass_change_email['message'] = cmx_passwort_mails_build_html('Passwort geaendert', $body_html);
	$pass_change_email['headers'] = cmx_passwort_mails_with_html_header($pass_change_email['headers'] ?? '');

	return $pass_change_email;
}
