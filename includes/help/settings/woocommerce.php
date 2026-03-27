<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

return [
	'intro' => 'Hier richtest Du die Anbindung an Deinen externen WooCommerce-Shop ein.',

	'overview' => [
		'Dein Secret Key kannst Du mir dem Button [Secret neu erzeugen] ganz rechts auf der Zeile neu erzeigen.',
		'Beim Klick auf das Icon kopieren, wird der akt. Secret Key in den Zwischenspeicher kopiert',
		'und Du kannst ihn dann in Deinem WooCommerce Shop WP-Admin → WooCommerce → Einstellungen → Erweitert → Webhooks nutzen.',
		'&nbsp;',
		'und Du kannst ihn dann in Deinem WooCommerce Shop WP-Admin → WooCommerce → Einstellungen → Erweitert → Webhooks nutzen.',
	],

	'workflow' => [
		'Zuerst Verbindungsdaten prüfen, danach Webhook-Konfiguration im Shop testen.',
		'Nach Änderungen mindestens einen Probe-Import oder Testlauf durchführen.',
		'Automatische Abläufe nur aktivieren, wenn die Zielpfade stabil funktionieren.',
	],

	'tabs' => [
		'Ballaal' => [
			'Zuerst Verbindungsdaten prüfen, danach Webhook-Konfiguration im Shop testen.',
			'Nach Änderungen mindestens einen Probe-Import oder Testlauf durchführen.',
			'Automatische Abläufe nur aktivieren, wenn die Zielpfade stabil funktionieren.',
		],
		'susi2' => [
			'Naaa geht sod.rbindungsdaten prüfen, danach Webhook-Konfiguration im Shop testen.',
			'Nach Änderungen mindestens einen Probe-Import oder Testlauf durchführen.',
			'Automatische Abläufe nur aktivieren, wenn die Zielpfade stabil funktionieren.',
		],
	],
];
