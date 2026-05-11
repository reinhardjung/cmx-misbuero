<?php
declare(strict_types=1);

namespace CLOUDMEISTER\MisBuero\Core;

final class ModuleLoader {
	/** @var string[] */
	private array $base_dirs;

	/** @param string[] $base_dirs */
	public function __construct(array $base_dirs) {
		$this->base_dirs = \array_values(\array_filter($base_dirs, static fn(string $dir): bool => $dir !== ''));
	}

	/** @param string[] $modules */
	public function load(array $modules): void {
		foreach ($modules as $module) {
			$module = \sanitize_key((string) $module);
			if ($module === '') {
				continue;
			}

			$file = $this->find($module);
			if ($file === null) {
				Logger::warning('Module not found: ' . $module);
				continue;
			}

			require_once $file;
		}
	}

	private function find(string $module): ?string {
		foreach ($this->base_dirs as $base_dir) {
			$file = \rtrim($base_dir, '/\\') . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . $module . '.php';
			if (\is_readable($file)) {
				return $file;
			}
		}

		return null;
	}
}
