<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/* ===== Neue Meta-Felder: Aufwand ===== */
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_AUFWAND')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_AUFWAND', '_cmx_artikel_aufwand');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_MEHRWERT')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_MEHRWERT', '_cmx_artikel_mehrwert');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_KATALOG')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_KATALOG', '_cmx_artikel_katalog');
}

\add_action('add_meta_boxes', function () {
	\add_meta_box('cmx_artikel_waehrung_preise', 'Konditionen', __NAMESPACE__ . '\\cmx_artikel_waehrung_preise_box_html', 'artikel', 'normal', 'default');
});

function cmx_artikel_waehrung_preise_box_html(\WP_Post $post): void {
	if (!isset($_POST['cmx_artikel_nonce'])) {
		\wp_nonce_field('cmx_artikel_save', 'cmx_artikel_nonce');
	}
	echo '<input type="hidden" name="cmx_artikel_konditionen_payload" value="1">';

	$sku         = cmx_meta_get($post->ID, CMX_ARTIKEL_META_SKU, '');
	$waehrung    = cmx_meta_get($post->ID, CMX_ARTIKEL_META_WAEHRUNGEN, 'CHF');
	$ek          = cmx_meta_get($post->ID, CMX_ARTIKEL_META_EK, '');
	$aufwand     = cmx_meta_get($post->ID, CMX_ARTIKEL_META_AUFWAND, '');
	$vk          = cmx_meta_get($post->ID, CMX_ARTIKEL_META_VK, '');
	$marge       = cmx_meta_get($post->ID, CMX_ARTIKEL_META_MARGE, '');
	$not_verkaufbar = (int) cmx_meta_get($post->ID, CMX_ARTIKEL_META_VERKAUFBAR, 0) === 1;
	$verkaufbar     = !$not_verkaufbar;
	$katalog_raw    = (string) \get_post_meta($post->ID, CMX_ARTIKEL_META_KATALOG, true);
	$katalog_exists = \metadata_exists('post', $post->ID, CMX_ARTIKEL_META_KATALOG);
	$katalog        = !$katalog_exists || $katalog_raw === '' || (int) $katalog_raw === 1;
	$ek_display       = ($ek === '' || $ek === null) ? '' : cmx_format_swiss_number($ek, 2);
	$aufwand_display  = ($aufwand === '' || $aufwand === null) ? '' : cmx_format_swiss_number($aufwand, 2);
	$vk_display       = ($vk === '' || $vk === null) ? '' : cmx_format_swiss_number($vk, 2);
	$vk_hidden_value  = ($vk === '' || $vk === null) ? '' : (string) \round(cmx_parse_number($vk), 2);
	$marge_display    = ($marge === '' || $marge === null) ? '' : cmx_format_swiss_number($marge, 2);

	$sel_einheit = cmx_get_single_term_id($post->ID, TAX_ARTIKEL_EINHEITEN);
	$einheiten   = cmx_get_terms_safe(TAX_ARTIKEL_EINHEITEN);
	$einheiten_url = \taxonomy_exists(TAX_ARTIKEL_EINHEITEN)
		? \admin_url('edit-tags.php?taxonomy=' . \rawurlencode((string) TAX_ARTIKEL_EINHEITEN) . '&post_type=artikel')
		: '';

	echo '<style>
		.cmx-price-row{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap}
		.cmx-price-row .cmx-f{display:flex;flex-direction:column;min-width:140px;flex:1 1 180px;max-width:320px}
		.cmx-price-row .cmx-f--xs{min-width:110px;max-width:160px;flex:1 1 140px}
		.cmx-price-row .cmx-f--sm{min-width:160px;max-width:200px;flex:1 1 180px}
		.cmx-price-row .cmx-f--md{min-width:220px;max-width:320px;flex:1 1 220px}
		.cmx-price-row .cmx-f--lg{min-width:260px;max-width:420px;flex:1 1 260px}
		.cmx-price-row .cmx-f--half{min-width:130px;max-width:180px;flex:1 1 140px}
		.cmx-price-row .cmx-f label{font-weight:600;margin-bottom:4px}
		.cmx-price-row .cmx-f input[type="number"],
		.cmx-price-row .cmx-f input[type="text"],
		.cmx-price-row .cmx-f select{width:100%}
		.cmx-price-row .cmx-check{display:flex;align-items:center;gap:14px;margin-left:8px;white-space:nowrap;flex:1 1 300px}
		@media (max-width: 1200px){
			.cmx-price-row{gap:10px}
			.cmx-price-row .cmx-f{max-width:100%}
			.cmx-price-row .cmx-check{margin-left:0;margin-top:6px}
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
		<label for="cmx_artikel_einheit">';
	if ($einheiten_url !== '') {
		echo '<a href="' . \esc_url($einheiten_url) . '" target="_blank" rel="noopener noreferrer" style="text-decoration:none;" title="Einheiten verwalten">Einheit</a>';
	} else {
		echo 'Einheit';
	}
	echo '</label>
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
		<input type="text" inputmode="decimal" id="cmx_artikel_ek" name="cmx_artikel_ek" value="' . esc_attr($ek_display) . '">
	</div>';

	// Aufwand
	echo '<div class="cmx-f cmx-f--xs">
		<label for="cmx_artikel_aufwand">Aufwand</label>
		<input type="text" inputmode="decimal" id="cmx_artikel_aufwand" name="cmx_artikel_aufwand" value="' . esc_attr($aufwand_display) . '">
	</div>';

	// Verkaufspreis
	echo '<div class="cmx-f cmx-f--xs">
		<label for="cmx_artikel_vk">Verkaufspreis</label>
		<input type="text" inputmode="decimal" id="cmx_artikel_vk" name="cmx_artikel_vk" value="' . esc_attr($vk_display) . '">
		<input type="hidden" id="cmx_artikel_vk_prev" name="cmx_artikel_vk_prev" value="' . esc_attr($vk_hidden_value) . '">
	</div>';

	// Marge
	echo '<div class="cmx-f cmx-f--xs">
		<label for="cmx_artikel_marge">Marge (VK − EK)</label>
		<input type="text" inputmode="decimal" id="cmx_artikel_marge" name="cmx_artikel_marge" value="' . esc_attr($marge_display) . '" readonly>
	</div>';

	echo '<div class="cmx-check">
		<input type="hidden" name="cmx_artikel_verkaufbar_present" value="1">
		<input type="hidden" name="cmx_artikel_katalog_present" value="1">
		<label><input type="checkbox" name="cmx_artikel_verkaufbar" value="1" ' . checked($verkaufbar, true, false) . '> verkaufbar</label>
		<label><input type="checkbox" name="cmx_artikel_katalog" value="1" ' . checked($katalog, true, false) . '> Katalog</label>
	</div>';

	echo '</div>';

	// Kalkulation:
	// A/F: EK + Aufwand => VK
	// E:   Wenn VK manuell geändert wird, bleibt EK und Aufwand wird auf (VK - EK) gesetzt.
	echo '<script>
	document.addEventListener("DOMContentLoaded", function(){
		const ek  = document.getElementById("cmx_artikel_ek");
		const aw  = document.getElementById("cmx_artikel_aufwand");
		const vk  = document.getElementById("cmx_artikel_vk");
		const mg  = document.getElementById("cmx_artikel_marge");
		const vkPrev = document.getElementById("cmx_artikel_vk_prev");

		function num(v){
			let s = (v ?? "0").toString().trim();
			if (s === "") return 0;
			s = s.replace(/[\s\u00A0\u202F]+/g, "").replace(/[\u0027’‘`´′]/g, "");
			const hasComma = s.indexOf(",") > -1;
			const hasDot = s.indexOf(".") > -1;
			if (hasComma && hasDot) {
				if (s.lastIndexOf(",") > s.lastIndexOf(".")) {
					s = s.replace(/\./g, "").replace(/,/g, ".");
				} else {
					s = s.replace(/,/g, "");
				}
			} else {
				s = s.replace(/,/g, ".");
			}
			const n = parseFloat(s);
			return isFinite(n) ? n : 0;
		}
		function formatCH(v){
			const parts = (Number(v) || 0).toFixed(2).split(".");
			let left = parts[0];
			let out = "";
			while (left.length > 3) {
				out = "\'" + left.slice(-3) + out;
				left = left.slice(0, -3);
			}
			return left + out + "." + parts[1];
		}

		function recalcVK(){
			const v = num(ek?.value) + num(aw?.value);
			if (vk) vk.value = formatCH(v);
			recalcMargin();
		}

		function syncAufwandFromVK(){
			if (!aw || !vk) { recalcMargin(); return; }
			const newAw = num(vk.value) - num(ek?.value);
			aw.value = formatCH(newAw);
			recalcMargin();
		}

		function recalcMargin(){
			if (!mg) return;
			const margin = num(vk?.value) - num(ek?.value);
			mg.value = formatCH(margin);
		}

		function ensureVkBeforeSubmit(){
			if (!vk) return;
			const rawVk = (vk.value ?? "").toString().trim();
			if (rawVk !== "") return;
			const hasEk = (ek?.value ?? "").toString().trim() !== "";
			const hasAw = (aw?.value ?? "").toString().trim() !== "";
			if (!hasEk && !hasAw) return;
			recalcVK();
		}

		function syncVkPrev(){
			if (!vkPrev || !vk) return;
			const raw = (vk.value ?? "").toString().trim();
			if (raw === "") return;
			vkPrev.value = (Math.round(num(raw) * 100) / 100).toFixed(2);
		}

		function enableAutoSelect(el) {
			if (!el) return;
			el.addEventListener("focus", function(){ this.select(); });
			el.addEventListener("click", function(){ this.select(); });
		}

		// VK manuell: Aufwand wird entsprechend angepasst (EK bleibt).
		["input","change","keydown","paste"].forEach(evt=>{
			vk?.addEventListener(evt, syncAufwandFromVK, {passive:true});
		});

		// EK/Aufwand geändert: VK neu berechnen.
		["input","change"].forEach(evt=>{
			ek?.addEventListener(evt, recalcVK, {passive:true});
			aw?.addEventListener(evt, recalcVK, {passive:true});
		});

		// Alle numerischen Felder automatisch selektieren bei Fokus
		[ek, aw, vk, mg].forEach(enableAutoSelect);
		[ek, aw, vk, mg].forEach(el => {
			if (!el) return;
			if (el.value !== "") el.value = formatCH(num(el.value));
		});
		[ek, aw, vk].forEach(el => {
			if (!el) return;
			el.addEventListener("blur", function(){
				const raw = (this.value ?? "").toString().trim();
				if (raw === "") return;
					this.value = formatCH(num(raw));
					if (this === vk) {
						syncAufwandFromVK();
						syncVkPrev();
						return;
					}
				recalcVK();
				syncVkPrev();
			});
		});

		const postForm = document.getElementById("post");
		postForm?.addEventListener("submit", function(){
			ensureVkBeforeSubmit();
			syncVkPrev();
		});

		// Initialzustand:
		// Wenn bereits manuell ein VK existiert, der nicht EK+Aufwand entspricht,
		// Aufwand daran angleichen; sonst VK aus EK+Aufwand berechnen.
		const hasVk = vk && ((vk.value ?? "").toString().trim() !== "");
		const delta = hasVk ? Math.abs(num(vk.value) - (num(ek?.value) + num(aw?.value))) : 0;
		if (hasVk && delta > 0.005) {
			syncAufwandFromVK();
		} else {
			recalcVK();
		}
		syncVkPrev();
	});
	</script>';
}

// Save-Handler:
// A/F: EK+Aufwand berechnet VK.
// E:   Wenn VK manuell vom Auto-Wert abweicht, wird Aufwand auf (VK - EK) angepasst.
// ZUSÄTZLICH: Artikel-Nr. (SKU) und Währung speichern.
\add_action('save_post_artikel', function (int $post_id, \WP_Post $post) {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) return;
	if ($post->post_type !== 'artikel') return;
	if (!\current_user_can('edit_post', $post_id)) return;
	if (!isset($_POST['cmx_artikel_nonce']) || !\wp_verify_nonce($_POST['cmx_artikel_nonce'], 'cmx_artikel_save')) return;
	if (!isset($_POST['cmx_artikel_konditionen_payload'])) return;

	$in        = static fn($k, $d = '') => $_POST[$k] ?? $d;
	$norm      = static fn($v) => cmx_parse_number((string) $v);
	$is_finite = static fn($v) => \is_finite($v);
	$has       = static fn($k) => \array_key_exists($k, $_POST);

	// --- SKU & Währung speichern ---
	if ($has('cmx_artikel_sku')) {
		$sku = \sanitize_text_field($in('cmx_artikel_sku', ''));
		\update_post_meta($post_id, CMX_ARTIKEL_META_SKU, $sku);
	}

	if ($has('cmx_artikel_waehrung')) {
		$waehrung = \strtoupper(\sanitize_text_field($in('cmx_artikel_waehrung', 'CHF')));
		$allowed  = ['CHF', 'EUR', 'USD'];
		if (!\in_array($waehrung, $allowed, true)) {
			$waehrung = 'CHF';
		}
		\update_post_meta($post_id, CMX_ARTIKEL_META_WAEHRUNGEN, $waehrung);
	}
	// --- Ende SKU & Währung ---

	$has_ek      = $has('cmx_artikel_ek');
	$has_aufwand = $has('cmx_artikel_aufwand');
	$has_vk      = $has('cmx_artikel_vk');
	if ($has_ek || $has_aufwand || $has_vk) {
		$current_ek      = \round($norm((string) \get_post_meta($post_id, CMX_ARTIKEL_META_EK, true)), 2);
		$current_aufwand = \round($norm((string) \get_post_meta($post_id, CMX_ARTIKEL_META_AUFWAND, true)), 2);
		$current_vk      = \round($norm((string) \get_post_meta($post_id, CMX_ARTIKEL_META_VK, true)), 2);
		$posted_prev_vk  = $has('cmx_artikel_vk_prev')
			? \round($norm((string) $in('cmx_artikel_vk_prev', '')), 2)
			: $current_vk;
		if (!$is_finite($posted_prev_vk) || $posted_prev_vk < 0) {
			$posted_prev_vk = $current_vk;
		}

		$ek = $has_ek ? \round($norm($in('cmx_artikel_ek', '')), 2) : $current_ek;
		if (!$is_finite($ek) || $ek < 0) $ek = 0.00;

		$aufwand = $has_aufwand ? \round($norm($in('cmx_artikel_aufwand', '')), 2) : $current_aufwand;
		if (!$is_finite($aufwand)) $aufwand = 0.00;

		$vk_post = null;
		if ($has_vk) {
			$vk_post_s = (string) $in('cmx_artikel_vk', '');
			$vk_post_raw = \html_entity_decode(\trim($vk_post_s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
			if ($vk_post_raw === '') {
				$vk_post = $posted_prev_vk;
			} else {
				$vk_post = \round($norm($vk_post_raw), 2);
				$looks_numeric = \preg_match('/\d/u', $vk_post_raw) === 1;
				if ((!$is_finite($vk_post) || $vk_post < 0) || ($vk_post === 0.0 && $looks_numeric && $posted_prev_vk > 0)) {
					$vk_post = $posted_prev_vk;
				}
			}
		}

		$vk_auto = \round($ek + $aufwand, 2);
		$epsilon = 0.005;
		$manual_vk = ($vk_post !== null && \abs($vk_post - $vk_auto) > $epsilon);

		if ($manual_vk && $vk_post !== null) {
			$vk = $vk_post;
			$aufwand = \round($vk - $ek, 2);
			if (!$is_finite($aufwand)) $aufwand = 0.00;
		} else {
			$vk = \round($ek + $aufwand, 2);
		}
		if ($has_vk && $vk === 0.0 && $posted_prev_vk > 0) {
			$vk = $posted_prev_vk;
			$aufwand = \round($vk - $ek, 2);
			if (!$is_finite($aufwand)) $aufwand = 0.00;
		}
		if (!$is_finite($vk) || $vk < 0) $vk = 0.00;

		\update_post_meta($post_id, CMX_ARTIKEL_META_EK, $ek);
		\update_post_meta($post_id, CMX_ARTIKEL_META_AUFWAND, $aufwand);
		\update_post_meta($post_id, CMX_ARTIKEL_META_VK, $vk);
		\update_post_meta($post_id, CMX_ARTIKEL_META_MARGE, \round($vk - $ek, 2));
		\delete_post_meta($post_id, CMX_ARTIKEL_META_MEHRWERT);
		\delete_post_meta($post_id, '_cmx_artikel_vk_lock');
	}

	// Kompatibilität zur bestehenden Datenlogik:
	// 1 = NICHT verkaufbar, 0 = verkaufbar.
	if ($has('cmx_artikel_verkaufbar_present')) {
		\update_post_meta($post_id, CMX_ARTIKEL_META_VERKAUFBAR, isset($_POST['cmx_artikel_verkaufbar']) ? 0 : 1);
	}
	if ($has('cmx_artikel_katalog_present')) {
		\update_post_meta($post_id, CMX_ARTIKEL_META_KATALOG, isset($_POST['cmx_artikel_katalog']) ? 1 : 0);
	}

	if (\taxonomy_exists(TAX_ARTIKEL_EINHEITEN) && $has('cmx_artikel_einheit')) {
		$einheit_id = (int) $in('cmx_artikel_einheit', 0);
		\wp_set_post_terms($post_id, $einheit_id ? [$einheit_id] : [], TAX_ARTIKEL_EINHEITEN, false);
	}
}, 20, 2);

\add_action('admin_head', function() {
	$screen = \get_current_screen();
	if ($screen && $screen->post_type === 'artikel') {
		echo '<style>label:has(input[name="cmx_artikel_verkaufbar"]), label:has(input[name="cmx_artikel_katalog"]) { position:relative; top:-5px; }</style>';
	}
});
