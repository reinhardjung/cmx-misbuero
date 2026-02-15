<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

$widgets = ['stammdaten','rechnungen','rechnungen_faellig','lieferantenrechungen','kontakt_daten','gutschriften','kuchen_ein_aus_ok','kuchen_ein_aus_nok'];

foreach ($widgets as $file) {
	$path = __DIR__ . '/' . $file . '.php';
	if (is_readable($path)) {
		require_once $path;
	}
}
