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

if (!\function_exists(__NAMESPACE__ . '\\cmx65_adminbar_fallback_avatar_url')) {
	function cmx65_adminbar_fallback_avatar_url(): string {
		return (string) \plugins_url('assets/login.png', \dirname(__DIR__, 2) . '/cmx-misbuero.php');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx65_adminbar_fallback_avatar_markup')) {
	function cmx65_adminbar_fallback_avatar_markup(int $size, string $fallback_url): string {
		$size = $size > 0 ? $size : 26;
		return '<img alt="" src="' . \esc_url($fallback_url) . '" class="avatar avatar-' . $size . ' photo cmx-adminbar-fallback-avatar" height="' . $size . '" width="' . $size . '" decoding="async" loading="lazy" />';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx65_adminbar_force_avatar_in_title')) {
	function cmx65_adminbar_force_avatar_in_title(string $title, int $size, string $fallback_url): string {
		$fallback_img = cmx65_adminbar_fallback_avatar_markup($size, $fallback_url);
		if (\preg_match('/<img\b[^>]*>/i', $title)) {
			return (string) \preg_replace('/<img\b[^>]*>/i', $fallback_img, $title, 1);
		}
		return $fallback_img . ' ' . $title;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx65_adminbar_pdf_upload_url')) {
	function cmx65_adminbar_pdf_upload_url(): string {
		$token = (string) \get_option(MIS_BUERO_BELEG_UPLOAD::OPTION_TOKEN, '');
		if ($token === '') {
			$token = \wp_generate_uuid4();
			\update_option(MIS_BUERO_BELEG_UPLOAD::OPTION_TOKEN, $token, false);
		}

		return (string) \home_url('/mis-upload/?token=' . \rawurlencode($token));
	}
}

add_action('admin_bar_menu', __NAMESPACE__ . '\\cmx65_adminbar_my_account_avatar_fallback', 99999);
function cmx65_adminbar_my_account_avatar_fallback(\WP_Admin_Bar $wp_admin_bar): void {
	if (!\is_user_logged_in() || !\is_admin_bar_showing()) {
		return;
	}

	$fallback_url = \esc_url(cmx65_adminbar_fallback_avatar_url());
	if ($fallback_url === '') {
		return;
	}

	$current_user = \wp_get_current_user();
	$user_id = ($current_user instanceof \WP_User && $current_user->exists()) ? (int) $current_user->ID : 0;
	$should_force_fallback = false;
	if ($user_id > 0 && \function_exists('\\get_avatar_data')) {
		$avatar_data = (array) \get_avatar_data($user_id, ['size' => 64]);
		$should_force_fallback = empty($avatar_data['found_avatar']);
	}

	$targets = [
		'my-account' => ['size' => 26],
		'user-info'  => ['size' => 64],
	];

	foreach ($targets as $node_id => $config) {
		$node = $wp_admin_bar->get_node($node_id);
		if (!$node) {
			continue;
		}

		$title = (string) ($node->title ?? '');
		if ($title === '') {
			continue;
		}

		$size = (int) ($config['size'] ?? 26);
		if ($should_force_fallback || \stripos($title, '<img') === false) {
			$node->title = cmx65_adminbar_force_avatar_in_title($title, $size, $fallback_url);
			$wp_admin_bar->add_node((array) $node);
		}
	}
}

add_action('admin_footer', __NAMESPACE__ . '\\cmx65_adminbar_avatar_fallback_script', 20);
add_action('wp_footer', __NAMESPACE__ . '\\cmx65_adminbar_avatar_fallback_script', 20);
function cmx65_adminbar_avatar_fallback_script(): void {
	if (!\is_user_logged_in() || !\is_admin_bar_showing()) {
		return;
	}

	$fallback_url = (string) cmx65_adminbar_fallback_avatar_url();
	if ($fallback_url === '') {
		return;
	}
	?>
	<script>
	(function(){
		var fallbackUrl = <?php echo \wp_json_encode($fallback_url); ?>;
		if (!fallbackUrl) return;

		function applyFallback(img) {
			if (!img) return;
			img.setAttribute('src', fallbackUrl);
			img.removeAttribute('srcset');
			img.classList.add('cmx-adminbar-fallback-avatar');
		}

		function isDefaultAvatar(img) {
			if (!img) return false;
			if (img.classList.contains('avatar-default')) return true;
			var src = String(img.getAttribute('src') || '');
			return /[?&]d=/.test(src) || /gravatar\.com\/avatar/i.test(src);
		}

		function ensureAvatar(selector, size) {
			var item = document.querySelector(selector);
			if (!item) return;

			var img = item.querySelector('img.avatar');
			if (!img) {
				img = document.createElement('img');
				img.alt = '';
				img.width = size;
				img.height = size;
				img.decoding = 'async';
				img.loading = 'lazy';
				img.className = 'avatar avatar-' + String(size) + ' photo cmx-adminbar-fallback-avatar';
				item.insertBefore(img, item.firstChild);
				applyFallback(img);
				return;
			}

			img.addEventListener('error', function handleError() {
				applyFallback(img);
			}, { once: true });

			if (!img.getAttribute('src') || isDefaultAvatar(img)) {
				applyFallback(img);
			}
		}

		function ensureAvatars() {
			ensureAvatar('#wp-admin-bar-my-account > .ab-item', 26);
			ensureAvatar('#wp-admin-bar-user-info > .ab-item', 64);
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', ensureAvatars, { once: true });
		} else {
			ensureAvatars();
		}
	})();
	</script>
	<?php
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
			. '.cmx-front-quicklinks .cmx-front-dropdown{position:relative;display:inline-flex;align-items:center;}'
			. '.cmx-front-quicklinks .cmx-front-dropdown-toggle{display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:700;list-style:none;}'
			. '.cmx-front-quicklinks .cmx-front-dropdown-toggle::-webkit-details-marker{display:none;}'
			. '.cmx-front-quicklinks .cmx-front-dropdown-toggle::after{content:"▾";font-size:11px;opacity:.85;}'
			. '.cmx-front-quicklinks .cmx-front-dropdown-menu{position:absolute;top:calc(100% + 8px);left:0;min-width:180px;padding:8px 0;border-radius:12px;background:#8f211b;box-shadow:0 18px 38px rgba(0,0,0,.24);display:flex;flex-direction:column;z-index:1000;}'
			. '.cmx-front-quicklinks .cmx-front-dropdown-menu a{padding:8px 14px;white-space:nowrap;}'
			. '.cmx-front-quicklinks .cmx-front-dropdown-menu a:hover,.cmx-front-quicklinks .cmx-front-dropdown-menu a:focus{background:rgba(255,255,255,.08);text-decoration:none;}'
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
		['label' => 'Mis Büro'],
		['label' => 'Archiv', 'href' => \home_url('/archiv/')],
		['label' => 'Scanner', 'href' => \home_url('/scanner/')],
	];

	$company_links = [
		['label' => 'Website', 'href' => 'https://misbuero.ch/', 'target' => '_blank'],
		['label' => 'FAQ', 'href' => 'https://misbuero.ch/faq/', 'target' => '_blank'],
		['label' => 'Aktuelles', 'href' => 'https://misbuero.ch/aktuelles/', 'target' => '_blank'],
		['label' => 'YouTube', 'href' => 'https://www.youtube.com/@MisBuero', 'target' => '_blank'],
	];

	$apps_links = [];

	if (\current_user_can('manage_options')) {
		$token = \get_option(MIS_BUERO_BELEG_UPLOAD::OPTION_TOKEN);
		if (empty($token)) {
			$token = \wp_generate_uuid4();
			\update_option(MIS_BUERO_BELEG_UPLOAD::OPTION_TOKEN, $token, false);
		}
		$apps_links[] = ['label' => 'PDF Upload', 'href' => \home_url('/mis-upload/?token=' . $token), 'target' => '_blank'];
		if (\function_exists(__NAMESPACE__ . '\\cmx_ext_time_stopwatch_url')) {
			$stopwatch_url = (string) cmx_ext_time_stopwatch_url();
			if ($stopwatch_url !== '') {
				$apps_links[] = ['label' => 'Stopuhr', 'href' => $stopwatch_url, 'target' => '_blank'];
			}
		}
		$apps_links[] = ['label' => 'Monitoring', 'href' => 'https://anyboard.io/', 'target' => '_blank'];
	} else {
		$links[] = ['label' => 'Monitoring', 'href' => 'https://anyboard.io/', 'target' => '_blank'];
	}

	$links[] = ['label' => 'Support-Ticket', 'href' => $support_url];

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
			if ($label === 'Mis Büro' && !empty($company_links)) {
				echo '<details class="cmx-front-dropdown">';
				echo '<summary class="cmx-front-dropdown-toggle">Mis Büro</summary>';
				echo '<div class="cmx-front-dropdown-menu">';
				foreach ($company_links as $company_link) {
					$company_href = (string) ($company_link['href'] ?? '');
					$company_label = (string) ($company_link['label'] ?? '');
					$company_target = (string) ($company_link['target'] ?? '');
					$company_rel = ($company_target === '_blank') ? ' rel="noopener noreferrer"' : '';
					$company_target_attr = ($company_target !== '') ? ' target="' . \esc_attr($company_target) . '"' : '';
					echo '<a href="' . \esc_url($company_href) . '"' . $company_target_attr . $company_rel . '>' . \esc_html($company_label) . '</a>';
				}
				echo '</div>';
				echo '</details>';
				continue;
			}
			echo '<a href="' . \esc_url($href) . '"' . $class_attr . $target_attr . $rel . '>' . \esc_html($label) . '</a>';
		}
	if (!empty($apps_links)) {
		if (!$first) {
			echo '<span class="cmx-front-sep">|</span>';
		}
		echo '<details class="cmx-front-dropdown">';
		echo '<summary class="cmx-front-dropdown-toggle">Apps</summary>';
		echo '<div class="cmx-front-dropdown-menu">';
		foreach ($apps_links as $link) {
			$href = (string) ($link['href'] ?? '');
			$label = (string) ($link['label'] ?? '');
			$target = (string) ($link['target'] ?? '');
			$rel = ($target === '_blank') ? ' rel="noopener noreferrer"' : '';
			$target_attr = ($target !== '') ? ' target="' . \esc_attr($target) . '"' : '';
			echo '<a href="' . \esc_url($href) . '"' . $target_attr . $rel . '>' . \esc_html($label) . '</a>';
		}
		echo '</div>';
		echo '</details>';
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

	if (\is_admin()) {
		echo '
		<style>
        #wpadminbar [id^="wp-admin-bar-cmx65_"] > .ab-item,
        #wpadminbar [id^="wp-admin-bar-cmx65_"] > .ab-item:focus,
        #wpadminbar [id^="wp-admin-bar-cmx65_"]:hover > .ab-item,
        #wpadminbar [id^="wp-admin-bar-cmx65_"].hover > .ab-item {
            background: #a42c24 !important;
            color: #fff !important;
        }
        #wpadminbar [id^="wp-admin-bar-cmx65_"] > .ab-item:hover,
        #wpadminbar [id^="wp-admin-bar-cmx65_"] > .ab-item:focus,
        #wpadminbar [id^="wp-admin-bar-cmx65_"]:hover > .ab-item,
        #wpadminbar [id^="wp-admin-bar-cmx65_"].hover > .ab-item {
            background: #a42c24 !important;
            color: #ffeb3b !important;
        }
        #wpadminbar [id^="wp-admin-bar-cmx65_"] .ab-sub-wrapper,
        #wpadminbar [id^="wp-admin-bar-cmx65_"] .ab-sub-wrapper .ab-submenu,
        #wpadminbar [id^="wp-admin-bar-cmx65_"] .ab-sub-wrapper .ab-submenu .ab-item {
            background: #a42c24 !important;
            color: #fff !important;
        }
        #wpadminbar [id^="wp-admin-bar-cmx65_"] .ab-sub-wrapper .ab-item:hover,
        #wpadminbar [id^="wp-admin-bar-cmx65_"] .ab-sub-wrapper .ab-item:focus,
        #wpadminbar [id^="wp-admin-bar-cmx65_"] .ab-sub-wrapper .ab-item:active {
            background: #a42c24 !important;
            color: #ffeb3b !important;
        }
        #wpadminbar .cmx-nohover > .ab-item {
            cursor: default !important;
            pointer-events: none !important;
            background: #a42c24 !important;
            color: #fff !important;
        }
        #wpadminbar .cmx-nohover > .ab-item:hover {
            background: #a42c24 !important;
            color: #ffeb3b !important;
        }
        #wpadminbar #wp-admin-bar-cmx65_monitoring_id > .ab-item {
            cursor: copy !important;
        }
    </style>';
	}

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

	if ( ! current_user_can( 'manage_options' ) ) {
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
	}

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
		$url = cmx65_adminbar_pdf_upload_url();
		$stopwatch_url = \function_exists(__NAMESPACE__ . '\\cmx_ext_time_stopwatch_url')
			? (string) cmx_ext_time_stopwatch_url()
			: '';

		$wp_admin_bar->add_menu( [
			'id'    => 'cmx65_apps_id',
			'title' => 'Apps',
			'href'  => false,
			'meta'  => [
				'title' => 'Apps',
			],
		] );

		$wp_admin_bar->add_menu( [
			'id'     => 'cmx65_apps_pdf_upload_id',
			'parent' => 'cmx65_apps_id',
			'title'  => 'PDF Upload',
			'href'   => esc_url( $url ),
			'meta'   => [
				'title'  => 'PDF Upload',
				'target' => '_blank',
				'rel'    => 'noopener noreferrer',
			],
		] );

		if ( $stopwatch_url !== '' ) {
			$wp_admin_bar->add_menu( [
				'id'     => 'cmx65_apps_stopwatch_id',
				'parent' => 'cmx65_apps_id',
				'title'  => 'Stopuhr',
				'href'   => esc_url( $stopwatch_url ),
				'meta'   => [
					'title'  => 'Stopuhr',
					'target' => '_blank',
					'rel'    => 'noopener noreferrer',
				],
			] );
		}

		$wp_admin_bar->add_menu( [
			'id'     => 'cmx65_monitoring_id',
			'parent' => 'cmx65_apps_id',
			'title'  => 'Monitoring',
			'href'   => 'https://anyboard.io/',
			'meta'   => [
				'title'  => __('Monitoring für Apple TV', 'textdomain'),
				'target' => '_blank',
				'rel'    => 'noopener',
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
		'parent' => 'cmx65_name_id',
		'title' => 'FAQ',
		'href'  => 'https://misbuero.ch/faq/',
		'meta'  => [
			'title'  => __('Du hast allgemeines Fragen?', 'textdomain'),
			'target' => '_blank',
		],
	]);

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_aktuelles_id',
		'parent' => 'cmx65_name_id',
		'title' => 'Aktuelles',
		'href'  => 'https://misbuero.ch/aktuelles/',
		'meta'  => [
			'title'  => __('Aktuelles für Dich Online', 'textdomain'),
			'target' => '_blank',
		],
	]);

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_videos_id',
		'parent' => 'cmx65_name_id',
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

	// $wp_admin_bar->add_menu([
	// 	'id'    => 'cmx65_menux_id',
	// 	'title' => '<span class="ab-label" style="cursor:default; pointer-events:none; color:yellow;">–</span>',
	// 	'href'  => false,
	// 	'meta'  => [
	// 		'title'  => '',
	// 		'class' => 'cmx-nohover',
	// 	],
	// ]);

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
	add_action('admin_footer', __NAMESPACE__ . '\\cmx65_apps_pdf_upload_script');
	add_action('wp_footer', __NAMESPACE__ . '\\cmx65_apps_pdf_upload_script');
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

function cmx65_apps_pdf_upload_script(): void
{
	echo '
		<script>
        document.addEventListener("DOMContentLoaded", function () {
            var link = document.querySelector("#wp-admin-bar-cmx65_apps_pdf_upload_id > .ab-item");
            if (!link) return;
            link.addEventListener("click", function (event) {
                var url = link.getAttribute("href");
                if (!url) return;
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
                    } catch (e) {
                    } finally {
                        document.body.removeChild(textarea);
                    }
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).catch(fallbackCopy);
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
