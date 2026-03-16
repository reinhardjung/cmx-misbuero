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
				'title'              => (string) $post->post_title,
				'edit_link'          => (string) \get_edit_post_link($post_id, ''),
				'post_date_display'  => \get_post_time('d.m.Y', false, $post),
				'amount_display'     => \function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_amount_display')
					? \trim((string) cmxbu_get_beleg_amount_display($post_id))
					: '',
				'paid_date'          => $paid_date,
				'paid_date_display'  => $paid_date !== '' ? \wp_date('d.m.Y', \strtotime($paid_date)) : '',
				'is_paid'            => $is_paid,
				'can_mark_paid'      => \current_user_can('edit_post', $post_id),
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
			#cmx_online_shop_widget .cmx-online-shop-table{width:100%;border-collapse:collapse;table-layout:fixed}
			#cmx_online_shop_widget .cmx-online-shop-table th,#cmx_online_shop_widget .cmx-online-shop-table td{padding:8px 6px;text-align:left;vertical-align:middle;border-bottom:1px solid #f0f0f1}
			#cmx_online_shop_widget .cmx-online-shop-table th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#646970}
			#cmx_online_shop_widget .cmx-online-shop-title{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-decoration:none;font-weight:600}
			#cmx_online_shop_widget .cmx-online-shop-title:hover{text-decoration:underline}
			#cmx_online_shop_widget .cmx-online-shop-title-text{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:600}
			#cmx_online_shop_widget .cmx-online-shop-amount{font-weight:700}
			#cmx_online_shop_widget .cmx-online-shop-amount.is-paid{color:#1f7a1f}
			#cmx_online_shop_widget .cmx-online-shop-amount.is-open{color:#b32d2e}
			#cmx_online_shop_widget .cmx-online-shop-payment-cell{text-align:right}
			#cmx_online_shop_widget .cmx-online-shop-pay-control{display:flex;justify-content:flex-end;align-items:center;gap:8px}
			#cmx_online_shop_widget .cmx-online-shop-pay-control input[type="date"]{min-width:145px}
			#cmx_online_shop_widget .cmx-online-shop-open-text{font-size:12px;color:#b32d2e;font-weight:600}
			#cmx_online_shop_widget .cmx-online-shop-paid-text{font-size:12px;color:#1f7a1f;font-weight:700;white-space:nowrap}
			#cmx_online_shop_widget .cmx-online-shop-saving{opacity:.55;pointer-events:none}
		</style>';

		echo '<table class="cmx-online-shop-table">';
		echo '<colgroup>';
		echo '<col>';
		echo '<col style="width:90px;">';
		echo '<col style="width:120px;">';
		echo '<col style="width:210px;">';
		echo '</colgroup>';
		echo '<thead><tr>';
		echo '<th>' . \esc_html__('Bestell-Titel', 'cmx-misbuero') . '</th>';
		echo '<th>' . \esc_html__('Datum', 'cmx-misbuero') . '</th>';
		echo '<th>' . \esc_html__('Betrag', 'cmx-misbuero') . '</th>';
		echo '<th>' . \esc_html__('Zahlung', 'cmx-misbuero') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($items as $item) {
			$post_id = (int) $item['post_id'];
			$title = (string) ($item['title'] !== '' ? $item['title'] : ('#' . $post_id));
			$edit_link = (string) $item['edit_link'];
			$amount_class = !empty($item['is_paid']) ? 'is-paid' : 'is-open';

			echo '<tr data-beleg-id="' . $post_id . '">';
			echo '<td>';
			if ($edit_link !== '') {
				echo '<a class="cmx-online-shop-title" href="' . \esc_url($edit_link) . '">' . \esc_html($title) . '</a>';
			} else {
				echo '<span class="cmx-online-shop-title-text">' . \esc_html($title) . '</span>';
			}
			echo '</td>';
			echo '<td>' . \esc_html((string) $item['post_date_display']) . '</td>';
			echo '<td class="cmx-online-shop-amount ' . \esc_attr($amount_class) . '">' . \esc_html((string) $item['amount_display']) . '</td>';
			echo '<td class="cmx-online-shop-payment-cell">';

			if (!empty($item['is_paid'])) {
				$paid_label = (string) ($item['paid_date_display'] !== '' ? $item['paid_date_display'] : __('Bezahlt', 'cmx-misbuero'));
				echo '<span class="cmx-online-shop-paid-text">' . \esc_html($paid_label) . '</span>';
			} elseif (!empty($item['can_mark_paid'])) {
				echo '<div class="cmx-online-shop-pay-control">';
				echo '<span class="cmx-online-shop-open-text">' . \esc_html__('offen', 'cmx-misbuero') . '</span>';
				echo '<input type="date" class="cmx-online-shop-paid-date" data-beleg="' . $post_id . '" value="">';
				echo '</div>';
			} else {
				echo '<span class="cmx-online-shop-open-text">' . \esc_html__('offen', 'cmx-misbuero') . '</span>';
			}

			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}

\add_action('admin_footer-index.php', function (): void {
	if (!\current_user_can('edit_posts')) {
		return;
	}

	$ajax_url = \admin_url('admin-ajax.php');
	$nonce = \wp_create_nonce('cmx_mark_paid');
	?>
	<script>
	(function(){
		var widget = document.getElementById('cmx_online_shop_widget');
		if (!widget) return;

		function escapeHtml(value){
			return String(value || '')
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		}

		function submitPaidDate(input){
			var belegId = parseInt(input.getAttribute('data-beleg') || '0', 10);
			var paidDate = String(input.value || '');
			var row = input.closest('tr');
			if (!belegId || !row || !/^\d{4}-\d{2}-\d{2}$/.test(paidDate)) return;

			var body = new URLSearchParams();
			body.set('action', 'cmx_mark_beleg_paid');
			body.set('post_id', String(belegId));
			body.set('paid_date', paidDate);
			body.set('_ajax_nonce', <?php echo \wp_json_encode($nonce); ?>);

			row.classList.add('cmx-online-shop-saving');

			fetch(<?php echo \wp_json_encode($ajax_url); ?>, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: body.toString()
			}).then(function(resp){
				return resp.json();
			}).then(function(resp){
				if (!resp || !resp.success || !resp.data) {
					throw new Error(resp && resp.data ? String(resp.data) : 'Fehler beim Speichern.');
				}

				var amountCell = row.querySelector('.cmx-online-shop-amount');
				var paymentCell = row.querySelector('.cmx-online-shop-payment-cell');
				if (amountCell) {
					amountCell.classList.remove('is-open');
					amountCell.classList.add('is-paid');
					if (resp.data.amount_display) {
						amountCell.textContent = String(resp.data.amount_display);
					}
				}
				if (paymentCell) {
					var label = resp.data.paid_date_display ? String(resp.data.paid_date_display) : 'Bezahlt';
					paymentCell.innerHTML = '<span class="cmx-online-shop-paid-text">' + escapeHtml(label) + '</span>';
				}
			}).catch(function(err){
				window.alert(err && err.message ? err.message : 'Fehler beim Speichern.');
			}).finally(function(){
				row.classList.remove('cmx-online-shop-saving');
			});
		}

		widget.addEventListener('change', function(event){
			var input = event.target && event.target.closest ? event.target.closest('.cmx-online-shop-paid-date') : null;
			if (!input) return;
			submitPaidDate(input);
		});
	})();
	</script>
	<?php
});
