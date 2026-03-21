<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


// Define: Custom-Post-Type based on DIR
register_post_type(basename(__DIR__), ['labels' => ['name' => cmx_sani_key(basename(__DIR__), 'title'), 'singular_name' => cmx_sani_key(basename(__DIR__), 'title'), 'add_new_item' => 'Hinzufügen', 'edit_item' => 'Bearbeiten',],
	'menu_position' => 70, 'supports' => ['title', 'editor'], 'public' => true, 'menu_icon' => 'dashicons-portfolio', 'show_in_rest' => true, 'has_archive' => true, 'rewrite' => ['slug' => basename(__DIR__)],
]);


// Define: CONST 4 @ll Taxos
define(__NAMESPACE__ . '\\CMX_TAX_'.strtoupper(basename(__DIR__)),'Kategorien,Status');


// Define: CONST 4 each Taxo
cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), CMX_TAX_PROJEKTE);
// cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), define('\\CMX_TAX_',strtoupper(basename(__DIR__))));


// Create: @ll Taxos
\add_action('init', function () {
	cmx_create_taxo(basename(__DIR__), 'Kategorie', 'Kategorien');
	cmx_create_taxo(basename(__DIR__), 'Status', 'Status', false);
	// cmx_create_taxo(basename(__DIR__), 'Type', 'Typen', false);
	// cmx_create_taxo(basename(__DIR__), 'Land', 'Länder', false); // REchungna ls default, genaus wioe Schwiez...
}, 15);


// Refill: Taxo with defaults if removed
\add_action('admin_init', function () {
	cmx_seed_taxo(cmx_sani_key(basename(__DIR__),'title'),CMX_TAX_PROJEKTE);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_projekte_make_taxonomy_metabox_title_link')) {
	function cmx_projekte_make_taxonomy_metabox_title_link(string $taxonomy, string $box_id, string $fallback_label): void {
		if ($taxonomy === '' || !\taxonomy_exists($taxonomy)) {
			return;
		}

		$url = \admin_url('edit-tags.php?taxonomy=' . \rawurlencode($taxonomy) . '&post_type=' . \rawurlencode(basename(__DIR__)));

		echo '<script>';
		echo 'document.addEventListener("DOMContentLoaded",function(){';
		echo 'var box=document.getElementById(' . \wp_json_encode($box_id) . ');';
		echo 'if(!box)return;';
		echo 'var title=box.querySelector(".postbox-header .hndle, .postbox-header h2, .hndle, h2.hndle");';
		echo 'if(!title||title.querySelector("a[data-cmx-tax-link=\\"1\\"]"))return;';
		echo 'var text=(title.textContent||"").trim()||' . \wp_json_encode($fallback_label) . ';';
		echo 'title.textContent="";';
		echo 'var link=document.createElement("a");';
		echo 'link.href=' . \wp_json_encode($url) . ';';
		echo 'link.target="_blank";';
		echo 'link.rel="noopener noreferrer";';
		echo 'link.dataset.cmxTaxLink="1";';
		echo 'link.style.textDecoration="none";';
		echo 'link.style.color="inherit";';
		echo 'link.style.font="inherit";';
		echo 'link.style.fontSize="inherit";';
		echo 'link.style.fontWeight="inherit";';
		echo 'link.style.lineHeight="inherit";';
		echo 'link.textContent=text;';
		echo 'title.appendChild(link);';
		echo '});';
		echo '</script>';
	}
}

\add_action('admin_print_footer_scripts', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== basename(__DIR__) || (string) ($screen->base ?? '') !== 'post') {
		return;
	}

	if (\defined(__NAMESPACE__ . '\\TAX_PROJEKTE_KATEGORIEN')) {
		$taxonomy = (string) \constant(__NAMESPACE__ . '\\TAX_PROJEKTE_KATEGORIEN');
		cmx_projekte_make_taxonomy_metabox_title_link($taxonomy, $taxonomy . 'div', 'Kategorien');
		cmx_projekte_make_taxonomy_metabox_title_link($taxonomy, 'tagsdiv-' . $taxonomy, 'Kategorien');
	}

	if (\function_exists(__NAMESPACE__ . '\\cmx_projekte_status_tax')) {
		$status_tax = (string) cmx_projekte_status_tax();
		cmx_projekte_make_taxonomy_metabox_title_link($status_tax, 'cmx_projekte_status_side', 'Status');
	}
});


// Define: Const 4 @ll CPT Fields
// cmx_define_meta_constants(basename(__DIR__), ['umsatz']);


// Include: @ll metaboxes
// cmx_require_files(__DIR__,'stammdaten, kontakt, admincolumns, exports, imports, dokumente, tasks, tasks-side');
cmx_require_files(__DIR__,'stammdaten, kontakt, status, admincolumns, exports, imports, exports_pdf, tasks, tasks-side, ext_time');
