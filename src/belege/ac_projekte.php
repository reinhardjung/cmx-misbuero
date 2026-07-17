<?php

/**
 * Datei: src/belege/admin-list-projekt-cpt-filter-cleanurl.php
 * Ziel:
 *  - Selectbox nutzt NUR den WP-Button „Filtern“ (kein Auto-Reload, kein Extra-Button)
 *  - Beim Submit wird eine SAUBERE URL erzwungen:
 *      /wp-admin/edit.php?post_type=belege&cmx_proj_id={ID}&paged=1
 *    (keine weiteren Query-Parameter wie s, action, filter_action=Filtern etc.)
 *  - pre_get_posts filtert robust anhand von cmx_proj_id (ID auch in serialisierten Metas)
 */

namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

/* === Helpers === */
if (!function_exists(__NAMESPACE__.'\\cmx_belege_cpt'))    { function cmx_belege_cpt(): string { return 'belege'; } }
if (!function_exists(__NAMESPACE__.'\\cmx_projekte_cpt'))  { function cmx_projekte_cpt(): string { return 'projekte'; } }
if (!function_exists(__NAMESPACE__.'\\cmx_meta_projekt_ids')) {
	function cmx_meta_projekt_ids(): array { return ['_cmx_beleg_projekt_id','_cmx_projekt_id','_projekt_id']; }
}
if (!function_exists(__NAMESPACE__.'\\cmx_meta_projekt_txts')) {
	function cmx_meta_projekt_txts(): array { return ['_cmx_beleg_projekt']; }
}
if (!function_exists(__NAMESPACE__.'\\cmx_admin_hidden_columns')) {
	function cmx_admin_hidden_columns(string $post_type): array {
		$post_type = \sanitize_key($post_type);
		if ($post_type === '') {
			return [];
		}

		$screen = function_exists('get_current_screen') ? \get_current_screen() : null;
		if ($screen && !empty($screen->id) && (string) ($screen->post_type ?? '') === $post_type && function_exists('get_hidden_columns')) {
			$hidden = \get_hidden_columns($screen);
			if (is_array($hidden)) {
				return array_values(array_unique(array_map('strval', $hidden)));
			}
		}

		$hidden = \get_user_option('manageedit-' . $post_type . 'columnshidden');
		if (!is_array($hidden)) {
			return [];
		}

		return array_values(array_unique(array_map('strval', $hidden)));
	}
}
if (!function_exists(__NAMESPACE__.'\\cmx_admin_post_type_column_is_visible')) {
	function cmx_admin_post_type_column_is_visible(string $post_type, string $column_key): bool {
		$post_type = \sanitize_key($post_type);
		if ($column_key === '') {
			return true;
		}
		return !in_array($column_key, cmx_admin_hidden_columns($post_type), true);
	}
}
if (!function_exists(__NAMESPACE__.'\\cmx_beleg_admin_hidden_columns')) {
	function cmx_beleg_admin_hidden_columns(): array {
		return cmx_admin_hidden_columns(cmx_belege_cpt());
	}
}
if (!function_exists(__NAMESPACE__.'\\cmx_beleg_admin_column_is_visible')) {
	function cmx_beleg_admin_column_is_visible(string $column_key): bool {
		return cmx_admin_post_type_column_is_visible(cmx_belege_cpt(), $column_key);
	}
}

/* Monats-Dropdown ausblenden (optional) */
\add_filter('months_dropdown_results', function($months, $post_type){
	return ($post_type === cmx_belege_cpt()) ? [] : $months;
}, 10, 2);

/* Query-Var whitelisten */
\add_filter('query_vars', function(array $vars){
	$vars[] = 'cmx_proj_id';
	return $vars;
});

/* Select NUR oben rendern (im #posts-filter Formular) */
\add_action('restrict_manage_posts', function($post_type = '', $which = ''){
	$screen = function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || $screen->id !== 'edit-'.cmx_belege_cpt() || $which !== 'top') return;
	if (!cmx_beleg_admin_column_is_visible('cmx_beleg_projekt')) return;

	$current = isset($_GET['cmx_proj_id']) ? (string)(int) $_GET['cmx_proj_id'] : '';

	$projects = \get_posts([
		'post_type'      => cmx_projekte_cpt(),
		'post_status'    => ['publish','private','draft'],
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	]);

	echo '<div id="cmx-projekt-filter-wrap" style="display:inline-block;margin-right:8px">';
	echo '<label class="screen-reader-text" for="cmx-projekt-select">Projekt filtern</label>';
	echo '<select id="cmx-projekt-select" name="cmx_proj_id">';
	echo '<option value="">'.esc_html__('Alle Projekte', 'default').'</option>';
	foreach ($projects as $pid) {
		$status = \get_post_status($pid);
		if (!$status || $status === 'trash' || $status === 'auto-draft') continue;
		// $title = \get_the_title($pid) ?: ('#'.$pid);
		$title = \get_the_title($pid);
		$val   = (string)(int)$pid;
		// echo '<option value="'.esc_attr($val).'"'.selected($current, $val, false).'>'.esc_html($title).' (#'.$pid.')</option>';
		echo '<option value="'.esc_attr($val).'"'.selected($current, $val, false).'>'.esc_html($title).'</option>';
	}
	echo '</select>';
	echo '</div>';
}, 20, 2);

/* Submit „säubern“: auf saubere URL umleiten (kein Auto-Reload; nur bei Button-Klick) */
\add_action('admin_footer', function () {
	$screen = function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || $screen->id !== 'edit-'.cmx_belege_cpt()) return;

	$basePath = esc_js(parse_url(\admin_url('edit.php'), PHP_URL_PATH) ?: '/wp-admin/edit.php');
	$postType = esc_js(cmx_belege_cpt());
	?>
	<script>
	(function(){
		var form = document.getElementById('posts-filter');
		if(!form) return;

		form.addEventListener('submit', function(e){
			// Nur eingreifen, wenn unser Filter-Button (name="filter_action") ausgelöst wurde
			var submitter = e.submitter || null;
			if (!submitter || submitter.name !== 'filter_action') return;

			e.preventDefault(); // Standard-Submit unterbinden
			var u = new URL(window.location.origin + "<?php echo $basePath; ?>");

			// Alle Felder des Formulars übernehmen (Filter, Suche, etc.)
			var fd = new FormData(form);
			fd.forEach(function(val, key){
				if (val === null) return;
				if (Array.isArray(val)) return; // FormData gibt Strings
				var v = (''+val).trim();
				if (v === '') {
					u.searchParams.delete(key);
					return;
				}
				u.searchParams.set(key, v);
			});

			// Sicherstellen: post_type & paged
			u.searchParams.set('post_type', '<?php echo $postType; ?>');
			u.searchParams.set('paged', '1');

			window.location.assign(u.toString());
		});

		// Störende/alte Projekt-Selects entfernen (damit nichts anderes submitted wird)
		['cmx_proj','belege_projekte','beleg_projekte','belege_projekt','beleg_projekt','projekt_kategorie'].forEach(function(n){
			document.querySelectorAll('select[name="'+n+'"]').forEach(function(el){ el.remove(); });
		});
	})();
	</script>
	<?php
});

/* Robuste Meta-Query auf cmx_proj_id */
if (!function_exists(__NAMESPACE__.'\\cmx_build_project_meta_or')) {
	function cmx_build_project_meta_or(int $pid): array {
		$pid_str         = (string)$pid;
		$like_serial_str = ':"'.$pid_str.'";'; // s:"123";
		$like_serial_int = ';i:'.$pid.';';     // i:123;

		$or = ['relation' => 'OR'];
		foreach (cmx_meta_projekt_ids() as $k) {
			$or[] = ['key'=>$k, 'value'=>$pid,     'compare'=>'=',   'type'=>'NUMERIC'];
			$or[] = ['key'=>$k, 'value'=>$pid_str, 'compare'=>'='];
			$or[] = ['key'=>$k, 'value'=>$like_serial_str, 'compare'=>'LIKE'];
			$or[] = ['key'=>$k, 'value'=>$like_serial_int, 'compare'=>'LIKE'];
		}
		foreach (cmx_meta_projekt_txts() as $k) {
			$or[] = ['key'=>$k, 'value'=>$pid_str, 'compare'=>'='];
			$or[] = ['key'=>$k, 'value'=>$pid_str, 'compare'=>'LIKE'];
		}
		return $or;
	}
}

\add_action('pre_get_posts', function(\WP_Query $q){
	if (!\is_admin() || !$q->is_main_query()) return;

	$pt = $q->get('post_type');
	$belege = cmx_belege_cpt();
	if ((is_array($pt) && !in_array($belege, $pt, true)) || (!is_array($pt) && $pt !== $belege)) return;
	if (!cmx_beleg_admin_column_is_visible('cmx_beleg_projekt')) return;

	$pid = (int) ($q->get('cmx_proj_id') ?: ($_GET['cmx_proj_id'] ?? 0));
	if ($pid <= 0) return;

	$meta_query = (array) $q->get('meta_query');

	// Doppelten OR-Block vermeiden
	$keys = cmx_meta_projekt_ids(); $has = false;
	foreach ($meta_query as $block) {
		if (is_array($block) && isset($block['relation']) && $block['relation']==='OR') {
			foreach ($block as $cond) {
				if (is_array($cond) && isset($cond['key']) && in_array($cond['key'], $keys, true)) { $has = true; break 2; }
			}
		}
	}
	if (!$has) {
		$meta_query[] = cmx_build_project_meta_or($pid);
		$q->set('meta_query', array_merge(['relation'=>'AND'], $meta_query));
	}

	// Sortierung
	if (!$q->get('orderby')) $q->set('orderby','date');
	if (!$q->get('order'))   $q->set('order','DESC');
}, 9999);

/* (optional) Spalte „Projekt(e)“ mit sauberem Link */
\add_filter('manage_'.'belege'.'_posts_columns', function($cols){
	if (!isset($cols['cmx_beleg_projekt'])) {
		$before = [];
		if (isset($cols['cb'])) $before['cb'] = $cols['cb'];
		$before['title'] = $cols['title'] ?? __('Titel','default');
		$cols = array_merge($before, ['cmx_beleg_projekt' => __('Projekt','default')], $cols);
	}
	return $cols;
});

\add_action('manage_'.'belege'.'_posts_custom_column', function($col, $post_id){
	if ($col !== 'cmx_beleg_projekt') return;

	foreach (cmx_meta_projekt_ids() as $k) {
		$pid = (int)\get_post_meta($post_id, $k, true);
		if ($pid > 0) {
			// $title = \get_the_title($pid) ?: ('#'.$pid);
			$title = \get_the_title($pid);
			$link  = \add_query_arg(
				['post_type'=>cmx_belege_cpt(),'cmx_proj_id'=>(int)$pid],
				\admin_url('edit.php')
			);
			echo '<a href="'.esc_url($link).'">'.esc_html($title).'</a>';
			return;
		}
	}
	foreach (cmx_meta_projekt_txts() as $k) {
		$txt = trim((string)\get_post_meta($post_id, $k, true));
		if ($txt !== '') { echo esc_html($txt); return; }
	}
	echo '';
}, 10, 2);
