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

	// Kalkulation mit "Lock", wenn VK manuell geändert wurde – und Mehrwert entsprechend anpassen
	echo '<script>
	document.addEventListener("DOMContentLoaded", function(){
		const ek  = document.getElementById("cmx_artikel_ek");
		const aw  = document.getElementById("cmx_artikel_aufwand");
		const mw  = document.getElementById("cmx_artikel_mehrwert");
		const vk  = document.getElementById("cmx_artikel_vk");
		const mg  = document.getElementById("cmx_artikel_marge");

		let vkLocked = false; // true, sobald VK manuell verändert wurde

		function num(v){ const n = parseFloat((v ?? "0").toString().replace(",", ".")); return isFinite(n) ? n : 0; }
		function clamp2(x){ return (x < 0 ? 0 : x).toFixed(2); }

		function recalcVK(){
			if (vkLocked) { recalcMargin(); return; }
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

		// VK "Lock" + Mehrwert an VK anpassen: mw = max(0, VK - EK - Aufwand)
		["input","change","keydown","paste"].forEach(evt=>{
			vk?.addEventListener(evt, ()=>{
				vkLocked = true;
				if (mw) {
					const newMw = num(vk.value) - num(ek?.value) - num(aw?.value);
					mw.value = clamp2(newMw);
				}
				recalcMargin();
			}, {passive:true});
		});

		// Auto-Berechnung, solange VK nicht gelockt ist
		["input","change"].forEach(evt=>{
			ek?.addEventListener(evt, recalcVK, {passive:true});
			aw?.addEventListener(evt, recalcVK, {passive:true});
			mw?.addEventListener(evt, recalcVK, {passive:true});
		});

		// Alle numerischen Felder automatisch selektieren bei Fokus
		[ek, aw, mw, vk, mg].forEach(enableAutoSelect);

		recalcVK(); // Initial
	});
	</script>';
}

// Save-Handler: VK NICHT neu berechnen, wenn manuell vergeben
// UND: Wenn VK manuell angepasst wurde, MEHRWERT = max(0, VK − EK − Aufwand) setzen.
\add_action('save_post_artikel', function (int $post_id, \WP_Post $post) {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) return;
	if ($post->post_type !== 'artikel') return;
	if (!\current_user_can('edit_post', $post_id)) return;
	if (!isset($_POST['cmx_artikel_nonce']) || !\wp_verify_nonce($_POST['cmx_artikel_nonce'], 'cmx_artikel_save')) return;

	$in        = static fn($k, $d = '') => $_POST[$k] ?? $d;
	$norm      = static fn($v) => (float) \str_replace(',', '.', (string) $v);
	$is_valid  = static fn($v) => \is_finite($v) && $v >= 0;

	$ek        = $norm($in('cmx_artikel_ek', ''));
	$aufwand   = $norm($in('cmx_artikel_aufwand', ''));
	$mehrwertP = $norm($in('cmx_artikel_mehrwert', ''));

	$vk_post_s = $in('cmx_artikel_vk', '');
	$vk_post   = ($vk_post_s === '') ? null : \round($norm($vk_post_s), 2);

	$inputs_valid = $is_valid($ek) && $is_valid($aufwand) && $is_valid($mehrwertP);
	$vk_auto      = $inputs_valid ? \round($ek + $aufwand + $mehrwertP, 2) : null;

	$lock_key     = '_cmx_artikel_vk_lock'; // 1 = manuell, 0/absent = auto
	$locked       = (int) \get_post_meta($post_id, $lock_key, true) === 1;
	$epsilon      = 0.005;

	$should_update_vk   = false;
	$override_mehrwert  = false;
	$vk                 = null;
	$mehrwert_to_save   = $mehrwertP; // default: was vom Formular kam
	$lock               = $locked ? 1 : 0;

	// 1) Manuelle VK-Eingabe schlägt Auto-Berechnung
	if ($vk_post !== null && ($vk_auto === null || \abs($vk_post - $vk_auto) > $epsilon)) {
		$vk   = $vk_post;
		$lock = 1;
		$should_update_vk  = true;
		// Mehrwert entsprechend aus VK ableiten
		$calcMw = $vk - $ek - $aufwand;
		$mehrwert_to_save = $is_valid($calcMw) ? \round($calcMw, 2) : 0.00;
		$override_mehrwert = true;

	// 2) Bereits gelockt -> VK so lassen (nicht überschreiben). Wenn User VK postet, trotzdem übernehmen.
	} elseif ($locked) {
		if ($vk_post !== null) {
			$vk   = $vk_post;
			$should_update_vk  = true;
			$calcMw = $vk - $ek - $aufwand;
			$mehrwert_to_save = $is_valid($calcMw) ? \round($calcMw, 2) : 0.00;
			$override_mehrwert = true;
		} else {
			$vk = \get_post_meta($post_id, CMX_ARTIKEL_META_VK, true);
			$vk = ($vk === '' || $vk === null) ? null : (float) $vk;
			$should_update_vk = false;
		}

	// 3) Auto-Berechnung NICHT korrekt -> VK & Mehrwert so lassen
	} elseif ($vk_auto === null || !\is_finite($vk_auto) || $vk_auto < 0) {
		$vk = \get_post_meta($post_id, CMX_ARTIKEL_META_VK, true);
		$vk = ($vk === '' || $vk === null) ? null : (float) $vk;
		$should_update_vk = false;

	// 4) Auto-Berechnung korrekt -> übernehmen
	} else {
		$vk   = $vk_auto;
		$lock = 0;
		$should_update_vk  = true;
		// Mehrwert bleibt wie gepostet (da aus Auto-Berechnung entstanden)
	}

	// EK / Aufwand immer speichern (ungültige als 0.00)
	\update_post_meta($post_id, CMX_ARTIKEL_META_EK,       $is_valid($ek) ? \round($ek, 2) : 0.00);
	\update_post_meta($post_id, CMX_ARTIKEL_META_AUFWAND,  $is_valid($aufwand) ? \round($aufwand, 2) : 0.00);

	// Mehrwert speichern: wenn VK manuell → überschreiben, sonst wie gepostet (mit 0-Absicherung)
	if (!$override_mehrwert) {
		$mehrwert_to_save = $is_valid($mehrwertP) ? \round($mehrwertP, 2) : 0.00;
	}
	\update_post_meta($post_id, CMX_ARTIKEL_META_MEHRWERT, $mehrwert_to_save);

	// VK nur dann aktualisieren, wenn wir ihn wirklich setzen wollen
	if ($should_update_vk && $vk !== null && $is_valid($vk)) {
		\update_post_meta($post_id, CMX_ARTIKEL_META_VK, \round($vk, 2));
	}

	// Marge nur berechnen, wenn ein gültiger VK existiert
	$vk_for_margin = $vk;
	if ($vk_for_margin === null) {
		$vk_for_margin = \get_post_meta($post_id, CMX_ARTIKEL_META_VK, true);
		$vk_for_margin = ($vk_for_margin === '' || $vk_for_margin === null) ? null : (float) $vk_for_margin;
	}
	if ($vk_for_margin !== null && $is_valid($vk_for_margin) && $is_valid($ek)) {
		\update_post_meta($post_id, CMX_ARTIKEL_META_MARGE, \round($vk_for_margin - (float) $ek, 2));
	}

	// Lock-Status persistieren
	\update_post_meta($post_id, $lock_key, (int) $lock);

	\update_post_meta($post_id, CMX_ARTIKEL_META_VERKAUFBAR, isset($_POST['cmx_artikel_verkaufbar']) ? 1 : 0);

	if (\taxonomy_exists(TAX_ARTIKEL_EINHEITEN)) {
		$einheit_id = (int) $in('cmx_artikel_einheit', 0);
		\wp_set_post_terms($post_id, $einheit_id ? [$einheit_id] : [], TAX_ARTIKEL_EINHEITEN, false);
	}
}, 10, 2);

\add_action('admin_head', function() {
	$screen = \get_current_screen();
	if ($screen && $screen->post_type === 'artikel') {
		echo '<style>label:has(input[name="cmx_artikel_verkaufbar"]) { position:relative; top:-5px; }</style>';
	}
});
