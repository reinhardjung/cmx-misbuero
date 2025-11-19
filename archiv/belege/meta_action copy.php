<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


add_action('add_meta_boxes', __NAMESPACE__ . '\\cmxbu_add_beleg_metabox');
function cmxbu_add_beleg_metabox(): void {
	add_meta_box('cmx_beleg_download',__('F&uuml;r Kunde...', 'default'),__NAMESPACE__ . '\\cmxbu_render_beleg_metabox','belege','side','high');
}


function cmxbu_render_beleg_metabox(\WP_Post $post) {
	?>
	<style>
		.cmx-beleg-actions { overflow:hidden; padding-top:8px; } /* verhindert das Hochrutschen der Buttons */
		.cmx-beleg-actions form { margin: 0; }
		.cmx-beleg-actions .alignleft { float: left; }
		.cmx-beleg-actions .alignright { float: right; }
	</style>

	<div class="cmx-beleg-actions">
		<?php
			cmxbu_render_beleg_send_metabox($post);
			cmxbu_render_beleg_download_metabox($post);
		?>
	</div>
	<?php
}


function cmx_get_beleg_type(\WP_Post $post): array {
	$post_id   = (int) $post->ID;
	$beleg_type='rechnung';
	foreach (['belege_kategorien','beleg_kategorie'] as $tax) {
		$slugs=wp_get_post_terms($post_id,$tax,['fields'=>'slugs']);
		if (!is_wp_error($slugs) && !empty($slugs)) { $beleg_type=(string)$slugs[0]; break; }
	}
	return [get_post($post_id)->post_title,$beleg_type];
}


require_once 'meta_action_send.php';
require_once 'meta_action_download.php';



/**
 * DAV-Zugangsdaten (kannst Du auch aus Option, .env oder Konstante holen)
 */
const CMX_DAV_USER = 'cloudmeister';
const CMX_DAV_PASS = 'Ibdg5adP!-4D';


/**
 * Speichert die PDF-URL für einen Beleg
 *
 * @param int    $post_id
 * @param string $pdf_url
 */
function cmx_speichere_beleg_pdf_link(int $post_id, string $pdf_url): void {
	if ($post_id > 0 && filter_var($pdf_url, FILTER_VALIDATE_URL)) {
		update_post_meta($post_id, '_cmx_pdf_url', esc_url_raw($pdf_url));
	}
}


/**
 * Liefert die gespeicherte PDF aus dem DAV-Verzeichnis aus
 *
 * @param int $post_id
 */
function cmx_liefere_beleg_pdf(int $post_id): void {

	$pdf_url = get_post_meta($post_id, '_cmx_pdf_url', true);

	if (!$pdf_url || !filter_var($pdf_url, FILTER_VALIDATE_URL)) {
		wp_die('PDF-Link ungültig oder nicht vorhanden.');
	}

	$context = stream_context_create([
		'http' => [
			'header'  => "Authorization: Basic " . base64_encode(CMX_DAV_USER . ':' . CMX_DAV_PASS),
			'timeout' => 10,
		]
	]);

	$data = file_get_contents($pdf_url, false, $context);

	if ($data === false) {
		wp_die('Die Datei konnte nicht geladen werden.');
	}

	header('Content-Type: application/pdf');
	header('Content-Disposition: attachment; filename="' . basename($pdf_url) . '"');
	header('Content-Length: ' . strlen($data));
	echo $data;
	exit;
}


/**
 * Öffentlicher Aufruf via URL: ?cmxdl=1234
 */
add_action('init', function () {
	if (!isset($_GET['cmxdl'])) {
		return;
	}
	$post_id = (int) $_GET['cmxdl'];
	cmx_liefere_beleg_pdf($post_id);
});


$pdf_url = 'https://deinedomain.ch/DAV/2025/rechnung_1234.pdf';
cmx_speichere_beleg_pdf_link($post->ID, $pdf_url);
