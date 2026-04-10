<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

return [
    'type' => 'table',
    'width' => 3,
    'height' => 8,
    'table' => [
        'title' => 'Zuletzt aktiv',
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
                'title' => 'Beleg',
                'flex' => 1.0,
                'color' => 'neutral',
            ],
            [
                'id' => 'amount',
                'title' => 'Betrag',
                'align' => 'right',
                'flex' => 1.2,
                'style' => 'bold',
                'colors' => [
                    '#d92d20' => 'amount_value < 0',
                    '#16a34a' => 'amount_value > 0',
                ],
            ],
        ],
        'data' => [
            ['name' => '', 'date' => '', 'source' => '', 'amount' => '', 'amount_value' => 0],
            ['name' => '', 'date' => '', 'source' => '', 'amount' => '', 'amount_value' => 0],
            ['name' => '', 'date' => '', 'source' => '', 'amount' => '', 'amount_value' => 0],
            ['name' => '', 'date' => '', 'source' => '', 'amount' => '', 'amount_value' => 0],
            ['name' => '', 'date' => '', 'source' => '', 'amount' => '', 'amount_value' => 0],
            ['name' => '', 'date' => '', 'source' => '', 'amount' => '', 'amount_value' => 0],
            ['name' => '', 'date' => '', 'source' => '', 'amount' => '', 'amount_value' => 0],
            ['name' => '', 'date' => '', 'source' => '', 'amount' => '', 'amount_value' => 0],
            ['name' => '', 'date' => '', 'source' => '', 'amount' => '', 'amount_value' => 0],
            ['name' => '', 'date' => '', 'source' => '', 'amount' => '', 'amount_value' => 0],
        ],
    ],
    'source' => [
        'endpoint' => 'stats',
        'mapping' => [
            'data[].name' => 'data.kontakte_letzte_nutzung[].name',
            'data[].date' => 'data.kontakte_letzte_nutzung[].date',
            'data[].source' => 'data.kontakte_letzte_nutzung[].source',
            'data[].amount' => 'data.kontakte_letzte_nutzung[].amount',
            'data[].amount_value' => 'data.kontakte_letzte_nutzung[].amount_value',
        ],
    ],
];
