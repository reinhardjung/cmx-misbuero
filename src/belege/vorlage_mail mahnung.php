<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

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
			return '<a href="' . $url . '" style="font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:14px;line-height:1.4;color:' . $link_color . ';text-decoration:underline;font-weight:600;">' . $label_html . '</a>';
		}

		return '<!--[if mso]>'
			. '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td>'
			. '<v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="' . $url . '" style="height:44px;v-text-anchor:middle;width:' . $width . 'px;" arcsize="12%" strokecolor="' . $background . '" fillcolor="' . $background . '">'
			. '<w:anchorlock/><center style="color:' . $color . ';font-family:Arial,sans-serif;font-size:14px;font-weight:700;">' . $label_esc . '</center>'
			. '</v:roundrect>'
			. '</td></tr></table>'
			. '<![endif]--><!--[if !mso]><!-->'
			. '<a href="' . $url . '" style="mso-hide:all;display:inline-block;padding:12px 20px;font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:14px;line-height:1.2;color:' . $color . ';text-decoration:none;font-weight:600;background:' . $background . ';border:1px solid ' . $background . ';border-radius:8px;">' . $inner_html . $label_html . '</a>'
			. '<!--<![endif]-->';
	}
}

function cmxbu_render_belegmail_mahnung_template(array $data = []): string {
	$anrede = \function_exists(__NAMESPACE__ . '\\cmxbu_belegmail_salutation_text')
		? cmxbu_belegmail_salutation_text($data)
		: 'Guten Tag';
	$beleg_label = trim((string) ($data['beleg_label'] ?? 'Beleg'));
	$beleg_id = trim((string) ($data['beleg_id'] ?? ''));
	$beleg_date = trim((string) ($data['beleg_date'] ?? ''));
	$download_url = (string) ($data['download_url'] ?? '');
	$site_name = trim((string) ($data['site_name'] ?? ''));
	$catalog_url = (string) ($data['catalog_url'] ?? '');
	$kontakt_id = (int) ($data['kontakt_id'] ?? 0);
	if ($site_name === '') {
		$site_name = 'MisBüro';
	}

	$title = $beleg_label . ($beleg_id !== '' ? ' ' . $beleg_id : '');
	$faellig_bis = trim((string) ($data['faellig_bis'] ?? ''));
	$betrag = trim((string) ($data['betrag'] ?? ''));
	if ($betrag !== '' && $faellig_bis !== '') {
		$preheader = 'Betrag ' . $betrag . ' zahlbar bis ' . $faellig_bis;
	} elseif ($betrag !== '') {
		$preheader = 'Betrag ' . $betrag;
	} elseif ($faellig_bis !== '') {
		$preheader = 'Zahlbar bis ' . $faellig_bis;
	} else {
		$preheader = 'Beleg ist verfügbar.';
	}

	$download_url = esc_url($download_url);
	$beleg_label_esc = esc_html($beleg_label);
	$beleg_id_esc = esc_html($beleg_id);
	$beleg_date_esc = esc_html($beleg_date);
	$title_esc = esc_html($title);
	$anrede_esc = esc_html($anrede);
	$anrede_text = $anrede;
	$sender_name = \function_exists(__NAMESPACE__ . '\\cmx_email_option_value')
		? \trim((string) cmx_email_option_value('email_name'))
		: '';
	$header_kicker = $sender_name !== '' ? $sender_name : $site_name;
	$header_kicker_esc = esc_html($header_kicker);
	$site_name_esc = esc_html($site_name);
	$catalog_url_esc = esc_url($catalog_url);
	$preheader_esc = esc_html($preheader);
	$agb_footer_html = \function_exists(__NAMESPACE__ . '\\cmx_email_agb_footer_html')
		? (string) cmx_email_agb_footer_html('color:#8b98a5;text-decoration:underline;')
		: '';
	$show_powered_by = \function_exists(__NAMESPACE__ . '\\cmx_powered_by_enabled') && cmx_powered_by_enabled();
	$show_footer_meta = ($agb_footer_html !== '' || $show_powered_by);
	$kundenportal_footer_html = \function_exists(__NAMESPACE__ . '\\cmx_email_kundenportal_footer_html')
		? (string) cmx_email_kundenportal_footer_html($kontakt_id, 'color:#8b98a5;text-decoration:underline;')
		: '';
	$thank_you_margin_bottom = $kundenportal_footer_html !== '' ? '16px' : '0';
	$mail_head_html = cmx_mail_outlook_head_html();
	$mail_theme = \function_exists(__NAMESPACE__ . '\\cmx_email_theme_palette')
		? (array) cmx_email_theme_palette()
		: [];
	$header_background = (string) ($mail_theme['header_background'] ?? '#b53a30');
	$header_gradient_start = (string) ($mail_theme['header_gradient_start'] ?? '#a42c24');
	$header_gradient_end = (string) ($mail_theme['header_gradient_end'] ?? '#d84a3a');
	$header_text = (string) ($mail_theme['header_text'] ?? '#ffffff');
	$header_plain = !empty($mail_theme['header_plain']);
	$header_border = (string) ($mail_theme['header_border'] ?? 'transparent');
	$button_background = (string) ($mail_theme['button_background'] ?? '#a42c24');
	$button_text = (string) ($mail_theme['button_text'] ?? '#ffffff');
	$button_accent = (string) ($mail_theme['button_accent'] ?? '#d84a3a');
	$button_block_style = \function_exists(__NAMESPACE__ . '\\cmx_email_button_block_style')
		? (string) cmx_email_button_block_style()
		: 'margin:18px 0 24px 0;';
	$button_outlook_gap_html = \function_exists(__NAMESPACE__ . '\\cmx_email_button_outlook_gap_html')
		? (string) cmx_email_button_outlook_gap_html()
		: '';
	$header_style = 'padding:20px 24px;background:' . \esc_attr($header_background) . ';color:' . \esc_attr($header_text) . ';';
	if (!$header_plain && $header_gradient_start !== '' && $header_gradient_end !== '') {
		$header_style .= 'background-image:linear-gradient(135deg,' . \esc_attr($header_gradient_start) . ',' . \esc_attr($header_gradient_end) . ');';
	}
	if ($header_plain && $header_border !== '') {
		$header_style .= 'border-bottom:1px solid ' . \esc_attr($header_border) . ';';
	}
	$header_logo_html = \function_exists(__NAMESPACE__ . '\\cmx_email_header_logo_html')
		? (string) cmx_email_header_logo_html('display:block;max-width:158px;width:100%;height:auto;max-height:66px;border:0;outline:none;text-decoration:none;margin:0 0 0 auto;')
		: '';
	$header_content_html = \function_exists(__NAMESPACE__ . '\\cmx_email_header_content_html')
		? (string) cmx_email_header_content_html($header_kicker_esc, $title_esc, $beleg_date_esc, $preheader_esc, $header_logo_html)
		: '<div style="font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:14px;letter-spacing:0.08em;text-transform:uppercase;opacity:0.9;color:' . \esc_attr($header_text) . ';">' . $header_kicker_esc . '</div>'
			. '<div style="font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:26px;line-height:1.2;margin-top:6px;font-weight:600;color:' . \esc_attr($header_text) . ';">' . $title_esc . '</div>'
			. ($beleg_date !== '' ? '<div style="font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:13px;line-height:1.4;margin-top:4px;opacity:0.9;color:' . \esc_attr($header_text) . ';">vom ' . $beleg_date_esc . '</div>' : '')
			. '<div style="font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:12px;opacity:0.85;margin-top:4px;color:' . \esc_attr($header_text) . ';">' . $preheader_esc . '</div>';
	$button_icon_html = '<span style="vertical-align:middle;display:inline-block;margin-right:8px;">'
		. '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" style="vertical-align:middle;display:inline-block;">'
		. '<path d="M6 2h9l5 5v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z" fill="#ffffff"/>'
		. '<path d="M15 2v6h6" fill="#e7e9ef"/>'
		. '<rect x="6.8" y="13.3" width="10.4" height="5.2" rx="1" fill="' . \esc_attr($button_accent) . '"/>'
		. '<text x="12" y="17.2" text-anchor="middle" font-family="Segoe UI,Roboto,Arial,sans-serif" font-size="3.7" font-weight="700" fill="#ffffff"></text>'
		. '</svg></span>';
	$download_button_html = cmx_mail_button_html($download_url, 'PDF Beleg herunterladen', $button_background, $button_text, 240, $button_icon_html, 2);
	$salutation_line_html = $anrede_esc !== ''
		? '<p style="margin:0 0 12px 0;font-size:16px;line-height:1.6;">' . $anrede_esc . ',</p>'
		: '';
	$default_body_html = $salutation_line_html
		. '<p style="margin:0 0 16px 0;font-size:16px;line-height:1.6;">Gem&auml;ss meinen Unterlagen ist die folgende Rechnung noch offen.<br>Kannst Du das bitte kurz pr&uuml;fen?</p>'
		. '<table role="presentation" cellpadding="0" cellspacing="0" style="' . \esc_attr($button_block_style) . '"><tr><td>' . $download_button_html . '</td></tr></table>'
		. $button_outlook_gap_html
		. '<p style="margin:0 0 ' . $thank_you_margin_bottom . ' 0;font-size:16px;line-height:1.6;">Vielen Dank für Dein Vertrauen in meine Dienstleistungen.</p>';
	$body_html = \function_exists(__NAMESPACE__ . '\\cmxbu_render_belegmail_body_html')
		? cmxbu_render_belegmail_body_html([
			'custom_content' => (string) ($data['custom_content'] ?? ''),
			'anrede_text' => $anrede_text,
			'beleg_date' => $beleg_date,
			'faellig_bis' => $faellig_bis,
			'betrag' => $betrag,
			'beleg_id' => $beleg_id,
			'beleg_label' => $beleg_label,
		], $default_body_html, $download_button_html, $thank_you_margin_bottom)
		: $default_body_html;

	return '<!doctype html>
<html lang="de" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>' . $title_esc . '</title>
	' . $mail_head_html . '
</head>
<body style="margin:0;padding:0;background:#f5f6f8;">
	<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="width:100%;background:#f5f6f8;mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;">
		<tr>
			<td align="center" style="padding:24px 12px;">
					<table role="presentation" cellpadding="0" cellspacing="0" width="600" style="width:600px;max-width:600px;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,0.08);mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;">
						<tr>
							<td style="' . $header_style . '">
								' . $header_content_html . '
							</td>
						</tr>
					<tr>
						<td style="padding:24px 24px 8px 24px;font-family:Segoe UI,Roboto,Arial,sans-serif;color:#1f2933;">
							' . $body_html . '
						</td>
					</tr>
					<tr>
						<td style="padding:0 24px 24px 24px;">
							' . ($kundenportal_footer_html !== '' ? '<p style="margin:0 0 10px 0;font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:12px;color:#8b98a5;line-height:1.5;">' . $kundenportal_footer_html . '</p>' : '') . '
							' . ($show_footer_meta ? '<hr style="border:none;border-top:1px solid #e5e7eb;margin:18px 0;">' : '') . '
							' . ($agb_footer_html !== '' ? '<p style="margin:0 0 6px 0;font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:12px;color:#8b98a5;line-height:1.5;">' . $agb_footer_html . '</p>' : '') . '
							' . ($show_powered_by
								? '<p style="margin:0;font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:12px;color:#8b98a5;line-height:1.5;">Erstellt mit <a href="https://misbuero.ch/" style="color:#8b98a5;text-decoration:underline;">MisBüro</a> – der einfachen Bürosoftware für Selbständige in der Schweiz.</p>'
								: '') . '
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>';
}
