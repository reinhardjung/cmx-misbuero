<?php
declare(strict_types=1);

namespace CLOUDMEISTER\MisBuero\Trial;

final class TrialGuard {
	private const OPTION_START = 'mis_buero_trial_started_at';
	private const DAYS = 30;

	public static function register(string $plugin_file): void {
		\register_activation_hook($plugin_file, [self::class, 'activate']);
		\add_action('admin_notices', [self::class, 'notice']);
		\add_filter('user_has_cap', [self::class, 'filter_caps'], 20, 4);
	}

	public static function activate(): void {
		if ((string) \get_option(self::OPTION_START, '') === '') {
			\add_option(self::OPTION_START, \gmdate('Y-m-d H:i:s'), '', false);
		}
	}

	public static function expired(): bool {
		$started = (string) \get_option(self::OPTION_START, '');
		if ($started === '') {
			return false;
		}

		$start_ts = \strtotime($started . ' UTC');
		if ($start_ts === false) {
			return false;
		}

		return \time() > ($start_ts + (self::DAYS * DAY_IN_SECONDS));
	}

	public static function notice(): void {
		if (!self::expired() || !\current_user_can('manage_options')) {
			return;
		}

		echo '<div class="notice notice-warning"><p>' . \esc_html__('Mis Büro Trial ist abgelaufen. Bestehende Daten bleiben sichtbar, neue Aktionen sind blockiert.', 'mis-buero') . '</p></div>';
	}

	public static function filter_caps(array $allcaps, array $caps, array $args, $user): array {
		unset($caps, $user);
		if (!self::expired()) {
			return $allcaps;
		}

		$blocked = [
			'publish_posts',
			'publish_pages',
			'upload_files',
			'create_posts',
		];

		$requested = isset($args[0]) ? (string) $args[0] : '';
		if (\in_array($requested, $blocked, true)) {
			$allcaps[$requested] = false;
		}

		return $allcaps;
	}
}
