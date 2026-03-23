<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_admin_list_active')) {
	function cmx_emails_admin_list_active(): bool {
		if (!\is_admin()) {
			return false;
		}
		$post_type = isset($_GET['post_type']) ? \sanitize_key((string) \wp_unslash($_GET['post_type'])) : '';
		return $post_type === CMX_EMAILS_CPT && !cmx_emails_page_is_active();
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_current_view')) {
	function cmx_emails_current_view(): string {
		$view = isset($_GET['cmx_email_view']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_email_view'])) : 'all';
		return \in_array($view, ['all', 'new', 'read', 'attachment', 'unassigned', 'processed'], true) ? $view : 'all';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_current_filters')) {
	function cmx_emails_current_filters(): array {
		$filters = [
			'account_id' => isset($_GET['cmx_email_account']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_email_account'])) : '',
			'folder' => isset($_GET['cmx_email_folder']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_email_folder'])) : '',
			'status' => isset($_GET['cmx_email_status']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_email_status'])) : '',
		];

		return $filters;
	}
}

\add_filter('manage_edit-' . CMX_EMAILS_CPT . '_columns', function ($columns) {
	$new = [];
	$new['cb'] = $columns['cb'] ?? '<input type="checkbox">';
	$new['cmx_email_sender'] = 'Absender';
	$new['title'] = 'Betreff';
	$new['cmx_email_account'] = 'Konto';
	$new['cmx_email_date'] = 'Datum';
	$new['cmx_email_status'] = 'Status';
	$new['cmx_email_assignment'] = 'Zuordnung';
	return $new;
});

\add_filter('manage_edit-' . CMX_EMAILS_CPT . '_sortable_columns', function ($columns) {
	$columns['cmx_email_date'] = 'cmx_email_date';
	return $columns;
});

\add_action('manage_' . CMX_EMAILS_CPT . '_posts_custom_column', function ($column, $post_id) {
	$post_id = (int) $post_id;

	if ($column === 'cmx_email_sender') {
		$sender_email = \sanitize_email((string) \get_post_meta($post_id, cmx_emails_meta_key('sender_email'), true));
		$sender_label = (string) \get_post_meta($post_id, cmx_emails_meta_key('sender_label'), true);
		echo '<div class="cmx-email-admin-sender">';
		echo \get_avatar($sender_email, 52);
		echo '<div class="cmx-email-admin-sender-copy"><strong>' . \esc_html($sender_label !== '' ? $sender_label : $sender_email) . '</strong><span>' . \esc_html($sender_email) . '</span></div>';
		echo '</div>';
		return;
	}

	if ($column === 'title') {
		$excerpt = (string) \get_post_meta($post_id, cmx_emails_meta_key('body_plain'), true);
		if ($excerpt === '') {
			$excerpt = (string) \get_post($post_id)->post_excerpt;
		}
		$edit_url = \get_edit_post_link($post_id, '');
		$title = '<strong>' . \esc_html((string) \get_the_title($post_id)) . '</strong>';
		echo \is_string($edit_url) && $edit_url !== '' ? '<a href="' . \esc_url($edit_url) . '">' . $title . '</a>' : $title;
		if ($excerpt !== '') {
			echo '<span class="cmx-email-admin-excerpt">' . \esc_html(cmx_emails_text_excerpt($excerpt, 100)) . '</span>';
		}
		return;
	}

	if ($column === 'cmx_email_account') {
		echo \esc_html((string) \get_post_meta($post_id, cmx_emails_meta_key('account_label'), true));
		return;
	}

	if ($column === 'cmx_email_date') {
		$ts = (int) \get_post_meta($post_id, cmx_emails_meta_key('received_ts'), true);
		echo \esc_html(cmx_emails_date_label($ts));
		return;
	}

	if ($column === 'cmx_email_status') {
		$status = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('status'), true));
		echo '<span class="cmx-email-badge ' . \esc_attr(cmx_emails_status_class($status)) . '">' . \esc_html(cmx_emails_status_label($status)) . '</span>';
		return;
	}

	if ($column === 'cmx_email_assignment') {
		$attachment_count = (int) \get_post_meta($post_id, cmx_emails_meta_key('attachment_count'), true);
		$label = (string) \get_post_meta($post_id, cmx_emails_meta_key('assignment_label'), true);
		if ($attachment_count > 0) {
			echo '<div class="cmx-email-admin-attachments">📎 ' . (int) $attachment_count . '</div>';
		}
		echo \esc_html($label !== '' ? $label : 'nicht zugeordnet');
	}
}, 10, 2);

\add_filter('post_row_actions', function (array $actions, \WP_Post $post): array {
	if ($post->post_type !== CMX_EMAILS_CPT) {
		return $actions;
	}

	$actions = [];
	$edit_url = \get_edit_post_link($post->ID, '');
	if (\is_string($edit_url) && $edit_url !== '') {
		$actions['edit'] = '<a href="' . \esc_url($edit_url) . '">Bearbeiten</a>';
	}

	$import_url = \wp_nonce_url(\add_query_arg([
		'action' => 'cmx_emails_import',
		'post_id' => (int) $post->ID,
		'email_id' => (int) $post->ID,
	], \admin_url('admin-post.php')), 'cmx_emails_import');
	$actions['import'] = '<a href="' . \esc_url($import_url) . '">Als Beleg uebernehmen</a>';

	$delete_url = \wp_nonce_url(\add_query_arg([
		'action' => 'cmx_emails_delete',
		'post_id' => (int) $post->ID,
	], \admin_url('admin-post.php')), 'cmx_emails_delete');
	$actions['delete'] = '<a href="' . \esc_url($delete_url) . '" class="submitdelete">Loeschen</a>';

	return $actions;
}, 10, 2);

\add_filter('views_edit-' . CMX_EMAILS_CPT, function (array $views): array {
	$filters = cmx_emails_current_filters();
	$base_filters = [
		'account_id' => (string) ($filters['account_id'] ?? ''),
		'folder' => (string) ($filters['folder'] ?? ''),
	];
	$view = cmx_emails_current_view();
	$defs = [
		'all' => ['label' => 'Alle', 'filters' => []],
		'new' => ['label' => 'Neu', 'filters' => ['status' => 'new']],
		'read' => ['label' => 'Gelesen', 'filters' => ['status' => 'read']],
		'attachment' => ['label' => 'Mit Anhang', 'filters' => ['has_attachment' => true]],
		'unassigned' => ['label' => 'Nicht zugeordnet', 'filters' => ['unassigned' => true]],
		'processed' => ['label' => 'Verarbeitet', 'filters' => ['status' => 'processed']],
	];

	$args_keep = [];
	foreach (['cmx_email_account', 'cmx_email_folder', 'cmx_email_status', 's'] as $key) {
		if (isset($_GET[$key])) {
			$args_keep[$key] = \sanitize_text_field((string) \wp_unslash($_GET[$key]));
		}
	}

	$views = [];
	foreach ($defs as $key => $def) {
		$count = cmx_emails_count(\array_merge($base_filters, (array) ($def['filters'] ?? [])));
		$url = cmx_emails_admin_list_url(\array_merge($args_keep, [
			'cmx_email_view' => $key,
		]));
		$current = $view === $key ? ' class="current" aria-current="page"' : '';
		$views[$key] = '<a href="' . \esc_url($url) . '"' . $current . '>' . \esc_html((string) $def['label']) . ' <span class="count">(' . (int) $count . ')</span></a>';
	}

	return $views;
});

\add_action('restrict_manage_posts', function (): void {
	if (!cmx_emails_admin_list_active()) {
		return;
	}

	$filters = cmx_emails_current_filters();
	$status = \sanitize_key((string) ($filters['status'] ?? ''));
	$folder = \sanitize_key((string) ($filters['folder'] ?? ''));
	$account = \sanitize_key((string) ($filters['account_id'] ?? ''));

	echo '<select name="cmx_email_account">';
	echo '<option value="">Alle Konten</option>';
	foreach (cmx_emails_client_list() as $client) {
		$client = (array) $client;
		$id = \sanitize_key((string) ($client['id'] ?? ''));
		echo '<option value="' . \esc_attr($id) . '"' . \selected($account, $id, false) . '>' . \esc_html(cmx_emails_client_label($client)) . '</option>';
	}
	echo '</select>';

	echo '<select name="cmx_email_folder">';
	echo '<option value="">Alle Ordner</option>';
	foreach (cmx_emails_folder_map() as $folder_key => $data) {
		echo '<option value="' . \esc_attr($folder_key) . '"' . \selected($folder, $folder_key, false) . '>' . \esc_html((string) ($data['label'] ?? $folder_key)) . '</option>';
	}
	echo '</select>';

	echo '<select name="cmx_email_status">';
	echo '<option value="">Alle Status</option>';
	foreach (['new' => 'Neu', 'read' => 'Gelesen', 'processed' => 'Verarbeitet'] as $value => $label) {
		echo '<option value="' . \esc_attr($value) . '"' . \selected($status, $value, false) . '>' . \esc_html($label) . '</option>';
	}
	echo '</select>';

	echo '<span class="cmx-email-filter-actions">';
	$sync_folder = $folder !== '' ? $folder : 'inbox';
	$sync_args = [
		'action' => 'cmx_emails_sync',
		'folder' => $sync_folder,
	];
	if ($account !== '') {
		$sync_args['account_id'] = $account;
	}
	$sync_url = \wp_nonce_url(\add_query_arg($sync_args, \admin_url('admin-post.php')), 'cmx_emails_sync');
	echo '<a class="button" href="' . \esc_url($sync_url) . '">Synchronisieren</a>';
	echo '<a class="button" href="' . \esc_url(cmx_emails_settings_url()) . '">Einstellungen</a>';
	echo '</span>';
});

\add_action('pre_get_posts', function (\WP_Query $query): void {
	if (!\is_admin() || !$query->is_main_query()) {
		return;
	}

	$post_type = $query->get('post_type');
	$matches = $post_type === CMX_EMAILS_CPT
		|| (\is_array($post_type) && \in_array(CMX_EMAILS_CPT, $post_type, true))
		|| ($post_type === null && cmx_emails_admin_list_active());
	if (!$matches) {
		return;
	}

	$filters = cmx_emails_current_filters();
	$view = cmx_emails_current_view();

	if ($view === 'new') {
		$filters['status'] = 'new';
	} elseif ($view === 'read') {
		$filters['status'] = 'read';
	} elseif ($view === 'processed') {
		$filters['status'] = 'processed';
	} elseif ($view === 'attachment') {
		$filters['has_attachment'] = true;
	} elseif ($view === 'unassigned') {
		$filters['unassigned'] = true;
	}

	$meta_query = cmx_emails_build_meta_query($filters);
	if ($meta_query !== []) {
		$query->set('meta_query', $meta_query);
	}

	$orderby = isset($_GET['orderby']) ? \sanitize_key((string) \wp_unslash($_GET['orderby'])) : '';
	if ($orderby === '' || $orderby === 'cmx_email_date') {
		$query->set('meta_key', cmx_emails_meta_key('received_ts'));
		$query->set('orderby', 'meta_value_num');
		$query->set('order', isset($_GET['order']) ? \sanitize_key((string) \wp_unslash($_GET['order'])) : 'DESC');
	}
});

\add_action('admin_head-edit.php', function (): void {
	if (!cmx_emails_admin_list_active()) {
		return;
	}
	?>
	<style>
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .column-cmx_email_sender {
			width: 320px;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .column-title {
			width: 360px;
		}
		.cmx-email-admin-sender {
			display: flex;
			align-items: center;
			gap: 12px;
		}
		.cmx-email-admin-sender img {
			width: 52px;
			height: 52px;
			border-radius: 12px;
		}
		.cmx-email-admin-sender-copy strong,
		.cmx-email-admin-sender-copy span,
		.cmx-email-admin-excerpt {
			display: block;
		}
		.cmx-email-admin-sender-copy {
			font-size: 14px;
			line-height: 1.35;
		}
		.cmx-email-admin-sender-copy strong {
			font-size: inherit;
			line-height: inherit;
			font-weight: 600;
		}
		.cmx-email-admin-sender-copy span,
		.cmx-email-admin-excerpt {
			margin-top: 2px;
			color: #475467;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .column-title strong {
			font-size: 18px;
			line-height: 1.2;
		}
		.cmx-email-admin-attachments {
			margin-bottom: 6px;
			color: #344054;
			font-weight: 600;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 6px 12px;
			border-radius: 8px;
			font-size: 12px;
			font-weight: 700;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-new {
			background: #1d69d8;
			color: #fff;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-read {
			background: #0f766e;
			color: #fff;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-processed {
			background: #6b7280;
			color: #fff;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .wp-list-table td,
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .wp-list-table th {
			vertical-align: top;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .tablenav.top {
			display: flex;
			align-items: center;
			gap: 10px;
			flex-wrap: wrap;
			margin: 14px 0 18px;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .tablenav.top .alignleft.actions,
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .tablenav.top .tablenav-pages {
			float: none;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .tablenav.top .alignleft.actions.bulkactions {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			flex: 0 0 auto;
			margin-right: 0;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .tablenav .alignleft.actions:not(.bulkactions) {
			display: flex;
			align-items: center;
			gap: 8px;
			flex: 1 1 auto;
			min-width: 0;
			flex-wrap: nowrap;
			margin-right: 0;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .tablenav .alignleft.actions:not(.bulkactions) #post-query-submit {
			order: 1;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-filter-actions {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			order: 2;
			margin-left: auto;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .tablenav.top .tablenav-pages {
			margin-left: auto;
			padding-top: 0;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .tablenav.top .displaying-num,
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .tablenav.top .pagination-links {
			margin-bottom: 0;
		}
	</style>
	<?php
});
