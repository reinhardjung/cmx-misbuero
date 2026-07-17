<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

function cmx_admin_color_scheme_slug(): string {
	return 'misbuero_admin_red';
}

function cmx_admin_global_color(string $key, string $fallback): string {
	$file = __DIR__ . '/globales.ini';
	if (!\is_readable($file)) {
		return $fallback;
	}

	$ini = \parse_ini_file($file, true, \INI_SCANNER_RAW);
	if (!\is_array($ini)) {
		return $fallback;
	}

	$value = isset($ini['Farben'][$key]) ? \trim((string) $ini['Farben'][$key]) : '';
	if ($value === '' && $key === 'gruen' && isset($ini['Farben']['grün'])) {
		$value = \trim((string) $ini['Farben']['grün']);
	}
	$value = \ltrim($value, '#');
	if (!\preg_match('/^[a-fA-F0-9]{6}$/', $value)) {
		return $fallback;
	}

	return '#' . \strtoupper($value);
}

function cmx_adminbar_dark_mode_icon_html(): string {
	$icon_path = \dirname(__DIR__) . '/assets/icons/sun-moon.svg';
	if (!\is_readable($icon_path)) {
		return '<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>';
	}

	$svg = (string) \file_get_contents($icon_path);
	$svg = (string) \preg_replace('/<svg\b/', '<svg class="cmx-adminbar-dark-mode-icon" aria-hidden="true" focusable="false"', $svg, 1);
	return $svg !== '' ? $svg : '<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>';
}

add_action('admin_init', __NAMESPACE__ . '\\register_scheme');
function register_scheme(): void {
	$slug = cmx_admin_color_scheme_slug();
	$red = cmx_admin_global_color('rot', '#A42C24');
	wp_admin_css_color($slug,'Mis Büro',false,[$red, $red, '#3D3D3D', '#F7F7F7']);
}

add_filter('get_user_option_admin_color', __NAMESPACE__ . '\\cmx_force_admin_color_scheme', 10, 3);
function cmx_force_admin_color_scheme($color_scheme, string $option, $user): string {
	return cmx_admin_color_scheme_slug();
}

add_action('admin_init', __NAMESPACE__ . '\\cmx_lock_admin_color_scheme');
function cmx_lock_admin_color_scheme(): void {
	\remove_action('admin_color_scheme_picker', 'admin_color_scheme_picker');
}

add_action('user_register', __NAMESPACE__ . '\\cmx_persist_admin_color_scheme');
add_action('personal_options_update', __NAMESPACE__ . '\\cmx_persist_admin_color_scheme');
add_action('edit_user_profile_update', __NAMESPACE__ . '\\cmx_persist_admin_color_scheme');
function cmx_persist_admin_color_scheme(int $user_id): void {
	if ($user_id <= 0) {
		return;
	}

	\update_user_option($user_id, 'admin_color', cmx_admin_color_scheme_slug(), true);
}

add_filter('admin_footer_text', __NAMESPACE__ . '\\cmx_admin_footer_text');
function cmx_admin_footer_text(?string $text): string {
	return 'Managed by <a href="https://misbuero.ch/" target="_blank" rel="noopener noreferrer">Mis Büro</a>';
}

add_filter('update_footer', __NAMESPACE__ . '\\cmx_admin_footer_version', 11);
function cmx_admin_footer_version(?string $text): string {
	$plugin_file = __DIR__ . '/../cmx-misbuero.php';
	if (!is_readable($plugin_file)) {
		return (string) $text;
	}
	$data = get_file_data($plugin_file, ['Version' => 'Version'], 'plugin');
	$version = isset($data['Version']) ? trim((string) $data['Version']) : '';
	if ($version === '') {
		return (string) $text;
	}
	return '<span class="cmx-admin-version" translate="no">Version ' . esc_html($version) . '</span>';
}

add_filter('wp_editor_settings', __NAMESPACE__ . '\\cmx_admin_reduce_cpt_content_editor_settings', 25, 2);
function cmx_admin_reduce_cpt_content_editor_settings(array $settings, string $editor_id): array {
	if (!\is_admin() || !\function_exists('get_current_screen')) {
		return $settings;
	}

	$screen = \get_current_screen();
	if (!$screen || !\in_array((string) ($screen->base ?? ''), ['post', 'post-new'], true)) {
		return $settings;
	}

	$post_type = (string) ($screen->post_type ?? '');
	if ($post_type === '') {
		return $settings;
	}

	$post_type_object = \get_post_type_object($post_type);
	if (!$post_type_object || !empty($post_type_object->_builtin)) {
		return $settings;
	}

	$note_settings = \function_exists(__NAMESPACE__ . '\\cmx_notizen_editor_settings')
		? (array) cmx_notizen_editor_settings()
		: [
			'mediaButtons' => false,
			'quicktags'    => [
				'buttons' => 'strong,em,link,ul,ol,li,code',
			],
			'tinymce'      => [
				'wpautop'   => true,
				'branding'  => false,
				'menubar'   => false,
				'statusbar' => false,
				'resize'    => true,
				'toolbar1'  => 'bold,italic,bullist,numlist,blockquote,link,unlink,undo,redo',
				'toolbar2'  => '',
			],
		];

	$settings['media_buttons'] = (bool) ($note_settings['mediaButtons'] ?? ($note_settings['media_buttons'] ?? false));
	$quicktags = $note_settings['quicktags'] ?? true;
	if ($quicktags === false) {
		$settings['quicktags'] = false;
	} else {
		$quicktags_settings = \is_array($quicktags) ? $quicktags : [];
		if (empty($quicktags_settings['buttons'])) {
			$quicktags_settings['buttons'] = 'strong,em,link,ul,ol,li,code';
		}
		$settings['quicktags'] = $quicktags_settings;
	}

	if (($settings['tinymce'] ?? true) !== false) {
		$current_tinymce = \is_array($settings['tinymce'] ?? null) ? $settings['tinymce'] : [];
		$note_tinymce = \is_array($note_settings['tinymce'] ?? null) ? $note_settings['tinymce'] : [];
		$note_tinymce['toolbar1'] = 'bold,italic,bullist,numlist,blockquote,link,unlink,undo,redo';
		$note_tinymce['toolbar2'] = '';
		$settings['tinymce'] = \array_merge($current_tinymce, $note_tinymce);
	}

	return $settings;
}

add_filter('wp_default_editor', __NAMESPACE__ . '\\cmx_admin_default_cpt_editor_mode', 25);
function cmx_admin_default_cpt_editor_mode(string $default): string {
	if (!\is_admin() || !\function_exists('get_current_screen')) {
		return $default;
	}

	$screen = \get_current_screen();
	if (!$screen || !\in_array((string) ($screen->base ?? ''), ['post', 'post-new'], true)) {
		return $default;
	}

	$post_type = (string) ($screen->post_type ?? '');
	if ($post_type === '') {
		return $default;
	}

	$post_type_object = \get_post_type_object($post_type);
	if (!$post_type_object || !empty($post_type_object->_builtin)) {
		return $default;
	}

	return 'tinymce';
}

add_action('current_screen', __NAMESPACE__ . '\\cmx_admin_register_cpt_help_tabs', 20);
function cmx_admin_register_cpt_help_tabs($screen = null): void {
	if (!\is_admin()) {
		return;
	}

	if (!$screen instanceof \WP_Screen) {
		$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	}
	if (!$screen || !\method_exists($screen, 'add_help_tab')) {
		return;
	}

	$post_type = cmx_admin_help_post_type_from_screen($screen);
	if ($post_type === '') {
		return;
	}

	$post_type_object = \get_post_type_object($post_type);
	if (!$post_type_object || !empty($post_type_object->_builtin)) {
		return;
	}

	$tabs = cmx_admin_help_tabs_for_post_type($post_type, $screen, $post_type_object);
	foreach ($tabs as $tab) {
		$id = isset($tab['id']) ? \sanitize_key((string) $tab['id']) : '';
		$title = isset($tab['title']) ? \trim((string) $tab['title']) : '';
		$content = isset($tab['content']) ? \trim((string) $tab['content']) : '';
		if ($id === '' || $title === '' || $content === '') {
			continue;
		}

		$screen->add_help_tab([
			'id'      => $id,
			'title'   => $title,
			'content' => \wp_kses_post($content),
		]);
	}

	$sidebar = cmx_admin_help_sidebar_for_post_type($post_type, $screen, $post_type_object);
	if ($sidebar !== '' && \method_exists($screen, 'set_help_sidebar')) {
		$screen->set_help_sidebar(\wp_kses_post($sidebar));
	}
}

add_action('current_screen', __NAMESPACE__ . '\\cmx_admin_register_settings_help_tabs', 21);
function cmx_admin_register_settings_help_tabs($screen = null): void {
	if (!\is_admin()) {
		return;
	}

	if (!$screen instanceof \WP_Screen) {
		$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	}
	if (!$screen || !\method_exists($screen, 'add_help_tab')) {
		return;
	}

	$context = cmx_admin_help_settings_context();
	if ($context === []) {
		return;
	}

	$tabs = cmx_admin_help_tabs_for_settings_screen($context, $screen);
	foreach ($tabs as $tab) {
		$id = isset($tab['id']) ? \sanitize_key((string) $tab['id']) : '';
		$title = isset($tab['title']) ? \trim((string) $tab['title']) : '';
		$content = isset($tab['content']) ? \trim((string) $tab['content']) : '';
		if ($id === '' || $title === '' || $content === '') {
			continue;
		}

		$screen->add_help_tab([
			'id'      => $id,
			'title'   => $title,
			'content' => \wp_kses_post($content),
		]);
	}

	$sidebar = cmx_admin_help_sidebar_for_settings_screen($context, $screen);
	if ($sidebar !== '' && \method_exists($screen, 'set_help_sidebar')) {
		$screen->set_help_sidebar(\wp_kses_post($sidebar));
	}
}

function cmx_admin_help_post_type_from_screen(\WP_Screen $screen): string {
	$post_type = (string) ($screen->post_type ?? '');
	if ($post_type !== '') {
		return $post_type;
	}

	$post_type = isset($_GET['post_type']) ? \sanitize_key((string) \wp_unslash($_GET['post_type'])) : '';
	if ($post_type !== '') {
		return $post_type;
	}

	$post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
	if ($post_id > 0) {
		$post_type = (string) \get_post_type($post_id);
	}

	return $post_type;
}

function cmx_admin_help_settings_context(): array {
	$settings_slug = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
		? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
		: 'cmx-einstellungen';
	$page = isset($_GET['page']) ? \sanitize_key((string) \wp_unslash($_GET['page'])) : '';
	if ($page !== $settings_slug) {
		return [];
	}

	$tabs = \function_exists(__NAMESPACE__ . '\\cmx_get_tabs')
		? (array) cmx_get_tabs()
		: [
			'general'     => 'Allgemein',
			'vorgaben'    => 'Vorgaben',
			'banken'      => 'Zahlungen',
			'belege'      => 'Belege',
			'woocommerce' => 'WooCommerce',
			'email'       => 'E-Mails',
			'system'      => 'System',
			'support'     => 'Support',
		];
	if ($tabs === []) {
		return [];
	}

	$tab = isset($_GET['tab']) ? \sanitize_key((string) \wp_unslash($_GET['tab'])) : 'general';
	if (!isset($tabs[$tab])) {
		$tab = (string) \array_key_first($tabs);
	}

	$subtabs = \function_exists(__NAMESPACE__ . '\\cmx_get_subtabs')
		? (array) cmx_get_subtabs($tab)
		: [];
	$sub = isset($_GET['sub']) ? \sanitize_key((string) \wp_unslash($_GET['sub'])) : '';
	if ($subtabs !== []) {
		if ($sub === '' || !isset($subtabs[$sub])) {
			$sub = (string) \array_key_first($subtabs);
		}
	} else {
		$sub = '';
	}

	$tab_label = \trim((string) ($tabs[$tab] ?? $tab));
	$sub_label = $sub !== '' ? \trim((string) ($subtabs[$sub] ?? $sub)) : '';
	$section_label = 'Einstellungen';
	if ($tab_label !== '') {
		$section_label .= ' · ' . $tab_label;
	}
	if ($sub_label !== '') {
		$section_label .= ' · ' . $sub_label;
	}

	return [
		'page'          => $page,
		'tab'           => $tab,
		'sub'           => $sub,
		'tabs'          => $tabs,
		'subtabs'       => $subtabs,
		'tab_label'     => $tab_label,
		'sub_label'     => $sub_label,
		'section_label' => $section_label,
	];
}

function cmx_admin_help_tabs_for_post_type(string $post_type, \WP_Screen $screen, \WP_Post_Type $post_type_object): array {
	$label = \trim((string) ($post_type_object->labels->singular_name ?? $post_type_object->labels->name ?? $post_type));
	$screen_label = cmx_admin_help_screen_label($screen);
	$definitions = cmx_admin_help_tab_definitions();
	$definition = \is_array($definitions[$post_type] ?? null) ? $definitions[$post_type] : [];

	$overview_intro = isset($definition['intro']) && \is_string($definition['intro'])
		? $definition['intro']
		: 'Hier findest du kurze Hinweise zur Arbeit mit diesem Bereich.';

	$overview_items = [];
	if (!empty($definition['overview']) && \is_array($definition['overview'])) {
		$overview_items = \array_values(\array_filter(\array_map('strval', $definition['overview'])));
	}
	if (empty($overview_items)) {
		$overview_items = [
			'Diese Hilfe wird zentral in Dateien unter <code>includes/help/post_types</code> gepflegt.',
			'Du kannst die Inhalte je nach <code>post_type</code> unterschiedlich ausgeben.',
			'Zusätzliche Tabs lassen sich über den Filter <code>cmx_admin_help_tabs</code> ergänzen.',
		];
	}

	$workflow_items = [];
	$workflow_key = (string) ($screen->base ?? '');
	if ($workflow_key === 'edit-tags') {
		$workflow_key = 'term';
	}
	if (!empty($definition[$workflow_key]) && \is_array($definition[$workflow_key])) {
		$workflow_items = \array_values(\array_filter(\array_map('strval', $definition[$workflow_key])));
	} elseif (!empty($definition['workflow']) && \is_array($definition['workflow'])) {
		$workflow_items = \array_values(\array_filter(\array_map('strval', $definition['workflow'])));
	}
	if (empty($workflow_items)) {
		$workflow_items = [
			'Prüfe zuerst Titel, Stammdaten und die zugehörigen Taxonomien.',
			'Speichere Änderungen direkt im aktuellen Datensatz.',
			'Nutze Listenansicht, Filter und Spalten für die schnelle Kontrolle.',
		];
	}

	$tabs = [
			[
				'id'      => 'cmx-help-' . $post_type . '-overview',
				'title'   => 'Mis Büro Hilfe',
				'content' => '<p><strong>' . \esc_html($label) . '</strong> · ' . \esc_html($screen_label) . '</p>'
					. cmx_admin_help_normalize_extra_tab_content_html($overview_intro)
					. cmx_admin_help_html_list($overview_items),
			],
		[
			'id'      => 'cmx-help-' . $post_type . '-workflow',
			'title'   => 'Hinweise',
			'content' => '<p>Hinweise für <strong>' . \esc_html($label) . '</strong> auf dieser Seite:</p>'
				. cmx_admin_help_html_list($workflow_items),
			],
		];

	$tabs = \array_merge($tabs, cmx_admin_help_extra_tabs_from_definition($definition, 'cmx-help-' . $post_type, $workflow_key));

	/**
	 * Zusätzliche Help-Tabs pro CPT ergänzen oder bestehende ersetzen.
	 *
	 * Erwartetes Format:
	 * [
	 *   ['id' => 'cmx-help-kontakte-extra', 'title' => 'XYZ', 'content' => '<p>...</p>'],
	 * ]
	 */
	return (array) \apply_filters('cmx_admin_help_tabs', $tabs, $post_type, $screen, $post_type_object, $definition);
}

function cmx_admin_help_common_sidebar_links_html(): string {
	return '<div class="cmx-admin-help-common-links">'
		. '<p><strong>Weitere Hilfen gibt es</strong></p>'
		. '<ul>'
		. '<li>auf der Homepage <a href="' . \esc_url('https://misbuero.ch/faq/') . '" target="_blank" rel="noopener noreferrer">FAQ</a></li>'
		. '<li>sowie auf <a href="' . \esc_url('https://www.youtube.com/@MisBuero') . '" target="_blank" rel="noopener noreferrer">YouTube</a>.</li>'
		. '<li><br>Oder einfach ein Ticket machen <a href="' . \esc_url(\admin_url('admin.php?page=cmx-einstellungen&tab=support')) . '" target="_blank" rel="noopener noreferrer">Support-Team</a></li>'
		. '</ul>'
		. '</div>';
}

function cmx_admin_help_append_common_sidebar_links(string $sidebar): string {
	if (\strpos($sidebar, 'cmx-admin-help-common-links') !== false) {
		return $sidebar;
	}

	return $sidebar . cmx_admin_help_common_sidebar_links_html();
}

function cmx_admin_help_sidebar_for_post_type(string $post_type, \WP_Screen $screen, \WP_Post_Type $post_type_object): string {
	$definitions = cmx_admin_help_tab_definitions();
	$definition = \is_array($definitions[$post_type] ?? null) ? $definitions[$post_type] : [];
	$label = \trim((string) ($post_type_object->labels->singular_name ?? $post_type_object->labels->name ?? $post_type));

	// $default_sidebar = '<p><strong>' . \esc_html($label) . '</strong></p>' . '<p>Diese Hilfe wird zentral aus <code>includes/help/post_types</code> geladen.</p>';
	$default_sidebar = '';

	$sidebar = isset($definition['sidebar']) && \is_string($definition['sidebar']) && \trim($definition['sidebar']) !== ''
		? $definition['sidebar']
		: $default_sidebar;

	$sidebar = cmx_admin_help_append_common_sidebar_links($sidebar);

	return (string) \apply_filters('cmx_admin_help_sidebar', $sidebar, $post_type, $screen, $post_type_object, $definition);
}

function cmx_admin_help_tabs_for_settings_screen(array $context, \WP_Screen $screen): array {
	$definitions = cmx_admin_help_settings_definitions();
	$tab = (string) ($context['tab'] ?? '');
	$sub = (string) ($context['sub'] ?? '');
	$definition = \is_array($definitions[$tab] ?? null) ? $definitions[$tab] : [];
	if ($sub !== '' && \is_array($definition['subtabs'][$sub] ?? null)) {
		$definition = \array_merge($definition, (array) $definition['subtabs'][$sub]);
	}

	$intro = isset($definition['intro']) && \is_string($definition['intro'])
		? $definition['intro']
		: 'Hier verwaltest du zentrale Einstellungen und Vorgaben von Mis Büro.';
	$overview_items = !empty($definition['overview']) && \is_array($definition['overview'])
			? \array_values(\array_filter(\array_map('strval', $definition['overview'])))
			: [
				'Änderungen in den Einstellungen wirken oft bereichsübergreifend.',
				'Viele Felder definieren Standards für neue Datensätze oder Ausgaben.',
				'Diese Hilfe wird zentral in Dateien unter <code>includes/help/settings</code> gepflegt.',
			];
	$workflow_items = !empty($definition['workflow']) && \is_array($definition['workflow'])
		? \array_values(\array_filter(\array_map('strval', $definition['workflow'])))
		: [
			'Änderungen speichern und die betroffene Oberfläche danach kurz neu laden.',
			'Bereichsspezifische Einstellungen immer direkt im passenden Tab prüfen.',
			'Nach Mail- oder PDF-Anpassungen einen kurzen Praxistest machen.',
		];

	$section_label = \trim((string) ($context['section_label'] ?? 'Einstellungen'));
	$tabs = [
			[
				'id'      => 'cmx-help-settings-overview',
				'title'   => 'Mis Büro Hilfe',
				'content' => '<p><strong>' . \esc_html($section_label) . '</strong></p>'
					. cmx_admin_help_normalize_extra_tab_content_html($intro)
					. cmx_admin_help_html_list($overview_items),
			],
		[
			'id'      => 'cmx-help-settings-workflow',
			'title'   => 'Hinweise',
			'content' => '<p>Hinweise für <strong>' . \esc_html($section_label) . '</strong>:</p>'
				. cmx_admin_help_html_list($workflow_items),
			],
		];

	$settings_tab_slug = \sanitize_key((string) ($context['tab'] ?? 'settings'));
	$settings_sub_slug = \sanitize_key((string) ($context['sub'] ?? ''));
	$tab_prefix = 'cmx-help-settings-' . ($settings_sub_slug !== '' ? $settings_tab_slug . '-' . $settings_sub_slug : $settings_tab_slug);
	$tabs = \array_merge($tabs, cmx_admin_help_extra_tabs_from_definition($definition, $tab_prefix));

	return (array) \apply_filters('cmx_admin_help_tabs_settings', $tabs, $context, $screen, $definition);
}

function cmx_admin_help_sidebar_for_settings_screen(array $context, \WP_Screen $screen): string {
	$section_label = \trim((string) ($context['sub_label'] !== '' ? $context['sub_label'] : ($context['tab_label'] ?? 'Einstellungen')));
	if ($section_label === '') {
		$section_label = 'Einstellungen';
	}

	// $sidebar = '<p><strong>' . \esc_html($section_label) . '</strong></p>' . '<p>Diese Hilfe wird zentral aus <code>includes/help/settings</code> geladen.</p>';
	$sidebar = '';

	$sidebar = cmx_admin_help_append_common_sidebar_links($sidebar);

	return (string) \apply_filters('cmx_admin_help_sidebar_settings', $sidebar, $context, $screen);
}

function cmx_admin_help_screen_label(\WP_Screen $screen): string {
	$base = (string) ($screen->base ?? '');
	if ($base === 'edit-tags') {
		$base = 'term';
	}

	return match ($base) {
		'post'     => 'Bearbeiten',
		'post-new' => 'Neu anlegen',
		'edit'     => 'Listenansicht',
		'term'     => 'Taxonomien',
		default    => 'Verwaltung',
	};
}

function cmx_admin_help_html_list(array $items): string {
	$items = \array_values(\array_filter(\array_map(static function ($item): string {
		return \trim((string) $item);
	}, $items)));
	if (empty($items)) {
		return '';
	}

	$html = '';
	$list_open = false;
	foreach ($items as $item) {
		if (cmx_admin_help_is_spacer_item($item)) {
			if ($list_open) {
				$html .= '</ul>';
				$list_open = false;
			}
			$html .= '<div class="cmx-admin-help-spacer" aria-hidden="true"></div>';
			continue;
		}

		if (!$list_open) {
			$html .= '<ul>';
			$list_open = true;
		}

		$html .= '<li>' . \wp_kses_post($item) . '</li>';
	}
	if ($list_open) {
		$html .= '</ul>';
	}

	return $html;
}

function cmx_admin_help_is_spacer_item(string $item): bool {
	$normalized = \html_entity_decode($item, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
	$normalized = \str_replace("\u{00A0}", '', $normalized);

	return \trim($normalized) === '';
}

function cmx_admin_help_extra_tabs_from_definition(array $definition, string $id_prefix, string $screen_key = ''): array {
	$configured_tabs = [];
	if (\is_array($definition['tabs'] ?? null)) {
		$configured_tabs = \array_merge($configured_tabs, (array) $definition['tabs']);
	}

	$screen_specific_tabs = cmx_admin_help_screen_specific_tabs_from_definition($definition, $screen_key);
	if ($screen_specific_tabs !== []) {
		$configured_tabs = \array_merge($configured_tabs, $screen_specific_tabs);
	}

	if ($configured_tabs === []) {
		return [];
	}

	$tabs = [];
	$index = 0;
	foreach ($configured_tabs as $tab_key => $tab_definition) {
		$index++;
		$tab = cmx_admin_help_normalize_extra_tab_definition($tab_definition, $tab_key, $id_prefix, $index);
		if ($tab === null) {
			continue;
		}

		$tabs[] = $tab;
	}

	return $tabs;
}

function cmx_admin_help_screen_specific_tabs_from_definition(array $definition, string $screen_key): array {
	$screen_key = \trim($screen_key);
	if ($screen_key === '') {
		return [];
	}

	$configured_tabs = [];
	$normalized_screen_key = \str_replace('-', '_', \sanitize_key($screen_key));

	$tabs_by_screen = $definition['tabs_by_screen'] ?? null;
	if (\is_array($tabs_by_screen)) {
		if (\is_array($tabs_by_screen[$screen_key] ?? null)) {
			$configured_tabs = \array_merge($configured_tabs, (array) $tabs_by_screen[$screen_key]);
		}
		if ($normalized_screen_key !== $screen_key && \is_array($tabs_by_screen[$normalized_screen_key] ?? null)) {
			$configured_tabs = \array_merge($configured_tabs, (array) $tabs_by_screen[$normalized_screen_key]);
		}
	}

	$legacy_key = 'tabs_' . $normalized_screen_key;
	if (\is_array($definition[$legacy_key] ?? null)) {
		$configured_tabs = \array_merge($configured_tabs, (array) $definition[$legacy_key]);
	}

	return $configured_tabs;
}

function cmx_admin_help_normalize_extra_tab_definition(mixed $tab_definition, mixed $tab_key, string $id_prefix, int $index): ?array {
	$key_title = \is_string($tab_key) ? \trim($tab_key) : '';
	$title = '';
	$id = '';
	$intro = '';
	$content = '';
	$items = [];

	if (\is_string($tab_definition)) {
		$title = $key_title;
		$content = '<p>' . \esc_html(\trim($tab_definition)) . '</p>';
	} elseif (\is_array($tab_definition)) {
		$title = \trim((string) ($tab_definition['title'] ?? $key_title));
		$id = \sanitize_key((string) ($tab_definition['id'] ?? $key_title));
		$intro = \trim((string) ($tab_definition['intro'] ?? ''));
		$content = isset($tab_definition['content']) && \is_string($tab_definition['content'])
			? \trim($tab_definition['content'])
			: '';

		if (isset($tab_definition['items']) && \is_array($tab_definition['items'])) {
			$items = \array_values(\array_filter(\array_map('strval', $tab_definition['items'])));
		} elseif (cmx_admin_help_is_list_array($tab_definition)) {
			$items = \array_values(\array_filter(\array_map('strval', $tab_definition)));
		}
	}

	if ($title === '') {
		return null;
	}

	if ($id === '') {
		$id = \sanitize_key($title);
	}

	if ($id === '') {
		$id = 'tab-' . $index;
	}

		$parts = [];
		if ($intro !== '') {
			$parts[] = cmx_admin_help_normalize_extra_tab_content_html($intro);
		}
		if ($content !== '') {
			$parts[] = cmx_admin_help_normalize_extra_tab_content_html($content);
		}
		if ($items !== []) {
			$parts[] = cmx_admin_help_html_list($items);
		}

	$tab_content = \trim(\implode('', $parts));
	if ($tab_content === '') {
		return null;
	}

	return [
		'id' => $id_prefix . '-' . \sanitize_key($id),
		'title' => $title,
		'content' => $tab_content,
	];
}

function cmx_admin_help_normalize_extra_tab_content_html(string $content): string {
	$content = \trim($content);
	if ($content === '') {
		return '';
	}

	if (\preg_match('/^\s*<(p|ul|ol|div|table|blockquote|pre|h[1-6])\b/i', $content) === 1) {
		return $content;
	}

	return '<p>' . $content . '</p>';
}

function cmx_admin_help_is_list_array(array $array): bool {
	$expected_key = 0;
	foreach (\array_keys($array) as $key) {
		if ($key !== $expected_key) {
			return false;
		}
		$expected_key++;
	}

	return true;
}

function cmx_admin_help_tab_definitions(): array {
	$definitions = [];
	foreach (cmx_admin_help_post_type_definition_file_map() as $post_type => $path) {
		$loaded = cmx_admin_help_load_definition_file($path);
		if ($loaded === []) {
			continue;
		}

		$definitions[$post_type] = $loaded;
	}

	return $definitions;
}

function cmx_admin_help_post_type_definition_file_map(): array {
	$dir = cmx_admin_help_post_type_definitions_dir();
	return [
		'kontakte' => $dir . '/kontakte.php',
		'artikel' => $dir . '/artikel.php',
		'projekte' => $dir . '/projekte.php',
		'belege' => $dir . '/belege.php',
		'dokumente' => $dir . '/dokumente.php',
		'emails' => $dir . '/emails.php',
		'scanner' => $dir . '/scanner.php',
	];
}

function cmx_admin_help_post_type_definitions_dir(): string {
	return __DIR__ . '/help/post_types';
}

function cmx_admin_help_settings_definitions(): array {
	$definitions = [];
	foreach (cmx_admin_help_settings_definition_file_map() as $target => $path) {
		$loaded = cmx_admin_help_load_definition_file($path);
		if ($loaded === []) {
			continue;
		}

		if (\str_contains($target, '.')) {
			[$tab, $subtab] = \explode('.', $target, 2);
			if ($tab === '' || $subtab === '') {
				continue;
			}
			if (!isset($definitions[$tab]) || !\is_array($definitions[$tab])) {
				$definitions[$tab] = [];
			}
			if (!isset($definitions[$tab]['subtabs']) || !\is_array($definitions[$tab]['subtabs'])) {
				$definitions[$tab]['subtabs'] = [];
			}
			$definitions[$tab]['subtabs'][$subtab] = $loaded;
			continue;
		}

		$definitions[$target] = $loaded;
	}

	return $definitions;
}

function cmx_admin_help_settings_definition_file_map(): array {
	$dir = cmx_admin_help_settings_definitions_dir();
	return [
		'general' => $dir . '/general.php',
		'vorgaben' => $dir . '/vorgaben.php',
		'vorgaben.email' => $dir . '/vorgaben.email.php',
		'banken' => $dir . '/banken.php',
		'belege' => $dir . '/belege.php',
		'woocommerce' => $dir . '/woocommerce.php',
		'email' => $dir . '/email.php',
		'email.belege' => $dir . '/email.belege.php',
		'email.clients' => $dir . '/email.clients.php',
		'system' => $dir . '/system.php',
		'support' => $dir . '/support.php',
	];
}

function cmx_admin_help_settings_definitions_dir(): string {
	return __DIR__ . '/help/settings';
}

function cmx_admin_help_load_definition_file(string $path): array {
	if ($path === '' || !\is_readable($path)) {
		return [];
	}

	$data = require $path;
	return \is_array($data) ? $data : [];
}

add_action('admin_head', __NAMESPACE__ . '\\cmx_admin_footer_no_tel');
function cmx_admin_footer_no_tel(): void {
	echo '<meta name="format-detection" content="telephone=no">' . "\n";
	echo '<style>
	a[x-apple-data-detectors],
	a[href^="tel"],
	a[href^="tel:"] {
		color: inherit !important;
		text-decoration: none !important;
		pointer-events: none !important;
		cursor: default !important;
	}
	</style>' . "\n";
}

add_action('admin_footer', __NAMESPACE__ . '\\cmx_admin_unlink_tel_numbers');
function cmx_admin_unlink_tel_numbers(): void {
	if (!function_exists('get_current_screen')) {
		return;
	}
	$screen = get_current_screen();
	if (!$screen || $screen->id !== 'plugins') {
		return;
	}
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('#the-list a[href^="tel:"], #the-list a[href^="tel"]').forEach(function(link) {
			var span = document.createElement('span');
			span.textContent = link.textContent || '';
			span.className = link.className;
			link.replaceWith(span);
		});
	});
	</script>
	<?php
}

add_action('admin_footer', __NAMESPACE__ . '\\cmx_admin_label_placeholder_fill');
function cmx_admin_label_placeholder_fill(): void {
	?>
	<script>
	(function(){
		if (window.__cmxLabelPlaceholderFillBound) return;
		window.__cmxLabelPlaceholderFillBound = true;

		const blockedInputTypes = new Set([
			'checkbox','radio','button','submit','reset','file','hidden','image'
		]);

		function findControlForLabel(label){
			if (!label) return null;
			if (label.control) return label.control;

			const forId = label.getAttribute('for');
			if (forId) {
				const byId = document.getElementById(forId);
				if (byId) return byId;
			}
			return label.querySelector('input, textarea');
		}

		function canFill(el){
			if (!el || !el.tagName) return false;
			const tag = el.tagName.toLowerCase();
			if (tag !== 'input' && tag !== 'textarea') return false;
			if (el.disabled || el.readOnly) return false;

			if (tag === 'input') {
				const type = (el.getAttribute('type') || 'text').toLowerCase();
				if (blockedInputTypes.has(type)) return false;
			}

			const placeholder = (el.getAttribute('placeholder') || '').trim();
			if (!placeholder) return false;

			return (el.value || '').trim() === '';
		}

		function fillControl(el){
			if (!canFill(el)) return;
			const placeholder = (el.getAttribute('placeholder') || '').trim();
			if (!placeholder) return;

			el.value = placeholder;
			el.dispatchEvent(new Event('input', { bubbles: true }));
			el.dispatchEvent(new Event('change', { bubbles: true }));
		}

		function findControlFromHeaderCell(th){
			if (!th || !th.closest) return null;
			const tr = th.closest('tr');
			if (!tr) return null;
			const td = tr.querySelector('td');
			if (!td) return null;
			return td.querySelector('input, textarea');
		}

		function fillFromLabel(label){
			if (!label) return;
			const control = findControlForLabel(label);
			fillControl(control);
		}

		document.addEventListener('click', function(e){
			const rawTarget = e.target;
			const target = (rawTarget && rawTarget.nodeType === 3) ? rawTarget.parentElement : rawTarget;
			if (!target || !target.closest) return;

			if (target.closest('input, textarea, select, button, a')) return;

			let label = target.closest('label');
			if (!label) {
				const th = target.closest('th');
				if (th) label = th.querySelector('label[for]');
			}
			if (label) {
				fillFromLabel(label);
				return;
			}

			const th = target.closest('th');
			if (th) {
				fillControl(findControlFromHeaderCell(th));
			}
		}, true);
	})();
	</script>
	<?php
}

add_action('admin_head', __NAMESPACE__ . '\\cmx_admin_placeholder_click_cursor');
function cmx_admin_placeholder_click_cursor(): void {
	?>
	<style id="cmx-placeholder-click-cursor">
		form .form-table th label[for],
		form .form-table th {
			cursor: pointer;
		}
	</style>
	<?php
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_taxonomy_admin_current_taxonomy')) {
	function cmx_taxonomy_admin_current_taxonomy(): string {
		$taxonomy = isset($_REQUEST['taxonomy']) ? \sanitize_key((string) \wp_unslash($_REQUEST['taxonomy'])) : '';
		return ($taxonomy !== '' && \taxonomy_exists($taxonomy)) ? $taxonomy : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_taxonomy_admin_term_screen_active')) {
	function cmx_taxonomy_admin_term_screen_active(?string $taxonomy = null): bool {
		if (!\is_admin()) {
			return false;
		}

		$pagenow = (string) ($GLOBALS['pagenow'] ?? '');
		if (!\in_array($pagenow, ['edit-tags.php', 'term.php'], true)) {
			return false;
		}

		$current_taxonomy = cmx_taxonomy_admin_current_taxonomy();
		if ($current_taxonomy === '') {
			return false;
		}

		return $taxonomy === null ? true : $current_taxonomy === \sanitize_key($taxonomy);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_taxonomy_admin_autoslug_request_active')) {
	function cmx_taxonomy_admin_autoslug_request_active(?string $taxonomy = null): bool {
		if (!cmx_taxonomy_admin_term_screen_active($taxonomy)) {
			return false;
		}

		$name = isset($_POST['name']) ? \trim((string) \wp_unslash($_POST['name'])) : '';
		return $name !== '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_taxonomy_admin_unique_slug_for_name')) {
	function cmx_taxonomy_admin_unique_slug_for_name(string $name, string $taxonomy, int $term_id = 0, array $args = []): string {
		$name = \trim($name);
		$taxonomy = \sanitize_key($taxonomy);
		if ($name === '' || $taxonomy === '' || !\taxonomy_exists($taxonomy)) {
			return '';
		}

		$slug = \sanitize_title($name);
		if ($slug === '') {
			return '';
		}

		return \wp_unique_term_slug($slug, (object) [
			'taxonomy' => $taxonomy,
			'parent'   => (int) ($args['parent'] ?? 0),
			'term_id'  => $term_id,
		]);
	}
}

add_action('admin_head', function (): void {
	if (!cmx_taxonomy_admin_term_screen_active()) {
		return;
	}
	?>
	<style id="cmx-taxonomy-ui-cleanup">
		.term-slug-wrap,
		.term-parent-wrap {
			display: none !important;
		}
	</style>
	<?php
});

add_action('admin_footer', function (): void {
	if (!cmx_taxonomy_admin_term_screen_active()) {
		return;
	}
	?>
	<script>
	(function () {
		function hideQuickEditSlugField(context) {
			var root = context && context.querySelector ? context : document;
			root.querySelectorAll('.inline-edit-row input[name="slug"]').forEach(function (input) {
				var label = input.closest('label');
				if (label) {
					label.style.display = 'none';
				}
			});
		}

		document.addEventListener('DOMContentLoaded', function () {
			hideQuickEditSlugField(document);
			var observer = new MutationObserver(function (mutations) {
				mutations.forEach(function (mutation) {
					mutation.addedNodes.forEach(function (node) {
						if (node && node.nodeType === 1) {
							hideQuickEditSlugField(node);
						}
					});
				});
			});
			observer.observe(document.body, { childList: true, subtree: true });
		});
	})();
	</script>
	<?php
});

add_filter('wp_update_term_data', function (array $data, int $term_id, string $taxonomy, array $args): array {
	if (!cmx_taxonomy_admin_autoslug_request_active($taxonomy)) {
		return $data;
	}

	$name = \trim((string) ($data['name'] ?? ''));
	if ($name === '') {
		return $data;
	}

	$new_slug = cmx_taxonomy_admin_unique_slug_for_name($name, $taxonomy, $term_id, $args);
	if ($new_slug !== '') {
		$data['slug'] = $new_slug;
	}

	return $data;
}, 10, 4);

add_action('admin_init', function (): void {
	foreach (\get_taxonomies([], 'names') as $taxonomy) {
		$taxonomy = (string) $taxonomy;
		if ($taxonomy === '') {
			continue;
		}

		\add_filter('manage_edit-' . $taxonomy . '_columns', static function (array $columns): array {
			unset($columns['slug']);
			return $columns;
		});
	}
});


add_action('admin_head', __NAMESPACE__ . '\\inject_styles');
function inject_styles(): void {
	$user_id = get_current_user_id();
	if (!$user_id) return;
	if (get_user_option('admin_color', $user_id) !== cmx_admin_color_scheme_slug()) return;
	$adminbar_red = cmx_admin_global_color('rot', '#A42C24');
	$admin_hover_green = cmx_admin_global_color('gruen', '#a6c73f');
	?>
	<style id="misbuero-admin-skin">
	body.admin-color-misbuero_admin_red {
		--mb-primary: #A42C24;   /* Rot */
		--mb-primary-dark: #A42C24;
		--mb-adminbar-bg: <?php echo \esc_html($adminbar_red); ?>;
		--mb-admin-menu-bg: #1f0c3f;
		--mb-admin-menu-hover-bg: <?php echo \esc_html($admin_hover_green); ?>;
		--mb-yellow: #FFEB3B;    /* Gelb für Schrift */
		--mb-bg-light: #F7F7F7;
		--mb-border: #E0E0E0;
		--mb-text: #3D3D3D;
		--mb-muted: #BFBFBF;
		--mb-success: #4BB572;
		--mb-error: #e53836;

		--wp-admin-theme-color: var(--mb-primary);
		--wp-admin-theme-color-darker-10: var(--mb-primary-dark);
		--wp-admin-theme-color-darker-20: #7f1f1a;
		--wp-admin-theme-color-text: #ffffff;
		--wp-admin-border-color: var(--mb-border);
	}


	/* Adminbar bleibt Rot/Weiss */
	#wpadminbar { background: var(--mb-adminbar-bg); }
	#wpadminbar .ab-item,
	#wpadminbar a.ab-item { color:#fff; }
	#wpadminbar .ab-item:hover,
	#wpadminbar .ab-item:focus { background: var(--mb-primary-dark); color:#fff; }

	#adminmenuback,
	#adminmenuwrap,
	#adminmenu {
		background: var(--mb-admin-menu-bg);
	}

	#adminmenu .wp-submenu,
	#adminmenu .wp-has-current-submenu .wp-submenu,
	#adminmenu .wp-has-current-submenu.opensub .wp-submenu,
	#adminmenu .wp-has-current-submenu.opensub .wp-submenu.sub-open,
	#adminmenu .wp-has-current-submenu.opensub .wp-submenu-wrap,
	#adminmenu .wp-submenu-wrap {
		background: var(--mb-admin-menu-bg);
	}

	#adminmenu a,
	#adminmenu .wp-submenu a,
	#adminmenu .wp-menu-name,
	#adminmenu div.wp-menu-image:before {
		color: #fff;
	}

	#adminmenu div.wp-menu-image {
		transform: translateX(4px);
	}

	#adminmenu .wp-menu-name {
		padding-left: 41px !important;
	}

	#adminmenu li.current a.menu-top,
	#adminmenu li.wp-has-current-submenu > a.wp-has-current-submenu,
	#adminmenu li.current a.menu-top .wp-menu-name,
	#adminmenu li.wp-has-current-submenu > a.wp-has-current-submenu .wp-menu-name,
	#adminmenu li.current a.menu-top div.wp-menu-image:before,
	#adminmenu li.wp-has-current-submenu > a.wp-has-current-submenu div.wp-menu-image:before {
		color: #fff;
	}

	#adminmenu li.menu-top:hover,
	#adminmenu li.opensub > a.menu-top,
	#adminmenu li > a.menu-top:focus,
	#adminmenu .wp-submenu a:hover,
	#adminmenu .wp-submenu a:focus {
		background: var(--mb-admin-menu-hover-bg);
		color: #fff;
	}

	#adminmenu li.menu-top:hover div.wp-menu-image:before,
	#adminmenu li.opensub > a.menu-top div.wp-menu-image:before,
	#adminmenu li > a.menu-top:focus div.wp-menu-image:before {
		color: #fff;
	}


	/* Restliche Bereiche (Buttons, Content etc.) unverändert */
	a { color: var(--mb-primary); }
	a:hover { color: var(--mb-primary-dark); }
	.wp-core-ui .button-primary {
		background: var(--mb-primary);
		border-color: var(--mb-primary-dark);
		color: #fff;
	}

	.wp-core-ui .button-primary:hover,
	.wp-core-ui .button-primary:focus {
		background: var(--mb-primary-dark);
		border-color: var(--mb-primary-dark);
	}

	.wp-core-ui select:not([multiple]),
	.wp-core-ui .postbox .inside select:not([multiple]),
	.wp-core-ui .form-table select:not([multiple]) {
		min-height: 36px;
		line-height: 34px;
		padding-top: 0;
		padding-bottom: 0;
		box-sizing: border-box;
	}

	/* Papierkorb-Icon statt Text im Delete-Button */
	body.post-php #delete-action .submitdelete {
		position: relative;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 36px;
		height: 36px;
		text-indent: -9999px;
		padding: 0;
		border: none;
		background: transparent;
		box-shadow: none;
	}
	body.post-php #delete-action .submitdelete::before {
		content: "\f182";
		font-family: "dashicons";
		font-size: 18px;
		color: #d63638; /* klassisches WP-Rot für Papierkorb */
		text-indent: 0;
		display: inline-block;
		vertical-align: middle;
	}
	body.post-php #delete-action .submitdelete:hover::before,
	body.post-php #delete-action .submitdelete:focus::before {
		color: #b42527;
	}


	.notice-success { border-left: 4px solid var(--mb-success); }
	.notice-error { border-left: 4px solid var(--mb-error); }
	</style>
	<?php if (!is_network_admin() && isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'post.php') : ?>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('#delete-action .submitdelete').forEach(function(el){
			var text = (el.textContent || '').trim();
			if (text && !el.getAttribute('title')) {
				el.setAttribute('title', text);
			}
		});
	});
	</script>
	<?php endif; ?>
	<?php
}

// Globale Fallback-Styles: Papierkorb-Icon statt Text im Edit-Modus für alle CPTs.
add_action('admin_head', function () {
	if (is_network_admin()) return;
	?>
	<style id="cmx-trash-icon-global">
	/* Classic + Block Editor: nur im Edit-Modus fuer alle CPTs */
	body.post-php .submitdelete,
	body.post-php .editor-post-trash .components-button {
		position: relative;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 36px;
		height: 36px;
		text-indent: -9999px;
		padding: 0;
		border: none;
		background: transparent;
		box-shadow: none;
		color: transparent !important;
	}
	body.post-php .submitdelete::before,
	body.post-php .editor-post-trash .components-button::before {
		content: "\f182";
		font-family: "dashicons";
		font-size: 18px;
		color: #d63638;
		text-indent: 0;
		display: inline-block;
		vertical-align: middle;
	}
	body.post-php .submitdelete:hover::before,
	body.post-php .submitdelete:focus::before,
	body.post-php .editor-post-trash .components-button:hover::before,
	body.post-php .editor-post-trash .components-button:focus::before {
		color: #b42527;
	}

	/* Duplizieren-Icon im Custom-Aktions-Block */
	body.post-php .cmx-dup-link {
		position: relative;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 36px;
		height: 36px;
		text-indent: 0;
		padding: 0;
		border: none;
		background: transparent;
		box-shadow: none;
		color: #d63638;
	}
	body.post-php .cmx-dup-link::before {
		content: "\f105"; /* dashicons-controls-repeat */
		font-size: 18px;
		color: #d63638; /* Rot passend zum Papierkorb */
	}
	body.post-php .cmx-dup-link:hover::before,
	body.post-php .cmx-dup-link:focus::before {
		color: #b42527;
	}
	body.post-php .cmx-kontakt-new-beleg-link,
	body.post-php .cmx-artikel-new-beleg-link {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 20px;
		height: 20px;
		color: #d63638;
	}
	body.post-php .cmx-kontakt-new-beleg-link:hover,
	body.post-php .cmx-kontakt-new-beleg-link:focus,
	body.post-php .cmx-artikel-new-beleg-link:hover,
	body.post-php .cmx-artikel-new-beleg-link:focus {
		color: #b42527;
	}
	body.post-php .cmx-kontakt-belege-link {
		margin-left: 15px;
	}
		</style>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		if (document.body && !document.body.classList.contains('post-php')) return;
		document.querySelectorAll('#delete-action .submitdelete, .editor-post-trash .components-button, .submitdelete').forEach(function(el){
			var text = (el.textContent || '').trim();
			if (text && !el.getAttribute('title')) {
				el.setAttribute('title', text);
			}
		});
	});
	</script>
	<?php
});

add_action('admin_head', function (): void {
	if (\is_network_admin()) {
		return;
	}
	?>
	<style id="cmx-trash-button-uniform">
		.cmx-bank-remove,
		.cmx-email-client-remove,
		.cmx-notiz-remove,
		#cmx-projekt-tasks button.cmx-task-remove,
		.cmx-kommu-remove,
		.cmx-variant-del,
		.cmx-lief-del,
		.cmx-pend-modal-delete,
		.cmx-camt-assignment-notice .cmx-camt-unassign-btn,
		.cmx-anzahlung-del,
		.cmx-del-pos,
		.cmx-scanner-rel-remove,
		.cmx-dok-rel-remove,
		.cmx-email-rel-remove,
		.cmx-kontakte-zu-kontakt-remove,
		.cmx-zu-projekt-remove,
		.cmx-li-remove-file,
		.cmx-kl-remove-file,
		.cmx-dok-remove,
		.cmx-belege-remove,
		#cmx_projekt_clear,
		#cmx_kontakt_clear,
		#cmx-export-zip-share-delete {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			box-sizing: border-box;
			width: 36px !important;
			min-width: 36px !important;
			height: 36px !important;
			min-height: 36px !important;
			padding: 0 !important;
			line-height: 1 !important;
			border-radius: 6px !important;
			border: 1px solid #c3c4c7 !important;
			background: #fff !important;
			box-shadow: none !important;
			color: #d63638 !important;
			text-decoration: none !important;
			transform: none !important;
			margin-top: 0 !important;
			overflow: hidden;
		}
		.cmx-bank-remove:hover,
		.cmx-bank-remove:focus,
		.cmx-email-client-remove:hover,
		.cmx-email-client-remove:focus,
		.cmx-notiz-remove:hover,
		.cmx-notiz-remove:focus,
		#cmx-projekt-tasks button.cmx-task-remove:hover,
		#cmx-projekt-tasks button.cmx-task-remove:focus,
		.cmx-kommu-remove:hover,
		.cmx-kommu-remove:focus,
		.cmx-variant-del:hover,
		.cmx-variant-del:focus,
		.cmx-lief-del:hover,
		.cmx-lief-del:focus,
		.cmx-pend-modal-delete:hover,
		.cmx-pend-modal-delete:focus,
		.cmx-camt-assignment-notice .cmx-camt-unassign-btn:hover,
		.cmx-camt-assignment-notice .cmx-camt-unassign-btn:focus,
		.cmx-anzahlung-del:hover,
		.cmx-anzahlung-del:focus,
		.cmx-del-pos:hover,
		.cmx-del-pos:focus,
		.cmx-scanner-rel-remove:hover,
		.cmx-scanner-rel-remove:focus,
		.cmx-dok-rel-remove:hover,
		.cmx-dok-rel-remove:focus,
		.cmx-email-rel-remove:hover,
		.cmx-email-rel-remove:focus,
		.cmx-kontakte-zu-kontakt-remove:hover,
		.cmx-kontakte-zu-kontakt-remove:focus,
		.cmx-zu-projekt-remove:hover,
		.cmx-zu-projekt-remove:focus,
		.cmx-li-remove-file:hover,
		.cmx-li-remove-file:focus,
		.cmx-kl-remove-file:hover,
		.cmx-kl-remove-file:focus,
		.cmx-dok-remove:hover,
		.cmx-dok-remove:focus,
		.cmx-belege-remove:hover,
		.cmx-belege-remove:focus,
		#cmx_projekt_clear:hover,
		#cmx_projekt_clear:focus,
		#cmx_kontakt_clear:hover,
		#cmx_kontakt_clear:focus,
		#cmx-export-zip-share-delete:hover,
		#cmx-export-zip-share-delete:focus {
			border-color: #8c8f94 !important;
			background: #f6f7f7 !important;
			color: #b32d2e !important;
		}
		.cmx-bank-remove .dashicons-trash,
		.cmx-email-client-remove .dashicons-trash,
		.cmx-notiz-remove .dashicons-trash,
		#cmx-projekt-tasks button.cmx-task-remove .dashicons-trash,
		.cmx-kommu-remove .dashicons-trash,
		.cmx-variant-del .dashicons-trash,
		.cmx-lief-del .dashicons-trash,
		.cmx-pend-modal-delete .dashicons-trash,
		.cmx-camt-assignment-notice .cmx-camt-unassign-btn .dashicons-trash,
		.cmx-anzahlung-del .dashicons-trash,
		.cmx-del-pos .dashicons-trash,
		.cmx-scanner-rel-remove .dashicons-trash,
		.cmx-dok-rel-remove .dashicons-trash,
		.cmx-email-rel-remove .dashicons-trash,
		.cmx-kontakte-zu-kontakt-remove .dashicons-trash,
		.cmx-zu-projekt-remove .dashicons-trash,
		.cmx-li-remove-file .dashicons-trash,
		.cmx-kl-remove-file .dashicons-trash,
		.cmx-dok-remove .dashicons-trash,
		.cmx-belege-remove .dashicons-trash,
		#cmx_projekt_clear .dashicons-trash,
		#cmx_kontakt_clear .dashicons-trash,
		#cmx-export-zip-share-delete .dashicons-trash {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 18px !important;
			height: 18px !important;
			font-size: 18px !important;
			line-height: 1 !important;
			color: #d63638 !important;
			margin: 0 !important;
			position: static !important;
			top: auto !important;
			left: auto !important;
			transform: none !important;
		}
	</style>
	<?php
}, 120);

add_action('admin_footer', function (): void {
	if (!\function_exists('get_current_screen')) {
		return;
	}
	$screen = \get_current_screen();
	if (!$screen || $screen->base !== 'post' || !\in_array((string) $screen->post_type, ['artikel', 'belege'], true)) {
		return;
	}
	?>
	<script>
	(function(){
		if (window.__cmxMaxTwoDecimalsBound) return;
		window.__cmxMaxTwoDecimalsBound = true;

		const disallowedTypes = new Set([
			'date','datetime-local','time','month','week',
			'checkbox','radio','hidden','button','submit','reset','file'
		]);
		const decimalHints = /(menge|preis|betrag|summe|total|subtotal|mwst|tax|vk|ek|aufwand|marge|rabatt|qty|anzahl|rate|prozent|percent)/i;

		function keyOf(el){
			return [
				el.name || '',
				el.id || '',
				(typeof el.className === 'string' ? el.className : '')
			].join(' ').toLowerCase();
		}

		function isDecimalField(el){
			if (!el || el.tagName !== 'INPUT') return false;
			const type = (el.getAttribute('type') || 'text').toLowerCase();
			if (disallowedTypes.has(type)) return false;
			if (type === 'number') return true;
			if ((el.inputMode || '').toLowerCase() === 'decimal') return true;
			return decimalHints.test(keyOf(el));
		}

		function clampString(raw){
			let s = String(raw == null ? '' : raw);
			if (s === '') return s;

			let suffix = '';
			const pct = s.match(/\s*%$/);
			if (pct) {
				suffix = pct[0];
				s = s.slice(0, -suffix.length);
			}

			const lastComma = s.lastIndexOf(',');
			const lastDot = s.lastIndexOf('.');
			const idx = Math.max(lastComma, lastDot);
			if (idx < 0) return String(raw);

			const before = s.slice(0, idx + 1);
			const after  = s.slice(idx + 1);
			const m = after.match(/^(\d+)(.*)$/);
			if (!m) return String(raw);

			const digits = m[1];
			const rest = m[2] || '';
			if (digits.length <= 2) return String(raw);

			return before + digits.slice(0, 2) + rest + suffix;
		}

		function enforce(el){
			if (!isDecimalField(el)) return;
			if ((el.getAttribute('type') || '').toLowerCase() === 'number') {
				const step = (el.getAttribute('step') || '').trim();
				if (step === '' || step.toLowerCase() === 'any') {
					el.setAttribute('step', '0.01');
				}
			}
			const v = String(el.value == null ? '' : el.value);
			if (v === '') return;
			const clamped = clampString(v);
			if (clamped !== v) {
				el.value = clamped;
			}
		}

		document.addEventListener('input', function(e){
			const el = e.target;
			if (!(el instanceof HTMLInputElement)) return;
			enforce(el);
		}, true);

		document.addEventListener('blur', function(e){
			const el = e.target;
			if (!(el instanceof HTMLInputElement)) return;
			enforce(el);
		}, true);

		document.querySelectorAll('input').forEach(function(el){
			if (!(el instanceof HTMLInputElement)) return;
			enforce(el);
		});

		const form = document.getElementById('post');
		if (form) {
			form.addEventListener('submit', function(){
				form.querySelectorAll('input').forEach(function(el){
					if (!(el instanceof HTMLInputElement)) return;
					enforce(el);
				});
			});
		}
	})();
	</script>
	<?php
});


// add_action('admin_head', function() {
//     echo '<style>

//     /* Metabox moderner */
//     .postbox {
//         border-radius: 8px;
//         border: 1px solid #dcdcdc;
//         box-shadow: 0 2px 6px rgba(0,0,0,0.05);
//         overflow: hidden;
//     }

//     /* Titelbereich */
//     .postbox .hndle {
//         background: #f7f7f7;
//         font-weight: 600;
//         padding: 12px 16px;
//         border-bottom: 1px solid #e5e5e5;
//     }

//     /* Inhalt */
//     .postbox .inside {
//         padding: 16px;
//         background: #ffffff;
//     }

//     </style>';
// });

add_action('admin_head', function() {
	echo '<style>

	/* Allgemein */
	/* .wrap, #wpbody-content { background: #f6f7fb; } */

	/* Metabox moderner */
	.postbox {
		border-radius: 12px;
		border: 1px solid #d9e0e7;
		box-shadow: 0 4px 14px rgba(0,0,0,0.05);
		overflow: hidden;
		background: #ffffff;
	}

	/* Abstand zwischen Boxen */
	.postbox,
	.stuffbox {
		margin-bottom: 18px;
	}

	/* Titelbereich */
	.postbox .postbox-header {
		background: #f8fafc;
		border-bottom: 1px solid #e6ebf0;
	}

	.postbox .hndle,
	.postbox .handlediv {
		background: #f8fafc;
	}

	.postbox .postbox-header .handle-actions,
	.postbox .postbox-header .handle-order-higher,
	.postbox .postbox-header .handle-order-lower,
	.postbox .postbox-header .handlediv button {
		background: transparent;
		box-shadow: none;
	}

	.postbox .hndle {
		font-weight: 600;
		padding: 14px 18px;
		border-bottom: 0;
		font-size: 14px;
		line-height: 1.4;
	}

	/* Inhalt */
	.postbox .inside {
		padding: 18px;
		background: #ffffff;
	}

	/* Tabs in Metaboxen etwas schöner */
	.postbox .nav-tab-wrapper {
		padding: 12px 18px 0;
		border-bottom: 1px solid #e6ebf0;
		background: #ffffff;
	}

	.postbox .nav-tab {
		border-radius: 8px 8px 0 0;
		padding: 8px 14px;
		font-weight: 500;
	}

	.postbox .nav-tab-active {
		background: #ffffff;
		border-bottom: 1px solid #ffffff;
	}

	/* Formularelemente */
	.postbox input[type="text"],
	.postbox input[type="number"],
	.postbox input[type="email"],
	.postbox input[type="url"],
	.postbox input[type="password"],
	.postbox input[type="date"],
	.postbox input[type="time"],
	.postbox input[type="search"],
	.postbox select,
	.postbox textarea,
	#titlediv #title,
	.form-table input[type="text"],
	.form-table input[type="number"],
	.form-table input[type="email"],
	.form-table input[type="url"],
	.form-table input[type="password"],
	.form-table select,
	.form-table textarea {
		border-radius: 8px;
		border: 1px solid #cfd8e3;
		padding: 8px 12px;
		min-height: 40px;
		box-shadow: none;
		background: #ffffff;
		transition: border-color 0.2s ease, box-shadow 0.2s ease;
	}

	.postbox textarea,
	.form-table textarea {
		min-height: 110px;
	}

	.postbox input:focus,
	.postbox select:focus,
	.postbox textarea:focus,
	#titlediv #title:focus,
	.form-table input:focus,
	.form-table select:focus,
	.form-table textarea:focus {
		border-color: #2271b1;
		box-shadow: 0 0 0 1px #2271b1;
		outline: none;
	}

	/* Labels */
	.postbox label,
	.form-table th {
		font-weight: 600;
		color: #1d2327;
	}

	/* Buttons */
	.button,
	.button-secondary {
		border-radius: 8px;
		min-height: 36px;
		padding: 0 14px;
		box-shadow: none;
	}

	#show-settings-link,
	#contextual-help-link,
	.screen-meta-toggle .show-settings {
		border-radius: 12px;
		min-height: 36px;
		padding: 0 14px;
		box-shadow: none;
	}

	.button-primary {
		border-radius: 8px;
		min-height: 36px;
		padding: 0 16px;
		box-shadow: none;
	}

	/* Tabellen im Admin */
	.wp-list-table {
		border: 1px solid #d9e0e7;
		border-radius: 12px;
		overflow: hidden;
		box-shadow: 0 4px 14px rgba(0,0,0,0.04);
		background: #ffffff;
	}

	.wp-list-table th {
		background: #f8fafc;
		font-weight: 600;
		border-bottom: 1px solid #e6ebf0;
	}

	body[class*="post-type-"] .wp-list-table thead .check-column input[type="checkbox"],
	body[class*="post-type-"] .wp-list-table tfoot .check-column input[type="checkbox"] {
		position: relative;
		top: 3px;
	}

	.wp-list-table td {
		vertical-align: middle;
	}

	body.post-type-kontakte .wp-list-table td,
	body.post-type-kontakte .wp-list-table th,
	body.post-type-artikel .wp-list-table td,
	body.post-type-artikel .wp-list-table th,
	body.post-type-belege .wp-list-table td,
	body.post-type-belege .wp-list-table th {
		vertical-align: top;
	}

	.wp-list-table tr:nth-child(even) td {
		background: #fcfdff;
	}

	/* Notices moderner */
	.notice,
	div.updated,
	div.error {
		border-radius: 10px;
		border-left-width: 4px;
		box-shadow: 0 2px 8px rgba(0,0,0,0.04);
	}

	/* Cards / Einstellungen optisch ruhiger */
	.form-table th {
		padding-top: 18px;
		padding-bottom: 18px;
	}

	.form-table td {
		padding-top: 14px;
		padding-bottom: 18px;
	}

	/* Dashboard Widgets */
	#dashboard-widgets .postbox-container .postbox {
		border-radius: 12px;
	}

	/* Kleinere Headline-Verbesserung */
	.wrap h1,
	.wrap h2 {
		font-weight: 600;
	}

	body.index-php .wrap > h1,
	body.index-php .wrap .wp-heading-inline {
		font-size: 20px;
		line-height: 1.3;
	}

	/* Akkordeon / wiederkehrende Boxen */
	.accordion-section {
		border-radius: 10px;
		overflow: hidden;
		border: 1px solid #d9e0e7;
		margin-bottom: 10px;
		background: #ffffff;
	}

		/* Checkboxen / Radios etwas luftiger */
		input[type="checkbox"],
		input[type="radio"] {
			margin-right: 6px;
		}

		/* Help-Spacer zwischen Listenblöcken etwas kompakter */
		.cmx-admin-help-spacer {
			height: 0.35em;
			margin: 0;
		}

		</style>';
	});

add_action('admin_head', function (): void {
	if (!\is_admin() || !\function_exists('get_current_screen')) {
		return;
	}

	$screen = \get_current_screen();
	if (!$screen || (string) ($screen->base ?? '') !== 'edit') {
		return;
	}

	$post_type = (string) ($screen->post_type ?? '');
	if ($post_type === '') {
		return;
	}

	$post_type_object = \get_post_type_object($post_type);
	if (!$post_type_object || !empty($post_type_object->_builtin)) {
		return;
	}
	?>
	<style>
		body.edit-php.post-type-<?php echo \esc_html($post_type); ?> .wrap .page-title-action,
		body.edit-php.post-type-<?php echo \esc_html($post_type); ?> .tablenav .button,
		body.edit-php.post-type-<?php echo \esc_html($post_type); ?> .tablenav input.button,
		body.edit-php.post-type-<?php echo \esc_html($post_type); ?> .tablenav button.button,
		body.edit-php.post-type-<?php echo \esc_html($post_type); ?> .tablenav select,
		body.edit-php.post-type-<?php echo \esc_html($post_type); ?> .search-box input[type="search"],
		body.edit-php.post-type-<?php echo \esc_html($post_type); ?> .search-box input[type="submit"] {
			border-radius: 8px;
		}
		body.edit-php.post-type-<?php echo \esc_html($post_type); ?> .subsubsub li {
			margin-right: 10px;
		}
	</style>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			document.querySelectorAll('body.edit-php.post-type-<?php echo \esc_js($post_type); ?> .subsubsub li').forEach(function (item) {
				Array.prototype.slice.call(item.childNodes).forEach(function (node) {
					if (node.nodeType !== Node.TEXT_NODE) {
						return;
					}
					if (node.nodeValue.indexOf('|') === -1) {
						return;
					}
					node.nodeValue = node.nodeValue.replace(/\s*\|\s*/g, ' ').trim();
				});
			});
		});
		</script>
		<?php
	});

add_action('admin_head', function (): void {
	if (!\is_admin() || !\function_exists('get_current_screen')) {
		return;
	}

	$screen = \get_current_screen();
	if (!$screen || !\in_array((string) ($screen->base ?? ''), ['post', 'post-new'], true)) {
		return;
	}

	$post_type = (string) ($screen->post_type ?? '');
	if ($post_type === '') {
		return;
	}

	$post_type_object = \get_post_type_object($post_type);
	if (!$post_type_object || !empty($post_type_object->_builtin)) {
		return;
	}
	?>
	<style id="cmx-cpt-editor-flex-layout">
		body.post-php.post-type-<?php echo \esc_html($post_type); ?> #poststuff,
		body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> #poststuff,
		body.post-php.post-type-<?php echo \esc_html($post_type); ?> #post-body,
		body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> #post-body,
		body.post-php.post-type-<?php echo \esc_html($post_type); ?> .postbox,
		body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> .postbox,
		body.post-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside,
		body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside {
			box-sizing: border-box;
			max-width: 100%;
		}

		body.post-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside,
		body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside {
			min-width: 0;
		}

		body.post-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]),
		body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]),
		body.post-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside select,
		body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside select,
		body.post-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside textarea,
		body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside textarea {
			max-width: 100%;
			box-sizing: border-box;
		}

		body.post-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside .form-table,
		body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside .form-table,
		body.post-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside .form-table tbody,
		body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside .form-table tbody {
			display: block;
			width: 100%;
		}

		body.post-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside .form-table tr,
		body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside .form-table tr {
			display: flex;
			flex-wrap: wrap;
			align-items: flex-start;
			width: 100%;
			gap: 6px 16px;
		}

		body.post-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside .form-table th,
		body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside .form-table th {
			flex: 0 0 180px;
			width: auto;
			min-width: 140px;
			padding-right: 0;
			box-sizing: border-box;
		}

		body.post-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside .form-table td,
		body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside .form-table td {
			flex: 1 1 260px;
			min-width: 0;
			width: auto;
			box-sizing: border-box;
		}

		body.post-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside [class*="-row"],
		body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside [class*="-row"] {
			min-width: 0;
		}

		body.post-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside [class*="-row"] select:not([multiple]),
		body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside [class*="-row"] select:not([multiple]),
		body.post-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside .form-table td select:not([multiple]),
		body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside .form-table td select:not([multiple]) {
			flex-shrink: 1;
			min-width: 0;
		}

		@media (max-width: 1180px) {
			body.post-php.post-type-<?php echo \esc_html($post_type); ?> #post-body.columns-2,
			body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> #post-body.columns-2 {
				display: flex;
				flex-direction: column;
				margin-right: 0;
			}

			body.post-php.post-type-<?php echo \esc_html($post_type); ?> #post-body.columns-2 #post-body-content,
			body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> #post-body.columns-2 #post-body-content {
				order: 1;
				width: 100%;
				min-width: 0;
			}

			body.post-php.post-type-<?php echo \esc_html($post_type); ?> #post-body.columns-2 #postbox-container-2,
			body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> #post-body.columns-2 #postbox-container-2 {
				order: 2;
			}

			body.post-php.post-type-<?php echo \esc_html($post_type); ?> #post-body.columns-2 #postbox-container-1,
			body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> #post-body.columns-2 #postbox-container-1 {
				order: 3;
			}

			body.post-php.post-type-<?php echo \esc_html($post_type); ?> #post-body.columns-2 #postbox-container-1,
			body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> #post-body.columns-2 #postbox-container-1,
			body.post-php.post-type-<?php echo \esc_html($post_type); ?> #post-body.columns-2 #postbox-container-2,
			body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> #post-body.columns-2 #postbox-container-2 {
				float: none;
				width: 100%;
				margin-right: 0;
			}

			body.post-php.post-type-<?php echo \esc_html($post_type); ?> #poststuff #post-body.columns-2 #side-sortables,
			body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> #poststuff #post-body.columns-2 #side-sortables {
				width: 100%;
				min-height: 0;
			}

			body.post-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside [class*="-row"] select:not([multiple]),
			body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside [class*="-row"] select:not([multiple]),
			body.post-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside .form-table td select:not([multiple]),
			body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside .form-table td select:not([multiple]) {
				width: 90%;
				max-width: 90%;
			}
		}

		@media (max-width: 782px) {
			body.post-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside .form-table th,
			body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside .form-table th,
			body.post-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside .form-table td,
			body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside .form-table td {
				flex: 1 1 100%;
				width: 100%;
				min-width: 0;
				padding-left: 0;
				padding-right: 0;
			}

			body.post-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside .button,
			body.post-new-php.post-type-<?php echo \esc_html($post_type); ?> .postbox .inside .button {
				white-space: normal;
				min-height: 34px;
			}
		}
	</style>
	<?php
});

add_action('admin_head', function (): void {
	if (!\is_admin() || \is_network_admin()) {
		return;
	}
	?>
	<style id="cmx-admin-select-height">
		.wp-core-ui select:not([multiple]) {
			min-height: 36px;
			line-height: 34px;
			padding-top: 0;
			padding-bottom: 0;
			box-sizing: border-box;
		}
		@media (max-width: 1180px) {
			.wp-core-ui .postbox .inside select:not([multiple]),
			.wp-core-ui .form-table select:not([multiple]) {
				max-width: 90%;
			}
		}
	</style>
	<?php
});

add_action('admin_head', function (): void {
	if (!\is_admin()) {
		return;
	}

	$settings_slug = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
		? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
		: 'cmx-einstellungen';
	$page = isset($_GET['page']) ? \sanitize_key((string) \wp_unslash($_GET['page'])) : '';
	if ($page !== $settings_slug) {
		return;
	}
	?>
	<style>
		.wrap h2.nav-tab-wrapper {
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
			padding-bottom: 0;
			border-bottom: 1px solid #e6ebf0;
		}

		.wrap h2.nav-tab-wrapper .nav-tab {
			margin: 0;
			padding: 8px 14px;
			border-radius: 8px 8px 0 0;
			font-weight: 500;
		}

		.wrap h2.nav-tab-wrapper .nav-tab-active {
			background: #ffffff;
			border-bottom-color: #ffffff;
		}

		.wrap .subsubsub {
			margin: 12px 0 0;
		}

		.wrap .subsubsub li {
			margin-right: 8px;
		}

		.wrap .subsubsub a {
			display: inline-block;
			padding: 6px 10px;
			border-radius: 999px;
			text-decoration: none;
		}

		.wrap .subsubsub a.current {
			color: #1d2327;
			background: transparent;
			box-shadow: none;
		}

		.wrap .cmx-tabpanel h2 {
			margin-top: 10px;
		}

		.wrap .cmx-tabpanel {
			clear: both;
		}

		.wrap .cmx-tabpanel .button,
		.wrap .cmx-tabpanel .button-secondary,
		.wrap .cmx-tabpanel .button-primary,
		.wrap .cmx-tabpanel input.button,
		.wrap .cmx-tabpanel button.button {
			border-radius: 8px;
		}
	</style>
	<?php
});



add_action('wp_before_admin_bar_render', function() {
	$current_user = \wp_get_current_user();
	global $wp_admin_bar;
	if (!$wp_admin_bar instanceof \WP_Admin_Bar) {
		return;
	}

	$current_user_id = (int) $current_user->ID;
	if ($current_user_id > 0) {
		$wp_admin_bar->add_node([
			'id'     => 'cmx-dark-mode-toggle',
			'parent' => 'top-secondary',
			'title'  => cmx_adminbar_dark_mode_icon_html() . '<span class="screen-reader-text">Dark Mode umschalten</span>',
			'href'   => '#',
			'meta'   => [
				'class' => 'cmx-adminbar-dark-mode-toggle',
				'title' => 'Dark Mode umschalten',
			],
		]);
	}

	// Nur für deinen speziellen User NICHT entfernen
	if ($current_user->user_login === 'cloudmeister') {
		return;
	}

	if ($current_user_id > 0) {
		$wp_admin_bar->add_node([
			'id'    => 'my-account',
			'title' => 'Abmelden, ' . \esc_html($current_user->user_login) . ' ' . \get_avatar($current_user_id, 26),
			'href'  => \wp_logout_url(\home_url('/')),
			'meta'  => [
				'class' => 'with-avatar cmx-admin-logout-link',
			],
		]);
	}

	// WordPress legt im Konto-Menü sowohl den direkten Link als auch den User-Info-Block an.
	$wp_admin_bar->remove_node('edit-profile');
	$wp_admin_bar->remove_node('user-info');
	$wp_admin_bar->remove_node('logout');
	$wp_admin_bar->remove_node('user-actions');

}, 99999);

function cmx_adminbar_dark_mode_head(): void {
	if (!\is_user_logged_in() || !\is_admin_bar_showing()) {
		return;
	}
	?>
	<script>
	(function(){
		try {
			if (window.localStorage && window.localStorage.getItem('cmxDarkMode') === '1') {
				document.documentElement.classList.add('cmx-dark-mode');
			}
		} catch (error) {}
	})();
	</script>
	<style id="cmx-adminbar-dark-mode-styles">
		#wpadminbar #wp-admin-bar-cmx-dark-mode-toggle > .ab-item {
			display: flex !important;
			align-items: center !important;
			justify-content: center !important;
			width: 34px !important;
			min-width: 34px !important;
			padding: 0 !important;
			cursor: pointer !important;
		}
		#wpadminbar #wp-admin-bar-cmx-dark-mode-toggle .cmx-adminbar-dark-mode-icon,
		#wpadminbar #wp-admin-bar-cmx-dark-mode-toggle .dashicons {
			display: block !important;
			width: 18px !important;
			height: 18px !important;
			font-size: 18px !important;
			line-height: 18px !important;
			margin: 0 !important;
			color: currentColor !important;
			stroke: currentColor !important;
		}
		html.cmx-dark-mode body.wp-admin,
		html.cmx-dark-mode body.wp-admin #wpwrap,
		html.cmx-dark-mode body.wp-admin #wpcontent,
		html.cmx-dark-mode body.wp-admin #wpbody-content {
			background: #111827 !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.wp-admin .wrap h1,
		html.cmx-dark-mode body.wp-admin .wrap h2,
		html.cmx-dark-mode body.wp-admin .wrap h3,
		html.cmx-dark-mode body.wp-admin .form-table th,
		html.cmx-dark-mode body.wp-admin label,
		html.cmx-dark-mode body.wp-admin .description {
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.wp-admin .postbox,
		html.cmx-dark-mode body.wp-admin .stuffbox,
		html.cmx-dark-mode body.wp-admin .card,
		html.cmx-dark-mode body.wp-admin .notice,
		html.cmx-dark-mode body.wp-admin .widefat,
		html.cmx-dark-mode body.wp-admin .wp-list-table,
		html.cmx-dark-mode body.wp-admin .cmb2-wrap,
		html.cmx-dark-mode body.wp-admin .cmx-tabpanel {
			background: #18202c !important;
			border-color: #334155 !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.wp-admin .notice,
		html.cmx-dark-mode body.wp-admin div.notice,
		html.cmx-dark-mode body.wp-admin div.updated,
		html.cmx-dark-mode body.wp-admin div.error,
		html.cmx-dark-mode body.wp-admin .update-nag,
		html.cmx-dark-mode body.wp-admin .notice-info,
		html.cmx-dark-mode body.wp-admin .notice-warning,
		html.cmx-dark-mode body.wp-admin .notice-success,
		html.cmx-dark-mode body.wp-admin .notice-error {
			background: #172033 !important;
			border-top-color: #334155 !important;
			border-right-color: #334155 !important;
			border-bottom-color: #334155 !important;
			color: #e5e7eb !important;
			box-shadow: 0 14px 32px rgba(0,0,0,.2) !important;
		}
		html.cmx-dark-mode body.wp-admin .notice p,
		html.cmx-dark-mode body.wp-admin .notice strong,
		html.cmx-dark-mode body.wp-admin .notice li,
		html.cmx-dark-mode body.wp-admin .update-nag,
		html.cmx-dark-mode body.wp-admin div.updated p,
		html.cmx-dark-mode body.wp-admin div.error p {
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.wp-admin .notice-info {
			border-left-color: #60a5fa !important;
		}
		html.cmx-dark-mode body.wp-admin .notice-warning,
		html.cmx-dark-mode body.wp-admin .update-nag {
			border-left-color: #f59e0b !important;
		}
		html.cmx-dark-mode body.wp-admin .notice-success,
		html.cmx-dark-mode body.wp-admin div.updated {
			border-left-color: #34d399 !important;
		}
		html.cmx-dark-mode body.wp-admin .notice-error,
		html.cmx-dark-mode body.wp-admin div.error {
			border-left-color: #f87171 !important;
		}
		html.cmx-dark-mode body.wp-admin .notice .button,
		html.cmx-dark-mode body.wp-admin .update-nag .button,
		html.cmx-dark-mode body.wp-admin .notice .button-secondary {
			background: #111827 !important;
			border-color: #64748b !important;
			color: #dbeafe !important;
		}
		html.cmx-dark-mode body.post-php #poststuff,
		html.cmx-dark-mode body.post-new-php #poststuff,
		html.cmx-dark-mode body.post-php #post-body,
		html.cmx-dark-mode body.post-new-php #post-body,
		html.cmx-dark-mode body.post-php #post-body-content,
		html.cmx-dark-mode body.post-new-php #post-body-content {
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.post-php #poststuff h2,
		html.cmx-dark-mode body.post-new-php #poststuff h2,
		html.cmx-dark-mode body.post-php #poststuff h3,
		html.cmx-dark-mode body.post-new-php #poststuff h3,
		html.cmx-dark-mode body.post-php #poststuff .postbox-header h2,
		html.cmx-dark-mode body.post-new-php #poststuff .postbox-header h2,
		html.cmx-dark-mode body.post-php #poststuff .hndle,
		html.cmx-dark-mode body.post-new-php #poststuff .hndle {
			color: #f8fafc !important;
		}
		html.cmx-dark-mode body.post-php .postbox,
		html.cmx-dark-mode body.post-new-php .postbox,
		html.cmx-dark-mode body.post-php .stuffbox,
		html.cmx-dark-mode body.post-new-php .stuffbox {
			background: #172033 !important;
			border-color: #334155 !important;
			box-shadow: 0 16px 36px rgba(0,0,0,.24) !important;
		}
		html.cmx-dark-mode body.post-php .postbox .postbox-header,
		html.cmx-dark-mode body.post-new-php .postbox .postbox-header,
		html.cmx-dark-mode body.post-php .postbox .hndle,
		html.cmx-dark-mode body.post-new-php .postbox .hndle,
		html.cmx-dark-mode body.post-php .postbox .handlediv,
		html.cmx-dark-mode body.post-new-php .postbox .handlediv,
		html.cmx-dark-mode body.post-php .postbox-header,
		html.cmx-dark-mode body.post-new-php .postbox-header {
			background: #111827 !important;
			border-color: #334155 !important;
			color: #f8fafc !important;
		}
		html.cmx-dark-mode body.post-php .postbox .inside,
		html.cmx-dark-mode body.post-new-php .postbox .inside,
		html.cmx-dark-mode body.post-php .postbox .cmb2-wrap,
		html.cmx-dark-mode body.post-new-php .postbox .cmb2-wrap,
		html.cmx-dark-mode body.post-php .postbox .cmb-row,
		html.cmx-dark-mode body.post-new-php .postbox .cmb-row,
		html.cmx-dark-mode body.post-php .postbox .form-table,
		html.cmx-dark-mode body.post-new-php .postbox .form-table {
			background: #172033 !important;
			border-color: #334155 !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.post-php .postbox table,
		html.cmx-dark-mode body.post-new-php .postbox table,
		html.cmx-dark-mode body.post-php .postbox thead,
		html.cmx-dark-mode body.post-new-php .postbox thead,
		html.cmx-dark-mode body.post-php .postbox tbody,
		html.cmx-dark-mode body.post-new-php .postbox tbody,
		html.cmx-dark-mode body.post-php .postbox tr,
		html.cmx-dark-mode body.post-new-php .postbox tr,
		html.cmx-dark-mode body.post-php .postbox th,
		html.cmx-dark-mode body.post-new-php .postbox th,
		html.cmx-dark-mode body.post-php .postbox td {
			background-color: transparent !important;
			border-color: #334155 !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.post-php .postbox [class*="-row"],
		html.cmx-dark-mode body.post-new-php .postbox [class*="-row"],
		html.cmx-dark-mode body.post-php .postbox [class*="_row"],
		html.cmx-dark-mode body.post-new-php .postbox [class*="_row"],
		html.cmx-dark-mode body.post-php .postbox [class*="-item"],
		html.cmx-dark-mode body.post-new-php .postbox [class*="-item"],
		html.cmx-dark-mode body.post-php .postbox [class*="-line"],
		html.cmx-dark-mode body.post-new-php .postbox [class*="-line"] {
			border-color: #334155 !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.post-php .postbox [style*="background:#fff"],
		html.cmx-dark-mode body.post-new-php .postbox [style*="background:#fff"],
		html.cmx-dark-mode body.post-php .postbox [style*="background: #fff"],
		html.cmx-dark-mode body.post-new-php .postbox [style*="background: #fff"],
		html.cmx-dark-mode body.post-php .postbox [style*="background:#ffffff"],
		html.cmx-dark-mode body.post-new-php .postbox [style*="background:#ffffff"],
		html.cmx-dark-mode body.post-php .postbox [style*="background: #ffffff"],
		html.cmx-dark-mode body.post-new-php .postbox [style*="background: #ffffff"],
		html.cmx-dark-mode body.post-php .postbox [style*="background:#fafafa"],
		html.cmx-dark-mode body.post-new-php .postbox [style*="background:#fafafa"],
		html.cmx-dark-mode body.post-php .postbox [style*="background: #fafafa"],
		html.cmx-dark-mode body.post-new-php .postbox [style*="background: #fafafa"] {
			background: #172033 !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.post-php #cmx-dokumente-drop,
		html.cmx-dark-mode body.post-new-php #cmx-dokumente-drop,
		html.cmx-dark-mode body.post-php [id*="upload"] label[for*="file"],
		html.cmx-dark-mode body.post-new-php [id*="upload"] label[for*="file"],
		html.cmx-dark-mode body.post-php [class*="upload"] label[for*="file"],
		html.cmx-dark-mode body.post-new-php [class*="upload"] label[for*="file"],
		html.cmx-dark-mode body.post-php [class*="drop"],
		html.cmx-dark-mode body.post-new-php [class*="drop"],
		html.cmx-dark-mode body.post-php [id*="drop"],
		html.cmx-dark-mode body.post-new-php [id*="drop"] {
			background: #101826 !important;
			border-color: #64748b !important;
			color: #dbeafe !important;
		}
		html.cmx-dark-mode body.post-php #cmx-dokumente-drop strong,
		html.cmx-dark-mode body.post-new-php #cmx-dokumente-drop strong,
		html.cmx-dark-mode body.post-php #cmx-dokumente-drop small,
		html.cmx-dark-mode body.post-new-php #cmx-dokumente-drop small,
		html.cmx-dark-mode body.post-php [class*="upload"] strong,
		html.cmx-dark-mode body.post-new-php [class*="upload"] strong,
		html.cmx-dark-mode body.post-php [class*="upload"] small,
		html.cmx-dark-mode body.post-new-php [class*="upload"] small,
		html.cmx-dark-mode body.post-php [class*="drop"] strong,
		html.cmx-dark-mode body.post-new-php [class*="drop"] strong,
		html.cmx-dark-mode body.post-php [class*="drop"] small,
		html.cmx-dark-mode body.post-new-php [class*="drop"] small {
			color: #dbeafe !important;
		}
		html.cmx-dark-mode body.post-php #cmx-dokumente-list,
		html.cmx-dark-mode body.post-new-php #cmx-dokumente-list,
		html.cmx-dark-mode body.post-php #cmx-dokumente-existing,
		html.cmx-dark-mode body.post-new-php #cmx-dokumente-existing,
		html.cmx-dark-mode body.post-php #cmx-dokumente-existing li,
		html.cmx-dark-mode body.post-new-php #cmx-dokumente-existing li {
			background: #172033 !important;
			border-color: #334155 !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.post-php #cmx-belege-drop,
		html.cmx-dark-mode body.post-new-php #cmx-belege-drop,
		html.cmx-dark-mode body.post-php .cmx-li-preview-frame,
		html.cmx-dark-mode body.post-new-php .cmx-li-preview-frame,
		html.cmx-dark-mode body.post-php .cmx-kl-preview-frame,
		html.cmx-dark-mode body.post-new-php .cmx-kl-preview-frame {
			background: #101826 !important;
			border-color: #64748b !important;
			color: #dbeafe !important;
		}
		html.cmx-dark-mode body.post-php #cmx-intern-notizen-list .cmx-intern-notiz-row,
		html.cmx-dark-mode body.post-new-php #cmx-intern-notizen-list .cmx-intern-notiz-row,
		html.cmx-dark-mode body.post-php #cmx-projekt-tasks .cmx-task-row,
		html.cmx-dark-mode body.post-new-php #cmx-projekt-tasks .cmx-task-row,
		html.cmx-dark-mode body.post-php .cmx-variant-block,
		html.cmx-dark-mode body.post-new-php .cmx-variant-block,
		html.cmx-dark-mode body.post-php #cmx-artikel-lieferanten-list .cmx-lief-row,
		html.cmx-dark-mode body.post-new-php #cmx-artikel-lieferanten-list .cmx-lief-row {
			background: #182234 !important;
			border-color: #334155 !important;
			color: #e5e7eb !important;
			box-shadow: none !important;
		}
		html.cmx-dark-mode body.post-php #cmx-intern-notizen-list .cmx-intern-notiz-html-preview,
		html.cmx-dark-mode body.post-new-php #cmx-intern-notizen-list .cmx-intern-notiz-html-preview,
		html.cmx-dark-mode body.post-php #cmx-artikel-lieferanten-list .cmx-lief-note-panel,
		html.cmx-dark-mode body.post-new-php #cmx-artikel-lieferanten-list .cmx-lief-note-panel {
			background: #101826 !important;
			border-color: #334155 !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.post-php #cmx-intern-notizen-list .cmx-intern-notiz-html-preview th,
		html.cmx-dark-mode body.post-new-php #cmx-intern-notizen-list .cmx-intern-notiz-html-preview th,
		html.cmx-dark-mode body.post-php #cmx-intern-notizen-list .cmx-intern-notiz-html-preview td,
		html.cmx-dark-mode body.post-new-php #cmx-intern-notizen-list .cmx-intern-notiz-html-preview td {
			background: transparent !important;
			border-color: #334155 !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.post-php #cmx-intern-notizen-list .cmx-intern-notiz-links,
		html.cmx-dark-mode body.post-new-php #cmx-intern-notizen-list .cmx-intern-notiz-links,
		html.cmx-dark-mode body.post-php #cmx-artikel-lieferanten-head span,
		html.cmx-dark-mode body.post-new-php #cmx-artikel-lieferanten-head span,
		html.cmx-dark-mode body.post-php #cmx-artikel-lieferanten-list .cmx-lief-field::before,
		html.cmx-dark-mode body.post-new-php #cmx-artikel-lieferanten-list .cmx-lief-field::before,
		html.cmx-dark-mode body.post-php #cmx-projekt-tasks .cmx-task-label,
		html.cmx-dark-mode body.post-new-php #cmx-projekt-tasks .cmx-task-label,
		html.cmx-dark-mode body.post-php .cmx-price-row .cmx-f label,
		html.cmx-dark-mode body.post-new-php .cmx-price-row .cmx-f label,
		html.cmx-dark-mode body.post-php #cmx-artikel-lieferanten-list .cmx-lief-note-label,
		html.cmx-dark-mode body.post-new-php #cmx-artikel-lieferanten-list .cmx-lief-note-label {
			color: #dbeafe !important;
		}
		html.cmx-dark-mode body.post-php #cmx-projekt-tasks .cmx-art-suggest,
		html.cmx-dark-mode body.post-new-php #cmx-projekt-tasks .cmx-art-suggest,
		html.cmx-dark-mode body.post-php #cmx-artikel-lieferanten-list .cmx-lief-supplier-suggest,
		html.cmx-dark-mode body.post-new-php #cmx-artikel-lieferanten-list .cmx-lief-supplier-suggest,
		html.cmx-dark-mode body.post-php .cmx-price-row .cmx-f .cmx-taxonomy-picker,
		html.cmx-dark-mode body.post-new-php .cmx-price-row .cmx-f .cmx-taxonomy-picker {
			background: #0f172a !important;
			border-color: #475569 !important;
			color: #e5e7eb !important;
			box-shadow: 0 18px 36px rgba(0,0,0,.35) !important;
		}
		html.cmx-dark-mode body.post-php #cmx-projekt-tasks .cmx-art-suggest li,
		html.cmx-dark-mode body.post-new-php #cmx-projekt-tasks .cmx-art-suggest li,
		html.cmx-dark-mode body.post-php #cmx-artikel-lieferanten-list .cmx-lief-supplier-suggest li,
		html.cmx-dark-mode body.post-new-php #cmx-artikel-lieferanten-list .cmx-lief-supplier-suggest li {
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.post-php #cmx-projekt-tasks .cmx-art-suggest li.active,
		html.cmx-dark-mode body.post-new-php #cmx-projekt-tasks .cmx-art-suggest li.active,
		html.cmx-dark-mode body.post-php #cmx-projekt-tasks .cmx-art-suggest li:hover,
		html.cmx-dark-mode body.post-new-php #cmx-projekt-tasks .cmx-art-suggest li:hover,
		html.cmx-dark-mode body.post-php #cmx-artikel-lieferanten-list .cmx-lief-supplier-suggest li.active,
		html.cmx-dark-mode body.post-new-php #cmx-artikel-lieferanten-list .cmx-lief-supplier-suggest li.active,
		html.cmx-dark-mode body.post-php #cmx-artikel-lieferanten-list .cmx-lief-supplier-suggest li:hover,
		html.cmx-dark-mode body.post-new-php #cmx-artikel-lieferanten-list .cmx-lief-supplier-suggest li:hover {
			background: #1d4ed8 !important;
			color: #fff !important;
		}
		html.cmx-dark-mode body.post-php #cmx-artikel-lieferanten-list .cmx-supplier-open,
		html.cmx-dark-mode body.post-new-php #cmx-artikel-lieferanten-list .cmx-supplier-open,
		html.cmx-dark-mode body.post-php #cmx-artikel-lieferanten-list .cmx-url-open,
		html.cmx-dark-mode body.post-new-php #cmx-artikel-lieferanten-list .cmx-url-open,
		html.cmx-dark-mode body.post-php #cmx-artikel-lieferanten-list .cmx-lief-drag,
		html.cmx-dark-mode body.post-new-php #cmx-artikel-lieferanten-list .cmx-lief-drag {
			background: #101826 !important;
			border-color: #475569 !important;
			color: #bfdbfe !important;
		}
		html.cmx-dark-mode body.post-php #cmx-artikel-lieferanten-list .cmx-supplier-open:hover,
		html.cmx-dark-mode body.post-new-php #cmx-artikel-lieferanten-list .cmx-supplier-open:hover,
		html.cmx-dark-mode body.post-php #cmx-artikel-lieferanten-list .cmx-url-open:hover,
		html.cmx-dark-mode body.post-new-php #cmx-artikel-lieferanten-list .cmx-url-open:hover,
		html.cmx-dark-mode body.post-php #cmx-artikel-lieferanten-list .cmx-lief-drag:hover,
		html.cmx-dark-mode body.post-new-php #cmx-artikel-lieferanten-list .cmx-lief-drag:hover {
			background: #1e293b !important;
			border-color: #93c5fd !important;
			color: #fff !important;
		}
		html.cmx-dark-mode body.post-php #cmx-artikel-lieferanten-list .cmx-lief-row.is-dragging,
		html.cmx-dark-mode body.post-new-php #cmx-artikel-lieferanten-list .cmx-lief-row.is-dragging {
			background: #1e3a5f !important;
			border-color: #60a5fa !important;
		}
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-contact-row,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-contact-row,
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-contact-row:nth-child(even),
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-contact-row:nth-child(even) {
			background: #182234 !important;
			border-color: #334155 !important;
			color: #e5e7eb !important;
			box-shadow: none !important;
		}
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-contact-row.is-dragging,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-contact-row.is-dragging {
			background: #1e3a5f !important;
			border-color: #60a5fa !important;
			box-shadow: 0 0 0 1px rgba(96,165,250,.35) !important;
		}
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-field label,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-field label,
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-field-title,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-field-title {
			color: #dbeafe !important;
		}
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-email-action,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-email-action,
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-phone-action,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-phone-action {
			color: #dbeafe !important;
			background: transparent !important;
			box-shadow: none !important;
		}
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-email-action:hover,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-email-action:hover,
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-email-action:focus,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-email-action:focus,
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-phone-action:hover,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-phone-action:hover,
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-phone-action:focus,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-phone-action:focus {
			color: #fff !important;
			outline: 1px solid #60a5fa !important;
			outline-offset: 2px !important;
		}
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-email-menu,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-email-menu,
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-phone-menu,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-phone-menu {
			background: #0f172a !important;
			border-color: #475569 !important;
			color: #e5e7eb !important;
			box-shadow: 0 18px 36px rgba(0,0,0,.35) !important;
		}
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-email-menu button,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-email-menu button,
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-phone-menu button,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-phone-menu button {
			background: transparent !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-email-menu button:hover,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-email-menu button:hover,
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-email-menu button:focus,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-email-menu button:focus,
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-phone-menu button:hover,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-phone-menu button:hover,
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-phone-menu button:focus,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-phone-menu button:focus {
			background: #1d4ed8 !important;
			color: #fff !important;
			outline: none !important;
		}
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-drag,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-drag {
			background: #101826 !important;
			color: #bfdbfe !important;
			box-shadow: inset 0 0 0 1px #475569 !important;
		}
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-drag:hover,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-drag:hover {
			background: #1e293b !important;
			color: #fff !important;
			box-shadow: inset 0 0 0 1px #93c5fd !important;
		}
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-thanks,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-thanks,
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-field-icon,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-field-icon {
			color: #94a3b8 !important;
		}
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-thanks:hover,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-thanks:hover,
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-thanks:focus,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-thanks:focus {
			color: #f87171 !important;
			outline: 1px solid #60a5fa !important;
			outline-offset: 2px !important;
		}
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-thanks.is-disabled,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-thanks.is-disabled,
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-thanks:disabled,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-thanks:disabled {
			color: #64748b !important;
		}
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-thanks.is-success,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-thanks.is-success {
			color: #34d399 !important;
		}
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-toggle-ui,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-toggle-ui {
			background: #475569 !important;
			box-shadow: inset 0 0 0 1px #64748b !important;
		}
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-toggle-ui::after,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-toggle-ui::after {
			background: #e5e7eb !important;
			box-shadow: 0 1px 4px rgba(0,0,0,.35) !important;
		}
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-toggle input[type="checkbox"]:checked + .cmx-kommu-toggle-ui,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-toggle input[type="checkbox"]:checked + .cmx-kommu-toggle-ui {
			background: #a42c24 !important;
			box-shadow: inset 0 0 0 1px #dc6b60 !important;
		}
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-toggle input[type="checkbox"]:focus-visible + .cmx-kommu-toggle-ui,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-toggle input[type="checkbox"]:focus-visible + .cmx-kommu-toggle-ui {
			box-shadow: inset 0 0 0 1px #64748b, 0 0 0 2px rgba(96,165,250,.45) !important;
		}
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-remove,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-remove {
			background: #101826 !important;
			border-color: #475569 !important;
		}
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-remove:hover,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-remove:hover,
		html.cmx-dark-mode body.post-php #cmx_kommunikation_box .cmx-kommu-remove:focus,
		html.cmx-dark-mode body.post-new-php #cmx_kommunikation_box .cmx-kommu-remove:focus {
			background: #1e293b !important;
			border-color: #f87171 !important;
		}
		html.cmx-dark-mode body.post-php .cmx-suggest ul,
		html.cmx-dark-mode body.post-new-php .cmx-suggest ul,
		html.cmx-dark-mode body.post-php .cmx-ac-list,
		html.cmx-dark-mode body.post-new-php .cmx-ac-list,
		html.cmx-dark-mode body.post-php #cmx-artikel-marke-box .cmx-marke-suggest-list,
		html.cmx-dark-mode body.post-new-php #cmx-artikel-marke-box .cmx-marke-suggest-list,
		html.cmx-dark-mode body.post-php .cmx-task-picker-panel,
		html.cmx-dark-mode body.post-new-php .cmx-task-picker-panel,
		html.cmx-dark-mode body.post-php .cmx-task-picker-list,
		html.cmx-dark-mode body.post-new-php .cmx-task-picker-list,
		html.cmx-dark-mode body.post-php .cmx-art-suggest,
		html.cmx-dark-mode body.post-new-php .cmx-art-suggest {
			background: #0f172a !important;
			border-color: #475569 !important;
			color: #e5e7eb !important;
			box-shadow: 0 18px 36px rgba(0,0,0,.35) !important;
		}
		html.cmx-dark-mode body.post-php .cmx-suggest li,
		html.cmx-dark-mode body.post-new-php .cmx-suggest li,
		html.cmx-dark-mode body.post-php .cmx-ac-item,
		html.cmx-dark-mode body.post-new-php .cmx-ac-item,
		html.cmx-dark-mode body.post-php #cmx-artikel-marke-box .cmx-marke-suggest-list li,
		html.cmx-dark-mode body.post-new-php #cmx-artikel-marke-box .cmx-marke-suggest-list li,
		html.cmx-dark-mode body.post-php .cmx-task-picker-list li,
		html.cmx-dark-mode body.post-new-php .cmx-task-picker-list li,
		html.cmx-dark-mode body.post-php .cmx-art-suggest li,
		html.cmx-dark-mode body.post-new-php .cmx-art-suggest li {
			border-color: #334155 !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.post-php .cmx-suggest li.active,
		html.cmx-dark-mode body.post-new-php .cmx-suggest li.active,
		html.cmx-dark-mode body.post-php .cmx-suggest li:hover,
		html.cmx-dark-mode body.post-new-php .cmx-suggest li:hover,
		html.cmx-dark-mode body.post-php .cmx-ac-item.is-active,
		html.cmx-dark-mode body.post-new-php .cmx-ac-item.is-active,
		html.cmx-dark-mode body.post-php .cmx-ac-item:hover,
		html.cmx-dark-mode body.post-new-php .cmx-ac-item:hover,
		html.cmx-dark-mode body.post-php #cmx-artikel-marke-box .cmx-marke-suggest-list li.active,
		html.cmx-dark-mode body.post-new-php #cmx-artikel-marke-box .cmx-marke-suggest-list li.active,
		html.cmx-dark-mode body.post-php #cmx-artikel-marke-box .cmx-marke-suggest-list li:hover,
		html.cmx-dark-mode body.post-new-php #cmx-artikel-marke-box .cmx-marke-suggest-list li:hover,
		html.cmx-dark-mode body.post-php .cmx-task-picker-list li.active,
		html.cmx-dark-mode body.post-new-php .cmx-task-picker-list li.active,
		html.cmx-dark-mode body.post-php .cmx-task-picker-list li:hover,
		html.cmx-dark-mode body.post-new-php .cmx-task-picker-list li:hover {
			background: #1d4ed8 !important;
			color: #fff !important;
		}
		html.cmx-dark-mode body.post-php .cmx-task-picker-meta,
		html.cmx-dark-mode body.post-new-php .cmx-task-picker-meta,
		html.cmx-dark-mode body.post-php .cmx-task-picker-info,
		html.cmx-dark-mode body.post-new-php .cmx-task-picker-info,
		html.cmx-dark-mode body.post-php .cmx-task-picker-empty,
		html.cmx-dark-mode body.post-new-php .cmx-task-picker-empty {
			color: #a7b4c7 !important;
		}
		html.cmx-dark-mode body.post-php #cmx-positionen-wrap,
		html.cmx-dark-mode body.post-new-php #cmx-positionen-wrap,
		html.cmx-dark-mode body.post-php #cmx-positionen-table,
		html.cmx-dark-mode body.post-new-php #cmx-positionen-table {
			background: #172033 !important;
			border-color: #334155 !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.post-php #cmx-positionen-table tbody tr.cmx-pos-row:not(.cmx-pos-row-abschnitt),
		html.cmx-dark-mode body.post-new-php #cmx-positionen-table tbody tr.cmx-pos-row:not(.cmx-pos-row-abschnitt),
		html.cmx-dark-mode body.post-php #cmx-positionen-table tr.ui-sortable-helper td,
		html.cmx-dark-mode body.post-new-php #cmx-positionen-table tr.ui-sortable-helper td {
			background: #182234 !important;
			border-color: #334155 !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.post-php #cmx-positionen-table tr.cmx-pos-row-abschnitt td,
		html.cmx-dark-mode body.post-new-php #cmx-positionen-table tr.cmx-pos-row-abschnitt td,
		html.cmx-dark-mode body.post-php #cmx-positionen-table.cmx-section-drag-active tr.cmx-pos-row-abschnitt td,
		html.cmx-dark-mode body.post-new-php #cmx-positionen-table.cmx-section-drag-active tr.cmx-pos-row-abschnitt td {
			background: #101826 !important;
			border-color: #334155 !important;
			color: #dbeafe !important;
		}
		html.cmx-dark-mode body.post-php #cmx-positionen-table tr.cmx-pos-sort-placeholder td.cmx-pos-sort-placeholder-cell,
		html.cmx-dark-mode body.post-new-php #cmx-positionen-table tr.cmx-pos-sort-placeholder td.cmx-pos-sort-placeholder-cell,
		html.cmx-dark-mode body.post-php #cmx-positionen-table tr.cmx-pos-sort-placeholder-section td.cmx-pos-sort-placeholder-cell,
		html.cmx-dark-mode body.post-new-php #cmx-positionen-table tr.cmx-pos-sort-placeholder-section td.cmx-pos-sort-placeholder-cell {
			background: #1e293b !important;
			border-color: #60a5fa !important;
			color: #dbeafe !important;
		}
		html.cmx-dark-mode body.post-php #cmx-positionen-table .cmx-pos-sort-placeholder-label,
		html.cmx-dark-mode body.post-new-php #cmx-positionen-table .cmx-pos-sort-placeholder-label {
			color: #dbeafe !important;
		}
		html.cmx-dark-mode body.post-php .cmx-tab-buttons,
		html.cmx-dark-mode body.post-new-php .cmx-tab-buttons {
			border-color: #334155 !important;
			background: transparent !important;
		}
		html.cmx-dark-mode body.post-php .cmx-tab-buttons button,
		html.cmx-dark-mode body.post-new-php .cmx-tab-buttons button {
			background: #101826 !important;
			border-color: #475569 !important;
			color: #dbeafe !important;
			box-shadow: none !important;
		}
		html.cmx-dark-mode body.post-php .cmx-tab-buttons button.active,
		html.cmx-dark-mode body.post-new-php .cmx-tab-buttons button.active {
			background: #1e293b !important;
			border-color: #64748b !important;
			border-bottom-color: #1e293b !important;
			color: #fff !important;
		}
		html.cmx-dark-mode body.post-php .cmx-tab-buttons button:hover,
		html.cmx-dark-mode body.post-new-php .cmx-tab-buttons button:hover,
		html.cmx-dark-mode body.post-php .cmx-tab-buttons button:focus,
		html.cmx-dark-mode body.post-new-php .cmx-tab-buttons button:focus {
			background: #243044 !important;
			border-color: #93c5fd !important;
			color: #fff !important;
			box-shadow: 0 0 0 1px rgba(147,197,253,.45) !important;
			outline: none !important;
		}
		html.cmx-dark-mode body.post-php .cmx-tab-content,
		html.cmx-dark-mode body.post-new-php .cmx-tab-content,
		html.cmx-dark-mode body.post-php .cmx-tabs,
		html.cmx-dark-mode body.post-new-php .cmx-tabs {
			background: transparent !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.post-php .cmx-map,
		html.cmx-dark-mode body.post-new-php .cmx-map,
		html.cmx-dark-mode body.post-php .cmx-map .cmx-map-label,
		html.cmx-dark-mode body.post-new-php .cmx-map .cmx-map-label {
			background: #101826 !important;
			border-color: #334155 !important;
			color: #dbeafe !important;
		}
		html.cmx-dark-mode body.post-php .cmx-map iframe,
		html.cmx-dark-mode body.post-new-php .cmx-map iframe {
			background: #101826 !important;
			filter: invert(.86) hue-rotate(180deg) saturate(.72) brightness(.82) contrast(.96) !important;
		}
		html.cmx-dark-mode body.post-php .cmx-map a,
		html.cmx-dark-mode body.post-new-php .cmx-map a,
		html.cmx-dark-mode body.post-php .cmx-map .button,
		html.cmx-dark-mode body.post-new-php .cmx-map .button {
			background: #101826 !important;
			border-color: #475569 !important;
			color: #bfdbfe !important;
			box-shadow: 0 8px 18px rgba(0,0,0,.24) !important;
		}
		html.cmx-dark-mode body.post-php .cmx-map a:hover,
		html.cmx-dark-mode body.post-new-php .cmx-map a:hover,
		html.cmx-dark-mode body.post-php .cmx-map a:focus,
		html.cmx-dark-mode body.post-new-php .cmx-map a:focus,
		html.cmx-dark-mode body.post-php .cmx-map .button:hover,
		html.cmx-dark-mode body.post-new-php .cmx-map .button:hover,
		html.cmx-dark-mode body.post-php .cmx-map .button:focus,
		html.cmx-dark-mode body.post-new-php .cmx-map .button:focus {
			background: #1e293b !important;
			border-color: #93c5fd !important;
			color: #fff !important;
		}
		html.cmx-dark-mode body.post-php .postbox label,
		html.cmx-dark-mode body.post-new-php .postbox label,
		html.cmx-dark-mode body.post-php .postbox legend,
		html.cmx-dark-mode body.post-new-php .postbox legend,
		html.cmx-dark-mode body.post-php .postbox .cmb-th,
		html.cmx-dark-mode body.post-new-php .postbox .cmb-th,
		html.cmx-dark-mode body.post-php .postbox .cmb-th label,
		html.cmx-dark-mode body.post-new-php .postbox .cmb-th label,
		html.cmx-dark-mode body.post-php .postbox .form-table th,
		html.cmx-dark-mode body.post-new-php .postbox .form-table th,
		html.cmx-dark-mode body.post-php .postbox .description,
		html.cmx-dark-mode body.post-new-php .postbox .description {
			color: #dbeafe !important;
		}
		html.cmx-dark-mode body.post-php .postbox .description,
		html.cmx-dark-mode body.post-new-php .postbox .description,
		html.cmx-dark-mode body.post-php .postbox small,
		html.cmx-dark-mode body.post-new-php .postbox small {
			color: #a7b4c7 !important;
		}
		html.cmx-dark-mode body.post-php #titlediv #title,
		html.cmx-dark-mode body.post-new-php #titlediv #title,
		html.cmx-dark-mode body.post-php .postbox input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"]):not([type="hidden"]),
		html.cmx-dark-mode body.post-new-php .postbox input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"]):not([type="hidden"]),
		html.cmx-dark-mode body.post-php .postbox select,
		html.cmx-dark-mode body.post-new-php .postbox select,
		html.cmx-dark-mode body.post-php .postbox textarea,
		html.cmx-dark-mode body.post-new-php .postbox textarea {
			background: #0f172a !important;
			border-color: #475569 !important;
			color: #f8fafc !important;
			box-shadow: none !important;
		}
		html.cmx-dark-mode body.wp-admin input[readonly],
		html.cmx-dark-mode body.wp-admin textarea[readonly],
		html.cmx-dark-mode body.wp-admin input:disabled,
		html.cmx-dark-mode body.wp-admin select:disabled,
		html.cmx-dark-mode body.wp-admin textarea:disabled,
		html.cmx-dark-mode body.post-php #cmx-stammdaten input[readonly],
		html.cmx-dark-mode body.post-new-php #cmx-stammdaten input[readonly] {
			background: #101826 !important;
			border-color: #475569 !important;
			color: #cbd5e1 !important;
			opacity: 1 !important;
			-webkit-text-fill-color: #cbd5e1 !important;
			box-shadow: none !important;
		}
		html.cmx-dark-mode body.wp-admin input[readonly]:focus,
		html.cmx-dark-mode body.wp-admin textarea[readonly]:focus,
		html.cmx-dark-mode body.post-php #cmx-stammdaten input[readonly]:focus,
		html.cmx-dark-mode body.post-new-php #cmx-stammdaten input[readonly]:focus {
			border-color: #64748b !important;
			box-shadow: none !important;
			outline: none !important;
		}
		html.cmx-dark-mode body.post-php #titlediv #title:focus,
		html.cmx-dark-mode body.post-new-php #titlediv #title:focus,
		html.cmx-dark-mode body.post-php .postbox input:focus,
		html.cmx-dark-mode body.post-new-php .postbox input:focus,
		html.cmx-dark-mode body.post-php .postbox select:focus,
		html.cmx-dark-mode body.post-new-php .postbox select:focus,
		html.cmx-dark-mode body.post-php .postbox textarea:focus,
		html.cmx-dark-mode body.post-new-php .postbox textarea:focus {
			border-color: #93c5fd !important;
			box-shadow: 0 0 0 1px rgba(147,197,253,.5) !important;
			outline: none !important;
		}
		html.cmx-dark-mode body.post-php #title-prompt-text,
		html.cmx-dark-mode body.post-new-php #title-prompt-text,
		html.cmx-dark-mode body.post-php .postbox input::placeholder,
		html.cmx-dark-mode body.post-new-php .postbox input::placeholder,
		html.cmx-dark-mode body.post-php .postbox textarea::placeholder,
		html.cmx-dark-mode body.post-new-php .postbox textarea::placeholder {
			color: #94a3b8 !important;
		}
		html.cmx-dark-mode body.post-php .postbox input[type="checkbox"],
		html.cmx-dark-mode body.post-new-php .postbox input[type="checkbox"],
		html.cmx-dark-mode body.post-php .postbox input[type="radio"],
		html.cmx-dark-mode body.post-new-php .postbox input[type="radio"] {
			background: #0f172a !important;
			border-color: #64748b !important;
			color: #93c5fd !important;
		}
		html.cmx-dark-mode body.post-php .postbox .button,
		html.cmx-dark-mode body.post-new-php .postbox .button,
		html.cmx-dark-mode body.post-php .postbox .button-secondary,
		html.cmx-dark-mode body.post-new-php .postbox .button-secondary {
			background: #111827 !important;
			border-color: #64748b !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.post-php .postbox .button:hover,
		html.cmx-dark-mode body.post-new-php .postbox .button:hover,
		html.cmx-dark-mode body.post-php .postbox .button:focus,
		html.cmx-dark-mode body.post-new-php .postbox .button:focus {
			background: #1e293b !important;
			border-color: #93c5fd !important;
			color: #f8fafc !important;
		}
		html.cmx-dark-mode body.post-php .postbox .button-primary,
		html.cmx-dark-mode body.post-new-php .postbox .button-primary,
		html.cmx-dark-mode body.post-php #publishing-action .button-primary,
		html.cmx-dark-mode body.post-new-php #publishing-action .button-primary {
			background: #a42c24 !important;
			border-color: #a42c24 !important;
			color: #fff !important;
		}
		html.cmx-dark-mode body.post-php .postbox .nav-tab-wrapper,
		html.cmx-dark-mode body.post-new-php .postbox .nav-tab-wrapper,
		html.cmx-dark-mode body.post-php .category-tabs,
		html.cmx-dark-mode body.post-new-php .category-tabs {
			background: #172033 !important;
			border-color: #334155 !important;
		}
		html.cmx-dark-mode body.post-php .postbox .nav-tab,
		html.cmx-dark-mode body.post-new-php .postbox .nav-tab,
		html.cmx-dark-mode body.post-php .category-tabs a,
		html.cmx-dark-mode body.post-new-php .category-tabs a {
			background: #101826 !important;
			border-color: #334155 !important;
			color: #bfdbfe !important;
			box-shadow: none !important;
		}
		html.cmx-dark-mode body.post-php .categorydiv .category-tabs,
		html.cmx-dark-mode body.post-new-php .categorydiv .category-tabs,
		html.cmx-dark-mode body.post-php .categorydiv .category-tabs li,
		html.cmx-dark-mode body.post-new-php .categorydiv .category-tabs li,
		html.cmx-dark-mode body.post-php .tagsdiv .category-tabs,
		html.cmx-dark-mode body.post-new-php .tagsdiv .category-tabs,
		html.cmx-dark-mode body.post-php .tagsdiv .category-tabs li,
		html.cmx-dark-mode body.post-new-php .tagsdiv .category-tabs li {
			background: transparent !important;
			border-color: transparent !important;
		}
		html.cmx-dark-mode body.post-php .categorydiv .category-tabs a,
		html.cmx-dark-mode body.post-new-php .categorydiv .category-tabs a,
		html.cmx-dark-mode body.post-php .tagsdiv .category-tabs a,
		html.cmx-dark-mode body.post-new-php .tagsdiv .category-tabs a {
			display: inline-block !important;
			padding: 4px 7px !important;
			border: 1px solid transparent !important;
			border-radius: 4px !important;
			background: transparent !important;
			color: #bfdbfe !important;
			text-decoration: none !important;
			box-shadow: none !important;
			outline: none !important;
		}
		html.cmx-dark-mode body.post-php .postbox .nav-tab-active,
		html.cmx-dark-mode body.post-new-php .postbox .nav-tab-active,
		html.cmx-dark-mode body.post-php .category-tabs .tabs a,
		html.cmx-dark-mode body.post-new-php .category-tabs .tabs a {
			background: #1e293b !important;
			color: #f8fafc !important;
		}
		html.cmx-dark-mode body.post-php .categorydiv .category-tabs .tabs a,
		html.cmx-dark-mode body.post-new-php .categorydiv .category-tabs .tabs a,
		html.cmx-dark-mode body.post-php .tagsdiv .category-tabs .tabs a,
		html.cmx-dark-mode body.post-new-php .tagsdiv .category-tabs .tabs a,
		html.cmx-dark-mode body.post-php .categorydiv .category-tabs a[aria-current="true"],
		html.cmx-dark-mode body.post-new-php .categorydiv .category-tabs a[aria-current="true"],
		html.cmx-dark-mode body.post-php .tagsdiv .category-tabs a[aria-current="true"],
		html.cmx-dark-mode body.post-new-php .tagsdiv .category-tabs a[aria-current="true"] {
			background: #1e293b !important;
			border-color: #64748b !important;
			color: #fff !important;
			box-shadow: none !important;
		}
		html.cmx-dark-mode body.post-php .category-tabs a:hover,
		html.cmx-dark-mode body.post-new-php .category-tabs a:hover,
		html.cmx-dark-mode body.post-php .category-tabs a:focus,
		html.cmx-dark-mode body.post-new-php .category-tabs a:focus,
		html.cmx-dark-mode body.post-php .category-tabs a:active,
		html.cmx-dark-mode body.post-new-php .category-tabs a:active,
		html.cmx-dark-mode body.post-php .category-tabs .tabs a:focus,
		html.cmx-dark-mode body.post-new-php .category-tabs .tabs a:focus {
			background: #243044 !important;
			color: #fff !important;
			box-shadow: 0 0 0 1px #60a5fa !important;
			outline: 0 !important;
		}
		html.cmx-dark-mode body.post-php .categorydiv .category-tabs a:hover,
		html.cmx-dark-mode body.post-new-php .categorydiv .category-tabs a:hover,
		html.cmx-dark-mode body.post-php .categorydiv .category-tabs a:focus,
		html.cmx-dark-mode body.post-new-php .categorydiv .category-tabs a:focus,
		html.cmx-dark-mode body.post-php .tagsdiv .category-tabs a:hover,
		html.cmx-dark-mode body.post-new-php .tagsdiv .category-tabs a:hover,
		html.cmx-dark-mode body.post-php .tagsdiv .category-tabs a:focus,
		html.cmx-dark-mode body.post-new-php .tagsdiv .category-tabs a:focus {
			background: #243044 !important;
			border-color: #93c5fd !important;
			color: #fff !important;
			box-shadow: 0 0 0 1px rgba(147,197,253,.45) !important;
			outline: none !important;
		}
		html.cmx-dark-mode body.post-php .categorydiv div.tabs-panel,
		html.cmx-dark-mode body.post-new-php .categorydiv div.tabs-panel,
		html.cmx-dark-mode body.post-php .tagsdiv .jaxtag,
		html.cmx-dark-mode body.post-new-php .tagsdiv .jaxtag {
			background: #172033 !important;
			border-color: #334155 !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.post-php .categorydiv .tabs-panel a,
		html.cmx-dark-mode body.post-new-php .categorydiv .tabs-panel a,
		html.cmx-dark-mode body.post-php .tagsdiv .jaxtag a,
		html.cmx-dark-mode body.post-new-php .tagsdiv .jaxtag a,
		html.cmx-dark-mode body.post-php .category-add a,
		html.cmx-dark-mode body.post-new-php .category-add a,
		html.cmx-dark-mode body.post-php .taxonomy-add-new,
		html.cmx-dark-mode body.post-new-php .taxonomy-add-new,
		html.cmx-dark-mode body.post-php .category-add-toggle,
		html.cmx-dark-mode body.post-new-php .category-add-toggle {
			background: transparent !important;
			color: #bfdbfe !important;
			box-shadow: none !important;
			outline-color: #60a5fa !important;
		}
		html.cmx-dark-mode body.post-php .categorydiv .tabs-panel a:hover,
		html.cmx-dark-mode body.post-new-php .categorydiv .tabs-panel a:hover,
		html.cmx-dark-mode body.post-php .categorydiv .tabs-panel a:focus,
		html.cmx-dark-mode body.post-new-php .categorydiv .tabs-panel a:focus,
		html.cmx-dark-mode body.post-php .tagsdiv .jaxtag a:hover,
		html.cmx-dark-mode body.post-new-php .tagsdiv .jaxtag a:hover,
		html.cmx-dark-mode body.post-php .tagsdiv .jaxtag a:focus,
		html.cmx-dark-mode body.post-new-php .tagsdiv .jaxtag a:focus,
		html.cmx-dark-mode body.post-php .category-add a:hover,
		html.cmx-dark-mode body.post-new-php .category-add a:hover,
		html.cmx-dark-mode body.post-php .category-add a:focus,
		html.cmx-dark-mode body.post-new-php .category-add a:focus,
		html.cmx-dark-mode body.post-php .taxonomy-add-new:hover,
		html.cmx-dark-mode body.post-new-php .taxonomy-add-new:hover,
		html.cmx-dark-mode body.post-php .taxonomy-add-new:focus,
		html.cmx-dark-mode body.post-new-php .taxonomy-add-new:focus,
		html.cmx-dark-mode body.post-php .category-add-toggle:hover,
		html.cmx-dark-mode body.post-new-php .category-add-toggle:hover,
		html.cmx-dark-mode body.post-php .category-add-toggle:focus,
		html.cmx-dark-mode body.post-new-php .category-add-toggle:focus {
			background: transparent !important;
			color: #fff !important;
			box-shadow: none !important;
			outline: 1px solid #60a5fa !important;
			outline-offset: 2px !important;
		}
		html.cmx-dark-mode body.post-php #poststuff .handlediv,
		html.cmx-dark-mode body.post-new-php #poststuff .handlediv,
		html.cmx-dark-mode body.post-php #poststuff .handle-order-higher,
		html.cmx-dark-mode body.post-new-php #poststuff .handle-order-higher,
		html.cmx-dark-mode body.post-php #poststuff .handle-order-lower,
		html.cmx-dark-mode body.post-new-php #poststuff .handle-order-lower,
		html.cmx-dark-mode body.post-php #poststuff .toggle-indicator,
		html.cmx-dark-mode body.post-new-php #poststuff .toggle-indicator {
			color: #cbd5e1 !important;
		}
		html.cmx-dark-mode body.post-php #poststuff .handlediv:hover,
		html.cmx-dark-mode body.post-new-php #poststuff .handlediv:hover,
		html.cmx-dark-mode body.post-php #poststuff .handle-order-higher:hover,
		html.cmx-dark-mode body.post-new-php #poststuff .handle-order-higher:hover,
		html.cmx-dark-mode body.post-php #poststuff .handle-order-lower:hover,
		html.cmx-dark-mode body.post-new-php #poststuff .handle-order-lower:hover {
			color: #fff !important;
		}
		html.cmx-dark-mode body.post-php #post-status-info,
		html.cmx-dark-mode body.post-new-php #post-status-info,
		html.cmx-dark-mode body.post-php .wp-editor-container,
		html.cmx-dark-mode body.post-new-php .wp-editor-container,
		html.cmx-dark-mode body.post-php .quicktags-toolbar,
		html.cmx-dark-mode body.post-new-php .quicktags-toolbar,
		html.cmx-dark-mode body.post-php .mce-panel,
		html.cmx-dark-mode body.post-new-php .mce-panel,
		html.cmx-dark-mode body.post-php .mce-toolbar-grp,
		html.cmx-dark-mode body.post-new-php .mce-toolbar-grp {
			background: #172033 !important;
			border-color: #334155 !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.post-php .wp-switch-editor,
		html.cmx-dark-mode body.post-new-php .wp-switch-editor,
		html.cmx-dark-mode body.post-php .mce-btn,
		html.cmx-dark-mode body.post-new-php .mce-btn,
		html.cmx-dark-mode body.post-php .quicktags-toolbar input,
		html.cmx-dark-mode body.post-new-php .quicktags-toolbar input {
			background: #111827 !important;
			border-color: #475569 !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.post-php .wp-switch-editor.switch-tmce,
		html.cmx-dark-mode body.post-new-php .wp-switch-editor.switch-tmce,
		html.cmx-dark-mode body.post-php .wp-switch-editor.switch-html,
		html.cmx-dark-mode body.post-new-php .wp-switch-editor.switch-html {
			color: #dbeafe !important;
		}
		html.cmx-dark-mode body.post-php .wp-editor-tabs,
		html.cmx-dark-mode body.post-new-php .wp-editor-tabs,
		html.cmx-dark-mode body.post-php #wp-content-editor-tools,
		html.cmx-dark-mode body.post-new-php #wp-content-editor-tools {
			background: #111827 !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.post-php .mce-ico,
		html.cmx-dark-mode body.post-new-php .mce-ico,
		html.cmx-dark-mode body.post-php .mce-menubtn button,
		html.cmx-dark-mode body.post-new-php .mce-menubtn button,
		html.cmx-dark-mode body.post-php .mce-btn button,
		html.cmx-dark-mode body.post-new-php .mce-btn button {
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.post-php .mce-btn:hover,
		html.cmx-dark-mode body.post-new-php .mce-btn:hover,
		html.cmx-dark-mode body.post-php .mce-btn:focus,
		html.cmx-dark-mode body.post-new-php .mce-btn:focus {
			background: #1e293b !important;
			border-color: #64748b !important;
		}
		html.cmx-dark-mode body.post-php #major-publishing-actions,
		html.cmx-dark-mode body.post-new-php #major-publishing-actions,
		html.cmx-dark-mode body.post-php #misc-publishing-actions,
		html.cmx-dark-mode body.post-new-php #misc-publishing-actions,
		html.cmx-dark-mode body.post-php #minor-publishing-actions,
		html.cmx-dark-mode body.post-new-php #minor-publishing-actions {
			background: #172033 !important;
			border-color: #334155 !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.wp-admin .widefat th,
		html.cmx-dark-mode body.wp-admin .widefat td,
		html.cmx-dark-mode body.wp-admin .wp-list-table th,
		html.cmx-dark-mode body.wp-admin .wp-list-table td {
			background: transparent !important;
			border-color: #334155 !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.wp-admin .striped > tbody > :nth-child(odd),
		html.cmx-dark-mode body.wp-admin ul.striped > :nth-child(odd),
		html.cmx-dark-mode body.wp-admin .alternate {
			background: #131c2a !important;
		}
		html.cmx-dark-mode body.wp-admin input[type="text"],
		html.cmx-dark-mode body.wp-admin input[type="email"],
		html.cmx-dark-mode body.wp-admin input[type="number"],
		html.cmx-dark-mode body.wp-admin input[type="search"],
		html.cmx-dark-mode body.wp-admin input[type="password"],
		html.cmx-dark-mode body.wp-admin input[type="url"],
		html.cmx-dark-mode body.wp-admin select,
		html.cmx-dark-mode body.wp-admin textarea {
			background: #0f172a !important;
			border-color: #475569 !important;
			color: #f8fafc !important;
		}
		html.cmx-dark-mode body.wp-admin input::placeholder,
		html.cmx-dark-mode body.wp-admin textarea::placeholder {
			color: #94a3b8 !important;
		}
		html.cmx-dark-mode body.wp-admin .tablenav,
		html.cmx-dark-mode body.wp-admin .subsubsub,
		html.cmx-dark-mode body.wp-admin .search-box {
			color: #cbd5e1 !important;
		}
		html.cmx-dark-mode body.wp-admin .wrap h2.nav-tab-wrapper,
		html.cmx-dark-mode body.wp-admin .wrap .nav-tab-wrapper {
			background: transparent !important;
			border-color: #334155 !important;
		}
		html.cmx-dark-mode body.wp-admin .wrap h2.nav-tab-wrapper .nav-tab,
		html.cmx-dark-mode body.wp-admin .wrap .nav-tab-wrapper .nav-tab {
			background: #101826 !important;
			border-color: #475569 !important;
			color: #bfdbfe !important;
			box-shadow: none !important;
		}
		html.cmx-dark-mode body.wp-admin .wrap h2.nav-tab-wrapper .nav-tab:hover,
		html.cmx-dark-mode body.wp-admin .wrap .nav-tab-wrapper .nav-tab:hover,
		html.cmx-dark-mode body.wp-admin .wrap h2.nav-tab-wrapper .nav-tab:focus,
		html.cmx-dark-mode body.wp-admin .wrap .nav-tab-wrapper .nav-tab:focus {
			background: #243044 !important;
			border-color: #93c5fd !important;
			color: #fff !important;
			box-shadow: 0 0 0 1px rgba(147,197,253,.45) !important;
			outline: none !important;
		}
		html.cmx-dark-mode body.wp-admin .wrap h2.nav-tab-wrapper .nav-tab-active,
		html.cmx-dark-mode body.wp-admin .wrap .nav-tab-wrapper .nav-tab-active,
		html.cmx-dark-mode body.wp-admin .wrap h2.nav-tab-wrapper .nav-tab-active:hover,
		html.cmx-dark-mode body.wp-admin .wrap .nav-tab-wrapper .nav-tab-active:hover,
		html.cmx-dark-mode body.wp-admin .wrap h2.nav-tab-wrapper .nav-tab-active:focus,
		html.cmx-dark-mode body.wp-admin .wrap .nav-tab-wrapper .nav-tab-active:focus {
			background: #1e293b !important;
			border-color: #64748b !important;
			border-bottom-color: #1e293b !important;
			color: #fff !important;
		}
		html.cmx-dark-mode body.wp-admin .wrap .subsubsub {
			background: transparent !important;
			color: #94a3b8 !important;
		}
		html.cmx-dark-mode body.wp-admin .wrap .subsubsub a {
			background: transparent !important;
			color: #bfdbfe !important;
			box-shadow: none !important;
			text-decoration: none !important;
		}
		html.cmx-dark-mode body.wp-admin .wrap .subsubsub a:hover,
		html.cmx-dark-mode body.wp-admin .wrap .subsubsub a:focus {
			background: #243044 !important;
			color: #fff !important;
			box-shadow: 0 0 0 1px rgba(147,197,253,.45) !important;
			outline: none !important;
		}
		html.cmx-dark-mode body.wp-admin .wrap .subsubsub a.current,
		html.cmx-dark-mode body.wp-admin .wrap .subsubsub a.current:hover,
		html.cmx-dark-mode body.wp-admin .wrap .subsubsub a.current:focus {
			background: #1e293b !important;
			color: #fff !important;
			box-shadow: inset 0 0 0 1px #64748b !important;
		}
		html.cmx-dark-mode body.wp-admin #screen-meta,
		html.cmx-dark-mode body.wp-admin #screen-options-wrap,
		html.cmx-dark-mode body.wp-admin #contextual-help-wrap {
			background: #172033 !important;
			border-color: #334155 !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.wp-admin #screen-meta label,
		html.cmx-dark-mode body.wp-admin #screen-meta legend,
		html.cmx-dark-mode body.wp-admin #screen-meta h5,
		html.cmx-dark-mode body.wp-admin #screen-meta h6,
		html.cmx-dark-mode body.wp-admin #screen-meta p,
		html.cmx-dark-mode body.wp-admin #screen-meta li,
		html.cmx-dark-mode body.wp-admin #screen-meta span,
		html.cmx-dark-mode body.wp-admin #screen-meta .screen-reader-text {
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.wp-admin #screen-meta-links .show-settings {
			background: #1e293b !important;
			border-color: #475569 !important;
			color: #e5e7eb !important;
			box-shadow: none !important;
		}
		html.cmx-dark-mode body.wp-admin #screen-meta-links .show-settings:hover,
		html.cmx-dark-mode body.wp-admin #screen-meta-links .show-settings:focus,
		html.cmx-dark-mode body.wp-admin #screen-meta-links .show-settings[aria-expanded="true"] {
			background: #243044 !important;
			border-color: #93c5fd !important;
			color: #fff !important;
			box-shadow: 0 0 0 1px rgba(147,197,253,.45) !important;
			outline: none !important;
		}
		html.cmx-dark-mode body.wp-admin #screen-options-wrap fieldset,
		html.cmx-dark-mode body.wp-admin #screen-options-wrap .metabox-prefs,
		html.cmx-dark-mode body.wp-admin #contextual-help-back,
		html.cmx-dark-mode body.wp-admin #contextual-help-columns,
		html.cmx-dark-mode body.wp-admin #contextual-help-tabs,
		html.cmx-dark-mode body.wp-admin #contextual-help-sidebar {
			background: #172033 !important;
			border-color: #334155 !important;
			color: #e5e7eb !important;
		}
		html.cmx-dark-mode body.wp-admin #contextual-help-tabs .active,
		html.cmx-dark-mode body.wp-admin #contextual-help-tabs .active a {
			background: #1e293b !important;
			border-color: #475569 !important;
			color: #fff !important;
		}
		html.cmx-dark-mode body.wp-admin #contextual-help-tabs a,
		html.cmx-dark-mode body.wp-admin #contextual-help-sidebar a,
		html.cmx-dark-mode body.wp-admin #contextual-help-wrap a,
		html.cmx-dark-mode body.wp-admin #contextual-help-columns a,
		html.cmx-dark-mode body.wp-admin #contextual-help-wrap .contextual-help-sidebar a {
			color: #bfdbfe !important;
			background: transparent !important;
			box-shadow: none !important;
			text-decoration: underline !important;
			text-underline-offset: 2px !important;
		}
		html.cmx-dark-mode body.wp-admin #contextual-help-tabs a:hover,
		html.cmx-dark-mode body.wp-admin #contextual-help-tabs a:focus,
		html.cmx-dark-mode body.wp-admin #contextual-help-sidebar a:hover,
		html.cmx-dark-mode body.wp-admin #contextual-help-sidebar a:focus,
		html.cmx-dark-mode body.wp-admin #contextual-help-wrap a:hover,
		html.cmx-dark-mode body.wp-admin #contextual-help-wrap a:focus,
		html.cmx-dark-mode body.wp-admin #contextual-help-columns a:hover,
		html.cmx-dark-mode body.wp-admin #contextual-help-columns a:focus,
		html.cmx-dark-mode body.wp-admin #contextual-help-wrap .contextual-help-sidebar a:hover,
		html.cmx-dark-mode body.wp-admin #contextual-help-wrap .contextual-help-sidebar a:focus {
			color: #fff !important;
			background: #243044 !important;
			box-shadow: 0 0 0 1px rgba(147,197,253,.45) !important;
			outline: none !important;
			text-decoration: none !important;
		}
		html.cmx-dark-mode body.wp-admin #contextual-help-tabs .active a,
		html.cmx-dark-mode body.wp-admin #contextual-help-tabs .active a:hover,
		html.cmx-dark-mode body.wp-admin #contextual-help-tabs .active a:focus {
			color: #fff !important;
			text-decoration: none !important;
		}
		html.cmx-dark-mode body.wp-admin a {
			color: #93c5fd;
		}
		html.cmx-dark-mode body.wp-admin a:hover,
		html.cmx-dark-mode body.wp-admin a:focus {
			color: #bfdbfe;
		}
		html.cmx-dark-mode body:not(.wp-admin) {
			background: #0f172a;
			color: #e5e7eb;
		}
		html.cmx-dark-mode #wpadminbar #wp-admin-bar-cmx-dark-mode-toggle > .ab-item {
			color: #ffeb3b !important;
		}
	</style>
	<?php
}
add_action('admin_head', __NAMESPACE__ . '\\cmx_adminbar_dark_mode_head', 1);
add_action('wp_head', __NAMESPACE__ . '\\cmx_adminbar_dark_mode_head', 1);

function cmx_adminbar_dark_mode_footer(): void {
	if (!\is_user_logged_in() || !\is_admin_bar_showing()) {
		return;
	}
	?>
	<script>
	(function(){
		var storageKey = 'cmxDarkMode';
		var root = document.documentElement;
		var body = document.body;
		var button = document.querySelector('#wp-admin-bar-cmx-dark-mode-toggle > .ab-item');

		function isActive() {
			return root.classList.contains('cmx-dark-mode');
		}

		function eachTinyMceEditor(callback) {
			if (!window.tinymce || !window.tinymce.editors) {
				return;
			}
			var editors = window.tinymce.editors;
			if (typeof editors.forEach === 'function') {
				editors.forEach(callback);
				return;
			}
			Object.keys(editors).forEach(function(key) {
				callback(editors[key]);
			});
		}

		function applyTinyMceDarkMode(active) {
			eachTinyMceEditor(function(editor) {
				if (!editor || typeof editor.getDoc !== 'function') {
					return;
				}
				var doc = null;
				try {
					doc = editor.getDoc();
				} catch (error) {
					return;
				}
				if (!doc || !doc.head) {
					return;
				}
				var style = doc.getElementById('cmx-dark-mode-editor-style');
				if (!active) {
					if (style) {
						style.parentNode.removeChild(style);
					}
					return;
				}
				if (!style) {
					style = doc.createElement('style');
					style.id = 'cmx-dark-mode-editor-style';
					doc.head.appendChild(style);
				}
				style.textContent = [
					'html,body{background:#0f172a!important;color:#e5e7eb!important;}',
					'body{caret-color:#fff!important;}',
					'a{color:#93c5fd!important;}',
					'p,div,span,li,td,th,h1,h2,h3,h4,h5,h6{color:inherit!important;}',
					'table,td,th{border-color:#334155!important;}',
					'blockquote{border-left-color:#475569!important;color:#cbd5e1!important;}',
					'::-moz-selection{background:#2563eb!important;color:#fff!important;}',
					'::selection{background:#2563eb!important;color:#fff!important;}'
				].join('');
			});
		}

		function scheduleTinyMceDarkMode(active) {
			applyTinyMceDarkMode(active);
			window.setTimeout(function() { applyTinyMceDarkMode(active); }, 150);
			window.setTimeout(function() { applyTinyMceDarkMode(active); }, 600);
			window.setTimeout(function() { applyTinyMceDarkMode(active); }, 1400);
		}

		function setActive(active) {
			root.classList.toggle('cmx-dark-mode', active);
			if (body) {
				body.classList.toggle('cmx-dark-mode', active);
			}
			scheduleTinyMceDarkMode(active);
			try {
				if (window.localStorage) {
					window.localStorage.setItem(storageKey, active ? '1' : '0');
				}
			} catch (error) {}
			if (button) {
				button.setAttribute('aria-pressed', active ? 'true' : 'false');
				button.setAttribute('title', active ? 'Light Mode aktivieren' : 'Dark Mode aktivieren');
			}
			try {
				window.dispatchEvent(new CustomEvent('cmx-dark-mode-change', { detail: { active: active } }));
			} catch (error) {}
		}

		setActive(isActive());
		try {
			if (window.tinymce && typeof window.tinymce.on === 'function' && !window.tinymce.__cmxDarkModeBound) {
				window.tinymce.__cmxDarkModeBound = true;
				window.tinymce.on('AddEditor', function() {
					scheduleTinyMceDarkMode(isActive());
				});
			}
		} catch (error) {}
		if (!button) {
			return;
		}
		button.setAttribute('role', 'button');
		button.addEventListener('click', function(event) {
			event.preventDefault();
			setActive(!isActive());
		});
	})();
	</script>
	<?php
}
add_action('admin_footer', __NAMESPACE__ . '\\cmx_adminbar_dark_mode_footer', 99);
add_action('wp_footer', __NAMESPACE__ . '\\cmx_adminbar_dark_mode_footer', 99);

add_action('admin_head', function (): void {
	if (!\is_admin() || \wp_get_current_user()->user_login === 'cloudmeister') {
		return;
	}
	?>
	<style>
		#wpadminbar #wp-admin-bar-my-account.with-avatar > .ab-sub-wrapper {
			min-width: 140px;
		}
		#wpadminbar #wp-admin-bar-my-account.with-avatar > #wp-admin-bar-user-actions > li {
			margin-left: 0;
		}
		#wpadminbar #wp-admin-bar-my-account.with-avatar > #wp-admin-bar-user-actions > li > .ab-item {
			padding-left: 12px;
			text-align: left;
		}
	</style>
	<?php
});


add_action('admin_init', function() {

	if (\wp_get_current_user()->user_login === 'cloudmeister') {
		return;
	}

	global $pagenow;

	$current_user_id = (int) \get_current_user_id();
	$requested_user_id = isset($_GET['user_id']) ? (int) \wp_unslash($_GET['user_id']) : 0;
	$is_own_user_edit = $pagenow === 'user-edit.php' && $current_user_id > 0 && $requested_user_id === $current_user_id;

	if ($pagenow === 'profile.php' || $is_own_user_edit) {
		\wp_safe_redirect(\admin_url());
		exit;
	}

});
