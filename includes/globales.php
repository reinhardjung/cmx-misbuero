<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


require_once 'public_box.php';
require_once 'permalink.php';
require_once 'excerpt.php';
require_once 'messages.php';
require_once 'admin_ui.php';
require_once 'login_manager.php';
require_once 'call.php';
require_once 'datas.php';
// require_once 'bacs.php';
require_once 'user_ui.php';
require_once 'user_switch.php';
require_once 'system_users.php';
require_once 'login_ui.php';
require_once 'dokumente.php';





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



/**
 * Erzwingt für alle Custom Post Types den Status "publish".
 * Entwürfe/Pending/Geplant/Privat werden beim Speichern auf "publish" gesetzt.
 */
add_filter('wp_insert_post_data', __NAMESPACE__ . '\\cmx_force_cpt_publish_status', 99, 2);
function cmx_force_cpt_publish_status(array $data, array $postarr): array {
	$post_type = (string) ($data['post_type'] ?? '');
	if ($post_type === '' || \post_type_exists($post_type) === false) {
		return $data;
	}

	$builtin = ['post', 'page', 'attachment', 'revision', 'nav_menu_item'];
	if (\in_array($post_type, $builtin, true)) {
		return $data;
	}

	$status = (string) ($data['post_status'] ?? '');
	if ($status === '' || \in_array($status, ['publish', 'trash', 'inherit', 'auto-draft'], true)) {
		return $data;
	}

	if (!\in_array($status, ['draft', 'pending', 'future', 'private'], true)) {
		return $data;
	}

	$post_id = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
	if ($post_id > 0 && (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id))) {
		return $data;
	}

	$post_type_obj = \get_post_type_object($post_type);
	$publish_cap = (string) (($post_type_obj && isset($post_type_obj->cap->publish_posts)) ? $post_type_obj->cap->publish_posts : 'publish_posts');
	if ($publish_cap !== '' && !\current_user_can($publish_cap)) {
		return $data;
	}

	$data['post_status'] = 'publish';
	return $data;
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
		$is_debug = \function_exists(__NAMESPACE__ . '\\cmx_system_is_debug_mode_enabled')
			&& cmx_system_is_debug_mode_enabled();
		$title = $is_debug
			? 'Beleg-ID manuell eingeben'
			: '{Die Nummer des Beleges wird hier automatisch vergeben}';
	}
	return $title;
}, 10, 2);


add_action('admin_head', function() {
	$screen = get_current_screen();
	if ($screen && $screen->post_type === 'belege') {
		$is_debug = \function_exists(__NAMESPACE__ . '\\cmx_system_is_debug_mode_enabled')
			&& cmx_system_is_debug_mode_enabled();
		if (!$is_debug) {
			echo '<style> div#titlediv { background:#f7f7f7 !important; pointer-events:none; } </style>';
		}
		// echo '<script>document.addEventListener("DOMContentLoaded",()=>{const t=document.getElementById("title");if(t){t.readOnly=true;}});</script>';
	}
	if ($screen && (string) ($screen->base ?? '') === 'edit' && !empty($screen->post_type)) {
		$post_type_object = \get_post_type_object((string) $screen->post_type);
		if ($post_type_object instanceof \WP_Post_Type && empty($post_type_object->_builtin)) {
			echo '<style>
				.wrap .page-title-action{
					border-radius:10px !important;
				}
			</style>';
		}
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
// .notice-need-update-pro :-(

add_action('load-index.php', function (): void {
	$user_id = \get_current_user_id();
	if ($user_id <= 0) {
		return;
	}

	$version = '20260408_restore_all_dashboard_widgets';
	$current = (string) \get_user_meta($user_id, 'cmx_dashboard_restore_all_version', true);
	if ($current === $version) {
		return;
	}

	\update_user_option($user_id, 'metaboxhidden_dashboard', [], true);
	\update_user_option($user_id, 'closedpostboxes_dashboard', [], true);
	\update_user_meta($user_id, 'cmx_dashboard_restore_all_version', $version);
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



add_action('admin_init', function () {

    // // Nur im Adminbereich
    // if (!is_admin()) {
    //     return;
    // }

    // Alle Admin Notices filtern
    remove_all_actions('admin_notices');
    remove_all_actions('network_admin_notices');

    add_action('admin_notices', function () {
        global $wp_filter;

        if (empty($wp_filter['admin_notices']->callbacks)) {
            return;
        }

        foreach ($wp_filter['admin_notices']->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $key => $callback) {
                if (
                    is_array($callback['function']) &&
                    is_object($callback['function'][0]) &&
                    stripos(get_class($callback['function'][0]), 'wpvivid') !== false
                ) {
                    remove_action('admin_notices', $callback['function'], $priority);
                }
            }
        }
    }, 999);
});
