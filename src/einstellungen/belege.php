<?php namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit('Oxytocin!');

/* ------------------------------------------------------------
 * INI auslesen
 * ------------------------------------------------------------ */
function cmx_get_ini_belege(): array {

	$out = [
		'mail_angebot'            => '',
		'mail_gutschrift'         => '',
		'mail_lieferschein'       => '',
		'mail_rechnung'           => '',
		'belegfuss_angebot'       => '',
		'belegfuss_gutschrift'    => '',
		'belegfuss_lieferschein'  => '',
		'belegfuss_rechnung'      => '',
	];

	if (function_exists(__NAMESPACE__ . '\\cmx_plugin_path')) {
		$file = cmx_plugin_path('includes/globals.ini');
	} else {
		$file = dirname(__DIR__, 2) . '/includes/globals.ini';
	}

	if (!file_exists($file)) return $out;

	$ini = parse_ini_file($file, true, INI_SCANNER_RAW);
	if (!$ini) return $out;

	$mapMail = [
		'Angebot'      => 'mail_angebot',
		'Gutschrift'   => 'mail_gutschrift',
		'Lieferschein' => 'mail_lieferschein',
		'Rechnung'     => 'mail_rechnung',
	];

	foreach ($mapMail as $src => $dst) {
		if (!empty($ini['E-Mails'][$src])) {
			$out[$dst] = str_replace(['<br>', '<br />'], "\n", $ini['E-Mails'][$src]);
		}
	}

	$mapBF = [
		'Angebot'      => 'belegfuss_angebot',
		'Gutschrift'   => 'belegfuss_gutschrift',
		'Lieferschein' => 'belegfuss_lieferschein',
		'Rechnung'     => 'belegfuss_rechnung',
	];

	foreach ($mapBF as $src => $dst) {
		if (!empty($ini['Belegfuss'][$src])) {
			$out[$dst] = str_replace(['<br>', '<br />'], "\n", $ini['Belegfuss'][$src]);
		}
	}

	return $out;
}

/* ------------------------------------------------------------
 * SINGLE OPTION REGISTRIERUNG
 * ------------------------------------------------------------ */
add_action('admin_init', function() {

	register_setting('cmx_belege_angebot',      'mail_angebot');
	register_setting('cmx_belege_angebot',      'belegfuss_angebot');

	register_setting('cmx_belege_gutschrift',   'mail_gutschrift');
	register_setting('cmx_belege_gutschrift',   'belegfuss_gutschrift');

	register_setting('cmx_belege_lieferschein', 'mail_lieferschein');
	register_setting('cmx_belege_lieferschein', 'belegfuss_lieferschein');

	register_setting('cmx_belege_rechnung',     'mail_rechnung');
	register_setting('cmx_belege_rechnung',     'belegfuss_rechnung');
});

/* ------------------------------------------------------------
 * BELEGE-FELDER RENDER
 * ------------------------------------------------------------ */
add_action('admin_init', __NAMESPACE__ . '\\cmx_register_belege_tab');
function cmx_register_belege_tab(): void {

	$ph = cmx_get_ini_belege();

	$add_field = function($page, $section, $label, $key, $rows) use ($ph) {
		add_settings_field(
			$key,
			$label,
			__NAMESPACE__ . '\\cmx_field_textarea',
			$page,
			$section,
			[
				'key'         => $key,
				'rows'        => $rows,
				'placeholder' => $ph[$key] ?? ''
			]
		);
	};

	// Angebot
	$page = 'cmx_tab_belege__angebot';
	add_settings_section('sec_angebot','Angebot','__return_false',$page);
	$add_field($page,'sec_angebot','Belegfuss für Angebot','belegfuss_angebot',4);
	$add_field($page,'sec_angebot','E-Mail Text für Angebot','mail_angebot',8);

	// Gutschrift
	$page = 'cmx_tab_belege__gutschrift';
	add_settings_section('sec_gutschrift','Gutschrift','__return_false',$page);
	$add_field($page,'sec_gutschrift','Belegfuss für Gutschrift','belegfuss_gutschrift',4);
	$add_field($page,'sec_gutschrift','E-Mail Text für Gutschrift','mail_gutschrift',8);

	// Lieferschein
	$page = 'cmx_tab_belege__lieferschein';
	add_settings_section('sec_lieferschein','Lieferschein','__return_false',$page);
	$add_field($page,'sec_lieferschein','Belegfuss für Lieferschein','belegfuss_lieferschein',4);
	$add_field($page,'sec_lieferschein','E-Mail Text für Lieferschein','mail_lieferschein',8);

	// Rechnung
	$page = 'cmx_tab_belege__rechnung';
	add_settings_section('sec_rechnung','Rechnung','__return_false',$page);
	$add_field($page,'sec_rechnung','Belegfuss für Rechnung','belegfuss_rechnung',4);
	$add_field($page,'sec_rechnung','E-Mail Text für Rechnung','mail_rechnung',8);
}


function cmx_get_belegfuss(string $typ): string {

	// gültige Typen
	$allowed = [
		'angebot',
		'gutschrift',
		'lieferschein',
		'rechnung'
	];

	if (!in_array($typ, $allowed, true)) {
		return '';
	}

	$key = 'belegfuss_' . $typ;

	// 1) gespeicherter Wert?
	$val = get_option($key, null);

	// Falls nicht vorhanden → Default aus INI
	if ($val === null || $val === '') {

		if (function_exists(__NAMESPACE__ . '\\cmx_get_ini_belege')) {
			$ini = cmx_get_ini_belege();
			if (!empty($ini[$key])) {
				return $ini[$key];
			}
		}

		return '';
	}

	return $val;
}
