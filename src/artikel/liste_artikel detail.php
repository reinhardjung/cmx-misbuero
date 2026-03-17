<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_detail_request_value')) {
	function cmx_artikel_detail_request_value(): string {
		if (!isset($_GET['artikel'])) {
			return '';
		}

		return \trim(\sanitize_text_field((string) \wp_unslash($_GET['artikel'])));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_is_artikel_detail_request')) {
	function cmx_is_artikel_detail_request(): bool {
		if (!\function_exists(__NAMESPACE__ . '\\cmx_is_artikel_liste_request')) {
			return false;
		}

		return cmx_is_artikel_liste_request() && cmx_artikel_detail_request_value() !== '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_detail_url')) {
	function cmx_artikel_detail_url(int $artikel_id): string {
		$artikel_id = (int) $artikel_id;
		if ($artikel_id <= 0) {
			return \function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_url')
				? cmx_artikel_liste_url()
				: (string) \home_url('/katalog/');
		}

		$base_url = \function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_url')
			? cmx_artikel_liste_url()
			: (string) \home_url('/katalog/');
		$slug = \trim((string) \get_post_field('post_name', $artikel_id));
		$slug = $slug !== '' ? $slug : (string) $artikel_id;

		return (string) \add_query_arg(['artikel' => $slug], $base_url);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_detail_find_post')) {
	function cmx_artikel_detail_find_post(): ?\WP_Post {
		$request = cmx_artikel_detail_request_value();
		if ($request === '' || !\function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_meta_query')) {
			return null;
		}

		$query_args = [
			'post_type'              => \defined(__NAMESPACE__ . '\\CMX_PT_ARTIKEL') ? CMX_PT_ARTIKEL : 'artikel',
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'no_found_rows'          => true,
			'suppress_filters'       => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => cmx_artikel_liste_meta_query(),
		];

		$slug_query = new \WP_Query($query_args + [
			'name' => \sanitize_title($request),
		]);
		if (!empty($slug_query->posts[0]) && $slug_query->posts[0] instanceof \WP_Post) {
			return $slug_query->posts[0];
		}

		if (!\ctype_digit($request)) {
			return null;
		}

		$id_query = new \WP_Query($query_args + [
			'p' => (int) $request,
		]);
		if (!empty($id_query->posts[0]) && $id_query->posts[0] instanceof \WP_Post) {
			return $id_query->posts[0];
		}

		return null;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_detail_content_html')) {
	function cmx_artikel_detail_content_html(int $artikel_id): string {
		$content = (string) \get_post_field('post_content', $artikel_id);
		$content = \trim($content);
		if ($content === '') {
			return '';
		}

		return (string) \wpautop(\wp_kses_post($content));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_detail_excerpt_text')) {
	function cmx_artikel_detail_excerpt_text(int $artikel_id): string {
		$excerpt = \trim((string) \get_post_field('post_excerpt', $artikel_id));
		if ($excerpt !== '') {
			return $excerpt;
		}

		$pair = \function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_description_pair')
			? cmx_artikel_liste_description_pair($artikel_id)
			: ['', ''];

		return \trim((string) ($pair[0] ?? ''));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_detail_belegtext')) {
	function cmx_artikel_detail_belegtext(int $artikel_id): string {
		$meta_key = \defined(__NAMESPACE__ . '\\CMX_META_ARTIKEL_BELEG')
			? (string) \constant(__NAMESPACE__ . '\\CMX_META_ARTIKEL_BELEG')
			: '_cmx_artikel_beleg_text';

		return \trim((string) \get_post_meta($artikel_id, $meta_key, true));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_detail_me_logo_url')) {
	function cmx_artikel_detail_me_logo_url(): string {
		return \function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_url')
			? (string) cmx_email_self_logo_url()
			: '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_detail_me_contact_url')) {
	function cmx_artikel_detail_me_contact_url(): string {
		$query = new \WP_Query([
			'post_type'              => 'kontakte',
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'no_found_rows'          => true,
			'suppress_filters'       => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => [[
				'taxonomy' => 'kontakte_kategorien',
				'field'    => 'name',
				'terms'    => ['Das bin ich', 'Ich'],
			]],
		]);

		$post_id = !empty($query->posts[0]->ID) ? (int) $query->posts[0]->ID : 0;
		if ($post_id <= 0) {
			return '';
		}

		$meta_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_URL')
			? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_URL')
			: '_cmx_kontakte_url';

		return \trim((string) \get_post_meta($post_id, $meta_key, true));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_detail_me_contact_title')) {
	function cmx_artikel_detail_me_contact_title(): string {
		$query = new \WP_Query([
			'post_type'              => 'kontakte',
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'no_found_rows'          => true,
			'suppress_filters'       => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => [[
				'taxonomy' => 'kontakte_kategorien',
				'field'    => 'name',
				'terms'    => ['Das bin ich', 'Ich'],
			]],
		]);

		$post_id = !empty($query->posts[0]->ID) ? (int) $query->posts[0]->ID : 0;
		if ($post_id <= 0) {
			return '';
		}

		return \trim((string) \get_the_title($post_id));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_detail_sort_state')) {
	function cmx_artikel_detail_sort_state(): array {
		$key = isset($_GET['sort_key']) ? \sanitize_key((string) \wp_unslash($_GET['sort_key'])) : 'title';
		$dir = isset($_GET['sort_dir']) ? \sanitize_key((string) \wp_unslash($_GET['sort_dir'])) : 'asc';

		$allowed_keys = ['sku', 'title', 'description', 'unit', 'price'];
		if (!\in_array($key, $allowed_keys, true)) {
			$key = 'title';
		}

		if ($dir !== 'desc') {
			$dir = 'asc';
		}

		return ['key' => $key, 'dir' => $dir];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_detail_term_name')) {
	function cmx_artikel_detail_term_name(int $artikel_id, string $taxonomy): string {
		if ($taxonomy === '' || !\taxonomy_exists($taxonomy)) {
			return '';
		}

		$terms = \wp_get_post_terms($artikel_id, $taxonomy);
		if (\is_wp_error($terms) || empty($terms) || empty($terms[0]->name)) {
			return '';
		}

		return \trim((string) $terms[0]->name);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_detail_meta_items')) {
	function cmx_artikel_detail_meta_items(int $artikel_id): array {
		$sku_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_SKU')
			? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_SKU')
			: '_cmx_artikel_sku';
		$marke_tax = \defined(__NAMESPACE__ . '\\TAX_ARTIKEL_MARKEN')
			? (string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_MARKEN')
			: '';
		$kategorie_tax = \defined(__NAMESPACE__ . '\\TAX_ARTIKEL_KATEGORIEN')
			? (string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_KATEGORIEN')
			: '';
		$type_tax = \defined(__NAMESPACE__ . '\\TAX_ARTIKEL_TYPEN')
			? (string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_TYPEN')
			: '';
		$unit = \function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_unit_label')
			? cmx_artikel_liste_unit_label($artikel_id)
			: '';
		$price = \function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_price_label')
			&& \function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_price_raw')
			? cmx_artikel_liste_price_label(cmx_artikel_liste_price_raw($artikel_id))
			: '';

		return \array_values(\array_filter([
			[
				'label' => 'SKU',
				'value' => \trim((string) \get_post_meta($artikel_id, $sku_key, true)),
			],
			[
				'label' => 'Einheit',
				'value' => $unit,
			],
			[
				'label' => 'Marke',
				'value' => cmx_artikel_detail_term_name($artikel_id, $marke_tax),
			],
			[
				'label' => 'Kategorie',
				'value' => cmx_artikel_detail_term_name($artikel_id, $kategorie_tax),
			],
			[
				'label' => 'Typ',
				'value' => cmx_artikel_detail_term_name($artikel_id, $type_tax),
			],
			[
				'label' => 'CHF',
				'value' => $price,
			],
		], static function ($item): bool {
			return \trim((string) ($item['value'] ?? '')) !== '';
		}));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_detail_neighbors')) {
	function cmx_artikel_detail_neighbors(int $artikel_id, array $sort_state): array {
		if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_rows')) {
			return ['prev' => null, 'next' => null];
		}

		$rows = \array_values((array) cmx_artikel_liste_rows());
		$key = (string) ($sort_state['key'] ?? 'title');
		$dir = (string) ($sort_state['dir'] ?? 'asc');

		$extract_value = static function (array $row, string $key) {
			if ($key === 'price') {
				return (float) ($row['price_raw'] ?? 0.0);
			}

			if ($key === 'description') {
				$value = (string) ($row['description_full'] ?? '');
				if (\trim($value) === '') {
					$value = (string) ($row['description'] ?? '');
				}
				return \function_exists('mb_strtolower') ? \mb_strtolower(\trim($value), 'UTF-8') : \strtolower(\trim($value));
			}

			$value = (string) ($row[$key] ?? '');
			return \function_exists('mb_strtolower') ? \mb_strtolower(\trim($value), 'UTF-8') : \strtolower(\trim($value));
		};

		\usort($rows, static function (array $a, array $b) use ($key, $dir, $extract_value): int {
			$av = $extract_value($a, $key);
			$bv = $extract_value($b, $key);

			if ($key === 'price') {
				$result = $av <=> $bv;
			} else {
				$result = $av <=> $bv;
			}

			if ($result === 0) {
				$at = \function_exists('mb_strtolower') ? \mb_strtolower((string) ($a['title'] ?? ''), 'UTF-8') : \strtolower((string) ($a['title'] ?? ''));
				$bt = \function_exists('mb_strtolower') ? \mb_strtolower((string) ($b['title'] ?? ''), 'UTF-8') : \strtolower((string) ($b['title'] ?? ''));
				$result = $at <=> $bt;
			}

			if ($result === 0) {
				$result = ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
			}

			return $dir === 'desc' ? -$result : $result;
		});
		$current_index = null;

		foreach ($rows as $index => $row) {
			if ((int) ($row['id'] ?? 0) === $artikel_id) {
				$current_index = $index;
				break;
			}
		}

		if ($current_index === null) {
			return ['prev' => null, 'next' => null];
		}

		$prev = $current_index > 0 ? (array) $rows[$current_index - 1] : null;
		$next = isset($rows[$current_index + 1]) ? (array) $rows[$current_index + 1] : null;

		return ['prev' => $prev, 'next' => $next];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_detail_url_with_sort')) {
	function cmx_artikel_detail_url_with_sort(int $artikel_id, array $sort_state): string {
		$url = cmx_artikel_detail_url($artikel_id);
		return (string) \add_query_arg([
			'sort_key' => (string) ($sort_state['key'] ?? 'title'),
			'sort_dir' => (string) ($sort_state['dir'] ?? 'asc'),
		], $url);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_artikel_detail_page')) {
	function cmx_render_artikel_detail_page(): void {
		if (!cmx_is_artikel_detail_request()) {
			return;
		}

		$artikel = cmx_artikel_detail_find_post();
		$sort_state = cmx_artikel_detail_sort_state();
		$reload_url = \function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_url')
			? cmx_artikel_liste_url()
			: (string) \home_url('/katalog/');
		$reload_url = (string) \add_query_arg([
			'sort_key' => (string) $sort_state['key'],
			'sort_dir' => (string) $sort_state['dir'],
		], $reload_url);

		while (\ob_get_level()) {
			\ob_end_clean();
		}

		if (!\defined('DONOTCACHEPAGE')) {
			\define('DONOTCACHEPAGE', true);
		}
		\nocache_headers();

		if (!$artikel instanceof \WP_Post) {
			\status_header(404);
			echo '<!doctype html><html lang="de"><head><meta charset="utf-8">';
			echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
			echo '<title>Artikel nicht gefunden</title>';
			echo '<style>
				:root{color-scheme:light}
				*{box-sizing:border-box}
				body{margin:0;font-family:Segoe UI,Roboto,Arial,sans-serif;background:#efefef;color:#1d2327}
				.cmx-artikel-detail-page{max-width:900px;margin:0 auto;padding:32px 18px 40px}
				.cmx-artikel-detail-card{background:#fff;border:1px solid #ddd;border-radius:14px;box-shadow:0 18px 40px rgba(0,0,0,.06);overflow:hidden}
				.cmx-artikel-detail-head{padding:26px 28px 20px;background:linear-gradient(135deg,#f7f7f7 0%,#ededed 100%);border-bottom:1px solid #e2e2e2}
				.cmx-artikel-detail-kicker{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;margin:0 0 8px}
				.cmx-artikel-detail-kicker a{color:inherit;text-decoration:none}
				.cmx-artikel-detail-kicker a:hover{color:#1d2327}
				.cmx-artikel-detail-title{margin:0;font-size:30px;line-height:1.1}
				.cmx-artikel-detail-sub{margin:8px 0 0;color:#6b7280;font-size:14px}
				.cmx-artikel-detail-body{padding:28px}
				.cmx-artikel-detail-button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 16px;border:1px solid #c8c8c8;border-radius:10px;background:#fff;color:#1d2327;text-decoration:none;font-weight:600}
				.cmx-artikel-detail-button:hover{background:#f5faff;border-color:#9abfe1}
			</style>';
			echo '</head><body><div class="cmx-artikel-detail-page"><div class="cmx-artikel-detail-card">';
			echo '<div class="cmx-artikel-detail-head">';
			echo '<p class="cmx-artikel-detail-kicker"><a href="' . \esc_url($reload_url) . '">Zurück zum Katalog</a></p>';
			echo '<h1 class="cmx-artikel-detail-title">Artikel nicht gefunden</h1>';
			echo '<p class="cmx-artikel-detail-sub">Der angeforderte Artikel ist nicht mehr im Katalog sichtbar.</p>';
			echo '</div><div class="cmx-artikel-detail-body">';
			echo '<a class="cmx-artikel-detail-button" href="' . \esc_url($reload_url) . '">in den Warenkorb</a>';
			echo '</div></div></div></body></html>';
			exit;
		}

		\status_header(200);

		$artikel_id = (int) $artikel->ID;
		$title = \trim((string) \get_the_title($artikel_id));
		$title = $title !== '' ? $title : ('#' . $artikel_id);
		$belegtext = cmx_artikel_detail_belegtext($artikel_id);
		$excerpt = cmx_artikel_detail_excerpt_text($artikel_id);
		$intro_text = $belegtext !== '' ? $belegtext : $excerpt;
		$content_html = cmx_artikel_detail_content_html($artikel_id);
		$meta_items = cmx_artikel_detail_meta_items($artikel_id);
		$neighbors = cmx_artikel_detail_neighbors($artikel_id, $sort_state);
		$prev_item = \is_array($neighbors['prev'] ?? null) ? (array) $neighbors['prev'] : null;
		$next_item = \is_array($neighbors['next'] ?? null) ? (array) $neighbors['next'] : null;
		$image_url = \trim((string) \get_post_meta($artikel_id, '_cmx_local_image_artikel_url', true));
		$me_logo_url = cmx_artikel_detail_me_logo_url();
		$me_contact_url = cmx_artikel_detail_me_contact_url();
		$me_contact_title = cmx_artikel_detail_me_contact_title();
		$price_label = \function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_price_label')
			&& \function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_price_raw')
			? cmx_artikel_liste_price_label(cmx_artikel_liste_price_raw($artikel_id))
			: '';

		echo '<!doctype html><html lang="de"><head><meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>' . \esc_html($title) . ' | Katalog</title>';
		echo '<style>
			:root{color-scheme:light}
			*{box-sizing:border-box}
			body{margin:0;font-family:Segoe UI,Roboto,Arial,sans-serif;background:#efefef;color:#1d2327}
			.cmx-artikel-detail-shell{position:relative;max-width:1360px;margin:0 auto}
			.cmx-artikel-detail-page{max-width:1180px;margin:0 auto;padding:32px 18px 40px}
			.cmx-artikel-detail-card{background:#fff;border:1px solid #ddd;border-radius:14px;box-shadow:0 18px 40px rgba(0,0,0,.06);overflow:hidden}
			.cmx-artikel-detail-nav{position:absolute;top:50%;transform:translateY(-50%);display:flex;align-items:center;justify-content:center;width:62px;height:62px;border-radius:999px;background:linear-gradient(180deg,#1f6fad 0%,#135e96 100%);box-shadow:0 14px 30px rgba(19,94,150,.24);text-decoration:none;transition:transform .18s ease,box-shadow .18s ease,background .18s ease}
			.cmx-artikel-detail-nav:hover{background:linear-gradient(180deg,#257dc0 0%,#1568a5 100%);box-shadow:0 18px 34px rgba(19,94,150,.32)}
			.cmx-artikel-detail-nav-left{left:18px}
			.cmx-artikel-detail-nav-right{right:18px}
			.cmx-artikel-detail-nav-icon{display:block;width:18px;height:18px;border-top:4px solid #fff;border-right:4px solid #fff}
			.cmx-artikel-detail-nav-left .cmx-artikel-detail-nav-icon{transform:rotate(-135deg);margin-left:5px}
			.cmx-artikel-detail-nav-right .cmx-artikel-detail-nav-icon{transform:rotate(45deg);margin-right:5px}
			.cmx-artikel-detail-head{padding:24px 28px 18px;background:linear-gradient(135deg,#f7f7f7 0%,#ededed 100%);border-bottom:1px solid #e2e2e2}
			.cmx-artikel-detail-head-inner{display:flex;align-items:flex-start;justify-content:space-between;gap:24px}
			.cmx-artikel-detail-head-copy{flex:1 1 auto;min-width:0}
			.cmx-artikel-detail-head-brand{flex:0 0 190px;display:flex;align-items:flex-start;justify-content:flex-end;min-height:84px}
			.cmx-artikel-detail-head-logo{display:block;max-width:190px;max-height:84px;width:auto;height:auto;object-fit:contain;object-position:right top}
			.cmx-artikel-detail-kicker{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;margin:0 0 8px}
			.cmx-artikel-detail-kicker a{color:inherit;text-decoration:none}
			.cmx-artikel-detail-kicker a:hover{color:#1d2327}
			.cmx-artikel-detail-title{margin:0;font-size:36px;line-height:1.06}
			.cmx-artikel-detail-sub{margin:10px 0 0;max-width:720px;color:#4b5563;font-size:15px;line-height:1.6}
			.cmx-artikel-detail-stage{padding:28px;display:grid;grid-template-columns:380px minmax(0,1fr);gap:28px;align-items:start}
			.cmx-artikel-detail-media{background:linear-gradient(180deg,#fafafa 0%,#f3f4f6 100%);border:1px solid #ececec;border-radius:16px;padding:18px;min-height:320px;display:flex;align-items:center;justify-content:center;overflow:hidden}
			.cmx-artikel-detail-image{display:block;width:auto;height:auto;max-width:100%;max-height:100%;object-fit:contain;object-position:center center}
			.cmx-artikel-detail-image-placeholder{display:flex;align-items:center;justify-content:center;width:100%;height:100%;border:2px dashed #d5d5d5;border-radius:14px;background:#fff;color:#9ca3af;font-size:14px;text-align:center;padding:18px}
			.cmx-artikel-detail-panel{display:flex;flex-direction:column;gap:18px}
			.cmx-artikel-detail-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
			.cmx-artikel-detail-meta-item{padding:14px 16px;border:1px solid #ececec;border-radius:12px;background:#fafafa}
			.cmx-artikel-detail-meta-label{display:block;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;margin-bottom:6px}
			.cmx-artikel-detail-meta-value{display:block;font-size:17px;font-weight:700;line-height:1.3;color:#1f2937}
			.cmx-artikel-detail-price-card{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 20px;border-radius:14px;background:#0f5f9a;color:#fff}
			.cmx-artikel-detail-price-copy{display:flex;flex-direction:column;gap:6px}
			.cmx-artikel-detail-price-label{font-size:12px;letter-spacing:.08em;text-transform:uppercase;opacity:.78}
			.cmx-artikel-detail-price-value{font-size:34px;font-weight:800;line-height:1}
			.cmx-artikel-detail-back{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 16px;border:1px solid #c8c8c8;border-radius:10px;background:#fff;color:#1d2327;text-decoration:none;font-weight:600}
			.cmx-artikel-detail-back:hover{background:#f5faff;border-color:#9abfe1}
			.cmx-artikel-detail-content-wrap{padding:0 28px 28px}
			.cmx-artikel-detail-section{border-top:1px solid #ececec;padding-top:22px}
			.cmx-artikel-detail-section-title{margin:0 0 12px;font-size:18px}
			.cmx-artikel-detail-content{color:#374151;font-size:15px;line-height:1.75}
			.cmx-artikel-detail-content p{margin:0 0 14px}
			.cmx-artikel-detail-content p:last-child{margin-bottom:0}
			@media (max-width:860px){
				.cmx-artikel-detail-nav{display:none}
				.cmx-artikel-detail-head-inner{flex-direction:column}
				.cmx-artikel-detail-head-brand{justify-content:flex-start;min-height:0}
				.cmx-artikel-detail-stage{grid-template-columns:1fr}
				.cmx-artikel-detail-title{font-size:30px}
				.cmx-artikel-detail-media{height:auto;min-height:320px}
				.cmx-artikel-detail-image{width:auto;height:auto;max-width:100%;max-height:100%}
			}
			@media (max-width:720px){
				.cmx-artikel-detail-page{padding:18px 12px 24px}
				.cmx-artikel-detail-head,.cmx-artikel-detail-stage,.cmx-artikel-detail-content-wrap{padding-left:16px;padding-right:16px}
				.cmx-artikel-detail-meta{grid-template-columns:1fr}
				.cmx-artikel-detail-price-card{align-items:flex-start;flex-direction:column}
			}
		</style>';
		echo '</head><body>';
		echo '<div class="cmx-artikel-detail-shell">';
		if ($prev_item !== null && !empty($prev_item['id'])) {
			echo '<a id="cmx-artikel-detail-nav-left" class="cmx-artikel-detail-nav cmx-artikel-detail-nav-left" href="' . \esc_url(cmx_artikel_detail_url_with_sort((int) $prev_item['id'], $sort_state)) . '" title="' . \esc_attr((string) ($prev_item['title'] ?? 'Vorheriger Artikel')) . '" aria-label="' . \esc_attr('Vorheriger Artikel: ' . (string) ($prev_item['title'] ?? '')) . '"><span class="cmx-artikel-detail-nav-icon" aria-hidden="true"></span></a>';
		}
		if ($next_item !== null && !empty($next_item['id'])) {
			echo '<a id="cmx-artikel-detail-nav-right" class="cmx-artikel-detail-nav cmx-artikel-detail-nav-right" href="' . \esc_url(cmx_artikel_detail_url_with_sort((int) $next_item['id'], $sort_state)) . '" title="' . \esc_attr((string) ($next_item['title'] ?? 'Nächster Artikel')) . '" aria-label="' . \esc_attr('Nächster Artikel: ' . (string) ($next_item['title'] ?? '')) . '"><span class="cmx-artikel-detail-nav-icon" aria-hidden="true"></span></a>';
		}
		echo '<div class="cmx-artikel-detail-page"><div class="cmx-artikel-detail-card">';
		echo '<div class="cmx-artikel-detail-head">';
		echo '<div class="cmx-artikel-detail-head-inner">';
		echo '<div class="cmx-artikel-detail-head-copy">';
		echo '<p class="cmx-artikel-detail-kicker"><a href="' . \esc_url($reload_url) . '">Zurück zum Katalog</a></p>';
		echo '<h1 class="cmx-artikel-detail-title">' . \esc_html($title) . '</h1>';
		if ($intro_text !== '') {
			echo '<p class="cmx-artikel-detail-sub">' . \wp_kses(\nl2br(\esc_html($intro_text)), ['br' => []]) . '</p>';
		}
		echo '</div>';
		if ($me_logo_url !== '') {
			echo '<div class="cmx-artikel-detail-head-brand">';
			if ($me_contact_url !== '') {
				echo '<a href="' . \esc_url($me_contact_url) . '" target="_blank" rel="noopener noreferrer" title="' . \esc_attr($me_contact_title) . '">';
			}
			echo '<img class="cmx-artikel-detail-head-logo" src="' . \esc_url($me_logo_url) . '" alt="Das bin ich Logo">';
			if ($me_contact_url !== '') {
				echo '</a>';
			}
			echo '</div>';
		}
		echo '</div>';
		echo '</div>';

		echo '<div class="cmx-artikel-detail-stage" id="cmx-artikel-detail-stage">';
		echo '<div class="cmx-artikel-detail-media" id="cmx-artikel-detail-media">';
		if ($image_url !== '') {
			echo '<img class="cmx-artikel-detail-image" src="' . \esc_url($image_url) . '" alt="' . \esc_attr($title) . '">';
		} else {
			echo '<div class="cmx-artikel-detail-image-placeholder">Kein Artikelbild hinterlegt</div>';
		}
		echo '</div>';

		echo '<div class="cmx-artikel-detail-panel" id="cmx-artikel-detail-panel">';
		if ($price_label !== '') {
			echo '<div class="cmx-artikel-detail-price-card">';
			echo '<div class="cmx-artikel-detail-price-copy">';
			echo '<span class="cmx-artikel-detail-price-label">Verkaufspreis</span>';
			echo '<span class="cmx-artikel-detail-price-value">' . \esc_html($price_label) . ' <span style="font-size:15px;font-weight:700;opacity:.82;vertical-align:middle">CHF</span></span>';
			echo '</div>';
			echo '<a class="cmx-artikel-detail-back" href="' . \esc_url($reload_url) . '">in den Warenkorb</a>';
			echo '</div>';
		} else {
			echo '<div><a class="cmx-artikel-detail-back" href="' . \esc_url($reload_url) . '">in den Warenkorb</a></div>';
		}

		if (!empty($meta_items)) {
			echo '<div class="cmx-artikel-detail-meta">';
			foreach ($meta_items as $item) {
				$label = \trim((string) ($item['label'] ?? ''));
				$value = \trim((string) ($item['value'] ?? ''));
				if ($label === '' || $value === '') {
					continue;
				}

				echo '<div class="cmx-artikel-detail-meta-item">';
				echo '<span class="cmx-artikel-detail-meta-label">' . \esc_html($label) . '</span>';
				echo '<span class="cmx-artikel-detail-meta-value">' . \esc_html($value) . '</span>';
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div></div>';

		echo '<div class="cmx-artikel-detail-content-wrap">';
		echo '<div class="cmx-artikel-detail-section">';
		echo '<h2 class="cmx-artikel-detail-section-title">Beschreibung</h2>';
		if ($content_html !== '') {
			echo '<div class="cmx-artikel-detail-content">' . $content_html . '</div>';
		} elseif ($excerpt !== '') {
			echo '<div class="cmx-artikel-detail-content"><p>' . \esc_html($excerpt) . '</p></div>';
		} else {
			echo '<div class="cmx-artikel-detail-content"><p>Zu diesem Artikel ist aktuell keine Beschreibung hinterlegt.</p></div>';
		}
		echo '</div></div>';
		echo '<script>
			(function(){
				var media=document.getElementById("cmx-artikel-detail-media");
				var panel=document.getElementById("cmx-artikel-detail-panel");
				var stage=document.getElementById("cmx-artikel-detail-stage");
				var navLeft=document.getElementById("cmx-artikel-detail-nav-left");
				var navRight=document.getElementById("cmx-artikel-detail-nav-right");
				if(!media||!panel){return;}
				function syncLayout(){
					if(window.innerWidth<=860){
						media.style.height="";
						if(navLeft){ navLeft.style.top=""; }
						if(navRight){ navRight.style.top=""; }
						return;
					}
					media.style.height=panel.offsetHeight + "px";
					if(stage){
						var navTop=stage.offsetTop + (stage.offsetHeight / 2);
						if(navLeft){ navLeft.style.top=navTop + "px"; }
						if(navRight){ navRight.style.top=navTop + "px"; }
					}
				}
				window.addEventListener("load", syncLayout);
				window.addEventListener("resize", syncLayout);
				syncLayout();
			})();
		</script>';
		echo '</div></div></div></body></html>';
		exit;
	}
}

\add_action('template_redirect', __NAMESPACE__ . '\\cmx_render_artikel_detail_page', 4);
