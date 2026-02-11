<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit;

/* ------------------------------------------------------------
 * SECTION DESCRIPTIONS
 * ------------------------------------------------------------ */
function cmx_section_rev_desc(): void {
	echo '<p class="description">Keine Schweizer QR-Rechnung möglich – EUR.</p>';
	echo '<h2 class="title" style="margin-top:8px;">Revolut im Banking</h2>';
}

function cmx_section_zkb_desc(): void {
	echo '<p class="description">ZKB – CH-Konto, geeignet für QR-Rechnungen.</p>';
	echo '<h2 class="title" style="margin-top:8px;">ZKB im Banking</h2>';
}

function cmx_section_ubs_desc(): void {
	echo '<p class="description">UBS – CH-Konto, geeignet für QR-Rechnungen.</p>';
	echo '<h2 class="title" style="margin-top:8px;">UBS im Banking</h2>';
}

function cmx_section_migros_desc(): void {
	echo '<p class="description">Migros – CH-Konto, geeignet für QR-Rechnungen.</p>';
	echo '<h2 class="title" style="margin-top:8px;">Migros Bank im Banking</h2>';
}

function cmx_section_eisen_desc(): void {
	echo '<p class="description">Raiffeisen – CH-Konto, geeignet für QR-Rechnungen.</p>';
	echo '<h2 class="title" style="margin-top:8px;">Raiffeisen im Banking</h2>';
}

/* ------------------------------------------------------------
 * SETTINGS-FELDER REGISTRIEREN
 * ------------------------------------------------------------ */
add_action('admin_init', __NAMESPACE__ . '\\cmx_register_banken_tab');
function cmx_register_banken_tab(): void {

	/* ========== REVOLUT ========== */
	$page = 'cmx_tab_banken__rev';

	add_settings_section('cmx_sec_banken_rev', '', __NAMESPACE__ . '\\cmx_section_rev_desc', $page);

	add_settings_field('rev_enabled', 'Diese Bank als Standard',
		__NAMESPACE__ . '\\cmx_field_checkbox', $page, 'cmx_sec_banken_rev',
		['key' => 'rev_enabled', 'label' => 'Ja, diese Bank nutzen']
	);

	add_settings_field('rev_bank_name', 'Bankname',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_rev',
		['key' => 'rev_bank_name', 'placeholder' => 'Revolut']
	);

	add_settings_field('rev_recipient', 'Empfänger',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_rev',
		['key' => 'rev_recipient', 'placeholder' => 'Dein Firmenname']
	);

	add_settings_field('rev_iban', 'IBAN',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_rev',
		['key' => 'rev_iban', 'placeholder' => 'LTxx xxxx xxxx xxxx xxxx x']
	);

	add_settings_field('rev_qr_iban', 'QR-IBAN',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_rev',
		['key' => 'rev_qr_iban', 'placeholder' => 'CHxx xxxx xxxx xxxx xxxx x (für QR)']
	);

	add_settings_field('rev_bic', 'BIC / SWIFT',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_rev',
		['key' => 'rev_bic', 'placeholder' => 'REVOGB21XXX']
	);

	/* ========== ZKB ========== */
	$page = 'cmx_tab_banken__zkb';

	add_settings_section('cmx_sec_banken_zkb', '', __NAMESPACE__ . '\\cmx_section_zkb_desc', $page);

	add_settings_field('zkb_enabled', 'Diese Bank als Standard',
		__NAMESPACE__ . '\\cmx_field_checkbox', $page, 'cmx_sec_banken_zkb',
		['key' => 'zkb_enabled', 'label' => 'Ja, diese Bank nutzen']
	);

	add_settings_field('zkb_bank_name', 'Bankname',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_zkb',
		['key' => 'zkb_bank_name', 'placeholder' => 'Zürcher Kantonalbank']
	);

	add_settings_field('zkb_recipient', 'Empfänger',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_zkb',
		['key' => 'zkb_recipient', 'placeholder' => 'Dein Firmenname']
	);

	add_settings_field('zkb_iban', 'IBAN',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_zkb',
		['key' => 'zkb_iban', 'placeholder' => 'CHxx xxxx xxxx xxxx xxxx x']
	);

	add_settings_field('zkb_qr_iban', 'QR-IBAN',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_zkb',
		['key' => 'zkb_qr_iban', 'placeholder' => 'CHxx xxxx xxxx xxxx xxxx x (für QR)']
	);

	add_settings_field('zkb_bic', 'BIC / SWIFT',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_zkb',
		['key' => 'zkb_bic', 'placeholder' => 'ZKBKCHZZ80A']
	);

	/* ========== UBS ========== */
	$page = 'cmx_tab_banken__ubs';
	add_settings_section('cmx_sec_banken_ubs', '', __NAMESPACE__ . '\\cmx_section_ubs_desc', $page);

	add_settings_field('ubs_enabled', 'Diese Bank als Standard',
		__NAMESPACE__ . '\\cmx_field_checkbox', $page, 'cmx_sec_banken_ubs',
		['key' => 'ubs_enabled', 'label' => 'Ja, diese Bank nutzen']
	);

	add_settings_field('ubs_bank_name', 'Bankname',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_ubs',
		['key' => 'ubs_bank_name', 'placeholder' => 'UBS']
	);

	add_settings_field('ubs_recipient', 'Empfänger',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_ubs',
		['key' => 'ubs_recipient', 'placeholder' => 'Dein Firmenname']
	);

	add_settings_field('ubs_iban', 'IBAN',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_ubs',
		['key' => 'ubs_iban', 'placeholder' => 'CHxx xxxx xxxx xxxx xxxx x']
	);

	add_settings_field('ubs_qr_iban', 'QR-IBAN',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_ubs',
		['key' => 'ubs_qr_iban', 'placeholder' => 'CHxx xxxx xxxx xxxx xxxx x (für QR)']
	);

	add_settings_field('ubs_bic', 'BIC / SWIFT',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_ubs',
		['key' => 'ubs_bic', 'placeholder' => 'UBSWCHZH80A']
	);

	/* ========== MIGROS ========== */
	$page = 'cmx_tab_banken__migros';
	add_settings_section('cmx_sec_banken_migros', '', __NAMESPACE__ . '\\cmx_section_migros_desc', $page);

	add_settings_field('migros_enabled', 'Diese Bank als Standard',
		__NAMESPACE__ . '\\cmx_field_checkbox', $page, 'cmx_sec_banken_migros',
		['key' => 'migros_enabled', 'label' => 'Ja, diese Bank nutzen']
	);

	add_settings_field('migros_bank_name', 'Bankname',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_migros',
		['key' => 'migros_bank_name', 'placeholder' => 'Migros Bank']
	);

	add_settings_field('migros_recipient', 'Empfänger',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_migros',
		['key' => 'migros_recipient', 'placeholder' => 'Dein Firmenname']
	);

	add_settings_field('migros_iban', 'IBAN',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_migros',
		['key' => 'migros_iban', 'placeholder' => 'CHxx xxxx xxxx xxxx xxxx x']
	);

	add_settings_field('migros_qr_iban', 'QR-IBAN',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_migros',
		['key' => 'migros_qr_iban', 'placeholder' => 'CHxx xxxx xxxx xxxx xxxx x (für QR)']
	);

	add_settings_field('migros_bic', 'BIC / SWIFT',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_migros',
		['key' => 'migros_bic', 'placeholder' => 'MIGRCHZZXXX']
	);

	/* ========== RAIFFEISEN ========== */
	$page = 'cmx_tab_banken__eisen';
	add_settings_section('cmx_sec_banken_eisen', '', __NAMESPACE__ . '\\cmx_section_eisen_desc', $page);

	add_settings_field('eisen_enabled', 'Diese Bank als Standard',
		__NAMESPACE__ . '\\cmx_field_checkbox', $page, 'cmx_sec_banken_eisen',
		['key' => 'eisen_enabled', 'label' => 'Ja, diese Bank nutzen']
	);

	add_settings_field('eisen_bank_name', 'Bankname',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_eisen',
		['key' => 'eisen_bank_name', 'placeholder' => 'Raiffeisen']
	);

	add_settings_field('eisen_recipient', 'Empfänger',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_eisen',
		['key' => 'eisen_recipient', 'placeholder' => 'Dein Firmenname']
	);

	add_settings_field('eisen_iban', 'IBAN',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_eisen',
		['key' => 'eisen_iban', 'placeholder' => 'CHxx xxxx xxxx xxxx xxxx x']
	);

	add_settings_field('eisen_qr_iban', 'QR-IBAN',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_eisen',
		['key' => 'eisen_qr_iban', 'placeholder' => 'CHxx xxxx xxxx xxxx xxxx x (für QR)']
	);

	add_settings_field('eisen_bic', 'BIC / SWIFT',
		__NAMESPACE__ . '\\cmx_field_text', $page, 'cmx_sec_banken_eisen',
		['key' => 'eisen_bic', 'placeholder' => 'RAIFCH22XXX']
	);
}

/* ------------------------------------------------------------
 * AKTIVE BANK
 * ------------------------------------------------------------ */
function cmx_get_active_bank(): ?array {

	$banks = [
		'rev' => [
			'enabled' => cmx_get_option('rev_enabled'),
			'name'    => cmx_get_option('rev_bank_name'),
			'recipient' => cmx_get_option('rev_recipient'),
			'iban'    => cmx_get_option('rev_iban'),
			'qr_iban' => cmx_get_option('rev_qr_iban'),
			'bic'     => cmx_get_option('rev_bic'),
			'api'     => cmx_get_option('rev_api'),
			'label'   => 'Revolut',
			'qr_supported' => false,
		],
		'zkb' => [
			'enabled' => cmx_get_option('zkb_enabled'),
			'name'    => cmx_get_option('zkb_bank_name'),
			'recipient' => cmx_get_option('zkb_recipient'),
			'iban'    => cmx_get_option('zkb_iban'),
			'qr_iban' => cmx_get_option('zkb_qr_iban'),
			'bic'     => cmx_get_option('zkb_bic'),
			'api'     => cmx_get_option('zkb_api'),
			'label'   => 'ZKB',
			'qr_supported' => true,
		],
		'ubs' => [
			'enabled' => cmx_get_option('ubs_enabled'),
			'name'    => cmx_get_option('ubs_bank_name'),
			'recipient' => cmx_get_option('ubs_recipient'),
			'iban'    => cmx_get_option('ubs_iban'),
			'qr_iban' => cmx_get_option('ubs_qr_iban'),
			'bic'     => cmx_get_option('ubs_bic'),
			'api'     => cmx_get_option('ubs_api'),
			'label'   => 'UBS',
			'qr_supported' => true,
		],
		'migros' => [
			'enabled' => cmx_get_option('migros_enabled'),
			'name'    => cmx_get_option('migros_bank_name'),
			'recipient' => cmx_get_option('migros_recipient'),
			'iban'    => cmx_get_option('migros_iban'),
			'qr_iban' => cmx_get_option('migros_qr_iban'),
			'bic'     => cmx_get_option('migros_bic'),
			'api'     => cmx_get_option('migros_api'),
			'label'   => 'Migros Bank',
			'qr_supported' => true,
		],
		'eisen' => [
			'enabled' => cmx_get_option('eisen_enabled'),
			'name'    => cmx_get_option('eisen_bank_name'),
			'recipient' => cmx_get_option('eisen_recipient'),
			'iban'    => cmx_get_option('eisen_iban'),
			'qr_iban' => cmx_get_option('eisen_qr_iban'),
			'bic'     => cmx_get_option('eisen_bic'),
			'api'     => cmx_get_option('eisen_api'),
			'label'   => 'Raiffeisen',
			'qr_supported' => true,
		],
	];

	foreach ($banks as $key => $bank) {
		if (!empty($bank['enabled'])) {
			return array_merge(['key' => $key], $bank);
		}
	}

	return null;
}
