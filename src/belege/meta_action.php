<?php namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

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
if (isset($_GET['post'])) {
	add_action('add_meta_boxes', __NAMESPACE__ . '\\cmxbu_add_beleg_metabox');
	function cmxbu_add_beleg_metabox(): void {
		add_meta_box(
			'cmx_beleg_download',
			__('F&uuml;r Kunde...', 'default'),
			__NAMESPACE__ . '\\cmxbu_render_beleg_metabox',
			'belege',
			'side',
			'high'
		);
	}
}


/**
 * Wrapper, rendert beide Bereiche (Senden + Download/Copy)
 */
function cmxbu_render_beleg_metabox(\WP_Post $post): void {
	?>
	<style>
		.cmx-beleg-actions { overflow:hidden; padding-top:8px; }
		.cmx-beleg-actions form { margin: 0; }
		.cmx-beleg-actions .alignleft { float: left; }
		.cmx-beleg-actions .alignright { float: right; }
	</style>
	<div class="cmx-beleg-actions">
		<?php
		// Button "Senden..."
		if (function_exists(__NAMESPACE__ . '\\cmxbu_render_beleg_send_metabox')) {
			cmxbu_render_beleg_send_metabox($post);
		}

		// Download + Copy-Button
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
		$slugs = \wp_get_post_terms($post_id, $tax, ['fields' => 'slugs']);
		if (!\is_wp_error($slugs) && !empty($slugs)) {
			$beleg_type = (string) $slugs[0];
			break;
		}
	}

	$title = \get_post($post_id)->post_title ?? '';

	return [$title, $beleg_type];
}

/**
 * Liefert relativen und absoluten PDF-Pfad zum Beleg
 * Rückgabe: [relativer Pfad, absoluter Pfad]
 */
function cmxbu_get_beleg_pdf_paths(\WP_Post $post): array {
	[$title, $type] = cmx_get_beleg_type($post);

	// Beispiel: 251126-055729_rechnung.pdf → 2025/251126-055729_rechnung.pdf
	$pdf_rel_path = '20' . substr($title, 0, 2) . '/' . $title . '_' . $type . '.pdf';
	$base_dir     = rtrim(CMX_UPLOADS_MISBUERO, '/\\') . '/';
	$pdf_abs_path = $base_dir . ltrim($pdf_rel_path, '/\\');

	return [$pdf_rel_path, $pdf_abs_path];
}

/**
 * Stabilen Token pro Beleg holen oder einmalig erzeugen.
 * - Option: cmx_beleg_token_for_{post_id} = token
 * - Option: cmx_beleg_token_data_{token} = ['post_id' => X]
 */
function cmxbu_get_stable_token(int $post_id): string {
	$post_id    = (int) $post_id;
	$option_key = 'cmx_beleg_token_for_' . $post_id;

	$token = \get_option($option_key);
	if (\is_string($token) && $token !== '') {
		return $token;
	}

	// einmalige Erzeugung
	$token = \wp_generate_password(20, false, false);

	\update_option($option_key, $token, false);
	\update_option(
		'cmx_beleg_token_data_' . $token,
		['post_id' => $post_id],
		false
	);

	return $token;
}

// Einzelne Teil-Module einbinden (UI + Handler, aber KEINE Token-Erzeugung mehr)
require_once __DIR__ . '/meta_action_send.php';
require_once __DIR__ . '/meta_action_link.php';
require_once __DIR__ . '/meta_action_download.php';
