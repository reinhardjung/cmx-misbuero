<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


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

	$query = new \WP_Query([
		'post_type'      => 'artikel',
		'post_status'    => 'publish',
		'posts_per_page' => (int) $atts['posts_per_page'],
		'orderby'        => 'title',
		'order'          => 'ASC',
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
			}
			.cmx-artikel-table {
				width: 100%;
				border-collapse: collapse;
				font-size: 14px;
				background: #fff;
				border-radius: 6px;
				overflow: hidden;
			}
			.cmx-artikel-table thead {
				background: #f5f5f5;
			}
			.cmx-artikel-table th,
			.cmx-artikel-table td {
				padding: 8px 12px;
				border-bottom: 1px solid #e0e0e0;
				text-align: left;
			}
			.cmx-artikel-table tbody tr:last-child td {
				border-bottom: none;
			}
			.cmx-artikel-table th {
				cursor: pointer;
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
			.cmx-artikel-table tbody tr:hover {
				background: #fafafa;
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
				color: #222;
			}
			.cmx-artikel-title a:hover {
				text-decoration: underline;
			}
		</style>

		<script>
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
		</script>
		<?php
		$cmx_artikel_assets_done = true;
	}

	echo '<div class="cmx-artikel-table-wrap">';
	echo '<table class="cmx-artikel-table">';
	echo '<thead><tr>';
	echo '<th data-cmx-sort="1" data-cmx-sort-type="string"><span>Bild</span><span class="cmx-sort-indicator"></span></th>';
	echo '<th data-cmx-sort="1" data-cmx-sort-type="string"><span>Artikel</span><span class="cmx-sort-indicator"></span></th>';
	echo '<th data-cmx-sort="1" data-cmx-sort-type="date"><span>Datum</span><span class="cmx-sort-indicator"></span></th>';
	echo '</tr></thead><tbody>';

	while ($query->have_posts()) {
		$query->the_post();
		$post_id = \get_the_ID();

		$thumb_html = \get_the_post_thumbnail(
			$post_id,
			'thumbnail',
			[
				'class' => 'cmx-artikel-thumb',
				'alt'   => esc_attr(\get_the_title($post_id)),
			]
		);
		if (!$thumb_html) {
			$thumb_html = '<span class="cmx-artikel-thumb-placeholder" aria-hidden="true"></span>';
		}

		$date_display = \get_the_date('d.m.Y', $post_id);
		$date_sort    = \get_the_date('c', $post_id);

		echo '<tr>';

		echo '<td data-sort-value="' . esc_attr(strip_tags(\get_the_title($post_id))) . '">' . $thumb_html . '</td>';

		echo '<td class="cmx-artikel-title">';
		echo '<a href="' . esc_url(\get_permalink($post_id)) . '">';
		echo esc_html(\get_the_title($post_id));
		echo '</a>';
		echo '</td>';

		echo '<td data-sort-value="' . esc_attr($date_sort) . '">';
		echo esc_html($date_display);
		echo '</td>';

		echo '</tr>';
	}

	\wp_reset_postdata();

	echo '</tbody></table>';
	echo '</div>';

	return ob_get_clean();
}
