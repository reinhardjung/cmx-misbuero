<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * Rohwerte aus includes/globals.ini laden (HTML bleibt erhalten)
 * — OHNE CACHE —
 */
function cmx_get_ini_placeholders_raw(): array {

	$result = [
		'angebot'      => '',
		'gutschrift'   => '',
		'lieferschein' => '',
		'rechnung'     => '',
	];

	// KORREKTER PFAD
	if (function_exists(__NAMESPACE__ . '\\cmx_plugin_path')) {
		$ini_file = cmx_plugin_path('includes/globals.ini');
	} else {
		$ini_file = dirname(__DIR__, 2) . '/includes/globals.ini';
	}

	if (!file_exists($ini_file)) {
		return $result;
	}

	$ini = parse_ini_file($ini_file, true, INI_SCANNER_RAW);

	if (!is_array($ini) || empty($ini['E-Mails'])) {
		return $result;
	}

	$sec = $ini['E-Mails'];

	// WICHTIG: Keys exakt wie in der INI-Datei (case-sensitive!)
	$map = [
		'Angebot'      => 'angebot',
		'Gutschrift'   => 'gutschrift',
		'Lieferschein' => 'lieferschein',
		'Rechnung'     => 'rechnung',
	];

	foreach ($map as $ini_key => $target_key) {
		if (isset($sec[$ini_key]) && $sec[$ini_key] !== '') {
			$result[$target_key] = (string)$sec[$ini_key];
		}
	}

	return $result;
}


/**
 * Für das Admin-Textarea: <br> → \n umwandeln
 * — OHNE CACHE —
 */
function cmx_get_ini_placeholders_for_admin(): array {
	$raw = cmx_get_ini_placeholders_raw();

	$convert = static function(string $txt): string {
		if ($txt === '') {
			return '';
		}
		return str_replace(["<br />", "<br>"], "\n", $txt);
	};

	return [
		'angebot'      => $convert($raw['angebot']),
		'gutschrift'   => $convert($raw['gutschrift']),
		'lieferschein' => $convert($raw['lieferschein']),
		'rechnung'     => $convert($raw['rechnung']),
	];
}



/**
 * Tab: Belege
 */
add_action('admin_init', __NAMESPACE__ . '\\cmx_register_belege_tab');
function cmx_register_belege_tab(): void {

	// Immer frisch einlesen
	$ph = cmx_get_ini_placeholders_for_admin();

	/* ------------------------- Angebot ------------------------- */
	$page_angebot = 'cmx_tab_belege__angebot';
	add_settings_section('cmx_sec_belege_angebot', __('Angebot', 'default'), '__return_false', $page_angebot);

	add_settings_field(
		'mail_angebot',
		__('E-Mail Text für Angebot', 'default'),
		__NAMESPACE__ . '\\cmx_field_textarea',
		$page_angebot,
		'cmx_sec_belege_angebot',
		[
			'key'         => 'mail_angebot',
			'rows'        => 8,
			'placeholder' => $ph['angebot'],
		]
	);


	/* ------------------------- Gutschrift ------------------------- */
	$page_gutschrift = 'cmx_tab_belege__gutschrift';
	add_settings_section('cmx_sec_belege_gutschrift', __('Gutschrift', 'default'), '__return_false', $page_gutschrift);

	add_settings_field(
		'mail_gutschrift',
		__('E-Mail Text für Gutschrift', 'default'),
		__NAMESPACE__ . '\\cmx_field_textarea',
		$page_gutschrift,
		'cmx_sec_belege_gutschrift',
		[
			'key'         => 'mail_gutschrift',
			'rows'        => 8,
			'placeholder' => $ph['gutschrift'],
		]
	);


	/* ------------------------- Lieferschein ------------------------- */
	$page_lieferschein = 'cmx_tab_belege__lieferschein';
	add_settings_section('cmx_sec_belege_lieferschein', __('Lieferschein', 'default'), '__return_false', $page_lieferschein);

	add_settings_field(
		'mail_lieferschein',
		__('E-Mail Text für Lieferschein', 'default'),
		__NAMESPACE__ . '\\cmx_field_textarea',
		$page_lieferschein,
		'cmx_sec_belege_lieferschein',
		[
			'key'         => 'mail_lieferschein',
			'rows'        => 8,
			'placeholder' => $ph['lieferschein'],
		]
	);


	/* ------------------------- Rechnung ------------------------- */
	$page_rechnung = 'cmx_tab_belege__rechnung';
	add_settings_section('cmx_sec_belege_rechnung', __('Rechnung', 'default'), '__return_false', $page_rechnung);

	add_settings_field(
		'mail_rechnung',
		__('E-Mail Text für Rechnung', 'default'),
		__NAMESPACE__ . '\\cmx_field_textarea',
		$page_rechnung,
		'cmx_sec_belege_rechnung',
		[
			'key'         => 'mail_rechnung',
			'rows'        => 8,
			'placeholder' => $ph['rechnung'],
		]
	);
}



/**
 * Für den Versand (HTML unverändert)
 * — OHNE CACHE —
 */
function cmx_get_email_defaults_from_ini(): array {
	$raw = cmx_get_ini_placeholders_raw();

	return [
		'mail_angebot'      => $raw['angebot'],
		'mail_gutschrift'   => $raw['gutschrift'],
		'mail_lieferschein' => $raw['lieferschein'],
		'mail_rechnung'     => $raw['rechnung'],
	];
}
