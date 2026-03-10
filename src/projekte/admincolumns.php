<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * CPT: projekte – Admin-Liste & Meta-Box "Projektzeitraum"
 * - Spalten: Kunde, Kategorie, Beginn, Ende
 * - Sortierung: Kunde (meta_value_num), Beginn/Ende (DATE)
 * - Filter: Kategorie-Dropdown (Monats-Archiv entfernt)
 * - Meta-Box (SIDE): Beginn & Ende, Speicherung YYYY-MM-DD
 */

/** =========================
 *  Konfiguration
 * ========================= */
if (!defined('CMX_PROJEKT_CPT'))  define('CMX_PROJEKT_CPT',  'projekte');
if (!defined('CMX_KONTAKT_META')) define('CMX_KONTAKT_META', '_cmx_projekt_kontakt_id');
if (!defined('CMX_PROJEKT_TAX'))  define('CMX_PROJEKT_TAX', 'projekt_kategorie'); // '' = Auto-Detect

// Zeitraum-Meta
if (!defined('CMX_PROJ_BEG_META'))  define('CMX_PROJ_BEG_META',  '_cmx_projekt_beginn'); // YYYY-MM-DD
if (!defined('CMX_PROJ_END_META'))  define('CMX_PROJ_END_META',  '_cmx_projekt_ende');   // YYYY-MM-DD
if (!defined('CMX_PROJ_NONCE_KEY')) define('CMX_PROJ_NONCE_KEY', 'cmx_proj_zeitraum_nonce');
if (!defined('CMX_PROJ_UMSATZ_META')) define('CMX_PROJ_UMSATZ_META', '_cmx_projekt_umsatz_total');

/** =========================
 *  Helpers
 * ========================= */

/** Taxonomie für Projekte ermitteln (Preferenzen: feste Tax, sonst public+hierarchical, sonst public, sonst erste) */
function cmx_projekte_detect_taxonomy(): ?string {
	$cpt = CMX_PROJEKT_CPT;
	$preferred = trim((string) CMX_PROJEKT_TAX);

	if ($preferred !== '' && taxonomy_exists($preferred) && is_object_in_taxonomy($cpt, $preferred)) {
		return $preferred;
	}
	$taxes = get_object_taxonomies($cpt, 'objects');
	if (empty($taxes)) return null;

	foreach ($taxes as $slug => $obj) {
		if (!empty($obj->public) && !empty($obj->hierarchical)) return $slug;
	}
	foreach ($taxes as $slug => $obj) {
		if (!empty($obj->public)) return $slug;
	}
	foreach ($taxes as $slug => $obj) { return $slug; }
	return null;
}

/** Status-Taxonomie für Projekte ermitteln */
if (!function_exists(__NAMESPACE__ . '\cmx_projekte_detect_status_taxonomy')) {
	function cmx_projekte_detect_status_taxonomy(): ?string {
		if (function_exists(__NAMESPACE__ . '\cmx_projekte_status_tax')) {
			$tax = (string) cmx_projekte_status_tax();
			if ($tax !== '' && taxonomy_exists($tax) && is_object_in_taxonomy(CMX_PROJEKT_CPT, $tax)) {
				return $tax;
			}
		}

		if (defined(__NAMESPACE__ . '\TAX_PROJEKTE_STATUS')) {
			$tax = (string) TAX_PROJEKTE_STATUS;
			if ($tax !== '' && taxonomy_exists($tax) && is_object_in_taxonomy(CMX_PROJEKT_CPT, $tax)) {
				return $tax;
			}
		}

		foreach (['projekte_status', 'projekt_status', 'status'] as $candidate) {
			if (taxonomy_exists($candidate) && is_object_in_taxonomy(CMX_PROJEKT_CPT, $candidate)) {
				return $candidate;
			}
		}

		return null;
	}
}

/** Kunden-Optionen für Admin-Filter (nur tatsächlich verwendete Kontakt-IDs) */
if (!function_exists(__NAMESPACE__ . '\cmx_projekte_get_kunden_filter_options')) {
	function cmx_projekte_get_kunden_filter_options(): array {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT DISTINCT pm.meta_value
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = %s
			   AND p.post_type = %s
			   AND p.post_status IN ('publish','draft','pending','future','private')
			   AND pm.meta_value <> ''",
			CMX_KONTAKT_META,
			CMX_PROJEKT_CPT
		);

		$raw_ids = $wpdb->get_col($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if (empty($raw_ids) || !is_array($raw_ids)) return [];

		$ids = array_values(array_unique(array_filter(array_map('absint', $raw_ids))));
		if (empty($ids)) return [];

		$options = [];
		foreach ($ids as $id) {
			if ($id <= 0 || !get_post_status($id)) continue;
			$title = (string) get_the_title($id);
			$options[$id] = $title !== '' ? $title : ('#' . $id);
		}

		if (!empty($options)) {
			natcasesort($options);
		}
		return $options;
	}
}

/** Datum-Helpers (ISO prüfen/säubern + CH-Format) */
if (!function_exists(__NAMESPACE__ . '\cmx_proj_is_iso_date')) {
	function cmx_proj_is_iso_date($value) {
		return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
	}
}
if (!function_exists(__NAMESPACE__ . '\cmx_proj_sanitize_date')) {
	function cmx_proj_sanitize_date($value) {
		$value = trim((string)$value);
		if ($value === '') return '';
		if (!cmx_proj_is_iso_date($value)) return '';
		[$y,$m,$d] = array_map('intval', explode('-', $value));
		return checkdate($m, $d, $y) ? $value : '';
	}
}
if (!function_exists(__NAMESPACE__ . '\cmx_format_ch_date')) {
	function cmx_format_ch_date($yyyy_mm_dd) {
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$yyyy_mm_dd)) return '';
		$ts = strtotime($yyyy_mm_dd . ' 00:00:00');
		return $ts ? date('d.m.Y', $ts) : '';
	}
}

/** Umsatz-Helfer (für Admin-Spalte) */
if (!function_exists(__NAMESPACE__ . '\cmx_proj_decimal_to_float_fallback')) {
	function cmx_proj_decimal_to_float_fallback($value): float {
		$s = trim((string) $value);
		if ($s === '') return 0.0;
		$s = str_replace([" ", "'"], '', $s);
		$has_comma = strpos($s, ',') !== false;
		$has_dot   = strpos($s, '.') !== false;
		if ($has_comma && $has_dot) {
			if (strrpos($s, ',') > strrpos($s, '.')) {
				$s = str_replace('.', '', $s);
				$s = str_replace(',', '.', $s);
			} else {
				$s = str_replace(',', '', $s);
			}
		} elseif ($has_comma) {
			$s = str_replace(',', '.', $s);
		}
		return is_numeric($s) ? (float) $s : 0.0;
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_proj_calc_umsatz_total')) {
	function cmx_proj_calc_umsatz_total(int $post_id): float {
		$tasks_meta_key = defined(__NAMESPACE__ . '\CMX_PROJEKT_TASK_META')
			? CMX_PROJEKT_TASK_META
			: '_cmx_projekt_tasks';
		$vk_meta_key = defined(__NAMESPACE__ . '\CMX_ARTIKEL_META_VK')
			? CMX_ARTIKEL_META_VK
			: '_cmx_artikel_vk';

		$tasks = get_post_meta($post_id, $tasks_meta_key, true);
		if (!is_array($tasks) || empty($tasks)) return 0.0;

		static $artikel_vk_cache = [];
		$total = 0.0;

		foreach ($tasks as $row) {
			if (!is_array($row)) continue;

			$artikel_id = (int) ($row['artikel_id'] ?? 0);
			if ($artikel_id <= 0) continue;

			$dauer = function_exists(__NAMESPACE__ . '\cmx_projekt_decimal_to_float')
				? cmx_projekt_decimal_to_float($row['dauer'] ?? 0)
				: cmx_proj_decimal_to_float_fallback($row['dauer'] ?? 0);
			if ($dauer <= 0) continue;

			if (!array_key_exists($artikel_id, $artikel_vk_cache)) {
				$vk_raw = get_post_meta($artikel_id, $vk_meta_key, true);
				$artikel_vk_cache[$artikel_id] = function_exists(__NAMESPACE__ . '\cmx_projekt_decimal_to_float')
					? cmx_projekt_decimal_to_float($vk_raw)
					: cmx_proj_decimal_to_float_fallback($vk_raw);
			}

			$vk = (float) $artikel_vk_cache[$artikel_id];
			if ($vk <= 0) continue;

			$total += ($dauer * $vk);
		}

		return $total;
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_proj_format_chf')) {
	function cmx_proj_format_chf($value): string {
		$amount = (float) $value;
		if (function_exists(__NAMESPACE__ . '\cmx_projekt_format_swiss_number')) {
			return 'CHF ' . cmx_projekt_format_swiss_number($amount, 2);
		}
		return 'CHF ' . number_format($amount, 2, '.', "'");
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_proj_format_decimal_parts')) {
	function cmx_proj_format_decimal_parts($value): array {
		$formatted = function_exists(__NAMESPACE__ . '\cmx_projekt_format_swiss_number')
			? cmx_projekt_format_swiss_number((float) $value, 2)
			: number_format((float) $value, 2, '.', "'");
		$parts = explode('.', $formatted, 2);
		return [
			'int'  => (string) ($parts[0] ?? '0'),
			'sep'  => '.',
			'frac' => (string) ($parts[1] ?? '00'),
		];
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_proj_sync_umsatz_meta')) {
	function cmx_proj_sync_umsatz_meta(int $post_id): float {
		$total = cmx_proj_calc_umsatz_total($post_id);
		update_post_meta($post_id, CMX_PROJ_UMSATZ_META, (string) $total);
		return $total;
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_proj_prime_missing_umsatz_meta')) {
	function cmx_proj_prime_missing_umsatz_meta(): void {
		static $done = false;
		if ($done) return;
		$done = true;

		$ids = get_posts([
			'post_type'              => CMX_PROJEKT_CPT,
			'post_status'            => ['publish', 'draft', 'pending', 'future', 'private'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => true,
			'meta_query'             => [[
				'key'     => CMX_PROJ_UMSATZ_META,
				'compare' => 'NOT EXISTS',
			]],
		]);

		if (empty($ids) || !is_array($ids)) return;
		foreach ($ids as $id) {
			cmx_proj_sync_umsatz_meta((int) $id);
		}
	}
}

/** =========================
 *  Admin-Liste: Kunde & Kategorie
 * ========================= */

/** Spalten hinzufügen & Datum-Spalte entfernen */
add_filter("manage_" . CMX_PROJEKT_CPT . "_posts_columns", function(array $columns) {
	unset($columns['date']); // Standard-Datum ausblenden

	$new = [];
	foreach ($columns as $key => $label) {
		$new[$key] = $label;
		if ($key === 'title') {
			$new['cmx_kunde']     = __('Kunde', 'cmx');
			$new['cmx_kategorie'] = __('Kategorie', 'cmx');
			$new['cmx_status']    = __('Status', 'cmx');
		}
	}
	if (!isset($columns['title'])) {
		$new['cmx_kunde']     = __('Kunde', 'cmx');
		$new['cmx_kategorie'] = __('Kategorie', 'cmx');
		$new['cmx_status']    = __('Status', 'cmx');
	}
	return $new;
}, 20);

/** Spaltenwerte ausgeben */
add_action('manage_' . CMX_PROJEKT_CPT . '_posts_custom_column', function(string $column, int $post_id) {

	if ($column === 'cmx_kunde') {
		$kontakt_id = (int) get_post_meta($post_id, CMX_KONTAKT_META, true);
		if ($kontakt_id > 0 && get_post_status($kontakt_id)) {
			$title = get_the_title($kontakt_id);
			$link  = get_edit_post_link($kontakt_id, '');
			echo $link
				? '<a href="' . esc_url($link) . '">' . esc_html($title) . '</a>'
				: esc_html($title);
		} else {
			echo '';
		}
		return;
	}

	if ($column === 'cmx_kategorie') {
		$tax = cmx_projekte_detect_taxonomy();
		if (!$tax) { echo ''; return; }

		$terms = get_the_terms($post_id, $tax);
		if (empty($terms) || is_wp_error($terms)) { echo ''; return; }

		$out = [];
		foreach ($terms as $t) {
			$url = add_query_arg([
				'post_type' => CMX_PROJEKT_CPT,
				$tax        => $t->slug,
			], admin_url('edit.php'));
			$out[] = '<a href="' . esc_url($url) . '">' . esc_html($t->name) . '</a>';
		}
		echo implode(', ', $out);
		return;
	}

	if ($column === 'cmx_status') {
		$tax = cmx_projekte_detect_status_taxonomy();
		if (!$tax) { echo ''; return; }

		$terms = get_the_terms($post_id, $tax);
		if (empty($terms) || is_wp_error($terms)) { echo ''; return; }

		$out = [];
		foreach ($terms as $t) {
			$url = add_query_arg([
				'post_type'         => CMX_PROJEKT_CPT,
				'cmx_status_filter' => $t->slug,
			], admin_url('edit.php'));
			$out[] = '<a href="' . esc_url($url) . '">' . esc_html($t->name) . '</a>';
		}
		echo implode(', ', $out);
		return;
	}

}, 10, 2);

/** Sortierbarkeit: Kunde (nach Kontakt-ID) */
add_filter('manage_edit-' . CMX_PROJEKT_CPT . '_sortable_columns', function(array $cols) {
	$cols['cmx_kunde'] = 'cmx_kunde';
	$cols['cmx_kategorie'] = 'cmx_sort_kategorie';
	$cols['cmx_status'] = 'cmx_sort_status';
	return $cols;
});

/** A: Monats-Archiv (Alle Daten) entfernen – nur für CPT projekte */
add_filter('months_dropdown_results', function($months, $post_type) {
	if ((string) $post_type === (string) CMX_PROJEKT_CPT) return [];
	return $months;
}, 10, 2);

/** B1: Kategorie-Filter (Dropdown) über der Tabelle */
add_action('restrict_manage_posts', function($post_type) {
	if ($post_type !== CMX_PROJEKT_CPT) return;
	if (\function_exists(__NAMESPACE__ . '\\cmx_admin_post_type_column_is_visible') && !cmx_admin_post_type_column_is_visible(CMX_PROJEKT_CPT, 'cmx_kategorie')) return;

	$tax = cmx_projekte_detect_taxonomy();
	if (!$tax || !taxonomy_exists($tax) || !is_object_in_taxonomy(CMX_PROJEKT_CPT, $tax)) return;

	$selected = isset($_GET[$tax]) ? sanitize_text_field(wp_unslash($_GET[$tax])) : '';

	wp_dropdown_categories([
		'show_option_all' => __('Alle Kategorien', 'cmx'),
		'taxonomy'        => $tax,
		'name'            => $tax,
		'orderby'         => 'name',
		'selected'        => $selected,
		'hierarchical'    => true,
		'show_count'      => false,
		'hide_empty'      => false,
		'value_field'     => 'slug',
	]);
}, 10);

/** B1b: Status-Filter (Dropdown) über der Tabelle */
add_action('restrict_manage_posts', function($post_type) {
	if ($post_type !== CMX_PROJEKT_CPT) return;
	if (\function_exists(__NAMESPACE__ . '\\cmx_admin_post_type_column_is_visible') && !cmx_admin_post_type_column_is_visible(CMX_PROJEKT_CPT, 'cmx_status')) return;

	$tax = cmx_projekte_detect_status_taxonomy();
	if (!$tax || !taxonomy_exists($tax) || !is_object_in_taxonomy(CMX_PROJEKT_CPT, $tax)) return;

	$param = 'cmx_status_filter';
	$selected = isset($_GET[$param]) ? sanitize_text_field(wp_unslash($_GET[$param])) : '';
	$terms = get_terms([
		'taxonomy'   => $tax,
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	]);
	if (is_wp_error($terms)) return;

	echo '<select name="' . esc_attr($param) . '">';
	echo '<option value="">' . esc_html__('Alle Status', 'cmx') . '</option>';
	foreach ($terms as $term) {
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr($term->slug),
			selected($selected, (string) $term->slug, false),
			esc_html($term->name)
		);
	}
	echo '</select>';
}, 10);

/** B2: Kunden-Filter (Dropdown) über der Tabelle */
add_action('restrict_manage_posts', function($post_type) {
	if ($post_type !== CMX_PROJEKT_CPT) return;
	if (\function_exists(__NAMESPACE__ . '\\cmx_admin_post_type_column_is_visible') && !cmx_admin_post_type_column_is_visible(CMX_PROJEKT_CPT, 'cmx_kunde')) return;

	$param = 'cmx_kunde_filter';
	$selected = isset($_GET[$param]) ? absint(wp_unslash($_GET[$param])) : 0;
	$options = cmx_projekte_get_kunden_filter_options();

	if ($selected > 0 && !isset($options[$selected]) && get_post_status($selected)) {
		$title = (string) get_the_title($selected);
		$options[$selected] = $title !== '' ? $title : ('#' . $selected);
		natcasesort($options);
	}

	echo '<select name="' . esc_attr($param) . '">';
	echo '<option value="">' . esc_html__('Alle Kunden', 'cmx') . '</option>';
	foreach ($options as $id => $label) {
		printf(
			'<option value="%d"%s>%s</option>',
			(int) $id,
			selected($selected, (int) $id, false),
			esc_html($label)
		);
	}
	echo '</select>';
}, 11);

/** =========================
 *  Admin-Liste: Beginn & Ende (zusätzliche Spalten, sortierbar)
 * ========================= */

/** Spalten hinzufügen (Beginn/Ende) – ergänzt vorhandene Spalten */
add_filter('manage_edit-projekte_columns', function($columns) {
	$new = [];
	foreach ($columns as $key => $label) {
		$new[$key] = $label;
		if ($key === 'title') {
			$new['cmx_col_beginn'] = 'Beginn';
			$new['cmx_col_ende']   = 'Ende';
		}
	}
	if (!isset($new['cmx_col_beginn'])) {
		$new['cmx_col_beginn'] = 'Beginn';
		$new['cmx_col_ende']   = 'Ende';
	}
	return $new;
});

/** Spalteninhalte (Beginn/Ende) */
add_action('manage_projekte_posts_custom_column', function($column, $post_id) {
	if ($column === 'cmx_col_beginn') {
		$val = get_post_meta($post_id, CMX_PROJ_BEG_META, true);
		echo $val ? esc_html(cmx_format_ch_date($val)) : '';
	}
	if ($column === 'cmx_col_ende') {
		$val = get_post_meta($post_id, CMX_PROJ_END_META, true);
		echo $val ? esc_html(cmx_format_ch_date($val)) : '';
	}
}, 10, 2);

/** Spalten sortierbar (Beginn/Ende) */
add_filter('manage_edit-projekte_sortable_columns', function($sortable) {
	$sortable['cmx_col_beginn'] = 'cmx_sort_beginn';
	$sortable['cmx_col_ende']   = 'cmx_sort_ende';
	$sortable['cmx_col_umsatz'] = 'cmx_sort_umsatz';
	$sortable['cmx_kategorie']  = 'cmx_sort_kategorie';
	$sortable['cmx_status']     = 'cmx_sort_status';
	// Kunde-Sortierung bleibt über den anderen Filter gesetzt
	return $sortable;
});

/** =========================
 *  Query-Hooks (Sortierung + Tax-Filter)
 * ========================= */
add_action('pre_get_posts', function(\WP_Query $q) {
	if (!is_admin() || !$q->is_main_query()) return;
	if ((string) $q->get('post_type') !== (string) CMX_PROJEKT_CPT) return;

	$orderby = $q->get('orderby');

	// Sortierung Kunde
	if ($orderby === 'cmx_kunde') {
		$q->set('meta_key', CMX_KONTAKT_META);
		$q->set('orderby', 'meta_value_num');
	}

	// Sortierung Beginn/Ende
	if ($orderby === 'cmx_sort_beginn') {
		$q->set('meta_key', CMX_PROJ_BEG_META);
		$q->set('orderby', 'meta_value');
		$q->set('meta_type', 'DATE');
	}
	if ($orderby === 'cmx_sort_ende') {
		$q->set('meta_key', CMX_PROJ_END_META);
		$q->set('orderby', 'meta_value');
		$q->set('meta_type', 'DATE');
	}
	if ($orderby === 'cmx_sort_umsatz') {
		// Fehlende Cache-Werte einmalig aufbauen, damit alle Projekte sortierbar sind.
		cmx_proj_prime_missing_umsatz_meta();
		$q->set('meta_key', CMX_PROJ_UMSATZ_META);
		$q->set('orderby', 'meta_value_num');
	}

	// Filter Kunde
	$kunde_filter = isset($_GET['cmx_kunde_filter']) ? absint(wp_unslash($_GET['cmx_kunde_filter'])) : 0;
	if (\function_exists(__NAMESPACE__ . '\\cmx_admin_post_type_column_is_visible') && !cmx_admin_post_type_column_is_visible(CMX_PROJEKT_CPT, 'cmx_kunde')) {
		$kunde_filter = 0;
	}
	if ($kunde_filter > 0) {
		$meta_query = (array) $q->get('meta_query');
		$meta_query[] = [
			'key'     => CMX_KONTAKT_META,
			'value'   => $kunde_filter,
			'compare' => '=',
			'type'    => 'NUMERIC',
		];
		$q->set('meta_query', $meta_query);
	}

	// Taxonomie-Filter (Kategorie + Status)
	$tax_query = (array) $q->get('tax_query');

	$category_tax = cmx_projekte_detect_taxonomy();
	if ($category_tax && taxonomy_exists($category_tax) && is_object_in_taxonomy(CMX_PROJEKT_CPT, $category_tax)) {
		$selected_category = isset($_GET[$category_tax]) ? sanitize_text_field(wp_unslash($_GET[$category_tax])) : '';
		if (\function_exists(__NAMESPACE__ . '\\cmx_admin_post_type_column_is_visible') && !cmx_admin_post_type_column_is_visible(CMX_PROJEKT_CPT, 'cmx_kategorie')) {
			$selected_category = '';
		}
		if ($selected_category !== '' && $selected_category !== '0') {
			$tax_query[] = [
				'taxonomy'         => $category_tax,
				'field'            => 'slug',
				'terms'            => [$selected_category],
				'include_children' => true,
			];
		}
	}

	$status_tax = cmx_projekte_detect_status_taxonomy();
	$selected_status = isset($_GET['cmx_status_filter']) ? sanitize_text_field(wp_unslash($_GET['cmx_status_filter'])) : '';
	if (\function_exists(__NAMESPACE__ . '\\cmx_admin_post_type_column_is_visible') && !cmx_admin_post_type_column_is_visible(CMX_PROJEKT_CPT, 'cmx_status')) {
		$selected_status = '';
	}
	if ($status_tax && taxonomy_exists($status_tax) && is_object_in_taxonomy(CMX_PROJEKT_CPT, $status_tax) && $selected_status !== '' && $selected_status !== '0') {
		$tax_query[] = [
			'taxonomy'         => $status_tax,
			'field'            => 'slug',
			'terms'            => [$selected_status],
			'include_children' => false,
		];
	}

	$has_tax_filters = false;
	foreach ($tax_query as $key => $clause) {
		if (is_int($key) && is_array($clause) && !empty($clause['taxonomy'])) {
			$has_tax_filters = true;
			break;
		}
	}
	if ($has_tax_filters) {
		if (!isset($tax_query['relation'])) {
			$tax_query['relation'] = 'AND';
		}
		$q->set('tax_query', $tax_query);
	}
});

/** Sortierung Kategorie/Status (Taxonomie-Termname) */
add_filter('posts_clauses', function(array $clauses, \WP_Query $q): array {
	if (!is_admin() || !$q->is_main_query()) return $clauses;
	if ((string) $q->get('post_type') !== (string) CMX_PROJEKT_CPT) return $clauses;
	$orderby = (string) $q->get('orderby');
	if (!in_array($orderby, ['cmx_sort_kategorie', 'cmx_sort_status'], true)) return $clauses;

	$is_status_sort = ($orderby === 'cmx_sort_status');
	$tax = $is_status_sort ? cmx_projekte_detect_status_taxonomy() : cmx_projekte_detect_taxonomy();
	if (!$tax || !taxonomy_exists($tax) || !is_object_in_taxonomy(CMX_PROJEKT_CPT, $tax)) {
		return $clauses;
	}

	global $wpdb;
	$order = strtoupper((string) $q->get('order')) === 'DESC' ? 'DESC' : 'ASC';
	$tax_sql = esc_sql($tax);
	$suffix = $is_status_sort ? 'status' : 'kat';
	$tr_alias = 'cmxtr_' . $suffix;
	$tt_alias = 'cmxtt_' . $suffix;
	$t_alias  = 'cmxt_' . $suffix;

	$clauses['join'] .= " LEFT JOIN {$wpdb->term_relationships} {$tr_alias} ON ({$wpdb->posts}.ID = {$tr_alias}.object_id)";
	$clauses['join'] .= " LEFT JOIN {$wpdb->term_taxonomy} {$tt_alias} ON ({$tr_alias}.term_taxonomy_id = {$tt_alias}.term_taxonomy_id AND {$tt_alias}.taxonomy = '{$tax_sql}')";
	$clauses['join'] .= " LEFT JOIN {$wpdb->terms} {$t_alias} ON ({$tt_alias}.term_id = {$t_alias}.term_id)";

	if (empty($clauses['groupby'])) {
		$clauses['groupby'] = "{$wpdb->posts}.ID";
	} elseif (strpos($clauses['groupby'], "{$wpdb->posts}.ID") === false) {
		$clauses['groupby'] .= ", {$wpdb->posts}.ID";
	}

	$clauses['orderby'] = "COALESCE(MIN({$t_alias}.name), '') {$order}, {$wpdb->posts}.ID {$order}";
	return $clauses;
}, 20, 2);

/** =========================
 *  Meta-Box (SIDE): Projektzeitraum
 * ========================= */
add_action('add_meta_boxes', function() {
	add_meta_box(
		'cmx_proj_zeitraum',
		'Projektzeitraum',
		function($post) {
			$beginn = get_post_meta($post->ID, CMX_PROJ_BEG_META, true);
			$ende   = get_post_meta($post->ID, CMX_PROJ_END_META, true);
			wp_nonce_field(CMX_PROJ_NONCE_KEY, CMX_PROJ_NONCE_KEY . '_field');
			?>
			<p>
				<label for="cmx_proj_beginn"><strong>Beginn</strong></label><br>
				<input type="date" id="cmx_proj_beginn" name="cmx_proj_beginn"
					   value="<?php echo esc_attr($beginn); ?>" style="width:100%;">
			</p>
			<p>
				<label for="cmx_proj_ende"><strong>Ende</strong></label><br>
				<input type="date" id="cmx_proj_ende" name="cmx_proj_ende"
					   value="<?php echo esc_attr($ende); ?>" style="width:100%;">
			</p>
			<?php
		},
		'projekte',
		'side',
		'high'
	);
});

/** Speicherung der Meta-Werte */
add_action('save_post_projekte', function($post_id) {
	if (
		!isset($_POST[CMX_PROJ_NONCE_KEY . '_field']) ||
		!wp_verify_nonce($_POST[CMX_PROJ_NONCE_KEY . '_field'], CMX_PROJ_NONCE_KEY)
	) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (wp_is_post_revision($post_id)) return;
	if (!current_user_can('edit_post', $post_id)) return;

	$beginn = cmx_proj_sanitize_date($_POST['cmx_proj_beginn'] ?? '');
	$ende   = cmx_proj_sanitize_date($_POST['cmx_proj_ende'] ?? '');

	update_post_meta($post_id, CMX_PROJ_BEG_META, $beginn);
	update_post_meta($post_id, CMX_PROJ_END_META, $ende);
});

/** Umsatz-Cache beim Speichern aktualisieren (für Sortierung). */
add_action('save_post_projekte', function($post_id) {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (wp_is_post_revision($post_id)) return;
	if (!current_user_can('edit_post', $post_id)) return;
	cmx_proj_sync_umsatz_meta((int) $post_id);
}, 120);



/**
 * Zusatz: Spalte "URL" für CPT "projekte"
 * - Meta-Key: _cmx_projekt_url
 * - Klickbarer Link (neuer Tab)
 */

// Namespaced-Konstante für den URL-Meta-Key
if (!defined(__NAMESPACE__ . '\CMX_PROJ_URL_META')) {
	define(__NAMESPACE__ . '\CMX_PROJ_URL_META', '_cmx_projekt_url');
}

/**
 * Spalte "URL" in die Liste einfügen
 * - Versucht, direkt nach "Ende" zu platzieren, sonst nach "Titel", sonst ans Ende.
 */
add_filter('manage_edit-projekte_columns', function(array $columns) {
	$label = 'URL';

	// Zielreihenfolge berechnen
	$insert_after_keys = ['cmx_col_ende', 'title'];
	$injected = [];

	// Schon vorhanden? dann nichts tun
	if (isset($columns['cmx_col_url'])) {
		return $columns;
	}

	// Array in gewünschter Reihenfolge neu zusammensetzen
	foreach ($columns as $key => $val) {
		$injected[$key] = $val;

		// Nach dem ersten passenden Schlüssel einfügen
		if (in_array($key, $insert_after_keys, true)) {
			$injected['cmx_col_url'] = $label;
			// nur einmal einfügen
			$insert_after_keys = [];
		}
	}

	// Falls kein gewünschter Schlüssel existierte: am Ende anhängen
	if (!isset($injected['cmx_col_url'])) {
		$injected['cmx_col_url'] = $label;
	}

	return $injected;
}, 30);

/**
 * Spalte "Umsatz" einfügen (immer als letzte Spalte).
 */
add_filter('manage_edit-projekte_columns', function(array $columns) {
	unset($columns['cmx_col_umsatz']);
	$columns['cmx_col_umsatz'] = 'Umsatz';
	return $columns;
}, 999);

/** Umsatz-Spalte: Dezimalstellen sauber untereinander ausrichten */
add_action('admin_head-edit.php', function() {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || $screen->id !== 'edit-projekte') return;
	echo '<style>
		td.column-cmx_col_umsatz {
			text-align: left;
			white-space: nowrap;
		}
		td.column-cmx_col_umsatz .cmx-umsatz-num {
			display: inline-flex;
			align-items: baseline;
			font-variant-numeric: tabular-nums;
			margin-left: -30px;
		}
		td.column-cmx_col_umsatz .cmx-umsatz-int {
			display: inline-block;
			min-width: 9ch;
			text-align: right;
		}
		td.column-cmx_col_umsatz .cmx-umsatz-sep,
		td.column-cmx_col_umsatz .cmx-umsatz-frac {
			display: inline-block;
		}
	</style>';
});

/**
 * Spalteninhalt "URL" ausgeben
 */
add_action('manage_projekte_posts_custom_column', function($column, $post_id) {
	if ($column !== 'cmx_col_url') return;

	$url = trim((string) get_post_meta($post_id, CMX_PROJ_URL_META, true));
	if ($url === '') {
		echo '';
		return;
	}

	// Falls ohne Schema gespeichert, optional "https://" voranstellen (nur Anzeige)
	if (!preg_match('~^https?://~i', $url)) {
		$display = 'https://' . $url;
	} else {
		$display = $url;
	}

	// Klickbar ausgeben
	echo '<a href="' . esc_url($display) . '" target="_blank" rel="noopener noreferrer">'
		. esc_html($url)
		. '</a>';
}, 10, 2);

/**
 * Spalteninhalt "Umsatz" ausgeben
 */
add_action('manage_projekte_posts_custom_column', function($column, $post_id) {
	if ($column !== 'cmx_col_umsatz') return;

	$total = cmx_proj_calc_umsatz_total((int) $post_id);
	$parts = cmx_proj_format_decimal_parts($total);
	echo '<span class="cmx-umsatz-num">'
		. '<span class="cmx-umsatz-int">' . esc_html($parts['int']) . '</span>'
		. '<span class="cmx-umsatz-sep">' . esc_html($parts['sep']) . '</span>'
		. '<span class="cmx-umsatz-frac">' . esc_html($parts['frac']) . '</span>'
		. '</span>';
}, 10, 2);

add_filter('views_edit-projekte', function(array $views): array {
	return cmx_admin_deckungsbeitrag_add_view($views, 'projekte', 'projekte');
}, 30);

add_filter('manage_edit-projekte_columns', function(array $columns): array {
	if (!cmx_admin_deckungsbeitrag_view_active('projekte')) {
		return $columns;
	}

	return cmx_admin_deckungsbeitrag_insert_column($columns, 'cmx_deckungsbeitrag', 'Deckungsbeitrag');
}, 900);

add_action('manage_projekte_posts_custom_column', function(string $column, int $post_id): void {
	if ($column !== 'cmx_deckungsbeitrag' || !cmx_admin_deckungsbeitrag_view_active('projekte')) {
		return;
	}

	cmx_admin_deckungsbeitrag_render_value('projekte', $post_id);
}, 20, 2);

add_action('pre_get_posts', function(\WP_Query $query): void {
	cmx_admin_deckungsbeitrag_apply_query_sort($query, 'projekte', 'projekte');
}, 999);
