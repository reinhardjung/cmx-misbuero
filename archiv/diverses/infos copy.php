<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') or die('Oxytocin!');

/**
 * Logo/Icon der angegebenen Website ermitteln und als Beitragsbild setzen.
 * Quelle: URL aus Post-Meta CMX_KONTAKTE_META_URL
 * Strategie (Priorität):
 * 1) <link rel="apple-touch-icon"...> (größte size)
 * 2) <link rel="icon"...> / <link rel="shortcut icon"...> (größte size)
 * 3) <meta property="og:image"...>
 * 4) Fallback: /favicon.ico
 *
 * Hinweis: SVG wird übersprungen (Standard-WP-Upload blockiert).
 *
 * @param int $post_id
 * @return int|WP_Error Attachment-ID bei Erfolg, WP_Error bei Fehler
 */
function cmx_fetch_logo_from_url(int $post_id) {
	if ($post_id <= 0) return new \WP_Error('bad_post', 'Ungültige Post-ID');

	// 1) URL aus Meta holen
	if (!defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_URL')) {
		return new \WP_Error('missing_const', 'CMX_KONTAKTE_META_URL ist nicht definiert');
	}
	$site_url = trim((string) \get_post_meta($post_id, CMX_KONTAKTE_META_URL, true));
	if (empty($site_url)) return new \WP_Error('no_url', 'Keine URL im Kontakt hinterlegt');

	// 2) Normalisieren
	if (!preg_match('~^https?://~i', $site_url)) {
		$site_url = 'https://' . ltrim($site_url, '/');
	}
	$base = \trailingslashit(\untrailingslashit($site_url));

	// 3) HTML abrufen
	$resp = \wp_remote_get($site_url, [
		'timeout' => 10,
		'headers' => ['User-Agent' => 'CMX-LogoFetcher/1.0; WordPress'],
	]);
	if (\is_wp_error($resp)) return $resp;
	$code = (int) \wp_remote_retrieve_response_code($resp);
	$html = (string) \wp_remote_retrieve_body($resp);
	if ($code < 200 || $code >= 400 || $html === '') return new \WP_Error('http_fail', 'Seite konnte nicht geladen werden');

	// 4) Kandidaten extrahieren
	$icon_candidates = [];

	// Helper: absolute URL auflösen (einfach)
	$abs = static function(string $url, string $base) : string {
		$url = trim($url);
		if ($url === '') return '';
		if (preg_match('~^https?://~i', $url)) return $url;
		if (str_starts_with($url, '//'))  return 'https:' . $url;
		if (str_starts_with($url, '/'))   return rtrim(parse_url($base, PHP_URL_SCHEME) . '://' . parse_url($base, PHP_URL_HOST), '/') . $url;
		return \trailingslashit($base) . ltrim($url, './');
	};

	// a) <link rel="apple-touch-icon" ... sizes="180x180">
	if (preg_match_all('~<link[^>]+rel=["\']?apple-touch-icon[^"\']*["\']?[^>]*>~i', $html, $m)) {
		foreach ($m[0] as $tag) {
			$href  = '';
			$sizes = 0;
			if (preg_match('~href=["\']([^"\']+)~i', $tag, $mm)) $href = $mm[1];
			if (preg_match('~sizes=["\'](\d+)x(\d+)~i', $tag, $ms)) $sizes = (int)$ms[1] * (int)$ms[2];
			if ($href) $icon_candidates[] = ['url' => $abs($href, $base), 'prio' => 100, 'area' => $sizes];
		}
	}

	// b) <link rel="icon"|rel="shortcut icon">
	if (preg_match_all('~<link[^>]+rel=["\']?(?:icon|shortcut icon)[^"\']*["\']?[^>]*>~i', $html, $m)) {
		foreach ($m[0] as $tag) {
			$href  = '';
			$sizes = 0;
			if (preg_match('~href=["\']([^"\']+)~i', $tag, $mm)) $href = $mm[1];
			if (preg_match('~sizes=["\'](\d+)x(\d+)~i', $tag, $ms)) $sizes = (int)$ms[1] * (int)$ms[2];
			if ($href) $icon_candidates[] = ['url' => $abs($href, $base), 'prio' => 90, 'area' => $sizes];
		}
	}

	// c) <meta property="og:image" content="...">
	if (preg_match('~<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)~i', $html, $og)) {
		$icon_candidates[] = ['url' => $abs($og[1], $base), 'prio' => 80, 'area' => 0];
	}

	// d) Fallback: /favicon.ico
	$parsed = \wp_parse_url($base);
	if (!empty($parsed['scheme']) && !empty($parsed['host'])) {
		$favicon = $parsed['scheme'] . '://' . $parsed['host'] . '/favicon.ico';
		$icon_candidates[] = ['url' => $favicon, 'prio' => 10, 'area' => 0];
	}

	// 5) Kandidaten filtern: keine SVG (Standard-WP), deduplizieren
	$norm = static function(string $u) { return strtolower(preg_replace('~#.*$~', '', $u)); };
	$seen = [];
	$clean = [];
	foreach ($icon_candidates as $c) {
		if ($c['url'] === '') continue;
		if (preg_match('~\.svg(\?.*)?$~i', $c['url'])) continue; // SVG überspringen
		$key = $norm($c['url']);
		if (isset($seen[$key])) continue;
		$seen[$key] = true;
		$clean[] = $c;
	}
	if (empty($clean)) return new \WP_Error('no_icon', 'Kein geeignetes Icon gefunden');

	// 6) Beste URL wählen: primär Prio, sekundär größte Fläche
	usort($clean, function($a,$b){
		return ($b['prio'] <=> $a['prio']) ?: ($b['area'] <=> $a['area']);
	});
	$best = $clean[0]['url'];

	// 7) Download & Medienanhang erzeugen
	$tmp = \download_url($best, 15);
	if (\is_wp_error($tmp)) return $tmp;

	// Dateiname schätzen
	$name = basename(parse_url($best, PHP_URL_PATH) ?? '');
	if ($name === '' || !str_contains($name, '.')) $name = 'site-icon-' . $post_id . '.png';

	$file_array = [
		'name'     => sanitize_file_name($name),
		'tmp_name' => $tmp,
	];

	// Dateityp prüfen / korrigieren
	$filetype = \wp_check_filetype($file_array['name']);
	if (empty($filetype['type'])) {
		// Fallback: PNG
		$file_array['name'] = preg_replace('~\.[^.]+$~', '.png', $file_array['name']);
	}

	$attach_id = \media_handle_sideload($file_array, $post_id, 'Website-Icon/Logo importiert');
	if (\is_wp_error($attach_id)) {
		@unlink($tmp);
		return $attach_id;
	}

	// 8) Als Beitragsbild setzen
	\set_post_thumbnail($post_id, (int)$attach_id);

	return (int)$attach_id;
}



\add_action('save_post_kontakte', function($post_id){
	if (\wp_is_post_revision($post_id) || \defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\get_post_type($post_id) !== 'kontakte') return;

	// nur ausführen, wenn noch kein Beitragsbild vorhanden
	if (\has_post_thumbnail($post_id)) return;

	$url = \get_post_meta($post_id, CMX_KONTAKTE_META_URL, true);
	if (empty($url)) return;

	$result = cmx_fetch_logo_from_url((int)$post_id);
	if (\is_wp_error($result)) {
		// Optional: Logging
		if (function_exists('error_log')) {
			error_log('[CMX] Logo-Fetch fehlgeschlagen für Post '.$post_id.': '.$result->get_error_message());
		}
	}
}, 30);







/**
 * Single file: URL → Adresse (Rechnungsanschrift) befüllen
 * Namespace: CLOUDMEISTER\CMX\Buero
 *
 * Erwartet vorhandene Konstanten/Meta-Keys:
 *  - CMX_KONTAKTE_META_URL
 *  - CMX_RECHNUNG_META_STRASSE
 *  - CMX_RECHNUNG_META_PLZ
 *  - CMX_RECHNUNG_META_ORT
 *  - CMX_RECHNUNG_META_COUNTRY
 *  - Taxonomie: cmx_country
 */

/** ------------------------------------------------------------
 * HOOK: Beim Speichern automatisch befüllen (nur wenn leer)
 * ------------------------------------------------------------ */
\add_action('save_post_kontakte', function($post_id){
	if (\wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
	if (\get_post_type($post_id) !== 'kontakte') return;

	$already = (\get_post_meta($post_id, CMX_RECHNUNG_META_STRASSE, true)
	         || \get_post_meta($post_id, CMX_RECHNUNG_META_PLZ, true)
	         || \get_post_meta($post_id, CMX_RECHNUNG_META_ORT, true));
	if ($already) return;

	cmx_fill_billing_from_url((int)$post_id);
}, 50);

/** ------------------------------------------------------------
 * Hauptfunktion: URL → Adresse ermitteln → Rechnungs-Metas setzen
 * ------------------------------------------------------------ */
function cmx_fill_billing_from_url(int $post_id) : array {
	if ($post_id <= 0) return ['ok'=>false,'reason'=>'bad_post','data'=>[]];

	$url = trim((string)\get_post_meta($post_id, CMX_KONTAKTE_META_URL, true));
	if ($url === '') return ['ok'=>false,'reason'=>'no_url','data'=>[]];
	if (!preg_match('~^https?://~i', $url)) $url = 'https://' . ltrim($url, '/');

	$resp = \wp_remote_get($url, [
		'timeout' => 10,
		'headers' => ['User-Agent' => 'CMX-AddressFetcher/1.0; WordPress'],
	]);
	if (\is_wp_error($resp)) return ['ok'=>false,'reason'=>'http_error','data'=>[]];
	$code = (int)\wp_remote_retrieve_response_code($resp);
	$html = (string)\wp_remote_retrieve_body($resp);
	if ($code < 200 || $code >= 400 || $html === '') return ['ok'=>false,'reason'=>'bad_response','data'=>[]];

	$addr = cmx_extract_address_from_html($html);
	$addr = cmx_geocode_with_nominatim($addr);

	if (empty($addr['strasse']) && empty($addr['plz']) && empty($addr['ort'])) {
		return ['ok'=>false,'reason'=>'not_found','data'=>$addr];
	}

	if (!empty($addr['strasse'])) \update_post_meta($post_id, CMX_RECHNUNG_META_STRASSE, sanitize_text_field($addr['strasse']));
	if (!empty($addr['plz']))     \update_post_meta($post_id, CMX_RECHNUNG_META_PLZ,     sanitize_text_field($addr['plz']));
	if (!empty($addr['ort']))     \update_post_meta($post_id, CMX_RECHNUNG_META_ORT,     sanitize_text_field($addr['ort']));

	$term_id = cmx_ensure_country_term(trim((string)($addr['land'] ?? '')));
	if ($term_id > 0) {
		\update_post_meta($post_id, CMX_RECHNUNG_META_COUNTRY, $term_id);
		\wp_set_object_terms($post_id, [$term_id], 'cmx_country', false);
	}

	return ['ok'=>true,'reason'=>'ok','data'=>$addr];
}

/** ------------------------------------------------------------
 * HTML → Adresse extrahieren (JSON-LD bevorzugt; Fallback Heuristiken)
 * ------------------------------------------------------------ */
function cmx_extract_address_from_html(string $html) : array {
	$addr = ['strasse'=>'','plz'=>'','ort'=>'','land'=>''];

	// 1) JSON-LD (<script type="application/ld+json">)
	if (preg_match_all('~<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>~is', $html, $m)) {
		foreach ($m[1] as $block) {
			$data = json_decode(trim($block), true);
			if (!$data) continue;
			$nodes = cmx_is_assoc($data) ? [$data] : (array)$data;
			foreach ($nodes as $node) {
				$found = cmx_find_postal_address_in_ld($node);
				if ($found) {
					$addr['strasse'] = (string)($found['streetAddress']  ?? '');
					$addr['plz']     = (string)($found['postalCode']     ?? '');
					$addr['ort']     = (string)($found['addressLocality'] ?? '');
					$addr['land']    = (string)($found['addressCountry']  ?? '');
					if ($addr['strasse'] || $addr['plz'] || $addr['ort'] || $addr['land']) {
						return $addr;
					}
				}
			}
		}
	}

	// 2) Fallback: <address>…</address>
	if (preg_match('~<address[^>]*>(.*?)</address>~is', $html, $a)) {
		$plain = \wp_strip_all_tags($a[1]);
		$guess = cmx_guess_address_from_text($plain);
		$addr  = array_merge($addr, array_filter($guess));
	}
	// 3) Fallback: Footer-Sektion
	if (!($addr['strasse'] || $addr['plz'] || $addr['ort'])) {
		if (preg_match('~<footer[^>]*>(.*?)</footer>~is', $html, $f)) {
			$plain = \wp_strip_all_tags($f[1]);
			$guess = cmx_guess_address_from_text($plain);
			$addr  = array_merge($addr, array_filter($guess));
		}
	}
	return $addr;
}

/** rekursive Suche nach PostalAddress in JSON-LD */
function cmx_find_postal_address_in_ld($node) {
	if (!is_array($node)) return null;

	if (!empty($node['@type'])) {
		$types = (array)$node['@type'];
		if (in_array('PostalAddress', $types, true)) return $node;
	}
	if (!empty($node['address']) && is_array($node['address'])) {
		$addr = $node['address'];
		if (!empty($addr['@type'])) {
			$types = (array)$addr['@type'];
			if (in_array('PostalAddress', $types, true)) return $addr;
		}
	}
	foreach ($node as $v) {
		if (is_array($v)) {
			$found = cmx_find_postal_address_in_ld($v);
			if ($found) return $found;
		}
	}
	return null;
}

/** Heuristik aus freiem Text (CH/EU-fokussiert, bewusst defensiv) */
function cmx_guess_address_from_text(string $text) : array {
	$addr = ['strasse'=>'','plz'=>'','ort'=>'','land'=>''];

	if (preg_match('~\b(\d{4,5})\s+([A-Za-zÀ-ÿ\'\-\s]{2,})\b~u', $text, $m)) {
		$addr['plz'] = trim($m[1]);
		$addr['ort'] = trim($m[2]);
	}
	if (preg_match('~\b([A-Za-zÀ-ÿ\'\-\s\.]+(?:strasse|straße|weg|gasse|platz|allee|road|street|avenue|boulevard))\s*\d{0,4}[A-Za-z]?~iu', $text, $m)) {
		$addr['strasse'] = trim($m[0]);
	}
	if (preg_match('~\b(Schweiz|Switzerland|CH|Deutschland|DE|Österreich|AT|Italy|Italia|IT|France|FR)\b~i', $text, $m)) {
		$addr['land'] = strtoupper(trim($m[0]));
	}
	return $addr;
}

/** Assoc-Array-Check */
function cmx_is_assoc($arr){ return is_array($arr) && array_keys($arr)!==range(0,count($arr)-1); }

/** ------------------------------------------------------------
 * Nominatim-Geocoding (für Normalisierung/Ergänzung)
 * ------------------------------------------------------------ */
function cmx_geocode_with_nominatim(array $addr) : array {
	$qParts = array_filter([$addr['strasse'] ?? '', $addr['plz'] ?? '', $addr['ort'] ?? '', $addr['land'] ?? '']);
	$q = implode(', ', $qParts);
	if ($q === '') return $addr;

	$resp = \wp_remote_get('https://nominatim.openstreetmap.org/search', [
		'timeout' => 10,
		'headers' => ['User-Agent' => 'CMX-AddressNormalizer/1.0; WordPress'],
		'body'    => [
			'format'         => 'json',
			'limit'          => 1,
			'addressdetails' => 1,
			'q'              => $q,
		],
	]);
	if (\is_wp_error($resp)) return $addr;

	$data = json_decode(\wp_remote_retrieve_body($resp), true);
	if (!is_array($data) || empty($data[0]['address'])) return $addr;

	$a = $data[0]['address'];
	$addr['strasse'] = $addr['strasse'] ?: trim(($a['road'] ?? '') . ' ' . ($a['house_number'] ?? ''));
	$addr['plz']     = $addr['plz']     ?: (string)($a['postcode'] ?? '');
	$addr['ort']     = $addr['ort']     ?: (string)($a['city'] ?? $a['town'] ?? $a['village'] ?? $a['municipality'] ?? '');
	$addr['land']    = $addr['land']    ?: (string)($a['country_code'] ?? $a['country'] ?? '');
	return $addr;
}

/** ------------------------------------------------------------
 * Land sicherstellen: Taxonomie „cmx_country“ → Term-ID zurückgeben
 * ------------------------------------------------------------ */
function cmx_ensure_country_term(string $raw) : int {
	if ($raw === '') return 0;
	$rawU = strtoupper(trim($raw));

	// Minimal-Map (CH/EU häufig)
	$map = [
		'CH'=>'Schweiz','SWITZERLAND'=>'Schweiz',
		'DE'=>'Deutschland','GERMANY'=>'Deutschland',
		'AT'=>'Österreich','AUSTRIA'=>'Österreich',
		'IT'=>'Italien','ITALY'=>'Italien','ITALIA'=>'Italien',
		'FR'=>'Frankreich','FRANCE'=>'Frankreich',
	];
	$name = $map[$rawU] ?? $raw;

	$term = \term_exists($name, 'cmx_country');
	if ($term && !is_wp_error($term)) return (int)$term['term_id'];

	$created = \wp_insert_term($name, 'cmx_country');
	return (!is_wp_error($created)) ? (int)$created['term_id'] : 0;
}
