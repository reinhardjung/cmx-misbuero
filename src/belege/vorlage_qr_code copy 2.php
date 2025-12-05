<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

use Dompdf\Dompdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * QRR prüfen (27 Stellen, Modulo-10).
 */
function cmx_qr_is_valid_qrr(string $ref): bool {
	$ref = preg_replace('~\D+~', '', $ref);
	if (strlen($ref) !== 27 || !ctype_digit($ref)) {
		return false;
	}

	$table = [0,9,4,6,8,2,7,1,3,5];
	$c = 0;

	for ($i = 0; $i < 26; $i++) {
		$c = $table[($c + (int)$ref[$i]) % 10];
	}

	$check = (10 - $c) % 10;
	return $check === (int)$ref[26];
}

/**
 * QRR druckfreundlich formatieren.
 */
function cmx_qr_format_qrr_print(string $ref): string {
	$ref = preg_replace('~\D+~', '', $ref);
	if (strlen($ref) !== 27) {
		return $ref;
	}
	return substr($ref, 0, 2) . ' ' .
	       substr($ref, 2, 5) . ' ' .
	       substr($ref, 7, 5) . ' ' .
	       substr($ref, 12, 5) . ' ' .
	       substr($ref, 17, 5) . ' ' .
	       substr($ref, 22, 5);
}

/**
 * QR-Rechnung nach CH-Norm UNTER den Beleg auf der LETZTEN Seite zeichnen.
 *
 * - Keine neue Seite
 * - Position fix am Seitenende (Empfangsschein + Zahlteil)
 *
 * @param Dompdf $dom
 * @param array  $tpl     Deine Belegdaten (wie bisher)
 * @param int    $post_id Beleg-ID (für Debitor-Adresse)
 */
function cmx_add_qr_page(Dompdf $dom, array $tpl, int $post_id): void {

	$canvas      = $dom->getCanvas();
	$fontMetrics = $dom->getFontMetrics();
	$font        = $fontMetrics->getFont('DejaVu Sans', 'normal');
	$fontBold    = $fontMetrics->getFont('DejaVu Sans', 'bold');

	$mm = 72 / 25.4; // mm → pt

	// Neue Seite erzeugen
	$canvas->new_page();
	$page = $canvas->get_page_number();
	$pageHeight = $canvas->get_height();

	/* ------------------------------------------------------------
	 * DATEN AUFBEREITEN
	 * ------------------------------------------------------------ */
	$opts      = (array) get_option('cmx_einstellungen', []);
	$mode      = strtoupper(trim($opts['qr_mode'] ?? 'NON'));
	$qrr_input = (string)($opts['qr_reference'] ?? '');
	$qrr_raw   = preg_replace('~\D+~', '', $qrr_input);

	$is_qrr = ($mode === 'QRR' && $qrr_raw !== '' && cmx_qr_is_valid_qrr($qrr_raw));
	if (!$is_qrr) { $mode = 'NON'; $qrr_raw = ''; }
	$qrr_print = $is_qrr ? cmx_qr_format_qrr_print($qrr_raw) : '';

	$iban   = trim($tpl['bank']['iban'] ?? '');
	$betrag = number_format((float)($tpl['document']['total'] ?? 0), 2, '.', '');
	$w      = $tpl['document']['currency'] ?: 'CHF';

	$cr_name = $tpl['me']['company'] ?? '';
	$cr_str  = $tpl['me']['strasse'] ?? '';
	$cr_plz  = $tpl['me']['plz'] ?? '';
	$cr_ort  = $tpl['me']['ort'] ?? '';

	$cr_zip  = trim("$cr_plz $cr_ort");

	$deb_raw = (string) get_post_meta($post_id, '_cmx_beleg_kontakt_addr', true);
	$deb     = array_values(array_filter(array_map('trim', preg_split('~[\r\n]+~', $deb_raw))));
	$db1     = $deb[0] ?? '';
	$db2     = $deb[1] ?? '';
	$db3     = $deb[2] ?? '';

	/* ------------------------------------------------------------
	 * QR-CODE ERZEUGEN (PNG)
	 * ------------------------------------------------------------ */
	$qr_data = [
		'SPC','0200','1',$iban,'S',
		$cr_name,$cr_str,'',$cr_plz,$cr_ort,'CH',
		($is_qrr ? 'QRR' : 'NON'),
		$db1,$db2,'','',$db3,'CH',
		$betrag,$w,
		($is_qrr ? 'QRR' : 'NON'),
		($is_qrr ? $qrr_raw : ''),
		'','','','','EPD'
	];
	$qr_raw = implode("\n", $qr_data);

	try {
		$qr = new \Endroid\QrCode\QrCode($qr_raw);
		$qr->setSize((int)(46*$mm));
		$qr->setMargin(0);

		$tmp = wp_tempnam('cmx_qr');
		$writer = new \Endroid\QrCode\Writer\PngWriter();
		file_put_contents($tmp, $writer->write($qr)->getString());
	} catch (\Throwable $e) {
		error_log('[CMX QR] Fehler: '.$e->getMessage());
		return;
	}

	/* ------------------------------------------------------------
	 * CH-NORM LAYOUT – EIGENE SEITE
	 * ------------------------------------------------------------ */

	$zoneH = 105 * $mm;                 // Gesamthöhe
	$zoneTop = ($pageHeight - $zoneH);  // von oben gemessen

	$left = 5 * $mm;
	$esWidth = 62 * $mm;
	$gap = 5 * $mm;
	$ztX = $left + $esWidth + $gap;

	$qrSize = 46 * $mm;
	$qrX = $ztX;
	$qrY = $zoneTop + 30 * $mm;

	/* ---------- TRENNLINIE + SCHERE ---------- */
	$midX = $left + $esWidth + ($gap / 2);

	$canvas->line(
		$midX, $zoneTop + 5*$mm,
		$midX, $zoneTop + $zoneH - 5*$mm,
		[0,0,0],
		$page
	);

	$canvas->text(
		$midX - (3*$mm),
		$zoneTop + 8*$mm,
		'✂',
		$font,
		10,
		[0,0,0],
		$page
	);

	/* ============================================================
	 * EMPFANGSSCHEIN (links)
	 * ============================================================ */
	$x = $left;
	$y = $zoneTop + 10*$mm;

	$canvas->text($x, $y, "Empfangsschein", $fontBold, 12, [0,0,0], $page);
	$y += 8*$mm;

	$canvas->text($x, $y, "Konto / Zahlbar an", $fontBold, 9, [0,0,0], $page);
	$y += 4*$mm;

	foreach ([$iban, $cr_name, $cr_str, $cr_zip] as $line) {
		if ($line !== "") {
			$canvas->text($x, $y, $line, $font, 9, [0,0,0], $page);
			$y += 3.6*$mm;
		}
	}

	if ($is_qrr) {
		$y += 4*$mm;
		$canvas->text($x, $y, "Referenz", $fontBold, 9, [0,0,0], $page);
		$y += 4*$mm;
		$canvas->text($x, $y, $qrr_print, $font, 9, [0,0,0], $page);
	}

	$y += 6*$mm;
	$canvas->text($x, $y, "Zahlbar durch", $fontBold, 9, [0,0,0], $page);
	$y += 4*$mm;

	foreach ([$db1,$db2,$db3] as $line) {
		if ($line !== "") {
			$canvas->text($x, $y, $line, $font, 9, [0,0,0], $page);
			$y += 3.6*$mm;
		}
	}

	// Währung/Betrag unten links
	$yb = $zoneTop + $zoneH - 20*$mm;
	$canvas->text($x, $yb, "Währung", $fontBold, 9, [0,0,0], $page);
	$canvas->text($x + 28*$mm, $yb, "Betrag", $fontBold, 9, [0,0,0], $page);

	$yb += 4*$mm;
	$canvas->text($x, $yb, $w, $font, 9, [0,0,0], $page);
	$canvas->text($x + 28*$mm, $yb, $betrag, $font, 9, [0,0,0], $page);

	$canvas->text($x + 40*$mm, $zoneTop + $zoneH - 6*$mm, "Annahmestelle", $font, 8, [0,0,0], $page);

	/* ============================================================
	 * ZAHLTEIL (rechts)
	 * ============================================================ */
	$x = $ztX;
	$y = $zoneTop + 10*$mm;

	$canvas->text($x, $y, "Zahlteil", $fontBold, 12, [0,0,0], $page);
	$y += 8*$mm;

	$canvas->text($x, $y, "Konto / Zahlbar an", $fontBold, 9, [0,0,0], $page);
	$y += 4*$mm;

	foreach ([$iban, $cr_name, $cr_str, $cr_zip] as $line) {
		if ($line !== "") {
			$canvas->text($x, $y, $line, $font, 9, [0,0,0], $page);
			$y += 3.6*$mm;
		}
	}

	if ($is_qrr) {
		$y += 4*$mm;
		$canvas->text($x, $y, "Referenz", $fontBold, 9, [0,0,0], $page);
		$y += 4*$mm;
		$canvas->text($x, $y, $qrr_print, $font, 9, [0,0,0], $page);
	}

	$y += 6*$mm;
	$canvas->text($x, $y, "Zahlbar durch", $fontBold, 9, [0,0,0], $page);
	$y += 4*$mm;

	foreach ([$db1,$db2,$db3] as $line) {
		if ($line !== "") {
			$canvas->text($x, $y, $line, $font, 9, [0,0,0], $page);
			$y += 3.6*$mm;
		}
	}

	// Betrag / Währung über dem QR
	$wy = $qrY + $qrSize + 8*$mm;
	$canvas->text($x, $wy, "Währung", $fontBold, 9, [0,0,0], $page);
	$canvas->text($x + 30*$mm, $wy, "Betrag", $fontBold, 9, [0,0,0], $page);
	$wy += 4*$mm;

	$canvas->text($x, $wy, $w, $font, 9, [0,0,0], $page);
	$canvas->text($x + 30*$mm, $wy, $betrag, $font, 9, [0,0,0], $page);

	/* ---------- QR Code ---------- */
	$canvas->image($tmp, $qrX, $qrY, $qrSize, $qrSize, $page);

	@unlink($tmp);
}
