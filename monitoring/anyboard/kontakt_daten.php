<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

return [
    'type' => 'table',
    'width' => 6,
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
                'flex' => 1.1,
            ],
            [
                'id' => 'type',
                'title' => 'Typ',
                'flex' => 1.4,
                'color' => 'neutral',
            ],
            [
                'id' => 'email',
                'title' => 'E-Mail',
                'flex' => 2.1,
            ],
            [
                'id' => 'phone',
                'title' => 'Telefon',
                'align' => 'right',
                'flex' => 1.2,
            ],
        ],
        'data' => [
            ['name' => '', 'date' => '', 'type' => '', 'email' => '', 'phone' => ''],
            ['name' => '', 'date' => '', 'type' => '', 'email' => '', 'phone' => ''],
            ['name' => '', 'date' => '', 'type' => '', 'email' => '', 'phone' => ''],
            ['name' => '', 'date' => '', 'type' => '', 'email' => '', 'phone' => ''],
            ['name' => '', 'date' => '', 'type' => '', 'email' => '', 'phone' => ''],
            ['name' => '', 'date' => '', 'type' => '', 'email' => '', 'phone' => ''],
            ['name' => '', 'date' => '', 'type' => '', 'email' => '', 'phone' => ''],
            ['name' => '', 'date' => '', 'type' => '', 'email' => '', 'phone' => ''],
            ['name' => '', 'date' => '', 'type' => '', 'email' => '', 'phone' => ''],
            ['name' => '', 'date' => '', 'type' => '', 'email' => '', 'phone' => ''],
        ],
    ],
    'source' => [
        'endpoint' => 'stats',
        'mapping' => [
            'data[].name' => 'data.kontakt_daten[].name',
            'data[].date' => 'data.kontakt_daten[].date',
            'data[].type' => 'data.kontakt_daten[].type',
            'data[].email' => 'data.kontakt_daten[].email',
            'data[].phone' => 'data.kontakt_daten[].phone',
        ],
    ],
];
