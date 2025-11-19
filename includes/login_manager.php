<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit;

/**
 * Beim Login eigene Funktion ausführen
 */
add_action('wp_login', function($user_login, $user) {

    // Beispiel: Letztes Login speichern
    update_user_meta($user->ID, 'cmx_last_login', current_time('mysql'));

    // Beispiel: Weiterleitung anpassen
    wp_redirect(home_url('/mein-dashboard/'));
    exit;

}, 10, 2);


/**
 * Login prüfen und ggf. blockieren
 */
// add_filter('wp_authenticate_user', function($user) {

//     // Beispiel: Benutzer mit bestimmtem Meta sperren
//     if (get_user_meta($user->ID, 'cmx_login_blocked', true) === 'yes') {
//         return new \WP_Error(
//             'cmx_blocked',
//             __('Dieser Benutzer ist gesperrt.', 'cmx')
//         );
//     }

//     return $user;
// });




/**
 * Loginseite stylen
 */
add_action('login_head', function() {
    ?>
    <style>
        body.login #login h1 a {
            background-image: url('<?php echo esc_url(CMX_PLUGIN_URL . "assets/login-logo.png"); ?>') !important;
            background-size: contain !important;
            width: 240px;
            height: 100px;
        }
    </style>
    <?php
});
