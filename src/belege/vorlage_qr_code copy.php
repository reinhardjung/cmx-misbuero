<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

use Dompdf\Dompdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Prüft, ob eine QRR-Referenz (27 Stellen, Mod10) gültig ist.
 */
function cmx_qr_is_valid_qrr(string $ref): bool {
	$ref = preg_replace('~\D+~', '', $ref);
	if (strlen($ref) !== 27 || !ctype_digit($ref)) {
		return false;
	}
	$table = [0,9,4,6,8,2,7,1,3,5];
	$carry = 0;
	for ($i = 0; $i < 26; $i++) {
		$carry = $table[($carry + (int)$ref[$i]) % 10];
	}
	$check = (10 - $carry) % 10;
	return $check === (int)$ref[26];
}

/**
 * Formatiert eine 27-stellige QRR in Blöcke wie "21 00000 00003 ..."
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
 * QR-Seite hinzufügen (Empfangsschein + Zahlteil).
 *
 * $post_id = Beleg-ID (für Debitor-Adresse).
 */
function cmx_add_qr_page(Dompdf $dom, array $tpl, int $post_id): void {

    $canvas      = $dom->getCanvas();
    $fontMetrics = $dom->getFontMetrics();

    $fontNormal  = $fontMetrics->getFont('DejaVu Sans', 'normal');
    $fontBold    = $fontMetrics->getFont('DejaVu Sans', 'bold');

    /* --------------------------------------
     * mm → px Converter (Dompdf Standard)
     * -------------------------------------- */
    $mm = 2.83464567;

    // Seitenhöhe (A4 Portrait)
    $page = $canvas->get_page_number();

		$dom->getCanvas()->page_script('');
		$dom->getCanvas()->new_page();

    $page = $canvas->get_page_number();

    /* --------------------------------------
     * Offizielle QR-Rechnung Maße
     * -------------------------------------- */
    $leftMargin     = 5 * $mm;

    $esWidth        = 105 * $mm;   // Empfangsschein
    $ztWidth        = 148 * $mm;   // Zahlteil
    $qrSize         = 46 * $mm;    // QR Code
    $top            = 20 * $mm;


    /* --------------------------------------
     * Daten vorbereiten
     * -------------------------------------- */
    $iban   = trim($tpl['bank']['iban']);
    $betrag = number_format((float)$tpl['document']['total'], 2, '.', '');
    $w      = $tpl['document']['currency'] ?? 'CHF';

    $cr_name = $tpl['me']['company'] ?? '';
    $cr_str  = $tpl['me']['strasse'] ?? '';
    $cr_zip  = trim(($tpl['me']['plz'] ?? '') . ' ' . ($tpl['me']['ort'] ?? ''));

    $deb_raw = (string) get_post_meta($post_id, '_cmx_beleg_kontakt_addr', true);
    $deb     = preg_split('~[\r\n]+~', $deb_raw);
    $deb     = array_filter(array_map('trim', $deb));

    /* --------------------------------------
     * QR Code generieren
     * -------------------------------------- */
    $qrTmp = wp_tempnam('qr');
    $qrPng = (new PngWriter())->write(
        new QrCode(
            implode("\n", [
                'SPC','0200','1',
                $iban,
                'S',$cr_name,$cr_str,'',$tpl['me']['plz'],$tpl['me']['ort'],'CH',
                'NON','','','','','',
                $deb[0] ?? '', $deb[1] ?? '', '', '', $deb[2] ?? '', 'CH',
                $betrag, $w, 'NON', '', '', '', '', 'EPD'
            ])
        )
    )->getString();

    file_put_contents($qrTmp, $qrPng);


    /* --------------------------------------
     * Trennlinie (offizieller Schnittpunkt)
     * -------------------------------------- */
    $canvas->line(
        $leftMargin + $esWidth,
        10 * $mm,
        $leftMargin + $esWidth,
        287 * $mm,
        [0,0,0],
        $page
    );

    /* --------------------------------------
     * "✂" Symbol
     * -------------------------------------- */
    $canvas->text(
        ($leftMargin + $esWidth) - (3*$mm),
        15 * $mm,
        "✂",
        $fontNormal,
        10,
        [0,0,0],
        $page
    );


    /* --------------------------------------
     * EMPFANGSSCHEIN (links)
     * -------------------------------------- */
    $x = $leftMargin;
    $y = $top;

    $canvas->text($x, $y, "Empfangsschein", $fontBold, 12, [0,0,0], $page);
    $y += 8 * $mm;

    $canvas->text($x, $y, "Konto / Zahlbar an", $fontBold, 9, [0,0,0], $page);
    $y += 4 * $mm;

    foreach ([$iban, $cr_name, $cr_str, $cr_zip] as $line) {
        if ($line !== "") {
            $canvas->text($x, $y, $line, $fontNormal, 9, [0,0,0], $page);
            $y += 3.8 * $mm;
        }
    }

    $y += 6 * $mm;
    $canvas->text($x, $y, "Zahlbar durch", $fontBold, 9, [0,0,0], $page);
    $y += 4 * $mm;

    foreach ($deb as $line) {
        $canvas->text($x, $y, $line, $fontNormal, 9, [0,0,0], $page);
        $y += 3.8 * $mm;
    }

    /* Betrag */
    $yb = 260 * $mm;
    $canvas->text($x, $yb, "Währung", $fontBold, 9, [0,0,0], $page);
    $canvas->text($x + 35*$mm, $yb, "Betrag", $fontBold, 9, [0,0,0], $page);

    $yb += 4*$mm;
    $canvas->text($x, $yb, $w, $fontNormal, 9, [0,0,0], $page);
    $canvas->text($x + 35*$mm, $yb, $betrag, $fontNormal, 9, [0,0,0], $page);


    /* --------------------------------------
     * ZAHLTEIL (rechts)
     * -------------------------------------- */
    $x = $leftMargin + $esWidth + (6*$mm);
    $y = $top;

    $canvas->text($x, $y, "Zahlteil", $fontBold, 12, [0,0,0], $page);
    $y += 8*$mm;

    $canvas->text($x, $y, "Konto / Zahlbar an", $fontBold, 9, [0,0,0], $page);
    $y += 4*$mm;

    foreach ([$iban, $cr_name, $cr_str, $cr_zip] as $line) {
        if ($line !== "") {
            $canvas->text($x, $y, $line, $fontNormal, 9, [0,0,0], $page);
            $y += 3.8*$mm;
        }
    }

    /* QR CODE */
    $qrX = $x;
    $qrY = 70 * $mm;

    $canvas->image(
        $qrTmp,
        $qrX,
        $qrY,
        $qrSize,
        $qrSize,
        $page
    );

    unlink($qrTmp);
}
