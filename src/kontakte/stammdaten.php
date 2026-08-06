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
	foreach ([CMX_KONTAKTE_META_VORNAME, CMX_KONTAKTE_META_NACHNAME, CMX_KONTAKTE_META_ANREDE, CMX_KONTAKTE_META_FIRMA, CMX_KONTAKTE_META_MUH, CMX_KONTAKTE_META_KUNDEN_NR] as $key) {
		\register_post_meta('kontakte', $key, [
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => fn() => \current_user_can('edit_posts'),
		]);
	}
	\register_post_meta('kontakte', CMX_KONTAKTE_META_HR_UID, [
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => __NAMESPACE__ . '\\cmx_format_hr_uid',
		'auth_callback'     => fn() => \current_user_can('edit_posts'),
	]);
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

function cmx_format_hr_uid($value): string {
	$raw = \sanitize_text_field((string) $value);
	$raw = \trim($raw);
	if ($raw === '') {
		return '';
	}
	if (\strncasecmp($raw, 'CHE', 3) !== 0) {
		return $raw;
	}
	$digits = (string) \preg_replace('/\D+/', '', \substr($raw, 3));
	if (\strlen($digits) === 9) {
		return 'CHE-' . \substr($digits, 0, 3) . '.' . \substr($digits, 3, 3) . '.' . \substr($digits, 6, 3);
	}
	return \strtoupper($raw);
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_first_beleg_created_date')) {
	function cmx_kontakt_beleg_contact_id(int $beleg_id): int {
		if ($beleg_id <= 0) {
			return 0;
		}
		$kontakt_keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID')) {
			$kontakt_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID');
		}
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT')) {
			$kontakt_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT');
		}
		$kontakt_keys = \array_values(\array_unique(\array_filter(\array_merge($kontakt_keys, ['_cmx_beleg_kontakt_id', 'cmx_beleg_kontakt_id']))));
		foreach ($kontakt_keys as $meta_key) {
			$kontakt_id = (int) \get_post_meta($beleg_id, $meta_key, true);
			if ($kontakt_id > 0 && (string) \get_post_type($kontakt_id) === 'kontakte') {
				return $kontakt_id;
			}
		}
		return 0;
	}

	function cmx_kontakt_beleg_date(int $beleg_id): string {
		if ($beleg_id <= 0) {
			return '';
		}
		foreach (['_cmx_beleg_rng_datum', '_cmx_rechnungsdatum', '_invoice_date', '_date'] as $meta_key) {
			$raw = \trim((string) \get_post_meta($beleg_id, $meta_key, true));
			if ($raw === '') {
				continue;
			}
			$ts = \strtotime($raw);
			if ($ts) {
				return \date('Y-m-d', $ts);
			}
		}

		$post_date = (string) \get_post_field('post_date', $beleg_id);
		return \preg_match('/^\d{4}-\d{2}-\d{2}/', $post_date) ? \substr($post_date, 0, 10) : '';
	}

	function cmx_kontakt_beleg_is_customer_invoice(int $beleg_id): bool {
		if ($beleg_id <= 0 || (string) \get_post_type($beleg_id) !== 'belege') {
			return false;
		}

		$terms = [];
		foreach ((array) \get_object_taxonomies('belege', 'names') as $taxonomy) {
			$post_terms = \wp_get_post_terms($beleg_id, (string) $taxonomy, ['fields' => 'slugs']);
			if (!\is_wp_error($post_terms)) {
				$terms = \array_merge($terms, (array) $post_terms);
			}
		}
		$terms = \array_values(\array_unique(\array_map('sanitize_key', $terms)));
		if (\array_intersect($terms, ['lieferantenrechnung', 'lieferantenquittung', 'ausgabe', 'ausgaben', 'expense', 'expenses'])) {
			return false;
		}

		$raw_direction = \sanitize_key((string) \get_post_meta($beleg_id, '_cmx_beleg_richtung', true));
		if (\in_array($raw_direction, ['eingang', 'ausgabe', 'ausgaben', 'expense', 'expenses', 'supplier'], true)) {
			return false;
		}

		$raw_type = \strtolower((string) \get_post_meta($beleg_id, '_cmx_beleg_typ', true));
		if (\str_contains($raw_type, 'liefer') || \str_contains($raw_type, 'ausgabe')) {
			return false;
		}

		return true;
	}

	function cmx_kontakt_first_beleg_created_date(int $kontakt_id): string {
		if ($kontakt_id <= 0) {
			return '';
		}
		if (!\post_type_exists('belege')) {
			return '';
		}

		$kontakt_keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID')) {
			$kontakt_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID');
		}
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT')) {
			$kontakt_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT');
		}
		$kontakt_keys = \array_values(\array_unique(\array_filter(\array_merge($kontakt_keys, ['_cmx_beleg_kontakt_id', 'cmx_beleg_kontakt_id']))));

		$meta_query = ['relation' => 'OR'];
		foreach ($kontakt_keys as $meta_key) {
			$meta_query[] = [
				'key' => $meta_key,
				'value' => $kontakt_id,
				'compare' => '=',
				'type' => 'NUMERIC',
			];
		}

		$ids = \get_posts([
			'post_type' => 'belege',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'meta_query' => $meta_query,
		]);

		$dates = [];
		foreach ((array) $ids as $beleg_id) {
			$beleg_id = (int) $beleg_id;
			if (!cmx_kontakt_beleg_is_customer_invoice($beleg_id)) {
				continue;
			}
			$date = cmx_sanitize_date_ymd(cmx_kontakt_beleg_date($beleg_id));
			if ($date !== '') {
				$dates[] = $date;
			}
		}

		if ($dates === []) {
			return '';
		}
		\sort($dates, SORT_STRING);
		return (string) $dates[0];
	}

	function cmx_kontakt_fill_kunde_seit_from_beleg(int $beleg_id): void {
		$kontakt_id = cmx_kontakt_beleg_contact_id($beleg_id);
		if ($kontakt_id <= 0 || !cmx_kontakt_beleg_is_customer_invoice($beleg_id)) {
			return;
		}

		$current = cmx_sanitize_date_ymd((string) \get_post_meta($kontakt_id, CMX_KONTAKTE_META_KUNDE_SEIT, true));
		if ($current !== '') {
			return;
		}

		$date = cmx_sanitize_date_ymd(cmx_kontakt_first_beleg_created_date($kontakt_id));
		if ($date === '') {
			$date = cmx_sanitize_date_ymd(cmx_kontakt_beleg_date($beleg_id));
		}
		if ($date !== '') {
			\update_post_meta($kontakt_id, CMX_KONTAKTE_META_KUNDE_SEIT, $date);
		}
	}
}

\add_action('save_post_belege', function (int $post_id, \WP_Post $post, bool $update): void {
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) {
		return;
	}
	cmx_kontakt_fill_kunde_seit_from_beleg($post_id);
}, 30, 3);

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
		$first_beleg_date = cmx_kontakt_first_beleg_created_date($kontakt_id);
		if ($first_beleg_date !== '') {
			\update_post_meta($kontakt_id, CMX_KONTAKTE_META_KUNDE_SEIT, $first_beleg_date);
		}
		return $first_beleg_date;
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
	$hr_uid   = cmx_format_hr_uid((string) \get_post_meta($post->ID, CMX_KONTAKTE_META_HR_UID, true));
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
	@media (min-width: 1201px) and (max-width: 1500px) {
		#post-body.columns-2 #cmx-stammdaten .grid {
			grid-template-columns:repeat(3, minmax(150px, 1fr));
			row-gap:12px;
		}
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
	echo <<<'HTML'
<script>
(function(){
	var input = document.getElementById("cmx_hr_uid");
	if (!input) {
		return;
	}

	function formatHrUid(value) {
		var raw = String(value || "");
		var trimmed = raw.trim();
		if (trimmed.slice(0, 3).toUpperCase() !== "CHE") {
			return raw;
		}
		var digits = trimmed.slice(3).replace(/\D+/g, "").slice(0, 9);
		var parts = [];
		if (digits.length > 0) {
			parts.push(digits.slice(0, 3));
		}
		if (digits.length > 3) {
			parts.push(digits.slice(3, 6));
		}
		if (digits.length > 6) {
			parts.push(digits.slice(6, 9));
		}
		return parts.length ? "CHE-" + parts.join(".") : "CHE";
	}

	function applyFormat() {
		input.value = formatHrUid(input.value);
	}

	input.addEventListener("input", applyFormat);
	input.addEventListener("blur", applyFormat);
})();
</script>
HTML;
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
		\update_post_meta($post_id, CMX_KONTAKTE_META_HR_UID, cmx_format_hr_uid((string) \wp_unslash($_POST['cmx_hr_uid'])));
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
