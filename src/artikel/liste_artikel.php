<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_PT_ARTIKEL')) {
	\define(__NAMESPACE__ . '\\CMX_PT_ARTIKEL', 'artikel');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_uuid_option_key')) {
	function cmx_artikel_liste_uuid_option_key(): string {
		return 'cmx_artikel_liste_uuid';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_get_stable_uuid')) {
	function cmx_artikel_liste_get_stable_uuid(): string {
		$uuid = \trim((string) \get_option(cmx_artikel_liste_uuid_option_key(), ''));
		if ($uuid !== '') {
			return $uuid;
		}

		$uuid = \function_exists('\\wp_generate_uuid4')
			? (string) \wp_generate_uuid4()
			: '';
		if ($uuid === '') {
			return '';
		}

		\update_option(cmx_artikel_liste_uuid_option_key(), $uuid, false);
		return $uuid;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_matches_uuid')) {
	function cmx_artikel_liste_matches_uuid(string $uuid): bool {
		$uuid = \trim($uuid);
		if ($uuid === '') {
			return false;
		}

		return \hash_equals(cmx_artikel_liste_get_stable_uuid(), $uuid);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_url')) {
	function cmx_artikel_liste_url(): string {
		$uuid = cmx_artikel_liste_get_stable_uuid();
		if ($uuid === '') {
			return (string) \home_url('/katalog/');
		}

		return (string) \add_query_arg(['katalog' => $uuid], \home_url('/'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_meta_query')) {
	function cmx_artikel_liste_meta_query(): array {
		$verkaufbar_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VERKAUFBAR')
			? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VERKAUFBAR')
			: '_cmx_artikel_verkaufbar';
		$katalog_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_KATALOG')
			? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_KATALOG')
			: '_cmx_artikel_katalog';

		return [
			'relation' => 'AND',
			[
				'relation' => 'OR',
				[
					'key'     => $verkaufbar_key,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => $verkaufbar_key,
					'value'   => '',
					'compare' => '=',
				],
				[
					'key'     => $verkaufbar_key,
					'value'   => '0',
					'compare' => '=',
				],
				[
					'key'     => $verkaufbar_key,
					'value'   => 0,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
			],
			[
				'relation' => 'OR',
				[
					'key'     => $katalog_key,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => $katalog_key,
					'value'   => '',
					'compare' => '=',
				],
				[
					'key'     => $katalog_key,
					'value'   => '1',
					'compare' => '=',
				],
				[
					'key'     => $katalog_key,
					'value'   => 1,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
			],
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_taxonomy')) {
	function cmx_artikel_liste_taxonomy(string $kind): string {
		$kind = \sanitize_key($kind);

		if ($kind === 'einheit' && \function_exists(__NAMESPACE__ . '\\cmx_tax_einheiten')) {
			$tax = (string) cmx_tax_einheiten();
			if ($tax !== '') {
				return $tax;
			}
		}

		$candidates = [
			'einheit' => ['artikel_einheit', 'artikel_einheiten', 'einheit', 'einheiten'],
		];

		foreach ((array) ($candidates[$kind] ?? []) as $tax) {
			if (\taxonomy_exists($tax)) {
				return (string) $tax;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_price_raw')) {
	function cmx_artikel_liste_price_raw(int $artikel_id): float {
		$meta_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VK')
			? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VK')
			: '_cmx_artikel_vk';
		$raw = (string) \get_post_meta($artikel_id, $meta_key, true);
		$raw = \trim($raw);
		if ($raw === '') {
			return 0.0;
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_parse_number')) {
			return (float) cmx_parse_number($raw);
		}

		$normalized = \str_replace([' ', "'"], '', $raw);
		$normalized = \str_replace(',', '.', $normalized);
		return \is_numeric($normalized) ? (float) $normalized : 0.0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_price_label')) {
	function cmx_artikel_liste_price_label(float $price): string {
		if ($price <= 0) {
			return '';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')) {
			return (string) cmx_format_swiss_number($price, 2);
		}

		return \number_format($price, 2, '.', "'");
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_description_short')) {
	function cmx_artikel_liste_description_short(string $text, int $max_chars = 50): string {
		$text = \trim(\wp_strip_all_tags($text));
		if ($text === '') {
			return '';
		}

		$length = \function_exists('mb_strlen') ? \mb_strlen($text, 'UTF-8') : \strlen($text);
		if ($length <= $max_chars) {
			return $text;
		}

		$cut = \function_exists('mb_substr')
			? \mb_substr($text, 0, $max_chars, 'UTF-8')
			: \substr($text, 0, $max_chars);

		return \rtrim($cut) . '...';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_unit_label')) {
	function cmx_artikel_liste_unit_label(int $artikel_id): string {
		$tax = cmx_artikel_liste_taxonomy('einheit');
		if ($tax === '') {
			return '';
		}

		$terms = \wp_get_post_terms($artikel_id, $tax);
		if (\is_wp_error($terms) || empty($terms) || empty($terms[0]->name)) {
			return '';
		}

		return \trim((string) $terms[0]->name);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_description_pair')) {
	function cmx_artikel_liste_description_pair(int $artikel_id): array {
		$content_full = (string) \get_post_field('post_content', $artikel_id);
		$content_full = \trim(\wp_strip_all_tags($content_full));
		if ($content_full === '') {
			return ['', ''];
		}

		$short = cmx_artikel_liste_description_short($content_full, 110);
		return [$short, $content_full];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_rows')) {
	function cmx_artikel_liste_rows(): array {
		$q = new \WP_Query([
			'post_type'               => CMX_PT_ARTIKEL,
			'post_status'             => 'publish',
			'posts_per_page'          => -1,
			'fields'                  => 'ids',
			'no_found_rows'           => true,
			'update_post_meta_cache'  => false,
			'update_post_term_cache'  => false,
			'orderby'                 => 'title',
			'order'                   => 'ASC',
			'meta_query'              => cmx_artikel_liste_meta_query(),
		]);

		$rows = [];
		$sku_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_SKU')
			? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_SKU')
			: '_cmx_artikel_sku';

		foreach ((array) $q->posts as $artikel_id) {
			$artikel_id = (int) $artikel_id;
			if ($artikel_id <= 0) {
				continue;
			}

			$title = \trim((string) \get_the_title($artikel_id));
			if ($title === '') {
				$title = '#' . $artikel_id;
			}

			[$description_short, $description_full] = cmx_artikel_liste_description_pair($artikel_id);
			$price_raw = cmx_artikel_liste_price_raw($artikel_id);

			$rows[] = [
				'id' => $artikel_id,
				'title' => $title,
				'url' => \function_exists(__NAMESPACE__ . '\\cmx_artikel_detail_url')
					? (string) cmx_artikel_detail_url($artikel_id)
					: (string) \get_permalink($artikel_id),
				'sku' => \trim((string) \get_post_meta($artikel_id, $sku_key, true)),
				'description' => $description_short,
				'description_full' => $description_full,
				'unit' => cmx_artikel_liste_unit_label($artikel_id),
				'price_raw' => $price_raw,
				'price_label' => cmx_artikel_liste_price_label($price_raw),
				'image_url' => \trim((string) \get_post_meta($artikel_id, '_cmx_local_image_artikel_url', true)),
			];
		}

		return $rows;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_is_artikel_liste_request')) {
	function cmx_is_artikel_liste_request(): bool {
		if (\is_admin()) {
			return false;
		}

		$uuid = isset($_GET['katalog']) ? \sanitize_text_field((string) \wp_unslash($_GET['katalog'])) : '';
		if ($uuid !== '' && cmx_artikel_liste_matches_uuid($uuid)) {
			return true;
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_is_katalog_request')) {
			return cmx_is_katalog_request();
		}

		$path = \parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), \PHP_URL_PATH);
		$path = \is_string($path) ? \trim($path, '/') : '';
		return $path === 'katalog';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_artikel_liste_page')) {
	function cmx_render_artikel_liste_page(): void {
		if (!cmx_is_artikel_liste_request()) {
			return;
		}

		$rows = cmx_artikel_liste_rows();
		$reload_url = cmx_artikel_liste_url();
		$me_logo_url = \function_exists(__NAMESPACE__ . '\\cmx_artikel_detail_me_logo_url')
			? (string) cmx_artikel_detail_me_logo_url()
			: '';
		$me_contact_url = \function_exists(__NAMESPACE__ . '\\cmx_artikel_detail_me_contact_url')
			? (string) cmx_artikel_detail_me_contact_url()
			: '';
		$me_contact_title = \function_exists(__NAMESPACE__ . '\\cmx_artikel_detail_me_contact_title')
			? (string) cmx_artikel_detail_me_contact_title()
			: '';
		$show_sku_column = false;
		foreach ($rows as $row) {
			if (\trim((string) ($row['sku'] ?? '')) !== '') {
				$show_sku_column = true;
				break;
			}
		}

		while (\ob_get_level()) {
			\ob_end_clean();
		}

		if (!\defined('DONOTCACHEPAGE')) {
			\define('DONOTCACHEPAGE', true);
		}
		\nocache_headers();
		\status_header(200);
		$cart_storage_key = 'cmxArtikelKatalogCart';
		$show_cart_widget = \is_user_logged_in();
		$can_create_beleg = \is_user_logged_in()
			&& \post_type_exists('belege')
			&& \function_exists(__NAMESPACE__ . '\\cmx_post_type_can_create')
			&& \function_exists(__NAMESPACE__ . '\\cmx_post_type_can_publish')
			&& cmx_post_type_can_create('belege')
			&& cmx_post_type_can_publish('belege');
		$create_beleg_action_url = $can_create_beleg ? (string) \admin_url('admin-post.php') : '';
		$create_beleg_nonce = $can_create_beleg ? (string) \wp_create_nonce('cmx_artikel_create_beleg_cart') : '';

		echo '<!doctype html><html lang="de"><head><meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>Katalog</title>';
		echo '<link rel="stylesheet" href="' . \esc_url(\includes_url('css/dashicons.min.css')) . '">';
		echo '<style>
			:root{color-scheme:light}
			*{box-sizing:border-box}
			body{margin:0;font-family:Segoe UI,Roboto,Arial,sans-serif;background:#efefef;color:#1d2327}
			.cmx-artikel-page{max-width:1570px;margin:0 auto;padding:32px 18px 40px}
			.cmx-artikel-card{background:#fff;border:1px solid #ddd;border-radius:14px;box-shadow:0 18px 40px rgba(0,0,0,.06);overflow:hidden}
			.cmx-artikel-head{padding:24px 28px 18px;background:linear-gradient(135deg,#f7f7f7 0%,#ededed 100%);border-bottom:1px solid #e2e2e2}
			.cmx-artikel-head-inner{display:flex;align-items:flex-start;justify-content:space-between;gap:24px}
			.cmx-artikel-head-copy{flex:1 1 auto;min-width:0}
			.cmx-artikel-head-brand{flex:0 0 auto;display:flex;align-items:flex-start;justify-content:flex-end;gap:16px;min-height:84px}
			.cmx-artikel-head-logo{display:block;max-width:190px;max-height:84px;width:auto;height:auto;object-fit:contain;object-position:right top}
			.cmx-artikel-kicker{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;margin:0 0 8px}
			.cmx-artikel-kicker a{color:inherit;text-decoration:none}
			.cmx-artikel-kicker a:hover{color:#1d2327}
			.cmx-artikel-title{margin:0;font-size:30px;line-height:1.1}
			.cmx-artikel-sub{margin:8px 0 0;color:#6b7280;font-size:14px}
			.cmx-artikel-sub a{color:inherit;text-decoration:none}
			.cmx-artikel-sub a:hover{color:#1d2327}
			.cmx-artikel-cart-widget{position:relative;display:flex;align-items:flex-start;justify-content:flex-end}
			.cmx-artikel-cart-trigger{position:relative;display:inline-flex;align-items:center;justify-content:center;width:88px;height:88px;border:1px solid #d7e0ea;border-radius:18px;background:#fff;color:#135e96;cursor:pointer;box-shadow:0 10px 24px rgba(15,23,42,.10);transition:background .16s ease,border-color .16s ease,transform .16s ease}
			.cmx-artikel-cart-trigger:hover,.cmx-artikel-cart-trigger:focus{background:#f2f8ff;border-color:#9abfe1;color:#0f5f9a;transform:translateY(-1px)}
			.cmx-artikel-cart-trigger .dashicons{font-size:38px;width:38px;height:38px;line-height:38px}
			.cmx-artikel-cart-badge{position:absolute;top:-7px;right:-7px;display:none;min-width:24px;height:24px;padding:0 6px;border-radius:999px;background:#b32d2e;color:#fff;font-size:12px;font-weight:700;line-height:24px;text-align:center}
			.cmx-artikel-cart-widget.has-items .cmx-artikel-cart-badge{display:inline-block}
			.cmx-artikel-cart-flyout{position:absolute;top:calc(100% + 12px);right:0;display:none;width:min(360px,calc(100vw - 48px));padding:10px;background:#fff;border:1px solid #d7e0ea;border-radius:16px;box-shadow:0 20px 40px rgba(15,23,42,.16);z-index:60}
			.cmx-artikel-cart-widget:hover .cmx-artikel-cart-flyout,.cmx-artikel-cart-widget:focus-within .cmx-artikel-cart-flyout,.cmx-artikel-cart-widget.is-open .cmx-artikel-cart-flyout{display:block}
			.cmx-artikel-cart-list{margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:8px;max-height:320px;overflow:auto}
			.cmx-artikel-cart-item{display:flex;align-items:center;gap:10px;padding:8px 10px;border:1px solid #edf0f3;border-radius:12px;background:#fafbfc}
			.cmx-artikel-cart-item-label{flex:1 1 auto;min-width:0;display:flex;flex-direction:column;gap:2px}
			.cmx-artikel-cart-item-title{font-size:14px;font-weight:600;line-height:1.35;color:#1d2327;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
			.cmx-artikel-cart-item-qty{font-size:12px;line-height:1.2;color:#6b7280}
			.cmx-artikel-cart-remove{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;width:32px;height:32px;border:0;border-radius:999px;background:#fff;color:#d63638;cursor:pointer;transition:background .16s ease,color .16s ease}
			.cmx-artikel-cart-remove:hover,.cmx-artikel-cart-remove:focus{background:#fde8e7;color:#b32d2e}
			.cmx-artikel-cart-remove .dashicons{font-size:18px;width:18px;height:18px;line-height:18px}
			.cmx-artikel-cart-empty{padding:10px 12px;color:#6b7280;font-size:14px;line-height:1.5}
			.cmx-artikel-tools{padding:18px 28px 0}
			.cmx-artikel-tools input{width:100%;max-width:340px;padding:10px 12px;border:1px solid #c8c8c8;border-radius:10px;font:inherit}
			.cmx-artikel-table-wrap{padding:18px 28px 28px;overflow-x:auto}
			.cmx-artikel-table{width:100%;border-collapse:collapse;min-width:860px}
			.cmx-artikel-table th,.cmx-artikel-table td{padding:10px 10px;border-bottom:1px solid #ececec;text-align:left;vertical-align:middle;line-height:1.2}
			.cmx-artikel-table th{font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:#6b7280;background:#fafafa}
			.cmx-artikel-table th button{all:unset;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:inherit}
			.cmx-artikel-table th button:hover{color:#1d2327}
			.cmx-artikel-sort-indicator{font-size:11px;opacity:.55;min-width:10px;text-align:center}
				.cmx-artikel-table tbody tr:nth-child(even){background:#fcfcfc}
				.cmx-artikel-table tbody tr:hover{background:#eaf5ff}
				.cmx-artikel-table tbody tr.cmx-artikel-row-active{background:#d9ebff !important}
				.cmx-artikel-table tbody tr.cmx-artikel-row-active td{background:#d9ebff !important;box-shadow:inset 0 1px 0 #2271b1,inset 0 -1px 0 #2271b1}
				.cmx-artikel-table tbody tr.cmx-artikel-row-active td:first-child{box-shadow:inset 1px 0 0 #2271b1,inset 0 1px 0 #2271b1,inset 0 -1px 0 #2271b1}
				.cmx-artikel-table tbody tr.cmx-artikel-row-active td:last-child{box-shadow:inset -1px 0 0 #2271b1,inset 0 1px 0 #2271b1,inset 0 -1px 0 #2271b1}
				.cmx-artikel-table tbody tr.cmx-artikel-row-active:hover{background:#d9ebff !important}
			.cmx-artikel-table a{color:#135e96;text-decoration:none;font-weight:600}
			.cmx-artikel-table a:hover{color:#0a4b79}
			.cmx-artikel-price,.cmx-artikel-sku,.cmx-artikel-unit{white-space:nowrap}
			.cmx-artikel-table td.cmx-artikel-price,.cmx-artikel-table th.cmx-artikel-price-head{text-align:right;font-variant-numeric:tabular-nums}
			.cmx-artikel-price-head button{justify-content:flex-end;width:100%}
			.cmx-artikel-desc{min-width:320px}
			.cmx-artikel-thumb-wrap{width:76px}
			.cmx-artikel-thumb{display:block;width:60px;height:60px;object-fit:contain;border-radius:10px;border:1px solid #e0e0e0;background:#fff;padding:4px}
			.cmx-artikel-thumb-placeholder{display:block;width:60px;height:60px;border-radius:10px;border:1px dashed #d4d4d4;background:#f4f4f4}
			.cmx-artikel-empty{padding:28px;color:#6b7280}
			@media (max-width:720px){
				.cmx-artikel-page{padding:18px 12px 24px}
				.cmx-artikel-head-inner{flex-direction:column}
				.cmx-artikel-head-brand{justify-content:flex-start;min-height:0}
				.cmx-artikel-head,.cmx-artikel-tools,.cmx-artikel-table-wrap{padding-left:16px;padding-right:16px}
				.cmx-artikel-title{font-size:24px}
				.cmx-artikel-cart-flyout{left:0;right:auto;width:min(340px,calc(100vw - 32px))}
				.cmx-artikel-thumb-wrap{width:68px}
				.cmx-artikel-thumb,.cmx-artikel-thumb-placeholder{width:52px;height:52px}
			}
		</style>';
		echo '</head><body>';
		echo '<div class="cmx-artikel-page"><div class="cmx-artikel-card">';
		echo '<div class="cmx-artikel-head">';
		echo '<div class="cmx-artikel-head-inner">';
		echo '<div class="cmx-artikel-head-copy">';
		echo '<p class="cmx-artikel-kicker"><a href="' . \esc_url($reload_url) . '">Artikelübersicht</a></p>';
		echo '<h1 class="cmx-artikel-title">Katalog</h1>';
		echo '<p class="cmx-artikel-sub"><a href="' . \esc_url($reload_url) . '" id="cmx-artikel-count">' . \esc_html(\count($rows) . ' Artikel') . '</a></p>';
		echo '</div>';
		if ($me_logo_url !== '' || $show_cart_widget) {
			echo '<div class="cmx-artikel-head-brand">';
			if ($me_logo_url !== '') {
				if ($me_contact_url !== '') {
					echo '<a href="' . \esc_url($me_contact_url) . '" target="_blank" rel="noopener noreferrer" title="' . \esc_attr($me_contact_title) . '">';
				}
				echo '<img class="cmx-artikel-head-logo" src="' . \esc_url($me_logo_url) . '" alt="Das bin ich Logo">';
				if ($me_contact_url !== '') {
					echo '</a>';
				}
			}
			if ($show_cart_widget) {
				echo '<div class="cmx-artikel-cart-widget" id="cmx-artikel-cart-widget">';
				echo '<button type="button" class="cmx-artikel-cart-trigger" id="cmx-artikel-cart-trigger" aria-label="Vorgemerkte Artikel anzeigen" aria-expanded="false">';
				echo '<span class="dashicons dashicons-cart" aria-hidden="true"></span>';
				echo '<span class="cmx-artikel-cart-badge" id="cmx-artikel-cart-badge" aria-hidden="true"></span>';
				echo '</button>';
				echo '<div class="cmx-artikel-cart-flyout" id="cmx-artikel-cart-flyout">';
				echo '<ul class="cmx-artikel-cart-list" id="cmx-artikel-cart-list"></ul>';
				echo '<div class="cmx-artikel-cart-empty" id="cmx-artikel-cart-empty">Keine Artikel im Warenkorb</div>';
				echo '</div>';
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';
		echo '</div>';

		if (empty($rows)) {
			echo '<div class="cmx-artikel-empty">Aktuell keine Artikel gefunden.</div>';
			echo '</div></div></body></html>';
			exit;
		}

		echo '<div class="cmx-artikel-tools">';
		echo '<input type="search" id="cmx-artikel-search" placeholder="Artikel durchsuchen">';
		echo '</div>';
		if ($can_create_beleg && $create_beleg_action_url !== '' && $create_beleg_nonce !== '') {
			echo '<form method="post" action="' . \esc_url($create_beleg_action_url) . '" id="cmx-artikel-cart-create-form" style="display:none">';
			echo '<input type="hidden" name="action" value="cmx_artikel_create_beleg">';
			echo '<input type="hidden" name="_wpnonce" value="' . \esc_attr($create_beleg_nonce) . '">';
			echo '<div id="cmx-artikel-cart-create-fields"></div>';
			echo '</form>';
		}
		echo '<div class="cmx-artikel-table-wrap">';
		echo '<table class="cmx-artikel-table"><thead><tr>';
		echo '<th>Bild</th>';
		if ($show_sku_column) {
			echo '<th><button type="button" data-sort-key="sku" data-sort-type="string">SKU<span class="cmx-artikel-sort-indicator"> </span></button></th>';
		}
		echo '<th><button type="button" data-sort-key="title" data-sort-type="string">Artikel<span class="cmx-artikel-sort-indicator"> </span></button></th>';
		echo '<th><button type="button" data-sort-key="description" data-sort-type="string">Beschreibung<span class="cmx-artikel-sort-indicator"> </span></button></th>';
		echo '<th><button type="button" data-sort-key="unit" data-sort-type="string">Einheit<span class="cmx-artikel-sort-indicator"> </span></button></th>';
		echo '<th class="cmx-artikel-price-head"><button type="button" data-sort-key="price" data-sort-type="number">CHF<span class="cmx-artikel-sort-indicator"> </span></button></th>';
		echo '</tr></thead><tbody id="cmx-artikel-table-body">';

		foreach ($rows as $row) {
			$title = (string) ($row['title'] ?? '');
			$url = (string) ($row['url'] ?? '');
			$sku = (string) ($row['sku'] ?? '');
			$description = (string) ($row['description'] ?? '');
			$description_full = (string) ($row['description_full'] ?? '');
			$unit = (string) ($row['unit'] ?? '');
			$price_raw = (float) ($row['price_raw'] ?? 0.0);
			$price_label = (string) ($row['price_label'] ?? '');
			$image_url = (string) ($row['image_url'] ?? '');
			$search_blob = \implode(' ', \array_filter([
				$sku,
				$title,
				$description_full,
				$description,
				$unit,
				$price_label,
				(string) $price_raw,
			]));

			echo '<tr data-search="' . \esc_attr(\function_exists('mb_strtolower') ? \mb_strtolower($search_blob, 'UTF-8') : \strtolower($search_blob)) . '"'
				. ' data-sort-sku="' . \esc_attr(\function_exists('mb_strtolower') ? \mb_strtolower($sku, 'UTF-8') : \strtolower($sku)) . '"'
				. ' data-sort-title="' . \esc_attr(\function_exists('mb_strtolower') ? \mb_strtolower($title, 'UTF-8') : \strtolower($title)) . '"'
				. ' data-sort-description="' . \esc_attr(\function_exists('mb_strtolower') ? \mb_strtolower($description_full !== '' ? $description_full : $description, 'UTF-8') : \strtolower($description_full !== '' ? $description_full : $description)) . '"'
				. ' data-sort-unit="' . \esc_attr(\function_exists('mb_strtolower') ? \mb_strtolower($unit, 'UTF-8') : \strtolower($unit)) . '"'
				. ' data-sort-price="' . \esc_attr(\number_format($price_raw, 2, '.', '')) . '">';

			echo '<td class="cmx-artikel-thumb-wrap">';
			if ($url !== '' && $image_url !== '') {
				echo '<a href="' . \esc_url($url) . '" data-detail-url="' . \esc_attr($url) . '" title="Artikel anzeigen"><img src="' . \esc_url($image_url) . '" alt="' . \esc_attr($title) . '" class="cmx-artikel-thumb"></a>';
			} elseif ($image_url !== '') {
				echo '<img src="' . \esc_url($image_url) . '" alt="' . \esc_attr($title) . '" class="cmx-artikel-thumb">';
			} elseif ($url !== '') {
				echo '<a href="' . \esc_url($url) . '" data-detail-url="' . \esc_attr($url) . '" title="Artikel anzeigen"><span class="cmx-artikel-thumb-placeholder" aria-hidden="true"></span></a>';
			} else {
				echo '<span class="cmx-artikel-thumb-placeholder" aria-hidden="true"></span>';
			}
			echo '</td>';

			if ($show_sku_column) {
				echo '<td class="cmx-artikel-sku">';
				if ($url !== '' && $sku !== '') {
					echo '<a href="' . \esc_url($url) . '" data-detail-url="' . \esc_attr($url) . '" title="Artikel anzeigen">' . \esc_html($sku) . '</a>';
				} else {
					echo \esc_html($sku);
				}
				echo '</td>';
			}

			echo '<td>';
			if ($url !== '') {
				echo '<a href="' . \esc_url($url) . '" data-detail-url="' . \esc_attr($url) . '" title="Artikel anzeigen">' . \esc_html($title) . '</a>';
			} else {
				echo \esc_html($title);
			}
			echo '</td>';

			echo '<td class="cmx-artikel-desc">' . \esc_html($description) . '</td>';
			echo '<td class="cmx-artikel-unit">' . \esc_html($unit) . '</td>';
			echo '<td class="cmx-artikel-price">' . \esc_html($price_label) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
		echo '</div></div>';
			echo '<script>
				(function(){
					var input=document.getElementById("cmx-artikel-search");
					var body=document.getElementById("cmx-artikel-table-body");
					var countNode=document.getElementById("cmx-artikel-count");
					var sortButtons=document.querySelectorAll(".cmx-artikel-table thead button[data-sort-key]");
					var cartWidget=document.getElementById("cmx-artikel-cart-widget");
					var cartTrigger=document.getElementById("cmx-artikel-cart-trigger");
					var cartList=document.getElementById("cmx-artikel-cart-list");
					var cartEmpty=document.getElementById("cmx-artikel-cart-empty");
					var cartBadge=document.getElementById("cmx-artikel-cart-badge");
					var createForm=document.getElementById("cmx-artikel-cart-create-form");
					var createFields=document.getElementById("cmx-artikel-cart-create-fields");
					var cartStorageKey=' . \wp_json_encode($cart_storage_key) . ';
					var canCreate=' . ($can_create_beleg ? 'true' : 'false') . ';
					var activeRow=null;
					if(!body){return;}
					function normalize(txt){return String(txt||"").toLowerCase().trim();}
					function normalizeCart(items){
						var merged=[];
						var lookup={};
						(items||[]).forEach(function(entry){
							var id=0;
							var count=1;
							var title="";
							if(entry && typeof entry==="object"){
								id=parseInt(entry.id || entry.artikelId || 0, 10) || 0;
								count=parseInt(entry.count || entry.qty || entry.menge || 1, 10) || 1;
								title=String(entry.title || "");
							}else{
								id=parseInt(entry || 0, 10) || 0;
							}
							if(id<=0 || count<=0){return;}
							if(!lookup[id]){
								lookup[id]={id:id,count:0,title:title};
								merged.push(lookup[id]);
							}
							lookup[id].count += count;
							if(!lookup[id].title && title){
								lookup[id].title=title;
							}
						});
						return merged;
					}
					function readCart(){
						var raw="";
						try{
							raw=window.localStorage ? String(window.localStorage.getItem(cartStorageKey) || "") : "";
						}catch(err){
							raw="";
						}
						if(!raw){return [];}
						try{
							return normalizeCart(JSON.parse(raw));
						}catch(err){
							return [];
						}
					}
					function writeCart(items){
						var normalized=normalizeCart(items);
						try{
							if(window.localStorage){
								if(normalized.length){
									window.localStorage.setItem(cartStorageKey, JSON.stringify(normalized));
								}else{
									window.localStorage.removeItem(cartStorageKey);
								}
							}
						}catch(err){}
						return normalized;
					}
					function cartTotal(items){
						return (items||[]).reduce(function(sum, item){
							return sum + (parseInt(item && item.count ? item.count : 0, 10) || 0);
						}, 0);
					}
					function syncCartBadge(items){
						var total=cartTotal(items);
						if(!cartWidget || !cartBadge){return;}
						if(total > 0){
							cartWidget.classList.add("has-items");
							cartBadge.textContent=total > 99 ? "99+" : String(total);
							return;
						}
						cartWidget.classList.remove("has-items");
						cartBadge.textContent="";
					}
					function renderCart(){
						var items;
						if(!cartList || !cartEmpty){return;}
						items=readCart();
						cartList.innerHTML="";
						syncCartBadge(items);
						if(!items.length){
							cartEmpty.style.display="block";
							return;
						}
						cartEmpty.style.display="none";
						items.forEach(function(item){
							var entry=document.createElement("li");
							var label=document.createElement("div");
							var title=document.createElement("span");
							var remove=document.createElement("button");
							entry.className="cmx-artikel-cart-item";
							entry.setAttribute("data-artikel-id", String(item.id));
							label.className="cmx-artikel-cart-item-label";
							title.className="cmx-artikel-cart-item-title";
							title.textContent=String(item.title || ("Artikel #" + String(item.id)));
							label.appendChild(title);
							if((parseInt(item.count, 10) || 0) > 1){
								var qty=document.createElement("span");
								qty.className="cmx-artikel-cart-item-qty";
								qty.textContent=String(item.count) + "x";
								label.appendChild(qty);
							}
							remove.type="button";
							remove.className="cmx-artikel-cart-remove";
							remove.setAttribute("data-artikel-id", String(item.id));
							remove.setAttribute("aria-label", "Artikel entfernen");
							remove.innerHTML="<span class=\"dashicons dashicons-trash\" aria-hidden=\"true\"></span>";
							entry.appendChild(label);
							entry.appendChild(remove);
							cartList.appendChild(entry);
						});
					}
					function submitCreateFromCart(){
						var items;
						if(!canCreate || !createForm || !createFields){return;}
						items=readCart();
						if(!items.length){return;}
						createFields.innerHTML="";
						items.forEach(function(item){
							var idField=document.createElement("input");
							var qtyField=document.createElement("input");
							idField.type="hidden";
							idField.name="artikel_ids[]";
							idField.value=String(item.id);
							createFields.appendChild(idField);
							qtyField.type="hidden";
							qtyField.name="artikel_mengen[" + String(item.id) + "]";
							qtyField.value=String(item.count);
							createFields.appendChild(qtyField);
						});
						createForm.submit();
					}
					function getRows(){return Array.prototype.slice.call(body.querySelectorAll("tr[data-search]"));}
					function getVisibleRows(){return getRows().filter(function(row){ return row.style.display!=="none"; });}
					function getDetailLink(row){return row ? row.querySelector("a[data-detail-url]") : null;}
					function getSelectableRows(){return getVisibleRows().filter(function(row){ return !!getDetailLink(row); });}
					function setActiveRow(row,scrollIntoView){
						if(activeRow && activeRow!==row){
							activeRow.classList.remove("cmx-artikel-row-active");
							activeRow.removeAttribute("aria-selected");
						}
						activeRow=row||null;
						if(!activeRow){return;}
						activeRow.classList.add("cmx-artikel-row-active");
						activeRow.setAttribute("aria-selected","true");
						if(scrollIntoView!==false){
							try{
								activeRow.scrollIntoView({block:"nearest",inline:"nearest"});
							}catch(e){
								activeRow.scrollIntoView(false);
							}
						}
					}
					function syncActiveRow(){
						var selectableRows=getSelectableRows();
						if(!selectableRows.length){
							setActiveRow(null,false);
							return;
						}
						if(activeRow && selectableRows.indexOf(activeRow)!==-1){
							return;
						}
						if(normalize(input?input.value:"")!==""){
							setActiveRow(selectableRows[0],false);
							return;
						}
						setActiveRow(null,false);
					}
					function moveActiveRow(delta){
						var selectableRows=getSelectableRows();
						var nextIndex;
						if(!selectableRows.length){return;}
						nextIndex=selectableRows.indexOf(activeRow);
						if(nextIndex===-1){
							setActiveRow(delta<0 ? selectableRows[selectableRows.length-1] : selectableRows[0]);
							return;
						}
						nextIndex+=delta;
						if(nextIndex<0){nextIndex=0;}
						if(nextIndex>=selectableRows.length){nextIndex=selectableRows.length-1;}
						setActiveRow(selectableRows[nextIndex]);
					}
					function openActiveRow(){
						var row=activeRow;
						var link;
						if(!row){
							var selectableRows=getSelectableRows();
							if(!selectableRows.length){return;}
							if(selectableRows.length===1 || normalize(input?input.value:"")!==""){
								row=selectableRows[0];
							}
						}
						link=getDetailLink(row);
						if(link && link.href){
							window.location.href=link.href;
						}
					}
					function updateCount(){
						if(!countNode){return;}
						var visible=getVisibleRows().length;
						countNode.textContent=visible + (visible===1 ? " Artikel" : " Artikel");
					}
					function applyFilter(){
						var term=normalize(input?input.value:"");
						getRows().forEach(function(row){
							var hay=(row.getAttribute("data-search")||"") + " " + normalize(row.textContent||"");
							row.style.display=(term===""||hay.indexOf(term)!==-1)?"":"none";
						});
						syncActiveRow();
						updateCount();
					}
				function currentSortType(key){
					var activeButton=document.querySelector(".cmx-artikel-table thead button[data-sort-key=\\"" + key + "\\"]");
					return activeButton ? (activeButton.getAttribute("data-sort-type")||"string") : "string";
				}
				function syncDetailLinks(key,dir){
					var links=body.querySelectorAll("a[data-detail-url]");
					Array.prototype.forEach.call(links,function(link){
						var base=link.getAttribute("data-detail-url")||"";
						if(!base){return;}
						try{
							var url=new URL(base, window.location.href);
							url.searchParams.set("sort_key", key);
							url.searchParams.set("sort_dir", dir);
							link.href=url.toString();
						}catch(e){}
					});
				}
				function updateSortQuery(key,dir){
					try{
						var url=new URL(window.location.href);
						url.searchParams.set("sort_key", key);
						url.searchParams.set("sort_dir", dir);
						window.history.replaceState(null,"",url.toString());
					}catch(e){}
					syncDetailLinks(key,dir);
				}
				function setActiveSort(key,dir){
					Array.prototype.forEach.call(sortButtons,function(other){
						var active=(other.getAttribute("data-sort-key")||"")===key;
						other.setAttribute("data-sort-dir", active ? dir : "");
						var indicator=other.querySelector(".cmx-artikel-sort-indicator");
						if(indicator){ indicator.textContent = active ? (dir==="asc" ? "▲" : "▼") : " "; }
					});
				}
					function sortRows(key,type,dir){
						var rows=getRows();
						rows.sort(function(a,b){
						var av=a.getAttribute("data-sort-"+key)||"";
						var bv=b.getAttribute("data-sort-"+key)||"";
						if(type==="number"){
							av=parseFloat(av||"0")||0;
							bv=parseFloat(bv||"0")||0;
						}else{
							av=normalize(av);
							bv=normalize(bv);
						}
						if(av===bv){ return 0; }
						if(dir==="desc"){ return av < bv ? 1 : -1; }
						return av > bv ? 1 : -1;
						});
						rows.forEach(function(row){ body.appendChild(row); });
						applyFilter();
						syncDetailLinks(key,dir);
					}
					if(input){
						input.addEventListener("input",applyFilter);
						input.addEventListener("keydown",function(event){
							if(event.key==="ArrowDown"){
								event.preventDefault();
								moveActiveRow(1);
								return;
							}
							if(event.key==="ArrowUp"){
								event.preventDefault();
								moveActiveRow(-1);
								return;
							}
							if(event.key==="Enter"){
								if(activeRow || getSelectableRows().length===1 || normalize(input.value)!==""){
									event.preventDefault();
									openActiveRow();
								}
							}
						});
					}
					if(cartWidget){
						cartWidget.addEventListener("mouseenter", function(){
							renderCart();
							if(cartTrigger){
								cartTrigger.setAttribute("aria-expanded","true");
							}
						});
						cartWidget.addEventListener("mouseleave", function(){
							if(cartTrigger){
								cartTrigger.setAttribute("aria-expanded","false");
							}
						});
					}
					if(cartTrigger){
						cartTrigger.addEventListener("focus", function(){
							renderCart();
							cartTrigger.setAttribute("aria-expanded","true");
						});
						cartTrigger.addEventListener("blur", function(){
							window.setTimeout(function(){
								if(cartWidget && cartWidget.contains(document.activeElement)){return;}
								cartTrigger.setAttribute("aria-expanded","false");
							}, 80);
						});
						cartTrigger.addEventListener("dblclick", function(event){
							event.preventDefault();
							event.stopPropagation();
							submitCreateFromCart();
						});
					}
					if(cartList){
						cartList.addEventListener("click", function(event){
							var button=event.target.closest(".cmx-artikel-cart-remove");
							var artikelId;
							var items;
							if(!button){return;}
							event.preventDefault();
							event.stopPropagation();
							artikelId=parseInt(button.getAttribute("data-artikel-id") || "0", 10) || 0;
							if(artikelId <= 0){return;}
							items=readCart().filter(function(item){
								return (parseInt(item && item.id ? item.id : 0, 10) || 0) !== artikelId;
							});
							writeCart(items);
							renderCart();
						});
					}
					window.addEventListener("storage", function(event){
						if(!event || event.key !== cartStorageKey){return;}
						renderCart();
					});
				Array.prototype.forEach.call(sortButtons,function(btn){
					btn.addEventListener("click",function(){
						var key=btn.getAttribute("data-sort-key")||"";
						var type=btn.getAttribute("data-sort-type")||"string";
						var current=btn.getAttribute("data-sort-dir")||"";
						var next=current==="asc"?"desc":"asc";
						setActiveSort(key,next);
						sortRows(key,type,next);
						updateSortQuery(key,next);
					});
				});
					var params;
					try{
						params=new URL(window.location.href).searchParams;
					}catch(e){
						params={ get:function(){ return ""; } };
				}
				var initialKey=params.get("sort_key")||"title";
				var initialDir=params.get("sort_dir")==="desc"?"desc":"asc";
				if(!document.querySelector(".cmx-artikel-table thead button[data-sort-key=\\"" + initialKey + "\\"]")){
					initialKey="title";
					}
					setActiveSort(initialKey,initialDir);
					sortRows(initialKey,currentSortType(initialKey),initialDir);
					updateSortQuery(initialKey,initialDir);
					renderCart();
					updateCount();
					if(input && params.get("cmx_focus_search")==="1"){
						window.setTimeout(function(){
							try{
								input.focus({preventScroll:true});
							}catch(e){
								input.focus();
							}
							if((input.value||"")===""){
								input.select();
							}
						},30);
						try{
							var focusUrl=new URL(window.location.href);
							focusUrl.searchParams.delete("cmx_focus_search");
							window.history.replaceState(null,"",focusUrl.toString());
						}catch(e){}
					}
				})();
			</script>';
		echo '</body></html>';
		exit;
	}
}

\add_action('template_redirect', __NAMESPACE__ . '\\cmx_render_artikel_liste_page', 5);
