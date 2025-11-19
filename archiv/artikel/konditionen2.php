<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') or die('Oxytocin!');

/* ===== Neue Meta-Felder: Aufwand & Mehrwert ===== */
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_AUFWAND')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_AUFWAND', '_cmx_artikel_aufwand');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_MEHRWERT')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_MEHRWERT', '_cmx_artikel_mehrwert');
}

\add_action('add_meta_boxes', function () {
	\add_meta_box('cmx_artikel_waehrung_preise', 'Konditionen', __NAMESPACE__ . '\\cmx_artikel_waehrung_preise_box_html', 'artikel', 'normal', 'default');
});

function cmx_artikel_waehrung_preise_box_html(\WP_Post $post): void {
	if (!isset($_POST['cmx_artikel_nonce'])) {
		\wp_nonce_field('cmx_artikel_save', 'cmx_artikel_nonce');
	}

	$sku         = cmx_meta_get($post->ID, CMX_ARTIKEL_META_SKU, '');
	$waehrung    = cmx_meta_get($post->ID, CMX_ARTIKEL_META_WAEHRUNGEN, 'CHF');
	$ek          = cmx_meta_get($post->ID, CMX_ARTIKEL_META_EK, '');
	$aufwand     = cmx_meta_get($post->ID, CMX_ARTIKEL_META_AUFWAND, '');
	$mehrwert    = cmx_meta_get($post->ID, CMX_ARTIKEL_META_MEHRWERT, '');
	$vk          = cmx_meta_get($post->ID, CMX_ARTIKEL_META_VK, '');
	$marge       = cmx_meta_get($post->ID, CMX_ARTIKEL_META_MARGE, '');
	$verkaufbar  = (bool) cmx_meta_get($post->ID, CMX_ARTIKEL_META_VERKAUFBAR, false);

	$sel_einheit = cmx_get_single_term_id($post->ID, TAX_ARTIKEL_EINHEITEN);
	$einheiten   = cmx_get_terms_safe(TAX_ARTIKEL_EINHEITEN);

	echo '<style>
		.cmx-price-row{display:flex;gap:12px;align-items:flex-end;flex-wrap:nowrap}
		.cmx-price-row .cmx-f{display:flex;flex-direction:column;min-width:140px}
		.cmx-price-row .cmx-f--xs{min-width:100px;max-width:140px}
		.cmx-price-row .cmx-f--sm{min-width:160px;max-width:200px}
		.cmx-price-row .cmx-f--md{min-width:220px;max-width:300px}
		.cmx-price-row .cmx-f--lg{min-width:260px;max-width:420px}
		.cmx-price-row .cmx-f--half{min-width:130px;max-width:150px}
		.cmx-price-row .cmx-f label{font-weight:600;margin-bottom:4px}
		.cmx-price-row .cmx-f input[type="number"],
		.cmx-price-row .cmx-f input[type="text"],
		.cmx-price-row .cmx-f select{width:100%}
		.cmx-price-row .cmx-check{display:flex;align-items:center;margin-left:8px;white-space:nowrap}
		@media (max-width: 1200px){
			.cmx-price-row{flex-wrap:wrap}
		}
	</style>';

	echo '<div class="cmx-price-row" role="group" aria-label="Währung & Preise">';

	echo '<div class="cmx-f cmx-f--md">
		<label for="cmx_artikel_sku">Artikel-Nr.</label>
		<input type="text" id="cmx_artikel_sku" name="cmx_artikel_sku" value="' . esc_attr($sku) . '" autocomplete="off">
	</div>';

	echo '<div class="cmx-f cmx-f--xs">
		<label for="cmx_artikel_waehrung">Währung</label>
		<select id="cmx_artikel_waehrung" name="cmx_artikel_waehrung">';
	foreach (['CHF' => 'Schweizer Franken', 'EUR' => 'Euro', 'USD' => 'US-Dollar'] as $val => $label) {
		echo '<option value="' . esc_attr($val) . '" ' . selected($waehrung, $val, false) . '>' . esc_html($label) . '</option>';
	}
	echo '	</select>
	</div>';

	// Einheit (halbe Breite)
	echo '<div class="cmx-f cmx-f--half">
		<label for="cmx_artikel_einheit">Einheit</label>
		<select id="cmx_artikel_einheit" name="cmx_artikel_einheit">
			<option value="0">— auswählen —</option>';
	foreach ($einheiten as $t) {
		$name = (string) ($t->name ?? '');
		echo '<option value="' . (int) $t->term_id . '" ' . selected($sel_einheit, $t->term_id, false) . '>' . esc_html($name) . '</option>';
	}
	echo '	</select>
	</div>';

	// Einkaufspreis
	echo '<div class="cmx-f cmx-f--xs">
		<label for="cmx_artikel_ek">Einkaufspreis</label>
		<input type="number" step="0.01" min="0" id="cmx_artikel_ek" name="cmx_artikel_ek" value="' . esc_attr($ek) . '">
	</div>';

	// Aufwand
	echo '<div class="cmx-f cmx-f--xs">
		<label for="cmx_artikel_aufwand">Aufwand</label>
		<input type="number" step="0.01" min="0" id="cmx_artikel_aufwand" name="cmx_artikel_aufwand" value="' . esc_attr($aufwand) . '">
	</div>';

	// Mehrwert
	echo '<div class="cmx-f cmx-f--xs">
		<label for="cmx_artikel_mehrwert">Mehrwert</label>
		<input type="number" step="0.01" min="0" id="cmx_artikel_mehrwert" name="cmx_artikel_mehrwert" value="' . esc_attr($mehrwert) . '">
	</div>';

	// Verkaufspreis
	echo '<div class="cmx-f cmx-f--xs">
		<label for="cmx_artikel_vk">Verkaufspreis</label>
		<input type="number" step="0.01" min="0" id="cmx_artikel_vk" name="cmx_artikel_vk" value="' . esc_attr($vk) . '">
	</div>';

	// Marge
	echo '<div class="cmx-f cmx-f--xs">
		<label for="cmx_artikel_marge">Marge (VK − EK)</label>
		<input type="number" step="0.01" id="cmx_artikel_marge" name="cmx_artikel_marge" value="' . esc_attr($marge) . '" readonly>
	</div>';

	echo '<div class="cmx-check">
		<label><input type="checkbox" name="cmx_artikel_verkaufbar" value="1" ' . checked($verkaufbar, true, false) . '> NICHT verkaufbar</label>
	</div>';

	echo '</div>';

	// Kalkulation & Auto-Select
	echo '<script>
	document.addEventListener("DOMContentLoaded", function(){
		const ek  = document.getElementById("cmx_artikel_ek");
		const aw  = document.getElementById("cmx_artikel_aufwand");
		const mw  = document.getElementById("cmx_artikel_mehrwert");
		const vk  = document.getElementById("cmx_artikel_vk");
		const mg  = document.getElementById("cmx_artikel_marge");

		function num(v){ const n = parseFloat((v ?? "0").toString().replace(",", ".")); return isFinite(n) ? n : 0; }

		function recalcVK(){
			const v = num(ek?.value) + num(aw?.value) + num(mw?.value);
			if (vk) vk.value = v.toFixed(2);
			recalcMargin();
		}

		function recalcMargin(){
			if (!mg) return;
			const margin = num(vk?.value) - num(ek?.value);
			mg.value = margin.toFixed(2);
		}

		function enableAutoSelect(el) {
			if (!el) return;
			el.addEventListener("focus", function(){ this.select(); });
			el.addEventListener("click", function(){ this.select(); });
		}

		// Events für Berechnung
		["input","change"].forEach(evt=>{
			ek?.addEventListener(evt, recalcVK, {passive:true});
			aw?.addEventListener(evt, recalcVK, {passive:true});
			mw?.addEventListener(evt, recalcVK, {passive:true});
			vk?.addEventListener(evt, recalcMargin, {passive:true});
		});

		// Alle numerischen Felder automatisch selektieren bei Fokus
		[ek, aw, mw, vk, mg].forEach(enableAutoSelect);

		recalcVK(); // Initialberechnung
	});
	</script>';
}

// Save-Handler (serverseitig IMMER korrekt berechnen)
\add_action('save_post_artikel', function (int $post_id, \WP_Post $post) {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) return;
	if ($post->post_type !== 'artikel') return;
	if (!\current_user_can('edit_post', $post_id)) return;
	if (!isset($_POST['cmx_artikel_nonce']) || !\wp_verify_nonce($_POST['cmx_artikel_nonce'], 'cmx_artikel_save')) return;

	$in = static fn($k, $d = '') => $_POST[$k] ?? $d;

	$ek        = (float) \str_replace(',', '.', (string) $in('cmx_artikel_ek', '0'));
	$aufwand   = (float) \str_replace(',', '.', (string) $in('cmx_artikel_aufwand', '0'));
	$mehrwert  = (float) \str_replace(',', '.', (string) $in('cmx_artikel_mehrwert', '0'));
	$vk        = $ek + $aufwand + $mehrwert;

	\update_post_meta($post_id, CMX_ARTIKEL_META_EK,        \round($ek, 2));
	\update_post_meta($post_id, CMX_ARTIKEL_META_AUFWAND,   \round($aufwand, 2));
	\update_post_meta($post_id, CMX_ARTIKEL_META_MEHRWERT,  \round($mehrwert, 2));
	\update_post_meta($post_id, CMX_ARTIKEL_META_VK,        \round($vk, 2));
	\update_post_meta($post_id, CMX_ARTIKEL_META_MARGE,     \round($vk - $ek, 2));

	\update_post_meta($post_id, CMX_ARTIKEL_META_VERKAUFBAR, isset($_POST['cmx_artikel_verkaufbar']) ? 1 : 0);

	if (\taxonomy_exists(TAX_ARTIKEL_EINHEITEN)) {
		$einheit_id = (int) $in('cmx_artikel_einheit', 0);
		\wp_set_post_terms($post_id, $einheit_id ? [$einheit_id] : [], TAX_ARTIKEL_EINHEITEN, false);
	}
}, 10, 2);


add_action('admin_head', function() {
	if (get_current_screen()->post_type === 'artikel') {
		echo '<style>label:has(input[name="cmx_artikel_verkaufbar"]) { position:relative; top:-5px; }</style>';
	}
});
