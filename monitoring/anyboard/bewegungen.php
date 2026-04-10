<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

return [
    'type' => 'minitable',
    'width' => 3,
    'height' => 2,
    'minitable' => [
        'title' => 'Bewegungen (akt. Jahr)',
        'columns' => [
            [
                'id' => 'label',
                'title' => '',
                'flex' => 2,
            ],
            [
                'id' => 'green',
                'title' => '',
                'align' => 'right',
                'style' => 'bold',
                'color' => 'success',
                'flex' => 1.35,
            ],
            [
                'id' => 'red',
                'title' => '',
                'align' => 'right',
                'style' => 'bold',
                'color' => 'danger',
                'flex' => 1.60,
            ],
            [
                'id' => 'sum',
                'title' => '',
                'align' => 'right',
                'style' => 'bold',
                'flex' => 2,
            ],
        ],
        'data' => [
            ['label' => '', 'green' => '', 'red' => '', 'sum' => ''],
            ['label' => '', 'green' => '', 'red' => '', 'sum' => ''],
            ['label' => '', 'green' => '', 'red' => '', 'sum' => ''],
            ['label' => '', 'green' => '', 'red' => '', 'sum' => ''],
            ['label' => '', 'green' => '', 'red' => '', 'sum' => ''],
            ['label' => '', 'green' => '', 'red' => '', 'sum' => ''],
        ],
    ],
    'source' => [
        'endpoint' => 'stats',
        'mapping' => [
            'data[].label' => 'data.bewegungen[].label',
            'data[].green' => 'data.bewegungen[].green',
            'data[].red' => 'data.bewegungen[].red',
            'data[].sum' => 'data.bewegungen[].sum',
        ],
    ],
];
