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

function cmx_anyboard_normalize_decimal(string $value): float
{
    $value = trim($value);
    $value = str_replace(["\xc2\xa0", ' ', "'"], '', $value);
    $value = (string) preg_replace('/[^\d,\.\+\-]/u', '', $value);
    if ($value === '' || $value === '+' || $value === '-') {
        return 0.0;
    }

    $sign = '';
    if ($value[0] === '+' || $value[0] === '-') {
        $sign = $value[0];
        $value = (string) substr($value, 1);
    }

    $value = str_replace(['+', '-'], '', $value);
    if ($value === '') {
        return 0.0;
    }

    $has_comma = strpos($value, ',') !== false;
    $has_dot = strpos($value, '.') !== false;

    if ($has_comma && $has_dot) {
        if (strrpos($value, ',') > strrpos($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }
    } elseif ($has_comma || $has_dot) {
        $separator = $has_comma ? ',' : '.';
        $parts = explode($separator, $value);
        $left_part = $parts[0] ?? '';
        $left_digits = ltrim($left_part, '+-');

        if (count($parts) > 2) {
            $value = implode('', $parts);
        } elseif (count($parts) === 2) {
            $right_part = $parts[1];
            $looks_thousands = preg_match('/^\d{3}$/', $right_part) && preg_match('/^\d{1,3}$/', $left_digits);
            if ($looks_thousands) {
                $value = $left_part . $right_part;
            } elseif ($separator === ',') {
                $value = $left_part . '.' . $right_part;
            }
        } elseif ($separator === ',') {
            $value = str_replace(',', '.', $value);
        }
    }

    $value = $sign . $value;
    return is_numeric($value) ? (float) $value : 0.0;
}

function cmx_anyboard_round_5rp(float $amount): float
{
    return round($amount * 20) / 20;
}

function cmx_anyboard_parse_discount(float $subtotal, $raw): float
{
    if ($raw === null || $raw === '') {
        return 0.0;
    }

    $txt = strtolower(trim((string) $raw));
    $base = abs($subtotal);
    if ($base <= 0.0) {
        return 0.0;
    }

    if (substr($txt, -1) === '%') {
        $discount = max(0.0, $base * (cmx_anyboard_normalize_decimal(substr($txt, 0, -1)) / 100));
    } else {
        $txt = (string) preg_replace('/\s*(chf|fr\.?)\s*/i', '', $txt);
        $discount = max(0.0, cmx_anyboard_normalize_decimal($txt));
    }

    if ($discount > $base) {
        $discount = $base;
    }

    return ($subtotal >= 0 ? 1 : -1) * $discount;
}

function cmx_anyboard_load_positionen(int $post_id): array
{
    $raw = get_post_meta($post_id, '_cmx_beleg_positionen', true);
    if (empty($raw)) {
        return [];
    }

    $positionen = maybe_unserialize($raw);
    if (is_string($positionen)) {
        $decoded = json_decode($positionen, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $positionen = $decoded;
        }
    }

    return is_array($positionen) ? $positionen : [];
}

function cmx_anyboard_has_positionen_data(array $positionen): bool
{
    foreach ($positionen as $position) {
        if (!is_array($position)) {
            continue;
        }
        if (!empty($position['artikel_id']) && (int) $position['artikel_id'] > 0) {
            return true;
        }
        $name = trim((string) ($position['artikel_name'] ?? $position['item'] ?? $position['title'] ?? ''));
        if ($name !== '') {
            return true;
        }
        $qty = trim((string) ($position['menge'] ?? ''));
        $price = trim((string) ($position['preis'] ?? ''));
        $discount = trim((string) ($position['rabatt'] ?? ''));
        if ($qty !== '' || $price !== '' || $discount !== '') {
            return true;
        }
    }

    return false;
}

function cmx_anyboard_beleg_total(int $post_id): float
{
    static $cache = [];
    if (isset($cache[$post_id])) {
        return $cache[$post_id];
    }

    $positionen = cmx_anyboard_load_positionen($post_id);
    $has_positions = cmx_anyboard_has_positionen_data($positionen);
    $manual_total_raw = (string) get_post_meta($post_id, '_cmx_beleg_summe_override', true);

    if (!$has_positions && $manual_total_raw !== '') {
        $cache[$post_id] = round(cmx_anyboard_normalize_decimal($manual_total_raw), 2);
        return $cache[$post_id];
    }

    $sum = 0.0;
    foreach ($positionen as $position) {
        if (!is_array($position)) {
            continue;
        }

        $qty = isset($position['menge']) ? cmx_anyboard_normalize_decimal((string) $position['menge']) : 0.0;
        $price = isset($position['preis']) ? cmx_anyboard_normalize_decimal((string) $position['preis']) : 0.0;
        $subtotal = $qty * $price;
        $discount = cmx_anyboard_parse_discount($subtotal, $position['rabatt'] ?? '');
        $sum += cmx_anyboard_round_5rp($subtotal - $discount);
    }

    $cache[$post_id] = $has_positions ? round($sum, 2) : 0.0;
    return $cache[$post_id];
}

function cmx_anyboard_meta_first_non_empty(int $post_id, array $keys): string
{
    foreach ($keys as $key) {
        $value = trim((string) get_post_meta($post_id, (string) $key, true));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function cmx_anyboard_normalize_date(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    if (preg_match('/^\d{8}$/', $value)) {
        return substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
    }

    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : '';
}

function cmx_anyboard_format_display_date(string $value): string
{
    $value = cmx_anyboard_normalize_date($value);
    if ($value === '') {
        return '';
    }

    $tz = function_exists('wp_timezone')
        ? wp_timezone()
        : new \DateTimeZone(date_default_timezone_get());
    $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $tz);

    return $dt ? wp_date('d.m.Y', $dt->getTimestamp(), $tz) : $value;
}

function cmx_anyboard_next_occurrence_ts(string $value): int
{
    $value = cmx_anyboard_normalize_date($value);
    if ($value === '') {
        return PHP_INT_MAX;
    }

    $tz = function_exists('wp_timezone')
        ? wp_timezone()
        : new \DateTimeZone(date_default_timezone_get());
    $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $tz);
    if (!$dt) {
        return PHP_INT_MAX;
    }

    $month = (int) $dt->format('m');
    $day = (int) $dt->format('d');
    $today = new \DateTimeImmutable('today', $tz);
    $year = (int) $today->format('Y');

    $candidate = \DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        sprintf('%04d-%02d-%02d', $year, $month, $day),
        $tz
    );
    if (!$candidate) {
        return PHP_INT_MAX;
    }

    if ((int) $candidate->format('m') !== $month || (int) $candidate->format('d') !== $day) {
        return PHP_INT_MAX;
    }

    if ($candidate < $today) {
        return PHP_INT_MAX;
    }

    return (int) $candidate->getTimestamp();
}

function cmx_anyboard_first_beleg_map(array $kontakt_ids): array
{
    $kontakt_ids = array_values(array_filter(array_map('intval', $kontakt_ids), static function (int $id): bool {
        return $id > 0;
    }));
    if ($kontakt_ids === []) {
        return [];
    }

    global $wpdb;
    if (!($wpdb instanceof \wpdb)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($kontakt_ids), '%d'));
    $sql = $wpdb->prepare(
        "SELECT CAST(pm.meta_value AS UNSIGNED) AS kontakt_id, DATE(MIN(p.post_date)) AS first_date
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key = %s
          AND CAST(pm.meta_value AS UNSIGNED) IN ($placeholders)
          AND p.post_type = 'belege'
          AND p.post_status NOT IN ('trash','auto-draft','inherit')
        GROUP BY CAST(pm.meta_value AS UNSIGNED)",
        array_merge(['_cmx_beleg_kontakt_id'], $kontakt_ids)
    );
    if (!is_string($sql) || $sql === '') {
        return [];
    }

    $rows = $wpdb->get_results($sql, ARRAY_A);
    if (!is_array($rows)) {
        return [];
    }

    $map = [];
    foreach ($rows as $row) {
        $kontakt_id = isset($row['kontakt_id']) ? (int) $row['kontakt_id'] : 0;
        $first_date = isset($row['first_date']) ? trim((string) $row['first_date']) : '';
        if ($kontakt_id > 0 && $first_date !== '') {
            $map[$kontakt_id] = $first_date;
        }
    }

    return $map;
}

function cmx_anyboard_latest_beleg_usage_map(array $kontakt_ids): array
{
    $kontakt_ids = array_values(array_filter(array_map('intval', $kontakt_ids), static function (int $id): bool {
        return $id > 0;
    }));
    if ($kontakt_ids === []) {
        return [];
    }

    global $wpdb;
    if (!($wpdb instanceof \wpdb)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($kontakt_ids), '%d'));
    $sql = $wpdb->prepare(
        "SELECT CAST(pm.meta_value AS UNSIGNED) AS kontakt_id, p.ID AS beleg_id
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key = %s
          AND CAST(pm.meta_value AS UNSIGNED) IN ($placeholders)
          AND p.post_type = 'belege'
          AND p.post_status NOT IN ('trash','auto-draft','inherit')
        ORDER BY CAST(pm.meta_value AS UNSIGNED) ASC, p.post_modified DESC, p.ID DESC",
        array_merge(['_cmx_beleg_kontakt_id'], $kontakt_ids)
    );
    if (!is_string($sql) || $sql === '') {
        return [];
    }

    $rows = $wpdb->get_results($sql, ARRAY_A);
    if (!is_array($rows)) {
        return [];
    }

    $map = [];
    foreach ($rows as $row) {
        $kontakt_id = isset($row['kontakt_id']) ? (int) $row['kontakt_id'] : 0;
        $beleg_id = isset($row['beleg_id']) ? (int) $row['beleg_id'] : 0;
        if ($kontakt_id <= 0 || $beleg_id <= 0 || isset($map[$kontakt_id])) {
            continue;
        }

        $ts = (int) get_post_modified_time('U', false, $beleg_id);
        if ($ts <= 0) {
            $ts = (int) get_post_time('U', false, $beleg_id);
        }

        $map[$kontakt_id] = [
            'beleg_id' => $beleg_id,
            'ts' => $ts,
        ];
    }

    return $map;
}

function cmx_anyboard_normalize_beleg_type_slug(string $type_slug): string
{
    $type_slug = strtolower(sanitize_key($type_slug));
    if ($type_slug === '') {
        return '';
    }

    if (function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_normalize_type')) {
        return (string) cmxbu_beleg_export_normalize_type($type_slug);
    }

    $map = [
        'rechnungen' => 'rechnung',
        'quittungen' => 'quittung',
        'gutschriften' => 'gutschrift',
    ];

    return (string) ($map[$type_slug] ?? $type_slug);
}

function cmx_anyboard_beleg_type_label(string $type_slug): string
{
    $type_slug = cmx_anyboard_normalize_beleg_type_slug($type_slug);
    if ($type_slug === '') {
        return 'Beleg';
    }

    $labels = [
        'rechnung' => 'Rechnung',
        'quittung' => 'Quittung',
        'gutschrift' => 'Gutschrift',
        'lieferantenrechnung' => 'Lieferantenrechnung',
        'lieferantenquittung' => 'Lieferantenquittung',
    ];

    if (isset($labels[$type_slug])) {
        return $labels[$type_slug];
    }

    return ucfirst(str_replace(['-', '_'], ' ', $type_slug));
}

function cmx_anyboard_beleg_usage_context(int $beleg_id): array
{
    $type_slug = '';
    $post = get_post($beleg_id);
    if ($post instanceof \WP_Post && function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_raw_type')) {
        $type_slug = (string) cmxbu_beleg_export_raw_type($post);
    }

    if ($type_slug === '' && function_exists(__NAMESPACE__ . '\\cmx_belege_kategorie_taxonomy')) {
        $taxonomy = (string) cmx_belege_kategorie_taxonomy();
        if ($taxonomy !== '' && taxonomy_exists($taxonomy)) {
            $terms = wp_get_post_terms($beleg_id, $taxonomy, ['fields' => 'slugs']);
            if (!is_wp_error($terms) && !empty($terms[0])) {
                $type_slug = (string) $terms[0];
            }
        }
    }

    $type_slug = cmx_anyboard_normalize_beleg_type_slug($type_slug);

    $direction_key = defined(__NAMESPACE__ . '\\CMX_BELEG_META_RICHTUNG')
        ? CMX_BELEG_META_RICHTUNG
        : '_cmx_beleg_richtung';
    $direction_key_alt = ltrim($direction_key, '_');
    $direction_raw = sanitize_key((string) cmx_anyboard_meta_first_non_empty($beleg_id, [$direction_key, $direction_key_alt]));

    if (function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_normalize_richtung')) {
        $direction = (string) cmxbu_beleg_export_normalize_richtung($direction_raw);
    } elseif (in_array($direction_raw, ['ausgabe', 'ausgaben', 'expense', 'expenses', 'eingang'], true)) {
        $direction = 'eingang';
    } elseif (in_array($direction_raw, ['einnahme', 'einnahmen', 'income', 'revenues', 'ausgang'], true)) {
        $direction = 'ausgang';
    } else {
        $direction = $direction_raw;
    }

    $side_map = [];
    if ($type_slug !== '' && function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_direction_side_map')) {
        $side_map = (array) cmxbu_beleg_export_direction_side_map($type_slug);
    }
    if ($side_map === []) {
        if (in_array($type_slug, ['rechnung', 'quittung', 'lieferantenrechnung', 'lieferantenquittung'], true)) {
            $side_map = ['ausgang' => 'income', 'eingang' => 'expense'];
        } elseif ($type_slug === 'gutschrift') {
            $side_map = ['ausgang' => 'expense', 'eingang' => 'income'];
        }
    }

    $side = (string) ($side_map[$direction] ?? '');
    if ($side !== 'income' && $side !== 'expense') {
        if ($direction === 'ausgang') {
            $side = 'income';
        } elseif ($direction === 'eingang') {
            $side = 'expense';
        } else {
            $side = '';
        }
    }

    return [
        'type_slug' => $type_slug,
        'type_label' => cmx_anyboard_beleg_type_label($type_slug),
        'side' => $side,
    ];
}

function cmx_anyboard_format_display_timestamp(int $timestamp): string
{
    if ($timestamp <= 0) {
        return '';
    }

    $tz = function_exists('wp_timezone')
        ? wp_timezone()
        : new \DateTimeZone(date_default_timezone_get());

    return wp_date('d.m.Y H:i', $timestamp, $tz);
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

    $query = new \WP_Query([
        'post_type' => 'belege',
        'post_status' => ['publish', 'private'],
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'fields' => 'ids',
        'update_post_meta_cache' => true,
        'update_post_term_cache' => false,
        'tax_query' => [
            [
                'taxonomy' => 'belege_kategorien',
                'field' => 'slug',
                'terms' => ['rechnung'],
                'operator' => 'IN',
            ],
        ],
    ]);

    $sum_total = 0.0;
    $paid_sum = 0.0;
    $open_sum = 0.0;

    foreach ((array) $query->posts as $bid) {
        $bid = (int) $bid;
        $total = cmx_anyboard_beleg_total($bid);

        $paid_raw = cmx_anyboard_meta_first_non_empty($bid, [$paid_key, $paid_key_alt]);
        $paid_date = cmx_anyboard_normalize_date($paid_raw);
        if ($paid_date !== '' && (int) substr($paid_date, 0, 4) === $year) {
            $paid_sum += $total;
            $sum_total += $total;
            continue;
        }

        if ($paid_raw !== '') {
            continue;
        }

        $rng_raw = cmx_anyboard_meta_first_non_empty($bid, [$rng_key, $rng_key_alt]);
        $rng_date = cmx_anyboard_normalize_date($rng_raw);
        if ($rng_date !== '' && (int) substr($rng_date, 0, 4) === $year) {
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

function cmx_anyboard_belege_term_stats(int $year, array $terms): array
{
    if (!class_exists('\\WP_Query')) {
        return ['paid_sum' => 0.0, 'open_sum' => 0.0, 'sum_total' => 0.0];
    }

    $terms = array_values(array_filter(array_map('strval', $terms), static function (string $term): bool {
        return $term !== '';
    }));
    if ($terms === []) {
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

    $query = new \WP_Query([
        'post_type' => 'belege',
        'post_status' => ['publish', 'private'],
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'fields' => 'ids',
        'update_post_meta_cache' => true,
        'update_post_term_cache' => false,
        'tax_query' => [
            [
                'taxonomy' => 'belege_kategorien',
                'field' => 'slug',
                'terms' => $terms,
                'operator' => 'IN',
            ],
        ],
    ]);

    $sum_total = 0.0;
    $paid_sum = 0.0;
    $open_sum = 0.0;

    foreach ((array) $query->posts as $bid) {
        $bid = (int) $bid;
        $total = cmx_anyboard_beleg_total($bid);

        $paid_raw = cmx_anyboard_meta_first_non_empty($bid, [$paid_key, $paid_key_alt]);
        $paid_date = cmx_anyboard_normalize_date($paid_raw);
        if ($paid_date !== '' && (int) substr($paid_date, 0, 4) === $year) {
            $paid_sum += $total;
            $sum_total += $total;
            continue;
        }

        if ($paid_raw !== '') {
            continue;
        }

        $rng_raw = cmx_anyboard_meta_first_non_empty($bid, [$rng_key, $rng_key_alt]);
        $rng_date = cmx_anyboard_normalize_date($rng_raw);
        if ($rng_date !== '' && (int) substr($rng_date, 0, 4) === $year) {
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

function cmx_anyboard_quittungen_stats(int $year): array
{
    return cmx_anyboard_belege_term_stats($year, ['quittung', 'quittungen']);
}

function cmx_anyboard_gutschriften_stats(int $year): array
{
    return cmx_anyboard_belege_term_stats($year, ['gutschrift', 'gutschriften']);
}

function cmx_anyboard_lieferanten_stats(int $year): array
{
    return cmx_anyboard_belege_term_stats($year, ['lieferantenrechnung', 'lieferantenrechnungen']);
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
    foreach ($paid_query->posts as $bid) {
        $total = cmx_anyboard_beleg_total((int) $bid);
        $paid_sum += $total;
        $sum_total += $total;
    }
    foreach ($open_query->posts as $bid) {
        $total = cmx_anyboard_beleg_total((int) $bid);
        $open_sum += $total;
        $sum_total += $total;
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
        $total = cmx_anyboard_beleg_total((int) $bid);
        $month_totals[$month] += $total;
    }

    $labels = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
    $series = [];
    for ($i = 1; $i <= 12; $i++) {
        $raw_value = $month_totals[$i];
        $series[] = [
            'month' => $labels[$i - 1],
            'value' => round($raw_value, 2),
            'label' => number_format($raw_value, 2, '.', "'"),
        ];
    }

    return $series;
}

function cmx_anyboard_kontakt_daten_rows(int $limit = 10): array
{
    if (!class_exists('\\WP_Query')) {
        return [];
    }

    $date_key = defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_DATUM') ? CMX_KONTAKTE_META_DATUM : '_cmx_kontakte_datum';
    $priv_key = defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_PRIVAT') ? CMX_KONTAKTE_META_PRIVAT : '_cmx_kontakte_privat';
    $firm_key = defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_FIRMENGRUENDUNG') ? CMX_KONTAKTE_META_FIRMENGRUENDUNG : '_cmx_kontakte_firmengruendung';
    $birth_key = defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_GEBURTSDATUM') ? CMX_KONTAKTE_META_GEBURTSDATUM : '_cmx_kontakte_geburtsdatum';
    $kunde_seit_key = defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_KUNDE_SEIT') ? CMX_KONTAKTE_META_KUNDE_SEIT : '_cmx_kontakte_kunde_seit';

    $q = new \WP_Query([
        'post_type' => 'kontakte',
        'post_status' => ['publish', 'private'],
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ]);

    $rows = [];
    $first_beleg_map = cmx_anyboard_first_beleg_map((array) $q->posts);

    foreach ((array) $q->posts as $pid) {
        $pid = (int) $pid;
        if ($pid <= 0) {
            continue;
        }

        $title = html_entity_decode(wp_strip_all_tags((string) get_the_title($pid)), ENT_QUOTES, 'UTF-8');
        $legacy = (string) get_post_meta($pid, $date_key, true);
        $firm = (string) get_post_meta($pid, $firm_key, true);
        $birth = (string) get_post_meta($pid, $birth_key, true);
        $kunde_seit_saved = (string) get_post_meta($pid, $kunde_seit_key, true);
        $kunde_seit = trim($kunde_seit_saved) !== '' ? trim($kunde_seit_saved) : ((string) ($first_beleg_map[$pid] ?? ''));
        $email = function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_email')
            ? (string) cmx_kommunikation_primary_email($pid)
            : cmx_anyboard_meta_first_non_empty($pid, ['_cmx_email_1', 'cmx_email_1', 'email_1', 'e_mail_1', 'kontakt_email', 'email', 'e_mail', 'mail']);
        $phone = function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_phone')
            ? (string) cmx_kommunikation_primary_phone($pid)
            : cmx_anyboard_meta_first_non_empty($pid, ['_cmx_telefon_1', 'cmx_telefon_1', 'telefon_1', 'tel_1', 'phone_1', 'telefon', 'tel', 'phone']);

        $privat = (bool) get_post_meta($pid, $priv_key, true);
        if (!$privat) {
            $privat = (bool) get_post_meta($pid, '_cmx_privat', true);
        }

        if ($legacy !== '') {
            if ($privat && $birth === '') {
                $birth = $legacy;
            }
            if (!$privat && $firm === '') {
                $firm = $legacy;
            }
            if ($firm === '' && $birth === '') {
                $firm = $legacy;
            }
        }

        $entries = [];
        if ($firm !== '') {
            $entries[] = ['label' => 'Firmengründung', 'raw' => $firm];
        }
        if ($birth !== '') {
            $entries[] = ['label' => 'Geburtsdatum', 'raw' => $birth];
        }
        if ($kunde_seit !== '') {
            $entries[] = ['label' => 'Kunde seit', 'raw' => $kunde_seit];
        }
        if ($entries === [] && $legacy !== '') {
            $entries[] = ['label' => $privat ? 'Geburtsdatum' : 'Firmengründung', 'raw' => $legacy];
        }

        foreach ($entries as $entry) {
            $raw = (string) ($entry['raw'] ?? '');
            $sort_ts = cmx_anyboard_next_occurrence_ts($raw);
            if ($sort_ts === PHP_INT_MAX) {
                continue;
            }

            $rows[] = [
                'name' => $title,
                'date' => cmx_anyboard_format_display_date($raw),
                'type' => (string) ($entry['label'] ?? ''),
                'email' => $email,
                'phone' => $phone,
                '_sort_ts' => $sort_ts,
            ];
        }
    }

    usort($rows, static function (array $a, array $b): int {
        $sort_a = (int) ($a['_sort_ts'] ?? PHP_INT_MAX);
        $sort_b = (int) ($b['_sort_ts'] ?? PHP_INT_MAX);
        if ($sort_a !== $sort_b) {
            return $sort_a <=> $sort_b;
        }
        $cmp = strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcasecmp((string) ($a['type'] ?? ''), (string) ($b['type'] ?? ''));
    });

    $rows = array_slice($rows, 0, $limit);

    foreach ($rows as &$row) {
        unset($row['_sort_ts']);
    }
    unset($row);

    while (count($rows) < $limit) {
        $rows[] = ['name' => '', 'date' => '', 'type' => '', 'email' => '', 'phone' => ''];
    }

    return $rows;
}

function cmx_anyboard_erinnerungen_rows(int $limit = 5): array
{
    $rows = cmx_anyboard_kontakt_daten_rows($limit);
    $items = [];

    foreach ($rows as $row) {
        $items[] = [
            'name' => (string) ($row['name'] ?? ''),
            'date' => (string) ($row['date'] ?? ''),
            'type' => (string) ($row['type'] ?? ''),
        ];
    }

    return $items;
}

function cmx_anyboard_kontakte_letzte_nutzung_rows(int $limit = 10): array
{
    if (!class_exists('\\WP_Query')) {
        return [];
    }

    $q = new \WP_Query([
        'post_type' => 'kontakte',
        'post_status' => ['publish', 'private'],
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ]);

    $usage_map = cmx_anyboard_latest_beleg_usage_map((array) $q->posts);
    $rows = [];

    foreach ((array) $q->posts as $pid) {
        $pid = (int) $pid;
        if ($pid <= 0) {
            continue;
        }

        $usage = $usage_map[$pid] ?? null;
        $beleg_ts = (int) ($usage['ts'] ?? 0);
        $beleg_id = (int) ($usage['beleg_id'] ?? 0);
        if ($beleg_id <= 0 || $beleg_ts <= 0) {
            continue;
        }

        $name = html_entity_decode(wp_strip_all_tags((string) get_the_title($pid)), ENT_QUOTES, 'UTF-8');
        $beleg = cmx_anyboard_beleg_usage_context($beleg_id);
        $amount_value = (float) cmx_anyboard_beleg_total($beleg_id);
        if (($beleg['side'] ?? '') === 'expense') {
            $amount_value = -abs($amount_value);
        } elseif (($beleg['side'] ?? '') === 'income') {
            $amount_value = abs($amount_value);
        }

        $amount_label = number_format(abs($amount_value), 2, '.', "'");

        $rows[] = [
            'name' => $name,
            'date' => cmx_anyboard_format_display_timestamp($beleg_ts),
            'source' => (string) ($beleg['type_label'] ?? 'Beleg'),
            'amount' => $amount_label,
            'amount_value' => round($amount_value, 2),
            '_sort_ts' => $beleg_ts,
        ];
    }

    usort($rows, static function (array $a, array $b): int {
        $sort_a = (int) ($a['_sort_ts'] ?? 0);
        $sort_b = (int) ($b['_sort_ts'] ?? 0);
        if ($sort_a !== $sort_b) {
            return $sort_b <=> $sort_a;
        }

        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    $rows = array_slice($rows, 0, $limit);

    foreach ($rows as &$row) {
        unset($row['_sort_ts']);
    }
    unset($row);

    while (count($rows) < $limit) {
        $rows[] = ['name' => '', 'date' => '', 'source' => '', 'amount' => '', 'amount_value' => 0];
    }

    return $rows;
}

function cmx_anyboard_kontakte_info_summary(array $rows): array
{
    foreach ($rows as $row) {
        $name = trim((string) ($row['name'] ?? ''));
        $date = trim((string) ($row['date'] ?? ''));
        $type = trim((string) ($row['type'] ?? ''));
        if ($name === '' && $date === '' && $type === '') {
            continue;
        }

        return [
            'title' => 'Erinnerungen',
            'value' => $date !== '' ? $date : '-',
            'subtitle' => $name !== '' ? $name : $type,
        ];
    }

    return [
        'title' => 'Erinnerungen',
        'value' => '-',
        'subtitle' => 'Keine Einträge',
    ];
}

function cmx_anyboard_swiss_amount(float $value, int $decimals = 2): string
{
    return number_format($value, $decimals, '.', "'");
}

function cmx_anyboard_svg_escape(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function cmx_anyboard_nice_step(float $value): float
{
    if ($value <= 0.0) {
        return 1.0;
    }

    $power = pow(10, floor(log10($value)));
    $normalized = $value / $power;

    if ($normalized <= 1.0) {
        $nice = 1.0;
    } elseif ($normalized <= 2.0) {
        $nice = 2.0;
    } elseif ($normalized <= 5.0) {
        $nice = 5.0;
    } else {
        $nice = 10.0;
    }

    return $nice * $power;
}

function cmx_anyboard_kontakte_letzte_nutzung_svg(array $rows): string
{
    $width = 1200;
    $height = 720;
    $title_y = 28;
    $header_y = 64;
    $row_start_y = 106;
    $row_height = 55;
    $left = 28;
    $col_name = 28;
    $col_date = 650;
    $col_source = 900;
    $col_amount = 1145;

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">';
    $svg .= '<rect width="100%" height="100%" fill="transparent"/>';
    $svg .= '<text x="' . $left . '" y="' . $title_y . '" fill="#ffffff" font-family="Arial, sans-serif" font-size="16" font-weight="700">Zuletzt aktiv</text>';
    $svg .= '<text x="' . $col_name . '" y="' . $header_y . '" fill="#9fb0d2" font-family="Arial, sans-serif" font-size="13">Kontakt</text>';
    $svg .= '<text x="' . $col_date . '" y="' . $header_y . '" fill="#9fb0d2" font-family="Arial, sans-serif" font-size="13">Zuletzt</text>';
    $svg .= '<text x="' . $col_source . '" y="' . $header_y . '" fill="#9fb0d2" font-family="Arial, sans-serif" font-size="13">Beleg</text>';
    $svg .= '<text x="' . $col_amount . '" y="' . $header_y . '" text-anchor="end" fill="#9fb0d2" font-family="Arial, sans-serif" font-size="13">Betrag</text>';
    $svg .= '<line x1="' . $left . '" y1="78" x2="1172" y2="78" stroke="rgba(233,238,247,0.18)" stroke-width="1"/>';

    $rendered = 0;
    foreach ($rows as $row) {
        $name = trim((string) ($row['name'] ?? ''));
        $date = trim((string) ($row['date'] ?? ''));
        $source = trim((string) ($row['source'] ?? ''));
        $amount = trim((string) ($row['amount'] ?? ''));
        $amount_value = (float) ($row['amount_value'] ?? 0.0);

        if ($name === '' && $date === '' && $source === '' && $amount === '') {
            continue;
        }

        $y = $row_start_y + ($rendered * $row_height);
        $amount_color = '#ffffff';
        if ($amount_value < 0.0) {
            $amount_color = '#ff5c7a';
        } elseif ($amount_value > 0.0) {
            $amount_color = '#2ce5a0';
        }

        $svg .= '<line x1="' . $left . '" y1="' . ($y + 18) . '" x2="1172" y2="' . ($y + 18) . '" stroke="rgba(233,238,247,0.08)" stroke-width="1"/>';
        $svg .= '<text x="' . $col_name . '" y="' . $y . '" fill="#ffffff" font-family="Arial, sans-serif" font-size="14" font-weight="700">'
            . cmx_anyboard_svg_escape($name)
            . '</text>';
        $svg .= '<text x="' . $col_date . '" y="' . $y . '" fill="#e6edff" font-family="Arial, sans-serif" font-size="14">'
            . cmx_anyboard_svg_escape($date)
            . '</text>';
        $svg .= '<text x="' . $col_source . '" y="' . $y . '" fill="#8ea0c5" font-family="Arial, sans-serif" font-size="14">'
            . cmx_anyboard_svg_escape($source)
            . '</text>';
        $svg .= '<text x="' . $col_amount . '" y="' . $y . '" text-anchor="end" fill="' . $amount_color . '" font-family="Arial, sans-serif" font-size="14" font-weight="700">'
            . cmx_anyboard_svg_escape($amount)
            . '</text>';

        $rendered++;
        if ($rendered >= 10) {
            break;
        }
    }

    if ($rendered === 0) {
        $svg .= '<text x="' . $left . '" y="122" fill="#9fb0d2" font-family="Arial, sans-serif" font-size="15">Keine Einträge</text>';
    }

    $svg .= '</svg>';

    return $svg;
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
        __DIR__ . '/favicon.php',
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

function cmx_anyboard_kontakte_widget_files(): array
{
    return [
        __DIR__ . '/kontakt_daten.php',
        __DIR__ . '/kontakte_letzte_nutzung.php',
    ];
}

function cmx_anyboard_widget_id_from_file(string $file): string
{
    $id = strtolower((string) pathinfo($file, PATHINFO_FILENAME));
    $id = (string) preg_replace('/[^a-z0-9_]+/', '_', $id);
    $id = trim($id, '_');

    return $id !== '' ? $id : 'widget';
}

function cmx_anyboard_load_widget_list(array $files): array
{
    $widgets = [];
    $GLOBALS['cmx_anyboard_widget_context'] = true;
    foreach (array_values($files) as $index => $file) {
        if (!is_file($file)) {
            continue;
        }
        $widget = include $file;
        if (is_array($widget)) {
            if (empty($widget['id'])) {
                $widget['id'] = cmx_anyboard_widget_id_from_file($file);
            }
            if (empty($widget['position'])) {
                $widget['position'] = $index + 1;
            }
            $widgets[] = $widget;
        }
    }
    return $widgets;
}

function cmx_anyboard_load_widgets(): array
{
    return cmx_anyboard_load_widget_list(cmx_anyboard_widget_files());
}

function cmx_anyboard_load_kontakte_widgets(): array
{
    return cmx_anyboard_load_widget_list(cmx_anyboard_kontakte_widget_files());
}

function cmx_anyboard_dashboard_files(): array
{
    return [
        __DIR__ . '/kachel_cockpit.php',
        __DIR__ . '/kachel_kontakte.php',
    ];
}

function cmx_anyboard_load_dashboards(): array
{
    $dashboards = [];
    foreach (cmx_anyboard_dashboard_files() as $file) {
        if (!is_file($file)) {
            continue;
        }
        $dashboard = include $file;
        if (is_array($dashboard)) {
            $dashboards[] = $dashboard;
        }
    }

    return $dashboards;
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
        'dashboards' => cmx_anyboard_load_dashboards(),
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

function cmx_anyboard_kontakte_letzte_nutzung_response(): \WP_REST_Response
{
    $svg = cmx_anyboard_kontakte_letzte_nutzung_svg(cmx_anyboard_kontakte_letzte_nutzung_rows(10));

    $response = new \WP_REST_Response($svg, 200);
    $response->header('Content-Type', 'image/svg+xml; charset=utf-8');
    return $response;
}

function cmx_anyboard_data_response(): \WP_REST_Response
{
    $query_user = isset($_GET['user']) ? (string) $_GET['user'] : '';
    $query_pass = isset($_GET['pw']) ? (string) $_GET['pw'] : '';
    $year = (int) current_time('Y');
    $cache_key = 'cmx_anyboard_data_' . $year;
    $data = get_transient($cache_key);

    if (!is_array($data)) {
        $kontakte = cmx_anyboard_count_posts('kontakte');
        $artikel = cmx_anyboard_count_sellable_artikel();
        $projekte = cmx_anyboard_count_active_projects();
        $dokumente = cmx_anyboard_count_posts('dokumente');
        $rechnungen = cmx_anyboard_rechnungen_stats($year);
        $angebote = cmx_anyboard_angebote_stats($year);
        $quittungen = cmx_anyboard_quittungen_stats($year);
        $gutschriften = cmx_anyboard_gutschriften_stats($year);
        $lieferanten = cmx_anyboard_lieferanten_stats($year);
        $ausgaben = cmx_anyboard_ausgaben_stats($year);
        $umsatz = cmx_anyboard_umsatz_series($year);
        $kontakt_daten = cmx_anyboard_kontakt_daten_rows(10);
        $kontakte_letzte_nutzung = cmx_anyboard_kontakte_letzte_nutzung_rows(10);
        $erinnerungen = cmx_anyboard_erinnerungen_rows(5);
        $kontakte_info = cmx_anyboard_kontakte_info_summary($kontakt_daten);

        $sum_label = number_format((float) ($rechnungen['sum_total'] ?? 0.0), 2, '.', "'");
        $sum_angebote = number_format((float) ($angebote['sum_total'] ?? 0.0), 2, '.', "'");
        $rechnungen_paid_label = number_format((float) ($rechnungen['paid_sum'] ?? 0.0), 2, '.', "'");
        $rechnungen_open_label = number_format((float) ($rechnungen['open_sum'] ?? 0.0), 2, '.', "'");
        $angebote_paid_label = number_format((float) ($angebote['paid_sum'] ?? 0.0), 2, '.', "'");
        $angebote_open_label = number_format((float) ($angebote['open_sum'] ?? 0.0), 2, '.', "'");
        $quittungen_paid_label = number_format((float) ($quittungen['paid_sum'] ?? 0.0), 2, '.', "'");
        $quittungen_open_label = number_format((float) ($quittungen['open_sum'] ?? 0.0), 2, '.', "'");
        $quittungen_sum_label = number_format((float) ($quittungen['sum_total'] ?? 0.0), 2, '.', "'");
        $gutschriften_paid_label = number_format((float) ($gutschriften['paid_sum'] ?? 0.0), 2, '.', "'");
        $gutschriften_open_label = number_format((float) ($gutschriften['open_sum'] ?? 0.0), 2, '.', "'");
        $gutschriften_sum_label = number_format((float) ($gutschriften['sum_total'] ?? 0.0), 2, '.', "'");
        $lieferanten_paid_label = number_format((float) ($lieferanten['paid_sum'] ?? 0.0), 2, '.', "'");
        $lieferanten_open_label = number_format((float) ($lieferanten['open_sum'] ?? 0.0), 2, '.', "'");
        $lieferanten_sum_label = number_format((float) ($lieferanten['sum_total'] ?? 0.0), 2, '.', "'");
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
                'label' => 'Quittungen',
                'green' => $quittungen_paid_label,
                'red' => $quittungen_open_label,
                'sum' => $quittungen_sum_label,
            ],
            [
                'label' => 'Gutschriften',
                'green' => $gutschriften_paid_label,
                'red' => $gutschriften_open_label,
                'sum' => $gutschriften_sum_label,
            ],
            [
                'label' => 'Lieferanten',
                'green' => $lieferanten_paid_label,
                'red' => $lieferanten_open_label,
                'sum' => $lieferanten_sum_label,
            ],
        ];

        $data = [
            'kontakte' => $kontakte,
            'artikel' => $artikel,
            'projekte' => $projekte,
            'dokumente' => $dokumente,
            'bewegungen' => $bewegungen,
            'umsatz' => $umsatz,
            'umsatz_year' => $year,
            'kontakte_info' => $kontakte_info,
            'kontakt_daten' => $kontakt_daten,
            'kontakte_letzte_nutzung' => $kontakte_letzte_nutzung,
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
        ];

        set_transient($cache_key, $data, MINUTE_IN_SECONDS);
    }

    $pie_url = function_exists('home_url')
        ? home_url('/wp-json/cmx-misbuero/v1/anyboard-pie')
        : '';
    $kontakte_letzte_nutzung_url = function_exists('home_url')
        ? home_url('/wp-json/cmx-misbuero/v1/anyboard-kontakte-aktiv')
        : '';
    if ($pie_url !== '') {
        $pie_args = [];
        if ($query_user !== '') {
            $pie_args['user'] = $query_user;
        }
        if ($query_pass !== '') {
            $pie_args['pw'] = $query_pass;
        }
        if (!empty($pie_args)) {
            $pie_url = add_query_arg($pie_args, $pie_url);
        }
    }
    if ($kontakte_letzte_nutzung_url !== '') {
        $kontakte_args = [];
        if ($query_user !== '') {
            $kontakte_args['user'] = $query_user;
        }
        if ($query_pass !== '') {
            $kontakte_args['pw'] = $query_pass;
        }
        if (!empty($kontakte_args)) {
            $kontakte_letzte_nutzung_url = add_query_arg($kontakte_args, $kontakte_letzte_nutzung_url);
        }
    }

    $response_data = $data + [
        'kontakte_letzte_nutzung_url' => $kontakte_letzte_nutzung_url,
        'umsatz_pie_url' => $pie_url,
    ];

    $snapshot = md5(json_encode($data));
    $previous = get_transient('cmx_anyboard_snapshot');
    $changed = $previous !== $snapshot && $previous !== false;
    set_transient('cmx_anyboard_snapshot', $snapshot, 2 * HOUR_IN_SECONDS);

    return rest_ensure_response([
        'data' => $response_data + ['changed' => $changed],
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
            register_rest_route('cmx-misbuero/v1', '/anyboard-kontakte-aktiv', [
                'methods' => 'GET',
                'permission_callback' => __NAMESPACE__ . '\\cmx_anyboard_permission',
                'callback' => __NAMESPACE__ . '\\cmx_anyboard_kontakte_letzte_nutzung_response',
            ]);
        });

        add_filter('rest_pre_serve_request', function ($served, $result, $request, $server) {
            if (!in_array($request->get_route(), [
                '/cmx-misbuero/v1/anyboard-pie',
                '/cmx-misbuero/v1/anyboard-kontakte-aktiv',
            ], true)) {
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
