<?php
/**
 * Plugin Name: CMX Belege – Kopfdaten mit Projektsuche & Kontaktsuche (AJAX, Inline-Buttons, Keyboard-Navigation)
 * Description: Metabox für CPT "belege" mit AJAX-Suche (Projekte & Kontakte), Inline-"Löschen", Keyboard-Navigation (↑/↓/Enter/Esc), Auto-Fill: Kontakt/Adresse aus Projekt, Betreff aus Projekt-URL. Titel = nur Rechnungsnummer.
 * Author: CLOUDMEISTER
 */

namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

/* =========================================================
 * Meta Key
 * ========================================================= */
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_ANZAHLUNGEN')) {
	\define(__NAMESPACE__.'\\CMX_BELEG_META_ANZAHLUNGEN', '_cmx_beleg_anzahlungen');
}

/* =========================================================
 * Metabox registrieren
 * ========================================================= */
\add_action('add_meta_boxes', function () {
	if (!\post_type_exists('belege')) return;
	\add_meta_box(
		'cmx_beleg_anzahlungen',
		'Teilzahlungen',
		__NAMESPACE__.'\\cmx_render_beleg_anzahlungen_metabox',
		'belege',
		'side',
		'default'
	);
});

/* =========================================================
 * Daten laden
 * ========================================================= */
function cmx_beleg_anzahlungen_get_rows(int $post_id): array {
	$raw = \get_post_meta($post_id, CMX_BELEG_META_ANZAHLUNGEN, true);

	if (\is_string($raw) && $raw !== '') {
		$decoded = \json_decode($raw, true);
		if (\json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
			$raw = $decoded;
		} else {
			$maybe = @\maybe_unserialize($raw);
			$raw = \is_array($maybe) ? $maybe : [];
		}
	} elseif (!\is_array($raw)) {
		$raw = [];
	}

	$rows = [];
	foreach ($raw as $row) {
		if (!\is_array($row)) continue;
		$datum  = isset($row['datum']) ? \trim((string)$row['datum']) : '';
		$betrag_raw = isset($row['betrag']) ? \trim((string)$row['betrag']) : '';
		$betrag = $betrag_raw !== '' ? cmx_format_swiss_number($betrag_raw, 2) : '';
		$zahlungsart = isset($row['zahlungsart']) ? \trim((string)$row['zahlungsart']) : '';
		if ($datum === '' && $betrag === '' && $zahlungsart === '') continue;
		$rows[] = ['datum'=>$datum, 'betrag'=>$betrag, 'zahlungsart'=>$zahlungsart];
	}
	if (\count($rows) > 1) {
		\usort($rows, function(array $a, array $b): int {
			$ad = $a['datum'] ?? '';
			$bd = $b['datum'] ?? '';
			if ($ad === $bd) return 0;
			if ($ad === '') return 1;
			if ($bd === '') return -1;
			$at = \strtotime($ad) ?: 0;
			$bt = \strtotime($bd) ?: 0;
			if ($at === $bt) return \strcmp($ad, $bd);
			return $at <=> $bt;
		});
	}
	return $rows;
}

/* =========================================================
 * Metabox-Render
 * ========================================================= */
function cmx_render_beleg_anzahlungen_metabox(\WP_Post $post): void {
	$rows = cmx_beleg_anzahlungen_get_rows($post->ID);
	$has_rows = !empty($rows);
	if (!$rows) $rows = [['datum'=>'', 'betrag'=>'']];
	$pay_tax = function_exists(__NAMESPACE__ . '\\cmx_beleg_zahlungsart_tax') ? cmx_beleg_zahlungsart_tax() : null;
	$pay_terms = $pay_tax ? \get_terms(['taxonomy' => $pay_tax, 'hide_empty' => false]) : [];
	$status = \get_post_meta($post->ID, \defined(__NAMESPACE__ . '\\CMX_BELEG_META_STATUS') ? CMX_BELEG_META_STATUS : '_cmx_beleg_status', true);
	$wrap_style = ($status === 'teilbezahlt' || $has_rows) ? '' : 'display:none;';

	\wp_nonce_field('cmx_save_beleg_anzahlungen', 'cmx_beleg_anzahlungen_nonce');

	echo '<style>
		.cmx-anzahlung-row{border:1px solid #e2e4e7;background:#fff;padding:6px;margin:0 0 8px}
		.cmx-anzahlung-row label{display:block;font-size:11px;margin:0 0 2px}
		.cmx-anzahlung-row input[type=text]{width:100%}
		.cmx-anzahlung-row input[type=date]{width:100%}
		.cmx-anzahlung-today{cursor:pointer;text-decoration:underline}
		.cmx-anzahlung-betrag-row{display:flex;gap:6px;align-items:center}
		.cmx-anzahlung-betrag-row input[type=text]{flex:1 1 auto}
		.cmx-anzahlung-del{flex:0 0 auto;padding:0 8px;line-height:24px}
	</style>';

	echo '<div id="cmx-anzahlungen-wrap" style="' . $wrap_style . '">';
	foreach ($rows as $i => $row) {
		$datum  = \esc_attr($row['datum'] ?? '');
		$betrag = \esc_attr($row['betrag'] ?? '');
		$zahlungsart = isset($row['zahlungsart']) ? (string) $row['zahlungsart'] : '';
		echo '<div class="cmx-anzahlung-row">';
		echo '<label>Datum <small style="color:#666;">(<span class="cmx-anzahlung-today">heute</span>)</small></label>';
		echo '<input type="date" class="cmx-anzahlung-date" data-name="cmx_anzahlungen[__INDEX__][datum]" name="cmx_anzahlungen['.$i.'][datum]" value="'.$datum.'">';
		echo '<label style="margin-top:6px">Betrag</label>';
		echo '<div class="cmx-anzahlung-betrag-row">';
		echo '<input type="text" data-name="cmx_anzahlungen[__INDEX__][betrag]" name="cmx_anzahlungen['.$i.'][betrag]" value="'.$betrag.'">';
		echo '<button type="button" class="button-link-delete cmx-anzahlung-del"><span class="dashicons dashicons-trash" style="color:#d63638;"></span></button>';
		echo '</div>';
		echo '<label style="margin-top:6px">Zahlungsart</label>';
		echo '<select class="cmx-anzahlung-zahlungsart" data-name="cmx_anzahlungen[__INDEX__][zahlungsart]" name="cmx_anzahlungen['.$i.'][zahlungsart]" style="width:100%;">';
		echo '<option value="">— auswählen —</option>';
		if ($pay_tax && !\is_wp_error($pay_terms)) {
			foreach ($pay_terms as $term) {
				echo '<option value="' . \esc_attr($term->term_id) . '"' . \selected($zahlungsart, (string) $term->term_id, false) . '>' . \esc_html($term->name) . '</option>';
			}
		}
		echo '</select>';
		echo '</div>';
	}
	echo '</div>';

	echo '<button type="button" class="button" id="cmx-anzahlung-add">hinzufuegen</button>';

	echo '<script type="text/html" id="cmx-anzahlung-template">
		<div class="cmx-anzahlung-row">
			<label>Datum <small style="color:#666;">(<span class="cmx-anzahlung-today">heute</span>)</small></label>
			<input type="date" class="cmx-anzahlung-date" data-name="cmx_anzahlungen[__INDEX__][datum]" name="cmx_anzahlungen[__INDEX__][datum]" value="">
			<label style="margin-top:6px">Betrag</label>
			<div class="cmx-anzahlung-betrag-row">
				<input type="text" data-name="cmx_anzahlungen[__INDEX__][betrag]" name="cmx_anzahlungen[__INDEX__][betrag]" value="">
				<button type="button" class="button-link-delete cmx-anzahlung-del"><span class="dashicons dashicons-trash" style="color:#d63638;"></span></button>
			</div>
			<label style="margin-top:6px">Zahlungsart</label>
			<select class="cmx-anzahlung-zahlungsart" data-name="cmx_anzahlungen[__INDEX__][zahlungsart]" name="cmx_anzahlungen[__INDEX__][zahlungsart]" style="width:100%;">
				<option value="">— auswählen —</option>
				'.(function() use ($pay_tax, $pay_terms){
					if (!$pay_tax || is_wp_error($pay_terms)) return '';
					$out = '';
					foreach ($pay_terms as $term) {
						$out .= '<option value="' . esc_attr($term->term_id) . '">' . esc_html($term->name) . '</option>';
					}
					return $out;
				})().'
			</select>
		</div>
	</script>';

	echo '<script>
	jQuery(function($){
		const $wrap = $("#cmx-anzahlungen-wrap");
		const tmpl = $("#cmx-anzahlung-template").html();
		const $status = $("#cmx_beleg_status");
		const $box = $("#cmx_beleg_anzahlungen").closest(".postbox");

		function toNumber(v){
			let s = (v ?? "").toString().trim();
			if (s === "") return 0;
			s = s.replace(/\s+/g, "").replace(/\'/g, "");
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
			return isNaN(n) ? 0 : n;
		}
		function formatCH(n){
			const parts = (Number(n) || 0).toFixed(2).split(".");
			let left = parts[0];
			let out = "";
			while (left.length > 3) {
				out = "\'" + left.slice(-3) + out;
				left = left.slice(0, -3);
			}
			return left + out + "." + parts[1];
		}

		function reindexRows(){
			$wrap.find(".cmx-anzahlung-row").each(function(i){
				$(this).find("input[data-name], select[data-name]").each(function(){
					const base = $(this).data("name");
					if (base) $(this).attr("name", base.replace("__INDEX__", i));
				});
			});
		}

		function todayISO(){
			const d = new Date();
			const y = d.getFullYear();
			const m = String(d.getMonth() + 1).padStart(2, "0");
			const day = String(d.getDate()).padStart(2, "0");
			return y + "-" + m + "-" + day;
		}
		function hasFilledRows(){
			let filled = false;
			$wrap.find(".cmx-anzahlung-row").each(function(){
				const dateVal = ($(this).find(".cmx-anzahlung-date").val() ?? "").toString().trim();
				const amountVal = ($(this).find("input[name*=\"[betrag]\"]").val() ?? "").toString().trim();
				const methodVal = ($(this).find(".cmx-anzahlung-zahlungsart").val() ?? "").toString().trim();
				if (dateVal !== "" || amountVal !== "" || methodVal !== "") {
					filled = true;
					return false;
				}
			});
			return filled;
		}
		function getSlug(){
			const el = document.querySelector("input[name=cmx_beleg_kategorie]:checked");
			return el ? (el.getAttribute("data-slug") || "") : "";
		}
		function syncWrap(){
			const slug = getSlug();
			const hideByCat = slug === "offerte" || slug === "lieferschein";
			const show = !hideByCat && ($status.val() === "teilbezahlt" || hasFilledRows());
			$wrap.toggle(show);
			if ($box.length) {
				$box.toggle(show);
			}
		}

		$("#cmx-anzahlung-add").on("click", function(){
			const idx = $wrap.find(".cmx-anzahlung-row").length;
			const $row = $(tmpl.replace(/__INDEX__/g, idx));
			$wrap.append($row);
			syncWrap();
			$row.find("input:first").trigger("focus");
		});

		$wrap.on("click", ".cmx-anzahlung-del", function(){
			const $rows = $wrap.find(".cmx-anzahlung-row");
			if ($rows.length <= 1) {
				$(this).closest(".cmx-anzahlung-row").find("input, select").val("");
				syncWrap();
				return;
			}
			$(this).closest(".cmx-anzahlung-row").remove();
			reindexRows();
			syncWrap();
		});

		$wrap.on("click", ".cmx-anzahlung-today", function(e){
			e.preventDefault();
			const $row = $(this).closest(".cmx-anzahlung-row");
			$row.find(".cmx-anzahlung-date").val(todayISO()).trigger("change");
		});
		$wrap.on("blur", "input[name*=\"[betrag]\"]", function(){
			const raw = ($(this).val() ?? "").toString().trim();
			if (raw === "") return;
			$(this).val(formatCH(toNumber(raw)));
			syncWrap();
		});
		$wrap.on("change input", ".cmx-anzahlung-date, .cmx-anzahlung-zahlungsart, input[name*=\"[betrag]\"]", syncWrap);

		if ($status.length) {
			$status.on("change", syncWrap);
			$(document).on("change", "input[name=cmx_beleg_kategorie]", syncWrap);
			syncWrap();
		}
	});
	</script>';
}

/* =========================================================
 * Save-Handler
 * ========================================================= */
\add_action('save_post_belege', function($post_id, \WP_Post $post, $update) {
	if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) return;
	if ($post->post_type !== 'belege') return;
	if (!\current_user_can('edit_post', $post_id)) return;
	if (!isset($_POST['cmx_beleg_anzahlungen_nonce']) || !\wp_verify_nonce($_POST['cmx_beleg_anzahlungen_nonce'], 'cmx_save_beleg_anzahlungen')) return;
	if (defined('DOING_AJAX') && DOING_AJAX) return;

	$rows = $_POST['cmx_anzahlungen'] ?? [];
	if (!\is_array($rows)) {
		\delete_post_meta($post_id, CMX_BELEG_META_ANZAHLUNGEN);
		return;
	}

	if (\count($rows) > 200) $rows = \array_slice($rows, 0, 200);

	$clean = [];
	foreach ($rows as $row) {
		if (!\is_array($row)) continue;
		$datum  = isset($row['datum']) ? \trim((string)\sanitize_text_field(\wp_unslash($row['datum']))) : '';
		$betrag_raw = isset($row['betrag']) ? \trim((string)\sanitize_text_field(\wp_unslash($row['betrag']))) : '';
		$betrag_raw = $betrag_raw !== '' ? (string) \preg_replace('/\s*(chf|fr\.?)\s*/i', '', $betrag_raw) : '';
		$betrag = '';
		if ($betrag_raw !== '' && preg_match('/\d/', $betrag_raw)) {
			$betrag = number_format(cmx_parse_number($betrag_raw), 2, '.', '');
		}
		$zahlungsart = isset($row['zahlungsart']) ? \trim((string)\sanitize_text_field(\wp_unslash($row['zahlungsart']))) : '';
		if ($datum === '' && $betrag === '') continue;
		$clean[] = ['datum'=>$datum, 'betrag'=>$betrag, 'zahlungsart'=>$zahlungsart];
	}

	if (!$clean) {
		\delete_post_meta($post_id, CMX_BELEG_META_ANZAHLUNGEN);
		return;
	}

	\update_post_meta($post_id, CMX_BELEG_META_ANZAHLUNGEN, \wp_json_encode($clean));
}, 10, 3);
