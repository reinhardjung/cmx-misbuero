<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_page_is_active')) {
	function cmx_emails_page_is_active(): bool {
		if (!\is_admin()) {
			return false;
		}
		$page = isset($_GET['page']) ? \sanitize_key((string) \wp_unslash($_GET['page'])) : '';
		return $page === CMX_EMAILS_PAGE_SLUG;
	}
}

\add_action('admin_init', function (): void {
	if (!\is_admin()) {
		return;
	}

	$page = isset($_GET['page']) ? \sanitize_key((string) \wp_unslash($_GET['page'])) : '';
	if ($page !== CMX_EMAILS_PAGE_SLUG) {
		return;
	}

	$email_id = isset($_GET['email_id']) ? (int) \wp_unslash($_GET['email_id']) : 0;
	if ($email_id > 0 && (string) \get_post_type($email_id) === CMX_EMAILS_CPT) {
		$edit_url = \get_edit_post_link($email_id, '');
		if (\is_string($edit_url) && $edit_url !== '') {
			\wp_safe_redirect($edit_url);
			exit;
		}
	}

	$args = [];
	if (isset($_GET['account_id']) && !\is_array($_GET['account_id'])) {
		$args['cmx_email_account'] = \sanitize_key((string) \wp_unslash($_GET['account_id']));
	}
	if (isset($_GET['folder']) && !\is_array($_GET['folder'])) {
		$args['cmx_email_folder'] = \sanitize_key((string) \wp_unslash($_GET['folder']));
	}
	if (isset($_GET['s']) && !\is_array($_GET['s'])) {
		$args['s'] = \sanitize_text_field((string) \wp_unslash($_GET['s']));
	}
	if (isset($_GET['cmx_email_notice']) && !\is_array($_GET['cmx_email_notice'])) {
		$args['cmx_email_notice'] = \sanitize_text_field((string) \wp_unslash($_GET['cmx_email_notice']));
	}
	if (isset($_GET['cmx_email_notice_type']) && !\is_array($_GET['cmx_email_notice_type'])) {
		$args['cmx_email_notice_type'] = \sanitize_key((string) \wp_unslash($_GET['cmx_email_notice_type']));
	}

	\wp_safe_redirect(cmx_emails_admin_list_url($args));
	exit;
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_mailbox_notice')) {
	function cmx_emails_mailbox_notice(): array {
		$message = isset($_GET['cmx_email_notice']) ? \sanitize_text_field((string) \wp_unslash($_GET['cmx_email_notice'])) : '';
		$type = isset($_GET['cmx_email_notice_type']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_email_notice_type'])) : 'info';
		return [
			'message' => $message,
			'type' => \in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'info',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_action_context')) {
	function cmx_emails_action_context(int $post_id = 0): array {
		$account_id = isset($_REQUEST['account_id']) ? \sanitize_key((string) \wp_unslash($_REQUEST['account_id'])) : '';
		$folder = isset($_REQUEST['folder']) ? \sanitize_key((string) \wp_unslash($_REQUEST['folder'])) : '';
		$email_id = isset($_REQUEST['email_id']) ? (int) \wp_unslash($_REQUEST['email_id']) : 0;

		if ($post_id > 0) {
			if ($account_id === '') {
				$account_id = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('account_id'), true));
			}
			if ($folder === '') {
				$folder = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('folder'), true));
			}
			if ($email_id <= 0) {
				$email_id = $post_id;
			}
		}

		return [
			'account_id' => $account_id !== '' ? $account_id : cmx_emails_default_client_id(),
			'folder' => $folder !== '' ? $folder : 'inbox',
			'email_id' => $email_id,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_redirect_with_notice')) {
	function cmx_emails_redirect_with_notice(array $context, string $message, string $type = 'info', array $extra = []): void {
		$email_id = (int) ($context['email_id'] ?? 0);
		$args = \array_merge([
			'cmx_email_account' => \sanitize_key((string) ($context['account_id'] ?? '')),
			'cmx_email_folder' => \sanitize_key((string) ($context['folder'] ?? 'inbox')),
			'cmx_email_notice' => $message,
			'cmx_email_notice_type' => $type,
		], $extra);

		if ((string) ($args['cmx_email_account'] ?? '') === '') {
			unset($args['cmx_email_account']);
		}
		if ((string) ($args['cmx_email_folder'] ?? '') === '') {
			unset($args['cmx_email_folder']);
		}
		unset($args['account_id'], $args['folder'], $args['email_id']);

		if ($email_id > 0 && (string) \get_post_type($email_id) === CMX_EMAILS_CPT) {
			$edit_url = \get_edit_post_link($email_id, '');
			if (\is_string($edit_url) && $edit_url !== '') {
				\wp_safe_redirect(\add_query_arg([
					'cmx_email_notice' => (string) ($args['cmx_email_notice'] ?? ''),
					'cmx_email_notice_type' => (string) ($args['cmx_email_notice_type'] ?? 'info'),
				], $edit_url));
				exit;
			}
		}

		\wp_safe_redirect(cmx_emails_admin_list_url($args));
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_mailto_link')) {
	function cmx_emails_mailto_link(string $email, string $subject_prefix, string $subject): string {
		$email = \sanitize_email($email);
		$query = [
			'subject' => \trim($subject_prefix . ' ' . $subject),
		];
		$base = 'mailto:' . ($email !== '' ? \rawurlencode($email) : '');
		return $base . '?' . \http_build_query($query, '', '&', \PHP_QUERY_RFC3986);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_render_address_html')) {
	function cmx_emails_render_address_html(array $items): string {
		if ($items === []) {
			return '–';
		}
		$labels = [];
		foreach ($items as $item) {
			if (!\is_array($item)) {
				continue;
			}
			$labels[] = \esc_html((string) ($item['label'] ?? ''));
		}
		return $labels !== [] ? \implode('<br>', $labels) : '–';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_render_assignment_options')) {
	function cmx_emails_render_assignment_options(array $options, int $selected, string $placeholder): string {
		$html = '<option value="0">' . \esc_html($placeholder) . '</option>';
		foreach ($options as $id => $label) {
			$html .= '<option value="' . (int) $id . '"' . \selected($selected, (int) $id, false) . '>' . \esc_html((string) $label) . '</option>';
		}
		return $html;
	}
}

\add_action('admin_post_cmx_emails_sync', function (): void {
	if (!\current_user_can('edit_posts')) {
		\wp_die('Keine Berechtigung.');
	}
	\check_admin_referer('cmx_emails_sync');
	$account_id = isset($_REQUEST['account_id']) && !\is_array($_REQUEST['account_id'])
		? \sanitize_key((string) \wp_unslash($_REQUEST['account_id']))
		: '';
	$folder = isset($_REQUEST['folder']) && !\is_array($_REQUEST['folder'])
		? \sanitize_key((string) \wp_unslash($_REQUEST['folder']))
		: 'inbox';
	$context = [
		'account_id' => $account_id,
		'folder' => $folder !== '' ? $folder : 'inbox',
		'email_id' => 0,
	];
	$result = cmx_emails_sync_messages($account_id, (string) $context['folder']);
	cmx_emails_redirect_with_notice($context, (string) ($result['message'] ?? 'Synchronisierung beendet.'), !empty($result['ok']) ? 'success' : 'error');
});

\add_action('admin_post_cmx_emails_delete', function (): void {
	if (!\current_user_can('delete_posts')) {
		\wp_die('Keine Berechtigung.');
	}
	\check_admin_referer('cmx_emails_delete');
	$post_id = isset($_REQUEST['post_id']) ? (int) \wp_unslash($_REQUEST['post_id']) : 0;
	$context = cmx_emails_action_context($post_id);
	$result = cmx_emails_delete_message($post_id);
	$context['email_id'] = 0;
	cmx_emails_redirect_with_notice($context, (string) ($result['message'] ?? 'E-Mail geloescht.'), !empty($result['ok']) ? 'success' : 'error');
});

\add_action('admin_post_cmx_emails_import', function (): void {
	if (!\current_user_can('edit_posts')) {
		\wp_die('Keine Berechtigung.');
	}
	\check_admin_referer('cmx_emails_import');
	$post_id = isset($_REQUEST['post_id']) ? (int) \wp_unslash($_REQUEST['post_id']) : 0;
	$context = cmx_emails_action_context($post_id);
	$result = cmx_emails_import_post_attachments($post_id);
	cmx_emails_redirect_with_notice($context, (string) ($result['message'] ?? 'Uebernahme beendet.'), !empty($result['ok']) ? 'success' : 'error');
});

\add_action('admin_post_cmx_emails_assign', function (): void {
	if (!\current_user_can('edit_posts')) {
		\wp_die('Keine Berechtigung.');
	}
	\check_admin_referer('cmx_emails_assign');
	$post_id = isset($_POST['post_id']) ? (int) \wp_unslash($_POST['post_id']) : 0;
	$contact_id = isset($_POST['contact_id']) ? (int) \wp_unslash($_POST['contact_id']) : 0;
	$project_id = isset($_POST['project_id']) ? (int) \wp_unslash($_POST['project_id']) : 0;
	$context = cmx_emails_action_context($post_id);

	if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_EMAILS_CPT) {
		cmx_emails_redirect_with_notice($context, 'E-Mail wurde nicht gefunden.', 'error');
	}

	\update_post_meta($post_id, cmx_emails_meta_key('contact_id'), (string) \max(0, $contact_id));
	\update_post_meta($post_id, cmx_emails_meta_key('project_id'), (string) \max(0, $project_id));
	\update_post_meta($post_id, cmx_emails_meta_key('assignment_manual'), '1');
	cmx_emails_update_assignment_cache($post_id);

	cmx_emails_redirect_with_notice($context, 'Zuordnung wurde gespeichert.', 'success');
});

\add_action('all_admin_notices', function (): void {
	if (!\is_admin()) {
		return;
	}

	if (\function_exists(__NAMESPACE__ . '\\cmx_emails_edit_screen_active') && cmx_emails_edit_screen_active()) {
		return;
	}

	$notice = cmx_emails_mailbox_notice();
	if ($notice['message'] === '') {
		return;
	}

	$post_type = isset($_GET['post_type']) ? \sanitize_key((string) \wp_unslash($_GET['post_type'])) : '';
	$post_id = isset($_GET['post']) ? (int) \wp_unslash($_GET['post']) : 0;
	$is_email_screen = $post_type === CMX_EMAILS_CPT || ($post_id > 0 && (string) \get_post_type($post_id) === CMX_EMAILS_CPT);
	if (!$is_email_screen) {
		return;
	}

	echo '<div class="notice notice-' . \esc_attr($notice['type']) . ' is-dismissible"><p>' . \esc_html($notice['message']) . '</p></div>';
});

\add_action('admin_head', function (): void {
	if (!cmx_emails_page_is_active()) {
		return;
	}
	?>
	<style>
		.cmx-email-app {
			margin-top: 18px;
		}
		.cmx-email-shell {
			border: 1px solid #c9d8ee;
			border-radius: 20px;
			background: linear-gradient(180deg, #ffffff 0%, #f5f9ff 100%);
			box-shadow: 0 16px 40px rgba(30, 64, 175, 0.08);
			padding: 22px;
		}
		.cmx-email-toolbar,
		.cmx-email-tabs,
		.cmx-email-layout {
			display: grid;
			gap: 18px;
		}
		.cmx-email-toolbar {
			grid-template-columns: minmax(340px, 520px) auto;
			align-items: end;
			margin-bottom: 18px;
		}
		.cmx-email-toolbar h1 {
			margin: 0 0 8px;
			font-size: 20px;
			line-height: 1.3;
		}
		.cmx-email-toolbar p {
			margin: 0;
			color: #475467;
		}
		.cmx-email-toolbar-actions {
			display: flex;
			justify-content: flex-end;
			gap: 10px;
			flex-wrap: wrap;
		}
		.cmx-email-account-box,
		.cmx-email-tabbar,
		.cmx-email-panel,
		.cmx-email-body {
			border: 1px solid #c9d8ee;
			border-radius: 14px;
			background: linear-gradient(180deg, #ffffff 0%, #f7faff 100%);
			box-shadow: 0 8px 18px rgba(30, 64, 175, 0.06);
		}
		.cmx-email-account-box {
			padding: 16px;
		}
		.cmx-email-account-box label {
			display: block;
			font-weight: 600;
			margin-bottom: 8px;
		}
		.cmx-email-account-box select,
		.cmx-email-account-box input[type="search"],
		.cmx-email-assign select {
			width: 100%;
			max-width: none;
			min-height: 44px;
			border-radius: 10px;
		}
		.cmx-email-tabbar {
			display: flex;
			flex-wrap: wrap;
			padding: 12px;
			gap: 10px;
			margin-bottom: 18px;
		}
		.cmx-email-tabbar a {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			padding: 12px 16px;
			border: 1px solid #c8d7ee;
			border-radius: 11px;
			color: #274472;
			text-decoration: none;
			font-weight: 600;
			background: rgba(255, 255, 255, 0.82);
		}
		.cmx-email-tabbar a.current {
			background: #ffffff;
			box-shadow: inset 0 -3px 0 #2271b1;
		}
		.cmx-email-layout {
			grid-template-columns: minmax(0, 1.75fr) minmax(300px, 0.95fr);
			align-items: start;
		}
		.cmx-email-panel {
			overflow: hidden;
		}
		.cmx-email-panel-head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			padding: 14px 18px;
			border-bottom: 1px solid #d7e3f3;
		}
		.cmx-email-panel-head h2 {
			margin: 0;
			font-size: 15px;
			line-height: 1.3;
		}
		.cmx-email-search-form {
			display: flex;
			gap: 10px;
			align-items: center;
		}
		.cmx-email-search-form input[type="search"] {
			min-width: 240px;
		}
		.cmx-email-message-list {
			display: flex;
			flex-direction: column;
		}
		.cmx-email-message {
			display: grid;
			grid-template-columns: 1.35fr 1fr 0.72fr 0.7fr 34px;
			gap: 14px;
			align-items: center;
			padding: 14px 18px;
			border-top: 1px solid #dfe8f5;
			text-decoration: none;
			color: #1f2937;
			background: #fff;
		}
		.cmx-email-message:hover {
			background: #f5faff;
		}
		.cmx-email-message.is-current {
			background: linear-gradient(180deg, #edf5ff 0%, #e6f0fd 100%);
			box-shadow: inset 4px 0 0 #2271b1;
		}
		.cmx-email-sender {
			display: flex;
			align-items: center;
			gap: 12px;
			min-width: 0;
		}
		.cmx-email-sender strong,
		.cmx-email-sender span,
		.cmx-email-subject strong,
		.cmx-email-subject span {
			display: block;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
		.cmx-email-sender img {
			width: 38px;
			height: 38px;
			border-radius: 50%;
		}
		.cmx-email-subject strong {
			font-size: 15px;
		}
		.cmx-email-date {
			font-weight: 600;
			color: #475467;
		}
		.cmx-email-badge {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 6px 12px;
			border-radius: 9px;
			font-size: 12px;
			font-weight: 700;
		}
		.cmx-email-badge.is-new {
			background: #1d69d8;
			color: #fff;
		}
		.cmx-email-badge.is-read {
			background: #e8eef5;
			color: #425466;
		}
		.cmx-email-badge.is-processed {
			background: #6f7782;
			color: #fff;
		}
		.cmx-email-message-clip {
			font-size: 20px;
			text-align: center;
			color: #344054;
		}
		.cmx-email-body {
			margin-top: 18px;
			padding: 22px;
			min-height: 220px;
		}
		.cmx-email-body h3,
		.cmx-email-side-section h3 {
			margin: 0 0 14px;
			font-size: 16px;
		}
		.cmx-email-body-copy {
			font-size: 16px;
			line-height: 1.8;
			color: #233043;
			white-space: normal;
		}
		.cmx-email-side {
			display: flex;
			flex-direction: column;
			gap: 14px;
		}
		.cmx-email-side-section {
			border: 1px solid #c9d8ee;
			border-radius: 14px;
			background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
			box-shadow: 0 8px 18px rgba(30, 64, 175, 0.06);
			overflow: hidden;
		}
		.cmx-email-side-section header {
			padding: 14px 18px;
			border-bottom: 1px solid #d7e3f3;
		}
		.cmx-email-side-section header h3 {
			margin: 0;
		}
		.cmx-email-side-content {
			padding: 18px;
		}
		.cmx-email-meta-grid {
			display: grid;
			grid-template-columns: 62px 1fr;
			gap: 12px 10px;
			align-items: start;
		}
		.cmx-email-meta-grid dt {
			font-weight: 700;
		}
		.cmx-email-meta-grid dd {
			margin: 0;
			color: #25364f;
			word-break: break-word;
		}
		.cmx-email-attachments {
			display: flex;
			flex-direction: column;
			gap: 10px;
		}
		.cmx-email-attachment {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 14px;
			padding: 12px 14px;
			border: 1px solid #d7e3f3;
			border-radius: 12px;
			background: #fff;
		}
		.cmx-email-actions {
			display: flex;
			flex-wrap: wrap;
			gap: 10px;
		}
		.cmx-email-actions form {
			margin: 0;
		}
		.cmx-email-assign {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 10px;
		}
		.cmx-email-assign-actions {
			margin-top: 12px;
			display: flex;
			justify-content: space-between;
			gap: 10px;
			flex-wrap: wrap;
		}
		.cmx-email-empty {
			padding: 30px 18px;
			color: #64748b;
		}
		.cmx-email-list-link {
			text-decoration: none;
		}
		@media (max-width: 1280px) {
			.cmx-email-layout {
				grid-template-columns: 1fr;
			}
		}
		@media (max-width: 900px) {
			.cmx-email-toolbar {
				grid-template-columns: 1fr;
			}
			.cmx-email-message {
				grid-template-columns: 1fr;
			}
			.cmx-email-assign {
				grid-template-columns: 1fr;
			}
		}
	</style>
	<?php
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_render_mailbox_page')) {
	function cmx_emails_render_mailbox_page(): void {
		if (!\current_user_can('edit_posts')) {
			\wp_die('Keine Berechtigung.');
		}

		$clients = cmx_emails_client_list();
		$account_id = isset($_GET['account_id']) ? \sanitize_key((string) \wp_unslash($_GET['account_id'])) : cmx_emails_default_client_id();
		$folder = isset($_GET['folder']) ? \sanitize_key((string) \wp_unslash($_GET['folder'])) : 'inbox';
		$search = isset($_GET['s']) ? \sanitize_text_field((string) \wp_unslash($_GET['s'])) : '';
		$email_id = isset($_GET['email_id']) ? (int) \wp_unslash($_GET['email_id']) : 0;

		$client = cmx_emails_get_client($account_id);
		if ($client !== []) {
			$account_id = \sanitize_key((string) ($client['id'] ?? $account_id));
		}

		$notice = cmx_emails_mailbox_notice();
		if ($clients === []) {
			echo '<div class="wrap"><h1>E-Mails</h1><div class="notice notice-warning"><p>Es sind noch keine E-Mail-Clients hinterlegt. <a href="' . \esc_url(cmx_emails_settings_url()) . '">Jetzt Clients erfassen</a>.</p></div></div>';
			return;
		}

		$query = new \WP_Query(cmx_emails_query_args([
			'account_id' => $account_id,
			'folder' => $folder,
			's' => $search,
			'posts_per_page' => 40,
			'paged' => 1,
		]));
		$messages = $query->posts;

		if ($email_id > 0) {
			cmx_emails_mark_as_read($email_id);
		}

		$selected = $email_id > 0 ? \get_post($email_id) : null;
		if (!$selected instanceof \WP_Post || $selected->post_type !== CMX_EMAILS_CPT) {
			$selected = isset($messages[0]) && $messages[0] instanceof \WP_Post ? $messages[0] : null;
		}
		if ($selected instanceof \WP_Post) {
			$email_id = (int) $selected->ID;
			cmx_emails_mark_as_read($email_id);
		}

		$folder_counts = [];
		foreach (cmx_emails_folder_map() as $folder_key => $folder_data) {
			$folder_counts[$folder_key] = cmx_emails_count([
				'account_id' => $account_id,
				'folder' => $folder_key,
			]);
		}

		$contact_options = cmx_emails_assignment_options('contact');
		$project_options = cmx_emails_assignment_options('project');

		echo '<div class="wrap cmx-email-app">';
		if ($notice['message'] !== '') {
			echo '<div class="notice notice-' . \esc_attr($notice['type']) . '"><p>' . \esc_html($notice['message']) . '</p></div>';
		}
		echo '<div class="cmx-email-shell">';
		echo '<div class="cmx-email-toolbar">';
		echo '<div class="cmx-email-account-box">';
		// echo '<h1>E-Mail Center</h1>';
		// echo '<p>Synchronisierte E-Mails aus deinen hinterlegten Clients mit Detailansicht, Anhaengen und Zuordnung.</p>';
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="' . \esc_attr(CMX_EMAILS_PAGE_SLUG) . '">';
		echo '<input type="hidden" name="post_type" value="' . \esc_attr(CMX_EMAILS_CPT) . '">';
		echo '<input type="hidden" name="folder" value="' . \esc_attr($folder) . '">';
		// echo '<label for="cmx-email-account">Konto</label>';
		echo '<select id="cmx-email-account" name="account_id" onchange="this.form.submit()">';
		foreach ($clients as $row) {
			$row = (array) $row;
			$row_id = \sanitize_key((string) ($row['id'] ?? ''));
			echo '<option value="' . \esc_attr($row_id) . '"' . \selected($row_id, $account_id, false) . '>' . \esc_html(cmx_emails_client_label($row)) . '</option>';
		}
		echo '</select>';
		echo '</form>';
		echo '</div>';
		echo '<div class="cmx-email-toolbar-actions">';
		echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '">';
		\wp_nonce_field('cmx_emails_sync');
		echo '<input type="hidden" name="action" value="cmx_emails_sync">';
		echo '<input type="hidden" name="account_id" value="' . \esc_attr($account_id) . '">';
		echo '<input type="hidden" name="folder" value="' . \esc_attr($folder) . '">';
		echo '<button type="submit" class="button button-primary button-large">Synchronisieren</button>';
		echo '</form>';
		echo '<a class="button button-secondary button-large" href="' . \esc_url(cmx_emails_settings_url()) . '">Einstellungen</a>';
		echo '</div>';
		echo '</div>';

		echo '<nav class="cmx-email-tabbar">';
		foreach (cmx_emails_folder_map() as $folder_key => $folder_data) {
			$url = cmx_emails_mailbox_url([
				'account_id' => $account_id,
				'folder' => $folder_key,
			]);
			$current_class = $folder_key === $folder ? ' current' : '';
			echo '<a class="' . \esc_attr(\trim($current_class)) . '" href="' . \esc_url($url) . '">' . \esc_html((string) $folder_data['label']) . ' <span class="count">(' . (int) ($folder_counts[$folder_key] ?? 0) . ')</span></a>';
		}
		echo '</nav>';

		echo '<div class="cmx-email-layout">';
		echo '<div>';
		echo '<section class="cmx-email-panel">';
		echo '<div class="cmx-email-panel-head">';
		echo '<h2>' . \esc_html(cmx_emails_folder_label($folder)) . '</h2>';
		echo '<form class="cmx-email-search-form" method="get">';
		echo '<input type="hidden" name="page" value="' . \esc_attr(CMX_EMAILS_PAGE_SLUG) . '">';
		echo '<input type="hidden" name="post_type" value="' . \esc_attr(CMX_EMAILS_CPT) . '">';
		echo '<input type="hidden" name="account_id" value="' . \esc_attr($account_id) . '">';
		echo '<input type="hidden" name="folder" value="' . \esc_attr($folder) . '">';
		echo '<input type="search" name="s" value="' . \esc_attr($search) . '" placeholder="Suchen E-Mails">';
		echo '<button type="submit" class="button">Suchen</button>';
		echo '</form>';
		echo '</div>';

		if ($messages === []) {
			echo '<div class="cmx-email-empty">Noch keine synchronisierten E-Mails in diesem Ordner.</div>';
		} else {
			echo '<div class="cmx-email-message-list">';
			foreach ($messages as $message) {
				if (!$message instanceof \WP_Post) {
					continue;
				}
				$message_id_row = (int) $message->ID;
				$sender_email = \sanitize_email((string) \get_post_meta($message_id_row, cmx_emails_meta_key('sender_email'), true));
				$sender_label = (string) \get_post_meta($message_id_row, cmx_emails_meta_key('sender_label'), true);
				$status = \sanitize_key((string) \get_post_meta($message_id_row, cmx_emails_meta_key('status'), true));
				$attachment_count = (int) \get_post_meta($message_id_row, cmx_emails_meta_key('attachment_count'), true);
				$ts = (int) \get_post_meta($message_id_row, cmx_emails_meta_key('received_ts'), true);
				$url = cmx_emails_mailbox_url([
					'account_id' => $account_id,
					'folder' => $folder,
					's' => $search,
					'email_id' => $message_id_row,
				]);
				$current_class = $message_id_row === $email_id ? ' is-current' : '';
				echo '<a class="cmx-email-message' . \esc_attr($current_class) . '" href="' . \esc_url($url) . '">';
				echo '<div class="cmx-email-sender">';
				echo \get_avatar($sender_email, 38);
				echo '<div><strong>' . \esc_html($sender_label !== '' ? $sender_label : $sender_email) . '</strong><span>' . \esc_html($sender_email) . '</span></div>';
				echo '</div>';
				echo '<div class="cmx-email-subject"><strong>' . \esc_html((string) $message->post_title) . '</strong><span>' . \esc_html((string) $message->post_excerpt) . '</span></div>';
				echo '<div class="cmx-email-date">' . \esc_html(cmx_emails_date_label($ts)) . '</div>';
				echo '<div><span class="cmx-email-badge ' . \esc_attr(cmx_emails_status_class($status)) . '">' . \esc_html(cmx_emails_status_label($status)) . '</span></div>';
				echo '<div class="cmx-email-message-clip">' . ($attachment_count > 0 ? '📎' : '') . '</div>';
				echo '</a>';
			}
			echo '</div>';
		}
		echo '</section>';

		echo '<section class="cmx-email-body">';
		if ($selected instanceof \WP_Post) {
			$body_html = (string) \get_post_meta($selected->ID, cmx_emails_meta_key('body_html'), true);
			$body_plain = (string) \get_post_meta($selected->ID, cmx_emails_meta_key('body_plain'), true);
			echo '<h3>Inhalt</h3>';
			echo '<div class="cmx-email-body-copy">';
			if ($body_html !== '') {
				echo \wp_kses_post($body_html);
			} else {
				echo \wpautop(\esc_html($body_plain !== '' ? $body_plain : (string) $selected->post_content));
			}
			echo '</div>';
		} else {
			echo '<div class="cmx-email-empty">Bitte zuerst synchronisieren oder eine E-Mail auswaehlen.</div>';
		}
		echo '</section>';
		echo '</div>';

		echo '<aside class="cmx-email-side">';
		if ($selected instanceof \WP_Post) {
			$selected_id = (int) $selected->ID;
			$sender_email = \sanitize_email((string) \get_post_meta($selected_id, cmx_emails_meta_key('sender_email'), true));
			$subject = (string) \get_post_meta($selected_id, cmx_emails_meta_key('subject'), true);
			$ts = (int) \get_post_meta($selected_id, cmx_emails_meta_key('received_ts'), true);
			$attachments = cmx_emails_normalize_attachment_list(\get_post_meta($selected_id, cmx_emails_meta_key('attachments'), true));
			$contact_id = (int) \get_post_meta($selected_id, cmx_emails_meta_key('contact_id'), true);
			$project_id = (int) \get_post_meta($selected_id, cmx_emails_meta_key('project_id'), true);
			$reply_url = \function_exists(__NAMESPACE__ . '\\cmx_emails_compose_admin_url')
				? cmx_emails_compose_admin_url('reply', $selected_id)
				: cmx_emails_mailto_link($sender_email, 'Re:', $subject);
			$forward_url = \function_exists(__NAMESPACE__ . '\\cmx_emails_compose_admin_url')
				? cmx_emails_compose_admin_url('forward', $selected_id)
				: cmx_emails_mailto_link('', 'Fwd:', $subject);

			echo '<section class="cmx-email-side-section"><header><h3>E-Mail-Details</h3></header><div class="cmx-email-side-content">';
			echo '<dl class="cmx-email-meta-grid">';
			echo '<dt>Von:</dt><dd>' . \esc_html((string) \get_post_meta($selected_id, cmx_emails_meta_key('sender_label'), true)) . '</dd>';
			echo '<dt>An:</dt><dd>' . cmx_emails_render_address_html((array) \get_post_meta($selected_id, cmx_emails_meta_key('to'), true)) . '</dd>';
			echo '<dt>Betreff:</dt><dd>' . \esc_html($subject !== '' ? $subject : (string) $selected->post_title) . '</dd>';
			echo '<dt>Datum:</dt><dd>' . \esc_html(cmx_emails_date_label_long($ts)) . '</dd>';
			echo '<dt>Konto:</dt><dd>' . \esc_html((string) \get_post_meta($selected_id, cmx_emails_meta_key('account_label'), true)) . '</dd>';
			echo '</dl>';
			echo '</div></section>';

			echo '<section class="cmx-email-side-section"><header><h3>Anhaenge</h3></header><div class="cmx-email-side-content">';
			if ($attachments === []) {
				echo '<div class="cmx-email-empty" style="padding:0;color:#64748b;">Keine Anhaenge vorhanden.</div>';
			} else {
				echo '<div class="cmx-email-attachments">';
				foreach ($attachments as $attachment) {
					$url = (string) ($attachment['url'] ?? '');
					$size = (int) ($attachment['size'] ?? 0);
					echo '<div class="cmx-email-attachment">';
					echo '<div><strong>' . \esc_html((string) ($attachment['filename'] ?? 'Anhang')) . '</strong><br><span>' . \esc_html($size > 0 ? \size_format($size, 0) : '') . '</span></div>';
					if ($url !== '') {
						echo '<a class="button button-link" href="' . \esc_url($url) . '" download>Download</a>';
					}
					echo '</div>';
				}
				echo '</div>';
			}
			echo '</div></section>';

			echo '<section class="cmx-email-side-section"><header><h3>Aktionen</h3></header><div class="cmx-email-side-content">';
			echo '<div class="cmx-email-actions">';
			if ($reply_url !== '') {
				echo '<a class="button button-primary" href="' . \esc_url($reply_url) . '">Antworten</a>';
			}
			if ($forward_url !== '') {
				echo '<a class="button" href="' . \esc_url($forward_url) . '">Weiterleiten</a>';
			}
			echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '">';
			\wp_nonce_field('cmx_emails_delete');
			echo '<input type="hidden" name="action" value="cmx_emails_delete">';
			echo '<input type="hidden" name="post_id" value="' . (int) $selected_id . '">';
			echo '<input type="hidden" name="account_id" value="' . \esc_attr($account_id) . '">';
			echo '<input type="hidden" name="folder" value="' . \esc_attr($folder) . '">';
			echo '<button type="submit" class="button button-link-delete">Loeschen</button>';
			echo '</form>';
			echo '</div>';
			echo '</div></section>';

			echo '<section class="cmx-email-side-section"><header><h3>Zuordnung</h3></header><div class="cmx-email-side-content">';
			echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '">';
			\wp_nonce_field('cmx_emails_assign');
			echo '<input type="hidden" name="action" value="cmx_emails_assign">';
			echo '<input type="hidden" name="post_id" value="' . (int) $selected_id . '">';
			echo '<input type="hidden" name="account_id" value="' . \esc_attr($account_id) . '">';
			echo '<input type="hidden" name="folder" value="' . \esc_attr($folder) . '">';
			echo '<div class="cmx-email-assign">';
			echo '<select name="contact_id">' . cmx_emails_render_assignment_options($contact_options, $contact_id, 'Kunde zuordnen') . '</select>';
			echo '<select name="project_id">' . cmx_emails_render_assignment_options($project_options, $project_id, 'Projekt zuweisen') . '</select>';
			echo '</div>';
			echo '<div class="cmx-email-assign-actions">';
			echo '<button type="submit" class="button">Zuordnung speichern</button>';
			echo '</form>';
			echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '">';
			\wp_nonce_field('cmx_emails_import');
			echo '<input type="hidden" name="action" value="cmx_emails_import">';
			echo '<input type="hidden" name="post_id" value="' . (int) $selected_id . '">';
			echo '<input type="hidden" name="account_id" value="' . \esc_attr($account_id) . '">';
			echo '<input type="hidden" name="folder" value="' . \esc_attr($folder) . '">';
			echo '<button type="submit" class="button button-primary">Als Beleg uebernehmen</button>';
			echo '</form>';
			echo '</div>';
			echo '</div>';
			echo '</section>';
		} else {
			echo '<section class="cmx-email-side-section"><div class="cmx-email-side-content">Noch keine E-Mail ausgewaehlt.</div></section>';
		}
		echo '</aside>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}
}
