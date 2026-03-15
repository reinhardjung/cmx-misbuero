<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/* ------------------------------
 * Admin-Skripte nur im Belege-Editor laden
 * ------------------------------ */
add_action('admin_enqueue_scripts', function() {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if ($screen && $screen->post_type === 'belege') {
		wp_enqueue_script('jquery-ui-autocomplete');
		wp_enqueue_script('jquery-ui-sortable');
		wp_enqueue_style('wp-jquery-ui-dialog'); // Basis-Styles für jQuery UI
	}
});

/* ---------------------------------
 * Zentrale Helper: Artikel-Nr (SKU) – robust mit Fallbacks
 * --------------------------------- */
if (!function_exists(__NAMESPACE__ . '\cmx_get_artikel_nr')) {
	function cmx_get_artikel_nr($post_id){
		$nr = get_post_meta($post_id, 'cmx_artikel_sku', true);
		if ($nr === '' || $nr === null) {
			$tmp = get_post_meta($post_id, '_cmx_artikel_sku', true);
			if (is_string($tmp) && $tmp !== '' && strpos($tmp, 'field_') !== 0) {
				$nr = $tmp;
			}
		}
		if ($nr === '' || $nr === null) $nr = get_post_meta($post_id, '_cmx_artikel_nr', true);
		if ($nr === '' || $nr === null) $nr = get_post_meta($post_id, '_sku', true);

		if (($nr === '' || $nr === null) && function_exists('wc_get_product')) {
			$p = wc_get_product($post_id);
			if ($p && method_exists($p, 'get_sku')) $nr = $p->get_sku();
		}
		return is_string($nr) ? trim($nr) : '';
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_beleg_decode_label_text')) {
	function cmx_beleg_decode_label_text(string $value): string {
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
}

if (!function_exists(__NAMESPACE__ . '\cmx_artikel_einheiten_taxonomies')) {
	function cmx_artikel_einheiten_taxonomies(): array {
		static $cache = null;
		if ($cache !== null) {
			return $cache;
		}

		$cache = [];

		// Belege-Positionen sollen exakt dieselbe Einheiten-Taxonomie wie Artikel nutzen.
		$const = __NAMESPACE__ . '\\TAX_ARTIKEL_EINHEITEN';
		if (\defined($const)) {
			$tax = \sanitize_key((string) \constant($const));
			if ($tax !== '' && \taxonomy_exists($tax)) {
				$cache = [$tax];
				return $cache;
			}
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_tax_einheiten')) {
			$tax = \sanitize_key((string) cmx_tax_einheiten());
			if ($tax !== '' && \taxonomy_exists($tax)) {
				$cache = [$tax];
				return $cache;
			}
		}

		foreach (['artikel_einheit', 'artikel_einheiten', 'einheit', 'einheiten'] as $candidate) {
			$tax = \sanitize_key((string) $candidate);
			if ($tax !== '' && \taxonomy_exists($tax)) {
				$cache = [$tax];
				return $cache;
			}
		}

		return $cache;
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_artikel_einheiten_taxonomy')) {
	function cmx_artikel_einheiten_taxonomy(): string {
		$taxes = \function_exists(__NAMESPACE__ . '\\cmx_artikel_einheiten_taxonomies')
			? cmx_artikel_einheiten_taxonomies()
			: [];
		return (string) ($taxes[0] ?? '');
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_artikel_einheiten_options')) {
	function cmx_artikel_einheiten_options(): array {
		static $cache = null;
		if ($cache !== null) {
			return $cache;
		}

		$cache = [];
		$taxes = \function_exists(__NAMESPACE__ . '\\cmx_artikel_einheiten_taxonomies')
			? cmx_artikel_einheiten_taxonomies()
			: [];
		if (empty($taxes)) {
			return $cache;
		}

		$seen = [];
		foreach ($taxes as $tax) {
			$terms = \get_terms([
				'taxonomy'   => $tax,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]);
			if (\is_wp_error($terms) || !\is_array($terms)) {
				continue;
			}

			foreach ($terms as $term) {
				if (!$term instanceof \WP_Term) continue;
				$term_id = (int) ($term->term_id ?? 0);
				if ($term_id <= 0 || isset($seen[$term_id])) continue;
				$name = \trim((string) ($term->name ?? ''));
				if ($name === '') continue;
				$cache[] = [
					'id'   => $term_id,
					'name' => $name,
				];
				$seen[$term_id] = true;
			}
		}

		\usort($cache, static function(array $a, array $b): int {
			return \strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
		});

		return $cache;
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_artikel_einheit_name_by_id')) {
	function cmx_artikel_einheit_name_by_id(int $term_id): string {
		if ($term_id <= 0) return '';

		$taxes = \function_exists(__NAMESPACE__ . '\\cmx_artikel_einheiten_taxonomies')
			? cmx_artikel_einheiten_taxonomies()
			: [];
		foreach ($taxes as $tax) {
			$term = \get_term($term_id, $tax);
			if ($term && !\is_wp_error($term) && $term instanceof \WP_Term) {
				$name = \trim((string) ($term->name ?? ''));
				if ($name !== '') {
					return $name;
				}
			}
		}
		return '';
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_artikel_default_einheit')) {
	function cmx_artikel_default_einheit(int $artikel_id): array {
		static $cache = [];
		if (isset($cache[$artikel_id])) {
			return $cache[$artikel_id];
		}

		$cache[$artikel_id] = ['id' => 0, 'name' => ''];
		if ($artikel_id <= 0) {
			return $cache[$artikel_id];
		}

		$taxes = \function_exists(__NAMESPACE__ . '\\cmx_artikel_einheiten_taxonomies')
			? cmx_artikel_einheiten_taxonomies()
			: [];
		if (empty($taxes)) {
			return $cache[$artikel_id];
		}

		$term_id = 0;
		foreach ($taxes as $tax) {
			$term_ids = \wp_get_post_terms($artikel_id, $tax, ['fields' => 'ids']);
			if (\is_wp_error($term_ids) || empty($term_ids)) {
				continue;
			}
			$term_id = (int) ($term_ids[0] ?? 0);
			if ($term_id > 0) {
				break;
			}
		}
		if ($term_id <= 0) {
			return $cache[$artikel_id];
		}

		$name = '';
		if (\function_exists(__NAMESPACE__ . '\\cmx_artikel_einheiten_options')) {
			foreach (cmx_artikel_einheiten_options() as $opt) {
				if ((int) ($opt['id'] ?? 0) === $term_id) {
					$name = (string) ($opt['name'] ?? '');
					break;
				}
			}
		}
		if ($name === '' && \function_exists(__NAMESPACE__ . '\\cmx_artikel_einheit_name_by_id')) {
			$name = cmx_artikel_einheit_name_by_id($term_id);
		}

		$cache[$artikel_id] = ['id' => $term_id, 'name' => $name];
		return $cache[$artikel_id];
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_beleg_resolve_position_unit')) {
	function cmx_beleg_resolve_position_unit(array $row, int $artikel_id = 0): array {
		static $map_cache = null;
		if ($map_cache === null) {
			$map_cache = ['by_id' => [], 'by_name' => []];
			if (\function_exists(__NAMESPACE__ . '\\cmx_artikel_einheiten_options')) {
				foreach (cmx_artikel_einheiten_options() as $opt) {
					$id = (int) ($opt['id'] ?? 0);
					$name = \trim((string) ($opt['name'] ?? ''));
					if ($id <= 0 || $name === '') continue;
					$map_cache['by_id'][$id] = $name;
					$key = \function_exists('mb_strtolower')
						? \mb_strtolower($name, 'UTF-8')
						: \strtolower($name);
					$map_cache['by_name'][$key] = $id;
				}
			}
		}

		$has_unit_fields = \array_key_exists('einheit_id', $row)
			|| \array_key_exists('unit_id', $row)
			|| \array_key_exists('unit', $row)
			|| \array_key_exists('einheit', $row);

		$einheit_id_raw = $row['einheit_id'] ?? ($row['unit_id'] ?? 0);
		$einheit_id = \is_numeric((string) $einheit_id_raw) ? (int) $einheit_id_raw : 0;
		$unit = \sanitize_text_field((string) ($row['unit'] ?? ($row['einheit'] ?? '')));

		if ($einheit_id > 0 && isset($map_cache['by_id'][$einheit_id])) {
			$unit = (string) $map_cache['by_id'][$einheit_id];
		}

		if ($einheit_id <= 0 && $unit !== '') {
			$key = \function_exists('mb_strtolower')
				? \mb_strtolower($unit, 'UTF-8')
				: \strtolower($unit);
			if (isset($map_cache['by_name'][$key])) {
				$einheit_id = (int) $map_cache['by_name'][$key];
				$unit = (string) ($map_cache['by_id'][$einheit_id] ?? $unit);
			}
		}

		// Nur dann auf Artikel-Default zurückfallen, wenn in der Zeile keinerlei Einheit-Daten vorhanden sind.
		// So bleibt eine bewusst leere Auswahl ("— auswählen —") erhalten.
		if (
			$einheit_id <= 0
			&& !$has_unit_fields
			&& $artikel_id > 0
			&& \function_exists(__NAMESPACE__ . '\\cmx_artikel_default_einheit')
		) {
			$default = cmx_artikel_default_einheit($artikel_id);
			$default_id = (int) ($default['id'] ?? 0);
			if ($default_id > 0) {
				$einheit_id = $default_id;
				$unit = (string) ($default['name'] ?? '');
			}
		}

			if ($einheit_id > 0 && $unit === '' && isset($map_cache['by_id'][$einheit_id])) {
				$unit = (string) $map_cache['by_id'][$einheit_id];
			}
			if ($einheit_id > 0 && $unit === '' && \function_exists(__NAMESPACE__ . '\\cmx_artikel_einheit_name_by_id')) {
				$unit = (string) cmx_artikel_einheit_name_by_id($einheit_id);
			}

		return [
			'einheit_id' => $einheit_id > 0 ? $einheit_id : 0,
			'unit'       => $unit,
		];
	}
}

/* ---------------------------------
 * Taxonomie-Erkennung: Belege-Textbausteine
 * --------------------------------- */
if (!function_exists(__NAMESPACE__ . '\cmx_beleg_textbaustein_taxonomy')) {
	function cmx_beleg_textbaustein_taxonomy(): string {
		$candidates = [];
		$const = __NAMESPACE__ . '\\TAX_BELEGE_BELEGETEXTBAUSTEINE';
		if (\defined($const)) {
			$candidates[] = (string) \constant($const);
		}
		$candidates = \array_merge($candidates, [
			'belege_belegetextbausteine',
			'belege_textbausteine',
			'belege_textbaustein',
			'beleg_textbausteine',
			'beleg_textbaustein',
		]);
		foreach ($candidates as $tax) {
			$tax = \sanitize_key((string) $tax);
			if ($tax !== '' && \taxonomy_exists($tax)) return $tax;
		}
		return '';
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_beleg_textbaustein_items')) {
	function cmx_beleg_textbaustein_items(string $search = '', int $limit = 30): array {
		$items = [];
		$seen  = [];
		$search = trim((string) $search);
		$search_lc = $search !== '' ? (function_exists('mb_strtolower') ? mb_strtolower($search, 'UTF-8') : strtolower($search)) : '';

		$add_item = static function(string $name, string $desc = '', int $id = 0) use (&$items, &$seen, $search_lc): void {
			$name = trim($name);
			$desc = trim($desc);
			if ($name === '' && $desc === '') return;

			$name_lc = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
			$desc_lc = function_exists('mb_strtolower') ? mb_strtolower($desc, 'UTF-8') : strtolower($desc);
			if ($search_lc !== '' && strpos($name_lc, $search_lc) === false && strpos($desc_lc, $search_lc) === false) {
				return;
			}

			$key = $name_lc . '|' . $desc_lc;
			if (isset($seen[$key])) return;
			$seen[$key] = true;

			$items[] = [
				'label' => $name,
				'value' => $id,
				'nr'    => $name,
				'title' => $desc,
				'text'  => ($desc !== '' ? $desc : $name),
			];
		};

		$tax = cmx_beleg_textbaustein_taxonomy();
		if ($tax !== '') {
			$terms = get_terms([
				'taxonomy'   => $tax,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'number'     => $limit,
			]);
			if (!is_wp_error($terms) && is_array($terms)) {
				foreach ($terms as $t) {
					$add_item((string)($t->name ?? ''), (string)($t->description ?? ''), (int)($t->term_id ?? 0));
				}
			}
		}

		// Fallback/Ergaenzung aus INI-Section [BelegeTextbausteine]
		$ini_file = \dirname(__DIR__, 2) . '/includes/globales.ini';
		if (\is_file($ini_file)) {
			$ini = \parse_ini_file($ini_file, true, INI_SCANNER_TYPED);
			if (\is_array($ini)) {
				foreach ($ini as $section_name => $section_data) {
					if (!\is_array($section_data)) continue;
					if (\strcasecmp((string) $section_name, 'BelegeTextbausteine') !== 0) continue;
					foreach ($section_data as $name => $desc_raw) {
						$desc = \is_array($desc_raw)
							? \implode(', ', \array_values(\array_filter(\array_map(static fn($v) => \trim((string) $v), $desc_raw), static fn($v) => $v !== '')))
							: \trim((string) $desc_raw);
						$add_item((string) $name, $desc, 0);
					}
					break;
				}
			}
		}

		if (\count($items) > $limit) {
			$items = \array_slice($items, 0, $limit);
		}
		return $items;
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_beleg_textbaustein_admin_url')) {
	function cmx_beleg_textbaustein_admin_url(): string {
		$tax = cmx_beleg_textbaustein_taxonomy();
		if ($tax === '') {
			return '';
		}
		return \admin_url('edit-tags.php?taxonomy=' . \rawurlencode($tax) . '&post_type=belege');
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_beleg_meta_array')) {
	function cmx_beleg_meta_array(int $post_id, string $meta_key): array {
		$raw = \get_post_meta($post_id, $meta_key, true);
		if (\is_string($raw) && $raw !== '') {
			$tmp = \json_decode($raw, true);
			if (\json_last_error() === JSON_ERROR_NONE && \is_array($tmp)) {
				return $tmp;
			}
			$tmp = @\maybe_unserialize($raw);
			return \is_array($tmp) ? $tmp : [];
		}
		return \is_array($raw) ? $raw : [];
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_beleg_truthy')) {
	function cmx_beleg_truthy($value): bool {
		if ($value === true) return true;
		if ($value === 1 || $value === '1') return true;
		$s = \strtolower(\trim((string) $value));
		return \in_array($s, ['true', 'yes', 'on'], true);
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_beleg_open_task_items')) {
	function cmx_beleg_open_task_items(int $source_id = 0, string $search = '', int $limit = 150, string $source_type = 'projekte'): array {
		$limit = max(1, min(500, (int) $limit));
		$search = \trim((string) $search);
		$search_lc = $search !== ''
			? (\function_exists('mb_strtolower') ? \mb_strtolower($search, 'UTF-8') : \strtolower($search))
			: '';
		$source_type = \sanitize_key((string) $source_type);
		if (!\in_array($source_type, ['projekte', 'kontakte'], true)) {
			$source_type = 'projekte';
		}

		$source_ids = [];
		if ($source_id > 0) {
			$post = \get_post($source_id);
			if ($post && $post->post_type === $source_type && $post->post_status !== 'trash') {
				$source_ids[] = (int) $source_id;
			}
		} else {
			$q = new \WP_Query([
				'post_type'      => $source_type,
				'post_status'    => ['publish', 'draft', 'pending', 'future', 'private'],
				'posts_per_page' => 200,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]);
			$source_ids = \array_map('intval', (array) ($q->posts ?? []));
			\wp_reset_postdata();
		}
		if (empty($source_ids)) return [];

		$items = [];
		$vk_cache = [];
		$vk_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VK') ? CMX_ARTIKEL_META_VK : '_cmx_artikel_vk';

		foreach ($source_ids as $pid) {
			$source_title = \function_exists(__NAMESPACE__ . '\\cmx_beleg_decode_label_text')
				? cmx_beleg_decode_label_text((string) \get_the_title($pid))
				: (string) \get_the_title($pid);
			if ($source_title === '') $source_title = '#' . $pid;

			$tasks = cmx_beleg_meta_array($pid, '_cmx_projekt_tasks');
			if (empty($tasks)) continue;

			foreach ($tasks as $idx => $task) {
				if (!\is_array($task)) continue;
				$is_billable = \array_key_exists('verrechenbar', $task)
					? cmx_beleg_truthy($task['verrechenbar'])
					: true;
				if (!$is_billable) continue;
				if (cmx_beleg_truthy($task['abgerechnet'] ?? 0)) continue;

				$artikel_id = (int) ($task['artikel_id'] ?? 0);
				$dauer = (float) cmx_norm_decimal((string) ($task['dauer'] ?? '0'));
				if ($artikel_id <= 0 || $dauer <= 0) continue;

				if (!isset($vk_cache[$artikel_id])) {
					$vk_raw = (string) \get_post_meta($artikel_id, $vk_key, true);
					$vk_cache[$artikel_id] = (float) cmx_norm_decimal($vk_raw);
				}
				$preis = (float) $vk_cache[$artikel_id];
				if (!\is_finite($preis)) $preis = 0.0;

				$artikel_title = \function_exists(__NAMESPACE__ . '\\cmx_beleg_decode_label_text')
					? cmx_beleg_decode_label_text((string) \get_the_title($artikel_id))
					: (string) \get_the_title($artikel_id);
				if ($artikel_title === '') $artikel_title = '#' . $artikel_id;
				$artikel_nr = \function_exists(__NAMESPACE__ . '\\cmx_get_artikel_nr')
					? (string) cmx_get_artikel_nr($artikel_id)
					: '';
				$artikel_label = \trim(($artikel_nr !== '' ? $artikel_nr . ' – ' : '') . $artikel_title);

				$info  = \trim((string) ($task['info'] ?? ''));
				$datum = \trim((string) ($task['datum'] ?? ''));
				$zeit  = \trim((string) ($task['zeit'] ?? ''));
				$uid   = (string) \preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($task['uid'] ?? ''));

				if ($search_lc !== '') {
					$hay = $source_title . ' ' . $artikel_label . ' ' . $info . ' ' . $datum . ' ' . $zeit;
					$hay_lc = \function_exists('mb_strtolower') ? \mb_strtolower($hay, 'UTF-8') : \strtolower($hay);
					if (\strpos($hay_lc, $search_lc) === false) continue;
				}

				$items[] = [
					'source_type'   => $source_type,
					'source_id'     => (int) $pid,
					'projekt_id'    => (int) $pid,
					'projekt_title' => $source_title,
					'task_idx'      => (int) $idx,
					'task_uid'      => $uid,
					'artikel_id'    => (int) $artikel_id,
					'artikel_label' => $artikel_label,
					'artikel_nr'    => $artikel_nr,
					'artikel_title' => $artikel_title,
					'menge'         => (string) $dauer,
					'preis'         => (string) $preis,
					'info'          => $info,
					'datum'         => $datum,
					'zeit'          => $zeit,
				];

				if (\count($items) >= $limit) break 2;
			}
		}

		return $items;
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_beleg_task_refs_normalize_map')) {
	function cmx_beleg_task_refs_normalize_map(array $raw_map): array {
		$out = [];
		foreach ($raw_map as $pid_raw => $refs_raw) {
			$pid = (int) $pid_raw;
			if ($pid <= 0 || !\is_array($refs_raw)) continue;
			$uids = [];
			$idx  = [];
			foreach ((array) ($refs_raw['uids'] ?? []) as $uid_raw) {
				$uid = (string) \preg_replace('/[^A-Za-z0-9_-]/', '', (string) $uid_raw);
				if ($uid !== '') $uids[$uid] = true;
			}
			foreach ((array) ($refs_raw['idx'] ?? []) as $idx_raw) {
				if ($idx_raw === '' || $idx_raw === null || !\is_numeric((string) $idx_raw)) continue;
				$idx[(int) $idx_raw] = true;
			}
			if (!empty($uids) || !empty($idx)) {
				$out[$pid] = ['uids' => $uids, 'idx' => $idx];
			}
		}
		return $out;
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_beleg_task_refs_from_positionen')) {
	function cmx_beleg_task_refs_from_positionen(array $positionen, int $fallback_projekt_id = 0): array {
		$map = [];
		foreach ($positionen as $row) {
			if (!\is_array($row)) continue;
			$uid = (string) \preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($row['task_uid'] ?? ''));
			$idx_raw = isset($row['task_idx']) ? (string) $row['task_idx'] : '';
			$idx = ($idx_raw !== '' && \is_numeric($idx_raw)) ? (int) $idx_raw : null;
			if ($uid === '' && $idx === null) continue;

			$pid = isset($row['task_projekt_id']) ? (int) $row['task_projekt_id'] : 0;
			if ($pid <= 0 && $fallback_projekt_id > 0) {
				$pid = $fallback_projekt_id;
			}
			if ($pid <= 0) continue;

			if (!isset($map[$pid])) {
				$map[$pid] = ['uids' => [], 'idx' => []];
			}
			if ($uid !== '') {
				$map[$pid]['uids'][$uid] = true;
			}
			if ($idx !== null) {
				$map[$pid]['idx'][$idx] = true;
			}
		}
		return $map;
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_beleg_task_refs_map_for_meta')) {
	function cmx_beleg_task_refs_map_for_meta(array $map): array {
		$out = [];
		foreach ($map as $pid => $refs) {
			$pid = (int) $pid;
			if ($pid <= 0 || !\is_array($refs)) continue;
			$uids = \array_values(\array_keys((array) ($refs['uids'] ?? [])));
			$idx  = \array_values(\array_map('intval', \array_keys((array) ($refs['idx'] ?? []))));
			if (empty($uids) && empty($idx)) continue;
			$out[(string) $pid] = ['uids' => $uids, 'idx' => $idx];
		}
		return $out;
	}
}

/* ------------------------------
 * Locale-robust: String -> Float normalisieren (Punkt/Komma/tausender)
 * ------------------------------ */
if (!function_exists(__NAMESPACE__ . '\cmx_norm_decimal')) {
	function cmx_norm_decimal($val){
		$s = trim((string) $val);
		// Tausendertrennzeichen entfernen (inkl. NBSP)
		$s = str_replace(["\xc2\xa0", " ", "'"], '', $s);
		// Nur Ziffern und Dezimal/Sign-Zeichen behalten (entfernt z. B. typografische Apostrophe).
		$s = (string) preg_replace('/[^\d,\.\+\-]/u', '', $s);
		if ($s === '' || $s === '+' || $s === '-') {
			return '0';
		}
		$sign = '';
		if ($s[0] === '+' || $s[0] === '-') {
			$sign = $s[0];
			$s = (string) substr($s, 1);
		}
		$s = str_replace(['+', '-'], '', $s);
		if ($s === '') {
			return '0';
		}
		$hasComma = strpos($s, ',') !== false;
		$hasDot   = strpos($s, '.') !== false;

		if ($hasComma && $hasDot) {
			// Letztes Trennzeichen ist das Dezimaltrennzeichen.
			if (strrpos($s, ',') > strrpos($s, '.')) {
				$s = str_replace('.', '', $s);
				$s = str_replace(',', '.', $s);
			} else {
				$s = str_replace(',', '', $s);
			}
			return $sign . $s;
		}

		// Nur ein Trennzeichen-Typ vorhanden.
		if ($hasComma || $hasDot) {
			$sep = $hasComma ? ',' : '.';
			$parts = explode($sep, $s);
			$leftPart = $parts[0] ?? '';
			$leftDigits = ltrim($leftPart, '+-');

			// Mehrere gleiche Trennzeichen: als Tausendertrennzeichen interpretieren.
			if (count($parts) > 2) {
				$s = implode('', $parts);
			} elseif (count($parts) === 2) {
				$rightPart = $parts[1];
				$looksThousands = preg_match('/^\d{3}$/', $rightPart) && preg_match('/^\d{1,3}$/', $leftDigits);
				if ($looksThousands) {
					// Beispiel: 1.000 oder 12,345
					$s = $leftPart . $rightPart;
				} elseif ($sep === ',') {
					// Dezimalkomma -> Punkt
					$s = $leftPart . '.' . $rightPart;
				}
			} elseif ($sep === ',') {
				$s = str_replace(',', '.', $s);
			}
		}

		return $sign . $s;
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_beleg_artikel_usage_cache_key')) {
	function cmx_beleg_artikel_usage_cache_key(): string {
		return 'cmx_beleg_artikel_usage_counts_v1';
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_beleg_artikel_usage_counts_invalidate')) {
	function cmx_beleg_artikel_usage_counts_invalidate(): void {
		\delete_transient(cmx_beleg_artikel_usage_cache_key());
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_beleg_artikel_usage_counts')) {
	/**
	 * Anzahl Belege pro Artikel-ID (ein Artikel zählt pro Beleg höchstens einmal).
	 *
	 * @return array<int,int> artikel_id => beleg_count
	 */
	function cmx_beleg_artikel_usage_counts(): array {
		static $runtime_cache = null;
		if (\is_array($runtime_cache)) {
			return $runtime_cache;
		}

		$cached = \get_transient(cmx_beleg_artikel_usage_cache_key());
		if (\is_array($cached)) {
			$out = [];
			foreach ($cached as $artikel_id => $count) {
				$aid = (int) $artikel_id;
				$cnt = (int) $count;
				if ($aid > 0 && $cnt > 0) {
					$out[$aid] = $cnt;
				}
			}
			$runtime_cache = $out;
			return $runtime_cache;
		}

		$beleg_ids = \get_posts([
			'post_type'              => 'belege',
			'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => true,
		]);

		$counts = [];
		foreach ((array) $beleg_ids as $beleg_id_raw) {
			$beleg_id = (int) $beleg_id_raw;
			if ($beleg_id <= 0) {
				continue;
			}

			$rows = \get_post_meta($beleg_id, '_cmx_beleg_positionen', true);
			if (\is_string($rows) && $rows !== '') {
				$tmp = \json_decode($rows, true);
				if (\json_last_error() === \JSON_ERROR_NONE && \is_array($tmp)) {
					$rows = $tmp;
				} else {
					$tmp = @\maybe_unserialize($rows);
					$rows = \is_array($tmp) ? $tmp : [];
				}
			} elseif (!\is_array($rows)) {
				$rows = [];
			}

			if (empty($rows)) {
				continue;
			}

			$seen_in_beleg = [];
			foreach ($rows as $row) {
				if (!\is_array($row)) {
					continue;
				}
				$artikel_id = isset($row['artikel_id']) ? (int) $row['artikel_id'] : 0;
				if ($artikel_id > 0) {
					$seen_in_beleg[$artikel_id] = true;
				}
			}

			foreach (\array_keys($seen_in_beleg) as $artikel_id) {
				$aid = (int) $artikel_id;
				if ($aid <= 0) {
					continue;
				}
				$counts[$aid] = (int) ($counts[$aid] ?? 0) + 1;
			}
		}

		\set_transient(cmx_beleg_artikel_usage_cache_key(), $counts, 30 * \MINUTE_IN_SECONDS);
		$runtime_cache = $counts;
		return $runtime_cache;
	}
}

\add_action('trashed_post', function (int $post_id): void {
	if ((string) \get_post_type($post_id) !== 'belege') return;
	if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_artikel_usage_counts_invalidate')) {
		cmx_beleg_artikel_usage_counts_invalidate();
	}
});
\add_action('untrashed_post', function (int $post_id): void {
	if ((string) \get_post_type($post_id) !== 'belege') return;
	if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_artikel_usage_counts_invalidate')) {
		cmx_beleg_artikel_usage_counts_invalidate();
	}
});
\add_action('deleted_post', function (int $post_id): void {
	if ((string) \get_post_type($post_id) !== 'belege') return;
	if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_artikel_usage_counts_invalidate')) {
		cmx_beleg_artikel_usage_counts_invalidate();
	}
});

/* ------------------------------
 * Metabox registrieren
 * ------------------------------ */
add_action('add_meta_boxes', function() {
	add_meta_box(
		'cmx_beleg_positionen',
		'Positionen',
		__NAMESPACE__ . '\\cmx_render_beleg_positionen',
		'belege',
		'normal',
		'default'
	);
});

/* ------------------------------
 * Metabox Inhalt
 * ------------------------------ */
function cmx_render_beleg_positionen(\WP_Post $post) {
	wp_nonce_field('cmx_save_beleg_positionen', 'cmx_beleg_positionen_nonce');

	$positionen = get_post_meta($post->ID, '_cmx_beleg_positionen', true);
	if (is_string($positionen) && $positionen !== '') {
		$tmp = json_decode($positionen, true);
		$positionen = (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) ? $tmp : [];
	} elseif (!is_array($positionen)) {
		$positionen = [];
	}
	$artikel_list_url = \add_query_arg(
		[
			'post_type'      => 'artikel',
			'cmx_verkaufbar' => '1',
		],
		\admin_url('edit.php')
	);
	$einheiten_admin_url = '';
	if (\function_exists(__NAMESPACE__ . '\\cmx_artikel_einheiten_taxonomy')) {
		$einheiten_tax = (string) cmx_artikel_einheiten_taxonomy();
		if ($einheiten_tax !== '') {
			$einheiten_admin_url = \admin_url(
				'edit-tags.php?taxonomy=' . \rawurlencode($einheiten_tax) . '&post_type=artikel'
			);
		}
	}
	$einheiten_head = $einheiten_admin_url !== ''
		? '<a href="' . \esc_url($einheiten_admin_url) . '" target="_blank" rel="noopener noreferrer">Einheiten</a>'
		: 'Einheiten';
	echo '<div id="cmx-positionen-wrap">';
		echo '<table class="widefat striped" id="cmx-positionen-table">
				<thead><tr>
					<th><a href="' . esc_url($artikel_list_url) . '" target="_blank" rel="noopener noreferrer">Artikel</a></th>
					<th class="cmx-pos-qty-head"><span class="cmx-pos-qty-head-menge">&nbsp;Menge</span><span class="cmx-pos-qty-head-einheit">&nbsp;' . $einheiten_head . '</span></th>
					<th>&nbsp;&nbsp;Einzelpreis</th>
					<th>&nbsp;&nbsp;Rabatt</th>
					<th style="text-align:right;">Gesamt</th>
					<th>zus&auml;tzliche Notiz</th>
				<th></th>
			</tr></thead>
			<tbody>';

	$has_article_rows = false;
	if (!empty($positionen)) {
		foreach ($positionen as $i => $pos) {
			$custom_rendered = (bool) \apply_filters('cmx_beleg_positionen_render_custom_row', false, (int) $i, $pos);
			if ($custom_rendered) {
				continue;
			}
			$has_article_rows = true;
			cmx_render_position_row($i, $pos);
		}
	}
	if (empty($positionen) || !$has_article_rows) {
		$next_i = !empty($positionen) ? \count($positionen) : 0;
		cmx_render_position_row($next_i, []);
	}

	echo '</tbody></table>';
	echo '<div class="cmx-pos-actions"><button type="button" class="button button-secondary" id="cmx-add-pos">+ Position hinzufügen</button>';
	\do_action('cmx_beleg_positionen_after_add_button');
	echo '</div>';
	echo '</div>';

	cmx_beleg_positionen_js();
}

add_action('cmx_beleg_positionen_after_add_button', function(): void {
	echo ' <div class="cmx-task-picker-wrap" id="cmx-task-picker-wrap">';
	echo '<button type="button" class="button button-secondary" id="cmx-add-task">Task hinzufügen</button>';
	echo '<div class="cmx-task-picker-panel" id="cmx-task-picker-panel" style="display:none;">';
	echo '<input type="text" id="cmx-task-picker-search" class="regular-text" placeholder="Task suchen..." autocomplete="off">';
	echo '<ul id="cmx-task-picker-list" class="cmx-task-picker-list"></ul>';
	echo '</div></div>';
}, 20);

/* ------------------------------
 * Einzelzeile rendern (Autocomplete für Artikel)
 * ------------------------------ */
function cmx_render_position_row($i, $pos) {
	$artikel_id   = isset($pos['artikel_id']) ? (int)$pos['artikel_id'] : 0;
	$title        = $artikel_id ? get_the_title($artikel_id) : '';
	$title        = \function_exists(__NAMESPACE__ . '\\cmx_beleg_decode_label_text')
		? cmx_beleg_decode_label_text((string) $title)
		: (string) $title;
	$nr           = $artikel_id ? cmx_get_artikel_nr($artikel_id) : '';
	$display_name = $title ?: (\function_exists(__NAMESPACE__ . '\\cmx_beleg_decode_label_text')
		? cmx_beleg_decode_label_text((string) ($pos['artikel_name'] ?? ''))
		: (string) ($pos['artikel_name'] ?? ''));
	$display      = esc_html( ($nr ? $nr.' – ' : '') . $display_name );
	$textbaustein_edit_url = \function_exists(__NAMESPACE__ . '\\cmx_beleg_textbaustein_admin_url')
		? (string) cmx_beleg_textbaustein_admin_url()
		: '';

	$menge        = (string)($pos['menge'] ?? '');
	$preis        = (string)($pos['preis'] ?? '');
	$unit_data    = \function_exists(__NAMESPACE__ . '\\cmx_beleg_resolve_position_unit')
		? cmx_beleg_resolve_position_unit((array) $pos, $artikel_id)
		: ['einheit_id' => 0, 'unit' => ''];
	$einheit_id   = (int) ($unit_data['einheit_id'] ?? 0);
	$einheiten    = \function_exists(__NAMESPACE__ . '\\cmx_artikel_einheiten_options')
		? cmx_artikel_einheiten_options()
		: [];
	$beschreibung = esc_textarea($pos['beschreibung'] ?? '');
	$rabatt_raw   = trim((string)($pos['rabatt'] ?? ''));
	$task_idx_raw = isset($pos['task_idx']) ? (string) $pos['task_idx'] : '';
	$task_idx     = ($task_idx_raw !== '' && is_numeric($task_idx_raw)) ? (int) $task_idx_raw : null;
	$task_uid_raw = isset($pos['task_uid']) ? (string) $pos['task_uid'] : '';
	$task_uid     = (string) \preg_replace('/[^A-Za-z0-9_-]/', '', $task_uid_raw);
	$task_projekt_id_raw = isset($pos['task_projekt_id']) ? (string) $pos['task_projekt_id'] : '';
	$task_projekt_id     = ($task_projekt_id_raw !== '' && is_numeric($task_projekt_id_raw)) ? (int) $task_projekt_id_raw : null;

	$menge_display = $menge !== '' ? cmx_format_swiss_number(cmx_norm_decimal($menge), 2) : '';
	$preis_display = $preis !== '' ? cmx_format_swiss_number(cmx_norm_decimal($preis), 2) : '';
	$rabatt_display = $rabatt_raw;
	if ($rabatt_raw !== '') {
		$is_percent = str_ends_with($rabatt_raw, '%');
		$raw = $is_percent ? substr($rabatt_raw, 0, -1) : $rabatt_raw;
		$raw = trim((string) preg_replace('/\s*(chf|fr\.?)\s*/i', '', $raw));
		if ($raw !== '' && is_numeric(cmx_norm_decimal($raw))) {
			$rabatt_display = cmx_format_swiss_number(cmx_norm_decimal($raw), 2) . ($is_percent ? '%' : '');
		}
	}
	$rabatt = esc_attr($rabatt_display);

	echo '<tr class="cmx-pos-row">';

	echo '<td style="min-width:320px">';
	$edit_link = $artikel_id ? get_edit_post_link($artikel_id, '') : '';
	echo '<a href="'.esc_url($edit_link).'" class="cmx-artikel-edit" data-cmx-help-key="beleg_artikel_edit" aria-label="Artikel bearbeiten" title="Artikel im neuen Tab bearbeiten" target="_blank" rel="noopener noreferrer" style="'.($edit_link ? '' : 'pointer-events:none; opacity:0.35;').'">✎</a>';
	echo '<input type="hidden" name="cmx_positionen['.$i.'][artikel_id]" class="cmx-artikel-id" value="'.esc_attr($artikel_id).'">';
	echo '<input type="hidden" name="cmx_positionen['.$i.'][task_idx]" class="cmx-task-idx" value="'.($task_idx === null ? '' : esc_attr((string) $task_idx)).'">';
	echo '<input type="hidden" name="cmx_positionen['.$i.'][task_uid]" class="cmx-task-uid" value="'.esc_attr($task_uid).'">';
	echo '<input type="hidden" name="cmx_positionen['.$i.'][task_projekt_id]" class="cmx-task-projekt-id" value="'.($task_projekt_id === null ? '' : esc_attr((string) $task_projekt_id)).'">';
	echo '<input type="text" class="regular-text cmx-artikel-autocomplete" data-cmx-help-key="beleg_artikel_suche" placeholder="Artikel suchen …" title="Artikel suchen" value="'.esc_attr($display).'" autocomplete="off" style="width:100%">';
	echo '</td>';

	// negative Mengen zulassen (Komma/Punkt erlaubt) + Einheit
	echo '<td class="cmx-pos-qty-cell">';
	echo '<input type="text" name="cmx_positionen['.$i.'][menge]" value="'.esc_attr($menge_display).'" style="width:90px">';
	echo '<select name="cmx_positionen['.$i.'][einheit_id]" class="cmx-einheit-select" style="width:120px; margin-left:6px;">';
	echo '<option value="">— auswählen —</option>';
	foreach ($einheiten as $opt) {
		$opt_id = (int) ($opt['id'] ?? 0);
		if ($opt_id <= 0) continue;
		$opt_name = (string) ($opt['name'] ?? '');
		echo '<option value="' . $opt_id . '" ' . selected($einheit_id, $opt_id, false) . '>' . esc_html($opt_name) . '</option>';
	}
	echo '</select>';
	echo '</td>';

	// Preis als Text (Komma/Punkt erlaubt)
	echo '<td><input type="text" name="cmx_positionen['.$i.'][preis]" value="'.esc_attr($preis_display).'" style="width:88px"></td>';

	echo '<td class="cmx-pos-rabatt-td" style="width:88px;">';
	echo '<input type="text" name="cmx_positionen['.$i.'][rabatt]" value="'.$rabatt.'" placeholder="" style="width:88px">';
	echo '</td>';

	// Initial total (robust normalisiert)
	$menge_f = (float)\CLOUDMEISTER\CMX\Buero\cmx_norm_decimal($menge);
	$preis_f = (float)\CLOUDMEISTER\CMX\Buero\cmx_norm_decimal($preis);
	$total_init = $menge_f * $preis_f;

	echo '<td class="cmx-pos-total" style="width:78px;text-align:right;">'.esc_html(cmx_format_swiss_number($total_init, 2)).'</td>';

	echo '<td class="cmx-pos-beschr-cell">';
	if ($textbaustein_edit_url !== '') {
		// echo '<a href="'.esc_url($textbaustein_edit_url).'" class="cmx-textbaustein-edit" aria-label="Textbausteine bearbeiten" title="Textbausteine im neuen Tab bearbeiten" target="_blank" rel="noopener noreferrer">✎</a>';
echo '<a href="'.esc_url($textbaustein_edit_url).'"
class="cmx-textbaustein-edit"
aria-label="Textbausteine bearbeiten"
title="Textbausteine im neuen Tab bearbeiten"
target="_blank"
rel="noopener noreferrer">
<span style="display:inline-block; transform:translateY(5px);">✎</span>
</a>';

	}
	echo '<textarea name="cmx_positionen['.$i.'][beschreibung]" rows="1" style="width:100%;height:38px;min-height:38px;resize:none;overflow-y:hidden;line-height:1.4;">'.$beschreibung.'</textarea>';
	echo '</td>';
	echo '<td class="cmx-pos-controls">';
	echo '<span class="cmx-pos-drag-handle" title="Zeile verschieben" aria-label="Zeile verschieben">↕</span>';
	echo '<button type="button" class="button-link-delete cmx-del-pos"><span class="dashicons dashicons-trash" style=""></span></button>';
	echo '</td>';

	echo '</tr>';
}

/* ------------------------------
 * Speichern der Positionen (gehärtet)
 * ------------------------------ */
add_action('save_post_belege', function($post_id, \WP_Post $post, $update) {

	if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
	if ($post->post_type !== 'belege') return;
	if (!current_user_can('edit_post', $post_id)) return;
	if (!isset($_POST['cmx_beleg_positionen_nonce']) || !wp_verify_nonce($_POST['cmx_beleg_positionen_nonce'], 'cmx_save_beleg_positionen')) return;
	if (defined('DOING_AJAX') && DOING_AJAX) return;

	$positionen = $_POST['cmx_positionen'] ?? [];
	if (!is_array($positionen)) return;

	$max_rows = 500;
	if (count($positionen) > $max_rows) $positionen = array_slice($positionen, 0, $max_rows);

	$clean = [];
	foreach ($positionen as $row) {
		if (!is_array($row)) continue;
		$custom = \apply_filters('cmx_beleg_positionen_clean_custom_row', null, $row, (int) $post_id);
		if (\is_array($custom)) {
			$clean[] = $custom;
			continue;
		}
		if ($custom === false) {
			continue;
		}

		$artikel_id   = isset($row['artikel_id']) ? (int)$row['artikel_id'] : 0;

		$menge_raw    = isset($row['menge']) ? (string)$row['menge'] : '';
		$menge        = (float)\CLOUDMEISTER\CMX\Buero\cmx_norm_decimal($menge_raw);
		$menge        = (float) \round($menge, 2);

		$preis_raw    = isset($row['preis']) ? (string)$row['preis'] : '';
		$preis        = (float)\CLOUDMEISTER\CMX\Buero\cmx_norm_decimal($preis_raw);
		$preis        = (float) \round($preis, 2);

		$rabatt_raw   = isset($row['rabatt']) ? (string)$row['rabatt'] : '';
		$rabatt       = sanitize_text_field($rabatt_raw);

			$beschreibung_raw = isset($row['beschreibung']) ? (string)$row['beschreibung'] : '';
			$beschreibung_raw = wp_unslash($beschreibung_raw);
			$beschreibung_raw = str_replace(["\r\n", "\r"], "\n", $beschreibung_raw);
			$beschreibung = trim($beschreibung_raw);
			$task_idx_raw = isset($row['task_idx']) ? trim((string)$row['task_idx']) : '';
			$task_idx     = ($task_idx_raw !== '' && is_numeric($task_idx_raw)) ? (int)$task_idx_raw : null;
			$task_uid_raw = isset($row['task_uid']) ? (string)$row['task_uid'] : '';
			$task_uid     = (string)\preg_replace('/[^A-Za-z0-9_-]/', '', $task_uid_raw);
			$task_projekt_id_raw = isset($row['task_projekt_id']) ? trim((string)$row['task_projekt_id']) : '';
			$task_projekt_id     = ($task_projekt_id_raw !== '' && is_numeric($task_projekt_id_raw)) ? (int)$task_projekt_id_raw : null;
			$unit_data = \function_exists(__NAMESPACE__ . '\\cmx_beleg_resolve_position_unit')
				? cmx_beleg_resolve_position_unit($row, $artikel_id)
				: ['einheit_id' => 0, 'unit' => ''];
			$einheit_id = (int) ($unit_data['einheit_id'] ?? 0);
			$unit       = \sanitize_text_field((string) ($unit_data['unit'] ?? ''));

			// Wenn Artikel gewählt ist, leere Menge als 1 übernehmen.
			if ($artikel_id > 0 && \trim($menge_raw) === '' && $menge == 0.0) {
				$menge = 1.0;
			}

			// negative Mengen zulassen; nur 0 verwerfen
			if ($artikel_id <= 0 || $menge == 0.0) continue;
			if (strlen($beschreibung) > 10000) $beschreibung = substr($beschreibung, 0, 10000);

			$clean[] = [
				'artikel_id'   => $artikel_id,
				'menge'        => $menge,
				'einheit_id'   => $einheit_id,
				'unit'         => $unit,
				'preis'        => $preis,
				'rabatt'       => $rabatt,
				'beschreibung' => $beschreibung,
				'task_idx'     => $task_idx,
				'task_uid'     => $task_uid,
				'task_projekt_id' => $task_projekt_id,
			];
		}

	// Altdaten angleichen (können als JSON-String oder Array vorliegen)
	$old_raw  = get_post_meta($post_id, '_cmx_beleg_positionen', true);
	if (is_string($old_raw) && $old_raw !== '') {
		$tmp = json_decode($old_raw, true);
		if (json_last_error() === JSON_ERROR_NONE) {
			$old_data = $tmp;
		} else {
			$old_data = @maybe_unserialize($old_raw);
			if (!is_array($old_data)) $old_data = [];
		}
	} elseif (is_array($old_raw)) {
		$old_data = $old_raw;
	} else {
		$old_data = [];
	}

	if ($old_data !== $clean) {
		update_post_meta($post_id, '_cmx_beleg_positionen', $clean);
		if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_artikel_usage_counts_invalidate')) {
			cmx_beleg_artikel_usage_counts_invalidate();
		}
	}

}, 10, 3);

add_action('save_post_belege', function($post_id, \WP_Post $post, $update) {
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) return;
	if ($post->post_type !== 'belege') return;
	if (!\current_user_can('edit_post', $post_id)) return;

	$positionen = \function_exists(__NAMESPACE__ . '\\cmx_beleg_meta_array')
		? cmx_beleg_meta_array((int) $post_id, '_cmx_beleg_positionen')
		: [];

	$fallback_pid = (int) \get_post_meta($post_id, '_cmx_beleg_projekt_id', true);
	if ($fallback_pid <= 0 && \function_exists(__NAMESPACE__ . '\\cmx_meta_projekt_ids')) {
		foreach ((array) cmx_meta_projekt_ids() as $mk) {
			$fallback_pid = (int) \get_post_meta($post_id, (string) $mk, true);
			if ($fallback_pid > 0) break;
		}
	}

	$current_map = \function_exists(__NAMESPACE__ . '\\cmx_beleg_task_refs_from_positionen')
		? cmx_beleg_task_refs_from_positionen((array) $positionen, $fallback_pid)
		: [];
	$prev_raw_map = \function_exists(__NAMESPACE__ . '\\cmx_beleg_meta_array')
		? cmx_beleg_meta_array((int) $post_id, '_cmx_beleg_task_refs_by_project')
		: [];
	$prev_map = \function_exists(__NAMESPACE__ . '\\cmx_beleg_task_refs_normalize_map')
		? cmx_beleg_task_refs_normalize_map((array) $prev_raw_map)
		: [];

	if (empty($current_map) && empty($prev_map)) {
		\delete_post_meta($post_id, '_cmx_beleg_task_refs_by_project');
		return;
	}

	$all_pids = [];
	foreach (\array_keys($current_map) as $pid) $all_pids[(int) $pid] = true;
	foreach (\array_keys($prev_map) as $pid) $all_pids[(int) $pid] = true;

	foreach (\array_keys($all_pids) as $pid) {
		$pid = (int) $pid;
		if ($pid <= 0) continue;

		$tasks = \function_exists(__NAMESPACE__ . '\\cmx_beleg_meta_array')
			? cmx_beleg_meta_array($pid, '_cmx_projekt_tasks')
			: [];
		if (empty($tasks)) continue;

		$current_refs = isset($current_map[$pid]) && \is_array($current_map[$pid]) ? $current_map[$pid] : ['uids' => [], 'idx' => []];
		$prev_refs = isset($prev_map[$pid]) && \is_array($prev_map[$pid]) ? $prev_map[$pid] : ['uids' => [], 'idx' => []];
		$current_uids = \is_array($current_refs['uids'] ?? null) ? (array) $current_refs['uids'] : [];
		$current_idx  = \is_array($current_refs['idx'] ?? null) ? (array) $current_refs['idx'] : [];
		$prev_uids    = \is_array($prev_refs['uids'] ?? null) ? (array) $prev_refs['uids'] : [];
		$prev_idx     = \is_array($prev_refs['idx'] ?? null) ? (array) $prev_refs['idx'] : [];

		$changed = false;
		foreach ($tasks as $idx => &$task_row) {
			if (!\is_array($task_row)) continue;

			$task_uid = (string) \preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($task_row['uid'] ?? ''));
			$is_now = ($task_uid !== '' && isset($current_uids[$task_uid])) || isset($current_idx[(int) $idx]);
			$was_before = ($task_uid !== '' && isset($prev_uids[$task_uid])) || isset($prev_idx[(int) $idx]);
			if (!$is_now && !$was_before) continue;

			$target = $is_now ? 1 : 0;
			$current_flag = \function_exists(__NAMESPACE__ . '\\cmx_beleg_truthy') && cmx_beleg_truthy($task_row['abgerechnet'] ?? 0) ? 1 : 0;
			if ($current_flag !== $target) {
				$task_row['abgerechnet'] = $target;
				$changed = true;
			}
		}
		unset($task_row);

		if ($changed) {
			\update_post_meta($pid, '_cmx_projekt_tasks', $tasks);
		}
	}

	$store_map = \function_exists(__NAMESPACE__ . '\\cmx_beleg_task_refs_map_for_meta')
		? cmx_beleg_task_refs_map_for_meta($current_map)
		: [];
	if (empty($store_map)) {
		\delete_post_meta($post_id, '_cmx_beleg_task_refs_by_project');
	} else {
		\update_post_meta($post_id, '_cmx_beleg_task_refs_by_project', $store_map);
	}
}, 90, 3);

/* ------------------------------
 * AJAX: VK-Preis aus Artikel (_cmx_artikel_vk)
 * ------------------------------ */
add_action('wp_ajax_cmx_get_artikel_vk', function() {
	if (!current_user_can('edit_posts')) wp_send_json_error(['msg' => 'forbidden'], 403);
	$artikel_id = isset($_POST['artikel_id']) ? (int) $_POST['artikel_id'] : 0;
	if ($artikel_id <= 0) wp_send_json_error(['msg' => 'no_id'], 400);
	$vk = get_post_meta($artikel_id, '_cmx_artikel_vk', true);
	$default_unit = \function_exists(__NAMESPACE__ . '\\cmx_artikel_default_einheit')
		? cmx_artikel_default_einheit($artikel_id)
		: ['id' => 0, 'name' => ''];
	wp_send_json_success([
		'vk'      => ($vk === '' || $vk === null) ? '' : (string) $vk,
		'unit_id' => (int) ($default_unit['id'] ?? 0),
		'unit'    => (string) ($default_unit['name'] ?? ''),
	]);
});

/* ------------------------------
 * AJAX: Artikel-Suche
 * ------------------------------ */
add_action('wp_ajax_cmx_search_artikel', function() {
	if (!current_user_can('edit_posts')) wp_send_json_error(['msg' => 'forbidden'], 403);

	global $wpdb;

	$term  = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';
	$limit = 20;

	$post_type = 'artikel';
	$post_tbl  = $wpdb->posts;
	$meta_tbl  = $wpdb->postmeta;

	$ids = [];

	if ($term === '') {
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$post_tbl}
				 WHERE post_type=%s AND post_status<>'trash'
				 ORDER BY post_title ASC
				 LIMIT %d",
				$post_type, $limit
			)
		);
	} else {
		$like = '%' . $wpdb->esc_like($term) . '%';
		$norm      = preg_replace('/[\s\.\-_:]/', '', $term);
		$norm_like = '%' . $wpdb->esc_like($norm) . '%';

		$title_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$post_tbl}
				 WHERE post_type=%s AND post_status<>'trash'
				   AND post_title LIKE %s
				 ORDER BY post_title ASC
				 LIMIT %d",
				$post_type, $like, $limit
			)
		);

		$meta_keys = ['cmx_artikel_sku', '_cmx_artikel_sku', '_cmx_artikel_nr', '_sku'];
		$in_keys   = implode(',', array_fill(0, count($meta_keys), '%s'));

		$meta_sql  = $wpdb->prepare(
			"SELECT p.ID
			   FROM {$post_tbl} p
			   JOIN {$meta_tbl} m ON m.post_id = p.ID
			  WHERE p.post_type=%s
			    AND p.post_status<>'trash'
			    AND m.meta_key IN ($in_keys)
			    AND (
					m.meta_value LIKE %s
					OR REPLACE(REPLACE(REPLACE(REPLACE(m.meta_value,' ',''),'-',''),'.',''),':','') LIKE %s
				)
			  GROUP BY p.ID
			  ORDER BY MAX(p.post_title) ASC
			  LIMIT %d",
			array_merge([$post_type], $meta_keys, [$like, $norm_like, $limit])
		);
		$meta_ids = $wpdb->get_col($meta_sql);

		$ids = array_slice(array_values(array_unique(array_merge($title_ids, $meta_ids))), 0, $limit);
	}

	$items = [];
	foreach ($ids as $id) {
		$title = \function_exists(__NAMESPACE__ . '\\cmx_beleg_decode_label_text')
			? cmx_beleg_decode_label_text((string) get_the_title($id))
			: (string) get_the_title($id);
		$nr    = cmx_get_artikel_nr($id);

		$items[] = [
			'label' => trim(($nr !== '' ? $nr . ' – ' : '') . $title),
			'value' => (int) $id,
			'nr'    => $nr,
			'title' => $title,
		];
	}

	$priority_threshold = (int) \apply_filters('cmx_beleg_artikel_priority_threshold', 3);
	if ($priority_threshold < 1) {
		$priority_threshold = 1;
	}
	$usage_counts = \function_exists(__NAMESPACE__ . '\\cmx_beleg_artikel_usage_counts')
		? cmx_beleg_artikel_usage_counts()
		: [];
	if (!empty($usage_counts) && !empty($items)) {
		\usort($items, static function(array $a, array $b) use ($usage_counts, $priority_threshold): int {
			$a_id = (int) ($a['value'] ?? 0);
			$b_id = (int) ($b['value'] ?? 0);
			$a_hot = ((int) ($usage_counts[$a_id] ?? 0) >= $priority_threshold) ? 1 : 0;
			$b_hot = ((int) ($usage_counts[$b_id] ?? 0) >= $priority_threshold) ? 1 : 0;
			if ($a_hot !== $b_hot) {
				return $b_hot <=> $a_hot;
			}
			$a_title = (string) ($a['title'] ?? '');
			$b_title = (string) ($b['title'] ?? '');
			$cmp = \strnatcasecmp($a_title, $b_title);
			if ($cmp !== 0) {
				return $cmp;
			}
			return $a_id <=> $b_id;
		});
	}

	wp_send_json($items);
});

/* ------------------------------
 * AJAX: Belege-Textbausteine-Suche (Taxonomie)
 * ------------------------------ */
add_action('wp_ajax_cmx_search_beleg_textbausteine', function() {
	if (!current_user_can('edit_posts')) wp_send_json_error(['msg' => 'forbidden'], 403);

	$term = isset($_GET['term']) ? \sanitize_text_field(\wp_unslash($_GET['term'])) : '';
	$items = \function_exists(__NAMESPACE__ . '\\cmx_beleg_textbaustein_items')
		? cmx_beleg_textbaustein_items($term, 30)
		: [];
	wp_send_json($items);
});

add_action('wp_ajax_cmx_search_beleg_tasks', function() {
	if (!\current_user_can('edit_posts')) \wp_send_json_error(['msg' => 'forbidden'], 403);

	$term = isset($_GET['term']) ? \sanitize_text_field(\wp_unslash($_GET['term'])) : '';
	$source_type = isset($_GET['source_type']) ? \sanitize_key((string) $_GET['source_type']) : 'projekte';
	$source_id = isset($_GET['source_id']) ? (int) $_GET['source_id'] : 0;
	$projekt_id = isset($_GET['projekt_id']) ? (int) $_GET['projekt_id'] : 0; // Legacy/Fallback
	if ($source_id <= 0) {
		$source_id = $projekt_id;
	}
	$items = \function_exists(__NAMESPACE__ . '\\cmx_beleg_open_task_items')
		? cmx_beleg_open_task_items($source_id, $term, 150, $source_type)
		: [];
	\wp_send_json($items);
});

/* ------------------------------
 * JS – Suggest + Berechnung (inkl. negative Beträge & Gesamtsumme)
 * ------------------------------ */
function cmx_beleg_positionen_js() {
	$ajax_url = admin_url('admin-ajax.php');
	?>
	<script>
	jQuery(function($){
		const table   = $('#cmx-positionen-table tbody');
		const AJAX_URL = <?php echo wp_json_encode($ajax_url); ?>;
		const ARTICLE_EDIT_BASE = <?php echo wp_json_encode(admin_url('post.php?post=')); ?>;
		const INITIAL_ARTICLE_ROW_HTML = (function(){
			const $tpl = table.find('tr.cmx-pos-row:not(.cmx-pos-row-abschnitt):first').first();
			return $tpl.length ? $('<div>').append($tpl.clone()).html() : '';
		})();
		const $taskPickerWrap = $('#cmx-task-picker-wrap');
		const $taskPickerPanel = $('#cmx-task-picker-panel');
		const $taskPickerSearch = $('#cmx-task-picker-search');
		const $taskPickerList = $('#cmx-task-picker-list');
		let taskPickerItems = [];
		let taskPickerActive = -1;
		let taskPickerTimer = null;
		let taskPickerSeq = 0;
		let dragMode = 'row';

		// ROBUSTER Parser: akzeptiert u. a. 1'234.56, 1'234,56, 1.234,56, 1,234.56, 1.000
		function parseNumberFlexible(val){
			if(typeof val!=='string') val=(val??'').toString();
			let s = val.replace(/\s+/g,'').replace(/'/g,'');
			// Nur numerische Zeichen/Separatoren behalten (entfernt z. B. typografische Apostrophe).
			s = s.replace(/[^\d,.\-+]/g, '');
			if(!s || s==='+' || s==='-') return 0;
			let sign = 1;
			if(s.charAt(0)==='-' || s.charAt(0)==='+'){
				sign = s.charAt(0)==='-' ? -1 : 1;
				s = s.slice(1);
			}
			s = s.replace(/[+-]/g, '');
			if(!s) return 0;
			const hasComma = s.indexOf(',')>-1, hasDot = s.indexOf('.')>-1;
			if(hasComma && hasDot){
				if(s.lastIndexOf(',') > s.lastIndexOf('.')){
					// Komma ist Dezimal → Punkte sind Tausender
					s = s.replace(/\./g,'').replace(/,/g,'.');
				}else{
					// Punkt ist Dezimal → Kommas sind Tausender
					s = s.replace(/,/g,'');
				}
			}else if(hasComma || hasDot){
				const sep = hasComma ? ',' : '.';
				const parts = s.split(sep);
				const left = parts[0] || '';
				const leftDigits = left.replace(/^[+-]/, '');
				if(parts.length > 2){
					// z. B. 1.234.567 oder 1,234,567
					s = parts.join('');
				}else if(parts.length === 2){
					const right = parts[1] || '';
					const looksThousands = /^\d{3}$/.test(right) && /^\d{1,3}$/.test(leftDigits);
					if(looksThousands){
						// z. B. 1.000 oder 12,345
						s = left + right;
					}else if(sep === ','){
						s = left + '.' + right;
					}
				}else if(sep === ','){
					s = s.replace(/,/g,'.');
				}
			}
			const n = parseFloat(s);
			return isNaN(n)?0:(sign*n);
		}

		function parseRabattOnSubtotal(subtotal, rabattRaw){
			if(!rabattRaw) return 0;
			const base = Math.abs(subtotal); // Rabattgrundlage = Betrag
			const txt=(rabattRaw+'').trim().toLowerCase();
			if(txt.endsWith('%')){
				const pct=parseNumberFlexible(txt.replace('%',''));
				return pct>0 ? base*(pct/100) : 0;
			}
			const betrag=parseNumberFlexible(txt.replace(/chf|fr\.?/g,''));
			return betrag>0?betrag:0;
		}

		function roundTo5Rp(amount){ return Math.round((amount + Number.EPSILON) * 20) / 20; }
		function formatSwiss(n){
			const parts = (Number(n) || 0).toFixed(2).split('.');
			let left = parts[0];
			let out = '';
			while (left.length > 3) {
				out = "'" + left.slice(-3) + out;
				left = left.slice(0, -3);
			}
			return left + out + '.' + parts[1];
		}
		function formatRabattValue(raw){
			let txt = (raw ?? '').toString().trim();
			if (!txt) return '';
			const isPercent = txt.endsWith('%');
			if (isPercent) txt = txt.slice(0, -1);
			txt = txt.replace(/chf|fr\.?/gi, '').trim();
			if (txt === '') return '';
			return formatSwiss(parseNumberFlexible(txt)) + (isPercent ? '%' : '');
		}
			function escHtml(s){
				return (s ?? '').toString()
					.replace(/&/g,'&amp;')
					.replace(/</g,'&lt;')
					.replace(/>/g,'&gt;');
			}
			function setRowUnitSelection($row, unitIdRaw){
				const $unit = $row.find('select[name*="[einheit_id]"]').first();
				if(!$unit.length) return;
				const unitId = parseInt((unitIdRaw ?? '').toString(), 10);
				if(!isNaN(unitId) && unitId > 0 && $unit.find('option[value="' + unitId + '"]').length){
					$unit.val(String(unitId));
				}else{
					$unit.val('');
				}
			}
			function reindexPositionRows(){
				const fieldSelector = 'input[name^="cmx_positionen["], textarea[name^="cmx_positionen["], select[name^="cmx_positionen["]';
				table.children('tr').each(function(rowIndex){
					$(this).find(fieldSelector).each(function(){
						const $el = $(this);
						const oldName = ($el.attr('name') || '').toString();
						if (!oldName) return;
						const newName = oldName.replace(/^cmx_positionen\[\d+\]/, 'cmx_positionen[' + rowIndex + ']');
						if (newName !== oldName) {
							$el.attr('name', newName);
						}
					});
				});
			}
				function nextRowIndex(){
					let max = -1;
					table.find('input[name^="cmx_positionen["], textarea[name^="cmx_positionen["], select[name^="cmx_positionen["]').each(function(){
						const m = ((this.name || '') + '').match(/^cmx_positionen\[(\d+)\]/);
					if (!m) return;
					const idx = parseInt(m[1], 10);
				if (!isNaN(idx) && idx > max) max = idx;
				});
				return max + 1;
			}

			function initSortable(){
				if (!$.fn.sortable || !table.length) return;
				if (table.data('ui-sortable')) {
					table.sortable('destroy');
				}
				table.sortable({
					items: '> tr.cmx-pos-row',
					handle: '.cmx-pos-drag-handle, .cmx-section-drag-handle',
					cancel: 'input, textarea, a, button:not(.cmx-section-drag-handle)',
					placeholder: 'cmx-pos-sort-placeholder',
					forcePlaceholderSize: true,
					tolerance: 'pointer',
					helper: function(e, tr){
						const $originals = tr.children();
						const $helper = tr.clone();
						$helper.children().each(function(index){
							$(this).width($originals.eq(index).outerWidth());
						});
						return $helper;
					},
					start: function(e, ui){
						const colCount = $('#cmx-positionen-table thead th').length || 7;
						ui.placeholder
							.empty()
							.append('<td class="cmx-pos-sort-placeholder-cell" colspan="' + colCount + '"></td>');

						const moveWholeSection = dragMode === 'section' && ui.item.hasClass('cmx-pos-row-abschnitt');
						ui.item.data('cmx-move-whole-section', moveWholeSection ? 1 : 0);
						if (moveWholeSection) {
							const $followers = ui.item.nextUntil('tr.cmx-pos-row-abschnitt', 'tr.cmx-pos-row');
							const followersCount = $followers.length;
							ui.item.data('cmx-section-followers', $followers.detach());
							table.addClass('cmx-section-drag-active');
							ui.helper.addClass('cmx-sorting-section');
							ui.placeholder.addClass('cmx-pos-sort-placeholder-section');
							ui.placeholder.find('.cmx-pos-sort-placeholder-cell').html(
								'<span class="cmx-pos-sort-placeholder-label">Abschnitt verschieben' +
								(followersCount > 0 ? ' (' + (followersCount + 1) + ' Zeilen)' : '') +
								'</span>'
							);
							ui.placeholder.height(ui.helper.outerHeight());
							return;
						}
						ui.placeholder.removeClass('cmx-pos-sort-placeholder-section');
						ui.placeholder.find('.cmx-pos-sort-placeholder-cell')
							.html('<span class="cmx-pos-sort-placeholder-label">Position verschieben</span>');
						ui.placeholder.height(ui.helper.outerHeight());
					},
					sort: function(e, ui){
						if (!ui.item.data('cmx-move-whole-section')) return;
						ui.placeholder.height(ui.helper.outerHeight());
					},
					stop: function(e, ui){
						if (ui.item.data('cmx-move-whole-section')) {
							const $followers = ui.item.data('cmx-section-followers');
							if ($followers && $followers.length) {
								ui.item.after($followers);
							}
						}
						table.removeClass('cmx-section-drag-active');
						ui.item.removeClass('cmx-sorting-section');
						ui.item.removeData('cmx-move-whole-section');
						ui.item.removeData('cmx-section-followers');
						table.children('tr.ui-sortable-placeholder, tr.cmx-pos-sort-placeholder').remove();
						dragMode = 'row';
						table.trigger('cmx_positionen_rows_changed');
					}
				});
			}

			function refreshSortable(){
				if ($.fn.sortable && table.data('ui-sortable')) {
					table.sortable('refresh');
				}
			}

			table.on('mousedown touchstart', '.cmx-pos-drag-handle', function(){
				dragMode = 'row';
			});
			table.on('mousedown touchstart', '.cmx-section-drag-handle', function(){
				dragMode = 'section';
			});
			table.on('pointerdown', '.cmx-pos-drag-handle, .cmx-section-drag-handle', function(e){
				// Global help modal listens on document pointerdown (long-press).
				// Ignore drag handles so no help popup appears during sorting.
				e.stopPropagation();
			});

		function recalcRowTotal($row){
			const menge=parseNumberFlexible($row.find('input[name*="[menge]"]').val());
			const preis=parseNumberFlexible($row.find('input[name*="[preis]"]').val());
			const rabattRaw=$row.find('input[name*="[rabatt]"]').val();

			let subtotal=menge*preis;
			let rabatt=parseRabattOnSubtotal(subtotal, rabattRaw);

			const cap = Math.abs(subtotal);
			if(rabatt>cap) rabatt=cap;

			const signedRabatt = Math.sign(subtotal) * rabatt;
			const total = roundTo5Rp(subtotal - signedRabatt);

			$row.find('.cmx-pos-total').text(formatSwiss(total));
			return total;
		}

		function recalcAll(){
			let sum=0;
			table.find('tr').each(function(){ sum += recalcRowTotal($(this)); });
			// Optional: Boxen im UI aktualisieren
			$('#cmx-gesamtsumme, .cmx-gesamtbox .sum').text(formatSwiss(sum));
			$(document).trigger('cmx_total_updated', [sum]);
		}

		/* ========= Suggest (unverändert) ========= */
			function makeNavigator(inputEl, listEl, chooseCb){
				let active=-1, items=[];
				function esc(s){ return (s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
				function closeList(){ listEl.style.display='none'; listEl.innerHTML=''; active=-1; }
				function render(arr){
					items = Array.isArray(arr) ? arr : [];
					if(!items.length){ closeList(); return; }
					listEl.innerHTML = items.map((it,i)=>(
						`<li data-index="${i}">
							<div class="cmx-ac-row">
								<span class="cmx-ac-nr">${esc(it.nr||'')}</span>
								<span class="cmx-ac-title">${esc(it.title||'')}</span>
						</div>
					</li>`
				)).join('');
				listEl.style.display='block'; active=-1;
			}
			function move(d){
				if(!items.length) return;
				active = (active + d + items.length) % items.length;
				[...listEl.children].forEach((li,i)=>li.classList.toggle('active', i===active));
			}
				function choose(i){
					if(i<0||i>=items.length) return;
					chooseCb(items[i]);
					closeList();
				}
			listEl.addEventListener('mousedown', e=>{
				const li=e.target.closest('li'); if(!li) return;
				e.preventDefault();
				choose(parseInt(li.dataset.index,10));
			});
			inputEl.addEventListener('keydown', e=>{
				if(e.key==='ArrowDown'){
					if(listEl.style.display!=='block'){
						e.preventDefault();
						inputEl.dispatchEvent(new Event('focus'));
						setTimeout(()=>{ move(1); }, 0);
					}else{
						e.preventDefault(); move(1);
					}
				}else if(e.key==='ArrowUp'){
					if(listEl.style.display==='block'){ e.preventDefault(); move(-1); }
					}else if(e.key==='Enter'){
						if(listEl.style.display==='block'){
							const idx = active>-1 ? active : 0;
							e.preventDefault(); choose(idx);
						}
					}else if(e.key==='Tab'){
						if(listEl.style.display==='block'){
							// Beim Verlassen mit TAB niemals automatisch auswählen.
							closeList();
						}
					}else if(e.key==='Escape'){
						if(listEl.style.display==='block'){ e.preventDefault(); closeList(); }
					}
				});
				document.addEventListener('click', e=>{
					if(!listEl.contains(e.target) && e.target!==inputEl){
						closeList();
					}
				});
			return { render, reset:()=>{ items=[]; active=-1; } };
		}

			function fetchArtikel(term, cb){
				$.getJSON(<?php echo wp_json_encode($ajax_url); ?>, { action:'cmx_search_artikel', term: term||'' }, function(data){
					const rows = Array.isArray(data) ? data.map(it=>({ id: it.value, title: it.title||'', nr: it.nr||'' })) : [];
					cb(rows);
				});
			}

			function fetchTextbausteine(term, cb){
				$.getJSON(<?php echo wp_json_encode($ajax_url); ?>, { action:'cmx_search_beleg_textbausteine', term: term||'' }, function(data){
					const rows = Array.isArray(data)
						? data.map(it=>({
							id: it.value || 0,
							nr: it.nr || '',
							title: it.title || '',
							text: it.text || ''
						}))
						: [];
					cb(rows);
				});
			}

			function currentProjektId(){
				const raw = ($('#cmx_projekt_id').val() || '').toString().trim();
				const id = parseInt(raw, 10);
				return isNaN(id) ? 0 : id;
			}

			function currentKontaktId(){
				const raw = ($('#cmx_kontakt_id').val() || '').toString().trim();
				const id = parseInt(raw, 10);
				return isNaN(id) ? 0 : id;
			}

			function fetchOffeneTasks(term, cb, sourceType, sourceId){
				let st = (sourceType === 'kontakte') ? 'kontakte' : 'projekte';
				let sid = parseInt((sourceId ?? '').toString(), 10);
				const explicitSourceType = sourceType === 'kontakte' || sourceType === 'projekte';
				if (!explicitSourceType) {
					const pid = currentProjektId();
					if (pid > 0) {
						st = 'projekte';
						sid = pid;
					} else {
						const kid = currentKontaktId();
						if (kid > 0) {
							st = 'kontakte';
							sid = kid;
						}
					}
				} else if (isNaN(sid) || sid <= 0) {
					sid = (st === 'kontakte') ? currentKontaktId() : currentProjektId();
				}
				$.getJSON(AJAX_URL, {
					action: 'cmx_search_beleg_tasks',
					term: term || '',
					projekt_id: st === 'projekte' ? sid : 0,
					source_type: st,
					source_id: sid
				}, function(data){
					cb(Array.isArray(data) ? data : []);
				}).fail(function(){
					cb([]);
				});
			}

			function buildTaskRefKey(sourceId, uidRaw, idxRaw){
				const sid = parseInt((sourceId ?? '').toString(), 10);
				if (isNaN(sid) || sid <= 0) return '';
				const uid = (uidRaw || '').toString().replace(/[^A-Za-z0-9_-]/g, '');
				if (uid) return 'u:' + sid + ':' + uid;
				const idx = parseInt((idxRaw ?? '').toString(), 10);
				if (!isNaN(idx)) return 'i:' + sid + ':' + idx;
				return '';
			}

			function collectExistingTaskRefKeys(){
				const keys = new Set();
				table.find('tr.cmx-pos-row').each(function(){
					const $row = $(this);
					const sourceRaw = ($row.find('.cmx-task-projekt-id').first().val() || '').toString().trim();
					const sourceId = parseInt(sourceRaw, 10);
					if (isNaN(sourceId) || sourceId <= 0) return;
					const uid = ($row.find('.cmx-task-uid').first().val() || '').toString();
					const idx = ($row.find('.cmx-task-idx').first().val() || '').toString();
					const key = buildTaskRefKey(sourceId, uid, idx);
					if (key) keys.add(key);
				});
				return keys;
			}

			function isPositionRowEmpty($row){
				if(!$row || !$row.length) return false;
				if($row.hasClass('cmx-pos-row-abschnitt')) return false;

				const artikelIdRaw = ($row.find('.cmx-artikel-id').first().val() || '').toString().trim();
				const artikelId = parseInt(artikelIdRaw, 10);
				if(!isNaN(artikelId) && artikelId > 0) return false;

				const fieldSelectors = [
					'.cmx-artikel-autocomplete',
					'input[name*="[menge]"]',
					'input[name*="[preis]"]',
					'input[name*="[rabatt]"]',
					'.cmx-einheit-select',
					'textarea[name*="[beschreibung]"]',
					'.cmx-task-idx',
					'.cmx-task-uid',
					'.cmx-task-projekt-id'
				];
				for(let i = 0; i < fieldSelectors.length; i++){
					const val = ($row.find(fieldSelectors[i]).first().val() || '').toString().trim();
					if(val !== '') return false;
				}
				return true;
			}

			function removeEmptyPositionRows(){
				let removed = 0;
				table.find('tr.cmx-pos-row').each(function(){
					const $row = $(this);
					if(!isPositionRowEmpty($row)) return;
					$row.remove();
					removed++;
				});
				return removed;
			}

			function autoImportTasksFromSource(sourceType, sourceId){
				const st = (sourceType === 'kontakte') ? 'kontakte' : 'projekte';
				const sid = parseInt((sourceId ?? '').toString(), 10);
				if (isNaN(sid) || sid <= 0) return;

				fetchOffeneTasks('', function(rows){
					if (!Array.isArray(rows) || !rows.length) return;
					const removed = removeEmptyPositionRows();
					const existing = collectExistingTaskRefKeys();
					let added = 0;
					rows.forEach(function(task){
						const taskSourceId = parseInt((task && task.projekt_id !== undefined ? task.projekt_id : sid).toString(), 10);
						const key = buildTaskRefKey(taskSourceId, task ? task.task_uid : '', task ? task.task_idx : '');
						if (key && existing.has(key)) return;
						const $row = addPositionRow(task || {});
						if ($row.length) {
							added++;
							if (key) existing.add(key);
						}
					});
					$('#cmx_tasks_prefilled_js').val('1');
					$('#cmx_tasks_prefill_source_type').val(st);
					$('#cmx_tasks_prefill_source_id').val(String(sid));
					if (added > 0 || removed > 0) {
						table.trigger('cmx_positionen_rows_changed');
					}
				}, st, sid);
			}

			document.addEventListener('cmx:task-source-change', function(ev){
				const detail = ev && ev.detail ? ev.detail : {};
				const sourceType = detail.sourceType === 'kontakte' ? 'kontakte' : 'projekte';
				const sourceId = parseInt((detail.sourceId ?? '').toString(), 10);
				if (isNaN(sourceId) || sourceId <= 0) return;
				autoImportTasksFromSource(sourceType, sourceId);
			});

			function taskPickerClose(){
				taskPickerItems = [];
				taskPickerActive = -1;
				if($taskPickerList.length) $taskPickerList.empty();
				if($taskPickerPanel.length) $taskPickerPanel.hide();
			}

			function taskPickerRender(items){
				taskPickerItems = Array.isArray(items) ? items : [];
				taskPickerActive = -1;
				if (!$taskPickerList.length) return;
				if (!taskPickerItems.length) {
					$taskPickerList.html('<li class="cmx-task-picker-empty">Keine offenen Tasks gefunden.</li>');
					return;
				}

				const html = taskPickerItems.map((it, idx) => {
					const artikel = (it.artikel_label || it.artikel_title || '').toString();
					const proj = (it.projekt_title || '').toString();
					const datumZeit = ((it.datum || '') + ' ' + (it.zeit || '')).trim();
					const dauer = parseNumberFlexible(it.menge || '0');
					const info = (it.info || '').toString().trim();
					const metaParts = [];
					if (proj) metaParts.push(proj);
					if (datumZeit) metaParts.push(datumZeit);
					if (dauer > 0) metaParts.push(formatSwiss(dauer) + ' h');
					const meta = metaParts.join(' · ');
					return '' +
						'<li data-index="' + idx + '">' +
							'<div class="cmx-task-picker-row">' +
								'<span class="cmx-task-picker-title">' + escHtml(artikel) + '</span>' +
								(meta ? '<span class="cmx-task-picker-meta">' + escHtml(meta) + '</span>' : '') +
								(info ? '<span class="cmx-task-picker-info">' + escHtml(info) + '</span>' : '') +
							'</div>' +
						'</li>';
				}).join('');
				$taskPickerList.html(html);
			}

			function taskPickerFetchAndRender(term){
				const reqSeq = ++taskPickerSeq;
				fetchOffeneTasks(term, function(rows){
					if (reqSeq !== taskPickerSeq) return;
					taskPickerRender(rows);
				});
			}

			function applyTaskPrefill($row, task){
				if (!task || typeof task !== 'object') return;

				const artikelId = parseInt(task.artikel_id || 0, 10) || 0;
				const taskIdxRaw = task.task_idx;
				const taskIdx = (taskIdxRaw === undefined || taskIdxRaw === null || taskIdxRaw === '') ? '' : String(taskIdxRaw);
				const taskUid = (task.task_uid || '').toString();
				const taskProjektRaw = task.projekt_id;
				const taskProjektId = (taskProjektRaw === undefined || taskProjektRaw === null || taskProjektRaw === '') ? '' : String(taskProjektRaw);
				const artikelLabel = (task.artikel_label || task.artikel_title || '').toString();
				const menge = parseNumberFlexible(task.menge || '');
				const preis = parseNumberFlexible(task.preis || '');
				const info = (task.info || '').toString();

				const $articleId = $row.find('.cmx-artikel-id').first();
				const $articleInput = $row.find('.cmx-artikel-autocomplete').first();
				const $taskIdx = $row.find('.cmx-task-idx').first();
				const $taskUid = $row.find('.cmx-task-uid').first();
				const $taskProjekt = $row.find('.cmx-task-projekt-id').first();
				const $qty = $row.find('input[name*="[menge]"]').first();
				const $price = $row.find('input[name*="[preis]"]').first();
				const $discount = $row.find('input[name*="[rabatt]"]').first();
				const $desc = $row.find('textarea[name*="[beschreibung]"]').first();
				const $edit = $row.find('.cmx-artikel-edit').first();

				$articleId.val(artikelId > 0 ? artikelId : '');
				$articleInput.val(artikelLabel);
				$taskIdx.val(taskIdx);
				$taskUid.val(taskUid.replace(/[^A-Za-z0-9_-]/g, ''));
				$taskProjekt.val(taskProjektId.replace(/[^0-9]/g, ''));
				$qty.val(menge > 0 ? formatSwiss(menge) : '');
				$price.val(preis > 0 ? formatSwiss(preis) : '');
				$discount.val('');
				$desc.val(info);

					if ($edit.length) {
						if (artikelId > 0) {
							$edit.attr('href', ARTICLE_EDIT_BASE + artikelId + '&action=edit');
							$edit.css({ 'pointer-events':'auto', 'opacity':'1' });
						} else {
							$edit.removeAttr('href');
							$edit.css({ 'pointer-events':'none', 'opacity':'0.35' });
						}
					}
					if (artikelId > 0) {
						$.post(AJAX_URL, { action:'cmx_get_artikel_vk', artikel_id: artikelId }, function(resp){
							if (resp && resp.success && resp.data) {
								setRowUnitSelection($row, resp.data.unit_id);
							}
						}, 'json');
					} else {
						setRowUnitSelection($row, 0);
					}
				}

			function addPositionRow(prefill){
				let i = nextRowIndex();
				let $template = table.find('tr.cmx-pos-row:not(.cmx-pos-row-abschnitt):first');
				if (!$template.length) {
					if (INITIAL_ARTICLE_ROW_HTML) {
						$template = $(INITIAL_ARTICLE_ROW_HTML).filter('tr.cmx-pos-row').first();
					}
				}
				if (!$template.length) {
					$template = table.find('tr:first');
				}
				if (!$template.length) {
					return $();
				}
				let newRow = $template.clone();

					newRow.find('input, textarea, select').each(function(){
						let $el = $(this), name = $el.attr('name');
							if(name) $el.attr('name', name.replace(/\[\d+\]/,'['+i+']'));
							if($el.hasClass('cmx-artikel-id')){ $el.val(''); }
							else if($el.hasClass('cmx-task-idx') || $el.hasClass('cmx-task-uid') || $el.hasClass('cmx-task-projekt-id')){ $el.val(''); }
							else if($el.hasClass('cmx-artikel-autocomplete')){ $el.val('').removeData('cmx-suggest-ready'); }
						else if($el.hasClass('cmx-einheit-select')){ $el.val(''); }
						else if($el.is('[name*="[menge]"]')){ $el.val(''); }
						else if($el.is('[name*="[preis]"]')){ $el.val(''); }
						else if($el.is('[name*="[rabatt]"]')){ $el.val(''); }
						else if($el.is('textarea')){ $el.val('').removeData('cmx-text-suggest-ready'); } else { $el.val(''); }
					});
				newRow.find('.cmx-art-suggest').remove();
				newRow.find('.cmx-pos-total').text('0,00');

				if (prefill && typeof prefill === 'object') {
					applyTaskPrefill(newRow, prefill);
				}

				table.append(newRow);
				initArtikelSuggest(newRow);
				initTextbausteinSuggest(newRow);
				table.trigger('cmx_positionen_rows_changed');
				return newRow;
			}

			function initArtikelSuggest($ctx){
			$ctx.find('.cmx-artikel-autocomplete').each(function(){
				const $input = $(this);
				try{ if($.ui && $.ui.autocomplete && $input.data('ui-autocomplete')) $input.autocomplete('destroy'); }catch(e){}
				$input.off('.autocomplete');
				if($input.data('cmx-suggest-ready')) return;

				const $cell = $input.closest('td');
				if($cell.css('position')==='static'){ $cell.css('position','relative'); }
				const $ul = $('<ul class="cmx-art-suggest" style="display:none"></ul>');
				$input.after($ul);

				const nav = makeNavigator($input[0], $ul[0], chooseItem);
				let t=null;

				function chooseItem(it){
					const $row = $input.closest('tr');
					$row.find('.cmx-artikel-id').val(it.id||0);
					$input.val((it.nr?it.nr+' – ':'') + (it.title||''));
					const $edit = $row.find('.cmx-artikel-edit');
					const $qty = $row.find('input[name*="[menge]"]').first();
					if (it.id) {
						$edit.attr('href', <?php echo wp_json_encode(admin_url('post.php?post=')); ?> + it.id + '&action=edit');
						$edit.css({ 'pointer-events':'auto', 'opacity':'1' });
					} else {
						$edit.removeAttr('href');
						$edit.css({ 'pointer-events':'none', 'opacity':'0.35' });
					}
						if ($qty.length) {
							const qtyRaw = ($qty.val() ?? '').toString().trim();
							if (qtyRaw === '') {
								$qty.val(formatSwiss(1)).trigger('input');
							}
						}
						if(it.id){
							$.post(AJAX_URL, { action:'cmx_get_artikel_vk', artikel_id: it.id }, function(resp){
								if(resp && resp.success && resp.data){
									if(resp.data.vk!==undefined){
										$row.find('input[name*="[preis]"]').val(formatSwiss(parseNumberFlexible(resp.data.vk))).trigger('input');
									}
									setRowUnitSelection($row, resp.data.unit_id);
								}
							}, 'json');
						} else {
							setRowUnitSelection($row, 0);
						}
						setTimeout(function(){
							$qty.focus().select();
						}, 0);
				}
				function doSearch(q){ fetchArtikel(q, (rows)=>{ nav.render(rows); }); }

				$input.on('input', function(){
					if(t) clearTimeout(t);
					const q = $input.val().trim();
					if(q.length<1){ doSearch(''); return; }
					t = setTimeout(()=>doSearch(q), 120);
				});
				$input.on('focus click', function(){ doSearch(''); });

					$input.data('cmx-suggest-ready', true);
				});
			}

				function initTextbausteinSuggest($ctx){
					$ctx.find('textarea[name*="[beschreibung]"], input[name*="[abschnitt_titel]"]').each(function(){
						const $input = $(this);
						if($input.data('cmx-text-suggest-ready')) return;
						const isAbschnittTitel = $input.is('input[name*="[abschnitt_titel]"]');

						const $cell = $input.closest('td');
						if($cell.css('position')==='static'){ $cell.css('position','relative'); }
						const $ul = $('<ul class="cmx-art-suggest cmx-text-suggest" style="display:none"></ul>');
						$input.after($ul);

						const nav = makeNavigator($input[0], $ul[0], chooseItem);
						let t = null;
						let querySeq = 0;

						function closeList(){
							$ul.hide().empty();
							if (nav && typeof nav.reset === 'function') nav.reset();
						}

						function chooseItem(it){
							if (t) {
								clearTimeout(t);
								t = null;
							}
							// Invalidate pending AJAX callbacks from previous keystrokes.
							querySeq++;
							if (isAbschnittTitel) {
								const titel = (it.nr || it.label || '').toString().trim();
								const beschreibung = (it.title || it.text || '').toString().trim();
								if (titel !== '') {
									$input.val(titel).trigger('change');
								}
								const $row = $input.closest('tr');
								const $abschnittText = $row.find('textarea[name*="[abschnitt_text]"]').first();
								if ($abschnittText.length && beschreibung !== '') {
									$abschnittText.val(beschreibung).trigger('change');
								}
								$input.data('cmx-text-selected', 1);
							} else {
								const txt = (it.text || it.title || it.nr || '').toString().trim();
								if (txt !== '') {
									$input.val(txt).trigger('change');
								}
							}
							$input.data('cmx-text-suppress-open', 1);
							closeList();
							setTimeout(function(){
								$input.focus();
							}, 0);
						}

					function doSearch(q){
						const reqSeq = ++querySeq;
						fetchTextbausteine(q, function(rows){
							if (reqSeq !== querySeq) return;
							nav.render(rows);
						});
					}

						$input.on('input', function(){
							if(t) clearTimeout(t);
							const q = ($input.val() || '').toString().trim();
							if (isAbschnittTitel) {
								if (q.length < 1) {
									$input.removeData('cmx-text-selected');
									doSearch('');
									return;
								}
								if ($input.data('cmx-text-selected')) {
									closeList();
									return;
								}
							} else {
								// Zusätzliche Notiz: Vorschlagsliste nur bei leerem Feld anzeigen.
								if (q.length > 0) {
									closeList();
									return;
								}
								doSearch('');
								return;
							}
							if(q.length < 1){ doSearch(''); return; }
							t = setTimeout(()=>doSearch(q), 120);
						});
							$input.on('focus click', function(){
								if ($input.data('cmx-text-suppress-open')) {
									$input.removeData('cmx-text-suppress-open');
									closeList();
									return;
							}
								if (isAbschnittTitel) {
									const q = ($input.val() || '').toString().trim();
									if (q !== '') {
										closeList();
										return;
									}
								} else {
									const q = ($input.val() || '').toString().trim();
									if (q !== '') {
										closeList();
										return;
									}
								}
								doSearch('');
							});
							$input.on('blur', function(){
								setTimeout(function(){ closeList(); }, 120);
							});

						$input.data('cmx-text-suggest-ready', true);
					});
				}

		/* ========= Eingabe-Events ========= */
			const selectorMRP = 'input[name*="[menge]"], input[name*="[preis]"], input[name*="[rabatt]"]';
			table.on('keydown', selectorMRP, function(e){
				if (e.key !== 'Enter') return;
				e.preventDefault();
				const $el = $(this);
				$el.trigger('blur');
				setTimeout(function(){
					const form = document.getElementById('post');
					if (!form) return;
					const publishBtn = document.getElementById('publish');
					if (publishBtn && !publishBtn.disabled) {
						publishBtn.click();
						return;
					}
					if (typeof form.requestSubmit === 'function') {
						form.requestSubmit();
						return;
					}
					form.submit();
				}, 0);
			});
		table.on('focus', selectorMRP, function(){ const el=this; setTimeout(()=>{ try{ el.select(); }catch(e){} }, 0); });
		table.on('mouseup', selectorMRP, function(e){ e.preventDefault(); });

			initArtikelSuggest(table);
			initTextbausteinSuggest(table);
			initSortable();

			table.on('input change', selectorMRP, function(){ recalcAll(); });
		table.on('blur', 'input[name*="[menge]"], input[name*="[preis]"]', function(){
			const raw = ($(this).val() ?? '').toString().trim();
			if (raw === '') return;
			const num = parseNumberFlexible(raw);
			$(this).val(formatSwiss(num));
			recalcAll();
		});
		table.on('blur', 'input[name*="[rabatt]"]', function(){
			const raw = ($(this).val() ?? '').toString().trim();
			if (raw === '') return;
			$(this).val(formatRabattValue(raw));
			recalcAll();
		});

				table.on('cmx_positionen_rows_changed', function(){
					reindexPositionRows();
					initArtikelSuggest(table);
					initTextbausteinSuggest(table);
					refreshSortable();
					recalcAll();
				});

			// Neue Zeile
			$('#cmx-add-pos').on('click', function(){
				const $row = addPositionRow({});
				setTimeout(()=>{ $row.find('.cmx-artikel-autocomplete').trigger('focus'); }, 0);
			});

			function taskPickerChoose(index){
				const idx = parseInt(index, 10);
				if (isNaN(idx) || idx < 0 || idx >= taskPickerItems.length) return;
				const selected = taskPickerItems[idx];
				let $row = $();
				const $lastRow = table.children('tr').last();
				const lastIsArticleRow = $lastRow.length
					&& $lastRow.hasClass('cmx-pos-row')
					&& !$lastRow.hasClass('cmx-pos-row-abschnitt');

				if (lastIsArticleRow) {
					const rawId = ($lastRow.find('.cmx-artikel-id').first().val() || '').toString().trim();
					const artikelId = parseInt(rawId, 10);
					const hasSelectedArticle = !isNaN(artikelId) && artikelId > 0;
					if (!hasSelectedArticle) {
						applyTaskPrefill($lastRow, selected);
						table.trigger('cmx_positionen_rows_changed');
						$row = $lastRow;
					}
				}

				if (!$row.length) {
					$row = addPositionRow(selected);
				}

				taskPickerClose();
				setTimeout(function(){
					const $qty = $row.find('input[name*="[menge]"]').first();
					if ($qty.length) {
						$qty.trigger('focus').select();
					} else {
						$row.find('.cmx-artikel-autocomplete').first().trigger('focus');
					}
				}, 0);
			}

			if ($taskPickerWrap.length) {
				$(document).on('click', '#cmx-add-task', function(e){
					e.preventDefault();
					if ($taskPickerPanel.is(':visible')) {
						taskPickerClose();
						return;
					}
					taskPickerSeq++;
					taskPickerItems = [];
					taskPickerActive = -1;
					$taskPickerPanel.show();
					$taskPickerSearch.val('');
					$taskPickerList.html('<li class="cmx-task-picker-empty">Lade offene Tasks...</li>');
					taskPickerFetchAndRender('');
					setTimeout(function(){ $taskPickerSearch.trigger('focus'); }, 0);
				});

				$taskPickerSearch.on('input', function(){
					if (taskPickerTimer) clearTimeout(taskPickerTimer);
					const q = ($(this).val() || '').toString().trim();
					taskPickerTimer = setTimeout(function(){
						taskPickerFetchAndRender(q);
					}, 120);
				});

				$taskPickerSearch.on('keydown', function(e){
					if (!$taskPickerPanel.is(':visible')) return;
					if (e.key === 'Escape') {
						e.preventDefault();
						taskPickerClose();
						return;
					}
					if (!taskPickerItems.length) return;
					if (e.key === 'ArrowDown') {
						e.preventDefault();
						taskPickerActive = (taskPickerActive + 1 + taskPickerItems.length) % taskPickerItems.length;
					} else if (e.key === 'ArrowUp') {
						e.preventDefault();
						taskPickerActive = (taskPickerActive - 1 + taskPickerItems.length) % taskPickerItems.length;
					} else if (e.key === 'Enter') {
						e.preventDefault();
						const idx = taskPickerActive > -1 ? taskPickerActive : 0;
						taskPickerChoose(idx);
						return;
					} else {
						return;
					}
					$taskPickerList.children('li[data-index]').removeClass('active')
						.eq(taskPickerActive).addClass('active');
				});

				$taskPickerList.on('mousedown', 'li[data-index]', function(e){
					e.preventDefault();
					taskPickerChoose($(this).attr('data-index'));
				});

				$(document).on('mousedown', function(e){
					if (!$taskPickerPanel.is(':visible')) return;
					if (!$taskPickerWrap.is(e.target) && $taskPickerWrap.has(e.target).length === 0) {
						taskPickerClose();
					}
				});
			}

			// Entfernen
			table.on('click','.cmx-del-pos',function(){
				const $row = $(this).closest('tr');
				const rowCount = table.find('tr').length;
				const isAbschnitt = $row.hasClass('cmx-pos-row-abschnitt');
				let rowsChangedHandled = false;

				if (rowCount > 1) {
					$row.remove();
				} else {
					if (isAbschnitt) {
						// Wenn nur noch ein Abschnitt existiert und gelöscht wird, direkt leere Artikel-Zeile wiederherstellen.
						$row.remove();
						const $newRow = addPositionRow({});
						if ($newRow.length) {
							rowsChangedHandled = true;
							setTimeout(function(){
								$newRow.find('.cmx-artikel-autocomplete').first().trigger('focus');
							}, 0);
						}
					} else {
						// Letzte normale Positionszeile: Inhalte leeren, damit kein zusätzlicher Platzhalter nötig ist
							$row.find('input, textarea, select').each(function(){
								const $el = $(this);
								if ($el.hasClass('cmx-artikel-id')) { $el.val(''); }
								else if ($el.hasClass('cmx-task-idx') || $el.hasClass('cmx-task-uid') || $el.hasClass('cmx-task-projekt-id')) { $el.val(''); }
								else if ($el.hasClass('cmx-artikel-autocomplete')) { $el.val(''); }
								else if ($el.hasClass('cmx-einheit-select')) { $el.val(''); }
								else { $el.val(''); }
							});
						$row.find('.cmx-pos-total').text('0,00');
					}
				}

				if (!rowsChangedHandled) {
					table.trigger('cmx_positionen_rows_changed');
				}
			});

		// Initial
		reindexPositionRows();
		recalcAll();
		const $postForm = table.closest('form#post');
		if ($postForm.length) {
			$postForm.on('submit', function(){
				reindexPositionRows();
			});
		}
	});
	</script>
		<style>
			.cmx-art-suggest{ position:absolute; z-index:1000; left:0; right:0; max-height:280px; overflow:auto; margin:2px 0 0; padding:0; border:1px solid #ccd0d4; background:#fff; list-style:none; }
			.cmx-art-suggest li{ margin:0; padding:0; cursor:pointer; }
			.cmx-art-suggest li.active, .cmx-art-suggest li:hover{ background:#e5f3ff; }
			.cmx-task-picker-wrap{ position:relative; display:inline-block; margin-left:8px; }
			.cmx-task-picker-panel{
				position:absolute;
				top:0;
				left:calc(100% + 6px);
				z-index:1200;
				width:520px;
				max-width:86vw;
				padding:8px;
				border:1px solid #ccd0d4;
				background:#fff;
				box-shadow:0 6px 16px rgba(0,0,0,.08);
			}
			#cmx-task-picker-search{ width:100%; margin-bottom:6px; }
			.cmx-task-picker-list{
				max-height:320px;
				overflow:auto;
				margin:0;
				padding:0;
				list-style:none;
				border:1px solid #dcdcde;
				background:#fff;
			}
			.cmx-task-picker-list li{ margin:0; padding:0; cursor:pointer; border-bottom:1px solid #f0f0f1; }
			.cmx-task-picker-list li:last-child{ border-bottom:0; }
			.cmx-task-picker-list li:hover,
			.cmx-task-picker-list li.active{ background:#e5f3ff; }
			.cmx-task-picker-row{ display:flex; flex-direction:column; gap:2px; padding:6px 8px; }
			.cmx-task-picker-title{ font-weight:600; line-height:1.25; }
			.cmx-task-picker-meta{ font-size:12px; color:#50575e; line-height:1.2; }
			.cmx-task-picker-info{ font-size:12px; color:#646970; line-height:1.2; }
			.cmx-task-picker-empty{ padding:8px; color:#646970; cursor:default; }
				.cmx-ac-row{ display:grid; grid-template-columns: 140px 1fr; gap:8px; align-items:center; padding:6px 8px; }
				.cmx-ac-nr{ font-weight:600; white-space:nowrap; }
				.cmx-ac-title{ overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

				#cmx_beleg_positionen .inside{
					margin:0;
					padding:0px;
				}
				#cmx-positionen-wrap{
					margin:0;
					padding:0;
				}
				#cmx-positionen-table{
					border:0 !important;
					box-shadow:none !important;
					border-collapse:collapse;
					margin:0 !important;
				}
				#cmx-positionen-table thead th{
					border-top:0 !important;
					padding-top:4px;
				}
				#cmx-positionen-table > thead > tr > th:first-child,
				#cmx-positionen-table > tbody > tr > td:first-child{
					border-left:0 !important;
				}
				#cmx-positionen-table > thead > tr > th:last-child,
				#cmx-positionen-table > tbody > tr > td:last-child{
					border-right:0 !important;
				}
				#cmx-positionen-table th, #cmx-positionen-table td { vertical-align: middle; }
					#cmx-positionen-table th:first-child,
					#cmx-positionen-table td:first-child{
						padding-left:0;
						padding-right:6px;
					}
					#cmx-positionen-table th:first-child{
						width:36%;
						padding-left:20px;
					}
					#cmx-positionen-table th:nth-child(3),
					#cmx-positionen-table td:nth-child(3),
					#cmx-positionen-table th:nth-child(4),
					#cmx-positionen-table td:nth-child(4){
						width:1%;
						white-space:nowrap;
						padding-left:2px;
						padding-right:2px;
					}
					#cmx-positionen-table th.cmx-pos-qty-head,
					#cmx-positionen-table td.cmx-pos-qty-cell{
						white-space:nowrap;
						min-width:206px;
					}
					#cmx-positionen-table th.cmx-pos-qty-head .cmx-pos-qty-head-menge{
						display:inline-block;
						width:90px;
					}
					#cmx-positionen-table th.cmx-pos-qty-head .cmx-pos-qty-head-einheit{
						display:inline-block;
						margin-left:6px;
						width:120px;
					}
					#cmx-positionen-table td.cmx-pos-qty-cell .cmx-einheit-select{
						margin-left:6px;
						width:120px;
						max-width:48%;
					}
					#cmx-positionen-table td:nth-child(3) input,
					#cmx-positionen-table td:nth-child(4) input{
						width:88px !important;
						min-width:88px;
						box-sizing:border-box;
					}
					#cmx-positionen-table td textarea { resize: vertical; }
					#cmx-positionen-table td.cmx-pos-beschr-cell{
						position:relative;
					padding-left:26px;
				}
				#cmx-positionen-table .cmx-textbaustein-edit{
					position:absolute;
					left:6px;
					top:8px;
					text-decoration:none;
					font-size:12px;
					color:#2271b1;
					line-height:1;
				}
				#cmx-positionen-table .cmx-textbaustein-edit:hover{
					color:#135e96;
				}
				.cmx-pos-row td:first-child{ position:relative; padding-right:8px; }
			.cmx-pos-total{ font-weight:600; text-align:right; }
			#cmx-positionen-table td.cmx-pos-controls{
				white-space:nowrap;
				width:1%;
				text-align:right;
				vertical-align:top;
				padding-left:2px;
				padding-right:0;
			}
			#cmx-positionen-table .cmx-pos-controls .cmx-del-pos,
			#cmx-positionen-table .cmx-pos-controls .cmx-pos-drag-handle,
			#cmx-positionen-table .cmx-pos-controls .cmx-section-drag-handle{
				vertical-align:top;
			}
			#cmx-positionen-table .cmx-pos-controls .cmx-del-pos{ margin-left:4px; }
			#cmx-positionen-table .cmx-pos-drag-handle{
				cursor:move;
				display:inline-flex;
				align-items:center;
				justify-content:center;
				width:20px;
				height:20px;
				margin-right:4px;
				color:#646970;
				border-radius:3px;
				user-select:none;
				font-weight:600;
			}
			#cmx-positionen-table .cmx-section-drag-handle{
				cursor:move;
				margin-right:4px;
				display:inline-flex;
				align-items:center;
				justify-content:center;
				width:20px;
				min-width:20px;
				height:20px;
				min-height:20px;
				line-height:1;
				padding:0;
				font-weight:600;
			}
			#cmx-positionen-table .cmx-pos-drag-handle:hover{ background:#f0f0f1; color:#1d2327; }
			#cmx-positionen-table tr.ui-sortable-helper td{
				background:#fff;
				box-shadow: inset 0 0 0 1px #c3c4c7;
			}
			#cmx-positionen-table tr.ui-sortable-helper.cmx-sorting-section td{
				background:#eef6ff;
				box-shadow: inset 0 0 0 1px #72aee6;
			}
			#cmx-positionen-table tr.cmx-pos-sort-placeholder td.cmx-pos-sort-placeholder-cell{
				background:#f6f7f7 !important;
				border:1px dashed #8c8f94;
				height:34px;
				padding:4px 8px;
			}
			#cmx-positionen-table tr.cmx-pos-sort-placeholder-section td.cmx-pos-sort-placeholder-cell{
				background:#eaf4ff !important;
				border-color:#72aee6;
			}
			#cmx-positionen-table .cmx-pos-sort-placeholder-label{
				display:inline-block;
				font-size:11px;
				font-weight:600;
				color:#1d2327;
			}
			#cmx-positionen-table.cmx-section-drag-active tr.cmx-pos-row-abschnitt td{
				background:#f0f6fc;
			}
			.cmx-artikel-edit{
				position:absolute;
			left:6px;
			top:50%;
			transform:translateY(-50%);
			text-decoration:none;
			font-size:12px;
			color:#2271b1;
			padding-right:6px;
		}
		.cmx-pos-row td:first-child .cmx-artikel-autocomplete{
			padding-left:8px;
			margin-left:16px;
			width: calc(100% - 16px);
		}
	</style>
	<?php
}

/* ------------------------------
 * AJAX: Positionen nach Sortierung speichern (gehärtet)
 * ------------------------------ */
add_action('wp_ajax_cmx_save_beleg_positionen_order', function() {

	if (!current_user_can('edit_posts')) wp_send_json_error(['msg'=>'forbidden'],403);

	$post_id = (int)($_POST['post_id'] ?? 0);
	if ($post_id <= 0) wp_send_json_error(['msg'=>'no_post_id'],400);

	$rows = $_POST['rows'] ?? [];
	if (!is_array($rows)) wp_send_json_error(['msg'=>'invalid_rows'],400);

	$max_rows = 500;
	if (count($rows) > $max_rows) $rows = array_slice($rows, 0, $max_rows);

	$clean = [];
	foreach ($rows as $r) {
		if (!\is_array($r)) continue;
		$custom = \apply_filters('cmx_beleg_positionen_clean_custom_row', null, $r, (int) $post_id);
		if (\is_array($custom)) {
			$clean[] = $custom;
			continue;
		}
		if ($custom === false) {
			continue;
		}
		$artikel_id   = isset($r['artikel_id']) ? (int)$r['artikel_id'] : 0;

		$menge_raw    = (string)($r['menge'] ?? '');
		$menge        = (float)\CLOUDMEISTER\CMX\Buero\cmx_norm_decimal($menge_raw);
		$menge        = (float) \round($menge, 2);
		$preis        = (float)\CLOUDMEISTER\CMX\Buero\cmx_norm_decimal((string)($r['preis'] ?? ''));
		$preis        = (float) \round($preis, 2);

		$rabatt_raw   = isset($r['rabatt']) ? (string)$r['rabatt'] : '';
		$rabatt       = sanitize_text_field($rabatt_raw);

		$beschreibung_raw = isset($r['beschreibung']) ? (string)$r['beschreibung'] : '';
		$beschreibung_raw = wp_unslash($beschreibung_raw);
		$beschreibung_raw = str_replace(["\r\n", "\r"], "\n", $beschreibung_raw);
		$beschreibung = trim($beschreibung_raw);
		$task_idx_raw = isset($r['task_idx']) ? trim((string)$r['task_idx']) : '';
		$task_idx = ($task_idx_raw !== '' && is_numeric($task_idx_raw)) ? (int)$task_idx_raw : null;
		$task_uid_raw = isset($r['task_uid']) ? (string)$r['task_uid'] : '';
		$task_uid = (string)\preg_replace('/[^A-Za-z0-9_-]/', '', $task_uid_raw);
		$task_projekt_id_raw = isset($r['task_projekt_id']) ? trim((string)$r['task_projekt_id']) : '';
		$task_projekt_id = ($task_projekt_id_raw !== '' && is_numeric($task_projekt_id_raw)) ? (int)$task_projekt_id_raw : null;
		$unit_data = \function_exists(__NAMESPACE__ . '\\cmx_beleg_resolve_position_unit')
			? cmx_beleg_resolve_position_unit($r, $artikel_id)
			: ['einheit_id' => 0, 'unit' => ''];
		$einheit_id = (int) ($unit_data['einheit_id'] ?? 0);
		$unit = \sanitize_text_field((string) ($unit_data['unit'] ?? ''));

		// Wenn Artikel gewählt ist, leere Menge als 1 übernehmen.
		if ($artikel_id > 0 && \trim($menge_raw) === '' && $menge == 0.0) {
			$menge = 1.0;
		}

		// negative Mengen zulassen; nur 0 verwerfen
		if ($artikel_id <= 0 || $menge == 0.0) continue;
		if (strlen($beschreibung) > 10000) $beschreibung = substr($beschreibung, 0, 10000);

		$clean[] = [
			'artikel_id'   => $artikel_id,
			'menge'        => $menge,
			'einheit_id'   => $einheit_id,
			'unit'         => $unit,
			'preis'        => $preis,
			'rabatt'       => $rabatt,
			'beschreibung' => $beschreibung,
			'task_idx'     => $task_idx,
			'task_uid'     => $task_uid,
			'task_projekt_id' => $task_projekt_id,
		];
	}

	$old = get_post_meta($post_id, '_cmx_beleg_positionen', true);
	if ($old !== $clean) {
		update_post_meta($post_id, '_cmx_beleg_positionen', $clean);
		if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_artikel_usage_counts_invalidate')) {
			cmx_beleg_artikel_usage_counts_invalidate();
		}
	}

	wp_send_json_success(['saved'=>count($clean)]);
});
