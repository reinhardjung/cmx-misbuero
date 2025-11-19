<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


const CMX_META_BELEG_NOTIZEN  = '_cmx_beleg_intern_notizen';
const CMX_MB_BELEG_NOTIZEN_ID = 'cmx_belege_internenotizen';

/**
 * Registrieren – spezifisch für CPT "belege"
 */
\add_action('add_meta_boxes_belege', __NAMESPACE__ . '\\cmx_register_belege_notizen_mb', 10, 0);

/**
 * Fallback: Generisch registrieren, falls der spezifische Hook verhindert wird.
 */
\add_action('add_meta_boxes', function($post_type){
	if ($post_type === 'belege' && !\did_action('add_meta_boxes_belege')) {
		cmx_register_belege_notizen_mb();
	}
}, 20);

/**
 * Metabox-Registrierung
 */
function cmx_register_belege_notizen_mb(): void {
	\add_meta_box(
		CMX_MB_BELEG_NOTIZEN_ID,
		__('Interne Notizen', 'cmx'),
		__NAMESPACE__ . '\\cmx_render_belege_notizen_box',
		'belege',
		'normal', // normal-Bereich
		'low'     // ans Ende des Bereichs
	);
}

/**
 * Metabox-Output
 */
function cmx_render_belege_notizen_box(\WP_Post $post): void {
	$value = (string) \get_post_meta($post->ID, CMX_META_BELEG_NOTIZEN, true);
	\wp_nonce_field('cmx_belege_notizen_save', 'cmx_belege_notizen_nonce');
	?>
	<p style="margin:0 0 8px;"><?php echo esc_html__('Nur intern sichtbar.', 'cmx'); ?></p>
	<textarea name="cmx_beleg_intern_notizen" id="cmx_beleg_intern_notizen" rows="8" style="width:100%;max-width:100%;"><?php echo esc_textarea($value); ?></textarea>
	<?php
}

/**
 * Speichern
 */
\add_action('save_post_belege', function($post_id, \WP_Post $post) {
	// Nonce prüfen
	if (!isset($_POST['cmx_belege_notizen_nonce']) || !\wp_verify_nonce($_POST['cmx_belege_notizen_nonce'], 'cmx_belege_notizen_save')) {
		return;
	}
	// Autosave / Rechte / CPT prüfen
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if ($post->post_type !== 'belege') return;
	if (!\current_user_can('edit_post', $post_id)) return;

	$new = isset($_POST['cmx_beleg_intern_notizen']) ? \sanitize_textarea_field(\wp_unslash($_POST['cmx_beleg_intern_notizen'])) : '';
	if ($new === '') {
		\delete_post_meta($post_id, CMX_META_BELEG_NOTIZEN);
	} else {
		\update_post_meta($post_id, CMX_META_BELEG_NOTIZEN, $new);
	}
}, 10, 2);

/**
 * Reihenfolge wirklich ans Ende erzwingen, auch wenn User bereits eigene Reihenfolge hat.
 * Hängt die Box-ID ans Ende des "normal"-Stacks für den Screen "belege".
 */
\add_action('do_meta_boxes', function($post_type, $context) {
	if ($post_type !== 'belege' || $context !== 'normal') return;

	$opt_key = 'meta-box-order_' . $post_type;
	$order   = \get_user_option($opt_key);
	$box     = CMX_MB_BELEG_NOTIZEN_ID;

	if (!is_array($order)) {
		$order = ['normal' => '', 'advanced' => '', 'side' => ''];
	} else {
		$order += ['normal'=>'', 'advanced'=>'', 'side'=>''];
	}

	$ids = array_filter(array_map('trim', explode(',', (string)$order['normal'])));
	$ids = array_values(array_filter($ids, static fn($id) => $id !== $box));
	$ids[] = $box;

	$order['normal'] = implode(',', $ids);
	\update_user_option(\get_current_user_id(), $opt_key, $order, true);
}, 99, 2);
