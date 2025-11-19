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

	$current = isset($_GET['cmx_proj_id']) ? (string)(int) $_GET['cmx_proj_id'] : '';

	// 1) verwendete Projekt-IDs aus den Belegen einsammeln
	$meta_keys = array_filter(array_map('strval', cmx_meta_projekt_ids()));
	if (empty($meta_keys)) return;

	global $wpdb;
	$ph = implode(',', array_fill(0, count($meta_keys), '%s'));
	$sql = "
		SELECT DISTINCT pm.meta_value
		FROM {$wpdb->postmeta} pm
		INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		WHERE p.post_type = %s
		  AND p.post_status NOT IN ('trash','auto-draft')
		  AND pm.meta_key IN ($ph)
		  AND pm.meta_value REGEXP '^[0-9]+$'
		  AND CAST(pm.meta_value AS UNSIGNED) > 0
	";
	$params = array_merge([cmx_belege_cpt()], $meta_keys);
	$used_ids = array_values(array_unique(array_map('intval', array_filter($wpdb->get_col($wpdb->prepare($sql, $params))))));
	if (empty($used_ids)) $used_ids = [0]; // verhindert leere post__in Abfrage

	// 2) passende Projekte laden (nur die verwendeten)
	$projects = \get_posts([
		'post_type'      => cmx_projekte_cpt(),
		'post_status'    => ['publish','private','draft','pending','future'],
		'posts_per_page' => -1,
		'post__in'       => $used_ids,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	]);

	// 3) Selectbox rendern (Struktur unverändert)
	echo '<div id="cmx-projekt-filter-wrap" style="display:inline-block;margin-right:8px">';
	echo '<label class="screen-reader-text" for="cmx-projekt-select">Projekt filtern</label>';
	echo '<select id="cmx-projekt-select" name="cmx_proj_id">';
	echo '<option value="">'.esc_html__('Alle Projekte', 'default').'</option>';

	foreach ($projects as $pid) {
		$st = \get_post_status($pid);
		if (!$st || $st === 'trash' || $st === 'auto-draft') continue;
		$title = \get_the_title($pid);
		$val   = (string)(int)$pid;
		echo '<option value="'.esc_attr($val).'"'.\selected($current, $val, false).'>'.esc_html($title).'</option>';
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
			// Nur wenn unser Select existiert → saubere URL bauen
			var s = document.getElementById('cmx-projekt-select');
			if(!s) return;

			e.preventDefault(); // Standard-Submit unterbinden

			// Ziel: /wp-admin/edit.php?post_type=belege&cmx_proj_id={ID?}&paged=1
			var u = new URL(window.location.origin + "<?php echo $basePath; ?>");
			u.searchParams.set('post_type', '<?php echo $postType; ?>');

			var id = (s.value || '').trim();
			if (id) {
				u.searchParams.set('cmx_proj_id', id);
			}
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

\add_action('pre_get_posts', function($q){
	if (!is_admin() || !$q->is_main_query()) return;

	$screen = function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || $screen->id !== 'edit-'.cmx_belege_cpt()) return;

	$proj = isset($_GET['cmx_proj_id']) ? (int) $_GET['cmx_proj_id'] : 0;
	if ($proj <= 0) return;

	$keys = array_filter(array_map('strval', cmx_meta_projekt_ids()));
	if (empty($keys)) return;

	$mq = ['relation' => 'OR'];
	foreach ($keys as $k) {
		$mq[] = ['key' => $k, 'value' => (string)$proj, 'compare' => '='];
	}
	$q->set('meta_query', $mq);
});


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
	echo '—';
}, 10, 2);
