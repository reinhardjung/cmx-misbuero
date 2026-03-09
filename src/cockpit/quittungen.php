<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Dashboard-Widget: Quittungen
 * Zeigt zwei Kennzahlen:
 * - bezahlt: Anzahl und Summe aller bezahlten Quittungen
 * - offen:   Anzahl und Summe aller offenen Quittungen
 */

\add_action('wp_dashboard_setup', function (): void {
	\wp_add_dashboard_widget(
		'cmx_quittungen_widget',
		'Quittungen',
		__NAMESPACE__ . '\\cmx_render_quittungen_widget'
	);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_quittungen_widget')) {
	function cmx_render_quittungen_widget(): void {
		if (!\current_user_can('edit_posts')) {
			echo '<p>' . \esc_html__('Keine Berechtigung.', 'default') . '</p>';
			return;
		}

		if (!\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
			echo '<p><em>Summen-Berechnung nicht verfügbar.</em></p>';
			return;
		}

		$range = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_requested_range')
			? (array) cmx_cockpit_requested_range()
			: ['from' => '', 'to' => ''];

		$taxonomy = 'belege_kategorien';
		$term_slugs = ['quittung', 'quittungen'];

		$calc_total = static function (array $args, string $date_mode) use ($range): array {
			$q = new \WP_Query($args);
			$count = 0;
			$sum = 0.0;

			if ($q->have_posts()) {
				foreach ((array) $q->posts as $pid) {
					$pid = (int) $pid;
					if ($pid <= 0) {
						continue;
					}

					if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_date_in_range')) {
						$date_value = '';
						if ($date_mode === 'paid' && \function_exists(__NAMESPACE__ . '\\cmx_cockpit_paid_date')) {
							$date_value = cmx_cockpit_paid_date($pid);
						} elseif ($date_mode === 'post' && \function_exists(__NAMESPACE__ . '\\cmx_cockpit_post_date')) {
							$date_value = cmx_cockpit_post_date($pid);
						}

						if ($date_value === '' || !cmx_cockpit_date_in_range($date_value, $range)) {
							continue;
						}
					}

					$calc = (array) cmxbu_get_beleg_positionen_calc($pid);
					$sum += isset($calc['total']) ? (float) $calc['total'] : 0.0;
					$count++;
				}
			}

			return [$count, $sum];
		};

		$base_args = [
			'post_type'      => 'belege',
			'post_status'    => ['publish', 'private'],
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'tax_query'      => [
				[
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $term_slugs,
					'operator' => 'IN',
				],
			],
		];

		[$paid_count, $paid_sum] = $calc_total($base_args + [
			'meta_query' => [
				[
					'key'     => '_cmx_beleg_bezahlt_am',
					'compare' => 'EXISTS',
				],
				[
					'key'     => '_cmx_beleg_bezahlt_am',
					'value'   => '',
					'compare' => '!=',
				],
			],
		], 'paid');

		[$open_count, $open_sum] = $calc_total($base_args + [
			'meta_query' => [
				'relation' => 'OR',
				[
					'key'     => '_cmx_beleg_bezahlt_am',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_cmx_beleg_bezahlt_am',
					'value'   => '',
					'compare' => '=',
				],
			],
		], 'post');

		echo '<style>
			.cmx-quittungen-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:6px;}
			.cmx-quittungen-box{border:1px solid #e5e5e5;border-radius:6px;padding:10px;background:#fafafa;}
			.cmx-quittungen-title{font-size:12px;text-transform:uppercase;letter-spacing:.02em;color:#555;margin-bottom:6px;}
			.cmx-quittungen-val{font-size:14px;font-weight:600;line-height:1.4;}
			.cmx-quittungen-sub{color:#777;font-size:12px;}
		</style>';

		echo '<div class="cmx-quittungen-grid">';
		echo '  <div class="cmx-quittungen-box">';
		echo '    <div class="cmx-quittungen-title">Bezahlt</div>';
		echo '    <div class="cmx-quittungen-val">' . \esc_html(cmx_format_swiss_number($paid_count, 0)) . '</div>';
		echo '    <div class="cmx-quittungen-sub">CHF ' . \esc_html(cmx_format_swiss_number($paid_sum, 2)) . '</div>';
		echo '  </div>';
		echo '  <div class="cmx-quittungen-box">';
		echo '    <div class="cmx-quittungen-title">Offen</div>';
		echo '    <div class="cmx-quittungen-val">' . \esc_html(cmx_format_swiss_number($open_count, 0)) . '</div>';
		echo '    <div class="cmx-quittungen-sub">CHF ' . \esc_html(cmx_format_swiss_number($open_sum, 2)) . '</div>';
		echo '  </div>';
		echo '</div>';
	}
}
