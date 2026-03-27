<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


return [
	'intro' => 'Liste aller Kontakte. Hier kannst Du alle Kontakte nach belieben filtern um sie bestmöglichst betreuen zuu können.',

	'overview' => [
		'<strong>vCard</strong> kann nur importiert werden',
		'Beim <strong>exportieren</strong> werden alle Kontakte inkl. deren Bilder exportiert. <em>(perfekt für Dich als Backup)</em>',
		'<strong>importieren</strong> ist dann das Gegestück zum exportieren.</em>',
	],

	'tabs' => [
		[
			'title' => 'Allgemein',
			'content' => 'Bevor Du Belege also Rechnungen etc. schreiben kannst, musst Du immer erst einen Kontakt dazu haben.',
		],
	],

	'post' => [
		'Texte für bestehenden Kontakt bearbeiten.',
	],

	'edit' => [
		'Du musst mindestens einen bzw. zwei Kontakte anlegen: Deinen eigenen und einen Kunden, dem Du eine Rechnung schreiben könntest.',
		'Wenn Du keinen Treuhänder brauchst, kannst Du diesse Funktion deaktivieren',
	],

	'tabs_by_screen' => [
		'edit' => [
			[
				'title' => 'E-Mail 1',
				'content' =>
					'Die erste E-Mail in der Kommunikation aller Ansprechparter wird hier angezeigt.<br>'.
					'Um diese zu ändern gehst Du in den gewünschten Kontakt und verschiebst den gewünschten Ansprechpartner an die erste Stelle in der Liste.<br>',
			],
		],
		'post' => [
			[
				'title' => 'Bearbeiten',
				'content' => 'Nur beim Bearbeiten eines bestehenden Kontakts sichtbar.',
			],
		],
		'post-new' => [
			[
				'title' => 'Neu anlegen',
				'content' => 'Nur beim Neuanlegen sichtbar.',
			],
		],
		'term' => [
			[
				'title' => 'Taxonomien',
				'content' => 'Nur auf Taxonomie-Screens sichtbar.',
			],
		],
	],
];
