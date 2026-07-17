<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

function cmx_anyboard_month_de(int $month): string
{
    $months = [
        1 => 'Januar',
        2 => 'Februar',
        3 => 'März',
        4 => 'April',
        5 => 'Mai',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'August',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Dezember',
    ];

    return $months[$month] ?? '';
}

function cmx_anyboard_plugin_version(): string
{
    $plugin_file = dirname(__DIR__, 2) . '/cmx-misbuero.php';
    if (!is_file($plugin_file)) {
        return '';
    }

    $contents = (string) file_get_contents($plugin_file);
    if ($contents === '') {
        return '';
    }

    if (preg_match('/^[ \\t\\/*#@]*Version:\\s*([^\\r\\n]+)/mi', $contents, $matches)) {
        return trim($matches[1]);
    }

    return '';
}

$day = (int) date('j');
$month = cmx_anyboard_month_de((int) date('n'));
$year = date('Y');
$date_label = trim($day . ' ' . $month . ' ' . $year);
$version = cmx_anyboard_plugin_version();
$version_label = $version !== '' ? '' . $version : '';

return [
    'type' => 'time',
    'width' => 1,
    'height' => 1,
    'time' => [
        'title' => $date_label,
        'value' => 'time',
        'subtitle' => $version_label,
        'subtitleColor' => '#8f9bb0',
    ],
];
