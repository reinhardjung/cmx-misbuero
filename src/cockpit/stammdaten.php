<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


\add_action('wp_dashboard_setup', __NAMESPACE__ . '\\cmx_register_cpt_count_widget');
function cmx_register_cpt_count_widget() {
	\wp_add_dashboard_widget('cmx_cpt_counts_widget','Stammdaten',__NAMESPACE__ . '\\cmx_render_cpt_count_widget');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_stammdaten_beleg_type_options')) {
	function cmx_cockpit_stammdaten_beleg_type_options(): array {
		if (\function_exists(__NAMESPACE__ . '\\cmx65_adminbar_beleg_type_options')) {
			return (array) cmx65_adminbar_beleg_type_options();
		}

		return [
			'rechnung'     => 'Rechnung',
			'lieferschein' => 'Lieferschein',
			'quittung'     => 'Quittung',
			'gutschrift'   => 'Gutschrift',
			'offerte'      => 'Offerte',
		];
	}
}

\add_action('admin_post_cmx_cockpit_create_beleg', __NAMESPACE__ . '\\cmx_cockpit_stammdaten_create_beleg_handler');
if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_stammdaten_create_beleg_handler')) {
	function cmx_cockpit_stammdaten_create_beleg_handler(): void {
		$redirect_url = (string) (\wp_get_referer() ?: \admin_url());
		if (!\is_admin() || !\current_user_can('edit_posts')) {
			\wp_safe_redirect($redirect_url);
			exit;
		}
		if (!isset($_POST['_wpnonce']) || !\wp_verify_nonce((string) \wp_unslash($_POST['_wpnonce']), 'cmx_cockpit_create_beleg')) {
			\wp_die('Ungültige Anfrage.');
		}
		if (!\post_type_exists('belege')) {
			\wp_safe_redirect($redirect_url);
			exit;
		}

		$post_type_object = \get_post_type_object('belege');
		$create_cap = (string) ($post_type_object->cap->create_posts ?? 'edit_posts');
		if ($create_cap === 'do_not_allow' || !\current_user_can($create_cap)) {
			\wp_safe_redirect($redirect_url);
			exit;
		}

		$beleg_typ_optionen = cmx_cockpit_stammdaten_beleg_type_options();
		$beleg_typ = isset($_POST['beleg_typ']) ? \sanitize_key((string) \wp_unslash($_POST['beleg_typ'])) : 'rechnung';
		$allowed_beleg_typen = \array_keys($beleg_typ_optionen);
		if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_kategorie_allowed_slugs')) {
			$allowed_beleg_typen = \array_values(\array_filter(\array_map(
				static function ($slug): string {
					return \sanitize_key((string) $slug);
				},
				(array) cmx_beleg_kategorie_allowed_slugs()
			), static function (string $slug): bool {
				return $slug !== '';
			}));
		}
		if ($beleg_typ === '' || !\in_array($beleg_typ, $allowed_beleg_typen, true)) {
			$beleg_typ = 'rechnung';
		}

		$richtung_meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_RICHTUNG')
			? CMX_BELEG_META_RICHTUNG
			: '_cmx_beleg_richtung';

		$beleg_id = \wp_insert_post([
			'post_type'   => 'belege',
			'post_status' => 'auto-draft',
			'post_title'  => '',
			'post_author' => (int) \get_current_user_id(),
			'meta_input'  => [
				'_cmx_title_auto' => 1,
				$richtung_meta_key => $beleg_typ === 'gutschrift' ? 'eingang' : 'ausgang',
			],
		], true);

		if (\is_wp_error($beleg_id) || (int) $beleg_id <= 0) {
			\wp_safe_redirect($redirect_url);
			exit;
		}

		$beleg_tax = \function_exists(__NAMESPACE__ . '\\cmx_belege_tax')
			? cmx_belege_tax()
			: (\function_exists(__NAMESPACE__ . '\\cmx_belege_kategorie_taxonomy') ? cmx_belege_kategorie_taxonomy() : null);
		if (\is_string($beleg_tax) && $beleg_tax !== '' && \taxonomy_exists($beleg_tax)) {
			$term = \get_term_by('slug', $beleg_typ, $beleg_tax);
			if ((!$term || \is_wp_error($term)) && isset($beleg_typ_optionen[$beleg_typ])) {
				$inserted_term = \wp_insert_term($beleg_typ_optionen[$beleg_typ], $beleg_tax, ['slug' => $beleg_typ]);
				if (!\is_wp_error($inserted_term) && !empty($inserted_term['term_id'])) {
					$term = \get_term((int) $inserted_term['term_id'], $beleg_tax);
				}
			}
			if ($term && !\is_wp_error($term) && !empty($term->term_id)) {
				\wp_set_post_terms((int) $beleg_id, [(int) $term->term_id], $beleg_tax, false);
			}
		}

		$edit_url = (string) \admin_url('post.php?post=' . (int) $beleg_id . '&action=edit');
		$edit_url = (string) \add_query_arg('cmx_beleg_typ', $beleg_typ, $edit_url);
		\wp_safe_redirect($edit_url);
		exit;
	}
}

function cmx_render_cpt_count_widget() {
	$cpts_to_show = ['kontakte','artikel','belege','kassenbuch','projekte','dokumente'];
	$beleg_type_optionen = cmx_cockpit_stammdaten_beleg_type_options();
	$beleg_create_action_url = (string) \admin_url('admin-post.php');
	$beleg_create_nonce = (string) \wp_create_nonce('cmx_cockpit_create_beleg');

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
		button.cmx-add-link{
			-webkit-appearance:none;
			appearance:none;
			padding:0;
			font:inherit;
			cursor:pointer;
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
		.cmx-add-wrap{
			position:relative;
			display:inline-flex;
			align-items:center;
			justify-content:flex-end;
		}
		.cmx-beleg-picker[hidden]{display:none !important}
		.cmx-beleg-picker{
			position:absolute;
			top:calc(100% + 6px);
			right:0;
			min-width:180px;
			padding:6px;
			background:#fff;
			border:1px solid #ccd0d4;
			border-radius:8px;
			box-shadow:0 16px 30px rgba(0,0,0,.18);
			z-index:1002;
		}
		.cmx-beleg-type-list{
			display:block;
			margin:0;
			padding:0;
			outline:none;
		}
		.cmx-beleg-type-option{
			display:block;
			width:100%;
			box-sizing:border-box;
			margin:0;
			padding:8px 12px;
			border:0;
			border-radius:6px;
			background:transparent;
			color:#1d2327;
			font-size:13px;
			line-height:1.25;
			text-align:left;
			cursor:pointer;
			box-shadow:none;
		}
		.cmx-beleg-type-option + .cmx-beleg-type-option{margin-top:2px}
		.cmx-beleg-type-option:hover,
		.cmx-beleg-type-option:focus,
		.cmx-beleg-type-option.active{
			background:#f5d6cf;
			color:#1d2327;
			outline:none;
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
			if ($slug === 'belege') {
				echo '<div class="cmx-add-wrap" data-cmx-beleg-picker="1">';
				echo '<button type="button" class="cmx-add-link cmx-beleg-picker-toggle"
							data-tip="' . esc_attr($tip) . '"
							aria-label="' . esc_attr($tip) . '"
							aria-haspopup="listbox"
							aria-expanded="false"
							aria-controls="cmx-dashboard-beleg-type-list">'
							. $svg_plus .
					  '</button>';
				echo '<form class="cmx-dashboard-beleg-create-form" method="post" action="' . \esc_url($beleg_create_action_url) . '">';
				echo '<input type="hidden" name="action" value="cmx_cockpit_create_beleg">';
				echo '<input type="hidden" name="_wpnonce" value="' . \esc_attr($beleg_create_nonce) . '">';
				echo '<div class="cmx-beleg-picker" hidden>';
				echo '<div id="cmx-dashboard-beleg-type-list" class="cmx-beleg-type-list" role="listbox" aria-label="Belegart auswählen">';
				foreach ($beleg_type_optionen as $beleg_typ_slug => $beleg_typ_label) {
					echo '<button type="submit" class="cmx-beleg-type-option" role="option" name="beleg_typ" value="' . \esc_attr($beleg_typ_slug) . '" data-value="' . \esc_attr($beleg_typ_slug) . '">' . \esc_html($beleg_typ_label) . '</button>';
				}
				echo '</div>';
				echo '</div>';
				echo '</form>';
				echo '</div>';
			} else {
				echo '<a class="cmx-add-link" href="' . esc_url($add_new_url) . '"
							data-tip="' . esc_attr($tip) . '"
							aria-label="' . esc_attr($tip) . '">'
							. $svg_plus .
					  '</a>';
			}
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
	echo '<script>(function(){var widget=document.getElementById("cmx_cpt_counts_widget");if(!widget){return;}var wrap=widget.querySelector("[data-cmx-beleg-picker=\\"1\\"]");if(!wrap){return;}var toggle=wrap.querySelector(".cmx-beleg-picker-toggle");var picker=wrap.querySelector(".cmx-beleg-picker");var typeList=wrap.querySelector(".cmx-beleg-type-list");var typeItems=typeList?Array.prototype.slice.call(typeList.querySelectorAll(".cmx-beleg-type-option[data-value]")):[];var typeActive=0;var typeOpen=false;if(!toggle||!picker||!typeList||!typeItems.length){return;}function sync(){typeItems.forEach(function(item,index){var active=index===typeActive;item.classList.toggle("active",active);item.setAttribute("aria-selected",active?"true":"false");});var activeItem=typeItems[typeActive];if(activeItem){typeList.setAttribute("aria-activedescendant",activeItem.id||"");if(activeItem.scrollIntoView){activeItem.scrollIntoView({block:"nearest"});}}}function close(restoreFocus){picker.hidden=true;typeOpen=false;toggle.setAttribute("aria-expanded","false");typeList.removeAttribute("aria-activedescendant");if(restoreFocus){toggle.focus();}}function open(){typeActive=0;sync();picker.hidden=false;typeOpen=true;toggle.setAttribute("aria-expanded","true");window.setTimeout(function(){var activeItem=typeItems[typeActive];if(activeItem){activeItem.focus();}},0);}function setActive(index,focusItem){typeActive=(index+typeItems.length)%typeItems.length;sync();if(focusItem){var activeItem=typeItems[typeActive];if(activeItem){activeItem.focus();}}}function move(direction,focusItem){setActive(typeActive+direction,focusItem);}typeItems.forEach(function(item,index){item.id="cmx-dashboard-beleg-type-option-"+index;item.addEventListener("mouseenter",function(){setActive(index,false);});item.addEventListener("focus",function(){setActive(index,false);});item.addEventListener("keydown",function(event){if(!typeOpen){return;}if(event.key==="ArrowDown"){event.preventDefault();move(1,true);return;}if(event.key==="ArrowUp"){event.preventDefault();move(-1,true);return;}if(event.key==="Escape"){event.preventDefault();close(true);}});});toggle.addEventListener("click",function(event){event.preventDefault();if(typeOpen){close(true);return;}open();});toggle.addEventListener("keydown",function(event){if(event.key==="ArrowDown"||event.key==="ArrowUp"||event.key==="Enter"||event.key===" "){event.preventDefault();open();}});document.addEventListener("click",function(event){if(!typeOpen){return;}if(!wrap.contains(event.target)){close(false);}});})();</script>';
}
