<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');
/**
 * Plugin Name: CMX Call Router (Query, MU)
 * Description: Startet Anrufe via ?cmx_call=<nummer> – ohne .htaccess u. ohne Permalink-Änderung.
 * Version:     1.0.0
 * Author:      CLOUD Meister
 */

/**
 * Telefonnummer in E.164 normalisieren: nur Ziffern + führendes Plus.
 */
function cmx_norm_phone(string $raw): string {
    $raw = rawurldecode($raw);                     // %2B -> +
    $raw = preg_replace('/[^0-9\+]/', '', $raw) ?: '';

    // 0041... -> +41...
    if ($raw !== '' && str_starts_with($raw, '00')) {
        $raw = '+' . substr($raw, 2);
    }
    // führendes + erzwingen
    if ($raw !== '' && $raw[0] !== '+') {
        $raw = '+' . $raw;
    }
    // weitere Pluszeichen (außer am Anfang) entfernen
    $raw = preg_replace('/(?!^)\+/', '', $raw) ?: '';

    return ($raw === '+' ? '' : $raw);
}

/**
 * HARTE Frontdoor: direkt beim Laden der MU-Datei reagieren,
 * noch bevor Themes/Plugins oder Caches eingreifen können.
 */
if (isset($_GET['cmx_call'])) {
    $num = cmx_norm_phone((string) $_GET['cmx_call']);

    if ($num === '') {
        if (!headers_sent()) header('Content-Type: text/plain; charset=UTF-8', true, 400);
        echo 'Ungültige Telefonnummer.'; exit;
    }

    // primitive Geräte-Erkennung
    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    $is_mobile = (bool) preg_match('/iphone|ipad|ipod|android|mobile/', $ua);

    if ($is_mobile) {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Location: tel:' . $num, true, 302);
        }
        exit;
    }

    // Desktop-Fallback: HTML mit Button + JS + Meta-Refresh
    if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
    $safe = htmlspecialchars($num, ENT_QUOTES, 'UTF-8');
    ?>
<!doctype html>
<html lang="de">
<meta charset="utf-8">
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<meta http-equiv="refresh" content="0;url=tel:<?= $safe ?>">
<title>Anruf starten</title>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; padding:24px;">
  <h1>Anruf starten</h1>
  <p>Nummer: <strong><?= $safe ?></strong></p>
  <p><a href="tel:<?= $safe ?>" style="display:inline-block;padding:12px 18px;border:1px solid #333;border-radius:6px;text-decoration:none;">Jetzt anrufen</a></p>
  <p>Falls sich nichts öffnet: Öffne diesen Link auf deinem Smartphone.</p>
  <script>try{location.href="tel:<?= $safe ?>";}catch(e){}</script>
</body>
</html>
    <?php
    exit;
}

// Optionaler schneller Selbsttest: https://domain/?cmx_call_test=1
if (isset($_GET['cmx_call_test'])) { header('Content-Type: text/plain; charset=UTF-8'); echo "CMX Call Router aktiv."; exit; }
