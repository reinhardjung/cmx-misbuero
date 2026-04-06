<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

if (!\defined(__NAMESPACE__ . '\\CMX_BUDGET_KOSTEN_TYP_META')) {
	\define(__NAMESPACE__ . '\\CMX_BUDGET_KOSTEN_TYP_META', '_cmx_budget_kosten_typ');
}
if (!\defined(__NAMESPACE__ . '\\CMX_BUDGET_KOSTEN_BETRAG_META')) {
	\define(__NAMESPACE__ . '\\CMX_BUDGET_KOSTEN_BETRAG_META', '_cmx_budget_kosten_betrag');
}
if (!\defined(__NAMESPACE__ . '\\CMX_BUDGET_KOSTEN_ANTEIL_META')) {
	\define(__NAMESPACE__ . '\\CMX_BUDGET_KOSTEN_ANTEIL_META', '_cmx_budget_kosten_anteil');
}
if (!\defined(__NAMESPACE__ . '\\CMX_BUDGET_KOSTEN_ANTEIL_BETRAG_META')) {
	\define(__NAMESPACE__ . '\\CMX_BUDGET_KOSTEN_ANTEIL_BETRAG_META', '_cmx_budget_kosten_anteil_betrag');
}
if (!\defined(__NAMESPACE__ . '\\CMX_BUDGET_KOSTEN_ZAHLBAR_PRO_META')) {
	\define(__NAMESPACE__ . '\\CMX_BUDGET_KOSTEN_ZAHLBAR_PRO_META', '_cmx_budget_kosten_zahlbar_pro');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_kosten_normalize_decimal')) {
	function cmx_budget_kosten_normalize_decimal($value): string {
		$value = \trim((string) $value);
		if ($value === '') {
			return '';
		}

		$value = \str_replace(["\xc2\xa0", ' '], '', $value);
		$value = \str_replace("'", '', $value);

		if (\str_contains($value, ',') && \str_contains($value, '.')) {
			$value = \str_replace('.', '', $value);
			$value = \str_replace(',', '.', $value);
		} elseif (\str_contains($value, ',')) {
			$value = \str_replace(',', '.', $value);
		}

		$value = (string) \preg_replace('/[^0-9.\-]/', '', $value);
		if ($value === '' || $value === '-' || $value === '.' || $value === '-.') {
			return '';
		}

		$number = (float) $value;
		return \number_format($number, 2, '.', '');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_kosten_calculate_anteil_betrag')) {
	function cmx_budget_kosten_calculate_anteil_betrag(string $betrag, string $anteil): string {
		$betrag_normalized = cmx_budget_kosten_normalize_decimal($betrag);
		$anteil_raw = \trim($anteil);
		if ($betrag_normalized === '' || $anteil_raw === '') {
			return '';
		}

		$betrag_value = (float) $betrag_normalized;
		$is_percent = \str_contains($anteil_raw, '%');
		$anteil_normalized = cmx_budget_kosten_normalize_decimal(\str_replace('%', '', $anteil_raw));
		if ($anteil_normalized === '') {
			return '';
		}

		$anteil_value = (float) $anteil_normalized;
		$result = $is_percent
			? ($betrag_value * $anteil_value / 100)
			: $anteil_value;

		return \number_format($result, 2, '.', '');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_kosten_format_display')) {
	function cmx_budget_kosten_format_display(string $value): string {
		$value = cmx_budget_kosten_normalize_decimal($value);
		if ($value === '') {
			return '';
		}

		return \number_format((float) $value, 2, ',', "'");
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_kosten_zahlbar_pro_options')) {
	function cmx_budget_kosten_zahlbar_pro_options(): array {
		return [
			'monat'         => 'Monat',
			'quartal'       => 'Quartal',
			'halbjaehrlich' => 'Halbjährlich',
			'jaehrlich'     => 'Jährlich',
		];
	}
}

\add_action('add_meta_boxes', function (): void {
	\add_meta_box(
		'cmx_budget_kosten_side',
		'Kosten',
		__NAMESPACE__ . '\\cmx_budget_kosten_box_html',
		'budget',
		'side',
		'default'
	);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_kosten_box_html')) {
	function cmx_budget_kosten_box_html(\WP_Post $post): void {
		$typ = (string) \get_post_meta($post->ID, CMX_BUDGET_KOSTEN_TYP_META, true);
		if ($typ !== 'ausgabe') {
			$typ = 'einnahme';
		}
		$betrag = (string) \get_post_meta($post->ID, CMX_BUDGET_KOSTEN_BETRAG_META, true);
		$anteil = (string) \get_post_meta($post->ID, CMX_BUDGET_KOSTEN_ANTEIL_META, true);
		if (\trim($anteil) === '') {
			$anteil = '100%';
		}
		$anteil_betrag = (string) \get_post_meta($post->ID, CMX_BUDGET_KOSTEN_ANTEIL_BETRAG_META, true);
		if ($anteil_betrag === '') {
			$anteil_betrag = cmx_budget_kosten_calculate_anteil_betrag($betrag, $anteil);
		}
		$zahlbar_pro = (string) \get_post_meta($post->ID, CMX_BUDGET_KOSTEN_ZAHLBAR_PRO_META, true);
		$zahlbar_pro_options = cmx_budget_kosten_zahlbar_pro_options();
		if (!isset($zahlbar_pro_options[$zahlbar_pro])) {
			$zahlbar_pro = 'monat';
		}

		\wp_nonce_field('cmx_budget_kosten_save', 'cmx_budget_kosten_nonce');

		$box_id = 'cmx-budget-kosten-box-' . (int) $post->ID;
		$betrag_display = cmx_budget_kosten_format_display($betrag);
		$anteil_betrag_display = cmx_budget_kosten_format_display($anteil_betrag);

		echo '<style>
		#' . \esc_attr($box_id) . ' .cmx-budget-kosten-stack{display:grid;gap:12px}
		#' . \esc_attr($box_id) . ' .cmx-budget-kosten-label{display:block;margin:0 0 6px;font-weight:600}
		#' . \esc_attr($box_id) . ' .cmx-budget-kosten-radio-row{display:flex;flex-wrap:wrap;gap:10px}
		#' . \esc_attr($box_id) . ' .cmx-budget-kosten-radio-row label{display:inline-flex;align-items:center;gap:5px;margin:0}
		#' . \esc_attr($box_id) . ' input[type="text"]{width:100%}
		#' . \esc_attr($box_id) . ' select{width:100%}
		#' . \esc_attr($box_id) . ' .cmx-budget-kosten-readonly{background:#f6f7f7;color:#50575e}
		#' . \esc_attr($box_id) . ' .cmx-budget-kosten-note{margin:4px 0 0;color:#646970;font-size:12px}
		</style>';

		echo '<div id="' . \esc_attr($box_id) . '">';
		echo '<div class="cmx-budget-kosten-stack">';

		echo '<div>';
		echo '<label class="cmx-budget-kosten-label" for="cmx_budget_kosten_betrag">Betrag</label>';
		echo '<input type="text" class="widefat" inputmode="decimal" id="cmx_budget_kosten_betrag" name="cmx_budget_kosten_betrag" value="' . \esc_attr($betrag_display) . '" placeholder="0,00">';
		echo '</div>';

		echo '<div>';
		echo '<span class="cmx-budget-kosten-label">Art</span>';
		echo '<div class="cmx-budget-kosten-radio-row">';
		echo '<label><input type="radio" name="cmx_budget_kosten_typ" value="einnahme" ' . \checked($typ, 'einnahme', false) . '> Einnahme</label>';
		echo '<label><input type="radio" name="cmx_budget_kosten_typ" value="ausgabe" ' . \checked($typ, 'ausgabe', false) . '> Ausgabe</label>';
		echo '</div>';
		echo '</div>';

		echo '<div>';
		echo '<label class="cmx-budget-kosten-label" for="cmx_budget_kosten_anteil">Anteil</label>';
		echo '<input type="text" class="widefat" inputmode="decimal" id="cmx_budget_kosten_anteil" name="cmx_budget_kosten_anteil" value="' . \esc_attr($anteil) . '" placeholder="10% oder 50,00">';
		echo '<p class="cmx-budget-kosten-note">Betrag direkt oder Prozentwert eingeben.</p>';
		echo '</div>';

		echo '<div>';
		echo '<label class="cmx-budget-kosten-label" for="cmx_budget_kosten_anteil_betrag_display">Anteil Betrag</label>';
		echo '<input type="text" class="widefat cmx-budget-kosten-readonly" id="cmx_budget_kosten_anteil_betrag_display" value="' . \esc_attr($anteil_betrag_display) . '" readonly>';
		echo '</div>';

		echo '<div>';
		echo '<label class="cmx-budget-kosten-label" for="cmx_budget_kosten_zahlbar_pro">Zahlbar pro</label>';
		echo '<select id="cmx_budget_kosten_zahlbar_pro" name="cmx_budget_kosten_zahlbar_pro">';
		foreach ($zahlbar_pro_options as $value => $label) {
			echo '<option value="' . \esc_attr($value) . '"' . \selected($zahlbar_pro, $value, false) . '>' . \esc_html($label) . '</option>';
		}
		echo '</select>';
		echo '</div>';

		echo '</div>';
		echo '</div>';

		echo '<script>
		(function(){
			var root = document.getElementById(' . \wp_json_encode($box_id) . ');
			if (!root || root.dataset.cmxBudgetKostenBound === "1") return;
			root.dataset.cmxBudgetKostenBound = "1";

			var betragInput = document.getElementById("cmx_budget_kosten_betrag");
			var anteilInput = document.getElementById("cmx_budget_kosten_anteil");
			var anteilReadonly = document.getElementById("cmx_budget_kosten_anteil_betrag_display");
			if (!betragInput || !anteilInput || !anteilReadonly) return;

			function selectOnFocus(event){
				var input = event && event.target ? event.target : null;
				if (!input || typeof input.select !== "function") return;
				window.requestAnimationFrame(function(){
					try { input.select(); } catch (err) {}
				});
			}

			function normalizeDecimal(value){
				value = String(value || "").trim();
				if (!value) return "";
				value = value.replace(/\\u00a0/g, "").replace(/\\s+/g, "").replace(/\'/g, "");
				if (value.indexOf(",") !== -1 && value.indexOf(".") !== -1) {
					value = value.replace(/\\./g, "").replace(/,/g, ".");
				} else if (value.indexOf(",") !== -1) {
					value = value.replace(/,/g, ".");
				}
				value = value.replace(/[^0-9.\\-]/g, "");
				if (!value || value === "-" || value === "." || value === "-.") return "";
				var parsed = Number(value);
				if (!isFinite(parsed)) return "";
				return parsed.toFixed(2);
			}

			function formatDisplay(value){
				var normalized = normalizeDecimal(value);
				if (!normalized) return "";
				var number = Number(normalized);
				return new Intl.NumberFormat("de-CH", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(number);
			}

			function updateReadonly(){
				var betrag = normalizeDecimal(betragInput.value);
				var anteilRaw = String(anteilInput.value || "").trim();
				if (!betrag || !anteilRaw) {
					anteilReadonly.value = "";
					return;
				}
				var isPercent = anteilRaw.indexOf("%") !== -1;
				var anteil = normalizeDecimal(anteilRaw.replace(/%/g, ""));
				if (!anteil) {
					anteilReadonly.value = "";
					return;
				}
				var result = isPercent
					? (Number(betrag) * Number(anteil) / 100)
					: Number(anteil);
				if (!isFinite(result)) {
					anteilReadonly.value = "";
					return;
				}
				anteilReadonly.value = formatDisplay(String(result));
			}

			betragInput.addEventListener("input", updateReadonly);
			anteilInput.addEventListener("input", updateReadonly);
			betragInput.addEventListener("focus", selectOnFocus);
			anteilInput.addEventListener("focus", selectOnFocus);
			updateReadonly();
		})();
		</script>';
	}
}

\add_action('save_post_budget', function (int $post_id, \WP_Post $post): void {
	if ($post->post_type !== 'budget') {
		return;
	}
	if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) {
		return;
	}
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (!isset($_POST['cmx_budget_kosten_nonce']) || !\wp_verify_nonce((string) \wp_unslash($_POST['cmx_budget_kosten_nonce']), 'cmx_budget_kosten_save')) {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

	$typ = isset($_POST['cmx_budget_kosten_typ']) ? \sanitize_key((string) \wp_unslash($_POST['cmx_budget_kosten_typ'])) : 'einnahme';
	if ($typ !== 'ausgabe') {
		$typ = 'einnahme';
	}

	$betrag_input = isset($_POST['cmx_budget_kosten_betrag']) ? (string) \wp_unslash($_POST['cmx_budget_kosten_betrag']) : '';
	$anteil_input = isset($_POST['cmx_budget_kosten_anteil']) ? (string) \wp_unslash($_POST['cmx_budget_kosten_anteil']) : '';
	$zahlbar_pro_input = isset($_POST['cmx_budget_kosten_zahlbar_pro']) ? \sanitize_key((string) \wp_unslash($_POST['cmx_budget_kosten_zahlbar_pro'])) : '';
	$betrag = cmx_budget_kosten_normalize_decimal($betrag_input);
	$anteil = \sanitize_text_field($anteil_input);
	$anteil_betrag = cmx_budget_kosten_calculate_anteil_betrag($betrag_input, $anteil_input);
	$zahlbar_pro_options = cmx_budget_kosten_zahlbar_pro_options();
	$zahlbar_pro = isset($zahlbar_pro_options[$zahlbar_pro_input]) ? $zahlbar_pro_input : 'monat';

	\update_post_meta($post_id, CMX_BUDGET_KOSTEN_TYP_META, $typ);

	if ($betrag === '') {
		\delete_post_meta($post_id, CMX_BUDGET_KOSTEN_BETRAG_META);
	} else {
		\update_post_meta($post_id, CMX_BUDGET_KOSTEN_BETRAG_META, $betrag);
	}

	if ($anteil === '') {
		\delete_post_meta($post_id, CMX_BUDGET_KOSTEN_ANTEIL_META);
	} else {
		\update_post_meta($post_id, CMX_BUDGET_KOSTEN_ANTEIL_META, $anteil);
	}

	if ($anteil_betrag === '') {
		\delete_post_meta($post_id, CMX_BUDGET_KOSTEN_ANTEIL_BETRAG_META);
	} else {
		\update_post_meta($post_id, CMX_BUDGET_KOSTEN_ANTEIL_BETRAG_META, $anteil_betrag);
	}

	\update_post_meta($post_id, CMX_BUDGET_KOSTEN_ZAHLBAR_PRO_META, $zahlbar_pro);
}, 10, 2);
