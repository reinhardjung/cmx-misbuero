<?php
declare(strict_types=1);

namespace CLOUDMEISTER\MisBuero\Core;

final class Logger {
	public static function info(string $message): void {
		self::write('info', $message);
	}

	public static function warning(string $message): void {
		self::write('warning', $message);
	}

	public static function error(string $message): void {
		self::write('error', $message);
	}

	private static function write(string $level, string $message): void {
		if (!\defined('WP_DEBUG') || !WP_DEBUG) {
			return;
		}

		\error_log('[Mis Büro][' . $level . '] ' . $message);
	}
}
