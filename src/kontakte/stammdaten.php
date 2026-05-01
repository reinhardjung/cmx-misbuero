<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * ------------------------------------------------------------
 * Konstanten / Metaschlüssel
 * ------------------------------------------------------------
 */
const CMX_KONTAKTE_META_VORNAME  = '_cmx_kontakte_vorname';
const CMX_KONTAKTE_META_NACHNAME = '_cmx_kontakte_nachname';
const CMX_KONTAKTE_META_ANREDE   = '_cmx_kontakte_anrede';
const CMX_KONTAKTE_META_FIRMA    = '_cmx_kontakte_firma';
const CMX_KONTAKTE_META_URL      = '_cmx_kontakte_url';
const CMX_KONTAKTE_META_HR_UID   = '_cmx_kontakte_hr_uid';
const CMX_KONTAKTE_META_MUH      = '_cmx_kontakte_muh';
const CMX_KONTAKTE_META_PRIVAT   = '_cmx_kontakte_privat';
const CMX_KONTAKTE_META_KUNDEN_NR = '_cmx_kontakte_kunden_nr';
const CMX_KONTAKTE_META_DATUM    = '_cmx_kontakte_datum';
const CMX_KONTAKTE_META_FIRMENGRUENDUNG = '_cmx_kontakte_firmengruendung';
const CMX_KONTAKTE_META_GEBURTSDATUM    = '_cmx_kontakte_geburtsdatum';
const CMX_KONTAKTE_META_KUNDE_SEIT      = '_cmx_kontakte_kunde_seit';

/**
 * ------------------------------------------------------------
 * Metas registrieren
 * ------------------------------------------------------------
 */
\add_action('init', __NAMESPACE__ . '\\cmx_register_kontakte_stammdaten_meta');
function cmx_register_kontakte_stammdaten_meta() {
	// Text
	foreach ([CMX_KONTAKTE_META_VORNAME, CMX_KONTAKTE_META_NACHNAME, CMX_KONTAKTE_META_ANREDE, CMX_KONTAKTE_META_FIRMA, CMX_KONTAKTE_META_HR_UID, CMX_KONTAKTE_META_MUH, CMX_KONTAKTE_META_KUNDEN_NR] as $key) {
		\register_post_meta('kontakte', $key, [
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => fn() => \current_user_can('edit_posts'),
		]);
	}
	// URL
	\register_post_meta('kontakte', CMX_KONTAKTE_META_URL, [
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'esc_url_raw',
		'auth_callback'     => fn() => \current_user_can('edit_posts'),
	]);
	// PRIVAT (bool)
	\register_post_meta('kontakte', CMX_KONTAKTE_META_PRIVAT, [
		'type'              => 'boolean',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => fn($v) => (bool)$v,
		'auth_callback'     => fn() => \current_user_can('edit_posts'),
	]);
	// DATUM (YYYY-MM-DD)
	\register_post_meta('kontakte', CMX_KONTAKTE_META_DATUM, [
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => __NAMESPACE__ . '\\cmx_sanitize_date_ymd',
		'auth_callback'     => fn() => \current_user_can('edit_posts'),
	]);
	// Firmengründung (YYYY-MM-DD)
	\register_post_meta('kontakte', CMX_KONTAKTE_META_FIRMENGRUENDUNG, [
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => __NAMESPACE__ . '\\cmx_sanitize_date_ymd',
		'auth_callback'     => fn() => \current_user_can('edit_posts'),
	]);
	// Geburtsdatum (YYYY-MM-DD)
	\register_post_meta('kontakte', CMX_KONTAKTE_META_GEBURTSDATUM, [
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => __NAMESPACE__ . '\\cmx_sanitize_date_ymd',
		'auth_callback'     => fn() => \current_user_can('edit_posts'),
	]);
	// Kunde seit (YYYY-MM-DD)
	\register_post_meta('kontakte', CMX_KONTAKTE_META_KUNDE_SEIT, [
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => __NAMESPACE__ . '\\cmx_sanitize_date_ymd',
		'auth_callback'     => fn() => \current_user_can('edit_posts'),
	]);
}

/** Sanitizer: exakt YYYY-MM-DD, sonst '' */
function cmx_sanitize_date_ymd($v): string {
	$v = \is_string($v) ? \trim($v) : '';
	if ($v === '') return '';
	$dt = \DateTime::createFromFormat('Y-m-d', $v);
	return ($dt && $dt->format('Y-m-d') === $v) ? $v : '';
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_first_beleg_created_date')) {
	function cmx_kontakt_first_beleg_created_date(int $kontakt_id): string {
		if ($kontakt_id <= 0) {
			return '';
		}
		global $wpdb;
		if (!($wpdb instanceof \wpdb)) {
			return '';
		}

		$sql = $wpdb->prepare(
			"SELECT DATE(MIN(p.post_date))
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			WHERE p.post_type = 'belege'
			  AND p.post_status NOT IN ('trash','auto-draft','inherit')
			  AND pm.meta_key = %s
			  AND pm.meta_value = %d",
			'_cmx_beleg_kontakt_id',
			$kontakt_id
		);
		$raw = \is_string($sql) ? (string) $wpdb->get_var($sql) : '';
		return cmx_sanitize_date_ymd($raw);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_kunde_seit_value')) {
	function cmx_kontakt_kunde_seit_value(int $kontakt_id): string {
		if ($kontakt_id <= 0) {
			return '';
		}
		$saved = (string) \get_post_meta($kontakt_id, CMX_KONTAKTE_META_KUNDE_SEIT, true);
		$saved = cmx_sanitize_date_ymd($saved);
		if ($saved !== '') {
			return $saved;
		}
		return cmx_kontakt_first_beleg_created_date($kontakt_id);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_business_form_taxonomy')) {
	function cmx_kontakte_business_form_taxonomy(): string {
		$candidates = [
			'kontakte_geschaeftsform',
			'kontakte_geschaeftsformen',
			'geschaeftsform',
			'geschaeftsformen',
			'kontakte_rechtsform',
			'kontakte_rechtsformen',
			'rechtsform',
			'rechtsformen',
		];

		$object_taxes = (array) \get_object_taxonomies('kontakte', 'names');
		foreach ($object_taxes as $taxonomy) {
			$taxonomy = (string) $taxonomy;
			if ($taxonomy === '') {
				continue;
			}
			$normalized = \strtolower(\str_replace('_', '', \function_exists(__NAMESPACE__ . '\\cmx_no_umlaute') ? cmx_no_umlaute($taxonomy) : $taxonomy));
			if (\strpos($normalized, 'geschaeftsform') !== false || \strpos($normalized, 'rechtsform') !== false) {
				$candidates[] = $taxonomy;
			}
		}

		foreach (\array_values(\array_unique($candidates)) as $taxonomy) {
			if (\taxonomy_exists($taxonomy)) {
				return $taxonomy;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_business_form_label_html')) {
	function cmx_kontakte_business_form_label_html(string $label = 'Form'): string {
		$taxonomy = cmx_kontakte_business_form_taxonomy();
		if ($taxonomy === '') {
			return \esc_html($label);
		}

		$url = \admin_url('edit-tags.php?taxonomy=' . \rawurlencode($taxonomy) . '&post_type=kontakte');
		return '<a href="' . \esc_url($url) . '" target="_blank" rel="noopener noreferrer" style="color:inherit;text-decoration:none;font:inherit;font-size:inherit;font-weight:inherit;line-height:inherit;">' . \esc_html($label) . '</a>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_business_form_terms')) {
	function cmx_kontakte_business_form_terms(): array {
		$taxonomy = cmx_kontakte_business_form_taxonomy();
		if ($taxonomy === '') {
			return [];
		}

		$terms = \get_terms([
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]);

		return \is_wp_error($terms) ? [] : (array) $terms;
	}
}

/**
 * ------------------------------------------------------------
 * Metabox registrieren
 * ------------------------------------------------------------
 */
\add_action('add_meta_boxes', __NAMESPACE__ . '\\cmx_add_stammdaten_metabox');
function cmx_add_stammdaten_metabox() {
	\add_meta_box(
		'cmx_kontakte_stammdaten',
		'Stammdaten',
		__NAMESPACE__ . '\\cmx_render_stammdaten_metabox',
		'kontakte',
		'normal',
		'core'
	);
}

/**
 * ------------------------------------------------------------
 * Metabox-Ausgabe
 * ------------------------------------------------------------
 */
function cmx_render_stammdaten_metabox(\WP_Post $post) {
	\wp_nonce_field('cmx_kontakte_save_meta', 'cmx_kontakte_nonce');

	$firma_meta_exists = \metadata_exists('post', $post->ID, CMX_KONTAKTE_META_FIRMA);
	$firma = (string) \get_post_meta($post->ID, CMX_KONTAKTE_META_FIRMA, true);
	if (!$firma_meta_exists) {
		$firma = \trim((string) ($post->post_title ?? ''));
		$firma_normalized = \strtolower(\str_replace(['_', ' '], '-', $firma));
		if (
			(string) ($post->post_status ?? '') === 'auto-draft'
			|| \strpos($firma_normalized, 'automatisch-gespeicherter-entwurf') !== false
			|| \strpos($firma_normalized, 'auto-draft') !== false
		) {
			$firma = '';
		}
	}
	$kunden_nr = (string) \get_post_meta($post->ID, CMX_KONTAKTE_META_KUNDEN_NR, true);
	$url_raw  = (string) \get_post_meta($post->ID, CMX_KONTAKTE_META_URL, true);
	$hr_uid   = (string) \get_post_meta($post->ID, CMX_KONTAKTE_META_HR_UID, true);
	$muh      = (string) \get_post_meta($post->ID, CMX_KONTAKTE_META_MUH, true);
	$firmengr = (string) \get_post_meta($post->ID, CMX_KONTAKTE_META_FIRMENGRUENDUNG, true);
	$kunde_seit = cmx_kontakt_kunde_seit_value((int) $post->ID);
	$business_form_taxonomy = cmx_kontakte_business_form_taxonomy();
	$business_form_terms = cmx_kontakte_business_form_terms();
	$business_form_term_id = '';
	$assigned_terms = $business_form_taxonomy !== '' ? \get_the_terms($post->ID, $business_form_taxonomy) : false;
	if (!\is_wp_error($assigned_terms) && !empty($assigned_terms)) {
		$first_term = \reset($assigned_terms);
		$business_form_term_id = $first_term ? (string) $first_term->term_id : '';
	}

	// Für das Label (nur Anzeige) https:// ergänzen
	$url_disp = \trim($url_raw);
	if ($url_disp !== '' && !\preg_match('~^https?://~i', $url_disp)) {
		$url_disp = 'https://' . \ltrim($url_disp, '/');
	}
	$hr_uid_search_url = $hr_uid !== '' ? 'https://www.zefix.ch/de/search/entity/list?name=' . \rawurlencode($hr_uid) : '';

	echo '<style>
	body.post-type-kontakte #titlediv,
	body.post-type-kontakte #titlewrap{
		display:block !important;
		visibility:visible !important;
		opacity:1 !important;
		pointer-events:auto !important;
	}
	body.post-type-kontakte #cmx_kontakte_stammdaten,
	body.post-type-kontakte #cmx_kontakte_stammdaten .inside{
		overflow:visible !important;
		position:relative;
		z-index:50;
	}
	body.post-type-kontakte #cmx_kontakte_stammdaten{
		border-radius:12px !important;
		overflow:visible !important;
	}
	body.post-type-kontakte #cmx_kontakte_stammdaten .postbox-header{
		border-radius:12px 12px 0 0 !important;
	}
	body.post-type-kontakte #cmx_kontakte_stammdaten .inside{
		border-radius:0 0 12px 12px !important;
	}
	#cmx-stammdaten{
		position:relative;
		overflow:visible;
	}
	#cmx-stammdaten .grid {
		position:relative;
		overflow:visible;
		display:grid !important;
		grid-template-columns:minmax(210px,1.25fr) minmax(140px,0.9fr) minmax(170px,1.45fr) minmax(160px,1.05fr) minmax(128px,0.92fr) minmax(128px,0.92fr) minmax(90px,0.62fr) max-content 150px;
		column-gap:12px;
		row-gap:0;
		align-items:flex-start;
		width:100%;
	}
	#cmx-stammdaten .field {margin:0; display:block !important; min-width:0;}
	#cmx-stammdaten .field--status{align-self:flex-start;display:flex !important;flex-direction:column;align-items:flex-start;gap:6px;position:relative;overflow:visible;z-index:60}
	#cmx-stammdaten .field--status > label{display:block !important;width:100%;margin:0;text-align:left}
	#cmx-stammdaten .field--status .cmx-status-field-control{display:block;width:100%}
	#cmx-stammdaten .field--status .cmx-kontakt-status-control{display:inline-flex;margin:0}
	#cmx-stammdaten .field--status .cmx-kontakt-status-control.is-open{z-index:1000006}
	#cmx-stammdaten .text,
	#cmx-stammdaten .date,
	#cmx-stammdaten select {width:100% !important; max-width:100% !important}
	#cmx-stammdaten input[readonly]{
		background:#f6f7f7 !important;
		color:#50575e !important;
		border-color:#dcdcde !important;
		opacity:1 !important;
	}
	#cmx-stammdaten .url-label a{text-decoration:none}
	#cmx-stammdaten .url-label a:hover{text-decoration:underline}
	@media (max-width: 1200px) {
		#cmx-stammdaten .grid { grid-template-columns:repeat(2, minmax(220px, 1fr)); row-gap:12px; }
	}
	@media (max-width: 640px) {
		#cmx-stammdaten .grid { display:block !important; }
		#cmx-stammdaten .field { min-width:0; }
		#cmx-stammdaten .field + .field{margin-top:12px;}
	}
</style>';

	echo '<div id="cmx-stammdaten"><div class="grid">';

	echo '<p class="field field--firma">
		<label for="cmx_firma"><strong>Firma</strong></label><br>
		<input id="cmx_firma" name="cmx_firma" type="text" class="text" value="' . \esc_attr($firma) . '">
	</p>';

	echo '<p class="field field--form">
		<label for="cmx_geschaeftsform_term_id"><strong>' . cmx_kontakte_business_form_label_html('Rechtsform') . '</strong></label><br>
		<select id="cmx_geschaeftsform_term_id" name="cmx_geschaeftsform_term_id">
			<option value="">auswählen</option>';
	foreach ($business_form_terms as $term) {
		if (!($term instanceof \WP_Term)) {
			continue;
		}
		echo '<option value="' . \esc_attr((string) $term->term_id) . '"' . \selected($business_form_term_id, (string) $term->term_id, false) . '>' . \esc_html((string) $term->name) . '</option>';
	}
	echo '</select>
	</p>';

	// URL (Label ist Link)
	echo '<p class="field field--url">
		<label class="url-label" for="cmx_url">';
			if ($url_disp !== '') {
				echo '<a href="' . \esc_url($url_disp) . '" target="_blank" rel="noopener noreferrer"><strong>URL</strong></a>';
			} else {
				echo '<strong>URL</strong>';
			}
		echo '	</label><br>
			<input id="cmx_url" name="cmx_url" type="url" class="text" placeholder="https://example.ch" value="' . \esc_attr($url_raw) . '"
				onblur="if(this.value && !/^https?:\\/\\//i.test(this.value)){this.value=\'https://\'+this.value.trim().replace(/^\\/+/ , \'\');}">
		</p>';

	echo '<p class="field field--hr-uid">
		<label class="url-label" for="cmx_hr_uid">';
			if ($hr_uid_search_url !== '') {
				echo '<a href="' . \esc_url($hr_uid_search_url) . '" target="_blank" rel="noopener noreferrer"><strong>HR-UID</strong></a>';
			} else {
				echo '<strong>HR-UID</strong>';
			}
	echo '	</label><br>
		<input id="cmx_hr_uid" name="cmx_hr_uid" type="text" class="text" placeholder="CHE-123.456.789" value="' . \esc_attr($hr_uid) . '">
	</p>';

	echo '<p class="field field--datum field--datum-compact">
	<label for="cmx_firmengruendung"><strong>Firmengründung</strong></label><br>
	<input id="cmx_firmengruendung" name="cmx_firmengruendung" type="date" class="date" value="' . \esc_attr($firmengr) . '">
	</p>';
	echo '<p class="field field--datum field--datum-compact">
	<label for="cmx_kunde_seit"><strong>Kunde seit</strong></label><br>
	<input id="cmx_kunde_seit" name="cmx_kunde_seit" type="date" class="date" value="' . \esc_attr($kunde_seit) . '">
	</p>';
	echo '<p class="field field--muh">
		<label for="cmx_muh"><strong>Muh</strong></label><br>
		<input id="cmx_muh" name="cmx_muh" type="text" class="text" value="' . \esc_attr($muh) . '">
	</p>';
	echo '<div class="field field--status">
		<label for="cmx_status"><strong>Status</strong></label>
		<div class="cmx-status-field-control">';
	if (\function_exists(__NAMESPACE__ . '\\cmx_kontakte_status_control_html')) {
		echo cmx_kontakte_status_control_html((int) $post->ID, [
			'context'    => 'edit',
			'input_name' => 'cmx_status',
			'input_id'   => 'cmx_status',
		]);
	} else {
		echo '<input id="cmx_status" name="cmx_status" type="text" class="text" value="play">';
	}
	echo '	</div>
	</div>';
	echo '<p class="field field--kunden-nr">
		<label for="cmx_kunden_nr"><strong>Kunden Nr</strong></label><br>
		<input id="cmx_kunden_nr" name="cmx_kunden_nr" type="text" class="text" readonly value="' . \esc_attr($kunden_nr) . '">
	</p>';

	echo '</div></div>';
}

/**
 * ------------------------------------------------------------
 * Save-Handler (Metabox speichern)
 * ------------------------------------------------------------
 */
\add_action('save_post_kontakte', __NAMESPACE__ . '\\cmx_save_kontakte_meta');
\add_action('save_post_kontakt',  __NAMESPACE__ . '\\cmx_save_kontakte_meta'); // falls Singular-CPT existiert
function cmx_save_kontakte_meta(int $post_id): void {
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!isset($_POST['cmx_kontakte_nonce']) || !\wp_verify_nonce($_POST['cmx_kontakte_nonce'], 'cmx_kontakte_save_meta')) return;
	if (!\current_user_can('edit_post', $post_id)) return;

	// Kunden Nr (nur beim ersten Speichern setzen)
	$kunden_nr = (string) \get_post_meta($post_id, CMX_KONTAKTE_META_KUNDEN_NR, true);
	if ($kunden_nr === '') {
		$timestamp = (int) \current_time('timestamp');
		$kunden_nr = 'K-' . \date('ymd-His', $timestamp);
		\update_post_meta($post_id, CMX_KONTAKTE_META_KUNDEN_NR, $kunden_nr);
	}

	if (isset($_POST['cmx_firma'])) {
		\update_post_meta($post_id, CMX_KONTAKTE_META_FIRMA, \sanitize_text_field((string) \wp_unslash($_POST['cmx_firma'])));
	}

	// Anrede
	if (isset($_POST['cmx_anrede'])) {
		\update_post_meta($post_id, CMX_KONTAKTE_META_ANREDE, \sanitize_text_field((string) $_POST['cmx_anrede']));
	}

	// HR-UID
	if (isset($_POST['cmx_hr_uid'])) {
		\update_post_meta($post_id, CMX_KONTAKTE_META_HR_UID, \sanitize_text_field((string) $_POST['cmx_hr_uid']));
	}

	if (isset($_POST['cmx_muh'])) {
		\update_post_meta($post_id, CMX_KONTAKTE_META_MUH, \sanitize_text_field((string) \wp_unslash($_POST['cmx_muh'])));
	}

	// URL
	if (isset($_POST['cmx_url'])) {
		$url = \function_exists(__NAMESPACE__ . '\\cmx_normalize_url_for_storage')
			? cmx_normalize_url_for_storage((string) $_POST['cmx_url'])
			: (string) \esc_url_raw((string) $_POST['cmx_url']);
		\update_post_meta($post_id, CMX_KONTAKTE_META_URL, $url);
	}

	if (isset($_POST['cmx_status']) && \function_exists(__NAMESPACE__ . '\\cmx_kontakte_store_status')) {
		cmx_kontakte_store_status($post_id, (string) \wp_unslash($_POST['cmx_status']));
	}

	$business_form_taxonomy = cmx_kontakte_business_form_taxonomy();
	if ($business_form_taxonomy !== '' && isset($_POST['cmx_geschaeftsform_term_id'])) {
		$incoming_term_id = (int) \sanitize_text_field((string) \wp_unslash($_POST['cmx_geschaeftsform_term_id']));
		if ($incoming_term_id > 0) {
			$term = \get_term_by('id', $incoming_term_id, $business_form_taxonomy);
			if ($term && !\is_wp_error($term)) {
				\wp_set_object_terms($post_id, [$incoming_term_id], $business_form_taxonomy, false);
			}
		} else {
			\wp_set_object_terms($post_id, [], $business_form_taxonomy, false);
		}
	}

	// Firmengründung / Kunde seit (YYYY-MM-DD)
	$firmengruendung = isset($_POST['cmx_firmengruendung']) ? (string) $_POST['cmx_firmengruendung'] : '';
	$kunde_seit      = isset($_POST['cmx_kunde_seit']) ? (string) $_POST['cmx_kunde_seit'] : '';
	$firmengruendung = cmx_sanitize_date_ymd($firmengruendung);
	$kunde_seit      = cmx_sanitize_date_ymd($kunde_seit);

	if ($firmengruendung === '') {
		\delete_post_meta($post_id, CMX_KONTAKTE_META_FIRMENGRUENDUNG);
	} else {
		\update_post_meta($post_id, CMX_KONTAKTE_META_FIRMENGRUENDUNG, $firmengruendung);
	}
	if ($kunde_seit === '') {
		\delete_post_meta($post_id, CMX_KONTAKTE_META_KUNDE_SEIT);
	} else {
		\update_post_meta($post_id, CMX_KONTAKTE_META_KUNDE_SEIT, $kunde_seit);
	}

	// Legacy-Feld für bestehende Integrationen
	$existing_birth = cmx_sanitize_date_ymd((string) \get_post_meta($post_id, CMX_KONTAKTE_META_GEBURTSDATUM, true));
	$legacy_val = $firmengruendung !== '' ? $firmengruendung : $existing_birth;
	if ($legacy_val === '') {
		\delete_post_meta($post_id, CMX_KONTAKTE_META_DATUM);
	} else {
		\update_post_meta($post_id, CMX_KONTAKTE_META_DATUM, $legacy_val);
	}
}

/**
 * ------------------------------------------------------------
 * Helper: URL normalisieren + Domain ermitteln (für Admin-Column)
 * ------------------------------------------------------------
 */
function cmx_normalize_url_for_href(?string $url): string {
	$url = \trim((string)$url);
	if ($url === '') return '';
	if (!\preg_match('~^https?://~i', $url)) {
		$url = 'https://' . \ltrim($url, '/');
	}
	return $url;
}

function cmx_normalize_url_for_storage(?string $url): string {
	$url = \trim((string) $url);
	if ($url === '') {
		return '';
	}
	if (!\preg_match('~^https?://~i', $url)) {
		$url = 'https://' . \ltrim($url, '/');
	}
	$parts = \wp_parse_url($url);
	if (\is_array($parts) && !empty($parts['host'])) {
		$parts['host'] = (string) \preg_replace('~^www\.~i', '', (string) $parts['host']);
		$rebuilt = '';
		if (!empty($parts['scheme'])) {
			$rebuilt .= $parts['scheme'] . '://';
		}
		if (!empty($parts['user'])) {
			$rebuilt .= $parts['user'];
			if (\array_key_exists('pass', $parts) && $parts['pass'] !== '') {
				$rebuilt .= ':' . $parts['pass'];
			}
			$rebuilt .= '@';
		}
		$rebuilt .= (string) $parts['host'];
		if (!empty($parts['port'])) {
			$rebuilt .= ':' . (string) $parts['port'];
		}
		$rebuilt .= (string) ($parts['path'] ?? '');
		if (isset($parts['query']) && $parts['query'] !== '') {
			$rebuilt .= '?' . $parts['query'];
		}
		if (isset($parts['fragment']) && $parts['fragment'] !== '') {
			$rebuilt .= '#' . $parts['fragment'];
		}
		$url = $rebuilt;
	}
	if ($url !== 'https://' && $url !== 'http://') {
		$url = \preg_replace('~/+$~', '', $url) ?? $url;
	}
	return (string) \esc_url_raw($url);
}

/** Ursprungsdomain (ohne www, mit TLD-Intelligenz) */
function cmx_domain_core_from_url(string $url): string {
	$url = \trim($url);
	if ($url === '') return '';

	if (!\preg_match('~^https?://~i', $url)) {
		$url = 'https://' . \ltrim($url, '/');
	}

	$host = (string)(\parse_url($url, PHP_URL_HOST) ?? '');
	if ($host === '') return '';

	// IDN → ASCII (für Umlaute)
	if (\function_exists('idn_to_ascii')) {
		$ascii = @\idn_to_ascii($host, 0, \defined('INTL_IDNA_VARIANT_UTS46') ? \INTL_IDNA_VARIANT_UTS46 : 0);
		if ($ascii) $host = $ascii;
	}

	$host   = \preg_replace('~^www\.~i', '', $host);
	$labels = \array_values(\array_filter(\explode('.', \strtolower($host))));
	$n      = \count($labels);
	if ($n === 0) return '';

	$twoPart = [
		'co.uk','org.uk','ac.uk','gov.uk',
		'com.au','net.au','org.au','gov.au',
		'co.jp','ne.jp','or.jp',
		'com.br','com.ar','com.mx','com.tr',
		'co.nz','org.nz'
	];

	if ($n >= 3) {
		$lastTwo = $labels[$n-2] . '.' . $labels[$n-1];
		if (\in_array($lastTwo, $twoPart, true)) return $labels[$n-3];
	}
	if ($n >= 2) return $labels[$n-2];
	return $labels[0];
}
