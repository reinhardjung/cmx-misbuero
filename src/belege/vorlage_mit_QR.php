<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/*
 * Schweizer Rechnungs-Layout (mit QR)
 * Erwartet $tpl und ggf. $cmx_beleg_adress.
 */

$__fmt_dec  = (string)($tpl['format']['decimal']   ?? ',');
$__fmt_tho  = (string)($tpl['format']['thousands'] ?? "'");
$__fmt_prec = (int)   ($tpl['format']['decimals']  ?? 2);
$__fmt_cur  = (string)($tpl['format']['currency']  ?? 'CHF');

$__fmt_num = function(float $v) use ($__fmt_prec, $__fmt_dec, $__fmt_tho): string {
	return number_format($v, $__fmt_prec, $__fmt_dec, $__fmt_tho);
};

$positions = (array)($tpl['positions'] ?? []);
$show_position_index = count($positions) > 1;
$show_sku = false;
$show_discount = false;
$discount_sum = 0.0;
foreach ($positions as $row) {
	$sku_val = trim((string)($row['article_number'] ?? ''));
	if ($sku_val !== '') $show_sku = true;
	$qty = (float)($row['qty'] ?? 0);
	$unit_price = (float)($row['unit_price'] ?? 0);
	$line_total = (float)($row['line_total'] ?? ($qty * $unit_price));
	$line_subtotal = $qty * $unit_price;
	$line_discount = $line_subtotal - $line_total;
	if ($line_discount > 0.0001) {
		$show_discount = true;
		$discount_sum += $line_discount;
	}
}
$col_count = ($show_position_index ? 1 : 0)
	+ ($show_sku ? 1 : 0)
	+ 1
	+ 1
	+ 1
	+ ($show_discount ? 1 : 0)
	+ 1;

$totals = array_replace([
	'subtotal' => 0.0,
	'tax_rate' => 0.0,
	'tax_amount' => 0.0,
	'total' => 0.0,
], (array)($tpl['totals'] ?? []));

if ($totals['total'] == 0.0 && !empty($positions)) {
	foreach ($positions as $row) {
		$qty = (float)($row['qty'] ?? 0);
		$unit_price = (float)($row['unit_price'] ?? 0);
		$line_total = (float)($row['line_total'] ?? ($qty * $unit_price));
		$totals['subtotal'] += $line_total;
	}
	$totals['total'] = $totals['subtotal'] + (float)$totals['tax_amount'];
}

$opts_general      = (array) get_option('cmx_einstellungen', []);
$is_mwst_pflichtig = !empty($opts_general['mwst_pfl']) || !empty($opts_general['mwstpflichtig']) || !empty($opts_general['mwst_pflichtig']);
$opts_belege = (array) get_option('cmx_belege', []);
$beleg_type = strtolower((string)($tpl['document']['type'] ?? 'rechnung'));
$footer_key = 'belegfuss_' . $beleg_type;
$footer_block = (string)($opts_belege[$footer_key] ?? '');
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
	.invoice-meta { width: 200px; float: right; text-align: right; }
	.invoice-meta table { border: 0 !important; border-spacing: 0 !important; table-layout: auto; }
	.invoice-meta td,
	.invoice-meta tr { border: 0 !important; }
	.invoice-meta td { width: 1%; white-space: nowrap; padding: 0; line-height: 1.1; }
	h1 { margin-top: 32px; font-size: 20px; }
	table { width: 100%; border-collapse: collapse; margin-top: 16px; }
	th, td { border: none; padding: 6px 8px; }
	thead th { border-bottom: 1px solid #000; text-align: left; }
	tbody tr { border-bottom: 1px solid #777; }
	tbody tr:last-child { border-bottom: 1px solid #777; }
	.positions-table tbody tr:nth-child(even) { background: #f3f3f3; }
	.positions-table tbody td { vertical-align: top; }
	.totals-table,
	.totals-table tr,
	.totals-table td { border: 0 !important; }
	.th-right { text-align: right; }
	.mwst-note { margin-top: 8px; font-size: 11px; }
	.totals { width: 40%; float: right; margin-top: 16px; }
	.footer { margin-top: 60px; font-size: 11px; }
	.clear { clear: both; }
	.text-right { text-align: right; }
	.qr-block { margin-top: 40px; }
	.qr-placeholder {
		border: 1px dashed #999;
		height: 140px;
		width: 240px;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 11px;
		color: #666;
	}
</style>
</head>
<body>
<div class="container">
	<div class="header">
		<div class="address">
			<strong><?= nl2br(htmlspecialchars($sender_block, ENT_QUOTES, 'UTF-8')); ?></strong><br>
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

	<h1 class="text-right"><?= htmlspecialchars($tpl['document']['title'] ?? 'Rechnung', ENT_QUOTES, 'UTF-8'); ?></h1>

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
				<th class="th-right">Menge</th>
				<th class="th-right">Einzelpreis</th>
				<?php if ($show_discount): ?>
					<th class="th-right">Rabatt</th>
				<?php endif; ?>
				<th class="th-right">Summe <?= htmlspecialchars($__fmt_cur, ENT_QUOTES, 'UTF-8'); ?></th>
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
				$item = (string)($row['item'] ?? '');
				$desc = (string)($row['desc_text'] ?? $row['desc_raw'] ?? '');
				$desc_html = (string)($row['desc_html'] ?? '');
				$sku = (string)($row['article_number'] ?? '');
				$discount = (string)($row['discount'] ?? '');
				$discount_display = $discount !== '' ? $discount : '';
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
					<td class="text-right"><?= htmlspecialchars($__fmt_num($unit_price), ENT_QUOTES, 'UTF-8'); ?></td>
					<?php if ($show_discount): ?>
						<td class="text-right"><?= htmlspecialchars($discount_display, ENT_QUOTES, 'UTF-8'); ?></td>
					<?php endif; ?>
					<td class="text-right"><?= htmlspecialchars($__fmt_num($line_total), ENT_QUOTES, 'UTF-8'); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<table class="totals-table" border="0">
		<?php if ($show_discount && $discount_sum > 0.0): ?>
			<tr>
				<td colspan="<?= $col_count; ?>" class="text-right">
					<strong>Rabatt <?= htmlspecialchars($__fmt_num((float)$discount_sum), ENT_QUOTES, 'UTF-8'); ?></strong>
				</td>
			</tr>
		<?php endif; ?>
		<tr>
			<td colspan="<?= $col_count; ?>" class="text-right">
				<strong>Total <?= htmlspecialchars($__fmt_num((float)$totals['total']), ENT_QUOTES, 'UTF-8'); ?></strong>
			</td>
		</tr>
	</table>
	<?php if (!$is_mwst_pflichtig): ?>
		<div class="mwst-note">
			Nicht mehrwertsteuerpflichtig gemäss <a href="https://www.fedlex.admin.ch/eli/cc/2009/615/de#art_10" style="color:black;" target="_blank" rel="noopener noreferrer">Art. 10 Abs. 2 lit. a MWSTG</a>
		</div>
	<?php endif; ?>

	<div class="clear"></div>

	<div class="qr-block">
		<strong>QR-Rechnung</strong><br>
		<div class="qr-placeholder">QR-Code</div>
	</div>

	<div class="footer">
		Zahlung via QR-Rechnung.<br>
		<?= $footer_html; ?>
	</div>
</div>
</body>
</html>
