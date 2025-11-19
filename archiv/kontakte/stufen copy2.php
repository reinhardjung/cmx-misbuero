<?php
/**
 * CMX – Kontakte: Taxonomie "Stufen" (A–D) als Single-Select (Radio) + Admin-Filter
 * - Registriert Taxonomie "stufen" für CPT "kontakte"
 * - Eigene SIDE-Metabox mit Radio-Buttons (Name – Kurzbeschreibung)
 * - Standard-Taxonomie-Metaboxen entfernt
 * - Speichern erzwingt Single-Select
 * - Admin-Listenfilter (Dropdown oberhalb der Tabelle) funktioniert robust via pre_get_posts
 *
 * Namespace: CLOUDMEISTER\CMX\Buero
 */
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit;

/* =========================================================
 * 1) Taxonomie registrieren (nach CPT) – query_var = 'stufe'
 * ========================================================= */
\add_action('init', __NAMESPACE__ . '\\cmx_register_tax_stufen', 12);
function cmx_register_tax_stufen(): void {
	if (\taxonomy_exists('stufen')) return;

	\register_taxonomy('stufen', ['kontakte'], [
		'label'                 => 'Stufen',
		'labels'                => [
			'name'          => 'Stufen',
			'singular_name' => 'Stufe',
			'search_items'  => 'Stufe suchen',
			'all_items'     => 'Alle Stufen',
			'edit_item'     => 'Stufe bearbeiten',
			'update_item'   => 'Stufe aktualisieren',
			'add_new_item'  => 'Neue Stufe hinzufügen',
			'new_item_name' => 'Neue Stufe',
			'menu_name'     => 'Stufen',
		],
		'public'                => false,
		'show_ui'               => true,
		'show_admin_column'     => true,
		'show_in_rest'          => false,
		'hierarchical'          => false,
		'meta_box_cb'           => '__return_false', // Standard-Box deaktivieren
		'query_var'             => 'stufe',
		'rewrite'               => false,
	]);

	// OPTIONAL: Default-Terme anlegen (A–D) – Slugs klein
	// foreach ([['A','a'],['B','b'],['C','c'],['D','d']] as [$name,$slug]) {
	// 	if (!\term_exists($slug, 'stufen')) {
	// 		\wp_insert_term($name, 'stufen', ['slug' => $slug]);
	// 	}
	// }
}

/* =========================================================
 * 2) Standard-Metaboxen konsequent entfernen
 * ========================================================= */
\add_action('admin_menu', function () {
	\remove_meta_box('stufendiv',      'kontakte', 'side');
	\remove_meta_box('stufendiv',      'kontakte', 'normal');
	\remove_meta_box('tagsdiv-stufen', 'kontakte', 'side');
	\remove_meta_box('tagsdiv-stufen', 'kontakte', 'normal');
}, 999);

/* =========================================================
 * 3) Eigene SIDE-Metabox (Radio Single-Select, Name – Beschreibung)
 * ========================================================= */
\add_action('add_meta_boxes', __NAMESPACE__ . '\\cmx_add_stufen_metabox', 20);
function cmx_add_stufen_metabox(): void {
	// Doppelt absichern: Standard-Box entfernen
	\remove_meta_box('stufendiv',      'kontakte', 'side');
	\remove_meta_box('tagsdiv-stufen', 'kontakte', 'side');

	\add_meta_box(
		'cmx_stufen_box',
		'Stufen',
		__NAMESPACE__ . '\\cmx_stufen_box_html',
		'kontakte',
		'side',
		'default'
	);
}

function cmx_stufen_box_html(\WP_Post $post): void {
	\wp_nonce_field('cmx_stufen_save', 'cmx_stufen_nonce');

	if (!\taxonomy_exists('stufen')) {
		echo '<p>Stufen werden initialisiert … bitte Seite neu laden.</p>';
		return;
	}

	$terms = \get_terms([
		'taxonomy'   => 'stufen',
		'hide_empty' => false,
		'orderby'    => 'slug',
		'order'      => 'ASC',
	]);

	// Falls Terme fehlen, optional hier anlegen
	if (\is_wp_error($terms) || empty($terms)) {
		// OPTIONAL: Default-Terme on-the-fly
		// foreach ([['A','a'],['B','b'],['C','c'],['D','d']] as [$name,$slug]) {
		// 	if (!\term_exists($slug, 'stufen')) \wp_insert_term($name, 'stufen', ['slug' => $slug]);
		// }
		$terms = \get_terms(['taxonomy' => 'stufen', 'hide_empty' => false, 'orderby'=>'slug','order'=>'ASC']);
		if (\is_wp_error($terms) || empty($terms)) {
			echo '<p>Stufen konnten nicht geladen werden.</p>';
			return;
		}
	}

	$current_ids = \wp_get_object_terms($post->ID, 'stufen', ['fields' => 'ids']);
	$current_id  = (!\is_wp_error($current_ids) && !empty($current_ids)) ? (int) $current_ids[0] : 0;

	echo '<div class="cmx-stufen-metabox">';
	// Option: keine Auswahl
	echo '<p style="margin:0 0 6px;">
		<label><input type="radio" name="cmx_stufe" value="0" '.\checked(0, $current_id, false).'> – keine –</label>
	</p>';

	foreach ($terms as $t) {
		$desc = \trim((string) $t->description);
		$label = $t->name . ($desc !== '' ? ' – ' . $desc : '');
		echo '<p style="margin:0 0 6px;">
			<label>
				<input type="radio" name="cmx_stufe" value="'.(int)$t->term_id.'" '.\checked($t->term_id, $current_id, false).'>
				'.\esc_html($label).'
			</label>
		</p>';
	}
	echo '</div>';

	echo '<style>
		#cmx_stufen_box .inside { padding-top:8px; }
		#cmx_stufen_box .cmx-stufen-metabox p { line-height:1.25; }
	</style>';
}

/* =========================================================
 * 4) Speichern – Single-Select erzwingen
 * ========================================================= */
\add_action('save_post_kontakte', __NAMESPACE__ . '\\cmx_stufen_save', 10, 3);
function cmx_stufen_save(int $post_id, \WP_Post $post, bool $update): void {
	if (!isset($_POST['cmx_stufen_nonce']) || !\wp_verify_nonce($_POST['cmx_stufen_nonce'], 'cmx_stufen_save')) return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if ($post->post_type !== 'kontakte') return;
	if (!\current_user_can('edit_post', $post_id)) return;

	$term_id = isset($_POST['cmx_stufe']) ? (int) $_POST['cmx_stufe'] : 0;

	if ($term_id > 0) {
		\wp_set_object_terms($post_id, [$term_id], 'stufen', false); // ersetzen
	} else {
		\wp_set_object_terms($post_id, [], 'stufen', false); // löschen
	}
}

/* =========================================================
 * 5) Quick-/Bulk-Edit für "stufen" ausblenden (verhindert Mehrfachzuordnung)
 * ========================================================= */
\add_filter('quick_edit_show_taxonomy', __NAMESPACE__ . '\\cmx_hide_stufen_quick_edit', 10, 3);
function cmx_hide_stufen_quick_edit(bool $show, string $taxonomy_name, string $post_type): bool {
	return ($taxonomy_name === 'stufen' && $post_type === 'kontakte') ? false : $show;
}

/* =========================================================
 * 6) Admin-Listenfilter (Dropdown) + Query-Anpassung
 *    - nutzt automatisch den query_var der Taxonomie (hier 'stufe')
 *    - zeigt Name – Kurzbeschreibung im Dropdown
 * ========================================================= */
\add_action('restrict_manage_posts', function () {
	$screen = \get_current_screen();
	if (!$screen || $screen->post_type !== 'kontakte') return;

	$taxonomy = 'stufen';
	$tx = \get_taxonomy($taxonomy);
	if (!$tx) return;

	$query_var = !empty($tx->query_var) ? $tx->query_var : $taxonomy;
	$selected  = isset($_GET[$query_var]) ? \sanitize_text_field(\wp_unslash($_GET[$query_var])) : '';

	$terms = \get_terms([
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
		'orderby'    => 'slug',
		'order'      => 'ASC',
	]);
	if (\is_wp_error($terms) || empty($terms)) return;

	echo '<select name="' . \esc_attr($query_var) . '" id="' . \esc_attr($query_var) . '" class="postform">';
	echo '<option value="">' . \esc_html__('Alle Stufen', 'default') . '</option>';
	foreach ($terms as $term) {
		$label = $term->name . (!empty($term->description) ? ' – ' . $term->description : '');
		echo '<option value="' . \esc_attr($term->slug) . '" ' . \selected($selected, $term->slug, false) . '>' . \esc_html($label) . '</option>';
	}
	echo '</select>';
});

\add_action('pre_get_posts', function (\WP_Query $query) {
	if (!\is_admin() || !$query->is_main_query()) return;
	if (($query->get('post_type') ?: '') !== 'kontakte') return;

	$taxonomy = 'stufen';
	$tx = \get_taxonomy($taxonomy);
	if (!$tx) return;

	$query_var = !empty($tx->query_var) ? $tx->query_var : $taxonomy;
	if (empty($_GET[$query_var])) return;

	$slug = \sanitize_text_field(\wp_unslash($_GET[$query_var]));
	$term = \get_term_by('slug', $slug, $taxonomy);
	if (!$term || \is_wp_error($term)) return;

	$tax_query = (array) $query->get('tax_query');
	$tax_query[] = [
		'taxonomy' => $taxonomy,
		'field'    => 'slug',
		'terms'    => [$slug],
		'operator' => 'IN',
	];
	$query->set('tax_query', $tax_query);

	// Direktes Query-Var entfernen, um Kollisionen zu vermeiden
	$query->set($query_var, null);
});
