<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


return [
	'intro' => 'Liste aller Kontakte. Hier kannst Du alle Kontakte nach belieben filtern um sie bestmöglichst betreuen zuu können.',

	'overview' => [
		'<strong>vCard</strong> kann nur importiert werden',
		'Beim <strong>exportieren</strong> werden alle Kontakte inkl. deren Bilder exportiert. <em>(perfekt für Dich als Backup)</em>',
		'<strong>importieren</strong> ist dann das Gegestück zum exportieren.',
		'Wenn Du <strong>doppelte Kontakte</strong> löschen möchtest werden alle Kontakte auf den Namen geprüft und ältere Kontakte des gleichen Namens gelöscht.',
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
		'Wenn Du keinen Treuhänder brauchst, kannst Du diese <a href="/wp-admin/admin.php?page=cmx-einstellungen&tab=vorgaben&sub=belege" target="_blank" rel="noopener noreferrer">Funktion deaktivieren</a>.',
	],

	'tabs_by_screen' => [
		'edit' => [
			[
				'title' => 'Name',
				'content' =>
					'Der Name ist die Bezeichnunng die Du als Name des Kontaktes gegeben hast<br><br>'.
					'Teilweise wird er automatisch vergeben. Du kannst ihn aber jederzeit wieder ändern',
			],
			[
				'title' => 'Kategorie',
				'content' =>
					'Das ist die Hauptkategorie eines Kontaktes',
			],
			[
				'title' => 'Stufen',
				'content' =>
					'Hier legst Du die Qualität des Kontaktes fest.<br><br><b>A</b> ist der perfekte Kontakt (Kunde/Lieferant<7etc.).<br>Mit <b>F</b> Kontakten möchtest Du eigentlich nichts mehr zu tun haben.'.
					'Kannst sie aber nicht löschen, da Du ja in der Regel bereits Belege dazu hast?',
			],
			[
				'title' => 'Telefon 1',
				'content' =>
					'Die erste Telefon Nr in der Kommunikation aller Ansprechparter wird hier angezeigt.<br><br>'.
					'Um diese zu ändern gehst Du in den gewünschten Kontakt und verschiebst den gewünschten Ansprechpartner an die erste Stelle in der Liste.<br>',
			],
			[
				'title' => 'E-Mail 1',
				'content' =>
					'Die erste E-Mail in der Kommunikation aller Ansprechparter wird hier angezeigt.<br><br>'.
					'Um diese zu ändern gehst Du in den gewünschten Kontakt und verschiebst den gewünschten Ansprechpartner an die erste Stelle in der Liste.<br>'.
					'Du kannst hier direkt Deinem Kontakt eine Mail schreiben (intern oder extern).',
			],
			[
				'title' => 'Karte',
				'content' =>
					'Die erste Telefon-Nr in der Kommunikation aller Ansprechparter wird hier angezeigt.<br>'.
					'Um diese zu ändern gehst Du in den gewünschten Kontakt und verschiebst den gewünschten Ansprechpartner an die erste Stelle in der Liste.<br>'.
					'Du kannst hier direkt Deinen Kontaktanrufen.',
			],
			[
				'title' => 'URL',
				'content' =>
					'Wenn Du mal weitere Infos zu Deinem Kontakt suchst, kannst Du hier einfach direkt auf seine Website springen.',
			],
			[
				'title' => 'Kundenportal',
				'content' =>
					'Wenn Du das in den Einstellungen erlaubst, kann der Kontakt sich seine Belege selsbt anschauen und bezahlen'.
					'Klick einfach mal darauf und Du kannst sehen was Dein Kontakt sehen wird.',
			],
			[
				'title' => 'Firmengründung',
				'content' =>
					'Kaum ein Firmeninhaber kennt das Datum siener Firmengründung.<br>'.
					'Umso netter ist es ihn daran zu erinnern und ihm weiterhin viel Erfolg zu wünschen?',
			],
			[
				'title' => 'Geburtsdatum',
				'content' =>
					'Jeder freut sich über eine nete Mail an Seinem Geburtstag.<br>'.
					'Idealerweise mit einem kleinen Geschenk verbunden? Einem Rabatt oder ähnlichem?',
			],
			[
				'title' => 'Logo',
				'content' =>
					'Das Logo sollte idealerweise im PNG-Format und eine Grösse von 1920x1080px haben. Zumind. für das Logo von Deinem eigenen Kontakt <code>Das bin ich</code>',
			],
			[
				'title' => 'Status',
				'content' =>
					'Es stellt sich die Frage, ob Du deisen Kontakt überhaupt noch kontaktieren solltest?',
			],
			[
				'title' => 'PDF',
				'content' =>
					'Hier siehst Du direkt das zuletzt hinzugefügte Dokument',
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
