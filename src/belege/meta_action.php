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
	if ($override !== '' && in_array($override, ['rechnung', 'offerte', 'lieferschein'], true) && $default_type !== 'gutschrift') {
		return $override;
	}
	return $default_type;
}

/**
 * PDF-Typ für Darstellung/Dateinamen normalisieren.
 * Regel: Rechnung + Unterkategorie "Ausgabe" (Meta-Richtung "eingang")
 * wird als Lieferantenrechnung geführt.
 * Gleiches gilt für Quittung -> Lieferantenquittung.
 */
function cmxbu_get_beleg_pdf_effective_type(int $post_id, string $type): string {
	$type = \sanitize_key($type);
	if ($type === '') {
		$type = 'rechnung';
	}

	if ($type === 'rechnung' || $type === 'rechnungen' || $type === 'quittung' || $type === 'quittungen') {
		$richtung_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_RICHTUNG')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_RICHTUNG')
			: '_cmx_beleg_richtung';
		$richtung = \sanitize_key((string) \get_post_meta($post_id, $richtung_key, true));
		if ($richtung === 'eingang' || $richtung === 'ausgabe') {
			if ($type === 'quittung' || $type === 'quittungen') {
				return 'lieferantenquittung';
			}
			return 'lieferantenrechnung';
		}
	}

	return $type;
}

function cmxbu_beleg_type_filename_slug(string $type): string {
	return $type;
}

/**
 * Liefert relativen und absoluten PDF-Pfad zum Beleg
 * Rückgabe: [relativer Pfad, absoluter Pfad]
 */
function cmxbu_get_beleg_pdf_paths(\WP_Post $post): array {
	[$title, $type_raw] = cmx_get_beleg_type($post);
	$type = cmxbu_get_beleg_pdf_type_override((int) $post->ID, $type_raw);
	$type = cmxbu_get_beleg_pdf_effective_type((int) $post->ID, $type);
	$file_type = cmxbu_beleg_type_filename_slug($type);

	$title_safe = ($title !== '') ? $title : (string) $post->ID;
	$basename   = \sanitize_title($title_safe . '_' . $file_type);
	$base_dir   = rtrim(CMX_UPLOADS_MISBUERO, '/\\');

	$years = [\date('Y')];
	if (!empty($post->post_date)) {
		$years[] = \date('Y', \strtotime($post->post_date));
	}
	if (!empty($post->post_modified)) {
		$years[] = \date('Y', \strtotime($post->post_modified));
	}
	$years = array_values(array_unique(array_filter($years)));

	foreach ($years as $year) {
		$dir_candidates = [
			[
				'dir'        => $base_dir . '/archiv/' . $year . '/belege/',
				'rel_prefix' => 'archiv/' . $year . '/belege/',
			],
			[
				'dir'        => $base_dir . '/' . $year . '/belege/',
				'rel_prefix' => $year . '/belege/',
			],
		];

		foreach ($dir_candidates as $candidate) {
			$dir = (string) ($candidate['dir'] ?? '');
			$rel_prefix = (string) ($candidate['rel_prefix'] ?? '');
			if ($dir === '' || $rel_prefix === '') {
				continue;
			}

			$abs = $dir . $basename . '.pdf';
			if (is_file($abs)) {
				return [$rel_prefix . $basename . '.pdf', $abs];
			}

			// Fallback: bezahlte Belege können ein Datumspräfix haben
			foreach ((array) \glob($dir . '????-??-??_' . $basename . '.pdf') as $prefixed) {
				if (is_file($prefixed)) {
					return [$rel_prefix . basename($prefixed), $prefixed];
				}
			}

			$legacy_types = [];
			if ($file_type !== $type) {
				$legacy_types[] = $type;
			}
			if ($type_raw !== '' && $type_raw !== $type && $type_raw !== $file_type) {
				$legacy_types[] = $type_raw;
			}
			foreach (array_values(array_unique($legacy_types)) as $legacy_type) {
				$old_base = \sanitize_title($title_safe . '_' . $legacy_type);
				$old_abs = $dir . $old_base . '.pdf';
				if (is_file($old_abs)) {
					return [$rel_prefix . $old_base . '.pdf', $old_abs];
				}
				foreach ((array) \glob($dir . '????-??-??_' . $old_base . '.pdf') as $prefixed) {
					if (is_file($prefixed)) {
						return [$rel_prefix . basename($prefixed), $prefixed];
					}
				}
			}
		}
	}

	// Fallback: Standardpfad zurückgeben, auch wenn Datei noch nicht existiert
	$pdf_rel_path = 'archiv/' . \date('Y') . '/belege/' . $basename . '.pdf';
	$pdf_abs_path = $base_dir . '/' . $pdf_rel_path;

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

function cmxbu_beleg_public_filename(int $post_id): string {
	$post = \get_post($post_id);
	if (!$post instanceof \WP_Post || $post->post_type !== 'belege') {
		return 'Beleg.pdf';
	}

	[, $type_raw] = cmx_get_beleg_type($post);
	$type = cmxbu_get_beleg_pdf_type_override($post_id, (string) $type_raw);
	$type = cmxbu_get_beleg_pdf_effective_type($post_id, $type);
	$labels = [
		'rechnung'            => 'Rechnung',
		'rechnungen'           => 'Rechnung',
		'offerte'              => 'Offerte',
		'offerten'             => 'Offerte',
		'lieferschein'         => 'Lieferschein',
		'lieferscheine'        => 'Lieferschein',
		'lieferantenrechnung'  => 'Lieferantenrechnung',
		'lieferantenquittung'  => 'Lieferantenquittung',
		'quittung'             => 'Quittung',
		'quittungen'           => 'Quittung',
		'gutschrift'           => 'Gutschrift',
		'gutschriften'         => 'Gutschrift',
		'mahnung'              => 'Mahnung',
		'mahnungen'            => 'Mahnung',
	];
	$label = (string) ($labels[\sanitize_key($type)] ?? 'Beleg');
	$number = \trim((string) $post->post_title);
	if ($number === '') {
		$number = (string) $post_id;
	}
	$number = \trim((string) \preg_replace('/[\\x00-\\x1F\\x7F\\\\\/]+/u', '-', \wp_strip_all_tags($number)), " .-");
	if ($number === '') {
		$number = (string) $post_id;
	}

	return $label . ' - ' . $number . '.pdf';
}

function cmxbu_get_beleg_public_url(int $post_id, string $token, array $args = []): string {
	$token = \sanitize_text_field($token);
	if ($post_id <= 0 || $token === '') {
		return '';
	}

	$filename = cmxbu_beleg_public_filename($post_id);
	$query = \array_merge(['beleg' => $token, 'vorschau' => '1'], $args);
	return \esc_url_raw((string) \add_query_arg($query, \home_url('/' . \rawurlencode($filename))));
}

function cmxbu_beleg_uploads_meta_key(): string {
	return \defined(__NAMESPACE__ . '\\CMX_BELEG_UPLOADS_META')
		? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_UPLOADS_META')
		: '_cmx_belege_uploads';
}

function cmxbu_beleg_rel_upload_to_abs(string $rel_path): string {
	$rel_path = \ltrim($rel_path, "/\\");
	if ($rel_path === '') {
		return '';
	}
	return \rtrim((string) \WP_CONTENT_DIR, '/\\') . '/uploads/' . $rel_path;
}

function cmxbu_is_beleg_archive_abs_path(string $abs_path): bool {
	if ($abs_path === '') {
		return false;
	}
	$normalized = \wp_normalize_path($abs_path);
	$uploads_base = \trailingslashit(\wp_normalize_path((string) \WP_CONTENT_DIR . '/uploads/misbuero'));
	if (\strpos($normalized, $uploads_base) !== 0) {
		return false;
	}
	return \strpos($normalized, '/belege/') !== false || \strpos($normalized, '/belegeingang/') !== false;
}

function cmxbu_collect_beleg_upload_attachment_ids(int $post_id): array {
	$uploads = (array) \get_post_meta($post_id, cmxbu_beleg_uploads_meta_key(), true);
	$ids = [];
	foreach ($uploads as $entry) {
		if (!\is_numeric($entry)) {
			continue;
		}
		$id = (int) $entry;
		if ($id > 0) {
			$ids[] = $id;
		}
	}
	return \array_values(\array_unique($ids));
}

function cmxbu_get_beleg_primary_upload_abs_path(int $post_id): string {
	$post_id = (int) $post_id;
	if ($post_id <= 0) {
		return '';
	}

	$uploads = (array) \get_post_meta($post_id, cmxbu_beleg_uploads_meta_key(), true);
	// Neueste Upload-Datei bevorzugen: neue Einträge werden am Ende gespeichert.
	for ($i = \count($uploads) - 1; $i >= 0; $i--) {
		$entry = $uploads[$i] ?? null;
		$abs_path = '';
		if (\is_numeric($entry)) {
			$abs_path = (string) \get_attached_file((int) $entry);
		} else {
			$abs_path = cmxbu_beleg_rel_upload_to_abs((string) $entry);
		}
		if ($abs_path === '' || !\is_file($abs_path)) {
			continue;
		}
		if (!cmxbu_is_beleg_archive_abs_path($abs_path)) {
			continue;
		}
		return $abs_path;
	}

	$legacy_url = (string) \get_post_meta($post_id, 'datei_url', true);
	if ($legacy_url !== '') {
		$legacy_path = \wp_parse_url($legacy_url, \PHP_URL_PATH);
		if (\is_string($legacy_path) && $legacy_path !== '') {
			$uploads_marker = '/uploads/';
			$marker_pos = \strpos($legacy_path, $uploads_marker);
			if ($marker_pos !== false) {
				$rel = (string) \substr($legacy_path, $marker_pos + \strlen($uploads_marker));
				$abs_path = cmxbu_beleg_rel_upload_to_abs($rel);
				if ($abs_path !== '' && \is_file($abs_path) && cmxbu_is_beleg_archive_abs_path($abs_path)) {
					return $abs_path;
				}
			}
		}
	}

	return '';
}

function cmxbu_collect_beleg_archive_paths(int $post_id, \WP_Post $post): array {
	$paths = [];

	[, $pdf_abs_path] = cmxbu_get_beleg_pdf_paths($post);
	if (\is_string($pdf_abs_path) && $pdf_abs_path !== '') {
		$paths[] = $pdf_abs_path;
	}

	$uploads = (array) \get_post_meta($post_id, cmxbu_beleg_uploads_meta_key(), true);
	foreach ($uploads as $entry) {
		if (\is_numeric($entry)) {
			$attached = (string) \get_attached_file((int) $entry);
			if ($attached !== '') {
				$paths[] = $attached;
			}
			continue;
		}
		$rel = \ltrim((string) $entry, "/\\");
		if ($rel !== '') {
			$paths[] = cmxbu_beleg_rel_upload_to_abs($rel);
		}
	}

	$legacy_url = (string) \get_post_meta($post_id, 'datei_url', true);
	if ($legacy_url !== '') {
		$legacy_path = \wp_parse_url($legacy_url, \PHP_URL_PATH);
		if (\is_string($legacy_path) && $legacy_path !== '') {
			$uploads_marker = '/uploads/';
			$marker_pos = \strpos($legacy_path, $uploads_marker);
			if ($marker_pos !== false) {
				$rel = (string) \substr($legacy_path, $marker_pos + \strlen($uploads_marker));
				if ($rel !== '') {
					$paths[] = cmxbu_beleg_rel_upload_to_abs($rel);
				}
			}
		}
	}

	$paths = \array_map('strval', $paths);
	$paths = \array_filter($paths, static fn(string $path): bool => $path !== '');
	return \array_values(\array_unique($paths));
}

function cmxbu_cleanup_beleg_archive_files(int $post_id): void {
	$post = \get_post($post_id);
	if (!$post || $post->post_type !== 'belege') {
		return;
	}

	$paths = cmxbu_collect_beleg_archive_paths($post_id, $post);
	$attachment_ids = cmxbu_collect_beleg_upload_attachment_ids($post_id);

	foreach ($attachment_ids as $attachment_id) {
		$attached = (string) \get_attached_file((int) $attachment_id);
		if ($attached !== '' && cmxbu_is_beleg_archive_abs_path($attached)) {
			\wp_delete_attachment((int) $attachment_id, true);
		}
	}

	foreach ($paths as $abs_path) {
		if (!cmxbu_is_beleg_archive_abs_path($abs_path)) {
			continue;
		}
		if (\is_file($abs_path)) {
			@unlink($abs_path);
		}
	}

	\delete_post_meta($post_id, cmxbu_beleg_uploads_meta_key());
	\delete_post_meta($post_id, '_cmx_beleg_upload_prefix');
	\delete_post_meta($post_id, 'datei_url');

	$token_option_key = 'cmx_beleg_token_for_' . $post_id;
	$token = \get_option($token_option_key);
	if (\is_string($token) && $token !== '') {
		\delete_option('cmx_beleg_token_data_' . $token);
	}
	\delete_option($token_option_key);
}

function cmxbu_cleanup_beleg_archive_files_on_delete(int $post_id): void {
	$post = \get_post($post_id);
	if (!$post || $post->post_type !== 'belege') {
		return;
	}
	cmxbu_cleanup_beleg_archive_files($post_id);
}
\add_action('before_delete_post', __NAMESPACE__ . '\\cmxbu_cleanup_beleg_archive_files_on_delete', 20, 1);

// Einzelne Teil-Module einbinden (UI + Handler, aber KEINE Token-Erzeugung mehr)
require_once __DIR__ . '/meta_action_send.php';
require_once __DIR__ . '/meta_action_link.php';
require_once __DIR__ . '/meta_action_download.php';
