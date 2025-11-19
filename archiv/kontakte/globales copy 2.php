<?php
/**
 * CMX – Submitdiv: Button zuerst, Papierkorb-Link darunter (Classic Editor)
 * - Re-Add mit Core-Callback (Struktur/IDs bleiben intakt)
 * - CSS blendet Rest aus
 * - JS verschiebt #delete-action NACH #major-publishing-actions
 */

namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit;

/** Ziel-CPTs anpassen falls nötig */
function cmx_get_target_post_types(): array {
	$cpts = \get_post_types(['show_ui' => true, '_builtin' => false], 'names');
	$extra = ['kontakte', 'eingangsbeleg', 'belege', 'lieferantenprodukt'];
	foreach ($extra as $pt) {
		if (\post_type_exists($pt) && !\in_array($pt, $cpts, true)) $cpts[] = $pt;
	}
	$cpts = (array) \apply_filters('cmx_submitdiv_post_types', $cpts);
	return \array_values(\array_filter($cpts, '\post_type_exists'));
}

/** 1) submitdiv korrekt registrieren (Core-Callback) */
\add_action('add_meta_boxes', function() {
	$post_types = cmx_get_target_post_types();
	if (empty($post_types)) return;

	foreach ($post_types as $pt) {
		\remove_meta_box('submitdiv', $pt, 'side');
		\remove_meta_box('submitdiv', $pt, 'normal');
		\remove_meta_box('submitdiv', $pt, 'advanced');

		\add_meta_box(
			'submitdiv',
			__('Veröffentlichen'),
			'\post_submit_meta_box',
			$pt,
			'side',
			'high'
		);
	}
}, 99);

/** 2) UI reduzieren (nur Button + Papierkorb sichtbar) */
\add_action('admin_head', function () {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || !\in_array($screen->base, ['post','post-new'], true)) return;
	if (!\in_array($screen->post_type, cmx_get_target_post_types(), true)) return;

	echo '<style id="cmx-submitdiv-minimal-ui">
		/* Nur relevante Elemente zeigen */
		#submitdiv #misc-publishing-actions,
		#submitdiv #save-post { display:none !important; }

		/* Wir verschieben #delete-action aus #minor-publishing-actions heraus,
		   danach kann der komplette Bereich ausgeblendet werden */
		#submitdiv #minor-publishing-actions { display:none !important; }

		/* Etwas Luft oben */
		#submitdiv .inside { padding-top:8px; }
	</style>';
});

/** 3) Reihenfolge sicher tauschen: Papierkorb-Container NACH die gesamte Major-Box setzen */
\add_action('admin_footer', function () {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || !\in_array($screen->base, ['post','post-new'], true)) return;
	if (!\in_array($screen->post_type, cmx_get_target_post_types(), true)) return;

	// Nur Classic-Editor (submitdiv-Struktur existiert dort)
	if (\function_exists('use_block_editor_for_post_type') && \use_block_editor_for_post_type($screen->post_type)) return;
	?>
	<script>
	(function(){
		function onReady(cb){ if(document.readyState!=='loading'){ cb(); } else { document.addEventListener('DOMContentLoaded', cb, {once:true}); } }

		function placeTrashBelow(){
			var submitDiv   = document.getElementById('submitdiv');
			if (!submitDiv) return false;

			var majorBox = submitDiv.querySelector('#major-publishing-actions'); // kompletter Bereich mit Button
			var deleteWrap = document.getElementById('delete-action');           // Container mit <a class="submitdelete">
			if (!majorBox || !deleteWrap) return false; // Papierkorb existiert nur bei gespeicherten Posts

			// Papierkorb-Container NACH die gesamte Major-Box setzen (nicht daneben),
			// damit CSS-Floats der Core-Box nicht die Reihenfolge optisch umdrehen.
			if (majorBox.nextSibling !== deleteWrap) {
				majorBox.insertAdjacentElement('afterend', deleteWrap);
				deleteWrap.style.display = 'block';
				deleteWrap.style.marginTop = '8px';
				deleteWrap.style.clear = 'both'; // gegen Floats/Flex der Major-Box absichern
			}
			return true;
		}

		onReady(function(){
			// Sofort versuchen
			if (placeTrashBelow()) return;

			// Kurz verzögert erneut (falls andere Skripte zuerst rendern)
			setTimeout(placeTrashBelow, 120);

			// Falls die Box später nochmal umgebaut wird, erneut anwenden
			var mo = new MutationObserver(function(){
				if (placeTrashBelow()) { /* einmalig reicht, Beobachtung kann laufen bleiben */ }
			});
			mo.observe(document.body, { childList:true, subtree:true });
		});
	})();
	</script>
	<?php
});

/** 4) Safety: Falls andere Plugins submitdiv entfernen, neu hinzufügen */
\add_action('do_meta_boxes', function($post_type) {
	if (!\in_array($post_type, cmx_get_target_post_types(), true)) return;
	global $wp_meta_boxes;
	$has = isset($wp_meta_boxes[$post_type]['side']['high']['submitdiv'])
	    || isset($wp_meta_boxes[$post_type]['side']['core']['submitdiv'])
	    || isset($wp_meta_boxes[$post_type]['side']['default']['submitdiv']);
	if ($has) return;

	\add_meta_box('submitdiv', __('Veröffentlichen'), '\post_submit_meta_box', $post_type, 'side', 'high');
}, 9, 1);
