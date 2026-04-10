<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

return [
    'type' => 'image',
    'width' => 3,
    'height' => 8,
    'background' => '',
    'image' => [
        'url' => '',
    ],
    'source' => [
        'endpoint' => 'stats',
        'mapping' => [
            'url' => 'data.kontakte_letzte_nutzung_url',
        ],
    ],
];
