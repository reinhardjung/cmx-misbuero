<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_SCANNER_REL_ARTIKEL_META = '_cmx_scanner_rel_artikel_id';

\add_action('add_meta_boxes_scanner', function (\WP_Post $post): void {
	\add_meta_box(
		'cmx_scanner_rel_artikel',
		'Artikel',
		__NAMESPACE__ . '\\cmx_scanner_render_rel_artikel_metabox',
		'scanner',
		'side',
		'default'
	);
});

function cmx_scanner_render_rel_artikel_metabox(\WP_Post $post): void {
	cmx_scanner_render_relation_select_box(
		$post,
		'artikel',
		CMX_SCANNER_REL_ARTIKEL_META,
		'cmx_scanner_rel_artikel_save',
		'cmx_scanner_rel_artikel_nonce',
		'Kein Artikel'
	);
}

\add_action('save_post_scanner', function (int $post_id): void {
	if (!isset($_POST['cmx_scanner_rel_artikel_nonce']) || !\wp_verify_nonce((string) $_POST['cmx_scanner_rel_artikel_nonce'], 'cmx_scanner_rel_artikel_save')) {
		return;
	}
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

	$selected_type = cmx_scanner_get_requested_zuordnung_type($post_id);
	$value = isset($_POST[CMX_SCANNER_REL_ARTIKEL_META]) ? (int) $_POST[CMX_SCANNER_REL_ARTIKEL_META] : 0;
	if ($selected_type !== 'artikel' || $value <= 0 || \get_post_type($value) !== 'artikel') {
		\delete_post_meta($post_id, CMX_SCANNER_REL_ARTIKEL_META);
		return;
	}
	\update_post_meta($post_id, CMX_SCANNER_REL_ARTIKEL_META, $value);

	if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_ensure_doc_for_post')) {
		cmx_scanner_ensure_doc_for_post($post_id);
	}

	$doc_ids = \function_exists(__NAMESPACE__ . '\\cmx_scanner_get_doc_ids_for_post')
		? cmx_scanner_get_doc_ids_for_post($post_id)
		: [];
	if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_link_docs_to_artikel')) {
		cmx_scanner_link_docs_to_artikel($value, $doc_ids);
	}
	if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_mark_redirect_to_list_after_save')) {
		cmx_scanner_mark_redirect_to_list_after_save($post_id);
	}
});
