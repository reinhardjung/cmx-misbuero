<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * Download-/Copy-Bereich innerhalb der "Für Kunde..."-Metabox
 */
function cmxbu_render_beleg_download_metabox_with_copy(\WP_Post $post): void {

	// Stabilen Token für diesen Beleg holen (wird nur einmalig erzeugt)
	$token = cmxbu_get_stable_token($post->ID);

	[, $pdf_abs_path] = cmxbu_get_beleg_pdf_paths($post);

	// Wenn Datei nicht existiert → Buttons disabled
	// if (!is_file($pdf_abs_path)) {
	// 	echo '<a href="#" class="button button-secondary alignright" style="pointer-events:none; opacity:0.5; color:silver; border:silver solid 1px;">copy</a>';
	// 	echo '<a href="#" class="button button-secondary alignright" style="pointer-events:none; opacity:0.5; color:silver; border:silver solid 1px;">download</a>';
	// 	return;
	// }

	// Download-URL über stabilen Token
	$download_url = \home_url('/?beleg=' . $token);

	// Download-Button
	// echo '<a href="' . \esc_url($download_url) . '" target="_blank" class="button button-secondary alignright cmx-btn-transparent cmx-btn-download"style="color:#a42c24; border:#a42c24 solid 1px;">download</a>';
	echo '<a href="' . esc_url($download_url) . '" target="_blank" title="download als PDF" class="button button-secondary alignright cmx-btn-transparent cmx-btn-download"style="color:#a42c24; border:#a42c24 solid 1px;"> <span class="dashicons dashicons-download" style="margin-top:5px;"></span></a>';


	// Copy-Button
	// echo '<a href="#" class="button button-secondary alignright cmx-btn-transparent cmx-btn-copy" data-download-url="' . \esc_attr($download_url) . '" style="color:darkred; border:darkred solid 1px; margin-right:10px;">Link</a>';
	echo '<a href="#" title="kopiere download-Link in Zwischenablage" class="button button-secondary alignright cmx-btn-transparent cmx-btn-copy"data-download-url="' . esc_attr($download_url) . '"style="color:darkred; border:darkred solid 1px; margin-right:10px;"><span class="dashicons dashicons-admin-page" style="margin-top:4px;"></span></a>';


	// JS für Copy-Funktion (kopiert den Link in die Zwischenablage)
	echo '<script>
		document.addEventListener("click", function(event) {
			var target = event.target;
			if (!target.classList.contains("cmx-btn-copy")) {
				return;
			}
			event.preventDefault();

			var url = target.getAttribute("data-download-url");
			if (!url) { return; }

			function setCopiedLabel(btn) {
				var oldText = btn.textContent;
				btn.textContent = "kopiert";
				btn.disabled = true;
				setTimeout(function () {
					btn.textContent = oldText;
					btn.disabled = false;
				}, 2000);
			}

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(url).then(function () {
					setCopiedLabel(target);
				}).catch(function () {
					setCopiedLabel(target);
				});
			} else {
				var textarea = document.createElement("textarea");
				textarea.value = url;
				textarea.style.position = "fixed";
				textarea.style.opacity = "0";
				document.body.appendChild(textarea);
				textarea.select();
				try { document.execCommand("copy"); } catch (e) {}
				document.body.removeChild(textarea);
				setCopiedLabel(target);
			}
		});
	</script>';
}
