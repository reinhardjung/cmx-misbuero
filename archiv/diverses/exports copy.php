<?php
/**
 * Datei: exports.php
 * Zweck: Export aller "kontakte" als CSV inkl. Stammdaten, Adressen, Stufe & Kommunikation
 */
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

/** =========================================================
 * Konstanten-Fallbacks (falls in anderen Dateien nicht geladen)
 * ========================================================= */
if (!defined(__NAMESPACE__ . '\\CMX_TAX_PHONE_LABELS')) {
	define(__NAMESPACE__ . '\\CMX_TAX_PHONE_LABELS', 'kontakte_telefone');
}
if (!defined(__NAMESPACE__ . '\\CMX_TAX_MAIL_LABELS')) {
	define(__NAMESPACE__ . '\\CMX_TAX_MAIL_LABELS',  'kontakte_emails');
}

/* Stammdaten */
if (!defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_VORNAME'))  define(__NAMESPACE__ . '\\CMX_KONTAKTE_META_VORNAME',  '_cmx_kontakte_vorname');
if (!defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_NACHNAME')) define(__NAMESPACE__ . '\\CMX_KONTAKTE_META_NACHNAME', '_cmx_kontakte_nachname');
if (!defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_URL'))      define(__NAMESPACE__ . '\\CMX_KONTAKTE_META_URL',      '_cmx_kontakte_url');
if (!defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_PRIVAT'))   define(__NAMESPACE__ . '\\CMX_KONTAKTE_META_PRIVAT',   '_cmx_kontakte_privat');
if (!defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_DATUM'))    define(__NAMESPACE__ . '\\CMX_KONTAKTE_META_DATUM',    '_cmx_kontakte_datum');

/* Adressen: Rechnung / Liefer */
if (!defined(__NAMESPACE__ . '\\CMX_RECHNUNG_META_STRASSE')) define(__NAMESPACE__ . '\\CMX_RECHNUNG_META_STRASSE', '_cmx_rechnung_strasse');
if (!defined(__NAMESPACE__ . '\\CMX_RECHNUNG_META_ZUSATZ'))  define(__NAMESPACE__ . '\\CMX_RECHNUNG_META_ZUSATZ',  '_cmx_rechnung_zusatz');
if (!defined(__NAMESPACE__ . '\\CMX_RECHNUNG_META_PLZ'))     define(__NAMESPACE__ . '\\CMX_RECHNUNG_META_PLZ',     '_cmx_rechnung_plz');
if (!defined(__NAMESPACE__ . '\\CMX_RECHNUNG_META_ORT'))     define(__NAMESPACE__ . '\\CMX_RECHNUNG_META_ORT',     '_cmx_rechnung_ort');
if (!defined(__NAMESPACE__ . '\\CMX_RECHNUNG_META_LAND'))    define(__NAMESPACE__ . '\\CMX_RECHNUNG_META_LAND',    '_cmx_rechnung_land');

if (!defined(__NAMESPACE__ . '\\CMX_LIEFER_META_STRASSE'))   define(__NAMESPACE__ . '\\CMX_LIEFER_META_STRASSE',   '_cmx_liefer_strasse');
if (!defined(__NAMESPACE__ . '\\CMX_LIEFER_META_ZUSATZ'))    define(__NAMESPACE__ . '\\CMX_LIEFER_META_ZUSATZ',    '_cmx_liefer_zusatz');
if (!defined(__NAMESPACE__ . '\\CMX_LIEFER_META_PLZ'))       define(__NAMESPACE__ . '\\CMX_LIEFER_META_PLZ',       '_cmx_liefer_plz');
if (!defined(__NAMESPACE__ . '\\CMX_LIEFER_META_ORT'))       define(__NAMESPACE__ . '\\CMX_LIEFER_META_ORT',       '_cmx_liefer_ort');
if (!defined(__NAMESPACE__ . '\\CMX_LIEFER_META_LAND'))      define(__NAMESPACE__ . '\\CMX_LIEFER_META_LAND',      '_cmx_liefer_land');

/** =========================================================
 * Helper / Resolver
 * ========================================================= */

/** Telefon/E-Mail: Term-Name zu Slug */
if (!function_exists(__NAMESPACE__ . '\\cmx_term_name_by_slug')) {
	function cmx_term_name_by_slug(string $taxonomy, string $slug): string {
		if ($taxonomy === '' || $slug === '' || !\taxonomy_exists($taxonomy)) return '';
		$t = \get_term_by('slug', $slug, $taxonomy);
		return ($t && !\is_wp_error($t) && isset($t->name)) ? (string)$t->name : '';
	}
}

/** Länder-Label zu Slug aus Taxonomie "kontakte_laender" (Fallback-Dummies) */
if (!function_exists(__NAMESPACE__ . '\\cmx_country_label_from_slug')) {
	function cmx_country_label_from_slug(?string $slug): string {
		$slug = strtolower((string)$slug);
		if ($slug === '') return '';
		$tax = 'kontakte_laender';
		if (\taxonomy_exists($tax)) {
			$term = \get_term_by('slug', $slug, $tax);
			if ($term && !\is_wp_error($term) && isset($term->name)) return (string)$term->name;
		}
		$map = [
			'ch' => 'Schweiz',
			'at' => 'Österreich',
			'de' => 'Deutschland',
			'li' => 'Liechtenstein',
			'us' => 'USA',
			'fr' => 'Frankreich',
			'it' => 'Italien',
		];
		return $map[$slug] ?? strtoupper($slug);
	}
}

/** Stufe: Taxonomie-Namen heuristisch finden (wie in Deinem Code) */
if (!function_exists(__NAMESPACE__ . '\\cmx_stufen_tax')) {
	function cmx_stufen_tax(): string {
		foreach (['kontakte_stufen','stufen','kontakte_stufe','kontakt_stufen'] as $t) {
			if (\taxonomy_exists($t)) return $t;
		}
		return 'kontakte_stufen';
	}
}

/** URL normalisieren (für CSV) */
if (!function_exists(__NAMESPACE__ . '\\cmx_normalize_url_for_href')) {
	function cmx_normalize_url_for_href(?string $url): string {
		$url = \trim((string)$url);
		if ($url === '') return '';
		if (!\preg_match('~^https?://~i', $url)) $url = 'https://' . \ltrim($url, '/');
		return $url;
	}
}

/** Domain-Core (example aus example.co.uk etc.) */
if (!function_exists(__NAMESPACE__ . '\\cmx_domain_core_from_url')) {
	function cmx_domain_core_from_url(string $url): string {
		$url = \trim($url);
		if ($url === '') return '';
		if (!\preg_match('~^https?://~i', $url)) $url = 'https://' . \ltrim($url, '/');

		$host = (string)(\parse_url($url, PHP_URL_HOST) ?? '');
		if ($host === '') return '';

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
}

/** Kommunikation für einen Kontakt lesen (Single-Felder > Bündel) */
if (!function_exists(__NAMESPACE__ . '\\cmx_read_comm_values_for_contact')) {
	function cmx_read_comm_values_for_contact(int $post_id): array {
		$out = [
			'telefon' => [1=>['value'=>'','label_slug'=>'','label_name'=>''], 2=>['value'=>'','label_slug'=>'','label_name'=>''], 3=>['value'=>'','label_slug'=>'','label_name'=>'']],
			'email'   => [1=>['value'=>'','label_slug'=>'','label_name'=>''], 2=>['value'=>'','label_slug'=>'','label_name'=>''], 3=>['value'=>'','label_slug'=>'','label_name'=>'']],
		];

		$bundle = \get_post_meta($post_id, '_cmx_kommunikation', true);
		if (!\is_array($bundle)) $bundle = [];

		for ($i=1; $i<=3; $i++) {
			$tel  = \get_post_meta($post_id, "_cmx_telefon_{$i}", true);
			if ($tel === '' || $tel === null) $tel = isset($bundle['telefon'][$i]['value']) ? (string)$bundle['telefon'][$i]['value'] : '';
			$out['telefon'][$i]['value'] = (string)$tel;

			$mail = \get_post_meta($post_id, "_cmx_email_{$i}", true);
			if ($mail === '' || $mail === null) $mail = isset($bundle['email'][$i]['value']) ? (string)$bundle['email'][$i]['value'] : '';
			$out['email'][$i]['value'] = (string)$mail;

			$tel_slug  = isset($bundle['telefon'][$i]['label']) ? (string)$bundle['telefon'][$i]['label'] : '';
			$mail_slug = isset($bundle['email'][$i]['label'])   ? (string)$bundle['email'][$i]['label']   : '';

			$out['telefon'][$i]['label_slug'] = $tel_slug;
			$out['email'][$i]['label_slug']   = $mail_slug;

			$out['telefon'][$i]['label_name'] = $tel_slug  ? cmx_term_name_by_slug(CMX_TAX_PHONE_LABELS, $tel_slug) : '';
			$out['email'][$i]['label_name']   = $mail_slug ? cmx_term_name_by_slug(CMX_TAX_MAIL_LABELS,  $mail_slug) : '';
		}
		return $out;
	}
}

/** Stammdaten für einen Kontakt lesen */
if (!function_exists(__NAMESPACE__ . '\\cmx_read_stammdaten')) {
	function cmx_read_stammdaten(int $post_id): array {
		$vorname  = (string)\get_post_meta($post_id, CMX_KONTAKTE_META_VORNAME,  true);
		$nachname = (string)\get_post_meta($post_id, CMX_KONTAKTE_META_NACHNAME, true);
		$privat   = (bool)\get_post_meta($post_id, CMX_KONTAKTE_META_PRIVAT,     true);
		$url      = (string)\get_post_meta($post_id, CMX_KONTAKTE_META_URL,       true);
		$datum    = (string)\get_post_meta($post_id, CMX_KONTAKTE_META_DATUM,     true);

		$url_norm = cmx_normalize_url_for_href($url);
		$domain   = $url_norm ? cmx_domain_core_from_url($url_norm) : '';

		return compact('vorname','nachname','privat','url_norm','datum','domain');
	}
}

/** Adressen lesen (Rechnung/Liefer) */
if (!function_exists(__NAMESPACE__ . '\\cmx_read_adressen')) {
	function cmx_read_adressen(int $post_id): array {
		$r = [
			'strasse' => (string)\get_post_meta($post_id, CMX_RECHNUNG_META_STRASSE, true),
			'zusatz'  => (string)\get_post_meta($post_id, CMX_RECHNUNG_META_ZUSATZ,  true),
			'plz'     => (string)\get_post_meta($post_id, CMX_RECHNUNG_META_PLZ,     true),
			'ort'     => (string)\get_post_meta($post_id, CMX_RECHNUNG_META_ORT,     true),
			'land'    => strtolower((string)\get_post_meta($post_id, CMX_RECHNUNG_META_LAND,    true)),
		];
		$l = [
			'strasse' => (string)\get_post_meta($post_id, CMX_LIEFER_META_STRASSE, true),
			'zusatz'  => (string)\get_post_meta($post_id, CMX_LIEFER_META_ZUSATZ,  true),
			'plz'     => (string)\get_post_meta($post_id, CMX_LIEFER_META_PLZ,     true),
			'ort'     => (string)\get_post_meta($post_id, CMX_LIEFER_META_ORT,     true),
			'land'    => strtolower((string)\get_post_meta($post_id, CMX_LIEFER_META_LAND,      true)),
		];
		$r['land_label'] = cmx_country_label_from_slug($r['land']);
		$l['land_label'] = cmx_country_label_from_slug($l['land']);

		return ['rechnung'=>$r, 'liefer'=>$l];
	}
}

/** Stufe (erste zugewiesene) lesen */
if (!function_exists(__NAMESPACE__ . '\\cmx_read_stufe')) {
	function cmx_read_stufe(int $post_id): array {
		$tax = cmx_stufen_tax();
		if (!\taxonomy_exists($tax)) return ['id'=>'', 'slug'=>'', 'name'=>''];
		$terms = \get_the_terms($post_id, $tax);
		if (\is_wp_error($terms) || empty($terms)) return ['id'=>'', 'slug'=>'', 'name'=>''];
		$first = reset($terms);
		return [
			'id'   => (string)$first->term_id,
			'slug' => (string)$first->slug,
			'name' => (string)$first->name,
		];
	}
}

/** =========================================================
 * Admin-Menü: Export-Seite (Button → admin-post)
 * ========================================================= */
\add_action('admin_menu', function () {
	if (!\post_type_exists('kontakte')) return;
	\add_submenu_page(
		'edit.php?post_type=kontakte',
		'Export (CSV)',
		'Export (CSV)',
		'export',
		'cmx_kontakte_export',
		__NAMESPACE__ . '\\cmx_render_kontakte_export_page'
	);
});

function cmx_render_kontakte_export_page(): void {
	if (!\current_user_can('export')) \wp_die('Keine Berechtigung.');
	$url = \wp_nonce_url(\admin_url('admin-post.php?action=cmx_kontakte_export'), 'cmx_kontakte_export');
	echo '<div class="wrap"><h1>Kontakte exportieren (CSV)</h1>';
	echo '<p>Exportiert alle <code>kontakte</code> inkl. Stammdaten, Adressen, Stufe und Kommunikationsfeldern. CSV ist UTF-8 (BOM), Semikolon-getrennt.</p>';
	echo '<p><a class="button button-primary" href="'.\esc_url($url).'">Jetzt CSV exportieren</a></p>';
	echo '</div>';
}

/** =========================================================
 * Admin-Post Handler → CSV streamen
 * ========================================================= */
\add_action('admin_post_cmx_kontakte_export', __NAMESPACE__ . '\\cmx_handle_kontakte_export');

function cmx_handle_kontakte_export(): void {
	if (!\current_user_can('export')) \wp_die('Keine Berechtigung.');
	\check_admin_referer('cmx_kontakte_export');
	cmx_stream_kontakte_csv(); // exit;
}

/** =========================================================
 * CSV-Streaming
 * ========================================================= */
function cmx_stream_kontakte_csv(): void {
	\ignore_user_abort(true);
	if (function_exists('set_time_limit')) @set_time_limit(0);

	// Alle Output-Buffer schließen
	while (ob_get_level() > 0) { @ob_end_clean(); }

	\nocache_headers();

	$filename = 'kontakte_export_' . \gmdate('Ymd_His') . '.csv';
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Pragma: no-cache');
	header('Expires: 0');

	$out = fopen('php://output', 'w');
	if (!$out) { header_remove(); \wp_die('Konnte Export-Stream nicht öffnen.'); }

	// UTF-8 BOM (Excel)
	fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

	// Kopfzeile
	$headers = [
		'ID','Titel','Status','Erstellt_am',

		'vorname','nachname','privat','url','url_domain_core','datum',

		'rechnung_strasse','rechnung_zusatz','rechnung_plz','rechnung_ort','rechnung_land_slug','rechnung_land_label',
		'liefer_strasse','liefer_zusatz','liefer_plz','liefer_ort','liefer_land_slug','liefer_land_label',

		'stufe_id','stufe_slug','stufe_name',

		'telefon_1','telefon_label_1','telefon_2','telefon_label_2','telefon_3','telefon_label_3',
		'email_1','email_label_1','email_2','email_label_2','email_3','email_label_3',
	];
	fputcsv($out, $headers, ';');

	$paged = 1;
	$per_page = 500;

	do {
		$q = new \WP_Query([
			'post_type'      => 'kontakte',
			'post_status'    => ['publish','draft','pending','private'],
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => false,
		]);

		if (!$q->have_posts()) break;

		foreach ($q->posts as $p) {
			$post_id = (int)$p->ID;

			// Zeit konvertieren in WP-Format (lokal)
			$created = (string)\get_date_from_gmt(\gmdate('Y-m-d H:i:s', strtotime($p->post_date_gmt)), 'Y-m-d H:i:s');

			$stamm   = cmx_read_stammdaten($post_id);
			$addr    = cmx_read_adressen($post_id);
			$stufe   = cmx_read_stufe($post_id);
			$comm    = cmx_read_comm_values_for_contact($post_id);

			$row = [
				(string)$post_id,
				(string)\get_the_title($p),
				(string)$p->post_status,
				$created,

				(string)$stamm['vorname'],
				(string)$stamm['nachname'],
				(string)($stamm['privat'] ? '1' : '0'),
				(string)$stamm['url_norm'],
				(string)$stamm['domain'],
				(string)$stamm['datum'],

				(string)$addr['rechnung']['strasse'],
				(string)$addr['rechnung']['zusatz'],
				(string)$addr['rechnung']['plz'],
				(string)$addr['rechnung']['ort'],
				(string)$addr['rechnung']['land'],
				(string)$addr['rechnung']['land_label'],

				(string)$addr['liefer']['strasse'],
				(string)$addr['liefer']['zusatz'],
				(string)$addr['liefer']['plz'],
				(string)$addr['liefer']['ort'],
				(string)$addr['liefer']['land'],
				(string)$addr['liefer']['land_label'],

				(string)$stufe['id'],
				(string)$stufe['slug'],
				(string)$stufe['name'],

				(string)$comm['telefon'][1]['value'],
				(string)$comm['telefon'][1]['label_name'],
				$string = (string)$comm['telefon'][2]['value'],
				(string)$comm['telefon'][2]['label_name'],
				(string)$comm['telefon'][3]['value'],
				(string)$comm['telefon'][3]['label_name'],

				(string)$comm['email'][1]['value'],
				(string)$comm['email'][1]['label_name'],
				(string)$comm['email'][2]['value'],
				(string)$comm['email'][2]['label_name'],
				(string)$comm['email'][3]['value'],
				(string)$comm['email'][3]['label_name'],
			];

			fputcsv($out, $row, ';');
		}

		$paged++;
		if (function_exists('flush')) { flush(); }
	} while ($paged <= (int)$q->max_num_pages);

	fclose($out);
	exit;
}
