<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_FIRMENGRUENDUNG')) {
	\define(__NAMESPACE__ . '\\CMX_KONTAKTE_META_FIRMENGRUENDUNG', '_cmx_kontakte_firmengruendung');
}
if (!\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_GEBURTSDATUM')) {
	\define(__NAMESPACE__ . '\\CMX_KONTAKTE_META_GEBURTSDATUM', '_cmx_kontakte_geburtsdatum');
}


/**
 * NEU: Helper – Kundenkategorie-Taxonomie robust ermitteln
 */
function cmx_kundenkategorie_tax(): string {
	foreach (['kontakte_kategorien','kontakte_kategorie','kundenkategorie','kontakt_kategorie'] as $t) {
		if (\taxonomy_exists($t)) return $t;
	}
	// Fallback auf den wahrscheinlichsten Namen
	return 'kontakte_kategorien';
}

/**
 * NEU: Helper – Stufen-Taxonomie robust ermitteln
 */
function cmx_stufen_tax(): string {
	foreach (['stufen','kontakte_stufen','kontakt_stufen'] as $t) {
		if (\taxonomy_exists($t)) return $t;
	}
	return 'stufen';
}

/* ==========================================================
 * Admin-Columns (Firmenname → Kundenart → Telefon 1 → E-Mail 1 → Karte)
 * + "Datum" entfernen
 * ========================================================== */
\add_filter('manage_edit-kontakte_columns', __NAMESPACE__ . '\\cmx_kontakte_columns');
function cmx_kontakte_columns($columns) {
	$new = [];
	foreach ($columns as $key => $label) {
		if ($key === 'cb') { $new[$key] = $label; continue; }

		if ($key === 'title') {
			$new['title']          = 'Firmenname';
			$new['cmx_kategorie']  = 'Kategorie'; // NEU
			$new['cmx_stufen']     = 'Stufen';    // NEU
			$new['cmx_tel_1']      = 'Telefon 1';
			$new['cmx_email_1']    = 'E-Mail 1';
			$new['cmx_gmaps']      = 'Karte';     // als letzte Spalte
			continue;
		}

		if ($key === 'date') { continue; } // Datum ausblenden
		$new[$key] = $label;
	}

	// Aufräumen (wie gehabt)
	unset($new['cmx_vorname'], $new['cmx_nachname'], $new['cmx_register_rechtsform_taxonomy'], $new['date']);

	// Falls "Karte" durch das obige continue nicht gesetzt wurde, sicherstellen:
	if (!isset($new['cmx_gmaps'])) $new['cmx_gmaps'] = 'Karte';

	// Logo ans Ende anhängen
	$new['cmx_logo'] = 'Logo';

	return $new;
}

/* ==========================================================
 * Flexible Quellen (Kommunikation) – mit NEUER Meta-Speicherlogik zuerst
 * ========================================================== */
function cmx_comm_container_keys(): array {
	$keys = ['cmx_kommunikation', '_cmx_kommunikation', 'kommunikation', 'cmx_kommunikation_data'];
	return (array) \apply_filters('cmx_comm_container_keys', $keys);
}

function cmx_meta_keys_tel_1(): array {
	$c = __NAMESPACE__ . '\\CMX_KONTAKTE_META_TELEFON_1';
	$list = \defined($c) ? [\constant($c)] : [];
	// NEU: direkte Felder zuerst, dann Legacy-Keys
	$list = array_merge($list, [
		'_cmx_telefon_1','cmx_telefon_1','telefon_1','tel_1','phone_1','telefon','tel','phone'
	]);
	return (array) \apply_filters('cmx_kontakte_tel1_meta_keys', $list);
}

function cmx_meta_keys_email_1(): array {
	$c = __NAMESPACE__ . '\\CMX_KONTAKTE_META_EMAIL_1';
	$list = \defined($c) ? [\constant($c)] : [];
	// NEU: direkte Felder zuerst, dann Legacy-Keys
	$list = array_merge($list, [
		'_cmx_email_1','cmx_email_1','email_1','e_mail_1','kontakt_email','email','e_mail','mail'
	]);
	return (array) \apply_filters('cmx_kontakte_email1_meta_keys', $list);
}

function cmx_get_first_meta_value(int $post_id, array $keys): string {
	foreach ($keys as $key) {
		$val = \get_post_meta($post_id, $key, true);
		if ($val !== '' && $val !== null) return is_string($val) ? trim($val) : (string) $val;
	}
	return '';
}

function cmx_comm_array(int $post_id): array {
	foreach (cmx_comm_container_keys() as $meta_key) {
		$raw = \get_post_meta($post_id, $meta_key, true);
		if ($raw === '' || $raw === null) continue;
		if (\is_array($raw)) return $raw;
		if (\is_string($raw) && \is_serialized($raw)) {
			$un = @\maybe_unserialize($raw);
			if (\is_array($un)) return $un;
		}
		if (\is_string($raw)) {
			$try = $raw;
			if (preg_match('~^[A-Za-z0-9+/=]+$~', $raw)) {
				$decoded = base64_decode($raw, true);
				if ($decoded !== false && $decoded !== '') $try = $decoded;
			}
			$decoded = json_decode($try, true);
			if (json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) return $decoded;
		}
	}
	return [];
}

/**
 * NEU: cmx_comm_get liest zuerst neue Einzelfelder (Meta),
 * dann Fallback: Kommunikations-Container, dann Legacy-Metas.
 */
function cmx_comm_get(int $post_id, string $field_key): string {
	// Direkte neue Felder je nach gewünschtem Key:
	if ($field_key === 'telefon_1') {
		$v = cmx_get_first_meta_value($post_id, cmx_meta_keys_tel_1());
		if ($v !== '') return $v;
	}
	if ($field_key === 'email_1') {
		$v = cmx_get_first_meta_value($post_id, cmx_meta_keys_email_1());
		if ($v !== '') return $v;
	}

	// Fallback 1: Container (Array/JSON/serialized)
	$arr = cmx_comm_array($post_id);
	if (isset($arr[$field_key]) && $arr[$field_key] !== '') {
		return is_string($arr[$field_key]) ? trim($arr[$field_key]) : (string) $arr[$field_key];
	}

	// Fallback 2: Legacy-Einzelfelder
	if ($field_key === 'telefon_1') {
		return cmx_get_first_meta_value($post_id, ['telefon_1','tel_1','phone_1','telefon','tel','phone']);
	}
	if ($field_key === 'email_1') {
		return cmx_get_first_meta_value($post_id, ['email_1','e_mail_1','kontakt_email','email','e_mail','mail']);
	}

	return '';
}

/* ==========================================================
 * Rechnungsadresse → formatierter String für Google Maps
 * ========================================================== */
function cmx_billing_address_string(int $post_id): string {
	$streetKey = __NAMESPACE__ . '\\CMX_RECHNUNG_META_STRASSE';
	$zipKey    = __NAMESPACE__ . '\\CMX_RECHNUNG_META_PLZ';
	$cityKey   = __NAMESPACE__ . '\\CMX_RECHNUNG_META_ORT';
	$countryKey= __NAMESPACE__ . '\\CMX_RECHNUNG_META_LAND';

	$street = \defined($streetKey)  ? \get_post_meta($post_id, \constant($streetKey), true)  : '';
	$zip    = \defined($zipKey)     ? \get_post_meta($post_id, \constant($zipKey), true)     : '';
	$city   = \defined($cityKey)    ? \get_post_meta($post_id, \constant($cityKey), true)    : '';
	$country= \defined($countryKey) ? \get_post_meta($post_id, \constant($countryKey), true) : '';

	$parts = array_filter([trim((string)$street), trim((string)$zip).' '.trim((string)$city), trim((string)$country)]);
	$addr  = trim(implode(', ', array_filter($parts, fn($v)=>trim($v) !== ',')));

	if ($addr === '') {
		$candidates = ['cmx_rechnung', 'rechnung', '_cmx_rechnung', 'cmx_billing'];
		foreach ($candidates as $k) {
			$raw = \get_post_meta($post_id, $k, true);
			if (\is_array($raw)) {
				$street = $raw['strasse'] ?? $raw['street'] ?? '';
				$zip    = $raw['plz']     ?? $raw['zip']    ?? '';
				$city   = $raw['ort']     ?? $raw['city']   ?? '';
				$country= $raw['land']    ?? $raw['country']?? '';
				$parts  = array_filter([trim((string)$street), trim((string)$zip).' '.trim((string)$city), trim((string)$country)]);
				$addr   = trim(implode(', ', array_filter($parts, fn($v)=>trim($v) !== ',')));
				if ($addr !== '') break;
			}
		}
	}

	if ($addr === '') {
		$street = cmx_get_first_meta_value($post_id, ['rechnung_strasse','billing_street','strasse','street']);
		$zip    = cmx_get_first_meta_value($post_id, ['rechnung_plz','billing_zip','plz','zip']);
		$city   = cmx_get_first_meta_value($post_id, ['rechnung_ort','billing_city','ort','city']);
		$country= cmx_get_first_meta_value($post_id, ['rechnung_land','billing_country','land','country','Schweiz','Switzerland','CH']);
		$parts  = array_filter([trim($street), trim($zip).' '.trim($city), trim($country)]);
		$addr   = trim(implode(', ', array_filter($parts, fn($v)=>trim($v) !== ',')));
	}

	return $addr;
}

/* ==========================================================
 * Zellinhalte rendern (Kundenart klickbar + Telefon/E-Mail + GMaps)
 * ========================================================== */
\add_action('manage_kontakte_posts_custom_column', __NAMESPACE__ . '\\cmx_kontakte_custom_column', 10, 2);
function cmx_kontakte_custom_column($column, $post_id) {
	if ($column === 'title') {
		echo esc_html(\get_the_title($post_id));
		return;
	}
	if ($column === 'cmx_logo') {
		$local_img = (string) \get_post_meta($post_id, '_cmx_local_image_kontakte_url', true);
		$fallback  = \get_the_post_thumbnail_url($post_id, 'thumbnail');
		$src       = $local_img !== '' ? $local_img : ($fallback ?: '');
		if ($src !== '') {
			$path     = \parse_url($src, PHP_URL_PATH);
			$filename = $path ? basename($path) : ('kontakt-' . (int) $post_id . '.jpg');
			$img_tag  = '<img class="cmx-ac-thumb" src="' . \esc_url($src) . '" alt="" />';
			echo '<a href="' . \esc_url($src) . '" download="' . \esc_attr($filename) . '" title="Logo herunterladen" style="text-decoration:none;">' . $img_tag . '</a>';
		// } else {
		// 	echo '<span class="dashicons dashicons-format-image" style="opacity:0.35;" title="Kein Logo"></span>';
		}
		return;
	}

	// Kategorie (klickbar)
	if ($column === 'cmx_kategorie') {
		$tax   = cmx_kundenkategorie_tax();
		if (!\taxonomy_exists($tax)) { echo ''; return; }

		$terms = \get_the_terms($post_id, $tax);
		if (\is_wp_error($terms) || empty($terms)) { echo ''; return; }

		$links = [];
		foreach ($terms as $t) {
			$url = \add_query_arg(
				['post_type' => 'kontakte', 'filter_kundenkategorie' => (string) $t->term_id],
				admin_url('edit.php')
			);
			$links[] = '<a href="' . esc_url($url) . '" title="Nach &bdquo;'.esc_attr($t->name).'&ldquo; filtern">' . esc_html($t->name) . '</a>';
		}
		echo implode(', ', $links);
		return;
	}

	// Stufen (klickbar)
	if ($column === 'cmx_stufen') {
		$tax   = cmx_stufen_tax();
		if (!\taxonomy_exists($tax)) { echo ''; return; }

		$terms = \get_the_terms($post_id, $tax);
		if (\is_wp_error($terms) || empty($terms)) { echo ''; return; }

		$links = [];
		foreach ($terms as $t) {
			$url = \add_query_arg(
				['post_type' => 'kontakte', 'filter_stufen' => (string) $t->term_id],
				admin_url('edit.php')
			);
			$links[] = '<a href="' . esc_url($url) . '" title="Nach &bdquo;'.esc_attr($t->name).'&ldquo; filtern">' . esc_html($t->name) . '</a>';
		}
		echo implode(', ', $links);
		return;
	}

	// Telefon 1 – jetzt via neue Meta-Keys (Fallbacks integriert)
	if ($column === 'cmx_tel_1') {
		$val = cmx_comm_get($post_id, 'telefon_1');
		if ($val === '') { echo ''; return; }
		printf('<a href="%s">%s</a>', esc_url('tel:' . preg_replace('/\s+/', '', $val)), esc_html($val));
		return;
	}

	// E-Mail 1 – jetzt via neue Meta-Keys (Fallbacks integriert)
	if ($column === 'cmx_email_1') {
		$val = cmx_comm_get($post_id, 'email_1');
		if ($val === '') { echo ''; return; }
		printf('<a href="%s">%s</a>', esc_url('mailto:' . $val), esc_html($val));
		return;
	}

	// Google Maps
	if ($column === 'cmx_gmaps') {
		$addr = cmx_billing_address_string($post_id);
		if ($addr === '') { echo ''; return; }

		$q = rawurlencode($addr);
		$href = 'https://www.google.com/maps/search/?api=1&query='.$q;

		printf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer" class="cmx-gmaps-link" title="%2$s">
				<span class="dashicons dashicons-location-alt" aria-hidden="true"></span>
				<span class="screen-reader-text">%2$s</span>
			</a>',
			esc_url($href),
			esc_attr('In Google Maps öffnen: ' . $addr)
		);
		return;
	}
}

/* ==========================================================
 * Sortierbarkeit: nur Titel
 * ========================================================== */
\add_filter('manage_edit-kontakte_sortable_columns', __NAMESPACE__ . '\\cmx_kontakte_sortable_columns');
function cmx_kontakte_sortable_columns($columns) {
	return ['title' => 'title'];
}
\add_action('pre_get_posts', __NAMESPACE__ . '\\cmx_kontakte_sort_by_meta');
function cmx_kontakte_sort_by_meta($query) {
	if (!\is_admin() || !$query->is_main_query()) return;
	if ($query->get('post_type') !== 'kontakte') return;
}

/* ==========================================================
 * Datumsfilter (Monate) entfernen
 * ========================================================== */
\add_filter('months_dropdown_results', function ($months, $post_type) {
	return ($post_type === 'kontakte') ? [] : $months;
}, 10, 2);

/* ==========================================================
 * Filter-Dropdown: Kundenart + NEU Kundenkategorie + NEU Stufen (AND)
 * ========================================================== */
\add_action('restrict_manage_posts', __NAMESPACE__ . '\\cmx_kontakte_tax_filters');
function cmx_kontakte_tax_filters() {
	global $typenow;
	if ($typenow !== 'kontakte') return;

	// NEU: Kundenkategorie
	$kundkat_tax = cmx_kundenkategorie_tax();
	cmx_render_tax_filter_dropdown(taxonomy:$kundkat_tax, param:'filter_kundenkategorie', label:'Alle Kategorien');

	// NEU: Stufen
	$stufen_tax = cmx_stufen_tax();
	cmx_render_tax_filter_dropdown(taxonomy:$stufen_tax, param:'filter_stufen', label:'Alle Stufen');
}

function cmx_render_tax_filter_dropdown(string $taxonomy, string $param, string $label) {
	if (!\taxonomy_exists($taxonomy)) return;

	$current = isset($_GET[$param]) ? sanitize_text_field(wp_unslash($_GET[$param])) : '';
	$terms   = \get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
	if (\is_wp_error($terms) || empty($terms)) return;

	$stufen_tax = cmx_stufen_tax(); // für Vergleich

	echo '<label for="'.esc_attr($param).'" class="screen-reader-text">'.esc_html($label).'</label>';
	echo '<select name="'.esc_attr($param).'" id="'.esc_attr($param).'">';
	echo '<option value="">'.esc_html($label).'</option>';

	foreach ($terms as $term) {
		$option_text  = $term->name;

		// Nur bei Stufen: Beschreibung zusätzlich im sichtbaren Label
		if ($taxonomy === $stufen_tax) {
			$desc = is_string($term->description ?? '') ? trim((string)$term->description) : '';
			if ($desc !== '') {
				$desc_short = \wp_strip_all_tags(\wp_trim_words($desc, 16, '…'));
				$option_text .= ' — ' . $desc_short;
			}
		}

		// Immer: vollständige Beschreibung als title-Attribut (Tooltip)
		$title_attr = '';
		if (!empty($term->description)) {
			$title_attr = ' title="'.esc_attr(\wp_strip_all_tags((string)$term->description)).'"';
		}

		printf(
			'<option value="%1$s"%2$s%4$s>%3$s</option>',
			esc_attr((string)$term->term_id),
			selected($current, (string)$term->term_id, false),
			esc_html($option_text),
			$title_attr
		);
	}
	echo '</select> ';
}

\add_action('pre_get_posts', __NAMESPACE__ . '\\cmx_kontakte_apply_tax_filters');
function cmx_kontakte_apply_tax_filters($query) {
	if (!\is_admin() || !$query->is_main_query()) return;
	if ($query->get('post_type') !== 'kontakte') return;

	$tax_query = $query->get('tax_query');
	if (!is_array($tax_query)) $tax_query = [];

	// Kundenart (kontakt_type | kundenart)
	if (!empty($_GET['filter_kontakt_type'])) {
		$kundenart_tax = cmx_kundenart_tax();
		$term_id = (int) sanitize_text_field(wp_unslash($_GET['filter_kontakt_type']));
		$tax_query[] = [
			'taxonomy'         => $kundenart_tax,
			'field'            => 'term_id',
			'terms'            => [$term_id],
			'include_children' => false,
		];
	}

	// NEU: Kundenkategorie
	if (!empty($_GET['filter_kundenkategorie'])) {
		$kundkat_tax = cmx_kundenkategorie_tax();
		$term_id = (int) sanitize_text_field(wp_unslash($_GET['filter_kundenkategorie']));
		$tax_query[] = [
			'taxonomy'         => $kundkat_tax,
			'field'            => 'term_id',
			'terms'            => [$term_id],
			'include_children' => false,
		];
	}

	// NEU: Stufen
	if (!empty($_GET['filter_stufen'])) {
		$stufen_tax = cmx_stufen_tax();
		$term_id = (int) sanitize_text_field(wp_unslash($_GET['filter_stufen']));
		$tax_query[] = [
			'taxonomy'         => $stufen_tax,
			'field'            => 'term_id',
			'terms'            => [$term_id],
			'include_children' => false,
		];
	}

	if (!empty($tax_query)) {
		$query->set('tax_query', array_merge(['relation' => 'AND'], $tax_query));
	}
}

/* ==========================================================
 * Kleines Styling nur für die Karten-Spalte
 * ========================================================== */
\add_action('admin_head', function () {
	$screen = \get_current_screen();
	if (!$screen || $screen->post_type !== 'kontakte') return;

	echo '<style>
		.cmx-ac-thumb {
			width:50px;
			height:50px;
			object-fit: contain;
			background:#fff;
			border:1px solid #e6e6e6;
			border-radius:6px;
			box-shadow:0 2px 6px rgba(0,0,0,0.08);
			display:inline-block;
		}
		.column-cmx_gmaps { width:56px; text-align:center; }
		.column-cmx_gmaps .dashicons { font-size:20px; width:20px; height:20px; line-height:20px; }
		.column-cmx_gmaps a.cmx-gmaps-link { display:inline-block; padding:2px; }
	</style>';
	\wp_enqueue_style('dashicons');
});

\add_action('admin_head', function () {
	$screen = \get_current_screen();
	if (!$screen || $screen->post_type !== 'kontakte') return;
	$stufen_param = 'filter_stufen';
	echo '<style>select#'.esc_attr($stufen_param).' { max-width:120px; } </style>';
});


/**
 * ------------------------------------------------------------
 * Admin Columns: "Hersteller URL" + Firmengründung + Geburtsdatum hinzufügen
 * Reihenfolge: … [URL] → Hersteller URL → Firmengründung → Geburtsdatum → … (Fallback: vor "Karte"; sonst ans Ende)
 * ------------------------------------------------------------
 */
\add_filter('manage_edit-kontakte_columns', __NAMESPACE__ . '\\cmx_kontakte_add_columns');
function cmx_kontakte_add_columns(array $columns): array {
	$new = [];
	$logo_label = null;
	$inserted_after_url = false;

	foreach ($columns as $key => $label) {
		// Original übernehmen
		if ($key === 'cmx_logo') {
			$logo_label = $label;
			continue;
		}
		$new[$key] = $label;
	}

	// Falls weder URL noch Karte existiert: ans Ende anhängen
	if (!$inserted_after_url && !isset($new['cmx_hersteller_url'])) {
		$new['cmx_hersteller_url'] = 'URL';
	}
	if (!isset($new['cmx_firmengruendung'])) $new['cmx_firmengruendung'] = 'Firmengründung';
	if (!isset($new['cmx_geburtsdatum'])) $new['cmx_geburtsdatum'] = 'Geburtsdatum';

	if ($logo_label !== null) {
		$new['cmx_logo'] = $logo_label; // Logo ans Ende
	}

	return $new;
}

/**
 * ------------------------------------------------------------
 * Spalten-Inhalte rendern
 * ------------------------------------------------------------
 */
\add_action('manage_kontakte_posts_custom_column', __NAMESPACE__ . '\\cmx_kontakte_render_custom_columns', 10, 2);
function cmx_kontakte_render_custom_columns(string $column, int $post_id): void {
	if ($column === 'cmx_hersteller_url') {
		$raw  = (string) \get_post_meta($post_id, CMX_KONTAKTE_META_URL, true);
		if ($raw === '') { echo ''; return; }

		$href = cmx_normalize_url_for_href($raw);
		$disp = cmx_domain_core_from_url($raw);
		echo '<a href="' . \esc_url($href) . '" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-admin-site" style="font-size:14px;opacity:0.8"></span></a>';
		return;
	}

	if ($column === 'cmx_firmengruendung' || $column === 'cmx_geburtsdatum') {
		$meta_key = ($column === 'cmx_firmengruendung')
			? CMX_KONTAKTE_META_FIRMENGRUENDUNG
			: CMX_KONTAKTE_META_GEBURTSDATUM;
		$raw = (string) \get_post_meta($post_id, $meta_key, true);
		if ($raw === '') {
			$raw = (string) \get_post_meta($post_id, CMX_KONTAKTE_META_DATUM, true);
		}
		if ($raw === '') { echo ''; return; }

		$dt = \DateTime::createFromFormat('Y-m-d', $raw);
		if ($dt && $dt->format('Y-m-d') === $raw) {
			echo \esc_html($dt->format('d.m.Y'));
		} else {
			echo \esc_html($raw);
		}
		return;
	}
}

\add_action('pre_get_posts', __NAMESPACE__ . '\\cmx_kontakte_orderby_columns');
function cmx_kontakte_orderby_columns(\WP_Query $query): void {
	if (!\is_admin() || !$query->is_main_query()) return;

	$orderby = $query->get('orderby');
	if ($orderby === 'cmx_hersteller_url') {
		$query->set('meta_key', CMX_KONTAKTE_META_URL);
		$query->set('orderby', 'meta_value');
	}
	if ($orderby === 'cmx_firmengruendung') {
		$query->set('meta_key', CMX_KONTAKTE_META_FIRMENGRUENDUNG);
		$query->set('orderby', 'meta_value');
	}
	if ($orderby === 'cmx_geburtsdatum') {
		$query->set('meta_key', CMX_KONTAKTE_META_GEBURTSDATUM);
		$query->set('orderby', 'meta_value');
	}
}

function cmx_kontakte_search_email_meta_keys(): array {
	$keys = [
		'_cmx_email_1', '_cmx_email_2', '_cmx_email_3',
		'cmx_email_1', 'cmx_email_2', 'cmx_email_3',
		'email_1', 'email_2', 'email_3',
		'e_mail_1', 'e_mail_2', 'e_mail_3',
		'kontakt_email', 'email', 'e_mail', 'mail',
		'_cmx_kommunikation', 'cmx_kommunikation', 'kommunikation',
	];

	return \array_values(\array_unique(\array_filter(\array_map('strval', $keys))));
}

\add_action('pre_get_posts', __NAMESPACE__ . '\\cmx_kontakte_extend_admin_search', 20);
function cmx_kontakte_extend_admin_search(\WP_Query $query): void {
	if (!\is_admin() || !$query->is_main_query()) {
		return;
	}
	if ((string) $query->get('post_type') !== 'kontakte') {
		return;
	}
	if ((bool) $query->get('cmx_kontakte_search_lookup')) {
		return;
	}

	$search_term = \trim((string) $query->get('s'));
	if ($search_term === '') {
		return;
	}

	$lookup_args = [
		'post_type' => 'kontakte',
		'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
		'orderby' => 'ID',
		'order' => 'ASC',
		'cmx_kontakte_search_lookup' => true,
	];

	$default_match_ids = \get_posts(\array_merge($lookup_args, [
		's' => $search_term,
	]));

	$email_meta_query = ['relation' => 'OR'];
	foreach (cmx_kontakte_search_email_meta_keys() as $meta_key) {
		$email_meta_query[] = [
			'key' => $meta_key,
			'value' => $search_term,
			'compare' => 'LIKE',
		];
	}

	$email_match_ids = \get_posts(\array_merge($lookup_args, [
		's' => '',
		'meta_query' => $email_meta_query,
	]));

	$matched_ids = \array_values(\array_unique(\array_map('intval', \array_merge(
		(array) $default_match_ids,
		(array) $email_match_ids
	))));

	$query->set('s', '');
	$query->set('post__in', empty($matched_ids) ? [0] : $matched_ids);
}
