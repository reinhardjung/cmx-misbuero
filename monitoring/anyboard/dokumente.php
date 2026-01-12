<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

$count = function_exists(__NAMESPACE__ . '\\cmx_anyboard_count_posts')
    ? cmx_anyboard_count_posts('dokumente')
    : 0;

return [
    'type' => 'basic',
    'width' => 1,
    'height' => 1,
    'background' => '#8c8f94',
    'basic' => [
        'title' => 'Dokumente',
        'value' => (string) $count,
    ],
    'source' => [
        'endpoint' => 'stats',
        'mapping' => [
            'basic.value' => 'data.dokumente',
        ],
    ],
];
