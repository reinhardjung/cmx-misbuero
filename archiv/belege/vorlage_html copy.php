<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');
// 	body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color:#333; }
// var_dump(CMX_PLUGIN_DIR ."assets/asap/static/Asap-Regular.ttf"); exit;


?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($tpl['document']['title'] ?? 'Rechnung', ENT_QUOTES, 'UTF-8'); ?></title>
<style>
	h1 { font-size: 18px; margin: 10px 0 6px 0; }
	table { width: 100%; border-collapse: collapse; margin-top: 14px; }
	th, td { padding: 6px; text-align: left; vertical-align: top; }
	table th { border-bottom: 1px solid #000; }
	.position { border-bottom: .5px groove #000; }
	.zebra-even td { background: #f8f8f8; }
	.zebra-odd  td { background: #ffffff; }
	.total { font-weight: bold; }
	a, a:hover, a:visited, a:active, a:focus { color: grey; text-decoration: none; }
	@page { margin: 20px 30px 80px 50px; }

$css = "
@font-face {
  font-family: 'Asap';
  src: url('" . CMX_PLUGIN_DIR . "assets/asap/static/Asap-Regular.ttf') format('truetype');
}
body { font-family: 'Asap', sans-serif; }
";

	@font-face {
    font-family: 'Asap';
    src: url('<?php echo plugin_dir_url( __FILE__ ); ?>assets/asap/static/Asap-Regular.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
}
@font-face {
    font-family: 'Asap';
    src: url('<?php echo plugin_dir_url( __FILE__ ); ?>assets/asap/static/Asap-Bold.ttf') format('truetype');
    font-weight: bold;
    font-style: normal;
}
	body {
		font-family: 'Asap', sans-serif; font-size: 12px; color:#333;
	}


	table, td, th { border: none; }
	.footer { position: fixed; bottom: -60px; left: 0; right: 0; height: 50px; text-align: center; font-size: 12px; }
	.footer:after { content: "Seite " counter(page) " von " counter(pages); }
	.text-right { text-align: right; }
	.small { font-size: 10px; }
</style>
</head>
<body>
<?php
	$__fmt_dec  = (string)($tpl['format']['decimal']   ?? ',');
	$__fmt_tho  = (string)($tpl['format']['thousands'] ?? "'");
	$__fmt_prec = (int)   ($tpl['format']['decimals']  ?? 2);
	$__fmt_cur  = (string)($tpl['format']['currency']  ?? 'CHF');

	$__fmt_num = function(float $v) use ($__fmt_prec, $__fmt_dec, $__fmt_tho): string {
		return number_format($v, $__fmt_prec, $__fmt_dec, $__fmt_tho);
	};
	$__fmt_minus = function(float $v) use ($__fmt_num): string {
		return ($v < 0 ? '-' : '') . $__fmt_num(abs($v));
	};
	$__fmt_discount = function(string $disc) use ($__fmt_num, $__fmt_cur, $__fmt_dec) : string {
		$disc = trim($disc);
		if ($disc === '') return '—';
		if (preg_match('~^(\d+(?:[.,]\d+)?)\s*%$~', $disc, $m)) {
			$val = (float)str_replace(',', '.', $m[1]);
			$str = $__fmt_num($val);
			$str = rtrim(rtrim($str, '0'), $__fmt_dec);
			return $str.'%';
		}
		if (preg_match('~^(\d+(?:[.,]\d+)?)\s+\w+$~', $disc, $m)) {
			$val = (float)str_replace(',', '.', $m[1]);
			return $__fmt_num($val).' '.$__fmt_cur;
		}
		return strtr($disc, ['.' => $__fmt_dec]);
	};
	$__compute_total = function(array $rows): float {
		$sum = 0.0;
		foreach ($rows as $row) {
			$lt = (float)($row['line_total'] ?? 0);
			if (abs($lt) < 0.0000001) {
				$qty   = (float)($row['qty'] ?? 0);
				$upris = (float)($row['unit_price'] ?? 0);
				$calc  = $qty * $upris;
				if (abs($calc) > 0.0000001) $lt = $calc;
			}
			$sum += $lt;
		}
		return $sum;
	};
?>
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

<!-- META -->
<table>
	<tr>
		<td width="160">
			<strong><?= htmlspecialchars($tpl['labels']['date'] ?? 'Datum', ENT_QUOTES, 'UTF-8'); ?></strong><br>
			<strong><?= htmlspecialchars($tpl['labels']['due'] ?? 'Fällig bis', ENT_QUOTES, 'UTF-8'); ?></strong><br>
			<strong><?= htmlspecialchars($tpl['labels']['period'] ?? 'Leistung für', ENT_QUOTES, 'UTF-8'); ?></strong>
		</td>
		<td width="220">
			<?= htmlspecialchars($tpl['document']['date'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
			<?= htmlspecialchars($tpl['document']['due'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
			<?= htmlspecialchars($tpl['document']['period'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
		</td>
		<td></td>
	</tr>
</table>

<h1><?= htmlspecialchars($tpl['document']['title'] ?? 'Rechnung', ENT_QUOTES, 'UTF-8'); ?></h1>
<?php if (!empty($tpl['document']['number'])): ?>
	<p><i><strong><?= htmlspecialchars($tpl['document']['subject'], ENT_QUOTES, 'UTF-8'); ?></strong></i></p>
<?php endif; ?>

<?php if (!empty($tpl['document']['description'])): ?>
	<p><?= nl2br(htmlspecialchars($tpl['document']['description'], ENT_QUOTES, 'UTF-8')); ?></p>
<?php endif; ?>

<?php
$showDiscount = !empty($tpl['any_discount']);
if (!$showDiscount && !empty($tpl['positions'])) {
	foreach ($tpl['positions'] as $__p) {
		if (trim((string)($__p['discount'] ?? '')) !== '' || !empty($__p['has_discount'])) { $showDiscount = true; break; }
	}
}
$showArtNr = false;
if (!empty($tpl['positions'])) {
	foreach ($tpl['positions'] as $__p) {
		if (trim((string)($__p['article_number'] ?? '')) !== '') { $showArtNr = true; break; }
	}
}
$colCount  = 1;
$colCount += $showArtNr ? 1 : 0;
$colCount += 1; // Artikel (Name + Zusatznotiz)
$colCount += 1; // Beschreibung (Belegtext aus CPT)
$colCount += 1; // Menge
$colCount += 1; // Einzelpreis
$colCount += $showDiscount ? 1 : 0; // Rabatt
$colCount += 1; // Summe
$preTotalColspan = max(0, $colCount - 2);
?>

<!-- POSITIONEN -->
<table>
	<thead>
	<tr>
		<th>#</th>
		<?php if ($showArtNr): ?>
			<th><?= htmlspecialchars($tpl['labels']['artnr'] ?? 'Artikel-Nr.', ENT_QUOTES, 'UTF-8'); ?></th>
		<?php endif; ?>
		<th><?= htmlspecialchars($tpl['labels']['item'] ?? 'Artikel', ENT_QUOTES, 'UTF-8'); ?></th>
		<th><?= htmlspecialchars($tpl['labels']['desc'] ?? 'Beschreibung', ENT_QUOTES, 'UTF-8'); ?></th>
		<th class="text-right"><?= htmlspecialchars($tpl['labels']['qty'] ?? 'Menge', ENT_QUOTES, 'UTF-8'); ?></th>
		<th class="text-right"><?= htmlspecialchars($tpl['labels']['unit_price'] ?? 'Einzelpreis', ENT_QUOTES, 'UTF-8'); ?></th>
		<?php if ($showDiscount): ?>
			<th class="text-right"><?= htmlspecialchars($tpl['labels']['discount'] ?? 'Rabatt', ENT_QUOTES, 'UTF-8'); ?></th>
		<?php endif; ?>
		<th class="text-right"><?= htmlspecialchars($tpl['labels']['line_total'] ?? 'Summe', ENT_QUOTES, 'UTF-8'); ?></th>
	</tr>
	</thead>
	<tbody>
	<?php if (!empty($tpl['positions'])): ?>
		<?php $i = 0; foreach ($tpl['positions'] as $row): $z = ($i % 2 === 0) ? 'zebra-even' : 'zebra-odd'; $posNr = $i + 1; ?>
			<tr class="position <?= $z ?>">
				<td><?= (int)$posNr; ?></td>

				<?php if ($showArtNr): ?>
					<td><?= htmlspecialchars($row['article_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
				<?php endif; ?>

				<!-- Artikel + Zusatznotiz (Spalte 2) -->
				<td>
					<div><strong><?= htmlspecialchars($row['item'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></div>
					<?php
						$noteHtml = (string)($row['desc_html'] ?? '');
						$noteTxt  = (string)($row['desc_text'] ?? '');
						$noteRaw  = (string)($row['desc_raw']  ?? '');
						$noteHas  = (trim(strip_tags($noteHtml)) !== '') || (trim($noteTxt) !== '') || (trim($noteRaw) !== '');
						if ($noteHas): ?>
							<div class="small">
								<?php
									if (trim(strip_tags($noteHtml)) !== '')      echo $noteHtml;
									elseif (trim($noteTxt) !== '')               echo nl2br(htmlspecialchars($noteTxt, ENT_QUOTES, 'UTF-8'));
									else                                         echo nl2br(htmlspecialchars($noteRaw, ENT_QUOTES, 'UTF-8'));
								?>
							</div>
					<?php endif; ?>
				</td>

				<!-- Beschreibung (Spalte 3) = Beleg-Text (CPT „Artikel“) -->
				<td>
					<?php
						$artDesc = (string)($row['article_belegtext_html'] ?? '');
						if (trim(strip_tags($artDesc)) !== '') {
							echo $artDesc;
						}
					?>
				</td>

				<td class="text-right">
					<?= htmlspecialchars($__fmt_num((float)($row['qty'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>
				</td>
				<td class="text-right">
					<?= htmlspecialchars($__fmt_minus((float)($row['unit_price'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>
				</td>
				<?php if ($showDiscount): ?>
					<td class="text-right">
						<?php
							$_disc = (string)($row['discount'] ?? '');
							echo $_disc !== '' ? htmlspecialchars($__fmt_discount($_disc), ENT_QUOTES, 'UTF-8') : '—';
						?>
					</td>
				<?php endif; ?>
				<td class="text-right">
					<?php
						$lt = (float)($row['line_total'] ?? 0);
						if (abs($lt) < 0.0000001) {
							$qty   = (float)($row['qty'] ?? 0);
							$upris = (float)($row['unit_price'] ?? 0);
							$calc  = $qty * $upris;
							if (abs($calc) > 0.0000001) $lt = $calc;
						}
						echo htmlspecialchars($__fmt_minus($lt), ENT_QUOTES, 'UTF-8');
					?>
				</td>
			</tr>
		<?php $i++; endforeach; ?>
	<?php else: ?>
		<tr>
			<td colspan="<?= (int)$colCount ?>"><i>–</i></td>
		</tr>
	<?php endif; ?>

	<!-- TOTAL -->
	<?php $total_display = !empty($tpl['positions']) ? $__compute_total($tpl['positions']) : (float)($tpl['document']['total'] ?? 0); ?>
	<tr>
		<td colspan="<?= (int)$preTotalColspan ?>"></td>
		<td class="total text-right">
			<strong><?= htmlspecialchars($tpl['labels']['total'] ?? 'Gesamtbetrag', ENT_QUOTES, 'UTF-8'); ?></strong>
		</td>
		<td class="total text-right">
			<?= htmlspecialchars($__fmt_minus($total_display), ENT_QUOTES, 'UTF-8'); ?>
			&nbsp;<?= htmlspecialchars($__fmt_cur, ENT_QUOTES, 'UTF-8'); ?>
		</td>
	</tr>
	</tbody>
</table>

<!-- FOOTER -->
<div class="footer">
	<table>
	<tr>
		<td style="text-align:left;">
			<b><?= htmlspecialchars($tpl['labels']['recipient'] ?? 'Empfänger', ENT_QUOTES, 'UTF-8'); ?></b><br>
			<?= htmlspecialchars($tpl['me']['company'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
			<?= htmlspecialchars($tpl['me']['strasse'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
			<?= htmlspecialchars(($tpl['me']['land'] ?? 'CH').'-'.($tpl['me']['plz'] ?? '').' '.($tpl['me']['ort'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><br>
		</td>
		<td style="text-align:center;">
			<b><?= htmlspecialchars($tpl['labels']['bank'] ?? 'Bank', ENT_QUOTES, 'UTF-8'); ?></b><br>
			<?= htmlspecialchars($tpl['bank']['bank_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
			<?= htmlspecialchars($tpl['bank']['iban'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
			<?= htmlspecialchars($tpl['bank']['bic'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
		</td>
		<td style="text-align:right;">
			<b><?= htmlspecialchars($tpl['labels']['contact'] ?? 'Kontakt', ENT_QUOTES, 'UTF-8'); ?></b><br>
			<?php if (!empty($tpl['me']['phone'])): ?>
				<a href="tel:<?= htmlspecialchars($tpl['me']['phone'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($tpl['me']['phone'], ENT_QUOTES, 'UTF-8'); ?></a><br>
			<?php endif; ?>
			<?php if (!empty($tpl['me']['email'])): ?>
				<a href="mailto:<?= htmlspecialchars($tpl['me']['email'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($tpl['me']['email'], ENT_QUOTES, 'UTF-8'); ?></a><br>
			<?php endif; ?>
			<?php if (!empty($tpl['me']['support'])): ?>
				<a href="mailto:<?= htmlspecialchars($tpl['me']['support'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($tpl['me']['support'], ENT_QUOTES, 'UTF-8'); ?></a>
			<?php endif; ?>
		</td>
	</tr>
	</table>
</div>

</body>
</html>
