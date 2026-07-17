<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_register_angebote_widget')) {
	function cmx_register_angebote_widget(): void {
		\wp_add_dashboard_widget(
			'cmx_angebote_widget',
			'Angebote',
			__NAMESPACE__ . '\\cmx_render_angebote_widget'
		);
	}
}
\add_action('wp_dashboard_setup', __NAMESPACE__ . '\\cmx_register_angebote_widget');

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_angebote_widget')) {
	function cmx_render_angebote_widget(): void {
		if (!\current_user_can('edit_posts')) {
			echo '<p>' . \esc_html__('Keine Berechtigung.', 'default') . '</p>';
			return;
		}

		$taxonomy = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_taxonomy')
			? (string) cmx_cockpit_beleg_taxonomy()
			: '';
		if ($taxonomy === '' || !\taxonomy_exists($taxonomy)) {
			echo '<p><em>Keine Angebots-Kategorie gefunden.</em></p>';
			return;
		}

		$status_meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_OFFERTENSTATUS')
			? (string) CMX_BELEG_META_OFFERTENSTATUS
			: '_cmx_beleg_offertenstatus';
		$valid_until_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_FAELLIG')
			? (string) CMX_BELEG_META_FAELLIG
			: '_cmx_beleg_faelligkeitsdatum';
		$kontakt_label_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_LABEL')
			? (string) CMX_BELEG_META_KONTAKT_LABEL
			: '_cmx_beleg_kontakt_label';

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
				'terms' => ['offerte', 'offerten'],
				'operator' => 'IN',
			]],
			'meta_query' => [
				'relation' => 'OR',
				[
					'key' => $status_meta_key,
					'compare' => 'NOT EXISTS',
				],
				[
					'key' => $status_meta_key,
					'value' => '',
					'compare' => '=',
				],
				[
					'key' => $status_meta_key,
					'value' => 'offen',
					'compare' => '=',
				],
			],
		]);

		echo '<style>
			#cmx_angebote_widget .cmx-angebote-list{margin:0;padding:0;list-style:none}
			#cmx_angebote_widget .cmx-angebote-item{padding:7px 8px;margin:0 -8px;border-bottom:1px solid #f0f0f0}
			#cmx_angebote_widget .cmx-angebote-item:last-child{border-bottom:none}
			#cmx_angebote_widget .cmx-angebote-title{display:block;font-weight:600;text-decoration:none}
			#cmx_angebote_widget .cmx-angebote-title:hover{color:#135e96}
			#cmx_angebote_widget .cmx-angebote-meta{display:block;margin-top:2px;font-size:12px;color:#6b7280}
		</style>';

		if (!$query->have_posts()) {
			echo '<p><em>Keine offenen Angebote.</em></p>';
			return;
		}

		echo '<ul class="cmx-angebote-list">';
		foreach ((array) $query->posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0) {
				continue;
			}

			$title = \trim((string) \get_the_title($post_id));
			if ($title === '') {
				$title = '#' . $post_id;
			}
			$edit_url = (string) \get_edit_post_link($post_id, '');
			$kontakt = \trim((string) \get_post_meta($post_id, $kontakt_label_key, true));
			$valid_until_raw = \trim((string) \get_post_meta($post_id, $valid_until_key, true));
			$valid_until = $valid_until_raw;
			if ($valid_until_raw !== '' && \function_exists(__NAMESPACE__ . '\\cmx_format_ch_date')) {
				$formatted = (string) cmx_format_ch_date($valid_until_raw);
				if ($formatted !== '') {
					$valid_until = $formatted;
				}
			}

			$meta = [];
			if ($kontakt !== '') {
				$meta[] = $kontakt;
			}
			if ($valid_until !== '') {
				$meta[] = 'Gültig bis ' . $valid_until;
			}

			echo '<li class="cmx-angebote-item">';
			echo '<a class="cmx-angebote-title" href="' . \esc_url($edit_url) . '">' . \esc_html($title) . '</a>';
			if (!empty($meta)) {
				echo '<span class="cmx-angebote-meta">' . \esc_html(\implode(' | ', $meta)) . '</span>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}
}
