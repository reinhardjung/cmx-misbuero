<?php
declare(strict_types=1);

namespace CLOUDMEISTER\MisBuero\Core;

final class Helpers {
	public static function plugin_is_active(string $plugin_file): bool {
		if (!\function_exists('is_plugin_active')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return \is_plugin_active($plugin_file);
	}

	public static function admin_notice(string $message, string $type = 'warning'): void {
		$type = \in_array($type, ['success', 'info', 'warning', 'error'], true) ? $type : 'warning';
		\add_action('admin_notices', static function () use ($message, $type): void {
			echo '<div class="notice notice-' . \esc_attr($type) . '"><p>' . \esc_html($message) . '</p></div>';
		});
	}

	public static function starts_with(string $value, string $prefix): bool {
		return \strncmp($value, $prefix, \strlen($prefix)) === 0;
	}
}
