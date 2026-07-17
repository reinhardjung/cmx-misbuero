<?php
declare(strict_types=1);

namespace CLOUDMEISTER\MisBuero\Core\Contracts;

defined('ABSPATH') || exit;

interface ModuleInterface {
	public function register(): void;
}
