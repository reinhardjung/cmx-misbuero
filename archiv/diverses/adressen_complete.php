<?php
/**
 * Plugin Name: CMX – Adress-Autocomplete (CH) – Straße+Hausnummer & Keyboard
 * Description: geo.admin.ch Autocomplete ohne API-Key. Straße + Hausnummer werden gemeinsam ins Strassenfeld geschrieben. Pfeiltasten/Enter/Esc, Portal-Dropdown, data-field/data-cmx-field/data-cmx-addr.
 * Version: 1.5.0
 * Author: CLOUDMEISTER
 * License: GPL-2.0+
 */
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit;

/** Auf welchen Post Types aktiv? */
const CMX_ADDR_POST_TYPES = ['kontakte']; // bei Bedarf ergänzen

/** ---------- CSS ---------- */
if (!function_exists(__NAMESPACE__.'\\cmx_ac_css')) {
	function cmx_ac_css() {
		return <<<'CSS'
.cmx-ac-wrap{position:relative;margin:8px 0 12px}
.cmx-ac-input{width:100%;max-width:280px;line-height:1.4;padding:8px 10px;border:1px solid #ccd0d4;border-radius:4px;background:#fff}
.cmx-ac-input:focus{outline:0;border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}
.cmx-ac-list{position:absolute;z-index:999999;width:420px;max-width:640px;max-height:300px;overflow:auto;border:1px solid #d0d7de;border-radius:6px;background:#fff;box-shadow:0 8px 24px rgba(31,35,40,.15);display:none}
.cmx-ac-item{padding:10px 12px;cursor:pointer;font-size:13px;line-height:1.4;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cmx-ac-item:hover{background:#f6f8fa}
.cmx-ac-item.is-active{background:#e7f3ff}
.cmx-ac-hint{color:#666;font-size:12px;margin:6px 0 0}
CSS;
	}
}

/** ---------- JS ---------- */
if (!function_exists(__NAMESPACE__.'\\cmx_ac_js')) {
	function cmx_ac_js() {
		return <<<'JS'
(function(){
	'use strict';

	/* Helpers */
	function $(s,r){return (r||document).querySelector(s);}
	function $all(s,r){return Array.prototype.slice.call((r||document).querySelectorAll(s));}
	function stripHTML(s){ var d=document.createElement('div'); d.innerHTML=String(s||''); return d.textContent||d.innerText||''; }

	// robustes Setzen inkl. Events + blur-Bump (für Frameworks)
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
			el.value=val;
			el.setAttribute('value',val);
		}
		el.dispatchEvent(new Event('input',{bubbles:true}));
		el.dispatchEvent(new Event('change',{bubbles:true}));
		setTimeout(function(){ try{ el.dispatchEvent(new Event('blur',{bubbles:true})); }catch(e){} },10);

		if(wasDis) el.disabled=true;
		if(wasRO) el.readOnly=true;
	}

	/* API */
	function buildQuery(q){
		return 'https://api3.geo.admin.ch/rest/services/api/SearchServer?sr=4326&type=locations&origins=address&lang=de&searchText='+encodeURIComponent(q);
	}

	// attrs sind nicht immer befüllt → aus label parsen
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
			lbl=lbl.replace(/\s+/g,' ').trim();
			// "Straße 12a 8000 Stadt (ZH)"
			var m=lbl.match(/^(.+?)\s+(\d+[a-zA-Z]?)\s+(?:CH-)?(\d{4})\s+(.+?)(?:\s*\(([^)]+)\))?$/);
			if(m) return {street:m[1],number:m[2],zip:m[3],city:m[4],canton:(m[5]||'')};
			// "Straße 8000 Stadt"
			m=lbl.match(/^(.+?)\s+(?:CH-)?(\d{4})\s+(.+?)(?:\s*\(([^)]+)\))?$/);
			if(m) return {street:m[1],number:'',zip:m[2],city:m[3],canton:(m[4]||'')};
			// "Straße 12a, 8000 Stadt"
			m=lbl.match(/^(.+?)\s+(\d+[a-zA-Z]?)\s*,\s*(?:CH-)?(\d{4})\s+(.+?)$/);
			if(m) return {street:m[1],number:m[2],zip:m[3],city:m[4],canton:''};
			// "Straße, 8000 Stadt"
			m=lbl.match(/^(.+?)\s*,\s*(?:CH-)?(\d{4})\s+(.+?)$/);
			if(m) return {street:m[1],number:'',zip:m[2],city:m[3],canton:''};
			// Fallback via PLZ
			m=lbl.match(/(\d{4})/);
			if(m){
				var plz=m[1];
				var parts=lbl.split(plz);
				var left=(parts[0]||'').trim();
				var right=(parts[1]||'').trim();
				var m2=left.match(/^(.+?)\s+(\d+[a-zA-Z]?)$/);
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

	/* Feldsuche */
	function findField(scope,role){
		return scope.querySelector('[data-field="'+role+'"], [data-cmx-field="'+role+'"]');
	}
	function resolveFields(container,tabKey){
		var f={
			strasse:findField(container,'strasse'),
			plz:findField(container,'plz'),
			ort:findField(container,'ort'),
			kanton:findField(container,'kanton'),
			land:findField(container,'land'),
			lat:findField(container,'lat'),
			lng:findField(container,'lng'),
			formatted:findField(container,'formatted')
		};
		// Fallbacks innerhalb aktiver Pane
		var pane=$('.cmx-tab-content.active')||container;
		Object.keys(f).forEach(function(k){ if(!f[k]) f[k]=findField(pane,k); });
		// Globale Fallbacks mit data-cmx-addr
		if(tabKey){
			Object.keys(f).forEach(function(k){
				if(!f[k]) f[k]=document.querySelector('[data-cmx-addr="'+tabKey+'"][data-field="'+k+'"], [data-cmx-addr="'+tabKey+'"][data-cmx-field="'+k+'"]');
			});
		}
		return f;
	}

	/* UI-Bindung mit Keyboard */
	function attachToTab(container,ph){
		// Eingabefeld + Hinweis
		var wrap=container.querySelector(':scope > .cmx-ac-wrap');
		if(!wrap){
			wrap=document.createElement('div');
			wrap.className='cmx-ac-wrap';
			wrap.innerHTML='<label><strong>Adresse suchen (CH)</strong></label><br>'
				+'<input type="text" class="cmx-ac-input" placeholder="'+(ph||'Adresse suchen…').replace(/"/g,'&quot;')+'" aria-label="Adresse suchen">'
				// +'<div class="cmx-ac-hint">Quelle: api3.geo.admin.ch – kein API-Key.</div>';
			container.insertBefore(wrap,container.firstChild);
		}
		var input=wrap.querySelector('.cmx-ac-input');

		// Portal-Dropdown
		var list=document.createElement('div'); list.className='cmx-ac-list'; document.body.appendChild(list);
		function openList(){ list.style.display='block'; positionList(); }
		function closeList(){ list.style.display='none'; list.innerHTML=''; }
		function positionList(){ var r=input.getBoundingClientRect(); list.style.top=(window.scrollY+r.bottom)+'px'; list.style.left=(window.scrollX+r.left)+'px'; list.style.width=r.width+'px'; }
		window.addEventListener('scroll',positionList,true); window.addEventListener('resize',positionList); input.addEventListener('focus',positionList);

		// Tab-Key
		var tabKey=(container.getAttribute('data-cmx-addr')||'').toLowerCase();
		if(!tabKey){ var id=(container.id||'').toLowerCase(); var m=id.match(/rechnung|lieferung|billing|shipping/); tabKey=m?m[0]:''; }

		var fields=resolveFields(container,tabKey);
		var results=[], activeIndex=-1, ctrl={};

		function render(){
			if(!results.length){ closeList(); return; }
			var html='';
			for(var i=0;i<results.length;i++){
				html+='<div class="cmx-ac-item'+(i===activeIndex?' is-active':'')+'" data-i="'+i+'">'+results[i].label+'</div>';
			}
			list.innerHTML=html;
			openList();
			ensureVisible();
		}
		function setActive(idx){
			if(!results.length) return;
			if(idx<0) idx=results.length-1;
			if(idx>=results.length) idx=0;
			activeIndex=idx;
			$all('.cmx-ac-item',list).forEach(function(el,i){ el.classList.toggle('is-active', i===activeIndex); });
			ensureVisible();
		}
		function ensureVisible(){
			var act=list.querySelector('.cmx-ac-item.is-active'); if(!act) return;
			var ar=act.getBoundingClientRect(), lr=list.getBoundingClientRect();
			if(ar.bottom>lr.bottom) list.scrollTop += (ar.bottom - lr.bottom);
			if(ar.top<lr.top) list.scrollTop -= (lr.top - ar.top);
		}
		function pick(idx){
			var r=results[idx]; if(!r) return;
			fields=resolveFields(container,tabKey); // lazy nachladen
			closeList();

			// Straße + Hausnummer zusammen in "strasse"
			var streetFull=[r.street,r.number].filter(Boolean).join(' ');
			setVal(fields.strasse, streetFull);
			setVal(fields.plz, r.zip||'');
			setVal(fields.ort, r.city||'');
			setVal(fields.kanton, r.canton||'');
			setVal(fields.land, 'CH');
			setVal(fields.lat, r.lat||'');
			setVal(fields.lng, r.lng||'');
			setVal(fields.formatted, r.formatted||r.label||'');
		}

		// Maus: hover & click
		list.addEventListener('mousemove', function(e){
			var item=e.target.closest('.cmx-ac-item'); if(!item) return;
			var i=parseInt(item.getAttribute('data-i'),10);
			if(!isNaN(i) && i!==activeIndex) setActive(i);
		});
		list.addEventListener('mousedown', function(e){
			var item=e.target.closest('.cmx-ac-item'); if(!item) return;
			e.preventDefault();
			pick(parseInt(item.getAttribute('data-i'),10));
		});
		document.addEventListener('click', function(e){ if(!list.contains(e.target) && e.target!==input) closeList(); });

		// Keyboard: ↑/↓/Enter/Esc
		input.addEventListener('keydown', function(e){
			if(list.style.display!=='block' && (e.key==='ArrowDown' || e.key==='ArrowUp')){ // öffne & aktiviere ersten Treffer
				if(results.length){ e.preventDefault(); activeIndex=0; render(); return; }
			}
			if(list.style.display!=='block') return;
			if(e.key==='ArrowDown'){ e.preventDefault(); setActive(activeIndex+1); }
			else if(e.key==='ArrowUp'){ e.preventDefault(); setActive(activeIndex-1); }
			else if(e.key==='Enter'){ e.preventDefault(); if(activeIndex>=0) pick(activeIndex); }
			else if(e.key==='Escape'){ e.preventDefault(); closeList(); }
		});

		// Suche
		input.addEventListener('input', function(){
			var q=input.value.trim();
			if(q.length<3){ closeList(); results=[]; activeIndex=-1; return; }
			if(ctrl.abort) try{ ctrl.abort(); }catch(_){}
			ctrl=new AbortController();
			fetch(buildQuery(q),{signal:ctrl.signal,headers:{'Accept':'application/json'}})
				.then(function(r){ return r.json(); })
				.then(function(j){
					results=(j.results||[]).map(normalize).filter(function(x){ return (x.country||'')==='CH'; });
					activeIndex = results.length ? 0 : -1;
					render();
				})
				.catch(function(){ /* ignore abort */ });
		});
	}

	/* Tabs finden & binden */
	function findContainers(){
		var tabs=$all('.cmx-tab-content');
		if(tabs.length) return tabs;
		var fallback=$('.cmx-tabs');
		return fallback ? [fallback] : [];
	}
	function init(){
		var containers=findContainers();
		containers.forEach(function(tab,i){
			if(tab.__cmxBound) return;
			tab.__cmxBound=true;
			attachToTab(tab, i===0?'Adresse suchen (Rechnung)…':'Adresse suchen (Lieferung)…');
		});
		var obs=new MutationObserver(function(){ findContainers().forEach(function(t,i){ if(!t.__cmxBound){ t.__cmxBound=true; attachToTab(t, i===0?'Adresse suchen (Rechnung)…':'Adresse suchen (Lieferung)…'); } }); });
		obs.observe(document.documentElement||document.body,{childList:true,subtree:true});
		document.addEventListener('click', function(e){
			if(e.target && (e.target.matches('.cmx-tab-buttons button') || e.target.closest('.cmx-tab-buttons button'))){
				$all('.cmx-ac-list').forEach(function(el){ el.style.display='none'; });
			}
		});
	}
	if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', init); else init();

})();
JS;
	}
}

/** ---------- Enqueue ---------- */
add_action('admin_enqueue_scripts', function($hook){
	if(!in_array($hook,['post.php','post-new.php'],true)) return;
	$screen=function_exists('get_current_screen')?get_current_screen():null;
	if(!$screen || !in_array($screen->post_type, CMX_ADDR_POST_TYPES, true)) return;

	wp_enqueue_style('wp-components');
	wp_add_inline_style('wp-components', cmx_ac_css());

	wp_register_script('cmx-ac', false, [], null, true);
	wp_enqueue_script('cmx-ac');
	wp_add_inline_script('cmx-ac', cmx_ac_js());
});
