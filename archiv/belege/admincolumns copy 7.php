<?php
/**
 * Datei: src/belege/admin-list-kontakte-projekte.php (CLEAN, FIXED)
 * - Ein einziges Filter-UI: Kategorie, Projekt (Tax ODER Meta), Kontakt
 * - Klicks:
 *     • Kategorie → Listen-Filter
 *     • Projekt   → Listen-Filter (Tax / Meta)
 *     • Kontakt   → Edit-Seite (bei ID)
 * - Doppelte/alte Filter-Selects werden entfernt
 */
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

/* =========================
 * Basiskonfiguration (mit Guards)
 * ========================= */
if (!function_exists(__NAMESPACE__.'\\cmx_belege_cpt')) {
	function cmx_belege_cpt(): string { return 'belege'; }
}
if (!function_exists(__NAMESPACE__.'\\cmx_kontakte_cpt')) {
	function cmx_kontakte_cpt(): string { return 'kontakte'; }
}
if (!function_exists(__NAMESPACE__.'\\cmx_tax_beleg_kategorie')) {
	function cmx_tax_beleg_kategorie(): string {
		foreach (['beleg_kategorie','belege_kategorien','beleg_kategorien','belege_kategorie'] as $tax) {
			if (\taxonomy_exists($tax)) return $tax;
		}
		return '';
	}
}
if (!function_exists(__NAMESPACE__.'\\cmx_tax_beleg_projekt')) {
	function cmx_tax_beleg_projekt(): string {
		foreach (['belege_projekte','beleg_projekte','belege_projekt','beleg_projekt','projekt_kategorie'] as $tax) {
			if (\taxonomy_exists($tax)) return $tax;
		}
		return '';
	}
}

/* =========================
 * Meta-Key Definitionen
 * ========================= */
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
	function cmx_meta_kontakt_text(): string { return '_cmx_beleg_kontakt_addr'; }
}

/* =========================
 * Hilfslinks
 * ========================= */
if (!function_exists(__NAMESPACE__.'\\cmx_list_filter_link')) {
	function cmx_list_filter_link(array $args): string {
		$args = array_merge(['post_type' => cmx_belege_cpt()], $args);
		return \add_query_arg($args, \admin_url('edit.php'));
	}
}

/* =========================
 * Daten für Fallback-Dropdowns
 * ========================= */
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
				$pid = (int)$val;
				// Waisen/Trash konsequent ausschliessen:
				$status = \get_post_status($pid);
				if (!$status || $status === 'trash' || $status === 'auto-draft') continue;
				$title = trim((string)\get_the_title($pid));
				if ($title === '') $title = '#'.$pid;
				$out['id:'.$pid] = $title.' (#'.$pid.')';
			} else {
				// Normalisierte Textwerte (Whitespace reduzieren)
				$val_norm = preg_replace('/\s+/u', ' ', $val);
				$out['txt:'.$val_norm] = $val_norm;
			}
		}

		// Stabil sortieren
		ksort($out, SORT_NATURAL);
		return $out; // key: id:123 | txt:Foo → label
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
			foreach ((array)$col as $v) {
				$v = (int)trim((string)$v);
				if ($v > 0) {
					$status = \get_post_status($v);
					if ($status && $status !== 'trash' && $status !== 'auto-draft') {
						$ids[$v] = true;
					}
				}
			}
		}
		// Optional: existierende Kontakte ergänzen
		if (\post_type_exists(cmx_kontakte_cpt())) {
			$kontakt_ids = \get_posts([
				'post_type'      => cmx_kontakte_cpt(),
				'post_status'    => 'any',
				'posts_per_page' => 500,
				'fields'         => 'ids',
			]);
			foreach ($kontakt_ids as $id) {
				$status = \get_post_status($id);
				if ($status && $status !== 'trash' && $status !== 'auto-draft') {
					$ids[(int)$id] = true;
				}
			}
		}
		$ids = array_keys($ids);
		sort($ids, SORT_NUMERIC);
		return $ids;
	}
}

/* =========================
 * Monatsfilter entfernen
 * ========================= */
\add_filter('months_dropdown_results', function($months, $post_type){
	return ($post_type === cmx_belege_cpt()) ? [] : $months;
}, 10, 2);

/* =========================
 * Doppelte/alte Filter-Selects ausblenden/entfernen
 * ========================= */
\add_action('admin_footer', function(){
	$screen = function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || $screen->id !== 'edit-'.cmx_belege_cpt()) return;
	?>
	<script>
		(function(){
			const wrap = document.getElementById('cmx-filters-wrap');
			if(!wrap) return;

			// Alle Selects mit gleichen Namen ausserhalb unseres Wrappers entfernen
			const names = ['cmx_proj','cmx_kontakt','<?php echo esc_js(cmx_tax_beleg_projekt()); ?>'].filter(Boolean);
			names.forEach((n) => {
				document.querySelectorAll('select[name="'+n+'"]').forEach((el) => {
					if (!wrap.contains(el)) el.remove();
				});
			});
		})();
	</script>
	<?php
});

/* =========================
 * Einziges Filter-UI rendern
 * ========================= */
\add_action('restrict_manage_posts', function($post_type = '', $which = ''){
	$screen = function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || $screen->id !== 'edit-'.cmx_belege_cpt()) return;

	echo '<div id="cmx-filters-wrap" style="display:inline-block">';

	// Kategorie (Tax)
	if ($tax_cat = cmx_tax_beleg_kategorie()) {
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

	// Projekt (Tax ODER Meta-Fallback)
	if ($tax_proj = cmx_tax_beleg_projekt()) {
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
		$used    = cmx_used_project_meta_values();
		$current = isset($_GET['cmx_proj']) ? sanitize_text_field(wp_unslash($_GET['cmx_proj'])) : '';
		if (!empty($used)) {
			echo '<select name="cmx_proj">';
			echo '<option value="">' . esc_html__('Alle Projekte (Meta)', 'default') . '</option>';
			foreach ($used as $val => $label) {
				echo '<option value="'.esc_attr($val).'"'.selected($current, $val, false).'>'.esc_html($label).'</option>';
			}
			echo '</select>';
		}
	}

	// Kontakt (ID-basiert)
	$current_contact = isset($_GET['cmx_kontakt']) ? (string)(int) $_GET['cmx_kontakt'] : '';
	$ids = cmx_used_contact_ids();
	echo '<select name="cmx_kontakt">';
	echo '<option value="">' . esc_html__('Alle Kontakte', 'default') . '</option>';
	if ($ids) {
		$chunks = array_chunk($ids, 200);
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
				$val   = (string)(int)$pid;
				echo '<option value="'.esc_attr($val).'"'.selected($current_contact, $val, false).'>'.esc_html($title).' (#'.$pid.')</option>';
			}
		}
	}
	echo '</select>';

	echo '</div>';
}, 20, 2);

/* =========================
 * Query-Anpassung (Filter greifen)
 * ========================= */
\add_action('pre_get_posts', function($q){
	if (!\is_admin() || !$q->is_main_query() || $q->get('post_type') !== cmx_belege_cpt()) return;

	$tax_query  = (array)$q->get('tax_query');
	$meta_query = (array)$q->get('meta_query');

	// Kategorie
	if ($tax_cat = cmx_tax_beleg_kategorie()) {
		if (!empty($_GET[$tax_cat]) && (int)$_GET[$tax_cat] > 0) {
			$tax_query[] = [
				'taxonomy' => $tax_cat,
				'field'    => 'term_id',
				'terms'    => (int)$_GET[$tax_cat],
			];
		}
	}

	// Projekt (Tax)
	if ($tax_proj = cmx_tax_beleg_projekt()) {
		if (!empty($_GET[$tax_proj]) && (int)$_GET[$tax_proj] > 0) {
			$tax_query[] = [
				'taxonomy' => $tax_proj,
				'field'    => 'term_id',
				'terms'    => (int)$_GET[$tax_proj],
			];
		}
	}
	// Projekt (Meta: id:123 | txt:Foo)
	if (!empty($_GET['cmx_proj'])) {
		$val = sanitize_text_field(wp_unslash($_GET['cmx_proj']));
		if (strpos($val, 'id:') === 0) {
			$pid = (string)(int)substr($val, 3);
			$or = ['relation' => 'OR'];
			foreach (cmx_meta_projekt_ids() as $k) {
				$or[] = ['key'=>$k, 'value'=>$pid, 'compare'=>'=', 'type'=>'NUMERIC'];
			}
			// falls versehentlich als Text gespeichert:
			foreach (cmx_meta_projekt_txts() as $k) {
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

	// Kontakt (ID, OR über alle Kontakt-Keys, NUMERIC)
	if (!empty($_GET['cmx_kontakt']) && (int)$_GET['cmx_kontakt'] > 0) {
		$id = (string)(int)$_GET['cmx_kontakt'];
		$or = ['relation' => 'OR'];
		foreach (cmx_meta_kontakt_ids() as $k) {
			$or[] = ['key'=>$k, 'value'=>$id, 'compare'=>'=', 'type'=>'NUMERIC'];
		}
		$meta_query[] = $or;
	}

	if ($tax_query)  $q->set('tax_query', $tax_query);
	if ($meta_query) $q->set('meta_query', array_merge(['relation'=>'AND'], $meta_query));
}, 20);

/* =========================
 * Spalten anpassen
 * ========================= */
\add_filter('manage_'.'belege'.'_posts_columns', function($cols){
	unset($cols['date']);
	$new = [];
	if (isset($cols['cb'])) $new['cb'] = $cols['cb'];
	$new['title']               = $cols['title'] ?? __('Titel','default');
	$new['cmx_beleg_kontakt']   = __('Kontakt','default');
	$new['cmx_beleg_kategorie'] = __('Kategorie','default');
	$new['cmx_beleg_projekt']   = __('Projekt','default');
	return array_merge($new, array_diff_key($cols, ['cb'=>1,'title'=>1,'date'=>1]));
});

/* =========================
 * Spalten-Render
 * ========================= */
\add_action('manage_'.'belege'.'_posts_custom_column', function($col, $post_id){

	// Kontakt → Edit-Link (bei ID), sonst 1. Zeile Text
	if ($col === 'cmx_beleg_kontakt') {
		foreach (cmx_meta_kontakt_ids() as $k) {
			$val = \get_post_meta($post_id, $k, true);
			if (is_array($val)) $val = reset($val);
			$pid = (int)trim((string)$val);
			if ($pid > 0 && \get_post_type($pid)) {
				$title = \get_the_title($pid) ?: ('Kontakt #'.$pid);
				$link  = \admin_url('post.php?action=edit&post='.$pid);
				echo '<a href="'.esc_url($link).'">'.esc_html($title).'</a>';
				return;
			}
		}
		$txt = trim((string)\get_post_meta($post_id, cmx_meta_kontakt_text(), true));
		if ($txt !== '') {
			$first = preg_split('/\r\n|\r|\n/', $txt);
			echo esc_html(trim((string)($first[0] ?? $txt)));
		} else {
			echo '—';
		}
		return;
	}

	// Kategorie → Listen-Filter-Link
	if ($col === 'cmx_beleg_kategorie') {
		if ($tax = cmx_tax_beleg_kategorie()) {
			$terms = \get_the_terms($post_id, $tax);
			if (!empty($terms) && !\is_wp_error($terms)) {
				$links = [];
				foreach ($terms as $t) {
					$links[] = '<a href="'.esc_url(cmx_list_filter_link([$tax => (int)$t->term_id])).'">'.esc_html($t->name).'</a>';
				}
				echo implode(', ', $links);
			} else {
				echo '—';
			}
		} else {
			echo '—';
		}
		return;
	}

	// Projekt → Tax (Filter-Link) oder Meta (Filter-Link)
	if ($col === 'cmx_beleg_projekt') {
		// Taxonomie
		if ($tax = cmx_tax_beleg_projekt()) {
			$terms = \get_the_terms($post_id, $tax);
			if (!empty($terms) && !\is_wp_error($terms)) {
				$links = [];
				foreach ($terms as $t) {
					$links[] = '<a href="'.esc_url(cmx_list_filter_link([$tax => (int)$t->term_id])).'">'.esc_html($t->name).'</a>';
				}
				echo implode(', ', $links);
				return;
			}
		}
		// Meta-ID
		foreach (cmx_meta_projekt_ids() as $k) {
			$pid = (int)\get_post_meta($post_id, $k, true);
			if ($pid > 0) {
				$title = \get_the_title($pid) ?: ('#'.$pid);
				$link  = cmx_list_filter_link(['cmx_proj' => 'id:'.$pid]);
				echo '<a href="'.esc_url($link).'">'.esc_html($title).'</a>';
				return;
			}
		}
		// Meta-Text
		foreach (cmx_meta_projekt_txts() as $k) {
			$txt = trim((string)\get_post_meta($post_id, $k, true));
			if ($txt !== '') {
				$link = cmx_list_filter_link(['cmx_proj' => 'txt:'.$txt]);
				echo '<a href="'.esc_url($link).'">'.esc_html($txt).'</a>';
				return;
			}
		}
		echo '—';
		return;
	}
}, 10, 2);

/* =========================
 * Sortierung (optional, nur Meta)
 * ========================= */
\add_filter('manage_edit-'.'belege'.'_sortable_columns', function($cols){
	if (!cmx_tax_beleg_projekt()) $cols['cmx_beleg_projekt'] = 'cmx_beleg_projekt';
	return $cols;
});
\add_action('pre_get_posts', function($q){
	if (!\is_admin() || !$q->is_main_query() || $q->get('post_type') !== cmx_belege_cpt()) return;
	if ($q->get('orderby') === 'cmx_beleg_projekt' && !cmx_tax_beleg_projekt()) {
		$q->set('meta_key', '_cmx_beleg_projekt');
		$q->set('orderby', 'meta_value');
	}
}, 30);
