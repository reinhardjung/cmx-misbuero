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
	function cmx_online_shop_widget_items(int $limit = 10): array {
		$meta_key = \function_exists(__NAMESPACE__ . '\\cmx_woocommerce_webhook_beleg_meta_key')
			? cmx_woocommerce_webhook_beleg_meta_key()
			: 'cmx_woo_webhook';

		$query = new \WP_Query([
			'post_type'              => 'belege',
			'post_status'            => ['publish', 'private'],
			'posts_per_page'         => $limit > 0 ? $limit : -1,
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_online_shop_url')) {
	function cmx_online_shop_url(): string {
		return (string) \home_url('/onlineshop/');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_is_online_shop_request')) {
	function cmx_is_online_shop_request(): bool {
		if (\is_admin()) {
			return false;
		}

		$req_path = \parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), \PHP_URL_PATH);
		$req_path = \is_string($req_path) ? \trim($req_path, '/') : '';

		return $req_path === 'onlineshop' || \str_starts_with($req_path, 'onlineshop/') || \is_page('onlineshop');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_online_shop_page')) {
	function cmx_render_online_shop_page(): void {
		if (!cmx_is_online_shop_request()) {
			return;
		}

		if (!\is_user_logged_in()) {
			\wp_safe_redirect(\wp_login_url(cmx_online_shop_url()));
			exit;
		}
		if (!\current_user_can('edit_posts')) {
			\wp_die(\esc_html__('Keine Berechtigung.', 'default'), '', ['response' => 403]);
		}

		$items = cmx_online_shop_widget_items(200);
		$reload_url = cmx_online_shop_url();
		$settings_url = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
			? (string) \admin_url('admin.php?page=' . \constant(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG') . '&tab=woocommerce')
			: '';

		while (\ob_get_level()) {
			\ob_end_clean();
		}

		if (!\defined('DONOTCACHEPAGE')) {
			\define('DONOTCACHEPAGE', true);
		}
		\nocache_headers();
		\status_header(200);

		echo '<!doctype html><html lang="de"><head><meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>Onlineshop</title>';
		echo '<style>
			:root{color-scheme:light}
			*{box-sizing:border-box}
			body{margin:0;font-family:Segoe UI,Roboto,Arial,sans-serif;background:#efefef;color:#1d2327}
			.cmx-online-shop-page{max-width:1570px;margin:0 auto;padding:32px 18px 40px}
			.cmx-online-shop-card{background:#fff;border:1px solid #ddd;border-radius:14px;box-shadow:0 18px 40px rgba(0,0,0,.06);overflow:hidden}
			.cmx-online-shop-head{padding:24px 28px 18px;background:linear-gradient(135deg,#f7f7f7 0%,#ededed 100%);border-bottom:1px solid #e2e2e2}
			.cmx-online-shop-head-inner{display:flex;align-items:flex-start;justify-content:space-between;gap:24px}
			.cmx-online-shop-kicker{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;margin:0 0 8px}
			.cmx-online-shop-kicker a,.cmx-online-shop-sub a{color:inherit;text-decoration:none}
			.cmx-online-shop-kicker a:hover,.cmx-online-shop-sub a:hover{color:#1d2327}
			.cmx-online-shop-title{margin:0;font-size:30px;line-height:1.1}
			.cmx-online-shop-sub{margin:8px 0 0;color:#6b7280;font-size:14px}
			.cmx-online-shop-settings{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:0 14px;border:1px solid #c8c8c8;border-radius:8px;background:#fff;color:#1d2327;text-decoration:none;font-weight:700}
			.cmx-online-shop-settings:hover{background:#f6f7f7}
			.cmx-online-shop-tools{padding:18px 28px 0}
			.cmx-online-shop-tools input{width:100%;max-width:360px;padding:10px 12px;border:1px solid #c8c8c8;border-radius:10px;font:inherit}
			.cmx-online-shop-table-wrap{padding:18px 28px 28px;overflow-x:auto}
			.cmx-online-shop-table{width:100%;border-collapse:collapse;min-width:760px}
			.cmx-online-shop-table th,.cmx-online-shop-table td{padding:12px 10px;border-bottom:1px solid #ececec;text-align:left;vertical-align:middle;line-height:1.35}
			.cmx-online-shop-table th{font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:#6b7280;background:#fafafa}
			.cmx-online-shop-table tbody tr:nth-child(even){background:#fcfcfc}
			.cmx-online-shop-table tbody tr:hover{background:#eaf5ff}
			.cmx-online-shop-number{font-weight:700;color:#135e96;text-decoration:none}
			.cmx-online-shop-number:hover{color:#0a4b79}
			.cmx-online-shop-date,.cmx-online-shop-amount{font-variant-numeric:tabular-nums;white-space:nowrap}
			.cmx-online-shop-col-amount{text-align:right}
			.cmx-online-shop-amount{font-weight:800}
			.cmx-online-shop-amount.is-paid{color:#1f7a1f}
			.cmx-online-shop-amount.is-open{color:#c2342b}
			.cmx-online-shop-status{display:inline-flex;align-items:center;min-height:26px;padding:0 10px;border-radius:999px;font-size:12px;font-weight:800}
			.cmx-online-shop-status.is-paid{background:#e6f4ea;color:#1f7a1f}
			.cmx-online-shop-status.is-open{background:#fde8e7;color:#b32d2e}
			.cmx-online-shop-empty{padding:28px;color:#6b7280}
			@media (max-width:720px){
				.cmx-online-shop-page{padding:18px 12px 24px}
				.cmx-online-shop-head-inner{flex-direction:column}
				.cmx-online-shop-head,.cmx-online-shop-tools,.cmx-online-shop-table-wrap{padding-left:16px;padding-right:16px}
				.cmx-online-shop-title{font-size:24px}
			}
		</style>';
		echo '</head><body>';
		echo '<div class="cmx-online-shop-page"><div class="cmx-online-shop-card">';
		echo '<div class="cmx-online-shop-head"><div class="cmx-online-shop-head-inner">';
		echo '<div>';
		echo '<p class="cmx-online-shop-kicker"><a href="' . \esc_url($reload_url) . '">WooCommerce</a></p>';
		echo '<h1 class="cmx-online-shop-title">Onlineshop</h1>';
		echo '<p class="cmx-online-shop-sub"><a href="' . \esc_url($reload_url) . '" id="cmx-online-shop-count">' . \esc_html(\count($items) . ' Rechnungen') . '</a></p>';
		echo '</div>';
		if ($settings_url !== '' && \current_user_can('manage_options')) {
			echo '<a class="cmx-online-shop-settings" href="' . \esc_url($settings_url) . '">Einstellungen</a>';
		}
		echo '</div></div>';

		if ($items === []) {
			echo '<div class="cmx-online-shop-empty">Keine WooCommerce-Rechnungen vorhanden.</div>';
			echo '</div></div></body></html>';
			exit;
		}

		echo '<div class="cmx-online-shop-tools"><input type="search" id="cmx-online-shop-search" placeholder="Rechnungen durchsuchen"></div>';
		echo '<div class="cmx-online-shop-table-wrap"><table class="cmx-online-shop-table" id="cmx-online-shop-table">';
		echo '<thead><tr><th>BelegNr</th><th>Datum</th><th>Status</th><th class="cmx-online-shop-col-amount">Betrag</th></tr></thead><tbody>';
		foreach ($items as $item) {
			$is_paid = !empty($item['is_paid']);
			$status_class = $is_paid ? 'is-paid' : 'is-open';
			$status_label = $is_paid ? 'bezahlt' : 'offen';
			$search = \strtolower(\implode(' ', [
				(string) ($item['number'] ?? ''),
				(string) ($item['date_display'] ?? ''),
				$status_label,
				(string) ($item['amount_display'] ?? ''),
			]));
			echo '<tr data-search="' . \esc_attr($search) . '">';
			echo '<td>';
			if ((string) ($item['edit_link'] ?? '') !== '') {
				echo '<a class="cmx-online-shop-number" href="' . \esc_url((string) $item['edit_link']) . '">' . \esc_html((string) $item['number']) . '</a>';
			} else {
				echo '<span class="cmx-online-shop-number">' . \esc_html((string) $item['number']) . '</span>';
			}
			echo '</td>';
			echo '<td class="cmx-online-shop-date">' . \esc_html((string) $item['date_display']) . '</td>';
			echo '<td><span class="cmx-online-shop-status ' . \esc_attr($status_class) . '">' . \esc_html($status_label) . '</span></td>';
			echo '<td class="cmx-online-shop-col-amount"><span class="cmx-online-shop-amount ' . \esc_attr($status_class) . '">' . \esc_html((string) $item['amount_display']) . '</span></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		echo '<script>
			document.addEventListener("DOMContentLoaded",function(){
				var input=document.getElementById("cmx-online-shop-search");
				var rows=Array.prototype.slice.call(document.querySelectorAll("#cmx-online-shop-table tbody tr"));
				var count=document.getElementById("cmx-online-shop-count");
				if(!input)return;
				input.addEventListener("input",function(){
					var q=(input.value||"").toLowerCase().trim();
					var visible=0;
					rows.forEach(function(row){
						var match=!q || String(row.dataset.search||"").indexOf(q)>-1;
						row.style.display=match?"":"none";
						if(match)visible++;
					});
					if(count)count.textContent=visible+" Rechnungen";
				});
			});
		</script>';
		echo '</div></div></body></html>';
		exit;
	}
}
\add_action('template_redirect', __NAMESPACE__ . '\\cmx_render_online_shop_page', 5);

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
