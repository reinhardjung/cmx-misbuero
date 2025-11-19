<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/* ------------------------------------------------------------
 * 1) Spalte registrieren
 * ------------------------------------------------------------ */
add_filter('manage_edit-product_columns', function($cols) {
	if (isset($cols['sku'])) {
		$new = [];
		foreach ($cols as $k => $v) {
			$new[$k] = $v;
			if ('sku' === $k) $new['cmx_var_ids'] = __('Var.-IDs', 'cmx');
		}
		return $new;
	}
	$cols['cmx_var_ids'] = __('Var.-IDs', 'cmx');
	return $cols;
}, 30);

/* ------------------------------------------------------------
 * 2) Inhalt: Variantenliste mit Copy-Buttons + klickbare ID
 * ------------------------------------------------------------ */
add_action('manage_product_posts_custom_column', function($column, $post_id) {
	if ($column !== 'cmx_var_ids') return;

	$product = wc_get_product($post_id);
	if (!$product || !$product->is_type('variable')) { echo '—'; return; }

	foreach ($product->get_children() as $vid) {
		$v = wc_get_product($vid);
		if (!$v instanceof WC_Product_Variation) continue;

		$parent_id = $v->get_parent_id();
		$attrs     = $v->get_attributes();

		$base_query = ['add-to-cart' => $parent_id, 'variation_id'=> $vid];
		foreach ($attrs as $tax => $term_slug) {
			if ($term_slug) $base_query['attribute_' . sanitize_title($tax)] = $term_slug;
		}

		$add_url      = add_query_arg($base_query, home_url('/'));
		$cart_url     = add_query_arg($base_query, wc_get_cart_url());
		$checkout_url = add_query_arg($base_query, wc_get_checkout_url());

		$var_name = function_exists('wc_get_formatted_variation')
			? wc_get_formatted_variation($v, true, false, true)
			: implode(', ', array_map(fn($k,$val)=> sanitize_title($k).': '.$val, array_keys($attrs), $attrs));

		$sku  = $v->get_sku() ?: __('ohne SKU', 'cmx');

		// Link: Elternprodukt + Hash + Parameter; zusätzlich localStorage-Fallback
		$link = esc_url(
			admin_url('post.php?post=' . $parent_id . '&action=edit#variable_product_options&cmx_open_variation=' . $vid)
		);

		printf(
			'<div class="cmx-var-row">
				<button type="button" class="button button-small cmx-copy" data-atc="%1$s" title="Nur Add-to-Cart">📋</button>
				<button type="button" class="button button-small cmx-copy" data-atc="%2$s" title="Add-to-Cart + Warenkorb">🛒</button>
				<button type="button" class="button button-small cmx-copy" data-atc="%3$s" title="Add-to-Cart + Kasse">💳</button>
				<span class="cmx-var-name">%4$s</span>
				<span class="cmx-var-id"><a class="cmx-open-var" data-var="%5$d" href="%6$s" target="_blank" title="Variante im Elternprodukt aufklappen">#%5$d</a> %7$s</span>
			</div>',
			esc_attr($add_url),
			esc_attr($cart_url),
			esc_attr($checkout_url),
			esc_html($var_name),
			intval($vid),
			$link,
			esc_html($sku)
		);
	}
}, 10, 2);

/* ------------------------------------------------------------
 * 3) Styles & JS (Clipboard + localStorage Fallback)
 * ------------------------------------------------------------ */
add_action('admin_head', function () {
	$screen = get_current_screen();
	if (!$screen || $screen->id !== 'edit-product') return;
	echo '<style>
		.column-cmx_var_ids{width:32%}
		.cmx-var-row{display:flex;align-items:center;gap:6px;margin:4px 0;flex-wrap:nowrap}
		.cmx-copy{cursor:pointer;line-height:1.2;padding:0 6px;min-width:auto}
		.cmx-copy.copied{background:#46b450;border-color:#46b450;color:#fff}
		.cmx-var-name{display:inline-block;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
		.cmx-var-id{color:#555;margin-left:6px;font-size:11px}
		.cmx-var-id a{text-decoration:none;color:#0073aa}
		.cmx-var-id a:hover{text-decoration:underline}
	</style>';
});

add_action('admin_print_footer_scripts-post.php', function () {
	$screen = get_current_screen();
	if (!$screen || $screen->post_type !== 'product') return;

	echo "<script>
	(function(){
		var targetId = new URLSearchParams(window.location.search).get('cmx_open_variation');
		if(!targetId) return;

		// 1) Einmalig Variationen-Tab aktivieren
		var tab = document.querySelector('a[href=\"#variable_product_options\"]');
		if(tab) { try{ tab.click(); }catch(e){} }

		// 2) Nach kurzer Zeit genau EINEN Ablauf versuchen (keine Loops)
		setTimeout(function(){
			// a) Einmalig 'Alle ausklappen', falls vorhanden
			var expand = document.querySelector('#variable_product_options .expand_all, .woocommerce_variations .expand_all');
			if(expand){ try{ expand.click(); }catch(e){} }

			// b) Ziel-Panel über gängige Selektoren finden
			var panel = document.querySelector('.woocommerce_variation[data-variation_id=\"'+targetId+'\"]');
			if(!panel){
				var inp = document.querySelector('input[name=\"variable_post_id[]\"][value=\"'+targetId+'\"]');
				if(inp) panel = inp.closest('.woocommerce_variation');
			}

			// c) Falls gefunden: ggf. öffnen und fokussieren – EIN Versuch
			if(panel){
				if(panel.classList.contains('closed')){
					var tgl = panel.querySelector('.handlediv, .actions .toggle-indicator, h3, summary');
					if(tgl) { try{ tgl.click(); }catch(e){} }
				}
				try{ panel.scrollIntoView({behavior:'smooth', block:'center'}); }catch(e){}
				panel.style.outline = '2px solid #2271b1';
			}
			// d) Ende – kein weiteres Retry, damit nichts hängt
		}, 350);
	})();
	</script>";
});

/* ------------------------------------------------------------
 * 4) Variation Auto-Open (spezifische Selektoren für Woo 10.3.0 + Fallbacks)
 * ------------------------------------------------------------ */
add_action('admin_print_footer_scripts-post.php', function () {
	$screen = get_current_screen();
	if (!$screen || $screen->post_type !== 'product') return;

	echo "<script>
	(function(){
		var qs = new URLSearchParams(window.location.search);
		var targetId = qs.get('cmx_open_variation') || localStorage.getItem('cmx_open_variation') || '';
		if(!targetId) return;
		// Aufräumen, damit bei Reloads nicht erneut getriggert wird
		try{ localStorage.removeItem('cmx_open_variation'); }catch(_){}

		var MAX_MS = 30000;     // bis 30s wiederholen
		var start  = Date.now();
		var opened = false;

		// Optik
		var css = document.createElement('style');
		css.textContent = '.cmx-highlight{outline:2px solid #2271b1;box-shadow:0 0 0 2px #2271b1 inset}.cmx-open-fail{background:#dc3232;color:#fff;padding:8px 12px;margin:10px 0;border-radius:3px}';
		document.head.appendChild(css);

		/* ========= Schritt A: korrekten Bereich/Tabs aktivieren ========= */
		function activateVariationsUI(){
			// Klassischer Editor: Tab-Anchor
			var a1 = document.querySelector('a[href=\"#variable_product_options\"]');
			if(a1) a1.click();

			// Produktdaten sichtbar erzwingen
			var pd = document.getElementById('woocommerce-product-data');
			if(pd && pd.style && pd.style.display==='none'){ pd.style.display='block'; }

			// Woo 10.x (neuer Editor): Buttons/Links, die Variationen zeigen
			var labels = ['variationen','variations','attributes & variations','variations & attributes'];
			var btns = document.querySelectorAll('[data-tab-id=\"variations\"], [aria-controls=\"variations\"], button, a, [role=\"tab\"]');
			for(var i=0;i<btns.length;i++){
				var t=(btns[i].innerText||btns[i].getAttribute('aria-label')||'').trim().toLowerCase();
				if(labels.some(function(s){return t===s || t.indexOf(s)>=0;})){
					try{ btns[i].click(); }catch(e){}
				}
			}
		}

		/* ========= Schritt B: „Alle ausklappen“ wiederholt versuchen ========= */
		function expandAll(){
			var selectors = [
				'#variable_product_options .expand_all',
				'.woocommerce_variations .expand_all',
				'[data-testid=\"expand-all-variations-button\"]',
				'button.expand-all-variations'
			];
			for(var s of selectors){
				var b=document.querySelector(s);
				if(b){ try{ b.click(); }catch(e){} }
			}
		}

		/* ========= Schritt C: Variation finden ========= */
		function findPanel(){
			// 1) Klassisch: data-variation_id
			var p = document.querySelector('.woocommerce_variation[data-variation_id=\"'+targetId+'\"]');
			if(p) return p;

			// 2) Klassisch: hidden input variable_post_id[]
			var inp = document.querySelector('input[name=\"variable_post_id[]\"][value=\"'+targetId+'\"]');
			if(inp){
				var wrap = inp.closest('.woocommerce_variation'); if(wrap) return wrap;
			}

			// 3) Woo 10.3 neuer Editor – häufige Container
			// Suche nach data-attributes oder generischen Rows
			var alt = document.querySelector('[data-variation-id=\"'+targetId+'\"]');
			if(alt) return alt;

			// 4) Generisches Text-Matching: #ID oder ID in Zeilen
			var candidates = document.querySelectorAll('[role=\"row\"], li, article, section, .components-card, .woocommerce-card, .wc-product-variations__row, .wc-components-card');
			for(var i=0;i<candidates.length;i++){
				var el = candidates[i];
				var txt=(el.innerText||'').trim();
				if(!txt) continue;
				if(txt.indexOf('#'+String(targetId))>=0 || txt===String(targetId) || txt.indexOf(String(targetId))>=0){
					return el;
				}
			}
			return null;
		}

		/* ========= Schritt D: Panel öffnen + markieren ========= */
		function openPanel(panel){
			expandAll();

			// Mögliche Toggles in klassischer und neuer UI
			var toggles = panel.querySelectorAll(
				'.handlediv, .actions .toggle-indicator, h3, summary, .woocommerce-card__title, [aria-expanded=\"false\"], button[aria-controls], [data-testid*=\"variation\"]'
			);

			// Falls geschlossen: ersten sinnvollen Toggle klicken
			if(panel.classList.contains('closed')){
				if(toggles.length){ try{ toggles[0].click(); }catch(e){} }
			} else {
				// Neuer Editor: häufig per aria-expanded=false
				for(var i=0;i<toggles.length;i++){
					var te=toggles[i];
					if(te.getAttribute && te.getAttribute('aria-expanded')==='false'){
						try{ te.click(); break; }catch(e){}
					}
				}
			}

			try{ panel.scrollIntoView({behavior:'smooth', block:'center'}); }catch(e){}
			panel.classList.add('cmx-highlight');
			opened = true;
		}

		/* ========= Orchestrierung ========= */
		function tick(){
			if(opened) return true;
			activateVariationsUI();
			expandAll();

			var panel = findPanel();
			if(panel){ openPanel(panel); return true; }
			return false;
		}

		// Direktversuch
		if(tick()) return;

		// Woo jQuery-Events
		if(window.jQuery){
			window.jQuery(document.body).on('woocommerce_variations_loaded woocommerce_variations_added', function(){
				setTimeout(tick, 80);
			});
		}

		// Polling + MutationObserver
		var iv = setInterval(function(){
			if(Date.now()-start > MAX_MS){
				clearInterval(iv); if(obs) obs.disconnect();
				if(!opened){
					var box=document.createElement('div');
					box.className='cmx-open-fail';
					box.textContent='Hinweis: Variante #'+targetId+' konnte in WooCommerce 10.3.0 nicht automatisch geöffnet werden. UI wurde vermutlich stark angepasst.';
					var holder=document.querySelector('#woocommerce-product-data')||document.querySelector('#post-body-content')||document.body;
					holder.appendChild(box);
				}
				return;
			}
			tick();
		}, 150);

		var obs = new MutationObserver(function(){ tick(); });
		obs.observe(document.body, {childList:true, subtree:true});
	})();
	</script>";
});
