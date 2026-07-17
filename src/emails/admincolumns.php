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
		return \in_array($view, ['all', 'new', 'read', 'attachment', 'unassigned', 'processed', 'drafts', 'spam', 'trash'], true) ? $view : 'all';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_current_filters')) {
	function cmx_emails_current_filters(): array {
		$category = isset($_GET['cmx_email_category']) ? (string) \wp_unslash($_GET['cmx_email_category']) : '';
		$category = $category === '0' ? '' : \sanitize_title($category);
		$folder_is_explicit = isset($_GET['cmx_email_folder']) && !\is_array($_GET['cmx_email_folder']);

		$filters = [
			'account_id' => isset($_GET['cmx_email_account']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_email_account'])) : '',
			'folder' => $folder_is_explicit ? \sanitize_key((string) \wp_unslash($_GET['cmx_email_folder'])) : 'inbox',
			'status' => isset($_GET['cmx_email_status']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_email_status'])) : '',
			'category' => $category,
			'archive_year' => isset($_GET['cmx_email_archive_year']) ? \preg_replace('/[^0-9]/', '', (string) \wp_unslash($_GET['cmx_email_archive_year'])) : '',
			'archive_month' => isset($_GET['cmx_email_archive_month']) ? cmx_emails_normalize_archive_month((string) \wp_unslash($_GET['cmx_email_archive_month'])) : '',
		];

		$archive_selected = (string) $filters['archive_year'] !== '' || (string) $filters['archive_month'] !== '';
		if ($archive_selected && (!$folder_is_explicit || \in_array((string) $filters['folder'], ['', 'archive'], true))) {
			$filters['folder'] = 'archive';
		}
		if ((string) $filters['folder'] !== 'archive') {
			$filters['archive_year'] = '';
			$filters['archive_month'] = '';
		}

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
		$subject = \function_exists(__NAMESPACE__ . '\\cmx_emails_subject_text')
			? cmx_emails_subject_text((string) \get_post_meta($post_id, cmx_emails_meta_key('subject'), true))
			: \trim((string) \get_post_meta($post_id, cmx_emails_meta_key('subject'), true));
		if ($subject !== '') {
			return $subject;
		}

		$post = \get_post($post_id);
		$post_title = $post instanceof \WP_Post
			? (\function_exists(__NAMESPACE__ . '\\cmx_emails_subject_text') ? cmx_emails_subject_text((string) $post->post_title) : \trim((string) $post->post_title))
			: '';
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
		if ($folder === 'spam') {
			return 'is-folder-spam';
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_admin_search_matches_query')) {
	function cmx_emails_admin_search_matches_query(\WP_Query $query): bool {
		if (!\is_admin() || !$query->is_main_query()) {
			return false;
		}

		$post_type = $query->get('post_type');
		return $post_type === CMX_EMAILS_CPT
			|| (\is_array($post_type) && \in_array(CMX_EMAILS_CPT, $post_type, true))
			|| ($post_type === null && cmx_emails_admin_list_active());
	}
}

\add_filter('posts_search', function (string $search, \WP_Query $query): string {
	if (!cmx_emails_admin_search_matches_query($query)) {
		return $search;
	}

	$term = \trim((string) $query->get('s'));
	if ($term === '') {
		return $search;
	}

	$tax = cmx_emails_category_taxonomy();
	if (!\taxonomy_exists($tax) || !\is_object_in_taxonomy(CMX_EMAILS_CPT, $tax)) {
		return $search;
	}

	global $wpdb;
	$like = '%' . $wpdb->esc_like($term) . '%';
	$category_sql = $wpdb->prepare(
		"EXISTS (
			SELECT 1
			FROM {$wpdb->term_relationships} AS cmx_email_search_tr
			INNER JOIN {$wpdb->term_taxonomy} AS cmx_email_search_tt
				ON cmx_email_search_tt.term_taxonomy_id = cmx_email_search_tr.term_taxonomy_id
			INNER JOIN {$wpdb->terms} AS cmx_email_search_t
				ON cmx_email_search_t.term_id = cmx_email_search_tt.term_id
			WHERE cmx_email_search_tr.object_id = {$wpdb->posts}.ID
				AND cmx_email_search_tt.taxonomy = %s
				AND (
					cmx_email_search_t.name LIKE %s
					OR cmx_email_search_t.slug LIKE %s
				)
		)",
		$tax,
		$like,
		$like
	);

	$search_sql = \trim((string) $search);
	$search_sql = (string) \preg_replace('/^\s*AND\s*/i', '', $search_sql);
	if ($search_sql === '') {
		return ' AND (' . $category_sql . ')';
	}

	return ' AND ((' . $search_sql . ') OR (' . $category_sql . '))';
}, 20, 2);

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
	$new['cmx_email_sender_domain'] = 'Domain';
	$new['cmx_email_spf'] = 'SPF';
	$new['cmx_email_dkim'] = 'DKIM';
	$new['cmx_email_dmarc'] = 'DMARC';
	$new['cmx_email_filter_status'] = 'Filterstatus';
	$new['cmx_email_folder'] = 'Ordner';
	$new['cmx_email_account'] = 'Konto';
	$new['cmx_email_status'] = 'Status';
	$new['cmx_email_category'] = 'Kategorie';
	$new['cmx_email_assignment'] = 'Zuordnung';
	$new['cmx_email_date'] = 'Datum';
	$new['cmx_email_actions'] = '';
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

	if ($column === 'cmx_email_sender_domain') {
		$domain = (string) \get_post_meta($post_id, '_cmx_email_sender_domain', true);
		if ($domain === '') {
			$domain = \function_exists(__NAMESPACE__ . '\\cmx_email_filter_sender_domain')
				? cmx_email_filter_sender_domain((string) \get_post_meta($post_id, cmx_emails_meta_key('sender_email'), true))
				: '';
		}
		echo \esc_html($domain);
		return;
	}

	if (\in_array($column, ['cmx_email_spf', 'cmx_email_dkim', 'cmx_email_dmarc'], true)) {
		$map = [
			'cmx_email_spf' => '_cmx_email_spf_result',
			'cmx_email_dkim' => '_cmx_email_dkim_result',
			'cmx_email_dmarc' => '_cmx_email_dmarc_result',
		];
		$value = \sanitize_key((string) \get_post_meta($post_id, $map[$column], true));
		$value = $value !== '' ? $value : 'unknown';
		echo '<span class="cmx-email-badge cmx-email-auth-badge is-auth-' . \esc_attr($value) . '">' . \esc_html($value) . '</span>';
		return;
	}

	if ($column === 'cmx_email_filter_status') {
		$status = \function_exists(__NAMESPACE__ . '\\cmx_email_filter_normalize_status')
			? cmx_email_filter_normalize_status((string) \get_post_meta($post_id, '_cmx_email_filter_status', true))
			: \sanitize_key((string) \get_post_meta($post_id, '_cmx_email_filter_status', true));
		$labels = \function_exists(__NAMESPACE__ . '\\cmx_email_filter_statuses') ? cmx_email_filter_statuses() : [];
		echo '<span class="cmx-email-badge cmx-email-filter-badge is-filter-' . \esc_attr($status) . '">' . \esc_html((string) ($labels[$status] ?? $status)) . '</span>';
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
		return;
	}

	if ($column === 'cmx_email_actions') {
		if ((string) \get_post_status($post_id) === 'trash') {
			return;
		}

		$folder = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('folder'), true));
		if ($folder === 'spam') {
			return;
		}

		$filters = cmx_emails_current_filters();
		$spam_args = [
			'action' => 'cmx_emails_spam',
			'post_id' => $post_id,
		];
		if ((string) ($filters['account_id'] ?? '') !== '') {
			$spam_args['account_id'] = (string) $filters['account_id'];
		}
		if ((string) ($filters['folder'] ?? '') !== '') {
			$spam_args['folder'] = (string) $filters['folder'];
		}
		if ((string) ($filters['archive_year'] ?? '') !== '') {
			$spam_args['archive_year'] = (string) $filters['archive_year'];
		}
		if ((string) ($filters['archive_month'] ?? '') !== '') {
			$spam_args['archive_month'] = (string) $filters['archive_month'];
		}

		$spam_url = \wp_nonce_url(\add_query_arg($spam_args, \admin_url('admin-post.php')), 'cmx_emails_spam');
		echo '<a class="cmx-email-admin-spam-link" href="' . \esc_url($spam_url) . '" title="In Spam verschieben" aria-label="In Spam verschieben">Spam</a>';
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

	$pdf_attachments = \function_exists(__NAMESPACE__ . '\\cmx_emails_pdf_attachments_for_post')
		? cmx_emails_pdf_attachments_for_post((int) $post->ID)
		: [];
	if ($pdf_attachments !== []) {
		$import_url = \wp_nonce_url(\add_query_arg([
			'action' => 'cmx_emails_import',
			'post_id' => (int) $post->ID,
		], \admin_url('admin-post.php')), 'cmx_emails_import');
		$actions['import'] = '<a href="' . \esc_url($import_url) . '">Als Beleg &uuml;bernehmen</a>';
	}

	$folder = \sanitize_key((string) \get_post_meta($post->ID, cmx_emails_meta_key('folder'), true));
	if ($folder === 'spam') {
		$filters = cmx_emails_current_filters();
		$not_spam_args = [
			'action' => 'cmx_emails_not_spam',
			'post_id' => (int) $post->ID,
		];
		if ((string) ($filters['account_id'] ?? '') !== '') {
			$not_spam_args['account_id'] = (string) $filters['account_id'];
		}
		if ((string) ($filters['folder'] ?? '') !== '') {
			$not_spam_args['folder'] = (string) $filters['folder'];
		}
		if ((string) ($filters['archive_year'] ?? '') !== '') {
			$not_spam_args['archive_year'] = (string) $filters['archive_year'];
		}
		if ((string) ($filters['archive_month'] ?? '') !== '') {
			$not_spam_args['archive_month'] = (string) $filters['archive_month'];
		}
		$not_spam_url = \wp_nonce_url(\add_query_arg($not_spam_args, \admin_url('admin-post.php')), 'cmx_emails_not_spam');
		$actions['not_spam'] = '<a href="' . \esc_url($not_spam_url) . '">Kein Spam</a>';
	}

	if (\current_user_can('manage_options') && \function_exists(__NAMESPACE__ . '\\cmx_email_filter_action_url')) {
		$actions['allow_sender'] = '<a href="' . \esc_url(cmx_email_filter_action_url((int) $post->ID, 'cmx_email_allow_sender')) . '">Absender zulassen</a>';
		$actions['mark_spam'] = '<a href="' . \esc_url(cmx_email_filter_action_url((int) $post->ID, 'cmx_email_mark_spam')) . '">Als Spam markieren</a>';
	}

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
			'drafts' => ['label' => 'Entwürfe', 'filters' => ['folder' => 'drafts'], 'url_args' => ['cmx_email_folder' => 'drafts']],
			'spam' => ['label' => 'Spam', 'filters' => ['folder' => 'spam'], 'url_args' => ['cmx_email_folder' => 'spam']],
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
	$archive_filter_map = [];

	if ($view === 'trash') {
		echo '<input type="hidden" name="post_status" value="trash">';
	}

	echo '<select name="cmx_email_account" id="cmx-email-account-filter">';
	echo '<option value="">Alle Konten</option>';
	$client_rows = (array) cmx_emails_client_list();
	foreach ($client_rows as $client) {
		$client = (array) $client;
		$id = \sanitize_key((string) ($client['id'] ?? ''));
		echo '<option value="' . \esc_attr($id) . '"' . \selected($account, $id, false) . '>' . \esc_html(cmx_emails_client_label($client)) . '</option>';
	}
	echo '</select>';

	echo '<select name="cmx_email_folder" id="cmx-email-folder-filter">';
	echo '<option value="">Alle Ordner</option>';
	foreach (cmx_emails_folder_map() as $folder_key => $data) {
		echo '<option value="' . \esc_attr($folder_key) . '"' . \selected($folder, $folder_key, false) . '>' . \esc_html((string) ($data['label'] ?? $folder_key)) . '</option>';
	}
	echo '</select>';

	$archive_filter_map = \function_exists(__NAMESPACE__ . '\\cmx_emails_archive_filter_option_map')
		? (array) cmx_emails_archive_filter_option_map($client_rows)
		: [];

	$archive_year_options = (array) (($archive_filter_map[$account]['years'] ?? null) ?: ($archive_filter_map['']['years'] ?? []));
	echo '<select name="cmx_email_archive_year" id="cmx-email-archive-year-filter">';
	echo '<option value="">' . \esc_html__('Jahr wählen', 'cmx') . '</option>';
	foreach ($archive_year_options as $year_value => $year_label) {
		echo '<option value="' . \esc_attr((string) $year_value) . '"' . \selected($archive_year, (string) $year_value, false) . '>' . \esc_html((string) $year_label) . '</option>';
	}
	echo '</select>';

	$archive_month_options = (array) (($archive_filter_map[$account]['months'][$archive_year] ?? null) ?: ($archive_filter_map['']['months'][$archive_year] ?? []));
	echo '<select name="cmx_email_archive_month" id="cmx-email-archive-month-filter">';
	echo '<option value="">' . \esc_html__('Monat wählen', 'cmx') . '</option>';
	foreach ($archive_month_options as $month_value => $month_label) {
		echo '<option value="' . \esc_attr((string) $month_value) . '"' . \selected($archive_month, (string) $month_value, false) . '>' . \esc_html((string) $month_label) . '</option>';
	}
	echo '</select>';
	echo '<script type="application/json" id="cmx-email-archive-filter-map">' . \wp_json_encode($archive_filter_map) . '</script>';

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

	$archive_sync_selected = $folder === 'archive' || $archive_year !== '' || $archive_month !== '';
	$show_reset_button = \function_exists(__NAMESPACE__ . '\\cmx_system_is_debug_mode_enabled')
		&& cmx_system_is_debug_mode_enabled();
	echo '<span class="cmx-email-filter-actions">';
	$sync_folder = $folder !== '' ? $folder : 'inbox';
	if ($sync_folder === 'spam') {
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
	echo '<a class="button cmx-email-sync-button" href="' . \esc_url($sync_url) . '">' . \esc_html($archive_sync_selected ? 'Synchronisieren' : 'Synchronisieren') . '</a>';

	if ($show_reset_button && \current_user_can('delete_posts')) {
		$reset_url = \wp_nonce_url(\add_query_arg([
			'action' => 'cmx_emails_reset_local',
		], \admin_url('admin-post.php')), 'cmx_emails_reset_local');
		$reset_confirm = 'Wirklich alle lokalen E-Mail-Posts loeschen? Die Mails im IMAP-Konto bleiben unveraendert und koennen danach wieder synchronisiert werden.';
		echo '<a class="button cmx-email-reset-button" href="' . \esc_url($reset_url) . '" onclick="return confirm(' . \wp_json_encode($reset_confirm) . ');">Reset lokal</a>';
	}

	echo '<a class="button cmx-email-settings-button" href="' . \esc_url(cmx_emails_settings_url()) . '" aria-label="Einstellungen" title="Einstellungen"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span></a>';
	echo '</span>';
	if ($archive_sync_selected) {
		// echo '<div class="cmx-email-archive-hint">Die Jahres- und Monatszahlen stammen direkt aus IMAP. In der Liste erscheinen Archiv-Mails erst nach dem Synchronisieren.</div>';
	}
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
	$query->set('suppress_filters', \trim((string) $query->get('s')) === '');

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
		} elseif ($view === 'spam') {
			$filters['folder'] = 'spam';
		} elseif ($view === 'drafts') {
			$filters['folder'] = 'drafts';
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
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .column-cmx_email_actions {
			width: 64px;
			text-align: center;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-admin-spam-link {
			color: #b42318;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			text-decoration: none;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-admin-spam-link:hover {
			color: #8a1f17;
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
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-folder-spam {
			border-color: #f0c9c9;
			background: #fff5f5;
			color: #b42318;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-folder-archive {
			border-color: #e6ebf0;
			background: #fbfcfd;
			color: #5b6673;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-auth-badge {
			text-transform: uppercase;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-auth-pass,
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-filter-posteingang {
			border-color: #d3e7d3;
			background: #f7fcf7;
			color: #2f7d32;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-auth-fail,
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-filter-spam {
			border-color: #f0c9c9;
			background: #fff5f5;
			color: #b42318;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-filter-pruefen {
			border-color: #ead9b7;
			background: #fffaf2;
			color: #9a6700;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-auth-unknown,
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-auth-softfail,
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-auth-neutral,
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-auth-none,
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-auth-temperror,
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-auth-permerror {
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
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-archive-hint {
			margin: 8px 0 0;
			color: #64748b;
			font-size: 12px;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-search-tools {
			display: flex;
			align-items: center;
			justify-content: flex-end;
			gap: 8px;
			margin: 0 0 10px;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-search-tools .cmx-email-filter-actions {
			order: 0;
			margin-left: 0;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-search-tools .search-box {
			order: 1;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-search-tools .search-box {
			float: none;
			margin: 0;
			padding: 0;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-search-tools .button {
			border-radius: 8px;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-reset-button {
			border-color: #f1b8b5;
			background: #fff7f7;
			color: #b42318;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-settings-button {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 44px;
			height: 32px;
			min-width: 44px;
			min-height: 32px;
			padding: 0;
			line-height: 1;
			box-sizing: border-box;
			color: #b32d2e;
			border-color: #b32d2e;
			text-decoration: none;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-settings-button .dashicons {
			display: block;
			width: 22px;
			height: 22px;
			margin: 0;
			font-size: 22px;
			line-height: 22px;
			position: static;
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
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const mapNode = document.getElementById('cmx-email-archive-filter-map');
			const accountSelect = document.getElementById('cmx-email-account-filter');
			const folderSelect = document.getElementById('cmx-email-folder-filter');
			const yearSelect = document.getElementById('cmx-email-archive-year-filter');
			const monthSelect = document.getElementById('cmx-email-archive-month-filter');
			const emptyListRow = document.querySelector(
				'.post-type-<?php echo \esc_js(CMX_EMAILS_CPT); ?> .wp-list-table .no-items, ' +
				'.post-type-<?php echo \esc_js(CMX_EMAILS_CPT); ?> .wp-list-table td.colspanchange'
			);
			if (emptyListRow && /keine|keinen|not found|no items/i.test(emptyListRow.textContent || '')) {
				document.body.classList.add('cmx-email-list-empty');
			}
			if (!mapNode || !accountSelect || !yearSelect || !monthSelect || !folderSelect) {
				return;
			}

			let filterMap = {};
			try {
				filterMap = JSON.parse(mapNode.textContent || '{}');
			} catch (error) {
				filterMap = {};
			}

			const placeholders = {
				year: 'Jahr wählen',
				month: 'Monat wählen',
			};

			function replaceOptions(select, options, placeholder, selectedValue) {
				const wanted = String(selectedValue || '');
				select.innerHTML = '';

				const placeholderOption = document.createElement('option');
				placeholderOption.value = '';
				placeholderOption.textContent = placeholder;
				select.appendChild(placeholderOption);

				Object.entries(options || {}).forEach(([value, label]) => {
					const option = document.createElement('option');
					option.value = String(value);
					option.textContent = String(label);
					if (String(value) === wanted) {
						option.selected = true;
					}
					select.appendChild(option);
				});

				if (wanted === '') {
					select.value = '';
				} else if (!Array.from(select.options).some((option) => option.value === wanted)) {
					select.value = '';
				}
			}

			function currentAccountMap() {
				const accountId = String(accountSelect.value || '');
				return filterMap[accountId] || filterMap[''] || { years: {}, months: {} };
			}

			function refreshYearOptions(preserveSelection) {
				const selectedYear = preserveSelection ? String(yearSelect.value || '') : '';
				const accountMap = currentAccountMap();
				replaceOptions(yearSelect, accountMap.years || {}, placeholders.year, selectedYear);
			}

			function refreshMonthOptions(preserveSelection) {
				const selectedMonth = preserveSelection ? String(monthSelect.value || '') : '';
				const accountMap = currentAccountMap();
				const selectedYear = String(yearSelect.value || '');
				const monthOptions = (accountMap.months && accountMap.months[selectedYear]) ? accountMap.months[selectedYear] : {};
				replaceOptions(monthSelect, monthOptions, placeholders.month, selectedMonth);
			}

			function syncFolderWithArchiveSelection() {
				const hasArchiveSelection = String(yearSelect.value || '') !== '' || String(monthSelect.value || '') !== '';
				if (hasArchiveSelection) {
					folderSelect.value = 'archive';
				} else if (String(folderSelect.value || '') === 'archive') {
					folderSelect.value = '';
				}
			}

			function clearArchiveSelection() {
				if (String(yearSelect.value || '') !== '') {
					yearSelect.value = '';
				}
				refreshMonthOptions(false);
				monthSelect.value = '';
			}

			accountSelect.addEventListener('change', function () {
				refreshYearOptions(true);
				refreshMonthOptions(true);
				syncFolderWithArchiveSelection();
			});

			folderSelect.addEventListener('change', function () {
				if (String(folderSelect.value || '') !== 'archive') {
					clearArchiveSelection();
				}
			});

			yearSelect.addEventListener('change', function () {
				refreshMonthOptions(false);
				syncFolderWithArchiveSelection();
			});

				monthSelect.addEventListener('change', function () {
					syncFolderWithArchiveSelection();
				});

				document.addEventListener('click', function (event) {
					const syncButton = event.target && event.target.closest ? event.target.closest('.cmx-email-sync-button') : null;
					if (!syncButton) {
						return;
					}

					const accountId = String(accountSelect.value || '');
					if (accountId === '') {
						event.preventDefault();
						window.alert('Bitte zuerst ein Konto auswaehlen. Es werden nicht automatisch alles synchronisiert.');
						return;
					}

					const url = new URL(syncButton.href, window.location.href);
					const folder = String(folderSelect.value || '');
					let syncFolder = folder !== '' ? folder : 'inbox';
					if (syncFolder === 'spam') {
						syncFolder = 'inbox';
					}

					url.searchParams.set('account_id', accountId);
					url.searchParams.set('sync_folder', syncFolder);
					if (folder !== '') {
						url.searchParams.set('folder', folder);
					} else {
						url.searchParams.delete('folder');
					}
					if (String(yearSelect.value || '') !== '') {
						url.searchParams.set('archive_year', String(yearSelect.value || ''));
					} else {
						url.searchParams.delete('archive_year');
					}
					if (String(monthSelect.value || '') !== '') {
						url.searchParams.set('archive_month', String(monthSelect.value || ''));
					} else {
						url.searchParams.delete('archive_month');
					}
					syncButton.href = url.toString();
				});

				const actionBar = document.querySelector('.post-type-<?php echo \esc_js(CMX_EMAILS_CPT); ?> .cmx-email-filter-actions');
				const searchBox = document.querySelector('.post-type-<?php echo \esc_js(CMX_EMAILS_CPT); ?> .search-box');
			if (actionBar && searchBox && searchBox.parentNode) {
				let toolbar = searchBox.previousElementSibling;
				if (!toolbar || !toolbar.classList.contains('cmx-email-search-tools')) {
					toolbar = document.createElement('div');
					toolbar.className = 'cmx-email-search-tools';
					searchBox.parentNode.insertBefore(toolbar, searchBox);
				}
				if (actionBar.parentNode !== toolbar) {
					toolbar.appendChild(actionBar);
				}
				if (searchBox.parentNode !== toolbar || toolbar.lastElementChild !== searchBox) {
					toolbar.appendChild(searchBox);
				}
			}

			syncFolderWithArchiveSelection();
		});
	</script>
	<?php
});
