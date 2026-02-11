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
			$opts = \get_option(\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN, []);
			$val = $opts['mwst_nummer'] ?? '';
			$checked = !empty($opts['mwst_pflichtig']) || !empty($opts['mwstpflichtig']) || !empty($opts['mwst_pfl']);
			echo '<div id="cmx-mwst-num-wrap" style="margin-top:8px;'.($checked ? '' : 'display:none;').'">';
			echo '<label for="cmx-mwst-nummer" style="display:block;margin-bottom:4px;">MWST‑Nr</label>';
			echo '<input type="text" id="cmx-mwst-nummer" class="regular-text" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[mwst_nummer]" value="'.\esc_attr($val).'" placeholder="CHE-123.456.789 MWST">';
			echo '</div>';
			echo '<script>
			(function(){
				const cb = document.querySelector("input[type=checkbox][name=\''.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[mwst_pflichtig]\']");
				const wrap = document.getElementById("cmx-mwst-num-wrap");
				if (!cb || !wrap) return;
				function sync(){ wrap.style.display = cb.checked ? "" : "none"; }
				cb.addEventListener("change", sync);
				sync();
			})();
			</script>';
		},
		'cmx_tab_general',
		'cmx_sec_general'
	);

	\register_setting(
		'cmx_einstellungen',
		'mis_buero_openai_key',
		[
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		]
	);

	\add_settings_field(
		'mis_buero_openai_key',
		'OpenAI API Key',
		function () {
			$val = \get_option( 'mis_buero_openai_key', '' );
			echo '<input type="text" name="mis_buero_openai_key" class="regular-text" value="' . \esc_attr( $val ) . '">';
			echo '<p class="description">Wird für OCR und Produkttexte verwendet</p>';
		},
		'cmx_tab_general',
		'cmx_sec_general'
	);

	\register_setting(
		'cmx_einstellungen',
		'cmx_katalog_online',
		[
			'type'              => 'boolean',
			'sanitize_callback' => function ( $val ) {
				return $val ? '1' : '0';
			},
		]
	);

	\add_settings_field(
		'cmx_katalog_online',
		'Katalog Online',
		function () {
			$val = \get_option( 'cmx_katalog_online', '0' );
			echo '<label><input type="checkbox" name="cmx_katalog_online" value="1" ' . checked( $val, '1', false ) . '> Katalog öffentlich sichtbar</label>';
		},
		'cmx_tab_general',
		'cmx_sec_general'
	);

	\add_settings_field(
		'support_user_switch',
		'Support',
		function () {
			\CLOUDMEISTER\CMX\Buero\cmx_field_checkbox([
				'key'   => 'support_user_switch',
				'label' => 'Benutzerwechsel erlauben',
			]);
			echo '<p class="description">Bei aktivierter Funktion darf der Support temporär in Deine Benutzerrollen wechseln, um Probleme zu analysieren.</p>';
		},
		'cmx_tab_general',
		'cmx_sec_general'
	);

	\add_settings_field(
		'misbuero_instance_backup_link',
		'Instanz-Backup',
		function () {
			$download_url = (string) \get_option('misbuero_backup_download_url', '');
			$download_url = \esc_url_raw($download_url);
			$backup_file = \sanitize_file_name((string) \get_option('misbuero_backup_file', ''));
			$created_raw = \trim((string) \get_option('misbuero_backup_created_at', ''));
			$size_bytes = (int) \get_option('misbuero_backup_size_bytes', 0);

			if ($backup_file === '' && $download_url !== '' && \preg_match('/[?&]file=([^&]+)/i', $download_url, $m_file)) {
				$backup_file = \sanitize_file_name(\rawurldecode((string) $m_file[1]));
			}

			if ($backup_file !== '' && \preg_match('/^backup-[a-z0-9.-]+\-\d{8}\-\d{6}\-[a-z0-9]+\.(?:zip|tar\.gz)$/i', $backup_file)) {
				$download_url = \add_query_arg(['file' => $backup_file], \home_url('/misbuero-backup-download.php'));
			}

			if ($download_url === '') {
				echo '<em>Kein Backup vorhanden.</em>';
				return;
			}

			$size_human = '';
			if ($size_bytes > 0) {
				$units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
				$idx = 0;
				$size = (float) $size_bytes;
				while ($size >= 1024 && $idx < \count($units) - 1) {
					$size /= 1024;
					$idx++;
				}
				$dec = ($size >= 100 || $idx === 0) ? 0 : 1;
				$size_human = \number_format($size, $dec, '.', '') . ' ' . $units[$idx];
			}

			$meta = [];
			if ($created_raw !== '') {
				$created_ts = \strtotime($created_raw);
				if ($created_ts !== false) {
					$meta[] = 'erstellt am ' . \date_i18n('d.m.Y H:i', $created_ts);
				} else {
					$meta[] = 'erstellt am ' . $created_raw;
				}
			}
			if ($size_human !== '') {
				$meta[] = $size_human;
			}

			echo '<p class="description"><a href="' . \esc_url($download_url) . '" target="_blank" rel="noopener">Backup herunterladen</a>';
			if (!empty($meta)) {
				echo ' (' . \esc_html(\implode(' | ', $meta)) . ')';
			}
			echo '</p>';
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
			$host = (string) \wp_parse_url(\home_url(), PHP_URL_HOST);
			if ($host === 'vorlage.misbuero.ch') {
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
