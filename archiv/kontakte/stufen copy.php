<?php
/**
 * Taxonomie "Stufen" (A–D) für CPT "kontakte" – Single-Select via Radio in SIDE-Metabox
 * Robust gegen Hook-Reihenfolge (registriert auf init, Prio 12) und legt Standard-Terme an.
 */
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit;

/** ==== 1) Taxonomie registrieren (nach CPT) ================================= */
\add_action('init', __NAMESPACE__ . '\\cmx_register_tax_stufen', 12);
function cmx_register_tax_stufen(): void {
	// Falls bereits vorhanden (z. B. wegen Reload), nichts tun
	if (\taxonomy_exists('stufen')) {
		return;
	}

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
		'meta_box_cb'           => '__return_false', // Standard-Box aus
		'query_var'             => 'stufe',
		'rewrite'               => false,
	]);

	// Default-Terme (A–D) sicherstellen – optional
	// foreach ([['A','a'],['B','b'],['C','c'],['D','d']] as [$name,$slug]) {
	// 	if (!\term_exists($slug, 'stufen')) {
	// 		\wp_insert_term($name, 'stufen', ['slug' => $slug]);
	// 	}
	// }
}

/** ==== 2) Eigene SIDE-Metabox mit Radio-Buttons ============================= */
\add_action('add_meta_boxes', __NAMESPACE__ . '\\cmx_add_stufen_metabox', 20);
function cmx_add_stufen_metabox(): void {
	// Falls WP doch die Standard-Metabox registriert hat: weg damit
	\remove_meta_box('stufendiv', 'kontakte', 'side');
	\remove_meta_box('stufendiv', 'kontakte', 'normal');

	\add_meta_box(
		'cmx_stufen_box',
		'Stufen',
		__NAMESPACE__ . '\\cmx_stufen_box_html',
		'kontakte',
		'side',
		'default'
	);
}

/** Metabox-HTML (Radio Single-Select) */
function cmx_stufen_box_html(\WP_Post $post): void {
	\wp_nonce_field('cmx_stufen_save', 'cmx_stufen_nonce');

	// Sicherstellen, dass Taxonomie existiert (sonst keine Fehlermeldung für User)
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

	// Falls Terme (noch) fehlen, jetzt anlegen und neu laden
	if (\is_wp_error($terms) || empty($terms)) {
		foreach ([['A','a'],['B','b'],['C','c'],['D','d']] as [$name,$slug]) {
			if (!\term_exists($slug, 'stufen')) {
				\wp_insert_term($name, 'stufen', ['slug' => $slug]);
			}
		}
		$terms = \get_terms(['taxonomy' => 'stufen', 'hide_empty' => false, 'orderby'=>'slug','order'=>'ASC']);
		if (\is_wp_error($terms) || empty($terms)) {
			echo '<p>Stufen konnten nicht geladen werden.</p>';
			return;
		}
	}

	$current = \wp_get_object_terms($post->ID, 'stufen', ['fields' => 'ids']);
	$current_id = (!\is_wp_error($current) && !empty($current)) ? (int) $current[0] : 0;

	echo '<div class="cmx-stufen-metabox">';
	// Option: keine Auswahl
	echo '<p style="margin:0 0 6px;">
		<label><input type="radio" name="cmx_stufe" value="0" '.\checked(0, $current_id, false).'> – keine –</label>
	</p>';

	foreach ($terms as $t) {
		// Kurzbeschreibung (Term Description) mit anzeigen, via Gedankenstrich trennen
		$desc = \trim((string) $t->description);
		$label_text = $t->name . ($desc !== '' ? ' – ' . $desc : '');
		echo '<p style="margin:0 0 6px;">
			<label>
				<input type="radio" name="cmx_stufe" value="'.(int)$t->term_id.'" '.\checked($t->term_id, $current_id, false).'>
				'.\esc_html($label_text).'
			</label>
		</p>';
	}
	echo '</div>';

	echo '<style>
		#cmx_stufen_box .inside { padding-top:8px; }
		#cmx_stufen_box .cmx-stufen-metabox p { line-height:1.25; }
	</style>';
}

/** ==== 3) Speichern – Single-Select erzwingen =============================== */
\add_action('save_post_kontakte', __NAMESPACE__ . '\\cmx_stufen_save', 10, 3);
function cmx_stufen_save(int $post_id, \WP_Post $post, bool $update): void {
	if (!isset($_POST['cmx_stufen_nonce']) || !\wp_verify_nonce($_POST['cmx_stufen_nonce'], 'cmx_stufen_save')) return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if ($post->post_type !== 'kontakte') return;
	if (!\current_user_can('edit_post', $post_id)) return;

	$term_id = isset($_POST['cmx_stufe']) ? (int) $_POST['cmx_stufe'] : 0;
	if ($term_id > 0) {
		\wp_set_object_terms($post_id, [$term_id], 'stufen', false);
	} else {
		\wp_set_object_terms($post_id, [], 'stufen', false);
	}
}

/** ==== 4) Quick-/Bulk-Edit für "stufen" ausblenden (verhindert Mehrfachzuordnung) */
\add_filter('quick_edit_show_taxonomy', __NAMESPACE__ . '\\cmx_hide_stufen_quick_edit', 10, 3);
function cmx_hide_stufen_quick_edit(bool $show, string $taxonomy_name, string $post_type): bool {
	return ($taxonomy_name === 'stufen' && $post_type === 'kontakte') ? false : $show;
}

/**
 * Standard-Taxonomie-Metaboxen für "stufen" konsequent entfernen
 * (greift sowohl im SIDE- als auch im NORMAL-Bereich, vor/nach Registrierungen)
 */
\add_action('admin_menu', function () {
	\remove_meta_box('stufendiv',       'kontakte', 'side');
	\remove_meta_box('stufendiv',       'kontakte', 'normal');
	\remove_meta_box('tagsdiv-stufen',  'kontakte', 'side');
	\remove_meta_box('tagsdiv-stufen',  'kontakte', 'normal');
}, 999);

/**
 * Sicherheitshalber auch beim Metabox-Setup entfernen und eigene Radio-Box hinzufügen
 */
\add_action('add_meta_boxes', function () {
	\remove_meta_box('stufendiv',       'kontakte', 'side');
	\remove_meta_box('stufendiv',       'kontakte', 'normal');
	\remove_meta_box('tagsdiv-stufen',  'kontakte', 'side');
	\remove_meta_box('tagsdiv-stufen',  'kontakte', 'normal');

	\add_meta_box(
		'cmx_stufen_box',
		'Stufen',
		__NAMESPACE__ . '\\cmx_stufen_box_html',
		'kontakte',
		'side',
		'default'
	);
}, 999);

/**
 * Falls die Taxonomie schon existierte und mit Standard-Metabox registriert wurde,
 * setze das Argument meta_box_cb zur Laufzeit auf false.
 */
\add_action('init', function () {
	global $wp_taxonomies;
	if (isset($wp_taxonomies['stufen'])) {
		$wp_taxonomies['stufen']->meta_box_cb = '__return_false';
	}
}, 20);
