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
 * Strasse/Hausnummer aus einer Zeile extrahieren (für QR-Adressstruktur "S").
 *
 * @return array{0:string,1:string} [street, house_no]
 */
function cmx_qr_split_street_house(string $line): array
{
    $line = trim((string) preg_replace('/\s+/', ' ', $line));
    if ($line === '') {
        return ['', ''];
    }

    // "Musterstrasse 12a"
    if (preg_match('/^(.+?)\s+(\d+[A-Za-z0-9\/\-]*)$/u', $line, $m)) {
        return [trim($m[1]), trim($m[2])];
    }
    // "12a Musterstrasse"
    if (preg_match('/^(\d+[A-Za-z0-9\/\-]*)\s+(.+)$/u', $line, $m)) {
        return [trim($m[2]), trim($m[1])];
    }

    return [$line, ''];
}

/**
 * Mod10-rekursiv Prüfziffer für eine 26-stellige QRR-Basis berechnen.
 */
function cmx_qr_mod10_recursive_check_digit(string $base26): string
{
    $base26 = preg_replace('~\D+~', '', $base26);
    if (strlen($base26) !== 26 || !ctype_digit($base26)) {
        return '';
    }

    $table = [0,9,4,6,8,2,7,1,3,5];
    $c     = 0;

    for ($i = 0; $i < 26; $i++) {
        $c = $table[($c + (int) $base26[$i]) % 10];
    }

    return (string) ((10 - $c) % 10);
}

/**
 * Gültige QRR aus Rechnungsnummer bauen.
 *
 * Betriebsregel:
 * - Erste 2 Stellen sind fix "65"
 * - Danach entweder:
 *   a) 6-stellige Debitor-ID + 18-stellige Rechnungsnummer (wenn passend)
 *   b) 24-stellige Rechnungsnummer (Fallback)
 * - Dann Mod10-rekursiv Prüfziffer (27. Stelle)
 */
function cmx_qr_build_qrr_reference(string $invoice_number, int $debitor_id = 0): string
{
    $invoice_digits = preg_replace('~\D+~', '', $invoice_number);
    if ($invoice_digits === '') {
        return '';
    }

    $debitor_digits = preg_replace('~\D+~', '', (string) $debitor_id);

    if ($debitor_digits !== '' && strlen($invoice_digits) <= 18) {
        $body24 = str_pad(substr($debitor_digits, -6), 6, '0', STR_PAD_LEFT)
            . str_pad($invoice_digits, 18, '0', STR_PAD_LEFT);
    } else {
        // QRR erlaubt exakt 26 Stellen Basis + 1 Prüfziffer.
        if (strlen($invoice_digits) > 24) {
            return '';
        }
        $body24 = str_pad($invoice_digits, 24, '0', STR_PAD_LEFT);
    }

    $base26 = '65' . $body24;
    $check_digit = cmx_qr_mod10_recursive_check_digit($base26);
    if ($check_digit === '') {
        return '';
    }

    $qrr = $base26 . $check_digit;
    return cmx_qr_is_valid_qrr($qrr) ? $qrr : '';
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
     * 1) Einstellungen & Referenz
     * ---------------------------------------------------------------- */
    $qr_enabled = !empty($tpl['qr']['enabled']);
    $qr_iban    = trim((string)($tpl['qr']['iban'] ?? $tpl['bank']['qr_iban'] ?? ''));
    $doc_type   = strtolower(trim((string)($tpl['document']['type'] ?? '')));
    $richtung   = strtolower(trim((string)($tpl['document']['richtung'] ?? '')));

    if (!$qr_enabled || $qr_iban === '' || $doc_type !== 'rechnung' || $richtung !== 'ausgang') {
        return;
    }

    $invoice_number = trim((string)($tpl['document']['number'] ?? ''));
    $debitor_id = (int) \get_post_meta($post_id, '_cmx_beleg_kontakt_id', true);
    $stored_qrr = preg_replace('~\D+~', '', (string) \get_post_meta($post_id, '_cmx_beleg_qrr', true));
    $expected_qrr = cmx_qr_build_qrr_reference($invoice_number, $debitor_id);

    // Nur verwenden, wenn gespeicherte Referenz exakt zu den aktuellen Belegdaten passt.
    if ($expected_qrr !== '' && $stored_qrr === $expected_qrr) {
        $qrr_reference = $stored_qrr;
    } else {
        $qrr_reference = $expected_qrr;
        if ($qrr_reference !== '') {
            \update_post_meta($post_id, '_cmx_beleg_qrr', $qrr_reference);
        } else {
            \delete_post_meta($post_id, '_cmx_beleg_qrr');
        }
    }

    $ref_mode = ($qrr_reference !== '') ? 'QRR' : 'NON';
    $ref_value = $qrr_reference;
    $ref_print = ($qrr_reference !== '') ? cmx_qr_format_qrr_print($qrr_reference) : '';

    /** ----------------------------------------------------------------
     * 2) Beträge & Adressen
     * ---------------------------------------------------------------- */
    $bank_iban = trim((string) ($tpl['bank']['iban'] ?? ''));
    $iban = ($ref_mode === 'QRR') ? $qr_iban : $bank_iban;
    if ($iban === '') {
        $iban = $qr_iban;
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
    [$cr_street, $cr_house_no] = cmx_qr_split_street_house($cr_str);

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
    [$db_street, $db_house_no] = cmx_qr_split_street_house($db_2);

    $additional_info = trim((string)($tpl['document']['subject'] ?? ''));
    if ($additional_info === '') {
        // Nur im QR-Feld auf den Belegnamen fallen, wenn der Betreff leer ist.
        $additional_info = trim((string)($tpl['document']['title'] ?? ''));
    }
    if ($additional_info === '') {
        $additional_info = trim((string)($tpl['document']['description'] ?? ''));
    }
    $additional_info = trim((string) preg_replace('/\s+/', ' ', $additional_info));
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
        $cr_street,
        $cr_house_no,
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
        $db_street,
        $db_house_no,
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
		 * 4) QR-Bild generieren
		 * ---------------------------------------------------------------- */
		try {

				// Hohe Pixelauflösung für robustes Scannen; physische Grösse bleibt
				// unten beim Platzieren exakt 46 x 46 mm.
				$qr_size_px = 550;

				$qr = QrCode::create($qr_raw)
						->setSize((int) $qr_size_px)
						->setMargin(0);

				$writer   = new PngWriter();
				$qrResult = $writer->write($qr);

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

    $receipt_width = 62 * $mm;               // Empfangsscheinbreite
    $payment_width = 148 * $mm;              // Zahlteilbreite
    $cut_x         = $receipt_width;         // Schnittlinie exakt bei 62 mm
    $zahlteil_x    = $cut_x;                 // Start des Zahlteils

    // Nutzbare Breite im Empfangsschein leicht erhöhen (innerer Rand etwas kleiner)
    $receipt_text_x = 4 * $mm;
    $payment_text_x = $zahlteil_x + 5 * $mm;

    $qr_size      = 46 * $mm;
    $qr_x         = $payment_text_x;
    $qr_y         = $zone_top + 18 * $mm;    // Abstand unter dem Titel

    // Typografie nahe SIX-Referenzlayout (6-10 pt)
    $title_size = 10.0;
    $label_size = 6.8;
    $body_size  = 6.8;
    $small_size = 6.0;

    // Währung/Betrag links und rechts auf gleicher Höhe (1 Zeile tiefer)
    $amount_row_y = $zone_top + 68.6 * $mm;

    /** ----------------------------------------------------------------
     * 6) Trennlinie + Schere
     * ---------------------------------------------------------------- */
    // SIX-konforme horizontale Trennlinie oberhalb des QR-Blocks
    $page_width = $canvas->get_width();
    $h_cut_y = $zone_top;
    $canvas->line(
        0,
        $h_cut_y,
        $page_width,
        $h_cut_y,
        [0, 0, 0],
        1
    );

    $canvas->text(
        4 * $mm,
        $h_cut_y - 1.8 * $mm,
        '✂',
        $font,
        10,
        [0, 0, 0]
    );

    // Vertikale Trennlinie zwischen Empfangsschein und Zahlteil
    $canvas->line(
        $cut_x,
        $zone_top,
        $cut_x,
        $zone_top + $zone_height,
        [0, 0, 0],
        1
    );

    // Schere an der vertikalen Linie um 90° gedreht
    $canvas->text(
        $cut_x - (2.2 * $mm),
        $zone_top + 8 * $mm,
        '✂',
        $font,
        10,
        [0, 0, 0],
        0.0,
        0.0,
        90
    );

    /** ----------------------------------------------------------------
     * 7) EMPFANGSSCHEIN (linke Seite)
     * ---------------------------------------------------------------- */
    $x = $receipt_text_x;
    $y = $zone_top + 7 * $mm;

    $canvas->text($x, $y, 'Empfangsschein', $fontBold, $title_size, [0, 0, 0], $page);
    $y += 5.8 * $mm;

    $canvas->text($x, $y, 'Konto / Zahlbar an', $fontBold, $label_size, [0, 0, 0], $page);
    $y += 2.9 * $mm;

    foreach ([$iban, $cr_name, $cr_str, $cr_zip] as $line) {
        if ($line !== '') {
            $canvas->text($x, $y, $line, $font, $body_size, [0, 0, 0], $page);
            $y += 2.8 * $mm;
        }
    }

    if ($ref_mode !== 'NON' && $ref_print !== '') {
        $y += 3.1 * $mm;
        $canvas->text($x, $y, 'Referenz', $fontBold, $label_size, [0, 0, 0], $page);
        $y += 2.9 * $mm;
        // Referenz innerhalb der Empfangsschein-Breite halten.
        $ref_font_size = $body_size;
        $ref_max_width = ($cut_x - 2 * $mm) - $x;
        if ($ref_max_width > 0 && method_exists($fontMetrics, 'getTextWidth')) {
            while ($ref_font_size > 6.0) {
                $ref_width = $fontMetrics->getTextWidth($ref_print, $font, $ref_font_size);
                if ($ref_width <= $ref_max_width) {
                    break;
                }
                $ref_font_size -= 0.25;
            }
        }
        $canvas->text($x, $y, $ref_print, $font, $ref_font_size, [0, 0, 0], $page);
    }

    // Zusätzliche Leerzeile vor "Zahlbar durch"
    $y += 7.1 * $mm;
    $canvas->text($x, $y, 'Zahlbar durch', $fontBold, $label_size, [0, 0, 0], $page);
    $y += 2.9 * $mm;

    foreach ([$db_1, $db_2, $db_3] as $line) {
        if ($line !== '') {
            $canvas->text($x, $y, $line, $font, $body_size, [0, 0, 0], $page);
            $y += 2.8 * $mm;
        }
    }

    // Betrag links unten
    $yb = $amount_row_y;
    $canvas->text($x, $yb, 'Währung', $fontBold, $label_size, [0, 0, 0], $page);
    $canvas->text($x + 24 * $mm, $yb, 'Betrag', $fontBold, $label_size, [0, 0, 0], $page);

    $yb += 3.4 * $mm;
    $canvas->text($x, $yb, $w, $font, $body_size, [0, 0, 0], $page);
    $canvas->text($x + 24 * $mm, $yb, $betrag_print, $font, $body_size, [0, 0, 0], $page);

    $canvas->text(
        $x + 38 * $mm,
        $zone_top + 79.2 * $mm, // 2 Zeilen nach oben
        'Annahmestelle',
        $font,
        $small_size,
        [0, 0, 0],
        $page
    );

    /** ----------------------------------------------------------------
     * 8) ZAHLTEIL (rechte Seite)
     * ---------------------------------------------------------------- */
    // Titel links im Zahlteil
    $x = $payment_text_x;
    $y = $zone_top + 7 * $mm;

    $canvas->text($x, $y, 'Zahlteil', $fontBold, $title_size, [0, 0, 0], $page);

    // Konto-Block rechts neben dem QR-Code
    $acc_x = $qr_x + $qr_size + 5 * $mm;
    $acc_y = $zone_top + 7 * $mm;

    $canvas->text($acc_x, $acc_y, 'Konto / Zahlbar an', $fontBold, $label_size, [0, 0, 0], $page);
    $acc_y += 2.9 * $mm;

    foreach ([$iban, $cr_name, $cr_str, $cr_zip] as $line) {
        if ($line !== '') {
            $canvas->text($acc_x, $acc_y, $line, $font, $body_size, [0, 0, 0], $page);
            $acc_y += 2.8 * $mm;
        }
    }

    if ($ref_mode !== 'NON' && $ref_print !== '') {
        $acc_y += 3.1 * $mm;
        $canvas->text($acc_x, $acc_y, 'Referenz', $fontBold, $label_size, [0, 0, 0], $page);
        $acc_y += 2.9 * $mm;
        $canvas->text($acc_x, $acc_y, $ref_print, $font, $body_size, [0, 0, 0], $page);
    }

    // OPTIONAL: Zusätzliche Infos (Betreff, sonst Belegname)
    if ($additional_info !== '') {
        // Zusätzliche Leerzeile vor "Zusätzliche Informationen"
        $acc_y += 7.1 * $mm;
        $canvas->text($acc_x, $acc_y, 'Zusätzliche Informationen', $fontBold, $label_size, [0, 0, 0], $page);
        $acc_y += 2.9 * $mm;
        $canvas->text($acc_x, $acc_y, $additional_info, $font, $body_size, [0, 0, 0], $page);
    }

    // Zahler rechts unter der Referenz (wie SIX-Muster)
    $pay_x = $acc_x;
    // Zusätzliche Leerzeile vor "Zahlbar durch"
    $pay_y = $acc_y + 7.1 * $mm;

    $canvas->text($pay_x, $pay_y, 'Zahlbar durch', $fontBold, $label_size, [0, 0, 0], $page);
    $pay_y += 2.9 * $mm;

    foreach ([$db_1, $db_2, $db_3] as $line) {
        if ($line !== '') {
            $canvas->text($pay_x, $pay_y, $line, $font, $body_size, [0, 0, 0], $page);
            $pay_y += 2.8 * $mm;
        }
    }

    // Währung / Betrag beim QR
    $wy = $amount_row_y;
    $canvas->text($x, $wy, 'Währung', $fontBold, $label_size, [0, 0, 0], $page);
    $canvas->text($x + 24 * $mm, $wy, 'Betrag', $fontBold, $label_size, [0, 0, 0], $page);

    $wy += 3.4 * $mm;
    $canvas->text($x, $wy, $w, $font, $body_size, [0, 0, 0], $page);
    $canvas->text($x + 24 * $mm, $wy, $betrag_print, $font, $body_size, [0, 0, 0], $page);

    /** ----------------------------------------------------------------
     * 9) QR-Code platzieren
     * ---------------------------------------------------------------- */
    $canvas->image($tmp, $qr_x, $qr_y, $qr_size, $qr_size, $page);

    // Schweizer Kreuz (schwarz/weiss) in der Mitte des QR-Codes
    // mit kleinem weissen Rand (Quiet Zone) um das Logo.
    $cross_size = 7 * $mm;
    $cross_border = 0.6 * $mm;
    $cross_x = $qr_x + ($qr_size - $cross_size) / 2;
    $cross_y = $qr_y + ($qr_size - $cross_size) / 2;

    $prev_page = $canvas->get_page_number();
    $canvas->set_page_number($page);
    $canvas->filled_rectangle(
        $cross_x - $cross_border,
        $cross_y - $cross_border,
        $cross_size + (2 * $cross_border),
        $cross_size + (2 * $cross_border),
        [1, 1, 1]
    );
    $canvas->filled_rectangle($cross_x, $cross_y, $cross_size, $cross_size, [0, 0, 0]);

    $bar = $cross_size * 0.2;
    $arm = $cross_size * 0.7;
    $canvas->filled_rectangle($cross_x + ($cross_size - $bar) / 2, $cross_y + ($cross_size - $arm) / 2, $bar, $arm, [1, 1, 1]);
    $canvas->filled_rectangle($cross_x + ($cross_size - $arm) / 2, $cross_y + ($cross_size - $bar) / 2, $arm, $bar, [1, 1, 1]);
    $canvas->set_page_number($prev_page);

    // Temporäre Datei löschen
    @\unlink($tmp);
}
