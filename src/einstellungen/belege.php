<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * Tab: Belege
 * Subtabs: Angebot, Gutschrift, Lieferschein, Rechnung
 * Jeweils ein Textarea-Feld für den E-Mail-Text
 */

add_action('admin_init', __NAMESPACE__ . '\\cmx_register_belege_tab');
function cmx_register_belege_tab(): void {

	/* --------------------------------------------------------------
	 * Angebot
	 * -------------------------------------------------------------- */
	$page_angebot = 'cmx_tab_belege__angebot';

	add_settings_section(
		'cmx_sec_belege_angebot',
		__('Angebot', 'default'),
		'__return_false',
		$page_angebot
	);

	add_settings_field(
		'mail_angebot',
		__('E-Mail Text für Angebot', 'default'),
		__NAMESPACE__ . '\\cmx_field_textarea',
		$page_angebot,
		'cmx_sec_belege_angebot',
		[
			'key'         => 'mail_angebot',
			'rows'        => 8,
			'placeholder' => __("Hallo …\n\nIm Anhang findest Du Dein Angebot.\n\nFreundliche Grüsse", 'default'),
		]
	);


	/* --------------------------------------------------------------
	 * Gutschrift
	 * -------------------------------------------------------------- */
	$page_gutschrift = 'cmx_tab_belege__gutschrift';

	add_settings_section(
		'cmx_sec_belege_gutschrift',
		__('Gutschrift', 'default'),
		'__return_false',
		$page_gutschrift
	);

	add_settings_field(
		'mail_gutschrift',
		__('E-Mail Text für Gutschrift', 'default'),
		__NAMESPACE__ . '\\cmx_field_textarea',
		$page_gutschrift,
		'cmx_sec_belege_gutschrift',
		[
			'key'         => 'mail_gutschrift',
			'rows'        => 8,
			'placeholder' => __("Hallo …\n\nIm Anhang findest Du Deine Gutschrift.\n\nFreundliche Grüsse", 'default'),
		]
	);


	/* --------------------------------------------------------------
	 * Lieferschein
	 * -------------------------------------------------------------- */
	$page_lieferschein = 'cmx_tab_belege__lieferschein';

	add_settings_section(
		'cmx_sec_belege_lieferschein',
		__('Lieferschein', 'default'),
		'__return_false',
		$page_lieferschein
	);

	add_settings_field(
		'mail_lieferschein',
		__('E-Mail Text für Lieferschein', 'default'),
		__NAMESPACE__ . '\\cmx_field_textarea',
		$page_lieferschein,
		'cmx_sec_belege_lieferschein',
		[
			'key'         => 'mail_lieferschein',
			'rows'        => 8,
			'placeholder' => __("Hallo …\n\nIm Anhang findest Du Deinen Lieferschein.\n\nFreundliche Grüsse", 'default'),
		]
	);


	/* --------------------------------------------------------------
	 * Rechnung
	 * -------------------------------------------------------------- */
	$page_rechnung = 'cmx_tab_belege__rechnung';

	add_settings_section(
		'cmx_sec_belege_rechnung',
		__('Rechnung', 'default'),
		'__return_false',
		$page_rechnung
	);

	add_settings_field(
		'mail_rechnung',
		__('E-Mail Text für Rechnung', 'default'),
		__NAMESPACE__ . '\\cmx_field_textarea',
		$page_rechnung,
		'cmx_sec_belege_rechnung',
		[
			'key'         => 'mail_rechnung',
			'rows'        => 8,
			'placeholder' => __("Hallo …\n\nIm Anhang findest Du Deine Rechnung.\n\nVielen Dank für Deinen Auftrag.\n\nFreundliche Grüsse", 'default'),
		]
	);
}



/**
 * E-Mail-Standardtexte aus INI lesen.
 * INI: [E-Mails] Angebot, Gutschrift, Lieferschein, Rechnung
 */
function cmx_get_email_defaults_from_ini(): array {
	static $defaults = null;

	if ($defaults !== null) {
		return $defaults;
	}

	// Grundstruktur
	$defaults = [
		'mail_angebot'     => '',
		'mail_gutschrift'  => '',
		'mail_lieferschein'=> '',
		'mail_rechnung'    => '',
	];

	// Pfad zur INI-Datei – ggf. anpassen
	if (function_exists(__NAMESPACE__ . '\\cmx_plugin_path')) {
		$ini_file = cmx_plugin_path('config/misbuero.ini');
	} else {
		// Fallback: von /includes/settings/ zwei Ebenen hoch
		$ini_file = dirname(__DIR__, 2) . '/config/misbuero.ini';
	}

	if (!file_exists($ini_file)) {
		return $defaults;
	}

	$ini = parse_ini_file($ini_file, true, INI_SCANNER_RAW);
	if (!is_array($ini) || empty($ini['E-Mails']) || !is_array($ini['E-Mails'])) {
		return $defaults;
	}

	$section = $ini['E-Mails'];

	$map = [
		'Angebot'     => 'mail_angebot',
		'Gutschrift'  => 'mail_gutschrift',
		'Lieferschein'=> 'mail_lieferschein',
		'Rechnung'    => 'mail_rechnung',
	];

	foreach ($map as $ini_key => $opt_key) {
		if (isset($section[$ini_key]) && $section[$ini_key] !== '') {
			$defaults[$opt_key] = (string) $section[$ini_key];
		}
	}

	return $defaults;
}




// $body_html = cmx_get_option('mail_rechnung', '');
// wp_mail($to, $subject, $body_html, ['Content-Type: text/html; charset=UTF-8'], $attachments);

// cmx_get_mailtext_for_belegtyp('rechnung'); // gibt HTML zurück
