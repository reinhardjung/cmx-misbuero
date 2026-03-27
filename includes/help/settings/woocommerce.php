<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

return [
	'intro' => 'Hier richtest Du die Anbindung an Deinen externen WooCommerce Online-Shop ein.<br><i>(Die Version Deines Woo Shops wird autom. anhand einer Bestellung ermittelt.)</i>',

	'overview' => [
		'',
	],

	'workflow' => [
		'Allgemeine Apassungen zu Deinem externen <b>Woo Online-Shop.</b>',
		'Die Daten werden nur vom Shop ins Mis Büro übertragen. <i>(keine Syncronisation)</i>',
	],

	'tabs' => [
		[
			'title' => 'Secret Key',
			'content' =>
				'Dein <strong>Secret Key</strong> kannst Du mir dem Button [Secret neu erzeugen] ganz rechts auf der Zeile neu erzeigen.<br><br>'.
				'Beim Klick auf das Icon kopieren, wird der akt. Secret Key in den Zwischenspeicher kopiert<br>'.
				'und Du kannst ihn dann in Deinem Woo Shop <code>WP-Admin → WooCommerce → Einstellungen → Erweitert → Webhooks</code> eintragen.',
		],
		[
			'title' => 'Webhook URL',
			'content' =>
				'Deine <strong>Webhook URL</strong> kannst Du auch eifnach üer das Icon kopieren und in Deinen Woo Shop übertragen.',
		],
		[
			'title' => 'Beispiel-URL einer Bestellung',
			'content' =>
				'In Deinem Woo Shop geht Du einfach auf eine Bestellung, kopierst Dir die URL und trägst sie dann hier ein.',
		],
	],
];



	// 		'content' => '
	// 			<ul>
	// 				<li>Erste Zeile<br>Zweite Zeile im selben Bullet</li>
	// 				<li>Noch ein Punkt</li>
	// 			</ul>
	// 		',
