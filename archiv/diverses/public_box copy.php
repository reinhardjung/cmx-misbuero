<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') or die('Oxytocin!');



/**
 * Minimaler „Speichern“-Button statt „Veröffentlichen“-Metabox (Classic Editor)
 * mit sichtbarem „In den Papierkorb verschieben“-Link nach dem Speichern.
 */
add_action('add_meta_boxes', function() {
	$allowed = ['post', 'page', 'kontakte', 'belege']; // <- ggf. anpassen
	$screen = get_current_screen();
	if (!$screen || !in_array($screen->post_type, $allowed, true)) return;

	add_meta_box(
		'cmx_savebox',
		__('Aktion', 'default'),
		function($post) use ($screen) {

			$is_new       = ($post->ID === 0 || $post->post_status === 'auto-draft');
			$post_type    = $screen->post_type;
			$pt_obj       = get_post_type_object($post_type);
			$singular     = $pt_obj->labels->singular_name ?? ucfirst($post_type);
			$btn_label    = sprintf('%s speichern', $singular);
			$btn_name     = $is_new ? 'publish' : 'save';

			echo '<div style="padding:12px 0;">';
			printf(
				'<input type="submit" name="%1$s" id="publish" class="button button-primary button-large" value="%2$s" />',
				esc_attr($btn_name),
				esc_attr($btn_label)
			);
			echo '</div>';

			// Papierkorb-Link nur anzeigen, wenn Post existiert (nicht neu)
			if ($post->ID && $post->post_status !== 'auto-draft') {
				$delete_link = get_delete_post_link($post->ID, '', true);
				if ($delete_link) {
					echo '<div style="margin-top:10px; padding-top:6px; border-top:1px solid #ddd;">';
					printf(
						'<a href="%1$s" class="submitdelete deletion" style="color:#b32d2e; text-decoration:none;">%2$s</a>',
						esc_url($delete_link),
						__('In den Papierkorb verschieben', 'default')
					);
					echo '</div>';
				}
			}
		},
		$screen->post_type,
		'side',
		'high'
	);
});
