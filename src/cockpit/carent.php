<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_carent_is_enabled')) {
	function cmx_cockpit_carent_is_enabled(): bool {
		if (\function_exists(__NAMESPACE__ . '\\cmx_system_is_carent_enabled') && !cmx_system_is_carent_enabled()) {
			return false;
		}

		return \post_type_exists('carent');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_carent_status_meta_key')) {
	function cmx_cockpit_carent_status_meta_key(): string {
		return \defined(__NAMESPACE__ . '\\CMX_CARENT_STATUS_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_STATUS_META')
			: '_cmx_carent_status';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_carent_normalize_status')) {
	function cmx_cockpit_carent_normalize_status(string $status): string {
		$status = \sanitize_key($status);
		return $status === 'abgeschlossen' ? 'abgeschlossen' : 'offen';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_carent_status_label')) {
	function cmx_cockpit_carent_status_label(string $status): string {
		return cmx_cockpit_carent_normalize_status($status) === 'abgeschlossen'
			? 'abgeschlossen'
			: 'offen';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_carent_format_date')) {
	function cmx_cockpit_carent_format_date(int $timestamp): string {
		if ($timestamp <= 0) {
			return '';
		}

		return (string) \date_i18n('d.m.Y H:i', $timestamp);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_carent_collect_data')) {
	function cmx_cockpit_carent_collect_data(): array {
		static $cache = null;
		if (\is_array($cache)) {
			return $cache;
		}

		if (!cmx_cockpit_carent_is_enabled() || !\class_exists('\\WP_Query')) {
			$cache = [
				'closed_count' => 0,
				'open_count' => 0,
				'items' => [],
				'list_url' => (string) \admin_url('edit.php?post_type=carent'),
			];
			return $cache;
		}

		$query = new \WP_Query([
			'post_type' => 'carent',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => -1,
			'orderby' => 'modified',
			'order' => 'DESC',
			'fields' => 'ids',
			'no_found_rows' => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		]);

		$status_meta_key = cmx_cockpit_carent_status_meta_key();
		$items = [];
		$closed_count = 0;
		$open_count = 0;

		foreach ((array) $query->posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0) {
				continue;
			}

			$status = cmx_cockpit_carent_normalize_status((string) \get_post_meta($post_id, $status_meta_key, true));
			if ($status === 'abgeschlossen') {
				$closed_count++;
			} else {
				$open_count++;
			}

			if (\count($items) >= 10) {
				continue;
			}

			$title = \trim((string) \get_the_title($post_id));
			if ($title === '') {
				$title = 'Vertrag #' . $post_id;
			}

			$timestamp = (int) \get_post_modified_time('U', false, $post_id);
			if ($timestamp <= 0) {
				$timestamp = (int) \get_post_time('U', false, $post_id);
			}

			$items[] = [
				'id' => $post_id,
				'title' => $title,
				'edit_url' => (string) \get_edit_post_link($post_id, ''),
				'status' => $status,
				'status_label' => cmx_cockpit_carent_status_label($status),
				'date_label' => cmx_cockpit_carent_format_date($timestamp),
			];
		}

		$cache = [
			'closed_count' => $closed_count,
			'open_count' => $open_count,
			'items' => $items,
			'list_url' => (string) \admin_url('edit.php?post_type=carent'),
		];

		return $cache;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_register_carent_widget')) {
	function cmx_register_carent_widget(): void {
		if (!\current_user_can('edit_posts') || !cmx_cockpit_carent_is_enabled()) {
			return;
		}

		$list_url = (string) \admin_url('edit.php?post_type=carent');
		$title = '<a class="cmx-carent-widget-heading" href="' . \esc_url($list_url) . '">CaRent</a>';

		\wp_add_dashboard_widget(
			'cmx_carent_widget',
			$title,
			__NAMESPACE__ . '\\cmx_render_carent_widget'
		);
	}
}
\add_action('wp_dashboard_setup', __NAMESPACE__ . '\\cmx_register_carent_widget');

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_carent_widget')) {
	function cmx_render_carent_widget(): void {
		if (!\current_user_can('edit_posts') || !cmx_cockpit_carent_is_enabled()) {
			echo '<p>' . \esc_html__('CaRent ist nicht aktiv.', 'cmx-misbuero') . '</p>';
			return;
		}

		$data = cmx_cockpit_carent_collect_data();
		$items = (array) ($data['items'] ?? []);
		$closed_count = (int) ($data['closed_count'] ?? 0);
		$open_count = (int) ($data['open_count'] ?? 0);

		echo '<style>
			#cmx_carent_widget .cmx-carent-cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:2px 0 14px}
			#cmx_carent_widget .cmx-carent-card{border-radius:12px;padding:14px 16px;color:#fff;box-shadow:0 6px 18px rgba(16,24,40,.08)}
			#cmx_carent_widget .cmx-carent-card.is-closed{background:linear-gradient(135deg,#1b9e4b,#2fbf71)}
			#cmx_carent_widget .cmx-carent-card.is-open{background:linear-gradient(135deg,#c0392b,#e74c3c)}
			#cmx_carent_widget .cmx-carent-card-label{display:block;font-size:12px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;opacity:.95}
			#cmx_carent_widget .cmx-carent-card-value{display:block;margin-top:6px;font-size:30px;line-height:1;font-weight:800}
			#cmx_carent_widget .cmx-carent-table{width:100%;border-collapse:collapse;table-layout:fixed}
			#cmx_carent_widget .cmx-carent-table col:nth-child(2){width:120px}
			#cmx_carent_widget .cmx-carent-table col:nth-child(3){width:132px}
			#cmx_carent_widget .cmx-carent-table td{padding:6px 5px;vertical-align:top}
			#cmx_carent_widget .cmx-carent-table tbody tr{transition:background-color .15s ease}
			#cmx_carent_widget .cmx-carent-table tbody tr:hover td{background:#f7fbff}
			#cmx_carent_widget .cmx-carent-title-link,
			#cmx_carent_widget .cmx-carent-title-text{display:block;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
			#cmx_carent_widget .cmx-carent-title-link{text-decoration:none;color:#135eaf}
			#cmx_carent_widget .cmx-carent-title-link:hover{text-decoration:underline}
			#cmx_carent_widget .cmx-carent-status{text-align:center;white-space:nowrap}
			#cmx_carent_widget .cmx-carent-status-badge{display:inline-flex;align-items:center;justify-content:center;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;line-height:1.4}
			#cmx_carent_widget .cmx-carent-status-badge.is-open{background:#fdeceb;color:#b42318}
			#cmx_carent_widget .cmx-carent-status-badge.is-closed{background:#e8f7ee;color:#067647}
			#cmx_carent_widget .cmx-carent-date{text-align:right;white-space:nowrap;color:#667085}
			#cmx_carent_widget .cmx-carent-empty{margin:0;color:#667085}
			#cmx_carent_widget .postbox-header .cmx-carent-widget-heading{display:inline;color:inherit;text-decoration:none;font:inherit;font-size:inherit;font-weight:inherit;line-height:inherit;letter-spacing:inherit}
			#cmx_carent_widget .postbox-header .cmx-carent-widget-heading:hover{color:inherit;text-decoration:none}
		</style>';

		echo '<div class="cmx-carent-cards">';
		echo '<div class="cmx-carent-card is-closed">';
		echo '<span class="cmx-carent-card-label">' . \esc_html__('Abgeschlossen', 'cmx-misbuero') . '</span>';
		echo '<span class="cmx-carent-card-value">' . \esc_html((string) $closed_count) . '</span>';
		echo '</div>';
		echo '<div class="cmx-carent-card is-open">';
		echo '<span class="cmx-carent-card-label">' . \esc_html__('Offen', 'cmx-misbuero') . '</span>';
		echo '<span class="cmx-carent-card-value">' . \esc_html((string) $open_count) . '</span>';
		echo '</div>';
		echo '</div>';

		if ($items === []) {
			echo '<p class="cmx-carent-empty"><em>' . \esc_html__('Keine Verträge vorhanden.', 'cmx-misbuero') . '</em></p>';
		} else {
			echo '<table class="cmx-carent-table">';
			echo '<colgroup><col><col><col></colgroup>';
			echo '<tbody>';

			foreach ($items as $item) {
				$item = (array) $item;
				$title = (string) ($item['title'] ?? '');
				$edit_url = (string) ($item['edit_url'] ?? '');
				$status = cmx_cockpit_carent_normalize_status((string) ($item['status'] ?? 'offen'));
				$status_label = (string) ($item['status_label'] ?? cmx_cockpit_carent_status_label($status));
				$date_label = (string) ($item['date_label'] ?? '');

				echo '<tr>';
				echo '<td>';
				if ($edit_url !== '') {
					echo '<a class="cmx-carent-title-link" href="' . \esc_url($edit_url) . '" title="' . \esc_attr($title) . '">' . \esc_html($title) . '</a>';
				} else {
					echo '<span class="cmx-carent-title-text" title="' . \esc_attr($title) . '">' . \esc_html($title) . '</span>';
				}
				echo '</td>';
				echo '<td class="cmx-carent-status"><span class="cmx-carent-status-badge ' . ($status === 'abgeschlossen' ? 'is-closed' : 'is-open') . '">' . \esc_html($status_label) . '</span></td>';
				echo '<td class="cmx-carent-date">' . \esc_html($date_label) . '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}
	}
}
