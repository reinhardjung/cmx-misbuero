<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Tab: Allgemein
 */
\add_action('admin_init', __NAMESPACE__ . '\\cmx_register_general_tab');
function cmx_register_general_tab(): void {

	\add_settings_section(
		'cmx_sec_general',
		__('Allgemein', 'default'),
		'__return_false',
		'cmx_tab_general'
	);

	// MwSt-Pflicht Checkbox
	\add_settings_field(
		'mwst_pflichtig',
		'MwSt-pflichtig J/N.',
		function () {
			\CLOUDMEISTER\CMX\Buero\cmx_field_checkbox([
				'key'   => 'mwst_pflichtig',
				'label' => 'Ja, MwSt wird ausgewiesen',
			]);
		},
		'cmx_tab_general',
		'cmx_sec_general'
	);

	\add_settings_field(
		'qr_reference',
		'QR-Referenz (QRR/SCOR)',
		function () {
			$opts = get_option('cmx_einstellungen', []);
			$val  = esc_attr($opts['qr_reference'] ?? '');
			echo '<input type="text" name="cmx_einstellungen[qr_reference]" value="'.$val.'" class="regular-text" placeholder="21 00000 00000 00000 00000 00000 oder RFxx...">';
			echo '<p class="description">QRR: 27-stellige Referenz, SCOR: RF-Referenz. Das System wählt automatisch den passenden Typ.</p>';
		},
		'cmx_tab_general',
		'cmx_sec_general'
	);

	// Wenn nötig: register_setting() ebenfalls hier setzen
	// \register_setting('cmx_einstellungen','cmx_einstellungen');
}



 \add_filter('pre_update_option_cmx_einstellungen', function ($new, $old) {

	// QRR sauber extrahieren
	$qrr = isset($new['qr_reference'])
		? preg_replace('~\D+~', '', $new['qr_reference'])
		: '';

	// Prüfen ob QRR gültig (27 Stellen + Modulo 10)
	$is_valid_qrr = false;
	if (strlen($qrr) === 27 && ctype_digit($qrr)) {

		$table = [0,9,4,6,8,2,7,1,3,5];
		$c = 0;

		for ($i=0; $i<26; $i++) {
			$c = $table[($c + (int)$qrr[$i]) % 10];
		}

		$check = (10 - $c) % 10;

		if ($check === (int)$qrr[26]) {
			$is_valid_qrr = true;
		}
	}

	$raw_ref = trim((string)($new['qr_reference'] ?? ''));
	$is_scor = false;
	if ($raw_ref !== '' && preg_match('/^RF[0-9A-Z]{2,}$/i', str_replace(' ', '', $raw_ref))) {
		$is_scor = true;
	}

	// Modus automatisch setzen
	if ($is_valid_qrr) {
		$new['qr_mode'] = 'QRR';
	} elseif ($is_scor) {
		$new['qr_mode'] = 'SCOR';
	} else {
		$new['qr_mode'] = 'NON';
	}

	// Referenz formatiert speichern
	if ($is_valid_qrr) {
		$new['qr_reference'] = substr($qrr,0,2).' '
		                     .substr($qrr,2,5).' '
		                     .substr($qrr,7,5).' '
		                     .substr($qrr,12,5).' '
		                     .substr($qrr,17,5).' '
		                     .substr($qrr,22,5);
	} elseif ($is_scor) {
		$scor = strtoupper(str_replace(' ', '', $raw_ref));
		$new['qr_reference'] = trim(chunk_split($scor, 4, ' '));
	}

	return $new;

}, 10, 2);
