<?php
/**
 * Plugin Name: Mis Buero
 * Description: Office workflows, invoices, contacts and documents for WordPress.
 * Version: 1.0.0
 * Author: Mis Buero
 * Text Domain: mis-buero
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace CLOUDMEISTER\CMX\Buero;

\defined('ABSPATH') || exit;

const CMX_ENABLE_HELP_ONCE = false;
const CMX_ENABLE_SAVE_PERF_LOG = false;

if (!\defined('CMX_PLUGIN_DIR')) {
	\define('CMX_PLUGIN_DIR', \plugin_dir_path(__FILE__));
}

if (!\defined('CMX_PLUGIN_URL')) {
	\define('CMX_PLUGIN_URL', \plugin_dir_url(__FILE__));
}

if (!\defined('CMX_UPLOADS_MISBUERO')) {
	$uploads = \wp_get_upload_dir();
	\define('CMX_UPLOADS_MISBUERO', \trailingslashit((string) ($uploads['basedir'] ?? WP_CONTENT_DIR . '/uploads')) . 'misbuero/');
}

if (!\defined('CMX_DOMAIN')) {
	$host = (string) \wp_parse_url(\home_url(), PHP_URL_HOST);
	\define('CMX_DOMAIN', (string) \explode('.', $host)[0]);
}

if (!\defined('UserDomain')) {
	\define('UserDomain', CMX_DOMAIN);
}

function misbuero_free_root_dir(): string {
	$dist_root = __DIR__;
	if (\is_dir($dist_root . '/includes') && \is_dir($dist_root . '/src/kontakte')) {
		return $dist_root;
	}

	return \dirname(__DIR__, 2);
}

function misbuero_free_require(string $relative_path): void {
	$file = \trailingslashit(misbuero_free_root_dir()) . \ltrim($relative_path, '/');
	if (\is_readable($file)) {
		require_once $file;
	}
}

function misbuero_free_languages(): array {
	return [
		'de_DE' => 'Deutsch',
		'en_US' => 'English',
	];
}

function misbuero_free_sanitize_language(string $language): string {
	return \array_key_exists($language, misbuero_free_languages()) ? $language : 'de_DE';
}

\add_filter('plugin_locale', static function (string $locale, string $domain): string {
	if ($domain !== 'mis-buero') {
		return $locale;
	}

	return misbuero_free_sanitize_language((string) \get_option('misbuero_language', 'de_DE'));
}, 10, 2);

\add_action('admin_init', static function (): void {
	\register_setting('general', 'misbuero_language', [
		'type' => 'string',
		'sanitize_callback' => __NAMESPACE__ . '\\misbuero_free_sanitize_language',
		'default' => 'de_DE',
	]);

	\add_settings_field(
		'misbuero_language',
		__('Mis Büro language', 'mis-buero'),
		static function (): void {
			$current = misbuero_free_sanitize_language((string) \get_option('misbuero_language', 'de_DE'));
			echo '<select id="misbuero_language" name="misbuero_language">';
			foreach (misbuero_free_languages() as $locale => $label) {
				echo '<option value="' . \esc_attr($locale) . '"' . \selected($current, $locale, false) . '>' . \esc_html($label) . '</option>';
			}
			echo '</select>';
			echo '<p class="description">' . \esc_html__('Controls the language used by Mis Büro. German is the default.', 'mis-buero') . '</p>';
		},
		'general'
	);
});

\add_filter('use_block_editor_for_post_type', static function (bool $use_block_editor, string $post_type): bool {
	return \in_array($post_type, ['kontakte', 'artikel', 'belege', 'dokumente', 'projekte', 'budget'], true)
		? false
		: $use_block_editor;
}, 10, 2);

misbuero_free_require('includes/cmx_version.php');
misbuero_free_require('includes/helpers.php');
misbuero_free_require('includes/functions.php');
misbuero_free_require('includes/notizen.php');
misbuero_free_require('includes/projekte.php');
misbuero_free_require('includes/featured_images.php');
misbuero_free_require('includes/dokumente.php');
misbuero_free_require('includes/uploads.php');
misbuero_free_require('includes/upload_form.php');
misbuero_free_require('includes/startseite_fix.php');
misbuero_free_require('includes/help_screens.php');
misbuero_free_require('includes/layout_defaults.php');

if (\is_admin()) {
	misbuero_free_require('includes/index.php');
}

\add_action('init', static function (): void {
	foreach (['kontakte', 'artikel', 'belege', 'dokumente', 'projekte', 'budget'] as $module) {
		misbuero_free_require('src/' . $module . '/index.php');
	}
	misbuero_free_require('src/cockpit/start.php');
}, 1);
