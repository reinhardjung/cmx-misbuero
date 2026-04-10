<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

return [
    'type' => 'table',
    'width' => 3,
    'height' => 8,
    'table' => [
        'title' => 'Zuletzt genutzt',
        'columns' => [
            [
                'id' => 'name',
                'title' => 'Kontakt',
                'flex' => 2.1,
                'style' => 'bold',
            ],
            [
                'id' => 'date',
                'title' => 'Zuletzt',
                'flex' => 1.8,
            ],
            [
                'id' => 'source',
                'title' => 'Art',
                'flex' => 1.0,
                'color' => 'neutral',
            ],
            [
                'id' => 'amount',
                'title' => 'Betrag',
                'align' => 'right',
                'flex' => 1.2,
            ],
        ],
        'data' => [
            ['name' => '', 'date' => '', 'source' => '', 'amount' => ''],
            ['name' => '', 'date' => '', 'source' => '', 'amount' => ''],
            ['name' => '', 'date' => '', 'source' => '', 'amount' => ''],
            ['name' => '', 'date' => '', 'source' => '', 'amount' => ''],
            ['name' => '', 'date' => '', 'source' => '', 'amount' => ''],
            ['name' => '', 'date' => '', 'source' => '', 'amount' => ''],
            ['name' => '', 'date' => '', 'source' => '', 'amount' => ''],
            ['name' => '', 'date' => '', 'source' => '', 'amount' => ''],
            ['name' => '', 'date' => '', 'source' => '', 'amount' => ''],
            ['name' => '', 'date' => '', 'source' => '', 'amount' => ''],
        ],
    ],
    'source' => [
        'endpoint' => 'stats',
        'mapping' => [
            'data[].name' => 'data.kontakte_letzte_nutzung[].name',
            'data[].date' => 'data.kontakte_letzte_nutzung[].date',
            'data[].source' => 'data.kontakte_letzte_nutzung[].source',
            'data[].amount' => 'data.kontakte_letzte_nutzung[].amount',
        ],
    ],
];
