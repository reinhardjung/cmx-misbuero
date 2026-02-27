<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_SCANNER_REL_BELEGE_META = '_cmx_scanner_rel_belege_id';

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
		'Kein Beleg'
	);
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
	$value = isset($_POST[CMX_SCANNER_REL_BELEGE_META]) ? (int) $_POST[CMX_SCANNER_REL_BELEGE_META] : 0;
	if ($selected_type !== 'belege' || $value <= 0 || \get_post_type($value) !== 'belege') {
		\delete_post_meta($post_id, CMX_SCANNER_REL_BELEGE_META);
		return;
	}
	\update_post_meta($post_id, CMX_SCANNER_REL_BELEGE_META, $value);
});
