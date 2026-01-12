<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

$count = function_exists(__NAMESPACE__ . '\\cmx_anyboard_count_active_projects')
    ? cmx_anyboard_count_active_projects()
    : 0;

return [
    'type' => 'basic',
    'width' => 1,
    'height' => 1,
    'background' => '#d35400',
    'basic' => [
        'title' => 'Projekte',
        'subtitle' => 'Aktiv',
        'value' => (string) $count,
    ],
    'source' => [
        'endpoint' => 'stats',
        'mapping' => [
            'basic.value' => 'data.projekte',
        ],
    ],
];
