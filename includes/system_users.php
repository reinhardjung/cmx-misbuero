<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_SYSTEM_ASSIGNED_USER_META')) {
	\define(__NAMESPACE__ . '\\CMX_SYSTEM_ASSIGNED_USER_META', '_cmx_assigned_wp_user');
}

if (!\defined(__NAMESPACE__ . '\\CMX_SYSTEM_MAX_WORKPLACES_KEY')) {
	\define(__NAMESPACE__ . '\\CMX_SYSTEM_MAX_WORKPLACES_KEY', 'max_workplaces');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_settings_option_name')) {
	function cmx_system_settings_option_name(): string {
		return \defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			: 'cmx_einstellungen';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_is_cloudmeister_user')) {
	function cmx_system_is_cloudmeister_user(): bool {
		$user = \wp_get_current_user();
		return ($user instanceof \WP_User) && $user->exists() && \strtolower((string) $user->user_login) === 'cloudmeister';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_plugin_post_types')) {
	function cmx_system_plugin_post_types(): array {
		$post_types = [];
		foreach (['artikel', 'kontakte', 'projekte', 'belege', 'dokumente', 'scanner', 'postfach'] as $post_type) {
			if (\post_type_exists($post_type)) {
				$post_types[] = $post_type;
			}
		}
		return $post_types;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_cloudmeister_hidden_post_types')) {
	function cmx_system_cloudmeister_hidden_post_types(): array {
		$post_types = [];
		foreach (['kontakte', 'artikel', 'belege', 'dokumente', 'projekte', 'emails'] as $post_type) {
			if (\post_type_exists($post_type)) {
				$post_types[] = $post_type;
			}
		}
		return $post_types;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_should_hide_post_type_for_cloudmeister')) {
	function cmx_system_should_hide_post_type_for_cloudmeister(string $post_type): bool {
		if (!cmx_system_is_cloudmeister_user()) {
			return false;
		}

		return \in_array($post_type, cmx_system_cloudmeister_hidden_post_types(), true);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_total_user_count')) {
	function cmx_system_total_user_count(): int {
		$counts = \count_users();
		return (int) ($counts['total_users'] ?? 0);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_assignable_users')) {
	function cmx_system_assignable_users(): array {
		$users = \get_users([
			'orderby' => 'display_name',
			'order'   => 'ASC',
		]);

		$assignable = [];
		foreach ((array) $users as $user) {
			if (!$user instanceof \WP_User || !$user->exists()) {
				continue;
			}
			if (\strtolower((string) $user->user_login) === 'cloudmeister') {
				continue;
			}
			$assignable[] = $user;
		}

		return $assignable;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_assignable_user_count')) {
	function cmx_system_assignable_user_count(): int {
		return \count(cmx_system_assignable_users());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_max_workplaces')) {
	function cmx_system_max_workplaces(): int {
		$options = (array) \get_option(cmx_system_settings_option_name(), []);
		$value = isset($options[CMX_SYSTEM_MAX_WORKPLACES_KEY]) ? (int) $options[CMX_SYSTEM_MAX_WORKPLACES_KEY] : 1;
		return $value > 0 ? $value : 1;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_should_show_user_assignment')) {
	function cmx_system_should_show_user_assignment(): bool {
		return cmx_system_max_workplaces() > 2;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_is_assignable_user_id')) {
	function cmx_system_is_assignable_user_id(int $user_id): bool {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return false;
		}
		$user = \get_user_by('id', $user_id);
		return ($user instanceof \WP_User) && $user->exists() && \strtolower((string) $user->user_login) !== 'cloudmeister';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_assigned_user_label')) {
	function cmx_system_assigned_user_label(int $post_id): string {
		$user_id = (int) \get_post_meta($post_id, CMX_SYSTEM_ASSIGNED_USER_META, true);
		if (!cmx_system_is_assignable_user_id($user_id)) {
			return '';
		}
		$user = \get_user_by('id', $user_id);
		if (!$user instanceof \WP_User) {
			return '';
		}
		$label = \trim((string) $user->display_name);
		if ($label === '') {
			$label = \trim((string) $user->user_login);
		}
		return $label;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_render_assigned_user_metabox')) {
	function cmx_system_render_assigned_user_metabox(\WP_Post $post): void {
		\wp_nonce_field('cmx_system_assigned_user_save', 'cmx_system_assigned_user_nonce');
		$current_user_id = (int) \get_post_meta($post->ID, CMX_SYSTEM_ASSIGNED_USER_META, true);
		$users = cmx_system_assignable_users();

		echo '<select name="cmx_system_assigned_user" id="cmx_system_assigned_user" style="width:100%;" aria-label="WP-User">';
		echo '<option value="">— auswählen —</option>';
		foreach ($users as $user) {
			$first_name = \trim((string) \get_user_meta((int) $user->ID, 'first_name', true));
			$last_name = \trim((string) \get_user_meta((int) $user->ID, 'last_name', true));
			$label = \trim(\preg_replace('/\s+/', ' ', $first_name . ' ' . $last_name));
			if ($label === '') {
				$label = \trim((string) $user->user_login);
			}
			echo '<option value="' . \esc_attr((string) $user->ID) . '"' . \selected($current_user_id, (int) $user->ID, false) . '>' . \esc_html($label) . '</option>';
		}
		echo '</select>';
	}
}

\add_action('add_meta_boxes', function (string $post_type, \WP_Post $post): void {
	if (!cmx_system_should_show_user_assignment()) {
		return;
	}
	if (!\in_array($post_type, cmx_system_plugin_post_types(), true)) {
		return;
	}

	\add_meta_box(
		'cmx_system_assigned_user',
		'Zuständig ist',
		__NAMESPACE__ . '\\cmx_system_render_assigned_user_metabox',
		$post_type,
		'side',
		'default'
	);
}, 20, 2);

\add_action('save_post', function (int $post_id, \WP_Post $post, bool $update): void {
	if (!\in_array((string) $post->post_type, cmx_system_plugin_post_types(), true)) {
		return;
	}
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) {
		return;
	}
	if (!isset($_POST['cmx_system_assigned_user_nonce']) || !\wp_verify_nonce((string) $_POST['cmx_system_assigned_user_nonce'], 'cmx_system_assigned_user_save')) {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

	$user_id = isset($_POST['cmx_system_assigned_user'])
		? (int) \wp_unslash($_POST['cmx_system_assigned_user'])
		: 0;

	if (!cmx_system_is_assignable_user_id($user_id)) {
		\delete_post_meta($post_id, CMX_SYSTEM_ASSIGNED_USER_META);
		return;
	}

	\update_post_meta($post_id, CMX_SYSTEM_ASSIGNED_USER_META, $user_id);
}, 20, 3);

\add_action('init', function (): void {
	$column_key = 'cmx_system_assigned_user';
	foreach (cmx_system_plugin_post_types() as $post_type) {
		$add_column = static function (array $columns) use ($column_key): array {
			if (!cmx_system_should_show_user_assignment()) {
				return $columns;
			}
			unset($columns[$column_key]);
			$columns[$column_key] = 'WP-User';
			return $columns;
		};

		\add_filter('manage_edit-' . $post_type . '_columns', $add_column, 9999);
		\add_filter('manage_' . $post_type . '_posts_columns', $add_column, 9999);

		\add_action('manage_' . $post_type . '_posts_custom_column', static function (string $column, int $post_id) use ($column_key): void {
			if ($column !== $column_key || !cmx_system_should_show_user_assignment()) {
				return;
			}
			echo \esc_html(cmx_system_assigned_user_label($post_id));
		}, 9999, 2);
	}
}, 25);

\add_action('admin_head-edit.php', function (): void {
	$post_type = isset($_GET['post_type']) ? \sanitize_key((string) \wp_unslash($_GET['post_type'])) : '';
	if ($post_type === '' && \function_exists('get_current_screen')) {
		$screen = \get_current_screen();
		$post_type = $screen ? \sanitize_key((string) ($screen->post_type ?? '')) : '';
	}
	if ($post_type === '' || !\in_array($post_type, cmx_system_plugin_post_types(), true) || !cmx_system_should_show_user_assignment()) {
		return;
	}

	echo '<style>
		.wp-list-table th.column-cmx_system_assigned_user{width:150px}
		.wp-list-table td.column-cmx_system_assigned_user{white-space:nowrap}
	</style>';
});

\add_action('all_admin_notices', function (): void {
	if (!\is_admin()) {
		return;
	}
	if (cmx_system_assignable_user_count() <= cmx_system_max_workplaces()) {
		return;
	}

	echo '<div class="notice notice-error"><p><strong>Systemfehler: Bitte kaufen Sie eine weitere Arbeitsplatzlizenz.</strong></p></div>';
});

\add_action('admin_menu', function (): void {
	if (!cmx_system_is_cloudmeister_user()) {
		return;
	}

	foreach (cmx_system_cloudmeister_hidden_post_types() as $post_type) {
		if (cmx_system_should_hide_post_type_for_cloudmeister($post_type)) {
			\remove_menu_page('edit.php?post_type=' . $post_type);
		}
	}
}, 999);

\add_action('admin_bar_menu', function (\WP_Admin_Bar $wp_admin_bar): void {
	if (!cmx_system_is_cloudmeister_user()) {
		return;
	}

	foreach (cmx_system_cloudmeister_hidden_post_types() as $post_type) {
		if (cmx_system_should_hide_post_type_for_cloudmeister($post_type)) {
			$wp_admin_bar->remove_node('new-' . $post_type);
		}
	}
}, 999);

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_session_prepare')) {
	function cmx_system_session_prepare($session): array {
		if (\is_int($session)) {
			return ['expiration' => $session];
		}
		return \is_array($session) ? $session : [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_cleanup_duplicate_sessions_for_user')) {
	function cmx_system_cleanup_duplicate_sessions_for_user(int $user_id): array {
		$user_id = (int) $user_id;
		$result = [
			'cleaned'         => false,
			'current_invalid' => false,
		];
		if ($user_id <= 0) {
			return $result;
		}

		$sessions = \get_user_meta($user_id, 'session_tokens', true);
		if (!\is_array($sessions) || \count($sessions) <= 1) {
			return $result;
		}

		$now = \time();
		$prepared = [];
		foreach ($sessions as $verifier => $session) {
			$session = cmx_system_session_prepare($session);
			$expiration = (int) ($session['expiration'] ?? 0);
			if ($expiration < $now) {
				continue;
			}
			$prepared[(string) $verifier] = $session;
		}
		if (\count($prepared) <= 1) {
			if ($prepared !== $sessions) {
				if (!empty($prepared)) {
					\update_user_meta($user_id, 'session_tokens', $prepared);
				} else {
					\delete_user_meta($user_id, 'session_tokens');
				}
			}
			return $result;
		}

		\uasort($prepared, static function (array $a, array $b): int {
			$a_login = (int) ($a['login'] ?? 0);
			$b_login = (int) ($b['login'] ?? 0);
			if ($a_login !== $b_login) {
				return $b_login <=> $a_login;
			}
			$a_expiration = (int) ($a['expiration'] ?? 0);
			$b_expiration = (int) ($b['expiration'] ?? 0);
			return $b_expiration <=> $a_expiration;
		});

		$latest_verifier = (string) \array_key_first($prepared);
		$latest_session = $prepared[$latest_verifier] ?? [];
		$latest_login = (int) ($latest_session['login'] ?? 0);
		if ($latest_login <= 0) {
			$latest_login = (int) ($latest_session['expiration'] ?? 0) - (int) \DAY_IN_SECONDS;
		}
		if ($latest_login <= 0 || ($latest_login + (int) \DAY_IN_SECONDS) > $now) {
			return $result;
		}

		$kept = [$latest_verifier => $latest_session];
		\update_user_meta($user_id, 'session_tokens', $kept);
		$result['cleaned'] = true;

		$current_token = \function_exists('wp_get_session_token') ? (string) \wp_get_session_token() : '';
		if ($current_token !== '' && \hash('sha256', $current_token) !== $latest_verifier) {
			$result['current_invalid'] = ((int) \get_current_user_id() === $user_id);
		}

		return $result;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_session_user_ids')) {
	function cmx_system_session_user_ids(): array {
		$ids = \get_users([
			'fields'     => 'ids',
			'meta_query' => [[
				'key'     => 'session_tokens',
				'compare' => 'EXISTS',
			]],
		]);
		return \array_values(\array_unique(\array_map('intval', (array) $ids)));
	}
}

\add_action('init', function (): void {
	if (\defined('DOING_AJAX') && DOING_AJAX) {
		return;
	}
	if (\defined('REST_REQUEST') && REST_REQUEST) {
		return;
	}
	if (\defined('DOING_CRON') && DOING_CRON) {
		return;
	}

	$current_user_id = (int) \get_current_user_id();
	if ($current_user_id > 0) {
		$current_result = cmx_system_cleanup_duplicate_sessions_for_user($current_user_id);
		if (!empty($current_result['current_invalid'])) {
			\wp_logout();
			if (!\headers_sent()) {
				$target = \function_exists(__NAMESPACE__ . '\\cmx_logout_redirect_url')
					? cmx_logout_redirect_url()
					: \home_url('/');
				\wp_safe_redirect($target);
				exit;
			}
			return;
		}
	}

	$transient_key = 'cmx_system_session_sweep_lock';
	if (\get_transient($transient_key)) {
		return;
	}
	\set_transient($transient_key, '1', 300);

	foreach (cmx_system_session_user_ids() as $user_id) {
		if ($user_id === $current_user_id) {
			continue;
		}
		cmx_system_cleanup_duplicate_sessions_for_user((int) $user_id);
	}
}, 30);
