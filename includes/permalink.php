<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


// Permalink im Editor ausblenden
\add_action( 'admin_head', __NAMESPACE__ . '\\cmx_hide_permalink_edit' );

function cmx_hide_permalink_edit() {
	$screen = \get_current_screen();
	if ( ! $screen || ! \in_array( $screen->base, [ 'post', 'post-new' ], true ) ) {
		return; // nur auf Edit-/Neu-Seiten
	}

	// Optional: nur für einen bestimmten Post-Type (z.B. 'kontakte')
	// if ( $screen->post_type !== 'kontakte' ) {
	// 	return;
	// }

	echo '<style>#edit-slug-box{display:none!important;}</style>';
}


// Wegen Elementor :-()
// /**
//  * ==========================================================
//  * Permalink (Slug) immer mit dem Titel synchronisieren
//  * ==========================================================
//  */
// \add_action('save_post', function ($post_id, $post, $update) {
// 	// Nur im Admin ausführen
// 	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
// 	if (wp_is_post_revision($post_id)) return;

// 	// Nur für bestimmte Post Types
// 	$allowed_post_types = ['post', 'page', 'kontakte', 'belege'];
// 	if (!in_array($post->post_type, $allowed_post_types, true)) return;

// 	// Titel holen
// 	$title = trim($post->post_title);
// 	if (empty($title)) return;

// 	// Slug generieren
// 	$new_slug = sanitize_title($title);

// 	// Nur aktualisieren, wenn sich der Slug wirklich unterscheidet
// 	if ($post->post_name !== $new_slug) {
// 		\remove_action('save_post', __FUNCTION__, 10); // Rekursion verhindern
// 		wp_update_post([
// 			'ID'        => $post_id,
// 			'post_name' => $new_slug,
// 		]);
// 		\add_action('save_post', __FUNCTION__, 10, 3);
// 	}
// }, 10, 3);
