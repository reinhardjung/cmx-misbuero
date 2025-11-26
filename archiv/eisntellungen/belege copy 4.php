<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * --------------------------------------------------------------
 * 1) Holt E-Mail-Texte roh aus der INI
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
 * 1b) Holt Belegfuss-Texte roh aus der INI
 * --------------------------------------------------------------
 */
function cmx_get_belegfuss_raw(): array {

	$keys = [
		'angebot',
		'gutschrift',
		'lieferschein',
		'rechnung'
	];

	$out = [];

	foreach ($keys as $k) {
		$out[$k] = cmx_ini_get_value('Belegfuss', $k);
	}

	return $out;
}


/**
 * --------------------------------------------------------------
 * 2) Für Admin: HTML <br> -> echte Zeilenumbrüche
 * --------------------------------------------------------------
 */
function cmx_convert_html_for_admin(string $text): string {
	if ($text === '') {
		return '';
	}
	return str_replace(['<br />', '<br>'], "\n", $text);
}


/**
 * --------------------------------------------------------------
 * 2a) Email-Texte für Admin
 * --------------------------------------------------------------
 */
function cmx_get_email_templates_for_admin(): array {

	$raw = cmx_get_email_templates_raw();

	return [
		'angebot'      => cmx_convert_html_for_admin($raw['angebot']),
		'gutschrift'   => cmx_convert_html_for_admin($raw['gutschrift']),
		'lieferschein' => cmx_convert_html_for_admin($raw['lieferschein']),
		'rechnung'     => cmx_convert_html_for_admin($raw['rechnung']),
	];
}


/**
 * --------------------------------------------------------------
 * 2b) Belegfuss-Texte für Admin
 * --------------------------------------------------------------
 */
function cmx_get_belegfuss_for_admin(): array {

	$raw = cmx_get_belegfuss_raw();

	return [
		'angebot'      => cmx_convert_html_for_admin($raw['angebot']),
		'gutschrift'   => cmx_convert_html_for_admin($raw['gutschrift']),
		'lieferschein' => cmx_convert_html_for_admin($raw['lieferschein']),
		'rechnung'     => cmx_convert_html_for_admin($raw['rechnung']),
	];
}


/**
 * --------------------------------------------------------------
 * 3) Admin-Tab Registrieren
 * --------------------------------------------------------------
 */
add_action('admin_init', __NAMESPACE__ . '\\cmx_register_belege_tab');

function cmx_register_belege_tab(): void {

	$ph_mail = cmx_get_email_templates_for_admin();
	$ph_fuss = cmx_get_belegfuss_for_admin();

	/* ------------------------- Helper Inline ------------------------- */
	$build_fields = function(string $page, string $label_mail, string $label_fuss, string $key)
    use ($ph_mail, $ph_fuss) : void {

		add_settings_section(
			"cmx_sec_belege_{$key}",
			ucfirst($key),
			'__return_false',
			$page
		);

		add_settings_field(
			"mail_{$key}",
			$label_mail,
			__NAMESPACE__ . '\\cmx_field_textarea',
			$page,
			"cmx_sec_belege_{$key}",
			[
				'key'         => "mail_{$key}",
				'rows'        => 8,
				'placeholder' => $ph_mail[$key],
			]
		);

		add_settings_field(
			"belegfuss_{$key}",
			$label_fuss,
			__NAMESPACE__ . '\\cmx_field_textarea',
			$page,
			"cmx_sec_belege_{$key}",
			[
				'key'         => "belegfuss_{$key}",
				'rows'        => 6,
				'placeholder' => $ph_fuss[$key],
			]
		);
	};


	/* ------------------------- ANGEBOT ------------------------- */
	$build_fields(
		'cmx_tab_belege__angebot',
		__('E-Mail Text für Angebot', 'default'),
		__('Belegfuss für Angebot', 'default'),
		'angebot'
	);

	/* ------------------------- GUTSCHRIFT ------------------------- */
	$build_fields(
		'cmx_tab_belege__gutschrift',
		__('E-Mail Text für Gutschrift', 'default'),
		__('Belegfuss für Gutschrift', 'default'),
		'gutschrift'
	);

	/* ------------------------- LIEFERSCHEIN ------------------------- */
	$build_fields(
		'cmx_tab_belege__lieferschein',
		__('E-Mail Text für Lieferschein', 'default'),
		__('Belegfuss für Lieferschein', 'default'),
		'lieferschein'
	);

	/* ------------------------- RECHNUNG ------------------------- */
	$build_fields(
		'cmx_tab_belege__rechnung',
		__('E-Mail Text für Rechnung', 'default'),
		__('Belegfuss für Rechnung', 'default'),
		'rechnung'
	);
}


/**
 * --------------------------------------------------------------
 * 4) Für Versand (HTML bleibt)
 * --------------------------------------------------------------
 */
function cmx_get_email_defaults_from_ini(): array {
	return cmx_get_email_templates_raw();
}


/**
 * --------------------------------------------------------------
 * 4b) Für PDF-Belegfuss (HTML bleibt)
 * --------------------------------------------------------------
 */
function cmx_get_belegfuss_defaults_from_ini(): array {
	return cmx_get_belegfuss_raw();
}
