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

function cmxbu_belege_export_presets(): array {
	return [
		'heute' => 'Heute (heute bis heute)',
		'diesen_monat' => 'Diesen Monat',
		'letzten_monat' => 'Letzten Monat',
		'dieses_quartal' => 'Dieses Quartal',
		'letztes_quartal' => 'Letztes Quartal',
		'dieses_jahr' => 'Dieses Jahr',
		'letztes_jahr' => 'Letztes Jahr',
		'benutzerdefiniert' => 'Benutzerdefiniert',
	];
}

function cmxbu_belege_export_requested_preset(): string {
	$preset = \sanitize_key((string) ($_REQUEST['cmx_export_range_preset'] ?? ''));
	$presets = cmxbu_belege_export_presets();
	if ($preset !== '' && isset($presets[$preset])) return $preset;
	return 'heute';
}

function cmxbu_belege_export_now_datetime(): \DateTimeImmutable {
	if (\function_exists('wp_timezone')) {
		return new \DateTimeImmutable('now', \wp_timezone());
	}
	return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
}

function cmxbu_belege_export_range_from_preset(string $preset): array {
	$now = cmxbu_belege_export_now_datetime();
	$today = $now->format('Y-m-d');

	switch ($preset) {
		case 'heute':
			return ['from' => $today, 'to' => $today];
		case 'diesen_monat':
			return [
				'from' => $now->modify('first day of this month')->format('Y-m-d'),
				'to' => $now->modify('last day of this month')->format('Y-m-d'),
			];
		case 'letzten_monat':
			return [
				'from' => $now->modify('first day of last month')->format('Y-m-d'),
				'to' => $now->modify('last day of last month')->format('Y-m-d'),
			];
		case 'dieses_quartal':
			$year = (int) $now->format('Y');
			$month = (int) $now->format('n');
			$q_start_month = ((int) \floor(($month - 1) / 3) * 3) + 1;
			$q_start = $now->setDate($year, $q_start_month, 1);
			$q_end = $q_start->modify('+2 months')->modify('last day of this month');
			return [
				'from' => $q_start->format('Y-m-d'),
				'to' => $q_end->format('Y-m-d'),
			];
		case 'letztes_quartal':
			$year = (int) $now->format('Y');
			$month = (int) $now->format('n');
			$q_start_month = ((int) \floor(($month - 1) / 3) * 3) + 1;
			$current_q_start = $now->setDate($year, $q_start_month, 1);
			$last_q_start = $current_q_start->modify('-3 months');
			$last_q_end = $current_q_start->modify('-1 day');
			return [
				'from' => $last_q_start->format('Y-m-d'),
				'to' => $last_q_end->format('Y-m-d'),
			];
		case 'dieses_jahr':
			$year = (int) $now->format('Y');
			return [
				'from' => \sprintf('%04d-01-01', $year),
				'to' => \sprintf('%04d-12-31', $year),
			];
		case 'letztes_jahr':
			$year = ((int) $now->format('Y')) - 1;
			return [
				'from' => \sprintf('%04d-01-01', $year),
				'to' => \sprintf('%04d-12-31', $year),
			];
		default:
			return ['from' => '', 'to' => ''];
	}
}

function cmxbu_belege_export_requested_date_range(): array {
	$from = cmxbu_belege_export_normalize_date((string) ($_REQUEST['cmx_export_date_from'] ?? ''));
	$to   = cmxbu_belege_export_normalize_date((string) ($_REQUEST['cmx_export_date_to'] ?? ''));

	if ($from === '' || $to === '') {
		$preset = cmxbu_belege_export_requested_preset();
		$preset_range = cmxbu_belege_export_range_from_preset($preset);
		if ($from === '') $from = $preset_range['from'];
		if ($to === '') $to = $preset_range['to'];
	}

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
		'cmx_export_range_preset' => cmxbu_belege_export_requested_preset(),
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

function cmxbu_belege_export_paid_date(int $post_id): string {
	$meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM')
		? CMX_BELEG_META_BEZAHLT_AM
		: '_cmx_beleg_bezahlt_am';
	$raw = (string) \get_post_meta($post_id, $meta_key, true);
	return cmxbu_belege_export_normalize_date($raw);
}

function cmxbu_belege_export_partial_payment_dates(int $post_id): array {
	$meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_ANZAHLUNGEN')
		? CMX_BELEG_META_ANZAHLUNGEN
		: '_cmx_beleg_anzahlungen';
	$raw = \get_post_meta($post_id, $meta_key, true);
	if (empty($raw)) return [];

	if (\is_string($raw)) {
		$decoded = \json_decode($raw, true);
		if (\json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
			$raw = $decoded;
		} else {
			$maybe = @\maybe_unserialize($raw);
			$raw = \is_array($maybe) ? $maybe : [];
		}
	}
	if (!\is_array($raw)) return [];

	$dates = [];
	foreach ($raw as $row) {
		if (!\is_array($row)) continue;
		$datum = cmxbu_belege_export_normalize_date((string) ($row['datum'] ?? ''));
		if ($datum === '') continue;
		$dates[$datum] = true;
	}

	return \array_keys($dates);
}

function cmxbu_belege_export_has_payment_date(int $post_id): bool {
	if (cmxbu_belege_export_paid_date($post_id) !== '') return true;
	$partial_dates = cmxbu_belege_export_partial_payment_dates($post_id);
	return !empty($partial_dates);
}

function cmxbu_belege_export_date_in_range(string $date_ymd, array $range): bool {
	$date_ymd = cmxbu_belege_export_normalize_date($date_ymd);
	if ($date_ymd === '') return false;
	$from = (string) ($range['from'] ?? '');
	$to = (string) ($range['to'] ?? '');
	if ($from === '' || $to === '') return true;
	return ($date_ymd >= $from && $date_ymd <= $to);
}

function cmxbu_belege_export_has_payment_in_range(int $post_id, array $range): bool {
	$paid_date = cmxbu_belege_export_paid_date($post_id);
	if (cmxbu_belege_export_date_in_range($paid_date, $range)) {
		return true;
	}
	$partial_dates = cmxbu_belege_export_partial_payment_dates($post_id);
	foreach ($partial_dates as $partial_date) {
		if (cmxbu_belege_export_date_in_range((string) $partial_date, $range)) {
			return true;
		}
	}
	return false;
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
	$preset = cmxbu_belege_export_requested_preset();
	$presets = cmxbu_belege_export_presets();
	$ref = cmxbu_belege_export_request_ref();
	$cancel_url = cmxbu_belege_export_normalize_ref($ref);
	$has_error = !empty($_GET['cmx_export_error']);
	?>
	<div class="notice notice-info" style="padding:20px;margin-top:15px;">
		<h2>Belege Export als Milchbüchli</h2>
		<p>Wähle <code>Datum von</code> und <code>Datum bis</code>. Erst danach kann das Milchbüchli exportiert werden.</p>
		<?php if ($has_error): ?>
			<p style="color:#b32d2e;"><strong>Bitte Datum von und Datum bis ausfüllen.</strong></p>
		<?php endif; ?>

		<form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>" id="cmx-belege-export-form">
			<?php \wp_nonce_field('cmx_export_belege_range'); ?>
			<input type="hidden" name="ref" value="<?php echo \esc_attr($ref); ?>">

			<table class="form-table" role="presentation" style="margin-top:1em;">
				<tbody>
					<tr>
						<th scope="row"><label for="cmx_export_range_preset">Zeitraum</label></th>
						<td>
							<select id="cmx_export_range_preset" name="cmx_export_range_preset">
								<?php foreach ($presets as $value => $label): ?>
									<option value="<?php echo \esc_attr($value); ?>" <?php selected($preset, $value); ?>><?php echo \esc_html($label); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
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
		var preset = document.getElementById('cmx_export_range_preset');
		var fromField = document.getElementById('cmx_export_date_from');
		var toField = document.getElementById('cmx_export_date_to');

		function pad2(n){ return (n < 10 ? '0' : '') + n; }
		function ymd(date){
			return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
		}
		function applyPreset(value){
			if (!fromField || !toField) return;
			var now = new Date();
			var from = '';
			var to = '';
			var y = now.getFullYear();
			var m = now.getMonth();

			switch (value) {
				case 'heute':
					from = ymd(now);
					to = ymd(now);
					break;
				case 'diesen_monat':
					from = ymd(new Date(y, m, 1));
					to = ymd(new Date(y, m + 1, 0));
					break;
				case 'letzten_monat':
					from = ymd(new Date(y, m - 1, 1));
					to = ymd(new Date(y, m, 0));
					break;
				case 'dieses_quartal':
					var qStartMonth = Math.floor(m / 3) * 3;
					from = ymd(new Date(y, qStartMonth, 1));
					to = ymd(new Date(y, qStartMonth + 3, 0));
					break;
				case 'letztes_quartal':
					var thisQStartMonth = Math.floor(m / 3) * 3;
					var thisQStart = new Date(y, thisQStartMonth, 1);
					var lastQStart = new Date(thisQStart.getFullYear(), thisQStart.getMonth() - 3, 1);
					var lastQEnd = new Date(thisQStart.getFullYear(), thisQStart.getMonth(), 0);
					from = ymd(lastQStart);
					to = ymd(lastQEnd);
					break;
				case 'dieses_jahr':
					from = y + '-01-01';
					to = y + '-12-31';
					break;
				case 'letztes_jahr':
					from = (y - 1) + '-01-01';
					to = (y - 1) + '-12-31';
					break;
				default:
					return;
			}
			if (from) fromField.value = from;
			if (to) toField.value = to;
		}

		if (preset) {
			preset.addEventListener('change', function () {
				if (preset.value === 'benutzerdefiniert') return;
				applyPreset(preset.value);
			});
		}

		function markCustomIfManual(){
			if (!preset) return;
			if (preset.value !== 'benutzerdefiniert') {
				preset.value = 'benutzerdefiniert';
			}
		}
		if (fromField) fromField.addEventListener('change', markCustomIfManual);
		if (toField) toField.addEventListener('change', markCustomIfManual);

		if (preset && preset.value !== 'benutzerdefiniert' && ((!fromField || !fromField.value) || (!toField || !toField.value))) {
			applyPreset(preset.value);
		}

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
	$range = cmxbu_belege_export_requested_date_range();

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

	$payment_filtered = [];
	foreach ($post_ids as $post_id) {
		if (!cmxbu_belege_export_has_payment_date($post_id)) continue;
		if (!cmxbu_belege_export_has_payment_in_range($post_id, $range)) continue;
		$payment_filtered[] = $post_id;
	}
	$post_ids = $payment_filtered;

	return $post_ids;
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

function cmxbu_belege_export_range_stamp(): string {
	$range = cmxbu_belege_export_requested_date_range();
	$from = \preg_replace('/[^0-9]/', '', (string) ($range['from'] ?? ''));
	$to   = \preg_replace('/[^0-9]/', '', (string) ($range['to'] ?? ''));

	if ($from !== '' && $to !== '') {
		if ($from === $to) return $from;
		return $from . '-' . $to;
	}

	return cmxbu_belege_export_now_stamp();
}

function cmxbu_belege_export_filename(string $ext): string {
	$ext = strtolower(trim($ext, ". \t\n\r\0\x0B"));
	if ($ext === '') $ext = 'dat';
	$prefix = cmxbu_belege_export_site_prefix();
	$base = $prefix . '-belege-' . cmxbu_belege_export_range_stamp();
	return $base . '.' . $ext;
}

require_once __DIR__ . '/exports_CSV.php';
require_once __DIR__ . '/exports_pdf.php';
require_once __DIR__ . '/exports_zip.php';
