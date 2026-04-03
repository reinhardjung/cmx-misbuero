<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_edit_screen_active')) {
	function cmx_emails_edit_screen_active(): bool {
		if (!\is_admin()) {
			return false;
		}

		$current_script = \basename((string) ($_SERVER['PHP_SELF'] ?? ''));
		if (!\in_array($current_script, ['post.php', 'post-new.php'], true)) {
			return false;
		}

		$post_id = isset($_GET['post']) ? (int) \wp_unslash($_GET['post']) : 0;
		if ($post_id > 0) {
			return (string) \get_post_type($post_id) === CMX_EMAILS_CPT;
		}

		$post_type = isset($_GET['post_type']) ? \sanitize_key((string) \wp_unslash($_GET['post_type'])) : '';
		return $post_type === CMX_EMAILS_CPT;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_is_auto_draft_title')) {
	function cmx_emails_is_auto_draft_title(string $value): bool {
		$value = \trim((string) $value);
		if ($value === '') {
			return true;
		}

		$normalized = \function_exists('mb_strtolower')
			? (string) \mb_strtolower($value, 'UTF-8')
			: (string) \strtolower($value);
		$normalized = \str_replace(['_', ' '], '-', $normalized);

		return $normalized === 'auto-draft'
			|| \str_contains($normalized, 'automatisch-gespeicherter-entwurf')
			|| \str_contains($normalized, 'auto-draft');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_missing_subject_label')) {
	function cmx_emails_missing_subject_label(): string {
		return 'Betreffzeile fehlt';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_recheck_post_for_spam')) {
	function cmx_emails_recheck_post_for_spam(int $post_id): void {
		$post_id = (int) $post_id;
		if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_EMAILS_CPT) {
			return;
		}
	if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_existing_post_spam_analysis')) {
		return;
	}
	if (\function_exists(__NAMESPACE__ . '\\cmx_emails_is_manual_ham_post') && cmx_emails_is_manual_ham_post($post_id)) {
		\update_post_meta($post_id, cmx_emails_meta_key('spam_status'), 'clean');
		\update_post_meta($post_id, cmx_emails_meta_key('spam_score'), '0');
		\update_post_meta($post_id, cmx_emails_meta_key('spam_reasons'), []);
		return;
	}

	$folder = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('folder'), true));
	$direction = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('direction'), true));
	if ($direction === 'outgoing' || \in_array($folder, ['sent', 'drafts', 'spam'], true)) {
		return;
		}

		$analysis = cmx_emails_existing_post_spam_analysis($post_id);
		$spam_status = \sanitize_key((string) ($analysis['status'] ?? ''));
		$spam_score = \max(0, (int) ($analysis['score'] ?? 0));
		$spam_reasons = \array_values(\array_filter(\array_map('sanitize_text_field', (array) ($analysis['reasons'] ?? []))));

	\update_post_meta($post_id, cmx_emails_meta_key('spam_status'), $spam_status);
	\update_post_meta($post_id, cmx_emails_meta_key('spam_score'), (string) $spam_score);
	\update_post_meta($post_id, cmx_emails_meta_key('spam_reasons'), $spam_reasons);

	if ($spam_status !== 'spam') {
		return;
	}
	if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_move_existing_post_to_spam')) {
		return;
		}
		cmx_emails_move_existing_post_to_spam($post_id);
	}
}

\add_action('current_screen', function ($screen = null): void {
	if (!cmx_emails_edit_screen_active()) {
		return;
	}

	$post_id = isset($_GET['post']) ? (int) \wp_unslash($_GET['post']) : 0;
	if ($post_id > 0) {
		cmx_emails_recheck_post_for_spam($post_id);
	}
});

\add_filter('wp_insert_post_data', function (array $data, array $postarr): array {
	$post_type = \sanitize_key((string) ($data['post_type'] ?? $postarr['post_type'] ?? ''));
	if ($post_type !== CMX_EMAILS_CPT) {
		return $data;
	}

	$status = \sanitize_key((string) ($data['post_status'] ?? ''));
	if ($status === 'auto-draft') {
		return $data;
	}
	if ($status !== 'trash') {
		$data['post_status'] = 'publish';
	}

	$title = \trim((string) ($data['post_title'] ?? ''));
	if (!cmx_emails_is_auto_draft_title($title)) {
		return $data;
	}

	$data['post_title'] = cmx_emails_missing_subject_label();
	return $data;
}, 20, 2);

\add_filter('default_title', function (string $title, \WP_Post $post): string {
	if ($post->post_type !== CMX_EMAILS_CPT || !cmx_emails_is_compose_post($post)) {
		return $title;
	}

	$prefill = cmx_emails_compose_prefill($post);
	return (string) ($prefill['subject'] ?? $title);
}, 10, 2);

\add_filter('default_content', function (string $content, \WP_Post $post): string {
	if ($post->post_type !== CMX_EMAILS_CPT || !cmx_emails_is_compose_post($post)) {
		return $content;
	}

	$prefill = cmx_emails_compose_prefill($post);
	return (string) ($prefill['body'] ?? $content);
}, 10, 2);

\add_filter('wp_editor_settings', function (array $settings, string $editor_id): array {
	if ($editor_id !== 'content' || !cmx_emails_edit_screen_active()) {
		return $settings;
	}

	$post_id = isset($_GET['post']) ? (int) \wp_unslash($_GET['post']) : 0;
	$post = $post_id > 0 ? \get_post($post_id) : null;
	if ($post instanceof \WP_Post && !cmx_emails_is_compose_post($post)) {
		return $settings;
	}

	if (($settings['tinymce'] ?? true) !== false) {
		$current_tinymce = \is_array($settings['tinymce'] ?? null) ? $settings['tinymce'] : [];
		$settings['tinymce'] = \array_merge($current_tinymce, [
			'resize'    => true,
			'statusbar' => true,
		]);
	}

	return $settings;
}, 30, 2);

\add_action('add_meta_boxes', function (string $post_type, \WP_Post $post): void {
	if ($post_type !== CMX_EMAILS_CPT) {
		return;
	}

	if (cmx_emails_is_compose_post($post)) {
		\add_meta_box(
			'cmx_email_compose',
			'E-Mail-Komposition',
			__NAMESPACE__ . '\\cmx_emails_render_compose_metabox',
			CMX_EMAILS_CPT,
			'side',
			'high'
		);

		\add_meta_box(
			'cmx_email_assignment',
			'Zuordnung',
			__NAMESPACE__ . '\\cmx_emails_render_assignment_metabox',
			CMX_EMAILS_CPT,
			'side',
			'default'
		);
		return;
	}

	\add_meta_box(
		'cmx_email_reader',
		'E-Mail-Inhalt',
		__NAMESPACE__ . '\\cmx_emails_render_reader_metabox',
		CMX_EMAILS_CPT,
		'normal',
		'high'
	);

	\add_meta_box(
		'cmx_email_details',
		'E-Mail-Details',
		__NAMESPACE__ . '\\cmx_emails_render_details_metabox',
		CMX_EMAILS_CPT,
		'side',
		'high'
	);

	\add_meta_box(
		'cmx_email_assignment',
		'Zuordnung',
		__NAMESPACE__ . '\\cmx_emails_render_assignment_metabox',
		CMX_EMAILS_CPT,
		'side',
		'default'
	);
}, 10, 2);

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_compose_query_mode')) {
	function cmx_emails_compose_query_mode(): string {
		$mode = isset($_GET['cmx_email_compose']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_email_compose'])) : '';
		return \in_array($mode, ['reply', 'forward'], true) ? $mode : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_compose_source_id')) {
	function cmx_emails_compose_source_id(\WP_Post $post): int {
		$query_source = isset($_GET['cmx_email_source']) ? (int) \wp_unslash($_GET['cmx_email_source']) : 0;
		if ($query_source > 0 && (string) \get_post_type($query_source) === CMX_EMAILS_CPT) {
			return $query_source;
		}

		$stored_source = (int) \get_post_meta($post->ID, cmx_emails_meta_key('compose_source_id'), true);
		return $stored_source > 0 && (string) \get_post_type($stored_source) === CMX_EMAILS_CPT ? $stored_source : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_is_compose_post')) {
	function cmx_emails_is_compose_post(\WP_Post $post): bool {
		if ($post->post_type !== CMX_EMAILS_CPT) {
			return false;
		}

		if (cmx_emails_compose_query_mode() !== '' || cmx_emails_compose_source_id($post) > 0) {
			return true;
		}

		$direction = \sanitize_key((string) \get_post_meta($post->ID, cmx_emails_meta_key('direction'), true));
		if ($direction === 'outgoing') {
			return true;
		}

		return $post->post_status === 'auto-draft';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_address_text_from_items')) {
	function cmx_emails_address_text_from_items(array $items): string {
		$emails = [];
		foreach ($items as $item) {
			if (!\is_array($item)) {
				continue;
			}
			$email = \sanitize_email((string) ($item['email'] ?? ''));
			if (\is_email($email)) {
				$emails[] = $email;
			}
		}
		return \implode(', ', \array_values(\array_unique($emails)));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_address_parts_from_text')) {
	function cmx_emails_address_parts_from_text(string $raw): array {
		$parts = \preg_split('/[\s,;]+/', $raw) ?: [];
		$out = [];
		foreach ($parts as $part) {
			$part = \trim((string) $part);
			if ($part === '') {
				continue;
			}
			$out[] = $part;
		}
		return \array_values($out);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_address_items_from_text')) {
	function cmx_emails_address_items_from_text(string $raw): array {
		$out = [];
		foreach (cmx_emails_address_parts_from_text($raw) as $part) {
			$email = \sanitize_email((string) $part);
			if (!\is_email($email)) {
				continue;
			}
			$key = \strtolower($email);
			$out[$key] = [
				'name' => '',
				'email' => $email,
				'label' => $email,
			];
		}
		return \array_values($out);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_invalid_address_parts')) {
	function cmx_emails_invalid_address_parts(string $raw): array {
		$out = [];
		foreach (cmx_emails_address_parts_from_text($raw) as $part) {
			$email = \sanitize_email((string) $part);
			if (\is_email($email)) {
				continue;
			}
			$key = \function_exists('mb_strtolower')
				? (string) \mb_strtolower($part, 'UTF-8')
				: (string) \strtolower($part);
			$out[$key] = $part;
		}
		return \array_values($out);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_invalid_address_notice')) {
	function cmx_emails_invalid_address_notice(array $invalid_by_field): string {
		$parts = [];
		foreach ($invalid_by_field as $label => $items) {
			$items = \array_values(\array_filter(\array_map('strval', (array) $items), static function (string $item): bool {
				return \trim($item) !== '';
			}));
			if ($items === []) {
				continue;
			}
			$parts[] = (string) $label . ': ' . \implode(', ', $items);
		}

		if ($parts === []) {
			return '';
		}

		return 'Versand blockiert. Bitte gueltige E-Mail-Adressen eintragen. Ungueltig in ' . \implode(' | ', $parts);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_address_emails')) {
	function cmx_emails_address_emails(array $items): array {
		$emails = [];
		foreach ($items as $item) {
			if (!\is_array($item)) {
				continue;
			}
			$email = \sanitize_email((string) ($item['email'] ?? ''));
			if (!\is_email($email)) {
				continue;
			}
			$emails[\strtolower($email)] = $email;
		}
		return \array_values($emails);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_notice_user_meta_key')) {
	function cmx_emails_notice_user_meta_key(int $post_id): string {
		return '_cmx_emails_edit_notice_' . (int) $post_id;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_store_edit_notice')) {
	function cmx_emails_store_edit_notice(int $post_id, string $message, string $type = 'info'): void {
		$user_id = \get_current_user_id();
		if ($post_id <= 0 || $user_id <= 0 || $message === '') {
			return;
		}

		\update_user_meta($user_id, cmx_emails_notice_user_meta_key($post_id), [
			'message' => $message,
			'type' => \in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'info',
		]);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_pull_edit_notice')) {
	function cmx_emails_pull_edit_notice(int $post_id): array {
		$user_id = \get_current_user_id();
		if ($post_id <= 0 || $user_id <= 0) {
			return ['message' => '', 'type' => 'info'];
		}

		$key = cmx_emails_notice_user_meta_key($post_id);
		$notice = \get_user_meta($user_id, $key, true);
		\delete_user_meta($user_id, $key);

		if (!\is_array($notice)) {
			return ['message' => '', 'type' => 'info'];
		}

		return [
			'message' => (string) ($notice['message'] ?? ''),
			'type' => \in_array((string) ($notice['type'] ?? 'info'), ['success', 'error', 'warning', 'info'], true)
				? (string) ($notice['type'] ?? 'info')
				: 'info',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_queue_redirect_notice')) {
	function cmx_emails_queue_redirect_notice(int $post_id, string $message, string $type = 'info', string $redirect_target = ''): void {
		if ($post_id <= 0 || $message === '') {
			return;
		}

		if (!isset($GLOBALS['cmx_emails_redirect_notices']) || !\is_array($GLOBALS['cmx_emails_redirect_notices'])) {
			$GLOBALS['cmx_emails_redirect_notices'] = [];
		}

		$GLOBALS['cmx_emails_redirect_notices'][(int) $post_id] = [
			'message' => $message,
			'type' => \in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'info',
			'redirect_target' => $redirect_target === 'list' ? 'list' : '',
		];

		if ($redirect_target !== 'list') {
			cmx_emails_store_edit_notice($post_id, $message, $type);
		}
	}
}

\add_filter('redirect_post_location', function (string $location, int $post_id): string {
	$map = isset($GLOBALS['cmx_emails_redirect_notices']) && \is_array($GLOBALS['cmx_emails_redirect_notices'])
		? $GLOBALS['cmx_emails_redirect_notices']
		: [];

	if ($post_id <= 0 || !isset($map[$post_id]) || !\is_array($map[$post_id])) {
		return $location;
	}

	$notice = $map[$post_id];
	unset($GLOBALS['cmx_emails_redirect_notices'][$post_id]);

	$notice_args = [
		'cmx_email_notice' => (string) ($notice['message'] ?? ''),
		'cmx_email_notice_type' => (string) ($notice['type'] ?? 'info'),
	];

	if ((string) ($notice['redirect_target'] ?? '') === 'list') {
		return cmx_emails_admin_list_url($notice_args);
	}

	return \add_query_arg($notice_args, $location);
}, 20, 2);

\add_action('all_admin_notices', function (): void {
	if (!cmx_emails_edit_screen_active() || !\function_exists(__NAMESPACE__ . '\\cmx_emails_mailbox_notice')) {
		return;
	}

	$post_id = isset($_GET['post']) ? (int) \wp_unslash($_GET['post']) : 0;
	$notice = $post_id > 0 ? cmx_emails_pull_edit_notice($post_id) : ['message' => '', 'type' => 'info'];
	if ((string) ($notice['message'] ?? '') === '') {
		$notice = cmx_emails_mailbox_notice();
	}

	if ((string) ($notice['message'] ?? '') === '') {
		return;
	}

	echo '<div class="notice notice-' . \esc_attr((string) ($notice['type'] ?? 'info')) . ' is-dismissible"><p>' . \esc_html((string) $notice['message']) . '</p></div>';
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_compose_attachment_row_from_path')) {
	function cmx_emails_compose_attachment_row_from_path(string $path, string $filename = '', string $mime = '', string $url = '', string $rel = ''): array {
		$path = \wp_normalize_path($path);
		if ($path === '' || !\is_file($path) || !\is_readable($path)) {
			return [];
		}

		$filename = \sanitize_file_name($filename !== '' ? $filename : (string) \basename($path));
		if ($filename === '') {
			return [];
		}

		$rel = \ltrim(\str_replace('\\', '/', $rel), '/');
		$uploads = \wp_get_upload_dir();
		$basedir = \trailingslashit(\wp_normalize_path((string) ($uploads['basedir'] ?? '')));
		if ($rel === '' && $basedir !== '' && \str_starts_with($path, $basedir)) {
			$rel = \ltrim((string) \substr($path, \strlen($basedir)), '/');
		}

		if ($url === '' && $rel !== '') {
			$baseurl = \trailingslashit((string) ($uploads['baseurl'] ?? ''));
			if ($baseurl !== '') {
				$url = $baseurl . $rel;
			}
		}

		if ($mime === '') {
			$filetype = (array) \wp_check_filetype($filename);
			$mime = \sanitize_text_field((string) ($filetype['type'] ?? ''));
		}

		return [
			'filename' => $filename,
			'mime'     => $mime !== '' ? $mime : 'application/octet-stream',
			'size'     => (int) \filesize($path),
			'rel'      => $rel,
			'path'     => $path,
			'url'      => $url,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_compose_related_post_attachment_list')) {
	function cmx_emails_compose_related_post_attachment_list(int $related_post_id): array {
		$related_post_id = (int) $related_post_id;
		if ($related_post_id <= 0) {
			return [];
		}

		$post_type = (string) \get_post_type($related_post_id);
		$list = [];
		$seen = [];
		$append = static function (string $path, string $filename = '', string $mime = '', string $url = '', string $rel = '') use (&$list, &$seen): void {
			$row = cmx_emails_compose_attachment_row_from_path($path, $filename, $mime, $url, $rel);
			if ($row === []) {
				return;
			}

			$normalized_path = \wp_normalize_path((string) ($row['path'] ?? ''));
			if ($normalized_path === '' || isset($seen[$normalized_path])) {
				return;
			}

			$seen[$normalized_path] = true;
			$list[] = $row;
		};

		if ($post_type === 'dokumente') {
			$self_meta_key = \defined(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
				: '_cmx_dokumente_files';
			foreach ((array) \get_post_meta($related_post_id, $self_meta_key, true) as $entry) {
				if (\is_numeric($entry)) {
					$attachment_id = (int) $entry;
					if ($attachment_id <= 0) {
						continue;
					}
					$append(
						(string) \get_attached_file($attachment_id),
						'',
						(string) \get_post_mime_type($attachment_id),
						(string) \wp_get_attachment_url($attachment_id)
					);
					continue;
				}

				$file_rel = \ltrim(\str_replace('\\', '/', (string) $entry), '/');
				if ($file_rel === '') {
					continue;
				}
				$append((string) (\WP_CONTENT_DIR . '/uploads/' . $file_rel), '', '', '', $file_rel);
			}

			$primary_rel = \ltrim(\str_replace('\\', '/', (string) \get_post_meta($related_post_id, '_cmx_dokumente_file_path', true)), '/');
			if ($primary_rel !== '') {
				$append((string) (\WP_CONTENT_DIR . '/uploads/' . $primary_rel), '', '', '', $primary_rel);
			}
		} elseif ($post_type === 'belege') {
			$uploads_meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_UPLOADS_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_UPLOADS_META')
				: '_cmx_belege_uploads';
			foreach ((array) \get_post_meta($related_post_id, $uploads_meta_key, true) as $entry) {
				$file_rel = \ltrim(\str_replace('\\', '/', (string) $entry), '/');
				if ($file_rel === '') {
					continue;
				}
				$append((string) (\WP_CONTENT_DIR . '/uploads/' . $file_rel), '', '', '', $file_rel);
			}

			if ($list === [] && \function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_primary_upload_abs_path')) {
				$append((string) cmxbu_get_beleg_primary_upload_abs_path($related_post_id));
			}
		}

		return $list;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_compose_document_attachment_list')) {
	function cmx_emails_compose_document_attachment_list(int $post_id): array {
		$post_id = (int) $post_id;
		if ($post_id <= 0) {
			return [];
		}

		$uploads_meta_key = \defined(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
			: '_cmx_dokumente_uploads';
		$related_ids = \array_merge(
			(array) \get_post_meta($post_id, $uploads_meta_key, true),
			(array) \get_post_meta($post_id, cmx_emails_meta_key('imported_post_ids'), true)
		);
		$related_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $related_ids))));
		if ($related_ids === []) {
			return [];
		}

		$list = [];
		$seen = [];
		foreach ($related_ids as $related_post_id) {
			foreach (cmx_emails_compose_related_post_attachment_list((int) $related_post_id) as $row) {
				$normalized_path = \wp_normalize_path((string) ($row['path'] ?? ''));
				if ($normalized_path === '' || isset($seen[$normalized_path])) {
					continue;
				}
				$seen[$normalized_path] = true;
				$list[] = $row;
			}
		}

		return $list;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_compose_attachment_list')) {
	function cmx_emails_compose_attachment_list(int $post_id): array {
		$list = [];
		$seen = [];
		$append = static function (array $row) use (&$list, &$seen): void {
			$path = \wp_normalize_path((string) ($row['path'] ?? ''));
			if ($path === '' || isset($seen[$path])) {
				return;
			}
			$seen[$path] = true;
			$list[] = $row;
		};

		foreach (\get_attached_media('', $post_id) as $attachment) {
			if (!$attachment instanceof \WP_Post) {
				continue;
			}

			$row = cmx_emails_compose_attachment_row_from_path(
				(string) \get_attached_file($attachment->ID),
				'',
				(string) \get_post_mime_type($attachment->ID),
				(string) \wp_get_attachment_url($attachment->ID)
			);
			if ($row !== []) {
				$append($row);
			}
		}

		foreach (cmx_emails_compose_document_attachment_list($post_id) as $row) {
			$append((array) $row);
		}

		return $list;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_send_compose_post')) {
	function cmx_emails_send_compose_post(int $post_id) {
		$post = \get_post($post_id);
		if (!$post instanceof \WP_Post || $post->post_type !== CMX_EMAILS_CPT) {
			return new \WP_Error('invalid_post', 'Die E-Mail konnte nicht geladen werden.');
		}

		$account_id = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('account_id'), true));
		$client = cmx_emails_get_client($account_id);
		$from_email = \sanitize_email((string) ($client['email'] ?? ''));
		$from_name = \sanitize_text_field((string) ($client['name'] ?? ''));
		$smtp_host = \sanitize_text_field((string) ($client['smtp_host'] ?? ''));
		$smtp_port = (int) ($client['smtp_port'] ?? 587);
		$smtp_password = (string) ($client['password'] ?? '');

		if (!\is_email($from_email)) {
			return new \WP_Error('missing_sender', 'Beim gewaehlten Konto fehlt eine gueltige Absender-Adresse.');
		}
		if ($from_name === '') {
			$from_name = \get_bloginfo('name');
		}
		if ($smtp_port <= 0 || $smtp_port > 65535) {
			$smtp_port = 587;
		}

		$to = cmx_emails_address_emails((array) \get_post_meta($post_id, cmx_emails_meta_key('to'), true));
		$cc = cmx_emails_address_emails((array) \get_post_meta($post_id, cmx_emails_meta_key('cc'), true));
		$bcc = cmx_emails_address_emails((array) \get_post_meta($post_id, cmx_emails_meta_key('bcc'), true));
		if ($to === []) {
			return new \WP_Error('missing_recipient', 'Bitte mindestens eine gueltige Empfaenger-Adresse eintragen.');
		}

		$subject = \function_exists(__NAMESPACE__ . '\\cmx_emails_subject_text')
			? cmx_emails_subject_text((string) \get_post_meta($post_id, cmx_emails_meta_key('subject'), true))
			: (string) \get_post_meta($post_id, cmx_emails_meta_key('subject'), true);
		if ($subject === '') {
			$subject = \function_exists(__NAMESPACE__ . '\\cmx_emails_subject_text')
				? cmx_emails_subject_text((string) $post->post_title)
				: (string) $post->post_title;
		}

		$body_html = (string) \get_post_meta($post_id, cmx_emails_meta_key('body_html'), true);
		if ($body_html === '') {
			$body_html = (string) $post->post_content;
		}
		if ($body_html === '') {
			$body_html = '&nbsp;';
		} elseif (\strpos($body_html, '<') === false) {
			$body_html = \wpautop(\esc_html($body_html));
		} else {
			$body_html = \wp_kses_post($body_html);
		}

		$attachments = cmx_emails_compose_attachment_list($post_id);
		$attachment_paths = [];
		foreach ($attachments as $attachment) {
			$path = \wp_normalize_path((string) ($attachment['path'] ?? ''));
			if ($path !== '' && \is_file($path) && \is_readable($path)) {
				$attachment_paths[] = $path;
			}
		}

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . ($from_name !== '' ? $from_name . ' <' . $from_email . '>' : $from_email),
			'Reply-To: ' . $from_email,
		];
		if ($cc !== []) {
			$headers[] = 'Cc: ' . \implode(', ', $cc);
		}
		if ($bcc !== []) {
			$headers[] = 'Bcc: ' . \implode(', ', $bcc);
		}

		$wp_mail_failed_message = '';
		$wp_mail_failed_listener = static function ($error) use (&$wp_mail_failed_message): void {
			if (!$error instanceof \WP_Error) {
				return;
			}

			$msg = \trim((string) $error->get_error_message());
			if ($msg === '') {
				$messages = \array_map('strval', (array) $error->get_error_messages());
				$msg = \trim(\implode(' | ', \array_filter($messages, static function (string $item): bool {
					return $item !== '';
				})));
			}

			$data = $error->get_error_data();
			if (\is_array($data) && !empty($data['phpmailer_exception_code'])) {
				$msg = $msg !== ''
					? ($msg . ' (Code ' . (string) $data['phpmailer_exception_code'] . ')')
					: ('PHPMailer-Fehler (Code ' . (string) $data['phpmailer_exception_code'] . ')');
			}

			$wp_mail_failed_message = $msg;
		};

		$smtp_listener = null;
		if ($smtp_host !== '') {
			$smtp_listener = static function (\PHPMailer\PHPMailer\PHPMailer $phpmailer) use ($from_email, $from_name, $smtp_host, $smtp_port, $smtp_password): void {
				$phpmailer->isSMTP();
				$phpmailer->Host = $smtp_host;
				$phpmailer->Port = $smtp_port;
				$phpmailer->Timeout = 20;
				$phpmailer->SMTPAutoTLS = true;
				$phpmailer->CharSet = 'UTF-8';
				$phpmailer->SMTPSecure = $smtp_port === 465
					? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
					: \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
				$phpmailer->SMTPOptions = [
					'ssl' => [
						'verify_peer' => false,
						'verify_peer_name' => false,
						'allow_self_signed' => true,
					],
				];

				$phpmailer->setFrom($from_email, $from_name, false);
				$phpmailer->Sender = $from_email;
				$phpmailer->clearReplyTos();
				$phpmailer->addReplyTo($from_email, $from_name);

				if ($from_email !== '' && $smtp_password !== '') {
					$phpmailer->SMTPAuth = true;
					$phpmailer->Username = $from_email;
					$phpmailer->Password = $smtp_password;
				} else {
					$phpmailer->SMTPAuth = false;
				}
			};
		}

		\add_action('wp_mail_failed', $wp_mail_failed_listener, 10, 1);
		if ($smtp_listener !== null) {
			\add_action('phpmailer_init', $smtp_listener, 100, 1);
		}

		try {
			$sent = \wp_mail($to, $subject, $body_html, $headers, $attachment_paths);
		} finally {
			\remove_action('wp_mail_failed', $wp_mail_failed_listener, 10);
			if ($smtp_listener !== null) {
				\remove_action('phpmailer_init', $smtp_listener, 100);
			}
		}

		if (!$sent) {
			$error_message = \trim((string) $wp_mail_failed_message);
			if ($error_message === '') {
				$error_message = 'E-Mail konnte nicht gesendet werden.';
			}
			return new \WP_Error('mail_failed', $error_message);
		}

		\update_post_meta($post_id, cmx_emails_meta_key('folder'), 'sent');
		\update_post_meta($post_id, cmx_emails_meta_key('status'), 'processed');
		\update_post_meta($post_id, cmx_emails_meta_key('manual_status'), 'processed');
		\update_post_meta($post_id, cmx_emails_meta_key('sent_at'), (string) \time());
		\update_post_meta($post_id, cmx_emails_meta_key('attachments'), $attachments);
		\update_post_meta($post_id, cmx_emails_meta_key('attachment_count'), (string) \count($attachments));
		\update_post_meta($post_id, cmx_emails_meta_key('has_attachment'), $attachments !== [] ? '1' : '0');
		if (\function_exists(__NAMESPACE__ . '\\cmx_emails_append_sent_recipient_notes')) {
			cmx_emails_append_sent_recipient_notes($post_id, $to, $cc, $bcc);
		}

		return [
			'ok' => true,
			'to' => $to,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_ensure_publish_status')) {
	function cmx_emails_ensure_publish_status(int $post_id): void {
		static $running = [];

		if ($post_id <= 0 || isset($running[$post_id])) {
			return;
		}

		$post = \get_post($post_id);
		if (!$post instanceof \WP_Post || $post->post_type !== CMX_EMAILS_CPT) {
			return;
		}

		$status = \sanitize_key((string) $post->post_status);
		if ($status === 'publish' || $status === 'trash') {
			return;
		}

		$running[$post_id] = true;
		\wp_update_post([
			'ID' => $post_id,
			'post_status' => 'publish',
		]);
		unset($running[$post_id]);
	}
}

\add_action('save_post_' . CMX_EMAILS_CPT, function (int $post_id, \WP_Post $post): void {
	if ((\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || \wp_is_post_autosave($post_id) || \wp_is_post_revision($post_id)) {
		return;
	}
	if ($post->post_type !== CMX_EMAILS_CPT) {
		return;
	}

	cmx_emails_ensure_publish_status($post_id);
}, 5, 2);

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_compose_admin_url')) {
	function cmx_emails_compose_admin_url(string $mode, int $source_id): string {
		$args = [
			'post_type' => CMX_EMAILS_CPT,
		];
		$mode = \sanitize_key($mode);
		if (\in_array($mode, ['reply', 'forward'], true) && $source_id > 0) {
			$args['cmx_email_compose'] = $mode;
			$args['cmx_email_source'] = (int) $source_id;
		}
		return \add_query_arg($args, \admin_url('post-new.php'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_compose_quoted_body')) {
	function cmx_emails_compose_quoted_body(int $source_id): string {
		$source = \get_post($source_id);
		if (!$source instanceof \WP_Post || $source->post_type !== CMX_EMAILS_CPT) {
			return '';
		}

		$body = (string) \get_post_meta($source_id, cmx_emails_meta_key('body_plain'), true);
		if ($body === '') {
			$body = (string) $source->post_content;
		}

		$lines = [
			'',
			'',
			'--- Urspruengliche Nachricht ---',
			'Von: ' . (string) \get_post_meta($source_id, cmx_emails_meta_key('sender_label'), true),
			'An: ' . cmx_emails_address_text_from_items((array) \get_post_meta($source_id, cmx_emails_meta_key('to'), true)),
			'Datum: ' . cmx_emails_date_label_long((int) \get_post_meta($source_id, cmx_emails_meta_key('received_ts'), true)),
			'Betreff: ' . (
				(\function_exists(__NAMESPACE__ . '\\cmx_emails_subject_text')
					? cmx_emails_subject_text((string) \get_post_meta($source_id, cmx_emails_meta_key('subject'), true))
					: (string) \get_post_meta($source_id, cmx_emails_meta_key('subject'), true))
				?: (\function_exists(__NAMESPACE__ . '\\cmx_emails_subject_text') ? cmx_emails_subject_text((string) $source->post_title) : (string) $source->post_title)
			),
			'',
			\trim($body),
		];

		return \implode("\n", $lines);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_compose_prefill')) {
	function cmx_emails_compose_prefill(\WP_Post $post): array {
		$source_id = cmx_emails_compose_source_id($post);
		$saved_mode = \sanitize_key((string) \get_post_meta($post->ID, cmx_emails_meta_key('compose_mode'), true));
		$mode = cmx_emails_compose_query_mode();
		if ($mode === '' && \in_array($saved_mode, ['reply', 'forward'], true)) {
			$mode = $saved_mode;
		}
		$saved_account_id = \sanitize_key((string) \get_post_meta($post->ID, cmx_emails_meta_key('account_id'), true));
		$saved_subject = \function_exists(__NAMESPACE__ . '\\cmx_emails_subject_text')
			? cmx_emails_subject_text((string) \get_post_meta($post->ID, cmx_emails_meta_key('subject'), true))
			: (string) \get_post_meta($post->ID, cmx_emails_meta_key('subject'), true);
		if (cmx_emails_is_auto_draft_title($saved_subject)) {
			$saved_subject = '';
		}
		$saved_to_raw = \trim((string) \get_post_meta($post->ID, cmx_emails_meta_key('to_raw'), true));
		$saved_cc_raw = \trim((string) \get_post_meta($post->ID, cmx_emails_meta_key('cc_raw'), true));
		$saved_bcc_raw = \trim((string) \get_post_meta($post->ID, cmx_emails_meta_key('bcc_raw'), true));
		$saved_to = cmx_emails_address_text_from_items((array) \get_post_meta($post->ID, cmx_emails_meta_key('to'), true));
		$saved_cc = cmx_emails_address_text_from_items((array) \get_post_meta($post->ID, cmx_emails_meta_key('cc'), true));
		$saved_bcc = cmx_emails_address_text_from_items((array) \get_post_meta($post->ID, cmx_emails_meta_key('bcc'), true));
		$body = (string) $post->post_content;
		$post_title = \function_exists(__NAMESPACE__ . '\\cmx_emails_subject_text')
			? cmx_emails_subject_text((string) $post->post_title)
			: (string) $post->post_title;
		if (cmx_emails_is_auto_draft_title($post_title)) {
			$post_title = '';
		}

		$data = [
			'mode' => $mode,
			'source_id' => $source_id,
			'account_id' => $saved_account_id !== '' ? $saved_account_id : cmx_emails_default_client_id(),
			'subject' => $saved_subject !== '' ? $saved_subject : $post_title,
			'to' => $saved_to_raw !== '' ? $saved_to_raw : $saved_to,
			'cc' => $saved_cc_raw !== '' ? $saved_cc_raw : $saved_cc,
			'bcc' => $saved_bcc_raw !== '' ? $saved_bcc_raw : $saved_bcc,
			'body' => $body,
		];

		if ($source_id <= 0 || (string) \get_post_type($source_id) !== CMX_EMAILS_CPT) {
			return $data;
		}

		$source_subject = \function_exists(__NAMESPACE__ . '\\cmx_emails_subject_text')
			? cmx_emails_subject_text((string) \get_post_meta($source_id, cmx_emails_meta_key('subject'), true))
			: (string) \get_post_meta($source_id, cmx_emails_meta_key('subject'), true);
		if ($source_subject === '') {
			$source_subject = \function_exists(__NAMESPACE__ . '\\cmx_emails_subject_text')
				? cmx_emails_subject_text((string) \get_the_title($source_id))
				: (string) \get_the_title($source_id);
		}

		if ($data['account_id'] === '') {
			$data['account_id'] = \sanitize_key((string) \get_post_meta($source_id, cmx_emails_meta_key('account_id'), true));
		}
		if ($data['subject'] === '') {
			$data['subject'] = ($mode === 'forward' ? 'Fwd: ' : 'Re: ') . $source_subject;
		}
		if ($mode === 'reply' && $data['to'] === '') {
			$data['to'] = \sanitize_email((string) \get_post_meta($source_id, cmx_emails_meta_key('sender_email'), true));
		}
		if ($data['body'] === '') {
			$data['body'] = cmx_emails_compose_quoted_body($source_id);
		}

		return $data;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_render_reader_metabox')) {
	function cmx_emails_render_reader_metabox(\WP_Post $post): void {
		$body_html = (string) \get_post_meta($post->ID, cmx_emails_meta_key('body_html'), true);
		$body_plain = (string) \get_post_meta($post->ID, cmx_emails_meta_key('body_plain'), true);

		echo '<div class="cmx-email-edit-reader">';
		if ($body_html !== '') {
			echo '<div class="cmx-email-edit-body is-html">' . \wp_kses_post($body_html) . '</div>';
		} else {
			$content = $body_plain !== '' ? $body_plain : (string) $post->post_content;
			if ($content === '') {
				echo '<p>Kein gespeicherter Inhalt vorhanden.</p>';
			} else {
				echo '<div class="cmx-email-edit-body is-plain">' . \wpautop(\esc_html($content)) . '</div>';
			}
		}
		echo '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_render_compose_metabox')) {
	function cmx_emails_render_compose_metabox(\WP_Post $post): void {
		$prefill = cmx_emails_compose_prefill($post);
		$source_id = (int) ($prefill['source_id'] ?? 0);
		$mode = \sanitize_key((string) ($prefill['mode'] ?? ''));
		$cc_value = (string) ($prefill['cc'] ?? '');
		$show_cc = \trim($cc_value) !== '';
		$bcc_value = (string) ($prefill['bcc'] ?? '');
		$show_bcc = \trim($bcc_value) !== '';

		\wp_nonce_field('cmx_emails_compose_save', 'cmx_emails_compose_nonce');

		if ($source_id > 0) {
			$source_label = $mode === 'forward' ? 'Weiterleitung von' : 'Antwort auf';
			$source_title = \function_exists(__NAMESPACE__ . '\\cmx_emails_subject_text')
				? cmx_emails_subject_text((string) \get_the_title($source_id))
				: (string) \get_the_title($source_id);
			echo '<div class="cmx-email-compose-note"><strong>' . \esc_html($source_label) . ':</strong> <a href="' . \esc_url(\get_edit_post_link($source_id, '')) . '">' . \esc_html($source_title) . '</a></div>';
		}

		echo '<input type="hidden" name="cmx_email_compose_mode" value="' . \esc_attr($mode) . '">';
		echo '<input type="hidden" name="cmx_email_compose_source_id" value="' . (int) $source_id . '">';
		echo '<input type="hidden" name="cmx_email_send_now" id="cmx-email-send-now" value="">';

		echo '<p><label for="cmx-email-account-compose"><strong>Konto</strong></label></p>';
		echo '<p><select id="cmx-email-account-compose" name="cmx_email_account_id" class="widefat">';
		foreach (cmx_emails_client_list() as $client) {
			$client = (array) $client;
			$id = \sanitize_key((string) ($client['id'] ?? ''));
			echo '<option value="' . \esc_attr($id) . '"' . \selected($id, (string) ($prefill['account_id'] ?? ''), false) . '>' . \esc_html(cmx_emails_client_label($client)) . '</option>';
		}
		echo '</select></p>';

		echo '<div class="cmx-email-compose-label-row">';
		echo '<label for="cmx-email-to"><strong>An</strong></label>';
		echo '<div class="cmx-email-compose-toggle-group">';
		echo '<button type="button" id="cmx-email-cc-toggle" class="button-link cmx-email-compose-toggle" aria-expanded="' . ($show_cc ? 'true' : 'false') . '">CC</button>';
		echo '<button type="button" id="cmx-email-bcc-toggle" class="button-link cmx-email-compose-toggle" aria-expanded="' . ($show_bcc ? 'true' : 'false') . '">BCC</button>';
		echo '</div>';
		echo '</div>';
		echo '<p><input id="cmx-email-to" type="text" name="cmx_email_to" class="widefat" value="' . \esc_attr((string) ($prefill['to'] ?? '')) . '" placeholder="mail@beispiel.ch, zweite@adresse.ch"></p>';

		echo '<div id="cmx-email-cc-wrap" class="cmx-email-compose-optional"' . ($show_cc ? '' : ' style="display:none;"') . '>';
		echo '<p><label for="cmx-email-cc"><strong>CC</strong></label></p>';
		echo '<p><input id="cmx-email-cc" type="text" name="cmx_email_cc" class="widefat" value="' . \esc_attr($cc_value) . '"></p>';
		echo '</div>';

		echo '<div id="cmx-email-bcc-wrap" class="cmx-email-compose-optional"' . ($show_bcc ? '' : ' style="display:none;"') . '>';
		echo '<p><label for="cmx-email-bcc"><strong>BCC</strong></label></p>';
		echo '<p><input id="cmx-email-bcc" type="text" name="cmx_email_bcc" class="widefat" value="' . \esc_attr($bcc_value) . '"></p>';
		echo '</div>';

		// echo '<p class="description">Betreff oben im Titel, Nachricht im Editor links schreiben. Versand rechts ueber den Button "Senden".</p>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_render_details_metabox')) {
	function cmx_emails_render_details_metabox(\WP_Post $post): void {
		$post_id = (int) $post->ID;
		$sender_label = (string) \get_post_meta($post_id, cmx_emails_meta_key('sender_label'), true);
		$sender_email = \sanitize_email((string) \get_post_meta($post_id, cmx_emails_meta_key('sender_email'), true));
		$subject = \function_exists(__NAMESPACE__ . '\\cmx_emails_subject_text')
			? cmx_emails_subject_text((string) \get_post_meta($post_id, cmx_emails_meta_key('subject'), true))
			: (string) \get_post_meta($post_id, cmx_emails_meta_key('subject'), true);
		$account_label = (string) \get_post_meta($post_id, cmx_emails_meta_key('account_label'), true);
		$folder = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('folder'), true));
		$status = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('status'), true));
		$ts = (int) \get_post_meta($post_id, cmx_emails_meta_key('received_ts'), true);
		$to = (array) \get_post_meta($post_id, cmx_emails_meta_key('to'), true);
		$cc = (array) \get_post_meta($post_id, cmx_emails_meta_key('cc'), true);
		$bcc = (array) \get_post_meta($post_id, cmx_emails_meta_key('bcc'), true);
		$attachments = cmx_emails_normalize_attachment_list(\get_post_meta($post_id, cmx_emails_meta_key('attachments'), true));
		$reply_url = cmx_emails_compose_admin_url('reply', $post_id);
		$forward_url = cmx_emails_compose_admin_url('forward', $post_id);
		$import_url = \wp_nonce_url(\add_query_arg([
			'action' => 'cmx_emails_import',
			'post_id' => $post_id,
			'email_id' => $post_id,
		], \admin_url('admin-post.php')), 'cmx_emails_import');

		echo '<div class="cmx-email-edit-meta">';
		echo '<dl class="cmx-email-edit-grid">';
		echo '<dt>Von</dt><dd>' . \esc_html($sender_label !== '' ? $sender_label : $sender_email) . '</dd>';
		if ($sender_email !== '') {
			echo '<dt>E-Mail</dt><dd>' . \esc_html($sender_email) . '</dd>';
		}
		echo '<dt>An</dt><dd>' . cmx_emails_render_address_html($to) . '</dd>';
		if ($cc !== []) {
			echo '<dt>CC</dt><dd>' . cmx_emails_render_address_html($cc) . '</dd>';
		}
		if ($bcc !== []) {
			echo '<dt>BCC</dt><dd>' . cmx_emails_render_address_html($bcc) . '</dd>';
		}
		echo '<dt>Datum</dt><dd>' . \esc_html(cmx_emails_date_label_long($ts)) . '</dd>';
		echo '<dt>Konto</dt><dd>' . \esc_html($account_label !== '' ? $account_label : '–') . '</dd>';
		echo '<dt>Ordner</dt><dd>' . \esc_html(cmx_emails_folder_label($folder)) . '</dd>';
		echo '<dt>Status</dt><dd><span class="cmx-email-badge ' . \esc_attr(cmx_emails_status_class($status)) . '">' . \esc_html(cmx_emails_status_label($status)) . '</span></dd>';
		echo '</dl>';

		echo '<div class="cmx-email-edit-actions">';
		echo '<a class="button button-primary" href="' . \esc_url($reply_url) . '">Antworten</a>';
		echo '<a class="button" href="' . \esc_url($forward_url) . '">Weiterleiten</a>';
		echo '<a class="button" href="' . \esc_url($import_url) . '">Als Beleg uebernehmen</a>';
		echo '</div>';

		echo '<div class="cmx-email-edit-attachments">';
		echo '<strong>Anhaenge</strong>';
		if ($attachments === []) {
			echo '<p>Keine Anhaenge vorhanden.</p>';
		} else {
			echo '<ul>';
			foreach ($attachments as $attachment) {
				$url = (string) ($attachment['url'] ?? '');
				$filename = (string) ($attachment['filename'] ?? 'Anhang');
				$size = (int) ($attachment['size'] ?? 0);
				echo '<li>';
				if ($url !== '') {
					echo '<a href="' . \esc_url($url) . '" download>' . \esc_html($filename) . '</a>';
				} else {
					echo \esc_html($filename);
				}
				if ($size > 0) {
					echo ' <span>(' . \esc_html(\size_format($size, 0)) . ')</span>';
				}
				echo '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
		echo '</div>';
	}
}

\add_action('save_post_' . CMX_EMAILS_CPT, function (int $post_id, \WP_Post $post): void {
	if ((\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || \wp_is_post_autosave($post_id) || \wp_is_post_revision($post_id)) {
		return;
	}
	if ($post->post_type !== CMX_EMAILS_CPT) {
		return;
	}
	if (!isset($_POST['cmx_emails_compose_nonce']) || !\wp_verify_nonce((string) \wp_unslash($_POST['cmx_emails_compose_nonce']), 'cmx_emails_compose_save')) {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

	$account_id = isset($_POST['cmx_email_account_id']) ? \sanitize_key((string) \wp_unslash($_POST['cmx_email_account_id'])) : '';
	$client = cmx_emails_get_client($account_id);
	$resolved_account_id = \sanitize_key((string) ($client['id'] ?? $account_id));
	$account_label = $client !== [] ? cmx_emails_client_label($client) : '';
	$account_email = \sanitize_email((string) ($client['email'] ?? ''));
	$compose_mode = isset($_POST['cmx_email_compose_mode']) ? \sanitize_key((string) \wp_unslash($_POST['cmx_email_compose_mode'])) : '';
	$compose_source_id = isset($_POST['cmx_email_compose_source_id']) ? (int) \wp_unslash($_POST['cmx_email_compose_source_id']) : 0;
	$to_raw = (string) \wp_unslash($_POST['cmx_email_to'] ?? '');
	$cc_raw = (string) \wp_unslash($_POST['cmx_email_cc'] ?? '');
	$bcc_raw = (string) \wp_unslash($_POST['cmx_email_bcc'] ?? '');
	$to = cmx_emails_address_items_from_text($to_raw);
	$cc = cmx_emails_address_items_from_text($cc_raw);
	$bcc = cmx_emails_address_items_from_text($bcc_raw);
	$invalid_addresses = [
		'An' => cmx_emails_invalid_address_parts($to_raw),
		'CC' => cmx_emails_invalid_address_parts($cc_raw),
		'BCC' => cmx_emails_invalid_address_parts($bcc_raw),
	];
	$body_html = (string) $post->post_content;
	$body_plain = \trim(\wp_strip_all_tags($body_html));
	$assignment_manual = \get_post_meta($post_id, cmx_emails_meta_key('assignment_manual'), true) === '1';
	$current_contact_ids = \function_exists(__NAMESPACE__ . '\\cmx_emails_assignment_contact_ids')
		? cmx_emails_assignment_contact_ids($post_id)
		: [];
	$current_project_ids = \function_exists(__NAMESPACE__ . '\\cmx_emails_assignment_project_ids')
		? cmx_emails_assignment_project_ids($post_id)
		: [];
	$auto_contact_ids = \function_exists(__NAMESPACE__ . '\\cmx_emails_auto_contact_ids_for_message')
		? cmx_emails_auto_contact_ids_for_message($account_email, $to, $cc, $bcc)
		: [];

	\update_post_meta($post_id, cmx_emails_meta_key('direction'), 'outgoing');
	\update_post_meta($post_id, cmx_emails_meta_key('compose_mode'), $compose_mode);
	\update_post_meta($post_id, cmx_emails_meta_key('compose_source_id'), (string) \max(0, $compose_source_id));
	\update_post_meta($post_id, cmx_emails_meta_key('account_id'), $resolved_account_id);
	\update_post_meta($post_id, cmx_emails_meta_key('account_label'), $account_label);
	\update_post_meta($post_id, cmx_emails_meta_key('account_email'), $account_email);
	\update_post_meta($post_id, cmx_emails_meta_key('sender_email'), $account_email);
	\update_post_meta($post_id, cmx_emails_meta_key('sender_label'), $account_label);
	\update_post_meta($post_id, cmx_emails_meta_key('subject'), (string) \get_the_title($post_id));
	\update_post_meta($post_id, cmx_emails_meta_key('to_raw'), $to_raw);
	\update_post_meta($post_id, cmx_emails_meta_key('cc_raw'), $cc_raw);
	\update_post_meta($post_id, cmx_emails_meta_key('bcc_raw'), $bcc_raw);
	\update_post_meta($post_id, cmx_emails_meta_key('to'), $to);
	\update_post_meta($post_id, cmx_emails_meta_key('cc'), $cc);
	\update_post_meta($post_id, cmx_emails_meta_key('bcc'), $bcc);
	\update_post_meta(
		$post_id,
		cmx_emails_meta_key('contact_id_auto'),
		(string) \max(
			0,
			\function_exists(__NAMESPACE__ . '\\cmx_emails_primary_assignment_id')
				? cmx_emails_primary_assignment_id($auto_contact_ids)
				: (isset($auto_contact_ids[0]) ? (int) $auto_contact_ids[0] : 0)
		)
	);
	\update_post_meta($post_id, cmx_emails_meta_key('body_html'), $body_html);
	\update_post_meta($post_id, cmx_emails_meta_key('body_plain'), $body_plain);
	\update_post_meta($post_id, cmx_emails_meta_key('folder'), 'drafts');
	if (\function_exists(__NAMESPACE__ . '\\cmx_emails_save_assignments')) {
		$contact_ids = $assignment_manual ? $current_contact_ids : ($auto_contact_ids !== [] ? $auto_contact_ids : $current_contact_ids);
		cmx_emails_save_assignments($post_id, $contact_ids, $current_project_ids, $assignment_manual, false);
	}
	$received_ts = (int) \get_post_meta($post_id, cmx_emails_meta_key('received_ts'), true);
	if ($received_ts <= 0) {
		\update_post_meta($post_id, cmx_emails_meta_key('received_ts'), (string) \time());
	}
	cmx_emails_ensure_publish_status($post_id);
	cmx_emails_recheck_post_for_spam($post_id);

	$should_send = !empty($_POST['cmx_email_send_now']);
	$recipient_notice = \trim($to_raw) === ''
		? 'Versand blockiert. Im Feld An fehlt eine E-Mail-Adresse.'
		: ($to === []
			? 'Versand blockiert. Bitte mindestens eine gueltige Empfaenger-Adresse in An eintragen.'
			: '');
	$subject_notice = cmx_emails_is_auto_draft_title((string) \get_the_title($post_id))
		? 'Versand blockiert. Bitte einen Betreff eingeben.'
		: '';

	if ($should_send) {
		$invalid_notice = cmx_emails_invalid_address_notice($invalid_addresses);
		if ($invalid_notice !== '') {
			cmx_emails_queue_redirect_notice($post_id, $invalid_notice, 'error');
		} elseif ($recipient_notice !== '') {
			cmx_emails_queue_redirect_notice($post_id, $recipient_notice, 'error');
		} elseif ($subject_notice !== '') {
			cmx_emails_queue_redirect_notice($post_id, $subject_notice, 'error');
		} else {
			$result = cmx_emails_send_compose_post($post_id);
			if (\is_wp_error($result)) {
				cmx_emails_queue_redirect_notice($post_id, (string) $result->get_error_message(), 'error');
			} else {
				cmx_emails_queue_redirect_notice($post_id, 'E-Mail wurde versendet.', 'success', 'list');
			}
		}
	}
}, 10, 2);

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_render_assignment_metabox')) {
	function cmx_emails_render_assignment_metabox(\WP_Post $post): void {
		$post_id = (int) $post->ID;
		$contact_ids = cmx_emails_assignment_contact_ids($post_id);
		$project_ids = cmx_emails_assignment_project_ids($post_id);

		\wp_nonce_field('cmx_emails_assignment_save', 'cmx_emails_assignment_nonce');

		cmx_emails_render_assignment_picker('contact', $contact_ids, 'cmx_email_contact_ids[]', 'editor');
		echo '<div style="height:12px;"></div>';
		cmx_emails_render_assignment_picker('project', $project_ids, 'cmx_email_project_ids[]', 'editor');
	}
}

\add_action('save_post_' . CMX_EMAILS_CPT, function (int $post_id, \WP_Post $post): void {
	if ((\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || \wp_is_post_autosave($post_id) || \wp_is_post_revision($post_id)) {
		return;
	}
	if ($post->post_type !== CMX_EMAILS_CPT) {
		return;
	}
	if (!isset($_POST['cmx_emails_assignment_nonce']) || !\wp_verify_nonce((string) \wp_unslash($_POST['cmx_emails_assignment_nonce']), 'cmx_emails_assignment_save')) {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

	$contact_ids = isset($_POST['cmx_email_contact_ids']) ? cmx_emails_normalize_post_id_list(\wp_unslash((array) $_POST['cmx_email_contact_ids']), cmx_emails_contact_post_types()) : [];
	$project_ids = isset($_POST['cmx_email_project_ids']) ? cmx_emails_normalize_post_id_list(\wp_unslash((array) $_POST['cmx_email_project_ids']), cmx_emails_project_post_types()) : [];

	cmx_emails_save_assignments($post_id, $contact_ids, $project_ids, true, true);
	cmx_emails_ensure_publish_status($post_id);
}, 10, 2);

\add_action('admin_head', function (): void {
	if (!cmx_emails_edit_screen_active()) {
		return;
	}
	global $post;
	$post_id = isset($_GET['post']) ? (int) \wp_unslash($_GET['post']) : 0;
	$screen_post = $post instanceof \WP_Post && $post->post_type === CMX_EMAILS_CPT
		? $post
		: ($post_id > 0 ? \get_post($post_id) : null);
	$is_compose = $screen_post instanceof \WP_Post
		? cmx_emails_is_compose_post($screen_post)
		: cmx_emails_compose_query_mode() !== '';
	?>
	<style>
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-edit-reader,
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-edit-meta {
			font-size: 14px;
			line-height: 1.6;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-edit-body {
			max-height: 70vh;
			overflow: auto;
			padding: 16px;
			border: 1px solid #d0d5dd;
			border-radius: 12px;
			background: #fff;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-edit-grid {
			display: grid;
			grid-template-columns: 72px 1fr;
			gap: 8px 12px;
			margin: 0;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-edit-grid dt {
			font-weight: 700;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-edit-grid dd {
			margin: 0;
			word-break: break-word;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-edit-actions {
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
			margin-top: 16px;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> #side-sortables,
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> #cmx_email_assignment,
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> #cmx_email_assignment .inside {
			overflow: visible;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> #cmx_email_assignment {
			position: relative;
			z-index: 30;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> #cmx_email_assignment .inside {
			position: relative;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-compose-note {
			margin-bottom: 14px;
			padding: 10px 12px;
			border: 1px solid #d0d5dd;
			border-radius: 10px;
			background: #f8fafc;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-compose-label-row {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			margin-bottom: 6px;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-compose-label-row label {
			margin: 0;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-compose-toggle-group {
			display: inline-flex;
			align-items: center;
			gap: 10px;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-compose-toggle {
			font-size: 12px;
			font-weight: 600;
			text-decoration: none;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-compose-toggle.is-active {
			color: #135e96;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-compose-optional {
			margin-top: 0;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 4px 10px;
			border-radius: 999px;
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
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-edit-attachments {
			margin-top: 18px;
			padding-top: 14px;
			border-top: 1px solid #e4e7ec;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-edit-attachments ul {
			margin: 10px 0 0 18px;
		}
		<?php if ($is_compose) : ?>
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> #postdivrich #content,
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> #postdivrich textarea.wp-editor-area {
			resize: vertical;
			min-height: 260px;
			overflow: auto;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> #submitdiv #minor-publishing {
			display: none;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> #submitdiv #major-publishing-actions {
			display: grid;
			grid-template-columns: 1fr auto;
			grid-template-areas:
				"send send"
				"save delete";
			align-items: center;
			column-gap: 8px;
			row-gap: 8px;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> #submitdiv #cmx-email-send-button {
			grid-area: send;
			width: 100%;
			margin: 0;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> #submitdiv #publishing-action {
			grid-area: save;
			display: flex;
			justify-content: flex-start;
			width: auto;
			float: none;
			justify-self: start;
			text-align: left;
			margin: 0 !important;
			padding: 0;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> #submitdiv #publishing-action #publish {
			float: none;
			margin: 0 !important;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> #submitdiv #publishing-action .spinner {
			display: none !important;
			margin: 0 !important;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> #submitdiv #delete-action {
			grid-area: delete;
			float: none;
			justify-self: end;
			margin: 0 !important;
		}
		<?php endif; ?>
	</style>
	<?php
});

\add_action('admin_footer', function (): void {
	if (!cmx_emails_edit_screen_active()) {
		return;
	}
	global $post;
	$post_id = isset($_GET['post']) ? (int) \wp_unslash($_GET['post']) : 0;
	$post = $post instanceof \WP_Post && $post->post_type === CMX_EMAILS_CPT
		? $post
		: ($post_id > 0 ? \get_post($post_id) : null);
	if (!$post instanceof \WP_Post || !cmx_emails_is_compose_post($post)) {
		return;
	}
	?>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			var publishButton = document.getElementById('publish');
			var sendNowField = document.getElementById('cmx-email-send-now');
			var isSendSubmit = false;
			var submitBox = document.getElementById('submitdiv');
			var ccToggle = document.getElementById('cmx-email-cc-toggle');
			var ccWrap = document.getElementById('cmx-email-cc-wrap');
			var ccInput = document.getElementById('cmx-email-cc');
			var bccToggle = document.getElementById('cmx-email-bcc-toggle');
			var bccWrap = document.getElementById('cmx-email-bcc-wrap');
			var bccInput = document.getElementById('cmx-email-bcc');
			function syncSubmitBoxTitle() {
				if (!submitBox) {
					return;
				}
				var titleNode = submitBox.querySelector('.postbox-header .hndle span');
				if (titleNode) {
					titleNode.textContent = 'E-Mail...';
					return;
				}
				titleNode = submitBox.querySelector('.postbox-header .hndle, h2.hndle');
				if (titleNode) {
					titleNode.textContent = 'E-Mail...';
				}
			}
			function syncOptionalState(toggle, wrap) {
				if (!toggle || !wrap) {
					return;
				}
				var visible = wrap.style.display !== 'none';
				toggle.setAttribute('aria-expanded', visible ? 'true' : 'false');
				toggle.classList.toggle('is-active', visible);
			}
			function bindOptionalToggle(toggle, wrap, input) {
				if (!toggle || !wrap) {
					return;
				}
				toggle.addEventListener('click', function (e) {
					e.preventDefault();
					var visible = wrap.style.display !== 'none';
					if (visible && input) {
						input.value = '';
						input.dispatchEvent(new Event('input', { bubbles: true }));
						input.dispatchEvent(new Event('change', { bubbles: true }));
					}
					wrap.style.display = visible ? 'none' : '';
					syncOptionalState(toggle, wrap);
					if (!visible && input) {
						input.focus();
					}
				});
				syncOptionalState(toggle, wrap);
			}
			if (publishButton) {
				publishButton.value = 'Speichern';
				publishButton.classList.remove('button-primary');
				publishButton.classList.add('button-secondary');
				publishButton.addEventListener('click', function () {
					if (sendNowField && !isSendSubmit) {
						sendNowField.value = '';
					}
				});
			}

			if (submitBox && publishButton && !document.getElementById('cmx-email-send-button')) {
				var sendButton = document.createElement('button');
				sendButton.type = 'button';
				sendButton.id = 'cmx-email-send-button';
				sendButton.className = 'button button-primary button-large';
				sendButton.textContent = 'Senden';
				sendButton.style.width = '100%';
				sendButton.style.marginBottom = '8px';
				sendButton.addEventListener('click', function () {
					isSendSubmit = true;
					if (sendNowField) {
						sendNowField.value = '1';
					}
					publishButton.click();
				});

				var publishAction = submitBox.querySelector('#major-publishing-actions');
				if (publishAction) {
					publishAction.insertBefore(sendButton, publishAction.firstChild);
				} else if (publishButton.parentNode) {
					publishButton.parentNode.appendChild(sendButton);
				}
			}

			bindOptionalToggle(ccToggle, ccWrap, ccInput);
			bindOptionalToggle(bccToggle, bccWrap, bccInput);

			syncSubmitBoxTitle();
			setTimeout(syncSubmitBoxTitle, 0);
			setTimeout(syncSubmitBoxTitle, 250);
		});
	</script>
	<?php
});
