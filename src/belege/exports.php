<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/* ===== Link „export“ in der Belege-Listenansicht ===== */
\add_filter('views_edit-belege', function(array $views){
	if (!\current_user_can('edit_posts')) return $views;

	$args = $_GET ?? [];
	unset($args['paged'],$args['action'],$args['action2'],$args['_wpnonce'],$args['_wp_http_referer'],$args['orderby'],$args['order']);
	$args['action'] = 'cmx_export_belege_list';

	$current_url = (is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
	$args['ref']  = rawurlencode($current_url);

	$base_args = $args;
	$base_args['action'] = 'cmx_export_belege_list';
	$url  = \wp_nonce_url(\add_query_arg($base_args, \admin_url('admin-post.php')), 'cmx_export_belege_list');
	$link = '<a href="' . esc_url($url) . '">exportieren (zip)</a>';

	$pdf_args = $args;
	$pdf_args['action'] = 'cmx_export_belege_list_pdf';
	$pdf_url = \wp_nonce_url(\add_query_arg($pdf_args, \admin_url('admin-post.php')), 'cmx_export_belege_list_pdf');
	$pdf_link = '<a href="' . esc_url($pdf_url) . '">(pdf)</a>';

	$csv_args = $args;
	$csv_args['action'] = 'cmx_export_belege_list_csv';
	$csv_url = \wp_nonce_url(\add_query_arg($csv_args, \admin_url('admin-post.php')), 'cmx_export_belege_list_csv');
	$csv_link = '<a href="' . esc_url($csv_url) . '">(csv)</a>';

	$links = $link . ' ' . $pdf_link . ' ' . $csv_link;

	$new = []; $inserted=false;
	foreach ($views as $key=>$html){ $new[$key]=$html; if ($key==='trash'&&!$inserted){$new['cmx_export_belege_list']=$links;$inserted=true;}}
	if(!$inserted){foreach($new as $key=>$html){if($key==='all'&&!$inserted){$new['cmx_export_belege_list']=$links;$inserted=true;}}}
	if(!$inserted)$new['cmx_export_belege_list']=$links;
	return $new;
});

/* ===== IDs sammeln ===== */
function cmxbu_belege_export_collect_ids(): array {
	$selected_ids = isset($_REQUEST['post']) ? array_filter(array_map('intval',(array)$_REQUEST['post'])) : [];

	$qv = [
		'post_type'      => 'belege',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'orderby'        => 'ID',
		'order'          => 'ASC',
	];

	$ref_qs=[]; $ref=$_REQUEST['ref']??'';
	if($ref!==''){ $parts=\wp_parse_url(rawurldecode($ref)); if(!empty($parts['query'])) parse_str($parts['query'],$ref_qs); }
	$src = $ref_qs ?: $_REQUEST;

	foreach(['s','author','m','post_status'] as $k){
		$v=$src[$k]??'';
		if($v!=='' && $v!=='0' && $v!=='-1') $qv[$k]=$v;
	}

	$tax_query=[]; $taxos=\get_object_taxonomies('belege','objects');
	foreach($taxos as $tax){
		$candidates = array_values(array_unique(array_filter([
			$tax->query_var ?? '',
			$tax->name,
			'filter_' . ($tax->name ?? ''),
			(isset($tax->query_var) && $tax->query_var!=='') ? ('filter_'.$tax->query_var) : '',
		])));
		$val=null;
		foreach($candidates as $param){
			if(!array_key_exists($param,$src)) continue;
			$tmp=$src[$param];
			if(is_array($tmp)){
				$tmp=array_values(array_filter($tmp,static fn($v)=>$v!==''&&$v!=='0'&&$v!=='-1'));
				if($tmp){ $val=$tmp; break; }
			}else{
				if($tmp!==''&&$tmp!=='0'&&$tmp!=='-1'){ $val=$tmp; break; }
			}
		}
		if($val!==null){
			$field = is_array($val) ? (is_numeric(reset($val)) ? 'term_id' : 'slug') : (is_numeric($val) ? 'term_id' : 'slug');
			$tax_query[] = ['taxonomy'=>$tax->name,'field'=>$field,'terms'=>is_array($val)?$val:[$val]];
		}
	}
	if($tax_query) $qv['tax_query'] = array_merge(['relation'=>'AND'],$tax_query);

	if($selected_ids){ $qv['post__in']=$selected_ids; $qv['orderby']='post__in'; }

	$q = new \WP_Query($qv);
	return $q->posts;
}

function cmxbu_belege_export_site_prefix(): string {
	$host = strtolower((string) \wp_parse_url(\home_url('/'), PHP_URL_HOST));
	if ($host === '') return 'misbuero';

	$prefix = '';
	$suffix = '.misbuero.ch';
	if (str_ends_with($host, $suffix)) {
		$left = substr($host, 0, -strlen($suffix));
		if ($left !== '') {
			$parts = array_values(array_filter(explode('.', $left)));
			if ($parts) $prefix = (string) end($parts);
		}
	}

	if ($prefix === '') {
		$parts = array_values(array_filter(explode('.', $host)));
		$prefix = (string)($parts[0] ?? 'misbuero');
	}

	$prefix = strtolower(trim((string) preg_replace('~[^a-z0-9_-]+~', '-', $prefix), '-_'));
	return $prefix !== '' ? $prefix : 'misbuero';
}

function cmxbu_belege_export_now_stamp(): string {
	if (function_exists('wp_date')) {
		return (string) \wp_date('Ymd-His');
	}
	return (string) \date_i18n('Ymd-His');
}

function cmxbu_belege_export_filename(string $ext): string {
	$ext = strtolower(trim($ext, ". \t\n\r\0\x0B"));
	if ($ext === '') $ext = 'dat';
	return cmxbu_belege_export_site_prefix() . '-belege-export-' . cmxbu_belege_export_now_stamp() . '.' . $ext;
}

require_once __DIR__ . '/exports_CSV.php';
require_once __DIR__ . '/exports_pdf.php';
require_once __DIR__ . '/exports_zip.php';
