<?php
/**
 * Datei: cmx-beleg-rechnung.php
 * Zweck: Typische Rechnungs-HTML-Ausgabe für CPT "belege" (schweiz-optimiert, PDF-tauglich)
 * Nutzung:
 *   1) Als Shortcode: [cmx_beleg_rechnung id="123"]  (id = Beleg-Post-ID; fehlt id -> aktueller Post)
 *   2) Als Template-Loader via GET: /?cmx_rechnung=ID
 *
 * Hinweise:
 *   - Keine externen Abhängigkeiten. QR-Sektion zeigt Bild/Markup aus Meta, wenn vorhanden.
 *   - Robust gegenüber fehlenden Metas; formatiert Zahlen im CH-Stil.
 */

namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

/* =========================
 * KONFIG / META-KEYS
 * ========================= */
if (!defined(__NAMESPACE__.'\\CMX_BELEG_CPT'))               define(__NAMESPACE__.'\\CMX_BELEG_CPT',               'belege');
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_NR'))           define(__NAMESPACE__.'\\CMX_BELEG_META_NR',           '_cmx_beleg_nummer');
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_DATUM'))        define(__NAMESPACE__.'\\CMX_BELEG_META_DATUM',        '_cmx_beleg_datum');           // Y-m-d oder Timestamp
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_FAELLIG'))      define(__NAMESPACE__.'\\CMX_BELEG_META_FAELLIG',      '_cmx_beleg_faellig');         // Y-m-d
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT'))      define(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT',      '_cmx_beleg_kontakt_id');      // Post ID CPT "kontakte"
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_POS'))          define(__NAMESPACE__.'\\CMX_BELEG_META_POS',          '_cmx_beleg_positionen');      // array|json
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_WAEHRUNG'))     define(__NAMESPACE__.'\\CMX_BELEG_META_WAEHRUNG',     '_cmx_beleg_waehrung');        // 'CHF' (Default)
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_MWST_SATZ'))    define(__NAMESPACE__.'\\CMX_BELEG_META_MWST_SATZ',    '_cmx_beleg_mwst_satz');       // numerisch, z.B. 8.1
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_FUSS_GLOBAL'))  define(__NAMESPACE__.'\\CMX_BELEG_META_FUSS_GLOBAL',  '_cmx_beleg_fuss_text');       // globaler Rechnungsfuss
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_QR_HTML'))      define(__NAMESPACE__.'\\CMX_BELEG_META_QR_HTML',      '_cmx_beleg_qr_html');         // optional: fertiges <img> oder <svg>
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_REFERENZ'))     define(__NAMESPACE__.'\\CMX_BELEG_META_REFERENZ',     '_cmx_beleg_referenz');        // Referenz/Bestellnummer

/* Kontakt-Metas (CPT "kontakte") – bei Dir ggf. anpassen */
if (!defined(__NAMESPACE__.'\\CMX_KONTAKT_META_FIRMA'))      define(__NAMESPACE__.'\\CMX_KONTAKT_META_FIRMA',      '_cmx_kontakt_firma');
if (!defined(__NAMESPACE__.'\\CMX_KONTAKT_META_VORNAME'))    define(__NAMESPACE__.'\\CMX_KONTAKT_META_VORNAME',    '_cmx_kontakt_vorname');
if (!defined(__NAMESPACE__.'\\CMX_KONTAKT_META_NACHNAME'))   define(__NAMESPACE__.'\\CMX_KONTAKT_META_NACHNAME',   '_cmx_kontakt_nachname');
if (!defined(__NAMESPACE__.'\\CMX_KONTAKT_META_STRASSE'))    define(__NAMESPACE__.'\\CMX_KONTAKT_META_STRASSE',    '_cmx_kontakt_strasse');
if (!defined(__NAMESPACE__.'\\CMX_KONTAKT_META_PLZ'))        define(__NAMESPACE__.'\\CMX_KONTAKT_META_PLZ',        '_cmx_kontakt_plz');
if (!defined(__NAMESPACE__.'\\CMX_KONTAKT_META_ORT'))        define(__NAMESPACE__.'\\CMX_KONTAKT_META_ORT',        '_cmx_kontakt_ort');
if (!defined(__NAMESPACE__.'\\CMX_KONTAKT_META_LAND'))       define(__NAMESPACE__.'\\CMX_KONTAKT_META_LAND',       '_cmx_kontakt_land');

/* =========================
 * HELFER
 * ========================= */
function cmx_ch_date($val): string {
	// erwartet Y-m-d oder Timestamp
	if (empty($val)) return '';
	if (is_numeric($val)) {
		return date_i18n('d.m.Y', (int)$val);
	}
	$ts = strtotime($val);
	return $ts ? date_i18n('d.m.Y', $ts) : '';
}

function cmx_money($number, $currency = 'CHF'): string {
	$n = is_numeric($number) ? (float)$number : 0.0;
	$formatted = number_format($n, 2, ',', ' ');
	return trim($formatted . ' ' . $currency);
}

function cmx_get($post_id, $key, $default = '') {
	$val = get_post_meta($post_id, $key, true);
	return $val === '' || $val === null ? $default : $val;
}

function cmx_positions_from_meta($post_id): array {
	$raw = get_post_meta($post_id, CMX_BELEG_META_POS, true);
	if (empty($raw)) return [];
	if (is_array($raw)) return $raw;
	$decoded = json_decode($raw, true);
	return is_array($decoded) ? $decoded : [];
}

function cmx_sender_info(): array {
	// Standard-Absender aus WP-Optionen; via Filter erweiterbar
	$sender = [
		'name'   => get_bloginfo('name'),
		'addr1'  => get_option('woocommerce_store_address', ''),
		'addr2'  => trim(get_option('woocommerce_store_postcode', '').' '.get_option('woocommerce_store_city', '')),
		'country'=> get_option('woocommerce_default_country', 'CH'),
		'email'  => get_option('admin_email'),
		'phone'  => '',
		'iban'   => '',
		'uid'    => '', // MWST/UID
		'logo'   => '', // URL zum Logo, via Filter setzen
	];
	/** @var array $sender */
	$sender = apply_filters('cmx_rechnung_sender_info', $sender);
	return $sender;
}

function cmx_contact_info($kontakt_id): array {
	if (!$kontakt_id) return [];
	$firma   = get_post_meta($kontakt_id, CMX_KONTAKT_META_FIRMA, true);
	$vor     = get_post_meta($kontakt_id, CMX_KONTAKT_META_VORNAME, true);
	$nach    = get_post_meta($kontakt_id, CMX_KONTAKT_META_NACHNAME, true);
	$str     = get_post_meta($kontakt_id, CMX_KONTAKT_META_STRASSE, true);
	$plz     = get_post_meta($kontakt_id, CMX_KONTAKT_META_PLZ, true);
	$ort     = get_post_meta($kontakt_id, CMX_KONTAKT_META_ORT, true);
	$land    = get_post_meta($kontakt_id, CMX_KONTAKT_META_LAND, true);

	return [
		'title' => get_the_title($kontakt_id),
		'firma' => $firma,
		'name'  => trim($vor.' '.$nach),
		'str'   => $str,
		'plz'   => $plz,
		'ort'   => $ort,
		'land'  => $land ?: 'CH',
	];
}

function cmx_calc_totals(array $pos, $default_vat): array {
	$netto = 0.0; $vat_total = 0.0;
	foreach ($pos as $p) {
		$qty   = isset($p['menge']) ? (float)str_replace(',', '.', (string)$p['menge']) : 1.0;
		$price = isset($p['einzelpreis']) ? (float)str_replace(',', '.', (string)$p['einzelpreis']) : 0.0;
		$rab   = isset($p['rabatt']) ? (float)str_replace(',', '.', (string)$p['rabatt']) : 0.0; // in %
		$vat   = isset($p['mwst']) ? (float)$p['mwst'] : (float)$default_vat;

		$line  = max($price * $qty * (1 - $rab/100), 0.0);
		$netto += $line;
		$vat_total += $line * ($vat/100);
	}
	return [
		'netto' => round($netto, 2),
		'mwst'  => round($vat_total, 2),
		'brutto'=> round($netto + $vat_total, 2),
	];
}

/* =========================
 * RENDER
 * ========================= */
function cmx_render_beleg_html($beleg_id): string {
	if (!$beleg_id || get_post_type($beleg_id) !== CMX_BELEG_CPT) {
		return '<div class="cmx-rechnung-error">Beleg nicht gefunden.</div>';
	}

	$nr        = cmx_get($beleg_id, CMX_BELEG_META_NR, get_the_title($beleg_id));
	$datum     = cmx_ch_date(cmx_get($beleg_id, CMX_BELEG_META_DATUM, get_post_time('U', true, $beleg_id)));
	$faellig   = cmx_ch_date(cmx_get($beleg_id, CMX_BELEG_META_FAELLIG, ''));
	$kontaktId = (int) cmx_get($beleg_id, CMX_BELEG_META_KONTAKT, 0);
	$ref       = cmx_get($beleg_id, CMX_BELEG_META_REFERENZ, '');
	$currency  = cmx_get($beleg_id, CMX_BELEG_META_WAEHRUNG, 'CHF');
	$mwstSatz  = (float) cmx_get($beleg_id, CMX_BELEG_META_MWST_SATZ, 8.1);
	$fuss      = cmx_get($beleg_id, CMX_BELEG_META_FUSS_GLOBAL, '');
	$qrHtml    = cmx_get($beleg_id, CMX_BELEG_META_QR_HTML, '');

	$pos       = cmx_positions_from_meta($beleg_id);
	$tot       = cmx_calc_totals($pos, $mwstSatz);

	$sender    = cmx_sender_info();
	$empf      = cmx_contact_info($kontaktId);

	ob_start(); ?>
	<style>
		.cmx-invoice { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color:#111; }
		.cmx-invoice .row{ display:flex; justify-content:space-between; gap:24px; }
		.cmx-invoice .muted{ color:#666; font-size:12px; }
		.cmx-invoice h1{ font-size:20px; margin:0 0 6px; }
		.cmx-invoice h2{ font-size:16px; margin:16px 0 6px; }
		.cmx-invoice .box{ padding:10px 12px; border:1px solid #ddd; border-radius:6px; }
		.cmx-invoice .meta{ margin:10px 0; display:grid; grid-template-columns: 160px 1fr; gap:6px 12px; font-size:13px; }
		.cmx-invoice table{ width:100%; border-collapse:collapse; margin-top:12px; }
		.cmx-invoice th, .cmx-invoice td{ padding:8px; border-bottom:1px solid #eee; text-align:left; vertical-align:top; }
		.cmx-invoice th{ background:#fafafa; font-weight:600; }
		.cmx-invoice tfoot td{ border-top:1px solid #ddd; }
		.cmx-invoice .totals td{ text-align:right; }
		.cmx-invoice .right{ text-align:right; }
		.cmx-invoice .qr-wrap{ display:flex; gap:16px; align-items:flex-start; margin-top:12px; }
		@media print{
			.cmx-invoice .no-print{ display:none !important; }
			body{ margin:0; }
		}
	</style>

	<div class="cmx-invoice">
		<div class="row">
			<div>
				<?php if (!empty($sender['logo'])): ?>
					<div style="margin-bottom:8px;"><img src="<?php echo esc_url($sender['logo']); ?>" alt="<?php echo esc_attr($sender['name']); ?>" style="max-height:60px;"></div>
				<?php endif; ?>
				<strong><?php echo esc_html($sender['name']); ?></strong><br>
				<?php echo esc_html($sender['addr1']); ?><br>
				<?php echo esc_html($sender['addr2']); ?><?php if(!empty($sender['country'])) echo ' · '.esc_html($sender['country']); ?><br>
				<?php if(!empty($sender['email'])): ?><span class="muted"><?php echo esc_html($sender['email']); ?></span><?php endif; ?>
				<?php if(!empty($sender['phone'])): ?> · <span class="muted"><?php echo esc_html($sender['phone']); ?></span><?php endif; ?>
			</div>
			<div class="box" style="min-width:280px;">
				<h1>Rechnung</h1>
				<div class="meta">
					<div>Rechnungs-Nr.</div><div><strong><?php echo esc_html($nr); ?></strong></div>
					<div>Datum</div><div><?php echo esc_html($datum); ?></div>
					<?php if ($faellig): ?><div>Fälligkeit</div><div><?php echo esc_html($faellig); ?></div><?php endif; ?>
					<?php if ($ref): ?><div>Referenz</div><div><?php echo esc_html($ref); ?></div><?php endif; ?>
					<div>Währung</div><div><?php echo esc_html($currency); ?></div>
					<div>MWST</div><div><?php echo esc_html(number_format($mwstSatz, 2, ',', '')); ?> %</div>
				</div>
			</div>
		</div>

		<div class="row" style="margin-top:16px;">
			<div class="box" style="flex:1;">
				<h2>Rechnung an</h2>
				<?php if (!empty($empf)): ?>
					<div>
						<?php if (!empty($empf['firma'])): ?><strong><?php echo esc_html($empf['firma']); ?></strong><br><?php endif; ?>
						<?php if (!empty($empf['name'])): ?><?php echo esc_html($empf['name']); ?><br><?php endif; ?>
						<?php echo esc_html($empf['str']); ?><br>
						<?php echo esc_html(trim(($empf['plz'] ?? '').' '.($empf['ort'] ?? ''))); ?><br>
						<?php echo esc_html($empf['land']); ?>
					</div>
				<?php else: ?>
					<div class="muted">Kein Kontakt verknüpft.</div>
				<?php endif; ?>
			</div>

			<div class="box" style="flex:1;">
				<h2>Zahlungsinformation</h2>
				<div class="muted">
					<?php if(!empty($sender['iban'])): ?>IBAN: <?php echo esc_html($sender['iban']); ?><br><?php endif; ?>
					<?php if(!empty($sender['uid'])): ?>UID/MWST: <?php echo esc_html($sender['uid']); ?><br><?php endif; ?>
					Bitte unter Angabe der Rechnungs-Nr. innert Frist begleichen.
				</div>
				<?php if (!empty($qrHtml)): ?>
					<div class="qr-wrap">
						<div><?php echo wp_kses_post($qrHtml); ?></div>
						<div class="muted">Schweizer QR-Zahlteil</div>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<table>
			<thead>
				<tr>
					<th style="width:18%;">Artikel-Nr</th>
					<th>Bezeichnung</th>
					<th class="right" style="width:10%;">Menge</th>
					<th class="right" style="width:14%;">Einzelpreis</th>
					<th class="right" style="width:10%;">Rabatt %</th>
					<th class="right" style="width:16%;">Zwischensumme</th>
				</tr>
			</thead>
			<tbody>
				<?php if ($pos): foreach ($pos as $p):
					$nrP    = isset($p['artikel_nr']) ? (string)$p['artikel_nr'] : '';
					$bez    = isset($p['titel']) ? (string)$p['titel'] : (isset($p['bezeichnung']) ? (string)$p['bezeichnung'] : '');
					$qty    = isset($p['menge']) ? (float)str_replace(',', '.', (string)$p['menge']) : 1.0;
					$price  = isset($p['einzelpreis']) ? (float)str_replace(',', '.', (string)$p['einzelpreis']) : 0.0;
					$rab    = isset($p['rabatt']) ? (float)str_replace(',', '.', (string)$p['rabatt']) : 0.0;
					$line   = max($price * $qty * (1 - $rab/100), 0.0);
				?>
				<tr>
					<td><?php echo esc_html($nrP); ?></td>
					<td><?php echo esc_html($bez); ?></td>
					<td class="right"><?php echo esc_html(number_format($qty, 2, ',', '')); ?></td>
					<td class="right"><?php echo esc_html(cmx_money($price, $currency)); ?></td>
					<td class="right"><?php echo esc_html(number_format($rab, 2, ',', '')); ?></td>
					<td class="right"><?php echo esc_html(cmx_money($line, $currency)); ?></td>
				</tr>
				<?php endforeach; else: ?>
				<tr><td colspan="6" class="muted">Keine Positionen vorhanden.</td></tr>
				<?php endif; ?>
			</tbody>
			<tfoot class="totals">
				<tr>
					<td colspan="5" class="right">Zwischensumme (exkl. MWST)</td>
					<td class="right"><?php echo esc_html(cmx_money($tot['netto'], $currency)); ?></td>
				</tr>
				<tr>
					<td colspan="5" class="right">MWST <?php echo esc_html(number_format($mwstSatz, 2, ',', '')); ?> %</td>
					<td class="right"><?php echo esc_html(cmx_money($tot['mwst'], $currency)); ?></td>
				</tr>
				<tr>
					<td colspan="5" class="right"><strong>Total</strong></td>
					<td class="right"><strong><?php echo esc_html(cmx_money($tot['brutto'], $currency)); ?></strong></td>
				</tr>
			</tfoot>
		</table>

		<?php if (!empty($fuss)): ?>
			<div style="margin-top:16px" class="muted"><?php echo wp_kses_post(nl2br($fuss)); ?></div>
		<?php endif; ?>

		<div class="no-print" style="margin-top:16px; display:flex; gap:8px;">
			<button onclick="window.print()">Drucken</button>
		</div>
	</div>
	<?php
	return (string)ob_get_clean();
}

/* =========================
 * SHORTCODE
 * ========================= */
add_shortcode('cmx_beleg_rechnung', function($atts){
	$atts = shortcode_atts(['id' => 0], $atts, 'cmx_beleg_rechnung');
	$beleg_id = (int)$atts['id'];
	if (!$beleg_id && is_singular(CMX_BELEG_CPT)) {
		$beleg_id = get_the_ID();
	}
	return cmx_render_beleg_html($beleg_id);
});

/* =========================
 * SIMPLE TEMPLATE-LOADER (?cmx_rechnung=ID)
 * ========================= */
add_action('template_redirect', function(){
	if (!isset($_GET['cmx_rechnung'])) return;
	$beleg_id = (int)sanitize_text_field($_GET['cmx_rechnung']);
	status_header(200);
	nocache_headers();
	header('Content-Type: text/html; charset='.get_bloginfo('charset'));
	echo '<!DOCTYPE html><html><head><meta charset="'.esc_attr(get_bloginfo('charset')).'"><meta name="viewport" content="width=device-width, initial-scale=1">';
	echo '<title>Rechnung '.$beleg_id.'</title>';
	echo '</head><body style="margin:24px;">';
	echo cmx_render_beleg_html($beleg_id);
	echo '</body></html>';
	exit;
});

/* =========================
 * BEISPIEL: SENDER-DATEN via Filter überschreiben
 * (in Deinem MU-Plugin/Theme aktivieren)
 *
 * add_filter('cmx_rechnung_sender_info', function($sender){
 *   $sender['name'] = 'CLOUDMEISTER GmbH';
 *   $sender['addr1'] = 'Dufourstrasse 10';
 *   $sender['addr2'] = '8008 Zürich';
 *   $sender['country'] = 'CH';
 *   $sender['email'] = 'info@cloudmeister.ch';
 *   $sender['phone'] = '+41 44 000 00 00';
 *   $sender['iban']  = 'CHxx xxxx xxxx xxxx xxxx x';
 *   $sender['uid']   = 'CHE-xxx.xxx.xxx MWST';
 *   $sender['logo']  = 'https://example.ch/logo.png';
 *   return $sender;
 * });
 *
 * =========================
 * BEISPIEL: QR-HTML in Meta speichern (SVG/IMG)
 * update_post_meta($beleg_id, '_cmx_beleg_qr_html', '<img src="https://…/qr.png" alt="QR" />');
 * =========================
 */
