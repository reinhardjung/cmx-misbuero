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

function cmx_anyboard_rechnungen_stats(int $year): array
{
    if (!class_exists('\\WP_Query')) {
        return ['paid_sum' => 0.0, 'open_sum' => 0.0, 'sum_total' => 0.0];
    }

    $paid_key = defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT')
        ? CMX_BELEG_META_BEZAHLT
        : '_cmx_beleg_bezahlt_am';
    $paid_key_alt = ltrim($paid_key, '_');
    $rng_key = defined(__NAMESPACE__ . '\\CMX_BELEG_META_RNG_DATUM')
        ? CMX_BELEG_META_RNG_DATUM
        : '_cmx_beleg_rng_datum';
    $rng_key_alt = ltrim($rng_key, '_');

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
                [
                    'key' => $paid_key,
                    'value' => [$range_start, $range_end],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE',
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
                [
                    'key' => $paid_key_alt,
                    'value' => [$range_start, $range_end],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE',
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
            [
                'relation' => 'OR',
                [
                    'key' => $rng_key,
                    'value' => [$range_start, $range_end],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE',
                ],
                [
                    'key' => $rng_key_alt,
                    'value' => [$range_start, $range_end],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE',
                ],
            ],
        ],
    ]));

    $sum_total = 0.0;
    $paid_sum = 0.0;
    $open_sum = 0.0;
    if (function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
        foreach ($paid_query->posts as $bid) {
            $calc = cmxbu_get_beleg_positionen_calc($bid);
            $total = isset($calc['total']) ? (float) $calc['total'] : 0.0;
            $paid_sum += $total;
            $sum_total += $total;
        }
        foreach ($open_query->posts as $bid) {
            $calc = cmxbu_get_beleg_positionen_calc($bid);
            $total = isset($calc['total']) ? (float) $calc['total'] : 0.0;
            $open_sum += $total;
            $sum_total += $total;
        }
    }

    return [
        'paid_sum' => $paid_sum,
        'open_sum' => $open_sum,
        'sum_total' => $sum_total,
    ];
}

function cmx_anyboard_angebote_stats(int $year): array
{
    if (!class_exists('\\WP_Query')) {
        return ['paid_sum' => 0.0, 'open_sum' => 0.0, 'sum_total' => 0.0];
    }

    $paid_key = defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT')
        ? CMX_BELEG_META_BEZAHLT
        : '_cmx_beleg_bezahlt_am';
    $paid_key_alt = ltrim($paid_key, '_');
    $rng_key = defined(__NAMESPACE__ . '\\CMX_BELEG_META_RNG_DATUM')
        ? CMX_BELEG_META_RNG_DATUM
        : '_cmx_beleg_rng_datum';
    $rng_key_alt = ltrim($rng_key, '_');

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
                [
                    'key' => $paid_key,
                    'value' => [$range_start, $range_end],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE',
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
                [
                    'key' => $paid_key_alt,
                    'value' => [$range_start, $range_end],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE',
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
            [
                'relation' => 'OR',
                [
                    'key' => $rng_key,
                    'value' => [$range_start, $range_end],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE',
                ],
                [
                    'key' => $rng_key_alt,
                    'value' => [$range_start, $range_end],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE',
                ],
            ],
        ],
    ]));

    $sum_total = 0.0;
    $paid_sum = 0.0;
    $open_sum = 0.0;
    if (function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
        foreach ($paid_query->posts as $bid) {
            $calc = cmxbu_get_beleg_positionen_calc($bid);
            $total = isset($calc['total']) ? (float) $calc['total'] : 0.0;
            $paid_sum += $total;
            $sum_total += $total;
        }
        foreach ($open_query->posts as $bid) {
            $calc = cmxbu_get_beleg_positionen_calc($bid);
            $total = isset($calc['total']) ? (float) $calc['total'] : 0.0;
            $open_sum += $total;
            $sum_total += $total;
        }
    }

    return [
        'paid_sum' => $paid_sum,
        'open_sum' => $open_sum,
        'sum_total' => $sum_total,
    ];
}

function cmx_anyboard_ausgaben_stats(int $year): array
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
    $rng_key = defined(__NAMESPACE__ . '\\CMX_BELEG_META_RNG_DATUM')
        ? CMX_BELEG_META_RNG_DATUM
        : '_cmx_beleg_rng_datum';
    $rng_key_alt = ltrim($rng_key, '_');

    $range_start = $year . '-01-01';
    $range_end = $year . '-12-31';

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
                [
                    'key' => $paid_key,
                    'value' => [$range_start, $range_end],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE',
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
                [
                    'key' => $paid_key_alt,
                    'value' => [$range_start, $range_end],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE',
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
            [
                'relation' => 'OR',
                [
                    'key' => $rng_key,
                    'value' => [$range_start, $range_end],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE',
                ],
                [
                    'key' => $rng_key_alt,
                    'value' => [$range_start, $range_end],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE',
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
        $raw_value = $month_totals[$i];
        $series[] = [
            'month' => $labels[$i - 1],
            'value' => round($raw_value, 2),
            'label' => number_format($raw_value, 0, '.', "'"),
        ];
    }

    return $series;
}

function cmx_anyboard_erinnerungen_rows(int $limit = 5): array
{
    if (!class_exists('\\WP_Query')) {
        return [];
    }

    $date_key = defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_DATUM')
        ? CMX_KONTAKTE_META_DATUM
        : '_cmx_kontakte_datum';
    $priv_key = defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_PRIVAT')
        ? CMX_KONTAKTE_META_PRIVAT
        : '_cmx_kontakte_privat';

    $q = new \WP_Query([
        'post_type' => 'kontakte',
        'post_status' => ['publish', 'private'],
        'posts_per_page' => -1,
        'meta_key' => $date_key,
        'meta_type' => 'DATE',
        'meta_query' => [
            [
                'key' => $date_key,
                'value' => '',
                'compare' => '!=',
            ],
        ],
        'no_found_rows' => true,
    ]);

    $rows = [];
    if ($q->have_posts()) {
        while ($q->have_posts()) {
            $q->the_post();
            $pid = get_the_ID();
            $title = get_the_title($pid);
            $date = (string) get_post_meta($pid, $date_key, true);
            $display = $date;
            $month = 0;
            $day = 0;
            if ($date !== '') {
                $tz = wp_timezone();
                $dt = \DateTime::createFromFormat('Y-m-d', $date, $tz);
                if ($dt) {
                    $display = wp_date('d.m.Y', $dt->getTimestamp(), $tz);
                    $month = (int) $dt->format('m');
                    $day = (int) $dt->format('d');
                }
            }

            $privat = (bool) get_post_meta($pid, $priv_key, true);
            if (!$privat) {
                $privat = (bool) get_post_meta($pid, '_cmx_privat', true);
            }
            $type_label = $privat ? 'Geburtsdatum' : 'Firmengründung';

            $rows[] = [
                'name' => $title,
                'date' => $display,
                'type' => $type_label,
                '_m' => $month,
                '_d' => $day,
            ];
        }
        wp_reset_postdata();
    }

    usort($rows, function (array $a, array $b): int {
        if ($a['_m'] !== $b['_m']) {
            return $a['_m'] <=> $b['_m'];
        }
        if ($a['_d'] !== $b['_d']) {
            return $a['_d'] <=> $b['_d'];
        }
        return strcmp($a['name'], $b['name']);
    });

    $rows = array_slice($rows, 0, $limit);

    foreach ($rows as &$row) {
        unset($row['_m'], $row['_d']);
    }
    unset($row);

    while (count($rows) < $limit) {
        $rows[] = ['name' => '', 'date' => '', 'type' => ''];
    }

    return $rows;
}

function cmx_anyboard_pie_svg(float $rechnungen, float $ausgaben): string
{
    $total = $rechnungen + $ausgaben;
    $size = 240;
    $cx = $size / 2;
    $cy = $size / 2;
    $r = 100;

    if ($total <= 0) {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">'
            . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="#7a7a7a"/>'
            . '</svg>';
    }

    $angle_a = ($rechnungen / $total) * 360.0;
    $angle_b = 360.0 - $angle_a;

    $path_a = cmx_anyboard_pie_slice_path($cx, $cy, $r, 0.0, $angle_a);
    $path_b = cmx_anyboard_pie_slice_path($cx, $cy, $r, $angle_a, $angle_b);

    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">'
        . '<path d="' . $path_a . '" fill="#2ecc71"/>'
        . '<path d="' . $path_b . '" fill="#e74c3c"/>'
        . '<circle cx="' . $cx . '" cy="' . $cy . '" r="50" fill="#1f2430"/>'
        . '</svg>';
}

function cmx_anyboard_pie_slice_path(float $cx, float $cy, float $r, float $start, float $sweep): string
{
    $start_rad = deg2rad($start - 90.0);
    $end_rad = deg2rad($start + $sweep - 90.0);

    $x1 = $cx + $r * cos($start_rad);
    $y1 = $cy + $r * sin($start_rad);
    $x2 = $cx + $r * cos($end_rad);
    $y2 = $cy + $r * sin($end_rad);

    $large_arc = $sweep > 180.0 ? 1 : 0;

    return 'M ' . $cx . ' ' . $cy . ' L ' . $x1 . ' ' . $y1 . ' A ' . $r . ' ' . $r . ' 0 ' . $large_arc . ' 1 ' . $x2 . ' ' . $y2 . ' Z';
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
        __DIR__ . '/erinnerungen.php',
        __DIR__ . '/umsatz_chart.php',
    ];
}

function cmx_anyboard_load_widgets(): array
{
    $widgets = [];
    $GLOBALS['cmx_anyboard_widget_context'] = true;
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

function cmx_anyboard_pie_response(): \WP_REST_Response
{
    $year = (int) current_time('Y');
    $rechnungen = cmx_anyboard_rechnungen_stats($year);
    $ausgaben = cmx_anyboard_ausgaben_stats($year);

    $svg = cmx_anyboard_pie_svg(
        (float) ($rechnungen['paid_sum'] ?? 0.0),
        (float) ($ausgaben['paid_sum'] ?? 0.0)
    );

    $response = new \WP_REST_Response($svg, 200);
    $response->header('Content-Type', 'image/svg+xml; charset=utf-8');
    return $response;
}

function cmx_anyboard_data_response(): \WP_REST_Response
{
    $query_user = isset($_GET['user']) ? (string) $_GET['user'] : '';
    $query_pass = isset($_GET['pw']) ? (string) $_GET['pw'] : '';
    $kontakte = cmx_anyboard_count_posts('kontakte');
    $artikel = cmx_anyboard_count_sellable_artikel();
    $projekte = cmx_anyboard_count_active_projects();
    $dokumente = cmx_anyboard_count_posts('dokumente');
    $year = (int) current_time('Y');
    $rechnungen = cmx_anyboard_rechnungen_stats($year);
    $angebote = cmx_anyboard_angebote_stats($year);
    $ausgaben = cmx_anyboard_ausgaben_stats($year);
    $umsatz = cmx_anyboard_umsatz_series($year);
    $erinnerungen = cmx_anyboard_erinnerungen_rows(5);

    $sum_label = number_format((float) ($rechnungen['sum_total'] ?? 0.0), 2, '.', "'");
    $sum_angebote = number_format((float) ($angebote['sum_total'] ?? 0.0), 2, '.', "'");
    $rechnungen_paid_label = number_format((float) ($rechnungen['paid_sum'] ?? 0.0), 2, '.', "'");
    $rechnungen_open_label = number_format((float) ($rechnungen['open_sum'] ?? 0.0), 2, '.', "'");
    $angebote_paid_label = number_format((float) ($angebote['paid_sum'] ?? 0.0), 2, '.', "'");
    $angebote_open_label = number_format((float) ($angebote['open_sum'] ?? 0.0), 2, '.', "'");
    $ausgaben_paid = (float) ($ausgaben['paid_sum'] ?? 0.0);
    $ausgaben_open = (float) ($ausgaben['open_sum'] ?? 0.0);
    $ausgaben_diff = $ausgaben_paid - $ausgaben_open;
    $ausgaben_paid_label = number_format($ausgaben_paid, 2, '.', "'");
    $ausgaben_open_label = number_format($ausgaben_open, 2, '.', "'");
    $ausgaben_diff_label = number_format($ausgaben_diff, 2, '.', "'");

    $bewegungen = [
        [
            'label' => 'Rechnungen',
            'green' => $rechnungen_paid_label,
            'red' => $rechnungen_open_label,
            'sum' => $sum_label,
        ],
        [
            'label' => 'Angebote',
            'green' => $angebote_paid_label,
            'red' => $angebote_open_label,
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
        'erinnerungen' => $erinnerungen,
        'umsatz_breakdown' => [
            [
                'label' => 'Rechnungen',
                'value' => round((float) ($rechnungen['paid_sum'] ?? 0.0), 2),
            ],
            [
                'label' => 'Ausgaben',
                'value' => round((float) ($ausgaben['paid_sum'] ?? 0.0), 2),
            ],
        ],
        'umsatz_pie_url' => $pie_url,
    ];

    $snapshot = md5(json_encode($data));
    $previous = get_transient('cmx_anyboard_snapshot');
    $changed = $previous !== $snapshot && $previous !== false;
    set_transient('cmx_anyboard_snapshot', $snapshot, 2 * HOUR_IN_SECONDS);

    return rest_ensure_response([
        'data' => $data + ['changed' => $changed],
    ]);
}

if (!defined('CMX_ANYBOARD_SKIP_BOOT')) {
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
            register_rest_route('cmx-misbuero/v1', '/anyboard-pie', [
                'methods' => 'GET',
                'permission_callback' => __NAMESPACE__ . '\\cmx_anyboard_permission',
                'callback' => __NAMESPACE__ . '\\cmx_anyboard_pie_response',
            ]);
        });

        add_filter('rest_pre_serve_request', function ($served, $result, $request, $server) {
            if ($request->get_route() !== '/cmx-misbuero/v1/anyboard-pie') {
                return $served;
            }

            if ($result instanceof \WP_REST_Response) {
                $data = $result->get_data();
                if (is_string($data)) {
                    $server->send_header('Content-Type', 'image/svg+xml; charset=utf-8');
                    echo $data;
                    return true;
                }
            }

            return $served;
        }, 10, 4);
    } else {
        cmx_anyboard_direct_response();
    }
}
