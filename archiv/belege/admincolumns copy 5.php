<?php
/**
 * Datei: src/belege/admin-list-kontakte-projekte.php
 * Zweck:
 *  - Belege-Adminliste: Filter & Spalten für PROJEKTE und KONTAKTE
 *  - Kontakt: klickbarer Link zur Bearbeitung (bei ID)
 *  - Projekt: Taxonomie-Links ODER Meta (ID/Text → List-Filter-Link)
 *  - Filter: Projekt (Tax/Meta) & Kontakt (über alle gängigen ID-Keys, OR)
 */
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

/* =========================
 * Helper / Konfiguration (nur definieren, wenn nicht vorhanden)
 * ========================= */
if (!function_exists(__NAMESPACE__.'\\cmx_belege_cpt')) {
	function cmx_belege_cpt(): string { return 'belege'; }
}
if (!function_exists(__NAMESPACE__.'\\cmx_kontakte_cpt')) {
	function cmx_kontakte_cpt(): string { return 'kontakte'; }
}
if (!function_exists(__NAMESPACE__.'\\cmx_tax_belege_kategorien')) {
	function cmx_tax_belege_kategorien(): string {
		foreach (['beleg_kategorie','belege_kategorien','beleg_kategorien','belege_kategorie'] as $tax) {
			if (\taxonomy_exists($tax)) return $tax;
		}
		return '';
	}
}
if (!function_exists(__NAMESPACE__.'\\cmx_tax_belege_projekte')) {
	function cmx_tax_belege_projekte(): string {
		foreach (['belege_projekte','beleg_projekte','belege_projekt','beleg_projekt','projekt_kategorie'] as $tax) {
			if (\taxonomy_exists($tax)) return $tax;
		}
		return '';
	}
}
if (!function_exists(__NAMESPACE__.'\\cmx_meta_projekt_ids')) {
	function cmx_meta_projekt_ids(): array { return ['_cmx_beleg_projekt_id','_cmx_projekt_id','_projekt_id']; }
}
if (!function_exists(__NAMESPACE__.'\\cmx_meta_projekt_txts')) {
	function cmx_meta_projekt_txts(): array { return ['_cmx_beleg_projekt']; }
}
if (!function_exists(__NAMESPACE__.'\\cmx_meta_kontakt_ids')) {
	function cmx_meta_kontakt_ids(): array {
		return ['_cmx_beleg_kontakt','_cmx_kontakt','_cmx_beleg_contact','_beleg_kontakt','_kontakt_id','_cmx_contact_id'];
	}
}
if (!function_exists(__NAMESPACE__.'\\cmx_meta_kontakt_text')) {
	function cmx_meta_kontakt_text(): string { return '_cmx_beleg_kontakt_addr'; } // Fallback Anzeige
}
if (!function_exists(__NAMESPACE__.'\\cmx_term_edit_link')) {
	function cmx_term_edit_link(string $tax, int $term_id): string {
		return \add_query_arg([
			'post_type' => cmx_belege_cpt(),
			'taxonomy'  => $tax,
			'tag_ID'    => $term_id,
		], \admin_url('term.php'));
	}
}
if (!function_exists(__NAMESPACE__.'\\cmx_term_filter_link')) {
	function cmx_term_filter_link(string $tax, int $term_id): string {
		return \add_query_arg([
			'post_type' => cmx_belege_cpt(),
			$tax        => $term_id,
		], \admin_url('edit.php'));
	}
}
if (!function_exists(__NAMESPACE__.'\\cmx_used_project_meta_values')) {
	function cmx_used_project_meta_values(): array {
		global $wpdb;
		$keys = array_merge(cmx_meta_projekt_ids(), cmx_meta_projekt_txts());
		if (!$keys) return [];
		$placeholders = implode(',', array_fill(0, count($keys), '%s'));
		$sql = "
			SELECT DISTINCT pm.meta_key, pm.meta_value
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key IN ($placeholders)
			  AND p.post_type = %s
			  AND p.post_status NOT IN ('auto-draft','trash')
			ORDER BY pm.meta_value ASC
		";
		$params = $keys; $params[] = cmx_belege_cpt();
		$rows = (array) $wpdb->get_results($wpdb->prepare($sql, ...$params));

		$out = [];
		foreach ($rows as $r) {
			$val = trim((string)$r->meta_value);
			if ($val === '') continue;
			if (in_array($r->meta_key, cmx_meta_projekt_ids(), true) && ctype_digit($val)) {
				$pid   = (int) $val;
				$title = \get_the_title($pid);
				$out['id:'.$pid] = $title ? $title.' (#'.$pid.')' : '#'.$pid;
			} else {
				$out['txt:'.$val] = $val;
			}
		}
		return $out; // key: id:123|txt:Foo → label
	}
}
if (!function_exists(__NAMESPACE__.'\\cmx_used_contact_ids')) {
	function cmx_used_contact_ids(): array {
		global $wpdb;
		$ids = [];
		foreach (cmx_meta_kontakt_ids() as $k) {
			$col = $wpdb->get_col($wpdb->prepare("
				SELECT DISTINCT pm.meta_value
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s
				  AND p.post_type = %s
				  AND p.post_status NOT IN ('auto-draft','trash')", $k, cmx_belege_cpt()));
			if (!$col) continue;
			foreach ($col as $v) {
				$v = (int)$v;
				if ($v > 0) $ids[$v] = true;
			}
		}
		// CPT 'kontakte' ergänzen (falls vorhanden)
		if (\post_type_exists(cmx_kontakte_cpt())) {
			$kontakt_posts = \get_posts([
				'post_type'      => cmx_kontakte_cpt(),
				'post_status'    => 'any',
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			]);
			foreach ($kontakt_posts as $pid) $ids[(int)$pid] = true;
		}
		return array_keys($ids);
	}
}

/* =========================
 * UI: Monatsfilter ausblenden
 * ========================= */
\add_filter('months_dropdown_results', function($months, $post_type){
	return ($post_type === cmx_belege_cpt()) ? [] : $months;
}, 10, 2);

/* =========================
 * UI: Filterleiste (Kategorie, Projekt, Kontakt)
 * ========================= */
\add_action('restrict_manage_posts', function($post_type = '', $which = ''){
	$screen = function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || $screen->id !== 'edit-'.cmx_belege_cpt()) return;

	/* Kategorie */
	if ($tax_cat = cmx_tax_belege_kategorien()) {
		$selected = isset($_GET[$tax_cat]) ? sanitize_text_field(wp_unslash($_GET[$tax_cat])) : '';
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

	/* Projekte (Tax oder Meta-Fallback – Meta nur zeigen, wenn KEINE Tax vorhanden ist!) */
	if ($tax_proj = cmx_tax_belege_projekte()) {
		$selected = isset($_GET[$tax_proj]) ? sanitize_text_field(wp_unslash($_GET[$tax_proj])) : '';
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
		$used_projects = cmx_used_project_meta_values();
		$current_proj  = isset($_GET['cmx_proj']) ? sanitize_text_field(wp_unslash($_GET['cmx_proj'])) : '';
		if (!empty($used_projects)) {
			echo '<select name="cmx_proj">';
			echo '<option value="">' . esc_html__('Alle Projekte (Meta)', 'default') . '</option>';
			foreach ($used_projects as $k => $label) {
				echo '<option value="'.esc_attr($k).'"'.selected($current_proj, $k, false).'>'.esc_html($label).'</option>';
			}
			echo '</select>';
		}
	}

	/* Kontakte (immer Dropdown, ID-basiert) */
	$current_kontakt = isset($_GET['cmx_kontakt']) ? (string)(int) $_GET['cmx_kontakt'] : '';
	$contact_ids     = cmx_used_contact_ids();
	echo '<select name="cmx_kontakt">';
	echo '<option value="">' . esc_html__('Alle Kontakte', 'default') . '</option>';
	if ($contact_ids) {
		$chunks = array_chunk($contact_ids, 100);
		foreach ($chunks as $chunk) {
			$posts = \get_posts([
				'post__in'       => $chunk,
				'post_type'      => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			]);
			foreach ($posts as $pid) {
				$title = \get_the_title($pid) ?: ('#'.$pid);
				$val   = (string) (int) $pid;
				echo '<option value="'.esc_attr($val).'"'.selected($current_kontakt, $val, false).'>'.esc_html($title).' (#'.$pid.')</option>';
			}
		}
	}
	echo '</select>';
}, 20, 2);

/* =========================
 * Query-Anpassung (Filter)
 * ========================= */
\add_action('pre_get_posts', function($q){
	if (!\is_admin() || !$q->is_main_query() || $q->get('post_type') !== cmx_belege_cpt()) return;

	$tax_query  = (array) $q->get('tax_query');
	$meta_query = (array) $q->get('meta_query');

	/* Kategorie */
	if ($tax_cat = cmx_tax_belege_kategorien()) {
		if (!empty($_GET[$tax_cat]) && (int)$_GET[$tax_cat] > 0) {
			$tax_query[] = [
				'taxonomy' => $tax_cat,
				'field'    => 'term_id',
				'terms'    => (int) $_GET[$tax_cat],
			];
		}
	}

	/* Projekte per Tax */
	if ($tax_proj = cmx_tax_belege_projekte()) {
		if (!empty($_GET[$tax_proj]) && (int)$_GET[$tax_proj] > 0) {
			$tax_query[] = [
				'taxonomy' => $tax_proj,
				'field'    => 'term_id',
				'terms'    => (int) $_GET[$tax_proj],
			];
		}
	}
	/* Projekt per Meta: cmx_proj = "id:123" oder "txt:Foo" */
	if (!empty($_GET['cmx_proj'])) {
		$val = sanitize_text_field(wp_unslash($_GET['cmx_proj']));
		if (strpos($val, 'id:') === 0) {
			$pid = (string) (int) substr($val, 3);
			$or = ['relation' => 'OR'];
			foreach (cmx_meta_projekt_ids() as $k) {
				$or[] = ['key'=>$k, 'value'=>$pid, 'compare'=>'='];
			}
			foreach (cmx_meta_projekt_txts() as $k) { // falls ID im Textfeld abgelegt wurde
				$or[] = ['key'=>$k, 'value'=>$pid, 'compare'=>'='];
			}
			$meta_query[] = $or;
		} elseif (strpos($val, 'txt:') === 0) {
			$txt = substr($val, 4);
			$or  = ['relation' => 'OR'];
			foreach (cmx_meta_projekt_txts() as $k) {
				$or[] = ['key'=>$k, 'value'=>$txt, 'compare'=>'='];
			}
			$meta_query[] = $or;
		}
	}

	/* Kontakt-Filter: OR über alle Kontakt-ID-Keys */
	if (!empty($_GET['cmx_kontakt']) && (int)$_GET['cmx_kontakt'] > 0) {
		$kontakt_id = (string) (int) $_GET['cmx_kontakt'];
		$or = ['relation' => 'OR'];
		foreach (cmx_meta_kontakt_ids() as $k) {
			$or[] = ['key'=>$k, 'value'=>$kontakt_id, 'compare'=>'='];
		}
		$meta_query[] = $or;
	}

	if (!empty($tax_query))  $q->set('tax_query', $tax_query);
	if (!empty($meta_query)) $q->set('meta_query', array_merge(['relation'=>'AND'], $meta_query));
}, 20);

/* =========================
 * Spalten definieren
 * ========================= */
\add_filter('manage_'.'belege'.'_posts_columns', function($cols){
	if (isset($cols['date'])) unset($cols['date']);
	$new = [];
	if (isset($cols['cb'])) $new['cb'] = $cols['cb'];
	$new['title'] = $cols['title'] ?? __('Titel','default');
	$new['cmx_beleg_kontakt'] = __('Kontakt','default');
	foreach ($cols as $k=>$v) {
		if (in_array($k, ['cb','title','date','cmx_beleg_kontakt'], true)) continue;
		$new[$k] = $v;
	}
	$new['cmx_beleg_kategorie'] = __('Kategorie','default');
	$new['cmx_beleg_projekt']   = __('Projekt','default');
	return $new;
});

/* =========================
 * Spalten-Inhalte
 * ========================= */
\add_action('manage_'.'belege'.'_posts_custom_column', function($col, $post_id){
	/* Kontakt → klickbarer Link, sonst Text-Fallback */
	if ($col === 'cmx_beleg_kontakt') {
		foreach (cmx_meta_kontakt_ids() as $k) {
			$val = \get_post_meta($post_id, $k, true);
			if (is_array($val)) $val = reset($val);
			$pid = (int) $val;
			if ($pid > 0 && \get_post_type($pid)) {
				$title = \get_the_title($pid) ?: ('Kontakt #'.$pid);
				$link  = \admin_url('post.php?action=edit&post='.$pid);
				echo '<a href="'.esc_url($link).'">'.esc_html($title).'</a>';
				return;
			}
		}
		$txt = trim((string) \get_post_meta($post_id, cmx_meta_kontakt_text(), true));
		if ($txt !== '') {
			$first = preg_split('/\r\n|\r|\n/', $txt);
			echo esc_html(trim((string)($first[0] ?? $txt)));
		} else {
			echo '—';
		}
		return;
	}

	/* Kategorie → **LISTEN-FILTER-LINK** statt Term-Edit */
	if ($col === 'cmx_beleg_kategorie') {
		if ($tax = cmx_tax_belege_kategorien()) {
			$terms = \get_the_terms($post_id, $tax);
			if (!empty($terms) && !\is_wp_error($terms)) {
				$out = [];
				foreach ($terms as $t) {
					$out[] = '<a href="'.esc_url(cmx_term_filter_link($tax,(int)$t->term_id)).'">'.esc_html($t->name).'</a>';
				}
				echo implode(', ', $out);
			} else echo '—';
		} else echo '—';
		return;
	}

	/* Projekt → Tax: **LISTEN-FILTER-LINK** | Meta-ID/Text: **LISTEN-FILTER-LINK** */
	if ($col === 'cmx_beleg_projekt') {
		// 1) Taxonomie → auf Listen-Filter linken
		if ($tax = cmx_tax_belege_projekte()) {
			$terms = \get_the_terms($post_id, $tax);
			if (!empty($terms) && !\is_wp_error($terms)) {
				$out = [];
				foreach ($terms as $t) {
					$out[] = '<a href="'.esc_url(cmx_term_filter_link($tax,(int)$t->term_id)).'">'.esc_html($t->name).'</a>';
				}
				echo implode(', ', $out);
				return;
			}
		}
		// 2) Meta-IDs → auf Listen-Filter "cmx_proj=id:{PID}" linken
		foreach (cmx_meta_projekt_ids() as $k) {
			$pid = (int)\get_post_meta($post_id, $k, true);
			if ($pid > 0) {
				$title = \get_the_title($pid) ?: ('#'.$pid);
				$link  = \add_query_arg(['post_type'=>cmx_belege_cpt(),'cmx_proj'=>'id:'.$pid], \admin_url('edit.php'));
				echo '<a href="'.esc_url($link).'">'.esc_html($title).'</a>';
				return;
			}
		}
		// 3) Meta-Text → auf Listen-Filter "cmx_proj=txt:{Wert}" linken
		foreach (cmx_meta_projekt_txts() as $k) {
			$txt = trim((string)\get_post_meta($post_id, $k, true));
			if ($txt !== '') {
				$link = \add_query_arg(['post_type'=>cmx_belege_cpt(),'cmx_proj'=>'txt:'.$txt], \admin_url('edit.php'));
				echo '<a href="'.esc_url($link).'">'.esc_html($txt).'</a>';
				return;
			}
		}
		echo '—';
		return;
	}
}, 10, 2);

/* =========================
 * Sortierung (optional)
 * ========================= */
\add_filter('manage_edit-'.'belege'.'_sortable_columns', function($cols){
	if (!cmx_tax_belege_projekte()) $cols['cmx_beleg_projekt'] = 'cmx_beleg_projekt';
	return $cols;
});
\add_action('pre_get_posts', function($q){
	if (!\is_admin() || !$q->is_main_query() || $q->get('post_type') !== cmx_belege_cpt()) return;
	if ($q->get('orderby') === 'cmx_beleg_projekt' && !cmx_tax_belege_projekte()) {
		$q->set('meta_key', '_cmx_beleg_projekt');
		$q->set('orderby', 'meta_value');
	}
}, 30);
