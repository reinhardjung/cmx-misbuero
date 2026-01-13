<?php
namespace CLOUDMEISTER\CMX\Buero;

function cmx_anyboard_required_credentials(): array
{
    return [
        'user' => '',
        'pass' => '',
    ];
}

function cmx_anyboard_count_posts(string $post_type): int
{
    if (!function_exists('wp_count_posts')) {
        return 0;
    }

    $counts = wp_count_posts($post_type);
    if (!$counts) {
        return 0;
    }

    $total = 0;
    foreach ((array) $counts as $status => $count) {
        if (in_array($status, ['trash', 'auto-draft'], true)) {
            continue;
        }
        $total += (int) $count;
    }

    return $total;
}

function cmx_anyboard_count_active_projects(): int
{
    if (!function_exists('wp_count_posts')) {
        return 0;
    }

    $counts = wp_count_posts('projekte');
    if (!$counts || !isset($counts->publish)) {
        return 0;
    }

    return (int) $counts->publish;
}

function cmx_anyboard_count_sellable_artikel(): int
{
    if (!class_exists('\\WP_Query')) {
        return 0;
    }

    $meta_key = defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VERKAUFBAR')
        ? CMX_ARTIKEL_META_VERKAUFBAR
        : '_cmx_artikel_verkaufbar';

    $query = new \WP_Query([
        'post_type' => 'artikel',
        'post_status' => ['publish', 'private'],
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => false,
        'meta_query' => [
            'relation' => 'OR',
            [
                'key' => $meta_key,
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => $meta_key,
                'value' => '0',
                'compare' => '=',
            ],
        ],
    ]);

    return (int) $query->found_posts;
}

function cmx_anyboard_rechnungen_stats(): array
{
    if (!class_exists('\\WP_Query')) {
        return ['paid_count' => 0, 'open_count' => 0, 'sum_total' => 0.0];
    }

    $paid_key = defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT')
        ? CMX_BELEG_META_BEZAHLT
        : '_cmx_beleg_bezahlt_am';
    $paid_key_alt = ltrim($paid_key, '_');

    $base_args = [
        'post_type' => 'belege',
        'post_status' => ['publish', 'private'],
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'fields' => 'ids',
        'tax_query' => [
            [
                'taxonomy' => 'belege_kategorien',
                'field' => 'slug',
                'terms' => ['rechnung'],
                'operator' => 'IN',
            ],
        ],
    ];

    $paid_query = new \WP_Query(array_merge($base_args, [
        'meta_query' => [
            'relation' => 'OR',
            [
                'relation' => 'AND',
                [
                    'key' => $paid_key,
                    'compare' => 'EXISTS',
                ],
                [
                    'key' => $paid_key,
                    'value' => '',
                    'compare' => '!=',
                ],
            ],
            [
                'relation' => 'AND',
                [
                    'key' => $paid_key_alt,
                    'compare' => 'EXISTS',
                ],
                [
                    'key' => $paid_key_alt,
                    'value' => '',
                    'compare' => '!=',
                ],
            ],
        ],
    ]));

    $open_query = new \WP_Query(array_merge($base_args, [
        'meta_query' => [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                [
                    'key' => $paid_key,
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => $paid_key,
                    'value' => '',
                    'compare' => '=',
                ],
            ],
            [
                'relation' => 'OR',
                [
                    'key' => $paid_key_alt,
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => $paid_key_alt,
                    'value' => '',
                    'compare' => '=',
                ],
            ],
        ],
    ]));

    $sum_total = 0.0;
    if (function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
        $all_ids = array_unique(array_merge($paid_query->posts, $open_query->posts));
        foreach ($all_ids as $bid) {
            $calc = cmxbu_get_beleg_positionen_calc($bid);
            $sum_total += isset($calc['total']) ? (float) $calc['total'] : 0.0;
        }
    }

    return [
        'paid_count' => count($paid_query->posts),
        'open_count' => count($open_query->posts),
        'sum_total' => $sum_total,
    ];
}

function cmx_anyboard_angebote_stats(): array
{
    if (!class_exists('\\WP_Query')) {
        return ['paid_count' => 0, 'open_count' => 0, 'sum_total' => 0.0];
    }

    $paid_key = defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT')
        ? CMX_BELEG_META_BEZAHLT
        : '_cmx_beleg_bezahlt_am';
    $paid_key_alt = ltrim($paid_key, '_');

    $base_args = [
        'post_type' => 'belege',
        'post_status' => ['publish', 'private'],
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'fields' => 'ids',
        'tax_query' => [
            [
                'taxonomy' => 'belege_kategorien',
                'field' => 'slug',
                'terms' => ['angebot'],
                'operator' => 'IN',
            ],
        ],
    ];

    $paid_query = new \WP_Query(array_merge($base_args, [
        'meta_query' => [
            'relation' => 'OR',
            [
                'relation' => 'AND',
                [
                    'key' => $paid_key,
                    'compare' => 'EXISTS',
                ],
                [
                    'key' => $paid_key,
                    'value' => '',
                    'compare' => '!=',
                ],
            ],
            [
                'relation' => 'AND',
                [
                    'key' => $paid_key_alt,
                    'compare' => 'EXISTS',
                ],
                [
                    'key' => $paid_key_alt,
                    'value' => '',
                    'compare' => '!=',
                ],
            ],
        ],
    ]));

    $open_query = new \WP_Query(array_merge($base_args, [
        'meta_query' => [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                [
                    'key' => $paid_key,
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => $paid_key,
                    'value' => '',
                    'compare' => '=',
                ],
            ],
            [
                'relation' => 'OR',
                [
                    'key' => $paid_key_alt,
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => $paid_key_alt,
                    'value' => '',
                    'compare' => '=',
                ],
            ],
        ],
    ]));

    $sum_total = 0.0;
    if (function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
        $all_ids = array_unique(array_merge($paid_query->posts, $open_query->posts));
        foreach ($all_ids as $bid) {
            $calc = cmxbu_get_beleg_positionen_calc($bid);
            $sum_total += isset($calc['total']) ? (float) $calc['total'] : 0.0;
        }
    }

    return [
        'paid_count' => count($paid_query->posts),
        'open_count' => count($open_query->posts),
        'sum_total' => $sum_total,
    ];
}

function cmx_anyboard_ausgaben_stats(): array
{
    if (!class_exists('\\WP_Query')) {
        return ['paid_sum' => 0.0, 'open_sum' => 0.0];
    }

    $paid_key = defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT')
        ? CMX_BELEG_META_BEZAHLT
        : '_cmx_beleg_bezahlt_am';
    $paid_key_alt = ltrim($paid_key, '_');
    $betrag_key = defined(__NAMESPACE__ . '\\CMX_AUSGABEN_META_GESAMTBETRAG')
        ? CMX_AUSGABEN_META_GESAMTBETRAG
        : '_cmx_ausgaben_gesamtbetrag';

    $base_args = [
        'post_type' => 'ausgaben',
        'post_status' => ['publish', 'private'],
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'fields' => 'ids',
    ];

    $paid_query = new \WP_Query(array_merge($base_args, [
        'meta_query' => [
            'relation' => 'OR',
            [
                'relation' => 'AND',
                [
                    'key' => $paid_key,
                    'compare' => 'EXISTS',
                ],
                [
                    'key' => $paid_key,
                    'value' => '',
                    'compare' => '!=',
                ],
            ],
            [
                'relation' => 'AND',
                [
                    'key' => $paid_key_alt,
                    'compare' => 'EXISTS',
                ],
                [
                    'key' => $paid_key_alt,
                    'value' => '',
                    'compare' => '!=',
                ],
            ],
        ],
    ]));

    $open_query = new \WP_Query(array_merge($base_args, [
        'meta_query' => [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                [
                    'key' => $paid_key,
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => $paid_key,
                    'value' => '',
                    'compare' => '=',
                ],
            ],
            [
                'relation' => 'OR',
                [
                    'key' => $paid_key_alt,
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => $paid_key_alt,
                    'value' => '',
                    'compare' => '=',
                ],
            ],
        ],
    ]));

    $paid_sum = 0.0;
    foreach ($paid_query->posts as $pid) {
        $raw = (string) get_post_meta($pid, $betrag_key, true);
        $val = (float) str_replace(',', '.', preg_replace('/[^0-9,.\-]/', '', $raw));
        $paid_sum += $val;
    }

    $open_sum = 0.0;
    foreach ($open_query->posts as $pid) {
        $raw = (string) get_post_meta($pid, $betrag_key, true);
        $val = (float) str_replace(',', '.', preg_replace('/[^0-9,.\-]/', '', $raw));
        $open_sum += $val;
    }

    return [
        'paid_sum' => $paid_sum,
        'open_sum' => $open_sum,
    ];
}

function cmx_anyboard_umsatz_series(int $year): array
{
    if (!class_exists('\\WP_Query')) {
        return [];
    }

    $paid_key = defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT')
        ? CMX_BELEG_META_BEZAHLT
        : '_cmx_beleg_bezahlt_am';
    $paid_key_alt = ltrim($paid_key, '_');

    $range_start = $year . '-01-01';
    $range_end = $year . '-12-31';

    $base_args = [
        'post_type' => 'belege',
        'post_status' => ['publish', 'private'],
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'fields' => 'ids',
        'tax_query' => [
            [
                'taxonomy' => 'belege_kategorien',
                'field' => 'slug',
                'terms' => ['rechnung'],
                'operator' => 'IN',
            ],
        ],
    ];

    $query_main = new \WP_Query(array_merge($base_args, [
        'meta_query' => [
            [
                'key' => $paid_key,
                'value' => [$range_start, $range_end],
                'compare' => 'BETWEEN',
                'type' => 'DATE',
            ],
        ],
    ]));

    $query_alt = new \WP_Query(array_merge($base_args, [
        'meta_query' => [
            [
                'key' => $paid_key_alt,
                'value' => [$range_start, $range_end],
                'compare' => 'BETWEEN',
                'type' => 'DATE',
            ],
        ],
    ]));

    $month_totals = array_fill(1, 12, 0.0);
    $all_ids = array_unique(array_merge($query_main->posts, $query_alt->posts));
    foreach ($all_ids as $bid) {
        $paid_date = (string) get_post_meta($bid, $paid_key, true);
        if ($paid_date === '') {
            $paid_date = (string) get_post_meta($bid, $paid_key_alt, true);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paid_date)) {
            continue;
        }
        $month = (int) substr($paid_date, 5, 2);
        if ($month < 1 || $month > 12) {
            continue;
        }
        $total = 0.0;
        if (function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
            $calc = cmxbu_get_beleg_positionen_calc($bid);
            $total = isset($calc['total']) ? (float) $calc['total'] : 0.0;
        }
        $month_totals[$month] += $total;
    }

    $labels = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
    $series = [];
    for ($i = 1; $i <= 12; $i++) {
        $series[] = [
            'month' => $labels[$i - 1],
            'value' => round($month_totals[$i], 2),
        ];
    }

    return $series;
}

function cmx_anyboard_widget_files(): array
{
    return [
        __DIR__ . '/cockpit.php',
        __DIR__ . '/datum_zeit.php',
        __DIR__ . '/kontakte.php',
        __DIR__ . '/artikel.php',
        __DIR__ . '/projekte.php',
        __DIR__ . '/dokumente.php',
        __DIR__ . '/chart_umsatz.php',
        __DIR__ . '/bewegungen.php',
    ];
}

function cmx_anyboard_load_widgets(): array
{
    $widgets = [];
    foreach (cmx_anyboard_widget_files() as $file) {
        if (!is_file($file)) {
            continue;
        }
        $widget = include $file;
        if (is_array($widget)) {
            $widgets[] = $widget;
        }
    }
    return $widgets;
}

function cmx_anyboard_build_payload(): array
{
    $stats_endpoint = function_exists('home_url')
        ? home_url('/wp-json/cmx-misbuero/v1/anyboard-data')
        : '';

    return [
        'name' => 'Mis Buero',
        'sources' => [
            [
                'auth' => 'none',
                'name' => 'CMX',
                'endpoints' => [
                    [
                        'id' => 'stats',
                        'url' => $stats_endpoint,
                        'refresh' => 60,
                    ],
                ],
            ],
        ],
        'dashboards' => [
            [
                'name' => 'Cockpit',
                'color' => 'danger',
                'source' => [
                    'endpoint' => 'stats',
                    'refresh' => 60,
                ],
                'actions' => [
                    'sound' => 'data.changed == true',
                ],
                'widgets' => cmx_anyboard_load_widgets(),
            ],
        ],
    ];
}

function cmx_anyboard_direct_response(): void
{
    $required = cmx_anyboard_required_credentials();
    $query_user = isset($_GET['user']) ? (string) $_GET['user'] : '';
    $query_pass = isset($_GET['pw']) ? (string) $_GET['pw'] : '';

    if ($required['user'] !== '' || $required['pass'] !== '') {
        if ($query_user !== $required['user'] || $query_pass !== $required['pass']) {
            header('Content-Type: application/json; charset=utf-8', true, 401);
            echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(cmx_anyboard_build_payload(), JSON_UNESCAPED_SLASHES);
}

function cmx_anyboard_permission(\WP_REST_Request $request)
{
    $required = cmx_anyboard_required_credentials();
    if ($required['user'] === '' && $required['pass'] === '') {
        return true;
    }

    $user = (string) $request->get_param('user');
    $pw = (string) $request->get_param('pw');

    if (hash_equals($required['user'], $user) && hash_equals($required['pass'], $pw)) {
        return true;
    }

    return new \WP_Error('cmx_anyboard_unauthorized', 'Unauthorized', ['status' => 401]);
}

function cmx_anyboard_response(): \WP_REST_Response
{
    return rest_ensure_response(cmx_anyboard_build_payload());
}

function cmx_anyboard_data_response(): \WP_REST_Response
{
    $kontakte = cmx_anyboard_count_posts('kontakte');
    $artikel = cmx_anyboard_count_sellable_artikel();
    $projekte = cmx_anyboard_count_active_projects();
    $dokumente = cmx_anyboard_count_posts('dokumente');
    $rechnungen = cmx_anyboard_rechnungen_stats();
    $angebote = cmx_anyboard_angebote_stats();
    $ausgaben = cmx_anyboard_ausgaben_stats();
    $year = (int) current_time('Y');
    $umsatz = cmx_anyboard_umsatz_series($year);

    $sum_label = number_format((float) ($rechnungen['sum_total'] ?? 0.0), 2, '.', "'");
    $sum_angebote = number_format((float) ($angebote['sum_total'] ?? 0.0), 2, '.', "'");
    $ausgaben_paid = (float) ($ausgaben['paid_sum'] ?? 0.0);
    $ausgaben_open = (float) ($ausgaben['open_sum'] ?? 0.0);
    $ausgaben_diff = $ausgaben_paid - $ausgaben_open;
    $ausgaben_paid_label = number_format($ausgaben_paid, 2, '.', "'");
    $ausgaben_open_label = number_format($ausgaben_open, 2, '.', "'");
    $ausgaben_diff_label = number_format($ausgaben_diff, 2, '.', "'");

    $bewegungen = [
        [
            'label' => 'Rechnungen',
            'green' => (string) ($rechnungen['paid_count'] ?? 0),
            'red' => (string) ($rechnungen['open_count'] ?? 0),
            'sum' => $sum_label,
        ],
        [
            'label' => 'Angebote',
            'green' => (string) ($angebote['paid_count'] ?? 0),
            'red' => (string) ($angebote['open_count'] ?? 0),
            'sum' => $sum_angebote,
        ],
        [
            'label' => 'Ausgaben',
            'green' => $ausgaben_paid_label,
            'red' => $ausgaben_open_label,
            'sum' => $ausgaben_diff_label,
        ],
        ['label' => '', 'green' => '', 'red' => '', 'sum' => ''],
        ['label' => '', 'green' => '', 'red' => '', 'sum' => ''],
        ['label' => '', 'green' => '', 'red' => '', 'sum' => ''],
    ];

    $data = [
        'kontakte' => $kontakte,
        'artikel' => $artikel,
        'projekte' => $projekte,
        'dokumente' => $dokumente,
        'bewegungen' => $bewegungen,
        'umsatz' => $umsatz,
        'umsatz_year' => $year,
    ];

    $snapshot = md5(json_encode($data));
    $previous = get_transient('cmx_anyboard_snapshot');
    $changed = $previous !== $snapshot && $previous !== false;
    set_transient('cmx_anyboard_snapshot', $snapshot, 2 * HOUR_IN_SECONDS);

    return rest_ensure_response([
        'data' => $data + ['changed' => $changed],
    ]);
}

if (function_exists('add_action')) {
    add_action('rest_api_init', function () {
        register_rest_route('cmx-misbuero/v1', '/anyboard', [
            'methods' => 'GET',
            'permission_callback' => __NAMESPACE__ . '\\cmx_anyboard_permission',
            'callback' => __NAMESPACE__ . '\\cmx_anyboard_response',
        ]);
        register_rest_route('cmx-misbuero/v1', '/anyboard-data', [
            'methods' => 'GET',
            'permission_callback' => __NAMESPACE__ . '\\cmx_anyboard_permission',
            'callback' => __NAMESPACE__ . '\\cmx_anyboard_data_response',
        ]);
    });
} else {
    cmx_anyboard_direct_response();
}
