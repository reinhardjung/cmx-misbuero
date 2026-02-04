<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


if (!defined('CMX_LOGIN_COLOR')) {
    define('CMX_LOGIN_COLOR', '#15adcb');
}
if (!defined('CMX_LOGIN_FOCUS_COLOR')) {
    define('CMX_LOGIN_FOCUS_COLOR', '#9B0000');  // 8d44ac
}
if (!defined('CMX_LOGIN_LOGO_URL')) {
    define('CMX_LOGIN_LOGO_URL', 'https://vorlage.misbuero.ch/wp-content/uploads/favicon.png');
}
if (!defined('CMX_LOGIN_LOGO_LINK')) {
    define('CMX_LOGIN_LOGO_LINK', 'https://misbuero.ch/');
}

add_action('login_enqueue_scripts', __NAMESPACE__ . '\\cmx_login_layout_enqueue');
function cmx_login_layout_enqueue() {
    $login_color = CMX_LOGIN_COLOR;
    $login_focus_color = CMX_LOGIN_FOCUS_COLOR;
    $login_logo_url = CMX_LOGIN_LOGO_URL;

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
CSS;

    wp_register_style('cmx-login-layout', false);
    wp_enqueue_style('cmx-login-layout');
    wp_add_inline_style('cmx-login-layout', $css);
}

add_filter('login_headerurl', __NAMESPACE__ . '\\cmx_login_layout_header_url');
function cmx_login_layout_header_url() {
    return CMX_LOGIN_LOGO_LINK;
}
