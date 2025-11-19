<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * Tab: Banken
 * Alle Bank-Untertabs (Revolut, ZKB, UBS, Migros, Raiffeisen)
 */

add_action('admin_init', __NAMESPACE__ . '\\cmx_register_banken_tab');
function cmx_register_banken_tab(): void {

	/* --------------------------------------------------------------
	 * Revolut (Subtab: rev)
	 * -------------------------------------------------------------- */
	$page_rev = 'cmx_tab_banken__rev';

	if (!function_exists(__NAMESPACE__ . '\\cmx_section_rev_desc')) {
		function cmx_section_rev_desc(): void {
			echo '<p class="description">' . esc_html__('Keine Schweizer QR-Code Rechnung möglich – da EUR-Zahlung.', 'default') . '</p>';
			echo '<h2 class="title" style="margin-top:8px;">' . esc_html__('Revolut im Banking', 'default') . '</h2>';
		}
	}

	add_settings_section(
		'cmx_sec_banken_rev',
		'',
		__NAMESPACE__ . '\\cmx_section_rev_desc',
		$page_rev
	);

	add_settings_field(
		'rev_enabled',
		__('Diese Bank als Standard', 'default'),
		__NAMESPACE__ . '\\cmx_field_checkbox',
		$page_rev,
		'cmx_sec_banken_rev',
		[
			'key'   => 'rev_enabled',
			'label' => __('Ja, diese Bank nutzen', 'default'),
		]
	);

	add_settings_field(
		'rev_bank_name',
		__('Bankname', 'default'),
		__NAMESPACE__ . '\\cmx_field_text',
		$page_rev,
		'cmx_sec_banken_rev',
		[
			'key'         => 'rev_bank_name',
			'placeholder' => 'Revolut',
		]
	);

	add_settings_field(
		'rev_recipient',
		__('Empfänger', 'default'),
		__NAMESPACE__ . '\\cmx_field_text',
		$page_rev,
		'cmx_sec_banken_rev',
		[
			'key'         => 'rev_recipient',
			'placeholder' => 'Dein Firmenname',
		]
	);

	add_settings_field(
		'rev_iban',
		__('IBAN', 'default'),
		__NAMESPACE__ . '\\cmx_field_text',
		$page_rev,
		'cmx_sec_banken_rev',
		[
			'key'         => 'rev_iban',
			'placeholder' => 'LTxx xxxx xxxx xxxx xxxx x',
		]
	);

	add_settings_field(
		'rev_api',
		__('API Key', 'default'),
		__NAMESPACE__ . '\\cmx_field_text',
		$page_rev,
		'cmx_sec_banken_rev',
		[
			'key'         => 'rev_api',
			'placeholder' => 'cmx_live_AJ7...',
		]
	);


	/* --------------------------------------------------------------
	 * ZKB (Subtab: zkb)
	 * -------------------------------------------------------------- */
	$page_zkb = 'cmx_tab_banken__zkb';

	if (!function_exists(__NAMESPACE__ . '\\cmx_section_zkb_desc')) {
		function cmx_section_zkb_desc(): void {
			echo '<p class="description">' . esc_html__('Zürcher Kantonalbank – Schweizer CHF-Konto, geeignet für QR-Rechnungen.', 'default') . '</p>';
			echo '<h2 class="title" style="margin-top:8px;">' . esc_html__('ZKB im Banking', 'default') . '</h2>';
		}
	}

	add_settings_section(
		'cmx_sec_banken_zkb',
		'',
		__NAMESPACE__ . '\\cmx_section_zkb_desc',
		$page_zkb
	);

	add_settings_field(
		'zkb_enabled',
		__('Diese Bank als Standard', 'default'),
		__NAMESPACE__ . '\\cmx_field_checkbox',
		$page_zkb,
		'cmx_sec_banken_zkb',
		[
			'key'   => 'zkb_enabled',
			'label' => __('Ja, diese Bank nutzen', 'default'),
		]
	);

	add_settings_field(
		'zkb_bank_name',
		__('Bankname', 'default'),
		__NAMESPACE__ . '\\cmx_field_text',
		$page_zkb,
		'cmx_sec_banken_zkb',
		[
			'key'         => 'zkb_bank_name',
			'placeholder' => 'Zürcher Kantonalbank',
		]
	);

	add_settings_field(
		'zkb_recipient',
		__('Empfänger', 'default'),
		__NAMESPACE__ . '\\cmx_field_text',
		$page_zkb,
		'cmx_sec_banken_zkb',
		[
			'key'         => 'zkb_recipient',
			'placeholder' => 'Dein Firmenname',
		]
	);

	add_settings_field(
		'zkb_iban',
		__('IBAN / QR-IBAN', 'default'),
		__NAMESPACE__ . '\\cmx_field_text',
		$page_zkb,
		'cmx_sec_banken_zkb',
		[
			'key'         => 'zkb_iban',
			'placeholder' => 'CHxx xxxx xxxx xxxx xxxx x',
		]
	);

	add_settings_field(
		'zkb_api',
		__('API Key / Referenz', 'default'),
		__NAMESPACE__ . '\\cmx_field_text',
		$page_zkb,
		'cmx_sec_banken_zkb',
		[
			'key'         => 'zkb_api',
			'placeholder' => 'Optionale interne Kennung',
		]
	);


	/* --------------------------------------------------------------
	 * UBS (Subtab: ubs)
	 * -------------------------------------------------------------- */
	$page_ubs = 'cmx_tab_banken__ubs';

	if (!function_exists(__NAMESPACE__ . '\\cmx_section_ubs_desc')) {
		function cmx_section_ubs_desc(): void {
			echo '<p class="description">' . esc_html__('UBS – Schweizer CHF-Konto, geeignet für QR-Rechnungen.', 'default') . '</p>';
			echo '<h2 class="title" style="margin-top:8px;">' . esc_html__('UBS im Banking', 'default') . '</h2>';
		}
	}

	add_settings_section(
		'cmx_sec_banken_ubs',
		'',
		__NAMESPACE__ . '\\cmx_section_ubs_desc',
		$page_ubs
	);

	add_settings_field(
		'ubs_enabled',
		__('Diese Bank als Standard', 'default'),
		__NAMESPACE__ . '\\cmx_field_checkbox',
		$page_ubs,
		'cmx_sec_banken_ubs',
		[
			'key'   => 'ubs_enabled',
			'label' => __('Ja, diese Bank nutzen', 'default'),
		]
	);

	add_settings_field(
		'ubs_bank_name',
		__('Bankname', 'default'),
		__NAMESPACE__ . '\\cmx_field_text',
		$page_ubs,
		'cmx_sec_banken_ubs',
		[
			'key'         => 'ubs_bank_name',
			'placeholder' => 'UBS',
		]
	);

	add_settings_field(
		'ubs_recipient',
		__('Empfänger', 'default'),
		__NAMESPACE__ . '\\cmx_field_text',
		$page_ubs,
		'cmx_sec_banken_ubs',
		[
			'key'         => 'ubs_recipient',
			'placeholder' => 'Dein Firmenname',
		]
	);

	add_settings_field(
		'ubs_iban',
		__('IBAN / QR-IBAN', 'default'),
		__NAMESPACE__ . '\\cmx_field_text',
		$page_ubs,
		'cmx_sec_banken_ubs',
		[
			'key'         => 'ubs_iban',
			'placeholder' => 'CHxx xxxx xxxx xxxx xxxx x',
		]
	);

	add_settings_field(
		'ubs_api',
		__('API Key / Referenz', 'default'),
		__NAMESPACE__ . '\\cmx_field_text',
		$page_ubs,
		'cmx_sec_banken_ubs',
		[
			'key'         => 'ubs_api',
			'placeholder' => 'Optionale interne Kennung',
		]
	);


	/* --------------------------------------------------------------
	 * Migros Bank (Subtab: migros)
	 * -------------------------------------------------------------- */
	$page_migros = 'cmx_tab_banken__migros';

	if (!function_exists(__NAMESPACE__ . '\\cmx_section_migros_desc')) {
		function cmx_section_migros_desc(): void {
			echo '<p class="description">' . esc_html__('Migros Bank – Schweizer CHF-Konto, geeignet für QR-Rechnungen.', 'default') . '</p>';
			echo '<h2 class="title" style="margin-top:8px;">' . esc_html__('Migros Bank im Banking', 'default') . '</h2>';
		}
	}

	add_settings_section(
		'cmx_sec_banken_migros',
		'',
		__NAMESPACE__ . '\\cmx_section_migros_desc',
		$page_migros
	);

	add_settings_field(
		'migros_enabled',
		__('Diese Bank als Standard', 'default'),
		__NAMESPACE__ . '\\cmx_field_checkbox',
		$page_migros,
		'cmx_sec_banken_migros',
		[
			'key'   => 'migros_enabled',
			'label' => __('Ja, diese Bank nutzen', 'default'),
		]
	);

	add_settings_field(
		'migros_bank_name',
		__('Bankname', 'default'),
		__NAMESPACE__ . '\\cmx_field_text',
		$page_migros,
		'cmx_sec_banken_migros',
		[
			'key'         => 'migros_bank_name',
			'placeholder' => 'Migros Bank',
		]
	);

	add_settings_field(
		'migros_recipient',
		__('Empfänger', 'default'),
		__NAMESPACE__ . '\\cmx_field_text',
		$page_migros,
		'cmx_sec_banken_migros',
		[
			'key'         => 'migros_recipient',
			'placeholder' => 'Dein Firmenname',
		]
	);

	add_settings_field(
		'migros_iban',
		__('IBAN / QR-IBAN', 'default'),
		__NAMESPACE__ . '\\cmx_field_text',
		$page_migros,
		'cmx_sec_banken_migros',
		[
			'key'         => 'migros_iban',
			'placeholder' => 'CHxx xxxx xxxx xxxx xxxx x',
		]
	);

	add_settings_field(
		'migros_api',
		__('API Key / Referenz', 'default'),
		__NAMESPACE__ . '\\cmx_field_text',
		$page_migros,
		'cmx_sec_banken_migros',
		[
			'key'         => 'migros_api',
			'placeholder' => 'Optionale interne Kennung',
		]
	);


	/* --------------------------------------------------------------
	 * Raiffeisen (Subtab: eisen)
	 * -------------------------------------------------------------- */
	$page_eisen = 'cmx_tab_banken__eisen';

	if (!function_exists(__NAMESPACE__ . '\\cmx_section_eisen_desc')) {
		function cmx_section_eisen_desc(): void {
			echo '<p class="description">' . esc_html__('Raiffeisen – Schweizer CHF-Konto, geeignet für QR-Rechnungen.', 'default') . '</p>';
			echo '<h2 class="title" style="margin-top:8px;">' . esc_html__('Raiffeisen im Banking', 'default') . '</h2>';
		}
	}

	add_settings_section(
		'cmx_sec_banken_eisen',
		'',
		__NAMESPACE__ . '\\cmx_section_eisen_desc',
		$page_eisen
	);

	add_settings_field(
		'eisen_enabled',
		__('Diese Bank als Standard', 'default'),
		__NAMESPACE__ . '\\cmx_field_checkbox',
		$page_eisen,
		'cmx_sec_banken_eisen',
		[
			'key'   => 'eisen_enabled',
			'label' => __('Ja, diese Bank nutzen', 'default'),
		]
	);

	add_settings_field(
		'eisen_bank_name',
		__('Bankname', 'default'),
		__NAMESPACE__ . '\\cmx_field_text',
		$page_eisen,
		'cmx_sec_banken_eisen',
		[
			'key'         => 'eisen_bank_name',
			'placeholder' => 'Raiffeisen',
		]
	);

	add_settings_field(
		'eisen_recipient',
		__('Empfänger', 'default'),
		__NAMESPACE__ . '\\cmx_field_text',
		$page_eisen,
		'cmx_sec_banken_eisen',
		[
			'key'         => 'eisen_recipient',
			'placeholder' => 'Dein Firmenname',
		]
	);

	add_settings_field(
		'eisen_iban',
		__('IBAN / QR-IBAN', 'default'),
		__NAMESPACE__ . '\\cmx_field_text',
		$page_eisen,
		'cmx_sec_banken_eisen',
		[
			'key'         => 'eisen_iban',
			'placeholder' => 'CHxx xxxx xxxx xxxx xxxx x',
		]
	);

	add_settings_field(
		'eisen_api',
		__('API Key / Referenz', 'default'),
		__NAMESPACE__ . '\\cmx_field_text',
		$page_eisen,
		'cmx_sec_banken_eisen',
		[
			'key'         => 'eisen_api',
			'placeholder' => 'Optionale interne Kennung',
		]
	);
}




/**
 * Liefert die gerade aktivierte Bank inkl. aller Felder.
 * Reihenfolge: Zuerst „enabled“-Bank, sonst null.
 */
function cmx_get_active_bank(): ?array {

	$banks = [
		'rev' => [
			'enabled'    => cmx_get_option('rev_enabled'),
			'name'       => cmx_get_option('rev_bank_name'),
			'recipient'  => cmx_get_option('rev_recipient'),
			'iban'       => cmx_get_option('rev_iban'),
			'api'        => cmx_get_option('rev_api'),
			'label'      => 'Revolut',
			'qr_supported' => false, // kein CH-QR
		],

		'zkb' => [
			'enabled'    => cmx_get_option('zkb_enabled'),
			'name'       => cmx_get_option('zkb_bank_name'),
			'recipient'  => cmx_get_option('zkb_recipient'),
			'iban'       => cmx_get_option('zkb_iban'),
			'api'        => cmx_get_option('zkb_api'),
			'label'      => 'ZKB',
			'qr_supported' => true,
		],

		'ubs' => [
			'enabled'    => cmx_get_option('ubs_enabled'),
			'name'       => cmx_get_option('ubs_bank_name'),
			'recipient'  => cmx_get_option('ubs_recipient'),
			'iban'       => cmx_get_option('ubs_iban'),
			'api'        => cmx_get_option('ubs_api'),
			'label'      => 'UBS',
			'qr_supported' => true,
		],

		'migros' => [
			'enabled'    => cmx_get_option('migros_enabled'),
			'name'       => cmx_get_option('migros_bank_name'),
			'recipient'  => cmx_get_option('migros_recipient'),
			'iban'       => cmx_get_option('migros_iban'),
			'api'        => cmx_get_option('migros_api'),
			'label'      => 'Migros Bank',
			'qr_supported' => true,
		],

		'eisen' => [
			'enabled'    => cmx_get_option('eisen_enabled'),
			'name'       => cmx_get_option('eisen_bank_name'),
			'recipient'  => cmx_get_option('eisen_recipient'),
			'iban'       => cmx_get_option('eisen_iban'),
			'api'        => cmx_get_option('eisen_api'),
			'label'      => 'Raiffeisen',
			'qr_supported' => true,
		],
	];

	// aktive Bank finden
	foreach ($banks as $key => $bank) {
		if (!empty($bank['enabled'])) {
			return array_merge(['key' => $key], $bank);
		}
	}

	return null; // keine Bank aktiviert
}


// $bank = \CLOUDMEISTER\CMX\Buero\cmx_get_active_bank();
// if ($bank) {
// 	echo "Aktive Bank: " . $bank['label'] . "<br>";
// 	echo "IBAN: " . $bank['iban'] . "<br>";
// 	echo "Empfänger: " . $bank['recipient'] . "<br>";
// } else {
// 	echo "Keine Bank ausgewählt.";
// }

// $bank = cmx_get_active_bank();
// if ($bank && !empty($bank['qr_supported'])) {
// 	$iban = $bank['iban'];
// 	$recipient = $bank['recipient'];
// 	$qr_data = ['iban' => $iban,'receiver' => $recipient,'amount' => $betrag,'currency' => 'CHF',];
// 	// … DOMPDF weiterverarbeiten
// }
