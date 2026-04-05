<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Tab: Allgemein
 */
if (!\function_exists(__NAMESPACE__ . '\\cmx_mwst_exempt_default_note_html')) {
	function cmx_mwst_exempt_default_note_html(): string {
		return 'Nicht mehrwertsteuerpflichtig gemäss <a href="https://www.fedlex.admin.ch/eli/cc/2009/615/de#art_10" style="color:black;" target="_blank" rel="noopener noreferrer">Art. 10 Abs. 2 lit. a MWSTG</a>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_preserve_backup_setting_when_missing')) {
	function cmx_preserve_backup_setting_when_missing($value, string $option = '', $original_value = null) {
		if ($original_value !== null) {
			return $value;
		}

		return match ($option) {
			'misbuero_backup_download_url' => (string) \get_option($option, ''),
			'misbuero_backup_created_at'   => (string) \get_option($option, ''),
			'misbuero_backup_file'         => (string) \get_option($option, ''),
			'misbuero_backup_size_bytes'   => (int) \get_option($option, 0),
			default                        => $value,
		};
	}
}

foreach ([
	'misbuero_backup_download_url',
	'misbuero_backup_created_at',
	'misbuero_backup_file',
	'misbuero_backup_size_bytes',
] as $cmx_backup_option) {
	\add_filter('sanitize_option_' . $cmx_backup_option, __NAMESPACE__ . '\\cmx_preserve_backup_setting_when_missing', 1, 3);
}

\add_action('admin_init', __NAMESPACE__ . '\\cmx_register_general_tab');
function cmx_register_general_tab(): void {

	\add_settings_section(
		'cmx_sec_general',
		__('Allgemein', 'default'),
		'__return_false',
		'cmx_tab_general'
	);

	\add_settings_field(
		'quick_edit',
		'Quick Edit',
		function () {
			\CLOUDMEISTER\CMX\Buero\cmx_field_checkbox([
				'key'   => 'quick_edit',
				'label' => 'Beleg Schnell-Erfassung in der Admin-Bar anzeigen',
			]);
			echo '<p class="description">Blendet im WP-Admin zentriert die beiden Auswahllisten für Kontakt und Artikel sowie den Button zum direkten Erstellen eines Belegs ein.</p>';
		},
		'cmx_tab_general',
		'cmx_sec_general'
	);

	\add_settings_field(
		'quick_search',
		'Quick Search',
		function () {
			\CLOUDMEISTER\CMX\Buero\cmx_field_checkbox([
				'key'   => 'quick_search',
				'label' => 'CPT-Adminlisten beim Tippen direkt per AJAX durchsuchen',
			]);
			echo '<p class="description">Zeigt Treffer schon während der Eingabe, statt erst nach Klick auf den Suchen Button.</p>';
		},
		'cmx_tab_general',
		'cmx_sec_general'
	);

	\add_settings_field(
		'support_user_switch',
		'Support',
		function () {
			\CLOUDMEISTER\CMX\Buero\cmx_field_checkbox([
				'key'   => 'support_user_switch',
				'label' => 'Benutzerwechsel erlauben',
			]);
			echo '<p class="description">Bei aktivierter Funktion darf der Support temporär in Deine Benutzerrollen wechseln, um Probleme zu analysieren.</p>';
		},
		'cmx_tab_general',
		'cmx_sec_general'
	);

	$backup_download_url = (string) \get_option('misbuero_backup_download_url', '');
	$backup_download_url = \esc_url_raw($backup_download_url);
	$backup_file = \sanitize_file_name((string) \get_option('misbuero_backup_file', ''));
	$backup_created_raw = \trim((string) \get_option('misbuero_backup_created_at', ''));
	$backup_size_bytes = (int) \get_option('misbuero_backup_size_bytes', 0);
	$backup_path_option = \wp_normalize_path((string) \get_option('misbuero_backup_path', ''));
	$backup_exists = false;
	$backup_found_path = '';
	$backup_dir = \defined('WP_CONTENT_DIR')
		? \wp_normalize_path((string) \constant('WP_CONTENT_DIR') . '/uploads/misbuero-backups')
		: '';

	if ($backup_file === '' && $backup_path_option !== '') {
		$backup_file = \sanitize_file_name(\wp_basename($backup_path_option));
	}

	if ($backup_file === '' && $backup_download_url !== '') {
		if (\preg_match('#/misbuero-backups/([^/?#]+\.(?:zip|tar\.gz))\b#i', $backup_download_url, $m_file)) {
			$backup_file = \sanitize_file_name(\rawurldecode((string) $m_file[1]));
		} elseif (\preg_match('/[?&]file=([^&]+)/i', $backup_download_url, $m_file)) {
			$backup_file = \sanitize_file_name(\rawurldecode((string) $m_file[1]));
		}
	}

	$backup_paths = [];
	if ($backup_path_option !== '') {
		$backup_paths[] = $backup_path_option;
	}
	if ($backup_file !== '') {
		if ($backup_dir !== '') {
			$backup_paths[] = \wp_normalize_path(\rtrim($backup_dir, '/\\') . '/' . $backup_file);
		}
		if (\defined(__NAMESPACE__ . '\\CMX_UPLOADS_MISBUERO')) {
			$backup_paths[] = \wp_normalize_path(\trailingslashit((string) \constant(__NAMESPACE__ . '\\CMX_UPLOADS_MISBUERO')) . $backup_file);
			$backup_paths[] = \wp_normalize_path(\trailingslashit((string) \constant(__NAMESPACE__ . '\\CMX_UPLOADS_MISBUERO')) . 'backups/' . $backup_file);
		}
		if (\defined('WP_CONTENT_DIR')) {
			$backup_paths[] = \wp_normalize_path(\trailingslashit((string) \constant('WP_CONTENT_DIR')) . 'uploads/' . $backup_file);
			$backup_paths[] = \wp_normalize_path(\trailingslashit((string) \constant('WP_CONTENT_DIR')) . 'uploads/misbuero/' . $backup_file);
			$backup_paths[] = \wp_normalize_path(\trailingslashit((string) \constant('WP_CONTENT_DIR')) . 'uploads/misbuero/backups/' . $backup_file);
		}
	}
	$backup_paths = \array_values(\array_unique(\array_filter($backup_paths, static function ($path): bool {
		return \is_string($path) && $path !== '';
	})));

	foreach ($backup_paths as $backup_path) {
		if (\is_file($backup_path)) {
			$backup_exists = true;
			$backup_found_path = $backup_path;
			break;
		}
	}

	if (!$backup_exists && $backup_dir !== '' && \is_dir($backup_dir)) {
		$domain_hint = \strtolower((string) \wp_parse_url(\home_url('/'), PHP_URL_HOST));
		$domain_prefix = ($domain_hint !== '') ? 'backup-' . $domain_hint . '-' : '';
		$best_any = '';
		$best_any_mtime = 0;
		$best_domain = '';
		$best_domain_mtime = 0;
		$files = \glob(\rtrim($backup_dir, '/\\') . '/backup-*');
		if (!\is_array($files)) {
			$files = [];
		}
		foreach ($files as $file_path) {
			$file_path = \wp_normalize_path((string) $file_path);
			if (!\is_file($file_path)) {
				continue;
			}
			$basename = \basename($file_path);
			if (!\preg_match('/^backup-[a-z0-9.-]+\-\d{8}\-\d{6}\-[a-z0-9]+\.(?:zip|tar\.gz)$/i', $basename)) {
				continue;
			}
			$mtime = \filemtime($file_path);
			$mtime = ($mtime !== false) ? (int) $mtime : 0;
			if ($mtime >= $best_any_mtime) {
				$best_any_mtime = $mtime;
				$best_any = $basename;
			}
			if ($domain_prefix !== '' && \strpos(\strtolower($basename), $domain_prefix) === 0 && $mtime >= $best_domain_mtime) {
				$best_domain_mtime = $mtime;
				$best_domain = $basename;
			}
		}
		$fallback_file = ($best_domain !== '') ? $best_domain : $best_any;
		if ($fallback_file !== '') {
			$backup_file = \sanitize_file_name((string) $fallback_file);
			$backup_found_path = \wp_normalize_path(\rtrim($backup_dir, '/\\') . '/' . $backup_file);
			$backup_exists = \is_file($backup_found_path);
		}
	}

	if ($backup_exists && $backup_size_bytes <= 0 && $backup_found_path !== '') {
		$detected_size = \filesize($backup_found_path);
		if (\is_int($detected_size) && $detected_size > 0) {
			$backup_size_bytes = $detected_size;
		}
	}

	if ($backup_exists && $backup_file !== '' && \defined('ABSPATH')) {
		$local_endpoint_path = \wp_normalize_path(\rtrim((string) \constant('ABSPATH'), '/\\') . '/misbuero-backup-download.php');
		if (\is_file($local_endpoint_path)) {
			$backup_download_url = \add_query_arg(['file' => $backup_file], \home_url('/misbuero-backup-download.php'));
		}
	}

	if ($backup_download_url !== '' && $backup_exists) {
		\add_settings_field(
			'misbuero_instance_backup_link',
			'Backup',
			function () use ($backup_download_url, $backup_created_raw, $backup_size_bytes) {
				$size_human = '';
				if ($backup_size_bytes > 0) {
					$units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
					$idx = 0;
					$size = (float) $backup_size_bytes;
					while ($size >= 1024 && $idx < \count($units) - 1) {
						$size /= 1024;
						$idx++;
					}
					$dec = ($size >= 100 || $idx === 0) ? 0 : 1;
					$size_human = \number_format($size, $dec, '.', '') . ' ' . $units[$idx];
				}

				$meta = [];
				if ($backup_created_raw !== '') {
					$created_ts = \strtotime($backup_created_raw);
					if ($created_ts !== false) {
						$meta[] = 'erstellt am ' . \date_i18n('d.m.Y H:i', $created_ts);
					} else {
						$meta[] = 'erstellt am ' . $backup_created_raw;
					}
				}
				if ($size_human !== '') {
					$meta[] = $size_human;
				}

				echo '<p class="description"><a href="' . \esc_url($backup_download_url) . '" target="_blank" rel="noopener">Backup herunterladen</a>';
				if (!empty($meta)) {
					echo ' (' . \esc_html(\implode(' | ', $meta)) . ')';
				}
				echo '</p>';
			},
			'cmx_tab_general',
			'cmx_sec_general'
		);
	}

	\add_settings_field(
		'help_sync_button',
		'Hilfe-Texte',
		function () {
			if (!\function_exists('\\CLOUDMEISTER\\CMX\\Buero\\cmx_help_is_cloud_meister') || !\CLOUDMEISTER\CMX\Buero\cmx_help_is_cloud_meister()) {
				echo '<em>Nur für Admin (CLOUD Meister)</em>';
				return;
			}
			$host = (string) \wp_parse_url(\home_url(), PHP_URL_HOST);
			if ($host === 'vorlage.misbuero.ch') {
				return;
			}
			$nonce  = \wp_create_nonce('cmx_help_sync');
			echo '<button type="button" class="button" id="cmx-help-sync-btn">Neue Hilfetexte laden</button>';
			echo '<span class="spinner" id="cmx-help-sync-spinner" style="float:none;margin-left:8px;"></span>';
			echo '<div id="cmx-help-sync-status" style="margin-top:8px;min-height:20px;"></div>';
			echo '<script>
			(function(){
				const btn = document.getElementById("cmx-help-sync-btn");
				const spinner = document.getElementById("cmx-help-sync-spinner");
				const status = document.getElementById("cmx-help-sync-status");
				if (!btn || !spinner || !status) return;
				function setStatus(text){ status.textContent = text || ""; }
				btn.addEventListener("click", function(){
					setStatus("Lese Hilfetexte...");
					spinner.classList.add("is-active");
					btn.disabled = true;
					const form = new URLSearchParams();
					form.append("action","cmx_help_sync");
					form.append("nonce","'.\esc_js($nonce).'");
					fetch(ajaxurl, {method:"POST", credentials:"same-origin", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:form.toString()})
						.then(r => r.json())
						.then(data => {
							const keys = data && data.data && Array.isArray(data.data.keys) ? data.data.keys : [];
							let i = 0;
							function step(){
								if (i < keys.length) {
									setStatus("Lese: " + keys[i]);
									i++;
									setTimeout(step, 40);
								} else {
									setStatus("Alle Texte geladen.");
									spinner.classList.remove("is-active");
									btn.disabled = false;
								}
							}
							step();
						})
						.catch(() => {
							setStatus("Hilfetexte konnten nicht geladen werden.");
							spinner.classList.remove("is-active");
							btn.disabled = false;
						});
				});
			})();
			</script>';
		},
		'cmx_tab_general',
		'cmx_sec_general'
	);

	// QR-Referenz wird pro Bank im Tab "Banken" gepflegt.

	// Wenn nötig: register_setting() ebenfalls hier setzen
	// \register_setting('cmx_einstellungen','cmx_einstellungen');
}



// QR-Referenz wird pro Bank verarbeitet (siehe Tab "Banken").

if (!\function_exists(__NAMESPACE__ . '\\cmx_admin_quick_search_enabled')) {
	function cmx_admin_quick_search_enabled(): bool {
		$options = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			? (array) \get_option(CMX_SETTINGS_MAIN, [])
			: [];

		return !empty($options['quick_search']);
	}
}

\add_action('admin_footer-edit.php', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->base ?? '') !== 'edit') {
		return;
	}

	$post_type = (string) ($screen->post_type ?? '');
	if ($post_type === '' || !\post_type_exists($post_type)) {
		return;
	}

	$post_type_object = \get_post_type_object($post_type);
	if (!$post_type_object || !empty($post_type_object->_builtin)) {
		return;
	}

	$label = \trim(\wp_strip_all_tags((string) ($post_type_object->labels->name ?? $post_type_object->label ?? $post_type)));
	if ($label === '') {
		$label = $post_type;
	}

	$button_label = $label . ' suchen';
	$enabled = cmx_admin_quick_search_enabled();
	?>
	<style>
		#posts-filter.cmx-admin-quick-search-busy{
			opacity:.72;
			transition:opacity .16s ease;
		}
		#the-list tr.cmx-admin-quick-search-active-row > th,
		#the-list tr.cmx-admin-quick-search-active-row > td{
			background:#edf5ff !important;
		}
		#the-list tr.cmx-admin-quick-search-active-row > th:first-child,
		#the-list tr.cmx-admin-quick-search-active-row > td:first-child{
			box-shadow:inset 2px 0 0 #2271b1;
		}
	</style>
	<script>
	(function(){
		var runtime = window.cmxAdminQuickSearchRuntime = window.cmxAdminQuickSearchRuntime || {
			bound: false,
			enabled: false,
			buttonLabel: "",
			postType: "",
			timer: 0,
			controller: null,
			requestId: 0,
			activeResultIndex: -1
		};
		runtime.enabled = <?php echo \wp_json_encode($enabled); ?>;
		runtime.buttonLabel = <?php echo \wp_json_encode($button_label); ?>;
		runtime.postType = <?php echo \wp_json_encode($post_type); ?>;

		function getPostsFilter(){
			return document.getElementById("posts-filter");
		}

		function getSearchInput(root){
			var scope = root && root.querySelector ? root : document;
			return scope.querySelector('#posts-filter input[name="s"]');
		}

		function getSearchButton(root){
			var scope = root && root.querySelector ? root : document;
			return scope.querySelector("#search-submit");
		}

		function getResultLink(row){
			if (!(row instanceof HTMLTableRowElement)) {
				return null;
			}
			return row.querySelector("a.row-title")
				|| row.querySelector("td.title strong a")
				|| row.querySelector("strong a");
		}

		function getResultRows(){
			return Array.prototype.filter.call(document.querySelectorAll("#the-list tr"), function(row){
				return row instanceof HTMLTableRowElement
					&& !row.classList.contains("no-items")
					&& !row.classList.contains("inline-editor")
					&& !!getResultLink(row);
			});
		}

		function clearActiveResult(){
			runtime.activeResultIndex = -1;
			document.querySelectorAll("#the-list tr.cmx-admin-quick-search-active-row").forEach(function(row){
				row.classList.remove("cmx-admin-quick-search-active-row");
				row.removeAttribute("aria-selected");
			});
		}

		function setActiveResult(index){
			var rows = getResultRows();
			if (!rows.length) {
				clearActiveResult();
				return null;
			}

			if (index < 0) {
				index = 0;
			}
			if (index >= rows.length) {
				index = rows.length - 1;
			}

			clearActiveResult();
			runtime.activeResultIndex = index;

			var row = rows[index];
			row.classList.add("cmx-admin-quick-search-active-row");
			row.setAttribute("aria-selected", "true");
			if (typeof row.scrollIntoView === "function") {
				row.scrollIntoView({ block: "nearest", inline: "nearest" });
			}

			return row;
		}

		function moveActiveResult(step){
			var rows = getResultRows();
			if (!rows.length) {
				clearActiveResult();
				return null;
			}

			var nextIndex = runtime.activeResultIndex;
			if (nextIndex < 0 || nextIndex >= rows.length) {
				nextIndex = step > 0 ? 0 : rows.length - 1;
			} else {
				nextIndex += step;
			}

			if (nextIndex < 0) {
				nextIndex = 0;
			}
			if (nextIndex >= rows.length) {
				nextIndex = rows.length - 1;
			}

			return setActiveResult(nextIndex);
		}

		function getActiveResultLink(){
			var rows = getResultRows();
			if (!rows.length || runtime.activeResultIndex < 0 || runtime.activeResultIndex >= rows.length) {
				return null;
			}
			return getResultLink(rows[runtime.activeResultIndex]);
		}

		function focusAndSelectSearchInput(input){
			if (!(input instanceof HTMLInputElement)) {
				input = getSearchInput(document);
			}
			if (!(input instanceof HTMLInputElement)) {
				return;
			}
			if (typeof input.focus === "function") {
				input.focus({ preventScroll: true });
			}
			if (typeof input.select === "function") {
				input.select();
				return;
			}
			if (typeof input.setSelectionRange === "function") {
				input.setSelectionRange(0, input.value.length);
			}
		}

		function updateSearchButtonLabel(root){
			var button = getSearchButton(root);
			if (!button) return;
			button.value = runtime.buttonLabel;
			button.setAttribute("aria-label", runtime.buttonLabel);
			button.setAttribute("title", runtime.buttonLabel);
		}

		function replaceRefreshFragments(nextDoc){
			document.querySelectorAll("[data-cmx-admin-refresh-fragment][id]").forEach(function(currentEl){
				var id = currentEl.getAttribute("id") || "";
				if (!id) return;
				var nextEl = nextDoc.getElementById(id);
				if (nextEl && nextEl.getAttribute("data-cmx-admin-refresh-fragment") !== null) {
					currentEl.outerHTML = nextEl.outerHTML;
					return;
				}
				currentEl.remove();
			});
		}

		function buildSearchUrl(rawQuery){
			var form = getPostsFilter();
			if (!form) {
				return window.location.href;
			}

			var params = new URLSearchParams(new FormData(form));
			var query = String(rawQuery || "");

			params.delete("_wp_http_referer");
			if (query.trim() === "") {
				params.delete("s");
			} else {
				params.set("s", query);
			}
			params.set("paged", "1");
			if (!params.get("post_type")) {
				params.set("post_type", runtime.postType);
			}

			var qs = params.toString();
			return window.location.pathname + (qs ? "?" + qs : "");
		}

		function setBusy(isBusy){
			var form = getPostsFilter();
			if (form) {
				form.classList.toggle("cmx-admin-quick-search-busy", !!isBusy);
			}
			var button = getSearchButton();
			if (!button) return;
			button.disabled = !!isBusy;
			button.classList.toggle("disabled", !!isBusy);
			button.value = !!isBusy ? (runtime.buttonLabel + " ...") : runtime.buttonLabel;
		}

		function afterRefresh(expectedValue, url){
			clearActiveResult();
			updateSearchButtonLabel(document);
			document.dispatchEvent(new CustomEvent("cmx:admin-list-refreshed", {
				detail: { postType: runtime.postType, url: url || "" }
			}));
			window.setTimeout(function(){
				var input = getSearchInput(document);
				if (!input) return;
				input.value = String(expectedValue || "");
				if (typeof input.focus === "function") {
					input.focus({ preventScroll: true });
				}
				if (typeof input.setSelectionRange === "function") {
					var pos = input.value.length;
					input.setSelectionRange(pos, pos);
				}
			}, 0);
		}

		function applyResponse(html, expectedValue, url){
			var parser = new DOMParser();
			var nextDoc = parser.parseFromString(String(html || ""), "text/html");
			var nextForm = nextDoc.getElementById("posts-filter");
			var currentForm = getPostsFilter();
			if (!nextForm || !currentForm) {
				window.location.assign(url);
				return;
			}

			currentForm.innerHTML = nextForm.innerHTML;
			replaceRefreshFragments(nextDoc);
			if (window.history && typeof window.history.replaceState === "function") {
				window.history.replaceState({ cmxQuickSearch: true }, "", url);
			}
			afterRefresh(expectedValue, url);
		}

		function loadSearchResults(query){
			if (!runtime.enabled) return;

			var url = buildSearchUrl(query);
			runtime.requestId += 1;
			var requestId = runtime.requestId;

			if (runtime.controller && typeof runtime.controller.abort === "function") {
				runtime.controller.abort();
			}
			runtime.controller = ("AbortController" in window) ? new AbortController() : null;
			setBusy(true);

			fetch(url, {
				credentials: "same-origin",
				headers: { "X-Requested-With": "XMLHttpRequest" },
				signal: runtime.controller ? runtime.controller.signal : undefined
			})
				.then(function(response){
					if (!response.ok) {
						throw new Error("request_failed");
					}
					return response.text();
				})
				.then(function(html){
					if (requestId !== runtime.requestId) return;
					applyResponse(html, query, url);
				})
				.catch(function(error){
					if (error && error.name === "AbortError") return;
					window.location.assign(url);
				})
				.finally(function(){
					if (requestId === runtime.requestId) {
						setBusy(false);
					}
				});
		}

		function scheduleSearch(query){
			if (!runtime.enabled) return;
			window.clearTimeout(runtime.timer);
			runtime.timer = window.setTimeout(function(){
				loadSearchResults(query);
			}, 220);
		}

		if (runtime.bound) {
			updateSearchButtonLabel(document);
			return;
		}

		runtime.bound = true;

		document.addEventListener("input", function(event){
			var target = event.target;
			if (!(target instanceof HTMLInputElement) || target.name !== "s") {
				return;
			}
			var form = target.closest("#posts-filter");
			if (!form) {
				return;
			}
			clearActiveResult();
			updateSearchButtonLabel(document);
			scheduleSearch(target.value || "");
		});

		document.addEventListener("keydown", function(event){
			var target = event.target;
			if (!(target instanceof HTMLInputElement) || target.name !== "s") {
				return;
			}
			if (!target.closest("#posts-filter")) {
				return;
			}

			if (event.key === "ArrowDown" || event.key === "Down") {
				if (!runtime.enabled || !getResultRows().length) {
					return;
				}
				event.preventDefault();
				moveActiveResult(1);
				return;
			}

			if (event.key === "ArrowUp" || event.key === "Up") {
				if (!runtime.enabled || !getResultRows().length) {
					return;
				}
				event.preventDefault();
				moveActiveResult(-1);
				return;
			}

			if (event.key === "Escape" || event.key === "Esc") {
				if (!runtime.enabled) {
					return;
				}
				event.preventDefault();
				clearActiveResult();
				focusAndSelectSearchInput(target);
				return;
			}

			if (event.key !== "Enter" || !runtime.enabled) {
				return;
			}

			var activeLink = getActiveResultLink();
			if (activeLink && activeLink.href) {
				event.preventDefault();
				window.location.assign(activeLink.href);
				return;
			}

			event.preventDefault();
			window.clearTimeout(runtime.timer);
			loadSearchResults(target.value || "");
		});

		document.addEventListener("focusin", function(event){
			var target = event.target;
			if (!(target instanceof HTMLInputElement) || target.name !== "s") {
				return;
			}
			if (!target.closest("#posts-filter")) {
				return;
			}
			updateSearchButtonLabel(document);
		});

		document.addEventListener("click", function(event){
			var button = event.target instanceof Element ? event.target.closest("#search-submit") : null;
			if (!button) {
				var titleLink = event.target instanceof Element ? event.target.closest("#the-list tr a.row-title, #the-list tr td.title strong a, #the-list tr strong a") : null;
				if (!titleLink) {
					return;
				}
				var row = titleLink.closest("tr");
				if (!(row instanceof HTMLTableRowElement)) {
					return;
				}
				var rows = getResultRows();
				var index = rows.indexOf(row);
				if (index !== -1) {
					setActiveResult(index);
				}
				return;
			}
			updateSearchButtonLabel(document);
			if (!runtime.enabled) {
				return;
			}
			event.preventDefault();
			var input = getSearchInput(document);
			window.clearTimeout(runtime.timer);
			loadSearchResults(input ? (input.value || "") : "");
		});

		document.addEventListener("submit", function(event){
			var form = event.target instanceof HTMLFormElement ? event.target : null;
			if (!form || form.id !== "posts-filter") {
				return;
			}
			updateSearchButtonLabel(document);
			if (!runtime.enabled) {
				return;
			}
			var submitter = event.submitter || null;
			var searchInput = form.querySelector('input[name="s"]');
			var triggerSearch = !!(submitter && submitter.id === "search-submit");
			if (!triggerSearch && document.activeElement === searchInput) {
				triggerSearch = true;
			}
			if (!triggerSearch) {
				return;
			}
			event.preventDefault();
			window.clearTimeout(runtime.timer);
			loadSearchResults(searchInput ? (searchInput.value || "") : "");
		});

		updateSearchButtonLabel(document);
	})();
	</script>
	<?php
}, 999);

\add_action('admin_footer-post.php', __NAMESPACE__ . '\\cmx_print_cpt_double_escape_back_to_list');
\add_action('admin_footer-post-new.php', __NAMESPACE__ . '\\cmx_print_cpt_double_escape_back_to_list');

function cmx_print_cpt_double_escape_back_to_list(): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen) {
		return;
	}

	$post_type = (string) ($screen->post_type ?? '');
	if ($post_type === '' || !\post_type_exists($post_type)) {
		return;
	}

	$post_type_object = \get_post_type_object($post_type);
	if (!$post_type_object || !empty($post_type_object->_builtin) || empty($post_type_object->show_ui)) {
		return;
	}

	$list_url = (string) \admin_url('edit.php?post_type=' . \rawurlencode($post_type));
	?>
	<script>
	(function(){
		var targetUrl = <?php echo \wp_json_encode($list_url); ?>;
		if (!targetUrl) {
			return;
		}

		var runtime = window.cmxAdminDoubleEscapeToList = window.cmxAdminDoubleEscapeToList || {
			bound: false,
			lastEscapeAt: 0,
			boundDocs: [],
			tinyMceHooked: false
		};

		if (runtime.bound) {
			return;
		}
		runtime.bound = true;

		function getSaveButton(){
			return document.querySelector(
				'#submitdiv #publish:not([disabled]), #submitdiv #save-post:not([disabled]), #publishing-action #publish:not([disabled]), #publishing-action #save-post:not([disabled]), #publish:not([disabled]), #save-post:not([disabled])'
			);
		}

		function isTextareaTarget(target){
			return !!(target && target.nodeType === 1 && String(target.nodeName || "").toLowerCase() === "textarea");
		}

		function isWithinContentEditable(target){
			var current = target && target.nodeType === 3 ? target.parentNode : target;
			while (current && current.nodeType === 1) {
				if (current.isContentEditable) {
					return true;
				}
				current = current.parentNode;
			}
			return false;
		}

		function handleKeydown(event){
			var target = event.target || null;

			if (
				(isTextareaTarget(target) || isWithinContentEditable(target))
				&& (event.ctrlKey || event.metaKey)
				&& !event.altKey
				&& !event.shiftKey
				&& !event.repeat
				&& !event.isComposing
				&& (event.key === "Enter" || event.key === "NumpadEnter")
			) {
				var saveButton = getSaveButton();
				if (saveButton) {
					event.preventDefault();
					event.stopPropagation();
					saveButton.click();
				}
				return;
			}

			if (!event || (event.key !== "Escape" && event.key !== "Esc")) {
				return;
			}
			if (event.repeat || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey || event.isComposing) {
				return;
			}

			var now = Date.now();
			var elapsed = now - (runtime.lastEscapeAt || 0);
			runtime.lastEscapeAt = now;

			if (elapsed > 0 && elapsed <= 1000) {
				event.preventDefault();
				event.stopPropagation();
				window.location.assign(targetUrl);
			}
		}

		function bindKeydownDocument(doc){
			if (!doc || typeof doc.addEventListener !== "function") {
				return;
			}
			if (runtime.boundDocs.indexOf(doc) !== -1) {
				return;
			}
			runtime.boundDocs.push(doc);
			doc.addEventListener("keydown", handleKeydown, true);
		}

		function bindTinyMceEditor(editor){
			if (!editor) {
				return;
			}

			function tryBindEditorDoc(){
				var editorDoc = null;
				try {
					editorDoc = typeof editor.getDoc === "function" ? editor.getDoc() : null;
				} catch (error) {
					editorDoc = null;
				}
				if (editorDoc) {
					bindKeydownDocument(editorDoc);
				}
			}

			if (typeof editor.on === "function") {
				editor.on("init", tryBindEditorDoc);
				editor.on("focus", tryBindEditorDoc);
			}
			tryBindEditorDoc();
		}

		function initTinyMceBindings(){
			if (runtime.tinyMceHooked || !window.tinymce) {
				return;
			}
			runtime.tinyMceHooked = true;

			if (Array.isArray(window.tinymce.editors)) {
				window.tinymce.editors.forEach(bindTinyMceEditor);
			}

			if (typeof window.tinymce.on === "function") {
				window.tinymce.on("AddEditor", function(event){
					if (event && event.editor) {
						bindTinyMceEditor(event.editor);
					}
				});
			}
		}

		bindKeydownDocument(document);
		initTinyMceBindings();
		window.addEventListener("load", initTinyMceBindings, { once: true });
		window.setTimeout(initTinyMceBindings, 250);
	})();
	</script>
	<?php
}
