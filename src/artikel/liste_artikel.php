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

		$short = (string) \wp_trim_words($content_full, 28, ' …');
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
				'url' => (string) \get_permalink($artikel_id),
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
		echo '<title>Katalog</title>';
		echo '<style>
			:root{color-scheme:light}
			*{box-sizing:border-box}
			body{margin:0;font-family:Segoe UI,Roboto,Arial,sans-serif;background:#efefef;color:#1d2327}
			.cmx-artikel-page{max-width:1180px;margin:0 auto;padding:32px 18px 40px}
			.cmx-artikel-card{background:#fff;border:1px solid #ddd;border-radius:14px;box-shadow:0 18px 40px rgba(0,0,0,.06);overflow:hidden}
			.cmx-artikel-head{padding:24px 28px 18px;background:linear-gradient(135deg,#f7f7f7 0%,#ededed 100%);border-bottom:1px solid #e2e2e2}
			.cmx-artikel-kicker{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;margin:0 0 8px}
			.cmx-artikel-kicker a{color:inherit;text-decoration:none}
			.cmx-artikel-kicker a:hover{color:#1d2327}
			.cmx-artikel-title{margin:0;font-size:30px;line-height:1.1}
			.cmx-artikel-sub{margin:8px 0 0;color:#6b7280;font-size:14px}
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
				.cmx-artikel-head,.cmx-artikel-tools,.cmx-artikel-table-wrap{padding-left:16px;padding-right:16px}
				.cmx-artikel-title{font-size:24px}
				.cmx-artikel-thumb-wrap{width:68px}
				.cmx-artikel-thumb,.cmx-artikel-thumb-placeholder{width:52px;height:52px}
			}
		</style>';
		echo '</head><body>';
		echo '<div class="cmx-artikel-page"><div class="cmx-artikel-card">';
		echo '<div class="cmx-artikel-head">';
		echo '<p class="cmx-artikel-kicker"><a href="' . \esc_url($reload_url) . '">Artikelübersicht</a></p>';
		echo '<h1 class="cmx-artikel-title">Katalog</h1>';
		echo '<p class="cmx-artikel-sub">' . \esc_html(\count($rows) . ' Artikel') . '</p>';
		echo '</div>';

		if (empty($rows)) {
			echo '<div class="cmx-artikel-empty">Aktuell keine Artikel gefunden.</div>';
			echo '</div></div></body></html>';
			exit;
		}

		echo '<div class="cmx-artikel-tools">';
		echo '<input type="search" id="cmx-artikel-search" placeholder="Artikel durchsuchen">';
		echo '</div>';
		echo '<div class="cmx-artikel-table-wrap">';
		echo '<table class="cmx-artikel-table"><thead><tr>';
		echo '<th>Bild</th>';
		echo '<th><button type="button" data-sort-key="sku" data-sort-type="string">SKU<span class="cmx-artikel-sort-indicator"> </span></button></th>';
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
				echo '<a href="' . \esc_url($url) . '" title="Artikel anzeigen"><img src="' . \esc_url($image_url) . '" alt="' . \esc_attr($title) . '" class="cmx-artikel-thumb"></a>';
			} elseif ($image_url !== '') {
				echo '<img src="' . \esc_url($image_url) . '" alt="' . \esc_attr($title) . '" class="cmx-artikel-thumb">';
			} elseif ($url !== '') {
				echo '<a href="' . \esc_url($url) . '" title="Artikel anzeigen"><span class="cmx-artikel-thumb-placeholder" aria-hidden="true"></span></a>';
			} else {
				echo '<span class="cmx-artikel-thumb-placeholder" aria-hidden="true"></span>';
			}
			echo '</td>';

			echo '<td class="cmx-artikel-sku">';
			if ($url !== '' && $sku !== '') {
				echo '<a href="' . \esc_url($url) . '" title="Artikel anzeigen">' . \esc_html($sku) . '</a>';
			} else {
				echo \esc_html($sku);
			}
			echo '</td>';

			echo '<td>';
			if ($url !== '') {
				echo '<a href="' . \esc_url($url) . '" title="Artikel anzeigen">' . \esc_html($title) . '</a>';
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
				var sortButtons=document.querySelectorAll(".cmx-artikel-table thead button[data-sort-key]");
				if(!body){return;}
				function normalize(txt){return String(txt||"").toLowerCase().trim();}
				function getRows(){return Array.prototype.slice.call(body.querySelectorAll("tr[data-search]"));}
				function applyFilter(){
					var term=normalize(input?input.value:"");
					getRows().forEach(function(row){
						var hay=(row.getAttribute("data-search")||"") + " " + normalize(row.textContent||"");
						row.style.display=(term===""||hay.indexOf(term)!==-1)?"":"none";
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
				}
				if(input){
					input.addEventListener("input",applyFilter);
				}
				Array.prototype.forEach.call(sortButtons,function(btn){
					btn.addEventListener("click",function(){
						var key=btn.getAttribute("data-sort-key")||"";
						var type=btn.getAttribute("data-sort-type")||"string";
						var current=btn.getAttribute("data-sort-dir")||"";
						var next=current==="asc"?"desc":"asc";
						Array.prototype.forEach.call(sortButtons,function(other){
							other.setAttribute("data-sort-dir", other===btn ? next : "");
							var indicator=other.querySelector(".cmx-artikel-sort-indicator");
							if(indicator){ indicator.textContent = other===btn ? (next==="asc" ? "▲" : "▼") : " "; }
						});
						sortRows(key,type,next);
					});
				});
				sortRows("title","string","asc");
				Array.prototype.forEach.call(sortButtons,function(btn){
					if((btn.getAttribute("data-sort-key")||"")==="title"){
						btn.setAttribute("data-sort-dir","asc");
						var indicator=btn.querySelector(".cmx-artikel-sort-indicator");
						if(indicator){ indicator.textContent="▲"; }
					}
				});
			})();
		</script>';
		echo '</body></html>';
		exit;
	}
}

\add_action('template_redirect', __NAMESPACE__ . '\\cmx_render_artikel_liste_page', 5);
