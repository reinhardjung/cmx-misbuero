<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

function cmxbu_belege_export_normalize_ref(string $raw_ref): string {
	$fallback = \admin_url('edit.php?post_type=belege');
	$ref = \trim(\rawurldecode($raw_ref));
	if ($ref === '') return $fallback;

	$ref = (string) \remove_query_arg(['cmx_export', 'cmx_export_error'], $ref);
	return (string) \wp_validate_redirect($ref, $fallback);
}

function cmxbu_belege_export_current_list_ref(): string {
	$scheme = \is_ssl() ? 'https://' : 'http://';
	$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
	$uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
	$current = $scheme . $host . $uri;
	return cmxbu_belege_export_normalize_ref($current);
}

function cmxbu_belege_export_request_ref(): string {
	$raw = (string) ($_REQUEST['ref'] ?? '');
	if ($raw !== '') {
		return cmxbu_belege_export_normalize_ref($raw);
	}
	return cmxbu_belege_export_current_list_ref();
}

function cmxbu_belege_export_normalize_date(string $raw_date): string {
	$raw_date = \trim($raw_date);
	if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_date)) return '';
	[$y, $m, $d] = \array_map('intval', \explode('-', $raw_date));
	if (!\checkdate($m, $d, $y)) return '';
	return \sprintf('%04d-%02d-%02d', $y, $m, $d);
}

function cmxbu_belege_export_requested_date_range(): array {
	$from = cmxbu_belege_export_normalize_date((string) ($_REQUEST['cmx_export_date_from'] ?? ''));
	$to   = cmxbu_belege_export_normalize_date((string) ($_REQUEST['cmx_export_date_to'] ?? ''));

	if ($from !== '' && $to !== '' && $from > $to) {
		[$from, $to] = [$to, $from];
	}

	return ['from' => $from, 'to' => $to];
}

function cmxbu_belege_export_require_date_range_or_redirect(): array {
	$range = cmxbu_belege_export_requested_date_range();
	if ($range['from'] !== '' && $range['to'] !== '') return $range;

	$args = [
		'post_type' => 'belege',
		'cmx_export' => 1,
		'cmx_export_error' => 'missing_range',
		'ref' => cmxbu_belege_export_request_ref(),
	];
	if ($range['from'] !== '') $args['cmx_export_date_from'] = $range['from'];
	if ($range['to'] !== '') $args['cmx_export_date_to'] = $range['to'];

	$target = \add_query_arg($args, \admin_url('edit.php'));
	\wp_safe_redirect($target);
	exit;
}

function cmxbu_belege_export_verify_nonce(string $specific_action): bool {
	$nonce = (string) ($_REQUEST['_wpnonce'] ?? '');
	if ($nonce === '') return false;
	if (\wp_verify_nonce($nonce, 'cmx_export_belege_range')) return true;
	return \wp_verify_nonce($nonce, $specific_action);
}

function cmxbu_belege_export_post_date(int $post_id): string {
	$belegdatum = (string) \get_post_meta(
		$post_id,
		\defined(__NAMESPACE__.'\\CMX_BELEG_META_RNG_DATUM') ? CMX_BELEG_META_RNG_DATUM : '_cmx_beleg_rng_datum',
		true
	);
	if ($belegdatum === '') {
		$post = \get_post($post_id);
		if (!$post) return '';
		$belegdatum = \get_date_from_gmt((string) $post->post_date_gmt, 'Y-m-d');
		if ($belegdatum === '') {
			$belegdatum = \mysql2date('Y-m-d', (string) $post->post_date, false);
		}
	}
	return cmxbu_belege_export_normalize_date($belegdatum);
}

/* ===== Link „export“ in der Belege-Listenansicht ===== */
\add_filter('views_edit-belege', function(array $views){
	if (!\current_user_can('edit_posts')) return $views;

	$url = \add_query_arg([
		'post_type'   => 'belege',
		'cmx_export'  => 1,
		'ref'         => cmxbu_belege_export_current_list_ref(),
	], \admin_url('edit.php'));
	$links = '<a href="' . \esc_url($url) . '">exportieren</a>';

	$new = []; $inserted=false;
	foreach ($views as $key=>$html){ $new[$key]=$html; if ($key==='trash'&&!$inserted){$new['cmx_export_belege_list']=$links;$inserted=true;}}
	if(!$inserted){foreach($new as $key=>$html){if($key==='all'&&!$inserted){$new['cmx_export_belege_list']=$links;$inserted=true;}}}
	if(!$inserted)$new['cmx_export_belege_list']=$links;
	return $new;
});

/* ===== Export-Formular (Datum von/bis) in der Listenansicht ===== */
\add_action('all_admin_notices', function () {
	global $typenow;
	if ($typenow !== 'belege' || empty($_GET['cmx_export'])) return;
	if (!\current_user_can('edit_posts')) return;

	$range = cmxbu_belege_export_requested_date_range();
	$ref = cmxbu_belege_export_request_ref();
	$cancel_url = cmxbu_belege_export_normalize_ref($ref);
	$has_error = !empty($_GET['cmx_export_error']);
	?>
	<div class="notice notice-info" style="padding:20px;margin-top:15px;">
		<h2>Belege Export</h2>
		<p>Wähle <code>Datum von</code> und <code>Datum bis</code>. Erst danach kann exportiert werden.</p>
		<?php if ($has_error): ?>
			<p style="color:#b32d2e;"><strong>Bitte Datum von und Datum bis ausfüllen.</strong></p>
		<?php endif; ?>

		<form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>" id="cmx-belege-export-form">
			<?php \wp_nonce_field('cmx_export_belege_range'); ?>
			<input type="hidden" name="ref" value="<?php echo \esc_attr($ref); ?>">

			<table class="form-table" role="presentation" style="margin-top:1em;">
				<tbody>
					<tr>
						<th scope="row"><label for="cmx_export_date_from">Datum von</label></th>
						<td><input type="date" id="cmx_export_date_from" name="cmx_export_date_from" value="<?php echo \esc_attr($range['from']); ?>" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="cmx_export_date_to">Datum bis</label></th>
						<td><input type="date" id="cmx_export_date_to" name="cmx_export_date_to" value="<?php echo \esc_attr($range['to']); ?>" required></td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<button type="submit" name="action" value="cmx_export_belege_list" class="button button-primary">Exportieren ZIP</button>
				<button type="submit" name="action" value="cmx_export_belege_list_pdf" class="button">Exportieren PDF</button>
				<button type="submit" name="action" value="cmx_export_belege_list_csv" class="button">Exportieren CSV</button>
				<a href="<?php echo \esc_url($cancel_url); ?>" class="button">Abbrechen</a>
			</p>
		</form>
	</div>
	<script>
	(function(){
		var form = document.getElementById('cmx-belege-export-form');
		if (!form) return;
		form.addEventListener('submit', function () {
			var stale = form.querySelectorAll('input[data-cmx-selected="1"]');
			for (var i = 0; i < stale.length; i++) stale[i].remove();

			var checked = document.querySelectorAll('#the-list input[name="post[]"]:checked');
			for (var j = 0; j < checked.length; j++) {
				var hid = document.createElement('input');
				hid.type = 'hidden';
				hid.name = 'post[]';
				hid.value = checked[j].value;
				hid.setAttribute('data-cmx-selected', '1');
				form.appendChild(hid);
			}
		});
	})();
	</script>
	<?php
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
	$post_ids = \array_map('intval', (array) $q->posts);

	$range = cmxbu_belege_export_requested_date_range();
	if ($range['from'] === '' || $range['to'] === '') return $post_ids;

	$filtered = [];
	foreach ($post_ids as $post_id) {
		$belegdatum = cmxbu_belege_export_post_date($post_id);
		if ($belegdatum === '') continue;
		if ($belegdatum < $range['from']) continue;
		if ($belegdatum > $range['to']) continue;
		$filtered[] = $post_id;
	}
	return $filtered;
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
	if (\function_exists(__NAMESPACE__ . '\\cmx_export_filename')) {
		return (string) cmx_export_filename('belege-export', $ext);
	}
	return cmxbu_belege_export_site_prefix() . '-belege-export-' . cmxbu_belege_export_now_stamp() . '.' . $ext;
}

require_once __DIR__ . '/exports_CSV.php';
require_once __DIR__ . '/exports_pdf.php';
require_once __DIR__ . '/exports_zip.php';
