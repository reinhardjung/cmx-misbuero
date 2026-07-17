<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_SCANNER_REL_BELEGE_META = '_cmx_scanner_rel_belege_id';

function cmx_scanner_prepare_new_beleg_defaults(int $beleg_id): void {
	if ($beleg_id <= 0 || (string) \get_post_type($beleg_id) !== 'belege') {
		return;
	}

	// "Ausgabe" entspricht in Belege der Richtung "eingang".
	$richtung_meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_RICHTUNG')
		? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_RICHTUNG')
		: '_cmx_beleg_richtung';
	\update_post_meta($beleg_id, $richtung_meta_key, 'eingang');

	$tax = \function_exists(__NAMESPACE__ . '\\cmx_belege_kategorie_taxonomy')
		? (string) cmx_belege_kategorie_taxonomy()
		: '';
	if ($tax === '' || !\taxonomy_exists($tax)) {
		return;
	}

	$term = \get_term_by('slug', 'rechnung', $tax);
	if (!$term || \is_wp_error($term)) {
		$term = \get_term_by('name', 'Rechnung', $tax);
	}
	if (!$term || \is_wp_error($term)) {
		$created = \wp_insert_term('Rechnung', $tax, ['slug' => 'rechnung']);
		if (!\is_wp_error($created) && isset($created['term_id'])) {
			$term = \get_term((int) $created['term_id'], $tax);
		}
	}
	if (!$term || \is_wp_error($term)) {
		return;
	}

	\wp_set_post_terms($beleg_id, [(int) $term->term_id], $tax, false);
}

\add_action('add_meta_boxes_scanner', function (\WP_Post $post): void {
	\add_meta_box(
		'cmx_scanner_rel_belege',
		'Beleg',
		__NAMESPACE__ . '\\cmx_scanner_render_rel_belege_metabox',
		'scanner',
		'side',
		'default'
	);
});

function cmx_scanner_render_rel_belege_metabox(\WP_Post $post): void {
	cmx_scanner_render_relation_select_box(
		$post,
		'belege',
		CMX_SCANNER_REL_BELEGE_META,
		'cmx_scanner_rel_belege_save',
		'cmx_scanner_rel_belege_nonce',
		'Kein Beleg',
		true
	);

	$raw_mode = \get_post_meta((int) $post->ID, CMX_SCANNER_REL_BELEGE_AS_DOC_META, true);
	$as_document = ($raw_mode === '' || $raw_mode === null) ? false : ((string) $raw_mode === '1');
	$as_uploads_checked = !$as_document;
	$toggle_label = $as_uploads_checked ? 'als Uploads' : 'als Dokument';
	echo '<p style="margin:8px 0 0;">';
	echo '<label for="cmx_scanner_rel_belege_as_document">';
	echo '<input type="checkbox" id="cmx_scanner_rel_belege_as_document" name="cmx_scanner_rel_belege_as_document" value="1" ' . \checked($as_uploads_checked, true, false) . ' /> ';
	echo '<span id="cmx_scanner_rel_belege_as_document_label">' . \esc_html($toggle_label) . '</span>';
	echo '</label>';
	echo '</p>';
	echo '<script>
	(function(){
		var cb = document.getElementById("cmx_scanner_rel_belege_as_document");
		var label = document.getElementById("cmx_scanner_rel_belege_as_document_label");
		if (!cb || !label) return;
		var sync = function(){
			label.textContent = cb.checked ? "als Uploads" : "als Dokument";
		};
		cb.addEventListener("change", sync);
		sync();
	})();
	</script>';
}

\add_action('save_post_scanner', function (int $post_id): void {
	if (!isset($_POST['cmx_scanner_rel_belege_nonce']) || !\wp_verify_nonce((string) $_POST['cmx_scanner_rel_belege_nonce'], 'cmx_scanner_rel_belege_save')) {
		return;
	}
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

	$selected_type = cmx_scanner_get_requested_zuordnung_type($post_id);
	$selected_ids = \function_exists(__NAMESPACE__ . '\\cmx_scanner_get_posted_relation_ids')
		? cmx_scanner_get_posted_relation_ids(CMX_SCANNER_REL_BELEGE_META)
		: [];
	$has_submitted_value = \function_exists(__NAMESPACE__ . '\\cmx_scanner_has_posted_relation_value')
		? cmx_scanner_has_posted_relation_value(CMX_SCANNER_REL_BELEGE_META)
		: \array_key_exists(CMX_SCANNER_REL_BELEGE_META, $_POST);
	$has_zero_value = \function_exists(__NAMESPACE__ . '\\cmx_scanner_posted_relation_has_zero')
		? cmx_scanner_posted_relation_has_zero(CMX_SCANNER_REL_BELEGE_META)
		: false;
	$is_touched = \function_exists(__NAMESPACE__ . '\\cmx_scanner_relation_was_touched')
		? cmx_scanner_relation_was_touched(CMX_SCANNER_REL_BELEGE_META)
		: false;
	if ($selected_type !== 'belege' && !$is_touched) {
		return;
	}
	if (!$has_submitted_value || (empty($selected_ids) && !$has_zero_value && !$is_touched)) {
		return;
	}

	$as_uploads = isset($_POST['cmx_scanner_rel_belege_as_document']) && (int) $_POST['cmx_scanner_rel_belege_as_document'] === 1;
	$as_document = !$as_uploads;
	\update_post_meta($post_id, CMX_SCANNER_REL_BELEGE_AS_DOC_META, $as_document ? '1' : '0');

	$valid_ids = [];
	foreach ($selected_ids as $selected_id) {
		$selected_id = (int) $selected_id;
		if ($selected_id <= 0 || \get_post_type($selected_id) !== 'belege') {
			continue;
		}
		$valid_ids[] = $selected_id;
	}
	$valid_ids = \array_values(\array_unique($valid_ids));
	if (empty($valid_ids) && !$has_zero_value) {
		\delete_post_meta($post_id, CMX_SCANNER_REL_BELEGE_META);
		return;
	}

	if (empty($valid_ids) && $has_zero_value) {
		$new_beleg_id = \function_exists(__NAMESPACE__ . '\\cmx_scanner_create_related_entry')
			? (int) cmx_scanner_create_related_entry($post_id, 'belege')
			: 0;
		if ($new_beleg_id <= 0 || \get_post_type($new_beleg_id) !== 'belege') {
			\delete_post_meta($post_id, CMX_SCANNER_REL_BELEGE_META);
			return;
		}
		if (!$as_document) {
			cmx_scanner_prepare_new_beleg_defaults($new_beleg_id);
		}
		$valid_ids = [$new_beleg_id];
	}

	if ($as_document && \function_exists(__NAMESPACE__ . '\\cmx_scanner_ensure_doc_for_post')) {
		cmx_scanner_ensure_doc_for_post($post_id);
	}

	if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_store_relation_ids')) {
		cmx_scanner_store_relation_ids($post_id, CMX_SCANNER_REL_BELEGE_META, $valid_ids);
	} else {
		\update_post_meta($post_id, CMX_SCANNER_REL_BELEGE_META, \count($valid_ids) === 1 ? (int) $valid_ids[0] : $valid_ids);
	}

	$is_single_beleg = \count($valid_ids) === 1;

	// Nur im Upload-Modus mit genau einem Ziel-Beleg direkt in Belege springen.
	if (!$as_document) {
		if (!$is_single_beleg) {
			return;
		}
		$redirect_target_id = (int) \reset($valid_ids);
		if ($redirect_target_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_scanner_mark_redirect_to_target_edit_after_save')) {
			cmx_scanner_mark_redirect_to_target_edit_after_save($post_id, $redirect_target_id);
		}
		return;
	}

	// Im Dokument-Modus wie bisher zurück zur Scanner-Liste (kein Sprung in Belege).
	if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_mark_redirect_to_list_after_save')) {
		cmx_scanner_mark_redirect_to_list_after_save($post_id);
	}
});
