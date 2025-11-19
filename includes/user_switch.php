<?php namespace CLOUDMEISTER\CMX\LoginManager; defined('ABSPATH') or die('Oxytocin!');

/**
 * Benutzerwechsel mit Rückkehr-Link (vollständig persistent)
 * - Fügt "Als Benutzer einloggen" in der Benutzerliste hinzu
 * - Speichert ursprünglichen Admin im Cookie
 * - Zeigt im Admin-Menü "Zurück zu [Name]" an (rechts in der Admin-Bar)
 * - Setzt beide Login-Cookies (Backend + Frontend)
 */
/**
 * Benutzerwechsel mit Rückkehr-Link (WooCommerce-freundlich)
 * - Fügt "Als Benutzer einloggen" in der Benutzerliste hinzu
 * - Speichert ursprünglichen Admin im Cookie
 * - Zeigt im Admin-Menü "Zurück zu [Name]" an (rechts in der Admin-Bar)
 * - Unterdrückt WooCommerce-Autoredirects
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

		$user = get_user_by('id', $user_id);
		if (!$user) {
			wp_die('Zielbenutzer nicht gefunden.');
		}

		// Ursprünglichen Benutzer im Cookie speichern
		if (empty($_COOKIE['cmx_original_user'])) {
			setcookie('cmx_original_user', get_current_user_id(), time() + 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
		}

		// Alte Auth-Daten löschen
		wp_clear_auth_cookie();
		wp_set_current_user($user_id);

		// Beide Cookies (Backend + Frontend)
		wp_set_auth_cookie($user_id, true, is_ssl());
		wp_set_logged_in_cookie($user_id, true, is_ssl());

		// WooCommerce-Autoredirects killen
		remove_all_actions('wp_login');
		remove_all_actions('woocommerce_login_redirect');

		do_action('wp_login', $user->user_login, $user);

		// Explizit in Admin weiterleiten
		wp_safe_redirect(admin_url('index.php'));
		exit;
	}
});


// Rückkehr-Link im Admin-Menü anzeigen, falls ein ursprünglicher Benutzer gespeichert ist (rechts in der Toolbar)
add_action('admin_bar_menu', function($wp_admin_bar) {
	if (isset($_COOKIE['cmx_original_user']) && current_user_can('read')) {
		$orig_id   = absint($_COOKIE['cmx_original_user']);
		$orig_user = get_user_by('id', $orig_id);
		if ($orig_user) {
			$url = wp_nonce_url(
				add_query_arg(['action' => 'cmx_switch_back'], admin_url('index.php')),
				'cmx_switch_back_' . $orig_id
			);
			$wp_admin_bar->add_node([
				'id'     => 'cmx_switch_back',
				'parent' => 'top-secondary',
				'title'  => 'Zurück zu ' . esc_html($orig_user->user_login),
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
		!empty($_COOKIE['cmx_original_user'])
	) {
		$orig_id = absint($_COOKIE['cmx_original_user']);
		if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'cmx_switch_back_' . $orig_id)) {
			wp_die('Ungültige Anfrage.');
		}

		$orig_user = get_user_by('id', $orig_id);
		if (!$orig_user) {
			wp_die('Ursprungsbenutzer nicht gefunden.');
		}

		wp_clear_auth_cookie();
		wp_set_current_user($orig_id);
		wp_set_auth_cookie($orig_id, true, is_ssl());
		wp_set_logged_in_cookie($orig_id, true, is_ssl());

		remove_all_actions('wp_login');
		remove_all_actions('woocommerce_login_redirect');

		do_action('wp_login', $orig_user->user_login, $orig_user);

		setcookie('cmx_original_user', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);

		wp_safe_redirect(admin_url('users.php'));
		exit;
	}
});
