<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


// cmx_create_taxo('artikel', 'Marke', 'Marken');
function cmx_create_taxo(string $cpt_name, string $taxo_single, string $taxo_plural, $metabox = null, $hierarchisch = true, array $overrides = []): void {
	$tax = cmx_tax_key($cpt_name, cmx_no_umlaute($taxo_plural));
	if (\taxonomy_exists($tax)) return;

	// $is_hierarchical	= is_bool($hierarchisch) ? $hierarchisch : (strtolower((string)$hierarchisch) !== 'nein'); // Standardmäßig hierarchisch (true)

	$labels = ['name' => cmx_no_umlaute($taxo_plural),'singular_name' => $taxo_single,'search_items' => 'Suchen','all_items' => 'Alle','edit_item' => 'Bearbeiten','view_item' => 'Anzeigen','update_item' => 'Aktualisieren','add_new_item' => 'Hinzufügen','new_item_name' => 'Neu','parent_item' => 'Übergeordnet','parent_item_colon' => 'Übergeordnet:','not_found' => 'Keine ' .cmx_no_umlaute($taxo_plural). ' gefunden','no_terms' => 'Keine ' .cmx_no_umlaute($taxo_plural),'menu_name' => $taxo_plural];
	$args_default = ['meta_box_cb' => null, 'labels'  => $labels,'label' => $taxo_single,'public' => false,'meta_box_cb' => $metabox, 'show_ui' => true,'show_admin_column' => false,'hierarchical' => $hierarchisch,'show_in_rest' => true,'rewrite' => false,'query_var' => false,];
	// \remove_meta_box('tagsdiv-cmx_komm_phone', 'kontakte', 'side');

	$args = array_replace_recursive($args_default, $overrides);

	\register_taxonomy($tax, [$cpt_name], $args);
}


// cmx_const_taxos('ARTIKEL','artikel', 'lala,lulu,bubu');
function cmx_const_taxos(string $prefix_const, string $prefix_value, string $tax_enties): void {
	$tax_suffixes = array_filter(array_map('trim', explode(',', $tax_enties)));
	foreach ($tax_suffixes as $suffix) {
		$suffix = trim(strtolower($suffix));
		if ($suffix === '') continue;

		$const_name  = 'TAX_' .$prefix_const .'_' . strtoupper($suffix);
		$const_value = $prefix_value .'_' . $suffix;

		if (!defined(__NAMESPACE__ . '\\' . $const_name)) {
			define(__NAMESPACE__ . '\\' . $const_name, $const_value);
		}
	}
}


// cmx_seed_taxo('Artikel','Marken,Farben,Einheiten,Typen,Kategorien');
function cmx_seed_taxo(string $base = 'NameDesCPTs', string $myTaxos): void {
	$labels = array_filter(array_map('trim', explode(',', $myTaxos)));
	if (empty($labels)) return;

	$constBase = cmx_sani_key($base,'upper');
	$slugBase  = cmx_sani_key($base,'lower');

	foreach ($labels as $label) {
		$upper    = cmx_sani_key($label,'upper');
		$lowerKey = cmx_sani_key($label,'lower');
		$constFqn = __NAMESPACE__ . '\\TAX_' . $constBase . '_' . $upper; // \NS\TAX_ARTIKEL_MARKEN

		$taxonomy = defined($constFqn) ? constant($constFqn) : $slugBase . '_' . $lowerKey;
		if (!taxonomy_exists($taxonomy)) continue;

		$have = get_terms(['taxonomy'=>$taxonomy,'hide_empty'=>false,'fields'=>'ids','number'=>1]);
		if (is_wp_error($have) || !empty($have)) continue;

		$terms = function_exists(__NAMESPACE__.'\\cmx_ini_get_value')
			? (array) cmx_ini_get_value($slugBase, $lowerKey) // optional: Basisbereich auch dynamisch
			: [];
		$terms = array_values(array_filter(array_map('trim', $terms), fn($v)=>$v!==''));
		if (empty($terms)) continue;

		wp_defer_term_counting(true);
		foreach ($terms as $name) {
			if ($name !== '' && !term_exists($name, $taxonomy)) {
				wp_insert_term($name, $taxonomy);
			}
		}
		wp_defer_term_counting(false);
	}
}


// add_action('admin_notices', function () {
//     if (!is_admin()) return;

//     $test = wp_remote_get('https://ipapi.co/31.165.222.102/json/');

//     echo '<div class="notice notice-info"><p><strong>GEO-API TEST:</strong><br>';

//     if (is_wp_error($test)) {
//         echo 'WP ERROR: ' . $test->get_error_message();
//     } else {
//         echo 'RESPONSE: ' . wp_remote_retrieve_body($test);
//     }

//     echo '</p></div>';
// });


	// $rows = $wpdb->get_results("
	// 	SELECT option_name, option_value
	// 	FROM {$wpdb->options}
	// 	WHERE option_name LIKE 'beleg_%'
