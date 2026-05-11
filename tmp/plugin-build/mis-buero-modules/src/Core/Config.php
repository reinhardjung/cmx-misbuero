<?php
declare(strict_types=1);

namespace CLOUDMEISTER\MisBuero\Core;

final class Config {
	private string $file;

	/** @var array<string, array<string, string>> */
	private array $data = [];

	public function __construct(string $file) {
		$this->file = $file;
		$this->data = $this->parse($file);
	}

	public function modules(string $group): array {
		$value = (string) ($this->data['versionen'][$group] ?? '');
		if ($value === '') {
			return [];
		}

		$modules = \array_map('trim', \explode(',', $value));
		$modules = \array_filter($modules, static fn(string $module): bool => $module !== '');
		return \array_values(\array_map('sanitize_key', $modules));
	}

	private function parse(string $file): array {
		if (!\is_readable($file)) {
			Logger::warning('Config file missing: ' . $file);
			return [];
		}

		$data = \parse_ini_file($file, true, INI_SCANNER_TYPED);
		return \is_array($data) ? $data : [];
	}
}
