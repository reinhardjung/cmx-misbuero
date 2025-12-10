<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

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
    <br><h3>Mis Büro</h3>

    <table class="form-table">
        <tr>
            <th><label for="cmx_mail_backup">Kunden-Mail</label></th>
            <td>
                <input type="email"
                       name="cmx_mail_backup"
                       id="cmx_mail_backup"
                       class="regular-text"
                       value="<?php echo esc_attr($value); ?>">
                <p class="description">Org. E-Mail-Adresse für Weiterleitungen</p>
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
