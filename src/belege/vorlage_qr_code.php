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
 * Einheitliche Zeilen-Normalisierung fuer QR-Felder.
 */
function cmx_qr_normalize_line(string $line): string
{
    $line = (string) \preg_replace('/\s+/u', ' ', $line);
    return \trim($line);
}

/**
 * Vergleichsschluessel (ohne Akzente, lowercase) fuer Textvergleiche.
 */
function cmx_qr_text_key(string $value): string
{
    $value = cmx_qr_normalize_line($value);
    if (\function_exists('remove_accents')) {
        $value = (string) \remove_accents($value);
    }
    return \strtolower($value);
}

/**
 * IBAN fuer den QR-Payload normalisieren (ohne Leerzeichen/Sonderzeichen).
 */
function cmx_qr_normalize_iban(string $iban): string
{
    $iban = (string) \preg_replace('/[^A-Za-z0-9]/', '', $iban);
    return \strtoupper($iban);
}

/**
 * Land (Slug/Name/Code) in ISO2-Code umwandeln.
 */
function cmx_qr_country_to_iso2(string $country, string $fallback = 'CH'): string
{
    $fallback = \strtoupper(\trim($fallback));
    if (!\preg_match('/^[A-Z]{2}$/', $fallback)) {
        $fallback = 'CH';
    }

    $country = cmx_qr_normalize_line($country);
    if ($country === '') {
        return $fallback;
    }

    if (\preg_match('/^[A-Za-z]{2}$/', $country)) {
        return \strtoupper($country);
    }

    $map = [
        'schweiz'            => 'CH',
        'switzerland'        => 'CH',
        'suisse'             => 'CH',
        'svizzera'           => 'CH',
        'liechtenstein'      => 'LI',
        'deutschland'        => 'DE',
        'germany'            => 'DE',
        'oesterreich'        => 'AT',
        'osterreich'         => 'AT',
        'austria'            => 'AT',
        'frankreich'         => 'FR',
        'france'             => 'FR',
        'italien'            => 'IT',
        'italy'              => 'IT',
        'amerika'            => 'US',
        'usa'                => 'US',
        'united states'      => 'US',
        'vereinigte staaten' => 'US',
    ];

    $key = cmx_qr_text_key($country);
    return $map[$key] ?? $fallback;
}

/**
 * Zeilen wie "CH-8049 Zuerich" oder "8049 Zuerich" lesen.
 *
 * @return array{0:string,1:string,2:string} [plz, city, country_iso2]
 */
function cmx_qr_parse_plz_city_line(string $line): array
{
    $line = cmx_qr_normalize_line($line);
    if ($line === '') {
        return ['', '', ''];
    }

    if (\preg_match('/^([A-Za-z]{2})\s*-\s*(\d{4,5})\s+(.+)$/u', $line, $m)) {
        return [\trim($m[2]), cmx_qr_normalize_line($m[3]), \strtoupper($m[1])];
    }
    if (\preg_match('/^([A-Za-z]{2})\s+(\d{4,5})\s+(.+)$/u', $line, $m)) {
        return [\trim($m[2]), cmx_qr_normalize_line($m[3]), \strtoupper($m[1])];
    }
    if (\preg_match('/^(\d{4,5})\s+(.+)$/u', $line, $m)) {
        return [\trim($m[1]), cmx_qr_normalize_line($m[2]), ''];
    }

    return ['', '', ''];
}

/**
 * Debitor fuer QR-Payload strikt strukturiert (SIX, Typ S) aufbauen.
 *
 * @return array{
 *   name:string,
 *   street:string,
 *   house_no:string,
 *   plz:string,
 *   ort:string,
 *   country:string,
 *   display_lines:array<int,string>
 * }
 */
function cmx_qr_build_debtor_structured_address(int $post_id, int $debitor_id): array
{
    $name     = '';
    $name2    = '';
    $street   = '';
    $house_no = '';
    $plz      = '';
    $ort      = '';
    $country  = '';

    if ($debitor_id > 0) {
        $company = (string) (\get_post_meta($debitor_id, '_company', true) ?: \get_the_title($debitor_id));
        $company = cmx_qr_normalize_line($company);
        if (cmx_qr_text_key($company) === 'firmenname fehlt') {
            $company = '';
        }

        $vorname = cmx_qr_normalize_line((string) \get_post_meta($debitor_id, '_cmx_kontakte_vorname', true));
        $nachname = cmx_qr_normalize_line((string) \get_post_meta($debitor_id, '_cmx_kontakte_nachname', true));
        $full_name = cmx_qr_normalize_line($vorname . ' ' . $nachname);

        if ($company !== '') {
            $name = $company;
            if ($full_name !== '' && cmx_qr_text_key($full_name) !== cmx_qr_text_key($company)) {
                $name2 = $full_name;
            }
        } elseif ($full_name !== '') {
            $name = $full_name;
        }

        $street_line = cmx_qr_normalize_line((string) \get_post_meta($debitor_id, '_cmx_rechnung_strasse', true));
        [$street, $house_no] = cmx_qr_split_street_house($street_line);
        $plz = cmx_qr_normalize_line((string) \get_post_meta($debitor_id, '_cmx_rechnung_plz', true));
        $ort = cmx_qr_normalize_line((string) \get_post_meta($debitor_id, '_cmx_rechnung_ort', true));

        $country_meta = cmx_qr_normalize_line((string) \get_post_meta($debitor_id, '_cmx_rechnung_land', true));
        if ($country_meta !== '') {
            $country = cmx_qr_country_to_iso2($country_meta, 'CH');
        }
    }

    $deb_raw = (string) \get_post_meta($post_id, '_cmx_beleg_kontakt_addr', true);
    $raw_lines = \preg_split('~[\r\n]+~', $deb_raw);
    $lines = [];
    if (\is_array($raw_lines)) {
        foreach ($raw_lines as $raw_line) {
            $line = cmx_qr_normalize_line((string) $raw_line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }
    }

    if ($name === '' && isset($lines[0])) {
        $name = $lines[0];
    }

    $zip_city_index = -1;
    $zip_from_line = '';
    $city_from_line = '';
    $country_from_zip = '';
    for ($i = \count($lines) - 1; $i >= 0; $i--) {
        [$zip_tmp, $city_tmp, $country_tmp] = cmx_qr_parse_plz_city_line($lines[$i]);
        if ($zip_tmp !== '' || $city_tmp !== '') {
            $zip_city_index = $i;
            $zip_from_line = $zip_tmp;
            $city_from_line = $city_tmp;
            $country_from_zip = $country_tmp;
            break;
        }
    }

    if ($plz === '' && $zip_from_line !== '') {
        $plz = $zip_from_line;
    }
    if ($ort === '' && $city_from_line !== '') {
        $ort = $city_from_line;
    }
    if ($country === '' && $country_from_zip !== '') {
        $country = $country_from_zip;
    }

    $country_from_line = '';
    for ($i = \count($lines) - 1; $i >= 0; $i--) {
        if (\preg_match('/^[A-Za-z]{2}$/', $lines[$i])) {
            $country_from_line = \strtoupper($lines[$i]);
            break;
        }
    }
    if ($country === '' && $country_from_line !== '') {
        $country = $country_from_line;
    }

    $non_zip_lines = [];
    for ($i = 1; $i < \count($lines); $i++) {
        if ($i === $zip_city_index) {
            continue;
        }
        if (\preg_match('/^[A-Za-z]{2}$/', $lines[$i])) {
            continue;
        }
        $non_zip_lines[] = $lines[$i];
    }

    if ($street === '' && !empty($non_zip_lines)) {
        $street_candidate = $non_zip_lines[\count($non_zip_lines) - 1];
        [$street, $house_no] = cmx_qr_split_street_house($street_candidate);
    }

    if ($name2 === '' && \count($non_zip_lines) >= 2) {
        $line2_candidate = $non_zip_lines[0];
        $street_key = cmx_qr_text_key(\trim($street . ' ' . $house_no));
        if ($line2_candidate !== '' && cmx_qr_text_key($line2_candidate) !== cmx_qr_text_key($name) && cmx_qr_text_key($line2_candidate) !== $street_key) {
            $name2 = $line2_candidate;
        }
    }

    if ($country === '') {
        $country = 'CH';
    } else {
        $country = cmx_qr_country_to_iso2($country, 'CH');
    }

    $street_line = \trim($street . ($house_no !== '' ? ' ' . $house_no : ''));
    $city_line = \trim($plz . ' ' . $ort);
    if ($city_line !== '' && $country !== '') {
        $city_line = $country . '-' . $city_line;
    } elseif ($city_line === '' && $country !== '') {
        $city_line = $country;
    }

    $display_lines = [];
    foreach ([$name, $name2, $street_line, $city_line] as $line) {
        $line = cmx_qr_normalize_line($line);
        if ($line !== '') {
            $display_lines[] = $line;
        }
    }
    if (empty($display_lines)) {
        $display_lines = $lines;
    }

    if ($name === '' && !empty($display_lines)) {
        $name = $display_lines[0];
    }

    return [
        'name' => cmx_qr_normalize_line($name),
        'street' => cmx_qr_normalize_line($street),
        'house_no' => cmx_qr_normalize_line($house_no),
        'plz' => cmx_qr_normalize_line($plz),
        'ort' => cmx_qr_normalize_line($ort),
        'country' => $country,
        'display_lines' => $display_lines,
    ];
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
    $bank_iban  = trim((string)($tpl['bank']['iban'] ?? ''));
    $doc_type   = strtolower(trim((string)($tpl['document']['type'] ?? '')));
    $richtung   = strtolower(trim((string)($tpl['document']['richtung'] ?? '')));
    if ($richtung === '') {
        $richtung = 'ausgang';
    }

    if (!$qr_enabled || $doc_type !== 'rechnung' || $richtung !== 'ausgang') {
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
    $qr_iban_norm = cmx_qr_normalize_iban($qr_iban);
    $bank_iban_norm = cmx_qr_normalize_iban($bank_iban);

    // QRR braucht zwingend eine QR-IBAN. Wenn diese fehlt, auf NON degradieren.
    if ($ref_mode === 'QRR' && $qr_iban_norm === '') {
        $ref_mode = 'NON';
        $ref_value = '';
        $ref_print = '';
    }

    if ($ref_mode === 'QRR') {
        $iban = $qr_iban_norm;
        $iban_print = $qr_iban !== '' ? $qr_iban : $qr_iban_norm;
    } else {
        $iban = ($bank_iban_norm !== '') ? $bank_iban_norm : $qr_iban_norm;
        $iban_print = $bank_iban !== '' ? $bank_iban : (($qr_iban !== '') ? $qr_iban : $iban);
    }

    if ($iban === '') {
        // Ohne IBAN kein QR-Code auf dem Beleg
        return;
    }
    $iban_print = trim((string) $iban_print);

    $amount = (float) ($tpl['document']['total'] ?? 0);
    // EMV braucht Punkt, KEINE Tausendertrennzeichen
    $betrag_emv = number_format($amount, 2, '.', '');
    // Für Druck etwas hübscher (Leerzeichen als Tausender)
    $betrag_print = number_format($amount, 2, '.', ' ');

    $w = $tpl['document']['currency'] ?: 'CHF';

    // Creditor (du)
    $cr_name = cmx_qr_normalize_line((string) ($tpl['me']['company'] ?? ''));
    $cr_str  = cmx_qr_normalize_line((string) ($tpl['me']['strasse'] ?? ''));
    $cr_plz  = cmx_qr_normalize_line((string) ($tpl['me']['plz'] ?? ''));
    $cr_ort  = cmx_qr_normalize_line((string) ($tpl['me']['ort'] ?? ''));
    $cr_country = cmx_qr_country_to_iso2((string) ($tpl['me']['land_code'] ?? $tpl['me']['land'] ?? ''), 'CH');
    $cr_zip  = trim($cr_plz . ' ' . $cr_ort);
    [$cr_street, $cr_house_no] = cmx_qr_split_street_house($cr_str);

    // Debitor-Adresse strikt strukturiert fuer SIX-Payload.
    $debtor = cmx_qr_build_debtor_structured_address($post_id, $debitor_id);
    $db_name = $debtor['name'];
    $db_street = $debtor['street'];
    $db_house_no = $debtor['house_no'];
    $db_plz = $debtor['plz'];
    $db_ort = $debtor['ort'];
    $db_country = $debtor['country'];
    $db_display_lines = $debtor['display_lines'];

    // Zusätzliche Informationen:
    // - Erste Zeile immer mit Belegtitel
    // - Betreff (falls vorhanden) in die nächste Zeile
    $beleg_line = cmx_qr_normalize_line((string) ($tpl['document']['title'] ?? ''));
    $subject_line = cmx_qr_normalize_line((string) ($tpl['document']['subject'] ?? ''));
    $description_line = cmx_qr_normalize_line((string) ($tpl['document']['description'] ?? ''));

    $additional_info_lines = [];
    if ($beleg_line !== '') {
        $additional_info_lines[] = $beleg_line;
    }
    if ($subject_line !== '') {
        $is_duplicate_subject = ($beleg_line !== '' && cmx_qr_text_key($subject_line) === cmx_qr_text_key($beleg_line));
        if (!$is_duplicate_subject) {
            $additional_info_lines[] = $subject_line;
        }
    } elseif ($description_line !== '' && $beleg_line === '') {
        // Fallback nur, wenn weder Belegtitel noch Betreff vorhanden sind.
        $additional_info_lines[] = $description_line;
    }

    // SIX-Payload bleibt einzeilig; fuer den Druck behalten wir die einzelnen Zeilen.
    $additional_info = trim((string) preg_replace('/\s+/', ' ', implode(' | ', $additional_info_lines)));
    if ($additional_info !== '') {
        $additional_info = \function_exists('mb_substr')
            ? mb_substr($additional_info, 0, 140)
            : substr($additional_info, 0, 140);
    }
    $additional_info_print_lines = [];
    if (!empty($additional_info_lines)) {
        foreach ($additional_info_lines as $line) {
            $line = cmx_qr_normalize_line((string) $line);
            if ($line !== '') {
                $additional_info_print_lines[] = $line;
            }
        }
        if (\count($additional_info_print_lines) > 2) {
            $additional_info_print_lines = \array_slice($additional_info_print_lines, 0, 2);
        }
    } elseif ($additional_info !== '') {
        $additional_info_print_lines[] = $additional_info;
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
        'S',                    // Strukturierte Adresse
        $cr_name,
        $cr_street,
        $cr_house_no,
        $cr_plz,
        $cr_ort,
        $cr_country,
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
        $db_name,
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

				$tmp = '';
				if (\function_exists('\\wp_tempnam')) {
						$tmp = (string) \wp_tempnam('cmx_qr');
				}
				if ($tmp === '') {
						$tmp = (string) \tempnam(\sys_get_temp_dir(), 'cmx_qr_');
				}
				if ($tmp === '' || $tmp === false) {
						throw new \RuntimeException('Temp-Datei für QR konnte nicht erstellt werden.');
				}

				$written = @\file_put_contents($tmp, $qrResult->getString());
				if ($written === false) {
						throw new \RuntimeException('QR-Bild konnte nicht in Temp-Datei geschrieben werden.');
				}

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

    foreach ([$iban_print, $cr_name, $cr_str, $cr_zip] as $line) {
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

    foreach ($db_display_lines as $line) {
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

    foreach ([$iban_print, $cr_name, $cr_str, $cr_zip] as $line) {
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

    // Zusätzliche Infos (erste Zeile: Beleg, zweite Zeile: Betreff)
    if (!empty($additional_info_print_lines)) {
        // Zusätzliche Leerzeile vor "Zusätzliche Informationen"
        $acc_y += 7.1 * $mm;
        $canvas->text($acc_x, $acc_y, 'Zusätzliche Informationen', $fontBold, $label_size, [0, 0, 0], $page);
        $acc_y += 2.9 * $mm;
        foreach ($additional_info_print_lines as $info_line) {
            $canvas->text($acc_x, $acc_y, $info_line, $font, $body_size, [0, 0, 0], $page);
            $acc_y += 2.8 * $mm;
        }
    }

    // Zahler rechts unter der Referenz (wie SIX-Muster)
    $pay_x = $acc_x;
    // Zusätzliche Leerzeile vor "Zahlbar durch"
    $pay_y = $acc_y + 7.1 * $mm;

    $canvas->text($pay_x, $pay_y, 'Zahlbar durch', $fontBold, $label_size, [0, 0, 0], $page);
    $pay_y += 2.9 * $mm;

    foreach ($db_display_lines as $line) {
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
