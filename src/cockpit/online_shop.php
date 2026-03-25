<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_online_shop_beleg_paid_date')) {
	function cmx_online_shop_beleg_paid_date(int $post_id): string {
		$meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM')
			: '_cmx_beleg_bezahlt_am';
		$raw = \trim((string) \get_post_meta($post_id, $meta_key, true));

		return \preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) ? $raw : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_online_shop_beleg_is_paid')) {
	function cmx_online_shop_beleg_is_paid(int $post_id): bool {
		if (cmx_online_shop_beleg_paid_date($post_id) !== '') {
			return true;
		}

		$status_meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_STATUS')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_STATUS')
			: '_cmx_beleg_status';

		return \trim((string) \get_post_meta($post_id, $status_meta_key, true)) === 'bezahlt';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_online_shop_amount_display')) {
	function cmx_online_shop_amount_display(int $post_id): string {
		$display = \function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_amount_display')
			? \trim((string) cmxbu_get_beleg_amount_display($post_id))
			: '';

		if ($display === '') {
			return '';
		}

		$display = (string) \preg_replace('/\s+[A-Z]{3}$/', '', $display);
		return \trim($display);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_online_shop_beleg_number')) {
	function cmx_online_shop_beleg_number(int $post_id, \WP_Post $post): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_ensure_rechnungsnummer')) {
			$number = \trim((string) cmx_ensure_rechnungsnummer($post_id));
			if ($number !== '') {
				return $number;
			}
		}

		$number = \trim((string) \get_post_meta($post_id, '_cmx_rechnungsnummer', true));
		if ($number !== '') {
			return $number;
		}

		$title = \trim((string) $post->post_title);
		return $title !== '' ? $title : ('Beleg #' . $post_id);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_online_shop_beleg_date_display')) {
	function cmx_online_shop_beleg_date_display(int $post_id, \WP_Post $post): string {
		$date_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_RNG_DATUM')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_RNG_DATUM')
			: '_cmx_beleg_rng_datum';
		$raw = \trim((string) \get_post_meta($post_id, $date_key, true));
		if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
			$ts = \strtotime($raw);
			if ($ts) {
				return \wp_date('d.m.Y', $ts);
			}
		}

		return \get_post_time('d.m.Y', false, $post);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_online_shop_widget_items')) {
	function cmx_online_shop_widget_items(): array {
		$meta_key = \function_exists(__NAMESPACE__ . '\\cmx_woocommerce_webhook_beleg_meta_key')
			? cmx_woocommerce_webhook_beleg_meta_key()
			: 'cmx_woo_webhook';

		$query = new \WP_Query([
			'post_type'              => 'belege',
			'post_status'            => ['publish', 'private'],
			'posts_per_page'         => 10,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
			'meta_query'             => [
				[
					'key'     => $meta_key,
					'value'   => '1',
					'compare' => '=',
				],
			],
		]);

		$items = [];
		foreach ((array) $query->posts as $post) {
			if (!$post instanceof \WP_Post) {
				continue;
			}

			$post_id = (int) $post->ID;
			$paid_date = cmx_online_shop_beleg_paid_date($post_id);
			$is_paid = cmx_online_shop_beleg_is_paid($post_id);
			$items[] = [
				'post_id'            => $post_id,
				'number'             => cmx_online_shop_beleg_number($post_id, $post),
				'edit_link'          => (string) \get_edit_post_link($post_id, ''),
				'date_display'       => cmx_online_shop_beleg_date_display($post_id, $post),
				'amount_display'     => cmx_online_shop_amount_display($post_id),
				'is_paid'            => $is_paid,
			];
		}

		return $items;
	}
}

\add_action('wp_dashboard_setup', __NAMESPACE__ . '\\cmx_register_online_shop_widget');
function cmx_register_online_shop_widget(): void {
	if (!\current_user_can('edit_posts')) {
		return;
	}

	\wp_add_dashboard_widget(
		'cmx_online_shop_widget',
		'Online Shop',
		__NAMESPACE__ . '\\cmx_render_online_shop_widget'
	);
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_online_shop_widget')) {
	function cmx_render_online_shop_widget(): void {
		if (!\current_user_can('edit_posts')) {
			echo '<p>' . \esc_html__('Keine Berechtigung.', 'default') . '</p>';
			return;
		}

		$items = cmx_online_shop_widget_items();
		if ($items === []) {
			echo '<p>' . \esc_html__('Keine WooCommerce-Rechnungen vorhanden.', 'cmx-misbuero') . '</p>';
			return;
		}

		echo '<style>
			#cmx_online_shop_widget .inside{margin:0;padding:10px 12px 12px;background:#fff}
			#cmx_online_shop_widget .cmx-online-shop-table{width:100%;border-collapse:collapse;table-layout:fixed}
			#cmx_online_shop_widget .cmx-online-shop-table th,#cmx_online_shop_widget .cmx-online-shop-table td{padding:8px 6px;border-bottom:1px solid #eef1f5;text-align:left;vertical-align:middle}
			#cmx_online_shop_widget .cmx-online-shop-table thead th{font-size:11px;line-height:1.2;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#667085}
			#cmx_online_shop_widget .cmx-online-shop-table tbody tr:last-child td{border-bottom:none}
			#cmx_online_shop_widget .cmx-online-shop-number{display:inline-block;max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:700;color:#111827;text-decoration:none}
			#cmx_online_shop_widget .cmx-online-shop-number:hover{text-decoration:underline}
			#cmx_online_shop_widget .cmx-online-shop-date{color:#475467;font-variant-numeric:tabular-nums;white-space:nowrap}
			#cmx_online_shop_widget .cmx-online-shop-col-amount{text-align:right}
			#cmx_online_shop_widget .cmx-online-shop-amount{display:inline-block;min-width:7ch;text-align:right;font-weight:800;font-variant-numeric:tabular-nums;white-space:nowrap}
			#cmx_online_shop_widget .cmx-online-shop-amount.is-paid{color:#1f7a1f}
			#cmx_online_shop_widget .cmx-online-shop-amount.is-open{color:#c2342b}
		</style>';

		echo '<table class="cmx-online-shop-table">';
		echo '<colgroup>';
		echo '<col>';
		echo '<col style="width:96px">';
		echo '<col style="width:96px">';
		echo '</colgroup>';
		echo '<thead><tr>';
		echo '<th>' . \esc_html__('BelegNr', 'cmx-misbuero') . '</th>';
		echo '<th>' . \esc_html__('Datum', 'cmx-misbuero') . '</th>';
		echo '<th class="cmx-online-shop-col-amount">' . \esc_html__('Betrag', 'cmx-misbuero') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($items as $item) {
			$amount_class = !empty($item['is_paid']) ? 'is-paid' : 'is-open';
			echo '<tr>';
			echo '<td>';
			if ((string) $item['edit_link'] !== '') {
				echo '<a class="cmx-online-shop-number" href="' . \esc_url((string) $item['edit_link']) . '">' . \esc_html((string) $item['number']) . '</a>';
			} else {
				echo '<span class="cmx-online-shop-number">' . \esc_html((string) $item['number']) . '</span>';
			}
			echo '</td>';
			echo '<td class="cmx-online-shop-date">' . \esc_html((string) $item['date_display']) . '</td>';
			echo '<td class="cmx-online-shop-col-amount">';
			echo '<span class="cmx-online-shop-amount ' . \esc_attr($amount_class) . '">' . \esc_html((string) $item['amount_display']) . '</span>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}
