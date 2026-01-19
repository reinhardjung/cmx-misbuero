<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

use Dompdf\Dompdf;
use Dompdf\Options;

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

/* ===== Export-Handler ===== */
\add_action('admin_post_cmx_export_belege_list', function(){
	if (!\current_user_can('edit_posts')) \wp_die('Keine Berechtigung.');
	if (!\wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'cmx_export_belege_list')) \wp_die('Ungültige Anfrage.');

	$post_ids = cmxbu_belege_export_collect_ids();

	cmxbu_stream_belege_csv_from_ids($post_ids);
});

/* ===== CSV Link ===== */
\add_action('admin_post_cmx_export_belege_list_csv', function(){
	if (!\current_user_can('edit_posts')) \wp_die('Keine Berechtigung.');
	if (!\wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'cmx_export_belege_list_csv')) \wp_die('Ungültige Anfrage.');
	$post_ids = cmxbu_belege_export_collect_ids();
	cmxbu_stream_belege_csv_from_ids($post_ids);
});

/* ===== PDF Link ===== */
\add_action('admin_post_cmx_export_belege_list_pdf', function(){
	if (!\current_user_can('edit_posts')) \wp_die('Keine Berechtigung.');
	if (!\wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'cmx_export_belege_list_pdf')) \wp_die('Ungültige Anfrage.');

	$post_ids = cmxbu_belege_export_collect_ids();

	$options = new Options();
	$options->set('isRemoteEnabled', true);
	$dom = new Dompdf($options);

	$rows_html = '';
	foreach ($post_ids as $pid) {
		$post = \get_post($pid);
		if (!$post) continue;

		$belegnr = (string) $post->post_title;
		$belegdatum = (string) \get_post_meta($pid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_RNG_DATUM') ? CMX_BELEG_META_RNG_DATUM : '_cmx_beleg_rng_datum', true);
		if ($belegdatum === '') {
			$belegdatum = \get_date_from_gmt(\gmdate('Y-m-d H:i:s', strtotime($post->post_date_gmt)), 'Y-m-d');
		}

		$kontakt_label = (string) \get_post_meta($pid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_LABEL') ? CMX_BELEG_META_KONTAKT_LABEL : '_cmx_beleg_kontakt_label', true);
		$kontakt_id = (int) \get_post_meta($pid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ID') ? CMX_BELEG_META_KONTAKT_ID : '_cmx_beleg_kontakt_id', true);
		$kunde = $kontakt_label !== '' ? $kontakt_label : ($kontakt_id ? (\get_the_title($kontakt_id) ?: '') : '');

		$belegtyp = '';
		if (function_exists(__NAMESPACE__ . '\\cmx_get_beleg_type')) {
			[, $belegtyp] = cmx_get_beleg_type($post);
		}
		if ($belegtyp === '') {
			$tax = function_exists(__NAMESPACE__ . '\\cmx_belege_taxonomy') ? cmx_belege_taxonomy() : '';
			if ($tax && \taxonomy_exists($tax)) {
				$terms = \wp_get_post_terms($pid, $tax, ['fields' => 'slugs']);
				if (!\is_wp_error($terms) && !empty($terms)) $belegtyp = (string)$terms[0];
			}
		}

		$total = 0.0;
		if (function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
			$calc = cmxbu_get_beleg_positionen_calc($pid);
			$total = (float)($calc['total'] ?? 0);
			$has_positions = cmxbu_beleg_has_positions($calc);
			if (!$has_positions) {
				$override = (string) \get_post_meta($pid, '_cmx_beleg_summe_override', true);
				if ($override !== '') {
					$total = (float) cmx_norm_decimal($override);
				}
			}
		}
		$total_str = number_format((float)$total, 2, ',', "'");

		$rows_html .= '<tr>'
			.'<td>'.\esc_html($belegnr).'</td>'
			.'<td>'.\esc_html($belegtyp).'</td>'
			.'<td>'.\esc_html($belegdatum).'</td>'
			.'<td>'.\esc_html($kunde).'</td>'
			.'<td style="text-align:right;">'.\esc_html($total_str).'</td>'
			.'</tr>';
	}

	$html = '<!doctype html><html><head><meta charset="utf-8"><style>
		body{font-family:DejaVu Sans, Arial, sans-serif;font-size:11px;color:#111}
		h1{font-size:14px;margin:0 0 8px 0}
		table{width:100%;border-collapse:collapse}
		th,td{border:1px solid #ddd;padding:6px}
		th{background:#f3f4f6;text-align:left}
	</style></head><body>
	<h1>Belege Export</h1>
	<table>
	<thead><tr>
	<th>Belegnummer</th><th>Belegtyp</th><th>Datum</th><th>Kunde</th><th>Total</th>
	</tr></thead><tbody>'.$rows_html.'</tbody></table>
	</body></html>';

	$dom->loadHtml($html, 'UTF-8');
	$dom->setPaper('A4', 'portrait');
	$dom->render();

	$filename = 'belege-export-'.\gmdate('Ymd-His').'.pdf';
	header('Content-Type: application/pdf');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	echo $dom->output();
	exit;
});

/* ===== CSV ===== */
function cmxbu_beleg_has_positions(array $calc): bool {
	if (empty($calc['positionen']) || !is_array($calc['positionen'])) return false;
	foreach ($calc['positionen'] as $row) {
		if (!is_array($row)) continue;
		$item = trim((string)($row['artikel_name'] ?? $row['item'] ?? $row['title'] ?? ''));
		$qty = (float)($row['qty'] ?? 0);
		$unit_price = (float)($row['unit_price'] ?? 0);
		$line_total = (float)($row['line_total'] ?? 0);
		if ($item !== '' || $qty > 0 || $unit_price > 0 || $line_total > 0) return true;
	}
	return false;
}

function cmxbu_stream_belege_csv_from_ids(array $ids): void {
	\ignore_user_abort(true); if (function_exists('set_time_limit')) @set_time_limit(0);
	while (ob_get_level()>0){ @ob_end_clean(); } \nocache_headers();

	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="belege-export-'.\gmdate('Ymd-His').'.csv"');
	header('Pragma: no-cache'); header('Expires: 0');

	$fh = fopen('php://output','w'); fwrite($fh, "\xEF\xBB\xBF");

	$headers = [
		'ID',
		'Belegnummer',
		'Belegtyp',
		'Belegdatum',
		'Faelligkeitsdatum',
		'Betreff',
		'Kunde',
		'Total',
		'Waehrung',
	];
	fputcsv($fh, $headers, ';');

	foreach ($ids as $pid) {
		$post = \get_post($pid);
		if (!$post) continue;

		$belegnr = (string) $post->post_title;
		$belegdatum = (string) \get_post_meta($pid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_RNG_DATUM') ? CMX_BELEG_META_RNG_DATUM : '_cmx_beleg_rng_datum', true);
		if ($belegdatum === '') {
			$belegdatum = \get_date_from_gmt(\gmdate('Y-m-d H:i:s', strtotime($post->post_date_gmt)), 'Y-m-d');
		}
		$faellig = (string) \get_post_meta($pid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_FAELLIG') ? CMX_BELEG_META_FAELLIG : '_cmx_beleg_faelligkeitsdatum', true);
		$betreff = (string) \get_post_meta($pid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF') ? CMX_BELEG_META_BETREFF : '_cmx_beleg_betreff', true);

		$kontakt_label = (string) \get_post_meta($pid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_LABEL') ? CMX_BELEG_META_KONTAKT_LABEL : '_cmx_beleg_kontakt_label', true);
		$kontakt_id = (int) \get_post_meta($pid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ID') ? CMX_BELEG_META_KONTAKT_ID : '_cmx_beleg_kontakt_id', true);
		$kunde = $kontakt_label !== '' ? $kontakt_label : ($kontakt_id ? (\get_the_title($kontakt_id) ?: '') : '');

		$belegtyp = '';
		if (function_exists(__NAMESPACE__ . '\\cmx_get_beleg_type')) {
			[, $belegtyp] = cmx_get_beleg_type($post);
		}
		if ($belegtyp === '') {
			$tax = function_exists(__NAMESPACE__ . '\\cmx_belege_taxonomy') ? cmx_belege_taxonomy() : '';
			if ($tax && \taxonomy_exists($tax)) {
				$terms = \wp_get_post_terms($pid, $tax, ['fields' => 'slugs']);
				if (!\is_wp_error($terms) && !empty($terms)) $belegtyp = (string)$terms[0];
			}
		}

		$waehrung = (string) \get_post_meta($pid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_WAEHRUNG') ? CMX_BELEG_META_WAEHRUNG : '_cmx_beleg_waehrung', true);
		if ($waehrung === '') $waehrung = 'CHF';

		$total = 0.0;
		if (function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
			$calc = cmxbu_get_beleg_positionen_calc($pid);
			$total = (float)($calc['total'] ?? 0);
			$has_positions = cmxbu_beleg_has_positions($calc);
			if (!$has_positions) {
				$override = (string) \get_post_meta($pid, '_cmx_beleg_summe_override', true);
				if ($override !== '') {
					$total = (float) cmx_norm_decimal($override);
				}
			}
		}
		$total_str = number_format((float)$total, 2, ',', '');

		$row = [
			$pid,
			$belegnr,
			$belegtyp,
			$belegdatum,
			$faellig,
			$betreff,
			$kunde,
			$total_str,
			$waehrung,
		];
		fputcsv($fh, $row, ';');
	}
	fclose($fh);
	exit;
}
