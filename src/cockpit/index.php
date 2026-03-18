<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

$widgets = ['date_range','stammdaten','rechnungen','angebote','online_shop','rechnungen_faellig','lieferantenrechungen','kontakt_daten','gutschriften','quittungen','kuchen_ein_aus_ok','kuchen_ein_aus_nok','overview_revenue','projekte','view_pendenzen','view_monitor'];

foreach ($widgets as $file) {
	$path = __DIR__ . '/' . $file . '.php';
	if (is_readable($path)) {
		require_once $path;
	}
}
