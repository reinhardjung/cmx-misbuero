<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


$logo_url = 'https://cloudmeister.ch/wp-content/uploads/cloudmeister_logo.png';
$alt_text = 'Zur Website vom CLOUD Meister O&Uuml;';

// var_dump('/wp-content/uploads/rechnung_' .str_replace(' ', '-', $order->get_order_number()) . '.pdf'); exit;

$getData = [];
$getData['vorname']				= 'reinhard';
$getData['bestellnr']			= 'bestellNr';

// var_dump($data); exit;

ob_start();
?>
<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="UTF-8">
	<title>Rechnung <?php echo esc_html( $order->get_order_number() ); ?></title>
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
		<td align="right">
			<?php if ($logo_url): ?>
				<a href="<?php echo esc_url($logo_url); ?>">
					<img width="70" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($alt_text); ?>">
				</a>
			<?php endif; ?>
		</td>
	</tr>
</table>

<table>
	<tr>
		<td width="80"><br>Datum<br>F&auml;llig bis<br>Leistung f&uuml;r</td>
		<td width="120" style="text-align:right;"><br>
			<?php echo esc_html( get_post_meta( $order->get_id(), 'cmx_date_document', true ) ); ?><br>
			<?php echo esc_html( get_post_meta( $order->get_id(), 'cmx_date_done', true ) ); ?><br>
			<?php
				$period = str_pad( (string) get_post_meta( $order->get_id(), 'cmx_date_period', true ), 2, '0', STR_PAD_LEFT );
				echo esc_html( cmx_month_label( $period ) );
			?>
		</td>
		<td width="250"></td>
		<td>
			<span style="font-size:8px; color:grey; font-style:italic;">
				&nbsp;&nbsp;&nbsp;&nbsp;<?php echo esc_html($store_address['company']); ?>
				&bull; <?php echo esc_html($store_address['strasse']); ?>
				&bull; <?php echo esc_html($store_address['land'].'-'.$store_address['plz'].' '.$store_address['ort']); ?>
			</span><br>
			<span style="font-size:15px;">
				<?php echo esc_html( $order->get_billing_company() ); ?><br>
				<?php echo esc_html( $order->get_billing_address_1() ); ?><br>
				<strong><?php echo esc_html( $order->get_billing_country() . '-' . $order->get_billing_postcode() . ' ' . $order->get_billing_city() ); ?></strong>
			</span>
		</td>
	</tr>
</table>

<h1>RNG <?php echo esc_html( $order->get_order_number() ); ?></h1>

<p><i><?php echo esc_html( $the_user_url ); ?></i></p>

<table>
	<thead>
	<tr>
		<th>Artikel</th>
		<th>Beschreibung</th>
		<th style="text-align:right;">Menge</th>
		<th style="text-align:right;">Einzelpreis</th>
		<th style="text-align:right;"><?php echo esc_html( get_woocommerce_currency() ); ?>&nbsp;</th>
	</tr>
	</thead>
	<tbody>
	<?php foreach ( $order->get_items() as $item ): ?>
		<?php
		$product   = $item->get_product();
		$sku       = $product ? $product->get_sku() : '';
		$qty       = (float) $item->get_quantity();
		$line_total= (float) $item->get_total();
		$unit      = $qty > 0 ? $line_total / $qty : 0.0;

		$desc = '';
		if ( $product ) {
			$product_id = $product->get_id();
			$parent_id  = $product->get_parent_id();
			$desc = get_post_meta( $product_id, '_product_invoice_description', true );
			if ( ! $desc && $parent_id ) {
				$desc = get_post_meta( $parent_id, '_product_invoice_description', true );
			}
		}
		?>
		<tr>
			<td class="position" style="width:80px;vertical-align:top;"><?php echo esc_html( $sku ); ?></td>
			<td class="position" style="width:400px;vertical-align:top;">
				<strong><?php echo esc_html( $item->get_name() ); ?></strong><br>
				<?php echo esc_html( $desc ); ?>
			</td>
			<td class="position" style="text-align:right;vertical-align:top;"><?php echo esc_html( wc_format_decimal($qty, 2) ); ?>&nbsp;</td>
			<td class="position" style="text-align:right;vertical-align:top;"><?php echo wp_kses_post( wc_price( $unit, ['currency'=> $order->get_currency()] ) ); ?>&nbsp;</td>
			<td class="position" style="text-align:right;vertical-align:top;"><?php echo wp_kses_post( wc_price( $line_total, ['currency'=> $order->get_currency()] ) ); ?>&nbsp;</td>
		</tr>
	<?php endforeach; ?>

	<?php if ( $shipping_total > 0 ) : ?>
		<tr class="total">
			<td colspan="3"></td>
			<td style="text-align:right;">Zwischensumme</td>
			<td style="text-align:right;"><?php echo wp_kses_post( wc_price( (float) $order->get_subtotal(), ['currency'=> $order->get_currency()] ) ); ?>&nbsp;</td>
		</tr>
		<tr class="total">
			<td colspan="3"></td>
			<td style="text-align:right;">Versand</td>
			<td style="text-align:right;"><?php echo wp_kses_post( wc_price( $shipping_total, ['currency'=> $order->get_currency()] ) ); ?>&nbsp;</td>
		</tr>
	<?php endif; ?>

	<tr>
		<td colspan="2">
			<i style="font-size: 9px;">
				MwSt# EE102523760<br>befreit nach Art. 146 EU-Richtlinie
			</i>
		</td>
		<td colspan="2" class="gesamt"><strong>Gesamtbetrag</strong><br>exkl. MwSt.</td>
		<td class="gesamt" style="text-align:right;vertical-align:top;">
			<strong><?php echo wp_kses_post( wc_price( (float) $order->get_total(), ['currency'=> $order->get_currency()] ) ); ?></strong>&nbsp;
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

// Zielpfad /dav/
$uploads   = wp_get_upload_dir();
$base_dir  = trailingslashit( $uploads['basedir'] ) . 'misbuero/dav/';
wp_mkdir_p( $base_dir );

$filename  = sanitize_file_name( $order->get_order_number() . '_rechnung.html' );
$target    = $base_dir . $filename;

file_put_contents( $target, $html );
