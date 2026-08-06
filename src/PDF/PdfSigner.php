<?php
namespace CLOUDMEISTER\CMX\Buero\PDF;

defined('ABSPATH') || exit;

use setasign\Fpdi\Tcpdf\Fpdi;
use WP_Error;

class PdfSigner {
	public function signFile(string $pdf_path, string $certificate_path, string $private_key_path, array $context = []): bool|WP_Error {
		$pdf_path = \wp_normalize_path($pdf_path);
		if ($pdf_path === '' || !\is_file($pdf_path) || !\is_readable($pdf_path) || !\is_writable($pdf_path)) {
			return new WP_Error('pdf_not_writable', __('PDF-Datei ist nicht les- oder schreibbar.', 'cmx-misbuero'));
		}
		if (!\is_readable($certificate_path) || !\is_readable($private_key_path)) {
			return new WP_Error('certificate_missing', __('Zertifikat oder privater Schlüssel fehlen.', 'cmx-misbuero'));
		}
		if (!\class_exists(Fpdi::class)) {
			return new WP_Error('signer_missing', __('FPDI/TCPDF ist nicht verfügbar.', 'cmx-misbuero'));
		}

		$temp_path = \wp_normalize_path((string) \wp_tempnam('cmx-signed-pdf'));
		if ($temp_path === '') {
			return new WP_Error('temp_failed', __('Temporäre PDF-Datei konnte nicht erstellt werden.', 'cmx-misbuero'));
		}

		try {
			$pdf = new Fpdi('P', 'mm');
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
			$pdf->SetMargins(0, 0, 0);
			$pdf->SetAutoPageBreak(false, 0);
			$pdf->setCompression(true);
			$pdf->setCreator('Mis Büro');
			$pdf->setAuthor((string) \get_bloginfo('name'));
			$pdf->setTitle((string) ($context['title'] ?? \basename($pdf_path)));

			$info = [
				'Name' => (string) \get_bloginfo('name'),
				'Location' => (string) \home_url('/'),
				'Reason' => __('Mis Büro Dokumentintegrität', 'cmx-misbuero'),
				'ContactInfo' => (string) \get_option('admin_email'),
			];
			$pdf->setSignature('file://' . $certificate_path, 'file://' . $private_key_path, '', '', 2, $info);

			$page_count = $pdf->setSourceFile($pdf_path);
			$payrexx_vpos_url = \trim((string) ($context['payrexx_vpos_url'] ?? ''));
			for ($page = 1; $page <= $page_count; $page++) {
				$template_id = $pdf->importPage($page, 'CropBox', true, true);
				$size = $pdf->getTemplateSize($template_id);
				$width = (float) ($size['width'] ?? 210);
				$height = (float) ($size['height'] ?? 297);
				$orientation = (string) ($size['orientation'] ?? ($width > $height ? 'L' : 'P'));
				$pdf->AddPage($orientation, [$width, $height]);
				$pdf->useTemplate($template_id, 0, 0, $width, $height, true);
				if ($page === 1 && $payrexx_vpos_url !== '') {
					$pdf->Link(115, 75, 88, 55, $payrexx_vpos_url);
				}
			}

			$pdf->Output($temp_path, 'F');
			if (!\is_file($temp_path) || \filesize($temp_path) <= 0) {
				return new WP_Error('signature_output_failed', __('Signierte PDF-Datei wurde nicht erzeugt.', 'cmx-misbuero'));
			}
			if (!@\copy($temp_path, $pdf_path)) {
				return new WP_Error('signature_replace_failed', __('Original-PDF konnte nicht durch die signierte Version ersetzt werden.', 'cmx-misbuero'));
			}
		} catch (\Throwable $exception) {
			return new WP_Error('signature_failed', $exception->getMessage());
		} finally {
			if ($temp_path !== '' && \is_file($temp_path)) {
				@\unlink($temp_path);
			}
		}

		return true;
	}

	public function validateFile(string $pdf_path): bool|WP_Error {
		$pdf_path = \wp_normalize_path($pdf_path);
		if ($pdf_path === '' || !\is_readable($pdf_path)) {
			return new WP_Error('pdf_missing', __('PDF-Datei fehlt.', 'cmx-misbuero'));
		}
		$head = (string) @\file_get_contents($pdf_path, false, null, 0, 5);
		if ($head !== '%PDF-') {
			return new WP_Error('pdf_invalid', __('Datei ist kein PDF.', 'cmx-misbuero'));
		}
		$content = (string) @\file_get_contents($pdf_path);
		return \str_contains($content, '/ByteRange') && \str_contains($content, '/Contents');
	}
}
