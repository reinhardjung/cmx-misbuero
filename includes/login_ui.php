<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


if (!defined('CMX_LOGIN_COLOR')) {
    define('CMX_LOGIN_COLOR', '#15adcb');
}
if (!defined('CMX_LOGIN_FOCUS_COLOR')) {
    define('CMX_LOGIN_FOCUS_COLOR', '#9B0000');  // 8d44ac
}
if (!defined('CMX_LOGIN_LOGO_URL')) {
    define('CMX_LOGIN_LOGO_URL', \plugins_url('../assets/favicon.png', __FILE__));
}
if (!defined('CMX_LOGIN_LOGO_LINK')) {
    define('CMX_LOGIN_LOGO_LINK', 'https://misbuero.ch/');
}

add_action('login_enqueue_scripts', __NAMESPACE__ . '\\cmx_login_layout_enqueue');
function cmx_login_layout_enqueue() {
    $login_color = CMX_LOGIN_COLOR;
    $login_focus_color = CMX_LOGIN_FOCUS_COLOR;
    $login_logo_url = CMX_LOGIN_LOGO_URL;
    $login_icon_see_url = \esc_url(\plugins_url('../assets/see.png', __FILE__));
    $login_icon_hide_url = \esc_url(\plugins_url('../assets/hide.png', __FILE__));

    $css = <<<CSS
.language-switcher,
.privacy-policy-page-link {
    display: none;
}

.forgetmenot {
    position: relative;
    top: 5px;
}

.login #nav {
    text-align: right;
}

.login form,
#loginform {
    position: relative;
    margin-left: auto;
    margin-right: auto;
    box-shadow: none;
    border-width: 2px;
    border-style: solid;
    border-color: {$login_focus_color};
    border-radius: 20px;
    padding-top: 20px;
    padding-bottom: 20px;
}

.login h1 a {
    background-image: url('{$login_logo_url}') !important;
    width: 100% !important;
    height: 90px !important;
    background-repeat: no-repeat !important;
    background-position: center bottom !important;
    background-size: auto 100% !important;
}

#login form input[type=text],
#login form input[type=password] {
    border-color: {$login_color} !important;
    box-shadow: 0 0 0 1px {$login_color} !important;
    border-radius: 10px;
}

#login form input[type=text]:focus,
#login form input[type=password]:focus {
    border-color: {$login_focus_color} !important;
    box-shadow: 0 0 0 1px {$login_focus_color} !important;
    border-radius: 5px;
}

#login form p.submit #wp-submit {
    background-color: {$login_color} !important;
    border-color: {$login_color} !important;
    border-radius: 5px;
}

#login form p.submit #wp-submit:hover {
    background-color: {$login_focus_color} !important;
    border-color: {$login_focus_color} !important;
}

#login form .wp-hide-pw .dashicons {
    display: block;
    position: relative;
    top: 2px;
    width: 42px;
    height: 28px;
    min-width: 42px;
    min-height: 28px;
    margin: 0;
    line-height: 0;
    background-repeat: no-repeat;
    background-position: center;
    background-size: cover;
}

#login form .wp-pwd input[type=password],
#login form .wp-pwd input[type=text] {
    padding-right: 50px !important;
}

#login form .wp-pwd .button.wp-hide-pw {
    position: absolute;
    right: -5px !important;
    top: 50% !important;
    transform: translateY(-75%) !important;
    width: 54px !important;
    min-width: 54px !important;
    height: 40px !important;
    min-height: 40px !important;
    margin: 0;
    padding: 0;
    border: 0;
    background: transparent;
    box-shadow: none;
    display: flex;
    align-items: center;
    justify-content: center;
}

#login form .wp-hide-pw .dashicons:before {
    content: "" !important;
    display: none !important;
}

#login form .wp-hide-pw .dashicons-visibility {
    background-image: url('{$login_icon_see_url}');
}

#login form .wp-hide-pw .dashicons-hidden {
    background-image: url('{$login_icon_hide_url}');
}
CSS;

    wp_register_style('cmx-login-layout', false);
    wp_enqueue_style('cmx-login-layout');
    wp_add_inline_style('cmx-login-layout', $css);
}

add_filter('login_headerurl', __NAMESPACE__ . '\\cmx_login_layout_header_url');
function cmx_login_layout_header_url() {
    return CMX_LOGIN_LOGO_LINK;
}
