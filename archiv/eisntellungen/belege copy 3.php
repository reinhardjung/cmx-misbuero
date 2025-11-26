<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * --------------------------------------------------------------
 * 1) Universeller INI-Loader
 * --------------------------------------------------------------
 * Holt einen Wert aus /includes/globals.ini
 * - Section case-insensitiv
 * - Key case-insensitiv
 * - Rückgabe immer string (HTML erlaubt)
 */

/**
 * --------------------------------------------------------------
 * 2) Holt alle E-Mail-Texte roh aus der INI
 * --------------------------------------------------------------
 */
function cmx_get_email_templates_raw(): array {

	$keys = [
		'angebot',
		'gutschrift',
		'lieferschein',
		'rechnung'
	];

	$out = [];

	foreach ($keys as $k) {
		$out[$k] = cmx_ini_get_value('E-Mails', $k);
	}

	return $out;
}



/**
 * --------------------------------------------------------------
 * 3) Für Admin: HTML <br> in echte Zeilenumbrüche umwandeln
 * --------------------------------------------------------------
 */
function cmx_get_email_templates_for_admin(): array {

	$raw = cmx_get_email_templates_raw();

	$convert = static function(string $text): string {
		if ($text === '') {
			return '';
		}
		return str_replace(['<br />', '<br>'], "\n", $text);
	};

	return [
		'angebot'      => $convert($raw['angebot']),
		'gutschrift'   => $convert($raw['gutschrift']),
		'lieferschein' => $convert($raw['lieferschein']),
		'rechnung'     => $convert($raw['rechnung']),
	];
}



/**
 * --------------------------------------------------------------
 * 4) Admin-Tabs: Angebot, Gutschrift, Lieferschein, Rechnung
 * --------------------------------------------------------------
 */
add_action('admin_init', __NAMESPACE__ . '\\cmx_register_belege_tab');

function cmx_register_belege_tab(): void {

	$ph = cmx_get_email_templates_for_admin();

	/* ------------------------- ANGEBOT ------------------------- */
	$page = 'cmx_tab_belege__angebot';

	add_settings_section(
		'cmx_sec_belege_angebot',
		__('Angebot', 'default'),
		'__return_false',
		$page
	);

	add_settings_field(
		'mail_angebot',
		__('E-Mail Text für Angebot', 'default'),
		__NAMESPACE__ . '\\cmx_field_textarea',
		$page,
		'cmx_sec_belege_angebot',
		[
			'key'         => 'mail_angebot',
			'rows'        => 8,
			'placeholder' => $ph['angebot'],
		]
	);


	/* ------------------------- GUTSCHRIFT ------------------------- */
	$page = 'cmx_tab_belege__gutschrift';

	add_settings_section(
		'cmx_sec_belege_gutschrift',
		__('Gutschrift', 'default'),
		'__return_false',
		$page
	);

	add_settings_field(
		'mail_gutschrift',
		__('E-Mail Text für Gutschrift', 'default'),
		__NAMESPACE__ . '\\cmx_field_textarea',
		$page,
		'cmx_sec_belege_gutschrift',
		[
			'key'         => 'mail_gutschrift',
			'rows'        => 8,
			'placeholder' => $ph['gutschrift'],
		]
	);


	/* ------------------------- LIEFERSCHEIN ------------------------- */
	$page = 'cmx_tab_belege__lieferschein';

	add_settings_section(
		'cmx_sec_belege_lieferschein',
		__('Lieferschein', 'default'),
		'__return_false',
		$page
	);

	add_settings_field(
		'mail_lieferschein',
		__('E-Mail Text für Lieferschein', 'default'),
		__NAMESPACE__ . '\\cmx_field_textarea',
		$page,
		'cmx_sec_belege_lieferschein',
		[
			'key'         => 'mail_lieferschein',
			'rows'        => 8,
			'placeholder' => $ph['lieferschein'],
		]
	);


	/* ------------------------- RECHNUNG ------------------------- */
	$page = 'cmx_tab_belege__rechnung';

	add_settings_section(
		'cmx_sec_belege_rechnung',
		__('Rechnung', 'default'),
		'__return_false',
		$page
	);

	add_settings_field(
		'mail_rechnung',
		__('E-Mail Text für Rechnung', 'default'),
		__NAMESPACE__ . '\\cmx_field_textarea',
		$page,
		'cmx_sec_belege_rechnung',
		[
			'key'         => 'mail_rechnung',
			'rows'        => 8,
			'placeholder' => $ph['rechnung'],
		]
	);
}



/**
 * --------------------------------------------------------------
 * 5) Für den Versand (HTML bleibt enthalten)
 * --------------------------------------------------------------
 */
function cmx_get_email_defaults_from_ini(): array {
	return cmx_get_email_templates_raw();
}
