<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_EXT_CHROME_USER_TOKEN_META')) {
	\define(__NAMESPACE__ . '\\CMX_EXT_CHROME_USER_TOKEN_META', '_cmx_ext_chrome_token');
}

if (!\defined(__NAMESPACE__ . '\\CMX_EXT_CHROME_DOWNLOAD_ACTION')) {
	\define(__NAMESPACE__ . '\\CMX_EXT_CHROME_DOWNLOAD_ACTION', 'cmx_ext_chrome_download');
}

if (!\defined(__NAMESPACE__ . '\\CMX_EXT_CHROME_DOWNLOAD_CRX_ACTION')) {
	\define(__NAMESPACE__ . '\\CMX_EXT_CHROME_DOWNLOAD_CRX_ACTION', 'cmx_ext_chrome_download_crx');
}

if (!\defined(__NAMESPACE__ . '\\CMX_EXT_CHROME_UPLOAD_ACTION')) {
	\define(__NAMESPACE__ . '\\CMX_EXT_CHROME_UPLOAD_ACTION', 'cmx_ext_chrome_import_pdf');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_belege_uploads_meta_key')) {
	function cmx_ext_chrome_belege_uploads_meta_key(): string {
		return \defined(__NAMESPACE__ . '\\CMX_BELEG_UPLOADS_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_UPLOADS_META')
			: '_cmx_belege_uploads';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_plugin_version')) {
	function cmx_ext_chrome_plugin_version(): string {
		$version = \defined(__NAMESPACE__ . '\\CMX_VERSION') ? (string) \constant(__NAMESPACE__ . '\\CMX_VERSION') : '2.6.5';
		if (!\preg_match('/^\d+(?:\.\d+){0,3}$/', $version)) {
			$version = '2.6.5';
		}
		return $version;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_current_user_token')) {
	function cmx_ext_chrome_current_user_token(int $user_id = 0): string {
		$user_id = $user_id > 0 ? $user_id : \get_current_user_id();
		if ($user_id <= 0) {
			return '';
		}

		$token = \trim((string) \get_user_meta($user_id, CMX_EXT_CHROME_USER_TOKEN_META, true));
		if ($token !== '') {
			return $token;
		}

		try {
			$token = 'cmxext_' . \bin2hex(\random_bytes(24));
		} catch (\Throwable $exception) {
			$token = 'cmxext_' . \wp_generate_password(48, false, false);
		}
		\update_user_meta($user_id, CMX_EXT_CHROME_USER_TOKEN_META, $token);

		return $token;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_request_token')) {
	function cmx_ext_chrome_request_token(): string {
		$server = (array) ($_SERVER ?? []);
		$token = '';
		foreach (['HTTP_X_CMX_EXTENSION_TOKEN', 'REDIRECT_HTTP_X_CMX_EXTENSION_TOKEN'] as $key) {
			if (!empty($server[$key])) {
				$token = (string) $server[$key];
				break;
			}
		}
		if ($token === '' && isset($_POST['token'])) {
			$token = (string) \wp_unslash($_POST['token']);
		}
		return \trim($token);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_validate_current_user_token')) {
	function cmx_ext_chrome_validate_current_user_token(): bool {
		$user_id = \get_current_user_id();
		if ($user_id <= 0) {
			return false;
		}
		$provided = cmx_ext_chrome_request_token();
		if ($provided === '') {
			return false;
		}
		$expected = \trim((string) \get_user_meta($user_id, CMX_EXT_CHROME_USER_TOKEN_META, true));
		if ($expected === '') {
			return false;
		}
		return \hash_equals($expected, $provided);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_download_url')) {
	function cmx_ext_chrome_download_url(): string {
		return (string) \add_query_arg(
			[
				'action' => CMX_EXT_CHROME_DOWNLOAD_ACTION,
				'_wpnonce' => \wp_create_nonce(CMX_EXT_CHROME_DOWNLOAD_ACTION),
			],
			\admin_url('admin-post.php')
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_download_crx_url')) {
	function cmx_ext_chrome_download_crx_url(): string {
		return (string) \add_query_arg(
			[
				'action' => CMX_EXT_CHROME_DOWNLOAD_CRX_ACTION,
				'_wpnonce' => \wp_create_nonce(CMX_EXT_CHROME_DOWNLOAD_CRX_ACTION),
			],
			\admin_url('admin-post.php')
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_crx_chrome_binary')) {
	function cmx_ext_chrome_crx_chrome_binary(): string {
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_crx_supported')) {
	function cmx_ext_chrome_crx_supported(): bool {
		return cmx_ext_chrome_crx_chrome_binary() !== ''
			&& \function_exists('exec')
			&& \function_exists('openssl_pkey_new')
			&& \function_exists('openssl_pkey_export');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_crx_private_key_pem')) {
	function cmx_ext_chrome_crx_private_key_pem(): string {
		$option_key = 'cmx_ext_chrome_crx_private_key_pem';
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_crx_remove_dir')) {
	function cmx_ext_chrome_crx_remove_dir(string $path): void {
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
				cmx_ext_chrome_crx_remove_dir($path . '/' . $item);
			}
		}
		@rmdir($path);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_upload_url')) {
	function cmx_ext_chrome_upload_url(): string {
		return (string) \admin_url('admin-ajax.php?action=' . CMX_EXT_CHROME_UPLOAD_ACTION);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_icon_asset_path')) {
	function cmx_ext_chrome_icon_asset_path(): string {
		return \wp_normalize_path(\dirname(__DIR__, 2) . '/assets/ext_pdf.png');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_icon_asset_url')) {
	function cmx_ext_chrome_icon_asset_url(): string {
		return (string) \plugins_url('assets/ext_pdf.png', \dirname(__DIR__, 2) . '/cmx-misbuero.php');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_icon_png')) {
	function cmx_ext_chrome_icon_png(int $size, bool $disabled = false): string {
		$size = \max(16, (int) $size);
		$asset_path = cmx_ext_chrome_icon_asset_path();
		if (\is_file($asset_path)) {
			$source = @\imagecreatefrompng($asset_path);
			if ($source instanceof \GdImage || \is_resource($source)) {
				$src_w = (int) \imagesx($source);
				$src_h = (int) \imagesy($source);
				if ($src_w > 0 && $src_h > 0) {
					$image = \imagecreatetruecolor($size, $size);
					\imagealphablending($image, false);
					\imagesavealpha($image, true);
					$transparent = \imagecolorallocatealpha($image, 0, 0, 0, 127);
					\imagefill($image, 0, 0, $transparent);
					\imagecopyresampled($image, $source, 0, 0, 0, 0, $size, $size, $src_w, $src_h);
					if ($disabled && \function_exists('imagefilter')) {
						@\imagefilter($image, \IMG_FILTER_GRAYSCALE);
						@\imagefilter($image, \IMG_FILTER_BRIGHTNESS, -12);
						@\imagefilter($image, \IMG_FILTER_CONTRAST, 8);
					}
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

		$image = \imagecreatetruecolor($size, $size);
		\imagealphablending($image, false);
		\imagesavealpha($image, true);

		$transparent = \imagecolorallocatealpha($image, 0, 0, 0, 127);
		\imagefill($image, 0, 0, $transparent);

		$outline = \imagecolorallocate($image, 59, 43, 38);
		$white = \imagecolorallocate($image, 255, 255, 255);
		$black = \imagecolorallocate($image, 31, 26, 23);
		$pink = \imagecolorallocate($image, 246, 167, 183);
		$horn = \imagecolorallocate($image, 243, 210, 122);

		$cx = (int) \round($size * 0.5);
		$cy = (int) \round($size * 0.52);
		$head_w = (int) \round($size * 0.66);
		$head_h = (int) \round($size * 0.68);
		$ear_w = (int) \round($size * 0.16);
		$ear_h = (int) \round($size * 0.22);
		$horn_w = (int) \round($size * 0.12);
		$horn_h = (int) \round($size * 0.12);

		\imagefilledellipse($image, (int) \round($size * 0.28), (int) \round($size * 0.25), $horn_w, $horn_h, $horn);
		\imagefilledellipse($image, (int) \round($size * 0.72), (int) \round($size * 0.25), $horn_w, $horn_h, $horn);
		\imageellipse($image, (int) \round($size * 0.28), (int) \round($size * 0.25), $horn_w, $horn_h, $outline);
		\imageellipse($image, (int) \round($size * 0.72), (int) \round($size * 0.25), $horn_w, $horn_h, $outline);

		\imagefilledellipse($image, (int) \round($size * 0.16), (int) \round($size * 0.5), $ear_w, $ear_h, $white);
		\imagefilledellipse($image, (int) \round($size * 0.84), (int) \round($size * 0.5), $ear_w, $ear_h, $white);
		\imageellipse($image, (int) \round($size * 0.16), (int) \round($size * 0.5), $ear_w, $ear_h, $outline);
		\imageellipse($image, (int) \round($size * 0.84), (int) \round($size * 0.5), $ear_w, $ear_h, $outline);

		\imagefilledellipse($image, $cx, $cy, $head_w, $head_h, $white);
		\imageellipse($image, $cx, $cy, $head_w, $head_h, $outline);

		\imagefilledellipse($image, (int) \round($size * 0.36), (int) \round($size * 0.3), (int) \round($size * 0.22), (int) \round($size * 0.18), $black);
		\imagefilledellipse($image, (int) \round($size * 0.66), (int) \round($size * 0.22), (int) \round($size * 0.16), (int) \round($size * 0.14), $black);

		$eye_r = \max(1, (int) \round($size * 0.04));
		\imagefilledellipse($image, (int) \round($size * 0.41), (int) \round($size * 0.48), $eye_r * 2, $eye_r * 2, $outline);
		\imagefilledellipse($image, (int) \round($size * 0.59), (int) \round($size * 0.48), $eye_r * 2, $eye_r * 2, $outline);

		$nose_w = (int) \round($size * 0.34);
		$nose_h = (int) \round($size * 0.2);
		$nose_x = $cx;
		$nose_y = (int) \round($size * 0.67);
		\imagefilledellipse($image, $nose_x, $nose_y, $nose_w, $nose_h, $pink);
		\imageellipse($image, $nose_x, $nose_y, $nose_w, $nose_h, $outline);
		\imagefilledellipse($image, (int) \round($size * 0.46), $nose_y, \max(2, (int) \round($size * 0.04)), \max(3, (int) \round($size * 0.06)), $outline);
		\imagefilledellipse($image, (int) \round($size * 0.54), $nose_y, \max(2, (int) \round($size * 0.04)), \max(3, (int) \round($size * 0.06)), $outline);

		\imageline($image, (int) \round($size * 0.45), (int) \round($size * 0.79), (int) \round($size * 0.5), (int) \round($size * 0.82), $outline);
		\imageline($image, (int) \round($size * 0.55), (int) \round($size * 0.79), (int) \round($size * 0.5), (int) \round($size * 0.82), $outline);
		if ($disabled && \function_exists('imagefilter')) {
			@\imagefilter($image, \IMG_FILTER_GRAYSCALE);
			@\imagefilter($image, \IMG_FILTER_BRIGHTNESS, -12);
			@\imagefilter($image, \IMG_FILTER_CONTRAST, 8);
		}

		\ob_start();
		\imagepng($image);
		$png = (string) \ob_get_clean();
		\imagedestroy($image);

		return $png;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_is_png_bytes')) {
	function cmx_ext_chrome_is_png_bytes(string $bytes): bool {
		return $bytes !== '' && \strncmp($bytes, "\x89PNG\r\n\x1a\n", 8) === 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_icon_zip_bytes')) {
	function cmx_ext_chrome_icon_zip_bytes(int $size, bool $disabled = false): string {
		$png = cmx_ext_chrome_icon_png($size, $disabled);
		if (cmx_ext_chrome_is_png_bytes($png)) {
			return $png;
		}

		$asset_path = cmx_ext_chrome_icon_asset_path();
		if (\is_file($asset_path)) {
			$asset_bytes = (string) \file_get_contents($asset_path);
			if (cmx_ext_chrome_is_png_bytes($asset_bytes)) {
				return $asset_bytes;
			}
		}

		if ($disabled) {
			$fallback = cmx_ext_chrome_icon_png($size, false);
			if (cmx_ext_chrome_is_png_bytes($fallback)) {
				return $fallback;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_extension_config')) {
	function cmx_ext_chrome_extension_config(int $user_id = 0): array {
		$user_id = $user_id > 0 ? $user_id : \get_current_user_id();
		$user = $user_id > 0 ? \get_userdata($user_id) : null;

		return [
			'siteUrl'     => (string) \home_url('/'),
			'siteName'    => (string) \get_bloginfo('name'),
			'uploadUrl'   => cmx_ext_chrome_upload_url(),
			'token'       => cmx_ext_chrome_current_user_token($user_id),
			'userLogin'   => $user instanceof \WP_User ? (string) $user->user_login : '',
			'editBaseUrl' => (string) \admin_url('post.php'),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_manifest_json')) {
	function cmx_ext_chrome_manifest_json(array $config): string {
		$manifest = [
			'manifest_version' => 3,
			'name' => 'Mis Büro - PDF Import',
			'short_name' => 'Mis Büro',
			'version' => cmx_ext_chrome_plugin_version(),
			'description' => 'Importiert das aktuell angezeigte PDF direkt als neuen Beleg in Mis Büro.',
			'permissions' => ['activeTab', 'tabs', 'scripting'],
			'host_permissions' => ['<all_urls>'],
			'background' => [
				'service_worker' => 'service_worker.js',
			],
			'action' => [
				'default_title' => 'PDF an Mis Büro senden',
				'default_popup' => 'popup.html',
				'default_icon' => [
					'16' => 'icon-disabled16.png',
					'32' => 'icon-disabled32.png',
					'48' => 'icon-disabled48.png',
					'128' => 'icon-disabled128.png',
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_config_js')) {
	function cmx_ext_chrome_config_js(array $config): string {
		return 'self.CMX_MISBUERO_CONFIG = ' . (string) \wp_json_encode($config, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) . ';' . "\n";
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_popup_html')) {
	function cmx_ext_chrome_popup_html(): string {
		return '<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mis Büro - PDF Import</title>
<style>
body{margin:0;font:13px/1.45 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f6f7fb;color:#1d2327;min-width:320px}
.wrap{padding:14px}
.head{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.icon{width:42px;height:42px;display:flex;align-items:center;justify-content:center;background:#fff;border:1px solid #dcdcde;border-radius:12px}
.title{font-weight:700;font-size:15px}
.status{padding:10px 12px;border-radius:10px;background:#fff;border:1px solid #dcdcde;min-height:44px}
.status.is-error{border-color:#d63638;background:#fff1f1}
.status.is-success{border-color:#00a32a;background:#f2fff4}
.actions{display:flex;gap:8px;margin-top:12px}
button{appearance:none;border:1px solid #2271b1;background:#2271b1;color:#fff;border-radius:8px;padding:8px 10px;cursor:pointer}
button.secondary{background:#fff;color:#2271b1}
button[hidden]{display:none}
.hint{margin-top:10px;color:#646970;font-size:12px}
a{color:#2271b1}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <div class="icon"><img src="icon48.png" alt="" width="38" height="38"></div>
    <div>
      <div class="title">Mis Büro</div>
      <div>PDF Import</div>
    </div>
  </div>
  <div id="status" class="status">Prüfe aktuelle PDF-Vorschau...</div>
  <div class="actions">
    <button id="retry" class="secondary" type="button" hidden>Nochmals versuchen</button>
  </div>
  <div class="hint">Es wird ein neuer Beleg in Mis Büro angelegt und danach direkt geöffnet.</div>
</div>
<script src="config.js"></script>
<script src="popup.js"></script>
</body>
</html>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_popup_js')) {
	function cmx_ext_chrome_popup_js(): string {
		return <<<'JS'
const statusEl = document.getElementById('status');
const retryBtn = document.getElementById('retry');

function setStatus(text, type = '') {
  statusEl.textContent = text || '';
  statusEl.classList.remove('is-error', 'is-success');
  if (type === 'error') statusEl.classList.add('is-error');
  if (type === 'success') statusEl.classList.add('is-success');
}

async function runImport() {
  retryBtn.hidden = true;
  setStatus('PDF wird an Mis Büro gesendet...');
  let response;
  try {
    response = await chrome.runtime.sendMessage({ type: 'cmx-import-active-pdf' });
  } catch (error) {
    setStatus('Die Erweiterung konnte den Import nicht starten.', 'error');
    retryBtn.hidden = false;
    return;
  }

  if (!response || !response.success) {
    const message = response && response.error ? response.error : 'Aktuell ist keine PDF-Vorschau aktiv.';
    setStatus(message, 'error');
    retryBtn.hidden = false;
    return;
  }

  const result = response.result || {};
  setStatus('Beleg wurde erstellt. Öffne Bearbeitung...', 'success');
  const editUrl = result.editUrl || result.edit_url || '';
  if (editUrl) {
    try {
      await chrome.tabs.create({ url: editUrl });
      window.close();
      return;
    } catch (error) {
      setStatus('Beleg wurde erstellt, konnte aber nicht automatisch geöffnet werden.', 'success');
    }
  }
  retryBtn.hidden = false;
}

retryBtn.addEventListener('click', runImport);
document.addEventListener('DOMContentLoaded', runImport);
JS;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_service_worker_js')) {
	function cmx_ext_chrome_service_worker_js(): string {
		return <<<'JS'
importScripts('config.js');

const CONFIG = self.CMX_MISBUERO_CONFIG || {};

function urlLooksLikePdf(url) {
  if (!url) return false;
  try {
    const parsed = new URL(url);
    if ((parsed.pathname || '').toLowerCase().endsWith('.pdf')) return true;
    for (const key of ['file', 'filename', 'src']) {
      const value = parsed.searchParams.get(key) || '';
      if (value.toLowerCase().includes('.pdf')) return true;
    }
  } catch (error) {
    return /\.pdf(?:$|[?#])/i.test(url);
  }
  return false;
}

function extractViewerSource(url) {
  if (!url) return '';
  try {
    const parsed = new URL(url);
    const src = parsed.searchParams.get('src') || parsed.searchParams.get('file') || '';
    if (parsed.protocol === 'chrome-extension:' && src !== '') {
      return src;
    }
  } catch (error) {}
  return '';
}

function canScriptUrl(url) {
  return /^https?:/i.test(url || '') || /^file:/i.test(url || '');
}

async function executeInTab(tabId, func, args = []) {
  const results = await chrome.scripting.executeScript({
    target: { tabId },
    func,
    args,
  });
  return results && results[0] ? results[0].result : null;
}

async function probeTabForPdf(tabId) {
  return await executeInTab(tabId, () => {
    const absolute = (raw) => {
      try {
        return new URL(raw, window.location.href).toString();
      } catch (error) {
        return raw || '';
      }
    };

    const title = document.title || '';
    const contentType = (document.contentType || '').toLowerCase();
    if (contentType.includes('pdf')) {
      return { isPdf: true, url: window.location.href, title };
    }

    const pick = [];
    for (const node of document.querySelectorAll('embed, object, iframe')) {
      const raw = node.getAttribute('src') || node.getAttribute('data') || '';
      if (!raw) continue;
      const resolved = absolute(raw);
      if (resolved.startsWith('blob:') || /\.pdf(?:$|[?#])/i.test(resolved)) {
        pick.push(resolved);
      }
    }

    const candidate = pick[0] || '';
    return { isPdf: candidate !== '', url: candidate, title };
  });
}

function filenameFromValue(value) {
  let cleaned = String(value || '').trim();
  cleaned = cleaned.replace(/[\s]+/g, ' ');
  cleaned = cleaned.replace(/[\\/:*?"<>|]+/g, '-');
  cleaned = cleaned.replace(/\.+$/g, '');
  if (!cleaned) cleaned = 'beleg';
  if (!/\.pdf$/i.test(cleaned)) cleaned += '.pdf';
  return cleaned;
}

function filenameFromUrl(url, pageTitle) {
  try {
    const parsed = new URL(url);
    const file = decodeURIComponent((parsed.pathname || '').split('/').pop() || '');
    if (file) return filenameFromValue(file);
  } catch (error) {}
  return filenameFromValue(pageTitle || 'beleg');
}

function base64ToBytes(base64) {
  const binary = atob(base64 || '');
  const bytes = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i++) {
    bytes[i] = binary.charCodeAt(i);
  }
  return bytes;
}

function bytesLookLikePdf(bytes) {
  return !!(bytes && bytes.length >= 4 && bytes[0] === 0x25 && bytes[1] === 0x50 && bytes[2] === 0x44 && bytes[3] === 0x46);
}

async function fetchPdfViaPage(tabId, resourceUrl) {
  const result = await executeInTab(tabId, async (url) => {
    try {
      const response = await fetch(url, { credentials: 'include', cache: 'no-store' });
      const blob = await response.blob();
      const buffer = await blob.arrayBuffer();
      const bytes = new Uint8Array(buffer);
      let binary = '';
      const chunkSize = 0x8000;
      for (let i = 0; i < bytes.length; i += chunkSize) {
        binary += String.fromCharCode(...bytes.slice(i, i + chunkSize));
      }
      return {
        ok: response.ok,
        status: response.status,
        type: blob.type || '',
        base64: btoa(binary),
      };
    } catch (error) {
      return {
        ok: false,
        status: 0,
        error: error && error.message ? error.message : String(error),
      };
    }
  }, [resourceUrl]);

  if (!result || !result.ok || !result.base64) {
    throw new Error((result && result.error) || 'Die PDF-Datei konnte nicht aus der Seite gelesen werden.');
  }

  return {
    bytes: base64ToBytes(result.base64),
    type: result.type || 'application/pdf',
  };
}

async function resolvePdfTarget(tab) {
  if (!tab || !tab.id) return null;

  const viewerSource = extractViewerSource(tab.url || '');
  if (viewerSource) {
    return {
      url: viewerSource,
      pageUrl: tab.url || '',
      pageTitle: tab.title || '',
    };
  }

  if ((tab.url || '').startsWith('blob:')) {
    return {
      url: tab.url,
      pageUrl: tab.url,
      pageTitle: tab.title || '',
    };
  }

  if (urlLooksLikePdf(tab.url || '')) {
    return {
      url: tab.url,
      pageUrl: tab.url || '',
      pageTitle: tab.title || '',
    };
  }

  if (canScriptUrl(tab.url || '')) {
    try {
      const probe = await probeTabForPdf(tab.id);
      if (probe && probe.isPdf && probe.url) {
        return {
          url: probe.url,
          pageUrl: tab.url || '',
          pageTitle: probe.title || tab.title || '',
        };
      }
    } catch (error) {}
  }

  return null;
}

async function fetchPdfPayload(tab, target) {
  if (!tab || !tab.id || !target || !target.url) {
    throw new Error('Keine PDF-Datei gefunden.');
  }

  if (target.url.startsWith('blob:') || canScriptUrl(tab.url || '')) {
    try {
      const pagePayload = await fetchPdfViaPage(tab.id, target.url);
      if (bytesLookLikePdf(pagePayload.bytes)) {
        return pagePayload;
      }
    } catch (error) {}
  }

  const response = await fetch(target.url, {
    method: 'GET',
    credentials: 'include',
    cache: 'no-store',
  });
  if (!response.ok) {
    throw new Error('Die PDF-Datei konnte nicht geladen werden (' + response.status + ').');
  }

  const bytes = new Uint8Array(await response.arrayBuffer());
  if (!bytesLookLikePdf(bytes)) {
    throw new Error('Die aktuelle Vorschau liefert keine echte PDF-Datei.');
  }

  return {
    bytes,
    type: response.headers.get('content-type') || 'application/pdf',
  };
}

async function uploadToMisBuero(payload) {
  const formData = new FormData();
  formData.append('file', new Blob([payload.bytes], {
    type: payload.type || 'application/pdf',
  }), payload.filename);
  formData.append('source_url', payload.sourceUrl || '');
  formData.append('page_url', payload.pageUrl || '');
  formData.append('source_title', payload.pageTitle || '');

  const response = await fetch(CONFIG.uploadUrl, {
    method: 'POST',
    credentials: 'include',
    headers: {
      'X-CMX-Extension-Token': CONFIG.token || '',
    },
    body: formData,
  });

  let json = null;
  try {
    json = await response.json();
  } catch (error) {
    throw new Error('Mis Büro antwortet nicht mit einem gültigen JSON.');
  }

  if (!response.ok || !json || !json.success) {
    const message = json && json.data && json.data.message ? json.data.message : 'Der Import in Mis Büro ist fehlgeschlagen.';
    throw new Error(message);
  }

  return json.data || {};
}

async function importActivePdf() {
  const tabs = await chrome.tabs.query({ active: true, currentWindow: true });
  const tab = tabs && tabs[0] ? tabs[0] : null;
  if (!tab || !tab.id) {
    throw new Error('Kein aktiver Tab gefunden.');
  }

  const target = await resolvePdfTarget(tab);
  if (!target || !target.url) {
    throw new Error('Aktuell ist keine PDF-Vorschau aktiv.');
  }

  const payload = await fetchPdfPayload(tab, target);
  const result = await uploadToMisBuero({
    bytes: payload.bytes,
    type: payload.type,
    filename: filenameFromUrl(target.url, target.pageTitle || tab.title || ''),
    sourceUrl: target.url,
    pageUrl: target.pageUrl || tab.url || '',
    pageTitle: target.pageTitle || tab.title || '',
  });

  return result;
}

async function updateActionForTab(tabId, tabHint = null) {
  let tab = tabHint;
  if (!tab) {
    try {
      tab = await chrome.tabs.get(tabId);
    } catch (error) {
      return;
    }
  }

  let enabled = false;
  try {
    const target = await resolvePdfTarget(tab);
    enabled = !!(target && target.url);
  } catch (error) {
    enabled = false;
  }

  try {
    if (enabled) {
      await chrome.action.setIcon({
        tabId,
        path: {
          16: 'icon16.png',
          32: 'icon32.png',
          48: 'icon48.png',
          128: 'icon128.png',
        },
      });
      await chrome.action.enable(tabId);
      await chrome.action.setTitle({ tabId, title: 'PDF an Mis Büro senden' });
    } else {
      await chrome.action.setIcon({
        tabId,
        path: {
          16: 'icon-disabled16.png',
          32: 'icon-disabled32.png',
          48: 'icon-disabled48.png',
          128: 'icon-disabled128.png',
        },
      });
      await chrome.action.disable(tabId);
      await chrome.action.setTitle({ tabId, title: 'Nur aktiv, wenn eine PDF-Vorschau geöffnet ist' });
    }
  } catch (error) {}
}

chrome.runtime.onInstalled.addListener(async () => {
  const tabs = await chrome.tabs.query({});
  await Promise.all((tabs || []).map((tab) => tab && tab.id ? updateActionForTab(tab.id, tab) : Promise.resolve()));
});

chrome.runtime.onStartup.addListener(async () => {
  const tabs = await chrome.tabs.query({});
  await Promise.all((tabs || []).map((tab) => tab && tab.id ? updateActionForTab(tab.id, tab) : Promise.resolve()));
});

chrome.tabs.onActivated.addListener((info) => {
  if (info && info.tabId) updateActionForTab(info.tabId);
});

chrome.tabs.onUpdated.addListener((tabId, changeInfo, tab) => {
  if (changeInfo.status === 'complete' || changeInfo.url) {
    updateActionForTab(tabId, tab);
  }
});

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (!message || typeof message !== 'object') {
    return;
  }

  if (message.type === 'cmx-import-active-pdf') {
    importActivePdf()
      .then((result) => sendResponse({ success: true, result }))
      .catch((error) => sendResponse({ success: false, error: error && error.message ? error.message : String(error) }));
    return true;
  }
});
JS;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_readme_txt')) {
	function cmx_ext_chrome_readme_txt(): string {
		return "Mis Büro - Google Chrome Erweiterung\n\n"
			. "Installation:\n"
			. "1. ZIP-Datei entpacken.\n"
			. "2. In Google Chrome 'chrome://extensions' aufrufen.\n"
			. "3. Oben rechts den Entwicklermodus aktivieren.\n"
			. "4. 'Entpackte Erweiterung laden' wählen.\n"
			. "5. Den entpackten Ordner auswählen.\n\n"
			. "Verwendung:\n"
			. "- Eine PDF-Vorschau in Chrome öffnen.\n"
			. "- Auf das Kuh-Icon klicken.\n"
			. "- Mis Büro legt einen neuen Beleg an und hängt die PDF als Originalbeleg an.\n";
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_add_settings_field')) {
	function cmx_ext_chrome_add_settings_field(): void {
		\add_settings_field(
			'cmx_ext_chrome',
			'Google Chrome Erweiterung',
			__NAMESPACE__ . '\\cmx_ext_chrome_render_settings_field',
			'cmx_tab_vorgaben__belege',
			'cmx_sec_vorgaben_belege'
		);
	}
	\add_action('admin_init', __NAMESPACE__ . '\\cmx_ext_chrome_add_settings_field');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_render_settings_field')) {
	function cmx_ext_chrome_render_settings_field(): void {
		if (!\current_user_can('manage_options')) {
			echo '<em>Keine Berechtigung.</em>';
			return;
		}

		$download_url = cmx_ext_chrome_download_url();
		$download_crx_url = cmx_ext_chrome_download_crx_url();
		$crx_supported = cmx_ext_chrome_crx_supported();
		$icon_url = cmx_ext_chrome_icon_asset_url();
		$readme_text = cmx_ext_chrome_readme_txt();

		echo '<div id="cmx-ext-chrome-wrap" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">';
		echo '<button type="button" class="button button-secondary" id="cmx-ext-chrome-install" aria-label="Google Chrome Erweiterung herunterladen" style="width:52px;height:52px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:14px;overflow:hidden;">';
		echo '<img src="' . \esc_url($icon_url) . '" alt="" width="42" height="42" style="display:block;object-fit:contain;">';
		echo '</button>';
		echo '<div style="min-width:260px;">';
		echo '<div style="font-weight:600;">Mis Büro - PDF Import</div>';
		echo '<div style="color:#646970;">Lädt das Erweiterungspaket für Google Chrome herunter.</div>';
		echo '<div style="margin-top:4px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">';
		echo '<button type="button" class="button-link" id="cmx-ext-chrome-help" style="padding:0;height:auto;min-height:0;">Anleitung</button>';
		if ($crx_supported) {
			echo '<button type="button" class="button-link" id="cmx-ext-chrome-install-crx" style="padding:0;height:auto;min-height:0;">CRX laden</button>';
		}
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '<div id="cmx-ext-chrome-help-box" style="display:none;margin-top:8px;padding:12px 14px;border:1px solid #dcdcde;border-radius:8px;background:#fff;">';
		echo '<pre style="margin:0;white-space:pre-wrap;font-family:monospace;">' . \esc_html($readme_text) . '</pre>';
		echo '</div>';
		echo '<div id="cmx-ext-chrome-status" style="margin-top:8px;color:#646970;"></div>';
		echo '<script>
		(function(){
			var button = document.getElementById("cmx-ext-chrome-install");
			var crxButton = document.getElementById("cmx-ext-chrome-install-crx");
			var help = document.getElementById("cmx-ext-chrome-help");
			var helpBox = document.getElementById("cmx-ext-chrome-help-box");
			var status = document.getElementById("cmx-ext-chrome-status");
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
					var message = "Das Erweiterungspaket wird heruntergeladen. Chrome erlaubt die direkte Installation ausserhalb des Chrome Web Store nicht.<br>Bitte danach in <code>chrome://extensions</code> als entpackte Erweiterung laden.";
					if (copied) {
						message += "<br><code>chrome://extensions</code> wurde in die Zwischenablage kopiert. Die Erweiterung dann auch im Chrome auswählen und anzeigen lassen.";
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
							message += "<br><code>chrome://extensions</code> wurde in die Zwischenablage kopiert. Die Erweiterung dann auch im Chrome auswählen und anzeigen lassen.";
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_render_zip')) {
	function cmx_ext_chrome_render_zip(array $config): void {
		if (!\class_exists('\\ZipArchive')) {
			\wp_die('ZipArchive ist auf diesem Server nicht verfügbar.');
		}

		$tmp = \wp_tempnam('cmx-chrome-ext');
		if (!$tmp) {
			\wp_die('Temporäre ZIP-Datei konnte nicht erstellt werden.');
		}

		$zip = new \ZipArchive();
		if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
			@unlink($tmp);
			\wp_die('ZIP-Datei konnte nicht geöffnet werden.');
		}

		$zip->addFromString('manifest.json', cmx_ext_chrome_manifest_json($config));
		$zip->addFromString('config.js', cmx_ext_chrome_config_js($config));
		$zip->addFromString('popup.html', cmx_ext_chrome_popup_html());
		$zip->addFromString('popup.js', cmx_ext_chrome_popup_js());
		$zip->addFromString('service_worker.js', cmx_ext_chrome_service_worker_js());
		$zip->addFromString('README.txt', cmx_ext_chrome_readme_txt());

		foreach ([16, 32, 48, 128] as $size) {
			$icon_png = cmx_ext_chrome_icon_zip_bytes((int) $size, false);
			$icon_disabled_png = cmx_ext_chrome_icon_zip_bytes((int) $size, true);
			if ($icon_png === '' || $icon_disabled_png === '') {
				$zip->close();
				@unlink($tmp);
				\wp_die('Icon-Datei konnte nicht erzeugt werden.');
			}
			$zip->addFromString('icon' . $size . '.png', $icon_png);
			$zip->addFromString('icon-disabled' . $size . '.png', $icon_disabled_png);
		}

		$zip->close();

		$filename = 'misbuero-chrome-erweiterung.zip';
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_write_extension_dir')) {
	function cmx_ext_chrome_write_extension_dir(string $dir, array $config): bool {
		$dir = \wp_normalize_path($dir);
		if ($dir === '' || !\wp_mkdir_p($dir)) {
			return false;
		}

		$files = [
			'manifest.json'    => cmx_ext_chrome_manifest_json($config),
			'config.js'        => cmx_ext_chrome_config_js($config),
			'popup.html'       => cmx_ext_chrome_popup_html(),
			'popup.js'         => cmx_ext_chrome_popup_js(),
			'service_worker.js'=> cmx_ext_chrome_service_worker_js(),
			'README.txt'       => cmx_ext_chrome_readme_txt(),
		];

		foreach ($files as $name => $contents) {
			if (@\file_put_contents($dir . '/' . $name, $contents) === false) {
				return false;
			}
		}

		foreach ([16, 32, 48, 128] as $size) {
			$icon_png = cmx_ext_chrome_icon_zip_bytes((int) $size, false);
			$icon_disabled_png = cmx_ext_chrome_icon_zip_bytes((int) $size, true);
			if (
				$icon_png === ''
				|| $icon_disabled_png === ''
				|| @\file_put_contents($dir . '/icon' . $size . '.png', $icon_png) === false
				|| @\file_put_contents($dir . '/icon-disabled' . $size . '.png', $icon_disabled_png) === false
			) {
				return false;
			}
		}

		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_render_crx')) {
	function cmx_ext_chrome_render_crx(array $config): void {
		if (!cmx_ext_chrome_crx_supported()) {
			\wp_die('CRX-Erzeugung ist auf diesem System nicht verfügbar.');
		}

		$temp_root = \wp_normalize_path(\trailingslashit(\get_temp_dir()) . 'cmx-ext-chrome-' . \wp_generate_password(12, false, false));
		$bundle_dir = $temp_root . '/extension';
		$key_file = $temp_root . '/extension.pem';
		$crx_file = $bundle_dir . '.crx';

		try {
			if (!\wp_mkdir_p($bundle_dir)) {
				throw new \RuntimeException('Temporäres Verzeichnis konnte nicht erstellt werden.');
			}
			if (!cmx_ext_chrome_write_extension_dir($bundle_dir, $config)) {
				throw new \RuntimeException('Erweiterungsdateien konnten nicht erzeugt werden.');
			}

			$key_pem = cmx_ext_chrome_crx_private_key_pem();
			if ($key_pem === '' || @\file_put_contents($key_file, $key_pem) === false) {
				throw new \RuntimeException('CRX-Schlüssel konnte nicht bereitgestellt werden.');
			}

			$chrome = cmx_ext_chrome_crx_chrome_binary();
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

			$filename = 'misbuero-chrome-erweiterung.crx';
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
			cmx_ext_chrome_crx_remove_dir($temp_root);
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_download_handler')) {
	function cmx_ext_chrome_download_handler(): void {
		if (!\current_user_can('manage_options')) {
			\wp_die('Keine Berechtigung.');
		}
		if (!isset($_GET['_wpnonce']) || !\wp_verify_nonce((string) \wp_unslash($_GET['_wpnonce']), CMX_EXT_CHROME_DOWNLOAD_ACTION)) {
			\wp_die('Ungültige Anfrage.');
		}
		cmx_ext_chrome_render_zip(cmx_ext_chrome_extension_config());
	}
	\add_action('admin_post_' . CMX_EXT_CHROME_DOWNLOAD_ACTION, __NAMESPACE__ . '\\cmx_ext_chrome_download_handler');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_download_crx_handler')) {
	function cmx_ext_chrome_download_crx_handler(): void {
		if (!\current_user_can('manage_options')) {
			\wp_die('Keine Berechtigung.');
		}
		if (!isset($_GET['_wpnonce']) || !\wp_verify_nonce((string) \wp_unslash($_GET['_wpnonce']), CMX_EXT_CHROME_DOWNLOAD_CRX_ACTION)) {
			\wp_die('Ungültige Anfrage.');
		}
		cmx_ext_chrome_render_crx(cmx_ext_chrome_extension_config());
	}
	\add_action('admin_post_' . CMX_EXT_CHROME_DOWNLOAD_CRX_ACTION, __NAMESPACE__ . '\\cmx_ext_chrome_download_crx_handler');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_upload_dir_parts')) {
	function cmx_ext_chrome_upload_dir_parts(int $year): array {
		if (\function_exists(__NAMESPACE__ . '\\cmx_belege_upload_dir')) {
			return (array) cmx_belege_upload_dir($year);
		}
		$base = \defined('WP_CONTENT_DIR')
			? \wp_normalize_path((string) \constant('WP_CONTENT_DIR') . '/uploads/misbuero/archiv/' . $year . '/belege')
			: '';
		$url = \content_url('/uploads/misbuero/archiv/' . $year . '/belege');
		if ($base !== '' && !\is_dir($base)) {
			\wp_mkdir_p($base);
		}
		return [$base, $url];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_next_suffix')) {
	function cmx_ext_chrome_next_suffix(string $dir_base, string $post_slug): int {
		if (\function_exists(__NAMESPACE__ . '\\cmx_belege_next_suffix')) {
			return (int) cmx_belege_next_suffix($dir_base, $post_slug);
		}
		$max = 0;
		foreach ((array) (\glob(\rtrim($dir_base, '/\\') . '/' . $post_slug . '_upload_*') ?: []) as $path) {
			$base = \basename((string) $path);
			if (\preg_match('/_upload_([0-9]{3})/i', $base, $match)) {
				$max = \max($max, (int) $match[1]);
			}
		}
		return $max + 1;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_create_beleg_draft')) {
	function cmx_ext_chrome_create_beleg_draft(string $source_title = ''): int {
		if (!\post_type_exists('belege')) {
			return 0;
		}

		$base_title = \trim(\preg_replace('/\s+/', ' ', \wp_strip_all_tags($source_title)));
		$base_title = (string) \preg_replace('/^rechnung(?:\s*[-_]\s*|\s+)/i', '', $base_title);
		$base_title = \trim($base_title);
		if ($base_title === '') {
			$base_title = 'PDF Import ' . \wp_date('Y-m-d H:i');
		}

		$post_id = (int) \wp_insert_post([
			'post_type' => 'belege',
			'post_status' => 'draft',
			'post_title' => $base_title,
		], true);

		if ($post_id > 0) {
			$invoice_no = \function_exists(__NAMESPACE__ . '\\cmx_ensure_rechnungsnummer')
				? (string) cmx_ensure_rechnungsnummer($post_id)
				: '';
			if ($invoice_no !== '') {
				\wp_update_post([
					'ID' => $post_id,
					'post_title' => $invoice_no,
					'post_name' => \sanitize_title($invoice_no),
				]);
				\update_post_meta($post_id, '_cmx_title_auto', 1);
			}

			\update_post_meta($post_id, '_cmx_beleg_richtung', 'eingang');

			$tax = \function_exists(__NAMESPACE__ . '\\cmx_belege_tax')
				? (string) cmx_belege_tax()
				: '';
			if ($tax === '') {
				foreach (['belege_kategorien', 'beleg_kategorie'] as $candidate) {
					if (\taxonomy_exists($candidate)) {
						$tax = $candidate;
						break;
					}
				}
			}
			if ($tax !== '' && \taxonomy_exists($tax)) {
				$term = \get_term_by('slug', 'rechnung', $tax);
				if ($term instanceof \WP_Term) {
					\wp_set_object_terms($post_id, [(int) $term->term_id], $tax, false);
				}
			}
		}

		return $post_id > 0 ? $post_id : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_attach_pdf_to_beleg')) {
	function cmx_ext_chrome_attach_pdf_to_beleg(int $post_id, array $file): array {
		if ($post_id <= 0 || \get_post_type($post_id) !== 'belege') {
			throw new \RuntimeException('Ungültiger Beleg.');
		}
		if (empty($file['tmp_name']) || !\is_uploaded_file((string) $file['tmp_name'])) {
			throw new \RuntimeException('Keine hochgeladene PDF-Datei empfangen.');
		}

		$ext = \strtolower((string) \pathinfo((string) ($file['name'] ?? ''), \PATHINFO_EXTENSION));
		if ($ext !== 'pdf') {
			throw new \RuntimeException('Nur PDF-Dateien sind erlaubt.');
		}

		require_once \ABSPATH . 'wp-admin/includes/file.php';
		require_once \ABSPATH . 'wp-admin/includes/media.php';
		require_once \ABSPATH . 'wp-admin/includes/image.php';

		$post = \get_post($post_id);
		if (!$post instanceof \WP_Post) {
			throw new \RuntimeException('Beleg nicht gefunden.');
		}

		$year = \function_exists(__NAMESPACE__ . '\\cmx_get_beleg_upload_year')
			? (int) cmx_get_beleg_upload_year($post_id)
			: (int) \wp_date('Y');
		$post_title = \trim((string) $post->post_title);
		if ($post_title === '') {
			$post_title = 'PDF Import ' . \wp_date('Y-m-d H:i');
		}
		$post_slug = \sanitize_title($post_title);
		if ($post_slug === '') {
			$post_slug = 'beleg-' . $post_id;
		}
		\update_post_meta($post_id, '_cmx_beleg_upload_prefix', $post_slug);

		[$dir_base, $base_url] = cmx_ext_chrome_upload_dir_parts($year);
		if ($dir_base === '' || $base_url === '') {
			throw new \RuntimeException('Upload-Verzeichnis konnte nicht vorbereitet werden.');
		}

		$upload_filter = static function (array $dirs) use ($dir_base, $base_url): array {
			$dirs['path'] = $dir_base;
			$dirs['basedir'] = $dir_base;
			$dirs['url'] = $base_url;
			$dirs['baseurl'] = $base_url;
			$dirs['subdir'] = '';
			return $dirs;
		};
		$no_sizes_filter = static function ($sizes) { return []; };
		$no_meta_sizes_filter = static function ($metadata) {
			if (isset($metadata['sizes'])) {
				$metadata['sizes'] = [];
			}
			return $metadata;
		};
		$no_big_image = static function () { return false; };

		$next_suffix = cmx_ext_chrome_next_suffix($dir_base, $post_slug);
		$unique_cb = static function (string $dir, string $name, string $extension) use ($post_slug, &$next_suffix): string {
			do {
				$suffix = '_' . \str_pad((string) $next_suffix, 3, '0', \STR_PAD_LEFT);
				$filename = $post_slug . '_upload' . $suffix . $extension;
				$next_suffix++;
			} while (\file_exists($dir . '/' . $filename));
			return $filename;
		};

		\add_filter('upload_dir', $upload_filter);
		\add_filter('intermediate_image_sizes', $no_sizes_filter);
		\add_filter('intermediate_image_sizes_advanced', $no_sizes_filter);
		\add_filter('big_image_size_threshold', $no_big_image, 10, 0);

		$uploaded = \wp_handle_upload($file, [
			'test_form' => false,
			'unique_filename_callback' => $unique_cb,
			'mimes' => [
				'pdf' => 'application/pdf',
			],
		]);

		\add_filter('wp_generate_attachment_metadata', $no_meta_sizes_filter, 10, 2);
		\remove_filter('wp_generate_attachment_metadata', $no_meta_sizes_filter, 10);
		\remove_filter('big_image_size_threshold', $no_big_image, 10);
		\remove_filter('intermediate_image_sizes_advanced', $no_sizes_filter);
		\remove_filter('intermediate_image_sizes', $no_sizes_filter);
		\remove_filter('upload_dir', $upload_filter);

		if (!\is_array($uploaded) || empty($uploaded['file'])) {
			throw new \RuntimeException('Upload fehlgeschlagen.');
		}

		$rel = \ltrim((string) \str_replace(\trailingslashit((string) (\WP_CONTENT_DIR . '/uploads')), '', (string) $uploaded['file']), '/');
		$existing = (array) \get_post_meta($post_id, cmx_ext_chrome_belege_uploads_meta_key(), true);
		$existing = \array_values(\array_filter($existing, static function ($value): bool {
			return $value !== '' && $value !== null;
		}));
		$existing[] = $rel;
		$existing = \array_values(\array_unique($existing));
		\update_post_meta($post_id, cmx_ext_chrome_belege_uploads_meta_key(), $existing);

		return [
			'path' => $rel,
			'url' => \content_url('/uploads/' . $rel),
			'label' => \basename((string) $uploaded['file']),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ext_chrome_upload_response')) {
	function cmx_ext_chrome_upload_response(): void {
		if (!\is_user_logged_in() || !\current_user_can('upload_files')) {
			\wp_send_json_error(['message' => 'Bitte zuerst in Mis Büro anmelden.'], 403);
		}
		if (!cmx_ext_chrome_validate_current_user_token()) {
			\wp_send_json_error(['message' => 'Die Chrome-Erweiterung ist für diesen Benutzer nicht freigeschaltet. Bitte erneut herunterladen.'], 403);
		}
		if (empty($_FILES['file']) || !isset($_FILES['file']['tmp_name'])) {
			\wp_send_json_error(['message' => 'Keine PDF-Datei empfangen.'], 400);
		}

		$file_name = \sanitize_file_name((string) ($_FILES['file']['name'] ?? ''));
		$source_title = isset($_POST['source_title']) ? (string) \wp_unslash($_POST['source_title']) : '';
		$title_base = $file_name !== '' ? \preg_replace('/\.pdf$/i', '', $file_name) : $source_title;
		$beleg_id = cmx_ext_chrome_create_beleg_draft((string) $title_base);
		if ($beleg_id <= 0) {
			\wp_send_json_error(['message' => 'Neuer Beleg konnte nicht angelegt werden.'], 500);
		}

		try {
			cmx_ext_chrome_attach_pdf_to_beleg($beleg_id, (array) $_FILES['file']);
		} catch (\Throwable $exception) {
			\wp_delete_post($beleg_id, true);
			\wp_send_json_error(['message' => $exception->getMessage()], 500);
		}

		\wp_send_json_success([
			'post_id' => $beleg_id,
			'edit_url' => \admin_url('post.php?post=' . $beleg_id . '&action=edit'),
			'title' => (string) \get_the_title($beleg_id),
		]);
	}
	\add_action('wp_ajax_' . CMX_EXT_CHROME_UPLOAD_ACTION, __NAMESPACE__ . '\\cmx_ext_chrome_upload_response');
}
