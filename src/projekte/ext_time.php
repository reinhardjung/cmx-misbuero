<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_EXT_TIME_USER_TOKEN_META')) {
	\define(__NAMESPACE__ . '\\CMX_EXT_TIME_USER_TOKEN_META', '_cmx_ext_time_token');
}

if (!\defined(__NAMESPACE__ . '\\CMX_EXT_TIME_DOWNLOAD_ACTION')) {
	\define(__NAMESPACE__ . '\\CMX_EXT_TIME_DOWNLOAD_ACTION', 'cmx_ext_time_download');
}

if (!\defined(__NAMESPACE__ . '\\CMX_EXT_TIME_DOWNLOAD_CRX_ACTION')) {
	\define(__NAMESPACE__ . '\\CMX_EXT_TIME_DOWNLOAD_CRX_ACTION', 'cmx_ext_time_download_crx');
}

if (!\defined(__NAMESPACE__ . '\\CMX_EXT_TIME_CONNECT_ACTION')) {
	\define(__NAMESPACE__ . '\\CMX_EXT_TIME_CONNECT_ACTION', 'cmx_ext_time_connect');
}

if (!\defined(__NAMESPACE__ . '\\CMX_EXT_TIME_BOOTSTRAP_ACTION')) {
	\define(__NAMESPACE__ . '\\CMX_EXT_TIME_BOOTSTRAP_ACTION', 'cmx_ext_time_bootstrap');
}

if (!\defined(__NAMESPACE__ . '\\CMX_EXT_TIME_SEARCH_PROJECTS_ACTION')) {
	\define(__NAMESPACE__ . '\\CMX_EXT_TIME_SEARCH_PROJECTS_ACTION', 'cmx_ext_time_search_projects');
}

if (!\defined(__NAMESPACE__ . '\\CMX_EXT_TIME_SEARCH_CONTACTS_ACTION')) {
	\define(__NAMESPACE__ . '\\CMX_EXT_TIME_SEARCH_CONTACTS_ACTION', 'cmx_ext_time_search_contacts');
}

if (!\defined(__NAMESPACE__ . '\\CMX_EXT_TIME_SEARCH_ARTICLES_ACTION')) {
	\define(__NAMESPACE__ . '\\CMX_EXT_TIME_SEARCH_ARTICLES_ACTION', 'cmx_ext_time_search_articles');
}

if (!\defined(__NAMESPACE__ . '\\CMX_EXT_TIME_SAVE_ACTION')) {
	\define(__NAMESPACE__ . '\\CMX_EXT_TIME_SAVE_ACTION', 'cmx_ext_time_save');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_plugin_version')) {
	function cmx_ext_time_plugin_version(): string {
		$version = \defined(__NAMESPACE__ . '\\CMX_VERSION') ? (string) \constant(__NAMESPACE__ . '\\CMX_VERSION') : '2.6.5';
		if (!\preg_match('/^\d+(?:\.\d+){0,3}$/', $version)) {
			$version = '2.6.5';
		}
		return $version;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_interval_values')) {
	function cmx_ext_time_interval_values(): array {
		return [5, 10, 15, 20, 30, 45, 60];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_default_interval')) {
	function cmx_ext_time_default_interval(): int {
		$options = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			? (array) \get_option((string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN'), [])
			: [];
		$value = isset($options['task_Intervall']) ? (int) $options['task_Intervall'] : 5;
		return \in_array($value, cmx_ext_time_interval_values(), true) ? $value : 5;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_icon_asset_path')) {
	function cmx_ext_time_icon_asset_path(): string {
		return \wp_normalize_path(\dirname(__DIR__, 2) . '/assets/ext_time.png');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_icon_asset_url')) {
	function cmx_ext_time_icon_asset_url(): string {
		return (string) \plugins_url('assets/ext_time.png', \dirname(__DIR__, 2) . '/cmx-misbuero.php');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_icon_png')) {
	function cmx_ext_time_icon_png(int $size): string {
		$size = \max(16, (int) $size);
		$asset_path = cmx_ext_time_icon_asset_path();
		if (\is_file($asset_path)) {
			$source = @\imagecreatefrompng($asset_path);
			if ($source instanceof \GdImage || \is_resource($source)) {
				$src_w = (int) \imagesx($source);
				$src_h = (int) \imagesy($source);
				if ($src_w > 0 && $src_h > 0) {
					$crop_x1 = $src_w;
					$crop_y1 = $src_h;
					$crop_x2 = -1;
					$crop_y2 = -1;
					for ($y = 0; $y < $src_h; $y++) {
						for ($x = 0; $x < $src_w; $x++) {
							$rgba = (int) \imagecolorat($source, $x, $y);
							$alpha = ($rgba >> 24) & 0x7F;
							if ($alpha >= 120) {
								continue;
							}
							if ($x < $crop_x1) $crop_x1 = $x;
							if ($y < $crop_y1) $crop_y1 = $y;
							if ($x > $crop_x2) $crop_x2 = $x;
							if ($y > $crop_y2) $crop_y2 = $y;
						}
					}
					if ($crop_x2 < $crop_x1 || $crop_y2 < $crop_y1) {
						$crop_x1 = 0;
						$crop_y1 = 0;
						$crop_x2 = $src_w - 1;
						$crop_y2 = $src_h - 1;
					}
					$crop_w = \max(1, $crop_x2 - $crop_x1 + 1);
					$crop_h = \max(1, $crop_y2 - $crop_y1 + 1);
					$pad = \max(1, (int) \round(\min($crop_w, $crop_h) * 0.04));
					$crop_x1 = \max(0, $crop_x1 - $pad);
					$crop_y1 = \max(0, $crop_y1 - $pad);
					$crop_x2 = \min($src_w - 1, $crop_x2 + $pad);
					$crop_y2 = \min($src_h - 1, $crop_y2 + $pad);
					$crop_w = \max(1, $crop_x2 - $crop_x1 + 1);
					$crop_h = \max(1, $crop_y2 - $crop_y1 + 1);

					$image = \imagecreatetruecolor($size, $size);
					\imagealphablending($image, false);
					\imagesavealpha($image, true);
					$transparent = \imagecolorallocatealpha($image, 0, 0, 0, 127);
					\imagefill($image, 0, 0, $transparent);
					\imagecopyresampled($image, $source, 0, 0, $crop_x1, $crop_y1, $size, $size, $crop_w, $crop_h);
					\ob_start();
					\imagepng($image);
					$png = (string) \ob_get_clean();
					\imagedestroy($image);
					\imagedestroy($source);
					if ($png !== '') {
						return $png;
					}
				}
				\imagedestroy($source);
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_preview_icon_src')) {
	function cmx_ext_time_preview_icon_src(): string {
		$png = cmx_ext_time_icon_png(128);
		if ($png !== '') {
			return 'data:image/png;base64,' . \base64_encode($png);
		}

		return cmx_ext_time_icon_asset_url();
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_current_user_token')) {
	function cmx_ext_time_current_user_token(int $user_id = 0): string {
		$user_id = $user_id > 0 ? $user_id : \get_current_user_id();
		if ($user_id <= 0) {
			return '';
		}

		$token = \trim((string) \get_user_meta($user_id, CMX_EXT_TIME_USER_TOKEN_META, true));
		if ($token !== '') {
			return $token;
		}

		try {
			$token = 'cmxexttime_' . \bin2hex(\random_bytes(24));
		} catch (\Throwable $exception) {
			$token = 'cmxexttime_' . \wp_generate_password(48, false, false);
		}
		\update_user_meta($user_id, CMX_EXT_TIME_USER_TOKEN_META, $token);

		return $token;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_request_token')) {
	function cmx_ext_time_request_token(): string {
		$server = (array) ($_SERVER ?? []);
		$token = '';
		foreach (['HTTP_X_CMX_EXTENSION_TOKEN', 'REDIRECT_HTTP_X_CMX_EXTENSION_TOKEN'] as $key) {
			if (!empty($server[$key])) {
				$token = (string) $server[$key];
				break;
			}
		}
		if ($token === '' && isset($_REQUEST['token'])) {
			$token = (string) \wp_unslash($_REQUEST['token']);
		}
		return \trim($token);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_token_user_id')) {
	function cmx_ext_time_token_user_id(string $token): int {
		$token = \trim($token);
		if ($token === '') {
			return 0;
		}

		$user_ids = \get_users([
			'meta_key'   => CMX_EXT_TIME_USER_TOKEN_META,
			'meta_value' => $token,
			'number'     => 1,
			'fields'     => 'ID',
		]);
		if (!\is_array($user_ids) || empty($user_ids[0])) {
			return 0;
		}

		$user_id = (int) $user_ids[0];
		$user = \get_userdata($user_id);
		if (!$user instanceof \WP_User) {
			return 0;
		}

		return \user_can($user, 'edit_posts') ? $user_id : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_authenticated_user_id')) {
	function cmx_ext_time_authenticated_user_id(bool $allow_token = true): int {
		$current_user_id = \get_current_user_id();
		if ($current_user_id > 0 && \current_user_can('edit_posts')) {
			return (int) $current_user_id;
		}

		if (!$allow_token) {
			return 0;
		}

		return cmx_ext_time_token_user_id(cmx_ext_time_request_token());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_admin_ajax_url')) {
	function cmx_ext_time_admin_ajax_url(string $action = ''): string {
		$url = (string) \admin_url('admin-ajax.php');
		if ($action !== '') {
			$url = (string) \add_query_arg(['action' => $action], $url);
		}
		return $url;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_download_url')) {
	function cmx_ext_time_download_url(): string {
		return (string) \add_query_arg(
			[
				'action'   => CMX_EXT_TIME_DOWNLOAD_ACTION,
				'_wpnonce' => \wp_create_nonce(CMX_EXT_TIME_DOWNLOAD_ACTION),
			],
			\admin_url('admin-post.php')
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_download_crx_url')) {
	function cmx_ext_time_download_crx_url(): string {
		return (string) \add_query_arg(
			[
				'action'   => CMX_EXT_TIME_DOWNLOAD_CRX_ACTION,
				'_wpnonce' => \wp_create_nonce(CMX_EXT_TIME_DOWNLOAD_CRX_ACTION),
			],
			\admin_url('admin-post.php')
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_crx_chrome_binary')) {
	function cmx_ext_time_crx_chrome_binary(): string {
		foreach ([
			'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
			'/Applications/Chromium.app/Contents/MacOS/Chromium',
		] as $candidate) {
			$candidate = \wp_normalize_path((string) $candidate);
			if ($candidate !== '' && \is_file($candidate) && \is_executable($candidate)) {
				return $candidate;
			}
		}

		if (!\function_exists('exec')) {
			return '';
		}

		foreach (['google-chrome', 'chromium', 'chromium-browser', 'chrome'] as $binary) {
			$output = [];
			$exit_code = 1;
			@exec('command -v ' . \escapeshellarg($binary) . ' 2>/dev/null', $output, $exit_code);
			if ($exit_code === 0 && !empty($output[0])) {
				return \trim((string) $output[0]);
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_crx_supported')) {
	function cmx_ext_time_crx_supported(): bool {
		return cmx_ext_time_crx_chrome_binary() !== ''
			&& \function_exists('exec')
			&& \function_exists('openssl_pkey_new')
			&& \function_exists('openssl_pkey_export');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_crx_private_key_pem')) {
	function cmx_ext_time_crx_private_key_pem(): string {
		$option_key = 'cmx_ext_time_crx_private_key_pem';
		$pem = \trim((string) \get_option($option_key, ''));
		if (\strpos($pem, 'BEGIN') !== false && \strpos($pem, 'PRIVATE KEY') !== false) {
			return $pem;
		}

		if (!\function_exists('openssl_pkey_new') || !\function_exists('openssl_pkey_export')) {
			return '';
		}

		$key = \openssl_pkey_new([
			'private_key_type' => \OPENSSL_KEYTYPE_RSA,
			'private_key_bits' => 2048,
		]);
		if ($key === false) {
			return '';
		}

		$pem = '';
		if (!\openssl_pkey_export($key, $pem) || \trim($pem) === '') {
			return '';
		}

		if (\get_option($option_key, null) === null) {
			\add_option($option_key, $pem, '', 'no');
		} else {
			\update_option($option_key, $pem, false);
		}

		return $pem;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_crx_remove_dir')) {
	function cmx_ext_time_crx_remove_dir(string $path): void {
		$path = \wp_normalize_path($path);
		if ($path === '' || !\file_exists($path)) {
			return;
		}
		if (\is_file($path) || \is_link($path)) {
			@unlink($path);
			return;
		}

		$items = \scandir($path);
		if (\is_array($items)) {
			foreach ($items as $item) {
				if ($item === '.' || $item === '..') {
					continue;
				}
				cmx_ext_time_crx_remove_dir($path . '/' . $item);
			}
		}
		@rmdir($path);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_readme_txt')) {
	function cmx_ext_time_readme_txt(): string {
		return "Mis Büro - Google Chrome Erweiterung für Zeiterfassung\n\n"
			. "Installation:\n"
			. "1. ZIP-Datei herunterladen und entpacken.\n"
			. "2. In Google Chrome 'chrome://extensions' öffnen.\n"
			. "3. Entwicklermodus aktivieren.\n"
			. "4. 'Entpackte Erweiterung laden' wählen.\n"
			. "5. Den entpackten Ordner auswählen.\n\n"
			. "Verwendung:\n"
			. "- Über das Zahnrad zuerst die Mis Büro Instanz verbinden.\n"
			. "- Projekt oder Kontakt sowie Tätigkeit oder Notiz auswählen.\n"
			. "- Mit Start den Timer starten.\n"
			. "- Nach Ablauf des Intervalls fragt die Erweiterung, ob Du noch arbeitest.\n"
			. "- Bei Stop oder Timeout wird die Zeit direkt im Projekt gespeichert.\n";
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_add_settings_field')) {
	function cmx_ext_time_add_settings_field(): void {
		\add_settings_field(
			'cmx_ext_time',
			'Google Chrome Erweiterung',
			__NAMESPACE__ . '\\cmx_ext_time_render_settings_field',
			'cmx_tab_vorgaben__projekte',
			'cmx_sec_vorgaben_projekte'
		);
	}
	\add_action('admin_init', __NAMESPACE__ . '\\cmx_ext_time_add_settings_field');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_render_settings_field')) {
	function cmx_ext_time_render_settings_field(): void {
		if (!\current_user_can('manage_options')) {
			echo '<em>Keine Berechtigung.</em>';
			return;
		}

		$download_url = cmx_ext_time_download_url();
		$download_crx_url = cmx_ext_time_download_crx_url();
		$crx_supported = cmx_ext_time_crx_supported();
		$icon_src = cmx_ext_time_preview_icon_src();
		$readme_text = cmx_ext_time_readme_txt();

		echo '<div id="cmx-ext-time-wrap" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">';
		echo '<button type="button" class="button button-secondary" id="cmx-ext-time-install" aria-label="Google Chrome Erweiterung herunterladen" style="width:52px;height:52px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:14px;overflow:hidden;">';
		echo '<img src="' . \esc_attr($icon_src) . '" alt="" width="42" height="42" style="display:block;width:42px;height:42px;object-fit:contain;">';
		echo '</button>';
		echo '<div style="min-width:260px;">';
		echo '<div style="font-weight:600;">Mis Büro - Zeiterfassung</div>';
		echo '<div style="color:#646970;">Lädt das Erweiterungspaket für Google Chrome herunter.</div>';
		echo '<div style="margin-top:4px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">';
		echo '<button type="button" class="button-link" id="cmx-ext-time-help" style="padding:0;height:auto;min-height:0;">Anleitung</button>';
		if ($crx_supported) {
			echo '<button type="button" class="button-link" id="cmx-ext-time-install-crx" style="padding:0;height:auto;min-height:0;">CRX laden</button>';
		}
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '<div id="cmx-ext-time-help-box" style="display:none;margin-top:8px;padding:12px 14px;border:1px solid #dcdcde;border-radius:8px;background:#fff;">';
		echo '<pre style="margin:0;white-space:pre-wrap;font-family:monospace;">' . \esc_html($readme_text) . '</pre>';
		echo '</div>';
		echo '<div id="cmx-ext-time-status" style="margin-top:8px;color:#646970;"></div>';
		echo '<script>
		(function(){
			var button = document.getElementById("cmx-ext-time-install");
			var crxButton = document.getElementById("cmx-ext-time-install-crx");
			var help = document.getElementById("cmx-ext-time-help");
			var helpBox = document.getElementById("cmx-ext-time-help-box");
			var status = document.getElementById("cmx-ext-time-status");
			if (!button || !status) return;
			function setStatus(text, isError){
				status.textContent = text || "";
				status.style.color = isError ? "#b32d2e" : "#646970";
			}
			function setStatusHtml(html, isError){
				status.innerHTML = html || "";
				status.style.color = isError ? "#b32d2e" : "#646970";
			}
			function fallbackCopyText(text){
				var field = document.createElement("textarea");
				field.value = text;
				field.setAttribute("readonly", "readonly");
				field.style.position = "fixed";
				field.style.opacity = "0";
				field.style.pointerEvents = "none";
				document.body.appendChild(field);
				field.focus();
				field.select();
				var ok = false;
				try {
					ok = document.execCommand("copy");
				} catch (err) {
					ok = false;
				}
				document.body.removeChild(field);
				return ok;
			}
			function copyText(text){
				if (navigator.clipboard && window.isSecureContext) {
					return navigator.clipboard.writeText(text).then(function(){
						return true;
					}).catch(function(){
						return fallbackCopyText(text);
					});
				}
				return Promise.resolve(fallbackCopyText(text));
			}
			function isChromeBrowser(){
				var ua = navigator.userAgent || "";
				var vendor = navigator.vendor || "";
				if (navigator.userAgentData && Array.isArray(navigator.userAgentData.brands)) {
					var hasChromeBrand = navigator.userAgentData.brands.some(function(entry){
						return /Google Chrome|Chromium/i.test((entry && entry.brand) || "");
					});
					if (hasChromeBrand) return true;
				}
				return /Chrome\\//.test(ua) && /Google Inc/i.test(vendor) && !/Edg\\//.test(ua) && !/OPR\\//.test(ua);
			}
			if (help && helpBox) {
				help.addEventListener("click", function(){
					var isOpen = helpBox.style.display !== "none";
					helpBox.style.display = isOpen ? "none" : "block";
				});
			}
			button.addEventListener("click", function(){
				if (!isChromeBrowser()) {
					setStatus("Diese Erweiterung kann nur in Google Chrome heruntergeladen werden.", true);
					return;
				}
				copyText("chrome://extensions").then(function(copied){
					var message = "Das Erweiterungspaket wird heruntergeladen.<br>Bitte danach in <code>chrome://extensions</code> als entpackte Erweiterung laden.";
					if (copied) {
						message += "<br><code>chrome://extensions</code> wurde in die Zwischenablage kopiert.";
					}
					setStatusHtml(message, false);
					window.location.href = ' . \wp_json_encode($download_url) . ';
				});
			});
			if (crxButton) {
				crxButton.addEventListener("click", function(){
					if (!isChromeBrowser()) {
						setStatus("Diese Erweiterung kann nur in Google Chrome heruntergeladen werden.", true);
						return;
					}
					copyText("chrome://extensions").then(function(copied){
						var message = "Die <code>.crx</code>-Datei wird heruntergeladen.";
						if (copied) {
							message += "<br><code>chrome://extensions</code> wurde in die Zwischenablage kopiert.";
						}
						message += "<br>Falls Chrome die direkte Installation blockiert, bitte weiter die ZIP-Datei als entpackte Erweiterung laden.";
						setStatusHtml(message, false);
						window.location.href = ' . \wp_json_encode($download_crx_url) . ';
					});
				});
			}
		})();
		</script>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_project_date_meta_keys')) {
	function cmx_ext_time_project_date_meta_keys(): array {
		$begin_key = \defined(__NAMESPACE__ . '\\CMX_PROJ_BEG_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_PROJ_BEG_META')
			: '_cmx_projekt_beginn';
		$end_key = \defined(__NAMESPACE__ . '\\CMX_PROJ_END_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_PROJ_END_META')
			: '_cmx_projekt_ende';

		return [$begin_key, $end_key];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_task_taxonomy')) {
	function cmx_ext_time_task_taxonomy(): ?string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_projekte_detect_task_taxonomy')) {
			$tax = (string) cmx_projekte_detect_task_taxonomy();
			if ($tax !== '' && \taxonomy_exists($tax) && \is_object_in_taxonomy('projekte', $tax)) {
				return $tax;
			}
		}

		if (\defined(__NAMESPACE__ . '\\TAX_PROJEKTE_AUFGABEN')) {
			$tax = (string) \constant(__NAMESPACE__ . '\\TAX_PROJEKTE_AUFGABEN');
			if ($tax !== '' && \taxonomy_exists($tax) && \is_object_in_taxonomy('projekte', $tax)) {
				return $tax;
			}
		}

		foreach (['projekte_aufgaben', 'projekte_aufgabe', 'aufgaben', 'aufgabe'] as $candidate) {
			if (\taxonomy_exists($candidate) && \is_object_in_taxonomy('projekte', $candidate)) {
				return $candidate;
			}
		}

		return null;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_project_task_names')) {
	function cmx_ext_time_project_task_names(int $project_id): array {
		$tax = cmx_ext_time_task_taxonomy();
		if ($project_id <= 0 || $tax === null || $tax === '') {
			return [];
		}

		$terms = \get_the_terms($project_id, $tax);
		if (empty($terms) || \is_wp_error($terms)) {
			return [];
		}

		$names = [];
		foreach ($terms as $term) {
			$name = \trim((string) ($term->name ?? ''));
			if ($name !== '') {
				$names[] = $name;
			}
		}

		return \array_values(\array_unique($names));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_active_projects')) {
	function cmx_ext_time_active_projects(int $limit = 300): array {
		[$begin_key, $end_key] = cmx_ext_time_project_date_meta_keys();
		$post_ids = \get_posts([
			'post_type'      => 'projekte',
			'posts_per_page' => $limit > 0 ? $limit : -1,
			'post_status'    => ['publish', 'private', 'draft'],
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'     => $begin_key,
					'value'   => '',
					'compare' => '!=',
				],
			],
		]);

		$today = \current_time('timestamp');
		$out = [];

		foreach ((array) $post_ids as $project_id) {
			$project_id = (int) $project_id;
			if ($project_id <= 0) {
				continue;
			}

			$begin = (string) \get_post_meta($project_id, $begin_key, true);
			$end = (string) \get_post_meta($project_id, $end_key, true);
			$begin_ts = $begin !== '' ? (int) \strtotime($begin) : 0;
			$end_ts = $end !== '' ? (int) \strtotime($end) : 0;

			if ($begin_ts <= 0) {
				continue;
			}
			if ($end_ts > 0 && $today > $end_ts) {
				continue;
			}

			$title = (string) \get_the_title($project_id);
			if ($title === '') {
				$title = '(#' . $project_id . ')';
			}
			$task_names = cmx_ext_time_project_task_names($project_id);
			$task_label = !empty($task_names) ? \implode(', ', $task_names) : '';
			$label = $task_label !== '' ? ($title . ' - ' . $task_label) : $title;

			$out[] = [
				'id'         => $project_id,
				'title'      => $title,
				'label'      => $label,
				'entity_type'=> 'project',
				'task_names' => $task_names,
				'beginn'     => $begin,
				'ende'       => $end,
			];
		}

		return $out;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_active_contacts')) {
	function cmx_ext_time_contacts_post_types(): array {
		$candidates = [];

		if (\function_exists(__NAMESPACE__ . '\\cmx_kontakte_cpt')) {
			$cpt = (string) cmx_kontakte_cpt();
			if ($cpt !== '') {
				$candidates[] = $cpt;
			}
		}

		$candidates = \array_merge($candidates, ['kontakte', 'kontakt', 'contacts', 'contact']);

		foreach ((array) \get_post_types([], 'names') as $post_type) {
			$post_type = (string) $post_type;
			if ($post_type !== '' && (\stripos($post_type, 'kontakt') !== false || \stripos($post_type, 'contact') !== false)) {
				$candidates[] = $post_type;
			}
		}

		$candidates = \array_values(\array_unique(\array_filter(\array_map('strval', $candidates))));
		$existing = [];
		foreach ($candidates as $candidate) {
			if (\post_type_exists($candidate)) {
				$existing[] = $candidate;
			}
		}

		return !empty($existing) ? $existing : ['kontakte'];
	}

	function cmx_ext_time_contacts_post_type(): string {
		$post_types = cmx_ext_time_contacts_post_types();
		return (string) ($post_types[0] ?? 'kontakte');
	}

	function cmx_ext_time_is_contact_post_type(string $post_type): bool {
		return \in_array($post_type, cmx_ext_time_contacts_post_types(), true);
	}

	function cmx_ext_time_active_contacts(int $limit = 300): array {
		global $wpdb;

		$post_types = cmx_ext_time_contacts_post_types();
		$limit = $limit > 0 ? $limit : 300;
		$placeholders = \implode(',', \array_fill(0, \count($post_types), '%s'));
		$params = \array_merge($post_types, [$limit]);

		$sql = $wpdb->prepare(
			"SELECT ID
			 FROM {$wpdb->posts}
			 WHERE post_type IN ($placeholders)
			   AND post_status NOT IN ('trash', 'auto-draft')
			 ORDER BY post_title ASC, ID ASC
			 LIMIT %d",
			$params
		);
		$post_ids = $wpdb->get_col($sql);

		$out = [];
		foreach ((array) $post_ids as $contact_id) {
			$contact_id = (int) $contact_id;
			if ($contact_id <= 0) {
				continue;
			}

			$title = \trim((string) \get_the_title($contact_id));
			if ($title === '') {
				$title = '(#' . $contact_id . ')';
			}

			$out[] = [
				'id'          => $contact_id,
				'title'       => $title,
				'label'       => $title,
				'entity_type' => 'contact',
			];
		}

		return $out;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_bootstrap_data')) {
	function cmx_ext_time_bootstrap_data(int $user_id = 0): array {
		$user_id = $user_id > 0 ? $user_id : \get_current_user_id();
		$user = $user_id > 0 ? \get_userdata($user_id) : null;
		$display_name = '';
		if ($user instanceof \WP_User) {
			$display_name = \trim((string) $user->display_name);
			if ($display_name === '') {
				$display_name = (string) $user->user_login;
			}
		}

		return [
			'siteUrl'         => (string) \home_url('/'),
			'siteName'        => (string) \get_bloginfo('name'),
			'ajaxUrl'         => cmx_ext_time_admin_ajax_url(),
			'token'           => cmx_ext_time_current_user_token($user_id),
			'intervals'       => cmx_ext_time_interval_values(),
			'defaultInterval' => cmx_ext_time_default_interval(),
			'userDisplay'     => $display_name,
			'projects'        => cmx_ext_time_active_projects(),
			'contacts'        => cmx_ext_time_active_contacts(),
			'supports'        => [
				'contactSave' => true,
			],
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_auth_error')) {
	function cmx_ext_time_auth_error(string $message = 'Zuerst in Mis Büro anmelden.'): void {
		\wp_send_json_error(['message' => $message], 403);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_bootstrap_handler')) {
	function cmx_ext_time_bootstrap_handler(): void {
		$user_id = cmx_ext_time_authenticated_user_id(false);
		if ($user_id <= 0) {
			cmx_ext_time_auth_error();
		}

		\wp_send_json_success(cmx_ext_time_bootstrap_data($user_id));
	}
	\add_action('wp_ajax_' . CMX_EXT_TIME_BOOTSTRAP_ACTION, __NAMESPACE__ . '\\cmx_ext_time_bootstrap_handler');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_connect_handler')) {
	function cmx_ext_time_connect_handler(): void {
		$user_id = cmx_ext_time_authenticated_user_id(true);

		if ($user_id <= 0) {
			$username = isset($_REQUEST['username']) ? \sanitize_user((string) \wp_unslash($_REQUEST['username']), true) : '';
			$password = isset($_REQUEST['password']) ? (string) \wp_unslash($_REQUEST['password']) : '';

			if ($username === '' || $password === '') {
				\wp_send_json_error(['message' => 'Bitte Benutzername und Passwort eingeben.'], 400);
			}

			$user = \wp_authenticate($username, $password);
			if ($user instanceof \WP_Error || !$user instanceof \WP_User) {
				\wp_send_json_error(['message' => 'Benutzername oder Passwort sind ungültig.'], 403);
			}
			if (!\user_can($user, 'edit_posts')) {
				\wp_send_json_error(['message' => 'Dieser Benutzer darf keine Projekte bearbeiten.'], 403);
			}

			$user_id = (int) $user->ID;
		}

		\wp_send_json_success(cmx_ext_time_bootstrap_data($user_id));
	}
	\add_action('wp_ajax_' . CMX_EXT_TIME_CONNECT_ACTION, __NAMESPACE__ . '\\cmx_ext_time_connect_handler');
	\add_action('wp_ajax_nopriv_' . CMX_EXT_TIME_CONNECT_ACTION, __NAMESPACE__ . '\\cmx_ext_time_connect_handler');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_project_search_results')) {
	function cmx_ext_time_project_search_results(string $term = ''): array {
		$term = \trim($term);
		$projects = cmx_ext_time_active_projects();
		if ($term === '') {
			return $projects;
		}

		$needle = \function_exists('mb_strtolower') ? \mb_strtolower($term, 'UTF-8') : \strtolower($term);
		$out = [];
		foreach ($projects as $project) {
			$title = (string) ($project['title'] ?? '');
			$label = (string) ($project['label'] ?? $title);
			$haystack = $title . ' ' . $label;
			$haystack = \function_exists('mb_strtolower') ? \mb_strtolower($haystack, 'UTF-8') : \strtolower($haystack);
			if (\strpos($haystack, $needle) === false) {
				continue;
			}
			$out[] = $project;
		}

		return $out;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_search_projects_handler')) {
	function cmx_ext_time_search_projects_handler(): void {
		$user_id = cmx_ext_time_authenticated_user_id(true);
		if ($user_id <= 0) {
			cmx_ext_time_auth_error('Keine Berechtigung für diese Instanz.');
		}

		$term = isset($_REQUEST['term']) ? \sanitize_text_field((string) \wp_unslash($_REQUEST['term'])) : '';
		\wp_send_json_success(cmx_ext_time_project_search_results($term));
	}
	\add_action('wp_ajax_' . CMX_EXT_TIME_SEARCH_PROJECTS_ACTION, __NAMESPACE__ . '\\cmx_ext_time_search_projects_handler');
	\add_action('wp_ajax_nopriv_' . CMX_EXT_TIME_SEARCH_PROJECTS_ACTION, __NAMESPACE__ . '\\cmx_ext_time_search_projects_handler');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_contact_search_results')) {
	function cmx_ext_time_contact_search_results(string $term = ''): array {
		$term = \trim($term);
		$contacts = cmx_ext_time_active_contacts();
		if ($term === '') {
			return $contacts;
		}

		$needle = \function_exists('mb_strtolower') ? \mb_strtolower($term, 'UTF-8') : \strtolower($term);
		$out = [];
		foreach ($contacts as $contact) {
			$title = (string) ($contact['title'] ?? '');
			$label = (string) ($contact['label'] ?? $title);
			$haystack = $title . ' ' . $label;
			$haystack = \function_exists('mb_strtolower') ? \mb_strtolower($haystack, 'UTF-8') : \strtolower($haystack);
			if (\strpos($haystack, $needle) === false) {
				continue;
			}
			$out[] = $contact;
		}

		return $out;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_search_contacts_handler')) {
	function cmx_ext_time_search_contacts_handler(): void {
		$user_id = cmx_ext_time_authenticated_user_id(true);
		if ($user_id <= 0) {
			cmx_ext_time_auth_error('Keine Berechtigung für diese Instanz.');
		}

		$term = isset($_REQUEST['term']) ? \sanitize_text_field((string) \wp_unslash($_REQUEST['term'])) : '';
		\wp_send_json_success(cmx_ext_time_contact_search_results($term));
	}
	\add_action('wp_ajax_' . CMX_EXT_TIME_SEARCH_CONTACTS_ACTION, __NAMESPACE__ . '\\cmx_ext_time_search_contacts_handler');
	\add_action('wp_ajax_nopriv_' . CMX_EXT_TIME_SEARCH_CONTACTS_ACTION, __NAMESPACE__ . '\\cmx_ext_time_search_contacts_handler');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_article_search_results')) {
	function cmx_ext_time_article_search_results(string $term = '', string $art = ''): array {
		global $wpdb;

		$term = \trim($term);
		$art = \sanitize_key($art);
		$allowed_art_values = ['produkt', 'dienstleistung'];
		$art_filter = \in_array($art, $allowed_art_values, true) ? $art : '';
		$limit = 20;

		$post_tbl = $wpdb->posts;
		$meta_tbl = $wpdb->postmeta;

		$art_join = '';
		$art_where = '';
		if ($art_filter !== '') {
			$art_join = " INNER JOIN {$meta_tbl} art_meta ON art_meta.post_id = ID AND art_meta.meta_key = '_cmx_artikel_art' ";
			$art_where = $wpdb->prepare(" AND art_meta.meta_value = %s", $art_filter);
		}

		$ids = [];
		$title_match_map = [];
		$meta_match_map = [];

		if ($term === '') {
			if ($art_filter !== '') {
				$sql = $wpdb->prepare(
					"SELECT ID FROM {$post_tbl}
					 {$art_join}
					 WHERE post_type=%s AND post_status<>'trash'
					 {$art_where}
					 ORDER BY post_title ASC
					 LIMIT %d",
					'artikel',
					$limit
				);
				$ids = $wpdb->get_col($sql);
			} else {
				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$post_tbl}
						 WHERE post_type=%s AND post_status<>'trash'
						 ORDER BY post_title ASC
						 LIMIT %d",
						'artikel',
						$limit
					)
				);
			}
		} else {
			$like = '%' . $wpdb->esc_like($term) . '%';
			$norm = \preg_replace('/[\s\.\-_:]/', '', $term);
			$norm_like = '%' . $wpdb->esc_like((string) $norm) . '%';

			if ($art_filter !== '') {
				$title_sql = $wpdb->prepare(
					"SELECT ID FROM {$post_tbl}
					 {$art_join}
					 WHERE post_type=%s AND post_status<>'trash'
					   {$art_where}
					   AND post_title LIKE %s
					 ORDER BY post_title ASC
					 LIMIT %d",
					'artikel',
					$like,
					$limit
				);
				$title_ids = $wpdb->get_col($title_sql);
			} else {
				$title_ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$post_tbl}
						 WHERE post_type=%s AND post_status<>'trash'
						   AND post_title LIKE %s
						 ORDER BY post_title ASC
						 LIMIT %d",
						'artikel',
						$like,
						$limit
					)
				);
			}

			$meta_keys = ['cmx_artikel_sku', '_cmx_artikel_sku', '_cmx_artikel_nr', '_sku'];
			$in_keys = \implode(',', \array_fill(0, \count($meta_keys), '%s'));
			$params = \array_merge(
				['artikel'],
				$art_filter !== '' ? [$art_filter] : [],
				$meta_keys,
				[$like, $like, $norm_like, $limit]
			);
			$meta_sql = $wpdb->prepare(
				"SELECT DISTINCT p.ID
				 FROM {$post_tbl} p
				 " . ($art_filter !== '' ? "INNER JOIN {$meta_tbl} art_meta ON art_meta.post_id = p.ID AND art_meta.meta_key = '_cmx_artikel_art'" : '') . "
				 INNER JOIN {$meta_tbl} pm ON pm.post_id = p.ID
				 WHERE p.post_type=%s
				   AND p.post_status<>'trash'
				   " . ($art_filter !== '' ? "AND art_meta.meta_value = %s" : '') . "
				   AND pm.meta_key IN ($in_keys)
				   AND (
				     pm.meta_value LIKE %s
				     OR REPLACE(REPLACE(REPLACE(REPLACE(pm.meta_value, ' ', ''), '.', ''), '-', ''), '_', '') LIKE %s
				     OR REPLACE(REPLACE(REPLACE(REPLACE(pm.meta_value, ' ', ''), '.', ''), '-', ''), '_', '') LIKE %s
				   )
				 ORDER BY p.post_title ASC
				 LIMIT %d",
				$params
			);
			$meta_ids = $wpdb->get_col($meta_sql);

			foreach ((array) $title_ids as $title_id) {
				$title_match_map[(int) $title_id] = true;
			}
			foreach ((array) $meta_ids as $meta_id) {
				$meta_match_map[(int) $meta_id] = true;
			}
			$ids = \array_values(\array_unique(\array_merge((array) $title_ids, (array) $meta_ids)));
		}

		$out = [];
		foreach ((array) $ids as $id) {
			$id = (int) $id;
			if ($id <= 0) {
				continue;
			}
			$title = (string) \get_the_title($id);
			$nr = (string) \get_post_meta($id, '_cmx_artikel_sku', true);
			if ($nr === '') {
				$nr = (string) \get_post_meta($id, 'cmx_artikel_sku', true);
			}
			if ($nr === '') {
				$nr = (string) \get_post_meta($id, '_cmx_artikel_nr', true);
			}
			if ($nr === '') {
				$nr = (string) \get_post_meta($id, '_sku', true);
			}
			$label = $nr !== '' ? ($nr . ' – ' . $title) : $title;
			$out[] = [
				'id'        => $id,
				'title'     => $title,
				'nr'        => $nr,
				'label'     => $label,
				'title_hit' => !empty($title_match_map[$id]),
				'meta_hit'  => !empty($meta_match_map[$id]),
			];
		}

		return $out;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_search_articles_handler')) {
	function cmx_ext_time_search_articles_handler(): void {
		$user_id = cmx_ext_time_authenticated_user_id(true);
		if ($user_id <= 0) {
			cmx_ext_time_auth_error('Keine Berechtigung für diese Instanz.');
		}

		$term = isset($_REQUEST['term']) ? \sanitize_text_field((string) \wp_unslash($_REQUEST['term'])) : '';
		$art = isset($_REQUEST['art']) ? \sanitize_key((string) \wp_unslash($_REQUEST['art'])) : '';
		\wp_send_json_success(cmx_ext_time_article_search_results($term, $art));
	}
	\add_action('wp_ajax_' . CMX_EXT_TIME_SEARCH_ARTICLES_ACTION, __NAMESPACE__ . '\\cmx_ext_time_search_articles_handler');
	\add_action('wp_ajax_nopriv_' . CMX_EXT_TIME_SEARCH_ARTICLES_ACTION, __NAMESPACE__ . '\\cmx_ext_time_search_articles_handler');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_round_hours')) {
	function cmx_ext_time_round_hours(float $hours): string {
		$hours = \max(0.0, $hours);
		return \number_format($hours, 2, '.', '');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_append_task')) {
	function cmx_ext_time_append_task(int $post_id, array $payload): void {
		$existing = \get_post_meta($post_id, CMX_PROJEKT_TASK_META, true);
		$existing = \is_array($existing) ? $existing : [];

		$existing[] = [
			'uid'           => \function_exists(__NAMESPACE__ . '\\cmx_projekt_task_uid') ? cmx_projekt_task_uid('') : ('tsk_' . \uniqid()),
			'datum'         => (string) ($payload['start_date'] ?? ''),
			'zeit'          => (string) ($payload['start_time'] ?? ''),
			'dauer'         => (string) ($payload['duration'] ?? '0.00'),
			'artikel_id'    => (int) ($payload['artikel_id'] ?? 0),
			'produkt_id'    => (int) ($payload['produkt_id'] ?? 0),
			'verrechenbar'  => !empty($payload['verrechenbar']) ? 1 : 0,
			'abgerechnet'   => 0,
			'info'          => (string) ($payload['info'] ?? ''),
		];

		\update_post_meta($post_id, CMX_PROJEKT_TASK_META, $existing);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_append_note')) {
	function cmx_ext_time_append_note(int $post_id, string $post_type, array $payload): void {
		$meta_key = \function_exists(__NAMESPACE__ . '\\cmx_notizen_meta_key_for_post_type')
			? (string) cmx_notizen_meta_key_for_post_type($post_type)
			: ($post_type === 'kontakte' ? '_cmx_intern_notizen' : '_cmx_projekt_intern_notizen');
		$existing = \get_post_meta($post_id, $meta_key, true);
		$existing = \is_array($existing) ? $existing : [];

		$note_text = \trim((string) ($payload['info'] ?? ''));
		if ($note_text === '') {
			$parts = [];
			if (!empty($payload['artikel_label'])) {
				$parts[] = (string) $payload['artikel_label'];
			}
			if (!empty($payload['produkt_label'])) {
				$parts[] = (string) $payload['produkt_label'];
			}
			$note_text = \trim(\implode(' / ', $parts));
		}
		if ($note_text === '') {
			$note_text = 'Zeiterfassung per Google Chrome Erweiterung';
		}

		$existing[] = [
			'betreff' => \function_exists(__NAMESPACE__ . '\\cmx_notizen_normalize_betreff')
				? (string) cmx_notizen_normalize_betreff((string) ($payload['betreff'] ?? ''))
				: \sanitize_text_field((string) ($payload['betreff'] ?? '')),
			'datum'   => (string) ($payload['start_date'] ?? ''),
			'zeit'    => (string) ($payload['start_time'] ?? ''),
			'text'    => $note_text,
		];

		\update_post_meta($post_id, $meta_key, $existing);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_save_handler')) {
	function cmx_ext_time_save_handler(): void {
		$user_id = cmx_ext_time_authenticated_user_id(true);
		if ($user_id <= 0) {
			cmx_ext_time_auth_error('Keine Berechtigung für diese Instanz.');
		}

		$target_type = isset($_POST['target_type']) ? \sanitize_key((string) \wp_unslash($_POST['target_type'])) : 'project';
		$target_type = \in_array($target_type, ['project', 'contact'], true) ? $target_type : 'project';
		$project_id = isset($_POST['project_id']) ? (int) \wp_unslash($_POST['project_id']) : 0;
		$contact_id = isset($_POST['contact_id']) ? (int) \wp_unslash($_POST['contact_id']) : 0;
		$target_id = isset($_POST['target_id']) ? (int) \wp_unslash($_POST['target_id']) : 0;
		if ($target_id <= 0) {
			$target_id = $target_type === 'contact' ? $contact_id : $project_id;
		}

		$target_post_type = (string) \get_post_type($target_id);
		if ($target_id > 0) {
			if (cmx_ext_time_is_contact_post_type($target_post_type)) {
				$target_type = 'contact';
				$contact_id = $target_id;
			} elseif ($target_post_type === 'projekte') {
				$target_type = 'project';
				$project_id = $target_id;
			}
		}

		if ($target_type === 'contact' && ($target_id <= 0 || !cmx_ext_time_is_contact_post_type($target_post_type)) && $contact_id > 0) {
			$target_id = $contact_id;
			$target_post_type = (string) \get_post_type($target_id);
		} elseif ($target_type === 'project' && ($target_id <= 0 || $target_post_type !== 'projekte') && $project_id > 0) {
			$target_id = $project_id;
			$target_post_type = (string) \get_post_type($target_id);
		}

		if ($target_id > 0 && cmx_ext_time_is_contact_post_type($target_post_type)) {
			$target_type = 'contact';
		} elseif ($target_id > 0 && $target_post_type === 'projekte') {
			$target_type = 'project';
		}

		$is_valid_target = $target_type === 'contact'
			? ($target_id > 0 && cmx_ext_time_is_contact_post_type($target_post_type))
			: ($target_id > 0 && $target_post_type === 'projekte');
		if (!$is_valid_target) {
			\wp_send_json_error(['message' => $target_type === 'contact' ? 'Kontakt wurde nicht gefunden.' : 'Projekt wurde nicht gefunden.'], 400);
		}

		$mode = isset($_POST['mode']) ? \sanitize_key((string) \wp_unslash($_POST['mode'])) : 'task';
		$mode = \in_array($mode, ['task', 'note'], true) ? $mode : 'task';

		$start_at = isset($_POST['start_at']) ? (string) \wp_unslash($_POST['start_at']) : '';
		$end_at = isset($_POST['end_at']) ? (string) \wp_unslash($_POST['end_at']) : '';
		$start_date = isset($_POST['start_date']) ? \sanitize_text_field((string) \wp_unslash($_POST['start_date'])) : '';
		$start_time = isset($_POST['start_time']) ? \sanitize_text_field((string) \wp_unslash($_POST['start_time'])) : '';

		try {
			$start_dt = new \DateTimeImmutable($start_at !== '' ? $start_at : 'now');
			$end_dt = new \DateTimeImmutable($end_at !== '' ? $end_at : 'now');
		} catch (\Throwable $exception) {
			\wp_send_json_error(['message' => 'Start- oder Endzeit ist ungültig.'], 400);
		}

		$duration_seconds = \max(0, $end_dt->getTimestamp() - $start_dt->getTimestamp());
		$duration_hours = cmx_ext_time_round_hours($duration_seconds / 3600);

		if ($start_date === '') {
			$start_date = $start_dt->setTimezone(\wp_timezone())->format('Y-m-d');
		}
		if ($start_time === '') {
			$start_time = $start_dt->setTimezone(\wp_timezone())->format('H:i');
		}

		$payload = [
			'start_date'   => $start_date,
			'start_time'   => $start_time,
			'duration'     => $duration_hours,
			'artikel_id'   => isset($_POST['artikel_id']) ? (int) \wp_unslash($_POST['artikel_id']) : 0,
			'produkt_id'   => isset($_POST['produkt_id']) ? (int) \wp_unslash($_POST['produkt_id']) : 0,
			'verrechenbar' => !empty($_POST['verrechenbar']) ? 1 : 0,
			'info'         => isset($_POST['info']) ? \sanitize_textarea_field((string) \wp_unslash($_POST['info'])) : '',
			'betreff'      => isset($_POST['betreff']) ? \sanitize_text_field((string) \wp_unslash($_POST['betreff'])) : '',
			'artikel_label'=> isset($_POST['artikel_label']) ? \sanitize_text_field((string) \wp_unslash($_POST['artikel_label'])) : '',
			'produkt_label'=> isset($_POST['produkt_label']) ? \sanitize_text_field((string) \wp_unslash($_POST['produkt_label'])) : '',
		];

		if ($mode === 'note') {
			cmx_ext_time_append_note($target_id, $target_post_type, $payload);
		} else {
			cmx_ext_time_append_task($target_id, $payload);
		}

		\wp_send_json_success([
			'target_type' => $target_type,
			'target_id'   => $target_id,
			'edit_url'    => (string) \admin_url('post.php?post=' . $target_id . '&action=edit'),
			'mode'        => $mode,
			'duration'    => $duration_hours,
			'start_date'  => $start_date,
			'start_time'  => $start_time,
		]);
	}
	\add_action('wp_ajax_' . CMX_EXT_TIME_SAVE_ACTION, __NAMESPACE__ . '\\cmx_ext_time_save_handler');
	\add_action('wp_ajax_nopriv_' . CMX_EXT_TIME_SAVE_ACTION, __NAMESPACE__ . '\\cmx_ext_time_save_handler');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_manifest_json')) {
	function cmx_ext_time_manifest_json(): string {
		$manifest = [
			'manifest_version' => 3,
			'name' => 'Mis Büro - Zeiterfassung',
			'short_name' => 'Mis Büro Zeit',
			'version' => cmx_ext_time_plugin_version(),
			'description' => 'Erfasst Zeiten direkt in Projekten und Kontakten von Mis Büro.',
			'permissions' => ['storage', 'alarms', 'notifications', 'tabs'],
			'host_permissions' => ['<all_urls>'],
			'background' => [
				'service_worker' => 'service_worker.js',
			],
			'options_page' => 'options.html',
			'action' => [
				'default_title' => 'Mis Büro - Zeiterfassung',
				'default_popup' => 'popup.html',
				'default_icon' => [
					'16' => 'icon16.png',
					'32' => 'icon32.png',
					'48' => 'icon48.png',
					'128' => 'icon128.png',
				],
			],
			'icons' => [
				'16' => 'icon16.png',
				'32' => 'icon32.png',
				'48' => 'icon48.png',
				'128' => 'icon128.png',
			],
		];

		return (string) \wp_json_encode($manifest, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_config_js')) {
	function cmx_ext_time_config_js(): string {
		$config = [
			'defaultSuffix' => '.misbuero.ch',
			'connectAction' => CMX_EXT_TIME_CONNECT_ACTION,
			'bootstrapAction' => CMX_EXT_TIME_BOOTSTRAP_ACTION,
			'searchProjectsAction' => CMX_EXT_TIME_SEARCH_PROJECTS_ACTION,
			'searchContactsAction' => CMX_EXT_TIME_SEARCH_CONTACTS_ACTION,
			'searchArticlesAction' => CMX_EXT_TIME_SEARCH_ARTICLES_ACTION,
			'saveAction' => CMX_EXT_TIME_SAVE_ACTION,
			'noteSubjects' => \function_exists(__NAMESPACE__ . '\\cmx_notizen_betreff_options')
				? \array_values(\array_map('strval', (array) cmx_notizen_betreff_options()))
				: ['Meeting', 'E-Mail', 'Telefonat', 'Vor Ort', 'Remote'],
		];

		return 'self.CMX_EXT_TIME_CONFIG = ' . (string) \wp_json_encode($config, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) . ';' . "\n";
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_popup_html')) {
	function cmx_ext_time_popup_html(): string {
		return <<<'HTML'
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mis Büro - Zeiterfassung</title>
<style>
body{margin:0;font:13px/1.45 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#eef2f7;color:#1d2327;min-width:360px}
.wrap{padding:14px}
.head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
.brand{display:flex;align-items:center;gap:10px}
.icon{width:62px;height:62px;border-radius:18px;background:#fff;border:1px solid #dcdcde;display:flex;align-items:center;justify-content:center}
.title{font-weight:700;font-size:15px}
.subtitle{color:#646970}
.gear{appearance:none;border:1px solid #ccd0d4;background:#fff;color:#1d2327;border-radius:12px;width:42px;height:42px;padding:0;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1}
.gear svg{display:block;width:22px;height:22px}
.panel{background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:12px}
.stack{display:flex;flex-direction:column;gap:10px}
label{display:flex;flex-direction:column;gap:4px;font-weight:600}
input[type=text],select,textarea{width:100%;box-sizing:border-box;border:1px solid #ccd0d4;border-radius:10px;padding:8px 10px;background:#fff;font:inherit;color:inherit}
textarea{min-height:76px;resize:vertical}
.row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.mode-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:end}
.mode-field{display:flex;flex-direction:column;gap:4px;min-width:0}
.field-caption{font-weight:600}
.note-subject{display:flex;flex-direction:column;justify-content:flex-end;min-width:118px;align-items:flex-end}
.note-subject select{min-width:118px;width:auto;max-width:150px}
.note-subject.is-hidden{visibility:hidden;pointer-events:none}
.inline{display:flex;align-items:center;gap:8px}
.inline label{font-weight:400;flex-direction:row;align-items:center;gap:6px}
.mode-options{flex-wrap:nowrap;gap:12px}
.mode-options label{white-space:nowrap}
.target-row{display:flex;align-items:center;justify-content:space-between;gap:12px}
.target-toggle{appearance:none;border:0;background:none;padding:0;color:#646970;font:inherit;font-weight:600;cursor:pointer}
.target-toggle.is-active{color:#1d2327}
.muted{color:#646970;font-size:12px}
.pill{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;background:#eef4ff;color:#1d4f91;font-size:12px}
button{appearance:none;border:1px solid #2271b1;background:#2271b1;color:#fff;border-radius:10px;padding:9px 12px;cursor:pointer;font-weight:600}
button.secondary{background:#fff;color:#2271b1}
button.danger{background:#d63638;border-color:#d63638}
button:disabled{opacity:.55;cursor:not-allowed}
.status{margin-top:10px;padding:10px 12px;border-radius:10px;background:#fff;border:1px solid #dcdcde;display:none}
.status.is-error{display:block;border-color:#d63638;background:#fff1f1}
.status.is-success{display:block;border-color:#00a32a;background:#f2fff4}
.status.is-info{display:block}
.suggest-wrap{position:relative}
.suggest{position:absolute;left:0;right:0;top:calc(100% + 2px);z-index:9999;background:#fff;border:1px solid #ccd0d4;border-radius:10px;max-height:220px;overflow:auto;display:none;box-shadow:0 8px 22px rgba(0,0,0,.12)}
.suggest button{width:100%;border:0;background:none;color:inherit;text-align:left;padding:10px 12px;border-radius:0;font-weight:400}
.suggest button:hover,.suggest button.active{background:#eef4ff}
.suggest .hint{padding:10px 12px;color:#646970}
.footer{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:8px 12px;align-items:center;margin-top:10px}
#selection-hint{text-align:center;min-width:0}
#reset-form{justify-self:start}
#start-stop{justify-self:end}
.session{padding:8px 10px;border-radius:10px;background:#f6f7fb;border:1px solid #e2e4e7;display:flex;flex-direction:column;justify-content:center;gap:6px;min-height:64px}
.session.is-info{border-color:#2271b1;background:#eef4ff}
.session.is-success{border-color:#00a32a;background:#f2fff4}
.session.is-error{border-color:#d63638;background:#fff1f1}
.session-message{font-size:13px;font-weight:600;line-height:1.35}
.task-inline{margin-left:8px;white-space:nowrap}
.task-inline.is-hidden{visibility:hidden;pointer-events:none}
@media (max-width:420px){.row{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <div class="brand">
      <div class="icon"><img src="icon48.png" alt="" width="54" height="54"></div>
      <div>
        <div class="title">Mis Büro</div>
        <div class="subtitle">Zeiterfassung</div>
      </div>
    </div>
    <button type="button" class="gear" id="open-settings" aria-label="Einstellungen öffnen" title="Einstellungen">
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
            <label><input type="radio" name="cmx-ext-time-mode" value="task" checked> Tätigkeit</label>
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
        <button type="button" class="secondary" id="reset-form">Zurücksetzen</button>
        <div class="muted" id="selection-hint" hidden></div>
        <button type="button" id="start-stop">Start</button>
      </div>
    </div>
  </div>
</div>
<script src="config.js"></script>
<script src="popup.js"></script>
</body>
</html>
HTML;
	}
}
if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_options_html')) {
	function cmx_ext_time_options_html(): string {
		return <<<'HTML'
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mis Büro - Zeiterfassung – Einstellungen</title>
<style>
body{margin:0;font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#eef2f7;color:#1d2327}
.wrap{max-width:760px;margin:0 auto;padding:28px 18px}
.card{background:#fff;border:1px solid #dcdcde;border-radius:18px;padding:18px}
.head{display:flex;align-items:center;gap:12px;margin-bottom:18px}
.icon{width:76px;height:76px;border-radius:20px;background:#fff;border:1px solid #dcdcde;display:flex;align-items:center;justify-content:center}
.title{font-size:20px;font-weight:700}
.muted{color:#646970}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
label{display:flex;flex-direction:column;gap:6px;font-weight:600}
input[type=text],input[type=password],select{width:100%;box-sizing:border-box;border:1px solid #ccd0d4;border-radius:12px;padding:10px 12px;background:#fff;font:inherit;color:inherit}
.suffix{display:flex;align-items:center;gap:8px}
.suffix input{flex:1 1 auto}
.suffix span{white-space:nowrap;color:#646970}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
button{appearance:none;border:1px solid #2271b1;background:#2271b1;color:#fff;border-radius:12px;padding:10px 14px;cursor:pointer;font-weight:600}
button.secondary{background:#fff;color:#2271b1}
button.danger{background:#d63638;border-color:#d63638}
button:disabled{opacity:.55;cursor:not-allowed}
.status{margin-top:14px;padding:12px 14px;border-radius:12px;background:#fff;border:1px solid #dcdcde;display:none}
.status.is-error{display:block;border-color:#d63638;background:#fff1f1}
.status.is-success{display:block;border-color:#00a32a;background:#f2fff4}
.status.is-info{display:block}
.list{margin-top:18px;border-top:1px solid #e2e4e7;padding-top:18px}
.instance-card{border:1px solid #e2e4e7;border-radius:14px;padding:12px 14px;background:#fafafa}
.instance-card + .instance-card{margin-top:10px}
.instance-title{font-weight:700}
.instance-meta{color:#646970;font-size:13px}
@media (max-width:760px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="head">
      <div class="icon"><img src="icon48.png" alt="" width="64" height="64"></div>
      <div>
        <div class="title">Mis Büro - Zeiterfassung</div>
        <div class="muted">Instanzen verbinden und Standard-Intervall pro Instanz setzen.</div>
      </div>
    </div>
    <div class="grid">
      <label>
        <span>Gespeicherte Instanzen</span>
        <select id="saved-instance"></select>
      </label>
      <label>
        <span>Erfassungsintervall</span>
        <select id="default-interval"></select>
      </label>
      <label>
        <span>Benutzername</span>
        <input type="text" id="instance-username" autocomplete="username" placeholder="WP-Benutzername">
      </label>
      <label>
        <span>Passwort</span>
        <input type="password" id="instance-password" autocomplete="current-password" placeholder="Passwort">
      </label>
      <label style="grid-column:1 / -1;">
        <span>Neue Instanz</span>
        <div class="suffix">
          <input type="text" id="instance-input" placeholder="meine-instanz">
          <span>.misbuero.ch</span>
        </div>
      </label>
    </div>
    <div class="actions">
      <button type="button" id="connect-instance">Instanz laden</button>
      <button type="button" class="secondary" id="save-instance">Einstellungen speichern</button>
      <button type="button" class="danger" id="remove-instance">Instanz entfernen</button>
    </div>
    <div id="status" class="status"></div>
    <div class="list" id="instance-list"></div>
  </div>
</div>
<script src="config.js"></script>
<script src="options.js"></script>
</body>
</html>
HTML;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_options_js')) {
	function cmx_ext_time_options_js(): string {
		return <<<'JS'
const CONFIG = self.CMX_EXT_TIME_CONFIG || {};
const STORAGE_KEY = 'cmxExtTime.instances';

const savedInstanceEl = document.getElementById('saved-instance');
const defaultIntervalEl = document.getElementById('default-interval');
const instanceInputEl = document.getElementById('instance-input');
const usernameInputEl = document.getElementById('instance-username');
const passwordInputEl = document.getElementById('instance-password');
const connectBtn = document.getElementById('connect-instance');
const saveBtn = document.getElementById('save-instance');
const removeBtn = document.getElementById('remove-instance');
const statusEl = document.getElementById('status');
const listEl = document.getElementById('instance-list');

function setStatus(text, type = 'info') {
  statusEl.textContent = text || '';
  statusEl.classList.remove('is-error', 'is-success', 'is-info');
  if (text) {
    statusEl.classList.add(type === 'error' ? 'is-error' : (type === 'success' ? 'is-success' : 'is-info'));
  }
}

function getStorage(key) {
  return chrome.storage.local.get(key).then((data) => data[key]);
}

function setStorage(obj) {
  return chrome.storage.local.set(obj);
}

function normalizeInstanceInput(raw) {
  const value = (raw || '').trim();
  if (!value) return { slug: '', baseUrl: '' };
  if (/^https?:\/\//i.test(value)) {
    try {
      const parsed = new URL(value);
      const host = (parsed.hostname || '').replace(/\.misbuero\.ch$/i, '');
      return {
        slug: host || parsed.hostname,
        baseUrl: parsed.origin,
      };
    } catch (error) {
      return { slug: '', baseUrl: '' };
    }
  }
  const slug = value.replace(/^https?:\/\//i, '').replace(/\/.*$/, '').replace(/\.misbuero\.ch$/i, '').trim();
  return {
    slug,
    baseUrl: slug ? ('https://' + slug + '.misbuero.ch') : '',
  };
}

function adminAjaxUrl(instance) {
  if (instance && typeof instance.ajaxUrl === 'string' && instance.ajaxUrl.trim()) {
    return instance.ajaxUrl.trim();
  }
  return instance.baseUrl.replace(/\/+$/, '') + '/wp-admin/admin-ajax.php';
}

function instanceResponseError(response, json, rawText) {
  const serverMessage = json && json.data && typeof json.data.message === 'string'
    ? json.data.message.trim()
    : '';
  if (serverMessage) {
    return serverMessage;
  }

  const body = (rawText || '').trim();
  if (body === '0') {
    return 'Die Instanz kennt die Zeiterfassungs-Schnittstelle noch nicht. Bitte das Plugin auf dieser Instanz aktualisieren.';
  }
  if (/<!doctype html|<html[\s>]/i.test(body)) {
    return 'Die Instanz liefert HTML statt der Zeiterfassungs-Daten. Meist ist die URL falsch oder das Plugin auf dieser Instanz ist nicht aktuell.';
  }
  if (response.status === 401 || response.status === 403) {
    return 'Die Instanz hat die Anmeldung abgewiesen. Bitte Benutzername und Passwort prüfen.';
  }
  if (response.status === 404) {
    return 'Die Instanz wurde nicht gefunden. Bitte die Subdomain prüfen.';
  }
  if (!response.ok) {
    return 'Die Instanz hat mit HTTP ' + response.status + ' geantwortet.';
  }

  return 'Die Instanz antwortet nicht mit gültigen Daten.';
}

async function fetchBootstrap(instance, credentials = {}) {
  const hasCredentials = !!((credentials.username || '').trim() && (credentials.password || '').trim());
  const action = hasCredentials ? (CONFIG.connectAction || 'cmx_ext_time_connect') : (CONFIG.bootstrapAction || 'cmx_ext_time_bootstrap');
  const url = new URL(adminAjaxUrl(instance));
  url.searchParams.set('action', action);
  if (!hasCredentials && instance.token) {
    url.searchParams.set('token', instance.token);
  }
  const fetchOptions = {
    method: hasCredentials ? 'POST' : 'GET',
    credentials: 'omit',
    cache: 'no-store',
    headers: {},
  };
  if (hasCredentials) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('username', (credentials.username || '').trim());
    formData.append('password', (credentials.password || '').trim());
    fetchOptions.body = formData;
  } else if (instance.token) {
    fetchOptions.headers['X-CMX-Extension-Token'] = instance.token;
  }

  let response;
  try {
    response = await fetch(url.toString(), fetchOptions);
  } catch (error) {
    throw new Error('Die Instanz ist nicht erreichbar. Bitte URL und Netzwerk prüfen.');
  }

  const rawText = await response.text().catch(() => '');
  let json = null;
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

async function fetchActionData(instance, action, params = {}) {
  const url = new URL(adminAjaxUrl(instance));
  url.searchParams.set('action', action);
  Object.keys(params || {}).forEach((key) => {
    const value = params[key];
    if (value === null || value === undefined) {
      return;
    }
    url.searchParams.set(key, String(value));
  });
  if (instance && instance.token) {
    url.searchParams.set('token', instance.token);
  }

  let response;
  try {
    response = await fetch(url.toString(), {
      method: 'GET',
      credentials: 'omit',
      cache: 'no-store',
      headers: instance && instance.token ? { 'X-CMX-Extension-Token': instance.token } : {},
    });
  } catch (error) {
    throw new Error('Die Instanz ist nicht erreichbar. Bitte URL und Netzwerk prüfen.');
  }

  const rawText = await response.text().catch(() => '');
  let json = null;
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
  return json.data || [];
}

function stripHtml(value) {
  return String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

function normalizeContacts(items, limit = 300) {
  const normalized = [];
  const seen = new Set();

  (Array.isArray(items) ? items : []).forEach((item) => {
    const id = Number(item && item.id ? item.id : 0);
    if (!id || seen.has(id)) {
      return;
    }
    seen.add(id);
    const title = stripHtml(item && (item.label || item.title || '')) || ('(#' + id + ')');
    normalized.push({
      id: id,
      title: title,
      label: title,
      entity_type: 'contact',
    });
  });

  return normalized.slice(0, Math.max(1, Number(limit) || 300));
}

async function fetchContactsFromAjax(instance, limit = 300) {
  const action = CONFIG.searchContactsAction || 'cmx_ext_time_search_contacts';
  const rows = await fetchActionData(instance, action, { term: '' });
  return normalizeContacts(rows, limit);
}

async function fetchRestJson(url) {
  const response = await fetch(url, {
    method: 'GET',
    credentials: 'omit',
    cache: 'no-store',
  });
  if (!response.ok) {
    throw new Error('HTTP ' + response.status);
  }
  return response.json();
}

async function detectContactRestBases(instance) {
  const fallbackBases = ['kontakte', 'kontakt', 'contacts', 'contact'];
  const baseUrl = (instance.baseUrl || '').replace(/\/+$/, '');
  if (!baseUrl) {
    return fallbackBases;
  }

  try {
    const types = await fetchRestJson(baseUrl + '/wp-json/wp/v2/types');
    const bases = [];
    Object.keys(types || {}).forEach((key) => {
      const entry = types[key] || {};
      const haystack = [
        key,
        entry.slug || '',
        entry.rest_base || '',
        entry.name || '',
        entry.description || '',
      ].join(' ');
      if (!/kontakt|contact/i.test(haystack)) {
        return;
      }
      bases.push(String(entry.rest_base || key || '').trim());
    });
    return Array.from(new Set(bases.concat(fallbackBases).filter(Boolean)));
  } catch (error) {
    return fallbackBases;
  }
}

async function fetchContactsFromRestBase(instance, restBase, limit = 300) {
  const baseUrl = (instance.baseUrl || '').replace(/\/+$/, '');
  if (!baseUrl || !restBase) {
    return [];
  }

  const out = [];
  let page = 1;

  while (out.length < limit) {
    const perPage = Math.min(100, limit - out.length);
    let rows = [];
    let loaded = false;
    const restPath = '/wp/v2/' + String(restBase).replace(/^\/+/, '');
    const urls = [
      baseUrl + '/wp-json' + restPath + '?per_page=' + perPage + '&page=' + page + '&_fields=id,title',
      baseUrl + '/?rest_route=' + encodeURIComponent(restPath) + '&per_page=' + perPage + '&page=' + page + '&_fields=id,title',
    ];
    for (const url of urls) {
      try {
        rows = await fetchRestJson(url);
        loaded = true;
        break;
      } catch (error) {
      }
    }
    if (!loaded) {
      return page === 1 ? [] : out;
    }

    if (!Array.isArray(rows) || !rows.length) {
      break;
    }

    rows.forEach((row) => {
      const id = Number(row && row.id ? row.id : 0);
      if (!id) {
        return;
      }
      const title = stripHtml(row && row.title && row.title.rendered ? row.title.rendered : '') || ('(#' + id + ')');
      out.push({
        id: id,
        title: title,
        label: title,
        entity_type: 'contact',
      });
    });

    if (rows.length < perPage) {
      break;
    }
    page += 1;
  }

  return out;
}

async function fetchFallbackContacts(instance, limit = 300) {
  const restBases = await detectContactRestBases(instance);
  for (const restBase of restBases) {
    const contacts = await fetchContactsFromRestBase(instance, restBase, limit);
    if (contacts.length) {
      return contacts;
    }
  }
  return [];
}

function intervalOptions(intervals, current) {
  const allowed = Array.isArray(intervals) && intervals.length ? intervals : [5,10,15,20,30,45,60];
  defaultIntervalEl.innerHTML = allowed.map((value) => {
    const n = Number(value);
    return '<option value="' + n + '"' + (Number(current) === n ? ' selected' : '') + '>' + n + '</option>';
  }).join('');
}

function pickDefaultInterval(intervals, preferred) {
  const allowed = Array.isArray(intervals) && intervals.length
    ? intervals.map((value) => Number(value)).filter((value) => Number.isFinite(value) && value > 0)
    : [5,10,15,20,30,45,60];
  const requested = Number(preferred || 0);
  if (allowed.includes(requested)) {
    return requested;
  }
  return Number(allowed[0] || 5);
}

function renderSavedInstanceSelect(instances, selectedKey) {
  const list = Array.isArray(instances) ? instances : [];
  savedInstanceEl.innerHTML = '';
  if (!list.length) {
    savedInstanceEl.innerHTML = '<option value="">Noch keine Instanz gespeichert</option>';
    savedInstanceEl.disabled = true;
    removeBtn.disabled = true;
    saveBtn.disabled = true;
    intervalOptions([5,10,15,20,30,45,60], 5);
    return;
  }

  savedInstanceEl.disabled = false;
  removeBtn.disabled = false;
  saveBtn.disabled = false;
  savedInstanceEl.innerHTML = list.map((instance) => {
    const key = instance.slug || instance.baseUrl;
    const selected = (selectedKey ? selectedKey === key : list[0] && key === (list[0].slug || list[0].baseUrl)) ? ' selected' : '';
    return '<option value="' + key + '"' + selected + '>' + (instance.slug || instance.baseUrl) + '</option>';
  }).join('');
}

function renderInstanceList(instances) {
  if (!Array.isArray(instances) || !instances.length) {
    listEl.innerHTML = '<div class="muted">Noch keine Instanz gespeichert.</div>';
    return;
  }
  listEl.innerHTML = instances.map((instance) => {
    const intervals = Array.isArray(instance.intervals) && instance.intervals.length ? instance.intervals.join(', ') : '-';
    const projectCount = Array.isArray(instance.projects) ? instance.projects.length : 0;
    const contactCount = Array.isArray(instance.contacts) ? instance.contacts.length : 0;
    return '<div class="instance-card">'
      + '<div class="instance-title">' + (instance.slug || instance.baseUrl) + '</div>'
      + '<div class="instance-meta">' + (instance.siteName || instance.baseUrl) + '</div>'
      + '<div class="instance-meta">Benutzer: ' + (instance.userLogin || '-') + '</div>'
      + '<div class="instance-meta">Intervall standardmässig: ' + (instance.defaultInterval || '-') + ' Minuten</div>'
      + '<div class="instance-meta">Mögliche Intervalle: ' + intervals + '</div>'
      + '<div class="instance-meta">Aktive Projekte geladen: ' + projectCount + '</div>'
      + '<div class="instance-meta">Kontakte geladen: ' + contactCount + '</div>'
      + '</div>';
  }).join('');
}

async function getInstances() {
  const instances = await getStorage(STORAGE_KEY);
  return Array.isArray(instances) ? instances : [];
}

async function saveInstances(instances) {
  await setStorage({ [STORAGE_KEY]: instances });
}

function findSelectedInstance(instances) {
  const key = savedInstanceEl.value || '';
  return (instances || []).find((instance) => (instance.slug || instance.baseUrl) === key) || null;
}

async function refresh(selectedKey = '') {
  const instances = await getInstances();
  renderSavedInstanceSelect(instances, selectedKey);
  renderInstanceList(instances);
  const selected = findSelectedInstance(instances) || instances[0] || null;
  if (selected) {
    intervalOptions(selected.intervals || [5,10,15,20,30,45,60], selected.defaultInterval || 5);
  } else {
    intervalOptions([5,10,15,20,30,45,60], 5);
  }
}

savedInstanceEl.addEventListener('change', async () => {
  const instances = await getInstances();
  const selected = findSelectedInstance(instances);
  if (selected) {
    intervalOptions(selected.intervals || [5,10,15,20,30,45,60], selected.defaultInterval || 5);
    instanceInputEl.value = selected.slug || '';
    usernameInputEl.value = selected.userLogin || '';
    passwordInputEl.value = '';
  }
});

instanceInputEl.addEventListener('keydown', (event) => {
  if (event.key !== 'Enter') {
    return;
  }
  event.preventDefault();
  connectBtn.click();
});

connectBtn.addEventListener('click', async () => {
  let normalized = normalizeInstanceInput(instanceInputEl.value);
  if (!normalized.slug || !normalized.baseUrl) {
    const existing = findSelectedInstance(await getInstances());
    if (existing) {
      normalized = {
        slug: existing.slug || '',
        baseUrl: existing.baseUrl || '',
        token: existing.token || '',
      };
    }
  }
  if (!normalized.slug || !normalized.baseUrl) {
    setStatus('Eine gültige Instanz eingeben.', 'error');
    return;
  }
  const username = (usernameInputEl.value || '').trim();
  const password = (passwordInputEl.value || '').trim();
  if (!username || !password) {
    setStatus('Bitte Benutzername und Passwort eingeben.', 'error');
    return;
  }

  setStatus('Instanz wird geladen...', 'info');
  try {
    const bootstrap = await fetchBootstrap(normalized, { username, password });
    const intervals = Array.isArray(bootstrap.intervals) && bootstrap.intervals.length ? bootstrap.intervals : [5,10,15,20,30,45,60];
    const defaultInterval = pickDefaultInterval(intervals, defaultIntervalEl.value || bootstrap.defaultInterval || 5);
    let contacts = Array.isArray(bootstrap.contacts) ? bootstrap.contacts : [];
    if (!contacts.length) {
      try {
        contacts = await fetchContactsFromAjax({
          baseUrl: normalized.baseUrl,
          ajaxUrl: bootstrap.ajaxUrl || '',
          token: bootstrap.token || normalized.token || '',
        });
      } catch (error) {
        contacts = [];
      }
    }
    if (!contacts.length) {
      contacts = await fetchFallbackContacts(normalized);
    }
    const instances = await getInstances();
    const merged = {
      slug: normalized.slug,
      baseUrl: normalized.baseUrl,
      siteName: bootstrap.siteName || normalized.baseUrl,
      token: bootstrap.token || '',
      userLogin: username,
      intervals: intervals,
      defaultInterval: defaultInterval,
      ajaxUrl: bootstrap.ajaxUrl || (normalized.baseUrl.replace(/\/+$/, '') + '/wp-admin/admin-ajax.php'),
      userDisplay: bootstrap.userDisplay || '',
      supports: bootstrap.supports && typeof bootstrap.supports === 'object' ? bootstrap.supports : {},
      projects: Array.isArray(bootstrap.projects) ? bootstrap.projects : [],
      contacts: contacts,
      updatedAt: new Date().toISOString(),
    };

    const next = instances.filter((instance) => (instance.slug || instance.baseUrl) !== merged.slug);
    next.push(merged);
    next.sort((a, b) => String(a.slug || '').localeCompare(String(b.slug || ''), 'de'));
    await saveInstances(next);
    await refresh(merged.slug);
    defaultIntervalEl.value = String(merged.defaultInterval);
    usernameInputEl.value = username;
    passwordInputEl.value = '';
    setStatus('Instanz wurde erfolgreich geladen und gespeichert. ' + String(merged.projects.length || 0) + ' Projekte und ' + String(merged.contacts.length || 0) + ' Kontakte wurden übernommen.', 'success');
  } catch (error) {
    setStatus(error && error.message ? error.message : 'Die Instanz konnte nicht geladen werden.', 'error');
  }
});

saveBtn.addEventListener('click', async () => {
  const instances = await getInstances();
  const selected = findSelectedInstance(instances);
  if (!selected) {
    setStatus('Erst eine Instanz auswählen.', 'error');
    return;
  }
  const value = Number(defaultIntervalEl.value || 0);
  if (!value) {
    setStatus('Bitte ein Intervall auswählen.', 'error');
    return;
  }
  selected.defaultInterval = value;
  await saveInstances(instances.map((instance) => ((instance.slug || instance.baseUrl) === (selected.slug || selected.baseUrl) ? selected : instance)));
  await refresh(selected.slug || selected.baseUrl);
  setStatus('Standard-Intervall wurde gespeichert.', 'success');
});

removeBtn.addEventListener('click', async () => {
  const instances = await getInstances();
  const selected = findSelectedInstance(instances);
  if (!selected) {
    setStatus('Keine Instanz ausgewählt.', 'error');
    return;
  }
  const next = instances.filter((instance) => (instance.slug || instance.baseUrl) !== (selected.slug || selected.baseUrl));
  await saveInstances(next);
  instanceInputEl.value = '';
  usernameInputEl.value = '';
  passwordInputEl.value = '';
  await refresh('');
  setStatus('Instanz wurde entfernt.', 'success');
});

document.addEventListener('DOMContentLoaded', refresh);
JS;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_popup_js')) {
	function cmx_ext_time_popup_js(): string {
		return <<<'JS'
const CONFIG = self.CMX_EXT_TIME_CONFIG || {};
const INSTANCE_KEY = 'cmxExtTime.instances';

const instanceSelect = document.getElementById('instance-select');
const modeSelect = document.getElementById('mode-select');
const targetProjectButton = document.getElementById('target-project');
const targetContactButton = document.getElementById('target-contact');
const projectSearch = document.getElementById('project-search');
const infoInput = document.getElementById('info-input');
const verrechenbarInput = document.getElementById('verrechenbar');
const startStopButton = document.getElementById('start-stop');
const resetButton = document.getElementById('reset-form');
const openSettingsButton = document.getElementById('open-settings');
const selectionHint = document.getElementById('selection-hint');
const sessionCardEl = document.getElementById('session-card');
const sessionLabelEl = document.getElementById('session-label');
const sessionMessageEl = document.getElementById('session-message');
const intervalDisplay = document.getElementById('interval-display');
const taskInlineEl = document.getElementById('task-inline');
const noteSubjectWrapEl = document.getElementById('note-subject-wrap');
const noteSubjectEl = document.getElementById('note-subject');

const state = {
  instances: [],
  activeSession: null,
  selectedTargetType: 'project',
  selectedProject: null,
  selectedContact: null,
  statusNotice: null,
};

let statusTimer = null;
let activeSessionSyncTimer = null;

function fillNoteSubjectOptions(selectedValue = '') {
  if (!noteSubjectEl) {
    return;
  }

  const subjects = Array.isArray(CONFIG.noteSubjects) && CONFIG.noteSubjects.length
    ? CONFIG.noteSubjects.map((value) => String(value || '').trim()).filter(Boolean)
    : ['Meeting', 'E-Mail', 'Telefonat', 'Vor Ort', 'Remote'];
  const current = String(selectedValue || subjects[0] || '').trim();
  noteSubjectEl.innerHTML = subjects.map((value) => (
    '<option value="' + value.replace(/[&<>"']/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    }[char] || char)) + '"' + (value === current ? ' selected' : '') + '>' + value + '</option>'
  )).join('');
  if (!noteSubjectEl.value && subjects[0]) {
    noteSubjectEl.value = subjects[0];
  }
}

function currentNoteSubject() {
  return noteSubjectEl ? String(noteSubjectEl.value || '').trim() : '';
}

function renderSessionCard() {
  if (!sessionCardEl || !sessionLabelEl || !intervalDisplay || !sessionMessageEl) return;

  sessionCardEl.classList.remove('is-error', 'is-success', 'is-info');
  if (state.statusNotice && state.statusNotice.text) {
    sessionLabelEl.textContent = 'Status';
    intervalDisplay.hidden = true;
    sessionMessageEl.hidden = false;
    sessionMessageEl.textContent = state.statusNotice.text;
    sessionCardEl.classList.add(
      state.statusNotice.type === 'error'
        ? 'is-error'
        : (state.statusNotice.type === 'success' ? 'is-success' : 'is-info')
    );
    return;
  }

  sessionLabelEl.textContent = 'Intervall';
  intervalDisplay.hidden = false;
  sessionMessageEl.hidden = true;
  sessionMessageEl.textContent = '';
  const instance = selectedInstance();
  intervalDisplay.textContent = instance ? ((instance.defaultInterval || 5) + ' min') : '-';
}

function setStatus(text, type = '', durationMs = 5000) {
  if (statusTimer) {
    window.clearTimeout(statusTimer);
    statusTimer = null;
  }
  if (!text) {
    state.statusNotice = null;
    renderSessionCard();
    return;
  }

  state.statusNotice = {
    text: text || '',
    type: type || 'info',
  };
  renderSessionCard();

  if (durationMs > 0) {
    statusTimer = window.setTimeout(() => {
      state.statusNotice = null;
      statusTimer = null;
      renderSessionCard();
    }, durationMs);
  }
}

function getStorage(key) {
  return chrome.storage.local.get(key).then((data) => data[key]);
}

function selectedInstance() {
  const key = instanceSelect.value || '';
  return state.instances.find((instance) => (instance.slug || instance.baseUrl) === key) || null;
}

function currentTargetType() {
  return state.selectedTargetType === 'contact' ? 'contact' : 'project';
}

function instanceSupportsContactSave(instance) {
  return !!(instance && instance.supports && instance.supports.contactSave);
}

function currentTargetConfig() {
  const targetType = currentTargetType();
  return targetType === 'contact'
    ? {
        type: 'contact',
        action: CONFIG.searchContactsAction || 'cmx_ext_time_search_contacts',
        cacheKey: 'contacts',
        placeholder: 'Kontakt suchen...',
      }
    : {
        type: 'project',
        action: CONFIG.searchProjectsAction || 'cmx_ext_time_search_projects',
        cacheKey: 'projects',
        placeholder: 'Projekt suchen...',
      };
}

function currentSelectedTarget() {
  return currentTargetType() === 'contact' ? state.selectedContact : state.selectedProject;
}

function buildAjaxUrl(instance, action, extra = {}) {
  const base = instance && instance.ajaxUrl ? instance.ajaxUrl : ((instance.baseUrl || '').replace(/\/+$/, '') + '/wp-admin/admin-ajax.php');
  const url = new URL(base);
  url.searchParams.set('action', action);
  Object.keys(extra || {}).forEach((key) => {
    const value = extra[key];
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, value);
    }
  });
  return url.toString();
}

function filterCachedProjects(projects, term) {
  const list = Array.isArray(projects) ? projects : [];
  const needle = String(term || '').trim().toLocaleLowerCase('de');
  if (!needle) {
    return list.slice(0, 100);
  }

  return list.filter((item) => {
    const haystack = String((item.title || '') + ' ' + (item.label || '')).toLocaleLowerCase('de');
    return haystack.includes(needle);
  }).slice(0, 100);
}

function instanceResponseError(response, json, rawText) {
  const serverMessage = json && json.data && typeof json.data.message === 'string'
    ? json.data.message.trim()
    : '';
  if (serverMessage) {
    return serverMessage;
  }

  const body = (rawText || '').trim();
  if (body === '0') {
    return 'Die Instanz kennt die Zeiterfassungs-Schnittstelle noch nicht. Bitte das Plugin auf dieser Instanz aktualisieren.';
  }
  if (/<!doctype html|<html[\s>]/i.test(body)) {
    return 'Die Instanz liefert HTML statt der Zeiterfassungs-Daten. Meist ist die URL falsch oder das Plugin auf dieser Instanz ist nicht aktuell.';
  }
  if (response.status === 401 || response.status === 403) {
    return 'Die Instanz hat die Anfrage abgewiesen. Bitte Instanz und Anmeldung prüfen.';
  }
  if (response.status === 404) {
    return 'Die Instanz wurde nicht gefunden. Bitte die Subdomain prüfen.';
  }
  if (!response.ok) {
    return 'Die Instanz hat mit HTTP ' + response.status + ' geantwortet.';
  }

  return 'Die Instanz antwortet nicht mit gültigen Daten.';
}

function fillSelect() {
  instanceSelect.innerHTML = '';
  if (!state.instances.length) {
    instanceSelect.innerHTML = '<option value="">Erst eine Instanz auswählen.</option>';
    instanceSelect.disabled = false;
    renderSessionCard();
    return;
  }
  instanceSelect.disabled = false;
  instanceSelect.innerHTML = state.instances.map((instance, index) => {
    const key = instance.slug || instance.baseUrl;
    return '<option value="' + key + '"' + (index === 0 ? ' selected' : '') + '>' + key + '</option>';
  }).join('');
  updateIntervalHint();
}

function updateIntervalHint() {
  renderSessionCard();
}

function updateTargetUi() {
  const targetType = currentTargetType();
  const instance = selectedInstance();
  const contactSupported = instanceSupportsContactSave(instance);
  if (targetProjectButton) {
    targetProjectButton.classList.toggle('is-active', targetType === 'project');
    targetProjectButton.setAttribute('aria-pressed', targetType === 'project' ? 'true' : 'false');
  }
  if (targetContactButton) {
    targetContactButton.classList.toggle('is-active', targetType === 'contact');
    targetContactButton.setAttribute('aria-pressed', targetType === 'contact' ? 'true' : 'false');
    targetContactButton.title = contactSupported ? '' : 'Kontakt-Speicherung wird von dieser Instanz noch nicht unterstützt';
  }

  const selected = currentSelectedTarget();
  const config = currentTargetConfig();
  projectSearch.placeholder = config.placeholder;
  projectSearch.value = selected ? (selected.label || selected.title || '') : '';
}

function setTargetType(type) {
  state.selectedTargetType = type === 'contact' ? 'contact' : 'project';
  updateTargetUi();
}

function setPicked(type, item) {
  if (type === 'project') {
    state.selectedProject = item || null;
  } else if (type === 'contact') {
    state.selectedContact = item || null;
  }
  if (type === currentTargetType()) {
    projectSearch.value = item ? (item.label || item.title || '') : '';
  }
  updateSelectionHint();
  if (state.activeSession && item && item.id) {
    chrome.runtime.sendMessage({
      type: 'cmx-ext-time-update-session',
      payload: {
        targetType: type,
        target: item,
        project: type === 'project' ? item : null,
        contact: type === 'contact' ? item : null,
      },
    }).then((result) => {
      if (result && result.success && result.session) {
        state.activeSession = result.session;
      }
    }).catch(() => {});
  }
}

function formatSessionStarted(session) {
  if (!session) return '';

  const startMs = Number(session.startMs || 0);
  if (startMs > 0) {
    try {
      return new Intl.DateTimeFormat('de-CH', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      }).format(new Date(startMs));
    } catch (error) {}
  }

  const startDate = String(session.startDate || '').trim();
  const startTime = String(session.startTime || '').trim();
  if (startDate) {
    const parts = startDate.split('-');
    if (parts.length === 3) {
      const formattedDate = [parts[2], parts[1], parts[0]].join('.');
      return startTime ? (formattedDate + ' ' + startTime) : formattedDate;
    }
  }

  return startTime ? startTime : '';
}

function updateSelectionHint() {
  if (state.activeSession) {
    const startedAt = formatSessionStarted(state.activeSession);
    selectionHint.textContent = startedAt ? ('Gestartet seit ' + startedAt) : 'Gestartet seit';
    selectionHint.hidden = false;
    return;
  }
  selectionHint.textContent = '';
  selectionHint.hidden = true;
}

function currentMode() {
  const checked = modeSelect ? modeSelect.querySelector('input[name="cmx-ext-time-mode"]:checked') : null;
  return checked ? (checked.value || 'task') : 'task';
}

function setMode(value) {
  if (!modeSelect) return;
  const target = modeSelect.querySelector('input[name="cmx-ext-time-mode"][value="' + value + '"]');
  if (target) {
    target.checked = true;
  }
}

function updateModeUi() {
  const isTask = currentMode() === 'task';
  if (taskInlineEl) {
    taskInlineEl.classList.toggle('is-hidden', !isTask);
    taskInlineEl.setAttribute('aria-hidden', isTask ? 'false' : 'true');
  }
  if (noteSubjectWrapEl) {
    noteSubjectWrapEl.classList.toggle('is-hidden', isTask);
    noteSubjectWrapEl.setAttribute('aria-hidden', isTask ? 'true' : 'false');
  }
  if (noteSubjectEl) {
    noteSubjectEl.disabled = isTask;
  }
  if (verrechenbarInput) {
    verrechenbarInput.disabled = !isTask;
  }
  const infoText = isTask ? 'Weitere Infos im Detail' : 'Kurze Info';
  infoInput.placeholder = infoText + '...';
  infoInput.setAttribute('aria-label', infoText);
}

async function persistActiveSessionInfo() {
  if (!state.activeSession) {
    return null;
  }

  try {
    const result = await chrome.runtime.sendMessage({
      type: 'cmx-ext-time-update-session',
      payload: {
        info: infoInput.value || '',
        betreff: currentNoteSubject(),
      },
    });
    if (result && result.success && result.session) {
      state.activeSession = result.session;
    }
    return result || null;
  } catch (error) {
    return null;
  }
}

function queueActiveSessionInfoSync() {
  if (!state.activeSession) {
    return;
  }

  window.clearTimeout(activeSessionSyncTimer);
  activeSessionSyncTimer = window.setTimeout(() => {
    activeSessionSyncTimer = null;
    persistActiveSessionInfo();
  }, 180);
}

async function flushActiveSessionInfoSync() {
  if (activeSessionSyncTimer) {
    window.clearTimeout(activeSessionSyncTimer);
    activeSessionSyncTimer = null;
  }
  await persistActiveSessionInfo();
}

function resetForm() {
  if (state.activeSession) return;
  setPicked('project', null);
  setPicked('contact', null);
  infoInput.value = '';
  if (noteSubjectEl && noteSubjectEl.options.length) {
    noteSubjectEl.selectedIndex = 0;
  }
  verrechenbarInput.checked = true;
  setStatus('');
}

async function fetchJson(url, token) {
  let response;
  try {
    response = await fetch(url, {
      credentials: 'omit',
      headers: token ? { 'X-CMX-Extension-Token': token } : {},
      cache: 'no-store',
    });
  } catch (error) {
    throw new Error('Die Instanz ist nicht erreichbar. Bitte URL und Netzwerk prüfen.');
  }

  const rawText = await response.text().catch(() => '');
  let json = null;
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
  return json.data || [];
}

function makeSuggest(input, box) {
  let timer = null;
  let activeIndex = -1;
  let items = [];

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    }[char] || char));
  }

  function close() {
    box.style.display = 'none';
    box.innerHTML = '';
    activeIndex = -1;
    items = [];
  }

  function render(list) {
    items = Array.isArray(list) ? list : [];
    if (!items.length) {
      box.innerHTML = '<div class="hint">Keine Treffer gefunden.</div>';
      box.style.display = 'block';
      return;
    }
    box.innerHTML = items.map((item, index) => {
      const title = item.label || item.title || '';
      return '<button type="button" data-index="' + index + '"><div>' + escapeHtml(title) + '</div></button>';
    }).join('');
    box.style.display = 'block';
  }

  function pick(index) {
    if (!items[index]) return;
    setPicked(currentTargetType(), items[index]);
    close();
  }

  async function load(termOverride = null) {
    const instance = selectedInstance();
    if (!instance) {
      close();
      return;
    }
    const config = currentTargetConfig();
    const term = termOverride === null ? (input.value || '').trim() : String(termOverride || '').trim();
    try {
      if (Array.isArray(instance[config.cacheKey]) && instance[config.cacheKey].length) {
        render(filterCachedProjects(instance[config.cacheKey], term));
        return;
      }
      const url = buildAjaxUrl(instance, config.action, { term });
      const results = await fetchJson(url, instance.token || '');
      render(results);
    } catch (error) {
      close();
    }
  }

  input.addEventListener('input', () => {
    if (currentTargetType() === 'contact') {
      state.selectedContact = null;
    } else {
      state.selectedProject = null;
    }
    updateSelectionHint();
    window.clearTimeout(timer);
    timer = window.setTimeout(load, 180);
  });

  input.addEventListener('focus', () => {
    load();
  });

  input.addEventListener('keydown', (event) => {
    if (!items.length) return;
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      activeIndex = (activeIndex + 1 + items.length) % items.length;
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      activeIndex = (activeIndex - 1 + items.length) % items.length;
    } else if (event.key === 'Enter') {
      if (activeIndex >= 0) {
        event.preventDefault();
        pick(activeIndex);
      }
      return;
    } else if (event.key === 'Escape') {
      close();
      return;
    } else {
      return;
    }
    Array.from(box.querySelectorAll('button')).forEach((button, index) => {
      button.classList.toggle('active', index === activeIndex);
    });
  });

  box.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-index]');
    if (!button) return;
    pick(Number(button.dataset.index || -1));
  });

  document.addEventListener('click', (event) => {
    if (
      event.target === input
      || box.contains(event.target)
      || (targetProjectButton && targetProjectButton.contains(event.target))
      || (targetContactButton && targetContactButton.contains(event.target))
    ) return;
    close();
  });

  return {
    openAll() {
      return load('');
    },
    close,
  };
}

async function refreshState() {
  state.instances = Array.isArray(await getStorage(INSTANCE_KEY)) ? await getStorage(INSTANCE_KEY) : [];
  fillSelect();
  fillNoteSubjectOptions();
  state.activeSession = await chrome.runtime.sendMessage({ type: 'cmx-ext-time-get-active-session' }).catch(() => null);
  if (state.activeSession) {
    const activeInstanceKey = state.activeSession.instanceKey || '';
    if (activeInstanceKey && Array.from(instanceSelect.options).some((opt) => opt.value === activeInstanceKey)) {
      instanceSelect.value = activeInstanceKey;
    }
    setMode(state.activeSession.mode || 'task');
    setTargetType(state.activeSession.targetType || (state.activeSession.contact ? 'contact' : 'project'));
    setPicked('project', state.activeSession.project || null);
    setPicked('contact', state.activeSession.contact || null);
    setPicked(currentTargetType(), state.activeSession.target || state.activeSession.project || state.activeSession.contact || null);
    infoInput.value = state.activeSession.info || '';
    fillNoteSubjectOptions(state.activeSession.betreff || '');
    verrechenbarInput.checked = !!state.activeSession.verrechenbar;
    startStopButton.textContent = 'Stop';
    startStopButton.classList.add('danger');
    updateSelectionHint();
  } else {
    setTargetType(currentTargetType());
    startStopButton.textContent = 'Start';
    startStopButton.classList.remove('danger');
  }
  updateSelectionHint();
  updateModeUi();
}

async function handleStartStop() {
  const instance = selectedInstance();
  if (!instance) {
    setStatus('Erst eine Instanz auswählen.', 'error');
    return;
  }

  if (state.activeSession) {
    setStatus('Erfassung wird gespeichert...', 'info', 0);
    try {
      const selectedTarget = currentSelectedTarget();
      if (selectedTarget && selectedTarget.id) {
        const syncResult = await chrome.runtime.sendMessage({
          type: 'cmx-ext-time-update-session',
          payload: {
            targetType: currentTargetType(),
            target: selectedTarget,
            project: currentTargetType() === 'project' ? selectedTarget : null,
            contact: currentTargetType() === 'contact' ? selectedTarget : null,
          },
        }).catch(() => null);
        if (syncResult && syncResult.success && syncResult.session) {
          state.activeSession = syncResult.session;
        }
      }
      await flushActiveSessionInfoSync();
      const result = await chrome.runtime.sendMessage({ type: 'cmx-ext-time-stop-session' });
      if (!result || !result.success) {
        throw new Error((result && result.error) || 'Die Erfassung konnte nicht gespeichert werden.');
      }
      state.activeSession = null;
      await refreshState();
      setStatus('Erfassung wurde gespeichert.', 'success');
    } catch (error) {
      setStatus(error && error.message ? error.message : 'Die Erfassung konnte nicht gespeichert werden.', 'error');
    }
    return;
  }

  const targetType = currentTargetType();
  const selectedTarget = currentSelectedTarget();
  if (!selectedTarget || !selectedTarget.id) {
    setStatus(targetType === 'contact' ? 'Einen Kontakt auswählen.' : 'Ein Projekt auswählen.', 'error');
    return;
  }

  const payload = {
    instance: instance,
    mode: currentMode(),
    targetType: targetType,
    target: selectedTarget,
    info: (infoInput.value || '').trim(),
    betreff: currentNoteSubject(),
    verrechenbar: !!verrechenbarInput.checked,
  };

  setStatus('Erfassung wird gestartet...', 'info', 0);
  try {
    const result = await chrome.runtime.sendMessage({ type: 'cmx-ext-time-start-session', payload });
    if (!result || !result.success) {
      throw new Error((result && result.error) || 'Die Erfassung konnte nicht gestartet werden.');
    }
    state.activeSession = result.session || null;
    await refreshState();
    setStatus('Erfassung wurde gestartet.', 'success');
  } catch (error) {
    setStatus(error && error.message ? error.message : 'Die Erfassung konnte nicht gestartet werden.', 'error');
  }
}

function openSettings() {
  chrome.runtime.openOptionsPage();
}

function handleEmptyInstanceSelect(event) {
  if (state.instances.length) {
    return;
  }
  event.preventDefault();
  event.stopPropagation();
  openSettings();
}

function handleTargetSwitch(type) {
  setTargetType(type);
  if (!selectedInstance()) {
    return;
  }
  targetSuggest.openAll();
}

openSettingsButton.addEventListener('click', openSettings);
if (targetProjectButton) {
  targetProjectButton.addEventListener('click', () => handleTargetSwitch('project'));
}
if (targetContactButton) {
  targetContactButton.addEventListener('click', () => handleTargetSwitch('contact'));
}

if (modeSelect) {
  modeSelect.addEventListener('change', updateModeUi);
}
infoInput.addEventListener('input', queueActiveSessionInfoSync);
if (noteSubjectEl) {
  noteSubjectEl.addEventListener('change', queueActiveSessionInfoSync);
}
instanceSelect.addEventListener('mousedown', handleEmptyInstanceSelect);
instanceSelect.addEventListener('keydown', (event) => {
  if (!state.instances.length && ['Enter', ' ', 'ArrowDown', 'ArrowUp'].includes(event.key)) {
    handleEmptyInstanceSelect(event);
  }
});
instanceSelect.addEventListener('change', () => {
  if (!state.instances.length) {
    openSettings();
    return;
  }
  if (!state.activeSession) {
    setPicked('project', null);
    setPicked('contact', null);
  }
  updateIntervalHint();
  updateTargetUi();
});
resetButton.addEventListener('click', resetForm);
startStopButton.addEventListener('click', handleStartStop);

const targetSuggest = makeSuggest(projectSearch, document.getElementById('project-suggest'));

document.addEventListener('DOMContentLoaded', refreshState);
JS;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_service_worker_js')) {
	function cmx_ext_time_service_worker_js(): string {
		return <<<'JS'
importScripts('config.js');

const CONFIG = self.CMX_EXT_TIME_CONFIG || {};
const INSTANCE_KEY = 'cmxExtTime.instances';
const ACTIVE_KEY = 'cmxExtTime.activeSession';
const REMINDER_KEY = 'cmxExtTime.reminder';
const ALARM_REMINDER = 'cmx-ext-time-reminder';
const ALARM_TIMEOUT = 'cmx-ext-time-timeout';
const NOTIFICATION_ID = 'cmx-ext-time-notification';

function getStorage(key) {
  return chrome.storage.local.get(key).then((data) => data[key]);
}

function setStorage(obj) {
  return chrome.storage.local.set(obj);
}

function removeStorage(keys) {
  return chrome.storage.local.remove(keys);
}

function buildAjaxUrl(instance, action) {
  const base = instance && instance.ajaxUrl ? instance.ajaxUrl : ((instance.baseUrl || '').replace(/\/+$/, '') + '/wp-admin/admin-ajax.php');
  const url = new URL(base);
  url.searchParams.set('action', action);
  return url.toString();
}

function instanceSupportsContactSave(instance) {
  return !!(instance && instance.supports && instance.supports.contactSave);
}

function formatLocalDate(date) {
  const d = new Date(date);
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function formatLocalTime(date) {
  const d = new Date(date);
  return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
}

function normalizeSessionTarget(source) {
  const project = source && source.project && Number(source.project.id || 0) ? source.project : null;
  const contact = source && source.contact && Number(source.contact.id || 0) ? source.contact : null;
  let targetType = source && source.targetType === 'contact' ? 'contact' : 'project';
  let target = source && source.target && Number(source.target.id || 0) ? source.target : null;
  const entityType = target && typeof target.entity_type === 'string'
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
    contact: targetType === 'contact' ? target : null,
  };
}

function intervalMs(session) {
  return Math.max(1, Number(session.intervalMinutes || 5)) * 60 * 1000;
}

function durationHours(session, endMs) {
  const startMs = Number(session.startMs || 0);
  const diff = Math.max(0, Number(endMs || Date.now()) - startMs);
  const hours = diff / 3600000;
  return (Math.round(hours * 100) / 100).toFixed(2);
}

async function scheduleReminder(session) {
  const when = Date.now() + intervalMs(session);
  await chrome.alarms.clear(ALARM_REMINDER);
  await chrome.alarms.create(ALARM_REMINDER, { when });
}

async function clearReminderUi() {
  try { await chrome.notifications.clear(NOTIFICATION_ID); } catch (error) {}
  await chrome.alarms.clear(ALARM_TIMEOUT);
  await removeStorage(REMINDER_KEY);
}

async function setActiveBadge(active) {
  try {
    await chrome.action.setBadgeBackgroundColor({ color: active ? '#d63638' : '#999999' });
    await chrome.action.setBadgeText({ text: active ? 'ON' : '' });
  } catch (error) {}
}

async function persistSession(session, reason) {
  const endMs = Date.now();
  const normalizedTarget = normalizeSessionTarget(session || {});
  const targetType = normalizedTarget.targetType;
  const target = normalizedTarget.target;
  if (targetType === 'contact' && !instanceSupportsContactSave(session.instance || null)) {
    throw new Error('Diese Instanz unterstützt Kontakt-Speicherung noch nicht. Bitte das Plugin auf dieser Instanz aktualisieren.');
  }
  const formData = new FormData();
  formData.append('action', CONFIG.saveAction || 'cmx_ext_time_save');
  formData.append('token', session.instance.token || '');
  formData.append('target_type', targetType);
  formData.append('target_id', String((target && target.id) || 0));
  if (targetType === 'project') {
    formData.append('project_id', String((target && target.id) || 0));
  } else {
    formData.append('contact_id', String((target && target.id) || 0));
  }
  formData.append('mode', session.mode || 'task');
  formData.append('artikel_id', String((session.article && session.article.id) || 0));
  formData.append('produkt_id', String((session.product && session.product.id) || 0));
  formData.append('verrechenbar', session.verrechenbar ? '1' : '0');
  formData.append('info', session.info || '');
  formData.append('betreff', session.betreff || '');
  formData.append('artikel_label', (session.article && (session.article.label || session.article.title)) || '');
  formData.append('produkt_label', (session.product && (session.product.label || session.product.title)) || '');
  formData.append('start_at', new Date(session.startMs).toISOString());
  formData.append('end_at', new Date(endMs).toISOString());
  formData.append('start_date', session.startDate || formatLocalDate(session.startMs));
  formData.append('start_time', session.startTime || formatLocalTime(session.startMs));
  formData.append('reason', reason || 'manual');

  const response = await fetch(buildAjaxUrl(session.instance, CONFIG.saveAction || 'cmx_ext_time_save'), {
    method: 'POST',
    credentials: 'omit',
    headers: {
      'X-CMX-Extension-Token': session.instance.token || '',
    },
    body: formData,
  });
  const json = await response.json().catch(() => null);
  if (!response.ok || !json || !json.success) {
    const message = (json && json.data && json.data.message) || 'Die Zeit konnte in Mis Büro nicht gespeichert werden.';
    if (targetType === 'contact' && /projekt wurde nicht gefunden/i.test(String(message || ''))) {
      throw new Error('Diese Instanz unterstützt Kontakt-Speicherung noch nicht. Bitte das Plugin auf dieser Instanz aktualisieren.');
    }
    throw new Error(message);
  }
  return json.data || {};
}

async function startSession(payload) {
  const normalizedTarget = normalizeSessionTarget(payload || {});
  const targetType = normalizedTarget.targetType;
  const target = normalizedTarget.target;
  if (!payload || !payload.instance || !target || !target.id) {
    throw new Error(targetType === 'contact' ? 'Kontakt oder Instanz fehlen.' : 'Projekt oder Instanz fehlen.');
  }
  const session = {
    instanceKey: payload.instance.slug || payload.instance.baseUrl,
    instance: payload.instance,
    targetType: targetType,
    target: target,
    project: normalizedTarget.project,
    contact: normalizedTarget.contact,
    article: payload.article || null,
    product: payload.product || null,
    mode: payload.mode || 'task',
    info: payload.info || '',
    betreff: payload.betreff || '',
    verrechenbar: !!payload.verrechenbar,
    intervalMinutes: Number(payload.instance.defaultInterval || 5),
    startMs: Date.now(),
  };
  session.startDate = formatLocalDate(session.startMs);
  session.startTime = formatLocalTime(session.startMs);

  await clearReminderUi();
  await setStorage({ [ACTIVE_KEY]: session });
  await scheduleReminder(session);
  await setActiveBadge(true);

  return session;
}

async function updateSession(payload) {
  const session = await getStorage(ACTIVE_KEY);
  if (!session) {
    return { success: false, error: 'Keine aktive Erfassung gefunden.' };
  }

  const nextSession = {
    ...session,
  };

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
    const normalizedTarget = normalizeSessionTarget({
      ...nextSession,
      targetType: Object.prototype.hasOwnProperty.call(payload, 'targetType') ? payload.targetType : nextSession.targetType,
      target: Object.prototype.hasOwnProperty.call(payload, 'target') ? payload.target : nextSession.target,
      project: Object.prototype.hasOwnProperty.call(payload, 'project') ? payload.project : nextSession.project,
      contact: Object.prototype.hasOwnProperty.call(payload, 'contact') ? payload.contact : nextSession.contact,
    });
    nextSession.targetType = normalizedTarget.targetType;
    nextSession.target = normalizedTarget.target;
    nextSession.project = normalizedTarget.project;
    nextSession.contact = normalizedTarget.contact;
  }

  await setStorage({ [ACTIVE_KEY]: nextSession });
  return { success: true, session: nextSession };
}

async function stopSession(reason) {
  const session = await getStorage(ACTIVE_KEY);
  if (!session) {
    return { success: false, error: 'Keine aktive Erfassung gefunden.' };
  }

  await clearReminderUi();
  await chrome.alarms.clear(ALARM_REMINDER);
  try {
    const result = await persistSession(session, reason || 'manual');
    await removeStorage(ACTIVE_KEY);
    await setActiveBadge(false);
    return { success: true, result };
  } catch (error) {
    return { success: false, error: error && error.message ? error.message : String(error) };
  }
}

async function continueSession() {
  const session = await getStorage(ACTIVE_KEY);
  if (!session) {
    await clearReminderUi();
    return;
  }
  await clearReminderUi();
  await scheduleReminder(session);
}

async function showReminder() {
  const session = await getStorage(ACTIVE_KEY);
  if (!session) return;

  const target = session.target || session.project || session.contact || null;
  const projectName = (target && (target.title || target.label)) || (session.targetType === 'contact' ? 'diesem Kontakt' : 'diesem Projekt');
  await setStorage({
    [REMINDER_KEY]: {
      createdAt: Date.now(),
      projectTitle: projectName,
    },
  });

  await chrome.notifications.create(NOTIFICATION_ID, {
    type: 'basic',
    iconUrl: 'icon128.png',
    title: 'Mis Büro - Zeiterfassung',
    message: 'Arbeitest du noch an ' + projectName + '?',
    buttons: [
      { title: 'Ja, weiter' },
      { title: 'Nein, stoppen' },
    ],
    priority: 2,
    requireInteraction: true,
  });

  await chrome.alarms.create(ALARM_TIMEOUT, { when: Date.now() + 20000 });
}

chrome.runtime.onInstalled.addListener(async () => {
  const session = await getStorage(ACTIVE_KEY);
  await setActiveBadge(!!session);
});

chrome.runtime.onStartup.addListener(async () => {
  const session = await getStorage(ACTIVE_KEY);
  if (session) {
    await scheduleReminder(session);
    await setActiveBadge(true);
  } else {
    await setActiveBadge(false);
  }
});

chrome.alarms.onAlarm.addListener(async (alarm) => {
  if (!alarm || !alarm.name) return;
  if (alarm.name === ALARM_REMINDER) {
    await showReminder();
    return;
  }
  if (alarm.name === ALARM_TIMEOUT) {
    const reminder = await getStorage(REMINDER_KEY);
    if (!reminder) return;
    await stopSession('timeout');
  }
});

chrome.notifications.onButtonClicked.addListener(async (notificationId, buttonIndex) => {
  if (notificationId !== NOTIFICATION_ID) return;
  if (buttonIndex === 0) {
    await continueSession();
  } else {
    await stopSession('declined');
  }
});

chrome.notifications.onClosed.addListener(async (notificationId, byUser) => {
  if (notificationId !== NOTIFICATION_ID) return;
  const reminder = await getStorage(REMINDER_KEY);
  if (!reminder) return;
  if (byUser) {
    await stopSession('closed');
  }
});

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (!message || typeof message !== 'object') return;

  if (message.type === 'cmx-ext-time-get-active-session') {
    getStorage(ACTIVE_KEY).then((session) => sendResponse(session || null));
    return true;
  }

  if (message.type === 'cmx-ext-time-start-session') {
    startSession(message.payload || {})
      .then((session) => sendResponse({ success: true, session }))
      .catch((error) => sendResponse({ success: false, error: error && error.message ? error.message : String(error) }));
    return true;
  }

  if (message.type === 'cmx-ext-time-update-session') {
    updateSession(message.payload || {})
      .then((result) => sendResponse(result))
      .catch((error) => sendResponse({ success: false, error: error && error.message ? error.message : String(error) }));
    return true;
  }

  if (message.type === 'cmx-ext-time-stop-session') {
    stopSession('manual')
      .then((result) => sendResponse(result))
      .catch((error) => sendResponse({ success: false, error: error && error.message ? error.message : String(error) }));
    return true;
  }
});
JS;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_render_zip')) {
	function cmx_ext_time_render_zip(): void {
		if (!\class_exists('\\ZipArchive')) {
			\wp_die('ZipArchive ist auf diesem Server nicht verfügbar.');
		}

		$tmp = \wp_tempnam('cmx-ext-time');
		if (!$tmp) {
			\wp_die('Temporäre ZIP-Datei konnte nicht erstellt werden.');
		}

		$zip = new \ZipArchive();
		if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
			@unlink($tmp);
			\wp_die('ZIP-Datei konnte nicht geöffnet werden.');
		}

		$zip->addFromString('manifest.json', cmx_ext_time_manifest_json());
		$zip->addFromString('config.js', cmx_ext_time_config_js());
		$zip->addFromString('popup.html', cmx_ext_time_popup_html());
		$zip->addFromString('popup.js', cmx_ext_time_popup_js());
		$zip->addFromString('options.html', cmx_ext_time_options_html());
		$zip->addFromString('options.js', cmx_ext_time_options_js());
		$zip->addFromString('service_worker.js', cmx_ext_time_service_worker_js());
		$zip->addFromString('README.txt', cmx_ext_time_readme_txt());

		foreach ([16, 32, 48, 128] as $size) {
			$png = cmx_ext_time_icon_png((int) $size);
			if ($png !== '') {
				$zip->addFromString('icon' . $size . '.png', $png);
			}
		}

		$zip->close();

		$filename = 'misbuero-zeit-erfassung-chrome.zip';
		\header('Content-Type: application/zip');
		\header('Content-Disposition: attachment; filename="' . $filename . '"');
		\header('Content-Length: ' . (string) \filesize($tmp));
		\header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		\header('Pragma: no-cache');
		\readfile($tmp);
		@unlink($tmp);
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_write_extension_dir')) {
	function cmx_ext_time_write_extension_dir(string $dir): bool {
		$dir = \wp_normalize_path($dir);
		if ($dir === '' || !\wp_mkdir_p($dir)) {
			return false;
		}

		$files = [
			'manifest.json'    => cmx_ext_time_manifest_json(),
			'config.js'        => cmx_ext_time_config_js(),
			'popup.html'       => cmx_ext_time_popup_html(),
			'popup.js'         => cmx_ext_time_popup_js(),
			'options.html'     => cmx_ext_time_options_html(),
			'options.js'       => cmx_ext_time_options_js(),
			'service_worker.js'=> cmx_ext_time_service_worker_js(),
			'README.txt'       => cmx_ext_time_readme_txt(),
		];

		foreach ($files as $name => $contents) {
			if (@\file_put_contents($dir . '/' . $name, $contents) === false) {
				return false;
			}
		}

		foreach ([16, 32, 48, 128] as $size) {
			$png = cmx_ext_time_icon_png((int) $size);
			if ($png === '' || @\file_put_contents($dir . '/icon' . $size . '.png', $png) === false) {
				return false;
			}
		}

		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_render_crx')) {
	function cmx_ext_time_render_crx(): void {
		if (!cmx_ext_time_crx_supported()) {
			\wp_die('CRX-Erzeugung ist auf diesem System nicht verfügbar.');
		}

		$temp_root = \wp_normalize_path(\trailingslashit(\get_temp_dir()) . 'cmx-ext-time-' . \wp_generate_password(12, false, false));
		$bundle_dir = $temp_root . '/extension';
		$key_file = $temp_root . '/extension.pem';
		$crx_file = $bundle_dir . '.crx';

		try {
			if (!\wp_mkdir_p($bundle_dir)) {
				throw new \RuntimeException('Temporäres Verzeichnis konnte nicht erstellt werden.');
			}
			if (!cmx_ext_time_write_extension_dir($bundle_dir)) {
				throw new \RuntimeException('Erweiterungsdateien konnten nicht erzeugt werden.');
			}

			$key_pem = cmx_ext_time_crx_private_key_pem();
			if ($key_pem === '' || @\file_put_contents($key_file, $key_pem) === false) {
				throw new \RuntimeException('CRX-Schlüssel konnte nicht bereitgestellt werden.');
			}

			$chrome = cmx_ext_time_crx_chrome_binary();
			if ($chrome === '') {
				throw new \RuntimeException('Google Chrome wurde nicht gefunden.');
			}

			$output = [];
			$exit_code = 1;
			$command = \escapeshellarg($chrome)
				. ' --pack-extension=' . \escapeshellarg($bundle_dir)
				. ' --pack-extension-key=' . \escapeshellarg($key_file);
			@exec($command . ' 2>&1', $output, $exit_code);

			if ($exit_code !== 0 || !\is_file($crx_file)) {
				$details = \trim(\implode("\n", \array_slice((array) $output, 0, 3)));
				throw new \RuntimeException($details !== '' ? $details : 'CRX-Datei konnte nicht erzeugt werden.');
			}

			$filename = 'misbuero-zeit-erfassung-chrome.crx';
			\header('Content-Type: application/x-chrome-extension');
			\header('Content-Disposition: attachment; filename="' . $filename . '"');
			\header('Content-Length: ' . (string) \filesize($crx_file));
			\header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
			\header('Pragma: no-cache');
			\readfile($crx_file);
			exit;
		} catch (\Throwable $exception) {
			\wp_die('CRX-Datei konnte nicht erstellt werden: ' . \esc_html($exception->getMessage()));
		} finally {
			cmx_ext_time_crx_remove_dir($temp_root);
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_download_handler')) {
	function cmx_ext_time_download_handler(): void {
		if (!\current_user_can('manage_options')) {
			\wp_die('Keine Berechtigung.');
		}
		if (!isset($_GET['_wpnonce']) || !\wp_verify_nonce((string) \wp_unslash($_GET['_wpnonce']), CMX_EXT_TIME_DOWNLOAD_ACTION)) {
			\wp_die('Ungültige Anfrage.');
		}
		cmx_ext_time_render_zip();
	}
	\add_action('admin_post_' . CMX_EXT_TIME_DOWNLOAD_ACTION, __NAMESPACE__ . '\\cmx_ext_time_download_handler');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_time_download_crx_handler')) {
	function cmx_ext_time_download_crx_handler(): void {
		if (!\current_user_can('manage_options')) {
			\wp_die('Keine Berechtigung.');
		}
		if (!isset($_GET['_wpnonce']) || !\wp_verify_nonce((string) \wp_unslash($_GET['_wpnonce']), CMX_EXT_TIME_DOWNLOAD_CRX_ACTION)) {
			\wp_die('Ungültige Anfrage.');
		}
		cmx_ext_time_render_crx();
	}
	\add_action('admin_post_' . CMX_EXT_TIME_DOWNLOAD_CRX_ACTION, __NAMESPACE__ . '\\cmx_ext_time_download_crx_handler');
}
