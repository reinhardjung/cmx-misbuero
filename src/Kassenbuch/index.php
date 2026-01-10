<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


$post_type = 'kassenbuch';
$label = 'Kassenbuch';

// Define: Custom-Post-Type
register_post_type($post_type, ['labels' => ['name' => $label, 'singular_name' => $label, 'add_new_item' => 'Hinzufügen', 'edit_item' => 'Bearbeiten',],
	'menu_position' => 60, 'supports' => ['title', 'editor', 'thumbnail'], 'public' => true, 'menu_icon' => 'dashicons-analytics', 'show_in_rest' => true, 'has_archive' => true, 'rewrite' => ['slug' => $post_type],
]);


// Define: CONST 4 @ll Taxos
define(__NAMESPACE__ . '\\CMX_TAX_'.strtoupper($post_type),'Kategorien,Konten');


// Define: CONST 4 each Taxo
cmx_const_taxos(strtoupper($post_type), $post_type, CMX_TAX_KASSENBUCH);
// cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), const('\\CMX_TAX_'.strtoupper(basename(__DIR__))));
// var_dump(CMX_TAX_KASSENBUCH); exit;


// Create: @ll Taxos
\add_action('init', function () use ($post_type) {
	cmx_create_taxo($post_type, 'Kategorie', 'Kategorien');
	cmx_create_taxo($post_type, 'Konto', 'Konten');
}, 15);


// Refill: Taxo with defaults if removed
\add_action('admin_init', function () use ($label) {
	cmx_seed_taxo(cmx_sani_key($label,'title'),CMX_TAX_KASSENBUCH);
});


// Define: Const 4 @ll CPT Fields
// cmx_define_meta_constants(basename(__DIR__), ['umsatz']);


// Include: @ll metaboxes
// cmx_require_files(__DIR__,'stammdaten,kommunikation,adressen,infos,anhaenge,admincolumns,stufen,exports,sichern');
cmx_require_files(__DIR__,'dokumente,sichern');
