<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


const CMX_META_ARTIKEL_BELEG   = '_cmx_artikel_beleg_text';
const CMX_MB_ARTIKEL_BELEG_ID  = 'cmx_artikel_belegtext';

/**
 * Registrieren – spezifisch für CPT "artikel"
 */
\add_action('add_meta_boxes_artikel', __NAMESPACE__ . '\\cmx_register_artikel_belegtext_mb', 10, 0);

/**
 * Fallback: Falls ein Drittplugin den spezifischen Hook verhindert, registrieren wir zusätzlich generisch.
 */
\add_action('add_meta_boxes', function($post_type){
	if ($post_type === 'artikel' && !\did_action('add_meta_boxes_artikel')) {
		cmx_register_artikel_belegtext_mb();
	}
}, 20);

/**
 * Metabox-Registrierung
 */
function cmx_register_artikel_belegtext_mb(): void {
	\add_meta_box(
		CMX_MB_ARTIKEL_BELEG_ID,
		__('Belegtext', 'cmx'),
		__NAMESPACE__ . '\\cmx_render_artikel_beleg_box',
		'artikel',
		'normal', // normal-Bereich
		'low'     // ans Ende des Bereichs
	);
}

/**
 * Metabox-Output
 */
function cmx_render_artikel_beleg_box(\WP_Post $post): void {
	$value = (string) \get_post_meta($post->ID, CMX_META_ARTIKEL_BELEG, true);
	\wp_nonce_field('cmx_artikel_beleg_save', 'cmx_artikel_beleg_nonce');
	?>
	<p style="margin:0 0 8px;"><?php echo esc_html__('Wird auf den Belegen als Positionstext gedruckt.', 'cmx'); ?></p>
	<textarea name="cmx_artikel_beleg_text" id="cmx_artikel_beleg_text" rows="8" style="width:100%;max-width:100%;"><?php echo esc_textarea($value); ?></textarea>
	<?php
}

/**
 * Speichern
 */
\add_action('save_post_artikel', function($post_id, \WP_Post $post) {
	// Nonce
	if (!isset($_POST['cmx_artikel_beleg_nonce']) || !\wp_verify_nonce($_POST['cmx_artikel_beleg_nonce'], 'cmx_artikel_beleg_save')) {
		return;
	}
	// Autosave / Rechte / CPT
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if ($post->post_type !== 'artikel') return;
	if (!\current_user_can('edit_post', $post_id)) return;

	$new = isset($_POST['cmx_artikel_beleg_text']) ? \sanitize_textarea_field(\wp_unslash($_POST['cmx_artikel_beleg_text'])) : '';
	if ($new === '') {
		\delete_post_meta($post_id, CMX_META_ARTIKEL_BELEG);
	} else {
		\update_post_meta($post_id, CMX_META_ARTIKEL_BELEG, $new);
	}
}, 10, 2);

/**
 * Optional: Box wirklich ans Ende schieben, auch wenn User bereits eigene Reihenfolge gespeichert hat.
 * Wir hängen die Box-ID ans Ende des "normal"-Stacks.
 */
\add_action('do_meta_boxes', function($post_type, $context, $post = null) {
	if ($post_type !== 'artikel' || $context !== 'normal') return;

	$opt_key = 'meta-box-order_' . $post_type;
	$order   = \get_user_option($opt_key);
	$box     = CMX_MB_ARTIKEL_BELEG_ID;

	if (!is_array($order)) {
		$order = ['normal' => '', 'advanced' => '', 'side' => ''];
	} else {
		$order += ['normal'=>'', 'advanced'=>'', 'side'=>''];
	}

	$ids = array_filter(array_map('trim', explode(',', (string)$order['normal'])));
	$ids = array_values(array_filter($ids, fn($id) => $id !== $box));
	$ids[] = $box;

	$order['normal'] = implode(',', $ids);
	\update_user_option(\get_current_user_id(), $opt_key, $order, true);
}, 10, 3);
