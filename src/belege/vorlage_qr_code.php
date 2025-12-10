<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

use Dompdf\Dompdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Prüft eine QRR-Referenz (27 Stellen, Modulo-10).
 */
function cmx_qr_is_valid_qrr(string $ref): bool
{
    $ref = preg_replace('~\D+~', '', $ref);
    if (strlen($ref) !== 27 || !ctype_digit($ref)) {
        return false;
    }

    $table = [0,9,4,6,8,2,7,1,3,5];
    $c     = 0;

    for ($i = 0; $i < 26; $i++) {
        $c = $table[($c + (int) $ref[$i]) % 10];
    }

    $check = (10 - $c) % 10;
    return $check === (int) $ref[26];
}

/**
 * QRR druckfreundlich formatieren.
 * z.B. 210000000003139471430009017 → "21 00000 00003 13947 14300 09017"
 */
function cmx_qr_format_qrr_print(string $ref): string
{
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
 * QR-Rechnungsblock (Empfangsschein + Zahlteil) am unteren Rand der LETZTEN Seite.
 *
 * A: Keine neue Seite – wird an den Beleg angehängt
 * B: Kein Footer, keine Seitenzahlen
 * C: Layout angelehnt an offizielle CH-Norm (210 × 105 mm, 62 mm Empfangsschein, 46 mm QR-Code)
 *
 * @param Dompdf $dom
 * @param array  $tpl      Beleg-Daten (wie in deinem Generator)
 * @param int    $post_id  Beleg-ID (für Debitor-Adresse)
 */
function cmx_add_qr_page(Dompdf $dom, array $tpl, int $post_id): void
{
    $canvas      = $dom->getCanvas();
    $fontMetrics = $dom->getFontMetrics();

    // Helvetica / Arial-ähnliche Schrift (für CH-Schriftverkehr ok)
    $font     = $fontMetrics->getFont('DejaVu Sans', 'normal');
    $fontBold = $fontMetrics->getFont('DejaVu Sans', 'bold');

    // Dompdf arbeitet in POINTS
    // 1 mm = 72 / 25.4 pt
    $mm = 72 / 25.4;

    /** ----------------------------------------------------------------
     * 1) Einstellungen & QRR
     * ---------------------------------------------------------------- */
    $opts      = (array) \get_option('cmx_einstellungen', []);
    $mode      = strtoupper(trim($opts['qr_mode'] ?? 'NON'));
    $qrr_input = (string) ($opts['qr_reference'] ?? '');
    $qrr_raw   = preg_replace('~\D+~', '', $qrr_input);

    $is_qrr = ($mode === 'QRR' && $qrr_raw !== '' && cmx_qr_is_valid_qrr($qrr_raw));
    if (!$is_qrr) {
        $mode    = 'NON';
        $qrr_raw = '';
    }

    $qrr_print = $is_qrr ? cmx_qr_format_qrr_print($qrr_raw) : '';

    /** ----------------------------------------------------------------
     * 2) Beträge & Adressen
     * ---------------------------------------------------------------- */
    $iban   = trim((string) ($tpl['bank']['iban'] ?? ''));
    if ($iban === '') {
        // Ohne IBAN kein QR-Code auf dem Beleg
        return;
    }
    $amount = (float) ($tpl['document']['total'] ?? 0);
    // EMV braucht Punkt, KEINE Tausendertrennzeichen
    $betrag_emv = number_format($amount, 2, '.', '');
    // Für Druck etwas hübscher (Leerzeichen als Tausender)
    $betrag_print = number_format($amount, 2, '.', ' ');

    $w = $tpl['document']['currency'] ?: 'CHF';

    // Creditor (du)
    $cr_name = (string) ($tpl['me']['company'] ?? '');
    $cr_str  = (string) ($tpl['me']['strasse'] ?? '');
    $cr_plz  = (string) ($tpl['me']['plz'] ?? '');
    $cr_ort  = (string) ($tpl['me']['ort'] ?? '');
    $cr_zip  = trim($cr_plz . ' ' . $cr_ort);

    // Debitor-Adresse aus Beleg
    $deb_raw = (string) \get_post_meta($post_id, '_cmx_beleg_kontakt_addr', true);
    $deb     = array_values(array_filter(array_map('trim', preg_split('~[\r\n]+~', $deb_raw))));
    $db_1    = $deb[0] ?? '';
    $db_2    = $deb[1] ?? '';
    $db_3    = $deb[2] ?? '';

    /** ----------------------------------------------------------------
     * 3) EMV-QR-Payload bauen
     * ---------------------------------------------------------------- */
    $qr_data = [
        'SPC',                  // QR-Typ
        '0200',                 // Version
        '1',                    // Codierung
        $iban,                  // IBAN
        'S',                    // Struktur (S = combined)
        $cr_name,
        $cr_str,
        '',
        $cr_plz,
        $cr_ort,
        'CH',
        ($is_qrr ? 'QRR' : 'NON'),  // Referenz-Typ
        $db_1,
        $db_2,
        '',
        '',
        $db_3,
        'CH',
        $betrag_emv,           // Betrag
        $w,
        ($is_qrr ? 'QRR' : 'NON'),
        ($is_qrr ? $qrr_raw : ''),
        '',
        '',
        '',
        '',
        'EPD'
    ];
    $qr_raw = implode("\n", $qr_data);

		/** ----------------------------------------------------------------
		 * 4) QR-Bild generieren (grösser + Schweizer Kreuz für Endroid v4/v5)
		 * ---------------------------------------------------------------- */
		try {

				// QR-Code 55 mm gross
				$qr_size_mm = 80 * $mm;

				$qr = QrCode::create($qr_raw)
						->setSize((int) $qr_size_mm)
						->setMargin(0);

				// Schweizer Kreuz laden (PNG-Datei MUSS existieren)
				$logoPath = __DIR__ . '/swiss-cross.png';

				$writer   = new PngWriter();

				if (file_exists($logoPath)) {
						// LOGO integration über Writer::write()
						$logo = \Endroid\QrCode\Logo\Logo::create($logoPath)
								->setResizeToWidth((int)(7 * $mm)); // 7 mm laut Norm

						$qrResult = $writer->write($qr, logo: $logo);
				} else {
						// Ohne Logo
						$qrResult = $writer->write($qr);
				}

				$tmp = \wp_tempnam('cmx_qr');
				\file_put_contents($tmp, $qrResult->getString());

		} catch (\Throwable $e) {
				\error_log('[CMX QR] Fehler beim QR-Code: ' . $e->getMessage());
				return;
		}


		/** ----------------------------------------------------------------
     * 5) Geometrie – QR-Zone am Ende der letzten Seite
     * ---------------------------------------------------------------- */
    $page_count  = $canvas->get_page_count();
    if ($page_count < 1) {
        @\unlink($tmp);
        return;
    }

    $page        = $page_count;              // letzte Seite
    $page_height = $canvas->get_height();    // in pt

    $zone_height = 105 * $mm;                // gesamter QR-Block (Empfangsschein + Zahlteil)
    $zone_top    = $page_height - $zone_height;

    $margin_left   = 5 * $mm;
    $receipt_width = 62 * $mm;               // Empfangsscheinbreite
    $gap           = 3 * $mm;

    $cut_x        = $margin_left + $receipt_width + $gap; // vertikale Trennlinie
    $zahlteil_x   = $cut_x + $gap;                        // Start des Zahlteils

    $qr_size      = 46 * $mm;
    $qr_x         = $zahlteil_x + 10 * $mm;               // etwas eingerückt
    // $qr_x         = $zahlteil_x * $mm;               // etwas eingerückt
    $qr_y         = $zone_top + 30 * $mm;                 // ungefähr mittig in der Höhe
    // $qr_y         = $zone_top * $mm;                 // ungefähr mittig in der Höhe

    /** ----------------------------------------------------------------
     * 6) Trennlinie + Schere
     * ---------------------------------------------------------------- */
    $canvas->line(
        $cut_x,
        $zone_top + 4 * $mm,
        $cut_x,
        $zone_top + $zone_height - 4 * $mm,
        [0, 0, 0],
        $page
    );

    $canvas->text(
        $cut_x - (3 * $mm),
        $zone_top + 8 * $mm,
        '✂',
        $font,
        10,
        [0, 0, 0],
        $page
    );

    /** ----------------------------------------------------------------
     * 7) EMPFANGSSCHEIN (linke Seite)
     * ---------------------------------------------------------------- */
    $x = $margin_left;
    $y = $zone_top + 10 * $mm;

    $canvas->text($x, $y, 'Empfangsschein', $fontBold, 11, [0, 0, 0], $page);
    $y += 8 * $mm;

    $canvas->text($x, $y, 'Konto / Zahlbar an', $fontBold, 8.5, [0, 0, 0], $page);
    $y += 4 * $mm;

    foreach ([$iban, $cr_name, $cr_str, $cr_zip] as $line) {
        if ($line !== '') {
            $canvas->text($x, $y, $line, $font, 8.5, [0, 0, 0], $page);
            $y += 3.5 * $mm;
        }
    }

    if ($is_qrr && $qrr_print !== '') {
        $y += 4 * $mm;
        $canvas->text($x, $y, 'Referenz', $fontBold, 8.5, [0, 0, 0], $page);
        $y += 4 * $mm;
        $canvas->text($x, $y, $qrr_print, $font, 8.5, [0, 0, 0], $page);
    }

    $y += 6 * $mm;
    $canvas->text($x, $y, 'Zahlbar durch', $fontBold, 8.5, [0, 0, 0], $page);
    $y += 4 * $mm;

    foreach ([$db_1, $db_2, $db_3] as $line) {
        if ($line !== '') {
            $canvas->text($x, $y, $line, $font, 8.5, [0, 0, 0], $page);
            $y += 3.5 * $mm;
        }
    }

    // Betrag links unten
    $yb = $zone_top + $zone_height - 20 * $mm;
    $canvas->text($x, $yb, 'Währung', $fontBold, 8.5, [0, 0, 0], $page);
    $canvas->text($x + 28 * $mm, $yb, 'Betrag', $fontBold, 8.5, [0, 0, 0], $page);

    $yb += 4 * $mm;
    $canvas->text($x, $yb, $w, $font, 8.5, [0, 0, 0], $page);
    $canvas->text($x + 28 * $mm, $yb, $betrag_print, $font, 8.5, [0, 0, 0], $page);

    $canvas->text(
        $x + 35 * $mm,
        $zone_top + $zone_height - 7 * $mm,
        'Annahmestelle',
        $font,
        7,
        [0, 0, 0],
        $page
    );

    /** ----------------------------------------------------------------
     * 8) ZAHLTEIL (rechte Seite)
     * ---------------------------------------------------------------- */
    // Titel links im Zahlteil
    $x = $zahlteil_x;
    $y = $zone_top + 10 * $mm;

    $canvas->text($x, $y, 'Zahlteil', $fontBold, 11, [0, 0, 0], $page);

    // Konto-Block rechts neben dem QR-Code
    $acc_x = $qr_x + $qr_size + 8 * $mm;
    $acc_y = $zone_top + 10 * $mm;

    $canvas->text($acc_x, $acc_y, 'Konto / Zahlbar an', $fontBold, 8.5, [0, 0, 0], $page);
    $acc_y += 4 * $mm;

    foreach ([$iban, $cr_name, $cr_str, $cr_zip] as $line) {
        if ($line !== '') {
            $canvas->text($acc_x, $acc_y, $line, $font, 8.5, [0, 0, 0], $page);
            $acc_y += 3.5 * $mm;
        }
    }

    if ($is_qrr && $qrr_print !== '') {
        $acc_y += 4 * $mm;
        $canvas->text($acc_x, $acc_y, 'Referenz', $fontBold, 8.5, [0, 0, 0], $page);
        $acc_y += 4 * $mm;
        $canvas->text($acc_x, $acc_y, $qrr_print, $font, 8.5, [0, 0, 0], $page);
    }

    // OPTIONAL: Zusätzliche Infos (hier Betreff)
    $subject = trim((string) ($tpl['document']['subject'] ?? ''));
    if ($subject !== '') {
        $acc_y += 4 * $mm;
        $canvas->text($acc_x, $acc_y, 'Zusätzliche Informationen', $fontBold, 8.5, [0, 0, 0], $page);
        $acc_y += 4 * $mm;
        $canvas->text($acc_x, $acc_y, $subject, $font, 8.5, [0, 0, 0], $page);
    }

    // Zahler rechts unterhalb des QR-Codes
    $pay_x = $acc_x;
    $pay_y = $qr_y + $qr_size + 10 * $mm;

    $canvas->text($pay_x, $pay_y, 'Zahlbar durch', $fontBold, 8.5, [0, 0, 0], $page);
    $pay_y += 4 * $mm;

    foreach ([$db_1, $db_2, $db_3] as $line) {
        if ($line !== '') {
            $canvas->text($pay_x, $pay_y, $line, $font, 8.5, [0, 0, 0], $page);
            $pay_y += 3.5 * $mm;
        }
    }

    // Währung / Betrag beim QR
    $wy = $qr_y + $qr_size + 8 * $mm;
    $canvas->text($x, $wy, 'Währung', $fontBold, 8.5, [0, 0, 0], $page);
    $canvas->text($x + 28 * $mm, $wy, 'Betrag', $fontBold, 8.5, [0, 0, 0], $page);

    $wy += 4 * $mm;
    $canvas->text($x, $wy, $w, $font, 8.5, [0, 0, 0], $page);
    $canvas->text($x + 28 * $mm, $wy, $betrag_print, $font, 8.5, [0, 0, 0], $page);

    /** ----------------------------------------------------------------
     * 9) QR-Code platzieren
     * ---------------------------------------------------------------- */
    $canvas->image($tmp, $qr_x, $qr_y, $qr_size, $qr_size, $page);

    // Temporäre Datei löschen
    @\unlink($tmp);
}
