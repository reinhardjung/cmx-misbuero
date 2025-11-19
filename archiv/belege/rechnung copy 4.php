<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


$logo_url = 'https://cloudmeister.ch/wp-content/uploads/cloudmeister_logo.png';
$alt_text = 'Zur Website vom CLOUD Meister O&Uuml;';

ob_start();
?>
<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="UTF-8">
	<title>Rechnung </title>
	<style>
		body { font-family: sans-serif; font-size: 12px; color: #333; }
		h1 { font-size: 18px; }
		table { width: 100%; border-collapse: collapse; margin-top: 20px; }
		th, td { padding: 6px; text-align: left; }
		td.brief { color:grey !important; font-style: italic; }
		table th { border-bottom: 1px solid #000 !important; }
		.position { border-bottom: .5px groove #000 !important; }
		.gesamt { border-bottom: 2px double #000 !important; }
		.total { font-weight: bold; }
		.footer { margin-top: 30px; font-size: 11px; }
		a, a:hover, a:visited, a:active, a:focus { color:grey !important; text-decoration: none; }
		@page { margin: 20px 30px 80px 50px; }
		table, td, th { border: none; }
		.footer { position: fixed; bottom: -60px; left: 0; right: 0; height: 50px; text-align: center; font-size: 12px; }
		.footer:after { content: "Seite " counter(page) " von " counter(pages); }
	</style>
</head>
<body>

<table>
	<tr>
		<td align="right">LLL
		</td>
	</tr>
</table>

<table>
	<tr>
		<td width="80"><br>Datum<br>F&auml;llig bis<br>Leistung f&uuml;r</td>
		<td width="120" style="text-align:right;"><br>
		</td>
		<td width="250"></td>
		<td>
			<span style="font-size:8px; color:grey; font-style:italic;">
			</span><br>
			<span style="font-size:15px;">
				<strong>
			</span>
		</td>
	</tr>
</table>

<h1>RNG </h1>

<p><i>URL</i></p>

<table>
	<thead>
	<tr>
		<th>Artikel</th>
		<th>Beschreibung</th>
		<th style="text-align:right;">Menge</th>
		<th style="text-align:right;">Einzelpreis</th>
		<th style="text-align:right;">CHF &nbsp;</th>
	</tr>
	</thead>
	<tbody>


	<tr>
		<td colspan="2">
			<i style="font-size: 9px;">
				MwSt# EE102523760<br>befreit nach Art. 146 EU-Richtlinie
			</i>
		</td>
		<td colspan="2" class="gesamt"><strong>Gesamtbetrag</strong><br>exkl. MwSt.</td>
		<td class="gesamt" style="text-align:right;vertical-align:top;">
			sdfsd
		</td>
	</tr>
	</tbody>
	<tfoot>
	<tr>
		<td colspan="5" style="font-size:10px;">
			<br><br><strong>Hinweis zur Zahlung</strong>
			&nbsp;<a href="https://cloudmeister.ch/agb/" style="font-size:8px;" target="_blank" rel="noopener">AGB</a><br>
			Alle Preise beinhalten bereits s&auml;mtliche Bankgeb&uuml;hren.<br>
			Der Gesamtbetrag ist <strong>ohne Abzug von Skonto oder Bankspesen</strong> vollst&auml;ndig zu entrichten.
		</td>
	</tr>
	</tfoot>
</table>

<div class="footer">
	<table>
	<tr>
		<td class="brief" style="text-align:left;">
			<b>Empfänger</b><br>
			<?php echo esc_html($store_address['company']); ?><br>
			<?php echo esc_html($store_address['strasse']); ?><br>
			<?php echo esc_html($store_address['land'].'-'.$store_address['plz'].' '.$store_address['ort']); ?><br>
		</td>
		<td class="brief" style="text-align:center;">
			<b>Bank</b><br>
			<?php echo esc_html($account_details[0]['bank_name']); ?><br>
			<?php echo esc_html($account_details[0]['iban']); ?><br>
			<?php echo esc_html($account_details[0]['bic']); ?>
		</td>
		<td class="brief" style="text-align:right;">
			<b>Kontakt</b><br>
			<a href="tel:0443418000">044 341 80 00</a><br>
			<a href="mailto:invoice@cloudmeister.ch">invoice@cloudmeister.ch</a><br>
			<a href="mailto:support@cloudmeister.ch">support@cloudmeister.ch</a>
		</td>
	</tr>
	</table>
</div>

</body>
</html>
<?php
$html = ob_get_clean();

$base_dir = CMX_UPLOADS_MISBUERO . '/' .date('Y') .'/';
$filename = $getPost['dateiname'] . '.html';
if($filename != '_'.$getPost['beleg_type'] .'.html') {
	$target    = $base_dir . $filename;
	file_put_contents( $target, $html );
}


// === Dompdf konfigurieren ===
$options = new Options();
$options->set('isRemoteEnabled', true); // erlaubt externe Ressourcen (CSS, Bilder, Fonts)
$dompdf = new Dompdf($options);

// === HTML-Datei laden ===
$base_dir = CMX_UPLOADS_MISBUERO . '/' .date('Y') .'/';
$filename = $getPost['dateiname'] . '.html';
$target    = $base_dir . $filename;
$html = file_get_contents($target); // Pfad zu Deiner HTML-Datei
// file_put_contents( $target, $html );

$dompdf->loadHtml($html);  // === HTML in PDF umwandeln ===
$dompdf->setPaper('A4', 'portrait');  // === Papierformat und Ausrichtung ===
$dompdf->render();  // === PDF rendern ===

var_dump($output); exit;

// === PDF speichern (optional) ===
$output = $dompdf->output();
// file_put_contents( $target, $html );
file_put_contents( $target, $output);

// === oder direkt im Browser anzeigen ===
// $dompdf->stream('rechnung.pdf', ['Attachment' => false]);
