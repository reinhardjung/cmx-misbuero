<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Basis-Pfad für MisBüro-Uploads
 * z.B. wp-content/uploads/misbuero/2025/...
 */
if (!defined('CMX_UPLOADS_MISBUERO')) {
	define('CMX_UPLOADS_MISBUERO', WP_CONTENT_DIR . '/uploads/misbuero/');
}

/**
 * Meta-Box "Für Kunde..."
 */
add_action('add_meta_boxes', __NAMESPACE__ . '\\cmxbu_add_beleg_metabox');
function cmxbu_add_beleg_metabox(): void {
	add_meta_box( 'cmx_beleg_download', __('F&uuml;r Kunde...', 'default'), __NAMESPACE__ . '\\cmxbu_render_beleg_metabox', 'belege', 'side', 'high');
}

/**
 * Wrapper, rendert beide Bereiche (Senden + Download/Copy)
 */
function cmxbu_render_beleg_metabox(\WP_Post $post) {
	?>
	<style>
		.cmx-beleg-actions { overflow:hidden; padding-top:8px; }
		.cmx-beleg-actions form { margin: 0; }
		.cmx-beleg-actions .alignleft { float: left; }
		.cmx-beleg-actions .alignright { float: right; }
	</style>
	<div class="cmx-beleg-actions">
		<?php
		// Button "Senden..." (kommt aus meta_action_send.php)
		if (function_exists(__NAMESPACE__ . '\\cmxbu_render_beleg_send_metabox')) {
			cmxbu_render_beleg_send_metabox($post);
		}

		// Download + Copy-Button (kommt aus meta_action_link.php)
		if (function_exists(__NAMESPACE__ . '\\cmxbu_render_beleg_download_metabox_with_copy')) {
			cmxbu_render_beleg_download_metabox_with_copy($post);
		}
		?>
	</div>
	<?php
}

/**
 * Ermittelt Beleg-Typ anhand Taxonomie
 * Rückgabe: [Titel, typ-slug]
 */
function cmx_get_beleg_type(\WP_Post $post): array {
	$post_id    = (int) $post->ID;
	$beleg_type = 'rechnung';

	foreach (['belege_kategorien', 'beleg_kategorie'] as $tax) {
		$slugs = wp_get_post_terms($post_id, $tax, ['fields' => 'slugs']);
		if (!is_wp_error($slugs) && !empty($slugs)) {
			$beleg_type = (string) $slugs[0];
			break;
		}
	}

	return [get_post($post_id)->post_title, $beleg_type];
}

// Einzelne Teil-Module einbinden
require_once __DIR__ . '/meta_action_send.php';
require_once __DIR__ . '/meta_action_link.php';
require_once __DIR__ . '/meta_action_download.php';
