<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Meta Box (SIDE): Kontakt-Auswahl für Projekte (Mis Büro)
 * - CPT Projekte:   projekte
 * - CPT Kontakte:   kontakte
 * - Speichert Meta: _cmx_projekt_kontakt_id (int)
 */


/**
 * Meta Box registrieren (SIDE)
 */
add_action('add_meta_boxes', function () {
	add_meta_box(
		'cmx_projekt_kontakt_box',
		__('Zugehöriger Kontakt', 'cmx'),
		__NAMESPACE__ . '\\cmx_render_projekt_kontakt_box',
		'projekte',
		'side',
		'default'
	);
});

/**
 * Render: Dropdown mit Kontakten
 */
function cmx_render_projekt_kontakt_box(\WP_Post $post): void {
	$selected = (int) get_post_meta($post->ID, '_cmx_projekt_kontakt_id', true);

	// Kontakte laden (alphabetisch)
	$kontakte = get_posts([
		'post_type'      => 'kontakte',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	]);

	wp_nonce_field('cmx_save_projekt_kontakt', 'cmx_projekt_kontakt_nonce');

	// echo '<p><label for="cmx_projekt_kontakt_select">' . esc_html__('Kontakt auswählen', 'cmx') . '</label></p>';
	echo '<select name="cmx_projekt_kontakt_id" id="cmx_projekt_kontakt_select" style="width:100%;">';
	echo '<option value="">' . esc_html__('— Kein Kontakt —', 'cmx') . '</option>';

	foreach ($kontakte as $kontakt) {
		printf(
			'<option value="%d" %s>%s</option>',
			(int) $kontakt->ID,
			selected($selected, (int) $kontakt->ID, false),
			esc_html($kontakt->post_title)
		);
	}
	echo '</select>';

	// Kleiner Hinweis
	if (empty($kontakte)) {
		$new_kontakt_url = admin_url('post-new.php?post_type=kontakte');
		$anlegen_link = '<a href="' . esc_url($new_kontakt_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('anlegen', 'cmx') . '</a>';
		$hint_html = sprintf(__('Keine Kontakte - zuerst einen %s.', 'cmx'), $anlegen_link);
		echo '<p style="margin-top:8px;color:#666;">' . wp_kses($hint_html, [
			'a' => [
				'href'   => [],
				'target' => [],
				'rel'    => [],
			],
		]) . '</p>';
	}
}

/**
 * Save: Auswahl persistieren
 */
add_action('save_post_projekte', __NAMESPACE__ . '\\cmx_save_projekt_kontakt', 10, 1);
function cmx_save_projekt_kontakt(int $post_id): void {

	// Nonce prüfen
	if (
		! isset($_POST['cmx_projekt_kontakt_nonce']) ||
		! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cmx_projekt_kontakt_nonce'])), 'cmx_save_projekt_kontakt')
	) {
		return;
	}

	// Autosave / Rechte prüfen
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (! current_user_can('edit_post', $post_id)) return;

	// Wert aus POST
	$val = isset($_POST['cmx_projekt_kontakt_id']) ? (int) $_POST['cmx_projekt_kontakt_id'] : 0;

	// Nur existierende Kontakte akzeptieren
	if ($val > 0 && 'kontakte' === get_post_type($val)) {
		update_post_meta($post_id, '_cmx_projekt_kontakt_id', $val);
	} else {
		delete_post_meta($post_id, '_cmx_projekt_kontakt_id');
	}
}
