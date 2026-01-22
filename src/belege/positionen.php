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
		$nr = get_post_meta($post_id, 'cmx_artikel_sku', true);
		if ($nr === '' || $nr === null) {
			$tmp = get_post_meta($post_id, '_cmx_artikel_sku', true);
			if (is_string($tmp) && $tmp !== '' && strpos($tmp, 'field_') !== 0) {
				$nr = $tmp;
			}
		}
		if ($nr === '' || $nr === null) $nr = get_post_meta($post_id, '_cmx_artikel_nr', true);
		if ($nr === '' || $nr === null) $nr = get_post_meta($post_id, '_sku', true);

		if (($nr === '' || $nr === null) && function_exists('wc_get_product')) {
			$p = wc_get_product($post_id);
			if ($p && method_exists($p, 'get_sku')) $nr = $p->get_sku();
		}
		return is_string($nr) ? trim($nr) : '';
	}
}

/* ------------------------------
 * Locale-robust: String -> Float normalisieren (Punkt/Komma/tausender)
 * ------------------------------ */
if (!function_exists(__NAMESPACE__ . '\cmx_norm_decimal')) {
	function cmx_norm_decimal($val){
		$s = (string)$val;
		// Tausendertrennzeichen entfernen
		$s = str_replace([" ", "'"], '', $s);
		$hasComma = strpos($s, ',') !== false;
		$hasDot   = strpos($s, '.') !== false;

		if ($hasComma && $hasDot) {
			// Das LETZTE Vorkommen bestimmt das Dezimaltrennzeichen
			if (strrpos($s, ',') > strrpos($s, '.')) {
				// Komma ist Dezimal → Punkte sind Tausender
				$s = str_replace('.', '', $s);
				$s = str_replace(',', '.', $s);
			} else {
				// Punkt ist Dezimal → Kommas sind Tausender
				$s = str_replace(',', '', $s);
			}
		} else {
			// Nur Komma vorhanden → als Dezimalpunkt behandeln
			$s = str_replace(',', '.', $s);
		}
		return $s;
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
	if (is_string($positionen) && $positionen !== '') {
		$tmp = json_decode($positionen, true);
		$positionen = (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) ? $tmp : [];
	} elseif (!is_array($positionen)) {
		$positionen = [];
	}
	echo '<div id="cmx-positionen-wrap">';
	echo '<table class="widefat striped" id="cmx-positionen-table">
			<thead><tr>
				<th><a href="/wp-admin/edit.php?post_type=artikel" target="_blank" rel="noopener noreferrer">Artikel</a></th>
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

	$menge        = (string)($pos['menge'] ?? '');
	$preis        = (string)($pos['preis'] ?? '');
	$beschreibung = esc_textarea($pos['beschreibung'] ?? '');
	$rabatt       = esc_attr($pos['rabatt'] ?? '');

	echo '<tr class="cmx-pos-row">';

	echo '<td style="min-width:260px">';
	echo '<input type="hidden" name="cmx_positionen['.$i.'][artikel_id]" class="cmx-artikel-id" value="'.esc_attr($artikel_id).'">';
	echo '<input type="text" class="regular-text cmx-artikel-autocomplete" placeholder="Artikel suchen …" value="'.esc_attr($display).'" autocomplete="off" style="width:100%">';
	echo '</td>';

	// negative Mengen zulassen (Komma/Punkt erlaubt)
	echo '<td><input type="text" name="cmx_positionen['.$i.'][menge]" value="'.esc_attr($menge).'" style="width:90px"></td>';

	// Preis als Text (Komma/Punkt erlaubt)
	echo '<td><input type="text" name="cmx_positionen['.$i.'][preis]" value="'.esc_attr($preis).'" style="width:100px"></td>';

	echo '<td class="cmx-pos-rabatt-td" style="width:100px;">';
	echo '<input type="text" name="cmx_positionen['.$i.'][rabatt]" value="'.$rabatt.'" placeholder="" style="width:100px">';
	echo '</td>';

	// Initial total (robust normalisiert)
	$menge_f = (float)\CLOUDMEISTER\CMX\Buero\cmx_norm_decimal($menge);
	$preis_f = (float)\CLOUDMEISTER\CMX\Buero\cmx_norm_decimal($preis);
	$total_init = $menge_f * $preis_f;

	echo '<td class="cmx-pos-total" style="width:90px;text-align:right;">'.number_format($total_init, 2, '.', '').'</td>';

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
		$menge        = (float)\CLOUDMEISTER\CMX\Buero\cmx_norm_decimal($menge_raw);

		$preis_raw    = isset($row['preis']) ? (string)$row['preis'] : '';
		$preis        = (float)\CLOUDMEISTER\CMX\Buero\cmx_norm_decimal($preis_raw);

		$rabatt_raw   = isset($row['rabatt']) ? (string)$row['rabatt'] : '';
		$rabatt       = sanitize_text_field($rabatt_raw);

		$beschreibung_raw = isset($row['beschreibung']) ? (string)$row['beschreibung'] : '';
		$beschreibung_raw = wp_unslash($beschreibung_raw);
		$beschreibung_raw = str_replace(["\r\n", "\r"], "\n", $beschreibung_raw);
		$beschreibung = trim($beschreibung_raw);
		$task_idx     = isset($row['task_idx']) ? (int)$row['task_idx'] : null;

		// negative Mengen zulassen; nur 0 verwerfen
		if ($artikel_id <= 0 || $menge == 0.0) continue;
		if (strlen($beschreibung) > 10000) $beschreibung = substr($beschreibung, 0, 10000);

		$clean[] = [
			'artikel_id'   => $artikel_id,
			'menge'        => $menge,
			'preis'        => $preis,
			'rabatt'       => $rabatt,
			'beschreibung' => $beschreibung,
			'task_idx'     => $task_idx,
		];
	}

	// Altdaten angleichen (können als JSON-String oder Array vorliegen)
	$old_raw  = get_post_meta($post_id, '_cmx_beleg_positionen', true);
	if (is_string($old_raw) && $old_raw !== '') {
		$tmp = json_decode($old_raw, true);
		if (json_last_error() === JSON_ERROR_NONE) {
			$old_data = $tmp;
		} else {
			$old_data = @maybe_unserialize($old_raw);
			if (!is_array($old_data)) $old_data = [];
		}
	} elseif (is_array($old_raw)) {
		$old_data = $old_raw;
	} else {
		$old_data = [];
	}

	if ($old_data !== $clean) {
		update_post_meta($post_id, '_cmx_beleg_positionen', $clean);
	}

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
 * AJAX: Artikel-Suche
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
		$norm      = preg_replace('/[\s\.\-_:]/', '', $term);
		$norm_like = '%' . $wpdb->esc_like($norm) . '%';

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
 * JS – Suggest + Berechnung (inkl. negative Beträge & Gesamtsumme)
 * ------------------------------ */
function cmx_beleg_positionen_js() {
	$ajax_url = admin_url('admin-ajax.php');
	?>
	<script>
	jQuery(function($){
		const table   = $('#cmx-positionen-table tbody');
		const AJAX_URL = <?php echo wp_json_encode($ajax_url); ?>;

		// ROBUSTER Parser: akzeptiert 1'234.56, 1'234,56, 1234,56, 1234.56
		function parseNumberFlexible(val){
			if(typeof val!=='string') val=(val??'').toString();
			let s = val.replace(/\s+/g,'').replace(/'/g,'');
			const hasComma = s.indexOf(',')>-1, hasDot = s.indexOf('.')>-1;
			if(hasComma && hasDot){
				if(s.lastIndexOf(',') > s.lastIndexOf('.')){
					// Komma ist Dezimal → Punkte sind Tausender
					s = s.replace(/\./g,'').replace(/,/g,'.');
				}else{
					// Punkt ist Dezimal → Kommas sind Tausender
					s = s.replace(/,/g,'');
				}
			}else{
				// Nur Komma vorhanden → als Dezimal interpretieren
				s = s.replace(/,/g,'.');
			}
			const n = parseFloat(s);
			return isNaN(n)?0:n;
		}

		function parseRabattOnSubtotal(subtotal, rabattRaw){
			if(!rabattRaw) return 0;
			const base = Math.abs(subtotal); // Rabattgrundlage = Betrag
			const txt=(rabattRaw+'').trim().toLowerCase();
			if(txt.endsWith('%')){
				const pct=parseNumberFlexible(txt.replace('%',''));
				return pct>0 ? base*(pct/100) : 0;
			}
			const betrag=parseNumberFlexible(txt.replace(/chf|fr\.?/g,''));
			return betrag>0?betrag:0;
		}

		function roundTo5Rp(amount){ return Math.round((amount + Number.EPSILON) * 20) / 20; }

		function recalcRowTotal($row){
			const menge=parseNumberFlexible($row.find('input[name*="[menge]"]').val());
			const preis=parseNumberFlexible($row.find('input[name*="[preis]"]').val());
			const rabattRaw=$row.find('input[name*="[rabatt]"]').val();

			let subtotal=menge*preis;
			let rabatt=parseRabattOnSubtotal(subtotal, rabattRaw);

			const cap = Math.abs(subtotal);
			if(rabatt>cap) rabatt=cap;

			const signedRabatt = Math.sign(subtotal) * rabatt;
			const total = roundTo5Rp(subtotal - signedRabatt);

			$row.find('.cmx-pos-total').text(total.toFixed(2)); // Ausgabe mit Punkt
			return total;
		}

		function recalcAll(){
			let sum=0;
			table.find('tr').each(function(){ sum += recalcRowTotal($(this)); });
			// Optional: Boxen im UI aktualisieren
			$('#cmx-gesamtsumme, .cmx-gesamtbox .sum').text(sum.toFixed(2));
			$(document).trigger('cmx_total_updated', [sum]);
		}

		/* ========= Suggest (unverändert) ========= */
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
				}else if(e.key==='Enter' || e==='Tab'){
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
			$.getJSON(<?php echo wp_json_encode($ajax_url); ?>, { action:'cmx_search_artikel', term: term||'' }, function(data){
				const rows = Array.isArray(data) ? data.map(it=>({ id: it.value, title: it.title||'', nr: it.nr||'' })) : [];
				cb(rows);
			});
		}

		function initArtikelSuggest($ctx){
			$ctx.find('.cmx-artikel-autocomplete').each(function(){
				const $input = $(this);
				try{ if($.ui && $.ui.autocomplete && $input.data('ui-autocomplete')) $input.autocomplete('destroy'); }catch(e){}
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
						$.post(<?php echo wp_json_encode($ajax_url); ?>, { action:'cmx_get_artikel_vk', artikel_id: it.id }, function(resp){
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

		/* ========= Eingabe-Events ========= */
		const selectorMRP = 'input[name*="[menge]"], input[name*="[preis]"], input[name*="[rabatt]"]';
		table.on('focus', selectorMRP, function(){ const el=this; setTimeout(()=>{ try{ el.select(); }catch(e){} }, 0); });
		table.on('mouseup', selectorMRP, function(e){ e.preventDefault(); });

		initArtikelSuggest(table);

		table.on('input change', selectorMRP, function(){ recalcAll(); });

		// Neue Zeile
		$('#cmx-add-pos').on('click', function(){
			let i=table.find('tr').length;
			let newRow=table.find('tr:first').clone();

			newRow.find('input, textarea').each(function(){
				let $el=$(this), name=$el.attr('name');
				if(name) $el.attr('name', name.replace(/\[\d+\]/,'['+i+']'));
				if($el.hasClass('cmx-artikel-id')){ $el.val(''); }
				else if($el.hasClass('cmx-artikel-autocomplete')){ $el.val('').removeData('cmx-suggest-ready'); }
				else if($el.is('[name*="[menge]"]')){ $el.val(''); }
				else if($el.is('[name*="[preis]"]')){ $el.val(''); }
				else if($el.is('[name*="[rabatt]"]')){ $el.val(''); }
				else if($el.is('textarea')){ $el.val(''); } else { $el.val(''); }
			});
			newRow.find('.cmx-pos-total').text('0.00');

			table.append(newRow);
			initArtikelSuggest(newRow);

			setTimeout(()=>{ newRow.find('.cmx-artikel-autocomplete').trigger('focus'); }, 0);
			setTimeout(recalcAll, 0);
		});

		// Entfernen
		table.on('click','.cmx-del-pos',function(){
			const $row = $(this).closest('tr');
			if (table.find('tr').length > 1) {
				$row.remove();
			} else {
				// Letzte Zeile: Inhalte leeren, damit kein zusätzlicher Platzhalter nötig ist
				$row.find('input, textarea').each(function(){
					const $el = $(this);
					if ($el.hasClass('cmx-artikel-id')) { $el.val(''); }
					else if ($el.hasClass('cmx-artikel-autocomplete')) { $el.val(''); }
					else { $el.val(''); }
				});
				$row.find('.cmx-pos-total').text('0.00');
			}
			setTimeout(recalcAll, 0);
		});

		// Initial
		recalcAll();
	});
	</script>
	<style>
		.cmx-art-suggest{ position:absolute; z-index:1000; left:0; right:0; max-height:280px; overflow:auto; margin:2px 0 0; padding:0; border:1px solid #ccd0d4; background:#fff; list-style:none; }
		.cmx-art-suggest li{ margin:0; padding:0; cursor:pointer; }
		.cmx-art-suggest li.active, .cmx-art-suggest li:hover{ background:#e5f3ff; }
		.cmx-ac-row{ display:grid; grid-template-columns: 140px 1fr; gap:8px; align-items:center; padding:6px 8px; }
		.cmx-ac-nr{ font-weight:600; white-space:nowrap; }
		.cmx-ac-title{ overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

		#cmx-positionen-table th, #cmx-positionen-table td { vertical-align: middle; }
		#cmx-positionen-table td textarea { resize: vertical; }
		.cmx-pos-row td:first-child{ position:relative; }
		.cmx-pos-total{ font-weight:600; text-align:right; }
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

		$menge        = (float)\CLOUDMEISTER\CMX\Buero\cmx_norm_decimal((string)($r['menge'] ?? ''));
		$preis        = (float)\CLOUDMEISTER\CMX\Buero\cmx_norm_decimal((string)($r['preis'] ?? ''));

		$rabatt_raw   = isset($r['rabatt']) ? (string)$r['rabatt'] : '';
		$rabatt       = sanitize_text_field($rabatt_raw);

		$beschreibung_raw = isset($r['beschreibung']) ? (string)$r['beschreibung'] : '';
		$beschreibung_raw = wp_unslash($beschreibung_raw);
		$beschreibung_raw = str_replace(["\r\n", "\r"], "\n", $beschreibung_raw);
		$beschreibung = trim($beschreibung_raw);

		// negative Mengen zulassen; nur 0 verwerfen
		if ($artikel_id <= 0 || $menge == 0.0) continue;
		if (strlen($beschreibung) > 10000) $beschreibung = substr($beschreibung, 0, 10000);

		$clean[] = [
			'artikel_id'   => $artikel_id,
			'menge'        => $menge,
			'preis'        => $preis,
			'rabatt'       => $rabatt,
			'beschreibung' => $beschreibung,
		];
	}

	$old = get_post_meta($post_id, '_cmx_beleg_positionen', true);
	if ($old !== $clean) update_post_meta($post_id, '_cmx_beleg_positionen', $clean);

	wp_send_json_success(['saved'=>count($clean)]);
});
