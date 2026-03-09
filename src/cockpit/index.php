<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

$widgets = ['date_range','stammdaten','rechnungen','rechnungen_faellig','lieferantenrechungen','kontakt_daten','gutschriften','quittungen','kuchen_ein_aus_ok','kuchen_ein_aus_nok','overview_revenue'];

foreach ($widgets as $file) {
	$path = __DIR__ . '/' . $file . '.php';
	if (is_readable($path)) {
		require_once $path;
	}
}
