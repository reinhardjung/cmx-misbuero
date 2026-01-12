<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

function cmxbu_render_belegmail_template(array $data = []): string {
	$anrede = trim((string) ($data['anrede'] ?? ''));
	if ($anrede === '') {
		$anrede = 'Guten Tag';
	}
	$beleg_label = trim((string) ($data['beleg_label'] ?? 'Beleg'));
	$beleg_id = trim((string) ($data['beleg_id'] ?? ''));
	$download_url = (string) ($data['download_url'] ?? '');
	$site_name = trim((string) ($data['site_name'] ?? ''));
	if ($site_name === '') {
		$site_name = 'MisBüro';
	}

	$title = $beleg_label . ($beleg_id !== '' ? ' ' . $beleg_id : '');
	$preheader = $title . ' ist verfügbar.';

	$download_url = esc_url($download_url);
	$beleg_label_esc = esc_html($beleg_label);
	$beleg_id_esc = esc_html($beleg_id);
	$title_esc = esc_html($title);
	$anrede_esc = esc_html($anrede);
	$site_name_esc = esc_html($site_name);
	$preheader_esc = esc_html($preheader);

	return '<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>' . $title_esc . '</title>
</head>
<body style="margin:0;padding:0;background:#f5f6f8;">
	<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f5f6f8;">
		<tr>
			<td align="center" style="padding:24px 12px;">
				<table role="presentation" cellpadding="0" cellspacing="0" width="600" style="width:600px;max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,0.08);">
					<tr>
						<td style="padding:20px 24px;background:linear-gradient(135deg,#a42c24,#d84a3a);color:#ffffff;">
							<div style="font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:14px;letter-spacing:0.08em;text-transform:uppercase;opacity:0.9;">' . $site_name_esc . '</div>
							<div style="font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:26px;line-height:1.2;margin-top:6px;font-weight:600;">' . $title_esc . '</div>
							<div style="font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:12px;opacity:0.85;margin-top:4px;">' . $preheader_esc . '</div>
						</td>
					</tr>
					<tr>
						<td style="padding:24px 24px 8px 24px;font-family:Segoe UI,Roboto,Arial,sans-serif;color:#1f2933;">
							<p style="margin:0 0 12px 0;font-size:16px;line-height:1.6;">' . $anrede_esc . ',</p>
							<!--- <p style="margin:0 0 16px 0;font-size:16px;line-height:1.6;">Dein Beleg ' . $beleg_label_esc . ' ist bereit zum download: <strong>' . $beleg_id_esc . '</strong></p> -->
							<p style="margin:0 0 16px 0;font-size:16px;line-height:1.6;">Dein Beleg ist bereit zum download: ' . $beleg_label_esc . ' <strong>' . $beleg_id_esc . '</strong></p>
							<p style="margin:0 0 16px 0;font-size:16px;line-height:1.6;">Vielen Dank für Dein Vertrauen in meine Diestleistungen.</p>
							<table role="presentation" cellpadding="0" cellspacing="0" style="margin:18px 0 24px 0;">
								<tr>
									<td bgcolor="#a42c24" style="border-radius:8px;">
										<a href="' . $download_url . '" style="display:inline-block;padding:12px 20px;font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:14px;color:#ffffff;text-decoration:none;font-weight:600;" aria-label="PDF Beleg downloaden" title="PDF Beleg downloaden">
											<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" style="vertical-align:middle;display:inline-block;margin-right:8px;">
												<path d="M6 2h9l5 5v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z" fill="#ffffff"/>
												<path d="M15 2v6h6" fill="#e7e9ef"/>
												<rect x="6.8" y="13.3" width="10.4" height="5.2" rx="1" fill="#d84a3a"/>
												<text x="12" y="17.2" text-anchor="middle" font-family="Segoe UI,Roboto,Arial,sans-serif" font-size="3.7" font-weight="700" fill="#ffffff">PDF</text>
											</svg>PDF Beleg herunterladen
										</a>
									</td>
								</tr>
							</table>
							<p style="margin:0 0 10px 0;font-size:14px;color:#52616b;line-height:1.5;">
								Alternativ kannst Du auch den Link kopieren:
								<a href="' . $download_url . '" style="color:#a42c24;text-decoration:underline;">' . $download_url . '</a>
							</p>
						</td>
					</tr>
					<tr>
						<td style="padding:0 24px 24px 24px;">
							<hr style="border:none;border-top:1px solid #e5e7eb;margin:18px 0;">
							<p style="margin:0;font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:12px;color:#8b98a5;line-height:1.5;">
								Diese E-Mail wurde von ' . $site_name_esc . ' automatisch generiert.
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
