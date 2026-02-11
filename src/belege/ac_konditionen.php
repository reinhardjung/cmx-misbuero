<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/** =========================
 * Konstanten (defensiv)
 * ========================= */
if (!defined(__NAMESPACE__.'\\CMX_PT_BELEGE'))         define(__NAMESPACE__.'\\CMX_PT_BELEGE', 'belege');
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_DATUM'))  define(__NAMESPACE__.'\\CMX_BELEG_META_DATUM',  '_cmx_beleg_rng_datum');
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_FAELLIG'))define(__NAMESPACE__.'\\CMX_BELEG_META_FAELLIG','_cmx_beleg_faellig_am');
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_BEZAHLT'))define(__NAMESPACE__.'\\CMX_BELEG_META_BEZAHLT','_cmx_beleg_bezahlt_am');

if (!function_exists(__NAMESPACE__ . '\\cmx_beleg_zahlungsgrund_taxonomy')) {
	function cmx_beleg_zahlungsgrund_taxonomy(): string {
		$candidates = [];
		if (\defined(__NAMESPACE__ . '\\TAX_BELEGE_ZAHLUNGSGRUND')) {
			$candidates[] = (string) \constant(__NAMESPACE__ . '\\TAX_BELEGE_ZAHLUNGSGRUND');
		}
		$candidates[] = 'belege_zahlungsgrund';
		$candidates[] = 'belege_zahlungsgruende';
		$candidates[] = \function_exists(__NAMESPACE__ . '\\cmx_tax_key')
			? (string) cmx_tax_key('belege', 'zahlungsgrund')
			: 'belege_zahlungsgrund';

		foreach (\array_unique(\array_filter($candidates)) as $tax) {
			if (\taxonomy_exists($tax)) {
				return (string) $tax;
			}
		}
		return '';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_first_word')) {
	function cmx_beleg_admin_first_word(string $label): string {
		$label = \trim($label);
		if ($label === '') {
			return '';
		}
		$label = (string) \preg_replace('/\s*\(.*$/u', '', $label);
		$parts = \preg_split('/\s+/u', $label);
		return isset($parts[0]) ? (string) $parts[0] : '';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_richtung_label_map')) {
	function cmx_beleg_admin_richtung_label_map(): array {
		static $map = null;
		if (\is_array($map)) {
			return $map;
		}

		$map = [
			'rechnung' => ['ausgang' => 'Einnahme (an Kunden)', 'eingang' => 'Ausgabe (von Lieferanten)'],
			'rechnungen' => ['ausgang' => 'Einnahme (an Kunden)', 'eingang' => 'Ausgabe (von Lieferanten)'],
			'gutschrift' => ['ausgang' => 'Ausgabe (an Kunden)', 'eingang' => 'Einnahme (von Lieferanten)'],
			'gutschriften' => ['ausgang' => 'Ausgabe (an Kunden)', 'eingang' => 'Einnahme (von Lieferanten)'],
			'quittung' => ['ausgang' => 'Einnahme (an Kunden)', 'eingang' => 'Ausgabe (von Lieferanten)'],
			'quittungen' => ['ausgang' => 'Einnahme (an Kunden)', 'eingang' => 'Ausgabe (von Lieferanten)'],
			'offerte' => ['ausgang' => 'Ausgang (an Kunden)', 'eingang' => 'Eingang (von Lieferanten)'],
			'offerten' => ['ausgang' => 'Ausgang (an Kunden)', 'eingang' => 'Eingang (von Lieferanten)'],
			'lieferschein' => ['ausgang' => 'Ausgang (an Kunden)', 'eingang' => 'Eingang (von Lieferanten)'],
			'lieferscheine' => ['ausgang' => 'Ausgang (an Kunden)', 'eingang' => 'Eingang (von Lieferanten)'],
		];

		if (\function_exists(__NAMESPACE__ . '\\cmx_ini_get_value')) {
			$ini_kategorien = (array) cmx_ini_get_value('Belege', 'Kategorien');
			foreach ($ini_kategorien as $cat_name) {
				$slug = \sanitize_title((string) $cat_name);
				if ($slug === '') {
					continue;
				}
				$ini_labels = cmx_ini_get_value('BelegeRichtungLabels', (string) $cat_name);
				if ($ini_labels === null || $ini_labels === '') {
					$ini_labels = cmx_ini_get_value('BelegeRichtungLabels', (string) $slug);
				}
				if (\is_array($ini_labels) && \count($ini_labels) >= 2) {
					$map[$slug] = [
						'ausgang' => (string) $ini_labels[0],
						'eingang' => (string) $ini_labels[1],
					];
				}
			}
		}

		return $map;
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_kategorie_slug')) {
	function cmx_beleg_admin_kategorie_slug(int $post_id): string {
		$tax_candidates = [];
		if (\function_exists(__NAMESPACE__ . '\\cmx_belege_taxonomy')) {
			$tax = (string) cmx_belege_taxonomy();
			if ($tax !== '') {
				$tax_candidates[] = $tax;
			}
		}
		$tax_candidates = \array_merge($tax_candidates, ['belege_kategorien', 'beleg_kategorie']);
		foreach (\array_unique($tax_candidates) as $tax) {
			if (!\taxonomy_exists($tax)) {
				continue;
			}
			$slugs = \wp_get_post_terms($post_id, $tax, ['fields' => 'slugs']);
			if (!\is_wp_error($slugs) && !empty($slugs[0])) {
				return (string) $slugs[0];
			}
		}
		return '';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_richtung_short_label')) {
	function cmx_beleg_admin_richtung_short_label(int $post_id): string {
		$richtung = \sanitize_key((string) \get_post_meta($post_id, '_cmx_beleg_richtung', true));
		if ($richtung !== 'ausgang' && $richtung !== 'eingang') {
			return '';
		}

		$slug = cmx_beleg_admin_kategorie_slug($post_id);
		$map = cmx_beleg_admin_richtung_label_map();
		$label = '';
		if ($slug !== '' && isset($map[$slug][$richtung])) {
			$label = (string) $map[$slug][$richtung];
		}
		if ($label === '' && \function_exists(__NAMESPACE__ . '\\cmx_beleg_richtung_options')) {
			$defaults = (array) cmx_beleg_richtung_options();
			$label = (string) ($defaults[$richtung] ?? '');
		}

		return cmx_beleg_admin_first_word($label);
	}
}

// Eigener Query-Var erlauben, damit WP ihn in der Listen-Abfrage nicht verwirft.
add_filter('query_vars', function(array $vars){
	if (!in_array('cmx_bezahlfilter', $vars, true)) {
		$vars[] = 'cmx_bezahlfilter';
	}
	if (!in_array('cmx_richtungfilter', $vars, true)) {
		$vars[] = 'cmx_richtungfilter';
	}
	if (!in_array('cmx_zahlungsgrundfilter', $vars, true)) {
		$vars[] = 'cmx_zahlungsgrundfilter';
	}
	return $vars;
});

/** =========================
 * Helper: Rohwert → Timestamp
 *  - akzeptiert: int(TS), Y-m-d, d.m.Y, Ymd(ACF), DateTime-Strings
 *  - akzeptiert: Arrays/Objekte (nimmt erstes skalares Feld)
 * ========================= */
if (!function_exists(__NAMESPACE__.'\\cmx_to_ts')) {
	function cmx_to_ts($raw): int {
		if (empty($raw)) return 0;

		// Arrays/Objekte: erstes skalares Element nehmen
		if (is_array($raw)) {
			$raw = reset($raw);
		} elseif (is_object($raw)) {
			$tmp = get_object_vars($raw);
			$raw = $tmp ? reset($tmp) : '';
		}

		$raw = (string) $raw;
		$raw = trim($raw);
		if ($raw === '') return 0;

		// Reiner Integer-Timestamp?
		if (ctype_digit($raw) && strlen($raw) >= 9 && strlen($raw) <= 11) {
			return (int) $raw;
		}

		// ACF Datepicker 'Ymd' (z.B. 20240904)
		if (ctype_digit($raw) && strlen($raw) === 8) {
			$y = substr($raw, 0, 4);
			$m = substr($raw, 4, 2);
			$d = substr($raw, 6, 2);
			$ts = strtotime("$y-$m-$d 00:00:00");
			return $ts ? (int) $ts : 0;
		}

		// Versuchsreihe an gängigen Formaten
		$formats = [
			'Y-m-d',
			'd.m.Y',
			'Y/m/d',
			'd/m/Y',
			'Y-m-d H:i:s',
			'd.m.Y H:i:s',
		];
		foreach ($formats as $fmt) {
			$dt = \DateTime::createFromFormat($fmt, $raw);
			if ($dt instanceof \DateTime) {
				return $dt->getTimestamp();
			}
		}

		// Fallback: strtotime
		$ts = strtotime($raw);
		return $ts ? (int) $ts : 0;
	}
}

/** Ausgabehelfer: formatiert, bei Fehler Rohwert zeigen */
if (!function_exists(__NAMESPACE__.'\\cmx_echo_date')) {
	function cmx_echo_date($val): void {
		if (empty($val)) { echo ''; return; }

		$ts = cmx_to_ts($val);
		if ($ts) {
			echo esc_html( date_i18n('d.m.Y', $ts) );
		} else {
			// Rohwert anzeigen, damit du inkorrekte Speicherung erkennst
			if (is_array($val) || is_object($val)) {
				$val = wp_json_encode($val, JSON_UNESCAPED_UNICODE);
			}
			echo '<span style="color:#a00" title="Nicht parsebar">'.esc_html((string)$val).'</span>';
		}
	}
}

/** =========================
 * Spalten registrieren (beide Hooks für maximale Kompatibilität)
 * ========================= */
$add_columns = function(array $columns){
	$insert = [
		'beleg_datum'   => __('Datum des Beleges', 'cmx'),
		'beleg_faellig' => __('Fällig am', 'cmx'),
		'beleg_bezahlt' => __('Bezahlt am', 'cmx'),
		'beleg_zahlungsgrund' => __('Zahlungsgrund', 'cmx'),
		'beleg_richtung' => __('Richtung', 'cmx'),
	];

	$new = [];
	foreach ($columns as $key => $label) {
		$new[$key] = $label;
		if ($key === 'title') {
			$new = array_merge($new, $insert);
		}
	}
	return $new;
};
add_filter('manage_edit-' . CMX_PT_BELEGE . '_columns', $add_columns, 20);
add_filter('manage_' . CMX_PT_BELEGE . '_posts_columns', $add_columns, 20);

/** =========================
 * Spalteninhalte
 * ========================= */
add_action('manage_' . CMX_PT_BELEGE . '_posts_custom_column', function(string $column, int $post_id){
	switch ($column) {
		case 'beleg_datum':
			cmx_echo_date( get_post_meta($post_id, CMX_BELEG_META_DATUM, true) );
			break;
		case 'beleg_faellig':
			cmx_echo_date( get_post_meta($post_id, CMX_BELEG_META_FAELLIG, true) );
			break;
	case 'beleg_bezahlt':
		$val = get_post_meta($post_id, CMX_BELEG_META_BEZAHLT, true);
		if ($val) {
			cmx_echo_date($val);
		} else {
			// Button zum Setzen auf heute – nur für rechnung/lieferantenrechnung/gutschrift
			$show_btn = false;
			$type_slug = null;
			$terms = wp_get_post_terms($post_id, 'belege_kategorien', ['fields' => 'slugs']);
			if (!is_wp_error($terms) && !empty($terms)) {
				$type_slug = (string) $terms[0];
			}
			$allowed = ['rechnung','lieferantenrechnung','gutschrift'];
			if ($type_slug && in_array(strtolower($type_slug), $allowed, true)) {
				$show_btn = true;
			}

			if ($show_btn) {
				echo '<a href="#" class="button cmx-mark-paid" data-beleg="'.esc_attr($post_id).'" style="margin-left:8px;">bezahlen</a>';
			}
		}
		break;
			case 'beleg_zahlungsgrund':
				$tax = cmx_beleg_zahlungsgrund_taxonomy();
				if ($tax === '') {
					echo '';
					break;
				}
				$terms = \wp_get_post_terms($post_id, $tax, ['orderby' => 'name', 'order' => 'ASC']);
				if (\is_wp_error($terms) || empty($terms)) {
					echo '';
					break;
				}
				$links = [];
				foreach ($terms as $term) {
					if (!($term instanceof \WP_Term)) {
						continue;
					}
					$url = \add_query_arg(
						[
							'post_type' => CMX_PT_BELEGE,
							'cmx_zahlungsgrundfilter' => (string) $term->slug,
						],
						\admin_url('edit.php')
					);
					$links[] = '<a href="' . \esc_url($url) . '">' . \esc_html((string) $term->name) . '</a>';
				}
				echo \implode(', ', $links);
				break;
			case 'beleg_richtung':
				$short = cmx_beleg_admin_richtung_short_label($post_id);
				if ($short === '') {
					echo '';
					break;
				}
				$richtung = \sanitize_key((string) \get_post_meta($post_id, '_cmx_beleg_richtung', true));
				if ($richtung !== 'ausgang' && $richtung !== 'eingang') {
					echo \esc_html($short);
					break;
				}
				$url = \add_query_arg(
					[
						'post_type' => CMX_PT_BELEGE,
						'cmx_richtungfilter' => $richtung,
					],
					\admin_url('edit.php')
				);
				echo '<a href="' . \esc_url($url) . '">' . \esc_html($short) . '</a>';
				break;
		}
	}, 10, 2);

/** =========================
 * Sortierbar + Query-Anpassung
 * ========================= */
add_filter('manage_edit-' . CMX_PT_BELEGE . '_sortable_columns', function(array $columns){
	$columns['beleg_datum']   = 'beleg_datum';
	$columns['beleg_faellig'] = 'beleg_faellig';
	$columns['beleg_bezahlt'] = 'beleg_bezahlt';
	$columns['beleg_richtung'] = 'beleg_richtung';
	$columns['beleg_zahlungsgrund'] = 'beleg_zahlungsgrund';
	return $columns;
}, 10);

// Filter-Dropdown: bezahlt / nicht bezahlt
add_action('restrict_manage_posts', function($post_type){
	if ($post_type !== CMX_PT_BELEGE) return;
	$selected = isset($_GET['cmx_bezahlfilter']) ? sanitize_text_field($_GET['cmx_bezahlfilter']) : '';
	$dir_selected = isset($_GET['cmx_richtungfilter']) ? sanitize_key($_GET['cmx_richtungfilter']) : '';
	$zg_selected = isset($_GET['cmx_zahlungsgrundfilter']) ? sanitize_text_field($_GET['cmx_zahlungsgrundfilter']) : '';

	echo '<select name="cmx_bezahlfilter" id="cmx_bezahlfilter" class="postform">';
		echo '<option value="">' . esc_html__('Alle Zahlstatus', 'cmx') . '</option>';
		echo '<option value="bezahlt" ' . selected($selected, 'bezahlt', false) . '>' . esc_html__('Nur bezahlte', 'cmx') . '</option>';
		echo '<option value="offen" '    . selected($selected, 'offen', false)    . '>' . esc_html__('Nur offene', 'cmx') . '</option>';
	echo '</select>';

	$dir_opts = \function_exists(__NAMESPACE__ . '\\cmx_beleg_richtung_options')
		? (array) cmx_beleg_richtung_options()
		: ['ausgang' => 'Ausgang', 'eingang' => 'Eingang'];
	$dir_filter_labels = [
		'ausgang' => 'Einnahme',
		'eingang' => 'Ausgabe',
	];
	echo '<select name="cmx_richtungfilter" id="cmx_richtungfilter" class="postform">';
		echo '<option value="">' . esc_html__('Alle Richtungen', 'cmx') . '</option>';
		foreach ($dir_opts as $val => $label) {
			$val = sanitize_key((string) $val);
			if ($val !== 'ausgang' && $val !== 'eingang') {
				continue;
			}
			$short = (string) ($dir_filter_labels[$val] ?? '');
			if ($short === '') {
				$short = cmx_beleg_admin_first_word((string) $label);
				$short = $short !== '' ? $short : (string) $label;
			}
			echo '<option value="' . esc_attr($val) . '" ' . selected($dir_selected, $val, false) . '>' . esc_html($short) . '</option>';
		}
	echo '</select>';

	$zg_tax = cmx_beleg_zahlungsgrund_taxonomy();
	if ($zg_tax !== '') {
		$zg_terms = \get_terms([
			'taxonomy'   => $zg_tax,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]);
		if (!\is_wp_error($zg_terms)) {
			echo '<select name="cmx_zahlungsgrundfilter" id="cmx_zahlungsgrundfilter" class="postform">';
				echo '<option value="">' . esc_html__('Alle Zahlungsgründe', 'cmx') . '</option>';
				foreach ($zg_terms as $term) {
					if (!isset($term->slug, $term->name)) {
						continue;
					}
					echo '<option value="' . esc_attr((string) $term->slug) . '" ' . selected($zg_selected, (string) $term->slug, false) . '>' . esc_html((string) $term->name) . '</option>';
				}
			echo '</select>';
		}
	}
}, 10, 1);

// Pre_get_posts: Bezahl-Filter + Sortierung konsolidiert
add_action('pre_get_posts', function(\WP_Query $q){
	if (!is_admin() || !$q->is_main_query()) return;
	$pt = $q->get('post_type');
	if ($pt !== CMX_PT_BELEGE && (!is_array($pt) || !in_array(CMX_PT_BELEGE, $pt, true))) {
		return;
	}

	$filter = $q->get('cmx_bezahlfilter');
	if ($filter === null || $filter === '') {
		$filter = isset($_GET['cmx_bezahlfilter']) ? sanitize_text_field($_GET['cmx_bezahlfilter']) : '';
	}
	$q->set('cmx_bezahlfilter', $filter);

	$richtung_filter = $q->get('cmx_richtungfilter');
	if ($richtung_filter === null || $richtung_filter === '') {
		$richtung_filter = isset($_GET['cmx_richtungfilter']) ? sanitize_key($_GET['cmx_richtungfilter']) : '';
	}
	$richtung_filter = sanitize_key((string) $richtung_filter);
	$q->set('cmx_richtungfilter', $richtung_filter);

	$zg_filter = $q->get('cmx_zahlungsgrundfilter');
	if ($zg_filter === null || $zg_filter === '') {
		$zg_filter = isset($_GET['cmx_zahlungsgrundfilter']) ? sanitize_text_field($_GET['cmx_zahlungsgrundfilter']) : '';
	}
	$zg_filter = sanitize_title((string) $zg_filter);
	$q->set('cmx_zahlungsgrundfilter', $zg_filter);

	$paid_keys = array_values(array_unique([
		CMX_BELEG_META_BEZAHLT,              // typischer Key mit Unterstrich
		ltrim(CMX_BELEG_META_BEZAHLT, '_'), // Fallback ohne Unterstrich
	]));

	$meta_query = $q->get('meta_query');
	if (!is_array($meta_query)) $meta_query = [];
	if (!isset($meta_query['relation'])) {
		$meta_query['relation'] = 'AND';
	}

	if ($filter === 'bezahlt') {
		$block = ['relation' => 'OR'];
		foreach ($paid_keys as $k) {
			$block[] = [
				'key'     => $k,
				'value'   => ['', '0', '0000-00-00'],
				'compare' => 'NOT IN',
			];
		}
		$meta_query[] = $block;
	} elseif ($filter === 'offen') {
		$block = ['relation' => 'AND'];
		foreach ($paid_keys as $k) {
			$block[] = [
				'relation' => 'OR',
				[
					'key'     => $k,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => $k,
					'value'   => ['', '0', '0000-00-00'],
					'compare' => 'IN',
				],
			];
		}
		$meta_query[] = $block;
		// kein meta_key setzen, damit offene ohne Meta nicht rausfallen
	}

	if ($richtung_filter === 'ausgang' || $richtung_filter === 'eingang') {
		$meta_query[] = [
			'key'     => '_cmx_beleg_richtung',
			'value'   => $richtung_filter,
			'compare' => '=',
		];
	}

	$q->set('meta_query', $meta_query);

	$tax_query = $q->get('tax_query');
	if (!is_array($tax_query)) $tax_query = [];
	if (!isset($tax_query['relation'])) {
		$tax_query['relation'] = 'AND';
	}
	$zg_tax = cmx_beleg_zahlungsgrund_taxonomy();
	if ($zg_tax !== '' && $zg_filter !== '') {
		$tax_query[] = [
			'taxonomy' => $zg_tax,
			'field'    => 'slug',
			'terms'    => [$zg_filter],
			'operator' => 'IN',
		];
	}
	if (count($tax_query) > 1) {
		$q->set('tax_query', $tax_query);
	}

	// Sortierung
	switch ($q->get('orderby')) {
		case 'beleg_datum':
			$q->set('meta_key', CMX_BELEG_META_DATUM);
			$q->set('orderby', 'meta_value');
			break;
		case 'beleg_faellig':
			$q->set('meta_key', CMX_BELEG_META_FAELLIG);
			$q->set('orderby', 'meta_value');
			break;
		case 'beleg_bezahlt':
			// Immer custom sort, damit unbezahlte (ohne Meta) nicht rausfallen
			$q->set('orderby', 'beleg_bezahlt_custom');
			break;
		case 'beleg_richtung':
			$q->set('meta_key', '_cmx_beleg_richtung');
			$q->set('orderby', 'meta_value');
			break;
		case 'beleg_zahlungsgrund':
			$q->set('orderby', 'beleg_zahlungsgrund_custom');
			break;
	}
}, 50);

// Custom ORDER BY für Spezialspalten
add_filter('posts_clauses', function($clauses, \WP_Query $q){
	if (!is_admin() || !$q->is_main_query()) return $clauses;
	$pt = $q->get('post_type');
	if ($pt !== CMX_PT_BELEGE && (!is_array($pt) || !in_array(CMX_PT_BELEGE, $pt, true))) return $clauses;
	$orderby = (string) $q->get('orderby');
	if ($orderby !== 'beleg_bezahlt_custom' && $orderby !== 'beleg_zahlungsgrund_custom') return $clauses;

	$order = strtoupper($q->get('order')) === 'DESC' ? 'DESC' : 'ASC';
	global $wpdb;

	if ($orderby === 'beleg_bezahlt_custom') {
		$key1 = esc_sql(CMX_BELEG_META_BEZAHLT);
		$key2 = esc_sql(ltrim(CMX_BELEG_META_BEZAHLT, '_'));
		if (strpos($clauses['join'], 'cmx_order_paid1') === false) {
			$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS cmx_order_paid1 ON cmx_order_paid1.post_id = {$wpdb->posts}.ID AND cmx_order_paid1.meta_key = '{$key1}'";
		}
		if (strpos($clauses['join'], 'cmx_order_paid2') === false) {
			$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS cmx_order_paid2 ON cmx_order_paid2.post_id = {$wpdb->posts}.ID AND cmx_order_paid2.meta_key = '{$key2}'";
		}
		$clauses['orderby'] = "COALESCE(NULLIF(cmx_order_paid1.meta_value,''), NULLIF(cmx_order_paid2.meta_value,'')) {$order}, {$wpdb->posts}.post_date {$order}";
		return $clauses;
	}

	$zg_tax = cmx_beleg_zahlungsgrund_taxonomy();
	if ($zg_tax === '') {
		return $clauses;
	}
	$zg_tax_sql = esc_sql($zg_tax);
	if (strpos($clauses['join'], 'cmxzg_rel') === false) {
		$clauses['join'] .= " LEFT JOIN {$wpdb->term_relationships} AS cmxzg_rel ON cmxzg_rel.object_id = {$wpdb->posts}.ID";
	}
	if (strpos($clauses['join'], 'cmxzg_tax') === false) {
		$clauses['join'] .= " LEFT JOIN {$wpdb->term_taxonomy} AS cmxzg_tax ON cmxzg_tax.term_taxonomy_id = cmxzg_rel.term_taxonomy_id AND cmxzg_tax.taxonomy = '{$zg_tax_sql}'";
	}
	if (strpos($clauses['join'], 'cmxzg_term') === false) {
		$clauses['join'] .= " LEFT JOIN {$wpdb->terms} AS cmxzg_term ON cmxzg_term.term_id = cmxzg_tax.term_id";
	}

	if (trim((string) ($clauses['groupby'] ?? '')) === '') {
		$clauses['groupby'] = "{$wpdb->posts}.ID";
	} elseif (strpos((string) $clauses['groupby'], "{$wpdb->posts}.ID") === false) {
		$clauses['groupby'] .= ", {$wpdb->posts}.ID";
	}
	$clauses['orderby'] = "MIN(COALESCE(cmxzg_term.name,'')) {$order}, {$wpdb->posts}.post_date {$order}";

	return $clauses;
}, 20, 2);

// Admin-Footer JS nur auf der Belege-Liste
add_action('admin_footer-edit.php', function () {
	$screen = function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || $screen->id !== 'edit-'.CMX_PT_BELEGE) return;

	$nonce = wp_create_nonce('cmx_mark_paid');
	?>
	<script>
	(function($){
		// Button "Als bezahlt markieren" per AJAX
		$(document).on('click', '.cmx-mark-paid', function(e){
			e.preventDefault();
			var $btn = $(this);
			var bid  = $btn.data('beleg');
			if (!bid) return;

			$.post(ajaxurl, {
				action: 'cmx_mark_beleg_paid',
				post_id: bid,
				_wpnonce: '<?php echo esc_js($nonce); ?>'
			}).done(function(resp){
				if (resp && resp.success) {
					location.reload();
				} else {
					alert(resp && resp.data ? resp.data : 'Fehler beim Speichern.');
				}
			}).fail(function(){
				alert('Fehler beim Speichern.');
			});
		});
	})(jQuery);
	</script>
	<?php
});

// AJAX: Beleg als bezahlt markieren (heutiges Datum)
add_action('wp_ajax_cmx_mark_beleg_paid', function() {
	if (!current_user_can('edit_posts')) {
		wp_send_json_error('forbidden', 403);
	}
	check_ajax_referer('cmx_mark_paid');

	$post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
	if ($post_id <= 0 || get_post_type($post_id) !== CMX_PT_BELEGE) {
		wp_send_json_error('invalid');
	}

	$today = gmdate('Y-m-d', current_time('timestamp'));
	update_post_meta($post_id, CMX_BELEG_META_BEZAHLT, $today);

	wp_send_json_success();
});
