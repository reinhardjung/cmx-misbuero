<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


// Define: Custom-Post-Type based on DIR
register_post_type(basename(__DIR__), ['labels' => ['name' => cmx_sani_key(basename(__DIR__), 'title'), 'singular_name' => cmx_sani_key(basename(__DIR__), 'title'), 'add_new_item' => 'Hinzufügen', 'edit_item' => 'Bearbeiten',],
	'menu_position' => 20, 'supports' => ['title', 'editor'], 'public' => true, 'menu_icon' => 'dashicons-cart', 'show_in_rest' => true, 'has_archive' => true, 'rewrite' => ['slug' => basename(__DIR__)],
]);


// Define: CONST 4 @ll Taxos
define(__NAMESPACE__ . '\\CMX_TAX_'.strtoupper(basename(__DIR__)),'Marken,Farben,Einheiten,Typen,Kategorien,Grössen,Materialien,Ausführungen');


// Define: CONST 4 each Taxo
cmx_const_taxos(cmx_sani_key(basename(__DIR__),'upper'),basename(__DIR__), CMX_TAX_ARTIKEL);
// cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), define('\\CMX_TAX_',strtoupper(basename(__DIR__))));


// Create: @ll Taxos
\add_action('init', function () {
	cmx_create_taxo(basename(__DIR__), 'Kategorie', 'Kategorien');
	cmx_create_taxo(basename(__DIR__), 'Type', 'Typen');
	cmx_create_taxo(basename(__DIR__), 'Marke', 'Marken');
	cmx_create_taxo(basename(__DIR__), 'Farbe', 'Farben');
	cmx_create_taxo(basename(__DIR__), 'Einheit', 'Einheiten');
	cmx_create_taxo(basename(__DIR__), 'Groesse', 'Grössen');
	cmx_create_taxo(basename(__DIR__), 'Ausfuehrung', 'Ausführungen');
	cmx_create_taxo(basename(__DIR__), 'Material', 'Materialien');
}, 15);


// Refill: Taxo with defaults if removed
\add_action('admin_init', function () {
	cmx_seed_taxo(cmx_sani_key(basename(__DIR__),'title'),CMX_TAX_ARTIKEL);
});


// Define: Const 4 @ll CPT Fields
cmx_define_meta_constants(basename(__DIR__), 'sku,anzahl,ek,vk,marge,waehrungen,verkaufbar,katalog,lieferant,lieferzeit,lieferant_nr,bezugsquelle,lagerbestand');


// Include: @ll metaboxes
cmx_require_files(__DIR__,'stammdaten,lieferanten,belegtext,konditionen,admincolumns,doppelte,liste_artikel,liste_artikel detail,qr-code,exports,imports,bilder,preview,status');


// Halte den Slug immer synchron mit dem Titel beim Speichern.
\add_action('save_post_artikel', __NAMESPACE__ . '\\cmx_sync_artikel_slug', 10, 3);
function cmx_sync_artikel_slug(int $post_id, \WP_Post $post, bool $update): void {
	if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
		return;
	}

	$title = trim($post->post_title);
	if ($title === '') return;

	$new_slug = sanitize_title($title);
	if ($new_slug === '') return;

	$unique_slug = wp_unique_post_slug($new_slug, $post_id, $post->post_status, $post->post_type, $post->post_parent);
	if ($unique_slug === $post->post_name) return;


	// Temporär abklemmen, um keine Endlosschleife auszulösen.
	remove_action('save_post_artikel', __NAMESPACE__ . '\\cmx_sync_artikel_slug', 10);
	wp_update_post(['ID' => $post_id, 'post_name' => $unique_slug]);
	add_action('save_post_artikel', __NAMESPACE__ . '\\cmx_sync_artikel_slug', 10, 3);
}

// CMX_TAX_BELEGE_KATEGORIEN?
// cmx_show_consts(); exit;
