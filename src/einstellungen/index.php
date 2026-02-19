<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit;

/* ------------------------------------------------------------
 * KONSTANTEN – MÜSSEN HIER DEFINIERT SEIN
 * ------------------------------------------------------------ */
const CMX_SETTINGS_SLUG  = 'cmx-einstellungen';
const CMX_SETTINGS_MAIN  = 'cmx_einstellungen';
const CMX_SETTINGS_BELEG = 'cmx_belege';

/* ------------------------------------------------------------
 * INCLUDE FILES – jetzt korrekt, da Konstanten bereis existieren
 * ------------------------------------------------------------ */
require_once 'allgemein.php';
require_once 'banken.php';
require_once 'kontakte.php';
require_once 'belege.php';
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
		'manage_options',
		CMX_SETTINGS_SLUG,
		__NAMESPACE__ . '\\cmx_render_settings_page',
		'dashicons-admin-generic',
		100
	);
});

/* ------------------------------------------------------------
 * TAB-LISTEN
 * ------------------------------------------------------------ */
function cmx_get_tabs(): array {
	return [
		'general' => 'Allgemein',
		'banken'  => 'Banken',
		'belege'  => 'Belege',
		'support' => 'Support',
	];
}

function cmx_get_subtabs(string $tab): array {

	if ($tab === 'banken') {
		return [
			'rev'    => 'Revolut',
			'zkb'    => 'ZKB',
			'ubs'    => 'UBS',
			'migros' => 'Migros Bank',
			'eisen'  => 'Raiffeisen',
		];
	}

	if ($tab === 'belege') {
		return [
			'offerte'      => 'Offerte',
			'gutschrift'   => 'Gutschrift',
			'lieferschein' => 'Lieferschein',
			'rechnung'     => 'Rechnung',
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
				return is_array($input)
					? array_merge(get_option(CMX_SETTINGS_MAIN, []), $input)
					: get_option(CMX_SETTINGS_MAIN, []);
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
				return is_array($input)
					? array_merge(get_option(CMX_SETTINGS_BELEG, []), $input)
					: get_option(CMX_SETTINGS_BELEG, []);
			}
		]
	);
});

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

	$tabs = cmx_get_tabs();
	$tab  = $_GET['tab'] ?? 'general';
	if (!isset($tabs[$tab])) $tab = 'general';

	$subtabs = cmx_get_subtabs($tab);
	$sub     = $_GET['sub'] ?? (array_key_first($subtabs) ?: '');
	if ($sub && !isset($subtabs[$sub])) $sub = array_key_first($subtabs) ?: '';

	$page_id = $sub ? "cmx_tab_{$tab}__{$sub}" : "cmx_tab_{$tab}";

	echo '<div class="wrap"><h1>Einstellungen</h1>';

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
		$i=0; $n=count($subtabs);
		foreach ($subtabs as $k => $label) {
			echo '<li><a href="?page='.CMX_SETTINGS_SLUG.'&tab='.$tab.'&sub='.$k.'"'
			     .' class="'.($k === $sub ? 'current' : '').'">'.$label.'</a>'
			     .(++$i < $n ? ' | ' : '').'</li>';
		}
		echo '</ul><br><br>';
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

	/* ALLE ANDEREN */
	echo '<form method="post" action="options.php">';
	settings_fields(CMX_SETTINGS_MAIN);
	do_settings_sections($page_id);
	submit_button();
	echo '</form>';

	echo '</div></div>';
}

/* ------------------------------------------------------------
 * SANITIZER: IMMER GENAU 1 BANK
 * ------------------------------------------------------------ */
add_filter('pre_update_option_' . CMX_SETTINGS_MAIN, function($new, $old) {
	$new = \is_array($new) ? $new : [];
	$normalize_due_days = static function(array $data): array {
		if (!\array_key_exists('belege_faelligkeit_tage', $data)) {
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
		return $data;
	};
	$new = $normalize_due_days($new);

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

	/* Keine aktiv → erste aktivieren */
	if (count($active) === 0) {
		$first = $bank_keys[0];
		foreach ($bank_keys as $k) {
			$new[$k] = ($k === $first) ? 1 : 0;
		}
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
