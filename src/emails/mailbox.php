<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_EMAILS_SYNC_LIMIT')) {
	\define(__NAMESPACE__ . '\\CMX_EMAILS_SYNC_LIMIT', 60);
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_meta_key')) {
	function cmx_emails_meta_key(string $suffix): string {
		$suffix = \sanitize_key($suffix);
		return '_cmx_email_' . $suffix;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_upload_rel_from_abs')) {
	function cmx_emails_upload_rel_from_abs(string $abs): string {
		$abs = \wp_normalize_path($abs);
		$uploads = \wp_get_upload_dir();
		$basedir = \wp_normalize_path((string) ($uploads['basedir'] ?? ''));
		if ($abs === '' || $basedir === '' || !\str_starts_with($abs, $basedir)) {
			return '';
		}
		return \ltrim((string) \substr($abs, \strlen($basedir)), '/');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_upload_url_from_rel')) {
	function cmx_emails_upload_url_from_rel(string $rel): string {
		$rel = \ltrim(\str_replace('\\', '/', $rel), '/');
		if ($rel === '') {
			return '';
		}
		$uploads = \wp_get_upload_dir();
		$baseurl = \trailingslashit((string) ($uploads['baseurl'] ?? ''));
		if ($baseurl === '') {
			return '';
		}
		$parts = \array_map('rawurlencode', \array_filter(\explode('/', $rel), static function ($value): bool {
			return \is_string($value) && $value !== '';
		}));
		return $baseurl . \implode('/', $parts);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_store_attachment')) {
	function cmx_emails_store_attachment(string $client_id, int $uid, array $attachment): array {
		$uploads = \wp_get_upload_dir();
		$basedir = \wp_normalize_path((string) ($uploads['basedir'] ?? ''));
		if ($basedir === '') {
			return [];
		}

		$client_id = \sanitize_key($client_id);
		if ($client_id === '') {
			$client_id = 'default';
		}

		$target_dir = \trailingslashit($basedir) . 'misbuero/emails/' . $client_id . '/' . \max(1, $uid);
		if (!\is_dir($target_dir)) {
			@\wp_mkdir_p($target_dir);
		}
		if (!\is_dir($target_dir) || !\is_writable($target_dir)) {
			return [];
		}

		$filename = \sanitize_file_name((string) ($attachment['filename'] ?? ''));
		if ($filename === '') {
			$filename = 'anhang.bin';
		}
		$filename = \wp_unique_filename($target_dir, $filename);
		$target_abs = \wp_normalize_path($target_dir . '/' . $filename);
		$content = (string) ($attachment['content'] ?? '');
		if ($content === '' || @\file_put_contents($target_abs, $content) === false) {
			return [];
		}
		@\chmod($target_abs, 0666);

		$rel = cmx_emails_upload_rel_from_abs($target_abs);
		return [
			'filename' => $filename,
			'mime'     => \sanitize_text_field((string) ($attachment['mime'] ?? 'application/octet-stream')),
			'size'     => (int) \filesize($target_abs),
			'rel'      => $rel,
			'path'     => $target_abs,
			'url'      => cmx_emails_upload_url_from_rel($rel),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_normalize_attachment_list')) {
	function cmx_emails_normalize_attachment_list($attachments): array {
		$list = \is_array($attachments) ? $attachments : [];
		$out = [];

		foreach ($list as $attachment) {
			if (!\is_array($attachment)) {
				continue;
			}

			$rel = \ltrim((string) ($attachment['rel'] ?? ''), '/');
			$path = \wp_normalize_path((string) ($attachment['path'] ?? ''));
			if ($path === '' && $rel !== '') {
				$uploads = \wp_get_upload_dir();
				$basedir = \wp_normalize_path((string) ($uploads['basedir'] ?? ''));
				if ($basedir !== '') {
					$path = $basedir . '/' . $rel;
				}
			}

			$filename = \sanitize_file_name((string) ($attachment['filename'] ?? ''));
			if ($filename === '' && $path !== '') {
				$filename = \basename($path);
			}
			if ($filename === '') {
				continue;
			}

			$mime = \sanitize_text_field((string) ($attachment['mime'] ?? 'application/octet-stream'));
			$size = isset($attachment['size']) ? (int) $attachment['size'] : ($path !== '' && \is_file($path) ? (int) \filesize($path) : 0);
			$url = (string) ($attachment['url'] ?? '');
			if ($url === '' && $rel !== '') {
				$url = cmx_emails_upload_url_from_rel($rel);
			}

			$out[] = [
				'filename' => $filename,
				'mime'     => $mime,
				'size'     => $size,
				'rel'      => $rel,
				'path'     => $path,
				'url'      => $url,
			];
		}

		return $out;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_decode_mime_text')) {
	function cmx_emails_decode_mime_text(string $value): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_mail_import_decode_mime_header')) {
			return (string) cmx_mail_import_decode_mime_header($value);
		}
		return \trim($value);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_part_filename')) {
	function cmx_emails_part_filename($part): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_mail_import_part_filename')) {
			return (string) cmx_mail_import_part_filename($part);
		}
		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_part_mime_type')) {
	function cmx_emails_part_mime_type($part): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_mail_import_get_mime_type')) {
			return (string) cmx_mail_import_get_mime_type($part);
		}
		return 'application/octet-stream';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_decode_part_body')) {
	function cmx_emails_decode_part_body(string $body, int $encoding): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_mail_import_decode_part_body')) {
			return (string) cmx_mail_import_decode_part_body($body, $encoding);
		}
		if ($encoding === 3) {
			$decoded = \base64_decode($body, true);
			return \is_string($decoded) ? $decoded : '';
		}
		if ($encoding === 4) {
			return (string) \quoted_printable_decode($body);
		}
		return $body;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_address_list_from_entries')) {
	function cmx_emails_address_list_from_entries($entries): array {
		$list = [];

		foreach ((array) $entries as $entry) {
			if (!\is_object($entry)) {
				continue;
			}

			$mailbox = isset($entry->mailbox) ? (string) $entry->mailbox : '';
			$host = isset($entry->host) ? (string) $entry->host : '';
			$email = \sanitize_email($mailbox !== '' && $host !== '' ? $mailbox . '@' . $host : '');
			if (!\is_email($email)) {
				continue;
			}

			$name = cmx_emails_decode_mime_text((string) ($entry->personal ?? ''));
			$name = \sanitize_text_field($name);
			$label = $name !== '' ? ($name . ' <' . $email . '>') : $email;
			$key = \strtolower($email);

			$list[$key] = [
				'name'  => $name,
				'email' => $key,
				'label' => $label,
			];
		}

		return \array_values($list);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_sender_from_header')) {
	function cmx_emails_sender_from_header($header): array {
		if (!\is_object($header)) {
			return ['name' => '', 'email' => '', 'label' => ''];
		}

		$entries = [];
		if (isset($header->from)) {
			$entries = cmx_emails_address_list_from_entries($header->from);
		}
		if ($entries === [] && isset($header->sender)) {
			$entries = cmx_emails_address_list_from_entries($header->sender);
		}
		if ($entries === []) {
			return ['name' => '', 'email' => '', 'label' => ''];
		}

		return (array) $entries[0];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_header_addresses')) {
	function cmx_emails_header_addresses($header, string $property): array {
		if (!\is_object($header) || !isset($header->{$property})) {
			return [];
		}
		return cmx_emails_address_list_from_entries($header->{$property});
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_parse_message_timestamp')) {
	function cmx_emails_parse_message_timestamp($overview, $header): int {
		$candidates = [];
		if (\is_object($overview) && isset($overview->date)) {
			$candidates[] = (string) $overview->date;
		}
		if (\is_object($header) && isset($header->date)) {
			$candidates[] = (string) $header->date;
		}

		foreach ($candidates as $candidate) {
			$ts = \strtotime($candidate);
			if ($ts !== false && $ts > 0) {
				return (int) $ts;
			}
		}

		return \time();
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_collect_message_payload_walk')) {
	function cmx_emails_collect_message_payload_walk($imap, int $msg_no, $part, string $part_no, array &$payload): void {
		if (!\is_object($part)) {
			return;
		}

		$has_children = isset($part->parts) && \is_array($part->parts) && !empty($part->parts);
		if ($has_children) {
			foreach ($part->parts as $index => $child) {
				$child_no = $part_no === '' ? (string) ($index + 1) : ($part_no . '.' . ($index + 1));
				cmx_emails_collect_message_payload_walk($imap, $msg_no, $child, $child_no, $payload);
			}
			return;
		}

		$raw = $part_no === ''
			? (string) \imap_body($imap, $msg_no, \FT_PEEK)
			: (string) \imap_fetchbody($imap, $msg_no, $part_no, \FT_PEEK);
		if ($raw === '') {
			return;
		}

		$mime = cmx_emails_part_mime_type($part);
		$filename = cmx_emails_part_filename($part);
		$encoding = isset($part->encoding) ? (int) $part->encoding : 0;
		$content = cmx_emails_decode_part_body($raw, $encoding);
		if ($content === '') {
			return;
		}

		$disposition = \strtolower((string) ($part->disposition ?? ''));
		$is_attachment = $filename !== '';
		if (!$is_attachment && $disposition !== '' && \in_array($disposition, ['attachment', 'inline'], true) && !\in_array($mime, ['text/plain', 'text/html'], true)) {
			$is_attachment = true;
		}

		if ($is_attachment) {
			$payload['attachments'][] = [
				'filename' => $filename !== '' ? $filename : 'attachment.bin',
				'mime'     => $mime,
				'content'  => $content,
			];
			return;
		}

		if ($mime === 'text/plain') {
			$payload['plain'] .= ($payload['plain'] !== '' ? "\n\n" : '') . \trim($content);
			return;
		}

		if ($mime === 'text/html') {
			$payload['html'] .= $content;
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_fetch_message_payload')) {
	function cmx_emails_fetch_message_payload($imap, int $msg_no): array {
		$payload = [
			'plain' => '',
			'html' => '',
			'attachments' => [],
		];

		$structure = \imap_fetchstructure($imap, $msg_no);
		if (!$structure || !\is_object($structure)) {
			return $payload;
		}

		cmx_emails_collect_message_payload_walk($imap, $msg_no, $structure, '', $payload);
		return $payload;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_find_post_id')) {
	function cmx_emails_find_post_id(string $client_id, string $folder, int $uid): int {
		$ids = \get_posts([
			'post_type'        => CMX_EMAILS_CPT,
			'post_status'      => ['publish', 'draft', 'private', 'pending'],
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_query'       => [
				['key' => cmx_emails_meta_key('account_id'), 'value' => \sanitize_key($client_id)],
				['key' => cmx_emails_meta_key('folder'), 'value' => \sanitize_key($folder)],
				['key' => cmx_emails_meta_key('uid'), 'value' => (string) \max(0, $uid)],
			],
		]);

		return isset($ids[0]) ? (int) $ids[0] : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_text_excerpt')) {
	function cmx_emails_text_excerpt(string $text, int $limit = 180): string {
		$text = \trim(\wp_strip_all_tags($text));
		if ($text === '') {
			return '';
		}

		if (\function_exists('mb_strlen') && \function_exists('mb_substr')) {
			return \mb_strlen($text) > $limit ? \rtrim((string) \mb_substr($text, 0, $limit - 1)) . '…' : $text;
		}

		return \strlen($text) > $limit ? \rtrim(\substr($text, 0, $limit - 1)) . '…' : $text;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_assignment_label')) {
	function cmx_emails_assignment_label(int $post_id): string {
		$contact_id = (int) \get_post_meta($post_id, cmx_emails_meta_key('contact_id'), true);
		$project_id = (int) \get_post_meta($post_id, cmx_emails_meta_key('project_id'), true);
		$parts = [];

		if ($contact_id > 0 && \get_post_status($contact_id) !== false) {
			$parts[] = 'Kunde: ' . (string) \get_the_title($contact_id);
		}
		if ($project_id > 0 && \get_post_status($project_id) !== false) {
			$parts[] = 'Projekt: ' . (string) \get_the_title($project_id);
		}

		return $parts !== [] ? \implode(' | ', $parts) : 'nicht zugeordnet';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_compute_status')) {
	function cmx_emails_compute_status(array $data): string {
		$manual_status = \sanitize_key((string) ($data['manual_status'] ?? ''));
		$imported_post_ids = \is_array($data['imported_post_ids'] ?? null) ? (array) $data['imported_post_ids'] : [];
		$imap_seen = !empty($data['imap_seen']);
		$read_at = (int) ($data['read_at'] ?? 0);

		if ($manual_status === 'processed' || $imported_post_ids !== []) {
			return 'processed';
		}
		if ($manual_status === 'read' || $read_at > 0 || $imap_seen) {
			return 'read';
		}
		return 'new';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_update_assignment_cache')) {
	function cmx_emails_update_assignment_cache(int $post_id): void {
		\update_post_meta($post_id, cmx_emails_meta_key('assignment_label'), cmx_emails_assignment_label($post_id));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_sync_single_message')) {
	function cmx_emails_sync_single_message($imap, array $client, string $folder, int $msg_no, string $mailbox = ''): array {
		$overview_list = \imap_fetch_overview($imap, (string) $msg_no, 0);
		$overview = (\is_array($overview_list) && isset($overview_list[0]) && \is_object($overview_list[0])) ? $overview_list[0] : null;
		if (!$overview) {
			return ['post_id' => 0, 'uid' => 0];
		}

		$uid = (int) \imap_uid($imap, $msg_no);
		if ($uid <= 0) {
			return ['post_id' => 0, 'uid' => 0];
		}

		$raw_header = (string) \imap_fetchheader($imap, $msg_no, \FT_PREFETCHTEXT);
		$header = $raw_header !== '' ? \imap_rfc822_parse_headers($raw_header) : false;
		if (!$header || !\is_object($header)) {
			return ['post_id' => 0, 'uid' => $uid];
		}

		$subject = '';
		if (isset($header->subject)) {
			$subject = \sanitize_text_field(cmx_emails_decode_mime_text((string) $header->subject));
		}

		$sender = cmx_emails_sender_from_header($header);
		$to = cmx_emails_header_addresses($header, 'to');
		$cc = cmx_emails_header_addresses($header, 'cc');
		$bcc = cmx_emails_header_addresses($header, 'bcc');
		$ts = cmx_emails_parse_message_timestamp($overview, $header);
		$payload = cmx_emails_fetch_message_payload($imap, $msg_no);
		$plain = \trim((string) ($payload['plain'] ?? ''));
		$html = (string) ($payload['html'] ?? '');
		$content = $plain !== '' ? $plain : \trim(\wp_strip_all_tags($html));
		$snippet = cmx_emails_text_excerpt($content !== '' ? $content : $subject);
		$message_id = \sanitize_text_field((string) ($header->message_id ?? ''));
		$account_id = \sanitize_key((string) ($client['id'] ?? ''));
		$existing_id = cmx_emails_find_post_id($account_id, $folder, $uid);
		$existing_attachments = $existing_id > 0 ? cmx_emails_normalize_attachment_list(\get_post_meta($existing_id, cmx_emails_meta_key('attachments'), true)) : [];
		$stored_attachments = $existing_attachments;

		if ($stored_attachments === []) {
			$stored_attachments = [];
			foreach ((array) ($payload['attachments'] ?? []) as $attachment) {
				$stored = cmx_emails_store_attachment($account_id, $uid, (array) $attachment);
				if ($stored !== []) {
					$stored_attachments[] = $stored;
				}
			}
		}

		$subject_title = $subject !== '' ? $subject : ($sender['label'] !== '' ? $sender['label'] : ('E-Mail #' . $uid));
		$postarr = [
			'post_type'    => CMX_EMAILS_CPT,
			'post_status'  => 'publish',
			'post_title'   => $subject_title,
			'post_content' => $content,
			'post_excerpt' => $snippet,
		];
		if ($existing_id > 0) {
			$postarr['ID'] = $existing_id;
		}

		$post_id = \wp_insert_post($postarr, true);
		if (\is_wp_error($post_id) || (int) $post_id <= 0) {
			return ['post_id' => 0, 'uid' => $uid];
		}
		$post_id = (int) $post_id;

		$current_contact_id = (int) \get_post_meta($post_id, cmx_emails_meta_key('contact_id'), true);
		$current_project_id = (int) \get_post_meta($post_id, cmx_emails_meta_key('project_id'), true);
		$assignment_manual = \get_post_meta($post_id, cmx_emails_meta_key('assignment_manual'), true) === '1';
		$auto_contact_id = 0;
		if ($sender['email'] !== '' && \function_exists(__NAMESPACE__ . '\\cmx_mail_import_find_contact_by_sender')) {
			$auto_contact_id = (int) cmx_mail_import_find_contact_by_sender((string) $sender['email']);
		}

		$contact_id = $assignment_manual ? $current_contact_id : ($current_contact_id > 0 ? $current_contact_id : $auto_contact_id);
		$project_id = $current_project_id > 0 ? $current_project_id : 0;
		$imported_post_ids = (array) \get_post_meta($post_id, cmx_emails_meta_key('imported_post_ids'), true);
		$read_at = (int) \get_post_meta($post_id, cmx_emails_meta_key('read_at'), true);
		$manual_status = (string) \get_post_meta($post_id, cmx_emails_meta_key('manual_status'), true);
		$status = cmx_emails_compute_status([
			'manual_status' => $manual_status,
			'imported_post_ids' => $imported_post_ids,
			'imap_seen' => !empty($overview->seen),
			'read_at' => $read_at,
		]);

		\update_post_meta($post_id, cmx_emails_meta_key('account_id'), $account_id);
		\update_post_meta($post_id, cmx_emails_meta_key('account_label'), cmx_emails_client_label($client));
		\update_post_meta($post_id, cmx_emails_meta_key('account_email'), \sanitize_email((string) ($client['email'] ?? '')));
		\update_post_meta($post_id, cmx_emails_meta_key('folder'), \sanitize_key($folder));
		\update_post_meta($post_id, cmx_emails_meta_key('uid'), (string) $uid);
		\update_post_meta($post_id, cmx_emails_meta_key('message_id'), $message_id);
		\update_post_meta($post_id, cmx_emails_meta_key('subject'), $subject);
		\update_post_meta($post_id, cmx_emails_meta_key('sender_name'), (string) ($sender['name'] ?? ''));
		\update_post_meta($post_id, cmx_emails_meta_key('sender_email'), (string) ($sender['email'] ?? ''));
		\update_post_meta($post_id, cmx_emails_meta_key('sender_label'), (string) ($sender['label'] ?? ''));
		\update_post_meta($post_id, cmx_emails_meta_key('to'), $to);
		\update_post_meta($post_id, cmx_emails_meta_key('cc'), $cc);
		\update_post_meta($post_id, cmx_emails_meta_key('bcc'), $bcc);
		\update_post_meta($post_id, cmx_emails_meta_key('received_ts'), (string) $ts);
		\update_post_meta($post_id, cmx_emails_meta_key('imap_seen'), !empty($overview->seen) ? '1' : '0');
		\update_post_meta($post_id, cmx_emails_meta_key('attachments'), $stored_attachments);
		\update_post_meta($post_id, cmx_emails_meta_key('attachment_count'), (string) \count($stored_attachments));
		\update_post_meta($post_id, cmx_emails_meta_key('has_attachment'), $stored_attachments !== [] ? '1' : '0');
		\update_post_meta($post_id, cmx_emails_meta_key('body_plain'), $plain);
		\update_post_meta($post_id, cmx_emails_meta_key('body_html'), $html);
		\update_post_meta($post_id, cmx_emails_meta_key('mailbox'), $mailbox);
		\update_post_meta($post_id, cmx_emails_meta_key('contact_id_auto'), (string) \max(0, $auto_contact_id));
		\update_post_meta($post_id, cmx_emails_meta_key('contact_id'), (string) \max(0, $contact_id));
		\update_post_meta($post_id, cmx_emails_meta_key('project_id'), (string) \max(0, $project_id));
		\update_post_meta($post_id, cmx_emails_meta_key('status'), $status);
		cmx_emails_update_assignment_cache($post_id);

		return ['post_id' => $post_id, 'uid' => $uid];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_open_client_folder')) {
	function cmx_emails_open_client_folder(array $client, string $folder, ?string &$resolved_mailbox = null) {
		$host = \sanitize_text_field((string) ($client['imap_host'] ?? ''));
		$user = \sanitize_email((string) ($client['email'] ?? ''));
		$pass = (string) ($client['password'] ?? '');
		$port = (int) (($client['imap_port'] ?? 993) ?: 993);
		$folder = \sanitize_key($folder);
		$resolved_mailbox = null;

		if ($host === '' || $user === '' || $pass === '' || !\function_exists('imap_open')) {
			return false;
		}

		if (\function_exists('imap_timeout')) {
			if (\defined('OPENTIMEOUT')) {
				@\imap_timeout(\OPENTIMEOUT, 12);
			}
			if (\defined('READTIMEOUT')) {
				@\imap_timeout(\READTIMEOUT, 20);
			}
		}

		$root_candidates = [
			'{' . $host . ':' . $port . '/imap/ssl}',
			'{' . $host . ':' . $port . '/imap/ssl/novalidate-cert}',
		];
		$folder_map = cmx_emails_folder_map();
		$folder_candidates = (array) ($folder_map[$folder]['candidates'] ?? ['INBOX']);

		foreach ($root_candidates as $root) {
			$available = [];
			$imap = @\imap_open($root . 'INBOX', $user, $pass);
			if ($imap === false) {
				continue;
			}

			$mailboxes = @\imap_getmailboxes($imap, $root, '*');
			if (\is_array($mailboxes)) {
				foreach ($mailboxes as $mailbox) {
					if (\is_object($mailbox) && isset($mailbox->name)) {
						$available[] = (string) $mailbox->name;
					}
				}
			}

			if ($folder === 'inbox') {
				$resolved_mailbox = $root . 'INBOX';
				return $imap;
			}

			foreach ($folder_candidates as $candidate) {
				foreach ($available as $available_mailbox) {
					if (\strcasecmp($available_mailbox, $root . $candidate) === 0 || \str_ends_with(\strtolower($available_mailbox), \strtolower($candidate))) {
						if (@\imap_reopen($imap, $available_mailbox)) {
							$resolved_mailbox = $available_mailbox;
							return $imap;
						}
					}
				}
			}

			foreach ($folder_candidates as $candidate) {
				$target = $root . $candidate;
				if (@\imap_reopen($imap, $target)) {
					$resolved_mailbox = $target;
					return $imap;
				}
			}

			@\imap_close($imap);
		}

		return false;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_sync_client_messages')) {
	function cmx_emails_sync_client_messages(string $client_id, string $folder = 'inbox', int $limit = 0): array {
		$client = cmx_emails_get_client($client_id);
		if ($client === []) {
			return ['ok' => false, 'message' => 'Kein E-Mail-Client gefunden.', 'synced' => 0];
		}
		if (!\function_exists('imap_open')) {
			return ['ok' => false, 'message' => 'PHP-IMAP ist auf diesem Server nicht verfuegbar. Bitte die PHP-Erweiterung imap aktivieren.', 'synced' => 0];
		}

		$limit = $limit > 0 ? $limit : (int) \constant(__NAMESPACE__ . '\\CMX_EMAILS_SYNC_LIMIT');
		$mailbox = '';
		$imap = cmx_emails_open_client_folder($client, $folder, $mailbox);
		if ($imap === false) {
			$error = \function_exists('imap_last_error') ? (string) \imap_last_error() : '';
			return ['ok' => false, 'message' => 'IMAP-Verbindung fehlgeschlagen.' . ($error !== '' ? ' ' . $error : ''), 'synced' => 0];
		}

		$total = (int) \imap_num_msg($imap);
		$start = \max(1, $total - $limit + 1);
		$synced = 0;
		for ($msg_no = $total; $msg_no >= $start; $msg_no--) {
			$result = cmx_emails_sync_single_message($imap, $client, $folder, $msg_no, $mailbox);
			if ((int) ($result['post_id'] ?? 0) > 0) {
				$synced++;
			}
		}

		@\imap_close($imap);

		return [
			'ok' => true,
			'message' => $synced > 0 ? ($synced . ' E-Mails synchronisiert.') : 'Keine E-Mails gefunden.',
			'synced' => $synced,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_sync_messages')) {
	function cmx_emails_sync_messages(string $client_id = '', string $folder = 'inbox', int $limit = 0): array {
		$client_id = \sanitize_key($client_id);
		$folder = \sanitize_key($folder);
		if ($folder === '') {
			$folder = 'inbox';
		}

		if ($client_id !== '') {
			return cmx_emails_sync_client_messages($client_id, $folder, $limit);
		}

		$clients = cmx_emails_client_list();
		if ($clients === []) {
			return ['ok' => false, 'message' => 'Keine E-Mail-Clients gefunden.', 'synced' => 0];
		}

		$total_synced = 0;
		$success_count = 0;
		$error_count = 0;

		foreach ($clients as $client) {
			$client = (array) $client;
			$id = \sanitize_key((string) ($client['id'] ?? ''));
			if ($id === '') {
				continue;
			}

			$result = cmx_emails_sync_client_messages($id, $folder, $limit);
			if (!empty($result['ok'])) {
				$success_count++;
				$total_synced += (int) ($result['synced'] ?? 0);
				continue;
			}

			$error_count++;
		}

		if ($success_count <= 0) {
			return ['ok' => false, 'message' => 'Synchronisierung fuer alle Konten fehlgeschlagen.', 'synced' => 0];
		}

		$message = $total_synced > 0
			? ($total_synced . ' E-Mails synchronisiert.')
			: 'Keine E-Mails gefunden.';
		if ($error_count > 0) {
			$message .= ' ' . $error_count . ' Konto/Konten konnten nicht geladen werden.';
		}

		return [
			'ok' => true,
			'message' => $message,
			'synced' => $total_synced,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_status_label')) {
	function cmx_emails_status_label(string $status): string {
		$status = \sanitize_key($status);
		return match ($status) {
			'processed' => 'Verarbeitet',
			'read' => 'Gelesen',
			default => 'Neu',
		};
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_status_class')) {
	function cmx_emails_status_class(string $status): string {
		$status = \sanitize_key($status);
		return match ($status) {
			'processed' => 'is-processed',
			'read' => 'is-read',
			default => 'is-new',
		};
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_date_label')) {
	function cmx_emails_date_label(int $timestamp): string {
		if ($timestamp <= 0) {
			return '-';
		}

		$today = \wp_date('Y-m-d');
		$yesterday = \wp_date('Y-m-d', \strtotime('-1 day'));
		$current_year = \wp_date('Y');
		$value_day = \wp_date('Y-m-d', $timestamp);

		if ($value_day === $today) {
			return 'heute, ' . \wp_date('H:i', $timestamp);
		}
		if ($value_day === $yesterday) {
			return 'gestern, ' . \wp_date('H:i', $timestamp);
		}
		if (\wp_date('Y', $timestamp) === $current_year) {
			return \wp_date('d.m., H:i', $timestamp);
		}
		return \wp_date('d.m.Y, H:i', $timestamp);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_date_label_long')) {
	function cmx_emails_date_label_long(int $timestamp): string {
		return $timestamp > 0 ? \wp_date('d.m.Y, H:i', $timestamp) . ' Uhr' : '-';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_contact_post_types')) {
	function cmx_emails_contact_post_types(): array {
		$types = [];
		foreach (['kontakte', 'kontakt', 'contact', 'contacts'] as $type) {
			if (\post_type_exists($type)) {
				$types[] = $type;
			}
		}
		return $types !== [] ? \array_values(\array_unique($types)) : ['kontakte'];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_project_post_types')) {
	function cmx_emails_project_post_types(): array {
		$types = [];
		foreach (['projekte', 'projekt', 'project', 'projects'] as $type) {
			if (\post_type_exists($type)) {
				$types[] = $type;
			}
		}
		return $types !== [] ? \array_values(\array_unique($types)) : ['projekte'];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_assignment_options')) {
	function cmx_emails_assignment_options(string $kind): array {
		$post_types = $kind === 'project' ? cmx_emails_project_post_types() : cmx_emails_contact_post_types();
		$posts = \get_posts([
			'post_type'        => $post_types,
			'post_status'      => ['publish', 'private'],
			'posts_per_page'   => 250,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => true,
			'no_found_rows'    => true,
		]);

		$options = [];
		foreach ($posts as $post) {
			if (!$post instanceof \WP_Post) {
				continue;
			}
			$options[(int) $post->ID] = (string) $post->post_title;
		}
		return $options;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_mark_as_read')) {
	function cmx_emails_mark_as_read(int $post_id): void {
		if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_EMAILS_CPT) {
			return;
		}
		$status = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('status'), true));
		if ($status === 'new') {
			\update_post_meta($post_id, cmx_emails_meta_key('read_at'), (string) \time());
			\update_post_meta($post_id, cmx_emails_meta_key('status'), 'read');
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_delete_message')) {
	function cmx_emails_delete_message(int $post_id): array {
		$post_id = (int) $post_id;
		if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_EMAILS_CPT) {
			return ['ok' => false, 'message' => 'E-Mail wurde nicht gefunden.'];
		}

		$client = cmx_emails_get_client((string) \get_post_meta($post_id, cmx_emails_meta_key('account_id'), true));
		$folder = (string) \get_post_meta($post_id, cmx_emails_meta_key('folder'), true);
		$uid = (int) \get_post_meta($post_id, cmx_emails_meta_key('uid'), true);

		if ($client !== [] && $uid > 0 && \function_exists('imap_open')) {
			$mailbox = '';
			$imap = cmx_emails_open_client_folder($client, $folder !== '' ? $folder : 'inbox', $mailbox);
			if ($imap !== false) {
				@\imap_delete($imap, (string) $uid, \FT_UID);
				@\imap_expunge($imap);
				@\imap_close($imap);
			}
		}

		\wp_trash_post($post_id);
		return ['ok' => true, 'message' => 'E-Mail wurde geloescht.'];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_import_post_attachments')) {
	function cmx_emails_import_post_attachments(int $post_id): array {
		$post_id = (int) $post_id;
		if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_EMAILS_CPT) {
			return ['ok' => false, 'message' => 'E-Mail wurde nicht gefunden.'];
		}
		if (!\function_exists(__NAMESPACE__ . '\\cmx_mail_import_create_document') || !\function_exists(__NAMESPACE__ . '\\cmx_mail_import_find_contact_by_sender')) {
			return ['ok' => false, 'message' => 'Die Mail-Import-Helfer sind nicht verfuegbar.'];
		}

		$attachments = cmx_emails_normalize_attachment_list(\get_post_meta($post_id, cmx_emails_meta_key('attachments'), true));
		if ($attachments === []) {
			return ['ok' => false, 'message' => 'Diese E-Mail hat keine gespeicherten Anhaenge.'];
		}

		$subject = (string) \get_post_meta($post_id, cmx_emails_meta_key('subject'), true);
		$sender_email = \sanitize_email((string) \get_post_meta($post_id, cmx_emails_meta_key('sender_email'), true));
		$contact_id = (int) \get_post_meta($post_id, cmx_emails_meta_key('contact_id'), true);
		if ($contact_id <= 0 && $sender_email !== '') {
			$contact_id = (int) cmx_mail_import_find_contact_by_sender($sender_email);
		}
		if ($contact_id <= 0) {
			return ['ok' => false, 'message' => 'Kein Kontakt zur Absender-Adresse gefunden.'];
		}

		$settings = \function_exists(__NAMESPACE__ . '\\cmx_mail_import_get_settings')
			? (array) cmx_mail_import_get_settings()
			: [];
		$supplier_target = \function_exists(__NAMESPACE__ . '\\cmx_mail_import_normalize_email')
			? (string) cmx_mail_import_normalize_email((string) ($settings['supplier_email'] ?? ''))
			: '';
		$to = (array) \get_post_meta($post_id, cmx_emails_meta_key('to'), true);
		$recipients = [];
		foreach ($to as $entry) {
			if (!\is_array($entry)) {
				continue;
			}
			$email = \sanitize_email((string) ($entry['email'] ?? ''));
			if ($email !== '') {
				$recipients[] = \strtolower($email);
			}
		}
		$is_supplier_mail = $supplier_target !== '' && \in_array($supplier_target, $recipients, true);
		$is_supplier_contact = $is_supplier_mail && \function_exists(__NAMESPACE__ . '\\cmx_mail_import_contact_is_supplier')
			? (bool) cmx_mail_import_contact_is_supplier($contact_id)
			: false;
		$keywords = \function_exists(__NAMESPACE__ . '\\cmx_mail_import_beleg_filter_keywords')
			? (array) cmx_mail_import_beleg_filter_keywords()
			: [];

		$created_ids = [];
		foreach ($attachments as $attachment) {
			$path = \wp_normalize_path((string) ($attachment['path'] ?? ''));
			$filename = (string) ($attachment['filename'] ?? 'attachment.pdf');
			if ($path === '' || !\is_file($path)) {
				continue;
			}
			$binary = (string) @\file_get_contents($path);
			if ($binary === '') {
				continue;
			}

			$payload = [
				'filename' => $filename,
				'mime'     => (string) ($attachment['mime'] ?? 'application/octet-stream'),
				'content'  => $binary,
			];

			$created = ['post_id' => 0];
			$is_pdf = \strtolower((string) \pathinfo($filename, \PATHINFO_EXTENSION)) === 'pdf';
			$looks_like_invoice = $is_pdf && $is_supplier_contact
				&& \function_exists(__NAMESPACE__ . '\\cmx_mail_import_pdf_keyword_match')
				&& cmx_mail_import_pdf_keyword_match($binary, $keywords);

			if ($looks_like_invoice && \function_exists(__NAMESPACE__ . '\\cmx_mail_import_create_supplier_beleg')) {
				$created = (array) cmx_mail_import_create_supplier_beleg($contact_id, $subject, $payload, [
					'sender' => $sender_email,
					'recipients' => $recipients,
				]);
			} elseif ($is_pdf) {
				$created = (array) cmx_mail_import_create_document($contact_id, $subject, $payload, [
					'sender' => $sender_email,
					'recipients' => $recipients,
				]);
			}

			$created_id = (int) ($created['post_id'] ?? 0);
			if ($created_id > 0) {
				$created_ids[] = $created_id;
			}
		}

		if ($created_ids === []) {
			return ['ok' => false, 'message' => 'Es konnte kein Anhang uebernommen werden.'];
		}

		$created_ids = \array_values(\array_unique(\array_map('intval', $created_ids)));
		\update_post_meta($post_id, cmx_emails_meta_key('imported_post_ids'), $created_ids);
		\update_post_meta($post_id, cmx_emails_meta_key('manual_status'), 'processed');
		\update_post_meta($post_id, cmx_emails_meta_key('status'), 'processed');
		\update_post_meta($post_id, cmx_emails_meta_key('imported_at'), (string) \time());

		return ['ok' => true, 'message' => \count($created_ids) . ' Anhang/Anhaenge uebernommen.', 'created_ids' => $created_ids];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_direction_meta_query')) {
	function cmx_emails_direction_meta_query(string $direction): array {
		$direction = \sanitize_key($direction);

		if ($direction === 'outgoing') {
			return [
				'relation' => 'OR',
				['key' => cmx_emails_meta_key('direction'), 'value' => 'outgoing'],
				['key' => cmx_emails_meta_key('folder'), 'value' => ['sent', 'drafts'], 'compare' => 'IN'],
			];
		}

		if ($direction === 'incoming') {
			return [
				'relation' => 'AND',
				[
					'relation' => 'OR',
					['key' => cmx_emails_meta_key('direction'), 'compare' => 'NOT EXISTS'],
					['key' => cmx_emails_meta_key('direction'), 'value' => 'outgoing', 'compare' => '!='],
				],
				[
					'relation' => 'OR',
					['key' => cmx_emails_meta_key('folder'), 'compare' => 'NOT EXISTS'],
					['key' => cmx_emails_meta_key('folder'), 'value' => ['sent', 'drafts'], 'compare' => 'NOT IN'],
				],
			];
		}

		return [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_build_meta_query')) {
	function cmx_emails_build_meta_query(array $filters = []): array {
		$meta_query = [];

		$account_id = \sanitize_key((string) ($filters['account_id'] ?? ''));
		if ($account_id !== '') {
			$meta_query[] = ['key' => cmx_emails_meta_key('account_id'), 'value' => $account_id];
		}

		$folder = \sanitize_key((string) ($filters['folder'] ?? ''));
		if ($folder !== '') {
			$meta_query[] = ['key' => cmx_emails_meta_key('folder'), 'value' => $folder];
		}

		$status = \sanitize_key((string) ($filters['status'] ?? ''));
		if ($status !== '') {
			$meta_query[] = ['key' => cmx_emails_meta_key('status'), 'value' => $status];
		}

		$direction = \sanitize_key((string) ($filters['direction'] ?? ''));
		if ($direction !== '') {
			$direction_query = cmx_emails_direction_meta_query($direction);
			if ($direction_query !== []) {
				$meta_query[] = $direction_query;
			}
		}

		if (!empty($filters['has_attachment'])) {
			$meta_query[] = ['key' => cmx_emails_meta_key('has_attachment'), 'value' => '1'];
		}

		if (!empty($filters['unassigned'])) {
			$meta_query[] = ['key' => cmx_emails_meta_key('contact_id'), 'value' => '0'];
			$meta_query[] = ['key' => cmx_emails_meta_key('project_id'), 'value' => '0'];
		}

		return $meta_query;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_build_tax_query')) {
	function cmx_emails_build_tax_query(array $filters = []): array {
		$tax_query = [];

		$category = \sanitize_title((string) ($filters['category'] ?? ''));
		if ($category !== '') {
			$tax = \function_exists(__NAMESPACE__ . '\\cmx_emails_category_taxonomy')
				? (string) cmx_emails_category_taxonomy()
				: 'emails_kategorien';
			if (\taxonomy_exists($tax) && \is_object_in_taxonomy(CMX_EMAILS_CPT, $tax)) {
				$tax_query[] = [
					'taxonomy' => $tax,
					'field' => 'slug',
					'terms' => [$category],
				];
			}
		}

		return $tax_query;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_query_args')) {
	function cmx_emails_query_args(array $filters = [], array $overrides = []): array {
		$args = [
			'post_type'        => CMX_EMAILS_CPT,
			'post_status'      => ['publish'],
			'posts_per_page'   => (int) ($filters['posts_per_page'] ?? 25),
			'paged'            => \max(1, (int) ($filters['paged'] ?? 1)),
			'meta_key'         => cmx_emails_meta_key('received_ts'),
			'orderby'          => 'meta_value_num',
			'order'            => 'DESC',
			'suppress_filters' => false,
		];

		$search = \trim((string) ($filters['s'] ?? ''));
		if ($search !== '') {
			$args['s'] = $search;
		}

		$meta_query = cmx_emails_build_meta_query($filters);
		if ($meta_query !== []) {
			$args['meta_query'] = $meta_query;
		}

		$tax_query = cmx_emails_build_tax_query($filters);
		if ($tax_query !== []) {
			$args['tax_query'] = $tax_query;
		}

		return \array_merge($args, $overrides);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_count')) {
	function cmx_emails_count(array $filters = []): int {
		$query = new \WP_Query(cmx_emails_query_args($filters, [
			'fields' => 'ids',
			'posts_per_page' => 1,
			'paged' => 1,
		]));
		return (int) $query->found_posts;
	}
}
