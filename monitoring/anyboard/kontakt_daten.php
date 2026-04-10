<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

return [
    'type' => 'table',
    'width' => 3,
    'height' => 8,
    'table' => [
        'title' => 'Erinnerungen',
        'columns' => [
            [
                'id' => 'name',
                'title' => 'Kontakt',
                'flex' => 2.2,
                'style' => 'bold',
            ],
            [
                'id' => 'date',
                'title' => 'Datum',
                'align' => 'right',
                'flex' => 1.2,
            ],
            [
                'id' => 'type',
                'title' => 'Typ',
                'flex' => 1.5,
                'color' => 'neutral',
            ],
        ],
        'data' => [
            ['name' => '', 'date' => '', 'type' => ''],
            ['name' => '', 'date' => '', 'type' => ''],
            ['name' => '', 'date' => '', 'type' => ''],
            ['name' => '', 'date' => '', 'type' => ''],
            ['name' => '', 'date' => '', 'type' => ''],
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
            'data[].name' => 'data.kontakt_daten[].name',
            'data[].date' => 'data.kontakt_daten[].date',
            'data[].type' => 'data.kontakt_daten[].type',
        ],
    ],
];
