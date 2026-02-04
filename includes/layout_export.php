<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\is_admin()) {
	return;
}

function cmx_layout_export_can(): bool {
	if (\function_exists(__NAMESPACE__ . '\\cmx_help_is_cloud_meister')) {
		return cmx_help_is_cloud_meister();
	}
	$user = \wp_get_current_user();
	if (!$user || !$user->exists()) return false;
	if ($user->display_name === 'CLOUD Meister') return true;
	if ($user->user_login === 'cloudmeister') return true;
	return \current_user_can('manage_options');
}

function cmx_layout_export_collect(int $user_id): array {
	$meta = \get_user_meta($user_id);
	$keys = [];
	foreach ($meta as $key => $values) {
		if (!\preg_match('/(^|_)meta-box-order_|(^|_)metaboxhidden_|(^|_)closedpostboxes_|(^|_)screen_layout_/', (string)$key)) {
			continue;
		}
		$value = \is_array($values) ? ($values[0] ?? null) : $values;
		if ($value === null) {
			continue;
		}
		if (\is_string($value)) {
			$maybe = \maybe_unserialize($value);
			if ($maybe !== $value) {
				$value = $maybe;
			}
		}

		$is_meta_order = \strpos($key, 'meta-box-order_') !== false;
		$is_hidden = \strpos($key, 'metaboxhidden_') !== false;
		$is_closed = \strpos($key, 'closedpostboxes_') !== false;
		$is_screen_layout = \strpos($key, 'screen_layout_') !== false;

		if (($is_meta_order || $is_hidden || $is_closed) && !\is_array($value)) {
			if (\is_string($value)) {
				$parts = \array_values(\array_filter(\array_map('trim', \explode(',', $value))));
				$value = $parts;
			}
		}
		if ($is_screen_layout && \is_array($value)) {
			$value = \reset($value);
		}

		$keys[(string)$key] = $value;
	}
	\ksort($keys);

	$blog_id = \function_exists('get_current_blog_id') ? (int)\get_current_blog_id() : 0;
	global $wpdb;
	$blog_prefix = \is_object($wpdb) ? (string)$wpdb->get_blog_prefix($blog_id) : '';

	$user = \get_user_by('id', $user_id);
	$payload = [
		'format' => 1,
		'exported_at' => \gmdate('c'),
		'site_url' => \home_url('/'),
		'blog_id' => $blog_id,
		'blog_prefix' => $blog_prefix,
		'user' => [
			'id' => $user ? (int)$user->ID : $user_id,
			'login' => $user ? (string)$user->user_login : '',
			'display_name' => $user ? (string)$user->display_name : '',
		],
		'layout' => $keys,
	];

	return $payload;
}

function cmx_layout_export_get_json(): string {
	$user = \wp_get_current_user();
	$payload = cmx_layout_export_collect((int)$user->ID);
	$json = \wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	if (!\is_string($json)) {
		$json = '{}';
	}
	return $json;
}

function cmx_layout_export_file_path(): string {
	if (\function_exists(__NAMESPACE__ . '\\cmx_layout_defaults_file_path')) {
		return \CLOUDMEISTER\CMX\Buero\cmx_layout_defaults_file_path();
	}
	return \dirname(__DIR__) . '/assets/layout_defaults.json';
}

\add_action('admin_menu', function () {
	if (!cmx_layout_export_can()) return;
	\add_management_page(
		'Layout Export',
		'Layout Export',
		'manage_options',
		'cmx-layout-export',
		__NAMESPACE__ . '\\cmx_render_layout_export_page'
	);
});

function cmx_render_layout_export_page(): void {
	if (!cmx_layout_export_can()) {
		\wp_die('forbidden', 403);
	}
	$nonce = \wp_create_nonce('cmx_layout_export');
	$download_url = \esc_url(\admin_url('admin-post.php?action=cmx_layout_export&_wpnonce=' . $nonce));
	$save_url = \esc_url(\admin_url('admin-post.php?action=cmx_layout_export_save_file&_wpnonce=' . $nonce));
	$path = cmx_layout_export_file_path();
	$dir = \dirname($path);
	$file_exists = \is_file($path);
	$dir_writable = \is_dir($dir) && \is_writable($dir);
	$file_writable = $file_exists ? \is_writable($path) : $dir_writable;
	$json = cmx_layout_export_get_json();
	$saved = isset($_GET['cmx_layout_saved']) ? (string) $_GET['cmx_layout_saved'] : '';
	?>
	<div class="wrap">
		<h1>Layout Export</h1>
		<p>Dieser Export enthält alle gespeicherten Metabox-Layouts des aktuellen Users (inkl. Dashboard).</p>
		<?php if ($saved === '1'): ?>
			<div class="notice notice-success is-dismissible"><p>layout_defaults.json wurde gespeichert.</p></div>
		<?php elseif ($saved === '0'): ?>
			<div class="notice notice-error is-dismissible"><p>layout_defaults.json konnte nicht gespeichert werden.</p></div>
		<?php endif; ?>
		<p>
			<a class="button button-primary" href="<?php echo $download_url; ?>">JSON herunterladen</a>
			<?php if ($file_writable): ?>
				<a class="button" href="<?php echo $save_url; ?>">In assets/layout_defaults.json speichern</a>
			<?php else: ?>
				<span style="margin-left:8px;color:#a00;">assets/ ist nicht beschreibbar.</span>
			<?php endif; ?>
		</p>
		<p><code><?php echo esc_html($path); ?></code></p>
		<textarea style="width:100%;min-height:360px;font-family:monospace;" readonly><?php echo esc_textarea($json); ?></textarea>
	</div>
	<?php
}

\add_action('admin_post_cmx_layout_export', function () {
	if (!cmx_layout_export_can()) {
		\wp_die('forbidden', 403);
	}
	\check_admin_referer('cmx_layout_export');

	$json = cmx_layout_export_get_json();
	$filename = 'cmx-layout-export-' . \gmdate('Ymd-His') . '.json';

	\header('Content-Type: application/json; charset=utf-8');
	\header('Content-Disposition: attachment; filename="' . $filename . '"');
	\header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	\header('Pragma: no-cache');
	echo $json;
	exit;
});

\add_action('admin_post_cmx_layout_export_save_file', function () {
	if (!cmx_layout_export_can()) {
		\wp_die('forbidden', 403);
	}
	\check_admin_referer('cmx_layout_export');

	$json = cmx_layout_export_get_json();
	$path = cmx_layout_export_file_path();
	$dir = \dirname($path);

	$ok = false;
	if (\is_dir($dir) && \is_writable($dir)) {
		$bytes = \file_put_contents($path, $json, LOCK_EX);
		if ($bytes !== false) {
			$ok = true;
		}
	}

	$redirect = \admin_url('tools.php?page=cmx-layout-export');
	$redirect = \add_query_arg(['cmx_layout_saved' => $ok ? '1' : '0'], $redirect);
	\wp_safe_redirect($redirect);
	exit;
});
