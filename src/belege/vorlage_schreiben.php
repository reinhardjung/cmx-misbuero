<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

/**
 * Erweiterte PDF-Speicherfunktionen mit Logging.
 *
 * Features:
 * - Löscht alte Belege mit gleicher Belegnummer (Post-Title) im Dateinamen
 * - Präfixt bei bezahlten Belegen den Dateinamen mit Zahlungsdatum "YYYY-MM-DD_"
 * - Ausführliches Logging über error_log
 *
 * Empfohlener Aufruf:
 * cmxbu_save_beleg_pdf_paidaware($pdf_path, $pdf_binary, $post_id, get_the_title($post_id));
 */

/* =====================================================
 * 0) (Optional) Composer Autoload nur einmal pro Request loggen
 * ===================================================== */
$__cmx_vendor = trailingslashit(defined('CMX_PLUGIN_DIR') ? CMX_PLUGIN_DIR : plugin_dir_path(__FILE__)) . 'vendor/autoload.php';
if (is_file($__cmx_vendor)) {
	require_once $__cmx_vendor;
	static $cmx_pdf_autoload_logged = false;
	if (!$cmx_pdf_autoload_logged) {
		// error_log('[CMX PDF] Composer autoload geladen.');
		$cmx_pdf_autoload_logged = true;
	}
}

/* =====================================================
 * 1) Basisspeicher: löscht alte Dateien + speichert PDF
 * ===================================================== */
if (!function_exists(__NAMESPACE__ . '\\cmxbu_save_beleg_pdf')) {
	function cmxbu_save_beleg_pdf(string $pdf_path, string $pdf_binary, string $beleg_ref = ''): bool {
		$dir = \dirname($pdf_path);

		// Zielverzeichnis sicherstellen
		if (!is_dir($dir)) {
			if (!\wp_mkdir_p($dir)) {
				error_log('[CMX] Verzeichnis konnte nicht erstellt werden: ' . $dir);
				return false;
			}
		}

		// Beleg-Referenz (Post-Title) ermitteln
		if ($beleg_ref === '' || $beleg_ref === null) {
			$base = \pathinfo($pdf_path, PATHINFO_FILENAME); // z. B. "RE-2025-001_rechnung"
			$beleg_ref = (\strpos($base, '_') !== false) ? \substr($base, 0, \strpos($base, '_')) : $base;
		}

		$needle = \sanitize_title($beleg_ref);

		// Sicherheitsnetz: wenn Referenz leer/zu kurz, keine Löschrunde
		if ($needle === '' || \strlen($needle) < 2) {
			error_log('[CMX] Beleg-Referenz zu kurz/leer – Datei wird direkt gespeichert.');
			return (@\file_put_contents($pdf_path, $pdf_binary) !== false);
		}

		// Alte Dateien mit gleicher Beleg-Nr. löschen (nur im Zielordner)
		$patterns = [
			$dir . '/*' . $needle . '*.pdf',
			$dir . '/*' . $needle . '*.PDF',
			$dir . '/*' . $needle . '*.html',
			$dir . '/*' . $needle . '*.htm',
		];

		foreach ($patterns as $pattern) {
			foreach ((array)\glob($pattern) as $old) {
				if (\is_file($old) && \dirname($old) === $dir && $old !== $pdf_path) {
					if (\strpos(\basename($old), '_upload_') !== false) {
						continue;
					}
					@\unlink($old);
					error_log('[CMX] Alte Datei gelöscht: ' . $old);
				}
			}
		}

		$ok = (@\file_put_contents($pdf_path, $pdf_binary) !== false);
		error_log('[CMX] PDF gespeichert: ' . $pdf_path);

		return $ok;
	}
}

/* =====================================================
 * 2) Datum normalisieren → "YYYY-MM-DD" (oder '' wenn ungültig)
 * ===================================================== */
if (!function_exists(__NAMESPACE__ . '\\cmxbu_norm_ymd_date')) {
	function cmxbu_norm_ymd_date($val): string {
		$val = is_string($val) ? trim($val) : (string)$val;
		if ($val === '') return '';

		// ISO: YYYY-MM-DD
		if (\preg_match('~^\d{4}-\d{2}-\d{2}$~', $val)) return $val;

		// CH: DD.MM.YYYY
		if (\preg_match('~^\d{1,2}\.\d{1,2}\.\d{4}$~', $val)) {
			[$d, $m, $y] = \preg_split('~[.]~', $val);
			return \sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
		}

		// Unix-Timestamp
		if (\ctype_digit($val)) {
			$ts = (int)$val;
			return $ts > 0 ? \gmdate('Y-m-d', $ts) : '';
		}

		// Fallback: strtotime
		$ts = \strtotime($val);
		return $ts ? \date('Y-m-d', $ts) : '';
	}
}

/* =====================================================
 * 3) Pfad ggf. mit Zahlungsdatum (YYYY-MM-DD_) präfixen
 * ===================================================== */
if (!function_exists(__NAMESPACE__ . '\\cmxbu_pdfpath_with_paid_prefix')) {
	function cmxbu_pdfpath_with_paid_prefix(string $pdf_path, int $post_id): string {
		$paid_raw = get_post_meta($post_id, 'cmx_beleg_bezahlt_am', true);
		error_log('[CMX] Prüfe Zahlungsdatum für Beleg-ID ' . $post_id . ': ' . var_export($paid_raw, true));

		$ymd = cmxbu_norm_ymd_date($paid_raw);
		if ($ymd === '') {
			error_log('[CMX] Kein gültiges Zahlungsdatum – Dateiname bleibt unverändert.');
			return $pdf_path;
		}

		$info     = \pathinfo($pdf_path);
		$dirname  = $info['dirname']  ?? '';
		$filename = $info['filename'] ?? '';
		$ext      = isset($info['extension']) && $info['extension'] !== '' ? ('.' . $info['extension']) : '.pdf';

		// Doppeltes Präfix vermeiden
		if (!\preg_match('~^\d{4}-\d{2}-\d{2}_~', $filename)) {
			$filename = $ymd . '_' . $filename;
		}

		$new_path = ($dirname !== '' ? $dirname . DIRECTORY_SEPARATOR : '') . $filename . $ext;
		error_log('[CMX] Neuer Dateiname: ' . $new_path);

		return $new_path;
	}
}

/* =====================================================
 * 4) Wrapper: Zahlungsdatum berücksichtigen + speichern
 * ===================================================== */
if (!function_exists(__NAMESPACE__ . '\\cmxbu_save_beleg_pdf_paidaware')) {
	function cmxbu_save_beleg_pdf_paidaware(string $pdf_path, string $pdf_binary, int $post_id, string $beleg_ref = ''): bool {
		// ggf. Dateinamen mit Zahlungsdatum präfixen
		$prefixed_path = cmxbu_pdfpath_with_paid_prefix($pdf_path, $post_id);

		// speichern + Altdateien mit gleicher Beleg-Nr. löschen
		$ok = cmxbu_save_beleg_pdf($prefixed_path, $pdf_binary, $beleg_ref);

		if ($ok) {
			error_log('[CMX] PDF erfolgreich gespeichert unter: ' . $prefixed_path);
		} else {
			error_log('[CMX] FEHLER beim Speichern der PDF: ' . $prefixed_path);
		}

		return $ok;
	}
}

/* =====================================================
 * Beispiel:
 * =====================================================
 * $ok = cmxbu_save_beleg_pdf_paidaware(
 *     $pdf_path,
 *     $pdf_binary,
 *     $post_id,
 *     get_the_title($post_id)
 * );
 */


// fixme rju 2025-11-18: noch zu löschen: automatisch-gespeicherter-entwurf_rechnung
