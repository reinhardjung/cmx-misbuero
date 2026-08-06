<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_SCANNER_REL_PROJEKTE_META = '_cmx_scanner_rel_projekte_id';

\add_action('add_meta_boxes_scanner', function (\WP_Post $post): void {
	\add_meta_box(
		'cmx_scanner_rel_projekte',
		'Projekt',
		__NAMESPACE__ . '\\cmx_scanner_render_rel_projekte_metabox',
		'scanner',
		'side',
		'default'
	);
});

function cmx_scanner_render_rel_projekte_metabox(\WP_Post $post): void {
	cmx_scanner_render_relation_select_box(
		$post,
		'projekte',
		CMX_SCANNER_REL_PROJEKTE_META,
		'cmx_scanner_rel_projekte_save',
		'cmx_scanner_rel_projekte_nonce',
		'Kein Projekt',
		true
	);
}

\add_action('save_post_scanner', function (int $post_id): void {
	if (!isset($_POST['cmx_scanner_rel_projekte_nonce']) || !\wp_verify_nonce((string) $_POST['cmx_scanner_rel_projekte_nonce'], 'cmx_scanner_rel_projekte_save')) {
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
		? cmx_scanner_get_posted_relation_ids(CMX_SCANNER_REL_PROJEKTE_META)
		: [];
	$has_submitted_value = \function_exists(__NAMESPACE__ . '\\cmx_scanner_has_posted_relation_value')
		? cmx_scanner_has_posted_relation_value(CMX_SCANNER_REL_PROJEKTE_META)
		: \array_key_exists(CMX_SCANNER_REL_PROJEKTE_META, $_POST);
	$has_zero_value = \function_exists(__NAMESPACE__ . '\\cmx_scanner_posted_relation_has_zero')
		? cmx_scanner_posted_relation_has_zero(CMX_SCANNER_REL_PROJEKTE_META)
		: false;
	$is_touched = \function_exists(__NAMESPACE__ . '\\cmx_scanner_relation_was_touched')
		? cmx_scanner_relation_was_touched(CMX_SCANNER_REL_PROJEKTE_META)
		: false;
	if ($selected_type !== 'projekte' && !$is_touched) {
		return;
	}
	if (!$has_submitted_value || (empty($selected_ids) && !$has_zero_value && !$is_touched)) {
		return;
	}

	$valid_ids = [];
	foreach ($selected_ids as $selected_id) {
		$selected_id = (int) $selected_id;
		if ($selected_id <= 0 || \get_post_type($selected_id) !== 'projekte') {
			continue;
		}
		$valid_ids[] = $selected_id;
	}
	$valid_ids = \array_values(\array_unique($valid_ids));
	if (empty($valid_ids) && !$has_zero_value) {
		\delete_post_meta($post_id, CMX_SCANNER_REL_PROJEKTE_META);
		return;
	}

	if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_ensure_doc_for_post')) {
		cmx_scanner_ensure_doc_for_post($post_id);
	}

	$doc_ids = \function_exists(__NAMESPACE__ . '\\cmx_scanner_get_doc_ids_for_post')
		? cmx_scanner_get_doc_ids_for_post($post_id)
		: [];

	if (!empty($valid_ids)) {
		if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_store_relation_ids')) {
			cmx_scanner_store_relation_ids($post_id, CMX_SCANNER_REL_PROJEKTE_META, $valid_ids);
		} else {
			\update_post_meta($post_id, CMX_SCANNER_REL_PROJEKTE_META, \count($valid_ids) === 1 ? (int) $valid_ids[0] : $valid_ids);
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_link_docs_to_projekte')) {
			foreach ($valid_ids as $projekt_id) {
				cmx_scanner_link_docs_to_projekte((int) $projekt_id, $doc_ids);
			}
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_mark_redirect_to_list_after_save')) {
			cmx_scanner_mark_redirect_to_list_after_save($post_id);
		}
		return;
	}

	$new_projekt_id = \function_exists(__NAMESPACE__ . '\\cmx_scanner_create_related_entry')
		? (int) cmx_scanner_create_related_entry($post_id, 'projekte')
		: 0;
	if ($new_projekt_id <= 0) {
		\delete_post_meta($post_id, CMX_SCANNER_REL_PROJEKTE_META);
		return;
	}

	if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_store_relation_ids')) {
		cmx_scanner_store_relation_ids($post_id, CMX_SCANNER_REL_PROJEKTE_META, [$new_projekt_id]);
	} else {
		\update_post_meta($post_id, CMX_SCANNER_REL_PROJEKTE_META, $new_projekt_id);
	}
	if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_link_docs_to_projekte')) {
		cmx_scanner_link_docs_to_projekte($new_projekt_id, $doc_ids);
	}
	if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_mark_redirect_to_target_edit_after_save')) {
		cmx_scanner_mark_redirect_to_target_edit_after_save($post_id, $new_projekt_id);
	}
});
