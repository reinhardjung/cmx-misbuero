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

if (!\function_exists(__NAMESPACE__ . '\\cmx_belege_default_due_days')) {
	function cmx_belege_default_due_days(array $opts = []): int {
		if (empty($opts)) {
			$opts = (array) \get_option('cmx_einstellungen', []);
		}
		$raw = isset($opts['belege_faelligkeit_tage']) ? (string) $opts['belege_faelligkeit_tage'] : '';
		$days = ($raw === '') ? 30 : (int) $raw;
		if ($days < 0) {
			$days = 0;
		}
		if ($days > 3650) {
			$days = 3650;
		}
		return $days;
	}
}

function cmx_beleg_zahlungsart_tax(): ?string {
	foreach (['belege_zahlungsarten', 'belege_zahlungsart'] as $tax) {
		if (\taxonomy_exists($tax)) return $tax;
	}
	return null;
}

function cmx_beleg_zahlungsgrund_tax(): ?string {
	$candidates = [];
	if (\defined(__NAMESPACE__ . '\\TAX_BELEGE_ZAHLUNGSGRUND')) {
		$candidates[] = (string) \constant(__NAMESPACE__ . '\\TAX_BELEGE_ZAHLUNGSGRUND');
	}
	$candidates[] = 'belege_zahlungsgrund';
	$candidates[] = 'belege_zahlungsgruende';
	$candidates[] = \function_exists(__NAMESPACE__ . '\\cmx_tax_key')
		? (string) cmx_tax_key('belege', 'zahlungsgrund')
		: 'belege_zahlungsgrund';

	foreach (\array_values(\array_unique(\array_filter($candidates))) as $tax) {
		if (\taxonomy_exists($tax)) return (string) $tax;
	}
	return null;
}

function cmx_beleg_taxonomy_admin_url(?string $taxonomy): string {
	if (!$taxonomy || !\taxonomy_exists($taxonomy)) {
		return '';
	}

	$taxonomy_obj = \get_taxonomy($taxonomy);
	if (!$taxonomy_obj || empty($taxonomy_obj->show_ui)) {
		return '';
	}

	$post_type = 'belege';
	$object_types = \is_array($taxonomy_obj->object_type) ? $taxonomy_obj->object_type : [];
	if (!\in_array($post_type, $object_types, true)) {
		foreach ($object_types as $candidate) {
			if (\post_type_exists($candidate)) {
				$post_type = $candidate;
				break;
			}
		}
	}

	return (string) \admin_url('edit-tags.php?taxonomy=' . \rawurlencode($taxonomy) . '&post_type=' . \rawurlencode($post_type));
}

function cmx_beleg_waehrung_tax(): ?string {
	foreach (['belege_waehrungen', 'artikel_waehrung'] as $tax) {
		if (\taxonomy_exists($tax)) {
			return $tax;
		}
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

// Zahlungsgrund wird direkt in "Konditionen" gerendert; doppelte Standard-Taxobox ausblenden.
\add_action('add_meta_boxes_belege', function () {
	$zg_tax = cmx_beleg_zahlungsgrund_tax();
	$ids = [
		'belege_zahlungsgrunddiv',
		'tagsdiv-belege_zahlungsgrund',
		'belege_zahlungsgruendediv',
		'tagsdiv-belege_zahlungsgruende',
	];
	if ($zg_tax) {
		$ids[] = $zg_tax . 'div';
		$ids[] = 'tagsdiv-' . $zg_tax;
	}
	foreach (\array_unique($ids) as $id) {
		\remove_meta_box((string) $id, 'belege', 'side');
	}
}, 100);

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
	$opts_general = (array) \get_option('cmx_einstellungen', []);
	$default_due_days = \function_exists(__NAMESPACE__ . '\\cmx_belege_default_due_days')
		? cmx_belege_default_due_days($opts_general)
		: 30;
	$use_leistungszeitraum = \function_exists(__NAMESPACE__ . '\\cmx_belege_uses_leistungszeitraum')
		? cmx_belege_uses_leistungszeitraum($opts_general)
		: !empty($opts_general['belege_use_leistungszeitraum']);

	// Aktueller Monat als Default, wenn leer
	if ($use_leistungszeitraum && !$leistMon) {
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
		. '<span id="cmx_f_today" style="text-decoration:none; cursor:pointer;">heute</span> '
		. '<span id="cmx_f_10" style="text-decoration:none; cursor:pointer;">&nbsp;10&nbsp;</span> '
		. '<span id="cmx_f_14" style="text-decoration:none; cursor:pointer;">&nbsp;14&nbsp;</span> '
		. '<span id="cmx_f_30" style="text-decoration:none; cursor:pointer;">&nbsp;30&nbsp;</span> '
		. '<span id="cmx_f_end" style="text-decoration:none; cursor:pointer;">Monatsende</span>'
		. ')</small>';
	echo '</label>';
	echo '<input type="date" name="cmx_beleg_faelligkeitsdatum" id="cmx_beleg_faelligkeitsdatum" style="width:100%;" value="' . \esc_attr($faellig) . '">';
	echo '</p>';

	// Leistungszeitraum (Monat) – Label klickbar => nächster Monat
	if ($use_leistungszeitraum) {
		echo '<p style="margin:8px 0 12px;">';
		echo '<label for="cmx_beleg_leistungsmonat" id="cmx_leistungs_label" style="display:block;margin-bottom:6px;cursor:pointer;"><strong>Leistungszeitraum</strong> <small style="color:#666;">(nächster Monat)</small></label>';
		echo '<select name="cmx_beleg_leistungsmonat" id="cmx_beleg_leistungsmonat" style="width:100%;">';
		foreach ($monate as $val => $label) {
			echo '<option value="' . \esc_attr($val) . '"' . \selected($leistMon, $val, false) . '>' . \esc_html($label) . '</option>';
		}
		echo '</select>';
		echo '</p>';
	}

	/* ===== Bestehende Währungs-Logik (unverändert) ===== */
	$currencies = cmx_get_artikel_waehrungen();
	$current    = \get_post_meta($post->ID, CMX_BELEG_META_WAEHRUNG, true);
	$current    = $current ? \strtoupper(\sanitize_text_field($current)) : '';
	$waehrung_tax_url = cmx_beleg_taxonomy_admin_url(cmx_beleg_waehrung_tax());

	if (!$current || !\in_array($current, $currencies, true)) {
		$current = \in_array('CHF', $currencies, true) ? 'CHF' : $currencies[0];
	}

	echo '<p style="margin:8px 0 12px;">';
	echo '<label for="cmx_beleg_waehrung_select" style="display:block;margin-bottom:6px;"><strong>';
	if ($waehrung_tax_url !== '') {
		echo '<a href="' . \esc_url($waehrung_tax_url) . '" target="_blank" rel="noopener noreferrer" style="color:inherit;text-decoration:none;">' .
			 \esc_html__('W&auml;hrung', 'cmx-misbuero') . '</a>';
	} else {
		echo \esc_html__('W&auml;hrung', 'cmx-misbuero');
	}
	echo '</strong></label>';
	echo '<select id="cmx_beleg_waehrung_select" name="cmx_beleg_waehrung" style="width:100%;">';

	foreach ($currencies as $code) {
		echo '<option value="' . \esc_attr($code) . '"' .
			 \selected($current, $code, false) . '>' . \esc_html($code) . '</option>';
	}

	echo '</select>';
	echo '</p>';

	$hide_pay_fields = false;
	if (function_exists(__NAMESPACE__ . '\\cmx_belege_tax')) {
		$tax = cmx_belege_tax();
		if ($tax) {
			$slugs = \wp_get_post_terms($post->ID, $tax, ['fields' => 'slugs']);
			if (!\is_wp_error($slugs) && array_intersect(['offerte', 'lieferschein'], $slugs)) {
				$hide_pay_fields = true;
			}
		}
	}
	echo '<div id="cmx_beleg_payment_fields" style="' . ($hide_pay_fields ? 'display:none;' : '') . '">';

	/* ===== NEU: Bezahlt am (am Ende der Metabox) ===== */
	echo '<p style="margin:8px 0 0;">';
	echo '<label for="cmx_beleg_bezahlt_am" id="cmx_bezahlt_label" style="display:block;margin-bottom:6px;cursor:pointer;"><strong>Bezahlt am</strong> <small style="color:#666;">(<span id="cmx_bezahlt_today" style="text-decoration:none; cursor:pointer;">heute</span> <span id="cmx_bezahlt_rng" style="text-decoration:none; cursor:pointer;">&nbsp;&nbsp;Beleg</span> <span id="cmx_bezahlt_partial" style="text-decoration:none; cursor:pointer;">&nbsp;&nbsp;geteilt</span>)</small> <a href="#" id="cmx_bezahlt_clear" style="margin-left:8px;font-size:10px; font-weight:normal; text-decoration:none;">offen</a></label>';
	echo '<input type="date" name="cmx_beleg_bezahlt_am" id="cmx_beleg_bezahlt_am" style="width:100%;" value="' . \esc_attr($bezahlt) . '">';

	// Inline-JS: sauberes Event-Handling inkl. "heute" vor 10/14/30/Monatsende
	echo '<script>(function(){function pad(n){return ("0"+n).slice(-2);}';
	echo 'var lblR=document.getElementById("cmx_rng_label"),inpR=document.getElementById("cmx_beleg_rng_datum");';
	echo 'var inpF=document.getElementById("cmx_beleg_faelligkeitsdatum");';
	echo 'var ltdy=document.getElementById("cmx_f_today"), l10=document.getElementById("cmx_f_10"), l14=document.getElementById("cmx_f_14"), l30=document.getElementById("cmx_f_30"), lend=document.getElementById("cmx_f_end");';
	echo 'var lblB=document.getElementById("cmx_bezahlt_label"),inpB=document.getElementById("cmx_beleg_bezahlt_am"),btnBToday=document.getElementById("cmx_bezahlt_today"),btnBRng=document.getElementById("cmx_bezahlt_rng"),btnBPartial=document.getElementById("cmx_bezahlt_partial"),btnBClear=document.getElementById("cmx_bezahlt_clear");';
	echo 'var lblL=document.getElementById("cmx_leistungs_label"),selL=document.getElementById("cmx_beleg_leistungsmonat");';
	echo 'var defaultDueDays=' . (int) $default_due_days . ';';

	// helpers
	echo 'function fmt(d){return d.getFullYear()+"-"+pad(d.getMonth()+1)+"-"+pad(d.getDate());}';
	echo 'function today(){return fmt(new Date());}';
	echo 'function isYmd(v){return /^\\d{4}-\\d{2}-\\d{2}$/.test(v||"");}';
	echo 'function monthEnd(){var b=baseDate(),y=b.getFullYear(),m=b.getMonth()+1;var last=new Date(y,m,0);return y+"-"+pad(m)+"-"+pad(last.getDate());}';
	echo 'function nextMonthVal(){var d=new Date(),m=d.getMonth()+2;if(m===13)m=1;return pad(m);}';
	echo 'function baseDate(){var v=(inpR&&inpR.value)?new Date(inpR.value):new Date(); if(isNaN(v)) v=new Date(); return v;}';
	echo 'function addDays(n){var b=baseDate(); b.setDate(b.getDate()+n); return fmt(b);}';
	echo 'function applyDefaultDueFromInvoice(force){if(!inpR||!inpF)return;if(!force&&(inpF.value||"")!=="")return;var n=parseInt(defaultDueDays,10);if(isNaN(n)||n<0)n=0;var b=baseDate();b.setDate(b.getDate()+n);inpF.value=fmt(b);}';
	echo 'function lastPartialDate(){var latest="";document.querySelectorAll("#cmx-anzahlungen-wrap .cmx-anzahlung-date").forEach(function(el){var val=(el&&el.value)?String(el.value).trim():"";if(!isYmd(val)) return;if(latest===""||val>latest){latest=val;}});return latest;}';

	// Rechnungsdatum -> heute
	echo 'if(lblR&&inpR){lblR.addEventListener("click",function(e){e.preventDefault();inpR.value=today();applyDefaultDueFromInvoice(true);});}';
	echo 'if(inpR&&inpF){inpR.addEventListener("change",function(){applyDefaultDueFromInvoice(true);});inpR.addEventListener("input",function(){applyDefaultDueFromInvoice(true);});}';

	// Fällig am: heute / +10 / +14 / +30 Tage / Monatsende (stopPropagation verhindert Label-Bubble)
	echo 'if(ltdy&&inpF){ltdy.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();inpF.value=today();});}';
	echo 'if(l10&&inpF){l10.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();inpF.value=addDays(10);});}';
	echo 'if(l14&&inpF){l14.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();inpF.value=addDays(14);});}';
	echo 'if(l30&&inpF){l30.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();inpF.value=addDays(30);});}';
	echo 'if(lend&&inpF){lend.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();inpF.value=monthEnd();});}';

	// Bezahlt am -> heute
	echo 'if(lblB&&inpB){lblB.addEventListener("click",function(e){var tid=(e.target&&e.target.id)?e.target.id:"";if(tid==="cmx_bezahlt_clear"||tid==="cmx_bezahlt_today"||tid==="cmx_bezahlt_rng"||tid==="cmx_bezahlt_partial"){return;}e.preventDefault();inpB.value=today();inpB.dispatchEvent(new Event("change",{bubbles:true}));});}';
	echo 'if(btnBToday&&inpB){btnBToday.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();inpB.value=today();inpB.dispatchEvent(new Event("change",{bubbles:true}));});}';
	echo 'if(btnBRng&&inpB){btnBRng.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();var rngVal=(inpR&&isYmd(inpR.value))?inpR.value:"";if(rngVal===""){return;}inpB.value=rngVal;inpB.dispatchEvent(new Event("change",{bubbles:true}));});}';
	echo 'if(btnBPartial&&inpB){btnBPartial.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();var partialVal=lastPartialDate();if(partialVal===""){return;}inpB.value=partialVal;inpB.dispatchEvent(new Event("change",{bubbles:true}));});}';
	echo 'if(btnBClear&&inpB){btnBClear.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();inpB.value="";var sel=document.getElementById("cmx_beleg_status");if(sel){sel.value="offen";sel.dispatchEvent(new Event("change",{bubbles:true}));}inpB.dispatchEvent(new Event("change",{bubbles:true}));});}';

	// Leistungszeitraum -> nächster Monat
	echo 'if(lblL&&selL){lblL.addEventListener("click",function(e){e.preventDefault();selL.value=nextMonthVal();});}';
	echo '})();</script>';

	echo '</p>';

		/* ===== NEU: Status ===== */
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
		$pay_tax_url = cmx_beleg_taxonomy_admin_url($pay_tax);

			$current_id = $bez_valid ? $current_id : 0;
			$wrap_style = ($bez_valid && $status !== 'teilbezahlt') ? 'block' : 'none';
		echo '<p id="cmx_beleg_zahlungsart_wrap" style="margin:8px 0 0; display:' . $wrap_style . ';">';
		echo '<label for="cmx_beleg_zahlungsart" style="display:block;margin-bottom:6px;"><strong>';
		if ($pay_tax_url !== '') {
			echo '<a href="' . \esc_url($pay_tax_url) . '" target="_blank" rel="noopener noreferrer" style="color:inherit;text-decoration:none;">Zahlungsart</a>';
		} else {
			echo 'Zahlungsart';
		}
		echo '</strong></label>';
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

		echo '</div>';

		/* ===== NEU: Zahlungsgrund (immer sichtbar, als letztes Feld) ===== */
		$zg_tax = cmx_beleg_zahlungsgrund_tax();
		if ($zg_tax) {
			$zg_terms = \get_terms(['taxonomy' => $zg_tax, 'hide_empty' => false]);
			$zg_current_terms = \wp_get_post_terms($post->ID, $zg_tax, ['fields' => 'ids']);
			$zg_current_id = (int) ($zg_current_terms[0] ?? 0);
			$zg_tax_url = cmx_beleg_taxonomy_admin_url($zg_tax);

			echo '<p id="cmx_beleg_zahlungsgrund_wrap" style="margin:8px 0 0;">';
			echo '<label for="cmx_beleg_zahlungsgrund" style="display:block;margin-bottom:6px;"><strong>';
			if ($zg_tax_url !== '') {
				echo '<a href="' . \esc_url($zg_tax_url) . '" target="_blank" rel="noopener noreferrer" style="color:inherit;text-decoration:none;">Zahlungsgrund</a>';
			} else {
				echo 'Zahlungsgrund';
			}
			echo '</strong></label>';
			echo '<select name="cmx_beleg_zahlungsgrund" id="cmx_beleg_zahlungsgrund" style="width:100%;">';
			echo '<option value="">— auswählen —</option>';
			if (!\is_wp_error($zg_terms)) {
				foreach ($zg_terms as $term) {
					echo '<option value="' . \esc_attr((string) $term->term_id) . '"' . \selected($zg_current_id, (int) $term->term_id, false) . '>' . \esc_html((string) $term->name) . '</option>';
				}
			}
			echo '</select>';
			echo '</p>';
		}

	echo '<script>(function(){var inpB=document.getElementById("cmx_beleg_bezahlt_am");var selS=document.getElementById("cmx_beleg_status");var payWrap=document.getElementById("cmx_beleg_zahlungsart_wrap");var paySel=document.getElementById("cmx_beleg_zahlungsart");var payFields=document.getElementById("cmx_beleg_payment_fields");var lblF=document.getElementById("cmx_faellig_label");function findZahlungsgrundBoxes(){var nodes=[];document.querySelectorAll(".postbox").forEach(function(box){var title=box.querySelector(".hndle, h2, h3");var t=title?title.textContent.trim():"";if(t&&t.toLowerCase().includes("zahlungsgrund")){nodes.push(box);}});var direct=document.querySelectorAll("#tagsdiv-belege_zahlungsgrund, #belege_zahlungsgrunddiv, .postbox[id*=\\"zahlungsgrund\\"]");direct.forEach(function(n){if(nodes.indexOf(n)===-1) nodes.push(n);});return nodes;}var canPay=!!(inpB&&selS);function hasValidDate(){return canPay&&/^\\d{4}-\\d{2}-\\d{2}$/.test(inpB.value||"");}function syncStatus(){if(!canPay){return;}if(hasValidDate()&&selS.value!=="bezahlt"&&selS.value!=="teilbezahlt"){selS.value="bezahlt";selS.dispatchEvent(new Event("change",{bubbles:true}));}}function syncPay(){if(!canPay||!payWrap){return;}var show=hasValidDate()&&selS.value!=="teilbezahlt";payWrap.style.display=show?"block":"none";if(!show&&paySel){paySel.value="";paySel.selectedIndex=0;}}function getSlug(){var el=document.querySelector("input[name=cmx_beleg_kategorie]:checked");return el?(el.getAttribute("data-slug")||""):"";}function syncDueLabel(slug){if(!lblF||!lblF.querySelector){return;}var strong=lblF.querySelector("strong");if(!strong){return;}strong.textContent=(slug==="offerte"||slug==="offerten")?"Gültig bis":"Fällig am";}function syncKategorieFields(){var slug=getSlug();var hide=slug==="offerte"||slug==="lieferschein";syncDueLabel(slug);if(payFields){payFields.style.display=hide?"none":"";}findZahlungsgrundBoxes().forEach(function(box){box.style.display=hide?"none":"";});if(hide&&canPay){inpB.value="";selS.value="offen";syncPay();}}function onStatusChange(){if(!canPay){return;}if(selS.value==="offen"){inpB.value="";syncPay();}else if(selS.value==="teilbezahlt"){syncPay();}else if(selS.value==="bezahlt"){inpB.value=inpB.value||new Date().toISOString().slice(0,10);syncPay();}}if(canPay){inpB.addEventListener("change",function(){syncStatus();syncPay();});inpB.addEventListener("input",function(){syncStatus();syncPay();});selS.addEventListener("change",onStatusChange);}document.addEventListener("change",function(e){if(e.target&&e.target.name==="cmx_beleg_kategorie"){syncKategorieFields();}});document.addEventListener("DOMContentLoaded",syncKategorieFields);setTimeout(syncKategorieFields,0);syncStatus();syncPay();})();</script>';
}

/** ===== Speichern (ergänzt um die neuen Felder, bestehende Währungslogik bleibt) ===== */
\add_action('save_post_belege', function (int $post_id, \WP_Post $post, bool $update) {
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!isset($_POST['cmx_beleg_waehrung_nonce']) || !\wp_verify_nonce($_POST['cmx_beleg_waehrung_nonce'], 'cmx_save_beleg_waehrung')) return;
	if (!\current_user_can('edit_post', $post_id)) return;
	if ($post->post_type !== 'belege') return;

	// RNG Datum
	$rng = isset($_POST['cmx_beleg_rng_datum']) ? \sanitize_text_field((string) \wp_unslash($_POST['cmx_beleg_rng_datum'])) : null;
	// Kein automatisches Setzen mehr: nur gültiges Datum speichern.
	// Bei leer/ungültig bleibt der bestehende Wert unverändert.
	if (\is_string($rng) && \preg_match('/^\d{4}-\d{2}-\d{2}$/', $rng)) {
		\update_post_meta($post_id, CMX_BELEG_META_RNG_DATUM, $rng);
	}

	// Fälligkeitsdatum
	$fae = isset($_POST['cmx_beleg_faelligkeitsdatum']) ? \sanitize_text_field($_POST['cmx_beleg_faelligkeitsdatum']) : '';
	if ($fae && \preg_match('/^\d{4}-\d{2}-\d{2}$/', $fae)) {
		\update_post_meta($post_id, CMX_BELEG_META_FAELLIG, $fae);
	} else {
		\delete_post_meta($post_id, CMX_BELEG_META_FAELLIG);
	}

	// Leistungszeitraum (Monat 01..12)
	$opts_general = (array) \get_option('cmx_einstellungen', []);
	$use_leistungszeitraum = \function_exists(__NAMESPACE__ . '\\cmx_belege_uses_leistungszeitraum')
		? cmx_belege_uses_leistungszeitraum($opts_general)
		: !empty($opts_general['belege_use_leistungszeitraum']);
	if ($use_leistungszeitraum) {
		$lm = isset($_POST['cmx_beleg_leistungsmonat']) ? \sanitize_text_field($_POST['cmx_beleg_leistungsmonat']) : '';
		if ($lm && \preg_match('/^(0[1-9]|1[0-2])$/', $lm)) {
			\update_post_meta($post_id, CMX_BELEG_META_LEISTUNGSMONAT, $lm);
		} else {
			\delete_post_meta($post_id, CMX_BELEG_META_LEISTUNGSMONAT);
		}
	}

	// ===== Bestehende Währungs-Speicherung (unverändert) =====
	$currencies = cmx_get_artikel_waehrungen();
	$input = isset($_POST['cmx_beleg_waehrung']) ? \strtoupper(\sanitize_text_field($_POST['cmx_beleg_waehrung'])) : '';

	if ($input && \preg_match('/^[A-Z]{3}$/', $input) && \in_array($input, $currencies, true)) {
		\update_post_meta($post_id, CMX_BELEG_META_WAEHRUNG, $input);
	} else {
		\delete_post_meta($post_id, CMX_BELEG_META_WAEHRUNG);
	}

	// ===== NEU: Status =====
	$val = isset($_POST['cmx_beleg_status']) ? \sanitize_key($_POST['cmx_beleg_status']) : '';
	$opts = cmx_beleg_status_options();
	if (!isset($opts[$val])) {
		$val = array_key_first($opts);
	}

	// ===== NEU: Bezahlt am =====
	$bez = isset($_POST['cmx_beleg_bezahlt_am']) ? \sanitize_text_field($_POST['cmx_beleg_bezahlt_am']) : '';
	$bez_valid = ($bez && \preg_match('/^\d{4}-\d{2}-\d{2}$/', $bez));
		if (!\in_array($val, ['bezahlt', 'teilbezahlt'], true)) {
			$bez = '';
			$bez_valid = false;
		}
	if ($bez_valid) {
		\update_post_meta($post_id, CMX_BELEG_META_BEZAHLT_AM, $bez);
	} else {
		\delete_post_meta($post_id, CMX_BELEG_META_BEZAHLT_AM);
	}

		if ($bez_valid && $val !== 'teilbezahlt') {
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

	// ===== NEU: Zahlungsgrund =====
	$zg_tax = cmx_beleg_zahlungsgrund_tax();
	if ($zg_tax) {
		$term_id = isset($_POST['cmx_beleg_zahlungsgrund']) ? (int) $_POST['cmx_beleg_zahlungsgrund'] : 0;
		if ($term_id > 0) {
			\wp_set_post_terms($post_id, [$term_id], $zg_tax, false);
		} else {
			\wp_set_post_terms($post_id, [], $zg_tax, false);
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
