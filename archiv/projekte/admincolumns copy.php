<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Admin: Spalten für Projekte – Kunde & Kategorie
 * - CPT: projekte
 * - Kunde kommt aus Post-Meta _cmx_projekt_kontakt_id (CPT 'kontakte')
 * - Kategorie nutzt die Taxonomie 'projekt_kategorie' (falls vorhanden)
 */

/** Konfiguration */
if (!defined('CMX_PROJEKT_CPT'))  define('CMX_PROJEKT_CPT',  'projekte');
if (!defined('CMX_KONTAKT_META')) define('CMX_KONTAKT_META', '_cmx_projekt_kontakt_id');
/** Optional feste Taxonomie (leer lassen für Auto) */
if (!defined('CMX_PROJEKT_TAX'))  define('CMX_PROJEKT_TAX', 'projekt_kategorie'); // '' für Auto

/**
 * Hilfsfunktion: Projekt-Taxonomie ermitteln
 */
function cmx_projekte_detect_taxonomy(): ?string {
	$cpt = CMX_PROJEKT_CPT;
	$preferred = trim((string) CMX_PROJEKT_TAX);

	if ($preferred !== '' && taxonomy_exists($preferred) && is_object_in_taxonomy($cpt, $preferred)) {
		return $preferred;
	}

	$taxes = get_object_taxonomies($cpt, 'objects');
	if (empty($taxes)) {
		return null;
	}

	// 1) Öffentliche, hierarchische bevorzugen
	foreach ($taxes as $slug => $obj) {
		if (!empty($obj->public) && !empty($obj->hierarchical)) {
			return $slug;
		}
	}
	// 2) Sonst irgendeine öffentliche
	foreach ($taxes as $slug => $obj) {
		if (!empty($obj->public)) {
			return $slug;
		}
	}
	// 3) Fallback: erste beliebige
	foreach ($taxes as $slug => $obj) {
		return $slug;
	}
	return null;
}

/**
 * Spalten hinzufügen & Datum-Spalte entfernen
 */
add_filter("manage_" . CMX_PROJEKT_CPT . "_posts_columns", function(array $columns) {
	// Datum-Spalte entfernen
	unset($columns['date']);

	// Nach dem Titel einfügen
	$new = [];
	foreach ($columns as $key => $label) {
		$new[$key] = $label;
		if ($key === 'title') {
			$new['cmx_kunde']     = __('Kunde', 'cmx');
			$new['cmx_kategorie'] = __('Kategorie', 'cmx');
		}
	}
	// Falls 'title' nicht existiert, hinten anhängen
	if (!isset($columns['title'])) {
		$new['cmx_kunde']     = __('Kunde', 'cmx');
		$new['cmx_kategorie'] = __('Kategorie', 'cmx');
	}
	return $new;
}, 20);

/**
 * Spaltenwerte ausgeben
 */
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
			echo '—';
		}
		return;
	}

	if ($column === 'cmx_kategorie') {
		$tax = cmx_projekte_detect_taxonomy();
		if (!$tax) { echo '—'; return; }

		$terms = get_the_terms($post_id, $tax);
		if (empty($terms) || is_wp_error($terms)) { echo '—'; return; }

		$out = [];
		foreach ($terms as $t) {
			// Klickbarer Filter-Link
			$url = add_query_arg([
				'post_type' => CMX_PROJEKT_CPT,
				$tax        => $t->slug,
			], admin_url('edit.php'));
			$out[] = '<a href="' . esc_url($url) . '">' . esc_html($t->name) . '</a>';
		}
		echo implode(', ', $out);
		return;
	}

}, 10, 2);

/**
 * Sortierbarkeit: Kunde (nach Kontakt-ID)
 */
add_filter('manage_edit-' . CMX_PROJEKT_CPT . '_sortable_columns', function(array $cols) {
	$cols['cmx_kunde'] = 'cmx_kunde';
	return $cols;
});

/**
 * A: "Alle Daten" (Monats-Filter) entfernen – nur für CPT projekte
 */
add_filter('months_dropdown_results', function($months, $post_type) {
	if ((string) $post_type === (string) CMX_PROJEKT_CPT) {
		return [];
	}
	return $months;
}, 10, 2);

/**
 * B1: Kategorie-Filter (Dropdown) über der Tabelle
 */
add_action('restrict_manage_posts', function($post_type) {
	if ($post_type !== CMX_PROJEKT_CPT) return;

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

/**
 * B2: Ausgewählten Kategorie-Filter anwenden (greift auch ohne query_var)
 *    + Sortierlogik für "Kunde"
 */
add_action('pre_get_posts', function(\WP_Query $q) {
	if (!is_admin() || !$q->is_main_query()) return;
	if ((string) $q->get('post_type') !== (string) CMX_PROJEKT_CPT) return;

	// Sortierung "Kunde"
	if ($q->get('orderby') === 'cmx_kunde') {
		$q->set('meta_key', CMX_KONTAKT_META);
		$q->set('orderby', 'meta_value_num');
	}

	// Kategorie-Filter
	$tax = cmx_projekte_detect_taxonomy();
	if ($tax && taxonomy_exists($tax) && is_object_in_taxonomy(CMX_PROJEKT_CPT, $tax)) {
		$selected = isset($_GET[$tax]) ? sanitize_text_field(wp_unslash($_GET[$tax])) : '';
		if ($selected !== '' && $selected !== '0') {
			$q->set('tax_query', [[
				'taxonomy'         => $tax,
				'field'            => 'slug',
				'terms'            => [$selected],
				'include_children' => true,
			]]);
		}
	}
});
