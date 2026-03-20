<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/* --------------------------------------------------------------------------
 * Admin-Bar (Link auf Support-Tab)
 * -------------------------------------------------------------------------- */

function cmx65_is_home_request_path(): bool {
	$request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
	$request_path = (string) \parse_url($request_uri, \PHP_URL_PATH);
	if ($request_path === '') {
		$request_path = '/';
	}
	$request_path = '/' . \ltrim($request_path, '/');
	$request_path = \rtrim($request_path, '/');
	if ($request_path === '') {
		$request_path = '/';
	}

	$home_path = (string) \parse_url(\home_url('/'), \PHP_URL_PATH);
	if ($home_path === '') {
		$home_path = '/';
	}
	$home_path = '/' . \ltrim($home_path, '/');
	$home_path = \rtrim($home_path, '/');
	if ($home_path === '') {
		$home_path = '/';
	}

	return $request_path === $home_path;
}

add_filter('show_admin_bar', __NAMESPACE__ . '\\cmx65_filter_show_adminbar_on_instance_home', 50);
function cmx65_filter_show_adminbar_on_instance_home($show): bool {
	if (!(bool) $show) {
		return false;
	}
	if (\is_admin() || \wp_doing_ajax()) {
		return (bool) $show;
	}
	if (!\is_user_logged_in()) {
		return (bool) $show;
	}
	if (cmx65_is_home_request_path()) {
		return false;
	}
	return (bool) $show;
}

function cmx65_is_instance_home_request(): bool {
	if (\is_admin() || \wp_doing_ajax()) {
		return false;
	}
	if (!\is_user_logged_in()) {
		return false;
	}
	return (\is_front_page() || \is_home() || cmx65_is_home_request_path());
}

add_action('wp_head', __NAMESPACE__ . '\\cmx65_render_front_quicklinks_css', 20);
function cmx65_render_front_quicklinks_css(): void {
	if (!cmx65_is_instance_home_request()) {
		return;
	}
		echo '<style id="cmx-front-quicklinks-css">'
			. '.cmx-front-quicklinks{display:flex;align-items:center;gap:12px;padding:8px 14px;background:#a42c24;color:#fff;font-size:13px;line-height:1.3;}'
			. '.cmx-front-quicklinks-main{display:flex;flex-wrap:wrap;align-items:center;gap:10px;}'
			. '.cmx-front-quicklinks a{color:#fff;text-decoration:none;font-weight:700;}'
			. '.cmx-front-quicklinks a.cmx-front-home-link{color:#ffeb3b;}'
			. '.cmx-front-quicklinks a:hover,.cmx-front-quicklinks a:focus{text-decoration:underline;color:#fff;}'
			. '.cmx-front-quicklinks .cmx-front-sep{opacity:.55;}'
			. '.cmx-front-quicklinks-logout{margin-left:auto;white-space:nowrap;}'
		. 'html{margin-top:0 !important;}* html body{margin-top:0 !important;}'
		. '@media (max-width: 900px){.cmx-front-quicklinks{flex-wrap:wrap;}.cmx-front-quicklinks-logout{margin-left:0;}}'
		. '@media screen and (max-width:782px){html{margin-top:0 !important;}}'
		. '</style>';
}

add_action('wp_body_open', __NAMESPACE__ . '\\cmx65_render_front_quicklinks', 5);
function cmx65_render_front_quicklinks(): void {
	if (!cmx65_is_instance_home_request()) {
		return;
	}

	$support_url = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
		? \add_query_arg(
			[
				'page' => CMX_SETTINGS_SLUG,
				'tab'  => 'support',
			],
			\admin_url('admin.php')
		)
		: \admin_url('admin.php?page=cmx-einstellungen&tab=support');

	$links = [
		['label' => 'Home', 'href' => \home_url('/wp-admin/')],
		['label' => 'Mis Büro', 'href' => 'https://misbuero.ch/', 'target' => '_blank'],
		['label' => 'Monitoring', 'href' => 'https://anyboard.io/', 'target' => '_blank'],
		['label' => 'Archiv', 'href' => \home_url('/archiv/')],
		['label' => 'Scanner', 'href' => \home_url('/scanner/')],
	];

	if (\current_user_can('manage_options')) {
		$token = \get_option(MIS_BUERO_BELEG_UPLOAD::OPTION_TOKEN);
		if (empty($token)) {
			$token = \wp_generate_uuid4();
			\update_option(MIS_BUERO_BELEG_UPLOAD::OPTION_TOKEN, $token, false);
		}
		$links[] = ['label' => 'Upload-Link', 'href' => \home_url('/mis-upload/?token=' . $token)];
	}

	$links = array_merge($links, [
		['label' => 'FAQ', 'href' => 'https://misbuero.ch/faq/', 'target' => '_blank'],
		['label' => 'Aktuelles', 'href' => 'https://misbuero.ch/aktuelles/', 'target' => '_blank'],
		['label' => 'YouTube', 'href' => 'https://www.youtube.com/@MisBuero', 'target' => '_blank'],
		['label' => 'Support-Ticket', 'href' => $support_url],
	]);

	echo '<nav class="cmx-front-quicklinks" aria-label="Schnellnavigation">';
	echo '<div class="cmx-front-quicklinks-main">';
	$first = true;
	foreach ($links as $link) {
		if (!$first) {
			echo '<span class="cmx-front-sep">|</span>';
		}
		$first = false;
			$href = (string) ($link['href'] ?? '');
			$label = (string) ($link['label'] ?? '');
			$target = (string) ($link['target'] ?? '');
			$rel = ($target === '_blank') ? ' rel="noopener noreferrer"' : '';
			$target_attr = ($target !== '') ? ' target="' . \esc_attr($target) . '"' : '';
			$class_attr = ($label === 'Home') ? ' class="cmx-front-home-link"' : '';
			echo '<a href="' . \esc_url($href) . '"' . $class_attr . $target_attr . $rel . '>' . \esc_html($label) . '</a>';
		}
	echo '</div>';
	echo '<div class="cmx-front-quicklinks-logout"><a href="' . \esc_url(\wp_logout_url(\home_url('/'))) . '">Abmelden</a></div>';
	echo '</nav>';
}

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

	// $wp_admin_bar->add_menu([
	// 	'id'    => 'cmx65_name_id',
	// 	'title' => '<span class="ab-label" style="cursor:default; pointer-events:none;">Mis Büro</span>',
	// 	'href'  => false,
	// 	'meta'  => [
	// 		'title'  => '',
	// 		'class' => 'cmx-nohover',
	// 	],
	// ]);


	// fixme rju 2026-02-15: Evtl. spöter zur eignen homePage springen?
	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_name_id',
		'title' => '<span class="ab-label" style="cursor:default; pointer-events:none;">Mis Büro</span>',
		'href'  => 'https://misbuero.ch/',
		'meta'  => [
			'title'  => __('Zur Mis Büro Homepage', 'textdomain'),
			'target' => '_blank',
			'rel'    => 'noopener',
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
		'href'  => \function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_url') ? cmx_artikel_liste_url() : \home_url('/katalog/'),
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

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_scanner_id',
		'title' => 'Scanner',
		'href'  => '/scanner/',
		'meta'  => [
			'title'  => __('Deine digitale Post', 'textdomain'),
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
	add_action('admin_footer', __NAMESPACE__ . '\\cmx65_katalog_copy_script');
	add_action('wp_footer', __NAMESPACE__ . '\\cmx65_katalog_copy_script');
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

function cmx65_katalog_copy_script(): void
{
	echo '
		<script>
        document.addEventListener("DOMContentLoaded", function () {
            var link = document.querySelector("#wp-admin-bar-cmx65_katalog_id > .ab-item");
            if (!link) return;
            link.addEventListener("click", function (event) {
                event.preventDefault();
                var url = link.getAttribute("href");
                if (!url) return;
                var target = link.getAttribute("target") || "";
                var openLink = function () {
                    if (target === "_blank") {
                        window.open(url, "_blank", "noopener");
                        return;
                    }
                    window.location.href = url;
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
                        openLink();
                    } catch (e) {
                        openLink();
                    } finally {
                        document.body.removeChild(textarea);
                    }
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(openLink, fallbackCopy);
                } else {
                    fallbackCopy();
                }
            });
        });
        </script>';
}
