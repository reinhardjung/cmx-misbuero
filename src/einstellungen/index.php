<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit;

/* ------------------------------------------------------------
 * INCLUDE FILES
 * ------------------------------------------------------------ */
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
const CMX_SETTINGS_SLUG  = 'cmx-einstellungen';
const CMX_SETTINGS_MAIN  = 'cmx_einstellungen';   // ARRAY
const CMX_SETTINGS_BELEG = 'cmx_belege';          // ARRAY

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
		// 'general'  => 'Allgemein',
		// 'kontakte' => 'Kontakte',
		// 'banken'   => 'Banken',
		'belege'   => 'Belege',
		// 'advanced' => 'Erweitert',
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
 * FELDER FÜR MAIN-TABS
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
	name="'.CMX_SETTINGS_MAIN.'['.$key.']"
	value="'.esc_attr($val).'"
	placeholder="'.esc_attr($ph).'">';
}

/* ------------------------------------------------------------
 * SETTINGS PAGE
 * ------------------------------------------------------------ */
function cmx_render_settings_page(): void
{
	if (!current_user_can('manage_options')) {
		wp_die('Nicht erlaubt.');
	}

	$tabs = cmx_get_tabs();
	$tab  = $_GET['tab'] ?? 'general';

	if (!isset($tabs[$tab])) $tab = 'general';

	$subtabs = cmx_get_subtabs($tab);
	$sub     = $_GET['sub'] ?? (array_key_first($subtabs) ?: '');

	if ($sub && !isset($subtabs[$sub])) {
		$sub = array_key_first($subtabs) ?: '';
	}

	$page_id = $sub
		? "cmx_tab_{$tab}__{$sub}"
		: "cmx_tab_{$tab}";

	echo '<div class="wrap"><h1>Einstellungen</h1>';

	/* ---------- TABS ---------- */
	echo '<h2 class="nav-tab-wrapper">';
	foreach ($tabs as $key => $label) {
		echo '<a href="?page=' . CMX_SETTINGS_SLUG . '&tab=' . $key . '" class="nav-tab ' .
		     ($tab === $key ? 'nav-tab-active' : '') . '">' . $label . '</a>';
	}
	echo '</h2>';

	/* ---------- SUBTABS ---------- */
	if ($subtabs) {
		echo '<ul class="subsubsub">';
		$i = 0; $n = count($subtabs);
		foreach ($subtabs as $key => $label) {
			echo '<li><a href="?page=' . CMX_SETTINGS_SLUG . '&tab=' . $tab . '&sub=' . $key .
			     '" class="' . ($key === $sub ? 'current' : '') . '">' . $label .
			     '</a>' . (++$i < $n ? ' | ' : '') . '</li>';
		}
		echo '</ul><br><br>';
	}

	echo '<div class="cmx-tabpanel">';

	/* ------------------------------------------------------------
	 * 1) SUPPORT-TAB → KEIN WP SETTINGS FORMULAR!
	 * ------------------------------------------------------------ */
	if ($tab === 'support') {
		do_settings_sections($page_id);
		echo '</div></div>';
		return;
	}

	/* ------------------------------------------------------------
	 * 2) BELEGE → eigenes settings array
	 * ------------------------------------------------------------ */
	if ($tab === 'belege') {
		echo '<form method="post" action="options.php">';
		settings_fields(CMX_SETTINGS_BELEG);
		do_settings_sections($page_id);
		submit_button();
		echo '</form></div></div>';
		return;
	}

	/* ------------------------------------------------------------
	 * 3) ALLE ANDEREN TABS
	 * ------------------------------------------------------------ */
	echo '<form method="post" action="options.php">';
	settings_fields(CMX_SETTINGS_MAIN);
	do_settings_sections($page_id);
	submit_button();
	echo '</form>';

	echo '</div></div>';
}
