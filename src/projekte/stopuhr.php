<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_EXT_TIME_STOPWATCH_QUERY_VAR')) {
	\define(__NAMESPACE__ . '\\CMX_EXT_TIME_STOPWATCH_QUERY_VAR', 'mis-stopuhr');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_stopwatch_route_slug')) {
	function cmx_ext_time_stopwatch_route_slug(): string {
		return 'mis-stopuhr';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_stopwatch_url')) {
	function cmx_ext_time_stopwatch_url(int $user_id = 0): string {
		$url = (string) \home_url('/' . cmx_ext_time_stopwatch_route_slug() . '/');
		$token = \function_exists(__NAMESPACE__ . '\\cmx_ext_time_current_user_token')
			? (string) cmx_ext_time_current_user_token($user_id)
			: '';
		if ($token !== '') {
			return (string) \add_query_arg(['token' => \rawurlencode($token)], $url);
		}

		return $url;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_stopwatch_register_rewrite')) {
	function cmx_ext_time_stopwatch_register_rewrite(): void {
		$route = cmx_ext_time_stopwatch_route_slug();
		\add_rewrite_rule('^' . \preg_quote($route, '/') . '/?$', 'index.php?' . CMX_EXT_TIME_STOPWATCH_QUERY_VAR . '=1', 'top');
		\add_rewrite_tag('%' . CMX_EXT_TIME_STOPWATCH_QUERY_VAR . '%', '1');
	}
	\add_action('init', __NAMESPACE__ . '\\cmx_ext_time_stopwatch_register_rewrite', 20);
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_stopwatch_maybe_flush_rewrite')) {
	function cmx_ext_time_stopwatch_maybe_flush_rewrite(): void {
		$route_key = '^' . \preg_quote(cmx_ext_time_stopwatch_route_slug(), '/') . '/?$';
		$rules = \get_option('rewrite_rules');
		if (\is_array($rules) && isset($rules[$route_key])) {
			return;
		}

		cmx_ext_time_stopwatch_register_rewrite();
		\flush_rewrite_rules(false);
	}
	\add_action('init', __NAMESPACE__ . '\\cmx_ext_time_stopwatch_maybe_flush_rewrite', 21);
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_stopwatch_is_request')) {
	function cmx_ext_time_stopwatch_is_request(): bool {
		return (int) \get_query_var(CMX_EXT_TIME_STOPWATCH_QUERY_VAR) === 1;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_stopwatch_config_array')) {
	function cmx_ext_time_stopwatch_config_array(): array {
		return [
			'connectAction'        => CMX_EXT_TIME_CONNECT_ACTION,
			'bootstrapAction'      => CMX_EXT_TIME_BOOTSTRAP_ACTION,
			'searchProjectsAction' => CMX_EXT_TIME_SEARCH_PROJECTS_ACTION,
			'searchContactsAction' => CMX_EXT_TIME_SEARCH_CONTACTS_ACTION,
			'searchArticlesAction' => CMX_EXT_TIME_SEARCH_ARTICLES_ACTION,
			'saveAction'           => CMX_EXT_TIME_SAVE_ACTION,
			'noteSubjects'         => \function_exists(__NAMESPACE__ . '\\cmx_notizen_betreff_options')
				? \array_values(\array_map('strval', (array) cmx_notizen_betreff_options()))
				: ['Meeting', 'E-Mail', 'Telefonat', 'Vor Ort', 'Remote', 'Arbeit'],
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_stopwatch_bootstrap')) {
		function cmx_ext_time_stopwatch_bootstrap(int $user_id): array {
			$bootstrap = \function_exists(__NAMESPACE__ . '\\cmx_ext_time_bootstrap_data')
				? (array) cmx_ext_time_bootstrap_data($user_id)
				: [];
			$user = $user_id > 0 ? \get_userdata($user_id) : null;
				$user_login = $user instanceof \WP_User ? (string) $user->user_login : '';
				$user_display = $user instanceof \WP_User ? (string) $user->display_name : '';
				$page_token = \function_exists(__NAMESPACE__ . '\\cmx_ext_time_current_user_token')
					? (string) cmx_ext_time_current_user_token($user_id)
					: '';
				$bootstrap['userId'] = $user_id;
				$bootstrap['userLogin'] = $user_login;
				$bootstrap['userDisplay'] = $user_display;
				$bootstrap['pageToken'] = $page_token;
				$bootstrap['storageNamespace'] = 'cmxStopwatch.user.' . $user_id;
				$bootstrap['stopwatchUrl'] = cmx_ext_time_stopwatch_url($user_id);

		return $bootstrap;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_stopwatch_handle_request')) {
	function cmx_ext_time_stopwatch_handle_request(): void {
		if (!cmx_ext_time_stopwatch_is_request()) {
			return;
		}

		if (!\defined('DONOTCACHEPAGE')) {
			\define('DONOTCACHEPAGE', true);
		}
		\nocache_headers();

		$request_uri = isset($_SERVER['REQUEST_URI']) ? (string) \wp_unslash($_SERVER['REQUEST_URI']) : '';
		if ($request_uri === '') {
			$request_uri = '/' . cmx_ext_time_stopwatch_route_slug() . '/';
		}

		if (isset($_GET['swv'])) {
			$redirect_url = (string) \remove_query_arg('swv', $request_uri);
			\wp_redirect($redirect_url, 302, 'CMX Stopwatch Cleanup');
			exit;
		}

		$action = isset($_GET['cmx_stopwatch_action']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_stopwatch_action'])) : '';
		if ($action === 'abort') {
			$redirect_url = (string) \remove_query_arg('cmx_stopwatch_action', $request_uri);
			if ($redirect_url === '' || $redirect_url === '/' || $redirect_url === '?') {
				$redirect_url = '/' . cmx_ext_time_stopwatch_route_slug() . '/';
			}
			cmx_ext_time_stopwatch_render_abort_page($redirect_url);
			exit;
		}

		$token = \function_exists(__NAMESPACE__ . '\\cmx_ext_time_request_token')
			? (string) cmx_ext_time_request_token()
			: '';
		$user_id = \function_exists(__NAMESPACE__ . '\\cmx_ext_time_authenticated_user_id')
			? (int) cmx_ext_time_authenticated_user_id(true)
			: 0;

		if ($user_id <= 0) {
			if ($token !== '') {
				\wp_die('Ungültiger Stopuhr-Link.');
			}
			\auth_redirect();
			exit;
		}

		cmx_ext_time_stopwatch_render_page(cmx_ext_time_stopwatch_bootstrap($user_id), cmx_ext_time_stopwatch_config_array());
		exit;
	}
	\add_action('template_redirect', __NAMESPACE__ . '\\cmx_ext_time_stopwatch_handle_request', 20);
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_stopwatch_render_abort_page')) {
	function cmx_ext_time_stopwatch_render_abort_page(string $redirect_url): void {
		$redirect_url = $redirect_url !== '' ? $redirect_url : '/' . cmx_ext_time_stopwatch_route_slug() . '/';
		?>
		<!doctype html>
		<html lang="de">
		<head>
			<meta charset="utf-8">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<meta http-equiv="refresh" content="0;url=<?php echo \esc_attr($redirect_url); ?>">
			<title>Stopuhr wird zurückgesetzt</title>
			<style>
				body{
					margin:0;
					min-height:100vh;
					display:flex;
					align-items:center;
					justify-content:center;
					padding:20px;
					background:#fff;
					font:16px/1.45 "Avenir Next","Segoe UI","Helvetica Neue",sans-serif;
					color:#241b16;
				}
				.card{
					max-width:420px;
					padding:24px;
					border-radius:22px;
					background:rgba(255,255,255,.9);
					border:1px solid rgba(255,255,255,.7);
					box-shadow:0 24px 70px rgba(0,0,0,.14);
					text-align:center;
				}
				h1{margin:0 0 10px;font-size:28px;line-height:1.1}
				p{margin:0;color:#6e6257}
				a{
					display:inline-block;
					margin-top:18px;
					color:#a42c24;
					font-weight:700;
					text-decoration:none;
				}
			</style>
		</head>
		<body>
			<div class="card">
				<h1>Stopuhr wird zurückgesetzt</h1>
				<p>Lokale Sitzungen werden gelöscht und die Seite wird neu geladen.</p>
				<a href="<?php echo \esc_url($redirect_url); ?>">Falls nichts passiert: hier klicken</a>
			</div>
			<script>
				(function () {
					try {
						var keysToRemove = [];
						for (var index = 0; index < window.localStorage.length; index += 1) {
							var key = window.localStorage.key(index);
							if (!key) {
								continue;
							}
								if (key === 'cmxExtTime.activeSession' || key.indexOf('cmxStopwatch.') === 0) {
									keysToRemove.push(key);
								}
						}
						keysToRemove.forEach(function (key) {
							window.localStorage.removeItem(key);
						});
					} catch (error) {}
					window.location.replace(<?php echo \wp_json_encode($redirect_url); ?>);
				})();
			</script>
		</body>
		</html>
		<?php
	}
}

	if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_stopwatch_render_page')) {
		function cmx_ext_time_stopwatch_render_page(array $bootstrap, array $config): void {
			$site_name = (string) ($bootstrap['siteName'] ?? \get_bloginfo('name'));
			$site_url = (string) ($bootstrap['siteUrl'] ?? \home_url('/'));
			$site_admin_url = \trailingslashit($site_url) . 'wp-admin/index.php';
			$site_name_display = $site_name;
				$site_name_parts = \preg_split('/\s*[-\x{2010}-\x{2015}]+\s*/u', $site_name);
				$site_name_parts = \array_values(\array_filter(\array_map('trim', (array) $site_name_parts), 'strlen'));
				if (!empty($site_name_parts)) {
					$site_name_display = (string) \end($site_name_parts);
				}
			if ($site_name_display === '' || $site_name_display === $site_name) {
				$site_host = (string) \parse_url($site_url, \PHP_URL_HOST);
				$site_host = \preg_replace('/^www\./i', '', $site_host);
				$site_host = \preg_replace('/\.misbuero\.ch$/i', '', (string) $site_host);
				$site_host = \preg_replace('/\.local$/i', '', (string) $site_host);
				$site_host = \trim((string) $site_host, '.');
				if ($site_host !== '') {
					$site_name_display = $site_host;
				}
			}
					$user_display = (string) ($bootstrap['userDisplay'] ?? '');
					if ($user_display === '') {
						$user_display = (string) ($bootstrap['userLogin'] ?? '');
					}
					$stopwatch_url = (string) ($bootstrap['stopwatchUrl'] ?? cmx_ext_time_stopwatch_url((int) ($bootstrap['userId'] ?? 0)));
					$config['proxyAjaxUrl'] = \function_exists(__NAMESPACE__ . '\\cmx_ext_time_admin_ajax_url')
						? (string) cmx_ext_time_admin_ajax_url()
						: (string) \admin_url('admin-ajax.php');
					$config['proxyConnectAction'] = \defined(__NAMESPACE__ . '\\CMX_EXT_TIME_PROXY_CONNECT_ACTION')
						? (string) \constant(__NAMESPACE__ . '\\CMX_EXT_TIME_PROXY_CONNECT_ACTION')
						: 'cmx_ext_time_proxy_connect';
					$config['proxyBootstrapAction'] = \defined(__NAMESPACE__ . '\\CMX_EXT_TIME_PROXY_BOOTSTRAP_ACTION')
						? (string) \constant(__NAMESPACE__ . '\\CMX_EXT_TIME_PROXY_BOOTSTRAP_ACTION')
						: 'cmx_ext_time_proxy_bootstrap';
					$config['proxySearchAction'] = \defined(__NAMESPACE__ . '\\CMX_EXT_TIME_PROXY_SEARCH_ACTION')
						? (string) \constant(__NAMESPACE__ . '\\CMX_EXT_TIME_PROXY_SEARCH_ACTION')
						: 'cmx_ext_time_proxy_search';
					$config['proxySaveAction'] = \defined(__NAMESPACE__ . '\\CMX_EXT_TIME_PROXY_SAVE_ACTION')
						? (string) \constant(__NAMESPACE__ . '\\CMX_EXT_TIME_PROXY_SAVE_ACTION')
						: 'cmx_ext_time_proxy_save';
					$config['proxyAuthToken'] = (string) ($bootstrap['pageToken'] ?? '');
					$config['stopwatchRoute'] = cmx_ext_time_stopwatch_route_slug();
					$logo_src = \function_exists(__NAMESPACE__ . '\\cmx_ext_time_icon_asset_url')
						? (string) cmx_ext_time_icon_asset_url()
						: '';
			?>
		<!doctype html>
		<html lang="de">
		<head>
			<meta charset="utf-8">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title>Mis Büro - Stopuhr</title>
				<style>
					:root{
						--wp-blue:#2271b1;
						--wp-blue-dark:#135e96;
						--wp-gray-100:#f6f7f7;
						--wp-gray-200:#f0f0f1;
						--wp-gray-700:#3c434a;
						--wp-border:#c3c4c7;
						--wp-danger:#d63638;
						--wp-danger-dark:#b32d2e;
					}
					*,
					*::before,
					*::after{box-sizing:border-box}
					html,body{min-height:100%}
					body{
						margin:0;
						font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI","Helvetica Neue",Arial,sans-serif;
						color:var(--wp-gray-700);
						background:var(--wp-gray-100);
						padding:32px 16px;
					}
					body.cmx-stopwatch-dialog-open{overflow:hidden}
					.page{
						max-width:560px;
						margin:0 auto;
						background:#fff;
						border:1px solid var(--wp-border);
						border-radius:8px;
						padding:24px;
						box-shadow:0 1px 1px rgba(0,0,0,.04);
					}
					.hero{
						display:grid;
						grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);
						align-items:center;
						gap:16px;
						margin-bottom:24px;
					}
					.hero-kicker{display:none}
					.hero-logo-link{
						display:inline-flex;
						justify-self:center;
					}
					.hero-logo{
						display:block;
						max-width:72px;
						height:auto;
						margin:0;
					}
					.hero-meta{
						display:flex;
						flex-direction:column;
						align-items:flex-start;
						gap:2px;
						text-align:left;
						min-width:0;
					}
					.hero-meta strong{
						display:block;
						font-size:14px;
						font-weight:600;
					}
					.hero-meta a{
						color:#1d2327;
						text-decoration:none;
					}
					.hero-meta a:hover,
					.hero-meta a:focus{
						color:var(--wp-blue);
						outline:none;
					}
					.hero-user{
						min-width:0;
						text-align:right;
						font-size:13px;
						color:#646970;
						justify-self:end;
					}
					.wrap{
						padding:0;
						background:transparent;
						border:0;
					}
					.gear{
						appearance:none;
						border:1px solid var(--wp-blue);
						background:#fff;
						color:var(--wp-blue);
						border-radius:4px;
						width:42px;
						height:42px;
						padding:0;
						cursor:pointer;
						display:flex;
						align-items:center;
						justify-content:center;
						line-height:1;
					}
					.gear:hover,
					.gear:focus{background:var(--wp-blue);border-color:var(--wp-blue);color:#fff;outline:none}
					.gear svg{display:block;width:22px;height:22px}
					.panel{
						background:transparent;
						border:0;
						padding:0;
					}
					.stack{display:flex;flex-direction:column;gap:16px}
					label{display:flex;flex-direction:column;gap:6px;font-weight:600}
						input[type=text],input[type=password],select,textarea{
							width:100%;
							box-sizing:border-box;
							border:1px solid var(--wp-border);
							border-radius:4px;
							padding:10px 12px;
						background:#fff;
						font:inherit;
						color:inherit;
					}
						input[type=text]:focus,input[type=password]:focus,select:focus,textarea:focus{
							border-color:var(--wp-blue);
							outline:1px solid var(--wp-blue);
						}
						.cmx-stopwatch-settings-note{
							font-weight:400;
							font-size:12px;
							color:#646970;
						}
						textarea{min-height:96px;resize:vertical}
					.mode-row{display:grid;grid-template-columns:minmax(0,1fr) 160px;gap:12px;align-items:end}
					.mode-field{display:flex;flex-direction:column;gap:5px;min-width:0}
					.field-caption{font-weight:600}
					.note-subject{display:flex;flex-direction:column;justify-content:flex-end;min-width:160px;align-items:flex-end}
					.note-subject select{min-width:160px;width:auto;max-width:160px}
					.note-subject.is-hidden{visibility:hidden;pointer-events:none}
					.inline{display:flex;align-items:center;gap:8px}
					.inline label{font-weight:400;flex-direction:row;align-items:center;gap:6px}
					.mode-options{flex-wrap:nowrap;gap:14px}
					.mode-options label{white-space:nowrap}
					.target-row{display:flex;align-items:center;justify-content:flex-start;gap:12px;flex-wrap:wrap}
					.target-toggle{
						appearance:none;
						border:1px solid var(--wp-blue);
						background:#fff;
						padding:10px 14px;
						color:var(--wp-blue);
						font:inherit;
						font-weight:600;
						cursor:pointer;
						border-radius:4px;
						transition:background-color .16s ease,color .16s ease,border-color .16s ease;
					}
					.target-toggle:hover,
					.target-toggle:focus,
					.target-toggle.is-active{background:var(--wp-blue);border-color:var(--wp-blue);color:#fff;outline:none}
					.muted{color:#646970;font-size:12px}
					button{
						appearance:none;
						border:1px solid var(--wp-blue);
						background:var(--wp-blue);
						color:#fff;
						border-radius:4px;
						padding:10px 14px;
						cursor:pointer;
						font-weight:600;
					}
					button:hover,
					button:focus{background:var(--wp-blue-dark);border-color:var(--wp-blue-dark)}
					button.secondary{background:#fff;color:var(--wp-blue)}
					button.secondary:hover,
					button.secondary:focus{background:var(--wp-gray-200)}
					button.danger{background:var(--wp-danger);border-color:var(--wp-danger)}
					button.danger:hover,
					button.danger:focus{background:var(--wp-danger-dark);border-color:var(--wp-danger-dark)}
					button:disabled{opacity:.55;cursor:not-allowed}
					.suggest-wrap{position:relative}
					.suggest{
						position:absolute;
						left:0;
						right:0;
						top:calc(100% + 4px);
						z-index:1000;
						background:#fff;
						border:1px solid var(--wp-border);
						border-radius:4px;
						max-height:240px;
						overflow:auto;
						display:none;
						box-shadow:0 8px 22px rgba(0,0,0,.12);
					}
					.suggest button{
						display:block;
						width:100%;
						border:0;
						background:none;
						color:inherit;
						text-align:left;
						padding:10px 12px;
						border-radius:0;
						font-weight:400;
					}
					.suggest button:hover,
					.suggest button.active{background:var(--wp-gray-200);color:inherit}
					.suggest .hint{padding:10px 12px;color:#646970}
					.footer{
						display:flex;
						flex-direction:column;
						gap:10px;
						align-items:stretch;
						margin-top:4px;
					}
					.footer-actions{
						display:flex;
						align-items:stretch;
						gap:10px;
					}
					#selection-hint{text-align:left;min-width:0}
					.footer .gear{
						flex:0 0 42px;
						width:42px;
						height:42px;
					}
					#start-stop{width:auto;flex:1 1 auto}
					.session{
						padding:0;
						background:transparent;
						border:0;
						display:flex;
						flex-direction:column;
						gap:6px;
						min-height:0;
					}
					.session.is-info,
					.session.is-success,
					.session.is-error{
						padding:10px 12px;
						border-radius:4px;
						border:1px solid var(--wp-border);
						background:var(--wp-gray-100);
					}
					.session.is-info{border-color:var(--wp-blue)}
					.session.is-success{border-color:#00a32a;background:#f2fff4}
					.session.is-error{border-color:var(--wp-danger);background:#fff1f1}
					.session-message{font-size:13px;font-weight:600;line-height:1.35}
					.task-inline{margin-left:8px;white-space:nowrap}
					.task-inline.is-hidden{visibility:hidden;pointer-events:none}
					dialog.cmx-stopwatch-dialog{
						width:min(calc(100vw - 24px), 560px);
						max-width:560px;
					margin:auto;
					padding:0;
					border:0;
					background:transparent;
					color:inherit;
					overflow:visible;
				}
					dialog.cmx-stopwatch-dialog:not([open]){display:none}
					dialog.cmx-stopwatch-dialog::backdrop{
						background:rgba(17,24,39,.32);
					}
					.cmx-stopwatch-dialog-card{
						width:100%;
						border-radius:8px;
						background:#fff;
						border:1px solid var(--wp-border);
						box-shadow:0 12px 30px rgba(0,0,0,.12);
						padding:24px;
					}
					.cmx-stopwatch-dialog-head{
						display:flex;
						align-items:flex-start;
					justify-content:space-between;
					gap:16px;
					margin-bottom:14px;
				}
				.cmx-stopwatch-dialog-head h2{
					margin:0;
					font-size:22px;
					line-height:1.1;
					letter-spacing:-.02em;
				}
					.cmx-stopwatch-dialog-head p{
						margin:6px 0 0;
						color:#646970;
					}
					.cmx-stopwatch-dialog-close{
						display:inline-flex;
						align-items:center;
						justify-content:center;
						width:32px;
						height:32px;
						padding:0;
						border-radius:4px;
						border:1px solid var(--wp-blue);
						background:#fff;
						color:var(--wp-blue);
						font-size:22px;
						font-weight:600;
						line-height:1;
					}
					.cmx-stopwatch-dialog-close:hover,
					.cmx-stopwatch-dialog-close:focus{
						background:var(--wp-blue);
						border-color:var(--wp-blue);
						color:#fff;
						outline:none;
					}
						.cmx-stopwatch-settings-grid{
							display:grid;
							grid-template-columns:1fr 1fr;
							gap:14px;
							margin-bottom:16px;
						}
						.cmx-stopwatch-settings-grid label{
							font-weight:600;
						}
						.cmx-stopwatch-settings-wide{
							grid-column:1 / -1;
						}
						.cmx-stopwatch-settings-suffix{
							display:flex;
							align-items:center;
							gap:8px;
						}
						.cmx-stopwatch-settings-suffix input{
							flex:1 1 auto;
						}
						.cmx-stopwatch-settings-suffix span{
							white-space:nowrap;
							color:#646970;
							font-weight:400;
						}
						.cmx-stopwatch-password-wrap{
							position:relative;
						}
						.cmx-stopwatch-password-wrap input{
							padding-right:46px;
						}
						.cmx-stopwatch-password-toggle{
							position:absolute;
							top:50%;
							right:8px;
							transform:translateY(-50%);
							width:30px;
							height:30px;
							padding:0;
							border:0;
							background:transparent;
							color:#646970;
							display:inline-flex;
							align-items:center;
							justify-content:center;
							cursor:pointer;
						}
						.cmx-stopwatch-password-toggle:hover,
						.cmx-stopwatch-password-toggle:focus{
							background:transparent;
							border:0;
							color:var(--wp-blue);
							outline:none;
						}
						.cmx-stopwatch-password-toggle svg{
							display:block;
							width:18px;
							height:18px;
						}
						.cmx-stopwatch-dialog-actions{
							display:flex;
							flex-wrap:wrap;
						gap:10px;
						justify-content:flex-end;
						}
						.cmx-stopwatch-settings-actions-primary{
							justify-content:flex-start;
							margin-bottom:8px;
						}
						.cmx-stopwatch-instance-list{
							margin-top:16px;
							padding-top:16px;
							border-top:1px solid var(--wp-border);
							max-height:260px;
							overflow-y:auto;
							overflow-x:hidden;
							overscroll-behavior:contain;
							padding-right:4px;
						}
						.cmx-stopwatch-instance-list.is-empty{
							color:#646970;
							font-size:13px;
						}
						.cmx-stopwatch-instance-card{
							padding:12px;
							border-radius:4px;
							background:var(--wp-gray-100);
							border:1px solid var(--wp-border);
						}
						.cmx-stopwatch-instance-card + .cmx-stopwatch-instance-card{
							margin-top:10px;
						}
						.cmx-stopwatch-instance-card strong{
							display:block;
							font-size:14px;
							color:#1d2327;
						}
						.cmx-stopwatch-instance-meta{
							margin-top:4px;
							color:#646970;
							font-size:13px;
						}
						.cmx-stopwatch-dialog-status{
							margin-top:12px;
							min-height:20px;
							color:#646970;
							font-size:13px;
					}
					.cmx-stopwatch-reminder-card{
						position:relative;
						width:100%;
						border-radius:8px;
						background:#fff;
						border:1px solid var(--wp-border);
						box-shadow:0 12px 30px rgba(0,0,0,.12);
						padding:24px 24px 22px;
						text-align:center;
					}
				.cmx-stopwatch-reminder-card h2{
					margin:0 32px 0 0;
					font-size:26px;
					letter-spacing:-.03em;
				}
					.cmx-stopwatch-reminder-card p{
						margin:10px 0 0;
						color:#646970;
					}
					.cmx-stopwatch-reminder-target{
						margin:18px 0 8px;
						padding:14px;
						border-radius:6px;
						background:var(--wp-gray-200);
						border:1px dashed var(--wp-border);
						font-size:18px;
						font-weight:600;
					}
					.cmx-stopwatch-reminder-count{
						display:inline-flex;
						align-items:center;
					justify-content:center;
					width:76px;
						height:76px;
						margin:14px auto 4px;
						border-radius:999px;
						background:var(--wp-blue);
						color:#fff;
						font-size:28px;
						font-weight:600;
					}
				.cmx-stopwatch-reminder-actions{
					display:flex;
					justify-content:center;
					flex-wrap:wrap;
					gap:10px;
					margin-top:18px;
				}
				.cmx-stopwatch-reminder-actions button{
					display:inline-flex;
					align-items:center;
					justify-content:center;
					min-width:132px;
				}
					.cmx-stopwatch-reminder-status{
						margin-top:12px;
						min-height:20px;
						color:#646970;
						font-size:13px;
					}
							@media (max-width:520px){
								body{padding:16px 12px}
								.page{padding:20px 16px}
								.hero{
									grid-template-columns:1fr;
									justify-items:center;
								}
								.hero-meta{
									align-items:center;
									text-align:center;
								}
								.hero-user{
									width:100%;
									text-align:center;
									justify-self:center;
								}
								.footer-actions{
									width:100%;
								}
								.mode-row,
								.cmx-stopwatch-settings-grid{grid-template-columns:1fr}
							}
					</style>
		</head>
		<body>
				<div class="page">
							<div class="hero">
								<div class="hero-meta">
									<strong><a id="cmx-stopwatch-instance-admin-link" href="<?php echo \esc_url($site_admin_url); ?>"><?php echo \esc_html($site_name_display); ?></a></strong>
								</div>
								<?php if ($logo_src !== '') : ?>
									<a class="hero-logo-link" href="<?php echo \esc_url($site_url); ?>">
										<img class="hero-logo" src="<?php echo \esc_url($logo_src); ?>" alt="Mis Büro">
									</a>
								<?php endif; ?>
								<?php if ($user_display !== '') : ?>
									<div class="hero-user"><?php echo \esc_html($user_display); ?></div>
								<?php endif; ?>
						</div>

					<div class="wrap">
						<div class="panel">
							<div class="stack">
							<label>
								<span>Instanz</span>
								<select id="instance-select"></select>
							</label>
							<div class="mode-row">
								<div class="mode-field">
									<div class="field-caption">Speichern als</div>
									<div class="inline mode-options" id="mode-select">
										<label><input type="radio" name="cmx-ext-time-mode" value="note" checked> Notiz</label>
										<label><input type="radio" name="cmx-ext-time-mode" value="task"> Taetigkeit</label>
										<label class="task-inline task-only" id="task-inline"><input type="checkbox" id="verrechenbar" checked> verrechenbar</label>
									</div>
								</div>
								<label class="note-subject is-hidden" id="note-subject-wrap" aria-label="Betreff">
									<select id="note-subject" aria-label="Betreff"></select>
								</label>
							</div>
								<div class="session" id="session-card">
									<div class="muted" id="session-label">Intervall</div>
									<select id="interval-select" aria-label="Intervall"></select>
									<div id="session-message" class="session-message" hidden></div>
								</div>
							<div class="target-row">
								<button type="button" class="target-toggle" id="target-project" data-target="project">Projekt</button>
								<button type="button" class="target-toggle" id="target-contact" data-target="contact">Kontakt</button>
							</div>
							<label class="suggest-wrap">
								<input type="text" id="project-search" autocomplete="off" placeholder="Projekt suchen...">
								<div class="suggest" id="project-suggest"></div>
							</label>
									<div>
										<textarea id="info-input" aria-label="Weitere Infos im Detail" placeholder="Weitere Infos im Detail..."></textarea>
									</div>
									<div class="footer">
										<div class="muted" id="selection-hint" hidden></div>
										<div class="footer-actions">
											<button type="button" class="gear" id="open-settings" aria-label="Stopuhr-Einstellungen" title="Stopuhr-Einstellungen">
												<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
													<path fill="currentColor" d="M19.43 12.98c.04-.32.07-.65.07-.98s-.03-.66-.08-.98l2.11-1.65a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.6-.22l-2.49 1a7.03 7.03 0 0 0-1.69-.98l-.38-2.65A.5.5 0 0 0 14.1 1h-4a.5.5 0 0 0-.49.42l-.38 2.65c-.61.24-1.17.56-1.69.98l-2.49-1a.5.5 0 0 0-.6.22l-2 3.46a.5.5 0 0 0 .12.64l2.11 1.65c-.05.32-.08.66-.08.98s.03.66.08.98l-2.11 1.65a.5.5 0 0 0-.12.64l2 3.46a.5.5 0 0 0 .6.22l2.49-1c.52.42 1.08.74 1.69.98l.38 2.65a.5.5 0 0 0 .49.42h4a.5.5 0 0 0 .49-.42l.38-2.65c.61-.24 1.17-.56 1.69-.98l2.49 1a.5.5 0 0 0 .6-.22l2-3.46a.5.5 0 0 0-.12-.64l-2.11-1.65ZM12 15.5A3.5 3.5 0 1 1 12 8.5a3.5 3.5 0 0 1 0 7Z"/>
												</svg>
											</button>
											<button type="button" id="start-stop" disabled>Start</button>
										</div>
									</div>
						</div>
					</div>
				</div>
			</div>

				<dialog class="cmx-stopwatch-dialog" id="cmx-stopwatch-settings" aria-labelledby="cmx-stopwatch-settings-title">
					<div class="cmx-stopwatch-dialog-card">
						<div class="cmx-stopwatch-dialog-head">
							<div>
								<h2 id="cmx-stopwatch-settings-title">Stopuhr</h2>
								<p>Instanzen verbinden und Standard-Intervall pro Instanz setzen.</p>
							</div>
							<button type="button" class="cmx-stopwatch-dialog-close" data-stopwatch-close="settings" aria-label="Fenster schließen" title="Fenster schließen">×</button>
						</div>
						<div class="cmx-stopwatch-settings-grid">
							<label>
								<span>Gespeicherte Instanzen</span>
								<select id="cmx-stopwatch-saved-instance"></select>
							</label>
							<label>
								<span>Erfassungsintervall</span>
								<select id="cmx-stopwatch-default-interval"></select>
							</label>
							<label>
								<span>Benutzername</span>
								<input type="text" id="cmx-stopwatch-instance-username" autocomplete="username" placeholder="WP-Benutzername">
							</label>
							<label>
								<span>Passwort</span>
								<div class="cmx-stopwatch-password-wrap">
									<input type="password" id="cmx-stopwatch-instance-password" autocomplete="current-password" placeholder="Passwort">
									<button type="button" class="cmx-stopwatch-password-toggle" id="cmx-stopwatch-password-toggle" aria-label="Passwort anzeigen" aria-pressed="false" title="Passwort anzeigen">
										<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
											<path fill="currentColor" d="M12 5c5.58 0 9.36 4.42 10.55 6-.58.78-1.83 2.29-3.66 3.63l1.42 1.42-.71.71-15-15 .71-.71 2.53 2.53A11.2 11.2 0 0 1 12 5Zm0 2a9.1 9.1 0 0 0-2.69.4l1.68 1.68A3.5 3.5 0 0 1 15.92 13l1.65 1.65A14.76 14.76 0 0 0 21.14 11 15.5 15.5 0 0 0 12 7Zm-8.14.99A15.5 15.5 0 0 0 1.86 11c.58.78 1.83 2.29 3.66 3.63A10.85 10.85 0 0 0 12 17c1.25 0 2.42-.2 3.5-.56l-1.69-1.69a3.5 3.5 0 0 1-4.56-4.56L7.57 8.5A14.42 14.42 0 0 0 3.86 7.99Z"/>
										</svg>
									</button>
								</div>
							</label>
							<label class="cmx-stopwatch-settings-wide">
								<span>Neue Instanz</span>
								<div class="cmx-stopwatch-settings-suffix">
									<input type="text" id="cmx-stopwatch-instance-input" placeholder="DeinKundenName">
									<span>misbuero.ch</span>
								</div>
								<span class="cmx-stopwatch-settings-note">Eingabe als Subdomain, Domain oder komplette URL.</span>
							</label>
						</div>
							<div class="cmx-stopwatch-dialog-actions cmx-stopwatch-settings-actions-primary">
								<button type="button" id="cmx-stopwatch-connect-instance">Instanz laden</button>
								<button type="button" class="secondary" id="cmx-stopwatch-save-instance">Einstellungen speichern</button>
								<button type="button" class="danger" id="cmx-stopwatch-remove-instance">Instanz entfernen</button>
							</div>
							<div class="cmx-stopwatch-dialog-status" id="cmx-stopwatch-settings-status"></div>
							<div class="cmx-stopwatch-instance-list" id="cmx-stopwatch-instance-list"></div>
						</div>
					</dialog>

			<dialog class="cmx-stopwatch-dialog" id="cmx-stopwatch-reminder" aria-labelledby="cmx-stopwatch-reminder-title">
				<div class="cmx-stopwatch-reminder-card">
					<button type="button" class="cmx-stopwatch-dialog-close" id="cmx-stopwatch-reminder-close" aria-label="Fenster schließen" title="Fenster schließen">×</button>
					<h2 id="cmx-stopwatch-reminder-title">Arbeitest Du noch daran?</h2>
					<p>Wenn keine Antwort kommt, wird die laufende Zeit automatisch gespeichert.</p>
					<div class="cmx-stopwatch-reminder-target" id="cmx-stopwatch-reminder-project">Projekt</div>
					<div class="cmx-stopwatch-reminder-count" id="cmx-stopwatch-reminder-countdown">20</div>
					<p>Noch <span id="cmx-stopwatch-reminder-seconds">20</span> Sekunden bis zum automatischen Stopp.</p>
					<div class="cmx-stopwatch-reminder-actions">
						<button type="button" class="secondary" id="cmx-stopwatch-reminder-continue">Ja, weiter</button>
						<button type="button" class="danger" id="cmx-stopwatch-reminder-stop">Nein, stoppen</button>
						<button type="button" class="secondary" id="cmx-stopwatch-reminder-abort">Abbrechen</button>
					</div>
					<div class="cmx-stopwatch-reminder-status" id="cmx-stopwatch-reminder-status"></div>
				</div>
			</dialog>

			<script>
				window.CMX_STOPWATCH_BOOTSTRAP = <?php echo \wp_json_encode($bootstrap, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE); ?>;
				window.CMX_EXT_TIME_CONFIG = <?php echo \wp_json_encode($config, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE); ?>;
			</script>
			<script>
				(function () {
					var BOOTSTRAP = window.CMX_STOPWATCH_BOOTSTRAP || {};
					var CONFIG = window.CMX_EXT_TIME_CONFIG || {};
					var RAW_INSTANCE_KEY = 'cmxExtTime.instances';
					var RAW_ACTIVE_KEY = 'cmxExtTime.activeSession';
					var REMINDER_ENABLED = false;
					var SESSION_SOURCE = 'cmx-stopwatch-web';
					var SESSION_VERSION = 2;
					var storageNamespace = String(BOOTSTRAP.storageNamespace || 'cmxStopwatch.default');
					var reminderTimer = 0;
					var reminderCountdownTimer = 0;
					var reminderResetTimer = 0;
						var reminderDeadlineMs = 0;

						var settingsRoot = document.getElementById('cmx-stopwatch-settings');
						var settingsStatus = document.getElementById('cmx-stopwatch-settings-status');
						var settingsSavedInstance = document.getElementById('cmx-stopwatch-saved-instance');
							var settingsDefaultInterval = document.getElementById('cmx-stopwatch-default-interval');
							var settingsInstanceInput = document.getElementById('cmx-stopwatch-instance-input');
							var settingsUsernameInput = document.getElementById('cmx-stopwatch-instance-username');
							var settingsPasswordInput = document.getElementById('cmx-stopwatch-instance-password');
							var settingsPasswordToggle = document.getElementById('cmx-stopwatch-password-toggle');
							var settingsConnect = document.getElementById('cmx-stopwatch-connect-instance');
							var settingsSave = document.getElementById('cmx-stopwatch-save-instance');
							var settingsRemove = document.getElementById('cmx-stopwatch-remove-instance');
							var settingsList = document.getElementById('cmx-stopwatch-instance-list');
							var reminderRoot = document.getElementById('cmx-stopwatch-reminder');
					var reminderProject = document.getElementById('cmx-stopwatch-reminder-project');
					var reminderCountdown = document.getElementById('cmx-stopwatch-reminder-countdown');
					var reminderSeconds = document.getElementById('cmx-stopwatch-reminder-seconds');
					var reminderStatus = document.getElementById('cmx-stopwatch-reminder-status');
					var reminderContinue = document.getElementById('cmx-stopwatch-reminder-continue');
					var reminderStop = document.getElementById('cmx-stopwatch-reminder-stop');
					var reminderAbort = document.getElementById('cmx-stopwatch-reminder-abort');
					var reminderClose = document.getElementById('cmx-stopwatch-reminder-close');

					function modalIsOpen(modal) {
						return !!(modal && modal.open);
					}

					function openModal(modal) {
						if (!modal) {
							return;
						}
						if (!modalIsOpen(modal)) {
							if (typeof modal.showModal === 'function') {
								modal.showModal();
							} else {
								modal.setAttribute('open', 'open');
							}
						}
						setDialogState();
					}

					function closeModal(modal) {
						if (!modal) {
							return;
						}
						if (modalIsOpen(modal)) {
							if (typeof modal.close === 'function') {
								modal.close();
							} else {
								modal.removeAttribute('open');
							}
						}
						setDialogState();
					}

					function translatedKey(key) {
						return storageNamespace + ':' + String(key || '');
					}

					function readValue(key) {
						var raw = window.localStorage.getItem(translatedKey(key));
						if (raw === null) {
							return undefined;
						}
						try {
							return JSON.parse(raw);
						} catch (error) {
							return undefined;
						}
					}

					function writeValue(key, value) {
						if (typeof value === 'undefined') {
							window.localStorage.removeItem(translatedKey(key));
							return;
						}
						window.localStorage.setItem(translatedKey(key), JSON.stringify(value));
					}

					function removeValue(key) {
						window.localStorage.removeItem(translatedKey(key));
					}

					function setDialogState() {
						var dialogOpen = modalIsOpen(settingsRoot) || modalIsOpen(reminderRoot);
						document.body.classList.toggle('cmx-stopwatch-dialog-open', dialogOpen);
					}

					function copyText(text) {
						text = String(text || '');
						if (text === '') {
							return Promise.resolve(false);
						}
						if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function' && window.isSecureContext) {
							return navigator.clipboard.writeText(text).then(function () {
								return true;
							}).catch(function () {
								return fallbackCopy(text);
							});
						}
						return Promise.resolve(fallbackCopy(text));
					}

					function fallbackCopy(text) {
						var field = document.createElement('textarea');
						field.value = text;
						field.setAttribute('readonly', 'readonly');
						field.style.position = 'fixed';
						field.style.top = '-9999px';
						document.body.appendChild(field);
						field.focus();
						field.select();
						var copied = false;
						try {
							copied = document.execCommand('copy');
						} catch (error) {
							copied = false;
						}
						document.body.removeChild(field);
						return copied;
					}

							function setSettingsStatus(text, isError) {
								if (!settingsStatus) {
									return;
								}
								settingsStatus.textContent = text || '';
								settingsStatus.style.color = isError ? 'var(--wp-danger)' : '#646970';
							}

						function stopwatchAuthToken() {
							return String(CONFIG.proxyAuthToken || BOOTSTRAP.pageToken || BOOTSTRAP.token || '');
						}

						function stopwatchProxyUrl(action, instance, extra) {
							var url = new URL(String(CONFIG.proxyAjaxUrl || BOOTSTRAP.ajaxUrl || window.location.origin + '/wp-admin/admin-ajax.php'), window.location.origin);
							url.searchParams.set('action', String(action || ''));
							var proxyToken = stopwatchAuthToken();
							if (proxyToken !== '') {
								url.searchParams.set('proxy_token', proxyToken);
							}
							if (instance && instance.baseUrl) {
								url.searchParams.set('remote_base_url', String(instance.baseUrl));
							}
							if (instance && instance.ajaxUrl) {
								url.searchParams.set('remote_ajax_url', String(instance.ajaxUrl));
							}
							if (instance && instance.token) {
								url.searchParams.set('remote_token', String(instance.token));
							}
							Object.keys(extra || {}).forEach(function (key) {
								var value = extra[key];
								if (value === null || typeof value === 'undefined' || value === '') {
									return;
								}
								url.searchParams.set(key, String(value));
							});
							return url;
						}

						function appendProxyFields(formData, action, instance) {
							if (!(formData instanceof FormData)) {
								return formData;
							}
							if (!formData.has('action')) {
								formData.append('action', String(action || ''));
							}
							var proxyToken = stopwatchAuthToken();
							if (proxyToken !== '' && !formData.has('proxy_token')) {
								formData.append('proxy_token', proxyToken);
							}
							if (instance && instance.baseUrl && !formData.has('remote_base_url')) {
								formData.append('remote_base_url', String(instance.baseUrl));
							}
							if (instance && instance.ajaxUrl && !formData.has('remote_ajax_url')) {
								formData.append('remote_ajax_url', String(instance.ajaxUrl));
							}
							if (instance && instance.token && !formData.has('remote_token')) {
								formData.append('remote_token', String(instance.token));
							}
							return formData;
						}

						async function requestStopwatchJson(method, action, instance, options) {
							options = options && typeof options === 'object' ? options : {};
							var response;
							var fetchOptions = {
								method: String(method || 'GET').toUpperCase(),
								credentials: 'same-origin',
								cache: 'no-store',
								headers: {}
							};
							try {
								if (fetchOptions.method === 'POST') {
									var body = options.body instanceof FormData ? options.body : new FormData();
									if (!(options.body instanceof FormData)) {
										Object.keys(options.body || {}).forEach(function (key) {
											var value = options.body[key];
											if (value === null || typeof value === 'undefined') {
												return;
											}
											body.append(key, String(value));
										});
									}
									fetchOptions.body = appendProxyFields(body, action, instance);
									response = await fetch(String(CONFIG.proxyAjaxUrl || BOOTSTRAP.ajaxUrl || window.location.origin + '/wp-admin/admin-ajax.php'), fetchOptions);
								} else {
									response = await fetch(stopwatchProxyUrl(action, instance, options.query || {}).toString(), fetchOptions);
								}
							} catch (error) {
								throw new Error('Die Stopuhr kann die Instanz gerade nicht erreichen.');
							}

							var rawText = await response.text().catch(function () { return ''; });
							var json = null;
							if (rawText !== '') {
								try {
									json = JSON.parse(rawText);
								} catch (error) {
									json = null;
								}
							}
							if (!response.ok || !json || !json.success) {
								throw new Error(instanceResponseError(response, json, rawText));
							}
							return json.data || {};
						}

						function normalizeInstanceInput(raw) {
							var value = String(raw || '').trim();
							if (!value) {
								return { slug: '', baseUrl: '' };
							}
							if (/^https?:\/\//i.test(value)) {
								try {
									var parsed = new URL(value);
									var host = String(parsed.hostname || '').trim().toLowerCase();
									var slug = /\.misbuero\.ch$/i.test(host)
										? host.replace(/\.misbuero\.ch$/i, '')
										: host;
									return {
										slug: slug || host || '',
										baseUrl: parsed.origin
									};
								} catch (error) {
									return { slug: '', baseUrl: '' };
								}
							}
							var looksLikeHost = /[.:/]/.test(value);
							if (looksLikeHost) {
								try {
									var scheme = /\.local(?::\d+)?$/i.test(value) || /^localhost(?::\d+)?$/i.test(value) ? 'http://' : 'https://';
									var parsedHost = new URL(scheme + value.replace(/^\/+/, ''));
									var hostname = String(parsedHost.hostname || '').trim().toLowerCase();
									var nextSlug = /\.misbuero\.ch$/i.test(hostname)
										? hostname.replace(/\.misbuero\.ch$/i, '')
										: hostname;
									return {
										slug: nextSlug || hostname || '',
										baseUrl: parsedHost.origin
									};
								} catch (error) {
									return { slug: '', baseUrl: '' };
								}
							}
							var slug = value.replace(/^https?:\/\//i, '').replace(/\/.*$/, '').trim().toLowerCase();
							if (slug === '') {
								return { slug: '', baseUrl: '' };
							}
							return {
								slug: slug,
								baseUrl: 'https://' + slug + '.misbuero.ch'
							};
						}

						function openSettingsDialog() {
							if (!settingsRoot) {
								return;
							}
							var currentInstanceSelect = document.getElementById('instance-select');
							var preferredKey = currentInstanceSelect ? String(currentInstanceSelect.value || '') : '';
							refreshSettingsUi(preferredKey || settingsSelectedKey());
							openModal(settingsRoot);
						}

					function closeSettingsDialog() {
						if (!settingsRoot) {
							return;
						}
						closeModal(settingsRoot);
					}

					function closeReminderDialog() {
						if (reminderCountdownTimer) {
							window.clearInterval(reminderCountdownTimer);
							reminderCountdownTimer = 0;
						}
						reminderDeadlineMs = 0;
						if (reminderStatus) {
							reminderStatus.textContent = '';
						}
						closeModal(reminderRoot);
					}

					function clearReminderTimers() {
						if (reminderTimer) {
							window.clearTimeout(reminderTimer);
							reminderTimer = 0;
						}
						if (reminderResetTimer) {
							window.clearTimeout(reminderResetTimer);
							reminderResetTimer = 0;
						}
						if (reminderCountdownTimer) {
							window.clearInterval(reminderCountdownTimer);
							reminderCountdownTimer = 0;
						}
					}

					function decodeHtmlEntities(value) {
						var source = String(value || '');
						if (!source || !/[&<]/.test(source)) {
							return source;
						}
						var field = document.createElement('textarea');
						field.innerHTML = source;
						return field.value || source;
					}

					function normalizeText(value) {
						return decodeHtmlEntities(String(value || ''))
							.replace(/<[^>]*>/g, ' ')
							.replace(/\s+/g, ' ')
							.trim();
					}

					function normalizeEntity(entity, fallbackType) {
						if (!entity || typeof entity !== 'object') {
							return null;
						}
						var next = {};
						Object.keys(entity).forEach(function (key) {
							next[key] = entity[key];
						});
						var title = normalizeText(entity.title || entity.label || '');
						var label = normalizeText(entity.label || entity.title || '');
						next.title = title || label || '';
						next.label = label || title || '';
						if (!next.entity_type && fallbackType) {
							next.entity_type = fallbackType;
						}
						return next;
					}

					function normalizeEntityList(items, fallbackType) {
						return (Array.isArray(items) ? items : []).map(function (item) {
							return normalizeEntity(item, fallbackType);
						}).filter(function (item) {
							return !!item;
						});
					}

					function normalizeInstanceRecord(source) {
						source = source && typeof source === 'object' ? source : {};
						var next = {};
						Object.keys(source).forEach(function (key) {
							next[key] = source[key];
						});
						next.siteName = normalizeText(source.siteName || source.siteUrl || source.baseUrl || 'Mis Buero');
						next.userLogin = normalizeText(source.userLogin || '');
						next.userDisplay = normalizeText(source.userDisplay || '');
						next.projects = normalizeEntityList(source.projects, 'project');
						next.contacts = normalizeEntityList(source.contacts, 'contact');
						return next;
					}

					function normalizeSessionRecord(session) {
						if (!session || typeof session !== 'object') {
							return null;
						}
						var next = {};
						Object.keys(session).forEach(function (key) {
							next[key] = session[key];
						});
						next.instance = session.instance && typeof session.instance === 'object'
							? normalizeInstanceRecord(session.instance)
							: null;
						next.target = normalizeEntity(session.target, session.targetType === 'contact' ? 'contact' : 'project');
						next.project = normalizeEntity(session.project, 'project');
						next.contact = normalizeEntity(session.contact, 'contact');
						next.article = normalizeEntity(session.article);
						next.product = normalizeEntity(session.product);
						return next;
					}

						function buildInstance(source) {
							source = normalizeInstanceRecord(source);
							var siteUrl = String(source.siteUrl || source.baseUrl || '').replace(/\/+$/, '');
							var ajaxUrl = String(source.ajaxUrl || '').trim();
							var slug = String(source.slug || '').trim().toLowerCase();
							try {
								if (slug === '') {
									slug = new URL(siteUrl || window.location.origin).hostname.replace(/\.misbuero\.ch$/i, '');
								}
							} catch (error) {
								if (slug === '') {
									slug = String(siteUrl || window.location.hostname || 'misbuero');
								}
							}
						var intervals = Array.isArray(source.intervals) && source.intervals.length ? source.intervals : [5, 10, 15, 20, 30, 45, 60];
						intervals = intervals.map(function (value) {
							return Number(value);
						}).filter(function (value) {
							return Number.isFinite(value) && value > 0;
						});
							if (!intervals.length) {
								intervals = [5, 10, 15, 20, 30, 45, 60];
							}
							var stopwatchUrl = String(source.stopwatchUrl || '').trim();
							if (stopwatchUrl === '' && siteUrl !== '') {
								var route = String(CONFIG.stopwatchRoute || 'mis-stopuhr').replace(/^\/+|\/+$/g, '');
								var url = new URL(siteUrl + '/' + route + '/', window.location.origin);
								if (source.token) {
									url.searchParams.set('token', String(source.token));
								}
								stopwatchUrl = url.toString();
							}
							if (stopwatchUrl === '') {
								stopwatchUrl = String(BOOTSTRAP.stopwatchUrl || window.location.href);
							}
							return {
								slug: slug,
								baseUrl: siteUrl,
								siteName: normalizeText(source.siteName || siteUrl || 'Mis Buero'),
								token: String(source.token || ''),
							userLogin: normalizeText(source.userLogin || ''),
							userDisplay: normalizeText(source.userDisplay || ''),
							intervals: intervals,
							defaultInterval: Number(source.defaultInterval || intervals[0] || 5),
								ajaxUrl: ajaxUrl !== '' ? ajaxUrl : (siteUrl.replace(/\/+$/, '') + '/wp-admin/admin-ajax.php'),
								supports: source.supports && typeof source.supports === 'object' ? source.supports : {},
								projects: normalizeEntityList(source.projects, 'project'),
								contacts: normalizeEntityList(source.contacts, 'contact'),
								stopwatchUrl: stopwatchUrl,
								updatedAt: new Date().toISOString()
							};
						}

						function instanceKey(source) {
							return String(source && (source.slug || source.baseUrl) ? (source.slug || source.baseUrl) : '').trim();
						}

						function settingsSelectedKey() {
							return settingsSavedInstance ? String(settingsSavedInstance.value || '') : '';
						}

						function getInstances() {
							var stored = readValue(RAW_INSTANCE_KEY);
							return (Array.isArray(stored) ? stored : []).map(function (entry) {
								return buildInstance(entry);
							}).filter(function (entry) {
								return instanceKey(entry) !== '';
							});
						}

						function saveInstances(instances) {
							var byKey = {};
							(Array.isArray(instances) ? instances : []).forEach(function (entry) {
								var normalized = buildInstance(entry);
								var key = instanceKey(normalized);
								if (key === '') {
									return;
								}
								byKey[key] = normalized;
							});
							var next = Object.keys(byKey).map(function (key) {
								return byKey[key];
							}).sort(function (left, right) {
								return String(instanceKey(left)).localeCompare(String(instanceKey(right)), 'de');
							});
							writeValue(RAW_INSTANCE_KEY, next);
							return next;
						}

						function upsertInstance(source) {
							var nextInstance = buildInstance(source);
							var key = instanceKey(nextInstance);
							var instances = getInstances().filter(function (entry) {
								return instanceKey(entry) !== key;
							});
							instances.push(nextInstance);
							saveInstances(instances);
							return nextInstance;
						}

						function seedInstance(source) {
							return upsertInstance(source);
						}

						function findStoredInstance(selectedKey, instances) {
							var key = String(selectedKey || '');
							var list = Array.isArray(instances) ? instances : getInstances();
							return list.find(function (entry) {
								return instanceKey(entry) === key;
							}) || null;
						}

						function pickDefaultInterval(intervals, preferred) {
							var allowed = (Array.isArray(intervals) ? intervals : []).map(function (value) {
								return Number(value);
							}).filter(function (value) {
								return Number.isFinite(value) && value > 0;
							});
							if (!allowed.length) {
								allowed = [5, 10, 15, 20, 30, 45, 60];
							}
							var wanted = Number(preferred || 0);
							if (allowed.indexOf(wanted) !== -1) {
								return wanted;
							}
							return Number(allowed[0] || 5);
						}

						function renderSettingsSavedInstanceSelect(instances, selectedKey) {
							if (!settingsSavedInstance) {
								return;
							}
							var list = Array.isArray(instances) ? instances : [];
							if (!list.length) {
								settingsSavedInstance.innerHTML = '<option value="">Noch keine Instanz gespeichert</option>';
								settingsSavedInstance.disabled = true;
								if (settingsRemove) {
									settingsRemove.disabled = true;
								}
								if (settingsSave) {
									settingsSave.disabled = true;
								}
								return;
							}

							var activeKey = String(selectedKey || instanceKey(list[0]));
							settingsSavedInstance.disabled = false;
							if (settingsRemove) {
								settingsRemove.disabled = false;
							}
							if (settingsSave) {
								settingsSave.disabled = false;
							}
							settingsSavedInstance.innerHTML = list.map(function (entry) {
								var key = instanceKey(entry);
								return '<option value="' + key.replace(/[&<>"']/g, function (char) {
									return ({
										'&': '&amp;',
										'<': '&lt;',
										'>': '&gt;',
										'"': '&quot;',
										"'": '&#39;'
									}[char] || char);
								}) + '"' + (key === activeKey ? ' selected' : '') + '>' + key + '</option>';
							}).join('');
						}

						function renderSettingsIntervalOptions(instance) {
							if (!settingsDefaultInterval) {
								return;
							}
							var intervals = Array.isArray(instance && instance.intervals) && instance.intervals.length
								? instance.intervals
								: [5, 10, 15, 20, 30, 45, 60];
							var current = pickDefaultInterval(intervals, instance && instance.defaultInterval ? instance.defaultInterval : 5);
							settingsDefaultInterval.innerHTML = intervals.map(function (value) {
								var n = Number(value);
								return '<option value="' + String(n) + '"' + (n === current ? ' selected' : '') + '>' + String(n) + '</option>';
							}).join('');
						}

						function renderSettingsInstanceList(instances) {
							if (!settingsList) {
								return;
							}
							var list = Array.isArray(instances) ? instances : [];
							if (!list.length) {
								settingsList.classList.add('is-empty');
								settingsList.innerHTML = 'Noch keine Instanz gespeichert.';
								return;
							}

							settingsList.classList.remove('is-empty');
							settingsList.innerHTML = list.map(function (entry) {
								var projectCount = Array.isArray(entry.projects) ? entry.projects.length : 0;
								var contactCount = Array.isArray(entry.contacts) ? entry.contacts.length : 0;
								var intervals = Array.isArray(entry.intervals) && entry.intervals.length ? entry.intervals.join(', ') : '-';
								return '<div class="cmx-stopwatch-instance-card">'
									+ '<strong>' + String(instanceKey(entry)).replace(/[&<>"']/g, function (char) {
										return ({
											'&': '&amp;',
											'<': '&lt;',
											'>': '&gt;',
											'"': '&quot;',
											"'": '&#39;'
										}[char] || char);
									}) + '</strong>'
									+ '<div class="cmx-stopwatch-instance-meta">' + String(entry.siteName || entry.baseUrl || '-').replace(/[&<>"']/g, function (char) {
										return ({
											'&': '&amp;',
											'<': '&lt;',
											'>': '&gt;',
											'"': '&quot;',
											"'": '&#39;'
										}[char] || char);
									}) + '</div>'
									+ '<div class="cmx-stopwatch-instance-meta">Benutzer: ' + String(entry.userDisplay || entry.userLogin || '-').replace(/[&<>"']/g, function (char) {
										return ({
											'&': '&amp;',
											'<': '&lt;',
											'>': '&gt;',
											'"': '&quot;',
											"'": '&#39;'
										}[char] || char);
									}) + '</div>'
									+ '<div class="cmx-stopwatch-instance-meta">Intervall standardmässig: ' + String(entry.defaultInterval || '-') + ' Minuten</div>'
									+ '<div class="cmx-stopwatch-instance-meta">Mögliche Intervalle: ' + intervals + '</div>'
									+ '<div class="cmx-stopwatch-instance-meta">Aktive Projekte geladen: ' + String(projectCount) + '</div>'
									+ '<div class="cmx-stopwatch-instance-meta">Kontakte geladen: ' + String(contactCount) + '</div>'
									+ '</div>';
							}).join('');
						}

						function fillSettingsForm(instance) {
							renderSettingsIntervalOptions(instance);
							if (settingsInstanceInput) {
								settingsInstanceInput.value = '';
							}
							if (settingsUsernameInput) {
								settingsUsernameInput.value = instance ? String(instance.userLogin || '') : '';
							}
							if (settingsPasswordInput) {
								settingsPasswordInput.value = '';
							}
						}

						function refreshSettingsUi(selectedKey) {
							var instances = getInstances();
							var activeKey = String(selectedKey || settingsSelectedKey() || instanceKey(instances[0]));
							renderSettingsSavedInstanceSelect(instances, activeKey);
							renderSettingsInstanceList(instances);
							var selected = findStoredInstance(activeKey, instances) || instances[0] || null;
							fillSettingsForm(selected);
							return {
								instances: instances,
								selected: selected
							};
						}

						function dispatchInstancesChanged(selectedKey) {
							if (window.CMX_EXT_TIME_CONFIG && typeof window.CMX_EXT_TIME_CONFIG === 'object') {
								window.CMX_EXT_TIME_CONFIG.preferredInstanceKey = String(selectedKey || '');
							}
							window.dispatchEvent(new CustomEvent('cmx-ext-time-instances-changed', {
								detail: {
									selectedKey: String(selectedKey || '')
								}
							}));
						}

					function sessionTargetId(session) {
						if (!session || typeof session !== 'object') {
							return 0;
						}

						var candidates = [session.target, session.project, session.contact];
						for (var index = 0; index < candidates.length; index += 1) {
							var candidate = candidates[index];
							var candidateId = Number(candidate && candidate.id ? candidate.id : 0);
							if (candidateId > 0) {
								return candidateId;
							}
						}

						return 0;
					}

					function isStoredSessionValid(session) {
						if (!session || typeof session !== 'object') {
							return false;
						}
						if (String(session.source || '') !== SESSION_SOURCE) {
							return false;
						}
						if (Number(session.version || 0) !== SESSION_VERSION) {
							return false;
						}
						if (!session.instance || typeof session.instance !== 'object') {
							return false;
						}
						if (sessionTargetId(session) <= 0) {
							return false;
						}

						var startMs = Number(session.startMs || 0);
						return Number.isFinite(startMs) && startMs > 0;
					}

					function clearActiveSessionState() {
						clearReminderTimers();
						removeValue(RAW_ACTIVE_KEY);
						closeReminderDialog();
					}

						function purgeStopwatchStorage() {
							clearReminderTimers();
							var keysToRemove = [
								translatedKey(RAW_ACTIVE_KEY),
								RAW_ACTIVE_KEY
							];
						try {
							for (var index = 0; index < window.localStorage.length; index += 1) {
								var key = window.localStorage.key(index);
								if (!key) {
									continue;
								}
								if (key.indexOf('cmxStopwatch.') === 0) {
									keysToRemove.push(key);
								}
							}
						} catch (error) {}

						var uniqueKeys = [];
						keysToRemove.forEach(function (key) {
							key = String(key || '');
							if (key === '' || uniqueKeys.indexOf(key) !== -1) {
								return;
							}
							uniqueKeys.push(key);
						});

						uniqueKeys.forEach(function (key) {
							try {
								window.localStorage.removeItem(key);
							} catch (error) {}
						});
						closeReminderDialog();
					}

					function getActiveSession() {
						var session = normalizeSessionRecord(readValue(RAW_ACTIVE_KEY));
						if (!isStoredSessionValid(session)) {
							removeValue(RAW_ACTIVE_KEY);
							return null;
						}
						return session;
					}

					function storeActiveSession(session) {
						if (session) {
							writeValue(RAW_ACTIVE_KEY, normalizeSessionRecord(session));
						} else {
							removeValue(RAW_ACTIVE_KEY);
						}
					}

					function formatLocalDate(timestamp) {
						var date = new Date(Number(timestamp || Date.now()));
						return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
					}

					function formatLocalTime(timestamp) {
						var date = new Date(Number(timestamp || Date.now()));
						return String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
					}

					function intervalMs(session) {
						return Math.max(1, Number(session && session.intervalMinutes ? session.intervalMinutes : 5)) * 60 * 1000;
					}

					function buildAjaxUrl(instance, action) {
						var base = instance && instance.ajaxUrl ? String(instance.ajaxUrl) : String((instance && instance.baseUrl) || '').replace(/\/+$/, '') + '/wp-admin/admin-ajax.php';
						var url = new URL(base, window.location.origin);
						url.searchParams.set('action', action);
						return url.toString();
					}

					function instanceResponseError(response, json, rawText) {
						var serverMessage = json && json.data && typeof json.data.message === 'string'
							? json.data.message.trim()
							: '';
						if (serverMessage) {
							return serverMessage;
						}

						var body = String(rawText || '').trim();
						if (body === '0') {
							return 'Die Stopuhr-Schnittstelle ist auf dieser Instanz noch nicht verfuegbar.';
						}
						if (/<!doctype html|<html[\s>]/i.test(body)) {
							return 'Die Instanz liefert HTML statt JSON. Bitte Anmeldung oder Link pruefen.';
						}
						if (response.status === 401 || response.status === 403) {
							return 'Die Instanz hat die Anfrage abgewiesen.';
						}
						if (response.status === 404) {
							return 'Die Instanz wurde nicht gefunden.';
						}
						if (!response.ok) {
							return 'Die Instanz hat mit HTTP ' + response.status + ' geantwortet.';
						}

						return 'Die Instanz antwortet nicht mit gueltigen Daten.';
					}

						async function fetchBootstrapData(instance) {
							var next;
							if (CONFIG.proxyBootstrapAction) {
								next = normalizeInstanceRecord(await requestStopwatchJson('GET', String(CONFIG.proxyBootstrapAction), instance));
							} else {
								var action = String(CONFIG.bootstrapAction || 'cmx_ext_time_bootstrap');
								var url = new URL(buildAjaxUrl(instance, action));
								if (instance && instance.token) {
									url.searchParams.set('token', instance.token);
								}

								var response;
								try {
									response = await fetch(url.toString(), {
										method: 'GET',
										credentials: 'same-origin',
										cache: 'no-store',
										headers: instance && instance.token ? { 'X-CMX-Extension-Token': instance.token } : {}
									});
								} catch (error) {
									throw new Error('Die Stopuhr kann die Instanz gerade nicht erreichen.');
								}

								var rawText = await response.text().catch(function () { return ''; });
								var json = null;
								if (rawText !== '') {
									try {
										json = JSON.parse(rawText);
									} catch (error) {
										json = null;
									}
								}

								if (!response.ok || !json || !json.success) {
									throw new Error(instanceResponseError(response, json, rawText));
								}
								next = normalizeInstanceRecord(json.data || {});
							}
							next.userLogin = normalizeText(next.userLogin || (instance && instance.userLogin) || '');
							next.userDisplay = normalizeText(next.userDisplay || (instance && instance.userDisplay) || next.userLogin || '');
							next.storageNamespace = storageNamespace;
							next.stopwatchUrl = String(next.stopwatchUrl || (instance && instance.stopwatchUrl) || '');
							return next;
						}

						async function connectInstanceData(instance, credentials) {
							credentials = credentials && typeof credentials === 'object' ? credentials : {};
							if (!CONFIG.proxyConnectAction) {
								throw new Error('Die Instanz-Verbindung ist auf dieser Seite nicht verfügbar.');
							}
							var username = String(credentials.username || '').trim();
							var password = String(credentials.password || '').trim();
							var next = normalizeInstanceRecord(await requestStopwatchJson('POST', String(CONFIG.proxyConnectAction), instance, {
								body: {
									username: username,
									password: password
								}
							}));
							next.userLogin = normalizeText(username);
							next.userDisplay = normalizeText(next.userDisplay || username);
							next.stopwatchUrl = String(next.stopwatchUrl || '');
							return next;
						}

					function normalizeSessionTarget(source) {
						source = source && typeof source === 'object' ? source : {};
						var project = source.project && Number(source.project.id || 0) ? normalizeEntity(source.project, 'project') : null;
						var contact = source.contact && Number(source.contact.id || 0) ? normalizeEntity(source.contact, 'contact') : null;
						var targetType = source.targetType === 'contact' ? 'contact' : 'project';
						var target = source.target && Number(source.target.id || 0)
							? normalizeEntity(source.target, targetType === 'contact' ? 'contact' : 'project')
							: null;
						var entityType = target && typeof target.entity_type === 'string'
							? target.entity_type.toLowerCase()
							: '';

						if (targetType === 'contact' && contact) {
							target = contact;
						} else if (targetType === 'project' && project) {
							target = project;
						} else if (entityType === 'contact') {
							targetType = 'contact';
						} else if (entityType === 'project') {
							targetType = 'project';
						} else if (!target && contact && !project) {
							targetType = 'contact';
							target = contact;
						} else if (!target && project && !contact) {
							targetType = 'project';
							target = project;
						}

						if (!target) {
							target = targetType === 'contact' ? contact : project;
						}
						if (!target && contact) {
							targetType = 'contact';
							target = contact;
						} else if (!target && project) {
							targetType = 'project';
							target = project;
						}

						return {
							targetType: targetType,
							target: target,
							project: targetType === 'project' ? target : null,
							contact: targetType === 'contact' ? target : null
						};
					}

						async function persistSession(session, reason) {
							var normalizedTarget = normalizeSessionTarget(session || {});
							var targetType = normalizedTarget.targetType;
							var target = normalizedTarget.target;
							if (!session || !session.instance || !target || !target.id) {
							throw new Error(targetType === 'contact' ? 'Kontakt oder Instanz fehlen.' : 'Projekt oder Instanz fehlen.');
						}

						var formData = new FormData();
						formData.append('action', String(CONFIG.saveAction || 'cmx_ext_time_save'));
						if (session.instance.token) {
							formData.append('token', session.instance.token);
						}
							formData.append('target_type', targetType);
							formData.append('target_id', String(target.id || 0));
							formData.append('target_label', String(target.label || target.title || ''));
							formData.append('target_title', String(target.title || target.label || ''));
							if (targetType === 'project') {
								formData.append('project_id', String(target.id || 0));
							} else {
							formData.append('contact_id', String(target.id || 0));
						}
						formData.append('mode', String(session.mode || 'task'));
						formData.append('artikel_id', String((session.article && session.article.id) || 0));
						formData.append('produkt_id', String((session.product && session.product.id) || 0));
						formData.append('verrechenbar', session.verrechenbar ? '1' : '0');
						formData.append('info', String(session.info || ''));
						formData.append('betreff', String(session.betreff || ''));
						formData.append('artikel_label', (session.article && (session.article.label || session.article.title)) || '');
						formData.append('produkt_label', (session.product && (session.product.label || session.product.title)) || '');
							formData.append('start_at', new Date(Number(session.startMs || Date.now())).toISOString());
							formData.append('end_at', new Date().toISOString());
							formData.append('start_date', String(session.startDate || formatLocalDate(session.startMs)));
							formData.append('start_time', String(session.startTime || formatLocalTime(session.startMs)));
							formData.append('reason', String(reason || 'manual'));

							if (CONFIG.proxySaveAction) {
								return requestStopwatchJson('POST', String(CONFIG.proxySaveAction), session.instance, {
									body: formData
								});
							}

							var response;
							try {
								response = await fetch(buildAjaxUrl(session.instance, String(CONFIG.saveAction || 'cmx_ext_time_save')), {
									method: 'POST',
									credentials: 'same-origin',
									cache: 'no-store',
									headers: session.instance.token ? { 'X-CMX-Extension-Token': session.instance.token } : {},
									body: formData
								});
							} catch (error) {
								throw new Error('Die Zeit konnte nicht gespeichert werden.');
							}

							var rawText = await response.text().catch(function () { return ''; });
							var json = null;
							if (rawText !== '') {
								try {
									json = JSON.parse(rawText);
								} catch (error) {
									json = null;
								}
							}

							if (!response.ok || !json || !json.success) {
								throw new Error(instanceResponseError(response, json, rawText));
							}

							return json.data || {};
						}

					function scheduleReminder(session) {
						clearReminderTimers();
						closeReminderDialog();
						if (!REMINDER_ENABLED) {
							if (isStoredSessionValid(session)) {
								session.reminderDueAt = 0;
								storeActiveSession(session);
							}
							return;
						}
						if (!isStoredSessionValid(session)) {
							clearActiveSessionState();
							return;
						}

						var dueAt = Number(session.reminderDueAt || 0);
						if (dueAt <= 0) {
							session.reminderDueAt = Date.now() + intervalMs(session);
							storeActiveSession(session);
							dueAt = session.reminderDueAt;
						}

						var delay = dueAt - Date.now();
						if (delay <= 0) {
							showReminder();
							return;
						}

						reminderTimer = window.setTimeout(showReminder, delay);
					}

					function updateReminderCountdown() {
						if (reminderDeadlineMs <= 0) {
							return;
						}
						var seconds = Math.max(0, Math.ceil((reminderDeadlineMs - Date.now()) / 1000));
						if (reminderCountdown) {
							reminderCountdown.textContent = String(seconds);
						}
						if (reminderSeconds) {
							reminderSeconds.textContent = String(seconds);
						}
						if (seconds <= 0) {
							handleReminderStop('timeout');
						}
					}

					function showReminder() {
						if (!REMINDER_ENABLED) {
							closeReminderDialog();
							return;
						}
						var session = getActiveSession();
						if (!session || !reminderRoot) {
							clearActiveSessionState();
							return;
						}

						var target = session.target || session.project || session.contact || null;
						if (reminderProject) {
							reminderProject.textContent = target && (target.title || target.label)
								? String(target.title || target.label)
								: (session.targetType === 'contact' ? 'diesem Kontakt' : 'diesem Projekt');
						}
						if (reminderStatus) {
							reminderStatus.textContent = '';
						}
						reminderDeadlineMs = Date.now() + 20000;
						openModal(reminderRoot);
						updateReminderCountdown();
						reminderCountdownTimer = window.setInterval(updateReminderCountdown, 250);
					}

					async function handleReminderContinue() {
						if (reminderResetTimer) {
							window.clearTimeout(reminderResetTimer);
							reminderResetTimer = 0;
						}
						var session = getActiveSession();
						if (!session) {
							clearActiveSessionState();
							return;
						}
						session.reminderDueAt = Date.now() + intervalMs(session);
						storeActiveSession(session);
						closeReminderDialog();
						scheduleReminder(session);
					}

					async function handleReminderStop(reason) {
						if (reminderResetTimer) {
							window.clearTimeout(reminderResetTimer);
							reminderResetTimer = 0;
						}
						if (!getActiveSession()) {
							clearActiveSessionState();
							window.location.reload();
							return;
						}
						if (reminderStatus) {
							reminderStatus.textContent = 'Zeit wird gespeichert...';
						}
						var result = await stopSession(reason || 'declined');
						if (!result || !result.success) {
							if (result && result.error === 'Keine aktive Erfassung gefunden.') {
								clearActiveSessionState();
								window.location.reload();
								return;
							}
							if (reminderStatus) {
								reminderStatus.textContent = result && result.error ? result.error : 'Die Zeit konnte nicht gespeichert werden.';
							}
							reminderResetTimer = window.setTimeout(function () {
								reminderResetTimer = 0;
								handleReminderAbort(cleanedStopwatchUrl());
							}, 5000);
							return;
						}
						window.location.reload();
					}

					function handleReminderAbort(fallbackUrl) {
						purgeStopwatchStorage();
						fallbackUrl = String(fallbackUrl || '');
						if (fallbackUrl !== '') {
							window.location.replace(fallbackUrl);
							return;
						}
						window.location.reload();
					}

						function refreshActiveSessionInstance(instance) {
							var session = getActiveSession();
							if (!session) {
								clearReminderTimers();
								closeReminderDialog();
								return;
							}
							var nextInstanceKey = instanceKey(instance);
							var activeInstanceKey = String(session.instanceKey || '');
							if (nextInstanceKey !== '' && activeInstanceKey !== '' && activeInstanceKey !== nextInstanceKey) {
								return;
							}
							session.instance = instance;
							session.instanceKey = nextInstanceKey;
							storeActiveSession(session);
							scheduleReminder(session);
						}

						function cleanedStopwatchUrl() {
							var url = new URL(String(BOOTSTRAP.stopwatchUrl || window.location.href), window.location.origin);
							url.searchParams.delete('cmx_stopwatch_action');
							return url.toString();
						}

					function replaceActionUrl() {
						if (!window.history || typeof window.history.replaceState !== 'function') {
							return;
						}
						window.history.replaceState(null, '', cleanedStopwatchUrl());
					}

						function startSession(payload) {
							var normalizedTarget = normalizeSessionTarget(payload || {});
						if (!payload || !payload.instance || !normalizedTarget.target || !normalizedTarget.target.id) {
							return Promise.reject(new Error(normalizedTarget.targetType === 'contact' ? 'Kontakt oder Instanz fehlen.' : 'Projekt oder Instanz fehlen.'));
						}

						var startMs = Date.now();
						var session = {
							source: SESSION_SOURCE,
							version: SESSION_VERSION,
							instanceKey: payload.instance.slug || payload.instance.baseUrl,
							instance: payload.instance,
							targetType: normalizedTarget.targetType,
							target: normalizedTarget.target,
							project: normalizedTarget.project,
							contact: normalizedTarget.contact,
							article: payload.article || null,
							product: payload.product || null,
								mode: payload.mode || 'task',
								info: payload.info || '',
								betreff: payload.betreff || '',
								verrechenbar: !!payload.verrechenbar,
								intervalMinutes: Number(payload.intervalMinutes || payload.instance.defaultInterval || 5),
								startMs: startMs,
								startDate: formatLocalDate(startMs),
								startTime: formatLocalTime(startMs)
						};
						session.reminderDueAt = startMs + intervalMs(session);
						storeActiveSession(session);
						scheduleReminder(session);

						return Promise.resolve(session);
					}

						function updateSession(payload) {
							var session = getActiveSession();
							if (!session) {
								return Promise.resolve({ success: false, error: 'Keine aktive Erfassung gefunden.' });
							}

							var nextSession = {};
							var shouldRescheduleReminder = false;
							Object.keys(session).forEach(function (key) {
								nextSession[key] = session[key];
							});

						if (payload && Object.prototype.hasOwnProperty.call(payload, 'info')) {
							nextSession.info = typeof payload.info === 'string' ? payload.info : '';
						}
						if (payload && Object.prototype.hasOwnProperty.call(payload, 'betreff')) {
							nextSession.betreff = typeof payload.betreff === 'string' ? payload.betreff : '';
						}
						if (payload && Object.prototype.hasOwnProperty.call(payload, 'intervalMinutes')) {
							var nextInterval = Number(payload.intervalMinutes || 0);
							if (Number.isFinite(nextInterval) && nextInterval > 0) {
								nextSession.intervalMinutes = nextInterval;
								if (nextSession.instance && typeof nextSession.instance === 'object') {
									nextSession.instance.defaultInterval = nextInterval;
								}
								nextSession.reminderDueAt = 0;
								shouldRescheduleReminder = true;
							}
						}
						if (payload && (
							Object.prototype.hasOwnProperty.call(payload, 'targetType')
							|| Object.prototype.hasOwnProperty.call(payload, 'target')
							|| Object.prototype.hasOwnProperty.call(payload, 'project')
							|| Object.prototype.hasOwnProperty.call(payload, 'contact')
						)) {
							var normalizedTarget = normalizeSessionTarget({
								targetType: Object.prototype.hasOwnProperty.call(payload, 'targetType') ? payload.targetType : nextSession.targetType,
								target: Object.prototype.hasOwnProperty.call(payload, 'target') ? payload.target : nextSession.target,
								project: Object.prototype.hasOwnProperty.call(payload, 'project') ? payload.project : nextSession.project,
								contact: Object.prototype.hasOwnProperty.call(payload, 'contact') ? payload.contact : nextSession.contact
							});
							nextSession.targetType = normalizedTarget.targetType;
							nextSession.target = normalizedTarget.target;
							nextSession.project = normalizedTarget.project;
							nextSession.contact = normalizedTarget.contact;
						}

						storeActiveSession(nextSession);
						if (shouldRescheduleReminder) {
							scheduleReminder(nextSession);
						}
						return Promise.resolve({ success: true, session: nextSession });
						}

					async function stopSession(reason) {
						var session = getActiveSession();
						if (!session) {
							return { success: false, error: 'Keine aktive Erfassung gefunden.' };
						}

						clearReminderTimers();
						closeReminderDialog();

						try {
							var result = await persistSession(session, reason || 'manual');
							removeValue(RAW_ACTIVE_KEY);
							return { success: true, result: result };
						} catch (error) {
							session.reminderDueAt = Date.now() + intervalMs(session);
							storeActiveSession(session);
							scheduleReminder(session);
							return {
								success: false,
								error: error && error.message ? error.message : String(error)
							};
						}
					}

					var chromeShim = window.chrome && typeof window.chrome === 'object' ? window.chrome : {};
					chromeShim.storage = chromeShim.storage || {};
					chromeShim.storage.local = {
						get: function (key) {
							if (Array.isArray(key)) {
								var arrayResult = {};
								key.forEach(function (entry) {
									arrayResult[entry] = readValue(entry);
								});
								return Promise.resolve(arrayResult);
							}
							if (key && typeof key === 'object') {
								var objectResult = {};
								Object.keys(key).forEach(function (entry) {
									var stored = readValue(entry);
									objectResult[entry] = typeof stored === 'undefined' ? key[entry] : stored;
								});
								return Promise.resolve(objectResult);
							}
							return Promise.resolve({ [key]: readValue(key) });
						},
						set: function (payload) {
							Object.keys(payload || {}).forEach(function (key) {
								writeValue(key, payload[key]);
							});
							return Promise.resolve();
						},
						remove: function (keys) {
							(Array.isArray(keys) ? keys : [keys]).forEach(function (key) {
								removeValue(key);
							});
							return Promise.resolve();
						}
					};
					chromeShim.runtime = chromeShim.runtime || {};
					chromeShim.runtime.openOptionsPage = function () {
						openSettingsDialog();
					};
					chromeShim.runtime.sendMessage = function (message) {
						if (!message || typeof message !== 'object') {
							return Promise.resolve(null);
						}

						if (message.type === 'cmx-ext-time-get-active-session') {
							return Promise.resolve(getActiveSession());
						}
						if (message.type === 'cmx-ext-time-start-session') {
							return startSession(message.payload || {}).then(function (session) {
								return { success: true, session: session };
							}).catch(function (error) {
								return { success: false, error: error && error.message ? error.message : String(error) };
							});
						}
						if (message.type === 'cmx-ext-time-update-session') {
							return updateSession(message.payload || {});
						}
						if (message.type === 'cmx-ext-time-stop-session') {
							return stopSession('manual');
						}
						if (message.type === 'cmx-ext-time-reset-session') {
							clearActiveSessionState();
							return Promise.resolve({ success: true });
						}

						return Promise.resolve(null);
					};
						window.chrome = chromeShim;

						var instance = seedInstance(BOOTSTRAP);
						if (window.CMX_EXT_TIME_CONFIG && typeof window.CMX_EXT_TIME_CONFIG === 'object') {
							window.CMX_EXT_TIME_CONFIG.preferredInstanceKey = instanceKey(instance);
						}
						refreshActiveSessionInstance(instance);
						refreshSettingsUi(instanceKey(instance));

						if (settingsSavedInstance) {
							settingsSavedInstance.addEventListener('change', function () {
								refreshSettingsUi(settingsSelectedKey());
								setSettingsStatus('', false);
							});
						}

						if (settingsInstanceInput) {
							settingsInstanceInput.addEventListener('keydown', function (event) {
								if (event.key !== 'Enter') {
									return;
								}
								event.preventDefault();
								if (settingsConnect) {
									settingsConnect.click();
								}
							});
						}

						if (settingsPasswordInput) {
							settingsPasswordInput.addEventListener('keydown', function (event) {
								if (event.key !== 'Enter') {
									return;
								}
								event.preventDefault();
								if (settingsConnect) {
									settingsConnect.click();
								}
							});
						}

						if (settingsPasswordToggle && settingsPasswordInput) {
							settingsPasswordToggle.addEventListener('click', function () {
								var isVisible = settingsPasswordInput.getAttribute('type') === 'text';
								settingsPasswordInput.setAttribute('type', isVisible ? 'password' : 'text');
								settingsPasswordToggle.setAttribute('aria-pressed', isVisible ? 'false' : 'true');
								settingsPasswordToggle.setAttribute('aria-label', isVisible ? 'Passwort anzeigen' : 'Passwort ausblenden');
								settingsPasswordToggle.setAttribute('title', isVisible ? 'Passwort anzeigen' : 'Passwort ausblenden');
							});
						}

						if (settingsConnect) {
							settingsConnect.addEventListener('click', async function () {
								var normalized = normalizeInstanceInput(settingsInstanceInput ? settingsInstanceInput.value : '');
								var selectedStoredInstance = findStoredInstance(settingsSelectedKey());
								if ((!normalized.slug || !normalized.baseUrl) && selectedStoredInstance) {
									normalized = {
										slug: selectedStoredInstance.slug || '',
										baseUrl: selectedStoredInstance.baseUrl || '',
										token: selectedStoredInstance.token || '',
										ajaxUrl: selectedStoredInstance.ajaxUrl || ''
									};
								}
								if (!normalized.slug || !normalized.baseUrl) {
									setSettingsStatus('Eine gültige Instanz eingeben.', true);
									return;
								}
								var storedInstance = findStoredInstance(normalized.slug || normalized.baseUrl);

								var username = settingsUsernameInput ? String(settingsUsernameInput.value || '').trim() : '';
								var password = settingsPasswordInput ? String(settingsPasswordInput.value || '').trim() : '';
								if (username === '' || password === '') {
									setSettingsStatus('Bitte Benutzername und Passwort eingeben.', true);
									return;
								}

								setSettingsStatus('Instanz wird geladen...', false);
								try {
									var bootstrapData = await connectInstanceData(normalized, {
										username: username,
										password: password
									});
									var intervals = Array.isArray(bootstrapData.intervals) && bootstrapData.intervals.length ? bootstrapData.intervals : [5, 10, 15, 20, 30, 45, 60];
									var merged = upsertInstance({
										slug: normalized.slug,
										baseUrl: normalized.baseUrl,
										siteName: bootstrapData.siteName || normalized.baseUrl,
										token: bootstrapData.token || (storedInstance && storedInstance.token) || '',
										userLogin: username,
										userDisplay: bootstrapData.userDisplay || username,
										intervals: intervals,
										defaultInterval: pickDefaultInterval(intervals, settingsDefaultInterval ? settingsDefaultInterval.value : (bootstrapData.defaultInterval || 5)),
										ajaxUrl: bootstrapData.ajaxUrl || (storedInstance && storedInstance.ajaxUrl) || (normalized.baseUrl.replace(/\/+$/, '') + '/wp-admin/admin-ajax.php'),
										supports: bootstrapData.supports && typeof bootstrapData.supports === 'object' ? bootstrapData.supports : {},
										projects: Array.isArray(bootstrapData.projects) ? bootstrapData.projects : [],
										contacts: Array.isArray(bootstrapData.contacts) ? bootstrapData.contacts : [],
										stopwatchUrl: bootstrapData.stopwatchUrl || ''
									});
									refreshSettingsUi(instanceKey(merged));
									dispatchInstancesChanged(instanceKey(merged));
									setSettingsStatus('Instanz wurde erfolgreich geladen und gespeichert. ' + String((merged.projects || []).length) + ' Projekte und ' + String((merged.contacts || []).length) + ' Kontakte wurden übernommen.', false);
								} catch (error) {
									setSettingsStatus(error && error.message ? error.message : 'Die Instanz konnte nicht geladen werden.', true);
								}
							});
						}

						if (settingsSave) {
							settingsSave.addEventListener('click', function () {
								var selected = findStoredInstance(settingsSelectedKey());
								if (!selected) {
									setSettingsStatus('Erst eine Instanz auswählen.', true);
									return;
								}
								var value = Number(settingsDefaultInterval ? settingsDefaultInterval.value : 0);
								if (!Number.isFinite(value) || value <= 0) {
									setSettingsStatus('Bitte ein Intervall auswählen.', true);
									return;
								}
								selected.defaultInterval = pickDefaultInterval(selected.intervals, value);
								var merged = upsertInstance(selected);
								refreshSettingsUi(instanceKey(merged));
								dispatchInstancesChanged(instanceKey(merged));
								setSettingsStatus('Standard-Intervall wurde gespeichert.', false);
							});
						}

						if (settingsRemove) {
							settingsRemove.addEventListener('click', function () {
								var selected = findStoredInstance(settingsSelectedKey());
								if (!selected) {
									setSettingsStatus('Keine Instanz ausgewählt.', true);
									return;
								}
								var activeSession = getActiveSession();
								if (activeSession && instanceKey(selected) === String(activeSession.instanceKey || '')) {
									setSettingsStatus('Diese Instanz hat gerade eine laufende Erfassung.', true);
									return;
								}
								var nextInstances = saveInstances(getInstances().filter(function (entry) {
									return instanceKey(entry) !== instanceKey(selected);
								}));
								var nextSelectedKey = instanceKey(nextInstances[0] || '');
								refreshSettingsUi(nextSelectedKey);
								dispatchInstancesChanged(nextSelectedKey);
								setSettingsStatus('Instanz wurde entfernt.', false);
							});
						}

						Array.prototype.slice.call(document.querySelectorAll('[data-stopwatch-close="settings"]')).forEach(function (button) {
							button.addEventListener('click', closeSettingsDialog);
						});

					if (settingsRoot) {
						settingsRoot.addEventListener('close', setDialogState);
						settingsRoot.addEventListener('cancel', function (event) {
							event.preventDefault();
							closeSettingsDialog();
						});
						settingsRoot.addEventListener('click', function (event) {
							if (event.target === settingsRoot) {
								closeSettingsDialog();
							}
						});
					}

					if (reminderRoot) {
						reminderRoot.addEventListener('close', setDialogState);
						reminderRoot.addEventListener('cancel', function (event) {
							event.preventDefault();
							handleReminderAbort(cleanedStopwatchUrl());
						});
						reminderRoot.addEventListener('click', function (event) {
							if (event.target === reminderRoot) {
								handleReminderAbort(cleanedStopwatchUrl());
							}
						});
					}

					if (reminderContinue) {
						reminderContinue.addEventListener('click', function () {
							handleReminderContinue();
						});
					}

					if (reminderStop) {
						reminderStop.addEventListener('click', function () {
							handleReminderStop('declined');
						});
					}

					if (reminderAbort) {
						reminderAbort.addEventListener('click', function () {
							handleReminderAbort(cleanedStopwatchUrl());
						});
					}

					if (reminderClose) {
						reminderClose.addEventListener('click', function () {
							handleReminderAbort(cleanedStopwatchUrl());
						});
					}

					(async function () {
						var currentUrl = new URL(window.location.href);
						var action = String(currentUrl.searchParams.get('cmx_stopwatch_action') || '').toLowerCase();

						if (action === 'abort') {
							purgeStopwatchStorage();
							replaceActionUrl();
							return;
						}

						if (action === 'continue') {
							var continueSession = getActiveSession();
							if (continueSession) {
								continueSession.reminderDueAt = Date.now() + intervalMs(continueSession);
								storeActiveSession(continueSession);
							}
							replaceActionUrl();
						} else if (action === 'stop') {
							var stopResult = await stopSession('declined');
							replaceActionUrl();
							if (!stopResult || !stopResult.success) {
								if (reminderStatus) {
									reminderStatus.textContent = stopResult && stopResult.error ? stopResult.error : 'Die Zeit konnte nicht gespeichert werden.';
								}
							}
							return;
						}

						var activeSession = getActiveSession();
						if (activeSession) {
							scheduleReminder(activeSession);
						}
					})();
				})();
			</script>
			<script><?php echo cmx_ext_time_popup_js(); ?></script>
		</body>
		</html>
		<?php
	}
}
