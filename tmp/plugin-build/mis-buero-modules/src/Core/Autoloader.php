<?php
declare(strict_types=1);

namespace CLOUDMEISTER\MisBuero\Core;

final class Autoloader {
	/** @var array<string, string> */
	private static array $prefixes = [];

	public static function register(array $prefixes = []): void {
		if ($prefixes !== []) {
			foreach ($prefixes as $prefix => $base_dir) {
				self::add((string) $prefix, (string) $base_dir);
			}
		}

		static $registered = false;
		if (!$registered) {
			\spl_autoload_register([self::class, 'load']);
			$registered = true;
		}
	}

	public static function add(string $prefix, string $base_dir): void {
		$prefix = \trim($prefix, '\\') . '\\';
		$base_dir = \rtrim($base_dir, '/\\') . DIRECTORY_SEPARATOR;
		self::$prefixes[$prefix] = $base_dir;
	}

	private static function load(string $class): void {
		foreach (self::$prefixes as $prefix => $base_dir) {
			if (\strncmp($class, $prefix, \strlen($prefix)) !== 0) {
				continue;
			}

			$relative = \substr($class, \strlen($prefix));
			$file = $base_dir . \str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
			if (\is_readable($file)) {
				require_once $file;
			}
			return;
		}
	}
}
