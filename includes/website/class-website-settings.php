<?php
namespace CLOUDMEISTER\CMX\Buero\Website;

defined('ABSPATH') || exit;

final class Settings {
	public const KEY = 'website';

	public static function init(): void {
		\add_action('admin_init', [self::class, 'register']);
		\add_action('admin_enqueue_scripts', [self::class, 'admin_assets']);
		\add_filter('pre_update_option_cmx_einstellungen', [self::class, 'sanitize_option'], 20, 2);
	}

	public static function register(): void {
		\add_settings_section(
			'cmx_sec_website',
			__('Website', 'cmx-misbuero'),
			'__return_false',
			'cmx_tab_website'
		);

		\add_settings_field(
			'cmx_website_settings',
			__('Website-Inhalte', 'cmx-misbuero'),
			[self::class, 'render'],
			'cmx_tab_website',
			'cmx_sec_website'
		);
	}

	public static function admin_assets(string $hook): void {
		$page = isset($_GET['page']) ? \sanitize_key((string) \wp_unslash($_GET['page'])) : '';
		$tab = isset($_GET['tab']) ? \sanitize_key((string) \wp_unslash($_GET['tab'])) : '';
		if ($page !== 'cmx-einstellungen' || $tab !== 'website') {
			return;
		}

		\wp_enqueue_media();
		\wp_add_inline_script('jquery-core', self::media_script(), 'after');
		\wp_register_style('cmx-website-admin', false, [], '1');
		\wp_enqueue_style('cmx-website-admin');
		\wp_add_inline_style('cmx-website-admin', self::admin_css());
	}

	public static function get(): array {
		$options = (array) \get_option('cmx_einstellungen', []);
		$saved = isset($options[self::KEY]) && \is_array($options[self::KEY]) ? $options[self::KEY] : [];
		$industry = isset($saved['industry']) ? \sanitize_key((string) $saved['industry']) : 'dienstleister';
		$defaults = Presets::defaults($industry);

		return self::merge_defaults($saved, $defaults);
	}

	public static function render(): void {
		$data = self::get();
		$name = 'cmx_einstellungen[' . self::KEY . ']';
		$industries = Presets::industries();

		echo '<div class="cmx-website-admin">';
		echo '<p class="description">' . \esc_html__('Diese Einstellungen steuern die öffentliche OnePager-Startseite dieser Mis-Büro-Instanz.', 'cmx-misbuero') . '</p>';

		echo '<div class="cmx-website-admin-actions">';
		echo '<button type="submit" class="button button-secondary" name="cmx_website_apply_preset" value="1">' . \esc_html__('Branchen-Preset übernehmen', 'cmx-misbuero') . '</button>';
		echo '<span class="description">' . \esc_html__('Überschreibt nur Website-Texte und Website-Kacheln, keine Firmen-, Kontakt- oder Rechtsdaten.', 'cmx-misbuero') . '</span>';
		echo '</div>';

		self::box(__('Allgemein', 'cmx-misbuero'));
		self::checkbox($name . '[enabled]', (string) ($data['enabled'] ?? '1'), __('Website aktivieren', 'cmx-misbuero'));
		self::select($name . '[industry]', (string) ($data['industry'] ?? 'dienstleister'), $industries, __('Branche', 'cmx-misbuero'));
		self::text($name . '[company_name]', (string) ($data['company_name'] ?? ''), __('Firmenname', 'cmx-misbuero'));
		self::media($name . '[logo_id]', (int) ($data['logo_id'] ?? 0), __('Firmenlogo', 'cmx-misbuero'));
		self::color($name . '[primary_color]', (string) ($data['primary_color'] ?? '#0F63F6'), __('Basisfarbe', 'cmx-misbuero'));
		self::media($name . '[header_image_id]', (int) ($data['header_image_id'] ?? 0), __('Header-Image', 'cmx-misbuero'));
		self::text($name . '[phone]', (string) ($data['phone'] ?? ''), __('Telefonnummer', 'cmx-misbuero'));
		self::email($name . '[email]', (string) ($data['email'] ?? ''), __('E-Mail-Adresse', 'cmx-misbuero'));
		self::url($name . '[contact_link]', (string) ($data['contact_link'] ?? ''), __('Kontaktformular-Ziel oder Kontakt-Link', 'cmx-misbuero'));
		self::end_box();

		self::box(__('Hero-Bereich', 'cmx-misbuero'));
		self::text($name . '[hero][kicker]', (string) ($data['hero']['kicker'] ?? ''), __('Kicker', 'cmx-misbuero'));
		self::textarea($name . '[hero][title]', (string) ($data['hero']['title'] ?? ''), __('Hauptüberschrift', 'cmx-misbuero'), 2);
		self::textarea($name . '[hero][text]', (string) ($data['hero']['text'] ?? ''), __('Subtext', 'cmx-misbuero'), 3);
		self::text($name . '[hero][primary_text]', (string) ($data['hero']['primary_text'] ?? ''), __('Primärer Button-Text', 'cmx-misbuero'));
		self::url($name . '[hero][primary_url]', (string) ($data['hero']['primary_url'] ?? ''), __('Primärer Button-Link', 'cmx-misbuero'));
		self::text($name . '[hero][secondary_text]', (string) ($data['hero']['secondary_text'] ?? ''), __('Sekundärer Button-Text', 'cmx-misbuero'));
		self::url($name . '[hero][secondary_url]', (string) ($data['hero']['secondary_url'] ?? ''), __('Sekundärer Button-Link oder Telefonnummer', 'cmx-misbuero'));
		self::end_box();

		self::section_editor($name, 'services', __('Leistungen', 'cmx-misbuero'), $data, 3, ['message', 'rocket', 'headset']);
		self::section_editor($name, 'process', __('Ablauf', 'cmx-misbuero'), $data, 4, ['message', 'clipboard', 'gear', 'check']);
		self::about_editor($name, $data);
		self::advantages_editor($name, $data);
		self::faq_editor($name, $data);
		self::contact_editor($name, $data);

		self::box(__('Rechtliches / Footer', 'cmx-misbuero'));
		self::url($name . '[legal][impressum_url]', (string) ($data['legal']['impressum_url'] ?? ''), __('Impressum-Link', 'cmx-misbuero'));
		self::url($name . '[legal][privacy_url]', (string) ($data['legal']['privacy_url'] ?? ''), __('Datenschutz-Link', 'cmx-misbuero'));
		self::url($name . '[legal][agb_url]', (string) ($data['legal']['agb_url'] ?? ''), __('AGB-Link', 'cmx-misbuero'));
		self::end_box();
		echo '</div>';
	}

	public static function sanitize_option($new, $old): array {
		$new = \is_array($new) ? $new : [];
		$old = \is_array($old) ? $old : [];

		if (!isset($new[self::KEY])) {
			if (isset($old[self::KEY])) {
				$new[self::KEY] = $old[self::KEY];
			}
			return $new;
		}
		$new = \array_merge($old, $new);

		$can_access = \function_exists('CLOUDMEISTER\\CMX\\Buero\\cmx_settings_current_user_can_access')
			? \CLOUDMEISTER\CMX\Buero\cmx_settings_current_user_can_access()
			: \current_user_can('manage_options');
		if (!$can_access) {
			$new[self::KEY] = $old[self::KEY] ?? [];
			return $new;
		}

		$current = isset($old[self::KEY]) && \is_array($old[self::KEY]) ? $old[self::KEY] : [];
		$incoming = isset($new[self::KEY]) && \is_array($new[self::KEY]) ? $new[self::KEY] : [];
		$sanitized = self::sanitize_website($incoming, $current);

		if (!empty($_POST['cmx_website_apply_preset'])) {
			$preset = Presets::content_preset((string) ($sanitized['industry'] ?? 'dienstleister'));
			foreach ($preset as $key => $value) {
				$sanitized[$key] = $value;
			}
			$sanitized = self::sanitize_website($sanitized, $current);
		}

		$new[self::KEY] = $sanitized;
		return $new;
	}

	public static function color_variants(string $hex): array {
		$hex = self::sanitize_color($hex);
		return [
			'primary' => $hex,
			'dark' => self::shade($hex, -42),
			'soft' => self::shade($hex, 88),
		];
	}

	private static function section_editor(string $base, string $key, string $label, array $data, int $count, array $fallback_icons): void {
		$section = isset($data[$key]) && \is_array($data[$key]) ? $data[$key] : [];
		self::box($label);
		self::checkbox($base . '[' . $key . '][enabled]', (string) ($section['enabled'] ?? '1'), __('Bereich aktivieren', 'cmx-misbuero'));
		self::text($base . '[' . $key . '][kicker]', (string) ($section['kicker'] ?? ''), __('Bereichs-Kicker', 'cmx-misbuero'));
		self::text($base . '[' . $key . '][title]', (string) ($section['title'] ?? ''), __('Überschrift', 'cmx-misbuero'));
		self::textarea($base . '[' . $key . '][subtitle]', (string) ($section['subtitle'] ?? ''), __('Sub-Überschrift', 'cmx-misbuero'), 2);
		echo '<div class="cmx-website-repeat">';
		for ($i = 0; $i < $count; $i++) {
			$item = isset($section['items'][$i]) && \is_array($section['items'][$i]) ? $section['items'][$i] : [];
			echo '<div class="cmx-website-repeat-item">';
			echo '<h4>' . \esc_html(\sprintf(__('Element %d', 'cmx-misbuero'), $i + 1)) . '</h4>';
			self::select($base . '[' . $key . '][items][' . $i . '][icon]', (string) ($item['icon'] ?? ($fallback_icons[$i] ?? 'star')), Icons::keys(), __('Icon', 'cmx-misbuero'));
			self::text($base . '[' . $key . '][items][' . $i . '][title]', (string) ($item['title'] ?? ''), __('Titel', 'cmx-misbuero'));
			self::textarea($base . '[' . $key . '][items][' . $i . '][text]', (string) ($item['text'] ?? ''), __('Text', 'cmx-misbuero'), 3);
			echo '</div>';
		}
		echo '</div>';
		self::end_box();
	}

	private static function about_editor(string $base, array $data): void {
		$section = isset($data['about']) && \is_array($data['about']) ? $data['about'] : [];
		self::box(__('Über uns', 'cmx-misbuero'));
		self::checkbox($base . '[about][enabled]', (string) ($section['enabled'] ?? '1'), __('Bereich aktivieren', 'cmx-misbuero'));
		self::text($base . '[about][kicker]', (string) ($section['kicker'] ?? ''), __('Bereichs-Kicker', 'cmx-misbuero'));
		self::text($base . '[about][title]', (string) ($section['title'] ?? ''), __('Überschrift', 'cmx-misbuero'));
		self::textarea($base . '[about][subtitle]', (string) ($section['subtitle'] ?? ''), __('Sub-Überschrift', 'cmx-misbuero'), 2);
		self::textarea($base . '[about][text]', (string) ($section['text'] ?? ''), __('Text', 'cmx-misbuero'), 5);
		self::media($base . '[about][image_id]', (int) ($section['image_id'] ?? 0), __('Optionales Bild', 'cmx-misbuero'));
		self::end_box();
	}

	private static function advantages_editor(string $base, array $data): void {
		$section = isset($data['advantages']) && \is_array($data['advantages']) ? $data['advantages'] : [];
		self::box(__('Warum wir?', 'cmx-misbuero'));
		self::text($base . '[advantages][kicker]', (string) ($section['kicker'] ?? ''), __('Kicker', 'cmx-misbuero'));
		self::text($base . '[advantages][title]', (string) ($section['title'] ?? ''), __('Hauptüberschrift', 'cmx-misbuero'));
		echo '<div class="cmx-website-repeat cmx-website-repeat-four">';
		for ($i = 0; $i < 4; $i++) {
			$item = isset($section['items'][$i]) && \is_array($section['items'][$i]) ? $section['items'][$i] : [];
			echo '<div class="cmx-website-repeat-item">';
			echo '<h4>' . \esc_html(\sprintf(__('Vorteil %d', 'cmx-misbuero'), $i + 1)) . '</h4>';
			self::select($base . '[advantages][items][' . $i . '][icon]', (string) ($item['icon'] ?? 'star'), Icons::keys(), __('Icon', 'cmx-misbuero'));
			self::text($base . '[advantages][items][' . $i . '][title]', (string) ($item['title'] ?? ''), __('Titel', 'cmx-misbuero'));
			self::textarea($base . '[advantages][items][' . $i . '][text]', (string) ($item['text'] ?? ''), __('Text', 'cmx-misbuero'), 2);
			echo '</div>';
		}
		echo '</div>';
		self::end_box();
	}

	private static function faq_editor(string $base, array $data): void {
		$section = isset($data['faq']) && \is_array($data['faq']) ? $data['faq'] : [];
		self::box(__('FAQ', 'cmx-misbuero'));
		self::checkbox($base . '[faq][enabled]', (string) ($section['enabled'] ?? '1'), __('Bereich aktivieren', 'cmx-misbuero'));
		self::text($base . '[faq][kicker]', (string) ($section['kicker'] ?? ''), __('Kicker', 'cmx-misbuero'));
		self::text($base . '[faq][title]', (string) ($section['title'] ?? ''), __('Überschrift', 'cmx-misbuero'));
		self::textarea($base . '[faq][subtitle]', (string) ($section['subtitle'] ?? ''), __('Sub-Überschrift', 'cmx-misbuero'), 2);
		for ($i = 0; $i < 4; $i++) {
			$item = isset($section['items'][$i]) && \is_array($section['items'][$i]) ? $section['items'][$i] : [];
			echo '<div class="cmx-website-inline-pair">';
			self::text($base . '[faq][items][' . $i . '][question]', (string) ($item['question'] ?? ''), \sprintf(__('Frage %d', 'cmx-misbuero'), $i + 1));
			self::textarea($base . '[faq][items][' . $i . '][answer]', (string) ($item['answer'] ?? ''), __('Antwort', 'cmx-misbuero'), 2);
			echo '</div>';
		}
		self::end_box();
	}

	private static function contact_editor(string $base, array $data): void {
		$section = isset($data['contact']) && \is_array($data['contact']) ? $data['contact'] : [];
		self::box(__('Kontakt', 'cmx-misbuero'));
		self::checkbox($base . '[contact][enabled]', (string) ($section['enabled'] ?? '1'), __('Bereich aktivieren', 'cmx-misbuero'));
		self::text($base . '[contact][kicker]', (string) ($section['kicker'] ?? ''), __('Kicker', 'cmx-misbuero'));
		self::text($base . '[contact][title]', (string) ($section['title'] ?? ''), __('Überschrift', 'cmx-misbuero'));
		self::textarea($base . '[contact][subtitle]', (string) ($section['subtitle'] ?? ''), __('Sub-Überschrift', 'cmx-misbuero'), 2);
		self::textarea($base . '[contact][address]', (string) ($section['address'] ?? ''), __('Adresse', 'cmx-misbuero'), 3);
		self::text($base . '[contact][button_text]', (string) ($section['button_text'] ?? ''), __('Button-Text', 'cmx-misbuero'));
		self::url($base . '[contact][button_url]', (string) ($section['button_url'] ?? ''), __('Button-Link', 'cmx-misbuero'));
		self::end_box();
	}

	private static function sanitize_website(array $data, array $old): array {
		$industry = \sanitize_key((string) ($data['industry'] ?? ($old['industry'] ?? 'dienstleister')));
		if (!isset(Presets::industries()[$industry])) {
			$industry = 'dienstleister';
		}

		$out = [
			'enabled' => !empty($data['enabled']) ? '1' : '0',
			'industry' => $industry,
			'company_name' => \sanitize_text_field((string) ($data['company_name'] ?? '')),
			'logo_id' => \absint($data['logo_id'] ?? 0),
			'primary_color' => self::sanitize_color((string) ($data['primary_color'] ?? '#0F63F6')),
			'header_image_id' => \absint($data['header_image_id'] ?? 0),
			'phone' => \sanitize_text_field((string) ($data['phone'] ?? '')),
			'email' => \sanitize_email((string) ($data['email'] ?? '')),
			'contact_link' => self::sanitize_url_or_anchor((string) ($data['contact_link'] ?? '')),
			'hero' => self::sanitize_text_group((array) ($data['hero'] ?? []), ['kicker', 'title', 'text', 'primary_text', 'primary_url', 'secondary_text', 'secondary_url']),
			'legal' => [
				'impressum_url' => self::sanitize_url_or_anchor((string) ($data['legal']['impressum_url'] ?? '')),
				'privacy_url' => self::sanitize_url_or_anchor((string) ($data['legal']['privacy_url'] ?? '')),
				'agb_url' => self::sanitize_url_or_anchor((string) ($data['legal']['agb_url'] ?? '')),
			],
		];
		foreach (['primary_url', 'secondary_url'] as $url_key) {
			$out['hero'][$url_key] = self::sanitize_url_or_anchor((string) ($data['hero'][$url_key] ?? ''));
		}

		$out['services'] = self::sanitize_section((array) ($data['services'] ?? []), 3);
		$out['process'] = self::sanitize_section((array) ($data['process'] ?? []), 4);
		$out['about'] = self::sanitize_about((array) ($data['about'] ?? []));
		$out['advantages'] = self::sanitize_advantages((array) ($data['advantages'] ?? []));
		$out['faq'] = self::sanitize_faq((array) ($data['faq'] ?? []));
		$out['contact'] = self::sanitize_contact((array) ($data['contact'] ?? []));

		return $out;
	}

	private static function sanitize_section(array $section, int $count): array {
		$out = [
			'enabled' => !empty($section['enabled']) ? '1' : '0',
			'kicker' => \sanitize_text_field((string) ($section['kicker'] ?? '')),
			'title' => \sanitize_text_field((string) ($section['title'] ?? '')),
			'subtitle' => \wp_kses_post((string) ($section['subtitle'] ?? '')),
			'items' => [],
		];
		for ($i = 0; $i < $count; $i++) {
			$item = isset($section['items'][$i]) && \is_array($section['items'][$i]) ? $section['items'][$i] : [];
			$out['items'][] = self::sanitize_card($item);
		}
		return $out;
	}

	private static function sanitize_about(array $section): array {
		return [
			'enabled' => !empty($section['enabled']) ? '1' : '0',
			'kicker' => \sanitize_text_field((string) ($section['kicker'] ?? '')),
			'title' => \sanitize_text_field((string) ($section['title'] ?? '')),
			'subtitle' => \wp_kses_post((string) ($section['subtitle'] ?? '')),
			'text' => \wp_kses_post((string) ($section['text'] ?? '')),
			'image_id' => \absint($section['image_id'] ?? 0),
		];
	}

	private static function sanitize_advantages(array $section): array {
		$out = [
			'kicker' => \sanitize_text_field((string) ($section['kicker'] ?? '')),
			'title' => \sanitize_text_field((string) ($section['title'] ?? '')),
			'items' => [],
		];
		for ($i = 0; $i < 4; $i++) {
			$item = isset($section['items'][$i]) && \is_array($section['items'][$i]) ? $section['items'][$i] : [];
			$out['items'][] = self::sanitize_card($item);
		}
		return $out;
	}

	private static function sanitize_faq(array $section): array {
		$out = [
			'enabled' => !empty($section['enabled']) ? '1' : '0',
			'kicker' => \sanitize_text_field((string) ($section['kicker'] ?? '')),
			'title' => \sanitize_text_field((string) ($section['title'] ?? '')),
			'subtitle' => \wp_kses_post((string) ($section['subtitle'] ?? '')),
			'items' => [],
		];
		for ($i = 0; $i < 4; $i++) {
			$item = isset($section['items'][$i]) && \is_array($section['items'][$i]) ? $section['items'][$i] : [];
			$out['items'][] = [
				'question' => \sanitize_text_field((string) ($item['question'] ?? '')),
				'answer' => \wp_kses_post((string) ($item['answer'] ?? '')),
			];
		}
		return $out;
	}

	private static function sanitize_contact(array $section): array {
		return [
			'enabled' => !empty($section['enabled']) ? '1' : '0',
			'kicker' => \sanitize_text_field((string) ($section['kicker'] ?? '')),
			'title' => \sanitize_text_field((string) ($section['title'] ?? '')),
			'subtitle' => \wp_kses_post((string) ($section['subtitle'] ?? '')),
			'address' => \wp_kses_post((string) ($section['address'] ?? '')),
			'button_text' => \sanitize_text_field((string) ($section['button_text'] ?? '')),
			'button_url' => self::sanitize_url_or_anchor((string) ($section['button_url'] ?? '')),
		];
	}

	private static function sanitize_card(array $item): array {
		$icon = \sanitize_key((string) ($item['icon'] ?? 'star'));
		return [
			'icon' => isset(Icons::keys()[$icon]) ? $icon : 'star',
			'title' => \sanitize_text_field((string) ($item['title'] ?? '')),
			'text' => \wp_kses_post((string) ($item['text'] ?? '')),
		];
	}

	private static function sanitize_text_group(array $data, array $keys): array {
		$out = [];
		foreach ($keys as $key) {
			$out[$key] = \in_array($key, ['text', 'title'], true)
				? \wp_kses_post((string) ($data[$key] ?? ''))
				: \sanitize_text_field((string) ($data[$key] ?? ''));
		}
		return $out;
	}

	private static function sanitize_url_or_anchor(string $url): string {
		$url = \trim($url);
		if ($url === '') {
			return '';
		}
		if (\str_starts_with($url, '#')) {
			return '#' . \sanitize_title(\substr($url, 1));
		}
		if (\str_starts_with($url, 'tel:') || \str_starts_with($url, 'mailto:')) {
			return \esc_url_raw($url, ['tel', 'mailto']);
		}
		return \esc_url_raw($url);
	}

	private static function sanitize_color(string $hex): string {
		$hex = \trim($hex);
		return \preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1 ? \strtoupper($hex) : '#0F63F6';
	}

	private static function shade(string $hex, int $percent): string {
		$hex = \ltrim(self::sanitize_color($hex), '#');
		$r = \hexdec(\substr($hex, 0, 2));
		$g = \hexdec(\substr($hex, 2, 2));
		$b = \hexdec(\substr($hex, 4, 2));
		$target = $percent < 0 ? 0 : 255;
		$p = \abs($percent) / 100;
		$r = (int) \round($r + ($target - $r) * $p);
		$g = (int) \round($g + ($target - $g) * $p);
		$b = (int) \round($b + ($target - $b) * $p);
		return \sprintf('#%02X%02X%02X', \max(0, \min(255, $r)), \max(0, \min(255, $g)), \max(0, \min(255, $b)));
	}

	private static function merge_defaults(array $data, array $defaults): array {
		foreach ($defaults as $key => $value) {
			if (!\array_key_exists($key, $data) || $data[$key] === '' || $data[$key] === []) {
				$data[$key] = $value;
				continue;
			}
			if (\is_array($value) && \is_array($data[$key])) {
				$data[$key] = self::merge_defaults($data[$key], $value);
			}
		}
		return $data;
	}

	private static function box(string $title): void {
		echo '<section class="cmx-website-admin-box"><h3>' . \esc_html($title) . '</h3>';
	}

	private static function end_box(): void {
		echo '</section>';
	}

	private static function text(string $name, string $value, string $label): void {
		echo '<label class="cmx-website-field"><span>' . \esc_html($label) . '</span><input type="text" class="regular-text" name="' . \esc_attr($name) . '" value="' . \esc_attr($value) . '"></label>';
	}

	private static function email(string $name, string $value, string $label): void {
		echo '<label class="cmx-website-field"><span>' . \esc_html($label) . '</span><input type="email" class="regular-text" name="' . \esc_attr($name) . '" value="' . \esc_attr($value) . '"></label>';
	}

	private static function url(string $name, string $value, string $label): void {
		echo '<label class="cmx-website-field"><span>' . \esc_html($label) . '</span><input type="text" class="regular-text" name="' . \esc_attr($name) . '" value="' . \esc_attr($value) . '" placeholder="https://... oder #kontakt"></label>';
	}

	private static function color(string $name, string $value, string $label): void {
		echo '<label class="cmx-website-field"><span>' . \esc_html($label) . '</span><input type="color" name="' . \esc_attr($name) . '" value="' . \esc_attr(self::sanitize_color($value)) . '"></label>';
	}

	private static function textarea(string $name, string $value, string $label, int $rows): void {
		echo '<label class="cmx-website-field cmx-website-field-full"><span>' . \esc_html($label) . '</span><textarea name="' . \esc_attr($name) . '" rows="' . \esc_attr((string) $rows) . '">' . \esc_textarea($value) . '</textarea></label>';
	}

	private static function checkbox(string $name, string $value, string $label): void {
		echo '<label class="cmx-website-field cmx-website-checkbox"><input type="hidden" name="' . \esc_attr($name) . '" value="0"><input type="checkbox" name="' . \esc_attr($name) . '" value="1" ' . \checked($value, '1', false) . '> <span>' . \esc_html($label) . '</span></label>';
	}

	private static function select(string $name, string $value, array $options, string $label): void {
		echo '<label class="cmx-website-field"><span>' . \esc_html($label) . '</span><select name="' . \esc_attr($name) . '">';
		foreach ($options as $key => $option_label) {
			echo '<option value="' . \esc_attr((string) $key) . '" ' . \selected($value, (string) $key, false) . '>' . \esc_html((string) $option_label) . '</option>';
		}
		echo '</select></label>';
	}

	private static function media(string $name, int $id, string $label): void {
		$preview = $id > 0 ? \wp_get_attachment_image($id, 'thumbnail', false, ['class' => 'cmx-website-media-preview']) : '';
		echo '<div class="cmx-website-field cmx-website-media"><span>' . \esc_html($label) . '</span><div class="cmx-website-media-row">';
		echo '<input type="number" min="0" name="' . \esc_attr($name) . '" value="' . \esc_attr((string) $id) . '">';
		echo '<button type="button" class="button cmx-website-media-button">' . \esc_html__('Bild auswählen', 'cmx-misbuero') . '</button>';
		echo '<button type="button" class="button cmx-website-media-clear">' . \esc_html__('Entfernen', 'cmx-misbuero') . '</button>';
		echo '<div class="cmx-website-media-preview-wrap">' . $preview . '</div></div></div>';
	}

	private static function media_script(): string {
		return 'jQuery(function($){$(".cmx-website-admin").on("click",".cmx-website-media-button",function(e){e.preventDefault();var box=$(this).closest(".cmx-website-media");var input=box.find("input");var preview=box.find(".cmx-website-media-preview-wrap");var frame=wp.media({title:"Bild auswählen",button:{text:"Übernehmen"},multiple:false});frame.on("select",function(){var file=frame.state().get("selection").first().toJSON();input.val(file.id);preview.html(file.sizes&&file.sizes.thumbnail?"<img class=\"cmx-website-media-preview\" src=\""+file.sizes.thumbnail.url+"\" alt=\"\">":"");});frame.open();}).on("click",".cmx-website-media-clear",function(e){e.preventDefault();var box=$(this).closest(".cmx-website-media");box.find("input").val("0");box.find(".cmx-website-media-preview-wrap").empty();});});';
	}

	private static function admin_css(): string {
		return '.cmx-website-admin{max-width:1180px}.cmx-website-admin-actions{display:flex;gap:12px;align-items:center;margin:14px 0 18px}.cmx-website-admin-box{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px 18px 8px;margin:0 0 18px}.cmx-website-admin-box h3{margin:0 0 14px;font-size:16px}.cmx-website-field{display:inline-flex;vertical-align:top;flex-direction:column;gap:6px;margin:0 18px 14px 0;min-width:260px}.cmx-website-field>span{font-weight:600}.cmx-website-field input[type=text],.cmx-website-field input[type=email],.cmx-website-field input[type=number],.cmx-website-field select,.cmx-website-field textarea{width:100%;max-width:520px}.cmx-website-field-full{display:flex;max-width:760px}.cmx-website-field-full textarea{max-width:760px}.cmx-website-checkbox{display:flex;flex-direction:row;align-items:center;min-width:100%;gap:8px}.cmx-website-repeat{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.cmx-website-repeat-four{grid-template-columns:repeat(4,minmax(0,1fr))}.cmx-website-repeat-item{border:1px solid #e5e7eb;border-radius:8px;padding:12px;background:#f8fafc}.cmx-website-repeat-item h4{margin:0 0 10px}.cmx-website-repeat-item .cmx-website-field{display:flex;min-width:0;margin-right:0}.cmx-website-media-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.cmx-website-media-row input{width:90px!important}.cmx-website-media-preview{width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #dcdcde}.cmx-website-inline-pair{border-top:1px solid #e5e7eb;padding-top:12px;margin-top:8px}@media(max-width:1100px){.cmx-website-repeat,.cmx-website-repeat-four{grid-template-columns:1fr 1fr}}@media(max-width:782px){.cmx-website-repeat,.cmx-website-repeat-four{grid-template-columns:1fr}.cmx-website-field{display:flex;min-width:100%;margin-right:0}}';
	}
}
