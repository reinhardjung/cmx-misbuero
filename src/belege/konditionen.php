<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/** ===== Konstanten (bestehend) ===== */
if (!\defined(__NAMESPACE__ . '\\CMX_BELEG_META_WAEHRUNG')) {
	\define(__NAMESPACE__ . '\\CMX_BELEG_META_WAEHRUNG', '_cmx_beleg_waehrung');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_WAEHRUNG')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_WAEHRUNG', '_cmx_artikel_waehrung');
}

/** ===== Neue Meta-Konstanten (ergänzt) ===== */
if (!\defined(__NAMESPACE__ . '\\CMX_BELEG_META_RNG_DATUM')) {
	\define(__NAMESPACE__ . '\\CMX_BELEG_META_RNG_DATUM', '_cmx_beleg_rng_datum'); // YYYY-MM-DD
}
if (!\defined(__NAMESPACE__ . '\\CMX_BELEG_META_FAELLIG')) {
	\define(__NAMESPACE__ . '\\CMX_BELEG_META_FAELLIG', '_cmx_beleg_faelligkeitsdatum'); // YYYY-MM-DD
}
if (!\defined(__NAMESPACE__ . '\\CMX_BELEG_META_LEISTUNGSMONAT')) {
	\define(__NAMESPACE__ . '\\CMX_BELEG_META_LEISTUNGSMONAT', '_cmx_beleg_leistungsmonat'); // '01'..'12'
}
if (!\defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM')) {
	\define(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM', '_cmx_beleg_bezahlt_am'); // YYYY-MM-DD
}
if (!\defined(__NAMESPACE__ . '\\CMX_BELEG_META_STATUS')) {
	\define(__NAMESPACE__ . '\\CMX_BELEG_META_STATUS', '_cmx_beleg_status');
}

if (!\function_exists(__NAMESPACE__.'\\cmx_beleg_status_options')) {
function cmx_beleg_status_options(): array {
		return [
			'offen'          => 'Offen',
			'bezahlt'        => 'Bezahlt',
			'teilbezahlt'    => 'Teilbezahlt',
		];
	}
}

function cmx_beleg_zahlungsart_tax(): ?string {
	foreach (['belege_zahlungsarten', 'belege_zahlungsart'] as $tax) {
		if (\taxonomy_exists($tax)) return $tax;
	}
	return null;
}

/**
 * Liefert eine eindeutige, sortierte Liste möglicher Währungen aus dem CPT "artikel".
 */
function cmx_get_artikel_waehrungen(): array {
	$waehrungen = [];

	// 1) Taxonomie nutzen, wenn vorhanden
	foreach (['belege_waehrungen', 'artikel_waehrung'] as $tax) {
		if (!\taxonomy_exists($tax)) {
			continue;
		}
		$terms = \get_terms(['taxonomy' => $tax, 'hide_empty' => false]);
		if (!\is_wp_error($terms) && !empty($terms)) {
			foreach ($terms as $t) {
				$code = \strtoupper(\sanitize_text_field($t->slug ?: $t->name));
				if (\preg_match('/^[A-Z]{3}$/', $code)) {
					$waehrungen[] = $code;
				}
			}
		}
		if (!empty($waehrungen)) {
			break;
		}
	}

	// 2) Distinct-Postmeta als Rückfall
	if (empty($waehrungen)) {
		global $wpdb;
		$pm   = \esc_sql(CMX_ARTIKEL_META_WAEHRUNG);
		$sql  = "
			SELECT DISTINCT pm.meta_value
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = '{$pm}'
			  AND p.post_type = 'artikel'
			  AND p.post_status IN ('publish','draft','pending','future','private')
		";
		$rows = $wpdb->get_col($sql);
		if (!empty($rows)) {
			foreach ($rows as $val) {
				$code = \strtoupper(\sanitize_text_field((string)$val));
				if (\preg_match('/^[A-Z]{3}$/', $code)) {
					$waehrungen[] = $code;
				}
			}
		}
	}

	// 3) Fallback
	if (empty($waehrungen)) {
		$waehrungen = ['CHF','EUR','USD'];
	}

	$waehrungen = \array_values(\array_unique($waehrungen));
	\sort($waehrungen, \SORT_ASC);

	return (array) \apply_filters('cmx_belege_waehrungen', $waehrungen);
}

/** ===== Metabox registrieren ===== */
\add_action('add_meta_boxes', function () {
	\add_meta_box(
		'cmx_beleg_waehrung',
		__('Konditionen', 'cmx-misbuero'),
		__NAMESPACE__ . '\\cmx_render_beleg_waehrung_box',
		'belege',
		'side',
		'high'
	);
});

/**
 * Render der Side-Box
 * (Neue Felder kommen VOR die bestehende Währungs-Selectbox; am Ende „Bezahlt am“)
 */
function cmx_render_beleg_waehrung_box(\WP_Post $post): void {
	\wp_nonce_field('cmx_save_beleg_waehrung', 'cmx_beleg_waehrung_nonce');

	/* ===== Neue Felder: RNG Datum / Fälligkeitsdatum / Leistungszeitraum ===== */
	$rng      = \get_post_meta($post->ID, CMX_BELEG_META_RNG_DATUM, true);
	$faellig  = \get_post_meta($post->ID, CMX_BELEG_META_FAELLIG, true);
	$leistMon = \get_post_meta($post->ID, CMX_BELEG_META_LEISTUNGSMONAT, true);
	$bezahlt  = \get_post_meta($post->ID, CMX_BELEG_META_BEZAHLT_AM, true);
	$bez_valid = ($bezahlt && \preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $bezahlt));

	// Fälligkeitsdatum Standard: heute + 30 Tage, falls leer
	if ($faellig === '' || $faellig === null) {
		$faellig = \gmdate('Y-m-d', strtotime('+30 days'));
	}

	// Aktueller Monat als Default, wenn leer
	if (!$leistMon) {
		$ts = \current_time('timestamp');
		$leistMon = \gmdate('m', $ts);
	}

	$monate = [
		'01' => 'Januar',  '02' => 'Februar', '03' => 'März',
		'04' => 'April',   '05' => 'Mai',     '06' => 'Juni',
		'07' => 'Juli',    '08' => 'August',  '09' => 'September',
		'10' => 'Oktober', '11' => 'November','12' => 'Dezember',
	];

	// RNG Datum (mit klickbarem Label = heute)
	echo '<p style="margin:8px 0 12px;">';
	echo '<label for="cmx_beleg_rng_datum" id="cmx_rng_label" style="display:block;margin-bottom:6px;cursor:pointer;"><strong>Datum des Beleges</strong> <small style="color:#666;">(heute)</small></label>';
	echo '<input type="date" name="cmx_beleg_rng_datum" id="cmx_beleg_rng_datum" style="width:100%;" value="' . \esc_attr($rng) . '">';
	echo '</p>';

	// Fälligkeitsdatum (mit klickbaren Optionen = heute / 10 / 14 / 30 Tage & Monatsende)
	echo '<p style="margin:8px 0 12px;">';
	echo '<label for="cmx_beleg_faelligkeitsdatum" id="cmx_faellig_label" style="display:block;margin-bottom:6px;cursor:pointer;">';
	echo '<strong>Fällig am</strong> ';
	echo '<small style="color:#666;">('
		. '<span id="cmx_f_today" style="text-decoration:underline; cursor:pointer;">heute</span> '
		. '<span id="cmx_f_10" style="text-decoration:underline; cursor:pointer;">&nbsp;10&nbsp;</span> '
		. '<span id="cmx_f_14" style="text-decoration:underline; cursor:pointer;">&nbsp;14&nbsp;</span> '
		. '<span id="cmx_f_30" style="text-decoration:underline; cursor:pointer;">&nbsp;30&nbsp;</span> '
		. '<span id="cmx_f_end" style="text-decoration:underline; cursor:pointer;">Monatsende</span>'
		. ')</small>';
	echo '</label>';
	echo '<input type="date" name="cmx_beleg_faelligkeitsdatum" id="cmx_beleg_faelligkeitsdatum" style="width:100%;" value="' . \esc_attr($faellig) . '">';
	echo '</p>';

	// Leistungszeitraum (Monat) – Label klickbar => nächster Monat
	echo '<p style="margin:8px 0 12px;">';
	echo '<label for="cmx_beleg_leistungsmonat" id="cmx_leistungs_label" style="display:block;margin-bottom:6px;cursor:pointer;"><strong>Leistungszeitraum</strong> <small style="color:#666;">(nächster Monat)</small></label>';
	echo '<select name="cmx_beleg_leistungsmonat" id="cmx_beleg_leistungsmonat" style="width:100%;">';
	foreach ($monate as $val => $label) {
		echo '<option value="' . \esc_attr($val) . '"' . \selected($leistMon, $val, false) . '>' . \esc_html($label) . '</option>';
	}
	echo '</select>';
	echo '</p>';

	/* ===== Bestehende Währungs-Logik (unverändert) ===== */
	$currencies = cmx_get_artikel_waehrungen();
	$current    = \get_post_meta($post->ID, CMX_BELEG_META_WAEHRUNG, true);
	$current    = $current ? \strtoupper(\sanitize_text_field($current)) : '';

	if (!$current || !\in_array($current, $currencies, true)) {
		$current = \in_array('CHF', $currencies, true) ? 'CHF' : $currencies[0];
	}

	echo '<p style="margin:8px 0 12px;">';
	echo '<label for="cmx_beleg_waehrung_select" style="display:block;margin-bottom:6px;"><strong>' .
		 \esc_html__('W&auml;hrung', 'cmx-misbuero') . '</strong></label>';
	echo '<select id="cmx_beleg_waehrung_select" name="cmx_beleg_waehrung" style="width:100%;">';

	foreach ($currencies as $code) {
		echo '<option value="' . \esc_attr($code) . '"' .
			 \selected($current, $code, false) . '>' . \esc_html($code) . '</option>';
	}

	echo '</select>';
	echo '</p>';

	/* ===== NEU: Bezahlt am (am Ende der Metabox) ===== */
	echo '<p style="margin:8px 0 0;">';
	echo '<label for="cmx_beleg_bezahlt_am" id="cmx_bezahlt_label" style="display:block;margin-bottom:6px;cursor:pointer;"><strong>Bezahlt am</strong> <small style="color:#666;">(heute)</small> <a href="#" id="cmx_bezahlt_clear" style="margin-left:8px;font-size:12px; font-weight:normal;">unbezahlt</a></label>';
	echo '<input type="date" name="cmx_beleg_bezahlt_am" id="cmx_beleg_bezahlt_am" style="width:100%;" value="' . \esc_attr($bezahlt) . '">';

	// Inline-JS: sauberes Event-Handling inkl. "heute" vor 10/14/30/Monatsende
	echo '<script>(function(){function pad(n){return ("0"+n).slice(-2);}';
	echo 'var lblR=document.getElementById("cmx_rng_label"),inpR=document.getElementById("cmx_beleg_rng_datum");';
	echo 'var inpF=document.getElementById("cmx_beleg_faelligkeitsdatum");';
	echo 'var ltdy=document.getElementById("cmx_f_today"), l10=document.getElementById("cmx_f_10"), l14=document.getElementById("cmx_f_14"), l30=document.getElementById("cmx_f_30"), lend=document.getElementById("cmx_f_end");';
	echo 'var lblB=document.getElementById("cmx_bezahlt_label"),inpB=document.getElementById("cmx_beleg_bezahlt_am"),btnBClear=document.getElementById("cmx_bezahlt_clear");';
	echo 'var lblL=document.getElementById("cmx_leistungs_label"),selL=document.getElementById("cmx_beleg_leistungsmonat");';

	// helpers
	echo 'function fmt(d){return d.getFullYear()+"-"+pad(d.getMonth()+1)+"-"+pad(d.getDate());}';
	echo 'function today(){return fmt(new Date());}';
	echo 'function monthEnd(){var d=new Date(),y=d.getFullYear(),m=d.getMonth()+1;var last=new Date(y,m,0);return y+"-"+pad(m)+"-"+pad(last.getDate());}';
	echo 'function nextMonthVal(){var d=new Date(),m=d.getMonth()+2;if(m===13)m=1;return pad(m);}';
	echo 'function baseDate(){var v=(inpR&&inpR.value)?new Date(inpR.value):new Date(); if(isNaN(v)) v=new Date(); return v;}';
	echo 'function addDays(n){var b=baseDate(); b.setDate(b.getDate()+n); return fmt(b);}';

	// Rechnungsdatum -> heute
	echo 'if(lblR&&inpR){lblR.addEventListener("click",function(e){e.preventDefault();inpR.value=today();});}';

	// Fällig am: heute / +10 / +14 / +30 Tage / Monatsende (stopPropagation verhindert Label-Bubble)
	echo 'if(ltdy&&inpF){ltdy.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();inpF.value=today();});}';
	echo 'if(l10&&inpF){l10.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();inpF.value=addDays(10);});}';
	echo 'if(l14&&inpF){l14.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();inpF.value=addDays(14);});}';
	echo 'if(l30&&inpF){l30.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();inpF.value=addDays(30);});}';
	echo 'if(lend&&inpF){lend.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();inpF.value=monthEnd();});}';

	// Bezahlt am -> heute
	echo 'if(lblB&&inpB){lblB.addEventListener("click",function(e){if(e.target&&e.target.id==="cmx_bezahlt_clear"){return;}e.preventDefault();inpB.value=today();inpB.dispatchEvent(new Event("change",{bubbles:true}));});}';
	echo 'if(btnBClear&&inpB){btnBClear.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();inpB.value="";var sel=document.getElementById("cmx_beleg_status");if(sel){sel.value="offen";}inpB.dispatchEvent(new Event("change",{bubbles:true}));});}';

	// Leistungszeitraum -> nächster Monat
	echo 'if(lblL&&selL){lblL.addEventListener("click",function(e){e.preventDefault();selL.value=nextMonthVal();});}';
	echo '})();</script>';

	echo '</p>';

	/* ===== NEU: Status (als letztes Feld) ===== */
	$status = \get_post_meta($post->ID, CMX_BELEG_META_STATUS, true);
	$status_opts = cmx_beleg_status_options();
	if (!isset($status_opts[$status])) {
		$status = array_key_first($status_opts);
	}
	echo '<p style="margin:8px 0 0;">';
	echo '<label for="cmx_beleg_status" style="display:block;margin-bottom:6px;"><strong>Status</strong></label>';
	echo '<select name="cmx_beleg_status" id="cmx_beleg_status" style="width:100%;">';
	foreach ($status_opts as $val => $label) {
		echo '<option value="' . \esc_attr($val) . '"' . \selected($status, $val, false) . '>' . \esc_html($label) . '</option>';
	}
	echo '</select>';
	echo '</p>';

	/* ===== NEU: Zahlungsart (nur wenn Bezahlt am gültig) ===== */
	$pay_tax = cmx_beleg_zahlungsart_tax();
	if ($pay_tax) {
		$pay_terms = \get_terms(['taxonomy' => $pay_tax, 'hide_empty' => false]);
		$current_terms = \wp_get_post_terms($post->ID, $pay_tax, ['fields' => 'ids']);
		$current_id = $current_terms[0] ?? 0;

		$current_id = $bez_valid ? $current_id : 0;
		$wrap_style = $bez_valid ? 'block' : 'none';
		echo '<p id="cmx_beleg_zahlungsart_wrap" style="margin:8px 0 0; display:' . $wrap_style . ';">';
		echo '<label for="cmx_beleg_zahlungsart" style="display:block;margin-bottom:6px;"><strong>Zahlungsart</strong></label>';
		echo '<select name="cmx_beleg_zahlungsart" id="cmx_beleg_zahlungsart" style="width:100%;">';
		echo '<option value="">— auswählen —</option>';
		if (!\is_wp_error($pay_terms)) {
			foreach ($pay_terms as $term) {
				echo '<option value="' . \esc_attr($term->term_id) . '"' . \selected($current_id, $term->term_id, false) . '>' . \esc_html($term->name) . '</option>';
			}
		}
		echo '</select>';
		echo '</p>';
	}

	echo '<script>(function(){var inpB=document.getElementById("cmx_beleg_bezahlt_am");var selS=document.getElementById("cmx_beleg_status");var payWrap=document.getElementById("cmx_beleg_zahlungsart_wrap");var paySel=document.getElementById("cmx_beleg_zahlungsart");if(!inpB||!selS)return;function hasValidDate(){return /^\\d{4}-\\d{2}-\\d{2}$/.test(inpB.value||"");}function syncStatus(){if(hasValidDate()){selS.value="bezahlt";}}function syncPay(){if(!payWrap)return;var show=hasValidDate();payWrap.style.display=show?"block":"none";if(!show&&paySel){paySel.value="";paySel.selectedIndex=0;}}inpB.addEventListener("change",function(){syncStatus();syncPay();});inpB.addEventListener("input",function(){syncStatus();syncPay();});syncStatus();syncPay();})();</script>';
}

/** ===== Speichern (ergänzt um die neuen Felder, bestehende Währungslogik bleibt) ===== */
\add_action('save_post_belege', function (int $post_id, \WP_Post $post, bool $update) {
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!isset($_POST['cmx_beleg_waehrung_nonce']) || !\wp_verify_nonce($_POST['cmx_beleg_waehrung_nonce'], 'cmx_save_beleg_waehrung')) return;
	if (!\current_user_can('edit_post', $post_id)) return;
	if ($post->post_type !== 'belege') return;

	// RNG Datum
	$rng = isset($_POST['cmx_beleg_rng_datum']) ? \sanitize_text_field($_POST['cmx_beleg_rng_datum']) : '';
	if (!$rng || !\preg_match('/^\d{4}-\d{2}-\d{2}$/', $rng)) {
		// Default: heutiges Datum
		$rng = \gmdate('Y-m-d', \current_time('timestamp'));
	}
	\update_post_meta($post_id, CMX_BELEG_META_RNG_DATUM, $rng);

	// Fälligkeitsdatum
	$fae = isset($_POST['cmx_beleg_faelligkeitsdatum']) ? \sanitize_text_field($_POST['cmx_beleg_faelligkeitsdatum']) : '';
	if (!$fae || !\preg_match('/^\d{4}-\d{2}-\d{2}$/', $fae)) {
		// Default: +30 Tage, wenn kein gültiges Datum geliefert
		$fae = \gmdate('Y-m-d', strtotime('+30 days'));
	}
	\update_post_meta($post_id, CMX_BELEG_META_FAELLIG, $fae);

	// Leistungszeitraum (Monat 01..12)
	$lm = isset($_POST['cmx_beleg_leistungsmonat']) ? \sanitize_text_field($_POST['cmx_beleg_leistungsmonat']) : '';
	if ($lm && \preg_match('/^(0[1-9]|1[0-2])$/', $lm)) {
		\update_post_meta($post_id, CMX_BELEG_META_LEISTUNGSMONAT, $lm);
	} else {
		\delete_post_meta($post_id, CMX_BELEG_META_LEISTUNGSMONAT);
	}

	// ===== Bestehende Währungs-Speicherung (unverändert) =====
	$currencies = cmx_get_artikel_waehrungen();
	$input = isset($_POST['cmx_beleg_waehrung']) ? \strtoupper(\sanitize_text_field($_POST['cmx_beleg_waehrung'])) : '';

	if ($input && \preg_match('/^[A-Z]{3}$/', $input) && \in_array($input, $currencies, true)) {
		\update_post_meta($post_id, CMX_BELEG_META_WAEHRUNG, $input);
	} else {
		\delete_post_meta($post_id, CMX_BELEG_META_WAEHRUNG);
	}

	// ===== NEU: Bezahlt am =====
	$bez = isset($_POST['cmx_beleg_bezahlt_am']) ? \sanitize_text_field($_POST['cmx_beleg_bezahlt_am']) : '';
	$bez_valid = ($bez && \preg_match('/^\d{4}-\d{2}-\d{2}$/', $bez));
	if ($bez_valid) {
		\update_post_meta($post_id, CMX_BELEG_META_BEZAHLT_AM, $bez);
	} else {
		\delete_post_meta($post_id, CMX_BELEG_META_BEZAHLT_AM);
	}

	// ===== NEU: Status =====
	$val = isset($_POST['cmx_beleg_status']) ? \sanitize_key($_POST['cmx_beleg_status']) : '';
	$opts = cmx_beleg_status_options();
	if (!isset($opts[$val])) {
		$val = array_key_first($opts);
	}
	if ($bez_valid) {
		$val = 'bezahlt';
	}
	\update_post_meta($post_id, CMX_BELEG_META_STATUS, $val);

	// ===== NEU: Zahlungsart =====
	$pay_tax = cmx_beleg_zahlungsart_tax();
	if ($pay_tax) {
		$term_id = isset($_POST['cmx_beleg_zahlungsart']) ? (int) $_POST['cmx_beleg_zahlungsart'] : 0;
		if ($bez_valid && $term_id > 0) {
			\wp_set_post_terms($post_id, [$term_id], $pay_tax, false);
		} else {
			\wp_set_post_terms($post_id, [], $pay_tax, false);
		}
	}
}, 10, 3);

/**
 * ===== Anzeige der Option-Labels wie im Artikel-CPT (CODE – Name) =====
 */
function cmx_artikel_waehrung_label_map(): array {
	$map = [];

	if (\taxonomy_exists('artikel_waehrung')) {
		$terms = \get_terms(['taxonomy' => 'artikel_waehrung', 'hide_empty' => false]);
		if (!\is_wp_error($terms) && $terms) {
			foreach ($terms as $t) {
				$code = \strtoupper(\sanitize_text_field($t->slug ?: $t->name));
				$name = \sanitize_text_field($t->name);
				if (\preg_match('/^[A-Z]{3}$/', $code)) {
					$map[$code] = $code . ' – ' . $name;
				}
			}
		}
	}

	$fallback = [
		'CHF' => 'Schweizer Franken',
		'EUR' => 'Euro',
		'USD' => 'US-Dollar',
	];
	foreach ($fallback as $code => $label) {
		if (!isset($map[$code])) $map[$code] = $code . ' – ' . $label;
	}

	return $map;
}

\add_action('admin_print_footer_scripts', function () {
	$screen = \get_current_screen();
	if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'belege') return;

	$map = cmx_artikel_waehrung_label_map();
	if (empty($map)) return;

	echo '<script>';
	echo 'document.addEventListener("DOMContentLoaded",function(){';
	echo 'const sel=document.getElementById("cmx_beleg_waehrung_select");';
	echo 'if(!sel)return;';
	echo 'const map=' . \wp_json_encode($map) . ';';
	echo 'for(let i=0;i<sel.options.length;i++){const o=sel.options[i];if(map[o.value])o.text=map[o.value];}';
	echo '});';
	echo '</script>';
});

// Nie direkt löschen: immer erst in den Papierkorb (gilt für alle Post Types)
// Nie direkt löschen: immer erst in den Papierkorb (gilt für alle Post Types)
\add_filter('pre_delete_post', function ($null, \WP_Post $post, bool $force_delete) {
	if ($force_delete && $post->post_status !== 'trash') {
		// Schicke in den Papierkorb statt hart zu löschen
		\wp_trash_post($post->ID);
		return true; // Short-circuit hard delete
	}
	return $null;
}, 10, 3);

// Admin-Hinweis korrigieren: "endgültig gelöscht" → "in den Papierkorb verschoben"
\add_action('admin_init', function() {
	if (!\is_admin()) return;
	if (isset($_GET['deleted']) && !isset($_GET['trashed'])) {
		// Übersetze deleted-Marker in "trashed", damit WP die richtige Meldung zeigt
		$_GET['trashed'] = (int) $_GET['deleted'];
		unset($_GET['deleted']);
	}
});

// Bulk-/Listen-Meldungen überschreiben (für alle Post Types)
\add_filter('bulk_post_updated_messages', function(array $bulk_messages, array $bulk_counts): array {
	foreach ($bulk_messages as $post_type => $messages) {
		if (isset($bulk_counts['deleted']) && $bulk_counts['deleted'] > 0) {
			$bulk_messages[$post_type]['deleted'] = _n(
				'%s Beitrag in den Papierkorb verschoben.',
				'%s Beiträge in den Papierkorb verschoben.',
				$bulk_counts['deleted'],
				'default'
			);
		}
	}
	return $bulk_messages;
}, 10, 2);
