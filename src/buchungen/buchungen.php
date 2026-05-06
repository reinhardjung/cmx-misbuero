<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_url')) {
	function cmx_buchungen_frontend_url(array $args = []): string {
		$url = (string) \home_url('/buchungen/');
		return $args === [] ? $url : \add_query_arg($args, $url);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_default_headline')) {
	function cmx_buchungen_frontend_default_headline(): string {
		return 'Online Buchung';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_default_subline')) {
	function cmx_buchungen_frontend_default_subline(): string {
		return 'Leistung wählen, freien Termin aussuchen und direkt buchen.';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_setting_text')) {
	function cmx_buchungen_frontend_setting_text(string $key, string $default): string {
		$option_name = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			: 'cmx_einstellungen';
		$options = (array) \get_option($option_name, []);
		$value = \trim((string) ($options[$key] ?? ''));
		return $value !== '' ? $value : $default;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_powered_by_enabled')) {
	function cmx_buchungen_frontend_powered_by_enabled(): bool {
		$option_name = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			: 'cmx_einstellungen';
		$options = (array) \get_option($option_name, []);
		return \array_key_exists('powered_by', $options)
			? !empty($options['powered_by'])
			: true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_duration_minutes')) {
	function cmx_buchungen_frontend_duration_minutes(string $unit, int $duration): int {
		$duration = \max(1, $duration);
		return match ($unit) {
			'days' => $duration * 1440,
			'hours' => $duration * 60,
			default => \max(5, $duration),
		};
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_duration_label')) {
	function cmx_buchungen_frontend_duration_label(int $duration_minutes, string $unit = ''): string {
		$duration_minutes = \max(1, $duration_minutes);
		if ($unit === 'days' || ($unit === '' && $duration_minutes >= 1440 && $duration_minutes % 1440 === 0)) {
			$days = (int) ($duration_minutes / 1440);
			return (string) $days . ($days === 1 ? ' Tag' : ' Tage');
		}
		if ($unit === 'hours' || ($unit === '' && $duration_minutes >= 60 && $duration_minutes % 60 === 0)) {
			$hours = (int) ($duration_minutes / 60);
			return (string) $hours . ($hours === 1 ? ' Stunde' : ' Stunden');
		}

		return (string) $duration_minutes . ' Minuten';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_is_buchungen_frontend_request')) {
	function cmx_is_buchungen_frontend_request(): bool {
		if (\is_admin() || !\post_type_exists(CMX_BUCHUNGEN_CPT)) {
			return false;
		}

		$req_path = \parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), \PHP_URL_PATH);
		$req_path = \is_string($req_path) ? \trim($req_path, '/') : '';

		return $req_path === 'buchungen' || \str_starts_with($req_path, 'buchungen/') || \is_page('buchungen');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_first_image_url')) {
	function cmx_buchungen_first_image_url(array $urls): string {
		foreach ($urls as $url) {
			$url = \trim((string) $url);
			if ($url !== '') {
				return $url;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_article_image_url')) {
	function cmx_buchungen_article_image_url(int $artikel_id): string {
		if ($artikel_id <= 0 || \get_post_type($artikel_id) !== 'artikel') {
			return '';
		}

		$gallery_url = '';
		if (\function_exists(__NAMESPACE__ . '\\cmx_li_gallery_get')) {
			$gallery = cmx_li_gallery_get($artikel_id, '_cmx_local_image_artikel');
			if (!empty($gallery[0]) && \is_array($gallery[0])) {
				$gallery_url = \trim((string) ($gallery[0]['url'] ?? ''));
			}
		}

		$admin_url = \function_exists(__NAMESPACE__ . '\\cmx_artikel_admin_image_src')
			? (string) cmx_artikel_admin_image_src($artikel_id)
			: '';

		return cmx_buchungen_first_image_url([
			$gallery_url,
			\get_post_meta($artikel_id, '_cmx_local_image_artikel_url', true),
			$admin_url,
			\get_the_post_thumbnail_url($artikel_id, 'thumbnail'),
		]);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_contact_image_url')) {
	function cmx_buchungen_contact_image_url(int $kontakt_id): string {
		if ($kontakt_id <= 0 || \get_post_type($kontakt_id) !== 'kontakte') {
			return '';
		}

		$gallery_url = '';
		if (\function_exists(__NAMESPACE__ . '\\cmx_kl_active_gallery_item')) {
			$meta_base = \function_exists(__NAMESPACE__ . '\\cmx_kl_meta_base')
				? (string) cmx_kl_meta_base()
				: '_cmx_local_image_kontakte';
			$active_item = cmx_kl_active_gallery_item($kontakt_id, $meta_base);
			if (\is_array($active_item)) {
				$gallery_url = \trim((string) ($active_item['url'] ?? ''));
			}
		}

		$logo_url = \function_exists(__NAMESPACE__ . '\\cmx_contact_logo_url')
			? (string) cmx_contact_logo_url($kontakt_id)
			: '';

		return cmx_buchungen_first_image_url([
			$gallery_url,
			\get_post_meta($kontakt_id, '_cmx_local_image_kontakte_url', true),
			$logo_url,
			\get_the_post_thumbnail_url($kontakt_id, 'thumbnail'),
		]);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_services')) {
	function cmx_buchungen_frontend_services(): array {
		if (!\post_type_exists('artikel')) {
			return [];
		}

		$templates = \function_exists(__NAMESPACE__ . '\\cmx_buchungen_template_rows')
			? cmx_buchungen_template_rows(true)
			: [];

		$services = [];
		foreach ($templates as $index => $template) {
			$artikel_id = (int) ($template['artikel_id'] ?? 0);
			$kontakt_id = (int) ($template['kontakt_id'] ?? 0);
			if ($artikel_id <= 0 || \get_post_type($artikel_id) !== 'artikel') {
				continue;
			}

			$article_title = \trim((string) \get_the_title($artikel_id));
			if ($article_title === '') {
				continue;
			}

			$title = \trim((string) ($template['title'] ?? ''));
			$label = \trim((string) ($template['label'] ?? ''));
			$unit = (string) ($template['unit'] ?? 'minutes');
			if (!\in_array($unit, ['minutes', 'hours', 'days'], true)) {
				$unit = 'minutes';
			}
			if ($label === '') {
				if ($unit === 'days') {
					$label = 'Reservation';
				} else {
					$service_terms = \get_the_terms($artikel_id, \defined(__NAMESPACE__ . '\\TAX_ARTIKEL_KATEGORIEN') ? TAX_ARTIKEL_KATEGORIEN : 'artikel_kategorien');
					if (!\is_wp_error($service_terms) && !empty($service_terms)) {
						$label = (string) ($service_terms[0]->name ?? '');
					}
				}
			}

			$duration = \max($unit === 'minutes' ? 5 : 1, (int) ($template['duration'] ?? ($unit === 'days' ? 1 : 60)));
			$duration_minutes = cmx_buchungen_frontend_duration_minutes($unit, $duration);
			$image = '';
			$avatar_label = $article_title;
			if ($kontakt_id > 0 && \get_post_type($kontakt_id) === 'kontakte') {
				$image = cmx_buchungen_contact_image_url($kontakt_id);
				$contact_title = \trim((string) \get_the_title($kontakt_id));
				if ($contact_title !== '') {
					$avatar_label = $contact_title;
				}
			}
			if ($kontakt_id <= 0 && $image === '') {
				$image = cmx_buchungen_article_image_url($artikel_id);
			}

			$services[] = [
				'id' => 'tpl-' . $index,
				'artikel_id' => $artikel_id,
				'kontakt_id' => $kontakt_id,
				'title' => $title !== '' ? $title : $article_title,
				'person' => $label !== '' ? $label : 'Online Termin',
				'duration' => $duration,
				'duration_minutes' => $duration_minutes,
				'unit' => $unit,
				'period' => (string) ($template['period'] ?? 'all'),
				'weekdays' => \function_exists(__NAMESPACE__ . '\\cmx_buchungen_template_sanitize_weekdays') ? cmx_buchungen_template_sanitize_weekdays($template['weekdays'] ?? []) : [1, 2, 3, 4, 5],
				'image' => $image,
				'avatar_label' => $avatar_label,
				'color' => (string) ($template['color'] ?? '#2563eb'),
				'excerpt' => \trim((string) \get_the_excerpt($artikel_id)),
			];
		}

		return $services;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_date_range_is_free')) {
	function cmx_buchungen_frontend_date_range_is_free(string $start_date, int $days): bool {
		static $busy_ranges = null;

		$start_date = cmx_buchungen_sanitize_date($start_date);
		$days = \max(1, \min(60, $days));
		if ($start_date === '') {
			return false;
		}

		$wanted_start = \strtotime($start_date . ' 00:00:00');
		$wanted_end = \strtotime($start_date . ' +' . ($days - 1) . ' days 23:59:59');
		if (!$wanted_start || !$wanted_end) {
			return false;
		}

		if ($busy_ranges === null) {
			$busy_ranges = [];
			$ids = \get_posts([
				'post_type' => CMX_BUCHUNGEN_CPT,
				'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
				'fields' => 'ids',
				'posts_per_page' => -1,
				'no_found_rows' => true,
				'meta_query' => [
					'relation' => 'AND',
					['key' => CMX_BUCHUNGEN_META_START_DATE, 'compare' => 'EXISTS'],
					['key' => CMX_BUCHUNGEN_META_STATUS, 'value' => cmx_buchungen_active_statuses(), 'compare' => 'IN'],
				],
			]);

			foreach ((array) $ids as $id) {
				$id = (int) $id;
				$existing_date = cmx_buchungen_sanitize_date((string) \get_post_meta($id, CMX_BUCHUNGEN_META_START_DATE, true));
				if ($existing_date === '') {
					continue;
				}

				$existing_duration = \max(1, (int) \get_post_meta($id, CMX_BUCHUNGEN_META_DURATION, true));
				$existing_days = \max(1, (int) \ceil($existing_duration / 1440));
				$existing_start = \strtotime($existing_date . ' 00:00:00');
				$existing_end = \strtotime($existing_date . ' +' . ($existing_days - 1) . ' days 23:59:59');
				if ($existing_start && $existing_end) {
					$busy_ranges[] = [$existing_start, $existing_end];
				}
			}
		}

		foreach ($busy_ranges as [$existing_start, $existing_end]) {
			if ((int) $existing_start <= $wanted_end && (int) $existing_end >= $wanted_start) {
				return false;
			}
		}

		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_day_dates')) {
	function cmx_buchungen_frontend_day_dates(int $days, int $range_days = 90, array $weekdays = []): array {
		$days = \max(1, \min(60, $days));
		$range_days = \max(1, \min(365, $range_days));
		$weekdays = $weekdays !== [] ? $weekdays : [1, 2, 3, 4, 5];
		$today = \wp_date('Y-m-d');
		$dates = [];

		for ($i = 0; $i < $range_days; $i++) {
			$date = \wp_date('Y-m-d', \strtotime($today . ' +' . $i . ' days'));
			if (\function_exists(__NAMESPACE__ . '\\cmx_buchungen_template_allows_date') && !cmx_buchungen_template_allows_date(['weekdays' => $weekdays], $date)) {
				continue;
			}
			if (cmx_buchungen_frontend_date_range_is_free($date, $days)) {
				$dates[] = $date;
			}
		}

		return $dates;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_slots')) {
	function cmx_buchungen_frontend_slots(int $duration, int $days = 42): array {
		$duration = \max(5, $duration);
		$days = \max(1, \min(90, $days));
		$today = \wp_date('Y-m-d');
		$slots_by_date = [];

		for ($i = 0; $i < $days; $i++) {
			$date = \wp_date('Y-m-d', \strtotime($today . ' +' . $i . ' days'));
			$slots = \function_exists(__NAMESPACE__ . '\\cmx_buchungen_available_slots')
				? cmx_buchungen_available_slots($date, $duration)
				: [];
			if ($slots !== []) {
				$slots_by_date[$date] = \array_values($slots);
			}
		}

		return $slots_by_date;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_find_or_create_contact')) {
	function cmx_buchungen_frontend_find_or_create_contact(string $name, string $email, string $phone) {
		$name = \trim($name);
		$email = \sanitize_email($email);
		$phone = \trim($phone);
		if ($name === '' || !\is_email($email)) {
			return new \WP_Error('invalid_contact', 'Bitte Name und E-Mail ausfüllen.');
		}

		if (\post_type_exists('kontakte')) {
			$existing = \get_posts([
				'post_type' => 'kontakte',
				'post_status' => ['publish', 'private', 'draft'],
				'fields' => 'ids',
				'posts_per_page' => 1,
				'no_found_rows' => true,
				'meta_query' => [
					['key' => '_cmx_email_1', 'value' => $email, 'compare' => '='],
				],
			]);
			if (!empty($existing)) {
				$contact_id = (int) $existing[0];
				if ($phone !== '' && \trim((string) \get_post_meta($contact_id, '_cmx_telefon_1', true)) === '') {
					\update_post_meta($contact_id, '_cmx_telefon_1', $phone);
				}
				return $contact_id;
			}

			$contact_id = \wp_insert_post([
				'post_type' => 'kontakte',
				'post_status' => 'publish',
				'post_title' => $name,
				'meta_input' => [
					'_cmx_email_1' => $email,
					'_cmx_telefon_1' => $phone,
					'_cmx_kontakte_vorname' => $name,
				],
			], true);
			if (!\is_wp_error($contact_id) && (int) $contact_id > 0) {
				return (int) $contact_id;
			}
			if (\is_wp_error($contact_id)) {
				\error_log('[CMX Buchungen] Kontakt konnte nicht angelegt werden: ' . $contact_id->get_error_message());
			}
		}

		return new \WP_Error('contact_failed', 'Der Kontakt konnte nicht angelegt werden.');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_error_message')) {
	function cmx_buchungen_frontend_error_message(string $code): string {
		return match ($code) {
			'nonce' => 'Die Buchung konnte nicht gespeichert werden, weil die Seite abgelaufen ist. Bitte neu laden und erneut versuchen.',
			'missing' => 'Bitte Leistung, Datum, Uhrzeit, Name und eine gültige E-Mail ausfüllen.',
			'slot' => 'Dieser Termin ist inzwischen nicht mehr frei. Bitte einen anderen Termin auswählen.',
			'contact' => 'Der Kontakt konnte nicht angelegt werden. Bitte Name und E-Mail prüfen.',
			'create' => 'Die Buchung konnte technisch nicht angelegt werden. Details stehen im Server-Log.',
			default => 'Die Buchung konnte nicht gespeichert werden. Bitte Auswahl prüfen und erneut versuchen.',
		};
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_duration_term_id')) {
	function cmx_buchungen_frontend_duration_term_id(int $duration): int {
		if (!\taxonomy_exists(CMX_BUCHUNGEN_TAX_DAUER)) {
			return 0;
		}

		$term = \get_term_by('name', (string) $duration, CMX_BUCHUNGEN_TAX_DAUER);
		if (!$term instanceof \WP_Term) {
			$term = \get_term_by('slug', (string) $duration, CMX_BUCHUNGEN_TAX_DAUER);
		}

		return $term instanceof \WP_Term ? (int) $term->term_id : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_handle_submit')) {
	function cmx_buchungen_frontend_handle_submit(): void {
		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
			return;
		}
		if ((string) ($_POST['cmx_buchungen_frontend_action'] ?? '') !== 'book') {
			return;
		}

		$nonce = isset($_POST['cmx_buchungen_frontend_nonce']) ? (string) \wp_unslash($_POST['cmx_buchungen_frontend_nonce']) : '';
		if (!\wp_verify_nonce($nonce, 'cmx_buchungen_frontend_book')) {
			\wp_safe_redirect(cmx_buchungen_frontend_url(['cmx_booking_error' => 'nonce']));
			exit;
		}

		$template_id = isset($_POST['template_id']) ? \sanitize_key((string) \wp_unslash($_POST['template_id'])) : '';
		$selected_service = null;
		foreach (cmx_buchungen_frontend_services() as $service) {
			if ((string) ($service['id'] ?? '') === $template_id) {
				$selected_service = $service;
				break;
			}
		}

		$service_id = $selected_service !== null ? (int) ($selected_service['artikel_id'] ?? 0) : 0;
		$date = isset($_POST['date']) ? cmx_buchungen_sanitize_date(\wp_unslash($_POST['date'])) : '';
		$time = isset($_POST['time']) ? cmx_buchungen_sanitize_time(\wp_unslash($_POST['time'])) : '';
		$name = isset($_POST['name']) ? \sanitize_text_field((string) \wp_unslash($_POST['name'])) : '';
		$email = isset($_POST['email']) ? \sanitize_email((string) \wp_unslash($_POST['email'])) : '';
		$phone = isset($_POST['phone']) ? \sanitize_text_field((string) \wp_unslash($_POST['phone'])) : '';
		$note = isset($_POST['note']) ? \sanitize_textarea_field((string) \wp_unslash($_POST['note'])) : '';
		$unit = $selected_service !== null ? (string) ($selected_service['unit'] ?? 'minutes') : 'minutes';
		$raw_duration = $selected_service !== null ? \max(1, (int) ($selected_service['duration'] ?? 60)) : 60;
		$booking_days = isset($_POST['booking_days']) ? \max(1, \min(60, (int) \wp_unslash($_POST['booking_days']))) : $raw_duration;
		$duration = cmx_buchungen_frontend_duration_minutes($unit, $raw_duration);
		if ($unit === 'days') {
			$raw_duration = $booking_days;
			$duration = cmx_buchungen_frontend_duration_minutes($unit, $raw_duration);
			$time = '00:00';
		}

		if ($selected_service === null || $service_id <= 0 || \get_post_type($service_id) !== 'artikel' || $date === '' || $time === '' || $name === '' || !\is_email($email)) {
			\wp_safe_redirect(cmx_buchungen_frontend_url(['cmx_booking_error' => 'missing']));
			exit;
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_buchungen_template_allows_date') && !cmx_buchungen_template_allows_date($selected_service, $date)) {
			\wp_safe_redirect(cmx_buchungen_frontend_url(['cmx_booking_error' => 'slot']));
			exit;
		}
		if ($unit === 'days' && !cmx_buchungen_frontend_date_range_is_free($date, $raw_duration)) {
			\wp_safe_redirect(cmx_buchungen_frontend_url(['cmx_booking_error' => 'slot']));
			exit;
		}
		if ($unit !== 'days') {
			if (\function_exists(__NAMESPACE__ . '\\cmx_buchungen_template_period_allows_time') && !cmx_buchungen_template_period_allows_time((string) ($selected_service['period'] ?? 'all'), $time)) {
				\wp_safe_redirect(cmx_buchungen_frontend_url(['cmx_booking_error' => 'slot']));
				exit;
			}
			if (!cmx_buchungen_slot_is_free($date, $time, $duration)) {
				\wp_safe_redirect(cmx_buchungen_frontend_url(['cmx_booking_error' => 'slot']));
				exit;
			}
		}

		$contact_id = cmx_buchungen_frontend_find_or_create_contact($name, $email, $phone);
		if (\is_wp_error($contact_id)) {
			\wp_safe_redirect(cmx_buchungen_frontend_url(['cmx_booking_error' => 'contact']));
			exit;
		}

		$title = \trim($date . ' - ' . $time . ' - ' . $name . ' - ' . (string) \get_the_title($service_id));
		$booking_id = \wp_insert_post([
			'post_type' => CMX_BUCHUNGEN_CPT,
			'post_status' => 'publish',
			'post_title' => $title,
			'post_content' => $note,
			'meta_input' => [
				CMX_BUCHUNGEN_META_KONTAKT => (string) (int) $contact_id,
				CMX_BUCHUNGEN_META_ARTIKEL => (string) $service_id,
				CMX_BUCHUNGEN_META_START_DATE => $date,
				CMX_BUCHUNGEN_META_START_TIME => $time,
				CMX_BUCHUNGEN_META_DURATION => (string) $duration,
				'_cmx_buchung_unit' => $unit,
				CMX_BUCHUNGEN_META_STATUS => 'bestaetigt',
				CMX_BUCHUNGEN_META_BUFFER_BEFORE => '0',
				CMX_BUCHUNGEN_META_BUFFER_AFTER => '0',
				CMX_BUCHUNGEN_META_BOOKING_TOKEN => cmx_buchungen_token(),
				CMX_BUCHUNGEN_META_CANCEL_TOKEN => cmx_buchungen_token(),
			],
		], true);
		if (\is_wp_error($booking_id) || (int) $booking_id <= 0) {
			if (\is_wp_error($booking_id)) {
				\error_log('[CMX Buchungen] Buchung konnte nicht angelegt werden: ' . $booking_id->get_error_message());
			}
			\wp_safe_redirect(cmx_buchungen_frontend_url(['cmx_booking_error' => 'create']));
			exit;
		}

		$booking_id = (int) $booking_id;
		$duration_term_id = cmx_buchungen_frontend_duration_term_id($duration);
		if ($duration_term_id > 0) {
			\wp_set_object_terms($booking_id, [$duration_term_id], CMX_BUCHUNGEN_TAX_DAUER, false);
		}
		cmx_buchungen_sync_title($booking_id);
		cmx_buchungen_schedule_reminder($booking_id);
		cmx_buchungen_maybe_send_confirmation($booking_id);

		\wp_safe_redirect(cmx_buchungen_frontend_url(['cmx_booking_confirmed' => $booking_id]));
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_json')) {
	function cmx_buchungen_frontend_json(array $data): string {
		return (string) \wp_json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_ics_text')) {
	function cmx_buchungen_frontend_ics_text(string $value): string {
		$value = \str_replace(["\\", ";", ",", "\r\n", "\r", "\n"], ["\\\\", "\;", "\,", "\\n", "\\n", "\\n"], $value);
		return $value;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_calendar_dates')) {
	function cmx_buchungen_frontend_calendar_dates(string $date, string $time, int $duration, string $unit = ''): array {
		$duration = \max(1, $duration);
		$is_day_booking = $unit === 'days' || ($unit === '' && $duration >= 1440 && $duration % 1440 === 0);
		$timezone = \wp_timezone();

		if ($is_day_booking) {
			$start = new \DateTimeImmutable($date . ' 00:00:00', $timezone);
			$end = $start->modify('+' . (int) ($duration / 1440) . ' days');
			return [
				'all_day' => true,
				'ics_start' => $start->format('Ymd'),
				'ics_end' => $end->format('Ymd'),
				'google_start' => $start->format('Ymd'),
				'google_end' => $end->format('Ymd'),
			];
		}

		$start = new \DateTimeImmutable($date . ' ' . $time, $timezone);
		$end = $start->modify('+' . \max(5, $duration) . ' minutes');
		return [
			'all_day' => false,
			'ics_start' => $start->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z'),
			'ics_end' => $end->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z'),
			'google_start' => $start->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z'),
			'google_end' => $end->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z'),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_google_calendar_url')) {
	function cmx_buchungen_frontend_google_calendar_url(int $booking_id, string $title, string $date, string $time, int $duration, string $unit = ''): string {
		$dates = cmx_buchungen_frontend_calendar_dates($date, $time, $duration, $unit);
		return \add_query_arg([
			'action' => 'TEMPLATE',
			'text' => $title,
			'dates' => (string) $dates['google_start'] . '/' . (string) $dates['google_end'],
			'details' => 'Buchung Nr. ' . $booking_id,
		], 'https://calendar.google.com/calendar/render');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_ics_content')) {
	function cmx_buchungen_frontend_ics_content(int $booking_id): string {
		$date = cmx_buchungen_sanitize_date((string) \get_post_meta($booking_id, CMX_BUCHUNGEN_META_START_DATE, true));
		$time = cmx_buchungen_sanitize_time((string) \get_post_meta($booking_id, CMX_BUCHUNGEN_META_START_TIME, true));
		$duration = \max(1, (int) \get_post_meta($booking_id, CMX_BUCHUNGEN_META_DURATION, true));
		$unit = \sanitize_key((string) \get_post_meta($booking_id, '_cmx_buchung_unit', true));
		$article_id = (int) \get_post_meta($booking_id, CMX_BUCHUNGEN_META_ARTIKEL, true);
		$title = \trim((string) \get_the_title($article_id));
		if ($title === '') {
			$title = \trim((string) \get_the_title($booking_id));
		}
		if ($title === '' || $date === '' || $time === '') {
			return '';
		}

		$dates = cmx_buchungen_frontend_calendar_dates($date, $time, $duration, $unit);
		$host = \parse_url((string) \home_url('/'), \PHP_URL_HOST);
		$uid = 'cmx-buchung-' . $booking_id . '@' . ($host ?: 'misbuero.local');
		$lines = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Mis Buero//Online Buchung//DE',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'BEGIN:VEVENT',
			'UID:' . $uid,
			'DTSTAMP:' . \gmdate('Ymd\THis\Z'),
			'SUMMARY:' . cmx_buchungen_frontend_ics_text($title),
			'DESCRIPTION:' . cmx_buchungen_frontend_ics_text('Buchung Nr. ' . $booking_id),
		];

		if (!empty($dates['all_day'])) {
			$lines[] = 'DTSTART;VALUE=DATE:' . (string) $dates['ics_start'];
			$lines[] = 'DTEND;VALUE=DATE:' . (string) $dates['ics_end'];
		} else {
			$lines[] = 'DTSTART:' . (string) $dates['ics_start'];
			$lines[] = 'DTEND:' . (string) $dates['ics_end'];
		}

		$lines[] = 'END:VEVENT';
		$lines[] = 'END:VCALENDAR';
		return \implode("\r\n", $lines) . "\r\n";
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_handle_ics')) {
	function cmx_buchungen_frontend_handle_ics(): void {
		if (!cmx_is_buchungen_frontend_request() || !isset($_GET['cmx_booking_ics'])) {
			return;
		}

		$booking_id = \max(0, (int) \wp_unslash($_GET['cmx_booking_ics']));
		$token = isset($_GET['token']) ? \sanitize_text_field((string) \wp_unslash($_GET['token'])) : '';
		$stored_token = $booking_id > 0 ? \trim((string) \get_post_meta($booking_id, CMX_BUCHUNGEN_META_BOOKING_TOKEN, true)) : '';
		if ($booking_id <= 0 || \get_post_type($booking_id) !== CMX_BUCHUNGEN_CPT || $token === '' || !\hash_equals($stored_token, $token)) {
			\status_header(404);
			exit;
		}

		$content = cmx_buchungen_frontend_ics_content($booking_id);
		if ($content === '') {
			\status_header(404);
			exit;
		}

		\nocache_headers();
		\header('Content-Type: text/calendar; charset=utf-8');
		\header('Content-Disposition: attachment; filename="buchung-' . $booking_id . '.ics"');
		echo $content;
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_frontend_render_page')) {
	function cmx_buchungen_frontend_render_page(): void {
		if (!cmx_is_buchungen_frontend_request()) {
			return;
		}

		$services = cmx_buchungen_frontend_services();
		$slots = [];
		$day_dates = [];
		foreach ($services as $service) {
			if ((string) ($service['unit'] ?? 'minutes') === 'days') {
				$service_day_dates = [];
				for ($days = 1; $days <= 60; $days++) {
					$service_day_dates[(string) $days] = cmx_buchungen_frontend_day_dates($days, 90, (array) ($service['weekdays'] ?? [1, 2, 3, 4, 5]));
				}
				$day_dates[(string) $service['id']] = $service_day_dates;
				continue;
			}
			$duration_key = (string) (int) ($service['duration_minutes'] ?? $service['duration']);
			if (!isset($slots[$duration_key])) {
				$slots[$duration_key] = cmx_buchungen_frontend_slots((int) ($service['duration_minutes'] ?? $service['duration']));
			}
		}
		$confirmed_id = isset($_GET['cmx_booking_confirmed']) ? \max(0, (int) \wp_unslash($_GET['cmx_booking_confirmed'])) : 0;
		$confirmed_service_id = $confirmed_id > 0 ? (int) \get_post_meta($confirmed_id, CMX_BUCHUNGEN_META_ARTIKEL, true) : 0;
		$booking_duration = $confirmed_id > 0 ? (int) \get_post_meta($confirmed_id, CMX_BUCHUNGEN_META_DURATION, true) : 0;
		$confirmed_service = null;
		foreach ($services as $service) {
			$service_compare_duration = (int) ($service['duration_minutes'] ?? cmx_buchungen_frontend_duration_minutes((string) ($service['unit'] ?? 'minutes'), (int) ($service['duration'] ?? 0)));
			if ((int) ($service['artikel_id'] ?? 0) === $confirmed_service_id && $service_compare_duration === $booking_duration) {
				$confirmed_service = $service;
				break;
			}
			if ($confirmed_service === null && (int) ($service['artikel_id'] ?? 0) === $confirmed_service_id) {
				$confirmed_service = $service;
			}
		}
		$booking_date = $confirmed_id > 0 ? (string) \get_post_meta($confirmed_id, CMX_BUCHUNGEN_META_START_DATE, true) : '';
		$booking_time = $confirmed_id > 0 ? (string) \get_post_meta($confirmed_id, CMX_BUCHUNGEN_META_START_TIME, true) : '';
		$me_logo_url = \function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_url')
			? (string) cmx_email_self_logo_url()
			: '';
		$me_contact_url = \function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_url')
			? (string) cmx_email_self_contact_url()
			: '';
		$me_contact_title = \function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_branding_text')
			? (string) cmx_email_self_contact_branding_text()
			: '';
		$headline = cmx_buchungen_frontend_setting_text('buchungen_headline', cmx_buchungen_frontend_default_headline());
		$subline = cmx_buchungen_frontend_setting_text('buchungen_subline', cmx_buchungen_frontend_default_subline());
		$show_powered_by = cmx_buchungen_frontend_powered_by_enabled();

		\status_header(200);
		\nocache_headers();
		echo '<!doctype html><html ' . \get_language_attributes() . '><head><meta charset="' . \esc_attr(\get_bloginfo('charset')) . '"><meta name="viewport" content="width=device-width,initial-scale=1">';
		echo '<title>Buchungen</title>';
		echo '<style>
			:root{--cmx-blue:#2f6cf6;--cmx-border:#d1d5db;--cmx-text:#111827;--cmx-muted:#6b7280;--cmx-soft:#eff6ff}
			*{box-sizing:border-box}
			body{margin:0;background:#f8fafc;color:var(--cmx-text);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
			.cmx-booking-page{max-width:1180px;margin:0 auto;padding:28px 18px 42px}
			.cmx-booking-shell{min-height:610px;border:1px solid #b8bec8;border-radius:8px;background:#fff;box-shadow:0 12px 32px rgba(15,23,42,.05);overflow:hidden}
			.cmx-booking-head{padding:24px 34px 18px;background:linear-gradient(135deg,#f7f7f7 0%,#ededed 100%);border-bottom:1px solid #e2e2e2}
			.cmx-booking-head-inner{display:flex;align-items:flex-start;justify-content:space-between;gap:24px}
			.cmx-booking-head-copy{flex:1 1 auto;min-width:0}
			.cmx-booking-head-brand{flex:0 0 auto;display:flex;align-items:flex-start;justify-content:flex-end;min-height:84px}
			.cmx-booking-head-logo{display:block;max-width:190px;max-height:84px;width:auto;height:auto;object-fit:contain;object-position:right top}
			.cmx-booking-content{padding:34px}
			.cmx-booking-title{margin:0;font-size:30px;line-height:1.1;font-weight:800;letter-spacing:0}
			.cmx-booking-title a{display:inline-flex;align-items:center;gap:10px;color:inherit;text-decoration:none}
			.cmx-booking-title-back{display:none;position:relative;top:-2px;color:#2563eb;font-size:30px;line-height:1;font-weight:800}
			.cmx-booking-shell.is-substep .cmx-booking-title-back{display:inline-block}
			.cmx-booking-sub{margin:7px 0 0;color:var(--cmx-muted)}
			.cmx-booking-error{margin:0 0 18px;padding:11px 14px;border:1px solid #fecaca;border-radius:8px;background:#fef2f2;color:#991b1b}
			.cmx-booking-service-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px 34px;max-width:940px;margin:0 auto}
			.cmx-booking-card{position:relative;min-height:178px;border:1px solid #d5d9e0;border-radius:22px;background:#fff;box-shadow:0 10px 24px rgba(15,23,42,.04);overflow:hidden;padding:38px 24px 18px}
			.cmx-booking-card:before{content:"";position:absolute;left:0;top:0;right:0;height:26px;background:#dcecff}
			.cmx-booking-card-header-label{position:absolute;left:24px;right:64px;top:4px;color:#334155;font-size:13px;font-weight:700;line-height:18px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
			.cmx-booking-card-menu{position:absolute;right:22px;top:26px;color:#111827;font-weight:800;letter-spacing:2px}
			.cmx-booking-person{display:flex;align-items:center;gap:16px;margin:0 0 18px}
			.cmx-booking-avatar{width:58px;height:58px;border-radius:999px;object-fit:cover;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-weight:800;color:#475569;flex:0 0 auto}
			.cmx-booking-person-name{font-size:16px}
			.cmx-booking-service-name{display:flex;align-items:center;gap:12px;font-size:21px;font-weight:800;margin:0 0 16px;line-height:1.2}
			.cmx-booking-dot{width:14px;height:14px;border-radius:999px;display:inline-block;flex:0 0 auto}
			.cmx-booking-card-line{height:1px;background:#c7ced8;margin:0 0 12px}
			.cmx-booking-card-foot{display:flex;align-items:center;justify-content:space-between;gap:12px}
			.cmx-booking-duration{color:#4b5563;font-size:14px}
			.cmx-booking-card-days{display:flex;align-items:center;gap:8px;color:#4b5563;font-size:14px}
			.cmx-booking-card-days input{width:76px;border:1px solid #cfd5df;border-radius:6px;padding:8px 18px 8px 10px;font:inherit;font-weight:700;text-align:center;background:#fff}
			.cmx-booking-card-days input::-webkit-inner-spin-button,.cmx-booking-day-count input::-webkit-inner-spin-button{margin-left:8px}
			.cmx-booking-button{border:0;border-radius:6px;background:#3b82f6;color:#fff;font:inherit;font-weight:700;padding:11px 24px;cursor:pointer;box-shadow:0 1px 0 rgba(0,0,0,.08)}
			.cmx-booking-button:hover{background:#2563eb}
			.cmx-booking-button[disabled]{opacity:.55;cursor:not-allowed}
			.cmx-booking-scheduler{display:grid;grid-template-columns:230px minmax(0,1fr) 270px;gap:32px;align-items:start;min-height:450px}
			.cmx-booking-side{padding-top:26px}
			.cmx-booking-side h2{font-size:24px;line-height:1.15;margin:20px 0 10px;font-weight:800}
			.cmx-booking-side-row{display:flex;align-items:center;gap:10px;color:#111827;margin:8px 0}
			.cmx-booking-icon{width:16px;height:16px;display:inline-flex;align-items:center;justify-content:center;color:#111}
			.cmx-booking-calendar-head{display:flex;align-items:center;justify-content:center;gap:36px;margin:6px 0 20px}
			.cmx-booking-month{border:0;background:transparent;padding:0;font:inherit;font-size:38px;color:#c4c4c4;font-weight:800;min-width:260px;text-align:center;cursor:pointer}
			.cmx-booking-month:hover{color:#9ca3af}
			.cmx-booking-nav{width:34px;height:34px;border:0;background:transparent;color:#aab0b8;font-size:28px;cursor:pointer;line-height:1}
			.cmx-booking-nav.is-next{color:#2563eb}
			.cmx-booking-weekdays,.cmx-booking-days{display:grid;grid-template-columns:repeat(7,44px);gap:13px;justify-content:center}
			.cmx-booking-weekdays{margin-bottom:16px;color:#6b7280;font-size:12px}
			.cmx-booking-day{width:36px;height:36px;border:0;border-radius:5px;background:transparent;color:#a4acb5;font-size:17px;font-weight:800;cursor:pointer}
			.cmx-booking-day.has-slots{background:#eef0f3;color:#9aa3ad}
			.cmx-booking-day.is-today{box-shadow:inset 0 0 0 1px #2563eb}
			.cmx-booking-day.is-selected{background:#2563eb;color:#fff}
			.cmx-booking-day:disabled{opacity:.32;cursor:not-allowed}
			.cmx-booking-slots{display:flex;flex-direction:column;gap:12px;padding-top:66px;max-height:500px;overflow:auto}
			.cmx-booking-slot{display:flex;align-items:center;justify-content:space-between;gap:10px;min-height:50px;border:1px solid #d8dde6;border-radius:4px;background:#fff;padding:0 16px;font-weight:700;cursor:pointer}
			.cmx-booking-slot small{font-weight:400;color:#6b7280}
			.cmx-booking-slot.is-selected{border-color:#2563eb;background:#eff6ff}
			.cmx-booking-next{border:0;border-radius:4px;background:#2563eb;color:#fff;padding:8px 18px;font-weight:700;cursor:pointer}
			.cmx-booking-day-count{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:50px;border:1px solid #d8dde6;border-radius:4px;background:#fff;padding:10px 16px}
			.cmx-booking-day-count label{display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;font-weight:700}
			.cmx-booking-day-count input{width:76px;border:1px solid #cfd5df;border-radius:6px;padding:8px 18px 8px 10px;font:inherit;font-weight:700;text-align:center}
			.cmx-booking-form{max-width:780px;margin:20px auto 0;border:1px solid #d6dbe3;border-radius:18px;padding:28px;background:#fff}
			.cmx-booking-form h2{margin:0 0 18px;font-size:26px}
			.cmx-booking-form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
			.cmx-booking-form label{display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:700;color:#374151}
			.cmx-booking-form input,.cmx-booking-form textarea{width:100%;border:1px solid #cfd5df;border-radius:8px;padding:11px 12px;font:inherit;background:#fff}
			.cmx-booking-form input[readonly]{background:#fff;color:#111827;font-weight:700}
			.cmx-booking-form textarea{min-height:90px;resize:vertical}
			.cmx-booking-form .is-wide{grid-column:1/-1}
			.cmx-booking-form-note{grid-column:1/-1;margin:-4px 0 0;color:#6b7280;font-size:13px;line-height:1.45}
			.cmx-booking-form-actions{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-top:20px}
			.cmx-booking-link{border:0;background:transparent;color:#2563eb;font:inherit;font-weight:700;cursor:pointer;padding:0}
			.cmx-booking-confirm{max-width:820px;margin:22px auto 0;border:1px solid #d6dbe3;border-radius:18px;background:#fff;padding:58px 70px;position:relative;min-height:320px}
			.cmx-booking-confirm h1{margin:0;text-align:center;font-size:30px;line-height:1.15}
			.cmx-booking-confirm-sub{text-align:center;margin:8px 0 34px;font-size:17px}
			.cmx-booking-confirm-line{height:1px;background:#dfe3e8;margin:0 0 24px}
			.cmx-booking-confirm-row{display:flex;align-items:center;gap:12px;margin:12px 0}
			.cmx-booking-confirm-cal{display:flex;flex-direction:column;gap:8px;margin-top:30px;font-size:14px;font-weight:700}
			.cmx-booking-confirm-cal a{color:#2563eb;text-decoration:none}
			.cmx-booking-confirm-cal a:hover{text-decoration:underline}
			.cmx-booking-check{position:absolute;right:-36px;bottom:-24px;width:75px;height:75px;border-radius:999px;background:#07bd58;color:#fff;display:flex;align-items:center;justify-content:center;font-size:43px;font-weight:800}
			.cmx-booking-powered{padding:0 24px 16px;text-align:right;color:#9ca3af;font-size:12px;line-height:1.4}
			.cmx-booking-shell.is-confirmed .cmx-booking-powered{padding-top:16px}
			.cmx-booking-powered a{color:#6b7280;text-decoration:none;font-weight:700}
			.cmx-booking-powered a:hover{text-decoration:underline}
			[hidden]{display:none !important}
			@media(max-width:900px){.cmx-booking-content{padding:20px}.cmx-booking-head{padding:20px}.cmx-booking-head-inner{flex-direction:column}.cmx-booking-head-brand{display:none}.cmx-booking-service-grid{grid-template-columns:1fr}.cmx-booking-scheduler{grid-template-columns:1fr;gap:18px}.cmx-booking-slots{padding-top:0}.cmx-booking-weekdays,.cmx-booking-days{grid-template-columns:repeat(7,minmax(0,1fr));gap:8px}.cmx-booking-day{width:100%}.cmx-booking-form-grid{grid-template-columns:1fr}.cmx-booking-confirm{padding:34px 22px}.cmx-booking-check{position:static;width:75px;height:75px;font-size:43px;margin:28px auto 0}}
		</style>';
		$shell_classes = 'cmx-booking-shell' . ($confirmed_id > 0 && $confirmed_service !== null ? ' is-confirmed' : '');
		echo '</head><body><main class="cmx-booking-page"><section class="' . \esc_attr($shell_classes) . '" data-cmx-booking-app data-services="' . \esc_attr(cmx_buchungen_frontend_json($services)) . '" data-slots="' . \esc_attr(cmx_buchungen_frontend_json($slots)) . '" data-day-dates="' . \esc_attr(cmx_buchungen_frontend_json($day_dates)) . '">';
		echo '<div class="cmx-booking-head"><div class="cmx-booking-head-inner">';
		echo '<div class="cmx-booking-head-copy"><h1 class="cmx-booking-title"><a href="' . \esc_url(cmx_buchungen_frontend_url()) . '"><span class="cmx-booking-title-back" aria-hidden="true">‹</span><span>' . \esc_html($headline) . '</span></a></h1><p class="cmx-booking-sub">' . \esc_html($subline) . '</p></div>';
		if ($me_logo_url !== '') {
			echo '<div class="cmx-booking-head-brand">';
			if ($me_contact_url !== '') {
				echo '<a href="' . \esc_url($me_contact_url) . '" target="_blank" rel="noopener noreferrer" title="' . \esc_attr($me_contact_title) . '">';
			}
			echo '<img class="cmx-booking-head-logo" src="' . \esc_url($me_logo_url) . '" alt="Das bin ich Logo">';
			if ($me_contact_url !== '') {
				echo '</a>';
			}
			echo '</div>';
		}
		echo '</div></div><div class="cmx-booking-content">';

		if ($confirmed_id > 0 && $confirmed_service !== null) {
			$confirmed_unit = (string) ($confirmed_service['unit'] ?? '');
			$is_day_booking = $confirmed_unit === 'days';
			$start_label = '';
			if ($booking_date !== '' && $booking_time !== '') {
				$start_label = $is_day_booking
					? \wp_date('d. F Y', \strtotime($booking_date)) . ' - ' . \wp_date('d. F Y', \strtotime($booking_date . ' +' . (($booking_duration / 1440) - 1) . ' days'))
					: \wp_date('H:i', \strtotime($booking_date . ' ' . $booking_time)) . ' - ' . \wp_date('H:i', \strtotime($booking_date . ' ' . $booking_time . ' +' . \max(5, $booking_duration) . ' minutes')) . ', ' . \wp_date('l, d. F Y', \strtotime($booking_date));
			}
			$booking_token = \trim((string) \get_post_meta($confirmed_id, CMX_BUCHUNGEN_META_BOOKING_TOKEN, true));
			$ics_url = $booking_token !== ''
				? cmx_buchungen_frontend_url(['cmx_booking_ics' => $confirmed_id, 'token' => $booking_token])
				: '';
			$google_url = ($booking_date !== '' && $booking_time !== '')
				? cmx_buchungen_frontend_google_calendar_url($confirmed_id, (string) $confirmed_service['title'], $booking_date, $booking_time, $booking_duration, $confirmed_unit)
				: '';
			echo '<div class="cmx-booking-confirm">';
			echo '<h1>Buchung erfolgt</h1>';
			echo '<p class="cmx-booking-confirm-sub">Dein Termin bei ' . \esc_html((string) $confirmed_service['person']) . ' ist vorgemerkt.</p>';
			echo '<div class="cmx-booking-confirm-line"></div>';
			echo '<div class="cmx-booking-confirm-row"><strong>' . \esc_html((string) $confirmed_service['title']) . '</strong></div>';
			echo '<div class="cmx-booking-confirm-row"><span class="cmx-booking-icon">◴</span><span>' . \esc_html($start_label) . '</span></div>';
			echo '<div class="cmx-booking-confirm-row"><span class="cmx-booking-icon">●</span><span>' . \esc_html(cmx_buchungen_frontend_duration_label($booking_duration, $confirmed_unit)) . '</span></div>';
			echo '<div class="cmx-booking-confirm-cal">';
			if ($ics_url !== '') {
				echo '<a href="' . \esc_url($ics_url) . '">ICS-Datei herunterladen</a>';
			}
			if ($google_url !== '') {
				echo '<a href="' . \esc_url($google_url) . '" target="_blank" rel="noopener noreferrer">In Google Kalender eintragen</a>';
			}
			echo '</div>';
			echo '<div class="cmx-booking-check">✓</div>';
			echo '</div>';
		} else {
			$error = isset($_GET['cmx_booking_error']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_booking_error'])) : '';
			if ($error !== '') {
				echo '<p class="cmx-booking-error">' . \esc_html(cmx_buchungen_frontend_error_message($error)) . '</p>';
			}
			echo '<div data-step="services">';
			if ($services === []) {
				echo '<p>Aktuell sind keine buchbaren Leistungen vorhanden.</p>';
			} else {
				echo '<div class="cmx-booking-service-grid">';
				foreach ($services as $service) {
					$avatar = (string) $service['image'] !== ''
						? '<img class="cmx-booking-avatar" src="' . \esc_url((string) $service['image']) . '" alt="' . \esc_attr((string) $service['title']) . '">'
						: '<span class="cmx-booking-avatar">' . \esc_html(\mb_substr((string) ($service['avatar_label'] ?? $service['title']), 0, 1)) . '</span>';
					echo '<article class="cmx-booking-card">';
					echo '<span class="cmx-booking-card-header-label">' . \esc_html((string) $service['person']) . '</span>';
					echo '<span class="cmx-booking-card-menu">...</span>';
					echo '<div class="cmx-booking-person">' . $avatar . '</div>';
					echo '<h2 class="cmx-booking-service-name">' . \esc_html((string) $service['title']) . '</h2>';
					echo '<div class="cmx-booking-card-line"></div>';
					echo '<div class="cmx-booking-card-foot">';
					if ((string) ($service['unit'] ?? 'minutes') === 'days') {
						$service_days = \max(1, (int) $service['duration']);
						echo '<label class="cmx-booking-card-days"><input type="number" min="1" max="60" step="1" value="' . \esc_attr((string) $service_days) . '" data-service-days><span data-service-days-label>' . \esc_html($service_days === 1 ? 'Tag' : 'Tage') . '</span></label>';
					} else {
						echo '<span class="cmx-booking-duration">' . \esc_html(cmx_buchungen_frontend_duration_label((int) ($service['duration_minutes'] ?? $service['duration']), (string) ($service['unit'] ?? 'minutes'))) . '</span>';
					}
					echo '<button type="button" class="cmx-booking-button" data-service-id="' . \esc_attr((string) $service['id']) . '">auswählen</button></div>';
					echo '</article>';
				}
				echo '</div>';
			}
			echo '</div>';
			echo '<div data-step="schedule" hidden><div class="cmx-booking-scheduler"><aside class="cmx-booking-side"><div data-side-avatar></div><h2 data-side-title></h2><div class="cmx-booking-side-row"><span class="cmx-booking-icon">◴</span><span data-side-duration></span></div><div class="cmx-booking-side-row" data-side-period-row><span class="cmx-booking-icon">◷</span><span data-side-period></span></div></aside><section><div class="cmx-booking-calendar-head"><button type="button" class="cmx-booking-nav" data-prev-month>‹</button><button type="button" class="cmx-booking-month" data-month-label data-today-jump></button><button type="button" class="cmx-booking-nav is-next" data-next-month>›</button></div><div class="cmx-booking-weekdays"><span>MO</span><span>DI</span><span>MI</span><span>DO</span><span>FR</span><span>SA</span><span>SO</span></div><div class="cmx-booking-days" data-calendar-days></div></section><aside class="cmx-booking-slots" data-slots-list></aside></div></div>';
			echo '<form class="cmx-booking-form" method="post" action="' . \esc_url(cmx_buchungen_frontend_url()) . '" data-step="form" hidden><h2>Kontaktdaten</h2><input type="hidden" name="cmx_buchungen_frontend_action" value="book"><input type="hidden" name="template_id" data-form-template><input type="hidden" name="service_id" data-form-service><input type="hidden" name="date" data-form-date><input type="hidden" name="time" data-form-time><input type="hidden" name="booking_days" data-form-booking-days value="1">';
			\wp_nonce_field('cmx_buchungen_frontend_book', 'cmx_buchungen_frontend_nonce');
			echo '<div class="cmx-booking-form-grid"><label>Name<input name="name" autocomplete="name" required></label><label>E-Mail<input type="email" name="email" autocomplete="email" required></label><label>Telefon<input name="phone" autocomplete="tel"></label><label class="is-wide">Termin<input data-form-summary readonly></label><label class="is-wide">Notiz<textarea name="note"></textarea></label><p class="cmx-booking-form-note">Du erhältst sofort eine Eingangsbestätigung per E-Mail.<br/>Die Buchung wird erst nach zusätzlicher manueller Bestätigung verbindlich.</p></div><div class="cmx-booking-form-actions"><button type="button" class="cmx-booking-link" data-back-schedule>Zurück</button><button type="submit" class="cmx-booking-button">buchen</button></div></form>';
		}

		echo '</div>';
		if ($show_powered_by) {
			echo '<div class="cmx-booking-powered">Powered by <a href="https://misbuero.ch/" target="_blank" rel="noopener noreferrer">Mis Büro</a></div>';
		}
		echo '</section></main><script>
		(function(){
			var root = document.querySelector("[data-cmx-booking-app]");
			if(!root || root.dataset.ready === "1") return;
			root.dataset.ready = "1";
			var services = [];
			var slots = {};
			var dayDates = {};
			try{ services = JSON.parse(root.getAttribute("data-services") || "[]"); }catch(err){}
			try{ slots = JSON.parse(root.getAttribute("data-slots") || "{}"); }catch(err){}
			try{ dayDates = JSON.parse(root.getAttribute("data-day-dates") || "{}"); }catch(err){}
			var byId = {};
			services.forEach(function(service){ byId[String(service.id)] = service; });
			var selectedService = null;
			var selectedDate = "";
			var selectedTime = "";
			var selectedDayCount = 1;
			var viewDate = new Date();
			viewDate.setDate(1);
			var todayKey = ymd(new Date());
			var monthNames = ["Januar","Februar","März","April","Mai","Juni","Juli","August","September","Oktober","November","Dezember"];
			function qs(sel){ return root.querySelector(sel); }
			function showStep(name){
				root.querySelectorAll("[data-step]").forEach(function(el){ el.hidden = el.getAttribute("data-step") !== name; });
				root.classList.toggle("is-substep", name === "schedule" || name === "form");
			}
			function ymd(date){
				var y = date.getFullYear();
				var m = String(date.getMonth() + 1).padStart(2, "0");
				var d = String(date.getDate()).padStart(2, "0");
				return y + "-" + m + "-" + d;
			}
			function fmtTime(time){
				var parts = String(time || "").split(":");
				var h = parseInt(parts[0] || "0", 10);
				var m = parts[1] || "00";
				return String(h).padStart(2, "0") + ":" + m;
			}
			function isDayService(){ return selectedService && String(selectedService.unit || "minutes") === "days"; }
			function serviceSlots(){ return selectedService ? (slots[String(parseInt(selectedService.duration_minutes || selectedService.duration || 60, 10))] || {}) : {}; }
			function serviceDayDates(){
				var map = {};
				var source = selectedService ? dayDates[String(selectedService.id)] : null;
				if(source && !Array.isArray(source)){
					source = source[String(selectedDayCount)] || [];
				}
				(source || []).forEach(function(dateKey){ map[String(dateKey)] = true; });
				return map;
			}
			function durationLabel(){
				if(!selectedService) return "";
				var duration = isDayService() ? selectedDayCount : parseInt(selectedService.duration || 60, 10);
				if(isDayService()) return String(duration) + (duration === 1 ? " Tag" : " Tage");
				if(String(selectedService.unit || "minutes") === "hours") return String(duration) + (duration === 1 ? " Stunde" : " Stunden");
				return String(duration) + " Minuten";
			}
			function slotAllowed(time){
				if(isDayService()) return true;
				var period = selectedService ? String(selectedService.period || "all") : "all";
				if(period === "all") return true;
				var hour = parseInt(String(time || "").split(":")[0] || "-1", 10);
				if(hour < 0) return false;
				return period === "morning" ? hour < 12 : hour >= 12;
			}
			function weekdayAllowed(dateKey){
				if(!selectedService) return false;
				var allowed = Array.isArray(selectedService.weekdays) ? selectedService.weekdays.map(function(day){ return parseInt(day, 10); }) : [1,2,3,4,5];
				if(!allowed.length) allowed = [1,2,3,4,5];
				var date = new Date(String(dateKey) + "T00:00:00");
				var weekday = date.getDay();
				weekday = weekday === 0 ? 7 : weekday;
				return allowed.indexOf(weekday) !== -1;
			}
			function filteredSlotsForDate(dateKey){
				if(!weekdayAllowed(dateKey)) return [];
				return (serviceSlots()[dateKey] || []).filter(slotAllowed);
			}
			function firstSlotDate(){
				if(isDayService()){
					var dayKeys = Object.keys(serviceDayDates()).sort();
					return dayKeys.length ? dayKeys[0] : ymd(new Date());
				}
				var keys = Object.keys(serviceSlots()).sort();
				for(var i = 0; i < keys.length; i++){
					if(filteredSlotsForDate(keys[i]).length) return keys[i];
				}
				return ymd(new Date());
			}
			function updateSide(){
				if(!selectedService) return;
				var avatar = qs("[data-side-avatar]");
				avatar.innerHTML = selectedService.image ? "<img class=\"cmx-booking-avatar\" src=\"" + selectedService.image + "\" alt=\"\">" : "<span class=\"cmx-booking-avatar\">" + String(selectedService.avatar_label || selectedService.title || "?").charAt(0) + "</span>";
				qs("[data-side-title]").textContent = selectedService.title || "";
				qs("[data-side-duration]").textContent = durationLabel();
				var periodRow = qs("[data-side-period-row]");
				if(periodRow) periodRow.hidden = !isDayService();
				qs("[data-side-period]").textContent = isDayService() ? "Tagesbuchung" : "";
			}
			function renderCalendar(){
				var label = qs("[data-month-label]");
				var grid = qs("[data-calendar-days]");
				if(!label || !grid) return;
				label.textContent = monthNames[viewDate.getMonth()] + " " + viewDate.getFullYear();
				grid.innerHTML = "";
				var first = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
				var last = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0);
				var map = isDayService() ? serviceDayDates() : serviceSlots();
				var firstOffset = (first.getDay() + 6) % 7;
				for(var blank = 0; blank < firstOffset; blank++){
					var spacer = document.createElement("span");
					grid.appendChild(spacer);
				}
				for(var day = 1; day <= last.getDate(); day++){
					var date = new Date(viewDate.getFullYear(), viewDate.getMonth(), day);
					var key = ymd(date);
					var btn = document.createElement("button");
					btn.type = "button";
					var hasSlots = isDayService() ? !!map[key] : filteredSlotsForDate(key).length > 0;
					btn.className = "cmx-booking-day" + (hasSlots ? " has-slots" : "") + (key === todayKey ? " is-today" : "") + (key === selectedDate ? " is-selected" : "");
					btn.textContent = String(day);
					btn.disabled = !hasSlots;
					btn.addEventListener("click", function(dateKey){
						return function(){
							selectedDate = dateKey;
							selectedTime = "";
							renderCalendar();
							renderSlots();
						};
					}(key));
					grid.appendChild(btn);
				}
			}
			function renderSlots(){
				var list = qs("[data-slots-list]");
				if(!list) return;
				list.innerHTML = "";
				if(isDayService()){
					var countWrap = document.createElement("div");
					countWrap.className = "cmx-booking-day-count";
					countWrap.innerHTML = "<label><span>Anzahl " + (selectedDayCount === 1 ? "Tag" : "Tage") + "</span><input type=\"number\" min=\"1\" max=\"60\" step=\"1\" value=\"" + String(selectedDayCount) + "\"></label>";
					var countInput = countWrap.querySelector("input");
					function selectCountInput(){ window.setTimeout(function(){ countInput.select(); }, 0); }
					countInput.addEventListener("focus", selectCountInput);
					countInput.addEventListener("click", selectCountInput);
					countInput.addEventListener("change", function(){
						var nextCount = parseInt(countInput.value || "1", 10);
						if(!Number.isFinite(nextCount)) nextCount = 1;
						selectedDayCount = Math.max(1, Math.min(60, nextCount));
						countInput.value = String(selectedDayCount);
						selectedTime = "";
						updateSide();
						renderCalendar();
						renderSlots();
					});
					list.appendChild(countWrap);
					if(!serviceDayDates()[selectedDate]){
						var unavailable = document.createElement("div");
						unavailable.className = "cmx-booking-slot";
						unavailable.textContent = "Nicht verfügbar";
						list.appendChild(unavailable);
						return;
					}
					var dayBtn = document.createElement("button");
					dayBtn.type = "button";
					dayBtn.className = "cmx-booking-slot is-selected";
					dayBtn.innerHTML = "<span>" + durationLabel() + "</span>";
					var dayNext = document.createElement("button");
					dayNext.type = "button";
					dayNext.className = "cmx-booking-next";
					dayNext.textContent = "Weiter";
					dayNext.addEventListener("click", openForm);
					dayBtn.appendChild(dayNext);
					list.appendChild(dayBtn);
					return;
				}
				var daySlots = filteredSlotsForDate(selectedDate);
				if(!daySlots.length){
					var empty = document.createElement("div");
					empty.className = "cmx-booking-slot";
					empty.textContent = "Keine freien Termine";
					list.appendChild(empty);
					return;
				}
				daySlots.forEach(function(time){
					var btn = document.createElement("button");
					btn.type = "button";
					btn.className = "cmx-booking-slot" + (selectedTime === time ? " is-selected" : "");
					btn.innerHTML = "<span>" + fmtTime(time) + "</span>";
					if(selectedTime === time){
						var next = document.createElement("button");
						next.type = "button";
						next.className = "cmx-booking-next";
						next.textContent = "Weiter";
						next.addEventListener("click", openForm);
						btn.appendChild(next);
					}else{
						var spots = document.createElement("small");
						spots.textContent = "1 Platz frei";
						btn.appendChild(spots);
					}
					btn.addEventListener("click", function(){
						if(selectedTime !== time){
							selectedTime = time;
							renderSlots();
						}
					});
					list.appendChild(btn);
				});
			}
			function openSchedule(serviceId, dayCount){
				selectedService = byId[String(serviceId)] || null;
				if(!selectedService) return;
				selectedDayCount = isDayService() ? Math.max(1, Math.min(60, parseInt(dayCount || selectedService.duration || 1, 10))) : 1;
				selectedDate = firstSlotDate();
				selectedTime = "";
				var first = selectedDate ? new Date(selectedDate + "T00:00:00") : new Date();
				viewDate = new Date(first.getFullYear(), first.getMonth(), 1);
				updateSide();
				renderCalendar();
				renderSlots();
				showStep("schedule");
			}
			function openForm(){
				if(!selectedService || !selectedDate || (!selectedTime && !isDayService())) return;
				qs("[data-form-template]").value = String(selectedService.id);
				qs("[data-form-service]").value = String(selectedService.artikel_id || "");
				qs("[data-form-date]").value = selectedDate;
				qs("[data-form-time]").value = isDayService() ? "00:00" : selectedTime;
				qs("[data-form-booking-days]").value = isDayService() ? String(selectedDayCount) : "1";
				var summaryDate = selectedDate;
				if(isDayService() && selectedDayCount > 1){
					var endDate = new Date(selectedDate + "T00:00:00");
					endDate.setDate(endDate.getDate() + selectedDayCount - 1);
					summaryDate = selectedDate + " - " + ymd(endDate);
				}
				qs("[data-form-summary]").value = (isDayService() ? summaryDate + ", " + durationLabel() : fmtTime(selectedTime) + ", " + selectedDate) + " - " + selectedService.title;
				showStep("form");
				var name = root.querySelector("input[name=\"name\"]");
				if(name) name.focus();
			}
			root.querySelectorAll("[data-service-id]").forEach(function(btn){
				btn.addEventListener("click", function(){
					var card = btn.closest ? btn.closest(".cmx-booking-card") : null;
					var daysInput = card ? card.querySelector("[data-service-days]") : null;
					openSchedule(btn.getAttribute("data-service-id"), daysInput ? daysInput.value : "");
				});
			});
			root.querySelectorAll("[data-service-days]").forEach(function(input){
				function selectInput(){ window.setTimeout(function(){ input.select(); }, 0); }
				input.addEventListener("focus", selectInput);
				input.addEventListener("click", selectInput);
				input.addEventListener("change", function(){
					var value = Math.max(1, Math.min(60, parseInt(input.value || "1", 10) || 1));
					input.value = String(value);
					var label = input.parentElement ? input.parentElement.querySelector("[data-service-days-label]") : null;
					if(label) label.textContent = value === 1 ? "Tag" : "Tage";
				});
			});
			var prev = qs("[data-prev-month]");
			var next = qs("[data-next-month]");
			if(prev) prev.addEventListener("click", function(){ viewDate.setMonth(viewDate.getMonth() - 1); renderCalendar(); renderSlots(); });
			if(next) next.addEventListener("click", function(){ viewDate.setMonth(viewDate.getMonth() + 1); renderCalendar(); renderSlots(); });
			var todayJump = qs("[data-today-jump]");
			if(todayJump) todayJump.addEventListener("click", function(){
				var today = new Date();
				viewDate = new Date(today.getFullYear(), today.getMonth(), 1);
				if((isDayService() && serviceDayDates()[todayKey]) || (!isDayService() && filteredSlotsForDate(todayKey).length)){
					selectedDate = todayKey;
					selectedTime = "";
				}
				renderCalendar();
				renderSlots();
			});
			var back = qs("[data-back-schedule]");
			if(back) back.addEventListener("click", function(){ showStep("schedule"); });
		})();
		</script></body></html>';
		exit;
	}
}

\add_action('template_redirect', __NAMESPACE__ . '\\cmx_buchungen_frontend_handle_submit', 1);
\add_action('template_redirect', __NAMESPACE__ . '\\cmx_buchungen_frontend_handle_ics', 2);
\add_action('template_redirect', __NAMESPACE__ . '\\cmx_buchungen_frontend_render_page', 5);
