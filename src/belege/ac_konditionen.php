<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/** =========================
 * Konstanten (defensiv)
 * ========================= */
if (!defined(__NAMESPACE__.'\\CMX_PT_BELEGE'))         define(__NAMESPACE__.'\\CMX_PT_BELEGE', 'belege');
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_DATUM'))  define(__NAMESPACE__.'\\CMX_BELEG_META_DATUM',  '_cmx_beleg_rng_datum');
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_FAELLIG'))define(__NAMESPACE__.'\\CMX_BELEG_META_FAELLIG','_cmx_beleg_faelligkeitsdatum');
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_BEZAHLT'))define(__NAMESPACE__.'\\CMX_BELEG_META_BEZAHLT','_cmx_beleg_bezahlt_am');

if (!function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_zeitraum_options')) {
	function cmx_beleg_admin_zeitraum_options(): array {
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_presets')) {
			$options = (array) cmxbu_belege_export_presets();
			unset($options['benutzerdefiniert']);
			return $options;
		}

		return [
			'heute' => 'Heute (heute bis heute)',
			'diesen_monat' => 'Diesen Monat',
			'letzten_monat' => 'Letzten Monat',
			'vorletzten_monat' => 'Vorletzten Monat',
			'dieses_quartal' => 'Dieses Quartal',
			'letztes_quartal' => 'Letztes Quartal',
			'vorletztes_quartal' => 'Vorletztes Quartal',
			'dieses_jahr' => 'Dieses Jahr',
			'letztes_jahr' => 'Letztes Jahr',
			'vorletztes_jahr' => 'Vorletztes Jahr',
		];
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_zeitraum_selected')) {
	function cmx_beleg_admin_zeitraum_selected(): string {
		$selected = isset($_GET['cmx_zeitraumfilter']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_zeitraumfilter'])) : '';
		$options = cmx_beleg_admin_zeitraum_options();
		return ($selected !== '' && isset($options[$selected])) ? $selected : '';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_zeitraum_range')) {
	function cmx_beleg_admin_zeitraum_range(string $preset): array {
		if ($preset === '') {
			return ['from' => '', 'to' => ''];
		}

		if (\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_range_from_preset')) {
			$range = (array) cmxbu_belege_export_range_from_preset($preset);
			return [
				'from' => (string) ($range['from'] ?? ''),
				'to' => (string) ($range['to'] ?? ''),
			];
		}

		$now = \function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_now_datetime')
			? cmxbu_belege_export_now_datetime()
			: new \DateTimeImmutable('now', \function_exists('wp_timezone') ? \wp_timezone() : new \DateTimeZone('UTC'));
		$today = $now->format('Y-m-d');

		switch ($preset) {
			case 'heute':
				return ['from' => $today, 'to' => $today];
			case 'diesen_monat':
				return [
					'from' => $now->modify('first day of this month')->format('Y-m-d'),
					'to' => $now->modify('last day of this month')->format('Y-m-d'),
				];
			case 'letzten_monat':
				return [
					'from' => $now->modify('first day of last month')->format('Y-m-d'),
					'to' => $now->modify('last day of last month')->format('Y-m-d'),
				];
			case 'vorletzten_monat':
				return [
					'from' => $now->modify('first day of -2 months')->format('Y-m-d'),
					'to' => $now->modify('last day of -2 months')->format('Y-m-d'),
				];
			case 'dieses_quartal':
				$year = (int) $now->format('Y');
				$month = (int) $now->format('n');
				$start_month = ((int) \floor(($month - 1) / 3) * 3) + 1;
				$q_start = $now->setDate($year, $start_month, 1);
				return [
					'from' => $q_start->format('Y-m-d'),
					'to' => $q_start->modify('+2 months')->modify('last day of this month')->format('Y-m-d'),
				];
			case 'letztes_quartal':
				$year = (int) $now->format('Y');
				$month = (int) $now->format('n');
				$start_month = ((int) \floor(($month - 1) / 3) * 3) + 1;
				$current_q_start = $now->setDate($year, $start_month, 1);
				return [
					'from' => $current_q_start->modify('-3 months')->format('Y-m-d'),
					'to' => $current_q_start->modify('-1 day')->format('Y-m-d'),
				];
			case 'vorletztes_quartal':
				$year = (int) $now->format('Y');
				$month = (int) $now->format('n');
				$start_month = ((int) \floor(($month - 1) / 3) * 3) + 1;
				$current_q_start = $now->setDate($year, $start_month, 1);
				return [
					'from' => $current_q_start->modify('-6 months')->format('Y-m-d'),
					'to' => $current_q_start->modify('-3 months')->modify('-1 day')->format('Y-m-d'),
				];
			case 'dieses_jahr':
				$year = (int) $now->format('Y');
				return ['from' => \sprintf('%04d-01-01', $year), 'to' => \sprintf('%04d-12-31', $year)];
			case 'letztes_jahr':
				$year = ((int) $now->format('Y')) - 1;
				return ['from' => \sprintf('%04d-01-01', $year), 'to' => \sprintf('%04d-12-31', $year)];
			case 'vorletztes_jahr':
				$year = ((int) $now->format('Y')) - 2;
				return ['from' => \sprintf('%04d-01-01', $year), 'to' => \sprintf('%04d-12-31', $year)];
			default:
				return ['from' => '', 'to' => ''];
		}
	}
}

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

if (!function_exists(__NAMESPACE__ . '\\cmx_beleg_zahlungsart_taxonomy')) {
	function cmx_beleg_zahlungsart_taxonomy(): string {
		$candidates = [];
		if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_zahlungsart_tax')) {
			$resolved = (string) cmx_beleg_zahlungsart_tax();
			if ($resolved !== '') {
				$candidates[] = $resolved;
			}
		}
		if (\defined(__NAMESPACE__ . '\\TAX_BELEGE_ZAHLUNGSART')) {
			$candidates[] = (string) \constant(__NAMESPACE__ . '\\TAX_BELEGE_ZAHLUNGSART');
		}
		$candidates[] = 'belege_zahlungsarten';
		$candidates[] = 'belege_zahlungsart';
		$candidates[] = \function_exists(__NAMESPACE__ . '\\cmx_tax_key')
			? (string) cmx_tax_key('belege', 'zahlungsarten')
			: 'belege_zahlungsarten';
		$candidates[] = \function_exists(__NAMESPACE__ . '\\cmx_tax_key')
			? (string) cmx_tax_key('belege', 'zahlungsart')
			: 'belege_zahlungsart';

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
		$tax = \function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_kategorie_taxonomy')
			? cmx_beleg_admin_kategorie_taxonomy()
			: '';
		if ($tax !== '') {
			$slugs = \wp_get_post_terms($post_id, $tax, ['fields' => 'slugs']);
			if (!\is_wp_error($slugs) && !empty($slugs[0])) {
				return (string) $slugs[0];
			}
		}
		return '';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_kategorie_taxonomy')) {
	function cmx_beleg_admin_kategorie_taxonomy(): string {
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
			return (string) $tax;
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
	if (!in_array('cmx_zahlungsartfilter', $vars, true)) {
		$vars[] = 'cmx_zahlungsartfilter';
	}
	if (!in_array('cmx_zeitraumfilter', $vars, true)) {
		$vars[] = 'cmx_zeitraumfilter';
	}
	if (!in_array('cmx_belegdatum_von', $vars, true)) {
		$vars[] = 'cmx_belegdatum_von';
	}
	if (!in_array('cmx_belegdatum_bis', $vars, true)) {
		$vars[] = 'cmx_belegdatum_bis';
	}
	return $vars;
});

if (!function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_normalize_date_value')) {
	function cmx_beleg_admin_normalize_date_value($raw): string {
		if (\is_array($raw)) {
			$raw = \reset($raw);
		} elseif (\is_object($raw)) {
			$tmp = \get_object_vars($raw);
			$raw = $tmp ? \reset($tmp) : '';
		}

		$raw = \trim((string) $raw);
		if ($raw === '') {
			return '';
		}
		if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
			return $raw;
		}

		$ts = \function_exists(__NAMESPACE__ . '\\cmx_to_ts') ? cmx_to_ts($raw) : 0;
		if ($ts <= 0) {
			return '';
		}

		return \wp_date('Y-m-d', $ts);
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_request_date_value')) {
	function cmx_beleg_admin_request_date_value(string $key): string {
		if (!isset($_GET[$key]) || \is_array($_GET[$key])) {
			return '';
		}

		return cmx_beleg_admin_normalize_date_value((string) \wp_unslash($_GET[$key]));
	}
}

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
	$tax = \function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_kategorie_taxonomy')
		? cmx_beleg_admin_kategorie_taxonomy()
		: '';
	$show_category_col = $tax !== '';

	$insert = [
		'beleg_datum'   => __('Beleg', 'cmx'),
		'beleg_faellig' => __('Fällig am', 'cmx'),
		'beleg_bezahlt' => __('Bezahlt am', 'cmx'),
		'beleg_zahlungsart' => __('Zahlungsart', 'cmx'),
		'beleg_zahlungsgrund' => __('Zahlungsgrund', 'cmx'),
	];
	if ($show_category_col) {
		$insert['cmx_belege_kategorie'] = __('Kategorie', 'cmx');
	}
	$insert['beleg_richtung'] = __('Richtung', 'cmx');

	$new = [];
	foreach ($columns as $key => $label) {
		// Vorhandene Kategorie-Spalte überspringen und gezielt neu platzieren.
		if ($key === 'cmx_belege_kategorie') {
			continue;
		}
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
		case 'cmx_belege_kategorie':
			// Für Cloudmeister rendert ac_kategorie.php bereits die Kategorie-Spalte.
			if (\function_exists(__NAMESPACE__ . '\\cmx_is_cloudmeister_user') && cmx_is_cloudmeister_user()) {
				break;
			}
			$tax = \function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_kategorie_taxonomy')
				? cmx_beleg_admin_kategorie_taxonomy()
				: '';
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
						$tax => (string) $term->slug,
					],
					\admin_url('edit.php')
				);
				$links[] = '<a href="' . \esc_url($url) . '">' . \esc_html((string) $term->name) . '</a>';
			}
			echo \implode(', ', $links);
			break;
			case 'beleg_datum':
				$raw_date = (string) \get_post_meta($post_id, CMX_BELEG_META_DATUM, true);
				$date_filter_value = cmx_beleg_admin_normalize_date_value($raw_date);
				if ($date_filter_value !== '') {
					$url = \add_query_arg(
						[
							'post_type' => CMX_PT_BELEGE,
							'cmx_belegdatum_von' => $date_filter_value,
							'cmx_belegdatum_bis' => $date_filter_value,
						],
						\admin_url('edit.php')
					);
					echo '<a href="' . \esc_url($url) . '">';
					cmx_echo_date($raw_date);
					echo '</a>';
					break;
				}
				cmx_echo_date($raw_date);
				break;
			case 'beleg_faellig':
				cmx_echo_date( get_post_meta($post_id, CMX_BELEG_META_FAELLIG, true) );
				break;
			case 'beleg_bezahlt':
				$val = get_post_meta($post_id, CMX_BELEG_META_BEZAHLT, true);
				if ($val) {
					cmx_echo_date($val);
				} else {
					// Button zum Setzen auf heute – nur für abrechenbare Kategorien
					$show_btn = false;
					$type_slug = \function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_kategorie_slug')
						? (string) cmx_beleg_admin_kategorie_slug($post_id)
						: '';
					$allowed = ['rechnung','lieferantenrechnung','gutschrift','quittung','quittungen'];
					if ($type_slug && in_array(strtolower($type_slug), $allowed, true)) {
						$show_btn = true;
					}

					if ($show_btn) {
						echo '<span class="cmx-admin-pay-wrap" style="position:relative;display:inline-flex;align-items:center;gap:6px;margin-left:8px;">';
						echo '<button type="button" class="button cmx-mark-paid" data-beleg="' . \esc_attr($post_id) . '">bezahlen</button>';
						echo '<input type="date" class="cmx-admin-pay-date" data-beleg="' . \esc_attr($post_id) . '" aria-label="Bezahlt am wählen" style="position:absolute;inset:0;opacity:0;pointer-events:none;width:100%;height:100%;border:0;padding:0;margin:0;">';
						echo '</span>';
					}
				}
				break;
			case 'beleg_zahlungsart':
				$tax = cmx_beleg_zahlungsart_taxonomy();
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
							'cmx_zahlungsartfilter' => (string) $term->slug,
						],
						\admin_url('edit.php')
					);
					$links[] = '<a href="' . \esc_url($url) . '">' . \esc_html((string) $term->name) . '</a>';
				}
				echo \implode(', ', $links);
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
	$columns['cmx_belege_kategorie'] = 'beleg_kategorie_custom';
	$columns['beleg_richtung'] = 'beleg_richtung';
	return $columns;
}, 10);

// Filter-Dropdown: bezahlt / nicht bezahlt
add_action('restrict_manage_posts', function($post_type){
	if ($post_type !== CMX_PT_BELEGE) return;
	$selected = isset($_GET['cmx_bezahlfilter']) ? sanitize_text_field($_GET['cmx_bezahlfilter']) : '';
	$dir_selected = isset($_GET['cmx_richtungfilter']) ? sanitize_key($_GET['cmx_richtungfilter']) : '';
	$za_selected = isset($_GET['cmx_zahlungsartfilter']) ? sanitize_text_field($_GET['cmx_zahlungsartfilter']) : '';
	$zg_selected = isset($_GET['cmx_zahlungsgrundfilter']) ? sanitize_text_field($_GET['cmx_zahlungsgrundfilter']) : '';
	$zeitraum_selected = cmx_beleg_admin_zeitraum_selected();
	$date_from_selected = cmx_beleg_admin_request_date_value('cmx_belegdatum_von');
	$date_to_selected = cmx_beleg_admin_request_date_value('cmx_belegdatum_bis');
	$show_paid_filter = !\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_column_is_visible') || cmx_beleg_admin_column_is_visible('beleg_bezahlt');
	$show_date_filter = !\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_column_is_visible') || cmx_beleg_admin_column_is_visible('beleg_datum');
	$show_category_filter_by_column = !\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_column_is_visible') || cmx_beleg_admin_column_is_visible('cmx_belege_kategorie');
	$show_direction_filter = !\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_column_is_visible') || cmx_beleg_admin_column_is_visible('beleg_richtung');
	$show_payment_type_filter = !\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_column_is_visible') || cmx_beleg_admin_column_is_visible('beleg_zahlungsart');
	$show_payment_reason_filter = !\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_column_is_visible') || cmx_beleg_admin_column_is_visible('beleg_zahlungsgrund');
	$cat_tax = \function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_kategorie_taxonomy')
		? cmx_beleg_admin_kategorie_taxonomy()
		: '';
	$cat_selected = ($cat_tax !== '' && isset($_GET[$cat_tax])) ? sanitize_title((string) \wp_unslash($_GET[$cat_tax])) : '';

	if ($show_paid_filter) {
		echo '<select name="cmx_bezahlfilter" id="cmx_bezahlfilter" class="postform">';
			echo '<option value="">' . esc_html__('Alle Zahlstatus', 'cmx') . '</option>';
			echo '<option value="bezahlt" ' . selected($selected, 'bezahlt', false) . '>' . esc_html__('Nur bezahlte', 'cmx') . '</option>';
			echo '<option value="offen" '    . selected($selected, 'offen', false)    . '>' . esc_html__('Nur offene', 'cmx') . '</option>';
		echo '</select>';
	}

	if ($show_date_filter) {
		echo '<select name="cmx_zeitraumfilter" id="cmx_zeitraumfilter" class="postform">';
			echo '<option value="">' . esc_html__('Alle Zeiträume', 'cmx') . '</option>';
			foreach (cmx_beleg_admin_zeitraum_options() as $value => $label) {
				echo '<option value="' . esc_attr((string) $value) . '" ' . selected($zeitraum_selected, (string) $value, false) . '>' . esc_html((string) $label) . '</option>';
			}
		echo '</select>';
		echo '<label class="screen-reader-text" for="cmx_belegdatum_von">' . esc_html__('Belegdatum von', 'cmx') . '</label>';
		echo '<input type="date" name="cmx_belegdatum_von" id="cmx_belegdatum_von" value="' . esc_attr($date_from_selected) . '" style="height:32px;min-height:32px;line-height:30px;border-radius:6px;" />';
		echo '<label class="screen-reader-text" for="cmx_belegdatum_bis">' . esc_html__('Belegdatum bis', 'cmx') . '</label>';
		echo '<input type="date" name="cmx_belegdatum_bis" id="cmx_belegdatum_bis" value="' . esc_attr($date_to_selected) . '" style="height:32px;min-height:32px;line-height:30px;border-radius:6px;" />';
	}

	$show_category_filter = true;
	if (\function_exists(__NAMESPACE__ . '\\cmx_is_cloudmeister_user') && cmx_is_cloudmeister_user()) {
		// Für Cloudmeister rendert ac_kategorie.php bereits den Kategorien-Filter.
		$show_category_filter = false;
	}
	if ($show_category_filter && $show_category_filter_by_column && $cat_tax !== '') {
		$cat_terms = \get_terms([
			'taxonomy'   => $cat_tax,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]);
		if (!\is_wp_error($cat_terms)) {
			echo '<select name="' . \esc_attr($cat_tax) . '" id="' . \esc_attr($cat_tax) . '" class="postform">';
				echo '<option value="">' . esc_html__('Alle Kategorien', 'cmx') . '</option>';
				foreach ($cat_terms as $term) {
					if (!($term instanceof \WP_Term)) {
						continue;
					}
					echo '<option value="' . \esc_attr((string) $term->slug) . '" ' . selected($cat_selected, (string) $term->slug, false) . '>' . esc_html((string) $term->name) . '</option>';
				}
			echo '</select>';
		}
	}

	$dir_opts = \function_exists(__NAMESPACE__ . '\\cmx_beleg_richtung_options')
		? (array) cmx_beleg_richtung_options()
		: ['ausgang' => 'Ausgang', 'eingang' => 'Eingang'];
	$dir_filter_labels = [
		'ausgang' => 'Einnahme',
		'eingang' => 'Ausgabe',
	];
	if ($show_direction_filter) {
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
	}

	$za_tax = cmx_beleg_zahlungsart_taxonomy();
	if ($show_payment_type_filter && $za_tax !== '') {
		$za_terms = \get_terms([
			'taxonomy'   => $za_tax,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]);
		if (!\is_wp_error($za_terms)) {
			echo '<select name="cmx_zahlungsartfilter" id="cmx_zahlungsartfilter" class="postform">';
				echo '<option value="">' . esc_html__('Alle Zahlungsarten', 'cmx') . '</option>';
				foreach ($za_terms as $term) {
					if (!isset($term->slug, $term->name)) {
						continue;
					}
					echo '<option value="' . esc_attr((string) $term->slug) . '" ' . selected($za_selected, (string) $term->slug, false) . '>' . esc_html((string) $term->name) . '</option>';
				}
			echo '</select>';
		}
	}

	$zg_tax = cmx_beleg_zahlungsgrund_taxonomy();
	if ($show_payment_reason_filter && $zg_tax !== '') {
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

	$za_filter = $q->get('cmx_zahlungsartfilter');
	if ($za_filter === null || $za_filter === '') {
		$za_filter = isset($_GET['cmx_zahlungsartfilter']) ? sanitize_text_field($_GET['cmx_zahlungsartfilter']) : '';
	}
	$za_filter = sanitize_title((string) $za_filter);
	$q->set('cmx_zahlungsartfilter', $za_filter);

	$zeitraum_filter = $q->get('cmx_zeitraumfilter');
	if ($zeitraum_filter === null || $zeitraum_filter === '') {
		$zeitraum_filter = cmx_beleg_admin_zeitraum_selected();
	}
	$zeitraum_filter = \sanitize_key((string) $zeitraum_filter);
	$q->set('cmx_zeitraumfilter', $zeitraum_filter);

	$date_from = $q->get('cmx_belegdatum_von');
	if ($date_from === null || $date_from === '') {
		$date_from = cmx_beleg_admin_request_date_value('cmx_belegdatum_von');
	}
	$date_from = cmx_beleg_admin_normalize_date_value($date_from);
	$q->set('cmx_belegdatum_von', $date_from);

	$date_to = $q->get('cmx_belegdatum_bis');
	if ($date_to === null || $date_to === '') {
		$date_to = cmx_beleg_admin_request_date_value('cmx_belegdatum_bis');
	}
	$date_to = cmx_beleg_admin_normalize_date_value($date_to);
	$q->set('cmx_belegdatum_bis', $date_to);

	if ($date_from !== '' && $date_to !== '' && \strcmp($date_from, $date_to) > 0) {
		[$date_from, $date_to] = [$date_to, $date_from];
		$q->set('cmx_belegdatum_von', $date_from);
		$q->set('cmx_belegdatum_bis', $date_to);
	}

	if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_column_is_visible')) {
		if (!cmx_beleg_admin_column_is_visible('beleg_bezahlt')) {
			$filter = '';
			$q->set('cmx_bezahlfilter', '');
		}
		if (!cmx_beleg_admin_column_is_visible('beleg_datum')) {
			$zeitraum_filter = '';
			$q->set('cmx_zeitraumfilter', '');
			$date_from = '';
			$date_to = '';
			$q->set('cmx_belegdatum_von', '');
			$q->set('cmx_belegdatum_bis', '');
		}
		if (!cmx_beleg_admin_column_is_visible('beleg_richtung')) {
			$richtung_filter = '';
			$q->set('cmx_richtungfilter', '');
		}
		if (!cmx_beleg_admin_column_is_visible('beleg_zahlungsart')) {
			$za_filter = '';
			$q->set('cmx_zahlungsartfilter', '');
		}
		if (!cmx_beleg_admin_column_is_visible('beleg_zahlungsgrund')) {
			$zg_filter = '';
			$q->set('cmx_zahlungsgrundfilter', '');
		}
	}

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

	if ($zeitraum_filter !== '' && isset(cmx_beleg_admin_zeitraum_options()[$zeitraum_filter])) {
		$range = cmx_beleg_admin_zeitraum_range($zeitraum_filter);
		$from = (string) ($range['from'] ?? '');
		$to = (string) ($range['to'] ?? '');
		if ($date_from === '' && $date_to === '' && $from !== '' && $to !== '') {
			$meta_query[] = [
				'key'     => CMX_BELEG_META_DATUM,
				'value'   => [$from, $to],
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			];
		}
	}

	if ($date_from !== '' || $date_to !== '') {
		if ($date_from !== '' && $date_to !== '') {
			$meta_query[] = [
				'key'     => CMX_BELEG_META_DATUM,
				'value'   => [$date_from, $date_to],
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			];
		} elseif ($date_from !== '') {
			$meta_query[] = [
				'key'     => CMX_BELEG_META_DATUM,
				'value'   => $date_from,
				'compare' => '>=',
				'type'    => 'DATE',
			];
		} else {
			$meta_query[] = [
				'key'     => CMX_BELEG_META_DATUM,
				'value'   => $date_to,
				'compare' => '<=',
				'type'    => 'DATE',
			];
		}
	}

	$q->set('meta_query', $meta_query);

	$tax_query = $q->get('tax_query');
	if (!is_array($tax_query)) $tax_query = [];
	if (!isset($tax_query['relation'])) {
		$tax_query['relation'] = 'AND';
	}
	$zg_tax = cmx_beleg_zahlungsgrund_taxonomy();
	$za_tax = cmx_beleg_zahlungsart_taxonomy();
	if ($za_tax !== '' && $za_filter !== '') {
		$tax_query[] = [
			'taxonomy' => $za_tax,
			'field'    => 'slug',
			'terms'    => [$za_filter],
			'operator' => 'IN',
		];
	}
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
		case 'beleg_kategorie_custom':
			$q->set('orderby', 'beleg_kategorie_custom');
			break;
		case 'beleg_richtung':
			$q->set('meta_key', '_cmx_beleg_richtung');
			$q->set('orderby', 'meta_value');
			break;
	}
}, 50);

// Custom ORDER BY für Spezialspalten
add_filter('posts_clauses', function($clauses, \WP_Query $q){
	if (!is_admin() || !$q->is_main_query()) return $clauses;
	$pt = $q->get('post_type');
	if ($pt !== CMX_PT_BELEGE && (!is_array($pt) || !in_array(CMX_PT_BELEGE, $pt, true))) return $clauses;
	$orderby = (string) $q->get('orderby');
	if ($orderby !== 'beleg_bezahlt_custom' && $orderby !== 'beleg_kategorie_custom') return $clauses;

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

	if ($orderby === 'beleg_kategorie_custom') {
		$cat_tax = \function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_kategorie_taxonomy')
			? cmx_beleg_admin_kategorie_taxonomy()
			: '';
		if ($cat_tax === '') {
			return $clauses;
		}
		$cat_tax_sql = esc_sql($cat_tax);
		if (strpos($clauses['join'], 'cmxcat_rel') === false) {
			$clauses['join'] .= " LEFT JOIN {$wpdb->term_relationships} AS cmxcat_rel ON cmxcat_rel.object_id = {$wpdb->posts}.ID";
		}
		if (strpos($clauses['join'], 'cmxcat_tax') === false) {
			$clauses['join'] .= " LEFT JOIN {$wpdb->term_taxonomy} AS cmxcat_tax ON cmxcat_tax.term_taxonomy_id = cmxcat_rel.term_taxonomy_id AND cmxcat_tax.taxonomy = '{$cat_tax_sql}'";
		}
		if (strpos($clauses['join'], 'cmxcat_term') === false) {
			$clauses['join'] .= " LEFT JOIN {$wpdb->terms} AS cmxcat_term ON cmxcat_term.term_id = cmxcat_tax.term_id";
		}
		if (trim((string) ($clauses['groupby'] ?? '')) === '') {
			$clauses['groupby'] = "{$wpdb->posts}.ID";
		} elseif (strpos((string) $clauses['groupby'], "{$wpdb->posts}.ID") === false) {
			$clauses['groupby'] .= ", {$wpdb->posts}.ID";
		}
		$clauses['orderby'] = "MIN(COALESCE(cmxcat_term.name,'')) {$order}, {$wpdb->posts}.post_date {$order}";
		return $clauses;
	}

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
		function submitPaidDate(bid, paidDate, $btn){
			$.post(ajaxurl, {
				action: 'cmx_mark_beleg_paid',
				post_id: bid,
				paid_date: paidDate,
				_ajax_nonce: '<?php echo esc_js($nonce); ?>'
			}).done(function(resp){
				if (resp && resp.success) {
					location.reload();
					return;
				}
				alert(resp && resp.data ? resp.data : 'Fehler beim Speichern.');
				$btn.data('loading', 0).prop('disabled', false);
			}).fail(function(){
				alert('Fehler beim Speichern.');
				$btn.data('loading', 0).prop('disabled', false);
			});
		}

		$(document).on('click', '.cmx-mark-paid', function(e){
			e.preventDefault();
			var $btn = $(this);
			var bid = parseInt($btn.data('beleg'), 10);
			if (!bid) return;

			var $wrap = $btn.closest('.cmx-admin-pay-wrap');
			var $input = $wrap.find('.cmx-admin-pay-date').first();
			if (!$input.length) return;

			if (!$input.val()) {
				var now = new Date();
				var month = String(now.getMonth() + 1).padStart(2, '0');
				var day = String(now.getDate()).padStart(2, '0');
				$input.val(String(now.getFullYear()) + '-' + month + '-' + day);
			}

			var input = $input.get(0);
			if (input && typeof input.showPicker === 'function') {
				input.showPicker();
				return;
			}

			$input.trigger('focus').trigger('click');
		});

		$(document).on('change', '.cmx-admin-pay-date', function(){
			var $input = $(this);
			var bid = parseInt($input.data('beleg'), 10);
			var paidDate = String($input.val() || '');
			var $wrap = $input.closest('.cmx-admin-pay-wrap');
			var $btn = $wrap.find('.cmx-mark-paid').first();
			if (!bid || !$btn.length || $btn.data('loading') === 1 || !/^\d{4}-\d{2}-\d{2}$/.test(paidDate)) {
				return;
			}

			$btn.data('loading', 1).prop('disabled', true);
			submitPaidDate(bid, paidDate, $btn);
		});
	})(jQuery);
	</script>
	<?php
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_admin_mark_beleg_paid')) {
	function cmx_admin_mark_beleg_paid(int $post_id, string $paid_date = ''): array {
		$post = \get_post($post_id);
		if (!$post instanceof \WP_Post || $post->post_type !== CMX_PT_BELEGE) {
			return [
				'success' => false,
				'message' => 'invalid',
				'status'  => 400,
			];
		}

		if (!\current_user_can('edit_post', $post_id)) {
			return [
				'success' => false,
				'message' => 'forbidden',
				'status'  => 403,
			];
		}

		$paid_date = \preg_match('/^\d{4}-\d{2}-\d{2}$/', $paid_date)
			? $paid_date
			: \wp_date('Y-m-d');

		$status_meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_STATUS')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_STATUS')
			: '_cmx_beleg_status';

		\update_post_meta($post_id, CMX_BELEG_META_BEZAHLT, $paid_date);
		\update_post_meta($post_id, $status_meta_key, 'bezahlt');
		\clean_post_cache($post_id);

		if (\function_exists(__NAMESPACE__ . '\\cmxbu_mark_project_tasks_paid')) {
			cmxbu_mark_project_tasks_paid($post_id, $post, true);
		}

		if (\function_exists(__NAMESPACE__ . '\\cmxbu_generate_document_on_save')) {
			cmxbu_generate_document_on_save($post_id, $post, true);
		}

		$amount_display = \function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_amount_display')
			? \trim((string) cmxbu_get_beleg_amount_display($post_id))
			: '';

		return [
			'success'           => true,
			'post_id'           => $post_id,
			'paid_date'         => $paid_date,
			'paid_date_display' => \wp_date('d.m.Y', \strtotime($paid_date)),
			'status'            => 'bezahlt',
			'amount_display'    => $amount_display,
		];
	}
}

// AJAX: Beleg als bezahlt markieren (ausgewaehltes Datum oder heutiges Datum)
add_action('wp_ajax_cmx_mark_beleg_paid', function() {
	check_ajax_referer('cmx_mark_paid');

	$post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
	$paid_date = isset($_POST['paid_date']) ? (string) $_POST['paid_date'] : '';
	$result = cmx_admin_mark_beleg_paid($post_id, $paid_date);

	if (empty($result['success'])) {
		$status = isset($result['status']) ? (int) $result['status'] : 400;
		$message = (string) ($result['message'] ?? 'error');
		wp_send_json_error($message, $status);
	}

	unset($result['success']);
	wp_send_json_success($result);
});
