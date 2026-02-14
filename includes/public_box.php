<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


add_action('admin_head', function () {
	echo '<style>.button-full { width:100%; display:block; box-sizing:border-box; text-align:center; } </style>';
});


/**
 * Minimaler „Speichern“-Button statt „Veröffentlichen“-Metabox (Classic Editor)
 * mit sichtbarem „In den Papierkorb verschieben“-Link nach dem Speichern.
 */
add_action('add_meta_boxes', function() {
	$allowed = ['post', 'page', 'kontakte','artikel','belege','kassenbuch','dokumente','projekte','ausgaben'];
	$screen = get_current_screen();
	if (!$screen || !in_array($screen->post_type, $allowed, true)) return;

	$box_title = ($screen->post_type === 'belege')
		? __('Beleg speichern ...', 'default')
		: __('Aktion', 'default');

	add_meta_box('cmx_savebox', $box_title,
		function($post) use ($screen) {

			$is_new       = ($post->ID === 0 || $post->post_status === 'auto-draft');
			$post_type    = $screen->post_type;
			$is_belege    = ($post_type === 'belege');
			$is_add_screen = (($screen->action ?? '') === 'add');
			$pt_obj       = get_post_type_object($post_type);
			$singular     = $pt_obj->labels->singular_name ?? '';

			// Fallback-Mapping, falls das Label plural ist oder fehlt
			$singular_map = [
				'kontakte'    => 'Kontakt',
				'artikel'     => 'Artikel',
				'belege'      => 'Beleg',
				'kassenbuch'  => 'Kassenbuch',
				'dokumente'   => 'Dokument',
				'projekte'    => 'Projekt',
				'post'        => __('Beitrag', 'default'),
				'page'        => __('Seite', 'default'),
			];
			if ($singular === '' || strcasecmp($singular, $pt_obj->labels->name ?? '') === 0) {
				$singular = $singular_map[$post_type] ?? ucfirst($post_type);
			}

			$btn_label    = sprintf('%s speichern', $singular);
			$btn_name     = $is_new ? 'publish' : 'save';
			$save_as_opts = [
				'rechnung'     => 'als Rechnung speichern',
				'offerte'      => 'als Offerte speichern',
				'lieferschein' => 'als Lieferschein duplizieren',
				'rechnung_kopie' => 'als Rechnung duplizieren',
			];
			$save_as_val = 'rechnung';
			$send_href = '';
			$download_url = '';
			$has_pdf = false;
			if ($is_belege && function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_pdf_paths')) {
				[, $pdf_abs_path] = cmxbu_get_beleg_pdf_paths($post);
				$has_pdf = is_file($pdf_abs_path);
				if ($has_pdf) {
					$send_href = esc_url(admin_url('admin-post.php?action=cmxbu_beleg_send&post_id='.(int)$post->ID));
					if (function_exists(__NAMESPACE__ . '\\cmxbu_get_stable_token')) {
						$token = cmxbu_get_stable_token($post->ID);
						$download_url = esc_url(add_query_arg('beleg', $token, home_url('/')));
					}
				}
			}

			echo '<div style="padding:12px 0;">';
			if ($is_belege) {
				$hide_save_as = false;
				$active_slug = '';
				if (function_exists(__NAMESPACE__ . '\\cmx_belege_tax')) {
					$tax = cmx_belege_tax();
					if ($tax) {
						$slugs = wp_get_post_terms($post->ID, $tax, ['fields' => 'slugs']);
						if (!is_wp_error($slugs) && !empty($slugs)) {
							$active_slug = (string) $slugs[0];
						}
					}
				}
				if ($active_slug === 'offerte') {
					$save_as_val = 'offerte';
				} else {
					$save_as_val = 'rechnung';
				}
				wp_nonce_field('cmx_beleg_save_as', 'cmx_beleg_save_as_nonce');
				echo '<div id="cmx_beleg_save_as_wrap" style="margin-bottom:8px;' . ($hide_save_as ? 'display:none;' : '') . '">';
				echo '<select name="cmx_beleg_save_as" id="cmx_beleg_save_as" style="width:100%;">';
				foreach ($save_as_opts as $val => $label) {
					echo '<option value="'.esc_attr($val).'" '.selected($save_as_val, $val, false).'>'.esc_html($label).'</option>';
				}
				echo '</select>';
				echo '</div>';
				echo '<script>(function(){var wrap=document.getElementById("cmx_beleg_save_as_wrap");var sel=document.getElementById("cmx_beleg_save_as");if(!wrap||!sel)return;var optRech=sel.querySelector("option[value=rechnung]");var optOff=sel.querySelector("option[value=offerte]");var optLief=sel.querySelector("option[value=lieferschein]");var optRechCopy=sel.querySelector("option[value=rechnung_kopie]");function getSlug(){var el=document.querySelector("input[name=cmx_beleg_kategorie]:checked");return el?(el.getAttribute("data-slug")||""):"";}function getRichtung(){var el=document.querySelector("input[name=cmx_beleg_richtung]:checked");return el?el.value:"";}function setOpt(opt, show){if(!opt)return;opt.disabled=!show;opt.hidden=!show;}function sync(){var slug=getSlug();var richtung=getRichtung();if((slug==="rechnung"||slug==="offerte")&&richtung==="ausgang"){wrap.style.display="";if(slug==="rechnung"){setOpt(optRech,true);setOpt(optLief,true);setOpt(optOff,false);setOpt(optRechCopy,false);sel.value="rechnung";}else{setOpt(optOff,true);setOpt(optRechCopy,true);setOpt(optRech,false);setOpt(optLief,false);sel.value="offerte";}}else{wrap.style.display="none";}}document.addEventListener("change",function(e){if(e.target&&(e.target.name==="cmx_beleg_kategorie"||e.target.name==="cmx_beleg_richtung")){sync();}});document.addEventListener("DOMContentLoaded",function(){sync();setTimeout(sync,200);});setTimeout(sync,0);})();</script>';
			}
			echo '<div style="display:flex; align-items:center; gap:8px;">';
			printf(
				'<input type="submit" name="%1$s" id="publish" class="button button-primary button-large button-full" value="%2$s" />',
				esc_attr($btn_name),
				esc_attr($btn_label)
			);
			if ($is_belege && $send_href !== '') {
				echo '<a href="'.$send_href.'" title="PDF-Link per Mail versenden" class="button button-secondary" style="height:36px; display:inline-flex; align-items:center; justify-content:center;"><span class="dashicons dashicons-email" style="margin-top:2px;"></span></a>';
			}
			echo '</div>';
			echo '</div>';

				if ($is_belege && $post->ID) {
					$tax = function_exists(__NAMESPACE__ . '\\cmx_belege_tax') ? cmx_belege_tax() : '';
					$from_id = (int) get_post_meta($post->ID, '_cmx_beleg_copied_from', true);
					$to_id = (int) get_post_meta($post->ID, '_cmx_beleg_copied_to', true);
					$current_slug = '';
					if ($tax) {
						$current_slugs = wp_get_post_terms($post->ID, $tax, ['fields' => 'slugs']);
						if (!is_wp_error($current_slugs) && !empty($current_slugs)) {
							$current_slug = (string) $current_slugs[0];
						}
					}
					$is_rechnung = in_array($current_slug, ['rechnung', 'rechnungen'], true);

					if ($from_id > 0) {
						$from_link = get_edit_post_link($from_id, '');
						$from_cat = '';
						if ($tax) {
							$from_terms = wp_get_post_terms($from_id, $tax, ['fields' => 'names']);
							if (!is_wp_error($from_terms) && !empty($from_terms)) {
								$from_cat = (string) $from_terms[0];
							}
						}
						if ($from_link && $from_cat !== '') {
							echo '<div style="margin-top:0px; font-size:12px;">';
							echo 'von ' . esc_html($from_cat) . ': <a href="' . esc_url($from_link) . '">' . esc_html(get_the_title($from_id)) . '</a>';
							echo '</div>';
						}
					}

					if ($is_rechnung) {
						$liefer_ids = [];

						$linked_args = [
							'post_type' => 'belege',
							'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
							'fields' => 'ids',
							'posts_per_page' => -1,
							'no_found_rows' => true,
							'suppress_filters' => true,
							'orderby' => ['date' => 'ASC', 'ID' => 'ASC'],
							'order' => 'ASC',
							'meta_query' => [[
								'key' => '_cmx_beleg_copied_from',
								'value' => (int) $post->ID,
								'compare' => '=',
							]],
						];
						if ($tax) {
							$linked_args['tax_query'] = [[
								'taxonomy' => $tax,
								'field' => 'slug',
								'terms' => ['lieferschein', 'lieferscheine'],
							]];
						}
						$linked_ids = get_posts($linked_args);
						if (is_array($linked_ids)) {
							foreach ($linked_ids as $lid) {
								$lid = (int) $lid;
								if ($lid > 0 && $lid !== (int) $post->ID) {
									$liefer_ids[] = $lid;
								}
							}
						}

						// Legacy-Fallback: einzelne alte Verknüpfung ergänzen, falls sie in der Query fehlt.
						if ($to_id > 0 && !in_array((int) $to_id, $liefer_ids, true)) {
							$to_post = get_post($to_id);
							if ($to_post && $to_post->post_type === 'belege' && $to_post->post_status !== 'trash') {
								if ($tax) {
									$to_slugs = wp_get_post_terms($to_id, $tax, ['fields' => 'slugs']);
									if (!is_wp_error($to_slugs) && array_intersect(['lieferschein', 'lieferscheine'], array_map('strval', (array) $to_slugs))) {
										$liefer_ids[] = (int) $to_id;
									}
								} else {
									$liefer_ids[] = (int) $to_id;
								}
							}
						}

						$liefer_ids = array_values(array_unique(array_map('intval', $liefer_ids)));
						if (!empty($liefer_ids)) {
							$links = [];
							foreach ($liefer_ids as $lid) {
								$edit_link = get_edit_post_link($lid, '');
								if (!$edit_link) {
									continue;
								}
								$links[] = '<a href="' . esc_url($edit_link) . '">' . esc_html(get_the_title($lid)) . '</a>';
							}
							if (!empty($links)) {
								echo '<div style="margin-top:0px; font-size:12px;">';
								echo (count($links) > 1 ? 'zu Lieferscheinen: ' : 'zu Lieferschein: ') . implode(', ', $links);
								echo '</div>';
							}
						}
					} elseif ($to_id > 0) {
						$to_link = get_edit_post_link($to_id, '');
						$to_cat = '';
						if ($tax) {
							$to_terms = wp_get_post_terms($to_id, $tax, ['fields' => 'names']);
							if (!is_wp_error($to_terms) && !empty($to_terms)) {
								$to_cat = (string) $to_terms[0];
							}
						}
						if ($to_link && $to_cat !== '') {
							echo '<div style="margin-top:0px; font-size:12px;">';
							echo 'zu ' . esc_html($to_cat) . ': <a href="' . esc_url($to_link) . '">' . esc_html(get_the_title($to_id)) . '</a>';
							echo '</div>';
						}
					}
			}

			// Icons: Duplizieren + Papierkorb (ohne Text)
			$has_uploads = false;
			if ($is_belege && $post->ID) {
				$meta = (array) get_post_meta($post->ID, '_cmx_belege_uploads', true);
				$meta = array_values(array_filter($meta, function($v){ return $v !== '' && $v !== null; }));
				$has_uploads = !empty($meta);
			}
			$show_actions = ($post->ID && ($post->post_status !== 'auto-draft' || $has_uploads) && ($is_belege || !$is_add_screen));
			if ($show_actions) {
				$delete_link = get_delete_post_link($post->ID);
				$dup_fn = __NAMESPACE__ . '\\cmx_dup_get_action_url';
				$dup_link = is_callable($dup_fn) ? $dup_fn((int)$post->ID) : '';

				$show_pdf_icons = ($is_belege && $has_pdf && $download_url !== '');
				if ($delete_link || $dup_link !== '' || $show_pdf_icons) {
					$justify = $is_belege ? 'space-between' : 'flex-start';
					echo '<div style="margin-top:10px; padding-top:6px; border-top:1px solid #ddd; display:flex; justify-content:'.$justify.'; align-items:center; gap:8px;">';
					if ($dup_link !== '') {
						echo '<a href="'.esc_url($dup_link).'" class="cmx-dup-link dashicons dashicons-clipboard" style="text-decoration:none;" title="'.esc_attr__('Duplizieren','default').'"><span class="screen-reader-text">'.esc_html__('Duplizieren','default').'</span></a>';
					}
						if ($show_pdf_icons) {
							echo '<a href="' . esc_url($download_url) . '" class="cmx-pdf-link" style="text-decoration:none;" title="Download als PDF" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-pdf" style="margin-top:5px;"></span></a>';
						}
					if ($delete_link) {
						$delete_style = $is_belege
							? 'color:#b32d2e; text-decoration:none;'
							: 'color:#b32d2e; text-decoration:none; margin-left:auto;';
						echo '<a href="'.esc_url($delete_link).'" class="submitdelete deletion dashicons dashicons-trash" style="'.$delete_style.'" title="'.esc_attr__('In den Papierkorb verschieben', 'default').'"><span class="screen-reader-text">'.esc_html__('In den Papierkorb verschieben', 'default').'</span></a>';
					}
					echo '</div>';
					}
				}
		},
		$screen->post_type,
		'side',
		'high'
	);
});



// Classic-Editor: "Website verlassen?" komplett unterdrücken – ohne deinen bestehenden Code zu ändern.
add_action('admin_footer', function () {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || !in_array($screen->base, ['post','post-new'], true)) return;

	// Falls du einschränken willst, hier Post Types anpassen:
	$targets = ['post','page','kontakte','belege'];
	if (!in_array($screen->post_type, $targets, true)) return;
	?>
	<script>
	(function(){
		function killPrompt(){ try { window.onbeforeunload = null; } catch(e){} }

		// 1) Sofort bestehende Handler entfernen
		killPrompt();

		// 2) Setzen von window.onbeforeunload unterbinden
		try {
			Object.defineProperty(window, 'onbeforeunload', {
				configurable: true,
				get: function(){ return null; },
				set: function(_){ /* block */ }
			});
		} catch(e){}

		// 3) Registrierungen von beforeunload-Listenern blockieren
		(function(){
			var _add = window.addEventListener;
			window.addEventListener = function(type, listener, options){
				if (type === 'beforeunload') return; // ignorieren
				return _add.call(this, type, listener, options);
			};
		})();

		// 4) Falls bereits Listener dran sind: in Capture-Phase davor abfangen
		window.addEventListener('beforeunload', function(ev){
			// Wichtig: KEIN preventDefault aufrufen, sonst provozieren manche Browser den Prompt erst.
			try { delete ev.returnValue; } catch(e){}
			ev.stopImmediatePropagation();
			ev.stopPropagation();
		}, { capture:true });

		// 5) Zusätzliche Absicherung: regelmäßig neutralisieren (falls sehr spätes Setzen passiert)
		var killer = setInterval(killPrompt, 1500);

		// 6) Beim Speichern/Shortcut ebenfalls aufräumen (harmlos, aber sicher)
		document.addEventListener('DOMContentLoaded', function(){
			var form = document.getElementById('post');
			if (form) form.addEventListener('submit', killPrompt, { capture:true });

			document.querySelectorAll('#publish,#save-post').forEach(function(el){
				el.addEventListener('click', killPrompt, { capture:true });
			});

			document.addEventListener('keydown', function(e){
				if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.keyCode === 83)) killPrompt();
			}, { capture:true });
		});

		// 7) Cleanup
		window.addEventListener('pagehide', function(){
			clearInterval(killer);
			killPrompt();
		}, { capture:true });
	})();
	</script>
	<?php
});




// Originale "Veröffentlichen"- und "Titelform"-Metaboxen für definierte CPTs entfernen
add_action('add_meta_boxes', function () {
	$allowed = ['post', 'page', 'kontakte','artikel','belege','kassenbuch','dokumente','projekte','ausgaben'];
	$screen  = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || !in_array($screen->post_type, $allowed, true)) return;

	// Entfernt die klassische "Veröffentlichen"-Box
	remove_meta_box('submitdiv', $screen->post_type, 'side');
	remove_meta_box('submitdiv', $screen->post_type, 'normal');
	remove_meta_box('submitdiv', $screen->post_type, 'advanced');

	// Entfernt die Metabox "Titelform" (Slug unterhalb des Titels)
	remove_meta_box('slugdiv', $screen->post_type, 'normal');
}, 100);

// Sicherheitshalber auch beim späteren Rendering (falls Plugins sie reaktivieren)
add_action('do_meta_boxes', function ($post_type) {
	$allowed = ['post', 'page', 'kontakte', 'belege'];
	if (!in_array($post_type, $allowed, true)) return;

	remove_meta_box('submitdiv', $post_type, 'side');
	remove_meta_box('submitdiv', $post_type, 'normal');
	remove_meta_box('submitdiv', $post_type, 'advanced');
	remove_meta_box('slugdiv', $post_type, 'normal');
}, 100);
