<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

return [
    'name' => 'Cockpit',
    'color' => 'danger',
    'cols' => 6,
    'rows' => 8,
    'source' => [
        'endpoint' => 'stats',
        'refresh' => 60,
    ],
    'actions' => [
        'sound' => 'data.changed == true',
    ],
    'widgets' => cmx_anyboard_load_widgets(),
];
