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
			__('Inhalte', 'cmx-misbuero'),
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

		\wp_register_style('cmx-website-admin', false, [], '1');
		\wp_enqueue_style('cmx-website-admin');
		\wp_add_inline_style('cmx-website-admin', self::admin_css());
		\wp_register_script('cmx-website-admin', '', [], '1', true);
		\wp_enqueue_script('cmx-website-admin');
		\wp_add_inline_script('cmx-website-admin', self::admin_js());
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
		echo '<p class="description">';
		echo \wp_kses(
			\sprintf(
				/* translators: %s: linked public website label */
				__('Diese Einstellungen steuern die %s dieser Mis-Büro-Instanz.', 'cmx-misbuero'),
				'<a href="' . \esc_url(\home_url('/')) . '" target="_blank" rel="noopener noreferrer">' . \esc_html__('öffentliche OnePager-Startseite', 'cmx-misbuero') . '</a>'
			),
			[
				'a' => [
					'href' => true,
					'target' => true,
					'rel' => true,
				],
			]
		);
		echo '</p>';

		echo '<div class="cmx-website-admin-actions">';
		$preset_confirm = __('Das Branchen-Preset überschreibt die Website-Texte und Website-Kacheln. Möchtest Du das Preset wirklich übernehmen?', 'cmx-misbuero');
		echo '<button type="submit" class="button button-secondary" name="cmx_website_apply_preset" value="1" onclick="return window.confirm(' . \esc_attr((string) \wp_json_encode($preset_confirm)) . ');">' . \esc_html__('Branchen-Preset übernehmen', 'cmx-misbuero') . '</button>';
		echo '<span class="description">' . \esc_html__('Überschreibt nur Website-Texte und Website-Kacheln, keine Firmen-, Kontakt- oder Rechtsdaten.', 'cmx-misbuero') . '</span>';
		echo '</div>';

		self::box(__('Allgemein', 'cmx-misbuero'));
		self::checkbox($name . '[enabled]', (string) ($data['enabled'] ?? '1'), __('Website aktivieren', 'cmx-misbuero'));
		self::select($name . '[industry]', (string) ($data['industry'] ?? 'dienstleister'), $industries, __('Branche', 'cmx-misbuero'));
		self::text($name . '[company_name]', (string) ($data['company_name'] ?? ''), __('Firmenname', 'cmx-misbuero'));
		self::color($name . '[primary_color]', (string) ($data['primary_color'] ?? '#0F63F6'), __('Basisfarbe', 'cmx-misbuero'));
		echo '<br>';
		self::file($name . '[logo_file]', 'cmx_website_logo_file', (string) ($data['logo_file'] ?? ''), __('Firmenlogo', 'cmx-misbuero'), self::self_logo_url());
		self::file($name . '[header_image_file]', 'cmx_website_header_image_file', (string) ($data['header_image_file'] ?? ''), __('Header-Image', 'cmx-misbuero'));
		echo '<br>';
		self::email($name . '[email]', (string) ($data['email'] ?? ''), __('E-Mail-Adresse', 'cmx-misbuero'));
		self::url($name . '[contact_link]', (string) ($data['contact_link'] ?? ''), __('Kontaktformular-Ziel oder Kontakt-Link', 'cmx-misbuero'));
		self::text($name . '[phone]', (string) ($data['phone'] ?? ''), __('Telefonnummer', 'cmx-misbuero'));
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

		self::hero_points_editor($name, $data);
		self::section_editor($name, 'services', __('Leistungen', 'cmx-misbuero'), $data, 3, ['message', 'rocket', 'headset']);
		self::articles_editor($name, $data);
		self::section_editor($name, 'process', __('Ablauf', 'cmx-misbuero'), $data, 4, ['message', 'clipboard', 'gear', 'check']);
		self::about_editor($name, $data);
		self::advantages_editor($name, $data);
		self::faq_editor($name, $data);
		self::contact_editor($name, $data);
		self::opening_hours_editor($name, $data);

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
		if (\class_exists(__NAMESPACE__ . '\\Renderer')) {
			Renderer::clear_public_cache();
		}
		return $new;
	}

	public static function color_variants(string $hex): array {
		$hex = self::sanitize_color($hex);
		return [
			'primary' => $hex,
			'dark' => self::shade($hex, -42),
			'soft' => self::shade($hex, 88),
			'rgb' => self::hex_to_rgb($hex),
		];
	}

	private static function hex_to_rgb(string $hex): string {
		$hex = \ltrim(self::sanitize_color($hex), '#');
		return \implode(',', [
			\hexdec(\substr($hex, 0, 2)),
			\hexdec(\substr($hex, 2, 2)),
			\hexdec(\substr($hex, 4, 2)),
		]);
	}

	private static function section_editor(string $base, string $key, string $label, array $data, int $count, array $fallback_icons): void {
		$section = isset($data[$key]) && \is_array($data[$key]) ? $data[$key] : [];
		self::box($label);
		self::checkbox($base . '[' . $key . '][enabled]', (string) ($section['enabled'] ?? '1'), __('Bereich aktivieren', 'cmx-misbuero'));
		self::text($base . '[' . $key . '][kicker]', (string) ($section['kicker'] ?? ''), __('Bereichs-Kicker', 'cmx-misbuero'));
		self::text($base . '[' . $key . '][title]', (string) ($section['title'] ?? ''), __('Überschrift', 'cmx-misbuero'));
		self::textarea($base . '[' . $key . '][subtitle]', (string) ($section['subtitle'] ?? ''), __('Sub-Überschrift', 'cmx-misbuero'), 2);
		echo '<div class="cmx-website-repeat' . ($count === 4 ? ' cmx-website-repeat-four' : '') . '">';
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

	private static function hero_points_editor(string $base, array $data): void {
		$items = isset($data['hero_points']) && \is_array($data['hero_points']) ? $data['hero_points'] : [];
		self::box(__('Hero-Punkte', 'cmx-misbuero'));
		echo '<div class="cmx-website-repeat">';
		for ($i = 0; $i < 3; $i++) {
			$item = isset($items[$i]) && \is_array($items[$i]) ? $items[$i] : [];
			echo '<div class="cmx-website-repeat-item">';
			echo '<h4>' . \esc_html(\sprintf(__('Punkt %d', 'cmx-misbuero'), $i + 1)) . '</h4>';
			self::select($base . '[hero_points][' . $i . '][icon]', (string) ($item['icon'] ?? 'star'), Icons::keys(), __('Icon', 'cmx-misbuero'));
			self::text($base . '[hero_points][' . $i . '][title]', (string) ($item['title'] ?? ''), __('Text', 'cmx-misbuero'));
			echo '</div>';
		}
		echo '</div>';
		self::end_box();
	}

	private static function articles_editor(string $base, array $data): void {
		$section = isset($data['articles']) && \is_array($data['articles']) ? $data['articles'] : [];
		self::box(__('Artikel', 'cmx-misbuero'));
		self::checkbox($base . '[articles][enabled]', (string) ($section['enabled'] ?? '0'), __('Bereich aktivieren', 'cmx-misbuero'));
		self::text($base . '[articles][kicker]', (string) ($section['kicker'] ?? ''), __('Bereichs-Kicker', 'cmx-misbuero'));
		self::text($base . '[articles][title]', (string) ($section['title'] ?? ''), __('Überschrift', 'cmx-misbuero'));
		self::textarea($base . '[articles][subtitle]', (string) ($section['subtitle'] ?? ''), __('Sub-Überschrift', 'cmx-misbuero'), 2);
		echo '<p class="description cmx-website-inline-pair">' . \esc_html__('Angezeigt werden Artikel-Varianten, bei denen in den Artikel-Stammdaten die Checkbox „Website“ aktiv ist.', 'cmx-misbuero') . '</p>';
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
		self::file($base . '[about][image_file]', 'cmx_website_about_image_file', (string) ($section['image_file'] ?? ''), __('Optionales Bild', 'cmx-misbuero'));
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
		echo '<div class="cmx-website-repeat cmx-website-repeat-four">';
		for ($i = 0; $i < 4; $i++) {
			$item = isset($section['items'][$i]) && \is_array($section['items'][$i]) ? $section['items'][$i] : [];
			echo '<div class="cmx-website-repeat-item">';
			self::text($base . '[faq][items][' . $i . '][question]', (string) ($item['question'] ?? ''), \sprintf(__('Frage %d', 'cmx-misbuero'), $i + 1));
			self::textarea($base . '[faq][items][' . $i . '][answer]', (string) ($item['answer'] ?? ''), __('Antwort', 'cmx-misbuero'), 2);
			echo '</div>';
		}
		echo '</div>';
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

	private static function opening_hours_editor(string $base, array $data): void {
		$section = isset($data['opening_hours']) && \is_array($data['opening_hours']) ? $data['opening_hours'] : [];
		$days = isset($section['days']) && \is_array($section['days']) ? $section['days'] : [];
		self::box(__('Öffnungszeiten', 'cmx-misbuero'));
		self::checkbox($base . '[opening_hours][enabled]', (string) ($section['enabled'] ?? '1'), __('Bereich aktivieren', 'cmx-misbuero'));
		self::text($base . '[opening_hours][kicker]', (string) ($section['kicker'] ?? ''), __('Kicker', 'cmx-misbuero'));
		self::text($base . '[opening_hours][title]', (string) ($section['title'] ?? ''), __('Überschrift', 'cmx-misbuero'));
		self::textarea($base . '[opening_hours][subtitle]', (string) ($section['subtitle'] ?? ''), __('Sub-Überschrift', 'cmx-misbuero'), 2);
		echo '<div class="cmx-website-hours" data-cmx-website-hours>';
		foreach (self::opening_hour_day_labels() as $day_key => $day_label) {
			$slots = isset($days[$day_key]) && \is_array($days[$day_key]) ? \array_values($days[$day_key]) : [];
			if ($slots === []) {
				$slots = [['start' => '', 'end' => '']];
			}
			echo '<div class="cmx-website-hours-day" data-day="' . \esc_attr($day_key) . '" data-name-prefix="' . \esc_attr($base . '[opening_hours][days][' . $day_key . ']') . '" data-next-index="' . \esc_attr((string) \count($slots)) . '">';
			echo '<div class="cmx-website-hours-day-head"><strong>' . \esc_html($day_label) . '</strong></div>';
			echo '<div class="cmx-website-hours-slots" data-cmx-hours-slots>';
			foreach ($slots as $index => $slot) {
				$slot = \is_array($slot) ? $slot : [];
				self::opening_hour_slot($base . '[opening_hours][days][' . $day_key . '][' . $index . ']', (string) ($slot['start'] ?? ''), (string) ($slot['end'] ?? ''));
			}
			echo '</div>';
			echo '</div>';
		}
		echo '</div>';
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
			'logo_file' => !empty($data['logo_file_remove']) ? '' : self::sanitize_file_path((string) ($data['logo_file'] ?? ($old['logo_file'] ?? ''))),
			'primary_color' => self::sanitize_color((string) ($data['primary_color'] ?? '#0F63F6')),
			'header_image_file' => !empty($data['header_image_file_remove']) ? '' : self::sanitize_file_path((string) ($data['header_image_file'] ?? ($old['header_image_file'] ?? ''))),
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
		$out['hero_points'] = self::sanitize_hero_points((array) ($data['hero_points'] ?? []));
		$out['articles'] = self::sanitize_simple_section((array) ($data['articles'] ?? []));
		$out['process'] = self::sanitize_section((array) ($data['process'] ?? []), 4);
		$out['about'] = self::sanitize_about((array) ($data['about'] ?? []));
		$out['advantages'] = self::sanitize_advantages((array) ($data['advantages'] ?? []));
		$out['faq'] = self::sanitize_faq((array) ($data['faq'] ?? []));
		$out['contact'] = self::sanitize_contact((array) ($data['contact'] ?? []));
		$out['opening_hours'] = self::sanitize_opening_hours((array) ($data['opening_hours'] ?? []));
		self::handle_file_uploads($out);

		return $out;
	}

	private static function sanitize_hero_points(array $items): array {
		$out = [];
		for ($i = 0; $i < 3; $i++) {
			$item = isset($items[$i]) && \is_array($items[$i]) ? $items[$i] : [];
			$icon = \sanitize_key((string) ($item['icon'] ?? 'star'));
			$out[] = [
				'icon' => isset(Icons::keys()[$icon]) ? $icon : 'star',
				'title' => \sanitize_text_field((string) ($item['title'] ?? '')),
			];
		}
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

	private static function sanitize_simple_section(array $section): array {
		return [
			'enabled' => !empty($section['enabled']) ? '1' : '0',
			'kicker' => \sanitize_text_field((string) ($section['kicker'] ?? '')),
			'title' => \sanitize_text_field((string) ($section['title'] ?? '')),
			'subtitle' => \wp_kses_post((string) ($section['subtitle'] ?? '')),
		];
	}

	private static function sanitize_about(array $section): array {
		return [
			'enabled' => !empty($section['enabled']) ? '1' : '0',
			'kicker' => \sanitize_text_field((string) ($section['kicker'] ?? '')),
			'title' => \sanitize_text_field((string) ($section['title'] ?? '')),
			'subtitle' => \wp_kses_post((string) ($section['subtitle'] ?? '')),
			'text' => \wp_kses_post((string) ($section['text'] ?? '')),
			'image_file' => self::sanitize_file_path((string) ($section['image_file'] ?? '')),
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

	private static function sanitize_opening_hours(array $section): array {
		$out = [
			'enabled' => !empty($section['enabled']) ? '1' : '0',
			'kicker' => \sanitize_text_field((string) ($section['kicker'] ?? '')),
			'title' => \sanitize_text_field((string) ($section['title'] ?? '')),
			'subtitle' => \wp_kses_post((string) ($section['subtitle'] ?? '')),
			'days' => [],
		];
		$days = isset($section['days']) && \is_array($section['days']) ? $section['days'] : [];
		foreach (self::opening_hour_day_labels() as $day_key => $day_label) {
			$out['days'][$day_key] = [];
			$slots = isset($days[$day_key]) && \is_array($days[$day_key]) ? $days[$day_key] : [];
			foreach ($slots as $slot) {
				if (!\is_array($slot)) {
					continue;
				}
				$start = self::sanitize_time((string) ($slot['start'] ?? ''));
				$end = self::sanitize_time((string) ($slot['end'] ?? ''));
				if ($start === '' || $end === '') {
					continue;
				}
				if (self::time_to_minutes($end) <= self::time_to_minutes($start)) {
					continue;
				}
				$out['days'][$day_key][] = [
					'start' => $start,
					'end' => $end,
				];
			}
		}
		return $out;
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

	private static function sanitize_time(string $time): string {
		$time = \trim($time);
		if (\preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1) {
			return $time;
		}
		if (\preg_match('/^(\d{1,2})[.:]([0-5]\d)$/', $time, $matches) === 1) {
			$hour = (int) $matches[1];
			if ($hour >= 0 && $hour <= 23) {
				return \sprintf('%02d:%s', $hour, (string) $matches[2]);
			}
		}
		return '';
	}

	private static function time_to_minutes(string $time): int {
		if (\preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $matches) !== 1) {
			return -1;
		}
		return ((int) $matches[1] * 60) + (int) $matches[2];
	}

	private static function sanitize_color(string $hex): string {
		$hex = \trim($hex);
		return \preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1 ? \strtoupper($hex) : '#0F63F6';
	}

	private static function sanitize_file_path(string $path): string {
		if (\function_exists('CLOUDMEISTER\\CMX\\Buero\\cmx_dav_normalize_rel_path')) {
			return (string) \CLOUDMEISTER\CMX\Buero\cmx_dav_normalize_rel_path($path);
		}
		$path = \str_replace('\\', '/', \trim($path));
		$parts = [];
		foreach (\explode('/', $path) as $part) {
			$part = \sanitize_file_name($part);
			if ($part === '' || $part === '.' || $part === '..') {
				continue;
			}
			$parts[] = $part;
		}
		return \implode('/', $parts);
	}

	private static function handle_file_uploads(array &$out): void {
		$map = [
			'cmx_website_logo_file' => ['target' => ['logo_file'], 'subdir' => 'allgemein'],
			'cmx_website_header_image_file' => ['target' => ['header_image_file'], 'subdir' => 'hero'],
			'cmx_website_about_image_file' => ['target' => ['about', 'image_file'], 'subdir' => 'ueber-uns'],
		];
		foreach ($map as $field => $config) {
			$file = $_FILES[$field] ?? null;
			if (!\is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
				continue;
			}
			if (!\function_exists('CLOUDMEISTER\\CMX\\Buero\\cmx_dav_store_uploaded_file')) {
				continue;
			}
			$result = \CLOUDMEISTER\CMX\Buero\cmx_dav_store_uploaded_file('website', $file, (string) $config['subdir'], [
				'jpg' => 'image/jpeg',
				'jpeg' => 'image/jpeg',
				'png' => 'image/png',
				'webp' => 'image/webp',
				'gif' => 'image/gif',
			]);
			if (\is_wp_error($result) || empty($result['rel_path'])) {
				continue;
			}
			$target = (array) $config['target'];
			if (\count($target) === 1) {
				$out[(string) $target[0]] = (string) $result['rel_path'];
			} elseif (\count($target) === 2) {
				$out[(string) $target[0]][(string) $target[1]] = (string) $result['rel_path'];
			}
		}
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

	private static function opening_hour_slot(string $name, string $start, string $end): void {
		echo '<div class="cmx-website-hours-slot" data-cmx-hours-slot>';
		echo '<label><span>' . \esc_html__('Von', 'cmx-misbuero') . '</span><input type="time" name="' . \esc_attr($name . '[start]') . '" value="' . \esc_attr($start) . '"></label>';
		echo '<label><span>' . \esc_html__('Bis', 'cmx-misbuero') . '</span><input type="time" name="' . \esc_attr($name . '[end]') . '" value="' . \esc_attr($end) . '"></label>';
		echo '<button type="button" class="button button-link-delete cmx-hours-icon-button" data-cmx-hours-remove title="' . \esc_attr__('Zeit entfernen', 'cmx-misbuero') . '" aria-label="' . \esc_attr__('Zeit entfernen', 'cmx-misbuero') . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>';
		echo '<button type="button" class="button button-secondary cmx-hours-icon-button" data-cmx-hours-add title="' . \esc_attr__('Zeit hinzufügen', 'cmx-misbuero') . '" aria-label="' . \esc_attr__('Zeit hinzufügen', 'cmx-misbuero') . '"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span></button>';
		echo '</div>';
	}

	private static function select(string $name, string $value, array $options, string $label): void {
		echo '<label class="cmx-website-field"><span>' . \esc_html($label) . '</span><select name="' . \esc_attr($name) . '">';
		foreach ($options as $key => $option_label) {
			echo '<option value="' . \esc_attr((string) $key) . '" ' . \selected($value, (string) $key, false) . '>' . \esc_html((string) $option_label) . '</option>';
		}
		echo '</select></label>';
	}

	private static function file(string $name, string $field_name, string $path, string $label, string $fallback_url = ''): void {
		$url = '';
		if ($path !== '' && \function_exists('CLOUDMEISTER\\CMX\\Buero\\cmx_dav_module_file_url')) {
			$url = (string) \CLOUDMEISTER\CMX\Buero\cmx_dav_module_file_url('website', $path);
		}
		echo '<div class="cmx-website-field cmx-website-media"><span>' . \esc_html($label) . '</span><div class="cmx-website-media-row">';
		echo '<input type="hidden" name="' . \esc_attr($name) . '" value="' . \esc_attr($path) . '">';
		echo '<input type="file" name="' . \esc_attr($field_name) . '" accept=".jpg,.jpeg,.png,.webp,.gif,image/*">';
		if ($url !== '') {
			echo '<a href="' . \esc_url($url) . '" target="_blank" rel="noopener">' . \esc_html(\basename($path)) . '</a>';
			echo '<img class="cmx-website-media-preview" src="' . \esc_url($url) . '" alt="">';
			echo '<label class="cmx-website-remove"><input type="checkbox" name="' . \esc_attr(\preg_replace('/\]$/', '_remove]', $name)) . '" value="1"> ' . \esc_html__('entfernen', 'cmx-misbuero') . '</label>';
		} elseif ($fallback_url !== '') {
			echo '<img class="cmx-website-media-preview" src="' . \esc_url($fallback_url) . '" alt="' . \esc_attr__('Logo aus „Das bin ich“-Kontakt', 'cmx-misbuero') . '" title="' . \esc_attr__('Logo aus „Das bin ich“-Kontakt', 'cmx-misbuero') . '">';
		}
		echo '</div></div>';
	}

	private static function self_logo_url(): string {
		return \function_exists('CLOUDMEISTER\\CMX\\Buero\\cmx_email_self_logo_url')
			? \trim((string) \CLOUDMEISTER\CMX\Buero\cmx_email_self_logo_url())
			: '';
	}

	private static function opening_hour_day_labels(): array {
		return [
			'mon' => __('Montag', 'cmx-misbuero'),
			'tue' => __('Dienstag', 'cmx-misbuero'),
			'wed' => __('Mittwoch', 'cmx-misbuero'),
			'thu' => __('Donnerstag', 'cmx-misbuero'),
			'fri' => __('Freitag', 'cmx-misbuero'),
			'sat' => __('Samstag', 'cmx-misbuero'),
			'sun' => __('Sonntag', 'cmx-misbuero'),
		];
	}

	private static function admin_css(): string {
		return '.cmx-website-admin{max-width:1180px}.cmx-website-admin-actions{display:flex;gap:12px;align-items:center;margin:14px 0 18px}.cmx-website-admin-box{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px 18px 8px;margin:0 0 18px}.cmx-website-admin-box h3{margin:0 0 14px;font-size:16px}.cmx-website-field{display:inline-flex;vertical-align:top;flex-direction:column;gap:6px;margin:0 18px 14px 0;min-width:260px}.cmx-website-field>span{font-weight:600}.cmx-website-field input[type=text],.cmx-website-field input[type=email],.cmx-website-field input[type=number],.cmx-website-field input[type=time],.cmx-website-field select,.cmx-website-field textarea{width:100%;max-width:520px}.cmx-website-field-full{display:flex;max-width:760px}.cmx-website-field-full textarea{max-width:760px}.cmx-website-checkbox{display:flex;flex-direction:row;align-items:center;min-width:100%;gap:8px}.cmx-website-repeat{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.cmx-website-repeat-four{grid-template-columns:repeat(4,minmax(0,1fr))}.cmx-website-repeat-item{border:1px solid #e5e7eb;border-radius:8px;padding:12px;background:#f8fafc}.cmx-website-repeat-item h4{margin:0 0 10px}.cmx-website-repeat-item .cmx-website-field{display:flex;min-width:0;margin-right:0}.cmx-website-media-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.cmx-website-media-preview{width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #dcdcde}.cmx-website-remove{display:inline-flex;align-items:center;gap:4px;color:#b32d2e}.cmx-website-inline-pair{border-top:1px solid #e5e7eb;padding-top:12px;margin-top:8px}.cmx-website-hours{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:8px 0 8px}.cmx-website-hours-day{display:grid;grid-template-columns:112px minmax(0,1fr);gap:12px;align-items:center;border:1px solid #e5e7eb;border-radius:8px;background:#f8fafc;padding:12px}.cmx-website-hours-day-head{align-self:center}.cmx-website-hours-slots{display:grid;gap:8px}.cmx-website-hours-slot{display:grid;grid-template-columns:116px 116px 40px 40px;align-items:end;gap:10px}.cmx-website-hours-slot label{display:flex;flex-direction:column;gap:4px;font-weight:600;color:#3c434a}.cmx-website-hours-slot input[type=time]{width:116px}.cmx-website-hours-slot .button.cmx-hours-icon-button{width:40px;height:40px;min-height:40px;display:inline-flex;align-items:center;justify-content:center;margin:0;padding:0;line-height:1;text-align:center}.cmx-website-hours-slot .cmx-hours-icon-button .dashicons{width:20px;height:20px;font-size:20px;line-height:20px}.cmx-website-hours-slot .button-link-delete{border:1px solid #b32d2e;border-radius:6px;text-decoration:none;background:#fff;color:#b32d2e}.cmx-website-hours-slot .button-secondary.cmx-hours-icon-button{border-radius:6px}@media(max-width:1200px){.cmx-website-hours{grid-template-columns:1fr}}@media(max-width:1100px){.cmx-website-repeat,.cmx-website-repeat-four{grid-template-columns:1fr 1fr}}@media(max-width:782px){.cmx-website-repeat,.cmx-website-repeat-four{grid-template-columns:1fr}.cmx-website-field{display:flex;min-width:100%;margin-right:0}.cmx-website-hours-day{grid-template-columns:1fr;align-items:start}.cmx-website-hours-day-head{align-self:start}.cmx-website-hours-slot{grid-template-columns:1fr 1fr 40px 40px}.cmx-website-hours-slot label,.cmx-website-hours-slot input[type=time]{width:100%}}';
	}

	private static function admin_js(): string {
		return <<<'JS'
(function(){
	function slotHtml(prefix, index) {
		var base = prefix + '[' + index + ']';
		return '<div class="cmx-website-hours-slot" data-cmx-hours-slot>'
			+ '<label><span>Von</span><input type="time" name="' + base + '[start]" value=""></label>'
			+ '<label><span>Bis</span><input type="time" name="' + base + '[end]" value=""></label>'
			+ '<button type="button" class="button button-link-delete cmx-hours-icon-button" data-cmx-hours-remove title="Zeit entfernen" aria-label="Zeit entfernen"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>'
			+ '<button type="button" class="button button-secondary cmx-hours-icon-button" data-cmx-hours-add title="Zeit hinzufügen" aria-label="Zeit hinzufügen"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span></button>'
			+ '</div>';
	}
	function minutes(value) {
		var match = /^(\d{2}):(\d{2})$/.exec(value || '');
		return match ? (parseInt(match[1], 10) * 60 + parseInt(match[2], 10)) : null;
	}
	function formatMinutes(value) {
		value = Math.max(0, Math.min(1439, value));
		var h = Math.floor(value / 60);
		var m = value % 60;
		return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
	}
	function syncSlot(slot) {
		var start = slot ? slot.querySelector('input[name$="[start]"]') : null;
		var end = slot ? slot.querySelector('input[name$="[end]"]') : null;
		var startMinutes = start ? minutes(start.value) : null;
		if (!end) return;
		if (startMinutes === null) {
			end.removeAttribute('min');
			return;
		}
		var minEnd = Math.min(1439, startMinutes + 1);
		end.min = formatMinutes(minEnd);
		var endMinutes = minutes(end.value);
		if (endMinutes === null || endMinutes <= startMinutes) {
			end.value = formatMinutes(Math.min(1439, startMinutes + 60));
		}
	}
	function syncAll() {
		document.querySelectorAll('[data-cmx-hours-slot]').forEach(syncSlot);
	}
	document.addEventListener('click', function(event){
		var add = event.target.closest('[data-cmx-hours-add]');
		if (add) {
			var day = add.closest('.cmx-website-hours-day');
			var slots = day ? day.querySelector('[data-cmx-hours-slots]') : null;
			if (!day || !slots) return;
			var index = parseInt(day.getAttribute('data-next-index') || '0', 10);
			day.setAttribute('data-next-index', String(index + 1));
			slots.insertAdjacentHTML('beforeend', slotHtml(day.getAttribute('data-name-prefix') || '', index));
			var lastInput = slots.querySelector('.cmx-website-hours-slot:last-child input');
			var lastSlot = slots.querySelector('.cmx-website-hours-slot:last-child');
			if (lastSlot) syncSlot(lastSlot);
			if (lastInput) lastInput.focus();
			return;
		}
		var remove = event.target.closest('[data-cmx-hours-remove]');
		if (remove) {
			var slot = remove.closest('[data-cmx-hours-slot]');
			var slotsWrap = slot ? slot.parentElement : null;
			if (!slot || !slotsWrap) return;
			if (slotsWrap.querySelectorAll('[data-cmx-hours-slot]').length > 1) {
				slot.remove();
				return;
			}
			slot.querySelectorAll('input').forEach(function(input){ input.value = ''; });
			syncSlot(slot);
		}
	});
	document.addEventListener('input', function(event){
		if (event.target && event.target.matches('[data-cmx-hours-slot] input[type="time"]')) {
			syncSlot(event.target.closest('[data-cmx-hours-slot]'));
		}
	});
	document.addEventListener('change', function(event){
		if (event.target && event.target.matches('[data-cmx-hours-slot] input[type="time"]')) {
			syncSlot(event.target.closest('[data-cmx-hours-slot]'));
		}
	});
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', syncAll);
	} else {
		syncAll();
	}
})();
JS;
	}
}
