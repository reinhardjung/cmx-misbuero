<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

return [
    'type' => 'chart',
    'width' => 1,
    'height' => 2,
    'chart' => [
        'title' => 'Umsatz',
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
                'id' => 'value',
                'name' => 'Umsatz',
                'type' => 'bar',
                'color' => 'active',
            ],
        ],
    ],
    'source' => [
        'endpoint' => 'stats',
        'mapping' => [
            'data[].x' => 'data.umsatz_breakdown[].label',
            'data[].value' => 'data.umsatz_breakdown[].value',
        ],
    ],
];
