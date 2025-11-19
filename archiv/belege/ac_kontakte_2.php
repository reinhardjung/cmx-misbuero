<?php
/**
 * Datei: src/belege/admin-list-kontaktspalte.php
 * Zweck:
 *  - Spalte "Kontakt" in der Belege-Übersicht (CPT: belege)
 *  - Filter-Selectbox nach Kontakt
 *  - Klick auf Kontakt öffnet den Kontakt (CPT: kontakte)
 */
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

/** =========================
 * Konfiguration (defensiv)
 * ========================= */
if (!\defined(__NAMESPACE__.'\\CMX_PT_BELEGE'))        \define(__NAMESPACE__.'\\CMX_PT_BELEGE', 'belege');
if (!\defined(__NAMESPACE__.'\\CMX_PT_KONTAKTE'))      \define(__NAMESPACE__.'\\CMX_PT_KONTAKTE', 'kontakte');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT')) \define(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT', '_cmx_beleg_kontakt_id');

/** =========================
 * 1) Spalte "Kontakt" hinzufügen
 * ========================= */
\add_filter('manage_edit-'.CMX_PT_BELEGE.'_columns', function(array $cols){
	// Füge "Kontakt" nach dem Titel ein
	$new = [];
	foreach ($cols as $key => $label) {
		$new[$key] = $label;
		if ($key === 'title') {
			$new['cmx_kontakt'] = __('Kontakt', 'cmx');
		}
	}
	// Falls 'title' nicht existiert, zumindest anhängen
	if (!isset($new['cmx_kontakt'])) {
		$new['cmx_kontakt'] = __('Kontakt', 'cmx');
	}
	return $new;
});

/** =========================
 * 2) Spalteninhalt ausgeben
 * ========================= */
\add_action('manage_'.CMX_PT_BELEGE.'_posts_custom_column', function(string $col, int $post_id){
	if ($col !== 'cmx_kontakt') {
		return;
	}

	$kontakt_id = (int) \get_post_meta($post_id, CMX_BELEG_META_KONTAKT, true);
	if ($kontakt_id <= 0) {
		echo '<span style="color:#777">—</span>';
		return;
	}

	$title = \get_the_title($kontakt_id);
	if (!$title) {
		echo '<span style="color:#777">#'.(int)$kontakt_id.'</span>';
		return;
	}

	$edit_link = \get_edit_post_link($kontakt_id, ''); // Bearbeiten-Seite des Kontakts
	if ($edit_link) {
		echo '<a href="'.\esc_url($edit_link).'" title="'.\esc_attr__('Kontakt bearbeiten', 'cmx').'">'.\esc_html($title).'</a>';
	} else {
		echo \esc_html($title);
	}
}, 10, 2);

/** =========================
 * 3) Filter oben: Selectbox "Kontakt"
 * ========================= */
\add_action('restrict_manage_posts', function($post_type){
	if ($post_type !== CMX_PT_BELEGE) {
		return;
	}

	// Nur für Benutzer mit Leserechten sinnvoll
	if (!\current_user_can('edit_posts')) {
		return;
	}

	$selected = isset($_GET['cmx_kontakt_id']) ? (int) $_GET['cmx_kontakt_id'] : 0;

	// Kontakte laden – sortiert nach Titel (angepasst auf typisch geringe Stückzahlen)
	$kontakte = \get_posts([
		'post_type'      => CMX_PT_KONTAKTE,
		'post_status'    => 'any',
		'numberposts'    => 200,        // ggf. anpassen
		'orderby'        => 'title',
		'order'          => 'ASC',
		'suppress_filters' => false,
	]);

	echo '<label for="cmx_kontakt_id" class="screen-reader-text">'.\esc_html__('Nach Kontakt filtern', 'cmx').'</label>';
	echo '<select name="cmx_kontakt_id" id="cmx_kontakt_id">';
	echo '<option value="0">'.\esc_html__('Alle Kontakte', 'cmx').'</option>';

	foreach ($kontakte as $k) {
		echo '<option value="'.(int)$k->ID.'" '.\selected($selected, (int)$k->ID, false).'>'
			.\esc_html($k->post_title ?: ('#'.$k->ID))
			.'</option>';
	}
	echo '</select>';
});

/** =========================
 * 4) Query anpassen, wenn Filter aktiv
 * ========================= */
\add_action('pre_get_posts', function(\WP_Query $q){
	if (!\is_admin() || !$q->is_main_query()) {
		return;
	}
	$screen = function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || $screen->id !== 'edit-'.CMX_PT_BELEGE) {
		return;
	}

	$kontakt_id = isset($_GET['cmx_kontakt_id']) ? (int) $_GET['cmx_kontakt_id'] : 0;
	if ($kontakt_id > 0) {
		$meta_query = (array) $q->get('meta_query');
		$meta_query[] = [
			'key'     => CMX_BELEG_META_KONTAKT,
			'value'   => $kontakt_id,
			'compare' => '=',
			'type'    => 'NUMERIC',
		];
		$q->set('meta_query', $meta_query);
	}
});

/** =========================
 * 5) Optional: Schnell-Filter-Link in der Spalte (deaktiviert)
 *    - Wenn Du zusätzlich per Klick filtern willst, statt Bearbeiten zu öffnen,
 *      tausche den Link in (2) aus gegen die kommentierte Variante unten.
 * =========================
 */
// Beispiel (in Aktion 2 ersetzen):
// $filter_url = \add_query_arg(['post_type' => CMX_PT_BELEGE, 'cmx_kontakt_id' => $kontakt_id], \admin_url('edit.php'));
// echo '<a href="'.\esc_url($filter_url).'" title="'.\esc_attr__('Nach diesem Kontakt filtern', 'cmx').'">'.\esc_html($title).'</a>';
