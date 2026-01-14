<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/*
 * Schweizer Rechnungs-Layout (ohne QR)
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

$sender_block = trim(
	($tpl['me']['company'] ?? '') . "\n" .
	($tpl['me']['strasse'] ?? '') . "\n" .
	(trim(($tpl['me']['plz'] ?? '') . ' ' . ($tpl['me']['ort'] ?? ''))) . "\n" .
	(($tpl['me']['land'] ?? 'Schweiz'))
);

$recipient_block = trim((string)($cmx_beleg_adress ?? ''));
if ($recipient_block === '') {
	$recipient_block = trim(
		($tpl['recipient']['name'] ?? '') . "\n" .
		($tpl['recipient']['street'] ?? '') . "\n" .
		(trim(($tpl['recipient']['zip'] ?? '') . ' ' . ($tpl['recipient']['city'] ?? '')))
	);
}
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
	.invoice-meta { width: 50%; float: right; text-align: right; }
	h1 { margin-top: 32px; font-size: 20px; }
	table { width: 100%; border-collapse: collapse; margin-top: 16px; }
	th, td { border: 1px solid #ccc; padding: 8px; }
	th { background: #f5f5f5; }
	.totals { width: 40%; float: right; margin-top: 16px; }
	.footer { margin-top: 60px; font-size: 11px; }
	.clear { clear: both; }
	.text-right { text-align: right; }
</style>
</head>
<body>
<div class="container">
	<div class="header">
		<div class="address">
			<strong><?= nl2br(htmlspecialchars($sender_block, ENT_QUOTES, 'UTF-8')); ?></strong><br><br>
			<?php if (!$is_mwst_pflichtig): ?>
				Nicht mehrwertsteuerpflichtig gemäss Art. 10 Abs. 2 lit. a MWSTG
			<?php endif; ?>
		</div>

		<div class="invoice-meta">
			<?= htmlspecialchars(($tpl['labels']['number'] ?? 'Rechnungsnummer') . ': ' . ($tpl['document']['number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><br>
			<?= htmlspecialchars(($tpl['labels']['date'] ?? 'Rechnungsdatum') . ': ' . ($tpl['document']['date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><br>
			<?= htmlspecialchars(($tpl['labels']['period'] ?? 'Leistungszeitraum') . ': ' . ($tpl['document']['period'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><br>
			<?= htmlspecialchars(($tpl['labels']['due'] ?? 'Fällig bis') . ': ' . ($tpl['document']['due'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
		</div>
		<div class="clear"></div>
	</div>

	<div class="address">
		<strong><?= htmlspecialchars($tpl['labels']['recipient'] ?? 'Rechnung an', ENT_QUOTES, 'UTF-8'); ?></strong><br>
		<?= nl2br(htmlspecialchars($recipient_block, ENT_QUOTES, 'UTF-8')); ?>
	</div>

	<h1><?= htmlspecialchars($tpl['document']['title'] ?? 'Rechnung', ENT_QUOTES, 'UTF-8'); ?></h1>

	<table>
		<thead>
			<tr>
				<th>SKU</th>
				<th>Artikel</th>
				<th>Menge</th>
				<th>Einzelpreis</th>
				<th>Rabatt</th>
				<th>Summe <?= htmlspecialchars($__fmt_cur, ENT_QUOTES, 'UTF-8'); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if (empty($positions)): ?>
			<tr>
				<td colspan="6">—</td>
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
				$sku = (string)($row['article_number'] ?? '');
				$discount = (string)($row['discount'] ?? '');
				?>
				<tr>
					<td><?= htmlspecialchars($sku, ENT_QUOTES, 'UTF-8'); ?></td>
					<td>
						<strong><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8'); ?></strong><br>
						<?= nl2br(htmlspecialchars($desc, ENT_QUOTES, 'UTF-8')); ?>
					</td>
					<td><?= htmlspecialchars(trim($__fmt_num($qty) . ' ' . $unit), ENT_QUOTES, 'UTF-8'); ?></td>
					<td class="text-right"><?= htmlspecialchars($__fmt_num($unit_price), ENT_QUOTES, 'UTF-8'); ?></td>
					<td class="text-right"><?= htmlspecialchars($discount !== '' ? $discount : '—', ENT_QUOTES, 'UTF-8'); ?></td>
					<td class="text-right"><?= htmlspecialchars($__fmt_num($line_total), ENT_QUOTES, 'UTF-8'); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<div class="totals">
		<?= htmlspecialchars(($tpl['labels']['subtotal'] ?? 'Zwischensumme') . ': ' . $__fmt_cur . ' ' . $__fmt_num((float)$totals['subtotal']), ENT_QUOTES, 'UTF-8'); ?><br>
		<?php if ($is_mwst_pflichtig && (float)$totals['tax_rate'] > 0): ?>
			<?= htmlspecialchars('MWST ' . rtrim(rtrim((string)$totals['tax_rate'], '0'), '.') . ' %: ' . $__fmt_cur . ' ' . $__fmt_num((float)$totals['tax_amount']), ENT_QUOTES, 'UTF-8'); ?><br>
		<?php endif; ?>
		<strong><?= htmlspecialchars(($tpl['labels']['total'] ?? 'Total') . ': ' . $__fmt_cur . ' ' . $__fmt_num((float)$totals['total']), ENT_QUOTES, 'UTF-8'); ?></strong>
	</div>

	<div class="clear"></div>

	<div class="footer">
		Zahlung via QR-Rechnung.<br>
		Vielen Dank für Deinen Auftrag.
	</div>
</div>
</body>
</html>
