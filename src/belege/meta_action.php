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

function cmxbu_get_beleg_pdf_type_override(int $post_id, string $default_type): string {
	$override = (string) \get_post_meta($post_id, '_cmx_beleg_pdf_type', true);
	if ($override !== '' && in_array($override, ['rechnung', 'angebot', 'lieferschein'], true) && $default_type !== 'gutschrift') {
		return $override;
	}
	return $default_type;
}

/**
 * Liefert relativen und absoluten PDF-Pfad zum Beleg
 * Rückgabe: [relativer Pfad, absoluter Pfad]
 */
function cmxbu_get_beleg_pdf_paths(\WP_Post $post): array {
	[$title, $type] = cmx_get_beleg_type($post);
	$type = cmxbu_get_beleg_pdf_type_override((int) $post->ID, $type);

	$title_safe = ($title !== '') ? $title : (string) $post->ID;
	$basename   = \sanitize_title($title_safe . '_' . $type);
	$base_dir   = rtrim(CMX_UPLOADS_MISBUERO, '/\\') . '/';

	$years = [\date('Y')];
	if (!empty($post->post_date)) {
		$years[] = \date('Y', \strtotime($post->post_date));
	}
	if (!empty($post->post_modified)) {
		$years[] = \date('Y', \strtotime($post->post_modified));
	}
	$years = array_values(array_unique(array_filter($years)));

	foreach ($years as $year) {
		$dir = $base_dir . $year . '/belege/';
		$abs = $dir . $basename . '.pdf';
		if (is_file($abs)) {
			return [$year . '/belege/' . $basename . '.pdf', $abs];
		}

		// Fallback: bezahlte Belege können ein Datumspräfix haben
		foreach ((array) \glob($dir . '????-??-??_' . $basename . '.pdf') as $prefixed) {
			if (is_file($prefixed)) {
				return [$year . '/belege/' . basename($prefixed), $prefixed];
			}
		}
	}

	// Fallback: Standardpfad zurückgeben, auch wenn Datei noch nicht existiert
	$pdf_rel_path = \date('Y') . '/belege/' . $basename . '.pdf';
	$pdf_abs_path = $base_dir . $pdf_rel_path;

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
