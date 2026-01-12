<?php
namespace CLOUDMEISTER\CMX\Buero;

$required_user = '';
$required_pass = '';

$query_user = isset($_GET['user']) ? (string) $_GET['user'] : '';
$query_pass = isset($_GET['pw']) ? (string) $_GET['pw'] : '';

if ($required_user !== '' || $required_pass !== '') {
    if ($query_user !== $required_user || $query_pass !== $required_pass) {
        header('Content-Type: application/json; charset=utf-8', true, 401);
        echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_SLASHES);
        exit;
    }
}

header('Content-Type: application/json; charset=utf-8');

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

echo json_encode($payload, JSON_UNESCAPED_SLASHES);
