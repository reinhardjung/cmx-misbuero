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

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_month_value')) {
	function cmx_cockpit_pendenzen_month_value(): string {
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
			$ts_a = (int) ($a['ts'] ?? 0);
			$ts_b = (int) ($b['ts'] ?? 0);
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_collect_events')) {
	function cmx_cockpit_pendenzen_collect_events(int $start_ts, int $end_ts, int $today_ts): array {
		$events = \array_merge(
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_css')) {
	function cmx_cockpit_pendenzen_css(): string {
		return '
			.cmx-pend-wrap{margin:20px 0 0;padding:0 20px 0 0;box-sizing:border-box}
			.cmx-pend-shell{background:linear-gradient(135deg,#f6fbff 0%,#ffffff 56%,#f4f8ff 100%);border:1px solid #cfe0f7;border-radius:14px;padding:18px;box-sizing:border-box;box-shadow:0 1px 2px rgba(16,24,40,.04),0 10px 24px rgba(16,24,40,.04)}
			.cmx-pend-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin:0 0 18px}
			.cmx-pend-title-wrap{display:flex;align-items:flex-start;gap:14px;min-width:0;flex:1 1 auto}
			.cmx-pend-title-icon{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;background:#fff;color:#3b8ed9;box-shadow:inset 0 0 0 1px #d9e6f6,0 1px 2px rgba(16,24,40,.06)}
			.cmx-pend-title-icon .dashicons{width:22px;height:22px;font-size:22px;line-height:22px}
			.cmx-pend-title-copy{min-width:0}
			.cmx-pend-title{margin:0;color:#162033;font-size:20px !important;line-height:1.2;font-weight:700}
			.cmx-pend-subtitle{margin:8px 0 0;color:#4e5968;font-size:14px;line-height:1.55;max-width:780px}
			.cmx-pend-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
			.cmx-pend-tab{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 18px;border-radius:10px;border:1px solid #cfd9e7;background:linear-gradient(180deg,#ffffff 0%,#f8fbff 100%);color:#344054;text-decoration:none;font-size:15px;font-weight:600;box-shadow:0 1px 2px rgba(16,24,40,.05)}
			.cmx-pend-tab:hover{background:#fff;color:#162033;border-color:#b7cce9}
			.cmx-pend-tab.is-active{background:#fff;color:#173d6d;border-color:#bcd2ee;box-shadow:0 0 0 2px rgba(59,142,217,.08),0 1px 2px rgba(16,24,40,.05)}
			.cmx-pend-body{padding:0}
			.cmx-pend-board-toolbar{display:grid;grid-template-columns:minmax(220px,1fr) minmax(220px,1fr) minmax(220px,1fr);gap:18px;align-items:center;margin:0 0 18px;padding:12px 14px;border:1px solid #d9e6f6;border-radius:12px;background:linear-gradient(180deg,#ffffff 0%,#f8fbff 100%)}
			.cmx-pend-board-filter{text-align:left}
			.cmx-pend-board-filter--center{text-align:center}
			.cmx-pend-board-filter--right{text-align:right}
			.cmx-pend-board-select{display:inline-flex;align-items:center;gap:10px;max-width:100%}
			.cmx-pend-board-select select{min-width:196px;max-width:100%;padding:8px 34px 8px 12px;border:1px solid #cfd9e7;border-radius:8px;background:#fff;color:#162033;font-size:14px;font-weight:600;box-shadow:0 1px 2px rgba(16,24,40,.04)}
			.cmx-pend-board-today{display:inline-block;min-width:240px;color:#41556d;font-size:18px;line-height:1.25;font-weight:700}
			.cmx-pend-columns{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
			.cmx-pend-column{min-width:0;padding:16px;border:1px solid #d7dce3;border-radius:14px;background:linear-gradient(180deg,#ffffff 0%,#fbfcfe 100%);box-shadow:0 1px 2px rgba(16,24,40,.04),0 10px 24px rgba(16,24,40,.04)}
			.cmx-pend-column-title{display:block;margin:0 0 14px;font-size:12px;font-weight:700;line-height:1.2;color:#667085;letter-spacing:.04em;text-transform:uppercase}
			.cmx-pend-card-list{display:flex;flex-direction:column;gap:12px}
			.cmx-pend-card{display:flex;gap:10px;align-items:flex-start;padding:14px 16px;border:1px solid #d9e6f6;border-radius:12px;background:#fff;box-shadow:0 1px 2px rgba(16,24,40,.04),0 8px 18px rgba(16,24,40,.04)}
			.cmx-pend-card-icon{display:flex;align-items:center;justify-content:center;flex:0 0 32px;width:32px;height:32px;border-radius:7px;color:#42556d;background:#f3f6fa;border:1px solid #d9e6f6;box-shadow:none}
			.cmx-pend-card-icon svg{display:block;width:14px;height:14px;fill:currentColor}
			.cmx-pend-card-body{min-width:0;flex:1 1 auto;padding-top:0}
			.cmx-pend-card-line{display:block;font-size:15px;line-height:1.35;color:#42556d}
			.cmx-pend-card-label{font-weight:800}
			.cmx-pend-card-link{color:inherit;text-decoration:none}
			.cmx-pend-card-link:hover{text-decoration:underline}
			.cmx-pend-card-meta{display:block;margin-top:4px;font-size:13px;line-height:1.35;color:#56687d}
			.cmx-pend-card--red .cmx-pend-card-label{color:#c44a3d}
			.cmx-pend-card--red .cmx-pend-card-icon{background:#feefee;border-color:#f7d0cb;color:#df4137}
			.cmx-pend-card--orange .cmx-pend-card-label{color:#d27b1f}
			.cmx-pend-card--orange .cmx-pend-card-icon{background:#fff4e2;border-color:#ffdca9;color:#e38b23}
			.cmx-pend-card--blue .cmx-pend-card-label{color:#3f73a7}
			.cmx-pend-card--blue .cmx-pend-card-icon{background:#edf6ff;border-color:#cfe4fb;color:#3194de}
			.cmx-pend-card--green .cmx-pend-card-label{color:#3d8642}
			.cmx-pend-card--green .cmx-pend-card-icon{background:#eef9ef;border-color:#cfeecf;color:#58b749}
			.cmx-pend-empty{padding:18px;border:1px dashed #d6dde7;border-radius:12px;background:linear-gradient(180deg,#ffffff 0%,#fbfdff 100%);color:#75859a;font-size:14px;text-align:center}
			.cmx-pend-calendar-shell{padding:16px;border:1px solid #d7dce3;border-radius:14px;background:linear-gradient(180deg,#ffffff 0%,#fbfcfe 100%);box-shadow:0 1px 2px rgba(16,24,40,.04),0 10px 24px rgba(16,24,40,.04)}
			.cmx-pend-calendar-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px}
			.cmx-pend-calendar-nav{display:inline-flex;align-items:center;gap:8px}
			.cmx-pend-calendar-nav a{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border:1px solid #d4dbe5;border-radius:10px;background:#fff;color:#41556d;text-decoration:none}
			.cmx-pend-calendar-nav a:hover{background:#f3f7fb}
			.cmx-pend-calendar-title{margin:0;color:#162033;font-size:20px;font-weight:700}
			.cmx-pend-calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:12px}
			.cmx-pend-calendar-weekday{padding:6px 4px;color:#7a8898;font-size:12px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;text-align:center}
			.cmx-pend-calendar-cell{min-height:168px;padding:10px 10px 12px;border:1px solid #dbe3ec;border-radius:14px;background:#fff;box-shadow:0 1px 2px rgba(16,24,40,.04),0 8px 18px rgba(16,24,40,.04)}
			.cmx-pend-calendar-cell.is-muted{background:rgba(255,255,255,.42);border-style:dashed}
			.cmx-pend-calendar-cell.is-today{border-color:#78a9df;box-shadow:0 0 0 2px rgba(50,120,200,.12),0 10px 18px rgba(16,24,40,.06)}
			.cmx-pend-calendar-day{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px}
			.cmx-pend-calendar-number{display:inline-flex;align-items:center;justify-content:center;min-width:30px;height:30px;border-radius:999px;color:#41556d;font-size:14px;font-weight:800}
			.cmx-pend-calendar-cell.is-today .cmx-pend-calendar-number{background:#2f7dd3;color:#fff}
			.cmx-pend-calendar-items{display:flex;flex-direction:column;gap:8px}
			.cmx-pend-calendar-chip{display:block;padding:8px 10px;border-radius:10px;border:1px solid #d9e6f6;background:#f8fbff;color:#42556d;text-decoration:none;font-size:12px;line-height:1.3;font-weight:700}
			.cmx-pend-calendar-chip:hover{opacity:1;filter:brightness(.99)}
			.cmx-pend-calendar-chip--red{background:#feefee;border-color:#f7d0cb;color:#df4137}
			.cmx-pend-calendar-chip--orange{background:#fff4e2;border-color:#ffdca9;color:#e38b23}
			.cmx-pend-calendar-chip--blue{background:#edf6ff;border-color:#cfe4fb;color:#3194de}
			.cmx-pend-calendar-chip--green{background:#eef9ef;border-color:#cfeecf;color:#58b749}
			.cmx-pend-calendar-more{margin-top:2px;color:#74849a;font-size:12px}
			@media (max-width: 1280px){
				.cmx-pend-board-toolbar,.cmx-pend-columns{grid-template-columns:1fr}
				.cmx-pend-board-filter--center,
				.cmx-pend-board-filter--right{text-align:left}
				.cmx-pend-board-today{min-width:0}
				.cmx-pend-calendar-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
			}
			@media (max-width: 782px){
				.cmx-pend-wrap{padding-right:10px}
				.cmx-pend-shell{padding:16px}
				.cmx-pend-head{flex-direction:column;align-items:stretch}
				.cmx-pend-actions{width:100%}
				.cmx-pend-tab{flex:1 1 auto}
				.cmx-pend-board-select select{min-width:0;width:100%}
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
		echo '<div class="cmx-pend-card-icon">' . cmx_cockpit_pendenzen_icon_svg((string) ($event['icon'] ?? 'warning')) . '</div>';
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

		echo '<form class="cmx-pend-board-toolbar" method="get">';
		echo '<input type="hidden" name="page" value="' . \esc_attr(cmx_cockpit_pendenzen_slug()) . '">';
		echo '<input type="hidden" name="view" value="board">';
		echo '<div class="cmx-pend-board-filter">';
		echo '<div class="cmx-pend-board-select">';
		echo '<select name="past" aria-label="Vergangene Tage">';
		foreach ($day_options as $value => $label) {
			echo '<option value="' . (int) $value . '"' . \selected($past_days, $value, false) . '>Letzte ' . \esc_html($label) . '</option>';
		}
		echo '</select>';
		echo '</div>';
		echo '</div>';
		echo '<div class="cmx-pend-board-filter cmx-pend-board-filter--center">';
		echo '<span class="cmx-pend-board-today">' . \esc_html(cmx_cockpit_pendenzen_format_board_date($today_ts)) . ' - Heute</span>';
		echo '</div>';
		echo '<div class="cmx-pend-board-filter cmx-pend-board-filter--right">';
		echo '<div class="cmx-pend-board-select">';
		echo '<select name="future" aria-label="Nächste Tage">';
		foreach ($day_options as $value => $label) {
			echo '<option value="' . (int) $value . '"' . \selected($future_days, $value, false) . '>Nächste ' . \esc_html($label) . '</option>';
		}
		echo '</select>';
		echo '</div>';
		echo '</div>';
		echo '</form>';

		echo '<div class="cmx-pend-columns">';
		echo '<section class="cmx-pend-column">';
		echo '<h2 class="cmx-pend-column-title">Vergangenheit</h2>';
		cmx_cockpit_pendenzen_render_day_column($past_events, 'Keine Einträge in den letzten ' . $past_days . ' Tagen.');
		echo '</section>';
		echo '<section class="cmx-pend-column">';
		echo '<h2 class="cmx-pend-column-title">Heute</h2>';
		cmx_cockpit_pendenzen_render_day_column($today_events, 'Heute sind keine Fristen erfasst.');
		echo '</section>';
		echo '<section class="cmx-pend-column">';
		echo '<h2 class="cmx-pend-column-title">Zukunft</h2>';
		cmx_cockpit_pendenzen_render_day_column($future_events, 'Keine Einträge in den nächsten ' . $future_days . ' Tagen.');
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
		$month_title = (string) \wp_date('F Y', (int) $month_start->getTimestamp(), $tz);
		$weekday_labels = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

		echo '<div class="cmx-pend-calendar-shell">';
		echo '<div class="cmx-pend-calendar-head">';
		echo '<div class="cmx-pend-calendar-nav">';
		echo '<a href="' . \esc_url(cmx_cockpit_pendenzen_base_url(['view' => 'calendar', 'month' => $prev_month])) . '" aria-label="Vorheriger Monat"><span class="dashicons dashicons-arrow-left-alt2"></span></a>';
		echo '</div>';
		echo '<h2 class="cmx-pend-calendar-title">' . \esc_html($month_title) . '</h2>';
		echo '<div class="cmx-pend-calendar-nav">';
		echo '<a href="' . \esc_url(cmx_cockpit_pendenzen_base_url(['view' => 'calendar', 'month' => $next_month])) . '" aria-label="Nächster Monat"><span class="dashicons dashicons-arrow-right-alt2"></span></a>';
		echo '</div>';
		echo '</div>';

		echo '<div class="cmx-pend-calendar-grid">';
		foreach ($weekday_labels as $weekday_label) {
			echo '<div class="cmx-pend-calendar-weekday">' . \esc_html($weekday_label) . '</div>';
		}

		$grid_start = $month_start->modify('-' . ((int) $month_start->format('N') - 1) . ' days');
		$grid_end = $month_end->modify('+' . (7 - (int) $month_end->format('N')) . ' days');
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
		echo '<div class="cmx-pend-shell">';
		echo '<div class="cmx-pend-head">';
		echo '<div class="cmx-pend-title-wrap">';
		echo '<div class="cmx-pend-title-copy">';
		echo '<h1 class="cmx-pend-title">Pendenzenübersicht</h1>';
		echo '<p class="cmx-pend-subtitle">Alle Fristen aus Belegen, Kontakten und Projekten in einer gemeinsamen Ansicht.</p>';
		echo '</div>';
		echo '</div>';
		echo '<div class="cmx-pend-actions">';
		echo '<a class="cmx-pend-tab' . ($view === 'board' && $active_range === 30 ? ' is-active' : '') . '" href="' . \esc_url(cmx_cockpit_pendenzen_base_url(['view' => 'board', 'range' => 30, 'past' => 15, 'future' => 15])) . '">30 Tage Ansicht</a>';
		echo '<a class="cmx-pend-tab' . ($view === 'board' && $active_range === 7 ? ' is-active' : '') . '" href="' . \esc_url(cmx_cockpit_pendenzen_base_url(['view' => 'board', 'range' => 7, 'past' => 3, 'future' => 3])) . '">7 Tage</a>';
		echo '<a class="cmx-pend-tab' . ($view === 'calendar' ? ' is-active' : '') . '" href="' . \esc_url(cmx_cockpit_pendenzen_base_url(['view' => 'calendar'])) . '">Kalender</a>';
		echo '</div>';
		echo '</div>';
		echo '<div class="cmx-pend-body">';

		if ($view === 'calendar') {
			cmx_cockpit_pendenzen_render_calendar();
		} else {
			cmx_cockpit_pendenzen_render_board();
		}

		echo '</div>';
		echo '</div>';
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
		var init = function(){
			document.querySelectorAll(".cmx-pend-board-select select").forEach(function(select){
				select.addEventListener("change", function(){
					var form = select.closest("form");
					if (form) {
						form.submit();
					}
				});
			});
		};
		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", init);
		} else {
			init();
		}
	})();');
});
