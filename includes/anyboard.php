<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

add_action('rest_api_init', function () {
    register_rest_route('cmx-misbuero/v1', '/anyboard', [
        'methods' => 'GET',
        'permission_callback' => __NAMESPACE__ . '\\cmx_anyboard_permission',
        'callback' => __NAMESPACE__ . '\\cmx_anyboard_response',
    ]);
});

function cmx_anyboard_permission(\WP_REST_Request $request)
{
    $required_user = '';
    $required_pass = '';

    if ($required_user === '' && $required_pass === '') {
        return true;
    }

    $user = (string) $request->get_param('user');
    $pw = (string) $request->get_param('pw');

    if (hash_equals($required_user, $user) && hash_equals($required_pass, $pw)) {
        return true;
    }

    return new \WP_Error('cmx_anyboard_unauthorized', 'Unauthorized', ['status' => 401]);
}

function cmx_anyboard_response(): \WP_REST_Response
{
    $payload = [
        'name' => 'Mis Buero',
        'sources' => [],
        'dashboards' => [
            [
                'name' => 'Mis Buero',
                'color' => 'active',
                'widgets' => [
                    [
                        'type' => 'image',
                        'width' => 1,
                        'height' => 1,
                        'image' => [
                            'url' => 'https://misbuero.ch/wp-content/uploads/favicon.png',
                        ],
                    ],
                ],
            ],
        ],
    ];

    return rest_ensure_response($payload);
}
