<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/* =========================================================
 * Helpers
 * ======================================================= */
function cmx_meta_get(int $post_id, string $key, $default = '') {
	$val = \get_post_meta($post_id, $key, true);
	return ($val === '' ? $default : $val);
}
function cmx_get_single_term_id(int $post_id, string $taxonomy): int {
	if (!\taxonomy_exists($taxonomy)) return 0;
	$terms = \wp_get_post_terms($post_id, $taxonomy, ['fields' => 'ids']);
	if (\is_wp_error($terms) || empty($terms)) return 0;
	return (int) $terms[0];
}
function cmx_get_terms_safe(string $taxonomy): array {
	if (!\taxonomy_exists($taxonomy)) return [];
	$terms = \get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
	return \is_wp_error($terms) ? [] : $terms;
}

function cmx_artikel_remove_taxonomy_metabox(string $taxonomy): void {
	if ($taxonomy === '') {
		return;
	}
	foreach (['side', 'normal', 'advanced'] as $context) {
		\remove_meta_box('tagsdiv-' . $taxonomy, 'artikel', $context);
		\remove_meta_box($taxonomy . 'div', 'artikel', $context);
	}
}

function cmx_artikel_taxonomy_slug(string $label): string {
	return cmx_tax_key('artikel', cmx_no_umlaute($label));
}

function cmx_artikel_make_taxonomy_metabox_title_link(string $taxonomy, string $box_id, string $fallback_label): void {
	if ($taxonomy === '' || !\taxonomy_exists($taxonomy)) {
		return;
	}

	$url = \admin_url('edit-tags.php?taxonomy=' . \rawurlencode($taxonomy) . '&post_type=artikel');
	echo '<script>';
	echo 'document.addEventListener("DOMContentLoaded",function(){';
	echo 'var box=document.getElementById(' . \wp_json_encode($box_id) . ');';
	echo 'if(!box)return;';
	echo 'var title=box.querySelector(".postbox-header .hndle, .postbox-header h2, .hndle, h2.hndle");';
	echo 'if(!title||title.querySelector("a[data-cmx-tax-link=\\"1\\"]"))return;';
	echo 'var text=(title.textContent||"").trim()||' . \wp_json_encode($fallback_label) . ';';
	echo 'title.textContent="";';
	echo 'var link=document.createElement("a");';
	echo 'link.href=' . \wp_json_encode($url) . ';';
	echo 'link.target="_blank";';
	echo 'link.rel="noopener noreferrer";';
	echo 'link.dataset.cmxTaxLink="1";';
	echo 'link.style.textDecoration="none";';
	echo 'link.style.color="inherit";';
	echo 'link.style.font="inherit";';
	echo 'link.style.fontSize="inherit";';
	echo 'link.style.fontWeight="inherit";';
	echo 'link.style.lineHeight="inherit";';
	echo 'link.textContent=text;';
	echo 'title.appendChild(link);';
	echo '});';
	echo '</script>';
}

\add_action('admin_print_footer_scripts', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== 'artikel' || (string) ($screen->base ?? '') !== 'post') {
		return;
	}

	if (\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_KATEGORIEN')) {
		$taxonomy = (string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_KATEGORIEN');
		cmx_artikel_make_taxonomy_metabox_title_link($taxonomy, $taxonomy . 'div', 'Kategorien');
		cmx_artikel_make_taxonomy_metabox_title_link($taxonomy, 'tagsdiv-' . $taxonomy, 'Kategorien');
	}

	if (\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_TYPEN')) {
		$taxonomy = (string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_TYPEN');
		cmx_artikel_make_taxonomy_metabox_title_link($taxonomy, $taxonomy . 'div', 'Typen');
		cmx_artikel_make_taxonomy_metabox_title_link($taxonomy, 'tagsdiv-' . $taxonomy, 'Typen');
	}

	if (\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_FARBEN')) {
		cmx_artikel_make_taxonomy_metabox_title_link((string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_FARBEN'), 'cmx_artikel_farbe_side', 'Farben');
	}

	if (\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_MARKEN')) {
		cmx_artikel_make_taxonomy_metabox_title_link((string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_MARKEN'), 'cmx_artikel_marke_side', 'Marke');
	}
});

/** CSV → eindeutige Integer-IDs */
function cmx_csv_ids_to_array(string $csv): array {
	$out = [];
	foreach (explode(',', $csv) as $p) {
		$id = (int) trim($p);
		if ($id > 0) $out[$id] = $id;
	}
	return array_values($out);
}

/* =========================================================
 * Titel-Fallback ohne Save-Loop (bleibt erhalten)
 * ======================================================= */
\add_filter('wp_insert_post_data', function(array $data, array $postarr) {
	if (($postarr['post_type'] ?? '') !== 'artikel') return $data;

	$title      = isset($data['post_title']) ? trim(wp_strip_all_tags((string) $data['post_title'])) : '';
	$artikel_nr = isset($_POST['cmx_artikel_sku']) ? trim(wp_strip_all_tags((string) $_POST['cmx_artikel_sku'])) : '';

	if ($title === '') {
		$data['post_title'] = ($artikel_nr !== '' ? $artikel_nr : 'Artikelname fehlt');
	}
	if ($title === 'Artikelname fehlt' && $artikel_nr !== '') {
		$data['post_title'] = $artikel_nr;
	}
	return $data;
}, 10, 2);


/* =========================================================
 * Core-Taxo-Boxen ausblenden (UNVERÄNDERT)
 * ======================================================= */
\add_action('admin_menu', function () {
	$groessen_taxonomy = cmx_artikel_taxonomy_slug('Grössen');
	$ausfuehrungen_taxonomy = cmx_artikel_taxonomy_slug('Ausführungen');
	\remove_meta_box('tagsdiv-'.TAX_ARTIKEL_MARKEN,    'artikel', 'side');
	\remove_meta_box(TAX_ARTIKEL_MARKEN.'div',         'artikel', 'side');
	\remove_meta_box('tagsdiv-'.TAX_ARTIKEL_FARBEN,    'artikel', 'side');
	\remove_meta_box(TAX_ARTIKEL_FARBEN.'div',         'artikel', 'side');
	\remove_meta_box('tagsdiv-'.TAX_ARTIKEL_EINHEITEN, 'artikel', 'side');
	\remove_meta_box(TAX_ARTIKEL_EINHEITEN.'div',      'artikel', 'side');
	cmx_artikel_remove_taxonomy_metabox($groessen_taxonomy);
	if (\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_MATERIALIEN')) {
		\remove_meta_box('tagsdiv-'.TAX_ARTIKEL_MATERIALIEN, 'artikel', 'side');
		\remove_meta_box(TAX_ARTIKEL_MATERIALIEN.'div',      'artikel', 'side');
	}
	cmx_artikel_remove_taxonomy_metabox($ausfuehrungen_taxonomy);

	// ALT: Stammdaten-Metabox entfernen (nur UI, KEINE Taxonomien!)
	\remove_meta_box('cmx_artikel_stammdaten', 'artikel', 'normal');
}, 50);

\add_action('do_meta_boxes', function ($post_type): void {
	if ((string) $post_type !== 'artikel') {
		return;
	}
	cmx_artikel_remove_taxonomy_metabox(cmx_artikel_taxonomy_slug('Grössen'));
	cmx_artikel_remove_taxonomy_metabox(cmx_artikel_taxonomy_slug('Ausführungen'));
}, 100, 1);

\add_action('admin_head', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== 'artikel' || !\in_array((string) ($screen->base ?? ''), ['post', 'post-new'], true)) {
		return;
	}
	$selectors = [];
	$taxonomy = cmx_artikel_taxonomy_slug('Grössen');
	$selectors[] = '#tagsdiv-' . $taxonomy;
	$selectors[] = '#' . $taxonomy . 'div';
	$taxonomy = cmx_artikel_taxonomy_slug('Ausführungen');
	$selectors[] = '#tagsdiv-' . $taxonomy;
	$selectors[] = '#' . $taxonomy . 'div';
	if ($selectors === []) {
		return;
	}
	echo '<style>' . \implode(',', $selectors) . '{display:none !important;}</style>';
}, 100);

/* =========================================================
 * NEUE/BEIBEBLIEBENE Metaboxen (ohne Artikel-Nr.-Sidebox)
 * ======================================================= */
\add_action('add_meta_boxes', function () {
	// KEINE Artikel-Nr.-Metabox mehr hier!

	// Marke in SIDE
	\add_meta_box('cmx_artikel_marke_side', 'Marke', __NAMESPACE__.'\\cmx_artikel_marke_side_box', 'artikel', 'side', 'default');
});

/* =========================================================
 * Metabox: Farben (SIDE, Mehrfach)
 * ======================================================= */
function cmx_artikel_farbe_side_box(\WP_Post $post): void {
	$terms = cmx_get_terms_safe(TAX_ARTIKEL_FARBEN);

	$selected_ids = [];
	if (\taxonomy_exists(TAX_ARTIKEL_FARBEN)) {
		$selected_ids = \wp_get_post_terms($post->ID, TAX_ARTIKEL_FARBEN, ['fields' => 'ids']);
		if (\is_wp_error($selected_ids)) $selected_ids = [];
	}

	echo '<div class="cmx-art-side">';
	echo '<input type="hidden" name="cmx_artikel_farbe_payload" value="1">';
	if (empty($terms)) {
		echo '<p><em>Keine Farben definiert.</em></p>';
	} else {
		echo '<ul style="margin:0;padding-left:0;list-style:none;max-height:220px;overflow:auto">';
		foreach ($terms as $t) {
			$id = (int)$t->term_id;
			$checked = in_array($id, array_map('intval',$selected_ids), true) ? ' checked' : '';
			echo '<li style="margin:2px 0;"><label>';
			echo '<input type="checkbox" name="cmx_artikel_farbe_ids[]" value="'.$id.'"'.$checked.'> ';
			echo esc_html($t->name);
			if (!empty($t->description)) {
				echo '<br><small>'.esc_html(wp_strip_all_tags((string)$t->description)).'</small>';
			}
			echo '</label></li>';
		}
		echo '</ul>';
	}
	echo '</div>';
}

/* =========================================================
 * Metabox: Marke (SIDE, Single)
 * ======================================================= */
function cmx_artikel_marke_side_box(\WP_Post $post): void {
	$sel_id = cmx_get_single_term_id($post->ID, TAX_ARTIKEL_MARKEN);
	$terms  = cmx_get_terms_safe(TAX_ARTIKEL_MARKEN);
	$selected_title = '';
	$term_items = [];
	foreach ($terms as $t) {
		$term_id = (int) $t->term_id;
		$term_name = (string) $t->name;
		if ($term_id === $sel_id) {
			$selected_title = $term_name;
		}
		$term_items[] = [
			'id'    => $term_id,
			'title' => $term_name,
		];
	}
	$term_items_json = \wp_json_encode($term_items);

	echo '<input type="hidden" name="cmx_artikel_marke_payload" value="1">';
	echo '<style>
	#cmx_artikel_marke_side,
	#cmx_artikel_marke_side .inside,
	#cmx-artikel-marke-box{overflow:visible !important}
	#cmx_artikel_marke_side .inside,
	#cmx-artikel-marke-box,
	#cmx-artikel-marke-box .cmx-marke-suggest{position:relative}
	#cmx-artikel-marke-box .cmx-marke-suggest{position:relative}
	#cmx-artikel-marke-box .cmx-marke-input-row{display:flex;align-items:center;gap:6px}
	#cmx-artikel-marke-box .cmx-marke-input-row input[type=text]{flex:1 1 auto;min-width:0}
	#cmx-artikel-marke-box .cmx-marke-suggest-list{
		position:absolute;
		z-index:100002;
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
	#cmx-artikel-marke-box .cmx-marke-suggest-list li{margin:0;padding:6px 8px;cursor:pointer}
	#cmx-artikel-marke-box .cmx-marke-suggest-list li.active{background:#e5f3ff}
	#cmx-artikel-marke-box .cmx-marke-suggest-list li:hover{background:#f3f4f5}
	</style>';
	echo '<div id="cmx-artikel-marke-box">';
	echo '<div class="cmx-marke-suggest">';
	echo '<div class="cmx-marke-input-row">';
	echo '<input type="text" id="cmx_artikel_marke_search" class="widefat" autocomplete="off" aria-label="Marke suchen" placeholder="Marke suchen..." value="' . \esc_attr($selected_title) . '">';
	echo '<input type="hidden" id="cmx_artikel_marke" name="cmx_artikel_marke" value="' . \esc_attr((string) $sel_id) . '">';
	echo '</div>';
	echo '<ul id="cmx_artikel_marke_suggest" class="cmx-marke-suggest-list" style="display:none"></ul>';
	echo '</div>';
	echo '</div>';
	echo '<script>
	(function(){
		var input=document.getElementById("cmx_artikel_marke_search");
		var hidden=document.getElementById("cmx_artikel_marke");
		var list=document.getElementById("cmx_artikel_marke_suggest");
		if(!input||!hidden||!list) return;
		var items=' . ($term_items_json ?: '[]') . ';
		items.forEach(function(item){
			item.id=parseInt(item.id||0,10)||0;
			item.title=item.title||"";
			item.titleLower=item.title.toLocaleLowerCase();
		});
		items.sort(function(a,b){
			if(a.titleLower<b.titleLower) return -1;
			if(a.titleLower>b.titleLower) return 1;
			return 0;
		});
		var byId={};
		items.forEach(function(item){
			if(item.id>0) byId[String(item.id)]=item;
		});
		function esc(s){
			return (s||"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");
		}
		function makeNavigator(inputEl,listEl,chooseCb){
			var active=-1, navItems=[];
			function closeList(){
				listEl.style.display="none";
				listEl.innerHTML="";
				active=-1;
			}
			function render(arr){
				navItems=arr||[];
				if(!navItems.length){ closeList(); return; }
				listEl.innerHTML=navItems.map(function(it,i){
					return "<li data-index=\""+i+"\">"+esc(it.title)+"</li>";
				}).join("");
				listEl.style.display="block";
				active=-1;
			}
			function move(delta){
				if(!navItems.length) return;
				active=(active+delta+navItems.length)%navItems.length;
				Array.prototype.forEach.call(listEl.children,function(li,i){
					li.classList.toggle("active", i===active);
				});
			}
			function choose(index){
				if(index<0||index>=navItems.length) return;
				chooseCb(navItems[index]);
				closeList();
			}
			listEl.addEventListener("mousedown", function(e){
				var li=e.target.closest("li");
				if(!li) return;
				e.preventDefault();
				choose(parseInt(li.dataset.index||"-1",10));
			});
			inputEl.addEventListener("keydown", function(e){
				if(listEl.style.display!=="block"&&(e.key==="ArrowDown"||e.key==="ArrowUp")) return;
				if(e.key==="ArrowDown"){ e.preventDefault(); move(1); }
				else if(e.key==="ArrowUp"){ e.preventDefault(); move(-1); }
				else if(e.key==="Enter"){ if(active>-1){ e.preventDefault(); choose(active); } }
				else if(e.key==="Escape"){ closeList(); }
			});
			inputEl.addEventListener("blur", function(){
				window.setTimeout(function(){
					var ae=document.activeElement;
					if(ae===inputEl||listEl.contains(ae)) return;
					closeList();
				}, 120);
			});
			document.addEventListener("click", function(e){
				if(!listEl.contains(e.target)&&e.target!==inputEl){ closeList(); }
			});
			return {
				render: render,
				reset: function(){ navItems=[]; active=-1; }
			};
		}
		var navigator=makeNavigator(input,list,chooseItem);
		var timer=null;
		function chooseItem(item, keepFocus){
			hidden.value=item&&item.id?String(item.id):"0";
			input.value=item&&item.title?item.title:"";
			input.dataset.selectedTitle=item&&item.title?item.title:"";
			hidden.dispatchEvent(new Event("change",{bubbles:true}));
			if(keepFocus!==false) input.focus();
		}
		function exactMatch(query){
			var q=(query||"").trim().toLocaleLowerCase();
			if(!q) return null;
			var matches=items.filter(function(item){ return item.titleLower===q; });
			return matches.length===1 ? matches[0] : null;
		}
		function matchedItems(query){
			var q=(query||"").trim().toLocaleLowerCase();
			var activeId=parseInt(hidden.value||"0",10)||0;
			var matches=items.slice();
			if(q){
				matches=matches.filter(function(item){ return item.titleLower.indexOf(q)!==-1; });
				matches.sort(function(a,b){
					var aStarts=a.titleLower.indexOf(q)===0 ? 0 : 1;
					var bStarts=b.titleLower.indexOf(q)===0 ? 0 : 1;
					if(aStarts!==bStarts) return aStarts-bStarts;
					if(a.titleLower<b.titleLower) return -1;
					if(a.titleLower>b.titleLower) return 1;
					return 0;
				});
			} else if(activeId>0) {
				matches.sort(function(a,b){
					if(a.id===activeId&&b.id!==activeId) return -1;
					if(b.id===activeId&&a.id!==activeId) return 1;
					if(a.titleLower<b.titleLower) return -1;
					if(a.titleLower>b.titleLower) return 1;
					return 0;
				});
			}
			return matches.slice(0,50);
		}
		function renderSuggestions(showAll){
			var query=(input.value||"").trim();
			if(!showAll&&query.length<1){
				navigator.reset();
				list.style.display="none";
				list.innerHTML="";
				return;
			}
			navigator.render(matchedItems(showAll ? "" : query));
		}
		function syncFieldFromHidden(){
			var item=byId[String(hidden.value||"")]||null;
			if(item){
				input.value=item.title||"";
				input.dataset.selectedTitle=item.title||"";
			} else if((hidden.value||"")===""||String(hidden.value||"0")==="0"){
				input.dataset.selectedTitle="";
			}
		}
		input.addEventListener("input", function(){
			hidden.value="0";
			if(timer) clearTimeout(timer);
			var query=(input.value||"").trim();
			if(query.length===0){
				renderSuggestions(true);
				return;
			}
			timer=window.setTimeout(function(){ renderSuggestions(false); }, 120);
		});
		input.addEventListener("focus", function(){
			renderSuggestions((input.value||"").trim()==="");
		});
		input.addEventListener("click", function(){
			renderSuggestions((input.value||"").trim()==="");
		});
		input.addEventListener("blur", function(){
			window.setTimeout(function(){
				if(String(hidden.value||"0")==="0"){
					var match=exactMatch(input.value||"");
					if(match) chooseItem(match, false);
				}
			}, 130);
		});
		hidden.addEventListener("change", syncFieldFromHidden);
		syncFieldFromHidden();
	})();
	</script>';
}

/* =========================================================
 * Speichern NUR für Farben/Marke (SKU wird in preise.php gespeichert)
 * ======================================================= */
\add_action('save_post_artikel', function (int $post_id, \WP_Post $post) {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) return;
	if ($post->post_type !== 'artikel') return;
	if (!\current_user_can('edit_post', $post_id)) return;

	if (!isset($_POST['cmx_artikel_nonce']) || !\wp_verify_nonce($_POST['cmx_artikel_nonce'], 'cmx_artikel_save')) return;

	$in = fn($k, $d='') => ($_POST[$k] ?? $d);

	// Farben (Mehrfach)
	if (\taxonomy_exists(TAX_ARTIKEL_FARBEN) && isset($_POST['cmx_artikel_farbe_payload'])) {
		$ids = array_map('intval', (array)($in('cmx_artikel_farbe_ids', [])));
		if (empty($ids) && isset($_POST['cmx_artikel_farben_csv'])) {
			$ids = cmx_csv_ids_to_array((string)$in('cmx_artikel_farben_csv', ''));
		}
		$ids = array_values(array_filter($ids, fn($v)=>$v>0));
		\wp_set_post_terms($post_id, $ids, TAX_ARTIKEL_FARBEN, false);
	}

	// Marke (Single)
	if (\taxonomy_exists(TAX_ARTIKEL_MARKEN) && isset($_POST['cmx_artikel_marke_payload'])) {
		$marke_id = (int) $in('cmx_artikel_marke', 0);
		\wp_set_post_terms($post_id, $marke_id ? [$marke_id] : [], TAX_ARTIKEL_MARKEN, false);
	}
}, 10, 2);
