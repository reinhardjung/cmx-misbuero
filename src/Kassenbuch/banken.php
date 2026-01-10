<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/** Ermittelt die als Standard aktivierte Bank und gibt die wichtigsten Felder zurück. Rückgabe: [] wenn keine Standardbank gesetzt ist.
 * var_dump(cmx_get_standard_bank_data()['iban']); exit;
 */

function cmx_get_standard_bank_data(): array { // Settings-Key sicher bestimmen (Fallback, falls die Konstante noch nicht geladen ist)
	$settings_key = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_KEY') ? CMX_SETTINGS_KEY : \apply_filters('cmx_settings_key', 'cmx_einstellungen');

	$opts = \get_option($settings_key, []);
	if (!\is_array($opts)) return [];


	$banks = [ // Mapping gemäss deinen add_settings_field-Keys
		'rev'    => ['enabled' => 'rev_enabled',    'name' => 'rev_bank_name',    'iban' => 'rev_iban',    'api' => 'rev_api',    'recipient' => 'rev_recipient'],
		'zkb'    => ['enabled' => 'zkb_enabled',    'name' => 'zkb_bank_name',    'iban' => 'zkb_iban',    'api' => 'zkb_api',    'recipient' => 'zkb_recipient'],
		'ubs'    => ['enabled' => 'ubs_enabled',    'name' => 'ubs_bank_name',    'iban' => 'ubs_iban',    'api' => 'ubs_api',    'recipient' => 'ubs_recipient'],
		'migros' => ['enabled' => 'migros_enabled', 'name' => 'migros_bank_name', 'iban' => 'migros_iban', 'api' => 'migros_api', 'recipient' => 'migros_recipient'],
		'eisen'  => ['enabled' => 'eisen_enabled',  'name' => 'eisen_bank_name',  'iban' => 'eisen_iban',  'api' => 'eisen_api',  'recipient' => 'eisen_recipient'],
	];

	$order = ['rev','zkb','ubs','migros','eisen']; // Prüf-Reihenfolge (falls mehrere aktiviert sind)

	foreach ($order as $code) {
		if (empty($banks[$code])) continue;
		$m = $banks[$code];

		if (!empty($opts[$m['enabled']])) return [ 'code' => $code, 'bank_name' => (string)($opts[$m['name']] ?? ''), 'iban' => (string)($opts[$m['iban']] ?? ''), 'api_key' => (string)($opts[$m['api']] ?? ''), 'recipient' => (string)($opts[$m['recipient']] ?? ''),];
	}

	return []; // Keine Standardbank gesetzt
}
