<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

return [
    'type' => 'chart',
    'width' => 6,
    'height' => 3,
    'chart' => [
        'title' => 'Umsatz (akt. Jahr)',
        'legend' => false,
        'xAxis' => [
            'visible' => true,
            'axisLabels' => true,
        ],
        'yLeftAxis' => [
            'visible' => true,
            'axisLabels' => true,
            'min' => 0,
        ],
        'series' => [
            [
                'id' => 'umsatz',
                'name' => 'Umsatz',
                'type' => 'bar',
                'color' => 'warning',
            ],
        ],
    ],
    'source' => [
        'endpoint' => 'stats',
        'mapping' => [
            'data[].x' => 'data.umsatz[].month',
            'data[].umsatz' => 'data.umsatz[].value',
        ],
    ],
];
