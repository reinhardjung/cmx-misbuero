<?php
/**
 * Datei: src/belege/admin-list-kontaktspalte.php
 * Zweck:
 *  - Spalte "Kontakt" in der Belege-Übersicht (CPT: belege)
 *  - Filter-Selectbox nach Kontakt (ALLE Kontakte)
 *  - Klick auf Kontakt öffnet den Kontakt (CPT: kontakte)
 */
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

/** =========================
 * Konfiguration (defensiv)
 * ========================= */
if (!\defined(__NAMESPACE__.'\\CMX_PT_BELEGE'))          \define(__NAMESPACE__.'\\CMX_PT_BELEGE', 'belege');
if (!\defined(__NAMESPACE__.'\\CMX_PT_KONTAKTE'))        \define(__NAMESPACE__.'\\CMX_PT_KONTAKTE', 'kontakte');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT')) \define(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT', '_cmx_beleg_kontakt_id');

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_kontakt_meta_keys')) {
	function cmx_beleg_kontakt_meta_keys(): array {
		$keys = [CMX_BELEG_META_KONTAKT, '_cmx_beleg_kontakt_id', 'cmx_beleg_kontakt_id'];
		return \array_values(\array_unique(\array_filter(\array_map('strval', $keys))));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_kontakt_meta_query')) {
	function cmx_beleg_kontakt_meta_query(int $kontakt_id): array {
		$kontakt_id = (int) $kontakt_id;
		if ($kontakt_id <= 0) {
			return [];
		}

		$meta_keys = cmx_beleg_kontakt_meta_keys();
		if (\count($meta_keys) === 1) {
			return [
				'key'     => (string) ($meta_keys[0] ?? CMX_BELEG_META_KONTAKT),
				'value'   => $kontakt_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			];
		}

		$meta_query = ['relation' => 'OR'];
		foreach ($meta_keys as $key) {
			$meta_query[] = [
				'key'     => $key,
				'value'   => $kontakt_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			];
		}

		return $meta_query;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_kontakt_id_for_post')) {
	function cmx_beleg_kontakt_id_for_post(int $post_id): int {
		foreach (cmx_beleg_kontakt_meta_keys() as $meta_key) {
			$kontakt_id = (int) \get_post_meta($post_id, $meta_key, true);
			if ($kontakt_id > 0) {
				return $kontakt_id;
			}
		}

		return 0;
	}
}

/** =========================
 * 1) Spalte "Kontakt" hinzufügen
 * ========================= */
\add_filter('manage_edit-'.CMX_PT_BELEGE.'_columns', function(array $cols){
	$new = [];
	foreach ($cols as $key => $label) {
		$new[$key] = $label;
		if ($key === 'title') {
			$new['cmx_kontakt'] = __('Kontakt', 'cmx');
		}
	}
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

	$kontakt_id = \function_exists(__NAMESPACE__ . '\\cmx_beleg_kontakt_id_for_post')
		? cmx_beleg_kontakt_id_for_post($post_id)
		: (int) \get_post_meta($post_id, CMX_BELEG_META_KONTAKT, true);
	if ($kontakt_id <= 0) {
		echo '<span style="color:#777"></span>';
		return;
	}

	$title = \get_the_title($kontakt_id);
	// if (!$title) {
	// 	echo '<span style="color:#777">#'.(int)$kontakt_id.'</span>';
	// 	return;
	// }
	if (!$title) {
		echo '<span style="color:#777"></span>';
		return;
	}

	$edit_link = \get_edit_post_link($kontakt_id, '');
	if ($edit_link) {
		echo '<a href="'.\esc_url($edit_link).'" title="'.\esc_attr__('Kontakt bearbeiten', 'cmx').'">'.\esc_html($title).'</a>';
	} else {
		echo \esc_html($title);
	}
}, 10, 2);

/** =========================
 * 3) Filter oben: Selectbox "Kontakt" (ALLE Kontakte)
 * ========================= */
\add_action('restrict_manage_posts', function($post_type){
	if ($post_type !== CMX_PT_BELEGE) {
		return;
	}
	if (!\current_user_can('edit_posts')) {
		return;
	}
	if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_column_is_visible') && !cmx_beleg_admin_column_is_visible('cmx_kontakt')) {
		return;
	}

	$selected = \function_exists(__NAMESPACE__ . '\\cmx_kontakt_request_kontakt_id')
		? (int) cmx_kontakt_request_kontakt_id()
		: (isset($_GET['cmx_kontakt_id']) ? (int) $_GET['cmx_kontakt_id'] : 0);

	// ALLE Kontakte laden – performant
	$kontakte = \get_posts([
		'post_type'               => CMX_PT_KONTAKTE,
		'post_status'             => 'any',
		'posts_per_page'          => -1,          // <-- wichtig: ALLE
		'orderby'                 => 'title',
		'order'                   => 'ASC',
		'fields'                  => 'ids',
		'no_found_rows'           => true,
		'update_post_meta_cache'  => false,
		'update_post_term_cache'  => false,
		'suppress_filters'        => false,
	]);

	echo '<label for="cmx_kontakt_id" class="screen-reader-text">'.\esc_html__('Nach Kontakt filtern', 'cmx').'</label>';
	echo '<select name="cmx_kontakt_id" id="cmx_kontakt_id">';
	echo '<option value="0">'.\esc_html__('Alle Kontakte', 'cmx').'</option>';

	foreach ($kontakte as $kontakt_id) {
		$kontakt_id = (int) $kontakt_id;
		$title = (string) \get_the_title($kontakt_id);
		echo '<option value="'.(int)$kontakt_id.'" '.\selected($selected, (int)$kontakt_id, false).'>'
			.\esc_html($title !== '' ? $title : ('#'.$kontakt_id))
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
	if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_query_post_type')) {
		// Der zentrale Filter in admincolumns.php übernimmt die eigentliche Query-Anpassung.
		return;
	}
	// Robust: prüfe direkt den post_type statt get_current_screen()
	$post_type = $q->get('post_type');
	if ((is_array($post_type) && !in_array(CMX_PT_BELEGE, $post_type, true)) || ($post_type !== CMX_PT_BELEGE)) {
		return;
	}
	if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_column_is_visible') && !cmx_beleg_admin_column_is_visible('cmx_kontakt')) {
		return;
	}

	$kontakt_id = \function_exists(__NAMESPACE__ . '\\cmx_kontakt_request_kontakt_id')
		? (int) cmx_kontakt_request_kontakt_id()
		: (isset($_GET['cmx_kontakt_id']) ? (int) $_GET['cmx_kontakt_id'] : 0);
	if ($kontakt_id > 0) {
		$meta_query   = (array) $q->get('meta_query');
		$kontakt_filter = \function_exists(__NAMESPACE__ . '\\cmx_beleg_kontakt_meta_query')
			? cmx_beleg_kontakt_meta_query($kontakt_id)
			: [
				'key'     => CMX_BELEG_META_KONTAKT,
				'value'   => $kontakt_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			];
		if (!empty($kontakt_filter)) {
			$meta_query[] = $kontakt_filter;
		}
		$q->set('meta_query', $meta_query);
	}
});

/** =========================
 * 5) Optional: Schnell-Filter-Link statt Bearbeiten (deaktiviert)
 * ========================= */
// $filter_url = \add_query_arg(['post_type' => CMX_PT_BELEGE, 'cmx_kontakt_id' => $kontakt_id], \admin_url('edit.php'));
// echo '<a href="'.\esc_url($filter_url).'" title="'.\esc_attr__('Nach diesem Kontakt filtern', 'cmx').'">'.\esc_html($title).'</a>';
