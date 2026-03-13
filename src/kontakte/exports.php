<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/* ===== Fallback-Konstanten ===== */
if (!defined(__NAMESPACE__.'\\CMX_TAX_PHONE_LABELS')) define(__NAMESPACE__.'\\CMX_TAX_PHONE_LABELS','kontakte_telefone');
if (!defined(__NAMESPACE__.'\\CMX_TAX_MAIL_LABELS'))  define(__NAMESPACE__.'\\CMX_TAX_MAIL_LABELS','kontakte_emails');

if (!defined(__NAMESPACE__.'\\CMX_KONTAKTE_META_VORNAME'))  define(__NAMESPACE__.'\\CMX_KONTAKTE_META_VORNAME','_cmx_kontakte_vorname');
if (!defined(__NAMESPACE__.'\\CMX_KONTAKTE_META_NACHNAME')) define(__NAMESPACE__.'\\CMX_KONTAKTE_META_NACHNAME','_cmx_kontakte_nachname');
if (!defined(__NAMESPACE__.'\\CMX_KONTAKTE_META_URL'))      define(__NAMESPACE__.'\\CMX_KONTAKTE_META_URL','_cmx_kontakte_url');
if (!defined(__NAMESPACE__.'\\CMX_KONTAKTE_META_PRIVAT'))   define(__NAMESPACE__.'\\CMX_KONTAKTE_META_PRIVAT','_cmx_kontakte_privat');
if (!defined(__NAMESPACE__.'\\CMX_KONTAKTE_META_DATUM'))    define(__NAMESPACE__.'\\CMX_KONTAKTE_META_DATUM','_cmx_kontakte_datum');

if (!defined(__NAMESPACE__.'\\CMX_RECHNUNG_META_STRASSE')) define(__NAMESPACE__.'\\CMX_RECHNUNG_META_STRASSE','_cmx_rechnung_strasse');
if (!defined(__NAMESPACE__.'\\CMX_RECHNUNG_META_ZUSATZ'))  define(__NAMESPACE__.'\\CMX_RECHNUNG_META_ZUSATZ','_cmx_rechnung_zusatz');
if (!defined(__NAMESPACE__.'\\CMX_RECHNUNG_META_PLZ'))     define(__NAMESPACE__.'\\CMX_RECHNUNG_META_PLZ','_cmx_rechnung_plz');
if (!defined(__NAMESPACE__.'\\CMX_RECHNUNG_META_ORT'))     define(__NAMESPACE__.'\\CMX_RECHNUNG_META_ORT','_cmx_rechnung_ort');
if (!defined(__NAMESPACE__.'\\CMX_RECHNUNG_META_LAND'))    define(__NAMESPACE__.'\\CMX_RECHNUNG_META_LAND','_cmx_rechnung_land');

if (!defined(__NAMESPACE__.'\\CMX_LIEFER_META_STRASSE'))   define(__NAMESPACE__.'\\CMX_LIEFER_META_STRASSE','_cmx_liefer_strasse');
if (!defined(__NAMESPACE__.'\\CMX_LIEFER_META_ZUSATZ'))    define(__NAMESPACE__.'\\CMX_LIEFER_META_ZUSATZ','_cmx_liefer_zusatz');
if (!defined(__NAMESPACE__.'\\CMX_LIEFER_META_PLZ'))       define(__NAMESPACE__.'\\CMX_LIEFER_META_PLZ','_cmx_liefer_plz');
if (!defined(__NAMESPACE__.'\\CMX_LIEFER_META_ORT'))       define(__NAMESPACE__.'\\CMX_LIEFER_META_ORT','_cmx_liefer_ort');
if (!defined(__NAMESPACE__.'\\CMX_LIEFER_META_LAND'))      define(__NAMESPACE__.'\\CMX_LIEFER_META_LAND','_cmx_liefer_land');

/* ===== Link „exportieren“ in der Kontakte-Listenansicht ===== */
\add_filter('views_edit-kontakte', function(array $views){
	if (!\current_user_can('edit_posts')) return $views;

	$args = $_GET ?? [];
	unset($args['paged'],$args['action'],$args['action2'],$args['_wpnonce'],$args['_wp_http_referer'],$args['orderby'],$args['order']);
	$args['action'] = 'cmx_export_kontakte_list';

	$url  = \wp_nonce_url(\add_query_arg($args, \admin_url('admin-post.php')), 'cmx_export_kontakte_list');
	$link = '<a href="' . esc_url($url) . '">exportieren</a>';

	$new = []; $inserted=false;
	foreach ($views as $key=>$html){ $new[$key]=$html; if ($key==='trash'&&!$inserted){$new['cmx_export_kontakte_list']=$link;$inserted=true;}}
	if(!$inserted){foreach($new as $key=>$html){if($key==='all'&&!$inserted){$new['cmx_export_kontakte_list']=$link;$inserted=true;}}}
	if(!$inserted)$new['cmx_export_kontakte_list']=$link;
	return $new;
});

/* ===== Export-Handler: markierte ODER gefilterte Kontakte ===== */
\add_action('admin_post_cmx_export_kontakte_list', function(){
	if (!\current_user_can('edit_posts')) \wp_die('Keine Berechtigung.');
	if (!\wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'cmx_export_kontakte_list')) \wp_die('Ungültige Anfrage.');

	$post_ids = \function_exists(__NAMESPACE__.'\\cmxkl_collect_export_contact_ids')
		? (array) cmxkl_collect_export_contact_ids()
		: [];
	$format = \function_exists(__NAMESPACE__.'\\cmxkl_requested_export_format')
		? (string) cmxkl_requested_export_format()
		: 'zip';

	if ($format === 'zip') {
		cmxkl_stream_kontakte_export_zip_from_ids($post_ids);
	}

	cmxkl_stream_kontakte_csv_from_ids($post_ids);
});

/* ===== Helpers ===== */
if (!function_exists(__NAMESPACE__.'\\cmxkl_collect_export_contact_ids')) {
	function cmxkl_collect_export_contact_ids(): array {
		$selected_ids = isset($_REQUEST['post']) ? \array_filter(\array_map('intval', (array) $_REQUEST['post'])) : [];

		$qv = [
			'post_type'      => 'kontakte',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		];

		if ($selected_ids) {
			$qv['post__in'] = $selected_ids;
			$qv['orderby'] = 'post__in';
			$qv['post_status'] = 'any';
		} else {
			$post_status = isset($_REQUEST['post_status']) ? \sanitize_key((string) $_REQUEST['post_status']) : '';
			if ($post_status !== '' && $post_status !== 'all') {
				$qv['post_status'] = $post_status;
			} else {
				$qv['post_status'] = ['publish', 'future', 'draft', 'pending', 'private'];
			}

			foreach (['s', 'author', 'm'] as $k) {
				$v = $_REQUEST[$k] ?? '';
				if ($v !== '' && $v !== '0' && $v !== '-1') {
					$qv[$k] = $v;
				}
			}

			$tax_query = [];
			$taxos = \get_object_taxonomies('kontakte', 'objects');
			foreach ($taxos as $tax) {
				$candidates = \array_values(\array_unique(\array_filter([
					$tax->query_var ?? '',
					$tax->name,
					'filter_' . ($tax->name ?? ''),
					(isset($tax->query_var) && $tax->query_var !== '') ? ('filter_' . $tax->query_var) : '',
				])));
				$val = null;
				foreach ($candidates as $param) {
					if (!\array_key_exists($param, $_REQUEST)) continue;
					$tmp = $_REQUEST[$param];
					if (\is_array($tmp)) {
						$tmp = \array_values(\array_filter($tmp, static fn($x) => $x !== '' && $x !== '0' && $x !== '-1'));
						if ($tmp) { $val = $tmp; break; }
					} else {
						if ($tmp !== '' && $tmp !== '0' && $tmp !== '-1') { $val = $tmp; break; }
					}
				}
				if ($val !== null) {
					$field = \is_array($val) ? (\is_numeric(\reset($val)) ? 'term_id' : 'slug') : (\is_numeric($val) ? 'term_id' : 'slug');
					$tax_query[] = ['taxonomy' => $tax->name, 'field' => $field, 'terms' => \is_array($val) ? $val : [$val]];
				}
			}
			if ($tax_query) $qv['tax_query'] = \array_merge(['relation' => 'AND'], $tax_query);
		}

		$q = new \WP_Query($qv);
		return \array_values(\array_filter(\array_map('intval', (array) $q->posts)));
	}
}
if (!function_exists(__NAMESPACE__.'\\cmxkl_requested_export_format')) {
	function cmxkl_requested_export_format(): string {
		$format = isset($_REQUEST['cmx_export_format']) ? \sanitize_key((string) \wp_unslash($_REQUEST['cmx_export_format'])) : 'zip';
		return \in_array($format, ['csv', 'zip'], true) ? $format : 'csv';
	}
}
if (!function_exists(__NAMESPACE__.'\\cmxkl_export_stamp')) {
	function cmxkl_export_stamp(): string {
		$raw = isset($_REQUEST['cmx_export_stamp']) ? \sanitize_text_field((string) \wp_unslash($_REQUEST['cmx_export_stamp'])) : '';
		if (\preg_match('/^\d{8}-\d{6}$/', $raw)) {
			return $raw;
		}
		return \function_exists(__NAMESPACE__ . '\\cmx_export_now_stamp')
			? (string) cmx_export_now_stamp()
			: (string) \gmdate('Ymd-His');
	}
}
if (!function_exists(__NAMESPACE__.'\\cmxkl_export_filename')) {
	function cmxkl_export_base_filename(): string {
		$prefix = \function_exists(__NAMESPACE__ . '\\cmx_export_actor_prefix')
			? (string) cmx_export_actor_prefix()
			: 'misbuero';
		return $prefix . '-kontakte-export-' . cmxkl_export_stamp();
	}

	function cmxkl_export_filename(string $ext = 'csv'): string {
		$ext = \strtolower(\trim($ext, ". \t\n\r\0\x0B"));
		if ($ext === '') $ext = 'dat';
		return cmxkl_export_base_filename() . '.' . $ext;
	}
}
if (!function_exists(__NAMESPACE__.'\\cmxkl_term_name_by_slug')) {
	function cmxkl_term_name_by_slug(string $tax, string $slug): string {
		if ($tax==='' || $slug==='' || !\taxonomy_exists($tax)) return '';
		$t = \get_term_by('slug',$slug,$tax);
		return ($t && !\is_wp_error($t) && isset($t->name)) ? (string)$t->name : '';
	}
}
if (!function_exists(__NAMESPACE__.'\\cmxkl_country_label_from_slug')) {
	function cmxkl_country_label_from_slug(?string $slug): string {
		$slug = strtolower((string)$slug);
		if ($slug==='') return '';
		$tax = 'kontakte_laender';
		if (\taxonomy_exists($tax)) {
			$term = \get_term_by('slug',$slug,$tax);
			if ($term && !\is_wp_error($term) && isset($term->name)) return (string)$term->name;
		}
		$map = ['ch'=>'Schweiz','at'=>'Österreich','de'=>'Deutschland','li'=>'Liechtenstein','fr'=>'Frankreich','it'=>'Italien','us'=>'USA'];
		return $map[$slug] ?? strtoupper($slug);
	}
}
if (!function_exists(__NAMESPACE__.'\\cmxkl_read_comm')) {
	function cmxkl_read_comm(int $post_id): array {
		$out = [
			'telefon' => [1=>['value'=>'','label_name'=>''], 2=>['value'=>'','label_name'=>''], 3=>['value'=>'','label_name'=>'']],
			'email'   => [1=>['value'=>'','label_name'=>''], 2=>['value'=>'','label_name'=>''], 3=>['value'=>'','label_name'=>'']],
		];
		$bundle = \get_post_meta($post_id,'_cmx_kommunikation',true);
		if (!\is_array($bundle)) $bundle = [];
		for ($i=1;$i<=3;$i++){
			$tel  = \get_post_meta($post_id,"_cmx_telefon_{$i}",true);
			if ($tel==='' || $tel===null)  $tel  = $bundle['telefon'][$i]['value'] ?? '';
			$mail = \get_post_meta($post_id,"_cmx_email_{$i}",true);
			if ($mail==='' || $mail===null) $mail = $bundle['email'][$i]['value']   ?? '';
			$out['telefon'][$i]['value'] = (string)$tel;
			$out['email'][$i]['value']   = (string)$mail;

			$tel_slug  = (string)($bundle['telefon'][$i]['label'] ?? '');
			$mail_slug = (string)($bundle['email'][$i]['label']   ?? '');
			$out['telefon'][$i]['label_name'] = $tel_slug  ? cmxkl_term_name_by_slug(CMX_TAX_PHONE_LABELS,$tel_slug) : '';
			$out['email'][$i]['label_name']   = $mail_slug ? cmxkl_term_name_by_slug(CMX_TAX_MAIL_LABELS,$mail_slug)  : '';
		}
		return $out;
	}
}

/* ===== CSV-Streaming ===== */
if (!function_exists(__NAMESPACE__.'\\cmxkl_write_kontakte_csv_to_handle')) {
	function cmxkl_write_kontakte_csv_to_handle($fh, array $ids): void {
		$headers = [
			'ID','Titel','Status','Erstellt_am',
			'vorname','nachname','privat','url','url_domain_core','datum',
			'logo_url','logo_path',
			'kontakt_laender_ids','kontakt_laender_slugs','kontakt_laender_names',
			// NEU: Kategorien
			'kategorien_ids','kategorien_slugs','kategorien_names',
			// Rechnungsadresse
			'rechnung_strasse','rechnung_zusatz','rechnung_plz','rechnung_ort','rechnung_land_slug','rechnung_land_label',
			// Lieferadresse
			'liefer_strasse','liefer_zusatz','liefer_plz','liefer_ort','liefer_land_slug','liefer_land_label',
			// Stufe
			'stufe_id','stufe_slug','stufe_name',
			// Kommunikation
			'telefon_1','telefon_label_1','telefon_2','telefon_label_2','telefon_3','telefon_label_3',
			'email_1','email_label_1','email_2','email_label_2','email_3','email_label_3',
			// Roh-Metas exakt
			'cmx_datum','_cmx_rechnung_land','_cmx_liefer_land',
		];
		\fputcsv($fh, $headers, ';');

		foreach ($ids as $pid) {
			$post = \get_post($pid); if (!$post) continue;
			$created = \get_date_from_gmt(\gmdate('Y-m-d H:i:s', strtotime($post->post_date_gmt)), 'Y-m-d H:i:s');

			// Stammdaten
			$vorname  = (string)\get_post_meta($pid, CMX_KONTAKTE_META_VORNAME,  true);
			$nachname = (string)\get_post_meta($pid, CMX_KONTAKTE_META_NACHNAME, true);
			$privat   = (bool)\get_post_meta($pid, CMX_KONTAKTE_META_PRIVAT,     true);
			$url      = (string)\get_post_meta($pid, CMX_KONTAKTE_META_URL,       true);
			if ($url && !\preg_match('~^https?://~i',$url)) $url = 'https://'.\ltrim($url,'/');
			$domain   = $url ? (string)(\parse_url($url, PHP_URL_HOST) ?? '') : '';
			if ($domain) $domain = \preg_replace('~^www\.~i','',$domain);
			$datum    = (string)\get_post_meta($pid, CMX_KONTAKTE_META_DATUM, true);
			$logo_url = (string)\get_post_meta($pid, '_cmx_local_image_kontakte_url', true);
			$logo_path= (string)\get_post_meta($pid, '_cmx_local_image_kontakte_path', true);

			// Kontakt-Länder (Taxo)
			$land_ids=$land_slugs=$land_names='';
			if (\taxonomy_exists('kontakte_laender')) {
				$terms = \get_the_terms($pid,'kontakte_laender');
				if ($terms && !\is_wp_error($terms)) {
					$land_ids   = implode('|', wp_list_pluck($terms,'term_id'));
					$land_slugs = implode('|', wp_list_pluck($terms,'slug'));
					$land_names = implode('|', wp_list_pluck($terms,'name'));
				}
			}

			// NEU: Kategorien (alle Taxonomien mit 'kategor' im Namen + 'category')
			$kategorien_ids=''; $kategorien_slugs=''; $kategorien_names='';
			$cat_ids=[]; $cat_slugs=[]; $cat_names=[];
			$all_tax = \get_object_taxonomies('kontakte','objects');
			foreach ($all_tax as $tax) {
				$tn = strtolower($tax->name);
				if (strpos($tn,'kategor')===false && $tn!=='category') continue;
				$ts = \get_the_terms($pid, $tax->name);
				if ($ts && !\is_wp_error($ts)) {
					$cat_ids   = array_merge($cat_ids,   wp_list_pluck($ts,'term_id'));
					$cat_slugs = array_merge($cat_slugs, wp_list_pluck($ts,'slug'));
					$cat_names = array_merge($cat_names, wp_list_pluck($ts,'name'));
				}
			}
			if ($cat_ids)   $kategorien_ids   = implode('|', array_unique(array_map('strval',$cat_ids)));
			if ($cat_slugs) $kategorien_slugs = implode('|', array_unique($cat_slugs));
			if ($cat_names) $kategorien_names = implode('|', array_unique($cat_names));

			// Adressen
			$r_land_raw = (string)\get_post_meta($pid, CMX_RECHNUNG_META_LAND,true);
			$l_land_raw = (string)\get_post_meta($pid, CMX_LIEFER_META_LAND, true);
			$rechnung = [
				'strasse'=>(string)\get_post_meta($pid, CMX_RECHNUNG_META_STRASSE,true),
				'zusatz' =>(string)\get_post_meta($pid, CMX_RECHNUNG_META_ZUSATZ, true),
				'plz'    =>(string)\get_post_meta($pid, CMX_RECHNUNG_META_PLZ,    true),
				'ort'    =>(string)\get_post_meta($pid, CMX_RECHNUNG_META_ORT,    true),
				'land'   => strtolower($r_land_raw),
				'label'  => cmxkl_country_label_from_slug($r_land_raw),
			];
			$liefer = [
				'strasse'=>(string)\get_post_meta($pid, CMX_LIEFER_META_STRASSE,true),
				'zusatz' =>(string)\get_post_meta($pid, CMX_LIEFER_META_ZUSATZ, true),
				'plz'    =>(string)\get_post_meta($pid, CMX_LIEFER_META_PLZ,    true),
				'ort'    =>(string)\get_post_meta($pid, CMX_LIEFER_META_ORT,    true),
				'land'   => strtolower($l_land_raw),
				'label'  => cmxkl_country_label_from_slug($l_land_raw),
			];

			// Stufe (erste gefundene)
			$stufe_id=$stufe_slug=$stufe_name='';
			foreach (['kontakte_stufen','stufen','kontakte_stufe','kontakt_stufen'] as $tax) {
				if (!\taxonomy_exists($tax)) continue;
				$st = \get_the_terms($pid,$tax);
				if ($st && !\is_wp_error($st)) {
					$fst = reset($st);
					$stufe_id   = (string)$fst->term_id;
					$stufe_slug = (string)$fst->slug;
					$stufe_name = (string)$fst->name;
					break;
				}
			}

			// Kommunikation
			$comm = cmxkl_read_comm($pid);

			// Roh-Metas exakt (ungefiltert/roh)
			$cmx_datum_raw     = (string)\get_post_meta($pid, 'cmx_datum', true);
			$rechnung_land_raw = (string)\get_post_meta($pid, '_cmx_rechnung_land', true);
			$liefer_land_raw   = (string)\get_post_meta($pid, '_cmx_liefer_land',  true);

			$row = [
				(string)$pid,
				(string)$post->post_title,
				(string)$post->post_status,
				$created,

				$vorname,
				$nachname,
				$privat ? '1' : '0',
				$url,
				$domain,
				$datum,
				$logo_url,
				$logo_path,

				$land_ids,
				$land_slugs,
				$land_names,

				// NEU: Kategorien
				$kategorien_ids,
				$kategorien_slugs,
				$kategorien_names,

				$rechnung['strasse'],
				$rechnung['zusatz'],
				$rechnung['plz'],
				$rechnung['ort'],
				$rechnung['land'],
				$rechnung['label'],

				$liefer['strasse'],
				$liefer['zusatz'],
				$liefer['plz'],
				$liefer['ort'],
				$liefer['land'],
				$liefer['label'],

				$stufe_id,
				$stufe_slug,
				$stufe_name,

				$comm['telefon'][1]['value'],
				$comm['telefon_[1]']['label_name'] ?? $comm['telefon'][1]['label_name'],
				$comm['telefon'][2]['value'],
				$comm['telefon_[2]']['label_name'] ?? $comm['telefon'][2]['label_name'],
				$comm['telefon'][3]['value'],
				$comm['telefon_[3]']['label_name'] ?? $comm['telefon'][3]['label_name'],

				$comm['email'][1]['value'],
				$comm['email_[1]']['label_name'] ?? $comm['email'][1]['label_name'],
				$comm['email'][2]['value'],
				$comm['email_[2]']['label_name'] ?? $comm['email'][2]['label_name'],
				$comm['email'][3]['value'],
				$comm['email_[3]']['label_name'] ?? $comm['email'][3]['label_name'],

				$cmx_datum_raw,
				$rechnung_land_raw,
				$liefer_land_raw,
			];

			\fputcsv($fh, $row, ';');
		}
	}
}

if (!function_exists(__NAMESPACE__.'\\cmxkl_kontakte_csv_string_from_ids')) {
	function cmxkl_kontakte_csv_string_from_ids(array $ids): string {
		$fh = \fopen('php://temp', 'w+');
		if ($fh === false) {
			return '';
		}

		\fwrite($fh, "\xEF\xBB\xBF");
		cmxkl_write_kontakte_csv_to_handle($fh, $ids);
		\rewind($fh);
		$content = (string) \stream_get_contents($fh);
		fclose($fh);
		return $content;
	}
}

if (!function_exists(__NAMESPACE__.'\\cmxkl_stream_kontakte_csv_from_ids')) {
	function cmxkl_stream_kontakte_csv_from_ids(array $ids): void {
		\ignore_user_abort(true); if (function_exists('set_time_limit')) @set_time_limit(0);
		while (ob_get_level()>0){ @ob_end_clean(); } \nocache_headers();

		$filename = \function_exists(__NAMESPACE__ . '\\cmxkl_export_filename')
			? (string) cmxkl_export_filename('csv')
			: ('kontakte-export-' . \gmdate('Ymd-His') . '.csv');
		$content = \function_exists(__NAMESPACE__ . '\\cmxkl_kontakte_csv_string_from_ids')
			? (string) cmxkl_kontakte_csv_string_from_ids($ids)
			: '';

		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Pragma: no-cache');
		header('Expires: 0');
		echo $content;
		exit;
	}
}

if (!function_exists(__NAMESPACE__.'\\cmxkl_collect_kontakte_image_entries')) {
	function cmxkl_collect_kontakte_image_entries(array $ids): array {
		$entries = [];
		$seen_real = [];
		$used_names = [];

		foreach ($ids as $pid) {
			$pid = (int) $pid;
			if ($pid <= 0) continue;
			$post = \get_post($pid);
			if (!$post instanceof \WP_Post) continue;

			$title = \function_exists(__NAMESPACE__ . '\\cmx_export_slugify')
				? (string) cmx_export_slugify((string) $post->post_title, 'kontakt-' . $pid)
				: ('kontakt-' . $pid);

			$candidates = [];
			$local_path = \trim((string) \get_post_meta($pid, '_cmx_local_image_kontakte_path', true));
			if ($local_path !== '') {
				$candidates[] = ['path' => $local_path, 'suffix' => 'logo'];
			}

			$thumb_id = (int) \get_post_thumbnail_id($pid);
			if ($thumb_id > 0) {
				$thumb_path = (string) \get_attached_file($thumb_id);
				if ($thumb_path !== '') {
					$candidates[] = ['path' => $thumb_path, 'suffix' => 'bild'];
				}
			}

			foreach ($candidates as $candidate) {
				$path = (string) ($candidate['path'] ?? '');
				if ($path === '' || !\is_file($path)) continue;
				$real = \realpath($path);
				if (!$real || !\is_file($real) || isset($seen_real[$real])) continue;

				$ext = \strtolower((string) \pathinfo($real, PATHINFO_EXTENSION));
				if ($ext === '') continue;

				$filename = $title . '-' . (string) ($candidate['suffix'] ?? 'bild') . '.' . $ext;
				$filename = \function_exists(__NAMESPACE__ . '\\cmx_export_slugify')
					? (string) cmx_export_slugify((string) \pathinfo($filename, PATHINFO_FILENAME), 'kontakt-' . $pid) . '.' . $ext
					: ('kontakt-' . $pid . '.' . $ext);

				if (isset($used_names[$filename])) {
					$filename = (\function_exists(__NAMESPACE__ . '\\cmx_export_slugify')
						? (string) cmx_export_slugify($title, 'kontakt-' . $pid)
						: ('kontakt-' . $pid)
					) . '-' . $pid . '-' . (string) ($candidate['suffix'] ?? 'bild') . '.' . $ext;
				}

				$used_names[$filename] = true;
				$seen_real[$real] = true;
				$entries[] = ['abs_path' => $real, 'zip_name' => $filename];
			}
		}

		return $entries;
	}
}

if (!function_exists(__NAMESPACE__.'\\cmxkl_stream_kontakte_export_zip_from_ids')) {
	function cmxkl_stream_kontakte_export_zip_from_ids(array $ids): void {
		if (!\class_exists('\\ZipArchive')) {
			\wp_die('ZIP-Export nicht verfügbar (ZipArchive fehlt).');
		}

		\ignore_user_abort(true); if (function_exists('set_time_limit')) @set_time_limit(0);
		while (ob_get_level()>0){ @ob_end_clean(); } \nocache_headers();

		$tmpZip = \function_exists('\\wp_tempnam') ? \wp_tempnam('kontakte-export-') : \tempnam(\sys_get_temp_dir(), 'kontakte-export-');
		if (!$tmpZip) {
			\wp_die('Temporäre ZIP-Datei konnte nicht erstellt werden.');
		}

		$zip = new \ZipArchive();
		if ($zip->open($tmpZip, \ZipArchive::OVERWRITE) !== true) {
			@unlink($tmpZip);
			\wp_die('ZIP-Datei konnte nicht erstellt werden.');
		}

		$csv_content = \function_exists(__NAMESPACE__ . '\\cmxkl_kontakte_csv_string_from_ids')
			? (string) cmxkl_kontakte_csv_string_from_ids($ids)
			: '';
		$csv_name = \function_exists(__NAMESPACE__ . '\\cmxkl_export_filename')
			? (string) cmxkl_export_filename('csv')
			: ('kontakte-export-' . \gmdate('Ymd-His') . '.csv');
		if ($csv_content !== '') {
			$zip->addFromString($csv_name, $csv_content);
		}

		$entries = \function_exists(__NAMESPACE__ . '\\cmxkl_collect_kontakte_image_entries')
			? (array) cmxkl_collect_kontakte_image_entries($ids)
			: [];
		foreach ($entries as $entry) {
			$abs_path = (string) ($entry['abs_path'] ?? '');
			$zip_name = (string) ($entry['zip_name'] ?? '');
			if ($abs_path === '' || $zip_name === '' || !\is_file($abs_path)) continue;
			$zip->addFile($abs_path, 'bilder/' . \ltrim($zip_name, '/'));
		}
		$zip->close();

		$filename = \function_exists(__NAMESPACE__ . '\\cmxkl_export_filename')
			? (string) cmxkl_export_filename('zip')
			: ('kontakte-export-' . \gmdate('Ymd-His') . '.zip');
		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . (string) \filesize($tmpZip));
		header('Pragma: no-cache');
		header('Expires: 0');
		\readfile($tmpZip);
		@unlink($tmpZip);
		exit;
	}
}

/* ===== JS: markierte Kontakte via POST mitsenden ===== */
\add_action('admin_footer-edit.php', function () {
	if (($_GET['post_type'] ?? '') !== 'kontakte') return;
	$action = esc_js(\admin_url('admin-post.php'));
	$nonce  = esc_js(\wp_create_nonce('cmx_export_kontakte_list'));
	?>
	<script>
	document.addEventListener('DOMContentLoaded',function(){
		const link=[...document.querySelectorAll('.subsubsub a')]
			.find(a=>a.textContent.trim().toLowerCase()==='exportieren'||/action=cmx_export_kontakte_list/i.test(a.href));
		if(!link) return;
		link.addEventListener('click',function(e){
			const checked=[...document.querySelectorAll('tbody input[name="post[]"]:checked')]
				.map(el=>parseInt(el.value,10)).filter(v=>!isNaN(v)&&v>0);
			if(!checked.length) return;
			e.preventDefault();
			const f=document.createElement('form');
			f.method='POST';
			f.action='<?php echo $action; ?>';
			f.innerHTML='<input type="hidden" name="action" value="cmx_export_kontakte_list">'+
			            '<input type="hidden" name="_wpnonce" value="<?php echo $nonce; ?>">'+
			            '<input type="hidden" name="cmx_export_format" value="zip">';
			checked.forEach(function(id){
				const h=document.createElement('input');
				h.type='hidden';
				h.name='post[]';
				h.value=String(id);
				f.appendChild(h);
			});
			document.body.appendChild(f);
			f.submit();
		});
	});
	</script>
	<?php
});
