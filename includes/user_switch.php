<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Benutzerwechsel mit Rückkehr-Link
 * - Fügt "zu Benutzer wechseln" in der Benutzerliste hinzu
 * - Speichert ursprünglichen Admin im Cookie
 * - Zeigt in der Admin-Bar rechts den Rückkehr-Link an
 */

add_filter('user_row_actions', function($actions, $user) {
	if (current_user_can('manage_options') && get_current_user_id() !== $user->ID) {
		$url = wp_nonce_url(
			add_query_arg([
				'action'  => 'cmx_switch_user',
				'user_id' => $user->ID,
			], admin_url('users.php')),
			'cmx_switch_user_' . $user->ID
		);
		$actions['cmx_switch_user'] = '<a href="' . esc_url($url) . '">zu Benutzer wechseln</a>';
	}
	return $actions;
}, 10, 2);

// Benutzerwechsel ausführen
add_action('admin_init', function() {
	if (
		isset($_GET['action'], $_GET['user_id']) &&
		$_GET['action'] === 'cmx_switch_user' &&
		current_user_can('manage_options')
	) {
		$user_id = absint($_GET['user_id']);
		if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'cmx_switch_user_' . $user_id)) {
			wp_die('Ungültige Anfrage.');
		}

		// Ursprünglichen Benutzer im Cookie speichern
		if (!isset($_COOKIE['cmx_original_user'])) {
			setcookie('cmx_original_user', get_current_user_id(), time() + 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
		}

		wp_set_current_user($user_id);
		wp_set_auth_cookie($user_id);
		wp_redirect(admin_url());
		exit;
	}
});

// Rückkehr-Link im Admin-Menü anzeigen (rechts in der Toolbar)
add_action('admin_bar_menu', function($wp_admin_bar) {
	if (isset($_COOKIE['cmx_original_user']) && current_user_can('read')) {
		$orig_id = absint($_COOKIE['cmx_original_user']);
		$orig_user = get_user_by('id', $orig_id);
		if ($orig_user) {
			$url = wp_nonce_url(
				add_query_arg(['action' => 'cmx_switch_back'], admin_url('index.php')),
				'cmx_switch_back_' . $orig_id
			);
			$wp_admin_bar->add_node([
				'id'     => 'cmx_switch_back',
				'parent' => 'top-secondary',
				'title'  => '' . esc_html($orig_user->user_login),
				'href'   => esc_url($url),
			]);
		}
	}
}, 999);

// Rückkehr zum ursprünglichen Benutzer
add_action('admin_init', function() {
	if (
		isset($_GET['action']) &&
		$_GET['action'] === 'cmx_switch_back' &&
		isset($_COOKIE['cmx_original_user'])
	) {
		$orig_id = absint($_COOKIE['cmx_original_user']);
		if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'cmx_switch_back_' . $orig_id)) {
			wp_die('Ungültige Anfrage.');
		}

		wp_set_current_user($orig_id);
		wp_set_auth_cookie($orig_id);
		setcookie('cmx_original_user', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
		wp_redirect(admin_url('users.php'));
		exit;
	}
});
