<?php
/**
 * Plugin Name: Mis Büro
 * Description: Büro, Rechnungen, Kontakte und Dokumente für WordPress.
 * Version: 1.0.0
 * Author: Mis Büro
 * Text Domain: mis-buero
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$mis_buero_root = \dirname(__DIR__, 2);
$mis_buero_core_dir = \is_dir(__DIR__ . '/src/Core') ? __DIR__ . '/src/Core' : \dirname(__DIR__) . '/core/src';

foreach ([__DIR__ . '/vendor/autoload.php', $mis_buero_root . '/vendor/autoload.php'] as $mis_buero_vendor) {
	if (is_readable($mis_buero_vendor)) {
		require_once $mis_buero_vendor;
		break;
	}
}

$mis_buero_autoloader = $mis_buero_core_dir . '/Autoloader.php';
if (!class_exists(\CLOUDMEISTER\MisBuero\Core\Autoloader::class) && is_readable($mis_buero_autoloader)) {
	require_once $mis_buero_autoloader;
}

\CLOUDMEISTER\MisBuero\Core\Autoloader::register([
	'CLOUDMEISTER\\MisBuero\\Core\\' => $mis_buero_core_dir,
	'CLOUDMEISTER\\MisBuero\\Standard\\' => __DIR__ . '/src',
]);

\CLOUDMEISTER\MisBuero\Core\Plugin::boot(__FILE__, 'standard', ['standard'], [__DIR__ . '/src']);
