<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

$widgets = ['date_range','stammdaten','rechnungen','angebote','online_shop','rechnungen_faellig','lieferantenrechungen','kontakte_info','gutschriften','quittungen','kuchen_ein_aus_ok','kuchen_ein_aus_nok','overview_revenue','projekte','stoppuhr_notizen','stoppuhr_taetigkeiten','view_pendenzen','view_monitor'];

foreach ($widgets as $file) {
	$path = __DIR__ . '/' . $file . '.php';
	if (is_readable($path)) {
		require_once $path;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_lazy_dashboard_widget_callbacks')) {
	function cmx_cockpit_lazy_dashboard_widget_callbacks(): array {
		return [
			'cmx_cpt_counts_widget' => __NAMESPACE__ . '\\cmx_render_cpt_count_widget',
			'cmx_umsatz_widget' => __NAMESPACE__ . '\\cmx_render_umsatz_widget',
			'cmx_angebote_widget' => __NAMESPACE__ . '\\cmx_render_angebote_widget',
			'cmx_online_shop_widget' => __NAMESPACE__ . '\\cmx_render_online_shop_widget',
			'cmx_rechnungen_faellig_widget' => __NAMESPACE__ . '\\cmx_render_rechnungen_faellig_widget',
			'cmx_lieferanten_widget' => __NAMESPACE__ . '\\cmx_render_lieferanten_widget',
			'cmx_kontakt_wichtige_daten' => __NAMESPACE__ . '\\cmx_render_kontakt_wichtige_daten',
			'cmx_gutschriften_widget' => __NAMESPACE__ . '\\cmx_render_gutschriften_widget',
			'cmx_quittungen_widget' => __NAMESPACE__ . '\\cmx_render_quittungen_widget',
			'cmx_kuchen_ein_aus' => __NAMESPACE__ . '\\cmx_render_kuchen_ein_aus',
			'cmx_kuchen_ein_aus_nok' => __NAMESPACE__ . '\\cmx_render_kuchen_ein_aus_nok',
			'cmx_overview_revenue_widget' => __NAMESPACE__ . '\\cmx_render_overview_revenue_widget',
			'cmx_projekte_widget' => __NAMESPACE__ . '\\cmx_render_projekte_widget',
			'cmx_stoppuhr_notizen_widget' => __NAMESPACE__ . '\\cmx_render_stoppuhr_notizen_widget',
			'cmx_stoppuhr_taetigkeiten_widget' => __NAMESPACE__ . '\\cmx_render_stoppuhr_taetigkeiten_widget',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_is_lazy_dashboard_request')) {
	function cmx_cockpit_is_lazy_dashboard_request(): bool {
		if (!\is_admin() || \wp_doing_ajax()) {
			return false;
		}

		return (($GLOBALS['pagenow'] ?? '') === 'index.php');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_replace_dashboard_callbacks_with_lazy_shell')) {
	function cmx_cockpit_replace_dashboard_callbacks_with_lazy_shell(): void {
		if (!cmx_cockpit_is_lazy_dashboard_request()) {
			return;
		}

		global $wp_meta_boxes;
		if (!\is_array($wp_meta_boxes) || !isset($wp_meta_boxes['dashboard']) || !\is_array($wp_meta_boxes['dashboard'])) {
			return;
		}

		$callbacks = cmx_cockpit_lazy_dashboard_widget_callbacks();
		if ($callbacks === []) {
			return;
		}

		foreach ($wp_meta_boxes['dashboard'] as $context => $priorities) {
			if (!\is_array($priorities)) {
				continue;
			}

			foreach ($priorities as $priority => $boxes) {
				if (!\is_array($boxes)) {
					continue;
				}

				foreach (\array_keys($boxes) as $widget_id) {
					$widget_id = (string) $widget_id;
					if (!isset($callbacks[$widget_id])) {
						continue;
					}

					$registered_callback = $wp_meta_boxes['dashboard'][$context][$priority][$widget_id]['callback'] ?? null;
					if (!\is_callable($registered_callback)) {
						continue;
					}

					$args = $wp_meta_boxes['dashboard'][$context][$priority][$widget_id]['args'] ?? [];
					if (!\is_array($args)) {
						$args = [];
					}
					$args['cmx_lazy_widget_id'] = $widget_id;

					$wp_meta_boxes['dashboard'][$context][$priority][$widget_id]['args'] = $args;
					$wp_meta_boxes['dashboard'][$context][$priority][$widget_id]['callback'] = __NAMESPACE__ . '\\cmx_render_lazy_dashboard_widget_shell';
				}
			}
		}
	}
}
\add_action('wp_dashboard_setup', __NAMESPACE__ . '\\cmx_cockpit_replace_dashboard_callbacks_with_lazy_shell', 1000);

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_lazy_dashboard_widget_shell')) {
	function cmx_render_lazy_dashboard_widget_shell($unused, array $box = []): void {
		$args = (isset($box['args']) && \is_array($box['args'])) ? $box['args'] : [];
		$widget_id = \sanitize_key((string) ($args['cmx_lazy_widget_id'] ?? ($box['id'] ?? '')));
		if ($widget_id === '') {
			echo '<p>Widget konnte nicht vorbereitet werden.</p>';
			return;
		}

		static $assets_printed = false;
		if (!$assets_printed) {
			$assets_printed = true;
			$ajax_url = \admin_url('admin-ajax.php');
			$nonce = \wp_create_nonce('cmx_dashboard_widget');
			?>
			<style>
				.cmx-dashboard-lazy-widget{min-height:64px}
				.cmx-dashboard-lazy-widget__status{margin:0;color:#646970}
				.cmx-dashboard-lazy-widget.is-loading{opacity:.72}
				.cmx-dashboard-lazy-widget.is-error .cmx-dashboard-lazy-widget__status{color:#b32d2e}
			</style>
			<script>
			(function(){
				if (window.cmxDashboardLazyWidgetsBooted) {
					return;
				}
				window.cmxDashboardLazyWidgetsBooted = true;

				var config = {
					ajaxUrl: <?php echo \wp_json_encode($ajax_url); ?>,
					nonce: <?php echo \wp_json_encode($nonce); ?>
				};
				var externalScripts = new Map();

				function copyScriptAttributes(from, to){
					Array.prototype.forEach.call(from.attributes || [], function(attr){
						if (!attr || !attr.name || attr.name === 'src') {
							return;
						}
						to.setAttribute(attr.name, attr.value);
					});
				}

				async function executeScripts(container){
					var scripts = Array.prototype.slice.call(container.querySelectorAll('script'));
					for (var i = 0; i < scripts.length; i++) {
						var oldScript = scripts[i];
						var src = oldScript.getAttribute('src');
						if (src) {
							oldScript.remove();
							if (!externalScripts.has(src)) {
								var currentScript = oldScript;
								var currentSrc = src;
								externalScripts.set(src, new Promise(function(resolve, reject){
									var script = document.createElement('script');
									copyScriptAttributes(currentScript, script);
									script.src = currentSrc;
									script.async = false;
									script.onload = resolve;
									script.onerror = function(){ reject(new Error('Script failed: ' + currentSrc)); };
									document.body.appendChild(script);
								}));
							}
							await externalScripts.get(src);
							continue;
						}

						var inlineScript = document.createElement('script');
						copyScriptAttributes(oldScript, inlineScript);
						inlineScript.text = oldScript.textContent || '';
						oldScript.replaceWith(inlineScript);
					}
				}

				async function loadWidget(container){
					var widgetId = container.getAttribute('data-cmx-widget-id') || '';
					if (!widgetId) {
						return;
					}

					container.classList.add('is-loading');
					var body = new URLSearchParams();
					body.set('action', 'cmx_dashboard_widget');
					body.set('widget_id', widgetId);
					body.set('nonce', config.nonce);

					try {
						var response = await fetch(config.ajaxUrl, {
							method: 'POST',
							credentials: 'same-origin',
							headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
							body: body.toString()
						});
						var html = await response.text();
						if (!response.ok) {
							throw new Error(html || ('HTTP ' + response.status));
						}

						container.innerHTML = html;
						await executeScripts(container);
						container.classList.remove('is-loading');
						container.classList.remove('is-error');
					} catch (error) {
						console.error(error);
						container.classList.remove('is-loading');
						container.classList.add('is-error');
						container.innerHTML = '<p class="cmx-dashboard-lazy-widget__status">Widget konnte nicht geladen werden.</p>';
					}
				}

				async function boot(){
					var widgets = Array.prototype.slice.call(document.querySelectorAll('.cmx-dashboard-lazy-widget[data-cmx-widget-id]'));
					for (var i = 0; i < widgets.length; i++) {
						await loadWidget(widgets[i]);
					}
				}

				if (document.readyState === 'loading') {
					document.addEventListener('DOMContentLoaded', boot, {once: true});
				} else {
					boot();
				}
			})();
			</script>
			<?php
		}

		echo '<div class="cmx-dashboard-lazy-widget" data-cmx-widget-id="' . \esc_attr($widget_id) . '">';
		echo '<p class="cmx-dashboard-lazy-widget__status">Widget wird geladen ...</p>';
		echo '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_ajax_render_lazy_dashboard_widget')) {
	function cmx_cockpit_ajax_render_lazy_dashboard_widget(): void {
		if (!\current_user_can('edit_posts')) {
			\wp_die('Keine Berechtigung.', 403);
		}

		\check_ajax_referer('cmx_dashboard_widget', 'nonce');

		$widget_id = \sanitize_key((string) ($_POST['widget_id'] ?? ''));
		$callbacks = cmx_cockpit_lazy_dashboard_widget_callbacks();
		$callback = $callbacks[$widget_id] ?? null;

		if ($widget_id === '' || !\is_callable($callback)) {
			\wp_die('Widget nicht gefunden.', 404);
		}

		if (\function_exists('set_current_screen')) {
			\set_current_screen('dashboard');
		}

		\ob_start();
		try {
			\call_user_func($callback, [], ['id' => $widget_id, 'args' => []]);
		} catch (\Throwable $throwable) {
			\ob_end_clean();
			\error_log('[cmx-cockpit] lazy dashboard widget failed (' . $widget_id . '): ' . $throwable->getMessage());
			\wp_die('Widget konnte nicht geladen werden.', 500);
		}

		$html = (string) \ob_get_clean();
		echo $html;
		\wp_die();
	}
}
\add_action('wp_ajax_cmx_dashboard_widget', __NAMESPACE__ . '\\cmx_cockpit_ajax_render_lazy_dashboard_widget');
