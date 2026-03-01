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
		'Kein Projekt'
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
	$value = isset($_POST[CMX_SCANNER_REL_PROJEKTE_META]) ? (int) $_POST[CMX_SCANNER_REL_PROJEKTE_META] : 0;
	if ($selected_type !== 'projekte' || $value <= 0 || \get_post_type($value) !== 'projekte') {
		\delete_post_meta($post_id, CMX_SCANNER_REL_PROJEKTE_META);
		return;
	}
	\update_post_meta($post_id, CMX_SCANNER_REL_PROJEKTE_META, $value);

	if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_ensure_doc_for_post')) {
		cmx_scanner_ensure_doc_for_post($post_id);
	}

	$doc_ids = \function_exists(__NAMESPACE__ . '\\cmx_scanner_get_doc_ids_for_post')
		? cmx_scanner_get_doc_ids_for_post($post_id)
		: [];
	if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_link_docs_to_projekte')) {
		cmx_scanner_link_docs_to_projekte($value, $doc_ids);
	}
	if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_mark_redirect_to_list_after_save')) {
		cmx_scanner_mark_redirect_to_list_after_save($post_id);
	}
});
