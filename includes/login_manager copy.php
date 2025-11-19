<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

/**
 * Login-Seite stylen (wp-login.php)
 */
function cmx_customize_login_page() {
	?>
	<style>
		.language-switcher, .privacy-policy-page-link { display: none; }
		.forgetmenot { position: relative; top: 5px; }
		.login #nav { text-align: right; }
		.login form, #loginform {
			position: relative;
			margin-left: auto;
			margin-right: auto;
			box-shadow: none;
			border-width: 2px;
			border-style: solid;
			border-color: #8d44ac;
			border-radius: 20px;
			padding-top: 20px;
			padding-bottom: 20px;
		}
		.login h1 a {
			background-image: url('https://cloudmeister.ch/wp-content/uploads/cloudmeister.png') !important;
			background-size: contain !important;
			width: 100% !important;
		}
		#login form input[type=text],
		#login form input[type=password] {
			border-color: #15adcb !important;
			box-shadow: 0 0 0 1px #15adcb !important;
			border-radius: 10px;
		}
		#login form input[type=text]:focus,
		#login form input[type=password]:focus {
			border-color: #8d44ac !important;
			box-shadow: 0 0 0 1px #8d44ac !important;
			border-radius: 5px;
		}
		#login form p.submit #wp-submit {
			background-color: #15adcb !important;
			border-color: #15adcb !important;
			border-radius: 5px;
		}
		#login form p.submit #wp-submit:hover {
			background-color: #8d44ac !important;
			border-color: #8d44ac !important;
		}
	</style>
	<?php
}

/**
 * Klick-URL des Login-Logos
 *
 * @param string $url Standard-URL.
 * @return string
 */
function cmx_custom_login_url( $url ) {
	$custom = \esc_url( \get_option( 'cmx_logo_link', 'https://cloudmeister.ch/' ) );
	return $custom ?: $url;
}

/**
 * Hooks registrieren – NUR login_head benutzen
 */
\add_action( 'login_head', __NAMESPACE__ . '\\cmx_customize_login_page', 20 );
\add_filter( 'login_headerurl', __NAMESPACE__ . '\\cmx_custom_login_url' );
