<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

// if (!in_array(UserDomain, cmx_ini_get_value('vip', 'instanzen'))) return;


if (!\in_array(UserDomain, (array) cmx_ini_get_value('vip', 'instanzen'), true)) return;

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_slug')) {
	function cmx_cockpit_pendenzen_slug(): string {
		return 'cmx-cockpit-pendenzen';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_base_url')) {
	function cmx_cockpit_pendenzen_base_url(array $args = []): string {
		$base = \add_query_arg(['page' => cmx_cockpit_pendenzen_slug()], \admin_url('index.php'));
		return (string) \add_query_arg($args, $base);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_view')) {
	function cmx_cockpit_pendenzen_view(): string {
		$view = isset($_GET['view']) ? \sanitize_key((string) \wp_unslash($_GET['view'])) : 'board';
		return \in_array($view, ['board', 'calendar'], true) ? $view : 'board';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_days_value')) {
	function cmx_cockpit_pendenzen_days_value(string $key, int $fallback): int {
		$value = isset($_GET[$key]) ? (int) $_GET[$key] : $fallback;
		$allowed = [3, 7, 15, 30, 60];
		return \in_array($value, $allowed, true) ? $value : $fallback;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_manual_option_key')) {
	function cmx_cockpit_pendenzen_manual_option_key(): string {
		return 'cmx_cockpit_pendenzen_manual_events';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_contact_post_types')) {
	function cmx_cockpit_pendenzen_contact_post_types(): array {
		$types = [];
		if (\function_exists(__NAMESPACE__ . '\\cmx_kontakte_cpt')) {
			$cpt = (string) cmx_kontakte_cpt();
			if ($cpt !== '' && \post_type_exists($cpt)) {
				$types[] = $cpt;
			}
		}
		foreach (['kontakte', 'kontakt', 'contact'] as $post_type) {
			if (\post_type_exists($post_type) && !\in_array($post_type, $types, true)) {
				$types[] = $post_type;
			}
		}
		return $types;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_manual_events')) {
	function cmx_cockpit_pendenzen_manual_events(): array {
		$raw = \get_option(cmx_cockpit_pendenzen_manual_option_key(), []);
		if (!\is_array($raw)) {
			return [];
		}

		$items = [];
		foreach ($raw as $row) {
			if (!\is_array($row)) {
				continue;
			}

			$date = isset($row['date']) ? (string) $row['date'] : '';
			$date = \preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
			$time = isset($row['time']) ? (string) $row['time'] : '';
			$time = \preg_match('/^\d{2}:\d{2}$/', $time) ? $time : '';
			$subject = \sanitize_text_field((string) ($row['subject'] ?? ''));
			if ($date === '' || $subject === '') {
				continue;
			}

			$hinweis = isset($row['hinweis']) ? (string) $row['hinweis'] : '';
			$hinweis = \preg_match('/^\d{4}-\d{2}-\d{2}$/', $hinweis) ? $hinweis : '';

			$items[] = [
				'id' => \sanitize_key((string) ($row['id'] ?? '')),
				'date' => $date,
				'time' => !empty($row['all_day']) ? '' : $time,
				'all_day' => !empty($row['all_day']),
				'subject' => $subject,
				'hinweis' => $hinweis,
				'description' => \sanitize_textarea_field((string) ($row['description'] ?? '')),
				'url' => \esc_url_raw((string) ($row['url'] ?? '')),
				'contact_id' => \max(0, (int) ($row['contact_id'] ?? 0)),
				'contact_label' => \sanitize_text_field((string) ($row['contact_label'] ?? '')),
			];
		}

		return $items;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_manual_contact_label')) {
	function cmx_cockpit_pendenzen_manual_contact_label(int $contact_id, string $fallback = ''): string {
		if ($contact_id <= 0 || !\get_post_status($contact_id)) {
			return $fallback;
		}
		$post_type = (string) \get_post_type($contact_id);
		if (!\in_array($post_type, cmx_cockpit_pendenzen_contact_post_types(), true)) {
			return $fallback;
		}
		$title = \trim((string) \get_the_title($contact_id));
		return $title !== '' ? $title : $fallback;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_time_sort_ts')) {
	function cmx_cockpit_pendenzen_time_sort_ts(string $date, string $time): int {
		$day_ts = cmx_cockpit_pendenzen_parse_date_to_ts($date);
		if ($day_ts <= 0 || !\preg_match('/^(\d{2}):(\d{2})$/', $time, $matches)) {
			return $day_ts;
		}
		$hours = \max(0, \min(23, (int) $matches[1]));
		$minutes = \max(0, \min(59, (int) $matches[2]));
		return $day_ts + ($hours * \HOUR_IN_SECONDS) + ($minutes * \MINUTE_IN_SECONDS);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_manual_description_snippet')) {
	function cmx_cockpit_pendenzen_manual_description_snippet(string $text, int $limit = 140): string {
		$text = \trim(\preg_replace('/\s+/', ' ', $text));
		if ($text === '') {
			return '';
		}
		if (\function_exists('mb_strimwidth')) {
			return (string) \mb_strimwidth($text, 0, $limit, '…');
		}
		return \strlen($text) > $limit ? \substr($text, 0, $limit - 1) . '…' : $text;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_month_value')) {
	function cmx_cockpit_pendenzen_month_value(): string {
		$selected_year = isset($_GET['calendar_year']) ? (int) \sanitize_text_field((string) \wp_unslash($_GET['calendar_year'])) : 0;
		$selected_month = isset($_GET['calendar_month']) ? (int) \sanitize_text_field((string) \wp_unslash($_GET['calendar_month'])) : 0;
		if ($selected_year >= 1000 && $selected_year <= 9999 && $selected_month >= 1 && $selected_month <= 12) {
			return \sprintf('%04d-%02d', $selected_year, $selected_month);
		}

		$raw = isset($_GET['month']) ? \sanitize_text_field((string) \wp_unslash($_GET['month'])) : '';
		if ($raw !== '' && \preg_match('/^\d{4}-\d{2}$/', $raw)) {
			return $raw;
		}
		return (string) \wp_date('Y-m');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_today_start_ts')) {
	function cmx_cockpit_pendenzen_today_start_ts(): int {
		$tz = \wp_timezone();
		return (int) (new \DateTimeImmutable('today', $tz))->getTimestamp();
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_day_start_ts')) {
	function cmx_cockpit_pendenzen_day_start_ts(int $ts): int {
		$tz = \wp_timezone();
		return (int) (new \DateTimeImmutable('@' . $ts))
			->setTimezone($tz)
			->setTime(0, 0, 0)
			->getTimestamp();
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_day_ymd')) {
	function cmx_cockpit_pendenzen_day_ymd(int $ts): string {
		return (string) \wp_date('Y-m-d', $ts, \wp_timezone());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_parse_date_to_ts')) {
	function cmx_cockpit_pendenzen_parse_date_to_ts(string $raw): int {
		$raw = \trim($raw);
		if ($raw === '') {
			return 0;
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_parse_date_to_ts')) {
			$ts = (int) cmx_cockpit_parse_date_to_ts($raw);
			if ($ts > 0) {
				return cmx_cockpit_pendenzen_day_start_ts($ts);
			}
		}

		$tz = \wp_timezone();
		foreach (['Y-m-d', 'd.m.Y', 'Y/m/d', 'd/m/Y'] as $format) {
			$dt = \DateTimeImmutable::createFromFormat('!' . $format, $raw, $tz);
			if ($dt instanceof \DateTimeImmutable) {
				return (int) $dt->setTime(0, 0, 0)->getTimestamp();
			}
		}

		$ts = \strtotime($raw);
		return $ts ? cmx_cockpit_pendenzen_day_start_ts((int) $ts) : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_format_board_date')) {
	function cmx_cockpit_pendenzen_format_board_date(int $ts): string {
		return (string) \wp_date('j. F', $ts, \wp_timezone());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_format_date')) {
	function cmx_cockpit_pendenzen_format_date(int $ts): string {
		return (string) \wp_date('d.m.Y', $ts, \wp_timezone());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_format_money')) {
	function cmx_cockpit_pendenzen_format_money(float $amount): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')) {
			return (string) cmx_format_swiss_number($amount, 2);
		}
		return \number_format($amount, 2, '.', "'");
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_amount_label')) {
	function cmx_cockpit_pendenzen_amount_label(int $post_id): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_amount_display')) {
			$formatted = (string) cmx_cockpit_beleg_amount_display($post_id);
			if ($formatted !== '') {
				return 'CHF ' . $formatted;
			}
		}
		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_beleg_contact_label')) {
	function cmx_cockpit_pendenzen_beleg_contact_label(int $post_id): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_kontakt_data')) {
			$data = (array) cmx_cockpit_beleg_kontakt_data($post_id);
			$name = \trim((string) ($data['name'] ?? ''));
			if ($name !== '') {
				return $name;
			}
		}

		$key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_LABEL')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_LABEL')
			: '_cmx_beleg_kontakt_label';
		return \trim((string) \get_post_meta($post_id, $key, true));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_is_open_offer')) {
	function cmx_cockpit_pendenzen_is_open_offer(int $post_id): bool {
		$key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_OFFERTENSTATUS')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_OFFERTENSTATUS')
			: '_cmx_beleg_offertenstatus';
		$status = \sanitize_key((string) \get_post_meta($post_id, $key, true));
		return $status === '' || $status === 'offen';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_paid_raw')) {
	function cmx_cockpit_pendenzen_paid_raw(int $post_id): string {
		$key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT')
			: '_cmx_beleg_bezahlt_am';
		$keys = \array_values(\array_unique(\array_filter([$key, \ltrim($key, '_'), '_cmx_beleg_bezahlt_am', 'cmx_beleg_bezahlt_am'])));
		foreach ($keys as $candidate) {
			$value = \trim((string) \get_post_meta($post_id, $candidate, true));
			if ($value !== '') {
				return $value;
			}
		}
		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_terms_contain')) {
	function cmx_cockpit_pendenzen_terms_contain(int $post_id, string $taxonomy, array $slugs): bool {
		if ($taxonomy === '' || !\taxonomy_exists($taxonomy)) {
			return false;
		}
		$terms = \wp_get_post_terms($post_id, $taxonomy, ['fields' => 'slugs']);
		if (\is_wp_error($terms) || !\is_array($terms)) {
			return false;
		}

		$terms = \array_map(static fn($slug): string => \sanitize_title((string) $slug), $terms);
		foreach ($slugs as $slug) {
			if (\in_array(\sanitize_title((string) $slug), $terms, true)) {
				return true;
			}
		}
		return false;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_icon_svg')) {
	function cmx_cockpit_pendenzen_icon_svg(string $icon): string {
		switch ($icon) {
			case 'check':
				return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.2 16.8 4.9 12.5l1.8-1.8 2.5 2.5 8-8 1.8 1.8z"/></svg>';
			case 'clock':
				return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1.8A10.2 10.2 0 1 0 22.2 12 10.21 10.21 0 0 0 12 1.8Zm0 18.1A7.9 7.9 0 1 1 19.9 12 7.91 7.91 0 0 1 12 19.9Zm1.2-8.4V6.3h-2.4v6.2l4.7 2.8 1.2-2Z"/></svg>';
			case 'cake':
				return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.1 3.2c.8.6 1 1.9.2 2.7-.2.2-.4.4-.7.5.1-.8-.1-1.6-.7-2.1-.7-.7-.7-1.9-.1-2.7.2-.2.4-.4.6-.5-.1.8.1 1.6.7 2.1Zm5 0c.8.6 1 1.9.2 2.7-.2.2-.4.4-.7.5.1-.8-.1-1.6-.7-2.1-.7-.7-.7-1.9-.1-2.7.2-.2.4-.4.6-.5-.1.8.1 1.6.7 2.1Zm5 0c.8.6 1 1.9.2 2.7-.2.2-.4.4-.7.5.1-.8-.1-1.6-.7-2.1-.7-.7-.7-1.9-.1-2.7.2-.2.4-.4.6-.5-.1.8.1 1.6.7 2.1ZM3 10.4a3.2 3.2 0 0 0 4.5 2.9 3.2 3.2 0 0 0 4.5 0 3.2 3.2 0 0 0 4.5 0A3.2 3.2 0 0 0 21 10.4V8.7H3Zm0 4.4h18v6.4H3Z"/></svg>';
			case 'calendar':
				return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2.5h2.2v2H14V2.5h2.2v2H20a1.8 1.8 0 0 1 1.8 1.8v13.4A1.8 1.8 0 0 1 20 21.5H4a1.8 1.8 0 0 1-1.8-1.8V6.3A1.8 1.8 0 0 1 4 4.5h3Zm12.6 7.2H4.4v9.6h15.2Z"/></svg>';
			default:
				return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 1.8 20.5h20.4Zm1.2 14.4h-2.4v-2.4h2.4Zm0-4.6h-2.4V7.4h2.4Z"/></svg>';
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_event_priority')) {
	function cmx_cockpit_pendenzen_event_priority(array $event): int {
		$tone = (string) ($event['tone'] ?? '');
		$label = (string) ($event['label'] ?? '');

		if ($tone === 'red' && \stripos($label, 'überfällig') !== false) {
			return 10;
		}
		if ($tone === 'red') {
			return 20;
		}
		if ($tone === 'orange') {
			return 30;
		}
		if ($tone === 'green') {
			return 40;
		}
		if ($tone === 'blue') {
			return 50;
		}
		return 90;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_sort_events')) {
	function cmx_cockpit_pendenzen_sort_events(array &$events, string $direction = 'asc'): void {
		\usort($events, static function (array $a, array $b) use ($direction): int {
			$ts_a = (int) ($a['sort_ts'] ?? $a['ts'] ?? 0);
			$ts_b = (int) ($b['sort_ts'] ?? $b['ts'] ?? 0);
			$prio_a = cmx_cockpit_pendenzen_event_priority($a);
			$prio_b = cmx_cockpit_pendenzen_event_priority($b);

			if ($ts_a !== $ts_b) {
				return $direction === 'desc' ? ($ts_b <=> $ts_a) : ($ts_a <=> $ts_b);
			}
			if ($prio_a !== $prio_b) {
				return $prio_a <=> $prio_b;
			}
			return \strnatcasecmp((string) ($a['subject'] ?? ''), (string) ($b['subject'] ?? ''));
		});
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_collect_beleg_events')) {
	function cmx_cockpit_pendenzen_collect_beleg_events(int $start_ts, int $end_ts, int $today_ts): array {
		$events = [];
		$taxonomy = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_taxonomy')
			? (string) cmx_cockpit_beleg_taxonomy()
			: '';
		if ($taxonomy === '' || !\taxonomy_exists($taxonomy)) {
			return [];
		}

		$query = new \WP_Query([
			'post_type' => 'belege',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => -1,
			'orderby' => 'date',
			'order' => 'DESC',
			'fields' => 'ids',
			'no_found_rows' => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query' => [[
				'taxonomy' => $taxonomy,
				'field' => 'slug',
				'terms' => ['rechnung', 'rechnungen', 'offerte', 'offerten'],
				'operator' => 'IN',
			]],
		]);

		foreach ((array) $query->posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0) {
				continue;
			}

			$title = \trim((string) \get_the_title($post_id));
			if ($title === '') {
				$title = '#' . $post_id;
			}

			$contact = cmx_cockpit_pendenzen_beleg_contact_label($post_id);
			$amount = cmx_cockpit_pendenzen_amount_label($post_id);
			$edit_url = (string) \get_edit_post_link($post_id, '');
			$is_offer = cmx_cockpit_pendenzen_terms_contain($post_id, $taxonomy, ['offerte', 'offerten']);
			$is_invoice = cmx_cockpit_pendenzen_terms_contain($post_id, $taxonomy, ['rechnung', 'rechnungen']);

			$paid_ts = cmx_cockpit_pendenzen_parse_date_to_ts(cmx_cockpit_pendenzen_paid_raw($post_id));
			if ($paid_ts >= $start_ts && $paid_ts <= $end_ts) {
				$events[] = [
					'key' => 'paid-' . $post_id . '-' . $paid_ts,
					'ts' => $paid_ts,
					'date' => cmx_cockpit_pendenzen_day_ymd($paid_ts),
					'label' => 'Zahlung eingegangen',
					'subject' => $contact !== '' ? $contact : $title,
					'meta' => $amount !== '' ? $amount : $title,
					'tone' => 'green',
					'icon' => 'check',
					'url' => $edit_url,
				];
			}

			$due_raw = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_due_raw')
				? (string) cmx_cockpit_due_raw($post_id)
				: '';
			$due_ts = cmx_cockpit_pendenzen_parse_date_to_ts($due_raw);
			if ($due_ts < $start_ts || $due_ts > $end_ts) {
				continue;
			}

			if ($is_offer) {
				if (!cmx_cockpit_pendenzen_is_open_offer($post_id)) {
					continue;
				}
				$label = $due_ts < $today_ts ? 'Offerte abgelaufen' : ($due_ts === $today_ts ? 'Offerte läuft ab' : 'Offerte fällig');
				$events[] = [
					'key' => 'offer-' . $post_id . '-' . $due_ts,
					'ts' => $due_ts,
					'date' => cmx_cockpit_pendenzen_day_ymd($due_ts),
					'label' => $label,
					'subject' => $title,
					'meta' => $contact !== '' ? $contact : $amount,
					'tone' => 'orange',
					'icon' => 'clock',
					'url' => $edit_url,
				];
				continue;
			}

			if (!$is_invoice) {
				continue;
			}

			$is_unpaid = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_is_unpaid_beleg')
				? (bool) cmx_cockpit_is_unpaid_beleg($post_id)
				: ($paid_ts <= 0);
			if (!$is_unpaid) {
				continue;
			}

			$label = $due_ts < $today_ts ? 'Rechnung überfällig' : ($due_ts === $today_ts ? 'Rechnung fällig' : 'Zahlungstermin');
			$events[] = [
				'key' => 'invoice-' . $post_id . '-' . $due_ts,
				'ts' => $due_ts,
				'date' => cmx_cockpit_pendenzen_day_ymd($due_ts),
				'label' => $label,
				'subject' => $title,
				'meta' => $amount !== '' ? $amount : $contact,
				'tone' => 'red',
				'icon' => 'warning',
				'url' => $edit_url,
			];
		}

		return $events;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_recurring_occurrence_ts')) {
	function cmx_cockpit_pendenzen_recurring_occurrence_ts(string $raw, int $year): int {
		$raw = \trim($raw);
		if ($raw === '' || !\preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
			return 0;
		}

		$month = (int) \substr($raw, 5, 2);
		$day = (int) \substr($raw, 8, 2);
		if (!\checkdate($month, $day, $year)) {
			return 0;
		}

		$tz = \wp_timezone();
		$date = \DateTimeImmutable::createFromFormat('!Y-m-d', \sprintf('%04d-%02d-%02d', $year, $month, $day), $tz);
		return $date instanceof \DateTimeImmutable ? (int) $date->getTimestamp() : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_collect_contact_events')) {
	function cmx_cockpit_pendenzen_collect_contact_events(int $start_ts, int $end_ts): array {
		$events = [];
		$start_year = (int) \wp_date('Y', $start_ts, \wp_timezone());
		$end_year = (int) \wp_date('Y', $end_ts, \wp_timezone());
		$years = \array_values(\array_unique([$start_year - 1, $start_year, $end_year, $end_year + 1]));

		$query = new \WP_Query([
			'post_type' => 'kontakte',
			'post_status' => ['publish', 'private'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		]);

		foreach ((array) $query->posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0) {
				continue;
			}

			$title = \trim((string) \get_the_title($post_id));
			if ($title === '') {
				$title = '#' . $post_id;
			}

			$rows = [
				['label' => 'Geburtstag', 'raw' => (string) \get_post_meta($post_id, '_cmx_kontakte_geburtsdatum', true)],
				['label' => 'Firmengründung', 'raw' => (string) \get_post_meta($post_id, '_cmx_kontakte_firmengruendung', true)],
			];

			foreach ($rows as $row) {
				$raw = \trim((string) ($row['raw'] ?? ''));
				if ($raw === '') {
					continue;
				}

				foreach ($years as $year) {
					$occurrence_ts = cmx_cockpit_pendenzen_recurring_occurrence_ts($raw, (int) $year);
					if ($occurrence_ts < $start_ts || $occurrence_ts > $end_ts) {
						continue;
					}

					$events[] = [
						'key' => 'contact-' . $post_id . '-' . \sanitize_title((string) ($row['label'] ?? '')) . '-' . $occurrence_ts,
						'ts' => $occurrence_ts,
						'date' => cmx_cockpit_pendenzen_day_ymd($occurrence_ts),
						'label' => (string) ($row['label'] ?? ''),
						'subject' => $title,
						'meta' => '',
						'tone' => 'blue',
						'icon' => 'cake',
						'url' => (string) \get_edit_post_link($post_id, ''),
					];
				}
			}
		}

		return $events;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_collect_project_events')) {
	function cmx_cockpit_pendenzen_collect_project_events(int $start_ts, int $end_ts): array {
		$events = [];
		$begin_key = \defined(__NAMESPACE__ . '\\CMX_PROJ_BEG_META') ? (string) \constant(__NAMESPACE__ . '\\CMX_PROJ_BEG_META') : '_cmx_projekt_beginn';
		$end_key = \defined(__NAMESPACE__ . '\\CMX_PROJ_END_META') ? (string) \constant(__NAMESPACE__ . '\\CMX_PROJ_END_META') : '_cmx_projekt_ende';
		$contact_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKT_META') ? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKT_META') : '_cmx_projekt_kontakt_id';

		$query = new \WP_Query([
			'post_type' => 'projekte',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		]);

		foreach ((array) $query->posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0) {
				continue;
			}

			$title = \trim((string) \get_the_title($post_id));
			if ($title === '') {
				$title = '#' . $post_id;
			}

			$kontakt_id = (int) \get_post_meta($post_id, $contact_key, true);
			$kontakt_title = $kontakt_id > 0 ? \trim((string) \get_the_title($kontakt_id)) : '';
			$edit_url = (string) \get_edit_post_link($post_id, '');

			$begin_ts = cmx_cockpit_pendenzen_parse_date_to_ts((string) \get_post_meta($post_id, $begin_key, true));
			if ($begin_ts >= $start_ts && $begin_ts <= $end_ts) {
				$events[] = [
					'key' => 'project-begin-' . $post_id . '-' . $begin_ts,
					'ts' => $begin_ts,
					'date' => cmx_cockpit_pendenzen_day_ymd($begin_ts),
					'label' => 'Projektbeginn',
					'subject' => $title,
					'meta' => $kontakt_title,
					'tone' => 'green',
					'icon' => 'calendar',
					'url' => $edit_url,
				];
			}

			$end_ts_project = cmx_cockpit_pendenzen_parse_date_to_ts((string) \get_post_meta($post_id, $end_key, true));
			if ($end_ts_project >= $start_ts && $end_ts_project <= $end_ts) {
				$events[] = [
					'key' => 'project-end-' . $post_id . '-' . $end_ts_project,
					'ts' => $end_ts_project,
					'date' => cmx_cockpit_pendenzen_day_ymd($end_ts_project),
					'label' => 'Projektende',
					'subject' => $title,
					'meta' => $kontakt_title,
					'tone' => 'orange',
					'icon' => 'calendar',
					'url' => $edit_url,
				];
			}
		}

		return $events;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_collect_manual_events')) {
	function cmx_cockpit_pendenzen_collect_manual_events(int $start_ts, int $end_ts): array {
		$events = [];

		foreach (cmx_cockpit_pendenzen_manual_events() as $event) {
			$date = (string) ($event['date'] ?? '');
			$day_ts = cmx_cockpit_pendenzen_parse_date_to_ts($date);
			if ($day_ts < $start_ts || $day_ts > $end_ts) {
				continue;
			}

			$all_day = !empty($event['all_day']);
			$time = (string) ($event['time'] ?? '');
			$contact_label = cmx_cockpit_pendenzen_manual_contact_label((int) ($event['contact_id'] ?? 0), (string) ($event['contact_label'] ?? ''));
			$description = cmx_cockpit_pendenzen_manual_description_snippet((string) ($event['description'] ?? ''));
			$hinweis = '';
			$hinweis_raw = \trim((string) ($event['hinweis'] ?? ''));
			if ($hinweis_raw !== '') {
				$hinweis_ts = cmx_cockpit_pendenzen_parse_date_to_ts($hinweis_raw);
				$hinweis = $hinweis_ts > 0 ? cmx_cockpit_pendenzen_format_date($hinweis_ts) : $hinweis_raw;
			}
			$meta_parts = \array_values(\array_filter([$contact_label, $hinweis, $description], static fn($value): bool => \trim((string) $value) !== ''));
			$label = $all_day ? 'Ganztägig' : ($time !== '' ? $time : 'Termin');

			$events[] = [
				'key' => 'manual-' . \sanitize_key((string) ($event['id'] ?? '')) . '-' . $date,
				'ts' => $day_ts,
				'sort_ts' => $all_day ? $day_ts : cmx_cockpit_pendenzen_time_sort_ts($date, $time),
				'date' => cmx_cockpit_pendenzen_day_ymd($day_ts),
				'label' => $label,
				'subject' => (string) ($event['subject'] ?? ''),
				'meta' => \implode(' · ', $meta_parts),
				'tone' => 'blue',
				'icon' => 'calendar',
				'url' => \trim((string) ($event['url'] ?? '')),
			];
		}

		return $events;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_collect_events')) {
	function cmx_cockpit_pendenzen_collect_events(int $start_ts, int $end_ts, int $today_ts): array {
		$events = \array_merge(
			cmx_cockpit_pendenzen_collect_manual_events($start_ts, $end_ts),
			cmx_cockpit_pendenzen_collect_beleg_events($start_ts, $end_ts, $today_ts),
			cmx_cockpit_pendenzen_collect_contact_events($start_ts, $end_ts),
			cmx_cockpit_pendenzen_collect_project_events($start_ts, $end_ts)
		);

		$unique = [];
		foreach ($events as $event) {
			$key = (string) ($event['key'] ?? '');
			if ($key === '' || isset($unique[$key])) {
				continue;
			}
			$unique[$key] = $event;
		}

		$events = \array_values($unique);
		cmx_cockpit_pendenzen_sort_events($events, 'asc');
		return $events;
	}
}

\add_action('wp_ajax_cmx_cockpit_pendenzen_add_manual_event', function (): void {
	if (!\current_user_can('edit_posts')) {
		\wp_send_json_error(['message' => 'forbidden'], 403);
	}
	$nonce = isset($_POST['_ajax_nonce']) ? (string) \wp_unslash($_POST['_ajax_nonce']) : '';
	if (!\wp_verify_nonce($nonce, 'cmx_cockpit_pendenzen_add_manual_event')) {
		\wp_send_json_error(['message' => 'bad_nonce'], 403);
	}

	$date = isset($_POST['date']) ? (string) \wp_unslash($_POST['date']) : '';
	$date = \preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
	$all_day = !empty($_POST['all_day']);
	$time = isset($_POST['time']) ? (string) \wp_unslash($_POST['time']) : '';
	$time = \preg_match('/^\d{2}:\d{2}$/', $time) ? $time : '';
	$subject = \sanitize_text_field((string) \wp_unslash($_POST['subject'] ?? ''));
	$hinweis = isset($_POST['hinweis']) ? (string) \wp_unslash($_POST['hinweis']) : '';
	$hinweis = \preg_match('/^\d{4}-\d{2}-\d{2}$/', $hinweis) ? $hinweis : '';
	$description = \sanitize_textarea_field((string) \wp_unslash($_POST['description'] ?? ''));
	$url = \esc_url_raw((string) \wp_unslash($_POST['url'] ?? ''));
	$contact_id = isset($_POST['contact_id']) ? (int) \wp_unslash($_POST['contact_id']) : 0;
	$contact_label = '';

	if ($date === '' || $subject === '') {
		\wp_send_json_error(['message' => 'missing_fields'], 400);
	}
	if (!$all_day && $time === '') {
		\wp_send_json_error(['message' => 'missing_time'], 400);
	}

	if ($contact_id > 0) {
		$contact_label = cmx_cockpit_pendenzen_manual_contact_label($contact_id, '');
		if ($contact_label === '') {
			$contact_id = 0;
		}
	}

	$items = cmx_cockpit_pendenzen_manual_events();
	$items[] = [
		'id' => 'evt_' . \wp_generate_uuid4(),
		'date' => $date,
		'time' => $all_day ? '' : $time,
		'all_day' => $all_day ? 1 : 0,
		'subject' => $subject,
		'hinweis' => $hinweis,
		'description' => $description,
		'url' => $url,
		'contact_id' => $contact_id,
		'contact_label' => $contact_label,
	];

	\update_option(cmx_cockpit_pendenzen_manual_option_key(), $items, false);
	\wp_send_json_success(['message' => 'ok']);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_css')) {
	function cmx_cockpit_pendenzen_css(): string {
		return '
			.cmx-pend-wrap{margin:20px 0 0;padding:0 20px 0 0;box-sizing:border-box}
			.cmx-pend-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin:0 0 18px}
			.cmx-pend-title-copy{min-width:0;flex:1 1 auto}
			.cmx-pend-title-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
			.cmx-pend-title{margin:0;color:#1d2327;font-size:20px !important;line-height:1.2;font-weight:600}
			.cmx-pend-subtitle{margin:8px 0 0;color:#646970;font-size:14px;line-height:1.55;max-width:760px}
			.cmx-pend-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
			.cmx-pend-actions .button{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:0 14px}
			.page-title-action.cmx-pend-add-button{display:inline-flex;align-items:center;white-space:nowrap;border-radius:8px !important;margin-top:10px;padding-left:14px;padding-right:14px}
			.cmx-pend-body{padding:0}
			.cmx-pend-board-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;align-items:start}
			.cmx-pend-postbox{margin:0}
			.cmx-pend-postbox .postbox-header .hndle,
			.cmx-pend-postbox .postbox-header h2{font-size:16px !important;line-height:1.3 !important;padding:7px 18px !important;min-height:0}
			.cmx-pend-postbox--full{grid-column:1 / -1}
			.cmx-pend-postbox-title{display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%}
			.cmx-pend-count{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:22px;padding:0 8px;border-radius:999px;background:#eef4fb;color:#1f5180;font-size:12px;font-weight:700;line-height:1}
			.cmx-pend-postbox .inside{padding:16px 18px 18px}
			.cmx-pend-toolbar-form{display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);gap:16px;align-items:end}
			.cmx-pend-field{display:flex;flex-direction:column;gap:6px}
			.cmx-pend-field label{font-size:11px;font-weight:700;line-height:1.2;letter-spacing:.04em;text-transform:uppercase;color:#646970}
			.cmx-pend-board-select select{width:auto;max-width:100%;align-self:flex-start}
			.cmx-pend-field--right select{align-self:flex-end}
			.cmx-pend-toolbar-today{align-self:center;justify-self:center;color:#1d2327;font-size:28px;line-height:1.2;font-weight:600;text-align:center;white-space:nowrap}
			.cmx-pend-field--right{align-items:flex-end}
			.cmx-pend-card-list{display:flex;flex-direction:column}
			.cmx-pend-card{display:grid;grid-template-columns:10px minmax(0,1fr);gap:12px;align-items:start;padding:12px 0;border:0;border-bottom:1px solid #edf0f3;background:transparent;box-shadow:none;border-radius:0}
			.cmx-pend-card:first-child{padding-top:0}
			.cmx-pend-card:last-child{padding-bottom:0;border-bottom:0}
			.cmx-pend-card-dot{display:block;width:10px;height:10px;border-radius:999px;background:#c0cad5;margin-top:6px}
			.cmx-pend-card-body{min-width:0;flex:1 1 auto;padding-top:0}
			.cmx-pend-card-line{display:block;font-size:15px;line-height:1.4;color:#2c3338}
			.cmx-pend-card-label{font-weight:800}
			.cmx-pend-card-link{color:inherit;text-decoration:none;font-weight:600}
			.cmx-pend-card-link:hover{text-decoration:underline}
			.cmx-pend-card-meta{display:block;margin-top:3px;font-size:12px;line-height:1.45;color:#646970}
			.cmx-pend-card--red .cmx-pend-card-label,
			.cmx-pend-card--red .cmx-pend-card-link{color:#b32d2e}
			.cmx-pend-card--red .cmx-pend-card-dot{background:#d63638}
			.cmx-pend-card--orange .cmx-pend-card-label,
			.cmx-pend-card--orange .cmx-pend-card-link{color:#b96f00}
			.cmx-pend-card--orange .cmx-pend-card-dot{background:#dba617}
			.cmx-pend-card--blue .cmx-pend-card-label,
			.cmx-pend-card--blue .cmx-pend-card-link{color:#135e96}
			.cmx-pend-card--blue .cmx-pend-card-dot{background:#2271b1}
			.cmx-pend-card--green .cmx-pend-card-label,
			.cmx-pend-card--green .cmx-pend-card-link{color:#2f7d32}
			.cmx-pend-card--green .cmx-pend-card-dot{background:#46a546}
			.cmx-pend-empty{padding:8px 0 2px;color:#646970;font-size:14px;font-style:italic}
			.cmx-pend-calendar-shell{padding:0}
			.cmx-pend-calendar-heading{display:flex;align-items:center;gap:10px;min-width:0}
			.cmx-pend-calendar-switch{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:0}
			.cmx-pend-calendar-switch select{min-width:130px;max-width:100%;height:34px}
			.cmx-pend-calendar-switch .cmx-pend-calendar-year{min-width:96px}
			.cmx-pend-calendar-nav{display:flex;align-items:center;gap:8px}
			.cmx-pend-calendar-nav .button{display:inline-flex;align-items:center;justify-content:center;min-width:26px;min-height:26px;height:26px;padding:0 6px;line-height:1}
			.cmx-pend-calendar-nav .dashicons{width:16px;height:16px;font-size:16px;line-height:16px}
			.cmx-pend-calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:clamp(6px,.7vw,12px);align-items:stretch;--cmx-pend-calendar-cell-height:112px}
			.cmx-pend-calendar-weekday{padding:0 4px;color:#646970;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;text-align:center}
			.cmx-pend-calendar-cell{height:var(--cmx-pend-calendar-cell-height);min-height:var(--cmx-pend-calendar-cell-height);padding:8px;border:1px solid #e6ebf0;border-radius:10px;background:#fff;box-shadow:none;display:flex;flex-direction:column;overflow:hidden}
			.cmx-pend-calendar-cell.is-muted{background:#fafbfc}
			.cmx-pend-calendar-cell.is-today{border-color:#c8daf6;box-shadow:0 0 0 1px #c8daf6}
			.cmx-pend-calendar-day{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px}
			.cmx-pend-calendar-number{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;border-radius:999px;color:#1d2327;font-size:13px;font-weight:700}
			.cmx-pend-calendar-cell.is-today .cmx-pend-calendar-number{background:#135e96;color:#fff}
			.cmx-pend-calendar-items{display:flex;flex-direction:column;gap:6px;min-height:0;overflow:auto;padding-right:2px}
			.cmx-pend-calendar-chip{display:block;padding:6px 8px;border-radius:8px;border:1px solid #e6ebf0;background:#fff;color:#2c3338;text-decoration:none;font-size:12px;line-height:1.3;font-weight:600}
			.cmx-pend-calendar-chip:hover{text-decoration:none;background:#fafcff}
			.cmx-pend-calendar-chip--red{border-color:#f3d0d2;background:#fff7f7;color:#b32d2e}
			.cmx-pend-calendar-chip--orange{border-color:#f4dfb1;background:#fffbf2;color:#b96f00}
			.cmx-pend-calendar-chip--blue{border-color:#cfe0f0;background:#f7fbff;color:#135e96}
			.cmx-pend-calendar-chip--green{border-color:#d3e7d3;background:#f7fcf7;color:#2f7d32}
			.cmx-pend-calendar-more{margin-top:2px;color:#646970;font-size:12px}
			.cmx-pend-modal[hidden]{display:none !important}
			.cmx-pend-modal{position:fixed;inset:0;z-index:100000;display:flex;align-items:flex-start;justify-content:center;padding:56px 24px;overflow:auto;background:rgba(15,23,42,.26);backdrop-filter:blur(6px)}
			.cmx-pend-modal-dialog{position:relative;width:min(760px,100%);max-height:calc(100vh - 96px);overflow:visible;border-radius:24px;background:linear-gradient(180deg,#ffffff 0%,#fbfcff 100%);box-shadow:0 28px 60px rgba(15,23,42,.22)}
			.cmx-pend-modal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:22px 22px 0}
			.cmx-pend-modal-eyebrow{margin:0 0 4px;color:#6b7280;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
			.cmx-pend-modal-title{margin:0;color:#1f2937;font-size:18px;line-height:1.2;font-weight:700}
			.cmx-pend-modal-close{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border:0;border-radius:999px;background:#eef3fb;color:#42526b;cursor:pointer}
			.cmx-pend-modal-close:hover{background:#e2ebf7}
			.cmx-pend-modal-body{padding:18px 22px 0;overflow:visible}
			.cmx-pend-modal-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px 22px 22px}
			.cmx-pend-modal-status{min-height:20px;color:#b32d2e;font-size:13px;line-height:1.4}
			.cmx-pend-modal-status[data-tone="success"]{color:#2f7d32}
			.cmx-pend-modal-actions{display:flex;align-items:center;gap:10px}
			.cmx-pend-modal-actions .button{border-radius:8px !important}
			.cmx-pend-form-grid{display:grid;gap:16px}
			.cmx-pend-form-row{display:grid;gap:16px}
			.cmx-pend-form-row--datetime{grid-template-columns:minmax(0,1fr) minmax(120px,146px) minmax(140px,180px) auto;align-items:end}
			.cmx-pend-form-field{display:flex;flex-direction:column;gap:6px;min-width:0}
			.cmx-pend-form-field label{font-size:12px;font-weight:700;line-height:1.3;color:#334155}
			.cmx-pend-form-label--quickfill{cursor:pointer;user-select:none}
			.cmx-pend-form-field input[type="text"],
			.cmx-pend-form-field input[type="url"],
			.cmx-pend-form-field input[type="date"],
			.cmx-pend-form-field input[type="time"],
			.cmx-pend-form-field textarea{width:100%;min-height:42px;padding:10px 12px;border:1px solid #c9d4e2;border-radius:14px;background:#fff;color:#1f2937;box-shadow:none}
			.cmx-pend-form-field textarea{min-height:110px;resize:vertical}
			.cmx-pend-form-field input:focus,
			.cmx-pend-form-field textarea:focus{border-color:#2271b1;outline:none;box-shadow:0 0 0 1px #2271b1}
			.cmx-pend-form-checkbox{display:inline-flex;align-items:center;gap:8px;min-height:42px;padding:0 2px;color:#334155;font-size:13px;font-weight:600}
			.cmx-pend-form-checkbox input{margin:0}
			.cmx-pend-contact-suggest{position:relative;z-index:20}
			.cmx-pend-contact-results{position:absolute;z-index:100005;left:0;right:0;max-height:220px;overflow:auto;margin:6px 0 0;padding:0;border:1px solid #ccd8e6;border-radius:14px;background:#fff;box-shadow:0 20px 38px rgba(15,23,42,.18);list-style:none}
			.cmx-pend-contact-results li{margin:0;padding:8px 10px;cursor:pointer}
			.cmx-pend-contact-results li.active,
			.cmx-pend-contact-results li:hover{background:#e8f2fe}
			.cmx-pend-contact-results small{display:block;margin-top:2px;color:#64748b}
			@media (max-width: 1280px){
				.cmx-pend-board-grid{grid-template-columns:1fr}
				.cmx-pend-postbox--full{grid-column:auto}
				.cmx-pend-toolbar-form{grid-template-columns:1fr}
				.cmx-pend-toolbar-today{justify-self:start;text-align:left;white-space:normal}
				.cmx-pend-field--right{align-items:stretch}
				.cmx-pend-calendar-heading{align-items:flex-start}
				.cmx-pend-calendar-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
				.cmx-pend-calendar-cell{height:auto;min-height:72px}
				.cmx-pend-calendar-items{overflow:visible}
			}
			@media (max-width: 782px){
				.cmx-pend-wrap{padding-right:10px}
				.cmx-pend-head{flex-direction:column;align-items:stretch}
				.cmx-pend-actions{justify-content:flex-start}
				.cmx-pend-actions .button{flex:1 1 auto}
				.cmx-pend-title-row{align-items:flex-start}
				.cmx-pend-form-row--datetime{grid-template-columns:1fr}
				.cmx-pend-modal{padding:24px 10px}
				.cmx-pend-modal-head,
				.cmx-pend-modal-body,
				.cmx-pend-modal-foot{padding-left:16px;padding-right:16px}
				.cmx-pend-modal-foot{flex-direction:column;align-items:stretch}
				.cmx-pend-modal-actions{width:100%}
				.cmx-pend-modal-actions .button{flex:1 1 auto;justify-content:center}
				.cmx-pend-calendar-heading{flex-direction:column;align-items:stretch}
				.cmx-pend-calendar-switch{width:100%}
				.cmx-pend-calendar-switch select,
				.cmx-pend-calendar-switch .cmx-pend-calendar-year{width:100%;min-width:0}
				.cmx-pend-calendar-grid{grid-template-columns:1fr}
			}
		';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_render_event_card')) {
	function cmx_cockpit_pendenzen_render_event_card(array $event): void {
		$tone = \sanitize_html_class((string) ($event['tone'] ?? 'red'));
		$label = \trim((string) ($event['label'] ?? ''));
		$subject = \trim((string) ($event['subject'] ?? ''));
		$meta = \trim((string) ($event['meta'] ?? ''));
		$url = \trim((string) ($event['url'] ?? ''));

		echo '<article class="cmx-pend-card cmx-pend-card--' . \esc_attr($tone) . '">';
		echo '<span class="cmx-pend-card-dot" aria-hidden="true"></span>';
		echo '<div class="cmx-pend-card-body">';
		echo '<span class="cmx-pend-card-line">';
		echo '<span class="cmx-pend-card-label">' . \esc_html($label) . ':</span> ';
		if ($url !== '') {
			echo '<a class="cmx-pend-card-link" href="' . \esc_url($url) . '">' . \esc_html($subject) . '</a>';
		} else {
			echo '<span>' . \esc_html($subject) . '</span>';
		}
		echo '</span>';
		if ($meta !== '') {
			echo '<span class="cmx-pend-card-meta">' . \esc_html($meta) . '</span>';
		}
		echo '</div>';
		echo '</article>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_render_day_column')) {
	function cmx_cockpit_pendenzen_render_day_column(array $events, string $empty_label): void {
		if (empty($events)) {
			echo '<div class="cmx-pend-empty">' . \esc_html($empty_label) . '</div>';
			return;
		}

		echo '<div class="cmx-pend-card-list">';
		foreach ($events as $event) {
			cmx_cockpit_pendenzen_render_event_card((array) $event);
		}
		echo '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_render_board')) {
	function cmx_cockpit_pendenzen_render_board(): void {
		$default_past = 15;
		$default_future = 15;
		if ((isset($_GET['range']) ? (int) $_GET['range'] : 30) === 7) {
			$default_past = 3;
			$default_future = 3;
		}

		$past_days = cmx_cockpit_pendenzen_days_value('past', $default_past);
		$future_days = cmx_cockpit_pendenzen_days_value('future', $default_future);
		$today_ts = cmx_cockpit_pendenzen_today_start_ts();
		$start_ts = $today_ts - ($past_days * \DAY_IN_SECONDS);
		$end_ts = $today_ts + ($future_days * \DAY_IN_SECONDS);
		$events = cmx_cockpit_pendenzen_collect_events($start_ts, $end_ts, $today_ts);

		$past_events = [];
		$today_events = [];
		$future_events = [];
		foreach ($events as $event) {
			$event_ts = (int) ($event['ts'] ?? 0);
			if ($event_ts < $today_ts) {
				$past_events[] = $event;
				continue;
			}
			if ($event_ts > $today_ts) {
				$future_events[] = $event;
				continue;
			}
			$today_events[] = $event;
		}

		cmx_cockpit_pendenzen_sort_events($past_events, 'desc');
		cmx_cockpit_pendenzen_sort_events($today_events, 'asc');
		cmx_cockpit_pendenzen_sort_events($future_events, 'asc');

		$day_options = [
			3 => '3 Tage',
			7 => '7 Tage',
			15 => '15 Tage',
			30 => '30 Tage',
			60 => '60 Tage',
		];

		echo '<div class="cmx-pend-board-grid">';
		echo '<section class="postbox cmx-pend-postbox cmx-pend-postbox--full">';
		echo '<div class="postbox-header"><h2 class="hndle"><span class="cmx-pend-postbox-title"><span>Zeitraum</span></span></h2></div>';
		echo '<div class="inside">';
		echo '<form class="cmx-pend-toolbar-form" method="get">';
		echo '<input type="hidden" name="page" value="' . \esc_attr(cmx_cockpit_pendenzen_slug()) . '">';
		echo '<input type="hidden" name="view" value="board">';
		echo '<div class="cmx-pend-field cmx-pend-board-select">';
		echo '<label for="cmx-pend-past">Vergangenheit</label>';
		echo '<select id="cmx-pend-past" name="past" aria-label="Vergangene Tage">';
		foreach ($day_options as $value => $label) {
			echo '<option value="' . (int) $value . '"' . \selected($past_days, $value, false) . '>Letzte ' . \esc_html($label) . '</option>';
		}
		echo '</select>';
		echo '</div>';
		echo '<div class="cmx-pend-toolbar-today">' . \esc_html(cmx_cockpit_pendenzen_format_board_date($today_ts)) . ' - Heute</div>';
		echo '<div class="cmx-pend-field cmx-pend-field--right cmx-pend-board-select">';
		echo '<label for="cmx-pend-future">Zukunft</label>';
		echo '<select id="cmx-pend-future" name="future" aria-label="Nächste Tage">';
		foreach ($day_options as $value => $label) {
			echo '<option value="' . (int) $value . '"' . \selected($future_days, $value, false) . '>Nächste ' . \esc_html($label) . '</option>';
		}
		echo '</select>';
		echo '</div>';
		echo '</form>';
		echo '</div>';
		echo '</section>';

		echo '<section class="postbox cmx-pend-postbox">';
		echo '<div class="postbox-header"><h2 class="hndle"><span class="cmx-pend-postbox-title"><span>Vergangenheit</span><span class="cmx-pend-count">' . (int) \count($past_events) . '</span></span></h2></div>';
		echo '<div class="inside">';
		cmx_cockpit_pendenzen_render_day_column($past_events, 'Keine Einträge in den letzten ' . $past_days . ' Tagen.');
		echo '</div>';
		echo '</section>';
		echo '<section class="postbox cmx-pend-postbox">';
		echo '<div class="postbox-header"><h2 class="hndle"><span class="cmx-pend-postbox-title"><span>Heute</span><span class="cmx-pend-count">' . (int) \count($today_events) . '</span></span></h2></div>';
		echo '<div class="inside">';
		cmx_cockpit_pendenzen_render_day_column($today_events, 'Heute sind keine Fristen erfasst.');
		echo '</div>';
		echo '</section>';
		echo '<section class="postbox cmx-pend-postbox">';
		echo '<div class="postbox-header"><h2 class="hndle"><span class="cmx-pend-postbox-title"><span>Zukunft</span><span class="cmx-pend-count">' . (int) \count($future_events) . '</span></span></h2></div>';
		echo '<div class="inside">';
		cmx_cockpit_pendenzen_render_day_column($future_events, 'Keine Einträge in den nächsten ' . $future_days . ' Tagen.');
		echo '</div>';
		echo '</section>';
		echo '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_render_calendar')) {
	function cmx_cockpit_pendenzen_render_calendar(): void {
		$month = cmx_cockpit_pendenzen_month_value();
		$tz = \wp_timezone();
		$month_start = \DateTimeImmutable::createFromFormat('!Y-m', $month, $tz);
		if (!$month_start instanceof \DateTimeImmutable) {
			$month_start = new \DateTimeImmutable('first day of this month', $tz);
		}
		$month_start = $month_start->setDate((int) $month_start->format('Y'), (int) $month_start->format('m'), 1)->setTime(0, 0, 0);
		$month_end = $month_start->modify('last day of this month')->setTime(0, 0, 0);
		$today_ts = cmx_cockpit_pendenzen_today_start_ts();
		$events = cmx_cockpit_pendenzen_collect_events((int) $month_start->getTimestamp(), (int) $month_end->getTimestamp(), $today_ts);

		$grouped = [];
		foreach ($events as $event) {
			$key = (string) ($event['date'] ?? '');
			if ($key === '') {
				continue;
			}
			$grouped[$key][] = $event;
		}

		$prev_month = $month_start->modify('-1 month')->format('Y-m');
		$next_month = $month_start->modify('+1 month')->format('Y-m');
		$current_month = (string) \wp_date('Y-m', null, $tz);
		$weekday_labels = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
		$selected_year = (int) $month_start->format('Y');
		$selected_month = (int) $month_start->format('n');
		$month_options = '';
		for ($month_number = 1; $month_number <= 12; $month_number++) {
			$month_date = \DateTimeImmutable::createFromFormat('!Y-n-j', $selected_year . '-' . $month_number . '-1', $tz);
			$month_label = $month_date instanceof \DateTimeImmutable
				? (string) \wp_date('F', (int) $month_date->getTimestamp(), $tz)
				: \sprintf('%02d', $month_number);
			$month_options .= '<option value="' . (int) $month_number . '"' . \selected($selected_month, $month_number, false) . '>' . \esc_html($month_label) . '</option>';
		}

		$year_options = '';
		for ($year = $selected_year - 5; $year <= $selected_year + 5; $year++) {
			$year_options .= '<option value="' . (int) $year . '"' . \selected($selected_year, $year, false) . '>' . (int) $year . '</option>';
		}

		echo '<section class="postbox cmx-pend-postbox">';
		echo '<div class="postbox-header"><div class="hndle"><div class="cmx-pend-postbox-title"><div class="cmx-pend-calendar-heading"><form class="cmx-pend-calendar-switch cmx-pend-auto-submit" method="get" action="' . \esc_url(\admin_url('index.php')) . '"><input type="hidden" name="page" value="' . \esc_attr(cmx_cockpit_pendenzen_slug()) . '"><input type="hidden" name="view" value="calendar"><select name="calendar_month" aria-label="Monat wählen">' . $month_options . '</select><select class="cmx-pend-calendar-year" name="calendar_year" aria-label="Jahr wählen">' . $year_options . '</select></form></div><span class="cmx-pend-calendar-nav"><a class="button" href="' . \esc_url(cmx_cockpit_pendenzen_base_url(['view' => 'calendar', 'month' => $prev_month])) . '" aria-label="Vorheriger Monat"><span class="dashicons dashicons-arrow-left-alt2"></span></a><a class="button" href="' . \esc_url(cmx_cockpit_pendenzen_base_url(['view' => 'calendar', 'month' => $current_month])) . '" aria-label="Zum aktuellen Monat"><span class="dashicons dashicons-calendar-alt"></span></a><a class="button" href="' . \esc_url(cmx_cockpit_pendenzen_base_url(['view' => 'calendar', 'month' => $next_month])) . '" aria-label="Nächster Monat"><span class="dashicons dashicons-arrow-right-alt2"></span></a></span></div></div></div>';
		$grid_start = $month_start->modify('-' . ((int) $month_start->format('N') - 1) . ' days');
		$grid_end = $month_end->modify('+' . (7 - (int) $month_end->format('N')) . ' days');
		$grid_days = ((int) $grid_start->diff($grid_end)->days) + 1;
		$week_count = \max(1, (int) ($grid_days / 7));
		echo '<div class="inside cmx-pend-calendar-shell" data-calendar-weeks="' . (int) $week_count . '">';
		echo '<div class="cmx-pend-calendar-grid" style="--cmx-pend-calendar-weeks:' . (int) $week_count . '">';
		foreach ($weekday_labels as $weekday_label) {
			echo '<div class="cmx-pend-calendar-weekday">' . \esc_html($weekday_label) . '</div>';
		}

		$cursor = $grid_start;
		while ($cursor <= $grid_end) {
			$is_current_month = $cursor->format('Y-m') === $month_start->format('Y-m');
			$is_today = cmx_cockpit_pendenzen_day_ymd((int) $cursor->getTimestamp()) === cmx_cockpit_pendenzen_day_ymd($today_ts);
			$date_key = $cursor->format('Y-m-d');
			$day_events = (array) ($grouped[$date_key] ?? []);
			cmx_cockpit_pendenzen_sort_events($day_events, 'asc');

			$classes = ['cmx-pend-calendar-cell'];
			if (!$is_current_month) {
				$classes[] = 'is-muted';
			}
			if ($is_today) {
				$classes[] = 'is-today';
			}

			echo '<div class="' . \esc_attr(\implode(' ', $classes)) . '">';
			echo '<div class="cmx-pend-calendar-day">';
			echo '<span class="cmx-pend-calendar-number">' . \esc_html($cursor->format('j')) . '</span>';
			echo '</div>';
			echo '<div class="cmx-pend-calendar-items">';
			$visible = \array_slice($day_events, 0, 3);
			foreach ($visible as $event) {
				$tone = \sanitize_html_class((string) ($event['tone'] ?? 'red'));
				$url = \trim((string) ($event['url'] ?? ''));
				$text = \trim((string) ($event['label'] ?? '')) . ': ' . \trim((string) ($event['subject'] ?? ''));
				if ($url !== '') {
					echo '<a class="cmx-pend-calendar-chip cmx-pend-calendar-chip--' . \esc_attr($tone) . '" href="' . \esc_url($url) . '">' . \esc_html($text) . '</a>';
				} else {
					echo '<span class="cmx-pend-calendar-chip cmx-pend-calendar-chip--' . \esc_attr($tone) . '">' . \esc_html($text) . '</span>';
				}
			}
			if (\count($day_events) > 3) {
				echo '<div class="cmx-pend-calendar-more">+' . (int) (\count($day_events) - 3) . ' weitere</div>';
			}
			echo '</div>';
			echo '</div>';

			$cursor = $cursor->modify('+1 day');
		}
		echo '</div>';
		echo '</div>';
		echo '</section>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_render_modal')) {
	function cmx_cockpit_pendenzen_render_modal(): void {
		echo '<div id="cmx-pend-modal" class="cmx-pend-modal" hidden>';
		echo '<div class="cmx-pend-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="cmx-pend-modal-title">';
		echo '<div class="cmx-pend-modal-head">';
		echo '<div><p class="cmx-pend-modal-eyebrow">Pendenz</p><h2 id="cmx-pend-modal-title" class="cmx-pend-modal-title">Hinzufügen</h2></div>';
		echo '<button type="button" class="cmx-pend-modal-close" id="cmx-pend-modal-close" aria-label="Schließen"><span class="dashicons dashicons-no-alt"></span></button>';
		echo '</div>';
		echo '<div class="cmx-pend-modal-body">';
		echo '<form id="cmx-pend-create-form" class="cmx-pend-form-grid">';
		echo '<div class="cmx-pend-form-row cmx-pend-form-row--datetime">';
		echo '<div class="cmx-pend-form-field"><label id="cmx-pend-create-date-label" class="cmx-pend-form-label--quickfill" for="cmx-pend-create-date">Datum</label><input type="date" id="cmx-pend-create-date" name="date" required></div>';
		echo '<div class="cmx-pend-form-field"><label id="cmx-pend-create-time-label" class="cmx-pend-form-label--quickfill" for="cmx-pend-create-time">Uhrzeit</label><input type="time" id="cmx-pend-create-time" name="time"></div>';
		echo '<div class="cmx-pend-form-field"><label for="cmx-pend-create-hinweis">Hinweis</label><input type="date" id="cmx-pend-create-hinweis" name="hinweis"></div>';
		echo '<label class="cmx-pend-form-checkbox" for="cmx-pend-create-all-day"><input type="checkbox" id="cmx-pend-create-all-day" name="all_day" value="1"> Ganztägig</label>';
		echo '</div>';
		echo '<div class="cmx-pend-form-field"><label for="cmx-pend-create-subject">Betreff</label><input type="text" id="cmx-pend-create-subject" name="subject" required></div>';
		echo '<div class="cmx-pend-form-field"><label for="cmx-pend-create-description">Beschreibung</label><textarea id="cmx-pend-create-description" name="description"></textarea></div>';
		echo '<div class="cmx-pend-form-field"><label for="cmx-pend-create-url">URL</label><input type="url" id="cmx-pend-create-url" name="url" placeholder="https://"></div>';
		echo '<div class="cmx-pend-form-field">';
		echo '<label for="cmx-pend-create-contact-search">Kontakt</label>';
		echo '<div class="cmx-pend-contact-suggest">';
		echo '<input type="text" id="cmx-pend-create-contact-search" autocomplete="off" placeholder="Kontakt auswählen">';
		echo '<input type="hidden" id="cmx-pend-create-contact-id" name="contact_id" value="">';
		echo '<ul id="cmx-pend-create-contact-results" class="cmx-pend-contact-results" style="display:none"></ul>';
		echo '</div>';
		echo '</div>';
		echo '</form>';
		echo '</div>';
		echo '<div class="cmx-pend-modal-foot">';
		echo '<div id="cmx-pend-modal-status" class="cmx-pend-modal-status" aria-live="polite"></div>';
		echo '<div class="cmx-pend-modal-actions">';
		echo '<button type="button" class="button" id="cmx-pend-modal-cancel">Abbrechen</button>';
		echo '<button type="button" class="button button-primary" id="cmx-pend-modal-save">Speichern</button>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_view_pendenzen_page')) {
	function cmx_render_view_pendenzen_page(): void {
		$view = cmx_cockpit_pendenzen_view();
		$active_range = isset($_GET['range']) ? (int) $_GET['range'] : 30;
		if (!\in_array($active_range, [7, 30], true)) {
			$active_range = 30;
		}

		echo '<div class="wrap cmx-pend-wrap">';
		echo '<div class="cmx-pend-head">';
		echo '<div class="cmx-pend-title-copy">';
		echo '<div class="cmx-pend-title-row">';
		echo '<h1 class="cmx-pend-title">Pendenzenübersicht</h1>';
		echo '<a href="#" id="cmx-pend-open-create" class="page-title-action cmx-pend-add-button">Hinzufügen</a>';
		echo '</div>';
		echo '<p class="cmx-pend-subtitle">Alle Fristen aus Belegen, Kontakten und Projekten in einer gemeinsamen Ansicht.</p>';
		echo '</div>';
		echo '<div class="cmx-pend-actions">';
		echo '<a class="button' . ($view === 'board' && $active_range === 30 ? ' button-primary' : '') . '" href="' . \esc_url(cmx_cockpit_pendenzen_base_url(['view' => 'board', 'range' => 30, 'past' => 15, 'future' => 15])) . '">30 Tage Ansicht</a>';
		echo '<a class="button' . ($view === 'board' && $active_range === 7 ? ' button-primary' : '') . '" href="' . \esc_url(cmx_cockpit_pendenzen_base_url(['view' => 'board', 'range' => 7, 'past' => 3, 'future' => 3])) . '">7 Tage</a>';
		echo '<a class="button' . ($view === 'calendar' ? ' button-primary' : '') . '" href="' . \esc_url(cmx_cockpit_pendenzen_base_url(['view' => 'calendar'])) . '">Kalender</a>';
		echo '</div>';
		echo '</div>';
		echo '<div class="cmx-pend-body">';

		if ($view === 'calendar') {
			cmx_cockpit_pendenzen_render_calendar();
		} else {
			cmx_cockpit_pendenzen_render_board();
		}

		echo '</div>';
		cmx_cockpit_pendenzen_render_modal();
		echo '</div>';
	}
}

\add_action('admin_menu', function (): void {
	\add_submenu_page(
		'index.php',
		'Pendenzenübersicht',
		'Pendenzen',
		'edit_posts',
		cmx_cockpit_pendenzen_slug(),
		__NAMESPACE__ . '\\cmx_render_view_pendenzen_page',
		1
	);
});

\add_action('admin_enqueue_scripts', function (string $hook): void {
	if ($hook !== 'dashboard_page_' . cmx_cockpit_pendenzen_slug()) {
		return;
	}

	$style_handle = 'cmx-cockpit-pendenzen';
	\wp_register_style($style_handle, false, [], '1.0');
	\wp_enqueue_style($style_handle);
	\wp_add_inline_style($style_handle, cmx_cockpit_pendenzen_css());

	$script_handle = 'cmx-cockpit-pendenzen-js';
	\wp_register_script($script_handle, false, [], '1.0', true);
	\wp_enqueue_script($script_handle);
	\wp_add_inline_script($script_handle, '(function(){
		var resizeTimer = 0;
		var applyCalendarSizing = function(){
			document.querySelectorAll(".cmx-pend-calendar-shell").forEach(function(shell){
				var grid = shell.querySelector(".cmx-pend-calendar-grid");
				if (!grid) {
					return;
				}
				if (window.matchMedia("(max-width: 1280px)").matches) {
					grid.style.removeProperty("--cmx-pend-calendar-cell-height");
					return;
				}

				var weeks = parseInt(shell.getAttribute("data-calendar-weeks") || grid.style.getPropertyValue("--cmx-pend-calendar-weeks") || "6", 10);
				if (!weeks || weeks < 1) {
					weeks = 6;
				}

				var weekday = grid.querySelector(".cmx-pend-calendar-weekday");
				var top = grid.getBoundingClientRect().top;
				var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
				var availableHeight = Math.floor(viewportHeight - top - 24);
				if (availableHeight <= 0) {
					return;
				}

				var computed = window.getComputedStyle(grid);
				var rowGap = parseFloat(computed.rowGap || computed.gap || "12") || 12;
				var weekdayHeight = weekday ? Math.ceil(weekday.getBoundingClientRect().height) : 18;
				var reservedHeight = weekdayHeight + (rowGap * weeks) + 120;
				var cellHeight = Math.floor((availableHeight - reservedHeight) / weeks);
				cellHeight = Math.max(48, Math.min(112, cellHeight));
				grid.style.setProperty("--cmx-pend-calendar-cell-height", cellHeight + "px");
			});
		};
		var scheduleCalendarSizing = function(){
			window.clearTimeout(resizeTimer);
			resizeTimer = window.setTimeout(applyCalendarSizing, 30);
		};
		var init = function(){
			document.querySelectorAll(".cmx-pend-board-select select, .cmx-pend-auto-submit select").forEach(function(select){
				select.addEventListener("change", function(){
					var form = select.closest("form");
					if (form) {
						form.submit();
					}
				});
			});
			scheduleCalendarSizing();
		};
		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", init);
		} else {
			init();
		}
		window.addEventListener("load", scheduleCalendarSizing);
		window.addEventListener("resize", scheduleCalendarSizing);
	})();');

	$pend_modal_data = [
		'ajaxUrl' => (string) \admin_url('admin-ajax.php'),
		'addNonce' => (string) \wp_create_nonce('cmx_cockpit_pendenzen_add_manual_event'),
		'contactNonce' => (string) \wp_create_nonce('cmx_search_kontakte'),
	];
	\wp_add_inline_script($script_handle, '(function(config){
		if (!config || !config.ajaxUrl) return;

		var modal = document.getElementById("cmx-pend-modal");
		var openButton = document.getElementById("cmx-pend-open-create");
		var closeButton = document.getElementById("cmx-pend-modal-close");
		var cancelButton = document.getElementById("cmx-pend-modal-cancel");
		var saveButton = document.getElementById("cmx-pend-modal-save");
		var form = document.getElementById("cmx-pend-create-form");
		var status = document.getElementById("cmx-pend-modal-status");
		var dateLabel = document.getElementById("cmx-pend-create-date-label");
		var dateInput = document.getElementById("cmx-pend-create-date");
		var timeLabel = document.getElementById("cmx-pend-create-time-label");
		var timeInput = document.getElementById("cmx-pend-create-time");
		var hinweisInput = document.getElementById("cmx-pend-create-hinweis");
		var allDayInput = document.getElementById("cmx-pend-create-all-day");
		var subjectInput = document.getElementById("cmx-pend-create-subject");
		var descriptionInput = document.getElementById("cmx-pend-create-description");
		var urlInput = document.getElementById("cmx-pend-create-url");
		var contactSearch = document.getElementById("cmx-pend-create-contact-search");
		var contactId = document.getElementById("cmx-pend-create-contact-id");
		var contactResults = document.getElementById("cmx-pend-create-contact-results");
		if (!modal || !openButton || !closeButton || !cancelButton || !saveButton || !form || !status || !dateInput || !timeInput || !hinweisInput || !allDayInput || !subjectInput || !descriptionInput || !urlInput || !contactSearch || !contactId || !contactResults) return;

		var timer = null;
		var active = -1;
		var items = [];

		function setStatus(message, tone){
			status.textContent = String(message || "");
			if (tone) {
				status.setAttribute("data-tone", String(tone));
			} else {
				status.removeAttribute("data-tone");
			}
		}

		function triggerInputEvents(input){
			if (!input) return;
			try { input.dispatchEvent(new Event("input", { bubbles: true })); } catch (err) {}
			try { input.dispatchEvent(new Event("change", { bubbles: true })); } catch (err) {}
		}

		function getTodayValue(){
			var now = new Date();
			var local = new Date(now.getTime() - (now.getTimezoneOffset() * 60000));
			return local.toISOString().slice(0, 10);
		}

		function getCurrentTimeValue(){
			var now = new Date();
			var hours = String(now.getHours()).padStart(2, "0");
			var minutes = String(now.getMinutes()).padStart(2, "0");
			return hours + ":" + minutes;
		}

		function resetForm(){
			form.reset();
			contactId.value = "";
			contactSearch.value = "";
			closeResults();
			toggleAllDay();
			setStatus("", "");
		}

		function openModal(){
			resetForm();
			modal.hidden = false;
			document.body.classList.add("cmx-pend-modal-open");
			window.setTimeout(function(){
				try { dateInput.focus(); } catch (err) {}
			}, 30);
		}

		function closeModal(){
			modal.hidden = true;
			document.body.classList.remove("cmx-pend-modal-open");
			setStatus("", "");
			closeResults();
		}

		function toggleAllDay(){
			var allDay = !!allDayInput.checked;
			timeInput.disabled = allDay;
			if (allDay) {
				timeInput.value = "";
			}
		}

		function esc(str){
			return String(str || "").replace(/[&<>"\\x27]/g, function(c){
				if (c === "&") return "&amp;";
				if (c === "<") return "&lt;";
				if (c === ">") return "&gt;";
				if (c.charCodeAt(0) === 34) return "&quot;";
				return "&#039;";
			});
		}

		function closeResults(){
			contactResults.style.display = "none";
			contactResults.innerHTML = "";
			items = [];
			active = -1;
		}

		function setActive(next){
			if (!items.length) {
				active = -1;
				return;
			}
			if (next < 0) next = items.length - 1;
			if (next >= items.length) next = 0;
			active = next;
			Array.prototype.forEach.call(contactResults.children, function(li, index){
				li.classList.toggle("active", index === active);
				if (index === active) {
					try { li.scrollIntoView({ block: "nearest" }); } catch (err) {}
				}
			});
		}

		function chooseContact(item){
			contactId.value = item && item.id ? String(item.id) : "";
			contactSearch.value = item && item.title ? String(item.title) : "";
			closeResults();
			contactSearch.focus();
		}

		function renderResults(rows){
			items = Array.isArray(rows) ? rows : [];
			if (!items.length) {
				contactResults.innerHTML = "<li style=\"color:#64748b;cursor:default;\">Keine Kontakte gefunden.</li>";
				contactResults.style.display = "block";
				active = -1;
				return;
			}
			contactResults.innerHTML = items.map(function(item, index){
				var title = item && item.title ? item.title : "";
				var addr = item && item.addr ? item.addr : "";
				return "<li data-index=\"" + index + "\"><span>" + esc(title) + "</span>" + (addr ? "<small>" + esc(addr) + "</small>" : "") + "</li>";
			}).join("");
			contactResults.style.display = "block";
			active = -1;
		}

		function searchContacts(query){
			var url = config.ajaxUrl + "?action=cmx_search_kontakte&_ajax_nonce=" + encodeURIComponent(config.contactNonce) + "&q=" + encodeURIComponent(query || "");
			fetch(url, { credentials: "same-origin" }).then(function(response){
				return response.json();
			}).then(function(json){
				if (!json || !json.success || !json.data || !Array.isArray(json.data.items)) {
					closeResults();
					return;
				}
				renderResults(json.data.items || []);
			}).catch(function(){
				closeResults();
			});
		}

		openButton.addEventListener("click", function(e){
			e.preventDefault();
			openModal();
		});
		closeButton.addEventListener("click", function(){ closeModal(); });
		cancelButton.addEventListener("click", function(){ closeModal(); });
		modal.addEventListener("click", function(e){
			if (e.target === modal) {
				closeModal();
			}
		});
		document.addEventListener("keydown", function(e){
			if (e.key === "Escape" && !modal.hidden) {
				if (contactResults.style.display === "block") {
					closeResults();
					return;
				}
				closeModal();
			}
		});

		allDayInput.addEventListener("change", toggleAllDay);
		toggleAllDay();

		if (dateLabel) {
			dateLabel.addEventListener("click", function(e){
				e.preventDefault();
				dateInput.value = getTodayValue();
				triggerInputEvents(dateInput);
				try { dateInput.focus(); } catch (err) {}
			});
		}

		if (timeLabel) {
			timeLabel.addEventListener("click", function(e){
				e.preventDefault();
				allDayInput.checked = false;
				toggleAllDay();
				timeInput.value = getCurrentTimeValue();
				triggerInputEvents(timeInput);
				try { timeInput.focus(); } catch (err) {}
			});
		}

		contactSearch.addEventListener("input", function(){
			contactId.value = "";
			if (timer) window.clearTimeout(timer);
			var query = String(contactSearch.value || "").trim();
			if (query.length === 0) {
				searchContacts("");
				return;
			}
			if (query.length < 2) {
				closeResults();
				return;
			}
			timer = window.setTimeout(function(){ searchContacts(query); }, 180);
		});
		contactSearch.addEventListener("focus", function(){
			searchContacts(String(contactSearch.value || "").trim());
		});
		contactSearch.addEventListener("click", function(){
			searchContacts(String(contactSearch.value || "").trim());
		});
		contactSearch.addEventListener("keydown", function(e){
			if (contactResults.style.display !== "block" && (e.key === "ArrowDown" || e.key === "ArrowUp")) return;
			if (e.key === "ArrowDown") {
				e.preventDefault();
				setActive(active + 1);
			} else if (e.key === "ArrowUp") {
				e.preventDefault();
				setActive(active - 1);
			} else if (e.key === "Enter") {
				if (active > -1 && items[active]) {
					e.preventDefault();
					chooseContact(items[active]);
				}
			} else if (e.key === "Escape") {
				closeResults();
			}
		});
		contactResults.addEventListener("mousedown", function(e){
			var li = e.target && e.target.closest ? e.target.closest("li[data-index]") : null;
			if (!li) return;
			e.preventDefault();
			var index = parseInt(li.getAttribute("data-index") || "-1", 10);
			if (!isNaN(index) && items[index]) {
				chooseContact(items[index]);
			}
		});
		contactResults.addEventListener("mousemove", function(e){
			var li = e.target && e.target.closest ? e.target.closest("li[data-index]") : null;
			if (!li) return;
			var index = parseInt(li.getAttribute("data-index") || "-1", 10);
			if (!isNaN(index)) setActive(index);
		});
		document.addEventListener("click", function(e){
			if (e.target === contactSearch || contactResults.contains(e.target)) return;
			closeResults();
		});

		saveButton.addEventListener("click", function(){
			var date = String(dateInput.value || "").trim();
			var allDay = !!allDayInput.checked;
			var time = String(timeInput.value || "").trim();
			var hinweis = String(hinweisInput.value || "").trim();
			var subject = String(subjectInput.value || "").trim();
			var description = String(descriptionInput.value || "").trim();
			var url = String(urlInput.value || "").trim();
			var selectedContactId = String(contactId.value || "").trim();

			if (!date || !subject) {
				setStatus("Datum und Betreff sind erforderlich.", "");
				return;
			}
			if (!allDay && !time) {
				setStatus("Bitte eine Uhrzeit angeben oder Ganztägig aktivieren.", "");
				return;
			}

			saveButton.disabled = true;
			setStatus("Eintrag wird gespeichert…", "success");
			var formData = new FormData();
			formData.append("action", "cmx_cockpit_pendenzen_add_manual_event");
			formData.append("_ajax_nonce", config.addNonce);
			formData.append("date", date);
			formData.append("time", allDay ? "" : time);
			formData.append("hinweis", hinweis);
			formData.append("all_day", allDay ? "1" : "");
			formData.append("subject", subject);
			formData.append("description", description);
			formData.append("url", url);
			formData.append("contact_id", selectedContactId);

			fetch(config.ajaxUrl, {
				method: "POST",
				credentials: "same-origin",
				body: formData
			}).then(function(response){
				return response.json();
			}).then(function(json){
				if (!json || !json.success) {
					var message = json && json.data && json.data.message ? String(json.data.message) : "Speichern fehlgeschlagen.";
					setStatus(message, "");
					saveButton.disabled = false;
					return;
				}
				setStatus("Pendenz gespeichert.", "success");
				window.location.reload();
			}).catch(function(){
				setStatus("Speichern fehlgeschlagen.", "");
				saveButton.disabled = false;
			});
		});

		resetForm();
	})(' . \wp_json_encode($pend_modal_data) . ');');
});
