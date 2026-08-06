<?php
namespace CLOUDMEISTER\CMX\Buero\PDF;

defined('ABSPATH') || exit;

use WP_Error;

class SignatureService {
	private CertificateManager $certificates;
	private PdfSigner $signer;

	public function __construct(?CertificateManager $certificates = null, ?PdfSigner $signer = null) {
		$this->certificates = $certificates ?: new CertificateManager();
		$this->signer = $signer ?: new PdfSigner();
	}

	public static function instance(): self {
		static $instance = null;
		if (!$instance instanceof self) {
			$instance = new self();
		}
		return $instance;
	}

	public static function activate(): void {
		$result = self::instance()->certificates->ensureCertificate();
		if (\is_wp_error($result)) {
			self::log('Zertifikat konnte bei Aktivierung nicht erzeugt werden.', ['error' => $result->get_error_message()]);
		}
	}

	public function ensureCertificate(): bool|WP_Error {
		return $this->certificates->ensureCertificate();
	}

	public function certificateManager(): CertificateManager {
		return $this->certificates;
	}

	public function signFile(string $pdf_path, array $context = []): bool|WP_Error {
		if (!\apply_filters('cmx_pdf_signature_enabled', true, $pdf_path, $context)) {
			return true;
		}
		$result = $this->certificates->ensureCertificate();
		if (\is_wp_error($result)) {
			self::log('PDF-Signatur übersprungen: Zertifikat fehlt.', ['pdf' => $pdf_path, 'error' => $result->get_error_message()]);
			return $result;
		}

		$result = $this->signer->signFile(
			$pdf_path,
			$this->certificates->certificatePath(),
			$this->certificates->keyPath(),
			$context
		);
		if (\is_wp_error($result)) {
			self::log('PDF-Signatur fehlgeschlagen.', ['pdf' => $pdf_path, 'error' => $result->get_error_message(), 'context' => $context]);
			return $result;
		}

		self::log('PDF signiert.', ['pdf' => $pdf_path, 'context' => $context]);
		\do_action('cmx_pdf_signed', $pdf_path, $context);
		return true;
	}

	public static function signPdf(string $pdf_path, array $context = []): bool|WP_Error {
		return self::instance()->signFile($pdf_path, $context);
	}

	public static function log(string $message, array $context = []): void {
		if ($context !== []) {
			$message .= ' ' . \wp_json_encode($context, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
		}
		\error_log('[CMX PDF Signature] ' . $message);
	}
}
