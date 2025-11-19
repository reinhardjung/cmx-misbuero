<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/* ------------------------------
 * Admin-Skripte nur im Belege-Editor laden
 * ------------------------------ */
add_action('admin_enqueue_scripts', function() {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if ($screen && $screen->post_type === 'belege') {
		wp_enqueue_script('jquery-ui-autocomplete');
		wp_enqueue_style('wp-jquery-ui-dialog'); // Basis-Styles für jQuery UI
	}
});

/* ---------------------------------
 * Zentrale Helper: Artikel-Nr (SKU) – robust mit Fallbacks
 * --------------------------------- */
if (!function_exists(__NAMESPACE__ . '\cmx_get_artikel_nr')) {
	function cmx_get_artikel_nr($post_id){
		// ACF-Normalfall (Feldname ohne Unterstrich)
		$nr = get_post_meta($post_id, 'cmx_artikel_sku', true);

		// Falls fälschlich nur ACF-FieldKey gespeichert wurde (Unterstrich)
		if ($nr === '' || $nr === null) {
			$tmp = get_post_meta($post_id, '_cmx_artikel_sku', true);
			if (is_string($tmp) && $tmp !== '' && strpos($tmp, 'field_') !== 0) {
				$nr = $tmp;
			}
		}

		// Historische Keys
		if ($nr === '' || $nr === null) $nr = get_post_meta($post_id, '_cmx_artikel_nr', true);
		if ($nr === '' || $nr === null) $nr = get_post_meta($post_id, '_sku', true);

		// WooCommerce (falls Produktobjekt existiert)
		if (($nr === '' || $nr === null) && function_exists('wc_get_product')) {
			$p = wc_get_product($post_id);
			if ($p && method_exists($p, 'get_sku')) {
				$nr = $p->get_sku();
			}
		}

		return is_string($nr) ? trim($nr) : '';
	}
}


/* ------------------------------
 * Metabox registrieren
 * ------------------------------ */
add_action('add_meta_boxes', function() {
	add_meta_box(
		'cmx_beleg_positionen',
		'Positionen',
		__NAMESPACE__ . '\\cmx_render_beleg_positionen',
		'belege',
		'normal',
		'default'
	);
});

/* ------------------------------
 * Metabox Inhalt
 * ------------------------------ */
function cmx_render_beleg_positionen(\WP_Post $post) {
	wp_nonce_field('cmx_save_beleg_positionen', 'cmx_beleg_positionen_nonce');

	$positionen = get_post_meta($post->ID, '_cmx_beleg_positionen', true);
	$positionen = $positionen ? json_decode($positionen, true) : [];

	echo '<div id="cmx-positionen-wrap">';
	echo '<table class="widefat striped" id="cmx-positionen-table">
			<thead><tr>
				<th>Artikel</th>
				<th>Menge</th>
				<th>Einzelpreis</th>
				<th>Rabatt</th>
				<th style="text-align:right;">Gesamt</th>
				<th>zus&auml;tzliche Notiz</th>
				<th></th>
			</tr></thead>
			<tbody>';

	if (!empty($positionen)) {
		foreach ($positionen as $i => $pos) {
			cmx_render_position_row($i, $pos);
		}
	} else {
		cmx_render_position_row(0, []);
	}

	echo '</tbody></table>';
	echo '<p><button type="button" class="button button-secondary" id="cmx-add-pos">+ Position hinzufügen</button></p>';
	echo '</div>';

	cmx_beleg_positionen_js();
}

/* ------------------------------
 * Einzelzeile rendern (Autocomplete für Artikel)
 * ------------------------------ */
function cmx_render_position_row($i, $pos) {
	$artikel_id   = isset($pos['artikel_id']) ? (int)$pos['artikel_id'] : 0;
	$title        = $artikel_id ? get_the_title($artikel_id) : '';
	$nr           = $artikel_id ? cmx_get_artikel_nr($artikel_id) : '';
	$display      = esc_html( ($nr ? $nr.' – ' : '') . ($title ?: ($pos['artikel_name'] ?? '')) );

	$menge        = esc_attr($pos['menge'] ?? 1);
	$preis        = esc_attr($pos['preis'] ?? '');
	$beschreibung = esc_textarea($pos['beschreibung'] ?? '');
	$rabatt       = esc_attr($pos['rabatt'] ?? '');

	echo '<tr class="cmx-pos-row">';

	echo '<td style="min-width:260px">';
	echo '<input type="hidden" name="cmx_positionen['.$i.'][artikel_id]" class="cmx-artikel-id" value="'.esc_attr($artikel_id).'">';
	echo '<input type="text" class="regular-text cmx-artikel-autocomplete" placeholder="Artikel suchen …" value="'.$display.'" autocomplete="off" style="width:100%">';
	echo '</td>';

	echo '<td><input type="number" name="cmx_positionen['.$i.'][menge]" value="'.$menge.'" min="1" step="1" style="width:70px"></td>';
	echo '<td><input type="text" name="cmx_positionen['.$i.'][preis]" value="'.$preis.'" style="width:100px"></td>';

	echo '<td class="cmx-pos-rabatt-td" style="width:100px;">';
	echo '<input type="text" name="cmx_positionen['.$i.'][rabatt]" value="'.$rabatt.'" placeholder="" style="width:100px">';
	echo '</td>';

	$total_init = 0.0;
	if ($preis !== '' && $menge !== '') {
		$total_init = (float)str_replace([',',"'"," "],['.','',''], (string)$preis) * (float)str_replace([',',"'"," "],['.','',''], (string)$menge);
	}
	echo '<td class="cmx-pos-total" style="width:90px;text-align:right;">'.number_format($total_init, 2).'</td>';

	echo '<td><textarea name="cmx_positionen['.$i.'][beschreibung]" rows="1" style="width:100%">'.$beschreibung.'</textarea></td>';
	echo '<td><button type="button" class="button-link-delete cmx-del-pos">✕</button></td>';

	echo '</tr>';
}

/* ------------------------------
 * Speichern der Positionen (gehärtet)
 * ------------------------------ */
add_action('save_post_belege', function($post_id, \WP_Post $post, $update) {

	if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
	if ($post->post_type !== 'belege') return;
	if (!current_user_can('edit_post', $post_id)) return;
	if (!isset($_POST['cmx_beleg_positionen_nonce']) || !wp_verify_nonce($_POST['cmx_beleg_positionen_nonce'], 'cmx_save_beleg_positionen')) return;
	if (defined('DOING_AJAX') && DOING_AJAX) return;

	$positionen = $_POST['cmx_positionen'] ?? [];
	if (!is_array($positionen)) return;

	$max_rows = 500;
	if (count($positionen) > $max_rows) $positionen = array_slice($positionen, 0, $max_rows);

	$clean = [];
	foreach ($positionen as $row) {
		if (!is_array($row)) continue;

		$artikel_id   = isset($row['artikel_id']) ? (int)$row['artikel_id'] : 0;

		$menge_raw    = isset($row['menge']) ? (string)$row['menge'] : '';
		$menge_norm   = str_replace([' ', "'", ','], ['', '', '.'], $menge_raw);
		$menge        = (float)$menge_norm;

		$preis_raw    = isset($row['preis']) ? (string)$row['preis'] : '';
		$preis        = (float)str_replace(["'", ' ', ','], ['', '', '.'], $preis_raw);

		$rabatt_raw   = isset($row['rabatt']) ? (string)$row['rabatt'] : '';
		$rabatt       = sanitize_text_field($rabatt_raw);

		$beschreibung = isset($row['beschreibung']) ? wp_kses_post($row['beschreibung']) : '';

		if ($artikel_id <= 0 || $menge <= 0) continue;
		if (strlen($beschreibung) > 10000) $beschreibung = substr($beschreibung, 0, 10000);

		$clean[] = [
			'artikel_id'   => $artikel_id,
			'menge'        => $menge,
			'preis'        => $preis,
			'rabatt'       => $rabatt,
			'beschreibung' => $beschreibung,
		];
	}

	$new_json = wp_json_encode($clean);
	$old_json = (string)get_post_meta($post_id, '_cmx_beleg_positionen', true);
	if ($new_json !== $old_json) update_post_meta($post_id, '_cmx_beleg_positionen', $new_json);

}, 10, 3);

/* ------------------------------
 * AJAX: VK-Preis aus Artikel (_cmx_artikel_vk)
 * ------------------------------ */
add_action('wp_ajax_cmx_get_artikel_vk', function() {
	if (!current_user_can('edit_posts')) wp_send_json_error(['msg' => 'forbidden'], 403);
	$artikel_id = isset($_POST['artikel_id']) ? (int) $_POST['artikel_id'] : 0;
	if ($artikel_id <= 0) wp_send_json_error(['msg' => 'no_id'], 400);
	$vk = get_post_meta($artikel_id, '_cmx_artikel_vk', true);
	wp_send_json_success(['vk' => ($vk === '' || $vk === null) ? '' : (string)$vk]);
});

/* ------------------------------
 * AJAX: Artikel-Suche (Name ODER Artikel-Nr/SKU) – OR-Matching mit Priorisierung
 * ------------------------------ */
add_action('wp_ajax_cmx_search_artikel', function() {
	if (!current_user_can('edit_posts')) wp_send_json_error(['msg' => 'forbidden'], 403);

	global $wpdb;

	$term  = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';
	$limit = 20;

	$post_type = 'artikel';
	$post_tbl  = $wpdb->posts;
	$meta_tbl  = $wpdb->postmeta;

	$ids = [];

	// Wenn kein Suchbegriff: zeige letzte geänderten Artikel
	if ($term === '') {
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$post_tbl}
				 WHERE post_type=%s AND post_status<>'trash'
				 ORDER BY post_modified_gmt DESC, post_title ASC
				 LIMIT %d",
				$post_type, $limit
			)
		);
	} else {
		$like = '%' . $wpdb->esc_like($term) . '%';

		// Normalisierte Suche (z. B. 12-34 -> 1234)
		$norm      = preg_replace('/[\s\.\-_:]/', '', $term);
		$norm_like = '%' . $wpdb->esc_like($norm) . '%';

		// 1) Titel-Treffer (priorisieren)
		$title_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$post_tbl}
				 WHERE post_type=%s AND post_status<>'trash'
				   AND post_title LIKE %s
				 ORDER BY post_title ASC
				 LIMIT %d",
				$post_type, $like, $limit
			)
		);

		// 2) Meta-Treffer (SKU/Artikel-Nr in verschiedenen Keys)
		$meta_keys = ['cmx_artikel_sku', '_cmx_artikel_sku', '_cmx_artikel_nr', '_sku'];
		$in_keys   = implode(',', array_fill(0, count($meta_keys), '%s'));

		$meta_sql  = $wpdb->prepare(
			"SELECT p.ID
			   FROM {$post_tbl} p
			   JOIN {$meta_tbl} m ON m.post_id = p.ID
			  WHERE p.post_type=%s
			    AND p.post_status<>'trash'
			    AND m.meta_key IN ($in_keys)
			    AND (
					m.meta_value LIKE %s
					OR REPLACE(REPLACE(REPLACE(REPLACE(m.meta_value,' ',''),'-',''),'.',''),':','') LIKE %s
				)
			  GROUP BY p.ID
			  ORDER BY MAX(p.post_title) ASC
			  LIMIT %d",
			array_merge([$post_type], $meta_keys, [$like, $norm_like, $limit])
		);
		$meta_ids = $wpdb->get_col($meta_sql);

		// 3) Zusammenführen (Titel zuerst), Duplikate entfernen, auf Limit kürzen
		$ids = array_slice(array_values(array_unique(array_merge($title_ids, $meta_ids))), 0, $limit);
	}

	$items = [];
	foreach ($ids as $id) {
		$title = (string) get_the_title($id);
		$nr    = cmx_get_artikel_nr($id);

		$items[] = [
			'label' => trim(($nr !== '' ? $nr . ' – ' : '') . $title),
			'value' => (int) $id,
			'nr'    => $nr,
			'title' => $title,
		];
	}

	wp_send_json($items);
});


/* ------------------------------
 * JS – Eigene Artikelliste (SKU + Titel) mit Pfeiltasten + Auto-Select der Werte
 * ------------------------------ */
function cmx_beleg_positionen_js() {
	$ajax_url = admin_url('admin-ajax.php');
	?>
	<script>
	jQuery(function($){
		const table   = $('#cmx-positionen-table tbody');
		const AJAX_URL = <?php echo wp_json_encode($ajax_url); ?>;

		/* ========= Rechen-Helfer ========= */
		function parseNumberFlexible(val){ if(typeof val!=='string') val=(val??'').toString(); val=val.replace(/'/g,'').replace(/\s+/g,'').replace(',', '.'); const n=parseFloat(val); return isNaN(n)?0:n; }
		function parseRabattOnSubtotal(subtotal, rabattRaw){
			if(!rabattRaw) return 0;
			const txt=(rabattRaw+'').trim().toLowerCase();
			if(txt.endsWith('%')){ const pct=parseNumberFlexible(txt.replace('%','')); return pct>0 ? subtotal*(pct/100) : 0; }
			const cleaned=txt.replace(/chf|fr\.?/g,'').trim();
			const betrag=parseNumberFlexible(cleaned);
			return betrag>0?betrag:0;
		}
		function roundTo5Rp(amount){ return Math.round((amount + Number.EPSILON) * 20) / 20; }
		function recalcRowTotal($row){
			let menge=parseNumberFlexible($row.find('input[name*="[menge]"]').val());
			let preis=parseNumberFlexible($row.find('input[name*="[preis]"]').val());
			let rabattRaw=$row.find('input[name*="[rabatt]"]').val();
			let subtotal=menge*preis;
			let rabatt=subtotal>0?parseRabattOnSubtotal(subtotal, rabattRaw):0;
			if(rabatt>subtotal) rabatt=subtotal;
			$row.find('.cmx-pos-total').text(roundTo5Rp(subtotal-rabatt).toFixed(2)+'');
		}
		function recalcAll(){ table.find('tr').each(function(){ recalcRowTotal($(this)); }); }

		/* ========= Core: eigene Suggest-Liste mit Navigator ========= */
		function makeNavigator(inputEl, listEl, chooseCb){
			let active=-1, items=[];
			function esc(s){ return (s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
			function render(arr){
				items = Array.isArray(arr) ? arr : [];
				if(!items.length){ listEl.style.display='none'; listEl.innerHTML=''; active=-1; return; }
				listEl.innerHTML = items.map((it,i)=>(
					`<li data-index="${i}">
						<div class="cmx-ac-row">
							<span class="cmx-ac-nr">${esc(it.nr||'')}</span>
							<span class="cmx-ac-title">${esc(it.title||'')}</span>
						</div>
					</li>`
				)).join('');
				listEl.style.display='block'; active=-1;
			}
			function move(d){
				if(!items.length) return;
				active = (active + d + items.length) % items.length;
				[...listEl.children].forEach((li,i)=>li.classList.toggle('active', i===active));
			}
			function choose(i){
				if(i<0||i>=items.length) return;
				chooseCb(items[i]);
				listEl.style.display='none'; listEl.innerHTML=''; active=-1;
			}
			listEl.addEventListener('mousedown', e=>{
				const li=e.target.closest('li'); if(!li) return;
				e.preventDefault();
				choose(parseInt(li.dataset.index,10));
			});
			inputEl.addEventListener('keydown', e=>{
				if(e.key==='ArrowDown'){
					if(listEl.style.display!=='block'){
						e.preventDefault();
						inputEl.dispatchEvent(new Event('focus'));
						setTimeout(()=>{ move(1); }, 0);
					}else{
						e.preventDefault(); move(1);
					}
				}else if(e.key==='ArrowUp'){
					if(listEl.style.display==='block'){ e.preventDefault(); move(-1); }
				}else if(e.key==='Enter' || e.key==='Tab'){
					if(listEl.style.display==='block'){
						const idx = active>-1 ? active : 0;
						e.preventDefault(); choose(idx);
					}
				}else if(e.key==='Escape'){
					if(listEl.style.display==='block'){ e.preventDefault(); listEl.style.display='none'; listEl.innerHTML=''; active=-1; }
				}
			});
			document.addEventListener('click', e=>{
				if(!listEl.contains(e.target) && e.target!==inputEl){
					listEl.style.display='none'; listEl.innerHTML=''; active=-1;
				}
			});
			return { render, reset:()=>{ items=[]; active=-1; } };
		}

		function fetchArtikel(term, cb){
			$.getJSON(AJAX_URL, { action:'cmx_search_artikel', term: term||'' }, function(data){
				const rows = Array.isArray(data) ? data.map(it=>({
					id: it.value,
					title: it.title||'',
					nr: it.nr||''
				})) : [];
				cb(rows);
			});
		}

		function initArtikelSuggest($ctx){
			$ctx.find('.cmx-artikel-autocomplete').each(function(){
				const $input = $(this);
				try{
					if($.ui && $.ui.autocomplete && $input.data('ui-autocomplete')){
						$input.autocomplete('destroy');
					}
				}catch(e){}
				$input.off('.autocomplete');
				if($input.data('cmx-suggest-ready')) return;

				const $cell = $input.closest('td');
				if($cell.css('position')==='static'){ $cell.css('position','relative'); }
				const $ul = $('<ul class="cmx-art-suggest" style="display:none"></ul>');
				$input.after($ul);

				const nav = makeNavigator($input[0], $ul[0], chooseItem);
				let t=null;

				function chooseItem(it){
					const $row = $input.closest('tr');
					$row.find('.cmx-artikel-id').val(it.id||0);
					$input.val((it.nr?it.nr+' – ':'') + (it.title||''));
					if(it.id){
						$.post(AJAX_URL, { action:'cmx_get_artikel_vk', artikel_id: it.id }, function(resp){
							if(resp && resp.success && resp.data && resp.data.vk!==undefined){
								$row.find('input[name*="[preis]"]').val(resp.data.vk).trigger('input');
							}
						}, 'json');
					}
				}

				function doSearch(q){ fetchArtikel(q, (rows)=>{ nav.render(rows); }); }

				$input.on('input', function(){
					if(t) clearTimeout(t);
					const q = $input.val().trim();
					if(q.length<1){ $ul.hide().empty(); nav.reset(); return; }
					t = setTimeout(()=>doSearch(q), 120);
				});
				$input.on('focus click', function(){ doSearch($input.val()); });

				$input.data('cmx-suggest-ready', true);
			});
		}

		/* ========= Auto-Select bei Fokus: Menge, Einzelpreis, Rabatt ========= */
		const selectorMRP = 'input[name*="[menge]"], input[name*="[preis]"], input[name*="[rabatt]"]';
		table.on('focus', selectorMRP, function(){
			const el = this;
			// Timeout vermeidet, dass Click die Selektion sofort wieder aufhebt
			setTimeout(()=>{ try{ el.select(); }catch(e){} }, 0);
		});
		// Mouseup unterdrücken, damit die Auswahl bestehen bleibt
		table.on('mouseup', selectorMRP, function(e){ e.preventDefault(); });

		/* ========= Initialisierung ========= */
		initArtikelSuggest(table);

		// Recalc live
		table.on('input change', selectorMRP, function(){
			const row=$(this).closest('tr');
			if($(this).attr('name').includes('[menge]')) $(this).attr({'min':'0','step':'0.01'});
			recalcRowTotal(row);
		});

		// Neue Zeile
		$('#cmx-add-pos').on('click', function(){
			let i=table.find('tr').length;
			let newRow=table.find('tr:first').clone();

			newRow.find('input, textarea').each(function(){
				let $el=$(this), name=$el.attr('name');
				if(name) $el.attr('name', name.replace(/\[\d+\]/,'['+i+']'));
				if($el.hasClass('cmx-artikel-id')){ $el.val(''); }
				else if($el.hasClass('cmx-artikel-autocomplete')){ $el.val('').removeData('cmx-suggest-ready'); }
				else if($el.is('[name*="[menge]"]')){ $el.val('1').attr({'min':'0','step':'0.01'}); }
				else if($el.is('[name*="[preis]"]')){ $el.val(''); }
				else if($el.is('[name*="[rabatt]"]')){ $el.val(''); }
				else if($el.is('textarea')){ $el.val(''); } else { $el.val(''); }
			});
			newRow.find('.cmx-pos-total').text('0.00');

			table.append(newRow);
			initArtikelSuggest(newRow);

			// Fokus auf neues Suchfeld
			const $input=newRow.find('.cmx-artikel-autocomplete');
			setTimeout(()=>{ $input.trigger('focus'); }, 0);
		});

		// Entfernen
		table.on('click','.cmx-del-pos',function(){ if(table.find('tr').length>1) $(this).closest('tr').remove(); });

		recalcAll();
	});
	</script>
	<style>
		/* Eigene Artikelliste: Nr links, Titel rechts */
		.cmx-art-suggest{ position:absolute; z-index:1000; left:0; right:0; max-height:280px; overflow:auto; margin:2px 0 0; padding:0; border:1px solid #ccd0d4; background:#fff; list-style:none; }
		.cmx-art-suggest li{ margin:0; padding:0; cursor:pointer; }
		.cmx-art-suggest li.active, .cmx-art-suggest li:hover{ background:#e5f3ff; }
		.cmx-ac-row{ display:grid; grid-template-columns: 140px 1fr; gap:8px; align-items:center; padding:6px 8px; }
		.cmx-ac-nr{ font-weight:600; white-space:nowrap; }
		.cmx-ac-title{ overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

		/* Tabelle */
		#cmx-positionen-table th, #cmx-positionen-table td { vertical-align: middle; }
		#cmx-positionen-table td textarea { resize: vertical; }

		/* Container für absolute Liste */
		.cmx-pos-row td:first-child{ position:relative; }
	</style>
	<?php
}



/* ------------------------------
 * AJAX: Positionen nach Sortierung speichern (gehärtet)
 * ------------------------------ */
add_action('wp_ajax_cmx_save_beleg_positionen_order', function() {

	if (!current_user_can('edit_posts')) wp_send_json_error(['msg'=>'forbidden'],403);

	$post_id = (int)($_POST['post_id'] ?? 0);
	if ($post_id <= 0) wp_send_json_error(['msg'=>'no_post_id'],400);

	$rows = $_POST['rows'] ?? [];
	if (!is_array($rows)) wp_send_json_error(['msg'=>'invalid_rows'],400);

	$max_rows = 500;
	if (count($rows) > $max_rows) $rows = array_slice($rows, 0, $max_rows);

	$clean = [];
	foreach ($rows as $r) {
		$artikel_id   = isset($r['artikel_id']) ? (int)$r['artikel_id'] : 0;

		$menge_raw    = isset($r['menge']) ? (string)$r['menge'] : '';
		$menge_norm   = str_replace([' ', "'", ','], ['', '', '.'], $menge_raw);
		$menge        = (float)$menge_norm;

		$preis_raw    = isset($r['preis']) ? (string)$r['preis'] : '';
		$preis        = (float)str_replace(["'", ' ', ','], ['', '', '.'], $preis_raw);

		$rabatt_raw   = isset($r['rabatt']) ? (string)$r['rabatt'] : '';
		$rabatt       = sanitize_text_field($rabatt_raw);

		$beschreibung = isset($r['beschreibung']) ? wp_kses_post($r['beschreibung']) : '';

		if ($artikel_id <= 0 || $menge <= 0) continue;
		if (strlen($beschreibung) > 10000) $beschreibung = substr($beschreibung, 0, 10000);

		$clean[] = [
			'artikel_id'   => $artikel_id,
			'menge'        => $menge,
			'preis'        => $preis,
			'rabatt'       => $rabatt,
			'beschreibung' => $beschreibung,
		];
	}

	$new_json = wp_json_encode($clean);
	$old_json = (string)get_post_meta($post_id, '_cmx_beleg_positionen', true);
	if ($new_json !== $old_json) update_post_meta($post_id, '_cmx_beleg_positionen', $new_json);

	wp_send_json_success(['saved'=>count($clean)]);
});
