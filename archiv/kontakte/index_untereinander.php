<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') or die('Oxytocin!');


register_post_type('kontakte', ['label' => 'kontakt','singular_name' => 'Kontakte','labels' => [
	'menu_name' => 'Kontakte',
	'all_items' => 'Übersicht',
	'add_new' => 'Add Kontakte',
	'add_new_item' => 'Neuen Kontakt',
	'edit_item' => 'Edit kontakte',
	'new_item' => 'New kontakte',
	'view_item' => 'View kontakte',
	'view_items' => 'View kontakt',
	'search_item' => 'Search kontakt',
	'not_found' => 'No kontakte found',
	'not_found_in_trash' => 'No kontakte found',
	'parent_item_colon' => 'Parent item',
	'featured_image' => 'Featured image',
	'set_featured_image' => 'Set featured image',
	'remove_featured_image' => 'Remove featured image',
	'use_featured_image' => 'Use featured image',
	'archives' => 'Archives',
	'insert_into_item' => 'Insert',
	'uploaded_to_this_item' => 'Upload',
	'filter_items_list' => 'Filter kontakt list',
	'items_list_navigation' => 'Navigation list kontakt',
	'items_list' => 'List kontakt',
	'filter_by_date' => 'Filter by date',
	'item_published' => 'kontakte published',
	'item_published_privately' => 'kontakte published privately',
	'item_reverted_to_draft' => 'kontakte reverted to draft',
	'item_scheduled' => 'kontakte scheduled',
	'item_updated' => 'kontakte updated',
	],
	'public' => true,
	'publicly_queryable' => true,
	'query_var' => true,
	'menu_icon' => 'dashicons-businessman',
	'rewrite' => true,
	'capability_type' => 'post',
	'hierarchical' => null,
	'menu_position' => 10,
	'supports' => [
		'title',
		'thumbnail',
		'excerpt',
	],
	'has_archive' => true,
	'show_in_rest' => true,
	'show_ui' => true,
	'show_in_menu' => true,
	'show_in_nav_menus' => true,
	'show_in_admin_bar' => true,
	'rest_base' => null,
	'with_front' => true,
	'custom_rewrite' => null,
	'custom_query_var' => null,
	'front_url_prefix' => 'kontakte',
	'show_in_graphql' => true,
	'graphql_single_name' => 'kontakte',
	'graphql_plural_name' => 'kontakt',
]);



register_taxonomy('kontakt_type',['kontakte',],[
	'hierarchical' => true,
	'label' => 'kontakt_types',
	'singular_label' => 'kontakt_type',
	'show_ui' => true,
	'query_var' => null,
	'show_admin_column' => null,
	'show_in_rest' => true,
	'rewrite' => null,
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
	'rest_base' => null,
	'rest_controller_class' => null,
	'show_tagcloud' => true,
	'show_in_quick_edit' => true,
	'capabilities' => [
		'manage_terms',
		'edit_terms',
		'delete_terms',
		'assign_terms',
		],
	'custom_rewrite' => null,
	'custom_query_var' => null,
	'default_term' => null,
	'sort' => null,
	]);



/**
 * Meta-Keys (neu: Firmenname/Firmenart)
 */
const CMX_KONTAKTE_META_FIRMENNAME = '_cmx_kontakte_firmenname';
const CMX_KONTAKTE_META_FIRMENART  = '_cmx_kontakte_firmenart';
const CMX_KONTAKTE_META_VORNAME    = '_cmx_kontakte_vorname';
const CMX_KONTAKTE_META_NACHNAME   = '_cmx_kontakte_nachname';

/**
 * 1) Meta registrieren
 */
\add_action('init', __NAMESPACE__ . '\\cmx_register_kontakte_meta');
function cmx_register_kontakte_meta() {
	$args_text = [
		'type'              => 'string',
		'description'       => 'Kontakt-Feld',
		'single'            => true,
		'show_in_rest'      => true,
		'auth_callback'     => function() { return current_user_can('edit_posts'); },
		'sanitize_callback' => 'sanitize_text_field',
	];

	\register_post_meta('kontakte', CMX_KONTAKTE_META_FIRMENNAME, $args_text);
	\register_post_meta('kontakte', CMX_KONTAKTE_META_FIRMENART,  $args_text);
	\register_post_meta('kontakte', CMX_KONTAKTE_META_VORNAME,    $args_text);
	\register_post_meta('kontakte', CMX_KONTAKTE_META_NACHNAME,   $args_text);
}

/**
 * 2) Metabox
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

	$firmenname = \get_post_meta($post->ID, CMX_KONTAKTE_META_FIRMENNAME, true);
	$firmenart  = \get_post_meta($post->ID, CMX_KONTAKTE_META_FIRMENART,  true);
	$vorname    = \get_post_meta($post->ID, CMX_KONTAKTE_META_VORNAME,    true);
	$nachname   = \get_post_meta($post->ID, CMX_KONTAKTE_META_NACHNAME,   true);

	// Kompaktes Flex-Layout (gilt nur in dieser Box)
	echo '<style>
		.cmx-row { display:flex; gap:16px; align-items:flex-start; flex-wrap:wrap; margin-bottom:8px; }
		.cmx-col { flex:1 1 280px; min-width:260px; }
		.cmx-col input.regular-text, .cmx-col select { width:100%; max-width:100%; }
		@media (max-width: 600px) { .cmx-row { flex-direction:column; } }
	</style>';

	// Reihe 1: Firmenname + Firmenart
	echo '<div class="cmx-row">';
		echo '<div class="cmx-col">
				<p><label for="cmx_firmenname"><strong>Firmenname</strong></label><br />
				<input type="text" id="cmx_firmenname" name="cmx_firmenname" value="' . esc_attr($firmenname) . '" class="regular-text" /></p>
			</div>';

		echo '<div class="cmx-col">
				<p><label for="cmx_firmenart"><strong>Firmenart</strong></label><br />
				<input type="text" id="cmx_firmenart" name="cmx_firmenart" value="' . esc_attr($firmenart) . '" class="regular-text" /></p>
				<!-- Alternative als Dropdown:
				<select id="cmx_firmenart" name="cmx_firmenart">
					<option value="">– bitte wählen –</option>
					<option value="Einzelunternehmen" ' . selected($firmenart, 'Einzelunternehmen', false) . '>Einzelunternehmen</option>
					<option value="GmbH" ' . selected($firmenart, 'GmbH', false) . '>GmbH</option>
					<option value="AG" ' . selected($firmenart, 'AG', false) . '>AG</option>
					<option value="Verein" ' . selected($firmenart, 'Verein', false) . '>Verein</option>
					<option value="Stiftung" ' . selected($firmenart, 'Stiftung', false) . '>Stiftung</option>
				</select>
				-->
			</div>';
	echo '</div>';

	// Reihe 2: Vorname + Nachname
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
 * 3) Speichern
 */
\add_action('save_post_kontakte', __NAMESPACE__ . '\\cmx_kontakte_save_meta');
function cmx_kontakte_save_meta($post_id) {
	if (!isset($_POST['cmx_kontakte_nonce']) || !\wp_verify_nonce($_POST['cmx_kontakte_nonce'], 'cmx_kontakte_save_meta')) { return; }
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return; }
	if (!current_user_can('edit_post', $post_id)) { return; }

	$map = [
		'cmx_firmenname' => CMX_KONTAKTE_META_FIRMENNAME,
		'cmx_firmenart'  => CMX_KONTAKTE_META_FIRMENART,
		'cmx_vorname'    => CMX_KONTAKTE_META_VORNAME,
		'cmx_nachname'   => CMX_KONTAKTE_META_NACHNAME,
	];
	foreach ($map as $field => $meta_key) {
		if (isset($_POST[$field])) {
			\update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$field]));
		}
	}
}

/**
 * 4) (Optional) Admin-Liste: Firmenname-Spalte
 */
\add_filter('manage_kontakte_posts_columns', __NAMESPACE__ . '\\cmx_kontakte_columns');
function cmx_kontakte_columns($cols) {
	$injected = [];
	foreach ($cols as $k => $v) {
		$injected[$k] = $v;
		if ($k === 'title') {
			$injected['cmx_firmenname'] = 'Firmenname';
			$injected['cmx_vorname']    = 'Vorname';
			$injected['cmx_nachname']   = 'Nachname';
		}
	}
	return $injected;
}

\add_action('manage_kontakte_posts_custom_column', __NAMESPACE__ . '\\cmx_kontakte_column_content', 10, 2);
function cmx_kontakte_column_content($column, $post_id) {
	if ($column === 'cmx_firmenname') {
		echo esc_html(\get_post_meta($post_id, CMX_KONTAKTE_META_FIRMENNAME, true));
	} elseif ($column === 'cmx_vorname') {
		echo esc_html(\get_post_meta($post_id, CMX_KONTAKTE_META_VORNAME, true));
	} elseif ($column === 'cmx_nachname') {
		echo esc_html(\get_post_meta($post_id, CMX_KONTAKTE_META_NACHNAME, true));
	}
}
