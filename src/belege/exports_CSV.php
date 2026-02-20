<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/* ===== CSV Link ===== */
\add_action('admin_post_cmx_export_belege_list_csv', function(){
	if (!\current_user_can('edit_posts')) \wp_die('Keine Berechtigung.');
	if (!cmxbu_belege_export_verify_nonce('cmx_export_belege_list_csv')) \wp_die('Ungültige Anfrage.');
	cmxbu_belege_export_require_date_range_or_redirect();
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

function cmxbu_beleg_positions_line_sum(array $calc): float {
	$sum = 0.0;
	if (empty($calc['positionen']) || !\is_array($calc['positionen'])) return 0.0;
	foreach ($calc['positionen'] as $row) {
		if (!\is_array($row)) continue;
		if (($row['row_type'] ?? '') === 'abschnitt') continue;
		$sum += (float)($row['line_total'] ?? 0.0);
	}
	return (float) $sum;
}

function cmxbu_beleg_export_meta_float(int $post_id, string $meta_key): float {
	$raw = (string) \get_post_meta($post_id, $meta_key, true);
	if ($raw === '') return 0.0;
	if (\function_exists(__NAMESPACE__ . '\\cmx_norm_decimal')) {
		return (float) cmx_norm_decimal($raw);
	}
	$raw = \str_replace(["'", ' '], '', $raw);
	$raw = \str_replace(',', '.', $raw);
	return \is_numeric($raw) ? (float) $raw : 0.0;
}

function cmxbu_beleg_export_format_money(float $value): string {
	$rounded = \round((float) $value, 2);
	if (\abs($rounded) < 0.00001) return '';
	if (\function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')) {
		return (string) cmx_format_swiss_number($rounded, 2);
	}
	return \number_format($rounded, 2, '.', "'");
}

function cmxbu_beleg_export_format_percent(float $rate_decimal): string {
	$pct = (float) $rate_decimal * 100;
	if ($pct <= 0) return '';
	if (\function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')) {
		$txt = (string) cmx_format_swiss_number($pct, 2);
	} else {
		$txt = \number_format($pct, 2, '.', "'");
	}
	return \rtrim(\rtrim($txt, '0'), '.');
}

function cmxbu_beleg_export_first_term_name(int $post_id, ?string $taxonomy): string {
	if (!$taxonomy || !\taxonomy_exists($taxonomy)) return '';
	$terms = \wp_get_post_terms($post_id, $taxonomy, ['fields' => 'names']);
	if (\is_wp_error($terms) || empty($terms[0])) return '';
	return (string) $terms[0];
}

function cmxbu_beleg_export_normalize_any_date(string $raw_date): string {
	$raw_date = \trim($raw_date);
	if ($raw_date === '') return '';

	if (\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_normalize_date')) {
		$normalized = (string) cmxbu_belege_export_normalize_date($raw_date);
		if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
			return $normalized;
		}
	}

	if (\preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?$/', $raw_date, $m)) {
		$d = (int) $m[1];
		$mo = (int) $m[2];
		$y = (int) $m[3];
		if (\checkdate($mo, $d, $y)) {
			return \sprintf('%04d-%02d-%02d', $y, $mo, $d);
		}
	}

	if (\preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', $raw_date, $m)) {
		$y = (int) $m[1];
		$mo = (int) $m[2];
		$d = (int) $m[3];
		if (\checkdate($mo, $d, $y)) {
			return \sprintf('%04d-%02d-%02d', $y, $mo, $d);
		}
	}

	$ts = \strtotime($raw_date);
	return $ts ? \date('Y-m-d', $ts) : '';
}

function cmxbu_beleg_export_date_sort_key(string $date_ymd): int {
	return cmxbu_beleg_export_date_timestamp($date_ymd);
}

function cmxbu_beleg_export_date_timestamp(string $raw_date): int {
	$raw_date = \trim($raw_date);
	if ($raw_date === '') return 0;
	$normalized = cmxbu_beleg_export_normalize_any_date($raw_date);
	if ($normalized === '') return 0;
	$ts = \strtotime($normalized . ' 00:00:00');
	return $ts ? (int) $ts : 0;
}

function cmxbu_beleg_export_format_date_display(string $date_ymd): string {
	$raw = \trim($date_ymd);
	if ($raw === '') return '';
	$normalized = cmxbu_beleg_export_normalize_any_date($raw);
	if ($normalized === '') return $raw;
	$ts = \strtotime($normalized . ' 00:00:00');
	return $ts ? \date('d.m.Y', $ts) : $raw;
}

function cmxbu_beleg_export_ucfirst(string $text): string {
	$text = (string) $text;
	if ($text === '') return '';
	if (\function_exists('mb_substr') && \function_exists('mb_strtoupper')) {
		return \mb_strtoupper(\mb_substr($text, 0, 1, 'UTF-8'), 'UTF-8') . \mb_substr($text, 1, null, 'UTF-8');
	}
	return \strtoupper(\substr($text, 0, 1)) . \substr($text, 1);
}

function cmxbu_beleg_export_paid_date_key_from_display(string $display_date): string {
	$display_date = \trim($display_date);
	if ($display_date === '') return '';

	if (\preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $display_date, $m)) {
		$d = (int) $m[1];
		$mo = (int) $m[2];
		$y = (int) $m[3];
		if (\checkdate($mo, $d, $y)) {
			return \sprintf('%04d-%02d-%02d', $y, $mo, $d);
		}
	}

	return cmxbu_beleg_export_normalize_any_date($display_date);
}

function cmxbu_beleg_export_paid_date_timestamp_from_display(string $display_date): int {
	$display_date = \str_replace("\xC2\xA0", ' ', (string) $display_date);
	$display_date = \trim($display_date);
	if ($display_date === '') return 0;

	if (\preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $display_date, $m)) {
		$d = (int) $m[1];
		$mo = (int) $m[2];
		$y = (int) $m[3];
		if (\checkdate($mo, $d, $y)) {
			$ts = \strtotime(\sprintf('%04d-%02d-%02d 00:00:00', $y, $mo, $d));
			return $ts ? (int) $ts : 0;
		}
	}

	$normalized = cmxbu_beleg_export_paid_date_key_from_display($display_date);
	if ($normalized === '') return 0;
	$ts = \strtotime($normalized . ' 00:00:00');
	return $ts ? (int) $ts : 0;
}

function cmxbu_beleg_export_sort_plain_rows_by_paid_date(array $rows): array {
	if (\count($rows) <= 1) return $rows;

	$indexed = [];
	foreach ($rows as $idx => $row) {
		$row = (array) $row;
		$raw_date = (string) ($row[1] ?? '');
		$sort_ts = cmxbu_beleg_export_paid_date_timestamp_from_display($raw_date);
		$indexed[] = [
			'idx' => (int) $idx,
			'sort_ts' => $sort_ts,
			'row' => $row,
		];
	}

	\usort($indexed, static function (array $a, array $b): int {
		$ats = (int) ($a['sort_ts'] ?? 0);
		$bts = (int) ($b['sort_ts'] ?? 0);
		if ($ats !== $bts) return $bts <=> $ats; // newest first

		$ai = (int) ($a['idx'] ?? 0);
		$bi = (int) ($b['idx'] ?? 0);
		return $ai <=> $bi; // stable fallback
	});

	$sorted = [];
	foreach ($indexed as $entry) {
		$sorted[] = (array) ($entry['row'] ?? []);
	}
	return $sorted;
}

function cmxbu_beleg_export_sort_context_rows_by_paid_date(array $items): array {
	if (\count($items) <= 1) return $items;

	$indexed = [];
	foreach ($items as $idx => $item) {
		$item = (array) $item;
		$row = (array) ($item['row'] ?? []);
		$sort_ts = (int) ($item['sort_ts'] ?? 0);
		if ($sort_ts <= 0) {
			$raw_date = (string) ($row[1] ?? '');
			$sort_ts = cmxbu_beleg_export_paid_date_timestamp_from_display($raw_date);
		}
		$indexed[] = [
			'idx' => (int) $idx,
			'sort_ts' => $sort_ts,
			'item' => $item,
		];
	}

	\usort($indexed, static function (array $a, array $b): int {
		$ats = (int) ($a['sort_ts'] ?? 0);
		$bts = (int) ($b['sort_ts'] ?? 0);
		if ($ats !== $bts) return $bts <=> $ats; // newest first

		$ai = (int) ($a['idx'] ?? 0);
		$bi = (int) ($b['idx'] ?? 0);
		return $ai <=> $bi; // stable fallback
	});

	$sorted = [];
	foreach ($indexed as $entry) {
		$sorted[] = (array) ($entry['item'] ?? []);
	}
	return $sorted;
}

function cmxbu_beleg_export_to_float($raw): float {
	if ($raw === null) return 0.0;
	if (\is_int($raw) || \is_float($raw)) return (float) $raw;
	$txt = \trim((string) $raw);
	if ($txt === '') return 0.0;
	if (\function_exists(__NAMESPACE__ . '\\cmx_norm_decimal')) {
		return (float) cmx_norm_decimal($txt);
	}
	$txt = \str_replace(["'", ' '], '', $txt);
	$txt = \str_replace(',', '.', $txt);
	return \is_numeric($txt) ? (float) $txt : 0.0;
}

function cmxbu_beleg_export_partial_payments(int $post_id, ?string $zahlungsart_tax): array {
	$meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_ANZAHLUNGEN')
		? CMX_BELEG_META_ANZAHLUNGEN
		: '_cmx_beleg_anzahlungen';
	$raw = \get_post_meta($post_id, $meta_key, true);
	if (empty($raw)) return [];

	if (\is_string($raw)) {
		$decoded = \json_decode($raw, true);
		if (\json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
			$raw = $decoded;
		} else {
			$maybe = @\maybe_unserialize($raw);
			$raw = \is_array($maybe) ? $maybe : [];
		}
	}
	if (!\is_array($raw)) return [];

	$rows = [];
	foreach ($raw as $row) {
		if (!\is_array($row)) continue;
		$datum_raw = (string) ($row['datum'] ?? '');
		$datum = cmxbu_beleg_export_normalize_any_date($datum_raw);
		$betrag = (float) \abs(cmxbu_beleg_export_to_float($row['betrag'] ?? 0));
		if ($datum === '') continue;
		if ($betrag <= 0.0) continue;

		$zahlungsart_raw = \trim((string) ($row['zahlungsart'] ?? ''));
		$zahlungsart = $zahlungsart_raw;
		if ($zahlungsart_raw !== '' && $zahlungsart_tax && \taxonomy_exists($zahlungsart_tax) && \is_numeric($zahlungsart_raw)) {
			$term = \get_term((int) $zahlungsart_raw, $zahlungsart_tax);
			if ($term instanceof \WP_Term && !\is_wp_error($term)) {
				$zahlungsart = (string) $term->name;
			}
		}

		$rows[] = [
			'datum' => $datum,
			'betrag' => $betrag,
			'zahlungsart' => $zahlungsart,
		];
	}

	if (\count($rows) > 1) {
		\usort($rows, static function (array $a, array $b): int {
			$ad = (string) ($a['datum'] ?? '');
			$bd = (string) ($b['datum'] ?? '');
			if ($ad === $bd) return 0;
			if ($ad === '') return 1;
			if ($bd === '') return -1;
			return \strcmp($ad, $bd);
		});
	}

	return $rows;
}

function cmxbu_beleg_export_raw_type(\WP_Post $post): string {
	$type = '';
	if (\function_exists(__NAMESPACE__ . '\\cmx_get_beleg_type')) {
		[, $type] = cmx_get_beleg_type($post);
	}
	if ($type === '' && \function_exists(__NAMESPACE__ . '\\cmx_belege_taxonomy')) {
		$tax = (string) cmx_belege_taxonomy();
		if ($tax !== '' && \taxonomy_exists($tax)) {
			$terms = \wp_get_post_terms((int) $post->ID, $tax, ['fields' => 'slugs']);
			if (!\is_wp_error($terms) && !empty($terms[0])) {
				$type = (string) $terms[0];
			}
		}
	}
	return \strtolower(\trim((string) $type));
}

function cmxbu_beleg_export_normalize_type(string $type): string {
	$type = \strtolower(\sanitize_key($type));
	$map = [
		'rechnungen' => 'rechnung',
		'quittungen' => 'quittung',
		'gutschriften' => 'gutschrift',
	];
	return $map[$type] ?? $type;
}

function cmxbu_beleg_export_is_allowed_base_type(string $type): bool {
	$type = cmxbu_beleg_export_normalize_type($type);
	return \in_array($type, ['rechnung', 'quittung', 'gutschrift'], true);
}

function cmxbu_beleg_export_is_paid_or_partial(string $status, string $bezahlt_am = '', int $post_id = 0): bool {
	$status = \sanitize_key($status);
	$bezahlt_am = cmxbu_beleg_export_normalize_any_date($bezahlt_am);
	if ($bezahlt_am !== '') {
		return true;
	}

	if ($post_id > 0 && $status === 'teilbezahlt') {
		$partials = cmxbu_beleg_export_partial_payments($post_id, null);
		if (!empty($partials)) {
			return true;
		}
	}

	if ($post_id > 0 && \function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_partial_payment_dates')) {
		return !empty(cmxbu_belege_export_partial_payment_dates($post_id));
	}

	return false;
}

function cmxbu_beleg_export_effective_type(\WP_Post $post, string $raw_type = ''): string {
	$type = $raw_type !== '' ? $raw_type : cmxbu_beleg_export_raw_type($post);
	if ($type !== '' && \function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_pdf_effective_type')) {
		$type = (string) cmxbu_get_beleg_pdf_effective_type((int) $post->ID, $type);
	}
	return \strtolower(\trim((string) $type));
}

function cmxbu_beleg_export_mwst_context(int $post_id, string $effective_type): array {
	$opts_general = (array) \get_option('cmx_einstellungen', []);
	$is_brutto = \get_post_meta($post_id, '_cmx_beleg_is_brutto', true) === '1';

	$mwst_rate = 0.0;
	if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_mwst_term_data')) {
		$mwst_term_id = (int) \get_post_meta($post_id, '_cmx_beleg_mwst_term', true);
		$mwst_data = (array) cmxbu_get_mwst_term_data($mwst_term_id);
		$mwst_rate = (float) ($mwst_data['rate'] ?? 0.0);
	}

	$mwst_allowed = \function_exists(__NAMESPACE__ . '\\cmx_belege_allows_mwst_for_type')
		? cmx_belege_allows_mwst_for_type((string) $effective_type, $opts_general)
		: (\function_exists(__NAMESPACE__ . '\\cmx_belege_is_mwst_pflichtig')
			? cmx_belege_is_mwst_pflichtig($opts_general)
			: !empty($opts_general['mwst_pflichtig']));

	if (!$mwst_allowed) {
		$mwst_rate = 0.0;
		$is_brutto = false;
	}

	return [
		'rate' => max(0.0, (float) $mwst_rate),
		'is_brutto' => (bool) $is_brutto,
	];
}

function cmxbu_beleg_export_calc(int $post_id, float $mwst_rate, bool $is_brutto): array {
	$calc = \function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')
		? (array) cmxbu_get_beleg_positionen_calc($post_id, [
			'round_decimals' => 2,
			'round_lines' => true,
			'round_totals' => true,
			'tax_rate' => $mwst_rate,
			'is_brutto' => $is_brutto,
		])
		: [];

	$has_positions = cmxbu_beleg_has_positions($calc);
	$line_sum = cmxbu_beleg_positions_line_sum($calc);
	$manual_total = cmxbu_beleg_export_meta_float($post_id, '_cmx_beleg_summe_override');
	$has_manual_total = ((string) \get_post_meta($post_id, '_cmx_beleg_summe_override', true) !== '');

	$subtotal_base = $has_positions
		? $line_sum
		: ($has_manual_total
			? $manual_total
			: (float) ($is_brutto ? ($calc['gross'] ?? 0.0) : ($calc['subtotal'] ?? 0.0)));

	return [
		'tax_amount' => (float) ($calc['tax_amount'] ?? 0.0),
		'total' => (float) ($calc['total'] ?? 0.0),
		'subtotal_base' => (float) $subtotal_base,
		'is_brutto' => (bool) $is_brutto,
	];
}

function cmxbu_beleg_export_headers(): array {
	return [
		'Belegnummer',
		'Bezahlt am',
		'Belegtyp',
		'Kontakt',
		'Zahlungsart',
		'Zahlungsgrund',
		'MwSt-Satz',
		'MwSt',
		'Vorsteuer',
		'Einnahmen',
		'Ausgaben',
	];
}

function cmxbu_beleg_export_rows_from_ids(array $ids, bool $with_context = false): array {
	$range = \function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_requested_date_range')
		? (array) cmxbu_belege_export_requested_date_range()
		: ['from' => '', 'to' => ''];

	$export_rows = [];
	$seq = 0;

	foreach ($ids as $pid) {
		$post = \get_post($pid);
		if (!$post) continue;
		$bezahlt_am = (string) \get_post_meta(
			$pid,
			\defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM') ? CMX_BELEG_META_BEZAHLT_AM : '_cmx_beleg_bezahlt_am',
			true
		);
		$bezahlt_am = cmxbu_beleg_export_normalize_any_date($bezahlt_am);
		$status = \sanitize_key((string) \get_post_meta(
			$pid,
			\defined(__NAMESPACE__ . '\\CMX_BELEG_META_STATUS') ? CMX_BELEG_META_STATUS : '_cmx_beleg_status',
			true
		));
		if (!cmxbu_beleg_export_is_paid_or_partial($status, $bezahlt_am, $pid)) {
			continue;
		}

		$belegnr = (string) $post->post_title;

		$kontakt_label = (string) \get_post_meta($pid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_LABEL') ? CMX_BELEG_META_KONTAKT_LABEL : '_cmx_beleg_kontakt_label', true);
		$kontakt_id = (int) \get_post_meta($pid, \defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ID') ? CMX_BELEG_META_KONTAKT_ID : '_cmx_beleg_kontakt_id', true);
		$kontakt = $kontakt_label !== '' ? $kontakt_label : ($kontakt_id ? (\get_the_title($kontakt_id) ?: '') : '');

			$raw_type = cmxbu_beleg_export_raw_type($post);
			$belegtyp = cmxbu_beleg_export_normalize_type($raw_type);
			if (!cmxbu_beleg_export_is_allowed_base_type($belegtyp)) {
				continue;
			}
			$belegtyp_display = cmxbu_beleg_export_ucfirst($belegtyp);
			$effective_type = cmxbu_beleg_export_effective_type($post, $raw_type);
		$richtung = \sanitize_key((string) \get_post_meta(
			$pid,
			\defined(__NAMESPACE__ . '\\CMX_BELEG_META_RICHTUNG') ? CMX_BELEG_META_RICHTUNG : '_cmx_beleg_richtung',
			true
		));
		if ($richtung === 'ausgabe') {
			$richtung = 'eingang';
		}

		$zahlungsart_tax = \function_exists(__NAMESPACE__ . '\\cmx_beleg_zahlungsart_tax')
			? cmx_beleg_zahlungsart_tax()
			: null;
		$zahlungsgrund_tax = \function_exists(__NAMESPACE__ . '\\cmx_beleg_zahlungsgrund_tax')
			? cmx_beleg_zahlungsgrund_tax()
			: null;
		$zahlungsart = cmxbu_beleg_export_first_term_name($pid, $zahlungsart_tax);
		$zahlungsgrund = cmxbu_beleg_export_first_term_name($pid, $zahlungsgrund_tax);

		$mwst_ctx = cmxbu_beleg_export_mwst_context($pid, $effective_type);
		$mwst_rate = (float) ($mwst_ctx['rate'] ?? 0.0);
		$is_brutto = !empty($mwst_ctx['is_brutto']);
		$calc = cmxbu_beleg_export_calc($pid, $mwst_rate, $is_brutto);

		$tax_amount = max(0.0, (float) ($calc['tax_amount'] ?? 0.0));
		$total = (float) ($calc['total'] ?? 0.0);
		$subtotal_base = (float) ($calc['subtotal_base'] ?? 0.0);

		$is_outgoing_invoice = ($richtung === 'ausgang')
			&& \in_array($belegtyp, ['rechnung', 'quittung'], true);
		$is_supplier_invoice = ($richtung === 'eingang')
			&& \in_array($belegtyp, ['rechnung', 'quittung'], true);

		$mwst = $is_outgoing_invoice ? $tax_amount : 0.0;
		$vorsteuer = $is_supplier_invoice ? $tax_amount : 0.0;

		$einnahmen = $is_outgoing_invoice ? $total : 0.0;

		$ausgaben = $is_supplier_invoice ? $total : 0.0;

			if ($status === 'teilbezahlt') {
				$partials = cmxbu_beleg_export_partial_payments($pid, $zahlungsart_tax);
				if (!empty($partials)) {
					foreach ($partials as $partial) {
					$partial_amount = (float) \abs((float) ($partial['betrag'] ?? 0.0));
					if ($partial_amount <= 0.0) continue;

					$partial_date = (string) ($partial['datum'] ?? '');
					if ($partial_date === '') continue;
					$partial_sort_date = cmxbu_beleg_export_normalize_any_date($partial_date);
					if ($partial_sort_date === '') continue;
					if (\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_date_in_range') && !cmxbu_belege_export_date_in_range($partial_date, $range)) {
						continue;
					}
						$partial_art = (string) ($partial['zahlungsart'] ?? '');
						if ($partial_art === '') $partial_art = $zahlungsart;

							$partial_einnahmen = $is_outgoing_invoice ? $total : 0.0;
							$partial_ausgaben = $is_supplier_invoice ? $total : 0.0;
							$partial_ratio = $total > 0.0 ? ($partial_amount / $total) : 0.0;
							$partial_tax_amount = max(0.0, (float) $tax_amount * (float) $partial_ratio);
							$partial_mwst = $is_outgoing_invoice ? $partial_tax_amount : 0.0;
							$partial_vorsteuer = $is_supplier_invoice ? $partial_tax_amount : 0.0;

							$partial_row = [
								$belegnr,
								cmxbu_beleg_export_format_date_display($partial_date),
								$belegtyp_display,
						$kontakt,
							$partial_art,
							$zahlungsgrund,
							cmxbu_beleg_export_format_percent($mwst_rate),
							cmxbu_beleg_export_format_money($partial_mwst),
							cmxbu_beleg_export_format_money($partial_vorsteuer),
								cmxbu_beleg_export_format_money($partial_einnahmen),
								cmxbu_beleg_export_format_money($partial_ausgaben),
							];
						$export_rows[] = [
							'sort_date' => $partial_sort_date,
							'sort_ts' => cmxbu_beleg_export_date_sort_key($partial_sort_date),
							'sort_seq' => $seq++,
							'post_id' => (int) $pid,
							'row' => $partial_row,
						];
					}
					continue;
				}
			if ($bezahlt_am === '') {
				continue;
			}
		}

				$row = [
					$belegnr,
					cmxbu_beleg_export_format_date_display($bezahlt_am),
					$belegtyp_display,
					$kontakt,
					$zahlungsart,
			$zahlungsgrund,
			cmxbu_beleg_export_format_percent($mwst_rate),
			cmxbu_beleg_export_format_money($mwst),
			cmxbu_beleg_export_format_money($vorsteuer),
			cmxbu_beleg_export_format_money($einnahmen),
			cmxbu_beleg_export_format_money($ausgaben),
		];
			if (\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_date_in_range') && !cmxbu_belege_export_date_in_range($bezahlt_am, $range)) {
				continue;
			}
			$paid_sort_date = cmxbu_beleg_export_normalize_any_date($bezahlt_am);
			if ($paid_sort_date === '') {
				continue;
			}
			$export_rows[] = [
				'sort_date' => $paid_sort_date,
				'sort_ts' => cmxbu_beleg_export_date_sort_key($paid_sort_date),
				'sort_seq' => $seq++,
				'post_id' => (int) $pid,
				'row' => $row,
			];
		}

	if (\count($export_rows) > 1) {
		\usort($export_rows, static function (array $a, array $b): int {
			$adate = (string) ($a['sort_date'] ?? '');
			$bdate = (string) ($b['sort_date'] ?? '');
			if ($adate !== '' || $bdate !== '') {
				if ($adate === '') return 1;
				if ($bdate === '') return -1;
				if ($adate !== $bdate) return \strcmp($bdate, $adate); // newest date first
			}

			$ats = (int) ($a['sort_ts'] ?? 0);
			$bts = (int) ($b['sort_ts'] ?? 0);

			if ($ats <= 0 || $bts <= 0) {
				$arow = (array) ($a['row'] ?? []);
				$brow = (array) ($b['row'] ?? []);
				if ($ats <= 0) {
					$ats = cmxbu_beleg_export_date_timestamp((string) ($arow[1] ?? ''));
				}
				if ($bts <= 0) {
					$bts = cmxbu_beleg_export_date_timestamp((string) ($brow[1] ?? ''));
				}
			}

			if ($ats !== $bts) return $bts <=> $ats; // newest first
			$aseq = (int) ($a['sort_seq'] ?? 0);
			$bseq = (int) ($b['sort_seq'] ?? 0);
			return $bseq <=> $aseq;
		});
	}

	$result = [];
	foreach ($export_rows as $entry) {
		$row = (array) ($entry['row'] ?? []);
		if ($with_context) {
			$result[] = [
				'post_id' => (int) ($entry['post_id'] ?? 0),
				'sort_ts' => (int) ($entry['sort_ts'] ?? 0),
				'sort_seq' => (int) ($entry['sort_seq'] ?? 0),
				'row' => $row,
			];
			continue;
		}
		$result[] = $row;
	}
	return $result;
}

function cmxbu_stream_belege_csv_from_ids(array $ids): void {
	\ignore_user_abort(true); if (function_exists('set_time_limit')) @set_time_limit(0);
	while (ob_get_level()>0){ @ob_end_clean(); } \nocache_headers();

	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="'.cmxbu_belege_export_filename('csv').'"');
	header('Pragma: no-cache'); header('Expires: 0');

	$fh = fopen('php://output','w'); fwrite($fh, "\xEF\xBB\xBF");
	$headers = cmxbu_beleg_export_headers();
	fputcsv($fh, $headers, ';');
	$rows = cmxbu_beleg_export_rows_from_ids($ids);
	foreach ($rows as $row) {
		fputcsv($fh, $row, ';');
	}
	fclose($fh);
	exit;
}
