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

/** ===== Render der Metabox (mit Serversumme & JS-Target) ===== */
function cmx_beleg_summe_box_render(\WP_Post $post): void {
	$positionen = cmx_load_positionen($post->ID);

	$summe = 0.0;
	foreach ($positionen as $p) {
		$menge  = isset($p['menge'])  ? (float) cmx_norm_decimal((string)$p['menge'])  : 0.0;
		$preis  = isset($p['preis'])  ? (float) cmx_norm_decimal((string)$p['preis'])  : 0.0;
		$rabatt_raw = $p['rabatt'] ?? '';
		$subtotal = $menge * $preis;
		$rabatt   = cmx_parse_rabatt($subtotal, $rabatt_raw);
		$summe   += cmx_round_5rp($subtotal - $rabatt);
	}

	// Anzeige im CH-Format (1'234,56)
	echo '<div id="cmx-beleg-summe-wrap" style="font-size:x-large; line-height:1.6; padding:6px 4px; text-align:center;">';
	echo '<strong><span id="cmx-beleg-summe-value" data-currency="">' .
		esc_html(number_format($summe, 2, ',', "'")) .
		'</span></strong>';
	echo '</div>';
}

/** ===== Inline-JS nur auf dem Belege-Editor ===== */
\add_action('admin_print_footer_scripts', function () {
	$screen = function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'belege') return;
	?>
	<script>
	(function(){
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
			if (!out) return 0;
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

			out.textContent = formatCH(total);
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
