<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

const CMX_QR_SIZE      = 256; // PNG-Kantenlänge in px
const CMX_QR_MARGIN    = 8;   // QR-Rand in px


add_action('add_meta_boxes', __NAMESPACE__ . '\\cmx_artikel_qr_add_box');
function cmx_artikel_qr_add_box(): void {
	add_meta_box('cmx_artikel_qr_box','QR-Code zum Artikel',__NAMESPACE__ . '\\cmx_artikel_qr_render_box','artikel','side','low');
}


function cmx_artikel_qr_render_box(\WP_Post $post): void {
	$sku = trim((string) get_post_meta($post->ID, CMX_ARTIKEL_META_SKU, true));
	if ($sku === '') {
		echo '<p><em>Noch keine Artikel-Nr gespeichert.</em></p>';
		return;
	}

	$target_url = home_url('/' . rawurlencode($sku));

	try {
		// QR erstellen
		$qr = new QrCode($target_url);
		$qr->setSize((int) CMX_QR_SIZE);
		$qr->setMargin((int) CMX_QR_MARGIN);

		// PNG generieren
		$writer = new PngWriter();
		$result = $writer->write($qr);
		$png    = $result->getString();

		// Data-URI für Bild & Download-Link
		$base64   = base64_encode($png);
		$data_uri = 'data:image/png;base64,' . $base64;

		// Dateiname
		$filename = 'qr-' . preg_replace('~[^a-zA-Z0-9._-]+~', '_', $sku) . '.png';

		// Ausgabe
		echo '<div id="cmx-art-qr-inline-box" style="margin-top:8px">';
		echo '  <a href="' . esc_attr($data_uri) . '" download="' . esc_attr($filename) . '" title="QR-Code als PNG herunterladen">';
		echo '    <img src="' . esc_attr($data_uri) . '" alt="QR-Code ' . esc_attr($sku) . '" style="width:' . (int)CMX_QR_SIZE . 'px;height:' . (int)CMX_QR_SIZE . 'px; border:1px solid #ccd0d4;border-radius:4px;background:#fff;display:block" /></a>';
		echo '  <p title="' . esc_html($target_url) .'" style="margin-top:6px"><small>Auf den QR-Code klicken zum downloaden</small></p>';
		// echo '  <p style="margin-top:6px"><small>Ziel: <code>' . esc_html($target_url) . '</code></small></p>';
		echo '</div>';
	} catch (\Throwable $e) {
		echo '<p><strong>QR-Fehler:</strong> ' . esc_html($e->getMessage()) . '</p>';
	}
}
