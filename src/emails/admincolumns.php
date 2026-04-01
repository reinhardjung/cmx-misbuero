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
		$post_status = isset($_GET['post_status']) ? \sanitize_key((string) \wp_unslash($_GET['post_status'])) : '';
		if ($post_status === 'trash') {
			return 'trash';
		}

		$view = isset($_GET['cmx_email_view']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_email_view'])) : 'all';
		return \in_array($view, ['all', 'new', 'read', 'attachment', 'unassigned', 'processed', 'trash'], true) ? $view : 'all';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_current_filters')) {
	function cmx_emails_current_filters(): array {
		$category = isset($_GET['cmx_email_category']) ? (string) \wp_unslash($_GET['cmx_email_category']) : '';
		$category = $category === '0' ? '' : \sanitize_title($category);

		$filters = [
			'account_id' => isset($_GET['cmx_email_account']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_email_account'])) : '',
			'folder' => isset($_GET['cmx_email_folder']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_email_folder'])) : '',
			'status' => isset($_GET['cmx_email_status']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_email_status'])) : '',
			'category' => $category,
			'archive_year' => isset($_GET['cmx_email_archive_year']) ? \preg_replace('/[^0-9]/', '', (string) \wp_unslash($_GET['cmx_email_archive_year'])) : '',
			'archive_month' => isset($_GET['cmx_email_archive_month']) ? cmx_emails_normalize_archive_month((string) \wp_unslash($_GET['cmx_email_archive_month'])) : '',
		];

		return $filters;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_admin_sender_display_label')) {
	function cmx_emails_admin_sender_display_label(string $label, string $email): string {
		$label = \trim((string) $label);
		$email = \sanitize_email($email);

		if ($label === '') {
			return $email;
		}

		if ($email !== '') {
			$quoted_email = \preg_quote($email, '/');
			$label = (string) \preg_replace('/\s*<\s*' . $quoted_email . '\s*>\s*/i', '', $label);
		}

		$label = (string) \preg_replace('/\s*<[^>]+>\s*/', '', $label);
		$label = \trim($label);

		return $label !== '' ? $label : $email;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_admin_subject_label')) {
	function cmx_emails_admin_subject_label(int $post_id): string {
		$subject = \trim((string) \get_post_meta($post_id, cmx_emails_meta_key('subject'), true));
		if ($subject !== '') {
			return $subject;
		}

		$post = \get_post($post_id);
		$post_title = $post instanceof \WP_Post ? \trim((string) $post->post_title) : '';
		if ($post_title !== '') {
			return $post_title;
		}

		return \function_exists(__NAMESPACE__ . '\\cmx_emails_missing_subject_label')
			? cmx_emails_missing_subject_label()
			: 'Betreffzeile fehlt';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_admin_assignment_html')) {
	function cmx_emails_admin_assignment_html(string $label): string {
		$label = \trim($label);
		if ($label === '') {
			return 'nicht zugeordnet';
		}

		$parts = \array_values(\array_filter(\array_map(static function ($part): string {
			return \trim((string) $part);
		}, \explode('|', $label)), static function (string $part): bool {
			return $part !== '';
		}));

		if ($parts === []) {
			return \esc_html($label);
		}

		return \implode('<br>', \array_map(static function (string $part): string {
			return \esc_html($part);
		}, $parts));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_admin_folder_badge_class')) {
	function cmx_emails_admin_folder_badge_class(string $folder): string {
		$folder = \sanitize_key($folder);

		if ($folder === 'sent') {
			return 'is-folder-sent';
		}
		if ($folder === 'drafts') {
			return 'is-folder-drafts';
		}
		if ($folder === 'archive') {
			return 'is-folder-archive';
		}

		return 'is-folder-inbox';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_admin_folder_label')) {
	function cmx_emails_admin_folder_label(int $post_id): string {
		$folder = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('folder'), true));
		if ($folder === 'archive') {
			return \function_exists(__NAMESPACE__ . '\\cmx_emails_archive_folder_label_for_post')
				? cmx_emails_archive_folder_label_for_post($post_id)
				: 'Archiv';
		}

		return cmx_emails_folder_label($folder);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_category_taxonomy')) {
	function cmx_emails_category_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_tax_key')) {
			$tax = (string) cmx_tax_key('emails', 'Kategorien');
			if ($tax !== '') {
				return $tax;
			}
		}

		return 'emails_kategorien';
	}
}

\add_filter('the_title', function (string $title, $post_id = 0): string {
	if (!cmx_emails_admin_list_active()) {
		return $title;
	}

	$post_id = (int) $post_id;
	if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_EMAILS_CPT) {
		return $title;
	}

	return cmx_emails_admin_subject_label($post_id);
}, 20, 2);

\add_filter('manage_edit-' . CMX_EMAILS_CPT . '_columns', function ($columns) {
	$new = [];
	$new['cb'] = $columns['cb'] ?? '<input type="checkbox">';
	$new['title'] = 'Betreff';
	$new['cmx_email_sender'] = 'Absender';
	$new['cmx_email_folder'] = 'Ordner';
	$new['cmx_email_account'] = 'Konto';
	$new['cmx_email_status'] = 'Status';
	$new['cmx_email_category'] = 'Kategorie';
	$new['cmx_email_assignment'] = 'Zuordnung';
	$new['cmx_email_date'] = 'Datum';
	return $new;
});

\add_filter('manage_edit-' . CMX_EMAILS_CPT . '_sortable_columns', function ($columns) {
	$columns['cmx_email_date'] = 'cmx_email_date';
	return $columns;
});

\add_action('manage_' . CMX_EMAILS_CPT . '_posts_custom_column', function ($column, $post_id) {
	$post_id = (int) $post_id;

		if ($column === 'cmx_email_folder') {
			$folder = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('folder'), true));
			echo '<span class="cmx-email-badge cmx-email-folder-badge ' . \esc_attr(cmx_emails_admin_folder_badge_class($folder)) . '">' . \esc_html(cmx_emails_admin_folder_label($post_id)) . '</span>';
			return;
		}

	if ($column === 'cmx_email_sender') {
		$sender_email = \sanitize_email((string) \get_post_meta($post_id, cmx_emails_meta_key('sender_email'), true));
		$sender_label = (string) \get_post_meta($post_id, cmx_emails_meta_key('sender_label'), true);
		$sender_display = cmx_emails_admin_sender_display_label($sender_label, $sender_email);
		echo '<div class="cmx-email-admin-sender">';
		echo \get_avatar($sender_email, 52);
		echo '<div class="cmx-email-admin-sender-copy"><strong>' . \esc_html($sender_display) . '</strong><span>' . \esc_html($sender_email) . '</span></div>';
		echo '</div>';
		return;
	}

	if ($column === 'title') {
		$excerpt = (string) \get_post_meta($post_id, cmx_emails_meta_key('body_plain'), true);
		if ($excerpt === '') {
			$excerpt = (string) \get_post($post_id)->post_excerpt;
		}
		$edit_url = \get_edit_post_link($post_id, '');
		$title = '<strong>' . \esc_html(cmx_emails_admin_subject_label($post_id)) . '</strong>';
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

	if ($column === 'cmx_email_category') {
		$tax = cmx_emails_category_taxonomy();
		if (!\taxonomy_exists($tax)) {
			echo '';
			return;
		}

		$terms = \get_the_terms($post_id, $tax);
		if (\is_wp_error($terms) || empty($terms)) {
			echo '';
			return;
		}

		echo \esc_html(\implode(', ', \wp_list_pluck($terms, 'name')));
		return;
	}

	if ($column === 'cmx_email_assignment') {
		$attachment_count = (int) \get_post_meta($post_id, cmx_emails_meta_key('attachment_count'), true);
		$label = (string) \get_post_meta($post_id, cmx_emails_meta_key('assignment_label'), true);
		if ($attachment_count > 0) {
			echo '<div class="cmx-email-admin-attachments">📎 ' . (int) $attachment_count . '</div>';
		}
		echo cmx_emails_admin_assignment_html($label);
	}
}, 10, 2);

\add_filter('post_row_actions', function (array $actions, \WP_Post $post): array {
	if ($post->post_type !== CMX_EMAILS_CPT) {
		return $actions;
	}

	if ((string) $post->post_status === 'trash') {
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
	$actions['delete'] = '<a href="' . \esc_url($delete_url) . '" class="submitdelete">L&ouml;schen</a>';

	return $actions;
}, 10, 2);

\add_filter('views_edit-' . CMX_EMAILS_CPT, function (array $views): array {
	$filters = cmx_emails_current_filters();
	$base_filters = [
		'account_id' => (string) ($filters['account_id'] ?? ''),
		'folder' => (string) ($filters['folder'] ?? ''),
		'category' => (string) ($filters['category'] ?? ''),
		'archive_year' => (string) ($filters['archive_year'] ?? ''),
		'archive_month' => (string) ($filters['archive_month'] ?? ''),
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
	$trash_count = cmx_emails_count(\array_merge($base_filters, ['post_status' => 'trash']));
	if ($trash_count > 0 || $view === 'trash') {
		$defs['trash'] = [
			'label' => 'Papierkorb',
			'filters' => ['post_status' => 'trash'],
			'url_args' => ['post_status' => 'trash'],
		];
	}

	$args_keep = [];
	foreach (['cmx_email_account', 'cmx_email_folder', 'cmx_email_status', 'cmx_email_category', 'cmx_email_archive_year', 'cmx_email_archive_month', 's'] as $key) {
		if (isset($_GET[$key])) {
			$args_keep[$key] = \sanitize_text_field((string) \wp_unslash($_GET[$key]));
		}
	}

	$views = [];
	foreach ($defs as $key => $def) {
		$count = cmx_emails_count(\array_merge($base_filters, (array) ($def['filters'] ?? [])));
		$url = cmx_emails_admin_list_url(\array_merge($args_keep, [
			'cmx_email_view' => $key,
		], (array) ($def['url_args'] ?? [])));
		$current = $view === $key ? ' class="current" aria-current="page"' : '';
		$views[$key] = '<a href="' . \esc_url($url) . '"' . $current . '>' . \esc_html((string) $def['label']) . ' <span class="count">(' . (int) $count . ')</span></a>';
	}

	return $views;
});

\add_filter('months_dropdown_results', function ($months, $post_type) {
	if ((string) $post_type === CMX_EMAILS_CPT) {
		return [];
	}

	return $months;
}, 10, 2);

\add_action('restrict_manage_posts', function ($post_type = '', $which = 'top'): void {
	if (!cmx_emails_admin_list_active()) {
		return;
	}
	if ((string) $which !== 'top') {
		return;
	}

	$filters = cmx_emails_current_filters();
	$status = \sanitize_key((string) ($filters['status'] ?? ''));
	$folder = \sanitize_key((string) ($filters['folder'] ?? ''));
	$account = \sanitize_key((string) ($filters['account_id'] ?? ''));
	$category = \sanitize_title((string) ($filters['category'] ?? ''));
	$archive_year = \preg_replace('/[^0-9]/', '', (string) ($filters['archive_year'] ?? ''));
	$archive_month = \function_exists(__NAMESPACE__ . '\\cmx_emails_normalize_archive_month')
		? cmx_emails_normalize_archive_month((string) ($filters['archive_month'] ?? ''))
		: \preg_replace('/[^0-9]/', '', (string) ($filters['archive_month'] ?? ''));
	$view = cmx_emails_current_view();

	if ($view === 'trash') {
		echo '<input type="hidden" name="post_status" value="trash">';
	}

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

	$archive_year_options = \function_exists(__NAMESPACE__ . '\\cmx_emails_archive_year_options')
		? (array) cmx_emails_archive_year_options($account)
		: [];
	echo '<select name="cmx_email_archive_year">';
	echo '<option value="">' . \esc_html__('Jahr wählen', 'cmx') . '</option>';
	foreach ($archive_year_options as $year_value => $year_label) {
		echo '<option value="' . \esc_attr((string) $year_value) . '"' . \selected($archive_year, (string) $year_value, false) . '>' . \esc_html((string) $year_label) . '</option>';
	}
	echo '</select>';

	$archive_month_options = \function_exists(__NAMESPACE__ . '\\cmx_emails_archive_month_options')
		? (array) cmx_emails_archive_month_options($archive_year, $account)
		: [];
	echo '<select name="cmx_email_archive_month">';
	echo '<option value="">' . \esc_html__('Monat wählen', 'cmx') . '</option>';
	foreach ($archive_month_options as $month_value => $month_label) {
		echo '<option value="' . \esc_attr((string) $month_value) . '"' . \selected($archive_month, (string) $month_value, false) . '>' . \esc_html((string) $month_label) . '</option>';
	}
	echo '</select>';

	echo '<select name="cmx_email_status">';
	echo '<option value="">Alle Status</option>';
	foreach (['new' => 'Neu', 'read' => 'Gelesen', 'processed' => 'Verarbeitet'] as $value => $label) {
		echo '<option value="' . \esc_attr($value) . '"' . \selected($status, $value, false) . '>' . \esc_html($label) . '</option>';
	}
	echo '</select>';

	$category_tax = cmx_emails_category_taxonomy();
	if (\taxonomy_exists($category_tax) && \is_object_in_taxonomy(CMX_EMAILS_CPT, $category_tax)) {
		\wp_dropdown_categories([
			'show_option_all' => 'Alle Kategorien',
			'taxonomy' => $category_tax,
			'name' => 'cmx_email_category',
			'orderby' => 'name',
			'selected' => $category,
			'hierarchical' => true,
			'show_count' => false,
			'hide_empty' => false,
			'value_field' => 'slug',
		]);
	}

		echo '<span class="cmx-email-filter-actions">';
		$sync_folder = $folder !== '' ? $folder : 'inbox';
		if ($sync_folder === 'archive') {
			$sync_folder = 'inbox';
		}
		$sync_args = [
			'action' => 'cmx_emails_sync',
			'sync_folder' => $sync_folder,
		];
		if ($folder !== '') {
			$sync_args['folder'] = $folder;
		}
		if ($account !== '') {
			$sync_args['account_id'] = $account;
		}
		if ($archive_year !== '') {
			$sync_args['archive_year'] = $archive_year;
		}
		if ($archive_month !== '') {
			$sync_args['archive_month'] = $archive_month;
		}
		$sync_url = \wp_nonce_url(\add_query_arg($sync_args, \admin_url('admin-post.php')), 'cmx_emails_sync');
		echo '<a class="button" href="' . \esc_url($sync_url) . '">Synchronisieren</a>';
	echo '<a class="button" href="' . \esc_url(cmx_emails_settings_url()) . '">Einstellungen</a>';
	echo '</span>';
}, 10, 2);

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
	$query->set('post_status', 'publish');

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
	} elseif ($view === 'trash') {
		$filters['post_status'] = 'trash';
		$query->set('post_status', 'trash');
	}

	$meta_query = cmx_emails_build_meta_query($filters);
	if ($meta_query !== []) {
		$query->set('meta_query', $meta_query);
	}

	$tax_query = \function_exists(__NAMESPACE__ . '\\cmx_emails_build_tax_query')
		? cmx_emails_build_tax_query($filters)
		: [];
	if ($tax_query !== []) {
		$query->set('tax_query', $tax_query);
	}

	$orderby = isset($_GET['orderby']) ? \sanitize_key((string) \wp_unslash($_GET['orderby'])) : '';
	if ($orderby === '' || $orderby === 'cmx_email_date') {
		$query->set('meta_key', cmx_emails_meta_key('received_ts'));
		$query->set('orderby', 'meta_value_num');
		$query->set('order', isset($_GET['order']) ? \sanitize_key((string) \wp_unslash($_GET['order'])) : 'DESC');
	}

	$max_messages = \function_exists(__NAMESPACE__ . '\\cmx_emails_message_limit')
		? cmx_emails_message_limit()
		: 500;
	$query->set('posts_per_page', $max_messages);
	$query->set('paged', 1);
});

\add_action('admin_head-edit.php', function (): void {
	if (!cmx_emails_admin_list_active()) {
		return;
	}
	?>
	<style>
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .column-cmx_email_folder {
			width: 110px;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .wrap .page-title-action {
			border-radius: 8px;
		}
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
			border: 1px solid #e6ebf0;
			background: #fff;
			color: #2c3338;
			font-size: 12px;
			font-weight: 600;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-new {
			border-color: #cfe0f0;
			background: #f7fbff;
			color: #135e96;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-read {
			border-color: #d3e7d3;
			background: #f7fcf7;
			color: #2f7d32;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-processed {
			border-color: #e6ebf0;
			background: #fbfcfd;
			color: #5b6673;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-folder-badge {
			justify-content: flex-start;
			min-width: 0;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-folder-inbox {
			border-color: #cfe0f0;
			background: #f7fbff;
			color: #135e96;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-folder-sent {
			border-color: #d3e7d3;
			background: #f7fcf7;
			color: #2f7d32;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-folder-drafts {
			border-color: #ead9b7;
			background: #fffaf2;
			color: #9a6700;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-folder-archive {
			border-color: #e6ebf0;
			background: #fbfcfd;
			color: #5b6673;
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
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .tablenav .button,
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .tablenav input.button,
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .tablenav button.button,
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .tablenav select,
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .search-box input[type="search"],
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .search-box input[type="submit"] {
			border-radius: 8px;
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
