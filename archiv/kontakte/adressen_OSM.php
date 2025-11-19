<?php
/**
 * Plugin Name: CMX – Adress-Metabox (weltweit, OSM/Nominatim)
 * Description: Adress-Metabox für CPT "kontakte" mit weltweitem Autocomplete via OpenStreetMap Nominatim und Leaflet-Karten-Vorschau. Länder-Select wird aus der Taxonomie "kontakte_laender" befüllt.
 * Version: 2.0.0
 * Author: CLOUDMEISTER
 * License: GPL-2.0+
 */
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit;

/** ---------------------------------------------------------
 * Meta-Konstanten
 * --------------------------------------------------------- */
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

/** ---------------------------------------------------------
 * Länder aus Taxonomie holen
 * @return array [['value'=>'de','label'=>'Deutschland'], ...]
 * --------------------------------------------------------- */
function cmx_countries_from_taxonomy(): array {
	$tax = 'kontakte_laender';
	if (!\taxonomy_exists($tax)) {
		// Fallback-Liste (weltweit, exemplarisch/kurz)
		return [
			['value'=>'de','label'=>'Deutschland'],
			['value'=>'ch','label'=>'Schweiz'],
			['value'=>'at','label'=>'Österreich'],
			['value'=>'us','label'=>'USA'],
			['value'=>'gb','label'=>'Vereinigtes Königreich'],
			['value'=>'fr','label'=>'Frankreich'],
			['value'=>'it','label'=>'Italien'],
			['value'=>'es','label'=>'Spanien'],
			['value'=>'nl','label'=>'Niederlande'],
			['value'=>'se','label'=>'Schweden'],
		];
	}
	$terms = \get_terms([
		'taxonomy'   => $tax,
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	]);
	if (\is_wp_error($terms) || empty($terms)) {
		return [['value'=>'de','label'=>'Deutschland']];
	}
	$out = [];
	foreach ($terms as $t) {
		$slug = strtolower($t->slug ?: sanitize_title($t->name));
		$out[] = ['value'=>$slug, 'label'=>$t->name];
	}
	return $out;
}

/** Ersten vorhandenen Länderslug als Default nutzen */
function cmx_countries_default_slug(array $countries): string {
	return strtolower($countries[0]['value'] ?? 'de');
}

/** ---------------------------------------------------------
 * Metas registrieren
 * --------------------------------------------------------- */
\add_action('init', __NAMESPACE__ . '\\cmx_register_address_metas');
function cmx_register_address_metas(): void {
	$auth = static fn() => \current_user_can('edit_posts');
	$keys = [
		CMX_RECHNUNG_META_STRASSE, CMX_RECHNUNG_META_ZUSATZ, CMX_RECHNUNG_META_PLZ, CMX_RECHNUNG_META_ORT, CMX_RECHNUNG_META_LAND,
		CMX_LIEFER_META_STRASSE,   CMX_LIEFER_META_ZUSATZ,   CMX_LIEFER_META_PLZ,   CMX_LIEFER_META_ORT,   CMX_LIEFER_META_LAND,
	];
	foreach ($keys as $key) {
		\register_post_meta('kontakte', $key, [
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => $auth,
		]);
	}
}

/** ---------------------------------------------------------
 * Metabox registrieren
 * --------------------------------------------------------- */
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

/** ---------------------------------------------------------
 * Speichern
 * --------------------------------------------------------- */
\add_action('save_post_kontakte', __NAMESPACE__.'\\cmx_save_adressen_metabox');
function cmx_save_adressen_metabox(int $post_id): void {
	if (!isset($_POST['cmx_address_nonce']) || !\wp_verify_nonce($_POST['cmx_address_nonce'], 'cmx_save_address_meta')) return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!\current_user_can('edit_post', $post_id)) return;

	$fields = [
		'rechnung' => [
			'strasse' => CMX_RECHNUNG_META_STRASSE,
			'zusatz'  => CMX_RECHNUNG_META_ZUSATZ,
			'plz'     => CMX_RECHNUNG_META_PLZ,
			'ort'     => CMX_RECHNUNG_META_ORT,
			'land'    => CMX_RECHNUNG_META_LAND,
		],
		'liefer' => [
			'strasse' => CMX_LIEFER_META_STRASSE,
			'zusatz'  => CMX_LIEFER_META_ZUSATZ,
			'plz'     => CMX_LIEFER_META_PLZ,
			'ort'     => CMX_LIEFER_META_ORT,
			'land'    => CMX_LIEFER_META_LAND,
		],
	];

	foreach ($fields as $group => $map) {
		foreach ($map as $role => $meta_key) {
			$req_key = "_cmx_{$group}_{$role}";
			if (isset($_POST[$req_key])) {
				\update_post_meta($post_id, $meta_key, sanitize_text_field(wp_unslash($_POST[$req_key])));
			}
		}
	}
}

/** ---------------------------------------------------------
 * Metabox Rendering
 * --------------------------------------------------------- */
function cmx_render_adressen_metabox(\WP_Post $post): void {
	\wp_nonce_field('cmx_save_address_meta', 'cmx_address_nonce');

	$countries   = cmx_countries_from_taxonomy();
	$defaultSlug = cmx_countries_default_slug($countries);

	$rechnung = [
		'strasse' => \get_post_meta($post->ID, CMX_RECHNUNG_META_STRASSE, true),
		'zusatz'  => \get_post_meta($post->ID, CMX_RECHNUNG_META_ZUSATZ,  true),
		'plz'     => \get_post_meta($post->ID, CMX_RECHNUNG_META_PLZ,     true),
		'ort'     => \get_post_meta($post->ID, CMX_RECHNUNG_META_ORT,     true),
		'land'    => strtolower(\get_post_meta($post->ID, CMX_RECHNUNG_META_LAND, true) ?: $defaultSlug),
	];
	$liefer = [
		'strasse' => \get_post_meta($post->ID, CMX_LIEFER_META_STRASSE, true),
		'zusatz'  => \get_post_meta($post->ID, CMX_LIEFER_META_ZUSATZ,  true),
		'plz'     => \get_post_meta($post->ID, CMX_LIEFER_META_PLZ,     true),
		'ort'     => \get_post_meta($post->ID, CMX_LIEFER_META_ORT,     true),
		'land'    => strtolower(\get_post_meta($post->ID, CMX_LIEFER_META_LAND, true) ?: $defaultSlug),
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
	.cmx-map .cmx-map-label{padding:6px 10px;background:#fafafa;border-bottom:1px solid #eee;font-weight:600}
	.cmx-map .cmx-map-canvas{height:260px}
	/* Autocomplete */
	.cmx-ac-wrap{position:relative;margin:8px 0 12px}
	.cmx-ac-input{width:100%;max-width:640px;line-height:1.4;padding:8px 10px;border:1px solid #ccd0d4;border-radius:4px;background:#fff}
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

	$render_tab = static function(string $key, array $vals, array $countries): void {
		$is_active = $key === 'rechnung' ? ' active' : '';
		echo '<div id="cmx-tab-'.$key.'" class="cmx-tab-content'.$is_active.'" data-cmx-addr="'.$key.'">';

		// Sichtbares Suchfeld (weltweit)
		echo '<div class="cmx-ac-wrap">
				<input type="text" class="cmx-ac-input" placeholder="'.esc_attr($key === 'liefer' ? 'Adresse suchen (Lieferung)…' : 'Adresse suchen (Rechnung)…').'" aria-label="Adresse suchen">
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
			</div>
			<div class="cmx-col cmx-land">
				<label><strong>Land</strong></label><br>
				<select name="_cmx_'.$key.'_land" data-cmx-addr="'.$key.'" data-cmx-field="land">';
					foreach ($countries as $c) {
						$val = strtolower($c['value']);
						echo '<option value="'.esc_attr($val).'"'.selected(strtolower($vals['land'] ?? ''), $val, false).'>'.esc_html($c['label']).'</option>';
					}
		echo   '</select>
			</div>
			<div class="cmx-col cmx-plz">
				<label><strong>PLZ</strong></label><br>
				<input type="text" name="_cmx_'.$key.'_plz" value="'.esc_attr($vals['plz']).'" data-cmx-addr="'.$key.'" data-cmx-field="plz">
			</div>
			<div class="cmx-col">
				<label><strong>Ort</strong></label><br>
				<input type="text" name="_cmx_'.$key.'_ort" value="'.esc_attr($vals['ort']).'" data-cmx-addr="'.$key.'" data-cmx-field="ort">
			</div>
		</div>';

		// Leaflet Map
		echo '<div class="cmx-map" id="cmx-map-'.$key.'">
			<div class="cmx-map-label">Karten-Vorschau (OpenStreetMap)</div>
			<div class="cmx-map-canvas" id="cmx-map-'.$key.'-canvas"></div>
		</div>';

		echo '</div>';
	};

	$render_tab('rechnung', $rechnung, $countries);
	$render_tab('liefer',   $liefer,   $countries);

	echo '</div>';
}

/** ---------------------------------------------------------
 * Assets + Inline-JS (Nominatim + Leaflet)
 * --------------------------------------------------------- */
\add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\cmx_addr_assets');
function cmx_addr_assets($hook): void {
	if (!in_array($hook, ['post.php','post-new.php'], true)) return;
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || $screen->post_type !== 'kontakte') return;

	// Leaflet (CDN)
	\wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
	\wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);

	// Inline-JS
	\wp_register_script('cmx-addr-inline', false, ['leaflet'], null, true);
	\wp_enqueue_script('cmx-addr-inline');

	$countries = cmx_countries_from_taxonomy();
	$knownCountrySlugs = array_map(static fn($c) => strtolower($c['value']), $countries);
	$knownJson = wp_json_encode($knownCountrySlugs);

	$js = <<<JS
(function(){
	'use strict';
	function \$all(sel,root){return Array.prototype.slice.call((root||document).querySelectorAll(sel));}
	function setVal(el,v){
		if(!el) return;
		var val=(v==null?'':String(v));
		if(el.tagName==='SELECT'){ el.value = val; }
		else { el.value = val; el.setAttribute('value',val); }
		el.dispatchEvent(new Event('input',{bubbles:true}));
		el.dispatchEvent(new Event('change',{bubbles:true}));
	}
	function field(container, profile, role){
		return container.querySelector('[data-cmx-addr="'+profile+'"][data-cmx-field="'+role+'"]');
	}
	function ensureMap(profile){
		var canvas = document.getElementById('cmx-map-'+profile+'-canvas');
		if(!canvas) return null;
		if(canvas.__leafletMap) return canvas.__leafletMap;
		var map = L.map(canvas, {zoomControl:true, attributionControl:true}).setView([20,0], 2);
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
			maxZoom: 19,
			attribution: '&copy; OpenStreetMap-Mitwirkende'
		}).addTo(map);
		canvas.__leafletMap = map;
		return map;
	}
	function updateMap(profile, lat, lon, label){
		var map = ensureMap(profile);
		if(!map || typeof lat!=='number' || typeof lon!=='number') return;
		map.setView([lat,lon], 16);
		if(map.__marker){ map.__marker.remove(); }
		map.__marker = L.marker([lat,lon]).addTo(map).bindPopup(label||'').openPopup();
		setTimeout(function(){ map.invalidateSize(); }, 250);
	}

	var KNOWN_SLUGS = {$knownJson};

	function attachAutocomplete(tab, profile){
		var input = tab.querySelector('.cmx-ac-input');
		if(!input) return;

		// Portal-Dropdown
		var list=document.createElement("div"); list.className="cmx-ac-list"; document.body.appendChild(list);
		function openList(){ list.style.display="block"; positionList(); }
		function closeList(){ list.style.display="none"; list.innerHTML=""; }
		function positionList(){ var r=input.getBoundingClientRect(); list.style.top=(window.scrollY+r.bottom)+"px"; list.style.left=(window.scrollX+r.left)+"px"; list.style.width=r.width+"px"; }
		window.addEventListener("scroll",positionList,true); window.addEventListener("resize",positionList); input.addEventListener("focus",positionList);

		var results=[], activeIndex=-1, ctrl={};

		function render(){
			if(!results.length){ closeList(); return; }
			var html="";
			for(var i=0;i<results.length;i++){
				var r=results[i];
				var line = (r.display_name||'').replace(/</g,'&lt;').replace(/>/g,'&gt;');
				html+='<div class="cmx-ac-item'+(i===activeIndex?' is-active':'')+'" data-i="'+i+'">'+line+'</div>';
			}
			list.innerHTML=html; openList(); ensureVisible();
		}
		function ensureVisible(){
			var act=list.querySelector(".cmx-ac-item.is-active"); if(!act) return;
			var ar=act.getBoundingClientRect(), lr=list.getBoundingClientRect();
			if(ar.bottom>lr.bottom) list.scrollTop += (ar.bottom - lr.bottom);
			if(ar.top<lr.top) list.scrollTop -= (lr.top - ar.top);
		}
		function setActive(idx){
			if(!results.length) return;
			if(idx<0) idx=results.length-1;
			if(idx>=results.length) idx=0;
			activeIndex = idx;
			\$all(".cmx-ac-item",list).forEach(function(el,i){ el.classList.toggle("is-active", i===activeIndex); });
			ensureVisible();
		}
		function pick(i){
			var r=results[i]; if(!r) return;
			closeList();
			var addr = r.address||{};
			var street = (addr.road||addr.pedestrian||addr.footway||addr.cycleway||addr.path||'') + (addr.house_number?(' '+addr.house_number):'');
			var zip    = addr.postcode||'';
			var city   = addr.city||addr.town||addr.village||addr.hamlet||'';
			var cc     = (addr.country_code||'').toLowerCase();

			setVal(field(tab, profile, 'strasse'), street);
			setVal(field(tab, profile, 'plz'), zip);
			setVal(field(tab, profile, 'ort'), city);

			// Land setzen – nur wenn der Länderslug existiert
			var sel = field(tab, profile, 'land');
			if(sel && cc && KNOWN_SLUGS.indexOf(cc)!==-1){
				setVal(sel, cc);
			}

			var lat = parseFloat(r.lat), lon = parseFloat(r.lon);
			if(!isNaN(lat) && !isNaN(lon)){
				updateMap(profile, lat, lon, r.display_name||'');
			}
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
			// Nominatim: weltweite Suche
			var url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=10&q='+encodeURIComponent(q);
			fetch(url,{signal:ctrl.signal,headers:{
				'Accept':'application/json',
				// Nominatim nutzt Browser-User-Agent/Referer zur Fair-Use-Erkennung
				'Accept-Language': (navigator.language||'de')
			}})
				.then(function(r){ return r.json(); })
				.then(function(j){
					results = Array.isArray(j) ? j : [];
					activeIndex = results.length ? 0 : -1;
					render();
				})
				.catch(function(){ /* ignore */ });
		});
	}

	function initTabs(){
		// Tabs schalten
		document.querySelectorAll(".cmx-tab-buttons button").forEach(function(btn){
			btn.addEventListener("click", function(){
				var tab=btn.dataset.tab;
				document.querySelectorAll(".cmx-tab-buttons button").forEach(function(b){ b.classList.remove("active"); });
				document.querySelectorAll(".cmx-tab-content").forEach(function(c){ c.classList.remove("active"); });
				btn.classList.add("active");
				document.getElementById("cmx-tab-"+tab).classList.add("active");
				// Map-Resize nach Tabwechsel
				setTimeout(function(){
					var canvas=document.getElementById('cmx-map-'+tab+'-canvas');
					if(canvas && canvas.__leafletMap){ canvas.__leafletMap.invalidateSize(); }
				}, 200);
			});
		});

		// Autocomplete + initiale Karte
		['rechnung','liefer'].forEach(function(profile){
			var tab=document.getElementById('cmx-tab-'+profile);
			if(!tab) return;
			attachAutocomplete(tab, profile);

			// Initiale Map anhand vorhandener Werte (wenn Ort/PLZ/Strasse befüllt)
			var parts=[];
			var f = function(role){ var el=field(tab, profile, role); return el?String(el.value||'').trim():''; };
			['strasse','zusatz','plz','ort','land'].forEach(function(role){ var v=f(role); if(v) parts.push(v); });
			var q = parts.join(', ');
			if(q.length>=3){
				// einmalige Positionssuche für die Vorschau
				var url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q='+encodeURIComponent(q);
				fetch(url,{headers:{'Accept':'application/json','Accept-Language':(navigator.language||'de')}})
					.then(function(r){ return r.json(); })
					.then(function(j){
						if(Array.isArray(j) && j[0]){
							var lat=parseFloat(j[0].lat), lon=parseFloat(j[0].lon);
							if(!isNaN(lat)&&!isNaN(lon)) updateMap(profile, lat, lon, j[0].display_name||'');
						}else{
							ensureMap(profile); // leere Weltkarte
						}
					})
					.catch(function(){ ensureMap(profile); });
			}else{
				ensureMap(profile); // leere Weltkarte
			}

			// Live-Refresh der Karte, wenn Felder geändert werden (debounced)
			var timer=null;
			\$all('[data-cmx-addr="'+profile+'"]').forEach(function(el){
				['input','change'].forEach(function(evt){
					el.addEventListener(evt, function(){
						clearTimeout(timer);
						timer=setTimeout(function(){
							var parts=[];
							['strasse','zusatz','plz','ort','land'].forEach(function(role){
								var el2 = field(tab, profile, role);
								if(!el2) return;
								var val = (el2.tagName==='SELECT' && el2.selectedOptions[0]) ? el2.selectedOptions[0].text : el2.value;
								val = String(val||'').trim();
								if(val) parts.push(val);
							});
							var q = parts.join(', ');
							if(q.length<3) return;
							var url='https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q='+encodeURIComponent(q);
							fetch(url,{headers:{'Accept':'application/json','Accept-Language':(navigator.language||'de')}})
								.then(function(r){ return r.json(); })
								.then(function(j){
									if(Array.isArray(j) && j[0]){
										var lat=parseFloat(j[0].lat), lon=parseFloat(j[0].lon);
										if(!isNaN(lat)&&!isNaN(lon)) updateMap(profile, lat, lon, j[0].display_name||'');
									}
								})
								.catch(function(){});
						}, 400);
					});
				});
			});
		});
	}

	if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', initTabs); else initTabs();
})();
JS;
	\wp_add_inline_script('cmx-addr-inline', $js);
}
