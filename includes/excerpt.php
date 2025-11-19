<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


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


// add_filter('gettext', function($translated_text, $text, $domain) {
// 	global $post;

// 	if ($translated_text === 'In den Papierkorb verschieben') {
// 		return 'Direkt löschen';
// 	}

// 	return $translated_text;
// }, 10, 3);

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


add_action('admin_head', function() {
    remove_action('media_buttons', 'media_buttons');
});
// add_action('admin_head', function() {
//     $screen = get_current_screen();
//     if (!$screen) return;

//     // Liste Deiner CPTs, bei denen der Button entfernt werden soll
//     $blocked_cpts = ['kontakte', 'belege', 'artikel'];

//     if (in_array($screen->post_type, $blocked_cpts, true)) {
//         remove_action('media_buttons', 'media_buttons');
//     }
// });
