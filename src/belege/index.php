<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


// Define: Custom-Post-Type based on DIR
register_post_type(basename(__DIR__), ['labels' => ['name' => cmx_sani_key(basename(__DIR__), 'title'), 'singular_name' => cmx_sani_key(basename(__DIR__), 'title'), 'add_new_item' => 'Hinzufügen', 'edit_item' => 'Bearbeiten',],
	'menu_position' => 30, 'supports' => ['title'], 'public' => true, 'menu_icon' => 'dashicons-businessman', 'show_in_rest' => true, 'has_archive' => true, 'rewrite' => ['slug' => basename(__DIR__)],
]);


// Define: CONST 4 @ll Taxos
define(__NAMESPACE__ . '\\CMX_TAX_'.strtoupper(basename(__DIR__)),'Kategorien,Projekte');


// Define: CONST 4 each Taxo
cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), CMX_TAX_BELEGE);
// cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), constant('CMX_TAX_'.strtoupper(basename(__DIR__))));


// Create: @ll Taxos
\add_action('init', function () {
	cmx_create_taxo(basename(__DIR__), 'Kategorie', 'Kategorien', false);
	cmx_create_taxo(basename(__DIR__), 'Projekt', 'Projekte', false);
	// cmx_create_taxo(basename(__DIR__), 'Land', 'Länder', false); // REchungna ls default, genaus wioe Schwiez...
}, 15);


// Refill: Taxo with defaults if removed
\add_action('admin_init', function () {
	cmx_seed_taxo(cmx_sani_key(basename(__DIR__),'title'),CMX_TAX_BELEGE);
});


// Define: Const 4 @ll CPT Fields
// cmx_define_meta_constants(basename(__DIR__), ['umsatz']);


// Include: @ll metaboxes
cmx_require_files(__DIR__,'kopfdaten,positionen,konditionen,admincolumns,notizen,summen,vorlage_pdf,meta_action,logfile,vorlage_qr_code');
