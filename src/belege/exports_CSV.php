<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/* ===== CSV Link ===== */
\add_action('admin_post_cmx_export_belege_list_csv', function(){
	if (!\current_user_can('edit_posts')) \wp_die('Keine Berechtigung.');
	if (!\wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'cmx_export_belege_list_csv')) \wp_die('Ungültige Anfrage.');
	$post_ids = cmxbu_belege_export_collect_ids();
	cmxbu_stream_belege_csv_from_ids($post_ids);
});

function cmxbu_beleg_has_positions(array $calc): bool {
	if (empty($calc['positionen']) || !is_array($calc['positionen'])) return false;
	foreach ($calc['positionen'] as $row) {
		if (!is_array($row)) continue;
		if (($row['row_type'] ?? '') === 'abschnitt') continue;
		$item = trim((string)($row['artikel_name'] ?? $row['item'] ?? $row['title'] ?? ''));
		$qty = (float)($row['qty'] ?? 0);
		$unit_price = (float)($row['unit_price'] ?? 0);
		$line_total = (float)($row['line_total'] ?? 0);
		if ($item !== '' || $qty > 0 || $unit_price > 0 || $line_total > 0) return true;
	}
	return false;
}

function cmxbu_stream_belege_csv_from_ids(array $ids): void {
	\ignore_user_abort(true); if (function_exists('set_time_limit')) @set_time_limit(0);
	while (ob_get_level()>0){ @ob_end_clean(); } \nocache_headers();

	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="'.cmxbu_belege_export_filename('csv').'"');
	header('Pragma: no-cache'); header('Expires: 0');

	$fh = fopen('php://output','w'); fwrite($fh, "\xEF\xBB\xBF");

	$headers = [
		'ID',
		'Belegnummer',
		'Belegtyp',
		'Belegdatum',
		'Faelligkeitsdatum',
		'Betreff',
		'Kunde',
		'Total',
		'Waehrung',
	];
	fputcsv($fh, $headers, ';');

	foreach ($ids as $pid) {
		$post = \get_post($pid);
		if (!$post) continue;

		$belegnr = (string) $post->post_title;
		$belegdatum = (string) \get_post_meta($pid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_RNG_DATUM') ? CMX_BELEG_META_RNG_DATUM : '_cmx_beleg_rng_datum', true);
		if ($belegdatum === '') {
			$belegdatum = \get_date_from_gmt((string) $post->post_date_gmt, 'Y-m-d');
		}
		$faellig = (string) \get_post_meta($pid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_FAELLIG') ? CMX_BELEG_META_FAELLIG : '_cmx_beleg_faelligkeitsdatum', true);
		$betreff = (string) \get_post_meta($pid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF') ? CMX_BELEG_META_BETREFF : '_cmx_beleg_betreff', true);

		$kontakt_label = (string) \get_post_meta($pid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_LABEL') ? CMX_BELEG_META_KONTAKT_LABEL : '_cmx_beleg_kontakt_label', true);
		$kontakt_id = (int) \get_post_meta($pid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ID') ? CMX_BELEG_META_KONTAKT_ID : '_cmx_beleg_kontakt_id', true);
		$kunde = $kontakt_label !== '' ? $kontakt_label : ($kontakt_id ? (\get_the_title($kontakt_id) ?: '') : '');

		$belegtyp = '';
		if (function_exists(__NAMESPACE__ . '\\cmx_get_beleg_type')) {
			[, $belegtyp] = cmx_get_beleg_type($post);
		}
		if ($belegtyp === '') {
			$tax = function_exists(__NAMESPACE__ . '\\cmx_belege_taxonomy') ? cmx_belege_taxonomy() : '';
			if ($tax && \taxonomy_exists($tax)) {
				$terms = \wp_get_post_terms($pid, $tax, ['fields' => 'slugs']);
				if (!\is_wp_error($terms) && !empty($terms)) $belegtyp = (string)$terms[0];
			}
		}

		$waehrung = (string) \get_post_meta($pid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_WAEHRUNG') ? CMX_BELEG_META_WAEHRUNG : '_cmx_beleg_waehrung', true);
		if ($waehrung === '') $waehrung = 'CHF';

		$total = 0.0;
		if (function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
			$calc = cmxbu_get_beleg_positionen_calc($pid);
			$total = (float)($calc['total'] ?? 0);
			$has_positions = cmxbu_beleg_has_positions($calc);
			if (!$has_positions) {
				$override = (string) \get_post_meta($pid, '_cmx_beleg_summe_override', true);
				if ($override !== '') {
					$total = (float) cmx_norm_decimal($override);
				}
			}
		}
		$total_str = number_format((float)$total, 2, ',', '');

		$row = [
			$pid,
			$belegnr,
			$belegtyp,
			$belegdatum,
			$faellig,
			$betreff,
			$kunde,
			$total_str,
			$waehrung,
		];
		fputcsv($fh, $row, ';');
	}
	fclose($fh);
	exit;
}
