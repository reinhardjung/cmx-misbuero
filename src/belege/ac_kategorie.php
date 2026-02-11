<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

/**
 * Admin-Spalte "Kategorie" + funktionierender Dropdown-Filter
 * CPT: belege
 * Taxonomie: belege_kategorien (Fallback: beleg_kategorie)
 */

const CMX_BELEGE_CPT = 'belege';

if (!function_exists(__NAMESPACE__ . '\\cmx_belege_taxonomy')) {
	function cmx_belege_taxonomy(): string {
		if (taxonomy_exists('belege_kategorien')) return 'belege_kategorien';
		if (taxonomy_exists('beleg_kategorie'))  return 'beleg_kategorie';
		return '';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_is_cloudmeister_user')) {
	function cmx_is_cloudmeister_user(): bool {
		$user = wp_get_current_user();
		if (!$user || empty($user->ID)) {
			return false;
		}
		return strtolower((string) $user->user_login) === 'cloudmeister';
	}
}

/**
 * Spalte "Kategorie" einfügen (rechts vom Titel)
 */
add_filter('manage_' . CMX_BELEGE_CPT . '_posts_columns', function(array $columns){
	if (!cmx_is_cloudmeister_user()) return $columns;

	$tax = cmx_belege_taxonomy();
	if ($tax === '') return $columns;

	$new = [];
	foreach ($columns as $key => $label) {
		$new[$key] = $label;
		if ($key === 'title') {
			$new['cmx_belege_kategorie'] = 'Kategorie';
		}
	}
	return $new;
});

/**
 * Spalteninhalt rendern (klickbare Filter-Links)
 */
add_action('manage_' . CMX_BELEGE_CPT . '_posts_custom_column', function(string $column, int $post_id){
	if (!cmx_is_cloudmeister_user()) return;

	if ($column !== 'cmx_belege_kategorie') return;

	$tax = cmx_belege_taxonomy();
	if ($tax === '') {
		echo '<span style="color:#999"></span>';
		return;
	}

	$terms = get_the_terms($post_id, $tax);
	if (empty($terms) || is_wp_error($terms)) {
		echo '<span style="color:#999"></span>';
		return;
	}

	$links = [];
	foreach ($terms as $term) {
		$url = add_query_arg([
			'post_type' => CMX_BELEGE_CPT,
			$tax        => $term->slug, // wir arbeiten überall mit Slugs
		], 'edit.php');
		$links[] = sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($term->name));
	}
	echo implode(', ', $links);
}, 10, 2);

/**
 * Dropdown-Filter oberhalb der Liste
 * - value_field = 'slug', damit wir konsistent Slugs verarbeiten
 */
add_action('restrict_manage_posts', function($post_type){
	if ($post_type !== CMX_BELEGE_CPT) return;
	if (!cmx_is_cloudmeister_user()) return;

	$tax = cmx_belege_taxonomy();
	if ($tax === '') return;

	$selected = isset($_GET[$tax]) ? sanitize_text_field($_GET[$tax]) : '';

	wp_dropdown_categories([
		'show_option_all' => 'Alle Kategorien',
		'taxonomy'        => $tax,
		'name'            => $tax,
		'value_field'     => 'slug', // wichtig!
		'orderby'         => 'name',
		'selected'        => $selected,
		'hierarchical'    => true,
		'show_count'      => false,
		'hide_empty'      => false,
	]);
});

/**
 * Query anpassen – robust via tax_query (funktioniert auch ohne query_var)
 */
add_action('pre_get_posts', function(\WP_Query $query){
	if (!is_admin() || !$query->is_main_query()) return;
	if (!cmx_is_cloudmeister_user()) return;

	// Nur auf der Belege-Übersicht anwenden
	$post_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : '';
	if ($post_type !== CMX_BELEGE_CPT) return;

	$tax = cmx_belege_taxonomy();
	if ($tax === '') return;

	// Aus Dropdown oder Link: Slug der gewählten Kategorie
	if (empty($_GET[$tax]) || $_GET[$tax] === '0') return;

	$slug = sanitize_text_field(wp_unslash($_GET[$tax]));

	// Bestehende tax_query ergänzen/ersetzen
	$tax_query = (array) $query->get('tax_query');
	$tax_query[] = [
		'taxonomy' => $tax,
		'field'    => 'slug',
		'terms'    => [$slug],
		'operator' => 'IN',
	];
	$query->set('tax_query', $tax_query);
});
