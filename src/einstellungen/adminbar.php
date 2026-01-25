<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/* --------------------------------------------------------------------------
 * Admin-Bar (Link auf Support-Tab)
 * -------------------------------------------------------------------------- */

add_action('admin_bar_menu', __NAMESPACE__ . '\\cmx65_adminbar', 999);
function cmx65_adminbar($wp_admin_bar) {

	$wp_admin_bar->remove_node('wp-logo');
	$wp_admin_bar->remove_node('new-content');
	$wp_admin_bar->remove_node('comments');

	echo '
		<style>
        #wpadminbar .cmx-nohover > .ab-item {
            cursor: default !important;
            pointer-events: none !important;
            background: none !important;
            color: #fff !important;
        }
        #wpadminbar .cmx-nohover > .ab-item:hover {
            background: none !important;
            color: yellow !important;
        }
        #wpadminbar #wp-admin-bar-cmx65_monitoring_id > .ab-item {
            cursor: copy !important;
        }
    </style>';

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_name_id',
		'title' => '<span class="ab-label" style="cursor:default; pointer-events:none;">Mis Büro</span>',
		'href'  => false,
		'meta'  => [
			'title'  => '',
			'class' => 'cmx-nohover',
		],
	]);


	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_menu1_id',
		'title' => '<span class="ab-label" style="cursor:default; pointer-events:none; color:yellow;">–</span>',
		'href'  => false,
		'meta'  => [
			'title'  => '',
			'class' => 'cmx-nohover',
		],
	]);


	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_monitoring_id',
		'title' => 'Monitoring',
		'href'  => 'https://anyboard.io/',
		'meta'  => [
			'title'  => __('Monitoring für Apple TV', 'textdomain'),
			'target' => '_blank',
			'rel'    => 'noopener',
		],
	]);

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_katalog_id',
		'title' => 'Katalog',
		'href'  => '/katalog/',
		'meta'  => [
			'title'  => __('Dein Online Katalog', 'textdomain'),
			'target' => '_blank',
		],
	]);

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_telefon_id',
		'title' => 'Telefonbuch',
		'href'  => '/telefonbuch/',
		'meta'  => [
			'title'  => __('Dein Telefonbuch', 'textdomain'),
			'target' => '_blank',
		],
	]);

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_archiv_id',
		'title' => 'Archiv',
		'href'  => '/archiv/',
		'meta'  => [
			'title'  => __('Dein Archiv', 'textdomain'),
			'target' => '_blank',
		],
	]);


	// $wp_admin_bar->add_menu([
	// 	'id'    => 'cmx65_menu2_id',
	// 	'title' => '<span class="ab-label" style="cursor:default; pointer-events:none; color:yellow;">–</span>',
	// 	'href'  => false,
	// 	'meta'  => [
	// 		'title'  => '',
	// 		'class' => 'cmx-nohover',
	// 	],
	// ]);

	if ( current_user_can( 'manage_options' ) ) {
		$token = get_option( MIS_BUERO_BELEG_UPLOAD::OPTION_TOKEN );
		if ( empty( $token ) ) {
			$token = wp_generate_uuid4();
			update_option( MIS_BUERO_BELEG_UPLOAD::OPTION_TOKEN, $token, false );
		}

		$url = home_url( '/mis-upload/?token=' . $token );

		$wp_admin_bar->add_menu( [
			'id'    => 'mis-buero-upload',
			'title' => 'Upload-Link',
			'href'  => esc_url( $url ),
			'meta'  => [
				'class'    => 'mis-buero-upload-link',
			],
		] );
	}


	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_menu23_id',
		'title' => '<span class="ab-label" style="cursor:default; pointer-events:none; color:yellow;">–</span>',
		'href'  => false,
		'meta'  => [
			'title'  => '',
			'class' => 'cmx-nohover',
		],
	]);

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_faq_id',
		'title' => 'FAQ',
		'href'  => 'https://misbuero.ch/faq/',
		'meta'  => [
			'title'  => __('Du hast allgemeines Fragen?', 'textdomain'),
			'target' => '_blank',
		],
	]);

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_aktuelles_id',
		'title' => 'Aktuelles',
		'href'  => 'https://misbuero.ch/aktuelles/',
		'meta'  => [
			'title'  => __('Aktuelles für Dich Online', 'textdomain'),
			'target' => '_blank',
		],
	]);

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_videos_id',
		'title' => 'YouTube',
		'href'  => 'https://www.youtube.com/@MisBuero',
		'meta'  => [
			'title'  => __('Mehr über Mis Büro erfahren...', 'textdomain'),
			'target' => '_blank',
		],
	]);

	// $wp_admin_bar->add_menu([
	// 	'id'    => 'cmx65_roadmap',
	// 	'title' => 'Roadmap',
	// 	'href'  => 'https://misbuero.ch/roadmap/',
	// 	'meta'  => [
	// 		'title'  => __('Wie geht es weiter mit Mis Büro?', 'textdomain'),
	// 		'target' => '_blank',
	// 	],
	// ]);

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_menux_id',
		'title' => '<span class="ab-label" style="cursor:default; pointer-events:none; color:yellow;">–</span>',
		'href'  => false,
		'meta'  => [
			'title'  => '',
			'class' => 'cmx-nohover',
		],
	]);

	// Support-URL: wenn Konstante vorhanden, nutze sie, sonst Fallback
	if (defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')) {
		$support_url = add_query_arg(
			[
				'page' => CMX_SETTINGS_SLUG,
				'tab'  => 'support',
			],
			admin_url('admin.php')
		);
	} else {
		$support_url = admin_url('admin.php?page=cmx-einstellungen&tab=support');
	}

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_support_id',
		'title' => 'Support-Ticket',
		'href'  => esc_url($support_url),
		'meta'  => [
			'title' => __('Hier kannst ein Support Ticket erstellen', 'textdomain'),
		],
	]);

	add_action('admin_footer', __NAMESPACE__ . '\\cmx65_anyboard_copy_script');
	add_action('wp_footer', __NAMESPACE__ . '\\cmx65_anyboard_copy_script');
	add_action('admin_footer', __NAMESPACE__ . '\\cmx65_upload_copy_script');
	add_action('wp_footer', __NAMESPACE__ . '\\cmx65_upload_copy_script');
}

function cmx65_anyboard_copy_script(): void
{
	$current_user = wp_get_current_user();
	$user = $current_user && $current_user->exists() ? $current_user->user_login : '';
	$pw = $current_user && $current_user->exists()
		? (string) get_user_meta($current_user->ID, 'cmx_anyboard_pw', true)
		: '';

	$args = [
		'user' => $user,
		'pw' => '{DeinPassword}',
	];

	$anyboard_url = add_query_arg($args, home_url('/wp-json/cmx-misbuero/v1/anyboard'));

	echo '
		<script>
        document.addEventListener("DOMContentLoaded", function () {
            var link = document.querySelector("#wp-admin-bar-cmx65_monitoring_id > .ab-item");
            if (!link) return;
            link.addEventListener("click", function (event) {
                event.preventDefault();
                var url = ' . json_encode($anyboard_url) . ';
                var openAnyboard = function () {
                    window.open("https://anyboard.io/", "_blank", "noopener");
                };
                if (!url) {
                    openAnyboard();
                    return;
                }
                var done = function () {
                    alert("URL für Anyboard wurde in die Zeischenablage kopiert.");
                    openAnyboard();
                };
                var fallbackCopy = function () {
                    var textarea = document.createElement("textarea");
                    textarea.value = url;
                    textarea.setAttribute("readonly", "");
                    textarea.style.position = "fixed";
                    textarea.style.top = "-1000px";
                    document.body.appendChild(textarea);
                    textarea.select();
                    try {
                        document.execCommand("copy");
                        done();
                    } catch (e) {
                        window.prompt("URL kopieren:", url);
                        openAnyboard();
                    } finally {
                        document.body.removeChild(textarea);
                    }
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(done, fallbackCopy);
                } else {
                    fallbackCopy();
                }
            });
        });
        </script>';
}

function cmx65_upload_copy_script(): void
{
	echo '
		<script>
        document.addEventListener("DOMContentLoaded", function () {
            var link = document.querySelector("#wp-admin-bar-mis-buero-upload > .ab-item");
            if (!link) return;
            link.addEventListener("click", function (event) {
                event.preventDefault();
            var url = link.getAttribute("href");
            if (!url) return;
                var done = function () {
                    alert("Upload-Link wurde in die Zwischenablage kopiert");
                    window.open("https://www.youtube.com/shorts/ScpGtbqrpkY", "_blank", "noopener");
                };
                var fallbackCopy = function () {
                    var textarea = document.createElement("textarea");
                    textarea.value = url;
                    textarea.setAttribute("readonly", "");
                    textarea.style.position = "fixed";
                    textarea.style.top = "-1000px";
                    document.body.appendChild(textarea);
                    textarea.select();
                    try {
                        document.execCommand("copy");
                        done();
                    } catch (e) {
                        window.prompt("Upload-Link kopieren:", url);
                    } finally {
                        document.body.removeChild(textarea);
                    }
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(done, fallbackCopy);
                } else {
                    fallbackCopy();
                }
            });
        });
        </script>';
}
