<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Tab: Allgemein
 */
\add_action('admin_init', __NAMESPACE__ . '\\cmx_register_general_tab');
function cmx_register_general_tab(): void {

	\add_settings_section(
		'cmx_sec_general',
		__('Allgemein', 'default'),
		'__return_false',
		'cmx_tab_general'
	);

	// MwSt-Pflicht Checkbox
	\add_settings_field(
		'mwst_pflichtig',
		'MwSt-pflichtig?',
		function () {
			\CLOUDMEISTER\CMX\Buero\cmx_field_checkbox([
				'key'   => 'mwst_pflichtig',
				'label' => 'Ja, MwSt wird ausgewiesen',
			]);
		},
		'cmx_tab_general',
		'cmx_sec_general'
	);

	\add_settings_field(
		'help_sync_button',
		'Hilfe-Texte',
		function () {
			if (!\function_exists('\\CLOUDMEISTER\\CMX\\Buero\\cmx_help_is_cloud_meister') || !\CLOUDMEISTER\CMX\Buero\cmx_help_is_cloud_meister()) {
				echo '<em>Nur für Admin (CLOUD Meister)</em>';
				return;
			}
			$nonce  = \wp_create_nonce('cmx_help_sync');
			echo '<button type="button" class="button" id="cmx-help-sync-btn">Neue Hilfetexte laden</button>';
			echo '<span class="spinner" id="cmx-help-sync-spinner" style="float:none;margin-left:8px;"></span>';
			echo '<div id="cmx-help-sync-status" style="margin-top:8px;min-height:20px;"></div>';
			echo '<script>
			(function(){
				const btn = document.getElementById("cmx-help-sync-btn");
				const spinner = document.getElementById("cmx-help-sync-spinner");
				const status = document.getElementById("cmx-help-sync-status");
				if (!btn || !spinner || !status) return;
				function setStatus(text){ status.textContent = text || ""; }
				btn.addEventListener("click", function(){
					setStatus("Lese Hilfetexte...");
					spinner.classList.add("is-active");
					btn.disabled = true;
					const form = new URLSearchParams();
					form.append("action","cmx_help_sync");
					form.append("nonce","'.\esc_js($nonce).'");
					fetch(ajaxurl, {method:"POST", credentials:"same-origin", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:form.toString()})
						.then(r => r.json())
						.then(data => {
							const keys = data && data.data && Array.isArray(data.data.keys) ? data.data.keys : [];
							let i = 0;
							function step(){
								if (i < keys.length) {
									setStatus("Lese: " + keys[i]);
									i++;
									setTimeout(step, 40);
								} else {
									setStatus("Alle Texte geladen.");
									spinner.classList.remove("is-active");
									btn.disabled = false;
								}
							}
							step();
						})
						.catch(() => {
							setStatus("Hilfetexte konnten nicht geladen werden.");
							spinner.classList.remove("is-active");
							btn.disabled = false;
						});
				});
			})();
			</script>';
		},
		'cmx_tab_general',
		'cmx_sec_general'
	);

	// QR-Referenz wird pro Bank im Tab "Banken" gepflegt.

	// Wenn nötig: register_setting() ebenfalls hier setzen
	// \register_setting('cmx_einstellungen','cmx_einstellungen');
}



// QR-Referenz wird pro Bank verarbeitet (siehe Tab "Banken").
