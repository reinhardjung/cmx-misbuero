<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

// fixme rju 2026-01-17: Braucht es das noch?
function cmx_is_cloud_meister_user(): bool {
	$user = wp_get_current_user();
	return $user && $user->exists() && $user->display_name === 'CLOUD Meister';
}

function cmx_belege_kategorie_taxonomy(): ?string {
	foreach (['belege_kategorien','belege_kategorie','beleg_kategorien','beleg_kategorie','belege_categories','belege_typ','belege_themen'] as $tax) {
		if (taxonomy_exists($tax)) return $tax;
	}
	return null;
}


// Define: Custom-Post-Type based on DIR
register_post_type(basename(__DIR__), ['labels' => ['name' => cmx_sani_key(basename(__DIR__), 'title'), 'singular_name' => cmx_sani_key(basename(__DIR__), 'title'), 'add_new_item' => 'Hinzufügen', 'edit_item' => 'Bearbeiten',],
	'menu_position' => 30, 'supports' => ['title'], 'public' => true, 'menu_icon' => 'dashicons-media-text', 'show_in_rest' => true, 'has_archive' => true, 'rewrite' => ['slug' => basename(__DIR__)],
]);


// Define: CONST 4 @ll Taxos
define(__NAMESPACE__ . '\\CMX_TAX_'.strtoupper(basename(__DIR__)),'Kategorien,MwSt,Waehrungen,Buchungsarten,Zahlungsgrund,Zahlungsarten');


// Define: CONST 4 each Taxo
cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), CMX_TAX_BELEGE);
// cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), constant('CMX_TAX_'.strtoupper(basename(__DIR__))));


// Create: @ll Taxos
\add_action('init', function () {
	// Kategorien: UI komplett ausblenden, Taxonomie bleibt für Abfragen bestehen
	$show_ui = cmx_is_cloud_meister_user();
	cmx_create_taxo(basename(__DIR__), 'Kategorie', 'Kategorien', false, true, ['show_ui' => $show_ui]);
	cmx_create_taxo(basename(__DIR__), 'MwSt', 'MwSt', false);
	cmx_create_taxo(basename(__DIR__), 'Währung', 'Währungen', false, false, ['show_ui' => true, 'meta_box_cb' => false]);
	// cmx_create_taxo(basename(__DIR__), 'Buchungsart', 'Buchungsarten');
	cmx_create_taxo(basename(__DIR__), 'Zahlungsgrund', 'Zahlungsgrund');
	cmx_create_taxo(basename(__DIR__), 'Zahlungsart', 'Zahlungsarten');
	// cmx_create_taxo(basename(__DIR__), 'Land', 'Länder', false); // REchungna ls default, genaus wioe Schwiez...
}, 15);


// Refill: Taxo with defaults if removed
\add_action('admin_init', function () {
	cmx_seed_taxo(cmx_sani_key(basename(__DIR__),'title'),CMX_TAX_BELEGE);
});

// Beleg-Kategorien sicherstellen (Rechnung, Gutschrift, Sonstiges)
\add_action('admin_init', function () {
	$tax = cmx_belege_kategorie_taxonomy();
	if (!$tax) return;

	$required = [
		'rechnung'  => 'Rechnung',
		'gutschrift'=> 'Gutschrift',
		'sonstiges' => 'Sonstiges',
	];
	foreach ($required as $slug => $label) {
		if (!term_exists($slug, $tax) && !term_exists($label, $tax)) {
			wp_insert_term($label, $tax, ['slug' => $slug]);
		}
	}
});

// Kategorien in der Admin-Navigation ausblenden (Taxonomie bleibt bestehen)
\add_action('admin_menu', function () {
	if (cmx_is_cloud_meister_user()) {
		return;
	}
	$parent = 'edit.php?post_type=' . basename(__DIR__);
	foreach (['belege_kategorien', 'beleg_kategorie', 'belege_zahlungsarten', 'belege_zahlungsart'] as $tax) {
		remove_submenu_page($parent, 'edit-tags.php?taxonomy=' . $tax . '&post_type=' . basename(__DIR__));
		remove_submenu_page($parent, 'edit-tags.php?taxonomy=' . $tax); // ggf. Variante ohne post_type
	}
}, 99);

// Kategorien-Metabox auf dem Beleg-Edit-Screen ausblenden (nicht editierbar)
\add_action('add_meta_boxes', function() {
	if (cmx_is_cloud_meister_user()) {
		return;
	}
	foreach (['belege_kategoriendiv', 'beleg_kategoriediv'] as $box) {
		remove_meta_box($box, basename(__DIR__), 'side');
	}
}, 99);

// Direkten Aufruf der Kategorien-Seite wegleiten
\add_action('load-edit-tags.php', function () {
	if (cmx_is_cloud_meister_user()) {
		return;
	}
	$tax  = $_GET['taxonomy']   ?? '';
	$pt   = $_GET['post_type']  ?? '';
	$want = ['belege_kategorien','beleg_kategorie'];
	if ($pt === basename(__DIR__) && in_array($tax, $want, true)) {
		wp_safe_redirect(admin_url('edit.php?post_type=' . basename(__DIR__)));
		exit;
	}
});

// Hinweis: Reihenfolge wird über "Save box position" gespeichert.


// Define: Const 4 @ll CPT Fields
// cmx_define_meta_constants(basename(__DIR__), ['umsatz']);


// Include: @ll metaboxes
cmx_require_files(__DIR__,'kopfdaten,positionen,konditionen,mwst,admincolumns,notizen,summen,anzahlungen,vorlage_pdf,meta_action,logfile,vorlage_qr_code,add_tasks,exports');
