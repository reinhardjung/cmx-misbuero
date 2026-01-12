<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

return [
    'type' => 'minitable',
    'width' => 2,
    'height' => 2,
    'minitable' => [
        'title' => 'Bewegungen',
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
            ],
            [
                'id' => 'red',
                'title' => '',
                'align' => 'right',
                'style' => 'bold',
                'color' => 'danger',
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
