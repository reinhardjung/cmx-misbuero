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
	if ($ids) return $ids;
	$all = \get_posts(['post_type'=>$post_type,'post_status'=>['publish','private'],'posts_per_page'=>-1,'fields'=>'ids','suppress_filters'=>true]);
	$all = \is_array($all) ? \array_map('intval',$all) : [];
	if (!$all) return [];
	$out=[]; foreach ($all as $pid) if (cmx_is_lieferant_unified($pid)) $out[]=$pid; return $out;
}

/* ============================================================================
 * 2) Metabox "Lieferanten" + Save (CPT "artikel")
 * ========================================================================== */
\add_action('add_meta_boxes', function(){
	\add_meta_box('cmx_artikel_lieferanten','Lieferanten',__NAMESPACE__.'\\cmx_artikel_lieferanten_box_html_unified','artikel','normal','default');
});
function cmx_normalize_url_for_label_unified(string $url): string { $u=\trim($url); if($u==='')return''; if(!\preg_match('~^https?://~i',$u))$u='https://'.$u; return \filter_var($u,\FILTER_VALIDATE_URL)?$u:''; }
function cmx_artikel_lieferanten_box_html_unified(\WP_Post $post): void {
	\wp_nonce_field('cmx_artikel_lieferanten_save_unified','cmx_artikel_lieferanten_nonce_unified');
	$lager=(int)\get_post_meta($post->ID,CMX_ARTIKEL_META_LAGERBESTAND,true);
	$lieferant=(int)\get_post_meta($post->ID,CMX_ARTIKEL_META_LIEFERANT_ID,true);
	$ltage=(int)\get_post_meta($post->ID,CMX_ARTIKEL_META_LIEFERZEIT,true);
	$quelle=(string)\get_post_meta($post->ID,CMX_ARTIKEL_META_BEZUGSQUELLE,true);
	$lfnr=(string)\get_post_meta($post->ID,CMX_ARTIKEL_META_LIEFERANT_NR,true);

	$kontakt_pt=cmx_first_existing_kontakt_cpt_unified();
	$lieferanten_ids=$kontakt_pt?cmx_fetch_lieferanten_ids_unified($kontakt_pt):[];

	$label_href=''; if($lieferant && \get_post_status($lieferant)) $label_href=\get_edit_post_link($lieferant,''); elseif($kontakt_pt) $label_href=\add_query_arg(['post_type'=>$kontakt_pt],\admin_url('edit.php'));
	$bez_label_href=cmx_normalize_url_for_label_unified($quelle);

	echo '<style>.cmx-lief-row{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap}.cmx-lief-row p{margin:0;flex:1 1 160px}.cmx-lief-row p.lager{flex:0 0 120px}.cmx-lief-row p.lieferant{flex:2 1 260px}.cmx-lief-row p.ltage{flex:0 0 140px}.cmx-lief-row p.quelle{flex:2 1 260px;min-width:220px}.cmx-lief-row p.lfnr{flex:1 1 180px}.cmx-inline-help{font-size:11px;color:#666;margin-top:4px;display:block}</style>';
	echo '<div class="cmx-lief-row">';

	echo '<p class="lieferant"><label for="cmx_artikel_lieferant"><strong>';
	echo $label_href ? 'Lieferant (<a href="'.\esc_url($label_href).'">Kontakt</a>)' : 'Lieferant (Kontakt)';
	echo '</strong></label><select id="cmx_artikel_lieferant" name="cmx_artikel_lieferant" class="widefat"><option value="0">— auswählen —</option>';
	if ($lieferanten_ids) {
		$_posts=\get_posts(['post_type'=>$kontakt_pt?:'post','posts_per_page'=>-1,'post__in'=>$lieferanten_ids,'orderby'=>'title','order'=>'ASC','post_status'=>['publish','private'],'suppress_filters'=>true]);
		foreach($_posts as $k){ $title=\get_the_title($k->ID)?:'(#'.(int)$k->ID.')'; echo '<option value="'.(int)$k->ID.'" '.\selected($lieferant,$k->ID,false).'>'.\esc_html($title).'</option>'; }
	} else echo '<option value="0" disabled>(Keine als Lieferant gekennzeichneten Kontakte gefunden)</option>';
	echo '</select>'; if(!$kontakt_pt) echo '<span class="cmx-inline-help">Kein Kontakte-CPT gefunden (<code>kontakt</code> / <code>kontakte</code>).</span>'; echo '</p>';

	echo '<p class="lfnr"><label for="cmx_artikel_lieferant_nr"><strong>Lieferanten-Artikelnummer</strong></label><input type="text" id="cmx_artikel_lieferant_nr" name="cmx_artikel_lieferant_nr" class="widefat" value="'.\esc_attr($lfnr).'"></p>';

	echo '<p class="quelle"><label for="cmx_artikel_bezugsquelle"><strong>';
	echo ($bez_label_href!=='')?'Bezugsquelle (<a id="cmx_bezugsquelle_label" href="'.\esc_url($bez_label_href).'" target="_blank" rel="noopener noreferrer">URL</a>)':'Bezugsquelle <span id="cmx_bezugsquelle_label">(URL)</span>';
	echo '</strong></label><input type="url" id="cmx_artikel_bezugsquelle" name="cmx_artikel_bezugsquelle" class="widefat" placeholder="https://…" value="'.\esc_attr($quelle).'"></p>';

	echo '<script>(function(){var i=document.getElementById("cmx_artikel_bezugsquelle"),l=document.getElementById("cmx_bezugsquelle_label");if(!i||!l)return;function n(u){u=(u||"").trim();if(!u)return"";if(!/^https?:\\/\\//i.test(u))u="https://"+u;try{new URL(u);return u}catch(e){return""}}i.addEventListener("input",function(){var u=n(i.value);if(u){if(l.tagName!=="A"){var a=document.createElement("a");a.id=l.id;a.textContent=l.textContent||"Bezugsquelle (URL)";l.replaceWith(a);l=a}l.href=u;l.target="";l.rel="noopener noreferrer"}else{if(l.tagName==="A"){var s=document.createElement("span");s.id=l.id;s.textContent="Bezugsquelle (URL)";l.replaceWith(s);l=s}}});})();</script>';

	echo '<p class="ltage"><label for="cmx_artikel_lieferzeit"><strong>Lieferzeit (Tage)</strong></label><input type="number" min="0" step="1" id="cmx_artikel_lieferzeit" name="cmx_artikel_lieferzeit" class="widefat" value="'.\esc_attr((string)$ltage).'"></p>';
	echo '<p class="lager"><label for="cmx_artikel_lager"><strong>Lagerbestand</strong></label><input type="number" min="0" step="1" id="cmx_artikel_lager" name="cmx_artikel_lager" class="widefat" value="'.\esc_attr((string)$lager).'"></p>';

	echo '</div>';
	echo '<script>(function(){function a(e){if(!e)return;e.addEventListener("focus",function(){this.select()});e.addEventListener("click",function(){this.select()})}a(document.getElementById("cmx_artikel_lieferzeit"));a(document.getElementById("cmx_artikel_lager"));})();</script>';
}

\add_action('save_post_artikel', function (int $post_id, \WP_Post $post) {
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if ($post->post_type !== 'artikel') return;
	if (!\current_user_can('edit_post', $post_id)) return;
	if (!isset($_POST['cmx_artikel_lieferanten_nonce_unified']) || !\wp_verify_nonce($_POST['cmx_artikel_lieferanten_nonce_unified'], 'cmx_artikel_lieferanten_save_unified')) return;

	$lager = isset($_POST['cmx_artikel_lager']) ? (int) $_POST['cmx_artikel_lager'] : 0;
	\update_post_meta($post_id, CMX_ARTIKEL_META_LAGERBESTAND, max(0, $lager));

	$kontakt_pt   = cmx_first_existing_kontakt_cpt_unified();
	$allowed_ids  = $kontakt_pt ? cmx_fetch_lieferanten_ids_unified($kontakt_pt) : [];
	$lieferant_id = isset($_POST['cmx_artikel_lieferant']) ? (int) $_POST['cmx_artikel_lieferant'] : 0;

	if ($lieferant_id && $allowed_ids && \in_array($lieferant_id, $allowed_ids, true)) {
		\update_post_meta($post_id, CMX_ARTIKEL_META_LIEFERANT_ID, $lieferant_id);
	} else {
		if (!$allowed_ids && $lieferant_id > 0) {
			$p = \get_post($lieferant_id);
			\update_post_meta($post_id, CMX_ARTIKEL_META_LIEFERANT_ID, ($p && $kontakt_pt && $p->post_type === $kontakt_pt) ? $lieferant_id : 0);
		} else {
			\update_post_meta($post_id, CMX_ARTIKEL_META_LIEFERANT_ID, 0);
		}
	}

	$ltage = isset($_POST['cmx_artikel_lieferzeit']) ? (int) $_POST['cmx_artikel_lieferzeit'] : 0;
	\update_post_meta($post_id, CMX_ARTIKEL_META_LIEFERZEIT, max(0, $ltage));

	$url = isset($_POST['cmx_artikel_bezugsquelle']) ? \trim((string) $_POST['cmx_artikel_bezugsquelle']) : '';
	$url = $url !== '' ? \esc_url_raw($url) : '';
	\update_post_meta($post_id, CMX_ARTIKEL_META_BEZUGSQUELLE, $url);

	$lfnr = isset($_POST['cmx_artikel_lieferant_nr']) ? \sanitize_text_field((string) $_POST['cmx_artikel_lieferant_nr']) : '';
	\update_post_meta($post_id, CMX_ARTIKEL_META_LIEFERANT_NR, $lfnr);
}, 10, 2);

/* ============================================================================
 * 3) Beleg-Positionen – Renderer (Label enthält Link)
 *    Eindeutiger Funktionsname → keine Redeclare-Konflikte.
 * ========================================================================== */
function cmx_render_beleg_position_row_unified($i, $pos): void {
	$artikel_id   = esc_attr($pos['artikel_id'] ?? '');
	$menge        = esc_attr($pos['menge'] ?? 1);
	$preis        = esc_attr($pos['preis'] ?? '');
	$beschreibung = esc_textarea($pos['beschreibung'] ?? '');
	$rabatt       = esc_attr($pos['rabatt'] ?? '');

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

	echo '<td><input type="number" name="cmx_positionen['.$i.'][menge]" value="'.$menge.'" min="0" step="0.01" style="width:70px"></td>';
	echo '<td><input type="text" name="cmx_positionen['.$i.'][preis]" value="'.$preis.'" style="width:100px"></td>';
	echo '<td class="cmx-pos-rabatt-td" style="width:100px;"><input type="text" name="cmx_positionen['.$i.'][rabatt]" value="'.$rabatt.'" style="width:100px"></td>';
	echo '<td class="cmx-pos-total" style="width:90px;text-align:right;">'.number_format((float)$preis * (float)$menge, 2).'</td>';
	echo '<td><textarea name="cmx_positionen['.$i.'][beschreibung]" rows="1" style="width:100%">'.$beschreibung.'</textarea></td>';
	echo '<td><button type="button" class="button-link-delete cmx-del-pos">✕</button></td>';

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
			val = val.replace(/'/g,'').replace(/\s+/g,'').replace(',', '.');
			const n = parseFloat(val);
			return isNaN(n) ? 0 : n;
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
			$row.find('.cmx-pos-total').text(totalRounded.toFixed(2)+'');
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
					row.find('input[name*="[preis]"]').val(resp.data.vk).trigger('input');
				}
			}, 'json');
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
			newRow.find('.cmx-pos-total').text('0.00');
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
