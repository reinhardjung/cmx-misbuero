<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_BANK_IMPORT_LOG_FILENAME')) {
	\define(__NAMESPACE__ . '\\CMX_BANK_IMPORT_LOG_FILENAME', 'cmx-bank-import.log');
}

if (!\defined(__NAMESPACE__ . '\\CMX_CAMT_STATE_META')) {
	\define(__NAMESPACE__ . '\\CMX_CAMT_STATE_META', '_cmx_camt_import_state');
}

if (!\defined(__NAMESPACE__ . '\\CMX_CAMT_ASSIGNMENTS_OPTION')) {
	\define(__NAMESPACE__ . '\\CMX_CAMT_ASSIGNMENTS_OPTION', 'cmx_camt_assignments');
}

if (!\defined(__NAMESPACE__ . '\\CMX_CAMT_BELEG_SIGNATURES_META')) {
	\define(__NAMESPACE__ . '\\CMX_CAMT_BELEG_SIGNATURES_META', '_cmx_camt_signatures');
}

if (!\defined(__NAMESPACE__ . '\\CMX_CAMT_BELEG_MATCHES_META')) {
	\define(__NAMESPACE__ . '\\CMX_CAMT_BELEG_MATCHES_META', '_cmx_camt_matches');
}

function cmx_bank_import_log_file_path(): string {
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

	return \trailingslashit($dir) . (string) \constant(__NAMESPACE__ . '\\CMX_BANK_IMPORT_LOG_FILENAME');
}

function cmx_bank_import_log(string $message, array $context = []): void {
	$path = cmx_bank_import_log_file_path();
	if ($path === '') {
		return;
	}

	$line = '[' . (string) \wp_date('Y-m-d H:i:s') . '] ' . \trim($message);
	if (!empty($context)) {
		$json = \wp_json_encode($context, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
		if (\is_string($json) && $json !== '') {
			$line .= ' ' . $json;
		}
	}
	$line .= "\n";
	@\file_put_contents($path, $line, \FILE_APPEND);
}

function cmx_camt_current_user_can(): bool {
	return \current_user_can('manage_options');
}

function cmx_camt_is_cloudmeister_user(): bool {
	if (\function_exists(__NAMESPACE__ . '\\cmx_system_is_cloudmeister_user')) {
		return (bool) cmx_system_is_cloudmeister_user();
	}
	if (\function_exists(__NAMESPACE__ . '\\cmx_is_cloud_meister_user')) {
		return (bool) cmx_is_cloud_meister_user();
	}
	$user = \wp_get_current_user();
	if (!($user instanceof \WP_User) || !$user->exists()) {
		return false;
	}
	return \strtolower((string) $user->user_login) === 'cloudmeister';
}

function cmx_camt_state_key(): string {
	return (string) \constant(__NAMESPACE__ . '\\CMX_CAMT_STATE_META');
}

function cmx_camt_state_get(): array {
	$user_id = (int) \get_current_user_id();
	if ($user_id <= 0) {
		return [];
	}

	$state = \get_user_meta($user_id, cmx_camt_state_key(), true);
	if (!\is_array($state)) {
		return [];
	}

	$entries = [];
	foreach ((array) ($state['entries'] ?? []) as $signature => $entry) {
		if (!\is_array($entry)) {
			continue;
		}
		$signature = \sanitize_key((string) ($entry['signature'] ?? $signature));
		if ($signature === '') {
			continue;
		}
		$entry['signature'] = $signature;
		$entries[$signature] = $entry;
	}

	return [
		'loaded_at' => (string) ($state['loaded_at'] ?? ''),
		'files'     => \is_array($state['files'] ?? null) ? \array_values((array) $state['files']) : [],
		'entries'   => $entries,
	];
}

function cmx_camt_state_set(array $state): void {
	$user_id = (int) \get_current_user_id();
	if ($user_id <= 0) {
		return;
	}

	\update_user_meta($user_id, cmx_camt_state_key(), $state);
}

function cmx_camt_state_clear(): void {
	$user_id = (int) \get_current_user_id();
	if ($user_id <= 0) {
		return;
	}
	\delete_user_meta($user_id, cmx_camt_state_key());
}

function cmx_camt_assignments_get(): array {
	$raw = \get_option((string) \constant(__NAMESPACE__ . '\\CMX_CAMT_ASSIGNMENTS_OPTION'), []);
	$raw = \is_array($raw) ? $raw : [];
	$clean = [];

	foreach ($raw as $signature => $row) {
		if (!\is_array($row)) {
			continue;
		}
		$signature = \sanitize_key((string) $signature);
		$beleg_id = (int) ($row['beleg_id'] ?? 0);
		if ($signature === '' || $beleg_id <= 0 || (string) \get_post_type($beleg_id) !== 'belege' || !\get_post_status($beleg_id)) {
			continue;
		}
		$clean[$signature] = [
			'beleg_id'      => $beleg_id,
			'assigned_at'   => (string) ($row['assigned_at'] ?? ''),
			'booking_date'  => (string) ($row['booking_date'] ?? ''),
			'amount'        => (string) ($row['amount'] ?? ''),
			'direction'     => (string) ($row['direction'] ?? ''),
			'counterparty'  => (string) ($row['counterparty'] ?? ''),
			'reference'     => (string) ($row['reference'] ?? ''),
		];
	}

	if ($clean !== $raw) {
		\update_option((string) \constant(__NAMESPACE__ . '\\CMX_CAMT_ASSIGNMENTS_OPTION'), $clean, false);
	}

	return $clean;
}

function cmx_camt_assignment_get(string $signature): array {
	$signature = \sanitize_key($signature);
	if ($signature === '') {
		return [];
	}

	$assignments = cmx_camt_assignments_get();
	return \is_array($assignments[$signature] ?? null) ? (array) $assignments[$signature] : [];
}

function cmx_camt_beleg_is_assigned(int $beleg_id, string $except_signature = ''): bool {
	if ($beleg_id <= 0) {
		return false;
	}

	$except_signature = \sanitize_key($except_signature);
	foreach (cmx_camt_assignments_get() as $signature => $row) {
		$signature = \sanitize_key((string) $signature);
		if ($signature === '' || $signature === $except_signature) {
			continue;
		}
		if ((int) ($row['beleg_id'] ?? 0) === $beleg_id) {
			return true;
		}
	}

	return false;
}

function cmx_camt_remove_assignment(string $signature): bool {
	$signature = \sanitize_key($signature);
	if ($signature === '') {
		return false;
	}

	$assignments = cmx_camt_assignments_get();
	$current = \is_array($assignments[$signature] ?? null) ? (array) $assignments[$signature] : [];
	$beleg_id = (int) ($current['beleg_id'] ?? 0);
	if (!isset($assignments[$signature])) {
		return false;
	}

	unset($assignments[$signature]);
	\update_option((string) \constant(__NAMESPACE__ . '\\CMX_CAMT_ASSIGNMENTS_OPTION'), $assignments, false);

	if ($beleg_id > 0 && (string) \get_post_type($beleg_id) === 'belege') {
		$signatures = \get_post_meta($beleg_id, (string) \constant(__NAMESPACE__ . '\\CMX_CAMT_BELEG_SIGNATURES_META'), true);
		$signatures = \is_array($signatures) ? \array_values(\array_filter(\array_map('strval', $signatures))) : [];
		$signatures = \array_values(\array_filter($signatures, static function (string $value) use ($signature): bool {
			return $value !== $signature;
		}));
		if (empty($signatures)) {
			\delete_post_meta($beleg_id, (string) \constant(__NAMESPACE__ . '\\CMX_CAMT_BELEG_SIGNATURES_META'));
		} else {
			\update_post_meta($beleg_id, (string) \constant(__NAMESPACE__ . '\\CMX_CAMT_BELEG_SIGNATURES_META'), $signatures);
		}

		$matches = \get_post_meta($beleg_id, (string) \constant(__NAMESPACE__ . '\\CMX_CAMT_BELEG_MATCHES_META'), true);
		$matches = \is_array($matches) ? $matches : [];
		unset($matches[$signature]);
		if (empty($matches)) {
			\delete_post_meta($beleg_id, (string) \constant(__NAMESPACE__ . '\\CMX_CAMT_BELEG_MATCHES_META'));
		} else {
			\update_post_meta($beleg_id, (string) \constant(__NAMESPACE__ . '\\CMX_CAMT_BELEG_MATCHES_META'), $matches);
		}
	}

	return true;
}

function cmx_camt_append_beleg_signature(int $beleg_id, string $signature, array $entry): void {
	if ($beleg_id <= 0 || $signature === '' || (string) \get_post_type($beleg_id) !== 'belege') {
		return;
	}

	$signatures = \get_post_meta($beleg_id, (string) \constant(__NAMESPACE__ . '\\CMX_CAMT_BELEG_SIGNATURES_META'), true);
	$signatures = \is_array($signatures) ? \array_values(\array_filter(\array_map('strval', $signatures))) : [];
	if (!\in_array($signature, $signatures, true)) {
		$signatures[] = $signature;
	}
	\update_post_meta($beleg_id, (string) \constant(__NAMESPACE__ . '\\CMX_CAMT_BELEG_SIGNATURES_META'), \array_values(\array_unique($signatures)));

	$matches = \get_post_meta($beleg_id, (string) \constant(__NAMESPACE__ . '\\CMX_CAMT_BELEG_MATCHES_META'), true);
	$matches = \is_array($matches) ? $matches : [];
	$matches[$signature] = [
		'booking_date'     => (string) ($entry['booking_date'] ?? ''),
		'amount'           => (string) ($entry['amount'] ?? ''),
		'currency'         => (string) ($entry['currency'] ?? ''),
		'direction'        => (string) ($entry['direction'] ?? ''),
		'counterparty'     => (string) ($entry['counterparty_name'] ?? ''),
		'reference'        => (string) ($entry['reference'] ?? ''),
		'document_ref'     => (string) ($entry['document_ref'] ?? ''),
		'assigned_at'      => (string) \wp_date('c'),
		'source_versions'  => \array_values(\array_filter(\array_map('strval', (array) ($entry['source_versions'] ?? [])))),
		'source_files'     => \array_values(\array_filter(\array_map('strval', (array) ($entry['source_files'] ?? [])))),
	];
	\update_post_meta($beleg_id, (string) \constant(__NAMESPACE__ . '\\CMX_CAMT_BELEG_MATCHES_META'), $matches);
}

function cmx_camt_store_assignment(string $signature, int $beleg_id, array $entry): bool {
	$signature = \sanitize_key($signature);
	if ($signature === '' || $beleg_id <= 0 || (string) \get_post_type($beleg_id) !== 'belege') {
		return false;
	}

	$assignments = cmx_camt_assignments_get();
	$current = \is_array($assignments[$signature] ?? null) ? (array) $assignments[$signature] : [];
	$current_beleg_id = (int) ($current['beleg_id'] ?? 0);
	if ($current_beleg_id > 0 && $current_beleg_id !== $beleg_id) {
		return false;
	}
	if (cmx_camt_beleg_is_assigned($beleg_id, $signature)) {
		return false;
	}

	$assignments[$signature] = [
		'beleg_id'      => $beleg_id,
		'assigned_at'   => (string) \wp_date('c'),
		'booking_date'  => (string) ($entry['booking_date'] ?? ''),
		'amount'        => (string) ($entry['amount'] ?? ''),
		'direction'     => (string) ($entry['direction'] ?? ''),
		'counterparty'  => (string) ($entry['counterparty_name'] ?? ''),
		'reference'     => (string) ($entry['reference'] ?? ''),
	];
	\update_option((string) \constant(__NAMESPACE__ . '\\CMX_CAMT_ASSIGNMENTS_OPTION'), $assignments, false);
	cmx_camt_append_beleg_signature($beleg_id, $signature, $entry);
	return true;
}

function cmx_camt_clean_ws(string $value): string {
	$value = \wp_strip_all_tags($value);
	$value = \preg_replace('~\s+~u', ' ', $value);
	return \trim((string) $value);
}

function cmx_camt_normalize_name(string $value): string {
	$value = \remove_accents(cmx_camt_clean_ws($value));
	$value = \function_exists('mb_strtolower')
		? (string) \mb_strtolower($value, 'UTF-8')
		: (string) \strtolower($value);
	return \trim((string) \preg_replace('~\s+~u', ' ', $value));
}

function cmx_camt_normalize_iban(string $value): string {
	$value = \preg_replace('~[^A-Za-z0-9]+~', '', $value);
	return \strtoupper(\trim((string) $value));
}

function cmx_camt_normalize_ref(string $value): string {
	$value = \strtoupper(cmx_camt_clean_ws($value));
	$value = \preg_replace('~[^A-Z0-9]+~', '', $value);
	if ($value === 'NOTPROVIDED') {
		return '';
	}
	return \trim((string) $value);
}

function cmx_camt_normalize_date(string $value): string {
	$value = \trim($value);
	if ($value === '') {
		return '';
	}
	if (\preg_match('~^\d{4}-\d{2}-\d{2}$~', $value)) {
		return $value;
	}
	if (\preg_match('~^(\d{4}-\d{2}-\d{2})T~', $value, $m)) {
		return (string) $m[1];
	}
	$ts = \strtotime($value);
	return $ts ? (string) \wp_date('Y-m-d', $ts) : '';
}

function cmx_camt_normalize_amount($value): string {
	if ($value === null || $value === '') {
		return '';
	}
	if (\function_exists(__NAMESPACE__ . '\\cmx_parse_number')) {
		$amount = (float) cmx_parse_number((string) $value);
	} else {
		$amount = (float) \str_replace(',', '.', (string) $value);
	}
	return \number_format(\abs($amount), 2, '.', '');
}

function cmx_camt_format_amount($value): string {
	$amount = cmx_camt_normalize_amount($value);
	if ($amount === '') {
		return '-';
	}
	$num = (float) $amount;
	return \function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')
		? (string) cmx_format_swiss_number($num, 2)
		: \number_format($num, 2, '.', "'");
}

function cmx_camt_format_date(string $value): string {
	$value = cmx_camt_normalize_date($value);
	if ($value === '') {
		return '-';
	}
	$ts = \strtotime($value);
	return $ts ? (string) \wp_date('d.m.Y', $ts) : $value;
}

function cmx_camt_direction_label(string $direction): string {
	return $direction === 'credit' ? 'Einnahme' : 'Ausgabe';
}

function cmx_camt_beleg_direction_from_entry(string $direction): string {
	return $direction === 'credit' ? 'ausgang' : 'eingang';
}

function cmx_camt_dom_query(\DOMXPath $xpath, string $query, ?\DOMNode $context = null): array {
	$list = $xpath->query($query, $context);
	if (!$list instanceof \DOMNodeList) {
		return [];
	}

	$out = [];
	foreach ($list as $node) {
		if ($node instanceof \DOMNode) {
			$out[] = $node;
		}
	}
	return $out;
}

function cmx_camt_dom_first_text(\DOMXPath $xpath, string $query, ?\DOMNode $context = null): string {
	$nodes = cmx_camt_dom_query($xpath, $query, $context);
	if (empty($nodes)) {
		return '';
	}
	return cmx_camt_clean_ws((string) ($nodes[0]->textContent ?? ''));
}

function cmx_camt_dom_all_text(\DOMXPath $xpath, string $query, ?\DOMNode $context = null): array {
	$out = [];
	foreach (cmx_camt_dom_query($xpath, $query, $context) as $node) {
		$text = cmx_camt_clean_ws((string) ($node->textContent ?? ''));
		if ($text !== '') {
			$out[] = $text;
		}
	}
	return \array_values(\array_unique($out));
}

function cmx_camt_dom_first_attr(\DOMXPath $xpath, string $query, string $attr, ?\DOMNode $context = null): string {
	$nodes = cmx_camt_dom_query($xpath, $query, $context);
	if (empty($nodes) || !($nodes[0] instanceof \DOMElement)) {
		return '';
	}
	return cmx_camt_clean_ws((string) $nodes[0]->getAttribute($attr));
}

function cmx_camt_extract_document_ref(array $pieces): string {
	foreach ($pieces as $piece) {
		$piece = cmx_camt_clean_ws((string) $piece);
		if ($piece === '') {
			continue;
		}
		if (\preg_match('~\b\d{6,}-\d{3,}\b~', $piece, $m)) {
			return (string) $m[0];
		}
	}

	foreach ($pieces as $piece) {
		$piece = cmx_camt_clean_ws((string) $piece);
		if ($piece === '') {
			continue;
		}
		if (\preg_match('~\b\d{12,}\b~', $piece, $m)) {
			return (string) $m[0];
		}
	}

	return '';
}

function cmx_camt_amounts_match($a, $b): bool {
	$fa = (float) cmx_camt_normalize_amount($a);
	$fb = (float) cmx_camt_normalize_amount($b);
	return \abs($fa - $fb) < 0.009;
}

function cmx_camt_refs_overlap(array $refs_a, array $refs_b): bool {
	$refs_a = \array_values(\array_filter(\array_map(__NAMESPACE__ . '\\cmx_camt_normalize_ref', $refs_a)));
	$refs_b = \array_values(\array_filter(\array_map(__NAMESPACE__ . '\\cmx_camt_normalize_ref', $refs_b)));
	if (empty($refs_a) || empty($refs_b)) {
		return false;
	}

	foreach ($refs_a as $left) {
		foreach ($refs_b as $right) {
			if ($left === $right) {
				return true;
			}
			if (\strlen($left) >= 10 && \strlen($right) >= 10 && (\str_contains($left, $right) || \str_contains($right, $left))) {
				return true;
			}
		}
	}

	return false;
}

function cmx_camt_entries_should_merge(array $left, array $right): bool {
	if ((string) ($left['direction'] ?? '') !== (string) ($right['direction'] ?? '')) {
		return false;
	}
	if (!cmx_camt_amounts_match((string) ($left['amount'] ?? ''), (string) ($right['amount'] ?? ''))) {
		return false;
	}

	$left_date = (string) ($left['booking_date'] ?? '');
	$right_date = (string) ($right['booking_date'] ?? '');
	if ($left_date !== '' && $right_date !== '' && $left_date !== $right_date) {
		return false;
	}

	$left_account = cmx_camt_normalize_iban((string) ($left['account_iban'] ?? ''));
	$right_account = cmx_camt_normalize_iban((string) ($right['account_iban'] ?? ''));
	if ($left_account !== '' && $right_account !== '' && $left_account !== $right_account) {
		return false;
	}

	if (cmx_camt_refs_overlap((array) ($left['refs'] ?? []), (array) ($right['refs'] ?? []))) {
		return true;
	}

	$left_doc = cmx_camt_normalize_ref((string) ($left['document_ref'] ?? ''));
	$right_doc = cmx_camt_normalize_ref((string) ($right['document_ref'] ?? ''));
	if ($left_doc !== '' && $right_doc !== '' && $left_doc === $right_doc) {
		return true;
	}

	$left_cp_iban = cmx_camt_normalize_iban((string) ($left['counterparty_iban'] ?? ''));
	$right_cp_iban = cmx_camt_normalize_iban((string) ($right['counterparty_iban'] ?? ''));
	$left_cp_name = cmx_camt_normalize_name((string) ($left['counterparty_name'] ?? ''));
	$right_cp_name = cmx_camt_normalize_name((string) ($right['counterparty_name'] ?? ''));
	if ($left_cp_iban !== '' && $right_cp_iban !== '' && $left_cp_iban === $right_cp_iban) {
		if ($left_cp_name === '' || $right_cp_name === '' || $left_cp_name === $right_cp_name) {
			return true;
		}
	}

	$left_haystack = cmx_camt_normalize_name((string) ($left['search_text'] ?? ''));
	$right_haystack = cmx_camt_normalize_name((string) ($right['search_text'] ?? ''));
	if ($left_cp_name !== '' && $left_cp_name === $right_cp_name && $left_date !== '' && $left_date === $right_date) {
		if ($left_haystack !== '' && $right_haystack !== '' && ($left_haystack === $right_haystack || \str_contains($left_haystack, $right_haystack) || \str_contains($right_haystack, $left_haystack))) {
			return true;
		}
	}

	return false;
}

function cmx_camt_merge_entry_pair(array $left, array $right): array {
	$merged = $left;
	foreach (['value_date', 'reference', 'document_ref', 'remittance', 'message', 'counterparty_name', 'counterparty_iban', 'account_iban', 'currency'] as $key) {
		if ((string) ($merged[$key] ?? '') === '' && (string) ($right[$key] ?? '') !== '') {
			$merged[$key] = (string) $right[$key];
		}
	}

	$merged['source_versions'] = \array_values(\array_unique(\array_merge((array) ($left['source_versions'] ?? []), (array) ($right['source_versions'] ?? []))));
	$merged['source_files'] = \array_values(\array_unique(\array_merge((array) ($left['source_files'] ?? []), (array) ($right['source_files'] ?? []))));
	$merged['refs'] = \array_values(\array_unique(\array_filter(\array_merge((array) ($left['refs'] ?? []), (array) ($right['refs'] ?? [])))));
	$merged['reference_labels'] = \array_values(\array_unique(\array_filter(\array_merge((array) ($left['reference_labels'] ?? []), (array) ($right['reference_labels'] ?? [])))));
	$merged['search_text'] = cmx_camt_clean_ws(\implode(' ', \array_values(\array_unique(\array_filter([
		(string) ($left['search_text'] ?? ''),
		(string) ($right['search_text'] ?? ''),
	])))));

	return $merged;
}

function cmx_camt_finalize_entry(array $entry, int $index): array {
	$refs = \array_values(\array_unique(\array_filter(\array_map(__NAMESPACE__ . '\\cmx_camt_normalize_ref', (array) ($entry['refs'] ?? [])))));
	\sort($refs);

	$seed = [
		'amount'           => (string) ($entry['amount'] ?? ''),
		'currency'         => (string) ($entry['currency'] ?? ''),
		'direction'        => (string) ($entry['direction'] ?? ''),
		'booking_date'     => (string) ($entry['booking_date'] ?? ''),
		'value_date'       => (string) ($entry['value_date'] ?? ''),
		'refs'             => $refs,
		'document_ref'     => (string) ($entry['document_ref'] ?? ''),
		'counterparty_iban'=> cmx_camt_normalize_iban((string) ($entry['counterparty_iban'] ?? '')),
		'counterparty'     => cmx_camt_normalize_name((string) ($entry['counterparty_name'] ?? '')),
	];

	$signature = 'camt_' . \substr(\hash('sha256', (string) \wp_json_encode($seed)), 0, 24);
	$entry['signature'] = $signature;
	$entry['row_index'] = $index;
	$entry['refs'] = $refs;
	$entry['source_versions'] = \array_values(\array_unique(\array_filter(\array_map('strval', (array) ($entry['source_versions'] ?? [])))));
	$entry['source_files'] = \array_values(\array_unique(\array_filter(\array_map('strval', (array) ($entry['source_files'] ?? [])))));
	$entry['beleg_direction'] = cmx_camt_beleg_direction_from_entry((string) ($entry['direction'] ?? ''));
	return $entry;
}

function cmx_camt_parse_transaction_node(\DOMXPath $xpath, \DOMNode $ntry, ?\DOMNode $tx, string $doc_type, string $filename, string $account_iban): array {
	$amount = cmx_camt_dom_first_text($xpath, './*[local-name()="Amt"]', $tx);
	if ($amount === '') {
		$amount = cmx_camt_dom_first_text($xpath, './*[local-name()="Amt"]', $ntry);
	}
	$amount = cmx_camt_normalize_amount($amount);

	$currency = cmx_camt_dom_first_attr($xpath, './*[local-name()="Amt"]', 'Ccy', $tx);
	if ($currency === '') {
		$currency = cmx_camt_dom_first_attr($xpath, './*[local-name()="Amt"]', 'Ccy', $ntry);
	}

	$direction_code = \strtoupper(cmx_camt_dom_first_text($xpath, './*[local-name()="CdtDbtInd"]', $tx));
	if ($direction_code === '') {
		$direction_code = \strtoupper(cmx_camt_dom_first_text($xpath, './*[local-name()="CdtDbtInd"]', $ntry));
	}
	$direction = $direction_code === 'DBIT' ? 'debit' : 'credit';

	$booking_date = cmx_camt_normalize_date(cmx_camt_dom_first_text($xpath, './*[local-name()="BookgDt"]/*[local-name()="Dt"] | ./*[local-name()="BookgDt"]/*[local-name()="DtTm"]', $ntry));
	$value_date = cmx_camt_normalize_date(cmx_camt_dom_first_text($xpath, './*[local-name()="ValDt"]/*[local-name()="Dt"] | ./*[local-name()="ValDt"]/*[local-name()="DtTm"]', $ntry));

	$counterparty_name = '';
	$counterparty_iban = '';
	if ($tx instanceof \DOMNode) {
		if ($direction === 'credit') {
			$counterparty_name = cmx_camt_dom_first_text($xpath, './*[local-name()="RltdPties"]/*[local-name()="Dbtr"]/*[local-name()="Nm"] | ./*[local-name()="RltdPties"]/*[local-name()="UltmtDbtr"]/*[local-name()="Nm"]', $tx);
			$counterparty_iban = cmx_camt_dom_first_text($xpath, './*[local-name()="RltdPties"]/*[local-name()="DbtrAcct"]/*[local-name()="Id"]/*[local-name()="IBAN"]', $tx);
		} else {
			$counterparty_name = cmx_camt_dom_first_text($xpath, './*[local-name()="RltdPties"]/*[local-name()="Cdtr"]/*[local-name()="Nm"] | ./*[local-name()="RltdPties"]/*[local-name()="UltmtCdtr"]/*[local-name()="Nm"]', $tx);
			$counterparty_iban = cmx_camt_dom_first_text($xpath, './*[local-name()="RltdPties"]/*[local-name()="CdtrAcct"]/*[local-name()="Id"]/*[local-name()="IBAN"]', $tx);
		}
	}

	$reference = cmx_camt_dom_first_text($xpath, './*[local-name()="RmtInf"]//*[local-name()="CdtrRefInf"]//*[local-name()="Ref"]', $tx);
	$remittance_parts = [];
	$remittance_parts = \array_merge($remittance_parts, cmx_camt_dom_all_text($xpath, './*[local-name()="RmtInf"]//*[local-name()="AddtlRmtInf"]', $tx));
	$remittance_parts = \array_merge($remittance_parts, cmx_camt_dom_all_text($xpath, './*[local-name()="RmtInf"]//*[local-name()="Ustrd"]', $tx));
	$message_parts = [];
	$message_parts = \array_merge($message_parts, cmx_camt_dom_all_text($xpath, './*[local-name()="AddtlTxInf"]', $tx));
	$message_parts = \array_merge($message_parts, cmx_camt_dom_all_text($xpath, './*[local-name()="AddtlNtryInf"]', $ntry));

	$ref_candidates = [
		(string) cmx_camt_dom_first_text($xpath, './*[local-name()="AcctSvcrRef"]', $ntry),
		(string) cmx_camt_dom_first_text($xpath, './*[local-name()="NtryRef"]', $ntry),
		(string) cmx_camt_dom_first_text($xpath, './*[local-name()="Refs"]/*[local-name()="AcctSvcrRef"]', $tx),
		(string) cmx_camt_dom_first_text($xpath, './*[local-name()="Refs"]/*[local-name()="TxId"]', $tx),
		(string) cmx_camt_dom_first_text($xpath, './*[local-name()="Refs"]/*[local-name()="InstrId"]', $tx),
		(string) cmx_camt_dom_first_text($xpath, './*[local-name()="Refs"]/*[local-name()="EndToEndId"]', $tx),
		(string) $reference,
	];

	$document_ref = cmx_camt_extract_document_ref(\array_merge($ref_candidates, $remittance_parts, $message_parts));
	$reference_label = $reference !== '' ? $reference : ($document_ref !== '' ? $document_ref : '');
	$remittance = \implode(' | ', \array_values(\array_unique(\array_filter($remittance_parts))));
	$message = \implode(' | ', \array_values(\array_unique(\array_filter($message_parts))));

	$search_text = \implode(' ', \array_values(\array_filter([
		$counterparty_name,
		$counterparty_iban,
		$reference,
		$document_ref,
		$remittance,
		$message,
	])));

	return [
		'doc_type'         => $doc_type,
		'source_versions'  => [$doc_type],
		'source_files'     => [$filename],
		'amount'           => $amount,
		'currency'         => \strtoupper($currency),
		'direction'        => $direction,
		'booking_date'     => $booking_date,
		'value_date'       => $value_date,
		'account_iban'     => cmx_camt_normalize_iban($account_iban),
		'counterparty_name'=> $counterparty_name,
		'counterparty_iban'=> cmx_camt_normalize_iban($counterparty_iban),
		'reference'        => $reference_label,
		'document_ref'     => $document_ref,
		'remittance'       => $remittance,
		'message'          => $message,
		'refs'             => \array_values(\array_unique(\array_filter(\array_map(__NAMESPACE__ . '\\cmx_camt_normalize_ref', $ref_candidates)))),
		'reference_labels' => \array_values(\array_unique(\array_filter([$reference, $document_ref]))),
		'search_text'      => $search_text,
	];
}

function cmx_camt_parse_document(string $xml, string $filename): array {
	$xml = \trim($xml);
	if ($xml === '') {
		return ['ok' => false, 'error' => 'Leere Datei.'];
	}

	$prev_errors = \libxml_use_internal_errors(true);
	$dom = new \DOMDocument();
	$loaded = @$dom->loadXML($xml, \LIBXML_NONET | \LIBXML_NOCDATA | \LIBXML_COMPACT);
	$errors = \libxml_get_errors();
	\libxml_clear_errors();
	\libxml_use_internal_errors($prev_errors);

	if (!$loaded) {
		$msg = isset($errors[0]) ? \trim((string) $errors[0]->message) : 'XML konnte nicht gelesen werden.';
		return ['ok' => false, 'error' => $msg];
	}

	$ns_uri = (string) ($dom->documentElement instanceof \DOMElement ? $dom->documentElement->namespaceURI : '');
	$doc_type = '';
	if (\strpos($ns_uri, 'camt.053') !== false) {
		$doc_type = '053';
	} elseif (\strpos($ns_uri, 'camt.054') !== false) {
		$doc_type = '054';
	}
	if ($doc_type === '') {
		return ['ok' => false, 'error' => 'Nur camt.053 und camt.054 werden unterstuetzt.'];
	}

	$xpath = new \DOMXPath($dom);
	$account_iban = cmx_camt_dom_first_text($xpath, '//*[local-name()="Acct"]/*[local-name()="Id"]/*[local-name()="IBAN"][1]');
	$msg_id = cmx_camt_dom_first_text($xpath, '//*[local-name()="GrpHdr"]/*[local-name()="MsgId"][1]');
	$ntries = cmx_camt_dom_query($xpath, '//*[local-name()="Ntry"]');
	$entries = [];

	foreach ($ntries as $ntry) {
		$tx_nodes = cmx_camt_dom_query($xpath, './*[local-name()="NtryDtls"]/*[local-name()="TxDtls"]', $ntry);
		if (empty($tx_nodes)) {
			$tx_nodes = [null];
		}

		foreach ($tx_nodes as $tx) {
			$entry = cmx_camt_parse_transaction_node($xpath, $ntry, $tx instanceof \DOMNode ? $tx : null, $doc_type, $filename, $account_iban);
			if ((string) ($entry['amount'] ?? '') === '') {
				continue;
			}
			$entries[] = $entry;
		}
	}

	return [
		'ok'          => true,
		'doc_type'    => $doc_type,
		'message_id'  => $msg_id,
		'account_iban'=> cmx_camt_normalize_iban($account_iban),
		'entries'     => $entries,
	];
}

function cmx_camt_normalize_uploaded_files(array $files): array {
	$out = [];
	$names = $files['name'] ?? [];
	if (!\is_array($names)) {
		$names = [$names];
	}
	$types = $files['type'] ?? [];
	if (!\is_array($types)) {
		$types = [$types];
	}
	$tmp_names = $files['tmp_name'] ?? [];
	if (!\is_array($tmp_names)) {
		$tmp_names = [$tmp_names];
	}
	$errors = $files['error'] ?? [];
	if (!\is_array($errors)) {
		$errors = [$errors];
	}
	$sizes = $files['size'] ?? [];
	if (!\is_array($sizes)) {
		$sizes = [$sizes];
	}

	$count = \max(\count($names), \count($tmp_names));
	for ($i = 0; $i < $count; $i++) {
		$out[] = [
			'name'     => (string) ($names[$i] ?? ''),
			'type'     => (string) ($types[$i] ?? ''),
			'tmp_name' => (string) ($tmp_names[$i] ?? ''),
			'error'    => (int) ($errors[$i] ?? \UPLOAD_ERR_NO_FILE),
			'size'     => (int) ($sizes[$i] ?? 0),
		];
	}

	return $out;
}

function cmx_camt_merge_entry_collections(array $left_entries, array $right_entries): array {
	$merged = \array_values($left_entries);
	foreach ($right_entries as $right) {
		$found = null;
		foreach ($merged as $idx => $left) {
			if (cmx_camt_entries_should_merge($left, $right)) {
				$found = $idx;
				break;
			}
		}

		if ($found === null) {
			$merged[] = $right;
			continue;
		}

		$merged[$found] = cmx_camt_merge_entry_pair($merged[$found], $right);
	}

	\usort($merged, static function (array $a, array $b): int {
		$da = (string) ($a['booking_date'] ?? '');
		$db = (string) ($b['booking_date'] ?? '');
		if ($da !== $db) {
			return \strcmp($db, $da);
		}
		$aa = (float) ($a['amount'] ?? 0);
		$ab = (float) ($b['amount'] ?? 0);
		if ($aa === $ab) {
			return \strcmp((string) ($a['counterparty_name'] ?? ''), (string) ($b['counterparty_name'] ?? ''));
		}
		return $ab <=> $aa;
	});

	$final = [];
	foreach ($merged as $index => $entry) {
		$entry = cmx_camt_finalize_entry($entry, $index + 1);
		$final[(string) $entry['signature']] = $entry;
	}

	return $final;
}

function cmx_camt_upload_and_parse_files(array $normalized_files): array {
	$errors = [];
	$file_rows = [];
	$new_entries = [];

	foreach ($normalized_files as $file) {
		$name = \sanitize_file_name((string) ($file['name'] ?? ''));
		if ($name === '') {
			continue;
		}
		$error_code = (int) ($file['error'] ?? \UPLOAD_ERR_NO_FILE);
		if ($error_code !== \UPLOAD_ERR_OK) {
			$errors[] = $name . ': Upload fehlgeschlagen.';
			continue;
		}

		$tmp_name = (string) ($file['tmp_name'] ?? '');
		if ($tmp_name === '' || !\is_uploaded_file($tmp_name)) {
			$errors[] = $name . ': Temporäre Datei nicht gefunden.';
			continue;
		}

		$ext = \strtolower((string) \pathinfo($name, \PATHINFO_EXTENSION));
		if ($ext !== 'xml') {
			$errors[] = $name . ': Nur XML-Dateien sind erlaubt.';
			continue;
		}

		$content = @\file_get_contents($tmp_name);
		if (!\is_string($content) || $content === '') {
			$errors[] = $name . ': Datei konnte nicht gelesen werden.';
			continue;
		}

		$parsed = cmx_camt_parse_document($content, $name);
		if (empty($parsed['ok'])) {
			$errors[] = $name . ': ' . (string) ($parsed['error'] ?? 'Unbekannter Fehler.');
			continue;
		}

		$doc_type = (string) ($parsed['doc_type'] ?? '');
		$entries = \is_array($parsed['entries'] ?? null) ? (array) $parsed['entries'] : [];
		$file_rows[] = [
			'name'         => $name,
			'doc_type'     => $doc_type,
			'account_iban' => (string) ($parsed['account_iban'] ?? ''),
			'message_id'   => (string) ($parsed['message_id'] ?? ''),
			'entry_count'  => \count($entries),
		];
		$new_entries = \array_merge($new_entries, $entries);
	}

	return [
		'files'   => $file_rows,
		'entries' => $new_entries,
		'errors'  => $errors,
	];
}

function cmx_camt_beleg_title_ids_like(string $needle, int $limit = 25): array {
	global $wpdb;
	$needle = \trim($needle);
	if ($needle === '') {
		return [];
	}

	$like = '%' . $wpdb->esc_like($needle) . '%';
	$sql = $wpdb->prepare(
		"SELECT ID
		   FROM {$wpdb->posts}
		  WHERE post_type = %s
		    AND post_status IN ('publish','draft','pending','future','private')
		    AND post_title LIKE %s
		  ORDER BY post_date DESC
		  LIMIT %d",
		'belege',
		$like,
		$limit
	);
	$ids = $wpdb->get_col($sql);
	return \array_values(\array_filter(\array_map('intval', \is_array($ids) ? $ids : [])));
}

function cmx_camt_recent_beleg_ids(array $entry, int $limit = 80): array {
	$args = [
		'post_type'      => 'belege',
		'post_status'    => ['publish', 'draft', 'pending', 'future', 'private'],
		'posts_per_page' => $limit,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'fields'         => 'ids',
	];

	$booking_date = cmx_camt_normalize_date((string) ($entry['booking_date'] ?? ''));
	if ($booking_date !== '') {
		$ts = \strtotime($booking_date);
		if ($ts) {
			$args['date_query'] = [[
				'after'     => (string) \wp_date('Y-m-d', \strtotime('-120 days', $ts)),
				'before'    => (string) \wp_date('Y-m-d', \strtotime('+30 days', $ts)),
				'inclusive' => true,
			]];
		}
	}

	$ids = \get_posts($args);
	return \array_values(\array_filter(\array_map('intval', \is_array($ids) ? $ids : [])));
}

function cmx_camt_beleg_ids_by_amount_window(array $entry, float $window = 0.0): array {
	$entry_amount = (float) cmx_camt_normalize_amount((string) ($entry['amount'] ?? ''));
	if ($entry_amount <= 0.0) {
		return [];
	}
	$target_amount = $entry_amount + $window;
	$amount_from = \min($entry_amount, $target_amount);
	$amount_to = \max($entry_amount, $target_amount);
	if ($amount_to <= 0.0) {
		return [];
	}
	if ($amount_from < 0.0) {
		$amount_from = 0.0;
	}

	$ids = \get_posts([
		'post_type'      => 'belege',
		'post_status'    => ['publish', 'draft', 'pending', 'future', 'private'],
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'fields'         => 'ids',
		'no_found_rows'  => true,
	]);

	$matches = [];
	foreach ((array) $ids as $beleg_id) {
		$beleg_id = (int) $beleg_id;
		if ($beleg_id <= 0) {
			continue;
		}
		$amount = cmx_camt_beleg_amount($beleg_id);
		if ($amount <= 0.0) {
			continue;
		}
		if ($amount >= ($amount_from - 0.009) && $amount <= ($amount_to + 0.009)) {
			$matches[] = $beleg_id;
		}
	}

	return $matches;
}

function cmx_camt_amount_window_from_request($value): float {
	return (float) $value;
}

function cmx_camt_beleg_amount(int $beleg_id): float {
	if ($beleg_id <= 0) {
		return 0.0;
	}

	if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
		$calc = (array) cmxbu_get_beleg_positionen_calc($beleg_id);
		if (isset($calc['total']) && (float) $calc['total'] > 0.0) {
			return (float) $calc['total'];
		}
	}

	$raw = (string) \get_post_meta($beleg_id, '_cmx_beleg_summe_override', true);
	if ($raw === '') {
		return 0.0;
	}

	return \function_exists(__NAMESPACE__ . '\\cmx_parse_number')
		? (float) cmx_parse_number($raw)
		: (float) \str_replace(',', '.', $raw);
}

function cmx_camt_beleg_category_label(int $beleg_id): string {
	if ($beleg_id <= 0 || !\function_exists(__NAMESPACE__ . '\\cmx_belege_kategorie_taxonomy')) {
		return '';
	}
	$tax = (string) cmx_belege_kategorie_taxonomy();
	if ($tax === '' || !\taxonomy_exists($tax)) {
		return '';
	}
	$terms = \wp_get_post_terms($beleg_id, $tax, ['fields' => 'names']);
	if (\is_wp_error($terms) || empty($terms)) {
		return '';
	}
	return (string) $terms[0];
}

function cmx_camt_beleg_contact_label(int $beleg_id): string {
	if ($beleg_id <= 0) {
		return '';
	}
	if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_beleg_contact_option_label')) {
		return (string) cmx_scanner_beleg_contact_option_label($beleg_id);
	}

	$label = \trim((string) \get_post_meta($beleg_id, '_cmx_beleg_kontakt_label', true));
	if ($label !== '') {
		return $label;
	}
	$kontakt_id = (int) \get_post_meta($beleg_id, '_cmx_beleg_kontakt_id', true);
	return $kontakt_id > 0 ? (string) \get_the_title($kontakt_id) : '';
}

function cmx_camt_beleg_score_candidate(int $beleg_id, array $entry, float $amount_window = 0.0): array {
	$title = \trim((string) \get_the_title($beleg_id));
	$contact = cmx_camt_beleg_contact_label($beleg_id);
	$direction = \sanitize_key((string) \get_post_meta($beleg_id, \defined(__NAMESPACE__ . '\\CMX_BELEG_META_RICHTUNG') ? CMX_BELEG_META_RICHTUNG : '_cmx_beleg_richtung', true));
	$amount = cmx_camt_beleg_amount($beleg_id);
	$entry_amount = (float) cmx_camt_normalize_amount((string) ($entry['amount'] ?? ''));
	$target_amount = $entry_amount + $amount_window;
	$amount_from = \min($entry_amount, $target_amount);
	$amount_to = \max($entry_amount, $target_amount);
	$booking_date = cmx_camt_normalize_date((string) ($entry['booking_date'] ?? ''));
	$paid_date = cmx_camt_normalize_date((string) \get_post_meta($beleg_id, \defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM') ? CMX_BELEG_META_BEZAHLT_AM : '_cmx_beleg_bezahlt_am', true));
	$invoice_date = cmx_camt_normalize_date((string) \get_post_meta($beleg_id, \defined(__NAMESPACE__ . '\\CMX_BELEG_META_RNG_DATUM') ? CMX_BELEG_META_RNG_DATUM : '_cmx_beleg_rng_datum', true));
	$status = \sanitize_key((string) \get_post_meta($beleg_id, \defined(__NAMESPACE__ . '\\CMX_BELEG_META_STATUS') ? CMX_BELEG_META_STATUS : '_cmx_beleg_status', true));
	$doc_ref = \trim((string) ($entry['document_ref'] ?? ''));
	$counterparty_name = \trim((string) ($entry['counterparty_name'] ?? ''));
	$score = 0;

	if ($direction !== '' && $direction === (string) ($entry['beleg_direction'] ?? '')) {
		$score += 120;
	}

	if (cmx_camt_amounts_match($amount, $entry_amount)) {
		$score += 320;
	} elseif (cmx_camt_amounts_match($amount, $target_amount)) {
		$score += 220;
	} elseif ($amount >= ($amount_from - 0.009) && $amount <= ($amount_to + 0.009)) {
		$distance = \abs($amount - $entry_amount);
		$score += \max(40, 160 - (int) \round($distance * 8));
	} else {
		$score = -100000;
	}

	if ($doc_ref !== '' && $title !== '' && \stripos($title, $doc_ref) !== false) {
		$score += 320;
	}
	if ($doc_ref !== '' && $contact !== '' && \stripos($contact, $doc_ref) !== false) {
		$score += 50;
	}

	if ($counterparty_name !== '') {
		$cp_norm = cmx_camt_normalize_name($counterparty_name);
		if ($cp_norm !== '' && (\str_contains(cmx_camt_normalize_name($title), $cp_norm) || \str_contains(cmx_camt_normalize_name($contact), $cp_norm))) {
			$score += 90;
		}
	}

	if ($booking_date !== '') {
		if ($paid_date !== '' && $paid_date === $booking_date) {
			$score += 170;
		}
		if ($invoice_date !== '' && $invoice_date === $booking_date) {
			$score += 70;
		}
		if ($invoice_date !== '') {
			$diff = (int) \abs((\strtotime($invoice_date) ?: 0) - (\strtotime($booking_date) ?: 0));
			$days = (int) \floor($diff / \DAY_IN_SECONDS);
			if ($days <= 3) {
				$score += 30;
			} elseif ($days <= 30) {
				$score += 10;
			}
		}
	}

	if ($status === 'offen' || $status === '') {
		$score += 20;
	}

	return [
		'id'          => $beleg_id,
		'score'       => $score,
		'title'       => $title,
		'contact'     => $contact,
		'amount'      => $amount,
		'direction'   => $direction,
		'paid_date'   => $paid_date,
		'invoice_date'=> $invoice_date,
		'status'      => $status,
		'category'    => cmx_camt_beleg_category_label($beleg_id),
	];
}

function cmx_camt_collect_beleg_candidates(array $entry, float $amount_window = 0.0): array {
	$ids = [];
	$doc_ref = \trim((string) ($entry['document_ref'] ?? ''));
	if ($doc_ref !== '') {
		$ids = \array_merge($ids, cmx_camt_beleg_title_ids_like($doc_ref, 25));
	}
	$reference = \trim((string) ($entry['reference'] ?? ''));
	if ($reference !== '' && $reference !== $doc_ref) {
		$ids = \array_merge($ids, cmx_camt_beleg_title_ids_like($reference, 15));
	}
	$ids = \array_merge($ids, cmx_camt_recent_beleg_ids($entry, 80));
	$ids = \array_merge($ids, cmx_camt_beleg_ids_by_amount_window($entry, $amount_window));
	$ids = \array_values(\array_unique(\array_filter(\array_map('intval', $ids))));

	$candidates = [];
	foreach ($ids as $beleg_id) {
		$candidate = cmx_camt_beleg_score_candidate($beleg_id, $entry, $amount_window);
		if ((int) ($candidate['score'] ?? 0) <= 0) {
			continue;
		}
		$candidates[] = $candidate;
	}

	\usort($candidates, static function (array $a, array $b): int {
		if ((int) $a['score'] !== (int) $b['score']) {
			return (int) $b['score'] <=> (int) $a['score'];
		}
		return (int) $b['id'] <=> (int) $a['id'];
	});

	return $candidates;
}

function cmx_camt_contact_tokens(string $name): array {
	$name = cmx_camt_normalize_name($name);
	if ($name === '') {
		return [];
	}
	$parts = \preg_split('~[^a-z0-9]+~i', $name) ?: [];
	$parts = \array_values(\array_unique(\array_filter(\array_map('trim', $parts), static function (string $part): bool {
		return $part !== '' && \strlen($part) >= 3;
	})));
	\usort($parts, static function (string $a, string $b): int {
		return \strlen($b) <=> \strlen($a);
	});
	return $parts;
}

function cmx_camt_score_contact_name_match(string $needle, string $title, string $person_name): int {
	$needle = cmx_camt_normalize_name($needle);
	$title = cmx_camt_normalize_name($title);
	$person_name = cmx_camt_normalize_name($person_name);
	if ($needle === '') {
		return 0;
	}

	$score = 0;
	foreach ([$title, $person_name] as $candidate) {
		if ($candidate === '') {
			continue;
		}
		if ($candidate === $needle) {
			$score = \max($score, 260);
			continue;
		}
		if (\str_contains($candidate, $needle) || \str_contains($needle, $candidate)) {
			$score = \max($score, 180);
		}
	}

	$tokens = cmx_camt_contact_tokens($needle);
	foreach ($tokens as $token) {
		foreach ([$title, $person_name] as $candidate) {
			if ($candidate === '') {
				continue;
			}
			if (\str_contains($candidate, $token)) {
				$score += \strlen($token) >= 6 ? 40 : 22;
			}
		}
	}

	return $score;
}

function cmx_camt_find_existing_contact_id(string $name): int {
	$name = \trim($name);
	if ($name === '') {
		return 0;
	}

	$post_type = \function_exists(__NAMESPACE__ . '\\cmx_kontakte_cpt')
		? (string) cmx_kontakte_cpt()
		: 'kontakte';
	if (!\post_type_exists($post_type)) {
		return 0;
	}

	$ids = \get_posts([
		'post_type'      => $post_type,
		'post_status'    => ['publish', 'private', 'draft', 'pending', 'future'],
		'posts_per_page' => 300,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'fields'         => 'ids',
		'no_found_rows'  => true,
	]);

	$best_id = 0;
	$best_score = 0;
	foreach ((array) $ids as $contact_id) {
		$contact_id = (int) $contact_id;
		if ($contact_id <= 0) {
			continue;
		}
		$title = \trim((string) \get_the_title($contact_id));
		$person_name = \trim((string) \get_post_meta($contact_id, '_cmx_kontakte_vorname', true) . ' ' . (string) \get_post_meta($contact_id, '_cmx_kontakte_nachname', true));
		$score = cmx_camt_score_contact_name_match($name, $title, $person_name);
		if ($score > $best_score) {
			$best_score = $score;
			$best_id = $contact_id;
		}
	}

	return $best_score >= 40 ? $best_id : 0;
}

function cmx_camt_find_or_create_contact(string $name): int {
	$name = \trim($name);
	if ($name === '') {
		return 0;
	}

	$existing_id = cmx_camt_find_existing_contact_id($name);
	if ($existing_id > 0) {
		return $existing_id;
	}

	$post_type = \function_exists(__NAMESPACE__ . '\\cmx_kontakte_cpt')
		? (string) cmx_kontakte_cpt()
		: 'kontakte';
	if (!\post_type_exists($post_type)) {
		return 0;
	}

	$inserted = \wp_insert_post([
		'post_type'   => $post_type,
		'post_status' => 'publish',
		'post_title'  => $name,
	], true);

	if (\is_wp_error($inserted) || (int) $inserted <= 0) {
		return 0;
	}

	return (int) $inserted;
}

function cmx_camt_create_new_beleg_from_entry(array $entry): array {
	$inserted = \wp_insert_post([
		'post_type'   => 'belege',
		'post_status' => 'draft',
		'post_title'  => '',
		'meta_input'  => ['_cmx_title_auto' => 1],
	], true);

	if (\is_wp_error($inserted) || (int) $inserted <= 0) {
		return ['ok' => false, 'error' => 'Beleg konnte nicht angelegt werden.'];
	}

	$beleg_id = (int) $inserted;

	if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_prepare_new_beleg_defaults')) {
		cmx_scanner_prepare_new_beleg_defaults($beleg_id);
	}

	$direction = (string) ($entry['beleg_direction'] ?? cmx_camt_beleg_direction_from_entry((string) ($entry['direction'] ?? '')));
	\update_post_meta($beleg_id, \defined(__NAMESPACE__ . '\\CMX_BELEG_META_RICHTUNG') ? CMX_BELEG_META_RICHTUNG : '_cmx_beleg_richtung', $direction);

	$booking_date = cmx_camt_normalize_date((string) ($entry['booking_date'] ?? ''));
	if ($booking_date !== '') {
		\update_post_meta($beleg_id, \defined(__NAMESPACE__ . '\\CMX_BELEG_META_RNG_DATUM') ? CMX_BELEG_META_RNG_DATUM : '_cmx_beleg_rng_datum', $booking_date);
		\update_post_meta($beleg_id, \defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM') ? CMX_BELEG_META_BEZAHLT_AM : '_cmx_beleg_bezahlt_am', $booking_date);
	}

	$amount = cmx_camt_normalize_amount((string) ($entry['amount'] ?? ''));
	if ($amount !== '') {
		\update_post_meta($beleg_id, '_cmx_beleg_summe_override', $amount);
	}

	\update_post_meta($beleg_id, \defined(__NAMESPACE__ . '\\CMX_BELEG_META_STATUS') ? CMX_BELEG_META_STATUS : '_cmx_beleg_status', 'bezahlt');

	$counterparty = \trim((string) ($entry['counterparty_name'] ?? ''));
	$counterparty_norm = \strtoupper(cmx_camt_clean_ws($counterparty));
	if ($counterparty !== '' && $counterparty_norm !== 'NOTPROVIDED') {
		$kontakt_id = cmx_camt_find_or_create_contact($counterparty);
		if ($kontakt_id > 0) {
			\update_post_meta($beleg_id, '_cmx_beleg_kontakt_id', $kontakt_id);
			\update_post_meta($beleg_id, '_cmx_beleg_kontakt_label', (string) \get_the_title($kontakt_id));
		} else {
			\update_post_meta($beleg_id, '_cmx_beleg_kontakt_label', $counterparty);
		}
	}

	$betreff = \trim((string) ($entry['remittance'] ?? ''));
	if ($betreff === '') {
		$betreff = \trim((string) ($entry['message'] ?? ''));
	}
	if ($betreff !== '') {
		\update_post_meta($beleg_id, '_cmx_beleg_betreff', \sanitize_text_field($betreff));
	}

	return [
		'ok'       => true,
		'beleg_id' => $beleg_id,
		'edit_url' => (string) \get_edit_post_link($beleg_id, 'raw'),
	];
}

function cmx_camt_row_status_html(array $entry): string {
	$signature = (string) ($entry['signature'] ?? '');
	$assignment = cmx_camt_assignment_get($signature);
	$beleg_id = (int) ($assignment['beleg_id'] ?? 0);
	if ($beleg_id <= 0) {
		return '<span class="cmx-camt-status cmx-camt-status--open">offen</span>';
	}

	$url = (string) \get_edit_post_link($beleg_id, 'raw');
	$title = \trim((string) \get_the_title($beleg_id));
	$title = $title !== '' ? $title : ('Beleg #' . $beleg_id);
	return '<a class="cmx-camt-status cmx-camt-status--assigned" href="' . \esc_url($url) . '">' . \esc_html($title) . '</a>';
}

function cmx_camt_render_candidates_panel(array $entry, float $amount_window = 0.0): string {
	$signature = (string) ($entry['signature'] ?? '');
	$assignment = cmx_camt_assignment_get($signature);
	$assigned_id = (int) ($assignment['beleg_id'] ?? 0);
	$amount_window = cmx_camt_amount_window_from_request($amount_window);
	$candidates = cmx_camt_collect_beleg_candidates($entry, $amount_window);

	$counterparty = \trim((string) ($entry['counterparty_name'] ?? ''));
	$reference = \trim((string) ($entry['reference'] ?? ''));
	$doc_ref = \trim((string) ($entry['document_ref'] ?? ''));
	$remittance = \trim((string) ($entry['remittance'] ?? ''));
	$message = \trim((string) ($entry['message'] ?? ''));

	\ob_start();
	echo '<div class="cmx-camt-detail-head">';
	echo '<div>';
	echo '<h2 style="margin:0 0 4px;">Buchung</h2>';
	echo '<p style="margin:0;color:#646970;">' . \esc_html(cmx_camt_direction_label((string) ($entry['direction'] ?? ''))) . ' vom ' . \esc_html(cmx_camt_format_date((string) ($entry['booking_date'] ?? ''))) . ' über ' . \esc_html(cmx_camt_format_amount((string) ($entry['amount'] ?? ''))) . ' ' . \esc_html((string) ($entry['currency'] ?? 'CHF')) . '</p>';
	echo '</div>';
	echo '<button type="button" class="button button-primary cmx-camt-create-beleg" data-signature="' . \esc_attr($signature) . '">Neuen Beleg anlegen</button>';
	echo '</div>';

	echo '<div class="cmx-camt-entry-meta">';
	if ($counterparty !== '') {
		echo '<p><strong>Gegenpartei:</strong> ' . \esc_html($counterparty) . '</p>';
	}
	if ($doc_ref !== '') {
		echo '<p><strong>Beleg-Nr.:</strong> ' . \esc_html($doc_ref) . '</p>';
	}
	if ($reference !== '' && $reference !== $doc_ref) {
		echo '<p><strong>Referenz:</strong> ' . \esc_html($reference) . '</p>';
	}
	if ($remittance !== '') {
		echo '<p><strong>Verwendungszweck:</strong> ' . \esc_html($remittance) . '</p>';
	}
	if ($message !== '') {
		echo '<p><strong>Info:</strong> ' . \esc_html($message) . '</p>';
	}
	echo '</div>';

	if ($assigned_id > 0) {
		$assigned_title = \trim((string) \get_the_title($assigned_id));
		$assigned_url = (string) \get_edit_post_link($assigned_id, 'raw');
		echo '<div class="notice notice-success inline cmx-camt-assignment-notice">';
		echo '<p>Diese Buchung ist bereits zugeordnet zu <a href="' . \esc_url($assigned_url) . '" target="_blank" rel="noopener noreferrer">' . \esc_html($assigned_title !== '' ? $assigned_title : ('Beleg #' . $assigned_id)) . '</a>.</p>';
		echo '<button type="button" class="button-link-delete cmx-camt-unassign-btn" data-signature="' . \esc_attr($signature) . '" aria-label="Zuordnung aufheben" title="Zuordnung aufheben"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>';
		echo '</div>';
	}

	echo '<div class="cmx-camt-candidates-head" data-signature="' . \esc_attr($signature) . '" data-amount-window="' . \esc_attr((string) $amount_window) . '">';
	echo '<button type="button" class="button button-secondary cmx-camt-range-btn" data-signature="' . \esc_attr($signature) . '" data-direction="plus" data-amount-window="' . \esc_attr((string) $amount_window) . '" title="in 10.- Schritten" aria-label="in 10.- Schritten">+</button>';
	echo '<button type="button" class="button button-secondary cmx-camt-range-btn" data-signature="' . \esc_attr($signature) . '" data-direction="minus" data-amount-window="' . \esc_attr((string) $amount_window) . '" title="in 10.- Schritten" aria-label="in 10.- Schritten">-</button>';
	$entry_amount = (float) cmx_camt_normalize_amount((string) ($entry['amount'] ?? ''));
	$target_amount = $entry_amount + $amount_window;
	$amount_from = \min($entry_amount, $target_amount);
	$amount_to = \max($entry_amount, $target_amount);
	echo '<h3 style="margin:0;">Mögliche Belege <span style="font-weight:400;color:#646970;">(' . \esc_html(cmx_camt_format_amount($amount_from)) . ' bis ' . \esc_html(cmx_camt_format_amount($amount_to)) . ')</span></h3>';
	echo '</div>';
	if (empty($candidates)) {
		echo '<p><em>Kein passender Beleg gefunden.</em></p>';
		return (string) \ob_get_clean();
	}

	echo '<div class="cmx-camt-candidates">';
	foreach ($candidates as $candidate) {
		$beleg_id = (int) ($candidate['id'] ?? 0);
		$title = \trim((string) ($candidate['title'] ?? ''));
		$edit_url = (string) \get_edit_post_link($beleg_id, 'raw');
		$score = (int) ($candidate['score'] ?? 0);
		$contact = \trim((string) ($candidate['contact'] ?? ''));
		$category = \trim((string) ($candidate['category'] ?? ''));
		$dir_label = (string) ($candidate['direction'] ?? '') === 'ausgang' ? 'Einnahme' : 'Ausgabe';
		$status = (string) ($candidate['status'] ?? '');
		$candidate_assigned = cmx_camt_beleg_is_assigned($beleg_id, $signature);
		$assign_disabled = '';
		$assign_label = 'Zuordnen';
		$assign_label_html = \esc_html($assign_label);
		$assign_class = 'button button-secondary cmx-camt-assign-beleg';
		if ($assigned_id === $beleg_id) {
			$assign_label = 'Zuordnung aufheben';
			$assign_label_html = '&nbsp;&nbsp;Zuordnung aufheben&nbsp;&nbsp;';
			$assign_class = 'button button-secondary cmx-camt-unassign-btn';
		} elseif ($candidate_assigned) {
			$assign_label = 'Bereits zugeordnet';
			$assign_label_html = \esc_html($assign_label);
			$assign_disabled = ' disabled';
		} elseif ($assigned_id > 0 && $assigned_id !== $beleg_id) {
			$assign_disabled = ' disabled';
		}

		echo '<div class="cmx-camt-candidate">';
		echo '<div class="cmx-camt-candidate-main">';
		echo '<div class="cmx-camt-candidate-title"><a href="' . \esc_url($edit_url) . '">' . \esc_html($title !== '' ? $title : ('Beleg #' . $beleg_id)) . '</a></div>';
		echo '<div class="cmx-camt-candidate-meta">';
		if ($contact !== '') {
			echo '<span>' . \esc_html($contact) . '</span>';
		}
		if ($category !== '') {
			echo '<span>' . \esc_html($category) . '</span>';
		}
		echo '<span>' . \esc_html($dir_label) . '</span>';
		echo '<span>' . \esc_html(cmx_camt_format_amount((string) ($candidate['amount'] ?? '0'))) . '</span>';
		if ((string) ($candidate['invoice_date'] ?? '') !== '') {
			echo '<span>Belegdatum ' . \esc_html(cmx_camt_format_date((string) $candidate['invoice_date'])) . '</span>';
		}
		if ((string) ($candidate['paid_date'] ?? '') !== '') {
			echo '<span>Bezahlt ' . \esc_html(cmx_camt_format_date((string) $candidate['paid_date'])) . '</span>';
		}
		if ($status !== '') {
			echo '<span>Status ' . \esc_html($status) . '</span>';
		}
		echo '<span>Score ' . \esc_html((string) $score) . '</span>';
		echo '</div>';
		echo '</div>';
		echo '<div class="cmx-camt-candidate-actions">';
		echo '<button type="button" class="' . \esc_attr($assign_class) . '" data-signature="' . \esc_attr($signature) . '" data-beleg-id="' . \esc_attr((string) $beleg_id) . '"' . $assign_disabled . '>' . $assign_label_html . '</button>';
		echo '</div>';
		echo '</div>';
	}
	echo '</div>';

	return (string) \ob_get_clean();
}

function cmx_camt_render_left_rows(array $entries): void {
	echo '<table class="widefat striped cmx-camt-table">';
	echo '<thead><tr><th>Datum</th><th>Betrag</th><th>Richtung</th><th>Gegenpartei</th><th>Referenz</th><th>Quelle</th><th>Status</th></tr></thead>';
	echo '<tbody>';
	foreach ($entries as $entry) {
		$signature = (string) ($entry['signature'] ?? '');
		if ($signature === '') {
			continue;
		}
		$source_versions = \array_values(\array_unique(\array_filter(\array_map('strval', (array) ($entry['source_versions'] ?? [])))));
		$reference = \trim((string) ($entry['document_ref'] ?? ''));
		if ($reference === '') {
			$reference = \trim((string) ($entry['reference'] ?? ''));
		}
		$counterparty = \trim((string) ($entry['counterparty_name'] ?? ''));
		$assignment = cmx_camt_assignment_get($signature);
		$row_class = (int) ($assignment['beleg_id'] ?? 0) > 0 ? ' is-assigned' : '';

		echo '<tr class="cmx-camt-entry-row' . \esc_attr($row_class) . '" data-signature="' . \esc_attr($signature) . '">';
		echo '<td>' . \esc_html(cmx_camt_format_date((string) ($entry['booking_date'] ?? ''))) . '</td>';
		echo '<td><strong>' . \esc_html(cmx_camt_format_amount((string) ($entry['amount'] ?? ''))) . '</strong> ' . \esc_html((string) ($entry['currency'] ?? 'CHF')) . '</td>';
		echo '<td>' . \esc_html(cmx_camt_direction_label((string) ($entry['direction'] ?? ''))) . '</td>';
		echo '<td>' . \esc_html($counterparty !== '' ? $counterparty : '-') . '</td>';
		echo '<td>' . \esc_html($reference !== '' ? $reference : '-') . '</td>';
		echo '<td>';
		foreach ($source_versions as $version) {
			echo '<span class="cmx-camt-chip">camt.' . \esc_html($version) . '</span> ';
		}
		echo '</td>';
		echo '<td>' . cmx_camt_row_status_html($entry) . '</td>';
		echo '</tr>';
	}
	echo '</tbody>';
	echo '</table>';
}

function cmx_camt_render_file_summary(array $files): void {
	if (empty($files)) {
		return;
	}
	echo '<div class="cmx-camt-files">';
	foreach ($files as $file) {
		if (!\is_array($file)) {
			continue;
		}
		$name = \trim((string) ($file['name'] ?? ''));
		if ($name === '') {
			continue;
		}
		echo '<div class="cmx-camt-file-card">';
		echo '<strong>' . \esc_html($name) . '</strong>';
		echo '<div class="cmx-camt-file-meta">';
		echo '<span>camt.' . \esc_html((string) ($file['doc_type'] ?? '?')) . '</span>';
		echo '<span>' . \esc_html((string) (int) ($file['entry_count'] ?? 0)) . ' Buchungen</span>';
		if ((string) ($file['account_iban'] ?? '') !== '') {
			echo '<span>' . \esc_html((string) $file['account_iban']) . '</span>';
		}
		echo '</div>';
		echo '</div>';
	}
	echo '</div>';
}

function cmx_bank_import_render_log_page(): void {
	if (!cmx_camt_current_user_can()) {
		\wp_die('forbidden');
	}

	$state = cmx_camt_state_get();
	$entries = \is_array($state['entries'] ?? null) ? (array) $state['entries'] : [];
	$files = \is_array($state['files'] ?? null) ? (array) $state['files'] : [];
	$log_file_path = cmx_bank_import_log_file_path();
	$open_log_url = \admin_url('admin-post.php?action=cmx_bank_import_open_logfile');
	$reset_url = \wp_nonce_url(\admin_url('admin-post.php?action=cmx_camt_clear_state'), 'cmx_camt_clear_state');
	$log_exists = $log_file_path !== '' && \is_file($log_file_path) && \is_readable($log_file_path);
	$ajax_url = \admin_url('admin-ajax.php');
	$nonce = \wp_create_nonce('cmx_camt_ajax');

	echo '<div class="wrap">';
	echo '<h1>Banken Import</h1>';
	echo '<p>camt.053 und camt.054 werden gemeinsam eingelesen und über ihre Buchungsmerkmale zusammengeführt, damit dieselbe Buchung nicht doppelt zugeordnet wird.</p>';

	echo '<style>
	.cmx-camt-upload{
		border:2px dashed #c3c4c7;
		border-radius:12px;
		padding:28px;
		text-align:center;
		background:#fff;
		transition:border-color .15s ease, background .15s ease, box-shadow .15s ease;
		cursor:pointer;
	}
	.cmx-camt-upload.is-dragover{
		border-color:#2271b1;
		background:#f0f6fc;
		box-shadow:0 0 0 1px rgba(34,113,177,.08) inset;
	}
	.cmx-camt-upload h2{margin:0 0 8px;font-size:20px}
	.cmx-camt-upload p{margin:0;color:#646970}
	.cmx-camt-toolbar{
		display:flex;
		align-items:center;
		justify-content:space-between;
		gap:12px;
		margin:14px 0 18px;
		flex-wrap:wrap;
	}
	.cmx-camt-layout{
		display:flex;
		gap:18px;
		align-items:stretch;
	}
	.cmx-camt-pane{
		flex:1 1 50%;
		min-width:0;
		background:#fff;
		border:1px solid #dcdcde;
		border-radius:12px;
		padding:14px;
		box-sizing:border-box;
	}
	.cmx-camt-pane h2{margin:0 0 12px}
	.cmx-camt-pane--left{max-height:72vh;overflow:auto}
	.cmx-camt-pane--right{max-height:72vh;overflow:auto}
	.cmx-camt-table th,.cmx-camt-table td{vertical-align:top}
	.cmx-camt-entry-row{cursor:pointer}
	.cmx-camt-entry-row.is-selected{background:#e5f3ff !important}
	.cmx-camt-entry-row.is-assigned td{background:#f6ffed}
	.cmx-camt-chip{
		display:inline-block;
		padding:2px 8px;
		border-radius:999px;
		background:#eef4fb;
		color:#1d4f91;
		font-size:11px;
		font-weight:600;
		line-height:1.8;
	}
	.cmx-camt-status{
		display:inline-block;
		padding:3px 8px;
		border-radius:999px;
		font-size:12px;
		font-weight:600;
		text-decoration:none;
	}
	.cmx-camt-status--open{background:#fff2d6;color:#8a6700}
	.cmx-camt-status--assigned{background:#ecf7ed;color:#156b2a}
	.cmx-camt-files{
		display:grid;
		grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
		gap:10px;
		margin:0 0 16px;
	}
	.cmx-camt-file-card{
		background:#fff;
		border:1px solid #dcdcde;
		border-radius:10px;
		padding:12px;
	}
	.cmx-camt-file-meta{
		display:flex;
		gap:8px;
		flex-wrap:wrap;
		margin-top:6px;
		color:#646970;
		font-size:12px;
	}
	.cmx-camt-detail-head{
		display:flex;
		justify-content:space-between;
		align-items:flex-start;
		gap:12px;
		margin-bottom:12px;
	}
	.cmx-camt-entry-meta{
		display:grid;
		grid-template-columns:1fr;
		gap:6px;
		margin-bottom:14px;
	}
	.cmx-camt-entry-meta p{margin:0}
	.cmx-camt-assignment-notice{
		display:flex;
		align-items:flex-start;
		gap:12px;
	}
	.cmx-camt-assignment-notice p{margin:0}
	.cmx-camt-unassign-btn{
		margin-left:auto;
		padding:0 !important;
		line-height:1;
	}
	.cmx-camt-unassign-btn .dashicons{
		width:20px;
		height:20px;
		font-size:20px;
	}
	.cmx-camt-candidates-head{
		display:flex;
		align-items:center;
		gap:8px;
		margin:18px 0 10px;
	}
	.cmx-camt-candidates-head .button{
		min-width:32px;
		padding:0 8px;
	}
	.cmx-camt-candidates{display:flex;flex-direction:column;gap:10px}
	.cmx-camt-candidate{
		display:flex;
		justify-content:space-between;
		align-items:flex-start;
		gap:12px;
		border:1px solid #dcdcde;
		border-radius:10px;
		padding:12px;
		background:#fafafa;
	}
	.cmx-camt-candidate-main{min-width:0;flex:1 1 auto}
	.cmx-camt-candidate-title{font-weight:700;margin-bottom:6px}
	.cmx-camt-candidate-title a{text-decoration:none}
	.cmx-camt-candidate-meta{
		display:flex;
		gap:8px;
		flex-wrap:wrap;
		font-size:12px;
		color:#50575e;
	}
	#cmx-camt-messages{margin:12px 0 0}
	.cmx-camt-empty{
		border:1px dashed #c3c4c7;
		border-radius:12px;
		padding:22px;
		background:#fff;
		text-align:center;
		color:#646970;
	}
	@media (max-width: 1200px){
		.cmx-camt-layout{flex-direction:column}
		.cmx-camt-pane{max-height:none}
	}
	</style>';

	echo '<div id="cmx-camt-upload" class="cmx-camt-upload" tabindex="0" role="button" aria-label="CAMT Dateien hochladen">';
	echo '<h2>camt.053 / camt.054 hier ablegen</h2>';
	echo '<p>XML-Dateien per Drag & Drop oder per Klick auswählen. Bereits geladene Dateien werden mit den neuen CAMT-Dateien zusammengeführt.</p>';
	echo '<input type="file" id="cmx-camt-files" accept=".xml,text/xml,application/xml" multiple style="display:none;">';
	echo '</div>';

	echo '<div class="cmx-camt-toolbar">';
	echo '<div><strong>Aktive CAMT-Arbeitsliste:</strong> ' . \esc_html((string) \count($entries)) . ' Buchungen</div>';
	echo '<div style="display:flex;gap:8px;flex-wrap:wrap;">';
	if (!empty($entries)) {
		echo '<a class="button" href="' . \esc_url($reset_url) . '">CAMT Arbeitsliste leeren</a>';
	}
	if ($log_exists && cmx_camt_is_cloudmeister_user()) {
		echo '<a class="button" href="' . \esc_url($open_log_url) . '" target="_blank" rel="noopener noreferrer">Logdatei öffnen</a>';
	}
	echo '</div>';
	echo '</div>';

	echo '<div id="cmx-camt-messages"></div>';

	if (!empty($files)) {
		cmx_camt_render_file_summary($files);
	}

	if (empty($entries)) {
		echo '<div class="cmx-camt-empty">Noch keine CAMT-Datei geladen.</div>';
	} else {
		echo '<div class="cmx-camt-layout">';
		echo '<div class="cmx-camt-pane cmx-camt-pane--left">';
		echo '<h2>Buchungen</h2>';
		cmx_camt_render_left_rows($entries);
		echo '</div>';

		echo '<div class="cmx-camt-pane cmx-camt-pane--right">';
		echo '<div id="cmx-camt-detail"><div class="cmx-camt-empty">Links eine Buchung auswählen.</div></div>';
		echo '</div>';
		echo '</div>';
	}

	$page_url = \admin_url('edit.php?post_type=scanner&page=cmx-camt-import-log');
	$ajax_json = (string) \wp_json_encode($ajax_url);
	$nonce_json = (string) \wp_json_encode($nonce);
	$page_json = (string) \wp_json_encode($page_url);
	$script = <<<HTML
<script>
(function(){
	var ajaxUrl = {$ajax_json};
	var nonce = {$nonce_json};
	var pageUrl = {$page_json};
	var upload = document.getElementById("cmx-camt-upload");
	var input = document.getElementById("cmx-camt-files");
	var messages = document.getElementById("cmx-camt-messages");
	var detail = document.getElementById("cmx-camt-detail");
	var rows = Array.prototype.slice.call(document.querySelectorAll(".cmx-camt-entry-row"));
	var currentSignature = "";

	function escapeHtml(text){
		return String(text || "").replace(/[&<>"']/g, function(c){
			return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[c] || c;
		});
	}

	function setMessage(type, text){
		if (!messages) return;
		messages.innerHTML = '<div class="notice notice-' + escapeHtml(type) + ' inline"><p>' + escapeHtml(text) + '</p></div>';
	}

	function selectRow(signature){
		currentSignature = String(signature || "");
		rows.forEach(function(row){
			row.classList.toggle("is-selected", row.getAttribute("data-signature") === currentSignature);
		});
	}

	function uploadFiles(files){
		if (!files || !files.length) return;
		var fd = new FormData();
		fd.append("action", "cmx_camt_upload_files");
		fd.append("nonce", nonce);
		Array.prototype.forEach.call(files, function(file){
			fd.append("camt_files[]", file);
		});
		upload.classList.add("is-dragover");
		setMessage("info", "CAMT-Dateien werden eingelesen...");
		fetch(ajaxUrl, {method:"POST", body:fd, credentials:"same-origin"})
			.then(function(r){ return r.json(); })
			.then(function(res){
				if (!res || !res.success) {
					throw new Error((res && res.data && res.data.message) ? res.data.message : "Upload fehlgeschlagen.");
				}
				window.location.href = pageUrl;
			})
			.catch(function(err){
				setMessage("error", err && err.message ? err.message : "Upload fehlgeschlagen.");
			})
			.finally(function(){
				upload.classList.remove("is-dragover");
				if (input) input.value = "";
			});
	}

	function getPanelAmountWindow(source){
		var panel = source ? source.closest(".cmx-camt-candidates-head") : null;
		if (!panel && detail) {
			panel = detail.querySelector(".cmx-camt-candidates-head");
		}
		var raw = panel ? (panel.getAttribute("data-amount-window") || "0") : "0";
		var num = parseFloat(raw);
		return isNaN(num) ? 0 : num;
	}

	function loadDetail(signature, amountWindow){
		if (!detail || !signature) return;
		if (typeof amountWindow === "undefined") {
			amountWindow = 0;
		}
		selectRow(signature);
		detail.innerHTML = '<div class="cmx-camt-empty">Lade mögliche Belege...</div>';
		var fd = new FormData();
		fd.append("action", "cmx_camt_load_candidates");
		fd.append("nonce", nonce);
		fd.append("signature", signature);
		fd.append("amount_window", String(amountWindow));
		fetch(ajaxUrl, {method:"POST", body:fd, credentials:"same-origin"})
			.then(function(r){ return r.json(); })
			.then(function(res){
				if (!res || !res.success) {
					throw new Error((res && res.data && res.data.message) ? res.data.message : "Kandidaten konnten nicht geladen werden.");
				}
				detail.innerHTML = String((res.data && res.data.html) || "");
			})
			.catch(function(err){
				detail.innerHTML = '<div class="notice notice-error inline"><p>' + escapeHtml(err && err.message ? err.message : "Fehler") + '</p></div>';
			});
	}

	function refreshStatusCell(signature, html){
		rows.forEach(function(row){
			if (row.getAttribute("data-signature") !== signature) return;
			var cell = row.querySelector("td:last-child");
			if (cell) cell.innerHTML = html;
			if (String(html).indexOf("cmx-camt-status--open") !== -1) {
				row.classList.remove("is-assigned");
			} else {
				row.classList.add("is-assigned");
			}
		});
	}

	if (upload && input) {
		upload.addEventListener("click", function(){ input.click(); });
		upload.addEventListener("keydown", function(e){
			if (e.key === "Enter" || e.key === " ") {
				e.preventDefault();
				input.click();
			}
		});
		input.addEventListener("change", function(){ uploadFiles(input.files); });
		["dragenter","dragover"].forEach(function(evt){
			upload.addEventListener(evt, function(e){
				e.preventDefault();
				e.stopPropagation();
				upload.classList.add("is-dragover");
			});
		});
		["dragleave","dragend","drop"].forEach(function(evt){
			upload.addEventListener(evt, function(e){
				e.preventDefault();
				e.stopPropagation();
				if (evt !== "drop") {
					upload.classList.remove("is-dragover");
				}
			});
		});
		upload.addEventListener("drop", function(e){
			upload.classList.remove("is-dragover");
			var files = e.dataTransfer ? e.dataTransfer.files : null;
			uploadFiles(files);
		});
	}

	rows.forEach(function(row){
		row.addEventListener("click", function(){
			loadDetail(row.getAttribute("data-signature") || "");
		});
	});

	document.addEventListener("click", function(e){
		var assignBtn = e.target.closest(".cmx-camt-assign-beleg");
		if (assignBtn) {
			e.preventDefault();
			var signature = assignBtn.getAttribute("data-signature") || "";
			var belegId = assignBtn.getAttribute("data-beleg-id") || "";
			if (!signature || !belegId) return;
			var amountWindow = getPanelAmountWindow(assignBtn);
			assignBtn.disabled = true;
			var fd = new FormData();
			fd.append("action", "cmx_camt_assign_beleg");
			fd.append("nonce", nonce);
			fd.append("signature", signature);
			fd.append("beleg_id", belegId);
			fd.append("amount_window", String(amountWindow));
			fetch(ajaxUrl, {method:"POST", body:fd, credentials:"same-origin"})
				.then(function(r){ return r.json(); })
				.then(function(res){
					if (!res || !res.success) {
						throw new Error((res && res.data && res.data.message) ? res.data.message : "Zuordnung fehlgeschlagen.");
					}
					if (res.data && res.data.status_html) {
						refreshStatusCell(signature, String(res.data.status_html));
					}
					if (res.data && res.data.html && detail) {
						detail.innerHTML = String(res.data.html);
					}
				})
				.catch(function(err){
					setMessage("error", err && err.message ? err.message : "Zuordnung fehlgeschlagen.");
					assignBtn.disabled = false;
				});
			return;
		}

		var createBtn = e.target.closest(".cmx-camt-create-beleg");
		if (createBtn) {
			e.preventDefault();
			var signature = createBtn.getAttribute("data-signature") || "";
			if (!signature) return;
			var amountWindow = getPanelAmountWindow(createBtn);
			createBtn.disabled = true;
			var fd = new FormData();
			fd.append("action", "cmx_camt_create_beleg");
			fd.append("nonce", nonce);
			fd.append("signature", signature);
			fd.append("amount_window", String(amountWindow));
			fetch(ajaxUrl, {method:"POST", body:fd, credentials:"same-origin"})
				.then(function(r){ return r.json(); })
				.then(function(res){
					if (!res || !res.success) {
						throw new Error((res && res.data && res.data.message) ? res.data.message : "Beleg konnte nicht angelegt werden.");
					}
					if (res.data && res.data.status_html) {
						refreshStatusCell(signature, String(res.data.status_html));
					}
					if (res.data && res.data.html && detail) {
						detail.innerHTML = String(res.data.html);
					}
					if (res.data && res.data.edit_url) {
						window.open(String(res.data.edit_url), "_blank");
					}
				})
				.catch(function(err){
					setMessage("error", err && err.message ? err.message : "Beleg konnte nicht angelegt werden.");
					createBtn.disabled = false;
				});
			return;
		}

		var unassignBtn = e.target.closest(".cmx-camt-unassign-btn");
		if (unassignBtn) {
			e.preventDefault();
			var signature = unassignBtn.getAttribute("data-signature") || "";
			if (!signature) return;
			var amountWindow = getPanelAmountWindow(unassignBtn);
			unassignBtn.disabled = true;
			var fd = new FormData();
			fd.append("action", "cmx_camt_unassign_beleg");
			fd.append("nonce", nonce);
			fd.append("signature", signature);
			fd.append("amount_window", String(amountWindow));
			fetch(ajaxUrl, {method:"POST", body:fd, credentials:"same-origin"})
				.then(function(r){ return r.json(); })
				.then(function(res){
					if (!res || !res.success) {
						throw new Error((res && res.data && res.data.message) ? res.data.message : "Zuordnung konnte nicht aufgehoben werden.");
					}
					if (res.data && res.data.status_html) {
						refreshStatusCell(signature, String(res.data.status_html));
					}
					if (res.data && res.data.html && detail) {
						detail.innerHTML = String(res.data.html);
					}
				})
				.catch(function(err){
					setMessage("error", err && err.message ? err.message : "Zuordnung konnte nicht aufgehoben werden.");
					unassignBtn.disabled = false;
				});
			return;
		}

		var rangeBtn = e.target.closest(".cmx-camt-range-btn");
		if (rangeBtn) {
			e.preventDefault();
			var signature = rangeBtn.getAttribute("data-signature") || "";
			if (!signature) return;
			var amountWindow = parseFloat(rangeBtn.getAttribute("data-amount-window") || "0");
			if (isNaN(amountWindow)) amountWindow = 0;
			var direction = rangeBtn.getAttribute("data-direction") || "";
			if (direction === "plus") {
				amountWindow += 10;
			} else if (direction === "minus") {
				amountWindow -= 10;
			}
			loadDetail(signature, amountWindow);
			return;
		}
	});

	if (rows.length) {
		loadDetail(rows[0].getAttribute("data-signature") || "");
	}
})();
</script>
HTML;
	echo $script;

	echo '</div>';
}

\add_action('admin_menu', function (): void {
	\add_submenu_page(
		'edit.php?post_type=scanner',
		'Banken Import',
		'Banken',
		'manage_options',
		'cmx-camt-import-log',
		__NAMESPACE__ . '\\cmx_bank_import_render_log_page'
	);
});

\add_action('admin_post_cmx_bank_import_open_logfile', function (): void {
	if (!cmx_camt_current_user_can()) {
		\wp_die('forbidden');
	}

	$path = cmx_bank_import_log_file_path();
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

\add_action('admin_post_cmx_camt_clear_state', function (): void {
	if (!cmx_camt_current_user_can()) {
		\wp_die('forbidden');
	}
	\check_admin_referer('cmx_camt_clear_state');
	cmx_camt_state_clear();
	\wp_safe_redirect(\admin_url('edit.php?post_type=scanner&page=cmx-camt-import-log'));
	exit;
});

\add_action('wp_ajax_cmx_camt_upload_files', function (): void {
	if (!cmx_camt_current_user_can()) {
		\wp_send_json_error(['message' => 'forbidden'], 403);
	}
	\check_ajax_referer('cmx_camt_ajax', 'nonce');

	if (empty($_FILES['camt_files'])) {
		\wp_send_json_error(['message' => 'Keine CAMT-Datei empfangen.'], 400);
	}

	$normalized_files = cmx_camt_normalize_uploaded_files((array) $_FILES['camt_files']);
	$parsed = cmx_camt_upload_and_parse_files($normalized_files);
	$existing = cmx_camt_state_get();
	$existing_entries = \array_values(\array_filter(\array_map(static function ($entry) {
		return \is_array($entry) ? $entry : null;
	}, (array) ($existing['entries'] ?? []))));

	$entries = cmx_camt_merge_entry_collections($existing_entries, (array) ($parsed['entries'] ?? []));
	$files = \array_values(\array_merge((array) ($existing['files'] ?? []), (array) ($parsed['files'] ?? [])));

	if (empty($entries) && empty($files)) {
		$message = !empty($parsed['errors']) ? \implode(' ', (array) $parsed['errors']) : 'Es wurden keine gültigen CAMT-Buchungen gefunden.';
		\wp_send_json_error(['message' => $message], 400);
	}

	$state = [
		'loaded_at' => (string) \wp_date('c'),
		'files'     => $files,
		'entries'   => $entries,
	];
	cmx_camt_state_set($state);
	cmx_bank_import_log('CAMT Upload verarbeitet', [
		'files'        => \count($files),
		'entries'      => \count($entries),
		'new_files'    => \count((array) ($parsed['files'] ?? [])),
		'errors'       => \array_values((array) ($parsed['errors'] ?? [])),
	]);

	$response = [
		'message' => 'CAMT-Dateien geladen.',
		'count'   => \count($entries),
	];
	if (!empty($parsed['errors'])) {
		$response['message'] .= ' Hinweise: ' . \implode(' ', (array) $parsed['errors']);
	}

	\wp_send_json_success($response);
});

\add_action('wp_ajax_cmx_camt_load_candidates', function (): void {
	if (!cmx_camt_current_user_can()) {
		\wp_send_json_error(['message' => 'forbidden'], 403);
	}
	\check_ajax_referer('cmx_camt_ajax', 'nonce');

	$signature = \sanitize_key((string) ($_POST['signature'] ?? ''));
	$amount_window = cmx_camt_amount_window_from_request($_POST['amount_window'] ?? 0);
	$state = cmx_camt_state_get();
	$entry = \is_array($state['entries'][$signature] ?? null) ? (array) $state['entries'][$signature] : [];
	if ($signature === '' || empty($entry)) {
		\wp_send_json_error(['message' => 'Buchung nicht gefunden.'], 404);
	}

	\wp_send_json_success(['html' => cmx_camt_render_candidates_panel($entry, $amount_window)]);
});

\add_action('wp_ajax_cmx_camt_assign_beleg', function (): void {
	if (!cmx_camt_current_user_can()) {
		\wp_send_json_error(['message' => 'forbidden'], 403);
	}
	\check_ajax_referer('cmx_camt_ajax', 'nonce');

	$signature = \sanitize_key((string) ($_POST['signature'] ?? ''));
	$beleg_id = (int) ($_POST['beleg_id'] ?? 0);
	$amount_window = cmx_camt_amount_window_from_request($_POST['amount_window'] ?? 0);
	$state = cmx_camt_state_get();
	$entry = \is_array($state['entries'][$signature] ?? null) ? (array) $state['entries'][$signature] : [];
	if ($signature === '' || $beleg_id <= 0 || empty($entry)) {
		\wp_send_json_error(['message' => 'Ungültige Zuordnung.'], 400);
	}
	if ((string) \get_post_type($beleg_id) !== 'belege') {
		\wp_send_json_error(['message' => 'Beleg nicht gefunden.'], 404);
	}
	if (cmx_camt_beleg_is_assigned($beleg_id, $signature)) {
		\wp_send_json_error(['message' => 'Dieser Beleg ist bereits einer anderen Buchung zugeordnet.'], 409);
	}

	if (!cmx_camt_store_assignment($signature, $beleg_id, $entry)) {
		\wp_send_json_error(['message' => 'Diese Buchung ist bereits einem anderen Beleg zugeordnet.'], 409);
	}

	$booking_date = cmx_camt_normalize_date((string) ($entry['booking_date'] ?? ''));
	if ($booking_date !== '') {
		\update_post_meta(
			$beleg_id,
			\defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM') ? CMX_BELEG_META_BEZAHLT_AM : '_cmx_beleg_bezahlt_am',
			$booking_date
		);
	}

	cmx_bank_import_log('CAMT Buchung zugeordnet', ['signature' => $signature, 'beleg_id' => $beleg_id]);
	\wp_send_json_success([
		'status_html' => cmx_camt_row_status_html($entry),
		'html'        => cmx_camt_render_candidates_panel($entry, $amount_window),
	]);
});

\add_action('wp_ajax_cmx_camt_unassign_beleg', function (): void {
	if (!cmx_camt_current_user_can()) {
		\wp_send_json_error(['message' => 'forbidden'], 403);
	}
	\check_ajax_referer('cmx_camt_ajax', 'nonce');

	$signature = \sanitize_key((string) ($_POST['signature'] ?? ''));
	$amount_window = cmx_camt_amount_window_from_request($_POST['amount_window'] ?? 0);
	$state = cmx_camt_state_get();
	$entry = \is_array($state['entries'][$signature] ?? null) ? (array) $state['entries'][$signature] : [];
	if ($signature === '' || empty($entry)) {
		\wp_send_json_error(['message' => 'Buchung nicht gefunden.'], 404);
	}

	if (!cmx_camt_remove_assignment($signature)) {
		\wp_send_json_error(['message' => 'Zuordnung konnte nicht aufgehoben werden.'], 400);
	}

	cmx_bank_import_log('CAMT Zuordnung aufgehoben', ['signature' => $signature]);
	\wp_send_json_success([
		'status_html' => cmx_camt_row_status_html($entry),
		'html'        => cmx_camt_render_candidates_panel($entry, $amount_window),
	]);
});

\add_action('wp_ajax_cmx_camt_create_beleg', function (): void {
	if (!cmx_camt_current_user_can()) {
		\wp_send_json_error(['message' => 'forbidden'], 403);
	}
	\check_ajax_referer('cmx_camt_ajax', 'nonce');

	$signature = \sanitize_key((string) ($_POST['signature'] ?? ''));
	$amount_window = cmx_camt_amount_window_from_request($_POST['amount_window'] ?? 0);
	$state = cmx_camt_state_get();
	$entry = \is_array($state['entries'][$signature] ?? null) ? (array) $state['entries'][$signature] : [];
	if ($signature === '' || empty($entry)) {
		\wp_send_json_error(['message' => 'Buchung nicht gefunden.'], 404);
	}

	$current_assignment = cmx_camt_assignment_get($signature);
	$current_beleg_id = (int) ($current_assignment['beleg_id'] ?? 0);
	if ($current_beleg_id > 0) {
		\wp_send_json_success([
			'status_html' => cmx_camt_row_status_html($entry),
			'html'        => cmx_camt_render_candidates_panel($entry, $amount_window),
			'edit_url'    => (string) \get_edit_post_link($current_beleg_id, 'raw'),
		]);
	}

	$created = cmx_camt_create_new_beleg_from_entry($entry);
	if (empty($created['ok'])) {
		\wp_send_json_error(['message' => (string) ($created['error'] ?? 'Beleg konnte nicht angelegt werden.')], 500);
	}

	$beleg_id = (int) ($created['beleg_id'] ?? 0);
	if ($beleg_id <= 0 || !cmx_camt_store_assignment($signature, $beleg_id, $entry)) {
		\wp_send_json_error(['message' => 'Buchung konnte nicht zugeordnet werden.'], 500);
	}

	cmx_bank_import_log('CAMT Beleg neu angelegt', ['signature' => $signature, 'beleg_id' => $beleg_id]);
	\wp_send_json_success([
		'status_html' => cmx_camt_row_status_html($entry),
		'html'        => cmx_camt_render_candidates_panel($entry, $amount_window),
		'edit_url'    => (string) ($created['edit_url'] ?? ''),
	]);
});
