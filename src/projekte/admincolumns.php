<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * CPT: projekte – Admin-Liste & Meta-Box "Projektzeitraum"
 * - Spalten: Kunde, Kategorie, Beginn, Ende
 * - Sortierung: Kunde (meta_value_num), Beginn/Ende (DATE)
 * - Filter: Kategorie-Dropdown (Monats-Archiv entfernt)
 * - Meta-Box (SIDE): Beginn & Ende, Speicherung YYYY-MM-DD
 */

/** =========================
 *  Konfiguration
 * ========================= */
if (!defined('CMX_PROJEKT_CPT'))  define('CMX_PROJEKT_CPT',  'projekte');
if (!defined('CMX_KONTAKT_META')) define('CMX_KONTAKT_META', '_cmx_projekt_kontakt_id');
if (!defined('CMX_PROJEKT_TAX'))  define('CMX_PROJEKT_TAX', 'projekt_kategorie'); // '' = Auto-Detect

// Zeitraum-Meta
if (!defined('CMX_PROJ_BEG_META'))  define('CMX_PROJ_BEG_META',  '_cmx_projekt_beginn'); // YYYY-MM-DD
if (!defined('CMX_PROJ_END_META'))  define('CMX_PROJ_END_META',  '_cmx_projekt_ende');   // YYYY-MM-DD
if (!defined('CMX_PROJ_NONCE_KEY')) define('CMX_PROJ_NONCE_KEY', 'cmx_proj_zeitraum_nonce');

/** =========================
 *  Helpers
 * ========================= */

/** Taxonomie für Projekte ermitteln (Preferenzen: feste Tax, sonst public+hierarchical, sonst public, sonst erste) */
function cmx_projekte_detect_taxonomy(): ?string {
	$cpt = CMX_PROJEKT_CPT;
	$preferred = trim((string) CMX_PROJEKT_TAX);

	if ($preferred !== '' && taxonomy_exists($preferred) && is_object_in_taxonomy($cpt, $preferred)) {
		return $preferred;
	}
	$taxes = get_object_taxonomies($cpt, 'objects');
	if (empty($taxes)) return null;

	foreach ($taxes as $slug => $obj) {
		if (!empty($obj->public) && !empty($obj->hierarchical)) return $slug;
	}
	foreach ($taxes as $slug => $obj) {
		if (!empty($obj->public)) return $slug;
	}
	foreach ($taxes as $slug => $obj) { return $slug; }
	return null;
}

/** Datum-Helpers (ISO prüfen/säubern + CH-Format) */
if (!function_exists(__NAMESPACE__ . '\cmx_proj_is_iso_date')) {
	function cmx_proj_is_iso_date($value) {
		return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
	}
}
if (!function_exists(__NAMESPACE__ . '\cmx_proj_sanitize_date')) {
	function cmx_proj_sanitize_date($value) {
		$value = trim((string)$value);
		if ($value === '') return '';
		if (!cmx_proj_is_iso_date($value)) return '';
		[$y,$m,$d] = array_map('intval', explode('-', $value));
		return checkdate($m, $d, $y) ? $value : '';
	}
}
if (!function_exists(__NAMESPACE__ . '\cmx_format_ch_date')) {
	function cmx_format_ch_date($yyyy_mm_dd) {
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$yyyy_mm_dd)) return '';
		$ts = strtotime($yyyy_mm_dd . ' 00:00:00');
		return $ts ? date('d.m.Y', $ts) : '';
	}
}

/** =========================
 *  Admin-Liste: Kunde & Kategorie
 * ========================= */

/** Spalten hinzufügen & Datum-Spalte entfernen */
add_filter("manage_" . CMX_PROJEKT_CPT . "_posts_columns", function(array $columns) {
	unset($columns['date']); // Standard-Datum ausblenden

	$new = [];
	foreach ($columns as $key => $label) {
		$new[$key] = $label;
		if ($key === 'title') {
			$new['cmx_kunde']     = __('Kunde', 'cmx');
			$new['cmx_kategorie'] = __('Kategorie', 'cmx');
		}
	}
	if (!isset($columns['title'])) {
		$new['cmx_kunde']     = __('Kunde', 'cmx');
		$new['cmx_kategorie'] = __('Kategorie', 'cmx');
	}
	return $new;
}, 20);

/** Spaltenwerte ausgeben */
add_action('manage_' . CMX_PROJEKT_CPT . '_posts_custom_column', function(string $column, int $post_id) {

	if ($column === 'cmx_kunde') {
		$kontakt_id = (int) get_post_meta($post_id, CMX_KONTAKT_META, true);
		if ($kontakt_id > 0 && get_post_status($kontakt_id)) {
			$title = get_the_title($kontakt_id);
			$link  = get_edit_post_link($kontakt_id, '');
			echo $link
				? '<a href="' . esc_url($link) . '">' . esc_html($title) . '</a>'
				: esc_html($title);
		} else {
			echo '';
		}
		return;
	}

	if ($column === 'cmx_kategorie') {
		$tax = cmx_projekte_detect_taxonomy();
		if (!$tax) { echo ''; return; }

		$terms = get_the_terms($post_id, $tax);
		if (empty($terms) || is_wp_error($terms)) { echo ''; return; }

		$out = [];
		foreach ($terms as $t) {
			$url = add_query_arg([
				'post_type' => CMX_PROJEKT_CPT,
				$tax        => $t->slug,
			], admin_url('edit.php'));
			$out[] = '<a href="' . esc_url($url) . '">' . esc_html($t->name) . '</a>';
		}
		echo implode(', ', $out);
		return;
	}

}, 10, 2);

/** Sortierbarkeit: Kunde (nach Kontakt-ID) */
add_filter('manage_edit-' . CMX_PROJEKT_CPT . '_sortable_columns', function(array $cols) {
	$cols['cmx_kunde'] = 'cmx_kunde';
	return $cols;
});

/** A: Monats-Archiv (Alle Daten) entfernen – nur für CPT projekte */
add_filter('months_dropdown_results', function($months, $post_type) {
	if ((string) $post_type === (string) CMX_PROJEKT_CPT) return [];
	return $months;
}, 10, 2);

/** B1: Kategorie-Filter (Dropdown) über der Tabelle */
add_action('restrict_manage_posts', function($post_type) {
	if ($post_type !== CMX_PROJEKT_CPT) return;

	$tax = cmx_projekte_detect_taxonomy();
	if (!$tax || !taxonomy_exists($tax) || !is_object_in_taxonomy(CMX_PROJEKT_CPT, $tax)) return;

	$selected = isset($_GET[$tax]) ? sanitize_text_field(wp_unslash($_GET[$tax])) : '';

	wp_dropdown_categories([
		'show_option_all' => __('Alle Kategorien', 'cmx'),
		'taxonomy'        => $tax,
		'name'            => $tax,
		'orderby'         => 'name',
		'selected'        => $selected,
		'hierarchical'    => true,
		'show_count'      => false,
		'hide_empty'      => false,
		'value_field'     => 'slug',
	]);
}, 10);

/** =========================
 *  Admin-Liste: Beginn & Ende (zusätzliche Spalten, sortierbar)
 * ========================= */

/** Spalten hinzufügen (Beginn/Ende) – ergänzt vorhandene Spalten */
add_filter('manage_edit-projekte_columns', function($columns) {
	$new = [];
	foreach ($columns as $key => $label) {
		$new[$key] = $label;
		if ($key === 'title') {
			$new['cmx_col_beginn'] = 'Beginn';
			$new['cmx_col_ende']   = 'Ende';
		}
	}
	if (!isset($new['cmx_col_beginn'])) {
		$new['cmx_col_beginn'] = 'Beginn';
		$new['cmx_col_ende']   = 'Ende';
	}
	return $new;
});

/** Spalteninhalte (Beginn/Ende) */
add_action('manage_projekte_posts_custom_column', function($column, $post_id) {
	if ($column === 'cmx_col_beginn') {
		$val = get_post_meta($post_id, CMX_PROJ_BEG_META, true);
		echo $val ? esc_html(cmx_format_ch_date($val)) : '';
	}
	if ($column === 'cmx_col_ende') {
		$val = get_post_meta($post_id, CMX_PROJ_END_META, true);
		echo $val ? esc_html(cmx_format_ch_date($val)) : '';
	}
}, 10, 2);

/** Spalten sortierbar (Beginn/Ende) */
add_filter('manage_edit-projekte_sortable_columns', function($sortable) {
	$sortable['cmx_col_beginn'] = 'cmx_sort_beginn';
	$sortable['cmx_col_ende']   = 'cmx_sort_ende';
	// Kunde-Sortierung bleibt über den anderen Filter gesetzt
	return $sortable;
});

/** =========================
 *  Query-Hooks (Sortierung + Tax-Filter)
 * ========================= */
add_action('pre_get_posts', function(\WP_Query $q) {
	if (!is_admin() || !$q->is_main_query()) return;
	if ((string) $q->get('post_type') !== (string) CMX_PROJEKT_CPT) return;

	$orderby = $q->get('orderby');

	// Sortierung Kunde
	if ($orderby === 'cmx_kunde') {
		$q->set('meta_key', CMX_KONTAKT_META);
		$q->set('orderby', 'meta_value_num');
	}

	// Sortierung Beginn/Ende
	if ($orderby === 'cmx_sort_beginn') {
		$q->set('meta_key', CMX_PROJ_BEG_META);
		$q->set('orderby', 'meta_value');
		$q->set('meta_type', 'DATE');
	}
	if ($orderby === 'cmx_sort_ende') {
		$q->set('meta_key', CMX_PROJ_END_META);
		$q->set('orderby', 'meta_value');
		$q->set('meta_type', 'DATE');
	}

	// Kategorie-Filter (greift auch ohne query_var)
	$tax = cmx_projekte_detect_taxonomy();
	if ($tax && taxonomy_exists($tax) && is_object_in_taxonomy(CMX_PROJEKT_CPT, $tax)) {
		$selected = isset($_GET[$tax]) ? sanitize_text_field(wp_unslash($_GET[$tax])) : '';
		if ($selected !== '' && $selected !== '0') {
			$q->set('tax_query', [[
				'taxonomy'         => $tax,
				'field'            => 'slug',
				'terms'            => [$selected],
				'include_children' => true,
			]]);
		}
	}
});

/** =========================
 *  Meta-Box (SIDE): Projektzeitraum
 * ========================= */
add_action('add_meta_boxes', function() {
	add_meta_box(
		'cmx_proj_zeitraum',
		'Projektzeitraum',
		function($post) {
			$beginn = get_post_meta($post->ID, CMX_PROJ_BEG_META, true);
			$ende   = get_post_meta($post->ID, CMX_PROJ_END_META, true);
			wp_nonce_field(CMX_PROJ_NONCE_KEY, CMX_PROJ_NONCE_KEY . '_field');
			?>
			<p>
				<label for="cmx_proj_beginn"><strong>Beginn</strong></label><br>
				<input type="date" id="cmx_proj_beginn" name="cmx_proj_beginn"
					   value="<?php echo esc_attr($beginn); ?>" style="width:100%;">
			</p>
			<p>
				<label for="cmx_proj_ende"><strong>Ende</strong></label><br>
				<input type="date" id="cmx_proj_ende" name="cmx_proj_ende"
					   value="<?php echo esc_attr($ende); ?>" style="width:100%;">
			</p>
			<?php
		},
		'projekte',
		'side',
		'high'
	);
});

/** Speicherung der Meta-Werte */
add_action('save_post_projekte', function($post_id) {
	if (
		!isset($_POST[CMX_PROJ_NONCE_KEY . '_field']) ||
		!wp_verify_nonce($_POST[CMX_PROJ_NONCE_KEY . '_field'], CMX_PROJ_NONCE_KEY)
	) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (wp_is_post_revision($post_id)) return;
	if (!current_user_can('edit_post', $post_id)) return;

	$beginn = cmx_proj_sanitize_date($_POST['cmx_proj_beginn'] ?? '');
	$ende   = cmx_proj_sanitize_date($_POST['cmx_proj_ende'] ?? '');

	update_post_meta($post_id, CMX_PROJ_BEG_META, $beginn);
	update_post_meta($post_id, CMX_PROJ_END_META, $ende);
});



/**
 * Zusatz: Spalte "URL" für CPT "projekte"
 * - Meta-Key: _cmx_projekt_url
 * - Klickbarer Link (neuer Tab)
 */

// Namespaced-Konstante für den URL-Meta-Key
if (!defined(__NAMESPACE__ . '\CMX_PROJ_URL_META')) {
	define(__NAMESPACE__ . '\CMX_PROJ_URL_META', '_cmx_projekt_url');
}

/**
 * Spalte "URL" in die Liste einfügen
 * - Versucht, direkt nach "Ende" zu platzieren, sonst nach "Titel", sonst ans Ende.
 */
add_filter('manage_edit-projekte_columns', function(array $columns) {
	$label = 'URL';

	// Zielreihenfolge berechnen
	$insert_after_keys = ['cmx_col_ende', 'title'];
	$injected = [];

	// Schon vorhanden? dann nichts tun
	if (isset($columns['cmx_col_url'])) {
		return $columns;
	}

	// Array in gewünschter Reihenfolge neu zusammensetzen
	foreach ($columns as $key => $val) {
		$injected[$key] = $val;

		// Nach dem ersten passenden Schlüssel einfügen
		if (in_array($key, $insert_after_keys, true)) {
			$injected['cmx_col_url'] = $label;
			// nur einmal einfügen
			$insert_after_keys = [];
		}
	}

	// Falls kein gewünschter Schlüssel existierte: am Ende anhängen
	if (!isset($injected['cmx_col_url'])) {
		$injected['cmx_col_url'] = $label;
	}

	return $injected;
}, 30);

/**
 * Spalteninhalt "URL" ausgeben
 */
add_action('manage_projekte_posts_custom_column', function($column, $post_id) {
	if ($column !== 'cmx_col_url') return;

	$url = trim((string) get_post_meta($post_id, CMX_PROJ_URL_META, true));
	if ($url === '') {
		echo '';
		return;
	}

	// Falls ohne Schema gespeichert, optional "https://" voranstellen (nur Anzeige)
	if (!preg_match('~^https?://~i', $url)) {
		$display = 'https://' . $url;
	} else {
		$display = $url;
	}

	// Klickbar ausgeben
	echo '<a href="' . esc_url($display) . '" target="_blank" rel="noopener noreferrer">'
		. esc_html($url)
		. '</a>';
}, 10, 2);
