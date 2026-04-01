<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/** Verbindliche Taxonomien (unverändert) */
const CMX_TAX_PHONE_LABELS = 'kontakte_telefone';
const CMX_TAX_MAIL_LABELS  = 'kontakte_emails';

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_default_label_terms')) {
	function cmx_kommunikation_default_label_terms(): array {
		return [
			CMX_TAX_PHONE_LABELS => ['Geschäft', 'Privat', 'Mobil', 'Homeoffice', 'Support', 'Durchwahl', 'FaceTime', 'WhatsApp', 'SMS'],
			CMX_TAX_MAIL_LABELS  => ['Geschäft', 'Privat', 'Support', 'Sales', 'Direkt'],
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_ensure_default_label_terms')) {
	function cmx_kommunikation_ensure_default_label_terms(): void {
		foreach (cmx_kommunikation_default_label_terms() as $taxonomy => $terms) {
			if (!\taxonomy_exists($taxonomy)) {
				continue;
			}
			foreach ($terms as $term_name) {
				$term_name = \trim((string) $term_name);
				if ($term_name === '') {
					continue;
				}
				$slug = \sanitize_title($term_name);
				$existing = \get_term_by('name', $term_name, $taxonomy);
				if (!$existing || \is_wp_error($existing)) {
					$existing = $slug !== '' ? \get_term_by('slug', $slug, $taxonomy) : false;
				}
				if ($existing && !\is_wp_error($existing)) {
					continue;
				}
				\wp_insert_term($term_name, $taxonomy, $slug !== '' ? ['slug' => $slug] : []);
			}
		}
	}
}

/** Hilfsfunktionen (unverändert) */
function cmx_get_terms_normalized(string $taxonomy): array {
	$terms = \get_terms([
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
		'fields'     => 'all',
	]);
	if (\is_wp_error($terms) || empty($terms)) return [];
	$out = [];
	foreach ($terms as $t) {
		if (\is_object($t) && isset($t->slug, $t->name)) {
			$out[] = ['slug' => (string)$t->slug, 'name' => (string)$t->name];
		} elseif (\is_array($t)) {
			$slug = isset($t['slug']) ? (string)$t['slug'] : '';
			$name = isset($t['name']) ? (string)$t['name'] : $slug;
			if ($slug !== '' || $name !== '') $out[] = ['slug' => $slug, 'name' => $name];
		}
	}
	return $out;
}
function cmx_term_slug_exists(string $taxonomy, string $slug): bool {
	if (!$taxonomy || !$slug) return false;
	$t = \get_term_by('slug', $slug, $taxonomy);
	return ($t && !\is_wp_error($t));
}
function cmx_label_dropdown(array $terms, string $name, array $meta, string $taxonomy): string {
	$current = isset($meta[$name]) ? (string)$meta[$name] : '';
	$html  = '<select name="cmx_kommunikation[' . \esc_attr($name) . ']" data-taxonomy="'.\esc_attr($taxonomy).'">';
	$html .= '<option value="">auswählen</option>';
	foreach ($terms as $t) {
		$slug = (string)($t['slug'] ?? '');
		$txt  = (string)($t['name'] ?? $slug);
		$html .= '<option value="' . \esc_attr($slug) . '"' . \selected($current, $slug, false) . '>' . \esc_html($txt) . '</option>';
	}
	$html .= '</select>';
	return $html;
}

function cmx_kommunikation_taxonomy_label_html(string $taxonomy, string $label): string {
	if ($taxonomy === '' || !\taxonomy_exists($taxonomy)) {
		return \esc_html($label);
	}

	$url = \admin_url('edit-tags.php?taxonomy=' . \rawurlencode($taxonomy) . '&post_type=kontakte');

	return '<a href="' . \esc_url($url) . '" target="_blank" rel="noopener noreferrer" style="color:inherit;text-decoration:none;font:inherit;font-size:inherit;font-weight:inherit;line-height:inherit;">' . \esc_html($label) . '</a>';
}

/** Metabox registrieren (unverändert) */
\add_action('add_meta_boxes', function () {
	if (!\post_type_exists('kontakte')) return;
	\add_meta_box(
		'cmx_kommunikation_box',
		'Kommunikation',
		__NAMESPACE__ . '\\cmx_kommunikation_box_html',
		'kontakte',
		'normal',
		'default'
	);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_sanitize_birthdate')) {
	function cmx_kommunikation_sanitize_birthdate(mixed $value): string {
		$value = \is_scalar($value) ? \trim((string) $value) : '';
		if ($value === '') {
			return '';
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_sanitize_date_ymd')) {
			return (string) cmx_sanitize_date_ymd($value);
		}
		$dt = \DateTime::createFromFormat('Y-m-d', $value);
		return ($dt && $dt->format('Y-m-d') === $value) ? $value : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_phone_pattern')) {
	function cmx_kommunikation_phone_pattern(): string {
		return '(?:\+[1-9][\d\s().\/-]{7,20}|00[1-9][\d\s().\/-]{7,20}|41[\d\s().\/-]{9,20}|0[\d\s().\/-]{9,20}|[\d\s().\/-]{9,20})';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_phone_title')) {
	function cmx_kommunikation_phone_title(): string {
		return 'Wird beim Verlassen intern auf E.164 normalisiert und im Feld als Schweizer Nummer angezeigt, z. B. +41 79 123 45 67. Fehlt die Vorwahl, wird automatisch +41 verwendet.';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_phone_placeholder')) {
	function cmx_kommunikation_phone_placeholder(): string {
		return '+41 79 123 45 67';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_normalize_phone')) {
	function cmx_kommunikation_normalize_phone(mixed $value): string {
		$raw = \is_scalar($value) ? \trim((string) $value) : '';
		if ($raw === '') {
			return '';
		}

		$compact = (string) \preg_replace('/[^\d+]+/', '', $raw);
		if (\strpos($compact, '00') === 0) {
			$compact = '+' . \substr($compact, 2);
		}
		if (\preg_match('/^\+410\d{9}$/', $compact)) {
			$compact = '+41' . \substr($compact, 4);
		}
		if (\preg_match('/^410\d{9}$/', $compact)) {
			$compact = '+41' . \substr($compact, 3);
		}

		if (\preg_match('/^\+([1-9]\d{7,14})$/', $compact, $m)) {
			return '+' . $m[1];
		}
		if (\preg_match('/^41\d{9}$/', $compact)) {
			return '+' . $compact;
		}
		if (\preg_match('/^0\d{9}$/', $compact)) {
			return '+41' . \substr($compact, 1);
		}
		if (\preg_match('/^\d{9}$/', $compact)) {
			return '+41' . $compact;
		}

		return \sanitize_text_field($raw);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_format_phone_display')) {
	function cmx_kommunikation_split_e164_phone(string $normalized): array {
		$compact = (string) \preg_replace('/[^\d+]+/', '', $normalized);
		if (!\preg_match('/^\+(\d{8,15})$/', $compact, $matches)) {
			return ['', ''];
		}

		$digits = (string) ($matches[1] ?? '');
		$known_codes = ['423', '372', '41', '49', '43', '44', '39', '33', '1'];
		foreach ($known_codes as $code) {
			if (\str_starts_with($digits, $code) && \strlen($digits) > \strlen($code)) {
				return [$code, \substr($digits, \strlen($code))];
			}
		}

		$fallback_len = \strlen($digits) > 11 ? 3 : (\strlen($digits) > 10 ? 2 : 1);
		return [\substr($digits, 0, $fallback_len), \substr($digits, $fallback_len)];
	}

	function cmx_kommunikation_format_phone_display(mixed $value): string {
		$normalized = cmx_kommunikation_normalize_phone($value);
		if ($normalized === '') {
			return '';
		}

		[$country, $national] = cmx_kommunikation_split_e164_phone($normalized);
		if ($country === '' || $national === '') {
			return $normalized;
		}

		if ($country === '41') {
			if (\preg_match('/^(800|840|842|844|848|860|868|900|901|906)(\d{3})(\d{3})$/', $national, $service)) {
				return '+41 ' . $service[1] . ' ' . $service[2] . ' ' . $service[3];
			}

			if (\preg_match('/^(\d{2})(\d{3})(\d{2})(\d{2})$/', $national, $standard)) {
				return '+41 ' . $standard[1] . ' ' . $standard[2] . ' ' . $standard[3] . ' ' . $standard[4];
			}
		}

		if (\preg_match('/^(\d{2})(\d{3})(\d{2})(\d{2})$/', $national, $standard)) {
			return '+' . $country . ' ' . $standard[1] . ' ' . $standard[2] . ' ' . $standard[3] . ' ' . $standard[4];
		}
		if (\preg_match('/^(\d{3})(\d{3})(\d{2})(\d{2})$/', $national, $ten)) {
			return '+' . $country . ' ' . $ten[1] . ' ' . $ten[2] . ' ' . $ten[3] . ' ' . $ten[4];
		}
		if (\preg_match('/^(\d{3})(\d{2})(\d{2})$/', $national, $seven)) {
			return '+' . $country . ' ' . $seven[1] . ' ' . $seven[2] . ' ' . $seven[3];
		}
		if (\preg_match('/^(\d{2})(\d{2})(\d{2})(\d{2})$/', $national, $eight)) {
			return '+' . $country . ' ' . $eight[1] . ' ' . $eight[2] . ' ' . $eight[3] . ' ' . $eight[4];
		}

		return $normalized;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_normalize_label_slug')) {
	function cmx_kommunikation_normalize_label_slug(mixed $value, string $taxonomy): string {
		$slug = \sanitize_title((string) $value);
		if ($slug === '') {
			return '';
		}
		return cmx_term_slug_exists($taxonomy, $slug) ? $slug : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_resolve_label_term_slug')) {
	function cmx_kommunikation_resolve_label_term_slug(mixed $value, string $taxonomy): string {
		$raw = \trim((string) $value);
		if ($raw === '') {
			return '';
		}

		$slug = cmx_kommunikation_normalize_label_slug($raw, $taxonomy);
		if ($slug !== '') {
			return $slug;
		}

		$term = \get_term_by('name', $raw, $taxonomy);
		if ($term && !\is_wp_error($term) && isset($term->slug)) {
			return (string) $term->slug;
		}

		return '';
	}
}

	if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_row_fields')) {
		function cmx_kommunikation_row_fields(): array {
			return ['vorname', 'nachname', 'telefon_label', 'telefon', 'email_label', 'email', 'geburtsdatum', 'anrede', 'duzis'];
		}
	}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_count_meta_key')) {
	function cmx_kommunikation_count_meta_key(): string {
		return '_cmx_kommunikation_count';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_contact_meta_key')) {
	function cmx_kommunikation_contact_meta_key(int $slot, string $field): string {
		return '_cmx_kommunikation_' . \max(1, $slot) . '_' . \sanitize_key($field);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_label_meta_key')) {
	function cmx_kommunikation_label_meta_key(string $kind, int $slot): string {
		return ($kind === 'email' ? '_cmx_email_label_' : '_cmx_telefon_label_') . \max(1, $slot);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_normalize_contact_row')) {
	function cmx_kommunikation_normalize_contact_row(array $row): array {
		$email = \sanitize_email((string) ($row['email'] ?? ''));
		if ($email === '' && isset($row['email'])) {
			$email = \trim((string) $row['email']);
		}

			return [
				'vorname'      => \sanitize_text_field((string) ($row['vorname'] ?? '')),
				'nachname'     => \sanitize_text_field((string) ($row['nachname'] ?? '')),
				'telefon_label'=> cmx_kommunikation_normalize_label_slug($row['telefon_label'] ?? '', CMX_TAX_PHONE_LABELS),
				'telefon'      => cmx_kommunikation_normalize_phone($row['telefon'] ?? ''),
				'email_label'  => cmx_kommunikation_normalize_label_slug($row['email_label'] ?? '', CMX_TAX_MAIL_LABELS),
				'email'        => $email,
				'geburtsdatum' => cmx_kommunikation_sanitize_birthdate($row['geburtsdatum'] ?? ''),
				'anrede'       => \sanitize_text_field((string) ($row['anrede'] ?? '')),
				'duzis'        => !empty($row['duzis']) ? '1' : '0',
			];
		}
	}

	if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_contact_row_is_empty')) {
		function cmx_kommunikation_contact_row_is_empty(array $row): bool {
			foreach (['vorname', 'nachname', 'telefon', 'email', 'geburtsdatum', 'anrede'] as $key) {
				if (\trim((string) ($row[$key] ?? '')) !== '') {
					return false;
				}
			}
		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_flat_saved_slots')) {
	function cmx_kommunikation_flat_saved_slots(int $post_id): array {
		$slots = [];
		$count_raw = \get_post_meta($post_id, cmx_kommunikation_count_meta_key(), true);
		if ($count_raw !== '' && $count_raw !== null) {
			$count = \max(0, (int) $count_raw);
			for ($slot = 1; $slot <= $count; $slot++) {
				$slots[$slot] = $slot;
			}
		}

			$all_meta = \get_post_meta($post_id);
			if (\is_array($all_meta)) {
				foreach (\array_keys($all_meta) as $meta_key) {
					if (\preg_match('/^_cmx_kommunikation_(\d+)_(vorname|nachname|telefon_label|telefon|email_label|email|geburtsdatum|anrede|duzis)$/', (string) $meta_key, $matches)) {
						$slot = (int) ($matches[1] ?? 0);
						if ($slot > 0) {
							$slots[$slot] = $slot;
					}
				}
			}
		}

		\ksort($slots, \SORT_NUMERIC);
		return \array_values($slots);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_has_flat_storage')) {
	function cmx_kommunikation_has_flat_storage(int $post_id): bool {
		return \metadata_exists('post', $post_id, cmx_kommunikation_count_meta_key()) || cmx_kommunikation_flat_saved_slots($post_id) !== [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_read_flat_contacts')) {
	function cmx_kommunikation_read_flat_contacts(int $post_id): array {
		$contacts = [];
		foreach (cmx_kommunikation_flat_saved_slots($post_id) as $slot) {
			$row = [];
			foreach (cmx_kommunikation_row_fields() as $field) {
				$row[$field] = (string) \get_post_meta($post_id, cmx_kommunikation_contact_meta_key((int) $slot, $field), true);
			}
			$normalized = cmx_kommunikation_normalize_contact_row($row);
			if (!cmx_kommunikation_contact_row_is_empty($normalized)) {
				$contacts[] = $normalized;
			}
		}
		return $contacts;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_legacy_slot_value')) {
	function cmx_kommunikation_legacy_slot_value(int $post_id, array $bundle, string $kind, int $slot): string {
		$meta_key = $kind === 'email' ? "_cmx_email_{$slot}" : "_cmx_telefon_{$slot}";
		$value = \get_post_meta($post_id, $meta_key, true);
		if ($value === '' || $value === null) {
			$value = $bundle[$kind][$slot]['value'] ?? '';
		}
		return \trim((string) $value);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_legacy_slot_label')) {
	function cmx_kommunikation_legacy_slot_label(int $post_id, array $bundle, string $kind, int $slot): string {
		$meta_key = cmx_kommunikation_label_meta_key($kind, $slot);
		$value = \get_post_meta($post_id, $meta_key, true);
		if ($value === '' || $value === null) {
			$value = $bundle[$kind][$slot]['label'] ?? '';
		}
		return \trim((string) $value);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_persist_contacts')) {
	function cmx_kommunikation_persist_contacts(int $post_id, array $contacts): array {
		$normalized_contacts = [];
		foreach ($contacts as $row) {
			if (!\is_array($row)) {
				continue;
			}
			$normalized = cmx_kommunikation_normalize_contact_row($row);
			if (!cmx_kommunikation_contact_row_is_empty($normalized)) {
				$normalized_contacts[] = $normalized;
			}
		}

		$existing_slots = cmx_kommunikation_flat_saved_slots($post_id);
		$max_existing_slot = $existing_slots !== [] ? (int) \max($existing_slots) : 0;
		$max_slot = \max($max_existing_slot, \count($normalized_contacts));

		\update_post_meta($post_id, cmx_kommunikation_count_meta_key(), (string) \count($normalized_contacts));

		foreach ($normalized_contacts as $index => $row) {
			$slot = $index + 1;
			foreach (cmx_kommunikation_row_fields() as $field) {
				$meta_key = cmx_kommunikation_contact_meta_key($slot, $field);
				$value = (string) ($row[$field] ?? '');
				if ($field === 'duzis') {
					\update_post_meta($post_id, $meta_key, $value === '1' ? '1' : '0');
					continue;
				}
				if ($value === '') {
					\delete_post_meta($post_id, $meta_key);
				} else {
					\update_post_meta($post_id, $meta_key, $value);
				}
			}
		}

		for ($slot = \count($normalized_contacts) + 1; $slot <= $max_slot; $slot++) {
			foreach (cmx_kommunikation_row_fields() as $field) {
				\delete_post_meta($post_id, cmx_kommunikation_contact_meta_key($slot, $field));
			}
		}

		for ($slot = 1; $slot <= 3; $slot++) {
			$row = $normalized_contacts[$slot - 1] ?? null;
			$telefon = (string) ($row['telefon'] ?? '');
			$email = (string) ($row['email'] ?? '');
			$telefon_label = (string) ($row['telefon_label'] ?? '');
			$email_label = (string) ($row['email_label'] ?? '');

			if ($telefon === '') {
				\delete_post_meta($post_id, "_cmx_telefon_{$slot}");
			} else {
				\update_post_meta($post_id, "_cmx_telefon_{$slot}", $telefon);
			}
			if ($email === '') {
				\delete_post_meta($post_id, "_cmx_email_{$slot}");
			} else {
				\update_post_meta($post_id, "_cmx_email_{$slot}", $email);
			}
			if ($telefon_label === '') {
				\delete_post_meta($post_id, cmx_kommunikation_label_meta_key('telefon', $slot));
			} else {
				\update_post_meta($post_id, cmx_kommunikation_label_meta_key('telefon', $slot), $telefon_label);
			}
			if ($email_label === '') {
				\delete_post_meta($post_id, cmx_kommunikation_label_meta_key('email', $slot));
			} else {
				\update_post_meta($post_id, cmx_kommunikation_label_meta_key('email', $slot), $email_label);
			}
		}

		$first = $normalized_contacts[0] ?? null;
		if (\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_VORNAME')) {
			if ($first && (string) ($first['vorname'] ?? '') !== '') {
				\update_post_meta($post_id, CMX_KONTAKTE_META_VORNAME, (string) $first['vorname']);
			} else {
				\delete_post_meta($post_id, CMX_KONTAKTE_META_VORNAME);
			}
		}
			if (\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_NACHNAME')) {
				if ($first && (string) ($first['nachname'] ?? '') !== '') {
					\update_post_meta($post_id, CMX_KONTAKTE_META_NACHNAME, (string) $first['nachname']);
				} else {
					\delete_post_meta($post_id, CMX_KONTAKTE_META_NACHNAME);
				}
			}
			if (\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_ANREDE')) {
				$salutation = $first ? (string) ($first['anrede'] ?? '') : '';
				if ($salutation !== '') {
					\update_post_meta($post_id, CMX_KONTAKTE_META_ANREDE, $salutation);
				} else {
					\delete_post_meta($post_id, CMX_KONTAKTE_META_ANREDE);
				}
			}
			if (\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_GEBURTSDATUM')) {
				$birthdate = $first ? (string) ($first['geburtsdatum'] ?? '') : '';
				if ($birthdate !== '') {
				\update_post_meta($post_id, CMX_KONTAKTE_META_GEBURTSDATUM, $birthdate);
			} else {
				\delete_post_meta($post_id, CMX_KONTAKTE_META_GEBURTSDATUM);
			}
		}

		\delete_post_meta($post_id, '_cmx_kommunikation');

		return $normalized_contacts;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_has_legacy_data')) {
	function cmx_kommunikation_has_legacy_data(int $post_id): bool {
		$bundle = \get_post_meta($post_id, '_cmx_kommunikation', true);
		if (\is_array($bundle) && $bundle !== []) {
			return true;
		}

		$keys = [
			'_cmx_telefon_1', '_cmx_telefon_2', '_cmx_telefon_3',
			'_cmx_email_1', '_cmx_email_2', '_cmx_email_3',
			cmx_kommunikation_label_meta_key('telefon', 1),
			cmx_kommunikation_label_meta_key('telefon', 2),
			cmx_kommunikation_label_meta_key('telefon', 3),
			cmx_kommunikation_label_meta_key('email', 1),
			cmx_kommunikation_label_meta_key('email', 2),
			cmx_kommunikation_label_meta_key('email', 3),
		];
		if (\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_VORNAME')) {
			$keys[] = CMX_KONTAKTE_META_VORNAME;
		}
			if (\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_NACHNAME')) {
				$keys[] = CMX_KONTAKTE_META_NACHNAME;
			}
			if (\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_ANREDE')) {
				$keys[] = CMX_KONTAKTE_META_ANREDE;
			}
			if (\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_GEBURTSDATUM')) {
				$keys[] = CMX_KONTAKTE_META_GEBURTSDATUM;
			}

		foreach ($keys as $key) {
			$value = \get_post_meta($post_id, (string) $key, true);
			if (\is_array($value)) {
				if ($value !== []) {
					return true;
				}
				continue;
			}
			if (\trim((string) $value) !== '') {
				return true;
			}
		}

		return false;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_migration_query_args')) {
	function cmx_kommunikation_migration_query_args(int $posts_per_page = 100): array {
		$meta_query = [
			'relation' => 'AND',
			[
				'key' => cmx_kommunikation_count_meta_key(),
				'compare' => 'NOT EXISTS',
			],
			[
				'relation' => 'OR',
				['key' => '_cmx_kommunikation', 'compare' => 'EXISTS'],
				['key' => '_cmx_telefon_1', 'compare' => 'EXISTS'],
				['key' => '_cmx_telefon_2', 'compare' => 'EXISTS'],
				['key' => '_cmx_telefon_3', 'compare' => 'EXISTS'],
				['key' => '_cmx_email_1', 'compare' => 'EXISTS'],
				['key' => '_cmx_email_2', 'compare' => 'EXISTS'],
				['key' => '_cmx_email_3', 'compare' => 'EXISTS'],
				['key' => cmx_kommunikation_label_meta_key('telefon', 1), 'compare' => 'EXISTS'],
				['key' => cmx_kommunikation_label_meta_key('telefon', 2), 'compare' => 'EXISTS'],
				['key' => cmx_kommunikation_label_meta_key('telefon', 3), 'compare' => 'EXISTS'],
				['key' => cmx_kommunikation_label_meta_key('email', 1), 'compare' => 'EXISTS'],
				['key' => cmx_kommunikation_label_meta_key('email', 2), 'compare' => 'EXISTS'],
				['key' => cmx_kommunikation_label_meta_key('email', 3), 'compare' => 'EXISTS'],
			],
		];

		if (\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_VORNAME')) {
			$meta_query[1][] = ['key' => CMX_KONTAKTE_META_VORNAME, 'compare' => 'EXISTS'];
		}
			if (\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_NACHNAME')) {
				$meta_query[1][] = ['key' => CMX_KONTAKTE_META_NACHNAME, 'compare' => 'EXISTS'];
			}
			if (\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_ANREDE')) {
				$meta_query[1][] = ['key' => CMX_KONTAKTE_META_ANREDE, 'compare' => 'EXISTS'];
			}
			if (\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_GEBURTSDATUM')) {
				$meta_query[1][] = ['key' => CMX_KONTAKTE_META_GEBURTSDATUM, 'compare' => 'EXISTS'];
			}

		return [
			'post_type' => 'kontakte',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => \max(1, $posts_per_page),
			'fields' => 'ids',
			'orderby' => 'ID',
			'order' => 'ASC',
			'no_found_rows' => true,
			'meta_query' => $meta_query,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_migrate_post')) {
	function cmx_kommunikation_migrate_post(int $post_id): bool {
		if ($post_id <= 0 || !cmx_kommunikation_has_legacy_data($post_id)) {
			return false;
		}
		$contacts = cmx_kommunikation_read_contacts($post_id);
		cmx_kommunikation_persist_contacts($post_id, $contacts);
		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_migrate_batch')) {
	function cmx_kommunikation_migrate_batch(int $limit = 100): int {
		$migrated = 0;
		$query = new \WP_Query(cmx_kommunikation_migration_query_args($limit));
		foreach ((array) $query->posts as $post_id) {
			if (cmx_kommunikation_migrate_post((int) $post_id)) {
				$migrated++;
			}
		}
		return $migrated;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_has_pending_migrations')) {
	function cmx_kommunikation_has_pending_migrations(): bool {
		$query = new \WP_Query(cmx_kommunikation_migration_query_args(1));
		return !empty($query->posts);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_read_contacts')) {
	function cmx_kommunikation_read_contacts(int $post_id): array {
		$flat_contacts = cmx_kommunikation_read_flat_contacts($post_id);
		if ($flat_contacts !== [] || cmx_kommunikation_has_flat_storage($post_id)) {
			return $flat_contacts !== [] ? $flat_contacts : [cmx_kommunikation_normalize_contact_row([])];
		}

		$bundle = \get_post_meta($post_id, '_cmx_kommunikation', true);
		if (!\is_array($bundle)) {
			$bundle = [];
		}

		$contacts = [];
		if (\is_array($bundle['kontakte'] ?? null)) {
			foreach ((array) $bundle['kontakte'] as $row) {
				if (!\is_array($row)) {
					continue;
				}
				$normalized = cmx_kommunikation_normalize_contact_row($row);
				if (!cmx_kommunikation_contact_row_is_empty($normalized)) {
					$contacts[] = $normalized;
				}
			}
		}
		if (!empty($contacts)) {
			return $contacts;
		}

		$legacy_rows = [];
		for ($slot = 1; $slot <= 3; $slot++) {
				$row = [
					'vorname'      => $slot === 1 && \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_VORNAME')
						? (string) \get_post_meta($post_id, CMX_KONTAKTE_META_VORNAME, true)
						: '',
					'nachname'     => $slot === 1 && \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_NACHNAME')
					? (string) \get_post_meta($post_id, CMX_KONTAKTE_META_NACHNAME, true)
					: '',
				'telefon_label'=> cmx_kommunikation_legacy_slot_label($post_id, $bundle, 'telefon', $slot),
				'telefon'      => cmx_kommunikation_legacy_slot_value($post_id, $bundle, 'telefon', $slot),
					'email_label'  => cmx_kommunikation_legacy_slot_label($post_id, $bundle, 'email', $slot),
					'email'        => cmx_kommunikation_legacy_slot_value($post_id, $bundle, 'email', $slot),
					'geburtsdatum' => $slot === 1 && \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_GEBURTSDATUM')
						? (string) \get_post_meta($post_id, CMX_KONTAKTE_META_GEBURTSDATUM, true)
						: '',
					'anrede'       => $slot === 1 && \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_ANREDE')
						? (string) \get_post_meta($post_id, CMX_KONTAKTE_META_ANREDE, true)
						: '',
					'duzis'        => '0',
				];
				$row = cmx_kommunikation_normalize_contact_row($row);
			if (!cmx_kommunikation_contact_row_is_empty($row)) {
				$legacy_rows[] = $row;
			}
		}

	return !empty($legacy_rows) ? $legacy_rows : [cmx_kommunikation_normalize_contact_row([])];
	}
}

\add_action('admin_init', function (): void {
	if (!\post_type_exists('kontakte') || !\current_user_can('edit_posts')) {
		return;
	}

	cmx_kommunikation_ensure_default_label_terms();
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_render_contact_row')) {
	function cmx_kommunikation_render_contact_row(array $row, int|string $index, array $phone_terms, array $mail_terms): void {
		$index_attr = (string) $index;
		$id_suffix = \preg_replace('/[^A-Za-z0-9_-]/', '_', $index_attr) ?: '0';
		$field_base = 'cmx_kommunikation[kontakte][' . $index_attr . ']';
		$email_post_type = \defined(__NAMESPACE__ . '\\CMX_EMAILS_CPT') ? (string) \constant(__NAMESPACE__ . '\\CMX_EMAILS_CPT') : 'emails';
		$emails_enabled = \post_type_exists($email_post_type);
		if (
			$emails_enabled
			&& \function_exists(__NAMESPACE__ . '\\cmx_system_should_hide_post_type_for_cloudmeister')
			&& cmx_system_should_hide_post_type_for_cloudmeister($email_post_type)
		) {
			$emails_enabled = false;
		}
		$internal_email_url = $emails_enabled
			? \admin_url('post-new.php?post_type=' . \rawurlencode($email_post_type))
			: '';
		$phone_pattern = cmx_kommunikation_phone_pattern();
		$phone_title = cmx_kommunikation_phone_title();
		$phone_placeholder = cmx_kommunikation_phone_placeholder();
		?>
		<div class="cmx-kommu-contact-row" data-row-index="<?php echo \esc_attr($index_attr); ?>">
			<div class="cmx-kommu-field cmx-kommu-field-handle">
				<!-- <span class="cmx-kommu-field-title">Reihenfolge</span> -->
				<span class="cmx-kommu-drag" draggable="true" title="Kontakt verschieben" aria-label="Kontakt verschieben">
					<span class="dashicons dashicons-menu" aria-hidden="true"></span>
				</span>
			</div>
			<div class="cmx-kommu-field">
				<label for="<?php echo \esc_attr('cmx_komm_vorname_' . $id_suffix); ?>">Vorname</label>
				<input id="<?php echo \esc_attr('cmx_komm_vorname_' . $id_suffix); ?>" type="text" name="<?php echo \esc_attr($field_base . '[vorname]'); ?>" value="<?php echo \esc_attr((string) ($row['vorname'] ?? '')); ?>" data-cmx-tab-role="vorname">
			</div>
			<div class="cmx-kommu-field">
				<label for="<?php echo \esc_attr('cmx_komm_nachname_' . $id_suffix); ?>">Nachname</label>
				<input id="<?php echo \esc_attr('cmx_komm_nachname_' . $id_suffix); ?>" type="text" name="<?php echo \esc_attr($field_base . '[nachname]'); ?>" value="<?php echo \esc_attr((string) ($row['nachname'] ?? '')); ?>" data-cmx-tab-role="nachname">
			</div>
			<div class="cmx-kommu-field">
				<label for="<?php echo \esc_attr('cmx_komm_telefon_label_' . $id_suffix); ?>"><?php echo cmx_kommunikation_taxonomy_label_html(CMX_TAX_PHONE_LABELS, 'Telefon Typ'); ?></label>
				<select id="<?php echo \esc_attr('cmx_komm_telefon_label_' . $id_suffix); ?>" name="<?php echo \esc_attr($field_base . '[telefon_label]'); ?>" data-cmx-tab-role="telefon_label">
					<option value="">auswählen</option>
					<?php foreach ($phone_terms as $term) {
						$slug = (string) ($term['slug'] ?? '');
						$name = (string) ($term['name'] ?? $slug);
						?>
						<option value="<?php echo \esc_attr($slug); ?>" <?php echo \selected((string) ($row['telefon_label'] ?? ''), $slug, false); ?>><?php echo \esc_html($name); ?></option>
					<?php } ?>
				</select>
			</div>
			<div class="cmx-kommu-field">
				<label for="<?php echo \esc_attr('cmx_komm_telefon_' . $id_suffix); ?>" class="cmx-kommu-phone-label">
					<button
						type="button"
						class="button-link cmx-kommu-phone-action"
						data-phone-target="<?php echo \esc_attr('cmx_komm_telefon_' . $id_suffix); ?>"
						tabindex="-1"
					>Telefon</button>
				</label>
					<input
						id="<?php echo \esc_attr('cmx_komm_telefon_' . $id_suffix); ?>"
						type="tel"
						name="<?php echo \esc_attr($field_base . '[telefon]'); ?>"
						value="<?php echo \esc_attr(cmx_kommunikation_format_phone_display((string) ($row['telefon'] ?? ''))); ?>"
						inputmode="tel"
						autocomplete="tel-national"
						placeholder="<?php echo \esc_attr($phone_placeholder); ?>"
					pattern="<?php echo \esc_attr($phone_pattern); ?>"
					title="<?php echo \esc_attr($phone_title); ?>"
					data-cmx-phone="1"
					data-cmx-tab-role="telefon"
				>
			</div>
			<div class="cmx-kommu-field">
				<label for="<?php echo \esc_attr('cmx_komm_email_label_' . $id_suffix); ?>"><?php echo cmx_kommunikation_taxonomy_label_html(CMX_TAX_MAIL_LABELS, 'E-Mail Typ'); ?></label>
				<select id="<?php echo \esc_attr('cmx_komm_email_label_' . $id_suffix); ?>" name="<?php echo \esc_attr($field_base . '[email_label]'); ?>" data-cmx-tab-role="email_label">
					<option value="">auswählen</option>
					<?php foreach ($mail_terms as $term) {
						$slug = (string) ($term['slug'] ?? '');
						$name = (string) ($term['name'] ?? $slug);
						?>
						<option value="<?php echo \esc_attr($slug); ?>" <?php echo \selected((string) ($row['email_label'] ?? ''), $slug, false); ?>><?php echo \esc_html($name); ?></option>
					<?php } ?>
				</select>
			</div>
			<div class="cmx-kommu-field">
				<label for="<?php echo \esc_attr('cmx_komm_email_' . $id_suffix); ?>" class="cmx-kommu-email-label">
					<?php if ($emails_enabled) : ?>
						<button
							type="button"
							class="button-link cmx-kommu-email-action"
							data-email-target="<?php echo \esc_attr('cmx_komm_email_' . $id_suffix); ?>"
							data-internal-url="<?php echo \esc_url($internal_email_url); ?>"
							tabindex="-1"
						>E-Mail</button>
					<?php else : ?>
						<span>E-Mail</span>
					<?php endif; ?>
				</label>
				<input id="<?php echo \esc_attr('cmx_komm_email_' . $id_suffix); ?>" type="email" name="<?php echo \esc_attr($field_base . '[email]'); ?>" value="<?php echo \esc_attr((string) ($row['email'] ?? '')); ?>" data-cmx-tab-role="email">
			</div>
				<div class="cmx-kommu-field cmx-kommu-field-date">
					<label for="<?php echo \esc_attr('cmx_komm_geburtsdatum_' . $id_suffix); ?>">Geburtsdatum</label>
					<input id="<?php echo \esc_attr('cmx_komm_geburtsdatum_' . $id_suffix); ?>" type="date" name="<?php echo \esc_attr($field_base . '[geburtsdatum]'); ?>" value="<?php echo \esc_attr((string) ($row['geburtsdatum'] ?? '')); ?>" data-cmx-tab-role="geburtsdatum">
				</div>
				<div class="cmx-kommu-field">
					<label for="<?php echo \esc_attr('cmx_komm_anrede_' . $id_suffix); ?>">Anrede</label>
					<input id="<?php echo \esc_attr('cmx_komm_anrede_' . $id_suffix); ?>" type="text" name="<?php echo \esc_attr($field_base . '[anrede]'); ?>" value="<?php echo \esc_attr((string) ($row['anrede'] ?? '')); ?>" data-cmx-tab-role="anrede">
				</div>
				<div class="cmx-kommu-field cmx-kommu-field-check">
					<span class="cmx-kommu-field-title">Duzis</span>
					<label class="cmx-kommu-toggle" for="<?php echo \esc_attr('cmx_komm_duzis_' . $id_suffix); ?>">
					<input id="<?php echo \esc_attr('cmx_komm_duzis_' . $id_suffix); ?>" type="checkbox" name="<?php echo \esc_attr($field_base . '[duzis]'); ?>" value="1" <?php echo \checked((string) ($row['duzis'] ?? '0'), '1', false); ?> data-cmx-tab-role="duzis">
					<span class="cmx-kommu-toggle-ui" aria-hidden="true"></span>
				</label>
			</div>
			<div class="cmx-kommu-field cmx-kommu-field-actions">
				<button type="button" class="button-link-delete cmx-kommu-remove" aria-label="Kontakt entfernen" title="Kontakt entfernen">
					<span class="dashicons dashicons-trash" aria-hidden="true"></span>
					<span class="screen-reader-text">Entfernen</span>
				</button>
			</div>
		</div>
		<?php
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_nextcloud_call_url')) {
	function cmx_kommunikation_read_nextcloud_setting(string $option_name, array $fallback_keys = []): string {
		$value = \trim((string) \get_option($option_name, ''));
		if ($value !== '') {
			return $value;
		}

		$settings = (array) \get_option(\defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN') ? CMX_SETTINGS_MAIN : 'cmx_einstellungen', []);
		$keys = \array_merge([$option_name], $fallback_keys);
		foreach ($keys as $key) {
			$fallback_value = \trim((string) ($settings[$key] ?? ''));
			if ($fallback_value !== '') {
				return $fallback_value;
			}
		}

		return '';
	}

	function cmx_kommunikation_nextcloud_call_url(): string {
		$base_url = cmx_kommunikation_read_nextcloud_setting('mis_buero_nextcloud_url', ['nextcloud_url']);
		$chat_room = cmx_kommunikation_read_nextcloud_setting('mis_buero_nextcloud_chat_room', ['nextcloud_chat_room', 'nextcloud_chat_room_id']);
		if ($base_url !== '' && !\preg_match('~^https?://~i', $base_url)) {
			$base_url = 'https://' . $base_url;
		}
		$base_url = \untrailingslashit($base_url);
		if ($base_url === '' || $chat_room === '') {
			return '';
		}

		return $base_url . '/index.php/call/' . \rawurlencode($chat_room);
	}
}

\add_action('wp_ajax_cmx_kommunikation_videochat_url', __NAMESPACE__ . '\\cmx_kommunikation_videochat_url_ajax');
function cmx_kommunikation_videochat_url_ajax(): void {
	if (!\current_user_can('edit_posts')) {
		\wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
	}

	\check_ajax_referer('cmx_kommunikation_videochat', 'nonce');

	$url = cmx_kommunikation_nextcloud_call_url();
	if ($url === '') {
		\wp_send_json_error([
			'message' => 'Nextcloud URL oder Chat Room ID ist nicht gespeichert in Einstellungen > System.',
		], 400);
	}

	\wp_send_json_success([
		'url' => $url,
	]);
}

function cmx_kommunikation_box_html($post): void {
	$rows = cmx_kommunikation_read_contacts((int) $post->ID);
	$phone_terms = \taxonomy_exists(CMX_TAX_PHONE_LABELS) ? cmx_get_terms_normalized(CMX_TAX_PHONE_LABELS) : [];
	$mail_terms = \taxonomy_exists(CMX_TAX_MAIL_LABELS) ? cmx_get_terms_normalized(CMX_TAX_MAIL_LABELS) : [];
	$nextcloud_call_url = cmx_kommunikation_nextcloud_call_url();
	$videochat_nonce = \wp_create_nonce('cmx_kommunikation_videochat');
	\wp_nonce_field('cmx_kommunikation_save', 'cmx_kommunikation_nonce');
	?>
		<style>
			#cmx_kommunikation_box .cmx-kommu-rows {
				display: flex;
				flex-direction: column;
				gap: 10px;
			}
			#cmx_kommunikation_box .cmx-kommu-contact-row {
				display: grid;
				grid-template-columns: 40px minmax(108px, 0.95fr) minmax(108px, 0.95fr) minmax(108px, 0.82fr) minmax(138px, 1fr) minmax(108px, 0.82fr) minmax(168px, 1.08fr) 136px minmax(108px, 0.85fr) 72px 34px;
				gap: 8px;
				padding: 10px;
				border: 1px solid #dcdcde;
			border-radius: 7px;
			background: #fff;
			align-items: end;
			transition: border-color 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
		}
		#cmx_kommunikation_box .cmx-kommu-contact-row:nth-child(even) {
			background: #fcfcfc;
		}
		#cmx_kommunikation_box .cmx-kommu-contact-row.is-dragging {
			opacity: 0.6;
			border-color: #d63638;
			box-shadow: 0 0 0 1px rgba(214, 54, 56, 0.18);
		}
		#cmx_kommunikation_box .cmx-kommu-field {
			margin: 0;
		}
		#cmx_kommunikation_box .cmx-kommu-field-handle {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			gap: 0;
			padding: 0;
		}
		#cmx_kommunikation_box .cmx-kommu-field-handle .cmx-kommu-field-title {
			display: none;
		}
		#cmx_kommunikation_box .cmx-kommu-field label {
			display: block;
			margin: 0 0 3px;
			padding-left: 1ch;
			font-weight: 600;
			font-size: 12px;
			line-height: 1.2;
		}
		#cmx_kommunikation_box .cmx-kommu-field-title {
			display: block;
			margin: 0 0 3px;
			padding-left: 1ch;
			font-weight: 600;
			font-size: 12px;
			line-height: 1.2;
		}
		#cmx_kommunikation_box .cmx-kommu-field input:not([type="checkbox"]),
		#cmx_kommunikation_box .cmx-kommu-field select {
			width: 100%;
			max-width: none;
			min-height: 40px;
			padding: 8px 12px;
			box-sizing: border-box;
			border: 1px solid #cfd8e3;
			border-radius: 8px;
			background: #fff;
			color: #2c3338;
			box-shadow: none;
			transition: border-color 0.2s ease, box-shadow 0.2s ease;
		}
		#cmx_kommunikation_box .cmx-kommu-field input[data-cmx-phone] {
			-webkit-appearance: none;
			appearance: none;
		}
		#cmx_kommunikation_box .cmx-kommu-field input[data-cmx-phone]:invalid {
			border-color: #cfd8e3;
			box-shadow: none;
		}
		#cmx_kommunikation_box .cmx-kommu-field input:not([type="checkbox"]):focus,
		#cmx_kommunikation_box .cmx-kommu-field select:focus {
			border-color: #2271b1;
			box-shadow: 0 0 0 1px #2271b1;
			outline: none;
		}
		#cmx_kommunikation_box .cmx-kommu-email-label,
		#cmx_kommunikation_box .cmx-kommu-phone-label {
			cursor: pointer;
		}
		#cmx_kommunikation_box .cmx-kommu-email-action,
		#cmx_kommunikation_box .cmx-kommu-phone-action {
			padding: 0;
			border: 0;
			background: transparent;
			color: inherit;
			font: inherit;
			line-height: inherit;
			text-decoration: none;
			cursor: pointer;
		}
		#cmx_kommunikation_box .cmx-kommu-email-action:hover,
		#cmx_kommunikation_box .cmx-kommu-email-action:focus,
		#cmx_kommunikation_box .cmx-kommu-phone-action:hover,
		#cmx_kommunikation_box .cmx-kommu-phone-action:focus {
			color: #d63638;
			text-decoration: underline;
			outline: none;
		}
		#cmx_kommunikation_box .cmx-kommu-email-menu,
		#cmx_kommunikation_box .cmx-kommu-phone-menu {
			position: fixed;
			z-index: 100000;
			display: none;
			min-width: 132px;
			padding: 6px;
			border: 1px solid #dcdcde;
			border-radius: 7px;
			background: #fff;
			box-shadow: 0 10px 28px rgba(15, 23, 42, 0.14);
		}
		#cmx_kommunikation_box .cmx-kommu-email-menu.is-open,
		#cmx_kommunikation_box .cmx-kommu-phone-menu.is-open {
			display: block;
		}
		#cmx_kommunikation_box .cmx-kommu-email-menu button,
		#cmx_kommunikation_box .cmx-kommu-phone-menu button {
			display: block;
			width: 100%;
			margin: 0;
			padding: 7px 9px;
			border: 0;
			border-radius: 5px;
			background: transparent;
			color: #1d2327;
			text-align: left;
			cursor: pointer;
		}
		#cmx_kommunikation_box .cmx-kommu-email-menu button:hover,
		#cmx_kommunikation_box .cmx-kommu-email-menu button:focus,
		#cmx_kommunikation_box .cmx-kommu-phone-menu button:hover,
		#cmx_kommunikation_box .cmx-kommu-phone-menu button:focus {
			background: #f6f7f7;
			outline: none;
		}
		#cmx_kommunikation_box .cmx-kommu-drag {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 28px;
			height: 28px;
			margin-top: -60px;
			border-radius: 5px;
			cursor: grab;
			color: #646970;
			background: #f6f7f7;
			box-shadow: inset 0 0 0 1px #dcdcde;
			user-select: none;
		}
		#cmx_kommunikation_box .cmx-kommu-drag:active {
			cursor: grabbing;
		}
		#cmx_kommunikation_box .cmx-kommu-drag .dashicons {
			font-size: 15px;
			width: 15px;
			height: 15px;
			line-height: 15px;
		}
		#cmx_kommunikation_box .cmx-kommu-field-check {
			display: flex;
			flex-direction: column;
			align-items: flex-start;
			justify-content: flex-end;
			gap: 2px;
			padding-bottom: 1px;
		}
		#cmx_kommunikation_box .cmx-kommu-toggle {
			position: relative;
			display: inline-flex;
			align-items: center;
			min-height: 26px;
			cursor: pointer;
		}
		#cmx_kommunikation_box .cmx-kommu-toggle input[type="checkbox"] {
			position: absolute;
			opacity: 0;
			width: 1px;
			height: 1px;
			margin: 0;
		}
		#cmx_kommunikation_box .cmx-kommu-toggle-ui {
			position: relative;
			display: inline-block;
			width: 38px;
			height: 22px;
			border-radius: 999px;
			background: #dcdcde;
			box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08);
			transition: background-color 0.18s ease, box-shadow 0.18s ease;
		}
		#cmx_kommunikation_box .cmx-kommu-toggle-ui::after {
			content: "";
			position: absolute;
			top: 3px;
			left: 3px;
			width: 16px;
			height: 16px;
			border-radius: 50%;
			background: #fff;
			box-shadow: 0 1px 3px rgba(15, 23, 42, 0.22);
			transition: transform 0.18s ease;
		}
		#cmx_kommunikation_box .cmx-kommu-toggle input[type="checkbox"]:checked + .cmx-kommu-toggle-ui {
			background: #d63638;
		}
		#cmx_kommunikation_box .cmx-kommu-toggle input[type="checkbox"]:checked + .cmx-kommu-toggle-ui::after {
			transform: translateX(16px);
		}
		#cmx_kommunikation_box .cmx-kommu-toggle input[type="checkbox"]:focus-visible + .cmx-kommu-toggle-ui {
			box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08), 0 0 0 2px rgba(214, 54, 56, 0.28);
		}
		#cmx_kommunikation_box .cmx-kommu-field-actions {
			display: flex;
			align-items: flex-end;
			justify-content: flex-end;
			padding-bottom: 2px;
		}
		#cmx_kommunikation_box .cmx-kommu-remove {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 32px;
			height: 32px;
			padding: 0;
			color: #d63638;
			line-height: 1;
			text-decoration: none;
		}
		#cmx_kommunikation_box .cmx-kommu-remove .dashicons {
			font-size: 16px;
			width: 16px;
			height: 16px;
			line-height: 16px;
		}
		#cmx_kommunikation_box .cmx-kommu-remove:hover,
		#cmx_kommunikation_box .cmx-kommu-remove:focus {
			color: #b42527;
		}
		#cmx_kommunikation_box .cmx-kommu-toolbar {
			margin-top: 10px;
		}
		@media (max-width: 1280px) {
			#cmx_kommunikation_box .cmx-kommu-contact-row {
				grid-template-columns: repeat(3, minmax(0, 1fr));
			}
			#cmx_kommunikation_box .cmx-kommu-field-actions {
				justify-content: flex-start;
			}
		}
		@media (max-width: 782px) {
			#cmx_kommunikation_box .cmx-kommu-contact-row {
				grid-template-columns: minmax(0, 1fr);
			}
		}
	</style>
	<div id="cmx_kommunikation_box" data-videochat-url="<?php echo \esc_url($nextcloud_call_url); ?>" data-videochat-nonce="<?php echo \esc_attr($videochat_nonce); ?>">
		<div class="cmx-kommu-rows">
			<?php foreach ($rows as $index => $row) { cmx_kommunikation_render_contact_row((array) $row, (int) $index, $phone_terms, $mail_terms); } ?>
		</div>
		<div class="cmx-kommu-toolbar">
			<button type="button" class="button button-secondary" id="cmx-kommu-add-row">Kontakt hinzufügen</button>
		</div>
		<template id="cmx-kommu-row-template"><?php cmx_kommunikation_render_contact_row(cmx_kommunikation_normalize_contact_row([]), '__INDEX__', $phone_terms, $mail_terms); ?></template>
		<div class="cmx-kommu-email-menu" id="cmx-kommu-email-menu" aria-hidden="true">
			<button type="button" data-action="internal">Intern</button>
			<button type="button" data-action="external">Extern</button>
		</div>
		<div class="cmx-kommu-phone-menu" id="cmx-kommu-phone-menu" aria-hidden="true">
			<button type="button" data-action="phone">Telefon</button>
			<button type="button" data-action="whatsapp">WhatsApp</button>
			<button type="button" data-action="sms">SMS</button>
			<button type="button" data-action="signal">Signal</button>
			<button type="button" data-action="videochat">VideoChat</button>
		</div>
	</div>
	<script>
	(function(){
		var root = document.getElementById("cmx_kommunikation_box");
		if (!root) return;
		var rows = root.querySelector(".cmx-kommu-rows");
		var addButton = document.getElementById("cmx-kommu-add-row");
		var template = document.getElementById("cmx-kommu-row-template");
		var emailMenu = document.getElementById("cmx-kommu-email-menu");
		var phoneMenu = document.getElementById("cmx-kommu-phone-menu");
		if (!rows || !addButton || !template || !emailMenu || !phoneMenu) return;
		var draggedRow = null;
		var emailMenuState = null;
		var phoneMenuState = null;
		var videoChatUrl = String(root.getAttribute("data-videochat-url") || "");
		var videoChatNonce = String(root.getAttribute("data-videochat-nonce") || "");

		function closeEmailMenu() {
			emailMenu.classList.remove("is-open");
			emailMenu.setAttribute("aria-hidden", "true");
			emailMenuState = null;
		}

		function closePhoneMenu() {
			phoneMenu.classList.remove("is-open");
			phoneMenu.setAttribute("aria-hidden", "true");
			phoneMenuState = null;
		}

		function openEmailMenu(trigger) {
			if (!trigger) return;
			closePhoneMenu();
			var rect = trigger.getBoundingClientRect();
			emailMenuState = {
				targetId: trigger.getAttribute("data-email-target") || "",
				internalUrl: trigger.getAttribute("data-internal-url") || ""
			};
			emailMenu.style.top = Math.round(rect.bottom + 6) + "px";
			emailMenu.style.left = Math.round(rect.left) + "px";
			emailMenu.classList.add("is-open");
			emailMenu.setAttribute("aria-hidden", "false");
		}

		function openPhoneMenu(trigger) {
			if (!trigger) return;
			closeEmailMenu();
			var rect = trigger.getBoundingClientRect();
			phoneMenuState = {
				targetId: trigger.getAttribute("data-phone-target") || ""
			};
			phoneMenu.style.top = Math.round(rect.bottom + 6) + "px";
			phoneMenu.style.left = Math.round(rect.left) + "px";
			phoneMenu.classList.add("is-open");
			phoneMenu.setAttribute("aria-hidden", "false");
		}

		function resolveEmailInput() {
			if (!emailMenuState || !emailMenuState.targetId) return null;
			return document.getElementById(emailMenuState.targetId);
		}

		function resolvePhoneInput() {
			if (!phoneMenuState || !phoneMenuState.targetId) return null;
			return document.getElementById(phoneMenuState.targetId);
		}

		function normalizePhoneDigits(value) {
			return String(value || "").replace(/\D+/g, "");
		}

		function normalizePhoneUriValue(value) {
			var normalized = normalizeToE164PhoneValue(value);
			if (normalized) {
				return normalized;
			}
			var raw = String(value || "").trim();
			var digits = normalizePhoneDigits(raw);
			if (!digits) return "";
			return raw.indexOf("+") === 0 ? "+" + digits : digits;
		}

		function normalizeToE164PhoneValue(value) {
			var raw = String(value || "").trim();
			if (!raw) return "";
			var compact = raw.replace(/[^\d+]+/g, "");
			if (compact.indexOf("00") === 0) {
				compact = "+" + compact.slice(2);
			}
			if (/^\+410\d{9}$/.test(compact)) {
				compact = "+41" + compact.slice(4);
			}
			if (/^410\d{9}$/.test(compact)) {
				compact = "+41" + compact.slice(3);
			}
			if (/^\+[1-9]\d{7,14}$/.test(compact)) {
				return compact;
			}
			if (/^41\d{9}$/.test(compact)) {
				return "+" + compact;
			}
			if (/^0\d{9}$/.test(compact)) {
				return "+41" + compact.slice(1);
			}
			if (/^\d{9}$/.test(compact)) {
				return "+41" + compact;
			}
			return raw;
		}

		function splitE164PhoneValue(normalized) {
			var compact = String(normalized || "").replace(/[^\d+]+/g, "");
			var match = compact.match(/^\+(\d{8,15})$/);
			if (!match) {
				return {country: "", national: ""};
			}
			var digits = match[1] || "";
			var knownCodes = ["423", "372", "41", "49", "43", "44", "39", "33", "1"];
			for (var i = 0; i < knownCodes.length; i += 1) {
				var code = knownCodes[i];
				if (digits.indexOf(code) === 0 && digits.length > code.length) {
					return {
						country: code,
						national: digits.slice(code.length)
					};
				}
			}
			var fallbackLen = digits.length > 11 ? 3 : (digits.length > 10 ? 2 : 1);
			return {
				country: digits.slice(0, fallbackLen),
				national: digits.slice(fallbackLen)
			};
		}

		function formatSwissPhoneDisplayValue(value) {
			var normalized = normalizeToE164PhoneValue(value);
			if (!normalized) return "";
			var parts = splitE164PhoneValue(normalized);
			if (!parts.country || !parts.national) {
				return normalized;
			}
			var country = parts.country;
			var national = parts.national;
			if (country === "41") {
				var service = national.match(/^(800|840|842|844|848|860|868|900|901|906)(\d{3})(\d{3})$/);
				if (service) {
					return "+41 " + service[1] + " " + service[2] + " " + service[3];
				}

				var swiss = national.match(/^(\d{2})(\d{3})(\d{2})(\d{2})$/);
				if (swiss) {
					return "+41 " + swiss[1] + " " + swiss[2] + " " + swiss[3] + " " + swiss[4];
				}
			}

			var nine = national.match(/^(\d{2})(\d{3})(\d{2})(\d{2})$/);
			if (nine) {
				return "+" + country + " " + nine[1] + " " + nine[2] + " " + nine[3] + " " + nine[4];
			}
			var ten = national.match(/^(\d{3})(\d{3})(\d{2})(\d{2})$/);
			if (ten) {
				return "+" + country + " " + ten[1] + " " + ten[2] + " " + ten[3] + " " + ten[4];
			}
			var seven = national.match(/^(\d{3})(\d{2})(\d{2})$/);
			if (seven) {
				return "+" + country + " " + seven[1] + " " + seven[2] + " " + seven[3];
			}
			var eight = national.match(/^(\d{2})(\d{2})(\d{2})(\d{2})$/);
			if (eight) {
				return "+" + country + " " + eight[1] + " " + eight[2] + " " + eight[3] + " " + eight[4];
			}
			return normalized;
		}

		function isValidE164PhoneValue(value) {
			return /^\+[1-9]\d{7,14}$/.test(String(value || "").trim());
		}

		function updateE164PhoneValidity(input) {
			if (!input || !input.matches("[data-cmx-phone]")) return;
			var rawValue = String(input.value || "").trim();
			if (!rawValue) {
				input.setCustomValidity("");
				return;
			}

			var normalized = normalizeToE164PhoneValue(rawValue);
			if (isValidE164PhoneValue(normalized)) {
				input.setCustomValidity("");
				return;
			}

			if (input.validity.patternMismatch) {
				input.setCustomValidity("Bitte eine gültige Telefonnummer eingeben. Beim Verlassen wird sie intern auf E.164 normalisiert und im Feld im Schweizer Format angezeigt, z. B. +41 79 123 45 67.");
				return;
			}

			input.setCustomValidity("Bitte eine gültige Telefonnummer eingeben. Schweizer Nummern ohne Ländervorwahl werden automatisch als +41 formatiert.");
		}

		function normalizePhoneField(input) {
			if (!input || !input.matches("[data-cmx-phone]")) return;
			var normalized = normalizeToE164PhoneValue(input.value);
			input.value = normalized ? formatSwissPhoneDisplayValue(normalized) : String(input.value || "").trim();
			updateE164PhoneValidity(input);
		}

		function fetchVideoChatUrl() {
			if (videoChatUrl) {
				return Promise.resolve(videoChatUrl);
			}
			try {
				var storedBaseUrl = String(window.localStorage.getItem("cmx_nextcloud_url") || "").trim().replace(/\/+$/, "");
				var storedChatRoom = String(window.localStorage.getItem("cmx_nextcloud_chat_room") || "").trim();
				if (storedBaseUrl && storedChatRoom) {
					videoChatUrl = storedBaseUrl + "/index.php/call/" + encodeURIComponent(storedChatRoom);
					root.setAttribute("data-videochat-url", videoChatUrl);
					return Promise.resolve(videoChatUrl);
				}
			} catch (error) {}
			if (typeof ajaxurl === "undefined" || !ajaxurl || !videoChatNonce) {
				return Promise.reject(new Error("VideoChat-Link fehlt auf dieser Kontakte-Seite noch. Bitte Seite neu laden."));
			}
			var form = new URLSearchParams();
			form.set("action", "cmx_kommunikation_videochat_url");
			form.set("nonce", videoChatNonce);
			return fetch(ajaxurl, {
				method: "POST",
				credentials: "same-origin",
				headers: {"Content-Type": "application/x-www-form-urlencoded"},
				body: form.toString()
			}).then(function(response) {
				return response.json().catch(function() {
					return {success: false, data: {message: "Ungültige Serverantwort."}};
				});
			}).then(function(payload) {
				if (!payload || !payload.success || !payload.data || !payload.data.url) {
					var message = payload && payload.data && payload.data.message ? payload.data.message : "VideoChat-Link fehlt. Bitte Einstellungen > System speichern und die Kontakte-Seite neu laden.";
					throw new Error(message);
				}
				videoChatUrl = String(payload.data.url || "");
				root.setAttribute("data-videochat-url", videoChatUrl);
				return videoChatUrl;
			});
		}

		function renumberRows() {
			rows.querySelectorAll(".cmx-kommu-contact-row").forEach(function(row, index) {
				row.setAttribute("data-row-index", String(index));

				row.querySelectorAll("[name]").forEach(function(field) {
					var name = field.getAttribute("name");
					if (!name) return;
					field.setAttribute("name", name.replace(/cmx_kommunikation\[kontakte\]\[[^\]]+\]/, "cmx_kommunikation[kontakte][" + index + "]"));
				});

				row.querySelectorAll("[id]").forEach(function(field) {
					var id = field.getAttribute("id");
					if (!id) return;
					field.setAttribute("id", id.replace(/_[^_]+$/, "_" + index));
				});

				row.querySelectorAll("label[for]").forEach(function(label) {
					var target = label.getAttribute("for");
					if (!target) return;
					label.setAttribute("for", target.replace(/_[^_]+$/, "_" + index));
				});

				row.querySelectorAll("[data-email-target], [data-phone-target]").forEach(function(trigger) {
					var emailTarget = trigger.getAttribute("data-email-target");
					if (emailTarget) {
						trigger.setAttribute("data-email-target", emailTarget.replace(/_[^_]+$/, "_" + index));
					}
					var phoneTarget = trigger.getAttribute("data-phone-target");
					if (phoneTarget) {
						trigger.setAttribute("data-phone-target", phoneTarget.replace(/_[^_]+$/, "_" + index));
					}
				});
			});
		}

		function updateRemoveButtons() {
			var items = rows.querySelectorAll(".cmx-kommu-contact-row");
			items.forEach(function(row) {
				var button = row.querySelector(".cmx-kommu-remove");
				if (button) {
					button.hidden = items.length <= 1;
				}
			});
		}

			function focusNextTabField(currentField, backwards) {
				if (!currentField) return false;
				var row = currentField.closest(".cmx-kommu-contact-row");
				if (!row) return false;
				var order = ["vorname", "nachname", "telefon_label", "telefon", "email_label", "email", "geburtsdatum", "anrede", "duzis"];
				var currentRole = currentField.getAttribute("data-cmx-tab-role") || "";
				var currentIndex = order.indexOf(currentRole);
				if (currentIndex === -1) return false;
			var nextIndex = currentIndex + (backwards ? -1 : 1);
			if (nextIndex < 0 || nextIndex >= order.length) {
				return false;
			}
			var nextField = row.querySelector('[data-cmx-tab-role="' + order[nextIndex] + '"]');
			if (!nextField || nextField.disabled || nextField.hidden) {
				return false;
			}
			nextField.focus();
			if (nextField.select && (nextField.tagName || "").toLowerCase() === "input" && nextField.type !== "checkbox" && nextField.type !== "date") {
				nextField.select();
			}
			return true;
		}

		function nextIndex() {
			var max = -1;
			rows.querySelectorAll(".cmx-kommu-contact-row").forEach(function(row) {
				var idx = parseInt(row.getAttribute("data-row-index") || "-1", 10);
				if (!isNaN(idx) && idx > max) {
					max = idx;
				}
			});
			return max + 1;
		}

		function addRow() {
			var html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex()));
			var wrapper = document.createElement("div");
			wrapper.innerHTML = html.trim();
			var row = wrapper.firstElementChild;
			if (!row) return;
			rows.appendChild(row);
			renumberRows();
			updateRemoveButtons();
		}

		addButton.addEventListener("click", function () {
			addRow();
		});

		rows.addEventListener("click", function (event) {
			var phoneTrigger = event.target.closest(".cmx-kommu-phone-action");
			if (phoneTrigger) {
				event.preventDefault();
				event.stopPropagation();
				if (phoneMenu.classList.contains("is-open") && phoneMenuState && phoneMenuState.targetId === (phoneTrigger.getAttribute("data-phone-target") || "")) {
					closePhoneMenu();
				} else {
					openPhoneMenu(phoneTrigger);
				}
				return;
			}

			var emailTrigger = event.target.closest(".cmx-kommu-email-action");
			if (emailTrigger) {
				event.preventDefault();
				event.stopPropagation();
				if (emailMenu.classList.contains("is-open") && emailMenuState && emailMenuState.targetId === (emailTrigger.getAttribute("data-email-target") || "")) {
					closeEmailMenu();
				} else {
					openEmailMenu(emailTrigger);
				}
				return;
			}

			var button = event.target.closest(".cmx-kommu-remove");
			if (!button) return;
			var row = button.closest(".cmx-kommu-contact-row");
			if (!row) return;
			row.remove();
			if (!rows.querySelector(".cmx-kommu-contact-row")) {
				addRow();
			}
			renumberRows();
			updateRemoveButtons();
		});

		rows.addEventListener("keydown", function(event) {
			if (event.key === "Enter" && !event.altKey && !event.ctrlKey && !event.metaKey) {
				var enterPhoneField = event.target.closest("[data-cmx-phone]");
				if (enterPhoneField) {
					event.preventDefault();
					normalizePhoneField(enterPhoneField);
					enterPhoneField.blur();
					return;
				}
			}
			if (event.key !== "Tab" || event.altKey || event.ctrlKey || event.metaKey) {
				return;
			}
			var field = event.target.closest("[data-cmx-tab-role]");
			if (!field) {
				return;
			}
			if (focusNextTabField(field, !!event.shiftKey)) {
				event.preventDefault();
			}
		});

		rows.addEventListener("input", function(event) {
			var phoneInput = event.target.closest("[data-cmx-phone]");
			if (!phoneInput) return;
			phoneInput.setCustomValidity("");
		});

		rows.addEventListener("blur", function(event) {
			var phoneInput = event.target.closest("[data-cmx-phone]");
			if (!phoneInput) return;
			normalizePhoneField(phoneInput);
		}, true);

		rows.addEventListener("change", function(event) {
			var phoneInput = event.target.closest("[data-cmx-phone]");
			if (!phoneInput) return;
			normalizePhoneField(phoneInput);
		});

		rows.addEventListener("paste", function(event) {
			var phoneInput = event.target.closest("[data-cmx-phone]");
			if (!phoneInput) return;
			window.setTimeout(function() {
				normalizePhoneField(phoneInput);
			}, 0);
		});

		rows.addEventListener("drop", function(event) {
			var phoneInput = event.target.closest("[data-cmx-phone]");
			if (!phoneInput) return;
			window.setTimeout(function() {
				normalizePhoneField(phoneInput);
			}, 0);
		});

		var form = root.closest("form");
		if (form) {
			form.addEventListener("submit", function() {
				rows.querySelectorAll("[data-cmx-phone]").forEach(function(phoneInput) {
					normalizePhoneField(phoneInput);
				});
			});
		}

		phoneMenu.addEventListener("click", function(event) {
			var actionButton = event.target.closest("button[data-action]");
			if (!actionButton || !phoneMenuState) return;
			var action = actionButton.getAttribute("data-action") || "";
			var input = resolvePhoneInput();
			var rawValue = input ? String(input.value || "").trim() : "";
			var phoneDigits = normalizePhoneDigits(rawValue);
			var phoneUriValue = normalizePhoneUriValue(rawValue);
			if ((action === "phone" || action === "sms" || action === "whatsapp" || action === "signal") && phoneDigits === "") {
				if (input) {
					input.focus();
				}
				closePhoneMenu();
				return;
			}
			if (action === "phone") {
				window.location.href = "tel:" + phoneUriValue;
				closePhoneMenu();
				return;
			}
			if (action === "sms") {
				window.location.href = "sms:" + phoneUriValue;
				closePhoneMenu();
				return;
			}
			if (action === "whatsapp") {
				window.open("https://wa.me/" + phoneDigits, "_blank", "noopener");
				closePhoneMenu();
				return;
			}
			if (action === "signal") {
				window.open("https://signal.me/#p/+" + phoneDigits, "_blank", "noopener");
				closePhoneMenu();
				return;
			}
			if (action === "videochat") {
				fetchVideoChatUrl().then(function(url) {
					window.open(url, "_blank", "noopener");
				}).catch(function(error) {
					window.alert(error && error.message ? error.message : "VideoChat-Link konnte nicht geladen werden.");
				}).finally(function() {
					closePhoneMenu();
				});
				return;
			}
		});

		emailMenu.addEventListener("click", function(event) {
			var actionButton = event.target.closest("button[data-action]");
			if (!actionButton || !emailMenuState) return;
			var action = actionButton.getAttribute("data-action") || "";
			var input = resolveEmailInput();
			var emailValue = input ? String(input.value || "").trim() : "";
			if (action === "internal") {
				if (emailMenuState.internalUrl) {
					window.location.href = emailMenuState.internalUrl;
				}
				closeEmailMenu();
				return;
			}
			if (action === "external") {
				if (emailValue !== "") {
					window.location.href = "mailto:" + encodeURIComponent(emailValue);
				} else if (input) {
					input.focus();
				}
				closeEmailMenu();
			}
		});

		document.addEventListener("click", function(event) {
			if (phoneMenu.classList.contains("is-open")) {
				if (!event.target.closest("#cmx-kommu-phone-menu") && !event.target.closest(".cmx-kommu-phone-action")) {
					closePhoneMenu();
				}
			}
			if (!emailMenu.classList.contains("is-open")) return;
			if (event.target.closest("#cmx-kommu-email-menu")) return;
			if (event.target.closest(".cmx-kommu-email-action")) return;
			closeEmailMenu();
		});

		document.addEventListener("keydown", function(event) {
			if (event.key === "Escape") {
				closePhoneMenu();
				closeEmailMenu();
			}
		});

		rows.addEventListener("dragstart", function(event) {
			closePhoneMenu();
			closeEmailMenu();
			var handle = event.target.closest(".cmx-kommu-drag");
			if (!handle) return;
			draggedRow = handle.closest(".cmx-kommu-contact-row");
			if (!draggedRow) return;
			draggedRow.classList.add("is-dragging");
			if (event.dataTransfer) {
				event.dataTransfer.effectAllowed = "move";
				try {
					event.dataTransfer.setData("text/plain", draggedRow.getAttribute("data-row-index") || "");
				} catch (error) {}
			}
		});

		rows.addEventListener("dragover", function(event) {
			if (!draggedRow) return;
			event.preventDefault();
			var targetRow = event.target.closest(".cmx-kommu-contact-row");
			if (!targetRow || targetRow === draggedRow) return;
			var rect = targetRow.getBoundingClientRect();
			var insertBefore = event.clientY < (rect.top + rect.height / 2);
			rows.insertBefore(draggedRow, insertBefore ? targetRow : targetRow.nextSibling);
		});

		rows.addEventListener("drop", function(event) {
			if (!draggedRow) return;
			event.preventDefault();
			renumberRows();
		});

		rows.addEventListener("dragend", function() {
			if (!draggedRow) return;
			draggedRow.classList.remove("is-dragging");
			draggedRow = null;
			renumberRows();
		});

		renumberRows();
		updateRemoveButtons();
	})();
	</script>
	<?php
}

\add_action('save_post', function ($post_id) {
	if (!isset($_POST['cmx_kommunikation_nonce']) || !\wp_verify_nonce($_POST['cmx_kommunikation_nonce'], 'cmx_kommunikation_save')) return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\wp_is_post_autosave($post_id) || \wp_is_post_revision($post_id)) return;
	if (!\current_user_can('edit_post', $post_id)) return;
	if (!isset($_POST['cmx_kommunikation']) || !\is_array($_POST['cmx_kommunikation'])) return;

	if (isset($_POST['_cmx_rechnung_land'])) {
			$rechnung_land = \sanitize_text_field($_POST['_cmx_rechnung_land']);
			if (\function_exists(__NAMESPACE__ . '\\cmx_kontakte_resolve_country_option_value')) {
				$rechnung_land = (string) cmx_kontakte_resolve_country_option_value($rechnung_land);
			} elseif (\function_exists(__NAMESPACE__ . '\\cmx_kontakte_normalize_country_meta_value')) {
				$rechnung_land = (string) cmx_kontakte_normalize_country_meta_value($rechnung_land);
			}
			\update_post_meta($post_id, '_cmx_rechnung_land', $rechnung_land);
	}
	if (isset($_POST['_cmx_liefer_land'])) {
			$liefer_land = \sanitize_text_field($_POST['_cmx_liefer_land']);
			if (\function_exists(__NAMESPACE__ . '\\cmx_kontakte_resolve_country_option_value')) {
				$liefer_land = (string) cmx_kontakte_resolve_country_option_value($liefer_land);
			} elseif (\function_exists(__NAMESPACE__ . '\\cmx_kontakte_normalize_country_meta_value')) {
				$liefer_land = (string) cmx_kontakte_normalize_country_meta_value($liefer_land);
			}
			\update_post_meta($post_id, '_cmx_liefer_land', $liefer_land);
	}

	$in = (array) \wp_unslash($_POST['cmx_kommunikation']);
	$posted_contacts = $in['kontakte'] ?? [];
	cmx_kommunikation_persist_contacts($post_id, \is_array($posted_contacts) ? $posted_contacts : []);
});
