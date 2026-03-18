<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/* =========================================================
 * Defensive Konstanten (nur definieren, falls noch nicht)
 * ========================================================= */
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_SKU'))            \define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_SKU', '_cmx_artikel_sku');
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_EK'))             \define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_EK', '_cmx_artikel_ek');
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VK'))             \define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VK', '_cmx_artikel_vk');
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_MARGE'))          \define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_MARGE', '_cmx_artikel_marge');
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VERKAUFBAR'))     \define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VERKAUFBAR', '_cmx_artikel_verkaufbar');
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_KATALOG'))        \define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_KATALOG', '_cmx_artikel_katalog');
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_BEZUGSQUELLE'))   \define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_BEZUGSQUELLE', '_cmx_artikel_bezugsquelle_url');
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_LIEFERANT_ID'))   \define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_LIEFERANT_ID', '_cmx_artikel_lieferant_id');
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VARIANT_ROWS'))   \define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VARIANT_ROWS', '_cmx_artikel_variant_rows');

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_admin_variant_term_name')) {
	function cmx_artikel_admin_variant_term_name(string $taxonomy, int $term_id): string {
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_admin_variant_money_display')) {
	function cmx_artikel_admin_variant_money_display($value): string {
		$raw = \trim((string) $value);
		if ($raw === '') {
			return '';
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_parse_number')) {
			$number = (float) cmx_parse_number($raw);
		} else {
			$number = (float) \str_replace([',', "'"], ['.', ''], $raw);
		}
		return \function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')
			? (string) cmx_format_swiss_number($number, 2)
			: \number_format($number, 2, '.', "'");
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_admin_variant_entries')) {
	function cmx_artikel_admin_variant_entries(int $post_id): array {
		static $cache = [];
		if (isset($cache[$post_id])) {
			return $cache[$post_id];
		}
		$stored = \get_post_meta($post_id, CMX_ARTIKEL_META_VARIANT_ROWS, true);
		if (!\is_array($stored) || empty($stored)) {
			$cache[$post_id] = [];
			return [];
		}

		$parent_title = (string) \get_the_title($post_id);
		if (\function_exists(__NAMESPACE__ . '\\cmx_normalize_minus_sign')) {
			$parent_title = (string) cmx_normalize_minus_sign($parent_title);
		}
		$parent_title = \trim($parent_title);
		$einheiten_tax = \function_exists(__NAMESPACE__ . '\\cmx_tax_einheiten') ? (string) cmx_tax_einheiten() : '';
		$entries = [];

		foreach (\array_values($stored) as $index => $row) {
			if (!\is_array($row)) {
				continue;
			}
			$sku = \trim((string) ($row['sku'] ?? ''));
			$left_taxonomy = \sanitize_key((string) ($row['left_taxonomy'] ?? ''));
			$right_taxonomy = \sanitize_key((string) ($row['right_taxonomy'] ?? ''));
			$left_term = cmx_artikel_admin_variant_term_name($left_taxonomy, (int) ($row['left_term_id'] ?? 0));
			$right_term = cmx_artikel_admin_variant_term_name($right_taxonomy, (int) ($row['right_term_id'] ?? 0));
			$einheit = cmx_artikel_admin_variant_term_name($einheiten_tax, (int) ($row['einheit_term_id'] ?? 0));
			$title_parts = \array_values(\array_filter([$left_term, $right_term], static fn($v) => \trim((string) $v) !== ''));
			$title = $parent_title !== '' ? $parent_title : '(ohne Titel)';
			if (!empty($title_parts)) {
				$title .= ' - ' . \implode(' / ', $title_parts);
			} elseif ($sku !== '') {
				$title .= ' - ' . $sku;
			}

			$search_blob = \implode(' ', \array_filter([
				$title,
				$parent_title,
				$sku,
				$left_term,
				$right_term,
				$einheit,
				(string) ($row['belegtext'] ?? ''),
			], static fn($v) => \trim((string) $v) !== ''));

			if (\function_exists('mb_strtolower')) {
				$search_blob = (string) \mb_strtolower($search_blob, 'UTF-8');
			} else {
				$search_blob = (string) \strtolower($search_blob);
			}

			$entries[] = [
				'index'      => (int) $index,
				'title'      => $title,
				'sku'        => $sku,
				'farben'     => $right_term,
				'einheit'    => $einheit,
				'vk'         => cmx_artikel_admin_variant_money_display($row['vk'] ?? ''),
				'ek'         => cmx_artikel_admin_variant_money_display($row['ek'] ?? ''),
				'marge'      => cmx_artikel_admin_variant_money_display($row['marge'] ?? ''),
				'verkaufbar' => !empty($row['verkaufbar']),
				'katalog'    => !empty($row['katalog']),
				'search'     => $search_blob,
			];
		}

		$cache[$post_id] = $entries;
		return $entries;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_admin_variant_search_ids')) {
	function cmx_artikel_admin_variant_search_ids(string $term): array {
		static $cache = [];
		$term = \trim($term);
		if ($term === '') {
			return [];
		}
		$needle = \function_exists('mb_strtolower') ? (string) \mb_strtolower($term, 'UTF-8') : (string) \strtolower($term);
		if (isset($cache[$needle])) {
			return $cache[$needle];
		}

		$post_ids = \get_posts([
			'post_type'      => 'artikel',
			'post_status'    => ['publish', 'draft', 'pending', 'future', 'private'],
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => [[
				'key'     => CMX_ARTIKEL_META_VARIANT_ROWS,
				'compare' => 'EXISTS',
			]],
		]);

		$matches = [];
		foreach ((array) $post_ids as $post_id) {
			foreach (cmx_artikel_admin_variant_entries((int) $post_id) as $entry) {
				$haystack = (string) ($entry['search'] ?? '');
				if ($haystack !== '' && \strpos($haystack, $needle) !== false) {
					$matches[] = (int) $post_id;
					break;
				}
			}
		}

		$cache[$needle] = \array_values(\array_unique($matches));
		return $cache[$needle];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_admin_image_url_exists')) {
	function cmx_artikel_admin_image_url_exists(string $url): bool {
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_admin_image_src')) {
	function cmx_artikel_admin_image_src(int $post_id): string {
		$local_path = \trim((string) \get_post_meta($post_id, '_cmx_local_image_artikel_path', true));
		$local_img = \trim((string) \get_post_meta($post_id, '_cmx_local_image_artikel_url', true));
		if ($local_path !== '' && \is_file($local_path) && $local_img !== '') {
			return $local_img;
		}
		if ($local_img !== '' && cmx_artikel_admin_image_url_exists($local_img)) {
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

function cmx_artikel_is_verkaufbar(int $post_id): bool {
	return (int) \get_post_meta($post_id, CMX_ARTIKEL_META_VERKAUFBAR, true) !== 1;
}

function cmx_artikel_is_katalog_sichtbar(int $post_id): bool {
	if (!\metadata_exists('post', $post_id, CMX_ARTIKEL_META_KATALOG)) {
		return true;
	}
	$raw = (string) \get_post_meta($post_id, CMX_ARTIKEL_META_KATALOG, true);
	return $raw === '' || (int) $raw === 1;
}

/* =========================================================
 * Helpers: Taxonomie-Ermittlung + Kontakte + Lieferantenliste
 * ========================================================= */


/**
 * Wählt den ersten existierenden Taxonomie-Slug aus Kandidaten.
 */
function cmx_first_existing_tax(array $candidates): ?string {
	foreach ($candidates as $tax) {
		if (\taxonomy_exists($tax)) return $tax;
	}
	return null;
}


/** Kandidaten je Logik (deine Slugs können variieren – wir finden, was existiert) */
function cmx_tax_typen(): ?string      { return cmx_first_existing_tax(['artikel_type','artikel_typ','artikel_typen','artikel_types']); }
function cmx_tax_kategorien(): ?string { return cmx_first_existing_tax(['artikel_kategorie','artikel_kategorien','artikel_category','artikel_categories']); }
function cmx_tax_marken(): ?string     { return cmx_first_existing_tax(['artikel_marke','artikel_marken']); }
function cmx_tax_farben(): ?string     { return cmx_first_existing_tax(['artikel_farbe','artikel_farben','farbe','farben']); }
function cmx_tax_einheiten(): ?string  { return cmx_first_existing_tax(['artikel_einheit','artikel_einheiten','einheit','einheiten']); }

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_admin_marken_label')) {
	function cmx_artikel_admin_marken_label(string $label): string {
		$label = (string) \preg_replace('/\s*\([^)]*\)\s*/u', ' ', $label);
		$label = (string) \preg_replace('/\s+/u', ' ', $label);
		return \trim($label);
	}
}


/** Lieferanten-Query-Args (optional eingeschränkt, falls Kennzeichnung existiert) */
function cmx_lieferanten_args(): array {
	$args = [
		'post_type'      => 'kontakte',
		'post_status'    => 'any',
		'posts_per_page' => 1000,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	];

	$tax_query = [];
	if (\taxonomy_exists('lieferant')) {
		$tax_query[] = ['taxonomy'=>'lieferant','field'=>'slug','terms'=>['lieferant']];
	}
	foreach (['kontakt_type','kundenart'] as $maybe_tax) {
		if (\taxonomy_exists($maybe_tax)) {
			$tax_query[] = ['taxonomy'=>$maybe_tax,'field'=>'slug','terms'=>['lieferant']];
			break;
		}
	}
	if (!empty($tax_query)) {
		$args['tax_query'] = (count($tax_query) > 1) ? array_merge(['relation'=>'OR'],$tax_query) : $tax_query;
	} else {
		// Fallback Meta-Flag
		$args['meta_query'] = [[
			'key'     => 'is_supplier',
			'value'   => ['1', 1, 'true', true],
			'compare' => 'IN',
		]];
	}

	return $args;
}


/* =========================================================
 * Spalten definieren
 * ========================================================= */
\add_filter('manage_edit-artikel_columns', function (array $cols): array {
	$new = [];
	$new['cb']             = $cols['cb'] ?? '<input type="checkbox" />';
	$new['title']          = 'Titel';
	$new['sku']            = 'Artikel-Nr.';
	$new['typen']          = 'Typen';
	$new['kategorien']     = 'Kategorien';
	$new['marken']         = 'Marke';
	$new['farben']         = 'Farbe';
	$new['einheiten']      = 'Einheit';
	$new['lieferant']      = 'Lieferant';
	$new['vk']             = 'VK';
	$new['ek']             = 'EK';
	$new['marge']          = 'Marge';
	$new['verkaufbar']     = 'Verkaufbar';
	$new['katalog']        = 'Katalog';
	$new['hersteller_url'] = 'URL';
	$new['featimg']        = 'Bild';
	return $new;
}, 999);


/* =========================================================
 * Zellinhalte
 * ========================================================= */
\add_action('manage_artikel_posts_custom_column', function (string $col, int $post_id) {
	switch ($col) {
		case 'sku':
			echo \esc_html(\get_post_meta($post_id, CMX_ARTIKEL_META_SKU, true));
			break;

		case 'typen':
			$tax = cmx_tax_typen();
			if (!$tax) { echo ''; break; }
			$terms = \get_the_terms($post_id, $tax);
			if (\is_wp_error($terms) || empty($terms)) { echo ''; break; }
			$out = [];
			foreach ($terms as $t) {
				$url = \add_query_arg(['post_type' => 'artikel', $tax => $t->slug], \admin_url('edit.php'));
				$out[] = '<a href="'.\esc_url($url).'">'.\esc_html($t->name).'</a>';
			}
			echo implode(', ', $out);
			break;

		case 'kategorien':
			$tax = cmx_tax_kategorien();
			if (!$tax) { echo ''; break; }
			$terms = \get_the_terms($post_id, $tax);
			if (\is_wp_error($terms) || empty($terms)) { echo ''; break; }
			$out = [];
			foreach ($terms as $t) {
				$url = \add_query_arg(['post_type' => 'artikel', $tax => $t->slug], \admin_url('edit.php'));
				$out[] = '<a href="'.\esc_url($url).'">'.\esc_html($t->name).'</a>';
			}
			echo implode(', ', $out);
			break;

		case 'marken':
			$tax = cmx_tax_marken();
			if (!$tax) { echo ''; break; }
			$terms = \get_the_terms($post_id, $tax);
			if (\is_wp_error($terms) || empty($terms)) { echo ''; break; }
			$out = [];
			foreach ($terms as $t) {
				$url = \add_query_arg(['post_type' => 'artikel', $tax => $t->slug], \admin_url('edit.php'));
				$label = \function_exists(__NAMESPACE__ . '\\cmx_artikel_admin_marken_label')
					? (string) cmx_artikel_admin_marken_label((string) $t->name)
					: (string) $t->name;
				if ($label === '') {
					continue;
				}
				$out[] = '<a href="'.\esc_url($url).'">'.\esc_html($label).'</a>';
			}
			echo implode(', ', $out);
			break;

		case 'farben':
			$tax = cmx_tax_farben();
			if (!$tax) { echo ''; break; }
			$terms = \get_the_terms($post_id, $tax);
			if (\is_wp_error($terms) || empty($terms)) { echo ''; break; }
			$out = [];
			foreach ($terms as $t) {
				$url = \add_query_arg(['post_type' => 'artikel', $tax => $t->slug], \admin_url('edit.php'));
				$out[] = '<a href="'.\esc_url($url).'">'.\esc_html($t->name).'</a>';
			}
			echo implode(', ', $out);
			break;

		case 'einheiten':
			$tax = cmx_tax_einheiten();
			if (!$tax) { echo ''; break; }
			$terms = \get_the_terms($post_id, $tax);
			if (\is_wp_error($terms) || empty($terms)) { echo ''; break; }
			$out = [];
			foreach ($terms as $t) {
				$url = \add_query_arg(['post_type' => 'artikel', $tax => $t->slug], \admin_url('edit.php'));
				$out[] = '<a href="'.\esc_url($url).'">'.\esc_html($t->name).'</a>';
			}
			echo implode(', ', $out);
			break;

		case 'lieferant':
			$lid = (int)\get_post_meta($post_id, CMX_ARTIKEL_META_LIEFERANT_ID, true);
			if ($lid > 0) {
				$title = \get_the_title($lid);
				$link  = \get_edit_post_link($lid, '');
				if (!$title) { $title = '(#'.$lid.')'; }
				echo $link ? '<a href="'.\esc_url($link).'">'.\esc_html($title).'</a>' : \esc_html($title);
			} else {
				echo '';
			}
			break;

		case 'vk':
			$vk = (float)\get_post_meta($post_id, CMX_ARTIKEL_META_VK, true);
			echo \esc_html(cmx_format_swiss_number($vk, 2));
			break;

		case 'ek':
			$ek = (float)\get_post_meta($post_id, CMX_ARTIKEL_META_EK, true);
			echo \esc_html(cmx_format_swiss_number($ek, 2));
			break;

		case 'marge':
			$m = (float)\get_post_meta($post_id, CMX_ARTIKEL_META_MARGE, true);
			echo \esc_html(cmx_format_swiss_number($m, 2));
			break;

		case 'verkaufbar':
			$is_sellable = cmx_artikel_is_verkaufbar($post_id);
			echo $is_sellable
				? '<span title="Verkaufbar" aria-hidden="true">&#10003;</span><span class="screen-reader-text">Verkaufbar</span>'
				: '<span title="Nicht verkaufbar" aria-hidden="true"></span><span class="screen-reader-text">Nicht verkaufbar</span>';
			break;

		case 'katalog':
			$in_catalog = cmx_artikel_is_katalog_sichtbar($post_id);
			echo $in_catalog
				? '<span title="Im Katalog" aria-hidden="true">&#10003;</span><span class="screen-reader-text">Im Katalog</span>'
				: '<span title="Nicht im Katalog" aria-hidden="true"></span><span class="screen-reader-text">Nicht im Katalog</span>';
			break;

		case 'hersteller_url':
			$url = \trim(\get_post_meta($post_id, CMX_ARTIKEL_META_BEZUGSQUELLE, true));
			echo $url
				? '<a href="' . \esc_url($url) . '" target="_blank" rel="noopener noreferrer" title="Hersteller-Website" style="text-decoration:none;"><span class="dashicons dashicons-admin-site-alt3" style="font-size:18px;vertical-align:middle;"></span></a>'
				: '';
			break;

		case 'featimg':
			// Zeige kleines Thumbnail: erst lokales Artikelbild, sonst Beitragsbild. Klick = Download.
			$src = \function_exists(__NAMESPACE__ . '\\cmx_artikel_admin_image_src')
				? (string) cmx_artikel_admin_image_src($post_id)
				: '';

			if ($src !== '') {
				$path     = \parse_url($src, PHP_URL_PATH);
				$filename = $path ? basename($path) : ('artikel-' . (int) $post_id . '.jpg');
				$img_tag  = '<img class="cmx-ac-thumb" src="' . \esc_url($src) . '" alt="" onerror="this.onerror=null; if(this.parentNode){ this.parentNode.remove(); }">';
				echo '<a href="' . \esc_url($src) . '" download="' . \esc_attr($filename) . '" title="Bild herunterladen" style="text-decoration:none;">' . $img_tag . '</a>';
			// } else {
			// 	echo '<span class="dashicons dashicons-format-image" style="opacity:0.35;" title="Kein Bild"></span>';
			}
			break;
	}
}, 10, 2);


/* =========================================================
 * Sortierbare Spalten
 * ========================================================= */
\add_filter('manage_edit-artikel_sortable_columns', function (array $cols): array {
	$cols['sku']            = 'sku';
	$cols['marken']         = 'marke';
	$cols['lieferant']      = 'lieferant';
	$cols['vk']             = 'vk';
	$cols['ek']             = 'ek';
	$cols['marge']          = 'marge';
	$cols['verkaufbar']     = 'verkaufbar';
	$cols['katalog']        = 'katalog';
	$cols['hersteller_url'] = 'hersteller_url';
	$cols['featimg']        = 'featimg';
	return $cols;
});

\add_action('admin_head-edit.php', function () {
	if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'artikel') return;
	?>
		<style>
			.cmx-ac-thumb {
				width: 50px;
				height: 50px;
				object-fit: contain; /* ganzes Bild zeigen wie im Katalog */
				background: #fff;
				border: 1px solid #e6e6e6;
				border-radius: 6px;
				box-shadow: 0 2px 6px rgba(0,0,0,0.08);
				display: inline-block;
			}
			.wp-list-table th#title,
			.wp-list-table th.manage-column.column-title,
			.wp-list-table td.title.column-title {
				width: 25ch;
				max-width: 25ch;
			}
			.wp-list-table th.manage-column.column-title,
			.wp-list-table th.manage-column.column-title > a,
			.wp-list-table th.manage-column.column-title > span,
			.wp-list-table td.title.column-title,
			.wp-list-table td.column-title strong,
			.wp-list-table td.column-title .row-title {
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}
		</style>
		<?php
	});


/* =========================================================
 * Filter-Dropdowns (oben) inkl. Lieferant
 * ========================================================= */
\add_action('restrict_manage_posts', function (string $post_type, string $which) {
	if ($post_type !== 'artikel' || $which !== 'top') return;

	$taxes = array_filter([
		cmx_tax_typen()      => ['label' => 'Typen', 'column' => 'typen'],
		cmx_tax_kategorien() => ['label' => 'Kategorien', 'column' => 'kategorien'],
		cmx_tax_marken()     => ['label' => 'Marken', 'column' => 'marken'],
		cmx_tax_farben()     => ['label' => 'Farben', 'column' => 'farben'],
		cmx_tax_einheiten()  => ['label' => 'Einheiten', 'column' => 'einheiten'],
	]);

	foreach ($taxes as $tax => $config) {
		$column = (string) ($config['column'] ?? '');
		if ($column !== '' && \function_exists(__NAMESPACE__ . '\\cmx_admin_post_type_column_is_visible') && !cmx_admin_post_type_column_is_visible('artikel', $column)) {
			continue;
		}
		$label = (string) ($config['label'] ?? '');
		$selected = isset($_GET[$tax]) ? sanitize_text_field($_GET[$tax]) : '';
		\wp_dropdown_categories([
			'show_option_all' => 'Alle '.$label,
			'taxonomy'        => $tax,
			'name'            => $tax,
			'orderby'         => 'name',
			'selected'        => $selected,
			'hierarchical'    => true,
			'hide_empty'      => false,
			'value_field'     => 'slug', // liefert Slugs; unten in pre_get_posts abgesichert
		]);
	}

	$lieferanten = \get_posts(cmx_lieferanten_args());
	$current = isset($_GET['cmx_lieferant']) ? (string)$_GET['cmx_lieferant'] : '0';
	$current_verkaufbar = isset($_GET['cmx_verkaufbar']) ? (string)\sanitize_text_field((string) $_GET['cmx_verkaufbar']) : '0';
	$current_katalog = isset($_GET['cmx_katalog']) ? (string)\sanitize_text_field((string) $_GET['cmx_katalog']) : '0';

	if (!\function_exists(__NAMESPACE__ . '\\cmx_admin_post_type_column_is_visible') || cmx_admin_post_type_column_is_visible('artikel', 'lieferant')) {
		echo '<label class="screen-reader-text" for="cmx_lieferant">Lieferant filtern</label>';
		echo '<select name="cmx_lieferant" id="cmx_lieferant">';
		echo '<option value="0">'.esc_html__('Alle Lieferanten', 'default').'</option>';
		echo '<option value="-1"'.selected($current, '-1', false).'>- alle ohne -</option>';

		if ($lieferanten) {
			foreach ($lieferanten as $lid) {
				$title = \get_the_title($lid);
				if ($title === '') $title = '(ohne Titel #'.$lid.')';
				printf('<option value="%1$d"%2$s>%3$s</option>',
					(int)$lid,
					selected((string)$current, (string)$lid, false),
					esc_html($title)
				);
			}
		}
		echo '</select>';
	}

	if (!\function_exists(__NAMESPACE__ . '\\cmx_admin_post_type_column_is_visible') || cmx_admin_post_type_column_is_visible('artikel', 'verkaufbar')) {
		echo '<label class="screen-reader-text" for="cmx_verkaufbar">Verkaufbarkeit filtern</label>';
		echo '<select name="cmx_verkaufbar" id="cmx_verkaufbar">';
		echo '<option value="0"' . selected($current_verkaufbar, '0', false) . '>Alle Verkaufbarkeit</option>';
		echo '<option value="1"' . selected($current_verkaufbar, '1', false) . '>Nur verkaufbar</option>';
		echo '<option value="2"' . selected($current_verkaufbar, '2', false) . '>Nur NICHT verkaufbar</option>';
		echo '</select>';
	}

	if (!\function_exists(__NAMESPACE__ . '\\cmx_admin_post_type_column_is_visible') || cmx_admin_post_type_column_is_visible('artikel', 'katalog')) {
		echo '<label class="screen-reader-text" for="cmx_katalog">Katalog filtern</label>';
		echo '<select name="cmx_katalog" id="cmx_katalog">';
		echo '<option value="0"' . selected($current_katalog, '0', false) . '>Alle Katalogstatus</option>';
		echo '<option value="1"' . selected($current_katalog, '1', false) . '>Nur im Katalog</option>';
		echo '<option value="2"' . selected($current_katalog, '2', false) . '>Nur NICHT im Katalog</option>';
		echo '</select>';
	}
}, 10, 2);


/* =========================================================
 * Monats-Dropdown ausblenden (nur CPT artikel)
 * ========================================================= */
\add_filter('disable_months_dropdown', function ($disable, $post_type) {
	return ($post_type === 'artikel') ? true : $disable;
}, 10, 2);

/* =========================================================
 * Query anpassen (nur filtern, wenn Filter aktiv sind):
 * - Taxonomie-Filter (Slug ODER ID) via tax_query
 * - Lieferant per meta_query (exakte ID, Relation AND)
 * - Meta-Sortierungen: SKU, VK, EK, Marge
 * ========================================================= */
\add_action('pre_get_posts', function (\WP_Query $q) {
	if (!\is_admin() || !$q->is_main_query()) return;
	$post_types = (array)$q->get('post_type');
	if (!\in_array('artikel', $post_types, true)) return;

	/* ---- Welche Filter sind wirklich gesetzt? ---- */
	$tax_slugs = array_values(array_filter([
		cmx_tax_typen(),
		cmx_tax_kategorien(),
		cmx_tax_marken(),
		cmx_tax_farben(),
		cmx_tax_einheiten(),
	]));
	$tax_to_column = array_filter([
		cmx_tax_typen()      => 'typen',
		cmx_tax_kategorien() => 'kategorien',
		cmx_tax_marken()     => 'marken',
		cmx_tax_farben()     => 'farben',
		cmx_tax_einheiten()  => 'einheiten',
	]);

	$active_tax_filters = [];
	foreach ($tax_slugs as $tax) {
		if (\function_exists(__NAMESPACE__ . '\\cmx_admin_post_type_column_is_visible')) {
			$column = (string) ($tax_to_column[$tax] ?? '');
			if ($column !== '' && !cmx_admin_post_type_column_is_visible('artikel', $column)) {
				continue;
			}
		}
		if (isset($_GET[$tax]) && $_GET[$tax] !== '' && $_GET[$tax] !== '0') {
			$active_tax_filters[$tax] = $_GET[$tax];
		}
	}

	$lieferant_raw   = isset($_GET['cmx_lieferant']) ? (string)$_GET['cmx_lieferant'] : '0';
	$has_lieferant   = ($lieferant_raw !== '0'); // nur wenn explizit -1 oder >0
	$lieferant_id    = absint($lieferant_raw);
	$verkaufbar_raw  = isset($_GET['cmx_verkaufbar']) ? (string)\sanitize_text_field((string) $_GET['cmx_verkaufbar']) : '0';
	$katalog_raw     = isset($_GET['cmx_katalog']) ? (string)\sanitize_text_field((string) $_GET['cmx_katalog']) : '0';
	if (\function_exists(__NAMESPACE__ . '\\cmx_admin_post_type_column_is_visible')) {
		if (!cmx_admin_post_type_column_is_visible('artikel', 'lieferant')) {
			$lieferant_raw = '0';
			$has_lieferant = false;
			$lieferant_id = 0;
		}
		if (!cmx_admin_post_type_column_is_visible('artikel', 'verkaufbar')) {
			$verkaufbar_raw = '0';
		}
		if (!cmx_admin_post_type_column_is_visible('artikel', 'katalog')) {
			$katalog_raw = '0';
		}
	}

	/* ---- Nur dann tax_query bauen, wenn wirklich ein Tax-Filter gesetzt ist ---- */
	if (!empty($active_tax_filters)) {
		$tax_query = (array)$q->get('tax_query');
		foreach ($active_tax_filters as $tax => $val) {
			$field = 'slug';
			if (\is_numeric($val)) {
				$term = \get_term((int)$val, $tax);
				if ($term && !\is_wp_error($term)) {
					$val = $term->slug;
				} else {
					continue;
				}
			}
			$tax_query[] = [
				'taxonomy' => $tax,
				'field'    => $field,
				'terms'    => [$val],
			];
		}
		if (!empty($tax_query)) {
			if (!isset($tax_query['relation'])) {
				$tax_query = array_merge(['relation' => 'AND'], $tax_query);
			}
			$q->set('tax_query', $tax_query);
		}
	}

	/* ---- Lieferanten-Filter nur setzen, wenn -1 (ohne) oder >0 gewählt wurde ---- */
	if ($has_lieferant) {
		$meta_key   = CMX_ARTIKEL_META_LIEFERANT_ID;
		$meta_query = (array)$q->get('meta_query');

		if ($lieferant_raw === '-1') {
			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'     => $meta_key,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => $meta_key,
					'value'   => '',
					'compare' => '=',
				],
				[
					'key'     => $meta_key,
					'value'   => '0',
					'compare' => '=',
				],
				[
					'key'     => $meta_key,
					'value'   => 0,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
			];
		} elseif ($lieferant_id > 0) {
			$meta_query[] = [
				'key'     => $meta_key,
				'value'   => $lieferant_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			];
		}

		if (!isset($meta_query['relation'])) {
			$meta_query = array_merge(['relation' => 'AND'], $meta_query);
		}
		$q->set('meta_query', $meta_query);
	}

	/* ---- Verkaufbarkeit nur setzen, wenn explizit gefiltert wurde ---- */
	if ($verkaufbar_raw === '1' || $verkaufbar_raw === '2') {
		$meta_key   = CMX_ARTIKEL_META_VERKAUFBAR;
		$meta_query = (array) $q->get('meta_query');

		if ($verkaufbar_raw === '2') {
			$meta_query[] = [
				'key'     => $meta_key,
				'value'   => 1,
				'compare' => '=',
				'type'    => 'NUMERIC',
			];
		} else {
			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'     => $meta_key,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => $meta_key,
					'value'   => '',
					'compare' => '=',
				],
				[
					'key'     => $meta_key,
					'value'   => '0',
					'compare' => '=',
				],
				[
					'key'     => $meta_key,
					'value'   => 0,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
			];
		}

		if (!isset($meta_query['relation'])) {
			$meta_query = array_merge(['relation' => 'AND'], $meta_query);
		}
		$q->set('meta_query', $meta_query);
	}

	/* ---- Katalogsichtbarkeit nur setzen, wenn explizit gefiltert wurde ---- */
	if ($katalog_raw === '1' || $katalog_raw === '2') {
		$meta_key   = CMX_ARTIKEL_META_KATALOG;
		$meta_query = (array) $q->get('meta_query');

		if ($katalog_raw === '2') {
			$meta_query[] = [
				'key'     => $meta_key,
				'value'   => 0,
				'compare' => '=',
				'type'    => 'NUMERIC',
			];
		} else {
			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'     => $meta_key,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => $meta_key,
					'value'   => '',
					'compare' => '=',
				],
				[
					'key'     => $meta_key,
					'value'   => '1',
					'compare' => '=',
				],
				[
					'key'     => $meta_key,
					'value'   => 1,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
			];
		}

		if (!isset($meta_query['relation'])) {
			$meta_query = array_merge(['relation' => 'AND'], $meta_query);
		}
		$q->set('meta_query', $meta_query);
	}

	/* ---- Sortierung (beeinflusst keine Filter) ---- */
	$orderby = $q->get('orderby');

	// SKU: erkenne sowohl "orderby=sku" als auch "orderby=meta_value" ohne gesetzten meta_key
	if ($orderby === 'sku' || ($orderby === 'meta_value' && !$q->get('meta_key'))) {
		$q->set('meta_key', CMX_ARTIKEL_META_SKU);
		$q->set('meta_type', 'CHAR');         // behält führende Nullen
		$q->set('orderby', 'meta_value');     // bei rein numerischen SKUs ggf. meta_value_num
	}

	if ($orderby === 'vk') {
		$q->set('meta_key', CMX_ARTIKEL_META_VK);
		$q->set('meta_type', 'NUMERIC');
		$q->set('orderby', 'meta_value_num');
	}
	if ($orderby === 'ek') {
		$q->set('meta_key', CMX_ARTIKEL_META_EK);
		$q->set('meta_type', 'NUMERIC');
		$q->set('orderby', 'meta_value_num');
	}
	if ($orderby === 'marge') {
		$q->set('meta_key', CMX_ARTIKEL_META_MARGE);
		$q->set('meta_type', 'NUMERIC');
		$q->set('orderby', 'meta_value_num');
	}
}, 10);


/* =========================================================
 * Komplexe Sortierungen via JOIN (Marke, Lieferant) + SKU stabilisieren
 * ========================================================= */
\add_filter('posts_clauses', function (array $clauses, \WP_Query $q) {
	if (!\is_admin() || !$q->is_main_query()) return $clauses;
	$post_types = (array)$q->get('post_type');
	if (!\in_array('artikel', $post_types, true)) return $clauses;

	global $wpdb;

	// Marke sortieren
	if ($q->get('orderby') === 'marke') {
		$tax = cmx_tax_marken();
		if ($tax) {
			if (strpos($clauses['join'], 'tr_m.object_id') === false) {
				$clauses['join'] .= " LEFT JOIN {$wpdb->term_relationships} tr_m ON ({$wpdb->posts}.ID = tr_m.object_id)";
				$clauses['join'] .= " LEFT JOIN {$wpdb->term_taxonomy} tt_m ON (tr_m.term_taxonomy_id = tt_m.term_taxonomy_id AND tt_m.taxonomy = '{$tax}')";
				$clauses['join'] .= " LEFT JOIN {$wpdb->terms} t_m ON (tt_m.term_id = t_m.term_id)";
			}
			$order = (strtoupper($q->get('order')) === 'ASC') ? 'ASC' : 'DESC';
			if (empty($clauses['groupby'])) $clauses['groupby'] = "{$wpdb->posts}.ID";
			$clauses['orderby'] = "COALESCE(MIN(t_m.name), '') {$order}, {$wpdb->posts}.ID {$order}";
		}
	}

	// Lieferant sortieren
	if ($q->get('orderby') === 'lieferant') {
		$meta_key = CMX_ARTIKEL_META_LIEFERANT_ID;
		if (strpos($clauses['join'], 'pm_lief.post_id') === false) {
			$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} pm_lief ON ({$wpdb->posts}.ID = pm_lief.post_id AND pm_lief.meta_key = '{$meta_key}')";
		}
		if (strpos($clauses['join'], 'p_lief.ID') === false) {
			$clauses['join'] .= " LEFT JOIN {$wpdb->posts} p_lief ON (p_lief.ID = CAST(pm_lief.meta_value AS UNSIGNED))";
		}
		$order = (strtoupper($q->get('order')) === 'ASC') ? 'ASC' : 'DESC';
		if (empty($clauses['groupby'])) $clauses['groupby'] = "{$wpdb->posts}.ID";
		$clauses['orderby'] = "COALESCE(MIN(p_lief.post_title), '') {$order}, {$wpdb->posts}.ID {$order}";
	}

	// Verkaufbar sortieren (kompatible Altlogik: 0/leer = verkaufbar, 1 = NICHT verkaufbar)
	if ($q->get('orderby') === 'verkaufbar') {
		$meta_key = CMX_ARTIKEL_META_VERKAUFBAR;
		if (strpos($clauses['join'], 'pm_verk.post_id') === false) {
			$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} pm_verk ON ({$wpdb->posts}.ID = pm_verk.post_id AND pm_verk.meta_key = '{$meta_key}')";
		}
		$order = (strtoupper($q->get('order')) === 'DESC') ? 'DESC' : 'ASC';
		if (empty($clauses['groupby'])) {
			$clauses['groupby'] = "{$wpdb->posts}.ID";
		}
		$clauses['orderby'] = "CAST(COALESCE(NULLIF(pm_verk.meta_value, ''), '0') AS UNSIGNED) {$order}, {$wpdb->posts}.post_title {$order}";
	}

	// Katalog sortieren (Default: im Katalog, wenn Meta fehlt)
	if ($q->get('orderby') === 'katalog') {
		$meta_key = CMX_ARTIKEL_META_KATALOG;
		if (strpos($clauses['join'], 'pm_kat.post_id') === false) {
			$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} pm_kat ON ({$wpdb->posts}.ID = pm_kat.post_id AND pm_kat.meta_key = '{$meta_key}')";
		}
		$order = (strtoupper($q->get('order')) === 'DESC') ? 'DESC' : 'ASC';
		if (empty($clauses['groupby'])) {
			$clauses['groupby'] = "{$wpdb->posts}.ID";
		}
		$visibility_case = "CASE WHEN pm_kat.meta_id IS NULL OR pm_kat.meta_value = '' OR CAST(pm_kat.meta_value AS UNSIGNED) = 1 THEN 0 ELSE 1 END";
		$clauses['orderby'] = "{$visibility_case} {$order}, {$wpdb->posts}.post_title {$order}";
	}

	// SKU: Sekundärsortierung nach Titel ergänzen (stabil)
	if ( ($q->get('orderby') === 'meta_value' || $q->get('orderby') === 'meta_value_num' || $q->get('orderby') === 'sku')
		&& $q->get('meta_key') === CMX_ARTIKEL_META_SKU ) {
		$dir = strtoupper($q->get('order') === 'DESC' ? 'DESC' : 'ASC');
		if (!empty($clauses['orderby']) && stripos($clauses['orderby'], 'post_title') === false) {
			$clauses['orderby'] = rtrim($clauses['orderby']) . ", {$wpdb->posts}.post_title {$dir}";
		}
	}

	return $clauses;
}, 10, 2);


/**
 * Ergänzung: Lieferanten-Select (#cmx_lieferant) um ALLE realen Lieferanten erweitern
 * - Quellen:
 *   a) cmx_lieferanten_args()  (deine bestehende Ermittlung)
 *   b) in Artikeln referenzierte Lieferanten (Meta: CMX_ARTIKEL_META_LIEFERANT_ID)
 * - Vorgehen:
 *   1) Serverseitig Hidden-Select mit allen fehlenden Lieferanten-Optionen ausgeben
 *   2) Clientseitig per JS in das bestehende #cmx_lieferant mergen (ohne Duplikate)
 *   3) Optionen (ab Index 2) alphabetisch sortieren; aktuelle Auswahl bleibt erhalten
 */

\add_action('restrict_manage_posts', function (string $post_type, string $which) {
	if ($post_type !== 'artikel' || $which !== 'top') return;
	if (\function_exists(__NAMESPACE__ . '\\cmx_admin_post_type_column_is_visible') && !cmx_admin_post_type_column_is_visible('artikel', 'lieferant')) return;

	global $wpdb;

	// a) Bereits bekannte Lieferanten (dein Feed)
	$ids_a = \get_posts(\array_merge(cmx_lieferanten_args(), ['fields' => 'ids']));

	// b) In Artikeln verwendete Lieferanten-IDs
	$meta_key = CMX_ARTIKEL_META_LIEFERANT_ID;
	$ids_b = $wpdb->get_col(
		$wpdb->prepare("
			SELECT DISTINCT CAST(pm.meta_value AS UNSIGNED)
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = %s
			  AND pm.meta_value REGEXP '^[0-9]+$'
			  AND CAST(pm.meta_value AS UNSIGNED) > 0
			  AND p.post_type = 'artikel'
			  AND p.post_status IN ('publish','draft','pending','future','private')
		", $meta_key)
	);

	$ids = \array_values(\array_unique(\array_map('intval', \array_merge($ids_a ?: [], $ids_b ?: []))));
	if (empty($ids)) return;

	// Hidden-Select mit fehlenden Optionen
	echo '<select id="cmx_lieferant_extra" style="display:none" aria-hidden="true">';
	foreach ($ids as $cid) {
		$title = \get_the_title($cid);
		if ($title === '') $title = '(ohne Titel #'.$cid.')';
		printf('<option value="%1$d">%2$s</option>', (int)$cid, esc_html($title));
	}
	echo '</select>';
}, 99, 2);


\add_action('admin_footer-edit.php', function () {
	if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'artikel') return;

	$current = isset($_GET['cmx_lieferant']) ? (string)$_GET['cmx_lieferant'] : '';
	?>
	<script>
	(function(){
		var sel = document.getElementById('cmx_lieferant');
		var extra = document.getElementById('cmx_lieferant_extra');
		if (!sel || !extra) return;

		// Stelle sicher, dass Option "— Kein Lieferant —" vorhanden bleibt (value = -1)
		var hasNone = false;
		for (var i=0;i<sel.options.length;i++){
			if (sel.options[i].value === '-1') { hasNone = true; break; }
		}
		if (!hasNone) {
			var noneOpt = document.createElement('option');
			noneOpt.value = '-1';
			noneOpt.textContent = '— Kein Lieferant —';
			try { sel.add(noneOpt, sel.options[1] || null); } catch(e){ sel.appendChild(noneOpt); }
		}

		// Existierende Werte merken (zur Duplikat-Vermeidung)
		var existing = new Set(Array.prototype.map.call(sel.options, function(o){ return o.value; }));

		// Zusätzliche Lieferanten aus hidden-Select hinzufügen, falls nicht vorhanden
		Array.prototype.forEach.call(extra.options, function(opt){
			if (!existing.has(opt.value)) {
				sel.appendChild(opt.cloneNode(true));
			}
		});

		// Ab Index 2 (nach "Alle Lieferanten" und "— Kein Lieferant —") alphabetisch sortieren
		var staticCount = 2; // 0: Alle, 1: Kein
		var tail = Array.prototype.slice.call(sel.options, staticCount);
		tail.sort(function(a,b){ return a.text.localeCompare(b.text, undefined, {sensitivity:'base'}); });
		// Neu zusammensetzen
		while (sel.options.length > staticCount) sel.remove(staticCount);
		tail.forEach(function(o){ sel.appendChild(o); });

		// Auswahl beibehalten
		if (<?php echo json_encode($current); ?> !== '') {
			sel.value = <?php echo json_encode($current); ?>;
		}
	})();
	</script>
	<?php
});


/* ===== Fix 1: sehr spät – SKU-Sortierung ankündigen & INNER JOIN neutralisieren ===== */
\add_action('pre_get_posts', function (\WP_Query $q) {
	if (!\is_admin() || !$q->is_main_query()) return;
	$post_types = (array) $q->get('post_type');
	if (!\in_array('artikel', $post_types, true)) return;

	$orderby  = $q->get('orderby');
	$meta_key = $q->get('meta_key');

	// Greift sowohl auf Klick "Artikel-Nr." (orderby=sku) als auch falls vorher meta_key auf SKU gesetzt wurde
	if ($orderby === 'sku' || $meta_key === CMX_ARTIKEL_META_SKU) {
		$q->set('cmx_force_leftjoin_sku', 1);  // Flag für posts_clauses
		$q->set('meta_key', null);             // INNER JOIN vermeiden
		$q->set('meta_type', null);
		if ($orderby !== 'title') {
			$q->set('orderby', 'title');       // Dummy; echte Sortierung folgt unten
		}
	}
}, 9999);


/* ===== Fix 2: sehr spät – echte SKU-Sortierung via LEFT JOIN (kein Filterverlust) ===== */
\add_filter('posts_clauses', function (array $clauses, \WP_Query $q) {
	if (!\is_admin() || !$q->is_main_query()) return $clauses;
	$post_types = (array) $q->get('post_type');
	if (!\in_array('artikel', $post_types, true)) return $clauses;
	if ((int) $q->get('cmx_force_leftjoin_sku') !== 1) return $clauses;

	global $wpdb;
	$meta_key = CMX_ARTIKEL_META_SKU;
	$order    = (strtoupper($q->get('order')) === 'DESC') ? 'DESC' : 'ASC';

	// LEFT JOIN auf SKU-Meta
	if (strpos($clauses['join'] ?? '', 'pm_sku.post_id') === false) {
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} pm_sku
			ON ({$wpdb->posts}.ID = pm_sku.post_id AND pm_sku.meta_key = '{$meta_key}')";
	}

	// GROUP BY absichern
	if (empty($clauses['groupby'])) {
		$clauses['groupby'] = "{$wpdb->posts}.ID";
	}

	// ORDER BY: erst SKU (als Text, behält führende Nullen), dann Titel
	$clauses['orderby'] = "COALESCE(pm_sku.meta_value, '') {$order}, {$wpdb->posts}.post_title {$order}";

	return $clauses;
}, 9999, 2);

\add_filter('views_edit-artikel', function(array $views): array {
	return cmx_admin_deckungsbeitrag_add_view($views, 'artikel', 'artikel');
}, 30);

\add_filter('manage_edit-artikel_columns', function(array $columns): array {
	if (!cmx_admin_deckungsbeitrag_view_active('artikel')) {
		return $columns;
	}

	return cmx_admin_deckungsbeitrag_insert_column($columns, 'cmx_deckungsbeitrag', 'Deckungsbeitrag');
}, 900);

\add_action('manage_artikel_posts_custom_column', function(string $column, int $post_id): void {
	if ($column !== 'cmx_deckungsbeitrag' || !cmx_admin_deckungsbeitrag_view_active('artikel')) {
		return;
	}

	cmx_admin_deckungsbeitrag_render_value('artikel', $post_id);
}, 20, 2);

\add_action('pre_get_posts', function(\WP_Query $query): void {
	cmx_admin_deckungsbeitrag_apply_query_sort($query, 'artikel', 'artikel');
}, 99999);

\add_filter('posts_search', function (string $search, \WP_Query $q): string {
	if (!\is_admin() || !$q->is_main_query()) {
		return $search;
	}
	$post_types = (array) $q->get('post_type');
	if (!\in_array('artikel', $post_types, true)) {
		return $search;
	}
	$term = \trim((string) $q->get('s'));
	if ($term === '') {
		return $search;
	}
	$match_ids = cmx_artikel_admin_variant_search_ids($term);
	if (empty($match_ids)) {
		return $search;
	}

	global $wpdb;
	$id_sql = $wpdb->posts . '.ID IN (' . \implode(',', \array_map('intval', $match_ids)) . ')';
	$search = \trim($search);
	if ($search === '') {
		return ' AND (' . $id_sql . ')';
	}
	if (\stripos($search, 'AND ') === 0) {
		$search = \trim((string) \substr($search, 4));
	}
	return ' AND ((' . $search . ') OR ' . $id_sql . ')';
}, 30, 2);

\add_action('admin_footer-edit.php', function (): void {
	if (!isset($_GET['post_type']) || (string) $_GET['post_type'] !== 'artikel') {
		return;
	}

	global $wp_query;
	$payload = [];
	foreach ((array) ($wp_query->posts ?? []) as $post) {
		$post_id = (int) ($post->ID ?? 0);
		if ($post_id <= 0) {
			continue;
		}
		$entries = cmx_artikel_admin_variant_entries($post_id);
		if (\count($entries) <= 1) {
			continue;
		}
		$edit_url = (string) \admin_url('post.php?post=' . $post_id . '&action=edit');
		$rows = [];
		foreach (\array_slice($entries, 1) as $entry) {
			$rows[] = [
				'index' => (int) ($entry['index'] ?? 0),
				'title' => (string) ($entry['title'] ?? ''),
				'sku' => (string) ($entry['sku'] ?? ''),
				'farben' => (string) ($entry['farben'] ?? ''),
				'einheit' => (string) ($entry['einheit'] ?? ''),
				'vk' => (string) ($entry['vk'] ?? ''),
				'ek' => (string) ($entry['ek'] ?? ''),
				'marge' => (string) ($entry['marge'] ?? ''),
				'verkaufbar_html' => !empty($entry['verkaufbar'])
					? '<span title="Verkaufbar" aria-hidden="true">&#10003;</span><span class="screen-reader-text">Verkaufbar</span>'
					: '<span title="Nicht verkaufbar" aria-hidden="true"></span><span class="screen-reader-text">Nicht verkaufbar</span>',
				'katalog_html' => !empty($entry['katalog'])
					? '<span title="Im Katalog" aria-hidden="true">&#10003;</span><span class="screen-reader-text">Im Katalog</span>'
					: '<span title="Nicht im Katalog" aria-hidden="true"></span><span class="screen-reader-text">Nicht im Katalog</span>',
				'edit_url' => $edit_url,
			];
		}
		if (!empty($rows)) {
			$payload[$post_id] = $rows;
		}
	}

	if (empty($payload)) {
		return;
	}
	$json = \wp_json_encode($payload);
	if (!\is_string($json) || $json === '') {
		return;
	}
	?>
	<script>
	(function(){
		const variantsByPost = <?php echo $json; ?>;
		const setText = function(row, selector, value){
			const cell = row.querySelector(selector);
			if (!cell) return;
			cell.textContent = value || "";
		};
		const setHtml = function(row, selector, html){
			const cell = row.querySelector(selector);
			if (!cell) return;
			cell.innerHTML = html || "";
		};
		const setTitle = function(row, variant){
			const titleCell = row.querySelector(".column-title");
			if (!titleCell) return;
			const titleLink = titleCell.querySelector(".row-title");
			if (titleLink) {
				titleLink.textContent = variant.title || "";
				titleLink.href = variant.edit_url || "#";
				titleLink.title = variant.title || "";
			}
			const actions = titleCell.querySelector(".row-actions");
			if (actions) {
				actions.innerHTML = '<span class="edit"><a href="' + (variant.edit_url || "#") + '">Bearbeiten</a></span>';
			}
		};

		Object.keys(variantsByPost).forEach(function(postId){
			const baseRow = document.getElementById("post-" + postId);
			if (!baseRow || !baseRow.parentNode) return;
			let insertAfter = baseRow;
			(variantsByPost[postId] || []).forEach(function(variant){
				const clone = baseRow.cloneNode(true);
				clone.id = "cmx-artikel-variant-" + postId + "-" + String(variant.index || "0");
				clone.classList.add("cmx-artikel-variant-row");

				const checkCell = clone.querySelector("th.check-column, td.check-column");
				if (checkCell) {
					checkCell.innerHTML = "";
				}

				setTitle(clone, variant);
				setText(clone, ".column-sku", variant.sku);
				setText(clone, ".column-farben", variant.farben);
				setText(clone, ".column-einheiten", variant.einheit);
				setText(clone, ".column-vk", variant.vk);
				setText(clone, ".column-ek", variant.ek);
				setText(clone, ".column-marge", variant.marge);
				setHtml(clone, ".column-verkaufbar", variant.verkaufbar_html);
				setHtml(clone, ".column-katalog", variant.katalog_html);

				insertAfter.parentNode.insertBefore(clone, insertAfter.nextSibling);
				insertAfter = clone;
			});
		});
	})();
	</script>
	<?php
});
