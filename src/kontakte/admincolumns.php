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

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_admin_image_url_exists')) {
	function cmx_kontakte_admin_image_url_exists(string $url): bool {
		$url = \trim($url);
		if ($url === '') {
			return false;
		}

		$uploads = \wp_get_upload_dir();
		$baseurl = \rtrim((string) ($uploads['baseurl'] ?? ''), '/');
		$basedir = \rtrim((string) ($uploads['basedir'] ?? ''), '/');
		if ($baseurl !== '' && $basedir !== '' && \strpos($url, $baseurl . '/') === 0) {
			$parsed_path = (string) (\wp_parse_url($url, PHP_URL_PATH) ?: '');
			$base_path = (string) (\wp_parse_url($baseurl, PHP_URL_PATH) ?: '');
			if ($parsed_path !== '' && $base_path !== '' && \strpos($parsed_path, $base_path . '/') === 0) {
				$rel = \ltrim((string) \substr($parsed_path, \strlen($base_path)), '/');
			} else {
				$rel = \ltrim((string) \substr($url, \strlen($baseurl)), '/');
				$rel = (string) \preg_replace('/\?.*$/', '', $rel);
			}
			return $rel !== '' && \is_file($basedir . '/' . $rel);
		}

		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_admin_logo_src')) {
	function cmx_kontakte_admin_logo_src(int $post_id): string {
		$local_path = \trim((string) \get_post_meta($post_id, '_cmx_local_image_kontakte_path', true));
		$local_img = \trim((string) \get_post_meta($post_id, '_cmx_local_image_kontakte_url', true));
		if ($local_path !== '' && \is_file($local_path) && $local_img !== '') {
			return $local_img;
		}
		if ($local_img !== '' && cmx_kontakte_admin_image_url_exists($local_img)) {
			return $local_img;
		}

		$thumb_id = (int) \get_post_thumbnail_id($post_id);
		if ($thumb_id > 0) {
			$attached_file = (string) \get_attached_file($thumb_id);
			if ($attached_file !== '' && \is_file($attached_file)) {
				$thumb_url = (string) \wp_get_attachment_image_url($thumb_id, 'thumbnail');
				if ($thumb_url !== '') {
					return $thumb_url;
				}
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_admin_deckungsbeitrag_view_active')) {
	function cmx_admin_deckungsbeitrag_view_active(string $post_type): bool {
		$current_post_type = isset($_GET['post_type']) ? \sanitize_key((string) \wp_unslash($_GET['post_type'])) : '';
		if ($current_post_type !== $post_type) {
			return false;
		}

		$current_view = isset($_GET['cmx_view']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_view'])) : '';
		return $current_view === 'deckungsbeitrag';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_admin_deckungsbeitrag_format_number')) {
	function cmx_admin_deckungsbeitrag_format_number(float $value): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')) {
			return (string) cmx_format_swiss_number($value, 2);
		}

		return \number_format($value, 2, '.', '\'');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_admin_deckungsbeitrag_insert_column')) {
	function cmx_admin_deckungsbeitrag_insert_column(array $columns, string $column_key, string $label): array {
		if (isset($columns[$column_key])) {
			return $columns;
		}

		$new = [];
		$inserted = false;
		foreach ($columns as $key => $column_label) {
			$new[$key] = $column_label;
			if ($key === 'title') {
				$new[$column_key] = $label;
				$inserted = true;
			}
		}

		if (!$inserted) {
			$new[$column_key] = $label;
		}

		return $new;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_admin_deckungsbeitrag_map')) {
	function cmx_admin_deckungsbeitrag_map(string $kind): array {
		static $cache = [];

		if (isset($cache[$kind])) {
			return $cache[$kind];
		}

		if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_chart_payload')) {
			$cache[$kind] = [];
			return [];
		}

		$payload = (array) cmx_cockpit_view_monitor_chart_payload();
		$rows = [];
		$id_key = '';
		$title_key = '';

		switch ($kind) {
			case 'artikel':
				$rows = (array) ($payload['article_rows'] ?? []);
				$id_key = 'article_id';
				$title_key = 'article_title';
				break;
			case 'belege':
				$rows = (array) ($payload['beleg_rows'] ?? []);
				$id_key = 'beleg_id';
				$title_key = 'beleg_title';
				break;
			case 'kontakte':
				$rows = (array) ($payload['contact_rows'] ?? []);
				$id_key = 'contact_id';
				$title_key = 'contact_title';
				break;
			case 'projekte':
				$rows = (array) ($payload['project_rows'] ?? []);
				$id_key = 'project_id';
				$title_key = 'project_title';
				break;
			default:
				$cache[$kind] = [];
				return [];
		}

		$items = [];
		foreach ($rows as $row) {
			if (!\is_array($row)) {
				continue;
			}

			$object_id = (int) ($row[$id_key] ?? 0);
			if ($object_id <= 0) {
				continue;
			}

			if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_post_is_published')) {
				if (!cmx_cockpit_view_monitor_post_is_published($object_id)) {
					continue;
				}
			} elseif (\get_post_status($object_id) !== 'publish') {
				continue;
			}

			if (!isset($items[$object_id])) {
				$title = \trim((string) ($row[$title_key] ?? ''));
				if ($title === '') {
					$title = (string) (\get_the_title($object_id) ?: '');
				}
				$items[$object_id] = [
					'title' => $title,
					'revenue' => 0.0,
					'cost' => 0.0,
					'profit' => 0.0,
					'margin' => 0.0,
				];
			}

			$items[$object_id]['revenue'] += (float) ($row['revenue'] ?? 0.0);
			$items[$object_id]['cost'] += (float) ($row['cost'] ?? 0.0);
		}

		foreach ($items as &$item) {
			$item['revenue'] = \round((float) $item['revenue'], 2);
			$item['cost'] = \round((float) $item['cost'], 2);
			$item['profit'] = \round((float) $item['revenue'] - (float) $item['cost'], 2);
			$item['margin'] = (float) $item['revenue'] !== 0.0
				? \round((((float) $item['profit']) / ((float) $item['revenue'])) * 100, 2)
				: 0.0;
		}
		unset($item);

		\uasort($items, static function (array $left, array $right): int {
			if ((float) $left['profit'] !== (float) $right['profit']) {
				return ((float) $right['profit'] <=> (float) $left['profit']);
			}
			if ((float) $left['revenue'] !== (float) $right['revenue']) {
				return ((float) $right['revenue'] <=> (float) $left['revenue']);
			}
			return \strnatcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
		});

		$cache[$kind] = $items;
		return $items;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_admin_deckungsbeitrag_add_view')) {
	function cmx_admin_deckungsbeitrag_add_view(array $views, string $post_type, string $kind): array {
		if (!\current_user_can('edit_posts')) {
			return $views;
		}

		$args = $_GET ?? [];
		unset($args['paged'], $args['orderby'], $args['order'], $args['_wpnonce'], $args['_wp_http_referer']);
		$args['post_type'] = $post_type;
		$args['cmx_view'] = 'deckungsbeitrag';

		$url = \add_query_arg($args, \admin_url('edit.php'));
		$count = \count(cmx_admin_deckungsbeitrag_map($kind));
		$is_current = cmx_admin_deckungsbeitrag_view_active($post_type);

		$views['cmx_deckungsbeitrag'] = '<a href="' . \esc_url($url) . '"' . ($is_current ? ' class="current" aria-current="page"' : '') . '>Deckungsbeitrag <span class="count">(' . (int) $count . ')</span></a>';
		return $views;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_admin_deckungsbeitrag_apply_query_sort')) {
	function cmx_admin_deckungsbeitrag_apply_query_sort(\WP_Query $query, string $post_type, string $kind): void {
		if (!\is_admin() || !$query->is_main_query() || !cmx_admin_deckungsbeitrag_view_active($post_type)) {
			return;
		}

		$query_post_type = $query->get('post_type');
		$matches_post_type = ((string) $query_post_type === $post_type)
			|| (\is_array($query_post_type) && \in_array($post_type, $query_post_type, true))
			|| ($query_post_type === null && isset($_GET['post_type']) && \sanitize_key((string) \wp_unslash($_GET['post_type'])) === $post_type);
		if (!$matches_post_type) {
			return;
		}

		$ids = \array_map('intval', \array_keys(cmx_admin_deckungsbeitrag_map($kind)));
		if ($ids === []) {
			$ids = [0];
		}

		$query->set('post__in', $ids);
		$query->set('orderby', 'post__in');
		$query->set('order', 'ASC');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_admin_deckungsbeitrag_render_value')) {
	function cmx_admin_deckungsbeitrag_render_value(string $kind, int $post_id): void {
		$item = cmx_admin_deckungsbeitrag_map($kind)[$post_id] ?? null;
		if (!\is_array($item)) {
			echo '';
			return;
		}

		$profit = (float) ($item['profit'] ?? 0.0);
		$margin = (float) ($item['margin'] ?? 0.0);
		$color = $profit < 0 ? '#b42318' : ($profit > 0 ? '#166534' : '#344054');

		echo '<strong style="color:' . \esc_attr($color) . ';">' . \esc_html(cmx_admin_deckungsbeitrag_format_number($profit)) . '</strong>';
		echo '<span style="display:block;margin-top:2px;font-size:11px;color:#98a2b3;">' . \esc_html(cmx_admin_deckungsbeitrag_format_number($margin)) . '%</span>';
	}
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
		$src = \function_exists(__NAMESPACE__ . '\\cmx_kontakte_admin_logo_src')
			? (string) cmx_kontakte_admin_logo_src((int) $post_id)
			: '';
		if ($src !== '') {
			$path     = \parse_url($src, PHP_URL_PATH);
			$filename = $path ? basename($path) : ('kontakt-' . (int) $post_id . '.jpg');
			$img_tag  = '<img class="cmx-ac-thumb" src="' . \esc_url($src) . '" alt="" onerror="this.onerror=null; if(this.parentNode){ this.parentNode.remove(); }">';
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
	if (!\function_exists(__NAMESPACE__ . '\\cmx_admin_post_type_column_is_visible') || cmx_admin_post_type_column_is_visible('kontakte', 'cmx_kategorie')) {
		cmx_render_tax_filter_dropdown(taxonomy:$kundkat_tax, param:'filter_kundenkategorie', label:'Alle Kategorien');
	}

	// NEU: Stufen
	$stufen_tax = cmx_stufen_tax();
	if (!\function_exists(__NAMESPACE__ . '\\cmx_admin_post_type_column_is_visible') || cmx_admin_post_type_column_is_visible('kontakte', 'cmx_stufen')) {
		cmx_render_tax_filter_dropdown(taxonomy:$stufen_tax, param:'filter_stufen', label:'Alle Stufen');
	}
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
	if (
		(!\function_exists(__NAMESPACE__ . '\\cmx_admin_post_type_column_is_visible') || cmx_admin_post_type_column_is_visible('kontakte', 'cmx_kategorie'))
		&& !empty($_GET['filter_kundenkategorie'])
	) {
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
	if (
		(!\function_exists(__NAMESPACE__ . '\\cmx_admin_post_type_column_is_visible') || cmx_admin_post_type_column_is_visible('kontakte', 'cmx_stufen'))
		&& !empty($_GET['filter_stufen'])
	) {
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
		.column-cmx_gmaps,
		.column-cmx_hersteller_url,
		.column-cmx_kontakt_belege { width:56px; text-align:center; padding-left:0 !important; padding-right:0 !important; }
		th#cmx_kontakt_belege { text-indent:2ch; }
		.column-cmx_stufen { width:56px; max-width:56px; }
		.column-cmx_email_1 { width:220px; min-width:220px; }
		.column-cmx_gmaps .dashicons { font-size:20px; width:20px; height:20px; line-height:20px; }
		.column-cmx_gmaps a.cmx-gmaps-link { display:inline-block; padding:2px; }
		.column-cmx_hersteller_url a,
		.column-cmx_kontakt_belege a { display:inline-block; padding:2px; }
	</style>';
	\wp_enqueue_style('dashicons');
});

\add_action('admin_head', function () {
	$screen = \get_current_screen();
	if (!$screen || $screen->post_type !== 'kontakte') return;
	$stufen_param = 'filter_stufen';
	echo '<style>select#'.esc_attr($stufen_param).' { max-width:120px; } </style>';
});

\add_action('admin_head-edit.php', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== 'kontakte' || (string) ($screen->base ?? '') !== 'edit') {
		return;
	}
	?>
	<style>
		body.post-type-kontakte {
			background: linear-gradient(180deg, #f7fbff 0%, #eef4fb 100%);
		}
		body.post-type-kontakte .wrap > h1.wp-heading-inline,
		body.post-type-kontakte .wrap > .page-title-action,
		body.post-type-kontakte .wrap > hr.wp-header-end {
			display: none;
		}
		body.post-type-kontakte .wrap {
			margin: 18px 20px 0 2px;
		}
		body.post-type-kontakte .wrap::before {
			content: "Kontakte";
			display: block;
			margin: 0 0 10px;
			font-size: 20px;
			line-height: 1.3;
			font-weight: 700;
			color: #1f2937;
		}
		body.post-type-kontakte .wrap::after {
			content: "Kontakte übersichtlich verwalten, filtern und direkt weiterverarbeiten.";
			display: block;
			margin: 0 0 18px;
			color: #475467;
			font-size: 14px;
		}
		body.post-type-kontakte #posts-filter {
			display: grid;
			grid-template-columns: minmax(0, 1fr) auto;
			grid-template-areas:
				"views search"
				"topnav topnav"
				"table table"
				"bottomnav bottomnav";
			align-items: start;
			gap: 18px;
			margin: 0;
			padding: 0;
			border: 0;
			border-radius: 0;
			background: transparent;
			box-shadow: none;
		}
		body.post-type-kontakte .subsubsub {
			grid-area: views;
			float: none;
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			gap: 0;
			margin: 0 0 0 15px;
			padding: 0;
			border: 0;
			border-radius: 0;
			background: transparent;
			box-shadow: none;
		}
		body.post-type-kontakte .subsubsub li {
			margin: 0;
			display: inline-flex;
			align-items: center;
		}
		body.post-type-kontakte .subsubsub li:not(:last-child)::after {
			content: "";
			margin: 10px 14px;
			color: #98a2b3;
		}
		body.post-type-kontakte .subsubsub a {
			display: inline-flex;
			align-items: center;
			padding: 0;
			border: 0;
			border-radius: 0;
			background: transparent;
			box-shadow: none;
			color: #27508a;
			text-decoration: none;
			font-weight: 600;
			line-height: 1;
		}
		body.post-type-kontakte .subsubsub a.current {
			background: transparent;
			box-shadow: none;
			color: #1f2937;
			text-decoration: none;
			font-weight: 700;
		}
		body.post-type-kontakte .subsubsub .count {
			color: #667085;
			line-height: 1;
			margin-left: 0.25em;
		}
		body.post-type-kontakte #posts-filter > .search-box {
			grid-area: search;
			float: none;
			display: flex;
			align-items: center;
			justify-content: flex-end;
			gap: 10px;
			width: auto;
			max-width: none;
			margin: 0;
			padding: 0;
			border: 0;
			border-radius: 0;
			background: transparent;
			box-shadow: none;
			align-self: start;
			justify-self: end;
			margin-top: -46px;
			margin-right: 6px;
		}
		body.post-type-kontakte .tablenav.top,
		body.post-type-kontakte .tablenav.bottom {
			float: none;
			display: grid;
			grid-template-columns: minmax(0, 1fr) auto;
			align-items: center;
			gap: 14px;
			margin: 0;
			padding: 14px 16px;
			border: 0px solid #c9d8ee;
			border-radius: 14px;
			/* background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%); */
			/* box-shadow: 0 8px 18px rgba(30, 64, 175, 0.06); */
		}
		body.post-type-kontakte .tablenav.top {
			grid-area: topnav;
			margin: 0;
			padding-right: 0;
		}
		body.post-type-kontakte .tablenav.bottom {
			grid-area: bottomnav;
		}
		body.post-type-kontakte .tablenav.bottom {
			margin-top: 18px;
		}
		body.post-type-kontakte .tablenav .actions {
			display: flex;
			align-items: center;
			justify-content: flex-start;
			flex-wrap: wrap;
			gap: 10px;
			padding: 0;
		}
		body.post-type-kontakte .tablenav.top .actions {
			margin-left: -10px;
		}
		body.post-type-kontakte .tablenav .actions select,
		body.post-type-kontakte .tablenav .actions input[type="search"],
		body.post-type-kontakte .search-box input[type="search"] {
			min-height: 40px;
			border: 1px solid #b9cae6;
			border-radius: 10px;
			background: #fff;
			box-shadow: inset 0 1px 2px rgba(16, 24, 40, 0.04);
		}
		body.post-type-kontakte .tablenav.top .actions select,
		body.post-type-kontakte .tablenav.top .actions .button {
			position: relative;
			top: -5px;
		}
		body.post-type-kontakte .tablenav .actions .button,
		body.post-type-kontakte .search-box .button {
			min-height: 40px;
			padding: 0 14px;
			border-radius: 10px;
		}
		body.post-type-kontakte #post-search-input,
		body.post-type-kontakte #search-submit {
			position: relative;
			top: 16px;
			margin-bottom: 0;
			margin-right: -5px;
		}
		body.post-type-kontakte .search-box {
			display: flex;
			align-items: center;
			gap: 10px;
			margin: 0;
		}
		body.post-type-kontakte .tablenav .tablenav-pages {
			justify-self: end;
			margin: 0;
			text-align: right;
		}
		body.post-type-kontakte .tablenav.top .tablenav-pages,
		body.post-type-kontakte .tablenav.bottom .tablenav-pages {
			display: flex;
			align-items: center;
			justify-content: flex-end;
			gap: 12px;
		}
		body.post-type-kontakte #post-query-submit {
			position: relative;
			left: 5px;
		}
		body.post-type-kontakte .tablenav.top .tablenav-pages {
			margin-right: -45px;
		}
		body.post-type-kontakte .wp-list-table {
			grid-area: table;
			margin-top: -10px;
			border: 1px solid #c9d8ee;
			border-radius: 16px;
			background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
			box-shadow: 0 10px 24px rgba(30, 64, 175, 0.06);
			overflow: hidden;
		}
		body.post-type-kontakte .wp-list-table thead th,
		body.post-type-kontakte .wp-list-table tfoot th {
			/* background: linear-gradient(180deg, #ffffff 0%, #eef4fc 100%); */
			color: #22324a;
			border-bottom: 1px solid #d9e4f2;
			font-size: 13px;
			font-weight: 700;
			padding-top: 15px;
			padding-bottom: 9px;
		}
		body.post-type-kontakte .wp-list-table tbody td,
		body.post-type-kontakte .wp-list-table tbody th {
			padding-top: 16px;
			padding-bottom: 16px;
			border-bottom: 1px solid #e0e8f3;
			background: rgba(255, 255, 255, 0.82);
			vertical-align: top;
		}
		body.post-type-kontakte .wp-list-table tbody tr:hover td,
		body.post-type-kontakte .wp-list-table tbody tr:hover th {
			background: #f4f8ff;
		}
		body.post-type-kontakte .wp-list-table .check-column {
			padding-top: 18px;
		}
		body.post-type-kontakte .wp-list-table .row-title {
			font-size: 17px;
			font-weight: 700;
			color: #1f2937;
		}
		body.post-type-kontakte .wp-list-table td a,
		body.post-type-kontakte .wp-list-table th a {
			color: #27508a;
		}
		body.post-type-kontakte .wp-list-table .row-actions {
			margin-top: 8px;
			color: #98a2b3;
			display: flex;
			flex-wrap: wrap;
			gap: 0 0.9em;
		}
		body.post-type-kontakte .wp-list-table .row-actions > span {
			display: inline-flex;
			align-items: center;
		}
		body.post-type-kontakte .wp-list-table .row-actions a {
			color: #27508a;
		}
		body.post-type-kontakte .column-title {
			width: 300px;
			min-width: 300px;
			max-width: 300px;
		}
		body.post-type-kontakte .column-cmx_kategorie a,
		body.post-type-kontakte .column-cmx_stufen a {
			display: inline-flex;
			align-items: center;
			padding: 5px 10px;
			margin: 0 6px 6px 0;
			border: 1px solid #cfe0f6;
			border-radius: 999px;
			background: #f4f8ff;
			text-decoration: none;
			font-size: 12px;
			font-weight: 600;
		}
		body.post-type-kontakte .column-cmx_tel_1 a,
		body.post-type-kontakte .column-cmx_email_1 a {
			font-weight: 600;
		}
		body.post-type-kontakte .tablenav-pages .current-page,
		body.post-type-kontakte .tablenav-pages a,
		body.post-type-kontakte .tablenav-pages .paging-input {
			border-radius: 9px;
		}
		@media (max-width: 960px) {
			body.post-type-kontakte #posts-filter {
				display: grid;
				grid-template-columns: 1fr;
				grid-template-areas:
					"views"
					"search"
					"topnav"
					"table"
					"bottomnav";
			}
			body.post-type-kontakte #posts-filter > .search-box {
				justify-content: flex-start;
				margin: 0 0 18px;
			}
			body.post-type-kontakte .tablenav.top,
			body.post-type-kontakte .tablenav.bottom {
				grid-template-columns: 1fr;
				align-items: stretch;
			}
			body.post-type-kontakte .tablenav .actions,
			body.post-type-kontakte .search-box {
				flex-wrap: wrap;
			}
			body.post-type-kontakte .tablenav .tablenav-pages {
				justify-self: stretch;
			}
		}
	</style>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			var wrap = document.querySelector('body.post-type-kontakte .wrap');
			if (!wrap) {
				return;
			}
			var walker = document.createTreeWalker(wrap, NodeFilter.SHOW_TEXT, null);
			var toRemove = [];
			while (walker.nextNode()) {
				var node = walker.currentNode;
				if (node.textContent.replace(/\|/g, '').trim() === '') {
					toRemove.push(node);
				}
			}
			toRemove.forEach(function (node) {
				node.remove();
			});
		});
	</script>
	<?php
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

	foreach ($columns as $key => $label) {
		// Original übernehmen
		if ($key === 'cmx_logo') {
			$logo_label = $label;
			continue;
		}
		$new[$key] = $label;
	}

	// Falls weder URL noch Karte existiert: ans Ende anhängen
	if (!isset($new['cmx_hersteller_url'])) {
		$new['cmx_hersteller_url'] = 'URL';
	}
	if (!isset($new['cmx_kontakt_belege'])) $new['cmx_kontakt_belege'] = 'P';
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
		echo '<a href="' . \esc_url($href) . '" target="_blank" rel="noopener noreferrer" title="' . \esc_attr($href) . '"><span class="dashicons dashicons-admin-site" style="font-size:14px;opacity:0.8;position:relative;top:7px;"></span></a>';
		return;
	}

	if ($column === 'cmx_kontakt_belege') {
		if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_belege_share_url')) {
			echo '';
			return;
		}

		$url = (string) cmx_kontakt_belege_share_url($post_id);
		if ($url === '') {
			echo '';
			return;
		}

		echo '<a href="' . \esc_url($url) . '" class="cmx-kontakt-belege-link" title="Alle Belege dieses Kontakts anzeigen" target="_blank" rel="noopener noreferrer" data-copy-url="' . \esc_attr($url) . '"><span class="dashicons dashicons-portfolio" style="font-size:14px;opacity:0.8;position:relative;top:4px;"></span></a>';
		return;
	}

	if ($column === 'cmx_firmengruendung' || $column === 'cmx_geburtsdatum') {
		$meta_key = ($column === 'cmx_firmengruendung')
			? CMX_KONTAKTE_META_FIRMENGRUENDUNG
			: CMX_KONTAKTE_META_GEBURTSDATUM;
		$raw = (string) \get_post_meta($post_id, $meta_key, true);
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

\add_action('admin_footer-edit.php', __NAMESPACE__ . '\\cmx_kontakte_admin_list_copy_share_link');
function cmx_kontakte_admin_list_copy_share_link(): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== 'kontakte' || (string) ($screen->base ?? '') !== 'edit') {
		return;
	}
	?>
	<script>
	(function(){
		function copyFallback(text){
			var input=document.createElement('textarea');
			input.value=text;
			input.setAttribute('readonly','readonly');
			input.style.position='fixed';
			input.style.opacity='0';
			document.body.appendChild(input);
			input.focus();
			input.select();
			try { document.execCommand('copy'); } catch (e) {}
			document.body.removeChild(input);
		}
		document.addEventListener('click', function(ev){
			var link = ev.target.closest('.cmx-kontakt-belege-link');
			if (!link) return;
			var text = (link.getAttribute('data-copy-url') || link.href || '').trim();
			if (!text) return;
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).catch(function(){ copyFallback(text); });
				return;
			}
			copyFallback(text);
		});
	})();
	</script>
	<?php
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

function cmx_kontakte_search_address_meta_keys(): array {
	$keys = [
		CMX_RECHNUNG_META_STRASSE,
		CMX_RECHNUNG_META_ZUSATZ,
		CMX_RECHNUNG_META_PLZ,
		CMX_RECHNUNG_META_ORT,
		CMX_RECHNUNG_META_LAND,
		CMX_LIEFER_META_STRASSE,
		CMX_LIEFER_META_ZUSATZ,
		CMX_LIEFER_META_PLZ,
		CMX_LIEFER_META_ORT,
		CMX_LIEFER_META_LAND,
		'_cmx_rechnung',
		'cmx_rechnung',
		'rechnung',
		'_cmx_liefer',
		'cmx_liefer',
		'liefer',
		'cmx_billing',
		'cmx_shipping',
		'rechnung_strasse',
		'rechnung_zusatz',
		'rechnung_plz',
		'rechnung_ort',
		'rechnung_land',
		'billing_street',
		'billing_zip',
		'billing_city',
		'billing_country',
		'liefer_strasse',
		'liefer_zusatz',
		'liefer_plz',
		'liefer_ort',
		'liefer_land',
		'shipping_street',
		'shipping_zip',
		'shipping_city',
		'shipping_country',
		'strasse',
		'plz',
		'ort',
		'land',
		'street',
		'zip',
		'city',
		'country',
	];

	return \array_values(\array_unique(\array_filter(\array_map('strval', $keys))));
}

function cmx_kontakte_search_terms(string $search_term): array {
	$search_term = \trim($search_term);
	if ($search_term === '') {
		return [];
	}

	$terms = [$search_term];
	$umlaut_variant = \strtr($search_term, [
		'Ä' => 'Ae',
		'Ö' => 'Oe',
		'Ü' => 'Ue',
		'ä' => 'ae',
		'ö' => 'oe',
		'ü' => 'ue',
		'ß' => 'ss',
	]);

	if ($umlaut_variant !== $search_term) {
		$terms[] = $umlaut_variant;
	}

	return \array_values(\array_unique(\array_filter(\array_map('strval', $terms))));
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
	$search_terms = cmx_kontakte_search_terms($search_term);
	if (empty($search_terms)) {
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

	$default_match_ids = [];
	foreach ($search_terms as $lookup_term) {
		$default_match_ids = \array_merge($default_match_ids, (array) \get_posts(\array_merge($lookup_args, [
			's' => $lookup_term,
		])));
	}

	$email_meta_query = ['relation' => 'OR'];
	foreach ($search_terms as $lookup_term) {
		foreach (\array_merge(
			cmx_kontakte_search_email_meta_keys(),
			cmx_kontakte_search_address_meta_keys()
		) as $meta_key) {
			$email_meta_query[] = [
				'key' => $meta_key,
				'value' => $lookup_term,
				'compare' => 'LIKE',
			];
		}
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

\add_filter('views_edit-kontakte', function(array $views): array {
	return cmx_admin_deckungsbeitrag_add_view($views, 'kontakte', 'kontakte');
}, 30);

\add_filter('manage_edit-kontakte_columns', function(array $columns): array {
	if (!cmx_admin_deckungsbeitrag_view_active('kontakte')) {
		return $columns;
	}

	return cmx_admin_deckungsbeitrag_insert_column($columns, 'cmx_deckungsbeitrag', 'Deckungsbeitrag');
}, 900);

\add_action('manage_kontakte_posts_custom_column', function(string $column, int $post_id): void {
	if ($column !== 'cmx_deckungsbeitrag' || !cmx_admin_deckungsbeitrag_view_active('kontakte')) {
		return;
	}

	cmx_admin_deckungsbeitrag_render_value('kontakte', $post_id);
}, 20, 2);

\add_action('pre_get_posts', function(\WP_Query $query): void {
	cmx_admin_deckungsbeitrag_apply_query_sort($query, 'kontakte', 'kontakte');
}, 999);
