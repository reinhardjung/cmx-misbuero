<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


if (!\function_exists(__NAMESPACE__ . '\\cmx_system_bulk_delete_notice_base_url')) {
	function cmx_system_bulk_delete_notice_base_url(): string {
		$page = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
			? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
			: 'cmx-einstellungen';

		return (string) \admin_url('admin.php?page=' . \rawurlencode($page) . '&tab=system&sub=backup');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_storage_upload_root')) {
	function cmx_system_storage_upload_root(): string {
		$uploads = \wp_get_upload_dir();
		$basedir = isset($uploads['basedir']) ? (string) $uploads['basedir'] : '';
		return \wp_normalize_path(\untrailingslashit($basedir) . '/misbuero');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_storage_dir_size')) {
	function cmx_system_storage_dir_size(string $dir): int {
		if ($dir === '' || !\is_dir($dir) || !\is_readable($dir)) {
			return 0;
		}

		$total = 0;
		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::LEAVES_ONLY
			);

			foreach ($iterator as $file) {
				if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->isLink()) {
					continue;
				}
				$total += \max(0, (int) $file->getSize());
			}
		} catch (\Throwable $e) {
			return $total;
		}

		return $total;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_storage_format_bytes')) {
	function cmx_system_storage_format_bytes(int $bytes): string {
		$bytes = \max(0, $bytes);
		$units = ['B', 'KB', 'MB', 'GB', 'TB'];
		$value = (float) $bytes;
		$unit = 'B';

		foreach ($units as $candidate) {
			$unit = $candidate;
			if ($value < 1024 || $candidate === 'TB') {
				break;
			}
			$value /= 1024;
		}

		if ($unit === 'B') {
			return \number_format_i18n($bytes, 0) . ' B';
		}

		$decimals = $value >= 10 ? 1 : 2;
		return \number_format_i18n($value, $decimals) . ' ' . $unit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_storage_label')) {
	function cmx_system_storage_label(string $name): string {
		$labels = [
			'webssite' => 'website',
			'.dav-locks' => 'dav-locks',
		];
		return $labels[$name] ?? $name;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_storage_data')) {
	function cmx_system_storage_data(): array {
		$root = cmx_system_storage_upload_root();
		$items = [];
		$total = 0;

		if (\is_dir($root) && \is_readable($root)) {
			$children = \scandir($root);
			if (\is_array($children)) {
				foreach ($children as $child) {
					if ($child === '.' || $child === '..') {
						continue;
					}
					$path = \wp_normalize_path($root . '/' . $child);
					if (!\is_dir($path)) {
						continue;
					}
					$size = cmx_system_storage_dir_size($path);
					$total += $size;
					$items[] = [
						'name' => $child,
						'label' => cmx_system_storage_label($child),
						'bytes' => $size,
					];
				}
			}
		}

		\usort($items, static function (array $left, array $right): int {
			return ((int) ($right['bytes'] ?? 0)) <=> ((int) ($left['bytes'] ?? 0));
		});

		foreach ($items as &$item) {
			$bytes = (int) ($item['bytes'] ?? 0);
			$item['formatted'] = cmx_system_storage_format_bytes($bytes);
			$item['percent'] = $total > 0 ? \round(($bytes / $total) * 100) : 0;
		}
		unset($item);

		return [
			'root' => $root,
			'total' => $total,
			'total_formatted' => cmx_system_storage_format_bytes($total),
			'items' => $items,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_storage_archive_relative_path')) {
	function cmx_system_storage_archive_relative_path(string $path): string {
		$path = \wp_normalize_path(\str_replace("\0", '', $path));
		$path = \ltrim($path, '/');
		if ($path === '') {
			return '';
		}

		$segments = [];
		foreach (\explode('/', $path) as $segment) {
			$segment = \trim($segment);
			if ($segment === '' || $segment === '.' || $segment === '..') {
				continue;
			}
			$segments[] = $segment;
		}

		return \implode('/', $segments);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_storage_archive_base_url')) {
	function cmx_system_storage_archive_base_url(): string {
		$page = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
			? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
			: 'cmx-einstellungen';

		return \admin_url('admin.php?page=' . \rawurlencode($page) . '&tab=system&sub=storage');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_storage_archive_browse_url')) {
	function cmx_system_storage_archive_browse_url(string $relative_path): string {
		$relative_path = cmx_system_storage_archive_relative_path($relative_path);
		$url = cmx_system_storage_archive_base_url();
		if ($relative_path === '') {
			return $url;
		}

		return \add_query_arg('cmx_archive_dir', $relative_path, $url);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_storage_archive_download_url')) {
	function cmx_system_storage_archive_download_url(string $relative_path): string {
		$relative_path = cmx_system_storage_archive_relative_path($relative_path);
		return \add_query_arg(
			[
				'action' => 'cmx_system_archive_download',
				'file' => $relative_path,
				'_wpnonce' => \wp_create_nonce('cmx_system_archive_download_' . $relative_path),
			],
			\admin_url('admin-post.php')
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_storage_archive_can_manage')) {
	function cmx_system_storage_archive_can_manage(): bool {
		return \function_exists(__NAMESPACE__ . '\\cmx_settings_current_user_can_access')
			? cmx_settings_current_user_can_access()
			: \current_user_can('manage_options');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_storage_archive_resolve_path')) {
	function cmx_system_storage_archive_resolve_path(string $relative_path): string {
		$root = \wp_normalize_path(cmx_system_storage_upload_root() . '/archiv');
		$real_root = \realpath($root);
		if ($real_root === false) {
			return '';
		}

		$real_root = \wp_normalize_path($real_root);
		$relative_path = cmx_system_storage_archive_relative_path($relative_path);
		$path = $relative_path === '' ? $real_root : $real_root . '/' . $relative_path;
		$real_path = \realpath($path);
		if ($real_path === false) {
			return '';
		}

		$real_path = \wp_normalize_path($real_path);
		if ($real_path !== $real_root && !\str_starts_with($real_path, $real_root . '/')) {
			return '';
		}

		return $real_path;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_storage_archive_delete_path')) {
	function cmx_system_storage_archive_delete_path(string $path): bool {
		if (\is_file($path) || \is_link($path)) {
			return \unlink($path);
		}
		if (!\is_dir($path)) {
			return false;
		}

		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ($iterator as $item) {
				if (!$item instanceof \SplFileInfo) {
					continue;
				}
				$item_path = $item->getPathname();
				if ($item->isDir() && !$item->isLink()) {
					\rmdir($item_path);
				} else {
					\unlink($item_path);
				}
			}
		} catch (\Throwable $e) {
			return false;
		}

		return \rmdir($path);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_storage_archive_browser_data')) {
	function cmx_system_storage_archive_browser_data(): array {
		$root = \wp_normalize_path(cmx_system_storage_upload_root() . '/archiv');
		$current = isset($_GET['cmx_archive_dir'])
			? cmx_system_storage_archive_relative_path((string) \wp_unslash($_GET['cmx_archive_dir']))
			: '';
		$current_path = cmx_system_storage_archive_resolve_path($current);
		if ($current_path === '' || !\is_dir($current_path) || !\is_readable($current_path)) {
			$current = '';
			$current_path = cmx_system_storage_archive_resolve_path('');
		}

		$folders = [];
		$files = [];

		if ($current_path !== '' && \is_dir($current_path) && \is_readable($current_path)) {
			$children = \scandir($current_path);
			if (\is_array($children)) {
				foreach ($children as $child) {
					if ($child === '.' || $child === '..') {
						continue;
					}
					$path = \wp_normalize_path($current_path . '/' . $child);
					if (\is_link($path)) {
						continue;
					}
					$relative = cmx_system_storage_archive_relative_path($current === '' ? $child : $current . '/' . $child);
					if (\is_dir($path)) {
						$folders[] = [
							'name' => $child,
							'path' => $relative,
						];
						continue;
					}
					if (\is_file($path)) {
						$size = \max(0, (int) \filesize($path));
						$modified = \max(0, (int) \filemtime($path));
						$files[] = [
							'name' => $child,
							'path' => $relative,
							'bytes' => $size,
							'formatted' => cmx_system_storage_format_bytes($size),
							'modified' => $modified,
						];
					}
				}
			}
		}

		\usort($folders, static function (array $left, array $right): int {
			return \strnatcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
		});
		\usort($files, static function (array $left, array $right): int {
			$modified_order = ((int) ($right['modified'] ?? 0)) <=> ((int) ($left['modified'] ?? 0));
			if ($modified_order !== 0) {
				return $modified_order;
			}
			return \strnatcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
		});

		$parent = '';
		if ($current !== '') {
			$parts = \explode('/', $current);
			\array_pop($parts);
			$parent = \implode('/', $parts);
		}

		return [
			'root' => $root,
			'current' => $current,
			'parent' => $parent,
			'folders' => $folders,
			'files' => $files,
			'folder_count' => \count($folders),
			'count' => \count($files),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_storage_enqueue_chartjs')) {
	function cmx_system_storage_enqueue_chartjs(): void {
		if (\function_exists(__NAMESPACE__ . '\\cmx_enqueue_chartjs')) {
			cmx_enqueue_chartjs();
			return;
		}
		if (\wp_script_is('cmx-chartjs', 'enqueued')) {
			return;
		}

		$plugin_main = \dirname(__DIR__, 2) . '/cmx-misbuero.php';
		$local_file = \dirname(__DIR__, 2) . '/assets/chart.umd.min.js';
		$chartjs_url = \is_readable($local_file)
			? \plugins_url('assets/chart.umd.min.js', $plugin_main)
			: 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
		\wp_register_script('cmx-chartjs', $chartjs_url, [], '4.4.1', true);
		\wp_enqueue_script('cmx-chartjs');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_system_storage_panel')) {
	function cmx_render_system_storage_panel(): void {
		cmx_system_storage_enqueue_chartjs();

		$data = cmx_system_storage_data();
		$archive_data = cmx_system_storage_archive_browser_data();
		$archive_folders = (array) ($archive_data['folders'] ?? []);
		$archive_files = (array) ($archive_data['files'] ?? []);
		$items = (array) ($data['items'] ?? []);
		$colors = ['#2b7bb9', '#32bd55', '#7347ff', '#edb20f', '#b7c2ce', '#0f766e', '#db2777', '#64748b'];
		$labels = [];
		$values = [];

		foreach ($items as $item) {
			$labels[] = (string) ($item['label'] ?? '');
			$values[] = (int) ($item['bytes'] ?? 0);
		}

		$payload = [
			'labels' => $labels,
			'values' => $values,
			'colors' => \array_slice($colors, 0, \max(1, \count($items))),
			'total' => (int) ($data['total'] ?? 0),
			'totalFormatted' => (string) ($data['total_formatted'] ?? '0 B'),
			'timeLabel' => \wp_date('H:i'),
			'timeEndLabel' => \wp_date('H:i', \time() + HOUR_IN_SECONDS),
		];
		$donut_segments = [];
		$donut_start = 0.0;
		$total_bytes = \max(0, (int) ($data['total'] ?? 0));
		if ($total_bytes > 0) {
			foreach ($values as $index => $value) {
				$value = \max(0, (int) $value);
				if ($value <= 0) {
					continue;
				}
				$donut_end = $donut_start + (($value / $total_bytes) * 360);
				$color = $colors[$index % \count($colors)];
				$donut_segments[] = $color . ' ' . \number_format($donut_start, 4, '.', '') . 'deg ' . \number_format($donut_end, 4, '.', '') . 'deg';
				$donut_start = $donut_end;
			}
		}
		$donut_gradient = $donut_segments !== []
			? 'conic-gradient(' . \implode(', ', $donut_segments) . ')'
			: 'conic-gradient(#edf0f4 0deg 360deg)';

		echo '<div class="cmx-system-storage-layout">';
		echo '<div class="cmx-system-storage-card">';
		echo '<div class="cmx-system-storage-head">';
		echo '<h2>Speicherplatz</h2>';
		echo '<p>misbuero</p>';
		echo '</div>';

		if ($items === []) {
			echo '<p>Keine Ordnerdaten gefunden.</p>';
			echo '</div>';
		} else {
			echo '<div class="cmx-system-storage-grid">';
			echo '<div class="cmx-system-storage-donut-wrap">';
			echo '<div class="cmx-system-storage-donut-css" style="background:' . \esc_attr($donut_gradient) . '"></div>';
			echo '<canvas id="cmx-system-storage-donut" width="260" height="260"></canvas>';
			echo '<div class="cmx-system-storage-center"><span>' . \esc_html((string) ($data['total_formatted'] ?? '0 B')) . '</span><strong>misbuero</strong></div>';
			echo '</div>';
			echo '<div class="cmx-system-storage-legend">';
			foreach ($items as $index => $item) {
				$color = $colors[$index % \count($colors)];
				echo '<div class="cmx-system-storage-legend-row">';
				echo '<span class="cmx-system-storage-swatch" style="background:' . \esc_attr($color) . '"></span>';
				echo '<span class="cmx-system-storage-name">' . \esc_html((string) ($item['label'] ?? '')) . '</span>';
				echo '<strong>' . \esc_html((string) ($item['formatted'] ?? '0 B')) . ' (' . \esc_html((string) (int) ($item['percent'] ?? 0)) . '%)</strong>';
				echo '</div>';
			}
			echo '</div>';
			echo '</div>';
			echo '<div class="cmx-system-storage-total">' . \esc_html((string) ($data['total_formatted'] ?? '0 B')) . '</div>';
			echo '<div class="cmx-system-storage-line-wrap"><div class="cmx-system-storage-line-css"></div><canvas id="cmx-system-storage-line" height="120"></canvas></div>';
		}
		echo '</div>';

		echo '<div class="cmx-system-archive-card">';
		echo '<div class="cmx-system-archive-head">';
		echo '<h2>Archiv-Dateien</h2>';
		echo '<p>archiv</p>';
		echo '</div>';
		$current_archive_dir = (string) ($archive_data['current'] ?? '');
		$render_delete_form = static function (string $path, string $type, string $label) use ($current_archive_dir): void {
			$path = cmx_system_storage_archive_relative_path($path);
			$type = $type === 'folder' ? 'folder' : 'file';
			echo '<button type="button" class="cmx-system-archive-delete-btn"'
				. ' data-action="' . \esc_url(\admin_url('admin-post.php')) . '"'
				. ' data-file="' . \esc_attr($path) . '"'
				. ' data-type="' . \esc_attr($type) . '"'
				. ' data-redirect-dir="' . \esc_attr($current_archive_dir) . '"'
				. ' data-nonce="' . \esc_attr(\wp_create_nonce('cmx_system_archive_delete_' . $type . '_' . $path)) . '"'
				. ' title="' . \esc_attr($label . ' löschen') . '"'
				. ' aria-label="' . \esc_attr($label . ' löschen') . '">'
				. '<span class="dashicons dashicons-trash" aria-hidden="true"></span></button>';
		};
		echo '<div class="cmx-system-archive-path">';
		echo '<a href="' . \esc_url(cmx_system_storage_archive_browse_url('')) . '">archiv</a>';
		if ($current_archive_dir !== '') {
			$breadcrumb_path = '';
			foreach (\explode('/', $current_archive_dir) as $segment) {
				$breadcrumb_path = $breadcrumb_path === '' ? $segment : $breadcrumb_path . '/' . $segment;
				echo '<span>/</span><a href="' . \esc_url(cmx_system_storage_archive_browse_url($breadcrumb_path)) . '">' . \esc_html($segment) . '</a>';
			}
		}
		echo '</div>';
		$delete_notice = isset($_GET['cmx_archive_deleted']) ? (string) \sanitize_key((string) \wp_unslash($_GET['cmx_archive_deleted'])) : '';
		if ($delete_notice === '1') {
			echo '<div class="cmx-system-archive-notice is-success">Gelöscht.</div>';
		} elseif ($delete_notice === 'error') {
			echo '<div class="cmx-system-archive-notice is-error">Konnte nicht gelöscht werden.</div>';
		}
		if ($archive_folders === [] && $archive_files === []) {
			echo '<p class="cmx-system-archive-empty">Keine Ordner oder Dateien im Archiv gefunden.</p>';
		} else {
			echo '<div class="cmx-system-archive-summary">' . \esc_html(\number_format_i18n((int) ($archive_data['folder_count'] ?? 0))) . ' Ordner, ' . \esc_html(\number_format_i18n((int) ($archive_data['count'] ?? 0))) . ' Dateien</div>';
			echo '<div class="cmx-system-archive-list" role="list">';
			if ($current_archive_dir !== '') {
				echo '<a class="cmx-system-archive-row cmx-system-archive-row-folder" role="listitem" href="' . \esc_url(cmx_system_storage_archive_browse_url((string) ($archive_data['parent'] ?? ''))) . '">';
				echo '<span class="dashicons dashicons-undo" aria-hidden="true"></span>';
				echo '<span class="cmx-system-archive-file">.. zurück</span>';
				echo '<strong></strong><time></time>';
				echo '<span></span>';
				echo '</a>';
			}
			foreach ($archive_folders as $folder) {
				$folder_path = (string) ($folder['path'] ?? '');
				$folder_name = (string) ($folder['name'] ?? '');
				echo '<div class="cmx-system-archive-row cmx-system-archive-row-folder" role="listitem">';
				echo '<span class="dashicons dashicons-category" aria-hidden="true"></span>';
				echo '<a class="cmx-system-archive-file" href="' . \esc_url(cmx_system_storage_archive_browse_url($folder_path)) . '" title="' . \esc_attr($folder_path) . '">' . \esc_html($folder_name) . '</a>';
				echo '<strong>Ordner</strong><time></time>';
				$render_delete_form($folder_path, 'folder', $folder_name);
				echo '</div>';
			}
			foreach ($archive_files as $file) {
				$modified = (int) ($file['modified'] ?? 0);
				$file_path = (string) ($file['path'] ?? '');
				$file_name = (string) ($file['name'] ?? $file['path'] ?? '');
				$download_url = cmx_system_storage_archive_download_url($file_path);
				echo '<div class="cmx-system-archive-row cmx-system-archive-row-file" role="listitem">';
				echo '<span class="dashicons dashicons-media-default" aria-hidden="true"></span>';
				echo '<a class="cmx-system-archive-file" href="' . \esc_url($download_url) . '" title="' . \esc_attr($file_path) . '">' . \esc_html($file_name) . '</a>';
				echo '<strong>' . \esc_html((string) ($file['formatted'] ?? '0 B')) . '</strong>';
				echo '<time datetime="' . \esc_attr($modified > 0 ? \wp_date('c', $modified) : '') . '">' . \esc_html($modified > 0 ? \wp_date('d.m.Y H:i', $modified) : '-') . '</time>';
				$render_delete_form($file_path, 'file', $file_name);
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';
		echo '</div>';

		?>
		<style>
			.cmx-system-storage-layout{display:grid;grid-template-columns:minmax(0,640px) minmax(360px,520px);gap:24px;align-items:stretch}
			.cmx-system-storage-card{align-self:stretch;height:100%;max-width:640px;padding:24px;border:1px solid #d6e0ec;border-radius:10px;background:#fff;color:#172033}
			.cmx-system-storage-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:14px}
			.cmx-system-storage-head h2{margin:0;font-size:18px;line-height:1.2}
			.cmx-system-storage-head p{max-width:360px;margin:0;color:#667085;font-size:12px;text-align:right;overflow-wrap:anywhere}
			.cmx-system-storage-grid{display:grid;grid-template-columns:320px 1fr;gap:28px;align-items:center}
			.cmx-system-storage-donut-wrap{position:relative;width:260px;height:260px}
			.cmx-system-storage-donut-css{position:absolute;inset:0;border-radius:50%;z-index:1}
			.cmx-system-storage-donut-css:after{content:"";position:absolute;inset:48px;border-radius:50%;background:#fff}
			.cmx-system-storage-donut-wrap canvas{position:relative;z-index:2;display:block;width:260px;height:260px}
			.cmx-system-storage-center{position:absolute;inset:82px;z-index:3;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;pointer-events:none}
			.cmx-system-storage-center span{font-size:31px;font-weight:800;line-height:1;color:#172033}
			.cmx-system-storage-center strong{margin-top:6px;font-size:13px;color:#172033}
			.cmx-system-storage-legend{display:grid;gap:14px}
			.cmx-system-storage-legend-row{display:grid;grid-template-columns:16px minmax(70px,1fr) auto;gap:12px;align-items:center;font-size:14px}
			.cmx-system-storage-swatch{width:16px;height:12px;border-radius:3px}
			.cmx-system-storage-name{color:#172033;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
			.cmx-system-storage-legend-row strong{font-size:16px;color:#172033;white-space:nowrap}
			.cmx-system-storage-total{margin-top:18px;padding-bottom:10px;border-bottom:2px solid #edf0f4;color:#5e6b82;font-size:20px}
			.cmx-system-storage-line-wrap{position:relative;height:130px;margin-top:18px}
			.cmx-system-storage-donut-wrap.is-canvas-rendered .cmx-system-storage-donut-css,
			.cmx-system-storage-line-wrap.is-canvas-rendered .cmx-system-storage-line-css{display:none}
			.cmx-system-storage-line-css{position:absolute;left:26px;right:26px;top:38px;bottom:26px;border-top:4px solid #7347ff;background:rgba(115,71,255,.12);z-index:1}
			.cmx-system-storage-line-wrap canvas{position:relative;z-index:2}
			.cmx-system-archive-card{display:flex;flex-direction:column;align-self:stretch;height:100%;padding:24px;border:1px solid #d6e0ec;border-radius:10px;background:#fff;color:#172033}
			.cmx-system-archive-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:14px}
			.cmx-system-archive-head h2{margin:0;font-size:18px;line-height:1.2}
			.cmx-system-archive-head p{max-width:260px;margin:0;color:#667085;font-size:12px;text-align:right;overflow-wrap:anywhere}
			.cmx-system-archive-path{display:flex;flex-wrap:wrap;gap:4px;margin:-2px 0 10px;color:#667085;font-size:12px}
			.cmx-system-archive-path a{color:#2271b1;text-decoration:none}
			.cmx-system-archive-path a:hover{text-decoration:underline}
			.cmx-system-archive-notice{margin:0 0 10px;padding:8px 10px;border-radius:6px;font-size:12px;font-weight:700}
			.cmx-system-archive-notice.is-success{background:#f0fdf4;color:#166534}
			.cmx-system-archive-notice.is-error{background:#fef2f2;color:#991b1b}
			.cmx-system-archive-summary{margin-bottom:10px;color:#5e6b82;font-size:13px;font-weight:700}
			.cmx-system-archive-list{flex:1 1 auto;max-height:370px;overflow:auto;border:1px solid #edf0f4;border-radius:8px}
			.cmx-system-archive-row{display:grid;grid-template-columns:20px minmax(0,1fr) minmax(76px,max-content) minmax(118px,max-content) 28px;gap:10px;align-items:center;min-height:37px;padding:0 10px;border-bottom:1px solid #edf0f4;font-size:13px;text-decoration:none}
			.cmx-system-archive-row:last-child{border-bottom:0}
			.cmx-system-archive-row:hover{background:#f8fafc}
			.cmx-system-archive-row .dashicons{width:18px;height:18px;font-size:18px;color:#5e6b82}
			.cmx-system-archive-row-folder .dashicons{color:#2b7bb9}
			.cmx-system-archive-file{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#172033;text-decoration:none}
			a.cmx-system-archive-file:hover{text-decoration:underline}
			.cmx-system-archive-row strong,.cmx-system-archive-row time{font-size:12px;text-align:right;white-space:nowrap}
			.cmx-system-archive-row strong{color:#172033}
			.cmx-system-archive-row time{color:#667085}
			.cmx-system-archive-delete-btn{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;padding:0;border:0;background:transparent;color:#b32d2e;cursor:pointer}
			.cmx-system-archive-delete-btn:hover,.cmx-system-archive-delete-btn:focus{background:#f6f7f7;color:#8f211b;outline:1px solid transparent}
			.cmx-system-archive-delete-btn .dashicons{width:18px;height:18px;font-size:18px;color:currentColor}
			.cmx-system-archive-card.is-loading{opacity:.62;pointer-events:none}
			.cmx-system-archive-empty{margin:0;color:#667085;font-size:13px}
			@media (max-width: 782px){
				.cmx-system-storage-layout{grid-template-columns:1fr}
				.cmx-system-storage-card{max-width:none}
				.cmx-system-storage-head{display:block}
				.cmx-system-storage-head p{text-align:left;margin-top:8px}
				.cmx-system-storage-grid{grid-template-columns:1fr;gap:18px}
				.cmx-system-storage-donut-wrap{margin:0 auto}
				.cmx-system-archive-head{display:block}
				.cmx-system-archive-head p{text-align:left;margin-top:8px}
				.cmx-system-archive-row{grid-template-columns:20px minmax(0,1fr) minmax(70px,max-content) 28px}
				.cmx-system-archive-row time{display:none}
			}
		</style>
		<?php
		\wp_add_inline_script('cmx-chartjs', 'document.addEventListener("DOMContentLoaded", function () {
			var data = ' . \wp_json_encode($payload) . ';
			if (!data) { return; }
			var donut = document.getElementById("cmx-system-storage-donut");
			if (donut) {
				if (typeof Chart !== "undefined") {
					try {
						new Chart(donut, {
							type: "doughnut",
							data: { labels: data.labels, datasets: [{ data: data.values, backgroundColor: data.colors, borderWidth: 0 }] },
							options: { responsive: false, cutout: "63%", plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return ctx.label + ": " + formatBytes(Number(ctx.raw || 0)); } } } } }
						});
						markCanvasRendered(donut);
					} catch (err) {
						drawDonutFallback(donut, data);
					}
				} else {
					drawDonutFallback(donut, data);
				}
			}
			var line = document.getElementById("cmx-system-storage-line");
			if (line) {
				if (typeof Chart !== "undefined") {
					try {
						new Chart(line, {
							type: "line",
							data: { labels: [data.timeLabel, data.timeEndLabel], datasets: [{ data: [data.total, data.total], borderColor: "#7347ff", backgroundColor: "rgba(115,71,255,.12)", fill: true, tension: 0, pointRadius: 0, borderWidth: 4 }] },
							options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return formatBytes(Number(ctx.raw || 0)); } } } }, scales: { x: { grid: { display: false }, ticks: { color: "#5e6b82", font: { size: 18 } } }, y: { display: false, min: 0, suggestedMax: Math.max(1, Number(data.total || 0) * 1.08) } } }
						});
						markCanvasRendered(line);
					} catch (err) {
						drawLineFallback(line, data);
					}
				} else {
					drawLineFallback(line, data);
				}
			}
			bindArchiveBrowser();
			function drawDonutFallback(canvas, chartData) {
				var ctx = canvas.getContext && canvas.getContext("2d");
				if (!ctx) { return; }
				var width = canvas.width || 260;
				var height = canvas.height || 260;
				var centerX = width / 2;
				var centerY = height / 2;
				var radius = Math.min(width, height) / 2;
				var ring = 48;
				var values = Array.isArray(chartData.values) ? chartData.values : [];
				var colors = Array.isArray(chartData.colors) ? chartData.colors : [];
				var total = values.reduce(function (sum, value) { return sum + Math.max(0, Number(value || 0)); }, 0);
				var start = -Math.PI / 2;

				ctx.clearRect(0, 0, width, height);
				if (total <= 0) {
					ctx.beginPath();
					ctx.arc(centerX, centerY, radius - ring / 2, 0, Math.PI * 2);
					ctx.lineWidth = ring;
					ctx.strokeStyle = "#edf0f4";
					ctx.stroke();
					return;
				}

				values.forEach(function (raw, index) {
					var value = Math.max(0, Number(raw || 0));
					if (value <= 0) { return; }
					var end = start + (value / total) * Math.PI * 2;
					ctx.beginPath();
					ctx.arc(centerX, centerY, radius - ring / 2, start, end);
					ctx.lineWidth = ring;
					ctx.strokeStyle = colors[index] || "#2b7bb9";
					ctx.stroke();
					start = end;
				});
				markCanvasRendered(canvas);
			}
			function drawLineFallback(canvas, chartData) {
				var ctx = canvas.getContext && canvas.getContext("2d");
				if (!ctx) { return; }
				var rect = canvas.getBoundingClientRect();
				var dpr = window.devicePixelRatio || 1;
				var width = Math.max(1, Math.round((rect.width || canvas.clientWidth || 640) * dpr));
				var height = Math.max(1, Math.round((rect.height || canvas.clientHeight || 120) * dpr));
				canvas.width = width;
				canvas.height = height;
				ctx.clearRect(0, 0, width, height);
				ctx.fillStyle = "rgba(115,71,255,.12)";
				ctx.fillRect(26 * dpr, 38 * dpr, Math.max(0, width - 52 * dpr), Math.max(0, height - 64 * dpr));
				ctx.strokeStyle = "#7347ff";
				ctx.lineWidth = 4 * dpr;
				ctx.beginPath();
				ctx.moveTo(26 * dpr, 38 * dpr);
				ctx.lineTo(Math.max(26 * dpr, width - 26 * dpr), 38 * dpr);
				ctx.stroke();
				markCanvasRendered(canvas);
			}
			function markCanvasRendered(canvas) {
				if (canvas && canvas.parentElement) {
					canvas.parentElement.classList.add("is-canvas-rendered");
				}
			}
			function formatBytes(bytes) {
				var units = ["B", "KB", "MB", "GB", "TB"];
				var value = Math.max(0, bytes || 0);
				var unit = units[0];
				for (var i = 0; i < units.length; i++) {
					unit = units[i];
					if (value < 1024 || unit === "TB") { break; }
					value = value / 1024;
				}
				if (unit === "B") { return Math.round(value) + " B"; }
				return value.toLocaleString(document.documentElement.lang || "de-CH", { minimumFractionDigits: value >= 10 ? 1 : 2, maximumFractionDigits: value >= 10 ? 1 : 2 }) + " " + unit;
			}
			function bindArchiveBrowser() {
				var card = document.querySelector(".cmx-system-archive-card");
				if (!card || !window.fetch || !window.DOMParser) { return; }
				if (card.dataset.cmxArchiveBound === "1") { return; }
				card.dataset.cmxArchiveBound = "1";

				card.addEventListener("click", function (event) {
					var link = event.target && event.target.closest ? event.target.closest("a") : null;
					var deleteButton = event.target && event.target.closest ? event.target.closest(".cmx-system-archive-delete-btn") : null;
					if (deleteButton && card.contains(deleteButton)) {
						event.preventDefault();
						deleteArchiveEntry(deleteButton);
						return;
					}
					if (!link || !card.contains(link) || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) { return; }
					if (!link.matches(".cmx-system-archive-path a, .cmx-system-archive-row-folder, .cmx-system-archive-row-folder .cmx-system-archive-file")) { return; }
					event.preventDefault();
					loadArchiveCard(link.href, null, true);
				});

				if (!window.cmxSystemArchivePopstateBound) {
					window.cmxSystemArchivePopstateBound = true;
					window.addEventListener("popstate", function () {
						loadArchiveCard(window.location.href, null, false);
					});
				}
			}
			function deleteArchiveEntry(button) {
				if (!button || !window.confirm("Diesen Eintrag wirklich löschen?")) { return; }
				var formData = new FormData();
				formData.append("action", "cmx_system_archive_delete");
				formData.append("file", button.getAttribute("data-file") || "");
				formData.append("type", button.getAttribute("data-type") || "file");
				formData.append("redirect_dir", button.getAttribute("data-redirect-dir") || "");
				formData.append("_wpnonce", button.getAttribute("data-nonce") || "");
				loadArchiveCard(button.getAttribute("data-action") || window.location.href, { method: "POST", body: formData, credentials: "same-origin" }, true);
			}
			function loadArchiveCard(url, options, pushState) {
				var current = document.querySelector(".cmx-system-archive-card");
				if (!current) { return; }
				current.classList.add("is-loading");
				var fetchOptions = options || { credentials: "same-origin" };
				if (!fetchOptions.credentials) {
					fetchOptions.credentials = "same-origin";
				}
				fetch(url, fetchOptions)
					.then(function (response) {
						if (!response.ok) { throw new Error("Archiv konnte nicht geladen werden."); }
						return response.text().then(function (html) {
							return { html: html, url: response.url || url };
						});
					})
					.then(function (result) {
						var parser = new DOMParser();
						var doc = parser.parseFromString(result.html, "text/html");
						var next = doc.querySelector(".cmx-system-archive-card");
						var active = document.querySelector(".cmx-system-archive-card");
						if (!next || !active) { throw new Error("Archiv-Antwort unvollständig."); }
						active.replaceWith(next);
						bindArchiveBrowser();
						if (pushState && window.history && window.history.pushState) {
							window.history.pushState({}, "", result.url);
						}
					})
					.catch(function () {
						var active = document.querySelector(".cmx-system-archive-card");
						if (active) {
							active.classList.remove("is-loading");
						}
						window.location.href = url;
					});
			}
		});', 'after');
	}
}

\add_action('admin_post_cmx_system_archive_download', __NAMESPACE__ . '\\cmx_system_handle_archive_download');
function cmx_system_handle_archive_download(): void {
	if (!cmx_system_storage_archive_can_manage()) {
		\wp_die('Keine Berechtigung.');
	}

	$relative_path = isset($_GET['file'])
		? cmx_system_storage_archive_relative_path((string) \wp_unslash($_GET['file']))
		: '';
	if ($relative_path === '') {
		\wp_die('Datei nicht gefunden.');
	}

	if (!\wp_verify_nonce((string) ($_GET['_wpnonce'] ?? ''), 'cmx_system_archive_download_' . $relative_path)) {
		\wp_die('Ungültige Anfrage.');
	}

	$path = cmx_system_storage_archive_resolve_path($relative_path);
	if ($path === '' || !\is_file($path) || !\is_readable($path)) {
		\wp_die('Datei nicht gefunden.');
	}

	$filename = \basename($path);
	$size = (int) \filesize($path);
	$mime = 'application/octet-stream';
	if (\function_exists('mime_content_type')) {
		$detected = (string) \mime_content_type($path);
		if ($detected !== '') {
			$mime = $detected;
		}
	}

	while (\ob_get_level() > 0) {
		\ob_end_clean();
	}

	\nocache_headers();
	\header('Content-Type: ' . $mime);
	\header('Content-Disposition: attachment; filename="' . \str_replace('"', '', $filename) . '"');
	\header('Content-Length: ' . \max(0, $size));
	\readfile($path);
	exit;
}

\add_action('admin_post_cmx_system_archive_delete', __NAMESPACE__ . '\\cmx_system_handle_archive_delete');
function cmx_system_handle_archive_delete(): void {
	if (!cmx_system_storage_archive_can_manage()) {
		\wp_die('Keine Berechtigung.');
	}

	$relative_path = isset($_POST['file'])
		? cmx_system_storage_archive_relative_path((string) \wp_unslash($_POST['file']))
		: '';
	$type = isset($_POST['type']) && (string) $_POST['type'] === 'folder' ? 'folder' : 'file';
	$redirect_dir = isset($_POST['redirect_dir'])
		? cmx_system_storage_archive_relative_path((string) \wp_unslash($_POST['redirect_dir']))
		: '';

	$redirect_url = cmx_system_storage_archive_browse_url($redirect_dir);
	if ($relative_path === '') {
		\wp_safe_redirect(\add_query_arg('cmx_archive_deleted', 'error', $redirect_url));
		exit;
	}

	if (!\wp_verify_nonce((string) ($_POST['_wpnonce'] ?? ''), 'cmx_system_archive_delete_' . $type . '_' . $relative_path)) {
		\wp_die('Ungültige Anfrage.');
	}

	$path = cmx_system_storage_archive_resolve_path($relative_path);
	if ($path === '' || ($type === 'folder' && !\is_dir($path)) || ($type === 'file' && !\is_file($path))) {
		\wp_safe_redirect(\add_query_arg('cmx_archive_deleted', 'error', $redirect_url));
		exit;
	}

	$deleted = cmx_system_storage_archive_delete_path($path);
	\wp_safe_redirect(\add_query_arg('cmx_archive_deleted', $deleted ? '1' : 'error', $redirect_url));
	exit;
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_bulk_delete_post_types')) {
	function cmx_system_bulk_delete_post_types(): array {
		$candidates = ['artikel', 'kontakte', 'projekte', 'belege', 'dokumente', 'scanner', 'emails', 'budget', 'carent', 'zugangsdaten'];
		$post_types = [];

		foreach ($candidates as $post_type) {
			if (!\post_type_exists($post_type)) {
				continue;
			}

			$object = \get_post_type_object($post_type);
			if (!$object instanceof \WP_Post_Type || empty($object->show_ui)) {
				continue;
			}

			$delete_cap = (string) ($object->cap->delete_posts ?? 'delete_posts');
			if ($delete_cap !== '' && !\current_user_can($delete_cap) && !\current_user_can('delete_posts')) {
				continue;
			}

			$post_types[$post_type] = $object;
		}

		\uasort($post_types, static function (\WP_Post_Type $left, \WP_Post_Type $right): int {
			$left_label = (string) ($left->labels->name ?? $left->label ?? $left->name);
			$right_label = (string) ($right->labels->name ?? $right->label ?? $right->name);
			return \strnatcasecmp($left_label, $right_label);
		});

		return $post_types;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_bulk_delete_post_type_counts')) {
	function cmx_system_bulk_delete_post_type_counts(string $post_type): array {
		$counts = \wp_count_posts($post_type);
		$active = 0;
		$trash = 0;

		foreach ((array) \get_object_vars($counts) as $status => $count) {
			$count = (int) $count;
			if ($count <= 0) {
				continue;
			}
			if ($status === 'trash') {
				$trash += $count;
				continue;
			}
			if ($status === 'auto-draft' || $status === 'inherit') {
				continue;
			}
			$active += $count;
		}

		return [
			'active' => $active,
			'trash'  => $trash,
			'total'  => $active + $trash,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_bulk_delete_total_count')) {
	function cmx_system_bulk_delete_total_count(string $post_type, bool $delete_permanently): int {
		$counts = cmx_system_bulk_delete_post_type_counts($post_type);
		return $delete_permanently
			? (int) ($counts['total'] ?? 0)
			: (int) ($counts['active'] ?? 0);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_bulk_delete_post_type_label')) {
	function cmx_system_bulk_delete_post_type_label(\WP_Post_Type $post_type_object): string {
		$post_type = (string) $post_type_object->name;
		$label = (string) ($post_type_object->labels->name ?? $post_type_object->label ?? $post_type);
		$counts = cmx_system_bulk_delete_post_type_counts($post_type);
		$parts = [];

		if (($counts['active'] ?? 0) > 0) {
			$parts[] = \number_format_i18n((int) $counts['active']) . ' aktiv';
		}
		if (($counts['trash'] ?? 0) > 0) {
			$parts[] = \number_format_i18n((int) $counts['trash']) . ' im Papierkorb';
		}
		if ($parts === []) {
			$parts[] = 'leer';
		}

		return $label . ' (' . \implode(' | ', $parts) . ')';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_bulk_delete_process_batch')) {
	function cmx_system_bulk_delete_process_batch(string $post_type, bool $delete_permanently = false, int $after_id = 0, float $max_seconds = 4.0, int $query_limit = 50): array {
		$post_type = \sanitize_key($post_type);
		if ($post_type === '' || !\post_type_exists($post_type)) {
			return [
				'processed' => 0,
				'failed'    => 0,
				'skipped'   => 0,
				'last_id'   => \max(0, $after_id),
				'done'      => true,
			];
		}

		$after_id = \max(0, $after_id);
		$query_limit = \max(1, $query_limit);
		$max_seconds = $max_seconds > 0 ? $max_seconds : 4.0;
		$started_at = \microtime(true);
		$statuses = cmx_system_bulk_delete_query_statuses($delete_permanently);
		$processed = 0;
		$failed = 0;
		$skipped = 0;
		$last_id = $after_id;
		$done = false;

		if (\function_exists('ignore_user_abort')) {
			\ignore_user_abort(true);
		}
		if (\function_exists('set_time_limit')) {
			@\set_time_limit(20);
		}

		$restore_term_counting = \function_exists('wp_defer_term_counting');
		$restore_comment_counting = \function_exists('wp_defer_comment_counting');

		if ($restore_term_counting) {
			\wp_defer_term_counting(true);
		}
		if ($restore_comment_counting) {
			\wp_defer_comment_counting(true);
		}

		try {
			do {
				$ids = cmx_system_bulk_delete_load_post_ids($post_type, $statuses, $last_id, $query_limit);
				if ($ids === []) {
					$done = true;
					break;
				}

				foreach ($ids as $post_id) {
					$post_id = (int) $post_id;
					if ($post_id <= 0) {
						continue;
					}

					$last_id = $post_id;

					if (!\current_user_can('delete_post', $post_id)) {
						$skipped++;
					} else {
						$result = $delete_permanently
							? \wp_delete_post($post_id, true)
							: \wp_trash_post($post_id);

						if ($result) {
							$processed++;
						} else {
							$failed++;
						}
					}

					if ((\microtime(true) - $started_at) >= $max_seconds) {
						break 2;
					}
				}
			} while ((\microtime(true) - $started_at) < $max_seconds);
		} finally {
			if ($restore_comment_counting) {
				\wp_defer_comment_counting(false);
			}
			if ($restore_term_counting) {
				\wp_defer_term_counting(false);
			}
		}

		if (!$done) {
			$done = cmx_system_bulk_delete_load_post_ids($post_type, $statuses, $last_id, 1) === [];
		}

		return [
			'processed' => $processed,
			'failed'    => $failed,
			'skipped'   => $skipped,
			'last_id'   => $last_id,
			'done'      => $done,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_bulk_delete_query_statuses')) {
	function cmx_system_bulk_delete_query_statuses(bool $delete_permanently): array {
		$statuses = \array_values(\array_unique(\array_map('strval', \get_post_stati([], 'names'))));
		$statuses = \array_values(\array_diff($statuses, ['auto-draft', 'inherit']));
		if (!$delete_permanently) {
			$statuses = \array_values(\array_diff($statuses, ['trash']));
		}
		return $statuses;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_bulk_delete_load_post_ids')) {
	function cmx_system_bulk_delete_load_post_ids(string $post_type, array $statuses, int $after_id = 0, int $limit = 100): array {
		global $wpdb;

		$post_type = \sanitize_key($post_type);
		$after_id = \max(0, (int) $after_id);
		$limit = \max(1, (int) $limit);
		$statuses = \array_values(\array_filter(\array_map('strval', $statuses)));

		if ($post_type === '' || $statuses === []) {
			return [];
		}

		$status_placeholders = \implode(', ', \array_fill(0, \count($statuses), '%s'));
		$sql = "
			SELECT ID
			FROM {$wpdb->posts}
			WHERE post_type = %s
				AND ID > %d
				AND post_status IN ({$status_placeholders})
			ORDER BY ID ASC
			LIMIT %d
		";

		$params = \array_merge([$post_type, $after_id], $statuses, [$limit]);
		$prepared = $wpdb->prepare($sql, $params);
		if (!\is_string($prepared) || $prepared === '') {
			return [];
		}

		return \array_map('intval', (array) $wpdb->get_col($prepared));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_bulk_delete_run')) {
	function cmx_system_bulk_delete_run(string $post_type, bool $delete_permanently = false): array {
		$post_type = \sanitize_key($post_type);
		if ($post_type === '' || !\post_type_exists($post_type)) {
			return ['processed' => 0, 'failed' => 0, 'skipped' => 0];
		}

		$processed = 0;
		$failed = 0;
		$skipped = 0;
		$last_id = 0;
		$done = false;

		do {
			$batch = cmx_system_bulk_delete_process_batch($post_type, $delete_permanently, $last_id, 5.0, 100);
			$processed += (int) ($batch['processed'] ?? 0);
			$failed += (int) ($batch['failed'] ?? 0);
			$skipped += (int) ($batch['skipped'] ?? 0);
			$last_id = (int) ($batch['last_id'] ?? $last_id);
			$done = !empty($batch['done']);
		} while (!$done);

		return [
			'processed' => $processed,
			'failed'    => $failed,
			'skipped'   => $skipped,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_system_bulk_delete_panel')) {
	function cmx_render_system_bulk_delete_panel(): void {
		if (!\current_user_can('manage_options')) {
			return;
		}

		$post_types = cmx_system_bulk_delete_post_types();
		if ($post_types === []) {
			return;
		}

		echo '<div class="cmx-system-bulk-delete-panel" style="margin-top:28px;padding:20px;border:1px solid #dcdcde;border-radius:10px;background:#fff;">';
		echo '<h2 style="margin-top:0;">Alles löschen</h2>';
		echo '<p class="description" style="margin-bottom:16px;">Wähle ein Modul aus und verschiebe alle Einträge gesammelt in den Papierkorb. Optional kannst Du sie direkt endgültig löschen.</p>';
		echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '" id="cmx-system-bulk-delete-form">';
		\wp_nonce_field('cmx_system_bulk_delete_post_type');
		echo '<input type="hidden" name="action" value="cmx_system_bulk_delete_post_type">';
		echo '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">';
		echo '<label class="screen-reader-text" for="cmx-system-bulk-delete-post-type">Modul auswählen</label>';
		echo '<select name="cmx_bulk_delete_post_type" id="cmx-system-bulk-delete-post-type">';
		echo '<option value="">' . \esc_html__('Modul auswählen', 'cmx-misbuero') . '</option>';
		foreach ($post_types as $post_type => $post_type_object) {
			echo '<option value="' . \esc_attr((string) $post_type) . '">' . \esc_html(cmx_system_bulk_delete_post_type_label($post_type_object)) . '</option>';
		}
		echo '</select>';
		echo '<label for="cmx-system-bulk-delete-force" style="display:inline-flex;align-items:center;gap:6px;">';
		echo '<input type="checkbox" name="cmx_bulk_delete_force" id="cmx-system-bulk-delete-force" value="1">';
		echo '<span>Endgültig löschen</span>';
		echo '</label>';
		echo '<button type="submit" class="button button-secondary" id="cmx-system-bulk-delete-submit">In Papierkorb legen</button>';
		echo '</div>';
		echo '<div id="cmx-system-bulk-delete-status" style="display:none;margin-top:14px;">';
		echo '<progress id="cmx-system-bulk-delete-progress" value="0" max="100" style="width:min(460px,100%);height:16px;"></progress>';
		echo '<p id="cmx-system-bulk-delete-status-text" style="margin:8px 0 0;"></p>';
		echo '</div>';
		echo '<p class="description" style="margin-top:12px;color:#b32d2e;">Diese Aktion betrifft alle Einträge des ausgewählten Moduls.</p>';
		echo '</form>';
		echo '</div>';
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function () {
			const form = document.getElementById('cmx-system-bulk-delete-form');
			const select = document.getElementById('cmx-system-bulk-delete-post-type');
			const force = document.getElementById('cmx-system-bulk-delete-force');
			const submit = document.getElementById('cmx-system-bulk-delete-submit');
			const statusWrap = document.getElementById('cmx-system-bulk-delete-status');
			const progress = document.getElementById('cmx-system-bulk-delete-progress');
			const statusText = document.getElementById('cmx-system-bulk-delete-status-text');
			if (!form || !select || !force || !submit || !statusWrap || !progress || !statusText) {
				return;
			}

			const ajaxUrl = <?php echo \wp_json_encode((string) \admin_url('admin-ajax.php')); ?>;
			const noticeBaseUrl = <?php echo \wp_json_encode(cmx_system_bulk_delete_notice_base_url()); ?>;
			const nonceField = form.querySelector('input[name="_wpnonce"]');
			const numberFormat = new Intl.NumberFormat(document.documentElement.lang || 'de-CH');
			let isRunning = false;

			const setBusy = function (busy) {
				isRunning = !!busy;
				select.disabled = busy;
				force.disabled = busy;
				submit.disabled = busy;
			};

			const setStatus = function (text, percent) {
				statusWrap.style.display = 'block';
				statusText.textContent = text || '';
				var value = Number(percent || 0);
				if (!Number.isFinite(value)) {
					value = 0;
				}
				value = Math.max(0, Math.min(100, value));
				progress.value = value;
			};

			const updateLabel = function () {
				submit.textContent = force.checked ? 'Endgültig löschen' : 'In Papierkorb legen';
			};

			const runBatch = async function (state) {
				const body = new URLSearchParams();
				body.set('action', 'cmx_system_bulk_delete_post_type_batch');
				body.set('_ajax_nonce', nonceField ? String(nonceField.value || '') : '');
				body.set('cmx_bulk_delete_post_type', state.postType);
				body.set('cmx_bulk_delete_force', state.force ? '1' : '0');
				body.set('after_id', String(state.afterId || 0));

				const response = await fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
					},
					body: body.toString()
				});

				let payload = null;
				try {
					payload = await response.json();
				} catch (error) {
					throw new Error('Antwort konnte nicht gelesen werden.');
				}

				if (!response.ok || !payload || payload.success !== true) {
					const message = payload && payload.data && payload.data.message
						? String(payload.data.message)
						: 'Löschen fehlgeschlagen.';
					throw new Error(message);
				}

				return payload.data || {};
			};

			const redirectToNotice = function (state) {
				const url = new URL(noticeBaseUrl);
				url.searchParams.set('cmx_system_bulk_delete_notice', 'success');
				url.searchParams.set('cmx_system_bulk_delete_post_type', state.postType);
				url.searchParams.set('cmx_system_bulk_delete_mode', state.force ? 'delete' : 'trash');
				url.searchParams.set('cmx_system_bulk_delete_count', String(state.processed));
				url.searchParams.set('cmx_system_bulk_delete_failed', String(state.failed));
				url.searchParams.set('cmx_system_bulk_delete_skipped', String(state.skipped));
				window.location.href = url.toString();
			};

			const runDeletion = async function (state) {
				while (true) {
					const data = await runBatch(state);
					if (state.total <= 0) {
						state.total = Number(data.total || 0);
					}
					state.afterId = Number(data.last_id || state.afterId || 0);
					state.processed += Number(data.processed || 0);
					state.failed += Number(data.failed || 0);
					state.skipped += Number(data.skipped || 0);

					const handled = state.processed + state.failed + state.skipped;
					const total = state.total > 0 ? state.total : handled;
					const percent = total > 0 ? (handled / total) * 100 : 100;
					const verb = state.force ? 'gelöscht' : 'verschoben';
					setStatus(numberFormat.format(handled) + ' / ' + numberFormat.format(total) + ' Einträge verarbeitet, ' + numberFormat.format(state.processed) + ' ' + verb + '…', percent);

					if (data.done) {
						setStatus('Vorgang abgeschlossen. Weiterleitung…', 100);
						redirectToNotice(state);
						return;
					}
				}
			};

			form.addEventListener('submit', async function (event) {
				const option = select.options[select.selectedIndex] || null;
				const postType = String(select.value || '').trim();
				if (isRunning) {
					event.preventDefault();
					return;
				}
				if (!postType) {
					event.preventDefault();
					window.alert('Bitte zuerst ein Modul auswählen.');
					return;
				}

				const label = option ? String(option.textContent || postType).trim() : postType;
				const actionLabel = force.checked ? 'endgültig löschen' : 'in den Papierkorb legen';
				if (!window.confirm('Wirklich alle Einträge von "' + label + '" ' + actionLabel + '?')) {
					event.preventDefault();
					return;
				}

				event.preventDefault();
				setBusy(true);
				setStatus('Löschen läuft…', 0);

				try {
					await runDeletion({
						postType: postType,
						force: force.checked,
						afterId: 0,
						total: 0,
						processed: 0,
						failed: 0,
						skipped: 0
					});
				} catch (error) {
					setBusy(false);
					setStatus(error && error.message ? String(error.message) : 'Löschen fehlgeschlagen.', 0);
				}
			});

			force.addEventListener('change', updateLabel);
			updateLabel();
		});
		</script>
		<?php
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_bulk_delete_redirect')) {
	function cmx_system_bulk_delete_redirect(array $args = []): void {
		$base_url = cmx_system_bulk_delete_notice_base_url();
		\wp_safe_redirect(\add_query_arg($args, $base_url));
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_render_copyable_instance_link')) {
	function cmx_system_render_copyable_instance_link(string $base_id, string $url, string $open_label): void {
		$base_id = \sanitize_key($base_id);
		if ($base_id === '') {
			$base_id = 'cmx-system-link';
		}

		$copy_label = 'Link in Zwischenablage kopieren';
		$status_id = $base_id . '-status';
		$copy_id = $base_id . '-copy';
		$link_id = $base_id . '-link';
		$href = \str_replace(['{', '}'], ['%7B', '%7D'], $url);

		static $copy_link_style_printed = false;
		if (!$copy_link_style_printed) {
			echo '<style>.cmx-system-copy-link-button{display:inline-flex!important;align-items:center!important;justify-content:center!important;width:42px;min-width:42px;height:40px;min-height:40px;padding:0!important;line-height:1!important}.cmx-system-copy-link-button .dashicons{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;font-size:20px;line-height:1;margin:0!important;transform:none!important}</style>';
			$copy_link_style_printed = true;
		}

		echo '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">';
		echo '<button type="button" class="button button-secondary cmx-system-copy-link-button" id="' . \esc_attr($copy_id) . '" aria-label="' . \esc_attr($copy_label) . '" title="' . \esc_attr($copy_label) . '" data-copy-url="' . \esc_attr($url) . '">';
		echo '<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>';
		echo '</button>';
		echo '<a id="' . \esc_attr($link_id) . '" href="' . \esc_url($href) . '" target="_blank" rel="noopener noreferrer" data-copy-url="' . \esc_attr($url) . '" title="' . \esc_attr($open_label) . '">' . \esc_html($url) . '</a>';
		echo '<span class="description" id="' . \esc_attr($status_id) . '" aria-live="polite" style="min-height:18px;"></span>';
		echo '</div>';
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function () {
			const copyButton = document.getElementById('<?php echo \esc_js($copy_id); ?>');
			const link = document.getElementById('<?php echo \esc_js($link_id); ?>');
			const status = document.getElementById('<?php echo \esc_js($status_id); ?>');
			if (!copyButton || !link || !status) {
				return;
			}

			let resetTimer = null;
			const setStatus = function (message, isError) {
				status.textContent = message || '';
				status.style.color = isError ? '#b32d2e' : '#2271b1';
				if (resetTimer) {
					window.clearTimeout(resetTimer);
				}
				if (message) {
					resetTimer = window.setTimeout(function () {
						status.textContent = '';
					}, 1800);
				}
			};

			const copyFallback = function (text) {
				const input = document.createElement('textarea');
				input.value = text;
				input.setAttribute('readonly', 'readonly');
				input.style.position = 'fixed';
				input.style.opacity = '0';
				document.body.appendChild(input);
				input.select();
				input.setSelectionRange(0, input.value.length);
				let ok = false;
				try {
					ok = document.execCommand('copy');
				} catch (error) {
					ok = false;
				}
				document.body.removeChild(input);
				return ok;
			};

			const copyUrl = function (text) {
				if (!text) {
					setStatus('Link fehlt.', true);
					return;
				}
				if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function' && window.isSecureContext) {
					navigator.clipboard.writeText(text).then(function () {
						setStatus('Link kopiert.', false);
					}).catch(function () {
						if (copyFallback(text)) {
							setStatus('Link kopiert.', false);
							return;
						}
						setStatus('Link konnte nicht kopiert werden.', true);
					});
					return;
				}
				if (copyFallback(text)) {
					setStatus('Link kopiert.', false);
					return;
				}
				setStatus('Link konnte nicht kopiert werden.', true);
			};

			copyButton.addEventListener('click', function () {
				copyUrl(copyButton.getAttribute('data-copy-url') || '');
			});

			link.addEventListener('click', function () {
				copyUrl(link.getAttribute('data-copy-url') || link.href || '');
			});
		});
		</script>
		<?php
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_netvoip_url')) {
	function cmx_system_netvoip_url(): string {
		return \untrailingslashit((string) \home_url('/call'))
			. '?number={phone}&account={account}&dnid={dnid}&callid={call_id}';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_system_backup_panel')) {
	function cmx_render_system_backup_panel(): void {
		$can = \function_exists(__NAMESPACE__ . '\\cmx_settings_current_user_can_access')
			? cmx_settings_current_user_can_access()
			: \current_user_can('manage_options');
		if (!$can) {
			return;
		}

		$post_types = \function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_supported_post_types')
			? cmx_cpt_transfer_supported_post_types()
			: \array_values((array) \get_post_types(['show_ui' => true], 'names'));
		$post_types = \array_values(\array_filter(\array_map('sanitize_key', $post_types), static function (string $post_type): bool {
			return $post_type !== '' && $post_type !== 'attachment' && \post_type_exists($post_type);
			}));
			\sort($post_types, \SORT_NATURAL | \SORT_FLAG_CASE);
			$local_backups = \function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_local_backup_files')
				? cmx_cpt_transfer_local_backup_files()
				: [];
			$webdav_backups = \function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_webdav_backup_files')
				? cmx_cpt_transfer_webdav_backup_files()
				: [];
		$webdav_settings = \function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_backup_webdav_settings')
			? cmx_cpt_transfer_backup_webdav_settings()
			: ['url' => '', 'user' => '', 'password' => '', 'path' => 'backups'];
		$webdav_ready = \function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_backup_webdav_ready')
			? cmx_cpt_transfer_backup_webdav_ready($webdav_settings)
			: false;
		$initial_backup_source = isset($_GET['cmx_backup_source'])
			&& \sanitize_key((string) \wp_unslash($_GET['cmx_backup_source'])) === 'webdav'
			? 'webdav'
			: 'local';

		if (!empty($_GET['cmx_backup_notice'])) {
			$notice = \get_user_meta(\get_current_user_id(), 'cmx_cpt_transfer_backup_notice', true);
			\delete_user_meta(\get_current_user_id(), 'cmx_cpt_transfer_backup_notice');
			$notice = \is_array($notice) ? $notice : [];
			$message = \trim((string) ($notice['message'] ?? ''));
			if ($message !== '') {
				$type = (string) ($notice['type'] ?? 'success');
				$class = $type === 'error' ? 'notice-error' : 'notice-success';
				echo '<div class="notice ' . \esc_attr($class) . ' is-dismissible"><p>' . \esc_html($message) . '</p></div>';
			}
			}

			echo '<div class="cmx-system-backup">';
			echo '<div class="cmx-system-backup-grid">';

			echo '<section class="cmx-system-backup-card">';
			echo '<h2>Export</h2>';
			echo '<p>Wähle aus, welche Module in ein gemeinsames Backup-ZIP geschrieben werden sollen.</p>';
			echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '">';
			\wp_nonce_field('cmx_cpt_transfer_backup_export');
			echo '<input type="hidden" name="action" value="cmx_cpt_transfer_backup_export">';
			echo '<div class="cmx-system-backup-choice" role="radiogroup" aria-label="Export-Ziel">';
			echo '<label><input type="radio" name="backup_target" value="local" checked> Lokal speichern</label>';
			echo '<label><input type="radio" name="backup_target" value="webdav"> In WebDAV speichern</label>';
			echo '</div>';
			echo '<label class="cmx-system-backup-all"><input type="checkbox" id="cmx-backup-select-all" checked> Alle auswählen</label>';
			echo '<div class="cmx-system-backup-list" data-cmx-backup-export-list>';
			foreach ($post_types as $post_type) {
				$label = \function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_post_type_label')
					? cmx_cpt_transfer_post_type_label($post_type)
					: $post_type;
			echo '<label><input type="checkbox" name="post_types[]" value="' . \esc_attr($post_type) . '" checked> <span>' . \esc_html($label) . '</span><code>' . \esc_html($post_type) . '</code></label>';
		}
		echo '</div>';
		echo '<button type="submit" class="button button-primary">Backup exportieren</button>';
		echo '</form>';
		echo '</section>';

			echo '<section class="cmx-system-backup-card">';
			echo '<h2>Import</h2>';
			echo '<p>Importiert ein Backup-ZIP und übernimmt die gewählten Module.<br><code>/wp-content/uploads/misbuero/archiv/backups/</code></p>';
			echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
			\wp_nonce_field('cmx_cpt_transfer_backup_import');
			echo '<input type="hidden" name="action" value="cmx_cpt_transfer_backup_import">';
			echo '<div class="cmx-system-backup-choice" role="radiogroup" aria-label="Import-Quelle">';
			echo '<label><input type="radio" name="backup_source" value="local"' . \checked($initial_backup_source, 'local', false) . ' data-cmx-backup-source="local"> Lokales Backup auswählen</label>';
			echo '<label><input type="radio" name="backup_source" value="webdav"' . \checked($initial_backup_source, 'webdav', false) . ' data-cmx-backup-source="webdav"> Backup aus WebDAV auswählen</label>';
			echo '</div>';
			echo '<div class="cmx-system-backup-source" data-cmx-backup-panel="local"' . ($initial_backup_source === 'local' ? '' : ' hidden') . '>';
			if ($local_backups === []) {
				echo '<p class="description">Keine lokalen Backups gefunden.</p>';
			} else {
				$local_download_url = \wp_nonce_url(\add_query_arg(['action' => 'cmx_cpt_transfer_backup_download', 'backup_source' => 'local'], \admin_url('admin-post.php')), 'cmx_cpt_transfer_backup_download');
				echo '<div class="cmx-system-backup-select-row">';
				echo '<select name="local_backup" class="regular-text">';
				foreach ($local_backups as $backup) {
					$name = (string) ($backup['name'] ?? '');
					$modified = (int) ($backup['modified'] ?? 0);
					$size = (int) ($backup['size'] ?? 0);
					$label = $name;
					if ($modified > 0) {
						$label .= ' - ' . \wp_date('d.m.Y H:i', $modified);
					}
					if (\function_exists(__NAMESPACE__ . '\\cmx_system_storage_format_bytes')) {
						$label .= ' - ' . cmx_system_storage_format_bytes($size);
					}
					echo '<option value="' . \esc_attr($name) . '">' . \esc_html($label) . '</option>';
				}
				echo '</select>';
				echo '<a class="button cmx-system-backup-download" href="#" data-cmx-backup-download="local" data-cmx-backup-download-url="' . \esc_url($local_download_url) . '" title="Ausgewähltes Backup herunterladen" aria-label="Ausgewähltes Backup herunterladen"><span class="dashicons dashicons-download" aria-hidden="true"></span></a>';
				echo '<button type="submit" form="cmx-system-backup-delete-local" class="button cmx-system-backup-delete" data-cmx-backup-delete-button="local" title="Ausgewähltes Backup löschen" aria-label="Ausgewähltes Backup löschen"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>';
				echo '</div>';
			}
			echo '</div>';
			echo '<div class="cmx-system-backup-source" data-cmx-backup-panel="webdav"' . ($initial_backup_source === 'webdav' ? '' : ' hidden') . '>';
		if (!$webdav_ready) {
			echo '<p class="description">Bitte zuerst WebDAV vollständig einrichten.</p>';
		} elseif ($webdav_backups === []) {
			$webdav_reload_url = \add_query_arg([
				'page' => \defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG') ? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG') : 'cmx-einstellungen',
				'tab' => 'system',
				'sub' => 'backup',
				'cmx_backup_source' => 'webdav',
			], \admin_url('admin.php'));
			echo '<div class="cmx-system-backup-empty-row">';
			echo '<p class="description">Keine WebDAV-Backups gefunden.</p>';
			echo '<a class="button cmx-system-backup-refresh" href="' . \esc_url($webdav_reload_url) . '">Backups neu laden</a>';
			echo '</div>';
		} else {
			$webdav_download_url = \wp_nonce_url(\add_query_arg(['action' => 'cmx_cpt_transfer_backup_download', 'backup_source' => 'webdav'], \admin_url('admin-post.php')), 'cmx_cpt_transfer_backup_download');
			echo '<div class="cmx-system-backup-select-row">';
			echo '<select name="webdav_backup" class="regular-text">';
			foreach ($webdav_backups as $backup) {
				$name = (string) ($backup['name'] ?? '');
				$modified = (int) ($backup['modified'] ?? 0);
				$size = (int) ($backup['size'] ?? 0);
				$label = $name;
				if ($modified > 0) {
					$label .= ' - ' . \wp_date('d.m.Y H:i', $modified);
				}
				if (\function_exists(__NAMESPACE__ . '\\cmx_system_storage_format_bytes')) {
					$label .= ' - ' . cmx_system_storage_format_bytes($size);
				}
				echo '<option value="' . \esc_attr($name) . '">' . \esc_html($label) . '</option>';
			}
			echo '</select>';
			echo '<a class="button cmx-system-backup-download" href="#" data-cmx-backup-download="webdav" data-cmx-backup-download-url="' . \esc_url($webdav_download_url) . '" title="Ausgewähltes Backup herunterladen" aria-label="Ausgewähltes Backup herunterladen"><span class="dashicons dashicons-download" aria-hidden="true"></span></a>';
			echo '<button type="submit" form="cmx-system-backup-delete-webdav" class="button cmx-system-backup-delete" data-cmx-backup-delete-button="webdav" title="Ausgewähltes Backup löschen" aria-label="Ausgewähltes Backup löschen"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>';
			echo '</div>';
			}
			echo '</div>';
			echo '<p><label><input type="checkbox" name="import_all_modules" value="1" checked data-cmx-backup-import-all> Alle im Backup enthaltenen Module importieren</label></p>';
			echo '<div class="cmx-system-backup-list cmx-system-backup-import-modules" data-cmx-backup-import-list hidden>';
			foreach ($post_types as $post_type) {
				$label = \function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_post_type_label')
					? cmx_cpt_transfer_post_type_label($post_type)
					: $post_type;
				echo '<label><input type="checkbox" name="post_types[]" value="' . \esc_attr($post_type) . '"> <span>' . \esc_html($label) . '</span><code>' . \esc_html($post_type) . '</code></label>';
			}
			echo '</div>';
			echo '<p><label><input type="radio" name="import_mode" value="clear"> Alle gewählten Module zuvor leeren/löschen</label></p>';
			echo '<p><label><input type="radio" name="import_mode" value="update" checked> Existierende Beiträge mit gleichem Slug aktualisieren</label></p>';
			echo '<button type="submit" class="button button-primary">Backup importieren</button>';
		echo '</form>';
			echo '<form id="cmx-system-backup-delete-local" class="cmx-system-backup-delete-form" method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '" data-cmx-backup-delete-form="local">';
			\wp_nonce_field('cmx_cpt_transfer_backup_delete');
			echo '<input type="hidden" name="action" value="cmx_cpt_transfer_backup_delete">';
			echo '<input type="hidden" name="backup_source" value="local">';
			echo '<input type="hidden" name="backup_name" value="">';
			echo '</form>';
			echo '<form id="cmx-system-backup-delete-webdav" class="cmx-system-backup-delete-form" method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '" data-cmx-backup-delete-form="webdav">';
			\wp_nonce_field('cmx_cpt_transfer_backup_delete');
			echo '<input type="hidden" name="action" value="cmx_cpt_transfer_backup_delete">';
			echo '<input type="hidden" name="backup_source" value="webdav">';
			echo '<input type="hidden" name="backup_name" value="">';
			echo '</form>';
			echo '</section>';

			echo '</div>';

				echo '<section class="cmx-system-backup-card cmx-system-backup-settings" data-cmx-webdav-settings hidden>';
			echo '<h2>WebDAV</h2>';
			echo '<p>Diese Zugangsdaten werden nur für Backup-Export und Backup-Import verwendet.</p>';
			echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '">';
			\wp_nonce_field('cmx_cpt_transfer_backup_settings');
				echo '<input type="hidden" name="action" value="cmx_cpt_transfer_backup_settings">';
				echo '<div class="cmx-system-backup-fields">';
				$webdav_url_placeholder = \home_url('/archiv');
				echo '<label><span>Benutzername</span><input type="text" name="backup_webdav_user" value="' . \esc_attr((string) ($webdav_settings['user'] ?? '')) . '" autocomplete="username"></label>';
				echo '<label><span>Passwort / App-Passwort</span><input type="password" name="backup_webdav_password" value="" autocomplete="new-password" placeholder="' . (!empty($webdav_settings['password']) ? 'gespeichert - leer lassen zum Behalten' : '') . '"></label>';
				echo '<label><span>WebDAV URL</span><input type="url" name="backup_webdav_url" value="' . \esc_attr((string) ($webdav_settings['url'] ?? '')) . '" placeholder="' . \esc_attr($webdav_url_placeholder) . '"></label>';
				echo '<label><span>Zielordner</span><input type="text" name="backup_webdav_path" value="' . \esc_attr((string) ($webdav_settings['path'] ?? 'backups')) . '" placeholder="backups"></label>';
				echo '</div>';
			echo '<p><label><input type="checkbox" name="backup_webdav_clear_password" value="1"> gespeichertes Passwort löschen</label></p>';
			echo '<button type="submit" class="button">WebDAV speichern</button>';
			echo $webdav_ready
				? '<span class="cmx-system-backup-status is-ready">WebDAV eingerichtet</span>'
				: '<span class="cmx-system-backup-status">WebDAV noch nicht vollständig eingerichtet</span>';
			echo '</form>';
			echo '</section>';

			echo '<div class="cmx-system-backup-note">';
			echo '<div class="cmx-system-backup-note-text"><strong>Hinweis zu grossen Backups:</strong> Lokal speichert das ZIP unter <code>/wp-content/uploads/misbuero/archiv/backups/</code>. WebDAV speichert es im angegebenen Zielordner, damit es später direkt wieder ausgewählt werden kann.</div>';
			echo '<form class="cmx-system-backup-upload" method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
			\wp_nonce_field('cmx_cpt_transfer_backup_upload');
			echo '<input type="hidden" name="action" value="cmx_cpt_transfer_backup_upload">';
			echo '<input id="cmx-system-backup-upload-file" class="cmx-system-backup-upload-file" type="file" name="cmx_cpt_transfer_backup_zip" accept=".zip,application/zip" required>';
			echo '<label class="button cmx-system-backup-upload-button" for="cmx-system-backup-upload-file"><span class="dashicons dashicons-upload" aria-hidden="true"></span> Backup hochladen</label>';
			echo '</form>';
			echo '</div>';
			if (\function_exists(__NAMESPACE__ . '\\cmx_render_system_bulk_delete_panel')) {
				cmx_render_system_bulk_delete_panel();
			}
			echo '</div>';
		?>
		<style>
			.cmx-system-backup{max-width:1180px}
				.cmx-system-backup-settings{margin-top:28px;margin-bottom:24px}
			.cmx-system-backup-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start}
			.cmx-system-backup-card{padding:22px;border:1px solid #d6e0ec;border-radius:10px;background:#fff}
			.cmx-system-backup-card h2{margin:0 0 8px;font-size:20px}
			.cmx-system-backup-card p{margin:0 0 16px;color:#5f6b7a}
			.cmx-system-backup-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 18px;margin-bottom:12px}
			.cmx-system-backup-fields label{display:grid;gap:5px;font-weight:700}
			.cmx-system-backup-fields input{width:100%;max-width:none}
				.cmx-system-backup-status{display:inline-flex;margin-top:10px;margin-left:12px;color:#8a6d1f;font-weight:700}
			.cmx-system-backup-status.is-ready{color:#166534}
			.cmx-system-backup-choice{display:flex;flex-wrap:wrap;gap:10px 18px;margin:0 0 16px}
			.cmx-system-backup-choice label{display:inline-flex;align-items:center;gap:7px;font-weight:700}
			.cmx-system-backup-all{display:inline-flex;align-items:center;gap:8px;margin-bottom:12px;font-weight:700}
			.cmx-system-backup-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 14px;margin:0 0 18px;padding:14px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc}
			.cmx-system-backup-list label{display:grid;grid-template-columns:18px minmax(0,1fr) auto;gap:8px;align-items:center;min-width:0}
			.cmx-system-backup-list span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
			.cmx-system-backup-list code{font-size:11px;color:#64748b;background:transparent}
			.cmx-system-backup-source{margin:0 0 12px}
			.cmx-system-backup-source select{max-width:100%;width:100%}
			.cmx-system-backup-select-row{display:flex;align-items:center;gap:8px}
			.cmx-system-backup-select-row select{flex:1 1 auto;min-width:0}
			.button.cmx-system-backup-download{display:inline-flex;align-items:center;justify-content:center;width:42px;height:40px;min-height:40px;padding:0;line-height:1;box-sizing:border-box;flex:0 0 42px;color:#b32d2e;border-color:#b32d2e;text-decoration:none}
			.button.cmx-system-backup-download .dashicons{display:block;width:24px;height:24px;margin:0;font-size:24px;line-height:24px}
			.cmx-system-backup-delete-form{display:none}
			.button.cmx-system-backup-delete{display:inline-flex;align-items:center;justify-content:center;width:42px;height:40px;min-height:40px;padding:0;line-height:1;box-sizing:border-box;color:#b32d2e;border-color:#b32d2e;background:#fff}
			.button.cmx-system-backup-delete .dashicons{display:block;width:22px;height:22px;margin:0;font-size:22px;line-height:22px}
			.button.cmx-system-backup-delete:hover,.button.cmx-system-backup-delete:focus{color:#8f211b;border-color:#8f211b;background:#fef2f2}
			.cmx-system-backup-empty-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
			.cmx-system-backup-empty-row p{margin:0}
			.cmx-system-backup-refresh{display:inline-flex!important;align-items:center;justify-content:center;height:34px;min-height:34px;padding:0 14px;line-height:1;box-sizing:border-box}
			.cmx-system-backup-note{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:22px;padding:16px 18px;border-left:4px solid #b32d2e;background:#fff;color:#1f2937}
			.cmx-system-backup-note-text{min-width:0}
			.cmx-system-backup-upload{display:flex;align-items:center;flex:0 0 auto;margin:0}
			.cmx-system-backup-upload-file{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
			.button.cmx-system-backup-upload-button{display:inline-flex;align-items:center;gap:6px;min-height:34px;color:#b32d2e;border-color:#b32d2e}
			.button.cmx-system-backup-upload-button .dashicons{width:18px;height:18px;font-size:18px;line-height:18px}
			@media (max-width:960px){.cmx-system-backup-grid,.cmx-system-backup-fields{grid-template-columns:1fr}.cmx-system-backup-list{grid-template-columns:1fr}}
			@media (max-width:782px){.cmx-system-backup-note{align-items:flex-start;flex-direction:column}.cmx-system-backup-upload{width:100%}.button.cmx-system-backup-upload-button{justify-content:center;width:100%}}
		</style>
		<script>
			document.addEventListener('DOMContentLoaded', function(){
				var all = document.getElementById('cmx-backup-select-all');
				if (!all) return;
				var boxes = Array.prototype.slice.call(document.querySelectorAll('[data-cmx-backup-export-list] input[type="checkbox"]'));
				all.addEventListener('change', function(){
					boxes.forEach(function(box){ box.checked = all.checked; });
				});
			boxes.forEach(function(box){
				box.addEventListener('change', function(){
					all.checked = boxes.length > 0 && boxes.every(function(item){ return item.checked; });
				});
			});
				var sourceInputs = Array.prototype.slice.call(document.querySelectorAll('[data-cmx-backup-source]'));
				var sourcePanels = Array.prototype.slice.call(document.querySelectorAll('[data-cmx-backup-panel]'));
				var webdavSettings = document.querySelector('[data-cmx-webdav-settings]');
				function updateSourcePanels(){
					var active = (sourceInputs.find(function(input){ return input.checked; }) || {}).value || 'local';
					sourcePanels.forEach(function(panel){
						panel.hidden = panel.getAttribute('data-cmx-backup-panel') !== active;
					});
					updateWebdavSettings();
					updateDownloadLinks();
				}
				function updateWebdavSettings(){
					if (!webdavSettings) return;
					var exportWebdav = document.querySelector('input[name="backup_target"][value="webdav"]');
					var importWebdav = document.querySelector('input[name="backup_source"][value="webdav"]');
					webdavSettings.hidden = !((exportWebdav && exportWebdav.checked) || (importWebdav && importWebdav.checked));
				}
				function updateDownloadLinks(){
					Array.prototype.slice.call(document.querySelectorAll('[data-cmx-backup-download]')).forEach(function(button){
						var source = button.getAttribute('data-cmx-backup-download');
						var panel = document.querySelector('[data-cmx-backup-panel="' + source + '"]');
						var select = panel ? panel.querySelector('select') : null;
						var base = button.getAttribute('data-cmx-backup-download-url') || '';
						if (!select || !select.value || !base) {
							button.href = '#';
							button.classList.add('disabled');
							button.setAttribute('aria-disabled', 'true');
							return;
						}
						button.href = base + (base.indexOf('?') === -1 ? '?' : '&') + 'backup_name=' + encodeURIComponent(select.value);
						button.classList.remove('disabled');
						button.removeAttribute('aria-disabled');
					});
					Array.prototype.slice.call(document.querySelectorAll('[data-cmx-backup-delete-form]')).forEach(function(form){
						var source = form.getAttribute('data-cmx-backup-delete-form');
						var panel = document.querySelector('[data-cmx-backup-panel="' + source + '"]');
						var select = panel ? panel.querySelector('select') : null;
						var input = form.querySelector('input[name="backup_name"]');
						var button = document.querySelector('[data-cmx-backup-delete-button="' + source + '"]');
						var value = select && select.value ? select.value : '';
						if (input) {
							input.value = value;
						}
						if (button) {
							button.disabled = value === '';
						}
					});
				}
					sourceInputs.forEach(function(input){
						input.addEventListener('change', updateSourcePanels);
					});
					Array.prototype.slice.call(document.querySelectorAll('[data-cmx-backup-panel] select')).forEach(function(select){
						select.addEventListener('change', updateDownloadLinks);
					});
					Array.prototype.slice.call(document.querySelectorAll('input[name="backup_target"]')).forEach(function(input){
						input.addEventListener('change', updateWebdavSettings);
					});
					Array.prototype.slice.call(document.querySelectorAll('[data-cmx-backup-delete-form]')).forEach(function(form){
						form.addEventListener('submit', function(event){
							var input = form.querySelector('input[name="backup_name"]');
							var name = input ? input.value : '';
							if (!name || !window.confirm('Backup wirklich löschen?\\n\\n' + name)) {
								event.preventDefault();
							}
						});
					});
					updateSourcePanels();
				var importAll = document.querySelector('[data-cmx-backup-import-all]');
				var importList = document.querySelector('[data-cmx-backup-import-list]');
				function updateImportModules(){
					if (!importAll || !importList) return;
					importList.hidden = importAll.checked;
					Array.prototype.slice.call(importList.querySelectorAll('input[type="checkbox"]')).forEach(function(box){
						box.disabled = importAll.checked;
						if (!importAll.checked && !box.checked) {
							box.checked = true;
						}
					});
				}
				if (importAll) {
					importAll.addEventListener('change', updateImportModules);
					updateImportModules();
				}
				var uploadInput = document.getElementById('cmx-system-backup-upload-file');
				if (uploadInput) {
					uploadInput.addEventListener('change', function(){
						if (uploadInput.files && uploadInput.files.length > 0 && uploadInput.form) {
							uploadInput.form.submit();
						}
					});
				}
			});
		</script>
		<?php
	}
}

\add_action('admin_init', __NAMESPACE__ . '\\cmx_register_system_tab');
function cmx_register_system_tab(): void {
	$general_page = 'cmx_tab_system__general';
	$storage_page = 'cmx_tab_system__storage';
	$backup_page = 'cmx_tab_system__backup';

	\add_settings_section(
		'cmx_sec_system',
		__('System', 'default'),
		'__return_false',
		$general_page
	);

	\add_settings_section(
		'cmx_sec_system_storage',
		'',
		__NAMESPACE__ . '\\cmx_render_system_storage_panel',
		$storage_page
	);

	\add_settings_section(
		'cmx_sec_system_backup',
		'',
		__NAMESPACE__ . '\\cmx_render_system_backup_panel',
		$backup_page
	);

	\add_settings_field(
		'cmx_system_dokuscan',
		'DokuScan (WebDAV)',
		function (): void {
			cmx_system_render_copyable_instance_link(
				'cmx-system-dokuscan',
				(string) \home_url('/scanner/'),
				'DokuScan in neuem Tab öffnen und Link kopieren'
			);
		},
		$general_page,
		'cmx_sec_system'
	);

	\add_settings_field(
		'cmx_system_archiv',
		'Archiv',
		function (): void {
			cmx_system_render_copyable_instance_link(
				'cmx-system-archiv',
				(string) \home_url('/archiv/'),
				'Archiv in neuem Tab öffnen und Link kopieren'
			);
		},
		$general_page,
		'cmx_sec_system'
	);

	\add_settings_field(
		'cmx_system_netvoip',
		'NetVoip',
		function (): void {
			cmx_system_render_copyable_instance_link(
				'cmx-system-netvoip',
				cmx_system_netvoip_url(),
				'NetVoip in neuem Tab öffnen und Link kopieren'
			);
		},
		$general_page,
		'cmx_sec_system'
	);

	\register_setting(
		'cmx_einstellungen',
		'mis_buero_openai_key',
		[
			'type'              => 'string',
			'sanitize_callback' => static function ($value): string {
				if ($value === null) {
					$value = \get_option('mis_buero_openai_key', '');
				}
				return \sanitize_text_field((string) $value);
			},
		]
	);

	\add_settings_field(
		'mis_buero_openai_key',
		'OpenAI API Key',
		function (): void {
			$val = (string) \get_option('mis_buero_openai_key', '');
			echo '<input type="text" name="mis_buero_openai_key" class="regular-text" value="' . \esc_attr($val) . '">';
			echo '<p class="description">Wird für OCR und Produkttexte verwendet</p>';
		},
		$general_page,
		'cmx_sec_system'
	);

	\register_setting(
		'cmx_einstellungen',
		'mis_buero_services_url',
		[
			'type'              => 'string',
			'sanitize_callback' => static function ($value): string {
				if ($value === null) {
					$value = \get_option('mis_buero_services_url', 'https://services.misbuero.ch');
				}
				$value = \trim((string) $value);
				if ($value === '') {
					return 'https://services.misbuero.ch';
				}
				return \esc_url_raw($value);
			},
		]
	);

	\register_setting(
		'cmx_einstellungen',
		'mis_buero_services_api_key',
		[
			'type'              => 'string',
			'sanitize_callback' => static function ($value): string {
				if ($value === null) {
					$value = \get_option('mis_buero_services_api_key', '');
				}
				return \sanitize_text_field((string) $value);
			},
		]
	);

	\add_settings_field(
		'mis_buero_services_url',
		'Services URL',
		function (): void {
			$val = (string) \get_option('mis_buero_services_url', 'https://services.misbuero.ch');
			echo '<input type="url" name="mis_buero_services_url" class="regular-text" value="' . \esc_attr($val) . '" placeholder="https://services.misbuero.ch">';
			echo '<p class="description">Endpoint für PDF-Prüfung und Extraktion</p>';
		},
		$general_page,
		'cmx_sec_system'
	);

	\add_settings_field(
		'mis_buero_services_api_key',
		'Services API Key',
		function (): void {
			$val = (string) \get_option('mis_buero_services_api_key', '');
			echo '<input type="password" name="mis_buero_services_api_key" class="regular-text" value="' . \esc_attr($val) . '" autocomplete="new-password">';
			echo '<p class="description">Bearer-Token für services.misbuero.ch. Alternativ per MIS_BUERO_SERVICES_API_KEY in wp-config.php setzen.</p>';
		},
		$general_page,
		'cmx_sec_system'
	);

	\add_settings_field(
		'cmx_system_debug_mode',
		'Debug-Mode',
		__NAMESPACE__ . '\\cmx_field_checkbox',
		$general_page,
		'cmx_sec_system',
		[
			'key'   => \defined(__NAMESPACE__ . '\\CMX_SYSTEM_DEBUG_MODE_KEY')
				? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_DEBUG_MODE_KEY')
				: 'debug_mode',
			'label' => 'Debug-Modus aktivieren',
		]
	);

	if (\function_exists(__NAMESPACE__ . '\\cmx_system_is_cloudmeister_user') && cmx_system_is_cloudmeister_user()) {
		\add_settings_section(
			'cmx_sec_modules',
			'Module',
			'__return_false',
			$general_page
		);

		\add_settings_field(
			'cmx_system_pro_version',
			'PRO Version',
			function (): void {
				$option_name = \function_exists(__NAMESPACE__ . '\\cmx_system_settings_option_name')
					? (string) cmx_system_settings_option_name()
					: 'cmx_einstellungen';
				$key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_PRO_VERSION_KEY')
					? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_PRO_VERSION_KEY')
					: 'pro_version';
				$options = (array) \get_option($option_name, []);
				$checked = !empty($options[$key]);

				echo '<label>';
				echo '<input type="hidden" name="' . \esc_attr($option_name) . '[' . \esc_attr($key) . ']" value="0">';
				echo '<input type="checkbox" name="' . \esc_attr($option_name) . '[' . \esc_attr($key) . ']" value="1"' . \checked($checked, true, false) . '> ';
				echo 'E-Mail Client, Termine, VideoCalls';
				echo '</label>';
			},
			$general_page,
			'cmx_sec_system'
		);

		\add_settings_field(
			'cmx_system_carent',
			'Carent',
			function (): void {
				$option_name = \function_exists(__NAMESPACE__ . '\\cmx_system_settings_option_name')
					? (string) cmx_system_settings_option_name()
					: 'cmx_einstellungen';
				$key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_CARENT_KEY')
					? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_CARENT_KEY')
					: 'carent';
				$options = (array) \get_option($option_name, []);
				$checked = !empty($options[$key]);

				echo '<label>';
				echo '<input type="hidden" name="' . \esc_attr($option_name) . '[' . \esc_attr($key) . ']" value="0">';
				echo '<input type="checkbox" name="' . \esc_attr($option_name) . '[' . \esc_attr($key) . ']" value="1"' . \checked($checked, true, false) . '> ';
				echo \esc_html__('CaRent aktivieren', 'cmx-misbuero');
				echo '</label>';
			},
			$general_page,
			'cmx_sec_modules'
		);

		\add_settings_field(
			'cmx_system_max_workplaces',
			'Max. Arbeitsplätze',
			function (): void {
				$option_name = \function_exists(__NAMESPACE__ . '\\cmx_system_settings_option_name')
					? (string) cmx_system_settings_option_name()
					: 'cmx_einstellungen';
				$key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_MAX_WORKPLACES_KEY')
					? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_MAX_WORKPLACES_KEY')
					: 'max_workplaces';
				$options = (array) \get_option($option_name, []);
				$value = isset($options[$key]) ? (int) $options[$key] : 1;
				if ($value <= 0) {
					$value = 1;
				}
				echo '<input type="number" min="1" step="1" name="' . \esc_attr($option_name) . '[' . \esc_attr($key) . ']" value="' . \esc_attr((string) $value) . '" class="small-text">';
			},
			$general_page,
			'cmx_sec_system'
		);
	}
}

\add_filter('pre_update_option_' . 'cmx_einstellungen', function ($value, $old_value) {
	$value = \is_array($value) ? $value : [];
	$old_value = \is_array($old_value) ? $old_value : [];
	$key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_MAX_WORKPLACES_KEY')
		? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_MAX_WORKPLACES_KEY')
		: 'max_workplaces';
	$pro_key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_PRO_VERSION_KEY')
		? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_PRO_VERSION_KEY')
		: 'pro_version';
	$carent_key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_CARENT_KEY')
		? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_CARENT_KEY')
		: 'carent';
	$debug_key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_DEBUG_MODE_KEY')
		? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_DEBUG_MODE_KEY')
		: 'debug_mode';

	if (\function_exists(__NAMESPACE__ . '\\cmx_system_is_cloudmeister_user') && !cmx_system_is_cloudmeister_user()) {
		$value[$key] = isset($old_value[$key]) ? (int) $old_value[$key] : 1;
		$value[$pro_key] = !empty($old_value[$pro_key]) ? '1' : '0';
		$value[$carent_key] = !empty($old_value[$carent_key]) ? '1' : '0';
		$value[$debug_key] = !empty($value[$debug_key]) ? '1' : '0';
		return $value;
	}

	$max = isset($value[$key]) ? (int) $value[$key] : (isset($old_value[$key]) ? (int) $old_value[$key] : 1);
	$value[$key] = $max > 0 ? $max : 1;
	$value[$pro_key] = !empty($value[$pro_key]) ? '1' : '0';
	$value[$carent_key] = !empty($value[$carent_key]) ? '1' : '0';
	$value[$debug_key] = !empty($value[$debug_key]) ? '1' : '0';
	return $value;
}, 10, 2);

\add_action('admin_post_cmx_system_bulk_delete_post_type', __NAMESPACE__ . '\\cmx_system_handle_bulk_delete_post_type');
function cmx_system_handle_bulk_delete_post_type(): void {
	if (!\current_user_can('manage_options')) {
		\wp_die('Keine Berechtigung.');
	}

	\check_admin_referer('cmx_system_bulk_delete_post_type');

	$post_type = isset($_POST['cmx_bulk_delete_post_type']) && !\is_array($_POST['cmx_bulk_delete_post_type'])
		? \sanitize_key((string) \wp_unslash($_POST['cmx_bulk_delete_post_type']))
		: '';
	$delete_permanently = !empty($_POST['cmx_bulk_delete_force']);
	$post_types = cmx_system_bulk_delete_post_types();

	if ($post_type === '' || !isset($post_types[$post_type])) {
		cmx_system_bulk_delete_redirect([
			'cmx_system_bulk_delete_notice' => 'error',
			'cmx_system_bulk_delete_message' => 'invalid_post_type',
		]);
	}

	$result = cmx_system_bulk_delete_run($post_type, $delete_permanently);

	cmx_system_bulk_delete_redirect([
		'cmx_system_bulk_delete_notice'  => 'success',
		'cmx_system_bulk_delete_post_type' => $post_type,
		'cmx_system_bulk_delete_mode'    => $delete_permanently ? 'delete' : 'trash',
		'cmx_system_bulk_delete_count'   => (int) ($result['processed'] ?? 0),
		'cmx_system_bulk_delete_failed'  => (int) ($result['failed'] ?? 0),
		'cmx_system_bulk_delete_skipped' => (int) ($result['skipped'] ?? 0),
	]);
}

\add_action('wp_ajax_cmx_system_bulk_delete_post_type_batch', __NAMESPACE__ . '\\cmx_system_handle_bulk_delete_post_type_batch');
function cmx_system_handle_bulk_delete_post_type_batch(): void {
	if (!\current_user_can('manage_options')) {
		\wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
	}

	\check_ajax_referer('cmx_system_bulk_delete_post_type');

	$post_type = isset($_POST['cmx_bulk_delete_post_type']) && !\is_array($_POST['cmx_bulk_delete_post_type'])
		? \sanitize_key((string) \wp_unslash($_POST['cmx_bulk_delete_post_type']))
		: '';
	$delete_permanently = !empty($_POST['cmx_bulk_delete_force']);
	$after_id = isset($_POST['after_id']) && !\is_array($_POST['after_id'])
		? \max(0, (int) $_POST['after_id'])
		: 0;
	$post_types = cmx_system_bulk_delete_post_types();

	if ($post_type === '' || !isset($post_types[$post_type])) {
		\wp_send_json_error(['message' => 'Bitte zuerst ein gültiges Modul auswählen.'], 400);
	}

	$total = cmx_system_bulk_delete_total_count($post_type, $delete_permanently);
	$batch = cmx_system_bulk_delete_process_batch($post_type, $delete_permanently, $after_id, 5.0, 100);

	\wp_send_json_success([
		'total'     => $total,
		'processed' => (int) ($batch['processed'] ?? 0),
		'failed'    => (int) ($batch['failed'] ?? 0),
		'skipped'   => (int) ($batch['skipped'] ?? 0),
		'last_id'   => (int) ($batch['last_id'] ?? $after_id),
		'done'      => !empty($batch['done']),
	]);
}

\add_action('all_admin_notices', function (): void {
	$page = isset($_GET['page']) ? \sanitize_key((string) \wp_unslash($_GET['page'])) : '';
	$tab = isset($_GET['tab']) ? \sanitize_key((string) \wp_unslash($_GET['tab'])) : '';
	if ($page !== (\defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG') ? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG') : 'cmx-einstellungen') || $tab !== 'system') {
		return;
	}

	$notice = isset($_GET['cmx_system_bulk_delete_notice']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_system_bulk_delete_notice'])) : '';
	if ($notice === '') {
		return;
	}

	if ($notice === 'error') {
		$message_key = isset($_GET['cmx_system_bulk_delete_message']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_system_bulk_delete_message'])) : '';
		$message = $message_key === 'invalid_post_type'
			? 'Bitte zuerst ein gültiges Modul auswählen.'
			: 'Die Aktion konnte nicht ausgeführt werden.';
		echo '<div class="notice notice-error is-dismissible"><p>' . \esc_html($message) . '</p></div>';
		return;
	}

	$post_type = isset($_GET['cmx_system_bulk_delete_post_type']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_system_bulk_delete_post_type'])) : '';
	$mode = isset($_GET['cmx_system_bulk_delete_mode']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_system_bulk_delete_mode'])) : 'trash';
	$processed = isset($_GET['cmx_system_bulk_delete_count']) ? (int) $_GET['cmx_system_bulk_delete_count'] : 0;
	$failed = isset($_GET['cmx_system_bulk_delete_failed']) ? (int) $_GET['cmx_system_bulk_delete_failed'] : 0;
	$skipped = isset($_GET['cmx_system_bulk_delete_skipped']) ? (int) $_GET['cmx_system_bulk_delete_skipped'] : 0;

	$post_type_label = $post_type;
	$post_type_object = $post_type !== '' ? \get_post_type_object($post_type) : null;
	if ($post_type_object instanceof \WP_Post_Type) {
		$post_type_label = (string) ($post_type_object->labels->name ?? $post_type_object->label ?? $post_type);
	}

	$action_label = $mode === 'delete' ? 'endgültig gelöscht' : 'in den Papierkorb verschoben';
	$message = \number_format_i18n($processed) . ' Einträge von "' . $post_type_label . '" wurden ' . $action_label . '.';

	if ($processed === 0 && $failed === 0 && $skipped === 0) {
		$message = 'Für "' . $post_type_label . '" wurden keine Einträge gefunden.';
	}
	if ($failed > 0) {
		$message .= ' Fehler: ' . \number_format_i18n($failed) . '.';
	}
	if ($skipped > 0) {
		$message .= ' Übersprungen: ' . \number_format_i18n($skipped) . '.';
	}

	echo '<div class="notice notice-success is-dismissible"><p>' . \esc_html($message) . '</p></div>';
});
