<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_preset_options')) {
	function cmx_cockpit_preset_options(): array {
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_requested_preset')) {
	function cmx_cockpit_requested_preset(): string {
		$presets = cmx_cockpit_preset_options();
		foreach (['cmx_cockpit_preset', 'cmx_overview_revenue_preset'] as $key) {
			$preset = \sanitize_key((string) ($_GET[$key] ?? ''));
			if ($preset !== '' && isset($presets[$preset])) {
				return $preset;
			}
		}
		return 'dieses_jahr';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_range_from_preset')) {
	function cmx_cockpit_range_from_preset(string $preset): array {
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_range_from_preset')) {
			return (array) cmxbu_belege_export_range_from_preset($preset);
		}

		$tz = \function_exists('wp_timezone')
			? \wp_timezone()
			: new \DateTimeZone('UTC');
		$now = new \DateTimeImmutable('now', $tz);
		$today = $now->format('Y-m-d');

		switch ($preset) {
			case 'heute':
				return ['from' => $today, 'to' => $today];
			case 'diesen_monat':
				return [
					'from' => $now->modify('first day of this month')->format('Y-m-d'),
					'to' => $now->modify('last day of this month')->format('Y-m-d'),
				];
			case 'letzten_monat':
				return [
					'from' => $now->modify('first day of last month')->format('Y-m-d'),
					'to' => $now->modify('last day of last month')->format('Y-m-d'),
				];
			case 'vorletzten_monat':
				return [
					'from' => $now->modify('first day of -2 months')->format('Y-m-d'),
					'to' => $now->modify('last day of -2 months')->format('Y-m-d'),
				];
			case 'dieses_quartal':
				$year = (int) $now->format('Y');
				$month = (int) $now->format('n');
				$q_start_month = ((int) \floor(($month - 1) / 3) * 3) + 1;
				$q_start = $now->setDate($year, $q_start_month, 1);
				return [
					'from' => $q_start->format('Y-m-d'),
					'to' => $q_start->modify('+2 months')->modify('last day of this month')->format('Y-m-d'),
				];
			case 'letztes_quartal':
				$year = (int) $now->format('Y');
				$month = (int) $now->format('n');
				$q_start_month = ((int) \floor(($month - 1) / 3) * 3) + 1;
				$current_q_start = $now->setDate($year, $q_start_month, 1);
				return [
					'from' => $current_q_start->modify('-3 months')->format('Y-m-d'),
					'to' => $current_q_start->modify('-1 day')->format('Y-m-d'),
				];
			case 'vorletztes_quartal':
				$year = (int) $now->format('Y');
				$month = (int) $now->format('n');
				$q_start_month = ((int) \floor(($month - 1) / 3) * 3) + 1;
				$current_q_start = $now->setDate($year, $q_start_month, 1);
				return [
					'from' => $current_q_start->modify('-6 months')->format('Y-m-d'),
					'to' => $current_q_start->modify('-3 months')->modify('-1 day')->format('Y-m-d'),
				];
			case 'dieses_jahr':
				$year = (int) $now->format('Y');
				return ['from' => \sprintf('%04d-01-01', $year), 'to' => \sprintf('%04d-12-31', $year)];
			case 'letztes_jahr':
				$year = ((int) $now->format('Y')) - 1;
				return ['from' => \sprintf('%04d-01-01', $year), 'to' => \sprintf('%04d-12-31', $year)];
			case 'vorletztes_jahr':
				$year = ((int) $now->format('Y')) - 2;
				return ['from' => \sprintf('%04d-01-01', $year), 'to' => \sprintf('%04d-12-31', $year)];
			default:
				return ['from' => '', 'to' => ''];
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_requested_range')) {
	function cmx_cockpit_requested_range(): array {
		return cmx_cockpit_range_from_preset(cmx_cockpit_requested_preset());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_normalize_date')) {
	function cmx_cockpit_normalize_date(string $raw): string {
		$raw = \trim($raw);
		if ($raw === '') {
			return '';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_normalize_any_date')) {
			$normalized = (string) cmxbu_beleg_export_normalize_any_date($raw);
			if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
				return $normalized;
			}
		}

		if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
			return $raw;
		}

		if (\preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $raw, $m)) {
			$d = (int) $m[1];
			$mo = (int) $m[2];
			$y = (int) $m[3];
			if (\checkdate($mo, $d, $y)) {
				return \sprintf('%04d-%02d-%02d', $y, $mo, $d);
			}
		}

		$ts = \strtotime($raw);
		return $ts ? \date('Y-m-d', $ts) : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_date_in_range')) {
	function cmx_cockpit_date_in_range(string $raw, ?array $range = null): bool {
		$range = \is_array($range) ? $range : cmx_cockpit_requested_range();
		$date = cmx_cockpit_normalize_date($raw);
		if ($date === '') {
			return false;
		}

		$from = (string) ($range['from'] ?? '');
		$to = (string) ($range['to'] ?? '');
		if ($from === '' || $to === '') {
			return true;
		}

		return ($date >= $from && $date <= $to);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_paid_date')) {
	function cmx_cockpit_paid_date(int $post_id): string {
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_paid_date')) {
			return (string) cmxbu_belege_export_paid_date($post_id);
		}

		return cmx_cockpit_normalize_date((string) \get_post_meta($post_id, '_cmx_beleg_bezahlt_am', true));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_post_date')) {
	function cmx_cockpit_post_date(int $post_id): string {
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_post_date')) {
			return (string) cmxbu_belege_export_post_date($post_id);
		}

		$meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_RNG_DATUM')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_RNG_DATUM')
			: '_cmx_beleg_rng_datum';
		$raw = (string) \get_post_meta($post_id, $meta_key, true);
		if ($raw === '') {
			$post = \get_post($post_id);
			if ($post instanceof \WP_Post) {
				$raw = (string) \mysql2date('Y-m-d', (string) $post->post_date, false);
			}
		}

		return cmx_cockpit_normalize_date($raw);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_due_date')) {
	function cmx_cockpit_due_date(int $post_id): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_due_raw')) {
			return cmx_cockpit_normalize_date((string) cmx_cockpit_due_raw($post_id));
		}

		$keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_FAELLIG')) {
			$keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_FAELLIG');
		}
		$keys = \array_merge($keys, [
			'_cmx_beleg_faelligkeitsdatum',
			'_cmx_beleg_faellig_am',
			'cmx_beleg_faelligkeitsdatum',
			'cmx_beleg_faellig_am',
		]);

		foreach (\array_values(\array_unique($keys)) as $key) {
			$val = \trim((string) \get_post_meta($post_id, $key, true));
			if ($val !== '') {
				return cmx_cockpit_normalize_date($val);
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_render_preset_control')) {
	function cmx_cockpit_render_preset_control(string $select_id): void {
		static $assets_printed = false;
		$presets = cmx_cockpit_preset_options();
		$selected = cmx_cockpit_requested_preset();

		if (!$assets_printed) {
			$assets_printed = true;
			echo '<style>
				.cmx-cockpit-preset-control{margin:0 0 12px}
				.cmx-cockpit-preset-control label{display:block;margin:0 0 4px;font-size:12px;font-weight:600;color:#50575e}
				.cmx-cockpit-preset-control select{width:100%;max-width:none}
			</style>';
			echo '<script>(function(){if(window.cmxCockpitPresetInit){return;}window.cmxCockpitPresetInit=true;document.addEventListener("change",function(e){var el=e.target&&e.target.closest?e.target.closest(".cmx-cockpit-preset-select"):null;if(!el){return;}var url=new URL(window.location.href);url.searchParams.set("cmx_cockpit_preset",el.value);url.searchParams.delete("cmx_overview_revenue_preset");window.location.href=url.toString();});})();</script>';
		}

		echo '<div class="cmx-cockpit-preset-control">';
		echo '<label for="' . \esc_attr($select_id) . '">Zeitraum</label>';
		echo '<select id="' . \esc_attr($select_id) . '" class="cmx-cockpit-preset-select">';
		foreach ($presets as $preset_key => $preset_label) {
			echo '<option value="' . \esc_attr((string) $preset_key) . '"' . \selected($selected, (string) $preset_key, false) . '>' . \esc_html((string) $preset_label) . '</option>';
		}
		echo '</select>';
		echo '</div>';
	}
}
