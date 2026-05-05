<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

function cmx_buchungen_opening_hours(): array {
	$default = [
		1 => [['09:00', '17:00']],
		2 => [['09:00', '17:00']],
		3 => [['09:00', '17:00']],
		4 => [['09:00', '17:00']],
		5 => [['09:00', '17:00']],
		6 => [],
		7 => [],
	];
	return (array) \apply_filters('cmx_buchungen_opening_hours', $default);
}

function cmx_buchungen_absence_ranges(int $mitarbeiter_term_id = 0, int $ressource_term_id = 0): array {
	return (array) \apply_filters('cmx_buchungen_absence_ranges', [], $mitarbeiter_term_id, $ressource_term_id);
}

function cmx_buchungen_service_duration(int $artikel_id): int {
	$duration = $artikel_id > 0 ? (int) \get_post_meta($artikel_id, '_cmx_buchung_dauer', true) : 0;
	if ($duration <= 0) {
		$duration = $artikel_id > 0 ? (int) \get_post_meta($artikel_id, '_cmx_artikel_dauer', true) : 0;
	}
	return $duration > 0 ? $duration : 60;
}

function cmx_buchungen_time_to_minutes(string $time): int {
	$time = cmx_buchungen_sanitize_time($time);
	if ($time === '') {
		return -1;
	}
	[$hour, $minute] = \array_map('intval', \explode(':', $time));
	return ($hour * 60) + $minute;
}

function cmx_buchungen_booking_ranges(string $date, int $mitarbeiter_term_id = 0, int $ressource_term_id = 0, int $exclude_post_id = 0): array {
	$date = cmx_buchungen_sanitize_date($date);
	if ($date === '') {
		return [];
	}

	$meta_query = [
		'relation' => 'AND',
		['key' => CMX_BUCHUNGEN_META_START_DATE, 'value' => $date, 'compare' => '='],
		['key' => CMX_BUCHUNGEN_META_STATUS, 'value' => cmx_buchungen_active_statuses(), 'compare' => 'IN'],
	];
	if ($mitarbeiter_term_id > 0) {
		$meta_query[] = ['key' => CMX_BUCHUNGEN_META_MITARBEITER, 'value' => (string) $mitarbeiter_term_id, 'compare' => '='];
	}
	if ($ressource_term_id > 0) {
		$meta_query[] = ['key' => CMX_BUCHUNGEN_META_RESSOURCE, 'value' => (string) $ressource_term_id, 'compare' => '='];
	}

	$ids = \get_posts([
		'post_type' => CMX_BUCHUNGEN_CPT,
		'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
		'fields' => 'ids',
		'posts_per_page' => -1,
		'no_found_rows' => true,
		'post__not_in' => $exclude_post_id > 0 ? [$exclude_post_id] : [],
		'meta_query' => $meta_query,
	]);

	$ranges = [];
	foreach ((array) $ids as $id) {
		$start = cmx_buchungen_time_to_minutes((string) \get_post_meta((int) $id, CMX_BUCHUNGEN_META_START_TIME, true));
		if ($start < 0) {
			continue;
		}
		$duration = \max(5, (int) \get_post_meta((int) $id, CMX_BUCHUNGEN_META_DURATION, true));
		$before = \max(0, (int) \get_post_meta((int) $id, CMX_BUCHUNGEN_META_BUFFER_BEFORE, true));
		$after = \max(0, (int) \get_post_meta((int) $id, CMX_BUCHUNGEN_META_BUFFER_AFTER, true));
		$ranges[] = [$start - $before, $start + $duration + $after];
	}

	return $ranges;
}

function cmx_buchungen_slot_is_free(string $date, string $time, int $duration, int $mitarbeiter_term_id = 0, int $ressource_term_id = 0, int $exclude_post_id = 0): bool {
	$start = cmx_buchungen_time_to_minutes($time);
	if ($start < 0 || $duration <= 0) {
		return false;
	}
	$end = $start + $duration;

	foreach (cmx_buchungen_absence_ranges($mitarbeiter_term_id, $ressource_term_id) as $range) {
		$range_date = cmx_buchungen_sanitize_date((string) ($range['date'] ?? ''));
		if ($range_date !== '' && $range_date !== $date) {
			continue;
		}
		$from = cmx_buchungen_time_to_minutes((string) ($range['from'] ?? ''));
		$to = cmx_buchungen_time_to_minutes((string) ($range['to'] ?? ''));
		if ($from >= 0 && $to > $from && $start < $to && $end > $from) {
			return false;
		}
	}

	foreach (cmx_buchungen_booking_ranges($date, $mitarbeiter_term_id, $ressource_term_id, $exclude_post_id) as [$busy_from, $busy_to]) {
		if ($start < $busy_to && $end > $busy_from) {
			return false;
		}
	}

	return true;
}

function cmx_buchungen_available_slots(string $date, int $duration = 60, int $mitarbeiter_term_id = 0, int $ressource_term_id = 0, int $step = 15): array {
	$date = cmx_buchungen_sanitize_date($date);
	if ($date === '') {
		return [];
	}
	$timestamp = \strtotime($date);
	if ($timestamp === false) {
		return [];
	}

	$weekday = (int) \wp_date('N', $timestamp);
	$hours = cmx_buchungen_opening_hours()[$weekday] ?? [];
	$slots = [];
	foreach ((array) $hours as $range) {
		$from = cmx_buchungen_time_to_minutes((string) ($range[0] ?? ''));
		$to = cmx_buchungen_time_to_minutes((string) ($range[1] ?? ''));
		if ($from < 0 || $to <= $from) {
			continue;
		}
		for ($minute = $from; $minute + $duration <= $to; $minute += \max(5, $step)) {
			$time = \sprintf('%02d:%02d', \intdiv($minute, 60), $minute % 60);
			if (cmx_buchungen_slot_is_free($date, $time, $duration, $mitarbeiter_term_id, $ressource_term_id)) {
				$slots[] = $time;
			}
		}
	}

	return $slots;
}
