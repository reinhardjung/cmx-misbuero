<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

return [
    'name' => 'Kontakte',
    'color' => 'info',
    'cols' => 6,
    'rows' => 8,
    'source' => [
        'endpoint' => 'stats',
        'refresh' => 60,
    ],
    'widgets' => cmx_anyboard_load_kontakte_widgets(),
];
