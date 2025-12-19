<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || die('Oxytocin!');


/**
 * NUR Funktionsdefinitionen in dieser Datei.
 * KEIN add_action / add_shortcode hier drin.
 */

function cmx_render_artikel_tabelle($atts = [], $content = ''): string {
	$atts = \shortcode_atts(
		[
			'posts_per_page' => -1,
		],
		$atts,
		'cmx_artikel_tabelle'
	);

	// Meta-Key für "verkaufbar"
	$verkaufbar_key = \defined(__NAMESPACE__.'\\CMX_ARTIKEL_META_VERKAUFBAR')
		? CMX_ARTIKEL_META_VERKAUFBAR
		: '_cmx_artikel_verkaufbar';

	// nur verkaufbare Artikel:
	// Checkbox-Logik: 1 = NICHT verkaufbar, 0/nicht gesetzt = verkaufbar
	$meta_query = [
		'relation' => 'OR',
		[
			'key'     => $verkaufbar_key,
			'compare' => 'NOT EXISTS',
		],
		[
			'key'     => $verkaufbar_key,
			'value'   => '0',
			'compare' => '=',
		],
	];

	$query = new \WP_Query([
		'post_type'      => 'artikel',
		'post_status'    => 'publish',
		'posts_per_page' => (int) $atts['posts_per_page'],
		'orderby'        => 'title',
		'order'          => 'ASC',
		'meta_query'     => $meta_query,
	]);

	if (!$query->have_posts()) {
		return '<p>Keine Artikel gefunden.</p>';
	}

	ob_start();

	static $cmx_artikel_assets_done = false;

	if (!$cmx_artikel_assets_done) {
		?>
		<style>
			.cmx-artikel-table-wrap {
				overflow-x: auto;
				margin: 1.5rem 0;
				width: 100%;
			}
			.cmx-artikel-search-wrap {
				margin-bottom: 8px;
			}
			.cmx-artikel-search-wrap input[type="search"] {
				width: 100%;
				max-width: 320px;
				padding: 4px 8px;
			}
			.cmx-artikel-table {
				width: 100%;
				border-collapse: collapse;
				border-spacing: 0;
				font-size: 14px;
				background: #fff;
				border-radius: 6px;
				overflow: hidden;
				border: 1px solid #e0e0e0;
			}
			.cmx-artikel-table thead {
				background: #f5f5f5;
			}
			.cmx-artikel-table th,
			.cmx-artikel-table td {
				padding: 8px 12px;
				border-bottom: 1px solid #e0e0e0;
				text-align: left;
				vertical-align: top;
				white-space: nowrap;
			}
			/* Beschreibung darf umbrechen */
			.cmx-artikel-table td.cmx-col-desc { white-space: normal; }
			/* Unterer Rand explizit sicherstellen */
			.cmx-artikel-table tbody tr:last-child td {
				border-bottom: 1px solid #e0e0e0 !important;
			}
			.cmx-artikel-table th[data-cmx-sort] {
				cursor: pointer;
			}
			.cmx-artikel-table th {
				white-space: nowrap;
				font-weight: 600;
				position: relative;
			}
			.cmx-artikel-table th .cmx-sort-indicator {
				margin-left: 6px;
				font-size: 11px;
				opacity: 0.5;
			}
			.cmx-artikel-table th[data-cmx-sort-dir="asc"] .cmx-sort-indicator::after {
				content: "▲";
			}
			.cmx-artikel-table th[data-cmx-sort-dir="desc"] .cmx-sort-indicator::after {
				content: "▼";
			}
			/* Zebra ab zweiter Zeile: 1. Zeile ohne Hintergrund */
			.cmx-artikel-table tbody tr:nth-child(even) {
				background: #fafafa;
			}
			.cmx-artikel-table tbody tr:hover {
				background: #f0f0f0;
			}
			.cmx-artikel-thumb {
				width: 60px;
				height: 60px;
				object-fit: cover;
				border-radius: 4px;
				display: block;
				box-shadow: 0 0 4px rgba(0,0,0,0.08);
			}
			.cmx-artikel-thumb-placeholder {
				width: 60px;
				height: 60px;
				border-radius: 4px;
				background: #ddd;
				display: inline-block;
			}
			.cmx-artikel-title a {
				text-decoration: none;
				color: #a42c24;
			}
			.cmx-artikel-title a:hover {
				text-decoration: none;
				color: #a42c24;
			}
			.cmx-artikel-table td a {
				color: #a42c24;
				text-decoration: none;
			}
			.cmx-artikel-table td a:hover { text-decoration: none; color: #a42c24; }
		</style>

		<script>
			// Sortierung
			document.addEventListener('click', function (e) {
				const th = e.target.closest('th[data-cmx-sort]');
				if (!th) return;

				const table = th.closest('table');
				const tbody = table.querySelector('tbody');
				const index = Array.prototype.indexOf.call(th.parentNode.children, th);
				const type = th.dataset.cmxSortType || 'string';

				const currentDir = th.dataset.cmxSortDir === 'asc' ? 'asc' : (th.dataset.cmxSortDir === 'desc' ? 'desc' : '');
				const nextDir = currentDir === 'asc' ? 'desc' : 'asc';

				table.querySelectorAll('th[data-cmx-sort]').forEach(function (head) {
					if (head !== th) {
						head.dataset.cmxSortDir = '';
					}
				});
				th.dataset.cmxSortDir = nextDir;

				const rows = Array.from(tbody.querySelectorAll('tr'));

				rows.sort(function (rowA, rowB) {
					const cellA = rowA.children[index];
					const cellB = rowB.children[index];

					let valA = cellA.dataset.sortValue || cellA.textContent.trim();
					let valB = cellB.dataset.sortValue || cellB.textContent.trim();

					if (type === 'number') {
						valA = parseFloat(valA.replace(',', '.')) || 0;
						valB = parseFloat(valB.replace(',', '.')) || 0;
					} else if (type === 'date') {
						valA = Date.parse(valA) || 0;
						valB = Date.parse(valB) || 0;
					} else {
						valA = valA.toLowerCase();
						valB = valB.toLowerCase();
					}

					if (valA < valB) return nextDir === 'asc' ? -1 : 1;
					if (valA > valB) return nextDir === 'asc' ? 1 : -1;
					return 0;
				});

				rows.forEach(function (row) {
					tbody.appendChild(row);
				});
			});

			// Suche nach SKU oder Artikeltitel
			document.addEventListener('input', function (e) {
				const input = e.target.closest('.cmx-artikel-search');
				if (!input) return;

				const wrap  = input.closest('.cmx-artikel-table-wrap');
				if (!wrap) return;
				const table = wrap.querySelector('.cmx-artikel-table');
				if (!table) return;
				const tbody = table.querySelector('tbody');
				if (!tbody) return;

				const q = input.value.trim().toLowerCase();

				Array.from(tbody.rows).forEach(function (row) {
					// Spalte 0 = Bild, 1 = SKU, 2 = Artikel
					const skuCell   = row.cells[1];
					const titleCell = row.cells[2];

					const sku   = (skuCell   ? skuCell.textContent   : '').toLowerCase();
					const title = (titleCell ? titleCell.textContent : '').toLowerCase();

					if (!q || sku.indexOf(q) !== -1 || title.indexOf(q) !== -1) {
						row.style.display = '';
					} else {
						row.style.display = 'none';
					}
				});
			});
		</script>
		<?php
		$cmx_artikel_assets_done = true;
	}

	$artikel_count = 0;

	echo '<div class="cmx-artikel-table-wrap">';
	// Suchfeld über der Tabelle
	echo '<div class="cmx-artikel-search-wrap">';
	echo '<input type="search" class="cmx-artikel-search" placeholder="SKU oder Artikel">';
	echo '</div>';

	echo '<table class="cmx-artikel-table">';
	echo '<thead><tr>';
	// 1: Bild (ohne Text)
	echo '<th><span></span></th>';
	// 2: SKU
	echo '<th data-cmx-sort="1" data-cmx-sort-type="string"><span>SKU</span><span class="cmx-sort-indicator"></span></th>';
	// 3: Artikel
	echo '<th data-cmx-sort="1" data-cmx-sort-type="string"><span>Artikel</span><span class="cmx-sort-indicator"></span></th>';
	// 4: Beschreibung
	echo '<th><span>Beschreibung</span></th>';
	// 5: Einheit
	echo '<th><span>Einheit</span></th>';
	// 6: CHF
	echo '<th data-cmx-sort="1" data-cmx-sort-type="number"><span>CHF</span><span class="cmx-sort-indicator"></span></th>';
	echo '</tr></thead><tbody>';

	// VK-Metakey (Konstante oder Fallback)
	$vk_meta_key = \defined(__NAMESPACE__.'\\CMX_ARTIKEL_META_VK') ? CMX_ARTIKEL_META_VK : '_cmx_artikel_vk';

	while ($query->have_posts()) {
		$query->the_post();
		$post_id = \get_the_ID();
		$artikel_count++;

		// Bild aus lokalem Bildsystem (_cmx_local_image_artikel_url)
		$img_url = (string) \get_post_meta($post_id, '_cmx_local_image_artikel_url', true);
		if ($img_url !== '') {
			$thumb_html = '<img src="' . esc_url($img_url) . '" class="cmx-artikel-thumb" alt="' . esc_attr(\get_the_title($post_id)) . '">';
		} else {
			$thumb_html = '<span class="cmx-artikel-thumb-placeholder" aria-hidden="true"></span>';
		}

		// Artikelnummer
		$artikelnummer = \get_post_meta($post_id, '_cmx_artikel_sku', true);
		$artikelnummer_display = $artikelnummer !== '' ? $artikelnummer : '';

		// Titel
		$titel = \get_the_title($post_id);

		// VK
		$vk_raw   = \get_post_meta($post_id, $vk_meta_key, true);
		$vk_value = (float) str_replace(',', '.', (string) $vk_raw);
		$vk_display = $vk_value > 0 ? number_format($vk_value, 2, ',', "'") . '' : '';

		// Text
		$content_full     = \get_post_field('post_content', $post_id);
		$content_stripped = wp_strip_all_tags($content_full);
		$content_short    = wp_trim_words($content_stripped, 25, ' …');

		// Einheit (Taxonomie)
		$einheit_label = '';
		if (\defined(__NAMESPACE__.'\\TAX_ARTIKEL_EINHEITEN')) {
			$terms = \wp_get_post_terms($post_id, TAX_ARTIKEL_EINHEITEN);
			if (!\is_wp_error($terms) && !empty($terms) && !empty($terms[0]->name)) {
				$einheit_label = (string) $terms[0]->name;
			}
		}

		echo '<tr>';

		// 1: Bild
		echo '<td>' . $thumb_html . '</td>';

		// 2: SKU
		echo '<td data-sort-value="' . esc_attr($artikelnummer) . '">';
		if ($artikelnummer_display !== '') {
			echo '<a href="' . esc_url(\get_permalink($post_id)) . '">';
			echo esc_html($artikelnummer_display);
			echo '</a>';
		} else {
			echo esc_html($artikelnummer_display);
		}
		echo '</td>';

		// 3: Artikel
		echo '<td class="cmx-artikel-title" data-sort-value="' . esc_attr($titel) . '">';
		echo '<a href="' . esc_url(\get_permalink($post_id)) . '">';
		echo esc_html($titel);
		echo '</a>';
		echo '</td>';

		// 4: Beschreibung
		echo '<td class="cmx-col-desc">';
		echo esc_html($content_short);
		echo '</td>';

		// 5: Einheit
		echo '<td>';
		echo esc_html($einheit_label);
		echo '</td>';

		// 6: CHF
		echo '<td data-sort-value="' . esc_attr($vk_value) . '">';
		echo esc_html($vk_display);
		echo '</td>';

		echo '</tr>';
	}

	\wp_reset_postdata();

	echo '</tbody></table>';
	echo '<p><strong>' . (int) $artikel_count . '</strong> Artikel</p>';
	echo '</div>';

	return ob_get_clean();
}
