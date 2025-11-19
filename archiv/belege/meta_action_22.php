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
 * Speichert die PDF-URL für den späteren Download
 *
 * @param int    $post_id
 * @param string $pdf_url
 */
function cmx_store_pdf_url(int $post_id, string $pdf_url): void {
	update_post_meta($post_id, '_cmx_pdf_url', $pdf_url);
}


/**
 * Speichert den Link zur PDF im Beleg-Metafeld "_cmx_pdf_url"
 *
 * @param int    $post_id   Die ID des Belegs
 * @param string $pdf_url   Der vollständige DAV-Link zur PDF
 */
function cmx_speichere_beleg_pdf_link(int $post_id, string $pdf_url): void {
	if ($post_id > 0 && filter_var($pdf_url, FILTER_VALIDATE_URL)) {
		update_post_meta($post_id, '_cmx_pdf_url', esc_url_raw($pdf_url));
	}
}
