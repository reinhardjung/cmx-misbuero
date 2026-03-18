<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/* ===== Neue Meta-Felder: Aufwand ===== */
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_AUFWAND')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_AUFWAND', '_cmx_artikel_aufwand');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_MEHRWERT')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_MEHRWERT', '_cmx_artikel_mehrwert');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_SELBSTKOSTEN')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_SELBSTKOSTEN', '_cmx_artikel_selbstkosten');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_DECKUNGSBEITRAG')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_DECKUNGSBEITRAG', '_cmx_artikel_deckungsbeitrag');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_KATALOG')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_KATALOG', '_cmx_artikel_katalog');
}

\add_action('add_meta_boxes', function () {
	\add_meta_box('cmx_artikel_waehrung_preise', 'Konditionen', __NAMESPACE__ . '\\cmx_artikel_waehrung_preise_box_html', 'artikel', 'normal', 'default');
	\add_meta_box('cmx_artikel_waehrung_side', 'Währung', __NAMESPACE__ . '\\cmx_artikel_waehrung_side_box_html', 'artikel', 'side', 'default');
});

function cmx_artikel_render_save_nonce_once(): void {
	static $rendered = false;
	if ($rendered) return;
	\wp_nonce_field('cmx_artikel_save', 'cmx_artikel_nonce');
	$rendered = true;
}

function cmx_artikel_waehrung_optionen(): array {
	return [
		'CHF' => 'Schweizer Franken',
		'EUR' => 'Euro',
		'USD' => 'US-Dollar',
	];
}

function cmx_artikel_waehrung_preise_box_html(\WP_Post $post): void {
	cmx_artikel_render_save_nonce_once();
	echo '<input type="hidden" name="cmx_artikel_konditionen_payload" value="1">';

	$sku         = cmx_meta_get($post->ID, CMX_ARTIKEL_META_SKU, '');
	$ek          = cmx_meta_get($post->ID, CMX_ARTIKEL_META_EK, '');
	$aufwand     = cmx_meta_get($post->ID, CMX_ARTIKEL_META_AUFWAND, '');
	$vk          = cmx_meta_get($post->ID, CMX_ARTIKEL_META_VK, '');
	$selbstkosten = cmx_meta_get($post->ID, CMX_ARTIKEL_META_SELBSTKOSTEN, '');
	$deckungsbeitrag = cmx_meta_get($post->ID, CMX_ARTIKEL_META_DECKUNGSBEITRAG, '');
	$marge       = cmx_meta_get($post->ID, CMX_ARTIKEL_META_MARGE, '');
	$settings    = (array) \get_option(\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN, []);
	$deckungsbeitrag_percent = isset($settings['artikel_deckungsbeitrag']) ? (string) $settings['artikel_deckungsbeitrag'] : '';
	$not_verkaufbar = (int) cmx_meta_get($post->ID, CMX_ARTIKEL_META_VERKAUFBAR, 0) === 1;
	$verkaufbar     = !$not_verkaufbar;
	$katalog_raw    = (string) \get_post_meta($post->ID, CMX_ARTIKEL_META_KATALOG, true);
	$katalog_exists = \metadata_exists('post', $post->ID, CMX_ARTIKEL_META_KATALOG);
	$katalog        = !$katalog_exists || $katalog_raw === '' || (int) $katalog_raw === 1;
	$ek_display       = ($ek === '' || $ek === null) ? '' : cmx_format_swiss_number($ek, 2);
	$aufwand_display  = ($aufwand === '' || $aufwand === null) ? '' : cmx_format_swiss_number($aufwand, 2);
	$vk_display       = ($vk === '' || $vk === null) ? '' : cmx_format_swiss_number($vk, 2);
	$selbstkosten_display = ($selbstkosten === '' || $selbstkosten === null) ? '' : cmx_format_swiss_number($selbstkosten, 2);
	$deckungsbeitrag_display = ($deckungsbeitrag === '' || $deckungsbeitrag === null) ? '' : cmx_format_swiss_number($deckungsbeitrag, 2);
	$marge_display    = ($marge === '' || $marge === null) ? '' : cmx_format_swiss_number($marge, 2);

	$sel_einheit = cmx_get_single_term_id($post->ID, TAX_ARTIKEL_EINHEITEN);
	$einheiten   = cmx_get_terms_safe(TAX_ARTIKEL_EINHEITEN);
	$einheiten_url = \taxonomy_exists(TAX_ARTIKEL_EINHEITEN)
		? \admin_url('edit-tags.php?taxonomy=' . \rawurlencode((string) TAX_ARTIKEL_EINHEITEN) . '&post_type=artikel')
		: '';

	echo '<style>
		.cmx-price-row{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap}
		.cmx-price-row .cmx-f{display:flex;flex-direction:column;min-width:140px;flex:1 1 180px;max-width:320px}
		.cmx-price-row .cmx-f--xs{min-width:96px;max-width:132px;flex:1 1 112px}
		.cmx-price-row .cmx-f--sm{min-width:160px;max-width:200px;flex:1 1 180px}
		.cmx-price-row .cmx-f--md{min-width:220px;max-width:320px;flex:1 1 220px}
		.cmx-price-row .cmx-f--lg{min-width:260px;max-width:420px;flex:1 1 260px}
		.cmx-price-row .cmx-f--half{min-width:118px;max-width:150px;flex:1 1 124px}
		.cmx-price-row .cmx-f label{font-weight:600;margin-bottom:4px}
		.cmx-price-row .cmx-f input[type="number"],
		.cmx-price-row .cmx-f input[type="text"],
		.cmx-price-row .cmx-f select{width:100%}
		.cmx-price-row .cmx-f input[readonly]{background:#f6f7f7;color:#2c3338}
		.cmx-price-row .cmx-check{display:flex;align-items:center;gap:14px;white-space:nowrap;flex:0 0 100%;margin:2px 0 0}
		@media (max-width: 1200px){
			.cmx-price-row{gap:10px}
			.cmx-price-row .cmx-f{max-width:100%}
			.cmx-price-row .cmx-check{margin-left:0;margin-top:6px}
		}
	</style>';

	echo '<div class="cmx-price-row" role="group" aria-label="Konditionen & Preise">';

	echo '<div class="cmx-f cmx-f--md">
		<label for="cmx_artikel_sku">Artikel-Nr.</label>
		<input type="text" id="cmx_artikel_sku" name="cmx_artikel_sku" value="' . esc_attr($sku) . '" autocomplete="off">
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

	echo '<div class="cmx-f cmx-f--xs">
		<label for="cmx_artikel_selbstkosten">Selbstkosten</label>
		<input type="text" inputmode="decimal" id="cmx_artikel_selbstkosten" value="' . esc_attr($selbstkosten_display) . '" readonly>
	</div>';

	// Verkaufspreis
	echo '<div class="cmx-f cmx-f--xs">
		<label for="cmx_artikel_vk" id="cmx_artikel_vk_label" style="cursor:pointer;" title="Klicken, um den Vorgabe-Deckungsbeitrag als Vorschlag zu übernehmen">Verkaufspreis</label>
		<input type="text" inputmode="decimal" id="cmx_artikel_vk" name="cmx_artikel_vk" value="' . esc_attr($vk_display) . '">
	</div>';

	echo '<div class="cmx-f cmx-f--xs">
		<label for="cmx_artikel_deckungsbeitrag">Deckungsbeitrag</label>
		<input type="text" inputmode="decimal" id="cmx_artikel_deckungsbeitrag" value="' . esc_attr($deckungsbeitrag_display) . '" readonly>
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
	// EK, Aufwand und VK bleiben manuell.
	// Daraus werden Selbstkosten, Deckungsbeitrag und Marge nur angezeigt.
	echo '<script>
	document.addEventListener("DOMContentLoaded", function(){
		const ek  = document.getElementById("cmx_artikel_ek");
		const aw  = document.getElementById("cmx_artikel_aufwand");
		const vk  = document.getElementById("cmx_artikel_vk");
		const vkLabel = document.getElementById("cmx_artikel_vk_label");
		const sk  = document.getElementById("cmx_artikel_selbstkosten");
		const db  = document.getElementById("cmx_artikel_deckungsbeitrag");
		const mg  = document.getElementById("cmx_artikel_marge");
		const defaultDeckungsbeitragPercent = num(' . \wp_json_encode($deckungsbeitrag_percent) . ');

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

		function recalcDerived(){
			const selbstkosten = num(ek?.value) + num(aw?.value);
			const deckungsbeitrag = num(vk?.value) - selbstkosten;
			const margin = num(vk?.value) - num(ek?.value);
			if (sk) sk.value = formatCH(selbstkosten);
			if (db) db.value = formatCH(deckungsbeitrag);
			if (mg) mg.value = formatCH(margin);
		}

		function suggestVkFromDefaults() {
			const selbstkosten = num(ek?.value) + num(aw?.value);
			if (selbstkosten <= 0 || defaultDeckungsbeitragPercent <= 0) {
				return false;
			}
			const vkSuggestion = selbstkosten + (selbstkosten * defaultDeckungsbeitragPercent / 100);
			vk.value = formatCH(vkSuggestion);
			recalcDerived();
			setTimeout(function(){
				try { vk.focus(); vk.select(); } catch(e) {}
			}, 0);
			return true;
		}

		function maybeSuggestVkOnEmpty() {
			if (!vk) return false;
			const raw = (vk.value ?? "").toString().trim();
			if (raw !== "" && num(raw) !== 0) {
				return false;
			}
			return suggestVkFromDefaults();
		}

		function enableAutoSelect(el) {
			if (!el) return;
			el.addEventListener("focus", function(){ this.select(); });
			el.addEventListener("click", function(){ this.select(); });
			el.addEventListener("mouseup", function(e){ e.preventDefault(); });
		}

		// Alle manuellen Eingaben aktualisieren die readonly-Kennzahlen.
		["input","change"].forEach(evt=>{
			ek?.addEventListener(evt, recalcDerived, {passive:true});
			aw?.addEventListener(evt, recalcDerived, {passive:true});
			vk?.addEventListener(evt, recalcDerived, {passive:true});
		});

		// Alle Textfelder in der Konditionen-Box automatisch selektieren bei Klick/Fokus
		document.querySelectorAll(".cmx-price-row input[type=\"text\"], .cmx-price-row input[type=\"number\"]").forEach(enableAutoSelect);
		[ek, aw, vk].forEach(el => {
			if (!el) return;
			if (el.value !== "") el.value = formatCH(num(el.value));
		});
		[ek, aw, vk].forEach(el => {
			if (!el) return;
			el.addEventListener("blur", function(){
				const raw = (this.value ?? "").toString().trim();
				if (raw !== "") {
					this.value = formatCH(num(raw));
				}
				recalcDerived();
			});
		});

		if (vkLabel && vk) {
			vkLabel.addEventListener("click", function(){
				suggestVkFromDefaults();
				vk.focus();
				try { vk.select(); } catch(e) {}
			});
		}
		if (vk) {
			vk.addEventListener("mousedown", function(){
				maybeSuggestVkOnEmpty();
			});
			vk.addEventListener("focus", function(){
				maybeSuggestVkOnEmpty();
			});
		}

		// Initialzustand:
		recalcDerived();
	});
	</script>';
}

function cmx_artikel_waehrung_side_box_html(\WP_Post $post): void {
	cmx_artikel_render_save_nonce_once();
	echo '<input type="hidden" name="cmx_artikel_waehrung_payload" value="1">';
	$waehrung = cmx_meta_get($post->ID, CMX_ARTIKEL_META_WAEHRUNGEN, 'CHF');

	echo '<p><label for="cmx_artikel_waehrung"><strong>Währung auswählen</strong></label><br>';
	echo '<select id="cmx_artikel_waehrung" name="cmx_artikel_waehrung" class="widefat">';
	foreach (cmx_artikel_waehrung_optionen() as $val => $label) {
		echo '<option value="' . esc_attr($val) . '" ' . selected($waehrung, $val, false) . '>' . esc_html($label) . '</option>';
	}
	echo '</select></p>';
}

// Save-Handler:
// EK, Aufwand und VK werden manuell gespeichert.
// Selbstkosten, Deckungsbeitrag und Marge werden daraus serverseitig berechnet.
// ZUSÄTZLICH: Artikel-Nr. (SKU) und Währung speichern.
\add_action('save_post_artikel', function (int $post_id, \WP_Post $post) {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) return;
	if ($post->post_type !== 'artikel') return;
	if (!\current_user_can('edit_post', $post_id)) return;
	if (!isset($_POST['cmx_artikel_nonce']) || !\wp_verify_nonce($_POST['cmx_artikel_nonce'], 'cmx_artikel_save')) return;
	if (!isset($_POST['cmx_artikel_konditionen_payload']) && !isset($_POST['cmx_artikel_waehrung_payload'])) return;

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
		$allowed  = \array_keys(cmx_artikel_waehrung_optionen());
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

		$ek = $has_ek ? \round($norm($in('cmx_artikel_ek', '')), 2) : $current_ek;
		if (!$is_finite($ek) || $ek < 0) $ek = 0.00;

		$aufwand = $has_aufwand ? \round($norm($in('cmx_artikel_aufwand', '')), 2) : $current_aufwand;
		if (!$is_finite($aufwand)) $aufwand = 0.00;

		$vk = $has_vk ? \round($norm($in('cmx_artikel_vk', '')), 2) : $current_vk;
		if (!$is_finite($vk) || $vk < 0) $vk = 0.00;
		$selbstkosten = \round($ek + $aufwand, 2);
		$deckungsbeitrag = \round($vk - $selbstkosten, 2);

		\update_post_meta($post_id, CMX_ARTIKEL_META_EK, $ek);
		\update_post_meta($post_id, CMX_ARTIKEL_META_AUFWAND, $aufwand);
		\update_post_meta($post_id, CMX_ARTIKEL_META_VK, $vk);
		\update_post_meta($post_id, CMX_ARTIKEL_META_SELBSTKOSTEN, $selbstkosten);
		\update_post_meta($post_id, CMX_ARTIKEL_META_DECKUNGSBEITRAG, $deckungsbeitrag);
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
