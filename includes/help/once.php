<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_FIRMENGRUENDUNG')) {
	\define(__NAMESPACE__ . '\\CMX_KONTAKTE_META_FIRMENGRUENDUNG', '_cmx_kontakte_firmengruendung');
}

if (!\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_GEBURTSDATUM')) {
	\define(__NAMESPACE__ . '\\CMX_KONTAKTE_META_GEBURTSDATUM', '_cmx_kontakte_geburtsdatum');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_help_once_random_date_between')) {
	function cmx_help_once_random_date_between(int $min_ts, int $max_ts): string {
		if ($max_ts < $min_ts) {
			$max_ts = $min_ts;
		}

		$random_ts = \wp_rand($min_ts, $max_ts);
		return \gmdate('Y-m-d', $random_ts);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_help_once_generate_kontakt_dates')) {
	function cmx_help_once_generate_kontakt_dates(): array {
		$today_ts = \current_time('timestamp', true);
		$latest_birth_ts = \strtotime('-24 years', $today_ts);
		$earliest_birth_ts = \strtotime('-85 years', $today_ts);

		if ($earliest_birth_ts === false || $latest_birth_ts === false) {
			$today_ts = \time();
			$latest_birth_ts = \strtotime('-24 years', $today_ts);
			$earliest_birth_ts = \strtotime('-85 years', $today_ts);
		}

		$birthdate = cmx_help_once_random_date_between((int) $earliest_birth_ts, (int) $latest_birth_ts);
		$birth_ts = \strtotime($birthdate . ' 12:00:00 UTC');
		if ($birth_ts === false) {
			$birth_ts = (int) $earliest_birth_ts;
		}

		$min_founding_ts = \strtotime('+18 years', $birth_ts);
		$max_founding_ts = \strtotime('+45 years', $birth_ts);
		$latest_allowed_founding_ts = \strtotime('-90 days', $today_ts);
		$absolute_min_founding_ts = \strtotime('1960-01-01 12:00:00 UTC');

		if ($min_founding_ts === false) {
			$min_founding_ts = $birth_ts;
		}
		if ($max_founding_ts === false) {
			$max_founding_ts = $today_ts;
		}
		if ($latest_allowed_founding_ts === false) {
			$latest_allowed_founding_ts = $today_ts;
		}
		if ($absolute_min_founding_ts === false) {
			$absolute_min_founding_ts = $min_founding_ts;
		}

		$min_founding_ts = \max((int) $min_founding_ts, (int) $absolute_min_founding_ts);
		$max_founding_ts = \min((int) $max_founding_ts, (int) $latest_allowed_founding_ts);
		if ($max_founding_ts < $min_founding_ts) {
			$max_founding_ts = $min_founding_ts;
		}

		return [
			'geburtsdatum'    => $birthdate,
			'firmengruendung' => cmx_help_once_random_date_between($min_founding_ts, $max_founding_ts),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_help_once_fill_kontakte_random_dates')) {
	function cmx_help_once_fill_kontakte_random_dates(bool $overwrite_existing = true): array {
		$post_type = \defined(__NAMESPACE__ . '\\CMX_PT_KONTAKTE')
			? (string) \constant(__NAMESPACE__ . '\\CMX_PT_KONTAKTE')
			: 'kontakte';

		$kontakt_ids = \get_posts([
			'post_type'              => $post_type,
			'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => true,
		]);

		$result = [
			'total'   => \count($kontakt_ids),
			'updated' => 0,
			'skipped' => 0,
		];

		foreach ($kontakt_ids as $kontakt_id) {
			$kontakt_id = (int) $kontakt_id;
			if ($kontakt_id <= 0) {
				$result['skipped']++;
				continue;
			}

			$existing_founding = (string) \get_post_meta($kontakt_id, CMX_KONTAKTE_META_FIRMENGRUENDUNG, true);
			$existing_birth = (string) \get_post_meta($kontakt_id, CMX_KONTAKTE_META_GEBURTSDATUM, true);

			if (!$overwrite_existing && ($existing_founding !== '' || $existing_birth !== '')) {
				$result['skipped']++;
				continue;
			}

			$dates = cmx_help_once_generate_kontakt_dates();
			\update_post_meta($kontakt_id, CMX_KONTAKTE_META_FIRMENGRUENDUNG, (string) $dates['firmengruendung']);
			\update_post_meta($kontakt_id, CMX_KONTAKTE_META_GEBURTSDATUM, (string) $dates['geburtsdatum']);
			$result['updated']++;
		}

		return $result;
	}
}
