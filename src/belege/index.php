<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


// Define: Custom-Post-Type based on DIR
register_post_type(basename(__DIR__), ['labels' => ['name' => cmx_sani_key(basename(__DIR__), 'title'), 'singular_name' => cmx_sani_key(basename(__DIR__), 'title'), 'add_new_item' => 'Hinzufügen', 'edit_item' => 'Bearbeiten',],
	'menu_position' => 30, 'supports' => ['title'], 'public' => true, 'menu_icon' => 'dashicons-businessman', 'show_in_rest' => true, 'has_archive' => true, 'rewrite' => ['slug' => basename(__DIR__)],
]);


// Define: CONST 4 @ll Taxos
define(__NAMESPACE__ . '\\CMX_TAX_'.strtoupper(basename(__DIR__)),'Kategorien,MwSt');


// Define: CONST 4 each Taxo
cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), CMX_TAX_BELEGE);
// cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), constant('CMX_TAX_'.strtoupper(basename(__DIR__))));


// Create: @ll Taxos
\add_action('init', function () {
	// Kategorien: UI komplett ausblenden, Taxonomie bleibt für Abfragen bestehen
	cmx_create_taxo(basename(__DIR__), 'Kategorie', 'Kategorien', false, true, ['show_ui' => false]);
	cmx_create_taxo(basename(__DIR__), 'MwSt', 'MwSt', false);
	// cmx_create_taxo(basename(__DIR__), 'Land', 'Länder', false); // REchungna ls default, genaus wioe Schwiez...
}, 15);


// Refill: Taxo with defaults if removed
\add_action('admin_init', function () {
	cmx_seed_taxo(cmx_sani_key(basename(__DIR__),'title'),CMX_TAX_BELEGE);
});

// Kategorien in der Admin-Navigation ausblenden (Taxonomie bleibt bestehen)
\add_action('admin_menu', function () {
	$parent = 'edit.php?post_type=' . basename(__DIR__);
	foreach (['belege_kategorien', 'beleg_kategorie'] as $tax) {
		remove_submenu_page($parent, 'edit-tags.php?taxonomy=' . $tax . '&post_type=' . basename(__DIR__));
		remove_submenu_page($parent, 'edit-tags.php?taxonomy=' . $tax); // ggf. Variante ohne post_type
	}
}, 99);

// Kategorien-Metabox auf dem Beleg-Edit-Screen ausblenden (nicht editierbar)
\add_action('add_meta_boxes', function() {
	foreach (['belege_kategoriendiv', 'beleg_kategoriediv'] as $box) {
		remove_meta_box($box, basename(__DIR__), 'side');
	}
}, 99);

// Direkten Aufruf der Kategorien-Seite wegleiten
\add_action('load-edit-tags.php', function () {
	$tax  = $_GET['taxonomy']   ?? '';
	$pt   = $_GET['post_type']  ?? '';
	$want = ['belege_kategorien','beleg_kategorie'];
	if ($pt === basename(__DIR__) && in_array($tax, $want, true)) {
		wp_safe_redirect(admin_url('edit.php?post_type=' . basename(__DIR__)));
		exit;
	}
});


// Define: Const 4 @ll CPT Fields
// cmx_define_meta_constants(basename(__DIR__), ['umsatz']);


// Include: @ll metaboxes
cmx_require_files(__DIR__,'kopfdaten,positionen,konditionen,mwst,dokumente,admincolumns,notizen,summen,vorlage_pdf,meta_action,logfile,vorlage_qr_code,add_tasks');
