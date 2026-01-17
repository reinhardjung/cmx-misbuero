<?php
/**
 * Datei: src/belege/admin-metabox-summe-all-in-one.php
 * Zweck:
 *  - Sidebar-Metabox "Gesamtsumme" für CPT belege
 *  - Serverseitige Summenberechnung aus _cmx_beleg_positionen (Array/JSON/serialisiert)
 *  - Live-Aktualisierung per inline JavaScript (robuste Selektoren, MutationObserver)
 */
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

/** ===== Metabox registrieren ===== */
\add_action('add_meta_boxes', function () {
	add_meta_box(
		'cmx_beleg_summe_box',
		__('Gesamtsumme', 'cmx'),
		__NAMESPACE__ . '\\cmx_beleg_summe_box_render',
		'belege',
		'side',
		'high'
	);
});

/** ===== Hilfsfunktion: Positionen sicher laden ===== */
function cmx_load_positionen($post_id): array {
	$raw = get_post_meta($post_id, '_cmx_beleg_positionen', true);
	if (empty($raw)) return [];

	$pos = maybe_unserialize($raw);
	if (is_string($pos)) {
		$decoded = json_decode($pos, true);
		if (json_last_error() === JSON_ERROR_NONE) $pos = $decoded;
	}
	return is_array($pos) ? $pos : [];
}

function cmx_has_positionen_data(array $positionen): bool {
	foreach ($positionen as $pos) {
		if (!is_array($pos)) continue;
		if (!empty($pos['artikel_id']) && (int)$pos['artikel_id'] > 0) return true;
		$name = trim((string)($pos['artikel_name'] ?? $pos['item'] ?? $pos['title'] ?? ''));
		if ($name !== '') return true;
		$menge = trim((string)($pos['menge'] ?? ''));
		$preis = trim((string)($pos['preis'] ?? ''));
		$rabatt = trim((string)($pos['rabatt'] ?? ''));
		if ($menge !== '' || $preis !== '' || $rabatt !== '') return true;
	}
	return false;
}

/** ===== Helper (nur definieren, wenn nicht vorhanden) ===== */
if (!function_exists(__NAMESPACE__ . '\\cmx_norm_decimal')) {
	function cmx_norm_decimal(string $val): string {
		$s = str_replace([" ", "'"], '', (string)$val);
		$hasC = strpos($s, ',') !== false;
		$hasD = strpos($s, '.') !== false;
		if ($hasC && $hasD) {
			if (strrpos($s, ',') > strrpos($s, '.')) {
				$s = str_replace('.', '', $s);
				$s = str_replace(',', '.', $s);
			} else {
				$s = str_replace(',', '', $s);
			}
		} else {
			$s = str_replace(',', '.', $s);
		}
		return $s;
	}
}
if (!function_exists(__NAMESPACE__ . '\\cmx_round_5rp')) {
	function cmx_round_5rp(float $amount): float { return round($amount * 20) / 20; }
}
if (!function_exists(__NAMESPACE__ . '\\cmx_parse_rabatt')) {
	function cmx_parse_rabatt(float $subtotal, $raw): float {
		if ($raw === null || $raw === '') return 0.0;
		$txt  = strtolower(trim((string)$raw));
		$base = abs($subtotal);
		if (substr($txt, -1) === '%') {
			$p   = (float) cmx_norm_decimal(substr($txt, 0, -1));
			$rab = max(0.0, $base * ($p / 100));
		} else {
			$txt = preg_replace('/\s*(chf|fr\.?)\s*/i', '', $txt);
			$rab = max(0.0, (float) cmx_norm_decimal($txt));
		}
		if ($rab > $base) $rab = $base;
		return ($subtotal >= 0 ? 1 : -1) * $rab;
	}
}

/** ===== Anzahlungen laden und sumieren ===== */
if (!defined(__NAMESPACE__ . '\\CMX_BELEG_META_ANZAHLUNGEN')) {
	define(__NAMESPACE__ . '\\CMX_BELEG_META_ANZAHLUNGEN', '_cmx_beleg_anzahlungen');
}
function cmx_load_anzahlungen_summe(int $post_id): array {
	$raw = get_post_meta($post_id, CMX_BELEG_META_ANZAHLUNGEN, true);
	if (empty($raw)) return ['summe' => 0.0, 'count' => 0];

	$rows = $raw;
	if (is_string($rows)) {
		$decoded = json_decode($rows, true);
		if (json_last_error() === JSON_ERROR_NONE) {
			$rows = $decoded;
		} else {
			$maybe = @maybe_unserialize($rows);
			$rows = is_array($maybe) ? $maybe : [];
		}
	}
	if (!is_array($rows)) return ['summe' => 0.0, 'count' => 0];

	$sum = 0.0;
	$count = 0;
	foreach ($rows as $row) {
		if (!is_array($row)) continue;
		$datum  = isset($row['datum']) ? trim((string)$row['datum']) : '';
		$betrag = isset($row['betrag']) ? trim((string)$row['betrag']) : '';
		if ($datum === '' && $betrag === '') continue;
		$count++;

		if ($betrag !== '') {
			$txt = preg_replace('/\s*(chf|fr\.?)\s*/i', '', $betrag);
			$sum += (float) cmx_norm_decimal($txt);
		}
	}

	return ['summe' => $sum, 'count' => $count];
}

/** ===== Render der Metabox (mit Serversumme & JS-Target) ===== */
function cmx_beleg_summe_box_render(\WP_Post $post): void {
	$positionen = cmx_load_positionen($post->ID);
	$anz = cmx_load_anzahlungen_summe($post->ID);
	$beleg_type = '';
	if (function_exists(__NAMESPACE__ . '\\cmx_get_beleg_type')) {
		[, $beleg_type] = cmx_get_beleg_type($post);
	}
	$beleg_type = strtolower((string)$beleg_type);
	$is_lieferschein = ($beleg_type === 'lieferschein');
	$is_lieferantenrechnung = ($beleg_type === 'lieferantenrechnung');
	$is_gutschrift = ($beleg_type === 'gutschrift');
	$has_positions = cmx_has_positionen_data($positionen);
	$manual_total_raw = (string) get_post_meta($post->ID, '_cmx_beleg_summe_override', true);

	$summe = 0.0;
	foreach ($positionen as $p) {
		$menge  = isset($p['menge'])  ? (float) cmx_norm_decimal((string)$p['menge'])  : 0.0;
		$preis  = isset($p['preis'])  ? (float) cmx_norm_decimal((string)$p['preis'])  : 0.0;
		$rabatt_raw = $p['rabatt'] ?? '';
		$subtotal = $menge * $preis;
		$rabatt   = cmx_parse_rabatt($subtotal, $rabatt_raw);
		$summe   += cmx_round_5rp($subtotal - $rabatt);
	}

	wp_nonce_field('cmx_beleg_summe_save', 'cmx_beleg_summe_nonce');

	// Anzeige im CH-Format (1'234,56)
	echo '<div id="cmx-beleg-summe-wrap" data-beleg-type="'.esc_attr($beleg_type).'" style="font-size:x-large; line-height:1.6; padding:6px 4px; text-align:center;">';
	echo '<strong>';
	if ($is_lieferantenrechnung || $is_gutschrift) {
		$display = $manual_total_raw !== '' ? $manual_total_raw : number_format($summe, 2, ',', "'");
		$manual_attr = $manual_total_raw !== '' ? ' data-manual="1"' : '';
		$readonly = $has_positions ? ' readonly' : '';
		echo '<input type="text" id="cmx-beleg-summe-input" name="cmx_beleg_summe_override" value="'.esc_attr($display).'" style="width:140px;text-align:center;font-weight:600;"'.$manual_attr.$readonly.'>';
	} else {
		echo '<span id="cmx-beleg-summe-value" data-currency="">' .
			esc_html(number_format($summe, 2, ',', "'")) .
			'</span>';
	}
	echo '</strong>';
	if (!empty($anz['count'])) {
		$anz_sum = (float)$anz['summe'];
		$offen = $summe - $anz_sum;
		echo '<div style="font-size:small; margin-top:6px;">Anzahlungen: <strong>' .
			esc_html(number_format($anz_sum, 2, ',', "'")) .
			'</strong></div>';
		echo '<div style="font-size:small; color:#b32d2e;">Offener Betrag: <strong>' .
			esc_html(number_format($offen, 2, ',', "'")) .
			'</strong></div>';
	}
	echo '</div>';
}

/** ===== Inline-JS nur auf dem Belege-Editor ===== */
\add_action('admin_print_footer_scripts', function () {
	$screen = function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'belege') return;
	?>
	<script>
	(function(){
		const sumWrap = document.getElementById('cmx-beleg-summe-wrap');
		const sumInput = document.getElementById('cmx-beleg-summe-input');
		const sumType = sumWrap ? sumWrap.getAttribute('data-beleg-type') : '';
		const isManualType = sumType === 'lieferantenrechnung' || sumType === 'gutschrift';

		function toNumber(v){
			if (typeof v !== 'string') v = (v ?? '').toString();
			let s = v.replace(/\s+/g,'').replace(/'/g,'');
			const hasC = s.indexOf(',')>-1, hasD = s.indexOf('.')>-1;
			if (hasC && hasD){
				if (s.lastIndexOf(',') > s.lastIndexOf('.')) {
					s = s.replace(/\./g,'').replace(/,/g,'.');
				} else {
					s = s.replace(/,/g,'');
				}
			} else {
				s = s.replace(/,/g,'.');
			}
			const n = parseFloat(s);
			return isNaN(n) ? 0 : n;
		}
		function formatCH(n){
			const parts = (Number(n)||0).toFixed(2).split('.');
			let i = parts[0], r = '';
			while (i.length > 3) { r = "'" + i.slice(-3) + r; i = i.slice(0, -3); }
			return i + r + ',' + parts[1];
		}
		function round5(n){ return Math.round((Number(n)||0) * 20) / 20; }
		function parseRabatt(subtotal, raw){
			if (!raw) return 0;
			const base = Math.abs(subtotal);
			let txt = String(raw).trim().toLowerCase();
			let rab = 0;
			if (txt.endsWith('%')){
				const p = toNumber(txt.slice(0, -1));
				rab = p>0 ? base * (p/100) : 0;
			} else {
				txt = txt.replace(/chf|fr\.?/g,'');
				rab = Math.max(0, toNumber(txt));
			}
			if (rab > base) rab = base;
			return Math.sign(subtotal) * rab;
		}

		function findPositionenRoot(){
			const headers = Array.from(document.querySelectorAll('.postbox .hndle, .postbox .postbox-header h2'));
			for (const h of headers){
				const t = (h.textContent||'').trim().toLowerCase();
				if (t.indexOf('positionen') !== -1) return h.closest('.postbox');
			}
			const tables = Array.from(document.querySelectorAll('table'));
			for (const tbl of tables){
				const headTxt = (tbl.tHead ? tbl.tHead.textContent : tbl.textContent || '').toLowerCase();
				if (headTxt.indexOf('menge') !== -1 && headTxt.indexOf('einzelpreis') !== -1) return tbl.closest('.postbox') || tbl;
			}
			return null;
		}
		function findRows(root){
			if (!root) return [];
			let rows = Array.from(root.querySelectorAll('tbody tr'));
			if (rows.length) return rows;
			rows = Array.from(root.querySelectorAll('.cmx-position-row, .position-row, .item-row'));
			return rows.length ? rows : Array.from(root.querySelectorAll('tr, .row'));
		}
		function pickField(row, keys){
			let sel = keys.map(k => `input[name*="[${k}]"], input[name$="[${k}]"], input[name*="${k}"]`).join(',');
			let el = row.querySelector(sel);
			if (el) return el;
			el = Array.from(row.querySelectorAll('input[placeholder]')).find(i => {
				const ph = (i.getAttribute('placeholder')||'').toLowerCase();
				return keys.some(k => ph.indexOf(k) !== -1);
			});
			if (el) return el;
			const table = row.closest('table');
			if (table && table.tHead){
				const heads = Array.from(table.tHead.querySelectorAll('th')).map(th => (th.textContent||'').trim().toLowerCase());
				const idx = heads.findIndex(h => keys.some(k => h.indexOf(k) !== -1));
				if (idx >= 0) {
					const cell = row.children[idx];
					if (cell) {
						el = cell.querySelector('input');
						if (el) return el;
					}
				}
			}
			return null;
		}
		function pickGesamtCell(row){
			let el = row.querySelector('[data-col="gesamt"], .cmx-pos-gesamt, .pos-gesamt, .gesamt, td:last-child span, td:last-child div, td:last-child input[readonly]');
			if (el) return el;
			const table = row.closest('table');
			if (table && table.tHead){
				const heads = Array.from(table.tHead.querySelectorAll('th')).map(th => (th.textContent||'').trim().toLowerCase());
				const idx = heads.findIndex(h => h.indexOf('gesamt') !== -1 || h.indexOf('summe') !== -1);
				if (idx >= 0) return row.children[idx] || null;
			}
			return null;
		}
		function hasPositions(){
			const root = findPositionenRoot();
			if (!root) return false;
			const rows = findRows(root);
			for (const row of rows){
				const artikel = row.querySelector('.cmx-artikel-autocomplete');
				const artikelId = row.querySelector('.cmx-artikel-id');
				if (artikelId && artikelId.value && parseInt(artikelId.value,10) > 0) return true;
				if (artikel && artikel.value.trim() !== '') return true;
				const m = pickField(row, ['menge','qty','anzahl']);
				const p = pickField(row, ['einzelpreis','preis','price','betrag']);
				const r = pickField(row, ['rabatt','discount']);
				if (m && m.value.trim() !== '') return true;
				if (p && p.value.trim() !== '') return true;
				if (r && r.value.trim() !== '') return true;
			}
			return false;
		}

		function calcRow(row){
			const menge  = pickField(row, ['menge','qty','anzahl']);
			const preis  = pickField(row, ['einzelpreis','preis','price','betrag']);
			const rabatt = pickField(row, ['rabatt','discount']);

			const m = menge ? toNumber(menge.value) : 0;
			const p = preis ? toNumber(preis.value) : 0;
			const sub = m * p;
			const r = rabatt ? parseRabatt(sub, rabatt.value) : 0;

			return round5(sub - r);
		}
		function sumAll(){
			const out = document.getElementById('cmx-beleg-summe-value');
			if (!out && !sumInput) return 0;
			if (isManualType && sumInput) {
				const manual = sumInput.dataset.manual === '1';
				const hasPos = hasPositions();
				sumInput.readOnly = hasPos;
				sumInput.style.opacity = hasPos ? '0.6' : '1';
				if (hasPos) {
					sumInput.dataset.manual = '';
				}
				if (manual) {
					return toNumber(sumInput.value || '0');
				}
			}
			const root = findPositionenRoot();
			if (!root) return 0;

			const rows = findRows(root);
			let total = 0;

			if (rows.length) {
				let usedDirect = false;
				for (const row of rows){
					const m = pickField(row, ['menge','qty','anzahl']);
					const p = pickField(row, ['einzelpreis','preis','price','betrag']);
					if (m || p) { usedDirect = true; break; }
				}
				if (usedDirect){
					for (const row of rows) total += calcRow(row);
				} else {
					for (const row of rows){
						const cell = pickGesamtCell(row);
						if (!cell) continue;
						const val = cell.tagName === 'INPUT' ? cell.value : (cell.textContent||'');
						total += toNumber(val);
					}
					total = round5(total);
				}
			}

			if (out) out.textContent = formatCH(total);
			if (sumInput && !sumInput.dataset.manual) {
				sumInput.value = formatCH(total);
			}
			return total;
		}

		function bindLive(){
			const root = findPositionenRoot() || document;
			const handler = function(e){
				const t = e.target;
				if (!t || !t.getAttribute) return;
				const name = t.getAttribute('name') || '';
				if (/\b(menge|qty|anzahl|preis|einzelpreis|price|betrag|rabatt|discount)\b/i.test(name)) sumAll();
			};
			root.addEventListener('input', handler, true);
			root.addEventListener('change', handler, true);
			if (sumInput) {
				sumInput.addEventListener('input', function(){
					sumInput.dataset.manual = '1';
				});
			}

			const mo = new MutationObserver(function(){ sumAll(); });
			mo.observe(root, { childList:true, subtree:true, attributes:true, characterData:false });
		}

		document.addEventListener('DOMContentLoaded', function(){
			bindLive();
			setTimeout(sumAll, 30);
			setTimeout(sumAll, 200);
		});
	})();
	</script>
	<?php
});

add_action('save_post_belege', function ($post_id) {
	if (!isset($_POST['cmx_beleg_summe_nonce']) || !\wp_verify_nonce($_POST['cmx_beleg_summe_nonce'], 'cmx_beleg_summe_save')) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!current_user_can('edit_post', $post_id)) return;

	$beleg_type = '';
	if (function_exists(__NAMESPACE__ . '\\cmx_get_beleg_type')) {
		[, $beleg_type] = cmx_get_beleg_type(get_post($post_id));
	}
	$beleg_type = strtolower((string)$beleg_type);

	if (in_array($beleg_type, ['lieferantenrechnung', 'gutschrift'], true)) {
		$positionen = cmx_load_positionen($post_id);
		$has_positions = cmx_has_positionen_data($positionen);
		if ($has_positions) {
			delete_post_meta($post_id, '_cmx_beleg_summe_override');
			return;
		}
		$raw = isset($_POST['cmx_beleg_summe_override']) ? (string)\wp_unslash($_POST['cmx_beleg_summe_override']) : '';
		$raw = trim($raw);
		if ($raw === '') {
			delete_post_meta($post_id, '_cmx_beleg_summe_override');
			return;
		}
		$val = (float) cmx_norm_decimal($raw);
		update_post_meta($post_id, '_cmx_beleg_summe_override', number_format($val, 2, '.', ''));
	} else {
		delete_post_meta($post_id, '_cmx_beleg_summe_override');
	}
});
