<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Meta-Box: Projektzeitraum (Beginn & Ende)
 * CPT: projekte
 */

add_action('add_meta_boxes', function() {
	add_meta_box(
		'cmx_projekt_zeitraum',
		'Projektzeitraum',
		'cmx_projekt_zeitraum_metabox_callback',
		'projekte',
		'normal',
		'default'
	);
});

function cmx_projekt_zeitraum_metabox_callback($post) {
	$beginn = get_post_meta($post->ID, '_cmx_projekt_beginn', true);
	$ende   = get_post_meta($post->ID, '_cmx_projekt_ende', true);
	wp_nonce_field('cmx_projekt_zeitraum_nonce', 'cmx_projekt_zeitraum_nonce_field');
	?>
	<p>
		<label for="cmx_projekt_beginn"><strong>Beginn:</strong></label><br>
		<input type="date" id="cmx_projekt_beginn" name="cmx_projekt_beginn" value="<?php echo esc_attr($beginn); ?>" style="width: 200px;">
	</p>
	<p>
		<label for="cmx_projekt_ende"><strong>Ende:</strong></label><br>
		<input type="date" id="cmx_projekt_ende" name="cmx_projekt_ende" value="<?php echo esc_attr($ende); ?>" style="width: 200px;">
	</p>
	<?php
}

add_action('save_post_projekte', function($post_id) {
	if (!isset($_POST['cmx_projekt_zeitraum_nonce_field']) ||
	    !wp_verify_nonce($_POST['cmx_projekt_zeitraum_nonce_field'], 'cmx_projekt_zeitraum_nonce')) {
		return;
	}

	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	if (!current_user_can('edit_post', $post_id)) {
		return;
	}

	$beginn = sanitize_text_field($_POST['cmx_projekt_beginn'] ?? '');
	$ende   = sanitize_text_field($_POST['cmx_projekt_ende'] ?? '');

	update_post_meta($post_id, '_cmx_projekt_beginn', $beginn);
	update_post_meta($post_id, '_cmx_projekt_ende', $ende);
});
