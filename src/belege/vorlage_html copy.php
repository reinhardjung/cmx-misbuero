<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

$plugin_dir   = defined('CMX_PLUGIN_DIR') ? CMX_PLUGIN_DIR : plugin_dir_path(__FILE__);
$font_dir     = wp_normalize_path($plugin_dir . 'assets/asap/static/');
$font_regular = $font_dir . 'Asap-Regular.ttf';
$font_bold    = $font_dir . 'Asap-Bold.ttf';
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
	.sku { white-space:nowrap; word-break:keep-all; hyphens:none; white-space:nowrap; }
	.td-narrow { width: 1%; white-space: nowrap; text-align: center; }
	a, a:hover, a:visited, a:active, a:focus { color: grey; text-decoration: none; }

	/* Ab Seite 2: mehr Kopfplatz + fixer Footerplatz */
	@page {
		margin-top: 100px;  /* Kopfbereich für Seite >= 2 */
		margin-right: 30px;
		margin-left: 50px;
		margin-bottom: 90px; /* Footer-Reserve für alle Seiten */
	}
	@page :first {
		margin-top: 20px;   /* Seite 1: kein Kopf */
		margin-right: 30px;
		margin-left: 50px;
		margin-bottom: 80px;
	}

	@font-face {
		font-family: 'Asap';
		src: url('<?= $font_regular ?>') format('truetype');
		font-weight: 400;
		font-style: normal;
	}
	@font-face {
		font-family: 'Asap';
		src: url('<?= $font_bold ?>') format('truetype');
		font-weight: 700;
		font-style: normal;
	}

	body { font-family: 'Asap', sans-serif; font-size: 12px; color:#333; }
	table, td, th { border: none; }
	.footer { position: fixed; bottom: -60px; left: 0; right: 0; height:60px; text-align: center; font-size: 12px; }
	.text-right { text-align: right !important; }
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
		return $__fmt_num($val);
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

/* ---- Bankdaten robust aus $tpl['bank'] holen (ohne Notices) ---- */
$bank_arr = [];
if (isset($tpl['bank']) && is_array($tpl['bank'])) {
	$bank_arr = $tpl['bank'];
} elseif (function_exists(__NAMESPACE__ . '\\cmxbu_get_preferred_bank')) {
	// Fallback, falls Generator $tpl['bank'] nicht gesetzt hat
	$bank_arr = cmxbu_get_preferred_bank();
}
$bank_arr = array_replace(['bank_name' => '', 'iban' => '', 'bic' => ''], (array)$bank_arr);

$bank_name_safe = function_exists('esc_html') ? esc_html($bank_arr['bank_name']) : $bank_arr['bank_name'];
$bank_iban_safe = function_exists('esc_html') ? esc_html($bank_arr['iban'])      : $bank_arr['iban'];
$bank_bic_safe  = function_exists('esc_html') ? esc_html($bank_arr['bic'])       : $bank_arr['bic'];


?>

<!-- HEADER -->
<table>
<tr>
	<td class="text-right">
		<a href="<?= htmlspecialchars($tpl['me']['website'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
			<img src="<?= htmlspecialchars($tpl['branding']['logo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" width="70" alt="Logo"><br><br>
		</a>
	</td>
</tr>
</table>

<!-- META -->
<table>
	<tr>
		<td width="65px"><br>
			<?= htmlspecialchars($tpl['labels']['date'] ?? 'Datum', ENT_QUOTES, 'UTF-8'); ?><br>
			<?php if (($tpl['document']['due'] ?? '') !== '01.01.1970'): ?>
				<?= htmlspecialchars($tpl['labels']['due'] ?? 'Fällig bis', ENT_QUOTES, 'UTF-8'); ?><br>
			<?php endif; ?>
			<?= htmlspecialchars($tpl['labels']['period'] ?? 'Leistung für', ENT_QUOTES, 'UTF-8'); ?>
		</td>
		<td width="80px" style="text-align:right;"><br>
			<?= htmlspecialchars($tpl['document']['date'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
			<?php if (($tpl['document']['due'] ?? '') !== '01.01.1970'): ?>
				<?= htmlspecialchars($tpl['document']['due'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
			<?php endif; ?>
			<?= htmlspecialchars($tpl['document']['period'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br><br>
		</td>
		<td width="250px"></td>
		<td>
			<span style="font-size:8px; color:grey; font-style:italic; position:relative; top:-20px;">&nbsp;&nbsp;&nbsp;&nbsp;<?= htmlspecialchars($tpl['me']['company'] ?? '', ENT_QUOTES, 'UTF-8') .' &bull; ' .htmlspecialchars($tpl['me']['strasse'] ?? '', ENT_QUOTES, 'UTF-8') .' &bull; ' . htmlspecialchars($tpl['me']['plz'] ?? '', ENT_QUOTES, 'UTF-8')   .' '.  htmlspecialchars($tpl['me']['ort'] ?? '', ENT_QUOTES, 'UTF-8') ?></span><br>
			<span style="position:relative; top:-20px; font-size:larger;"><?= $cmx_beleg_adress ?? '' ?></span>
		</td>
	</tr>
</table>

<h1><?= htmlspecialchars($tpl['document']['title'] ?? 'Rechnung', ENT_QUOTES, 'UTF-8'); ?></h1>
<?php if (!empty($tpl['document']['number'])): ?>
	<p><i><strong><?= htmlspecialchars($tpl['document']['subject'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></i></p>
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

$showPosNr = !empty($tpl['positions']) && count($tpl['positions']) > 1;

$colCount  = $showPosNr ? 1 : 0;
$colCount += $showArtNr ? 1 : 0;
$colCount += 1; // Artikel
$colCount += 1; // Menge
$colCount += 1; // Einzelpreis
$colCount += $showDiscount ? 1 : 0;
$colCount += 1; // Summe
$preTotalColspan = max(0, $colCount - 2);
?>

<!-- POSITIONEN -->
<table>
	<thead>
	<tr>
		<?php if ($showPosNr): ?>
			<th class="td-narrow"></th>
		<?php endif; ?>
		<?php if ($showArtNr): ?>
			<th class="td-narrow" style="align:left"><?= htmlspecialchars($tpl['labels']['artnr'] ?? 'Nr.', ENT_QUOTES, 'UTF-8'); ?></th>
		<?php endif; ?>
		<th><?= htmlspecialchars($tpl['labels']['item'] ?? 'Artikel', ENT_QUOTES, 'UTF-8'); ?></th>
		<th class="td-narrow text-right"><?= htmlspecialchars($tpl['labels']['qty'] ?? 'Menge', ENT_QUOTES, 'UTF-8'); ?></th>
		<th class="td-narrow text-right"><?= htmlspecialchars($tpl['labels']['unit_price'] ?? 'Einzelpreis', ENT_QUOTES, 'UTF-8'); ?></th>
		<?php if ($showDiscount): ?>
			<th class="td-narrow text-right"><?= htmlspecialchars($tpl['labels']['discount'] ?? 'Rabatt', ENT_QUOTES, 'UTF-8'); ?></th>
		<?php endif; ?>
		<th class="td-narrow text-right"><?= htmlspecialchars($tpl['labels']['line_total'] ?? 'Summe', ENT_QUOTES, 'UTF-8'); ?> <?= htmlspecialchars($tpl['document']['currency'] ?? '', ENT_QUOTES, 'UTF-8'); ?></th>
	</tr>
	</thead>
	<tbody>
	<?php if (!empty($tpl['positions'])): ?>
		<?php $i = 0; foreach ($tpl['positions'] as $row): $z = ($i % 2 === 0) ? 'zebra-even' : 'zebra-odd'; $posNr = $i + 1; ?>
			<tr class="align:right; position <?= $z ?>">
				<?php if ($showPosNr): ?>
					<td><?= (int)$posNr; ?></td>
				<?php endif; ?>
				<?php if ($showArtNr): ?>
					<td class="sku"><?= htmlspecialchars($row['article_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
				<?php endif; ?>
				<td>
					<strong><?= htmlspecialchars($row['item'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>
					<?php if (!empty($row['article_belegtext_html'])): ?>
						<div class="small"><?= $row['article_belegtext_html']; ?></div>
					<?php endif; ?>
					<?php if (!empty($row['desc_html'])): ?>
						<div class="small"><?= $row['desc_html']; ?></div>
					<?php endif; ?>
				</td>
				<td class="td-narrow text-right" style="padding-right:10px;">
					<?php
					$menge = (float)($row['qty'] ?? 0);
					if (fmod($menge, 1.0) === 0.0) {
						echo htmlspecialchars(number_format($menge, 0, '', ''), ENT_QUOTES, 'UTF-8');
					} else {
						echo htmlspecialchars(rtrim(rtrim(number_format($menge, 2, ',', ''), '0'), '.'), ENT_QUOTES, 'UTF-8');
					}
					?>
				</td>
				<td class="td-narrow text-right"><?= htmlspecialchars($__fmt_minus((float)($row['unit_price'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
				<?php if ($showDiscount): ?>
					<td class="td-narrow text-right">
						<?php
							$_disc = (string)($row['discount'] ?? '');
							echo $_disc !== '' ? htmlspecialchars($__fmt_discount($_disc), ENT_QUOTES, 'UTF-8') : '';
						?>
					</td>
				<?php endif; ?>
				<td class="td-narrow text-right">
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
		<tr><td colspan="<?= (int)$colCount ?>"><i>–</i></td></tr>
	<?php endif; ?>

	<?php $total_display = !empty($tpl['positions']) ? $__compute_total($tpl['positions']) : (float)($tpl['document']['total'] ?? 0); ?>
	<tr>
		<td colspan="<?= (int)$preTotalColspan ?>"></td>
		<td>
			<strong><?= htmlspecialchars($tpl['labels']['total'] ?? 'Gesamtbetrag', ENT_QUOTES, 'UTF-8'); ?></strong><!-- <br>exkl. MwSt.-->
		</td>
		<td class="text-right">
			<?= htmlspecialchars($__fmt_minus($total_display), ENT_QUOTES, 'UTF-8'); ?>
		</td>
	</tr>
	</tbody>
</table>

<?php
if (in_array(strtolower($beleg_type), ['angebot', 'rechnung', 'lieferschein'], true)) {
	?>
	<table style="width:100%;">
	<tr>
		<td>Nicht mehrwertsteuerpflichtig (<a style="color:black; font-style:italic;" href="https://www.fedlex.admin.ch/eli/cc/2009/615/de">Art. 10 Abs. 2 lit. a MWSTG</a>)<br></td>
	</tr>
	<tr>
		<td><br><br><br><?= cmx_get_belegfuss($beleg_type); ?></td>
	</tr>
	</table>
<?php
}
?>


<!-- FOOTER -->
<div class="footer" style="font-size:xx-small; color:grey;">
	<table>
	<tr>
		<td style="text-align:left;">
			<b><?= htmlspecialchars($tpl['labels']['recipient'] ?? 'Empfänger', ENT_QUOTES, 'UTF-8'); ?></b><br>
			<?= htmlspecialchars($tpl['me']['company'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
			<?= htmlspecialchars($tpl['me']['strasse'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
			<?= htmlspecialchars(($tpl['me']['plz'] ?? '').' '.($tpl['me']['ort'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><br>
		</td>
		<td style="text-align:center;">
			<b><?= htmlspecialchars($tpl['labels']['bank'] ?? 'Bank', ENT_QUOTES, 'UTF-8'); ?></b><br>
			<?= $bank_name_safe; ?><br>
			<?= $bank_iban_safe; ?><br>
			<?php if ($bank_bic_safe !== ''): ?>
				<?= $bank_bic_safe; ?><br>
			<?php endif; ?>
		</td>
		<td style="text-align:right;">
			<b><?= htmlspecialchars($tpl['labels']['contact'] ?? 'Kontakt', ENT_QUOTES, 'UTF-8'); ?></b><br>
			<?php if (!empty($tpl['me']['phone'])): ?>
				<a href="https://misbuero.ch/?cmx_call=<?= urlencode(str_replace(' ', '+', $tpl['me']['phone'])) ?>"><?= htmlspecialchars($tpl['me']['phone'], ENT_QUOTES, 'UTF-8') ?></a><br>
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
