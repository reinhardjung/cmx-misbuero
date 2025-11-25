<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit('Oxytocin!');

require_once 'allgemein.php';
require_once 'banken.php';
require_once 'kontakte.php';
require_once 'belege.php';
require_once 'erweitert.php';
require_once 'support.php';
require_once 'adminbar.php';

/* ------------------------------------------------------------
 * KONSTANTEN
 * ------------------------------------------------------------ */
const CMX_SETTINGS_SLUG = 'cmx-einstellungen';
const CMX_SETTINGS_KEY  = 'cmx_einstellungen'; // ALLE ANDEREN TABS benutzen dieses Array

/* ------------------------------------------------------------
 * ADMIN MENU
 * ------------------------------------------------------------ */
add_action('admin_menu', __NAMESPACE__ . '\\cmx_add_settings_menu');
function cmx_add_settings_menu(): void {
	add_menu_page(
		'Einstellungen',
		'Einstellungen',
		'manage_options',
		CMX_SETTINGS_SLUG,
		__NAMESPACE__ . '\\cmx_render_settings_page',
		'dashicons-admin-generic',
		70
	);
}

/* ------------------------------------------------------------
 * TABS / SUBTABS
 * ------------------------------------------------------------ */
function cmx_get_tabs(): array {
	return [
		'general'  => 'Allgemein',
		'kontakte' => 'Kontakte',
		'banken'   => 'Banken',
		'belege'   => 'Belege',
		'advanced' => 'Erweitert',
		'support'  => 'Support',
	];
}

function cmx_get_subtabs(string $tab): array {

	if ($tab === 'belege') {
		return [
			'angebot'      => 'Angebot',
			'gutschrift'   => 'Gutschrift',
			'lieferschein' => 'Lieferschein',
			'rechnung'     => 'Rechnung',
		];
	}

	if ($tab === 'banken') {
		return [
			'rev'    => 'Revolut',
			'zkb'    => 'ZKB',
			'ubs'    => 'UBS',
			'migros' => 'Migros',
			'eisen'  => 'Raiffeisen',
		];
	}

	if ($tab === 'kontakte') {
		return [
			'ms' => 'Microsoft',
			'oo' => 'Google',
			'ic' => 'iCloud',
		];
	}

	return [];
}

/* ------------------------------------------------------------
 * FELDER FÜR ARRAY-TABS (General, Banken, Kontakte, Advanced)
 * ------------------------------------------------------------ */
add_action('admin_init', function() {
	register_setting(
		CMX_SETTINGS_KEY,
		CMX_SETTINGS_KEY,
		[
			'type'              => 'array',
			'default'           => [],
			'sanitize_callback' => __NAMESPACE__ . '\\cmx_sanitize_settings'
		]
	);
});

/* ------------------------------------------------------------
 * ARRAY-Speicher-Helfer (andere TABS)
 * ------------------------------------------------------------ */
function cmx_get_option(string $key, $default = '') {
	$arr = get_option(CMX_SETTINGS_KEY, []);
	return $arr[$key] ?? $default;
}

function cmx_field_text(array $args): void {
	$key = $args['key'];
	$val = cmx_get_option($key, '');
	$ph  = esc_attr($args['placeholder'] ?? '');
	echo '<input type="text" class="regular-text" name="' . CMX_SETTINGS_KEY . '[' . esc_attr($key) . ']" value="' . esc_attr($val) . '" placeholder="' . $ph . '">';
}

function cmx_field_checkbox(array $args): void {
	$key   = $args['key'];
	$label = $args['label'] ?? '';
	$val   = cmx_get_option($key, 0);

	echo '<label><input type="checkbox" name="' . CMX_SETTINGS_KEY . '[' . esc_attr($key) . ']" value="1" ' . checked(1, $val, false) . '> '
	     . esc_html($label) . '</label>';
}

/* ------------------------------------------------------------
 * SINGLE OPTION TEXTAREA für BELEGE
 * ------------------------------------------------------------ */
function cmx_field_textarea(array $args): void {

	$key   = $args['key'];
	$rows  = intval($args['rows'] ?? 6);
	$ph    = $args['placeholder'] ?? '';

	$value = get_option($key, null);

	// OPTION EXISTIERT NICHT → Default aus INI
	if ($value === null) {
		$display = $ph;
	}
	// OPTION IST LEER → Default aus INI
	elseif ($value === '') {
		$display = $ph;
	}
	// OPTION HAT WERT → diesen anzeigen
	else {
		$display = $value;
	}

	echo '<textarea name="' . esc_attr($key) . '"
		rows="' . esc_attr($rows) . '"
		style="width:100%;">'
	     . esc_textarea($display)
	     . '</textarea>';
}

/* ------------------------------------------------------------
 * SEITE RENDERN (TAB-SYSTEM)
 * ------------------------------------------------------------ */
function cmx_render_settings_page(): void {

	$tabs = cmx_get_tabs();
	$tab  = $_GET['tab'] ?? 'general';

	if (!isset($tabs[$tab])) $tab = 'general';

	$subtabs = cmx_get_subtabs($tab);
	$sub     = $_GET['sub'] ?? (array_key_first($subtabs) ?: '');

	if ($sub && !isset($subtabs[$sub])) {
		$sub = array_key_first($subtabs) ?: '';
	}

	$page_id = $sub ? "cmx_tab_{$tab}__{$sub}" : "cmx_tab_{$tab}";

	echo '<div class="wrap"><h1>Einstellungen</h1>';

	/* Tabs */
	echo '<h2 class="nav-tab-wrapper">';
	foreach ($tabs as $key => $label) {
		$url = admin_url("admin.php?page=" . CMX_SETTINGS_SLUG . "&tab={$key}");
		echo '<a href="' . $url . '" class="nav-tab ' . ($key === $tab ? 'nav-tab-active' : '') . '">' . esc_html($label) . '</a>';
	}
	echo '</h2>';

	/* Subtabs */
	if ($subtabs) {
		echo '<ul class="subsubsub">';
		$i = 0; $n = count($subtabs);
		foreach ($subtabs as $key => $label) {
			$url = admin_url("admin.php?page=" . CMX_SETTINGS_SLUG . "&tab={$tab}&sub={$key}");
			echo '<li><a href="' . $url . '" class="' . ($key === $sub ? 'current' : '') . '">' . esc_html($label) . '</a>'
			     . (++$i < $n ? ' | ' : '') . '</li>';
		}
		echo '</ul><br class="clear">';
	}

	echo '<form method="post" action="options.php">';
	echo '<div class="cmx-tabpanel">';

	/* ARRAY-TABS */
	if ($tab !== 'belege') {
		settings_fields(CMX_SETTINGS_KEY);
		do_settings_sections($page_id);
	}

	/* BELEGE SUBTABS → SINGLE OPTIONS */
	if ($tab === 'belege') {

		if ($sub === 'angebot')      settings_fields('cmx_belege_angebot');
		if ($sub === 'gutschrift')   settings_fields('cmx_belege_gutschrift');
		if ($sub === 'lieferschein') settings_fields('cmx_belege_lieferschein');
		if ($sub === 'rechnung')     settings_fields('cmx_belege_rechnung');

		do_settings_sections($page_id);
	}

	submit_button('Änderungen speichern');

	echo '</div></form></div>';
}

/* ------------------------------------------------------------
 * SANITIZER (für Array-Tabs)
 * ------------------------------------------------------------ */
function cmx_sanitize_settings($input) {
	if (!is_array($input)) return [];
	$existing = get_option(CMX_SETTINGS_KEY, []);
	return array_merge($existing, $input);
}
