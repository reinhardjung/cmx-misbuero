<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;


/**
 * Passwort-Reset auch an die hinterlegte Backup-Mail senden.
 */
add_filter('retrieve_password_message', function($message, $key, $user_login, $user_data) {
    if (!($user_data instanceof \WP_User)) {
        return $message;
    }

    $backup_mail = get_user_meta($user_data->ID, 'cmx_mail_backup', true);
    if (!$backup_mail || !is_email($backup_mail)) {
        return $message;
    }

    // Doppelte Zustellung vermeiden, falls Backup = primäre Mail.
    if (strcasecmp($backup_mail, $user_data->user_email) === 0) {
        return $message;
    }

    // Betreff wie WordPress generieren, inklusive Titel-Filter.
    $title = sprintf(
        __('[%s] Password Reset'),
        wp_specialchars_decode(get_option('blogname'), ENT_QUOTES)
    );
    $title = apply_filters('retrieve_password_title', $title, $user_login, $user_data);

    wp_mail($backup_mail, $title, $message);

    return $message;
}, 10, 4);
