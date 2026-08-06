<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


// Define: Custom-Post-Type based on DIR
register_post_type(basename(__DIR__), ['labels' => ['name' => 'Budget', 'singular_name' => cmx_sani_key(basename(__DIR__), 'title'), 'add_new_item' => 'Hinzufügen', 'edit_item' => 'Bearbeiten',],
	'menu_position' => 85, 'supports' => ['title', 'editor'], 'public' => true, 'menu_icon' => 'dashicons-info-outline', 'show_in_rest' => true, 'has_archive' => true, 'rewrite' => ['slug' => basename(__DIR__)],
]);


// Define: CONST 4 @ll Taxos
define(__NAMESPACE__ . '\\CMX_TAX_'.strtoupper(basename(__DIR__)),'Kategorien');


// Define: CONST 4 each Taxo
cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), CMX_TAX_BUDGET);
// cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), define('\\CMX_TAX_',strtoupper(basename(__DIR__))));


// Create: @ll Taxos
\add_action('init', function () {
	cmx_create_taxo(basename(__DIR__), 'Kategorie', 'Kategorien');
}, 15);


// Refill: Taxo with defaults if removed
\add_action('admin_init', function () {
	cmx_seed_taxo(cmx_sani_key(basename(__DIR__),'title'),CMX_TAX_BUDGET);
});


// Define: Const 4 @ll CPT Fields
// cmx_define_meta_constants(basename(__DIR__), ['umsatz']);


// Fill: empty post title on save
\add_filter('wp_insert_post_data', function (array $data, array $postarr): array {
	if ((string) ($data['post_type'] ?? ($postarr['post_type'] ?? '')) !== 'budget') {
		return $data;
	}
	if ((string) ($data['post_status'] ?? '') === 'auto-draft') {
		return $data;
	}

	$title = \trim((string) ($data['post_title'] ?? ''));
	if ($title !== '') {
		return $data;
	}

	$fallback_title = 'Belegname fehlt...';
	$data['post_title'] = $fallback_title;
	if (\trim((string) ($data['post_name'] ?? '')) === '') {
		$data['post_name'] = \sanitize_title($fallback_title);
	}

	return $data;
}, 20, 2);


// Include: @ll metaboxes
cmx_require_files(__DIR__,'stammdaten,kosten,kontakt,imports,exports,overview,admincolumns');
