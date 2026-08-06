<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

return [
    'type' => 'basic',
    'width' => 1,
    'height' => 1,
    'background' => '#8c8f94',
    'basic' => [
        'title' => 'Dokumente',
        'value' => '0',
    ],
    'source' => [
        'endpoint' => 'stats',
        'mapping' => [
            'value' => 'data.dokumente',
        ],
    ],
];
