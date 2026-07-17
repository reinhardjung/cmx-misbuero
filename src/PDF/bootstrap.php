<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

require_once __DIR__ . '/CertificateManager.php';
require_once __DIR__ . '/PdfSigner.php';
require_once __DIR__ . '/SignatureService.php';

use CLOUDMEISTER\CMX\Buero\PDF\SignatureService;

if (!\function_exists(__NAMESPACE__ . '\\cmx_pdf_signature_sign_file')) {
	function cmx_pdf_signature_sign_file(string $pdf_path, array $context = []): bool|\WP_Error {
		return SignatureService::signPdf($pdf_path, $context);
	}
}

\add_action('init', static function (): void {
	if (\defined('WP_CLI') && \WP_CLI) {
		return;
	}

	$result = SignatureService::instance()->ensureCertificate();
	if (\is_wp_error($result)) {
		SignatureService::log('Zertifikat konnte beim Plugin-Start nicht erzeugt werden.', ['error' => $result->get_error_message()]);
	}
}, 20);

\add_action('cmx_pdf_generated', static function (string $pdf_path, array $context = []): void {
	$result = SignatureService::signPdf($pdf_path, $context);
	if (\is_wp_error($result)) {
		SignatureService::log('Automatische Signatur über cmx_pdf_generated fehlgeschlagen.', ['pdf' => $pdf_path, 'error' => $result->get_error_message()]);
	}
}, 10, 2);
