<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_register_stoppuhr_notizen_widget')) {
	\add_action('wp_dashboard_setup', __NAMESPACE__ . '\\cmx_register_stoppuhr_notizen_widget');

	function cmx_register_stoppuhr_notizen_widget(): void {
		\wp_add_dashboard_widget(
			'cmx_stoppuhr_notizen_widget',
			'Stoppuhr-Notizen',
			__NAMESPACE__ . '\\cmx_render_stoppuhr_notizen_widget'
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_stoppuhr_notizen_widget_note_timestamp')) {
	function cmx_stoppuhr_notizen_widget_note_timestamp(array $row): int {
		$datum = \trim((string) ($row['datum'] ?? ''));
		$zeit = \trim((string) ($row['zeit'] ?? ''));
		if ($datum === '') {
			return 0;
		}

		$timezone = \wp_timezone();
		$formats = [
			'Y-m-d H:i',
			'Y-m-d',
		];

		foreach ($formats as $format) {
			$value = $format === 'Y-m-d H:i' ? ($datum . ' ' . ($zeit !== '' ? $zeit : '00:00')) : $datum;
			$date = \DateTimeImmutable::createFromFormat($format, $value, $timezone);
			if ($date instanceof \DateTimeImmutable) {
				return (int) $date->getTimestamp();
			}
		}

		return 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_stoppuhr_notizen_widget_is_stopwatch_row')) {
	function cmx_stoppuhr_notizen_widget_is_stopwatch_row(array $row): bool {
		$quelle = \function_exists(__NAMESPACE__ . '\\cmx_notizen_normalize_quelle')
			? (string) cmx_notizen_normalize_quelle((string) ($row['quelle'] ?? ''))
			: \sanitize_key((string) ($row['quelle'] ?? ''));

		if ($quelle === 'stoppuhr') {
			return true;
		}

		// Altbestand ohne Quellenmarker bestmöglich mitnehmen.
		$text = \trim(\wp_strip_all_tags((string) ($row['text'] ?? '')));
		return $text === 'Zeiterfassung per Google Chrome Erweiterung';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_collect_stoppuhr_notizen_widget_rows')) {
	function cmx_collect_stoppuhr_notizen_widget_rows(): array {
		global $wpdb;
		if (!($wpdb instanceof \wpdb)) {
			return [];
		}
		if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_supported_post_types') || !\function_exists(__NAMESPACE__ . '\\cmx_notizen_meta_key_for_post_type')) {
			return [];
		}

		$post_types = \array_values(\array_filter(cmx_notizen_supported_post_types(), 'is_string'));
		if ($post_types === []) {
			return [];
		}

		$meta_keys = [];
		foreach ($post_types as $post_type) {
			$meta_keys[] = (string) cmx_notizen_meta_key_for_post_type($post_type);
		}
		$meta_keys = \array_values(\array_unique(\array_filter($meta_keys, 'strlen')));
		if ($meta_keys === []) {
			return [];
		}

		$post_placeholders = \implode(',', \array_fill(0, \count($post_types), '%s'));
		$meta_placeholders = \implode(',', \array_fill(0, \count($meta_keys), '%s'));
		$params = \array_merge($meta_keys, $post_types);
		$sql = $wpdb->prepare(
			"SELECT p.ID AS post_id, p.post_title, p.post_type, p.post_status, pm.meta_value
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key IN ($meta_placeholders)
			  AND p.post_type IN ($post_placeholders)
			  AND p.post_status NOT IN ('trash', 'auto-draft')
			ORDER BY p.ID DESC",
			$params
		);
		if (!\is_string($sql) || $sql === '') {
			return [];
		}

		$raw_items = $wpdb->get_results($sql, \ARRAY_A);
		if (!\is_array($raw_items) || $raw_items === []) {
			return [];
		}

		$cutoff = (int) \current_time('timestamp') - (14 * \DAY_IN_SECONDS);
		$rows = [];

		foreach ($raw_items as $item) {
			$post_id = isset($item['post_id']) ? (int) $item['post_id'] : 0;
			$post_type = isset($item['post_type']) ? (string) $item['post_type'] : '';
			$post_title = \get_the_title($post_id);
			if ($post_id <= 0 || $post_type === '' || $post_title === '') {
				continue;
			}

			$decoded = \function_exists(__NAMESPACE__ . '\\cmx_notizen_decode_legacy_raw')
				? cmx_notizen_decode_legacy_raw($item['meta_value'] ?? '')
				: \maybe_unserialize($item['meta_value'] ?? '');

			$candidates = [];
			if (\is_array($decoded)) {
				$is_single_row = isset($decoded['betreff']) || isset($decoded['subject']) || isset($decoded['thema']) || isset($decoded['datum']) || isset($decoded['date']) || isset($decoded['zeit']) || isset($decoded['time']) || isset($decoded['text']) || isset($decoded['notiz']) || isset($decoded['note']) || isset($decoded['info']) || isset($decoded['quelle']) || isset($decoded['source']) || isset($decoded['herkunft']);
				$candidates = $is_single_row ? [$decoded] : $decoded;
			} else {
				$candidates = [$decoded];
			}

			foreach ($candidates as $candidate) {
				if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_normalize_row')) {
					continue;
				}
				$note = cmx_notizen_normalize_row($candidate);
				if ($note === null || !cmx_stoppuhr_notizen_widget_is_stopwatch_row($note)) {
					continue;
				}

				$timestamp = cmx_stoppuhr_notizen_widget_note_timestamp($note);
				if ($timestamp <= 0 || $timestamp < $cutoff) {
					continue;
				}

				$rows[] = [
					'post_id'    => $post_id,
					'post_type'  => $post_type,
					'post_title' => $post_title,
					'edit_url'   => (string) \get_edit_post_link($post_id),
					'betreff'    => (string) ($note['betreff'] ?? ''),
					'datum'      => (string) ($note['datum'] ?? ''),
					'zeit'       => (string) ($note['zeit'] ?? ''),
					'text'       => (string) ($note['text'] ?? ''),
					'timestamp'  => $timestamp,
				];
			}
		}

		\usort($rows, static function (array $a, array $b): int {
			$cmp = ((int) ($b['timestamp'] ?? 0)) <=> ((int) ($a['timestamp'] ?? 0));
			if ($cmp !== 0) {
				return $cmp;
			}

			return ((int) ($b['post_id'] ?? 0)) <=> ((int) ($a['post_id'] ?? 0));
		});

		return \array_values($rows);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_stoppuhr_notizen_widget')) {
	function cmx_render_stoppuhr_notizen_widget(): void {
		if (!\current_user_can('edit_posts')) {
			echo '<p>' . \esc_html__('Keine Berechtigung.', 'cmx') . '</p>';
			return;
		}

		$rows = cmx_collect_stoppuhr_notizen_widget_rows();

		echo '<style>
			#cmx_stoppuhr_notizen_widget .cmx-sw-notes-list{margin:0;padding:0;list-style:none;}
			#cmx_stoppuhr_notizen_widget .cmx-sw-notes-list.is-scroll{max-height:360px;overflow-y:auto;padding-right:4px;}
			#cmx_stoppuhr_notizen_widget .cmx-sw-notes-item{padding:10px 0;border-bottom:1px solid #edf0f3;}
			#cmx_stoppuhr_notizen_widget .cmx-sw-notes-item:first-child{padding-top:0;}
			#cmx_stoppuhr_notizen_widget .cmx-sw-notes-item:last-child{border-bottom:none;padding-bottom:0;}
			#cmx_stoppuhr_notizen_widget .cmx-sw-notes-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;}
			#cmx_stoppuhr_notizen_widget .cmx-sw-notes-title{font-weight:600;text-decoration:none;}
			#cmx_stoppuhr_notizen_widget .cmx-sw-notes-title:hover{text-decoration:underline;}
			#cmx_stoppuhr_notizen_widget .cmx-sw-notes-date{flex:0 0 auto;color:#667085;font-size:12px;white-space:nowrap;}
			#cmx_stoppuhr_notizen_widget .cmx-sw-notes-meta{margin-top:2px;color:#667085;font-size:12px;}
			#cmx_stoppuhr_notizen_widget .cmx-sw-notes-text{margin-top:6px;color:#344054;line-height:1.45;}
			#cmx_stoppuhr_notizen_widget .cmx-sw-notes-empty{margin:0;color:#667085;}
		</style>';

		if ($rows === []) {
			echo '<p class="cmx-sw-notes-empty">Keine Stoppuhr-Notizen in den letzten 14 Tagen.</p>';
			return;
		}

		$list_class = \count($rows) > 5 ? 'cmx-sw-notes-list is-scroll' : 'cmx-sw-notes-list';
		echo '<ul class="' . \esc_attr($list_class) . '">';
		foreach ($rows as $row) {
			$title = (string) ($row['post_title'] ?? '');
			$edit_url = (string) ($row['edit_url'] ?? '');
			$post_type = (string) ($row['post_type'] ?? '');
			$post_type_object = \get_post_type_object($post_type);
			$post_type_label = $post_type_object ? (string) $post_type_object->labels->singular_name : $post_type;
			$betreff = \trim((string) ($row['betreff'] ?? ''));
			$date_label = \trim((string) ($row['datum'] ?? ''));
			$time_label = \trim((string) ($row['zeit'] ?? ''));
			$text = \wp_trim_words(\wp_strip_all_tags((string) ($row['text'] ?? '')), 24, ' ...');

			if ($date_label !== '') {
				$date_timestamp = cmx_stoppuhr_notizen_widget_note_timestamp($row);
				if ($date_timestamp > 0) {
					$date_label = \wp_date('d.m.Y', $date_timestamp, \wp_timezone());
				}
			}
			if ($time_label !== '') {
				$date_label .= ' ' . $time_label;
			}

			echo '<li class="cmx-sw-notes-item">';
			echo '<div class="cmx-sw-notes-head">';
			if ($edit_url !== '') {
				echo '<a class="cmx-sw-notes-title" href="' . \esc_url($edit_url) . '">' . \esc_html($title) . '</a>';
			} else {
				echo '<span class="cmx-sw-notes-title">' . \esc_html($title) . '</span>';
			}
			echo '<span class="cmx-sw-notes-date">' . \esc_html($date_label) . '</span>';
			echo '</div>';

			$meta_parts = \array_values(\array_filter([$post_type_label, $betreff], 'strlen'));
			if ($meta_parts !== []) {
				echo '<div class="cmx-sw-notes-meta">' . \esc_html(\implode(' | ', $meta_parts)) . '</div>';
			}

			if ($text !== '') {
				echo '<div class="cmx-sw-notes-text">' . \esc_html($text) . '</div>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}
}
