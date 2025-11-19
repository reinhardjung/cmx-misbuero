<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

/**
 * CPT "kontakte"
 */
\register_post_type('kontakte', [
	'label' => 'kontakt',
	'singular_name' => 'Kontakte',
	'labels' => [
		'menu_name' => 'Kontakte',
		'all_items' => 'Übersicht',
		'add_new' => 'Kontakt hinzufügen',
		'add_new_item' => 'Neuen Kontakt',
		'edit_item' => 'Kontakt bearbeiten',
		'new_item' => 'Neuer Kontakt',
		'view_item' => 'Kontakt ansehen',
		'view_items' => 'Kontakte ansehen',
		'search_items' => 'Kontakte suchen',
		'not_found' => 'Keine Kontakte gefunden',
		'not_found_in_trash' => 'Keine Kontakte im Papierkorb',
		'parent_item_colon' => 'Übergeordnet',
		'featured_image' => 'Beitragsbild',
		'set_featured_image' => 'Beitragsbild festlegen',
		'remove_featured_image' => 'Beitragsbild entfernen',
		'use_featured_image' => 'Als Beitragsbild verwenden',
		'archives' => 'Archiv',
		'insert_into_item' => 'Einfügen',
		'uploaded_to_this_item' => 'Hierher hochgeladen',
		'filter_items_list' => 'Kontaktliste filtern',
		'items_list_navigation' => 'Kontaktliste Navigation',
		'items_list' => 'Kontaktliste',
		'filter_by_date' => 'Nach Datum filtern',
		'item_published' => 'Kontakt veröffentlicht',
		'item_published_privately' => 'Kontakt privat veröffentlicht',
		'item_reverted_to_draft' => 'Kontakt als Entwurf',
		'item_scheduled' => 'Kontakt geplant',
		'item_updated' => 'Kontakt aktualisiert',
	],
	'public' => true,
	'publicly_queryable' => true,
	'query_var' => true,
	'menu_icon' => 'dashicons-businessman',
	'rewrite' => true,
	'capability_type' => 'post',
	'menu_position' => 10,
	'supports' => [ 'title', 'thumbnail', 'excerpt' ], // Titel = Firmenname, Bild, Kurzbeschreibung
	'has_archive' => true,
	'show_in_rest' => true,
	'show_ui' => true,
	'show_in_menu' => true,
	'show_in_nav_menus' => true,
	'show_in_admin_bar' => true,
	'with_front' => true,
	'front_url_prefix' => 'kontakte',
	'show_in_graphql' => true,
	'graphql_single_name' => 'kontakte',
	'graphql_plural_name' => 'kontakt',
]);

/**
 * Taxonomie "Beziehungen" (dein bestehender kontakt_type)
 */
\register_taxonomy('kontakt_type', ['kontakte'], [
	'hierarchical' => true,
	'label' => 'kontakt_types',
	'singular_label' => 'kontakt_type',
	'show_ui' => true,
	'query_var' => null,
	'show_admin_column' => null,
	'show_in_rest' => true,
	'labels' => [
		'name' => 'Beziehungen',
		'singular_name' => 'kontakt_type',
		'search_items' => 'Search kontakt_types',
		'popular_items' => 'Popular kontakt_types',
		'all_items' => 'All kontakt_types',
		'parent_item' => 'Parent kontakt_type',
		'parent_item_colon' => 'Parent item',
		'edit_item' => 'Edit',
		'view_item' => 'View',
		'update_item' => 'Update kontakt_type',
		'add_new_item' => 'Add new kontakt_type',
		'new_item_name' => 'New kontakt_type',
		'separate_items_with_commas' => 'Separate kontakt_types with commas',
		'add_or_remove_items' => 'Add or remove kontakt_types',
		'choose_from_most_used' => 'Choose from most used kontakt_type',
		'not_found' => 'No kontakt_type found',
		'no_terms' => 'No kontakt_types',
		'filter_by_item' => 'Filter by kontakt_type',
		'items_list_navigation' => 'Navigation list kontakt_types',
		'items_list' => 'List kontakt_types',
		'most_used' => 'Most used kontakt_types',
		'back_to_items' => 'Back to kontakt_types',
	],
	'public' => true,
	'publicly_queryable' => true,
	'show_in_menu' => true,
	'show_in_nav_menus' => true,
	'show_tagcloud' => true,
	'show_in_quick_edit' => true,
	'capabilities' => [
		'manage_terms',
		'edit_terms',
		'delete_terms',
		'assign_terms',
	],
]);

/**
 * Taxonomie "rechtsform" – standard Sidebar-Metabox (NICHT unterdrücken)
 */
\add_action('init', __NAMESPACE__ . '\\cmx_register_rechtsform_taxonomy', 11);
function cmx_register_rechtsform_taxonomy() {
	$labels = [
		'name'          => 'Rechtsformen',
		'singular_name' => 'Rechtsform',
		'all_items'     => 'Alle Rechtsformen',
		'edit_item'     => 'Rechtsform bearbeiten',
		'view_item'     => 'Rechtsform ansehen',
		'update_item'   => 'Rechtsform aktualisieren',
		'add_new_item'  => 'Neue Rechtsform hinzufügen',
		'new_item_name' => 'Name der neuen Rechtsform',
		'search_items'  => 'Rechtsformen durchsuchen',
		'not_found'     => 'Keine Rechtsformen gefunden',
		'back_to_items' => 'Zurück zu Rechtsformen',
	];
	$args = [
		'hierarchical'      => true,
		'labels'            => $labels,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'public'            => true,
		'rewrite'           => [ 'slug' => 'rechtsform' ],
		// KEIN 'meta_box_cb' => false; => Sidebar-Metabox bleibt sichtbar
	];
	\register_taxonomy('rechtsform', ['kontakte'], $args);
	\register_taxonomy_for_object_type('rechtsform', 'kontakte');
}

/**
 * --- STAMMDATEN ---
 * Wir behalten NUR Vorname/Nachname als eigene Metadaten in der Haupt-Metabox.
 * Das frühere Meta "Firmenname" ENTFÄLLT (Titel = Firmenname).
 */
const CMX_KONTAKTE_META_VORNAME  = '_cmx_kontakte_vorname';
const CMX_KONTAKTE_META_NACHNAME = '_cmx_kontakte_nachname';

\add_action('init', __NAMESPACE__ . '\\cmx_register_kontakte_meta');
function cmx_register_kontakte_meta() {
	$args_text = [
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'auth_callback'     => function () { return current_user_can('edit_posts'); },
		'sanitize_callback' => 'sanitize_text_field',
	];
	\register_post_meta('kontakte', CMX_KONTAKTE_META_VORNAME,  $args_text);
	\register_post_meta('kontakte', CMX_KONTAKTE_META_NACHNAME, $args_text);
}

/**
 * Metabox "Stammdaten" (ohne Firmenname; Rechtsform ist in der Sidebar)
 */
\add_action('add_meta_boxes', __NAMESPACE__ . '\\cmx_kontakte_add_metabox');
function cmx_kontakte_add_metabox() {
	\add_meta_box(
		'cmx_kontakte_stammdaten',
		'Stammdaten',
		__NAMESPACE__ . '\\cmx_kontakte_metabox_render',
		'kontakte',
		'normal',
		'default'
	);
}

function cmx_kontakte_metabox_render(\WP_Post $post) {
	\wp_nonce_field('cmx_kontakte_save_meta', 'cmx_kontakte_nonce');

	$vorname  = \get_post_meta($post->ID, CMX_KONTAKTE_META_VORNAME, true);
	$nachname = \get_post_meta($post->ID, CMX_KONTAKTE_META_NACHNAME, true);

	echo '<style>
		.cmx-row { display:flex; gap:16px; align-items:flex-start; flex-wrap:wrap; margin-bottom:8px; }
		.cmx-col { flex:1 1 280px; min-width:260px; }
		.cmx-col input.regular-text { width:100%; max-width:100%; }
		@media (max-width:600px){ .cmx-row{ flex-direction:column; } }
	</style>';

	// Reihe: Vorname + Nachname
	echo '<div class="cmx-row">';
		echo '<div class="cmx-col">
				<p><label for="cmx_vorname"><strong>Vorname</strong></label><br />
				<input type="text" id="cmx_vorname" name="cmx_vorname" value="' . esc_attr($vorname) . '" class="regular-text" /></p>
			</div>';
		echo '<div class="cmx-col">
				<p><label for="cmx_nachname"><strong>Nachname</strong></label><br />
				<input type="text" id="cmx_nachname" name="cmx_nachname" value="' . esc_attr($nachname) . '" class="regular-text" /></p>
			</div>';
	echo '</div>';
}

/**
 * Speichern Vorname/Nachname
 */
\add_action('save_post_kontakte', __NAMESPACE__ . '\\cmx_kontakte_save_meta');
function cmx_kontakte_save_meta($post_id) {
	if (!isset($_POST['cmx_kontakte_nonce']) || !\wp_verify_nonce($_POST['cmx_kontakte_nonce'], 'cmx_kontakte_save_meta')) return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!current_user_can('edit_post', $post_id)) return;

	$map = [
		'cmx_vorname'  => CMX_KONTAKTE_META_VORNAME,
		'cmx_nachname' => CMX_KONTAKTE_META_NACHNAME,
	];
	foreach ($map as $field => $meta_key) {
		if (isset($_POST[$field])) {
			\update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$field]));
		}
	}
}

/**
 * Excerpt (Kurzbeschreibung) nach ganz unten im Hauptbereich
 */
\add_action('add_meta_boxes', function () {
	\remove_meta_box('postexcerpt', 'kontakte', 'normal');
	\add_meta_box('postexcerpt', __('Kurzbeschreibung'), 'post_excerpt_meta_box', 'kontakte', 'normal', 'low');
}, 20);

/**
 * --- UI-Feinschliff ---
 * 1) Titel-Placeholder/Label -> "Firmenname"
 * 2) Permalink-Zeile unter dem Titel ausblenden
 */
\add_filter('enter_title_here', function ($placeholder, $post) {
	if ($post && $post->post_type === 'kontakte') {
		return 'Firmenname';
	}
	return $placeholder;
}, 10, 2);

/**
 * Vorsichtige Übersetzung nur auf dem Kontakte-Screen:
 * ersetzt "Titel" / "Add title" im Editor-Kontext durch "Firmenname".
 */
\add_filter('gettext', function ($translated, $text, $domain) {
	$screen = function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || $screen->post_type !== 'kontakte') {
		return $translated;
	}
	// Deutsch & Englisch gängige Strings abfangen
	if ($text === 'Titel' || $text === 'Add title' || $text === 'Title') {
		return 'Firmenname';
	}
	return $translated;
}, 10, 3);

/**
 * Permalink-Zeile (klassische Ausgabe unter dem Titel) ausblenden
 * – greift auch im Block-Editor für die Stelle unter dem Titel.
 */
\add_filter('get_sample_permalink_html', function ($html, $post_id, $new_title, $new_slug, $post) {
	if ($post && $post->post_type === 'kontakte') {
		return ''; // nichts anzeigen
	}
	return $html;
}, 10, 5);

/**
 * (Optional) Zusätzliche Spalten – Titelspalte zeigt bereits Firmenname (Titel).
 * Vorname/Nachname zusätzlich in der Liste:
 */
\add_filter('manage_kontakte_posts_columns', function ($cols) {
	$injected = [];
	foreach ($cols as $k => $v) {
		$injected[$k] = $v;
		if ($k === 'title') {
			$injected['cmx_vorname']  = 'Vorname';
			$injected['cmx_nachname'] = 'Nachname';
		}
	}
	return $injected;
});
\add_action('manage_kontakte_posts_custom_column', function ($column, $post_id) {
	if ($column === 'cmx_vorname') {
		echo esc_html(\get_post_meta($post_id, CMX_KONTAKTE_META_VORNAME, true));
	} elseif ($column === 'cmx_nachname') {
		echo esc_html(\get_post_meta($post_id, CMX_KONTAKTE_META_NACHNAME, true));
	}
}, 10, 2);



// /**
//  * Sidebar-Reihenfolge für CPT "kontakte":
//  * 1) Beitragsbild, 2) Rechtsform, 3) Beziehungen
//  */
// \add_action('add_meta_boxes', __NAMESPACE__ . '\\cmx_kontakte_reorder_side_boxes', 100);
// function cmx_kontakte_reorder_side_boxes() {
// 	$post_type = 'kontakte';

// 	// Vorhandene Boxen entfernen (falls bereits registriert)
// 	\remove_meta_box('postimagediv',   $post_type, 'side');        // Beitragsbild
// 	\remove_meta_box('rechtsformdiv',  $post_type, 'side');        // Hierarchische Taxonomie "rechtsform"
// 	\remove_meta_box('kontakt_typediv',$post_type, 'side');        // Hierarchische Taxonomie "kontakt_type"

// 	// 1) Beitragsbild
// 	\add_meta_box(
// 		'postimagediv',
// 		__('Beitragsbild'),
// 		'post_thumbnail_meta_box',
// 		$post_type,
// 		'side',
// 		'high'
// 	);

// 	// 2) Rechtsform (hierarchische Taxonomie => Kategorien-Metabox)
// 	\add_meta_box(
// 		'rechtsformdiv',
// 		__('Rechtsformen'),
// 		'post_categories_meta_box',
// 		$post_type,
// 		'side',
// 		'default',
// 		['taxonomy' => 'rechtsform']
// 	);

// 	// 3) Beziehungen (kontakt_type – hierarchisch)
// 	\add_meta_box(
// 		'kontakt_typediv',
// 		__('Beziehungen'),
// 		'post_categories_meta_box',
// 		$post_type,
// 		'side',
// 		'low',
// 		['taxonomy' => 'kontakt_type']
// 	);
// }
