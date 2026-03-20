<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_CRON_HOOK')) {
	\define(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_CRON_HOOK', 'cmx_mail_import_imap_cron');
}

if (!\defined(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_CRON_INTERVAL')) {
	\define(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_CRON_INTERVAL', 'cmx_mail_import_5min');
}

if (!\defined(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_MAX_CONTACTS_SCAN')) {
	\define(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_MAX_CONTACTS_SCAN', 5000);
}

if (!\defined(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_EVENT_LOG_OPTION')) {
	\define(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_EVENT_LOG_OPTION', 'cmx_mail_import_event_log');
}

if (!\defined(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_EVENT_LOG_LIMIT')) {
	\define(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_EVENT_LOG_LIMIT', 300);
}

if (!\defined(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_RUN_LOG_OPTION')) {
	\define(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_RUN_LOG_OPTION', 'cmx_mail_import_run_log');
}

if (!\defined(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_RUN_LOG_LIMIT')) {
	\define(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_RUN_LOG_LIMIT', 200);
}

if (!\defined(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_LOG_FILENAME')) {
	\define(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_LOG_FILENAME', 'cmx-mail-import.log');
}

if (!\defined(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_LOG_MAX_LINES')) {
	\define(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_LOG_MAX_LINES', 500);
}

function cmx_mail_import_log_file_path(): string {
	$upload_data = \wp_get_upload_dir();
	$base_dir = \wp_normalize_path((string) ($upload_data['basedir'] ?? ''));
	if ($base_dir === '') {
		return '';
	}
	$dir = \trailingslashit($base_dir) . 'misbuero/scanner';
	if (!\is_dir($dir)) {
		@\wp_mkdir_p($dir);
	}
	if (!\is_dir($dir) || !\is_writable($dir)) {
		return '';
	}
	return \trailingslashit($dir) . (string) \constant(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_LOG_FILENAME');
}

function cmx_mail_import_trim_log_file(string $log_file, int $max_lines = 500): void {
	$max_lines = \max(1, $max_lines);
	if ($log_file === '' || !\is_file($log_file) || !\is_readable($log_file) || !\is_writable($log_file)) {
		return;
	}

	$lines = @\file($log_file, \FILE_IGNORE_NEW_LINES);
	if (!\is_array($lines) || \count($lines) <= $max_lines) {
		return;
	}

	$tail = \array_slice($lines, -$max_lines);
	@\file_put_contents($log_file, \implode(\PHP_EOL, $tail) . \PHP_EOL, \LOCK_EX);
}

function cmx_mail_import_log(string $message, array $context = []): void {
	$payload = $context ? ' | ' . \wp_json_encode($context, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) : '';
	$line = '[' . \wp_date('Y-m-d H:i:s') . '] [CMX MAIL IMPORT] ' . $message . $payload;
	\error_log($line);

	$log_file = cmx_mail_import_log_file_path();
	if ($log_file !== '') {
		@\file_put_contents($log_file, $line . \PHP_EOL, \FILE_APPEND | \LOCK_EX);
		cmx_mail_import_trim_log_file(
			$log_file,
			(int) \constant(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_LOG_MAX_LINES')
		);
	}
}

function cmx_mail_import_build_run_id(): string {
	return \wp_date('d.m.Y H:i:s');
}

function cmx_mail_import_run_id_label(string $run_id): string {
	$run_id = \trim($run_id);
	if (\preg_match('/^run-(\d{8})-(\d{6})(?:-[a-z0-9]+)?$/i', $run_id, $match) !== 1) {
		return $run_id;
	}

	$yyyymmdd = (string) ($match[1] ?? '');
	$hhmmss = (string) ($match[2] ?? '');
	if (\strlen($yyyymmdd) !== 8 || \strlen($hhmmss) !== 6) {
		return $run_id;
	}

	$year = \substr($yyyymmdd, 0, 4);
	$month = \substr($yyyymmdd, 4, 2);
	$day = \substr($yyyymmdd, 6, 2);
	$hour = \substr($hhmmss, 0, 2);
	$minute = \substr($hhmmss, 2, 2);
	$second = \substr($hhmmss, 4, 2);

	return $day . '.' . $month . '.' . $year . ' ' . $hour . ':' . $minute . ':' . $second;
}

function cmx_mail_import_run_query_value(string $run_id): string {
	$run_id = \trim($run_id);
	if ($run_id === '') {
		return '';
	}

	if (\preg_match('/^run-(\d{8})-(\d{6})(?:-[a-z0-9]+)?$/i', $run_id, $match) === 1) {
		return (string) ($match[1] . '-' . $match[2]);
	}

	if (\preg_match('/^(\d{2})\.(\d{2})\.(\d{4}) (\d{2}):(\d{2}):(\d{2})$/', $run_id, $match) === 1) {
		return (string) ($match[3] . $match[2] . $match[1] . '-' . $match[4] . $match[5] . $match[6]);
	}

	return $run_id;
}

function cmx_mail_import_event_log_option_name(): string {
	return (string) \constant(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_EVENT_LOG_OPTION');
}

function cmx_mail_import_get_event_log(): array {
	$entries = \get_option(cmx_mail_import_event_log_option_name(), []);
	if (!\is_array($entries)) {
		return [];
	}
	return \array_values(\array_filter($entries, static function ($entry): bool {
		return \is_array($entry);
	}));
}

function cmx_mail_import_run_log_option_name(): string {
	return (string) \constant(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_RUN_LOG_OPTION');
}

function cmx_mail_import_is_empty_run_entry(array $entry): bool {
	$imported = (int) ($entry['imported_items'] ?? 0);
	return $imported <= 0;
}

function cmx_mail_import_get_run_log(): array {
	$stored = \get_option(cmx_mail_import_run_log_option_name(), []);
	if (!\is_array($stored)) {
		return [];
	}
	$entries = \array_values(\array_filter($stored, static function ($entry): bool {
		return \is_array($entry) && !cmx_mail_import_is_empty_run_entry($entry);
	}));
	if (\count($entries) !== \count($stored)) {
		cmx_mail_import_save_run_log($entries);
	}
	return $entries;
}

function cmx_mail_import_save_run_log(array $entries): void {
	$limit = (int) \constant(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_RUN_LOG_LIMIT');
	if ($limit <= 0) {
		$limit = 200;
	}
	$entries = \array_slice(\array_values($entries), 0, $limit);
	\update_option(cmx_mail_import_run_log_option_name(), $entries, false);
}

function cmx_mail_import_register_run(array $result): void {
	$run_id = \sanitize_text_field((string) ($result['run_id'] ?? ''));
	if ($run_id === '') {
		return;
	}

	$entry = [
		'ts' => \time(),
		'run_id' => $run_id,
		'source' => \sanitize_key((string) ($result['source'] ?? '')),
		'status' => \sanitize_key((string) ($result['status'] ?? 'unknown')),
		'unseen_messages' => (int) ($result['unseen_messages'] ?? 0),
		'processed_messages' => (int) ($result['processed_messages'] ?? 0),
		'imported_items' => (int) ($result['imported_items'] ?? 0),
		'skipped_messages' => (int) ($result['skipped_messages'] ?? 0),
		'skip_reasons' => (array) ($result['skip_reasons'] ?? []),
	];
	if (cmx_mail_import_is_empty_run_entry($entry)) {
		return;
	}

	$entries = \array_values(\array_filter(cmx_mail_import_get_run_log(), static function ($row) use ($run_id): bool {
		return (string) ($row['run_id'] ?? '') !== $run_id;
	}));
	\array_unshift($entries, $entry);
	cmx_mail_import_save_run_log($entries);
}

function cmx_mail_import_save_event_log(array $entries): void {
	$limit = (int) \constant(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_EVENT_LOG_LIMIT');
	if ($limit <= 0) {
		$limit = 300;
	}
	$entries = \array_slice(\array_values($entries), 0, $limit);
	\update_option(cmx_mail_import_event_log_option_name(), $entries, false);
}

function cmx_mail_import_append_event_log(array $entry): void {
	$entries = cmx_mail_import_get_event_log();
	\array_unshift($entries, $entry);
	cmx_mail_import_save_event_log($entries);
}

function cmx_mail_import_register_event(array $context): void {
	$run_id = \sanitize_text_field((string) ($context['run_id'] ?? ''));
	$type = \sanitize_key((string) ($context['type'] ?? ''));
	$rule = \sanitize_key((string) ($context['rule'] ?? ''));
	$status = \sanitize_key((string) ($context['status'] ?? 'imported'));
	$reason = \sanitize_key((string) ($context['reason'] ?? ''));
	$subject = \sanitize_text_field((string) ($context['subject'] ?? ''));
	$sender = cmx_mail_import_normalize_email((string) ($context['sender'] ?? ''));
	$recipients = cmx_mail_import_parse_email_list($context['recipients'] ?? []);
	$filename = \sanitize_file_name((string) ($context['filename'] ?? ''));
	$upload_rel = \ltrim(\sanitize_text_field((string) ($context['upload_rel'] ?? '')), '/');
	$message_no = (int) ($context['message_no'] ?? 0);
	$kontakt_id = (int) ($context['kontakt_id'] ?? 0);
	$target_post_id = (int) ($context['target_post_id'] ?? 0);

	$entry = [
		'ts' => \time(),
		'run_id' => $run_id,
		'type' => $type,
		'rule' => $rule,
		'status' => $status,
		'reason' => $reason,
		'subject' => $subject,
		'message_no' => $message_no,
		'kontakt_id' => $kontakt_id,
		'kontakt_title' => $kontakt_id > 0 ? \sanitize_text_field((string) \get_the_title($kontakt_id)) : '',
		'sender' => $sender,
		'recipients' => $recipients,
		'filename' => $filename,
		'target_post_id' => $target_post_id,
		'target_post_type' => $target_post_id > 0 ? \sanitize_key((string) \get_post_type($target_post_id)) : '',
		'upload_rel' => $upload_rel,
	];

	cmx_mail_import_append_event_log($entry);
}

function cmx_mail_import_get_events_for_run(string $run_id, int $limit = 50): array {
	$run_id = \sanitize_text_field($run_id);
	if ($run_id === '') {
		return [];
	}
	$out = [];
	foreach (cmx_mail_import_get_event_log() as $entry) {
		if ((string) ($entry['run_id'] ?? '') !== $run_id) {
			continue;
		}
		$out[] = $entry;
		if (\count($out) >= $limit) {
			break;
		}
	}
	return $out;
}

function cmx_mail_import_get_events_for_run_query(string $run_query, int $limit = 50): array {
	$run_query = \trim(\sanitize_text_field($run_query));
	if ($run_query === '') {
		return cmx_mail_import_get_recent_events($limit);
	}

	$needle = \strtolower($run_query);
	$out = [];
	foreach (cmx_mail_import_get_event_log() as $entry) {
		$run_id = \sanitize_text_field((string) ($entry['run_id'] ?? ''));
		if ($run_id === '') {
			continue;
		}
		$label = \strtolower(cmx_mail_import_run_id_label($run_id));
		$query_value = \strtolower(cmx_mail_import_run_query_value($run_id));
		if (\strpos(\strtolower($run_id), $needle) === false && \strpos($label, $needle) === false && \strpos($query_value, $needle) === false) {
			continue;
		}
		$out[] = $entry;
		if (\count($out) >= $limit) {
			break;
		}
	}
	return $out;
}

function cmx_mail_import_get_recent_events(int $limit = 50): array {
	$limit = \max(1, $limit);
	return \array_slice(cmx_mail_import_get_event_log(), 0, $limit);
}

function cmx_mail_import_should_mark_seen_for_reason(string $reason): bool {
	$reason = \sanitize_key($reason);
	if ($reason === '') {
		return false;
	}

	// Diese Faelle sind in der Regel nicht importierbar und sollen nicht in jedem Lauf erneut geprueft werden.
	return \in_array($reason, [
		'kontakt_not_found',
		'no_pdf',
		'missing_sender',
		'missing_header',
	], true);
}

function cmx_mail_import_count_events_today(): int {
	$today = \wp_date('Y-m-d');
	$count = 0;
	foreach (cmx_mail_import_get_event_log() as $entry) {
		$ts = (int) ($entry['ts'] ?? 0);
		if ($ts <= 0) {
			continue;
		}
		if (\wp_date('Y-m-d', $ts) === $today) {
			$count++;
		}
	}
	return $count;
}

function cmx_mail_import_stamp_post(int $post_id, array $context, string $upload_rel): void {
	if ($post_id <= 0) {
		return;
	}
	$run_id = \sanitize_text_field((string) ($context['run_id'] ?? ''));
	$sender = cmx_mail_import_normalize_email((string) ($context['sender'] ?? ''));
	$recipients = cmx_mail_import_parse_email_list($context['recipients'] ?? []);

	\update_post_meta($post_id, '_cmx_mail_import_auto', '1');
	\update_post_meta($post_id, '_cmx_mail_import_at', \time());
	\update_post_meta($post_id, '_cmx_mail_import_run_id', $run_id);
	\update_post_meta($post_id, '_cmx_mail_import_sender', $sender);
	\update_post_meta($post_id, '_cmx_mail_import_recipients', \implode(', ', $recipients));
	\update_post_meta($post_id, '_cmx_mail_import_upload_rel', \ltrim((string) $upload_rel, '/'));
}

function cmx_mail_import_settings_option_name(): string {
	if (\defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')) {
		$value = (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN');
		if ($value !== '') {
			return $value;
		}
	}
	return 'cmx_einstellungen';
}

function cmx_mail_import_get_settings(): array {
	$option_name = cmx_mail_import_settings_option_name();
	$opts = (array) \get_option($option_name, []);

	$email_address = \sanitize_email((string) ($opts['email_address'] ?? \get_option('cmx_email_address', '')));
	$email_password = (string) ($opts['email_password'] ?? \get_option('cmx_email_password', ''));
	$imap_host = \sanitize_text_field((string) ($opts['imap_host'] ?? \get_option('cmx_imap_host', '')));

	$supplier = \sanitize_email((string) ($opts['supplier'] ?? ''));
	if (!\is_email($supplier)) {
		$supplier = \sanitize_email((string) ($opts['email_supplier'] ?? \get_option('cmx_email_supplier', '')));
	}

	return [
		'option_name' => $option_name,
		'email_address' => \is_email($email_address) ? $email_address : '',
		'email_password' => $email_password,
		'imap_host' => $imap_host,
		'imap_port' => 993,
		'supplier_email' => \is_email($supplier) ? $supplier : '',
	];
}

function cmx_mail_import_support_user_switch_enabled(): bool {
	$opts = (array) \get_option(cmx_mail_import_settings_option_name(), []);
	$value = $opts['support_user_switch'] ?? '';

	if (\is_bool($value)) {
		return $value;
	}

	return \in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
}

function cmx_mail_import_manual_state_key(string $run_id): string {
	$run_id = \trim($run_id);
	if ($run_id === '') {
		return '';
	}

	return 'cmx_mail_import_state_' . (int) \get_current_user_id() . '_' . \md5($run_id);
}

function cmx_mail_import_get_manual_state(string $run_id): array {
	$key = cmx_mail_import_manual_state_key($run_id);
	if ($key === '') {
		return [];
	}

	$state = \get_transient($key);
	return \is_array($state) ? $state : [];
}

function cmx_mail_import_save_manual_state(string $run_id, array $state): void {
	$key = cmx_mail_import_manual_state_key($run_id);
	if ($key === '') {
		return;
	}

	\set_transient($key, $state, 30 * \MINUTE_IN_SECONDS);
}

function cmx_mail_import_delete_manual_state(string $run_id): void {
	$key = cmx_mail_import_manual_state_key($run_id);
	if ($key === '') {
		return;
	}

	\delete_transient($key);
}

function cmx_mail_import_normalize_email(string $raw): string {
	$email = \sanitize_email((string) $raw);
	return \is_email($email) ? \strtolower($email) : '';
}

function cmx_mail_import_email_regex_all(string $raw): array {
	$raw = (string) $raw;
	if ($raw === '') {
		return [];
	}
	\preg_match_all('/[A-Z0-9._%+\'+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,63}/i', $raw, $matches);
	$found = [];
	foreach ((array) ($matches[0] ?? []) as $candidate) {
		$email = cmx_mail_import_normalize_email((string) $candidate);
		if ($email !== '') {
			$found[$email] = $email;
		}
	}
	return \array_values($found);
}

function cmx_mail_import_parse_email_list($value): array {
	$items = [];
	if (\is_array($value)) {
		$items = $value;
	} else {
		$items = \preg_split('/[,\s;]+/', (string) $value) ?: [];
	}
	$out = [];
	foreach ($items as $item) {
		$email = cmx_mail_import_normalize_email((string) $item);
		if ($email !== '') {
			$out[$email] = $email;
		}
	}
	return \array_values($out);
}

function cmx_mail_import_flatten_keywords($value): array {
	$words = [];
	if (\is_array($value)) {
		foreach ($value as $entry) {
			$words = \array_merge($words, cmx_mail_import_flatten_keywords($entry));
		}
	} elseif (\is_scalar($value)) {
		$parts = \preg_split('/\s*,\s*/', (string) $value) ?: [];
		foreach ($parts as $part) {
			$part = \trim((string) $part, " \t\n\r\0\x0B\"'");
			if ($part !== '') {
				$words[] = \strtolower($part);
			}
		}
	}
	$words = \array_values(\array_unique(\array_filter($words, static function ($word): bool {
		return \is_string($word) && $word !== '';
	})));
	return $words;
}

function cmx_mail_import_beleg_filter_keywords(): array {
	$keywords = [];

	if (\function_exists(__NAMESPACE__ . '\\cmx_ini_get_value')) {
		// Quelle wie gefordert: globales.ini -> Belege/Filter
		// (= Sektion "Belege", Key "Filter")
		$keywords = \array_merge($keywords, cmx_mail_import_flatten_keywords(cmx_ini_get_value('Belege', 'Filter')));
	}

	if (empty($keywords)) {
		$ini_file = \dirname(__DIR__, 2) . '/includes/globales.ini';
		if (\is_file($ini_file)) {
			$ini = \parse_ini_file($ini_file, true, \INI_SCANNER_TYPED);
			if (\is_array($ini)) {
				foreach ($ini as $section => $section_data) {
					if (!\is_array($section_data)) {
						continue;
					}
						$section_name = \strtolower(\trim((string) $section));
						if ($section_name !== 'belege') {
							continue;
						}
						foreach ($section_data as $key => $value) {
							$key_name = \strtolower(\trim((string) $key));
							if ($key_name !== 'filter') {
								continue;
							}
							$keywords = \array_merge($keywords, cmx_mail_import_flatten_keywords($value));
						}
				}
			}
		}
	}

	return \array_values(\array_unique($keywords));
}

function cmx_mail_import_contact_post_types(): array {
	$candidates = ['kontakte', 'kontakt', 'contact'];
	$post_types = [];
	foreach ($candidates as $post_type) {
		if (\post_type_exists($post_type)) {
			$post_types[] = $post_type;
		}
	}
	return \array_values(\array_unique($post_types));
}

function cmx_mail_import_collect_contact_emails(int $kontakt_id): array {
	$out = [];
	$add = static function (string $raw) use (&$out): void {
		$items = \preg_split('/[,\s;]+/', \trim((string) $raw)) ?: [];
		foreach ($items as $item) {
			$email = cmx_mail_import_normalize_email((string) $item);
			if ($email !== '') {
				$out[$email] = $email;
			}
		}
	};

	$bundle = \get_post_meta($kontakt_id, '_cmx_kommunikation', true);
	if (\is_array($bundle)) {
		for ($i = 1; $i <= 3; $i++) {
			$add((string) ($bundle['email'][$i]['value'] ?? ''));
			$add((string) ($bundle['email'][$i] ?? ''));
			$add((string) ($bundle['email_' . $i] ?? ''));
		}
	}

	foreach (['_cmx_email_1', '_cmx_email_2', '_cmx_email_3'] as $key) {
		$add((string) \get_post_meta($kontakt_id, $key, true));
	}

	$fallback_keys = (array) \apply_filters('cmx_kontakte_email1_meta_keys', [
		'cmx_email_1', 'email_1', 'e_mail_1', 'kontakt_email', 'email', 'e_mail', 'mail',
	]);
	foreach ($fallback_keys as $key) {
		$key = \trim((string) $key);
		if ($key === '') {
			continue;
		}
		$add((string) \get_post_meta($kontakt_id, $key, true));
	}

	return \array_values($out);
}

function cmx_mail_import_contact_is_supplier(int $kontakt_id): bool {
	if ($kontakt_id <= 0) {
		return false;
	}

	if (\function_exists(__NAMESPACE__ . '\\cmx_is_lieferant_unified')) {
		return (bool) cmx_is_lieferant_unified($kontakt_id);
	}

	foreach (['is_supplier', '_is_supplier', 'lieferant', '_lieferant'] as $meta_key) {
		$value = \strtolower(\trim((string) \get_post_meta($kontakt_id, $meta_key, true)));
		if (\in_array($value, ['1', 'true', 'yes', 'on', 'ja', 'wahr'], true)) {
			return true;
		}
	}

	$taxonomies = ['lieferant', 'kontakt_type', 'kundenart', 'stufen', 'kontakt_kategorie', 'kontakte_kategorien'];
	$supplier_slugs = ['lieferant', 'supplier', 'lieferanten', 'vendor', 'lieferfirma'];
	foreach ($taxonomies as $taxonomy) {
		if (!\taxonomy_exists($taxonomy)) {
			continue;
		}
		$terms = \get_the_terms($kontakt_id, $taxonomy);
		if (\is_wp_error($terms) || empty($terms)) {
			continue;
		}
		foreach ($terms as $term) {
			$slug = \is_object($term) ? \strtolower((string) $term->slug) : '';
			if ($slug !== '' && \in_array($slug, $supplier_slugs, true)) {
				return true;
			}
		}
	}

	return false;
}

function cmx_mail_import_find_contact_by_sender(string $sender_email): int {
	$sender_email = cmx_mail_import_normalize_email($sender_email);
	if ($sender_email === '') {
		return 0;
	}

	$post_types = cmx_mail_import_contact_post_types();
	if (empty($post_types)) {
		return 0;
	}

	$direct_keys = ['_cmx_email_1', '_cmx_email_2', '_cmx_email_3', 'cmx_email_1', 'email_1', 'e_mail_1', 'kontakt_email', 'email', 'e_mail', 'mail'];
	$meta_query = ['relation' => 'OR'];
	foreach ($direct_keys as $key) {
		$meta_query[] = [
			'key' => $key,
			'value' => $sender_email,
			'compare' => '=',
		];
	}

	$direct_matches = \get_posts([
		'post_type' => $post_types,
		'post_status' => 'publish',
		'posts_per_page' => 50,
		'fields' => 'ids',
		'meta_query' => $meta_query,
		'no_found_rows' => true,
		'suppress_filters' => true,
	]);
	foreach ((array) $direct_matches as $kontakt_id) {
		$kontakt_id = (int) $kontakt_id;
		if ($kontakt_id <= 0) {
			continue;
		}
		$emails = cmx_mail_import_collect_contact_emails($kontakt_id);
		foreach ($emails as $email) {
			if (\strcasecmp($email, $sender_email) === 0) {
				return $kontakt_id;
			}
		}
	}

	$all_contacts = \get_posts([
		'post_type' => $post_types,
		'post_status' => 'publish',
		'posts_per_page' => (int) CMX_MAIL_IMPORT_MAX_CONTACTS_SCAN,
		'fields' => 'ids',
		'orderby' => 'modified',
		'order' => 'DESC',
		'no_found_rows' => true,
		'suppress_filters' => true,
	]);

	foreach ((array) $all_contacts as $kontakt_id) {
		$kontakt_id = (int) $kontakt_id;
		if ($kontakt_id <= 0) {
			continue;
		}
		$emails = cmx_mail_import_collect_contact_emails($kontakt_id);
		foreach ($emails as $email) {
			if (\strcasecmp($email, $sender_email) === 0) {
				return $kontakt_id;
			}
		}
	}

	return 0;
}

function cmx_mail_import_header_sender_email($header): string {
	if (\is_object($header) && isset($header->from) && \is_array($header->from) && !empty($header->from[0])) {
		$from = $header->from[0];
		$mailbox = isset($from->mailbox) ? (string) $from->mailbox : '';
		$host = isset($from->host) ? (string) $from->host : '';
		$email = cmx_mail_import_normalize_email($mailbox . '@' . $host);
		if ($email !== '') {
			return $email;
		}
	}

	if (\is_object($header) && isset($header->fromaddress)) {
		$emails = cmx_mail_import_email_regex_all((string) $header->fromaddress);
		if (!empty($emails)) {
			return (string) $emails[0];
		}
	}

	return '';
}

function cmx_mail_import_header_recipient_emails($imap, int $msg_no, $header): array {
	$recipients = [];
	$add = static function (string $email) use (&$recipients): void {
		$email = cmx_mail_import_normalize_email($email);
		if ($email !== '') {
			$recipients[$email] = $email;
		}
	};

	$collect_imap_list = static function ($list) use ($add): void {
		if (!\is_array($list)) {
			return;
		}
		foreach ($list as $entry) {
			if (!\is_object($entry)) {
				continue;
			}
			$mailbox = isset($entry->mailbox) ? (string) $entry->mailbox : '';
			$host = isset($entry->host) ? (string) $entry->host : '';
			$add($mailbox . '@' . $host);
		}
	};

	if (\is_object($header)) {
		$collect_imap_list($header->to ?? null);
		$collect_imap_list($header->cc ?? null);
		if (isset($header->toaddress)) {
			foreach (cmx_mail_import_email_regex_all((string) $header->toaddress) as $email) {
				$add($email);
			}
		}
	}

	$raw_header = (string) \imap_fetchheader($imap, $msg_no, \FT_PREFETCHTEXT);
	if ($raw_header !== '') {
		foreach ((array) \preg_split('/\r\n|\r|\n/', $raw_header) as $line) {
			$line = (string) $line;
			if (\stripos($line, 'To:') === 0 || \stripos($line, 'Cc:') === 0 || \stripos($line, 'Delivered-To:') === 0 || \stripos($line, 'Envelope-To:') === 0 || \stripos($line, 'X-Original-To:') === 0) {
				foreach (cmx_mail_import_email_regex_all($line) as $email) {
					$add($email);
				}
			}
		}
	}

	return \array_values($recipients);
}

function cmx_mail_import_decode_mime_header(string $value): string {
	$value = \trim($value);
	if ($value === '') {
		return '';
	}
	$decoded = \imap_mime_header_decode($value);
	if (!\is_array($decoded) || empty($decoded)) {
		return $value;
	}
	$out = '';
	foreach ($decoded as $part) {
		if (!\is_object($part) || !isset($part->text)) {
			continue;
		}
		$out .= (string) $part->text;
	}
	return $out !== '' ? $out : $value;
}

function cmx_mail_import_get_mime_type($part): string {
	$type_map = ['text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'other'];
	$type_id = \is_object($part) && isset($part->type) ? (int) $part->type : 7;
	$primary = $type_map[$type_id] ?? 'other';
	$subtype = \is_object($part) && isset($part->subtype) ? \strtolower((string) $part->subtype) : 'octet-stream';
	return \strtolower($primary . '/' . $subtype);
}

function cmx_mail_import_part_filename($part): string {
	$params = [];
	if (\is_object($part) && isset($part->parameters) && \is_array($part->parameters)) {
		$params = \array_merge($params, $part->parameters);
	}
	if (\is_object($part) && isset($part->dparameters) && \is_array($part->dparameters)) {
		$params = \array_merge($params, $part->dparameters);
	}

	$multipart_names = [
		'filename' => [],
		'name' => [],
	];
	$single_names = [
		'filename' => '',
		'name' => '',
	];

	foreach ($params as $param) {
		if (!\is_object($param)) {
			continue;
		}
		$attr = \strtolower((string) ($param->attribute ?? ''));
		$value_raw = (string) ($param->value ?? '');
		$value = cmx_mail_import_decode_mime_header($value_raw);

		if (\preg_match('/^(filename|name)\\*(\\d+)\\*?$/', $attr, $match) === 1) {
			$key = (string) ($match[1] ?? '');
			$idx = (int) ($match[2] ?? 0);
			$value = (string) \rawurldecode($value);
			if ($value !== '') {
				$multipart_names[$key][$idx] = $value;
			}
			continue;
		}

		if (\preg_match('/^(filename|name)\\*$/', $attr, $match) === 1) {
			$key = (string) ($match[1] ?? '');
			$value = (string) \rawurldecode($value);
			// RFC2231: charset'lang'value
			if (\preg_match("/^[^']*'[^']*'(.*)$/", $value, $m) === 1) {
				$value = (string) ($m[1] ?? '');
			}
			if ($value !== '') {
				$single_names[$key] = $value;
			}
			continue;
		}

		if ($attr === 'filename' || $attr === 'name') {
			if ($value !== '') {
				$single_names[$attr] = $value;
			}
		}
	}

	foreach (['filename', 'name'] as $key) {
		if (!empty($multipart_names[$key])) {
			\ksort($multipart_names[$key], \SORT_NUMERIC);
			$combined = \sanitize_file_name(\implode('', $multipart_names[$key]));
			if ($combined !== '') {
				return $combined;
			}
		}
		$single = \sanitize_file_name((string) ($single_names[$key] ?? ''));
		if ($single !== '') {
			return $single;
		}
	}

	return '';
}

function cmx_mail_import_collect_pdf_parts_walk($part, string $part_no, array &$parts): void {
	if (!\is_object($part)) {
		return;
	}

	$mime = cmx_mail_import_get_mime_type($part);
	$filename = cmx_mail_import_part_filename($part);
	$is_pdf_by_name = $filename !== '' && \strtolower((string) \pathinfo($filename, \PATHINFO_EXTENSION)) === 'pdf';

	if ($mime === 'application/pdf' || $is_pdf_by_name) {
		$parts[] = [
			'part_no' => $part_no !== '' ? $part_no : '1',
			'encoding' => isset($part->encoding) ? (int) $part->encoding : 0,
			'filename' => $filename !== '' ? $filename : 'attachment.pdf',
		];
	}

	if (isset($part->parts) && \is_array($part->parts) && !empty($part->parts)) {
		foreach ($part->parts as $idx => $sub_part) {
			$sub_no = $part_no === '' ? (string) ($idx + 1) : ($part_no . '.' . ($idx + 1));
			cmx_mail_import_collect_pdf_parts_walk($sub_part, $sub_no, $parts);
		}
	}
}

function cmx_mail_import_decode_part_body(string $body, int $encoding): string {
	if ($encoding === 3) {
		$decoded = \base64_decode($body, true);
		return \is_string($decoded) ? $decoded : '';
	}
	if ($encoding === 4) {
		return (string) \quoted_printable_decode($body);
	}
	return $body;
}

function cmx_mail_import_collect_pdf_attachments($imap, int $msg_no): array {
	$structure = \imap_fetchstructure($imap, $msg_no);
	if (!$structure || !\is_object($structure)) {
		return [];
	}

	$parts = [];
	cmx_mail_import_collect_pdf_parts_walk($structure, '', $parts);
	if (empty($parts)) {
		return [];
	}

	$attachments = [];
	foreach ($parts as $part_info) {
		$part_no = (string) ($part_info['part_no'] ?? '1');
		$raw = (string) \imap_fetchbody($imap, $msg_no, $part_no, \FT_PEEK);
		if ($raw === '') {
			continue;
		}
		$content = cmx_mail_import_decode_part_body($raw, (int) ($part_info['encoding'] ?? 0));
		if ($content === '') {
			continue;
		}
		$attachments[] = [
			'filename' => \sanitize_file_name((string) ($part_info['filename'] ?? 'attachment.pdf')),
			'mime' => 'application/pdf',
			'content' => $content,
		];
	}

	return $attachments;
}

function cmx_mail_import_pdf_keyword_match(string $pdf_binary, array $keywords): bool {
	$keywords = cmx_mail_import_flatten_keywords($keywords);
	if (empty($keywords)) {
		return false;
	}

	$haystacks = [];
	$haystacks[] = \strtolower($pdf_binary);

	if (\preg_match_all('/stream\\r?\\n(.*?)\\r?\\nendstream/s', $pdf_binary, $matches)) {
		foreach ((array) ($matches[1] ?? []) as $stream) {
			$stream = (string) $stream;
			$stream = \trim($stream, "\r\n");
			if ($stream === '') {
				continue;
			}

			$decoded_variants = [$stream];
			$gzu = @\gzuncompress($stream);
			if (\is_string($gzu) && $gzu !== '') {
				$decoded_variants[] = $gzu;
			}
			$gzi = @\gzinflate($stream);
			if (\is_string($gzi) && $gzi !== '') {
				$decoded_variants[] = $gzi;
			}
			$gzdecode = \function_exists('gzdecode') ? @\gzdecode($stream) : false;
			if (\is_string($gzdecode) && $gzdecode !== '') {
				$decoded_variants[] = $gzdecode;
			}

			foreach ($decoded_variants as $decoded) {
				$decoded = (string) $decoded;
				if ($decoded !== '') {
					$haystacks[] = \strtolower($decoded);
				}
			}
		}
	}

	foreach ($keywords as $keyword) {
		foreach ($haystacks as $haystack) {
			if ($keyword !== '' && \strpos($haystack, $keyword) !== false) {
				return true;
			}
		}
	}

	return false;
}

function cmx_mail_import_beleg_taxonomy(): string {
	if (\function_exists(__NAMESPACE__ . '\\cmx_belege_kategorie_taxonomy')) {
		$tax = (string) cmx_belege_kategorie_taxonomy();
		if ($tax !== '') {
			return $tax;
		}
	}
	foreach (['belege_kategorien', 'belege_kategorie', 'beleg_kategorien', 'beleg_kategorie'] as $tax) {
		if (\taxonomy_exists($tax)) {
			return $tax;
		}
	}
	return '';
}

function cmx_mail_import_doc_kontakt_rel_key(): string {
	if (\defined(__NAMESPACE__ . '\\CMX_DOK_REL_META')) {
		$map = \constant(__NAMESPACE__ . '\\CMX_DOK_REL_META');
		if (\is_array($map) && isset($map['kontakte']) && \is_string($map['kontakte']) && $map['kontakte'] !== '') {
			return $map['kontakte'];
		}
	}
	return 'cmx_dokumente_kunden';
}

function cmx_mail_import_doc_uploads_meta_key(): string {
	if (\defined(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')) {
		$key = (string) \constant(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META');
		if ($key !== '') {
			return $key;
		}
	}
	return '_cmx_dokumente_uploads';
}

function cmx_mail_import_doc_self_meta_key(): string {
	if (\defined(__NAMESPACE__ . '\\CMX_DOK_SELF_META')) {
		$key = (string) \constant(__NAMESPACE__ . '\\CMX_DOK_SELF_META');
		if ($key !== '') {
			return $key;
		}
	}
	return '_cmx_dokumente_files';
}

function cmx_mail_import_uploads_root(): string {
	return \trailingslashit(\wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads')));
}

function cmx_mail_import_rel_from_abs(string $abs): string {
	$abs = \wp_normalize_path($abs);
	$root = cmx_mail_import_uploads_root();
	if ($abs === '' || !\str_starts_with($abs, $root)) {
		return '';
	}
	return \ltrim((string) \substr($abs, \strlen($root)), '/');
}

function cmx_mail_import_store_pdf_for_document(string $filename, string $binary): string {
	$year = (int) \wp_date('Y');
	$target_dir = '';
	if (\function_exists(__NAMESPACE__ . '\\cmx_dok_upload_target_dir')) {
		[$base] = cmx_dok_upload_target_dir('dokumente', $year);
		$target_dir = (string) $base;
	}
	if ($target_dir === '') {
		$target_dir = (string) (\WP_CONTENT_DIR . '/uploads/misbuero/archiv/' . $year . '/dokumente');
		if (!\is_dir($target_dir)) {
			@\wp_mkdir_p($target_dir);
		}
	}
	if (!\is_dir($target_dir) || !\is_writable($target_dir)) {
		return '';
	}

	$name = \sanitize_file_name($filename);
	if ($name === '' || \strtolower((string) \pathinfo($name, \PATHINFO_EXTENSION)) !== 'pdf') {
		$name = \wp_date('ymd-His') . '-mail-import.pdf';
	}
	$name = \wp_unique_filename($target_dir, $name);
	$target_abs = \wp_normalize_path((string) ($target_dir . '/' . $name));
	if (@\file_put_contents($target_abs, $binary) === false) {
		return '';
	}
	@\chmod($target_abs, 0666);

	return cmx_mail_import_rel_from_abs($target_abs);
}

function cmx_mail_import_store_pdf_for_beleg(int $beleg_id, string $filename, string $binary): string {
	$year = \function_exists(__NAMESPACE__ . '\\cmx_get_beleg_upload_year')
		? (int) cmx_get_beleg_upload_year($beleg_id)
		: (int) \wp_date('Y');
	if ($year <= 0) {
		$year = (int) \wp_date('Y');
	}

	if (\function_exists(__NAMESPACE__ . '\\cmx_belege_upload_dir')) {
		[$target_dir] = cmx_belege_upload_dir($year);
	} else {
		$target_dir = (string) (\WP_CONTENT_DIR . '/uploads/misbuero/archiv/' . $year . '/belege');
		if (!\is_dir($target_dir)) {
			@\wp_mkdir_p($target_dir);
		}
	}
	if (!\is_dir($target_dir) || !\is_writable($target_dir)) {
		return '';
	}

	$prefix = \sanitize_title((string) \get_post_meta($beleg_id, '_cmx_beleg_upload_prefix', true));
	if ($prefix === '') {
		$prefix = \sanitize_title((string) \get_the_title($beleg_id));
		if ($prefix === '') {
			$prefix = \wp_date('ymd-His') . '-mail-import';
		}
		\update_post_meta($beleg_id, '_cmx_beleg_upload_prefix', $prefix);
	}

	$next = 1;
	if (\function_exists(__NAMESPACE__ . '\\cmx_belege_next_suffix')) {
		$next = \max(1, (int) cmx_belege_next_suffix($target_dir, $prefix));
	}

	do {
		$target_name = $prefix . '_upload_' . \str_pad((string) $next, 3, '0', \STR_PAD_LEFT) . '.pdf';
		$next++;
		$target_abs = \wp_normalize_path((string) (\rtrim($target_dir, '/\\') . DIRECTORY_SEPARATOR . $target_name));
	} while (\is_file($target_abs));

	if (@\file_put_contents($target_abs, $binary) === false) {
		return '';
	}
	@\chmod($target_abs, 0666);

	$target_rel = cmx_mail_import_rel_from_abs($target_abs);
	if ($target_rel === '') {
		return '';
	}

	$uploads_meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_UPLOADS_META')
		? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_UPLOADS_META')
		: '_cmx_belege_uploads';
	$current = (array) \get_post_meta($beleg_id, $uploads_meta_key, true);
	$current = \array_values(\array_filter($current, static function ($value): bool {
		return !($value === '' || $value === null);
	}));
	if (!\in_array($target_rel, $current, true)) {
		$current[] = $target_rel;
		\update_post_meta($beleg_id, $uploads_meta_key, $current);
	}

	return $target_rel;
}

function cmx_mail_import_create_supplier_beleg(int $kontakt_id, string $subject, array $attachment, array $context = []): array {
	if ($kontakt_id <= 0 || (string) \get_post_type($kontakt_id) === '') {
		return ['post_id' => 0, 'upload_rel' => ''];
	}

	$inserted = \wp_insert_post([
		'post_type' => 'belege',
		'post_status' => 'draft',
		'post_title' => '',
		'meta_input' => ['_cmx_title_auto' => 1],
	], true);
	if (\is_wp_error($inserted) || (int) $inserted <= 0) {
		return ['post_id' => 0, 'upload_rel' => ''];
	}
	$beleg_id = (int) $inserted;

	\update_post_meta($beleg_id, '_cmx_beleg_richtung', 'eingang');
	\update_post_meta($beleg_id, '_cmx_beleg_kontakt_id', $kontakt_id);
	\update_post_meta($beleg_id, '_cmx_beleg_kontakt_label', (string) \get_the_title($kontakt_id));
	\update_post_meta($beleg_id, '_cmx_beleg_rng_datum', (string) \wp_date('Y-m-d'));
	if (\trim($subject) !== '') {
		\update_post_meta($beleg_id, '_cmx_beleg_betreff', \sanitize_text_field($subject));
	}

	$tax = cmx_mail_import_beleg_taxonomy();
	if ($tax !== '') {
		$term = \get_term_by('slug', 'rechnung', $tax);
		if (!$term || \is_wp_error($term)) {
			$term = \get_term_by('name', 'Rechnung', $tax);
		}
		if ($term && !\is_wp_error($term)) {
			\wp_set_post_terms($beleg_id, [(int) $term->term_id], $tax, false);
		}
	}

	$rel = cmx_mail_import_store_pdf_for_beleg(
		$beleg_id,
		(string) ($attachment['filename'] ?? 'attachment.pdf'),
		(string) ($attachment['content'] ?? '')
	);

	if ($rel === '') {
		return ['post_id' => 0, 'upload_rel' => ''];
	}

	cmx_mail_import_stamp_post($beleg_id, $context, $rel);

	return ['post_id' => $beleg_id, 'upload_rel' => $rel];
}

function cmx_mail_import_create_document(int $kontakt_id, string $subject, array $attachment, array $context = []): array {
	if ($kontakt_id <= 0) {
		return ['post_id' => 0, 'upload_rel' => ''];
	}

	$file_name = (string) ($attachment['filename'] ?? '');
	$title_from_file = (string) \pathinfo($file_name, \PATHINFO_FILENAME);
	$title = \sanitize_text_field(\trim($title_from_file !== '' ? $title_from_file : $subject));
	if ($title === '') {
		$title = \wp_date('ymd-His') . ' mail-import';
	}

	$inserted = \wp_insert_post([
		'post_type' => 'dokumente',
		'post_status' => 'publish',
		'post_title' => $title,
	], true);
	if (\is_wp_error($inserted) || (int) $inserted <= 0) {
		return ['post_id' => 0, 'upload_rel' => ''];
	}
	$doc_id = (int) $inserted;

	$rel = cmx_mail_import_store_pdf_for_document(
		$file_name !== '' ? $file_name : 'attachment.pdf',
		(string) ($attachment['content'] ?? '')
	);
	if ($rel === '') {
		return ['post_id' => 0, 'upload_rel' => ''];
	}

	\update_post_meta($doc_id, '_cmx_dokumente_file_path', $rel);
	\update_post_meta($doc_id, cmx_mail_import_doc_self_meta_key(), [$rel]);

	$kontakt_rel_key = cmx_mail_import_doc_kontakt_rel_key();
	$current_contacts = (array) \get_post_meta($doc_id, $kontakt_rel_key, true);
	$current_contacts = \array_values(\array_unique(\array_map('intval', $current_contacts)));
	if (!\in_array($kontakt_id, $current_contacts, true)) {
		$current_contacts[] = $kontakt_id;
	}
	\update_post_meta($doc_id, $kontakt_rel_key, \array_values(\array_filter($current_contacts)));

	$kontakt_uploads_meta = cmx_mail_import_doc_uploads_meta_key();
	$kontakt_docs = (array) \get_post_meta($kontakt_id, $kontakt_uploads_meta, true);
	$kontakt_docs = \array_values(\array_unique(\array_map('intval', $kontakt_docs)));
	if (!\in_array($doc_id, $kontakt_docs, true)) {
		$kontakt_docs[] = $doc_id;
		\update_post_meta($kontakt_id, $kontakt_uploads_meta, $kontakt_docs);
	}

	cmx_mail_import_stamp_post($doc_id, $context, $rel);

	return ['post_id' => $doc_id, 'upload_rel' => $rel];
}

function cmx_mail_import_process_message($imap, int $msg_no, array $settings, array $keywords, array $run_context = []): array {
	$raw_header = (string) \imap_fetchheader($imap, $msg_no, \FT_PREFETCHTEXT);
	$header = $raw_header !== '' ? \imap_rfc822_parse_headers($raw_header) : false;
	if (!$header || !\is_object($header)) {
		return ['imported' => 0, 'reason' => 'missing_header'];
	}
	$sender_email = cmx_mail_import_header_sender_email($header);
	if ($sender_email === '') {
		return ['imported' => 0, 'reason' => 'missing_sender'];
	}

	$kontakt_id = cmx_mail_import_find_contact_by_sender($sender_email);
	if ($kontakt_id <= 0) {
		return ['imported' => 0, 'reason' => 'kontakt_not_found', 'sender' => $sender_email];
	}

	$attachments = cmx_mail_import_collect_pdf_attachments($imap, $msg_no);
	if (empty($attachments)) {
		return ['imported' => 0, 'reason' => 'no_pdf', 'kontakt_id' => $kontakt_id, 'sender' => $sender_email];
	}

	$recipients = cmx_mail_import_header_recipient_emails($imap, $msg_no, $header);
	$supplier_target = cmx_mail_import_normalize_email((string) ($settings['supplier_email'] ?? ''));
	$is_supplier_mail = ($supplier_target !== '' && \in_array($supplier_target, $recipients, true));

	$subject = '';
	if (\is_object($header) && isset($header->subject)) {
		$subject = cmx_mail_import_decode_mime_header((string) $header->subject);
	}
	$subject = \sanitize_text_field($subject);
	$run_id = \sanitize_text_field((string) ($run_context['run_id'] ?? ''));

	$imported = 0;
	if ($is_supplier_mail) {
		$is_supplier_contact = cmx_mail_import_contact_is_supplier($kontakt_id);
		$imported_belege = 0;
		$imported_docs = 0;

		foreach ($attachments as $attachment) {
			$content = (string) ($attachment['content'] ?? '');
			$looks_like_invoice = $is_supplier_contact && cmx_mail_import_pdf_keyword_match($content, $keywords);

			if ($looks_like_invoice) {
				$created = cmx_mail_import_create_supplier_beleg($kontakt_id, $subject, $attachment, [
					'run_id' => $run_id,
					'sender' => $sender_email,
					'recipients' => $recipients,
				]);
				$beleg_id = (int) ($created['post_id'] ?? 0);
				$upload_rel = (string) ($created['upload_rel'] ?? '');
				if ($beleg_id > 0) {
					$imported++;
					$imported_belege++;
					cmx_mail_import_register_event([
						'run_id' => $run_id,
						'type' => 'beleg',
						'rule' => 'supplier',
						'status' => 'imported',
						'reason' => 'keyword_invoice_match',
						'subject' => $subject,
						'kontakt_id' => $kontakt_id,
						'sender' => $sender_email,
						'recipients' => $recipients,
						'message_no' => $msg_no,
						'filename' => (string) ($attachment['filename'] ?? 'attachment.pdf'),
						'target_post_id' => $beleg_id,
						'upload_rel' => $upload_rel,
					]);
				}
				continue;
			}

			$created = cmx_mail_import_create_document($kontakt_id, $subject, $attachment, [
				'run_id' => $run_id,
				'sender' => $sender_email,
				'recipients' => $recipients,
			]);
			$doc_id = (int) ($created['post_id'] ?? 0);
			$upload_rel = (string) ($created['upload_rel'] ?? '');
			if ($doc_id > 0) {
				$imported++;
				$imported_docs++;
				cmx_mail_import_register_event([
					'run_id' => $run_id,
					'type' => 'dokument',
					'rule' => 'supplier_document_fallback',
					'status' => 'imported',
					'reason' => 'general_document',
					'subject' => $subject,
					'kontakt_id' => $kontakt_id,
					'sender' => $sender_email,
					'recipients' => $recipients,
					'message_no' => $msg_no,
					'filename' => (string) ($attachment['filename'] ?? 'attachment.pdf'),
					'target_post_id' => $doc_id,
					'upload_rel' => $upload_rel,
				]);
			}
		}

		if ($imported > 0) {
			if ($imported_belege > 0 && $imported_docs > 0) {
				return ['imported' => $imported, 'reason' => 'ok_supplier_mixed', 'kontakt_id' => $kontakt_id, 'sender' => $sender_email];
			}
			if ($imported_belege > 0) {
				return ['imported' => $imported, 'reason' => 'ok_supplier', 'kontakt_id' => $kontakt_id, 'sender' => $sender_email];
			}
			return ['imported' => $imported, 'reason' => 'ok_supplier_document_fallback', 'kontakt_id' => $kontakt_id, 'sender' => $sender_email];
		}

		return ['imported' => 0, 'reason' => 'supplier_route_no_create', 'kontakt_id' => $kontakt_id, 'sender' => $sender_email];
	}

	foreach ($attachments as $attachment) {
		$created = cmx_mail_import_create_document($kontakt_id, $subject, $attachment, [
			'run_id' => $run_id,
			'sender' => $sender_email,
			'recipients' => $recipients,
		]);
		$doc_id = (int) ($created['post_id'] ?? 0);
		$upload_rel = (string) ($created['upload_rel'] ?? '');
		if ($doc_id > 0) {
			$imported++;
			cmx_mail_import_register_event([
				'run_id' => $run_id,
				'type' => 'dokument',
				'rule' => 'general',
				'status' => 'imported',
				'reason' => 'general_document',
				'subject' => $subject,
				'kontakt_id' => $kontakt_id,
				'sender' => $sender_email,
				'recipients' => $recipients,
				'message_no' => $msg_no,
				'filename' => (string) ($attachment['filename'] ?? 'attachment.pdf'),
				'target_post_id' => $doc_id,
				'upload_rel' => $upload_rel,
			]);
		}
	}

	return ['imported' => $imported, 'reason' => $imported > 0 ? 'ok_document' : 'document_create_failed', 'kontakt_id' => $kontakt_id, 'sender' => $sender_email];
}

function cmx_mail_import_open_mailbox(array $settings) {
	$host = (string) ($settings['imap_host'] ?? '');
	$port = (int) ($settings['imap_port'] ?? 993);
	$user = (string) ($settings['email_address'] ?? '');
	$pass = (string) ($settings['email_password'] ?? '');

	if ($host === '' || $user === '' || $pass === '') {
		return false;
	}

	if (\function_exists('imap_timeout')) {
		if (\defined('OPENTIMEOUT')) {
			@\imap_timeout(\OPENTIMEOUT, 12);
		}
		if (\defined('READTIMEOUT')) {
			@\imap_timeout(\READTIMEOUT, 20);
		}
		if (\defined('WRITETIMEOUT')) {
			@\imap_timeout(\WRITETIMEOUT, 20);
		}
		if (\defined('CLOSETIMEOUT')) {
			@\imap_timeout(\CLOSETIMEOUT, 10);
		}
	}

	$mailboxes = [
		'{' . $host . ':' . $port . '/imap/ssl}INBOX',
		'{' . $host . ':' . $port . '/imap/ssl/novalidate-cert}INBOX',
	];

	foreach ($mailboxes as $mailbox) {
		$imap = @\imap_open($mailbox, $user, $pass);
		if ($imap !== false) {
			return $imap;
		}
	}

	return false;
}

function cmx_mail_import_run(array $run_context = []): array {
	$run_id = \sanitize_text_field((string) ($run_context['run_id'] ?? ''));
	if ($run_id === '') {
		$run_id = cmx_mail_import_build_run_id();
	}
	$source = \sanitize_key((string) ($run_context['source'] ?? 'runtime'));
	if ($source === '') {
		$source = 'runtime';
	}
	$max_messages = \max(0, (int) ($run_context['max_messages'] ?? 0));
	$max_runtime_seconds = \max(0.0, (float) ($run_context['max_runtime_seconds'] ?? 0));
	$started_at = \microtime(true);

	$result = [
		'processed_messages' => \max(0, (int) ($run_context['processed_messages'] ?? 0)),
		'imported_items' => \max(0, (int) ($run_context['imported_items'] ?? 0)),
		'skipped_messages' => \max(0, (int) ($run_context['skipped_messages'] ?? 0)),
		'unseen_messages' => \max(0, (int) ($run_context['unseen_messages'] ?? 0)),
		'skip_reasons' => \is_array($run_context['skip_reasons'] ?? null) ? (array) ($run_context['skip_reasons'] ?? []) : [],
		'status' => 'idle',
		'run_id' => $run_id,
		'source' => $source,
		'remaining_messages' => 0,
	];
	cmx_mail_import_log('run start', [
		'run_id' => $run_id,
		'source' => $source,
	]);

	if (!\function_exists('imap_open')) {
		$result['status'] = 'imap_extension_missing';
		cmx_mail_import_register_run($result);
		cmx_mail_import_log('run stop', [
			'run_id' => $run_id,
			'status' => $result['status'],
		]);
		return $result;
	}

	$settings = cmx_mail_import_get_settings();
	if ((string) ($settings['imap_host'] ?? '') === '' || (string) ($settings['email_address'] ?? '') === '' || (string) ($settings['email_password'] ?? '') === '') {
		$result['status'] = 'settings_incomplete';
		cmx_mail_import_register_run($result);
		cmx_mail_import_log('run stop', [
			'run_id' => $run_id,
			'status' => $result['status'],
			'imap_host_set' => (string) ($settings['imap_host'] ?? '') !== '',
			'email_set' => (string) ($settings['email_address'] ?? '') !== '',
			'password_set' => (string) ($settings['email_password'] ?? '') !== '',
		]);
		return $result;
	}

	$keywords = cmx_mail_import_beleg_filter_keywords();
	$imap = cmx_mail_import_open_mailbox($settings);
	if ($imap === false) {
		$result['status'] = 'imap_connect_failed';
		cmx_mail_import_register_run($result);
		cmx_mail_import_log('run stop', [
			'run_id' => $run_id,
			'status' => $result['status'],
			'host' => (string) $settings['imap_host'],
			'user' => (string) $settings['email_address'],
		]);
		return $result;
	}

	try {
		$messages = \imap_search($imap, 'UNSEEN UNDELETED');
		if (!\is_array($messages) || empty($messages)) {
			$result['status'] = ((int) $result['processed_messages'] > 0 || (int) $result['imported_items'] > 0 || (int) $result['skipped_messages'] > 0)
				? 'done'
				: 'no_unseen';
			cmx_mail_import_register_run($result);
			cmx_mail_import_log('run stop', [
				'run_id' => $run_id,
				'status' => $result['status'],
			]);
			return $result;
		}

		\sort($messages, \SORT_NUMERIC);
		if ((int) $result['unseen_messages'] <= 0) {
			$result['unseen_messages'] = \count($messages);
		}
		$result['status'] = 'running';
		$handled_this_chunk = 0;

		foreach ($messages as $index => $msg_no) {
			if ($max_messages > 0 && $handled_this_chunk >= $max_messages) {
				$result['status'] = 'partial';
				$result['remaining_messages'] = \max(0, \count($messages) - (int) $index);
				cmx_mail_import_register_run($result);
				cmx_mail_import_log('run partial', $result);
				return $result;
			}
			if ($max_runtime_seconds > 0 && $handled_this_chunk > 0 && (\microtime(true) - $started_at) >= $max_runtime_seconds) {
				$result['status'] = 'partial';
				$result['remaining_messages'] = \max(0, \count($messages) - (int) $index);
				cmx_mail_import_register_run($result);
				cmx_mail_import_log('run partial', $result);
				return $result;
			}

			$msg_no = (int) $msg_no;
			if ($msg_no <= 0) {
				continue;
			}
			$overview = \imap_fetch_overview($imap, (string) $msg_no, 0);
			if (\is_array($overview) && isset($overview[0]) && \is_object($overview[0]) && !empty($overview[0]->seen)) {
				// Doppelte Absicherung: nur wirklich ungelesene Nachrichten prüfen.
				cmx_mail_import_log('message skipped seen', [
					'run_id' => $run_id,
					'msg_no' => $msg_no,
				]);
				continue;
			}
			$handled_this_chunk++;

			$info = cmx_mail_import_process_message($imap, $msg_no, $settings, $keywords, [
				'run_id' => $run_id,
				'source' => $source,
			]);
			$imported = (int) ($info['imported'] ?? 0);
			$reason = \sanitize_key((string) ($info['reason'] ?? 'unknown'));
			$sender = cmx_mail_import_normalize_email((string) ($info['sender'] ?? ''));
			$kontakt_id = (int) ($info['kontakt_id'] ?? 0);

			if ($imported > 0) {
				@\imap_setflag_full($imap, (string) $msg_no, '\\Seen');
				$result['processed_messages']++;
				$result['imported_items'] += $imported;
				cmx_mail_import_log('message imported', [
					'run_id' => $run_id,
					'msg_no' => $msg_no,
					'imported_items' => $imported,
					'reason' => $reason,
					'sender' => $sender,
					'kontakt_id' => $kontakt_id,
				]);
			} else {
				$mark_seen_on_skip = cmx_mail_import_should_mark_seen_for_reason($reason);
				$result['skipped_messages'] = (int) ($result['skipped_messages'] ?? 0) + 1;
				$skip_reasons = (array) ($result['skip_reasons'] ?? []);
				$skip_reasons[$reason] = ((int) ($skip_reasons[$reason] ?? 0)) + 1;
				$result['skip_reasons'] = $skip_reasons;
				if ($mark_seen_on_skip) {
					@\imap_setflag_full($imap, (string) $msg_no, '\\Seen');
				} else {
					// Falls ein IMAP-Client beim Lesen implizit "Seen" setzt, wieder rueckgaengig machen.
					@\imap_clearflag_full($imap, (string) $msg_no, '\\Seen');
				}
				// skipped nur im Datei-Log halten, nicht im DB-Eventlog.
				cmx_mail_import_log('message skipped', [
					'run_id' => $run_id,
					'msg_no' => $msg_no,
					'reason' => $reason,
					'sender' => $sender,
					'kontakt_id' => $kontakt_id,
					'mark_seen' => $mark_seen_on_skip,
				]);
			}
		}

		$result['status'] = 'done';
		cmx_mail_import_register_run($result);
		cmx_mail_import_log('run done', $result);
		return $result;
	} finally {
		@\imap_close($imap);
	}
}

function cmx_mail_import_maybe_run_for_scanner_admin_list(): void {
	static $did_run = false;
	if ($did_run) {
		return;
	}
	$did_run = true;

	$result = cmx_mail_import_run(['source' => 'scanner_admin']);
	$GLOBALS['cmx_mail_import_last_admin_run'] = $result;
	if ((int) ($result['imported_items'] ?? 0) > 0) {
		cmx_mail_import_log('scanner list run', $result);
	}
}

function cmx_mail_import_is_scanner_list_request_params(): bool {
	if (!isset($_GET['post_type']) || (string) $_GET['post_type'] !== 'scanner') {
		return false;
	}
	if (isset($_GET['page']) && (string) $_GET['page'] !== '') {
		return false;
	}
	return true;
}

function cmx_mail_import_maybe_run_for_scanner_request(): void {
	return;
}

\add_action('admin_init', function (): void {
	if (!\is_admin()) {
		return;
	}
	cmx_mail_import_maybe_run_for_scanner_request();
}, 8);

\add_action('load-edit.php', function (): void {
	if (!\is_admin()) {
		return;
	}
	cmx_mail_import_maybe_run_for_scanner_request();
}, 9);

\add_action('current_screen', function ($screen): void {
	if (!$screen instanceof \WP_Screen) {
		return;
	}
	if ((string) ($screen->base ?? '') !== 'edit') {
		return;
	}
	if ((string) ($screen->post_type ?? '') !== 'scanner') {
		return;
	}
	cmx_mail_import_maybe_run_for_scanner_request();
}, 9);

function cmx_mail_import_is_scanner_admin_list_request(): bool {
	if (!\is_admin()) {
		return false;
	}
	global $pagenow;
	if ((string) $pagenow !== 'edit.php') {
		return false;
	}
	return cmx_mail_import_is_scanner_list_request_params();
}

function cmx_mail_import_admin_log_page_url(array $args = []): string {
	$base_args = [
		'post_type' => 'scanner',
		'page' => 'cmx-mail-import-log',
	];
	$args = \array_merge($base_args, $args);
	if (isset($args['cmx_mail_import_run'])) {
		$args['cmx_mail_import_run'] = cmx_mail_import_run_query_value((string) $args['cmx_mail_import_run']);
	}
	return \add_query_arg($args, \admin_url('edit.php'));
}

function cmx_mail_import_upload_url_from_rel(string $rel): string {
	$rel = \ltrim(\str_replace('\\', '/', $rel), '/');
	if ($rel === '') {
		return '';
	}
	$upload_data = \wp_get_upload_dir();
	$base_url = \trailingslashit((string) ($upload_data['baseurl'] ?? ''));
	if ($base_url === '') {
		return '';
	}
	$parts = \array_map('rawurlencode', \array_filter(\explode('/', $rel), static function ($value): bool {
		return \is_string($value) && $value !== '';
	}));
	return $base_url . \implode('/', $parts);
}

function cmx_mail_import_is_visible_event($entry): bool {
	if (!\is_array($entry)) {
		return false;
	}
	$status = \sanitize_key((string) ($entry['status'] ?? ''));
	return $status !== 'skipped';
}

function cmx_mail_import_entry_status_label(array $entry): string {
	$status = \sanitize_key((string) ($entry['status'] ?? ''));
	$reason = \sanitize_key((string) ($entry['reason'] ?? ''));
	return $reason !== '' ? $reason : ($status !== '' ? $status : '-');
}

function cmx_mail_import_collect_status_filter_options(array $entries): array {
	$options = [];
	foreach ($entries as $entry) {
		if (!\is_array($entry) || !cmx_mail_import_is_visible_event($entry)) {
			continue;
		}
		$label = cmx_mail_import_entry_status_label($entry);
		if ($label === '' || $label === '-') {
			continue;
		}
		$options[$label] = $label;
	}
	\ksort($options, \SORT_NATURAL | \SORT_FLAG_CASE);
	return \array_values($options);
}

function cmx_mail_import_filter_entries_by_status(array $entries, string $status_filter): array {
	$status_filter = \sanitize_key($status_filter);
	if ($status_filter === '') {
		return $entries;
	}

	return \array_values(\array_filter($entries, static function ($entry) use ($status_filter): bool {
		return \is_array($entry) && cmx_mail_import_entry_status_label($entry) === $status_filter;
	}));
}

function cmx_mail_import_filter_entries_by_sender(array $entries, string $sender_filter): array {
	$sender_filter = cmx_mail_import_normalize_email($sender_filter);
	if ($sender_filter === '') {
		return $entries;
	}

	return \array_values(\array_filter($entries, static function ($entry) use ($sender_filter): bool {
		if (!\is_array($entry)) {
			return false;
		}
		$sender = cmx_mail_import_normalize_email((string) ($entry['sender'] ?? ''));
		return $sender !== '' && \strpos($sender, $sender_filter) !== false;
	}));
}

function cmx_mail_import_render_admin_details_table(array $entries): void {
	$entries = \array_values(\array_filter($entries, __NAMESPACE__ . '\\cmx_mail_import_is_visible_event'));

	if (empty($entries)) {
		echo '<p>Keine automatisch zugeordneten Eintraege fuer diesen Lauf gefunden.</p>';
		return;
	}

	echo '<table id="cmx-mail-import-table" class="widefat striped" style="margin-top:8px;"><thead><tr>';
	echo '<th>Zeit</th><th>Status</th><th>Typ</th><th>Kontakt</th><th>Absender</th><th>Empfänger</th><th>Datei</th><th>Ziel</th>';
	echo '</tr></thead><tbody>';
	foreach ($entries as $entry) {
		$ts = (int) ($entry['ts'] ?? 0);
		$time = $ts > 0 ? \wp_date('d.m.Y H:i', $ts) : '';
		$status_label = cmx_mail_import_entry_status_label($entry);
		$type = \sanitize_text_field((string) ($entry['type'] ?? ''));
		$type_post_type = \sanitize_key((string) ($entry['target_post_type'] ?? ''));
		if ($type_post_type === '') {
			if ($type === 'beleg') {
				$type_post_type = 'belege';
			} elseif ($type === 'dokument') {
				$type_post_type = 'dokumente';
			}
		}
		$type_list_link = $type_post_type !== '' ? \admin_url('edit.php?post_type=' . $type_post_type) : '';
		$kontakt = \sanitize_text_field((string) ($entry['kontakt_title'] ?? ''));
		$kontakt_id = (int) ($entry['kontakt_id'] ?? 0);
		if ($kontakt === '' && $kontakt_id > 0) {
			$kontakt = (string) \get_the_title($kontakt_id);
		}
		$kontakt_link = $kontakt_id > 0 ? \get_edit_post_link($kontakt_id, '') : '';
		$sender = cmx_mail_import_normalize_email((string) ($entry['sender'] ?? ''));
		$subject = \sanitize_text_field((string) ($entry['subject'] ?? ''));
		$recipients = cmx_mail_import_parse_email_list($entry['recipients'] ?? []);
		$filename = \sanitize_file_name((string) ($entry['filename'] ?? ''));
		$target_post_id = (int) ($entry['target_post_id'] ?? 0);
		$target_label = '-';
		$target_link = '';
		if ($target_post_id > 0) {
			$target_title = \sanitize_text_field((string) \get_the_title($target_post_id));
			$target_label = $target_title !== '' ? $target_title : '-';
			$target_link = (string) \get_edit_post_link($target_post_id, '');
		}
		$upload_rel = \sanitize_text_field((string) ($entry['upload_rel'] ?? ''));
		$upload_url = cmx_mail_import_upload_url_from_rel($upload_rel);

		echo '<tr>';
		echo '<td class="cmx-mail-import-zeit-cell">' . \esc_html($time) . '</td>';
		echo '<td><code>' . \esc_html($status_label) . '</code></td>';
		echo '<td>';
		if ($type_list_link !== '') {
			echo '<a href="' . \esc_url($type_list_link) . '" target="_blank" rel="noopener noreferrer">' . \esc_html($type) . '</a>';
		} else {
			echo \esc_html($type);
		}
		echo '</td>';
		echo '<td>';
		if ($kontakt_link !== '') {
			echo '<a href="' . \esc_url($kontakt_link) . '" target="_blank" rel="noopener noreferrer">' . \esc_html($kontakt) . '</a>';
		} else {
			echo \esc_html($kontakt);
		}
		echo '</td>';
		echo '<td>';
		if ($sender !== '') {
			$mailto = 'mailto:' . $sender;
			if ($subject !== '') {
				$mailto .= '?subject=' . \rawurlencode($subject);
			}
			echo '<a href="' . \esc_url($mailto) . '" title="' . \esc_attr($subject) . '">' . \esc_html($sender) . '</a>';
		} else {
			echo '-';
		}
		echo '</td>';
		echo '<td>';
		if (!empty($recipients)) {
			$recipient_links = [];
			foreach ($recipients as $recipient) {
				$recipient = cmx_mail_import_normalize_email((string) $recipient);
				if ($recipient === '') {
					continue;
				}
				$recipient_links[] = '<a href="mailto:' . \esc_attr($recipient) . '">' . \esc_html($recipient) . '</a>';
			}
			echo !empty($recipient_links) ? \implode(', ', $recipient_links) : '-';
		} else {
			echo '-';
		}
		echo '</td>';
		echo '<td>';
		if ($upload_url !== '') {
			echo '<a href="' . \esc_url($upload_url) . '" title="' . \esc_attr($upload_url) . '" target="_blank" rel="noopener noreferrer">' . \esc_html($filename !== '' ? $filename : 'Datei') . '</a>';
		} else {
			echo \esc_html($filename !== '' ? $filename : '-');
		}
		echo '</td>';
		echo '<td>';
		if ($target_link !== '') {
			echo '<a href="' . \esc_url($target_link) . '" target="_blank" rel="noopener noreferrer">' . \esc_html($target_label) . '</a>';
		} else {
			echo \esc_html($target_label);
		}
		echo '</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
}

function cmx_mail_import_render_log_page(): void {
	if (!\current_user_can('manage_options')) {
		\wp_die('forbidden');
	}

	$run_query = \sanitize_text_field((string) ($_GET['cmx_mail_import_run'] ?? $_GET['run'] ?? ''));
	$run_query_field = cmx_mail_import_run_query_value($run_query);
	$status_filter = \sanitize_key((string) ($_GET['status_filter'] ?? ''));
	$sender_filter = cmx_mail_import_normalize_email((string) ($_GET['sender_filter'] ?? ''));
	$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 200;
	$limit = \max(20, \min(500, $limit));
	if ($run_query !== '' && $run_query_field !== '' && $run_query_field !== $run_query) {
		$redirect_args = ['cmx_mail_import_run' => $run_query_field];
		if (isset($_GET['limit'])) {
			$redirect_args['limit'] = $limit;
		}
		if ($status_filter !== '') {
			$redirect_args['status_filter'] = $status_filter;
		}
		if ($sender_filter !== '') {
			$redirect_args['sender_filter'] = $sender_filter;
		}
		\wp_safe_redirect(cmx_mail_import_admin_log_page_url($redirect_args));
		exit;
	}
	$entries = cmx_mail_import_get_events_for_run_query($run_query, $limit);
	$status_filter_options = cmx_mail_import_collect_status_filter_options($entries);
	$entries = cmx_mail_import_filter_entries_by_status($entries, $status_filter);
	$entries = cmx_mail_import_filter_entries_by_sender($entries, $sender_filter);
	$visible_entry_count = \count(\array_values(\array_filter($entries, __NAMESPACE__ . '\\cmx_mail_import_is_visible_event')));
	$today_count = cmx_mail_import_count_events_today();
	$log_file_path = cmx_mail_import_log_file_path();
	$run_now_url = \add_query_arg(
		[
			'action' => 'cmx_mail_import_run_now',
			'redirect' => 'log',
		],
		\admin_url('admin-post.php')
	);
	$open_log_url = \admin_url('admin-post.php?action=cmx_mail_import_open_logfile');
	$can_open_log_file = cmx_mail_import_support_user_switch_enabled();

	$recent_run_entries = \array_values(\array_filter(
		cmx_mail_import_get_run_log(),
		static function ($run_entry): bool {
			if (!\is_array($run_entry)) {
				return false;
			}
			$imported = (int) ($run_entry['imported_items'] ?? 0);
			$skipped = (int) ($run_entry['skipped_messages'] ?? 0);
			return $imported > 0 || $skipped > 0;
		}
	));
	$recent_run_entries = \array_slice($recent_run_entries, 0, 8);

	echo '<div class="wrap">';
	echo '<h1>E-Mail Auto-Import</h1>';
	// echo '<p>Heute importiert: <strong>' . \esc_html((string) $today_count) . '</strong>. ';
	echo 'In der Scanner-Liste werden nur ungelesene Mails geprueft.</p>';

	echo '<form method="get" style="margin:10px 0 14px;">';
	echo '<input type="hidden" name="post_type" value="scanner">';
	echo '<input type="hidden" name="page" value="cmx-mail-import-log">';
	echo '<label for="cmx_mail_import_run"><strong>Zeiteintrag:</strong></label> ';
	echo '<input type="text" id="cmx_mail_import_run" name="cmx_mail_import_run" value="' . \esc_attr($run_query_field) . '" placeholder="20260306-080130"> ';
	echo '<label for="cmx_mail_import_status_filter"><strong>Status:</strong></label> ';
	echo '<select id="cmx_mail_import_status_filter" name="status_filter">';
	echo '<option value="">Alle</option>';
	foreach ($status_filter_options as $option) {
		$selected = $status_filter === $option ? ' selected' : '';
		echo '<option value="' . \esc_attr($option) . '"' . $selected . '>' . \esc_html($option) . '</option>';
	}
	echo '</select> ';
	echo '<label for="cmx_mail_import_sender_filter"><strong>Absender:</strong></label> ';
	echo '<input type="text" id="cmx_mail_import_sender_filter" name="sender_filter" value="' . \esc_attr($sender_filter) . '" placeholder="name@example.com"> ';
	echo '<label for="cmx_mail_import_limit"><strong>Limit:</strong></label> ';
	echo '<input type="number" id="cmx_mail_import_limit" name="limit" min="20" max="500" value="' . \esc_attr((string) $limit) . '" style="width:90px;"> ';
	echo '<button class="button button-primary" type="submit">Filtern</button> ';
	echo '<a class="button" href="' . \esc_url(cmx_mail_import_admin_log_page_url()) . '">Alle anzeigen</a> ';
	echo '<a class="button" href="' . \esc_url($run_now_url) . '">E-Mails prüfen</a> ';
	if ($can_open_log_file && $log_file_path !== '') {
		echo '<a class="button" href="' . \esc_url($open_log_url) . '" title="' . \esc_attr($log_file_path) . '" target="_blank" rel="noopener noreferrer">Logdatei öffnen</a>';
	}
	echo '</form>';

	if (!empty($recent_run_entries)) {
		$parts = [];
		foreach ($recent_run_entries as $run_entry) {
			$rid = \sanitize_text_field((string) ($run_entry['run_id'] ?? ''));
			if ($rid === '') {
				continue;
			}
			$label = cmx_mail_import_run_id_label($rid);
			$imported = (int) ($run_entry['imported_items'] ?? 0);
			$skipped = (int) ($run_entry['skipped_messages'] ?? 0);
			$status = \sanitize_key((string) ($run_entry['status'] ?? ''));
			$title = 'status=' . $status . ', imported=' . $imported . ', skipped=' . $skipped;
			if ($imported > 0) {
				$url = cmx_mail_import_admin_log_page_url(['cmx_mail_import_run' => $rid]);
				$parts[] = '<a href="' . \esc_url($url) . '" title="' . \esc_attr($title) . '"><code>' . \esc_html($label) . '</code></a>';
				continue;
			}
			$parts[] = '<code title="' . \esc_attr($title) . '" style="opacity:.7;">' . \esc_html($label) . '</code>';
		}
		echo '<p><strong>' . \esc_html((string) $visible_entry_count) . ' Einträge</strong> ';
		echo '| <strong>Letzte Runs:</strong> ';
		echo \implode(' | ', $parts);
		echo '</p>';
	}

	cmx_mail_import_render_admin_details_table($entries);
	echo '<script>(function(){var runInput=document.getElementById("cmx_mail_import_run");if(!runInput){return;}var toElement=function(node){if(!node){return null;}return node.nodeType===1?node:node.parentElement;};var isZeitSelectionNode=function(node){var el=toElement(node);if(!el){return false;}return !!el.closest(".cmx-mail-import-zeit-cell");};document.addEventListener("mouseup",function(){var selection=window.getSelection?window.getSelection():null;if(!selection||selection.isCollapsed){return;}if(!isZeitSelectionNode(selection.anchorNode)&&!isZeitSelectionNode(selection.focusNode)){return;}var selected=(selection.toString()||"").trim();if(selected===""){return;}runInput.value=selected;runInput.dispatchEvent(new Event("input",{bubbles:true}));if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(selected).catch(function(){});}});})();</script>';
	echo '</div>';
}

\add_action('admin_menu', function (): void {
	\add_submenu_page(
		'edit.php?post_type=scanner',
		'E-Mail Auto-Import',
		'E-Mails',
		'manage_options',
		'cmx-mail-import-log',
		__NAMESPACE__ . '\\cmx_mail_import_render_log_page'
	);
});

\add_action('admin_notices', function (): void {
	if (!cmx_mail_import_is_scanner_admin_list_request()) {
		return;
	}

	$last_run = (isset($GLOBALS['cmx_mail_import_last_admin_run']) && \is_array($GLOBALS['cmx_mail_import_last_admin_run']))
		? (array) $GLOBALS['cmx_mail_import_last_admin_run']
		: [];
	$status = \sanitize_key((string) ($last_run['status'] ?? ($_GET['cmx_mail_import_status'] ?? '')));
	$items = isset($last_run['imported_items']) ? (int) $last_run['imported_items'] : (int) ($_GET['cmx_mail_import_items'] ?? 0);
	$run_id = \sanitize_text_field((string) ($last_run['run_id'] ?? ($_GET['cmx_mail_import_run'] ?? '')));
	$run_query = cmx_mail_import_run_query_value($run_id);
	$today_count = cmx_mail_import_count_events_today();

	$details_url = \add_query_arg(
		[
			'post_type' => 'scanner',
			'cmx_mail_import_details' => 1,
			'cmx_mail_import_run' => $run_query,
		],
		\admin_url('edit.php')
	);
	$log_url = $run_query !== ''
		? cmx_mail_import_admin_log_page_url(['cmx_mail_import_run' => $run_query])
		: cmx_mail_import_admin_log_page_url();

	$class = $items > 0 ? 'notice notice-success is-dismissible' : 'notice notice-info is-dismissible';
	echo '<div class="' . \esc_attr($class) . '"><p>';
	echo '<strong>Auto-Import E-Mail:</strong> ';
	echo 'Neu automatisch zugeordnet in diesem Lauf: <strong>' . \esc_html((string) $items) . '</strong>. ';
	echo 'Heute gesamt: <strong>' . \esc_html((string) $today_count) . '</strong>. ';
	if ($status !== '') {
		echo 'Status: <code>' . \esc_html($status) . '</code>. ';
	}
	echo '<a href="' . \esc_url($details_url) . '">Details (hier)</a> | ';
	echo '<a href="' . \esc_url($log_url) . '">Protokoll (dauerhaft)</a>';
	echo '</p></div>';

	if (!isset($_GET['cmx_mail_import_details'])) {
		return;
	}

	$entries = $run_query !== '' ? cmx_mail_import_get_events_for_run_query($run_query, 80) : cmx_mail_import_get_recent_events(80);
	echo '<div class="notice notice-info"><p><strong>Auto-Import Details</strong></p>';
	cmx_mail_import_render_admin_details_table($entries);
	echo '</div>';
});

function cmx_mail_import_render_auto_import_meta_box(\WP_Post $post): void {
	$is_auto = (string) \get_post_meta($post->ID, '_cmx_mail_import_auto', true) === '1';
	if (!$is_auto) {
		echo '<p>Kein automatischer E-Mail-Import.</p>';
		return;
	}

	$ts = (int) \get_post_meta($post->ID, '_cmx_mail_import_at', true);
	$run_id = \sanitize_text_field((string) \get_post_meta($post->ID, '_cmx_mail_import_run_id', true));
	$sender = cmx_mail_import_normalize_email((string) \get_post_meta($post->ID, '_cmx_mail_import_sender', true));
	$recipients = \sanitize_text_field((string) \get_post_meta($post->ID, '_cmx_mail_import_recipients', true));
	$upload_rel = \sanitize_text_field((string) \get_post_meta($post->ID, '_cmx_mail_import_upload_rel', true));
	$run_log_url = $run_id !== ''
		? cmx_mail_import_admin_log_page_url(['cmx_mail_import_run' => $run_id])
		: cmx_mail_import_admin_log_page_url();

	echo '<p><strong>Automatisch importiert:</strong> ' . \esc_html($ts > 0 ? \wp_date('d.m.Y H:i:s', $ts) : '-') . '</p>';
	echo '<p><strong>Absender:</strong> ' . \esc_html($sender !== '' ? $sender : '-') . '</p>';
	echo '<p><strong>Empfaenger:</strong> ' . \esc_html($recipients !== '' ? $recipients : '-') . '</p>';
	echo '<p><strong>Upload:</strong> <code>' . \esc_html($upload_rel !== '' ? $upload_rel : '-') . '</code></p>';
	echo '<p><strong>Zeiteintrag:</strong> <code>' . \esc_html($run_id !== '' ? cmx_mail_import_run_query_value($run_id) : '-') . '</code></p>';
	echo '<p><a class="button button-secondary" href="' . \esc_url($run_log_url) . '">Im Import-Protokoll anzeigen</a></p>';
}

\add_action('add_meta_boxes', function (): void {
	foreach (['dokumente'] as $post_type) {
		if (!\post_type_exists($post_type)) {
			continue;
		}
		\add_meta_box(
			'cmx_mail_import_meta',
			'E-Mail Auto-Import',
			__NAMESPACE__ . '\\cmx_mail_import_render_auto_import_meta_box',
			$post_type,
			'side',
			'default'
		);
	}
});

\add_filter('cron_schedules', function (array $schedules): array {
	if (!isset($schedules[CMX_MAIL_IMPORT_CRON_INTERVAL])) {
		$schedules[CMX_MAIL_IMPORT_CRON_INTERVAL] = [
			'interval' => 300,
			'display' => 'CMX Mail Import (5 Minuten)',
		];
	}
	return $schedules;
});

\add_action('init', function (): void {
	if (!\wp_next_scheduled(CMX_MAIL_IMPORT_CRON_HOOK)) {
		\wp_schedule_event(\time() + 120, CMX_MAIL_IMPORT_CRON_INTERVAL, CMX_MAIL_IMPORT_CRON_HOOK);
	}
});

\add_action(CMX_MAIL_IMPORT_CRON_HOOK, function (): void {
	$result = cmx_mail_import_run(['source' => 'cron']);
	if ((int) ($result['imported_items'] ?? 0) > 0) {
		cmx_mail_import_log('import completed', $result);
	}
});

\add_action('admin_post_cmx_mail_import_open_logfile', function (): void {
	if (!\current_user_can('manage_options')) {
		\wp_die('forbidden');
	}
	if (!cmx_mail_import_support_user_switch_enabled()) {
		\wp_die('forbidden');
	}

	$path = cmx_mail_import_log_file_path();
	if ($path === '' || !\is_file($path) || !\is_readable($path)) {
		\wp_die('Logdatei nicht verfuegbar');
	}

	@\nocache_headers();
	@header('Content-Type: text/plain; charset=utf-8');
	@header('Content-Disposition: inline; filename="' . \basename($path) . '"');
	@header('X-Content-Type-Options: nosniff');
	@\readfile($path);
	exit;
});

\add_action('admin_post_cmx_mail_import_run_now', function (): void {
	if (!\current_user_can('manage_options')) {
		\wp_die('forbidden');
	}
	if (\function_exists('set_time_limit')) {
		@\set_time_limit(0);
	}
	@\ignore_user_abort(true);

	$redirect_target = \sanitize_key((string) ($_GET['redirect'] ?? $_POST['redirect'] ?? ''));
	$run_id = \sanitize_text_field((string) ($_GET['run_id'] ?? $_POST['run_id'] ?? ''));
	$manual_state = $run_id !== '' ? cmx_mail_import_get_manual_state($run_id) : [];
	$result = cmx_mail_import_run([
		'source' => 'manual_admin',
		'run_id' => $run_id,
		'processed_messages' => (int) ($manual_state['processed_messages'] ?? 0),
		'imported_items' => (int) ($manual_state['imported_items'] ?? 0),
		'skipped_messages' => (int) ($manual_state['skipped_messages'] ?? 0),
		'unseen_messages' => (int) ($manual_state['unseen_messages'] ?? 0),
		'skip_reasons' => \is_array($manual_state['skip_reasons'] ?? null) ? (array) $manual_state['skip_reasons'] : [],
		'max_messages' => 25,
		'max_runtime_seconds' => 8,
	]);
	cmx_mail_import_log('manual run', $result);

	if ((string) ($result['status'] ?? '') === 'partial' && (int) ($result['remaining_messages'] ?? 0) > 0) {
		$continue_run_id = \sanitize_text_field((string) ($result['run_id'] ?? ''));
		if ($continue_run_id !== '') {
			cmx_mail_import_save_manual_state($continue_run_id, [
				'processed_messages' => (int) ($result['processed_messages'] ?? 0),
				'imported_items' => (int) ($result['imported_items'] ?? 0),
				'skipped_messages' => (int) ($result['skipped_messages'] ?? 0),
				'unseen_messages' => (int) ($result['unseen_messages'] ?? 0),
				'skip_reasons' => \is_array($result['skip_reasons'] ?? null) ? (array) $result['skip_reasons'] : [],
			]);
			\wp_safe_redirect(\add_query_arg([
				'action' => 'cmx_mail_import_run_now',
				'redirect' => $redirect_target !== '' ? $redirect_target : 'log',
				'run_id' => $continue_run_id,
			], \admin_url('admin-post.php')));
			exit;
		}
	}

	if ((string) ($result['run_id'] ?? '') !== '') {
		cmx_mail_import_delete_manual_state((string) $result['run_id']);
	}

	$redirect_args = [
		'cmx_mail_import_status' => (string) ($result['status'] ?? 'unknown'),
		'cmx_mail_import_items' => (int) ($result['imported_items'] ?? 0),
		'cmx_mail_import_run' => cmx_mail_import_run_query_value((string) ($result['run_id'] ?? '')),
	];
	$redirect = $redirect_target === 'log'
		? cmx_mail_import_admin_log_page_url($redirect_args)
		: \add_query_arg($redirect_args, \admin_url('edit.php?post_type=scanner'));
	\wp_safe_redirect($redirect);
	exit;
});
