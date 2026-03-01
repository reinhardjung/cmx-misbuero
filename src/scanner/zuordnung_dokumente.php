<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_SCANNER_REL_DOKUMENTE_META = '_cmx_scanner_rel_dokumente_id';

\add_action('add_meta_boxes_scanner', function (\WP_Post $post): void {
	\add_meta_box(
		'cmx_scanner_rel_dokumente',
		'Dokument',
		__NAMESPACE__ . '\\cmx_scanner_render_rel_dokumente_metabox',
		'scanner',
		'side',
		'default'
	);
});

function cmx_scanner_render_rel_dokumente_metabox(\WP_Post $post): void {
	\wp_nonce_field('cmx_scanner_rel_dokumente_save', 'cmx_scanner_rel_dokumente_nonce');
	echo '<p>' . \esc_html__('Bei Auswahl "Dokumente" wird automatisch ein neues Dokument erzeugt.', 'cmx') . '</p>';
}

\add_action('save_post_scanner', function (int $post_id): void {
	if (!isset($_POST['cmx_scanner_rel_dokumente_nonce']) || !\wp_verify_nonce((string) $_POST['cmx_scanner_rel_dokumente_nonce'], 'cmx_scanner_rel_dokumente_save')) {
		return;
	}
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

	$selected_type = cmx_scanner_get_requested_zuordnung_type($post_id);
	if ($selected_type !== 'dokumente') {
		\delete_post_meta($post_id, CMX_SCANNER_REL_DOKUMENTE_META);
		return;
	}

	// Bei "Dokumente" wird immer ein neues/zugehöriges Dokument aus dem Scanner
	// erstellt und der Scanner danach finalisiert. Keine manuelle Fremd-Zuordnung.
	\delete_post_meta($post_id, CMX_SCANNER_REL_DOKUMENTE_META);

	if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_ensure_doc_for_post')) {
		cmx_scanner_ensure_doc_for_post($post_id);
	}
	if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_mark_redirect_to_list_after_save')) {
		cmx_scanner_mark_redirect_to_list_after_save($post_id);
	}
});
