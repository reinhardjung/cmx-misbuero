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
		$bootstrap['userId'] = $user_id;
		$bootstrap['userLogin'] = $user_login;
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
							if (key === 'cmxExtTime.activeSession' || key === 'cmxExtTime.instances' || key.indexOf('cmxStopwatch.') === 0) {
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
		$icon_src = (string) (\function_exists(__NAMESPACE__ . '\\cmx_ext_time_preview_icon_src')
			? cmx_ext_time_preview_icon_src()
			: '');
		$site_name = (string) ($bootstrap['siteName'] ?? \get_bloginfo('name'));
		$site_name_display = $site_name;
		if (\strpos($site_name, '-') !== false) {
			$site_name_parts = \preg_split('/\s*-\s*/u', $site_name);
			$site_name_parts = \array_values(\array_filter(\array_map('trim', (array) $site_name_parts), 'strlen'));
			if (!empty($site_name_parts)) {
				$site_name_display = (string) \end($site_name_parts);
			}
		}
		$user_display = (string) ($bootstrap['userDisplay'] ?? '');
		$stopwatch_url = (string) ($bootstrap['stopwatchUrl'] ?? cmx_ext_time_stopwatch_url((int) ($bootstrap['userId'] ?? 0)));
		?>
		<!doctype html>
		<html lang="de">
		<head>
			<meta charset="utf-8">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title>Mis Büro - Stopuhr</title>
			<style>
				:root{
					--cmx-bg-1:#f6efe6;
					--cmx-bg-2:#eadbc8;
					--cmx-panel:#fffaf4;
					--cmx-panel-strong:#ffffff;
					--cmx-text:#241b16;
					--cmx-muted:#6e6257;
					--cmx-border:#dccfc1;
					--cmx-accent:#a42c24;
					--cmx-accent-dark:#7d201b;
					--cmx-accent-soft:#f4e2dc;
					--cmx-success:#147d3f;
					--cmx-success-soft:#edf9f0;
					--cmx-info:#1d4f91;
					--cmx-info-soft:#edf4ff;
					--cmx-error:#b32d2e;
					--cmx-error-soft:#fff1f1;
					--cmx-shadow:0 22px 64px rgba(64, 31, 12, .12);
				}
				*,
				*::before,
				*::after{box-sizing:border-box}
				html,body{min-height:100%}
				body{
					margin:0;
					font:14px/1.5 "Avenir Next","Segoe UI","Helvetica Neue",sans-serif;
					color:var(--cmx-text);
					background:#fff;
					padding:28px 16px;
				}
				body.cmx-stopwatch-dialog-open{overflow:hidden}
				.page{
					max-width:640px;
					margin:0 auto;
				}
				.hero{
					display:flex;
					align-items:flex-end;
					justify-content:space-between;
					gap:20px;
					margin-bottom:18px;
				}
				.hero-copy{
					max-width:460px;
				}
				.hero-kicker{
					display:inline-flex;
					align-items:center;
					gap:8px;
					padding:6px 12px;
					border-radius:999px;
					background:rgba(255,255,255,.5);
					color:var(--cmx-accent);
					font-size:12px;
					font-weight:700;
					letter-spacing:.08em;
					text-transform:uppercase;
				}
				.hero h1{
					margin:12px 0 6px;
					font-size:34px;
					line-height:1.05;
					letter-spacing:-.03em;
				}
				.hero p{
					margin:0;
					color:var(--cmx-muted);
				}
				.hero-meta{
					display:flex;
					flex-direction:column;
					align-items:flex-end;
					gap:8px;
					text-align:right;
				}
				.hero-meta strong{display:block;font-size:15px}
				.hero-meta span{color:var(--cmx-muted);font-size:13px}
				.wrap{
					padding:18px;
					border-radius:28px;
					background:transparent;
					border:0;
					box-shadow:none;
					backdrop-filter:none;
				}
				.head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}
				.brand{display:flex;align-items:center;gap:12px}
				.icon{
					width:74px;
					height:74px;
					border-radius:22px;
					background:linear-gradient(160deg,rgba(255,255,255,.96),rgba(244,226,220,.9));
					border:1px solid var(--cmx-border);
					display:flex;
					align-items:center;
					justify-content:center;
					box-shadow:0 10px 24px rgba(125,32,27,.12);
				}
				.icon img{display:block;width:58px;height:58px;object-fit:contain}
				.title{font-weight:800;font-size:18px;letter-spacing:-.02em}
				.subtitle{color:var(--cmx-muted)}
				.gear{
					appearance:none;
					border:1px solid var(--cmx-border);
					background:var(--cmx-panel-strong);
					color:var(--cmx-text);
					border-radius:16px;
					width:48px;
					height:48px;
					padding:0;
					cursor:pointer;
					display:flex;
					align-items:center;
					justify-content:center;
					line-height:1;
					box-shadow:0 10px 24px rgba(36,27,22,.08);
				}
				.gear:hover,
				.gear:focus{border-color:var(--cmx-accent);color:var(--cmx-accent)}
				.gear svg{display:block;width:24px;height:24px}
				.panel{
					background:var(--cmx-panel);
					border:1px solid var(--cmx-border);
					border-radius:22px;
					padding:16px;
				}
				.stack{display:flex;flex-direction:column;gap:12px}
				label{display:flex;flex-direction:column;gap:5px;font-weight:700}
				input[type=text],select,textarea{
					width:100%;
					box-sizing:border-box;
					border:1px solid var(--cmx-border);
					border-radius:14px;
					padding:10px 12px;
					background:var(--cmx-panel-strong);
					font:inherit;
					color:inherit;
				}
				textarea{min-height:90px;resize:vertical}
				.mode-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:end}
				.mode-field{display:flex;flex-direction:column;gap:5px;min-width:0}
				.field-caption{font-weight:700}
				.note-subject{display:flex;flex-direction:column;justify-content:flex-end;min-width:138px;align-items:flex-end}
				.note-subject select{min-width:138px;width:auto;max-width:170px}
				.note-subject.is-hidden{visibility:hidden;pointer-events:none}
				.inline{display:flex;align-items:center;gap:8px}
				.inline label{font-weight:500;flex-direction:row;align-items:center;gap:6px}
				.mode-options{flex-wrap:nowrap;gap:14px}
				.mode-options label{white-space:nowrap}
				.target-row{display:flex;align-items:center;justify-content:space-between;gap:12px}
				.target-toggle{
					appearance:none;
					border:0;
					background:none;
					padding:0;
					color:var(--cmx-muted);
					font:inherit;
					font-weight:800;
					cursor:pointer;
				}
				.target-toggle.is-active{color:var(--cmx-text)}
				.muted{color:var(--cmx-muted);font-size:12px}
				.pill{
					display:inline-flex;
					align-items:center;
					gap:6px;
					padding:5px 10px;
					border-radius:999px;
					background:var(--cmx-info-soft);
					color:var(--cmx-info);
					font-size:12px;
					font-weight:700;
				}
				button{
					appearance:none;
					border:1px solid var(--cmx-accent);
					background:var(--cmx-accent);
					color:#fff;
					border-radius:14px;
					padding:10px 14px;
					cursor:pointer;
					font-weight:800;
					letter-spacing:.01em;
				}
				button:hover,
				button:focus{background:var(--cmx-accent-dark);border-color:var(--cmx-accent-dark)}
				button.secondary{background:var(--cmx-panel-strong);color:var(--cmx-accent)}
				button.secondary:hover,
				button.secondary:focus{background:var(--cmx-accent-soft)}
				button.danger{background:var(--cmx-error);border-color:var(--cmx-error)}
				button.danger:hover,
				button.danger:focus{background:#922326;border-color:#922326}
				button:disabled{opacity:.55;cursor:not-allowed}
				.suggest-wrap{position:relative}
				.suggest{
					position:absolute;
					left:0;
					right:0;
					top:calc(100% + 4px);
					z-index:1000;
					background:var(--cmx-panel-strong);
					border:1px solid var(--cmx-border);
					border-radius:16px;
					max-height:240px;
					overflow:auto;
					display:none;
					box-shadow:0 12px 28px rgba(36,27,22,.14);
				}
				.suggest button{
					width:100%;
					border:0;
					background:none;
					color:inherit;
					text-align:left;
					padding:11px 12px;
					border-radius:0;
					font-weight:500;
				}
				.suggest button:hover,
				.suggest button.active{background:var(--cmx-info-soft);color:var(--cmx-info)}
				.suggest .hint{padding:10px 12px;color:var(--cmx-muted)}
				.footer{
					display:grid;
					grid-template-columns:auto minmax(0,1fr) auto;
					gap:10px 12px;
					align-items:center;
					margin-top:2px;
				}
				#selection-hint{text-align:center;min-width:0}
				#reset-form{justify-self:start}
				#start-stop{justify-self:end}
				.session{
					padding:12px;
					border-radius:18px;
					background:#f8f2ea;
					border:1px solid var(--cmx-border);
					display:flex;
					flex-direction:column;
					justify-content:center;
					gap:6px;
					min-height:72px;
				}
				.session.is-info{border-color:var(--cmx-info);background:var(--cmx-info-soft)}
				.session.is-success{border-color:var(--cmx-success);background:var(--cmx-success-soft)}
				.session.is-error{border-color:var(--cmx-error);background:var(--cmx-error-soft)}
				.session-message{font-size:13px;font-weight:700;line-height:1.35}
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
					background:rgba(36,27,22,.48);
					backdrop-filter:blur(2px);
				}
				.cmx-stopwatch-dialog-card{
					width:100%;
					border-radius:24px;
					background:var(--cmx-panel-strong);
					border:1px solid rgba(255,255,255,.7);
					box-shadow:0 30px 80px rgba(0,0,0,.28);
					padding:20px;
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
					color:var(--cmx-muted);
				}
				.cmx-stopwatch-dialog-close{
					display:inline-flex;
					align-items:center;
					justify-content:center;
					width:36px;
					height:36px;
					padding:0;
					border-radius:999px;
					border:1px solid rgba(164,44,36,.22);
					background:#fff;
					color:var(--cmx-accent);
					font-size:22px;
					font-weight:800;
					line-height:1;
					box-shadow:0 8px 18px rgba(0,0,0,.08);
				}
				.cmx-stopwatch-dialog-close:hover,
				.cmx-stopwatch-dialog-close:focus{
					background:var(--cmx-accent-soft);
					border-color:#e8c7bf;
					outline:none;
				}
				.cmx-stopwatch-dialog-grid{
					display:grid;
					grid-template-columns:1fr 1fr;
					gap:12px;
					margin-bottom:14px;
				}
				.cmx-stopwatch-stat{
					padding:12px;
					border-radius:16px;
					background:#faf4ed;
					border:1px solid var(--cmx-border);
				}
				.cmx-stopwatch-stat strong{
					display:block;
					font-size:12px;
					text-transform:uppercase;
					letter-spacing:.08em;
					color:var(--cmx-muted);
				}
				.cmx-stopwatch-stat span{
					display:block;
					margin-top:6px;
					font-size:16px;
					font-weight:800;
				}
				.cmx-stopwatch-dialog-actions{
					display:flex;
					flex-wrap:wrap;
					gap:10px;
					justify-content:flex-end;
				}
				.cmx-stopwatch-dialog-status{
					margin-top:12px;
					min-height:20px;
					color:var(--cmx-muted);
					font-size:13px;
				}
				.cmx-stopwatch-reminder-card{
					position:relative;
					width:100%;
					border-radius:26px;
					background:linear-gradient(180deg,#fffaf4,#fff);
					border:1px solid rgba(255,255,255,.72);
					box-shadow:0 30px 80px rgba(0,0,0,.28);
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
					color:var(--cmx-muted);
				}
				.cmx-stopwatch-reminder-target{
					margin:18px 0 8px;
					padding:14px;
					border-radius:20px;
					background:var(--cmx-accent-soft);
					border:1px solid #e8c7bf;
					font-size:18px;
					font-weight:800;
				}
				.cmx-stopwatch-reminder-count{
					display:inline-flex;
					align-items:center;
					justify-content:center;
					width:76px;
					height:76px;
					margin:14px auto 4px;
					border-radius:999px;
					background:var(--cmx-accent);
					color:#fff;
					font-size:28px;
					font-weight:800;
					box-shadow:0 14px 30px rgba(164,44,36,.24);
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
					color:var(--cmx-muted);
					font-size:13px;
				}
				@media (max-width:680px){
					.hero{flex-direction:column;align-items:flex-start}
					.hero-meta{align-items:flex-start;text-align:left}
				}
				@media (max-width:520px){
					body{padding:18px 12px}
					.hero h1{font-size:28px}
					.wrap{padding:12px}
					.panel{padding:14px}
					.mode-row,
					.cmx-stopwatch-dialog-grid{grid-template-columns:1fr}
					.footer{grid-template-columns:1fr}
					#selection-hint{text-align:left}
					#start-stop,
					#reset-form{justify-self:stretch}
				}
			</style>
		</head>
		<body>
			<div class="page">
				<div class="hero">
					<div class="hero-copy">
						<div class="hero-kicker">Mis Büro</div>
						<h1>Zeiterfassung</h1>
						<p>Projekt oder Kontakt wählen und die Zeit direkt in Mis Buero speichern.</p>
					</div>
					<div class="hero-meta">
						<strong><?php echo \esc_html($site_name_display); ?></strong>
						<?php if ($user_display !== '') : ?>
							<span><?php echo \esc_html($user_display); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<div class="wrap">
					<div class="head">
						<div class="brand">
							<div class="icon">
								<img src="<?php echo \esc_attr($icon_src); ?>" alt="" width="58" height="58">
							</div>
							<div>
								<div class="title">Mis Buero</div>
								<div class="subtitle">Stopuhr</div>
							</div>
						</div>
						<button type="button" class="gear" id="open-settings" aria-label="Stopuhr-Einstellungen" title="Stopuhr-Einstellungen">
							<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
								<path fill="currentColor" d="M19.43 12.98c.04-.32.07-.65.07-.98s-.03-.66-.08-.98l2.11-1.65a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.6-.22l-2.49 1a7.03 7.03 0 0 0-1.69-.98l-.38-2.65A.5.5 0 0 0 14.1 1h-4a.5.5 0 0 0-.49.42l-.38 2.65c-.61.24-1.17.56-1.69.98l-2.49-1a.5.5 0 0 0-.6.22l-2 3.46a.5.5 0 0 0 .12.64l2.11 1.65c-.05.32-.08.66-.08.98s.03.66.08.98l-2.11 1.65a.5.5 0 0 0-.12.64l2 3.46a.5.5 0 0 0 .6.22l2.49-1c.52.42 1.08.74 1.69.98l.38 2.65a.5.5 0 0 0 .49.42h4a.5.5 0 0 0 .49-.42l.38-2.65c.61-.24 1.17-.56 1.69-.98l2.49 1a.5.5 0 0 0 .6-.22l2-3.46a.5.5 0 0 0-.12-.64l-2.11-1.65ZM12 15.5A3.5 3.5 0 1 1 12 8.5a3.5 3.5 0 0 1 0 7Z"/>
							</svg>
						</button>
					</div>

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
										<label><input type="radio" name="cmx-ext-time-mode" value="note"> Notiz</label>
										<label><input type="radio" name="cmx-ext-time-mode" value="task" checked> Taetigkeit</label>
										<label class="task-inline task-only" id="task-inline"><input type="checkbox" id="verrechenbar" checked> verrechenbar</label>
									</div>
								</div>
								<label class="note-subject is-hidden" id="note-subject-wrap" aria-label="Betreff">
									<select id="note-subject" aria-label="Betreff"></select>
								</label>
							</div>
							<div class="session" id="session-card">
								<div class="muted" id="session-label">Intervall</div>
								<div id="interval-display" class="pill">-</div>
								<div id="session-message" class="session-message" hidden></div>
							</div>
							<div class="target-row">
								<button type="button" class="target-toggle is-active" id="target-project" data-target="project">Projekt</button>
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
								<button type="button" class="secondary" id="reset-form">Zuruecksetzen</button>
								<div class="muted" id="selection-hint" hidden></div>
								<button type="button" id="start-stop">Start</button>
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
							<p>Diese Seite ist direkt mit Deiner aktuellen Mis Buero Instanz verbunden.</p>
						</div>
						<button type="button" class="cmx-stopwatch-dialog-close" data-stopwatch-close="settings" aria-label="Fenster schließen" title="Fenster schließen">×</button>
					</div>
					<div class="cmx-stopwatch-dialog-grid">
						<div class="cmx-stopwatch-stat">
							<strong>Instanz</strong>
							<span id="cmx-stopwatch-setting-instance">-</span>
						</div>
						<div class="cmx-stopwatch-stat">
							<strong>Benutzer</strong>
							<span id="cmx-stopwatch-setting-user">-</span>
						</div>
						<div class="cmx-stopwatch-stat">
							<strong>Intervall</strong>
							<span id="cmx-stopwatch-setting-interval">-</span>
						</div>
						<div class="cmx-stopwatch-stat">
							<strong>Link</strong>
							<span id="cmx-stopwatch-setting-link">bereit</span>
						</div>
					</div>
					<div class="cmx-stopwatch-dialog-actions">
						<button type="button" class="secondary" id="cmx-stopwatch-copy-link">Link kopieren</button>
						<button type="button" class="secondary" id="cmx-stopwatch-reload-bootstrap">Daten neu laden</button>
						<button type="button" class="secondary" data-stopwatch-close="settings">Schließen</button>
					</div>
					<div class="cmx-stopwatch-dialog-status" id="cmx-stopwatch-settings-status"></div>
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
					var reminderDeadlineMs = 0;

					var settingsRoot = document.getElementById('cmx-stopwatch-settings');
					var settingsStatus = document.getElementById('cmx-stopwatch-settings-status');
					var settingsInstance = document.getElementById('cmx-stopwatch-setting-instance');
					var settingsUser = document.getElementById('cmx-stopwatch-setting-user');
					var settingsInterval = document.getElementById('cmx-stopwatch-setting-interval');
					var settingsLink = document.getElementById('cmx-stopwatch-setting-link');
					var settingsReload = document.getElementById('cmx-stopwatch-reload-bootstrap');
					var settingsCopy = document.getElementById('cmx-stopwatch-copy-link');
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
						settingsStatus.style.color = isError ? 'var(--cmx-error)' : 'var(--cmx-muted)';
					}

					function openSettingsDialog() {
						if (!settingsRoot) {
							return;
						}
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
						var siteUrl = String(source.siteUrl || '').replace(/\/+$/, '');
						var ajaxUrl = String(source.ajaxUrl || '').trim();
						var slug = '';
						try {
							slug = new URL(siteUrl || window.location.origin).hostname.replace(/\.misbuero\.ch$/i, '');
						} catch (error) {
							slug = String(siteUrl || window.location.hostname || 'misbuero');
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
							stopwatchUrl: String(source.stopwatchUrl || window.location.href),
							updatedAt: new Date().toISOString()
						};
					}

					function writeInstances(instances) {
						writeValue(RAW_INSTANCE_KEY, Array.isArray(instances) ? instances : []);
					}

					function seedInstance(source) {
						var instance = buildInstance(source);
						writeInstances([instance]);
						updateSettingsSummary(instance);
						return instance;
					}

					function updateSettingsSummary(instance) {
						if (!instance) {
							return;
						}
						if (settingsInstance) {
							settingsInstance.textContent = instance.siteName || instance.baseUrl || '-';
						}
						if (settingsUser) {
							settingsUser.textContent = instance.userDisplay || instance.userLogin || '-';
						}
						if (settingsInterval) {
							settingsInterval.textContent = String(instance.defaultInterval || 5) + ' min';
						}
						if (settingsLink) {
							settingsLink.textContent = instance.stopwatchUrl ? 'aktiv' : '-';
						}
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
							translatedKey(RAW_INSTANCE_KEY),
							RAW_ACTIVE_KEY,
							RAW_INSTANCE_KEY
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

						var next = normalizeInstanceRecord(json.data || {});
						next.userId = BOOTSTRAP.userId || next.userId || 0;
						next.userLogin = normalizeText(BOOTSTRAP.userLogin || next.userLogin || '');
						next.storageNamespace = storageNamespace;
						next.stopwatchUrl = BOOTSTRAP.stopwatchUrl || window.location.href;
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
						session.instance = instance;
						session.instanceKey = instance.slug || instance.baseUrl;
						storeActiveSession(session);
						scheduleReminder(session);
					}

					function cleanedStopwatchUrl() {
						var url = new URL(String(instance && instance.stopwatchUrl ? instance.stopwatchUrl : window.location.href), window.location.origin);
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
							intervalMinutes: Number(payload.instance.defaultInterval || 5),
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
						Object.keys(session).forEach(function (key) {
							nextSession[key] = session[key];
						});

						if (payload && Object.prototype.hasOwnProperty.call(payload, 'info')) {
							nextSession.info = typeof payload.info === 'string' ? payload.info : '';
						}
						if (payload && Object.prototype.hasOwnProperty.call(payload, 'betreff')) {
							nextSession.betreff = typeof payload.betreff === 'string' ? payload.betreff : '';
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

						return Promise.resolve(null);
					};
					window.chrome = chromeShim;

					var instance = seedInstance(BOOTSTRAP);
					refreshActiveSessionInstance(instance);

					if (settingsReload) {
						settingsReload.addEventListener('click', async function () {
							setSettingsStatus('Daten werden neu geladen...', false);
							try {
								var nextBootstrap = await fetchBootstrapData(instance);
								instance = seedInstance(nextBootstrap);
								refreshActiveSessionInstance(instance);
								setSettingsStatus('Daten aktualisiert. Seite wird neu geladen.', false);
								window.setTimeout(function () {
									window.location.reload();
								}, 260);
							} catch (error) {
								setSettingsStatus(error && error.message ? error.message : 'Die Stopuhr konnte die Daten nicht neu laden.', true);
							}
						});
					}

					if (settingsCopy) {
						settingsCopy.addEventListener('click', function () {
							copyText(String(instance.stopwatchUrl || window.location.href)).then(function (copied) {
								setSettingsStatus(copied ? 'Link wurde in die Zwischenablage kopiert.' : 'Link konnte nicht kopiert werden.', !copied);
							});
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
