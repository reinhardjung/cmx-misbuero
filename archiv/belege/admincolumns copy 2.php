<?php
namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Belege-Adminliste: Filter & Spalten für Kontakte und Projekte
 * - Zeigt Dropdowns immer an (auch ohne Taxonomien / Meta-Werte).
 * - Kontakte: liest CPT "kontakte" (Fallback: nur verwendete IDs).
 * - Projekte: liest CPT "projekte" (Fallback: nur verwendete Meta-Werte) UND optional Projekt-Taxonomien.
 * - Filter: OR über alle bekannten Kontakt-/Projekt-Meta-Keys; zusätzlich Tax-Filter wenn vorhanden.
 */

/** --- Konfiguration / Helfer ------------------------------------------------ */

function cmx_belege_cpt_slug(): string { return 'belege'; }
function cmx_kontakte_cpt_slug(): string { return 'kontakte'; }
function cmx_projekte_cpt_slug(): string { return 'projekte'; }

/** Kontakt-Metakeys (erste Übereinstimmung gewinnt / OR im Filter) */
function cmx_meta_keys_kontakt(): array {
	return [
		'_cmx_beleg_kontakt',
		'_cmx_kontakt',
		'_cmx_beleg_contact',
		'_beleg_kontakt',
		'_kontakt_id',
		'_cmx_contact_id',
	];
}

/** Projekt-Metakeys (OR im Filter) */
function cmx_meta_keys_projekt(): array {
	return [
		'_cmx_beleg_projekt_id', // bevorzugt ID
		'_cmx_projekt_id',
		'_projekt_id',
		'_cmx_beleg_projekt',    // String/Slug/Name
	];
}

/** Kategorie-/Projekt-Taxonomien (breit erkennen) */
function cmx_tax_belege_kategorien(): string {
	foreach (['beleg_kategorie','belege_kategorien','beleg_kategorien','belege_kategorie'] as $tax) {
		if (\taxonomy_exists($tax)) return $tax;
	}
	return '';
}
function cmx_tax_belege_projekte(): string {
	foreach (['belege_projekte','beleg_projekte','belege_projekt','beleg_projekt','projekt_kategorie'] as $tax) {
		if (\taxonomy_exists($tax)) return $tax;
	}
	return '';
}

/** verwendete Projekt-Meta-Werte (Fallback) */
function cmx_used_project_meta_values(): array {
	global $wpdb;
	$keys = cmx_meta_keys_projekt();
	$placeholders = implode(',', array_fill(0, count($keys), '%s'));
	$sql = "
		SELECT DISTINCT pm.meta_value
		FROM {$wpdb->postmeta} pm
		INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		WHERE pm.meta_key IN ($placeholders)
		  AND p.post_type = %s
		  AND p.post_status NOT IN ('auto-draft','trash')
		ORDER BY pm.meta_value ASC
	";
	$params = $keys;
	$params[] = cmx_belege_cpt_slug();
	$values = $wpdb->get_col($wpdb->prepare($sql, ...$params));
	return array_values(array_filter(array_map('trim', (array) $values)));
}

/** verwendete Kontakt-IDs (Fallback) */
function cmx_used_contact_ids(): array {
	global $wpdb;
	$ids = [];
	foreach (cmx_meta_keys_kontakt() as $k) {
		$col = $wpdb->get_col($wpdb->prepare("
			SELECT DISTINCT pm.meta_value
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = %s
			  AND p.post_type = %s
			  AND p.post_status NOT IN ('auto-draft','trash')", $k, cmx_belege_cpt_slug()));
		if ($col) {
			foreach ($col as $v) {
				$v = (int)$v;
				if ($v > 0) $ids[$v] = true;
			}
		}
	}
	return array_keys($ids);
}

/** Term-Edit Link */
function cmx_term_edit_link(string $tax, int $term_id): string {
	return \add_query_arg(['post_type' => cmx_belege_cpt_slug(),'taxonomy'=>$tax,'tag_ID'=>$term_id], \admin_url('term.php'));
}

/** --- UI: Monatsfilter entfernen ------------------------------------------- */
\add_filter('months_dropdown_results', function($months, $post_type){
	return ($post_type === cmx_belege_cpt_slug()) ? [] : $months;
}, 10, 2);

/** --- UI: Filterleisten-Controls ------------------------------------------ */
\add_action('restrict_manage_posts', function($post_type = '', $which = ''){
	$screen = function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || $screen->id !== 'edit-'.cmx_belege_cpt_slug()) return;

	/* Kategorien (Tax) */
	if ($tax_cat = cmx_tax_belege_kategorien()) {
		$selected = $_GET[$tax_cat] ?? '';
		\wp_dropdown_categories([
			'show_option_all' => __('Alle Kategorien', 'default'),
			'taxonomy'        => $tax_cat,
			'name'            => $tax_cat,
			'orderby'         => 'name',
			'selected'        => $selected,
			'hierarchical'    => true,
			'show_count'      => false,
			'hide_empty'      => false,
		]);
	}

	/* Projekte (immer zeigen) */
	$current_proj = isset($_GET['cmx_proj']) ? sanitize_text_field(wp_unslash($_GET['cmx_proj'])) : '';
	echo '<select name="cmx_proj">';
	echo '<option value="">' . esc_html__('Alle Projekte', 'default') . '</option>';

	// Primär: CPT projekte (vollständige Liste)
	$proj_posts = \get_posts([
		'post_type'      => cmx_projekte_cpt_slug(),
		'post_status'    => 'any',
		'posts_per_page' => 500,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	]);
	if (!empty($proj_posts)) {
		foreach ($proj_posts as $pid) {
			$title = \get_the_title($pid) ?: ('#'.$pid);
			echo '<option value="'.esc_attr($pid).'"'.selected($current_proj,(string)$pid,false).'>'.esc_html($title).' (#'.$pid.')</option>';
		}
	} else {
		// Fallback: verwendete Meta-Werte
		foreach (cmx_used_project_meta_values() as $val) {
			echo '<option value="'.esc_attr($val).'"'.selected($current_proj,$val,false).'>'.esc_html($val).'</option>';
		}
	}
	echo '</select>';

	/* Kontakte (immer zeigen) */
	$current_kontakt = isset($_GET['cmx_kontakt']) ? (int) $_GET['cmx_kontakt'] : 0;
	echo '<select name="cmx_kontakt">';
	echo '<option value="">' . esc_html__('Alle Kontakte', 'default') . '</option>';

	// Primär: CPT kontakte (vollständige Liste)
	$kontakt_posts = \get_posts([
		'post_type'      => cmx_kontakte_cpt_slug(),
		'post_status'    => 'any',
		'posts_per_page' => 500,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	]);
	if (!empty($kontakt_posts)) {
		foreach ($kontakt_posts as $pid) {
			$title = \get_the_title($pid) ?: ('Kontakt #'.$pid);
			echo '<option value="'.esc_attr($pid).'"'.selected($current_kontakt,$pid,false).'>'.esc_html($title).' (#'.$pid.')</option>';
		}
	} else {
		// Fallback: nur verwendete IDs
		foreach (cmx_used_contact_ids() as $pid) {
			$title = \get_the_title($pid) ?: ('Kontakt #'.$pid);
			echo '<option value="'.esc_attr($pid).'"'.selected($current_kontakt,$pid,false).'>'.esc_html($title).' (#'.$pid.')</option>';
		}
	}
	echo '</select>';

	/* Mini-Debug (oben links, kann entfernt werden) */
	$tax_proj = cmx_tax_belege_projekte();
	printf(
		'<span style="margin-left:8px;color:#888;">[DBG] Tax(Kat):%s | Tax(Proj):%s | CPT(kontakte):%s | CPT(projekte):%s</span>',
		$tax_cat ?: '—',
		$tax_proj ?: '—',
		\post_type_exists(cmx_kontakte_cpt_slug()) ? 'ok' : '—',
		\post_type_exists(cmx_projekte_cpt_slug()) ? 'ok' : '—'
	);
}, 20, 2);

/** --- Query-Filter anwenden ----------------------------------------------- */
\add_action('pre_get_posts', function($q){
	if (!\is_admin() || !$q->is_main_query() || $q->get('post_type') !== cmx_belege_cpt_slug()) return;

	$tax_query = (array) $q->get('tax_query');
	$meta_query = (array) $q->get('meta_query');

	/* Kategorie per Tax */
	if ($tax_cat = cmx_tax_belege_kategorien()) {
		if (!empty($_GET[$tax_cat]) && (int)$_GET[$tax_cat] > 0) {
			$tax_query[] = [
				'taxonomy' => $tax_cat,
				'field'    => 'term_id',
				'terms'    => (int) $_GET[$tax_cat],
			];
		}
	}

	/* Projekt-Filter:
	 * - Wenn in cmx_proj eine Zahl => als ID behandeln und OR über Projekt-ID-Metakeys
	 * - Zusätzlich: falls Projekt-Tax existiert und ein Term gewählt wurde (separat über Dropdown der Tax), greifen wir oben via tax_query
	 * - Wenn cmx_proj ein String ist (Fallback), dann OR über String-Keys
	 */
	if (!empty($_GET['cmx_proj'])) {
		$val = sanitize_text_field(wp_unslash($_GET['cmx_proj']));
		$or = ['relation' => 'OR'];
		if (ctype_digit($val)) {
			// ID-basierte Keys
			foreach (['_cmx_beleg_projekt_id','_cmx_projekt_id','_projekt_id'] as $k) {
				$or[] = ['key' => $k, 'value' => (string)(int)$val, 'compare' => '='];
			}
			// optional: manche speichern die ID fälschlich in '_cmx_beleg_projekt'
			$or[] = ['key' => '_cmx_beleg_projekt', 'value' => (string)(int)$val, 'compare' => '='];
		} else {
			// String-basiert
			foreach (['_cmx_beleg_projekt'] as $k) {
				$or[] = ['key' => $k, 'value' => $val, 'compare' => '='];
			}
		}
		$meta_query[] = $or;
	}

	/* Kontakt-Filter: OR über alle bekannten Keys (ID) */
	if (!empty($_GET['cmx_kontakt']) && (int)$_GET['cmx_kontakt'] > 0) {
		$kontakt_id = (string)(int)$_GET['cmx_kontakt'];
		$or = ['relation' => 'OR'];
		foreach (cmx_meta_keys_kontakt() as $k) {
			$or[] = ['key' => $k, 'value' => $kontakt_id, 'compare' => '='];
		}
		$meta_query[] = $or;
	}

	if (!empty($tax_query))  $q->set('tax_query', $tax_query);
	if (!empty($meta_query)) $q->set('meta_query', array_merge(['relation'=>'AND'], $meta_query));
}, 20);

/** --- Spalten anpassen ----------------------------------------------------- */
\add_filter('manage_'.'belege'.'_posts_columns', function($cols){
	if (isset($cols['date'])) unset($cols['date']);
	$new = [];
	if (isset($cols['cb'])) $new['cb'] = $cols['cb'];
	$new['title'] = $cols['title'] ?? __('Titel','default');
	$new['cmx_beleg_kontakt'] = __('Kontakt','default');
	foreach ($cols as $k=>$v) {
		if (in_array($k, ['cb','title','date','cmx_beleg_kontakt'], true)) continue;
		$new[$k]=$v;
	}
	$new['cmx_beleg_kategorie'] = __('Kategorie','default');
	$new['cmx_beleg_projekt']   = __('Projekt','default');
	return $new;
});

/** --- Spalteninhalte ------------------------------------------------------- */
\add_action('manage_'.'belege'.'_posts_custom_column', function($col, $post_id){
	/* Kontakt */
	if ($col === 'cmx_beleg_kontakt') {
		$kontakt_id = 0;
		foreach (cmx_meta_keys_kontakt() as $k) {
			$val = \get_post_meta($post_id, $k, true);
			if (is_array($val)) $val = reset($val);
			if (!empty($val)) { $kontakt_id = (int)$val; break; }
		}
		if ($kontakt_id > 0 && \get_post_type($kontakt_id)) {
			$title = \get_the_title($kontakt_id);
			$link  = \get_edit_post_link($kontakt_id);
			echo ($title && $link) ? '<a href="'.esc_url($link).'">'.esc_html($title).'</a>' : '—';
		} else {
			echo '—';
		}
		return;
	}

	/* Kategorie (Tax) */
	if ($col === 'cmx_beleg_kategorie') {
		if ($tax = cmx_tax_belege_kategorien()) {
			$terms = \get_the_terms($post_id, $tax);
			if (!empty($terms) && !\is_wp_error($terms)) {
				$out = [];
				foreach ($terms as $t) {
					$out[] = '<a href="'.esc_url(cmx_term_edit_link($tax,(int)$t->term_id)).'">'.esc_html($t->name).'</a>';
				}
				echo implode(', ', $out);
			} else echo '—';
		} else echo '—';
		return;
	}

	/* Projekt (Tax oder Meta) */
	if ($col === 'cmx_beleg_projekt') {
		// 1) Projekt-Tax vorhanden?
		if ($tax = cmx_tax_belege_projekte()) {
			$terms = \get_the_terms($post_id, $tax);
			if (!empty($terms) && !\is_wp_error($terms)) {
				$out = [];
				foreach ($terms as $t) {
					$out[] = '<a href="'.esc_url(cmx_term_edit_link($tax,(int)$t->term_id)).'">'.esc_html($t->name).'</a>';
				}
				echo implode(', ', $out);
				return;
			}
		}
		// 2) Meta-IDs / Name
		foreach (['_cmx_beleg_projekt_id','_cmx_projekt_id','_projekt_id'] as $k) {
			$pid = (int)\get_post_meta($post_id, $k, true);
			if ($pid > 0) {
				$title = \get_the_title($pid) ?: ('#'.$pid);
				$link  = \get_edit_post_link($pid);
				echo $link ? '<a href="'.esc_url($link).'">'.esc_html($title).'</a>' : esc_html($title);
				return;
			}
		}
		// Fallback (String)
		$txt = \get_post_meta($post_id, '_cmx_beleg_projekt', true);
		echo $txt ? esc_html($txt) : '—';
		return;
	}
}, 10, 2);

/** --- Sortierung (Meta-Projekt) ------------------------------------------- */
\add_filter('manage_edit-'.'belege'.'_sortable_columns', function($cols){
	// nur sinnvoll wenn keine Projekt-Tax
	if (!cmx_tax_belege_projekte()) $cols['cmx_beleg_projekt'] = 'cmx_beleg_projekt';
	return $cols;
});
\add_action('pre_get_posts', function($q){
	if (!\is_admin() || !$q->is_main_query() || $q->get('post_type') !== cmx_belege_cpt_slug()) return;
	if ($q->get('orderby') === 'cmx_beleg_projekt' && !cmx_tax_belege_projekte()) {
		$q->set('meta_key', '_cmx_beleg_projekt'); // sortiert Strings; bei IDs ggf. '_cmx_beleg_projekt_id'
		$q->set('orderby', 'meta_value');
	}
}, 30);
