<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


\add_action('wp_dashboard_setup', __NAMESPACE__ . '\\cmx_register_cpt_count_widget');
function cmx_register_cpt_count_widget() {
	\wp_add_dashboard_widget('cmx_cpt_counts_widget','Stammdaten',__NAMESPACE__ . '\\cmx_render_cpt_count_widget');
}

function cmx_render_cpt_count_widget() {
	$cpts_to_show = ['kontakte','artikel','belege','kassenbuch','projekte','dokumente'];

	// CPT-Objekte laden
	$objects = [];
	foreach ($cpts_to_show as $slug) {
		$obj = \get_post_type_object($slug);
		if ($obj) { $objects[$slug] = $obj; }
	}

	if (empty($objects)) {
		echo '<p>' . esc_html__('Keine passenden Inhaltstypen gefunden.', 'default') . '</p>';
		return;
	}

	echo '<style>
		.cmx-cpt-table{width:100%;border-collapse:collapse}
		.cmx-cpt-table th,.cmx-cpt-table td{padding:8px 10px;border-bottom:1px solid #ececec;text-align:left;vertical-align:middle}
		.cmx-cpt-table th{font-weight:600}
		.cmx-cpt-table tr:last-child td{border-bottom:none}
		.cmx-cpt-table td.summe, th.summe { text-align:right; }
		.cmx-cpt-table th.add, .cmx-cpt-table td.add { width:58px; text-align:center; }

		/* Zeilen-Hover-Highlight, damit klar ist, wo das Plus geklickt wird */
		.cmx-cpt-table tbody tr{ transition: background-color .15s ease, box-shadow .15s ease; }
		.cmx-cpt-table tbody tr:hover{
			background:#f3f8ff;
			box-shadow: inset 0 0 0 2px rgba(34,113,177,.15);
		}

		/* Hübscher "Neu"-Button mit echtem SVG-Icon + Tooltip per CSS */
		.cmx-add-link{
			position:relative;
			display:inline-flex; align-items:center; justify-content:center;
			width:34px; height:34px; border-radius:8px;
			background: linear-gradient(#fefefe, #f6f7f7);
			border:1px solid #dcdcde;
			box-shadow: 0 1px 0 rgba(0,0,0,.02);
			color:#1d2327; text-decoration:none;
			transition: all .15s ease;
		}
		.cmx-add-link:hover{
			background:#fff; border-color:#2271b1; color:#2271b1;
			box-shadow: 0 2px 4px rgba(0,0,0,.05);
		}
		.cmx-add-link:active{ transform: translateY(1px); }
		.cmx-add-link svg{ width:18px; height:18px; display:block; fill:currentColor; }
		.cmx-add-link.is-disabled{ opacity:.45; cursor:not-allowed; }

		/* Tooltip (MouseOver) zeigt, für welches Modul geklickt wird */
		.cmx-add-link[data-tip]:hover::after{
			content: attr(data-tip);
			position:absolute;
			bottom: 110%;
			left: 50%;
			transform: translateX(-50%);
			white-space: nowrap;
			background:#1d2327;
			color:#fff;
			padding:4px 8px;
			font-size:11px;
			border-radius:4px;
			box-shadow:0 2px 6px rgba(0,0,0,.15);
			z-index:10;
		}
		.cmx-add-link[data-tip]:hover::before{
			content:"";
			position:absolute;
			bottom: 100%;
			left: 50%;
			transform: translateX(-50%);
			border:6px solid transparent;
			border-top-color:#1d2327;
		}
	</style>';

	echo '<table class="cmx-cpt-table">';
	echo '<thead><tr>
			<th>' . esc_html__('Modul', 'default') . '</th>
			<th class="summe">' . esc_html__('aktiv', 'default') . '</th>
			<th class="add" title="' . esc_attr__('Neu erfassen', 'default') . '"></th>
		</tr></thead>';
	echo '<tbody>';

	foreach ($objects as $slug => $obj) {
		if (!\current_user_can($obj->cap->edit_posts ?? 'edit_posts')) continue;

		$count_obj = \wp_count_posts($slug);
		$count     = isset($count_obj->publish) ? (int) $count_obj->publish : 0;

		$label       = $obj->labels->name ?? $slug;
		$list_url    = \admin_url('edit.php?post_type=' . $slug);
		$create_cap  = $obj->cap->create_posts ?? 'edit_posts';
		$can_create  = ($create_cap !== 'do_not_allow') && \current_user_can($create_cap);
		$add_new_url = $can_create ? \admin_url('post-new.php?post_type=' . $slug) : '';

		echo '<tr>';
		echo '<td><a href="' . esc_url($list_url) . '">' . esc_html($label) . '</a></td>';
		echo '<td class="summe" style="padding-right:15px;">' . esc_html(cmx_format_swiss_number($count, 0)) . '</td>';

		echo '<td class="add">';
		$svg_plus = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path d="M12 5a1 1 0 0 1 1 1v5h5a1 1 0 1 1 0 2h-5v5a1 1 0 1 1-2 0v-5H6a1 1 0 1 1 0-2h5V6a1 1 0 0 1 1-1z"/>
					</svg>';

		$tip = sprintf(__('Neu erfassen: %s', 'default'), $label);

		if ($can_create) {
			echo '<a class="cmx-add-link" href="' . esc_url($add_new_url) . '"
						data-tip="' . esc_attr($tip) . '"
						aria-label="' . esc_attr($tip) . '"
						title="' . esc_attr($tip) . '">'
						. $svg_plus .
				  '</a>';
		} else {
			echo '<span class="cmx-add-link is-disabled"
						data-tip="' . esc_attr__('Keine Berechtigung zum Erstellen', 'default') . '"
						title="' . esc_attr__('Keine Berechtigung zum Erstellen', 'default') . '">'
						. $svg_plus .
				  '</span>';
		}
		echo '</td>';

		echo '</tr>';
	}

	echo '</tbody></table>';
}
