<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


// --------------------------------------------
// HTML generieren und speichern
// --------------------------------------------
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

		.text-right { text-align: right; }
	</style>
</head>
<body>


<table>
	<tr>
		<?php
			$opts = \get_option('cmx_einstellungen', []);
			$logo = isset($opts['beleg_logo_url']) ? esc_url($opts['beleg_logo_url']) : 'https://vorlage.misbuero.ch/wp-content/uploads/favicon.png';
		?>
		<td class="text-right"><a href="https://misbuero.ch/"><img width="70" src="<?php echo $logo; ?>"></a></td>
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

<h1><?php echo cmx_sani_key($getPost['beleg_type'], 'pascal'); ?> <?php echo $getPost['beleg_nr'] ?></h1>

<p><i><?php echo $getPost['beleg_nr'] ?></i></p>

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

// --------------------------------------------
// Verzeichnisse und Dateinamen
// --------------------------------------------
$base_dir  = rtrim(CMX_UPLOADS_MISBUERO, '/').'/'.date('Y').'/';
wp_mkdir_p($base_dir);

$html_path = $base_dir . $getPost['dateiname'] . '.html';
$pdf_path  = $base_dir . $getPost['dateiname'] . '.pdf';

file_put_contents($html_path, $html);  // HTML-Datei speichern (immer)

// --------------------------------------------
// PDF mit Dompdf erzeugen (ohne Vergleichsbedingungen)
// --------------------------------------------
require_once CMX_PLUGIN_DIR . 'vendor/autoload.php';

$options = new \Dompdf\Options();
$options->set('isRemoteEnabled', true);

$dompdf = new \Dompdf\Dompdf($options);
$dompdf->setPaper('A4', 'portrait');

// HTML aus Datei laden (Datei existiert, weil oben geschrieben)
$dompdf->loadHtml(file_get_contents($html_path));
$dompdf->render();

file_put_contents($pdf_path, $dompdf->output());  // PDF speichern

// var_dump($_SERVER['REQUEST_METHOD']); exit;
// HTML & PDF nur löschen, wenn vorhanden
if (is_file($html_path)) @unlink($html_path);
$pdf_weg = $base_dir  = rtrim(CMX_UPLOADS_MISBUERO, '/').'/'.date('Y').'/' .'_' .$getPost['beleg_type'] . '.pdf';
if (is_file($pdf_weg)) @unlink($pdf_weg);
