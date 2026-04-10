<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

return [
    'type' => 'basic',
    'width' => 1,
    'height' => 1,
    'background' => '#8e44ad',
    'basic' => [
        'title' => 'Kontakte',
        'value' => '0',
    ],
    'source' => [
        'endpoint' => 'stats',
        'mapping' => [
            'value' => 'data.kontakte',
        ],
    ],
];
