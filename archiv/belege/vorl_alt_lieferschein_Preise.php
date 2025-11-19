<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

/**
 * Erwartete Meta-Struktur (_cmx_beleg_positionen):
 * - Array von Positionen (ACF-Repeater oder normales Array)
 * - Keys (beliebig, wir behandeln defensiv):
 *   artikel_id | artikel | artikel_titel | titel
 *   sku | artikel_nr
 *   beschreibung | desc
 *   menge | qty
 *   einheit | unit   (Default: Stk)
 *   bemerkung | note
 */

/** -------- Helpers: Normalisierung & Feldzugriff -------- */

if (!function_exists(__NAMESPACE__.'\\cmx_pos_raw')) {
	function cmx_pos_raw(int $post_id) {
		// get_post_meta($id, key, true) liefert bereits unserialized
		$raw = get_post_meta($post_id, '_cmx_beleg_positionen', true);
		if (empty($raw)) {
			// Fallback: manchmal wurden mehrere Einträge einzeln gespeichert
			$raw = get_post_meta($post_id, '_cmx_beleg_positionen', false);
		}
		// JSON-String?
		if (is_string($raw)) {
			$maybe = json_decode($raw, true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($maybe)) {
				return $maybe;
			}
		}
		return $raw;
	}
}

if (!function_exists(__NAMESPACE__.'\\cmx_pos_list')) {
	function cmx_pos_list($raw): array {
		// ACF-Repeater kommt oft als Array mit numerischen Keys → ok
		if (is_array($raw)) {
			// Manche speichern als ['rows' => [...]]
			if (isset($raw['rows']) && is_array($raw['rows'])) return array_values($raw['rows']);
			// Bereits eine Positionsliste?
			if (array_is_list($raw)) return $raw;
			// Edge-Case: assoziatives Array mit Unter-Arrays
			return array_values(array_filter($raw, 'is_array'));
		}
		return [];
	}
}

if (!function_exists(__NAMESPACE__.'\\cmx_field')) {
	function cmx_field(array $pos, array $keys, $default = '') {
		foreach ($keys as $k) {
			if (isset($pos[$k]) && $pos[$k] !== '') return $pos[$k];
		}
		return $default;
	}
}

if (!function_exists(__NAMESPACE__.'\\cmx_num')) {
	function cmx_num($v, int $dec = 2): string {
		// deutsche Eingaben tolerant: "1 234,50" → 1234.50
		$s = is_string($v) ? $v : (string)$v;
		$s = str_replace([' ', "'"], '', $s);
		$s = str_replace([','], ['.'], $s);
		$f = is_numeric($s) ? (float)$s : 0.0;
		return number_format($f, $dec, ',', "'");
	}
}

if (!function_exists(__NAMESPACE__.'\\cmx_get_artikel_title_sku')) {
	function cmx_get_artikel_title_sku($artikel_id): array {
		$artikel_id = (int)$artikel_id;
		if ($artikel_id <= 0) return ['', ''];
		$title = get_the_title($artikel_id) ?: '';
		// SKU – je nach System: _sku oder eigenes Meta (z. B. _cmx_artikel_nr)
		$sku = get_post_meta($artikel_id, '_cmx_artikel_nr', true);
		if ($sku === '') $sku = get_post_meta($artikel_id, '_sku', true);
		return [$title, (string)$sku];
	}
}

/** -------- Daten laden -------- */

$post_id = (int)($getPost['post_id'] ?? 0);
$raw      = cmx_pos_raw($post_id);
$positionen = cmx_pos_list($raw);

// Logo/Store/Bank/… kommen aus umgebenden Variablen ($logo, $store_address, …)
?>
<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="UTF-8">
	<title><?php echo ucfirst(esc_html($getPost['beleg_type'] ?? 'Lieferschein')); ?></title>
	<style>
		body { font-family: sans-serif; font-size: 12px; color: #333; }
		h1 { font-size: 18px; }
		table { width: 100%; border-collapse: collapse; margin-top: 20px; }
		th, td { padding: 6px; text-align: left; }
		td.brief { color:grey !important; font-style: italic; }
		table th { border-bottom: 1px solid #000 !important; }
		.position { border-bottom: .5px groove #000 !important; }
		.gesamt { border-bottom: 2px double #000 !important; }
		.total { font-weight: bold; }
		a, a:hover, a:visited, a:active, a:focus { color:grey !important; text-decoration: none; }
		@page { margin: 20px 30px 80px 50px; }
		table, td, th { border: none; }
		.footer { position: fixed; bottom: -60px; left: 0; right: 0; height: 50px; text-align: center; font-size: 12px; }
		.footer:after { content: "Seite " counter(page) " von " counter(pages); }
		.text-right { text-align: right; }
		.text-center { text-align: center; }
	</style>
</head>
<body>

<table>
	<tr>
		<td class="text-right">
			<a href="https://misbuero.ch/">
				<img width="70" src="<?php echo esc_url($logo); ?>" alt="Logo">
			</a>
		</td>
	</tr>
</table>

<h1><?php echo cmx_sani_key($getPost['beleg_type'] ?? 'Lieferschein'); ?> <?php echo esc_html($getPost['beleg_nr'] ?? ''); ?></h1>
<p><i><?php echo esc_html($getPost['beleg_nr'] ?? ''); ?></i></p>

<table>
	<thead>
	<tr>
		<th style="width:22%;">Artikel</th>
		<th>Beschreibung</th>
		<th style="width:12%; text-align:right;">Menge</th>
		<th style="width:12%; text-align:right;">Einheit</th>
		<th style="width:22%; text-align:right;">Bemerkung</th>
	</tr>
	</thead>
	<tbody>
	<?php if (empty($positionen)) : ?>
		<tr class="position">
			<td colspan="5" class="text-center" style="color:#777;"><i>Keine Positionen erfasst.</i></td>
		</tr>
	<?php else : ?>
		<?php foreach ($positionen as $pos) :
			// Artikel-Titel & SKU ermitteln
			$artikel_id   = (int) cmx_field($pos, ['artikel_id', 'artikel'], 0);
			list($artikel_title_by_id, $artikel_sku_by_id) = cmx_get_artikel_title_sku($artikel_id);

			$artikel_titel = cmx_field($pos, ['artikel_titel','titel'], $artikel_title_by_id);
			$sku           = cmx_field($pos, ['sku','artikel_nr'], $artikel_sku_by_id);

			$beschreibung  = cmx_field($pos, ['beschreibung','desc'], '');
			$menge_raw     = cmx_field($pos, ['menge','qty'], 0);
			$einheit       = cmx_field($pos, ['einheit','unit'], 'Stk');
			$bemerkung     = cmx_field($pos, ['bemerkung','note'], '');

			$menge_fmt     = cmx_num($menge_raw, 2);
			$artikel_cell  = trim(($sku ? '['.esc_html($sku).'] ' : '').esc_html($artikel_titel ?: '—'));
		?>
		<tr class="position">
			<td><?php echo $artikel_cell !== '' ? $artikel_cell : '—'; ?></td>
			<td><?php echo nl2br(esc_html($beschreibung)); ?></td>
			<td style="text-align:right;"><?php echo esc_html($menge_fmt); ?></td>
			<td style="text-align:right;"><?php echo esc_html($einheit); ?></td>
			<td style="text-align:right;"><?php echo nl2br(esc_html($bemerkung)); ?></td>
		</tr>
		<?php endforeach; ?>
	<?php endif; ?>
	</tbody>
</table>

<div class="footer">
	<table width="100%">
	<tr>
		<td class="brief" style="text-align:left;">
			<b>Empfänger</b><br>
			<?php echo esc_html($store_address['company'] ?? ''); ?><br>
			<?php echo esc_html($store_address['strasse'] ?? ''); ?><br>
			<?php echo esc_html(($store_address['land'] ?? 'CH').'-'.($store_address['plz'] ?? '').' '.($store_address['ort'] ?? '')); ?><br>
		</td>
		<td class="brief text-center">
			<b>Bank</b><br>
			<?php echo esc_html($account_details[0]['bank_name'] ?? ''); ?><br>
			<?php echo esc_html($account_details[0]['iban'] ?? ''); ?><br>
			<?php echo esc_html($account_details[0]['bic'] ?? ''); ?>
		</td>
		<td class="brief" style="text-align:right;">
			<b>Kontakt</b><br>
			<a href="tel:0443418000">044 341 80 00</a><br>
			<a href="mailto:invoice@cloudmeister.ch">invoice@cloudmeister.ch</a><br>
			<a href="mailto:support@cloudmeister.ch">support@cloudmeister.ch</a>
		</td>
	</tr>
	</table>
</div>

</body>
</html>
