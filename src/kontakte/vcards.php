<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/** Debug-Flag (bei Bedarf auf false) */
if (!defined(__NAMESPACE__.'\\CMX_VCARD_DEBUG')) {
	define(__NAMESPACE__.'\\CMX_VCARD_DEBUG', true);
}

/**
 * UI: Link "vcard" in der Kontakte-Liste – öffnet direkt Dateidialog (.vcf) und submitted
 */
add_filter('views_edit-kontakte', __NAMESPACE__ . '\\cmx_kontakte_add_vcard_view_link');
function cmx_kontakte_add_vcard_view_link(array $views): array {
	$views['cmx_vcard'] = '<a href="#" class="cmx-vcard-link">vcard</a>';
	return $views;
}

add_action('admin_footer-edit.php', __NAMESPACE__ . '\\cmx_kontakte_vcard_uploader_footer');
function cmx_kontakte_vcard_uploader_footer(): void {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || $screen->post_type !== 'kontakte') return;

	$action_url  = admin_url('admin-post.php');
	$nonce_field = wp_create_nonce('cmx_kontakte_vcard_import'); ?>
	<form id="cmx-vcard-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url($action_url); ?>" style="display:none">
		<input type="hidden" name="action" value="cmx_kontakte_vcard_import">
		<input type="hidden" name="cmx_kontakte_vcard_nonce" value="<?php echo esc_attr($nonce_field); ?>">
		<input type="file" id="cmx_vcf_file" name="cmx_vcf_file" accept=".vcf,text/vcard,text/x-vcard">
	</form>
	<script>
	(function(){
		const link = document.querySelector('a.cmx-vcard-link');
		if (!link) return;
		link.addEventListener('click', function(e){
			e.preventDefault();
			const input = document.getElementById('cmx_vcf_file');
			if (!input) return;
			input.value = '';
			input.click();
		});
		const input = document.getElementById('cmx_vcf_file');
		if (!input) return;
		input.addEventListener('change', function(){
			if (input.files && input.files.length > 0) {
				document.getElementById('cmx-vcard-form').submit();
			}
		});
	})();
	</script><?php
}

/**
 * Import-Handler
 */
add_action('admin_post_cmx_kontakte_vcard_import', __NAMESPACE__ . '\\cmx_kontakte_vcard_handle');
function cmx_kontakte_vcard_handle(): void {
	if (!current_user_can('manage_options')) wp_die(__('Keine Berechtigung.'));
	if (empty($_POST['cmx_kontakte_vcard_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cmx_kontakte_vcard_nonce'])), 'cmx_kontakte_vcard_import')) {
		wp_die(__('Sicherheitsprüfung fehlgeschlagen.'));
	}
	if (empty($_FILES['cmx_vcf_file']['tmp_name']) || !is_uploaded_file($_FILES['cmx_vcf_file']['tmp_name'])) {
		cmx_kontakte_vcard_redirect('Keine Datei empfangen.');
		return;
	}

	$cards = cmx_parse_vcard_file($_FILES['cmx_vcf_file']['tmp_name']);
	if (is_wp_error($cards)) {
		cmx_kontakte_vcard_redirect($cards->get_error_message());
		return;
	}

	$total_cards = \count($cards);
	$results = [
		'imported' => [],
		'updated' => [],
		'skipped' => [],
		'failed' => [],
	];
	$single_post_id = 0;

	foreach ($cards as $card) {
		if (CMX_VCARD_DEBUG) error_log('[VCARD parsed] ' . print_r($card, true));
		$result = cmx_kontakte_vcard_import_contact((array) $card);
		if (is_wp_error($result)) {
			$results['failed'][] = cmx_kontakte_vcard_prepare_title((array) $card);
			continue;
		}

		$status = (string) ($result['status'] ?? '');
		$post_id = (int) ($result['post_id'] ?? 0);
		$label = trim((string) ($result['label'] ?? ''));
		if ($label === '') {
			$label = 'Kontakt #' . $post_id;
		}
		if (!isset($results[$status])) {
			$results['failed'][] = $label;
			continue;
		}

		$results[$status][] = $label;
		if ($total_cards === 1 && $post_id > 0) {
			$single_post_id = $post_id;
		}
	}

	if ($results['imported'] === [] && $results['updated'] === [] && $results['skipped'] === []) {
		cmx_kontakte_vcard_redirect($results);
		return;
	}

	if ($total_cards === 1 && $single_post_id > 0) {
		cmx_kontakte_vcard_redirect($results, $single_post_id);
		return;
	}

	cmx_kontakte_vcard_redirect($results);
}

/** ---------- Helpers (Label-Slugs, Länder, Redirect, Parser) ---------- */

function cmx_kontakte_vcard_import_contact(array $d) {
	$post_title = cmx_kontakte_vcard_prepare_title($d);
	$post_id = cmx_kontakte_vcard_find_existing_contact_id($post_title);

	if ($post_id > 0) {
		$changed = cmx_kontakte_vcard_apply_contact_data($post_id, $d, false);
		if (\is_wp_error($changed)) {
			return $changed;
		}

		return [
			'status' => $changed ? 'updated' : 'skipped',
			'post_id' => $post_id,
			'label' => (string) \get_the_title($post_id),
		];
	}

	$post_id = \wp_insert_post([
		'post_type'   => 'kontakte',
		'post_status' => 'publish',
		'post_title'  => $post_title,
	], true);
	if (\is_wp_error($post_id) || !$post_id) {
		return new \WP_Error('vcf_save', 'Kontakt konnte nicht angelegt werden.');
	}

	$changed = cmx_kontakte_vcard_apply_contact_data((int) $post_id, $d, true);
	if (\is_wp_error($changed)) {
		return $changed;
	}

	return [
		'status' => 'imported',
		'post_id' => (int) $post_id,
		'label' => (string) \get_the_title((int) $post_id),
	];
}

function cmx_kontakte_vcard_prepare_title(array $d): string {
	$company = trim((string) ($d['company'] ?? ''));
	$first   = trim((string) ($d['first_name'] ?? ''));
	$last    = trim((string) ($d['last_name'] ?? ''));

	if ($company !== '') {
		return \sanitize_text_field($company);
	} elseif (($first . $last) !== '') {
		return \sanitize_text_field(trim($first . ' ' . $last));
	}

	return 'Kontakt ' . \current_time('Y-m-d H:i:s');
}

function cmx_kontakte_vcard_find_existing_contact_id(string $post_title): int {
	$post_title = trim($post_title);
	if ($post_title === '') {
		return 0;
	}

	if (\function_exists(__NAMESPACE__ . '\\cmx_import_find_existing_kontakt_id_by_title')) {
		return (int) \call_user_func(__NAMESPACE__ . '\\cmx_import_find_existing_kontakt_id_by_title', $post_title);
	}

	$query = new \WP_Query([
		'post_type' => 'kontakte',
		'post_status' => ['publish', 'draft', 'pending', 'private'],
		'title' => $post_title,
		'posts_per_page' => 1,
		'fields' => 'ids',
		'no_found_rows' => true,
	]);

	if (!empty($query->posts[0])) {
		return (int) $query->posts[0];
	}

	return 0;
}

function cmx_kontakte_vcard_apply_contact_data(int $post_id, array $d, bool $is_new) {
	$changed = $is_new;
	$post_title = cmx_kontakte_vcard_prepare_title($d);
	$current_title = (string) \get_the_title($post_id);
	if ($post_title !== '' && $post_title !== $current_title) {
		$updated_post_id = \wp_update_post([
			'ID' => $post_id,
			'post_title' => $post_title,
		], true);
		if (\is_wp_error($updated_post_id)) {
			return $updated_post_id;
		}
		$changed = true;
	}

	$notes = \wp_kses_post((string) ($d['notes'] ?? ''));
	if ($notes !== '') {
		$changed = cmx_kontakte_vcard_update_meta_if_changed($post_id, '_cmx_interne_notizen', $notes) || $changed;
	}

	$first_name = \sanitize_text_field((string) ($d['first_name'] ?? ''));
	if ($first_name !== '') {
		$changed = cmx_kontakte_vcard_update_meta_if_changed($post_id, CMX_KONTAKTE_META_VORNAME, $first_name) || $changed;
	}

	$last_name = \sanitize_text_field((string) ($d['last_name'] ?? ''));
	if ($last_name !== '') {
		$changed = cmx_kontakte_vcard_update_meta_if_changed($post_id, CMX_KONTAKTE_META_NACHNAME, $last_name) || $changed;
	}

	$website = \esc_url_raw((string) ($d['website'] ?? ''));
	if ($website !== '') {
		$changed = cmx_kontakte_vcard_update_meta_if_changed($post_id, CMX_KONTAKTE_META_URL, $website) || $changed;
	}

	$bday = trim((string) ($d['bday'] ?? ''));
	if ($bday !== '') {
		$changed = cmx_kontakte_vcard_update_meta_if_changed($post_id, CMX_KONTAKTE_META_DATUM, $bday) || $changed;
	}

	$privat = empty($d['company']) ? '1' : '0';
	$changed = cmx_kontakte_vcard_update_meta_if_changed($post_id, CMX_KONTAKTE_META_PRIVAT, $privat) || $changed;

	$email_label_map = cmx_find_label_slugs(['privat','geschaeft','home','work','other'], CMX_TAX_MAIL_LABELS);
	$phone_label_map = cmx_find_label_slugs(['privat','geschaeft','mobile','home','work','other'], CMX_TAX_PHONE_LABELS);
	$bundle = \get_post_meta($post_id, '_cmx_kommunikation', true);
	if (!is_array($bundle)) $bundle = ['telefon' => [], 'email' => []];
	$bundle['telefon'] = \is_array($bundle['telefon'] ?? null) ? $bundle['telefon'] : [];
	$bundle['email'] = \is_array($bundle['email'] ?? null) ? $bundle['email'] : [];
	$original_bundle = $bundle;

	$emails = array_slice((array) ($d['emails'] ?? []), 0, 3);
	foreach ($emails as $i => $row) {
		$changed = cmx_kontakte_vcard_apply_email_row($post_id, $i + 1, (array) $row, $bundle, $email_label_map) || $changed;
	}

	$tels = array_slice((array) ($d['tels'] ?? []), 0, 3);
	foreach ($tels as $i => $row) {
		$changed = cmx_kontakte_vcard_apply_phone_row($post_id, $i + 1, (array) $row, $bundle, $phone_label_map) || $changed;
	}

	if ($bundle !== $original_bundle) {
		\update_post_meta($post_id, '_cmx_kommunikation', $bundle);
		$changed = true;
	}

	$addr1 = $d['addresses'][0] ?? null;
	$addr2 = $d['addresses'][1] ?? null;
	if ($addr1) {
		$changed = cmx_kontakte_vcard_apply_address($post_id, (array) $addr1, [
			'street' => CMX_RECHNUNG_META_STRASSE,
			'extended' => CMX_RECHNUNG_META_ZUSATZ,
			'zip' => CMX_RECHNUNG_META_PLZ,
			'city' => CMX_RECHNUNG_META_ORT,
			'country' => CMX_RECHNUNG_META_LAND,
		]) || $changed;
	}
	if ($addr2) {
		$changed = cmx_kontakte_vcard_apply_address($post_id, (array) $addr2, [
			'street' => CMX_LIEFER_META_STRASSE,
			'extended' => CMX_LIEFER_META_ZUSATZ,
			'zip' => CMX_LIEFER_META_PLZ,
			'city' => CMX_LIEFER_META_ORT,
			'country' => CMX_LIEFER_META_LAND,
		]) || $changed;
	}

	if (CMX_VCARD_DEBUG) {
		error_log('[VCARD saved] post_id=' . $post_id);
		foreach ([
			CMX_KONTAKTE_META_VORNAME, CMX_KONTAKTE_META_NACHNAME, CMX_KONTAKTE_META_URL, CMX_KONTAKTE_META_DATUM,
			'_cmx_email_1','_cmx_email_2','_cmx_email_3',
			'_cmx_telefon_1','_cmx_telefon_2','_cmx_telefon_3',
			CMX_RECHNUNG_META_STRASSE, CMX_RECHNUNG_META_ZUSATZ, CMX_RECHNUNG_META_PLZ, CMX_RECHNUNG_META_ORT, CMX_RECHNUNG_META_LAND,
			CMX_LIEFER_META_STRASSE, CMX_LIEFER_META_ZUSATZ, CMX_LIEFER_META_PLZ, CMX_LIEFER_META_ORT, CMX_LIEFER_META_LAND,
			'_cmx_kommunikation',
		] as $k) {
			error_log("  meta[$k]=" . print_r(get_post_meta($post_id, $k, true), true));
		}
	}

	return $changed;
}

function cmx_kontakte_vcard_update_meta_if_changed(int $post_id, string $meta_key, string $value): bool {
	$current = (string) \get_post_meta($post_id, $meta_key, true);
	if ($current === $value) {
		return false;
	}
	\update_post_meta($post_id, $meta_key, $value);
	return true;
}

function cmx_kontakte_vcard_apply_email_row(int $post_id, int $slot, array $row, array &$bundle, array $label_map): bool {
	$val = \sanitize_email((string) ($row['value'] ?? ''));
	if ($val === '') {
		return false;
	}

	$type = strtolower((string) ($row['type'] ?? 'other'));
	$label = cmx_pick_slug_for_type($type, $label_map);
	$target_entry = [
		'label' => $label,
		'value' => $val,
		'valid' => \is_email($val) ? '1' : '0',
	];
	$changed = cmx_kontakte_vcard_update_meta_if_changed($post_id, "_cmx_email_{$slot}", $val);
	$current_entry = $bundle['email'][$slot] ?? [];
	if (!\is_array($current_entry) || $current_entry !== $target_entry) {
		$bundle['email'][$slot] = $target_entry;
		$changed = true;
	}

	return $changed;
}

function cmx_kontakte_vcard_apply_phone_row(int $post_id, int $slot, array $row, array &$bundle, array $label_map): bool {
	$val = \sanitize_text_field((string) ($row['value'] ?? ''));
	if ($val === '') {
		return false;
	}

	$type = strtolower((string) ($row['type'] ?? 'other'));
	$label = cmx_pick_slug_for_type($type, $label_map);
	$target_entry = [
		'label' => $label,
		'value' => $val,
	];
	$changed = cmx_kontakte_vcard_update_meta_if_changed($post_id, "_cmx_telefon_{$slot}", $val);
	$current_entry = $bundle['telefon'][$slot] ?? [];
	if (!\is_array($current_entry) || $current_entry !== $target_entry) {
		$bundle['telefon'][$slot] = $target_entry;
		$changed = true;
	}

	return $changed;
}

function cmx_kontakte_vcard_apply_address(int $post_id, array $address, array $meta_map): bool {
	$changed = false;
	$street = \sanitize_text_field((string) ($address['street'] ?? ''));
	$extended = \sanitize_text_field((string) ($address['extended'] ?? ''));
	$zip = \sanitize_text_field((string) ($address['zip'] ?? ''));
	$city = \sanitize_text_field((string) ($address['city'] ?? ''));
	$country = cmx_normalize_country_slug((string) ($address['country'] ?? ''));

	$changed = cmx_kontakte_vcard_update_meta_if_changed($post_id, $meta_map['street'], $street) || $changed;
	$changed = cmx_kontakte_vcard_update_meta_if_changed($post_id, $meta_map['extended'], $extended) || $changed;
	$changed = cmx_kontakte_vcard_update_meta_if_changed($post_id, $meta_map['zip'], $zip) || $changed;
	$changed = cmx_kontakte_vcard_update_meta_if_changed($post_id, $meta_map['city'], $city) || $changed;
	$changed = cmx_kontakte_vcard_update_meta_if_changed($post_id, $meta_map['country'], $country) || $changed;

	return $changed;
}

/** ermittelt vorhandene Slugs aus Taxonomie in gegebener Präferenzreihenfolge */
function cmx_find_label_slugs(array $candidates, string $taxonomy): array {
	$out = [];
	foreach ($candidates as $slug) {
		$slug = sanitize_title($slug);
		if (cmx_term_slug_exists($taxonomy, $slug)) $out[$slug] = $slug;
	}
	return $out; // z.B. ['privat'=>'privat','geschaeft'=>'geschaeft','mobile'=>'mobile']
}

/** wählt für einen TYPE den besten vorhandenen Slug */
function cmx_pick_slug_for_type(string $type, array $available): string {
	$type = strtolower($type);
	// Mapping: bevorzuge deutsch, fallback englisch
	$pref = [];
	if ($type === 'home')     $pref = ['privat','home'];
	elseif ($type === 'work') $pref = ['geschaeft','work'];
	elseif ($type === 'cell' || $type === 'mobile') $pref = ['mobile','handy'];
	else $pref = ['other','sonstiges'];

	foreach ($pref as $want) {
		$want = sanitize_title($want);
		if (isset($available[$want])) return $available[$want];
	}
	// gar kein passender Term → leer lassen
	return '';
}

/** Land aus ADR → slug (bevorzugt 2-letter) */
function cmx_normalize_country_slug(string $country): string {
	$country = trim($country);
	if ($country === '') return '';
	// 2-letter code
	if (preg_match('/^[A-Za-z]{2}$/', $country)) return strtolower($country);
	// Schweiz-Varianten
	$map = [
		'switzerland' => 'ch', 'schweiz' => 'ch', 'suisse' => 'ch', 'svizzera' => 'ch',
		'germany' => 'de', 'deutschland' => 'de',
		'austria' => 'at', 'österreich' => 'at', 'oesterreich' => 'at',
	];
	$key = strtolower(remove_accents($country));
	return $map[$key] ?? strtolower($country);
}

/** Redirect in Edit-Ansicht oder zurück zur Liste */
function cmx_kontakte_vcard_redirect($notice, ?int $post_id = null): void {
	if (\is_string($notice)) {
		$notice = ['failed' => [trim($notice)]];
	}
	if (!\is_array($notice)) {
		$notice = ['failed' => ['Der vCard-Import konnte nicht abgeschlossen werden.']];
	}
	\set_transient(cmx_kontakte_vcard_notice_key(), $notice, 60);

	if ($post_id && $post_id > 0) {
		$edit_url = get_edit_post_link($post_id, '');
		if ($edit_url) {
			$edit_url = add_query_arg(['cmx_vcard_notice' => 1], $edit_url);
			wp_safe_redirect($edit_url);
			exit;
		}
	}
	$url = add_query_arg(['cmx_vcard_notice' => 1], admin_url('edit.php?post_type=kontakte'));
	wp_safe_redirect($url);
	exit;
}

add_action('all_admin_notices', function (): void {
	if (!\is_admin() || empty($_GET['cmx_vcard_notice'])) {
		return;
	}
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen) {
		return;
	}
	$is_kontakte_screen = ((string) ($screen->post_type ?? '') === 'kontakte')
		&& \in_array((string) ($screen->base ?? ''), ['edit', 'post'], true);
	if (!$is_kontakte_screen) {
		return;
	}
	$notice = \get_transient(cmx_kontakte_vcard_notice_key());
	if (!\is_array($notice)) {
		return;
	}
	\delete_transient(cmx_kontakte_vcard_notice_key());
	$lines = cmx_kontakte_vcard_notice_lines($notice);
	if ($lines === []) {
		return;
	}
	echo '<div class="notice notice-success is-dismissible">';
	foreach ($lines as $line) {
		echo '<p>' . wp_kses_post($line) . '</p>';
	}
	echo '</div>';
});

function cmx_kontakte_vcard_notice_key(): string {
	return 'cmx_vcard_notice_kontakte_' . (int) \get_current_user_id();
}

function cmx_kontakte_vcard_notice_lines(array $notice): array {
	$sections = [
		'imported' => 'Importiert',
		'updated' => 'Aktualisiert',
		'skipped' => 'Übersprungen',
	];
	$lines = ['<strong>vCard-Import abgeschlossen:</strong>'];

	foreach ($sections as $key => $label) {
		$items = array_values(array_filter(array_map('trim', (array) ($notice[$key] ?? []))));
		if ($items === []) {
			$lines[] = '<strong>' . \esc_html($label) . ' (0):</strong> -';
			continue;
		}
		$lines[] = '<strong>' . \esc_html($label) . ' (' . \count($items) . '):</strong> ' . \esc_html(implode(', ', $items));
	}

	$failed = array_values(array_filter(array_map('trim', (array) ($notice['failed'] ?? []))));
	if ($failed !== []) {
		$lines[] = '<strong>Fehlgeschlagen (' . \count($failed) . '):</strong> ' . \esc_html(implode(', ', $failed));
	}

	return $lines;
}

function cmx_parse_single_vcard(string $filepath) {
	$cards = cmx_parse_vcard_file($filepath);
	if (is_wp_error($cards)) {
		return $cards;
	}
	if (\count($cards) !== 1) {
		return new \WP_Error('vcf_count', 'Die vCard-Datei muss genau einen Kontakt enthalten.');
	}
	return $cards[0];
}

/** vCard-Parser (ein oder mehrere Kontakte) – Apple + Google kompatibel */
function cmx_parse_vcard_file(string $filepath) {
	$raw = file_get_contents($filepath);
	if ($raw === false || $raw === '') return new \WP_Error('vcf_empty', 'vCard ist leer oder nicht lesbar.');

	// normalize & unfold
	$raw = str_replace(["\r\n", "\r"], "\n", $raw);
	$lines = explode("\n", $raw);
	$unfolded = [];
	foreach ($lines as $line) {
		if ($line === '') continue;
		if (!empty($unfolded) && (isset($line[0]) && ($line[0] === ' ' || $line[0] === "\t"))) {
			$unfolded[count($unfolded) - 1] .= substr($line, 1);
		} else {
			$unfolded[] = $line;
		}
	}
	$blocks = [];
	$current = [];
	$inside = false;
	foreach ($unfolded as $line) {
		$upper = strtoupper(trim((string) $line));
		if ($upper === 'BEGIN:VCARD') {
			$inside = true;
			$current = [];
			continue;
		}
		if ($upper === 'END:VCARD') {
			if ($inside && $current !== []) {
				$blocks[] = $current;
			}
			$inside = false;
			$current = [];
			continue;
		}
		if ($inside) {
			$current[] = $line;
		}
	}

	if ($blocks === []) {
		return new \WP_Error('vcf_count', 'Die vCard-Datei enthält keinen gültigen Kontakt.');
	}

	$out = [];
	foreach ($blocks as $block) {
		$parsed = cmx_parse_single_vcard_block($block);
		if (\is_array($parsed)) {
			$out[] = $parsed;
		}
	}

	if ($out === []) {
		return new \WP_Error('vcf_parse', 'Die vCard-Datei konnte nicht gelesen werden.');
	}

	return $out;
}

function cmx_parse_single_vcard_block(array $block): array {
	$out = [
		'company'    => '',
		'first_name' => '',
		'last_name'  => '',
		'website'    => '',
		'notes'      => '',
		'bday'       => '',
		'emails'     => [], // [['type'=>'home|work|other', 'value'=>'...']]
		'tels'       => [], // [['type'=>'home|work|mobile|other', 'value'=>'...']]
		'addresses'  => [], // [['street','zip','city','country']]
	];
	$full_name = '';

	foreach ($block as $line) {
		$pos = strpos($line, ':');
		if ($pos === false) continue;
		$left  = substr($line, 0, $pos);
		$value = substr($line, $pos + 1);

		// "item1.EMAIL" → "EMAIL"
		$parts = explode(';', $left);
		$prop  = strtoupper(array_shift($parts));
		$prop  = preg_replace('/^ITEM\d+\./i', '', $prop);
		if (strpos($prop, 'X-AB') === 0) {
			continue;
		}

		$params = ['TYPE' => []];
		foreach ($parts as $p) {
			if (strpos($p, '=') === false) {
				$params['TYPE'][] = strtoupper(trim($p));
				continue;
			}
			[$k, $v] = array_map('trim', explode('=', $p, 2));
			$kU = strtoupper($k);
			if ($kU === 'TYPE') {
				foreach (explode(',', $v) as $vv) {
					$vv = strtoupper(trim($vv));
					if ($vv !== '') $params['TYPE'][] = $vv;
				}
			} else {
				$params[$kU] = $v;
			}
		}

		$value = cmx_vcard_decode_value($value, $params);

		switch ($prop) {
			case 'N': {
				$name_parts = cmx_vcard_parse_n_value($value);
				if ($out['last_name'] === '' && $name_parts['last_name'] !== '') {
					$out['last_name'] = sanitize_text_field($name_parts['last_name']);
				}
				if ($out['first_name'] === '' && $name_parts['first_name'] !== '') {
					$out['first_name'] = sanitize_text_field($name_parts['first_name']);
				}
				break;
			}
			case 'FN': {
				$full_name = trim((string) $value);
				break;
			}
			case 'ORG':
				$out['company'] = sanitize_text_field(str_replace(';', ' ', $value));
				break;

			case 'BDAY':
				$out['bday'] = cmx_normalize_bday($value);
				break;

			case 'EMAIL': {
				$email = sanitize_email($value);
				if (!$email) break;
				$type = 'other';
				$t = strtoupper(implode(',', $params['TYPE']));
				if (str_contains($t, 'HOME')) $type = 'home';
				elseif (str_contains($t, 'WORK')) $type = 'work';
				$out['emails'][] = ['type' => $type, 'value' => $email];
				break;
			}

			case 'TEL': {
				$tel = sanitize_text_field($value);
				if (!$tel) break;
				$type = 'other';
				$t = strtoupper(implode(',', $params['TYPE']));
				if (str_contains($t, 'HOME')) $type = 'home';
				elseif (str_contains($t, 'WORK')) $type = 'work';
				elseif (str_contains($t, 'CELL') || str_contains($t, 'MOBILE')) $type = 'mobile';
				$out['tels'][] = ['type' => $type, 'value' => $tel];
				break;
			}

			case 'URL':
				if ($out['website'] === '') $out['website'] = esc_url_raw($value);
				break;

			case 'NOTE':
				if ($out['notes'] === '') $out['notes'] = wp_kses_post($value);
				break;

			case 'ADR': {
				// ADR: PO Box;Extended;Street;City;Region;PostalCode;Country
				$adr = \array_pad(\explode(';', $value), 7, '');
				$extended = trim((string) ($adr[1] ?? ''));
				$street = trim((string) ($adr[2] ?? ''));
				$street_full = trim(implode("\n", array_values(array_filter([$extended, $street], static function(string $v): bool {
					return $v !== '';
				}))));
				$city = trim((string) ($adr[3] ?? ''));
				$zip = trim((string) ($adr[5] ?? ''));
				$country = trim((string) ($adr[6] ?? ''));
				if ($street_full !== '' || $zip !== '' || $city !== '' || $country !== '') {
					if (count($out['addresses']) < 2) {
						$out['addresses'][] = [
							'street'  => sanitize_text_field($street_full),
							'extended'=> sanitize_text_field($extended),
							'zip'     => sanitize_text_field($zip),
							'city'    => sanitize_text_field($city),
							'country' => sanitize_text_field($country),
						];
					}
				}
				break;
			}
		}
	}

	if ($full_name !== '') {
		$fn_parts = cmx_vcard_parse_fn_value($full_name);
		if ($out['first_name'] === '' && $fn_parts['first_name'] !== '') {
			$out['first_name'] = sanitize_text_field($fn_parts['first_name']);
		}
		if ($out['last_name'] === '' && $fn_parts['last_name'] !== '') {
			$out['last_name'] = sanitize_text_field($fn_parts['last_name']);
		}
	}

	// Begrenzen
	if (count($out['emails']) > 3)    $out['emails']    = array_slice($out['emails'], 0, 3);
	if (count($out['addresses']) > 2) $out['addresses'] = array_slice($out['addresses'], 0, 2);

	return $out;
}

function cmx_vcard_parse_n_value(string $value): array {
	$bits = \array_pad(\explode(';', $value), 5, '');
	$family = cmx_vcard_clean_name_part((string) ($bits[0] ?? ''));
	$given = cmx_vcard_clean_name_part((string) ($bits[1] ?? ''));
	$additional = cmx_vcard_clean_name_part((string) ($bits[2] ?? ''));
	$first = trim(implode(' ', array_values(array_filter([$given, $additional], static function(string $v): bool {
		return $v !== '';
	}))));

	return [
		'first_name' => $first,
		'last_name'  => $family,
	];
}

function cmx_vcard_parse_fn_value(string $value): array {
	$full = cmx_vcard_clean_name_part($value);
	if ($full === '') {
		return ['first_name' => '', 'last_name' => ''];
	}

	$parts = \preg_split('/\s+/u', $full) ?: [];
	if (\count($parts) <= 1) {
		return ['first_name' => $full, 'last_name' => ''];
	}

	$last_part = (string) \end($parts);
	if (\count($parts) === 2 && cmx_vcard_is_initial_token($last_part)) {
		return ['first_name' => $full, 'last_name' => ''];
	}

	$last_name = (string) \array_pop($parts);
	$first_name = \trim(\implode(' ', $parts));

	return [
		'first_name' => $first_name,
		'last_name'  => $last_name,
	];
}

function cmx_vcard_clean_name_part(string $value): string {
	$value = \trim((string) \preg_replace('/\s+/u', ' ', $value));
	return $value;
}

function cmx_vcard_is_initial_token(string $value): bool {
	$value = \trim($value);
	return $value !== '' && (bool) \preg_match('/^[[:alpha:]]\.$/u', $value);
}

/** Value-Decoding (Quoted-Printable/Charset) + vCard-Unescapes */
function cmx_vcard_decode_value(string $value, array $params): string {
	$enc = strtoupper($params['ENCODING'] ?? '');
	$cs  = $params['CHARSET'] ?? '';

	if ($enc === 'QUOTED-PRINTABLE') {
		$value = preg_replace("/=\n/", '', $value);
		$value = quoted_printable_decode($value);
	}
	if ($cs && strtoupper($cs) !== 'UTF-8') {
		if (function_exists('mb_convert_encoding')) {
			$value = @mb_convert_encoding($value, 'UTF-8', $cs);
		} elseif (function_exists('iconv')) {
			$v = @iconv($cs, 'UTF-8//IGNORE', $value);
			if ($v !== false) $value = $v;
		}
	}
	// vCard Escapes
	$value = str_replace(['\\n', '\\N', '\\,', '\\;', '\\\\'], ["\n", "\n", ',', ';', '\\'], $value);

	return trim($value);
}

/** BDAY → YYYY-MM-DD */
function cmx_normalize_bday(string $raw): string {
	$raw = trim($raw);
	if ($raw === '') return '';
	if (preg_match('/^\d{8}$/', $raw)) {
		return substr($raw,0,4) . '-' . substr($raw,4,2) . '-' . substr($raw,6,2);
	}
	if (preg_match('/^(\d{4})[-\.\/](\d{2})[-\.\/](\d{2})$/', $raw, $m)) {
		return $m[1] . '-' . $m[2] . '-' . $m[3];
	}
	return '';
}
