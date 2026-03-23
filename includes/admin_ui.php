<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Plugin Name: Mis Büro – Admin Skin
 * Description: Mis Büro Farbdesign für WordPress-Administratoren – Menü-Highlight in Rot/Gelb.
 * Version:     1.2.0
 * Author:      CLOUD Meister
 */


add_action('admin_init', __NAMESPACE__ . '\\register_scheme');
function register_scheme(): void {
	$slug = 'misbuero_admin_red';
	wp_admin_css_color($slug,'Mis Büro',false,['#C9362C', '#A42C24', '#3D3D3D', '#F7F7F7']);
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

add_filter('plugin_row_meta', __NAMESPACE__ . '\\cmx_plugin_row_meta_version', 10, 4);
function cmx_plugin_row_meta_version(array $plugin_meta, string $plugin_file, array $plugin_data, string $status): array {
	if (strpos($plugin_file, 'cmx-misbuero.php') === false) {
		return $plugin_meta;
	}
	$version = isset($plugin_data['Version']) ? trim((string) $plugin_data['Version']) : '';
	if ($version === '') {
		return $plugin_meta;
	}

	$replacement = '<span class="cmx-plugin-version" translate="no">Version ' . esc_html($version) . '</span>';
	$replaced = false;
	foreach ($plugin_meta as $index => $meta) {
		if (!is_string($meta)) {
			continue;
		}
		if (stripos($meta, 'Version ') === 0 || stripos($meta, 'Version&nbsp;') === 0) {
			$plugin_meta[$index] = $replacement;
			$replaced = true;
			break;
		}
	}
	if (!$replaced) {
		array_unshift($plugin_meta, $replacement);
	}

	return $plugin_meta;
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
			'Diese Hilfe wird zentral in <code>includes/admin_ui.php</code> pro CPT gepflegt.',
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
				. '<p>' . \esc_html($overview_intro) . '</p>'
				. cmx_admin_help_html_list($overview_items),
		],
		[
			'id'      => 'cmx-help-' . $post_type . '-workflow',
			'title'   => 'Hinweise',
			'content' => '<p>Hinweise für <strong>' . \esc_html($label) . '</strong> auf dieser Seite:</p>'
				. cmx_admin_help_html_list($workflow_items),
		],
	];

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

function cmx_admin_help_sidebar_for_post_type(string $post_type, \WP_Screen $screen, \WP_Post_Type $post_type_object): string {
	$definitions = cmx_admin_help_tab_definitions();
	$definition = \is_array($definitions[$post_type] ?? null) ? $definitions[$post_type] : [];
	$label = \trim((string) ($post_type_object->labels->singular_name ?? $post_type_object->labels->name ?? $post_type));

	$default_sidebar = '<p><strong>' . \esc_html($label) . '</strong></p>'
		. '<p>Diese Hilfe wird zentral aus <code>includes/admin_ui.php</code> geladen.</p>';

	$sidebar = isset($definition['sidebar']) && \is_string($definition['sidebar']) && \trim($definition['sidebar']) !== ''
		? $definition['sidebar']
		: $default_sidebar;

	return (string) \apply_filters('cmx_admin_help_sidebar', $sidebar, $post_type, $screen, $post_type_object, $definition);
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

	$html = '<ul>';
	foreach ($items as $item) {
		$html .= '<li>' . \esc_html($item) . '</li>';
	}
	$html .= '</ul>';

	return $html;
}

function cmx_admin_help_tab_definitions(): array {
	return [
		'kontakte' => [
			'intro' => 'Hier verwaltest du Firmen, Ansprechpartner, Kommunikation und Zuordnungen.',
			'overview' => [
				'Nutze die Stammdaten für Firma, Form, Handelsregister und Kundennummer.',
				'Die Reihenfolge in Kommunikation bestimmt den primären Kontakt für Listen und Exporte.',
				'Telefon-, E-Mail- und weitere Typen werden über die zugehörigen Taxonomien gepflegt.',
			],
			'post' => [
				'Pflege Stammdaten, Kommunikation, Adressen, Bilder und interne Notizen direkt im Datensatz.',
				'Wenn der Post-Titel leer bleibt, kann er aus Firma oder Kontaktname ergänzt werden.',
				'Der erste Kommunikationskontakt ist der wichtigste Eintrag für die Übersicht.',
			],
			'edit' => [
				'Filtere Kontakte über Kategorien, Beziehungen, Länder und Geschäftsformen.',
				'Die Admin-Columns-Liste zeigt den primären Kommunikationskontakt aus der ersten Zeile.',
				'Import und Export arbeiten mit der aktuellen flachen Kommunikationsstruktur.',
			],
		],
		'artikel' => [
			'intro' => 'Hier pflegst du Artikelstammdaten, Preise, Lager- und Lieferinformationen.',
			'overview' => [
				'Artikel unterstützen Titel, Editor, Bilder, Lieferanten, Konditionen und QR-Code.',
				'Bilder behalten ihren Originalnamen und erhalten nur bei Konflikten eine laufende Nummer.',
				'Wichtige Klassifizierungen laufen über Marken, Farben, Einheiten, Typen und Kategorien.',
			],
			'post' => [
				'Pflege Stammdaten, Lieferanten, Belegtexte, Konditionen und Status direkt am Artikel.',
				'Der Editor ist reduziert und startet standardmässig im visuellen Modus.',
				'Der Slug wird beim Speichern mit dem Titel synchron gehalten.',
			],
			'edit' => [
				'Nutze die Listenansicht für schnelle Preis-, Lager- und Statuskontrolle.',
				'Import und Export berücksichtigen die bereinigten Metadaten und Bilder.',
			],
		],
		'projekte' => [
			'intro' => 'Hier verwaltest du Projekte, Aufgaben, Status und die zugehörige Zeiterfassung.',
			'overview' => [
				'Ein Projekt bündelt Stammdaten, Kontakte, Aufgaben und externe Zeitdaten.',
				'Die Chrome-Erweiterung für Zeitmessung arbeitet mit diesen Projektdatensätzen.',
				'Status und Aufgaben werden über die Projekt-Taxonomien strukturiert.',
			],
			'post' => [
				'Pflege Projektdetails, Kontakte und Aufgaben direkt auf der Bearbeitungsseite.',
				'Die Projekt-Exports schreiben strukturierte Metadaten in ein reimportierbares Format.',
			],
			'edit' => [
				'Verwende Status und Aufgaben als schnelle Filter in der Übersicht.',
				'Die Listenansicht ist der zentrale Einstieg für Projektkontrolle und Exporte.',
			],
		],
		'belege' => [
			'intro' => 'Hier verwaltest du Offerten, Rechnungen, Gutschriften und weitere Belege.',
			'overview' => [
				'Belege bündeln Kopfdaten, Positionen, MwSt, Konditionen, Summen und PDF-Ausgabe.',
				'ZIP-Exporte enthalten eine importierbare Daten-CSV für den späteren Re-Import.',
				'Wichtige Zuordnungen laufen über Kontakte, Projekte und Vorlagen.',
			],
			'post' => [
				'Erfasse zuerst Kopfdaten und Positionen, danach MwSt, Summen und PDF-relevante Optionen.',
				'Die Metaboxen sind aufeinander abgestimmt; Änderungen wirken direkt auf Vorschau und Ausgabe.',
			],
			'edit' => [
				'Die Belegliste ist der schnellste Weg für Statuskontrolle, Versand und Export.',
				'Beim Import werden technische Metafelder bewusst nicht blind übernommen.',
			],
		],
		'dokumente' => [
			'intro' => 'Hier pflegst du Dokumente, Module, Gültigkeiten und Statusinformationen.',
			'overview' => [
				'Dokumente unterstützen Status, Gültigkeit, Feature-Bild und modulare Zusatzfelder.',
				'Die Struktur ist auf Dokumentation und wiederverwendbare Inhalte ausgelegt.',
			],
		],
		'emails' => [
			'intro' => 'Hier verwaltest du interne E-Mails, Vorlagen und die Mailbox-Zuordnung.',
			'overview' => [
				'Die E-Mail-Datensätze werden für interne Kommunikation und Zuordnungen verwendet.',
				'Admin-Spalten und Clients unterstützen die schnelle Bearbeitung in der Übersicht.',
			],
		],
		'scanner' => [
			'intro' => 'Hier steuerst du Postfach, Scanner-Zuordnung und Mail-Importe.',
			'overview' => [
				'Dieser Bereich dient als Eingang für importierte Dateien und Mails.',
				'Zuordnungen und Importpfade werden direkt in den zugehörigen Modulen verarbeitet.',
			],
		],
	];
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
	if (get_user_option('admin_color', $user_id) !== 'misbuero_admin_red') return;
	?>
	<style id="misbuero-admin-skin">
	body.admin-color-misbuero_admin_red {
		--mb-primary: #A42C24;   /* Rot */
		--mb-primary-dark: #A42C24;
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
	#wpadminbar { background: var(--mb-primary); }
	#wpadminbar .ab-item,
	#wpadminbar a.ab-item { color:#fff; }
	#wpadminbar .ab-item:hover,
	#wpadminbar .ab-item:focus { background: var(--mb-primary-dark); color:#fff; }


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

	/* Papierkorb-Icon statt Text in Listen-Aktionen (alle CPT) */
	/* Nur im Edit-Formular (post.php) – Papierkorb-Icon statt Text im Delete-Button */
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
	/* Classic + Block Editor: nur im Edit-Modus (post.php) alle CPTs */
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

	</style>';
});
