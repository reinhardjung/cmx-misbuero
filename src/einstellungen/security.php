<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

use CLOUDMEISTER\CMX\Buero\PDF\SignatureService;

if (!\function_exists(__NAMESPACE__ . '\\cmx_pdf_signature_admin_url')) {
	function cmx_pdf_signature_admin_url(array $args = []): string {
		$base = \admin_url('admin.php?page=' . \rawurlencode(CMX_SETTINGS_SLUG) . '&tab=system&sub=security');
		return \add_query_arg($args, $base);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_pdf_signature_require_access')) {
	function cmx_pdf_signature_require_access(): void {
		if (!cmx_settings_current_user_can_access()) {
			\wp_die(\esc_html__('Keine Berechtigung.', 'cmx-misbuero'));
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_pdf_signature_format_datetime')) {
	function cmx_pdf_signature_format_datetime(string $value): string {
		if ($value === '') {
			return '-';
		}
		$timestamp = \strtotime($value);
		return $timestamp ? \wp_date('d.m.Y H:i', $timestamp) : $value;
	}
}

\add_action('admin_init', static function (): void {
	\add_settings_section(
		'cmx_sec_pdf_signature',
		\__('PDF-Signatur', 'cmx-misbuero'),
		__NAMESPACE__ . '\\cmx_render_pdf_signature_settings',
		'cmx_tab_system__security'
	);
});

function cmx_render_pdf_signature_settings(): void {
	$manager = SignatureService::instance()->certificateManager();
	$info = $manager->info();
	$status = !empty($info['exists'])
		? \__('Aktiv: PDFs werden unsichtbar digital signiert.', 'cmx-misbuero')
		: \__('Fehlt: PDFs können noch nicht signiert werden.', 'cmx-misbuero');
	$generate_url = \wp_nonce_url(
		\admin_url('admin-post.php?action=cmx_pdf_signature_generate'),
		'cmx_pdf_signature_generate'
	);
	$renew_url = \wp_nonce_url(
		\admin_url('admin-post.php?action=cmx_pdf_signature_renew'),
		'cmx_pdf_signature_renew'
	);
	$download_url = \wp_nonce_url(
		\admin_url('admin-post.php?action=cmx_pdf_signature_download_public'),
		'cmx_pdf_signature_download_public'
	);

	$message = isset($_GET['cmx_pdf_signature']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_pdf_signature'])) : '';
	if ($message === 'generated') {
		echo '<div class="notice notice-success inline"><p>' . \esc_html__('Zertifikat wurde erzeugt.', 'cmx-misbuero') . '</p></div>';
	} elseif ($message === 'renewed') {
		echo '<div class="notice notice-success inline"><p>' . \esc_html__('Zertifikat wurde erneuert.', 'cmx-misbuero') . '</p></div>';
	} elseif ($message === 'error') {
		echo '<div class="notice notice-error inline"><p>' . \esc_html__('Zertifikatsaktion fehlgeschlagen. Details stehen im PHP-Error-Log.', 'cmx-misbuero') . '</p></div>';
	}

	echo '<p>' . \esc_html__('Mis Büro signiert erzeugte PDFs automatisch und unsichtbar. Das Layout bleibt unverändert; der private Schlüssel wird nie ausgegeben.', 'cmx-misbuero') . '</p>';
	echo '<table class="widefat striped" style="max-width:920px"><tbody>';
	echo '<tr><th scope="row">' . \esc_html__('Status', 'cmx-misbuero') . '</th><td>' . \esc_html($status) . '</td></tr>';
	echo '<tr><th scope="row">' . \esc_html__('Zertifikat vorhanden', 'cmx-misbuero') . '</th><td>' . (!empty($info['exists']) ? \esc_html__('Ja', 'cmx-misbuero') : \esc_html__('Nein', 'cmx-misbuero')) . '</td></tr>';
	echo '<tr><th scope="row">' . \esc_html__('Erstellt am', 'cmx-misbuero') . '</th><td>' . \esc_html(cmx_pdf_signature_format_datetime((string) ($info['created_at'] ?? ''))) . '</td></tr>';
	echo '<tr><th scope="row">' . \esc_html__('Gültig ab', 'cmx-misbuero') . '</th><td>' . \esc_html(cmx_pdf_signature_format_datetime((string) ($info['valid_from'] ?? ''))) . '</td></tr>';
	echo '<tr><th scope="row">' . \esc_html__('Ablaufdatum', 'cmx-misbuero') . '</th><td>' . \esc_html(cmx_pdf_signature_format_datetime((string) ($info['expires_at'] ?? ''))) . '</td></tr>';
	echo '<tr><th scope="row">' . \esc_html__('Fingerprint SHA-256', 'cmx-misbuero') . '</th><td><code>' . \esc_html((string) ($info['fingerprint'] ?? '-')) . '</code></td></tr>';
	echo '<tr><th scope="row">' . \esc_html__('Speicherort', 'cmx-misbuero') . '</th><td><code>' . \esc_html((string) ($info['directory'] ?? '')) . '</code></td></tr>';
	echo '</tbody></table>';
	echo '<p class="submit">';
	echo '<a class="button button-secondary" href="' . \esc_url($generate_url) . '">' . \esc_html__('Zertifikat erzeugen', 'cmx-misbuero') . '</a> ';
	echo '<a class="button button-secondary" href="' . \esc_url($renew_url) . '">' . \esc_html__('Zertifikat erneuern', 'cmx-misbuero') . '</a> ';
	if (!empty($info['exists'])) {
		echo '<a class="button" href="' . \esc_url($download_url) . '">' . \esc_html__('Public Certificate herunterladen', 'cmx-misbuero') . '</a>';
	}
	echo '</p>';
}

\add_action('admin_post_cmx_pdf_signature_generate', static function (): void {
	cmx_pdf_signature_require_access();
	\check_admin_referer('cmx_pdf_signature_generate');
	$result = SignatureService::instance()->certificateManager()->generate(false);
	if (\is_wp_error($result)) {
		SignatureService::log('Zertifikat erzeugen fehlgeschlagen.', ['error' => $result->get_error_message()]);
		\wp_safe_redirect(cmx_pdf_signature_admin_url(['cmx_pdf_signature' => 'error']));
		exit;
	}
	\wp_safe_redirect(cmx_pdf_signature_admin_url(['cmx_pdf_signature' => 'generated']));
	exit;
});

\add_action('admin_post_cmx_pdf_signature_renew', static function (): void {
	cmx_pdf_signature_require_access();
	\check_admin_referer('cmx_pdf_signature_renew');
	$result = SignatureService::instance()->certificateManager()->renew();
	if (\is_wp_error($result)) {
		SignatureService::log('Zertifikat erneuern fehlgeschlagen.', ['error' => $result->get_error_message()]);
		\wp_safe_redirect(cmx_pdf_signature_admin_url(['cmx_pdf_signature' => 'error']));
		exit;
	}
	\wp_safe_redirect(cmx_pdf_signature_admin_url(['cmx_pdf_signature' => 'renewed']));
	exit;
});

\add_action('admin_post_cmx_pdf_signature_download_public', static function (): void {
	cmx_pdf_signature_require_access();
	\check_admin_referer('cmx_pdf_signature_download_public');
	$certificate = SignatureService::instance()->certificateManager()->publicCertificate();
	if ($certificate === '') {
		\wp_die(\esc_html__('Public Certificate ist nicht vorhanden.', 'cmx-misbuero'));
	}
	\nocache_headers();
	\header('Content-Type: application/x-pem-file; charset=utf-8');
	\header('Content-Disposition: attachment; filename="misbuero-public-certificate.crt"');
	echo $certificate;
	exit;
});
