<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_EMAILS_CPT')) {
	\define(__NAMESPACE__ . '\\CMX_EMAILS_CPT', basename(__DIR__));
}

if (!\defined(__NAMESPACE__ . '\\CMX_EMAILS_PAGE_SLUG')) {
	\define(__NAMESPACE__ . '\\CMX_EMAILS_PAGE_SLUG', 'cmx-emails-mailbox');
}

// Define: Custom-Post-Type based on DIR
register_post_type(CMX_EMAILS_CPT, ['labels' => ['name' => 'E-Mails', 'singular_name' => 'E-Mail', 'add_new_item' => 'Hinzufügen', 'edit_item' => 'Bearbeiten',],
	'menu_position' => 100, 'supports' => ['title', 'editor'], 'public' => true, 'menu_icon' => 'dashicons-email-alt', 'show_in_rest' => true, 'has_archive' => true, 'rewrite' => ['slug' => basename(__DIR__)],
]);


// Define: CONST 4 @ll Taxos
define(__NAMESPACE__ . '\\CMX_TAX_'.strtoupper(basename(__DIR__)),'Kategorien');


// Define: CONST 4 each Taxo
cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), CMX_TAX_EMAILS);
// cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), const('\\CMX_TAX_'.strtoupper(basename(__DIR__))));
// var_dump(CMX_TAX_DOKUMENTE); exit;


// Create: @ll Taxos
\add_action('init', function () {
	cmx_create_taxo(basename(__DIR__), 'Kategorie', 'Kategorien');
}, 15);


// Refill: Taxo with defaults if removed
\add_action('admin_init', function () {
	cmx_seed_taxo(cmx_sani_key(basename(__DIR__),'title'), CMX_TAX_EMAILS, 'E-Mails');
});



// Define: Const 4 @ll CPT Fields
// cmx_define_meta_constants(basename(__DIR__), ['umsatz']);


// Include: @ll metaboxes
cmx_require_files(__DIR__,'clients,mailbox,page,admincolumns,edit,spams');
