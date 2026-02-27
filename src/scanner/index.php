<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


// Define: Custom-Post-Type based on DIR
register_post_type(basename(__DIR__), ['labels' => ['name' => 'Posteingang', 'singular_name' => 'Posteingang', 'add_new_item' => 'Hinzufügen', 'edit_item' => 'Bearbeiten',],
	'menu_position' => 90, 'supports' => ['title', 'editor'], 'public' => true, 'menu_icon' => 'dashicons-cloud', 'show_in_rest' => true, 'has_archive' => true, 'rewrite' => ['slug' => basename(__DIR__)],
]);


// Define: CONST 4 @ll Taxos
define(__NAMESPACE__ . '\\CMX_TAX_'.strtoupper(basename(__DIR__)),'Marken,Farben,Einheiten,Typen,Kategorien');


// Define: CONST 4 each Taxo
cmx_const_taxos(cmx_sani_key(basename(__DIR__),'upper'),basename(__DIR__), CMX_TAX_ARTIKEL);
// cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), define('\\CMX_TAX_',strtoupper(basename(__DIR__))));


// Create: @ll Taxos
\add_action('init', function () {
	cmx_create_taxo('michbuechli', 'Kategorie', 'Kategorien');
}, 15);


// Refill: Taxo with defaults if removed
\add_action('admin_init', function () {
	cmx_seed_taxo(cmx_sani_key(basename(__DIR__),'title'),CMX_TAX_ARTIKEL);
});


// Define: Const 4 @ll CPT Fields
// cmx_define_meta_constants(basename(__DIR__), 'sku,ek,vk,marge,waehrungen,verkaufbar,lieferant,lieferzeit,lieferant_nr,bezugsquelle,lagerbestand');


// Include: @ll metaboxes
cmx_require_files(__DIR__, 'zuordnung,zuordnung_kontakte,zuordnung_artikel,zuordnung_dokumente,zuordnung_projekte,zuordnung_belege,admincolumnslist');
