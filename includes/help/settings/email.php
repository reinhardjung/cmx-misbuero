<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');
return [
	'intro' => 'Hier pflegst du Postfächer, Alias-Adressen und Versandparameter.',
	'overview' => [
		'Dieser Bereich enthält die technischen Mail-Einstellungen für Versand und Empfang.',
		'Untertabs trennen Beleg-bezogene Mailoptionen von Client-Konfigurationen.',
		'Absender, Alias und SMTP/IMAP-Daten sollten zusammen stimmig sein.',
	],
	'workflow' => [
		'Nach Änderungen an Serverdaten die Verbindung testen.',
		'Alias-Adressen nur verwenden, wenn sie im Mailserver korrekt eingerichtet sind.',
		'Vor Belegversand Absenderadresse, Antwortadresse und Beleg-Alias gegenprüfen.',
	],
];
