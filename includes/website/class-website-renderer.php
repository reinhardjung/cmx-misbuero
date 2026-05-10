<?php
namespace CLOUDMEISTER\CMX\Buero\Website;

defined('ABSPATH') || exit;

final class Renderer {
	public static function init(): void {
		\add_action('template_redirect', [self::class, 'maybe_render'], 40);
	}

	public static function maybe_render(): void {
		if (\is_admin() || \wp_doing_ajax() || \wp_doing_cron()) {
			return;
		}
		if (!self::is_public_home_request()) {
			return;
		}

		$settings = Settings::get();
		if (empty($settings['enabled'])) {
			return;
		}

		\status_header(200);
		\nocache_headers();
		self::render($settings);
		exit;
	}

	public static function render(array $settings): void {
		$colors = Settings::color_variants((string) ($settings['primary_color'] ?? '#0F63F6'));
		$css_url = \plugins_url('assets/css/website.css', \dirname(__DIR__, 2) . '/cmx-misbuero.php');
		$css_path = \dirname(__DIR__, 2) . '/assets/css/website.css';
		$css_version = \is_file($css_path) ? (string) \filemtime($css_path) : '1';
		$template = \dirname(__DIR__, 2) . '/templates/website/home.php';

		if (!\is_file($template)) {
			return;
		}

		include $template;
	}

	public static function asset_image(int $attachment_id, string $size, string $class, string $alt = ''): string {
		if ($attachment_id <= 0) {
			return '';
		}

		return (string) \wp_get_attachment_image($attachment_id, $size, false, [
			'class' => $class,
			'alt' => $alt,
			'loading' => 'lazy',
			'decoding' => 'async',
		]);
	}

	public static function link_url(string $url, string $fallback = '#'): string {
		$url = \trim($url);
		return $url !== '' ? $url : $fallback;
	}

	public static function phone_url(string $phone): string {
		$clean = \preg_replace('/[^\d+]+/', '', $phone);
		return $clean ? 'tel:' . $clean : '#kontakt';
	}

	private static function is_public_home_request(): bool {
		$path = (string) \wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), \PHP_URL_PATH);
		$home_path = (string) \wp_parse_url(\home_url('/'), \PHP_URL_PATH);
		$home_path = '/' . \trim($home_path, '/');
		if ($home_path === '/') {
			$home_path = '';
		}
		$relative_path = '/' . \trim(\substr($path, \strlen($home_path)), '/');
		if ($relative_path !== '/') {
			return false;
		}

		$query = isset($_SERVER['QUERY_STRING']) ? \trim((string) $_SERVER['QUERY_STRING']) : '';
		if ($query !== '') {
			return false;
		}

		return \is_front_page() || \is_home() || $relative_path === '/';
	}
}
