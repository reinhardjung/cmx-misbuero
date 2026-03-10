<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_projekte_status_taxonomy')) {
	function cmx_cockpit_projekte_status_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_projekte_detect_status_taxonomy')) {
			$tax = (string) cmx_projekte_detect_status_taxonomy();
			if ($tax !== '' && \taxonomy_exists($tax) && \is_object_in_taxonomy('projekte', $tax)) {
				return $tax;
			}
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_projekte_status_tax')) {
			$tax = (string) cmx_projekte_status_tax();
			if ($tax !== '' && \taxonomy_exists($tax) && \is_object_in_taxonomy('projekte', $tax)) {
				return $tax;
			}
		}

		foreach (['projekte_status', 'projekt_status', 'status'] as $candidate) {
			if (\taxonomy_exists($candidate) && \is_object_in_taxonomy('projekte', $candidate)) {
				return $candidate;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_projekte_is_released_status_term')) {
	function cmx_cockpit_projekte_is_released_status_term(string $slug, string $name): bool {
		$slug_key = \sanitize_title($slug);
		$name_key = \sanitize_title($name);
		$released_key = \sanitize_title('Freigegeben');
		return $slug_key === $released_key || $name_key === $released_key;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_projekte_released_status_terms')) {
	function cmx_cockpit_projekte_released_status_terms(string $taxonomy): array {
		if ($taxonomy === '' || !\taxonomy_exists($taxonomy)) {
			return [];
		}

		$terms = \get_terms([
			'taxonomy' => $taxonomy,
			'hide_empty' => false,
		]);
		if (\is_wp_error($terms) || !\is_array($terms)) {
			return [];
		}

		$active_terms = [];
		foreach ($terms as $term) {
			if (!$term instanceof \WP_Term) {
				continue;
			}
			if (cmx_cockpit_projekte_is_released_status_term((string) $term->slug, (string) $term->name)) {
				$active_terms[] = $term;
			}
		}

		return $active_terms;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_projekte_meta_is_active')) {
	function cmx_cockpit_projekte_meta_is_active(int $post_id): bool {
		$begin_key = \defined(__NAMESPACE__ . '\\CMX_PROJ_BEG_META') ? (string) CMX_PROJ_BEG_META : '_cmx_projekt_beginn';
		$end_key = \defined(__NAMESPACE__ . '\\CMX_PROJ_END_META') ? (string) CMX_PROJ_END_META : '_cmx_projekt_ende';

		$begin_raw = \trim((string) \get_post_meta($post_id, $begin_key, true));
		$end_raw = \trim((string) \get_post_meta($post_id, $end_key, true));
		$today = \current_time('Y-m-d');

		if ($begin_raw !== '' && \preg_match('/^\d{4}-\d{2}-\d{2}$/', $begin_raw) && $begin_raw > $today) {
			return false;
		}

		if ($end_raw !== '' && \preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_raw) && $end_raw < $today) {
			return false;
		}

		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_projekte_format_date')) {
	function cmx_cockpit_projekte_format_date(string $raw): string {
		$raw = \trim($raw);
		if ($raw === '') {
			return '';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_format_ch_date')) {
			$formatted = (string) cmx_format_ch_date($raw);
			if ($formatted !== '') {
				return $formatted;
			}
		}

		$ts = \strtotime($raw);
		return $ts ? (string) \date_i18n('d.m.Y', $ts) : $raw;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_projekte_parse_amount')) {
	function cmx_cockpit_projekte_parse_amount($raw): float {
		if (\is_numeric($raw)) {
			return (float) $raw;
		}

		$text = \trim((string) $raw);
		if ($text === '') {
			return 0.0;
		}

		$text = \str_replace(["\xc2\xa0", ' ', "'"], '', $text);
		$text = \str_replace(',', '.', $text);
		return \is_numeric($text) ? (float) $text : 0.0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_projekte_format_money')) {
	function cmx_cockpit_projekte_format_money(float $amount): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')) {
			return (string) cmx_format_swiss_number($amount, 2);
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_projekt_format_swiss_number')) {
			return (string) cmx_projekt_format_swiss_number($amount, 2);
		}
		return \number_format($amount, 2, '.', "'");
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_projekte_umsatz_total')) {
	function cmx_cockpit_projekte_umsatz_total(int $post_id): float {
		if (\function_exists(__NAMESPACE__ . '\\cmx_proj_calc_umsatz_total')) {
			return (float) cmx_proj_calc_umsatz_total($post_id);
		}

		$meta_key = \defined(__NAMESPACE__ . '\\CMX_PROJ_UMSATZ_META') ? (string) CMX_PROJ_UMSATZ_META : '_cmx_projekt_umsatz_total';
		return cmx_cockpit_projekte_parse_amount(\get_post_meta($post_id, $meta_key, true));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_projekte_data')) {
	function cmx_cockpit_projekte_data(): array {
		static $cache = null;
		if (\is_array($cache)) {
			return $cache;
		}

		$status_tax = cmx_cockpit_projekte_status_taxonomy();
		$active_terms = cmx_cockpit_projekte_released_status_terms($status_tax);
		$active_slugs = [];
		foreach ($active_terms as $term) {
			if ($term instanceof \WP_Term) {
				$active_slugs[] = (string) $term->slug;
			}
		}
		$active_slugs = \array_values(\array_unique(\array_filter($active_slugs)));

		$list_args = ['post_type' => 'projekte'];
		if (\count($active_slugs) === 1) {
			$list_args['cmx_status_filter'] = $active_slugs[0];
		}
		$list_url = (string) \add_query_arg($list_args, \admin_url('edit.php'));

		if ($status_tax === '' || empty($active_slugs)) {
			$cache = [
				'total' => 0,
				'items' => [],
				'list_url' => $list_url,
			];
			return $cache;
		}

		$query_args = [
			'post_type' => 'projekte',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
			'fields' => 'ids',
			'no_found_rows' => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];

			$query_args['tax_query'] = [[
				'taxonomy' => $status_tax,
				'field' => 'slug',
				'terms' => $active_slugs,
				'operator' => 'IN',
			]];

		$q = new \WP_Query($query_args);
		$items = [];

		$begin_key = \defined(__NAMESPACE__ . '\\CMX_PROJ_BEG_META') ? (string) CMX_PROJ_BEG_META : '_cmx_projekt_beginn';
		$kontakt_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKT_META') ? (string) CMX_KONTAKT_META : '_cmx_projekt_kontakt_id';

		foreach ((array) $q->posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0) {
				continue;
			}

				$title = \trim((string) \get_the_title($post_id));
			if ($title === '') {
				$title = '#' . $post_id;
			}

			$begin_raw = \trim((string) \get_post_meta($post_id, $begin_key, true));
			$kontakt_id = (int) \get_post_meta($post_id, $kontakt_key, true);
			$kontakt_title = '';
			$kontakt_url = '';
			if ($kontakt_id > 0 && \get_post_status($kontakt_id)) {
				$kontakt_title = \trim((string) \get_the_title($kontakt_id));
				$kontakt_url = (string) \get_edit_post_link($kontakt_id, '');
			}

			$items[] = [
				'id' => $post_id,
				'title' => $title,
				'edit_url' => (string) \get_edit_post_link($post_id, ''),
				'date_raw' => $begin_raw,
				'date_label' => cmx_cockpit_projekte_format_date($begin_raw),
				'kontakt_title' => $kontakt_title,
				'kontakt_url' => $kontakt_url,
				'umsatz' => cmx_cockpit_projekte_umsatz_total($post_id),
			];
		}

		$cache = [
			'total' => \count($items),
			'items' => $items,
			'list_url' => $list_url,
		];

		return $cache;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_register_projekte_widget')) {
	function cmx_register_projekte_widget(): void {
		if (!\current_user_can('edit_posts')) {
			return;
		}

		$data = cmx_cockpit_projekte_data();
			$title = 'Freigegebene Projekte (' . (int) ($data['total'] ?? 0) . ')';
		$title_link = '<a href="' . \esc_url((string) ($data['list_url'] ?? '')) . '" style="font-weight:700;font-size:14px;text-decoration:none;">' . \esc_html($title) . '</a>';

		\wp_add_dashboard_widget(
			'cmx_projekte_widget',
			$title_link,
			__NAMESPACE__ . '\\cmx_render_projekte_widget'
		);
	}
}
\add_action('wp_dashboard_setup', __NAMESPACE__ . '\\cmx_register_projekte_widget');

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_projekte_widget')) {
	function cmx_render_projekte_widget(): void {
		if (!\current_user_can('edit_posts')) {
			echo '<p>' . \esc_html__('Keine Berechtigung.', 'default') . '</p>';
			return;
		}

		$data = cmx_cockpit_projekte_data();
		$items = (array) ($data['items'] ?? []);

			echo '<style>
				#cmx_projekte_widget .cmx-projekte-table{width:100%;border-collapse:collapse;table-layout:fixed}
				#cmx_projekte_widget .cmx-projekte-table col:nth-child(1){width:34%}
				#cmx_projekte_widget .cmx-projekte-table col:nth-child(2){width:78px}
				#cmx_projekte_widget .cmx-projekte-table col:nth-child(4){width:88px}
				#cmx_projekte_widget .cmx-projekte-table td{padding:5px;vertical-align:top}
			#cmx_projekte_widget .cmx-projekte-table tbody tr{transition:background-color .15s ease}
			#cmx_projekte_widget .cmx-projekte-table tbody tr:hover td{background:#f7fbff}
			#cmx_projekte_widget .cmx-projekte-title-link,
			#cmx_projekte_widget .cmx-projekte-contact-link{display:block;text-decoration:none}
			#cmx_projekte_widget .cmx-projekte-title-link:hover,
			#cmx_projekte_widget .cmx-projekte-contact-link:hover{text-decoration:underline}
			#cmx_projekte_widget .cmx-projekte-title-link{font-weight:700;color:#135eaf;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
			#cmx_projekte_widget .cmx-projekte-title-text{display:block;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
			#cmx_projekte_widget .cmx-projekte-date{white-space:nowrap;color:#50575e}
			#cmx_projekte_widget .cmx-projekte-contact,
			#cmx_projekte_widget .cmx-projekte-contact-link{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
			#cmx_projekte_widget .cmx-projekte-umsatz{text-align:right;white-space:nowrap;font-weight:700}
		</style>';

			if (empty($items)) {
				echo '<p><em>Keine freigegebenen Projekte vorhanden.</em></p>';
				return;
			}

			echo '<table class="cmx-projekte-table">';
			echo '<colgroup><col><col><col><col></colgroup>';
			echo '<tbody>';

		foreach ($items as $item) {
			$item = (array) $item;
			$title = (string) ($item['title'] ?? '');
			$edit_url = (string) ($item['edit_url'] ?? '');
			$date_label = (string) ($item['date_label'] ?? '');
			$kontakt_title = (string) ($item['kontakt_title'] ?? '');
			$kontakt_url = (string) ($item['kontakt_url'] ?? '');
			$umsatz = (float) ($item['umsatz'] ?? 0.0);

			echo '<tr>';
			echo '<td>';
			if ($edit_url !== '') {
				echo '<a class="cmx-projekte-title-link" href="' . \esc_url($edit_url) . '" title="' . \esc_attr($title) . '">' . \esc_html($title) . '</a>';
			} else {
				echo '<span class="cmx-projekte-title-text" title="' . \esc_attr($title) . '">' . \esc_html($title) . '</span>';
			}
			echo '</td>';
				echo '<td><span class="cmx-projekte-date" title="Start des Projektes">' . \esc_html($date_label) . '</span></td>';
			echo '<td>';
			if ($kontakt_title !== '' && $kontakt_url !== '') {
				echo '<a class="cmx-projekte-contact-link" href="' . \esc_url($kontakt_url) . '" title="' . \esc_attr($kontakt_title) . '">' . \esc_html($kontakt_title) . '</a>';
			} else {
				echo '<span class="cmx-projekte-contact" title="' . \esc_attr($kontakt_title) . '">' . \esc_html($kontakt_title) . '</span>';
			}
			echo '</td>';
			echo '<td class="cmx-projekte-umsatz">' . \esc_html(cmx_cockpit_projekte_format_money($umsatz)) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}
