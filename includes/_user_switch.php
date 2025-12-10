<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

error_log('CMX: user-fields loaded');

/**
 * ============================================================
 * 1) MAIL-BACKUP – PROFILFELD (muss vor den Redirect-Funktionen stehen!)
 * ============================================================
 */

/**
 * Feld anzeigen
 */
function cmx_user_mailbackup_field( $user ) {
    $value = get_user_meta( $user->ID, 'cmx_mail_backup', true );
    ?>
    <h3>CLOUD Meister – Zusätzliche Angaben</h3>

    <table class="form-table">
        <tr>
            <th><label for="cmx_mail_backup">Mail-Backup</label></th>
            <td>
                <input type="email"
                       name="cmx_mail_backup"
                       id="cmx_mail_backup"
                       class="regular-text"
                       value="<?php echo esc_attr($value); ?>">
                <p class="description">Alternative E-Mail-Adresse für Backups.</p>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Feld speichern
 */
function cmx_user_mailbackup_save( $user_id ) {

    if (!current_user_can('edit_user', $user_id)) {
        return;
    }

    if (isset($_POST['cmx_mail_backup'])) {
        update_user_meta(
            $user_id,
            'cmx_mail_backup',
            sanitize_email($_POST['cmx_mail_backup'])
        );
    }
}

/**
 * Hooks registrieren
 */
add_action('show_user_profile', __NAMESPACE__ . '\\cmx_user_mailbackup_field');
add_action('edit_user_profile', __NAMESPACE__ . '\\cmx_user_mailbackup_field');
add_action('personal_options_update', __NAMESPACE__ . '\\cmx_user_mailbackup_save');
add_action('edit_user_profile_update', __NAMESPACE__ . '\\cmx_user_mailbackup_save');



/**
 * ============================================================
 * 2) BENUTZERWECHSEL (Switch User)
 * ============================================================
 */

/**
 * Link "zu Benutzer wechseln" in der Benutzerliste
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


/**
 * Benutzerwechsel ausführen
 */
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

		// ursprünglichen Benutzer merken
		if (empty($_COOKIE['cmx_original_user'])) {
			setcookie('cmx_original_user', get_current_user_id(), time() + 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
		}

		wp_clear_auth_cookie();
		wp_set_current_user($user_id);
		wp_set_auth_cookie($user_id, true, is_ssl());
		wp_set_logged_in_cookie($user_id, true, is_ssl());

		remove_all_actions('wp_login');
		remove_all_actions('woocommerce_login_redirect');

		do_action('wp_login', $user->user_login, $user);

		wp_safe_redirect(admin_url('index.php'));
		exit;
	}
});


/**
 * Rückkehr-Link rechts in der Admin-Bar
 */
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


/**
 * Zurück zum ursprünglichen Benutzer
 */
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
