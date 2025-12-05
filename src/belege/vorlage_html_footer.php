<!-- FOOTER -->
<div class="footer" style="font-size:xx-small; color:grey;">
	<table>
	<tr>
		<td style="text-align:left;">
			<b><?= htmlspecialchars($tpl['labels']['recipient'] ?? 'Empfänger', ENT_QUOTES, 'UTF-8'); ?></b><br>
			<?= htmlspecialchars($tpl['me']['company'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
			<?= htmlspecialchars($tpl['me']['strasse'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
			<?= htmlspecialchars(($tpl['me']['plz'] ?? '') .' '. ($tpl['me']['ort'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><br>
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
				<a href="https://misbuero.ch/?cmx_call=<?= urlencode(str_replace(' ', '+', $tpl['me']['phone'])) ?>">
					<?= htmlspecialchars($tpl['me']['phone'], ENT_QUOTES, 'UTF-8'); ?>
				</a><br>
			<?php endif; ?>

			<?php if (!empty($tpl['me']['email'])): ?>
				<a href="mailto:<?= htmlspecialchars($tpl['me']['email'], ENT_QUOTES, 'UTF-8'); ?>">
					<?= htmlspecialchars($tpl['me']['email'], ENT_QUOTES, 'UTF-8'); ?>
				</a><br>
			<?php endif; ?>

			<?php if (!empty($tpl['me']['support'])): ?>
				<a href="mailto:<?= htmlspecialchars($tpl['me']['support'], ENT_QUOTES, 'UTF-8'); ?>">
					<?= htmlspecialchars($tpl['me']['support'], ENT_QUOTES, 'UTF-8'); ?>
				</a>
			<?php endif; ?>

		</td>
	</tr>
	</table>
</div>
