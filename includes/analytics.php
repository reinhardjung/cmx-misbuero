<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_ANALYTICS_DB_VERSION = '1.0.0';
const CMX_ANALYTICS_COOKIE = 'cmx_analytics_sid';
const CMX_ANALYTICS_MAX_PAGEVIEW_SECONDS = 300;

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_table_name')) {
	function cmx_analytics_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'cmx_page_analytics';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_install')) {
	function cmx_analytics_install(): void {
		global $wpdb;

		$table = cmx_analytics_table_name();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id varchar(64) NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			path varchar(255) NOT NULL,
			title varchar(255) NOT NULL DEFAULT '',
			device varchar(20) NOT NULL DEFAULT 'desktop',
			started_at datetime NOT NULL,
			ended_at datetime DEFAULT NULL,
			last_ping_at datetime NOT NULL,
			duration_seconds int(10) unsigned NOT NULL DEFAULT 0,
			user_agent varchar(255) NOT NULL DEFAULT '',
			ip_hash char(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY started_at (started_at),
			KEY path (path(191)),
			KEY session_id (session_id),
			KEY user_id (user_id),
			KEY device (device)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		\dbDelta($sql);
		\update_option('cmx_analytics_db_version', CMX_ANALYTICS_DB_VERSION, false);
	}
}

\register_activation_hook(\dirname(__DIR__) . '/cmx-misbuero.php', __NAMESPACE__ . '\\cmx_analytics_install');

\add_action('admin_init', static function (): void {
	if ((string) \get_option('cmx_analytics_db_version', '') !== CMX_ANALYTICS_DB_VERSION) {
		cmx_analytics_install();
	}
}, 20);

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_request_path')) {
	function cmx_analytics_request_path(): string {
		$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
		$path = (string) \wp_parse_url($uri, PHP_URL_PATH);
		$path = '/' . \ltrim($path, '/');
		$query = (string) \wp_parse_url($uri, PHP_URL_QUERY);
		if ($query === '') {
			return \substr($path, 0, 255);
		}

		$allowed = ['page', 'tab', 'sub', 'post_type', 'post', 'taxonomy', 'action', 'filter_action'];
		$params = [];
		\parse_str($query, $params);
		$params = \is_array($params) ? \array_intersect_key($params, \array_flip($allowed)) : [];
		$params = \array_filter($params, static fn($value): bool => !\is_array($value) && (string) $value !== '');

		if ($params === []) {
			return \substr($path, 0, 255);
		}

		\ksort($params);
		return \substr($path . '?' . \http_build_query($params, '', '&', \PHP_QUERY_RFC3986), 0, 255);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_current_user_is_excluded')) {
	function cmx_analytics_current_user_is_excluded(): bool {
		$user = \wp_get_current_user();
		if (!$user instanceof \WP_User || !$user->exists()) {
			return true;
		}

		return \strtolower((string) $user->user_login) === 'cloudmeister';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_excluded_user_id')) {
	function cmx_analytics_excluded_user_id(): int {
		$user = \get_user_by('login', 'cloudmeister');
		return $user instanceof \WP_User ? (int) $user->ID : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_session_id')) {
	function cmx_analytics_session_id(): string {
		$current = isset($_COOKIE[CMX_ANALYTICS_COOKIE]) ? \sanitize_text_field((string) \wp_unslash($_COOKIE[CMX_ANALYTICS_COOKIE])) : '';
		if (\preg_match('/^[a-f0-9]{32}$/', $current)) {
			return $current;
		}

		$session_id = \function_exists('wp_generate_uuid4')
			? \str_replace('-', '', (string) \wp_generate_uuid4())
			: \bin2hex(\random_bytes(16));
		$secure = \is_ssl();
		$expires = \time() + (30 * DAY_IN_SECONDS);

		if (!headers_sent()) {
			\setcookie(CMX_ANALYTICS_COOKIE, $session_id, [
				'expires'  => $expires,
				'path'     => COOKIEPATH ?: '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => $secure,
				'httponly' => true,
				'samesite' => 'Lax',
			]);
		}
		$_COOKIE[CMX_ANALYTICS_COOKIE] = $session_id;
		return $session_id;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_device')) {
	function cmx_analytics_device(string $user_agent = ''): string {
		$user_agent = \strtolower($user_agent !== '' ? $user_agent : (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
		if ($user_agent === '') {
			return 'desktop';
		}
		if (\preg_match('/ipad|tablet|kindle|playbook|silk|android(?!.*mobile)/i', $user_agent)) {
			return 'tablet';
		}
		if (\preg_match('/mobi|iphone|ipod|android|blackberry|phone/i', $user_agent)) {
			return 'mobile';
		}
		return 'desktop';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_ip_hash')) {
	function cmx_analytics_ip_hash(): string {
		$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
		if ($ip === '') {
			return '';
		}
		return \hash_hmac('sha256', $ip, \wp_salt('auth'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_should_track')) {
	function cmx_analytics_should_track(): bool {
		if (!\is_admin() || \wp_doing_ajax() || \wp_doing_cron()) {
			return false;
		}
		if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
			return false;
		}
		if (cmx_analytics_current_user_is_excluded()) {
			return false;
		}
		$path = cmx_analytics_request_path();
		return !\str_contains($path, 'admin-ajax.php') && !\str_contains($path, 'async-upload.php');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_track_pageview')) {
	function cmx_analytics_track_pageview(): void {
		if (!cmx_analytics_should_track()) {
			return;
		}
		if ((string) \get_option('cmx_analytics_db_version', '') !== CMX_ANALYTICS_DB_VERSION) {
			cmx_analytics_install();
		}

		global $wpdb;
		$now = \current_time('mysql', true);
		$post_id = isset($_GET['post']) && !\is_array($_GET['post']) ? \max(0, (int) $_GET['post']) : 0;
		$title = '';
		global $title;
		if (isset($title) && (string) $title !== '') {
			$title = (string) $title;
		}
		if (\function_exists('get_current_screen')) {
			$screen = \get_current_screen();
			if ($screen && $title === '') {
				$title = (string) ($screen->id ?? '');
			}
		}
		if ($title === '' && $post_id > 0) {
			$title = (string) \get_the_title($post_id);
		}
		if ($title === '') {
			$title = cmx_analytics_request_path();
		}
		$user_agent = \substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

		$result = $wpdb->insert(cmx_analytics_table_name(), [
			'session_id'       => cmx_analytics_session_id(),
			'user_id'          => (int) \get_current_user_id(),
			'post_id'          => $post_id,
			'path'             => cmx_analytics_request_path(),
			'title'            => \substr($title, 0, 255),
			'device'           => cmx_analytics_device($user_agent),
			'started_at'       => $now,
			'ended_at'         => null,
			'last_ping_at'     => $now,
			'duration_seconds' => 0,
			'user_agent'       => $user_agent,
			'ip_hash'          => cmx_analytics_ip_hash(),
			'created_at'       => $now,
			'updated_at'       => $now,
		], ['%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']);

		if ($result) {
			$GLOBALS['cmx_analytics_view_id'] = (int) $wpdb->insert_id;
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_footer_script')) {
	function cmx_analytics_footer_script(): void {
		if (!isset($GLOBALS['cmx_analytics_view_id'])) {
			cmx_analytics_track_pageview();
		}

		$view_id = isset($GLOBALS['cmx_analytics_view_id']) ? (int) $GLOBALS['cmx_analytics_view_id'] : 0;
		if ($view_id <= 0) {
			return;
		}

		$config = [
			'ajaxUrl'     => \admin_url('admin-ajax.php'),
			'nonce'       => \wp_create_nonce('cmx_analytics_update'),
			'viewId'      => $view_id,
			'maxDuration' => CMX_ANALYTICS_MAX_PAGEVIEW_SECONDS,
		];
		?>
		<script>
		(function () {
			var config = <?php echo \wp_json_encode($config); ?>;
			if (!config || !config.viewId || !config.ajaxUrl) { return; }
			var started = Date.now();
			var lastSent = 0;
			var done = false;
			var maxDuration = Math.max(0, Number(config.maxDuration || 0));

			function seconds() {
				var elapsed = Math.max(0, Math.round((Date.now() - started) / 1000));
				return maxDuration > 0 ? Math.min(maxDuration, elapsed) : elapsed;
			}
			function body() {
				var params = new URLSearchParams();
				params.set('action', 'cmx_analytics_update');
				params.set('_ajax_nonce', config.nonce || '');
				params.set('view_id', String(config.viewId));
				params.set('duration', String(seconds()));
				return params;
			}
			function send(useBeacon) {
				var duration = seconds();
				if (duration === lastSent && !useBeacon) { return; }
				lastSent = duration;
				var payload = body();
				if (maxDuration > 0 && duration >= maxDuration) {
					done = true;
				}
				if (useBeacon && navigator.sendBeacon) {
					navigator.sendBeacon(config.ajaxUrl, payload);
					return;
				}
				fetch(config.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					keepalive: true,
					headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
					body: payload.toString()
				}).catch(function () {});
			}
			window.setInterval(function () { if (!done) { send(false); } }, 15000);
			document.addEventListener('visibilitychange', function () {
				if (document.visibilityState === 'hidden') { send(true); }
			});
			window.addEventListener('pagehide', function () {
				done = true;
				send(true);
			});
		}());
		</script>
		<?php
	}
}
\add_action('admin_footer', __NAMESPACE__ . '\\cmx_analytics_footer_script', 99);

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_update_handler')) {
	function cmx_analytics_update_handler(): void {
		$nonce = isset($_POST['_ajax_nonce']) ? (string) \wp_unslash($_POST['_ajax_nonce']) : '';
		if (!\wp_verify_nonce($nonce, 'cmx_analytics_update')) {
			\wp_send_json_error(['message' => 'bad_nonce'], 403);
		}

		$view_id = isset($_POST['view_id']) ? (int) $_POST['view_id'] : 0;
		$duration = isset($_POST['duration']) ? (int) $_POST['duration'] : 0;
		if ($view_id <= 0) {
			\wp_send_json_error(['message' => 'invalid_view'], 400);
		}

		$duration = \max(0, \min(CMX_ANALYTICS_MAX_PAGEVIEW_SECONDS, $duration));
		global $wpdb;
		$wpdb->update(cmx_analytics_table_name(), [
			'ended_at'         => \current_time('mysql', true),
			'last_ping_at'     => \current_time('mysql', true),
			'duration_seconds' => $duration,
			'updated_at'       => \current_time('mysql', true),
		], ['id' => $view_id], ['%s', '%s', '%d', '%s'], ['%d']);

		\wp_send_json_success(['duration' => $duration]);
	}
}
\add_action('wp_ajax_cmx_analytics_update', __NAMESPACE__ . '\\cmx_analytics_update_handler');

\add_action('admin_init', static function (): void {
	\add_settings_section(
		'cmx_sec_system_analytics',
		'',
		__NAMESPACE__ . '\\cmx_render_system_analytics_panel',
		'cmx_tab_system__analytics'
	);
}, 30);

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_seconds')) {
	function cmx_analytics_seconds(int $seconds): string {
		$seconds = \max(0, $seconds);
		$minutes = (int) \floor($seconds / 60);
		$remaining = $seconds % 60;
		return \sprintf('%02d:%02d Min.', $minutes, $remaining);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_percent')) {
	function cmx_analytics_percent(float $value): string {
		return \number_format_i18n($value, 1) . ' %';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_hours')) {
	function cmx_analytics_hours(int $seconds): string {
		return \number_format_i18n(\max(0, $seconds) / HOUR_IN_SECONDS, 1) . ' h';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_custom_post_type')) {
	function cmx_analytics_custom_post_type(string $post_type): string {
		$post_type = \sanitize_key($post_type);
		if ($post_type === '' || !\post_type_exists($post_type)) {
			return '';
		}

		$object = \get_post_type_object($post_type);
		if (!$object instanceof \WP_Post_Type || !empty($object->_builtin)) {
			return '';
		}

		return $post_type;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_module_from_row')) {
	function cmx_analytics_module_from_row(string $path, int $post_id = 0): string {
		if ($post_id > 0) {
			$post_type = (string) \get_post_type($post_id);
			$post_type = cmx_analytics_custom_post_type($post_type);
			if ($post_type !== '') {
				return $post_type;
			}
		}

		$query = (string) \wp_parse_url($path, \PHP_URL_QUERY);
		if ($query === '') {
			return '';
		}

		$params = [];
		\parse_str($query, $params);
		if (!\is_array($params) || empty($params['post_type']) || \is_array($params['post_type'])) {
			return '';
		}

		return cmx_analytics_custom_post_type((string) $params['post_type']);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_module_label')) {
	function cmx_analytics_module_label(string $post_type): string {
		$object = \get_post_type_object($post_type);
		if ($object instanceof \WP_Post_Type) {
			$label = (string) ($object->labels->name ?? $object->label ?? $post_type);
			if ($label !== '') {
				return $label;
			}
		}

		return $post_type;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_period_options')) {
	function cmx_analytics_period_options(): array {
		return [
			'hour' => [
				'label' => 'Stunde',
				'seconds' => HOUR_IN_SECONDS,
				'bucket' => 5 * MINUTE_IN_SECONDS,
				'date_format' => 'H:i',
			],
			'day' => [
				'label' => 'Tag',
				'seconds' => DAY_IN_SECONDS,
				'bucket' => HOUR_IN_SECONDS,
				'date_format' => 'H:i',
			],
			'week' => [
				'label' => 'Woche',
				'seconds' => 7 * DAY_IN_SECONDS,
				'bucket' => DAY_IN_SECONDS,
				'date_format' => 'd. M',
			],
			'month' => [
				'label' => 'Monat',
				'seconds' => 30 * DAY_IN_SECONDS,
				'bucket' => DAY_IN_SECONDS,
				'date_format' => 'd. M',
			],
			'quarter' => [
				'label' => 'Quartal',
				'seconds' => 90 * DAY_IN_SECONDS,
				'bucket' => 7 * DAY_IN_SECONDS,
				'date_format' => 'd. M',
			],
			'year' => [
				'label' => 'Jahr',
				'seconds' => 365 * DAY_IN_SECONDS,
				'bucket' => 30 * DAY_IN_SECONDS,
				'date_format' => 'M Y',
			],
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_selected_period')) {
	function cmx_analytics_selected_period(): string {
		$period = isset($_GET['period']) && !\is_array($_GET['period'])
			? \sanitize_key((string) \wp_unslash($_GET['period']))
			: 'month';
		return isset(cmx_analytics_period_options()[$period]) ? $period : 'month';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_period_bounds')) {
	function cmx_analytics_period_bounds(string $period = 'month', int $offset_periods = 0): array {
		$options = cmx_analytics_period_options();
		$config = $options[$period] ?? $options['month'];
		$seconds = \max(1, (int) ($config['seconds'] ?? (30 * DAY_IN_SECONDS)));
		$offset_periods = \max(0, $offset_periods);
		$site_now = \current_datetime();
		$end = $site_now->modify('-' . ($offset_periods * $seconds) . ' seconds');
		$start = $end->modify('-' . $seconds . ' seconds');

		return [
			$start->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
			$end->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
			(int) $start->format('U'),
			(int) $end->format('U'),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_analytics_collect_data')) {
	function cmx_analytics_collect_data(string $period = 'month'): array {
		global $wpdb;
		$table = cmx_analytics_table_name();
		$options = cmx_analytics_period_options();
		$period = isset($options[$period]) ? $period : 'month';
		$config = $options[$period];
		[$start, $end, $start_ts, $end_ts] = cmx_analytics_period_bounds($period);
		[$prev_start, $prev_end] = cmx_analytics_period_bounds($period, 1);
		$admin_path_like = '/wp-admin/%';
		$excluded_user_id = cmx_analytics_excluded_user_id();
		$bucket_seconds = \max(1, (int) ($config['bucket'] ?? DAY_IN_SECONDS));
		$date_format = (string) ($config['date_format'] ?? 'd. M');
		$duration_sql = 'LEAST(duration_seconds, ' . (int) CMX_ANALYTICS_MAX_PAGEVIEW_SECONDS . ')';

		$total_views = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE started_at BETWEEN %s AND %s AND path LIKE %s AND user_id != %d", $start, $end, $admin_path_like, $excluded_user_id));
		$total_users = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN CONCAT('u:', user_id) ELSE CONCAT('s:', session_id) END) FROM {$table} WHERE started_at BETWEEN %s AND %s AND path LIKE %s AND user_id != %d", $start, $end, $admin_path_like, $excluded_user_id));
		$prev_views = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE started_at BETWEEN %s AND %s AND path LIKE %s AND user_id != %d", $prev_start, $prev_end, $admin_path_like, $excluded_user_id));
		$total_duration = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM({$duration_sql}), 0) FROM {$table} WHERE started_at BETWEEN %s AND %s AND path LIKE %s AND user_id != %d", $start, $end, $admin_path_like, $excluded_user_id));
		$prev_total_duration = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM({$duration_sql}), 0) FROM {$table} WHERE started_at BETWEEN %s AND %s AND path LIKE %s AND user_id != %d", $prev_start, $prev_end, $admin_path_like, $excluded_user_id));
		$total_short_views = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(CASE WHEN {$duration_sql} < 10 THEN 1 ELSE 0 END), 0) FROM {$table} WHERE started_at BETWEEN %s AND %s AND path LIKE %s AND user_id != %d", $start, $end, $admin_path_like, $excluded_user_id));

		$chart_rows = (array) $wpdb->get_results($wpdb->prepare("
			SELECT started_at, {$duration_sql} duration_seconds
			FROM {$table}
			WHERE started_at BETWEEN %s AND %s AND path LIKE %s AND user_id != %d
			ORDER BY started_at ASC
		", $start, $end, $admin_path_like, $excluded_user_id), ARRAY_A);

		$daily = [];
		foreach ($chart_rows as $row) {
			$started_at = (string) ($row['started_at'] ?? '');
			$row_ts = $started_at !== '' ? \strtotime($started_at . ' UTC') : false;
			if (!$row_ts) {
				continue;
			}
			$key = (string) ((int) \floor($row_ts / $bucket_seconds) * $bucket_seconds);
			if (!isset($daily[$key])) {
				$daily[$key] = [
					'views' => 0,
					'durationSum' => 0,
				];
			}
			$daily[$key]['views']++;
			$duration = \max(0, (int) ($row['duration_seconds'] ?? 0));
			$daily[$key]['durationSum'] += $duration;
		}

		$labels = [];
		$views = [];
		$durations = [];
		$bucket_start = (int) (\floor($start_ts / $bucket_seconds) * $bucket_seconds);
		$bucket_end = (int) (\floor($end_ts / $bucket_seconds) * $bucket_seconds);
		for ($bucket = $bucket_start; $bucket <= $bucket_end; $bucket += $bucket_seconds) {
			$key = (string) $bucket;
			$labels[] = \wp_date($date_format, $bucket);
			$views[] = (int) ($daily[$key]['views'] ?? 0);
			$durations[] = (int) ($daily[$key]['durationSum'] ?? 0);
		}

		$module_source_rows = (array) $wpdb->get_results($wpdb->prepare("
			SELECT path, post_id, {$duration_sql} duration_seconds
			FROM {$table}
			WHERE started_at BETWEEN %s AND %s AND path LIKE %s AND user_id != %d
		", $start, $end, $admin_path_like, $excluded_user_id), ARRAY_A);
		$module_map = [];
		foreach ($module_source_rows as $row) {
			$post_type = cmx_analytics_module_from_row((string) ($row['path'] ?? ''), (int) ($row['post_id'] ?? 0));
			if ($post_type === '') {
				continue;
			}
			if (!isset($module_map[$post_type])) {
				$module_map[$post_type] = [
					'key' => $post_type,
					'label' => cmx_analytics_module_label($post_type),
					'duration' => 0,
					'views' => 0,
				];
			}
			$module_map[$post_type]['duration'] += \max(0, (int) ($row['duration_seconds'] ?? 0));
			$module_map[$post_type]['views']++;
		}
		$module_rows = \array_values($module_map);
		\usort($module_rows, static function (array $left, array $right): int {
			$duration_order = ((int) ($right['duration'] ?? 0)) <=> ((int) ($left['duration'] ?? 0));
			if ($duration_order !== 0) {
				return $duration_order;
			}
			return \strnatcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
		});

		$page_rows = (array) $wpdb->get_results($wpdb->prepare("
			SELECT
				path,
				MAX(title) title,
				COUNT(*) views,
				COUNT(DISTINCT CASE WHEN user_id > 0 THEN CONCAT('u:', user_id) ELSE CONCAT('s:', session_id) END) users,
				COALESCE(AVG(NULLIF({$duration_sql}, 0)), 0) avg_duration,
				SUM(CASE WHEN {$duration_sql} < 10 THEN 1 ELSE 0 END) short_views
			FROM {$table}
			WHERE started_at BETWEEN %s AND %s AND path LIKE %s AND user_id != %d
			GROUP BY path
			ORDER BY views DESC
			LIMIT 25
		", $start, $end, $admin_path_like, $excluded_user_id), ARRAY_A);

		return [
			'period' => $period,
			'periodLabel' => (string) ($config['label'] ?? 'Monat'),
			'start' => $start,
			'end' => $end,
			'totalViews' => $total_views,
			'totalUsers' => $total_users,
			'totalDuration' => $total_duration,
			'totalShortViews' => $total_short_views,
			'viewsChange' => $prev_views > 0 ? (($total_views - $prev_views) / $prev_views) * 100 : ($total_views > 0 ? 100 : 0),
			'durationChange' => $prev_total_duration > 0 ? (($total_duration - $prev_total_duration) / $prev_total_duration) * 100 : ($total_duration > 0 ? 100 : 0),
			'chart' => [
				'labels' => $labels,
				'views' => $views,
				'durations' => $durations,
			],
			'modules' => $module_rows,
			'pages' => $page_rows,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_system_analytics_panel')) {
	function cmx_render_system_analytics_panel(): void {
		cmx_system_storage_enqueue_chartjs();
		$period = cmx_analytics_selected_period();
		$period_options = cmx_analytics_period_options();
		$data = cmx_analytics_collect_data($period);
		$total_views = \max(0, (int) ($data['totalViews'] ?? 0));
		$total_users = \max(0, (int) ($data['totalUsers'] ?? 0));
		$total_duration = \max(0, (int) ($data['totalDuration'] ?? 0));
		$total_short_views = \max(0, (int) ($data['totalShortViews'] ?? 0));
		$total_avg_duration = $total_views > 0 ? (int) \round($total_duration / $total_views) : 0;
		$total_bounce = $total_views > 0 ? ($total_short_views / $total_views) * 100 : 0;
		$comparison_label = 'im Vergleich zum vorherigen Zeitraum';
		$module_colors = ['#2563eb', '#14b8a6', '#f97316', '#8b5cf6', '#ef4444', '#22c55e', '#0ea5e9', '#eab308', '#64748b', '#db2777'];
		$module_total = 0;
		foreach ((array) ($data['modules'] ?? []) as $row) {
			$module_total += (int) ($row['duration'] ?? 0);
		}

		$payload = [
			'labels' => $data['chart']['labels'] ?? [],
			'views' => $data['chart']['views'] ?? [],
			'durations' => $data['chart']['durations'] ?? [],
			'modules' => [
				'labels' => [],
				'values' => [],
				'colors' => [],
			],
		];
		foreach ((array) ($data['modules'] ?? []) as $index => $row) {
			$payload['modules']['labels'][] = (string) ($row['label'] ?? $row['key'] ?? '');
			$payload['modules']['values'][] = (int) ($row['duration'] ?? 0);
			$payload['modules']['colors'][] = $module_colors[$index % \count($module_colors)];
		}

		echo '<div class="cmx-analytics"><div class="cmx-analytics-layout"><div class="cmx-analytics-content">';
		echo '<div class="cmx-analytics-chart-card"><div class="cmx-analytics-chart-head"><h3>Seitenaufrufe im Zeitverlauf</h3><select id="cmx-analytics-metric"><option value="views">Seitenaufrufe</option><option value="durations">Nutzungsdauer</option></select></div><div class="cmx-analytics-chart-wrap"><canvas id="cmx-analytics-line-chart"></canvas></div></div>';
		echo '<div class="cmx-analytics-table-card"><h3>Seiten-Nutzung</h3><div class="cmx-analytics-table-wrap"><table><thead><tr><th>Seite</th><th>Seitenaufrufe</th><th>Einzelne Nutzer</th><th>Ø Sitzungsdauer</th><th>Absprungrate</th></tr></thead><tbody>';
		if (empty($data['pages'])) {
			echo '<tr><td colspan="5">Noch keine Nutzungsdaten vorhanden.</td></tr>';
		} else {
			foreach ((array) $data['pages'] as $row) {
				$views = \max(0, (int) ($row['views'] ?? 0));
				$users = \max(0, (int) ($row['users'] ?? 0));
				$views_percent = $total_views > 0 ? ($views / $total_views) * 100 : 0;
				$users_percent = $total_views > 0 ? ($users / $total_views) * 100 : 0;
				$bounce = $views > 0 ? (((int) ($row['short_views'] ?? 0) / $views) * 100) : 0;
				$path = (string) ($row['path'] ?? '/');
				$url = \str_starts_with($path, '/wp-admin/')
					? \admin_url(\ltrim(\substr($path, \strlen('/wp-admin/')), '/'))
					: \home_url($path);
				echo '<tr>';
				echo '<td><a class="cmx-analytics-page-link" href="' . \esc_url($url) . '" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-media-document"></span>' . \esc_html($path) . '</a></td>';
				echo '<td><strong>' . \esc_html(\number_format_i18n($views)) . '</strong> <small>(' . \esc_html(cmx_analytics_percent($views_percent)) . ')</small><i><b style="width:' . \esc_attr((string) \min(100, $views_percent * 3)) . '%"></b></i></td>';
				echo '<td><strong>' . \esc_html(\number_format_i18n($users)) . '</strong> <small>(' . \esc_html(cmx_analytics_percent($users_percent)) . ')</small><i><b style="width:' . \esc_attr((string) \min(100, $users_percent * 3)) . '%"></b></i></td>';
				echo '<td>' . \esc_html(cmx_analytics_seconds((int) ($row['avg_duration'] ?? 0))) . '</td>';
				echo '<td>' . \esc_html(cmx_analytics_percent($bounce)) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody><tfoot><tr>';
		echo '<td>Gesamt</td>';
		echo '<td><strong>' . \esc_html(\number_format_i18n($total_views)) . '</strong></td>';
		echo '<td><strong>' . \esc_html(\number_format_i18n($total_users)) . '</strong></td>';
		echo '<td>' . \esc_html(cmx_analytics_seconds($total_avg_duration)) . '</td>';
		echo '<td>' . \esc_html(cmx_analytics_percent($total_bounce)) . '</td>';
		echo '</tr></tfoot></table></div></div></div>';
		echo '<aside class="cmx-analytics-sidebar">';
		echo '<div class="cmx-analytics-card cmx-analytics-card-standalone"><span>Gesamte Nutzungsdauer</span><strong>' . \esc_html(cmx_analytics_seconds($total_duration)) . '</strong><small class="' . ((float) $data['durationChange'] >= 0 ? 'up' : 'down') . '">' . \esc_html(cmx_analytics_percent((float) $data['durationChange'])) . '</small><p>' . \esc_html($comparison_label) . '</p></div>';
		echo '<div class="cmx-analytics-main cmx-analytics-main-sidebar"><div class="cmx-analytics-kpis"><div class="cmx-analytics-card cmx-analytics-card-plain"><span>Gesamte Seitenaufrufe</span><strong>' . \esc_html(\number_format_i18n($total_views)) . '</strong><small class="' . ((float) $data['viewsChange'] >= 0 ? 'up' : 'down') . '">' . \esc_html(cmx_analytics_percent((float) $data['viewsChange'])) . '</small><p>' . \esc_html($comparison_label) . '</p></div></div></div>';
		echo '<div class="cmx-analytics-device cmx-analytics-device-sidebar"><h3>Nutzung nach Modul</h3><div class="cmx-analytics-device-grid"><canvas id="cmx-analytics-device-chart" width="190" height="190"></canvas><div class="cmx-analytics-device-legend">';
		if ($module_total <= 0) {
			echo '<p>Noch keine Modulzeiten.</p>';
		} else {
			foreach ((array) ($data['modules'] ?? []) as $index => $row) {
				$value = (int) ($row['duration'] ?? 0);
				if ($value <= 0) {
					continue;
				}
				$percent = $module_total > 0 ? ($value / $module_total) * 100 : 0;
				echo '<div><span style="background:' . \esc_attr($module_colors[$index % \count($module_colors)]) . '"></span><em>' . \esc_html((string) ($row['label'] ?? $row['key'] ?? '')) . '</em><b>' . \esc_html(cmx_analytics_hours($value)) . '</b><strong>' . \esc_html(cmx_analytics_percent($percent)) . '</strong></div>';
			}
		}
		echo '</div></div><label class="cmx-analytics-period-filter" for="cmx-analytics-period"><span>Im ausgewählten Zeitraum</span><select id="cmx-analytics-period" name="period">';
		foreach ($period_options as $key => $option) {
			echo '<option value="' . \esc_attr((string) $key) . '"' . \selected($period, (string) $key, false) . '>' . \esc_html((string) ($option['label'] ?? $key)) . '</option>';
		}
		echo '</select></label></div>';
		echo '</aside></div></div>';
		?>
		<style>
			.cmx-analytics{--cmx-analytics-gap:24px;max-width:1420px;color:#111827;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
			.cmx-analytics *{box-sizing:border-box}
			.cmx-analytics-layout{display:grid;grid-template-columns:minmax(760px,1fr) 360px;gap:var(--cmx-analytics-gap);align-items:start}
			.cmx-analytics-content{display:grid;gap:var(--cmx-analytics-gap);padding:0;background:transparent}
			.cmx-analytics-sidebar{display:grid;gap:var(--cmx-analytics-gap);align-items:start}
			.cmx-analytics-sidebar>*{width:100%;margin:0}
			.cmx-analytics-main,.cmx-analytics-device,.cmx-analytics-chart-card,.cmx-analytics-table-card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 8px 24px rgba(15,23,42,.04)}
			.cmx-analytics-main{padding:28px}
			.cmx-analytics-main-sidebar{padding:24px}
			.cmx-analytics-title{display:flex;align-items:center;gap:8px}
			.cmx-analytics-title h2{margin:0;font-size:27px;line-height:1.15}
			.cmx-analytics-title .dashicons{width:18px;height:18px;font-size:18px;color:#94a3b8}
			.cmx-analytics-main>p{margin:10px 0 24px;color:#475569;font-size:15px}
			.cmx-analytics-kpis{display:grid;grid-template-columns:1fr;gap:22px}
			.cmx-analytics-card{min-height:132px;padding:24px;border:1px solid #e5e7eb;border-radius:8px}
			.cmx-analytics-card-standalone{background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.04)}
			.cmx-analytics-card-plain{border:0;padding:0;min-height:0}
			.cmx-analytics-card span{display:block;margin-bottom:18px;color:#374151;font-weight:700}
			.cmx-analytics-card strong{font-size:30px;line-height:1;color:#111827}
			.cmx-analytics-card small{margin-left:10px;font-weight:700}
			.cmx-analytics-card small.up{color:#16a34a}.cmx-analytics-card small.down{color:#dc2626}
			.cmx-analytics-card p{margin:16px 0 0;color:#64748b;font-size:13px}
			.cmx-analytics-device{padding:24px}
			.cmx-analytics-device-sidebar{padding:24px}
			.cmx-analytics-device h3,.cmx-analytics-chart-card h3,.cmx-analytics-table-card h3{margin:0;font-size:16px}
			.cmx-analytics-device-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:14px;align-items:center;margin:24px 0}
			.cmx-analytics-device-grid canvas{display:block;margin:0 auto;max-width:100%}
			.cmx-analytics-device-legend{display:grid;gap:12px}
			.cmx-analytics-device-legend div{display:grid;grid-template-columns:10px minmax(0,1fr) minmax(62px,max-content) minmax(70px,max-content);column-gap:10px;row-gap:4px;align-items:center;font-size:13px}
			.cmx-analytics-device-legend span{width:8px;height:8px;border-radius:50%}
			.cmx-analytics-device-legend em{font-style:normal;color:#334155;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.cmx-analytics-device-legend b,.cmx-analytics-device-legend strong{justify-self:stretch;width:100%;font-size:13px;text-align:right;white-space:nowrap}.cmx-analytics-device-legend b{color:#475569;font-weight:700}.cmx-analytics-device-legend strong{color:#111827;padding-left:20px}
			.cmx-analytics-device>p{margin:0;color:#64748b;font-size:12px}
			.cmx-analytics-period-filter{display:grid;gap:8px;margin:0;color:#64748b;font-size:12px}
			.cmx-analytics-period-filter select{width:100%;min-height:36px;border-color:#d1d5db;border-radius:6px;color:#111827;font-size:13px}
			.cmx-analytics-chart-card,.cmx-analytics-table-card{padding:24px}
			.cmx-analytics-chart-head{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:20px}
			.cmx-analytics-chart-head select{min-width:190px}
			.cmx-analytics-chart-wrap{height:310px}
			.cmx-analytics-table-wrap{max-height:430px;margin-top:18px;overflow:auto;border:1px solid #e5e7eb;border-radius:8px}
			.cmx-analytics table{width:100%;border-collapse:collapse;min-width:820px;background:#fff}
			.cmx-analytics th,.cmx-analytics td{padding:13px 14px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:middle}
			.cmx-analytics th{position:sticky;top:0;z-index:1;background:#f8fafc;color:#334155;font-size:13px}
			.cmx-analytics td{font-size:13px;color:#111827}
			.cmx-analytics tbody tr{transition:background-color .16s ease,box-shadow .16s ease}
			.cmx-analytics tbody tr:hover td{background:#f8fbff}
			.cmx-analytics tbody tr:hover .cmx-analytics-page-link{color:#2563eb}
			.cmx-analytics tbody tr:hover td:first-child{box-shadow:inset 3px 0 0 #2563eb}
			.cmx-analytics tr:last-child td{border-bottom:0}
			.cmx-analytics tfoot td{position:sticky;bottom:0;background:#f8fafc;border-top:1px solid #d8dee7;border-bottom:0;font-weight:700}
			.cmx-analytics td:first-child{font-weight:600;color:#334155}
			.cmx-analytics-page-link{display:inline-flex;align-items:flex-start;gap:8px;color:#334155;text-decoration:none}
			.cmx-analytics-page-link:hover,.cmx-analytics-page-link:focus{color:#2563eb;text-decoration:underline}
			.cmx-analytics td .dashicons{width:16px;height:16px;margin-right:8px;font-size:16px;color:#64748b;vertical-align:middle}
			.cmx-analytics-page-link .dashicons{flex:0 0 16px;margin:1px 0 0}
			.cmx-analytics td small{color:#64748b}
			.cmx-analytics td i{display:inline-block;width:76px;height:5px;margin-left:10px;border-radius:999px;background:#e5e7eb;vertical-align:middle;overflow:hidden}
			.cmx-analytics td i b{display:block;height:100%;border-radius:999px;background:#2563eb}
			@media (max-width: 1180px){.cmx-analytics-layout{grid-template-columns:1fr}.cmx-analytics-sidebar{grid-template-columns:repeat(3,minmax(0,1fr))}}
			@media (max-width: 980px){.cmx-analytics-content{padding:0;background:transparent}.cmx-analytics-sidebar{grid-template-columns:1fr}.cmx-analytics-chart-head{align-items:flex-start;flex-direction:column}}
		</style>
		<?php
		\wp_add_inline_script('cmx-chartjs', 'document.addEventListener("DOMContentLoaded", function () {
			var data = ' . \wp_json_encode($payload) . ';
			if (!data || typeof Chart === "undefined") { return; }
			var lineCanvas = document.getElementById("cmx-analytics-line-chart");
			var metric = document.getElementById("cmx-analytics-metric");
			var period = document.getElementById("cmx-analytics-period");
			var lineChart = null;
			function metricLabel(key) { return key === "durations" ? "Nutzungsdauer" : "Seitenaufrufe"; }
			function metricValues(key) { return Array.isArray(data[key]) ? data[key] : []; }
			function drawLine(key) {
				if (!lineCanvas) { return; }
				if (lineChart) { lineChart.destroy(); }
				lineChart = new Chart(lineCanvas, {
					type: "line",
					data: { labels: data.labels || [], datasets: [{ label: metricLabel(key), data: metricValues(key), borderColor: "#2563eb", backgroundColor: "rgba(37,99,235,.10)", fill: true, tension: .32, pointRadius: 0, borderWidth: 3 }] },
					options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { var value = Number(ctx.raw || 0); return key === "durations" ? formatDuration(value) : value.toLocaleString(document.documentElement.lang || "de-CH"); } } } }, scales: { x: { grid: { display: false }, ticks: { color: "#64748b", maxRotation: 0, autoSkip: true, maxTicksLimit: 7 } }, y: { beginAtZero: true, grid: { color: "#e5e7eb" }, ticks: { color: "#64748b", callback: function (value) { return key === "durations" ? formatDuration(Number(value || 0)).replace(" Min.", "") : Number(value || 0).toLocaleString(document.documentElement.lang || "de-CH"); } } } } }
				});
			}
			var deviceCanvas = document.getElementById("cmx-analytics-device-chart");
			if (deviceCanvas && data.modules && Array.isArray(data.modules.values) && data.modules.values.length) {
				new Chart(deviceCanvas, {
					type: "doughnut",
					data: { labels: data.modules.labels || [], datasets: [{ data: data.modules.values || [], backgroundColor: data.modules.colors || [], borderWidth: 0 }] },
					options: { responsive: false, cutout: "68%", plugins: { legend: { display: false } } }
				});
			}
			if (metric) { metric.addEventListener("change", function () { drawLine(String(metric.value || "views")); }); }
			if (period) {
				period.addEventListener("change", function () {
					var url = new URL(window.location.href);
					url.searchParams.set("period", String(period.value || "month"));
					window.location.href = url.toString();
				});
			}
			drawLine(metric ? String(metric.value || "views") : "views");
			function formatDuration(seconds) {
				seconds = Math.max(0, Math.round(seconds || 0));
				var minutes = Math.floor(seconds / 60);
				var rest = seconds % 60;
				return String(minutes).padStart(2, "0") + ":" + String(rest).padStart(2, "0") + " Min.";
			}
		});', 'after');
	}
}
