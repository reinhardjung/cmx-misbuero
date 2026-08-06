<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/*
 * TEMP PERFORMANCE LOG
 * Entfernen/deaktivieren: CMX_ENABLE_SAVE_PERF_LOG in cmx-misbuero.php auf false setzen
 * oder diesen require-Block wieder entfernen.
 */

if (!\function_exists(__NAMESPACE__ . '\\cmx_save_perf_target')) {
	function cmx_save_perf_target(?int $post_id = null, string $fallback_post_type = ''): array {
		$post_id = $post_id ?: (isset($_POST['post_ID']) ? (int) $_POST['post_ID'] : 0);
		$post_type = \sanitize_key($fallback_post_type);

		if ($post_type === '' && isset($_POST['post_type'])) {
			$post_type = \sanitize_key((string) \wp_unslash($_POST['post_type']));
		}
		if ($post_type === '' && isset($_GET['post_type'])) {
			$post_type = \sanitize_key((string) \wp_unslash($_GET['post_type']));
		}
		if ($post_id <= 0 && isset($_GET['post'])) {
			$post_id = (int) \wp_unslash($_GET['post']);
		}
		if ($post_type === '' && $post_id > 0) {
			$post_type = (string) \get_post_type($post_id);
		}
		if ($post_type === '' && \function_exists('get_current_screen')) {
			$screen = \get_current_screen();
			if ($screen && !empty($screen->post_type)) {
				$post_type = \sanitize_key((string) $screen->post_type);
			}
		}

		if (!\in_array($post_type, ['kontakte', 'artikel'], true)) {
			return [0, ''];
		}

		return [$post_id, $post_type];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_save_perf_is_request')) {
	function cmx_save_perf_is_request(?int $post_id = null, string $fallback_post_type = ''): bool {
		if (!\is_admin()) {
			return false;
		}

		[, $post_type] = cmx_save_perf_target($post_id, $fallback_post_type);
		return $post_type !== '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_save_perf_log')) {
	function cmx_save_perf_log(string $event, ?int $post_id = null, string $fallback_post_type = '', array $extra = []): void {
		if (!cmx_save_perf_is_request($post_id, $fallback_post_type)) {
			return;
		}

		[$target_id, $post_type] = cmx_save_perf_target($post_id, $fallback_post_type);
		$start = isset($GLOBALS['cmx_save_perf_start']) ? (float) $GLOBALS['cmx_save_perf_start'] : 0.0;
		if ($start <= 0.0) {
			$start = \microtime(true);
			$GLOBALS['cmx_save_perf_start'] = $start;
		}
		$last = isset($GLOBALS['cmx_save_perf_last']) ? (float) $GLOBALS['cmx_save_perf_last'] : $start;
		$now = \microtime(true);
		$GLOBALS['cmx_save_perf_last'] = $now;

		$data = \array_merge([
			'event' => $event,
			'post_id' => $target_id,
			'post_type' => $post_type,
			'elapsed_ms' => \round(($now - $start) * 1000, 1),
			'delta_ms' => \round(($now - $last) * 1000, 1),
			'memory_mb' => \round(\memory_get_usage(true) / 1048576, 1),
			'peak_mb' => \round(\memory_get_peak_usage(true) / 1048576, 1),
		], $extra);

		\error_log('[CMX SavePerf] ' . \wp_json_encode($data, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
	}
}

$GLOBALS['cmx_save_perf_start'] = \microtime(true);
$GLOBALS['cmx_save_perf_last'] = $GLOBALS['cmx_save_perf_start'];

\add_action('admin_init', static function (): void {
	cmx_save_perf_log('admin_init', null, '', [
		'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
		'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
	]);
}, -10000);

\add_action('current_screen', static function ($screen): void {
	if (!$screen || empty($screen->post_type)) {
		return;
	}
	cmx_save_perf_log('current_screen', null, (string) $screen->post_type, [
		'screen_id' => (string) ($screen->id ?? ''),
		'base' => (string) ($screen->base ?? ''),
	]);
}, -10000);

\add_action('load-edit.php', static function (): void {
	cmx_save_perf_log('load-edit.php');
}, -10000);

\add_action('load-post.php', static function (): void {
	cmx_save_perf_log('load-post.php');
}, -10000);

\add_action('pre_get_posts', static function (\WP_Query $query): void {
	if (!$query->is_main_query()) {
		return;
	}
	[, $post_type] = cmx_save_perf_target(null, (string) $query->get('post_type'));
	if ($post_type === '') {
		return;
	}
	cmx_save_perf_log('pre_get_posts', null, $post_type, [
		'post_status' => $query->get('post_status'),
		'orderby' => $query->get('orderby'),
		'meta_query_count' => \is_array($query->get('meta_query')) ? \count((array) $query->get('meta_query')) : 0,
	]);
}, 10000);

\add_filter('posts_results', static function (array $posts, \WP_Query $query): array {
	if (!$query->is_main_query()) {
		return $posts;
	}
	[, $post_type] = cmx_save_perf_target(null, (string) $query->get('post_type'));
	if ($post_type === '') {
		return $posts;
	}
	cmx_save_perf_log('posts_results', null, $post_type, [
		'count' => \count($posts),
	]);
	return $posts;
}, 10000, 2);

\add_action('admin_head', static function (): void {
	cmx_save_perf_log('admin_head');
}, -10000);

\add_action('admin_footer', static function (): void {
	cmx_save_perf_log('admin_footer');
}, 10000);

foreach (['kontakte', 'artikel'] as $cmx_save_perf_post_type) {
	\add_action('manage_' . $cmx_save_perf_post_type . '_posts_custom_column', static function (string $column, int $post_id) use ($cmx_save_perf_post_type): void {
		cmx_save_perf_log('column:start', $post_id, $cmx_save_perf_post_type, [
			'column' => $column,
		]);
	}, -10000, 2);

	\add_action('manage_' . $cmx_save_perf_post_type . '_posts_custom_column', static function (string $column, int $post_id) use ($cmx_save_perf_post_type): void {
		cmx_save_perf_log('column:end', $post_id, $cmx_save_perf_post_type, [
			'column' => $column,
		]);
	}, 10000, 2);
}
unset($cmx_save_perf_post_type);

\add_filter('wp_insert_post_data', static function (array $data, array $postarr): array {
	$post_id = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
	$post_type = \sanitize_key((string) ($data['post_type'] ?? $postarr['post_type'] ?? ''));
	cmx_save_perf_log('wp_insert_post_data:start', $post_id, $post_type, [
		'status' => (string) ($data['post_status'] ?? ''),
		'title_len' => \strlen((string) ($data['post_title'] ?? '')),
	]);
	return $data;
}, -10000, 2);

\add_filter('wp_insert_post_data', static function (array $data, array $postarr): array {
	$post_id = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
	$post_type = \sanitize_key((string) ($data['post_type'] ?? $postarr['post_type'] ?? ''));
	cmx_save_perf_log('wp_insert_post_data:end', $post_id, $post_type, [
		'status' => (string) ($data['post_status'] ?? ''),
	]);
	return $data;
}, 10000, 2);

foreach (['kontakte', 'artikel'] as $cmx_save_perf_post_type) {
	\add_action('save_post_' . $cmx_save_perf_post_type, static function (int $post_id, \WP_Post $post): void {
		cmx_save_perf_log('save_post_' . $post->post_type . ':start', $post_id, (string) $post->post_type);
	}, -10000, 2);

	\add_action('save_post_' . $cmx_save_perf_post_type, static function (int $post_id, \WP_Post $post): void {
		cmx_save_perf_log('save_post_' . $post->post_type . ':end', $post_id, (string) $post->post_type);
	}, 10000, 2);
}
unset($cmx_save_perf_post_type);

\add_action('save_post', static function (int $post_id, \WP_Post $post): void {
	cmx_save_perf_log('save_post:generic_start', $post_id, (string) $post->post_type);
}, -10000, 2);

\add_action('save_post', static function (int $post_id, \WP_Post $post): void {
	cmx_save_perf_log('save_post:generic_end', $post_id, (string) $post->post_type);
}, 10000, 2);

\add_action('shutdown', static function (): void {
	if (!cmx_save_perf_is_request()) {
		return;
	}

	$post_keys = \is_array($_POST) ? \count($_POST) : 0;
	$file_keys = \is_array($_FILES) ? \count($_FILES) : 0;
	$content_length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;

	cmx_save_perf_log('shutdown', null, '', [
		'post_keys' => $post_keys,
		'file_keys' => $file_keys,
		'content_length_kb' => \round($content_length / 1024, 1),
	]);
}, 10000);
