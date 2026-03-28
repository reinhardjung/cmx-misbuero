<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


// Define: Custom-Post-Type based on DIR
register_post_type(basename(__DIR__), ['labels' => ['name' => cmx_sani_key(basename(__DIR__), 'title'), 'singular_name' => cmx_sani_key(basename(__DIR__), 'title'), 'add_new_item' => 'Hinzufügen', 'edit_item' => 'Bearbeiten',],
	'menu_position' => 20, 'supports' => ['title', 'editor'], 'public' => true, 'menu_icon' => 'dashicons-tag', 'show_in_rest' => true, 'has_archive' => true, 'rewrite' => ['slug' => basename(__DIR__)],
]);

\add_filter('wp_editor_settings', function (array $settings, string $editor_id): array {
	if ($editor_id !== 'content') {
		return $settings;
	}

	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== basename(__DIR__)) {
		return $settings;
	}

	$settings['textarea_rows'] = 5;
	$settings['editor_height'] = 120;
	if (($settings['tinymce'] ?? true) !== false) {
		$settings['tinymce'] = \is_array($settings['tinymce'] ?? null) ? $settings['tinymce'] : [];
		$settings['tinymce']['height'] = 120;
	}

	return $settings;
}, 10, 2);

\add_action('admin_head', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== basename(__DIR__)) {
		return;
	}
	echo '<style>
		#postdivrich #wp-content-editor-container textarea.wp-editor-area,
		#postdivrich #content,
		#postdivrich .mce-edit-area iframe{
			height:120px !important;
			min-height:120px !important;
		}
	</style>';
});

\add_action('admin_print_footer_scripts', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== basename(__DIR__)) {
		return;
	}
	?>
	<script>
	(function(){
		const height = 120;
		function applyEditorHeight() {
			document.querySelectorAll('#postdivrich #wp-content-editor-container textarea.wp-editor-area, #postdivrich #content, #postdivrich .mce-edit-area iframe').forEach(function(el){
				el.style.height = height + 'px';
				el.style.minHeight = height + 'px';
			});
			if (window.tinymce) {
				const editor = window.tinymce.get('content');
				if (editor && editor.iframeElement) {
					editor.iframeElement.style.height = height + 'px';
					editor.iframeElement.style.minHeight = height + 'px';
				}
			}
		}
		document.addEventListener('DOMContentLoaded', applyEditorHeight, {once:true});
		window.addEventListener('load', applyEditorHeight, {once:true});
		let runs = 0;
		const timer = window.setInterval(function(){
			applyEditorHeight();
			runs += 1;
			if (runs >= 20) {
				window.clearInterval(timer);
			}
		}, 250);
	})();
	</script>
	<?php
});


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
