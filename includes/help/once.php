<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_help_once_ansprechpartner_done_option')) {
	function cmx_help_once_ansprechpartner_done_option(): string {
		return 'cmx_help_once_random_ansprechpartner_distribution_done';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_help_once_ansprechpartner_group_counts')) {
	function cmx_help_once_ansprechpartner_group_counts(int $total): array {
		$total = \max(0, $total);
		$quotas = [
			'one' => $total * 0.50,
			'two' => $total * 0.25,
			'three' => $total * 0.25,
		];
		$counts = ['one' => 0, 'two' => 0, 'three' => 0];
		$fractionals = [];
		$assigned = 0;

		foreach ($quotas as $key => $quota) {
			$base = (int) \floor($quota);
			$counts[$key] = $base;
			$fractionals[$key] = $quota - $base;
			$assigned += $base;
		}

		$remainder = $total - $assigned;
		if ($remainder > 0) {
			$order = \array_keys($fractionals);
			\usort($order, static function (string $left, string $right) use ($fractionals): int {
				$left_fraction = (float) ($fractionals[$left] ?? 0.0);
				$right_fraction = (float) ($fractionals[$right] ?? 0.0);
				if ($left_fraction === $right_fraction) {
					return \wp_rand(0, 1) === 1 ? 1 : -1;
				}
				return $left_fraction < $right_fraction ? 1 : -1;
			});

			for ($i = 0; $i < $remainder; $i++) {
				$key = $order[$i % \count($order)] ?? 'one';
				$counts[$key]++;
			}
		}

		return $counts;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_help_once_pick_ansprechpartner_beziehung')) {
	function cmx_help_once_pick_ansprechpartner_beziehung(): string {
		if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_zu_kontakt_beziehung_options')) {
			return '';
		}

		$options = (array) cmx_kontakte_zu_kontakt_beziehung_options();
		foreach ($options as $option) {
			$value = \sanitize_title((string) ($option['value'] ?? ''));
			$label = \strtolower((string) \remove_accents((string) ($option['label'] ?? '')));
			if ($value === '') {
				continue;
			}
			if (\strpos($value, 'ansprech') !== false || \strpos($label, 'ansprech') !== false) {
				return $value;
			}
		}

		return \sanitize_title((string) ($options[0]['value'] ?? ''));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_help_once_build_ansprechpartner_targets')) {
	function cmx_help_once_build_ansprechpartner_targets(array $contact_ids): array {
		$targets = [];
		$shuffled_ids = \array_values(\array_map('intval', $contact_ids));
		if ($shuffled_ids === []) {
			return $targets;
		}

		\shuffle($shuffled_ids);
		$counts = cmx_help_once_ansprechpartner_group_counts(\count($shuffled_ids));
		$offset = 0;
		foreach ([
			['key' => 'three', 'target' => 3],
			['key' => 'two', 'target' => 2],
			['key' => 'one', 'target' => 1],
		] as $group) {
			$group_count = (int) ($counts[(string) ($group['key'] ?? '')] ?? 0);
			for ($i = 0; $i < $group_count; $i++) {
				$contact_id = (int) ($shuffled_ids[$offset] ?? 0);
				if ($contact_id <= 0) {
					break;
				}
				$targets[$contact_id] = (int) ($group['target'] ?? 1);
				$offset++;
			}
		}

		foreach ($shuffled_ids as $contact_id) {
			$contact_id = (int) $contact_id;
			if ($contact_id > 0 && !isset($targets[$contact_id])) {
				$targets[$contact_id] = 1;
			}
		}

		return $targets;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_help_once_randomize_ansprechpartner_distribution')) {
	function cmx_help_once_randomize_ansprechpartner_distribution(): array {
		$result = [
			'total' => 0,
			'updated' => 0,
			'with_0' => 0,
			'with_1' => 0,
			'with_2' => 0,
			'with_3' => 0,
		];

		if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_zu_kontakt_store_rows')) {
			return $result;
		}

		$contact_ids = \get_posts([
			'post_type' => 'kontakte',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'orderby' => 'ID',
			'order' => 'ASC',
			'no_found_rows' => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters' => true,
		]);
		$contact_ids = \array_values(\array_unique(\array_filter(\array_map('intval', (array) $contact_ids))));
		$result['total'] = \count($contact_ids);
		if ($contact_ids === []) {
			return $result;
		}

		$targets = cmx_help_once_build_ansprechpartner_targets($contact_ids);
		$beziehung = cmx_help_once_pick_ansprechpartner_beziehung();

		foreach ($contact_ids as $contact_id) {
			$pool = \array_values(\array_filter($contact_ids, static fn(int $candidate_id): bool => $candidate_id !== (int) $contact_id));
			\shuffle($pool);

			$desired = (int) ($targets[(int) $contact_id] ?? 1);
			$limit = \min($desired, \count($pool));
			$rows = [];
			for ($i = 0; $i < $limit; $i++) {
				$related_id = (int) ($pool[$i] ?? 0);
				if ($related_id <= 0) {
					continue;
				}
				$rows[] = [
					'id' => $related_id,
					'beziehung' => $beziehung,
				];
			}

			cmx_kontakte_zu_kontakt_store_rows((int) $contact_id, $rows);
			$result['updated']++;
			$result_key = 'with_' . \count($rows);
			if (isset($result[$result_key])) {
				$result[$result_key]++;
			}
		}

		return $result;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_help_once_run_random_ansprechpartner_distribution')) {
	function cmx_help_once_run_random_ansprechpartner_distribution(): void {
		if (!\is_admin() || !\current_user_can('manage_options')) {
			return;
		}

		$option_name = cmx_help_once_ansprechpartner_done_option();
		if ((string) \get_option($option_name, '') !== '') {
			return;
		}

		$result = cmx_help_once_randomize_ansprechpartner_distribution();
		\update_option($option_name, (string) \current_time('mysql'), false);

		\error_log(
			'[CMX once] Ansprechpartner verteilt: total=' . (int) ($result['total'] ?? 0)
			. ', updated=' . (int) ($result['updated'] ?? 0)
			. ', one=' . (int) ($result['with_1'] ?? 0)
			. ', two=' . (int) ($result['with_2'] ?? 0)
			. ', three=' . (int) ($result['with_3'] ?? 0)
		);
	}
}

\add_action('admin_init', __NAMESPACE__ . '\\cmx_help_once_run_random_ansprechpartner_distribution');
