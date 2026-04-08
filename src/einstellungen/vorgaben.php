<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Tab: Vorgaben
 */
\add_action('admin_init', __NAMESPACE__ . '\\cmx_register_vorgaben_tab');
function cmx_register_vorgaben_tab(): void {

	\add_settings_section(
		'cmx_sec_vorgaben_allgemein',
		__('Allgemein', 'default'),
		'__return_false',
		'cmx_tab_vorgaben__allgemein'
	);

	\add_settings_section(
		'cmx_sec_vorgaben_belege',
		__('Belege', 'default'),
		'__return_false',
		'cmx_tab_vorgaben__belege'
	);

	\add_settings_section(
		'cmx_sec_vorgaben_belege_abo',
		__('Wiederkehrender Versand', 'default'),
		'__return_false',
		'cmx_tab_vorgaben__belege'
	);

	\add_settings_section(
		'cmx_sec_vorgaben_artikel',
		__('Artikel', 'default'),
		'__return_false',
		'cmx_tab_vorgaben__artikel'
	);

	\add_settings_section(
		'cmx_sec_vorgaben_email',
		__('E-Mail', 'default'),
		'__return_false',
		'cmx_tab_vorgaben__email'
	);

	\add_settings_section(
		'cmx_sec_vorgaben_projekte',
		__('Projekte', 'default'),
		'__return_false',
		'cmx_tab_vorgaben__projekte'
	);

		\add_settings_field(
			'email_theme',
			'Theme',
			function () {
			$opts = (array) \get_option(\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN, []);
			$current = \function_exists(__NAMESPACE__ . '\\cmx_email_theme_sanitize')
				? (string) cmx_email_theme_sanitize((string) ($opts['email_theme'] ?? 'rot'))
				: 'rot';
			$themes = \function_exists(__NAMESPACE__ . '\\cmx_email_theme_presets')
				? (array) cmx_email_theme_presets()
				: [
					'rot' => ['label' => 'Rot'],
					'blau' => ['label' => 'Blau'],
					'grau' => ['label' => 'Grau'],
				];

			echo '<select name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[email_theme]" style="min-width:220px;">';
			foreach ($themes as $key => $theme) {
				$label = \is_array($theme) ? (string) ($theme['label'] ?? $key) : (string) $key;
				echo '<option value="'.\esc_attr((string) $key).'" '.\selected($current, (string) $key, false).'>'.\esc_html($label).'</option>';
			}
			echo '</select>';
			echo '<p class="description">Steuert den Farbverlauf im Mail-Header sowie die Button-Farbe in versendeten E-Mails.</p>';
			$hide_logo = !empty($opts['email_hide_logo']);
			echo '<div style="margin-top:10px;">';
			echo '<input type="hidden" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[email_hide_logo]" value="0">';
			echo '<label><input type="checkbox" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[email_hide_logo]" value="1" '.\checked($hide_logo, true, false).'> Ohne Logo im Header</label>';
			echo '</div>';
		},
			'cmx_tab_vorgaben__email',
			'cmx_sec_vorgaben_email'
		);

		\add_settings_field(
			'email_button_text',
			'Button Text',
			function () {
				$opts = (array) \get_option(\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN, []);
				$default = 'PDF-Beleg herunterladen';
				$value = \array_key_exists('email_button_text', $opts)
					? (string) ($opts['email_button_text'] ?? '')
					: $default;

				echo '<input type="text" class="regular-text" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[email_button_text]" value="'.\esc_attr($value).'" placeholder="'.\esc_attr($default).'" autocomplete="off">';
				echo '<p class="description">Leer = es wird nur das Button-Icon angezeigt.</p>';
			},
			'cmx_tab_vorgaben__email',
			'cmx_sec_vorgaben_email'
		);

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
		'cmx_tab_vorgaben__belege',
		'cmx_sec_vorgaben_belege'
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
		},
			'cmx_tab_vorgaben__belege',
			'cmx_sec_vorgaben_belege'
		);

		\add_settings_field(
			'belege_faelligkeit_tage',
			'Fälligkeitsfrist (Tage)',
		function () {
			$opts = (array) \get_option(\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN, []);
			$raw = isset($opts['belege_faelligkeit_tage']) ? (string) $opts['belege_faelligkeit_tage'] : '';
			$days = ($raw === '') ? 30 : (int) $raw;
			$month_end = !empty($opts['belege_faelligkeit_monatsende']);
			if ($days < 0) {
				$days = 0;
			}
			if ($days > 3650) {
				$days = 3650;
			}
			echo '<div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">';
			echo '<input type="number" min="0" max="3650" step="1" style="width:120px;" id="cmx-belege-faelligkeit-tage" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[belege_faelligkeit_tage]" value="'.\esc_attr((string) $days).'" '.($month_end ? 'readonly aria-readonly="true"' : '').'>';
			echo '<input type="hidden" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[belege_faelligkeit_monatsende]" value="0">';
			echo '<label for="cmx-belege-faelligkeit-monatsende" style="display:inline-flex;align-items:center;gap:6px;">';
			echo '<input type="checkbox" id="cmx-belege-faelligkeit-monatsende" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[belege_faelligkeit_monatsende]" value="1" '.\checked($month_end, true, false).'> Monatsende';
			echo '</label>';
			echo '</div>';
			echo '<p class="description">Standard für "Fällig am" in Belegen. Beim Setzen des Belegdatums wird das Fälligkeitsdatum auf Belegdatum + diese Tage gesetzt. Ist "Monatsende" aktiv, wird stattdessen immer der letzte Tag des Beleg-Monats übernommen.</p>';
			echo '<script>(function(){var days=document.getElementById("cmx-belege-faelligkeit-tage");var monthEnd=document.getElementById("cmx-belege-faelligkeit-monatsende");if(!days||!monthEnd){return;}function sync(){var active=!!monthEnd.checked;days.readOnly=active;days.setAttribute("aria-readonly",active?"true":"false");days.style.backgroundColor=active?"#f3f4f6":"";days.style.color=active?"#6b7280":"";}monthEnd.addEventListener("change",sync);sync();})();</script>';
		},
			'cmx_tab_vorgaben__belege',
			'cmx_sec_vorgaben_belege'
		);

		\add_settings_field(
			'belege_abo_default_enabled',
			'Aktiv',
			function (): void {
				if (!\class_exists(__NAMESPACE__ . '\\CMX_Beleg_Abo')) {
					return;
				}
				$options = (array) \get_option(\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN, []);
				$enabled_key = CMX_Beleg_Abo::DEFAULT_ENABLED_OPTION;
				$enabled = !empty($options[$enabled_key]);

				echo '<input type="hidden" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'['.\esc_attr($enabled_key).']" value="0">';
				echo '<label><input type="checkbox" id="cmx-belege-abo-default-enabled" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'['.\esc_attr($enabled_key).']" value="1" '.\checked($enabled, true, false).'> Ja</label>';
			},
			'cmx_tab_vorgaben__belege',
			'cmx_sec_vorgaben_belege_abo'
		);

		\add_settings_field(
			'belege_abo_default_frequency',
			'Rhythmus',
			function (): void {
				if (!\class_exists(__NAMESPACE__ . '\\CMX_Beleg_Abo')) {
					return;
				}
				$options = (array) \get_option(\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN, []);
				$frequency_key = CMX_Beleg_Abo::DEFAULT_FREQUENCY_OPTION;
				$current = CMX_Beleg_Abo::sanitize_default_frequency((string) ($options[$frequency_key] ?? 'monthly'));
				$visible_labels = CMX_Beleg_Abo::visible_frequency_labels();
				$all_labels = CMX_Beleg_Abo::all_frequency_labels();
				$select_id = 'cmx-belege-abo-default-frequency';
				echo '<div class="cmx-belege-abo-default-dependent">';
				echo '<select id="'.\esc_attr($select_id).'" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'['.\esc_attr($frequency_key).']" style="width:100%;max-width:320px;">';
				if (!isset($visible_labels[$current]) && isset($all_labels[$current])) {
					echo '<option value="'.\esc_attr($current).'" selected hidden></option>';
				}
				foreach ($visible_labels as $value => $label) {
					echo '<option value="'.\esc_attr((string) $value).'" '.\selected($current, (string) $value, false).'>'.\esc_html((string) $label).'</option>';
				}
				echo '</select>';
				echo '</div>';
			},
			'cmx_tab_vorgaben__belege',
			'cmx_sec_vorgaben_belege_abo'
		);

		\add_settings_field(
			'belege_abo_default_time',
			'Default Uhrzeit',
			function (): void {
				if (!\class_exists(__NAMESPACE__ . '\\CMX_Beleg_Abo')) {
					return;
				}
				$options = (array) \get_option(\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN, []);
				$time_key = CMX_Beleg_Abo::DEFAULT_TIME_OPTION;
				$time = CMX_Beleg_Abo::sanitize_default_time((string) ($options[$time_key] ?? '08:00'));
				$row_id = 'cmx-belege-abo-default-time-row';
				echo '<div id="'.\esc_attr($row_id).'" class="cmx-belege-abo-default-dependent">';
				echo '<input type="time" id="cmx-belege-abo-default-time" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'['.\esc_attr($time_key).']" value="'.\esc_attr($time).'" style="width:100%;max-width:320px;">';
				echo '</div>';
				echo '<script>(function(){var enabled=document.getElementById("cmx-belege-abo-default-enabled");var select=document.getElementById("cmx-belege-abo-default-frequency");var row=document.getElementById("'.\esc_js($row_id).'");var input=document.getElementById("cmx-belege-abo-default-time");var dependentRows=document.querySelectorAll(".cmx-belege-abo-default-dependent");if(!enabled||!select||!row||!input||!dependentRows.length){return;}function sync(){var active=!!enabled.checked;var mode=select.value||"monthly";var showTime=active&&mode!=="minutely"&&mode!=="hourly"&&mode!=="never";dependentRows.forEach(function(node){node.style.display=active?\"\":\"none\";Array.prototype.forEach.call(node.querySelectorAll(\"input, select, textarea\"),function(field){field.disabled=!active;});});row.style.display=showTime?\"\":\"none\";input.disabled=!showTime;}enabled.addEventListener(\"change\",sync);select.addEventListener(\"change\",sync);sync();})();</script>';
			},
			'cmx_tab_vorgaben__belege',
			'cmx_sec_vorgaben_belege_abo'
		);

	\register_setting(
		'cmx_einstellungen',
		'cmx_katalog_online',
		[
			'type'              => 'boolean',
			'sanitize_callback' => function ($val) {
				return $val ? '1' : '0';
			},
		]
	);

	\add_settings_field(
		'cmx_katalog_online',
		'Katalog Online',
		function () {
			$val = \get_option('cmx_katalog_online', '0');
			echo '<label><input type="checkbox" name="cmx_katalog_online" value="1" ' . checked($val, '1', false) . '> Katalog öffentlich sichtbar</label>';
		},
		'cmx_tab_vorgaben__allgemein',
		'cmx_sec_vorgaben_allgemein'
	);

	\add_settings_field(
		'powered_by',
		'Powered by Mis Büro',
		function () {
			$opts = (array) \get_option(\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN, []);
			$enabled = \array_key_exists('powered_by', $opts)
				? !empty($opts['powered_by'])
				: true;
			echo '<label>';
			echo '<input type="hidden" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[powered_by]" value="0">';
			echo '<input type="checkbox" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[powered_by]" value="1" '.\checked($enabled, true, false).'> ';
			echo 'Werbung zeigen';
			echo '</label>';
		},
		'cmx_tab_vorgaben__allgemein',
		'cmx_sec_vorgaben_allgemein'
	);

	\add_settings_field(
		'task_Intervall',
		'Erfassungsintervall',
		function () {
			$opts = (array) \get_option(\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN, []);
			$allowed = ['5', '10', '15', '20', '30', '45', '60'];
			$current = isset($opts['task_Intervall']) ? (string) $opts['task_Intervall'] : '5';
			if (!\in_array($current, $allowed, true)) {
				$current = '5';
			}

			echo '<select name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[task_Intervall]" style="min-width:90px;">';
			foreach ($allowed as $value) {
				echo '<option value="'.\esc_attr($value).'" '.\selected($current, $value, false).'>'.\esc_html($value).'</option>';
			}
			echo '</select>';
			echo '<span style="margin-left:8px;">in Minuten</span>';
		},
		'cmx_tab_vorgaben__projekte',
		'cmx_sec_vorgaben_projekte'
	);

	\add_settings_field(
		'artikel_deckungsbeitrag',
		'Deckungsbeitrag',
		function () {
			$opts = (array) \get_option(\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN, []);
			$value = isset($opts['artikel_deckungsbeitrag']) ? (string) $opts['artikel_deckungsbeitrag'] : '';
			echo '<input type="number" step="0.01" min="0" style="width:140px;" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[artikel_deckungsbeitrag]" value="'.\esc_attr($value).'">';
			echo '<p class="description">mein mind. gewünschter Gewinn in Prozent</p>';
		},
		'cmx_tab_vorgaben__artikel',
			'cmx_sec_vorgaben_artikel'
		);
	}

\add_action('admin_init', __NAMESPACE__ . '\\cmx_register_vorgaben_belege_trustee_field', 99);
function cmx_register_vorgaben_belege_trustee_field(): void {
	\add_settings_field(
		'belege_use_treuhaender_notice',
		'Treuhänder',
		function () {
			$opts = (array) \get_option(\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN, []);
			$enabled = \array_key_exists('belege_use_treuhaender_notice', $opts)
				? !empty($opts['belege_use_treuhaender_notice'])
				: true;
			echo '<label>';
			echo '<input type="hidden" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[belege_use_treuhaender_notice]" value="0">';
			echo '<input type="checkbox" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[belege_use_treuhaender_notice]" value="1" '.\checked($enabled, true, false).'> ';
			echo 'Kann Milchbüchli an den Treuhänder versenden';
			echo '</label>';
		},
		'cmx_tab_vorgaben__belege',
		'cmx_sec_vorgaben_belege'
	);
}

\add_filter('pre_update_option_' . CMX_SETTINGS_MAIN, function ($new, $old) {
	if (!\is_array($new)) {
		return $old;
	}
	if (!\class_exists(__NAMESPACE__ . '\\CMX_Beleg_Abo')) {
		return $new;
	}

	$enabled_key = CMX_Beleg_Abo::DEFAULT_ENABLED_OPTION;
	$frequency_key = CMX_Beleg_Abo::DEFAULT_FREQUENCY_OPTION;
	$time_key = CMX_Beleg_Abo::DEFAULT_TIME_OPTION;

	$new[$enabled_key] = !empty($new[$enabled_key]) ? '1' : '0';
	$new[$frequency_key] = CMX_Beleg_Abo::sanitize_default_frequency((string) ($new[$frequency_key] ?? 'monthly'));
	$new[$time_key] = CMX_Beleg_Abo::sanitize_default_time((string) ($new[$time_key] ?? '08:00'));

	return $new;
}, 20, 2);

\add_action('admin_init', __NAMESPACE__ . '\\cmx_register_vorgaben_belege_geplante_saetze_field', 100);
function cmx_register_vorgaben_belege_geplante_saetze_field(): void {
	\add_settings_field(
		'belege_geplante_saetze',
		'<span>geplante Steuersätze</span><span style="display:block;margin-top:4px;font-size:12px;font-weight:400;color:#646970;">Vermögenssteuer?</span>',
		function () {
			$opts = (array) \get_option(\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN, []);
			$steuer = isset($opts['belege_geplante_steuer']) ? (string) $opts['belege_geplante_steuer'] : '';
			$ahv = isset($opts['belege_geplante_ahv']) ? (string) $opts['belege_geplante_ahv'] : '';

			echo '<div style="display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap;">';
			echo '<label style="display:flex;flex-direction:column;gap:4px;">';
			echo '<span>Steuern</span>';
			echo '<span style="display:flex;align-items:center;gap:8px;">';
			echo '<input type="number" step="0.01" min="0" inputmode="decimal" style="width:90px;" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[belege_geplante_steuer]" value="'.\esc_attr($steuer).'" placeholder="20" autocomplete="off">';
			echo '<span style="padding-right:50px;">15-25%</span>';
			echo '</span>';
			echo '</label>';
			echo '<label style="display:flex;flex-direction:column;gap:4px;">';
			echo '<span>AHV</span>';
			echo '<span style="display:flex;align-items:center;gap:8px;">';
			echo '<input type="number" step="0.01" min="0" inputmode="decimal" style="width:90px;" name="'.\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN.'[belege_geplante_ahv]" value="'.\esc_attr($ahv).'" placeholder="10" autocomplete="off">';
			echo '<span>10-12%</span>';
			echo '</span>';
			echo '</label>';
			echo '</div>';
		},
		'cmx_tab_vorgaben__belege',
		'cmx_sec_vorgaben_belege'
	);
}
