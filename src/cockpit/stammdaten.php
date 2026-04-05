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
			#cmx_cpt_counts_widget,
			#cmx_cpt_counts_widget .inside,
			#cmx_cpt_counts_widget .cmx-cpt-table,
		#cmx_cpt_counts_widget .cmx-cpt-table tbody,
			#cmx_cpt_counts_widget .cmx-cpt-table tr,
			#cmx_cpt_counts_widget .cmx-cpt-table td{
				overflow:visible;
			}
			#cmx_cpt_counts_widget{
				border-radius:12px;
			}
			#cmx_cpt_counts_widget .postbox-header{
				border-top-left-radius:12px;
				border-top-right-radius:12px;
			}
			#cmx_cpt_counts_widget .inside{
				border-bottom-left-radius:12px;
				border-bottom-right-radius:12px;
			}
			.cmx-cpt-table{width:100%;border-collapse:collapse;table-layout:fixed}
		.cmx-cpt-table td{padding:6px 10px;text-align:left;vertical-align:middle;font-size:14px;border:0}
		.cmx-cpt-table td.summe{text-align:right;width:68px}
		.cmx-cpt-table td.add{width:42px;text-align:right;padding-right:4px}
		.cmx-cpt-module-link{
			display:inline-block;
			font-weight:700;
			font-size:15px;
			line-height:1.2;
			color:#135eaf;
			text-decoration:none;
		}
		.cmx-cpt-module-link:hover{text-decoration:underline}
		.cmx-cpt-count-pill{
			display:inline-flex;
			align-items:center;
			justify-content:center;
			min-width:30px;
			padding:2px 8px;
			border-radius:999px;
			background:#eef4fb;
			color:#264b6f;
			font-weight:700;
			font-size:13px;
			line-height:1.2;
		}
		.cmx-cpt-table tbody tr{transition:background-color .15s ease}
		.cmx-cpt-table tbody tr:hover{background:#f7fbff}
		.cmx-cpt-table tbody tr:hover .cmx-cpt-count-pill{
			background:#e4eef9;
			color:#135eaf;
		}
		.cmx-add-link{
			position:relative;
			display:inline-flex;
			align-items:center;
			justify-content:center;
			width:30px;
			height:30px;
			border-radius:8px;
			background:#f7fafc;
			border:1px solid #d7e2ee;
			box-shadow:0 1px 3px rgba(18,52,86,.05);
			color:#30587a;
			text-decoration:none;
			transition:all .15s ease;
		}
		.cmx-add-link:hover{
			background:#eef6ff;
			border-color:#9fbddd;
			color:#135eaf;
			z-index:1000;
		}
		.cmx-add-link:active{transform:translateY(1px)}
		.cmx-add-link svg{width:16px;height:16px;display:block;fill:currentColor}
		.cmx-add-link.is-disabled{opacity:.45;cursor:not-allowed}
		.cmx-add-link[data-tip]:hover::after{
			content: attr(data-tip);
			position:absolute;
			bottom:110%;
			left:50%;
			transform:translateX(-50%);
			white-space:nowrap;
			background:#1d2327;
			color:#fff;
			padding:4px 8px;
			font-size:11px;
			border-radius:4px;
			box-shadow:0 2px 6px rgba(0,0,0,.15);
			z-index:1001;
		}
		.cmx-add-link[data-tip]:hover::before{
			content:"";
			position:absolute;
			bottom:100%;
			left:50%;
			transform:translateX(-50%);
			border:6px solid transparent;
			border-top-color:#1d2327;
			z-index:1001;
		}
	</style>';

	echo '<table class="cmx-cpt-table">';
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
		echo '<td><a class="cmx-cpt-module-link" href="' . esc_url($list_url) . '">' . esc_html($label) . '</a></td>';
		echo '<td class="summe" style="padding-right:15px;"><span class="cmx-cpt-count-pill">' . esc_html(cmx_format_swiss_number($count, 0)) . '</span></td>';

		echo '<td class="add">';
		$svg_plus = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path d="M12 5a1 1 0 0 1 1 1v5h5a1 1 0 1 1 0 2h-5v5a1 1 0 1 1-2 0v-5H6a1 1 0 1 1 0-2h5V6a1 1 0 0 1 1-1z"/>
					</svg>';

		$tip = sprintf(__('Neu erfassen: %s', 'default'), $label);

		if ($can_create) {
			echo '<a class="cmx-add-link" href="' . esc_url($add_new_url) . '"
						data-tip="' . esc_attr($tip) . '"
						aria-label="' . esc_attr($tip) . '">'
						. $svg_plus .
				  '</a>';
		} else {
			echo '<span class="cmx-add-link is-disabled"
						data-tip="' . esc_attr__('Keine Berechtigung zum Erstellen', 'default') . '">'
						. $svg_plus .
				  '</span>';
		}
		echo '</td>';

		echo '</tr>';
	}

	echo '</tbody></table>';
}
