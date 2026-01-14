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
    $qr_enabled = !empty($tpl['qr']['enabled']);
    $qr_iban    = trim((string)($tpl['qr']['iban'] ?? $tpl['bank']['qr_iban'] ?? ''));
    $doc_type   = strtolower(trim((string)($tpl['document']['type'] ?? '')));

    if (!$qr_enabled || $qr_iban === '' || $doc_type !== 'rechnung') {
        return;
    }

    $opts_general = \get_option('cmx_einstellungen', []);
    $ref_mode = strtoupper(trim((string)($opts_general['qr_mode'] ?? 'NON')));
    $ref_raw = trim((string)($opts_general['qr_reference'] ?? ''));

    $is_qrr = false;
    $is_scor = false;
    $ref_value = '';
    $ref_print = '';

    if ($ref_raw !== '') {
        $qrr_digits = preg_replace('~\D+~', '', $ref_raw);
        if (strlen($qrr_digits) === 27 && cmx_qr_is_valid_qrr($qrr_digits)) {
            $is_qrr = true;
            $ref_value = $qrr_digits;
            $ref_print = cmx_qr_format_qrr_print($qrr_digits);
        } elseif (preg_match('/^RF[0-9A-Z]{2,}$/i', str_replace(' ', '', $ref_raw))) {
            $is_scor = true;
            $ref_value = strtoupper(str_replace(' ', '', $ref_raw));
            $ref_print = trim(chunk_split($ref_value, 4, ' '));
        }
    }

    if (!$is_qrr && !$is_scor) {
        $ref_mode = 'NON';
    } elseif ($is_qrr) {
        $ref_mode = 'QRR';
    } elseif ($is_scor) {
        $ref_mode = 'SCOR';
    }

    /** ----------------------------------------------------------------
     * 2) Beträge & Adressen
     * ---------------------------------------------------------------- */
    $bank_iban = trim((string) ($tpl['bank']['iban'] ?? ''));
    $iban = ($ref_mode === 'QRR') ? $qr_iban : $bank_iban;
    if ($iban === '') {
        $iban = $qr_iban;
    }

    if ($ref_mode === 'QRR' && $qr_iban === '') {
        $ref_mode = 'NON';
        $ref_value = '';
        $ref_print = '';
    }

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
    $db_plz  = '';
    $db_ort  = '';
    $db_country = 'CH';

    if ($db_3 !== '' && preg_match('/^(?:[A-Z]{2}-)?(\d{4,5})\s+(.+)$/', $db_3, $m)) {
        $db_plz = $m[1];
        $db_ort = $m[2];
    } else {
        $db_ort = $db_3;
    }

    $additional_info = trim((string)($tpl['document']['subject'] ?? ''));
    if ($additional_info === '') {
        $additional_info = trim((string)($tpl['document']['description'] ?? ''));
    }
    $additional_info = trim(preg_replace('/\s+/', ' ', $additional_info));
    if ($additional_info !== '') {
        $additional_info = mb_substr($additional_info, 0, 140);
    }

    /** ----------------------------------------------------------------
     * 3) EMV-QR-Payload bauen
     * ---------------------------------------------------------------- */
    $ref_type = $ref_mode;
    $ref_value = ($ref_mode === 'NON') ? '' : $ref_value;

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
        '',                     // Ult. Creditor Address Type
        '',                     // Ult. Creditor Name
        '',                     // Ult. Creditor Street
        '',                     // Ult. Creditor Building No
        '',                     // Ult. Creditor Postal Code
        '',                     // Ult. Creditor Town
        '',                     // Ult. Creditor Country
        $betrag_emv,            // Betrag
        $w,
        'S',                    // Debtor Address Type
        $db_1,
        $db_2,
        '',
        $db_plz,
        $db_ort,
        $db_country,
        $ref_type,
        $ref_value,
        $additional_info,
        'EPD',
        '',
        ''
    ];
    $qr_raw = implode("\n", $qr_data);

		/** ----------------------------------------------------------------
		 * 4) QR-Bild generieren (grösser + Schweizer Kreuz für Endroid v4/v5)
		 * ---------------------------------------------------------------- */
		try {

				// QR-Code 46 mm gross (Schweizer Norm)
				$qr_size_mm = 46 * $mm;

				$qr = QrCode::create($qr_raw)
						->setSize((int) $qr_size_mm)
						->setMargin(0);

				// Schweizer Kreuz laden (PNG-Datei MUSS existieren)
				$logoPath = (defined('CMX_PLUGIN_DIR') ? CMX_PLUGIN_DIR : \plugin_dir_path(__FILE__)) . 'assets/swiss-cross.png';

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
    $qr_x         = $zahlteil_x + 5 * $mm;
    // $qr_x         = $zahlteil_x * $mm;               // etwas eingerückt
    $qr_y         = $zone_top + 20 * $mm;
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

    if ($ref_mode !== 'NON' && $ref_print !== '') {
        $y += 4 * $mm;
        $canvas->text($x, $y, 'Referenz', $fontBold, 8.5, [0, 0, 0], $page);
        $y += 4 * $mm;
        $canvas->text($x, $y, $ref_print, $font, 8.5, [0, 0, 0], $page);
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

    if ($ref_mode !== 'NON' && $ref_print !== '') {
        $acc_y += 4 * $mm;
        $canvas->text($acc_x, $acc_y, 'Referenz', $fontBold, 8.5, [0, 0, 0], $page);
        $acc_y += 4 * $mm;
        $canvas->text($acc_x, $acc_y, $ref_print, $font, 8.5, [0, 0, 0], $page);
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
