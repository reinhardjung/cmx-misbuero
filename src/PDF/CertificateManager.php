<?php
namespace CLOUDMEISTER\CMX\Buero\PDF;

defined('ABSPATH') || exit;

use WP_Error;

class CertificateManager {
	private const CERT_FILE = 'certificate.crt';
	private const KEY_FILE = 'private.key';
	private const OPTION_META = 'cmx_pdf_signature_certificate_meta';
	private const VALID_DAYS = 3650;

	public function ensureCertificate(): bool|WP_Error {
		if ($this->hasCertificate()) {
			$dir = $this->directory();
			if ($dir !== '') {
				$this->protectDirectory($dir);
			}
			return true;
		}
		return $this->generate(false);
	}

	public function generate(bool $renew = false): bool|WP_Error {
		if (!$renew && $this->hasCertificate()) {
			return true;
		}
		if (!\function_exists('openssl_pkey_new') || !\function_exists('openssl_csr_new') || !\function_exists('openssl_csr_sign')) {
			return new WP_Error('openssl_missing', __('OpenSSL ist auf diesem Server nicht verfügbar.', 'cmx-misbuero'));
		}

		$dir = $this->directory();
		if ($dir === '' || !\wp_mkdir_p($dir)) {
			return new WP_Error('directory_failed', __('Das Zertifikatsverzeichnis konnte nicht erstellt werden.', 'cmx-misbuero'));
		}
		$this->protectDirectory($dir);

		$config = [
			'private_key_type' => \OPENSSL_KEYTYPE_RSA,
			'private_key_bits' => 4096,
		];
		$key = \openssl_pkey_new($config);
		if ($key === false) {
			return new WP_Error('key_failed', $this->opensslError(__('Der private Schlüssel konnte nicht erzeugt werden.', 'cmx-misbuero')));
		}

		$dn = [
			'countryName' => 'CH',
			'organizationName' => $this->siteLabel(),
			'commonName' => $this->siteLabel() . ' PDF Signature',
		];
		$csr = \openssl_csr_new($dn, $key, ['digest_alg' => 'sha256']);
		if ($csr === false) {
			return new WP_Error('csr_failed', $this->opensslError(__('Der Zertifikatsantrag konnte nicht erzeugt werden.', 'cmx-misbuero')));
		}

		$certificate = \openssl_csr_sign($csr, null, $key, self::VALID_DAYS, ['digest_alg' => 'sha256']);
		if ($certificate === false) {
			return new WP_Error('certificate_failed', $this->opensslError(__('Das Zertifikat konnte nicht signiert werden.', 'cmx-misbuero')));
		}

		$private_key_pem = '';
		$certificate_pem = '';
		if (!\openssl_pkey_export($key, $private_key_pem) || !\openssl_x509_export($certificate, $certificate_pem)) {
			return new WP_Error('export_failed', $this->opensslError(__('Zertifikat oder privater Schlüssel konnten nicht exportiert werden.', 'cmx-misbuero')));
		}

		if (@\file_put_contents($this->keyPath(), $private_key_pem, \LOCK_EX) === false) {
			return new WP_Error('key_write_failed', __('Der private Schlüssel konnte nicht gespeichert werden.', 'cmx-misbuero'));
		}
		@\chmod($this->keyPath(), 0600);

		if (@\file_put_contents($this->certificatePath(), $certificate_pem, \LOCK_EX) === false) {
			return new WP_Error('certificate_write_failed', __('Das Zertifikat konnte nicht gespeichert werden.', 'cmx-misbuero'));
		}
		@\chmod($this->certificatePath(), 0640);

		\update_option(self::OPTION_META, [
			'created_at' => \current_time('mysql'),
			'renewed_at' => $renew ? \current_time('mysql') : '',
		], false);

		return true;
	}

	public function renew(): bool|WP_Error {
		return $this->generate(true);
	}

	public function hasCertificate(): bool {
		return \is_readable($this->certificatePath()) && \is_readable($this->keyPath());
	}

	public function certificatePath(): string {
		return $this->directory() . self::CERT_FILE;
	}

	public function keyPath(): string {
		return $this->directory() . self::KEY_FILE;
	}

	public function publicCertificate(): string {
		$path = $this->certificatePath();
		return \is_readable($path) ? (string) \file_get_contents($path) : '';
	}

	public function info(): array {
		$certificate = $this->publicCertificate();
		$parsed = $certificate !== '' ? @\openssl_x509_parse($certificate) : false;
		$meta = (array) \get_option(self::OPTION_META, []);
		$fingerprint = $certificate !== '' && \function_exists('openssl_x509_fingerprint')
			? (string) @\openssl_x509_fingerprint($certificate, 'sha256')
			: '';

		return [
			'exists' => $this->hasCertificate(),
			'created_at' => (string) ($meta['created_at'] ?? ''),
			'expires_at' => \is_array($parsed) && !empty($parsed['validTo_time_t']) ? \wp_date('Y-m-d H:i:s', (int) $parsed['validTo_time_t']) : '',
			'valid_from' => \is_array($parsed) && !empty($parsed['validFrom_time_t']) ? \wp_date('Y-m-d H:i:s', (int) $parsed['validFrom_time_t']) : '',
			'fingerprint' => $fingerprint,
			'subject' => \is_array($parsed) && \is_array($parsed['subject'] ?? null) ? (array) $parsed['subject'] : [],
			'directory' => $this->directory(),
		];
	}

	public function directory(): string {
		$uploads = \wp_get_upload_dir();
		$basedir = \wp_normalize_path((string) ($uploads['basedir'] ?? ''));
		return $basedir !== '' ? \trailingslashit($basedir) . 'misbuero/security/' : '';
	}

	private function protectDirectory(string $dir): void {
		$index = \trailingslashit($dir) . 'index.html';
		$htaccess = \trailingslashit($dir) . '.htaccess';
		$web_config = \trailingslashit($dir) . 'web.config';
		if (!\is_file($index)) {
			@\file_put_contents($index, '', \LOCK_EX);
		}
		if (!\is_file($htaccess)) {
			@\file_put_contents($htaccess, "Require all denied\nDeny from all\n", \LOCK_EX);
		}
		if (!\is_file($web_config)) {
			@\file_put_contents($web_config, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><security><requestFiltering><fileExtensions><add fileExtension=\".key\" allowed=\"false\" /><add fileExtension=\".crt\" allowed=\"false\" /></fileExtensions></requestFiltering></security></system.webServer></configuration>\n", \LOCK_EX);
		}
	}

	private function siteLabel(): string {
		$label = \trim((string) \get_bloginfo('name'));
		return $label !== '' ? $label : 'Mis Buero';
	}

	private function opensslError(string $fallback): string {
		$error = '';
		while ($message = \openssl_error_string()) {
			$error = (string) $message;
		}
		return $error !== '' ? $fallback . ' ' . $error : $fallback;
	}
}
