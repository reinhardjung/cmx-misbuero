<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit;

/* ------------------------------------------------------------
 * KONSTANTEN – MÜSSEN HIER DEFINIERT SEIN
 * ------------------------------------------------------------ */
const CMX_SETTINGS_SLUG  = 'cmx-einstellungen';
const CMX_SETTINGS_MAIN  = 'cmx_einstellungen';
const CMX_SETTINGS_BELEG = 'cmx_belege';

if (!\function_exists(__NAMESPACE__ . '\\cmx_settings_is_cloudmeister_switched_user')) {
	function cmx_settings_is_cloudmeister_switched_user(): bool {
		if (\function_exists(__NAMESPACE__ . '\\cmx_user_switch_is_cloudmeister_switched')) {
			return cmx_user_switch_is_cloudmeister_switched();
		}

		$current_user = \wp_get_current_user();
		if (!$current_user instanceof \WP_User || !$current_user->exists()) {
			return false;
		}

		if (\strtolower((string) $current_user->user_login) === 'cloudmeister') {
			return false;
		}

		$current_user_id = (int) $current_user->ID;
		$original_user_id = isset($_COOKIE['cmx_original_user']) ? (int) $_COOKIE['cmx_original_user'] : 0;
		$signature = isset($_COOKIE['cmx_original_user_sig']) ? (string) $_COOKIE['cmx_original_user_sig'] : '';
		if ($original_user_id <= 0 || $original_user_id === $current_user_id || $signature === '') {
			return false;
		}

		$expected = \hash_hmac('sha256', $original_user_id . '|' . $current_user_id, \wp_salt('auth'));
		if (!\hash_equals($expected, $signature)) {
			return false;
		}

		$original_user = \get_user_by('id', $original_user_id);
		return ($original_user instanceof \WP_User)
			&& $original_user->exists()
			&& \strtolower((string) $original_user->user_login) === 'cloudmeister';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_settings_page_capability')) {
	function cmx_settings_page_capability(): string {
		return cmx_settings_is_cloudmeister_switched_user() ? 'read' : 'manage_options';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_settings_current_user_can_access')) {
	function cmx_settings_current_user_can_access(): bool {
		return \current_user_can('manage_options') || cmx_settings_is_cloudmeister_switched_user();
	}
}

/* ------------------------------------------------------------
 * EDITOR DEFAULT: AUF EINSTELLUNGSSEITEN IMMER VISUELL
 * ------------------------------------------------------------ */
\add_filter('wp_default_editor', function (string $default): string {
	if (!\is_admin()) {
		return $default;
	}
	$page = isset($_GET['page']) ? \sanitize_key((string) \wp_unslash($_GET['page'])) : '';
	if ($page === CMX_SETTINGS_SLUG) {
		return 'tinymce';
	}
	return $default;
}, 20);

/* ------------------------------------------------------------
 * INCLUDE FILES – jetzt korrekt, da Konstanten bereis existieren
 * ------------------------------------------------------------ */
require_once 'allgemein.php';
require_once 'vorgaben.php';
require_once 'banken.php';
require_once 'kontakte.php';
require_once 'belege.php';
require_once 'woocommerce.php';
require_once 'email.php';
require_once 'carent.php';
require_once 'buchungen.php';
require_once 'security.php';
require_once 'system.php';
require_once 'erweitert.php';
require_once 'support.php';
require_once 'adminbar.php';

/* ------------------------------------------------------------
 * ADMIN MENU
 * ------------------------------------------------------------ */
add_action('admin_menu', function() {
	add_menu_page(
		'Einstellungen',
		'Einstellungen',
		cmx_settings_page_capability(),
		CMX_SETTINGS_SLUG,
		__NAMESPACE__ . '\\cmx_render_settings_page',
		'dashicons-admin-generic',
		150
	);

	add_submenu_page(
		CMX_SETTINGS_SLUG,
		'Einstellungen',
		'Einstellungen',
		cmx_settings_page_capability(),
		CMX_SETTINGS_SLUG,
		__NAMESPACE__ . '\\cmx_render_settings_page'
	);
});

\add_action('admin_menu', function (): void {
	global $submenu;
	if (empty($submenu[CMX_SETTINGS_SLUG]) || !\is_array($submenu[CMX_SETTINGS_SLUG])) {
		return;
	}

	$bank_slug = \function_exists(__NAMESPACE__ . '\\cmx_bank_types_taxonomy')
		? 'edit-tags.php?taxonomy=' . cmx_bank_types_taxonomy() . '&post_type=dokumente'
		: 'edit-tags.php?taxonomy=einstellungen_banktypen&post_type=dokumente';
	$priority = [
		CMX_SETTINGS_SLUG,
		$bank_slug,
		'cmx-layout-export',
	];

	$current = $submenu[CMX_SETTINGS_SLUG];
	$ordered = [];
	$used = [];

	foreach ($priority as $slug) {
		foreach ($current as $item) {
			if (!isset($item[2]) || (string) $item[2] !== (string) $slug) {
				continue;
			}
			$ordered[] = $item;
			$used[] = (string) $slug;
			break;
		}
	}

	foreach ($current as $item) {
		$item_slug = isset($item[2]) ? (string) $item[2] : '';
		if ($item_slug !== '' && \in_array($item_slug, $used, true)) {
			continue;
		}
		$ordered[] = $item;
	}

	$submenu[CMX_SETTINGS_SLUG] = $ordered;
}, 999);

\add_filter('option_page_capability_' . CMX_SETTINGS_MAIN, static function (string $cap): string {
	return cmx_settings_page_capability();
});

\add_filter('option_page_capability_' . CMX_SETTINGS_BELEG, static function (string $cap): string {
	return cmx_settings_page_capability();
});

add_action('all_admin_notices', function (): void {
	$page = isset($_GET['page']) ? \sanitize_key((string) \wp_unslash($_GET['page'])) : '';
	if ($page !== CMX_SETTINGS_SLUG) {
		return;
	}

	$has_standard_bank = false;
	if (\function_exists(__NAMESPACE__ . '\\cmx_get_active_bank')) {
		$active_bank = cmx_get_active_bank();
		$has_standard_bank = \is_array($active_bank) && !empty($active_bank['key']);
	}
	if (!$has_standard_bank) {
		$options = (array) \get_option(CMX_SETTINGS_MAIN, []);
		foreach (['rev_enabled', 'zkb_enabled', 'ubs_enabled', 'migros_enabled', 'eisen_enabled'] as $bank_key) {
			if (!empty($options[$bank_key])) {
				$has_standard_bank = true;
				break;
			}
		}
	}
	if ($has_standard_bank) {
		return;
	}

	$new_bank_url = \admin_url('admin.php?page=cmx-einstellungen&tab=banken');

	echo '<div class="notice notice-warning"><p><strong>Hinweis:</strong> Bitte wähle Deine <strong>Hausbank</strong> in den Einstellungen aus, gebe alle Daten an <strong>und</strong> markiere sie als Standardbank. <a href="' . \esc_url($new_bank_url) . '" class="button button-secondary" style="margin-left:8px;">Hausbank auswählen</a></p></div>';
});

/* ------------------------------------------------------------
 * TAB-LISTEN
 * ------------------------------------------------------------ */
function cmx_get_tabs(): array {
	$tabs = [
		'general'     => 'Allgemein',
		'vorgaben'    => 'Vorgaben',
		'banken'      => 'Zahlungen',
		'belege'      => 'Belege',
		'woocommerce' => 'WooCommerce',
		'email'       => 'E-Mails',
		'website'     => 'Website',
	];

	if (\function_exists(__NAMESPACE__ . '\\cmx_carent_settings_is_enabled') && cmx_carent_settings_is_enabled()) {
		$tabs['carent'] = 'Carent';
	}

	$tabs['buchungen'] = 'Buchungen';
	$tabs['system'] = 'System';
	$tabs['support'] = 'Support';

	return $tabs;
}

function cmx_get_subtabs(string $tab): array {
	if ($tab === 'vorgaben') {
		return [
			'allgemein' => 'Allgemein',
			'artikel'   => 'Artikel',
			'belege'    => 'Belege',
			'projekte'  => 'Projekte',
			'email'     => 'E-Mail',
		];
	}

	if ($tab === 'belege') {
		return [
			'offerte'      => 'Offerte',
			'gutschrift'   => 'Gutschrift',
			'lieferschein' => 'Lieferschein',
			'quittung'     => 'Quittung',
			'rechnung'     => 'Rechnung',
			'mahnung'      => 'Mahnung',
		];
	}

	if ($tab === 'email') {
		return [
			'belege'  => 'Belege',
			'kontakt' => 'Kontakt',
			'clients' => 'Clients',
		];
	}

	if ($tab === 'system') {
		return [
			'general' => 'Allgemein',
			'analytics' => 'Analytics',
			'storage' => 'Speicherplatz',
			'security' => 'Sicherheit',
			'backup' => 'Backup',
		];
	}

	return [];
}

/* ------------------------------------------------------------
 * REGISTER SETTINGS (MAIN)
 * ------------------------------------------------------------ */
add_action('admin_init', function() {
	register_setting(
		CMX_SETTINGS_MAIN,
		CMX_SETTINGS_MAIN,
		[
			'type' => 'array',
			'default' => [],
			'sanitize_callback' => function($input) {
				$current = get_option(CMX_SETTINGS_MAIN, []);
				$current = is_array($current) ? $current : [];
				return is_array($input)
					? cmx_settings_merge_option_arrays($current, $input)
					: $current;
			}
		]
	);
});

/* ------------------------------------------------------------
 * REGISTER SETTINGS (BELEGE)
 * ------------------------------------------------------------ */
add_action('admin_init', function() {
	register_setting(
		CMX_SETTINGS_BELEG,
		CMX_SETTINGS_BELEG,
		[
			'type' => 'array',
			'default' => [],
			'sanitize_callback' => function($input) {
				$current = get_option(CMX_SETTINGS_BELEG, []);
				$current = is_array($current) ? $current : [];
				return is_array($input)
					? cmx_settings_merge_option_arrays($current, $input)
					: $current;
			}
		]
	);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_settings_array_is_list')) {
	function cmx_settings_array_is_list(array $value): bool {
		if ($value === []) {
			return false;
		}

		return \array_keys($value) === \range(0, \count($value) - 1);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_settings_merge_option_arrays')) {
	function cmx_settings_merge_option_arrays(array $current, array $incoming): array {
		foreach ($incoming as $key => $value) {
			if (
				\is_array($value)
				&& isset($current[$key])
				&& \is_array($current[$key])
				&& !cmx_settings_array_is_list($value)
				&& !cmx_settings_array_is_list($current[$key])
			) {
				$current[$key] = cmx_settings_merge_option_arrays($current[$key], $value);
				continue;
			}

			$current[$key] = $value;
		}

		return $current;
	}
}

/* ------------------------------------------------------------
 * FELDER
 * ------------------------------------------------------------ */
function cmx_get_option(string $key, $default = '') {
	$arr = get_option(CMX_SETTINGS_MAIN, []);
	return $arr[$key] ?? $default;
}

function cmx_field_text(array $args): void {
	$key = $args['key'];
	$val = cmx_get_option($key);
	$ph  = $args['placeholder'] ?? '';

	echo '<input type="text" class="regular-text"
		id="'.esc_attr($key).'"
		name="'.CMX_SETTINGS_MAIN.'['.$key.']"
		value="'.esc_attr($val).'"
		placeholder="'.esc_attr($ph).'">';
}

function cmx_field_checkbox(array $args): void {

	$key   = $args['key'];
	$label = $args['label'] ?? '';

	$options = get_option(CMX_SETTINGS_MAIN, []);
	$val     = (!empty($options[$key]));

	echo '<label>';
	echo '<input type="hidden" name="'.CMX_SETTINGS_MAIN.'['.$key.']" value="0">';
	echo '<input type="checkbox"
		name="'.CMX_SETTINGS_MAIN.'['.$key.']"
		value="1" '.checked($val, true, false).'> ';
	echo esc_html($label);
	echo '</label>';
}

/* ------------------------------------------------------------
 * SETTINGS PAGE RENDERING
 * ------------------------------------------------------------ */
function cmx_render_settings_page(): void {
	if (!cmx_settings_current_user_can_access()) {
		\wp_die('Keine Berechtigung.');
	}

	$tabs = cmx_get_tabs();
	$tab  = $_GET['tab'] ?? 'general';
	if ($tab === 'security') {
		$tab = 'system';
		$_GET['sub'] = 'security';
	}
	if (!isset($tabs[$tab])) $tab = 'general';

	$subtabs = cmx_get_subtabs($tab);
	$sub     = $_GET['sub'] ?? (array_key_first($subtabs) ?: '');
	if ($sub && !isset($subtabs[$sub])) $sub = array_key_first($subtabs) ?: '';

	$page_id = $sub ? "cmx_tab_{$tab}__{$sub}" : "cmx_tab_{$tab}";

	echo '<div class="wrap"><h1>Einstellungen</h1>';
	$cmx_settings_errors = get_settings_errors(CMX_SETTINGS_MAIN);
	if (!empty($cmx_settings_errors)) {
		settings_errors(CMX_SETTINGS_MAIN);
	} elseif (!empty($_GET['settings-updated'])) {
		echo '<div class="notice notice-success is-dismissible"><p>Einstellungen gespeichert.</p></div>';
	}

	/* Tabs */
	echo '<h2 class="nav-tab-wrapper">';
	foreach ($tabs as $key => $label) {
		echo '<a href="?page=' . CMX_SETTINGS_SLUG . '&tab=' . $key
			.'" class="nav-tab '.($tab === $key ? 'nav-tab-active' : '').'">'
			.$label.'</a>';
	}
	echo '</h2>';

	/* Subtabs */
	if ($subtabs) {
		echo '<ul class="subsubsub">';
		foreach ($subtabs as $k => $label) {
			echo '<li><a href="?page='.CMX_SETTINGS_SLUG.'&tab='.$tab.'&sub='.$k.'"'
			     .' class="'.($k === $sub ? 'current' : '').'">'.$label.'</a></li>';
		}
		echo '</ul><br><br><br><br>';
	}

	echo '<div class="cmx-tabpanel">';

	/* SUPPORT → kein Formular */
	if ($tab === 'support') {
		do_settings_sections($page_id);
		echo '</div></div>';
		return;
	}

	/* BELEGE → eigenes Settings Array */
	if ($tab === 'belege') {
		echo '<form method="post" action="options.php">';
		settings_fields(CMX_SETTINGS_BELEG);
		do_settings_sections($page_id);
		submit_button();
		echo '</form></div></div>';
		return;
	}

	if ($tab === 'system' && $sub === 'backup') {
		do_settings_sections($page_id);
		echo '</div></div>';
		return;
	}

	/* ALLE ANDEREN */
	$form_attrs = 'method="post" action="options.php"';
	if ($tab === 'carent' || $tab === 'website') {
		$form_attrs .= ' enctype="multipart/form-data"';
	}
	echo '<form ' . $form_attrs . '>';
	settings_fields(CMX_SETTINGS_MAIN);
	if ($tab !== 'general' && $tab !== 'system') {
		$openai_key = (string) \get_option('mis_buero_openai_key', '');
		$services_url = (string) \get_option('mis_buero_services_url', 'https://services.misbuero.ch');
		$services_api_key = (string) \get_option('mis_buero_services_api_key', '');
		echo '<input type="hidden" name="mis_buero_openai_key" value="' . \esc_attr($openai_key) . '">';
		echo '<input type="hidden" name="mis_buero_services_url" value="' . \esc_attr($services_url) . '">';
		echo '<input type="hidden" name="mis_buero_services_api_key" value="' . \esc_attr($services_api_key) . '">';
	}
	do_settings_sections($page_id);
	if (!($tab === 'system' && ($sub === 'analytics' || $sub === 'storage'))) {
		submit_button();
	}
	echo '</form>';
	echo '</div></div>';
}

/* ------------------------------------------------------------
 * SANITIZER: MAXIMAL 1 BANK
 * ------------------------------------------------------------ */
add_filter('pre_update_option_' . CMX_SETTINGS_MAIN, function($new, $old) {
	$new = \is_array($new) ? $new : [];
	$normalize_beleg_contact_address_options = static function(array $data): array {
		if (\array_key_exists('belege_kontakt_ansprechpartner_uebernehmen', $data)) {
			$data['belege_kontakt_ansprechpartner_uebernehmen'] = !empty($data['belege_kontakt_ansprechpartner_uebernehmen']) ? '1' : '0';
		}
		if (\array_key_exists('belege_kontakt_land_kennung', $data)) {
			$mode = \sanitize_key((string) $data['belege_kontakt_land_kennung']);
			$data['belege_kontakt_land_kennung'] = \in_array($mode, ['immer', 'ausland', 'nie'], true) ? $mode : 'immer';
		}
		return $data;
	};
	$normalize_due_days = static function(array $data): array {
		if (!\array_key_exists('belege_faelligkeit_tage', $data)) {
			$data['belege_faelligkeit_monatsende'] = !empty($data['belege_faelligkeit_monatsende']) ? '1' : '0';
			return $data;
		}
		$days = (int) $data['belege_faelligkeit_tage'];
		if ($days < 0) {
			$days = 0;
		}
		if ($days > 3650) {
			$days = 3650;
		}
		$data['belege_faelligkeit_tage'] = $days;
		$data['belege_faelligkeit_monatsende'] = !empty($data['belege_faelligkeit_monatsende']) ? '1' : '0';
		return $data;
	};
	$new = $normalize_beleg_contact_address_options($new);
	$new = $normalize_due_days($new);
	$normalize_bank_list = static function(array $data): array {
		if (!\array_key_exists('banken_liste_present', $data) && !\array_key_exists('banken_liste', $data)) {
			return $data;
		}
		$list = $data['banken_liste'] ?? [];
		if (\function_exists(__NAMESPACE__ . '\\cmx_normalize_bank_list')) {
			$data['banken_liste'] = cmx_normalize_bank_list($list);
		} else {
			$data['banken_liste'] = \is_array($list) ? $list : [];
		}
		unset($data['banken_liste_present']);
		return $data;
	};
	$new = $normalize_bank_list($new);

	$bank_keys = [
		'rev_enabled',
		'zkb_enabled',
		'ubs_enabled',
		'migros_enabled',
		'eisen_enabled',
	];

	// Wenn im Request genau eine Bank angehakt wurde, alle anderen deaktivieren.
	$posted = $_POST[CMX_SETTINGS_MAIN] ?? [];
	$requestedActive = null;
	foreach ($bank_keys as $k) {
		if (isset($posted[$k]) && !empty($posted[$k])) {
			$requestedActive = $k;
		}
	}
	if ($requestedActive !== null) {
		foreach ($bank_keys as $k) {
			$new[$k] = ($k === $requestedActive) ? 1 : 0;
		}
		return $new;
	}

	$active = [];
	foreach ($bank_keys as $k) {
		if (!empty($new[$k])) {
			$active[] = $k;
		}
	}

	/* Keine aktiv → bewusst keine Standardbank setzen */
	if (count($active) === 0) {
		return $new;
	}

	/* Mehrere aktiv → nur die letzte bleibt aktiv */
	if (count($active) > 1) {
		$last = end($active);
		foreach ($bank_keys as $k) {
			$new[$k] = ($k === $last) ? 1 : 0;
		}
		return $new;
	}

	return $new;

}, 10, 2);
