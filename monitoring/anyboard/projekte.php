<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

return [
    'type' => 'basic',
    'width' => 1,
    'height' => 1,
    'background' => '#d35400',
    'basic' => [
        'title' => 'Projekte',
        'subtitle' => 'Aktiv',
        'value' => '0',
    ],
    'source' => [
        'endpoint' => 'stats',
        'mapping' => [
            'basic.value' => 'data.projekte',
        ],
    ],
];
