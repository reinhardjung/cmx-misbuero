<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit;

/* ------------------------------------------------------------
 * HILFSFUNKTIONEN
 * ------------------------------------------------------------ */

/** PHP 7 kompatibles starts_with */
function cmx_starts_with(string $haystack, string $needle): bool {
	return substr($haystack, 0, strlen($needle)) === $needle;
}

/**
 * Liefert den korrekten INI-Wert anhand des Keys:
 *   mail_angebot      → (E-Mails, Angebot)
 *   belegfuss_rechnung → (Belegfuss, Rechnung)
 */
// var_dump(get_option('cmx_belege')['belegfuss_rechnung']); exit;
// function cmx_get_beleg_default(string $key): string {
// 	if (cmx_starts_with($key, 'mail_')) {
// 		$section = 'E-Mails';
// 		$name    = ucfirst(substr($key, 5));
// 	}
// 	elseif (cmx_starts_with($key, 'belegfuss_')) {
// 		$section = 'Belegfuss';
// 		$name    = ucfirst(substr($key, 10));
// 	}
// 	else {
// 		return '';
// 	}

// 	$val = cmx_ini_get_value($section, $name);
// 	// var_dump(cmx_ini_get_value($section, $name)); exit;


// 	if (!is_string($val)) {
// 		return '';
// 	}

// 	return str_replace(['<br>', '<br/>', '<br />'], "\n", $val);
// }

function cmx_get_beleg_default(string $key): string {

	// Nur gespeicherte Optionen nutzen – nichts anderes
	$options = get_option('cmx_belege', []);

	if (isset($options[$key]) && is_string($options[$key])) {
		// var_dump($options[$key]); exit;
		return str_replace(['<br>', '<br/>', '<br />'], "\n", $options[$key]);
	}

	return '';
}



/* ------------------------------------------------------------
 * TEXTAREA – MIT INI PLACEHOLDER
 * ------------------------------------------------------------ */
function cmx_field_textarea_beleg(array $args): void {

	$key  = $args['key'];
	$rows = intval($args['rows'] ?? 5);

	$options = get_option('cmx_belege', []);
	$value   = $options[$key] ?? '';

	// Wenn leer → Placeholder aus INI
	$default = cmx_get_beleg_default($key);
	$display = ($value === '') ? $default : $value;

	echo '<textarea
		name="cmx_belege[' . esc_attr($key) . ']"
		rows="' . esc_attr($rows) . '"
		style="width:100%;">' . esc_textarea($display) . '</textarea>';
}


/* ------------------------------------------------------------
 * FELDER REGISTRIEREN
 * ------------------------------------------------------------ */
add_action('admin_init', function() {

	$add = function($page,$section,$label,$key,$rows) {
		add_settings_field(
			$key,
			$label,
			__NAMESPACE__ . '\\cmx_field_textarea_beleg',
			$page,
			$section,
			[
				'key'  => $key,
				'rows' => $rows,
			]
		);
	};

	$tabs = [
		'angebot'      => 'Angebot',
		'gutschrift'   => 'Gutschrift',
		'lieferschein' => 'Lieferschein',
		'rechnung'     => 'Rechnung'
	];

	foreach ($tabs as $sub => $label) {

		$page = "cmx_tab_belege__{$sub}";

		add_settings_section(
			"sec_{$sub}",
			$label,
			'__return_false',
			$page
		);

		$add($page, "sec_{$sub}", 'Belegfuss',   "belegfuss_{$sub}", 4);
		$add($page, "sec_{$sub}", 'E-Mail Text', "mail_{$sub}",      8);
	}
});
