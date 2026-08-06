<?php
declare(strict_types=1);

namespace CLOUDMEISTER\MisBuero\Core;

defined('ABSPATH') || exit;

final class Plugin {
	private string $file;
	private string $variant;
	private string $root_dir;
	private Config $config;

	/** @var string[] */
	private array $groups;

	/** @var string[] */
	private array $module_dirs;

	/**
	 * @param string[] $groups
	 * @param string[] $module_dirs
	 */
	public function __construct(string $file, string $variant, array $groups, array $module_dirs) {
		$this->file = $file;
		$this->variant = $variant;
		$this->groups = $groups;
		$this->root_dir = \dirname($file);
		$this->module_dirs = $module_dirs;
		$this->config = new Config($this->find_config_path());
	}

	public static function boot(string $file, string $variant, array $groups, array $module_dirs): self {
		$plugin = new self($file, $variant, $groups, $module_dirs);
		$plugin->register();
		return $plugin;
	}

	private function register(): void {
		\add_action('plugins_loaded', [$this, 'load_modules'], 20);
		\add_action('init', [$this, 'register_common_hooks'], 5);
	}

	public function register_common_hooks(): void {
		\do_action('misbuero_core_init', $this->variant);
	}

	public function load_modules(): void {
		$modules = [];
		foreach ($this->groups as $group) {
			$modules = \array_merge($modules, $this->config->modules($group));
		}

		$modules = \array_values(\array_unique($modules));
		\do_action('misbuero_modules_before_load', $modules, $this->variant);
		(new ModuleLoader($this->module_dirs))->load($modules);
		\do_action('misbuero_modules_loaded', $modules, $this->variant);
	}

	public function file(): string {
		return $this->file;
	}

	private function find_config_path(): string {
		$candidates = [
			$this->root_dir . '/includes/globales.ini',
			\dirname($this->root_dir, 2) . '/includes/globales.ini',
		];

		foreach ($candidates as $candidate) {
			if (\is_readable($candidate)) {
				return $candidate;
			}
		}

		return $candidates[0];
	}
}
