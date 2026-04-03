<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

use Dompdf\Dompdf;
use Dompdf\Options;


require_once trailingslashit(defined('CMX_PLUGIN_DIR') ? CMX_PLUGIN_DIR : plugin_dir_path(__FILE__)) . 'src/belege/vorlage_schreiben.php';
// require_once CMX_PLUGIN_DIR . 'src/belege/qr_code.php';  // <--- NEU




/** =========================
 * KONFIG & LOG (keine HTML-Reste)
 * ========================= */
if (!defined(__NAMESPACE__.'\\CMX_PDF_DEBUG')) {
	define(__NAMESPACE__.'\\CMX_PDF_DEBUG', true);
}
if (!function_exists(__NAMESPACE__.'\\cmxbu_log')) {
	function cmxbu_log(string $msg, array $ctx = []): void {
		if (!CMX_PDF_DEBUG) return;
		error_log('[CMX PDF] '.$msg.($ctx ? ' | '.json_encode($ctx, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : ''));
	}
}
if (!defined(__NAMESPACE__.'\\CMX_LOGO_X_MM')) define(__NAMESPACE__.'\\CMX_LOGO_X_MM', 150.0);
if (!defined(__NAMESPACE__.'\\CMX_LOGO_Y_MM')) define(__NAMESPACE__.'\\CMX_LOGO_Y_MM', 20.0);
if (!defined(__NAMESPACE__.'\\CMX_LOGO_WIDTH_MM')) define(__NAMESPACE__.'\\CMX_LOGO_WIDTH_MM', 40.0);
if (!defined(__NAMESPACE__.'\\CMX_DL_RECIPIENT_X_MM')) define(__NAMESPACE__.'\\CMX_DL_RECIPIENT_X_MM', 20.0);
if (!defined(__NAMESPACE__.'\\CMX_DL_RECIPIENT_Y_MM')) define(__NAMESPACE__.'\\CMX_DL_RECIPIENT_Y_MM', 45.0);
if (!defined(__NAMESPACE__.'\\CMX_DL_RECIPIENT_WIDTH_MM')) define(__NAMESPACE__.'\\CMX_DL_RECIPIENT_WIDTH_MM', 85.0);
if (!defined(__NAMESPACE__.'\\CMX_DL_RECIPIENT_HEIGHT_MM')) define(__NAMESPACE__.'\\CMX_DL_RECIPIENT_HEIGHT_MM', 40.0);
if (!defined(__NAMESPACE__.'\\CMX_DL_HEADER_HEIGHT_MM')) define(__NAMESPACE__.'\\CMX_DL_HEADER_HEIGHT_MM', 98.0);
if (!defined(__NAMESPACE__.'\\CMX_DL_META_TOP_MM')) define(__NAMESPACE__.'\\CMX_DL_META_TOP_MM', 38.0);
if (!defined(__NAMESPACE__.'\\CMX_DL_RECIPIENT_X_RIGHT_MM')) define(__NAMESPACE__.'\\CMX_DL_RECIPIENT_X_RIGHT_MM', 105.0);
if (!defined(__NAMESPACE__.'\\CMX_C5_RECIPIENT_X_MM')) define(__NAMESPACE__.'\\CMX_C5_RECIPIENT_X_MM', 20.0);
if (!defined(__NAMESPACE__.'\\CMX_C5_RECIPIENT_Y_MM')) define(__NAMESPACE__.'\\CMX_C5_RECIPIENT_Y_MM', 45.0);
if (!defined(__NAMESPACE__.'\\CMX_C5_RECIPIENT_WIDTH_MM')) define(__NAMESPACE__.'\\CMX_C5_RECIPIENT_WIDTH_MM', 85.0);
if (!defined(__NAMESPACE__.'\\CMX_C5_RECIPIENT_HEIGHT_MM')) define(__NAMESPACE__.'\\CMX_C5_RECIPIENT_HEIGHT_MM', 40.0);
if (!defined(__NAMESPACE__.'\\CMX_C5_HEADER_HEIGHT_MM')) define(__NAMESPACE__.'\\CMX_C5_HEADER_HEIGHT_MM', 98.0);
if (!defined(__NAMESPACE__.'\\CMX_C5_META_TOP_MM')) define(__NAMESPACE__.'\\CMX_C5_META_TOP_MM', 38.0);
if (!defined(__NAMESPACE__.'\\CMX_C5_RECIPIENT_X_RIGHT_MM')) define(__NAMESPACE__.'\\CMX_C5_RECIPIENT_X_RIGHT_MM', 105.0);
if (!defined(__NAMESPACE__.'\\CMX_C4_RECIPIENT_X_MM')) define(__NAMESPACE__.'\\CMX_C4_RECIPIENT_X_MM', 20.0);
if (!defined(__NAMESPACE__.'\\CMX_C4_RECIPIENT_Y_MM')) define(__NAMESPACE__.'\\CMX_C4_RECIPIENT_Y_MM', 55.0);
if (!defined(__NAMESPACE__.'\\CMX_C4_RECIPIENT_WIDTH_MM')) define(__NAMESPACE__.'\\CMX_C4_RECIPIENT_WIDTH_MM', 90.0);
if (!defined(__NAMESPACE__.'\\CMX_C4_RECIPIENT_HEIGHT_MM')) define(__NAMESPACE__.'\\CMX_C4_RECIPIENT_HEIGHT_MM', 40.0);
if (!defined(__NAMESPACE__.'\\CMX_C4_HEADER_HEIGHT_MM')) define(__NAMESPACE__.'\\CMX_C4_HEADER_HEIGHT_MM', 110.0);
if (!defined(__NAMESPACE__.'\\CMX_C4_META_TOP_MM')) define(__NAMESPACE__.'\\CMX_C4_META_TOP_MM', 38.0);
if (!defined(__NAMESPACE__.'\\CMX_C4_RECIPIENT_X_RIGHT_MM')) define(__NAMESPACE__.'\\CMX_C4_RECIPIENT_X_RIGHT_MM', 100.0);
if (!defined(__NAMESPACE__.'\\CMX_C4_SWITCH_PAGE_THRESHOLD')) define(__NAMESPACE__.'\\CMX_C4_SWITCH_PAGE_THRESHOLD', 5);

// function mytheme_enqueue_local_fonts() {
//     wp_enqueue_style(
//         'asap-fonts',
//         get_stylesheet_directory_uri() . '/asap/asap.css',
//         [],
//         null
//     );
// }
// add_action('wp_enqueue_scripts', 'mytheme_enqueue_local_fonts');


/** =========================
 * Composer Autoload
 * ========================= */
$autoload = trailingslashit(defined('CMX_PLUGIN_DIR') ? CMX_PLUGIN_DIR : plugin_dir_path(__FILE__)) . 'vendor/autoload.php';
if (is_file($autoload)) require_once $autoload; // cmxbu_log('Composer autoload geladen.');

/** =========================
 * Helper (eindeutig: cmxbu_*)
 * ========================= */
if (!function_exists(__NAMESPACE__.'\\cmxbu_parse_date_ymd')) {
	function cmxbu_parse_date_ymd($val): ?string {
		if ($val === null) return null;
		if (is_string($val)) {
			$val = trim($val);
			if ($val === '') return null;
		}
		if (is_int($val) || (is_string($val) && ctype_digit($val))) { $ts=(int)$val; return $ts>0 ? gmdate('Y-m-d',$ts) : null; }
		if (is_string($val) && preg_match('~^\s*(\d{1,2})\.(\d{1,2})\.(\d{4})\s*$~',$val,$m)) {
			return sprintf('%04d-%02d-%02d',(int)$m[3],(int)$m[2],(int)$m[1]);
		}
		$ts=strtotime((string)$val); return $ts?date('d.m.Y',$ts):null;
	}
}
if (!function_exists(__NAMESPACE__.'\\cmxbu_first_meta')) {
	function cmxbu_first_meta(int $post_id, array $keys): ?string {
		foreach ($keys as $k) {
			$v = get_post_meta($post_id, $k, true);
			if ($v !== '' && $v !== null) return (string)$v;
		}
		return null;
	}
}
if (!function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_payment_amounts')) {
	function cmxbu_get_beleg_payment_amounts(float $total, array $anzahlungen): array {
		$total = round($total, 2);
		$paid_amount = 0.0;

		foreach ($anzahlungen as $row) {
			if (!is_array($row)) {
				continue;
			}
			$betrag_raw = trim((string) ($row['betrag'] ?? ''));
			if ($betrag_raw === '') {
				continue;
			}
			$betrag_raw = preg_replace('/\s*(chf|fr\.?)\s*/i', '', $betrag_raw);
			$paid_amount += (float) cmx_norm_decimal((string) $betrag_raw);
		}

		$paid_amount = round($paid_amount, 2);
		$open_amount = round($total - $paid_amount, 2);

		return [
			'has_partial_payments' => !empty($anzahlungen),
			'paid_amount' => $paid_amount,
			'open_amount' => $open_amount,
			'payment_amount' => !empty($anzahlungen) ? max($open_amount, 0.0) : $total,
		];
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmxbu_strip_png_iccp_chunks')) {
	/**
	 * Entfernt iCCP-Chunks aus PNG-Daten (verhindert libpng-Warnungen in Dompdf/GD).
	 */
	function cmxbu_strip_png_iccp_chunks(string $png_data, bool &$removed = false): string {
		$removed = false;
		$signature = "\x89PNG\x0D\x0A\x1A\x0A";
		if (strlen($png_data) < 8 || substr($png_data, 0, 8) !== $signature) {
			return $png_data;
		}

		$len = strlen($png_data);
		$offset = 8;
		$out = $signature;
		$has_iend = false;

		while ($offset + 12 <= $len) {
			$chunk_len_raw = substr($png_data, $offset, 4);
			$chunk_len_arr = unpack('Nlen', $chunk_len_raw);
			$chunk_len = (int)($chunk_len_arr['len'] ?? -1);
			if ($chunk_len < 0) {
				break;
			}

			$chunk_total = 12 + $chunk_len;
			if ($offset + $chunk_total > $len) {
				break;
			}

			$chunk_type = substr($png_data, $offset + 4, 4);
			$chunk_bin = substr($png_data, $offset, $chunk_total);
			if ($chunk_type === 'iCCP') {
				$removed = true;
			} else {
				$out .= $chunk_bin;
			}

			$offset += $chunk_total;
			if ($chunk_type === 'IEND') {
				$has_iend = true;
				break;
			}
		}

		if (!$has_iend) {
			return $png_data;
		}

		return $removed ? $out : $png_data;
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmxbu_local_path_from_url')) {
	/**
	 * Versucht aus einer lokalen URL den absoluten Dateipfad zu ermitteln.
	 */
	function cmxbu_local_path_from_url(string $url): string {
		$url = trim($url);
		if ($url === '') {
			return '';
		}

		$url_path = (string) parse_url($url, PHP_URL_PATH);
		if ($url_path === '') {
			return '';
		}
		$url_path = rawurldecode($url_path);

		$uploads = wp_get_upload_dir();
		$baseurl_path = (string) parse_url((string)($uploads['baseurl'] ?? ''), PHP_URL_PATH);
		$basedir = (string)($uploads['basedir'] ?? '');
		if ($baseurl_path !== '' && $basedir !== '' && str_starts_with($url_path, $baseurl_path)) {
			$rel = ltrim(substr($url_path, strlen($baseurl_path)), '/');
			$candidate = trailingslashit($basedir) . $rel;
			if (is_file($candidate)) {
				return $candidate;
			}
		}

		$home_path = (string) parse_url(home_url('/'), PHP_URL_PATH);
		if ($home_path === '') {
			$home_path = '/';
		}
		$home_path = '/' . ltrim($home_path, '/');
		$home_path = rtrim($home_path, '/');
		if ($home_path === '') {
			$home_path = '/';
		}
		if (str_starts_with($url_path, $home_path)) {
			$rel = ltrim(substr($url_path, strlen($home_path)), '/');
			$candidate = trailingslashit(ABSPATH) . $rel;
			if (is_file($candidate)) {
				return $candidate;
			}
		}

		if (is_file($url_path)) {
			return $url_path;
		}

		return '';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmxbu_prepare_png_for_dompdf')) {
	/**
	 * Gibt bei problematischen lokalen PNGs eine bereinigte Cache-Datei-URL zurück.
	 */
	function cmxbu_prepare_png_for_dompdf(string $url): string {
		$url = trim($url);
		if ($url === '') {
			return '';
		}

		$url_path = (string) parse_url($url, PHP_URL_PATH);
		if ($url_path === '' || !preg_match('~\.png$~i', $url_path)) {
			return $url;
		}

		$source_path = cmxbu_local_path_from_url($url);
		if ($source_path === '' || !is_readable($source_path)) {
			return $url;
		}

		$raw = @file_get_contents($source_path);
		if (!is_string($raw) || $raw === '') {
			return $url;
		}

		$removed = false;
		$clean = cmxbu_strip_png_iccp_chunks($raw, $removed);
		if (!$removed) {
			return $url;
		}

		$uploads = wp_get_upload_dir();
		$basedir = (string)($uploads['basedir'] ?? '');
		$baseurl = (string)($uploads['baseurl'] ?? '');
		if ($basedir === '' || $baseurl === '') {
			return $url;
		}

		$cache_dir = trailingslashit($basedir) . 'misbuero/png-clean';
		if (!is_dir($cache_dir) && !wp_mkdir_p($cache_dir)) {
			return $url;
		}

		$fingerprint = $source_path . '|' . (string)@filemtime($source_path) . '|' . (string)strlen($clean);
		$file_name = md5($fingerprint) . '.png';
		$target_path = trailingslashit($cache_dir) . $file_name;
		if (!is_file($target_path)) {
			$ok = @file_put_contents($target_path, $clean);
			if ($ok === false) {
				return $url;
			}
		}

		return esc_url_raw(trailingslashit($baseurl) . 'misbuero/png-clean/' . rawurlencode($file_name));
	}
}

// Hole Kontakt-Logo von Kategorie "Das bin ich"
// Fallback bleibt das bisherige Standardlogo
// Kontakt-Logo "Das bin ich" ermitteln


function cmx_get_contact_logo(int $post_id): string {
	if ($post_id <= 0) {
		return '';
	}

	if (\function_exists(__NAMESPACE__ . '\\cmx_contact_logo_url')) {
		$logo_url = (string) cmx_contact_logo_url($post_id);
		if ($logo_url !== '') {
			return \esc_url($logo_url);
		}
	}

	$local_url  = (string) \get_post_meta($post_id, '_cmx_local_image_kontakte_url', true);
	$local_path = (string) \get_post_meta($post_id, '_cmx_local_image_kontakte_path', true);
	if ($local_url !== '' && $local_path !== '' && \is_file($local_path)) {
		$info = @\getimagesize($local_path);
		if (\is_array($info) && !empty($info['mime'])) {
			return \esc_url($local_url);
		}
	}

	$thumb = (string) \get_the_post_thumbnail_url($post_id, 'full');
	if ($thumb !== '') {
		return \esc_url($thumb);
	}

	return '';
}

function cmx_get_branding_logo(): string {
	if (\function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_url')) {
		$logo = (string) cmx_email_self_logo_url();
		if ($logo !== '') {
			return $logo;
		}
	}

	$q = new \WP_Query([
		'post_type'      => 'kontakte',
		'post_status'    => ['publish', 'private'],
		'posts_per_page' => 1,
		'tax_query'      => [[
			'taxonomy' => 'kontakte_kategorien',
			'field'    => 'slug',
			'terms'    => ['das-bin-ich'],
		]],
		'no_found_rows'    => true,
		'suppress_filters' => true,
	]);
	if (!$q->have_posts()) {
		return '';
	}

	$q->the_post();
	$post_id = (int) \get_the_ID();
	\wp_reset_postdata();

	return \function_exists(__NAMESPACE__ . '\\cmx_get_contact_logo')
		? (string) cmx_get_contact_logo($post_id)
		: '';
}



function cmx_get_belegfuss(string $key): string {
	$slug = strtolower(trim($key));
	$options = (array) get_option('cmx_belege', []);

	$read = static function (array $opts, string $option_key): string {
		if (!isset($opts[$option_key]) || !is_string($opts[$option_key])) {
			return '';
		}
		return trim((string) $opts[$option_key]);
	};

	$value = $read($options, 'belegfuss_' . $slug);
	if ($value !== '') {
		return $value;
	}

	$fallback_map = [
		'rechnungen' => 'rechnung',
		'quittung' => 'rechnung',
		'quittungen' => 'rechnung',
		'lieferantenrechnung' => 'rechnung',
		'lieferantenrechnungen' => 'rechnung',
		'lieferantenquittung' => 'rechnung',
		'lieferantenquittungen' => 'rechnung',
		'offerten' => 'offerte',
		'lieferscheine' => 'lieferschein',
		'gutschriften' => 'gutschrift',
	];
	$fallback_slug = (string) ($fallback_map[$slug] ?? 'rechnung');
	if ($fallback_slug !== '' && $fallback_slug !== $slug) {
		$value = $read($options, 'belegfuss_' . $fallback_slug);
		if ($value !== '') {
			return $value;
		}
	}

	return '';
}

function cmx_get_beleg_briefbogen(string $beleg_type): string {
	$beleg_type = strtolower(trim($beleg_type));
	// Alle Dokumenttypen ausser Lieferschein nutzen den Rechnungs-Briefbogen.
	$type_key = ($beleg_type === 'lieferschein') ? 'lieferschein' : 'rechnung';
	$option_key = 'briefbogen_' . $type_key;
	$options = (array) get_option('cmx_belege', []);
	$selected = strtolower(trim((string)($options[$option_key] ?? 'dl_left')));
	$legacy_map = [
		'dl' => 'dl_left',
		'c5' => 'c5_left',
		'c4' => 'c4_left',
	];
	if (isset($legacy_map[$selected])) {
		$selected = $legacy_map[$selected];
	}
	$allowed = ['dl_left', 'c5_left', 'c4_left', 'dl_right', 'c5_right', 'c4_right'];

	return in_array($selected, $allowed, true) ? $selected : 'dl_left';
}



if (!function_exists(__NAMESPACE__.'\\cmxbu_beleg_get_dates')) {
function cmxbu_beleg_get_dates(int $post_id): array {
		$inv_raw = cmxbu_first_meta($post_id, ['_cmx_beleg_rng_datum','_cmx_rechnungsdatum','_invoice_date','_date']);
		$date_invoice = cmxbu_parse_date_ymd($inv_raw) ?: '';

		// $due_raw = cmxbu_first_meta($post_id, ['_cmx_beleg_faellig_am','_cmx_faellig_am','_faellig_bis','_due_date','_due']);
		$due_raw = cmxbu_first_meta($post_id, ['_cmx_beleg_faelligkeitsdatum']);
		$date_due = cmxbu_parse_date_ymd($due_raw);
		// if (!$date_due) {<?php



if (!function_exists(__NAMESPACE__ . '\\cmxbu_get_me_contact')) {
	function cmxbu_get_me_contact(): array {

		$q = get_posts([
			'post_type'       => ['kontakte','kontakt','contact'],
			'post_status'     => ['publish','private'],
			'posts_per_page'  => 1,
			'tax_query'       => [
				'relation' => 'OR',
				['taxonomy'=>'kontakte_kategorien','field'=>'slug','terms'=>['das-bin-ich','ich']],
				['taxonomy'=>'kontakte_kategorien','field'=>'name','terms'=>['Das bin ich']],
			],
			'no_found_rows'    => true,
			'suppress_filters' => true,
		]);

		if (empty($q)) return [];

		$p  = $q[0];
		$id = (int)$p->ID;

		// alle Meta abrufen
		$m = get_post_meta($id);

		/** docu rju 2025-11-10: Best Dump */
		// var_dump(get_post_meta($id)); exit;
		return [
			'company' => get_post_meta($id, '_company', true) ?: $p->post_title,
			'vorname' => get_post_meta($id, '_cmx_kontakte_vorname', true),
			'nachname'=> get_post_meta($id, '_cmx_kontakte_nachname', true),
			'strasse' => get_post_meta($id, '_cmx_rechnung_strasse', true),
			'plz'     => get_post_meta($id, '_cmx_rechnung_plz', true),
			'ort'     => get_post_meta($id, '_cmx_rechnung_ort', true),
			'land'    => get_post_meta($id, '_cmx_rechnung_land', true),
			'phone'   => get_post_meta($id, '_cmx_telefon_1', true),
			'email'   => get_post_meta($id, '_cmx_email_1', true),
			'website' => get_post_meta($id, '_cmx_kontakte_url', true),
		];
	}
}

		// 	$opts = (array)get_option('cmx_einstellungen', []);
		// 	$days = isset($opts['due_days']) ? (int)$opts['due_days'] : 10;
		// 	$date_due = date('Y-m-d', strtotime($date_invoice.' +'.$days.' days'));
		// }


	setlocale(LC_TIME, 'de_CH.UTF-8');
	$period_raw = cmxbu_first_meta($post_id, ['_cmx_beleg_leistungsmonat']);
	$period_num = (int) $period_raw;

	// Monat automatisch erzeugen
	$formatter = new \IntlDateFormatter(
			get_user_locale(),
			\IntlDateFormatter::NONE,
			\IntlDateFormatter::NONE,
			null,
			null,
			'LLLL'   // langer Monatsname
	);

	$period = $formatter->format(mktime(0, 0, 0, $period_num, 1));


		$period_raw = cmxbu_first_meta($post_id, ['_cmx_beleg_leistungsmonat']);

		$period_num = (int) $period_raw; // ← WICHTIG

		$monate = [
				1 => 'Januar', 2 => 'Februar', 3 => 'März',
				4 => 'April', 5 => 'Mai', 6 => 'Juni',
				7 => 'Juli', 8 => 'August', 9 => 'September',
				10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
		];

		$period = $monate[$period_num] ?? '';
		$opts_general = (array) get_option('cmx_einstellungen', []);
		$use_leistungszeitraum = \function_exists(__NAMESPACE__ . '\\cmx_belege_uses_leistungszeitraum')
			? cmx_belege_uses_leistungszeitraum($opts_general)
			: !empty($opts_general['belege_use_leistungszeitraum']);
		if (!$use_leistungszeitraum) {
			$period = '';
		}

		$waehrung = cmxbu_first_meta($post_id, ['_cmx_beleg_waehrung']);

		return ['date_invoice'=>$date_invoice,'date_due'=>$date_due,'period'=>$period,'currency'=>$waehrung];
	}
}

/** ------- Notizen (Sanitizing/De-Escaping) ------- */
if (!function_exists(__NAMESPACE__.'\\cmxbu_kses_allow')) {
	function cmxbu_kses_allow(): array {
		return [
			'a'=>['href'=>[], 'title'=>[], 'target'=>[], 'rel'=>[]],
			'br'=>[], 'em'=>[], 'i'=>[], 'strong'=>[], 'b'=>[], 'u'=>[],
			'p'=>['style'=>[]], 'ul'=>[], 'ol'=>[], 'li'=>[], 'code'=>[], 'pre'=>[],
			'span'=>['style'=>[]],
		];
	}
}
if (!function_exists(__NAMESPACE__.'\\cmxbu_deep_unslash')) {
	function cmxbu_deep_unslash($v) {
		if (is_array($v)) return array_map('CLOUDMEISTER\\CMX\\Buero\\cmxbu_deep_unslash', $v);
		return is_string($v) ? wp_unslash($v) : $v;
	}
}
if (!function_exists(__NAMESPACE__.'\\cmxbu_sanitize_note_html')) {
	function cmxbu_sanitize_note_html($val): array {
		if (is_array($val)) $val = implode("\n", array_map('strval', $val));
		$raw  = is_string($val) ? wp_unslash($val) : '';
		$raw  = str_replace(["\r\n", "\r"], "\n", $raw);
		$has_tags = $raw !== '' && $raw !== wp_strip_all_tags($raw);
		$html = $raw !== '' ? wp_kses($raw, cmxbu_kses_allow()) : '';
		if (!$has_tags && strpos($raw, "\n") !== false) {
			$html = nl2br(esc_html($raw));
		}
		$text = trim(wp_strip_all_tags($html !== '' ? $html : $raw));
		if ($html === '' && $text !== '') $html = nl2br(esc_html($text));
		return ['html'=>$html, 'text'=>$text, 'raw'=>$raw];
	}
}

/** =========================
 * Kontakt „Das bin ich“ & bevorzugte Bank
 * ========================= */
if (!function_exists(__NAMESPACE__ . '\\cmxbu_meta_first')) {
	/**
	 * Liefert den ersten NICHT-leeren Meta-Wert aus einer Key-Liste.
	 */
	function cmxbu_meta_first(int $post_id, array $keys, string $default = ''): string {
		foreach ($keys as $key) {
			$val = get_post_meta($post_id, $key, true);
			if (is_array($val)) {
				$val = reset($val);
			}
			$val = is_string($val) ? trim($val) : (string) $val;
			if ($val !== '') return $val;
		}
		return $default;
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmxbu_take_first_token')) {
	/**
	 * Nimmt bei Mehrfachwerten (durch Komma/Semikolon/Zeilenumbruch getrennt) den ersten Eintrag.
	 */
	function cmxbu_take_first_token(string $val): string {
		if ($val === '') return '';
		$parts = preg_split('/[;,\\r\\n]+/', $val);
		$first = trim((string)($parts[0] ?? ''));
		return $first;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_kontakt_id')) {
	function cmxbu_get_beleg_kontakt_id(int $post_id): int {
		$kontakt_meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID')
			: '_cmx_beleg_kontakt_id';
		$kontakt_id = (int) \get_post_meta($post_id, $kontakt_meta_key, true);
		if ($kontakt_id <= 0 && $kontakt_meta_key !== '_cmx_beleg_kontakt_id') {
			$kontakt_id = (int) \get_post_meta($post_id, '_cmx_beleg_kontakt_id', true);
		}
		return $kontakt_id;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbu_get_payrexx_contact_data')) {
	function cmxbu_get_payrexx_contact_data(int $kontakt_id): array {
		if ($kontakt_id <= 0) {
			return [
				'company' => '',
				'forename' => '',
				'surname' => '',
				'email' => '',
			];
		}

		$company = \trim((string) \get_post_meta($kontakt_id, '_company', true));
		if ($company === '') {
			$company = \trim((string) \get_the_title($kontakt_id));
		}
		$company_lc = \function_exists('mb_strtolower') ? \mb_strtolower($company) : \strtolower($company);
		if ($company_lc === 'firmenname fehlt') {
			$company = '';
		}

		$email = \sanitize_email(cmxbu_take_first_token(cmxbu_meta_first($kontakt_id, [
			'_cmx_email_1', 'cmx_email_1', 'email_1', 'e_mail_1', 'kontakt_email', 'email', 'e_mail', 'mail',
		])));
		if (!\is_email($email)) {
			$email = '';
		}

		return [
			'company' => $company,
			'forename' => \trim((string) \get_post_meta($kontakt_id, '_cmx_kontakte_vorname', true)),
			'surname' => \trim((string) \get_post_meta($kontakt_id, '_cmx_kontakte_nachname', true)),
			'email' => $email,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmxbu_generate_payrexx_qr_data_uri')) {
	function cmxbu_generate_payrexx_qr_data_uri(string $data, int $size_px = 260): string {
		$data = \trim($data);
		if ($data === '') {
			return '';
		}

		$size_px = \max(120, \min(800, (int) $size_px));

		try {
			if (!\class_exists(\Endroid\QrCode\QrCode::class) || !\class_exists(\Endroid\QrCode\Writer\PngWriter::class)) {
				return '';
			}

			$qr = \Endroid\QrCode\QrCode::create($data)
				->setSize($size_px)
				->setMargin(0);

			$writer = new \Endroid\QrCode\Writer\PngWriter();
			$result = $writer->write($qr);
			$png = $result->getString();

			if (!\is_string($png) || $png === '') {
				return '';
			}

			return 'data:image/png;base64,' . \base64_encode($png);
		} catch (\Throwable $e) {
			if (\function_exists(__NAMESPACE__ . '\\cmxbu_log')) {
				cmxbu_log('Payrexx-QR konnte nicht erzeugt werden', ['error' => $e->getMessage()]);
			}
			return '';
		}
	}
}



if (!function_exists(__NAMESPACE__.'\\cmxbu_get_preferred_bank')) {
	function cmxbu_get_preferred_bank(): array {
		if (function_exists(__NAMESPACE__ . '\\cmx_get_active_bank')) {
			$active = cmx_get_active_bank();
			if (!empty($active)) {
				return [
					'bank_name'=>$active['name'] ?? ($active['label'] ?? ''),
					'iban'=>$active['iban'] ?? '',
					'qr_iban'=>$active['qr_iban'] ?? '',
					'bic'=>$active['bic'] ?? '',
				];
			}
		}

		$opts = (array)get_option('cmx_einstellungen', []);
		if (!empty($opts['bank_preferred_id'])) {
			$pid=(int)$opts['bank_preferred_id'];
			if ($pid>0 && ($p=get_post($pid))) {
				$m=get_post_meta($pid);
				return [
					'bank_name'=>$m['_bank_name'][0]??$p->post_title,
					'iban'=>$m['_iban'][0]??'',
					'qr_iban'=>$m['_qr_iban'][0]??'',
					'bic'=>$m['_bic'][0]??'',
				];
			}
		}
		$q = get_posts([
			'post_type'=>['bank','banken'],
			'post_status'=>['publish','private'],
			'posts_per_page'=>1,
			'meta_query'=>[
				'relation'=>'OR',
				['key'=>'_cmx_bank_preferred','value'=>['1','yes','true','on'],'compare'=>'IN'],
				['key'=>'_bank_preferred','value'=>['1','yes','true','on'],'compare'=>'IN'],
			],
			'no_found_rows'=>true,'suppress_filters'=>true,
		]);
		if (!empty($q)) { $p=$q[0]; $m=get_post_meta($p->ID);
			return [
				'bank_name'=>$m['_bank_name'][0]??$p->post_title,
				'iban'=>$m['_iban'][0]??'',
				'qr_iban'=>$m['_qr_iban'][0]??'',
				'bic'=>$m['_bic'][0]??'',
			];
		}
		return [
			'bank_name'=>$opts['bank_name']??'Bank',
			'iban'=>$opts['iban']??'',
			'qr_iban'=>$opts['qr_iban']??'',
			'bic'=>$opts['bic']??'',
		];
	}
}

/** =========================
 * Artikel-Belegtext (robust)
 * ========================= */
if (!function_exists(__NAMESPACE__.'\\cmxbu_get_article_belegtext')) {
	function cmxbu_get_article_belegtext(int $artikel_id): string {
		if ($artikel_id<=0) return '';
		$keys = [
			'cmx_artikel_belegtext','_cmx_artikel_belegtext',
			'cmx_artikel_beleg_text','_cmx_artikel_beleg_text', // <— NEU: exakter Key laut Vorgabe
			'belegtext','_belegtext','cmx_belegtext','_cmx_belegtext',
			'artikel_belegtext','_artikel_belegtext','beleg_text','_beleg_text'
		];
		foreach ($keys as $key) {
			$val = get_post_meta($artikel_id, $key, true);
			if (is_string($val) && $val !== '' && strpos($val, 'field_') !== 0) return $val;
		}
		$meta = get_post_meta($artikel_id);
		foreach ($keys as $k) {
			if (!empty($meta[$k][0]) && strpos((string)$meta[$k][0], 'field_') !== 0) return (string)$meta[$k][0];
		}
		return '';
	}
}

if (!function_exists(__NAMESPACE__.'\\cmxbu_article_variant_normalize_text')) {
	function cmxbu_article_variant_normalize_text(string $value): string {
		$value = \trim($value);
		if ($value === '') {
			return '';
		}
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_pdf_decode_label_text')) {
			$value = (string) cmxbu_pdf_decode_label_text($value);
		}
		$value = (string) \preg_replace('/\s+/u', ' ', $value);
		if (\function_exists('mb_strtolower')) {
			$value = (string) \mb_strtolower($value, 'UTF-8');
		} else {
			$value = (string) \strtolower($value);
		}
		return \trim($value);
	}
}

if (!function_exists(__NAMESPACE__.'\\cmxbu_article_variant_term_name')) {
	function cmxbu_article_variant_term_name(string $taxonomy, int $term_id): string {
		$taxonomy = \sanitize_key($taxonomy);
		if ($taxonomy === '' || $term_id <= 0 || !\taxonomy_exists($taxonomy)) {
			return '';
		}
		$term = \get_term($term_id, $taxonomy);
		if (!$term || \is_wp_error($term)) {
			return '';
		}
		return \trim((string) $term->name);
	}
}

if (!function_exists(__NAMESPACE__.'\\cmxbu_article_variant_taxonomy_label')) {
	function cmxbu_article_variant_taxonomy_label(string $taxonomy): string {
		$taxonomy = \sanitize_key($taxonomy);
		if ($taxonomy === '' || !\taxonomy_exists($taxonomy)) {
			return '';
		}
		$tax_obj = \get_taxonomy($taxonomy);
		if (!$tax_obj || !isset($tax_obj->labels) || !\is_object($tax_obj->labels)) {
			return '';
		}
		return \trim((string) ($tax_obj->labels->singular_name ?? $tax_obj->labels->name ?? ''));
	}
}

if (!function_exists(__NAMESPACE__.'\\cmxbu_get_article_variant_entries')) {
	function cmxbu_get_article_variant_entries(int $artikel_id): array {
		static $cache = [];
		if (isset($cache[$artikel_id])) {
			return $cache[$artikel_id];
		}
		if ($artikel_id <= 0) {
			$cache[$artikel_id] = [];
			return [];
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_artikel_variant_rows_load') && \function_exists(__NAMESPACE__ . '\\cmx_artikel_variant_taxonomy_choices')) {
			$stored = (array) cmx_artikel_variant_rows_load(
				$artikel_id,
				(array) cmx_artikel_variant_taxonomy_choices('Grössen'),
				(array) cmx_artikel_variant_taxonomy_choices('Farben')
			);
		} else {
			$stored = \get_post_meta($artikel_id, '_cmx_artikel_variant_rows', true);
		}
		if (!\is_array($stored) || empty($stored)) {
			$cache[$artikel_id] = [];
			return [];
		}

		$parent_title = (string) \get_the_title($artikel_id);
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_pdf_decode_label_text')) {
			$parent_title = (string) cmxbu_pdf_decode_label_text($parent_title);
		}
		$parent_title = \trim($parent_title);
		$entries = [];

		foreach (\array_values($stored) as $index => $row) {
			if (!\is_array($row)) {
				continue;
			}
			$sku = \trim((string) ($row['sku'] ?? ''));
			$title_parts = [];
			$variants = [];
			foreach ([
				[
					'taxonomy' => \sanitize_key((string) ($row['left_taxonomy'] ?? '')),
					'term_id'  => (int) ($row['left_term_id'] ?? 0),
				],
				[
					'taxonomy' => \sanitize_key((string) ($row['right_taxonomy'] ?? '')),
					'term_id'  => (int) ($row['right_term_id'] ?? 0),
				],
			] as $variant_row) {
				$taxonomy = (string) ($variant_row['taxonomy'] ?? '');
				$term_name = \function_exists(__NAMESPACE__ . '\\cmxbu_article_variant_term_name')
					? (string) cmxbu_article_variant_term_name($taxonomy, (int) ($variant_row['term_id'] ?? 0))
					: '';
				if ($taxonomy === '' || $term_name === '') {
					continue;
				}
				$title_parts[] = $term_name;
				$variants[] = [
					'label' => \function_exists(__NAMESPACE__ . '\\cmxbu_article_variant_taxonomy_label')
						? (string) cmxbu_article_variant_taxonomy_label($taxonomy)
						: '',
					'value' => $term_name,
				];
			}

			$title = $parent_title !== '' ? $parent_title : '(ohne Titel)';
			if (!empty($title_parts)) {
				$title .= ' - ' . \implode(' / ', $title_parts);
			} elseif ($sku !== '') {
				$title .= ' - ' . $sku;
			}
			if (\function_exists(__NAMESPACE__ . '\\cmxbu_pdf_decode_label_text')) {
				$title = (string) cmxbu_pdf_decode_label_text($title);
			}

			$entries[] = [
				'index'     => (int) $index,
				'title'     => \trim($title),
				'title_key' => \function_exists(__NAMESPACE__ . '\\cmxbu_article_variant_normalize_text')
					? (string) cmxbu_article_variant_normalize_text($title)
					: \trim($title),
				'belegtext' => \sanitize_textarea_field((string) ($row['belegtext'] ?? '')),
				'variants'  => $variants,
			];
		}

		$cache[$artikel_id] = $entries;
		return $entries;
	}
}

if (!function_exists(__NAMESPACE__.'\\cmxbu_resolve_position_variant_entry')) {
	function cmxbu_resolve_position_variant_entry(int $artikel_id, array $row): ?array {
		if ($artikel_id <= 0 || !\function_exists(__NAMESPACE__ . '\\cmxbu_get_article_variant_entries')) {
			return null;
		}

		$selected_index = isset($row['artikel_variant_index']) && $row['artikel_variant_index'] !== ''
			? (int) $row['artikel_variant_index']
			: null;
		if ($selected_index !== null) {
			foreach ((array) cmxbu_get_article_variant_entries($artikel_id) as $entry) {
				if (!\is_array($entry)) {
					continue;
				}
				if ((int) ($entry['index'] ?? -1) === $selected_index) {
					return $entry;
				}
			}
		}

		$selected_title = \trim((string) ($row['artikel_name'] ?? $row['item'] ?? $row['title'] ?? ''));
		if ($selected_title === '') {
			return null;
		}
		$selected_key = \function_exists(__NAMESPACE__ . '\\cmxbu_article_variant_normalize_text')
			? (string) cmxbu_article_variant_normalize_text($selected_title)
			: $selected_title;
		if ($selected_key === '') {
			return null;
		}

		$parent_title = (string) \get_the_title($artikel_id);
		$parent_key = \function_exists(__NAMESPACE__ . '\\cmxbu_article_variant_normalize_text')
			? (string) cmxbu_article_variant_normalize_text($parent_title)
			: \trim($parent_title);
		if ($parent_key !== '' && $selected_key === $parent_key) {
			return null;
		}

		foreach ((array) cmxbu_get_article_variant_entries($artikel_id) as $entry) {
			if (!\is_array($entry)) {
				continue;
			}
			$entry_key = \trim((string) ($entry['title_key'] ?? ''));
			if ($entry_key !== '' && $entry_key === $selected_key) {
				return $entry;
			}
		}

		return null;
	}
}

if (!function_exists(__NAMESPACE__.'\\cmxbu_build_article_variant_html')) {
	function cmxbu_build_article_variant_html(array $variants): string {
		$html = '';
		foreach ($variants as $variant) {
			if (!\is_array($variant)) {
				continue;
			}
			$label = \trim((string) ($variant['label'] ?? ''));
			$value = \trim((string) ($variant['value'] ?? ''));
			if ($value === '') {
				continue;
			}
			$html .= '<div class="cmx-pdf-article-variant-line">';
			if ($label !== '') {
				$html .= '<span class="cmx-pdf-article-variant-label">' . \esc_html($label) . ':</span> ';
			}
			$html .= \esc_html($value) . '</div>';
		}
		return $html;
	}
}
/** =========================
 * Positionen + Berechnung
 * ========================= */
if (!function_exists(__NAMESPACE__.'\\cmxbu_parse_tax_rate')) {
	/**
	 * Versucht einen MwSt-Satz (z. B. "7.7%" oder "7,7") in Dezimalform (0.077) zu bringen.
	 */
	function cmxbu_parse_tax_rate($raw): float {
		if ($raw === null) return 0.0;
		$str = trim((string)$raw);
		if ($str === '') return 0.0;

		$str = str_replace(['\'', ' '], '', $str);
		$str = str_replace(',', '.', $str);

		if (preg_match('~([\\-−]?[0-9]+(?:\\.[0-9]+)?)\\s*%?~u', $str, $m)) {
			$val = (float)$m[1];
			return $val > 0 ? $val / 100 : 0.0;
		}

		return 0.0;
	}
}

if (!function_exists(__NAMESPACE__.'\\cmxbu_get_mwst_term_data')) {
	/**
	 * Holt Daten zum gewählten MwSt-Term (Rate in Dezimal, Label).
	 */
	function cmxbu_get_mwst_term_data(int $term_id): array {
		$rate = 0.0;
		$label = '';

		if ($term_id > 0) {
			$term = get_term($term_id, 'belege_mwst');
			if (!is_wp_error($term) && $term instanceof \WP_Term) {
				$label = (string)$term->name;

				$sources = [
					get_term_meta($term_id, 'mwst', true),
					get_term_meta($term_id, 'mwst_rate', true),
					get_term_meta($term_id, 'rate', true),
					get_term_meta($term_id, 'steuer', true),
					get_term_meta($term_id, 'tax_rate', true),
					$term->description ?? '',
					$term->name ?? '', // bevorzugt, da der Slug bei "7,7%" zu "77" wird
					$term->slug ?? '',
				];

				foreach ($sources as $src) {
					$rate = cmxbu_parse_tax_rate($src);
					if ($rate > 0) break;
				}
			}
		}

		return [
			'rate'  => $rate,
			'label' => $label,
		];
	}
}

if (!function_exists(__NAMESPACE__.'\\cmxbu_get_beleg_positionen_calc')) {
	function cmxbu_pdf_decode_label_text(string $value): string {
		$value = \trim($value);
		for ($i = 0; $i < 2; $i++) {
			$decoded = \html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			if (!\is_string($decoded) || $decoded === $value) {
				break;
			}
			$value = $decoded;
		}
		return \str_replace("\u{00A0}", ' ', $value);
	}

	function cmxbu_get_beleg_positionen_calc(int $post_id, array $opts=[]): array {
		$opts = array_replace([
			'round_decimals'=>2,
			'round_lines'=>true,
			'round_totals'=>true,
			'currency'=>'CHF',
			'tax_rate'=>0.0,      // Dezimal, z. B. 0.077
			'is_brutto'=>false,   // Preise sind bereits inkl. MwSt
		], $opts);

		$raw = get_post_meta($post_id, '_cmx_beleg_positionen', true);
		if (is_string($raw) && $raw!=='') {
			$tmp = json_decode($raw, true);
			if (json_last_error()!==JSON_ERROR_NONE) $tmp = @unserialize($raw);
			$rows = is_array($tmp) ? $tmp : [];
		} elseif (is_array($raw)) {
			$rows = $raw;
		} else {
			$rows = [];
		}
		if (isset($_POST['cmx_positionen']) && is_array($_POST['cmx_positionen'])) {
			$posted_post_id = 0;
			if (isset($_POST['post_ID'])) {
				$posted_post_id = (int) $_POST['post_ID'];
			} elseif (isset($_POST['post_id'])) {
				$posted_post_id = (int) $_POST['post_id'];
			}
			if ($posted_post_id === $post_id) {
				$rows = cmxbu_deep_unslash($_POST['cmx_positionen']);
			}
		}

		$norm_minus = static function(string $s): string {
			return str_replace(["\xE2\x88\x92", "−"], "-", $s);
		};
		$to_float = static function($v) use ($norm_minus): float {
			if (!is_string($v)) $v = (string)($v ?? '');
			$v = $norm_minus($v);
			$v = str_replace(["'", ' '], '', $v);
			$v = str_replace(',', '.', $v);
			return is_numeric($v) ? (float)$v : 0.0;
		};
		$to_str = static function($v): string { return is_string($v) ? $v : ''; };
		$round_5rp = static function(float $amount): float {
			if (function_exists(__NAMESPACE__ . '\\cmx_round_5rp')) {
				return (float) cmx_round_5rp($amount);
			}
			return round($amount * 20) / 20;
		};

		$out = [
			'positionen'=>[],
			'subtotal'=>0.0,
			'total'=>0.0,
			'any_discount'=>false,
			'tax_rate'=>(float)$opts['tax_rate'],
			'is_brutto'=>(bool)$opts['is_brutto'],
			'tax_amount'=>0.0,
			'net'=>0.0,
			'gross'=>0.0,
		];

		foreach ($rows as $i=>$r) {
			if (!is_array($r)) $r=['artikel_id'=>0,'menge'=>1,'preis'=>0.0,'rabatt'=>'','beschreibung'=>''];
			$row_type = \sanitize_key((string)($r['typ'] ?? $r['row_type'] ?? ''));
			if ($row_type === 'abschnitt') {
				$section_title = cmxbu_pdf_decode_label_text(\trim((string)($r['abschnitt_titel'] ?? $r['section_title'] ?? '')));
				$section_text_raw = $r['abschnitt_text'] ?? $r['section_text'] ?? $r['beschreibung'] ?? '';
				$section_text = cmxbu_sanitize_note_html($section_text_raw);
				if ($section_title === '' && $section_text['text'] === '') {
					continue;
				}
				$out['positionen'][] = [
					'index'                 => $i,
					'row_type'              => 'abschnitt',
					'section_title'         => $section_title,
					'section_text'          => (string)($section_text['text'] ?? ''),
					'section_text_html'     => (string)($section_text['html'] ?? ''),
					'article_number'        => '',
					'title'                 => '',
					'qty'                   => 0.0,
					'unit_price'            => 0.0,
					'line_total'            => 0.0,
					'discount'              => '',
					'has_discount'          => false,
					'desc_text'             => '',
					'desc_html'             => '',
					'desc_raw'              => '',
					'article_belegtext_html'=> '',
					'article_variant_html'  => '',
				];
				continue;
			}

			$artikel_id = (int)($r['artikel_id'] ?? 0);
			$unit_data = \function_exists(__NAMESPACE__ . '\\cmx_beleg_resolve_position_unit')
				? cmx_beleg_resolve_position_unit((array) $r, $artikel_id)
				: [
					'einheit_id' => (int) ($r['einheit_id'] ?? ($r['unit_id'] ?? 0)),
					'unit'       => \sanitize_text_field((string) ($r['unit'] ?? ($r['einheit'] ?? ''))),
				];
			$unit      = (string) ($unit_data['unit'] ?? '');
			$einheit_id = (int) ($unit_data['einheit_id'] ?? 0);

			// C: Gespeicherten Titel bevorzugen; nur wenn leer, aus Artikel holen
			$title_saved = cmxbu_pdf_decode_label_text($to_str($r['artikel_name'] ?? $r['item'] ?? $r['title'] ?? ''));
			$title       = $title_saved !== '' ? $title_saved : ($artikel_id ? cmxbu_pdf_decode_label_text((string) (get_the_title($artikel_id) ?: '')) : '');

			$artnr      = '';

			if ($artikel_id>0) {
				$artnr = get_post_meta($artikel_id,'cmx_artikel_sku',true);
				if ($artnr==='' || $artnr===null) $artnr = get_post_meta($artikel_id,'_cmx_artikel_sku',true);
				if ($artnr==='' || $artnr===null) $artnr = get_post_meta($artikel_id,'_cmx_artikel_nr',true);
				if ($artnr==='' || $artnr===null) $artnr = get_post_meta($artikel_id,'_sku',true);
				$artnr = is_string($artnr)?$artnr:'';
			}

			$qty      = $to_float($r['menge'] ?? 1);
			$uprice   = $to_float($r['preis'] ?? 0);
			$rabatt_r = $to_str($r['rabatt'] ?? '');
			$note_in  = $r['beschreibung'] ?? '';

			// B: Zusatznotiz der Position (HTML-saniert)
			$note = cmxbu_sanitize_note_html($note_in);

			// A: Artikel-Belegtext laden (inkl. exaktem Key cmx_artikel_beleg_text)
			$belegtext_raw = '';
			$article_variant_html = '';
			if ($artikel_id) {
				$belegtext_raw = cmxbu_get_article_belegtext($artikel_id);
			} else {
				$pos_sku = $to_str($r['sku'] ?? $r['artnr'] ?? $r['artikelnummer'] ?? $r['nr'] ?? '');
				if ($pos_sku !== '') {
					$sku_post = get_posts([
						'post_type'=>['artikel','product','produkt'],
						'post_status'=>['publish','private','draft'],
						'fields'=>'ids','no_found_rows'=>true,'posts_per_page'=>1,
						'meta_query'=>[
							'relation'=>'OR',
							['key'=>'cmx_artikel_sku', 'value'=>$pos_sku,'compare'=>'='],
							['key'=>'_cmx_artikel_sku','value'=>$pos_sku,'compare'=>'='],
							['key'=>'_cmx_artikel_nr', 'value'=>$pos_sku,'compare'=>'='],
							['key'=>'_sku',            'value'=>$pos_sku,'compare'=>'='],
						],
					]);
					if (!empty($sku_post)) {
						$artikel_id = (int)$sku_post[0];
						if ($title === '') $title = cmxbu_pdf_decode_label_text((string) (get_the_title($artikel_id) ?: ''));
						$belegtext_raw = cmxbu_get_article_belegtext($artikel_id);
					}
				}
			}
				if ($artikel_id > 0 && \function_exists(__NAMESPACE__ . '\\cmxbu_resolve_position_variant_entry')) {
					$variant_entry = cmxbu_resolve_position_variant_entry($artikel_id, (array) $r);
					if (!\is_array($variant_entry) && \function_exists(__NAMESPACE__ . '\\cmxbu_get_article_variant_entries')) {
						$variant_entries = \array_values((array) cmxbu_get_article_variant_entries($artikel_id));
						if (!empty($variant_entries)) {
							$belegtext_key = \function_exists(__NAMESPACE__ . '\\cmxbu_article_variant_normalize_text')
								? (string) cmxbu_article_variant_normalize_text((string) $belegtext_raw)
								: \trim((string) $belegtext_raw);
							foreach ($variant_entries as $candidate) {
								if (!\is_array($candidate)) {
									continue;
								}
								$candidate_belegtext = \trim((string) ($candidate['belegtext'] ?? ''));
								$candidate_key = \function_exists(__NAMESPACE__ . '\\cmxbu_article_variant_normalize_text')
									? (string) cmxbu_article_variant_normalize_text($candidate_belegtext)
									: $candidate_belegtext;
								if ($belegtext_key !== '' && $candidate_key !== '' && $candidate_key === $belegtext_key) {
									$variant_entry = $candidate;
									break;
								}
							}
							if (!\is_array($variant_entry)) {
								$parent_title = (string) \get_the_title($artikel_id);
								$current_title_key = \function_exists(__NAMESPACE__ . '\\cmxbu_article_variant_normalize_text')
									? (string) cmxbu_article_variant_normalize_text((string) $title)
									: \trim((string) $title);
								$parent_title_key = \function_exists(__NAMESPACE__ . '\\cmxbu_article_variant_normalize_text')
									? (string) cmxbu_article_variant_normalize_text($parent_title)
									: \trim($parent_title);
								if ($current_title_key !== '' && $current_title_key === $parent_title_key) {
									$variant_entry = $variant_entries[0];
								}
							}
						}
					}
					if (\is_array($variant_entry)) {
						$variant_belegtext = \trim((string) ($variant_entry['belegtext'] ?? ''));
						if ($variant_belegtext !== '') {
						$belegtext_raw = $variant_belegtext;
					}
					$article_variant_html = \function_exists(__NAMESPACE__ . '\\cmxbu_build_article_variant_html')
						? (string) cmxbu_build_article_variant_html((array) ($variant_entry['variants'] ?? []))
						: '';
				}
			}
			if ($unit === '' && $einheit_id <= 0 && $artikel_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_beleg_resolve_position_unit')) {
				$unit_data = cmx_beleg_resolve_position_unit((array) $r, $artikel_id);
				$unit = (string) ($unit_data['unit'] ?? '');
				$einheit_id = (int) ($unit_data['einheit_id'] ?? 0);
			}
			$belegtext_arr  = cmxbu_sanitize_note_html($belegtext_raw);
			$belegtext_html = $belegtext_arr['html'];

			if (trim($rabatt_r) !== '') $out['any_discount'] = true;

			$line_subtotal = $qty * $uprice;

			$has_discount=false; $disc_amount=0.0; $disc_display='';
			$txt = strtolower(trim($rabatt_r));
			if ($txt !== '') {
				if (preg_match('~([\-−]?\d+[.,]?\d*)\s*%~u', $txt, $m)) {
					$pct = abs($to_float($m[1]));
					if ($pct>0) {
						$disc_amount = abs($line_subtotal) * ($pct/100);
						$disc_display = rtrim(rtrim(cmx_format_swiss_number($pct, 2), '0'), '.').'%';
						$has_discount = true;
					}
				} else {
					$clean = preg_replace('~(chf|fr\.?)~i','',$txt);
					$val = abs($to_float($clean));
					if ($val>0) {
						$disc_amount = min($val, abs($line_subtotal));
						$disc_display = cmx_format_swiss_number($val, 2) . ' ' . $opts['currency'];
						$has_discount= true;
					}
				}
			}

			$line_total = ($line_subtotal >= 0)
				? ($line_subtotal - $disc_amount)
				: ($line_subtotal + $disc_amount);

			if (!empty($opts['round_lines'])) $line_total = round($line_total, (int)$opts['round_decimals']);

			$out['positionen'][] = [
				'index'                 => $i,
				'row_type'              => 'position',
				'section_title'         => '',
				'section_text'          => '',
				'section_text_html'     => '',
				'article_number'        => $artnr,
				'title'                 => $title,
				'qty'                   => $qty,
				'unit'                  => $unit,
				'einheit_id'            => $einheit_id,
				'unit_price'            => round($uprice,(int)$opts['round_decimals']),
				'line_total'            => $line_total,
				'discount'              => $disc_display,
				'has_discount'          => $has_discount,
				'desc_text'             => $note['text'],          // Zusatznotiz
				'desc_html'             => $note['html'],
				'desc_raw'              => $note['raw'],
				'article_belegtext_html'=> $belegtext_html,        // Beleg-Text (CPT Artikel)
				'article_variant_html'  => $article_variant_html,
			];

			$out['subtotal'] += $line_total;
		}

		if (!empty($opts['round_totals'])) $out['subtotal'] = round($out['subtotal'], (int)$opts['round_decimals']);

		$rate     = max(0.0, (float)$opts['tax_rate']);
		$isBrutto = !empty($opts['is_brutto']);

		$net   = $out['subtotal'];
		$gross = $out['subtotal'];
		$tax   = 0.0;

		if ($rate > 0) {
			if ($isBrutto) {
				$gross = $round_5rp((float)$out['subtotal']);
				$net   = $gross / (1 + $rate);
				$tax   = $gross - $net;
			} else {
				$net   = $out['subtotal'];
				$tax   = $net * $rate;
				$gross = $net + $tax;
			}

			if (!empty($opts['round_totals'])) {
				$net   = round($net, (int)$opts['round_decimals']);
				$tax   = round($tax, (int)$opts['round_decimals']);
				$gross = round($gross, (int)$opts['round_decimals']);
			}

			// Nur den Totalbetrag auf 5 Rappen runden (CH-Rundung).
			$gross = $round_5rp($gross);
		}

		$out['net']        = $net;
		$out['gross']      = $gross;
		$out['tax_amount'] = round($tax, (int)$opts['round_decimals']);
		$out['subtotal']   = $net;
		$out['total']      = $gross;

		$has_positions = false;
		foreach ($rows as $row) {
			if (!is_array($row)) continue;
			if (\sanitize_key((string)($row['typ'] ?? '')) === 'abschnitt') continue;
			$item = cmxbu_pdf_decode_label_text(trim((string)($row['artikel_name'] ?? $row['item'] ?? $row['title'] ?? '')));
			$qty = $to_float($row['menge'] ?? $row['qty'] ?? 0);
			$price = $to_float($row['preis'] ?? $row['unit_price'] ?? 0);
			$total = $to_float($row['line_total'] ?? 0);
			if ($item !== '' || $qty > 0 || $price > 0 || $total > 0) {
				$has_positions = true;
				break;
			}
		}
		$override = (string) get_post_meta($post_id, '_cmx_beleg_summe_override', true);
		if (!$has_positions && $override !== '') {
			$ov = $to_float($override);
			$rate = max(0.0, (float)$opts['tax_rate']);
			$isBruttoOverride = !empty($opts['is_brutto']);
			$net = $ov;
			$gross = $ov;
			$tax = 0.0;
			if ($rate > 0.0) {
				if ($isBruttoOverride) {
					$gross = $round_5rp($ov);
					$net = $gross / (1 + $rate);
					$tax = $gross - $net;
				} else {
					$net = $ov;
					$tax = $net * $rate;
					$gross = $net + $tax;
				}
				if (!empty($opts['round_totals'])) {
					$net = round($net, (int)$opts['round_decimals']);
					$tax = round($tax, (int)$opts['round_decimals']);
					$gross = round($gross, (int)$opts['round_decimals']);
				}
				$gross = $round_5rp($gross);
			}
			$out['subtotal'] = $net;
			$out['total'] = $gross;
			$out['net'] = $net;
			$out['gross'] = $gross;
			$out['tax_amount'] = round($tax, (int)$opts['round_decimals']);
		}
		return $out;
	}
}

/** =========================
 * Generator (save_post_belege)
 * ========================= */
add_action('save_post_belege', __NAMESPACE__.'\\cmxbu_generate_document_on_save', 999, 3);
	function cmxbu_generate_document_on_save(int $post_id, \WP_Post $post, bool $update): void {
		if ($post->post_type !== 'belege') return;
		if (!empty($GLOBALS['cmx_skip_beleg_pdf_generation'])) { cmxbu_log('ABBRUCH: PDF-Generation per Flag.', compact('post_id')); return; }
		if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) { cmxbu_log('ABBRUCH: Revision/Autosave.',compact('post_id')); return; }
		if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) { cmxbu_log('ABBRUCH: DOING_AUTOSAVE.'); return; }
		$allow_internal = \function_exists(__NAMESPACE__ . '\\cmx_woocommerce_allows_internal_write')
			? cmx_woocommerce_allows_internal_write()
			: false;
		if (!$allow_internal && !current_user_can('edit_post',$post_id)) { cmxbu_log('ABBRUCH: Permission.'); return; }
		if (in_array($post->post_status, ['auto-draft','draft','trash'], true)) { cmxbu_log('ABBRUCH: draft/auto-draft/trash.',compact('post_id')); return; }
		$title_raw_check = (string)$post->post_title;
		if (str_starts_with($title_raw_check, 'automatisch-gespeicherter-entwurf')) {
			cmxbu_log('ABBRUCH: auto-draft Titel.', compact('post_id'));
			return;
		}

		static $in_progress=[]; if (!empty($in_progress[$post_id])) { cmxbu_log('ABBRUCH: bereits in Arbeit.',compact('post_id')); return; }
		$in_progress[$post_id]=true;

		// $adr = get_post_meta($post_id);
		// $cmx_beleg_adress = nl2br(get_post_meta($post_id)['_cmx_beleg_kontakt_addr'][0]);
		$raw_addr = get_post_meta($post_id, '_cmx_beleg_kontakt_addr', true);
		$cmx_beleg_adress = $raw_addr ? nl2br($raw_addr) : '';

		// var_dump($cmx_beleg_adress); exit;

		// Belegtyp (z. B. rechnung, offerte, lieferantenrechnung, gutschrift, ...)
		$beleg_type='rechnung';
		foreach (['belege_kategorien','beleg_kategorie'] as $tax) {
			$slugs=wp_get_post_terms($post_id,$tax,['fields'=>'slugs']);
			if (!is_wp_error($slugs) && !empty($slugs)) { $beleg_type=(string)$slugs[0]; break; }
		}
		$override_type = (string) get_post_meta($post_id, '_cmx_beleg_pdf_type', true);
		if ($override_type !== '' && in_array($override_type, ['rechnung', 'offerte', 'lieferschein'], true) && $beleg_type !== 'gutschrift') {
			$beleg_type = $override_type;
		}
		$beleg_type = apply_filters('cmx_beleg_pdf_type', $beleg_type, $post_id);
		if (function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_pdf_effective_type')) {
			$beleg_type = (string) cmxbu_get_beleg_pdf_effective_type($post_id, (string) $beleg_type);
		}

		// Upload-Ziel
		if (!defined('CMX_UPLOADS_MISBUERO') || !CMX_UPLOADS_MISBUERO) {
			$up=wp_get_upload_dir();
			if (!defined(__NAMESPACE__.'\\CMX_UPLOADS_MISBUERO')) define(__NAMESPACE__.'\\CMX_UPLOADS_MISBUERO', trailingslashit($up['basedir']).'misbuero/');
		}
		$base_dir=rtrim(CMX_UPLOADS_MISBUERO,'/').'/archiv/'.date('Y').'/belege/';
		if (!wp_mkdir_p($base_dir) || !is_writable($base_dir)) { cmxbu_log('FEHLER: Upload-Verzeichnis',['dir'=>$base_dir]); return; }

		// Cleanup: automatisch gespeicherte Entwurf-Dateien entfernen
		foreach (['html','pdf'] as $ext) {
			$auto = $base_dir . 'automatisch-gespeicherter-entwurf_rechnung.' . $ext;
			if (is_file($auto)) { @unlink($auto); }
		}


		// Dateinamen
		$title_raw=(string)get_the_title($post_id);
		$title_safe = ($title_raw !== '') ? $title_raw : (string)$post_id;
		$file_type = $beleg_type;
		$basename=sanitize_title($title_safe.'_'.$file_type);
		$html_path=$base_dir.$basename.'.html';  // wird am Ende gelöscht
		$pdf_path =$base_dir.$basename.'.pdf';


		// Optionen/Labels
		$opts=(array)get_option('cmx_einstellungen',[]);
		$branding_logo = \CLOUDMEISTER\CMX\Buero\cmx_get_branding_logo();
		$beleg_richtung = \sanitize_key((string) \get_post_meta($post_id, '_cmx_beleg_richtung', true));
		$kontakt_id = \function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_kontakt_id')
			? cmxbu_get_beleg_kontakt_id($post_id)
			: 0;
		$counterparty_contact = [
			'phone' => '',
			'email' => '',
			'website' => '',
		];
		if ($beleg_richtung === 'eingang') {
			if ($kontakt_id > 0) {
				$counterparty_contact = [
					'phone' => cmxbu_take_first_token(cmxbu_meta_first($kontakt_id, [
						'_cmx_telefon_1', 'cmx_telefon_1', 'telefon_1', 'tel_1', 'phone_1', 'telefon', 'tel', 'phone',
					])),
					'email' => cmxbu_take_first_token(cmxbu_meta_first($kontakt_id, [
						'_cmx_email_1', 'cmx_email_1', 'email_1', 'e_mail_1', 'kontakt_email', 'email', 'e_mail', 'mail',
					])),
					'website' => cmxbu_take_first_token(cmxbu_meta_first($kontakt_id, [
						'_cmx_kontakte_url', 'cmx_kontakte_meta_url', 'cmx_kontakte_url', '_cmx_url', 'cmx_url', '_website', 'website', 'url',
					])),
				];
			}
			if ($kontakt_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_get_contact_logo')) {
				$kontakt_logo = (string) cmx_get_contact_logo($kontakt_id);
				if ($kontakt_logo !== '') {
					$branding_logo = $kontakt_logo;
				}
			}
		}
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_prepare_png_for_dompdf')) {
			$branding_logo = (string) cmxbu_prepare_png_for_dompdf((string) $branding_logo);
		}
		// var_dump(cmx_get_branding_logo()); exit;


		$labels = array_replace([
			'doc_invoice'=>'Rechnung','date'=>'Datum','due'=>'Fällig bis','period'=>'Leistung für',
			'subject'=>'Betreff','desc'=>'Beschreibung','item'=>'Artikel','artnr'=>'SKU',
			'qty'=>'Menge','unit_price'=>'Einzelpreis','discount'=>'Rabatt','line_total'=>'Summe','total'=>'Gesamtbetrag',
			'recipient'=>'Empfänger','bank'=>'Bank','contact'=>'Kontakt',
		], (array)($opts['labels']??[]));

		// Titel je nach Belegtyp ** Meine Helper Funktion nutzen
		$type_map = [
			'rechnung'             => 'Rechnung',
			'quittung'             => 'Quittung',
			'offerte'              => 'Offerte',
			'lieferantenrechnung'  => 'Lieferantenrechnung',
			'lieferantenquittung'  => 'Lieferantenquittung',
			'gutschrift'           => 'Gutschrift',
			'mahnung'              => 'Mahnung',
			'zahlungseingang'      => '',
		];
		$doc_label = $type_map[strtolower($beleg_type)] ?? ucfirst($beleg_type);
		$doc_title = trim(($doc_label !== '' ? ($doc_label . ' ') : '') . $title_safe);

		// Schweizer Format
		$fmt = ['currency'=>$opts['currency']??'CHF','decimals'=>2,'decimal'=>'.','thousands'=>"'" ];

		// Daten
		$opts_general = (array) get_option('cmx_einstellungen', []);
		$is_mwst_pflichtig = \function_exists(__NAMESPACE__ . '\\cmx_belege_is_mwst_pflichtig')
			? cmx_belege_is_mwst_pflichtig($opts_general)
			: !empty($opts_general['mwst_pflichtig']);
		$mwst_allowed_for_type = \function_exists(__NAMESPACE__ . '\\cmx_belege_allows_mwst_for_type')
			? cmx_belege_allows_mwst_for_type((string)$beleg_type, $opts_general)
			: $is_mwst_pflichtig;

		$is_brutto = get_post_meta($post_id, '_cmx_beleg_is_brutto', true) === '1';
		$mwst_term_id = (int)get_post_meta($post_id, '_cmx_beleg_mwst_term', true);
		$mwst = cmxbu_get_mwst_term_data($mwst_term_id);

		if (!$mwst_allowed_for_type) {
			$mwst['rate'] = 0.0;
			$is_brutto = false;
		}
		$dates = cmxbu_beleg_get_dates($post_id);
		$calc  = cmxbu_get_beleg_positionen_calc($post_id, [
			'round_decimals'=>2,
			'round_lines'=>true,
			'round_totals'=>true,
			'currency'=>$fmt['currency'],
			'tax_rate'=>$mwst['rate'],
			'is_brutto'=>$is_brutto,
		]);
		$has_positions = false;
		if (!empty($calc['positionen']) && is_array($calc['positionen'])) {
			foreach ($calc['positionen'] as $row) {
				if (!is_array($row)) continue;
				if (($row['row_type'] ?? '') === 'abschnitt') continue;
				$item = trim((string)($row['title'] ?? ''));
				$qty = (float)($row['qty'] ?? 0);
				$unit_price = (float)($row['unit_price'] ?? 0);
				$line_total = (float)($row['line_total'] ?? 0);
				if ($item !== '' || $qty > 0 || $unit_price > 0 || $line_total > 0) {
					$has_positions = true;
					break;
				}
			}
		}
		$manual_total_value = null;
		if (!$has_positions) {
			$round_5rp = static function(float $amount): float {
				if (function_exists(__NAMESPACE__ . '\\cmx_round_5rp')) {
					return (float) cmx_round_5rp($amount);
				}
				return round($amount * 20) / 20;
			};
			$override = '';
			$posted_post_id = 0;
			if (isset($_POST['post_ID'])) {
				$posted_post_id = (int) $_POST['post_ID'];
			} elseif (isset($_POST['post_id'])) {
				$posted_post_id = (int) $_POST['post_id'];
			}
			if ($posted_post_id === $post_id && isset($_POST['cmx_beleg_summe_override'])) {
				$override = (string) cmxbu_deep_unslash($_POST['cmx_beleg_summe_override']);
			}
			if ($override === '') {
				$override = (string) get_post_meta($post_id, '_cmx_beleg_summe_override', true);
			}
			if ($override !== '') {
				$ov = (float) cmx_norm_decimal($override);
				$manual_total_value = $ov;
				$rate = max(0.0, (float)($mwst['rate'] ?? 0.0));
				$net = $ov;
				$gross = $ov;
				$tax = 0.0;
				if ($rate > 0.0) {
					if ($is_brutto) {
						$gross = $round_5rp($ov);
						$net = $gross / (1 + $rate);
						$tax = $gross - $net;
					} else {
						$net = $ov;
						$tax = $net * $rate;
						$gross = $net + $tax;
					}
					$net = round($net, 2);
					$tax = round($tax, 2);
					$gross = round($gross, 2);
					// Nur den Totalbetrag auf 5 Rappen runden (CH-Rundung).
					$gross = $round_5rp($gross);
				}
				$calc['subtotal'] = $net;
				$calc['total'] = $gross;
				$calc['net'] = $net;
				$calc['gross'] = $gross;
				$calc['tax_amount'] = round($tax, 2);
			}
		}
		$anzahlungen_raw = get_post_meta($post_id, defined(__NAMESPACE__.'\\CMX_BELEG_META_ANZAHLUNGEN') ? CMX_BELEG_META_ANZAHLUNGEN : '_cmx_beleg_anzahlungen', true);
		if (is_string($anzahlungen_raw) && $anzahlungen_raw !== '') {
			$tmp = json_decode($anzahlungen_raw, true);
			$anzahlungen = (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) ? $tmp : [];
		} elseif (is_array($anzahlungen_raw)) {
			$anzahlungen = $anzahlungen_raw;
		} else {
			$anzahlungen = [];
		}
		$anzahlungen = array_values(array_filter(array_map(static function($row) {
			if (!is_array($row)) return null;
			$datum = trim((string)($row['datum'] ?? ''));
			$betrag = trim((string)($row['betrag'] ?? ''));
			if ($datum === '' || $betrag === '') return null;
			return ['datum' => $datum, 'betrag' => $betrag];
		}, $anzahlungen)));
		if (!empty($anzahlungen)) {
			usort($anzahlungen, static function (array $a, array $b): int {
				$ad = $a['datum'] ?? '';
				$bd = $b['datum'] ?? '';
				if ($ad === $bd) return 0;
				if ($ad === '') return 1;
				if ($bd === '') return -1;
				$at = strtotime($ad) ?: 0;
				$bt = strtotime($bd) ?: 0;
				if ($at === $bt) return strcmp($ad, $bd);
				return $at <=> $bt;
			});
		}
		$payment_amounts = cmxbu_get_beleg_payment_amounts((float) ($calc['total'] ?? 0.0), $anzahlungen);
		$me    = cmxbu_get_me_contact(); // var_dump(cmxbu_get_me_contact()); exit;

		$bank  = cmxbu_get_preferred_bank();
		$qr_iban = trim((string)($bank['qr_iban'] ?? ''));
		$bank_iban = trim((string)($bank['iban'] ?? ''));
			$payrexx_vpos_url = '';
			$payrexx_qr_data_uri = '';
			$offerte_accept_url = '';
			$offerte_reject_url = '';
			$payment_amount = (float) ($payment_amounts['payment_amount'] ?? ($calc['total'] ?? 0.0));
			if (
				$beleg_type === 'rechnung'
			&& $beleg_richtung === 'ausgang'
			&& $payment_amount > 0.0
			&& \function_exists(__NAMESPACE__ . '\\cmx_get_payrexx_vpos_url')
		) {
			$payrexx_base_url = (string) cmx_get_payrexx_vpos_url();
			if ($payrexx_base_url !== '') {
				$payrexx_terminal_id = \function_exists(__NAMESPACE__ . '\\cmx_get_payrexx_terminal_id')
					? \trim((string) cmx_get_payrexx_terminal_id())
					: '';
				$payrexx_contact = \function_exists(__NAMESPACE__ . '\\cmxbu_get_payrexx_contact_data')
					? cmxbu_get_payrexx_contact_data($kontakt_id)
					: ['company' => '', 'forename' => '', 'surname' => '', 'email' => ''];
				$payrexx_query = \http_build_query([
					'tid' => $payrexx_terminal_id,
					'amount' => \number_format($payment_amount, 2, '.', ''),
					'currency' => \strtoupper(\trim((string) ($dates['currency'] ?? ($fmt['currency'] ?? 'CHF')))),
					'purpose' => \trim((string) $title_safe),
					'contact_company' => (string) ($payrexx_contact['company'] ?? ''),
					'contact_forename' => (string) ($payrexx_contact['forename'] ?? ''),
					'contact_surname' => (string) ($payrexx_contact['surname'] ?? ''),
					'contact_email' => (string) ($payrexx_contact['email'] ?? ''),
				], '', '&', \PHP_QUERY_RFC3986);
				$payrexx_vpos_url = $payrexx_base_url . $payrexx_query;
				if (\function_exists(__NAMESPACE__ . '\\cmxbu_generate_payrexx_qr_data_uri')) {
					$payrexx_qr_data_uri = (string) cmxbu_generate_payrexx_qr_data_uri($payrexx_vpos_url);
					}
				}
			}
			if (
				$beleg_type === 'offerte'
				&& $beleg_richtung === 'ausgang'
				&& \function_exists(__NAMESPACE__ . '\\cmx_beleg_offerte_accept_url')
			) {
				$offerte_status = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_OFFERTENSTATUS')
					? \sanitize_key((string) \get_post_meta($post_id, CMX_BELEG_META_OFFERTENSTATUS, true))
					: \sanitize_key((string) \get_post_meta($post_id, '_cmx_beleg_offertenstatus', true));
				if ($offerte_status === '' || $offerte_status === 'offen') {
					$offerte_accept_url = (string) cmx_beleg_offerte_accept_url($post_id);
					if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_offerte_reject_url')) {
						$offerte_reject_url = (string) cmx_beleg_offerte_reject_url($post_id);
					}
				}
			}
			$qr_enabled_raw = strtolower(trim((string) get_post_meta($post_id, '_cmx_beleg_qr_enabled', true)));
		$qr_user_enabled = ($qr_enabled_raw === '' || !in_array($qr_enabled_raw, ['0', 'no', 'false', 'off'], true));
		$qr_payment_iban = $qr_iban !== '' ? $qr_iban : $bank_iban;
		$qr_meta_enabled = $qr_user_enabled && ($qr_payment_iban !== '');
		$qr_should_print = $qr_meta_enabled
			&& (strtolower($beleg_type) === 'rechnung');
// var_dump($dates['currency']); exit;
		$doc_date = '';
		$doc_due  = '';
		if (!empty($dates['date_invoice'])) {
			$ts = \strtotime((string) $dates['date_invoice']);
			if ($ts) {
				$doc_date = \date('d.m.Y', $ts);
			}
		}
		if (!empty($dates['date_due'])) {
			$ts = \strtotime((string) $dates['date_due']);
			if ($ts) {
				$doc_due = \date('d.m.Y', $ts);
			}
		}

		// Platzhalter für Vorlage
		$tpl = [
			'branding' => ['logo' => $branding_logo, 'website' => $opts['website'] ?? ''],
			'labels' => $labels,
			'format' => $fmt,
			'document' => [
				'type' => $beleg_type,
				'richtung' => (string) get_post_meta($post_id, '_cmx_beleg_richtung', true),
				'number' => $title_safe,
				'title' => $doc_title,
				'date' => $doc_date,
					'due' => $doc_due,
					'currency' => $dates['currency'],
					'period' => $dates['period'],
					'payment_amount' => $payment_amount,
					'open_amount' => (float) ($payment_amounts['open_amount'] ?? ($calc['total'] ?? 0.0)),
					'paid_amount' => (float) ($payment_amounts['paid_amount'] ?? 0.0),
						'has_partial_payments' => !empty($payment_amounts['has_partial_payments']),
						'payrexx_vpos_url' => $payrexx_vpos_url,
						'payrexx_qr_data_uri' => $payrexx_qr_data_uri,
						'offerte_accept_url' => $offerte_accept_url,
						'offerte_reject_url' => $offerte_reject_url,
						'subtotal' => $calc['subtotal'],
					'total' => $calc['total'],
					'manual_total' => $manual_total_value,
				'subject' => (string) get_post_meta($post_id, '_cmx_beleg_betreff', true),
				'description' => (string) get_post_meta($post_id, '_cmx_beleg_beschreibung', true),
			],
			'positions' => array_map(function($p){
				return [
					'row_type' => (string)($p['row_type'] ?? 'position'),
					'section_title' => (string)($p['section_title'] ?? ''),
					'section_text' => (string)($p['section_text'] ?? ''),
					'section_text_html' => (string)($p['section_text_html'] ?? ''),
					'article_number' => (string)($p['article_number'] ?? ''),
					'item' => (string)($p['title'] ?? ''),
					'qty' => (float)($p['qty'] ?? 0),
					'unit' => (string)($p['unit'] ?? ''),
					'unit_price' => (float)($p['unit_price'] ?? 0),
					'line_total' => (float)($p['line_total'] ?? 0),
					'discount' => (string)($p['discount'] ?? ''),
					'has_discount' => (bool)($p['has_discount'] ?? false),
					'desc_text' => (string)($p['desc_text'] ?? ''),
					'desc_html' => (string)($p['desc_html'] ?? ''),
					'desc_raw' => (string)($p['desc_raw'] ?? ''),
					'article_belegtext_html' => (string)($p['article_belegtext_html'] ?? ''),
					'article_variant_html' => (string)($p['article_variant_html'] ?? ''),
				];
			}, $calc['positionen']),
			'any_discount' => (bool)($calc['any_discount'] ?? false),
			'qr' => [
				'enabled' => $qr_user_enabled,
				'iban' => $qr_payment_iban,
				'will_print' => $qr_should_print,
			],
			'tax' => [
				'rate' => $mwst['rate'],
				'label' => $mwst['label'],
				'amount' => $calc['tax_amount'],
				'is_brutto' => $is_brutto,
			],
			'totals' => [
				'net' => $calc['net'],
				'gross' => $calc['gross'],
				'tax' => $calc['tax_amount'],
				'tax_rate' => $mwst['rate'],
				'is_brutto' => $is_brutto,
				'is_mwst_pflichtig' => $is_mwst_pflichtig,
			],
			'anzahlungen' => $anzahlungen,
			'me' => $me,
			'counterparty_contact' => $counterparty_contact,
			'bank' => $bank,
		];
		$layout_profiles = [
			'dl_left' => [
				'profile' => 'dl_left',
				'logo_x_mm' => (float) CMX_LOGO_X_MM,
				'logo_y_mm' => (float) CMX_LOGO_Y_MM,
				'logo_width_mm' => (float) CMX_LOGO_WIDTH_MM,
				'recipient_x_mm' => (float) CMX_DL_RECIPIENT_X_MM,
				'recipient_y_mm' => (float) CMX_DL_RECIPIENT_Y_MM,
				'recipient_width_mm' => (float) CMX_DL_RECIPIENT_WIDTH_MM,
				'recipient_height_mm' => (float) CMX_DL_RECIPIENT_HEIGHT_MM,
				'header_height_mm' => (float) CMX_DL_HEADER_HEIGHT_MM,
				'meta_top_mm' => (float) CMX_DL_META_TOP_MM,
				'show_recipient_label' => false,
			],
			'c5_left' => [
				'profile' => 'c5_left',
				'logo_x_mm' => (float) CMX_LOGO_X_MM,
				'logo_y_mm' => (float) CMX_LOGO_Y_MM,
				'logo_width_mm' => (float) CMX_LOGO_WIDTH_MM,
				'recipient_x_mm' => (float) CMX_C5_RECIPIENT_X_MM,
				'recipient_y_mm' => (float) CMX_C5_RECIPIENT_Y_MM,
				'recipient_width_mm' => (float) CMX_C5_RECIPIENT_WIDTH_MM,
				'recipient_height_mm' => (float) CMX_C5_RECIPIENT_HEIGHT_MM,
				'header_height_mm' => (float) CMX_C5_HEADER_HEIGHT_MM,
				'meta_top_mm' => (float) CMX_C5_META_TOP_MM,
				'show_recipient_label' => false,
			],
			'c4_left' => [
				'profile' => 'c4_left',
				'logo_x_mm' => (float) CMX_LOGO_X_MM,
				'logo_y_mm' => (float) CMX_LOGO_Y_MM,
				'logo_width_mm' => (float) CMX_LOGO_WIDTH_MM,
				'recipient_x_mm' => (float) CMX_C4_RECIPIENT_X_MM,
				'recipient_y_mm' => (float) CMX_C4_RECIPIENT_Y_MM,
				'recipient_width_mm' => (float) CMX_C4_RECIPIENT_WIDTH_MM,
				'recipient_height_mm' => (float) CMX_C4_RECIPIENT_HEIGHT_MM,
				'header_height_mm' => (float) CMX_C4_HEADER_HEIGHT_MM,
				'meta_top_mm' => (float) CMX_C4_META_TOP_MM,
				'show_recipient_label' => false,
			],
			'dl_right' => [
				'profile' => 'dl_right',
				'logo_x_mm' => (float) CMX_LOGO_X_MM,
				'logo_y_mm' => (float) CMX_LOGO_Y_MM,
				'logo_width_mm' => (float) CMX_LOGO_WIDTH_MM,
				'recipient_x_mm' => (float) CMX_DL_RECIPIENT_X_RIGHT_MM,
				'recipient_y_mm' => (float) CMX_DL_RECIPIENT_Y_MM,
				'recipient_width_mm' => (float) CMX_DL_RECIPIENT_WIDTH_MM,
				'recipient_height_mm' => (float) CMX_DL_RECIPIENT_HEIGHT_MM,
				'header_height_mm' => (float) CMX_DL_HEADER_HEIGHT_MM,
				'meta_top_mm' => (float) CMX_DL_META_TOP_MM,
				'show_recipient_label' => false,
			],
			'c5_right' => [
				'profile' => 'c5_right',
				'logo_x_mm' => (float) CMX_LOGO_X_MM,
				'logo_y_mm' => (float) CMX_LOGO_Y_MM,
				'logo_width_mm' => (float) CMX_LOGO_WIDTH_MM,
				'recipient_x_mm' => (float) CMX_C5_RECIPIENT_X_RIGHT_MM,
				'recipient_y_mm' => (float) CMX_C5_RECIPIENT_Y_MM,
				'recipient_width_mm' => (float) CMX_C5_RECIPIENT_WIDTH_MM,
				'recipient_height_mm' => (float) CMX_C5_RECIPIENT_HEIGHT_MM,
				'header_height_mm' => (float) CMX_C5_HEADER_HEIGHT_MM,
				'meta_top_mm' => (float) CMX_C5_META_TOP_MM,
				'show_recipient_label' => false,
			],
			'c4_right' => [
				'profile' => 'c4_right',
				'logo_x_mm' => (float) CMX_LOGO_X_MM,
				'logo_y_mm' => (float) CMX_LOGO_Y_MM,
				'logo_width_mm' => (float) CMX_LOGO_WIDTH_MM,
				'recipient_x_mm' => (float) CMX_C4_RECIPIENT_X_RIGHT_MM,
				'recipient_y_mm' => (float) CMX_C4_RECIPIENT_Y_MM,
				'recipient_width_mm' => (float) CMX_C4_RECIPIENT_WIDTH_MM,
				'recipient_height_mm' => (float) CMX_C4_RECIPIENT_HEIGHT_MM,
				'header_height_mm' => (float) CMX_C4_HEADER_HEIGHT_MM,
				'meta_top_mm' => (float) CMX_C4_META_TOP_MM,
				'show_recipient_label' => false,
			],
		];
		$preferred_layout = cmx_get_beleg_briefbogen((string)$beleg_type);
		$tpl['layout'] = $layout_profiles[$preferred_layout] ?? $layout_profiles['dl_left'];

		// Vorlage laden & rendern
		$tpl_dir = trailingslashit(defined('CMX_PLUGIN_DIR') ? CMX_PLUGIN_DIR : plugin_dir_path(__FILE__)) . 'src/belege/';
		$tpl_path = $tpl_dir . 'vorlage_mit_QR.php';
		if (!is_file($tpl_path)) {
			cmxbu_log('FEHLER: Vorlage fehlt.', ['path' => $tpl_path]);
			return;
		}

		try {
			$render_html = static function(string $template_path, array $tpl_data, string $recipient_addr): string {
				$tpl = $tpl_data;
				$cmx_beleg_adress = $recipient_addr;
				ob_start();
				include $template_path;
				return trim((string) ob_get_clean());
			};
			$build_dom = static function(string $html, string $page_css, array $tpl_data, int $beleg_id): Dompdf {
				$opt = new Options();
				$opt->set('isRemoteEnabled', true);
				$opt->set('isHtml5ParserEnabled', true);
				$opt->set('defaultFont', 'DejaVu Sans');
				$dom = new Dompdf($opt);
				$dom->setPaper('A4', 'portrait');
				$dom->loadHtml($page_css . $html, 'UTF-8');
				$dom->render();
				cmx_add_qr_page($dom, $tpl_data, $beleg_id);
				return $dom;
			};

			$dir = \dirname($pdf_path);
			if (!is_dir($dir) && !\wp_mkdir_p($dir)) {
				cmxbu_log('FEHLER: Ordner konnte nicht erstellt werden', ['dir' => $dir]);
				return;
			}

			$page_css = '<style>
			  @page {
				margin-top: 100px;      /* Kopfbereich Seite >= 2 */
				margin-right: 30px;
				margin-left: 30px;
				margin-bottom: 90px;    /* Footer-Reserve */
			  }
			  @page :first {
				margin-top: 30px;       /* Seite 1: gleich wie links/rechts */
				margin-right: 30px;
				margin-left: 30px;
				margin-bottom: 90px;
			  }
			</style>';

			$html = $render_html($tpl_path, $tpl, $cmx_beleg_adress);
			if (mb_strlen($html, '8bit') < 50) {
				cmxbu_log('FEHLER: HTML leer/zu kurz.');
				return;
			}

			$dom = $build_dom($html, $page_css, $tpl, $post_id);
			$probe_pages = $dom->getCanvas()->get_page_count();
			$current_layout = strtolower((string)($tpl['layout']['profile'] ?? 'dl_left'));
			$is_c4_layout = (strpos($current_layout, 'c4') === 0);
			if (!$is_c4_layout && $probe_pages > (int) CMX_C4_SWITCH_PAGE_THRESHOLD) {
				$target_c4_profile = (substr($current_layout, -6) === '_right') ? 'c4_right' : 'c4_left';
				$tpl['layout'] = $layout_profiles[$target_c4_profile] ?? $layout_profiles['c4_left'];
				$html = $render_html($tpl_path, $tpl, $cmx_beleg_adress);
				if (mb_strlen($html, '8bit') < 50) {
					cmxbu_log('FEHLER: HTML leer/zu kurz (C4).');
					return;
				}
				$dom = $build_dom($html, $page_css, $tpl, $post_id);
				cmxbu_log('Layout auf C4 umgestellt', ['post_id' => $post_id, 'pages' => $probe_pages]);
			}

			$canvas = $dom->getCanvas();
			$fontMetrics = $dom->getFontMetrics();
			$fontHeader = $fontMetrics->getFont('DejaVu Sans', 'bold');
			$fontFooter = $fontMetrics->getFont('DejaVu Sans', 'normal');

			$page_count = $canvas->get_page_count();

			// Kopf ab Seite 2
			if ($page_count > 1) {
				$beleg_titel = $tpl['document']['title'] ?? 'Beleg';
				for ($i = 2; $i <= $page_count; $i++) {
					$canvas->text(50, 55, $beleg_titel, $fontHeader, 11, [0.1, 0.1, 0.1], $i);
				}
			}

			// Seitenzahlen im Kopfbereich (nur wenn mehr als 1 Seite)
			// (deaktiviert auf Wunsch)

			// Seitenzahlen unten
			// if ($page_count > 1) {
			// 		$canvas->page_text(
			// 				535, 780,
			// 				'Seite {PAGE_NUM} von {PAGE_COUNT}',
			// 				$fontFooter, 5,
			// 				[0.5, 0.5, 0.5]
			// 		);
			// }

			$pdf_binary = $dom->output();
			if ($pdf_binary === '' || $pdf_binary === false) {
				cmxbu_log('FEHLER: Leerer PDF-Output');
				return;
			}

			if (!cmxbu_save_beleg_pdf($pdf_path, $pdf_binary)) {
				cmxbu_log('FEHLER: PDF konnte nicht geschrieben werden', ['path' => $pdf_path]);
				return;
			}

			cmxbu_log('PDF erstellt', ['pdf' => $pdf_path, 'layout' => (string)($tpl['layout']['profile'] ?? 'dl')]);
		} catch (\Throwable $e) {
			cmxbu_log('DOMPDF EXCEPTION', ['error' => $e->getMessage()]);
			return;
		}

}

// 		if (is_file($html_path)) { @unlink($html_path); cmxbu_log('HTML entfernt (Cleanup).',['path'=>$html_path]); }
	// $dom->stream('beleg.pdf', ['Attachment' => false]);
