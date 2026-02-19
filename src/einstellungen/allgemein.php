<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Tab: Allgemein
 */
if (!\function_exists(__NAMESPACE__ . '\\cmx_mwst_exempt_default_note_html')) {
	function cmx_mwst_exempt_default_note_html(): string {
		return 'Nicht mehrwertsteuerpflichtig gemäss <a href="https://www.fedlex.admin.ch/eli/cc/2009/615/de#art_10" style="color:black;" target="_blank" rel="noopener noreferrer">Art. 10 Abs. 2 lit. a MWSTG</a>';
	}
}

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
				$checked = \function_exists(__NAMESPACE__ . '\\cmx_belege_is_mwst_pflichtig')
					? cmx_belege_is_mwst_pflichtig((array) $opts)
					: !empty($opts['mwst_pflichtig']);
				$mwst_exempt_note = isset($opts['mwst_exempt_note_html']) ? (string) $opts['mwst_exempt_note_html'] : '';
				$mwst_exempt_note_with_link = \function_exists(__NAMESPACE__ . '\\cmx_mwst_exempt_default_note_html')
					? cmx_mwst_exempt_default_note_html()
					: 'Nicht mehrwertsteuerpflichtig gemäss <a href="https://www.fedlex.admin.ch/eli/cc/2009/615/de#art_10" style="color:black;" target="_blank" rel="noopener noreferrer">Art. 10 Abs. 2 lit. a MWSTG</a>';
				$default_is_brutto = !empty($opts['belege_default_is_brutto']);
				$default_mwst_term = isset($opts['belege_default_mwst_term']) ? (int) $opts['belege_default_mwst_term'] : 0;
				$mwst_terms = \get_terms([
					'taxonomy'   => 'belege_mwst',
					'hide_empty' => false,
				]);
				if (\is_wp_error($mwst_terms)) {
					$mwst_terms = [];
				}

				echo '<div id="cmx-mwst-num-wrap" style="margin-top:8px;'.($checked ? '' : 'display:none;').'">';
				echo '<label for="cmx-mwst-nummer" style="display:block;margin-bottom:4px;margin-top:20px;">MWST‑Nr</label>';
				echo '<input type="text" id="cmx-mwst-nummer" class="regular-text" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[mwst_nummer]" value="'.\esc_attr($val).'" placeholder="CHE-123.456.789 MWST">';
				echo '</div>';

				echo '<div id="cmx-mwst-defaults-wrap" style="margin-top:10px;'.($checked ? '' : 'display:none;').'">';
				echo '<label style="display:block;margin-bottom:4px;margin-top:20px;"><strong>Vorlage für neue Belege</strong></label>';
				echo '<label style="display:block;margin-bottom:6px;">';
				echo '<input type="hidden" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[belege_default_is_brutto]" value="0">';
				echo '<input type="checkbox" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[belege_default_is_brutto]" value="1" '.\checked($default_is_brutto, true, false).'> Brutto (inkl.) / Netto (ohne MWST)';
				echo '</label>';
				echo '<label for="cmx-default-mwst-term" style="display:block;margin-bottom:4px;margin-top:20px;">MWST‑Satz</label>';
				echo '<select id="cmx-default-mwst-term" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[belege_default_mwst_term]" style="width:100%;max-width:320px;">';
				echo '<option value="">— auswählen —</option>';
				foreach ($mwst_terms as $term) {
					$term_id = (int) ($term->term_id ?? 0);
					echo '<option value="'.\esc_attr((string) $term_id).'" '.\selected($default_mwst_term, $term_id, false).'>'.\esc_html((string) ($term->name ?? '')).'</option>';
				}
				echo '</select>';
				echo '</div>';

				echo '<div id="cmx-mwst-exempt-wrap" style="margin-top:10px;'.($checked ? 'display:none;' : '').'">';
				echo '<input type="text" id="cmx-mwst-exempt-note" class="regular-text" style="max-width:640px;width:100%;" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[mwst_exempt_note_html]" value="'.\esc_attr($mwst_exempt_note).'" placeholder="Nicht mehrwertsteuerpflichtig gemäss Art. 10 Abs. 2 lit. a MWSTG">';
				echo '<p class="description">Wird in PDFs angezeigt, wenn MwSt-pflichtig = Nein. HTML ist erlaubt. <a href="#" id="cmx-mwst-exempt-fill-link">inkl. Link</a></p>';
				echo '</div>';

				echo '<script>
				(function(){
					const cb = document.querySelector("input[type=checkbox][name=\''.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[mwst_pflichtig]\']");
					const numWrap = document.getElementById("cmx-mwst-num-wrap");
					const defaultsWrap = document.getElementById("cmx-mwst-defaults-wrap");
					const exemptWrap = document.getElementById("cmx-mwst-exempt-wrap");
					const exemptInput = document.getElementById("cmx-mwst-exempt-note");
					const fillLink = document.getElementById("cmx-mwst-exempt-fill-link");
					const withLinkValue = '.\wp_json_encode($mwst_exempt_note_with_link).';
					if (!cb) return;
					function sync(){
						const show = cb.checked;
						if (numWrap) numWrap.style.display = show ? "" : "none";
						if (defaultsWrap) defaultsWrap.style.display = show ? "" : "none";
						if (exemptWrap) exemptWrap.style.display = show ? "none" : "";
					}
					if (fillLink && exemptInput) {
						fillLink.addEventListener("click", function(e){
							e.preventDefault();
							exemptInput.value = withLinkValue;
						});
					}
					cb.addEventListener("change", sync);
					sync();
				})();
				</script>';
		},
		'cmx_tab_general',
		'cmx_sec_general'
	);

	\add_settings_field(
		'belege_use_leistungszeitraum',
		'Leistungszeitraum nutzen',
		function () {
			$opts = (array) \get_option(\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN, []);
			$enabled = \array_key_exists('belege_use_leistungszeitraum', $opts)
				? !empty($opts['belege_use_leistungszeitraum'])
				: true;
			echo '<label>';
			echo '<input type="hidden" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[belege_use_leistungszeitraum]" value="0">';
			echo '<input type="checkbox" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[belege_use_leistungszeitraum]" value="1" '.\checked($enabled, true, false).'> ';
			echo 'Leistungszeitraum in Belegen verwenden';
			echo '</label>';
			// echo '<p class="description">Steuert das Feld "Leistungszeitraum (nächster Monat)" in der Beleg-Metabox "Konditionen" und die Anzeige im PDF.</p>';
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

	$backup_download_url = (string) \get_option('misbuero_backup_download_url', '');
	$backup_download_url = \esc_url_raw($backup_download_url);
	$backup_file = \sanitize_file_name((string) \get_option('misbuero_backup_file', ''));
	$backup_created_raw = \trim((string) \get_option('misbuero_backup_created_at', ''));
	$backup_size_bytes = (int) \get_option('misbuero_backup_size_bytes', 0);
	$backup_path_option = \wp_normalize_path((string) \get_option('misbuero_backup_path', ''));
	$backup_exists = false;
	$backup_found_path = '';
	$backup_dir = \defined('WP_CONTENT_DIR')
		? \wp_normalize_path((string) \constant('WP_CONTENT_DIR') . '/uploads/misbuero-backups')
		: '';

	if ($backup_file === '' && $backup_path_option !== '') {
		$backup_file = \sanitize_file_name(\wp_basename($backup_path_option));
	}

	if ($backup_file === '' && $backup_download_url !== '') {
		if (\preg_match('#/misbuero-backups/([^/?#]+\.(?:zip|tar\.gz))\b#i', $backup_download_url, $m_file)) {
			$backup_file = \sanitize_file_name(\rawurldecode((string) $m_file[1]));
		} elseif (\preg_match('/[?&]file=([^&]+)/i', $backup_download_url, $m_file)) {
			$backup_file = \sanitize_file_name(\rawurldecode((string) $m_file[1]));
		}
	}

	$backup_paths = [];
	if ($backup_path_option !== '') {
		$backup_paths[] = $backup_path_option;
	}
	if ($backup_file !== '') {
		if ($backup_dir !== '') {
			$backup_paths[] = \wp_normalize_path(\rtrim($backup_dir, '/\\') . '/' . $backup_file);
		}
		if (\defined(__NAMESPACE__ . '\\CMX_UPLOADS_MISBUERO')) {
			$backup_paths[] = \wp_normalize_path(\trailingslashit((string) \constant(__NAMESPACE__ . '\\CMX_UPLOADS_MISBUERO')) . $backup_file);
			$backup_paths[] = \wp_normalize_path(\trailingslashit((string) \constant(__NAMESPACE__ . '\\CMX_UPLOADS_MISBUERO')) . 'backups/' . $backup_file);
		}
		if (\defined('WP_CONTENT_DIR')) {
			$backup_paths[] = \wp_normalize_path(\trailingslashit((string) \constant('WP_CONTENT_DIR')) . 'uploads/' . $backup_file);
			$backup_paths[] = \wp_normalize_path(\trailingslashit((string) \constant('WP_CONTENT_DIR')) . 'uploads/misbuero/' . $backup_file);
			$backup_paths[] = \wp_normalize_path(\trailingslashit((string) \constant('WP_CONTENT_DIR')) . 'uploads/misbuero/backups/' . $backup_file);
		}
	}
	$backup_paths = \array_values(\array_unique(\array_filter($backup_paths, static function ($path): bool {
		return \is_string($path) && $path !== '';
	})));

	foreach ($backup_paths as $backup_path) {
		if (\is_file($backup_path)) {
			$backup_exists = true;
			$backup_found_path = $backup_path;
			break;
		}
	}

	if (!$backup_exists && $backup_dir !== '' && \is_dir($backup_dir)) {
		$domain_hint = \strtolower((string) \wp_parse_url(\home_url('/'), PHP_URL_HOST));
		$domain_prefix = ($domain_hint !== '') ? 'backup-' . $domain_hint . '-' : '';
		$best_any = '';
		$best_any_mtime = 0;
		$best_domain = '';
		$best_domain_mtime = 0;
		$files = \glob(\rtrim($backup_dir, '/\\') . '/backup-*');
		if (!\is_array($files)) {
			$files = [];
		}
		foreach ($files as $file_path) {
			$file_path = \wp_normalize_path((string) $file_path);
			if (!\is_file($file_path)) {
				continue;
			}
			$basename = \basename($file_path);
			if (!\preg_match('/^backup-[a-z0-9.-]+\-\d{8}\-\d{6}\-[a-z0-9]+\.(?:zip|tar\.gz)$/i', $basename)) {
				continue;
			}
			$mtime = \filemtime($file_path);
			$mtime = ($mtime !== false) ? (int) $mtime : 0;
			if ($mtime >= $best_any_mtime) {
				$best_any_mtime = $mtime;
				$best_any = $basename;
			}
			if ($domain_prefix !== '' && \strpos(\strtolower($basename), $domain_prefix) === 0 && $mtime >= $best_domain_mtime) {
				$best_domain_mtime = $mtime;
				$best_domain = $basename;
			}
		}
		$fallback_file = ($best_domain !== '') ? $best_domain : $best_any;
		if ($fallback_file !== '') {
			$backup_file = \sanitize_file_name($fallback_file);
			$backup_found_path = \wp_normalize_path(\rtrim($backup_dir, '/\\') . '/' . $backup_file);
			$backup_exists = \is_file($backup_found_path);
		}
	}

	if ($backup_exists && $backup_size_bytes <= 0 && $backup_found_path !== '') {
		$detected_size = \filesize($backup_found_path);
		if (\is_int($detected_size) && $detected_size > 0) {
			$backup_size_bytes = $detected_size;
		}
	}

	if ($backup_exists && $backup_file !== '' && \defined('ABSPATH')) {
		$local_endpoint_path = \wp_normalize_path(\rtrim((string) \constant('ABSPATH'), '/\\') . '/misbuero-backup-download.php');
		if (\is_file($local_endpoint_path)) {
			$backup_download_url = \add_query_arg(['file' => $backup_file], \home_url('/misbuero-backup-download.php'));
		}
	}

	if ($backup_download_url !== '' && $backup_exists) {
		\add_settings_field(
			'misbuero_instance_backup_link',
			'Backup',
			function () use ($backup_download_url, $backup_created_raw, $backup_size_bytes) {
				$size_human = '';
				if ($backup_size_bytes > 0) {
					$units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
					$idx = 0;
					$size = (float) $backup_size_bytes;
					while ($size >= 1024 && $idx < \count($units) - 1) {
						$size /= 1024;
						$idx++;
					}
					$dec = ($size >= 100 || $idx === 0) ? 0 : 1;
					$size_human = \number_format($size, $dec, '.', '') . ' ' . $units[$idx];
				}

				$meta = [];
				if ($backup_created_raw !== '') {
					$created_ts = \strtotime($backup_created_raw);
					if ($created_ts !== false) {
						$meta[] = 'erstellt am ' . \date_i18n('d.m.Y H:i', $created_ts);
					} else {
						$meta[] = 'erstellt am ' . $backup_created_raw;
					}
				}
				if ($size_human !== '') {
					$meta[] = $size_human;
				}

				echo '<p class="description"><a href="' . \esc_url($backup_download_url) . '" target="_blank" rel="noopener">Backup herunterladen</a>';
				if (!empty($meta)) {
					echo ' (' . \esc_html(\implode(' | ', $meta)) . ')';
				}
				echo '</p>';
			},
			'cmx_tab_general',
			'cmx_sec_general'
		);
	}

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
