<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

if (!\function_exists(__NAMESPACE__ . '\\cmx_ssh_public_key_comment')) {
	function cmx_ssh_public_key_comment(string $key): string {
		$parts = \preg_split('/\s+/', \trim($key), 3);
		return \count($parts) >= 3 ? \trim((string) $parts[2]) : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ssh_public_key_id')) {
	function cmx_ssh_public_key_id(string $key): string {
		$parts = \preg_split('/\s+/', \trim($key)) ?: [];
		$key_material = \implode(' ', \array_slice($parts, 0, 2));
		return \hash('sha256', $key_material);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_ssh_public_keys_normalize')) {
	/**
	 * @return array<int, array{name:string,key:string}>
	 */
	function cmx_ssh_public_keys_normalize(mixed $value): array {
		$rows = [];

		if (\is_string($value)) {
			$value = \str_replace(["\r\n", "\r"], "\n", $value);
			foreach (\explode("\n", $value) as $key) {
				$rows[] = ['name' => '', 'key' => $key];
			}
		} elseif (\is_array($value)) {
			foreach ($value as $row) {
				if (\is_string($row)) {
					$rows[] = ['name' => '', 'key' => $row];
					continue;
				}
				if (\is_array($row)) {
					$rows[] = [
						'name' => (string) ($row['name'] ?? ''),
						'key'  => (string) ($row['key'] ?? ''),
					];
				}
			}
		}

		$keys = [];
		foreach ($rows as $row) {
			// A pasted block is accepted as well, even though the UI normally sends one key per row.
			foreach (\preg_split('/\R+/', $row['key']) ?: [] as $key) {
				$key = \trim(\sanitize_text_field($key));
				if ($key === '') {
					continue;
				}

				$name = \trim(\sanitize_text_field($row['name']));
				if ($name === '') {
					$name = cmx_ssh_public_key_comment($key);
				}
				if ($name === '') {
					$name = \sprintf(\__('Schlüssel %d', 'cmx-misbuero'), \count($keys) + 1);
				}

				$keys[] = ['name' => $name, 'key' => $key];
			}
		}

		return $keys;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_get_admin_public_keys')) {
	/**
	 * @return array<int, array{name:string,key:string}>
	 */
	function cmx_get_admin_public_keys(): array {
		$value = \get_option('ADMIN_PUBLIC_KEYS', null);
		if (\is_array($value)) {
			return cmx_ssh_public_keys_normalize($value);
		}

		// Compatibility with installations that still have the former single setting.
		return cmx_ssh_public_keys_normalize((string) \get_option('ADMIN_PUBLIC_KEY', ''));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_admin_public_keys_cloud_init')) {
	function cmx_admin_public_keys_cloud_init(string $indent = '      ', string $selected_id = ''): string {
		$lines = [];
		foreach (cmx_get_admin_public_keys() as $entry) {
			if ($selected_id !== '' && !\hash_equals($selected_id, cmx_ssh_public_key_id($entry['key']))) {
				continue;
			}
			$encoded = \wp_json_encode($entry['key'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			$lines[] = $indent . '- ' . ($encoded === false ? '""' : $encoded);
		}

		return \implode("\n", $lines);
	}
}
