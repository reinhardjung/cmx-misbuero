<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

function cmx_help_once_db_migration_done_option(): string {
	return 'cmx_help_once_db_migration_v1_done';
}

function cmx_help_once_db_migration_progress_option(): string {
	return 'cmx_help_once_db_migration_v1_progress';
}

function cmx_help_once_db_migration_summary_option(): string {
	return 'cmx_help_once_db_migration_v1_summary';
}

function cmx_help_once_db_migration_batch_size(): int {
	return 200;
}

function cmx_help_once_db_migration_time_budget_seconds(): float {
	return 8.0;
}

function cmx_help_once_plugin_root_path(): string {
	return \dirname(__DIR__);
}

function cmx_help_once_require_runtime_dependencies(): void {
	$root = cmx_help_once_plugin_root_path();
	$files = [
		__NAMESPACE__ . '\\cmx_notizen_load_rows' => $root . '/includes/notizen.php',
		__NAMESPACE__ . '\\cmx_zu_projekt_load_ids' => $root . '/includes/projekte.php',
		__NAMESPACE__ . '\\cmx_kommunikation_read_contacts' => $root . '/src/kontakte/kommunikation.php',
		__NAMESPACE__ . '\\cmxbu_meta_array' => $root . '/src/belege/add_tasks.php',
		__NAMESPACE__ . '\\cmx_beleg_anzahlungen_get_rows' => $root . '/src/belege/anzahlungen.php',
		__NAMESPACE__ . '\\cmx_camt_beleg_partial_rows' => $root . '/src/scanner/import_camt.php',
		__NAMESPACE__ . '\\cmx_dok_cleanup_legacy_kassenbuch_links' => $root . '/src/dokumente/modules.php',
	];

	foreach ($files as $function_name => $file) {
		if (!\function_exists((string) $function_name) && \file_exists($file)) {
			require_once $file;
		}
	}
}

function cmx_help_once_db_migration_default_progress(): array {
	return [
		'started_at' => (string) \current_time('mysql'),
		'module_index' => 0,
		'cursor' => 0,
		'stats' => [],
	];
}

function cmx_help_once_db_migration_get_progress(): array {
	$progress = \get_option(cmx_help_once_db_migration_progress_option(), []);
	$progress = \is_array($progress) ? $progress : [];

	return [
		'started_at' => (string) ($progress['started_at'] ?? (string) \current_time('mysql')),
		'module_index' => \max(0, (int) ($progress['module_index'] ?? 0)),
		'cursor' => \max(0, (int) ($progress['cursor'] ?? 0)),
		'stats' => \is_array($progress['stats'] ?? null) ? (array) $progress['stats'] : [],
	];
}

function cmx_help_once_db_migration_save_progress(array $progress): void {
	\update_option(cmx_help_once_db_migration_progress_option(), $progress, false);
}

function cmx_help_once_db_migration_bump_stats(array &$progress, string $module_name, string $label, int $processed = 0, int $migrated = 0): void {
	if (!isset($progress['stats'][$module_name]) || !\is_array($progress['stats'][$module_name])) {
		$progress['stats'][$module_name] = [
			'label' => $label,
			'processed' => 0,
			'migrated' => 0,
		];
	}

	$progress['stats'][$module_name]['label'] = $label;
	$progress['stats'][$module_name]['processed'] = (int) ($progress['stats'][$module_name]['processed'] ?? 0) + $processed;
	$progress['stats'][$module_name]['migrated'] = (int) ($progress['stats'][$module_name]['migrated'] ?? 0) + $migrated;
}

function cmx_help_once_decode_array_meta($raw, ?bool &$decoded = null): array {
	$decoded = false;

	if (\is_array($raw)) {
		$decoded = true;
		return $raw;
	}

	if (!\is_string($raw)) {
		return [];
	}

	$trim = \trim($raw);
	if ($trim === '') {
		$decoded = true;
		return [];
	}

	$json = \json_decode($trim, true);
	if (\json_last_error() === \JSON_ERROR_NONE && \is_array($json)) {
		$decoded = true;
		return $json;
	}

	$maybe = @\maybe_unserialize($trim);
	if (\is_array($maybe)) {
		$decoded = true;
		return $maybe;
	}

	return [];
}

function cmx_help_once_normalize_scalar_list(array $values): array {
	$out = [];
	$seen = [];

	foreach ($values as $value) {
		if (\is_array($value) || \is_object($value)) {
			continue;
		}

		if (\is_bool($value)) {
			$normalized = $value ? '1' : '0';
		} elseif (\is_int($value) || \is_float($value)) {
			$normalized = $value;
		} else {
			$normalized = \trim((string) $value);
			if ($normalized === '') {
				continue;
			}
			if (\preg_match('/^-?\d+$/', $normalized)) {
				$normalized = (int) $normalized;
			}
		}

		$hash = \gettype($normalized) . ':' . (string) $normalized;
		if (isset($seen[$hash])) {
			continue;
		}
		$seen[$hash] = true;
		$out[] = $normalized;
	}

	return \array_values($out);
}

function cmx_help_once_decode_scalar_list_meta($raw, ?bool &$decoded = null): array {
	$decoded = false;

	if (\is_array($raw)) {
		$decoded = true;
		return cmx_help_once_normalize_scalar_list($raw);
	}

	if (!\is_string($raw)) {
		return [];
	}

	$trim = \trim($raw);
	if ($trim === '') {
		$decoded = true;
		return [];
	}

	$decoded_array = cmx_help_once_decode_array_meta($raw, $decoded_from_array);
	if ($decoded_from_array) {
		$decoded = true;
		return cmx_help_once_normalize_scalar_list($decoded_array);
	}

	if (\preg_match('/^-?\d+$/', $trim)) {
		$decoded = true;
		return [(int) $trim];
	}

	if (\preg_match('/^[A-Za-z0-9_-]+$/', $trim)) {
		$decoded = true;
		return [$trim];
	}

	return [];
}

function cmx_help_once_meta_has_value(int $post_id, string $meta_key): bool {
	if ($meta_key === '' || !\metadata_exists('post', $post_id, $meta_key)) {
		return false;
	}

	$value = \get_post_meta($post_id, $meta_key, true);
	if (\is_array($value)) {
		return $value !== [];
	}

	return \trim((string) $value) !== '';
}

function cmx_help_once_delete_meta_keys(int $post_id, array $meta_keys): void {
	foreach ($meta_keys as $meta_key) {
		$meta_key = (string) $meta_key;
		if ($meta_key !== '') {
			\delete_post_meta($post_id, $meta_key);
		}
	}
}

function cmx_help_once_post_has_any_meta_keys(int $post_id, array $meta_keys): bool {
	foreach ($meta_keys as $meta_key) {
		$meta_key = (string) $meta_key;
		if ($meta_key !== '' && \metadata_exists('post', $post_id, $meta_key)) {
			return true;
		}
	}

	return false;
}

function cmx_help_once_query_batch_ids(array $query_args, int $cursor, int $limit): array {
	global $wpdb;

	$limit = \max(1, $limit);
	$cursor = \max(0, $cursor);

	$post_types = $query_args['post_type'] ?? [];
	if (!\is_array($post_types)) {
		$post_types = [$post_types];
	}
	$post_types = \array_values(\array_filter(\array_map('sanitize_key', $post_types), static fn(string $post_type): bool => $post_type !== ''));
	if ($post_types === []) {
		return [];
	}

	$where = ["ID > %d"];
	$params = [$cursor];

	$type_placeholders = \implode(', ', \array_fill(0, \count($post_types), '%s'));
	$where[] = "post_type IN ({$type_placeholders})";
	foreach ($post_types as $post_type) {
		$params[] = $post_type;
	}

	$post_status = $query_args['post_status'] ?? 'any';
	if ($post_status !== 'any') {
		$statuses = \is_array($post_status) ? $post_status : [$post_status];
		$statuses = \array_values(\array_filter(\array_map('sanitize_key', $statuses), static fn(string $status): bool => $status !== ''));
		if ($statuses !== []) {
			$status_placeholders = \implode(', ', \array_fill(0, \count($statuses), '%s'));
			$where[] = "post_status IN ({$status_placeholders})";
			foreach ($statuses as $status) {
				$params[] = $status;
			}
		}
	}

	$params[] = $limit;
	$sql = "SELECT ID FROM {$wpdb->posts} WHERE " . \implode(' AND ', $where) . ' ORDER BY ID ASC LIMIT %d';
	$prepared = $wpdb->prepare($sql, $params);
	if (!\is_string($prepared) || $prepared === '') {
		return [];
	}

	$ids = $wpdb->get_col($prepared);
	if (!\is_array($ids) || $ids === []) {
		return [];
	}

	return \array_values(\array_map('intval', $ids));
}

function cmx_help_once_kontakte_kommunikation_direct_meta_keys(): array {
	return [
		'_cmx_telefon_1', '_cmx_telefon_2', '_cmx_telefon_3',
		'_cmx_email_1', '_cmx_email_2', '_cmx_email_3',
		'_cmx_telefon_label_1', '_cmx_telefon_label_2', '_cmx_telefon_label_3',
		'_cmx_email_label_1', '_cmx_email_label_2', '_cmx_email_label_3',
	];
}

function cmx_help_once_kontakte_kommunikation_query_args(): array {
	return [
		'post_type' => 'kontakte',
	];
}

function cmx_help_once_kontakte_kommunikation_needs_migration(int $post_id): bool {
	$has_bundle = \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_read_legacy_bundle')
		&& cmx_kommunikation_read_legacy_bundle($post_id) !== [];
	$has_direct_legacy = false;
	foreach (cmx_help_once_kontakte_kommunikation_direct_meta_keys() as $meta_key) {
		if (cmx_help_once_meta_has_value($post_id, $meta_key)) {
			$has_direct_legacy = true;
			break;
		}
	}

	if ($has_bundle || $has_direct_legacy) {
		return true;
	}

	$has_flat = \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_has_flat_storage')
		&& cmx_kommunikation_has_flat_storage($post_id);
	if ($has_flat) {
		return false;
	}

	return false;
}

function cmx_help_once_migrate_kontakte_kommunikation_post(int $post_id): bool {
	if (
		!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_read_contacts')
		|| !\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_persist_contacts')
		|| !cmx_help_once_kontakte_kommunikation_needs_migration($post_id)
	) {
		return false;
	}

	$contacts = cmx_kommunikation_read_contacts($post_id);
	cmx_kommunikation_persist_contacts($post_id, \is_array($contacts) ? $contacts : []);
	return true;
}

function cmx_help_once_notizen_query_args(string $post_type): array {
	return [
		'post_type' => $post_type,
	];
}

function cmx_help_once_migrate_notizen_post(int $post_id, string $post_type): bool {
	$meta_key = cmx_notizen_meta_key_for_post_type($post_type);
	$canonical_exists = \metadata_exists('post', $post_id, $meta_key);
	$raw = \get_post_meta($post_id, $meta_key, true);
	$legacy_present = false;
	foreach (cmx_notizen_legacy_meta_keys($post_type) as $legacy_key) {
		if (\metadata_exists('post', $post_id, (string) $legacy_key)) {
			$legacy_present = true;
			break;
		}
	}

	if (!$legacy_present && (!$canonical_exists || \is_array($raw))) {
		return false;
	}

	$rows = cmx_notizen_load_rows($post_id, $post_type);
	if ($rows === []) {
		\delete_post_meta($post_id, $meta_key);
	} else {
		\update_post_meta($post_id, $meta_key, $rows);
	}

	cmx_help_once_delete_meta_keys($post_id, cmx_notizen_legacy_meta_keys($post_type));
	return true;
}

function cmx_help_once_projekte_query_args(string $post_type): array {
	return [
		'post_type' => $post_type,
	];
}

function cmx_help_once_migrate_projekte_post(int $post_id, string $post_type): bool {
	$meta_key = cmx_zu_projekt_meta_key($post_type);
	$canonical_exists = \metadata_exists('post', $post_id, $meta_key);
	$raw = \get_post_meta($post_id, $meta_key, true);
	$legacy_present = false;
	foreach (cmx_zu_projekt_legacy_meta_keys($post_type) as $legacy_key) {
		if (\metadata_exists('post', $post_id, (string) $legacy_key)) {
			$legacy_present = true;
			break;
		}
	}

	if (!$legacy_present && (!$canonical_exists || \is_array($raw))) {
		return false;
	}

	$ids = cmx_zu_projekt_load_ids($post_id, $post_type);
	if ($ids === []) {
		\delete_post_meta($post_id, $meta_key);
	} else {
		\update_post_meta($post_id, $meta_key, \array_values(\array_unique(\array_map('intval', $ids))));
	}

	cmx_help_once_delete_meta_keys($post_id, cmx_zu_projekt_legacy_meta_keys($post_type));
	return true;
}

function cmx_help_once_migrate_beleg_positionen_post(int $post_id): bool {
	$meta_key = '_cmx_beleg_positionen';
	if (!\metadata_exists('post', $post_id, $meta_key)) {
		return false;
	}

	$raw = \get_post_meta($post_id, $meta_key, true);
	if (\is_array($raw)) {
		return false;
	}

	$decoded_ok = false;
	$rows = cmx_help_once_decode_array_meta($raw, $decoded_ok);
	if (!$decoded_ok) {
		return false;
	}

	if ($rows === []) {
		\delete_post_meta($post_id, $meta_key);
	} else {
		\update_post_meta($post_id, $meta_key, $rows);
	}

	return true;
}

function cmx_help_once_migrate_beleg_anzahlungen_post(int $post_id): bool {
	$meta_key = \function_exists(__NAMESPACE__ . '\\cmx_camt_beleg_partial_meta_key')
		? cmx_camt_beleg_partial_meta_key()
		: (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_ANZAHLUNGEN') ? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_ANZAHLUNGEN') : '_cmx_beleg_anzahlungen');
	if (!\metadata_exists('post', $post_id, $meta_key)) {
		return false;
	}

	$raw = \get_post_meta($post_id, $meta_key, true);
	if (\is_array($raw)) {
		return false;
	}

	$decoded_ok = false;
	$rows = cmx_help_once_decode_array_meta($raw, $decoded_ok);
	if (!$decoded_ok) {
		return false;
	}

	if ($rows === []) {
		\delete_post_meta($post_id, $meta_key);
	} else {
		\update_post_meta($post_id, $meta_key, \array_values($rows));
	}

	return true;
}

function cmx_help_once_migrate_beleg_task_tracking_post(int $post_id): bool {
	$migrated = false;

	foreach (['_cmx_beleg_tasks_imported_keys', '_cmx_beleg_tasks_imported_uids'] as $meta_key) {
		if (!\metadata_exists('post', $post_id, $meta_key)) {
			continue;
		}

		$raw = \get_post_meta($post_id, $meta_key, true);
		if (\is_array($raw)) {
			continue;
		}

		$decoded_ok = false;
		$list = cmx_help_once_decode_scalar_list_meta($raw, $decoded_ok);
		if (!$decoded_ok) {
			continue;
		}

		if ($list === []) {
			\delete_post_meta($post_id, $meta_key);
		} else {
			\update_post_meta($post_id, $meta_key, $list);
		}
		$migrated = true;
	}

	return $migrated;
}

function cmx_help_once_run_dokumente_cleanup_migration(): int {
	if (!\function_exists(__NAMESPACE__ . '\\cmx_dok_cleanup_legacy_kassenbuch_links')) {
		return 0;
	}

	return (int) cmx_dok_cleanup_legacy_kassenbuch_links();
}

function cmx_help_once_db_migration_modules(): array {
	$modules = [
		'kontakte_kommunikation' => [
			'label' => 'Kontakte Kommunikation',
			'query_args' => static fn(): array => cmx_help_once_kontakte_kommunikation_query_args(),
			'callback' => static fn(int $post_id): bool => cmx_help_once_migrate_kontakte_kommunikation_post($post_id),
		],
	];

	if (
		\function_exists(__NAMESPACE__ . '\\cmx_notizen_supported_post_types')
		&& \function_exists(__NAMESPACE__ . '\\cmx_notizen_meta_key_for_post_type')
		&& \function_exists(__NAMESPACE__ . '\\cmx_notizen_legacy_meta_keys')
		&& \function_exists(__NAMESPACE__ . '\\cmx_notizen_load_rows')
	) {
		foreach (cmx_notizen_supported_post_types() as $post_type) {
			$post_type = (string) $post_type;
			$modules['notizen_' . $post_type] = [
				'label' => 'Notizen ' . $post_type,
				'query_args' => static fn(): array => cmx_help_once_notizen_query_args($post_type),
				'callback' => static fn(int $post_id): bool => cmx_help_once_migrate_notizen_post($post_id, $post_type),
			];
		}
	}

	if (
		\function_exists(__NAMESPACE__ . '\\cmx_zu_projekt_supported_post_types')
		&& \function_exists(__NAMESPACE__ . '\\cmx_zu_projekt_meta_key')
		&& \function_exists(__NAMESPACE__ . '\\cmx_zu_projekt_legacy_meta_keys')
		&& \function_exists(__NAMESPACE__ . '\\cmx_zu_projekt_load_ids')
	) {
		foreach (cmx_zu_projekt_supported_post_types() as $post_type) {
			$post_type = (string) $post_type;
			$modules['projekte_' . $post_type] = [
				'label' => 'Projekt-Zuordnung ' . $post_type,
				'query_args' => static fn(): array => cmx_help_once_projekte_query_args($post_type),
				'callback' => static fn(int $post_id): bool => cmx_help_once_migrate_projekte_post($post_id, $post_type),
			];
		}
	}

	$modules['belege_positionen'] = [
		'label' => 'Belege Positionen',
		'query_args' => static fn(): array => [
			'post_type' => 'belege',
		],
		'callback' => static fn(int $post_id): bool => cmx_help_once_migrate_beleg_positionen_post($post_id),
	];

	$modules['belege_anzahlungen'] = [
		'label' => 'Belege Anzahlungen',
		'query_args' => static fn(): array => [
			'post_type' => 'belege',
		],
		'callback' => static fn(int $post_id): bool => cmx_help_once_migrate_beleg_anzahlungen_post($post_id),
	];

	$modules['belege_task_tracking'] = [
		'label' => 'Belege Task-Tracking',
		'query_args' => static fn(): array => [
			'post_type' => 'belege',
		],
		'callback' => static fn(int $post_id): bool => cmx_help_once_migrate_beleg_task_tracking_post($post_id),
	];

	$modules['dokumente_kassenbuch_cleanup'] = [
		'label' => 'Dokumente Kassenbuch Cleanup',
		'runner' => static fn(): int => cmx_help_once_run_dokumente_cleanup_migration(),
	];

	return $modules;
}

function cmx_help_once_db_migration_finalize(array $progress): void {
	$summary = [
		'started_at' => (string) ($progress['started_at'] ?? (string) \current_time('mysql')),
		'finished_at' => (string) \current_time('mysql'),
		'stats' => \is_array($progress['stats'] ?? null) ? (array) $progress['stats'] : [],
	];

	\update_option(cmx_help_once_db_migration_summary_option(), $summary, false);
	\update_option(cmx_help_once_db_migration_done_option(), (string) \current_time('mysql'), false);
	\delete_option(cmx_help_once_db_migration_progress_option());

	\error_log('[CMX once] DB-Migration abgeschlossen.');
}

function cmx_help_once_run_db_migration(): void {
	if (!\is_admin() || !\current_user_can('manage_options')) {
		return;
	}

	if ((string) \get_option(cmx_help_once_db_migration_done_option(), '') !== '') {
		return;
	}

	cmx_help_once_require_runtime_dependencies();

	$progress = cmx_help_once_db_migration_get_progress();
	$modules = cmx_help_once_db_migration_modules();
	$module_names = \array_values(\array_keys($modules));
	$deadline = \microtime(true) + cmx_help_once_db_migration_time_budget_seconds();
	$batch_size = cmx_help_once_db_migration_batch_size();

	while (\microtime(true) < $deadline) {
		$module_index = (int) ($progress['module_index'] ?? 0);
		if ($module_index >= \count($module_names)) {
			cmx_help_once_db_migration_finalize($progress);
			return;
		}

		$module_name = (string) ($module_names[$module_index] ?? '');
		$module = (array) ($modules[$module_name] ?? []);
		$label = (string) ($module['label'] ?? $module_name);

		if (\is_callable($module['runner'] ?? null)) {
			$updated = (int) \call_user_func($module['runner']);
			cmx_help_once_db_migration_bump_stats($progress, $module_name, $label, $updated, $updated);
			$progress['module_index'] = $module_index + 1;
			$progress['cursor'] = 0;
			continue;
		}

		if (!\is_callable($module['query_args'] ?? null) || !\is_callable($module['callback'] ?? null)) {
			$progress['module_index'] = $module_index + 1;
			$progress['cursor'] = 0;
			continue;
		}

		$query_args = (array) \call_user_func($module['query_args']);
		$batch = cmx_help_once_query_batch_ids($query_args, (int) ($progress['cursor'] ?? 0), $batch_size);
		if ($batch === []) {
			$progress['module_index'] = $module_index + 1;
			$progress['cursor'] = 0;
			continue;
		}

		foreach ($batch as $post_id) {
			$post_id = (int) $post_id;
			$migrated = (bool) \call_user_func($module['callback'], $post_id);
			cmx_help_once_db_migration_bump_stats($progress, $module_name, $label, 1, $migrated ? 1 : 0);
			$progress['cursor'] = $post_id;

			if (\microtime(true) >= $deadline) {
				cmx_help_once_db_migration_save_progress($progress);
				return;
			}
		}

		if (\count($batch) < $batch_size) {
			$progress['module_index'] = $module_index + 1;
			$progress['cursor'] = 0;
		}
	}

	cmx_help_once_db_migration_save_progress($progress);
}

function cmx_help_once_db_verification_done_option(): string {
	return 'cmx_help_once_db_verification_v1_done';
}

function cmx_help_once_db_verification_progress_option(): string {
	return 'cmx_help_once_db_verification_v1_progress';
}

function cmx_help_once_db_verification_summary_option(): string {
	return 'cmx_help_once_db_verification_v1_summary';
}

function cmx_help_once_db_verification_default_progress(): array {
	return [
		'started_at' => (string) \current_time('mysql'),
		'module_index' => 0,
		'cursor' => 0,
		'stats' => [],
	];
}

function cmx_help_once_db_verification_get_progress(): array {
	$progress = \get_option(cmx_help_once_db_verification_progress_option(), []);
	$progress = \is_array($progress) ? $progress : [];

	return [
		'started_at' => (string) ($progress['started_at'] ?? (string) \current_time('mysql')),
		'module_index' => \max(0, (int) ($progress['module_index'] ?? 0)),
		'cursor' => \max(0, (int) ($progress['cursor'] ?? 0)),
		'stats' => \is_array($progress['stats'] ?? null) ? (array) $progress['stats'] : [],
	];
}

function cmx_help_once_db_verification_save_progress(array $progress): void {
	\update_option(cmx_help_once_db_verification_progress_option(), $progress, false);
}

function cmx_help_once_db_verification_bump_stats(array &$progress, string $module_name, string $label, int $checked = 0, int $remaining = 0, int $example_id = 0): void {
	if (!isset($progress['stats'][$module_name]) || !\is_array($progress['stats'][$module_name])) {
		$progress['stats'][$module_name] = [
			'label' => $label,
			'checked' => 0,
			'remaining' => 0,
			'example_ids' => [],
		];
	}

	$progress['stats'][$module_name]['label'] = $label;
	$progress['stats'][$module_name]['checked'] = (int) ($progress['stats'][$module_name]['checked'] ?? 0) + $checked;
	$progress['stats'][$module_name]['remaining'] = (int) ($progress['stats'][$module_name]['remaining'] ?? 0) + $remaining;

	if ($example_id > 0) {
		$example_ids = (array) ($progress['stats'][$module_name]['example_ids'] ?? []);
		if (!\in_array($example_id, $example_ids, true)) {
			$example_ids[] = $example_id;
		}
		$progress['stats'][$module_name]['example_ids'] = \array_slice(\array_values(\array_map('intval', $example_ids)), 0, 10);
	}
}

function cmx_help_once_verify_kontakte_kommunikation_post(int $post_id): bool {
	if (cmx_help_once_post_has_any_meta_keys($post_id, cmx_kommunikation_legacy_bundle_meta_keys())) {
		return true;
	}

	return cmx_help_once_post_has_any_meta_keys($post_id, cmx_kommunikation_legacy_direct_meta_keys());
}

function cmx_help_once_verify_notizen_post(int $post_id, string $post_type): bool {
	if (cmx_help_once_post_has_any_meta_keys($post_id, cmx_notizen_legacy_meta_keys($post_type))) {
		return true;
	}

	$meta_key = cmx_notizen_meta_key_for_post_type($post_type);
	if (!\metadata_exists('post', $post_id, $meta_key)) {
		return false;
	}

	return !\is_array(\get_post_meta($post_id, $meta_key, true));
}

function cmx_help_once_verify_projekte_post(int $post_id, string $post_type): bool {
	if (cmx_help_once_post_has_any_meta_keys($post_id, cmx_zu_projekt_legacy_meta_keys($post_type))) {
		return true;
	}

	$meta_key = cmx_zu_projekt_meta_key($post_type);
	if (!\metadata_exists('post', $post_id, $meta_key)) {
		return false;
	}

	return !\is_array(\get_post_meta($post_id, $meta_key, true));
}

function cmx_help_once_verify_beleg_positionen_post(int $post_id): bool {
	$meta_key = '_cmx_beleg_positionen';
	return \metadata_exists('post', $post_id, $meta_key) && !\is_array(\get_post_meta($post_id, $meta_key, true));
}

function cmx_help_once_verify_beleg_anzahlungen_post(int $post_id): bool {
	$meta_key = \function_exists(__NAMESPACE__ . '\\cmx_camt_beleg_partial_meta_key')
		? cmx_camt_beleg_partial_meta_key()
		: (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_ANZAHLUNGEN') ? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_ANZAHLUNGEN') : '_cmx_beleg_anzahlungen');

	return \metadata_exists('post', $post_id, $meta_key) && !\is_array(\get_post_meta($post_id, $meta_key, true));
}

function cmx_help_once_verify_beleg_task_tracking_post(int $post_id): bool {
	foreach (['_cmx_beleg_tasks_imported_keys', '_cmx_beleg_tasks_imported_uids'] as $meta_key) {
		if (\metadata_exists('post', $post_id, $meta_key) && !\is_array(\get_post_meta($post_id, $meta_key, true))) {
			return true;
		}
	}

	return false;
}

function cmx_help_once_verify_dokumente_cleanup_post(int $post_id): bool {
	return \metadata_exists('post', $post_id, 'cmx_dokumente_buchhaltung');
}

function cmx_help_once_db_verification_modules(): array {
	$modules = [
		'kontakte_kommunikation' => [
			'label' => 'Kontakte Kommunikation',
			'query_args' => static fn(): array => cmx_help_once_kontakte_kommunikation_query_args(),
			'callback' => static fn(int $post_id): bool => cmx_help_once_verify_kontakte_kommunikation_post($post_id),
		],
	];

	if (
		\function_exists(__NAMESPACE__ . '\\cmx_notizen_supported_post_types')
		&& \function_exists(__NAMESPACE__ . '\\cmx_notizen_meta_key_for_post_type')
		&& \function_exists(__NAMESPACE__ . '\\cmx_notizen_legacy_meta_keys')
	) {
		foreach (cmx_notizen_supported_post_types() as $post_type) {
			$post_type = (string) $post_type;
			$modules['notizen_' . $post_type] = [
				'label' => 'Notizen ' . $post_type,
				'query_args' => static fn(): array => cmx_help_once_notizen_query_args($post_type),
				'callback' => static fn(int $post_id): bool => cmx_help_once_verify_notizen_post($post_id, $post_type),
			];
		}
	}

	if (
		\function_exists(__NAMESPACE__ . '\\cmx_zu_projekt_supported_post_types')
		&& \function_exists(__NAMESPACE__ . '\\cmx_zu_projekt_meta_key')
		&& \function_exists(__NAMESPACE__ . '\\cmx_zu_projekt_legacy_meta_keys')
	) {
		foreach (cmx_zu_projekt_supported_post_types() as $post_type) {
			$post_type = (string) $post_type;
			$modules['projekte_' . $post_type] = [
				'label' => 'Projekt-Zuordnung ' . $post_type,
				'query_args' => static fn(): array => cmx_help_once_projekte_query_args($post_type),
				'callback' => static fn(int $post_id): bool => cmx_help_once_verify_projekte_post($post_id, $post_type),
			];
		}
	}

	$modules['belege_positionen'] = [
		'label' => 'Belege Positionen',
		'query_args' => static fn(): array => ['post_type' => 'belege'],
		'callback' => static fn(int $post_id): bool => cmx_help_once_verify_beleg_positionen_post($post_id),
	];

	$modules['belege_anzahlungen'] = [
		'label' => 'Belege Anzahlungen',
		'query_args' => static fn(): array => ['post_type' => 'belege'],
		'callback' => static fn(int $post_id): bool => cmx_help_once_verify_beleg_anzahlungen_post($post_id),
	];

	$modules['belege_task_tracking'] = [
		'label' => 'Belege Task-Tracking',
		'query_args' => static fn(): array => ['post_type' => 'belege'],
		'callback' => static fn(int $post_id): bool => cmx_help_once_verify_beleg_task_tracking_post($post_id),
	];

	$modules['dokumente_kassenbuch_cleanup'] = [
		'label' => 'Dokumente Kassenbuch Cleanup',
		'query_args' => static fn(): array => [
			'post_type' => \function_exists(__NAMESPACE__ . '\\cmx_dok_cpt_slug') ? cmx_dok_cpt_slug() : 'dokumente',
			'post_status' => 'any',
		],
		'callback' => static fn(int $post_id): bool => cmx_help_once_verify_dokumente_cleanup_post($post_id),
	];

	return $modules;
}

function cmx_help_once_db_verification_finalize(array $progress): void {
	$stats = \is_array($progress['stats'] ?? null) ? (array) $progress['stats'] : [];
	$all_clear = true;
	foreach ($stats as $module_stats) {
		if ((int) ($module_stats['remaining'] ?? 0) > 0) {
			$all_clear = false;
			break;
		}
	}

	$summary = [
		'started_at' => (string) ($progress['started_at'] ?? (string) \current_time('mysql')),
		'finished_at' => (string) \current_time('mysql'),
		'all_clear' => $all_clear ? 1 : 0,
		'stats' => $stats,
	];

	\update_option(cmx_help_once_db_verification_summary_option(), $summary, false);
	\update_option(cmx_help_once_db_verification_done_option(), (string) \current_time('mysql'), false);
	\delete_option(cmx_help_once_db_verification_progress_option());

	\error_log('[CMX once] DB-Verifikation abgeschlossen. all_clear=' . ($all_clear ? '1' : '0'));
}

function cmx_help_once_run_db_verification(): void {
	if (!\is_admin() || !\current_user_can('manage_options')) {
		return;
	}

	if ((string) \get_option(cmx_help_once_db_migration_done_option(), '') === '') {
		return;
	}

	if ((string) \get_option(cmx_help_once_db_verification_done_option(), '') !== '') {
		return;
	}

	cmx_help_once_require_runtime_dependencies();

	$progress = cmx_help_once_db_verification_get_progress();
	$modules = cmx_help_once_db_verification_modules();
	$module_names = \array_values(\array_keys($modules));
	$deadline = \microtime(true) + cmx_help_once_db_migration_time_budget_seconds();
	$batch_size = cmx_help_once_db_migration_batch_size();

	while (\microtime(true) < $deadline) {
		$module_index = (int) ($progress['module_index'] ?? 0);
		if ($module_index >= \count($module_names)) {
			cmx_help_once_db_verification_finalize($progress);
			return;
		}

		$module_name = (string) ($module_names[$module_index] ?? '');
		$module = (array) ($modules[$module_name] ?? []);
		$label = (string) ($module['label'] ?? $module_name);

		if (!\is_callable($module['query_args'] ?? null) || !\is_callable($module['callback'] ?? null)) {
			$progress['module_index'] = $module_index + 1;
			$progress['cursor'] = 0;
			continue;
		}

		$query_args = (array) \call_user_func($module['query_args']);
		$batch = cmx_help_once_query_batch_ids($query_args, (int) ($progress['cursor'] ?? 0), $batch_size);
		if ($batch === []) {
			$progress['module_index'] = $module_index + 1;
			$progress['cursor'] = 0;
			continue;
		}

		foreach ($batch as $post_id) {
			$post_id = (int) $post_id;
			$has_remaining = (bool) \call_user_func($module['callback'], $post_id);
			cmx_help_once_db_verification_bump_stats($progress, $module_name, $label, 1, $has_remaining ? 1 : 0, $has_remaining ? $post_id : 0);
			$progress['cursor'] = $post_id;

			if (\microtime(true) >= $deadline) {
				cmx_help_once_db_verification_save_progress($progress);
				return;
			}
		}

		if (\count($batch) < $batch_size) {
			$progress['module_index'] = $module_index + 1;
			$progress['cursor'] = 0;
		}
	}

	cmx_help_once_db_verification_save_progress($progress);
}

\add_action('admin_init', __NAMESPACE__ . '\\cmx_help_once_run_db_migration', 100);
\add_action('admin_init', __NAMESPACE__ . '\\cmx_help_once_run_db_verification', 101);
