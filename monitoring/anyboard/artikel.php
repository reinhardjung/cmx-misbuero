<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

return [
    'type' => 'basic',
    'width' => 1,
    'height' => 1,
    'background' => '#1f3a93',
    'basic' => [
        'title' => 'Artikel',
        'value' => '0',
    ],
    'source' => [
        'endpoint' => 'stats',
        'mapping' => [
            'basic.value' => 'data.artikel',
        ],
    ],
];
