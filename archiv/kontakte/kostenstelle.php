<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') or die('Oxytocin!');


/** Neue Taxonomie-Konstante */
const TAX_ARTIKEL_KOSTENSTELLE = 'artikel_kostenstelle';

/* ---------------------------------------
 * 1) Taxonomie "Kostenstelle" registrieren (+ optional Seeds)
 * ------------------------------------- */
\add_action('init', function () {
	\register_taxonomy(TAX_ARTIKEL_KOSTENSTELLE, ['artikel'], [
		'label'             => 'Kostenstelle',
		'labels' => [
			'name'              => 'Kostenstellen',
			'singular_name'     => 'Kostenstelle',
			'add_new_item'      => 'Neue Kostenstelle hinzufügen',
			'search_items'      => 'Kostenstelle suchen',
			'all_items'         => 'Alle Kostenstellen',
			'menu_name'         => 'Kostenstellen',
		],
		'public'            => false,
		'show_ui'           => false,
		'show_in_menu'      => 'edit.php?post_type=artikel', // unter CPT-Menü einhängen
		'show_admin_column' => true,
		'hierarchical'      => true,   // für Dropdown/Filter praktisch
		'show_in_rest'      => true,
		'rewrite'           => false,
	]);
}, 15);

// (Optional) Beispiel-Terme anlegen, wenn leer
\add_action('admin_init', function () {
	if (!\taxonomy_exists(TAX_ARTIKEL_KOSTENSTELLE)) return;
	$have = \get_terms(['taxonomy'=>TAX_ARTIKEL_KOSTENSTELLE, 'hide_empty'=>false, 'number'=>1]);
	if (!\is_wp_error($have) && empty($have)) {
		foreach (['1000 Vertrieb','2000 Einkauf','3000 IT'] as $name) {
			if (!\term_exists($name, TAX_ARTIKEL_KOSTENSTELLE)) \wp_insert_term($name, TAX_ARTIKEL_KOSTENSTELLE);
		}
	}
});

// /* ---------------------------------------
//  * 2) Core-Metabox entfernen, eigene SIDE-Metabox registrieren
//  * ------------------------------------- */
// \add_action('admin_menu', function () {
// 	\remove_meta_box('tagsdiv-'.TAX_ARTIKEL_KOSTENSTELLE, 'artikel', 'side');
// 	\remove_meta_box(TAX_ARTIKEL_KOSTENSTELLE.'div',      'artikel', 'side');
// }, 50);

// \add_action('add_meta_boxes', function () {
// 	\add_meta_box(
// 		'cmx_art_kostenstelle_side',
// 		'Kostenstelle',
// 		__NAMESPACE__.'\\cmx_art_kostenstelle_side_box',
// 		'artikel',
// 		'side',
// 		'default'
// 	);
// });

// /* ---------------------------------------
//  * 3) Render: eigene SIDE-Metabox (Single-Select)
//  * ------------------------------------- */
// function cmx_art_kostenstelle_side_box(\WP_Post $post): void {
// 	$sel_id = cmx_get_single_term_id($post->ID, TAX_ARTIKEL_KOSTENSTELLE);
// 	$terms  = cmx_get_terms_safe(TAX_ARTIKEL_KOSTENSTELLE);

// 	echo '<p><label for="cmx_art_kostenstelle"><strong>Kostenstelle auswählen</strong></label><br>';
// 	echo '<select id="cmx_art_kostenstelle" name="cmx_art_kostenstelle" class="widefat">';
// 	echo '<option value="0">— auswählen —</option>';
// 	foreach ($terms as $t) {
// 		echo '<option value="'.(int)$t->term_id.'" '.selected($sel_id, $t->term_id, false).'>'.esc_html($t->name).'</option>';
// 	}
// 	echo '</select></p>';
// }

// /* ---------------------------------------
//  * 4) Speichern (Single-Select)
//  *  -> Hänge diesen Teil in Deinen bestehenden save_post_artikel-Hook an
//  *    oder lasse ihn parallel laufen (gleiche Priorität ist ok).
//  * ------------------------------------- */
// \add_action('save_post_artikel', function (int $post_id, \WP_Post $post) {
// 	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
// 	if ($post->post_type !== 'artikel') return;
// 	if (!\current_user_can('edit_post', $post_id)) return;

// 	$kostenstelle_id = isset($_POST['cmx_art_kostenstelle']) ? (int) $_POST['cmx_art_kostenstelle'] : 0;

// 	if (\taxonomy_exists(TAX_ARTIKEL_KOSTENSTELLE)) {
// 		\wp_set_post_terms($post_id, $kostenstelle_id ? [$kostenstelle_id] : [], TAX_ARTIKEL_KOSTENSTELLE, false);
// 	}
// }, 10, 2);
