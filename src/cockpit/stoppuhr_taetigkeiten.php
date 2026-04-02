<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_register_stoppuhr_taetigkeiten_widget')) {
	\add_action('wp_dashboard_setup', __NAMESPACE__ . '\\cmx_register_stoppuhr_taetigkeiten_widget');

	function cmx_register_stoppuhr_taetigkeiten_widget(): void {
		\wp_add_dashboard_widget(
			'cmx_stoppuhr_taetigkeiten_widget',
			'Stoppuhr-Tätigkeiten',
			__NAMESPACE__ . '\\cmx_render_stoppuhr_taetigkeiten_widget'
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_stoppuhr_task_widget_timestamp')) {
	function cmx_stoppuhr_task_widget_timestamp(array $task): int {
		$datum = \trim((string) ($task['datum'] ?? ''));
		$zeit = \trim((string) ($task['zeit'] ?? ''));
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_stoppuhr_task_widget_is_stopwatch_task')) {
	function cmx_stoppuhr_task_widget_is_stopwatch_task(array $task): bool {
		$quelle = \function_exists(__NAMESPACE__ . '\\cmx_projekt_task_normalize_quelle')
			? (string) cmx_projekt_task_normalize_quelle($task['quelle'] ?? '')
			: \sanitize_key((string) ($task['quelle'] ?? ''));

		return $quelle === 'stoppuhr';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_collect_stoppuhr_taetigkeiten_widget_rows')) {
	function cmx_collect_stoppuhr_taetigkeiten_widget_rows(): array {
		global $wpdb;
		if (!($wpdb instanceof \wpdb)) {
			return [];
		}

		$post_types = \defined(__NAMESPACE__ . '\\CMX_TASK_POST_TYPES')
			? (array) \constant(__NAMESPACE__ . '\\CMX_TASK_POST_TYPES')
			: ['projekte', 'kontakte'];
		$post_types = \array_values(\array_filter($post_types, 'is_string'));
		if ($post_types === []) {
			return [];
		}

		$meta_key = \defined(__NAMESPACE__ . '\\CMX_PROJEKT_TASK_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_PROJEKT_TASK_META')
			: '_cmx_projekt_tasks';
		$post_placeholders = \implode(',', \array_fill(0, \count($post_types), '%s'));
		$params = \array_merge([$meta_key], $post_types);
		$sql = $wpdb->prepare(
			"SELECT p.ID AS post_id, p.post_title, p.post_type, pm.meta_value
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = %s
			  AND p.post_type IN ($post_placeholders)
			  AND p.post_status NOT IN ('trash', 'auto-draft')
			ORDER BY p.ID DESC",
			$params
		);
		if (!\is_string($sql) || $sql === '') {
			return [];
		}

		$items = $wpdb->get_results($sql, \ARRAY_A);
		if (!\is_array($items) || $items === []) {
			return [];
		}

		$cutoff = (int) \current_time('timestamp') - (14 * \DAY_IN_SECONDS);
		$rows = [];

		foreach ($items as $item) {
			$post_id = isset($item['post_id']) ? (int) $item['post_id'] : 0;
			$post_type = isset($item['post_type']) ? (string) $item['post_type'] : '';
			if ($post_id <= 0 || $post_type === '') {
				continue;
			}

			$tasks = \maybe_unserialize($item['meta_value'] ?? '');
			if (!\is_array($tasks) || $tasks === []) {
				continue;
			}

			$post_title = (string) \get_the_title($post_id);
			if ($post_title === '') {
				continue;
			}

			foreach ($tasks as $task) {
				if (!\is_array($task) || !cmx_stoppuhr_task_widget_is_stopwatch_task($task)) {
					continue;
				}

				$timestamp = cmx_stoppuhr_task_widget_timestamp($task);
				if ($timestamp <= 0 || $timestamp < $cutoff) {
					continue;
				}

				$artikel_id = isset($task['artikel_id']) ? (int) $task['artikel_id'] : 0;
				$produkt_id = isset($task['produkt_id']) ? (int) $task['produkt_id'] : 0;
				$artikel_label = $artikel_id > 0 ? (string) \get_the_title($artikel_id) : '';
				$produkt_label = $produkt_id > 0 ? (string) \get_the_title($produkt_id) : '';
				$detail_parts = \array_values(\array_filter([$artikel_label, $produkt_label], 'strlen'));

				$rows[] = [
					'post_id'       => $post_id,
					'post_type'     => $post_type,
					'post_title'    => $post_title,
					'edit_url'      => (string) \get_edit_post_link($post_id),
					'datum'         => (string) ($task['datum'] ?? ''),
					'zeit'          => (string) ($task['zeit'] ?? ''),
					'dauer'         => (string) ($task['dauer'] ?? ''),
					'info'          => (string) ($task['info'] ?? ''),
					'verrechenbar'  => !empty($task['verrechenbar']),
					'detail'        => \implode(' / ', $detail_parts),
					'timestamp'     => $timestamp,
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_stoppuhr_taetigkeiten_widget')) {
	function cmx_render_stoppuhr_taetigkeiten_widget(): void {
		if (!\current_user_can('edit_posts')) {
			echo '<p>' . \esc_html__('Keine Berechtigung.', 'cmx') . '</p>';
			return;
		}

		$rows = cmx_collect_stoppuhr_taetigkeiten_widget_rows();

		echo '<style>
			#cmx_stoppuhr_taetigkeiten_widget .cmx-sw-task-list{margin:0;padding:0;list-style:none;}
			#cmx_stoppuhr_taetigkeiten_widget .cmx-sw-task-list.is-scroll{max-height:360px;overflow-y:auto;padding-right:4px;}
			#cmx_stoppuhr_taetigkeiten_widget .cmx-sw-task-item{padding:10px 0;border-bottom:1px solid #edf0f3;}
			#cmx_stoppuhr_taetigkeiten_widget .cmx-sw-task-item:first-child{padding-top:0;}
			#cmx_stoppuhr_taetigkeiten_widget .cmx-sw-task-item:last-child{border-bottom:none;padding-bottom:0;}
			#cmx_stoppuhr_taetigkeiten_widget .cmx-sw-task-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;}
			#cmx_stoppuhr_taetigkeiten_widget .cmx-sw-task-title{font-weight:600;text-decoration:none;}
			#cmx_stoppuhr_taetigkeiten_widget .cmx-sw-task-title:hover{text-decoration:underline;}
			#cmx_stoppuhr_taetigkeiten_widget .cmx-sw-task-date{flex:0 0 auto;color:#667085;font-size:12px;white-space:nowrap;}
			#cmx_stoppuhr_taetigkeiten_widget .cmx-sw-task-meta{margin-top:2px;color:#667085;font-size:12px;}
			#cmx_stoppuhr_taetigkeiten_widget .cmx-sw-task-text{margin-top:6px;color:#344054;line-height:1.45;}
			#cmx_stoppuhr_taetigkeiten_widget .cmx-sw-task-empty{margin:0;color:#667085;}
		</style>';

		if ($rows === []) {
			echo '<p class="cmx-sw-task-empty">Keine Stoppuhr-Tätigkeiten in den letzten 14 Tagen.</p>';
			return;
		}

		$list_class = \count($rows) > 5 ? 'cmx-sw-task-list is-scroll' : 'cmx-sw-task-list';
		echo '<ul class="' . \esc_attr($list_class) . '">';
		foreach ($rows as $row) {
			$title = (string) ($row['post_title'] ?? '');
			$edit_url = (string) ($row['edit_url'] ?? '');
			$post_type = (string) ($row['post_type'] ?? '');
			$post_type_object = \get_post_type_object($post_type);
			$post_type_label = $post_type_object ? (string) $post_type_object->labels->singular_name : $post_type;
			$dauer = \trim((string) ($row['dauer'] ?? ''));
			$detail = \trim((string) ($row['detail'] ?? ''));
			$info = \wp_trim_words(\wp_strip_all_tags((string) ($row['info'] ?? '')), 24, ' ...');
			$date_timestamp = cmx_stoppuhr_task_widget_timestamp($row);
			$date_label = $date_timestamp > 0 ? \wp_date('d.m.Y', $date_timestamp, \wp_timezone()) : (string) ($row['datum'] ?? '');
			$time_label = \trim((string) ($row['zeit'] ?? ''));
			if ($time_label !== '') {
				$date_label .= ' ' . $time_label;
			}

			$meta_parts = [$post_type_label];
			if ($dauer !== '') {
				$meta_parts[] = $dauer . ' h';
			}
			if ($detail !== '') {
				$meta_parts[] = $detail;
			}
			if (!empty($row['verrechenbar'])) {
				$meta_parts[] = 'verrechenbar';
			}
			$meta_parts = \array_values(\array_filter($meta_parts, 'strlen'));

			echo '<li class="cmx-sw-task-item">';
			echo '<div class="cmx-sw-task-head">';
			if ($edit_url !== '') {
				echo '<a class="cmx-sw-task-title" href="' . \esc_url($edit_url) . '">' . \esc_html($title) . '</a>';
			} else {
				echo '<span class="cmx-sw-task-title">' . \esc_html($title) . '</span>';
			}
			echo '<span class="cmx-sw-task-date">' . \esc_html($date_label) . '</span>';
			echo '</div>';

			if ($meta_parts !== []) {
				echo '<div class="cmx-sw-task-meta">' . \esc_html(\implode(' | ', $meta_parts)) . '</div>';
			}
			if ($info !== '') {
				echo '<div class="cmx-sw-task-text">' . \esc_html($info) . '</div>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}
}
