<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

return [
    'background' => '',
    'type' => 'image',
    'width' => 1,
    'height' => 1,
    'image' => [
        'url' => \function_exists(__NAMESPACE__ . '\\cmx_misbuero_favicon_url') ? cmx_misbuero_favicon_url() : '',
    ],
];
