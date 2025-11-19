<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Admin-Liste: Spalten "Beginn" & "Ende" (sortable) für CPT "projekte"
 * - Keine Filter, nur sortierbare Spalten
 * - Anzeige im CH-Format (d.m.Y), Speicherung bleibt YYYY-MM-DD
 */

// Falls die Konstanten aus der Metabox-Datei noch nicht vorhanden sind, hier setzen:
if (!defined('CMX_PROJ_BEG_META')) define('CMX_PROJ_BEG_META', '_cmx_projekt_beginn');
if (!defined('CMX_PROJ_END_META')) define('CMX_PROJ_END_META', '_cmx_projekt_ende');

/**
 * Spalten hinzufügen/reihenfolge
 */
add_filter('manage_edit-projekte_columns', function($columns) {
	// Reihenfolge: Checkbox, Titel, Beginn, Ende, Datum
	$new = [];
	foreach ($columns as $key => $label) {
		$new[$key] = $label;
		if ($key === 'title') {
			$new['cmx_col_beginn'] = 'Beginn';
			$new['cmx_col_ende']   = 'Ende';
		}
	}
	// Fallback: falls 'title' unerwartet fehlt
	if (!isset($new['cmx_col_beginn'])) {
		$new['cmx_col_beginn'] = 'Beginn';
		$new['cmx_col_ende']   = 'Ende';
	}
	return $new;
});

/**
 * Spalteninhalte
 */
add_action('manage_projekte_posts_custom_column', function($column, $post_id) {
	if ($column === 'cmx_col_beginn') {
		$val = get_post_meta($post_id, CMX_PROJ_BEG_META, true);
		echo $val ? esc_html(cmx_format_ch_date($val)) : '—';
	}
	if ($column === 'cmx_col_ende') {
		$val = get_post_meta($post_id, CMX_PROJ_END_META, true);
		echo $val ? esc_html(cmx_format_ch_date($val)) : '—';
	}
}, 10, 2);

/**
 * Spalten sortierbar machen
 */
add_filter('manage_edit-projekte_sortable_columns', function($sortable) {
	$sortable['cmx_col_beginn'] = 'cmx_sort_beginn';
	$sortable['cmx_col_ende']   = 'cmx_sort_ende';
	return $sortable;
});

/**
 * Sortierung nach Meta-Datum umsetzen
 */
add_action('pre_get_posts', function($query) {
	if (!is_admin() || !$query->is_main_query()) return;
	if ($query->get('post_type') !== 'projekte') return;

	$orderby = $query->get('orderby');

	if ($orderby === 'cmx_sort_beginn') {
		$query->set('meta_key', CMX_PROJ_BEG_META);
		$query->set('orderby', 'meta_value');
		$query->set('meta_type', 'DATE'); // korrektes Date-Sorting
	}
	if ($orderby === 'cmx_sort_ende') {
		$query->set('meta_key', CMX_PROJ_END_META);
		$query->set('orderby', 'meta_value');
		$query->set('meta_type', 'DATE');
	}
});

/**
 * Helper: YYYY-MM-DD -> d.m.Y (CH-Format)
 */
if (!function_exists('cmx_format_ch_date')) {
	function cmx_format_ch_date($yyyy_mm_dd) {
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$yyyy_mm_dd)) {
			return '';
		}
		$ts = strtotime($yyyy_mm_dd . ' 00:00:00');
		return $ts ? date('d.m.Y', $ts) : '';
	}
}

/* ======================================================================
 * Ergänzung: Meta-Box (SIDE) "Projektzeitraum" + Speichern (YYYY-MM-DD)
 * ==================================================================== */

if (!defined('CMX_PROJ_NONCE_KEY')) define('CMX_PROJ_NONCE_KEY', 'cmx_proj_zeitraum_nonce');

/**
 * Sanitizer für ISO-Datum (YYYY-MM-DD)
 */
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

/**
 * Meta-Box registrieren (SIDE)
 */
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

/**
 * Speichern der Meta-Werte
 */
add_action('save_post_projekte', function($post_id) {
	// Nonce prüfen
	if (
		!isset($_POST[CMX_PROJ_NONCE_KEY . '_field']) ||
		!wp_verify_nonce($_POST[CMX_PROJ_NONCE_KEY . '_field'], CMX_PROJ_NONCE_KEY)
	) {
		return;
	}
	// Autosave/Revision/Capability
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (wp_is_post_revision($post_id)) return;
	if (!current_user_can('edit_post', $post_id)) return;

	$beginn = cmx_proj_sanitize_date($_POST['cmx_proj_beginn'] ?? '');
	$ende   = cmx_proj_sanitize_date($_POST['cmx_proj_ende'] ?? '');

	update_post_meta($post_id, CMX_PROJ_BEG_META, $beginn);
	update_post_meta($post_id, CMX_PROJ_END_META, $ende);
});
