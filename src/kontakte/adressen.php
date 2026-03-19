<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * ==========================================================
 * - Autocomplete (geo.admin.ch) setzt bei CH-Treffern das Land auf den CH-Term-Slug
 * - Muss noch aufgeräumt werden, wegen meinen CKX-TAX_ als default werdete übergeben
 * ==========================================================
 */

/** -------------------- Meta-Konstanten -------------------- */
foreach ([
	'CMX_RECHNUNG_META_STRASSE' => '_cmx_rechnung_strasse',
	'CMX_RECHNUNG_META_ZUSATZ'  => '_cmx_rechnung_zusatz',
	'CMX_RECHNUNG_META_PLZ'     => '_cmx_rechnung_plz',
	'CMX_RECHNUNG_META_ORT'     => '_cmx_rechnung_ort',
	'CMX_RECHNUNG_META_LAND'    => '_cmx_rechnung_land',

	'CMX_LIEFER_META_STRASSE'   => '_cmx_liefer_strasse',
	'CMX_LIEFER_META_ZUSATZ'    => '_cmx_liefer_zusatz',
	'CMX_LIEFER_META_PLZ'       => '_cmx_liefer_plz',
	'CMX_LIEFER_META_ORT'       => '_cmx_liefer_ort',
	'CMX_LIEFER_META_LAND'      => '_cmx_liefer_land',
] as $k => $v) {
	if (!defined(__NAMESPACE__.'\\'.$k)) define(__NAMESPACE__.'\\'.$k, $v);
}

/** -------------------- Helper: Länder aus Taxonomie holen -------------------- */
/**
 * Liefert eine Optionsliste aus der Taxonomie "kontakte_laender".
 * @return array [['value'=>'ch','label'=>'Schweiz'], ...]
 */
if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_normalize_country_meta_value')) {
	function cmx_kontakte_normalize_country_meta_value(string $country): string {
		$country = \trim($country);
		if ($country === '') {
			return '';
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_normalize_country_slug')) {
			return (string) cmx_normalize_country_slug($country);
		}
		if (\preg_match('/^[A-Za-z]{2}$/', $country)) {
			return \strtolower($country);
		}
		$map = [
			'switzerland' => 'ch', 'schweiz' => 'ch', 'suisse' => 'ch', 'svizzera' => 'ch',
			'germany' => 'de', 'deutschland' => 'de',
			'austria' => 'at', 'österreich' => 'at', 'oesterreich' => 'at',
		];
		$key = \strtolower(\remove_accents($country));
		return $map[$key] ?? \strtolower($country);
	}
}

function cmx_countries_from_taxonomy(): array {
	$tax = 'kontakte_laender';
	if (!\taxonomy_exists($tax)) {
		return [
			['value'=>'ch','label'=>'Schweiz'],
			['value'=>'us','label'=>'Amerika'],
			['value'=>'at','label'=>'Österreich'],
		];
	}
	$terms = \get_terms([
		'taxonomy'   => $tax,
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	]);
	if (\is_wp_error($terms) || empty($terms)) {
		return [
			['value'=>'ch','label'=>'Schweiz'],
			['value'=>'us','label'=>'Amerika'],
			['value'=>'at','label'=>'Österreich'],
		];
	}
	$out = [];
	foreach ($terms as $t) {
		$slug = strtolower($t->slug ?: sanitize_title($t->name));
		$out[] = ['value'=>$slug, 'label'=>$t->name];
	}
	return $out;
}

/**
 * Hilft, einen passenden Standard (z. B. CH) zu finden.
 */
function cmx_countries_default_slug(array $countries, string $prefer='ch'): string {
	$prefer = strtolower($prefer);
	foreach ($countries as $c) {
		if (strtolower($c['value']) === $prefer) return $prefer;
	}
	// erster Eintrag als Fallback
	return strtolower($countries[0]['value'] ?? 'ch');
}

function cmx_countries_taxonomy(): string {
	return \taxonomy_exists('kontakte_laender') ? 'kontakte_laender' : '';
}

function cmx_countries_label_html(string $label = 'Land'): string {
	$taxonomy = cmx_countries_taxonomy();
	if ($taxonomy === '') {
		return \esc_html($label);
	}

	$url = \admin_url('edit-tags.php?taxonomy=' . \rawurlencode($taxonomy) . '&post_type=kontakte');

	return '<a href="' . \esc_url($url) . '" target="_blank" rel="noopener noreferrer" style="color:inherit;text-decoration:none;font:inherit;font-size:inherit;font-weight:inherit;line-height:inherit;">' . \esc_html($label) . '</a>';
}

/** -------------------- Metas registrieren -------------------- */
\add_action('init', __NAMESPACE__ . '\\cmx_register_address_metas');
function cmx_register_address_metas(): void {
	$auth = static fn() => \current_user_can('edit_posts');
	foreach ([
		CMX_RECHNUNG_META_STRASSE, CMX_RECHNUNG_META_ZUSATZ, CMX_RECHNUNG_META_PLZ, CMX_RECHNUNG_META_ORT, CMX_RECHNUNG_META_LAND,
		CMX_LIEFER_META_STRASSE,   CMX_LIEFER_META_ZUSATZ,   CMX_LIEFER_META_PLZ,   CMX_LIEFER_META_ORT,   CMX_LIEFER_META_LAND,
	] as $key) {
		\register_post_meta('kontakte', $key, [
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => $auth,
		]);
	}
}

/** -------------------- Metabox registrieren -------------------- */
\add_action('add_meta_boxes', __NAMESPACE__ . '\\cmx_add_adressen_metabox', 11);
function cmx_add_adressen_metabox(): void {
	\add_meta_box(
		'cmx_adressen_metabox',
		'Adressen',
		__NAMESPACE__ . '\\cmx_render_adressen_metabox',
		'kontakte',
		'normal',
		'default'
	);
}

/** -------------------- Metabox Rendering -------------------- */
function cmx_render_adressen_metabox(\WP_Post $post): void {
	\wp_nonce_field('cmx_save_address_meta', 'cmx_address_nonce');

	$countries = cmx_countries_from_taxonomy();
	$default_slug = cmx_countries_default_slug($countries, 'ch');
	$land_label_html = cmx_countries_label_html('Land');

	$rechnung = [
		'strasse' => \get_post_meta($post->ID, CMX_RECHNUNG_META_STRASSE, true),
		'zusatz'  => \get_post_meta($post->ID, CMX_RECHNUNG_META_ZUSATZ,  true),
		'plz'     => \get_post_meta($post->ID, CMX_RECHNUNG_META_PLZ,     true),
		'ort'     => \get_post_meta($post->ID, CMX_RECHNUNG_META_ORT,     true),
		'land'    => cmx_kontakte_normalize_country_meta_value((string) \get_post_meta($post->ID, CMX_RECHNUNG_META_LAND, true)) ?: $default_slug,
	];
	$liefer = [
		'strasse' => \get_post_meta($post->ID, CMX_LIEFER_META_STRASSE, true),
		'zusatz'  => \get_post_meta($post->ID, CMX_LIEFER_META_ZUSATZ,  true),
		'plz'     => \get_post_meta($post->ID, CMX_LIEFER_META_PLZ,     true),
		'ort'     => \get_post_meta($post->ID, CMX_LIEFER_META_ORT,     true),
		'land'    => cmx_kontakte_normalize_country_meta_value((string) \get_post_meta($post->ID, CMX_LIEFER_META_LAND, true)) ?: $default_slug,
	];

	echo '<style>
	.cmx-tabs{margin-top:10px}
	.cmx-tab-buttons{display:flex;border-bottom:1px solid #ddd;margin-bottom:12px}
	.cmx-tab-buttons button{background:#f7f7f7;border:1px solid #ccc;border-bottom:none;padding:8px 16px;cursor:pointer;margin-right:6px;border-radius:4px 4px 0 0}
	.cmx-tab-buttons button.active{background:#fff;border-bottom:1px solid #fff;font-weight:600}
	.cmx-tab-content{display:none}.cmx-tab-content.active{display:block}
	.cmx-row{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:8px;align-items:flex-start}
	.cmx-col{flex:1 1 0;min-width:160px}
	.cmx-col input, .cmx-col select{width:100%}
	.cmx-col.cmx-land{flex:0 0 160px;min-width:140px;max-width:200px}
	.cmx-col.cmx-plz{flex:0 0 140px;min-width:120px;max-width:180px}
	.cmx-map{margin-top:10px;border:1px solid #e5e5e5;border-radius:6px;overflow:hidden}
	.cmx-map iframe{width:100%;height:260px;border:0}
	.cmx-map .cmx-map-label{padding:6px 10px;background:#fafafa;border-bottom:1px solid #eee;font-weight:600}
	/* Autocomplete UI (sichtbares Feld pro Tab) */
	.cmx-ac-wrap{position:relative;margin:8px 0 12px}
	.cmx-ac-input{ width:100%; max-width:280px;line-height:1.4;padding:8px 10px;border:1px solid #ccd0d4;border-radius:4px;background:#fff }
	.cmx-ac-input:focus{outline:0;border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}
	.cmx-ac-list{position:absolute;z-index:999999;max-width:640px;max-height:300px;overflow:auto;border:1px solid #d0d7de;border-radius:6px;background:#fff;box-shadow:0 8px 24px rgba(31,35,40,.15);display:none}
	.cmx-ac-item{padding:10px 12px;cursor:pointer;font-size:13px;line-height:1.4;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
	.cmx-ac-item:hover{background:#f6f8fa}
	.cmx-ac-item.is-active{background:#e7f3ff}
	</style>';

	echo '<div class="cmx-tabs">
		<div class="cmx-tab-buttons">
			<button type="button" class="active" data-tab="rechnung">Rechnungsanschrift</button>
			<button type="button" data-tab="liefer">Lieferadresse</button>
		</div>';

	$render_tab = static function(string $key, array $vals, array $countries) use ($default_slug, $land_label_html): void {
		$is_active = $key === 'rechnung' ? ' active' : '';

		$init_parts = array_filter([$vals['strasse'] ?? '', $vals['zusatz'] ?? '', $vals['plz'] ?? '', $vals['ort'] ?? '', $vals['land'] ?? '']);
		$iframe_src = 'https://www.google.com/maps?q=' . rawurlencode(implode(', ', $init_parts)) . '&output=embed';

		echo '<div id="cmx-tab-'.$key.'" class="cmx-tab-content'.$is_active.'" data-cmx-addr="'.$key.'">';

		// Sichtbares Suchfeld (CH)
		echo '<div class="cmx-ac-wrap">
			 <!--- <label><strong>Adresse suchen (CH)</strong></label><br> --->
				<input type="text" class="cmx-ac-input" placeholder="'.esc_attr($key === 'liefer' ? 'Adesse suchen…' : 'Anschrift suchen…').'" aria-label="Adresse suchen (CH)">
			  </div>';

		// Eingabezeile
		echo '<div class="cmx-row">
			<div class="cmx-col">
				<label><strong>Strasse</strong></label><br>
				<input type="text" name="_cmx_'.$key.'_strasse" value="'.esc_attr($vals['strasse']).'" data-cmx-addr="'.$key.'" data-cmx-field="strasse">
			</div>
			<div class="cmx-col">
				<label><strong>Zusatz</strong></label><br>
				<input type="text" name="_cmx_'.$key.'_zusatz" value="'.esc_attr($vals['zusatz']).'" data-cmx-addr="'.$key.'" data-cmx-field="zusatz">
			</div>';


			echo '<div class="cmx-col cmx-land">
				<label><strong>' . $land_label_html . '</strong></label><br>';

				$selected_land = strtolower($vals['land'] ?? '') ?: $default_slug;

			echo '<select name="_cmx_'.$key.'_land" data-cmx-addr="'.esc_attr($key).'" data-cmx-field="land">';

			foreach ($countries as $c) {
				$val = strtolower($c['value']);
				echo '<option value="'.esc_attr($val).'"'.selected($selected_land, $val, false).'>'.esc_html($c['label']).'</option>';
			}

			echo '</select></div>


			<div class="cmx-col cmx-plz">
				<label><strong>PLZ</strong></label><br>
				<input type="text" name="_cmx_'.$key.'_plz" value="'.esc_attr($vals['plz']).'" data-cmx-addr="'.$key.'" data-cmx-field="plz">
			</div>
			<div class="cmx-col">
				<label><strong>Ort</strong></label><br>
				<input type="text" name="_cmx_'.$key.'_ort" value="'.esc_attr($vals['ort']).'" data-cmx-addr="'.$key.'" data-cmx-field="ort">
			</div>
		</div>';

		echo '<div class="cmx-map" id="cmx-map-'.$key.'">
			<div class="cmx-map-label">Karten-Vorschau</div>
			<iframe id="cmx-map-'.$key.'-iframe" src="'.esc_url($iframe_src).'" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
		</div>';

		echo '</div>';
	};

	$render_tab('rechnung', $rechnung, $countries);
	$render_tab('liefer',   $liefer,   $countries);

	echo '</div>';

	// Tabs + Map-Refresh
	echo '<script>
	(function(){
	  function getField(container, profile, role){
	    var el = container.querySelector(\'[data-cmx-addr="\'+profile+\'"][data-cmx-field="\'+role+\'"]\'); return el||null;
	  }
	  function readField(container, profile, role){
	    var el = getField(container, profile, role);
	    if(!el) return "";
	    if(el.tagName==="SELECT"){
	      return (el.selectedOptions && el.selectedOptions[0]) ? el.selectedOptions[0].text : (el.value||"");
	    }
	    return el.value||"";
	  }
	  function buildAddress(profile){
	    var w=document.getElementById("cmx-tab-"+profile); if(!w) return "";
	    var parts=[ "strasse","zusatz","plz","ort","land" ]
	      .map(function(role){ return readField(w, profile, role); })
	      .map(function(s){ return (s||"").trim(); })
	      .filter(Boolean);
	    return parts.join(", ");
	  }
	  function refreshMap(profile){
	    var iframe=document.getElementById("cmx-map-"+profile+"-iframe");
	    if(!iframe) return;
	    var q=buildAddress(profile);
	    iframe.src="https://www.google.com/maps?q="+encodeURIComponent(q)+"&output=embed";
	  }
	  // Tabs
	  document.querySelectorAll(".cmx-tab-buttons button").forEach(function(btn){
	    btn.addEventListener("click", function(){
	      var tab=btn.dataset.tab;
	      document.querySelectorAll(".cmx-tab-buttons button").forEach(function(b){ b.classList.remove("active"); });
	      document.querySelectorAll(".cmx-tab-content").forEach(function(c){ c.classList.remove("active"); });
	      btn.classList.add("active");
	      document.getElementById("cmx-tab-"+tab).classList.add("active");
	    });
	  });
	  var timers={rechnung:null, liefer:null};
	  ["rechnung","liefer"].forEach(function(profile){
	    var w=document.getElementById("cmx-tab-"+profile); if(!w) return;
	    w.querySelectorAll(\'[data-cmx-addr="\'+profile+\'"]\').forEach(function(el){
	      ["input","change"].forEach(function(evt){
	        el.addEventListener(evt, function(){
	          clearTimeout(timers[profile]);
	          timers[profile]=setTimeout(function(){ refreshMap(profile); }, 350);
	        });
	      });
	    });
	  });
	})();
	</script>';
}

/** -------------------- CH-Autocomplete (geo.admin.ch) – sichtbares Suchfeld je Tab -------------------- */
\add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\cmx_addr_ac_enqueue');
function cmx_addr_ac_enqueue($hook): void {
	if (!in_array($hook, ['post.php','post-new.php'], true)) return;
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || $screen->post_type !== 'kontakte') return;

	\wp_register_script('cmx-ac-inline', false, [], null, true);
	\wp_enqueue_script('cmx-ac-inline');

	// Wir setzen bei Pick standardmäßig den CH-Slug, wenn vorhanden. Den Slug holen wir serverseitig und übergeben ihn.
	$countries = cmx_countries_from_taxonomy();
	$chSlug    = cmx_countries_default_slug($countries, 'ch');
	$js_chSlug = json_encode($chSlug);

	$js = <<<JS
(function(){
	'use strict';
	function \$(s,r){return (r||document).querySelector(s);}
	function \$all(s,r){return Array.prototype.slice.call((r||document).querySelectorAll(s));}
	function stripHTML(s){ var d=document.createElement('div'); d.innerHTML=String(s||''); return d.textContent||d.innerText||''; }
	function setVal(el,v){
		if(!el) return;
		var val=(v==null?'':String(v));
		var wasDis=el.disabled===true, wasRO=el.readOnly===true;
		if(wasDis) el.disabled=false;
		if(wasRO) el.readOnly=false;
		if(el.tagName==='SELECT'){
			el.value=val;
			for(var i=0;i<el.options.length;i++){ if(el.options[i].value==val){ el.selectedIndex=i; break; } }
		}else{
			el.value=val; el.setAttribute('value',val);
		}
		el.dispatchEvent(new Event('input',{bubbles:true}));
		el.dispatchEvent(new Event('change',{bubbles:true}));
		setTimeout(function(){ try{ el.dispatchEvent(new Event('blur',{bubbles:true})); }catch(e){} },10);
		if(wasDis) el.disabled=true;
		if(wasRO) el.readOnly=true;
	}
	function buildQuery(q){
		return 'https://api3.geo.admin.ch/rest/services/api/SearchServer?sr=4326&type=locations&origins=address&lang=de&searchText='+encodeURIComponent(q);
	}
	function normalize(rec){
		var a=rec&&rec.attrs?rec.attrs:{};
		var country='CH';
		var label=stripHTML(rec.label||a.label||'').trim();
		var street=a.street||a.strasse||'';
		var number=a.number||a.housenumber||'';
		var zip=a.zip||a.postalcode||a.plz||'';
		var city=a.city||a.locality||a.ort||'';
		var canton=a.canton||a.kanton||a.state||'';
		function parseLabel(lbl){
			lbl=lbl.replace(/\\s+/g,' ').trim();
			var m=lbl.match(/^(.+?)\\s+(\\d+[a-zA-Z]?)\\s+(?:CH-)?(\\d{4})\\s+(.+?)(?:\\s*\\(([^)]+)\\))?$/);
			if(m) return {street:m[1],number:m[2],zip:m[3],city:m[4],canton:(m[5]||'')};
			m=lbl.match(/^(.+?)\\s+(?:CH-)?(\\d{4})\\s+(.+?)(?:\\s*\\(([^)]+)\\))?$/);
			if(m) return {street:m[1],number:'',zip:m[2],city:m[3],canton:(m[4]||'')};
			m=lbl.match(/^(.+?)\\s+(\\d+[a-zA-Z]?)\\s*,\\s*(?:CH-)?(\\d{4})\\s+(.+?)$/);
			if(m) return {street:m[1],number:m[2],zip:m[3],city:m[4],canton:''};
			m=lbl.match(/^(.+?)\\s*,\\s*(?:CH-)?(\\d{4})\\s+(.+?)$/);
			if(m) return {street:m[1],number:'',zip:m[2],city:m[3],canton:''};
			m=lbl.match(/(\\d{4})/);
			if(m){
				var plz=m[1], parts=lbl.split(plz), left=(parts[0]||'').trim(), right=(parts[1]||'').trim();
				var m2=left.match(/^(.+?)\\s+(\\d+[a-zA-Z]?)$/);
				return {street:m2?m2[1]:left, number:m2?m2[2]:'', zip:plz, city:right, canton:''};
			}
			return null;
		}
		if(!street||!zip||!city){
			var p=parseLabel(label);
			if(p){ street=street||p.street; number=number||p.number; zip=zip||p.zip; city=city||p.city; canton=canton||p.canton; }
		}
		var lat=(typeof a.y!=='undefined')?a.y:(typeof rec.y!=='undefined'?rec.y:null);
		var lng=(typeof a.x!=='undefined')?a.x:(typeof rec.x!=='undefined'?rec.x:null);
		var line1=[street,number].filter(Boolean).join(' ');
		var line2=[zip,city].filter(Boolean).join(' ');
		var line3=[canton,country].filter(Boolean).join(', ');
		var formatted=[line1,line2,line3].filter(Boolean).join(', ');
		return {label,street,number,zip,city,canton,country,lat,lng,formatted};
	}

	function attach(container, profile){
		var input = container.querySelector(".cmx-ac-input");
		if(!input) return;

		// Portal-Dropdown
		var list=document.createElement("div"); list.className="cmx-ac-list"; document.body.appendChild(list);
		function openList(){ list.style.display="block"; positionList(); }
		function closeList(){ list.style.display="none"; list.innerHTML=""; }
		function positionList(){ var r=input.getBoundingClientRect(); list.style.top=(window.scrollY+r.bottom)+"px"; list.style.left=(window.scrollX+r.left)+"px"; list.style.width=r.width+"px"; }
		window.addEventListener("scroll",positionList,true); window.addEventListener("resize",positionList); input.addEventListener("focus",positionList);

		function field(role){ return container.querySelector('[data-cmx-addr="'+profile+'"][data-cmx-field="'+role+'"]'); }

		var results=[], activeIndex=-1, ctrl={};

		function render(){
			if(!results.length){ closeList(); return; }
			var html=""; for(var i=0;i<results.length;i++){ html+='<div class="cmx-ac-item'+(i===activeIndex?' is-active':'')+'" data-i="'+i+'">'+results[i].label+'</div>'; }
			list.innerHTML=html; openList(); ensureVisible();
		}
		function setActive(idx){
			if(!results.length) return;
			if(idx<0) idx=results.length-1;
			if(idx>=results.length) idx=0;
			activeIndex=idx;
			\$all(".cmx-ac-item",list).forEach(function(el,i){ el.classList.toggle("is-active", i===activeIndex); });
			ensureVisible();
		}
		function ensureVisible(){
			var act=list.querySelector(".cmx-ac-item.is-active"); if(!act) return;
			var ar=act.getBoundingClientRect(), lr=list.getBoundingClientRect();
			if(ar.bottom>lr.bottom) list.scrollTop += (ar.bottom - lr.bottom);
			if(ar.top<lr.top) list.scrollTop -= (lr.top - ar.top);
		}
		function pick(i){
			var r=results[i]; if(!r) return;
			closeList();
			var streetFull=[r.street,r.number].filter(Boolean).join(" ");
			var f_strasse=field("strasse"), f_plz=field("plz"), f_ort=field("ort"), f_land=field("land");
			setVal(f_strasse, streetFull);
			setVal(f_plz, r.zip||"");
			setVal(f_ort, r.city||"");
			// Land bei CH-Autocomplete auf CH-Slug (aus Taxonomie) setzen:
			if(f_land) setVal(f_land, {$js_chSlug});
		}

		// Maus & Keyboard
		list.addEventListener("mousemove", function(e){
			var item=e.target.closest(".cmx-ac-item"); if(!item) return;
			var i=parseInt(item.getAttribute("data-i"),10);
			if(!isNaN(i) && i!==activeIndex) setActive(i);
		});
		list.addEventListener("mousedown", function(e){
			var item=e.target.closest(".cmx-ac-item"); if(!item) return;
			e.preventDefault(); pick(parseInt(item.getAttribute("data-i"),10));
		});
		document.addEventListener("click", function(e){ if(!list.contains(e.target) && e.target!==input) closeList(); });

		input.addEventListener("keydown", function(e){
			if(list.style.display!=="block" && (e.key==="ArrowDown" || e.key==="ArrowUp")){
				if(results.length){ e.preventDefault(); activeIndex=0; render(); return; }
			}
			if(list.style.display!=="block") return;
			if(e.key==="ArrowDown"){ e.preventDefault(); setActive(activeIndex+1); }
			else if(e.key==="ArrowUp"){ e.preventDefault(); setActive(activeIndex-1); }
			else if(e.key==="Enter"){ e.preventDefault(); if(activeIndex>=0) pick(activeIndex); }
			else if(e.key==="Escape"){ e.preventDefault(); closeList(); }
		});

		input.addEventListener("input", function(){
			var q=input.value.trim();
			if(q.length<3){ closeList(); results=[]; activeIndex=-1; return; }
			if(ctrl.abort) try{ ctrl.abort(); }catch(_){}
			ctrl=new AbortController();
			fetch(buildQuery(q),{signal:ctrl.signal,headers:{"Accept":"application/json"}})
				.then(function(r){ return r.json(); })
				.then(function(j){
					results=(j.results||[]).map(normalize).filter(function(x){ return (x.country||"")==="CH"; });
					activeIndex = results.length ? 0 : -1;
					render();
				})
				.catch(function(){ /* ignore */ });
		});
	}

	function init(){
		["rechnung","liefer"].forEach(function(profile){
			var tab=document.getElementById("cmx-tab-"+profile);
			if(tab && !tab.__cmxAcBound){
				tab.__cmxAcBound=true;
				attach(tab, profile);
			}
		});
		var obs=new MutationObserver(function(){
			["rechnung","liefer"].forEach(function(profile){
				var tab=document.getElementById("cmx-tab-"+profile);
				if(tab && !tab.__cmxAcBound){ tab.__cmxAcBound=true; attach(tab, profile); }
			});
		});
		obs.observe(document.body,{childList:true,subtree:true});
	}
	if(document.readyState==="loading") document.addEventListener("DOMContentLoaded", init); else init();
})();
JS;
	\wp_add_inline_script('cmx-ac-inline', $js);
}
