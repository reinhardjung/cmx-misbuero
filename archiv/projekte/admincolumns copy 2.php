<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Admin-Liste: Spalten "Beginn" & "Ende" (sortable) für CPT "projekte"
 * - Keine Filter, nur sortierbare Spalten
 * - Anzeige im CH-Format (d.m.Y), Speicherung bleibt YYYY-MM-DD
 */

// Falls die Konstanten aus der Metabox-Datei noch nicht vorhanden sind, hier setzen:
if (!defined('CMX_PROJ_BEG_META')) define('CMX_PROJ_BEG_META', '_cmx_projekt_beginn');
if (!defined('CMX_PROJ_END_META')) define('CMX_PROJ_END_META', '_cmx_projekt_ende');

/**
 * Spalten hinzufügen/reihenfolge
 */
add_filter('manage_edit-projekte_columns', function($columns) {
	// Reihenfolge: Checkbox, Titel, Beginn, Ende, Datum
	$new = [];
	foreach ($columns as $key => $label) {
		$new[$key] = $label;
		if ($key === 'title') {
			$new['cmx_col_beginn'] = 'Beginn';
			$new['cmx_col_ende']   = 'Ende';
		}
	}
	// Fallback: falls 'title' unerwartet fehlt
	if (!isset($new['cmx_col_beginn'])) {
		$new['cmx_col_beginn'] = 'Beginn';
		$new['cmx_col_ende']   = 'Ende';
	}
	return $new;
});

/**
 * Spalteninhalte
 */
add_action('manage_projekte_posts_custom_column', function($column, $post_id) {
	if ($column === 'cmx_col_beginn') {
		$val = get_post_meta($post_id, CMX_PROJ_BEG_META, true);
		echo $val ? esc_html(cmx_format_ch_date($val)) : '—';
	}
	if ($column === 'cmx_col_ende') {
		$val = get_post_meta($post_id, CMX_PROJ_END_META, true);
		echo $val ? esc_html(cmx_format_ch_date($val)) : '—';
	}
}, 10, 2);

/**
 * Spalten sortierbar machen
 */
add_filter('manage_edit-projekte_sortable_columns', function($sortable) {
	$sortable['cmx_col_beginn'] = 'cmx_sort_beginn';
	$sortable['cmx_col_ende']   = 'cmx_sort_ende';
	return $sortable;
});

/**
 * Sortierung nach Meta-Datum umsetzen
 */
add_action('pre_get_posts', function($query) {
	if (!is_admin() || !$query->is_main_query()) return;
	if ($query->get('post_type') !== 'projekte') return;

	$orderby = $query->get('orderby');

	if ($orderby === 'cmx_sort_beginn') {
		$query->set('meta_key', CMX_PROJ_BEG_META);
		$query->set('orderby', 'meta_value');
		$query->set('meta_type', 'DATE'); // korrektes Date-Sorting
	}
	if ($orderby === 'cmx_sort_ende') {
		$query->set('meta_key', CMX_PROJ_END_META);
		$query->set('orderby', 'meta_value');
		$query->set('meta_type', 'DATE');
	}
});

/**
 * Helper: YYYY-MM-DD -> d.m.Y (CH-Format)
 */
if (!function_exists('cmx_format_ch_date')) {
	function cmx_format_ch_date($yyyy_mm_dd) {
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$yyyy_mm_dd)) {
			return '';
		}
		$ts = strtotime($yyyy_mm_dd . ' 00:00:00');
		return $ts ? date('d.m.Y', $ts) : '';
	}
}
