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
				'rechnung'    => 'als Rechnung',
				'angebot'     => 'als Angebot',
				'lieferschein'=> 'als Lieferschein',
			];
			$save_as_val = (string) get_post_meta($post->ID, '_cmx_beleg_pdf_type', true);
			if (!isset($save_as_opts[$save_as_val])) {
				$save_as_val = 'rechnung';
			}
			$send_href = '';
			if ($is_belege && function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_pdf_paths')) {
				[, $pdf_abs_path] = cmxbu_get_beleg_pdf_paths($post);
				if (is_file($pdf_abs_path)) {
					$send_href = esc_url(admin_url('admin-post.php?action=cmxbu_beleg_send&post_id='.(int)$post->ID));
				}
			}

			echo '<div style="padding:12px 0;">';
			if ($is_belege) {
				wp_nonce_field('cmx_beleg_save_as', 'cmx_beleg_save_as_nonce');
				echo '<select name="cmx_beleg_save_as" style="width:100%; margin-bottom:8px;">';
				foreach ($save_as_opts as $val => $label) {
					echo '<option value="'.esc_attr($val).'" '.selected($save_as_val, $val, false).'>'.esc_html($label).'</option>';
				}
				echo '</select>';
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

			// Icons: Duplizieren + Papierkorb (ohne Text)
			if ($post->ID && $post->post_status !== 'auto-draft') {
				$delete_link = get_delete_post_link($post->ID, '', true);
				$dup_fn = __NAMESPACE__ . '\\cmx_dup_get_action_url';
				$dup_link = is_callable($dup_fn) ? $dup_fn((int)$post->ID) : '';

				if ($delete_link || $dup_link !== '') {
					echo '<div style="margin-top:10px; padding-top:6px; border-top:1px solid #ddd; display:flex; justify-content:space-between; align-items:center; gap:8px;">';
					if ($dup_link !== '') {
						echo '<a href="'.esc_url($dup_link).'" class="cmx-dup-link dashicons dashicons-clipboard" style="text-decoration:none;" title="'.esc_attr__('Duplizieren','default').'"><span class="screen-reader-text">'.esc_html__('Duplizieren','default').'</span></a>';
					}
					if ($delete_link) {
						echo '<a href="'.esc_url($delete_link).'" class="submitdelete deletion dashicons dashicons-trash" style="color:#b32d2e; text-decoration:none;" title="'.esc_attr__('In den Papierkorb verschieben', 'default').'"><span class="screen-reader-text">'.esc_html__('In den Papierkorb verschieben', 'default').'</span></a>';
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
