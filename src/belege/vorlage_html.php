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

/* ============================================================
   GLOBAL / PDF (Bildschirm)
   ============================================================ */
body {
    font-family: Helvetica, Arial, sans-serif;
    font-size: 12px;
    color:black;
}

h1 { font-size: 18px; margin: 10px 0 6px 0; }
table { width: 100%; border-collapse: collapse; margin-top: 14px; }
th, td { padding: 6px; text-align: left; vertical-align: top; }
table th { border-bottom: 1px solid #000; }
.position { border-bottom: .5px groove #000; }

/* ZEBRA (sichtbar in PDF, NICHT im Druck!) */
.zebra-even td { background: #f8f8f8; }
.zebra-odd  td { background: #ffffff; }

.text-right { text-align: right !important; }
.small { font-size: 10px; }

a, a:hover, a:visited, a:active, a:focus {
    color:black;
    text-decoration: none;
}

/* Seitenränder */
@page {
    margin-top: 100px;
    margin-right: 30px;
    margin-left: 50px;
    margin-bottom: 90px;
}
@page :first {
    margin-top: 20px;
    margin-right: 30px;
    margin-left: 50px;
    margin-bottom: 80px;
}


/* ============================================================
   ** DRUCK: Alles Schwarz erzwingen (Dompdf-kompatibel) **
   ============================================================ */
@media print {

    /* --- Text tiefschwarz (CMYK) --- */
    *, body, table, tr, td, th, span, div, p, strong, b, i {
        color: #000 !important;
        -dompdf-font-color: cmyk(0,0,0,1) !important;
        -dompdf-text-color: cmyk(0,0,0,1) !important;
    }

    /* --- Linien tiefschwarz --- */
    *, table, td, th {
        border-color: #000 !important;
        -dompdf-color: cmyk(0,0,0,1) !important;
    }

    /* --- KEIN Hintergrund im Druck (auch Zebra weg!) --- */
    * {
        background: transparent !important;

        /* zwingt Dompdf zu vollem Schwarz */
        -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
    }

    /* --- Links schwarz --- */
    a, a:hover, a:active, a:visited {
        color: #000 !important;
        text-decoration: none !important;
        -dompdf-color: cmyk(0,0,0,1) !important;
    }

    /* --- QR-Code & Bilder unberührt --- */
    img {
        filter: none !important;
        -webkit-filter: none !important;
        background: none !important;
    }

    /* --- Zebra-Streifen im Druck entfernen --- */
    .zebra-even td,
    .zebra-odd td {
        background: transparent !important;
    }
}

</style>


</head>
<body>
<?php
/* ----------------------------------------------
   Formatierung
   ---------------------------------------------- */
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
$totals = array_replace([
	'net'=>0.0,
	'gross'=>0.0,
	'tax'=>0.0,
	'tax_rate'=>0.0,
	'is_brutto'=>false,
], (array)($tpl['totals'] ?? []));

/* ----------------------------------------------
   BANKDATEN
   ---------------------------------------------- */
$bank_arr = [];
if (isset($tpl['bank']) && is_array($tpl['bank'])) {
	$bank_arr = $tpl['bank'];
} elseif (function_exists(__NAMESPACE__ . '\\cmxbu_get_preferred_bank')) {
	$bank_arr = cmxbu_get_preferred_bank();
}
$bank_arr = array_replace(['bank_name' => '', 'iban' => '', 'bic' => ''], (array)$bank_arr);

$bank_name_safe = esc_html($bank_arr['bank_name']);
$bank_iban_safe = esc_html($bank_arr['iban']);
$bank_bic_safe  = esc_html($bank_arr['bic']);

/* ----------------------------------------------
   Lieferschein → reduzierte Spalten
   ---------------------------------------------- */
$isLieferschein = (strtolower($beleg_type ?? '') === 'lieferschein');
$hasDiscounts  = !empty($tpl['any_discount']);
$showUnitPrice = !$isLieferschein;
$showDiscount  = !$isLieferschein && $hasDiscounts;
?>

<!-- HEADER -->
<table>
<tr>
	<td class="text-right">
		<a href="<?= esc_html($tpl['me']['website'] ?? ''); ?>">
			<img src="<?= esc_html($tpl['branding']['logo'] ?? ''); ?>" width="70" alt="Logo"><br><br>
		</a>
	</td>
</tr>
</table>

<!-- META -->
<table>
	<tr>
		<td width="65px"><br>
			<?= esc_html($tpl['labels']['date'] ?? 'Datum'); ?><br>

			<?php if (($tpl['document']['due'] ?? '') !== '01.01.1970'): ?>
				<?= esc_html($tpl['labels']['due'] ?? 'Fällig bis'); ?><br>
			<?php endif; ?>

			<?= esc_html($tpl['labels']['period'] ?? 'Leistung für'); ?>
		</td>

		<td width="80px" style="text-align:right;"><br>
			<?= esc_html($tpl['document']['date'] ?? ''); ?><br>

			<?php if (($tpl['document']['due'] ?? '') !== '01.01.1970'): ?>
				<?= esc_html($tpl['document']['due'] ?? ''); ?><br>
			<?php endif; ?>

			<?= esc_html($tpl['document']['period'] ?? ''); ?><br><br>
		</td>

		<td width="250px"></td>

		<td>
			<span style="font-size:8px; color:black; font-style:italic; position:relative; top:-20px;">
				<?= esc_html($tpl['me']['company']) .' • ' .
					esc_html($tpl['me']['strasse']) .' • ' .
					esc_html($tpl['me']['plz']) .' ' .
					esc_html($tpl['me']['ort']) ?>
			</span><br>

			<span style="position:relative; top:-20px; font-size:larger;"><?= $cmx_beleg_adress ?? '' ?></span>
		</td>
	</tr>
</table>

<!-- TITLE -->
<h1><?= esc_html($tpl['document']['title'] ?? 'Rechnung'); ?></h1>

<?php if (!empty($tpl['document']['number'])): ?>
	<p><i><strong><?= esc_html($tpl['document']['subject'] ?? ''); ?></strong></i></p>
<?php endif; ?>

<?php if (!empty($tpl['document']['description'])): ?>
	<p><?= nl2br(esc_html($tpl['document']['description'])); ?></p>
<?php endif; ?>

<?php
/* ----------------------------------------------
   Spalten dynamisch berechnen
   ---------------------------------------------- */
$showArtNr = false;
if (!empty($tpl['positions'])) {
	foreach ($tpl['positions'] as $__p) {
		if (trim((string)($__p['article_number'] ?? '')) !== '') {
			$showArtNr = true;
			break;
		}
	}
}

$showPosNr = !empty($tpl['positions']) && count($tpl['positions']) > 1;

$colCount  = $showPosNr ? 1 : 0;
$colCount += $showArtNr ? 1 : 0;
$colCount += 1; // Artikel
$colCount += 1; // Menge
$colCount += $showUnitPrice ? 1 : 0;
$colCount += $showDiscount ? 1 : 0;
$colCount += $showUnitPrice ? 1 : 0; // Summe

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
			<th class="td-narrow"><?= esc_html($tpl['labels']['artnr'] ?? 'Nr.'); ?></th>
		<?php endif; ?>

		<th><?= esc_html($tpl['labels']['item'] ?? 'Artikel'); ?></th>

		<th class="td-narrow text-right">
			<?= esc_html($tpl['labels']['qty'] ?? 'Menge'); ?>
		</th>

		<?php if ($showUnitPrice): ?>
			<th class="td-narrow text-right">
				<?= esc_html($tpl['labels']['unit_price'] ?? 'Einzelpreis'); ?>
			</th>
		<?php endif; ?>

		<?php if ($showDiscount): ?>
			<th class="td-narrow text-right">
				<?= esc_html($tpl['labels']['discount'] ?? 'Rabatt'); ?>
			</th>
		<?php endif; ?>

		<?php if ($showUnitPrice): ?>
			<th class="td-narrow text-right">
				<?= esc_html($tpl['labels']['line_total'] ?? 'Summe'); ?>
				<?= esc_html($tpl['document']['currency'] ?? ''); ?>
			</th>
		<?php endif; ?>

	</tr>
	</thead>

	<tbody>
	<?php if (!empty($tpl['positions'])): ?>
		<?php $i = 0; foreach ($tpl['positions'] as $row): ?>
			<tr class="position <?= ($i % 2 === 0 ? 'zebra-even' : 'zebra-odd'); ?>">

				<?php if ($showPosNr): ?>
					<td><?= (int)($i + 1); ?></td>
				<?php endif; ?>

				<?php if ($showArtNr): ?>
					<td class="sku"><?= esc_html($row['article_number'] ?? ''); ?></td>
				<?php endif; ?>

				<td>
					<strong><?= esc_html($row['item'] ?? ''); ?></strong>

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
					echo (fmod($menge, 1.0) === 0.0)
						? number_format($menge, 0, '', '')
						: rtrim(rtrim(number_format($menge, 2, ',', ''), '0'), '.');
					?>
				</td>

				<?php if ($showUnitPrice): ?>
					<td class="td-narrow text-right">
						<?= esc_html($__fmt_minus((float)($row['unit_price'] ?? 0))); ?>
					</td>
				<?php endif; ?>

				<?php if ($showDiscount): ?>
					<td class="td-narrow text-right">
						<?php
							$_disc = (string)($row['discount'] ?? '');
							echo $_disc !== '' ? esc_html($__fmt_discount($_disc)) : '';
						?>
					</td>
				<?php endif; ?>

				<?php if ($showUnitPrice): ?>
					<td class="td-narrow text-right">
						<?php
							$lt = (float)($row['line_total'] ?? 0);
							if (abs($lt) < 0.0000001) {
								$qty   = (float)($row['qty'] ?? 0);
								$upris = (float)($row['unit_price'] ?? 0);
								$calc  = $qty * $upris;
								if (abs($calc) > 0.0000001) $lt = $calc;
							}
							echo esc_html($__fmt_minus($lt));
						?>
					</td>
				<?php endif; ?>

			</tr>
		<?php $i++; endforeach; ?>

	<?php else: ?>
		<tr><td colspan="<?= (int)$colCount ?>"><i>–</i></td></tr>
	<?php endif; ?>


	<?php
	$total_display = (float)($totals['gross'] ?? 0);
	if (abs($total_display) < 0.0000001) {
		$total_display = !empty($tpl['positions'])
			? $__compute_total($tpl['positions'])
			: (float)($tpl['document']['total'] ?? 0);
		$totals['gross'] = $total_display;
	}

	$showTaxRow      = ($totals['tax_rate'] ?? 0) > 0;
	$tax_display     = (float)($totals['tax'] ?? 0);
	$net_display     = (float)($totals['net'] ?? ($total_display - $tax_display));
	$tax_rate_display = $showTaxRow
		? rtrim(rtrim(number_format((float)$totals['tax_rate'] * 100, 2, $__fmt_dec, $__fmt_tho), '0'), $__fmt_dec)
		: '';
	?>

	<?php if ($showUnitPrice): ?>
		<?php if ($showTaxRow && !$totals['is_brutto']): ?>
		<tr>
			<td colspan="<?= (int)$preTotalColspan ?>"></td>

			<td>
				<strong>Zwischensumme</strong>
			</td>

			<td class="text-right">
				<strong><?= esc_html($__fmt_minus($net_display)); ?></strong>
			</td>
		</tr>
		<?php endif; ?>

		<?php if ($showTaxRow): ?>
		<tr>
			<td colspan="<?= (int)$preTotalColspan ?>"></td>

			<td>
				<strong>MwSt <?= esc_html($tax_rate_display); ?>%<?= $totals['is_brutto'] ? ' (enthalten)' : ''; ?></strong>
			</td>

			<td class="text-right">
				<strong><?= esc_html($__fmt_minus($tax_display)); ?></strong>
			</td>
		</tr>
		<?php endif; ?>

		<tr>
			<td colspan="<?= (int)$preTotalColspan ?>"></td>

			<td>
				<strong><?= esc_html($tpl['labels']['total'] ?? 'Gesamtbetrag'); ?><?= $showTaxRow ? ' inkl. MwSt' : ''; ?></strong>
			</td>

			<td class="text-right">
				<strong><?= esc_html($__fmt_minus($total_display)); ?></strong>
			</td>
		</tr>
	<?php endif; ?>

	</tbody>
</table>

<?php if (in_array(strtolower($beleg_type), ['angebot', 'rechnung', 'gutschrift'], true)) : ?>
	<table style="width:100%;">
	<tr>
		<td>
			Nicht mehrwertsteuerpflichtig
			(<a style="color:black; font-style:italic;" href="https://www.fedlex.admin.ch/eli/cc/2009/615/de">Art. 10 Abs. 2 lit. a MWSTG</a>)
			<br>
		</td>
	</tr>
	<tr>
		<td><br><br><br><?= cmx_get_belegfuss($beleg_type); ?></td>
	</tr>
	</table>
<?php endif; ?>

<?php if (in_array(strtolower($beleg_type), ['lieferschein'], true)) : ?>
	<table style="width:100%;">
	<tr>
		<td><br><br><br><?= cmx_get_belegfuss($beleg_type); ?></td>
	</tr>
	</table>
<?php endif; ?>

</body>
</html>
