<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

$count = function_exists(__NAMESPACE__ . '\\cmx_anyboard_count_sellable_artikel')
    ? cmx_anyboard_count_sellable_artikel()
    : 0;

return [
    'type' => 'basic',
    'width' => 1,
    'height' => 1,
    'background' => '#1f3a93',
    'basic' => [
        'title' => 'Artikel',
        'value' => (string) $count,
    ],
    'source' => [
        'endpoint' => 'stats',
        'mapping' => [
            'basic.value' => 'data.artikel',
        ],
    ],
];
