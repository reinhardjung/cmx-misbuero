<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_once_flat_storage_migration_version')) {
	function cmx_once_flat_storage_migration_version(): string {
		return '20260530_flat_meta_v1';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_once_decode_structured_meta_value')) {
	function cmx_once_decode_structured_meta_value(string $raw): ?array {
		if ($raw === '') {
			return null;
		}

		$unserialized = @\maybe_unserialize($raw);
		if (\is_array($unserialized)) {
			return $unserialized;
		}

		$trim = \trim($raw);
		if ($trim !== '' && ($trim[0] === '[' || $trim[0] === '{')) {
			$decoded = \json_decode($trim, true);
			if (\json_last_error() === \JSON_ERROR_NONE && \is_array($decoded)) {
				return $decoded;
			}
		}

		return null;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_once_migrate_flat_storage')) {
	function cmx_once_migrate_flat_storage(): void {
		if (!\function_exists(__NAMESPACE__ . '\\cmx_flat_meta_is_managed_key')
			|| !\function_exists(__NAMESPACE__ . '\\cmx_flat_meta_write_array')) {
			return;
		}

		$version = cmx_once_flat_storage_migration_version();
		if ((string) \get_option('cmx_once_flat_storage_migration') === $version) {
			return;
		}

		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT meta_id, post_id, meta_key, meta_value
			 FROM {$wpdb->postmeta}
			 WHERE meta_key LIKE '%cmx%'
			 ORDER BY meta_id ASC",
			ARRAY_A
		);

		$migrated = 0;
		foreach ((array) $rows as $row) {
			$post_id = (int) ($row['post_id'] ?? 0);
			$meta_key = (string) ($row['meta_key'] ?? '');
			if ($post_id <= 0 || !cmx_flat_meta_is_managed_key($meta_key)) {
				continue;
			}

			$value = cmx_once_decode_structured_meta_value((string) ($row['meta_value'] ?? ''));
			if ($value === null) {
				continue;
			}

			cmx_flat_meta_write_array($post_id, $meta_key, $value);
			$migrated++;
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_flat_option_is_managed_key')
			&& \function_exists(__NAMESPACE__ . '\\cmx_flat_option_write_array')) {
			$option_rows = $wpdb->get_results(
				"SELECT option_name, option_value
				 FROM {$wpdb->options}
				 WHERE option_name LIKE 'cmx_%' OR option_name LIKE 'CMX_%'
				 ORDER BY option_id ASC",
				ARRAY_A
			);

			foreach ((array) $option_rows as $row) {
				$option = (string) ($row['option_name'] ?? '');
				if (!cmx_flat_option_is_managed_key($option)) {
					continue;
				}

				$value = cmx_once_decode_structured_meta_value((string) ($row['option_value'] ?? ''));
				if ($value === null) {
					continue;
				}

				cmx_flat_option_write_array($option, $value);
				$migrated++;
			}
		}

		\update_option('cmx_once_flat_storage_migration', $version, false);
		\update_option('cmx_once_flat_storage_migration_count', (string) $migrated, false);
	}
}

\add_action('admin_init', __NAMESPACE__ . '\\cmx_once_migrate_flat_storage', 1);
