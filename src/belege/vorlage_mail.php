<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_mail_outlook_head_html')) {
	function cmx_mail_outlook_head_html(): string {
		return '<!--[if mso]><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml><![endif]-->'
			. '<!--[if mso]><style type="text/css">body,table,td,p,a,div,span{font-family:Arial,sans-serif !important;}table{border-collapse:collapse !important;mso-table-lspace:0pt !important;mso-table-rspace:0pt !important;}a{text-decoration:none !important;}</style><![endif]-->';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_mail_button_html')) {
	function cmx_mail_button_html(string $url, string $label, string $background = '#a42c24', string $color = '#ffffff', int $width = 240, string $inner_html = ''): string {
		$url = \esc_url($url);
		$label_esc = \esc_html($label);
		$width = \max(140, $width);

		return '<!--[if mso]>'
			. '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td>'
			. '<v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="' . $url . '" style="height:44px;v-text-anchor:middle;width:' . $width . 'px;" arcsize="12%" strokecolor="' . $background . '" fillcolor="' . $background . '">'
			. '<w:anchorlock/><center style="color:' . $color . ';font-family:Arial,sans-serif;font-size:14px;font-weight:700;">' . $label_esc . '</center>'
			. '</v:roundrect>'
			. '</td></tr></table>'
			. '<![endif]--><!--[if !mso]><!-->'
			. '<a href="' . $url . '" style="mso-hide:all;display:inline-block;padding:12px 20px;font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:14px;line-height:1.2;color:' . $color . ';text-decoration:none;font-weight:600;background:' . $background . ';border:1px solid ' . $background . ';border-radius:8px;">' . $inner_html . $label_esc . '</a>'
			. '<!--<![endif]-->';
	}
}

function cmxbu_render_belegmail_template(array $data = []): string {
	$vorname = \trim((string) ($data['vorname'] ?? ''));
	$nachname = \trim((string) ($data['nachname'] ?? ''));
	$anrede = ($vorname !== '' && $nachname !== '')
		? ('Guten Tag ' . \trim($vorname . ' ' . $nachname))
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
	$kundenportal_footer_html = \function_exists(__NAMESPACE__ . '\\cmx_email_kundenportal_footer_html')
		? (string) cmx_email_kundenportal_footer_html($kontakt_id, 'color:#8b98a5;text-decoration:underline;')
		: '';
	$sender_footer_html = \function_exists(__NAMESPACE__ . '\\cmx_email_sender_mailto_html')
		? (string) cmx_email_sender_mailto_html('color:#8b98a5;text-decoration:none;')
		: '';
	$thank_you_margin_bottom = $kundenportal_footer_html !== '' ? '16px' : '0';
	$mail_head_html = cmx_mail_outlook_head_html();
	$button_icon_html = '<span style="vertical-align:middle;display:inline-block;margin-right:8px;">'
		. '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" style="vertical-align:middle;display:inline-block;">'
		. '<path d="M6 2h9l5 5v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z" fill="#ffffff"/>'
		. '<path d="M15 2v6h6" fill="#e7e9ef"/>'
		. '<rect x="6.8" y="13.3" width="10.4" height="5.2" rx="1" fill="#d84a3a"/>'
		. '<text x="12" y="17.2" text-anchor="middle" font-family="Segoe UI,Roboto,Arial,sans-serif" font-size="3.7" font-weight="700" fill="#ffffff"></text>'
		. '</svg></span>';
	$download_button_html = cmx_mail_button_html($download_url, 'PDF Beleg herunterladen', '#a42c24', '#ffffff', 240, $button_icon_html);

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
						<td style="padding:20px 24px;background:#b53a30;background-image:linear-gradient(135deg,#a42c24,#d84a3a);color:#ffffff;">
							<div style="font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:14px;letter-spacing:0.08em;text-transform:uppercase;opacity:0.9;">' . $header_kicker_esc . '</div>
							<div style="font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:26px;line-height:1.2;margin-top:6px;font-weight:600;">' . $title_esc . '</div>
							' . ($beleg_date !== '' ? '<div style="font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:13px;line-height:1.4;margin-top:4px;opacity:0.9;">vom ' . $beleg_date_esc . '</div>' : '') . '
							<div style="font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:12px;opacity:0.85;margin-top:4px;">' . $preheader_esc . '</div>
						</td>
					</tr>
					<tr>
						<td style="padding:24px 24px 8px 24px;font-family:Segoe UI,Roboto,Arial,sans-serif;color:#1f2933;">
							<p style="margin:0 0 12px 0;font-size:16px;line-height:1.6;">' . $anrede_esc . ',</p>
							<!--- <p style="margin:0 0 16px 0;font-size:16px;line-height:1.6;">Dein Beleg ' . $beleg_label_esc . ' ist bereit zum download: <strong>' . $beleg_id_esc . '</strong></p> -->
							<p style="margin:0 0 16px 0;font-size:16px;line-height:1.6;">Dein Beleg wurde erfolgreich erstellt.<br>Du kannst ihn jetzt bequem als PDF herunterladen.</p>
							<table role="presentation" cellpadding="0" cellspacing="0" style="margin:18px 0 24px 0;">
								<tr>
									<td>' . $download_button_html . '</td>
								</tr>
							</table>
							<!--[if mso]><div style="height:16px;line-height:16px;font-size:16px;">&nbsp;</div><![endif]-->
							<p style="margin:0 0 ' . $thank_you_margin_bottom . ' 0;font-size:16px;line-height:1.6;">Vielen Dank für Dein Vertrauen in meine Diestleistungen.</p>
							<!---
							<p style="margin:0 0 10px 0;font-size:14px;color:#52616b;line-height:1.5;">
								<a href="' . $download_url . '" style="color:#a42c24;text-decoration:underline;">' . $download_url . '</a>
							</p>
							 -->
						</td>
					</tr>
					<tr>
						<td style="padding:0 24px 24px 24px;">
							' . ($kundenportal_footer_html !== '' ? '<p style="margin:0 0 10px 0;font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:12px;color:#8b98a5;line-height:1.5;">' . $kundenportal_footer_html . '</p>' : '') . '
							<hr style="border:none;border-top:1px solid #e5e7eb;margin:18px 0;">
							' . ($agb_footer_html !== '' ? '<p style="margin:0 0 6px 0;font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:12px;color:#8b98a5;line-height:1.5;">' . $agb_footer_html . '</p>' : '') . '
							' . ($sender_footer_html !== '' ? '<p style="margin:0 0 6px 0;font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:12px;color:#8b98a5;line-height:1.5;">Absender: ' . $sender_footer_html . '</p>' : '') . '
							<p style="margin:0;font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:12px;color:#8b98a5;line-height:1.5;">
								Diese E-Mail wurde von <a href="' . $catalog_url_esc . '" style="color:#8b98a5;text-decoration:underline;">' . $site_name_esc . '</a> automatisch generiert.
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>';
}
