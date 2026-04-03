<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/* ============================================================================
 * 0) Konstanten
 * ========================================================================== */
if (!\defined(__NAMESPACE__.'\\CMX_TAX_KONTAKTE_KATEGORIEN')) \define(__NAMESPACE__.'\\CMX_TAX_KONTAKTE_KATEGORIEN', 'kontakte_kategorien');
if (!\defined(__NAMESPACE__.'\\CMX_TERMID_KONTAKT_LIEFERANT')) \define(__NAMESPACE__.'\\CMX_TERMID_KONTAKT_LIEFERANT', 399);
if (!\defined(__NAMESPACE__.'\\CMX_TERMSLUG_KONTAKT_LIEFERANT')) \define(__NAMESPACE__.'\\CMX_TERMSLUG_KONTAKT_LIEFERANT', 'lieferant');

if (!\defined(__NAMESPACE__.'\\CMX_ARTIKEL_META_LAGERBESTAND')) \define(__NAMESPACE__.'\\CMX_ARTIKEL_META_LAGERBESTAND', '_cmx_art_lagerbestand');
if (!\defined(__NAMESPACE__.'\\CMX_ARTIKEL_META_LIEFERANT_ID')) \define(__NAMESPACE__.'\\CMX_ARTIKEL_META_LIEFERANT_ID', '_cmx_art_lieferant_id');
if (!\defined(__NAMESPACE__.'\\CMX_ARTIKEL_META_LIEFERZEIT')) \define(__NAMESPACE__.'\\CMX_ARTIKEL_META_LIEFERZEIT', '_cmx_art_lieferzeit_tage');
if (!\defined(__NAMESPACE__.'\\CMX_ARTIKEL_META_BEZUGSQUELLE')) \define(__NAMESPACE__.'\\CMX_ARTIKEL_META_BEZUGSQUELLE', '_cmx_art_bezugsquelle_url');
if (!\defined(__NAMESPACE__.'\\CMX_ARTIKEL_META_LIEFERANT_NR')) \define(__NAMESPACE__.'\\CMX_ARTIKEL_META_LIEFERANT_NR', '_cmx_art_lieferant_nr');
if (!\defined(__NAMESPACE__.'\\CMX_ARTIKEL_META_LIEFERANTEN_LISTE')) \define(__NAMESPACE__.'\\CMX_ARTIKEL_META_LIEFERANTEN_LISTE', '_cmx_art_lieferanten_liste');
if (!\defined(__NAMESPACE__.'\\CMX_ARTIKEL_META_EK')) \define(__NAMESPACE__.'\\CMX_ARTIKEL_META_EK', '_cmx_artikel_ek');

/* ============================================================================
 * 1) Kontakt-CPT & Lieferanten-Finder
 * ========================================================================== */
if (!\defined(__NAMESPACE__.'\\CMX_CANDIDATE_CPTS_KONTAKT')) {
	\define(__NAMESPACE__.'\\CMX_CANDIDATE_CPTS_KONTAKT', ['kontakte','kontakt']);
}
function cmx_kontakt_candidates_unified(): array {
	$val = \constant(__NAMESPACE__.'\\CMX_CANDIDATE_CPTS_KONTAKT');
	if (\is_string($val)) {
		$tmp = @\unserialize($val);
		$val = \is_array($tmp) ? $tmp : \preg_split('/[,;|\s]+/', $val, -1, PREG_SPLIT_NO_EMPTY);
	}
	if (!\is_array($val)) $val = ['kontakte','kontakt'];
	return \array_values(\array_unique(\array_filter($val, 'is_string'))) ?: ['kontakte','kontakt'];
}
function cmx_first_existing_kontakt_cpt_unified(): ?string {
	foreach (cmx_kontakt_candidates_unified() as $pt) if (\post_type_exists($pt)) return $pt;
	return null;
}
function cmx_taxq_kontakte_kategorien_lieferant_unified(): ?array {
	$tax = CMX_TAX_KONTAKTE_KATEGORIEN;
	if (!\taxonomy_exists($tax)) return null;
	$term_id = (int) CMX_TERMID_KONTAKT_LIEFERANT;
	if ($term_id > 0 && !empty(\term_exists($term_id, $tax))) return ['taxonomy'=>$tax,'field'=>'term_id','terms'=>[$term_id]];
	$slug = (string) CMX_TERMSLUG_KONTAKT_LIEFERANT;
	$t = $slug !== '' ? \get_term_by('slug', $slug, $tax) : false;
	return ($t && !\is_wp_error($t)) ? ['taxonomy'=>$tax,'field'=>'term_id','terms'=>[(int)$t->term_id]] : null;
}
function cmx_lieferanten_query_args_unified(string $post_type): array {
	$args = ['post_type'=>$post_type,'numberposts'=>-1,'orderby'=>'title','order'=>'ASC','post_status'=>['publish','private'],'suppress_filters'=>true,'fields'=>'ids'];
	$tax_q = [];
	if (\taxonomy_exists('lieferant')) $tax_q[] = ['taxonomy'=>'lieferant','field'=>'slug','terms'=>['lieferant']];
	foreach (['kontakt_type','kundenart'] as $tx) { if (\taxonomy_exists($tx)) { $tax_q[] = ['taxonomy'=>$tx,'field'=>'slug','terms'=>['lieferant']]; break; } }
	if ($k = cmx_taxq_kontakte_kategorien_lieferant_unified()) $tax_q[] = $k;
	if ($tax_q) $args['tax_query'] = (count($tax_q)>1) ? array_merge(['relation'=>'OR'],$tax_q) : $tax_q;
	else $args['meta_query'] = [['key'=>'is_supplier','value'=>['1',1,'true',true],'compare'=>'IN']];
	return $args;
}
function cmx_truthy_unified($v): bool { if (\is_bool($v)) return $v; $v=\strtolower(\trim((string)$v)); return \in_array($v,['1','true','yes','on','y','ja','wahr'],true); }
function cmx_post_has_lieferant_term_unified(int $post_id): bool {
	$cand = ['lieferant','kontakt_type','kundenart','stufen','kontakt_kategorie', CMX_TAX_KONTAKTE_KATEGORIEN];
	$slugs = ['lieferant','supplier','lieferanten','vendor','lieferfirma']; $pref=(int) CMX_TERMID_KONTAKT_LIEFERANT;
	foreach ($cand as $tax) {
		if (!\taxonomy_exists($tax)) continue;
		$terms = \get_the_terms($post_id, $tax);
		if (\is_wp_error($terms) || empty($terms)) continue;
		foreach ($terms as $t) {
			$slug=\is_object($t)?\strtolower((string)$t->slug):''; $id=\is_object($t)?(int)$t->term_id:0;
			if ($pref>0 && $id===$pref) return true;
			if ($slug && \in_array($slug,$slugs,true)) return true;
			if ($slug===CMX_TERMSLUG_KONTAKT_LIEFERANT) return true;
		}
	}
	return false;
}
function cmx_is_lieferant_unified(int $post_id): bool {
	if (cmx_post_has_lieferant_term_unified($post_id)) return true;
	foreach (['is_supplier','_is_supplier','lieferant','_lieferant'] as $k) if (cmx_truthy_unified(\get_post_meta($post_id,$k,true))) return true;
	return false;
}
function cmx_fetch_lieferanten_ids_unified(string $post_type): array {
	$ids = \get_posts(cmx_lieferanten_query_args_unified($post_type));
	$ids = \is_array($ids) ? \array_map('intval',$ids) : [];
	if ($ids) {
		if (\function_exists(__NAMESPACE__ . '\\cmx_kontakte_filter_selectable_ids')) {
			$ids = cmx_kontakte_filter_selectable_ids($ids);
		}
		return $ids;
	}
	$all = \get_posts(['post_type'=>$post_type,'post_status'=>['publish','private'],'posts_per_page'=>-1,'fields'=>'ids','suppress_filters'=>true]);
	$all = \is_array($all) ? \array_map('intval',$all) : [];
	if (!$all) return [];
	$out=[]; foreach ($all as $pid) if (cmx_is_lieferant_unified($pid)) $out[]=$pid;
	if (\function_exists(__NAMESPACE__ . '\\cmx_kontakte_filter_selectable_ids')) {
		$out = cmx_kontakte_filter_selectable_ids($out);
	}
	return $out;
}
function cmx_artikel_lieferanten_list_url_unified(): string {
	$kontakt_pt = cmx_first_existing_kontakt_cpt_unified();
	if (!$kontakt_pt) return '';
	$term_id = 0;
	$taxq = cmx_taxq_kontakte_kategorien_lieferant_unified();
	if (\is_array($taxq) && !empty($taxq['taxonomy']) && \is_object_in_taxonomy($kontakt_pt, (string)$taxq['taxonomy'])) {
		$field = (string)($taxq['field'] ?? '');
		$terms = (array)($taxq['terms'] ?? []);
		$first = $terms ? \reset($terms) : '';
		if ($field === 'term_id' || \is_numeric($first)) {
			$term_id = (int)$first;
		} elseif ($field === 'slug' && \is_string($first) && $first !== '') {
			$term = \get_term_by('slug', (string)$first, (string)$taxq['taxonomy']);
			if ($term && !\is_wp_error($term)) $term_id = (int)$term->term_id;
		}
	}
	$args = [
		'post_type'             => $kontakt_pt,
		'cmx_filter_lieferant'  => '1',
	];
	if ($term_id > 0) {
		// Macht in der Kontakte-Liste den aktiven Dropdown-Filter sichtbar.
		$args['filter_kundenkategorie'] = $term_id;
	}
	return \add_query_arg(
		$args,
		\admin_url('edit.php')
	);
}

// Kontakte-Liste: Lieferantenfilter über robusten Spezial-Parameter erzwingen.
\add_action('pre_get_posts', function($q) {
	if (!\is_admin() || !$q instanceof \WP_Query || !$q->is_main_query()) return;
	$post_type = (string) $q->get('post_type');
	if ($post_type === '') return;
	if (!\in_array($post_type, cmx_kontakt_candidates_unified(), true)) return;
	$flag = isset($_GET['cmx_filter_lieferant']) ? \sanitize_text_field(\wp_unslash($_GET['cmx_filter_lieferant'])) : '';
	if ($flag !== '1') return;

	$lieferant_query = cmx_lieferanten_query_args_unified($post_type);

	if (!empty($lieferant_query['tax_query']) && \is_array($lieferant_query['tax_query'])) {
		$existing_tax = $q->get('tax_query');
		$tax_queries = [];
		if (\is_array($existing_tax) && !empty($existing_tax)) $tax_queries[] = $existing_tax;
		$tax_queries[] = $lieferant_query['tax_query'];
		if (\count($tax_queries) === 1) {
			$q->set('tax_query', $tax_queries[0]);
		} else {
			$q->set('tax_query', \array_merge(['relation' => 'AND'], $tax_queries));
		}
	}

	if (!empty($lieferant_query['meta_query']) && \is_array($lieferant_query['meta_query'])) {
		$existing_meta = $q->get('meta_query');
		$meta_queries = [];
		if (\is_array($existing_meta) && !empty($existing_meta)) $meta_queries[] = $existing_meta;
		$meta_queries[] = $lieferant_query['meta_query'];
		if (\count($meta_queries) === 1) {
			$q->set('meta_query', $meta_queries[0]);
		} else {
			$q->set('meta_query', \array_merge(['relation' => 'AND'], $meta_queries));
		}
	}
}, 20);
\add_action('admin_notices', function() {
	if (!\is_admin()) return;
	$post_type = isset($_GET['post_type']) ? \sanitize_text_field(\wp_unslash($_GET['post_type'])) : '';
	if (!\in_array($post_type, cmx_kontakt_candidates_unified(), true)) return;
	$flag = isset($_GET['cmx_filter_lieferant']) ? \sanitize_text_field(\wp_unslash($_GET['cmx_filter_lieferant'])) : '';
	$cat_raw = isset($_GET['filter_kundenkategorie']) ? \sanitize_text_field(\wp_unslash($_GET['filter_kundenkategorie'])) : '';
	$stufen_raw = isset($_GET['filter_stufen']) ? \sanitize_text_field(\wp_unslash($_GET['filter_stufen'])) : '';
	$type_raw = isset($_GET['filter_kontakt_type']) ? \sanitize_text_field(\wp_unslash($_GET['filter_kontakt_type'])) : '';
	if ($flag !== '1' && $cat_raw === '' && $stufen_raw === '' && $type_raw === '') return;

	$active = [];
	if ($flag === '1') $active[] = 'Lieferanten';

	$term_name = static function(string $taxonomy, string $raw): string {
		if ($taxonomy === '' || !\taxonomy_exists($taxonomy) || $raw === '') return '';
		$term = \get_term((int)$raw, $taxonomy);
		if ($term && !\is_wp_error($term)) return (string)$term->name;
		return '';
	};
	$find_tax = static function(array $candidates): string {
		foreach ($candidates as $tax) {
			if (\taxonomy_exists((string)$tax)) return (string)$tax;
		}
		return '';
	};

	if ($cat_raw !== '') {
		$cat_tax = '';
		$cat_taxq = cmx_taxq_kontakte_kategorien_lieferant_unified();
		if (\is_array($cat_taxq) && !empty($cat_taxq['taxonomy'])) $cat_tax = (string)$cat_taxq['taxonomy'];
		if ($cat_tax === '') $cat_tax = $find_tax(['kontakte_kategorien','kontakte_kategorie','kundenkategorie','kontakt_kategorie']);
		$cat_name = $term_name($cat_tax, $cat_raw);
		$active[] = 'Kategorie: ' . ($cat_name !== '' ? $cat_name : '#' . (int)$cat_raw);
	}

	if ($stufen_raw !== '') {
		$stufen_tax = $find_tax(['stufen','kontakte_stufen','kontakt_stufen']);
		$stufen_name = $term_name($stufen_tax, $stufen_raw);
		$active[] = 'Stufe: ' . ($stufen_name !== '' ? $stufen_name : '#' . (int)$stufen_raw);
	}

	if ($type_raw !== '') {
		$type_tax = $find_tax(['kontakt_type','kundenart']);
		$type_name = $term_name($type_tax, $type_raw);
		$active[] = 'Typ: ' . ($type_name !== '' ? $type_name : '#' . (int)$type_raw);
	}

	if (empty($active)) return;

	$reset = \remove_query_arg(['cmx_filter_lieferant', 'filter_kundenkategorie', 'filter_stufen', 'filter_kontakt_type', 'paged']);
	echo '<div class="notice notice-info is-dismissible"><p><strong>Aktiver Filter:</strong> ' . \esc_html(\implode(' | ', $active)) . '. <a href="' . \esc_url($reset) . '">Filter aufheben</a></p></div>';
});

/* ============================================================================
 * 2) Metabox "Lieferanten" + Save (CPT "artikel")
 * ========================================================================== */
\add_action('add_meta_boxes', function(){
	$title = 'Lieferanten';
	$link = cmx_artikel_lieferanten_list_url_unified();
	if ($link !== '') {
		$title = '<a href="' . \esc_url($link) . '" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation();" style="font-size:14px;font-weight:700;line-height:1.3;color:#2271b1;text-decoration:none;cursor:pointer;">Lieferanten</a>';
	}
	\add_meta_box('cmx_artikel_lieferanten', $title, __NAMESPACE__.'\\cmx_artikel_lieferanten_box_html_unified', 'artikel', 'normal', 'default');
});
function cmx_normalize_url_for_label_unified(string $url): string { $u=\trim($url); if($u==='')return''; if(!\preg_match('~^https?://~i',$u))$u='https://'.$u; return \filter_var($u,\FILTER_VALIDATE_URL)?$u:''; }
function cmx_parse_swiss_decimal_unified(string $raw): ?float {
	$s = \trim($raw);
	if ($s === '') return null;
	$s = (string) \preg_replace('/[\x{00A0}\x{202F}\s]+/u', '', $s);
	$s = \str_replace(["'", "’", "‘", "`", "´", "′"], '', $s);
	$has_comma = \strpos($s, ',') !== false;
	$has_dot = \strpos($s, '.') !== false;
	if ($has_comma && $has_dot) {
		if (\strrpos($s, ',') > \strrpos($s, '.')) {
			$s = \str_replace('.', '', $s);
			$s = \str_replace(',', '.', $s);
		} else {
			$s = \str_replace(',', '', $s);
		}
	} else {
		$s = \str_replace(',', '.', $s);
	}
	if (!\is_numeric($s)) return null;
	return (float) $s;
}
function cmx_artikel_lieferanten_row_meta_key_unified(int $index, string $field): string {
	$i = max(0, $index);
	$f = \preg_replace('/[^a-z0-9_]/i', '', \strtolower($field));
	return '_cmx_art_lieferant_' . $i . '_' . $f;
}
function cmx_artikel_load_lieferanten_rows_unified(int $post_id): array {
	$legacy_lager = (int)\get_post_meta($post_id, CMX_ARTIKEL_META_LAGERBESTAND, true);
	$legacy_ltage = (int)\get_post_meta($post_id, CMX_ARTIKEL_META_LIEFERZEIT, true);
	$legacy_lieferant = (int)\get_post_meta($post_id, CMX_ARTIKEL_META_LIEFERANT_ID, true);
	$legacy_quelle = (string)\get_post_meta($post_id, CMX_ARTIKEL_META_BEZUGSQUELLE, true);
	$legacy_lfnr = (string)\get_post_meta($post_id, CMX_ARTIKEL_META_LIEFERANT_NR, true);
	$legacy_ek_raw = (string)\get_post_meta($post_id, CMX_ARTIKEL_META_EK, true);
	$legacy_ek = cmx_parse_swiss_decimal_unified($legacy_ek_raw);

	$rows = [];
	$count = (int)\get_post_meta($post_id, '_cmx_art_lieferanten_count', true);
	if ($count <= 0) {
		$max_idx = -1;
		foreach (\array_keys((array)\get_post_meta($post_id)) as $meta_key) {
			if (\preg_match('/^_cmx_art_lieferant_(\d+)_(id|nr|ek|bezugsquelle|lieferzeit_tage|lagerbestand|notiz)$/', (string)$meta_key, $m)) {
				$max_idx = max($max_idx, (int)$m[1]);
			}
		}
		if ($max_idx >= 0) $count = $max_idx + 1;
	}
	if ($count > 0) {
		for ($i = 0; $i < $count; $i++) {
			$rid = (int)\get_post_meta($post_id, cmx_artikel_lieferanten_row_meta_key_unified($i, 'id'), true);
			$rnr = \trim((string)\get_post_meta($post_id, cmx_artikel_lieferanten_row_meta_key_unified($i, 'nr'), true));
			$rek = cmx_parse_swiss_decimal_unified((string)\get_post_meta($post_id, cmx_artikel_lieferanten_row_meta_key_unified($i, 'ek'), true));
			$rurl = \trim((string)\get_post_meta($post_id, cmx_artikel_lieferanten_row_meta_key_unified($i, 'bezugsquelle'), true));
			$rltage = max(0, (int)\get_post_meta($post_id, cmx_artikel_lieferanten_row_meta_key_unified($i, 'lieferzeit_tage'), true));
			$rlager = max(0, (int)\get_post_meta($post_id, cmx_artikel_lieferanten_row_meta_key_unified($i, 'lagerbestand'), true));
			$rnotiz = \trim((string)\get_post_meta($post_id, cmx_artikel_lieferanten_row_meta_key_unified($i, 'notiz'), true));
			if ($rid <= 0 && $rnr === '' && $rek === null && $rurl === '' && $rltage === 0 && $rlager === 0 && $rnotiz === '') continue;
			$rows[] = [
				'lieferant_id' => max(0, $rid),
				'lieferant_nr' => $rnr,
				'ek' => $rek,
				'bezugsquelle' => $rurl,
				'lieferzeit_tage' => $rltage,
				'lagerbestand' => $rlager,
				'notiz' => $rnotiz,
			];
		}
		if (!empty($rows)) return $rows;
	}

	$rows_raw = \get_post_meta($post_id, CMX_ARTIKEL_META_LIEFERANTEN_LISTE, true);
	$legacy_rows = [];
	if (\is_string($rows_raw) && $rows_raw !== '') {
		$tmp = \json_decode($rows_raw, true);
		if (\json_last_error() === JSON_ERROR_NONE && \is_array($tmp)) {
			$legacy_rows = $tmp;
		} else {
			$tmp = \maybe_unserialize($rows_raw);
			if (\is_array($tmp)) $legacy_rows = $tmp;
		}
	} elseif (\is_array($rows_raw)) {
		$legacy_rows = $rows_raw;
	}
	foreach ($legacy_rows as $row_idx => $row) {
		if (!\is_array($row)) continue;
		$rid = isset($row['lieferant_id']) ? (int)$row['lieferant_id'] : 0;
		$rnr = \trim((string)($row['lieferant_nr'] ?? ''));
		$rek = cmx_parse_swiss_decimal_unified((string)($row['ek'] ?? ''));
		$rurl = \trim((string)($row['bezugsquelle'] ?? ''));
		$has_ltage = \array_key_exists('lieferzeit_tage', $row) || \array_key_exists('lieferzeit', $row);
		$has_lager = \array_key_exists('lagerbestand', $row);
		$rnotiz = \sanitize_textarea_field((string)($row['notiz'] ?? ''));
		$rltage = $has_ltage ? (int)($row['lieferzeit_tage'] ?? $row['lieferzeit'] ?? 0) : (($row_idx === 0) ? $legacy_ltage : 0);
		$rlager = $has_lager ? (int)($row['lagerbestand'] ?? 0) : (($row_idx === 0) ? $legacy_lager : 0);
		$rltage = max(0, $rltage);
		$rlager = max(0, $rlager);
		if ($rid <= 0 && $rnr === '' && $rek === null && $rurl === '' && $rltage === 0 && $rlager === 0 && $rnotiz === '') continue;
		$rows[] = [
			'lieferant_id' => max(0, $rid),
			'lieferant_nr' => $rnr,
			'ek' => $rek,
			'bezugsquelle' => $rurl,
			'lieferzeit_tage' => $rltage,
			'lagerbestand' => $rlager,
			'notiz' => $rnotiz,
		];
	}
	if (!empty($rows)) return $rows;

	if ($legacy_lieferant > 0 || $legacy_lfnr !== '' || $legacy_quelle !== '' || $legacy_ek !== null || $legacy_ltage > 0 || $legacy_lager > 0) {
		return [[
			'lieferant_id' => max(0, $legacy_lieferant),
			'lieferant_nr' => $legacy_lfnr,
			'ek' => $legacy_ek,
			'bezugsquelle' => $legacy_quelle,
			'lieferzeit_tage' => max(0, $legacy_ltage),
			'lagerbestand' => max(0, $legacy_lager),
			'notiz' => '',
		]];
	}
	return [];
}
function cmx_artikel_save_lieferanten_rows_unified(int $post_id, array $rows): void {
	foreach (\array_keys((array)\get_post_meta($post_id)) as $meta_key) {
		if (\preg_match('/^_cmx_art_lieferant_\d+_(id|nr|ek|bezugsquelle|lieferzeit_tage|lagerbestand|notiz)$/', (string)$meta_key)) {
			\delete_post_meta($post_id, $meta_key);
		}
	}
	\delete_post_meta($post_id, '_cmx_art_lieferanten_count');

	$i = 0;
	$legacy_rows = [];
	foreach ($rows as $row) {
		if (!\is_array($row)) continue;
		$rid = max(0, (int)($row['lieferant_id'] ?? 0));
		$rnr = \sanitize_text_field((string)($row['lieferant_nr'] ?? ''));
		$rek = cmx_parse_swiss_decimal_unified((string)($row['ek'] ?? ''));
		$rurl = (string)($row['bezugsquelle'] ?? '');
		$rurl = cmx_normalize_url_for_label_unified($rurl);
		$rltage = max(0, (int)($row['lieferzeit_tage'] ?? 0));
		$rlager = max(0, (int)($row['lagerbestand'] ?? 0));
		$rnotiz = \sanitize_textarea_field((string)($row['notiz'] ?? ''));
		if ($rid <= 0 && $rnr === '' && $rek === null && $rurl === '' && $rltage === 0 && $rlager === 0 && $rnotiz === '') continue;
		\update_post_meta($post_id, cmx_artikel_lieferanten_row_meta_key_unified($i, 'id'), $rid);
		\update_post_meta($post_id, cmx_artikel_lieferanten_row_meta_key_unified($i, 'nr'), $rnr);
		\update_post_meta($post_id, cmx_artikel_lieferanten_row_meta_key_unified($i, 'ek'), ($rek === null ? '' : \number_format($rek, 2, '.', '')));
		\update_post_meta($post_id, cmx_artikel_lieferanten_row_meta_key_unified($i, 'bezugsquelle'), $rurl);
		\update_post_meta($post_id, cmx_artikel_lieferanten_row_meta_key_unified($i, 'lieferzeit_tage'), $rltage);
		\update_post_meta($post_id, cmx_artikel_lieferanten_row_meta_key_unified($i, 'lagerbestand'), $rlager);
		\update_post_meta($post_id, cmx_artikel_lieferanten_row_meta_key_unified($i, 'notiz'), $rnotiz);
		$legacy_rows[] = [
			'lieferant_id' => $rid,
			'lieferant_nr' => $rnr,
			'ek' => ($rek === null ? '' : \number_format($rek, 2, '.', '')),
			'bezugsquelle' => $rurl,
			'lieferzeit_tage' => $rltage,
			'lieferzeit' => $rltage,
			'lagerbestand' => $rlager,
			'notiz' => $rnotiz,
		];
		$i++;
	}
	\update_post_meta($post_id, '_cmx_art_lieferanten_count', $i);
	if ($legacy_rows === []) {
		\delete_post_meta($post_id, CMX_ARTIKEL_META_LIEFERANTEN_LISTE);
	} else {
		\update_post_meta($post_id, CMX_ARTIKEL_META_LIEFERANTEN_LISTE, $legacy_rows);
	}
}
function cmx_artikel_lieferanten_box_html_unified(\WP_Post $post): void {
	\wp_nonce_field('cmx_artikel_lieferanten_save_unified','cmx_artikel_lieferanten_nonce_unified');
	echo '<input type="hidden" name="cmx_artikel_lieferanten_payload" value="1">';
	$normalized_rows = cmx_artikel_load_lieferanten_rows_unified((int)$post->ID);
	if (empty($normalized_rows)) {
		$normalized_rows[] = [
			'lieferant_id' => 0,
			'lieferant_nr' => '',
			'ek' => null,
			'bezugsquelle' => '',
			'lieferzeit_tage' => 0,
			'lagerbestand' => 0,
			'notiz' => '',
		];
	}

	$kontakt_pt=cmx_first_existing_kontakt_cpt_unified();
	$lieferanten_ids=$kontakt_pt?cmx_fetch_lieferanten_ids_unified($kontakt_pt):[];
	foreach ($normalized_rows as $row) {
		$current_supplier_id = (int) ($row['lieferant_id'] ?? 0);
		if ($current_supplier_id > 0) $lieferanten_ids[] = $current_supplier_id;
	}
	$lieferanten_ids = \array_values(\array_unique(\array_map('intval', $lieferanten_ids)));
	$lieferanten_posts = [];
	if ($lieferanten_ids) {
		$lieferanten_posts = \get_posts([
			'post_type'=>$kontakt_pt?:'post',
			'posts_per_page'=>-1,
			'post__in'=>$lieferanten_ids,
			'orderby'=>'title',
			'order'=>'ASC',
			'post_status'=>['publish','private'],
			'suppress_filters'=>true
		]);
	}
	$lieferanten_items = [];
	foreach ($lieferanten_posts as $lieferant_post) {
		$lieferant_id = (int) ($lieferant_post->ID ?? 0);
		if ($lieferant_id <= 0) continue;
		$lieferanten_items[] = [
			'id' => $lieferant_id,
			'title' => (string) (\get_the_title($lieferant_id) ?: '(#' . $lieferant_id . ')'),
			'edit' => (string) \get_edit_post_link($lieferant_id, ''),
		];
	}
	$lieferanten_json = \wp_json_encode($lieferanten_items) ?: '[]';
	echo '<style>
	#cmx-artikel-lieferanten-head{
		display:grid;
		grid-template-columns:minmax(220px,1.6fr) minmax(140px,1fr) minmax(120px,.8fr) minmax(320px,2.4fr) minmax(90px,.6fr) minmax(90px,.6fr);
		gap:12px 18px;
		margin:0 0 8px;
		padding:4px 10px 0;
	}
		#cmx-artikel-lieferanten-head span{
			font-weight:500;
			color:#1d2327;
		}
		#cmx-artikel-lieferanten-head span:first-child{padding-left:6px}
	#cmx-artikel-lieferanten-list{
		display:flex;
		flex-direction:column;
		gap:10px;
	}
	#cmx-artikel-lieferanten-list .cmx-lief-row{
		padding:10px;
		border:1px solid #ddd;
		border-radius:6px;
		background:#fafafa;
	}
	#cmx-artikel-lieferanten-list .cmx-lief-grid{
		display:grid;
		grid-template-columns:minmax(220px,1.6fr) minmax(140px,1fr) minmax(120px,.8fr) minmax(320px,2.4fr) minmax(90px,.6fr) minmax(90px,.6fr);
		gap:12px 18px;
		align-items:start;
	}
	#cmx-artikel-lieferanten-list .cmx-lief-field{
		min-width:0;
		box-sizing:border-box;
	}
	#cmx-artikel-lieferanten-list .cmx-lief-field::before{content:none}
	#cmx-artikel-lieferanten-list .widefat{width:100%}
	#cmx-artikel-lieferanten-list .cmx-supplier-wrap{display:flex;align-items:flex-start;gap:6px}
	#cmx-artikel-lieferanten-list .cmx-lief-suggest{position:relative;flex:1 1 auto;min-width:0}
	#cmx-artikel-lieferanten-list .cmx-lief-input-row{display:flex;align-items:center;gap:6px}
	#cmx-artikel-lieferanten-list .cmx-lief-input-row input[type=text]{flex:1 1 auto;min-width:0}
	#cmx-artikel-lieferanten-list .cmx-lief-supplier-id{display:none}
	#cmx-artikel-lieferanten-list .cmx-lief-supplier-suggest{
		position:absolute;
		z-index:40;
		left:0;
		right:0;
		max-height:240px;
		overflow:auto;
		margin:2px 0 0;
		padding:0;
		border:1px solid #ccd0d4;
		border-radius:4px;
		background:#fff;
		box-shadow:0 10px 24px rgba(0,0,0,.10);
		list-style:none;
	}
	#cmx-artikel-lieferanten-list .cmx-lief-supplier-suggest li{margin:0;padding:6px 8px;cursor:pointer}
	#cmx-artikel-lieferanten-list .cmx-lief-supplier-suggest li.active{background:#e5f3ff}
	#cmx-artikel-lieferanten-list .cmx-lief-supplier-suggest li:hover{background:#f3f4f5}
		#cmx-artikel-lieferanten-list .cmx-supplier-open{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:1px solid #ccd0d4;border-radius:4px;text-decoration:none;color:#2271b1;background:#fff;flex:0 0 28px;margin-top:6px}
	#cmx-artikel-lieferanten-list .cmx-supplier-open:hover{color:#135e96;border-color:#8c8f94;background:#f6fbff}
	#cmx-artikel-lieferanten-list .cmx-supplier-open .dashicons{font-size:16px;line-height:16px;width:16px;height:16px}
	#cmx-artikel-lieferanten-list .cmx-url-wrap{display:flex;align-items:center;gap:6px;min-width:0}
	#cmx-artikel-lieferanten-list .cmx-lief-url{flex:1 1 auto;min-width:0}
	#cmx-artikel-lieferanten-list .cmx-url-open{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border:1px solid #ccd0d4;border-radius:4px;text-decoration:none;color:#2271b1;background:#fff;flex:0 0 28px}
	#cmx-artikel-lieferanten-list .cmx-url-open:hover{color:#135e96;border-color:#8c8f94;background:#f6fbff}
	#cmx-artikel-lieferanten-list .cmx-url-open .dashicons{font-size:16px;line-height:16px;width:16px;height:16px}
	#cmx-artikel-lieferanten-list .cmx-supplier-open.is-disabled,
	#cmx-artikel-lieferanten-list .cmx-url-open.is-disabled{opacity:.35;pointer-events:none}
	#cmx-artikel-lieferanten-list .cmx-lief-lager-wrap{display:flex;align-items:center;gap:8px;min-width:0}
	#cmx-artikel-lieferanten-list .cmx-lief-lager-wrap input{flex:1 1 auto;min-width:0}
	#cmx-artikel-lieferanten-list .cmx-lief-drag{
		display:inline-flex;
		align-items:center;
		justify-content:center;
		width:36px;
		height:36px;
		border:1px solid #ccd0d4;
		border-radius:6px;
		background:#fff;
		color:#8c8f94;
		cursor:grab;
		flex:0 0 36px;
		padding:0;
	}
	#cmx-artikel-lieferanten-list .cmx-lief-drag:hover{color:#2271b1;border-color:#8c8f94;background:#f6fbff}
	#cmx-artikel-lieferanten-list .cmx-lief-drag:active{cursor:grabbing}
	#cmx-artikel-lieferanten-list .cmx-lief-drag .dashicons{font-size:18px;line-height:18px;width:18px;height:18px}
	#cmx-artikel-lieferanten-list .cmx-lief-row.is-dragging{
		opacity:.55;
		border-color:#8bb6e8;
		background:#f3f8fe;
		box-shadow:0 8px 18px rgba(17, 84, 138, .12);
	}
	#cmx-artikel-lieferanten-list .cmx-lief-note-wrap{
		display:grid;
		grid-template-columns:minmax(0,1fr) auto;
		gap:10px;
		margin-top:10px;
		align-items:end;
	}
	#cmx-artikel-lieferanten-list .cmx-lief-note-panel{min-width:0;padding:0;border:0;border-radius:0;background:transparent}
	#cmx-artikel-lieferanten-list .cmx-lief-note-label{display:block;font-weight:600;margin:0 0 6px}
	#cmx-artikel-lieferanten-list .cmx-lief-note{width:100%;min-height:44px;height:44px;resize:vertical;box-sizing:border-box}
	#cmx-artikel-lieferanten-list .cmx-lief-actions-stack{display:flex;align-items:center;justify-content:flex-end;gap:8px;white-space:nowrap;margin:0}
	#cmx-artikel-lieferanten-list .cmx-lief-add{display:none;min-width:170px;width:170px;height:36px;box-sizing:border-box;align-items:center;justify-content:center;text-align:center;padding:0 10px}
	#cmx-artikel-lieferanten-list .cmx-lief-row:last-child .cmx-lief-add{display:inline-flex;justify-content:center}
	@media (max-width: 1280px){
		#cmx-artikel-lieferanten-head{display:none}
		#cmx-artikel-lieferanten-list .cmx-lief-grid{
			grid-template-columns:repeat(2, minmax(0, 1fr));
			gap:10px 12px;
		}
		#cmx-artikel-lieferanten-list .cmx-lief-field::before{
			content:attr(data-label);
			display:block;
			font-weight:600;
			margin:0 0 4px;
			color:#1d2327;
		}
		#cmx-artikel-lieferanten-list .cmx-lief-field--supplier,
		#cmx-artikel-lieferanten-list .cmx-lief-field--quelle{grid-column:1 / -1}
		#cmx-artikel-lieferanten-list .cmx-lief-note-wrap{grid-template-columns:1fr}
		#cmx-artikel-lieferanten-list .cmx-lief-actions-stack{
			justify-content:flex-start;
			flex-wrap:wrap;
		}
	}
	@media (max-width: 782px){
		#cmx-artikel-lieferanten-list .cmx-lief-grid{grid-template-columns:1fr}
	}
	.cmx-inline-help{font-size:11px;color:#666;margin-top:6px;display:block}
	.cmx-lief-del{min-width:36px;height:36px;display:inline-flex;align-items:center;justify-content:center;padding:0 8px}
	</style>';

	echo '<div id="cmx-artikel-lieferanten-head">';
	echo '<span>Name</span>';
	echo '<span>Artikel-Nr</span>';
	echo '<span>Einkaufspreis</span>';
	echo '<span>Bezugsquelle</span>';
	echo '<span>Lieferzeit</span>';
	echo '<span>Bestand</span>';
	echo '</div>';
	echo '<div id="cmx-artikel-lieferanten-list">';

	foreach ($normalized_rows as $i => $row) {
		$r_lieferant = (int)($row['lieferant_id'] ?? 0);
		$r_lieferant_open = ($r_lieferant > 0 && \get_post_status($r_lieferant)) ? (string)\get_edit_post_link($r_lieferant, '') : '';
		$r_lieferant_title = $r_lieferant > 0 ? (string) (\get_the_title($r_lieferant) ?: '(#' . $r_lieferant . ')') : '';
		$r_lfnr = (string)($row['lieferant_nr'] ?? '');
		$r_ek = $row['ek'] ?? null;
		$r_ek_display = ($r_ek === null) ? '' : cmx_format_swiss_number((float)$r_ek, 2);
		$r_quelle = (string)($row['bezugsquelle'] ?? '');
		$r_quelle_open = cmx_normalize_url_for_label_unified($r_quelle);
		$r_ltage = max(0, (int)($row['lieferzeit_tage'] ?? 0));
		$r_lager = max(0, (int)($row['lagerbestand'] ?? 0));
		$r_notiz = (string)($row['notiz'] ?? '');
			echo '<div class="cmx-lief-row">';
			echo '<div class="cmx-lief-grid">';
			echo '<div class="cmx-lief-field cmx-lief-field--supplier" data-label="Name"><div class="cmx-supplier-wrap"><a class="cmx-supplier-open cmx-lief-supplier-open'.($r_lieferant_open === '' ? ' is-disabled' : '').'" href="'.\esc_url($r_lieferant_open !== '' ? $r_lieferant_open : '#').'" target="_blank" rel="noopener noreferrer" title="Lieferant öffnen"><span class="dashicons dashicons-edit"></span></a><div class="cmx-lief-suggest"><div class="cmx-lief-input-row"><input type="text" class="widefat cmx-lief-supplier-search" autocomplete="off" value="'.\esc_attr($r_lieferant_title).'" placeholder="Lieferant suchen..."><input type="hidden" name="cmx_artikel_lieferanten['.(int)$i.'][lieferant_id]" class="cmx-lief-supplier-id" value="'.\esc_attr((string)$r_lieferant).'"></div><ul class="cmx-lief-supplier-suggest" style="display:none"></ul></div></div></div>';
			echo '<div class="cmx-lief-field cmx-lief-field--nr" data-label="Artikel-Nr."><input type="text" name="cmx_artikel_lieferanten['.(int)$i.'][lieferant_nr]" class="widefat" value="'.\esc_attr($r_lfnr).'"></div>';
			echo '<div class="cmx-lief-field cmx-lief-field--ek" data-label="Einkaufspreis"><input type="text" inputmode="decimal" name="cmx_artikel_lieferanten['.(int)$i.'][ek]" class="widefat cmx-lief-ek" value="'.\esc_attr($r_ek_display).'"></div>';
			echo '<div class="cmx-lief-field cmx-lief-field--quelle" data-label="Bezugsquelle"><div class="cmx-url-wrap"><input type="text" inputmode="url" name="cmx_artikel_lieferanten['.(int)$i.'][bezugsquelle]" class="widefat cmx-lief-url" placeholder="https://…" value="'.\esc_attr($r_quelle).'"><a class="cmx-url-open cmx-lief-url-open'.($r_quelle_open === '' ? ' is-disabled' : '').'" href="'.\esc_url($r_quelle_open !== '' ? $r_quelle_open : '#').'" target="_blank" rel="noopener noreferrer" title="URL öffnen"><span class="dashicons dashicons-admin-site"></span></a></div></div>';
			echo '<div class="cmx-lief-field cmx-lief-field--ltage" data-label="Lieferzeit"><input type="number" min="0" step="1" name="cmx_artikel_lieferanten['.(int)$i.'][lieferzeit_tage]" class="widefat cmx-lief-int" value="'.\esc_attr((string)$r_ltage).'"></div>';
			echo '<div class="cmx-lief-field cmx-lief-field--lager" data-label="Bestand"><div class="cmx-lief-lager-wrap"><input type="number" min="0" step="1" name="cmx_artikel_lieferanten['.(int)$i.'][lagerbestand]" class="widefat cmx-lief-int" value="'.\esc_attr((string)$r_lager).'"><button type="button" class="cmx-lief-drag" draggable="true" title="Lieferanten-Reihenfolge verschieben" aria-label="Lieferanten-Reihenfolge verschieben"><span class="dashicons dashicons-move" aria-hidden="true"></span></button></div></div>';
			echo '</div>';
			echo '<div class="cmx-lief-note-wrap">';
			echo '<div class="cmx-lief-note-panel">';
			echo '<label class="cmx-lief-note-label" for="cmx_artikel_lieferanten_notiz_' . (int)$i . '">Notizen</label>';
			echo '<textarea id="cmx_artikel_lieferanten_notiz_' . (int)$i . '" name="cmx_artikel_lieferanten['.(int)$i.'][notiz]" class="cmx-lief-note">' . \esc_textarea($r_notiz) . '</textarea>';
			echo '</div>';
			echo '<div class="cmx-lief-actions-stack"><button type="button" class="button button-secondary cmx-lief-add">Lieferant hinzufügen</button><button type="button" class="button-link-delete cmx-lief-del" title="Zeile löschen"><span class="dashicons dashicons-trash" style=""></span></button></div>';
			echo '</div>';
			echo '</div>';
		}

		echo '</div>';
	if(!$kontakt_pt) echo '<span class="cmx-inline-help">Kein Kontakte-CPT gefunden (<code>kontakt</code> / <code>kontakte</code>).</span>';

echo <<<HTML
<script>
(function(){
	var supplierItems = {$lieferanten_json};
	function esc(s){
		return (s||"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");
	}
	function compareSupplierTitle(a,b){
		var at = (a && a.titleLower) ? a.titleLower : "";
		var bt = (b && b.titleLower) ? b.titleLower : "";
		if(at < bt) return -1;
		if(at > bt) return 1;
		return 0;
	}
	(supplierItems||[]).forEach(function(item){
		item.id = parseInt(item.id || 0, 10) || 0;
		item.title = item.title || "";
		item.edit = item.edit || "";
		item.titleLower = item.title.toLocaleLowerCase();
	});
	supplierItems.sort(compareSupplierTitle);
	var supplierById = {};
	supplierItems.forEach(function(item){
		if(item.id > 0) supplierById[String(item.id)] = item;
	});
	function makeNavigator(inputEl, listEl, chooseCb){
		var active=-1, items=[];
		function closeList(){
			listEl.style.display="none";
			listEl.innerHTML="";
			active=-1;
		}
		function render(arr){
			items=arr||[];
			if(!items.length){ closeList(); return; }
			listEl.innerHTML=items.map(function(it, i){
				return '<li data-index="'+i+'">'+esc(it.title)+'</li>';
			}).join("");
			listEl.style.display="block";
			active=-1;
		}
		function move(delta){
			if(!items.length) return;
			active=(active+delta+items.length)%items.length;
			Array.prototype.forEach.call(listEl.children, function(li, i){
				li.classList.toggle("active", i===active);
			});
		}
		function choose(index){
			if(index<0 || index>=items.length) return;
			chooseCb(items[index]);
			closeList();
		}
		listEl.addEventListener("mousedown", function(e){
			var li=e.target.closest("li");
			if(!li) return;
			e.preventDefault();
			choose(parseInt(li.dataset.index || "-1", 10));
		});
		inputEl.addEventListener("keydown", function(e){
			if(listEl.style.display!=="block" && (e.key==="ArrowDown" || e.key==="ArrowUp")) return;
			if(e.key==="ArrowDown"){ e.preventDefault(); move(1); }
			else if(e.key==="ArrowUp"){ e.preventDefault(); move(-1); }
			else if(e.key==="Enter"){ if(active>-1){ e.preventDefault(); choose(active); } }
			else if(e.key==="Escape"){ closeList(); }
		});
		inputEl.addEventListener("blur", function(){
			window.setTimeout(function(){
				var ae=document.activeElement;
				if(ae===inputEl || listEl.contains(ae)) return;
				closeList();
			}, 120);
		});
		document.addEventListener("click", function(e){
			if(!listEl.contains(e.target) && e.target!==inputEl){
				closeList();
			}
		});
		return {
			render: render,
			reset: function(){ items=[]; active=-1; }
		};
	}
	function parseNumber(v){
		if(typeof v!=="string") v=(v??"").toString();
		var s=v.replace(/[\s\u00A0\u202F]+/g,"").replace(/['’‘`´′]/g,"");
		var hasComma=s.indexOf(",")>-1, hasDot=s.indexOf(".")>-1;
		if(hasComma && hasDot){
			if(s.lastIndexOf(",")>s.lastIndexOf(".")){ s=s.replace(/\./g,"").replace(/,/g,"."); }
			else { s=s.replace(/,/g,""); }
		}else{
			s=s.replace(/,/g,".");
		}
		var n=parseFloat(s);
		return isNaN(n)?0:n;
	}
	function formatSwiss(n){
		var parts=(Number(n)||0).toFixed(2).split(".");
		var left=parts[0], out="";
		while(left.length>3){ out="'"+left.slice(-3)+out; left=left.slice(0,-3); }
		return left+out+"."+parts[1];
	}
	function normalizeUrl(url){
		var u=(url||"").trim();
		if(!u) return "";
		if(!/^https?:\/\//i.test(u)) u="https://"+u;
		try { new URL(u); return u; } catch(e) { return ""; }
	}
	function withHttps(url){
		var u=(url||"").trim();
		if(!u) return "";
		if(/^[a-z][a-z0-9+.-]*:\/\//i.test(u)) return u;
		if(/^\/\//.test(u)) return "https:"+u;
		return "https://"+u.replace(/^\/+/, "");
	}
		function reindex(){
			document.querySelectorAll("#cmx-artikel-lieferanten-list .cmx-lief-row").forEach(function(r, currentIndex){
				r.querySelectorAll("input,select,textarea").forEach(function(el){
					var name=el.getAttribute("name")||"";
					if(!name) return;
					el.setAttribute("name", name.replace(/\[\d+\]/, "["+currentIndex+"]"));
			});
			r.querySelectorAll("label[for]").forEach(function(el){
				var val = el.getAttribute("for") || "";
				el.setAttribute("for", val.replace(/_\d+$/, "_" + currentIndex));
			});
			r.querySelectorAll("[id]").forEach(function(el){
				var val = el.getAttribute("id") || "";
				if(/^cmx_artikel_lieferanten_notiz_\d+$/.test(val)){
					el.setAttribute("id", val.replace(/_\d+$/, "_" + currentIndex));
				}
			});
		});
	}
	function bindSelect(scope){
		(scope||document).querySelectorAll("input.cmx-lief-ek,input.cmx-lief-int").forEach(function(el){
			if(el.dataset.cmxSelBound==="1") return;
			el.dataset.cmxSelBound="1";
			el.addEventListener("focus",function(){ this.select(); });
			el.addEventListener("click",function(){ this.select(); });
		});
	}
	function bindEkFormat(scope){
		(scope||document).querySelectorAll("input.cmx-lief-ek").forEach(function(el){
			if(el.dataset.cmxEkBound==="1") return;
			el.dataset.cmxEkBound="1";
			el.addEventListener("blur",function(){
				var raw=(this.value||"").trim();
				if(raw==="") return;
				this.value=formatSwiss(parseNumber(raw));
			});
		});
	}
	function bindSupplier(scope){
		(scope||document).querySelectorAll(".cmx-supplier-wrap").forEach(function(wrap){
			if(wrap.dataset.cmxSupplierBound==="1") return;
			wrap.dataset.cmxSupplierBound="1";
			var input=wrap.querySelector("input.cmx-lief-supplier-search");
			var hidden=wrap.querySelector("input.cmx-lief-supplier-id");
			var list=wrap.querySelector("ul.cmx-lief-supplier-suggest");
			var link=wrap.querySelector("a.cmx-lief-supplier-open");
			var timer=null;
			if(!input || !hidden || !list || !link) return;
			var navigator = makeNavigator(input, list, chooseSupplier);
			function closeSuggestions(){
				list.style.display="none";
				list.innerHTML="";
				navigator.reset();
			}
			function syncFieldFromHidden(){
				var item = supplierById[String(hidden.value || "")] || null;
				if(item){
					input.value = item.title || "";
					input.dataset.selectedTitle = item.title || "";
				} else if((hidden.value || "") === "" || String(hidden.value || "0") === "0"){
					input.dataset.selectedTitle = "";
				}
			}
			function sync(){
				var item = supplierById[String(hidden.value || "")] || null;
				if(item && item.edit){
					link.href=item.edit;
					link.classList.remove("is-disabled");
				}else{
					link.href="#";
					link.classList.add("is-disabled");
				}
			}
			function chooseSupplier(item, keepFocus){
				hidden.value = item && item.id ? String(item.id) : "0";
				input.value = item && item.title ? item.title : "";
				input.dataset.selectedTitle = item && item.title ? item.title : "";
				hidden.dispatchEvent(new Event("change", {bubbles:true}));
				if(keepFocus !== false) input.focus();
			}
			function exactMatch(query){
				var q=(query||"").trim().toLocaleLowerCase();
				if(!q) return null;
				var matches=supplierItems.filter(function(item){
					return item.titleLower===q;
				});
				return matches.length===1 ? matches[0] : null;
			}
			function matchedSuppliers(query){
				var q=(query||"").trim().toLocaleLowerCase();
				var activeId = parseInt(hidden.value || "0", 10) || 0;
				var items = supplierItems.slice();
				if(q){
					items = items.filter(function(item){
						return item.titleLower.indexOf(q)!==-1;
					});
					items.sort(function(a,b){
						var aStarts = a.titleLower.indexOf(q)===0 ? 0 : 1;
						var bStarts = b.titleLower.indexOf(q)===0 ? 0 : 1;
						if(aStarts !== bStarts) return aStarts - bStarts;
						return compareSupplierTitle(a,b);
					});
				} else if(activeId > 0) {
					items.sort(function(a,b){
						if(a.id===activeId && b.id!==activeId) return -1;
						if(b.id===activeId && a.id!==activeId) return 1;
						return compareSupplierTitle(a,b);
					});
				}
				return items.slice(0, 50);
			}
			function renderSuggestions(showAll){
				var query=(input.value||"").trim();
				if(!showAll && query.length>0 && query.length<2){
					closeSuggestions();
					return;
				}
				navigator.render(matchedSuppliers(showAll ? "" : query));
			}
			input.addEventListener("input", function(){
				hidden.value = "0";
				sync();
				if(timer) clearTimeout(timer);
				var query=(input.value||"").trim();
				if(query.length===0){
					renderSuggestions(true);
					return;
				}
				if(query.length<2){
					closeSuggestions();
					return;
				}
				timer=window.setTimeout(function(){
					renderSuggestions(false);
				}, 120);
			});
			input.addEventListener("focus", function(){
				renderSuggestions((input.value||"").trim()==="");
			});
			input.addEventListener("click", function(){
				renderSuggestions((input.value||"").trim()==="");
			});
			input.addEventListener("blur", function(){
				window.setTimeout(function(){
					if(String(hidden.value || "0")==="0"){
						var match = exactMatch(input.value || "");
						if(match) chooseSupplier(match, false);
					}
				}, 130);
			});
			hidden.addEventListener("change", function(){
				syncFieldFromHidden();
				sync();
			});
			syncFieldFromHidden();
			sync();
		});
	}
	function bindUrl(scope){
		(scope||document).querySelectorAll(".cmx-url-wrap").forEach(function(wrap){
			if(wrap.dataset.cmxUrlBound==="1") return;
			wrap.dataset.cmxUrlBound="1";
			var input=wrap.querySelector("input.cmx-lief-url");
			var link=wrap.querySelector("a.cmx-lief-url-open");
			if(!input || !link) return;
			function sync(){
				var u=normalizeUrl(input.value||"");
				if(u){
					link.href=u;
					link.classList.remove("is-disabled");
				}else{
					link.href="#";
					link.classList.add("is-disabled");
				}
			}
			function applyProtocol(){
				var raw=(input.value||"").trim();
				if(raw===""){ input.value=""; return; }
				input.value=withHttps(raw);
			}
			input.addEventListener("input", sync);
			input.addEventListener("change", sync);
			input.addEventListener("blur", function(){ applyProtocol(); sync(); });
			input.addEventListener("keydown", function(e){
				if(e.key==="Enter"){
					applyProtocol();
					sync();
				}
			});
			sync();
		});
	}
		var list=document.querySelector("#cmx-artikel-lieferanten-list");
		var draggedRow=null;
		function clearDragState(){
			if(list){
				list.querySelectorAll(".cmx-lief-row.is-dragging").forEach(function(row){
					row.classList.remove("is-dragging");
				});
			}
			draggedRow=null;
		}
		function moveDraggedRow(targetRow, clientY){
			if(!list || !draggedRow || !targetRow || targetRow===draggedRow) return;
			var rect=targetRow.getBoundingClientRect();
			var insertAfter=clientY > rect.top + (rect.height / 2);
			if(insertAfter){
				if(targetRow.nextElementSibling !== draggedRow){
					list.insertBefore(draggedRow, targetRow.nextElementSibling);
				}
			}else if(targetRow.previousElementSibling !== draggedRow){
				list.insertBefore(draggedRow, targetRow);
			}
		}
		function addRow(){
				if(!list) return;
				var row=list.querySelector(".cmx-lief-row");
				if(!row) return;
				row=row.cloneNode(true);
				row.querySelectorAll("*").forEach(function(el){
					delete el.dataset.cmxSelBound;
					delete el.dataset.cmxEkBound;
					delete el.dataset.cmxSupplierBound;
					delete el.dataset.cmxUrlBound;
				});
				row.querySelectorAll("input").forEach(function(i){ i.value=""; });
				row.querySelectorAll("select").forEach(function(s){ s.value="0"; });
				row.querySelectorAll("textarea").forEach(function(t){ t.value=""; });
				row.querySelectorAll(".cmx-lief-supplier-search").forEach(function(i){ i.dataset.selectedTitle=""; });
				row.querySelectorAll(".cmx-lief-supplier-suggest").forEach(function(list){
					list.innerHTML="";
					list.style.display="none";
				});
				list.appendChild(row);
				reindex();
				bindSelect(row);
				bindEkFormat(row);
				bindSupplier(row);
				bindUrl(row);
		}
		if(list){
			list.addEventListener("dragstart", function(e){
				var handle=e.target&&e.target.closest?e.target.closest(".cmx-lief-drag"):null;
				if(!handle){
					e.preventDefault();
					return;
				}
				draggedRow=handle.closest(".cmx-lief-row");
				if(!draggedRow){
					e.preventDefault();
					return;
				}
				draggedRow.classList.add("is-dragging");
				if(e.dataTransfer){
					e.dataTransfer.effectAllowed="move";
					try{ e.dataTransfer.setData("text/plain", "lieferant-row"); }catch(err){}
				}
			});
			list.addEventListener("dragover", function(e){
				if(!draggedRow) return;
				var targetRow=e.target&&e.target.closest?e.target.closest(".cmx-lief-row"):null;
				if(!targetRow || targetRow===draggedRow) return;
				e.preventDefault();
				moveDraggedRow(targetRow, e.clientY || 0);
			});
			list.addEventListener("drop", function(e){
				if(!draggedRow) return;
				e.preventDefault();
				var targetRow=e.target&&e.target.closest?e.target.closest(".cmx-lief-row"):null;
				if(targetRow && targetRow!==draggedRow){
					moveDraggedRow(targetRow, e.clientY || 0);
				}
				reindex();
				clearDragState();
			});
			list.addEventListener("dragend", function(){
				if(!draggedRow) return;
				reindex();
				clearDragState();
			});
			list.addEventListener("click", function(e){
				var addBtn=e.target.closest(".cmx-lief-add");
				if(addBtn){
					addRow();
					return;
				}
				var btn=e.target.closest(".cmx-lief-del");
				if(!btn) return;
				var rows=list.querySelectorAll(".cmx-lief-row");
				var row=btn.closest(".cmx-lief-row");
				if(rows.length<=1){
					if(!row) return;
					row.querySelectorAll("input").forEach(function(i){ i.value=""; });
					row.querySelectorAll("select").forEach(function(s){ s.value="0"; });
					row.querySelectorAll("textarea").forEach(function(t){ t.value=""; });
					row.querySelectorAll(".cmx-lief-supplier-search").forEach(function(i){ i.dataset.selectedTitle=""; });
					row.querySelectorAll(".cmx-lief-supplier-suggest").forEach(function(list){
						list.innerHTML="";
						list.style.display="none";
					});
					row.querySelectorAll("input.cmx-lief-supplier-id").forEach(function(i){ i.dispatchEvent(new Event("change", {bubbles:true})); });
					row.querySelectorAll("input.cmx-lief-url").forEach(function(i){ i.dispatchEvent(new Event("input")); });
					return;
				}
				if(row) row.remove();
				reindex();
			});
		}
	bindSelect(document);
	bindEkFormat(document);
	bindSupplier(document);
	bindUrl(document);
})();
</script>
HTML;
}

\add_action('save_post_artikel', function (int $post_id, \WP_Post $post) {
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if ($post->post_type !== 'artikel') return;
	if (!\current_user_can('edit_post', $post_id)) return;
	if (!isset($_POST['cmx_artikel_lieferanten_nonce_unified']) || !\wp_verify_nonce($_POST['cmx_artikel_lieferanten_nonce_unified'], 'cmx_artikel_lieferanten_save_unified')) return;
	if (!isset($_POST['cmx_artikel_lieferanten_payload'])) return;

	$kontakt_pt   = cmx_first_existing_kontakt_cpt_unified();
	$allowed_ids  = $kontakt_pt ? cmx_fetch_lieferanten_ids_unified($kontakt_pt) : [];
	$rows_in = isset($_POST['cmx_artikel_lieferanten']) && \is_array($_POST['cmx_artikel_lieferanten']) ? $_POST['cmx_artikel_lieferanten'] : [];
	$rows_out = [];
	foreach ($rows_in as $row) {
		if (!\is_array($row)) continue;
		$rid = isset($row['lieferant_id']) ? (int)$row['lieferant_id'] : 0;
		$rnr = isset($row['lieferant_nr']) ? \sanitize_text_field((string)$row['lieferant_nr']) : '';
		$rek_raw = isset($row['ek']) ? (string)$row['ek'] : '';
		$rek_num = cmx_parse_swiss_decimal_unified($rek_raw);
		$rurl = isset($row['bezugsquelle']) ? (string)$row['bezugsquelle'] : '';
		$rurl = cmx_normalize_url_for_label_unified($rurl);
		$rltage = isset($row['lieferzeit_tage']) ? max(0, (int)$row['lieferzeit_tage']) : 0;
		$rlager = isset($row['lagerbestand']) ? max(0, (int)$row['lagerbestand']) : 0;
		$rnotiz = isset($row['notiz']) ? \sanitize_textarea_field((string)$row['notiz']) : '';

		$rid_valid = 0;
		if ($rid > 0 && $allowed_ids && \in_array($rid, $allowed_ids, true)) {
			$rid_valid = $rid;
		} elseif ($rid > 0 && !$allowed_ids) {
			$p = \get_post($rid);
			if ($p && $kontakt_pt && $p->post_type === $kontakt_pt) $rid_valid = $rid;
		}

		if ($rid_valid <= 0 && $rnr === '' && $rek_num === null && $rurl === '' && $rltage === 0 && $rlager === 0 && $rnotiz === '') continue;
		$rows_out[] = [
			'lieferant_id' => $rid_valid,
			'lieferant_nr' => $rnr,
			'ek' => ($rek_num === null ? '' : \number_format($rek_num, 2, '.', '')),
			'bezugsquelle' => $rurl,
			'lieferzeit_tage' => $rltage,
			'lagerbestand' => $rlager,
			'notiz' => $rnotiz,
		];
	}
	cmx_artikel_save_lieferanten_rows_unified($post_id, $rows_out);

	$first = $rows_out[0] ?? null;
	if (\is_array($first)) {
		\update_post_meta($post_id, CMX_ARTIKEL_META_LIEFERANT_ID, (int)($first['lieferant_id'] ?? 0));
		\update_post_meta($post_id, CMX_ARTIKEL_META_LIEFERANT_NR, (string)($first['lieferant_nr'] ?? ''));
		\update_post_meta($post_id, CMX_ARTIKEL_META_BEZUGSQUELLE, (string)($first['bezugsquelle'] ?? ''));
		\update_post_meta($post_id, CMX_ARTIKEL_META_LIEFERZEIT, max(0, (int)($first['lieferzeit_tage'] ?? 0)));
		\update_post_meta($post_id, CMX_ARTIKEL_META_LAGERBESTAND, max(0, (int)($first['lagerbestand'] ?? 0)));
		$ek_first_raw = (string)($first['ek'] ?? '');
		if ($ek_first_raw !== '' && \is_numeric($ek_first_raw)) {
			\update_post_meta($post_id, CMX_ARTIKEL_META_EK, \number_format((float)$ek_first_raw, 2, '.', ''));
		}
	} else {
		\update_post_meta($post_id, CMX_ARTIKEL_META_LIEFERANT_ID, 0);
		\update_post_meta($post_id, CMX_ARTIKEL_META_LIEFERANT_NR, '');
		\update_post_meta($post_id, CMX_ARTIKEL_META_BEZUGSQUELLE, '');
		\update_post_meta($post_id, CMX_ARTIKEL_META_LIEFERZEIT, 0);
		\update_post_meta($post_id, CMX_ARTIKEL_META_LAGERBESTAND, 0);
	}
}, 10, 2);

/* ============================================================================
 * 3) Beleg-Positionen – Renderer (Label enthält Link)
 *    Eindeutiger Funktionsname → keine Redeclare-Konflikte.
 * ========================================================================== */
function cmx_render_beleg_position_row_unified($i, $pos): void {
	$artikel_id   = esc_attr($pos['artikel_id'] ?? '');
	$menge_raw    = (string)($pos['menge'] ?? 1);
	$preis_raw    = (string)($pos['preis'] ?? '');
	$beschreibung = esc_textarea($pos['beschreibung'] ?? '');
	$rabatt_raw   = trim((string)($pos['rabatt'] ?? ''));
	$menge        = esc_attr(cmx_format_swiss_number($menge_raw, 2));
	$preis        = esc_attr($preis_raw === '' ? '' : cmx_format_swiss_number($preis_raw, 2));
	$rabatt_display = $rabatt_raw;
	if ($rabatt_raw !== '') {
		$is_percent = str_ends_with($rabatt_raw, '%');
		$raw = $is_percent ? substr($rabatt_raw, 0, -1) : $rabatt_raw;
		$raw = trim((string) preg_replace('/\s*(chf|fr\.?)\s*/i', '', $raw));
		if ($raw !== '' && preg_match('/\d/', $raw)) {
			$rabatt_display = cmx_format_swiss_number($raw, 2) . ($is_percent ? '%' : '');
		}
	}
	$rabatt = esc_attr($rabatt_display);

	$artikel_list_url = admin_url('edit.php?post_type=artikel');
	$artikel_edit_url = (!empty($artikel_id) && (int)$artikel_id > 0 && get_post_status((int)$artikel_id)) ? get_edit_post_link((int)$artikel_id, '') : '';
	$link_href = $artikel_edit_url ?: $artikel_list_url;

	echo '<tr class="cmx-pos-row">';

	echo '<td>';
	// LABEL mit Link (statt Link unterhalb der Auswahl)
	echo '<label class="cmx-artikel-label" for="cmx_positionen_'.$i.'_artikel_id" style="display:block;margin-bottom:4px">';
	echo '<a class="cmx-artikel-link" href="'.esc_url($link_href).'" target="" rel="noopener noreferrer">'.($artikel_edit_url ? 'Artikel öffnen' : 'Zur Artikelliste').'</a>';
	echo '</label>';

	echo '<select id="cmx_positionen_'.$i.'_artikel_id" name="cmx_positionen['.$i.'][artikel_id]" class="cmx-artikel-select">';
	echo '<option value="">— Artikel wählen —</option>';
	$q = new \WP_Query(['post_type'=>'artikel','posts_per_page'=>-1,'post_status'=>'publish','orderby'=>'title','order'=>'ASC','fields'=>'ids']);
	foreach ($q->posts as $id) {
		$title = get_the_title($id);
		printf('<option value="%d"%s>%s</option>', $id, selected($artikel_id, $id, false), esc_html($title));
	}
	wp_reset_postdata();
	echo '</select>';
	echo '</td>';

	echo '<td><input type="text" inputmode="decimal" name="cmx_positionen['.$i.'][menge]" value="'.$menge.'" style="width:70px"></td>';
	echo '<td><input type="text" name="cmx_positionen['.$i.'][preis]" value="'.$preis.'" style="width:100px"></td>';
	echo '<td class="cmx-pos-rabatt-td" style="width:100px;"><input type="text" name="cmx_positionen['.$i.'][rabatt]" value="'.$rabatt.'" style="width:100px"></td>';
	echo '<td class="cmx-pos-total" style="width:90px;text-align:right;">'.esc_html(cmx_format_swiss_number(cmx_parse_number($preis_raw) * cmx_parse_number($menge_raw), 2)).'</td>';
	echo '<td><textarea name="cmx_positionen['.$i.'][beschreibung]" rows="1" style="width:100%">'.$beschreibung.'</textarea></td>';
	echo '<td><button type="button" class="button-link-delete cmx-del-pos"><span class="dashicons dashicons-trash" style="position:relative; top:8px;"></span>✕</button></td>';

	echo '</tr>';
}

/* ============================================================================
 * 4) Admin-JS für Beleg-Positionen (passt den Label-Link dynamisch an)
 * ========================================================================== */
function cmx_beleg_positionen_js_unified(): void {
	$ajax_url   = admin_url('admin-ajax.php');
	$admin_base = admin_url();
	?>
	<script>
	jQuery(function($){
		const table = $('#cmx-positionen-table tbody');
		const adminBase = <?php echo wp_json_encode($admin_base); ?>;
		let post_id = $('#post_ID').val();

		function artikelEditUrl(id){
			id = parseInt(id,10) || 0;
			return id>0 ? (adminBase+'post.php?post='+id+'&action=edit') : (adminBase+'edit.php?post_type=artikel');
		}
		function updateArtikelLink($row){
			const val  = $row.find('.cmx-artikel-select').val();
			const href = artikelEditUrl(val);
			const $a   = $row.find('.cmx-artikel-label .cmx-artikel-link');
			$a.attr('href', href).text((parseInt(val,10)>0)?'bearbeiten':'Zur Artikelliste');
		}

		// Header "Rabatt" nur einmal ergänzen
		(function(){
			const headRow = $('#cmx-positionen-table thead tr');
			if (headRow.find('th:contains("Rabatt")').length === 0) $('<th>Rabatt</th>').insertAfter(headRow.find('th').eq(2));
		})();

		function parseNumberFlexible(val){
			if (typeof val!=='string') val=(val??'').toString();
			let s = val.replace(/\s+/g,'').replace(/'/g,'');
			const hasComma = s.indexOf(',')>-1, hasDot = s.indexOf('.')>-1;
			if (hasComma && hasDot){
				if (s.lastIndexOf(',') > s.lastIndexOf('.')) {
					s = s.replace(/\./g,'').replace(/,/g,'.');
				} else {
					s = s.replace(/,/g,'');
				}
			} else {
				s = s.replace(/,/g,'.');
			}
			const n = parseFloat(s);
			return isNaN(n) ? 0 : n;
		}
		function formatSwiss(n){
			const parts = (Number(n)||0).toFixed(2).split('.');
			let left = parts[0], out = '';
			while (left.length > 3) { out = "'" + left.slice(-3) + out; left = left.slice(0, -3); }
			return left + out + '.' + parts[1];
		}
		function parseRabattOnSubtotal(subtotal, rabattRaw){
			if (!rabattRaw) return 0;
			const txt=(rabattRaw+'').trim().toLowerCase();
			if (txt.endsWith('%')) {
				const pct=parseNumberFlexible(txt.replace('%',''));
				return pct>0 ? subtotal*(pct/100) : 0;
			}
			const cleaned=txt.replace(/chf|fr\.?/g,'').trim();
			const betrag=parseNumberFlexible(cleaned);
			return betrag>0 ? betrag : 0;
		}
		function roundTo5Rp(a){ return Math.round((a+Number.EPSILON)*20)/20; }

		function recalcRowTotal($row){
			let menge=parseNumberFlexible($row.find('input[name*="[menge]"]').val());
			let preis=parseNumberFlexible($row.find('input[name*="[preis]"]').val());
			let rabattRaw=$row.find('input[name*="[rabatt]"]').val();
			let subtotal=menge*preis;
			let rabatt=subtotal>0 ? parseRabattOnSubtotal(subtotal, rabattRaw) : 0;
			if (rabatt>subtotal) rabatt=subtotal;
			let totalRounded=roundTo5Rp(subtotal-rabatt);
			$row.find('.cmx-pos-total').text(formatSwiss(totalRounded));
		}
		function recalcAll(){ table.find('tr').each(function(){ recalcRowTotal($(this)); }); }

		table.on('input change','input[name*="[menge]"], input[name*="[preis]"], input[name*="[rabatt]"]', function(){
			recalcRowTotal($(this).closest('tr'));
		});

		table.on('change','.cmx-artikel-select', function(){
			const row=$(this).closest('tr');
			const artikelID=$(this).val();
			updateArtikelLink(row);
			if(!artikelID) return;
			$.post(<?php echo wp_json_encode($ajax_url); ?>,{action:'cmx_get_artikel_vk', artikel_id: artikelID}, function(resp){
				if (resp && resp.success && resp.data.vk){
					row.find('input[name*="[preis]"]').val(formatSwiss(parseNumberFlexible(resp.data.vk))).trigger('input');
				}
			}, 'json');
		});
		table.on('blur','input[name*="[menge]"], input[name*="[preis]"], input[name*="[rabatt]"]', function(){
			const raw = ($(this).val() ?? '').toString().trim();
			if (raw === '') return;
			if ($(this).is('input[name*="[rabatt]"]') && raw.endsWith('%')) {
				$(this).val(formatSwiss(parseNumberFlexible(raw.slice(0, -1))) + '%');
			} else {
				$(this).val(formatSwiss(parseNumberFlexible(raw)));
			}
			recalcRowTotal($(this).closest('tr'));
		});

		$('#cmx-add-pos').on('click', function(){
			let i=table.find('tr').length;
			let newRow=table.find('tr').last().clone();

			// Wenn noch keine Zeile existiert, fällt das auf dein Template zurück
			if (!newRow.length) return;

			newRow.find('input, select, textarea').each(function(){
				const $el=$(this);
				if($el.is('select')) $el.val('');
				else $el.val('');
				let name = ($el.attr('name')||'').replace(/\[\d+\]/, '['+i+']');
				if (name) $el.attr('name', name);
				let idAttr = ($el.attr('id')||'').replace(/_\d+_artikel_id$/, '_'+i+'_artikel_id');
				if (idAttr) $el.attr('id', idAttr);
			});

			// Label-Link bleibt, nur href/text wird später via updateArtikelLink gesetzt
			newRow.find('.cmx-pos-total').text('0,00');
			table.append(newRow);
			updateArtikelLink(newRow);
		});

		if (typeof $.fn.sortable==='function'){
			table.sortable({
				axis:'y',
				stop:function(){
					const rows=[];
					table.find('tr').each(function(){
						const r=$(this);
						rows.push({
							artikel_id:r.find('select[name*="[artikel_id]"]').val(),
							menge:r.find('input[name*="[menge]"]').val(),
							Preis:r.find('input[name*="[preis]"]').val(),
							preis:r.find('input[name*="[preis]"]').val(),
							rabatt:r.find('input[name*="[rabatt]"]').val()||'',
							beschreibung:r.find('textarea[name*="[beschreibung]"]').val()
						});
					});
					$.post(ajaxurl,{action:'cmx_save_beleg_positionen_order', post_id: post_id, rows: rows});
				}
			}).disableSelection();
		}

		table.on('click','.cmx-del-pos', function(){
			if (table.find('tr').length>1) $(this).closest('tr').remove();
		});

		table.find('tr').each(function(){ updateArtikelLink($(this)); });
		recalcAll();
	});
	</script>
	<style>
		#cmx-positionen-table th, #cmx-positionen-table td { vertical-align: middle; }
		#cmx-positionen-table td textarea { resize: vertical; }
	</style>
	<?php
}

/* ============================================================================
 * 5) JS-ONLY Link-Patch (idempotent, Label mit Link erzwingen/fixen)
 * ========================================================================== */
function cmx_beleg_positionen_link_patch_unified(): void {
	if (!is_admin()) return;
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'belege') return;

	$admin_base = admin_url();
	?>
	<script>
	jQuery(function($){
		const adminBase=<?php echo wp_json_encode($admin_base); ?>;
		const $tbody=$('#cmx-positionen-table tbody');
		if(!$tbody.length) return;

		function artikelEditUrl(id){
			id=parseInt(id,10)||0;
			return id>0 ? (adminBase+'post.php?post='+id+'&action=edit') : (adminBase+'edit.php?post_type=artikel');
		}
		function ensureLabelWithLink($row){
			const $td = $row.find('td').first();
			const $sel = $td.find('.cmx-artikel-select');
			if (!$sel.length) return;

			let $label = $td.find('label.cmx-artikel-label');
			if (!$label.length){
				$label = $('<label class="cmx-artikel-label" style="display:block;margin-bottom:4px"></label>');
				$sel.before($label);
			}
			let $a = $label.find('a.cmx-artikel-link');
			if (!$a.length){
				$a = $('<a class="cmx-artikel-link" target="" rel="noopener noreferrer"></a>');
				$label.append($a);
			}
			updateLink($row);
		}
		function updateLink($row){
			const val  = $row.find('.cmx-artikel-select').val();
			const href = artikelEditUrl(val);
			const $a   = $row.find('label.cmx-artikel-label .cmx-artikel-link');
			if ($a.length) $a.attr('href', href).text((parseInt(val,10)>0)?'bearbeiten':'Zur Artikelliste');
		}

		$tbody.find('tr').each(function(){ ensureLabelWithLink($(this)); });
		$tbody.on('change','.cmx-artikel-select', function(){ updateLink($(this).closest('tr')); });
		$('#cmx-add-pos').on('click', function(){
			setTimeout(function(){
				const $rows=$tbody.find('tr');
				if($rows.length) ensureLabelWithLink($rows.last());
			}, 0);
		});
	});
	</script>
	<?php
}
\add_action('admin_print_footer_scripts', __NAMESPACE__.'\\cmx_beleg_positionen_link_patch_unified', 99);

/* ============================================================================
 * Hinweis
 * ============================================================================
 * Diese Datei definiert KEINE Funktion mit dem alten Namen cmx_render_position_row().
 * Nutze stattdessen cmx_render_beleg_position_row_unified(...).
 * Achte darauf, dass keine alten Dateien mit gleichartigen Funktionen geladen werden.
 */
