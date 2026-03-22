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

if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_read_contacts')) {
	function cmx_kommunikation_read_contacts(int $post_id): array {
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
				'telefon_label'=> (string) ($bundle['telefon'][$slot]['label'] ?? ''),
				'telefon'      => cmx_kommunikation_legacy_slot_value($post_id, $bundle, 'telefon', $slot),
				'email_label'  => (string) ($bundle['email'][$slot]['label'] ?? ''),
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
				<label for="<?php echo \esc_attr('cmx_komm_duzis_' . $id_suffix); ?>">Duzis</label>
				<input id="<?php echo \esc_attr('cmx_komm_duzis_' . $id_suffix); ?>" type="checkbox" name="<?php echo \esc_attr($field_base . '[duzis]'); ?>" value="1" <?php echo \checked((string) ($row['duzis'] ?? '0'), '1', false); ?>>
			</div>
			<div class="cmx-kommu-field cmx-kommu-field-actions">
				<button type="button" class="button-link-delete cmx-kommu-remove">Entfernen</button>
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
			grid-template-columns: minmax(120px, 0.95fr) minmax(120px, 0.95fr) minmax(120px, 0.85fr) minmax(150px, 1.05fr) minmax(120px, 0.85fr) minmax(180px, 1.15fr) 150px 86px 72px;
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
			padding-bottom: 4px;
		}
		#cmx_kommunikation_box .cmx-kommu-field-check input[type="checkbox"] {
			width: auto;
			margin: 0;
		}
		#cmx_kommunikation_box .cmx-kommu-field-actions {
			display: flex;
			align-items: flex-end;
			justify-content: flex-end;
			padding-bottom: 4px;
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
	$bundle = \get_post_meta($post_id, '_cmx_kommunikation', true);
	if (!\is_array($bundle)) {
		$bundle = [];
	}
	$bundle['telefon'] = \is_array($bundle['telefon'] ?? null) ? $bundle['telefon'] : [];
	$bundle['email'] = \is_array($bundle['email'] ?? null) ? $bundle['email'] : [];

	$contacts = [];
	$posted_contacts = $in['kontakte'] ?? [];
	if (\is_array($posted_contacts)) {
		foreach ($posted_contacts as $row) {
			if (!\is_array($row)) {
				continue;
			}
			$normalized = cmx_kommunikation_normalize_contact_row($row);
			if (!cmx_kommunikation_contact_row_is_empty($normalized)) {
				$contacts[] = $normalized;
			}
		}
	}
	$bundle['kontakte'] = $contacts;

	for ($slot = 1; $slot <= 3; $slot++) {
		$row = $contacts[$slot - 1] ?? null;
		$telefon_label = (string) ($row['telefon_label'] ?? '');
		$telefon = (string) ($row['telefon'] ?? '');
		$email_label = (string) ($row['email_label'] ?? '');
		$email = (string) ($row['email'] ?? '');

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

		$bundle['telefon'][$slot] = [
			'label' => $telefon_label,
			'value' => $telefon,
		];
		$bundle['email'][$slot] = [
			'label' => $email_label,
			'value' => $email,
			'valid' => \is_email($email) ? '1' : '0',
		];
	}

	$first = $contacts[0] ?? null;
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

	\update_post_meta($post_id, '_cmx_kommunikation', $bundle);
});
