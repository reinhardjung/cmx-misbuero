<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/** =========================
 * Konstanten (defensiv)
 * ========================= */
if (!defined(__NAMESPACE__.'\\CMX_PT_BELEGE'))         define(__NAMESPACE__.'\\CMX_PT_BELEGE', 'belege');
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_DATUM'))  define(__NAMESPACE__.'\\CMX_BELEG_META_DATUM',  '_cmx_beleg_rng_datum');
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_FAELLIG'))define(__NAMESPACE__.'\\CMX_BELEG_META_FAELLIG','_cmx_beleg_faellig_am');
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_BEZAHLT'))define(__NAMESPACE__.'\\CMX_BELEG_META_BEZAHLT','_cmx_beleg_bezahlt_am');

// Eigener Query-Var erlauben, damit WP ihn in der Listen-Abfrage nicht verwirft.
add_filter('query_vars', function(array $vars){
	if (!in_array('cmx_bezahlfilter', $vars, true)) {
		$vars[] = 'cmx_bezahlfilter';
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
				echo '<a href="#" class="button cmx-mark-paid" data-beleg="'.esc_attr($post_id).'" style="margin-left:8px;">bezahlt</a>';
			}
		}
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

// Filter-Dropdown: bezahlt / nicht bezahlt
add_action('restrict_manage_posts', function($post_type){
	if ($post_type !== CMX_PT_BELEGE) return;
	$selected = isset($_GET['cmx_bezahlfilter']) ? sanitize_text_field($_GET['cmx_bezahlfilter']) : '';
	echo '<select name="cmx_bezahlfilter" id="cmx_bezahlfilter" class="postform">';
		echo '<option value="">' . esc_html__('Alle Zahlstatus', 'cmx') . '</option>';
		echo '<option value="bezahlt" ' . selected($selected, 'bezahlt', false) . '>' . esc_html__('Nur bezahlt', 'cmx') . '</option>';
		echo '<option value="offen" '    . selected($selected, 'offen', false)    . '>' . esc_html__('Nur offen', 'cmx') . '</option>';
	echo '</select>';
}, 10, 1);

// Anfrage früh anpassen: Filter bezahlt/offen direkt auf query vars setzen
add_filter('request', function(array $vars){
	if (!is_admin()) return $vars;
	$pt = $vars['post_type'] ?? '';
	if ($pt !== CMX_PT_BELEGE) return $vars;

	$filter = isset($_GET['cmx_bezahlfilter']) ? sanitize_text_field($_GET['cmx_bezahlfilter']) : '';
	if ($filter === '') return $vars;

	$vars['cmx_bezahlfilter'] = $filter; // Query-Var verfügbar machen

	if ($filter === 'bezahlt') {
		$vars['meta_query'] = [
			[
				'key'     => CMX_BELEG_META_BEZAHLT,
				'value'   => '',
				'compare' => '!=',
			],
		];
		$vars['meta_key'] = CMX_BELEG_META_BEZAHLT;
	} elseif ($filter === 'offen') {
		$vars['meta_query'] = [
			'relation' => 'OR',
			[
				'key'     => CMX_BELEG_META_BEZAHLT,
				'compare' => 'NOT EXISTS',
			],
			[
				'key'     => CMX_BELEG_META_BEZAHLT,
				'value'   => '',
				'compare' => '=',
			],
		];
		$vars['meta_key'] = CMX_BELEG_META_BEZAHLT;
	}

	return $vars;
}, 5);

add_action('pre_get_posts', function(\WP_Query $q){
	if (!is_admin() || !$q->is_main_query()) return;
	$pt = $q->get('post_type');
	if ($pt !== CMX_PT_BELEGE && (!is_array($pt) || !in_array(CMX_PT_BELEGE, $pt, true))) {
		return;
	}

	// Filter auf Meta-Ebene anwenden (klar und nur mit definiertem Key)
	$filter = $q->get('cmx_bezahlfilter');
	if ($filter === null || $filter === '') {
		$filter = isset($_GET['cmx_bezahlfilter']) ? sanitize_text_field($_GET['cmx_bezahlfilter']) : '';
	}

	$paid_key     = CMX_BELEG_META_BEZAHLT;
	$paid_key_alt = ltrim(CMX_BELEG_META_BEZAHLT, '_'); // Fallback ohne Unterstrich

	if ($filter === 'bezahlt') {
		$meta_query = $q->get('meta_query');
		if (!is_array($meta_query)) $meta_query = [];
		if (!empty($meta_query) && !isset($meta_query['relation'])) {
			$meta_query['relation'] = 'AND';
		}
		$meta_query[] = [
			'relation' => 'OR',
			[
				'key'     => $paid_key,
				'value'   => '',
				'compare' => '!=',
			],
			[
				'key'     => $paid_key_alt,
				'value'   => '',
				'compare' => '!=',
			],
		];
		$q->set('meta_query', $meta_query);
		$q->set('meta_key', $paid_key); // nur für Sortier-Handling unten
	} elseif ($filter === 'offen') {
		$meta_query = $q->get('meta_query');
		if (!is_array($meta_query)) $meta_query = [];
		if (!empty($meta_query) && !isset($meta_query['relation'])) {
			$meta_query['relation'] = 'AND';
		}
		$meta_query[] = [
			'relation' => 'AND',
			[
				'relation' => 'OR',
				[
					'key'     => $paid_key,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => $paid_key,
					'value'   => '',
					'compare' => '=',
				],
			],
			[
				'relation' => 'OR',
				[
					'key'     => $paid_key_alt,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => $paid_key_alt,
					'value'   => '',
					'compare' => '=',
				],
			],
		];
		$q->set('meta_query', $meta_query);
		$q->set('meta_key', $paid_key); // nur für Sortier-Handling unten
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
			$q->set('meta_key', CMX_BELEG_META_BEZAHLT);
			$q->set('orderby', 'meta_value');
			break;
	}
}, 50);

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
