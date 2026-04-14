<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

use Dompdf\Dompdf;
use Dompdf\Options;

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
	\define(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_PDF_ARCHIVE_REL_DIR', 'misbuero/carent/vertraege');
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
			$raw = \file_get_contents($path);
			if (\is_string($raw) && $raw !== '') {
				$data_uri = 'data:' . ($mime !== '' ? $mime : 'image/png') . ';base64,' . \base64_encode($raw);
			}
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
		if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fotos_section_config') || !\function_exists(__NAMESPACE__ . '\\cmx_carent_fotos_rows')) {
			return [];
		}

		$config = (array) cmx_carent_fotos_section_config($section);
		$rows = cmx_carent_fotos_rows(
			$post_id,
			(string) ($config['meta_key'] ?? ''),
			(string) ($config['legacy_meta_key'] ?? '')
		);
		$taxonomy = \function_exists(__NAMESPACE__ . '\\cmx_carent_fotos_taxonomy')
			? (string) cmx_carent_fotos_taxonomy()
			: '';

		$items = [];
		foreach ($rows as $row) {
			$row = (array) $row;
			$term_id = isset($row['term_id']) ? (int) $row['term_id'] : 0;
			$attachment_id = isset($row['attachment_id']) ? (int) $row['attachment_id'] : 0;
			$items[] = [
				'term_id' => $term_id,
				'term_label' => cmx_carent_vertrag_term_label($taxonomy, $term_id),
				'attachment' => cmx_carent_vertrag_attachment_info($attachment_id),
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
			if ($term_id <= 0 && $note === '' && !$fotos_gemacht) {
				continue;
			}

			$rows[] = [
				'term_id' => $term_id,
				'note' => $note,
				'fotos_gemacht' => $fotos_gemacht,
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

		$anzahl = \trim((string) \get_post_meta($post_id, $anzahl_key, true));
		$mietpreis = \trim((string) \get_post_meta($post_id, $mietpreis_key, true));
		$summe = $anzahl !== '' && $mietpreis !== ''
			? cmx_carent_vertrag_format_money(cmx_carent_vertrag_parse_number($anzahl) * cmx_carent_vertrag_parse_number($mietpreis))
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
				'video' => cmx_carent_vertrag_attachment_info((int) \get_post_meta($post_id, cmx_carent_vertrag_video_meta_key($section), true)),
				'signatures' => [
					'vermieter' => cmx_carent_vertrag_attachment_info((int) \get_post_meta($post_id, cmx_carent_vertrag_signature_meta_key($section, 'vermieter'), true), true),
					'mieter' => cmx_carent_vertrag_attachment_info((int) \get_post_meta($post_id, cmx_carent_vertrag_signature_meta_key($section, 'mieter'), true), true),
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
			'documents' => [
				'fuehrerausweis' => cmx_carent_vertrag_attachment_info((int) \get_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_CARENT_FUEHRERAUSWEIS_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FUEHRERAUSWEIS_META')
					: '_cmx_carent_fuehrerausweis_attachment_id', true)),
				'identitaetskarte' => cmx_carent_vertrag_attachment_info((int) \get_post_meta($post_id, \defined(__NAMESPACE__ . '\\CMX_CARENT_IDENTITAETSKARTE_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_IDENTITAETSKARTE_META')
					: '_cmx_carent_identitaetskarte_attachment_id', true)),
			],
			'agb_link' => \function_exists(__NAMESPACE__ . '\\cmx_email_agb_link')
				? \trim((string) cmx_email_agb_link())
				: '',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_pdf_storage')) {
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
	function cmx_carent_vertrag_pdf_storage(int $post_id): array {
		$uploads = \wp_get_upload_dir();
		$basedir = \trim((string) ($uploads['basedir'] ?? ''));
		$baseurl = \trim((string) ($uploads['baseurl'] ?? ''));
		$error = \trim((string) ($uploads['error'] ?? ''));

		if ($post_id <= 0 || $basedir === '' || $baseurl === '' || $error !== '') {
			return [];
		}

		$relative_dir = \defined(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_PDF_ARCHIVE_REL_DIR')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_PDF_ARCHIVE_REL_DIR')
			: 'misbuero/carent/vertraege';
		$file_name = cmx_carent_vertrag_pdf_base_name($post_id) . '.pdf';

		return [
			'rel_path' => \trim($relative_dir . '/' . $file_name, '/'),
			'dir' => \trailingslashit($basedir) . $relative_dir,
			'url_base' => \trailingslashit($baseurl) . $relative_dir,
			'file_name' => $file_name,
			'abs_path' => \trailingslashit($basedir) . $relative_dir . '/' . $file_name,
			'url' => \trailingslashit($baseurl) . $relative_dir . '/' . \rawurlencode($file_name),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_normalize_rel_path')) {
	function cmx_carent_vertrag_normalize_rel_path(string $path): string {
		return \ltrim(\str_replace('\\', '/', $path), '/');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_legacy_pdf_dir_prefix')) {
	function cmx_carent_vertrag_legacy_pdf_dir_prefix(int $post_id): string {
		return 'misbuero/carent/vertraege/' . $post_id . '/';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_archive_pdf_dir_prefix')) {
	function cmx_carent_vertrag_archive_pdf_dir_prefix(): string {
		return \rtrim((string) (\defined(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_PDF_ARCHIVE_REL_DIR')
			? \constant(__NAMESPACE__ . '\\CMX_CARENT_VERTRAG_PDF_ARCHIVE_REL_DIR')
			: 'misbuero/carent/vertraege'), '/') . '/';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_previous_archive_pdf_dir_prefix')) {
	function cmx_carent_vertrag_previous_archive_pdf_dir_prefix(): string {
		return 'misbuero/archiv/carent/';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_is_managed_pdf_rel_path')) {
	function cmx_carent_vertrag_is_managed_pdf_rel_path(string $rel_path, int $post_id = 0): bool {
		$rel_path = cmx_carent_vertrag_normalize_rel_path($rel_path);
		if ($rel_path === '') {
			return false;
		}

		if (\str_starts_with($rel_path, cmx_carent_vertrag_archive_pdf_dir_prefix())) {
			return true;
		}
		if (\str_starts_with($rel_path, cmx_carent_vertrag_previous_archive_pdf_dir_prefix())) {
			return true;
		}

		return $post_id > 0 && \str_starts_with($rel_path, cmx_carent_vertrag_legacy_pdf_dir_prefix($post_id));
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

		$uploads_root = \trailingslashit(\wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads')));
		$absolute_path = \wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads/' . $rel_path));
		if ($absolute_path === '' || !\str_starts_with($absolute_path, $uploads_root) || !\is_file($absolute_path)) {
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
		$self = (array) ($data['self'] ?? []);
		$self_id = isset($self['id']) ? (int) $self['id'] : 0;
		$logo_src = \trim((string) ($self['logo_src'] ?? ''));
		$self_title = \trim((string) (($self['branding'] ?? '') ?: ($self['title'] ?? '')));
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
		if ($self_address === '' && $self_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_address_string')) {
			$self_address = \trim((string) cmx_telefonbuch_address_string($self_id));
		}
		$self_country_key = \defined(__NAMESPACE__ . '\\CMX_RECHNUNG_META_LAND')
			? (string) \constant(__NAMESPACE__ . '\\CMX_RECHNUNG_META_LAND')
			: '_cmx_rechnung_land';
		$self_country = $self_id > 0 ? \trim((string) \get_post_meta($self_id, $self_country_key, true)) : '';
		$address_lines = \array_values(\array_filter(\array_map(
			static fn(string $value): string => \trim($value),
			\preg_split('/\s*,\s*/u', $self_address) ?: []
		), static function (string $value) use ($self_country): bool {
			if ($value === '') {
				return false;
			}
			if ($self_country !== '' && \strcasecmp($value, $self_country) === 0) {
				return false;
			}
			return true;
		}));
		\ob_start();
		?>
		<!doctype html>
		<html lang="de">
		<head>
			<meta charset="utf-8">
			<style>
				@page{margin:10mm 8mm 9mm}
				*{box-sizing:border-box}
				body{margin:0;background:#fff}
				.page{width:100%}
				.header-table{width:100%;border-collapse:collapse;table-layout:fixed}
				.header-address{width:34%;vertical-align:top;text-align:left;padding:2px 18px 0 0}
				.header-logo{width:32%;vertical-align:top;text-align:center;padding:0 12px}
				.header-contact{width:34%;vertical-align:top;text-align:right;padding-top:2px}
				.brand-logo{display:block;max-width:220px;max-height:90px;width:auto;height:auto;margin:0 auto}
				.brand-contract-label{margin-top:6px;font-size:11px;line-height:1;color:#111;text-align:center}
				.header-company{font-size:13px;font-weight:700;line-height:1.15;color:#111}
				.header-line{font-size:12px;line-height:1.1;color:#222}
				.header-contact-line{font-size:12px;line-height:1.1;color:#222}
			</style>
		</head>
		<body>
			<div class="page">
				<table class="header-table" role="presentation" cellpadding="0" cellspacing="0" border="0">
					<tr>
						<td class="header-address">
							<?php if ($self_title !== '') : ?>
								<div class="header-company"><?php echo \esc_html($self_title); ?></div>
							<?php endif; ?>
							<?php if ($self_subtitle !== '') : ?>
								<div class="header-line"><?php echo \esc_html($self_subtitle); ?></div>
							<?php endif; ?>
							<?php foreach ($address_lines as $address_line) : ?>
								<div class="header-line"><?php echo \esc_html($address_line); ?></div>
							<?php endforeach; ?>
						</td>
						<td class="header-logo">
							<?php if ($logo_src !== '') : ?>
								<img src="<?php echo \esc_attr($logo_src); ?>" alt="Logo" class="brand-logo">
							<?php endif; ?>
							<div class="brand-contract-label">M I E T V E R T R A G</div>
						</td>
						<td class="header-contact">
							<?php if ($self_email !== '') : ?>
								<div class="header-contact-line"><?php echo \esc_html($self_email); ?></div>
							<?php endif; ?>
							<?php if ($self_phone !== '') : ?>
								<div class="header-contact-line"><?php echo \esc_html($self_phone); ?></div>
							<?php endif; ?>
							<?php if ($self_website !== '') : ?>
								<div class="header-contact-line"><?php echo \esc_html($self_website); ?></div>
							<?php endif; ?>
						</td>
					</tr>
				</table>
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

		$storage = cmx_carent_vertrag_pdf_storage($post_id);
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

		$pdf_binary = $dompdf->output();
		if (!\is_string($pdf_binary) || $pdf_binary === '') {
			return new \WP_Error('pdf_render_failed', 'PDF konnte nicht gerendert werden.');
		}

		$written = \file_put_contents((string) $storage['abs_path'], $pdf_binary);
		if ($written === false) {
			return new \WP_Error('pdf_write_failed', 'PDF konnte nicht gespeichert werden.');
		}

		$previous_rel_path = cmx_carent_vertrag_normalize_rel_path((string) \get_post_meta($post_id, CMX_CARENT_VERTRAG_PDF_REL_META, true));
		$generated_at = \current_time('mysql');

		$dokument_id = cmx_carent_vertrag_sync_dokument($post_id, $storage);
		if (\is_wp_error($dokument_id)) {
			return $dokument_id;
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
