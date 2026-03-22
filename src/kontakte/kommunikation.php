<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/** Verbindliche Taxonomien (unverändert) */
const CMX_TAX_PHONE_LABELS = 'kontakte_telefone';
const CMX_TAX_MAIL_LABELS  = 'kontakte_emails';

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
		return ['vorname', 'nachname', 'telefon_label', 'telefon', 'email_label', 'email', 'geburtsdatum', 'duzis'];
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
			'telefon'      => \sanitize_text_field((string) ($row['telefon'] ?? '')),
			'email_label'  => cmx_kommunikation_normalize_label_slug($row['email_label'] ?? '', CMX_TAX_MAIL_LABELS),
			'email'        => $email,
			'geburtsdatum' => cmx_kommunikation_sanitize_birthdate($row['geburtsdatum'] ?? ''),
			'duzis'        => !empty($row['duzis']) ? '1' : '0',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_contact_row_is_empty')) {
	function cmx_kommunikation_contact_row_is_empty(array $row): bool {
		foreach (['vorname', 'nachname', 'telefon', 'email', 'geburtsdatum'] as $key) {
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
				if (\preg_match('/^_cmx_kommunikation_(\d+)_(vorname|nachname|telefon_label|telefon|email_label|email|geburtsdatum|duzis)$/', (string) $meta_key, $matches)) {
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

	$option_key = 'cmx_kontakte_kommunikation_migrated_v1';
	if ((string) \get_option($option_key, '') === '1') {
		return;
	}

	cmx_kommunikation_migrate_batch(150);

	if (!cmx_kommunikation_has_pending_migrations()) {
		\update_option($option_key, '1', false);
	}
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_render_contact_row')) {
	function cmx_kommunikation_render_contact_row(array $row, int|string $index, array $phone_terms, array $mail_terms): void {
		$index_attr = (string) $index;
		$id_suffix = \preg_replace('/[^A-Za-z0-9_-]/', '_', $index_attr) ?: '0';
		$field_base = 'cmx_kommunikation[kontakte][' . $index_attr . ']';
		?>
		<div class="cmx-kommu-contact-row" data-row-index="<?php echo \esc_attr($index_attr); ?>">
			<div class="cmx-kommu-field">
				<label for="<?php echo \esc_attr('cmx_komm_vorname_' . $id_suffix); ?>">Vorname</label>
				<input id="<?php echo \esc_attr('cmx_komm_vorname_' . $id_suffix); ?>" type="text" name="<?php echo \esc_attr($field_base . '[vorname]'); ?>" value="<?php echo \esc_attr((string) ($row['vorname'] ?? '')); ?>">
			</div>
			<div class="cmx-kommu-field">
				<label for="<?php echo \esc_attr('cmx_komm_nachname_' . $id_suffix); ?>">Nachname</label>
				<input id="<?php echo \esc_attr('cmx_komm_nachname_' . $id_suffix); ?>" type="text" name="<?php echo \esc_attr($field_base . '[nachname]'); ?>" value="<?php echo \esc_attr((string) ($row['nachname'] ?? '')); ?>">
			</div>
			<div class="cmx-kommu-field">
				<label for="<?php echo \esc_attr('cmx_komm_telefon_label_' . $id_suffix); ?>">Telefon Typ</label>
				<select id="<?php echo \esc_attr('cmx_komm_telefon_label_' . $id_suffix); ?>" name="<?php echo \esc_attr($field_base . '[telefon_label]'); ?>">
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
				<label for="<?php echo \esc_attr('cmx_komm_telefon_' . $id_suffix); ?>">Telefon</label>
				<input id="<?php echo \esc_attr('cmx_komm_telefon_' . $id_suffix); ?>" type="text" name="<?php echo \esc_attr($field_base . '[telefon]'); ?>" value="<?php echo \esc_attr((string) ($row['telefon'] ?? '')); ?>">
			</div>
			<div class="cmx-kommu-field">
				<label for="<?php echo \esc_attr('cmx_komm_email_label_' . $id_suffix); ?>">E-Mail Typ</label>
				<select id="<?php echo \esc_attr('cmx_komm_email_label_' . $id_suffix); ?>" name="<?php echo \esc_attr($field_base . '[email_label]'); ?>">
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
				<label for="<?php echo \esc_attr('cmx_komm_email_' . $id_suffix); ?>">E-Mail</label>
				<input id="<?php echo \esc_attr('cmx_komm_email_' . $id_suffix); ?>" type="email" name="<?php echo \esc_attr($field_base . '[email]'); ?>" value="<?php echo \esc_attr((string) ($row['email'] ?? '')); ?>">
			</div>
			<div class="cmx-kommu-field cmx-kommu-field-date">
				<label for="<?php echo \esc_attr('cmx_komm_geburtsdatum_' . $id_suffix); ?>">Geburtsdatum</label>
				<input id="<?php echo \esc_attr('cmx_komm_geburtsdatum_' . $id_suffix); ?>" type="date" name="<?php echo \esc_attr($field_base . '[geburtsdatum]'); ?>" value="<?php echo \esc_attr((string) ($row['geburtsdatum'] ?? '')); ?>">
			</div>
			<div class="cmx-kommu-field cmx-kommu-field-check">
				<span class="cmx-kommu-field-title">Duzis</span>
				<label class="cmx-kommu-toggle" for="<?php echo \esc_attr('cmx_komm_duzis_' . $id_suffix); ?>">
					<input id="<?php echo \esc_attr('cmx_komm_duzis_' . $id_suffix); ?>" type="checkbox" name="<?php echo \esc_attr($field_base . '[duzis]'); ?>" value="1" <?php echo \checked((string) ($row['duzis'] ?? '0'), '1', false); ?>>
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

function cmx_kommunikation_box_html($post): void {
	$rows = cmx_kommunikation_read_contacts((int) $post->ID);
	$phone_terms = \taxonomy_exists(CMX_TAX_PHONE_LABELS) ? cmx_get_terms_normalized(CMX_TAX_PHONE_LABELS) : [];
	$mail_terms = \taxonomy_exists(CMX_TAX_MAIL_LABELS) ? cmx_get_terms_normalized(CMX_TAX_MAIL_LABELS) : [];
	\wp_nonce_field('cmx_kommunikation_save', 'cmx_kommunikation_nonce');
	?>
	<style>
		#cmx_kommunikation_box .cmx-kommu-rows {
			display: flex;
			flex-direction: column;
			gap: 12px;
		}
		#cmx_kommunikation_box .cmx-kommu-contact-row {
			display: grid;
			grid-template-columns: minmax(120px, 0.95fr) minmax(120px, 0.95fr) minmax(120px, 0.85fr) minmax(150px, 1.05fr) minmax(120px, 0.85fr) minmax(180px, 1.15fr) 150px 86px 40px;
			gap: 10px;
			padding: 12px;
			border: 1px solid #dcdcde;
			border-radius: 8px;
			background: #fff;
			align-items: end;
		}
		#cmx_kommunikation_box .cmx-kommu-field {
			margin: 0;
		}
		#cmx_kommunikation_box .cmx-kommu-field label {
			display: block;
			margin: 0 0 4px;
			font-weight: 600;
		}
		#cmx_kommunikation_box .cmx-kommu-field-title {
			display: block;
			margin: 0 0 4px;
			font-weight: 600;
		}
		#cmx_kommunikation_box .cmx-kommu-field input {
			width: 100%;
		}
		#cmx_kommunikation_box .cmx-kommu-field select {
			width: 100%;
			max-width: none;
		}
		#cmx_kommunikation_box .cmx-kommu-field-check {
			display: flex;
			flex-direction: column;
			align-items: flex-start;
			justify-content: flex-end;
			gap: 4px;
			padding-bottom: 3px;
		}
		#cmx_kommunikation_box .cmx-kommu-toggle {
			position: relative;
			display: inline-flex;
			align-items: center;
			min-height: 30px;
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
			width: 42px;
			height: 24px;
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
			width: 18px;
			height: 18px;
			border-radius: 50%;
			background: #fff;
			box-shadow: 0 1px 3px rgba(15, 23, 42, 0.22);
			transition: transform 0.18s ease;
		}
		#cmx_kommunikation_box .cmx-kommu-toggle input[type="checkbox"]:checked + .cmx-kommu-toggle-ui {
			background: #d63638;
		}
		#cmx_kommunikation_box .cmx-kommu-toggle input[type="checkbox"]:checked + .cmx-kommu-toggle-ui::after {
			transform: translateX(18px);
		}
		#cmx_kommunikation_box .cmx-kommu-toggle input[type="checkbox"]:focus-visible + .cmx-kommu-toggle-ui {
			box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08), 0 0 0 2px rgba(214, 54, 56, 0.28);
		}
		#cmx_kommunikation_box .cmx-kommu-field-actions {
			display: flex;
			align-items: flex-end;
			justify-content: flex-end;
			padding-bottom: 4px;
		}
		#cmx_kommunikation_box .cmx-kommu-remove {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 36px;
			height: 36px;
			padding: 0;
			color: #d63638;
			line-height: 1;
			text-decoration: none;
		}
		#cmx_kommunikation_box .cmx-kommu-remove .dashicons {
			font-size: 18px;
			width: 18px;
			height: 18px;
			line-height: 18px;
		}
		#cmx_kommunikation_box .cmx-kommu-remove:hover,
		#cmx_kommunikation_box .cmx-kommu-remove:focus {
			color: #b42527;
		}
		#cmx_kommunikation_box .cmx-kommu-toolbar {
			margin-top: 12px;
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
	<div id="cmx_kommunikation_box">
		<div class="cmx-kommu-rows">
			<?php foreach ($rows as $index => $row) { cmx_kommunikation_render_contact_row((array) $row, (int) $index, $phone_terms, $mail_terms); } ?>
		</div>
		<div class="cmx-kommu-toolbar">
			<button type="button" class="button button-secondary" id="cmx-kommu-add-row">Kontakt hinzufügen</button>
		</div>
		<template id="cmx-kommu-row-template"><?php cmx_kommunikation_render_contact_row(cmx_kommunikation_normalize_contact_row([]), '__INDEX__', $phone_terms, $mail_terms); ?></template>
	</div>
	<script>
	(function(){
		var root = document.getElementById("cmx_kommunikation_box");
		if (!root) return;
		var rows = root.querySelector(".cmx-kommu-rows");
		var addButton = document.getElementById("cmx-kommu-add-row");
		var template = document.getElementById("cmx-kommu-row-template");
		if (!rows || !addButton || !template) return;

		function updateRemoveButtons() {
			var items = rows.querySelectorAll(".cmx-kommu-contact-row");
			items.forEach(function(row) {
				var button = row.querySelector(".cmx-kommu-remove");
				if (button) {
					button.hidden = items.length <= 1;
				}
			});
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
			updateRemoveButtons();
		}

		addButton.addEventListener("click", function () {
			addRow();
		});

		rows.addEventListener("click", function (event) {
			var button = event.target.closest(".cmx-kommu-remove");
			if (!button) return;
			var row = button.closest(".cmx-kommu-contact-row");
			if (!row) return;
			row.remove();
			if (!rows.querySelector(".cmx-kommu-contact-row")) {
				addRow();
			}
			updateRemoveButtons();
		});

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
