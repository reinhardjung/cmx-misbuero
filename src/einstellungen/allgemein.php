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

	// \add_settings_field(
	// 	'qr_reference',
	// 	'QR-Referenz (QRR)',
	// 	function () {
	// 		$opts = get_option('cmx_einstellungen', []);
	// 		$val  = esc_attr($opts['qr_reference'] ?? '');
	// 		echo '<input type="text" name="cmx_einstellungen[qr_reference]" value="'.$val.'" class="regular-text" placeholder="21 00000 00000 00000 00000 00000">';
	// 		echo '<p class="description">Falls du eine QRR-Referenz hast, hier eintragen. Das System schaltet automatisch auf QRR um.</p>';
	// 	},
	// 	'cmx_tab_general',
	// 	'cmx_sec_general'
	// );

	// Wenn nötig: register_setting() ebenfalls hier setzen
	// \register_setting('cmx_einstellungen','cmx_einstellungen');
}



// \add_filter('pre_update_option_cmx_einstellungen', function ($new, $old) {

// 	// QRR sauber extrahieren
// 	$qrr = isset($new['qr_reference'])
// 		? preg_replace('~\D+~', '', $new['qr_reference'])
// 		: '';

// 	// Prüfen ob QRR gültig (27 Stellen + Modulo 10)
// 	$is_valid_qrr = false;
// 	if (strlen($qrr) === 27 && ctype_digit($qrr)) {

// 		$table = [0,9,4,6,8,2,7,1,3,5];
// 		$c = 0;

// 		for ($i=0; $i<26; $i++) {
// 			$c = $table[($c + (int)$qrr[$i]) % 10];
// 		}

// 		$check = (10 - $c) % 10;

// 		if ($check === (int)$qrr[26]) {
// 			$is_valid_qrr = true;
// 		}
// 	}

// 	// Modus automatisch setzen
// 	$new['qr_mode'] = $is_valid_qrr ? 'QRR' : 'NON';

// 	// Referenz formatiert speichern
// 	if ($is_valid_qrr) {
// 		$new['qr_reference'] = substr($qrr,0,2).' '
// 		                     .substr($qrr,2,5).' '
// 		                     .substr($qrr,7,5).' '
// 		                     .substr($qrr,12,5).' '
// 		                     .substr($qrr,17,5).' '
// 		                     .substr($qrr,22,5);
// 	}

// 	return $new;

// }, 10, 2);
