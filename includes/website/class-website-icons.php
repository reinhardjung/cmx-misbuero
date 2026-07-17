<?php
namespace CLOUDMEISTER\CMX\Buero\Website;

defined('ABSPATH') || exit;

final class Icons {
	public static function keys(): array {
		return [
			'users' => __('Benutzer', 'cmx-misbuero'),
			'rocket' => __('Rakete', 'cmx-misbuero'),
			'headset' => __('Headset', 'cmx-misbuero'),
			'message' => __('Nachricht', 'cmx-misbuero'),
			'clipboard' => __('Checkliste', 'cmx-misbuero'),
			'gear' => __('Zahnrad', 'cmx-misbuero'),
			'check' => __('Haken', 'cmx-misbuero'),
			'star' => __('Stern', 'cmx-misbuero'),
			'diamond' => __('Diamant', 'cmx-misbuero'),
			'user' => __('Person', 'cmx-misbuero'),
			'shield' => __('Schutz', 'cmx-misbuero'),
			'chart' => __('Diagramm', 'cmx-misbuero'),
			'phone' => __('Telefon', 'cmx-misbuero'),
			'mail' => __('E-Mail', 'cmx-misbuero'),
			'calendar' => __('Kalender', 'cmx-misbuero'),
			'map-pin' => __('Standort', 'cmx-misbuero'),
			'file-text' => __('Dokument', 'cmx-misbuero'),
			'heart' => __('Herz', 'cmx-misbuero'),
			'briefcase' => __('Koffer', 'cmx-misbuero'),
		];
	}

	public static function render(string $key, string $class = 'mib-icon'): string {
		$key = isset(self::paths()[$key]) ? $key : 'star';
		$path = self::paths()[$key];

		return '<svg class="' . \esc_attr($class) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . \esc_attr($path) . '"/></svg>';
	}

	private static function paths(): array {
		return [
			'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
			'rocket' => 'M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09M9 15l-2-2a22 22 0 0 1 2.2-3.42C11 7.08 13.32 5.2 16 4c1.56-.7 3.17-1.02 5-1-.02 1.83-.3 3.44-1 5-1.2 2.68-3.08 5-5.58 6.8A22 22 0 0 1 11 17l-2-2ZM15 9h.01',
			'headset' => 'M3 18v-6a9 9 0 0 1 18 0v6M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3v5ZM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3v5Z',
			'message' => 'M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8ZM8 9h8M8 13h5',
			'clipboard' => 'M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2M9 2h6v4H9V2ZM9 12h6M9 16h6',
			'gear' => 'M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5ZM19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6 1.65 1.65 0 0 0-.33 1V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.6-1 1.65 1.65 0 0 0-1-.33H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6c.38-.16.7-.38 1-.6.28-.32.42-.7.42-1.1V3a2 2 0 1 1 4 0v.09c0 .4.14.78.42 1.1.3.22.62.44 1 .6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.16.38.38.7.6 1 .32.28.7.42 1.1.42H21a2 2 0 1 1 0 4h-.09c-.4 0-.78.14-1.1.42-.22.3-.44.62-.6 1Z',
			'check' => 'M20 6 9 17l-5-5',
			'star' => 'm12 2 3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14 2 9.27l6.91-1.01L12 2Z',
			'diamond' => 'M6 3h12l4 6-10 12L2 9l4-6ZM2 9h20M12 21 8 9l4-6 4 6-4 12Z',
			'user' => 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
			'shield' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z',
			'chart' => 'M3 3v18h18M7 16v-5M12 16V7M17 16v-8',
			'phone' => 'M22 16.92v3a2 2 0 0 1-2.18 2A19.8 19.8 0 0 1 3.08 5.18 2 2 0 0 1 5.06 3h3a2 2 0 0 1 2 1.72c.12.9.32 1.77.6 2.61a2 2 0 0 1-.45 2.11L9 10.66a16 16 0 0 0 4.34 4.34l1.22-1.22a2 2 0 0 1 2.11-.45c.84.28 1.72.48 2.61.6A2 2 0 0 1 22 16.92Z',
			'mail' => 'M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm18 3-10 7L2 7',
			'calendar' => 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z',
			'map-pin' => 'M12 22s7-5.33 7-12a7 7 0 1 0-14 0c0 6.67 7 12 7 12ZM12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
			'file-text' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6ZM14 2v6h6M8 13h8M8 17h8M8 9h2',
			'heart' => 'M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 1 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z',
			'briefcase' => 'M10 6V5a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v1M3 7h18v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7ZM3 12h18M9 12v2h6v-2',
		];
	}
	}
