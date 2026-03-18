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
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VARIANT_TAXONOMY')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VARIANT_TAXONOMY', '_cmx_artikel_variant_taxonomy');
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

function cmx_artikel_format_quantity_display(mixed $value): string {
	if ($value === '' || $value === null) return '';
	$normalized = cmx_parse_number($value);
	if (!\is_finite($normalized)) return '';
	if (\abs($normalized - \round($normalized)) < 0.0005) return (string) (int) \round($normalized);
	return \rtrim(\rtrim(\number_format($normalized, 3, '.', "'"), '0'), '.');
}

function cmx_artikel_normalize_quantity_value(mixed $value): string {
	$raw = \trim((string) $value);
	if ($raw === '') return '';
	$normalized = cmx_parse_number($raw);
	if (!\is_finite($normalized)) return '';
	$normalized = \max(0, $normalized);
	if (\abs($normalized - \round($normalized)) < 0.0005) return (string) (int) \round($normalized);
	return \rtrim(\rtrim(\number_format($normalized, 3, '.', ''), '0'), '.');
}

function cmx_artikel_variant_taxonomy_choices(): array {
	$labels_raw = \defined(__NAMESPACE__ . '\\CMX_TAX_ARTIKEL')
		? (string) \constant(__NAMESPACE__ . '\\CMX_TAX_ARTIKEL')
		: '';
	$labels = \array_values(\array_filter(\array_map('trim', \explode(',', $labels_raw)), static fn($value) => $value !== ''));
	if ($labels === []) return [];

	$exclude = [];
	if (\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_EINHEITEN')) {
		$exclude[] = (string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_EINHEITEN');
	}
	if (\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_TYPEN')) {
		$exclude[] = (string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_TYPEN');
	}
	if (\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_KATEGORIEN')) {
		$exclude[] = (string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_KATEGORIEN');
	}
	if (\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_MARKEN')) {
		$exclude[] = (string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_MARKEN');
	}

	$out = [];
	foreach ($labels as $label) {
		$taxonomy = cmx_tax_key('artikel', cmx_no_umlaute($label));
		if ($taxonomy === '' || \in_array($taxonomy, $exclude, true) || !\taxonomy_exists($taxonomy)) continue;
		$terms = [];
		foreach (cmx_get_terms_safe($taxonomy) as $term) {
			$terms[] = [
				'id' => (int) ($term->term_id ?? 0),
				'name' => (string) ($term->name ?? ''),
			];
		}
		$out[$taxonomy] = [
			'label' => (string) $label,
			'taxonomy' => $taxonomy,
			'terms' => $terms,
		];
	}

	$preferred = cmx_tax_key('artikel', cmx_no_umlaute('Grössen'));
	if ($preferred !== '' && isset($out[$preferred])) {
		$current = [$preferred => $out[$preferred]];
		unset($out[$preferred]);
		$out = $current + $out;
	}

	return $out;
}

function cmx_artikel_variant_taxonomy_current(int $post_id, array $choices): string {
	if ($choices === []) return '';

	$stored = (string) \get_post_meta($post_id, CMX_ARTIKEL_META_VARIANT_TAXONOMY, true);
	if ($stored !== '' && isset($choices[$stored])) return $stored;

	foreach (\array_keys($choices) as $taxonomy) {
		if (cmx_get_single_term_id($post_id, (string) $taxonomy) > 0) {
			return (string) $taxonomy;
		}
	}

	return (string) \array_key_first($choices);
}

function cmx_artikel_waehrung_preise_box_html(\WP_Post $post): void {
	cmx_artikel_render_save_nonce_once();
	echo '<input type="hidden" name="cmx_artikel_konditionen_payload" value="1">';

	$sku         = cmx_meta_get($post->ID, CMX_ARTIKEL_META_SKU, '');
	$anzahl_raw  = cmx_meta_get($post->ID, CMX_ARTIKEL_META_ANZAHL, '');
	$belegtext   = (string) \get_post_meta($post->ID, CMX_META_ARTIKEL_BELEG, true);
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
	$anzahl_display   = cmx_artikel_format_quantity_display($anzahl_raw);
	$ek_display       = ($ek === '' || $ek === null) ? '' : cmx_format_swiss_number($ek, 2);
	$aufwand_display  = ($aufwand === '' || $aufwand === null) ? '' : cmx_format_swiss_number($aufwand, 2);
	$vk_display       = ($vk === '' || $vk === null) ? '' : cmx_format_swiss_number($vk, 2);
	$selbstkosten_display = ($selbstkosten === '' || $selbstkosten === null) ? '' : cmx_format_swiss_number($selbstkosten, 2);
	$deckungsbeitrag_display = ($deckungsbeitrag === '' || $deckungsbeitrag === null) ? '' : cmx_format_swiss_number($deckungsbeitrag, 2);
	$marge_display    = ($marge === '' || $marge === null) ? '' : cmx_format_swiss_number($marge, 2);

	$variant_taxonomies = cmx_artikel_variant_taxonomy_choices();
	$current_variant_taxonomy = cmx_artikel_variant_taxonomy_current((int) $post->ID, $variant_taxonomies);
	$current_variant_label = $current_variant_taxonomy !== '' && isset($variant_taxonomies[$current_variant_taxonomy]['label'])
		? (string) $variant_taxonomies[$current_variant_taxonomy]['label']
		: 'Grössen';
	$current_variant_terms = $current_variant_taxonomy !== '' && isset($variant_taxonomies[$current_variant_taxonomy]['terms'])
		? (array) $variant_taxonomies[$current_variant_taxonomy]['terms']
		: [];
	$current_variant_term_id = $current_variant_taxonomy !== '' ? cmx_get_single_term_id($post->ID, $current_variant_taxonomy) : 0;
	$variant_taxonomies_json = \wp_json_encode($variant_taxonomies) ?: '{}';

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
		.cmx-price-row .cmx-f--full{min-width:100%;max-width:100%;flex:1 1 100%}
		.cmx-price-row .cmx-f--half{min-width:118px;max-width:150px;flex:1 1 124px}
		.cmx-price-row .cmx-f label{font-weight:600;margin-bottom:4px}
		.cmx-price-row .cmx-f .cmx-taxonomy-label{display:inline-flex;align-items:center;gap:4px;cursor:pointer;text-decoration:underline dotted}
		.cmx-price-row .cmx-f .cmx-taxonomy-label::after{content:"▾";font-size:10px;opacity:.75}
		.cmx-price-row .cmx-f .cmx-taxonomy-picker{display:none;margin:0 0 6px}
		.cmx-price-row .cmx-f input[type="number"],
		.cmx-price-row .cmx-f input[type="text"],
		.cmx-price-row .cmx-f select,
		.cmx-price-row .cmx-f textarea{width:100%}
		.cmx-price-row .cmx-f input[readonly]{background:#f6f7f7;color:#2c3338}
		.cmx-price-row .cmx-f textarea{min-height:38px;height:38px;resize:vertical}
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

	echo '<div class="cmx-f cmx-f--xs">
		<label for="cmx_artikel_anzahl">Anzahl</label>
		<input type="text" inputmode="decimal" id="cmx_artikel_anzahl" name="cmx_artikel_anzahl" value="' . esc_attr($anzahl_display) . '" autocomplete="off">
	</div>';

	echo '<div class="cmx-f cmx-f--half">';
	echo '<label id="cmx_artikel_variant_term_label" class="cmx-taxonomy-label" for="cmx_artikel_variant_term" role="button" tabindex="0" title="Klicken, um eine andere Taxonomie auszuwählen">' . esc_html($current_variant_label) . '</label>';
	echo '<select id="cmx_artikel_variant_taxonomy" name="cmx_artikel_variant_taxonomy" class="cmx-taxonomy-picker" aria-label="Taxonomie auswählen">';
	foreach ($variant_taxonomies as $taxonomy => $config) {
		echo '<option value="' . esc_attr((string) $taxonomy) . '" ' . selected($current_variant_taxonomy, (string) $taxonomy, false) . '>' . esc_html((string) ($config['label'] ?? $taxonomy)) . '</option>';
	}
	echo '</select>';
	echo '<select id="cmx_artikel_variant_term" name="cmx_artikel_variant_term">';
	if ($current_variant_terms === []) {
		echo '<option value="0">— keine Werte —</option>';
	} else {
		echo '<option value="0">— auswählen —</option>';
		foreach ($current_variant_terms as $term) {
			$term_id = (int) ($term['id'] ?? 0);
			$term_name = (string) ($term['name'] ?? '');
			if ($term_id <= 0 || $term_name === '') continue;
			echo '<option value="' . $term_id . '" ' . selected($current_variant_term_id, $term_id, false) . '>' . esc_html($term_name) . '</option>';
		}
	}
	echo '</select>';
	echo '</div>';

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

	echo '<div class="cmx-f cmx-f--full">
		<label for="cmx_artikel_beleg_text">Belegtext</label>
		<textarea id="cmx_artikel_beleg_text" name="cmx_artikel_beleg_text" rows="1">' . esc_textarea($belegtext) . '</textarea>
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
		const anzahl = document.getElementById("cmx_artikel_anzahl");
		const variantLabel = document.getElementById("cmx_artikel_variant_term_label");
		const variantTaxonomy = document.getElementById("cmx_artikel_variant_taxonomy");
		const variantTerm = document.getElementById("cmx_artikel_variant_term");
		const variantTaxonomies = ' . $variant_taxonomies_json . ';
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
		function formatQty(v){
			const parts = (Math.max(0, Number(num(v)) || 0)).toFixed(3).split(".");
			let left = parts[0];
			let out = "";
			while (left.length > 3) {
				out = "\'" + left.slice(-3) + out;
				left = left.slice(0, -3);
			}
			const frac = parts[1].replace(/0+$/, "");
			return left + out + (frac !== "" ? "." + frac : "");
		}
		function updateVariantTerms(selectedTaxonomy, selectedTermId){
			if (!variantTerm) return;
			const config = variantTaxonomies && variantTaxonomies[selectedTaxonomy] ? variantTaxonomies[selectedTaxonomy] : null;
			const terms = config && Array.isArray(config.terms) ? config.terms : [];
			const target = String(selectedTermId || "0");
			if (variantLabel && config && config.label) {
				variantLabel.textContent = config.label;
			}
			if (!terms.length) {
				variantTerm.innerHTML = "<option value=\"0\">— keine Werte —</option>";
				variantTerm.value = "0";
				variantTerm.disabled = true;
				return;
			}
			var html = "<option value=\"0\">— auswählen —</option>";
			terms.forEach(function(term){
				const id = parseInt(term && term.id ? term.id : 0, 10) || 0;
				const name = term && term.name ? String(term.name) : "";
				if (!id || !name) return;
				const selected = String(id) === target ? " selected" : "";
				html += "<option value=\"" + id + "\"" + selected + ">" + name.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;") + "</option>";
			});
			variantTerm.innerHTML = html;
			variantTerm.disabled = false;
			if (!variantTerm.querySelector("option[selected]")) {
				variantTerm.value = "0";
			}
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
		if (anzahl && anzahl.value !== "") anzahl.value = formatQty(anzahl.value);
		[ek, aw, vk].forEach(el => {
			if (!el) return;
			if (el.value !== "") el.value = formatCH(num(el.value));
		});
		if (anzahl) {
			anzahl.addEventListener("blur", function(){
				const raw = (this.value ?? "").toString().trim();
				if (raw !== "") {
					this.value = formatQty(raw);
				}
			});
		}
		if (variantLabel && variantTaxonomy) {
			const toggleTaxonomyPicker = function(e){
				if (e) e.preventDefault();
				variantTaxonomy.style.display = variantTaxonomy.style.display === "block" ? "none" : "block";
				if (variantTaxonomy.style.display === "block") {
					variantTaxonomy.focus();
				}
			};
			variantLabel.addEventListener("click", toggleTaxonomyPicker);
			variantLabel.addEventListener("keydown", function(e){
				if (e.key === "Enter" || e.key === " ") {
					toggleTaxonomyPicker(e);
				}
			});
			variantTaxonomy.addEventListener("change", function(){
				updateVariantTerms(this.value, 0);
				this.style.display = "none";
				if (variantTerm) variantTerm.focus();
			});
			variantTaxonomy.addEventListener("blur", function(){
				window.setTimeout(function(){
					if (document.activeElement !== variantTaxonomy) {
						variantTaxonomy.style.display = "none";
					}
				}, 120);
			});
			updateVariantTerms(variantTaxonomy.value, variantTerm ? variantTerm.value : 0);
		}
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

	if ($has('cmx_artikel_beleg_text')) {
		$belegtext = \sanitize_textarea_field(\wp_unslash((string) $in('cmx_artikel_beleg_text', '')));
		if ($belegtext === '') {
			\delete_post_meta($post_id, CMX_META_ARTIKEL_BELEG);
		} else {
			\update_post_meta($post_id, CMX_META_ARTIKEL_BELEG, $belegtext);
		}
	}

	if ($has('cmx_artikel_anzahl')) {
		$anzahl_raw = \trim((string) $in('cmx_artikel_anzahl', ''));
		if ($anzahl_raw === '') {
			\delete_post_meta($post_id, CMX_ARTIKEL_META_ANZAHL);
		} else {
			\update_post_meta($post_id, CMX_ARTIKEL_META_ANZAHL, cmx_artikel_normalize_quantity_value($anzahl_raw));
		}
	}

	$variant_taxonomies = cmx_artikel_variant_taxonomy_choices();
	if ($has('cmx_artikel_variant_taxonomy')) {
		$variant_taxonomy = \sanitize_key((string) $in('cmx_artikel_variant_taxonomy', ''));
		if (!isset($variant_taxonomies[$variant_taxonomy])) {
			$variant_taxonomy = cmx_artikel_variant_taxonomy_current($post_id, $variant_taxonomies);
		}
		if ($variant_taxonomy === '') {
			\delete_post_meta($post_id, CMX_ARTIKEL_META_VARIANT_TAXONOMY);
		} else {
			\update_post_meta($post_id, CMX_ARTIKEL_META_VARIANT_TAXONOMY, $variant_taxonomy);
			$variant_term_id = (int) $in('cmx_artikel_variant_term', 0);
			$term = $variant_term_id > 0 ? \get_term($variant_term_id, $variant_taxonomy) : null;
			\wp_set_post_terms(
				$post_id,
				($term && !\is_wp_error($term)) ? [$variant_term_id] : [],
				$variant_taxonomy,
				false
			);
		}
	}

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
