<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

use Dompdf\Dompdf;
use Dompdf\Options;
use setasign\Fpdi\Fpdi;

if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_PDF_REL_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_PDF_REL_META', '_cmx_carent_vertrag_pdf_rel_path');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_PDF_GENERATED_AT_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_PDF_GENERATED_AT_META', '_cmx_carent_vertrag_pdf_generated_at');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_PDF_DOKUMENT_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_PDF_DOKUMENT_META', '_cmx_carent_vertrag_pdf_dokument_id');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_PDF_ARCHIVE_REL_DIR')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_PDF_ARCHIVE_REL_DIR', '');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_agb_pdf_path')) {
	function cmx_carent_vertrag_agb_pdf_path(): string {
		$option_name = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			: 'cmx_einstellungen';
		$options = (array) \get_option($option_name, []);

		$file_rel = \trim((string) ($options['carent_pdf_file_rel'] ?? ''));
		if ($file_rel !== '') {
			$file_abs = \function_exists(__NAMESPACE__ . '\\cmx_dav_module_file_path')
				? \wp_normalize_path((string) cmx_dav_module_file_path('carent', $file_rel))
				: '';
			if ($file_abs !== '' && \is_file($file_abs) && \is_readable($file_abs)) {
				return $file_abs;
			}
		}

		$attachment_id = (int) ($options['carent_pdf_attachment_id'] ?? 0);
		$attachment_path = $attachment_id > 0 ? \wp_normalize_path((string) \get_attached_file($attachment_id)) : '';
		if ($attachment_path !== '' && \is_file($attachment_path) && \is_readable($attachment_path)) {
			return $attachment_path;
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_agb_pdf_url')) {
	function cmx_carent_vertrag_agb_pdf_url(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_carent_current_pdf_url')) {
			$url = \trim((string) cmx_carent_current_pdf_url());
			if ($url !== '') {
				return $url;
			}
		}

		$option_name = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			: 'cmx_einstellungen';
		$options = (array) \get_option($option_name, []);

		$file_rel = \trim((string) ($options['carent_pdf_file_rel'] ?? ''));
		if ($file_rel !== '') {
			$file_abs = \function_exists(__NAMESPACE__ . '\\cmx_dav_module_file_path')
				? \wp_normalize_path((string) cmx_dav_module_file_path('carent', $file_rel))
				: '';
			if ($file_abs !== '' && \is_file($file_abs) && \function_exists(__NAMESPACE__ . '\\cmx_dav_module_file_url')) {
				return (string) cmx_dav_module_file_url('carent', $file_rel);
			}
		}

		$attachment_id = (int) ($options['carent_pdf_attachment_id'] ?? 0);
		if ($attachment_id > 0) {
			return (string) \wp_get_attachment_url($attachment_id);
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_agb_link')) {
	function cmx_carent_agb_link(): string {
		$email_link = \function_exists(__NAMESPACE__ . '\\cmx_email_agb_link')
			? \trim((string) cmx_email_agb_link())
			: '';
		if ($email_link !== '') {
			return $email_link;
		}

		return \trim((string) cmx_carent_vertrag_agb_pdf_url());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_agb_required')) {
	function cmx_carent_agb_required(): bool {
		return cmx_carent_agb_link() !== '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_transfer_template_default')) {
	function cmx_carent_vertrag_transfer_template_default(): string {
		return '<p>Wir empfehlen dem Kunden (auch bei der Rückgabe) Bilder vom Zustand des Fahrzeuges zu machen.</p><p>Es muss mind. der oben genannte Treibstoff getankt werden. Alle Tankquittungen müssen aufbewahrt werden.</p><p>Das Fahrzeug ist vollgetankt zurückzugeben, andernfalls werden Treibstoffkosten plus CHF 50.- Aufwandsentschädigung verrechnet.</p><p><strong>Besondere Abmachungen:</strong><br>{besondere_abmachungen}</p>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_transfer_template')) {
	function cmx_carent_vertrag_transfer_template(): string {
		$option_name = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			: 'cmx_einstellungen';
		$options = (array) \get_option($option_name, []);
		$value = \trim((string) ($options['carent_mail_transfer_template'] ?? ''));
		return $value !== '' ? $value : cmx_carent_vertrag_transfer_template_default();
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_render_transfer_template')) {
	function cmx_carent_vertrag_render_transfer_template(array $values): string {
		$template = cmx_carent_vertrag_transfer_template();
		$special_notes = \trim((string) ($values['besondere_abmachungen'] ?? ''));
		if ($special_notes !== '' && \strpos($template, '{besondere_abmachungen}') === false) {
			$template .= '<p><strong>Besondere Abmachungen:</strong><br>{besondere_abmachungen}</p>';
		}
		$replace = [];
		foreach ($values as $key => $value) {
			$replace['{' . $key . '}'] = (string) $value;
		}

		$html = \strtr($template, $replace);
		$html = \str_replace(["\r\n", "\r"], "\n", $html);
		$html = \wpautop($html, true);
		$html = \wp_kses_post($html);

		$check_icon = cmx_carent_vertrag_icon_svg('circle-check-big', 'pdf-icon transfer-check-icon', '#001b3d');
		if ($check_icon !== '') {
			$html = (string) \preg_replace('/<li([^>]*)>/i', '<li$1>' . $check_icon, $html);
		}

		return $html;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_file_badge_icon')) {
	function cmx_carent_vertrag_file_badge_icon(string $class = 'cmx-carent-vertrag-file-badge'): string {
		static $cache = [];
		if (isset($cache[$class])) {
			return (string) $cache[$class];
		}

		$path = \dirname(__DIR__, 2) . '/assets/icons/file-badge.svg';
		if (!\is_readable($path)) {
			$cache[$class] = '<span class="dashicons dashicons-media-document" aria-hidden="true"></span>';
			return (string) $cache[$class];
		}

		$svg = (string) \file_get_contents($path);
		if ($svg === '') {
			$cache[$class] = '<span class="dashicons dashicons-media-document" aria-hidden="true"></span>';
			return (string) $cache[$class];
		}

		$svg = \preg_replace('/<svg\b/', '<svg class="' . \esc_attr($class) . '" aria-hidden="true" focusable="false"', $svg, 1);
		$cache[$class] = \is_string($svg) ? $svg : '';
		return (string) $cache[$class];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_live_url')) {
	function cmx_carent_vertrag_live_url(int $post_id): string {
		if ($post_id <= 0 || (string) \get_post_type($post_id) !== 'carent' || !\current_user_can('edit_post', $post_id)) {
			return '';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_vermietung_manage_url')) {
			return (string) cmx_vermietung_manage_url($post_id);
		}

		return (string) \get_edit_post_link($post_id, 'raw');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_pdf_url')) {
	function cmx_carent_vertrag_pdf_url(int $post_id): string {
		if ($post_id <= 0 || (string) \get_post_type($post_id) !== 'carent' || !\current_user_can('edit_post', $post_id)) {
			return '';
		}

		return (string) \add_query_arg([
			'action' => 'cmx_carent_live_vertrag_pdf',
			'post_id' => $post_id,
			'_wpnonce' => \wp_create_nonce('cmx_carent_live_vertrag_pdf_' . $post_id),
		], \admin_url('admin-post.php'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_live_icon_link')) {
	function cmx_carent_vertrag_live_icon_link(int $post_id, string $class = 'cmx-carent-vertrag-live-link'): string {
		$url = cmx_carent_vertrag_live_url($post_id);
		if ($url === '') {
			return '<span aria-hidden="true"></span>';
		}

		return '<a href="' . \esc_url($url) . '" class="' . \esc_attr($class) . '" target="_blank" rel="noopener noreferrer" title="' . \esc_attr__('Live-Vermietung öffnen', 'cmx-misbuero') . '" aria-label="' . \esc_attr__('Live-Vermietung öffnen', 'cmx-misbuero') . '">'
			. cmx_carent_vertrag_file_badge_icon()
			. '<span class="screen-reader-text">' . \esc_html__('Live-Vermietung öffnen', 'cmx-misbuero') . '</span>'
			. '</a>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_pdf_icon_link')) {
	function cmx_carent_vertrag_pdf_icon_link(int $post_id, string $class = 'cmx-carent-vertrag-pdf-link'): string {
		$url = cmx_carent_vertrag_pdf_url($post_id);
		if ($url === '') {
			return '<span aria-hidden="true"></span>';
		}

		return '<a href="' . \esc_url($url) . '" class="' . \esc_attr($class) . '" target="_blank" rel="noopener noreferrer" title="' . \esc_attr__('Mietvertrag als PDF anzeigen', 'cmx-misbuero') . '" aria-label="' . \esc_attr__('Mietvertrag als PDF anzeigen', 'cmx-misbuero') . '">'
			. '<span class="dashicons dashicons-pdf" aria-hidden="true"></span>'
			. '<span class="screen-reader-text">' . \esc_html__('Mietvertrag als PDF anzeigen', 'cmx-misbuero') . '</span>'
			. '</a>';
	}
}

\add_action('admin_head', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== 'carent') {
		return;
	}

	echo '<style>
		.cmx-carent-vertrag-actions{display:inline-flex;align-items:center;justify-content:center;gap:8px}
		.cmx-carent-vertrag-live-link,
		.cmx-carent-vertrag-pdf-link{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;color:#d63638;text-decoration:none;vertical-align:middle}
		.cmx-carent-vertrag-live-link:hover,
		.cmx-carent-vertrag-live-link:focus,
		.cmx-carent-vertrag-pdf-link:hover,
		.cmx-carent-vertrag-pdf-link:focus{color:#b32d2e;text-decoration:none;box-shadow:none;outline:0}
		.cmx-carent-vertrag-live-link .cmx-carent-vertrag-file-badge{display:block;width:20px;height:20px;stroke:currentColor}
		.cmx-carent-vertrag-pdf-link .dashicons{width:20px;height:20px;font-size:20px;line-height:20px}
		.cmx-carent-vertrag-live-link.is-list,
		.cmx-carent-vertrag-pdf-link.is-list{margin:0}
	</style>';
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_append_pdf_file')) {
	function cmx_carent_vertrag_append_pdf_file(string $pdf_binary, string $append_pdf_path): string {
		if ($pdf_binary === '' || $append_pdf_path === '' || !\is_file($append_pdf_path) || !\is_readable($append_pdf_path)) {
			return $pdf_binary;
		}
		if (!\class_exists(Fpdi::class)) {
			\error_log('[CMX Carent] AGB-PDF nicht angehaengt: FPDI ist nicht verfuegbar.');
			return $pdf_binary;
		}

		$tmp_contract = \wp_tempnam('cmx-carent-vertrag.pdf');
		if (!\is_string($tmp_contract) || $tmp_contract === '') {
			\error_log('[CMX Carent] AGB-PDF nicht angehaengt: Temp-Datei konnte nicht erstellt werden.');
			return $pdf_binary;
		}

		try {
			if (\file_put_contents($tmp_contract, $pdf_binary) === false) {
				\error_log('[CMX Carent] AGB-PDF nicht angehaengt: Vertrags-PDF konnte nicht temporaer geschrieben werden.');
				return $pdf_binary;
			}

			$pdf = new Fpdi();
			$pdf->SetAutoPageBreak(false);
			foreach ([$tmp_contract, $append_pdf_path] as $source_path) {
				$page_count = $pdf->setSourceFile($source_path);
				for ($page_no = 1; $page_no <= $page_count; $page_no++) {
					$template_id = $pdf->importPage($page_no, 'CropBox', true, true);
					$size = (array) $pdf->getTemplateSize($template_id);
					$width = (float) ($size['width'] ?? 210);
					$height = (float) ($size['height'] ?? 297);
					$pdf->AddPage($width > $height ? 'L' : 'P', [$width, $height]);
					$pdf->useTemplate($template_id);
				}
			}

			$merged = $pdf->Output('S');
			return \is_string($merged) && $merged !== '' ? $merged : $pdf_binary;
		} catch (\Throwable $e) {
			\error_log('[CMX Carent] AGB-PDF nicht angehaengt: ' . $e->getMessage());
			return $pdf_binary;
		} finally {
			if (\is_file($tmp_contract)) {
				@\unlink($tmp_contract);
			}
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_has_media_links')) {
	function cmx_carent_vertrag_has_media_links(array $data): bool {
		$transfer = (array) ($data['transfer'] ?? []);
		foreach (['uebernahme', 'rueckgabe'] as $section) {
			$section_data = (array) ($transfer[$section] ?? []);
			$video = (array) ($section_data['video'] ?? []);
			if (\trim((string) (($video['url'] ?? '') ?: ($video['file_url'] ?? ''))) !== '') {
				return true;
			}

			foreach ((array) ($section_data['fotos'] ?? []) as $photo_item) {
				$photo_item = (array) $photo_item;
				$attachment = (array) ($photo_item['attachment'] ?? []);
				if (cmx_carent_vertrag_photo_url($attachment) !== '') {
					return true;
				}
			}
		}

		$documents = (array) ($data['documents'] ?? []);
		foreach (['fuehrerausweis', 'identitaetskarte'] as $document_key) {
			$document = (array) ($documents[$document_key] ?? []);
			if (\trim((string) (($document['url'] ?? '') ?: ($document['file_url'] ?? ''))) !== '') {
				return true;
			}
		}

		foreach (['contact', 'self_contact'] as $contact_key) {
			$contact = (array) ($data[$contact_key] ?? []);
			foreach (['fuehrerausweis', 'identitaetskarte'] as $document_key) {
				$document = (array) ($contact[$document_key] ?? []);
				if (\trim((string) (($document['url'] ?? '') ?: ($document['file_url'] ?? ''))) !== '') {
					return true;
				}
			}
		}

		return false;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_parse_number')) {
	function cmx_carent_vertrag_parse_number(mixed $value): float {
		if (\function_exists(__NAMESPACE__ . '\\cmx_parse_number')) {
			return (float) cmx_parse_number((string) $value);
		}

		$raw = \trim((string) $value);
		if ($raw === '') {
			return 0.0;
		}

		$raw = \str_replace(["'", ' '], '', $raw);
		$raw = \str_replace(',', '.', $raw);

		return \is_numeric($raw) ? (float) $raw : 0.0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_format_money')) {
	function cmx_carent_vertrag_format_money(mixed $value): string {
		if (\trim((string) $value) === '') {
			return '';
		}

		return \number_format(\max(0.0, cmx_carent_vertrag_parse_number($value)), 2, ',', "'");
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_format_int')) {
	function cmx_carent_vertrag_format_int(mixed $value): string {
		$raw = \trim((string) $value);
		if ($raw === '') {
			return '';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_normalize_int')) {
			return (string) cmx_carent_fahrzeug_normalize_int($raw);
		}

		return (string) \max(0, (int) \round(cmx_carent_vertrag_parse_number($raw)));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_format_date')) {
	function cmx_carent_vertrag_format_date(string $value): string {
		$value = \trim($value);
		if ($value === '') {
			return '';
		}

		if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
			$timestamp = \strtotime($value . ' 00:00:00');
			if ($timestamp !== false) {
				return (string) \wp_date('d.m.Y', $timestamp);
			}
		}

		return $value;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_format_datetime')) {
	function cmx_carent_vertrag_format_datetime(string $date, string $time): string {
		$parts = \array_values(\array_filter([
			cmx_carent_vertrag_format_date($date),
			\trim($time),
		], static fn(string $value): bool => $value !== ''));

		return \implode(' · ', $parts);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_bool_label')) {
	function cmx_carent_vertrag_bool_label(mixed $value): string {
		if (\is_bool($value)) {
			return $value ? 'Ja' : 'Nein';
		}

		$normalized = \strtolower(\trim((string) $value));
		if (\in_array($normalized, ['1', 'true', 'yes', 'ja', 'on'], true)) {
			return 'Ja';
		}
		if (\in_array($normalized, ['0', 'false', 'no', 'nein', 'off'], true)) {
			return 'Nein';
		}

		return $normalized !== '' ? \ucfirst($normalized) : 'Nein';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_html_value')) {
	function cmx_carent_vertrag_html_value(string $value, string $empty = '&ndash;'): string {
		$value = \trim($value);
		return $value !== '' ? \esc_html($value) : $empty;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_icon_svg')) {
	function cmx_carent_vertrag_icon_svg(string $name, string $class = 'pdf-icon', string $color = '#001b3d'): string {
		$name = \sanitize_file_name($name);
		if ($name === '') {
			return '';
		}

		$svg = \function_exists(__NAMESPACE__ . '\\cmx_icon') ? cmx_icon($name) : '';
		if ($svg === '') {
			$path = \dirname(__DIR__, 2) . '/assets/icons/' . $name . '.svg';
			$svg = \is_readable($path) ? \file_get_contents($path) : '';
		}
		if (!\is_string($svg) || \trim($svg) === '') {
			return '';
		}

		$svg = \str_replace(['currentColor', 'black', '#000000', '#000'], $color, $svg);

		return '<img class="' . \esc_attr($class) . '" src="data:image/svg+xml;base64,' . \base64_encode($svg) . '" alt="">';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_icon_badge')) {
	function cmx_carent_vertrag_icon_badge(string $name): string {
		$icon = cmx_carent_vertrag_icon_svg($name, 'pdf-icon', '#ffffff');
		return '<span class="icon-badge">' . $icon . '</span>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_field_html')) {
	function cmx_carent_vertrag_field_html(string $icon, string $label, string $value): string {
		return '<td class="vehicle-field">'
			. '<span class="field-icon">' . cmx_carent_vertrag_icon_svg($icon, 'pdf-field-icon', '#001b3d') . '</span>'
			. '<span class="field-copy"><strong>' . \esc_html($label) . '</strong><span>' . cmx_carent_vertrag_html_value($value) . '</span></span>'
			. '</td>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_attachment_image_path')) {
	function cmx_carent_vertrag_attachment_image_path(int $attachment_id): string {
		if ($attachment_id <= 0) {
			return '';
		}

		$full_path = (string) \get_attached_file($attachment_id);
		$meta = (array) \wp_get_attachment_metadata($attachment_id);
		$sizes = (array) ($meta['sizes'] ?? []);
		if ($full_path === '' || $sizes === []) {
			return $full_path;
		}

		$dir = \trailingslashit((string) \dirname($full_path));
		$best_path = '';
		$best_area = 0;
		$smallest_path = '';
		$smallest_area = PHP_INT_MAX;
		foreach ($sizes as $size) {
			$size = (array) $size;
			$file = \trim((string) ($size['file'] ?? ''));
			$width = (int) ($size['width'] ?? 0);
			$height = (int) ($size['height'] ?? 0);
			if ($file === '' || $width <= 0 || $height <= 0) {
				continue;
			}

			$path = $dir . $file;
			if (!\is_readable($path)) {
				continue;
			}

			$area = $width * $height;
			if ($width <= 1400 && $height <= 1400 && $area > $best_area) {
				$best_path = $path;
				$best_area = $area;
			}
			if ($area < $smallest_area) {
				$smallest_path = $path;
				$smallest_area = $area;
			}
		}

		return $best_path !== '' ? $best_path : ($smallest_path !== '' ? $smallest_path : $full_path);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_image_data_uri_from_path')) {
	function cmx_carent_vertrag_image_data_uri_from_path(string $path): string {
		$path = \wp_normalize_path($path);
		if ($path === '' || !\is_readable($path)) {
			return '';
		}

		$mime = (string) (\wp_check_filetype($path)['type'] ?? '');
		if ($mime === '' || !\str_starts_with($mime, 'image/')) {
			return '';
		}

		$raw = \file_get_contents($path);
		if (!\is_string($raw) || $raw === '') {
			return '';
		}

		return 'data:' . $mime . ';base64,' . \base64_encode($raw);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_image_data_uri')) {
	function cmx_carent_vertrag_image_data_uri(int $attachment_id): string {
		if ($attachment_id <= 0) {
			return '';
		}

		return cmx_carent_vertrag_image_data_uri_from_path(cmx_carent_vertrag_attachment_image_path($attachment_id));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_video_poster_src')) {
	function cmx_carent_vertrag_video_poster_src(int $attachment_id): string {
		if ($attachment_id <= 0) {
			return '';
		}

		$thumbnail_id = (int) \get_post_thumbnail_id($attachment_id);
		return $thumbnail_id > 0 ? cmx_carent_vertrag_image_data_uri($thumbnail_id) : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_article_image_src')) {
	function cmx_carent_vertrag_article_image_src(int $artikel_id): string {
		if ($artikel_id <= 0) {
			return '';
		}

		$gallery = \function_exists(__NAMESPACE__ . '\\cmx_li_gallery_get')
			? (array) cmx_li_gallery_get($artikel_id, '_cmx_local_image_artikel')
			: (array) \get_post_meta($artikel_id, '_cmx_local_image_artikel_gallery', true);
		$primary_image = \is_array($gallery[0] ?? null) ? (array) $gallery[0] : [];
		$local_path = \trim((string) (($primary_image['path'] ?? '') ?: \get_post_meta($artikel_id, '_cmx_local_image_artikel_path', true)));
		if ($local_path !== '' && \is_readable($local_path)) {
			$data_uri = cmx_carent_vertrag_image_data_uri_from_path($local_path);
			if ($data_uri !== '') {
				return $data_uri;
			}
		}

		$local_url = \trim((string) (($primary_image['url'] ?? '') ?: \get_post_meta($artikel_id, '_cmx_local_image_artikel_url', true)));
		if ($local_url !== '') {
			$uploads = \wp_upload_dir();
			$baseurl = \untrailingslashit((string) ($uploads['baseurl'] ?? ''));
			$basedir = \untrailingslashit((string) ($uploads['basedir'] ?? ''));
			if ($baseurl !== '' && $basedir !== '' && \str_starts_with($local_url, $baseurl)) {
				$rel = \ltrim((string) \substr($local_url, \strlen($baseurl)), '/');
				$rel = (string) \preg_replace('/\?.*$/', '', $rel);
				$resolved_path = $basedir . '/' . $rel;
				if (\is_readable($resolved_path)) {
					$data_uri = cmx_carent_vertrag_image_data_uri_from_path($resolved_path);
					if ($data_uri !== '') {
						return $data_uri;
					}
				}
			}
		}

		return cmx_carent_vertrag_image_data_uri((int) \get_post_thumbnail_id($artikel_id));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_attachment_info')) {
	function cmx_carent_vertrag_attachment_info(int $attachment_id, bool $with_data_uri = false): array {
		if ($attachment_id <= 0 || (string) \get_post_type($attachment_id) !== 'attachment') {
			return [
				'id' => 0,
				'label' => '',
				'url' => '',
				'path' => '',
				'mime' => '',
				'data_uri' => '',
			];
		}

		$path = (string) \get_attached_file($attachment_id);
		$url = (string) \wp_get_attachment_url($attachment_id);
		$mime = (string) \get_post_mime_type($attachment_id);
		$label = $path !== '' ? (string) \basename($path) : ('attachment-' . $attachment_id);
		$data_uri = '';

		if ($with_data_uri && $path !== '' && \is_readable($path) && \str_starts_with($mime, 'image/')) {
			$data_uri = cmx_carent_vertrag_image_data_uri($attachment_id);
		}

		return [
			'id' => $attachment_id,
			'label' => $label,
			'url' => $url,
			'path' => $path,
			'mime' => $mime,
			'data_uri' => $data_uri,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_empty_file_info')) {
	function cmx_carent_vertrag_empty_file_info(): array {
		return [
			'id' => 0,
			'label' => '',
			'url' => '',
			'path' => '',
			'mime' => '',
			'data_uri' => '',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_attachment_info_for_post')) {
	function cmx_carent_vertrag_attachment_info_for_post(int $attachment_id, int $post_id, bool $with_data_uri = false): array {
		if ($attachment_id <= 0 || $post_id <= 0 || (string) \get_post_type($attachment_id) !== 'attachment') {
			return cmx_carent_vertrag_empty_file_info();
		}

		$parent_id = (int) \wp_get_post_parent_id($attachment_id);
		if ($parent_id > 0 && $parent_id !== $post_id) {
			return cmx_carent_vertrag_empty_file_info();
		}

		return cmx_carent_vertrag_attachment_info($attachment_id, $with_data_uri);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_file_info')) {
	function cmx_carent_vertrag_file_info(string $file_path, bool $with_data_uri = false): array {
		$file_path = cmx_carent_vertrag_normalize_rel_path($file_path);
		if ($file_path === '' || !\function_exists(__NAMESPACE__ . '\\cmx_dav_module_file_path') || !\function_exists(__NAMESPACE__ . '\\cmx_dav_module_file_url')) {
			return cmx_carent_vertrag_empty_file_info();
		}

		if (\str_starts_with($file_path, 'misbuero/archiv/') && \function_exists(__NAMESPACE__ . '\\cmx_dav_archive_file_path') && \function_exists(__NAMESPACE__ . '\\cmx_dav_archive_file_url')) {
			$path = \wp_normalize_path((string) cmx_dav_archive_file_path($file_path));
			$url = (string) cmx_dav_archive_file_url($file_path);
		} else {
			$path = \wp_normalize_path((string) cmx_dav_module_file_path('carent', $file_path));
			$url = (string) cmx_dav_module_file_url('carent', $file_path);
		}
		$mime = (string) (\wp_check_filetype($path)['type'] ?? '');
		$data_uri = '';
		if ($with_data_uri && $path !== '' && \is_readable($path) && \str_starts_with($mime, 'image/')) {
			$data_uri = cmx_carent_vertrag_image_data_uri_from_path($path);
		}

		return [
			'id' => 0,
			'label' => (string) \basename($file_path),
			'url' => $url,
			'path' => $path,
			'mime' => $mime,
			'data_uri' => $data_uri,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_attachment_or_file_info_for_post')) {
	function cmx_carent_vertrag_attachment_or_file_info_for_post($value, int $post_id, bool $with_data_uri = false): array {
		$value = \trim((string) $value);
		if ($value === '') {
			return cmx_carent_vertrag_empty_file_info();
		}
		if (\ctype_digit($value) && (int) $value > 0) {
			return cmx_carent_vertrag_attachment_info_for_post((int) $value, $post_id, $with_data_uri);
		}
		return cmx_carent_vertrag_file_info($value, $with_data_uri);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_document_info_from_meta')) {
	function cmx_carent_vertrag_document_info_from_meta(int $post_id, string $meta_key): array {
		$value = \trim((string) \get_post_meta($post_id, $meta_key, true));
		return cmx_carent_vertrag_attachment_or_file_info($value);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_attachment_or_file_info')) {
	function cmx_carent_vertrag_attachment_or_file_info($value, bool $with_data_uri = false): array {
		$value = \trim((string) $value);
		if ($value === '') {
			return cmx_carent_vertrag_empty_file_info();
		}
		if (\ctype_digit($value) && (int) $value > 0) {
			return cmx_carent_vertrag_attachment_info((int) $value, $with_data_uri);
		}
		return cmx_carent_vertrag_file_info($value, $with_data_uri);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_signature_info')) {
	function cmx_carent_vertrag_signature_info(int $post_id, string $section, string $role): array {
		return cmx_carent_vertrag_attachment_or_file_info_for_post(
			\get_post_meta($post_id, cmx_carent_vertrag_signature_meta_key($section, $role), true),
			$post_id,
			true
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_photo_src')) {
	function cmx_carent_vertrag_photo_src(array $attachment): string {
		$data_uri = \trim((string) ($attachment['data_uri'] ?? ''));
		if ($data_uri !== '') {
			return $data_uri;
		}

		$id = isset($attachment['id']) ? (int) $attachment['id'] : 0;
		return $id > 0 ? cmx_carent_vertrag_image_data_uri($id) : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_photo_url')) {
	function cmx_carent_vertrag_photo_url(array $attachment): string {
		foreach (['url', 'file_url'] as $key) {
			$url = \trim((string) ($attachment[$key] ?? ''));
			if ($url !== '') {
				return $url;
			}
		}

		$id = isset($attachment['id']) ? (int) $attachment['id'] : 0;
		return $id > 0 ? \trim((string) \wp_get_attachment_url($id)) : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_photo_cell_html')) {
	function cmx_carent_vertrag_photo_cell_html(array $photo): string {
		$label = \trim((string) ($photo['label'] ?? ''));
		$src = \trim((string) ($photo['src'] ?? ''));
		$url = \trim((string) ($photo['url'] ?? ''));
		$style = \trim((string) ($photo['style'] ?? ''));

		if ($src === '') {
			return '<td class="photo-cell photo-cell-empty"></td>';
		}

		$image = '<img src="' . \esc_attr($src) . '" alt="" class="photo-image" style="' . \esc_attr($style) . '">';

		if ($url !== '') {
			$image = '<a href="' . \esc_url($url) . '" class="photo-link">' . $image . '</a>';
		}

		$content = '<span class="photo-label">' . \esc_html($label) . '</span>' . $image;

		return '<td class="photo-cell">' . $content . '</td>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_fit_image_style')) {
	function cmx_carent_vertrag_fit_image_style(string $path, float $max_width_mm, float $max_height_mm): string {
		$path = \wp_normalize_path($path);
		if ($path === '' || !\is_readable($path)) {
			return 'max-width:' . $max_width_mm . 'mm;max-height:' . $max_height_mm . 'mm;width:auto;height:auto;';
		}

		$size = @\getimagesize($path);
		$width_px = isset($size[0]) ? (float) $size[0] : 0.0;
		$height_px = isset($size[1]) ? (float) $size[1] : 0.0;
		if ($width_px <= 0 || $height_px <= 0) {
			return 'max-width:' . $max_width_mm . 'mm;max-height:' . $max_height_mm . 'mm;width:auto;height:auto;';
		}

		$scale = \min($max_width_mm / $width_px, $max_height_mm / $height_px);
		$width_mm = \max(0.1, $width_px * $scale);
		$height_mm = \max(0.1, $height_px * $scale);

		return 'width:' . \number_format($width_mm, 2, '.', '') . 'mm;height:' . \number_format($height_mm, 2, '.', '') . 'mm;';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_logo_src')) {
	function cmx_carent_vertrag_logo_src(): string {
		$logo_path = \function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_path')
			? \trim((string) cmx_email_self_logo_path())
			: '';
		if ($logo_path !== '' && \is_readable($logo_path)) {
			$raw = \file_get_contents($logo_path);
			if (\is_string($raw) && $raw !== '') {
				$filetype = \wp_check_filetype($logo_path);
				$mime = \trim((string) ($filetype['type'] ?? ''));
				if ($mime === '') {
					$mime = 'image/' . \strtolower((string) \pathinfo($logo_path, \PATHINFO_EXTENSION));
				}
				return 'data:' . $mime . ';base64,' . \base64_encode($raw);
			}
		}

		$logo_url = \function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_url')
			? \trim((string) cmx_email_self_logo_url())
			: '';
		if ($logo_url === '') {
			return '';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmxbu_prepare_png_for_dompdf')) {
			$logo_url = (string) cmxbu_prepare_png_for_dompdf($logo_url);
		}

		return $logo_url;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_transfer_meta_key')) {
	function cmx_carent_vertrag_transfer_meta_key(string $section, string $field): string {
		$section = \sanitize_key($section);
		$field = \sanitize_key($field);

		$map = [
			'uebernahme' => [
				'ort' => \defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_ORT_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_ORT_META')
					: '_cmx_carent_uebernahme_ort',
				'datum' => \defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_DATUM_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_DATUM_META')
					: '_cmx_carent_uebernahme_datum',
				'uhrzeit' => \defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_UHRZEIT_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_UHRZEIT_META')
					: '_cmx_carent_uebernahme_uhrzeit',
				'km_stand' => \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_STAND_UEBERNAHME_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_STAND_UEBERNAHME_META')
					: '_cmx_carent_fahrzeug_km_stand_uebernahme',
				'besondere_abmachungen' => \defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_BESONDERE_ABMACHUNGEN_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_BESONDERE_ABMACHUNGEN_META')
					: '_cmx_carent_uebernahme_besondere_abmachungen',
				'agb_akzeptiert' => \defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_MIETER_AGB_AKZEPTIERT_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_MIETER_AGB_AKZEPTIERT_META')
					: '_cmx_carent_uebernahme_mieter_agb_akzeptiert',
			],
			'rueckgabe' => [
				'ort' => \defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_ORT_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_ORT_META')
					: '_cmx_carent_rueckgabe_ort',
				'datum' => \defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_DATUM_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_DATUM_META')
					: '_cmx_carent_rueckgabe_datum',
				'uhrzeit' => \defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_UHRZEIT_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_UHRZEIT_META')
					: '_cmx_carent_rueckgabe_uhrzeit',
				'km_stand' => \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_STAND_RUECKGABE_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_STAND_RUECKGABE_META')
					: '_cmx_carent_fahrzeug_km_stand_rueckgabe',
			],
		];

		return (string) ($map[$section][$field] ?? '');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_signature_meta_key')) {
	function cmx_carent_vertrag_signature_meta_key(string $section, string $role): string {
		$section = \sanitize_key($section);
		$role = \sanitize_key($role);

		if ($section === 'rueckgabe') {
			return $role === 'mieter'
				? (\defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_MIETER_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_MIETER_META')
					: '_cmx_carent_rueckgabe_mieter_attachment_id')
				: (\defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_VERMIETER_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_VERMIETER_META')
					: '_cmx_carent_rueckgabe_vermieter_attachment_id');
		}

		return $role === 'mieter'
			? (\defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_MIETER_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_MIETER_META')
				: '_cmx_carent_uebernahme_mieter_attachment_id')
			: (\defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_VERMIETER_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_VERMIETER_META')
				: '_cmx_carent_uebernahme_vermieter_attachment_id');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_video_meta_key')) {
	function cmx_carent_vertrag_video_meta_key(string $section): string {
		return $section === 'rueckgabe'
			? (\defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_BESTANDSAUFNAHME_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_BESTANDSAUFNAHME_META')
				: '_cmx_carent_rueckgabe_bestandsaufnahme_attachment_id')
			: (\defined(__NAMESPACE__ . '\\CMX_CARENT_BESTANDSAUFNAHME_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_BESTANDSAUFNAHME_META')
				: '_cmx_carent_bestandsaufnahme_attachment_id');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_term_label')) {
	function cmx_carent_vertrag_term_label(string $taxonomy, int $term_id): string {
		if ($taxonomy === '' || $term_id <= 0 || !\taxonomy_exists($taxonomy)) {
			return '';
		}

		$term = \get_term($term_id, $taxonomy);
		if (!$term instanceof \WP_Term || \is_wp_error($term)) {
			return '';
		}

		return \trim((string) $term->name);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_contact_data')) {
	function cmx_carent_vertrag_contact_data(int $kontakt_id): array {
		$row = $kontakt_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_contact_row')
			? (array) cmx_telefonbuch_contact_row($kontakt_id)
			: [];
		$address = $kontakt_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_address_string')
			? \trim((string) cmx_telefonbuch_address_string($kontakt_id))
			: '';
		$email = $kontakt_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_email')
			? \sanitize_email((string) cmx_kommunikation_primary_email($kontakt_id))
			: '';
		$phones = (array) ($row['phones'] ?? []);
		$emails = (array) ($row['emails'] ?? []);

		if (!\is_email($email) && !empty($emails[0]['display'])) {
			$email = \sanitize_email((string) $emails[0]['display']);
		}

		return [
			'id' => $kontakt_id,
			'title' => \trim((string) ($row['title'] ?? ($kontakt_id > 0 ? \get_the_title($kontakt_id) : ''))),
			'subtitle' => \trim((string) ($row['subtitle'] ?? '')),
			'address' => $address,
			'phones' => $phones,
			'emails' => $emails,
			'email' => \is_email($email) ? $email : '',
			'website' => \trim((string) ($row['website'] ?? '')),
			'website_label' => \trim((string) ($row['website_label'] ?? '')),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_transfer_photos')) {
	function cmx_carent_vertrag_transfer_photos(int $post_id, string $section): array {
		if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fotos_section_config') || !\function_exists(__NAMESPACE__ . '\\cmx_carent_fotos_normalize_rows')) {
			return [];
		}

		$config = (array) cmx_carent_fotos_section_config($section);
		$meta_key = (string) ($config['meta_key'] ?? '');
		$legacy_meta_key = (string) ($config['legacy_meta_key'] ?? '');
		$rows = [];
		if ($meta_key !== '' && \metadata_exists('post', $post_id, $meta_key)) {
			$rows = cmx_carent_fotos_normalize_rows(\get_post_meta($post_id, $meta_key, true));
		} elseif ($legacy_meta_key !== '' && \metadata_exists('post', $post_id, $legacy_meta_key)) {
			$rows = cmx_carent_fotos_normalize_rows(\get_post_meta($post_id, $legacy_meta_key, true));
		}
		$taxonomy = \function_exists(__NAMESPACE__ . '\\cmx_carent_fotos_taxonomy')
			? (string) cmx_carent_fotos_taxonomy()
			: '';

		$items = [];
		foreach ($rows as $row) {
			$row = (array) $row;
			$term_id = isset($row['term_id']) ? (int) $row['term_id'] : 0;
			$attachment_ref = isset($row['attachment_id']) ? \trim((string) $row['attachment_id']) : '';
			$attachment = cmx_carent_vertrag_attachment_or_file_info_for_post($attachment_ref, $post_id, true);
			$items[] = [
				'term_id' => $term_id,
				'term_label' => cmx_carent_vertrag_term_label($taxonomy, $term_id),
				'attachment' => $attachment,
			];
		}

		return $items;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_schaden_rows')) {
	function cmx_carent_vertrag_schaden_rows(int $post_id): array {
		if (\function_exists(__NAMESPACE__ . '\\cmx_vermietung_schaden_rows')) {
			return (array) cmx_vermietung_schaden_rows($post_id);
		}

		$meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_ROWS_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_ROWS_META')
			: '_cmx_carent_schadenprotokoll_rows';
		$raw_rows = \get_post_meta($post_id, $meta_key, true);
		if (!\is_array($raw_rows)) {
			return [];
		}

		$rows = [];
		foreach ($raw_rows as $raw_row) {
			if (!\is_array($raw_row)) {
				continue;
			}

			$term_id = isset($raw_row['term_id']) ? (int) $raw_row['term_id'] : 0;
			$note = \trim((string) ($raw_row['note'] ?? ''));
			$fotos_gemacht = !empty($raw_row['fotos_gemacht']);
			$kosten = \function_exists(__NAMESPACE__ . '\\cmx_carent_schadenprotokoll_sanitize_cost')
				? cmx_carent_schadenprotokoll_sanitize_cost($raw_row['kosten'] ?? '')
				: \trim((string) ($raw_row['kosten'] ?? ''));
			if ($term_id <= 0 && $note === '' && !$fotos_gemacht && $kosten === '') {
				continue;
			}

			$rows[] = [
				'term_id' => $term_id,
				'note' => $note,
				'fotos_gemacht' => $fotos_gemacht,
				'kosten' => $kosten,
			];
		}

		return $rows;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_collect_data')) {
	function cmx_carent_vertrag_collect_data(int $post_id): array {
		$post = \get_post($post_id);
		if (!$post instanceof \WP_Post || (string) $post->post_type !== 'carent') {
			return [];
		}

		$kontakt_id = \defined(__NAMESPACE__ . '\\CMX_CARENT_KONTAKT_META')
			? (int) \get_post_meta($post_id, (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_KONTAKT_META'), true)
			: 0;
		$artikel_id = \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_META')
			? (int) \get_post_meta($post_id, (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_META'), true)
			: 0;
		$variant_index = \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_VARIANT_INDEX_META')
			? \get_post_meta($post_id, (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_VARIANT_INDEX_META'), true)
			: '';

		$contact = cmx_carent_vertrag_contact_data($kontakt_id);
		$self_id = \function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_id')
			? (int) cmx_email_self_contact_id()
			: 0;
		$self_contact = cmx_carent_vertrag_contact_data($self_id);
		$self_contact['branding'] = \function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_branding_text')
			? (string) cmx_email_self_contact_branding_text()
			: ($self_contact['title'] !== '' ? (string) $self_contact['title'] : \get_bloginfo('name'));
		$self_contact['logo_src'] = cmx_carent_vertrag_logo_src();

		$vehicle_label = \function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_selection_label')
			? (string) cmx_carent_fahrzeug_selection_label($artikel_id, $variant_index)
			: (\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_display_label')
				? (string) cmx_carent_fahrzeug_display_label($artikel_id)
				: \trim((string) \get_the_title($artikel_id)));
		$vehicle_number = \function_exists(__NAMESPACE__ . '\\cmx_get_artikel_nr')
			? \trim((string) cmx_get_artikel_nr($artikel_id))
			: '';
		$vehicle_article_meta = cmx_carent_vertrag_vehicle_article_data($artikel_id);
		$vehicle_variant_meta = cmx_carent_vertrag_vehicle_variant_data($artikel_id, $variant_index);

		$kennzeichen_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KENNZEICHEN_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KENNZEICHEN_META')
			: '_cmx_carent_fahrzeug_kennzeichen';
		$begrenzung_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_BEGRENZUNG_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_BEGRENZUNG_META')
			: '_cmx_carent_fahrzeug_km_begrenzung';
		$mehrpreis_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_MEHRPREIS_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_MEHRPREIS_META')
			: '_cmx_carent_fahrzeug_km_mehrpreis';
		$kasko_min_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KASKO_MIN_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KASKO_MIN_META')
			: '_cmx_carent_fahrzeug_kasko_min';
		$kasko_max_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KASKO_MAX_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KASKO_MAX_META')
			: '_cmx_carent_fahrzeug_kasko_max';
		$anzahl_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_ANZAHL_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_ANZAHL_META')
			: '_cmx_carent_fahrzeug_anzahl';
		$mietpreis_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_MIETPREIS_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_MIETPREIS_META')
			: '_cmx_carent_mietpreis';
		$mehrkilometer_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_ABRECHNUNG_MEHRKILOMETER_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_ABRECHNUNG_MEHRKILOMETER_META')
			: '_cmx_carent_abrechnung_mehrkilometer';
		$schadenkosten_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_ABRECHNUNG_SCHADENKOSTEN_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_ABRECHNUNG_SCHADENKOSTEN_META')
			: '_cmx_carent_abrechnung_schadenkosten';

		$anzahl = \trim((string) \get_post_meta($post_id, $anzahl_key, true));
		$mietpreis = \trim((string) \get_post_meta($post_id, $mietpreis_key, true));
		$mehrkilometer = \trim((string) \get_post_meta($post_id, $mehrkilometer_key, true));
		$schadenkosten = \trim((string) \get_post_meta($post_id, $schadenkosten_key, true));
		$summe = $anzahl !== '' && $mietpreis !== ''
			? cmx_carent_vertrag_format_money(\round(cmx_carent_vertrag_parse_number($anzahl) * cmx_carent_vertrag_parse_number($mietpreis), 0))
			: '';

		$status_value = \defined(__NAMESPACE__ . '\\CMX_CARENT_STATUS_META')
			? \sanitize_key((string) \get_post_meta($post_id, (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_STATUS_META'), true))
			: '';
		$status_options = \function_exists(__NAMESPACE__ . '\\cmx_carent_status_options')
			? (array) cmx_carent_status_options()
			: [];

		$damage_taxonomy = \function_exists(__NAMESPACE__ . '\\cmx_carent_schaden_taxonomy')
			? (string) cmx_carent_schaden_taxonomy()
			: '';
		$damage_rows = [];
		foreach (cmx_carent_vertrag_schaden_rows($post_id) as $row) {
			$row = (array) $row;
			$term_id = isset($row['term_id']) ? (int) $row['term_id'] : 0;
			$damage_rows[] = [
				'term_id' => $term_id,
				'term_label' => cmx_carent_vertrag_term_label($damage_taxonomy, $term_id),
				'note' => \trim((string) ($row['note'] ?? '')),
				'fotos_gemacht' => !empty($row['fotos_gemacht']),
				'kosten' => \function_exists(__NAMESPACE__ . '\\cmx_carent_schadenprotokoll_sanitize_cost')
					? cmx_carent_schadenprotokoll_sanitize_cost($row['kosten'] ?? '')
					: \trim((string) ($row['kosten'] ?? '')),
			];
		}

		$damage_data = [
			'rows' => $damage_rows,
			'ort' => \trim((string) \get_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_ORT_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_ORT_META')
				: '_cmx_carent_schadenprotokoll_ort', true)),
			'datum' => \trim((string) \get_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_DATUM_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_DATUM_META')
				: '_cmx_carent_schadenprotokoll_datum', true)),
			'uhrzeit' => \trim((string) \get_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_UHRZEIT_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_UHRZEIT_META')
				: '_cmx_carent_schadenprotokoll_uhrzeit', true)),
			'weitere_beteiligte' => \trim((string) \get_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_WEITERE_BETEILIGTE_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_WEITERE_BETEILIGTE_META')
				: '_cmx_carent_schadenprotokoll_weitere_beteiligte', true)),
			'weitere_angaben' => \trim((string) \get_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_WEITERE_ANGABEN_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_WEITERE_ANGABEN_META')
				: '_cmx_carent_schadenprotokoll_weitere_angaben', true)),
			'unfallprotokoll' => \trim((string) \get_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_UNFALLPROTOKOLL_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_UNFALLPROTOKOLL_META')
				: '_cmx_carent_schadenprotokoll_unfallprotokoll', true)),
			'anerkennung' => \trim((string) \get_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_ANERKENNUNG_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_ANERKENNUNG_META')
				: '_cmx_carent_schadenprotokoll_anerkennung', true)),
		];

		$transfer_sections = [];
		foreach (['uebernahme', 'rueckgabe'] as $section) {
			$transfer_sections[$section] = [
				'ort' => \trim((string) \get_post_meta($post_id, cmx_carent_vertrag_transfer_meta_key($section, 'ort'), true)),
				'datum' => \trim((string) \get_post_meta($post_id, cmx_carent_vertrag_transfer_meta_key($section, 'datum'), true)),
				'uhrzeit' => \trim((string) \get_post_meta($post_id, cmx_carent_vertrag_transfer_meta_key($section, 'uhrzeit'), true)),
				'km_stand' => \trim((string) \get_post_meta($post_id, cmx_carent_vertrag_transfer_meta_key($section, 'km_stand'), true)),
				'besondere_abmachungen' => $section === 'uebernahme'
					? \trim((string) \get_post_meta($post_id, cmx_carent_vertrag_transfer_meta_key('uebernahme', 'besondere_abmachungen'), true))
					: '',
				'agb_akzeptiert' => $section === 'uebernahme'
					? !empty(\get_post_meta($post_id, cmx_carent_vertrag_transfer_meta_key('uebernahme', 'agb_akzeptiert'), true))
					: false,
				'fotos' => cmx_carent_vertrag_transfer_photos($post_id, $section),
				'video' => cmx_carent_vertrag_attachment_or_file_info(\get_post_meta($post_id, cmx_carent_vertrag_video_meta_key($section), true)),
				'signatures' => [
					'vermieter' => cmx_carent_vertrag_signature_info($post_id, $section, 'vermieter'),
					'mieter' => cmx_carent_vertrag_signature_info($post_id, $section, 'mieter'),
				],
			];
		}

		$content_html = '';
		$content_raw = \trim((string) $post->post_content);
		if ($content_raw !== '') {
			$content_html = \wp_kses_post(\wpautop($content_raw));
		}

		return [
			'post_id' => $post_id,
			'title' => \function_exists(__NAMESPACE__ . '\\cmx_carent_display_title')
				? (string) cmx_carent_display_title($post_id)
				: \trim((string) $post->post_title),
			'status' => [
				'value' => $status_value,
				'label' => (string) ($status_options[$status_value] ?? ($status_value !== '' ? \ucfirst($status_value) : 'offen')),
			],
			'created_at' => (string) \get_post_time('Y-m-d H:i:s', false, $post, true),
			'updated_at' => (string) \get_post_modified_time('Y-m-d H:i:s', false, $post, true),
			'content_html' => $content_html,
			'contact' => $contact,
			'self' => $self_contact,
			'vehicle' => [
				'article_id' => $artikel_id,
				'variant_index' => $variant_index,
				'label' => \trim($vehicle_label),
				'number' => $vehicle_number,
				'article_meta' => $vehicle_article_meta,
				'variant_meta' => $vehicle_variant_meta,
				'kennzeichen' => \trim((string) \get_post_meta($post_id, $kennzeichen_key, true)),
				'begrenzung' => \trim((string) \get_post_meta($post_id, $begrenzung_key, true)),
				'mehrpreis' => \trim((string) \get_post_meta($post_id, $mehrpreis_key, true)),
				'kasko_min' => \trim((string) \get_post_meta($post_id, $kasko_min_key, true)),
				'kasko_max' => \trim((string) \get_post_meta($post_id, $kasko_max_key, true)),
				'anzahl' => $anzahl,
				'mietpreis' => $mietpreis,
				'summe' => $summe,
			],
			'transfer' => $transfer_sections,
			'damage' => $damage_data,
			'billing' => [
				'mehrkilometer' => $mehrkilometer,
				'schadenkosten' => $schadenkosten,
			],
			'documents' => [
				'fuehrerausweis' => cmx_carent_vertrag_document_info_from_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_CARENT_FUEHRERAUSWEIS_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FUEHRERAUSWEIS_META')
					: '_cmx_carent_fuehrerausweis_attachment_id'),
				'identitaetskarte' => cmx_carent_vertrag_document_info_from_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_CARENT_IDENTITAETSKARTE_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_IDENTITAETSKARTE_META')
					: '_cmx_carent_identitaetskarte_attachment_id'),
			],
			'agb_link' => \function_exists(__NAMESPACE__ . '\\cmx_carent_agb_link')
				? \trim((string) cmx_carent_agb_link())
				: '',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_pdf_archive_year')) {
	function cmx_carent_vertrag_pdf_archive_year(int $post_id, array $data = []): int {
		$candidates = [];

		$transfer = (array) ($data['transfer'] ?? []);
		$uebernahme = (array) ($transfer['uebernahme'] ?? []);
		$rueckgabe = (array) ($transfer['rueckgabe'] ?? []);
		$candidates[] = (string) ($uebernahme['datum'] ?? '');
		$candidates[] = (string) ($rueckgabe['datum'] ?? '');
		$candidates[] = (string) \get_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_DATUM_META')
			? \constant(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_DATUM_META')
			: '_cmx_carent_uebernahme_datum', true);
		$candidates[] = (string) \get_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_DATUM_META')
			? \constant(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_DATUM_META')
			: '_cmx_carent_rueckgabe_datum', true);

		$post = \get_post($post_id);
		if ($post instanceof \WP_Post) {
			$candidates[] = (string) $post->post_date;
		}

		foreach ($candidates as $candidate) {
			if (\preg_match('/\b(19|20|21|22)\d{2}\b/', $candidate, $match) === 1) {
				$year = (int) $match[0];
				if ($year >= 1900 && $year <= 2200) {
					return $year;
				}
			}
		}

		$year = (int) \wp_date('Y');
		return $year > 0 ? $year : (int) \date('Y');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_pdf_base_name')) {
	function cmx_carent_vertrag_pdf_base_name(int $post_id): string {
		$nummer = \trim((string) \get_post_meta($post_id, '_cmx_carent_nummer', true));
		if ($nummer !== '') {
			$nummer = \sanitize_file_name($nummer);
			$nummer = \preg_replace('/\.pdf$/i', '', $nummer);
			if (\is_string($nummer) && \trim($nummer) !== '') {
				return \trim($nummer);
			}
		}

		$title = \trim((string) \get_the_title($post_id));
		if ($title !== '') {
			$parts = \preg_split('/\s+\-\s+/u', $title, 2) ?: [];
			$prefix = \trim((string) ($parts[0] ?? ''));
			if ($prefix !== '') {
				$prefix = \sanitize_file_name($prefix);
				$prefix = \preg_replace('/\.pdf$/i', '', $prefix);
				if (\is_string($prefix) && \trim($prefix) !== '') {
					return \trim($prefix);
				}
			}
		}

		return (string) $post_id;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_pdf_storage')) {
	function cmx_carent_vertrag_pdf_storage(int $post_id, array $data = []): array {
		if ($post_id <= 0) {
			return [];
		}

		$relative_dir = \function_exists(__NAMESPACE__ . '\\cmx_carent_contract_archive_rel_dir')
			? (string) cmx_carent_contract_archive_rel_dir($post_id)
			: (cmx_carent_vertrag_pdf_archive_year($post_id, $data) . '/carent/' . (string) $post_id);
		$relative_dir = \trim(\str_replace('\\', '/', $relative_dir), '/');
		$file_name = 'mietvertrag.pdf';
		$archive_rel_path = \trim($relative_dir . '/' . $file_name, '/');
		$rel_path = \trim('misbuero/archiv/' . $archive_rel_path, '/');
		$dir = \function_exists(__NAMESPACE__ . '\\cmx_dav_archive_file_path')
			? \wp_normalize_path((string) \dirname((string) cmx_dav_archive_file_path($archive_rel_path)))
			: \wp_normalize_path((string) \WP_CONTENT_DIR . '/uploads/misbuero/archiv/' . $relative_dir);

		$url_base = \function_exists(__NAMESPACE__ . '\\cmx_dav_archive_file_url')
			? (string) cmx_dav_archive_file_url($relative_dir)
			: (string) \home_url('/archiv/' . $relative_dir);
		$url = \function_exists(__NAMESPACE__ . '\\cmx_dav_archive_file_url')
			? (string) cmx_dav_archive_file_url($archive_rel_path)
			: (string) \home_url('/archiv/' . $archive_rel_path);

		return [
			'year' => cmx_carent_vertrag_pdf_archive_year($post_id, $data),
			'rel_path' => $rel_path,
			'dir' => $dir,
			'url_base' => $url_base,
			'file_name' => $file_name,
			'abs_path' => \trailingslashit($dir) . $file_name,
			'url' => $url,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_public_token_option_key')) {
	function cmx_carent_vertrag_public_token_option_key(int $post_id): string {
		return 'cmx_carent_vertrag_token_for_' . (int) $post_id;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_public_token_data_option_key')) {
	function cmx_carent_vertrag_public_token_data_option_key(string $token): string {
		return 'cmx_carent_vertrag_token_data_' . \sanitize_key($token);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_public_token')) {
	function cmx_carent_vertrag_public_token(int $post_id): string {
		if ($post_id <= 0 || (string) \get_post_type($post_id) !== 'carent') {
			return '';
		}

		$option_key = cmx_carent_vertrag_public_token_option_key($post_id);
		$token = \get_option($option_key);
		if (\is_string($token) && \preg_match('/^[A-Za-z0-9]{24,80}$/', $token)) {
			$data_key = cmx_carent_vertrag_public_token_data_option_key($token);
			$data = \get_option($data_key);
			if (!\is_array($data) || (int) ($data['post_id'] ?? 0) !== $post_id) {
				\update_option($data_key, [
					'post_id' => $post_id,
					'created' => \time(),
				], false);
			}
			return $token;
		}

		$token = (string) \wp_generate_password(40, false, false);
		\update_option($option_key, $token, false);
		\update_option(cmx_carent_vertrag_public_token_data_option_key($token), [
			'post_id' => $post_id,
			'created' => \time(),
		], false);

		return $token;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_public_pdf_url')) {
	function cmx_carent_vertrag_public_pdf_url(int $post_id): string {
		$token = cmx_carent_vertrag_public_token($post_id);
		if ($token === '') {
			return '';
		}

		return (string) \add_query_arg('carent_vertrag', $token, \home_url('/'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_public_request_token')) {
	function cmx_carent_vertrag_public_request_token(): string {
		foreach (['carent_vertrag', 'cmx_carent_vertrag', '-p'] as $key) {
			if (empty($_GET[$key])) {
				continue;
			}
			$token = \trim(\sanitize_text_field((string) \wp_unslash($_GET[$key])));
			if (\preg_match('/^[A-Za-z0-9]{24,80}$/', $token)) {
				return $token;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_public_pdf_download_handler')) {
	function cmx_carent_vertrag_public_pdf_download_handler(): void {
		$token = cmx_carent_vertrag_public_request_token();
		if ($token === '') {
			return;
		}

		$data = \get_option(cmx_carent_vertrag_public_token_data_option_key($token));
		$post_id = \is_array($data) ? (int) ($data['post_id'] ?? 0) : 0;
		$stored_token = $post_id > 0 ? (string) \get_option(cmx_carent_vertrag_public_token_option_key($post_id), '') : '';
		if ($post_id <= 0 || (string) \get_post_type($post_id) !== 'carent' || $stored_token === '' || !\hash_equals($stored_token, $token)) {
			\status_header(404);
			exit;
		}

		$rel_path = cmx_carent_vertrag_normalize_rel_path((string) \get_post_meta($post_id, CMX_CARENT_VERTRAG_PDF_REL_META, true));
		$abs_path = $rel_path !== '' ? cmx_carent_vertrag_abs_path_from_rel_path($rel_path) : '';
		if ($abs_path === '' || !\is_file($abs_path) || !\is_readable($abs_path)) {
			$result = cmx_carent_vertrag_generate_pdf($post_id, ['sync_dokument' => true]);
			if (!\is_wp_error($result)) {
				$abs_path = \wp_normalize_path((string) ($result['abs_path'] ?? ''));
			}
		}

		if ($abs_path === '' || !\is_file($abs_path) || !\is_readable($abs_path)) {
			\status_header(404);
			exit;
		}

		$file_name = \basename($abs_path);
		\nocache_headers();
		\header('Content-Type: application/pdf');
		\header('Content-Disposition: inline; filename="' . \str_replace('"', '', $file_name) . '"');
		\header('Content-Length: ' . (string) \filesize($abs_path));
		\readfile($abs_path);
		exit;
	}
}
\add_action('template_redirect', __NAMESPACE__ . '\\cmx_carent_vertrag_public_pdf_download_handler', 0);

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_normalize_rel_path')) {
	function cmx_carent_vertrag_normalize_rel_path(string $path): string {
		return \ltrim(\str_replace('\\', '/', $path), '/');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_address_lines_from_string')) {
	function cmx_carent_vertrag_known_country_label(string $country): string {
		$country = \trim($country);
		if ($country === '' || !\function_exists(__NAMESPACE__ . '\\cmx_countries_from_taxonomy')) {
			return '';
		}

		$needle = \strtolower(\remove_accents($country));
		foreach (cmx_countries_from_taxonomy() as $option) {
			$value = \strtolower(\remove_accents((string) ($option['value'] ?? '')));
			$label = (string) ($option['label'] ?? '');
			$label_key = \strtolower(\remove_accents($label));
			if ($needle !== '' && ($needle === $value || $needle === $label_key)) {
				return $label;
			}
		}

		return '';
	}

	function cmx_carent_vertrag_address_lines_from_string(string $address, string $country = ''): array {
		$address = \trim($address);
		$country = \trim($country);
		$country_label = $country !== '' ? cmx_carent_vertrag_known_country_label($country) : '';
		if ($address === '') {
			return $country_label !== '' ? [$country_label] : [];
		}

		$lines = \preg_split('/\s*,\s*/u', $address) ?: [];
		$lines = \array_values(\array_filter(\array_map(static function (string $value): string {
			return \trim($value);
		}, $lines), static function (string $value) use ($country): bool {
			if ($value === '') {
				return false;
			}
			if ($country !== '' && \strcasecmp($value, $country) === 0) {
				return false;
			}
			return true;
		}));
		$lines = \array_map(static function (string $value): string {
			$country_label = cmx_carent_vertrag_known_country_label($value);
			return $country_label !== '' ? $country_label : $value;
		}, $lines);
		if ($country_label !== '') {
			$has_country = false;
			foreach ($lines as $line) {
				if (\strcasecmp($line, $country_label) === 0 || \strcasecmp($line, $country) === 0) {
					$has_country = true;
					break;
				}
			}
			if (!$has_country) {
				$lines[] = $country_label;
			}
		}

		return $lines;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_party_cell_text')) {
	function cmx_carent_vertrag_party_cell_text(array $address_lines, string $phone = '', string $email = ''): string {
		$parts = [];

		$address = \implode(', ', \array_values(\array_filter(\array_map(static function (string $value): string {
			return \trim($value);
		}, $address_lines), static fn(string $value): bool => $value !== '')));
		if ($address !== '') {
			$parts[] = $address;
		}

		$phone = \trim($phone);
		if ($phone !== '') {
			$parts[] = $phone;
		}

		$email = \trim($email);
		if ($email !== '') {
			$parts[] = $email;
		}

		return \implode(' · ', $parts);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_join_text_parts')) {
	function cmx_carent_vertrag_join_text_parts(array $parts, string $separator = ' · '): string {
		$parts = \array_values(\array_filter(\array_map(static function ($value): string {
			return \trim((string) $value);
		}, $parts), static fn(string $value): bool => $value !== ''));

		return \implode($separator, $parts);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_join_html_parts')) {
	function cmx_carent_vertrag_join_html_parts(array $parts, string $separator = ' <span class="contract-inline-separator">·</span> '): string {
		$parts = \array_values(\array_filter(\array_map(static function ($value): string {
			return \trim((string) $value);
		}, $parts), static fn(string $value): bool => $value !== ''));

		return \implode($separator, $parts);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_inline_field_html')) {
	function cmx_carent_vertrag_inline_field_html(string $label, string $value): string {
		$label = \trim($label);
		$value = \trim($value);
		if ($value === '') {
			return '';
		}

		return '<span class="contract-inline-label">' . \esc_html($label) . '</span> <strong class="contract-inline-value">' . \esc_html($value) . '</strong>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_bold_value_html')) {
	function cmx_carent_vertrag_bold_value_html(string $value): string {
		$value = \trim($value);
		if ($value === '') {
			return '';
		}

		return '<strong class="contract-inline-value">' . \esc_html($value) . '</strong>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_vehicle_article_data')) {
	function cmx_carent_vertrag_vehicle_article_data(int $artikel_id): array {
		if ($artikel_id <= 0 || !\get_post_status($artikel_id)) {
			return [];
		}

		$defaults = \function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_article_meta_defaults')
			? (array) cmx_carent_fahrzeug_article_meta_defaults($artikel_id)
			: [];
		$chassi_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_CHASSI_NR')
			? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_CHASSI_NR')
			: '_cmx_artikel_carent_chassi_nr';
		$km_stand_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KM_STAND')
			? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KM_STAND')
			: '_cmx_artikel_carent_km_stand';
		$treibstoff = '';

		if (\function_exists(__NAMESPACE__ . '\\cmx_artikel_carent_fuel_term_id') && \function_exists(__NAMESPACE__ . '\\cmx_artikel_carent_fuel_taxonomy')) {
			$treibstoff_taxonomy = (string) cmx_artikel_carent_fuel_taxonomy();
			$treibstoff_term_id = (int) cmx_artikel_carent_fuel_term_id($artikel_id);
			if ($treibstoff_term_id <= 0 && \function_exists(__NAMESPACE__ . '\\cmx_artikel_carent_default_fuel_term_id')) {
				$treibstoff_term_id = (int) cmx_artikel_carent_default_fuel_term_id();
			}
			$treibstoff = cmx_carent_vertrag_term_label($treibstoff_taxonomy, $treibstoff_term_id);
		}
		if ($treibstoff === '' && \function_exists(__NAMESPACE__ . '\\cmx_artikel_carent_default_fuel_label')) {
			$treibstoff = \trim((string) cmx_artikel_carent_default_fuel_label());
		}

		return [
			'chassi'      => \trim((string) \get_post_meta($artikel_id, $chassi_key, true)),
			'kennzeichen' => \trim((string) ($defaults['kennzeichen'] ?? '')),
			'treibstoff'  => $treibstoff,
			'km_stand'    => \trim((string) (\get_post_meta($artikel_id, $km_stand_key, true) ?: ($defaults['km_stand_uebernahme'] ?? ''))),
			'begrenzung'  => \trim((string) ($defaults['begrenzung'] ?? '')),
			'mehrpreis'   => \trim((string) ($defaults['mehrpreis'] ?? '')),
			'kasko_min'   => \trim((string) ($defaults['kasko_min'] ?? '')),
			'kasko_max'   => \trim((string) ($defaults['kasko_max'] ?? '')),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_vehicle_variant_data')) {
	function cmx_carent_vertrag_vehicle_variant_data(int $artikel_id, $variant_index): array {
		if ($artikel_id <= 0 || !\get_post_status($artikel_id) || $variant_index === '' || !\is_numeric((string) $variant_index)) {
			return [];
		}

		$variant_index = (int) $variant_index;
		$entry = \function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_variant_entry')
			? (array) cmx_carent_fahrzeug_variant_entry($artikel_id, $variant_index)
			: [];
		$raw_row = \function_exists(__NAMESPACE__ . '\\cmx_vermietung_vehicle_variant_raw_row')
			? (array) cmx_vermietung_vehicle_variant_raw_row($artikel_id, $variant_index)
			: [];
		$label = \function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_variant_label')
			? \trim((string) cmx_carent_fahrzeug_variant_label($artikel_id, $variant_index, false))
			: \trim((string) ($entry['title'] ?? ''));

		return [
			'label'         => $label,
			'sku'           => \trim((string) ($entry['sku'] ?? ($raw_row['sku'] ?? ''))),
			'groessen'      => \trim((string) ($entry['groessen'] ?? '')),
			'ausfuehrungen' => \trim((string) ($entry['ausfuehrungen'] ?? '')),
			'materialien'   => \trim((string) ($entry['materialien'] ?? '')),
			'farben'        => \trim((string) ($entry['farben'] ?? '')),
			'einheit'       => \trim((string) ($entry['einheit'] ?? '')),
			'anzahl'        => \trim((string) ($raw_row['anzahl'] ?? '')),
			'vk'            => \trim((string) ($entry['vk'] ?? ($raw_row['vk'] ?? ''))),
			'belegtext'     => \trim((string) ($raw_row['belegtext'] ?? '')),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_vehicle_article_lines')) {
	function cmx_carent_vertrag_vehicle_article_lines(array $vehicle): array {
		$article = (array) ($vehicle['article_meta'] ?? []);
		$article_id = isset($vehicle['article_id']) ? (int) $vehicle['article_id'] : 0;
		$article_title = $article_id > 0 ? \trim((string) \get_the_title($article_id)) : '';
		if ($article_title !== '' && \function_exists(__NAMESPACE__ . '\\cmx_normalize_minus_sign')) {
			$article_title = (string) cmx_normalize_minus_sign($article_title);
		}
		$begrenzung_raw = \trim((string) ($article['begrenzung'] ?? ''));
		$begrenzung_value = 'unbegrenzt';
		if ($begrenzung_raw !== '' && cmx_carent_vertrag_parse_number($begrenzung_raw) > 0) {
			$begrenzung_value = cmx_carent_vertrag_format_int($begrenzung_raw);
		}

		$primary_parts = [
			cmx_carent_vertrag_bold_value_html($article_title),
			cmx_carent_vertrag_bold_value_html((string) ($article['kennzeichen'] ?? '')),
			cmx_carent_vertrag_inline_field_html('Treibstoff', (string) ($article['treibstoff'] ?? '')),
		];
		$secondary_parts = [
			cmx_carent_vertrag_inline_field_html('KM-Stand', cmx_carent_vertrag_format_int((string) ($article['km_stand'] ?? ''))),
			cmx_carent_vertrag_inline_field_html('KM-Begrenzung', $begrenzung_value),
			cmx_carent_vertrag_inline_field_html('KM-Mehrpreis', cmx_carent_vertrag_format_money((string) ($article['mehrpreis'] ?? ''))),
		];
		$insurance_parts = [
			cmx_carent_vertrag_inline_field_html('Kasko min', cmx_carent_vertrag_format_money((string) ($article['kasko_min'] ?? ''))),
			cmx_carent_vertrag_inline_field_html('Kasko max', cmx_carent_vertrag_format_money((string) ($article['kasko_max'] ?? ''))),
		];

		return [
			'primary' => cmx_carent_vertrag_join_html_parts($primary_parts),
			'secondary' => cmx_carent_vertrag_join_html_parts($secondary_parts),
			'insurance' => cmx_carent_vertrag_join_html_parts($insurance_parts),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_vehicle_variant_text')) {
	function cmx_carent_vertrag_vehicle_variant_text(array $vehicle): string {
		$variant = (array) ($vehicle['variant_meta'] ?? []);
		$detail_parts = [
			cmx_carent_vertrag_inline_field_html('ArtikelNr', (string) ($variant['sku'] ?? '')),
			cmx_carent_vertrag_inline_field_html('Einheit', (string) ($variant['einheit'] ?? '')),
			cmx_carent_vertrag_inline_field_html('Anzahl', cmx_carent_vertrag_format_int((string) ($variant['anzahl'] ?? ''))),
			cmx_carent_vertrag_inline_field_html('VK', cmx_carent_vertrag_format_money((string) ($variant['vk'] ?? ''))),
		];
		return cmx_carent_vertrag_join_html_parts($detail_parts);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_legacy_pdf_dir_prefix')) {
	function cmx_carent_vertrag_legacy_pdf_dir_prefix(int $post_id): string {
		return 'misbuero/carent/vertraege/' . $post_id . '/';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_legacy_pdf_dir_prefixes')) {
	function cmx_carent_vertrag_legacy_pdf_dir_prefixes(int $post_id): array {
		return [
			'misbuero/carent/vertraege/' . $post_id . '/',
			'misbuero/caerent/vertraege/' . $post_id . '/',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_archive_pdf_dir_prefix')) {
	function cmx_carent_vertrag_archive_pdf_dir_prefix(): string {
		return 'misbuero/archiv/';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_legacy_archive_pdf_dir_prefix')) {
	function cmx_carent_vertrag_legacy_archive_pdf_dir_prefix(): string {
		return \rtrim((string) (\defined(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_PDF_ARCHIVE_REL_DIR')
			? \constant(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_PDF_ARCHIVE_REL_DIR')
			: 'carent/vertraege'), '/') . '/';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_previous_archive_pdf_dir_prefix')) {
	function cmx_carent_vertrag_previous_archive_pdf_dir_prefix(): string {
		return 'misbuero/archiv/carent/';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_previous_archive_pdf_dir_prefixes')) {
	function cmx_carent_vertrag_previous_archive_pdf_dir_prefixes(): array {
		return [
			'misbuero/archiv/carent/',
			'misbuero/archiv/caerent/',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_is_managed_pdf_rel_path')) {
	function cmx_carent_vertrag_is_managed_pdf_rel_path(string $rel_path, int $post_id = 0): bool {
		$rel_path = cmx_carent_vertrag_normalize_rel_path($rel_path);
		if ($rel_path === '') {
			return false;
		}

		if (\preg_match('#^misbuero/archiv/\d{4}/ca(?:e)?rent/vertraege/#', $rel_path) === 1) {
			return true;
		}
		if (\str_starts_with($rel_path, 'vertraege/')) {
			return true;
		}
		if ($post_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_carent_contract_folder_rel_dir')) {
			$contract_dir = \function_exists(__NAMESPACE__ . '\\cmx_carent_contract_archive_rel_dir')
				? 'misbuero/archiv/' . \trim((string) cmx_carent_contract_archive_rel_dir($post_id), '/')
				: \trim((string) cmx_carent_contract_folder_rel_dir($post_id), '/');
			if ($contract_dir !== '' && \str_starts_with($rel_path, $contract_dir . '/') && \strtolower((string) \pathinfo($rel_path, \PATHINFO_EXTENSION)) === 'pdf') {
				return true;
			}
		}
		if (\str_starts_with($rel_path, cmx_carent_vertrag_legacy_archive_pdf_dir_prefix())) {
			return true;
		}
		foreach (cmx_carent_vertrag_previous_archive_pdf_dir_prefixes() as $prefix) {
			if (\str_starts_with($rel_path, $prefix)) {
				return true;
			}
		}

		if ($post_id > 0) {
			foreach (cmx_carent_vertrag_legacy_pdf_dir_prefixes($post_id) as $prefix) {
				if (\str_starts_with($rel_path, $prefix)) {
					return true;
				}
			}
		}

		return false;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_abs_path_from_rel_path')) {
	function cmx_carent_vertrag_abs_path_from_rel_path(string $rel_path): string {
		$rel_path = cmx_carent_vertrag_normalize_rel_path($rel_path);
		if ($rel_path === '') {
			return '';
		}
		if (\str_starts_with($rel_path, 'misbuero/archiv/')) {
			return \wp_normalize_path((string) \WP_CONTENT_DIR . '/uploads/' . $rel_path);
		}
		if (\preg_match('#^misbuero/ca(?:e)?rent/vertraege/#', $rel_path) === 1) {
			return \wp_normalize_path((string) \WP_CONTENT_DIR . '/uploads/' . $rel_path);
		}
		return \function_exists(__NAMESPACE__ . '\\cmx_dav_module_file_path')
			? \wp_normalize_path((string) cmx_dav_module_file_path('carent', $rel_path))
			: '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_dokument_uploads_meta_key')) {
	function cmx_carent_vertrag_dokument_uploads_meta_key(): string {
		return \defined(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
			: '_cmx_dokumente_uploads';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_dokument_self_meta_key')) {
	function cmx_carent_vertrag_dokument_self_meta_key(): string {
		return \defined(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
			: '_cmx_dokumente_files';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_dokument_rel_meta_key')) {
	function cmx_carent_vertrag_dokument_rel_meta_key(string $post_type = 'carent'): string {
		$post_type = \sanitize_key($post_type);
		$meta_key = 'cmx_dokumente_rel_' . $post_type;

		if (\defined(__NAMESPACE__ . '\\CMX_DOK_REL_META')) {
			$map = \constant(__NAMESPACE__ . '\\CMX_DOK_REL_META');
			if (\is_array($map) && isset($map[$post_type]) && \is_string($map[$post_type]) && \trim($map[$post_type]) !== '') {
				$meta_key = \trim($map[$post_type]);
			}
		}

		return $meta_key;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_dokument_category_taxonomy')) {
	function cmx_carent_vertrag_dokument_category_taxonomy(): string {
		if (\defined(__NAMESPACE__ . '\\TAX_DOKUMENTE_KATEGORIEN')) {
			$taxonomy = (string) \constant(__NAMESPACE__ . '\\TAX_DOKUMENTE_KATEGORIEN');
			if ($taxonomy !== '' && \taxonomy_exists($taxonomy)) {
				return $taxonomy;
			}
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_tax_key')) {
			$taxonomy = (string) cmx_tax_key('dokumente', 'Kategorien');
			if ($taxonomy !== '' && \taxonomy_exists($taxonomy)) {
				return $taxonomy;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_assign_dokument_category')) {
	function cmx_carent_vertrag_assign_dokument_category(int $doc_id, string $term_name = 'Vertrag'): void {
		if ($doc_id <= 0 || (string) \get_post_type($doc_id) !== 'dokumente') {
			return;
		}

		$taxonomy = cmx_carent_vertrag_dokument_category_taxonomy();
		if ($taxonomy === '') {
			return;
		}

		$term_name = \trim($term_name);
		if ($term_name === '') {
			return;
		}

		$term = \get_term_by('name', $term_name, $taxonomy);
		if (!$term instanceof \WP_Term || \is_wp_error($term)) {
			$term = \get_term_by('slug', \sanitize_title($term_name), $taxonomy);
		}
		if ((!$term instanceof \WP_Term || \is_wp_error($term)) && \taxonomy_exists($taxonomy)) {
			$created = \wp_insert_term($term_name, $taxonomy, ['slug' => \sanitize_title($term_name)]);
			if (!\is_wp_error($created) && !empty($created['term_id'])) {
				$term = \get_term((int) $created['term_id'], $taxonomy);
			}
		}
		if (!$term instanceof \WP_Term || \is_wp_error($term)) {
			return;
		}

		\wp_set_post_terms($doc_id, [(int) $term->term_id], $taxonomy, false);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_dokument_title')) {
	function cmx_carent_vertrag_dokument_title(int $post_id, array $storage): string {
		$title = \trim((string) \get_the_title($post_id));
		if ($title !== '') {
			return $title;
		}

		$file_name = \trim((string) ($storage['file_name'] ?? ''));
		$base_name = $file_name !== '' ? (string) \pathinfo($file_name, \PATHINFO_FILENAME) : '';

		return $base_name !== '' ? $base_name : ('Mietvertrag ' . $post_id);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_collect_dokument_file_refs')) {
	function cmx_carent_vertrag_collect_dokument_file_refs(int $doc_id): array {
		if ($doc_id <= 0 || (string) \get_post_type($doc_id) !== 'dokumente') {
			return [];
		}

		$file_refs = [];
		$primary = cmx_carent_vertrag_normalize_rel_path((string) \get_post_meta($doc_id, '_cmx_dokumente_file_path', true));
		if ($primary !== '') {
			$file_refs[] = $primary;
		}

		$self_meta_key = cmx_carent_vertrag_dokument_self_meta_key();
		$self_files = (array) \get_post_meta($doc_id, $self_meta_key, true);
		foreach ($self_files as $entry) {
			$file_ref = '';
			if (\is_numeric($entry)) {
				$file_ref = (string) \get_post_meta((int) $entry, '_wp_attached_file', true);
			} elseif (\is_string($entry)) {
				$file_ref = $entry;
			}

			$file_ref = cmx_carent_vertrag_normalize_rel_path($file_ref);
			if ($file_ref !== '') {
				$file_refs[] = $file_ref;
			}
		}

		return \array_values(\array_unique($file_refs));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_find_existing_dokument')) {
	function cmx_carent_vertrag_find_existing_dokument(int $post_id, array $storage): int {
		$stored_doc_id = (int) \get_post_meta($post_id, CMX_CARENT_VERTRAG_PDF_DOKUMENT_META, true);
		if ($stored_doc_id > 0 && (string) \get_post_type($stored_doc_id) === 'dokumente') {
			return $stored_doc_id;
		}

		$uploads_meta_key = cmx_carent_vertrag_dokument_uploads_meta_key();
		$doc_ids = (array) \get_post_meta($post_id, $uploads_meta_key, true);
		$doc_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $doc_ids))));
		if (empty($doc_ids)) {
			return 0;
		}

		$target_rel_path = cmx_carent_vertrag_normalize_rel_path((string) ($storage['rel_path'] ?? ''));
		$target_file_name = \strtolower((string) \basename($target_rel_path));
		$legacy_contract_dir_prefix = cmx_carent_vertrag_legacy_pdf_dir_prefix($post_id);

		for ($i = \count($doc_ids) - 1; $i >= 0; $i--) {
			$doc_id = (int) $doc_ids[$i];
			if ($doc_id <= 0 || (string) \get_post_type($doc_id) !== 'dokumente') {
				continue;
			}

			foreach (cmx_carent_vertrag_collect_dokument_file_refs($doc_id) as $file_ref) {
				if ($file_ref === $target_rel_path) {
					return $doc_id;
				}
				if ($file_ref !== '' && \str_starts_with($file_ref, $legacy_contract_dir_prefix)) {
					return $doc_id;
				}
				if ($target_file_name !== '' && \strtolower((string) \basename($file_ref)) === $target_file_name) {
					return $doc_id;
				}
			}
		}

		return 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_unlink_upload_rel_path')) {
	function cmx_carent_vertrag_unlink_upload_rel_path(string $rel_path, string $preserve_rel_path = ''): void {
		$rel_path = cmx_carent_vertrag_normalize_rel_path($rel_path);
		$preserve_rel_path = cmx_carent_vertrag_normalize_rel_path($preserve_rel_path);
		if ($rel_path === '' || $rel_path === $preserve_rel_path) {
			return;
		}

		$absolute_path = cmx_carent_vertrag_abs_path_from_rel_path($rel_path);
		if ($absolute_path === '' || !\is_file($absolute_path)) {
			return;
		}

		@unlink($absolute_path);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_sync_dokument')) {
	function cmx_carent_vertrag_sync_dokument(int $post_id, array $storage) {
		$rel_path = cmx_carent_vertrag_normalize_rel_path((string) ($storage['rel_path'] ?? ''));
		if ($post_id <= 0 || $rel_path === '') {
			return new \WP_Error('pdf_document_sync_invalid', 'PDF-Dokument konnte nicht verknuepft werden.');
		}

		$doc_id = cmx_carent_vertrag_find_existing_dokument($post_id, $storage);
		$doc_title = cmx_carent_vertrag_dokument_title($post_id, $storage);

		if ($doc_id <= 0) {
			$doc_id = \wp_insert_post([
				'post_type'   => 'dokumente',
				'post_title'  => $doc_title,
				'post_status' => 'publish',
			], true);
			if (\is_wp_error($doc_id) || !(int) $doc_id) {
				return new \WP_Error('pdf_document_create_failed', 'PDF-Dokument konnte nicht angelegt werden.');
			}
			$doc_id = (int) $doc_id;
		} else {
			$post_update = ['ID' => $doc_id];
			$existing_title = \trim((string) \get_the_title($doc_id));
			$existing_status = (string) \get_post_status($doc_id);
			$needs_update = false;

			if ($existing_title !== $doc_title) {
				$post_update['post_title'] = $doc_title;
				$needs_update = true;
			}
			if ($existing_status !== 'publish') {
				$post_update['post_status'] = 'publish';
				$needs_update = true;
			}

			if ($needs_update) {
				\wp_update_post($post_update);
			}
		}

		$old_file_refs = cmx_carent_vertrag_collect_dokument_file_refs($doc_id);

		\update_post_meta($doc_id, '_cmx_dokumente_file_path', $rel_path);
		\update_post_meta($doc_id, cmx_carent_vertrag_dokument_self_meta_key(), [$rel_path]);
		\update_post_meta($doc_id, cmx_carent_vertrag_dokument_rel_meta_key('carent'), [$post_id]);
		cmx_carent_vertrag_assign_dokument_category($doc_id, 'Vertrag');

		$uploads_meta_key = cmx_carent_vertrag_dokument_uploads_meta_key();
		$related_doc_ids = (array) \get_post_meta($post_id, $uploads_meta_key, true);
		$related_doc_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $related_doc_ids))));
		$related_doc_ids[] = $doc_id;
		$related_doc_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $related_doc_ids))));
		\update_post_meta($post_id, $uploads_meta_key, $related_doc_ids);
		\update_post_meta($post_id, CMX_CARENT_VERTRAG_PDF_DOKUMENT_META, $doc_id);

		foreach ($old_file_refs as $old_file_ref) {
			if ($old_file_ref === '' || $old_file_ref === $rel_path) {
				continue;
			}
			if (!cmx_carent_vertrag_is_managed_pdf_rel_path($old_file_ref, $post_id)) {
				continue;
			}
			cmx_carent_vertrag_unlink_upload_rel_path($old_file_ref, $rel_path);
		}

		return $doc_id;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_render_pdf_html')) {
	function cmx_carent_vertrag_render_pdf_html(array $data): string {
		if ($data === []) {
			return '';
		}
		$contact = (array) ($data['contact'] ?? []);
		$self = (array) ($data['self'] ?? []);
		$vehicle = (array) ($data['vehicle'] ?? []);
		$self_id = isset($self['id']) ? (int) $self['id'] : 0;
		$logo_src = \trim((string) ($self['logo_src'] ?? ''));
		$self_title = \trim((string) (($self['branding'] ?? '') ?: ($self['title'] ?? '')));
		$self_post_title = \trim((string) ($self['title'] ?? ''));
		$self_subtitle = \trim((string) ($self['subtitle'] ?? ''));
		$self_address = \trim((string) ($self['address'] ?? ''));
		$self_email = \sanitize_email((string) ($self['email'] ?? ''));
		if ($self_email === '' && \function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_primary_email')) {
			$self_email = \sanitize_email((string) cmx_email_self_contact_primary_email());
		}
		$self_phone = '';
		$self_phones = (array) ($self['phones'] ?? []);
		if (!empty($self_phones[0]['display'])) {
			$self_phone = \trim((string) $self_phones[0]['display']);
		} elseif (!empty($self_phones[0]['value'])) {
			$self_phone = \trim((string) $self_phones[0]['value']);
		} elseif ($self_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_phone')) {
			$self_phone = \trim((string) cmx_kommunikation_primary_phone($self_id));
		}
		$self_website_href = \trim((string) ($self['website'] ?? ''));
		$self_website = \trim((string) (($self['website_label'] ?? '') ?: $self_website_href));
		if ($self_website === '' && \function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_url')) {
			$self_website_href = \trim((string) cmx_email_self_contact_url());
			$self_website = $self_website_href;
		}
		$self_website_link = $self_website_href;
		if ($self_website_link !== '' && \preg_match('~^[a-z][a-z0-9+.-]*://~i', $self_website_link) !== 1) {
			$self_website_link = 'https://' . \ltrim($self_website_link, '/');
		}
		if ($self_address === '' && $self_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_address_string')) {
			$self_address = \trim((string) cmx_telefonbuch_address_string($self_id));
		}
		$self_country_key = \defined(__NAMESPACE__ . '\\CMX_RECHNUNG_META_LAND')
			? (string) \constant(__NAMESPACE__ . '\\CMX_RECHNUNG_META_LAND')
			: '_cmx_rechnung_land';
		$self_country = $self_id > 0 ? \trim((string) \get_post_meta($self_id, $self_country_key, true)) : '';
		$self_address_lines = cmx_carent_vertrag_address_lines_from_string($self_address, $self_country);
		$contact_id = isset($contact['id']) ? (int) $contact['id'] : 0;
		$contact_address = \trim((string) ($contact['address'] ?? ''));
		if ($contact_address === '' && $contact_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_address_string')) {
			$contact_address = \trim((string) cmx_telefonbuch_address_string($contact_id));
		}
		$contact_country = $contact_id > 0 ? \trim((string) \get_post_meta($contact_id, $self_country_key, true)) : '';
		$contact_address_lines = cmx_carent_vertrag_address_lines_from_string($contact_address, $contact_country);
		$contact_email = \sanitize_email((string) ($contact['email'] ?? ''));
		if ($contact_email === '' && $contact_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_email')) {
			$contact_email = \sanitize_email((string) cmx_kommunikation_primary_email($contact_id));
		}
		$contact_phone = '';
		$contact_phones = (array) ($contact['phones'] ?? []);
		if (!empty($contact_phones[0]['display'])) {
			$contact_phone = \trim((string) $contact_phones[0]['display']);
		} elseif (!empty($contact_phones[0]['value'])) {
			$contact_phone = \trim((string) $contact_phones[0]['value']);
		} elseif ($contact_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_phone')) {
			$contact_phone = \trim((string) cmx_kommunikation_primary_phone($contact_id));
		}
		$self_party_text = cmx_carent_vertrag_party_cell_text($self_address_lines);
		if ($self_title !== '') {
			$self_party_text = \trim($self_title . ($self_party_text !== '' ? ' · ' . $self_party_text : ''));
		}
		$contact_party_text = cmx_carent_vertrag_party_cell_text($contact_address_lines, $contact_phone, $contact_email);
		$self_party_html = cmx_carent_vertrag_bold_value_html($self_party_text);
		$contact_party_html = cmx_carent_vertrag_bold_value_html($contact_party_text);
		$vehicle_article_lines = cmx_carent_vertrag_vehicle_article_lines($vehicle);
		$vehicle_article_text = \trim((string) ($vehicle_article_lines['primary'] ?? ''));
		$vehicle_article_secondary_text = \trim((string) ($vehicle_article_lines['secondary'] ?? ''));
		$vehicle_insurance_text = \trim((string) ($vehicle_article_lines['insurance'] ?? ''));
		$vehicle_insurance_notice = 'Selbstbehalt im Schadenfall zu Lasten Kunde: Haftpflicht, Teil- oder Vollkasko wie oben angegeben.';
		$vehicle_insurance_full_text = $vehicle_insurance_text;
		if ($vehicle_insurance_notice !== '') {
			$vehicle_insurance_full_text .= ($vehicle_insurance_full_text !== '' ? '' : '')
				. '<div class="contract-inline-note">' . \esc_html($vehicle_insurance_notice) . '</div>';
		}
		$vehicle_variant_text = cmx_carent_vertrag_vehicle_variant_text($vehicle);
		$transfer = (array) ($data['transfer'] ?? []);
		$uebernahme = (array) ($transfer['uebernahme'] ?? []);
		$uebernahme_video = (array) ($uebernahme['video'] ?? []);
		$uebernahme_video_url = \trim((string) ($uebernahme_video['url'] ?? ''));
		$uebernahme_video_label = \trim((string) ($uebernahme_video['label'] ?? ''));
		$uebernahme_video_poster_src = cmx_carent_vertrag_video_poster_src((int) ($uebernahme_video['id'] ?? 0));
		$uebernahme_photo_items = [];
		foreach ((array) ($uebernahme['fotos'] ?? []) as $photo_item) {
			$photo_item = (array) $photo_item;
			$attachment = (array) ($photo_item['attachment'] ?? []);
			$photo_src = cmx_carent_vertrag_photo_src($attachment);
			if ($photo_src === '') {
				continue;
			}
			$uebernahme_photo_items[] = [
				'src' => $photo_src,
				'url' => cmx_carent_vertrag_photo_url($attachment),
				'label' => \trim((string) ($photo_item['term_label'] ?? '')),
				'style' => cmx_carent_vertrag_fit_image_style((string) ($attachment['path'] ?? ''), 46.7, 19.4),
			];
		}
		$uebernahme_km_stand = \trim((string) ($uebernahme['km_stand'] ?? ''));
		if ($uebernahme_km_stand === '') {
			$uebernahme_km_stand = \trim((string) (($vehicle['article_meta']['km_stand'] ?? '')));
		}
		$uebernahme_km_stand = cmx_carent_vertrag_format_int($uebernahme_km_stand);
		$uebernahme_datum = cmx_carent_vertrag_format_date(\trim((string) ($uebernahme['datum'] ?? '')));
		$uebernahme_uhrzeit = \trim((string) ($uebernahme['uhrzeit'] ?? ''));
		$rueckgabe = (array) ($transfer['rueckgabe'] ?? []);
		$rueckgabe_ort = \trim((string) ($rueckgabe['ort'] ?? ''));
		$rueckgabe_km_stand = cmx_carent_vertrag_format_int(\trim((string) ($rueckgabe['km_stand'] ?? '')));
		$rueckgabe_datum = cmx_carent_vertrag_format_date(\trim((string) ($rueckgabe['datum'] ?? '')));
		$rueckgabe_uhrzeit = \trim((string) ($rueckgabe['uhrzeit'] ?? ''));
		$rueckgabe_video = (array) ($rueckgabe['video'] ?? []);
		$rueckgabe_video_url = \trim((string) ($rueckgabe_video['url'] ?? ''));
		$rueckgabe_video_label = \trim((string) ($rueckgabe_video['label'] ?? ''));
		$rueckgabe_video_poster_src = cmx_carent_vertrag_video_poster_src((int) ($rueckgabe_video['id'] ?? 0));
		$rueckgabe_photo_items = [];
		foreach ((array) ($rueckgabe['fotos'] ?? []) as $photo_item) {
			$photo_item = (array) $photo_item;
			$attachment = (array) ($photo_item['attachment'] ?? []);
			$photo_src = cmx_carent_vertrag_photo_src($attachment);
			if ($photo_src === '') {
				continue;
			}
			$rueckgabe_photo_items[] = [
				'src' => $photo_src,
				'url' => cmx_carent_vertrag_photo_url($attachment),
				'label' => \trim((string) ($photo_item['term_label'] ?? '')),
				'style' => cmx_carent_vertrag_fit_image_style((string) ($attachment['path'] ?? ''), 46.7, 19.4),
			];
		}
		$article = (array) ($vehicle['article_meta'] ?? []);
		$variant = (array) ($vehicle['variant_meta'] ?? []);
		$article_id = isset($vehicle['article_id']) ? (int) $vehicle['article_id'] : 0;
		$vehicle_title = $article_id > 0 ? \trim((string) \get_the_title($article_id)) : '';
		if ($vehicle_title === '') {
			$vehicle_title = \trim((string) ($vehicle['label'] ?? ''));
		}
		$vehicle_image_src = $article_id > 0 ? cmx_carent_vertrag_article_image_src($article_id) : '';
		$vehicle_number = \trim((string) (($variant['sku'] ?? '') ?: ($vehicle['number'] ?? '')));
		$vehicle_plate = \trim((string) (($vehicle['kennzeichen'] ?? '') ?: ($article['kennzeichen'] ?? '')));
		$vehicle_fuel = \trim((string) ($article['treibstoff'] ?? ''));
		$vehicle_limit_raw = \trim((string) (($vehicle['begrenzung'] ?? '') ?: ($article['begrenzung'] ?? '')));
		$vehicle_limit = $vehicle_limit_raw !== '' && cmx_carent_vertrag_parse_number($vehicle_limit_raw) > 0
			? cmx_carent_vertrag_format_int($vehicle_limit_raw)
			: 'unbegrenzt';
		$vehicle_more_price = cmx_carent_vertrag_format_money((string) (($vehicle['mehrpreis'] ?? '') ?: ($article['mehrpreis'] ?? '')));
		$vehicle_unit = \trim((string) ($variant['einheit'] ?? ''));
		$vehicle_price = cmx_carent_vertrag_format_money((string) ($vehicle['mietpreis'] ?? ($variant['vk'] ?? '')));
		$vehicle_amount = \trim((string) ($vehicle['anzahl'] ?? ($variant['anzahl'] ?? '')));
		$vehicle_kasko_min = cmx_carent_vertrag_format_money((string) (($vehicle['kasko_min'] ?? '') ?: ($article['kasko_min'] ?? '')));
		$vehicle_kasko_max = cmx_carent_vertrag_format_money((string) (($vehicle['kasko_max'] ?? '') ?: ($article['kasko_max'] ?? '')));
		$rental_summe_raw = \trim((string) ($vehicle['summe'] ?? ''));
		$rental_total = $rental_summe_raw !== ''
			? cmx_carent_vertrag_format_money(\round(cmx_carent_vertrag_parse_number($rental_summe_raw), 0))
			: '';
		$billing = (array) ($data['billing'] ?? []);
		$vehicle_more_km = cmx_carent_vertrag_format_int((string) ($billing['mehrkilometer'] ?? '0'));
		$more_km_total = cmx_carent_vertrag_format_money(
			cmx_carent_vertrag_parse_number($vehicle_more_km) * cmx_carent_vertrag_parse_number($vehicle_more_price)
		);
		$damage = (array) ($data['damage'] ?? []);
		$damage_rows = (array) ($damage['rows'] ?? []);
		$damage_ort = \trim((string) ($damage['ort'] ?? ''));
		$damage_datum = cmx_carent_vertrag_format_date(\trim((string) ($damage['datum'] ?? '')));
		$damage_uhrzeit = \trim((string) ($damage['uhrzeit'] ?? ''));
		$damage_weitere_beteiligte = \trim((string) ($damage['weitere_beteiligte'] ?? ''));
		$damage_weitere_angaben = \trim((string) ($damage['weitere_angaben'] ?? ''));
		$damage_unfallprotokoll = \trim((string) ($damage['unfallprotokoll'] ?? ''));
		$damage_anerkennung = \trim((string) ($damage['anerkennung'] ?? ''));
		$has_damage_protocol = $damage_rows !== []
			|| $damage_ort !== ''
			|| $damage_datum !== ''
			|| $damage_uhrzeit !== ''
			|| $damage_weitere_beteiligte !== ''
			|| $damage_weitere_angaben !== ''
			|| $damage_unfallprotokoll !== ''
			|| $damage_anerkennung !== '';
		$damage_weitere_beteiligte_html = \wp_kses(\nl2br($damage_weitere_beteiligte, false), ['br' => []]);
		$damage_weitere_angaben_html = \wp_kses(\nl2br($damage_weitere_angaben, false), ['br' => []]);
		$damage_unfallprotokoll_html = \wp_kses(\nl2br($damage_unfallprotokoll, false), ['br' => []]);
		$damage_anerkennung_html = \wp_kses(\nl2br($damage_anerkennung, false), ['br' => []]);
		$damage_cost_sum = 0.0;
		foreach ($damage_rows as $damage_row) {
			$damage_row = (array) $damage_row;
			$damage_cost_sum += cmx_carent_vertrag_parse_number((string) ($damage_row['kosten'] ?? ''));
		}
		$billing_damage_total = \trim((string) ($billing['schadenkosten'] ?? ''));
		$damage_total = $billing_damage_total !== ''
			? cmx_carent_vertrag_format_money($billing_damage_total)
			: ($damage_cost_sum > 0 ? cmx_carent_vertrag_format_money((string) $damage_cost_sum) : ($damage_rows !== [] ? $vehicle_kasko_max : ''));
		$show_damage_total = cmx_carent_vertrag_parse_number($damage_total) > 0;
		$total_amount = cmx_carent_vertrag_format_money(
			cmx_carent_vertrag_parse_number($rental_total) + cmx_carent_vertrag_parse_number($more_km_total) + cmx_carent_vertrag_parse_number($damage_total)
		);
		$duration_label = cmx_carent_vertrag_join_text_parts([$vehicle_amount, $vehicle_unit], ' ');
		$special_notes = \trim((string) ($uebernahme['besondere_abmachungen'] ?? ''));
		$special_notes_html = \wp_kses(\nl2br($special_notes, false), ['br' => []]);
		$contact_title_for_template = \trim((string) ($contact['title'] ?? ''));
		$contact_name_parts = \preg_split('/\s+/', $contact_title_for_template, 2);
		$contact_first_name = \trim((string) ($contact_name_parts[0] ?? ''));
		$contact_last_name = \trim((string) ($contact_name_parts[1] ?? ''));
		$contract_title_for_template = \trim((string) ($data['title'] ?? ''));
		$contract_number_for_template = \trim((string) ($data['number'] ?? ($data['contract_number'] ?? '')));
		$uebernahme_template_html = cmx_carent_vertrag_render_transfer_template([
			'anrede' => $contact_title_for_template !== '' ? 'Guten Tag ' . $contact_title_for_template : 'Guten Tag',
			'tageszeit' => 'Guten Tag',
			'vorname' => $contact_first_name,
			'nachname' => $contact_last_name,
			'firma' => $self_title !== '' ? $self_title : \get_bloginfo('name'),
			'vertrag' => $contract_title_for_template,
			'vertrags_id' => $contract_number_for_template,
			'fahrzeug' => $vehicle_title,
			'kennzeichen' => $vehicle_plate,
			'uebernahme' => \trim($uebernahme_datum . ($uebernahme_uhrzeit !== '' ? ' ' . $uebernahme_uhrzeit : '')),
			'rueckgabe' => \trim($rueckgabe_datum . ($rueckgabe_uhrzeit !== '' ? ' ' . $rueckgabe_uhrzeit : '')),
			'betrag' => $rental_total,
			'besondere_abmachungen' => $special_notes_html,
			'logo' => '',
		]);
		$documents = (array) ($data['documents'] ?? []);
		$fuehrerausweis = (array) ($documents['fuehrerausweis'] ?? []);
		$identitaetskarte = (array) ($documents['identitaetskarte'] ?? []);
		$fuehrerausweis_src = cmx_carent_vertrag_image_data_uri((int) ($fuehrerausweis['id'] ?? 0));
		$identitaetskarte_src = cmx_carent_vertrag_image_data_uri((int) ($identitaetskarte['id'] ?? 0));
		$fuehrerausweis_url = \trim((string) ($fuehrerausweis['url'] ?? ''));
		$identitaetskarte_url = \trim((string) ($identitaetskarte['url'] ?? ''));
		$self_signature = (array) (($uebernahme['signatures'] ?? [])['vermieter'] ?? []);
		$contact_signature = (array) (($uebernahme['signatures'] ?? [])['mieter'] ?? []);
		$rueckgabe_self_signature = (array) (($rueckgabe['signatures'] ?? [])['vermieter'] ?? []);
		$rueckgabe_contact_signature = (array) (($rueckgabe['signatures'] ?? [])['mieter'] ?? []);
		$has_documents = $fuehrerausweis_src !== '' || $identitaetskarte_src !== '';
		$has_uebernahme_video = $uebernahme_video_url !== '';
		$has_uebernahme_photos = $uebernahme_photo_items !== [];
		$has_uebernahme_detail_sections = $has_documents || $has_uebernahme_video || $has_uebernahme_photos;
		$has_rueckgabe_video = $rueckgabe_video_url !== '';
		$has_rueckgabe_photos = $rueckgabe_photo_items !== [];
		$has_rueckgabe_signatures = !empty($rueckgabe_self_signature['data_uri']) || !empty($rueckgabe_contact_signature['data_uri']);
		$has_rueckgabe_summary = $rueckgabe_ort !== ''
			|| $rueckgabe_datum !== ''
			|| $rueckgabe_uhrzeit !== ''
			|| $rueckgabe_km_stand !== '';
		$has_rueckgabe_section = $has_rueckgabe_summary
			|| $has_rueckgabe_video
			|| $has_rueckgabe_photos
			|| $has_rueckgabe_signatures;
		$has_second_page_content = $has_uebernahme_detail_sections
			|| $has_damage_protocol
			|| $has_rueckgabe_section;
		$self_street = \trim((string) ($self_address_lines[0] ?? ''));
		$self_zip_city = \trim((string) ($self_address_lines[1] ?? ''));
		if (\count($self_address_lines) > 2) {
			$self_zip_city = \trim($self_zip_city . ' ' . \implode(' ', \array_slice($self_address_lines, 2)));
		}
		$logo_link = $self_website_link !== '' ? $self_website_link : \home_url('/');
		\ob_start();
		?>
		<!doctype html>
		<html lang="de">
		<head>
			<meta charset="utf-8">
			<style>
				@page{margin:8mm 0 0}
				@page:first{margin:0}
				*{box-sizing:border-box}
				body{margin:0;background:#fff;color:#111;font-family:DejaVu Sans,Arial,sans-serif;font-size:10px;line-height:1.25}
				.sheet{position:relative;width:210mm;min-height:297mm;padding:44.2mm 9.5mm 14mm;background:#fff}
				.topbar{position:absolute;left:0;top:0;width:210mm;height:27mm;background:#001b3d;color:#fff;padding:4mm 9.5mm}
				.header-table{width:100%;border-collapse:collapse;table-layout:fixed}
				.header-logo-cell{width:26%;padding:5.2mm 4mm 0 0;border-right:1px solid #315f91;vertical-align:middle}
				.header-title-cell{width:74%;padding-left:4mm;vertical-align:middle}
				.brand-logo-wrap{display:inline-block;background:#fff;border-radius:1.7mm;padding:2.4mm 4.2mm;line-height:0}
				.brand-logo-link{display:inline-block;text-decoration:none;border:0;line-height:0}
				.brand-logo{display:block;max-width:42mm;max-height:23.6mm;width:auto;height:auto}
				.brand-fallback{font-size:25px;line-height:1;font-weight:800;letter-spacing:.5px}
				.brand-sub{margin-top:2mm;font-size:7px;letter-spacing:1.8px;text-transform:uppercase}
				.doc-title{font-size:25px;line-height:1;font-weight:800;letter-spacing:1.8px}
				.doc-company{margin-top:1mm;font-size:8.2px;line-height:1.05;font-weight:400;letter-spacing:.3px}
				.header-rule{height:1px;background:#2f5b88;margin:2.5mm 13.2mm 2.2mm 0}
				.header-contact-table{width:100%;border-collapse:collapse;table-layout:fixed}
				.header-contact-table td{color:#fff;font-size:8.2px;vertical-align:middle}
				.header-contact-address{width:30%}
				.header-contact-mail{width:25%;position:relative;left:-5.3mm}
				.header-contact-phone{width:22%;position:relative;left:-5.3mm}
				.header-contact-web{width:23%;position:relative;left:-5.3mm}
				.header-contact-table .pdf-icon{width:4.4mm;height:4.4mm;vertical-align:middle;margin-right:1.8mm}
				.header-contact-line{display:inline-block;color:#fff;font-weight:400;line-height:1.15;vertical-align:middle}
				.header-contact-link{color:#fff;text-decoration:none;font-weight:400}
				.section-title{margin:0 13.2mm 5mm 0;color:#001b3d;font-size:13px;font-weight:800;text-transform:uppercase;border-bottom:1px solid #c7d4e6;padding-bottom:1.6mm}
				.section-title .icon-badge{margin-right:4mm}
				.icon-badge{display:inline-block;width:5.6mm;height:5.6mm;background:#001b3d;border-radius:1.2mm;text-align:center;vertical-align:middle;padding-top:1.4mm}
				.icon-badge .pdf-icon{width:3.8mm;height:3.8mm}
				.pdf-field-icon{width:4.2mm;height:4.2mm}
				.card-table{width:177.3mm;border-collapse:separate;border-spacing:0;table-layout:fixed;margin:0 0 4mm}
				.card{height:35mm;vertical-align:top;background:#f4f7fb;border-radius:2mm;padding:0;text-align:left}
				.card-inner{height:26mm;padding:5mm 5mm 4mm;text-align:left}
				.card-left{width:57.8mm;max-width:57.8mm}
				.card-left .card-inner{width:62mm;background:#f4f7fb;border-radius:2mm}
				.card-right{width:115.5mm;max-width:115.5mm;background:transparent}
				.card-right .card-inner{position:relative;left:-39.7mm;width:108mm;background:#f4f7fb;border-radius:2mm}
				.card-spacer{width:4mm;max-width:4mm;padding:0;background:transparent}
				.party-table{width:100%;border-collapse:collapse}
				.party-icon{width:8.5mm;vertical-align:top}
				.party-icon .icon-badge{width:5.6mm;height:5.6mm;border-radius:999mm;padding-top:1.4mm;line-height:1}
				.party-icon .icon-badge .pdf-icon{width:3.8mm;height:3.8mm;vertical-align:top}
				.party-title{font-size:9.2px;font-weight:800;text-transform:uppercase;margin:.5mm 0 2mm}
				.party-line{font-size:9px;line-height:1.42}
				.tenant-details{width:100%;border-collapse:collapse;table-layout:fixed}
				.tenant-address{width:55%;vertical-align:top}
				.tenant-contact-cell{width:45%;vertical-align:top;padding-top:0}
				.party-contact{margin-top:0;font-size:9px;line-height:1.35}
				.party-contact .pdf-icon{width:10px;height:10px;vertical-align:middle;margin-right:4px}
				.party-contact .phone-icon{position:relative;top:5px}
				.party-contact .mail-icon{position:relative;top:5px}
				.vehicle-box{width:196mm;border:1px solid #c7d4e6;border-radius:1.3mm;margin-bottom:4.5mm}
				.vehicle-table{width:196mm;border-collapse:collapse;table-layout:fixed}
				.vehicle-image-cell{width:60.1mm;height:40mm;text-align:center;vertical-align:middle;border-right:1px solid #c7d4e6}
				.vehicle-image{max-width:60mm;max-height:34mm;width:auto;height:auto}
				.vehicle-placeholder{color:#001b3d}
				.vehicle-placeholder .pdf-icon{width:22mm;height:22mm}
				.vehicle-field{width:33%;height:10mm;border-bottom:1px solid #c7d4e6;padding:2mm 3mm;vertical-align:middle}
				.vehicle-field+.vehicle-field{border-left:1px solid #c7d4e6}
				.vehicle-table tr:last-child .vehicle-field{border-bottom:0}
				.field-icon{display:inline-block;width:8mm;vertical-align:top}
				.field-copy{display:inline-block;width:42mm;vertical-align:top}
				.field-copy strong{display:block;color:#001b3d;font-size:9px}
				.field-copy span{display:block;font-size:8.8px}
				.two-col{width:190mm;border-collapse:collapse;table-layout:fixed;margin-bottom:4.5mm}
				.two-col-panel{width:50%;max-width:50%;vertical-align:top}
				.two-col-left,.two-col-right{padding:0}
				.two-col-left .two-col-inner{margin-right:3mm}
				.two-col-right .two-col-inner{margin-left:3mm;width:99mm}
				.two-col .section-title{width:auto;margin-right:0}
				.section-gap{height:4mm;line-height:4mm}
				.panel{background:#f4f7fb;border-radius:2mm;padding:4mm;min-height:41mm}
				.two-col-right .panel{min-height:53mm}
				.insurance-table{width:86%;margin:2mm auto 3mm;border-collapse:collapse}
				.insurance-table td{padding:2mm 0;font-size:10px;font-weight:800}
				.insurance-table tr+tr td{border-top:1px solid #d5deec}
				.insurance-table td:last-child{text-align:right}
				.insurance-note{text-align:center;color:#001b3d;font-weight:800;margin-top:2mm}
				.check-row{margin:0 0 2.2mm}
				.check-row .pdf-icon{width:10px;height:10px;vertical-align:top;margin-right:3mm;position:relative;top:3px}
				.transfer-copy p{margin:0 0 2.2mm}
				.transfer-copy p:last-child{margin-bottom:0}
				.transfer-copy ul,.transfer-copy ol{margin:0 0 2.2mm 0;padding:0;list-style:none}
				.transfer-copy li{margin:0 0 1.3mm}
				.transfer-copy li .transfer-check-icon{width:10px;height:10px;vertical-align:top;margin-right:1.5mm;position:relative;top:3px}
				.document-table{width:200.7mm;border-collapse:separate;border-spacing:3mm 0;table-layout:fixed;margin:0 -1.5mm 4.5mm}
				.document-cell{width:50%;background:#f4f7fb;border:1px solid #c7d4e6;padding:3mm;text-align:center;vertical-align:top}
				.document-cell:first-child{border-top-left-radius:1.3mm;border-bottom-left-radius:1.3mm}
				.document-cell:last-child{border-top-right-radius:1.3mm;border-bottom-right-radius:1.3mm}
				.document-label{color:#001b3d;font-size:9.5px;font-weight:800;text-transform:uppercase;margin-bottom:2mm;text-align:left}
				.document-image{max-width:86mm;max-height:42mm;width:auto;height:auto}
				.video-box{width:187.9mm;min-height:50mm;background:#f4f7fb;border-radius:2mm;padding:3mm 4mm;margin-bottom:4.5mm;font-size:9.2px}
				.video-label{display:block;color:#001b3d;font-weight:800;text-transform:uppercase;margin-bottom:1mm}
				.video-poster{display:block;max-width:86mm;max-height:42mm;width:auto;height:auto;margin-bottom:2mm;border-radius:1.3mm}
				.video-link{display:block;color:#001b3d;text-decoration:none;font-weight:800}
				.photo-table{width:200mm;border-collapse:separate;border-spacing:2mm 2mm;table-layout:fixed;margin:0 -1mm 4.5mm}
				.photo-cell{width:25%;height:27.4mm;background:#f4f7fb;border:1px solid #c7d4e6;border-radius:1.3mm;text-align:center;vertical-align:middle;padding:1mm}
				.photo-link{display:block;color:#001b3d;text-decoration:none}
				.photo-label{display:block;color:#001b3d;font-size:7.8px;font-weight:800;text-transform:uppercase;margin-bottom:1mm;text-align:left}
				.photo-image{display:block;margin:0 auto;border-radius:1mm}
				.transfer-strip{width:189mm;background:#f4f7fb;border-radius:2mm;padding:3mm 4mm;margin-bottom:4.5mm}
				.page-break-before{page-break-before:always;break-before:page}
				.transfer-table{width:100%;border-collapse:collapse;table-layout:fixed}
				.transfer-table td{font-size:9.2px;vertical-align:top;border-right:1px solid #8aa7cc;padding:0 5mm}
				.transfer-table td:last-child{border-right:0}
				.transfer-label{display:block;color:#001b3d;font-weight:800;text-transform:uppercase}
				.transfer-value{display:block;font-weight:800}
				.damage-summary{width:189mm;background:#f4f7fb;border-radius:2mm;padding:3mm 4mm;margin-bottom:4.5mm}
				.damage-summary-table{width:100%;border-collapse:collapse;table-layout:fixed}
				.damage-summary-table td{font-size:9.2px;vertical-align:top;border-right:1px solid #8aa7cc;padding:0 5mm}
				.damage-summary-table td:last-child{border-right:0}
				.damage-label{display:block;color:#001b3d;font-weight:800;text-transform:uppercase}
				.damage-value{display:block;font-weight:800}
				.damage-table{width:197mm;border-collapse:collapse;table-layout:fixed;margin-bottom:4.5mm}
				.damage-table th{background:#001b3d;color:#fff;text-align:left;text-transform:uppercase;font-size:8.5px;padding:2.5mm 4mm}
				.damage-table th:not(:last-child),.damage-table td:not(:last-child){border-right:1px solid #c7d4e6}
				.damage-table td{border:1px solid #c7d4e6;border-top:0;padding:2mm 4mm;font-size:9px;vertical-align:top}
				.damage-table tr:first-child th:first-child{border-top-left-radius:1.3mm}
				.damage-table tr:first-child th:last-child{border-top-right-radius:1.3mm}
				.damage-table tr:last-child td:first-child{border-bottom-left-radius:1.3mm}
				.damage-table tr:last-child td:last-child{border-bottom-right-radius:1.3mm}
				.damage-text-grid{width:197mm;border-collapse:separate;border-spacing:0 2.5mm;table-layout:fixed;margin-bottom:4.5mm}
				.damage-text-grid td{width:50%;vertical-align:top;background:#f4f7fb;border:1px solid #c7d4e6;border-radius:1.3mm;padding:3mm 4mm;font-size:9px}
				.damage-text-grid td+td{border-left:3mm solid #fff}
				.return-strip{width:189mm;background:#f4f7fb;border-radius:2mm;padding:3mm 4mm;margin-bottom:4.5mm}
				.return-table{width:100%;border-collapse:collapse;table-layout:fixed}
				.return-table td{font-size:9.2px;vertical-align:top;border-right:1px solid #8aa7cc;padding:0 5mm}
				.return-table td:last-child{border-right:0}
				.return-label{display:block;color:#001b3d;font-weight:800;text-transform:uppercase}
				.return-value{display:block;font-weight:800}
				.billing{width:197mm;border-collapse:collapse;table-layout:fixed;margin-bottom:8mm}
				.billing th{background:#001b3d;color:#fff;text-align:left;text-transform:uppercase;font-size:8.5px;padding:2.5mm 4mm}
				.billing th:not(:last-child),.billing td:not(:last-child){border-right:1px solid #c7d4e6}
				.billing td{border:1px solid #c7d4e6;border-top:0;padding:2mm 4mm;font-size:9px}
				.billing .num{text-align:center}
				.billing .money{text-align:right}
				.billing tr:first-child th:first-child{border-top-left-radius:1.3mm}
				.billing tr:first-child th:last-child{border-top-right-radius:1.3mm}
				.billing tr:last-child td:first-child{border-bottom-left-radius:1.3mm}
				.billing tr:last-child td:last-child{border-bottom-right-radius:1.3mm}
				.billing-total-label{text-align:right!important;color:#001b3d;font-size:13px!important;font-weight:800}
				.billing-total-value{background:#001b3d;color:#fff;font-size:14px!important;font-weight:800;text-align:center!important}
				.signatures{width:100%;border-collapse:collapse;table-layout:fixed;margin-top:2mm}
				.signatures td{width:50%;vertical-align:bottom;padding-right:10mm}
				.signature-line{border-bottom:1px solid #c7d4e6;height:11mm;position:relative}
				.signature-line-mieter{margin-right:14px}
				.signature-line .pdf-icon{width:11mm;height:11mm;vertical-align:bottom;margin-right:3mm}
				.signature-img{max-width:45mm;max-height:10mm;width:auto;height:auto}
				.signature-label{font-size:8.5px;margin-top:1.2mm}
				.footer{display:none}
			</style>
		</head>
		<body>
			<div class="sheet">
				<div class="topbar">
					<table class="header-table" role="presentation" cellpadding="0" cellspacing="0" border="0">
						<tr>
							<td class="header-logo-cell">
								<?php if ($logo_src !== '') : ?>
									<a href="<?php echo \esc_url($logo_link); ?>" class="brand-logo-link"><span class="brand-logo-wrap"><img src="<?php echo \esc_attr($logo_src); ?>" alt="Rentify" class="brand-logo"></span></a>
								<?php else : ?>
									<a href="<?php echo \esc_url($logo_link); ?>" class="brand-logo-link">
										<div class="brand-fallback"><?php echo \esc_html($self_title !== '' ? $self_title : 'RENTIFY.'); ?></div>
										<div class="brand-sub">Autovermietung</div>
									</a>
								<?php endif; ?>
							</td>
							<td class="header-title-cell">
								<div class="doc-title">MIETVERTRAG</div>
								<?php if ($self_post_title !== '') : ?><div class="doc-company"><?php echo \esc_html($self_post_title); ?></div><?php endif; ?>
								<div class="header-rule"></div>
								<table class="header-contact-table" role="presentation" cellpadding="0" cellspacing="0" border="0">
									<tr>
										<td class="header-contact-address">
											<?php if ($self_street !== '' || $self_zip_city !== '') : ?>
												<?php echo cmx_carent_vertrag_icon_svg('map-pin', 'pdf-icon', '#ffffff'); ?><span class="header-contact-line"><?php echo \esc_html($self_street); ?><br><?php echo \esc_html($self_zip_city); ?></span>
											<?php endif; ?>
										</td>
										<td class="header-contact-mail">
											<?php if ($self_email !== '') : ?><?php echo cmx_carent_vertrag_icon_svg('mail', 'pdf-icon', '#ffffff'); ?><span class="header-contact-line"><a href="<?php echo \esc_url('mailto:' . $self_email); ?>" class="header-contact-link"><?php echo \esc_html($self_email); ?></a></span><?php endif; ?>
										</td>
										<td class="header-contact-phone">
											<?php if ($self_phone !== '') : ?><?php echo cmx_carent_vertrag_icon_svg('phone', 'pdf-icon', '#ffffff'); ?><span class="header-contact-line"><a href="<?php echo \esc_url('tel:' . \preg_replace('/\s+/', '', $self_phone)); ?>" class="header-contact-link"><?php echo \esc_html($self_phone); ?></a></span><?php endif; ?>
										</td>
										<td class="header-contact-web">
											<?php if ($self_website !== '') : ?><?php echo cmx_carent_vertrag_icon_svg('globe', 'pdf-icon', '#ffffff'); ?><span class="header-contact-line"><a href="<?php echo \esc_url($self_website_link); ?>" class="header-contact-link"><?php echo \esc_html($self_website); ?></a></span><?php endif; ?>
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
				</div>

				<div class="section-title"><?php echo cmx_carent_vertrag_icon_badge('contact-round'); ?>KONTAKTDATEN</div>
				<table class="card-table" role="presentation" cellpadding="0" cellspacing="0" border="0">
					<colgroup>
						<col style="width:57.8mm;">
						<col style="width:4mm;">
						<col style="width:115.5mm;">
					</colgroup>
					<tr>
						<td class="card card-left" style="width:57.8mm;min-width:57.8mm;max-width:57.8mm;">
							<div class="card-inner">
								<table class="party-table" role="presentation"><tr><td class="party-icon"><?php echo cmx_carent_vertrag_icon_badge('user-round'); ?></td><td>
									<div class="party-title">Vermieter</div>
									<?php if ($self_title !== '') : ?><div class="party-line"><?php echo \esc_html($self_title); ?></div><?php endif; ?>
									<?php foreach ($self_address_lines as $address_line) : ?><div class="party-line"><?php echo \esc_html($address_line); ?></div><?php endforeach; ?>
								</td></tr></table>
							</div>
						</td>
						<td class="card-spacer" style="width:4mm;min-width:4mm;max-width:4mm;padding:0;background:transparent;"></td>
						<td class="card card-right" style="width:115.5mm;min-width:115.5mm;max-width:115.5mm;">
							<div class="card-inner">
								<table class="party-table" role="presentation"><tr><td class="party-icon"><?php echo cmx_carent_vertrag_icon_badge('user-round'); ?></td><td>
									<div class="party-title">Mieter</div>
									<table class="tenant-details" role="presentation"><tr>
										<td class="tenant-address">
											<?php if (($contact['title'] ?? '') !== '') : ?><div class="party-line"><?php echo \esc_html((string) $contact['title']); ?></div><?php endif; ?>
											<?php foreach ($contact_address_lines as $address_line) : ?><div class="party-line"><?php echo \esc_html($address_line); ?></div><?php endforeach; ?>
										</td>
										<td class="tenant-contact-cell">
											<div class="party-contact">
												<?php if ($contact_phone !== '') : ?><div><?php echo cmx_carent_vertrag_icon_svg('phone', 'pdf-icon phone-icon', '#001b3d'); ?><?php echo \esc_html($contact_phone); ?></div><?php endif; ?>
												<?php if ($contact_email !== '') : ?><div><?php echo cmx_carent_vertrag_icon_svg('mail', 'pdf-icon mail-icon', '#001b3d'); ?><?php echo \esc_html($contact_email); ?></div><?php endif; ?>
											</div>
										</td>
									</tr></table>
								</td></tr></table>
							</div>
						</td>
					</tr>
				</table>

				<div class="section-gap"></div>
				<div class="section-gap"></div>
				<div class="section-title"><?php echo cmx_carent_vertrag_icon_badge('car'); ?>FAHRZEUG</div>
				<div class="vehicle-box">
					<table class="vehicle-table" role="presentation" cellpadding="0" cellspacing="0" border="0">
						<colgroup>
							<col style="width:60.1mm;">
							<col style="width:67.95mm;">
							<col style="width:67.95mm;">
						</colgroup>
						<tr>
							<td class="vehicle-image-cell" rowspan="4">
								<?php if ($vehicle_image_src !== '') : ?>
									<img src="<?php echo \esc_attr($vehicle_image_src); ?>" alt="" class="vehicle-image">
								<?php else : ?>
									<div class="vehicle-placeholder"><?php echo cmx_carent_vertrag_icon_svg('car-front', 'pdf-icon', '#001b3d'); ?></div>
								<?php endif; ?>
							</td>
							<?php echo cmx_carent_vertrag_field_html('car', 'Typ', $vehicle_title); ?>
							<?php echo cmx_carent_vertrag_field_html('tag', 'Fahrzeug-Nr.', $vehicle_plate); ?>
						</tr>
						<tr>
							<?php echo cmx_carent_vertrag_field_html('fuel', 'Treibstoff', $vehicle_fuel); ?>
							<?php echo cmx_carent_vertrag_field_html('gauge', 'KM-Begrenzung', $vehicle_limit); ?>
						</tr>
						<tr>
							<?php echo cmx_carent_vertrag_field_html('tag', 'Artikel Nr.', $vehicle_number); ?>
							<?php echo cmx_carent_vertrag_field_html('road', 'KM-Mehrpreis', $vehicle_more_price !== '' ? $vehicle_more_price . ' CHF / km' : ''); ?>
						</tr>
						<tr>
							<?php echo cmx_carent_vertrag_field_html('calendar', 'Einheit', $vehicle_unit); ?>
							<?php echo cmx_carent_vertrag_field_html('badge-swiss-franc', 'Preis pro Einheit', $vehicle_price !== '' ? $vehicle_price . ' CHF' : ''); ?>
						</tr>
					</table>
				</div>

				<div class="section-gap"></div>
				<div class="section-gap"></div>
				<table class="two-col" role="presentation" cellpadding="0" cellspacing="0" border="0">
					<colgroup>
						<col style="width:50%;">
						<col style="width:50%;">
					</colgroup>
					<tr>
						<td class="two-col-panel two-col-left" style="width:50%;max-width:50%;padding:0;">
							<div class="two-col-inner" style="margin-right:3mm;">
								<div class="section-title"><?php echo cmx_carent_vertrag_icon_badge('shield-check'); ?>VERSICHERUNG</div>
								<div class="panel">
									<table class="insurance-table" role="presentation">
										<tr><td>KASKO MIN.</td><td><?php echo cmx_carent_vertrag_html_value($vehicle_kasko_min); ?> CHF</td></tr>
										<tr><td>KASKO MAX.</td><td><?php echo cmx_carent_vertrag_html_value($vehicle_kasko_max); ?> CHF</td></tr>
									</table>
									<div class="insurance-note">Selbstbehalt im Schadenfall zu Lasten Kunde:</div>
									<div style="text-align:center">Haftpflicht, Teil- oder Vollkasko wie oben angegeben.</div>
								</div>
							</div>
						</td>
						<td class="two-col-panel two-col-right" style="width:50%;max-width:50%;padding:0;">
							<div class="two-col-inner" style="margin-left:3mm;">
								<div class="section-title"><?php echo cmx_carent_vertrag_icon_badge('clipboard-list'); ?>ÜBERNAHME</div>
								<div class="panel transfer-copy">
									<?php echo $uebernahme_template_html; ?>
								</div>
							</div>
						</td>
					</tr>
				</table>

				<?php if ($has_uebernahme_detail_sections) : ?>
					<div class="page-break-before"></div>
				<?php endif; ?>
				<?php if ($has_documents) : ?>
					<div class="section-gap"></div>
					<div class="section-title"><?php echo cmx_carent_vertrag_icon_badge('id-card'); ?>AUSWEISE</div>
					<table class="document-table" role="presentation" cellpadding="0" cellspacing="0" border="0">
						<tr>
							<?php if ($fuehrerausweis_src !== '') : ?>
								<td class="document-cell">
									<div class="document-label">Führerausweis</div>
									<?php if ($fuehrerausweis_url !== '') : ?><a href="<?php echo \esc_url($fuehrerausweis_url); ?>"><?php endif; ?><img src="<?php echo \esc_attr($fuehrerausweis_src); ?>" alt="" class="document-image"><?php if ($fuehrerausweis_url !== '') : ?></a><?php endif; ?>
								</td>
							<?php endif; ?>
							<?php if ($identitaetskarte_src !== '') : ?>
								<td class="document-cell document-cell-id">
									<div class="document-label">Identitätskarte</div>
									<?php if ($identitaetskarte_url !== '') : ?><a href="<?php echo \esc_url($identitaetskarte_url); ?>"><?php endif; ?><img src="<?php echo \esc_attr($identitaetskarte_src); ?>" alt="" class="document-image"><?php if ($identitaetskarte_url !== '') : ?></a><?php endif; ?>
								</td>
							<?php endif; ?>
						</tr>
					</table>
				<?php endif; ?>

				<?php if ($has_uebernahme_video) : ?>
					<div class="section-gap"></div>
					<div class="section-title"><?php echo cmx_carent_vertrag_icon_badge('video'); ?>ÜBERNAHMEVIDEO</div>
					<div class="video-box">
						<a href="<?php echo \esc_url($uebernahme_video_url); ?>" class="video-link"><?php if ($uebernahme_video_poster_src !== '') : ?><img src="<?php echo \esc_attr($uebernahme_video_poster_src); ?>" alt="" class="video-poster"><?php endif; ?><?php echo \esc_html($uebernahme_video_label !== '' ? $uebernahme_video_label : 'Video herunterladen'); ?></a>
					</div>
				<?php endif; ?>

				<?php if ($has_uebernahme_photos) : ?>
					<div class="section-gap"></div>
					<div class="section-title"><?php echo cmx_carent_vertrag_icon_badge('images'); ?>ÜBERNAHMEFOTOS</div>
					<table class="photo-table" role="presentation" cellpadding="0" cellspacing="0" border="0">
						<?php for ($photo_row_index = 0, $photo_row_count = (int) \ceil(\count($uebernahme_photo_items) / 4); $photo_row_index < $photo_row_count; $photo_row_index++) : ?>
							<tr>
								<?php for ($photo_col_index = 0; $photo_col_index < 4; $photo_col_index++) : ?>
									<?php $photo = (array) ($uebernahme_photo_items[($photo_row_index * 4) + $photo_col_index] ?? []); ?>
									<?php echo cmx_carent_vertrag_photo_cell_html($photo); ?>
								<?php endfor; ?>
							</tr>
						<?php endfor; ?>
					</table>
				<?php endif; ?>

				<?php if ($has_damage_protocol) : ?>
					<div class="page-break-before"></div>
					<div class="section-title"><?php echo cmx_carent_vertrag_icon_badge('triangle-alert'); ?>SCHADENSPROTOKOLL</div>
					<div class="damage-summary">
						<table class="damage-summary-table" role="presentation">
							<tr>
								<td><span class="damage-label">Ort</span><span class="damage-value"><?php echo \esc_html($damage_ort !== '' ? $damage_ort : '–'); ?></span></td>
								<td><span class="damage-label">Datum</span><span class="damage-value"><?php echo \esc_html($damage_datum !== '' ? $damage_datum : '–'); ?></span></td>
								<td><span class="damage-label">Uhrzeit</span><span class="damage-value"><?php echo \esc_html($damage_uhrzeit !== '' ? $damage_uhrzeit : '–'); ?></span></td>
							</tr>
						</table>
					</div>

					<?php if ($damage_rows !== []) : ?>
						<table class="damage-table" role="presentation" cellpadding="0" cellspacing="0" border="0">
							<tr><th>Schaden</th><th>Bemerkung</th><th>Fotos</th><th>Kosten</th></tr>
							<?php foreach ($damage_rows as $damage_row) : ?>
								<?php $damage_row = (array) $damage_row; ?>
								<tr>
									<td><?php echo cmx_carent_vertrag_html_value((string) ($damage_row['term_label'] ?? '')); ?></td>
									<td><?php echo cmx_carent_vertrag_html_value((string) ($damage_row['note'] ?? '')); ?></td>
									<td><?php echo !empty($damage_row['fotos_gemacht']) ? 'Ja' : 'Nein'; ?></td>
									<td><?php echo \esc_html(\trim((string) ($damage_row['kosten'] ?? '')) !== '' ? cmx_carent_vertrag_format_money((string) ($damage_row['kosten'] ?? '')) . ' CHF' : '–'); ?></td>
								</tr>
							<?php endforeach; ?>
						</table>
					<?php endif; ?>

					<table class="damage-text-grid" role="presentation" cellpadding="0" cellspacing="0" border="0">
						<tr>
							<td><span class="damage-label">Weitere Beteiligte</span><?php echo $damage_weitere_beteiligte_html !== '' ? $damage_weitere_beteiligte_html : '&ndash;'; ?></td>
							<td><span class="damage-label">Weitere Angaben</span><?php echo $damage_weitere_angaben_html !== '' ? $damage_weitere_angaben_html : '&ndash;'; ?></td>
						</tr>
						<tr>
							<td><span class="damage-label">Unfallprotokoll</span><?php echo $damage_unfallprotokoll_html !== '' ? $damage_unfallprotokoll_html : '&ndash;'; ?></td>
							<td><span class="damage-label">Anerkennung</span><?php echo $damage_anerkennung_html !== '' ? $damage_anerkennung_html : '&ndash;'; ?></td>
						</tr>
					</table>
					<div class="section-gap"></div>
				<?php endif; ?>

				<?php if ($has_rueckgabe_summary) : ?>
					<div class="section-title"><?php echo cmx_carent_vertrag_icon_badge('clipboard-check'); ?>RÜCKGABE</div>
					<div class="return-strip">
						<table class="return-table" role="presentation">
							<tr>
								<td><span class="return-label">Ort</span><span class="return-value"><?php echo \esc_html($rueckgabe_ort !== '' ? $rueckgabe_ort : '–'); ?></span></td>
								<td><span class="return-label">Datum</span><span class="return-value"><?php echo \esc_html($rueckgabe_datum !== '' ? $rueckgabe_datum : '–'); ?></span></td>
								<td><span class="return-label">Uhrzeit</span><span class="return-value"><?php echo \esc_html($rueckgabe_uhrzeit !== '' ? $rueckgabe_uhrzeit : '–'); ?></span></td>
								<td><span class="return-label">KM-Stand</span><span class="return-value"><?php echo \esc_html($rueckgabe_km_stand !== '' ? $rueckgabe_km_stand : '–'); ?></span></td>
							</tr>
						</table>
					</div>
				<?php endif; ?>
				<?php if ($has_rueckgabe_video) : ?>
					<div class="section-gap"></div>
					<div class="section-title"><?php echo cmx_carent_vertrag_icon_badge('video'); ?>RÜCKGABEVIDEO</div>
					<div class="video-box">
						<a href="<?php echo \esc_url($rueckgabe_video_url); ?>" class="video-link"><?php if ($rueckgabe_video_poster_src !== '') : ?><img src="<?php echo \esc_attr($rueckgabe_video_poster_src); ?>" alt="" class="video-poster"><?php endif; ?><?php echo \esc_html($rueckgabe_video_label !== '' ? $rueckgabe_video_label : 'Video herunterladen'); ?></a>
					</div>
				<?php endif; ?>

				<?php if ($has_rueckgabe_photos) : ?>
					<div class="section-gap"></div>
					<div class="section-title"><?php echo cmx_carent_vertrag_icon_badge('images'); ?>RÜCKGABEFOTOS</div>
					<table class="photo-table" role="presentation" cellpadding="0" cellspacing="0" border="0">
						<?php for ($photo_row_index = 0, $photo_row_count = (int) \ceil(\count($rueckgabe_photo_items) / 4); $photo_row_index < $photo_row_count; $photo_row_index++) : ?>
							<tr>
								<?php for ($photo_col_index = 0; $photo_col_index < 4; $photo_col_index++) : ?>
									<?php $photo = (array) ($rueckgabe_photo_items[($photo_row_index * 4) + $photo_col_index] ?? []); ?>
									<?php echo cmx_carent_vertrag_photo_cell_html($photo); ?>
								<?php endfor; ?>
							</tr>
						<?php endfor; ?>
					</table>
				<?php endif; ?>

				<?php if ($has_rueckgabe_signatures) : ?>
					<div class="section-gap"></div>
					<table class="signatures" role="presentation" cellpadding="0" cellspacing="0" border="0">
						<tr>
							<td>
								<div class="signature-line"><?php echo cmx_carent_vertrag_icon_svg('signature', 'pdf-icon', '#001b3d'); ?><?php if (!empty($rueckgabe_self_signature['data_uri'])) : ?><img class="signature-img" src="<?php echo \esc_attr((string) $rueckgabe_self_signature['data_uri']); ?>" alt=""><?php endif; ?></div>
								<div class="signature-label">Unterschrift Mitarbeiter Vermieter Rückgabe</div>
							</td>
							<td>
								<div class="signature-line signature-line-mieter"><?php echo cmx_carent_vertrag_icon_svg('signature', 'pdf-icon', '#001b3d'); ?><?php if (!empty($rueckgabe_contact_signature['data_uri'])) : ?><img class="signature-img" src="<?php echo \esc_attr((string) $rueckgabe_contact_signature['data_uri']); ?>" alt=""><?php endif; ?></div>
								<div class="signature-label">Unterschrift Mieter Rückgabe</div>
							</td>
						</tr>
					</table>
				<?php endif; ?>
				<?php if ($has_rueckgabe_section) : ?>
					<div class="section-gap"></div>
					<div class="section-gap"></div>
				<?php endif; ?>

				<?php if (!$has_damage_protocol) : ?><div class="page-break-before"></div><?php endif; ?>
				<div class="section-title"><?php echo cmx_carent_vertrag_icon_badge('calendar'); ?>MIETDATEN</div>
				<div class="transfer-strip">
					<table class="transfer-table" role="presentation">
						<tr>
							<td><span class="transfer-label">Übernahme</span><span class="transfer-value"><?php echo \esc_html($uebernahme_datum !== '' ? $uebernahme_datum : '–'); ?></span><span>Uhrzeit: <?php echo \esc_html($uebernahme_uhrzeit !== '' ? $uebernahme_uhrzeit : '–'); ?></span></td>
							<td><span class="transfer-label">KM-Stand</span><span class="transfer-value"><?php echo \esc_html($uebernahme_km_stand !== '' ? $uebernahme_km_stand : '–'); ?></span></td>
							<td><span class="transfer-label">Rückgabe</span><span class="transfer-value"><?php echo \esc_html($rueckgabe_datum !== '' ? $rueckgabe_datum : '–'); ?></span><span>Uhrzeit: <?php echo \esc_html($rueckgabe_uhrzeit !== '' ? $rueckgabe_uhrzeit : '–'); ?></span></td>
							<td><span class="transfer-label">KM-Stand</span><span class="transfer-value"><?php echo \esc_html($rueckgabe_km_stand !== '' ? $rueckgabe_km_stand : '–'); ?></span></td>
						</tr>
					</table>
				</div>

				<div class="section-gap"></div>
				<div class="section-gap"></div>
				<div class="section-title"><?php echo cmx_carent_vertrag_icon_badge('calculator'); ?>ABRECHNUNG</div>
				<table class="billing" role="presentation" cellpadding="0" cellspacing="0" border="0">
					<tr><th>Position</th><th class="num">Menge</th><th class="num">Einheitspreis</th><th class="money">Total</th></tr>
					<tr><td>Mietdauer</td><td class="num"><?php echo \esc_html($duration_label !== '' ? $duration_label : '–'); ?></td><td class="num"><?php echo \esc_html($vehicle_price !== '' ? $vehicle_price . ' CHF' : '–'); ?></td><td class="money"><?php echo \esc_html($rental_total !== '' ? $rental_total . ' CHF' : '–'); ?></td></tr>
					<tr><td>Mehrkilometer</td><td class="num"><?php echo \esc_html($vehicle_more_km !== '' ? $vehicle_more_km . ' km' : '0 km'); ?></td><td class="num"><?php echo \esc_html($vehicle_more_price !== '' ? $vehicle_more_price . ' CHF / km' : '–'); ?></td><td class="money"><?php echo \esc_html($more_km_total !== '' ? $more_km_total . ' CHF' : '0.00 CHF'); ?></td></tr>
					<?php if ($show_damage_total) : ?><tr><td>Zusatzkosten (Schaden)</td><td class="num">–</td><td class="num">–</td><td class="money"><?php echo \esc_html($damage_total . ' CHF'); ?></td></tr><?php endif; ?>
					<tr><td colspan="3" class="billing-total-label">TOTAL</td><td class="billing-total-value"><?php echo \esc_html($total_amount !== '' ? $total_amount . ' CHF' : '–'); ?></td></tr>
				</table>

				<div class="section-gap"></div>
				<table class="signatures" role="presentation" cellpadding="0" cellspacing="0" border="0">
					<tr>
						<td>
							<div class="signature-line"><?php echo cmx_carent_vertrag_icon_svg('signature', 'pdf-icon', '#001b3d'); ?><?php if (!empty($self_signature['data_uri'])) : ?><img class="signature-img" src="<?php echo \esc_attr((string) $self_signature['data_uri']); ?>" alt=""><?php endif; ?></div>
							<div class="signature-label">Unterschrift Mitarbeiter Vermieter</div>
							<div class="signature-label"><?php echo \esc_html(\implode(' · ', \array_slice($self_address_lines, 0, 2))); ?></div>
						</td>
						<td>
							<div class="signature-line signature-line-mieter"><?php echo cmx_carent_vertrag_icon_svg('signature', 'pdf-icon', '#001b3d'); ?><?php if (!empty($contact_signature['data_uri'])) : ?><img class="signature-img" src="<?php echo \esc_attr((string) $contact_signature['data_uri']); ?>" alt=""><?php endif; ?></div>
							<div class="signature-label">Unterschrift Mieter</div>
							<?php if (!empty($data['agb_link'])) : ?><div class="signature-label">(Der Mieter erklärt ausdrücklich seine Zustimmung zu den AGB.)</div><?php endif; ?>
						</td>
					</tr>
				</table>
				<div class="footer"></div>
			</div>
		</body>
		</html>
		<?php

		return (string) \ob_get_clean();
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_generate_pdf')) {
	function cmx_carent_vertrag_generate_pdf(int $post_id, array $args = []) {
		$args = \wp_parse_args($args, [
			'data' => null,
			'sync_dokument' => true,
		]);

		$post = \get_post($post_id);
		if (!$post instanceof \WP_Post || (string) $post->post_type !== 'carent') {
			return new \WP_Error('invalid_post', 'Vertrag nicht gefunden.');
		}

		if (!\class_exists(Dompdf::class) || !\class_exists(Options::class)) {
			return new \WP_Error('pdf_unavailable', 'PDF-Erzeugung ist aktuell nicht verfügbar.');
		}

		$data = \is_array($args['data']) ? (array) $args['data'] : cmx_carent_vertrag_collect_data($post_id);
		if ($data === []) {
			return new \WP_Error('missing_contract_data', 'Vertragsdaten konnten nicht geladen werden.');
		}
		$has_media_links = cmx_carent_vertrag_has_media_links($data);

		$storage = cmx_carent_vertrag_pdf_storage($post_id, $data);
		if ($storage === []) {
			return new \WP_Error('pdf_storage_unavailable', 'PDF-Speicherpfad konnte nicht vorbereitet werden.');
		}

		if (!\is_dir((string) $storage['dir']) && !\wp_mkdir_p((string) $storage['dir'])) {
			return new \WP_Error('pdf_storage_failed', 'PDF-Ordner konnte nicht erstellt werden.');
		}

		$html = cmx_carent_vertrag_render_pdf_html($data);
		if (\trim($html) === '') {
			return new \WP_Error('pdf_empty', 'PDF-Inhalt konnte nicht erzeugt werden.');
		}

		$options = new Options();
		$options->set('isRemoteEnabled', true);
		$options->set('isHtml5ParserEnabled', true);
		$options->set('defaultFont', 'DejaVu Sans');

		$dompdf = new Dompdf($options);
		$dompdf->loadHtml($html, 'UTF-8');
		$dompdf->setPaper('A4');
		$dompdf->render();
		$canvas = $dompdf->getCanvas();
		$font_metrics = $dompdf->getFontMetrics();
		$font = $font_metrics->getFont('DejaVu Sans', 'bold');
		$contract_id = \trim(\wp_strip_all_tags((string) $post->post_title));
		if (\method_exists($canvas, 'page_script')) {
			$canvas->page_script(static function (int $page_number, int $page_count, $canvas, $font_metrics) use ($contract_id): void {
				$font = $font_metrics->getFont('DejaVu Sans', 'bold');
				$text = 'Seite ' . $page_number . ' von ' . $page_count;
				$font_size = 8.5;
				$text_width = $font_metrics->getTextWidth($text, $font, $font_size);
				$canvas->filled_rectangle(0, 818, 595.28, 24, [0, 0.106, 0.239]);
				if ($contract_id !== '') {
					$canvas->text(28, 826, $contract_id, $font, $font_size, [1, 1, 1]);
				}
				$canvas->text(595.28 - 28 - $text_width, 826, $text, $font, $font_size, [1, 1, 1]);
			});
		} else {
			if ($contract_id !== '') {
				$canvas->page_text(28, 823, $contract_id, $font, 8.5, [1, 1, 1]);
			}
			$canvas->page_text(500, 823, 'Seite {PAGE_NUM} von {PAGE_COUNT}', $font, 8.5, [1, 1, 1]);
		}

		$pdf_binary = $dompdf->output();
		if (!\is_string($pdf_binary) || $pdf_binary === '') {
			return new \WP_Error('pdf_render_failed', 'PDF konnte nicht gerendert werden.');
		}
		$agb_pdf_path = cmx_carent_vertrag_agb_pdf_path();
		if ($agb_pdf_path !== '') {
			$pdf_binary = cmx_carent_vertrag_append_pdf_file($pdf_binary, $agb_pdf_path);
		}

		$written = \file_put_contents((string) $storage['abs_path'], $pdf_binary);
		if ($written === false) {
			return new \WP_Error('pdf_write_failed', 'PDF konnte nicht gespeichert werden.');
		}

		if (!$has_media_links && \function_exists(__NAMESPACE__ . '\\cmx_pdf_signature_sign_file')) {
			$signature_result = cmx_pdf_signature_sign_file((string) $storage['abs_path'], [
				'type' => 'carent_contract',
				'post_id' => $post_id,
				'title' => (string) $post->post_title,
			]);
			if (\is_wp_error($signature_result)) {
				\error_log('[CMX Carent] PDF-Signatur fehlgeschlagen: ' . $signature_result->get_error_message());
			}
		}

		$previous_rel_path = cmx_carent_vertrag_normalize_rel_path((string) \get_post_meta($post_id, CMX_CARENT_VERTRAG_PDF_REL_META, true));
		$generated_at = \current_time('mysql');

		$dokument_id = 0;
		if (!empty($args['sync_dokument'])) {
			$dokument_id = cmx_carent_vertrag_sync_dokument($post_id, $storage);
			if (\is_wp_error($dokument_id)) {
				return $dokument_id;
			}
		}

		\update_post_meta($post_id, CMX_CARENT_VERTRAG_PDF_REL_META, (string) $storage['rel_path']);
		\update_post_meta($post_id, CMX_CARENT_VERTRAG_PDF_GENERATED_AT_META, $generated_at);

		if ($previous_rel_path !== '' && $previous_rel_path !== (string) $storage['rel_path'] && cmx_carent_vertrag_is_managed_pdf_rel_path($previous_rel_path, $post_id)) {
			cmx_carent_vertrag_unlink_upload_rel_path($previous_rel_path, (string) $storage['rel_path']);
		}

		return [
			'post_id' => $post_id,
			'dokument_id' => (int) $dokument_id,
			'file_name' => (string) $storage['file_name'],
			'rel_path' => (string) $storage['rel_path'],
			'abs_path' => (string) $storage['abs_path'],
			'url' => (string) $storage['url'],
			'public_url' => cmx_carent_vertrag_public_pdf_url($post_id),
			'generated_at' => $generated_at,
			'data' => $data,
		];
	}
}

\add_action('wp_ajax_cmx_carent_preview_vertrag_pdf', function (): void {
	if (!\current_user_can('edit_posts')) {
		\wp_send_json_error([
			'message' => 'Keine Berechtigung.',
		], 403);
	}

	if (!isset($_POST['_ajax_nonce']) || !\wp_verify_nonce((string) \wp_unslash($_POST['_ajax_nonce']), 'cmx_carent_preview_vertrag_pdf')) {
		\wp_send_json_error([
			'message' => 'Ungültige Anfrage.',
		], 403);
	}

	$post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
	if ($post_id <= 0 || !\current_user_can('edit_post', $post_id)) {
		\wp_send_json_error([
			'message' => 'Vertrag nicht gefunden.',
		], 404);
	}

	$result = cmx_carent_vertrag_generate_pdf($post_id);
	if (\is_wp_error($result)) {
		\wp_send_json_error([
			'message' => $result->get_error_message(),
		], 500);
	}

	$url = \trim((string) ($result['url'] ?? ''));
	if ($url === '') {
		\wp_send_json_error([
			'message' => 'PDF-URL konnte nicht ermittelt werden.',
		], 500);
	}

	\wp_send_json_success([
		'message' => 'PDF wurde erstellt.',
		'url' => $url,
		'file_name' => (string) ($result['file_name'] ?? ''),
		'generated_at' => (string) ($result['generated_at'] ?? \current_time('mysql')),
	]);
});

\add_action('admin_post_cmx_carent_live_vertrag_pdf', function (): void {
	$post_id = isset($_GET['post_id']) ? (int) \wp_unslash($_GET['post_id']) : 0;
	if ($post_id <= 0 || (string) \get_post_type($post_id) !== 'carent' || !\current_user_can('edit_post', $post_id)) {
		\wp_die(\esc_html__('Vertrag nicht gefunden.', 'cmx-misbuero'), 404);
	}

	if (!isset($_GET['_wpnonce']) || !\wp_verify_nonce((string) \wp_unslash($_GET['_wpnonce']), 'cmx_carent_live_vertrag_pdf_' . $post_id)) {
		\wp_die(\esc_html__('Ungültige Anfrage.', 'cmx-misbuero'), 403);
	}

	$result = cmx_carent_vertrag_generate_pdf($post_id);
	if (\is_wp_error($result)) {
		\wp_die(\esc_html($result->get_error_message()), 500);
	}

	$url = \trim((string) ($result['url'] ?? ''));
	if ($url === '') {
		\wp_die(\esc_html__('PDF-URL konnte nicht ermittelt werden.', 'cmx-misbuero'), 500);
	}

	\wp_safe_redirect(\add_query_arg('v', \rawurlencode((string) ($result['generated_at'] ?? \time())), $url));
	exit;
});
