<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Download-/Copy-Bereich innerhalb der "Für Kunde..."-Metabox
 */
function cmxbu_render_beleg_download_metabox_with_copy(\WP_Post $post) {
	[$title, $type] = cmx_get_beleg_type($post);

	// Dateinamen-Schema: 20YY/YYMMDD_xxx_rechnung.pdf etc.
	$pdf_rel_path = '20' . substr($title, 0, 2) . '/' . $title . '_' . $type . '.pdf';

	$base_dir     = rtrim(CMX_UPLOADS_MISBUERO, '/\\') . '/';
	$pdf_abs_path = $base_dir . ltrim($pdf_rel_path, '/\\');

	// Wenn Datei nicht existiert → Buttons disabled
	if (!is_file($pdf_abs_path)) {
		echo '<a href="#" class="button button-secondary alignright" style="pointer-events:none; opacity:0.5; color:silver; border:silver solid 1px;">copy</a>';
		echo '<a href="#" class="button button-secondary alignright" style="pointer-events:none; opacity:0.5; color:silver; border:silver solid 1px;">download</a>';
		return;
	}

	// Dauerhafter Token (läuft NICHT mehr ab)
	$token = wp_generate_password(20, false, false);

	// In Option statt Transient speichern (kein Timeout)
	update_option(
		'beleg_' . $token,
		[
			'post_id' => $post->ID,
			'file'    => $pdf_rel_path,
		],
		false // nicht autoloaden
	);

	// Download-URL über normalen WP-Request
	$download_url = home_url('/?beleg=' . $token);
	echo '<a href="' . esc_url($download_url) . '" target="_blank" class="button button-secondary alignright cmx-btn-transparent cmx-btn-download" style="color:#a42c24; border:#a42c24 solid 1px;">download</a>';
	echo '<a href="#" class="button button-secondary alignright cmx-btn-transparent cmx-btn-copy" data-download-url="' . esc_attr($download_url) . '" style="color:darkred; border:darkred solid 1px; margin-right:10px;">Link</a>';


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
					btn.textContent = "Link";
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
