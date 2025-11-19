<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!'); ?>
<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="UTF-8">
	<title><?= htmlspecialchars($tpl['document']['title'] ?? 'Rechnung', ENT_QUOTES, 'UTF-8'); ?></title>
	<style>
		body { font-family: sans-serif; font-size: 12px; color: #333; }
		h1 { font-size: 18px; margin: 10px 0 6px 0; }
		table { width: 100%; border-collapse: collapse; margin-top: 14px; }
		th, td { padding: 6px; text-align: left; vertical-align: top; }
		table th { border-bottom: 1px solid #000; }
		.position { border-bottom: .5px groove #000; }
		.gesamt { border-bottom: 2px double #000; }
		.total { font-weight: bold; }
		.footer { margin-top: 30px; font-size: 11px; }
		a, a:hover, a:visited, a:active, a:focus { color: grey; text-decoration: none; }
		@page { margin: 20px 30px 80px 50px; }
		table, td, th { border: none; }
		.footer { position: fixed; bottom: -60px; left: 0; right: 0; height: 50px; text-align: center; font-size: 12px; }
		.footer:after { content: "Seite " counter(page) " von " counter(pages); }
		.text-right { text-align: right; }
		.meta small { color: #666; }
		pre.addr { margin: 0; white-space: pre-line; }
	</style>
</head>
<body>

<!-- HEADER -->
<table>
	<tr>
		<td></td>
		<td class="text-right">
			<?php if (!empty($tpl['branding']['website'])): ?>
				<a href="<?= htmlspecialchars($tpl['branding']['website'], ENT_QUOTES, 'UTF-8'); ?>">
					<img width="70" src="<?= htmlspecialchars($tpl['branding']['logo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" alt="Logo">
				</a>
			<?php else: ?>
				<img width="70" src="<?= htmlspecialchars($tpl['branding']['logo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" alt="Logo">
			<?php endif; ?>
		</td>
	</tr>
</table>

<!-- META BLOCK -->
<table class="meta">
	<tr>
		<td width="160">
			<strong><?= htmlspecialchars($tpl['labels']['date'] ?? 'Datum', ENT_QUOTES, 'UTF-8'); ?></strong><br>
			<strong><?= htmlspecialchars($tpl['labels']['due'] ?? 'Fällig bis', ENT_QUOTES, 'UTF-8'); ?></strong><br>
			<strong><?= htmlspecialchars($tpl['labels']['period'] ?? 'Leistung für', ENT_QUOTES, 'UTF-8'); ?></strong>
		</td>
		<td width="200">
			<?= htmlspecialchars($tpl['document']['date'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
			<?= htmlspecialchars($tpl['document']['due'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
			<?= htmlspecialchars($tpl['document']['period'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
		</td>
		<td>
			<pre class="addr"><?= htmlspecialchars($tpl['contact']['display'] ?? '', ENT_QUOTES, 'UTF-8'); ?></pre>
		</td>
	</tr>
</table>

<h1><?= htmlspecialchars($tpl['document']['title'] ?? 'Rechnung', ENT_QUOTES, 'UTF-8'); ?></h1>
<?php if (!empty($tpl['document']['number'])): ?>
	<p><i><?= htmlspecialchars($tpl['document']['number'], ENT_QUOTES, 'UTF-8'); ?></i></p>
<?php endif; ?>
<?php if (!empty($tpl['document']['description'])): ?>
	<p><?= nl2br(htmlspecialchars($tpl['document']['description'], ENT_QUOTES, 'UTF-8')); ?></p>
<?php endif; ?>

<!-- POSITIONEN -->
<table>
	<thead>
	<tr>
		<th><?= htmlspecialchars($tpl['labels']['item'] ?? 'Artikel', ENT_QUOTES, 'UTF-8'); ?></th>
		<th><?= htmlspecialchars($tpl['labels']['desc'] ?? 'Beschreibung', ENT_QUOTES, 'UTF-8'); ?></th>
		<th class="text-right"><?= htmlspecialchars($tpl['labels']['qty'] ?? 'Menge', ENT_QUOTES, 'UTF-8'); ?></th>
		<th class="text-right"><?= htmlspecialchars($tpl['labels']['unit_price'] ?? 'Einzelpreis', ENT_QUOTES, 'UTF-8'); ?></th>
		<th class="text-right"><?= htmlspecialchars($tpl['format']['currency'] ?? 'CHF', ENT_QUOTES, 'UTF-8'); ?>&nbsp;</th>
	</tr>
	</thead>
	<tbody>
	<?php if (!empty($tpl['positions'])): ?>
		<?php foreach ($tpl['positions'] as $row): ?>
			<tr class="position">
				<td><?= htmlspecialchars($row['item'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
				<td><?= nl2br(htmlspecialchars($row['desc'] ?? '', ENT_QUOTES, 'UTF-8')); ?></td>
				<td class="text-right"><?= htmlspecialchars(number_format((float)$row['qty'], 2, $tpl['format']['decimal'], $tpl['format']['thousands']), ENT_QUOTES, 'UTF-8'); ?></td>
				<td class="text-right"><?= htmlspecialchars(number_format((float)$row['unit_price'], $tpl['format']['decimals'], $tpl['format']['decimal'], $tpl['format']['thousands']), ENT_QUOTES, 'UTF-8'); ?></td>
				<td class="text-right"><?= htmlspecialchars(number_format((float)$row['line_net'], $tpl['format']['decimals'], $tpl['format']['decimal'], $tpl['format']['thousands']), ENT_QUOTES, 'UTF-8'); ?></td>
			</tr>
		<?php endforeach; ?>
	<?php else: ?>
		<tr><td colspan="5"><i>–</i></td></tr>
	<?php endif; ?>

	<!-- SUMMEN-ZEILE -->
	<tr>
		<td colspan="3">
			<?php if (!empty($tpl['shop']['mwst_nr'])): ?>
				<small>MwSt#: <?= htmlspecialchars($tpl['shop']['mwst_nr'], ENT_QUOTES, 'UTF-8'); ?></small><br>
			<?php endif; ?>
		</td>
		<td class="gesamt">
			<strong><?= htmlspecialchars($tpl['labels']['total'] ?? 'Gesamtbetrag', ENT_QUOTES, 'UTF-8'); ?></strong><br>
			<small><?= htmlspecialchars($tpl['labels']['total_excl_vat'] ?? 'exkl. MwSt.', ENT_QUOTES, 'UTF-8'); ?></small>
		</td>
		<td class="gesamt text-right">
			<?= htmlspecialchars(number_format((float)$tpl['document']['subtotal'], $tpl['format']['decimals'], $tpl['format']['decimal'], $tpl['format']['thousands']), ENT_QUOTES, 'UTF-8'); ?>
		</td>
	</tr>

	<!-- MWST-GRUPPEN (optional) -->
	<?php if (!empty($tpl['tax_groups'])): ?>
		<tr>
			<td colspan="5"><strong><?= htmlspecialchars($tpl['labels']['tax_group'] ?? 'MwSt-Sätze', ENT_QUOTES, 'UTF-8'); ?></strong></td>
		</tr>
		<?php foreach ($tpl['tax_groups'] as $g): ?>
			<tr>
				<td colspan="3">
					<?= htmlspecialchars(number_format($g['rate'] * 100, 1, $tpl['format']['decimal'], $tpl['format']['thousands']), ENT_QUOTES, 'UTF-8'); ?>%
				</td>
				<td class="text-right"><?= htmlspecialchars($tpl['labels']['tax'] ?? 'MwSt', ENT_QUOTES, 'UTF-8'); ?></td>
				<td class="text-right"><?= htmlspecialchars(number_format((float)$g['tax'], $tpl['format']['decimals'], $tpl['format']['decimal'], $tpl['format']['thousands']), ENT_QUOTES, 'UTF-8'); ?></td>
			</tr>
		<?php endforeach; ?>
	<?php endif; ?>

	<!-- TOTAL BRUTTO -->
	<tr>
		<td colspan="3"></td>
		<td class="total text-right"><strong><?= htmlspecialchars($tpl['labels']['total'] ?? 'Gesamtbetrag', ENT_QUOTES, 'UTF-8'); ?></strong></td>
		<td class="total text-right">
			<strong><?= htmlspecialchars(number_format((float)$tpl['document']['total'], $tpl['format']['decimals'], $tpl['format']['decimal'], $tpl['format']['thousands']), ENT_QUOTES, 'UTF-8'); ?></strong>
			&nbsp;<?= htmlspecialchars($tpl['format']['currency'] ?? 'CHF', ENT_QUOTES, 'UTF-8'); ?>
		</td>
	</tr>
	</tbody>

	<tfoot>
	<tr>
		<td colspan="5" style="font-size:10px;">
			<br><br><strong><?= htmlspecialchars($tpl['labels']['note_payment'] ?? 'Hinweis zur Zahlung', ENT_QUOTES, 'UTF-8'); ?></strong>
			<?php if (!empty($tpl['legal']['terms_url'])): ?>
				&nbsp;<a href="<?= htmlspecialchars($tpl['legal']['terms_url'], ENT_QUOTES, 'UTF-8'); ?>" style="font-size:8px;" target="_blank" rel="noopener">
					<?= htmlspecialchars($tpl['labels']['terms'] ?? 'AGB', ENT_QUOTES, 'UTF-8'); ?>
				</a>
			<?php endif; ?>
			<br>
			<?php if (!empty($tpl['legal']['include_fees'])): ?>
				<?= htmlspecialchars($tpl['legal']['text_include_fees'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
			<?php endif; ?>
			<?= htmlspecialchars($tpl['legal']['text_no_deduction'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
		</td>
	</tr>
	</tfoot>
</table>

<!-- FOOTER -->
<div class="footer">
	<table>
	<tr>
		<td class="brief" style="text-align:left;">
			<b><?= htmlspecialchars($tpl['labels']['recipient'] ?? 'Empfänger', ENT_QUOTES, 'UTF-8'); ?></b><br>
			<?= htmlspecialchars($tpl['shop']['company'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
			<?= htmlspecialchars($tpl['shop']['strasse'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
			<?= htmlspecialchars(($tpl['shop']['land'] ?? 'CH').'-'.($tpl['shop']['plz'] ?? '').' '.($tpl['shop']['ort'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><br>
		</td>
		<td class="brief" style="text-align:center;">
			<b><?= htmlspecialchars($tpl['labels']['bank'] ?? 'Bank', ENT_QUOTES, 'UTF-8'); ?></b><br>
			<?= htmlspecialchars($tpl['bank'][0]['bank_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
			<?= htmlspecialchars($tpl['bank'][0]['iban'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
			<?= htmlspecialchars($tpl['bank'][0]['bic'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
		</td>
		<td class="brief" style="text-align:right;">
			<b><?= htmlspecialchars($tpl['labels']['contact'] ?? 'Kontakt', ENT_QUOTES, 'UTF-8'); ?></b><br>
			<?php if (!empty($tpl['shop']['phone'])): ?>
				<a href="tel:<?= htmlspecialchars($tpl['shop']['phone'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($tpl['shop']['phone'], ENT_QUOTES, 'UTF-8'); ?></a><br>
			<?php endif; ?>
			<?php if (!empty($tpl['shop']['email'])): ?>
				<a href="mailto:<?= htmlspecialchars($tpl['shop']['email'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($tpl['shop']['email'], ENT_QUOTES, 'UTF-8'); ?></a><br>
			<?php endif; ?>
			<?php if (!empty($tpl['shop']['support'])): ?>
				<a href="mailto:<?= htmlspecialchars($tpl['shop']['support'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($tpl['shop']['support'], ENT_QUOTES, 'UTF-8'); ?></a>
			<?php endif; ?>
		</td>
	</tr>
	</table>
</div>

</body>
</html>
