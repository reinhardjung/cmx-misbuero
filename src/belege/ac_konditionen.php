<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/** =========================
 * Konstanten (defensiv)
 * ========================= */
if (!defined(__NAMESPACE__.'\\CMX_PT_BELEGE'))         define(__NAMESPACE__.'\\CMX_PT_BELEGE', 'belege');
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_DATUM'))  define(__NAMESPACE__.'\\CMX_BELEG_META_DATUM',  '_cmx_beleg_rng_datum');
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_FAELLIG'))define(__NAMESPACE__.'\\CMX_BELEG_META_FAELLIG','_cmx_beleg_faellig_am');
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_BEZAHLT'))define(__NAMESPACE__.'\\CMX_BELEG_META_BEZAHLT','_cmx_beleg_bezahlt_am');

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
		'beleg_datum'   => __('Datum der Rechnung', 'cmx'),
		'beleg_faellig' => __('Fällig am', 'cmx'),
		'beleg_bezahlt' => __('Bezahlt am', 'cmx'),
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
			cmx_echo_date( get_post_meta($post_id, CMX_BELEG_META_BEZAHLT, true) );
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
	return $columns;
}, 10);

add_action('pre_get_posts', function(\WP_Query $q){
	if (!is_admin() || !$q->is_main_query()) return;
	if ($q->get('post_type') !== CMX_PT_BELEGE) return;

	switch ($q->get('orderby')) {
		case 'beleg_datum':
			$q->set('meta_key', CMX_BELEG_META_DATUM);
			$q->set('orderby', 'meta_value'); // bei echten Timestamps: 'meta_value_num'
			break;
		case 'beleg_faellig':
			$q->set('meta_key', CMX_BELEG_META_FAELLIG);
			$q->set('orderby', 'meta_value');
			break;
		case 'beleg_bezahlt':
			$q->set('meta_key', CMX_BELEG_META_BEZAHLT);
			$q->set('orderby', 'meta_value');
			break;
	}
}, 10);
