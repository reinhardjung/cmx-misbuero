<?php
/**
 * Projekte-Export (Listen-Ansicht) – wie "Artikel"-Export
 * - Fügt "exportieren"-Link in der Projekte-Liste ein
 * - Exportiert markierte oder gefilterte Projekte als CSV (UTF-8 BOM, Semikolon)
 * - Nimmt alle Metas dynamisch auf (meta__*) + alle Taxonomien (tax__*)
 */

namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

/* =========================================================
 * 1) "exportieren"-Link oberhalb der Projekte-Liste
 * ========================================================= */
\add_filter('views_edit-projekte', function(array $views){
	if (!\current_user_can('edit_posts')) return $views;

	$args = $_GET ?? [];
	unset(
		$args['paged'], $args['action'], $args['action2'], $args['_wpnonce'],
		$args['_wp_http_referer'], $args['orderby'], $args['order']
	);
	$args['action'] = 'cmx_export_projekte_list';

	$url  = \wp_nonce_url(\add_query_arg($args, \admin_url('admin-post.php')), 'cmx_export_projekte_list');
	$link = '<a href="' . esc_url($url) . '">exportieren</a>';

	$new_views = [];
	$inserted  = false;

	foreach ($views as $key => $html) {
		$new_views[$key] = $html;
		if ($key === 'trash' && !$inserted) {
			$new_views['cmx_export_projekte_list'] = $link;
			$inserted = true;
		}
	}
	if (!$inserted) {
		foreach ($new_views as $key => $html) {
			if ($key === 'all' && !$inserted) {
				$new_views['cmx_export_projekte_list'] = $link;
				$inserted = true;
			}
		}
	}
	if (!$inserted) $new_views['cmx_export_projekte_list'] = $link;
	return $new_views;
});

/* =========================================================
 * 2) Export-Handler
 *    - Priorität: Markierte > Gefilterte
 * ========================================================= */
\add_action('admin_post_cmx_export_projekte_list', function () {
	if (!\current_user_can('edit_posts')) \wp_die('Keine Berechtigung.');
	if (!\wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'cmx_export_projekte_list')) \wp_die('Ungültige Anfrage.');

	/* --- A) Markierte IDs --- */
	$selected_ids = [];
	if (isset($_REQUEST['post'])) {
		$selected_ids = array_filter(array_map('intval', (array) $_REQUEST['post']));
	}

	/* --- Basis-Query --- */
	$query_vars = [
		'post_type'      => 'projekte',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	];

	/* --- A priorisiert: bei Auswahl nur markierte Projekte --- */
	if ($selected_ids) {
		$query_vars['post__in'] = $selected_ids;
		$query_vars['orderby']  = 'post__in';
		$query_vars['post_status'] = 'any';
	} else {
		/* --- B) Ohne Auswahl: aktuelle Filter anwenden --- */
		$post_status = isset($_REQUEST['post_status']) ? sanitize_key((string) $_REQUEST['post_status']) : '';
		if ($post_status !== '' && $post_status !== 'all') {
			$query_vars['post_status'] = $post_status;
		} else {
			$query_vars['post_status'] = ['publish', 'future', 'draft', 'pending', 'private'];
		}

		foreach (['s', 'author', 'm'] as $f) {
			$val = $_REQUEST[$f] ?? '';
			if ($val !== '' && $val !== '0' && $val !== '-1') {
				$query_vars[$f] = $val;
			}
		}

		// Projektspezifischer Kunden-Filter
		$kunde_filter = isset($_REQUEST['cmx_kunde_filter']) ? absint(wp_unslash($_REQUEST['cmx_kunde_filter'])) : 0;
		if ($kunde_filter > 0) {
			$kontakt_meta_key = defined('CMX_KONTAKT_META') ? CMX_KONTAKT_META : '_cmx_projekt_kontakt_id';
			$query_vars['meta_query'][] = [
				'key'     => $kontakt_meta_key,
				'value'   => $kunde_filter,
				'compare' => '=',
				'type'    => 'NUMERIC',
			];
		}

		/* --- Taxonomie-Filter --- */
		$tax_query = [];
		$taxos = \get_object_taxonomies('projekte', 'objects');
		foreach ($taxos as $tax) {
			$keys = [];
			if (!empty($tax->query_var) && is_string($tax->query_var)) $keys[] = $tax->query_var;
			$keys[] = $tax->name;
			$keys = array_values(array_unique(array_filter($keys)));
			foreach ($keys as $key) {
				$val = $_REQUEST[$key] ?? '';
				if ($val === '' || $val === '0' || $val === '-1') continue;
				$tax_query[] = [
					'taxonomy' => $tax->name,
					'field'    => is_numeric($val) ? 'term_id' : 'slug',
					'terms'    => [$val],
				];
				break;
			}
		}

		// Projektspezifischer Status-Filter
		$selected_status = isset($_REQUEST['cmx_status_filter']) ? sanitize_text_field(wp_unslash($_REQUEST['cmx_status_filter'])) : '';
		if ($selected_status !== '' && $selected_status !== '0') {
			$status_tax = '';
			if (function_exists(__NAMESPACE__ . '\\cmx_projekte_detect_status_taxonomy')) {
				$status_tax = (string) cmx_projekte_detect_status_taxonomy();
			}
			if ($status_tax === '') {
				foreach (['projekte_status', 'projekt_status', 'status'] as $candidate) {
					if (taxonomy_exists($candidate) && is_object_in_taxonomy('projekte', $candidate)) {
						$status_tax = $candidate;
						break;
					}
				}
			}
			if ($status_tax !== '' && taxonomy_exists($status_tax) && is_object_in_taxonomy('projekte', $status_tax)) {
				$tax_query[] = [
					'taxonomy' => $status_tax,
					'field'    => 'slug',
					'terms'    => [$selected_status],
				];
			}
		}
		if ($tax_query) $query_vars['tax_query'] = array_merge(['relation' => 'AND'], $tax_query);
	}

	$taxos = \get_object_taxonomies('projekte', 'objects');

	/* --- Query --- */
	$q = new \WP_Query($query_vars);
	$post_ids = $q->posts;

	/* --- CSV-Header vorbereiten --- */
	$base_headers = ['post_id','post_title','post_status','post_date','post_slug','permalink','featured_image'];
	$meta_keys = [];
	$tax_headers = [];

	foreach ($post_ids as $pid) {
		foreach (\get_post_meta($pid) as $k => $vals) $meta_keys[$k] = true;
	}
	foreach ($taxos as $tax) $tax_headers[] = 'tax__'.$tax->name;

	$meta_headers = array_map(fn($k) => 'meta__'.$k, array_keys($meta_keys));
	$headers = array_merge($base_headers, $meta_headers, $tax_headers);

	/* --- CSV ausgeben --- */
	$filename = 'projekte-export-' . \gmdate('Ymd-His') . '.csv';
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	header('Pragma: no-cache');
	header('Expires: 0');

	$fh = fopen('php://output', 'w');
	fwrite($fh, "\xEF\xBB\xBF"); // BOM
	fputcsv($fh, $headers, ';');

	foreach ($post_ids as $pid) {
		$post = get_post($pid);
		$row = [
			'post_id'     => $pid,
			'post_title'  => $post->post_title,
			'post_status' => $post->post_status,
			'post_date'   => get_date_from_gmt($post->post_date_gmt, 'Y-m-d H:i:s'),
			'post_slug'   => $post->post_name,
			'permalink'   => get_permalink($pid),
			'featured_image' => get_post_thumbnail_id($pid) ? wp_get_attachment_url(get_post_thumbnail_id($pid)) : '',
		];

		// Metafelder (robust serialisiert)
		$all_meta = get_post_meta($pid);
		foreach ($meta_headers as $mh) {
			$key = substr($mh,6);
			$vals = $all_meta[$key] ?? [];
			$norm = array_map(static function($v){
				if (is_array($v) || is_object($v)) {
					return wp_json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				}
				return (string)$v;
			}, (array)$vals);
			$row[$mh] = implode(' | ', $norm);
		}

		// Taxonomien
		foreach ($taxos as $tax) {
			$key = 'tax__'.$tax->name;
			$terms = wp_get_post_terms($pid,$tax->name,['fields'=>'names']);
			$row[$key] = is_wp_error($terms) ? '' : implode(', ',$terms);
		}

		// Schreiben
		$out = [];
		foreach ($headers as $h) {
			$val = $row[$h] ?? '';
			if (is_array($val) || is_object($val)) {
				$val = wp_json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			}
			$out[] = str_replace(["\r","\n"],' ',$val);
		}
		fputcsv($fh, $out, ';');
	}
	fclose($fh);
	exit;
});

/* =========================================================
 * 3) JS: Markierte Projekte per POST senden (Priorität A)
 * ========================================================= */
\add_action('admin_footer-edit.php', function () {
	if (($_GET['post_type'] ?? '') !== 'projekte') return;
	$action = esc_js(admin_url('admin-post.php'));
	$nonce  = esc_js(wp_create_nonce('cmx_export_projekte_list'));
	?>
	<script>
	document.addEventListener('DOMContentLoaded',function(){
		const exportLink=[...document.querySelectorAll('.subsubsub a')]
			.find(a=>a.textContent.trim().toLowerCase()==='exportieren'||/action=cmx_export_projekte_list/i.test(a.href));
		if(!exportLink) return;

		exportLink.addEventListener('click',function(e){
			const checked=[...document.querySelectorAll('tbody input[name="post[]"]:checked')]
				.map(el=>parseInt(el.value)).filter(v=>!isNaN(v)&&v>0);
			if(checked.length){
				e.preventDefault();
				const f=document.createElement('form');
				f.method='POST';f.action='<?php echo $action; ?>';
				f.innerHTML='<input type="hidden" name="action" value="cmx_export_projekte_list">'+
				            '<input type="hidden" name="_wpnonce" value="<?php echo $nonce; ?>">';
				checked.forEach(id=>{
					const h=document.createElement('input');
					h.type='hidden';h.name='post[]';h.value=id;
					f.appendChild(h);
				});
				document.body.appendChild(f);f.submit();
			}
		});
	});
	</script>
	<?php
});
