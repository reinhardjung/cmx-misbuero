<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') or die('Oxytocin!');


add_filter('gettext', __NAMESPACE__ . '\\cmx_rename_excerpt_label', 10, 3);
function cmx_rename_excerpt_label($translated_text, $untranslated_text, $domain) {
	if ($untranslated_text === 'Excerpt' || $untranslated_text === 'Textauszug') {
		$translated_text = 'Interne Notiz';
	}
	return $translated_text;
}



add_filter('gettext', __NAMESPACE__ . '\\cmx_rename_excerpt_label_for_cpt', 10, 3);
function cmx_rename_excerpt_label_for_cpt($translated_text, $untranslated_text, $domain) {
	global $post_type;
	if ($post_type === 'artikel' && ($untranslated_text === 'Excerpt' || $untranslated_text === 'Textauszug')) {
		$translated_text = 'Kurzbeschreibung';
	}
	return $translated_text;
}



add_action('add_meta_boxes', __NAMESPACE__ . '\\cmx_hide_slug_metabox_global', 100);
function cmx_hide_slug_metabox_global() {
	$post_types = get_post_types(['public' => true], 'names');
	foreach ($post_types as $post_type) {
		remove_meta_box('slugdiv', $post_type, 'normal');
	}
}


\add_action('admin_head', function () {
	$screen = \get_current_screen();
	if (!$screen) return;
	// echo '<style>#postexcerpt p { display: none !important; }</style>';

	if ($screen->post_type === 'kontakte') {
		echo '<script>
			document.addEventListener("DOMContentLoaded", function() {
				const box = document.querySelector("#postexcerpt p");
				if (box) {
					box.style.display = "block";
					box.style.fontStyle = "normal";
					box.textContent = "Interne Notizen oder Kommentare zu diesem Kontakt - nur für Dich sichtbar.";
				}
			});
		</script>';
	}
	if ($screen->post_type === 'artikel') {
		echo '<script>
			document.addEventListener("DOMContentLoaded", function() {
				const box = document.querySelector("#postexcerpt p");
				if (box) {
					box.style.display = "block";
					box.style.fontStyle = "normal";
					box.textContent = "Wird auf den Belegen als Positionstext angezeigt.";
				}
			});
		</script>';
	}
});





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

// function cmx_hide_permalink_edit_for_kontakte() {
// 	global $post;
// 	if ( is_admin() && isset( $post->post_type ) && $post->post_type === 'kontakte' ) {
// 		echo '<style>
// 			#edit-slug-box { display: none !important; }
// 		</style>';
// 	}
// }
// add_action( 'admin_head', 'cmx_hide_permalink_edit_for_kontakte' );


/**
 * Eigene Admin-Meldung nach dem Speichern oder Veröffentlichen von Beiträgen oder CPTs.
 */
function cmx_custom_post_updated_messages( $messages ) {
	global $post, $post_ID;

	// Hole den aktuellen Post Type
	$post_type = get_post_type( $post_ID );
	$post_type_object = get_post_type_object( $post_type );

	// Verwende das Label des CPTs (z. B. "Kontakt", "Beleg")
	$singular_name = $post_type_object->labels->singular_name ?? 'Beitrag';

	// Überschreibe alle Standard-Meldungen
	$messages[$post_type] = [
		0  => '', // Nicht verwendet
		1  => sprintf( __( '%s wurde gespeichert.', 'default' ), $singular_name ),
		2  => __( 'Benutzerdefiniertes Feld aktualisiert.', 'default' ),
		3  => __( 'Benutzerdefiniertes Feld gelöscht.', 'default' ),
		4  => sprintf( __( '%s wurde gespeichert.', 'default' ), $singular_name ),
		5  => isset($_GET['revision']) ? sprintf( __( '%s wurde auf Revision %s zurückgesetzt.', 'default' ), $singular_name, wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
		6  => sprintf( __( '%s wurde gespeichert.', 'default' ), $singular_name ),
		7  => sprintf( __( '%s gespeichert.', 'default' ), $singular_name ),
		8  => sprintf( __( '%s wurde gespeichert.', 'default' ), $singular_name ),
		9  => sprintf( __( '%s wurde gespeichert.', 'default' ), $singular_name ),
		10 => sprintf( __( '%s wurde gespeichert.', 'default' ), $singular_name ),
	];

	return $messages;
}
add_filter( 'post_updated_messages', __NAMESPACE__ . '\\cmx_custom_post_updated_messages' );




/**
 * Minimaler „Speichern“-Button statt „Veröffentlichen“-Metabox (Classic Editor).
 */

add_action('add_meta_boxes', function() {
	// Welche Post Types sollen angepasst werden?
	$allowed = ['post', 'page', 'kontakte', 'belege']; // <- anpassen

	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, $allowed, true ) ) {
		return;
	}

	// Standard-Metabox entfernen
	// remove_meta_box( 'submitdiv', $screen->post_type, 'side' );

	// Eigene Minimal-Box hinzufügen
	add_meta_box(
		'cmx_savebox',
		__( 'Aktion', 'default' ),
		function( $post ) use ( $screen ) {
			$is_published = ( $post && $post->post_status === 'publish' );

			// Button-Name je Status
			$btn_name = $is_published ? 'save' : 'publish';

			// Hole das Singular-Label des CPT
			$post_type_object = get_post_type_object( $screen->post_type );
			$singular_label   = $post_type_object->labels->singular_name ?? ucfirst( $screen->post_type );

			// Button-Text dynamisch zusammensetzen
			$btn_value = sprintf( '%s speichern', $singular_label );

			printf(
				'<div style="padding:12px 0;">
					<input type="submit" name="%1$s" id="publish" class="button button-primary button-large" value="%2$s" />
				</div>',
				esc_attr( $btn_name ),
				esc_attr( $btn_value )
			);
		},
		$screen->post_type,
		'side',
		'high'
	);
});


/**
 * Optional: Button-Text im Admin auch global angleichen
 * (z. B. falls andere WP-Komponenten ihn noch referenzieren).
 */
add_filter('gettext', function( $translated, $original, $domain ){
	if ( ! is_admin() ) return $translated;

	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if ( ! $screen ) return $translated;

	$post_type_object = get_post_type_object( $screen->post_type );
	if ( ! $post_type_object ) return $translated;

	$singular_label = $post_type_object->labels->singular_name ?? ucfirst( $screen->post_type );

	if ( in_array( $original, ['Veröffentlichen','Aktualisieren','Publish','Update'], true ) ) {
		return sprintf( '%s speichern', $singular_label );
	}

	return $translated;
}, 10, 3);
