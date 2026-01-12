<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

return [
    'width' => 3,
    'height' => 2,
    'table' => [
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
    ],
    'source' => [
        'endpoint' => 'stats',
        'mapping' => [
            'table.data[].label' => 'data.bewegungen[].label',
            'table.data[].green' => 'data.bewegungen[].green',
            'table.data[].red' => 'data.bewegungen[].red',
            'table.data[].sum' => 'data.bewegungen[].sum',
        ],
    ],
];
