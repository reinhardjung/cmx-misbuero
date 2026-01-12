<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

$count = function_exists(__NAMESPACE__ . '\\cmx_anyboard_count_posts')
    ? cmx_anyboard_count_posts('kontakte')
    : 0;

return [
    'type' => 'basic',
    'width' => 1,
    'height' => 1,
    'background' => '#8e44ad',
    'basic' => [
        'title' => 'Kontakte',
        'value' => (string) $count,
    ],
    'source' => [
        'endpoint' => 'stats',
        'mapping' => [
            'basic.value' => 'data.kontakte',
        ],
    ],
];
