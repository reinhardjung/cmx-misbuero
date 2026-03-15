<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

function cmxbu_belege_export_normalize_ref(string $raw_ref): string {
	$fallback = \admin_url('edit.php?post_type=belege');
	$ref = \trim(\rawurldecode($raw_ref));
	if ($ref === '') return $fallback;

	$ref = (string) \remove_query_arg(['cmx_export', 'cmx_export_error'], $ref);
	return (string) \wp_validate_redirect($ref, $fallback);
}

function cmxbu_belege_export_current_list_ref(): string {
	$scheme = \is_ssl() ? 'https://' : 'http://';
	$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
	$uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
	$current = $scheme . $host . $uri;
	return cmxbu_belege_export_normalize_ref($current);
}

function cmxbu_belege_export_request_ref(): string {
	$raw = (string) ($_REQUEST['ref'] ?? '');
	if ($raw !== '') {
		return cmxbu_belege_export_normalize_ref($raw);
	}
	return cmxbu_belege_export_current_list_ref();
}

function cmxbu_belege_export_normalize_date(string $raw_date): string {
	$raw_date = \trim($raw_date);
	if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_date)) return '';
	[$y, $m, $d] = \array_map('intval', \explode('-', $raw_date));
	if (!\checkdate($m, $d, $y)) return '';
	return \sprintf('%04d-%02d-%02d', $y, $m, $d);
}

function cmxbu_belege_export_normalize_payment_date(string $raw_date): string {
	$raw_date = \trim($raw_date);
	if ($raw_date === '') return '';

	if (\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_normalize_any_date')) {
		$normalized = (string) cmxbu_beleg_export_normalize_any_date($raw_date);
		if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
			return $normalized;
		}
	}

	$normalized = cmxbu_belege_export_normalize_date($raw_date);
	if ($normalized !== '') return $normalized;

	if (\preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?$/', $raw_date, $m)) {
		$d = (int) $m[1];
		$mo = (int) $m[2];
		$y = (int) $m[3];
		if (\checkdate($mo, $d, $y)) {
			return \sprintf('%04d-%02d-%02d', $y, $mo, $d);
		}
	}

	$ts = \strtotime($raw_date);
	return $ts ? \date('Y-m-d', $ts) : '';
}

function cmxbu_belege_export_presets(): array {
	return [
		'heute' => 'Heute (heute bis heute)',
		'diesen_monat' => 'Diesen Monat',
		'letzten_monat' => 'Letzten Monat',
		'vorletzten_monat' => 'Vorletzten Monat',
		'dieses_quartal' => 'Dieses Quartal',
		'letztes_quartal' => 'Letztes Quartal',
		'vorletztes_quartal' => 'Vorletztes Quartal',
		'dieses_jahr' => 'Dieses Jahr',
		'letztes_jahr' => 'Letztes Jahr',
		'vorletztes_jahr' => 'Vorletztes Jahr',
		'benutzerdefiniert' => 'Benutzerdefiniert',
	];
}

function cmxbu_belege_export_requested_preset(): string {
	$preset = \sanitize_key((string) ($_REQUEST['cmx_export_range_preset'] ?? ''));
	$presets = cmxbu_belege_export_presets();
	if ($preset !== '' && isset($presets[$preset])) return $preset;
	return 'dieses_jahr';
}

function cmxbu_belege_export_pdf_appendix_options(): array {
	return [
		'mwst' => 'MwSt',
		'belegtyp' => 'Belegtyp',
		'zahlungsart' => 'Zahlungsart',
		'zahlungsgrund' => 'Zahlungsgrund',
	];
}

function cmxbu_belege_export_requested_pdf_appendices(): array {
	$raw = $_REQUEST['cmx_export_pdf_appendices'] ?? [];
	if (!\is_array($raw)) {
		return [];
	}

	$allowed = cmxbu_belege_export_pdf_appendix_options();
	$selected = [];
	foreach ($raw as $value) {
		$key = \sanitize_key((string) $value);
		if ($key !== '' && isset($allowed[$key])) {
			$selected[$key] = true;
		}
	}

	return \array_keys($selected);
}

function cmxbu_belege_export_now_datetime(): \DateTimeImmutable {
	if (\function_exists('wp_timezone')) {
		return new \DateTimeImmutable('now', \wp_timezone());
	}
	return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
}

function cmxbu_belege_export_range_from_preset(string $preset): array {
	$now = cmxbu_belege_export_now_datetime();
	$today = $now->format('Y-m-d');

	switch ($preset) {
		case 'heute':
			return ['from' => $today, 'to' => $today];
		case 'diesen_monat':
			return [
				'from' => $now->modify('first day of this month')->format('Y-m-d'),
				'to' => $now->modify('last day of this month')->format('Y-m-d'),
			];
		case 'letzten_monat':
			return [
				'from' => $now->modify('first day of last month')->format('Y-m-d'),
				'to' => $now->modify('last day of last month')->format('Y-m-d'),
			];
		case 'vorletzten_monat':
			return [
				'from' => $now->modify('first day of -2 months')->format('Y-m-d'),
				'to' => $now->modify('last day of -2 months')->format('Y-m-d'),
			];
		case 'dieses_quartal':
			$year = (int) $now->format('Y');
			$month = (int) $now->format('n');
			$q_start_month = ((int) \floor(($month - 1) / 3) * 3) + 1;
			$q_start = $now->setDate($year, $q_start_month, 1);
			$q_end = $q_start->modify('+2 months')->modify('last day of this month');
			return [
				'from' => $q_start->format('Y-m-d'),
				'to' => $q_end->format('Y-m-d'),
			];
		case 'letztes_quartal':
			$year = (int) $now->format('Y');
			$month = (int) $now->format('n');
			$q_start_month = ((int) \floor(($month - 1) / 3) * 3) + 1;
			$current_q_start = $now->setDate($year, $q_start_month, 1);
			$last_q_start = $current_q_start->modify('-3 months');
			$last_q_end = $current_q_start->modify('-1 day');
			return [
				'from' => $last_q_start->format('Y-m-d'),
				'to' => $last_q_end->format('Y-m-d'),
			];
		case 'vorletztes_quartal':
			$year = (int) $now->format('Y');
			$month = (int) $now->format('n');
			$q_start_month = ((int) \floor(($month - 1) / 3) * 3) + 1;
			$current_q_start = $now->setDate($year, $q_start_month, 1);
			$prev2_q_start = $current_q_start->modify('-6 months');
			$prev2_q_end = $current_q_start->modify('-3 months')->modify('-1 day');
			return [
				'from' => $prev2_q_start->format('Y-m-d'),
				'to' => $prev2_q_end->format('Y-m-d'),
			];
		case 'dieses_jahr':
			$year = (int) $now->format('Y');
			return [
				'from' => \sprintf('%04d-01-01', $year),
				'to' => \sprintf('%04d-12-31', $year),
			];
		case 'letztes_jahr':
			$year = ((int) $now->format('Y')) - 1;
			return [
				'from' => \sprintf('%04d-01-01', $year),
				'to' => \sprintf('%04d-12-31', $year),
			];
		case 'vorletztes_jahr':
			$year = ((int) $now->format('Y')) - 2;
			return [
				'from' => \sprintf('%04d-01-01', $year),
				'to' => \sprintf('%04d-12-31', $year),
			];
		default:
			return ['from' => '', 'to' => ''];
	}
}

function cmxbu_belege_export_requested_date_range(): array {
	$from = cmxbu_belege_export_normalize_date((string) ($_REQUEST['cmx_export_date_from'] ?? ''));
	$to   = cmxbu_belege_export_normalize_date((string) ($_REQUEST['cmx_export_date_to'] ?? ''));

	if ($from === '' || $to === '') {
		$preset = cmxbu_belege_export_requested_preset();
		$preset_range = cmxbu_belege_export_range_from_preset($preset);
		if ($from === '') $from = $preset_range['from'];
		if ($to === '') $to = $preset_range['to'];
	}

	if ($from !== '' && $to !== '' && $from > $to) {
		[$from, $to] = [$to, $from];
	}

	return ['from' => $from, 'to' => $to];
}

function cmxbu_belege_export_require_date_range_or_redirect(): array {
	$range = cmxbu_belege_export_requested_date_range();
	if ($range['from'] !== '' && $range['to'] !== '') return $range;

	$args = [
		'post_type' => 'belege',
		'cmx_export' => 1,
		'cmx_export_error' => 'missing_range',
		'ref' => cmxbu_belege_export_request_ref(),
		'cmx_export_range_preset' => cmxbu_belege_export_requested_preset(),
	];
	$requested_appendices = cmxbu_belege_export_requested_pdf_appendices();
	if (!empty($requested_appendices)) {
		$args['cmx_export_pdf_appendices'] = $requested_appendices;
	}
	if ($range['from'] !== '') $args['cmx_export_date_from'] = $range['from'];
	if ($range['to'] !== '') $args['cmx_export_date_to'] = $range['to'];

	$target = \add_query_arg($args, \admin_url('edit.php'));
	\wp_safe_redirect($target);
	exit;
}

function cmxbu_belege_export_verify_nonce(string $specific_action): bool {
	$nonce = (string) ($_REQUEST['_wpnonce'] ?? '');
	if ($nonce === '') return false;
	if (\wp_verify_nonce($nonce, 'cmx_export_belege_range')) return true;
	return \wp_verify_nonce($nonce, $specific_action);
}

function cmxbu_belege_export_post_date(int $post_id): string {
	$belegdatum = (string) \get_post_meta(
		$post_id,
		\defined(__NAMESPACE__.'\\CMX_BELEG_META_RNG_DATUM') ? CMX_BELEG_META_RNG_DATUM : '_cmx_beleg_rng_datum',
		true
	);
	if ($belegdatum === '') {
		$post = \get_post($post_id);
		if (!$post) return '';
		$belegdatum = \get_date_from_gmt((string) $post->post_date_gmt, 'Y-m-d');
		if ($belegdatum === '') {
			$belegdatum = \mysql2date('Y-m-d', (string) $post->post_date, false);
		}
	}
	return cmxbu_belege_export_normalize_date($belegdatum);
}

function cmxbu_belege_export_paid_date(int $post_id): string {
	$meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM')
		? CMX_BELEG_META_BEZAHLT_AM
		: '_cmx_beleg_bezahlt_am';
	$raw = (string) \get_post_meta($post_id, $meta_key, true);
	return cmxbu_belege_export_normalize_payment_date($raw);
}

function cmxbu_belege_export_partial_payment_dates(int $post_id): array {
	$meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_ANZAHLUNGEN')
		? CMX_BELEG_META_ANZAHLUNGEN
		: '_cmx_beleg_anzahlungen';
	$raw = \get_post_meta($post_id, $meta_key, true);
	if (empty($raw)) return [];

	if (\is_string($raw)) {
		$decoded = \json_decode($raw, true);
		if (\json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
			$raw = $decoded;
		} else {
			$maybe = @\maybe_unserialize($raw);
			$raw = \is_array($maybe) ? $maybe : [];
		}
	}
	if (!\is_array($raw)) return [];

	$dates = [];
	foreach ($raw as $row) {
		if (!\is_array($row)) continue;
		$datum = cmxbu_belege_export_normalize_payment_date((string) ($row['datum'] ?? ''));
		if ($datum === '') continue;
		$dates[$datum] = true;
	}

	return \array_keys($dates);
}

function cmxbu_belege_export_has_payment_date(int $post_id): bool {
	if (cmxbu_belege_export_paid_date($post_id) !== '') return true;
	$partial_dates = cmxbu_belege_export_partial_payment_dates($post_id);
	return !empty($partial_dates);
}

function cmxbu_belege_export_date_in_range(string $date_ymd, array $range): bool {
	$date_ymd = cmxbu_belege_export_normalize_payment_date($date_ymd);
	if ($date_ymd === '') return false;
	$from = (string) ($range['from'] ?? '');
	$to = (string) ($range['to'] ?? '');
	if ($from === '' || $to === '') return true;
	return ($date_ymd >= $from && $date_ymd <= $to);
}

function cmxbu_belege_export_has_payment_in_range(int $post_id, array $range): bool {
	$paid_date = cmxbu_belege_export_paid_date($post_id);
	if (cmxbu_belege_export_date_in_range($paid_date, $range)) {
		return true;
	}
	$partial_dates = cmxbu_belege_export_partial_payment_dates($post_id);
	foreach ($partial_dates as $partial_date) {
		if (cmxbu_belege_export_date_in_range((string) $partial_date, $range)) {
			return true;
		}
	}
	return false;
}

function cmxbu_belege_export_zip_copy_meta_key(): string {
	return '_cmx_belege_export_zip_copy';
}

function cmxbu_belege_export_zip_copy_token_option_key(string $token): string {
	return 'cmx_belege_zip_token_data_' . $token;
}

function cmxbu_belege_export_zip_copy_sanitize_token(string $token): string {
	$token = \trim($token);
	if ($token === '') {
		return '';
	}
	if (!\preg_match('/^[A-Za-z0-9]{16,64}$/', $token)) {
		return '';
	}
	return $token;
}

function cmxbu_belege_export_zip_copy_generate_token(): string {
	for ($i = 0; $i < 12; $i++) {
		$token = cmxbu_belege_export_zip_copy_sanitize_token((string) \wp_generate_password(24, false, false));
		if ($token === '') {
			continue;
		}
		if (\get_option(cmxbu_belege_export_zip_copy_token_option_key($token), null) === null) {
			return $token;
		}
	}

	return '';
}

function cmxbu_belege_export_zip_copy_share_url_for_token(string $token): string {
	$token = cmxbu_belege_export_zip_copy_sanitize_token($token);
	if ($token === '') {
		return '';
	}

	return \esc_url_raw((string) \add_query_arg('beleg_zip', $token, \home_url('/')));
}

function cmxbu_belege_export_zip_copy_token_data_get(string $token): array {
	$token = cmxbu_belege_export_zip_copy_sanitize_token($token);
	if ($token === '') {
		return [];
	}

	$raw = \get_option(cmxbu_belege_export_zip_copy_token_option_key($token), null);
	if (!\is_array($raw)) {
		return [];
	}

	$rel = \ltrim(\str_replace('\\', '/', (string) ($raw['rel'] ?? '')), '/');
	if ($rel === '' || !\str_starts_with($rel, 'misbuero/')) {
		return [];
	}

	$file_name = \sanitize_file_name((string) ($raw['file_name'] ?? ''));
	if ($file_name === '') {
		$file_name = (string) \basename($rel);
	}

	return [
		'token' => $token,
		'rel' => $rel,
		'file_name' => $file_name,
		'created' => (int) ($raw['created'] ?? 0),
		'user_id' => (int) ($raw['user_id'] ?? 0),
	];
}

function cmxbu_belege_export_zip_copy_token_data_store(
	string $token,
	string $rel,
	string $file_name,
	int $created = 0,
	int $user_id = 0
): bool {
	$token = cmxbu_belege_export_zip_copy_sanitize_token($token);
	$rel = \ltrim(\str_replace('\\', '/', $rel), '/');
	if ($token === '' || $rel === '' || !\str_starts_with($rel, 'misbuero/')) {
		return false;
	}

	$file_name = \sanitize_file_name($file_name);
	if ($file_name === '') {
		$file_name = (string) \basename($rel);
	}

	if ($created <= 0) {
		$created = (int) \current_time('timestamp');
	}

	return (bool) \update_option(
		cmxbu_belege_export_zip_copy_token_option_key($token),
		[
			'rel' => $rel,
			'file_name' => $file_name,
			'created' => $created,
			'user_id' => $user_id,
		],
		false
	);
}

function cmxbu_belege_export_zip_copy_token_data_delete(string $token): void {
	$token = cmxbu_belege_export_zip_copy_sanitize_token($token);
	if ($token === '') {
		return;
	}
	\delete_option(cmxbu_belege_export_zip_copy_token_option_key($token));
}

function cmxbu_belege_export_zip_copy_ensure_token(string $rel, string $file_name, int $created = 0, int $user_id = 0, string $preferred = ''): string {
	$rel = \ltrim(\str_replace('\\', '/', $rel), '/');
	if ($rel === '' || !\str_starts_with($rel, 'misbuero/')) {
		return '';
	}

	$file_name = \sanitize_file_name($file_name);
	if ($file_name === '') {
		$file_name = (string) \basename($rel);
	}
	if ($created <= 0) {
		$created = (int) \current_time('timestamp');
	}

	$preferred = cmxbu_belege_export_zip_copy_sanitize_token($preferred);
	if ($preferred !== '') {
		$existing = cmxbu_belege_export_zip_copy_token_data_get($preferred);
		if ((string) ($existing['rel'] ?? '') === $rel) {
			cmxbu_belege_export_zip_copy_token_data_store($preferred, $rel, $file_name, $created, $user_id);
			return $preferred;
		}
	}

	$token = cmxbu_belege_export_zip_copy_generate_token();
	if ($token === '') {
		return '';
	}

	if (!cmxbu_belege_export_zip_copy_token_data_store($token, $rel, $file_name, $created, $user_id)) {
		return '';
	}

	return $token;
}

function cmxbu_belege_export_zip_copy_storage_year(): int {
	$range = cmxbu_belege_export_requested_date_range();
	$candidates = [
		(string) ($range['to'] ?? ''),
		(string) ($range['from'] ?? ''),
	];
	foreach ($candidates as $date_ymd) {
		if (\preg_match('/^(\d{4})-\d{2}-\d{2}$/', $date_ymd, $m)) {
			$y = (int) $m[1];
			if ($y >= 1970 && $y <= 2100) {
				return $y;
			}
		}
	}

	$now_year = (int) \wp_date('Y');
	return ($now_year > 0) ? $now_year : (int) \date('Y');
}

function cmxbu_belege_export_zip_copy_upload_context(): array {
	$uploads = \wp_get_upload_dir();
	$basedir = \wp_normalize_path((string) ($uploads['basedir'] ?? ''));
	$baseurl = (string) ($uploads['baseurl'] ?? '');
	if ($basedir === '' || $baseurl === '') {
		return [
			'uploads_root' => '',
			'uploads_url_root' => '',
			'target_rel_dir' => '',
			'target_dir' => '',
		];
	}

	$uploads_root = \trailingslashit($basedir);
	$uploads_url_root = \trailingslashit($baseurl);
	$target_rel_dir = 'misbuero/archiv';
	return [
		'uploads_root' => $uploads_root,
		'uploads_url_root' => $uploads_url_root,
		'target_rel_dir' => $target_rel_dir,
		'target_dir' => $uploads_root . $target_rel_dir,
	];
}

function cmxbu_belege_export_zip_copy_rel_path_for_file(string $file_name): string {
	$file_name = \sanitize_file_name($file_name);
	if ($file_name === '') {
		return '';
	}
	$ctx = cmxbu_belege_export_zip_copy_upload_context();
	$target_rel_dir = \trim((string) ($ctx['target_rel_dir'] ?? ''), '/');
	if ($target_rel_dir === '') {
		return '';
	}
	return $target_rel_dir . '/' . $file_name;
}

function cmxbu_belege_export_zip_copy_rel_to_abs(string $rel): string {
	$rel = \ltrim(\str_replace('\\', '/', $rel), '/');
	if ($rel === '') {
		return '';
	}
	$ctx = cmxbu_belege_export_zip_copy_upload_context();
	$uploads_root = (string) ($ctx['uploads_root'] ?? '');
	if ($uploads_root === '') {
		return '';
	}
	$abs = \wp_normalize_path($uploads_root . $rel);
	if ($abs === '' || !\str_starts_with($abs, $uploads_root)) {
		return '';
	}
	return $abs;
}

function cmxbu_belege_export_zip_copy_rel_to_url(string $rel): string {
	$rel = \ltrim(\str_replace('\\', '/', $rel), '/');
	if ($rel === '') {
		return '';
	}
	$ctx = cmxbu_belege_export_zip_copy_upload_context();
	$uploads_url_root = (string) ($ctx['uploads_url_root'] ?? '');
	if ($uploads_url_root === '') {
		return '';
	}
	$encoded = \str_replace('%2F', '/', \rawurlencode($rel));
	return \esc_url_raw($uploads_url_root . $encoded);
}

function cmxbu_belege_export_zip_copy_fallback_current_range(): array {
	if (!\function_exists(__NAMESPACE__ . '\\cmxbu_belege_zip_download_filename')) {
		return [];
	}

	$file_name = \sanitize_file_name((string) cmxbu_belege_zip_download_filename());
	if ($file_name === '') {
		return [];
	}
	$rel = cmxbu_belege_export_zip_copy_rel_path_for_file($file_name);
	if ($rel === '') {
		return [];
	}
	$abs = cmxbu_belege_export_zip_copy_rel_to_abs($rel);
	if ($abs === '' || !\is_file($abs)) {
		return [];
	}

	return [
		'rel' => $rel,
		'abs' => $abs,
		'url' => '',
		'token' => '',
		'file_name' => (string) \basename($rel),
		'created' => (int) @\filemtime($abs),
	];
}

function cmxbu_belege_export_zip_copy_get(): array {
	$user = \wp_get_current_user();
	if (!$user instanceof \WP_User || !$user->exists()) {
		return [];
	}
	$user_id = (int) $user->ID;

	$meta_key = cmxbu_belege_export_zip_copy_meta_key();
	$raw = \get_user_meta((int) $user->ID, $meta_key, true);
	if (empty($raw)) {
		$raw = cmxbu_belege_export_zip_copy_fallback_current_range();
	}
	if (\is_string($raw)) {
		$decoded = \json_decode($raw, true);
		if (\json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
			$raw = $decoded;
		}
	}
	if (!\is_array($raw)) {
		\delete_user_meta((int) $user->ID, $meta_key);
		return [];
	}

	$rel = \ltrim(\str_replace('\\', '/', (string) ($raw['rel'] ?? '')), '/');
	if ($rel === '' || !\str_starts_with($rel, 'misbuero/')) {
		\delete_user_meta((int) $user->ID, $meta_key);
		return [];
	}
	$abs = cmxbu_belege_export_zip_copy_rel_to_abs($rel);
	if ($abs === '' || !\is_file($abs)) {
		\delete_user_meta((int) $user->ID, $meta_key);
		$stale_token = cmxbu_belege_export_zip_copy_sanitize_token((string) ($raw['token'] ?? ''));
		if ($stale_token !== '') {
			cmxbu_belege_export_zip_copy_token_data_delete($stale_token);
		}
		return [];
	}

	$file_name = \sanitize_file_name((string) ($raw['file_name'] ?? (string) \basename($rel)));
	if ($file_name === '') {
		$file_name = (string) \basename($rel);
	}
	$created = (int) ($raw['created'] ?? 0);
	if ($created <= 0) {
		$created = (int) @\filemtime($abs);
		if ($created <= 0) {
			$created = (int) \current_time('timestamp');
		}
	}

	$token = cmxbu_belege_export_zip_copy_sanitize_token((string) ($raw['token'] ?? ''));
	$token_data = ($token !== '') ? cmxbu_belege_export_zip_copy_token_data_get($token) : [];
	if ($token === '' || (string) ($token_data['rel'] ?? '') !== $rel) {
		$token = cmxbu_belege_export_zip_copy_ensure_token($rel, $file_name, $created, $user_id, $token);
	}

	$url = ($token !== '')
		? cmxbu_belege_export_zip_copy_share_url_for_token($token)
		: cmxbu_belege_export_zip_copy_rel_to_url($rel);

	$fresh_meta = [
		'rel' => $rel,
		'token' => $token,
		'file_name' => $file_name,
		'created' => $created,
	];
	if ($raw !== $fresh_meta) {
		\update_user_meta($user_id, $meta_key, $fresh_meta);
	}

	return [
		'rel' => $rel,
		'abs' => $abs,
		'url' => $url,
		'token' => $token,
		'file_name' => $file_name,
		'created' => $created,
	];
}

function cmxbu_belege_export_zip_copy_store_from_temp(string $source_abs, string $target_file_name): string {
	$source_abs = (string) $source_abs;
	if ($source_abs === '' || !\is_file($source_abs)) {
		return '';
	}

	$ctx = cmxbu_belege_export_zip_copy_upload_context();
	$target_dir = (string) ($ctx['target_dir'] ?? '');
	if ($target_dir === '') {
		return '';
	}
	if (!\is_dir($target_dir) && !\wp_mkdir_p($target_dir)) {
		return '';
	}

	$file_name = \sanitize_file_name($target_file_name);
	if ($file_name === '') {
		$file_name = 'misbuero_export.zip';
	}
	if (!\preg_match('/\.zip$/i', $file_name)) {
		$file_name .= '.zip';
	}
	$target_abs = \wp_normalize_path(\trailingslashit($target_dir) . $file_name);
	$target_root = \trailingslashit(\wp_normalize_path($target_dir));
	if ($target_abs === '' || !\str_starts_with($target_abs, $target_root)) {
		return '';
	}

	$previous = cmxbu_belege_export_zip_copy_get();
	$previous_abs = (string) ($previous['abs'] ?? '');
	if ($previous_abs !== '') {
		$previous_abs = \wp_normalize_path($previous_abs);
		if ($previous_abs !== '' && $previous_abs !== $target_abs && \is_file($previous_abs)) {
			@unlink($previous_abs);
		}
	}
	$previous_token = cmxbu_belege_export_zip_copy_sanitize_token((string) ($previous['token'] ?? ''));
	if ($previous_token !== '') {
		cmxbu_belege_export_zip_copy_token_data_delete($previous_token);
	}

	if (!@\copy($source_abs, $target_abs)) {
		return '';
	}

	$user = \wp_get_current_user();
	if ($user instanceof \WP_User && $user->exists()) {
		$user_id = (int) $user->ID;
		$rel = cmxbu_belege_export_zip_copy_rel_path_for_file($file_name);
		if ($rel === '') {
			return '';
		}
		$created = (int) \current_time('timestamp');
		$token = cmxbu_belege_export_zip_copy_ensure_token($rel, $file_name, $created, $user_id);
		$share_url = ($token !== '')
			? cmxbu_belege_export_zip_copy_share_url_for_token($token)
			: cmxbu_belege_export_zip_copy_rel_to_url($rel);

		\update_user_meta(
			$user_id,
			cmxbu_belege_export_zip_copy_meta_key(),
			[
				'rel' => $rel,
				'token' => $token,
				'file_name' => $file_name,
				'created' => $created,
			]
		);
		return $share_url;
	}

	return '';
}

function cmxbu_belege_export_zip_copy_delete_for_current_user(bool $delete_file = true): void {
	$user = \wp_get_current_user();
	if (!$user instanceof \WP_User || !$user->exists()) {
		return;
	}

	$meta_key = cmxbu_belege_export_zip_copy_meta_key();
	$current = cmxbu_belege_export_zip_copy_get();
	if ($delete_file && !empty($current['abs']) && \is_file((string) $current['abs'])) {
		@unlink((string) $current['abs']);
	}
	$token = cmxbu_belege_export_zip_copy_sanitize_token((string) ($current['token'] ?? ''));
	if ($token !== '') {
		cmxbu_belege_export_zip_copy_token_data_delete($token);
	}
	\delete_user_meta((int) $user->ID, $meta_key);
}

function cmxbu_belege_export_normalize_trustee_label(string $label): string {
	$label = \trim($label);
	if ($label === '') {
		return '';
	}

	$label = \html_entity_decode($label, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
	$label = \wp_strip_all_tags($label);
	$label = \str_replace(["\u{00A0}", '–', '—', '−'], [' ', '-', '-', '-'], $label);
	$label = \str_replace(['&#8211;', '&#8212;', '&ndash;', '&mdash;'], '-', $label);
	$label = (string) \preg_replace('/\s+/u', ' ', $label);
	return \trim($label);
}

function cmxbu_belege_export_trustee_contacts(): array {
	$taxes = [];
	if (\function_exists(__NAMESPACE__ . '\\cmxbu_contact_category_taxonomies')) {
		$taxes = (array) cmxbu_contact_category_taxonomies();
	}
	if (empty($taxes)) {
		$fallback_taxes = ['kontakte_kategorien', 'kontakte_kategorie', 'kundenkategorie', 'kontakt_kategorie'];
		foreach ($fallback_taxes as $tax) {
			if (\taxonomy_exists($tax)) {
				$taxes[] = $tax;
			}
		}
	}
	$taxes = \array_values(\array_unique(\array_filter(\array_map('strval', $taxes))));
	if (empty($taxes)) {
		return [];
	}

	$queries = [
		['field' => 'slug', 'terms' => ['treuhaender', 'treuhander', 'treuhänder']],
		['field' => 'name', 'terms' => ['Treuhänder', 'Treuhaender', 'Treuhander']],
	];

	$posts_by_id = [];
	foreach ($taxes as $tax) {
		foreach ($queries as $query) {
			$posts = \get_posts([
				'post_type' => ['kontakte', 'kontakt', 'contact'],
				'post_status' => ['publish', 'private'],
				'posts_per_page' => 300,
				'orderby' => 'title',
				'order' => 'ASC',
				'no_found_rows' => true,
				'suppress_filters' => true,
				'tax_query' => [[
					'taxonomy' => $tax,
					'field' => (string) $query['field'],
					'terms' => (array) $query['terms'],
				]],
			]);
			foreach ((array) $posts as $post) {
				if ($post instanceof \WP_Post) {
					$posts_by_id[(int) $post->ID] = $post;
				}
			}
		}
	}

	if (empty($posts_by_id)) {
		return [];
	}

	$items = [];
	foreach ($posts_by_id as $post) {
		$post_id = (int) $post->ID;
		if ($post_id <= 0) {
			continue;
		}

		$email = '';
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_contact_primary_email')) {
			$email = (string) cmxbu_get_contact_primary_email($post_id);
		}
		if ($email === '' && \function_exists(__NAMESPACE__ . '\\cmxbu_collect_contact_reply_emails')) {
			$emails = (array) cmxbu_collect_contact_reply_emails($post_id);
			$email = (string) ($emails[0] ?? '');
		}
		$email = \sanitize_email($email);
		if (!\is_email($email)) {
			continue;
		}

		$label = cmxbu_belege_export_normalize_trustee_label((string) \get_the_title($post_id));
		if ($label === '') {
			$vorname = \trim((string) \get_post_meta($post_id, '_cmx_kontakte_vorname', true));
			$nachname = \trim((string) \get_post_meta($post_id, '_cmx_kontakte_nachname', true));
			$label = cmxbu_belege_export_normalize_trustee_label(\trim($vorname . ' ' . $nachname));
		}
		if ($label === '') {
			$label = 'Kontakt #' . $post_id;
		}

		$items[] = [
			'id' => $post_id,
			'label' => $label,
			'email' => $email,
			'created_ts' => (int) (\strtotime((string) $post->post_date_gmt) ?: \strtotime((string) $post->post_date) ?: 0),
			'modified_ts' => (int) (\strtotime((string) $post->post_modified_gmt) ?: \strtotime((string) $post->post_modified) ?: 0),
		];
	}

	if (empty($items)) {
		return [];
	}

	\usort($items, static function (array $a, array $b): int {
		$la = (string) ($a['label'] ?? '');
		$lb = (string) ($b['label'] ?? '');
		$cmp = \strnatcasecmp($la, $lb);
		if ($cmp !== 0) {
			return $cmp;
		}
		return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
	});

	return $items;
}

function cmxbu_belege_export_trustee_default_id(array $contacts): int {
	$best_id = 0;
	$best_modified_ts = 0;
	$best_created_ts = 0;

	foreach ($contacts as $contact) {
		$id = (int) ($contact['id'] ?? 0);
		if ($id <= 0) {
			continue;
		}
		$created_ts = (int) ($contact['created_ts'] ?? 0);
		$modified_ts = (int) ($contact['modified_ts'] ?? 0);

		if (
			$modified_ts > $best_modified_ts
			|| ($modified_ts === $best_modified_ts && $created_ts > $best_created_ts)
			|| ($modified_ts === $best_modified_ts && $created_ts === $best_created_ts && $id > $best_id)
		) {
			$best_id = $id;
			$best_modified_ts = $modified_ts;
			$best_created_ts = $created_ts;
		}
	}

	if ($best_id > 0) {
		return $best_id;
	}

	foreach ($contacts as $contact) {
		$id = (int) ($contact['id'] ?? 0);
		if ($id > 0) {
			return $id;
		}
	}

	return 0;
}

function cmxbu_belege_export_trustee_options_html(array $contacts, int $selected_id = 0): string {
	$html = '<option value="">Treuhänder auswählen</option>';
	if (empty($contacts)) {
		$html .= '<option value="" disabled>Keine Treuhänder gefunden</option>';
		return $html;
	}
	foreach ($contacts as $contact) {
		$id = (int) ($contact['id'] ?? 0);
		if ($id <= 0) {
			continue;
		}
		$label = cmxbu_belege_export_normalize_trustee_label((string) ($contact['label'] ?? ''));
		$email = \sanitize_email((string) ($contact['email'] ?? ''));
		if ($label === '') {
			$label = 'Kontakt #' . $id;
		}
		$option_text = $label . ($email !== '' ? ' (' . $email . ')' : '');
		$html .= '<option value="' . \esc_attr((string) $id) . '"' . \selected($selected_id, $id, false) . '>' . \esc_html($option_text) . '</option>';
	}
	return $html;
}

function cmxbu_handle_belege_zip_download(): void {
	if (empty($_GET['beleg_zip'])) {
		return;
	}

	$token = cmxbu_belege_export_zip_copy_sanitize_token((string) $_GET['beleg_zip']);
	if ($token === '') {
		\wp_die('Ungültiger Link.');
	}

	$data = cmxbu_belege_export_zip_copy_token_data_get($token);
	if ($data === []) {
		\wp_die('ZIP-Link nicht gefunden oder abgelaufen.');
	}

	$rel = (string) ($data['rel'] ?? '');
	$abs = cmxbu_belege_export_zip_copy_rel_to_abs($rel);
	if ($abs === '' || !\is_file($abs)) {
		cmxbu_belege_export_zip_copy_token_data_delete($token);
		\wp_die('ZIP-Datei nicht gefunden.');
	}

	$file_name = \sanitize_file_name((string) ($data['file_name'] ?? ''));
	if ($file_name === '') {
		$file_name = (string) \basename($rel);
	}
	if (!\preg_match('/\.zip$/i', $file_name)) {
		$file_name .= '.zip';
	}

	while (\ob_get_level()) {
		\ob_end_clean();
	}

	\nocache_headers();
	header('Content-Type: application/zip');
	header('X-Content-Type-Options: nosniff');
	header('Content-Disposition: attachment; filename="' . $file_name . '"');
	header('Content-Length: ' . (string) \filesize($abs));

	\readfile($abs);
	exit;
}
\add_action('template_redirect', __NAMESPACE__ . '\\cmxbu_handle_belege_zip_download');

function cmxbu_belege_export_zip_copy_delete_url(string $ref = '', ?array $range = null, string $preset = ''): string {
	if ($ref === '') {
		$ref = cmxbu_belege_export_request_ref();
	}
	if ($preset === '') {
		$preset = cmxbu_belege_export_requested_preset();
	}
	if ($range === null) {
		$range = cmxbu_belege_export_requested_date_range();
	}

	$delete_args = [
		'action' => 'cmx_export_belege_zip_copy_delete',
		'ref' => $ref,
		'cmx_export_range_preset' => $preset,
		'cmx_export_date_from' => (string) ($range['from'] ?? ''),
		'cmx_export_date_to' => (string) ($range['to'] ?? ''),
		'_wpnonce' => \wp_create_nonce('cmx_export_belege_zip_copy_delete'),
	];

	return (string) \add_query_arg($delete_args, \admin_url('admin-post.php'));
}

\add_action('admin_post_cmx_export_belege_zip_copy_delete', function (): void {
	if (!\current_user_can('edit_posts')) {
		\wp_die('Keine Berechtigung.');
	}
	if (!isset($_REQUEST['_wpnonce']) || !\wp_verify_nonce((string) $_REQUEST['_wpnonce'], 'cmx_export_belege_zip_copy_delete')) {
		\wp_die('Ungültige Anfrage.');
	}

	cmxbu_belege_export_zip_copy_delete_for_current_user(true);

	$args = [
		'post_type' => 'belege',
		'cmx_export' => 1,
		'cmx_export_zip_deleted' => 1,
		'ref' => cmxbu_belege_export_request_ref(),
		'cmx_export_range_preset' => cmxbu_belege_export_requested_preset(),
	];
	$range = cmxbu_belege_export_requested_date_range();
	if ((string) ($range['from'] ?? '') !== '') {
		$args['cmx_export_date_from'] = (string) $range['from'];
	}
	if ((string) ($range['to'] ?? '') !== '') {
		$args['cmx_export_date_to'] = (string) $range['to'];
	}

	$target = \add_query_arg($args, \admin_url('edit.php'));
	\wp_safe_redirect($target);
	exit;
});

\add_action('wp_ajax_cmx_export_belege_zip_copy_delete_ajax', function (): void {
	if (!\current_user_can('edit_posts')) {
		\wp_send_json_error(['message' => 'forbidden'], 403);
	}
	if (!isset($_REQUEST['_wpnonce']) || !\wp_verify_nonce((string) $_REQUEST['_wpnonce'], 'cmx_export_belege_zip_copy_delete')) {
		\wp_send_json_error(['message' => 'bad_nonce'], 403);
	}

	cmxbu_belege_export_zip_copy_delete_for_current_user(true);
	\wp_send_json_success(['deleted' => true]);
});

\add_action('wp_ajax_cmx_export_belege_zip_share_send_ajax', function (): void {
	if (!\current_user_can('edit_posts')) {
		\wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
	}
	if (!isset($_REQUEST['_wpnonce']) || !\wp_verify_nonce((string) $_REQUEST['_wpnonce'], 'cmx_export_belege_zip_share_send')) {
		\wp_send_json_error(['message' => 'Ungültige Anfrage.'], 403);
	}

	$trustees = cmxbu_belege_export_trustee_contacts();
	$kontakt_id = isset($_REQUEST['kontakt_id']) ? (int) $_REQUEST['kontakt_id'] : 0;
	if ($kontakt_id <= 0) {
		$kontakt_id = cmxbu_belege_export_trustee_default_id($trustees);
		if ($kontakt_id <= 0) {
			\wp_send_json_error(['message' => 'Bitte zuerst einen Treuhänder auswählen.'], 400);
		}
	}

	$recipient = null;
	foreach ($trustees as $entry) {
		if ((int) ($entry['id'] ?? 0) === $kontakt_id) {
			$recipient = $entry;
			break;
		}
	}
	if (!\is_array($recipient)) {
		\wp_send_json_error(['message' => 'Ausgewählter Treuhänder ist ungültig.'], 400);
	}

	$to = \sanitize_email((string) ($recipient['email'] ?? ''));
	if (!\is_email($to)) {
		\wp_send_json_error(['message' => 'Beim Treuhänder ist keine gültige E-Mail hinterlegt.'], 400);
	}

	$zip_copy = cmxbu_belege_export_zip_copy_get();
	$share_url = (string) ($zip_copy['url'] ?? '');
	if ($share_url === '') {
		\wp_send_json_error(['message' => 'Es ist aktuell kein ZIP-Link verfügbar.'], 400);
	}

	$subject = 'ZIP-Export Milchbüchli';
	$message = "Guten Tag,\n\nhier ist der ZIP-Download-Link:\n" . $share_url;
	if (\function_exists(__NAMESPACE__ . '\\cmx_email_agb_footer_text')) {
		$agb_footer_text = \trim((string) cmx_email_agb_footer_text());
		if ($agb_footer_text !== '') {
			$message .= "\n\n" . $agb_footer_text;
		}
	}
	if (\function_exists(__NAMESPACE__ . '\\cmx_powered_by_enabled') && cmx_powered_by_enabled()) {
		$message .= "\n\nErstellt mit MisBüro (https://misbuero.ch/) – der einfachen Bürosoftware für Selbständige in der Schweiz.";
	}

	$headers = ['Content-Type: text/plain; charset=UTF-8'];
	$had_sender_override = \array_key_exists('cmx_force_current_user_mail_sender', $GLOBALS);
	$previous_sender_override = $had_sender_override ? $GLOBALS['cmx_force_current_user_mail_sender'] : null;
	$had_mail_context = \array_key_exists('cmx_mail_context', $GLOBALS);
	$previous_mail_context = $had_mail_context ? $GLOBALS['cmx_mail_context'] : null;
	$GLOBALS['cmx_force_current_user_mail_sender'] = true;
	$GLOBALS['cmx_mail_context'] = 'beleg_export';
	try {
		$sent = \wp_mail($to, $subject, $message, $headers);
	} finally {
		if ($had_sender_override) {
			$GLOBALS['cmx_force_current_user_mail_sender'] = $previous_sender_override;
		} else {
			unset($GLOBALS['cmx_force_current_user_mail_sender']);
		}
		if ($had_mail_context) {
			$GLOBALS['cmx_mail_context'] = $previous_mail_context;
		} else {
			unset($GLOBALS['cmx_mail_context']);
		}
	}

	if (!$sent) {
		\wp_send_json_error(['message' => 'E-Mail konnte nicht gesendet werden.'], 500);
	}

	\wp_send_json_success([
		'to' => $to,
		'recipient_label' => (string) ($recipient['label'] ?? ''),
	]);
});

\add_action('wp_ajax_cmx_export_belege_list_ajax', function (): void {
	if (!\current_user_can('edit_posts')) {
		\wp_send_json_error(['message' => 'forbidden'], 403);
	}
	if (!cmxbu_belege_export_verify_nonce('cmx_export_belege_list')) {
		\wp_send_json_error(['message' => 'bad_nonce'], 403);
	}

	$range = cmxbu_belege_export_requested_date_range();
	if ((string) ($range['from'] ?? '') === '' || (string) ($range['to'] ?? '') === '') {
		\wp_send_json_error(['message' => 'missing_range'], 400);
	}
	if (!\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_build_zip_file_from_ids')) {
		\wp_send_json_error(['message' => 'zip_builder_missing'], 500);
	}

	$post_ids = cmxbu_belege_export_collect_ids();
	$tmp_zip = \wp_tempnam('cmx-belege-export-zip-ajax');
	if (!\is_string($tmp_zip) || $tmp_zip === '') {
		\wp_send_json_error(['message' => 'tmp_failed'], 500);
	}

	$build_noise = '';
	\ob_start();
	$built = cmxbu_belege_export_build_zip_file_from_ids($post_ids, $tmp_zip);
	$build_noise = (string) \ob_get_clean();
	if (!$built) {
		@unlink($tmp_zip);
		\wp_send_json_error(['message' => 'zip_build_failed'], 500);
	}

	$download_name = \function_exists(__NAMESPACE__ . '\\cmxbu_belege_zip_download_filename')
		? (string) cmxbu_belege_zip_download_filename()
		: (string) cmxbu_belege_export_filename('zip');

	$store_noise = '';
	\ob_start();
	$share_url = \function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_zip_copy_store_from_temp')
		? (string) cmxbu_belege_export_zip_copy_store_from_temp($tmp_zip, $download_name)
		: '';
	$store_noise = (string) \ob_get_clean();
	@unlink($tmp_zip);

	if ($share_url === '') {
		\wp_send_json_error(['message' => 'zip_copy_store_failed'], 500);
	}

	$preset = cmxbu_belege_export_requested_preset();
	$ref = cmxbu_belege_export_request_ref();
	$delete_url = cmxbu_belege_export_zip_copy_delete_url($ref, $range, $preset);
	$delete_nonce = (string) \wp_create_nonce('cmx_export_belege_zip_copy_delete');
	$send_nonce = (string) \wp_create_nonce('cmx_export_belege_zip_share_send');
	$trustees = cmxbu_belege_export_trustee_contacts();
	$default_trustee_id = cmxbu_belege_export_trustee_default_id($trustees);

	\wp_send_json_success([
		'share_url' => $share_url,
		'delete_url' => $delete_url,
		'delete_nonce' => $delete_nonce,
		'send_nonce' => $send_nonce,
		'trustees' => $trustees,
		'default_trustee_id' => $default_trustee_id,
		'download_url' => $share_url,
		'noise' => ($build_noise !== '' || $store_noise !== ''),
	]);
});

/* ===== Link „Milchbüechli“ in der Belege-Listenansicht ===== */
\add_filter('views_edit-belege', function(array $views){
	if (!\current_user_can('edit_posts')) return $views;

	$url = \add_query_arg([
		'post_type'   => 'belege',
		'cmx_export'  => 1,
		'ref'         => cmxbu_belege_export_current_list_ref(),
	], \admin_url('edit.php'));
	$is_current = !empty($_GET['cmx_export']);
	$links = '<a href="' . \esc_url($url) . '"' . ($is_current ? ' class="current" aria-current="page"' : '') . '>Milchbüechli</a>';

	if (\function_exists(__NAMESPACE__ . '\\cmxbel_view_insert_after')) {
		return cmxbel_view_insert_after($views, 'cmx_deckungsbeitrag', 'cmx_milchbueechli_belege', $links);
	}

	$new = [];
	$inserted = false;
	foreach ($views as $key => $html) {
		$new[$key] = $html;
		if ($key === 'cmx_deckungsbeitrag' && !$inserted) {
			$new['cmx_milchbueechli_belege'] = $links;
			$inserted = true;
		}
	}
	if (!$inserted) {
		$new['cmx_milchbueechli_belege'] = $links;
	}
	return $new;
}, 40);

/* ===== Export-Formular (Datum von/bis) in der Listenansicht ===== */
\add_action('all_admin_notices', function () {
	global $typenow;
	if ($typenow !== 'belege' || empty($_GET['cmx_export'])) return;
	if (!\current_user_can('edit_posts')) return;

	$range = cmxbu_belege_export_requested_date_range();
	$preset = cmxbu_belege_export_requested_preset();
	$presets = cmxbu_belege_export_presets();
	$ref = cmxbu_belege_export_request_ref();
	$cancel_url = cmxbu_belege_export_normalize_ref($ref);
	$has_error = !empty($_GET['cmx_export_error']);
	$zip_deleted = !empty($_GET['cmx_export_zip_deleted']);
	$zip_copy = cmxbu_belege_export_zip_copy_get();
	$zip_share_url = (string) ($zip_copy['url'] ?? '');
	$zip_delete_url = ($zip_share_url !== '') ? cmxbu_belege_export_zip_copy_delete_url($ref, $range, $preset) : '';
	$zip_delete_nonce = ($zip_share_url !== '') ? (string) \wp_create_nonce('cmx_export_belege_zip_copy_delete') : '';
	$zip_send_nonce = ($zip_share_url !== '') ? (string) \wp_create_nonce('cmx_export_belege_zip_share_send') : '';
	$zip_trustees = cmxbu_belege_export_trustee_contacts();
	$zip_trustee_selected_id = cmxbu_belege_export_trustee_default_id($zip_trustees);
	$zip_trustee_options_html = cmxbu_belege_export_trustee_options_html($zip_trustees, $zip_trustee_selected_id);
	$pdf_appendix_options = cmxbu_belege_export_pdf_appendix_options();
	$pdf_appendix_selected = \array_fill_keys(cmxbu_belege_export_requested_pdf_appendices(), true);
	?>
	<div class="notice notice-info" style="padding:20px;margin-top:15px;">
		<h2>Milchbüechli exportieren</h2>
		<p><code>ZIP</code> exportiert Milchbüechli, Belege und zugeordnete Dokumente und erstellt einen Link zum Teilen mit Ihrem Treuhänder. <code>PDF</code> und <code>CSV</code> exportieren nur das Milchbüechli.</p>
		<?php if ($has_error): ?>
			<p style="color:#b32d2e;"><strong>Bitte Datum von und Datum bis ausfüllen.</strong></p>
		<?php endif; ?>
		<?php if ($zip_deleted): ?>
			<p style="color:#007017;"><strong>ZIP-Link wurde gelöscht und ist nicht mehr gültig.</strong></p>
		<?php endif; ?>

		<form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>" id="cmx-belege-export-form">
			<?php \wp_nonce_field('cmx_export_belege_range'); ?>
			<input type="hidden" name="ref" value="<?php echo \esc_attr($ref); ?>">
			<input type="hidden" id="cmx-export-submit-action" value="">

				<div style="margin-top:1em;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
					<div style="display:flex;align-items:center;gap:8px;">
						<label for="cmx_export_range_preset" style="font-weight:600;">Zeitraum</label>
						<select id="cmx_export_range_preset" name="cmx_export_range_preset">
							<?php foreach ($presets as $value => $label): ?>
								<option value="<?php echo \esc_attr($value); ?>" <?php selected($preset, $value); ?>><?php echo \esc_html($label); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div style="display:flex;align-items:center;gap:8px;">
						<label for="cmx_export_date_from" style="font-weight:600;">Datum von</label>
						<input type="date" id="cmx_export_date_from" name="cmx_export_date_from" value="<?php echo \esc_attr($range['from']); ?>" required>
					</div>
					<div style="display:flex;align-items:center;gap:8px;">
						<label for="cmx_export_date_to" style="font-weight:600;">Datum bis</label>
						<input type="date" id="cmx_export_date_to" name="cmx_export_date_to" value="<?php echo \esc_attr($range['to']); ?>" required>
					</div>
				</div>

				<div style="margin-top:12px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
					<!-- <span style="font-weight:600;">Zusätzliche Listen im PDF:</span> -->
					<!-- <span style="color:#555;">Übersicht ist immer enthalten.</span> -->
					<span style="color:#555;">Zusätzliche Listen</span>
					<?php foreach ($pdf_appendix_options as $appendix_key => $appendix_label): ?>
						<label style="display:inline-flex;align-items:center;gap:6px;">
							<input
								type="checkbox"
								name="cmx_export_pdf_appendices[]"
								value="<?php echo \esc_attr($appendix_key); ?>"
								<?php checked(isset($pdf_appendix_selected[$appendix_key])); ?>
							>
							<span><?php echo \esc_html($appendix_label); ?></span>
						</label>
					<?php endforeach; ?>
				</div>

			<p class="submit">
				<p>Exportieren als</p>
				<button type="submit" id="cmx-export-belege-zip-btn" name="action" value="cmx_export_belege_list" class="button button-primary">ZIP</button>
				<button type="submit" id="cmx-export-belege-pdf-btn" name="action" value="cmx_export_belege_list_pdf" class="button">PDF</button>
				<button type="submit" id="cmx-export-belege-csv-btn" name="action" value="cmx_export_belege_list_csv" class="button">CSV</button>
				<a href="<?php echo \esc_url($cancel_url); ?>" class="button">Abbrechen</a>
			</p>

				<div id="cmx-export-zip-share-shell">
					<?php if ($zip_share_url !== ''): ?>
						<div id="cmx-export-zip-share" style="margin-top:8px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
							<select id="cmx-export-zip-share-recipient" style="min-width:260px;max-width:100%;">
								<?php echo $zip_trustee_options_html; ?>
							</select>
							<button type="button" id="cmx-export-zip-share-send" class="button button-secondary" title="ZIP-Link per Mail versenden" data-send-nonce="<?php echo \esc_attr($zip_send_nonce); ?>">
								<span class="dashicons dashicons-email" style="margin-top:3px;"></span>
							</button>
							<input type="text" id="cmx-export-zip-share-link" readonly value="<?php echo \esc_attr($zip_share_url); ?>" style="min-width:460px;max-width:100%;width:58ch;">
							<button type="button" id="cmx-export-zip-share-copy" class="button button-secondary" title="Link kopieren">
								<span class="dashicons dashicons-clipboard" style="margin-top:4px;"></span>
							</button>
							<button
								type="button"
								id="cmx-export-zip-share-delete"
								class="button button-link-delete"
								title="ZIP-Link löschen"
								data-delete-url="<?php echo \esc_attr($zip_delete_url); ?>"
								data-delete-nonce="<?php echo \esc_attr($zip_delete_nonce); ?>"
							>
								<span class="dashicons dashicons-trash" style="margin-top:4px;"></span>
							</button>
						</div>
					<p id="cmx-export-zip-share-status" class="description" style="margin-top:6px;">Der ZIP-Link wird automatisch in die Zwischenablage kopiert.</p>
				<?php endif; ?>
			</div>
		</form>
	</div>
		<script>
			(function(){
				var form = document.getElementById('cmx-belege-export-form');
				if (!form) return;
				var preset = document.getElementById('cmx_export_range_preset');
				var fromField = document.getElementById('cmx_export_date_from');
				var toField = document.getElementById('cmx_export_date_to');
				var actionMemoryField = document.getElementById('cmx-export-submit-action');
				var submitButtons = form.querySelectorAll('button[type="submit"][name="action"]');
				var zipButton = document.getElementById('cmx-export-belege-zip-btn');
				var zipRequestActive = false;
				var initialTrustees = <?php echo \wp_json_encode(\array_values($zip_trustees)); ?>;
				var initialSendNonce = <?php echo \wp_json_encode((string) $zip_send_nonce); ?>;
				var initialSelectedTrusteeId = <?php echo \wp_json_encode((int) $zip_trustee_selected_id); ?>;

		function pad2(n){ return (n < 10 ? '0' : '') + n; }
		function ymd(date){
			return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
		}
		function applyPreset(value){
			if (!fromField || !toField) return;
			var now = new Date();
			var from = '';
			var to = '';
			var y = now.getFullYear();
			var m = now.getMonth();

			switch (value) {
				case 'heute':
					from = ymd(now);
					to = ymd(now);
					break;
				case 'diesen_monat':
					from = ymd(new Date(y, m, 1));
					to = ymd(new Date(y, m + 1, 0));
					break;
				case 'letzten_monat':
					from = ymd(new Date(y, m - 1, 1));
					to = ymd(new Date(y, m, 0));
					break;
				case 'vorletzten_monat':
					from = ymd(new Date(y, m - 2, 1));
					to = ymd(new Date(y, m - 1, 0));
					break;
				case 'dieses_quartal':
					var qStartMonth = Math.floor(m / 3) * 3;
					from = ymd(new Date(y, qStartMonth, 1));
					to = ymd(new Date(y, qStartMonth + 3, 0));
					break;
				case 'letztes_quartal':
					var thisQStartMonth = Math.floor(m / 3) * 3;
					var thisQStart = new Date(y, thisQStartMonth, 1);
					var lastQStart = new Date(thisQStart.getFullYear(), thisQStart.getMonth() - 3, 1);
					var lastQEnd = new Date(thisQStart.getFullYear(), thisQStart.getMonth(), 0);
					from = ymd(lastQStart);
					to = ymd(lastQEnd);
					break;
				case 'vorletztes_quartal':
					var thisQStartMonth2 = Math.floor(m / 3) * 3;
					var thisQStart2 = new Date(y, thisQStartMonth2, 1);
					var prev2QStart = new Date(thisQStart2.getFullYear(), thisQStart2.getMonth() - 6, 1);
					var prev2QEnd = new Date(thisQStart2.getFullYear(), thisQStart2.getMonth() - 3, 0);
					from = ymd(prev2QStart);
					to = ymd(prev2QEnd);
					break;
				case 'dieses_jahr':
					from = y + '-01-01';
					to = y + '-12-31';
					break;
				case 'letztes_jahr':
					from = (y - 1) + '-01-01';
					to = (y - 1) + '-12-31';
					break;
				case 'vorletztes_jahr':
					from = (y - 2) + '-01-01';
					to = (y - 2) + '-12-31';
					break;
				default:
					return;
			}
			if (from) fromField.value = from;
			if (to) toField.value = to;
		}

		if (preset) {
			preset.addEventListener('change', function () {
				if (preset.value === 'benutzerdefiniert') return;
				applyPreset(preset.value);
			});
		}

		function markCustomIfManual(){
			if (!preset) return;
			if (preset.value !== 'benutzerdefiniert') {
				preset.value = 'benutzerdefiniert';
			}
		}
		if (fromField) fromField.addEventListener('change', markCustomIfManual);
		if (toField) toField.addEventListener('change', markCustomIfManual);

		if (preset && preset.value !== 'benutzerdefiniert' && ((!fromField || !fromField.value) || (!toField || !toField.value))) {
			applyPreset(preset.value);
		}

			function syncSelectedPostsIntoForm(){
				var stale = form.querySelectorAll('input[data-cmx-selected="1"]');
				for (var i = 0; i < stale.length; i++) stale[i].remove();

			var checked = document.querySelectorAll('#the-list input[name="post[]"]:checked');
			for (var j = 0; j < checked.length; j++) {
				var hid = document.createElement('input');
				hid.type = 'hidden';
				hid.name = 'post[]';
				hid.value = checked[j].value;
				hid.setAttribute('data-cmx-selected', '1');
					form.appendChild(hid);
				}
			}
			function rememberSubmitAction(value){
				var actionValue = String(value || '').trim();
				if (actionMemoryField) actionMemoryField.value = actionValue;
				return actionValue;
			}
			function detectSubmitAction(ev){
				var actionValue = '';
				if (ev && ev.submitter && ev.submitter.name === 'action') {
					actionValue = String(ev.submitter.value || '').trim();
				}
				if (!actionValue && actionMemoryField && actionMemoryField.value) {
					actionValue = String(actionMemoryField.value || '').trim();
				}
				if (!actionValue) {
					var active = document.activeElement;
					if (active && active.name === 'action') {
						actionValue = String(active.value || '').trim();
					}
				}
				return actionValue;
			}

			function setZipStatus(msg){
				var status = document.getElementById('cmx-export-zip-share-status');
				if (status) status.textContent = msg;
			}
		function copyFallback(text){
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.setAttribute('readonly', 'readonly');
			ta.style.position = 'absolute';
			ta.style.left = '-9999px';
			document.body.appendChild(ta);
			ta.select();
			var ok = false;
			try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
			document.body.removeChild(ta);
			return ok;
		}
			function copyZipLink(isAuto){
				var input = document.getElementById('cmx-export-zip-share-link');
				if (!input || !input.value) return Promise.resolve(false);
			var text = input.value;
			if (navigator.clipboard && navigator.clipboard.writeText) {
				return navigator.clipboard.writeText(text).then(function(){
					setZipStatus(isAuto ? 'Link automatisch in Zwischenablage kopiert.' : 'Link wurde in Zwischenablage kopiert.');
					return true;
				}).catch(function(){
					var ok = copyFallback(text);
					if (ok) {
						setZipStatus(isAuto ? 'Link automatisch in Zwischenablage kopiert.' : 'Link wurde in Zwischenablage kopiert.');
					} else {
						setZipStatus('Link konnte nicht kopiert werden.');
					}
					return ok;
				});
			}
			var ok = copyFallback(text);
			if (ok) {
				setZipStatus(isAuto ? 'Link automatisch in Zwischenablage kopiert.' : 'Link wurde in Zwischenablage kopiert.');
			} else {
				setZipStatus('Link konnte nicht kopiert werden.');
				}
				return Promise.resolve(ok);
			}
			function getSelectedTrusteeId(){
				var select = document.getElementById('cmx-export-zip-share-recipient');
				if (!select) return '';
				return String(select.value || '').trim();
			}
			function runZipSendAjax(triggerButton){
				if (!triggerButton || zipRequestActive) return;
				if (typeof ajaxurl === 'undefined' || !ajaxurl) {
					setZipStatus('Mailversand ist nicht verfügbar.');
					return;
				}

				var kontaktId = getSelectedTrusteeId();
				if (!kontaktId) {
					setZipStatus('Bitte zuerst einen Treuhänder auswählen.');
					return;
				}

				var nonce = String(triggerButton.getAttribute('data-send-nonce') || '').trim();
				if (!nonce) {
					setZipStatus('Mailversand ist aktuell nicht möglich.');
					return;
				}

				zipRequestActive = true;
				triggerButton.disabled = true;
				setZipStatus('E-Mail wird gesendet ...');

				var fd = new FormData();
				fd.set('action', 'cmx_export_belege_zip_share_send_ajax');
				fd.set('_wpnonce', nonce);
				fd.set('kontakt_id', kontaktId);

				fetch(ajaxurl, {
					method: 'POST',
					credentials: 'same-origin',
					body: fd
				})
				.then(function(resp){ return resp.text(); })
				.then(function(text){
					var payload = null;
					var jsonText = String(text || '');
					var payloadStart = jsonText.indexOf('{"success"');
					if (payloadStart === -1) {
						payloadStart = jsonText.indexOf('{\"success\"');
					}
					if (payloadStart === -1) {
						payloadStart = jsonText.indexOf('{');
					}
					if (payloadStart > 0) {
						jsonText = jsonText.slice(payloadStart);
					}
					try {
						payload = JSON.parse(jsonText);
					} catch (e) {
						throw new Error('bad_json');
					}
					if (!payload) {
						throw new Error('send_failed');
					}
					if (!payload.success) {
						var msg = payload.data && payload.data.message ? String(payload.data.message) : 'E-Mail konnte nicht gesendet werden.';
						throw new Error(msg);
					}
					var to = (payload.data && payload.data.to) ? String(payload.data.to) : '';
					if (to) {
						setZipStatus('E-Mail wurde an ' + to + ' gesendet.');
					} else {
						setZipStatus('E-Mail wurde gesendet.');
					}
				})
				.catch(function(err){
					var msg = (err && err.message) ? String(err.message) : 'E-Mail konnte nicht gesendet werden.';
					setZipStatus(msg);
					alert(msg);
				})
				.finally(function(){
					triggerButton.disabled = false;
					zipRequestActive = false;
				});
			}
				function bindZipCopyButton(){
					var copyBtn = document.getElementById('cmx-export-zip-share-copy');
					if (!copyBtn || copyBtn.getAttribute('data-bound') === '1') return;
					copyBtn.setAttribute('data-bound', '1');
					copyBtn.addEventListener('click', function(ev){
						ev.preventDefault();
						copyZipLink(false);
					});
				}
				function bindZipSendButton(){
					var sendBtn = document.getElementById('cmx-export-zip-share-send');
					if (!sendBtn || sendBtn.getAttribute('data-bound') === '1') return;
					sendBtn.setAttribute('data-bound', '1');
					sendBtn.addEventListener('click', function(ev){
						ev.preventDefault();
						runZipSendAjax(sendBtn);
					});
				}
			function clearZipShare(){
				var shell = document.getElementById('cmx-export-zip-share-shell');
				if (shell) shell.innerHTML = '';
			}
			function runZipDeleteAjax(triggerButton){
				if (!triggerButton || zipRequestActive) return;
				if (!window.confirm('ZIP-Datei wirklich löschen? Der Link wird danach ungültig.')) {
					return;
				}
				if (typeof ajaxurl === 'undefined' || !ajaxurl) {
					return;
				}

				var nonce = String(triggerButton.getAttribute('data-delete-nonce') || '').trim();
				var fallbackUrl = String(triggerButton.getAttribute('data-delete-url') || '').trim();
				if (!nonce) {
					if (fallbackUrl) window.location.href = fallbackUrl;
					return;
				}

				zipRequestActive = true;
				triggerButton.disabled = true;
				setZipStatus('ZIP-Link wird gelöscht ...');

				var fd = new FormData();
				fd.set('action', 'cmx_export_belege_zip_copy_delete_ajax');
				fd.set('_wpnonce', nonce);

				fetch(ajaxurl, {
					method: 'POST',
					credentials: 'same-origin',
					body: fd
				})
				.then(function(resp){ return resp.text(); })
				.then(function(text){
					var payload = null;
					var jsonText = String(text || '');
					var payloadStart = jsonText.indexOf('{"success"');
					if (payloadStart === -1) {
						payloadStart = jsonText.indexOf('{\"success\"');
					}
					if (payloadStart === -1) {
						payloadStart = jsonText.indexOf('{');
					}
					if (payloadStart > 0) {
						jsonText = jsonText.slice(payloadStart);
					}
					try {
						payload = JSON.parse(jsonText);
					} catch (e) {
						throw new Error('bad_json');
					}
					if (!payload || !payload.success) {
						throw new Error('delete_failed');
					}
					clearZipShare();
				})
				.catch(function(){
					setZipStatus('ZIP-Link konnte nicht gelöscht werden.');
					alert('ZIP-Link konnte nicht gelöscht werden.');
				})
				.finally(function(){
					triggerButton.disabled = false;
					zipRequestActive = false;
				});
			}
			function bindZipDeleteButton(){
				var deleteBtn = document.getElementById('cmx-export-zip-share-delete');
				if (!deleteBtn || deleteBtn.getAttribute('data-bound') === '1') return;
				deleteBtn.setAttribute('data-bound', '1');
				deleteBtn.addEventListener('click', function(ev){
					ev.preventDefault();
					runZipDeleteAjax(deleteBtn);
				});
			}
			function maybeAutoCopyCurrentLink(){
				var input = document.getElementById('cmx-export-zip-share-link');
				if (!input || !input.value) return;
				var cacheKey = 'cmx_zip_export_autocopied_' + input.value;
			var shouldAutoCopy = true;
			try {
				if (window.localStorage && localStorage.getItem(cacheKey) === '1') {
					shouldAutoCopy = false;
				}
			} catch (e) {}
			if (shouldAutoCopy) {
				copyZipLink(true).then(function(ok){
					if (!ok) return;
					try {
						if (window.localStorage) localStorage.setItem(cacheKey, '1');
					} catch (e) {}
				});
			}
		}
			function escAttr(value){
				return String(value || '')
					.replace(/&/g, '&amp;')
					.replace(/"/g, '&quot;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;');
			}
			function escText(value){
				return String(value || '')
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;');
			}
			function buildTrusteeOptions(list, selectedId){
				var html = '<option value="">Treuhänder auswählen</option>';
				if (!Array.isArray(list) || list.length === 0) {
					html += '<option value="" disabled>Keine Treuhänder gefunden</option>';
					return html;
				}
				var chosenId = parseInt(selectedId || 0, 10);
				if (!chosenId) {
					for (var d = 0; d < list.length; d++) {
						var did = parseInt((list[d] && list[d].id) ? list[d].id : 0, 10);
						if (did) {
							chosenId = did;
							break;
						}
					}
				}
				for (var i = 0; i < list.length; i++) {
					var row = list[i] || {};
					var id = parseInt(row.id || 0, 10);
					if (!id) continue;
					var label = String(row.label || '').trim();
					var email = String(row.email || '').trim();
					label = label
						.replace(/&#8211;|&#8212;|&ndash;|&mdash;/gi, '-')
						.replace(/[–—−]/g, '-');
					if (!label) label = 'Kontakt #' + id;
					var text = label + (email ? ' (' + email + ')' : '');
					var selectedAttr = (chosenId && id === chosenId) ? ' selected' : '';
					html += '<option value="' + escAttr(String(id)) + '"' + selectedAttr + '>' + escText(text) + '</option>';
				}
				return html;
			}
			function ensureZipShareRecipientControls(){
				var share = document.getElementById('cmx-export-zip-share');
				if (!share) return;

				var recipient = document.getElementById('cmx-export-zip-share-recipient');
				if (!recipient) {
					recipient = document.createElement('select');
					recipient.id = 'cmx-export-zip-share-recipient';
					recipient.style.minWidth = '260px';
					recipient.style.maxWidth = '100%';
					recipient.style.display = 'inline-block';
					recipient.innerHTML = buildTrusteeOptions(initialTrustees, initialSelectedTrusteeId);
					share.insertBefore(recipient, share.firstChild);
				}
				if (!recipient.value) {
					for (var oi = 0; oi < recipient.options.length; oi++) {
						if (recipient.options[oi].value) {
							recipient.value = recipient.options[oi].value;
							break;
						}
					}
				}

				var sendBtn = document.getElementById('cmx-export-zip-share-send');
				if (!sendBtn) {
					sendBtn = document.createElement('button');
					sendBtn.type = 'button';
					sendBtn.id = 'cmx-export-zip-share-send';
					sendBtn.className = 'button button-secondary';
					sendBtn.title = 'ZIP-Link per Mail versenden';
					sendBtn.setAttribute('data-send-nonce', initialSendNonce || '');
					sendBtn.innerHTML = '<span class="dashicons dashicons-email" style="margin-top:3px;"></span>';
					if (recipient.nextSibling) {
						share.insertBefore(sendBtn, recipient.nextSibling);
					} else {
						share.appendChild(sendBtn);
					}
				} else if (!sendBtn.getAttribute('data-send-nonce') && initialSendNonce) {
					sendBtn.setAttribute('data-send-nonce', initialSendNonce);
				}

				bindZipSendButton();
			}
				function renderZipShare(data){
					var shell = document.getElementById('cmx-export-zip-share-shell');
					if (!shell) return;

					var shareUrl = String((data && data.share_url) ? data.share_url : '').trim();
					if (!shareUrl) return;
					var deleteUrl = String((data && data.delete_url) ? data.delete_url : '').trim();
					var deleteNonce = String((data && data.delete_nonce) ? data.delete_nonce : '').trim();
					var sendNonce = String((data && data.send_nonce) ? data.send_nonce : '').trim();
					var trustees = (data && Array.isArray(data.trustees)) ? data.trustees : [];
					var defaultTrusteeId = parseInt((data && data.default_trustee_id) ? data.default_trustee_id : 0, 10) || 0;
					initialTrustees = trustees;
					initialSendNonce = sendNonce;
					initialSelectedTrusteeId = defaultTrusteeId;
					var optionsHtml = buildTrusteeOptions(trustees, defaultTrusteeId);

					shell.innerHTML =
						'<div id="cmx-export-zip-share" style="margin-top:8px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">'
						+ '<select id="cmx-export-zip-share-recipient" style="min-width:260px;max-width:100%;">'
						+ optionsHtml
						+ '</select>'
						+ '<button type="button" id="cmx-export-zip-share-send" class="button button-secondary" title="ZIP-Link per Mail versenden" data-send-nonce="' + escAttr(sendNonce) + '">'
						+ '<span class="dashicons dashicons-email" style="margin-top:3px;"></span>'
						+ '</button>'
						+ '<input type="text" id="cmx-export-zip-share-link" readonly value="' + escAttr(shareUrl) + '" style="min-width:460px;max-width:100%;width:58ch;">'
						+ '<button type="button" id="cmx-export-zip-share-copy" class="button button-secondary" title="Link kopieren">'
						+ '<span class="dashicons dashicons-clipboard" style="margin-top:4px;"></span>'
						+ '</button>'
						+ '<button type="button" id="cmx-export-zip-share-delete" class="button button-link-delete" title="ZIP-Link löschen" data-delete-url="' + escAttr(deleteUrl) + '" data-delete-nonce="' + escAttr(deleteNonce) + '">'
					+ '<span class="dashicons dashicons-trash" style="margin-top:4px;"></span>'
					+ '</button>'
					+ '</div>'
						+ '<p id="cmx-export-zip-share-status" class="description" style="margin-top:6px;">Der ZIP-Link wird automatisch in die Zwischenablage kopiert.</p>';

				ensureZipShareRecipientControls();
				bindZipCopyButton();
				bindZipDeleteButton();
				maybeAutoCopyCurrentLink();
			}
			function triggerZipDownload(url){
				url = String(url || '').trim();
				if (!url) return;
			var frame = document.getElementById('cmx-belege-export-zip-download-frame');
			if (!frame) {
				frame = document.createElement('iframe');
				frame.id = 'cmx-belege-export-zip-download-frame';
				frame.name = 'cmx-belege-export-zip-download-frame';
				frame.style.display = 'none';
				document.body.appendChild(frame);
			}
				var sep = (url.indexOf('?') === -1) ? '?' : '&';
				frame.src = url + sep + '_dlts=' + Date.now();
			}
			function runZipExportAjax(triggerButton){
				if (zipRequestActive) return;
				syncSelectedPostsIntoForm();

				var btn = (triggerButton && triggerButton.tagName) ? triggerButton : zipButton;
				if (btn) btn.disabled = true;
				zipRequestActive = true;
				setZipStatus('ZIP wird erstellt ...');

				if (typeof ajaxurl === 'undefined' || !ajaxurl) {
					setZipStatus('AJAX ist nicht verfügbar.');
					if (btn) btn.disabled = false;
					zipRequestActive = false;
					return;
				}

				var fd = new FormData(form);
				fd.set('action', 'cmx_export_belege_list_ajax');

				fetch(ajaxurl, {
					method: 'POST',
					credentials: 'same-origin',
					body: fd
				})
				.then(function(resp){ return resp.text(); })
				.then(function(text){
					var payload = null;
					var jsonText = String(text || '');
					var payloadStart = jsonText.indexOf('{"success"');
					if (payloadStart === -1) {
						payloadStart = jsonText.indexOf('{\"success\"');
					}
					if (payloadStart === -1) {
						payloadStart = jsonText.indexOf('{');
					}
					if (payloadStart > 0) {
						jsonText = jsonText.slice(payloadStart);
					}
					try {
						payload = JSON.parse(jsonText);
					} catch (e) {
						throw new Error('bad_json');
					}
					if (!payload || !payload.success || !payload.data) {
						throw new Error('zip_failed');
					}
					renderZipShare(payload.data);
					triggerZipDownload(payload.data.download_url || payload.data.share_url || '');
				})
				.catch(function(){
					setZipStatus('ZIP-Export fehlgeschlagen. Bitte erneut versuchen.');
					alert('ZIP-Export fehlgeschlagen. Bitte erneut versuchen.');
				})
				.finally(function(){
					if (btn) btn.disabled = false;
					zipRequestActive = false;
				});
			}

			for (var b = 0; b < submitButtons.length; b++) {
				submitButtons[b].addEventListener('click', function(){
					rememberSubmitAction(this.value || '');
				});
			}

			if (zipButton) {
				zipButton.addEventListener('click', function(ev){
					ev.preventDefault();
					rememberSubmitAction('cmx_export_belege_list');
					runZipExportAjax(zipButton);
				});
			}

			form.addEventListener('submit', function (ev) {
				var actionVal = detectSubmitAction(ev);
				if (actionVal === 'cmx_export_belege_list') {
					ev.preventDefault();
					var submitter = ev && ev.submitter ? ev.submitter : zipButton;
					runZipExportAjax(submitter);
					return;
				}
				syncSelectedPostsIntoForm();
			});

			ensureZipShareRecipientControls();
			bindZipCopyButton();
			bindZipDeleteButton();
			maybeAutoCopyCurrentLink();
		})();
	</script>
	<?php
});

/* ===== IDs sammeln ===== */
function cmxbu_belege_export_collect_ids(): array {
	$selected_ids = isset($_REQUEST['post']) ? array_filter(array_map('intval',(array)$_REQUEST['post'])) : [];
	$range = cmxbu_belege_export_requested_date_range();

	$qv = [
		'post_type'      => 'belege',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'orderby'        => 'ID',
		'order'          => 'ASC',
	];

	$ref_qs=[]; $ref=$_REQUEST['ref']??'';
	if($ref!==''){ $parts=\wp_parse_url(rawurldecode($ref)); if(!empty($parts['query'])) parse_str($parts['query'],$ref_qs); }
	$src = $ref_qs ?: $_REQUEST;

	foreach(['s','author','m','post_status'] as $k){
		$v=$src[$k]??'';
		if($v!=='' && $v!=='0' && $v!=='-1') $qv[$k]=$v;
	}

	$tax_query=[]; $taxos=\get_object_taxonomies('belege','objects');
	foreach($taxos as $tax){
		$candidates = array_values(array_unique(array_filter([
			$tax->query_var ?? '',
			$tax->name,
			'filter_' . ($tax->name ?? ''),
			(isset($tax->query_var) && $tax->query_var!=='') ? ('filter_'.$tax->query_var) : '',
		])));
		$val=null;
		foreach($candidates as $param){
			if(!array_key_exists($param,$src)) continue;
			$tmp=$src[$param];
			if(is_array($tmp)){
				$tmp=array_values(array_filter($tmp,static fn($v)=>$v!==''&&$v!=='0'&&$v!=='-1'));
				if($tmp){ $val=$tmp; break; }
			}else{
				if($tmp!==''&&$tmp!=='0'&&$tmp!=='-1'){ $val=$tmp; break; }
			}
		}
		if($val!==null){
			$field = is_array($val) ? (is_numeric(reset($val)) ? 'term_id' : 'slug') : (is_numeric($val) ? 'term_id' : 'slug');
			$tax_query[] = ['taxonomy'=>$tax->name,'field'=>$field,'terms'=>is_array($val)?$val:[$val]];
		}
	}
	if($tax_query) $qv['tax_query'] = array_merge(['relation'=>'AND'],$tax_query);

	if($selected_ids){ $qv['post__in']=$selected_ids; $qv['orderby']='post__in'; }

	$q = new \WP_Query($qv);
	$post_ids = \array_map('intval', (array) $q->posts);

	$payment_filtered = [];
	foreach ($post_ids as $post_id) {
		$is_credit_note = false;
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_raw_type') && \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_normalize_type')) {
			$post_obj = \get_post($post_id);
			if ($post_obj instanceof \WP_Post) {
				$raw_type = (string) cmxbu_beleg_export_raw_type($post_obj);
				$is_credit_note = ((string) cmxbu_beleg_export_normalize_type($raw_type) === 'gutschrift');
			}
		}
		if (!$is_credit_note) {
			$tax = \function_exists(__NAMESPACE__ . '\\cmx_belege_taxonomy')
				? (string) cmx_belege_taxonomy()
				: '';
			if ($tax !== '' && \taxonomy_exists($tax)) {
				$slugs = \wp_get_post_terms($post_id, $tax, ['fields' => 'slugs']);
				if (!\is_wp_error($slugs) && !empty($slugs[0])) {
					$slug = \sanitize_key((string) $slugs[0]);
					$is_credit_note = ($slug === 'gutschrift' || $slug === 'gutschriften');
				}
			}
		}

		if ($is_credit_note) {
			$post_date = cmxbu_belege_export_post_date($post_id);
			if ($post_date === '') continue;
			if (!cmxbu_belege_export_date_in_range($post_date, $range)) continue;
			$payment_filtered[] = $post_id;
			continue;
		}

		if (!cmxbu_belege_export_has_payment_date($post_id)) continue;
		if (!cmxbu_belege_export_has_payment_in_range($post_id, $range)) continue;
		$payment_filtered[] = $post_id;
	}
	$post_ids = $payment_filtered;

	return $post_ids;
}

function cmxbu_belege_export_site_prefix(): string {
	$host = strtolower((string) \wp_parse_url(\home_url('/'), PHP_URL_HOST));
	if ($host === '') return 'misbuero';

	$prefix = '';
	$suffix = '.misbuero.ch';
	if (str_ends_with($host, $suffix)) {
		$left = substr($host, 0, -strlen($suffix));
		if ($left !== '') {
			$parts = array_values(array_filter(explode('.', $left)));
			if ($parts) $prefix = (string) end($parts);
		}
	}

	if ($prefix === '') {
		$parts = array_values(array_filter(explode('.', $host)));
		$prefix = (string)($parts[0] ?? 'misbuero');
	}

	$prefix = strtolower(trim((string) preg_replace('~[^a-z0-9_-]+~', '-', $prefix), '-_'));
	return $prefix !== '' ? $prefix : 'misbuero';
}

function cmxbu_belege_export_now_stamp(): string {
	if (function_exists('wp_date')) {
		return (string) \wp_date('Ymd-His');
	}
	return (string) \date_i18n('Ymd-His');
}

function cmxbu_belege_export_range_stamp(): string {
	$range = cmxbu_belege_export_requested_date_range();
	$from = \preg_replace('/[^0-9]/', '', (string) ($range['from'] ?? ''));
	$to   = \preg_replace('/[^0-9]/', '', (string) ($range['to'] ?? ''));

	if ($from !== '' && $to !== '') {
		if ($from === $to) return $from;
		return $from . '-' . $to;
	}

	return cmxbu_belege_export_now_stamp();
}

function cmxbu_belege_export_filename(string $ext): string {
	$ext = strtolower(trim($ext, ". \t\n\r\0\x0B"));
	if ($ext === '') $ext = 'dat';
	$prefix = cmxbu_belege_export_site_prefix();
	$range = cmxbu_belege_export_range_stamp();
	$user_name = '';
	$user = \wp_get_current_user();
	if ($user instanceof \WP_User && $user->exists()) {
		$user_name = (string) $user->user_login;
		if ($user_name === '') {
			$user_name = (string) $user->display_name;
		}
	}
	$user_name = \sanitize_file_name($user_name);
	if ($user_name === '') {
		$user_name = 'user';
	}

	if ($ext === 'pdf') {
		$base = $prefix . '_milchbuechli_' . $user_name . '_' . $range;
	} elseif ($ext === 'csv') {
		$base = $prefix . '_' . $user_name . '_' . $range;
	} else {
		$base = $prefix . '-belege-' . $range;
	}
	return $base . '.' . $ext;
}

require_once __DIR__ . '/exports_CSV.php';
require_once __DIR__ . '/exports_pdf.php';
require_once __DIR__ . '/exports_zip.php';
