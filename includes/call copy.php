<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

function cmx_norm_phone(string $raw): string {
    $raw = rawurldecode($raw);
    $raw = preg_replace('/[^0-9\+]/', '', $raw) ?? '';
    if ($raw !== '' && str_starts_with($raw, '00')) $raw = '+' . substr($raw, 2);
    if ($raw !== '' && $raw[0] !== '+') $raw = '+' . $raw;
    $raw = preg_replace('/(?!^)\+/', '', $raw) ?? '';
    return ($raw === '+' ? '' : $raw);
}


add_action('template_redirect', function () {
    if (empty($_GET['cmx_call'])) return;
    $num = cmx_norm_phone((string) $_GET['cmx_call']);
    if ($num === '') {
        status_header(400);
        wp_die('Ungültige Telefonnummer.', 'Call Router', ['response' => 400]);
    }
    wp_redirect('tel:' . $num, 302);
    exit;
});
