<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_flat_meta_is_managed_key')) {
	function cmx_flat_meta_is_managed_key(string $meta_key): bool {
		if ($meta_key === '' || \str_contains($meta_key, '__')) {
			return false;
		}

		return \str_starts_with($meta_key, '_cmx_')
			|| \str_starts_with($meta_key, 'cmx_')
			|| \str_starts_with($meta_key, 'CMX_');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_flat_option_is_managed_key')) {
	function cmx_flat_option_is_managed_key(string $option): bool {
		if ($option === '' || \str_contains($option, '__')) {
			return false;
		}

		return \str_starts_with($option, 'cmx_') || \str_starts_with($option, 'CMX_');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_flat_meta_leaf_key')) {
	function cmx_flat_meta_leaf_key(string $meta_key, array $path): string {
		$parts = [$meta_key];
		foreach ($path as $segment) {
			$parts[] = (string) $segment;
		}
		return \implode('__', $parts);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_flat_option_leaf_key')) {
	function cmx_flat_option_leaf_key(string $option, array $path): string {
		$parts = [$option];
		foreach ($path as $segment) {
			$parts[] = (string) $segment;
		}
		return \implode('__', $parts);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_flat_meta_scalar')) {
	function cmx_flat_meta_scalar($value): string {
		if (\is_bool($value)) {
			return $value ? '1' : '0';
		}
		if ($value === null) {
			return '';
		}
		if (\is_int($value) || \is_float($value)) {
			return (string) $value;
		}
		return (string) $value;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_flat_meta_delete_storage')) {
	function cmx_flat_meta_delete_storage(int $post_id, string $meta_key): void {
		global $wpdb;

		if ($post_id <= 0 || $meta_key === '') {
			return;
		}

		$like = $wpdb->esc_like($meta_key . '__') . '%';
		$wpdb->query($wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
			$post_id,
			$like
		));
		\delete_metadata('post', $post_id, $meta_key);
		\wp_cache_delete($post_id, 'post_meta');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_flat_meta_store_walk')) {
	function cmx_flat_meta_store_walk(int $post_id, string $meta_key, array $path, $value): void {
		if (\is_array($value)) {
			\update_metadata('post', $post_id, cmx_flat_meta_leaf_key($meta_key, \array_merge($path, ['count'])), (string) \count($value));
			foreach ($value as $child_key => $child_value) {
				cmx_flat_meta_store_walk($post_id, $meta_key, \array_merge($path, [(string) $child_key]), $child_value);
			}
			return;
		}

		\update_metadata('post', $post_id, cmx_flat_meta_leaf_key($meta_key, $path), cmx_flat_meta_scalar($value));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_flat_option_delete_storage')) {
	function cmx_flat_option_delete_storage(string $option): void {
		global $wpdb;

		if ($option === '') {
			return;
		}

		$like = $wpdb->esc_like($option . '__') . '%';
		$wpdb->query($wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$like
		));
		\delete_option($option);
		\wp_cache_delete($option, 'options');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_flat_option_delete_flat_rows')) {
	function cmx_flat_option_delete_flat_rows(string $option): void {
		global $wpdb;

		if ($option === '') {
			return;
		}

		$like = $wpdb->esc_like($option . '__') . '%';
		$wpdb->query($wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$like
		));
		\wp_cache_delete($option, 'options');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_flat_option_base_exists')) {
	function cmx_flat_option_base_exists(string $option): bool {
		global $wpdb;

		if ($option === '') {
			return false;
		}

		return $wpdb->get_var($wpdb->prepare(
			"SELECT option_id FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
			$option
		)) !== null;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_flat_option_store_walk')) {
	function cmx_flat_option_store_walk(string $option, array $path, $value): void {
		if (\is_array($value)) {
			\update_option(cmx_flat_option_leaf_key($option, \array_merge($path, ['count'])), (string) \count($value), false);
			foreach ($value as $child_key => $child_value) {
				cmx_flat_option_store_walk($option, \array_merge($path, [(string) $child_key]), $child_value);
			}
			return;
		}

		\update_option(cmx_flat_option_leaf_key($option, $path), cmx_flat_meta_scalar($value), false);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_flat_option_write_array')) {
	function cmx_flat_option_write_array(string $option, array $value): void {
		cmx_flat_option_delete_flat_rows($option);
		\update_option($option . '__flat', '1', false);
		\update_option($option . '__count', (string) \count($value), false);
		foreach ($value as $child_key => $child_value) {
			cmx_flat_option_store_walk($option, [(string) $child_key], $child_value);
		}
		if (!cmx_flat_option_base_exists($option)) {
			\add_option($option, $value, '', false);
		}
		\wp_cache_delete($option, 'options');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_flat_meta_write_array')) {
	function cmx_flat_meta_write_array(int $post_id, string $meta_key, array $value): void {
		cmx_flat_meta_delete_storage($post_id, $meta_key);
		\update_metadata('post', $post_id, $meta_key . '__flat', '1');
		\update_metadata('post', $post_id, $meta_key . '__count', (string) \count($value));
		foreach ($value as $child_key => $child_value) {
			cmx_flat_meta_store_walk($post_id, $meta_key, [(string) $child_key], $child_value);
		}
		\wp_cache_delete($post_id, 'post_meta');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_flat_meta_assign_path')) {
	function cmx_flat_meta_assign_path(array &$target, array $path, string $value): void {
		if ($path === [] || \end($path) === 'flat' || \end($path) === 'count') {
			return;
		}

		$cursor =& $target;
		$last_index = \count($path) - 1;
		foreach ($path as $index => $segment) {
			$key = \ctype_digit((string) $segment) ? (int) $segment : (string) $segment;
			if ($index === $last_index) {
				$cursor[$key] = $value;
				return;
			}
			if (!isset($cursor[$key]) || !\is_array($cursor[$key])) {
				$cursor[$key] = [];
			}
			$cursor =& $cursor[$key];
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_flat_meta_sort_recursive')) {
	function cmx_flat_meta_sort_recursive(array $value): array {
		foreach ($value as $key => $child) {
			if (\is_array($child)) {
				$value[$key] = cmx_flat_meta_sort_recursive($child);
			}
		}
		\ksort($value);
		return $value;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_flat_meta_read_array')) {
	function cmx_flat_meta_read_array(int $post_id, string $meta_key): ?array {
		global $wpdb;

		if ($post_id <= 0 || !cmx_flat_meta_is_managed_key($meta_key)) {
			return null;
		}

		$like = $wpdb->esc_like($meta_key . '__') . '%';
		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s ORDER BY meta_key ASC, meta_id ASC",
			$post_id,
			$like
		), ARRAY_A);

		if (!\is_array($rows) || $rows === []) {
			return null;
		}

		$out = [];
		foreach ($rows as $row) {
			$key = (string) ($row['meta_key'] ?? '');
			if (!\str_starts_with($key, $meta_key . '__')) {
				continue;
			}
			$path = \explode('__', \substr($key, \strlen($meta_key . '__')));
			cmx_flat_meta_assign_path($out, $path, (string) ($row['meta_value'] ?? ''));
		}

		return cmx_flat_meta_sort_recursive($out);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_flat_option_marker_exists')) {
	function cmx_flat_option_marker_exists(string $option): bool {
		global $wpdb;

		if (!cmx_flat_option_is_managed_key($option)) {
			return false;
		}

		$found = $wpdb->get_var($wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
			$option . '__flat'
		));

		return $found !== null;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_flat_option_read_array')) {
	function cmx_flat_option_read_array(string $option): ?array {
		global $wpdb;

		if (!cmx_flat_option_is_managed_key($option)) {
			return null;
		}

		$like = $wpdb->esc_like($option . '__') . '%';
		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name ASC",
			$like
		), ARRAY_A);

		if (!\is_array($rows) || $rows === []) {
			return null;
		}

		$out = [];
		foreach ($rows as $row) {
			$key = (string) ($row['option_name'] ?? '');
			if (!\str_starts_with($key, $option . '__')) {
				continue;
			}
			$path = \explode('__', \substr($key, \strlen($option . '__')));
			cmx_flat_meta_assign_path($out, $path, (string) ($row['option_value'] ?? ''));
		}

		return cmx_flat_meta_sort_recursive($out);
	}
}

\add_filter('update_post_metadata', static function ($check, int $object_id, string $meta_key, $meta_value, $prev_value) {
	if ($check !== null || !cmx_flat_meta_is_managed_key($meta_key) || !\is_array($meta_value)) {
		return $check;
	}

	cmx_flat_meta_write_array($object_id, $meta_key, $meta_value);
	return true;
}, 10, 5);

\add_filter('add_post_metadata', static function ($check, int $object_id, string $meta_key, $meta_value, bool $unique) {
	if ($check !== null || !cmx_flat_meta_is_managed_key($meta_key) || !\is_array($meta_value)) {
		return $check;
	}

	cmx_flat_meta_write_array($object_id, $meta_key, $meta_value);
	return true;
}, 10, 5);

\add_filter('delete_post_metadata', static function ($check, int $object_id, string $meta_key, $meta_value, bool $delete_all) {
	if ($check !== null || !cmx_flat_meta_is_managed_key($meta_key)) {
		return $check;
	}

	global $wpdb;
	$like = $wpdb->esc_like($meta_key . '__') . '%';
	if ($delete_all) {
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $like));
	} else {
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s", $object_id, $like));
	}

	return $check;
}, 10, 5);

\add_filter('get_post_metadata', static function ($value, int $object_id, string $meta_key, bool $single) {
	if ($value !== null || !cmx_flat_meta_is_managed_key($meta_key)) {
		return $value;
	}
	if (!\metadata_exists('post', $object_id, $meta_key . '__flat')) {
		return $value;
	}

	$flat = cmx_flat_meta_read_array($object_id, $meta_key);
	if ($flat === null) {
		return $value;
	}

	return [$flat];
}, 10, 4);

\add_filter('pre_update_option', static function ($value, string $option, $old_value) {
	if (!cmx_flat_option_is_managed_key($option) || !\is_array($value)) {
		return $value;
	}

	cmx_flat_option_write_array($option, $value);
	return $value;
}, 10, 3);

if (!\function_exists(__NAMESPACE__ . '\\cmx_flat_option_pre_read')) {
	function cmx_flat_option_pre_read($pre, string $option) {
		if ($pre !== false || !cmx_flat_option_marker_exists($option)) {
			return $pre;
		}

		$flat = cmx_flat_option_read_array($option);
		return ($flat === null || $flat === []) ? $pre : $flat;
	}
}

\add_filter('pre_option', static function ($pre, string $option, $default_value) {
	if ($pre !== false || !cmx_flat_option_marker_exists($option)) {
		return $pre;
	}

	return cmx_flat_option_pre_read($pre, $option);
}, 10, 3);

foreach (['cmx_einstellungen', 'cmx_belege'] as $cmx_flat_critical_option) {
	\add_filter('pre_option_' . $cmx_flat_critical_option, static function ($pre, string $option, $default_value) {
		return cmx_flat_option_pre_read($pre, $option);
	}, 10, 3);
}
