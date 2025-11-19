<?php
/**
 * File: artikel-admin-list.php
 * Admin-Liste: Spalten + Inhalte + Filter (für CPT "artikel")
 *
 * - Taxonomie-Filter robust (ID oder Slug) -> tax_query
 * - Lieferanten-Filter -> meta_query (behält bestehende Queries, Relation AND)
 * - Sortierungen: SKU, Marke (JOIN), Lieferant (JOIN), VK/EK/Marge numerisch
 */
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit;

/* =========================================================
 * Defensive Konstanten (nur definieren, falls noch nicht)
 * ========================================================= */
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_SKU'))            \define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_SKU', '_cmx_artikel_sku');
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_EK'))             \define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_EK', '_cmx_artikel_ek');
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VK'))             \define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VK', '_cmx_artikel_vk');
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_MARGE'))          \define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_MARGE', '_cmx_artikel_marge');
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_BEZUGSQUELLE'))   \define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_BEZUGSQUELLE', '_cmx_artikel_bezugsquelle_url');
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_LIEFERANT_ID'))   \define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_LIEFERANT_ID', '_cmx_artikel_lieferant_id');

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

function cmx_kontakte_cpt(): string {
	if (\post_type_exists('kontakte')) return 'kontakte';
	if (\post_type_exists('kontakt'))  return 'kontakt';
	return 'kontakte';
}

/** Lieferanten-Query-Args (optional eingeschränkt, falls Kennzeichnung existiert) */
function cmx_lieferanten_args(): array {
	$args = [
		'post_type'      => cmx_kontakte_cpt(),
		'post_status'    => 'any',
		'posts_per_page' => 500,
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
				$out[] = '<a href="'.\esc_url($url).'">'.\esc_html($t->name).'</a>';
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
			echo \esc_html(\number_format($vk, 2));
			break;

		case 'ek':
			$ek = (float)\get_post_meta($post_id, CMX_ARTIKEL_META_EK, true);
			echo \esc_html(\number_format($ek, 2));
			break;

		case 'marge':
			$m = (float)\get_post_meta($post_id, CMX_ARTIKEL_META_MARGE, true);
			echo \esc_html(\number_format($m, 2));
			break;

		case 'hersteller_url':
			$url = \trim(\get_post_meta($post_id, CMX_ARTIKEL_META_BEZUGSQUELLE, true));
			echo $url
				? '<a href="' . \esc_url($url) . '" target="_blank" rel="noopener noreferrer" title="Hersteller-Website" style="text-decoration:none;"><span class="dashicons dashicons-admin-site-alt3" style="font-size:18px;vertical-align:middle;"></span></a>'
				: '';
			break;

		case 'featimg':
			$edit_link = \get_edit_post_link($post_id, '');
			if (\has_post_thumbnail($post_id)) {
				echo $edit_link
					? '<a href="'.\esc_url($edit_link).'#postimagediv" title="Beitragsbild vorhanden" style="text-decoration:none;"><span class="dashicons dashicons-yes" style="font-size:18px;vertical-align:middle;"></span></a>'
					: '<span class="dashicons dashicons-yes" title="Beitragsbild vorhanden" style="font-size:18px;vertical-align:middle;"></span>';
			} else {
				echo '';
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
	$cols['hersteller_url'] = 'hersteller_url';
	$cols['featimg']        = 'featimg';
	return $cols;
});

/* =========================================================
 * Filter-Dropdowns (oben) inkl. Lieferant
 * ========================================================= */
\add_action('restrict_manage_posts', function (string $post_type, string $which) {
	if ($post_type !== 'artikel' || $which !== 'top') return;

	$taxes = array_filter([
		cmx_tax_typen()      => 'Typen',
		cmx_tax_kategorien() => 'Kategorien',
		cmx_tax_marken()     => 'Marken',
		cmx_tax_farben()     => 'Farben',
		cmx_tax_einheiten()  => 'Einheiten',
	]);

	foreach ($taxes as $tax => $label) {
		$selected = isset($_GET[$tax]) ? sanitize_text_field($_GET[$tax]) : '';
		\wp_dropdown_categories([
			'show_option_all' => 'Alle '.$label,
			'taxonomy'        => $tax,
			'name'            => $tax,
			'orderby'         => 'name',
			'selected'        => $selected,
			'hierarchical'    => true,
			'hide_empty'      => false,
			'value_field'     => 'slug', // liefert Slugs; unten in parse_query abgesichert
		]);
	}

	$lieferanten = \get_posts(cmx_lieferanten_args());
	$current = isset($_GET['cmx_lieferant']) ? absint($_GET['cmx_lieferant']) : 0;

	echo '<label class="screen-reader-text" for="cmx_lieferant">Lieferant filtern</label>';
	echo '<select name="cmx_lieferant" id="cmx_lieferant">';
	echo '<option value="0">'.esc_html__('Alle Lieferanten', 'default').'</option>';

	if ($lieferanten) {
		foreach ($lieferanten as $lid) {
			$title = \get_the_title($lid);
			if ($title === '') $title = '(ohne Titel #'.$lid.')';
			printf('<option value="%1$d"%2$s>%3$s</option>',
				(int)$lid,
				selected($current, (int)$lid, false),
				esc_html($title)
			);
		}
	}
	echo '</select>';
}, 10, 2);

/* =========================================================
 * Monats-Dropdown ausblenden (nur CPT artikel)
 * ========================================================= */
\add_filter('disable_months_dropdown', function ($disable, $post_type) {
	return ($post_type === 'artikel') ? true : $disable;
}, 10, 2);

/* =========================================================
 * Query anpassen:
 * - Taxonomie-Filter (Slug ODER ID) via tax_query
 * - Lieferant per meta_query (exakte ID, Relation AND)
 * - Meta-Sortierungen: SKU, VK, EK, Marge
 * ========================================================= */
\add_action('parse_query', function (\WP_Query $q) {
	if (!\is_admin() || !$q->is_main_query()) return;
	$post_types = (array)$q->get('post_type');
	if (!\in_array('artikel', $post_types, true)) return;

	/* ---- Taxonomie-Filter robust via tax_query ---- */
	$tax_map = array_filter([
		cmx_tax_typen(),
		cmx_tax_kategorien(),
		cmx_tax_marken(),
		cmx_tax_farben(),
		cmx_tax_einheiten(),
	]);

	$tax_query = (array)$q->get('tax_query');
	foreach ($tax_map as $tax) {
		$val = isset($_GET[$tax]) ? $_GET[$tax] : '';
		if ($val === '' || $val === '0') continue;

		$field = 'slug';
		if (\is_numeric($val)) {
			$term = \get_term((int)$val, $tax);
			if ($term && !\is_wp_error($term)) {
				$val = $term->slug;
			} else {
				// Sicherheit: falls ID ungültig, Überspringen
				continue;
			}
		}
		$tax_query[] = [
			'taxonomy' => $tax,
			'field'    => $field,   // wir setzen auf Slug
			'terms'    => [$val],
		];
	}
	if (!empty($tax_query)) {
		// Bestehende tax_query übernehmen, Relation standardmäßig AND
		if (!isset($tax_query['relation'])) {
			$tax_query = array_merge(['relation' => 'AND'], $tax_query);
		}
		$q->set('tax_query', $tax_query);
	}

	/* ---- Lieferanten-Filter ---- */
	$lieferant = isset($_GET['cmx_lieferant']) ? absint($_GET['cmx_lieferant']) : 0;
	if ($lieferant > 0) {
		$meta_query = (array)$q->get('meta_query');
		$meta_query[] = [
			'key'     => CMX_ARTIKEL_META_LIEFERANT_ID,
			'value'   => $lieferant,
			'compare' => '=',
			'type'    => 'NUMERIC',
		];
		if (!isset($meta_query['relation'])) {
			$meta_query = array_merge(['relation' => 'AND'], $meta_query);
		}
		$q->set('meta_query', $meta_query);
	}

	/* ---- Meta-Sortierungen ---- */
	$orderby = $q->get('orderby');
	if ($orderby === 'sku') {
		$q->set('meta_key', CMX_ARTIKEL_META_SKU);
		$q->set('orderby', 'meta_value'); // alphanumerisch
	}
	if ($orderby === 'vk') {
		$q->set('meta_key', CMX_ARTIKEL_META_VK);
		$q->set('orderby', 'meta_value_num');
	}
	if ($orderby === 'ek') {
		$q->set('meta_key', CMX_ARTIKEL_META_EK);
		$q->set('orderby', 'meta_value_num');
	}
	if ($orderby === 'marge') {
		$q->set('meta_key', CMX_ARTIKEL_META_MARGE);
		$q->set('orderby', 'meta_value_num');
	}
}, 10);

/* =========================================================
 * Komplexe Sortierungen via JOIN (Marke, Lieferant)
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

	return $clauses;
}, 10, 2);
