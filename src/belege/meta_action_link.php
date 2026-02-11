<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * Download-/Copy-Bereich innerhalb der "Für Kunde..."-Metabox
 */
function cmxbu_render_beleg_download_metabox_with_copy(\WP_Post $post): void {

	// Stabilen Token für diesen Beleg holen (wird nur einmalig erzeugt)
	$token = cmxbu_get_stable_token($post->ID);

	[, $pdf_abs_path] = cmxbu_get_beleg_pdf_paths($post);

	// Wenn Datei nicht existiert → Buttons disabled
	// if (!is_file($pdf_abs_path)) {
	// 	echo '<a href="#" class="button button-secondary alignright" style="pointer-events:none; opacity:0.5; color:silver; border:silver solid 1px;">copy</a>';
	// 	echo '<a href="#" class="button button-secondary alignright" style="pointer-events:none; opacity:0.5; color:silver; border:silver solid 1px;">download</a>';
	// 	return;
	// }

	// Download-URL über stabilen Token
	$download_url = \add_query_arg('beleg', $token, \home_url('/'));

	// Download-Button
	// echo '<a href="' . \esc_url($download_url) . '" target="_blank" class="button button-secondary alignright cmx-btn-transparent cmx-btn-download"style="color:#a42c24; border:#a42c24 solid 1px;">download</a>';
	echo '<a href="' . esc_url($download_url) . '" target="_blank" rel="noopener noreferrer" title="Download als PDF" class="button button-secondary alignright cmx-btn-transparent cmx-btn-download" style="color:#a42c24; border:#a42c24 solid 1px;" aria-label="Download als PDF"><span class="dashicons dashicons-pdf" style="margin-top:5px;"></span></a>';

}
