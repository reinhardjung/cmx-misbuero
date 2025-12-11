<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


// Define: Custom-Post-Type based on DIR
register_post_type(basename(__DIR__), ['labels' => ['name' => cmx_sani_key(basename(__DIR__), 'title'), 'singular_name' => cmx_sani_key(basename(__DIR__), 'title'), 'add_new_item' => 'Hinzufügen', 'edit_item' => 'Bearbeiten',],
	'menu_position' => 70, 'supports' => ['title', 'editor', 'thumbnail'], 'public' => true, 'menu_icon' => 'dashicons-businessman', 'show_in_rest' => true, 'has_archive' => true, 'rewrite' => ['slug' => basename(__DIR__)],
]);


// Define: CONST 4 @ll Taxos
define(__NAMESPACE__ . '\\CMX_TAX_'.strtoupper(basename(__DIR__)),'Kategorien');


// Define: CONST 4 each Taxo
cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), CMX_TAX_BUCHHALTUNG);
// cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), const('\\CMX_TAX_'.strtoupper(basename(__DIR__))));
// var_dump(CMX_TAX_BUCHHALTUNG); exit;


// Create: @ll Taxos
\add_action('init', function () {
	cmx_create_taxo(basename(__DIR__), 'Kategorie', 'Kategorien');
}, 15);


// Refill: Taxo with defaults if removed
\add_action('admin_init', function () {
	cmx_seed_taxo(cmx_sani_key(basename(__DIR__),'title'),CMX_TAX_BUCHHALTUNG);
});


// Define: Const 4 @ll CPT Fields
// cmx_define_meta_constants(basename(__DIR__), ['umsatz']);


// Include: @ll metaboxes
cmx_require_files(__DIR__,'modules,status,validity,admincolumns');
// require_once __DIR__ . '/modules.php';
// require_once __DIR__ . '/status.php';
// require_once __DIR__ . '/validity.php';


/**
 * Wenn ein Dokument ein Beitragsbild erhält und noch keinen Titel hat,
 * wird der (sanitisierte, kleingeschriebene) Dateiname des Bildes als Titel gesetzt.
 */
function cmx_dokumente_autofill_title(int $post_id, \WP_Post $post): void {
	if ($post->post_type !== 'dokumente') return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (wp_is_post_revision($post_id)) return;
	if (!current_user_can('edit_post', $post_id)) return;

	if (trim((string) $post->post_title) !== '') return; // Titel existiert bereits

	$thumb_id = (int) get_post_thumbnail_id($post_id);
	if (!$thumb_id) return;

	$filename = '';
	$path = get_attached_file($thumb_id);
	if ($path) {
		$filename = pathinfo($path, PATHINFO_FILENAME);
	}
	if ($filename === '') {
		$att = get_post($thumb_id);
		$filename = $att?->post_title ?? '';
	}

	$filename = strtolower($filename);
	$filename = str_replace(['_', '-'], ' ', $filename);
	$filename = sanitize_text_field($filename);
	if ($filename === '') return;

	remove_action('save_post_dokumente', __NAMESPACE__ . '\\cmx_dokumente_autofill_title', 10);
	wp_update_post([
		'ID'         => $post_id,
		'post_title' => $filename,
	]);
	add_action('save_post_dokumente', __NAMESPACE__ . '\\cmx_dokumente_autofill_title', 10, 2);
}
\add_action('save_post_dokumente', __NAMESPACE__ . '\\cmx_dokumente_autofill_title', 10, 2);
