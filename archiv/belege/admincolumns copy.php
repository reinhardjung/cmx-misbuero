<?php
namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/** -------------------------------
 * Helper: Slugs / Fallbacks
 * ------------------------------- */
function cmx_belege_tax_kategorien(): string {
	return \taxonomy_exists('belege_kategorien') ? 'belege_kategorien' : '';
}

function cmx_belege_tax_projekte(): string {
	// bevorzugt belege_projekte
	if (\taxonomy_exists('belege_projekte')) return 'belege_projekte';
	foreach (['belege_projekt','projekte','projekt'] as $t) {
		if (\taxonomy_exists($t)) return $t;
	}
	return '';
}

function cmx_belege_meta_projekt_key(): string {
	return '_cmx_beleg_projekt';
}

/** Mögliche Kontakt-Metakeys (erste Übereinstimmung gewinnt) */
function cmx_belege_meta_kontakt_keys(): array {
	return ['_cmx_beleg_kontakt','_cmx_kontakt','_cmx_beleg_contact','_beleg_kontakt','_kontakt_id','_cmx_contact_id'];
}

/** Distinct Meta-Werte für Projekte (Fallback) */
function cmx_belege_project_meta_values(): array {
	global $wpdb;
	$key = cmx_belege_meta_projekt_key();
	$sql = $wpdb->prepare(
		"SELECT DISTINCT pm.meta_value
		 FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = %s AND p.post_type = 'belege' AND p.post_status NOT IN ('auto-draft','trash')
		 ORDER BY pm.meta_value ASC",
		$key
	);
	$values = $wpdb->get_col($sql);
	return array_values(array_filter(array_map('trim', (array) $values)));
}

/** Distinct Meta-Werte: Kontakte (IDs) über alle bekannten Keys */
function cmx_belege_contact_meta_ids(): array {
	global $wpdb;
	$keys = cmx_belege_meta_kontakt_keys();
	$ids  = [];

	foreach ($keys as $k) {
		$sql = $wpdb->prepare(
			"SELECT DISTINCT pm.meta_value
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = %s
			   AND p.post_type = 'belege'
			   AND p.post_status NOT IN ('auto-draft','trash')
			 ORDER BY pm.meta_value+0 ASC",
			$k
		);
		$col = $wpdb->get_col($sql);
		if (!empty($col)) {
			foreach ($col as $v) {
				$v = (int) $v;
				if ($v > 0) $ids[$v] = true;
			}
		}
	}

	return array_keys($ids);
}

/** Hilfslink: direkt auf Term-Detailseite (term.php) */
function cmx_belege_term_edit_link(string $tax, int $term_id): string {
	return \add_query_arg([
		'post_type' => 'belege',
		'taxonomy'  => $tax,
		'tag_ID'    => $term_id,
	], \admin_url('term.php'));
}

/** --------------------------------
 * A: Monatsfilter "Alle Daten" entfernen
 * -------------------------------- */
\add_filter('months_dropdown_results', function($months, $post_type){
	return ($post_type === 'belege') ? [] : $months;
}, 10, 2);

/** --------------------------------
 * Filter-UI oben in der Liste (Kategorie, Projekt, Kontakt)
 * -------------------------------- */
\add_action('restrict_manage_posts', function($post_type) {
	if ($post_type !== 'belege') return;

	// Kategorien (Taxonomie fix: belege_kategorien)
	if ($tax_cat = cmx_belege_tax_kategorien()) {
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

	// Projekte: Taxonomie ODER Meta-Fallback
	if ($tax_proj = cmx_belege_tax_projekte()) {
		$selected = $_GET[$tax_proj] ?? '';
		\wp_dropdown_categories([
			'show_option_all' => __('Alle Projekte', 'default'),
			'taxonomy'        => $tax_proj,
			'name'            => $tax_proj,
			'orderby'         => 'name',
			'selected'        => $selected,
			'hierarchical'    => true,
			'show_count'      => false,
			'hide_empty'      => false,
		]);
	} else {
		$current = isset($_GET['cmx_proj']) ? sanitize_text_field(wp_unslash($_GET['cmx_proj'])) : '';
		$values  = cmx_belege_project_meta_values();

		echo '<select name="cmx_proj">';
		echo '<option value="">' . esc_html__('Alle Projekte', 'default') . '</option>';
		foreach ($values as $val) {
			echo '<option value="' . esc_attr($val) . '"' . selected($current, $val, false) . '>' . esc_html($val) . '</option>';
		}
		echo '</select>';
	}

	// Kontakte: Dropdown aus tatsächlich verwendeten Kontakt-IDs (performant & relevant)
	$contact_ids = cmx_belege_contact_meta_ids();
	$current_contact = isset($_GET['cmx_kontakt']) ? (int) $_GET['cmx_kontakt'] : 0;

	echo '<select name="cmx_kontakt">';
	echo '<option value="">' . esc_html__('Alle Kontakte', 'default') . '</option>';

	if (!empty($contact_ids)) {
		// Titel für IDs holen (in Blöcken, um DB-Last klein zu halten)
		$chunks = array_chunk($contact_ids, 100);
		foreach ($chunks as $chunk) {
			$posts = get_posts([
				'post__in'       => $chunk,
				'post_type'      => 'any',     // Kontakt kann CPT sein – wir zeigen, was existiert
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			]);
			foreach ($posts as $pid) {
				$title = get_the_title($pid);
				if (!$title) $title = sprintf(__('Kontakt #%d', 'default'), $pid);
				echo '<option value="' . esc_attr($pid) . '"' . selected($current_contact, $pid, false) . '>' . esc_html($title) . '</option>';
			}
		}
	}

	echo '</select>';
}, 20);

/** --------------------------------
 * Query anpassen basierend auf Filtern (Kategorie, Projekt, Kontakt)
 * -------------------------------- */
\add_action('pre_get_posts', function($q) {
	if (!\is_admin() || !$q->is_main_query() || $q->get('post_type') !== 'belege') return;

	$tax_query = (array) $q->get('tax_query');

	// Kategorie-Filter
	if ($tax_cat = cmx_belege_tax_kategorien()) {
		if (!empty($_GET[$tax_cat]) && intval($_GET[$tax_cat]) > 0) {
			$tax_query[] = [
				'taxonomy' => $tax_cat,
				'field'    => 'term_id',
				'terms'    => intval($_GET[$tax_cat]),
			];
		}
	}

	// Projekt-Filter (Taxonomie bevorzugt)
	if ($tax_proj = cmx_belege_tax_projekte()) {
		if (!empty($_GET[$tax_proj]) && intval($_GET[$tax_proj]) > 0) {
			$tax_query[] = [
				'taxonomy' => $tax_proj,
				'field'    => 'term_id',
				'terms'    => intval($_GET[$tax_proj]),
			];
		}
	} else {
		// Meta-Fallback
		if (!empty($_GET['cmx_proj'])) {
			$q->set('meta_query', [
				[
					'key'     => cmx_belege_meta_projekt_key(),
					'value'   => sanitize_text_field(wp_unslash($_GET['cmx_proj'])),
					'compare' => '=',
				]
			]);
		}
	}

	// Kontakt-Filter (über alle bekannten Kontakt-Meta-Keys, OR-Verknüpfung)
	if (!empty($_GET['cmx_kontakt']) && intval($_GET['cmx_kontakt']) > 0) {
		$kontakt_id = intval($_GET['cmx_kontakt']);
		$keys = cmx_belege_meta_kontakt_keys();

		$meta_or = ['relation' => 'OR'];
		foreach ($keys as $k) {
			$meta_or[] = [
				'key'     => $k,
				'value'   => (string) $kontakt_id,
				'compare' => '=',
			];
		}

		// existierende meta_query (z. B. Projekt-Fallback) ergänzen
		$existing_meta = (array) $q->get('meta_query');
		if (!empty($existing_meta)) {
			$q->set('meta_query', array_merge(['relation' => 'AND'], $existing_meta, [$meta_or]));
		} else {
			$q->set('meta_query', $meta_or);
		}
	}

	if (!empty($tax_query)) {
		$q->set('tax_query', $tax_query);
	}
}, 20);

/** --------------------------------
 * Spalten: hinzufügen / Reihenfolge (Kontakt direkt nach Titel)
 * -------------------------------- */
\add_filter('manage_belege_posts_columns', function($cols) {
	// Datumsspalte entfernen
	if (isset($cols['date'])) unset($cols['date']);

	$new = [];
	if (isset($cols['cb']))    $new['cb']    = $cols['cb'];
	$new['title'] = $cols['title'] ?? __('Titel', 'default');

	// Kontakt nach Titel
	$new['cmx_beleg_kontakt'] = __('Kontakt', 'default');

	// Rest übernehmen (ohne title/date)
	foreach ($cols as $k => $v) {
		if (in_array($k, ['cb','title','date','cmx_beleg_kontakt'], true)) continue;
		$new[$k] = $v;
	}

	$new['cmx_beleg_kategorie'] = __('Kategorie', 'default');
	$new['cmx_beleg_projekt']   = __('Projekt', 'default');

	return $new;
});

/** --------------------------------
 * Spalten: Inhalte rendern
 * -------------------------------- */
\add_action('manage_belege_posts_custom_column', function($col, $post_id) {
	// Kontakt → direkt zur Bearbeitungsseite des Kontakts
	if ($col === 'cmx_beleg_kontakt') {
		$kontakt_id = 0;
		foreach (cmx_belege_meta_kontakt_keys() as $key) {
			$val = \get_post_meta($post_id, $key, true);
			if (is_array($val)) { $val = reset($val); }
			if (!empty($val)) { $kontakt_id = intval($val); break; }
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

	// Kategorie → Term-Detailseite
	if ($col === 'cmx_beleg_kategorie') {
		$tax = cmx_belege_tax_kategorien();
		if ($tax) {
			$terms = \get_the_terms($post_id, $tax);
			if (!empty($terms) && !\is_wp_error($terms)) {
				$out = [];
				foreach ($terms as $t) {
					$out[] = '<a href="'.esc_url(cmx_belege_term_edit_link($tax, (int)$t->term_id)).'">'.esc_html($t->name).'</a>';
				}
				echo implode(', ', $out);
			} else {
				echo '—';
			}
		} else {
			echo '—';
		}
		return;
	}

	// Projekt → Term-Detailseite (Tax), sonst Meta-Fallback-Text
	if ($col === 'cmx_beleg_projekt') {
		if ($tax = cmx_belege_tax_projekte()) {
			$terms = \get_the_terms($post_id, $tax);
			if (!empty($terms) && !\is_wp_error($terms)) {
				$out = [];
				foreach ($terms as $t) {
					$out[] = '<a href="'.esc_url(cmx_belege_term_edit_link($tax, (int)$t->term_id)).'">'.esc_html($t->name).'</a>';
				}
				echo implode(', ', $out);
			} else {
				echo '—';
			}
		} else {
			$val = \get_post_meta($post_id, cmx_belege_meta_projekt_key(), true);
			echo $val ? esc_html($val) : '—';
		}
		return;
	}
}, 10, 2);

/** --------------------------------
 * Sortierung (optional): Projekt per Meta sortierbar, wenn keine Projekt-Taxonomie
 * -------------------------------- */
\add_filter('manage_edit-belege_sortable_columns', function($cols) {
	if (!cmx_belege_tax_projekte()) {
		$cols['cmx_beleg_projekt'] = 'cmx_beleg_projekt';
	}
	return $cols;
});

\add_action('pre_get_posts', function($q) {
	if (!\is_admin() || !$q->is_main_query() || $q->get('post_type') !== 'belege') return;
	if ($q->get('orderby') === 'cmx_beleg_projekt' && !cmx_belege_tax_projekte()) {
		$q->set('meta_key', cmx_belege_meta_projekt_key());
		$q->set('orderby', 'meta_value');
	}
}, 30);
