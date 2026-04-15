<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_EMAILS_SYNC_LIMIT')) {
	\define(__NAMESPACE__ . '\\CMX_EMAILS_SYNC_LIMIT', 500);
}

if (!\defined(__NAMESPACE__ . '\\CMX_EMAILS_MAX_MESSAGES')) {
	\define(__NAMESPACE__ . '\\CMX_EMAILS_MAX_MESSAGES', 500);
}

if (!\defined(__NAMESPACE__ . '\\CMX_EMAILS_ARCHIVE_RETENTION_DAYS')) {
	// Testwert fuer die automatische Archivierung alter Inbox-Mails.
	\define(__NAMESPACE__ . '\\CMX_EMAILS_ARCHIVE_RETENTION_DAYS', 30);
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_message_limit')) {
	function cmx_emails_message_limit(int $requested = 0): int {
		$max = (int) \constant(__NAMESPACE__ . '\\CMX_EMAILS_MAX_MESSAGES');
		if ($max <= 0) {
			$max = 500;
		}

		if ($requested <= 0) {
			$requested = (int) \constant(__NAMESPACE__ . '\\CMX_EMAILS_SYNC_LIMIT');
		}

		if ($requested <= 0) {
			$requested = $max;
		}

		return \max(1, \min($requested, $max));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_archive_retention_days')) {
	function cmx_emails_archive_retention_days(): int {
		$days = (int) \constant(__NAMESPACE__ . '\\CMX_EMAILS_ARCHIVE_RETENTION_DAYS');
		return $days > 0 ? $days : 30;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_archive_cutoff_timestamp')) {
	function cmx_emails_archive_cutoff_timestamp(): int {
		return (int) \time() - (cmx_emails_archive_retention_days() * \DAY_IN_SECONDS);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_archive_period_from_timestamp')) {
	function cmx_emails_archive_period_from_timestamp(int $timestamp): array {
		$timestamp = (int) $timestamp;
		if ($timestamp <= 0) {
			return ['year' => '', 'month' => ''];
		}

		return [
			'year'  => \gmdate('Y', $timestamp),
			'month' => \gmdate('m', $timestamp),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_archive_month_label')) {
	function cmx_emails_archive_month_label(string $month): string {
		$month = \str_pad((string) \preg_replace('/[^0-9]/', '', $month), 2, '0', \STR_PAD_LEFT);
		$labels = [
			'01' => '01 Januar',
			'02' => '02 Februar',
			'03' => '03 Maerz',
			'04' => '04 April',
			'05' => '05 Mai',
			'06' => '06 Juni',
			'07' => '07 Juli',
			'08' => '08 August',
			'09' => '09 September',
			'10' => '10 Oktober',
			'11' => '11 November',
			'12' => '12 Dezember',
		];

		return $labels[$month] ?? $month;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_archive_selection_label')) {
	function cmx_emails_archive_selection_label(string $year, string $month): string {
		$year = \preg_replace('/[^0-9]/', '', $year);
		$month = cmx_emails_normalize_archive_month($month);
		if ($year === '' || $month === '') {
			return 'Archiv';
		}

		return 'Archiv ' . $year . '/' . $month;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_archive_year')) {
	function cmx_emails_archive_year(int $post_id): string {
		return \preg_replace('/[^0-9]/', '', (string) \get_post_meta($post_id, cmx_emails_meta_key('archive_year'), true));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_archive_month')) {
	function cmx_emails_archive_month(int $post_id): string {
		return cmx_emails_normalize_archive_month((string) \get_post_meta($post_id, cmx_emails_meta_key('archive_month'), true));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_normalize_archive_month')) {
	function cmx_emails_normalize_archive_month(string $month): string {
		$month = (string) \preg_replace('/[^0-9]/', '', $month);
		if ($month === '') {
			return '';
		}

		$month = \str_pad($month, 2, '0', \STR_PAD_LEFT);
		if ($month < '01' || $month > '12') {
			return '';
		}

		return $month;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_archive_folder_label_for_post')) {
	function cmx_emails_archive_folder_label_for_post(int $post_id): string {
		return cmx_emails_archive_selection_label(cmx_emails_archive_year($post_id), cmx_emails_archive_month($post_id));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_meta_key')) {
	function cmx_emails_meta_key(string $suffix): string {
		$suffix = \sanitize_key($suffix);
		return '_cmx_email_' . $suffix;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_is_manual_ham_post')) {
	function cmx_emails_is_manual_ham_post(int $post_id): bool {
		$post_id = (int) $post_id;
		if ($post_id <= 0) {
			return false;
		}

		return \get_post_meta($post_id, cmx_emails_meta_key('spam_override'), true) === 'ham';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_mark_post_as_inbox')) {
	function cmx_emails_mark_post_as_inbox(int $post_id, string $mailbox = ''): int {
		$post_id = (int) $post_id;
		if ($post_id <= 0 || (string) \get_post_status($post_id) === 'trash') {
			return 0;
		}

		\update_post_meta($post_id, cmx_emails_meta_key('folder'), 'inbox');
		if ($mailbox !== '') {
			\update_post_meta($post_id, cmx_emails_meta_key('mailbox'), $mailbox);
		}
		\delete_post_meta($post_id, cmx_emails_meta_key('archive_year'));
		\delete_post_meta($post_id, cmx_emails_meta_key('archive_month'));

		return $post_id;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_mark_post_as_manual_ham')) {
	function cmx_emails_mark_post_as_manual_ham(int $post_id, string $mailbox = ''): int {
		$post_id = cmx_emails_mark_post_as_inbox($post_id, $mailbox);
		if ($post_id <= 0) {
			return 0;
		}

		\update_post_meta($post_id, cmx_emails_meta_key('spam_override'), 'ham');
		\update_post_meta($post_id, cmx_emails_meta_key('spam_status'), 'clean');
		\update_post_meta($post_id, cmx_emails_meta_key('spam_score'), '0');
		\update_post_meta($post_id, cmx_emails_meta_key('spam_reasons'), []);

		return $post_id;
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_attachment_action_key')) {
	function cmx_emails_attachment_action_key(array $attachment): string {
		$rel = \ltrim((string) ($attachment['rel'] ?? ''), '/');
		$path = \wp_normalize_path((string) ($attachment['path'] ?? ''));
		$filename = \sanitize_file_name((string) ($attachment['filename'] ?? ''));
		$mime = \strtolower(\trim((string) ($attachment['mime'] ?? '')));
		$size = isset($attachment['size']) ? (string) (int) $attachment['size'] : '';

		return \sha1(\implode('|', [$rel, $path, $filename, $mime, $size]));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_attachment_is_pdf')) {
	function cmx_emails_attachment_is_pdf(array $attachment): bool {
		$mime = \strtolower(\trim((string) ($attachment['mime'] ?? '')));
		if ($mime === 'application/pdf') {
			return true;
		}

		$filename = (string) ($attachment['filename'] ?? '');
		return \strtolower((string) \pathinfo($filename, \PATHINFO_EXTENSION)) === 'pdf';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_attachment_binary')) {
	function cmx_emails_attachment_binary(array $attachment): string {
		$path = \wp_normalize_path((string) ($attachment['path'] ?? ''));
		$rel = \ltrim((string) ($attachment['rel'] ?? ''), '/');
		if ($path === '' && $rel !== '') {
			$uploads = \wp_get_upload_dir();
			$basedir = \wp_normalize_path((string) ($uploads['basedir'] ?? ''));
			if ($basedir !== '') {
				$path = $basedir . '/' . $rel;
			}
		}

		if ($path === '' || !\is_file($path) || !\is_readable($path)) {
			return '';
		}

		$binary = @\file_get_contents($path);
		return \is_string($binary) ? $binary : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_flatten_keywords')) {
	function cmx_emails_flatten_keywords($value): array {
		$words = [];
		if (\is_array($value)) {
			foreach ($value as $entry) {
				$words = \array_merge($words, cmx_emails_flatten_keywords($entry));
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

		return \array_values(\array_unique(\array_filter($words, static function ($word): bool {
			return \is_string($word) && $word !== '';
		})));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_beleg_filter_keywords')) {
	function cmx_emails_beleg_filter_keywords(): array {
		static $keywords = null;
		if (\is_array($keywords)) {
			return $keywords;
		}

		$keywords = [];
		if (\function_exists(__NAMESPACE__ . '\\cmx_ini_get_value')) {
			$keywords = \array_merge($keywords, cmx_emails_flatten_keywords(cmx_ini_get_value('Belege', 'Filter')));
		}

		if ($keywords === []) {
			$ini_file = \dirname(__DIR__, 2) . '/includes/globales.ini';
			if (\is_file($ini_file)) {
				$ini = \parse_ini_file($ini_file, true, \INI_SCANNER_TYPED);
				if (\is_array($ini)) {
					foreach ($ini as $section => $section_data) {
						if (!\is_array($section_data) || \strtolower(\trim((string) $section)) !== 'belege') {
							continue;
						}
						foreach ($section_data as $key => $value) {
							if (\strtolower(\trim((string) $key)) !== 'filter') {
								continue;
							}
							$keywords = \array_merge($keywords, cmx_emails_flatten_keywords($value));
						}
					}
				}
			}
		}

		$keywords = \array_values(\array_unique($keywords));
		return $keywords;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_pdf_keyword_match')) {
	function cmx_emails_pdf_keyword_match(string $pdf_binary, array $keywords): bool {
		$keywords = cmx_emails_flatten_keywords($keywords);
		if ($keywords === []) {
			return false;
		}

		$haystacks = [\strtolower($pdf_binary)];
		if (\preg_match_all('/stream\\r?\\n(.*?)\\r?\\nendstream/s', $pdf_binary, $matches)) {
			foreach ((array) ($matches[1] ?? []) as $stream) {
				$stream = \trim((string) $stream, "\r\n");
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
				$gzd = \function_exists('gzdecode') ? @\gzdecode($stream) : false;
				if (\is_string($gzd) && $gzd !== '') {
					$decoded_variants[] = $gzd;
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
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_attachment_store_mode')) {
	function cmx_emails_attachment_store_mode(array $attachment): string {
		if (!cmx_emails_attachment_is_pdf($attachment)) {
			return '';
		}

		static $cache = [];
		$key = cmx_emails_attachment_action_key($attachment);
		if (isset($cache[$key])) {
			return $cache[$key];
		}

		$binary = cmx_emails_attachment_binary($attachment);
		if ($binary === '') {
			$cache[$key] = '';
			return '';
		}

		$cache[$key] = cmx_emails_pdf_keyword_match($binary, cmx_emails_beleg_filter_keywords())
			? 'beleg'
			: 'dokument';

		return $cache[$key];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_document_contact_rel_key')) {
	function cmx_emails_document_contact_rel_key(): string {
		if (\defined(__NAMESPACE__ . '\\CMX_DOK_REL_META')) {
			$map = \constant(__NAMESPACE__ . '\\CMX_DOK_REL_META');
			if (\is_array($map) && isset($map['kontakte']) && \is_string($map['kontakte']) && $map['kontakte'] !== '') {
				return $map['kontakte'];
			}
		}

		return 'cmx_dokumente_kunden';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_document_uploads_meta_key')) {
	function cmx_emails_document_uploads_meta_key(): string {
		if (\defined(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')) {
			$key = (string) \constant(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META');
			if ($key !== '') {
				return $key;
			}
		}

		return '_cmx_dokumente_uploads';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_document_self_meta_key')) {
	function cmx_emails_document_self_meta_key(): string {
		if (\defined(__NAMESPACE__ . '\\CMX_DOK_SELF_META')) {
			$key = (string) \constant(__NAMESPACE__ . '\\CMX_DOK_SELF_META');
			if ($key !== '') {
				return $key;
			}
		}

		return '_cmx_dokumente_files';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_store_pdf_for_document')) {
	function cmx_emails_store_pdf_for_document(string $filename, string $binary): string {
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
			$name = \wp_date('ymd-His') . '-mail.pdf';
		}
		$name = \wp_unique_filename($target_dir, $name);
		$target_abs = \wp_normalize_path((string) ($target_dir . '/' . $name));
		if (@\file_put_contents($target_abs, $binary) === false) {
			return '';
		}
		@\chmod($target_abs, 0666);

		return cmx_emails_upload_rel_from_abs($target_abs);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_store_pdf_for_beleg')) {
	function cmx_emails_store_pdf_for_beleg(int $beleg_id, string $filename, string $binary): string {
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
				$prefix = \wp_date('ymd-His') . '-mail';
			}
			\update_post_meta($beleg_id, '_cmx_beleg_upload_prefix', $prefix);
		}

		$next = \function_exists(__NAMESPACE__ . '\\cmx_belege_next_suffix')
			? \max(1, (int) cmx_belege_next_suffix($target_dir, $prefix))
			: 1;

		do {
			$target_name = $prefix . '_upload_' . \str_pad((string) $next, 3, '0', \STR_PAD_LEFT) . '.pdf';
			$next++;
			$target_abs = \wp_normalize_path((string) (\rtrim($target_dir, '/\\') . \DIRECTORY_SEPARATOR . $target_name));
		} while (\is_file($target_abs));

		if (@\file_put_contents($target_abs, $binary) === false) {
			return '';
		}
		@\chmod($target_abs, 0666);

		$target_rel = cmx_emails_upload_rel_from_abs($target_abs);
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
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_attachment_subject')) {
	function cmx_emails_attachment_subject(int $post_id): string {
		$subject = cmx_emails_subject_text((string) \get_post_meta($post_id, cmx_emails_meta_key('subject'), true));
		if ($subject !== '') {
			return $subject;
		}

		return cmx_emails_subject_text((string) \get_the_title($post_id));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_attachment_target_contact_ids')) {
	function cmx_emails_attachment_target_contact_ids(int $post_id): array {
		$contact_ids = cmx_emails_assignment_contact_ids($post_id);
		$contact_ids = \array_values(\array_unique(\array_map('intval', $contact_ids)));
		$contact_ids = \array_values(\array_filter($contact_ids, static function (int $contact_id): bool {
			return $contact_id > 0;
		}));

		return $contact_ids !== [] ? $contact_ids : [0];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_pdf_attachments_for_post')) {
	function cmx_emails_pdf_attachments_for_post(int $post_id): array {
		$post_id = (int) $post_id;
		if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_EMAILS_CPT) {
			return [];
		}

		$pdf_attachments = [];
		foreach (cmx_emails_normalize_attachment_list(\get_post_meta($post_id, cmx_emails_meta_key('attachments'), true)) as $attachment) {
			if (\is_array($attachment) && cmx_emails_attachment_is_pdf($attachment)) {
				$pdf_attachments[] = $attachment;
			}
		}

		return $pdf_attachments;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_create_attachment_document')) {
	function cmx_emails_create_attachment_document(int $contact_id, string $subject, array $attachment, string $binary, array $project_ids = []): array {
		if (!\post_type_exists('dokumente')) {
			return ['post_id' => 0, 'upload_rel' => ''];
		}

		$file_name = (string) ($attachment['filename'] ?? '');
		$title_from_file = (string) \pathinfo($file_name, \PATHINFO_FILENAME);
		$title = \sanitize_text_field(\trim($title_from_file !== '' ? $title_from_file : $subject));
		if ($title === '') {
			$title = \wp_date('ymd-His') . ' mail';
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
		$rel = cmx_emails_store_pdf_for_document($file_name !== '' ? $file_name : 'attachment.pdf', $binary);
		if ($rel === '') {
			\wp_delete_post($doc_id, true);
			return ['post_id' => 0, 'upload_rel' => ''];
		}

		\update_post_meta($doc_id, '_cmx_dokumente_file_path', $rel);
		\update_post_meta($doc_id, cmx_emails_document_self_meta_key(), [$rel]);

		$contact_id = (int) $contact_id;
		if ($contact_id > 0) {
			if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_link_docs_to_kontakt')) {
				cmx_scanner_link_docs_to_kontakt($contact_id, [$doc_id]);
			} else {
				$kontakt_rel_key = cmx_emails_document_contact_rel_key();
				$current_contacts = (array) \get_post_meta($doc_id, $kontakt_rel_key, true);
				$current_contacts = \array_values(\array_unique(\array_map('intval', $current_contacts)));
				if (!\in_array($contact_id, $current_contacts, true)) {
					$current_contacts[] = $contact_id;
					\update_post_meta($doc_id, $kontakt_rel_key, \array_values(\array_filter($current_contacts)));
				}

				$kontakt_uploads_meta = cmx_emails_document_uploads_meta_key();
				$kontakt_docs = (array) \get_post_meta($contact_id, $kontakt_uploads_meta, true);
				$kontakt_docs = \array_values(\array_unique(\array_map('intval', $kontakt_docs)));
				if (!\in_array($doc_id, $kontakt_docs, true)) {
					$kontakt_docs[] = $doc_id;
					\update_post_meta($contact_id, $kontakt_uploads_meta, $kontakt_docs);
				}
			}
		}

		$project_ids = \array_values(\array_unique(\array_map('intval', $project_ids)));
		if ($project_ids !== [] && \function_exists(__NAMESPACE__ . '\\cmx_scanner_link_docs_to_projekte')) {
			foreach ($project_ids as $project_id) {
				if ($project_id > 0) {
					cmx_scanner_link_docs_to_projekte($project_id, [$doc_id]);
				}
			}
		}

		return ['post_id' => $doc_id, 'upload_rel' => $rel];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_create_attachment_beleg')) {
	function cmx_emails_create_attachment_beleg(int $contact_id, string $subject, array $attachment, string $binary, int $source_post_id = 0): array {
		if (!\post_type_exists('belege')) {
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
		if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_prepare_new_beleg_defaults')) {
			cmx_scanner_prepare_new_beleg_defaults($beleg_id);
		}

		$richtung_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_RICHTUNG')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_RICHTUNG')
			: '_cmx_beleg_richtung';
		$datum_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_RNG_DATUM')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_RNG_DATUM')
			: '_cmx_beleg_rng_datum';
		$betreff_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_BETREFF')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_BETREFF')
			: '_cmx_beleg_betreff';
		$kontakt_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID')
			: '_cmx_beleg_kontakt_id';

		\update_post_meta($beleg_id, $richtung_key, 'eingang');
		$received_ts = $source_post_id > 0 ? (int) \get_post_meta($source_post_id, cmx_emails_meta_key('received_ts'), true) : 0;
		\update_post_meta($beleg_id, $datum_key, \wp_date('Y-m-d', $received_ts > 0 ? $received_ts : \time()));

		if (\trim($subject) !== '') {
			\update_post_meta($beleg_id, $betreff_key, \sanitize_text_field($subject));
		}

		$contact_id = (int) $contact_id;
		if ($contact_id > 0) {
			\update_post_meta($beleg_id, $kontakt_key, $contact_id);
			\update_post_meta($beleg_id, '_cmx_beleg_kontakt_label', (string) \get_the_title($contact_id));
		} else {
			\delete_post_meta($beleg_id, $kontakt_key);
			\delete_post_meta($beleg_id, '_cmx_beleg_kontakt_label');
		}

		$rel = cmx_emails_store_pdf_for_beleg($beleg_id, (string) ($attachment['filename'] ?? 'attachment.pdf'), $binary);
		if ($rel === '') {
			\wp_delete_post($beleg_id, true);
			return ['post_id' => 0, 'upload_rel' => ''];
		}

		return ['post_id' => $beleg_id, 'upload_rel' => $rel];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_register_imported_posts')) {
	function cmx_emails_register_imported_posts(int $post_id, array $created_ids): void {
		$created_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $created_ids))));
		if ($created_ids === []) {
			return;
		}

		$existing_ids = (array) \get_post_meta($post_id, cmx_emails_meta_key('imported_post_ids'), true);
		$existing_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $existing_ids))));
		$merged_ids = \array_values(\array_unique(\array_merge($existing_ids, $created_ids)));

		\update_post_meta($post_id, cmx_emails_meta_key('imported_post_ids'), $merged_ids);
		\update_post_meta($post_id, cmx_emails_meta_key('manual_status'), 'processed');
		\update_post_meta($post_id, cmx_emails_meta_key('status'), 'processed');
		\update_post_meta($post_id, cmx_emails_meta_key('imported_at'), (string) \time());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_store_attachment_by_key')) {
	function cmx_emails_store_attachment_by_key(int $post_id, string $attachment_key): array {
		$post_id = (int) $post_id;
		$attachment_key = \sanitize_text_field($attachment_key);
		if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_EMAILS_CPT) {
			return ['ok' => false, 'message' => 'E-Mail wurde nicht gefunden.'];
		}
		if ($attachment_key === '') {
			return ['ok' => false, 'message' => 'Anhang wurde nicht gefunden.'];
		}

		$attachment = [];
		foreach (cmx_emails_normalize_attachment_list(\get_post_meta($post_id, cmx_emails_meta_key('attachments'), true)) as $candidate) {
			if (cmx_emails_attachment_action_key($candidate) === $attachment_key) {
				$attachment = (array) $candidate;
				break;
			}
		}
		if ($attachment === []) {
			return ['ok' => false, 'message' => 'Anhang wurde nicht gefunden.'];
		}
		if (!cmx_emails_attachment_is_pdf($attachment)) {
			return ['ok' => false, 'message' => 'Nur PDF-Anhaenge koennen gespeichert werden.'];
		}

		$binary = cmx_emails_attachment_binary($attachment);
		if ($binary === '') {
			return ['ok' => false, 'message' => 'PDF-Datei ist nicht lesbar.'];
		}

		$mode = cmx_emails_attachment_store_mode($attachment);
		if ($mode === '') {
			return ['ok' => false, 'message' => 'Speicherziel fuer PDF konnte nicht ermittelt werden.'];
		}

		$subject = cmx_emails_attachment_subject($post_id);
		$contact_ids = cmx_emails_attachment_target_contact_ids($post_id);
		$project_ids = cmx_emails_assignment_project_ids($post_id);
		$created_ids = [];
		foreach ($contact_ids as $contact_id) {
			$contact_id = (int) $contact_id;
			$created = $mode === 'beleg'
				? cmx_emails_create_attachment_beleg($contact_id, $subject, $attachment, $binary, $post_id)
				: cmx_emails_create_attachment_document($contact_id, $subject, $attachment, $binary, $project_ids);
			$created_id = (int) ($created['post_id'] ?? 0);
			if ($created_id > 0) {
				$created_ids[] = $created_id;
			}
		}

		if ($created_ids === []) {
			return ['ok' => false, 'message' => 'PDF konnte nicht gespeichert werden.'];
		}

		cmx_emails_register_imported_posts($post_id, $created_ids);

		$count = \count($created_ids);
		$message = $mode === 'beleg'
			? ($count === 1 ? '1 Beleg wurde erstellt.' : $count . ' Belege wurden erstellt.')
			: ($count === 1 ? '1 Dokument wurde erstellt.' : $count . ' Dokumente wurden erstellt.');

		return [
			'ok' => true,
			'message' => $message,
			'created_ids' => \array_values(\array_unique(\array_map('intval', $created_ids))),
			'mode' => $mode,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_store_all_pdf_attachments')) {
	function cmx_emails_store_all_pdf_attachments(int $post_id): array {
		$post_id = (int) $post_id;
		if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_EMAILS_CPT) {
			return ['ok' => false, 'message' => 'E-Mail wurde nicht gefunden.'];
		}

		$pdf_attachments = cmx_emails_pdf_attachments_for_post($post_id);
		if ($pdf_attachments === []) {
			return ['ok' => false, 'message' => 'Diese E-Mail hat keine PDF-Anhaenge.'];
		}

		$created_ids = [];
		$beleg_count = 0;
		$dokument_count = 0;
		$processed_pdf_count = 0;
		$last_error = '';

		foreach ($pdf_attachments as $attachment) {
			$result = cmx_emails_store_attachment_by_key($post_id, cmx_emails_attachment_action_key((array) $attachment));
			if (empty($result['ok'])) {
				$last_error = (string) ($result['message'] ?? '');
				continue;
			}

			$processed_pdf_count++;
			$current_ids = \array_values(\array_unique(\array_map('intval', (array) ($result['created_ids'] ?? []))));
			$created_ids = \array_merge($created_ids, $current_ids);

			$mode = (string) ($result['mode'] ?? '');
			if ($mode === 'beleg') {
				$beleg_count += \count($current_ids);
			} elseif ($mode === 'dokument') {
				$dokument_count += \count($current_ids);
			}
		}

		$created_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $created_ids))));
		if ($created_ids === []) {
			return ['ok' => false, 'message' => $last_error !== '' ? $last_error : 'PDF konnte nicht gespeichert werden.'];
		}

		if ($beleg_count > 0 && $dokument_count > 0) {
			$message = $processed_pdf_count . ' PDF-Anhaenge uebernommen: ' . $beleg_count . ' Belege, ' . $dokument_count . ' Dokumente erstellt.';
		} elseif ($beleg_count > 0) {
			$message = $processed_pdf_count . ' PDF-Anhaenge uebernommen: ' . $beleg_count . ' Belege erstellt.';
		} else {
			$message = $processed_pdf_count . ' PDF-Anhaenge uebernommen: ' . $dokument_count . ' Dokumente erstellt.';
		}

		return [
			'ok' => true,
			'message' => $message,
			'created_ids' => $created_ids,
			'beleg_count' => $beleg_count,
			'dokument_count' => $dokument_count,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_attachment_store_button_title')) {
	function cmx_emails_attachment_store_button_title(array $attachment): string {
		$mode = cmx_emails_attachment_store_mode($attachment);
		if ($mode === 'beleg') {
			return 'PDF als Beleg speichern';
		}
		if ($mode === 'dokument') {
			return 'PDF als Dokument speichern';
		}

		return 'PDF speichern';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_render_attachment_list')) {
	function cmx_emails_render_attachment_list(int $post_id, array $attachments, array $context = []): void {
		if ($context === [] && \function_exists(__NAMESPACE__ . '\\cmx_emails_action_context')) {
			$context = cmx_emails_action_context($post_id);
		}

		echo '<div class="cmx-email-attachments">';
		foreach ($attachments as $attachment) {
			if (!\is_array($attachment)) {
				continue;
			}

			$filename = \sanitize_file_name((string) ($attachment['filename'] ?? 'Anhang'));
			if ($filename === '') {
				$filename = 'Anhang';
			}
			$url = (string) ($attachment['url'] ?? '');
			$size = (int) ($attachment['size'] ?? 0);
			$is_pdf = cmx_emails_attachment_is_pdf($attachment);
			$store_mode = $is_pdf ? cmx_emails_attachment_store_mode($attachment) : '';
			$store_title = $store_mode !== '' ? cmx_emails_attachment_store_button_title($attachment) : '';

			$meta_parts = [];
			if ($size > 0) {
				$meta_parts[] = \size_format($size, 0);
			}
			if ($store_mode === 'beleg') {
				$meta_parts[] = 'Wird als Beleg gespeichert';
			} elseif ($store_mode === 'dokument') {
				$meta_parts[] = 'Wird als Dokument gespeichert';
			}

			echo '<div class="cmx-email-attachment">';
			echo '<div class="cmx-email-attachment-main">';
			if ($url !== '') {
				echo '<a class="cmx-email-attachment-title" href="' . \esc_url($url) . '" download>' . \esc_html($filename) . '</a>';
			} else {
				echo '<span class="cmx-email-attachment-title">' . \esc_html($filename) . '</span>';
			}
			if ($meta_parts !== []) {
				echo '<div class="cmx-email-attachment-meta">' . \esc_html(\implode(' | ', $meta_parts)) . '</div>';
			}
			echo '</div>';

			if ($store_mode !== '') {
				echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '" class="cmx-email-attachment-actions">';
				\wp_nonce_field('cmx_emails_store_pdf_attachment', '_wpnonce', false, true);
				echo '<input type="hidden" name="action" value="cmx_emails_store_pdf_attachment">';
				echo '<input type="hidden" name="post_id" value="' . (int) $post_id . '">';
				echo '<input type="hidden" name="attachment_key" value="' . \esc_attr(cmx_emails_attachment_action_key($attachment)) . '">';
				echo '<input type="hidden" name="account_id" value="' . \esc_attr(\sanitize_key((string) ($context['account_id'] ?? ''))) . '">';
				echo '<input type="hidden" name="folder" value="' . \esc_attr(\sanitize_key((string) ($context['folder'] ?? ''))) . '">';
				echo '<input type="hidden" name="archive_year" value="' . \esc_attr(\preg_replace('/[^0-9]/', '', (string) ($context['archive_year'] ?? ''))) . '">';
				echo '<input type="hidden" name="archive_month" value="' . \esc_attr(\function_exists(__NAMESPACE__ . '\\cmx_emails_normalize_archive_month') ? cmx_emails_normalize_archive_month((string) ($context['archive_month'] ?? '')) : '') . '">';
				echo '<button type="submit" class="cmx-email-attachment-store" title="' . \esc_attr($store_title) . '" aria-label="' . \esc_attr($store_title) . '">';
				echo '<span class="dashicons dashicons-database-add" aria-hidden="true"></span>';
				echo '</button>';
				echo '</form>';
			}

			echo '</div>';
		}
		echo '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_decode_mime_text')) {
	function cmx_emails_decode_mime_text(string $value): string {
		$value = \trim($value);
		if ($value === '') {
			return '';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_mail_import_decode_mime_header')) {
			return (string) cmx_mail_import_decode_mime_header($value);
		}

		if (\function_exists('iconv_mime_decode')) {
			$decoded = \iconv_mime_decode($value, \ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
			if (\is_string($decoded) && \trim($decoded) !== '') {
				return \trim($decoded);
			}
		}

		if (\function_exists('mb_decode_mimeheader')) {
			$decoded = @\mb_decode_mimeheader($value);
			if (\is_string($decoded) && \trim($decoded) !== '') {
				return \trim($decoded);
			}
		}

		if (\function_exists('imap_mime_header_decode')) {
			$decoded = \imap_mime_header_decode($value);
			if (\is_array($decoded) && !empty($decoded)) {
				$out = '';
				foreach ($decoded as $part) {
					if (!\is_object($part) || !isset($part->text)) {
						continue;
					}
					$out .= (string) $part->text;
				}
				if ($out !== '') {
					return $out;
				}
			}
		}

		return $value;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_subject_text')) {
	function cmx_emails_subject_text(string $value): string {
		$value = \trim((string) $value);
		if ($value === '') {
			return '';
		}

		$decoded = \trim(cmx_emails_decode_mime_text($value));
		return $decoded !== '' ? $decoded : $value;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_part_filename')) {
	function cmx_emails_part_filename($part): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_mail_import_part_filename')) {
			return (string) cmx_mail_import_part_filename($part);
		}

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
			$value = cmx_emails_decode_mime_text((string) ($param->value ?? ''));

			if (\preg_match('/^(filename|name)\\*(\\d+)\\*?$/', $attr, $match) === 1) {
				$key = (string) ($match[1] ?? '');
				$index = (int) ($match[2] ?? 0);
				$value = (string) \rawurldecode($value);
				if ($value !== '') {
					$multipart_names[$key][$index] = $value;
				}
				continue;
			}

			if (\preg_match('/^(filename|name)\\*$/', $attr, $match) === 1) {
				$key = (string) ($match[1] ?? '');
				$value = (string) \rawurldecode($value);
				if ($value !== '') {
					$single_names[$key] = $value;
				}
				continue;
			}

			if ((\in_array($attr, ['filename', 'name'], true)) && $value !== '' && $single_names[$attr] === '') {
				$single_names[$attr] = $value;
			}
		}

		foreach (['filename', 'name'] as $key) {
			if ($multipart_names[$key] !== []) {
				\ksort($multipart_names[$key], \SORT_NUMERIC);
				$joined = \implode('', $multipart_names[$key]);
				if ($joined !== '') {
					return \trim($joined);
				}
			}
			if ($single_names[$key] !== '') {
				return \trim($single_names[$key]);
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_part_mime_type')) {
	function cmx_emails_part_mime_type($part): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_mail_import_get_mime_type')) {
			return (string) cmx_mail_import_get_mime_type($part);
		}

		$type_map = ['text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'other'];
		$type_id = \is_object($part) && isset($part->type) ? (int) $part->type : 7;
		$primary = $type_map[$type_id] ?? 'other';
		$subtype = \is_object($part) && isset($part->subtype) ? \strtolower((string) $part->subtype) : 'octet-stream';
		return \strtolower($primary . '/' . $subtype);
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_analyze_message_spam')) {
	function cmx_emails_analyze_message_spam(array $message): array {
		if (!\class_exists(__NAMESPACE__ . '\\CMX_Spams')) {
			$spam_file = __DIR__ . '/spams.php';
			if (\is_file($spam_file)) {
				require_once $spam_file;
			}
		}

		if (!\class_exists(__NAMESPACE__ . '\\CMX_Spams')) {
			return [];
		}

		try {
			$result = CMX_Spams::analyze([
				'subject' => (string) ($message['subject'] ?? ''),
				'from' => (string) ($message['from'] ?? ''),
				'headers_raw' => (string) ($message['headers_raw'] ?? ''),
				'body_text' => (string) ($message['body_text'] ?? ''),
				'body_html' => (string) ($message['body_html'] ?? ''),
			]);
		} catch (\Throwable $e) {
			return [];
		}

		if (!\is_array($result) || $result === []) {
			return [];
		}

		return [
			'status' => \sanitize_key((string) ($result['status'] ?? '')),
			'score' => \max(0, (int) ($result['score'] ?? 0)),
			'reasons' => \array_values(\array_filter(\array_map('sanitize_text_field', (array) ($result['reasons'] ?? [])))),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_find_post_id')) {
	function cmx_emails_find_post_id(string $client_id, string $folder, int $uid, string $mailbox = '', string $archive_year = '', string $archive_month = '', bool $include_trash = true): int {
		$folder = \sanitize_key($folder);
		$meta_query = [
			['key' => cmx_emails_meta_key('account_id'), 'value' => \sanitize_key($client_id)],
			['key' => cmx_emails_meta_key('folder'), 'value' => $folder],
			['key' => cmx_emails_meta_key('uid'), 'value' => (string) \max(0, $uid)],
		];

		if ($folder === 'archive') {
			$mailbox = \trim($mailbox);
			$archive_year = \preg_replace('/[^0-9]/', '', $archive_year);
			$archive_month = cmx_emails_normalize_archive_month($archive_month);

			if ($mailbox !== '') {
				$meta_query[] = ['key' => cmx_emails_meta_key('mailbox'), 'value' => $mailbox];
			} elseif (\strlen($archive_year) === 4 && $archive_month !== '') {
				$meta_query[] = ['key' => cmx_emails_meta_key('archive_year'), 'value' => $archive_year];
				$meta_query[] = ['key' => cmx_emails_meta_key('archive_month'), 'value' => $archive_month];
			}
		}

		$post_status = ['publish', 'draft', 'private', 'pending'];
		if ($include_trash) {
			$post_status[] = 'trash';
		}

		$ids = \get_posts([
			'post_type'        => CMX_EMAILS_CPT,
			'post_status'      => $post_status,
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_query'       => $meta_query,
		]);

		return isset($ids[0]) ? (int) $ids[0] : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_find_post_id_by_message_id')) {
	function cmx_emails_find_post_id_by_message_id(string $client_id, string $message_id, bool $include_trash = true): int {
		$message_id = \sanitize_text_field($message_id);
		if ($message_id === '') {
			return 0;
		}

		$post_status = ['publish', 'draft', 'private', 'pending'];
		if ($include_trash) {
			$post_status[] = 'trash';
		}

		$ids = \get_posts([
			'post_type'        => CMX_EMAILS_CPT,
			'post_status'      => $post_status,
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_query'       => [
				['key' => cmx_emails_meta_key('account_id'), 'value' => \sanitize_key($client_id)],
				['key' => cmx_emails_meta_key('message_id'), 'value' => $message_id],
			],
		]);

		return isset($ids[0]) ? (int) $ids[0] : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_find_post_ids_by_message_id')) {
	function cmx_emails_find_post_ids_by_message_id(string $client_id, string $message_id, int $limit = 25): array {
		$message_id = \sanitize_text_field($message_id);
		if ($message_id === '') {
			return [];
		}

		$ids = \get_posts([
			'post_type'        => CMX_EMAILS_CPT,
			'post_status'      => ['publish', 'draft', 'private', 'pending', 'trash'],
			'posts_per_page'   => $limit,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_query'       => [
				['key' => cmx_emails_meta_key('account_id'), 'value' => \sanitize_key($client_id)],
				['key' => cmx_emails_meta_key('message_id'), 'value' => $message_id],
			],
		]);

		return \array_values(\array_filter(\array_map('intval', (array) $ids)));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_find_post_ids_by_fingerprint')) {
	function cmx_emails_find_post_ids_by_fingerprint(string $client_id, string $subject = '', string $sender_email = '', int $received_ts = 0, array $folders = ['archive', 'inbox']): array {
		$client_id = \sanitize_key($client_id);
		$subject = \sanitize_text_field($subject);
		$sender_email = \sanitize_email($sender_email);
		$received_ts = (int) $received_ts;
		$folders = \array_values(\array_filter(\array_map('sanitize_key', $folders), static function (string $folder): bool {
			return $folder !== '';
		}));

		if ($client_id === '' || ($subject === '' && $sender_email === '' && $received_ts <= 0)) {
			return [];
		}

		$meta_query = [
			['key' => cmx_emails_meta_key('account_id'), 'value' => $client_id],
		];
		if ($subject !== '') {
			$meta_query[] = ['key' => cmx_emails_meta_key('subject'), 'value' => $subject];
		}
		if ($sender_email !== '') {
			$meta_query[] = ['key' => cmx_emails_meta_key('sender_email'), 'value' => $sender_email];
		}

		$ids = \get_posts([
			'post_type'        => CMX_EMAILS_CPT,
			'post_status'      => ['publish', 'draft', 'private', 'pending', 'trash'],
			'posts_per_page'   => 50,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_query'       => $meta_query,
		]);

		$matched = [];
		foreach ((array) $ids as $candidate_id) {
			$candidate_id = (int) $candidate_id;
			if ($candidate_id <= 0 || (string) \get_post_status($candidate_id) === 'trash') {
				continue;
			}

			$candidate_folder = \sanitize_key((string) \get_post_meta($candidate_id, cmx_emails_meta_key('folder'), true));
			if ($folders !== [] && !\in_array($candidate_folder, $folders, true)) {
				continue;
			}

			$score = 0;
			if ($candidate_folder !== '') {
				$score += 2;
			}

			$candidate_subject = \sanitize_text_field((string) \get_post_meta($candidate_id, cmx_emails_meta_key('subject'), true));
			if ($subject !== '' && $candidate_subject === $subject) {
				$score += 5;
			}

			$candidate_sender_email = \sanitize_email((string) \get_post_meta($candidate_id, cmx_emails_meta_key('sender_email'), true));
			if ($sender_email !== '' && $candidate_sender_email === $sender_email) {
				$score += 5;
			}

			$candidate_ts = (int) \get_post_meta($candidate_id, cmx_emails_meta_key('received_ts'), true);
			if ($received_ts > 0 && $candidate_ts > 0) {
				$delta = \abs($candidate_ts - $received_ts);
				if ($delta === 0) {
					$score += 6;
				} elseif ($delta <= 300) {
					$score += 5;
				} elseif ($delta <= 3600) {
					$score += 3;
				}
			}

			if ($score >= 5) {
				$matched[$candidate_id] = $candidate_id;
			}
		}

		return \array_values($matched);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_collect_matching_post_ids_for_spam')) {
	function cmx_emails_collect_matching_post_ids_for_spam(string $client_id, int $uid, string $message_id, array $fingerprint = []): array {
		$matched = [];

		foreach (['inbox', 'archive'] as $folder) {
			$post_id = cmx_emails_find_post_id($client_id, $folder, $uid);
			if ($post_id > 0) {
				$matched[$post_id] = $post_id;
			}
		}

		foreach (cmx_emails_find_post_ids_by_message_id($client_id, $message_id) as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id > 0) {
				$matched[$post_id] = $post_id;
			}
		}

		foreach (cmx_emails_find_post_ids_by_fingerprint(
			$client_id,
			(string) ($fingerprint['subject'] ?? ''),
			(string) ($fingerprint['sender_email'] ?? ''),
			(int) ($fingerprint['received_ts'] ?? 0),
			(array) ($fingerprint['folders'] ?? ['archive', 'inbox'])
		) as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id > 0) {
				$matched[$post_id] = $post_id;
			}
		}

		return \array_values($matched);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_post_ids_have_manual_ham')) {
	function cmx_emails_post_ids_have_manual_ham(array $post_ids): bool {
		foreach ($post_ids as $post_id) {
			if (cmx_emails_is_manual_ham_post((int) $post_id)) {
				return true;
			}
		}

		return false;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_message_has_manual_ham_override')) {
	function cmx_emails_message_has_manual_ham_override(string $client_id, int $uid, string $message_id, array $fingerprint = []): bool {
		return cmx_emails_post_ids_have_manual_ham(
			cmx_emails_collect_matching_post_ids_for_spam($client_id, $uid, $message_id, $fingerprint)
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_mark_existing_message_as_spam')) {
	function cmx_emails_mark_existing_message_as_spam(string $client_id, int $uid, string $message_id, string $mailbox = '', array $fingerprint = []): int {
		$post_ids = cmx_emails_collect_matching_post_ids_for_spam($client_id, $uid, $message_id, $fingerprint);
		if ($post_ids === []) {
			return 0;
		}

		$first_post_id = 0;
		foreach ($post_ids as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0 || (string) \get_post_status($post_id) === 'trash') {
				continue;
			}
			if ($first_post_id <= 0) {
				$first_post_id = $post_id;
			}
			cmx_emails_mark_post_as_spam($post_id, $mailbox);
		}

		return $first_post_id;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_mark_existing_message_as_manual_ham')) {
	function cmx_emails_mark_existing_message_as_manual_ham(string $client_id, int $uid, string $message_id, string $mailbox = '', array $fingerprint = []): int {
		$post_ids = cmx_emails_collect_matching_post_ids_for_spam($client_id, $uid, $message_id, $fingerprint);
		if ($post_ids === []) {
			return 0;
		}

		$first_post_id = 0;
		foreach ($post_ids as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0 || (string) \get_post_status($post_id) === 'trash') {
				continue;
			}
			if ($first_post_id <= 0) {
				$first_post_id = $post_id;
			}
			cmx_emails_mark_post_as_manual_ham($post_id, $mailbox);
		}

		return $first_post_id;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_mark_post_as_spam')) {
	function cmx_emails_mark_post_as_spam(int $post_id, string $mailbox = ''): int {
		$post_id = (int) $post_id;
		if ($post_id <= 0 || (string) \get_post_status($post_id) === 'trash') {
			return 0;
		}
		if (cmx_emails_is_manual_ham_post($post_id)) {
			return $post_id;
		}

		\update_post_meta($post_id, cmx_emails_meta_key('folder'), 'spam');
		\update_post_meta($post_id, cmx_emails_meta_key('spam_status'), 'spam');
		\update_post_meta($post_id, cmx_emails_meta_key('spam_score'), '100');
		\update_post_meta($post_id, cmx_emails_meta_key('spam_reasons'), ['Manuell als Spam markiert']);
		if ($mailbox !== '') {
			\update_post_meta($post_id, cmx_emails_meta_key('mailbox'), $mailbox);
		}
		\delete_post_meta($post_id, cmx_emails_meta_key('archive_year'));
		\delete_post_meta($post_id, cmx_emails_meta_key('archive_month'));

		$account_id = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('account_id'), true));
		if ($account_id !== '' && \function_exists(__NAMESPACE__ . '\\cmx_emails_spam_sender_email_map')) {
			cmx_emails_spam_sender_email_map($account_id, true);
		}

		return $post_id;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_spam_sender_email_map')) {
	function cmx_emails_spam_sender_email_map(string $client_id = '', bool $refresh = false): array {
		static $cache = [];

		$client_id = \sanitize_key($client_id);
		$cache_key = $client_id !== '' ? $client_id : '__all__';
		if (!$refresh && isset($cache[$cache_key])) {
			return $cache[$cache_key];
		}

		$meta_query = [
			['key' => cmx_emails_meta_key('folder'), 'value' => 'spam'],
		];
		if ($client_id !== '') {
			$meta_query[] = ['key' => cmx_emails_meta_key('account_id'), 'value' => $client_id];
		}

		$ids = \get_posts([
			'post_type' => CMX_EMAILS_CPT,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'suppress_filters' => true,
			'meta_query' => $meta_query,
		]);

		$map = [];
		foreach ((array) $ids as $post_id) {
			$email = \sanitize_email((string) \get_post_meta((int) $post_id, cmx_emails_meta_key('sender_email'), true));
			if ($email !== '') {
				$map[\strtolower($email)] = true;
			}
		}

		$cache[$cache_key] = $map;
		return $map;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_sender_is_blocked_by_spam')) {
	function cmx_emails_sender_is_blocked_by_spam(string $client_id, string $sender_email): bool {
		$sender_email = \sanitize_email($sender_email);
		if ($sender_email === '') {
			return false;
		}

		$map = cmx_emails_spam_sender_email_map($client_id);
		return isset($map[\strtolower($sender_email)]);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_existing_recheckable_post_ids')) {
	function cmx_emails_existing_recheckable_post_ids(string $client_id, int $limit = 0): array {
		$client_id = \sanitize_key($client_id);
		if ($client_id === '') {
			return [];
		}

		$limit = cmx_emails_message_limit($limit);
		$ids = \get_posts([
			'post_type'        => CMX_EMAILS_CPT,
			'post_status'      => 'publish',
			'posts_per_page'   => $limit,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_key'         => cmx_emails_meta_key('received_ts'),
			'orderby'          => 'meta_value_num',
			'order'            => 'DESC',
				'meta_query'       => [
					['key' => cmx_emails_meta_key('account_id'), 'value' => $client_id],
				],
			]);

		$allowed = [];
		foreach ((array) $ids as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0) {
				continue;
			}

			$folder = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('folder'), true));
			$direction = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('direction'), true));
			if ($direction === 'outgoing' || \in_array($folder, ['sent', 'drafts', 'spam'], true)) {
				continue;
			}

			$allowed[] = $post_id;
		}

		return $allowed;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_existing_post_spam_analysis')) {
	function cmx_emails_existing_post_spam_analysis(int $post_id): array {
		$post_id = (int) $post_id;
		if ($post_id <= 0) {
			return [];
		}
		if (cmx_emails_is_manual_ham_post($post_id)) {
			return [
				'status' => 'clean',
				'score' => 0,
				'reasons' => [],
			];
		}

		$sender_email = \sanitize_email((string) \get_post_meta($post_id, cmx_emails_meta_key('sender_email'), true));
		$sender_label = \sanitize_text_field((string) \get_post_meta($post_id, cmx_emails_meta_key('sender_label'), true));
		$from = \trim($sender_label . ($sender_email !== '' ? ' <' . $sender_email . '>' : ''));
		if ($from === '') {
			$from = $sender_email;
		}

		return cmx_emails_analyze_message_spam([
			'subject' => (string) \get_post_meta($post_id, cmx_emails_meta_key('subject'), true),
			'from' => $from,
			'headers_raw' => (string) \get_post_meta($post_id, cmx_emails_meta_key('headers_raw'), true),
			'body_text' => (string) \get_post_meta($post_id, cmx_emails_meta_key('body_plain'), true),
			'body_html' => (string) \get_post_meta($post_id, cmx_emails_meta_key('body_html'), true),
		]);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_recheck_existing_posts_for_spam')) {
	function cmx_emails_recheck_existing_posts_for_spam(array $client, int $limit = 0): int {
		$account_id = \sanitize_key((string) ($client['id'] ?? ''));
		if ($account_id === '') {
			return 0;
		}

		$moved = 0;
		foreach (cmx_emails_existing_recheckable_post_ids($account_id, $limit) as $post_id) {
			$analysis = cmx_emails_existing_post_spam_analysis($post_id);
			$spam_status = \sanitize_key((string) ($analysis['status'] ?? ''));
			if ($spam_status !== 'spam') {
				continue;
			}

			cmx_emails_mark_post_as_spam($post_id);
			if (cmx_emails_move_existing_post_to_spam($post_id, $client)) {
				$moved++;
			} else {
				$moved++;
			}
		}

		return $moved;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_imap_root_from_mailbox')) {
	function cmx_emails_imap_root_from_mailbox(string $mailbox): string {
		if (\preg_match('/^\{[^}]+\}/', $mailbox, $match)) {
			return (string) ($match[0] ?? '');
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_imap_short_mailbox_name')) {
	function cmx_emails_imap_short_mailbox_name(string $mailbox): string {
		$root = cmx_emails_imap_root_from_mailbox($mailbox);
		if ($root === '' || !\str_starts_with($mailbox, $root)) {
			return $mailbox;
		}

		return (string) \substr($mailbox, \strlen($root));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_imap_delimiter')) {
	function cmx_emails_imap_delimiter($mailbox_info): string {
		$delimiter = '';
		if (\is_object($mailbox_info) && isset($mailbox_info->delimiter)) {
			$delimiter = (string) $mailbox_info->delimiter;
		}
		if ($delimiter === '' || $delimiter === 'NIL') {
			$delimiter = '.';
		}

		return $delimiter;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_imap_mailboxes')) {
	function cmx_emails_imap_mailboxes($imap, string $root): array {
		$list = [];
		$mailboxes = @\imap_getmailboxes($imap, $root, '*');
		if (!\is_array($mailboxes)) {
			return [];
		}

		foreach ($mailboxes as $mailbox_info) {
			if (!\is_object($mailbox_info) || !isset($mailbox_info->name)) {
				continue;
			}

			$full_name = (string) $mailbox_info->name;
			$list[] = [
				'name'      => $full_name,
				'short'     => cmx_emails_imap_short_mailbox_name($full_name),
				'delimiter' => cmx_emails_imap_delimiter($mailbox_info),
			];
		}

		return $list;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_imap_ensure_mailbox')) {
	function cmx_emails_imap_ensure_mailbox($imap, string $full_mailbox): bool {
		$full_mailbox = \trim($full_mailbox);
		if ($full_mailbox === '') {
			return false;
		}

		if (!\function_exists('imap_createmailbox') || !\function_exists('imap_utf7_encode')) {
			return false;
		}

		$encoded = \imap_utf7_encode($full_mailbox);
		if (!\is_string($encoded) || $encoded === '') {
			return false;
		}

		return @\imap_createmailbox($imap, $encoded);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_imap_archive_target')) {
	function cmx_emails_imap_archive_target($imap, string $current_mailbox, int $timestamp): array {
		$root = cmx_emails_imap_root_from_mailbox($current_mailbox);
		$period = cmx_emails_archive_period_from_timestamp($timestamp);
		$year = (string) ($period['year'] ?? '');
		$month = (string) ($period['month'] ?? '');
		if ($root === '' || $year === '' || $month === '') {
			return ['ok' => false];
		}

		$mailboxes = cmx_emails_imap_mailboxes($imap, $root);
		$archive_root_short = '';
		$delimiter = '.';
		$archive_candidates = (array) (cmx_emails_folder_map()['archive']['candidates'] ?? ['Archive']);

		foreach ($mailboxes as $mailbox_info) {
			$short_name = (string) ($mailbox_info['short'] ?? '');
			$current_delimiter = (string) ($mailbox_info['delimiter'] ?? '.');
			foreach ($archive_candidates as $candidate) {
				$candidate = (string) $candidate;
				if ($candidate === '') {
					continue;
				}
				if (\strcasecmp($short_name, $candidate) === 0 || \str_ends_with(\strtolower($short_name), \strtolower($candidate))) {
					$archive_root_short = $short_name;
					$delimiter = $current_delimiter !== '' ? $current_delimiter : '.';
					break 2;
				}
			}
		}

		if ($archive_root_short === '') {
			$archive_root_short = 'Archive';
			foreach ($mailboxes as $mailbox_info) {
				$current_delimiter = (string) ($mailbox_info['delimiter'] ?? '');
				if ($current_delimiter !== '') {
					$delimiter = $current_delimiter;
					break;
				}
			}
			$archive_root_full = $root . $archive_root_short;
			if (!cmx_emails_imap_ensure_mailbox($imap, $archive_root_full)) {
				return ['ok' => false];
			}
		}

		$year_short = $archive_root_short . $delimiter . $year;
		$month_short = $year_short . $delimiter . $month;
		$year_full = $root . $year_short;
		$month_full = $root . $month_short;

		if (!cmx_emails_imap_ensure_mailbox($imap, $year_full) && !\in_array($year_full, \array_column($mailboxes, 'name'), true)) {
			return ['ok' => false];
		}
		if (!cmx_emails_imap_ensure_mailbox($imap, $month_full) && !\in_array($month_full, \array_column($mailboxes, 'name'), true)) {
			return ['ok' => false];
		}

		return [
			'ok'          => true,
			'year'        => $year,
			'month'       => $month,
			'target'      => $month_short,
			'full_mailbox'=> $month_full,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_archive_inbox_message')) {
	function cmx_emails_archive_inbox_message($imap, int $uid, string $current_mailbox, int $timestamp): array {
		if ($uid <= 0 || $timestamp <= 0 || !\function_exists('imap_mail_move')) {
			return ['moved' => false];
		}

		$target = cmx_emails_imap_archive_target($imap, $current_mailbox, $timestamp);
		if (empty($target['ok']) || (string) ($target['target'] ?? '') === '') {
			return ['moved' => false];
		}

		$flags = \defined('CP_UID') ? (int) \constant('CP_UID') : 0;
		$moved = @\imap_mail_move($imap, (string) $uid, (string) $target['target'], $flags);
		if (!$moved) {
			return ['moved' => false];
		}

		return [
			'moved'        => true,
			'year'         => (string) ($target['year'] ?? ''),
			'month'        => (string) ($target['month'] ?? ''),
			'full_mailbox' => (string) ($target['full_mailbox'] ?? ''),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_imap_spam_target')) {
	function cmx_emails_imap_spam_target($imap, string $current_mailbox): array {
		$root = cmx_emails_imap_root_from_mailbox($current_mailbox);
		if ($root === '') {
			return ['ok' => false];
		}

		$mailboxes = cmx_emails_imap_mailboxes($imap, $root);
		$target_short = '';
		$delimiter = '.';
		$spam_candidates = (array) (cmx_emails_folder_map()['spam']['candidates'] ?? ['Spam', 'Junk']);

		foreach ($mailboxes as $mailbox_info) {
			$short_name = (string) ($mailbox_info['short'] ?? '');
			$current_delimiter = (string) ($mailbox_info['delimiter'] ?? '.');
			foreach ($spam_candidates as $candidate) {
				$candidate = (string) $candidate;
				if ($candidate === '') {
					continue;
				}
				if (\strcasecmp($short_name, $candidate) === 0 || \str_ends_with(\strtolower($short_name), \strtolower($candidate))) {
					$target_short = $short_name;
					$delimiter = $current_delimiter !== '' ? $current_delimiter : '.';
					break 2;
				}
			}
		}

		if ($target_short === '') {
			foreach ($mailboxes as $mailbox_info) {
				$current_delimiter = (string) ($mailbox_info['delimiter'] ?? '');
				if ($current_delimiter !== '') {
					$delimiter = $current_delimiter;
					break;
				}
			}

			$target_short = 'Spam';
			$target_full = $root . $target_short;
			if (!cmx_emails_imap_ensure_mailbox($imap, $target_full) && !\in_array($target_full, \array_column($mailboxes, 'name'), true)) {
				return ['ok' => false];
			}
		}

		return [
			'ok' => true,
			'target' => $target_short,
			'full_mailbox' => $root . $target_short,
			'delimiter' => $delimiter,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_imap_inbox_target')) {
	function cmx_emails_imap_inbox_target($imap, string $current_mailbox): array {
		$root = cmx_emails_imap_root_from_mailbox($current_mailbox);
		if ($root === '') {
			return ['ok' => false];
		}

		return [
			'ok' => true,
			'target' => 'INBOX',
			'full_mailbox' => $root . 'INBOX',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_move_inbox_message_to_spam')) {
	function cmx_emails_move_inbox_message_to_spam($imap, int $uid, string $current_mailbox): array {
		if ($uid <= 0 || $current_mailbox === '' || !\function_exists('imap_mail_move')) {
			return ['moved' => false];
		}

		$target = cmx_emails_imap_spam_target($imap, $current_mailbox);
		if (empty($target['ok']) || (string) ($target['target'] ?? '') === '') {
			return ['moved' => false];
		}

		$flags = \defined('CP_UID') ? (int) \constant('CP_UID') : 0;
		$moved = @\imap_mail_move($imap, (string) $uid, (string) $target['target'], $flags);
		if (!$moved) {
			return ['moved' => false];
		}

		return [
			'moved' => true,
			'target' => (string) ($target['target'] ?? ''),
			'full_mailbox' => (string) ($target['full_mailbox'] ?? ''),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_move_message_to_inbox')) {
	function cmx_emails_move_message_to_inbox($imap, int $uid, string $current_mailbox): array {
		if ($uid <= 0 || $current_mailbox === '' || !\function_exists('imap_mail_move')) {
			return ['moved' => false];
		}

		$target = cmx_emails_imap_inbox_target($imap, $current_mailbox);
		if (empty($target['ok']) || (string) ($target['target'] ?? '') === '') {
			return ['moved' => false];
		}

		$flags = \defined('CP_UID') ? (int) \constant('CP_UID') : 0;
		$moved = @\imap_mail_move($imap, (string) $uid, (string) $target['target'], $flags);
		if (!$moved) {
			return ['moved' => false];
		}

		return [
			'moved' => true,
			'target' => (string) ($target['target'] ?? ''),
			'full_mailbox' => (string) ($target['full_mailbox'] ?? ''),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_stale_inbox_message_numbers')) {
	function cmx_emails_stale_inbox_message_numbers($imap): array {
		$cutoff = cmx_emails_archive_cutoff_timestamp();
		if ($cutoff <= 0) {
			return [];
		}

		$messages = [];
		if (\function_exists('imap_search')) {
			$criteria = 'BEFORE "' . \gmdate('d-M-Y', $cutoff) . '"';
			$results = @\imap_search($imap, $criteria);
			if (\is_array($results)) {
				foreach ($results as $msg_no) {
					$msg_no = (int) $msg_no;
					if ($msg_no > 0) {
						$messages[$msg_no] = $msg_no;
					}
				}
			}
		}

		if ($messages === []) {
			$total = (int) \imap_num_msg($imap);
			for ($msg_no = 1; $msg_no <= $total; $msg_no++) {
				$overview_list = @\imap_fetch_overview($imap, (string) $msg_no, 0);
				$overview = (\is_array($overview_list) && isset($overview_list[0]) && \is_object($overview_list[0])) ? $overview_list[0] : null;
				$timestamp = cmx_emails_parse_message_timestamp($overview, null);
				if ($timestamp > 0 && $timestamp < $cutoff) {
					$messages[$msg_no] = $msg_no;
				}
			}
		}

		if ($messages === []) {
			return [];
		}

		\krsort($messages, \SORT_NUMERIC);
		return \array_values($messages);
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_normalize_post_id_list')) {
	function cmx_emails_normalize_post_id_list($raw, array $allowed_post_types = []): array {
		if (\is_string($raw)) {
			$raw = \trim($raw);
			if ($raw !== '' && (\str_starts_with($raw, '[') || \str_starts_with($raw, 'a:'))) {
				$decoded = \maybe_unserialize($raw);
				if ($decoded !== $raw) {
					$raw = $decoded;
				}
			}
		}

		if (!\is_array($raw)) {
			$raw = $raw === null || $raw === '' ? [] : [$raw];
		}

		$allowed_post_types = \array_values(\array_unique(\array_filter(\array_map('strval', $allowed_post_types))));
		$ids = [];
		foreach ($raw as $value) {
			$id = (int) $value;
			if ($id <= 0) {
				continue;
			}
			if ($allowed_post_types !== []) {
				$post_type = (string) \get_post_type($id);
				if ($post_type === '' || !\in_array($post_type, $allowed_post_types, true)) {
					continue;
				}
			}
			$ids[$id] = $id;
		}

		return \array_values($ids);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_primary_assignment_id')) {
	function cmx_emails_primary_assignment_id(array $ids): int {
		return isset($ids[0]) ? (int) $ids[0] : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_assignment_contact_ids')) {
	function cmx_emails_assignment_contact_ids(int $post_id): array {
		$ids = cmx_emails_normalize_post_id_list(
			\get_post_meta($post_id, cmx_emails_meta_key('contact_ids'), true),
			cmx_emails_contact_post_types()
		);
		if ($ids !== []) {
			return $ids;
		}

		return cmx_emails_normalize_post_id_list(
			[\get_post_meta($post_id, cmx_emails_meta_key('contact_id'), true)],
			cmx_emails_contact_post_types()
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_assignment_project_ids')) {
	function cmx_emails_assignment_project_ids(int $post_id): array {
		$ids = cmx_emails_normalize_post_id_list(
			\get_post_meta($post_id, cmx_emails_meta_key('project_ids'), true),
			cmx_emails_project_post_types()
		);
		if ($ids !== []) {
			return $ids;
		}

		return cmx_emails_normalize_post_id_list(
			[\get_post_meta($post_id, cmx_emails_meta_key('project_id'), true)],
			cmx_emails_project_post_types()
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_assignment_subject_label')) {
	function cmx_emails_assignment_subject_label(int $post_id): string {
		$subject = cmx_emails_subject_text((string) \get_post_meta($post_id, cmx_emails_meta_key('subject'), true));
		if ($subject !== '') {
			return $subject;
		}

		$title = cmx_emails_subject_text((string) \get_the_title($post_id));
		if ($title !== '') {
			return $title;
		}

		return \function_exists(__NAMESPACE__ . '\\cmx_emails_missing_subject_label')
			? cmx_emails_missing_subject_label()
			: 'Betreffzeile fehlt';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_assignment_link_html')) {
	function cmx_emails_assignment_link_html(int $post_id): string {
		$label = \trim(cmx_emails_assignment_subject_label($post_id));
		if ($label === '') {
			return '';
		}

		$edit_url = \get_edit_post_link($post_id, '');
		if (!\is_string($edit_url) || $edit_url === '') {
			return \esc_html($label);
		}

		return '<a href="' . \esc_url($edit_url) . '">' . \esc_html($label) . '</a>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_note_link_html')) {
	function cmx_emails_note_link_html(int $post_id, string $label): string {
		$label = \trim($label);
		if ($label === '') {
			$label = 'E-Mail';
		}

		$edit_url = \get_edit_post_link($post_id, '');
		if (!\is_string($edit_url) || $edit_url === '') {
			return $label;
		}

		return '<a href="' . \esc_url($edit_url) . '" title="' . \esc_attr($label) . '" target="_blank" rel="noopener noreferrer">&#8203;</a>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_sent_link_html')) {
	function cmx_emails_sent_link_html(int $post_id): string {
		return cmx_emails_note_link_html($post_id, 'versendet');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_sent_body_note_html')) {
	function cmx_emails_load_body_content(int $post_id, bool $hydrate = true): array {
		$post_id = (int) $post_id;
		$email_post_type = \defined(__NAMESPACE__ . '\\CMX_EMAILS_CPT')
			? (string) \constant(__NAMESPACE__ . '\\CMX_EMAILS_CPT')
			: 'emails';
		$post = $post_id > 0 ? \get_post($post_id) : null;
		$body_html = $post_id > 0 ? (string) \get_post_meta($post_id, cmx_emails_meta_key('body_html'), true) : '';
		$body_plain = $post_id > 0 ? (string) \get_post_meta($post_id, cmx_emails_meta_key('body_plain'), true) : '';
		$content = $post instanceof \WP_Post ? (string) $post->post_content : '';

		if (
			$hydrate
			&& $post instanceof \WP_Post
			&& (string) $post->post_type === $email_post_type
			&& \trim($body_html) === ''
			&& \trim($body_plain) === ''
			&& \trim($content) === ''
			&& \function_exists(__NAMESPACE__ . '\\cmx_emails_hydrate_post_body_from_imap')
		) {
			cmx_emails_hydrate_post_body_from_imap($post_id);
			$post = \get_post($post_id);
			$body_html = (string) \get_post_meta($post_id, cmx_emails_meta_key('body_html'), true);
			$body_plain = (string) \get_post_meta($post_id, cmx_emails_meta_key('body_plain'), true);
			$content = $post instanceof \WP_Post ? (string) $post->post_content : '';
		}

		return [
			'html' => $body_html,
			'plain' => $body_plain,
			'content' => $content,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_hydrate_post_body_from_imap')) {
	function cmx_emails_hydrate_post_body_from_imap(int $post_id): bool {
		$post_id = (int) $post_id;
		$email_post_type = \defined(__NAMESPACE__ . '\\CMX_EMAILS_CPT')
			? (string) \constant(__NAMESPACE__ . '\\CMX_EMAILS_CPT')
			: 'emails';
		if ($post_id <= 0 || !\function_exists('imap_fetchstructure')) {
			return false;
		}

		$post = \get_post($post_id);
		if (!$post instanceof \WP_Post || (string) $post->post_type !== $email_post_type) {
			return false;
		}

		$account_id = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('account_id'), true));
		$uid = (int) \get_post_meta($post_id, cmx_emails_meta_key('uid'), true);
		$folder = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('folder'), true));
		$mailbox = \trim((string) \get_post_meta($post_id, cmx_emails_meta_key('mailbox'), true));
		$archive_year = \preg_replace('/[^0-9]/', '', (string) \get_post_meta($post_id, cmx_emails_meta_key('archive_year'), true));
		$archive_month = cmx_emails_normalize_archive_month((string) \get_post_meta($post_id, cmx_emails_meta_key('archive_month'), true));
		if ($account_id === '' || $uid <= 0) {
			return false;
		}

		$client = \function_exists(__NAMESPACE__ . '\\cmx_emails_get_client')
			? (array) cmx_emails_get_client($account_id)
			: [];
		if ($client === []) {
			return false;
		}

		$resolved_mailbox = '';
		if ($mailbox !== '' && \function_exists(__NAMESPACE__ . '\\cmx_emails_open_client_mailbox')) {
			$imap = cmx_emails_open_client_mailbox($client, $mailbox, $resolved_mailbox);
		} elseif ($folder === 'archive' && \strlen($archive_year) === 4 && $archive_month !== '' && \function_exists(__NAMESPACE__ . '\\cmx_emails_open_client_archive_mailbox')) {
			$imap = cmx_emails_open_client_archive_mailbox($client, $archive_year, $archive_month, $resolved_mailbox);
		} elseif (\function_exists(__NAMESPACE__ . '\\cmx_emails_open_client_folder')) {
			$imap = cmx_emails_open_client_folder($client, $folder !== '' ? $folder : 'inbox', $resolved_mailbox);
		} else {
			$imap = false;
		}

		if ($imap === false) {
			return false;
		}

		$msg_no = \function_exists('imap_msgno') ? (int) \imap_msgno($imap, $uid) : 0;
		if ($msg_no <= 0) {
			@\imap_close($imap);
			return false;
		}

		$payload = cmx_emails_fetch_message_payload($imap, $msg_no);
		@\imap_close($imap);

		$plain = \trim((string) ($payload['plain'] ?? ''));
		$html = (string) ($payload['html'] ?? '');
		$content = $plain !== '' ? $plain : \trim(\wp_strip_all_tags($html));
		if ($plain === '' && $html === '' && $content === '') {
			return false;
		}

		\update_post_meta($post_id, cmx_emails_meta_key('body_plain'), $plain);
		\update_post_meta($post_id, cmx_emails_meta_key('body_html'), $html);

		$excerpt = cmx_emails_text_excerpt($content !== '' ? $content : (string) \get_post_meta($post_id, cmx_emails_meta_key('subject'), true));
		\wp_update_post([
			'ID' => $post_id,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
		]);

		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_sent_body_note_html')) {
	function cmx_emails_sent_body_note_html(int $post_id): string {
		$body = cmx_emails_load_body_content($post_id);
		$body_html = (string) ($body['html'] ?? '');
		if ($body_html !== '') {
			return \trim((string) \wp_kses_post($body_html));
		}

		$body_plain = (string) ($body['plain'] ?? '');
		if ($body_plain === '') {
			$body_plain = (string) ($body['content'] ?? '');
		}
		$body_plain = \trim($body_plain);
		return $body_plain !== '' ? \nl2br(\esc_html($body_plain)) : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_note_subject_html')) {
	function cmx_emails_note_subject_html(int $post_id): string {
		$subject = cmx_emails_subject_text((string) \get_post_meta($post_id, cmx_emails_meta_key('subject'), true));
		if ($subject === '') {
			$subject = cmx_emails_subject_text((string) \get_the_title($post_id));
		}

		$subject = \trim(\sanitize_text_field($subject));
		if ($subject === '') {
			return '';
		}

		return '<strong>' . \esc_html($subject) . '</strong>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_assignment_note_post_type')) {
	function cmx_emails_assignment_note_post_type(int $post_id, string $kind): string {
		$post_type = (string) \get_post_type($post_id);
		$supported = \function_exists(__NAMESPACE__ . '\\cmx_notizen_supported_post_types')
			? cmx_notizen_supported_post_types()
			: [];

		if ($post_type !== '' && \in_array($post_type, $supported, true)) {
			return $post_type;
		}

		return $kind === 'project' ? 'projekte' : 'kontakte';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_ignored_contact_match_emails')) {
	function cmx_emails_ignored_contact_match_emails(): array {
		$ignored = [];
		if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_client_list')) {
			return $ignored;
		}

		foreach ((array) cmx_emails_client_list() as $client) {
			$client = (array) $client;
			$email = \sanitize_email((string) ($client['email'] ?? ''));
			if (!\is_email($email)) {
				continue;
			}
			$ignored[\strtolower($email)] = $email;
		}

		return $ignored;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_matchable_email')) {
	function cmx_emails_matchable_email($value): string {
		$candidates = [];

		if (\is_array($value)) {
			foreach (['email', 'address', 'value', 'label'] as $key) {
				if (isset($value[$key]) && !\is_array($value[$key]) && !\is_object($value[$key])) {
					$candidates[] = (string) $value[$key];
				}
			}
		} elseif (\is_object($value)) {
			if (isset($value->mailbox, $value->host)) {
				$candidates[] = (string) $value->mailbox . '@' . (string) $value->host;
			}
			foreach (['email', 'address', 'value', 'label'] as $key) {
				if (isset($value->{$key}) && !\is_array($value->{$key}) && !\is_object($value->{$key})) {
					$candidates[] = (string) $value->{$key};
				}
			}
		} elseif (!\is_array($value) && !\is_object($value)) {
			$candidates[] = (string) $value;
		}

		foreach ($candidates as $candidate) {
			$candidate = \trim($candidate);
			if ($candidate === '') {
				continue;
			}

			$email = \sanitize_email($candidate);
			if (\is_email($email)) {
				return $email;
			}

			if (\preg_match('/([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', $candidate, $match) === 1) {
				$email = \sanitize_email((string) ($match[1] ?? ''));
				if (\is_email($email)) {
					return $email;
				}
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_collect_contact_emails')) {
	function cmx_emails_collect_contact_emails(int $contact_id): array {
		$emails = [];
		$add = null;
		$add = static function ($value) use (&$emails, &$add): void {
			if (\is_array($value)) {
				$email = cmx_emails_matchable_email($value);
				if ($email !== '') {
					$emails[\strtolower($email)] = $email;
				}

				foreach ($value as $item) {
					$add($item);
				}
				return;
			}

			if (\is_object($value)) {
				$email = cmx_emails_matchable_email($value);
				if ($email !== '') {
					$emails[\strtolower($email)] = $email;
				}
				return;
			}

			if (!\is_scalar($value)) {
				return;
			}

			$raw = \trim((string) $value);
			if ($raw === '') {
				return;
			}

			$email = cmx_emails_matchable_email($raw);
			if ($email !== '') {
				$emails[\strtolower($email)] = $email;
			}

			$items = \preg_split('/[,\s;<>]+/', $raw) ?: [];
			foreach ($items as $item) {
				$email = cmx_emails_matchable_email($item);
				if ($email !== '') {
					$emails[\strtolower($email)] = $email;
				}
			}
		};

		if (\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_read_contacts')) {
			foreach ((array) cmx_kommunikation_read_contacts($contact_id) as $row) {
				if (\is_array($row)) {
					$add($row['email'] ?? '');
				}
			}
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_collect_emails')) {
			foreach ((array) cmx_kommunikation_collect_emails($contact_id) as $email) {
				$add($email);
			}
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_email')) {
			$add(cmx_kommunikation_primary_email($contact_id));
		}

		foreach (['_cmx_kommunikation', 'cmx_kommunikation', 'kommunikation', 'cmx_kommunikation_data'] as $key) {
			$add(\get_post_meta($contact_id, $key, true));
		}

		$slots = \max(10, (int) \get_post_meta($contact_id, '_cmx_kommunikation_count', true));
		for ($slot = 1; $slot <= $slots; $slot++) {
			$add(\get_post_meta($contact_id, '_cmx_kommunikation_' . $slot . '_email', true));
			$add(\get_post_meta($contact_id, '_cmx_email_' . $slot, true));
		}

		$fallback_keys = (array) \apply_filters('cmx_kontakte_email1_meta_keys', [
			'cmx_email_1',
			'email_1',
			'e_mail_1',
			'kontakt_email',
			'email',
			'e_mail',
			'mail',
		]);
		foreach ($fallback_keys as $key) {
			$key = \trim((string) $key);
			if ($key !== '') {
				$add(\get_post_meta($contact_id, $key, true));
			}
		}

		return \array_values($emails);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_find_contact_ids_by_emails')) {
	function cmx_emails_find_contact_ids_by_emails(array $emails): array {
		$ignored_emails = cmx_emails_ignored_contact_match_emails();
		$normalized_emails = [];
		foreach ($emails as $email) {
			$email = cmx_emails_matchable_email($email);
			if ($email === '') {
				continue;
			}
			$key = \strtolower($email);
			if (isset($ignored_emails[$key])) {
				continue;
			}
			$normalized_emails[$key] = $email;
		}
		if ($normalized_emails === []) {
			return [];
		}

		$post_types = cmx_emails_contact_post_types();
		if ($post_types === []) {
			return [];
		}

		$scan_limit = \defined(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_MAX_CONTACTS_SCAN')
			? (int) \constant(__NAMESPACE__ . '\\CMX_MAIL_IMPORT_MAX_CONTACTS_SCAN')
			: 5000;
		if ($scan_limit <= 0) {
			$scan_limit = 5000;
		}

		$contact_ids = [];
		$contacts = \get_posts([
			'post_type'        => $post_types,
			'post_status'      => ['publish', 'private'],
			'posts_per_page'   => $scan_limit,
			'fields'           => 'ids',
			'orderby'          => 'modified',
			'order'            => 'DESC',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		]);

		foreach ((array) $contacts as $contact_id) {
			$contact_id = (int) $contact_id;
			if ($contact_id <= 0) {
				continue;
			}

			$contact_emails = cmx_emails_collect_contact_emails($contact_id);
			if (\function_exists(__NAMESPACE__ . '\\cmx_mail_import_collect_contact_emails')) {
				$contact_emails = \array_merge($contact_emails, (array) cmx_mail_import_collect_contact_emails($contact_id));
			}
			if ($contact_emails === []) {
				continue;
			}

			foreach ($contact_emails as $contact_email) {
				$key = \strtolower(cmx_emails_matchable_email($contact_email));
				if ($key !== '' && isset($normalized_emails[$key])) {
					$contact_ids[$contact_id] = $contact_id;
					break;
				}
			}
		}

		return \array_values($contact_ids);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_auto_contact_ids_for_message')) {
	function cmx_emails_auto_contact_ids_for_message(string $sender_email = '', array $to = [], array $cc = [], array $bcc = []): array {
		if ($sender_email === '') {
			return [];
		}

		return cmx_emails_find_contact_ids_by_emails([$sender_email]);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_auto_assign_existing_posts')) {
	function cmx_emails_auto_assign_existing_posts(string $client_id, string $folder = '', int $limit = 0): int {
		$client_id = \sanitize_key($client_id);
		if ($client_id === '') {
			return 0;
		}

		$folder = \sanitize_key($folder);
		$limit = cmx_emails_message_limit($limit);
		$meta_query = [
			['key' => cmx_emails_meta_key('account_id'), 'value' => $client_id],
		];
		if ($folder !== '') {
			$meta_query[] = ['key' => cmx_emails_meta_key('folder'), 'value' => $folder];
		}

		$post_ids = \get_posts([
			'post_type'        => CMX_EMAILS_CPT,
			'post_status'      => 'publish',
			'posts_per_page'   => $limit,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_key'         => cmx_emails_meta_key('received_ts'),
			'orderby'          => 'meta_value_num',
			'order'            => 'DESC',
			'meta_query'       => $meta_query,
		]);

		$assigned = 0;
		foreach ((array) $post_ids as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0) {
				continue;
			}
			if (\get_post_meta($post_id, cmx_emails_meta_key('assignment_manual'), true) === '1') {
				continue;
			}
			if (cmx_emails_assignment_contact_ids($post_id) !== []) {
				continue;
			}

			$sender_email = \sanitize_email((string) \get_post_meta($post_id, cmx_emails_meta_key('sender_email'), true));
			$contact_ids = cmx_emails_auto_contact_ids_for_message($sender_email);
			if ($contact_ids === []) {
				continue;
			}

			cmx_emails_save_assignments($post_id, $contact_ids, cmx_emails_assignment_project_ids($post_id), false, false);
			\update_post_meta($post_id, cmx_emails_meta_key('contact_id_auto'), (string) \max(0, cmx_emails_primary_assignment_id($contact_ids)));
			$assigned++;
		}

		return $assigned;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_append_internal_note')) {
	function cmx_emails_append_internal_note(int $target_id, string $target_post_type, string $text): void {
		if ($target_id <= 0 || $target_post_type === '') {
			return;
		}

		$text = \trim($text);
		if ($text === '') {
			return;
		}

		$meta_key = cmx_notizen_meta_key_for_post_type($target_post_type);
		$rows = cmx_notizen_load_rows($target_id, $target_post_type);
		$rows[] = [
			'betreff' => 'E-Mail',
			'datum'   => cmx_notizen_now_date(),
			'zeit'    => cmx_notizen_now_time(),
			'text'    => $text,
		];
		\update_post_meta($target_id, $meta_key, $rows);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_append_sent_recipient_notes')) {
	function cmx_emails_append_sent_recipient_notes(int $post_id, array $to, array $cc = [], array $bcc = []): void {
		$contact_ids = cmx_emails_find_contact_ids_by_emails(\array_merge($to, $cc, $bcc));
		if ($contact_ids === []) {
			return;
		}

		$text = cmx_emails_sent_link_html($post_id);
		$subject = cmx_emails_note_subject_html($post_id);
		if ($subject !== '') {
			$text .= $subject;
		}
		$body = cmx_emails_sent_body_note_html($post_id);
		if ($body !== '') {
			$text .= ($subject !== '' ? '<br>' : '') . $body;
		}

		foreach ($contact_ids as $contact_id) {
			$contact_id = (int) $contact_id;
			if ($contact_id <= 0) {
				continue;
			}
			cmx_emails_append_internal_note($contact_id, cmx_emails_assignment_note_post_type($contact_id, 'contact'), $text);
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_append_received_sender_notes')) {
	function cmx_emails_append_received_sender_notes(int $post_id, string $sender_email): void {
		$contact_ids = cmx_emails_find_contact_ids_by_emails([$sender_email]);
		if ($contact_ids === []) {
			return;
		}

		$text = cmx_emails_note_link_html($post_id, 'eingegangen');
		$subject = cmx_emails_note_subject_html($post_id);
		if ($subject !== '') {
			$text .= $subject;
		}
		$body = cmx_emails_sent_body_note_html($post_id);
		if ($body !== '') {
			$text .= ($subject !== '' ? '<br>' : '') . $body;
		}

		foreach ($contact_ids as $contact_id) {
			$contact_id = (int) $contact_id;
			if ($contact_id <= 0) {
				continue;
			}
			cmx_emails_append_internal_note($contact_id, cmx_emails_assignment_note_post_type($contact_id, 'contact'), $text);
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_append_assignment_notes')) {
	function cmx_emails_append_assignment_notes(int $post_id, array $contact_ids, array $project_ids): void {
		$link_html = cmx_emails_assignment_link_html($post_id);
		if ($link_html === '') {
			return;
		}

		foreach ($contact_ids as $contact_id) {
			$contact_id = (int) $contact_id;
			if ($contact_id <= 0) {
				continue;
			}
			cmx_emails_append_internal_note($contact_id, cmx_emails_assignment_note_post_type($contact_id, 'contact'), $link_html);
		}

		$project_contact_meta = \defined(__NAMESPACE__ . '\\CMX_KONTAKT_META')
			? (string) CMX_KONTAKT_META
			: '_cmx_projekt_kontakt_id';

		foreach ($project_ids as $project_id) {
			$project_id = (int) $project_id;
			if ($project_id <= 0) {
				continue;
			}

			cmx_emails_append_internal_note($project_id, cmx_emails_assignment_note_post_type($project_id, 'project'), $link_html);

			$project_contact_id = (int) \get_post_meta($project_id, $project_contact_meta, true);
			if ($project_contact_id > 0) {
				cmx_emails_append_internal_note($project_contact_id, cmx_emails_assignment_note_post_type($project_contact_id, 'contact'), $link_html);
			}
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_save_assignments')) {
	function cmx_emails_save_assignments(int $post_id, array $contact_ids, array $project_ids, bool $manual = true, bool $write_notes = true): void {
		$contact_ids = cmx_emails_normalize_post_id_list($contact_ids, cmx_emails_contact_post_types());
		$project_ids = cmx_emails_normalize_post_id_list($project_ids, cmx_emails_project_post_types());

		if ($contact_ids === []) {
			\delete_post_meta($post_id, cmx_emails_meta_key('contact_ids'));
		} else {
			\update_post_meta($post_id, cmx_emails_meta_key('contact_ids'), $contact_ids);
		}

		if ($project_ids === []) {
			\delete_post_meta($post_id, cmx_emails_meta_key('project_ids'));
		} else {
			\update_post_meta($post_id, cmx_emails_meta_key('project_ids'), $project_ids);
		}

		\update_post_meta($post_id, cmx_emails_meta_key('contact_id'), (string) cmx_emails_primary_assignment_id($contact_ids));
		\update_post_meta($post_id, cmx_emails_meta_key('project_id'), (string) cmx_emails_primary_assignment_id($project_ids));

		if ($manual) {
			\update_post_meta($post_id, cmx_emails_meta_key('assignment_manual'), '1');
		} else {
			\delete_post_meta($post_id, cmx_emails_meta_key('assignment_manual'));
		}

		cmx_emails_update_assignment_cache($post_id);

		if ($write_notes) {
			cmx_emails_append_assignment_notes($post_id, $contact_ids, $project_ids);
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_assignment_label')) {
	function cmx_emails_assignment_label(int $post_id): string {
		$contact_ids = cmx_emails_assignment_contact_ids($post_id);
		$project_ids = cmx_emails_assignment_project_ids($post_id);
		$parts = [];

		$contact_titles = [];
		foreach ($contact_ids as $contact_id) {
			$contact_id = (int) $contact_id;
			if ($contact_id <= 0 || \get_post_status($contact_id) === false) {
				continue;
			}
			$contact_titles[] = (string) \get_the_title($contact_id);
		}
		if ($contact_titles !== []) {
			$parts[] = 'Kunde: ' . \implode(', ', $contact_titles);
		}

		$project_titles = [];
		foreach ($project_ids as $project_id) {
			$project_id = (int) $project_id;
			if ($project_id <= 0 || \get_post_status($project_id) === false) {
				continue;
			}
			$project_titles[] = (string) \get_the_title($project_id);
		}
		if ($project_titles !== []) {
			$parts[] = 'Projekt: ' . \implode(', ', $project_titles);
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
	function cmx_emails_sync_single_message($imap, array $client, string $folder, int $msg_no, string $mailbox = '', array $context = []): array {
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
		$message_id = \sanitize_text_field((string) ($header->message_id ?? ''));
		$account_id = \sanitize_key((string) ($client['id'] ?? ''));
		$logical_folder = \sanitize_key($folder);
		$logical_mailbox = $mailbox;
		$archive_year = \preg_replace('/[^0-9]/', '', (string) ($context['archive_year'] ?? ''));
		$archive_month = cmx_emails_normalize_archive_month((string) ($context['archive_month'] ?? ''));
		$archived_message = false;
		$sender_email = \sanitize_email((string) ($sender['email'] ?? ''));

		$existing_id = cmx_emails_find_post_id($account_id, $logical_folder, $uid, $logical_mailbox, $archive_year, $archive_month, true);
		if ($existing_id <= 0 && $message_id !== '') {
			$existing_id = cmx_emails_find_post_id_by_message_id($account_id, $message_id, true);
		}
		if ($existing_id > 0 && (string) \get_post_status($existing_id) === 'trash') {
			return ['post_id' => $existing_id, 'uid' => $uid, 'archived' => false, 'skipped' => true, 'trashed_existing' => true];
		}

		if ($logical_folder !== 'spam' && cmx_emails_sender_is_blocked_by_spam($account_id, $sender_email)) {
			return ['post_id' => 0, 'uid' => $uid, 'ignored_spam_sender' => true];
		}
		if ($existing_id > 0) {
			if ($logical_folder === 'inbox' && $ts > 0 && $ts < cmx_emails_archive_cutoff_timestamp()) {
				$archive_move = cmx_emails_archive_inbox_message($imap, $uid, $mailbox, $ts);
				if (!empty($archive_move['moved'])) {
					$logical_folder = 'archive';
					$logical_mailbox = (string) ($archive_move['full_mailbox'] ?? $mailbox);
					$archive_year = (string) ($archive_move['year'] ?? '');
					$archive_month = (string) ($archive_move['month'] ?? '');
					$archived_message = true;
				}
			}

			$imported_post_ids = (array) \get_post_meta($existing_id, cmx_emails_meta_key('imported_post_ids'), true);
			$read_at = (int) \get_post_meta($existing_id, cmx_emails_meta_key('read_at'), true);
			$manual_status = (string) \get_post_meta($existing_id, cmx_emails_meta_key('manual_status'), true);
			$status = cmx_emails_compute_status([
				'manual_status' => $manual_status,
				'imported_post_ids' => $imported_post_ids,
				'imap_seen' => !empty($overview->seen),
				'read_at' => $read_at,
			]);

			\update_post_meta($existing_id, cmx_emails_meta_key('account_id'), $account_id);
			\update_post_meta($existing_id, cmx_emails_meta_key('account_label'), cmx_emails_client_label($client));
			\update_post_meta($existing_id, cmx_emails_meta_key('account_email'), \sanitize_email((string) ($client['email'] ?? '')));
			\update_post_meta($existing_id, cmx_emails_meta_key('folder'), $logical_folder);
			\update_post_meta($existing_id, cmx_emails_meta_key('uid'), (string) $uid);
			\update_post_meta($existing_id, cmx_emails_meta_key('message_id'), $message_id);
			\update_post_meta($existing_id, cmx_emails_meta_key('subject'), $subject);
			\update_post_meta($existing_id, cmx_emails_meta_key('sender_name'), (string) ($sender['name'] ?? ''));
			\update_post_meta($existing_id, cmx_emails_meta_key('sender_email'), (string) ($sender['email'] ?? ''));
			\update_post_meta($existing_id, cmx_emails_meta_key('sender_label'), (string) ($sender['label'] ?? ''));
			\update_post_meta($existing_id, cmx_emails_meta_key('to'), $to);
			\update_post_meta($existing_id, cmx_emails_meta_key('cc'), $cc);
			\update_post_meta($existing_id, cmx_emails_meta_key('bcc'), $bcc);
			\update_post_meta($existing_id, cmx_emails_meta_key('received_ts'), (string) $ts);
			\update_post_meta($existing_id, cmx_emails_meta_key('imap_seen'), !empty($overview->seen) ? '1' : '0');
			\update_post_meta($existing_id, cmx_emails_meta_key('mailbox'), $logical_mailbox);
			\update_post_meta($existing_id, cmx_emails_meta_key('status'), $status);
			if ($logical_folder === 'archive' && $archive_year !== '' && $archive_month !== '') {
				\update_post_meta($existing_id, cmx_emails_meta_key('archive_year'), $archive_year);
				\update_post_meta($existing_id, cmx_emails_meta_key('archive_month'), $archive_month);
			} else {
				\delete_post_meta($existing_id, cmx_emails_meta_key('archive_year'));
				\delete_post_meta($existing_id, cmx_emails_meta_key('archive_month'));
			}
			cmx_emails_update_assignment_cache($existing_id);

			return [
				'post_id' => $existing_id,
				'uid' => $uid,
				'archived' => $archived_message,
				'spam_moved' => false,
				'skipped' => true,
			];
		}

		$payload = cmx_emails_fetch_message_payload($imap, $msg_no);
		$plain = \trim((string) ($payload['plain'] ?? ''));
		$html = (string) ($payload['html'] ?? '');
		$content = $plain !== '' ? $plain : \trim(\wp_strip_all_tags($html));
		$snippet = cmx_emails_text_excerpt($content !== '' ? $content : $subject);

		if ($logical_folder === 'inbox') {
			$spam_fingerprint = [
				'subject' => $subject,
				'sender_email' => (string) ($sender['email'] ?? ''),
				'received_ts' => $ts,
				'folders' => ['archive', 'inbox', 'spam'],
			];
			$manual_ham = cmx_emails_message_has_manual_ham_override($account_id, $uid, $message_id, $spam_fingerprint);
			if (!$manual_ham) {
				$spam = cmx_emails_analyze_message_spam([
					'subject' => $subject,
					'from' => (string) ($sender['label'] ?? ''),
					'headers_raw' => $raw_header,
					'body_text' => $plain,
					'body_html' => $html,
				]);
				$spam_status = \sanitize_key((string) ($spam['status'] ?? ''));
				if ($spam_status === 'spam') {
					$local_spam_post_id = cmx_emails_mark_existing_message_as_spam(
						$account_id,
						$uid,
						$message_id,
						'',
						$spam_fingerprint
					);
					$spam_move = cmx_emails_move_inbox_message_to_spam($imap, $uid, $mailbox);
					if (!empty($spam_move['moved'])) {
						$spam_post_id = cmx_emails_mark_existing_message_as_spam(
							$account_id,
							$uid,
							$message_id,
							(string) ($spam_move['full_mailbox'] ?? ''),
							$spam_fingerprint
						);

						return [
							'post_id' => $spam_post_id,
							'uid' => $uid,
							'archived' => false,
							'spam_moved' => true,
							'spam_status' => $spam_status,
							'spam_target' => (string) ($spam_move['target'] ?? ''),
						];
					}

					$logical_folder = 'spam';
					$logical_mailbox = $mailbox;
					if ($local_spam_post_id > 0) {
						return [
							'post_id' => $local_spam_post_id,
							'uid' => $uid,
							'archived' => false,
							'spam_moved' => true,
							'spam_status' => $spam_status,
							'spam_target' => '',
						];
					}
				}
			}
		}
		if ($logical_folder === 'inbox' && $ts > 0 && $ts < cmx_emails_archive_cutoff_timestamp()) {
			$archive_move = cmx_emails_archive_inbox_message($imap, $uid, $mailbox, $ts);
			if (!empty($archive_move['moved'])) {
				$logical_folder = 'archive';
				$logical_mailbox = (string) ($archive_move['full_mailbox'] ?? $mailbox);
				$archive_year = (string) ($archive_move['year'] ?? '');
				$archive_month = (string) ($archive_move['month'] ?? '');
				$archived_message = true;
			}
		}

		$existing_id = cmx_emails_find_post_id($account_id, $logical_folder, $uid, $logical_mailbox, $archive_year, $archive_month, true);
		if ($existing_id <= 0 && $message_id !== '') {
			$existing_id = cmx_emails_find_post_id_by_message_id($account_id, $message_id, true);
		}
		if ($existing_id > 0 && (string) \get_post_status($existing_id) === 'trash') {
			return ['post_id' => $existing_id, 'uid' => $uid, 'archived' => $archived_message, 'skipped' => true, 'trashed_existing' => true];
		}
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

		$current_contact_ids = cmx_emails_assignment_contact_ids($post_id);
		$current_project_ids = cmx_emails_assignment_project_ids($post_id);
		$assignment_manual = \get_post_meta($post_id, cmx_emails_meta_key('assignment_manual'), true) === '1';
		$auto_contact_ids = cmx_emails_auto_contact_ids_for_message((string) ($sender['email'] ?? ''), $to, $cc, $bcc);
		$contact_ids = $assignment_manual ? $current_contact_ids : ($auto_contact_ids !== [] ? $auto_contact_ids : $current_contact_ids);
		$project_ids = $current_project_ids;
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
		\update_post_meta($post_id, cmx_emails_meta_key('folder'), $logical_folder);
		\update_post_meta($post_id, cmx_emails_meta_key('uid'), (string) $uid);
		\update_post_meta($post_id, cmx_emails_meta_key('message_id'), $message_id);
		\update_post_meta($post_id, cmx_emails_meta_key('headers_raw'), $raw_header);
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
		\update_post_meta($post_id, cmx_emails_meta_key('mailbox'), $logical_mailbox);
		\update_post_meta($post_id, cmx_emails_meta_key('contact_id_auto'), (string) \max(0, cmx_emails_primary_assignment_id($auto_contact_ids)));
		\update_post_meta($post_id, cmx_emails_meta_key('status'), $status);
		if ($logical_folder === 'archive' && $archive_year !== '' && $archive_month !== '') {
			\update_post_meta($post_id, cmx_emails_meta_key('archive_year'), $archive_year);
			\update_post_meta($post_id, cmx_emails_meta_key('archive_month'), $archive_month);
		} else {
			\delete_post_meta($post_id, cmx_emails_meta_key('archive_year'));
			\delete_post_meta($post_id, cmx_emails_meta_key('archive_month'));
		}
		cmx_emails_save_assignments($post_id, $contact_ids, $project_ids, $assignment_manual, false);
		if ($existing_id <= 0 && $sender['email'] !== '') {
			cmx_emails_append_received_sender_notes($post_id, (string) $sender['email']);
		}

		return ['post_id' => $post_id, 'uid' => $uid, 'archived' => $archived_message, 'spam_moved' => false];
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_open_client_mailbox')) {
	function cmx_emails_open_client_mailbox(array $client, string $mailbox, ?string &$resolved_mailbox = null) {
		$host = \sanitize_text_field((string) ($client['imap_host'] ?? ''));
		$user = \sanitize_email((string) ($client['email'] ?? ''));
		$pass = (string) ($client['password'] ?? '');
		$port = (int) (($client['imap_port'] ?? 993) ?: 993);
		$resolved_mailbox = null;
		$mailbox = \trim($mailbox);

		if ($host === '' || $user === '' || $pass === '' || $mailbox === '' || !\function_exists('imap_open')) {
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
		$short_mailbox = cmx_emails_imap_short_mailbox_name($mailbox);

		foreach ($root_candidates as $root) {
			$imap = @\imap_open($root . 'INBOX', $user, $pass);
			if ($imap === false) {
				continue;
			}

			$available = cmx_emails_imap_mailboxes($imap, $root);
			$candidates = [];
			if (\str_starts_with($mailbox, '{')) {
				$candidates[] = $mailbox;
			}
			if ($short_mailbox !== '') {
				$candidates[] = $root . $short_mailbox;
			}

			foreach (\array_values(\array_unique($candidates)) as $candidate_mailbox) {
				if (@\imap_reopen($imap, $candidate_mailbox)) {
					$resolved_mailbox = $candidate_mailbox;
					return $imap;
				}
			}

			foreach ($available as $mailbox_info) {
				$available_full = (string) ($mailbox_info['name'] ?? '');
				$available_short = (string) ($mailbox_info['short'] ?? '');
				if (
					($mailbox !== '' && \strcasecmp($available_full, $mailbox) === 0)
					|| ($short_mailbox !== '' && \strcasecmp($available_short, $short_mailbox) === 0)
				) {
					if (@\imap_reopen($imap, $available_full)) {
						$resolved_mailbox = $available_full;
						return $imap;
					}
				}
			}

			@\imap_close($imap);
		}

		return false;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_open_client_archive_mailbox')) {
	function cmx_emails_open_client_archive_mailbox(array $client, string $year, string $month, ?string &$resolved_mailbox = null) {
		$host = \sanitize_text_field((string) ($client['imap_host'] ?? ''));
		$user = \sanitize_email((string) ($client['email'] ?? ''));
		$pass = (string) ($client['password'] ?? '');
		$port = (int) (($client['imap_port'] ?? 993) ?: 993);
		$year = \preg_replace('/[^0-9]/', '', $year);
		$month = cmx_emails_normalize_archive_month($month);
		$resolved_mailbox = null;

		if ($host === '' || $user === '' || $pass === '' || \strlen($year) !== 4 || $month === '' || !\function_exists('imap_open')) {
			return false;
		}

		$root_candidates = [
			'{' . $host . ':' . $port . '/imap/ssl}',
			'{' . $host . ':' . $port . '/imap/ssl/novalidate-cert}',
		];
		$archive_candidates = (array) (cmx_emails_folder_map()['archive']['candidates'] ?? ['Archive']);

		foreach ($root_candidates as $root) {
			$imap = @\imap_open($root . 'INBOX', $user, $pass);
			if ($imap === false) {
				continue;
			}

			$mailboxes = cmx_emails_imap_mailboxes($imap, $root);
			foreach ($mailboxes as $mailbox_info) {
				$full_name = (string) ($mailbox_info['name'] ?? '');
				$short_name = (string) ($mailbox_info['short'] ?? '');
				$delimiter = (string) ($mailbox_info['delimiter'] ?? '.');
				if ($full_name === '' || $short_name === '' || $delimiter === '') {
					continue;
				}

				$suffix = $delimiter . $year . $delimiter . $month;
				if (!\str_ends_with(\strtolower($short_name), \strtolower($suffix))) {
					continue;
				}

				$prefix = \substr($short_name, 0, -\strlen($suffix));
				foreach ($archive_candidates as $candidate) {
					$candidate = (string) $candidate;
					if ($candidate === '') {
						continue;
					}

					if (\strcasecmp($prefix, $candidate) === 0 || \str_ends_with(\strtolower($prefix), \strtolower($candidate))) {
						if (@\imap_reopen($imap, $full_name)) {
							$resolved_mailbox = $full_name;
							return $imap;
						}
					}
				}
			}

			@\imap_close($imap);
		}

		return false;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_list_client_archive_mailboxes')) {
	function cmx_emails_list_client_archive_mailboxes(array $client): array {
		$host = \sanitize_text_field((string) ($client['imap_host'] ?? ''));
		$user = \sanitize_email((string) ($client['email'] ?? ''));
		$pass = (string) ($client['password'] ?? '');
		$port = (int) (($client['imap_port'] ?? 993) ?: 993);
		if ($host === '' || $user === '' || $pass === '' || !\function_exists('imap_open')) {
			return [];
		}

		$root_candidates = [
			'{' . $host . ':' . $port . '/imap/ssl}',
			'{' . $host . ':' . $port . '/imap/ssl/novalidate-cert}',
		];
		$archive_candidates = (array) (cmx_emails_folder_map()['archive']['candidates'] ?? ['Archive']);

		foreach ($root_candidates as $root) {
			$imap = @\imap_open($root . 'INBOX', $user, $pass);
			if ($imap === false) {
				continue;
			}

			$mailboxes = cmx_emails_imap_mailboxes($imap, $root);
			if ($mailboxes === []) {
				@\imap_close($imap);
				continue;
			}

			$archive_mailboxes = [];
			foreach ($mailboxes as $mailbox_info) {
				$full_name = (string) ($mailbox_info['name'] ?? '');
				$short_name = (string) ($mailbox_info['short'] ?? '');
				$delimiter = (string) ($mailbox_info['delimiter'] ?? '.');
				if ($full_name === '' || $short_name === '' || $delimiter === '') {
					continue;
				}

				foreach ($archive_candidates as $candidate) {
					$candidate = (string) $candidate;
					if ($candidate === '') {
						continue;
					}

					$pattern = '/(?:^|'.\preg_quote($delimiter, '/').')' . \preg_quote($candidate, '/') . \preg_quote($delimiter, '/') . '([0-9]{4})' . \preg_quote($delimiter, '/') . '([0-9]{2})$/i';
					if (!\preg_match($pattern, $short_name, $matches)) {
						continue;
					}

					$year = \preg_replace('/[^0-9]/', '', (string) ($matches[1] ?? ''));
					$month = cmx_emails_normalize_archive_month((string) ($matches[2] ?? ''));
					if (\strlen($year) !== 4 || $month === '') {
						continue;
					}

					$key = \strtolower($full_name);
					$mail_count = null;
					if (\function_exists('imap_status') && \defined('SA_MESSAGES')) {
						$status = @\imap_status($imap, $full_name, \SA_MESSAGES);
						if (\is_object($status) && isset($status->messages)) {
							$mail_count = \max(0, (int) $status->messages);
						}
					}
					$archive_mailboxes[$key] = [
						'mailbox' => $full_name,
						'short' => $short_name,
						'year' => $year,
						'month' => $month,
						'count' => $mail_count,
					];
					break;
				}
			}

			if ($archive_mailboxes !== []) {
				@\imap_close($imap);
				\usort($archive_mailboxes, static function (array $left, array $right): int {
					return \strcmp((string) ($right['short'] ?? ''), (string) ($left['short'] ?? ''));
				});
				return \array_values($archive_mailboxes);
			}

			@\imap_close($imap);
		}

		return [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_archive_filter_option_map')) {
	function cmx_emails_archive_filter_option_map(array $client_rows = []): array {
		$client_rows = $client_rows !== [] ? $client_rows : (array) cmx_emails_client_list();
		$account_ids = [''];
		foreach ($client_rows as $client) {
			$client = (array) $client;
			$client_id = \sanitize_key((string) ($client['id'] ?? ''));
			if ($client_id !== '') {
				$account_ids[] = $client_id;
			}
		}
		$account_ids = \array_values(\array_unique($account_ids));

		$map = [];
		foreach ($account_ids as $account_id) {
			$year_options = cmx_emails_archive_year_options((string) $account_id);
			$month_map = [];
			foreach ($year_options as $year_value => $year_label) {
				$month_map[(string) $year_value] = cmx_emails_archive_month_options((string) $year_value, (string) $account_id);
			}
			$map[(string) $account_id] = [
				'years' => $year_options,
				'months' => $month_map,
				'year_counts' => [],
				'month_counts' => [],
			];
		}

		foreach ($client_rows as $client) {
			$client = (array) $client;
			$client_id = \sanitize_key((string) ($client['id'] ?? ''));
			if ($client_id === '') {
				continue;
			}

			foreach (cmx_emails_list_client_archive_mailboxes($client) as $archive_mailbox) {
				$year = \preg_replace('/[^0-9]/', '', (string) ($archive_mailbox['year'] ?? ''));
				$month = cmx_emails_normalize_archive_month((string) ($archive_mailbox['month'] ?? ''));
				$mail_count = $archive_mailbox['count'] ?? null;
				if (\strlen($year) !== 4 || $month === '') {
					continue;
				}

				foreach (['', $client_id] as $account_key) {
					if (!isset($map[$account_key])) {
						$map[$account_key] = ['years' => [], 'months' => []];
					}
					if (!isset($map[$account_key]['years'][$year])) {
						$map[$account_key]['years'][$year] = $year;
					}
					if (!isset($map[$account_key]['months'][$year])) {
						$map[$account_key]['months'][$year] = [];
					}
					if (!isset($map[$account_key]['months'][$year][$month])) {
						$map[$account_key]['months'][$year][$month] = cmx_emails_archive_month_label($month);
					}
					if (\is_int($mail_count)) {
						if (!isset($map[$account_key]['year_counts'][$year])) {
							$map[$account_key]['year_counts'][$year] = 0;
						}
						$map[$account_key]['year_counts'][$year] += $mail_count;

						if (!isset($map[$account_key]['month_counts'][$year])) {
							$map[$account_key]['month_counts'][$year] = [];
						}
						if (!isset($map[$account_key]['month_counts'][$year][$month])) {
							$map[$account_key]['month_counts'][$year][$month] = 0;
						}
						$map[$account_key]['month_counts'][$year][$month] += $mail_count;
					}
				}
			}
		}

		foreach ($map as &$entry) {
			$entry['years'] = \is_array($entry['years'] ?? null) ? $entry['years'] : [];
			$entry['months'] = \is_array($entry['months'] ?? null) ? $entry['months'] : [];
			$entry['year_counts'] = \is_array($entry['year_counts'] ?? null) ? $entry['year_counts'] : [];
			$entry['month_counts'] = \is_array($entry['month_counts'] ?? null) ? $entry['month_counts'] : [];

			foreach ($entry['months'] as $year => &$months) {
				$months = \is_array($months) ? $months : [];
				if (!isset($entry['years'][(string) $year])) {
					$entry['years'][(string) $year] = (string) $year;
				}
				if ($months !== []) {
					\uksort($months, static function (string $left, string $right): int {
						return \strcmp($right, $left);
					});
				}
			}
			unset($months);

			foreach ($entry['year_counts'] as $year => $count) {
				$year = \preg_replace('/[^0-9]/', '', (string) $year);
				if (\strlen($year) !== 4) {
					continue;
				}
				$entry['years'][$year] = $year . ' (' . \max(0, (int) $count) . ')';
			}

			foreach ($entry['month_counts'] as $year => $months) {
				$year = \preg_replace('/[^0-9]/', '', (string) $year);
				if (\strlen($year) !== 4 || !\is_array($months)) {
					continue;
				}
				if (!isset($entry['months'][$year]) || !\is_array($entry['months'][$year])) {
					$entry['months'][$year] = [];
				}
				foreach ($months as $month => $count) {
					$month = cmx_emails_normalize_archive_month((string) $month);
					if ($month === '') {
						continue;
					}
					$entry['months'][$year][$month] = cmx_emails_archive_month_label($month) . ' (' . \max(0, (int) $count) . ')';
				}
			}

			if ($entry['years'] !== []) {
				\uksort($entry['years'], static function (string $left, string $right): int {
					return \strcmp($right, $left);
				});
			}

			unset($entry['year_counts'], $entry['month_counts']);
		}
		unset($entry);

		return $map;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_recheck_archive_mailboxes_for_spam')) {
	function cmx_emails_recheck_archive_mailboxes_for_spam(array $client, int $limit = 0): array {
		$account_id = \sanitize_key((string) ($client['id'] ?? ''));
		if ($account_id === '') {
			return ['processed' => 0, 'spam_moved' => 0];
		}

		$limit = cmx_emails_message_limit($limit);
		if ($limit <= 0) {
			return ['processed' => 0, 'spam_moved' => 0];
		}

		$processed = 0;
		$spam_moved = 0;

		foreach (cmx_emails_list_client_archive_mailboxes($client) as $archive_mailbox) {
			$mailbox = (string) ($archive_mailbox['mailbox'] ?? '');
			if ($mailbox === '') {
				continue;
			}

			$resolved_mailbox = '';
			$imap = cmx_emails_open_client_mailbox($client, $mailbox, $resolved_mailbox);
			if ($imap === false) {
				continue;
			}

			$total = \function_exists('imap_num_msg') ? (int) @\imap_num_msg($imap) : 0;
			$mailbox_changed = false;
			$mailbox_processed = 0;
			for ($msg_no = $total; $msg_no >= 1 && $mailbox_processed < $limit; $msg_no--) {
				$processed++;
				$mailbox_processed++;

				$overview_rows = @\imap_fetch_overview($imap, (string) $msg_no, 0);
				$overview = (\is_array($overview_rows) && isset($overview_rows[0]) && \is_object($overview_rows[0])) ? $overview_rows[0] : null;
				if (!$overview instanceof \stdClass) {
					continue;
				}

				$uid = \function_exists('imap_uid') ? (int) @\imap_uid($imap, $msg_no) : 0;
				$raw_header = (string) @\imap_fetchheader($imap, $msg_no, \FT_PREFETCHTEXT);
				if ($uid <= 0 || $raw_header === '') {
					continue;
				}

				$header = @\imap_rfc822_parse_headers($raw_header);
				if (!$header || !\is_object($header)) {
					continue;
				}

				$payload = cmx_emails_fetch_message_payload($imap, $msg_no);
				$sender = cmx_emails_sender_from_header($header);
				$subject = isset($header->subject) ? \sanitize_text_field(cmx_emails_decode_mime_text((string) $header->subject)) : '';
				$message_id = \sanitize_text_field((string) ($header->message_id ?? ''));
				$received_ts = cmx_emails_parse_message_timestamp($overview, $header);
				$spam = cmx_emails_analyze_message_spam([
					'subject' => $subject,
					'from' => (string) ($sender['label'] ?? ''),
					'headers_raw' => $raw_header,
					'body_text' => \trim((string) ($payload['plain'] ?? '')),
					'body_html' => (string) ($payload['html'] ?? ''),
				]);
				$spam_status = \sanitize_key((string) ($spam['status'] ?? ''));
				if (
					cmx_emails_message_has_manual_ham_override(
						$account_id,
						$uid,
						$message_id,
						[
							'subject' => $subject,
							'sender_email' => (string) ($sender['email'] ?? ''),
							'received_ts' => $received_ts,
							'folders' => ['archive', 'inbox', 'spam'],
						]
					)
				) {
					continue;
				}
				if ($spam_status !== 'spam') {
					continue;
				}

				$local_spam_post_id = cmx_emails_mark_existing_message_as_spam(
					$account_id,
					$uid,
					$message_id,
					'',
					[
						'subject' => $subject,
						'sender_email' => (string) ($sender['email'] ?? ''),
						'received_ts' => $received_ts,
						'folders' => ['archive', 'inbox', 'spam'],
					]
				);
				$spam_move = cmx_emails_move_inbox_message_to_spam($imap, $uid, $resolved_mailbox !== '' ? $resolved_mailbox : $mailbox);
				if (!empty($spam_move['moved'])) {
					cmx_emails_mark_existing_message_as_spam(
						$account_id,
						$uid,
						$message_id,
						(string) ($spam_move['full_mailbox'] ?? ''),
						[
							'subject' => $subject,
							'sender_email' => (string) ($sender['email'] ?? ''),
							'received_ts' => $received_ts,
							'folders' => ['archive', 'inbox', 'spam'],
						]
					);
					$spam_moved++;
					$mailbox_changed = true;
					continue;
				}

				if ($local_spam_post_id > 0) {
					$spam_moved++;
				}
			}

			if ($mailbox_changed && \function_exists('imap_expunge')) {
				@\imap_expunge($imap);
			}
			@\imap_close($imap);
		}

		return [
			'processed' => $processed,
			'spam_moved' => $spam_moved,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_find_uid_by_message_id')) {
	function cmx_emails_find_uid_by_message_id($imap, string $message_id): int {
		$message_id = \trim(\sanitize_text_field($message_id));
		if ($message_id === '' || !\function_exists('imap_search')) {
			return 0;
		}

		$flags = \defined('SE_UID') ? (int) \constant('SE_UID') : 0;
		$variants = [];
		$variants[] = $message_id;

		$trimmed_id = \trim($message_id, "<> \t\n\r\0\x0B");
		if ($trimmed_id !== '' && $trimmed_id !== $message_id) {
			$variants[] = $trimmed_id;
		}
		if ($trimmed_id !== '') {
			$variants[] = '<' . $trimmed_id . '>';
		}

		foreach (\array_values(\array_unique($variants)) as $variant) {
			$variant = (string) \preg_replace('/["\\\\]/', '', $variant);
			if ($variant === '') {
				continue;
			}

			$criteria_list = [
				'HEADER Message-ID "' . $variant . '"',
				'HEADER Message-Id "' . $variant . '"',
				'HEADER "Message-ID" "' . $variant . '"',
			];

			foreach ($criteria_list as $criteria) {
				$results = @\imap_search($imap, $criteria, $flags);
				if (!\is_array($results) || $results === []) {
					continue;
				}

				foreach ($results as $uid) {
					$uid = (int) $uid;
					if ($uid > 0) {
						return $uid;
					}
				}
			}
		}

		$total = \function_exists('imap_num_msg') ? (int) @\imap_num_msg($imap) : 0;
		for ($msg_no = $total; $msg_no >= 1; $msg_no--) {
			$header_raw = (string) @\imap_fetchheader($imap, $msg_no, \FT_PREFETCHTEXT);
			if ($header_raw === '') {
				continue;
			}

			if (\preg_match('/^Message-Id:\s*(.+)$/mi', $header_raw, $matches) || \preg_match('/^Message-ID:\s*(.+)$/mi', $header_raw, $matches)) {
				$current_message_id = \trim(\sanitize_text_field((string) ($matches[1] ?? '')));
				$current_message_id = \trim($current_message_id, "<> \t\n\r\0\x0B");
				if ($current_message_id !== '' && $current_message_id === $trimmed_id) {
					$uid = \function_exists('imap_uid') ? (int) @\imap_uid($imap, $msg_no) : 0;
					if ($uid > 0) {
						return $uid;
					}
				}
			}
		}

		return 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_message_id_from_uid')) {
	function cmx_emails_message_id_from_uid($imap, int $uid): string {
		$uid = (int) $uid;
		if ($uid <= 0 || !\function_exists('imap_msgno')) {
			return '';
		}

		$msg_no = (int) @\imap_msgno($imap, $uid);
		if ($msg_no <= 0) {
			return '';
		}

		$header_raw = (string) @\imap_fetchheader($imap, $msg_no, \FT_PREFETCHTEXT);
		if ($header_raw === '') {
			return '';
		}

		if (\preg_match('/^Message-Id:\s*(.+)$/mi', $header_raw, $matches) || \preg_match('/^Message-ID:\s*(.+)$/mi', $header_raw, $matches)) {
			return \trim(\sanitize_text_field((string) ($matches[1] ?? '')), "<> \t\n\r\0\x0B");
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_resolve_uid_in_mailbox')) {
	function cmx_emails_resolve_uid_in_mailbox($imap, int $uid, string $message_id): int {
		$uid = (int) $uid;
		$message_id = \trim(\sanitize_text_field($message_id), "<> \t\n\r\0\x0B");
		if ($uid > 0 && \function_exists('imap_msgno')) {
			$msg_no = (int) @\imap_msgno($imap, $uid);
			if ($msg_no > 0) {
				if ($message_id === '') {
					return $uid;
				}

				$current_message_id = cmx_emails_message_id_from_uid($imap, $uid);
				if ($current_message_id !== '' && $current_message_id === $message_id) {
					return $uid;
				}
			}
		}

		if ($message_id === '') {
			if ($uid > 0 && \function_exists('imap_msgno')) {
				$msg_no = (int) @\imap_msgno($imap, $uid);
				if ($msg_no > 0) {
					return $uid;
				}
			}

			return 0;
		}

		return cmx_emails_find_uid_by_message_id($imap, $message_id);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_move_existing_post_to_spam')) {
	function cmx_emails_move_existing_post_to_spam(int $post_id, array $client = []): bool {
		$post_id = (int) $post_id;
		if ($post_id <= 0) {
			return false;
		}
		if (cmx_emails_is_manual_ham_post($post_id)) {
			return false;
		}

		$account_id = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('account_id'), true));
		if ($account_id === '') {
			return false;
		}

		if ($client === [] || \sanitize_key((string) ($client['id'] ?? '')) !== $account_id) {
			$client = cmx_emails_get_client($account_id);
		}
		if ($client === []) {
			return false;
		}

		$uid = (int) \get_post_meta($post_id, cmx_emails_meta_key('uid'), true);
		$message_id = \sanitize_text_field((string) \get_post_meta($post_id, cmx_emails_meta_key('message_id'), true));
		$folder = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('folder'), true));
		$mailbox = (string) \get_post_meta($post_id, cmx_emails_meta_key('mailbox'), true);
		$archive_year = \preg_replace('/[^0-9]/', '', (string) \get_post_meta($post_id, cmx_emails_meta_key('archive_year'), true));
		$archive_month = cmx_emails_normalize_archive_month((string) \get_post_meta($post_id, cmx_emails_meta_key('archive_month'), true));

		$resolved_mailbox = '';
		$imap = $mailbox !== '' ? cmx_emails_open_client_mailbox($client, $mailbox, $resolved_mailbox) : false;
		if ($imap === false && $folder === 'archive' && \strlen($archive_year) === 4 && $archive_month !== '') {
			$imap = cmx_emails_open_client_archive_mailbox($client, $archive_year, $archive_month, $resolved_mailbox);
		}
		if ($imap === false) {
			return false;
		}

		$resolved_uid = cmx_emails_resolve_uid_in_mailbox($imap, $uid, $message_id);
		if ($resolved_uid <= 0 && $folder === 'archive' && \strlen($archive_year) === 4 && $archive_month !== '') {
			@\imap_close($imap);
			$resolved_mailbox = '';
			$imap = cmx_emails_open_client_archive_mailbox($client, $archive_year, $archive_month, $resolved_mailbox);
			if ($imap !== false) {
				$resolved_uid = cmx_emails_resolve_uid_in_mailbox($imap, $uid, $message_id);
			}
		}
		if ($resolved_uid <= 0) {
			@\imap_close($imap);
			return false;
		}
		if ($resolved_uid !== $uid) {
			\update_post_meta($post_id, cmx_emails_meta_key('uid'), (string) $resolved_uid);
		}

		$spam_move = cmx_emails_move_inbox_message_to_spam($imap, $resolved_uid, $resolved_mailbox !== '' ? $resolved_mailbox : $mailbox);
		if (!empty($spam_move['moved'])) {
			$target_mailbox = (string) ($spam_move['full_mailbox'] ?? '');
			cmx_emails_mark_post_as_spam($post_id, $target_mailbox);
			cmx_emails_mark_existing_message_as_spam(
				$account_id,
				$resolved_uid,
				$message_id,
				$target_mailbox
			);
			if (\function_exists('imap_expunge')) {
				@\imap_expunge($imap);
			}
			@\imap_close($imap);
			return true;
		}

		@\imap_close($imap);
		return false;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_move_existing_post_to_inbox')) {
	function cmx_emails_move_existing_post_to_inbox(int $post_id, array $client = []): bool {
		$post_id = (int) $post_id;
		if ($post_id <= 0) {
			return false;
		}

		$account_id = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('account_id'), true));
		if ($account_id === '') {
			return false;
		}

		if ($client === [] || \sanitize_key((string) ($client['id'] ?? '')) !== $account_id) {
			$client = cmx_emails_get_client($account_id);
		}
		if ($client === []) {
			return false;
		}

		$uid = (int) \get_post_meta($post_id, cmx_emails_meta_key('uid'), true);
		$message_id = \sanitize_text_field((string) \get_post_meta($post_id, cmx_emails_meta_key('message_id'), true));
		$mailbox = (string) \get_post_meta($post_id, cmx_emails_meta_key('mailbox'), true);
		$folder = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('folder'), true));
		$archive_year = \preg_replace('/[^0-9]/', '', (string) \get_post_meta($post_id, cmx_emails_meta_key('archive_year'), true));
		$archive_month = cmx_emails_normalize_archive_month((string) \get_post_meta($post_id, cmx_emails_meta_key('archive_month'), true));

		$resolved_mailbox = '';
		$imap = $mailbox !== '' ? cmx_emails_open_client_mailbox($client, $mailbox, $resolved_mailbox) : false;
		if ($imap === false && $folder === 'archive' && \strlen($archive_year) === 4 && $archive_month !== '') {
			$imap = cmx_emails_open_client_archive_mailbox($client, $archive_year, $archive_month, $resolved_mailbox);
		}
		if ($imap === false && $folder === 'spam') {
			$imap = cmx_emails_open_client_folder($client, 'spam', $resolved_mailbox);
		}
		if ($imap === false) {
			return false;
		}

		$resolved_uid = cmx_emails_resolve_uid_in_mailbox($imap, $uid, $message_id);
		if ($resolved_uid <= 0) {
			@\imap_close($imap);
			return false;
		}
		if ($resolved_uid !== $uid) {
			\update_post_meta($post_id, cmx_emails_meta_key('uid'), (string) $resolved_uid);
		}

		$inbox_move = cmx_emails_move_message_to_inbox($imap, $resolved_uid, $resolved_mailbox !== '' ? $resolved_mailbox : $mailbox);
		if (!empty($inbox_move['moved'])) {
			$target_mailbox = (string) ($inbox_move['full_mailbox'] ?? '');
			cmx_emails_mark_post_as_manual_ham($post_id, $target_mailbox);
			cmx_emails_mark_existing_message_as_manual_ham(
				$account_id,
				$resolved_uid,
				$message_id,
				$target_mailbox,
				[
					'subject' => (string) \get_post_meta($post_id, cmx_emails_meta_key('subject'), true),
					'sender_email' => (string) \get_post_meta($post_id, cmx_emails_meta_key('sender_email'), true),
					'received_ts' => (int) \get_post_meta($post_id, cmx_emails_meta_key('received_ts'), true),
					'folders' => ['archive', 'inbox', 'spam'],
				]
			);
			if (\function_exists('imap_expunge')) {
				@\imap_expunge($imap);
			}
			@\imap_close($imap);
			return true;
		}

		@\imap_close($imap);
		return false;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_sync_client_messages')) {
	function cmx_emails_sync_client_messages(string $client_id, string $folder = 'inbox', int $limit = 0, string $archive_year = '', string $archive_month = ''): array {
		$client = cmx_emails_get_client($client_id);
		if ($client === []) {
			return ['ok' => false, 'message' => 'Kein E-Mail-Client gefunden.', 'synced' => 0];
		}
		if (!\function_exists('imap_open')) {
			return ['ok' => false, 'message' => 'PHP-IMAP ist auf diesem Server nicht verfuegbar. Bitte die PHP-Erweiterung imap aktivieren.', 'synced' => 0];
		}

		$limit = cmx_emails_message_limit($limit);
		$folder = \sanitize_key($folder);
		$archive_year = \preg_replace('/[^0-9]/', '', $archive_year);
		$archive_month = cmx_emails_normalize_archive_month($archive_month);
		$mailbox = '';
		if ($folder === 'archive') {
			if (\strlen($archive_year) !== 4 || $archive_month === '') {
				return ['ok' => false, 'message' => 'Bitte Archivjahr und Archivmonat waehlen.', 'synced' => 0];
			}
			$imap = cmx_emails_open_client_archive_mailbox($client, $archive_year, $archive_month, $mailbox);
		} else {
			$imap = cmx_emails_open_client_folder($client, $folder, $mailbox);
		}
		if ($imap === false) {
			$error = \function_exists('imap_last_error') ? (string) \imap_last_error() : '';
			return ['ok' => false, 'message' => 'IMAP-Verbindung fehlgeschlagen.' . ($error !== '' ? ' ' . $error : ''), 'synced' => 0];
		}

		$total = (int) \imap_num_msg($imap);
		$synced = 0;
		$skipped = 0;
		$trashed_existing = 0;
		$ignored_spam_sender = 0;
		$archived = 0;
		$spam_moved = 0;
		$auto_assigned = 0;
		$archived_numbers = [];
		$manual_cleanup_required = false;
		$archive_backlog_remaining = false;
		$archive_candidates = [];
		$archive_processed = 0;

		if ($folder === 'inbox' && $total > 0) {
			$archive_candidates = cmx_emails_stale_inbox_message_numbers($imap);
			if (\count($archive_candidates) > $limit) {
				$archive_candidates = \array_slice($archive_candidates, 0, $limit);
				$archive_backlog_remaining = true;
			}
		}

		if ($archive_candidates !== []) {
			foreach ($archive_candidates as $msg_no) {
				$msg_no = (int) $msg_no;
				if ($msg_no <= 0) {
					continue;
				}

				$archive_processed++;
				$archived_numbers[$msg_no] = true;
					$result = cmx_emails_sync_single_message($imap, $client, $folder, $msg_no, $mailbox, [
						'archive_year' => $archive_year,
						'archive_month' => $archive_month,
					]);
				if (!empty($result['ignored_spam_sender'])) {
					$ignored_spam_sender++;
				} elseif (!empty($result['trashed_existing'])) {
					$trashed_existing++;
				} elseif (!empty($result['skipped'])) {
					$skipped++;
				} elseif ((int) ($result['post_id'] ?? 0) > 0) {
					$synced++;
				}
				if (!empty($result['spam_moved'])) {
					$spam_moved++;
					$archived_numbers[$msg_no] = true;
					continue;
				}
				if (!empty($result['archived'])) {
					$archived++;
				}
			}
		}

		$remaining_limit = \max(0, $limit - $archive_processed);
		$current_count = 0;
		for ($msg_no = $total; $msg_no >= 1 && $current_count < $remaining_limit; $msg_no--) {
			if (isset($archived_numbers[$msg_no])) {
				continue;
			}

			$result = cmx_emails_sync_single_message($imap, $client, $folder, $msg_no, $mailbox, [
				'archive_year' => $archive_year,
				'archive_month' => $archive_month,
			]);
			if (!empty($result['ignored_spam_sender'])) {
				$ignored_spam_sender++;
			} elseif (!empty($result['trashed_existing'])) {
				$trashed_existing++;
			} elseif (!empty($result['skipped'])) {
				$skipped++;
			} elseif ((int) ($result['post_id'] ?? 0) > 0) {
				$synced++;
			}
			if (!empty($result['spam_moved'])) {
				$spam_moved++;
				$archived_numbers[$msg_no] = true;
				continue;
			}
			if (!empty($result['archived'])) {
				$archived++;
				$archived_numbers[$msg_no] = true;
				continue;
			}

			$current_count++;
			if ($current_count >= $remaining_limit) {
				for ($probe = $msg_no - 1; $probe >= 1; $probe--) {
					if (!isset($archived_numbers[$probe])) {
						$manual_cleanup_required = true;
						break;
					}
				}
				break;
			}
		}

		if (($archived > 0 || $spam_moved > 0) && \function_exists('imap_expunge')) {
			@\imap_expunge($imap);
		}

		@\imap_close($imap);

		if ($folder === 'inbox') {
			$spam_moved += cmx_emails_recheck_existing_posts_for_spam($client, $limit);
			$archive_spam_scan = cmx_emails_recheck_archive_mailboxes_for_spam($client, $limit);
			$spam_moved += (int) ($archive_spam_scan['spam_moved'] ?? 0);
		}
		$auto_assigned = cmx_emails_auto_assign_existing_posts($client_id, $folder, $limit);

		$message = $synced > 0
			? ($synced . ' E-Mails synchronisiert.')
			: (($skipped > 0 || $trashed_existing > 0 || $ignored_spam_sender > 0) ? 'Keine neuen E-Mails synchronisiert.' : 'Keine E-Mails gefunden.');
		if ($archived > 0) {
			$message .= ' ' . $archived . ' E-Mails wurden in Monatsarchive verschoben.';
		}
		if ($spam_moved > 0) {
			$message .= ' ' . $spam_moved . ' E-Mails wurden in Spam verschoben.';
		}
		if ($skipped > 0) {
			$message .= ' ' . $skipped . ' bereits vorhandene E-Mails übersprungen.';
		}
		if ($trashed_existing > 0) {
			$message .= ' ' . $trashed_existing . ' E-Mails liegen lokal im Papierkorb und bleiben dort.';
		}
		if ($ignored_spam_sender > 0) {
			$message .= ' ' . $ignored_spam_sender . ' E-Mails von Spam-Absendern ignoriert.';
		}
		if ($auto_assigned > 0) {
			$message .= ' ' . $auto_assigned . ' Zuordnungen aktualisiert.';
		}
		if ($archive_backlog_remaining) {
			$message .= ' Weitere alte E-Mails folgen beim nächsten Sync.';
		}
		if ($manual_cleanup_required) {
			$message .= ' Mehr als ' . $limit . ' aktuelle E-Mails liegen noch im Posteingang. Bitte manuell löschen.';
		}

		return [
			'ok' => true,
			'message' => $message,
			'synced' => $synced,
			'skipped' => $skipped,
			'trashed_existing' => $trashed_existing,
			'ignored_spam_sender' => $ignored_spam_sender,
			'archived' => $archived,
			'spam_moved' => $spam_moved,
			'auto_assigned' => $auto_assigned,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_sync_messages')) {
	function cmx_emails_sync_messages(string $client_id = '', string $folder = 'inbox', int $limit = 0, string $archive_year = '', string $archive_month = ''): array {
		$client_id = \sanitize_key($client_id);
		$folder = \sanitize_key($folder);
		$archive_year = \preg_replace('/[^0-9]/', '', $archive_year);
		$archive_month = cmx_emails_normalize_archive_month($archive_month);
		if ($folder === '') {
			$folder = 'inbox';
		}

		if ($client_id !== '') {
			return cmx_emails_sync_client_messages($client_id, $folder, $limit, $archive_year, $archive_month);
		}

		$clients = cmx_emails_client_list();
		if ($clients === []) {
			return ['ok' => false, 'message' => 'Keine E-Mail-Clients gefunden.', 'synced' => 0];
		}

		$total_synced = 0;
		$total_skipped = 0;
		$total_trashed_existing = 0;
		$total_ignored_spam_sender = 0;
		$total_auto_assigned = 0;
		$success_count = 0;
		$error_count = 0;
		$error_messages = [];

		foreach ($clients as $client) {
			$client = (array) $client;
			$id = \sanitize_key((string) ($client['id'] ?? ''));
			if ($id === '') {
				continue;
			}

			$result = cmx_emails_sync_client_messages($id, $folder, $limit, $archive_year, $archive_month);
			if (!empty($result['ok'])) {
				$success_count++;
				$total_synced += (int) ($result['synced'] ?? 0);
				$total_skipped += (int) ($result['skipped'] ?? 0);
				$total_trashed_existing += (int) ($result['trashed_existing'] ?? 0);
				$total_ignored_spam_sender += (int) ($result['ignored_spam_sender'] ?? 0);
				$total_auto_assigned += (int) ($result['auto_assigned'] ?? 0);
				continue;
			}

			$error_count++;
			$client_label = cmx_emails_client_label($client);
			$client_message = \trim((string) ($result['message'] ?? ''));
			if ($client_message === '') {
				$client_message = 'Unbekannter Fehler.';
			}
			$error_messages[] = ($client_label !== '' ? $client_label . ': ' : '') . $client_message;
		}

		if ($success_count <= 0) {
			$first_error = (string) ($error_messages[0] ?? '');
			$message = 'Synchronisierung fuer alle Konten fehlgeschlagen.';
			if ($first_error !== '') {
				$message .= ' ' . $first_error;
			}

			return ['ok' => false, 'message' => $message, 'synced' => 0];
		}

		$message = $total_synced > 0
			? ($total_synced . ' E-Mails synchronisiert.')
			: (($total_skipped > 0 || $total_trashed_existing > 0 || $total_ignored_spam_sender > 0) ? 'Keine neuen E-Mails synchronisiert.' : 'Keine E-Mails gefunden.');
		if ($total_skipped > 0) {
			$message .= ' ' . $total_skipped . ' bereits vorhandene E-Mails übersprungen.';
		}
		if ($total_trashed_existing > 0) {
			$message .= ' ' . $total_trashed_existing . ' E-Mails liegen lokal im Papierkorb und bleiben dort.';
		}
		if ($total_ignored_spam_sender > 0) {
			$message .= ' ' . $total_ignored_spam_sender . ' E-Mails von Spam-Absendern ignoriert.';
		}
		if ($total_auto_assigned > 0) {
			$message .= ' ' . $total_auto_assigned . ' Zuordnungen aktualisiert.';
		}
		if ($error_count > 0) {
			$message .= ' ' . $error_count . ' Konto/Konten konnten nicht geladen werden.';
			$first_error = (string) ($error_messages[0] ?? '');
			if ($first_error !== '') {
				$message .= ' ' . $first_error;
			}
		}

		return [
			'ok' => true,
			'message' => $message,
			'synced' => $total_synced,
			'skipped' => $total_skipped,
			'trashed_existing' => $total_trashed_existing,
			'ignored_spam_sender' => $total_ignored_spam_sender,
			'auto_assigned' => $total_auto_assigned,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_delete_scoped_posts')) {
	function cmx_emails_delete_scoped_posts(array $filters): array {
		$meta_query = cmx_emails_build_meta_query($filters);
		$args = [
			'post_type'        => CMX_EMAILS_CPT,
			'post_status'      => ['publish', 'draft', 'private', 'pending', 'trash'],
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'suppress_filters' => true,
		];
		if ($meta_query !== []) {
			$args['meta_query'] = $meta_query;
		}

		$post_ids = \get_posts($args);
		$deleted = 0;
		cmx_emails_local_delete_without_imap(true);
		try {
			foreach ((array) $post_ids as $post_id) {
				$post_id = (int) $post_id;
				if ($post_id <= 0) {
					continue;
				}

				$removed = \wp_delete_post($post_id, true);
				if ($removed instanceof \WP_Post) {
					$deleted++;
				}
			}
		} finally {
			cmx_emails_local_delete_without_imap(false);
		}

		return [
			'deleted' => $deleted,
			'post_ids' => \array_values(\array_filter(\array_map('intval', (array) $post_ids))),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_local_delete_without_imap')) {
	function cmx_emails_local_delete_without_imap(?bool $active = null): bool {
		static $is_active = false;

		if ($active !== null) {
			$is_active = $active;
		}

		return $is_active;
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
			$options[(int) $post->ID] = cmx_emails_subject_text((string) $post->post_title);
		}
		return $options;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_assignment_picker_config')) {
	function cmx_emails_assignment_picker_config(string $kind): array {
		$is_project = $kind === 'project';
		$label = $is_project ? 'Projekt' : 'Kunde';
		$nonce_action = $is_project ? 'cmx_search_projekte' : 'cmx_search_kontakte';

		return [
			'label'       => $label,
			'placeholder' => $label . ' suchen...',
			'ajax_action' => $nonce_action,
			'ajax_nonce'  => \wp_create_nonce($nonce_action),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_assignment_picker_selected_items')) {
	function cmx_emails_assignment_picker_selected_items(string $kind, array $selected_ids): array {
		$selected_ids = $kind === 'project'
			? cmx_emails_assignment_project_ids_from_list($selected_ids ?? [])
			: cmx_emails_assignment_contact_ids_from_list($selected_ids ?? []);
		$items = [];

		foreach ($selected_ids as $selected_id) {
			$selected_id = (int) $selected_id;
			if ($selected_id <= 0 || \get_post_status($selected_id) === false) {
				continue;
			}

			$title = \trim((string) \get_the_title($selected_id));
			if ($title === '') {
				continue;
			}

			$subtitle = '';
			if ($kind === 'project') {
				$project_contact_meta = \defined(__NAMESPACE__ . '\\CMX_KONTAKT_META')
					? (string) CMX_KONTAKT_META
					: '_cmx_projekt_kontakt_id';
				$contact_id = (int) \get_post_meta($selected_id, $project_contact_meta, true);
				if ($contact_id > 0) {
					$subtitle = \trim((string) \get_the_title($contact_id));
				}
			} elseif (\function_exists(__NAMESPACE__ . '\\cmx_build_kontakt_postanschrift')) {
				$subtitle = \trim((string) cmx_build_kontakt_postanschrift($selected_id));
			}

			$items[] = [
				'id'       => $selected_id,
				'title'    => $title,
				'subtitle' => $subtitle,
				'link'     => (string) \get_edit_post_link($selected_id, ''),
			];
		}

		return $items;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_assignment_contact_ids_from_list')) {
	function cmx_emails_assignment_contact_ids_from_list(array $ids): array {
		return cmx_emails_normalize_post_id_list($ids, cmx_emails_contact_post_types());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_assignment_project_ids_from_list')) {
	function cmx_emails_assignment_project_ids_from_list(array $ids): array {
		return cmx_emails_normalize_post_id_list($ids, cmx_emails_project_post_types());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_render_assignment_picker')) {
	function cmx_emails_render_assignment_picker(string $kind, array $selected_ids, string $input_name, string $context_key = ''): void {
		$config = cmx_emails_assignment_picker_config($kind);
		$selected_items = cmx_emails_assignment_picker_selected_items($kind, $selected_ids);
		$context_key = \preg_replace('/[^a-z0-9_]+/i', '_', $context_key !== '' ? $context_key : ($kind . '_' . \wp_generate_password(6, false, false)));
		$root_id = 'cmx-email-assign-' . \sanitize_html_class($context_key . '-' . $kind);
		$input_id = $root_id . '-search';
		$results_id = $root_id . '-results';

		static $printed_assets = false;
		if (!$printed_assets) {
			$printed_assets = true;
			$ajax_url = \admin_url('admin-ajax.php');
			?>
			<style>
				.cmx-email-rel-picker { position: relative; z-index: 1; }
				.cmx-email-rel-picker.is-open { z-index: 100004; }
				.cmx-email-rel-picker label { display: block; margin: 0 0 6px; }
				.cmx-email-rel-suggest { position: relative; overflow: visible; }
				.cmx-email-rel-results {
					position: absolute;
					z-index: 100005;
					left: 0;
					right: 0;
					max-height: 240px;
					overflow: auto;
					margin: 2px 0 0;
					padding: 0;
					border: 1px solid #ccd0d4;
					border-radius: 4px;
					background: #fff;
					box-shadow: 0 10px 24px rgba(0, 0, 0, .10);
					list-style: none;
				}
				.cmx-email-rel-results li { margin: 0; padding: 8px 10px; cursor: pointer; }
				.cmx-email-rel-results li.active,
				.cmx-email-rel-results li:hover { background: #e5f3ff; }
				.cmx-email-rel-results li.is-empty { color: #646970; cursor: default; }
				.cmx-email-rel-result-title { display: block; font-weight: 600; }
				.cmx-email-rel-result-sub { display: block; margin-top: 2px; color: #646970; font-size: 12px; white-space: normal; }
				.cmx-email-rel-selected {
					margin: 8px 0 0;
					padding: 0;
					list-style: none;
					display: flex;
					flex-direction: column;
					gap: 6px;
				}
				.cmx-email-rel-selected li {
					display: flex;
					align-items: flex-start;
					justify-content: space-between;
					gap: 8px;
					padding: 8px 10px;
					border: 1px solid #d0d7de;
					border-radius: 8px;
					background: #fff;
				}
				.cmx-email-rel-selected-main { min-width: 0; flex: 1 1 auto; }
				.cmx-email-rel-selected-main a { display: block; text-decoration: none; font-weight: 600; }
				.cmx-email-rel-selected-sub { display: block; margin-top: 2px; color: #646970; font-size: 12px; white-space: normal; }
				.cmx-email-rel-remove {
					line-height: 1;
					flex: 0 0 auto;
					margin-top: 0;
					transform: translateY(6px);
				}
			</style>
			<script>
				(function(){
					if (window.cmxEmailAssignPickerInit) return;
					window.cmxEmailAssignPickerInit = true;
					var ajaxUrl = <?php echo \wp_json_encode($ajax_url); ?>;

					function escHtml(str){
						return String(str || '').replace(/[&<>"']/g, function(c){
							if (c === '&') return '&amp;';
							if (c === '<') return '&lt;';
							if (c === '>') return '&gt;';
							if (c.charCodeAt(0) === 34) return '&quot;';
							return '&#039;';
						});
					}

					function normalize(str){
						str = String(str || '');
						if (str.normalize) {
							try {
								str = str.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
							} catch (err) {}
						}
						return str.toLowerCase().trim();
					}

					function selectedIds(root){
						var hiddenWrap = root.querySelector('.cmx-email-rel-hidden');
						if (!hiddenWrap) return [];
						return Array.prototype.map.call(hiddenWrap.querySelectorAll('input[type="hidden"]'), function(input){
							return String(input.value || '');
						});
					}

					function findHidden(root, id){
						var hiddenWrap = root.querySelector('.cmx-email-rel-hidden');
						if (!hiddenWrap) return null;
						var idStr = String(id || '');
						var inputs = hiddenWrap.querySelectorAll('input[type="hidden"]');
						for (var i = 0; i < inputs.length; i++) {
							if (String(inputs[i].value || '') === idStr) {
								return inputs[i];
							}
						}
						return null;
					}

					function closeResults(root){
						var results = root.querySelector('.cmx-email-rel-results');
						if (!results) return;
						results.style.display = 'none';
						results.innerHTML = '';
						root.classList.remove('is-open');
						root._cmxItems = [];
						root._cmxActive = -1;
					}

					function renderSelectedItem(item){
						var link = String(item.link || '');
						var title = escHtml(String(item.title || ''));
						var subtitle = escHtml(String(item.subtitle || ''));
						var main = link
							? '<a href="' + escHtml(link) + '" target="_blank" rel="noopener noreferrer">' + title + '</a>'
							: '<span>' + title + '</span>';
						if (subtitle) {
							main += '<span class="cmx-email-rel-selected-sub">' + subtitle + '</span>';
						}
						return '<li data-id="' + escHtml(String(item.id || '')) + '"><div class="cmx-email-rel-selected-main">' + main + '</div><button type="button" class="button-link-delete cmx-email-rel-remove" data-id="' + escHtml(String(item.id || '')) + '" aria-label="Auswahl entfernen"><span class="dashicons dashicons-trash" style="color:#d63638;"></span></button></li>';
					}

					function addSelected(root, item){
						if (!item || !item.id || findHidden(root, item.id)) return;
						var hiddenWrap = root.querySelector('.cmx-email-rel-hidden');
						var selectedList = root.querySelector('.cmx-email-rel-selected');
						if (!hiddenWrap || !selectedList) return;

						var hidden = document.createElement('input');
						hidden.type = 'hidden';
						hidden.name = root.getAttribute('data-field-name') || '';
						hidden.value = String(item.id || '');
						hiddenWrap.appendChild(hidden);

						selectedList.insertAdjacentHTML('beforeend', renderSelectedItem(item));
					}

					function removeSelected(root, id){
						var hidden = findHidden(root, id);
						if (hidden && hidden.parentNode) {
							hidden.parentNode.removeChild(hidden);
						}
						var idStr = String(id || '');
						var items = root.querySelectorAll('.cmx-email-rel-selected li[data-id]');
						for (var i = 0; i < items.length; i++) {
							if (String(items[i].getAttribute('data-id') || '') === idStr) {
								if (items[i].parentNode) {
									items[i].parentNode.removeChild(items[i]);
								}
								break;
							}
						}
					}

					function resultItems(root){
						var results = root.querySelector('.cmx-email-rel-results');
						return results ? Array.prototype.slice.call(results.querySelectorAll('li[data-index]')) : [];
					}

					function setActive(root, next){
						var items = resultItems(root);
						if (!items.length) {
							root._cmxActive = -1;
							return;
						}
						if (next < 0) next = items.length - 1;
						if (next >= items.length) next = 0;
						root._cmxActive = next;
						items.forEach(function(item, idx){
							item.classList.toggle('active', idx === next);
							if (idx === next) {
								try { item.scrollIntoView({ block: 'nearest' }); } catch (err) {}
							}
						});
					}

					function renderResults(root, items){
						var results = root.querySelector('.cmx-email-rel-results');
						if (!results) return;

						root._cmxItems = Array.isArray(items) ? items : [];
						root._cmxActive = -1;

						if (!root._cmxItems.length) {
							results.innerHTML = '<li class="is-empty">Keine Treffer gefunden.</li>';
							results.style.display = 'block';
							root.classList.add('is-open');
							return;
						}

						results.innerHTML = root._cmxItems.map(function(item, index){
							var title = escHtml(String(item.title || ''));
							var subtitle = escHtml(String(item.subtitle || ''));
							return '<li data-index="' + index + '"><span class="cmx-email-rel-result-title">' + title + '</span>' + (subtitle ? '<span class="cmx-email-rel-result-sub">' + subtitle + '</span>' : '') + '</li>';
						}).join('');
						results.style.display = 'block';
						root.classList.add('is-open');
					}

					function fetchResults(root, query){
						var action = root.getAttribute('data-ajax-action') || '';
						var nonce = root.getAttribute('data-ajax-nonce') || '';
						if (!action || !nonce) return;

						var requestId = (root._cmxRequestId || 0) + 1;
						root._cmxRequestId = requestId;

						var url = ajaxUrl + '?action=' + encodeURIComponent(action) + '&_ajax_nonce=' + encodeURIComponent(nonce) + '&q=' + encodeURIComponent(query || '');
						fetch(url, { credentials: 'same-origin' }).then(function(response){
							return response.json();
						}).then(function(json){
							if (root._cmxRequestId !== requestId) return;
							if (!json || !json.success || !json.data || !Array.isArray(json.data.items)) {
								closeResults(root);
								return;
							}

							var selected = selectedIds(root);
							var selectedMap = {};
							selected.forEach(function(id){ selectedMap[String(id)] = true; });

							var items = json.data.items.map(function(item){
								return {
									id: String(item.id || ''),
									title: String(item.title || ''),
									subtitle: String(item.addr || item.kontakt_title || item.kontakt_addr || ''),
									link: String(item.link || '')
								};
							}).filter(function(item){
								return item.id !== '' && !selectedMap[item.id];
							});

							renderResults(root, items);
						}).catch(function(){
							closeResults(root);
						});
					}

					function bindPicker(root){
						if (!root || root.dataset.cmxBound === '1') return;
						root.dataset.cmxBound = '1';

						var search = root.querySelector('.cmx-email-rel-search');
						var results = root.querySelector('.cmx-email-rel-results');
						if (!search || !results) return;

						var timer = null;
						function triggerSearch(){
							if (timer) clearTimeout(timer);
							var q = String(search.value || '').trim();
							timer = setTimeout(function(){ fetchResults(root, q); }, q === '' ? 0 : 180);
						}

						search.addEventListener('input', triggerSearch);
						search.addEventListener('focus', triggerSearch);
						search.addEventListener('click', triggerSearch);
						search.addEventListener('keydown', function(e){
							var items = root._cmxItems || [];
							if (e.key === 'ArrowDown') {
								e.preventDefault();
								setActive(root, (root._cmxActive || 0) + 1);
							} else if (e.key === 'ArrowUp') {
								e.preventDefault();
								setActive(root, (root._cmxActive || 0) - 1);
							} else if (e.key === 'Enter') {
								if (!items.length) return;
								e.preventDefault();
								var index = typeof root._cmxActive === 'number' && root._cmxActive >= 0 ? root._cmxActive : 0;
								var item = items[index];
								if (!item) return;
								addSelected(root, item);
								search.value = '';
								triggerSearch();
							} else if (e.key === 'Escape') {
								closeResults(root);
							}
						});

						results.addEventListener('mousedown', function(e){
							e.preventDefault();
						});
						results.addEventListener('click', function(e){
							var itemNode = e.target && e.target.closest ? e.target.closest('li[data-index]') : null;
							if (!itemNode) return;
							var index = parseInt(itemNode.getAttribute('data-index') || '-1', 10);
							var item = (root._cmxItems || [])[index];
							if (!item) return;
							addSelected(root, item);
							search.value = '';
							triggerSearch();
							search.focus();
						});

						root.addEventListener('click', function(e){
							var removeButton = e.target && e.target.closest ? e.target.closest('.cmx-email-rel-remove') : null;
							if (!removeButton) return;
							e.preventDefault();
							removeSelected(root, removeButton.getAttribute('data-id') || '');
						});
					}

					function initAll(){
						document.querySelectorAll('.cmx-email-rel-picker').forEach(bindPicker);
					}

					document.addEventListener('click', function(e){
						document.querySelectorAll('.cmx-email-rel-picker').forEach(function(root){
							if (!root.contains(e.target)) {
								closeResults(root);
							}
						});
					});

					if (document.readyState === 'loading') {
						document.addEventListener('DOMContentLoaded', initAll, { once: true });
					} else {
						initAll();
					}
				})();
			</script>
			<?php
		}

		echo '<div id="' . \esc_attr($root_id) . '" class="cmx-email-rel-picker" data-field-name="' . \esc_attr($input_name) . '" data-ajax-action="' . \esc_attr((string) $config['ajax_action']) . '" data-ajax-nonce="' . \esc_attr((string) $config['ajax_nonce']) . '">';
		echo '<label for="' . \esc_attr($input_id) . '"><strong>' . \esc_html((string) $config['label']) . '</strong></label>';
		echo '<div class="cmx-email-rel-suggest">';
		echo '<input type="search" id="' . \esc_attr($input_id) . '" class="widefat cmx-email-rel-search" autocomplete="off" placeholder="' . \esc_attr((string) $config['placeholder']) . '">';
		echo '<ul id="' . \esc_attr($results_id) . '" class="cmx-email-rel-results" style="display:none"></ul>';
		echo '</div>';
		echo '<div class="cmx-email-rel-hidden">';
		foreach ($selected_items as $item) {
			echo '<input type="hidden" name="' . \esc_attr($input_name) . '" value="' . \esc_attr((string) ($item['id'] ?? 0)) . '">';
		}
		echo '</div>';
		echo '<ul class="cmx-email-rel-selected">';
		foreach ($selected_items as $item) {
			$title = \esc_html((string) ($item['title'] ?? ''));
			$subtitle = \trim((string) ($item['subtitle'] ?? ''));
			$link = (string) ($item['link'] ?? '');
			echo '<li data-id="' . \esc_attr((string) ($item['id'] ?? 0)) . '">';
			echo '<div class="cmx-email-rel-selected-main">';
			if ($link !== '') {
				echo '<a href="' . \esc_url($link) . '" target="_blank" rel="noopener noreferrer">' . $title . '</a>';
			} else {
				echo '<span>' . $title . '</span>';
			}
			if ($subtitle !== '') {
				echo '<span class="cmx-email-rel-selected-sub">' . \esc_html($subtitle) . '</span>';
			}
			echo '</div>';
			echo '<button type="button" class="button-link-delete cmx-email-rel-remove" data-id="' . \esc_attr((string) ($item['id'] ?? 0)) . '" aria-label="Auswahl entfernen"><span class="dashicons dashicons-trash" style="color:#d63638;"></span></button>';
			echo '</li>';
		}
		echo '</ul>';
		echo '</div>';
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_delete_post_from_imap')) {
	function cmx_emails_delete_post_from_imap(int $post_id): array {
		$post_id = (int) $post_id;
		if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_EMAILS_CPT) {
			return ['ok' => false, 'message' => 'E-Mail wurde nicht gefunden.'];
		}
		if (!\function_exists('imap_delete') || !\function_exists('imap_open')) {
			return ['ok' => false, 'message' => 'IMAP-Erweiterung ist nicht verfuegbar.'];
		}

		$account_id = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('account_id'), true));
		if ($account_id === '') {
			return ['ok' => false, 'message' => 'Kein E-Mail-Konto hinterlegt.'];
		}

		$client = cmx_emails_get_client($account_id);
		if ($client === []) {
			return ['ok' => false, 'message' => 'E-Mail-Konto wurde nicht gefunden.'];
		}

		$uid = (int) \get_post_meta($post_id, cmx_emails_meta_key('uid'), true);
		$message_id = \sanitize_text_field((string) \get_post_meta($post_id, cmx_emails_meta_key('message_id'), true));
		if ($uid <= 0 && $message_id === '') {
			return ['ok' => false, 'message' => 'Keine IMAP-UID oder Message-ID hinterlegt.'];
		}

		$folder = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('folder'), true));
		$mailbox = \trim((string) \get_post_meta($post_id, cmx_emails_meta_key('mailbox'), true));
		$archive_year = \preg_replace('/[^0-9]/', '', (string) \get_post_meta($post_id, cmx_emails_meta_key('archive_year'), true));
		$archive_month = cmx_emails_normalize_archive_month((string) \get_post_meta($post_id, cmx_emails_meta_key('archive_month'), true));

		$resolved_mailbox = '';
		$imap = $mailbox !== '' ? cmx_emails_open_client_mailbox($client, $mailbox, $resolved_mailbox) : false;
		if ($imap === false && $folder === 'archive' && \strlen($archive_year) === 4 && $archive_month !== '') {
			$imap = cmx_emails_open_client_archive_mailbox($client, $archive_year, $archive_month, $resolved_mailbox);
		}
		if ($imap === false && $folder !== '') {
			$imap = cmx_emails_open_client_folder($client, $folder, $resolved_mailbox);
		}
		if ($imap === false && $folder !== 'inbox') {
			$imap = cmx_emails_open_client_folder($client, 'inbox', $resolved_mailbox);
		}
		if ($imap === false) {
			return ['ok' => false, 'message' => 'IMAP-Ordner konnte nicht geoeffnet werden.'];
		}

		$resolved_uid = cmx_emails_resolve_uid_in_mailbox($imap, $uid, $message_id);
		if ($resolved_uid <= 0) {
			@\imap_close($imap);
			return ['ok' => false, 'message' => 'E-Mail wurde im IMAP-Ordner nicht gefunden.'];
		}

		$flags = \defined('FT_UID') ? \FT_UID : 0;
		$deleted = @\imap_delete($imap, (string) $resolved_uid, $flags);
		if ($deleted && \function_exists('imap_expunge')) {
			@\imap_expunge($imap);
		}
		@\imap_close($imap);

		if (!$deleted) {
			return ['ok' => false, 'message' => 'E-Mail konnte im IMAP-Konto nicht geloescht werden.'];
		}

		return ['ok' => true, 'message' => 'E-Mail wurde im IMAP-Konto geloescht.'];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_delete_from_imap_before_permanent_delete')) {
	function cmx_emails_delete_from_imap_before_permanent_delete($post_id, $post = null): void {
		$post_id = (int) $post_id;
		if ($post_id <= 0 || cmx_emails_local_delete_without_imap()) {
			return;
		}

		if (!$post instanceof \WP_Post) {
			$post = \get_post($post_id);
		}
		if (!$post instanceof \WP_Post || (string) $post->post_type !== CMX_EMAILS_CPT) {
			return;
		}
		if ((string) $post->post_status !== 'trash') {
			return;
		}

		$result = cmx_emails_delete_post_from_imap($post_id);
		if (empty($result['ok'])) {
			\error_log('[cmx-emails] IMAP delete failed for email post ' . $post_id . ': ' . (string) ($result['message'] ?? 'Unbekannter Fehler.'));
		}
	}
}
\add_action('before_delete_post', __NAMESPACE__ . '\\cmx_emails_delete_from_imap_before_permanent_delete', 10, 2);

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_delete_message')) {
	function cmx_emails_delete_message(int $post_id): array {
		$post_id = (int) $post_id;
		if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_EMAILS_CPT) {
			return ['ok' => false, 'message' => 'E-Mail wurde nicht gefunden.'];
		}

		if ((string) \get_post_status($post_id) === 'trash') {
			return ['ok' => true, 'message' => 'E-Mail liegt bereits im Papierkorb.'];
		}

		\wp_trash_post($post_id);
		return ['ok' => true, 'message' => 'E-Mail wurde in den Papierkorb verschoben.'];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_spam_message')) {
	function cmx_emails_spam_message(int $post_id): array {
		$post_id = (int) $post_id;
		if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_EMAILS_CPT) {
			return ['ok' => false, 'message' => 'E-Mail wurde nicht gefunden.'];
		}
		if ((string) \get_post_status($post_id) === 'trash') {
			return ['ok' => false, 'message' => 'E-Mail liegt im Papierkorb und wurde nicht veraendert.'];
		}

		$folder = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('folder'), true));
		if ($folder === 'spam') {
			return ['ok' => true, 'message' => 'E-Mail liegt bereits im Spam.'];
		}

		\delete_post_meta($post_id, cmx_emails_meta_key('spam_override'));
		$imap_moved = \function_exists(__NAMESPACE__ . '\\cmx_emails_move_existing_post_to_spam')
			? cmx_emails_move_existing_post_to_spam($post_id)
			: false;

		if (!$imap_moved) {
			cmx_emails_mark_post_as_spam($post_id);
		}

		return [
			'ok' => true,
			'message' => $imap_moved
				? 'E-Mail wurde in Spam verschoben.'
				: 'E-Mail wurde lokal in Spam verschoben. Der Absender wird beim Sync ignoriert.',
		];
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

		$subject = cmx_emails_subject_text((string) \get_post_meta($post_id, cmx_emails_meta_key('subject'), true));
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
		$archive_year = \preg_replace('/[^0-9]/', '', (string) ($filters['archive_year'] ?? ''));
		$archive_month = cmx_emails_normalize_archive_month((string) ($filters['archive_month'] ?? ''));
		$archive_filter_active = $archive_year !== '' || $archive_month !== '';

		$account_id = \sanitize_key((string) ($filters['account_id'] ?? ''));
		if ($account_id !== '') {
			$meta_query[] = ['key' => cmx_emails_meta_key('account_id'), 'value' => $account_id];
		}

		$folder = \sanitize_key((string) ($filters['folder'] ?? ''));
		if ($archive_filter_active) {
			$folder = 'archive';
		}
		if ($folder !== '') {
			$meta_query[] = ['key' => cmx_emails_meta_key('folder'), 'value' => $folder];
			if ($folder === 'archive') {
				if ($archive_month !== '' && \strlen($archive_year) !== 4) {
					$meta_query[] = ['key' => cmx_emails_meta_key('archive_year'), 'value' => '__archive_selection_required__'];
				} elseif (\strlen($archive_year) === 4) {
					$meta_query[] = ['key' => cmx_emails_meta_key('archive_year'), 'value' => $archive_year];
					if ($archive_month !== '') {
						$meta_query[] = ['key' => cmx_emails_meta_key('archive_month'), 'value' => $archive_month];
					}
				} elseif ($archive_filter_active) {
					$meta_query[] = ['key' => cmx_emails_meta_key('archive_year'), 'value' => '__archive_selection_required__'];
				}
			}
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_archive_year_options')) {
	function cmx_emails_archive_year_options(string $account_id = ''): array {
		global $wpdb;

		$account_id = \sanitize_key($account_id);
		$posts_table = (string) $wpdb->posts;
		$postmeta_table = (string) $wpdb->postmeta;
		$folder_key = cmx_emails_meta_key('folder');
		$year_key = cmx_emails_meta_key('archive_year');
		$account_key = cmx_emails_meta_key('account_id');

		$sql = "
			SELECT year_meta.meta_value AS archive_year, COUNT(DISTINCT posts.ID) AS mail_count
			FROM {$posts_table} posts
			INNER JOIN {$postmeta_table} folder_meta
				ON folder_meta.post_id = posts.ID
				AND folder_meta.meta_key = %s
				AND folder_meta.meta_value = 'archive'
			INNER JOIN {$postmeta_table} year_meta
				ON year_meta.post_id = posts.ID
				AND year_meta.meta_key = %s
				AND year_meta.meta_value <> ''
		";
		$params = [$folder_key, $year_key];

		if ($account_id !== '') {
			$sql .= "
				INNER JOIN {$postmeta_table} account_meta
					ON account_meta.post_id = posts.ID
					AND account_meta.meta_key = %s
					AND account_meta.meta_value = %s
			";
			$params[] = $account_key;
			$params[] = $account_id;
		}

		$sql .= "
			WHERE posts.post_type = %s
				AND posts.post_status = 'publish'
			GROUP BY year_meta.meta_value
			ORDER BY year_meta.meta_value DESC
		";
		$params[] = CMX_EMAILS_CPT;

		$rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
		if (!\is_array($rows)) {
			return [];
		}

		$options = [];
		foreach ($rows as $row) {
			$year = \preg_replace('/[^0-9]/', '', (string) ($row['archive_year'] ?? ''));
			$mail_count = (int) ($row['mail_count'] ?? 0);
			if (\strlen($year) !== 4) {
				continue;
			}
			$options[$year] = $year . ' (' . $mail_count . ')';
		}

		return $options;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_archive_month_options')) {
	function cmx_emails_archive_month_options(string $year = '', string $account_id = ''): array {
		global $wpdb;

		$year = \preg_replace('/[^0-9]/', '', $year);
		$account_id = \sanitize_key($account_id);
		if (\strlen($year) !== 4) {
			return [];
		}

		$posts_table = (string) $wpdb->posts;
		$postmeta_table = (string) $wpdb->postmeta;
		$folder_key = cmx_emails_meta_key('folder');
		$year_key = cmx_emails_meta_key('archive_year');
		$month_key = cmx_emails_meta_key('archive_month');
		$account_key = cmx_emails_meta_key('account_id');

		$sql = "
			SELECT month_meta.meta_value AS archive_month, COUNT(DISTINCT posts.ID) AS mail_count
			FROM {$posts_table} posts
			INNER JOIN {$postmeta_table} folder_meta
				ON folder_meta.post_id = posts.ID
				AND folder_meta.meta_key = %s
				AND folder_meta.meta_value = 'archive'
			INNER JOIN {$postmeta_table} year_meta
				ON year_meta.post_id = posts.ID
				AND year_meta.meta_key = %s
				AND year_meta.meta_value = %s
			INNER JOIN {$postmeta_table} month_meta
				ON month_meta.post_id = posts.ID
				AND month_meta.meta_key = %s
				AND month_meta.meta_value <> ''
		";
		$params = [$folder_key, $year_key, $year, $month_key];

		if ($account_id !== '') {
			$sql .= "
				INNER JOIN {$postmeta_table} account_meta
					ON account_meta.post_id = posts.ID
					AND account_meta.meta_key = %s
					AND account_meta.meta_value = %s
			";
			$params[] = $account_key;
			$params[] = $account_id;
		}

		$sql .= "
			WHERE posts.post_type = %s
				AND posts.post_status = 'publish'
			GROUP BY month_meta.meta_value
			ORDER BY month_meta.meta_value DESC
		";
		$params[] = CMX_EMAILS_CPT;

		$rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
		if (!\is_array($rows)) {
			return [];
		}

		$options = [];
		foreach ($rows as $row) {
			$month = cmx_emails_normalize_archive_month((string) ($row['archive_month'] ?? ''));
			$mail_count = (int) ($row['mail_count'] ?? 0);
			if ($month === '') {
				continue;
			}
			$options[$month] = cmx_emails_archive_month_label($month) . ' (' . $mail_count . ')';
		}

		return $options;
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
		$post_status = \sanitize_key((string) ($filters['post_status'] ?? 'publish'));
		if (!\in_array($post_status, ['publish', 'trash'], true)) {
			$post_status = 'publish';
		}

		$posts_per_page = cmx_emails_message_limit((int) ($filters['posts_per_page'] ?? 25));

		$args = [
			'post_type'        => CMX_EMAILS_CPT,
			'post_status'      => [$post_status],
			'posts_per_page'   => $posts_per_page,
			'paged'            => \max(1, (int) ($filters['paged'] ?? 1)),
			'meta_key'         => cmx_emails_meta_key('received_ts'),
			'orderby'          => 'meta_value_num',
			'order'            => 'DESC',
			'suppress_filters' => true,
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
			'posts_per_page' => cmx_emails_message_limit(),
			'paged' => 1,
		]));
		return \min((int) $query->found_posts, cmx_emails_message_limit());
	}
}
