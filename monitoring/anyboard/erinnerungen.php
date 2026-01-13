<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

return [
    'type' => 'minitable',
    'width' => 3,
    'height' => 2,
    'minitable' => [
        'title' => 'Erinnerungen',
        'columns' => [
            [
                'id' => 'name',
                'title' => '',
                'flex' => 2,
                'style' => 'bold',
            ],
            [
                'id' => 'date',
                'title' => '',
                'align' => 'right',
            ],
            [
                'id' => 'type',
                'title' => '',
                'align' => 'right',
                'color' => 'neutral',
            ],
        ],
        'data' => [
            ['name' => '', 'date' => '', 'type' => ''],
            ['name' => '', 'date' => '', 'type' => ''],
            ['name' => '', 'date' => '', 'type' => ''],
            ['name' => '', 'date' => '', 'type' => ''],
            ['name' => '', 'date' => '', 'type' => ''],
        ],
    ],
    'source' => [
        'endpoint' => 'stats',
        'mapping' => [
            'data[].name' => 'data.erinnerungen[].name',
            'data[].date' => 'data.erinnerungen[].date',
            'data[].type' => 'data.erinnerungen[].type',
        ],
    ],
];
