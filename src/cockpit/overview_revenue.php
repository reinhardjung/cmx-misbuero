<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_overview_revenue_preset_options')) {
	function cmx_cockpit_overview_revenue_preset_options(): array {
		if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_preset_options')) {
			return (array) cmx_cockpit_preset_options();
		}

		$fallback = [
			'heute' => 'Heute (heute bis heute)',
			'diesen_monat' => 'Diesen Monat',
			'letzten_monat' => 'Letzten Monat',
			'vorletzten_monat' => 'Vorletzten Monat',
			'dieses_quartal' => 'Dieses Quartal',
			'letztes_quartal' => 'Letztes Quartal',
			'vorletztes_quartal' => 'Vorletztes Quartal',
			'dieses_jahr' => 'Dieses Jahr',
			'letztes_jahr' => 'Letztes Jahr',
			'vorletztes_jahr' => 'Vorletztes Jahr',
		];

		if (\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_presets')) {
			$presets = (array) cmxbu_belege_export_presets();
			unset($presets['benutzerdefiniert']);
			if (!empty($presets)) {
				return $presets;
			}
		}

		return $fallback;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_overview_revenue_requested_preset')) {
	function cmx_cockpit_overview_revenue_requested_preset(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_requested_preset')) {
			return (string) cmx_cockpit_requested_preset();
		}

		$presets = cmx_cockpit_overview_revenue_preset_options();
		$preset = \sanitize_key((string) ($_GET['cmx_overview_revenue_preset'] ?? 'dieses_jahr'));
		return isset($presets[$preset]) ? $preset : 'dieses_jahr';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_overview_revenue_format_money')) {
	function cmx_cockpit_overview_revenue_format_money(float $amount): string {
		$rounded = \round($amount, 2);

		if (\function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')) {
			return (string) cmx_format_swiss_number($rounded, 2);
		}

		return \number_format($rounded, 2, '.', "'");
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_overview_revenue_to_float')) {
	function cmx_cockpit_overview_revenue_to_float($value): float {
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_to_float')) {
			return (float) cmxbu_beleg_export_to_float($value);
		}

		$txt = \trim((string) $value);
		if ($txt === '') {
			return 0.0;
		}

		$txt = \str_replace(["'", ' '], '', $txt);
		$txt = \str_replace(',', '.', $txt);
		return \is_numeric($txt) ? (float) $txt : 0.0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_overview_revenue_type_for_post')) {
	function cmx_cockpit_overview_revenue_type_for_post(int $post_id): string {
		static $cache = [];
		if (isset($cache[$post_id])) {
			return $cache[$post_id];
		}

		$post = \get_post($post_id);
		if (!$post instanceof \WP_Post) {
			$cache[$post_id] = '';
			return '';
		}

		$type = '';
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_raw_type')) {
			$type = (string) cmxbu_beleg_export_raw_type($post);
		}
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_normalize_type')) {
			$type = (string) cmxbu_beleg_export_normalize_type($type);
		}

		if (\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_is_allowed_base_type') && !cmxbu_beleg_export_is_allowed_base_type($type)) {
			$type = '';
		}

		$cache[$post_id] = $type;
		return $type;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_overview_revenue_normalize_decimal')) {
	function cmx_cockpit_overview_revenue_normalize_decimal(string $value): string {
		$value = \trim((string) $value);
		$value = \str_replace(["\xc2\xa0", ' ', "'"], '', $value);
		$value = (string) \preg_replace('/[^\d,\.\+\-]/u', '', $value);
		if ($value === '' || $value === '+' || $value === '-') {
			return '0';
		}

		$sign = '';
		if ($value[0] === '+' || $value[0] === '-') {
			$sign = $value[0];
			$value = (string) \substr($value, 1);
		}

		$value = \str_replace(['+', '-'], '', $value);
		if ($value === '') {
			return '0';
		}

		$has_comma = (\strpos($value, ',') !== false);
		$has_dot = (\strpos($value, '.') !== false);
		if ($has_comma && $has_dot) {
			if (\strrpos($value, ',') > \strrpos($value, '.')) {
				$value = \str_replace('.', '', $value);
				$value = \str_replace(',', '.', $value);
			} else {
				$value = \str_replace(',', '', $value);
			}
			return $sign . $value;
		}

		if ($has_comma || $has_dot) {
			$sep = $has_comma ? ',' : '.';
			$parts = \explode($sep, $value);
			$left_part = $parts[0] ?? '';
			$left_digits = \ltrim($left_part, '+-');

			if (\count($parts) > 2) {
				$value = \implode('', $parts);
			} elseif (\count($parts) === 2) {
				$right_part = $parts[1] ?? '';
				$looks_thousands = \preg_match('/^\d{3}$/', $right_part) && \preg_match('/^\d{1,3}$/', $left_digits);
				if ($looks_thousands) {
					$value = $left_part . $right_part;
				} elseif ($sep === ',') {
					$value = $left_part . '.' . $right_part;
				}
			} elseif ($sep === ',') {
				$value = \str_replace(',', '.', $value);
			}
		}

		return $sign . $value;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_overview_revenue_round_5rp')) {
	function cmx_cockpit_overview_revenue_round_5rp(float $amount): float {
		return \round($amount * 20) / 20;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_overview_revenue_parse_rabatt')) {
	function cmx_cockpit_overview_revenue_parse_rabatt(float $subtotal, $raw): float {
		if ($raw === null || $raw === '') {
			return 0.0;
		}

		$text = \strtolower(\trim((string) $raw));
		$base = \abs($subtotal);
		if (\substr($text, -1) === '%') {
			$percent = (float) cmx_cockpit_overview_revenue_normalize_decimal((string) \substr($text, 0, -1));
			$discount = \max(0.0, $base * ($percent / 100));
		} else {
			$text = (string) \preg_replace('/\s*(chf|fr\.?)\s*/i', '', $text);
			$discount = \max(0.0, (float) cmx_cockpit_overview_revenue_normalize_decimal($text));
		}

		if ($discount > $base) {
			$discount = $base;
		}

		return ($subtotal >= 0 ? 1 : -1) * $discount;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_overview_revenue_load_positionen')) {
	function cmx_cockpit_overview_revenue_load_positionen(int $post_id): array {
		$raw = \get_post_meta($post_id, '_cmx_beleg_positionen', true);
		if (empty($raw)) {
			return [];
		}

		$positionen = \maybe_unserialize($raw);
		if (\is_string($positionen)) {
			$decoded = \json_decode($positionen, true);
			if (\json_last_error() === JSON_ERROR_NONE) {
				$positionen = $decoded;
			}
		}

		return \is_array($positionen) ? $positionen : [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_overview_revenue_has_positionen_data')) {
	function cmx_cockpit_overview_revenue_has_positionen_data(array $positionen): bool {
		foreach ($positionen as $pos) {
			if (!\is_array($pos)) {
				continue;
			}
			if (!empty($pos['artikel_id']) && (int) $pos['artikel_id'] > 0) {
				return true;
			}
			$name = \trim((string) ($pos['artikel_name'] ?? $pos['item'] ?? $pos['title'] ?? ''));
			if ($name !== '') {
				return true;
			}
			$menge = \trim((string) ($pos['menge'] ?? ''));
			$preis = \trim((string) ($pos['preis'] ?? ''));
			$rabatt = \trim((string) ($pos['rabatt'] ?? ''));
			if ($menge !== '' || $preis !== '' || $rabatt !== '') {
				return true;
			}
		}

		return false;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_overview_revenue_beleg_amount')) {
	function cmx_cockpit_overview_revenue_beleg_amount(int $post_id): float {
		$positionen = cmx_cockpit_overview_revenue_load_positionen($post_id);
		$has_positions = cmx_cockpit_overview_revenue_has_positionen_data($positionen);
		$manual_total_raw = (string) \get_post_meta($post_id, '_cmx_beleg_summe_override', true);
		if (!$has_positions && $manual_total_raw !== '') {
			return (float) cmx_cockpit_overview_revenue_normalize_decimal($manual_total_raw);
		}

		$summe = 0.0;
		foreach ($positionen as $pos) {
			if (!\is_array($pos)) {
				continue;
			}
			$menge = isset($pos['menge'])
				? (float) cmx_cockpit_overview_revenue_normalize_decimal((string) $pos['menge'])
				: 0.0;
			$preis = isset($pos['preis'])
				? (float) cmx_cockpit_overview_revenue_normalize_decimal((string) $pos['preis'])
				: 0.0;
			$subtotal = $menge * $preis;
			$rabatt = cmx_cockpit_overview_revenue_parse_rabatt($subtotal, $pos['rabatt'] ?? '');
			$summe += cmx_cockpit_overview_revenue_round_5rp($subtotal - $rabatt);
		}

		if ($has_positions) {
			return (float) \round($summe, 2);
		}

		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
			$calc = (array) cmxbu_get_beleg_positionen_calc($post_id);
			if (isset($calc['total'])) {
				return (float) $calc['total'];
			}
		}

		return 0.0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_overview_revenue_type_options')) {
	function cmx_cockpit_overview_revenue_type_options(): array {
		static $cache = null;
		if (\is_array($cache)) {
			return $cache;
		}

		$options = ['overview' => 'Übersicht'];
		$post_ids = \get_posts([
			'post_type' => 'belege',
			'post_status' => ['publish', 'private'],
			'fields' => 'ids',
			'posts_per_page' => -1,
			'orderby' => 'ID',
			'order' => 'ASC',
			'no_found_rows' => true,
			'suppress_filters' => true,
		]);

		foreach ((array) $post_ids as $post_id) {
			$type = cmx_cockpit_overview_revenue_type_for_post((int) $post_id);
			if ($type === '') {
				continue;
			}

			$label = \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_ucfirst')
				? (string) cmxbu_beleg_export_ucfirst($type)
				: \ucfirst($type);
			$options[$type] = $label;
		}

		if (\count($options) > 2) {
			$overview = $options['overview'];
			unset($options['overview']);
			\asort($options, \SORT_NATURAL | \SORT_FLAG_CASE);
			$options = ['overview' => $overview] + $options;
		}

		$cache = $options;
		return $cache;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_overview_revenue_requested_type')) {
	function cmx_cockpit_overview_revenue_requested_type(): string {
		$options = cmx_cockpit_overview_revenue_type_options();
		$type = \sanitize_key((string) ($_GET['cmx_overview_revenue_type'] ?? 'overview'));
		return isset($options[$type]) ? $type : 'overview';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_overview_revenue_type_label')) {
	function cmx_cockpit_overview_revenue_type_label(string $type): string {
		if ($type === '') {
			return '';
		}

		$options = cmx_cockpit_overview_revenue_type_options();
		if (isset($options[$type])) {
			return (string) $options[$type];
		}

		return \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_ucfirst')
			? (string) cmxbu_beleg_export_ucfirst($type)
			: \ucfirst($type);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_overview_revenue_beleg_taxonomy')) {
	function cmx_cockpit_overview_revenue_beleg_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_taxonomy')) {
			$tax = (string) cmx_cockpit_beleg_taxonomy();
			if ($tax !== '' && \taxonomy_exists($tax)) {
				return $tax;
			}
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_belege_taxonomy')) {
			$tax = (string) cmx_belege_taxonomy();
			if ($tax !== '' && \taxonomy_exists($tax)) {
				return $tax;
			}
		}

		foreach (['belege_kategorien', 'beleg_kategorie'] as $tax) {
			if (\taxonomy_exists($tax)) {
				return $tax;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_overview_revenue_type_admin_url')) {
	function cmx_cockpit_overview_revenue_type_admin_url(string $type): string {
		$args = ['post_type' => 'belege'];
		$tax = cmx_cockpit_overview_revenue_beleg_taxonomy();
		if ($tax !== '' && $type !== '') {
			$args[$tax] = $type;
		}

		return (string) \add_query_arg($args, \admin_url('edit.php'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_overview_revenue_summary')) {
		function cmx_cockpit_overview_revenue_summary(string $preset, string $type): array {
			$summary = [
				'einnahmen' => 0.0,
				'ausgaben' => 0.0,
				'gewinn' => 0.0,
				'mwst_geschuldet' => 0.0,
				'mwst_vorsteuer' => 0.0,
				'count' => 0,
				'items' => [],
			];

		if (
			!\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_collect_ids')
			|| !\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_effective_type')
			|| !\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_mwst_context')
			|| !\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')
			|| !\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_raw_type')
			|| !\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_normalize_richtung')
			|| !\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_direction_side_map')
		) {
			return $summary;
		}

		$request_backup = $_REQUEST;
		$_REQUEST['cmx_export_range_preset'] = $preset;
		unset($_REQUEST['cmx_export_date_from'], $_REQUEST['cmx_export_date_to']);

		$post_ids = (array) cmxbu_belege_export_collect_ids();
		$_REQUEST = $request_backup;

		foreach ($post_ids as $raw_post_id) {
			$post_id = (int) $raw_post_id;
			if ($post_id <= 0) {
				continue;
			}

			$post_type = cmx_cockpit_overview_revenue_type_for_post($post_id);
			if ($post_type === '' || ($type !== 'overview' && $post_type !== $type)) {
				continue;
			}

			$post = \get_post($post_id);
			if (!$post instanceof \WP_Post) {
				continue;
			}

			$raw_type = (string) cmxbu_beleg_export_raw_type($post);
			$effective_type = (string) cmxbu_beleg_export_effective_type($post, $raw_type);
			$mwst_ctx = (array) cmxbu_beleg_export_mwst_context($post_id, $effective_type);
			$calc = (array) cmxbu_get_beleg_positionen_calc($post_id, [
				'round_decimals' => 2,
				'round_lines' => true,
				'round_totals' => true,
				'tax_rate' => (float) ($mwst_ctx['rate'] ?? 0.0),
				'is_brutto' => !empty($mwst_ctx['is_brutto']),
			]);

			$display_amount = cmx_cockpit_overview_revenue_beleg_amount($post_id);
			$tax_amount = (float) ($calc['tax_amount'] ?? 0.0);

			$richtung_raw = (string) \get_post_meta(
				$post_id,
				\defined(__NAMESPACE__ . '\\CMX_BELEG_META_RICHTUNG') ? CMX_BELEG_META_RICHTUNG : '_cmx_beleg_richtung',
				true
			);
			$richtung = (string) cmxbu_beleg_export_normalize_richtung($richtung_raw);
			$legacy_income_direction = \in_array($richtung_raw, ['einnahme', 'einnahmen', 'income', 'revenues'], true);
			$legacy_expense_direction = \in_array($richtung_raw, ['ausgabe', 'ausgaben', 'expense', 'expenses'], true);
			$richtung_side_map = (array) cmxbu_beleg_export_direction_side_map($post_type);

			if ($legacy_income_direction || $legacy_expense_direction) {
				$is_income_side = $legacy_income_direction;
				$is_expense_side = $legacy_expense_direction;
			} else {
				$side = (string) ($richtung_side_map[$richtung] ?? '');
				$is_income_side = ($side === 'income');
				$is_expense_side = ($side === 'expense');
			}

				$summary['einnahmen'] += $is_income_side ? $display_amount : 0.0;
				$summary['ausgaben'] += $is_expense_side ? $display_amount : 0.0;
				$summary['mwst_geschuldet'] += $is_income_side ? $tax_amount : 0.0;
				$summary['mwst_vorsteuer'] += $is_expense_side ? $tax_amount : 0.0;
				$summary['count']++;
					$summary['items'][] = [
						'post_id' => $post_id,
						'title' => (string) $post->post_title,
						'type' => $post_type,
						'type_label' => (string) cmx_cockpit_overview_revenue_type_label($post_type),
						'amount' => (float) $display_amount,
						'side' => $is_income_side ? 'income' : ($is_expense_side ? 'expense' : ''),
					];
				}

		$summary['einnahmen'] = (float) \round((float) $summary['einnahmen'], 2);
		$summary['ausgaben'] = (float) \round((float) $summary['ausgaben'], 2);
		$summary['mwst_geschuldet'] = (float) \round((float) $summary['mwst_geschuldet'], 2);
		$summary['mwst_vorsteuer'] = (float) \round((float) $summary['mwst_vorsteuer'], 2);
		$summary['gewinn'] = (float) \round(
			(float) $summary['einnahmen'] - (float) $summary['ausgaben'],
			2
		);
		return $summary;
	}
}

\add_action('wp_dashboard_setup', __NAMESPACE__ . '\\cmx_register_overview_revenue_widget');

if (!\function_exists(__NAMESPACE__ . '\\cmx_register_overview_revenue_widget')) {
	function cmx_register_overview_revenue_widget(): void {
		\wp_add_dashboard_widget(
			'cmx_overview_revenue_widget',
			'Übersicht',
			__NAMESPACE__ . '\\cmx_render_overview_revenue_widget'
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_overview_revenue_widget')) {
	function cmx_render_overview_revenue_widget(): void {
		if (!\current_user_can('edit_posts')) {
			echo '<p>Keine Berechtigung.</p>';
			return;
		}

		$presets = cmx_cockpit_overview_revenue_preset_options();
		$type_options = cmx_cockpit_overview_revenue_type_options();
		$selected_preset = cmx_cockpit_overview_revenue_requested_preset();
		$selected_type = cmx_cockpit_overview_revenue_requested_type();
		$summary = cmx_cockpit_overview_revenue_summary($selected_preset, $selected_type);

			echo '<style>
				#cmx_overview_revenue_widget .cmx-overview-revenue-form{margin:0 0 14px}
				#cmx_overview_revenue_widget .cmx-overview-revenue-controls{display:flex;flex-wrap:wrap;gap:10px 14px;align-items:end}
			#cmx_overview_revenue_widget .cmx-overview-revenue-field{display:flex;flex-direction:column;gap:4px;min-width:160px}
			#cmx_overview_revenue_widget .cmx-overview-revenue-field label{font-size:12px;font-weight:600;color:#50575e}
			#cmx_overview_revenue_widget .cmx-overview-revenue-field select{max-width:100%}
			#cmx_overview_revenue_widget .cmx-overview-revenue-table{margin-top:4px}
			#cmx_overview_revenue_widget .cmx-overview-revenue-row{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:baseline;padding:3px 0}
			#cmx_overview_revenue_widget .cmx-overview-revenue-label{font-weight:500;color:#1d2327}
			#cmx_overview_revenue_widget .cmx-overview-revenue-value{font-size:13px;font-weight:700;letter-spacing:.01em}
			#cmx_overview_revenue_widget .cmx-overview-revenue-divider{border-top:2px solid #1d2327;margin:8px 0}
				#cmx_overview_revenue_widget .cmx-overview-revenue-profit .cmx-overview-revenue-label,
				#cmx_overview_revenue_widget .cmx-overview-revenue-profit .cmx-overview-revenue-value{font-weight:800}
				#cmx_overview_revenue_widget .cmx-overview-revenue-mwst{margin-top:12px;padding-top:10px;border-top:1px solid #dcdcde}
				#cmx_overview_revenue_widget .cmx-overview-revenue-mwst .cmx-overview-revenue-row{padding:2px 0}
				#cmx_overview_revenue_widget .cmx-overview-revenue-meta{margin-top:10px;font-size:11px;color:#646970}
				#cmx_overview_revenue_widget .cmx-overview-revenue-details{margin-top:10px}
				#cmx_overview_revenue_widget .cmx-overview-revenue-details summary{cursor:pointer;color:#2271b1}
				#cmx_overview_revenue_widget .cmx-overview-revenue-detail-list{margin-top:8px;border-top:1px solid #dcdcde}
				#cmx_overview_revenue_widget .cmx-overview-revenue-detail-row{display:grid;grid-template-columns:132px minmax(0,1fr) auto;gap:6px;padding:3px 0;align-items:center}
				#cmx_overview_revenue_widget .cmx-overview-revenue-detail-title{color:#1d2327;min-width:0}
				#cmx_overview_revenue_widget .cmx-overview-revenue-detail-title a{display:inline-block;color:#2271b1;text-decoration:none;white-space:nowrap;word-break:keep-all;overflow-wrap:normal}
				#cmx_overview_revenue_widget .cmx-overview-revenue-detail-title a:hover{text-decoration:underline}
				#cmx_overview_revenue_widget .cmx-overview-revenue-detail-type{justify-self:start;text-align:left;white-space:nowrap}
				#cmx_overview_revenue_widget .cmx-overview-revenue-detail-type-badge{display:inline-flex;align-items:center;justify-content:center;padding:0 7px;border:1px solid #c8daf6;border-radius:4px;background:#eef5ff;color:#466792;line-height:1.25;text-align:center}
				#cmx_overview_revenue_widget .cmx-overview-revenue-detail-type-link{text-decoration:none}
				#cmx_overview_revenue_widget .cmx-overview-revenue-detail-type-link:hover .cmx-overview-revenue-detail-type-badge{background:#e4efff;border-color:#a8c6f0}
				#cmx_overview_revenue_widget .cmx-overview-revenue-detail-value{font-weight:600;text-align:right;white-space:nowrap}
			</style>';

		echo '<form method="get" action="' . \esc_url(\admin_url('index.php')) . '" class="cmx-overview-revenue-form">';
		echo '<div class="cmx-overview-revenue-controls">';
		echo '<div class="cmx-overview-revenue-field">';
		echo '<label for="cmx-overview-revenue-preset">Zeitraum</label>';
		echo '<select id="cmx-overview-revenue-preset" name="cmx_cockpit_preset">';
		foreach ($presets as $preset_key => $preset_label) {
			echo '<option value="' . \esc_attr((string) $preset_key) . '"' . \selected($selected_preset, (string) $preset_key, false) . '>' . \esc_html((string) $preset_label) . '</option>';
		}
		echo '</select>';
		echo '</div>';

		echo '<div class="cmx-overview-revenue-field">';
		echo '<label for="cmx-overview-revenue-type">Belegtyp</label>';
		echo '<select id="cmx-overview-revenue-type" name="cmx_overview_revenue_type">';
		foreach ($type_options as $type_key => $type_label) {
			echo '<option value="' . \esc_attr((string) $type_key) . '"' . \selected($selected_type, (string) $type_key, false) . '>' . \esc_html((string) $type_label) . '</option>';
		}
		echo '</select>';
		echo '</div>';
		echo '</div>';
		echo '</form>';

		echo '<div class="cmx-overview-revenue-table">';
		echo '<div class="cmx-overview-revenue-row">';
		echo '<div class="cmx-overview-revenue-label">Einnahmen</div>';
		echo '<div class="cmx-overview-revenue-value">' . \esc_html(cmx_cockpit_overview_revenue_format_money((float) $summary['einnahmen'])) . '</div>';
		echo '</div>';
		echo '<div class="cmx-overview-revenue-row">';
		echo '<div class="cmx-overview-revenue-label">Ausgaben</div>';
		echo '<div class="cmx-overview-revenue-value">' . \esc_html(cmx_cockpit_overview_revenue_format_money((float) $summary['ausgaben'])) . '</div>';
		echo '</div>';
		echo '<div class="cmx-overview-revenue-divider"></div>';
		echo '<div class="cmx-overview-revenue-row cmx-overview-revenue-profit">';
		echo '<div class="cmx-overview-revenue-label">Gewinn</div>';
		echo '<div class="cmx-overview-revenue-value">' . \esc_html(cmx_cockpit_overview_revenue_format_money((float) $summary['gewinn'])) . '</div>';
		echo '</div>';
		echo '</div>';

		echo '<div class="cmx-overview-revenue-mwst">';
		echo '<div class="cmx-overview-revenue-row">';
		echo '<div class="cmx-overview-revenue-label">MWST geschuldet</div>';
		echo '<div class="cmx-overview-revenue-value">' . \esc_html(cmx_cockpit_overview_revenue_format_money((float) $summary['mwst_geschuldet'])) . '</div>';
		echo '</div>';
		echo '<div class="cmx-overview-revenue-row">';
		echo '<div class="cmx-overview-revenue-label">MWST Vorsteuer</div>';
		echo '<div class="cmx-overview-revenue-value">' . \esc_html(cmx_cockpit_overview_revenue_format_money((float) $summary['mwst_vorsteuer'])) . '</div>';
		echo '</div>';
		echo '</div>';

			if (!empty($summary['items']) && \is_array($summary['items'])) {
				$detail_count = (int) ($summary['count'] ?? 0);
				$detail_label = $detail_count === 1
					? '1 Beleg anzeigen'
					: $detail_count . ' Belege anzeigen';
				echo '<details class="cmx-overview-revenue-details">';
				echo '<summary>' . \esc_html($detail_label) . '</summary>';
				echo '<div class="cmx-overview-revenue-detail-list">';
				foreach ($summary['items'] as $item) {
					$item = (array) $item;
					$post_id = (int) ($item['post_id'] ?? 0);
					$title = (string) ($item['title'] ?? '');
					$edit_link = $post_id > 0 ? (string) \get_edit_post_link($post_id, '') : '';
					$title_html = $title !== '' ? \esc_html($title) : '';
					if ($title !== '' && $edit_link !== '') {
						$title_html = '<a href="' . \esc_url($edit_link) . '">' . \esc_html($title) . '</a>';
						}
							$amount = (float) ($item['amount'] ?? 0.0);
							$type = (string) ($item['type'] ?? '');
							$type_label = (string) ($item['type_label'] ?? '');
							$type_url = $type !== '' ? cmx_cockpit_overview_revenue_type_admin_url($type) : '';
							$side = (string) ($item['side'] ?? '');
						if ($side === 'expense' && $amount > 0) {
							$amount *= -1;
							}
							echo '<div class="cmx-overview-revenue-detail-row">';
							echo '<div class="cmx-overview-revenue-detail-title">' . $title_html . '</div>';
							echo '<div class="cmx-overview-revenue-detail-type">';
							if ($type_url !== '') {
								echo '<a class="cmx-overview-revenue-detail-type-link" href="' . \esc_url($type_url) . '"><span class="cmx-overview-revenue-detail-type-badge">' . \esc_html($type_label) . '</span></a>';
							} else {
								echo '<span class="cmx-overview-revenue-detail-type-badge">' . \esc_html($type_label) . '</span>';
							}
							echo '</div>';
							echo '<div class="cmx-overview-revenue-detail-value">' . \esc_html(cmx_cockpit_overview_revenue_format_money($amount)) . '</div>';
						echo '</div>';
					}
				echo '</div>';
				echo '</details>';
			}

			echo '<script>(function(){var ids=["cmx-overview-revenue-preset","cmx-overview-revenue-type"];ids.forEach(function(id){var el=document.getElementById(id);if(!el||!el.form){return;}el.addEventListener("change",function(){el.form.submit();});});})();</script>';
		}
	}
