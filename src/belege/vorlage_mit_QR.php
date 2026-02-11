<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/*
 * Schweizer Rechnungs-Layout (mit QR)
 * Erwartet $tpl und ggf. $cmx_beleg_adress.
 */

$__fmt_dec  = (string)($tpl['format']['decimal']   ?? '.');
$__fmt_tho  = (string)($tpl['format']['thousands'] ?? "'");
$__fmt_prec = (int)   ($tpl['format']['decimals']  ?? 2);
$__fmt_cur  = (string)($tpl['format']['currency']  ?? 'CHF');

$__fmt_num = function(float $v) use ($__fmt_prec, $__fmt_dec, $__fmt_tho): string {
	return number_format($v, $__fmt_prec, $__fmt_dec, $__fmt_tho);
};

$positions = (array)($tpl['positions'] ?? []);
$has_positions = false;
foreach ($positions as $row) {
	$item = trim((string)($row['item'] ?? ''));
	$qty = (float)($row['qty'] ?? 0);
	$unit_price = (float)($row['unit_price'] ?? 0);
	$line_total = (float)($row['line_total'] ?? 0);
	if ($item !== '' || $qty > 0 || $unit_price > 0 || $line_total > 0) {
		$has_positions = true;
		break;
	}
}
$show_position_index = count($positions) > 1;
$show_sku = false;
$show_discount = false;
$discount_sum = 0.0;
$positions_sum = 0.0;
foreach ($positions as $row) {
	$sku_val = trim((string)($row['article_number'] ?? ''));
	if ($sku_val !== '') $show_sku = true;
	$qty = (float)($row['qty'] ?? 0);
	$unit_price = (float)($row['unit_price'] ?? 0);
	$line_total = (float)($row['line_total'] ?? ($qty * $unit_price));
	$positions_sum += $line_total;
	$line_subtotal = $qty * $unit_price;
	$line_discount = $line_subtotal - $line_total;
	if ($line_discount > 0.0001) {
		$show_discount = true;
		$discount_sum += $line_discount;
	}
}
$opts_general      = (array) get_option('cmx_einstellungen', []);
$is_mwst_pflichtig = !empty($opts_general['mwst_pfl']) || !empty($opts_general['mwstpflichtig']) || !empty($opts_general['mwst_pflichtig']);
$beleg_subject = trim((string)($tpl['document']['subject'] ?? ''));
$beleg_description = trim((string)($tpl['document']['description'] ?? ''));
$opts_belege = (array) get_option('cmx_belege', []);
$beleg_type = strtolower((string)($tpl['document']['type'] ?? 'rechnung'));
$richtung = strtolower((string)($tpl['document']['richtung'] ?? ''));
$is_ausgang = ($richtung === 'ausgang');
$is_lieferschein = ($beleg_type === 'lieferschein');
$is_lieferantenrechnung = ($beleg_type === 'lieferantenrechnung');
$is_gutschrift = ($beleg_type === 'gutschrift');

if ($is_lieferschein) {
	$show_discount = false;
}
$show_unit_price = !$is_lieferschein;
$show_line_total = !$is_lieferschein;

$col_count = ($show_position_index ? 1 : 0)
	+ ($show_sku ? 1 : 0)
	+ 1
	+ 1
	+ ($show_unit_price ? 1 : 0)
	+ ($show_discount ? 1 : 0)
	+ ($show_line_total ? 1 : 0);

$tpl_totals = (array)($tpl['totals'] ?? []);
if (!array_key_exists('subtotal', $tpl_totals) && array_key_exists('net', $tpl_totals)) {
	$tpl_totals['subtotal'] = $tpl_totals['net'];
}
if (!array_key_exists('tax_amount', $tpl_totals) && array_key_exists('tax', $tpl_totals)) {
	$tpl_totals['tax_amount'] = $tpl_totals['tax'];
}
if (!array_key_exists('total', $tpl_totals) && array_key_exists('gross', $tpl_totals)) {
	$tpl_totals['total'] = $tpl_totals['gross'];
}
$totals = array_replace([
	'subtotal' => 0.0,
	'tax_rate' => 0.0,
	'tax_amount' => 0.0,
	'total' => 0.0,
], $tpl_totals);

if ($totals['total'] == 0.0 && !empty($positions)) {
	foreach ($positions as $row) {
		$qty = (float)($row['qty'] ?? 0);
		$unit_price = (float)($row['unit_price'] ?? 0);
		$line_total = (float)($row['line_total'] ?? ($qty * $unit_price));
		$totals['subtotal'] += $line_total;
	}
	$totals['total'] = $totals['subtotal'] + (float)$totals['tax_amount'];
}

$footer_key = 'belegfuss_' . $beleg_type;
$footer_block = (string)($opts_belege[$footer_key] ?? '');
$footer_block = $is_ausgang ? $footer_block : '';
$footer_block = str_replace(["\r\n", "\r"], "\n", $footer_block);
$footer_has_br = stripos($footer_block, '<br') !== false;
$footer_has_tags = $footer_block !== wp_strip_all_tags($footer_block);
$footer_html = '';
if ($footer_block !== '') {
	$footer_html = $footer_has_tags
		? wp_kses($footer_block, ['br' => [], 'a' => ['href' => [], 'title' => [], 'target' => [], 'rel' => []], 'strong' => [], 'em' => [], 'b' => [], 'i' => []])
		: nl2br(esc_html($footer_block));
}

$sender_country_code = strtoupper(trim((string)($tpl['me']['land_code'] ?? '')));
if ($sender_country_code === '') $sender_country_code = 'CH';
$sender_plz = trim((string)($tpl['me']['plz'] ?? ''));
$sender_ort = trim((string)($tpl['me']['ort'] ?? ''));
$sender_city_line = trim($sender_country_code . '-' . $sender_plz . ' ' . $sender_ort);
$sender_block = trim(
	($tpl['me']['company'] ?? '') . "\n" .
	($tpl['me']['strasse'] ?? '') . "\n" .
	$sender_city_line
);

$recipient_block = trim((string)($cmx_beleg_adress ?? ''));
if ($recipient_block === '') {
	$recipient_block = trim(
		($tpl['recipient']['name'] ?? '') . "\n" .
		($tpl['recipient']['street'] ?? '') . "\n" .
		(trim(($tpl['recipient']['zip'] ?? '') . ' ' . ($tpl['recipient']['city'] ?? '')))
	);
}
$recipient_block = str_replace(["\r\n", "\r"], "\n", $recipient_block);
$recipient_has_br = (stripos($recipient_block, '<br') !== false);
$recipient_html = $recipient_has_br
	? wp_kses($recipient_block, ['br' => []])
	: nl2br(esc_html($recipient_block));
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($tpl['document']['title'] ?? 'Rechnung', ENT_QUOTES, 'UTF-8'); ?></title>
<style>
	body { font-family: Arial, sans-serif; font-size: 12px; color: #000; }
	.container { width: 100%; }
	.header { margin-bottom: 24px; }
	.address { width: 50%; float: left; }
	.sender-wrap { display: flex; align-items: flex-start; gap: 10px; }
	.sender-logo { width: 150px; height: auto; max-width: 100%; display: block; margin-bottom: 6px; }
	.sender-table { width: 100%; border-collapse: collapse; }
	.sender-table td { border: 0; padding: 0; vertical-align: top; }
	.sender-contact { font-size: 11px; text-align: right; white-space: nowrap; padding-left: 8px; }
	.sender-contact a { color: inherit; text-decoration: none; }
	.invoice-meta { width: 140px; float: right; text-align: right; }
	.invoice-meta table { border: 0 !important; border-spacing: 0 !important; table-layout: auto; }
	.invoice-meta td,
	.invoice-meta tr { border: 0 !important; }
	.invoice-meta td { width: 1%; white-space: nowrap; padding: 0; line-height: 1.1; }
	h1 { margin-top: 32px; font-size: 20px; }
	table { width: 100%; border-collapse: collapse; margin-top: 16px; }
	th, td { border: none; padding: 6px 8px; }
	.positions-table thead th { border-bottom: 1px solid #000; text-align: left; }
	.positions-table tbody tr { border-bottom: 1px solid #777; }
.positions-table tbody tr:last-child { border-bottom: 1px solid #777; }
.positions-table tbody tr:nth-child(even) { background: #f3f3f3; }
.positions-table tbody td { vertical-align: top; }
.positions-table th.col-num { text-align: right; padding-right: 8px; }
	.totals-table,
	.totals-table tr,
	.totals-table td { border: 0 !important; }
	.totals-table tr { line-height: 1.1; }
	.totals-table td { padding: 2px 8px; }
	.totals-table .total-row td { padding-top: 8px; }
	.beleg-subject { margin-top: 6px; font-size: 13px; }
	.beleg-desc { margin-top: 2px; }
	.mwst-note { margin-top: 8px; font-size: 11px; }
	.totals { width: 40%; float: right; margin-top: 16px; }
	.footer { margin-top: 20px; font-size: 11px; }
	.clear { clear: both; }
	.text-right { text-align: right; }
	.logo-link { text-decoration: none; }
	.qr-reserve { height: 105mm; }
</style>
</head>
<body>
<div class="container">
	<div class="header">
		<div class="address">
			<?php
			$me_phone = trim((string)($tpl['me']['phone'] ?? ''));
			$me_email = trim((string)($tpl['me']['email'] ?? ''));
			$me_web = trim((string)($tpl['me']['website'] ?? ''));
			$has_contact = ($me_phone !== '' || $me_email !== '' || $me_web !== '');
			$format_ch_phone = static function(string $raw): string {
				$digits = preg_replace('/\D+/', '', $raw);
				if (strpos($digits, '0041') === 0) {
					$digits = '41' . substr($digits, 4);
				}
				if (strpos($digits, '41') === 0 && strlen($digits) === 11) {
					$rest = substr($digits, 2);
					return '+41 ' . substr($rest, 0, 2) . ' ' . substr($rest, 2, 3) . ' ' . substr($rest, 5, 2) . ' ' . substr($rest, 7, 2);
				}
				if (strlen($digits) === 10 && $digits[0] === '0') {
					return substr($digits, 0, 3) . ' ' . substr($digits, 3, 3) . ' ' . substr($digits, 6, 2) . ' ' . substr($digits, 8, 2);
				}
				return $raw;
			};
			$me_phone_label = $me_phone !== '' ? $format_ch_phone($me_phone) : '';
			$me_phone_href = preg_replace('/[^0-9+]/', '', $me_phone);
			$me_web_href = $me_web;
			$me_web_label = $me_web;
			if ($me_web_label !== '') {
				$me_web_label = preg_replace('~^https?://~i', '', $me_web_label);
				$me_web_label = preg_replace('~^www\\.~i', '', $me_web_label);
				$me_web_label = 'www.' . $me_web_label;
			}
			if ($me_web_href !== '' && !preg_match('~^https?://~i', $me_web_href)) {
				$me_web_href = 'https://' . $me_web_href;
			}
			?>
			<table class="sender-table">
				<tr>
					<td>
						<div class="sender-wrap">
							<?php if (!empty($tpl['branding']['logo'])): ?>
								<?php
								$brand_url = trim((string)($tpl['branding']['website'] ?? ''));
								if ($brand_url === '') {
									$brand_url = trim((string)($tpl['me']['website'] ?? ''));
								}
								if ($brand_url !== '' && !preg_match('~^https?://~i', $brand_url)) {
									$brand_url = 'https://' . $brand_url;
								}
								?>
								<?php if ($brand_url !== ''): ?>
									<a href="<?= htmlspecialchars($brand_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="logo-link">
										<img class="sender-logo" src="<?= htmlspecialchars((string)$tpl['branding']['logo'], ENT_QUOTES, 'UTF-8'); ?>" alt="Logo">
									</a>
								<?php else: ?>
									<img class="sender-logo" src="<?= htmlspecialchars((string)$tpl['branding']['logo'], ENT_QUOTES, 'UTF-8'); ?>" alt="Logo">
								<?php endif; ?>
							<?php endif; ?>
							<div><strong><?= nl2br(htmlspecialchars($sender_block, ENT_QUOTES, 'UTF-8')); ?></strong></div>
						</div>
					</td>
					<?php if ($has_contact): ?>
						<td class="sender-contact">
							<?php if ($me_phone !== ''): ?>
								<div><a href="tel:<?= htmlspecialchars($me_phone_href, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($me_phone_label, ENT_QUOTES, 'UTF-8'); ?></a></div>
							<?php endif; ?>
							<?php if ($me_email !== ''): ?>
								<div><?= htmlspecialchars($me_email, ENT_QUOTES, 'UTF-8'); ?></div>
							<?php endif; ?>
							<?php if ($me_web !== ''): ?>
								<div><a href="<?= htmlspecialchars($me_web_href, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($me_web_label, ENT_QUOTES, 'UTF-8'); ?></a></div>
							<?php endif; ?>
						</td>
					<?php endif; ?>
				</tr>
			</table>
		</div>

		<div class="invoice-meta">
			<table style="width:100%; border-collapse:collapse; border:0;">
				<tr>
					<td style="border:0; padding:0; width:1%; white-space:nowrap;"><?= htmlspecialchars($tpl['labels']['date'] ?? 'Rechnungsdatum', ENT_QUOTES, 'UTF-8'); ?></td>
					<td class="text-right" style="border:0; padding:0; width:1%; white-space:nowrap;"><?= htmlspecialchars($tpl['document']['date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
				</tr>
				<tr>
					<td style="border:0; padding:0; width:1%; white-space:nowrap;"><?= htmlspecialchars($tpl['labels']['due'] ?? 'Fällig bis', ENT_QUOTES, 'UTF-8'); ?></td>
					<td class="text-right" style="border:0; padding:0; width:1%; white-space:nowrap;"><?= htmlspecialchars($tpl['document']['due'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
				</tr>
				<tr>
					<td style="border:0; padding:0; width:1%; white-space:nowrap;"><?= htmlspecialchars($tpl['labels']['period'] ?? 'Leistung für', ENT_QUOTES, 'UTF-8'); ?></td>
					<td class="text-right" style="border:0; padding:0; width:1%; white-space:nowrap;"><?= htmlspecialchars($tpl['document']['period'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
				</tr>
			</table>
		</div>
		<div class="clear"></div>
	</div>

	<div class="address">
		<strong><?= htmlspecialchars($tpl['labels']['recipient'] ?? 'Rechnung an', ENT_QUOTES, 'UTF-8'); ?></strong>
		<div><?= $recipient_html; ?></div>
	</div>

	<h1 class="text-right" style="margin-top: 64px;"><?= htmlspecialchars($tpl['document']['title'] ?? 'Rechnung', ENT_QUOTES, 'UTF-8'); ?></h1>
	<?php if ($beleg_subject !== ''): ?>
		<div class="beleg-subject"><strong><?= htmlspecialchars($beleg_subject, ENT_QUOTES, 'UTF-8'); ?></strong></div>
	<?php endif; ?>
	<?php if ($beleg_description !== ''): ?>
		<div class="beleg-desc"><?= nl2br(htmlspecialchars($beleg_description, ENT_QUOTES, 'UTF-8')); ?></div>
	<?php endif; ?>

<?php if ($has_positions): ?>
	<table class="positions-table">
		<thead>
			<tr>
				<?php if ($show_position_index): ?>
					<th>Pos.</th>
				<?php endif; ?>
				<?php if ($show_sku): ?>
					<th>SKU</th>
				<?php endif; ?>
				<th>Artikel</th>
				<th class="col-num">Menge</th>
				<?php if ($show_unit_price): ?>
					<th class="col-num">Einzelpreis</th>
				<?php endif; ?>
				<?php if ($show_discount): ?>
					<th class="col-num">Rabatt</th>
				<?php endif; ?>
				<?php if ($show_line_total): ?>
					<th class="col-num">Summe <?= htmlspecialchars($__fmt_cur, ENT_QUOTES, 'UTF-8'); ?></th>
				<?php endif; ?>
			</tr>
		</thead>
		<tbody>
		<?php if (empty($positions)): ?>
			<tr>
				<td colspan="<?= $col_count; ?>">—</td>
			</tr>
		<?php else: ?>
			<?php foreach ($positions as $i => $row): ?>
				<?php
				$qty = (float)($row['qty'] ?? 0);
				$unit = (string)($row['unit'] ?? '');
				$unit_price = (float)($row['unit_price'] ?? 0);
				$line_total = (float)($row['line_total'] ?? ($qty * $unit_price));
				$line_subtotal = $qty * $unit_price;
				$line_discount = $line_subtotal - $line_total;
				$item = (string)($row['item'] ?? '');
				$desc = (string)($row['desc_text'] ?? $row['desc_raw'] ?? '');
				$desc_html = (string)($row['desc_html'] ?? '');
				$sku = (string)($row['article_number'] ?? '');
				$discount_display = $line_discount > 0.0001 ? $__fmt_num($line_discount) : '';
				?>
				<tr>
					<?php if ($show_position_index): ?>
						<td><?= htmlspecialchars((string)($i + 1), ENT_QUOTES, 'UTF-8'); ?></td>
					<?php endif; ?>
					<?php if ($show_sku): ?>
						<td><?= htmlspecialchars($sku, ENT_QUOTES, 'UTF-8'); ?></td>
					<?php endif; ?>
					<td>
						<strong><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8'); ?></strong><br>
						<?php if ($desc_html !== ''): ?>
							<?= $desc_html; ?>
						<?php else: ?>
							<?= nl2br(htmlspecialchars($desc, ENT_QUOTES, 'UTF-8')); ?>
						<?php endif; ?>
					</td>
					<td class="text-right"><?= htmlspecialchars(trim($__fmt_num($qty) . ' ' . $unit), ENT_QUOTES, 'UTF-8'); ?></td>
					<?php if ($show_unit_price): ?>
						<td class="text-right"><?= htmlspecialchars($__fmt_num($unit_price), ENT_QUOTES, 'UTF-8'); ?></td>
					<?php endif; ?>
					<?php if ($show_discount): ?>
						<td class="text-right"><?= htmlspecialchars($discount_display, ENT_QUOTES, 'UTF-8'); ?></td>
					<?php endif; ?>
					<?php if ($show_line_total): ?>
						<td class="text-right"><?= htmlspecialchars($__fmt_num($line_total), ENT_QUOTES, 'UTF-8'); ?></td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
	<?php endif; ?>

<?php if (!$has_positions && !empty($tpl['document']['manual_total'])): ?>
	<?php $manual_total = (float)$tpl['document']['manual_total']; ?>
	<div style="margin-top:16px;text-align:right;">
		<strong>Total <?= htmlspecialchars($__fmt_num($manual_total), ENT_QUOTES, 'UTF-8'); ?></strong>
	</div>
<?php endif; ?>

	<?php if (!$is_lieferschein && !(!$has_positions && !empty($tpl['document']['manual_total']))): ?>
		<table class="totals-table" border="0">
			<?php
			$mwst_rate = (float)($tpl['totals']['tax_rate'] ?? 0);
			$mwst_amount = (float)($totals['tax_amount'] ?? 0);
			$mwst_rate_pct = $mwst_rate * 100;
			$mwst_rate_str = rtrim(rtrim(number_format($mwst_rate_pct, 1, '.', ''), '0'), '.');
			$show_subtotal_row = $has_positions && (
				($show_discount && $discount_sum > 0.0) ||
				($mwst_rate > 0.0)
			);
			?>
			<?php if ($show_subtotal_row): ?>
				<tr>
					<td colspan="<?= $col_count; ?>" class="text-right">
						<strong>Zwischensumme <?= htmlspecialchars($__fmt_num((float)$positions_sum), ENT_QUOTES, 'UTF-8'); ?></strong>
					</td>
				</tr>
			<?php endif; ?>
			<?php if ($show_discount && $discount_sum > 0.0): ?>
				<tr>
					<td colspan="<?= $col_count; ?>" class="text-right">
						Rabatt <?= htmlspecialchars($__fmt_num((float)$discount_sum), ENT_QUOTES, 'UTF-8'); ?>
					</td>
				</tr>
			<?php endif; ?>
			<?php if ($mwst_rate > 0 && !$is_lieferantenrechnung): ?>
				<tr>
					<td colspan="<?= $col_count; ?>" class="text-right">
					<?php $mwst_label = !empty($tpl['totals']['is_brutto']) ? 'davon' : 'zzgl.'; ?>
					<?= $mwst_label; ?>
					<?= htmlspecialchars($mwst_rate_str, ENT_QUOTES, 'UTF-8'); ?>% MwSt. <?= htmlspecialchars($__fmt_num($mwst_amount), ENT_QUOTES, 'UTF-8'); ?>
				</td>
			</tr>
		<?php endif; ?>
		<tr class="total-row">
			<td colspan="<?= $col_count; ?>" class="text-right">
				<strong>Total <?= htmlspecialchars($__fmt_num((float)$totals['total']), ENT_QUOTES, 'UTF-8'); ?></strong>
			</td>
		</tr>
	</table>
	<?php if ($is_mwst_pflichtig && !$is_lieferantenrechnung): ?>
		<?php $mwst_nr = trim((string)($opts_general['mwst_nummer'] ?? '')); ?>
		<?php if ($mwst_nr !== ''): ?>
			<div class="mwst-note">MWST-Nr: <?= htmlspecialchars($mwst_nr, ENT_QUOTES, 'UTF-8'); ?></div>
		<?php endif; ?>
	<?php elseif (!$is_lieferantenrechnung): ?>
		<div class="mwst-note">
			Nicht mehrwertsteuerpflichtig gemäss <a href="https://www.fedlex.admin.ch/eli/cc/2009/615/de#art_10" style="color:black;" target="_blank" rel="noopener noreferrer">Art. 10 Abs. 2 lit. a MWSTG</a>
		</div>
	<?php endif; ?>

	<?php endif; ?>

	<?php if (!$is_lieferschein && !$is_gutschrift && !empty($tpl['anzahlungen']) && is_array($tpl['anzahlungen'])): ?>
		<?php
$anz_base_total = (float)($totals['total'] ?? 0);
if (!$has_positions && !empty($tpl['document']['manual_total'])) {
	$anz_base_total = (float)$tpl['document']['manual_total'];
}
		$anzahlungen_sum = 0.0;
		foreach ($tpl['anzahlungen'] as $row) {
			$anz_amount_raw = (string)($row['betrag'] ?? 0);
			$anz_amount = (float)cmx_norm_decimal($anz_amount_raw);
			$anzahlungen_sum += $anz_amount;
		}
		$offen_betrag = $anz_base_total - $anzahlungen_sum;
		?>
		<div style="margin-top:16px;text-align:right;">
			<em>Bereits erhaltene Zahlungen</em>
			<table style="width:200px; border-collapse:collapse; margin-top:4px; margin-left:auto;">
				<?php foreach ($tpl['anzahlungen'] as $row): ?>
					<?php
					$anz_date = trim((string)($row['datum'] ?? ''));
					$anz_amount_raw = (string)($row['betrag'] ?? 0);
					$anz_amount = (float)cmx_norm_decimal($anz_amount_raw);
					if ($anz_date === '') continue;
					$anz_date_fmt = date('d.m.Y', strtotime($anz_date));
					?>
					<tr>
						<td style="padding:0 0 2px 0; text-align:right;"><?= htmlspecialchars($anz_date_fmt, ENT_QUOTES, 'UTF-8'); ?></td>
						<td style="padding:0 0 2px 12px; text-align:right;"><?= htmlspecialchars($__fmt_num($anz_amount), ENT_QUOTES, 'UTF-8'); ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
			<div>- <?= htmlspecialchars($__fmt_num($anzahlungen_sum), ENT_QUOTES, 'UTF-8'); ?></div>
			<?php
				$offen_fmt = $__fmt_num($offen_betrag);
				if (str_starts_with($offen_fmt, '-')) {
					$offen_fmt = '- ' . ltrim($offen_fmt, '-');
				}
			?>
			<div style="margin-top:8px;"><strong>Offener Betrag: <?= htmlspecialchars($offen_fmt, ENT_QUOTES, 'UTF-8'); ?></strong></div>
		</div>
	<?php endif; ?>

	<div class="clear"></div>

	<div class="footer">
		<?= $footer_html; ?>
	</div>

	<div class="qr-reserve"></div>
</div>
</body>
</html>
