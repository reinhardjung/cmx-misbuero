<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

// $item['value_formatted'] = number_format((float) $item['value'], 2, '.', "'");

return [
    'type' => 'chart',
    'width' => 6,
    'height' => 4,
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
                'valueLabels' => true,
            ],
        ],
    ],
    'source' => [
        'endpoint' => 'stats',
		'mapping' => [
			'data[].x' => 'data.umsatz[].month',
			'data[].umsatz' => 'data.umsatz[].value',
			'data[].label' => function($item) {
				return number_format((float) $item['value'], 2, '.', "'");
			},
		],
    ],
];
