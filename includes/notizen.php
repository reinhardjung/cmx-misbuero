<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_NOTIZEN_MB_ID = 'cmx_internenotizen_central';

if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_supported_post_types')) {
	function cmx_notizen_supported_post_types(): array {
		return ['kontakte', 'artikel', 'belege', 'dokumente', 'projekte'];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_meta_key_for_post_type')) {
	function cmx_notizen_meta_key_for_post_type(string $post_type): string {
		$map = [
			'kontakte'  => '_cmx_intern_notizen',
			'artikel'   => '_cmx_artikel_intern_notizen',
			'belege'    => '_cmx_beleg_intern_notizen',
			'dokumente' => '_cmx_dokumente_intern_notizen',
			'projekte'  => '_cmx_projekt_intern_notizen',
		];
		return $map[$post_type] ?? '_cmx_intern_notizen';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_legacy_meta_keys')) {
	function cmx_notizen_legacy_meta_keys(string $post_type): array {
		$map = [
			'kontakte' => ['_cmx_interne_notizen'],
		];
		return $map[$post_type] ?? [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_now_date')) {
	function cmx_notizen_now_date(): string {
		return (string) \current_time('Y-m-d');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_now_time')) {
	function cmx_notizen_now_time(): string {
		return (string) \current_time('H:i');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_betreff_options')) {
	function cmx_notizen_betreff_options(): array {
		return ['Meeting', 'E-Mail', 'Telefonat', 'Vor Ort', 'Remote'];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_normalize_betreff')) {
	function cmx_notizen_normalize_betreff($betreff): string {
		$betreff = \sanitize_text_field((string) $betreff);
		$allowed = cmx_notizen_betreff_options();
		return \in_array($betreff, $allowed, true) ? $betreff : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_is_valid_date')) {
	function cmx_notizen_is_valid_date(string $date): bool {
		return (bool) \preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_is_valid_time')) {
	function cmx_notizen_is_valid_time(string $time): bool {
		return (bool) \preg_match('/^\d{2}:\d{2}$/', $time);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_allowed_html')) {
	function cmx_notizen_allowed_html(): array {
		$allowed = \wp_kses_allowed_html('post');
		$allowed = \is_array($allowed) ? $allowed : [];
		$allowed['a'] = \array_merge(
			['href' => [], 'title' => [], 'target' => [], 'rel' => []],
			\is_array($allowed['a'] ?? null) ? $allowed['a'] : []
		);

		return $allowed;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_sanitize_text')) {
	function cmx_notizen_sanitize_text($text): string {
		if (!\is_string($text)) {
			return '';
		}

		$text = \str_replace(["\r\n", "\r"], "\n", $text);

		return \trim((string) \wp_kses($text, cmx_notizen_allowed_html()));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_editor_settings')) {
	function cmx_notizen_editor_settings(): array {
		return [
			'mediaButtons' => false,
			'quicktags'    => true,
			'tinymce'      => [
				'wpautop'   => true,
				'branding'  => false,
				'menubar'   => false,
				'statusbar' => false,
				'resize'    => true,
				'toolbar1'  => 'formatselect,bold,italic,bullist,numlist,blockquote,link,unlink,undo,redo',
				'toolbar2'  => '',
			],
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_extract_links')) {
	/**
	 * @return array<int, array{href:string,label:string}>
	 */
	function cmx_notizen_extract_links(string $text): array {
		$text = \trim($text);
		if ($text === '' || \stripos($text, '<a') === false) {
			return [];
		}

		$matches = [];
		\preg_match_all('/<a\b[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is', $text, $matches, \PREG_SET_ORDER);
		if ($matches === []) {
			return [];
		}

		$links = [];
		foreach ($matches as $match) {
			$href = \esc_url_raw((string) ($match[2] ?? ''), ['http', 'https', 'mailto']);
			$label = \trim(\wp_strip_all_tags((string) ($match[3] ?? '')));
			if ($href === '') {
				continue;
			}
			if ($label === '') {
				$label = $href;
			}
			$key = \md5($href . '|' . $label);
			$links[$key] = [
				'href'  => $href,
				'label' => $label,
			];
		}

		return \array_values($links);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_normalize_row')) {
	/**
	 * @param mixed $row
	 * @return array{betreff:string,datum:string,zeit:string,text:string}|null
	 */
	function cmx_notizen_normalize_row($row): ?array {
		$betreff = '';
		$datum = '';
		$zeit  = '';
		$text  = '';

		if (\is_array($row)) {
			$betreff = cmx_notizen_normalize_betreff($row['betreff'] ?? ($row['subject'] ?? ($row['thema'] ?? '')));
			$datum = \sanitize_text_field((string) ($row['datum'] ?? ($row['date'] ?? '')));
			$zeit  = \sanitize_text_field((string) ($row['zeit'] ?? ($row['time'] ?? ($row['uhrzeit'] ?? ''))));
			$text  = cmx_notizen_sanitize_text((string) ($row['text'] ?? ($row['notiz'] ?? ($row['note'] ?? ($row['info'] ?? '')))));
		} elseif (\is_string($row)) {
			$text = cmx_notizen_sanitize_text($row);
		}

		$betreff = \trim($betreff);
		$datum = \trim($datum);
		$zeit  = \trim($zeit);
		$text  = \trim($text);

		if ($datum !== '' && !cmx_notizen_is_valid_date($datum)) {
			$datum = '';
		}
		if ($zeit !== '' && !cmx_notizen_is_valid_time($zeit)) {
			$zeit = '';
		}

		if ($betreff === '' && $datum === '' && $zeit === '' && $text === '') {
			return null;
		}

		return ['betreff' => $betreff, 'datum' => $datum, 'zeit' => $zeit, 'text' => $text];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_load_rows')) {
	if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_decode_legacy_raw')) {
		/**
		 * Legacy-Notizen robust dekodieren (JSON/serialisiert, auch doppelt serialisiert).
		 * @param mixed $raw
		 * @return mixed
		 */
		function cmx_notizen_decode_legacy_raw($raw) {
			$value = $raw;
			for ($i = 0; $i < 3; $i++) {
				if (!\is_string($value)) {
					break;
				}
				$trim = \trim($value);
				if ($trim === '') {
					break;
				}

				$first = $trim[0] ?? '';
				if ($first === '[' || $first === '{') {
					$decoded = \json_decode($trim, true);
					if (\json_last_error() === JSON_ERROR_NONE) {
						$value = $decoded;
						continue;
					}
				}

				if (\function_exists('is_serialized') && \is_serialized($trim)) {
					$decoded = @\maybe_unserialize($trim);
					if ($decoded !== $value) {
						$value = $decoded;
						continue;
					}
				}
				break;
			}
			return $value;
		}
	}

		/**
		 * @return array<int, array{betreff:string,datum:string,zeit:string,text:string}>
		 */
		function cmx_notizen_load_rows(int $post_id, string $post_type): array {
		$meta_key = cmx_notizen_meta_key_for_post_type($post_type);
		$raw = \get_post_meta($post_id, $meta_key, true);

		if (($raw === '' || $raw === null) && $post_type !== '') {
			foreach (cmx_notizen_legacy_meta_keys($post_type) as $legacy_key) {
				$legacy_raw = \get_post_meta($post_id, $legacy_key, true);
				if ($legacy_raw !== '' && $legacy_raw !== null) {
					$raw = $legacy_raw;
					break;
				}
			}
		}

		$raw = cmx_notizen_decode_legacy_raw($raw);

		$rows = [];
		if (\is_array($raw)) {
				$is_single_row = isset($raw['betreff']) || isset($raw['subject']) || isset($raw['thema']) || isset($raw['datum']) || isset($raw['date']) || isset($raw['zeit']) || isset($raw['time']) || isset($raw['text']) || isset($raw['notiz']) || isset($raw['note']) || isset($raw['info']);
			if ($is_single_row) {
				$raw = [$raw];
			}
			foreach ($raw as $row) {
				$norm = cmx_notizen_normalize_row($row);
				if ($norm !== null) {
					$rows[] = $norm;
				}
			}
		} else {
			$norm = cmx_notizen_normalize_row($raw);
			if ($norm !== null) {
				$rows[] = $norm;
			}
		}

		return $rows;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_render_row')) {
	/**
	 * @param int|string $index
	 */
	function cmx_notizen_render_row($index, array $row, bool $is_template = false): void {
		$name_index = $is_template ? '__INDEX__' : (string) $index;
		$betreff = (string) ($row['betreff'] ?? '');
		$datum = \esc_attr((string) ($row['datum'] ?? ''));
		$zeit  = \esc_attr((string) ($row['zeit'] ?? ''));
		$text_value = (string) ($row['text'] ?? '');
		$text  = \esc_textarea($text_value);
		$textarea_id = 'cmx_intern_notiz_text_' . \preg_replace('/[^A-Za-z0-9_-]+/', '_', $name_index);
		$links = cmx_notizen_extract_links($text_value);

		echo '<div class="cmx-intern-notiz-row">';
		echo '<div class="cmx-intern-notiz-main">';
			echo '<textarea rows="4" class="cmx-intern-notiz-text" data-cmx-notiz-editor="1" spellcheck="false" aria-label="' . \esc_attr__('Notiz', 'cmx') . '" id="' . \esc_attr($textarea_id) . '" name="cmx_intern_notizen_rows[' . $name_index . '][text]">' . $text . '</textarea>';
		if ($links !== []) {
			echo '<div class="cmx-intern-notiz-links">';
			echo '<strong>' . \esc_html__('Links', 'cmx') . ':</strong> ';
			foreach ($links as $link_index => $link) {
				if ($link_index > 0) {
					echo ' <span aria-hidden="true">|</span> ';
				}
				echo '<a href="' . \esc_url((string) $link['href']) . '" target="_blank" rel="noopener noreferrer">' . \esc_html((string) $link['label']) . '</a>';
			}
			echo '</div>';
		}
		echo '</div>';

		echo '<div class="cmx-intern-notiz-side">';
		echo '<label class="cmx-intern-notiz-label">';
		echo '<span>Betreff</span>';
		echo '<select name="cmx_intern_notizen_rows[' . $name_index . '][betreff]">';
		echo '<option value=""></option>';
		foreach (cmx_notizen_betreff_options() as $option) {
			echo '<option value="' . \esc_attr($option) . '"' . \selected($betreff, $option, false) . '>' . \esc_html($option) . '</option>';
		}
		echo '</select>';
		echo '</label>';

		echo '<label class="cmx-intern-notiz-label">';
		echo '<span class="cmx-intern-notiz-label-inline">Datum <a href="#" class="cmx-notiz-heute">heute</a></span>';
		echo '<input type="date" name="cmx_intern_notizen_rows[' . $name_index . '][datum]" value="' . $datum . '" />';
		echo '</label>';

		echo '<label class="cmx-intern-notiz-label">';
		echo '<span class="cmx-intern-notiz-label-inline">Uhrzeit <a href="#" class="cmx-notiz-jetzt">jetzt</a></span>';
		echo '<input type="time" name="cmx_intern_notizen_rows[' . $name_index . '][zeit]" value="' . $zeit . '" />';
		echo '</label>';
		echo '<p class="cmx-intern-notiz-actions-wrap">';
		echo '<button type="button" class="button cmx-notiz-add">' . \esc_html__('Notiz hinzufügen', 'cmx') . '</button>';
		echo '<button type="button" class="button cmx-notiz-remove" aria-label="Zeile entfernen"><span class="dashicons dashicons-trash" style=""></span></button>';
		echo '</p>';
		echo '</div>';
		echo '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_central_notizen_box')) {
	function cmx_render_central_notizen_box(\WP_Post $post): void {
		$post_type = (string) $post->post_type;
		$rows = cmx_notizen_load_rows((int) $post->ID, $post_type);
		if (empty($rows)) {
			$rows[] = [
				'betreff' => '',
				'datum' => cmx_notizen_now_date(),
				'zeit'  => cmx_notizen_now_time(),
				'text'  => '',
			];
		}

		\wp_nonce_field('cmx_intern_notizen_save', 'cmx_intern_notizen_nonce');
		echo '<p style="margin:0 0 8px;">' . \esc_html__('Nur intern sichtbar. Zur Historie etc.', 'cmx') . '</p>';
		echo '<style>';
		echo '#cmx-intern-notizen-list{display:flex;flex-direction:column;gap:8px;}';
		echo '#cmx-intern-notizen-list .cmx-intern-notiz-row{display:grid;grid-template-columns:minmax(0,1fr) 220px;gap:10px;padding:8px;border:1px solid #ddd;border-radius:6px;background:#fafafa;}';
		echo '#cmx-intern-notizen-list .cmx-intern-notiz-main{min-width:0;}';
		echo '#cmx-intern-notizen-list .cmx-intern-notiz-label{display:flex;flex-direction:column;gap:4px;}';
		echo '#cmx-intern-notizen-list .cmx-intern-notiz-label-inline{display:flex;align-items:center;gap:6px;}';
		echo '#cmx-intern-notizen-list .cmx-intern-notiz-side{display:flex;flex-direction:column;gap:8px;}';
			echo '#cmx-intern-notizen-list textarea{width:100%;min-height:104px;}';
		echo '#cmx-intern-notizen-list .cmx-intern-notiz-links{margin-top:8px;font-size:12px;line-height:1.5;color:#50575e;}';
		echo '#cmx-intern-notizen-list .cmx-intern-notiz-links a{font-weight:600;text-decoration:underline;}';
		echo '#cmx-intern-notizen-list .wp-editor-wrap{width:100%;}';
			echo '#cmx-intern-notizen-list .wp-editor-container textarea.wp-editor-area{min-height:104px;}';
		echo '#cmx-intern-notizen-list .cmx-notiz-heute,#cmx-intern-notizen-list .cmx-notiz-jetzt{color:#d63638;text-decoration:none;}';
			echo '#cmx-intern-notizen-list .cmx-intern-notiz-actions-wrap{display:flex;justify-content:space-between;align-items:center;gap:8px;margin:0;width:100%;}';
		echo '#cmx-intern-notizen-list .cmx-notiz-add{display:none;min-width:140px;}';
			echo '#cmx-intern-notizen-list .cmx-intern-notiz-row:last-child .cmx-notiz-add{display:inline-flex;justify-content:center;}';
			echo '#cmx-intern-notizen-list .cmx-notiz-remove{color:#a00;font-size:18px;line-height:1;min-width:36px;margin-left:auto;}';
			echo '@media (max-width:782px){#cmx-intern-notizen-list .cmx-intern-notiz-row{grid-template-columns:1fr;}#cmx-intern-notizen-list .cmx-intern-notiz-actions-wrap{justify-content:space-between;}#cmx-intern-notizen-list .cmx-notiz-add{min-width:0;}}';
		echo '</style>';
			echo '<div id="cmx-intern-notizen-list">';
			foreach ($rows as $idx => $row) {
				cmx_notizen_render_row((int) $idx, $row, false);
			}
			echo '</div>';
			?>
			<script type="text/template" id="cmx-intern-notizen-template">
				<?php cmx_notizen_render_row('__INDEX__', ['betreff' => '', 'datum' => '', 'zeit' => '', 'text' => ''], true); ?>
			</script>
		<script>
		(function(){
			const list = document.getElementById('cmx-intern-notizen-list');
			const tpl = document.getElementById('cmx-intern-notizen-template');
			const editorSettings = <?php echo \wp_json_encode(cmx_notizen_editor_settings()); ?>;
			const editorIds = new Set();
			let editorBootAttempts = 0;
			if (!list || !tpl || list.dataset.cmxBound === '1') return;
			list.dataset.cmxBound = '1';

			function today() {
				const d = new Date();
				return d.toISOString().slice(0, 10);
			}
			function nowTime() {
				const d = new Date();
				return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
			}
			function getTextInput(row) {
				return row ? row.querySelector('textarea[data-cmx-notiz-editor="1"]') : null;
			}
			function hasEditorApi() {
				return !!(window.wp && wp.editor && typeof wp.editor.initialize === 'function' && typeof wp.editor.remove === 'function');
			}
			function initAllEditors() {
				list.querySelectorAll('.cmx-intern-notiz-row').forEach(initEditor);
			}
			function scheduleEditorBoot(delay) {
				window.setTimeout(function(){
					if (hasEditorApi()) {
						initAllEditors();
						return;
					}
					if (editorBootAttempts >= 40) {
						return;
					}
					editorBootAttempts += 1;
					scheduleEditorBoot(150);
				}, delay);
			}
			function initEditor(row) {
				const textarea = getTextInput(row);
				if (!textarea || editorIds.has(textarea.id) || !hasEditorApi()) return;
				try {
					wp.editor.initialize(textarea.id, editorSettings);
					editorIds.add(textarea.id);
				} catch (err) {}
			}
			function destroyEditor(row) {
				const textarea = getTextInput(row);
				if (!textarea) return;
				if (editorIds.has(textarea.id) && hasEditorApi()) {
					try {
						wp.editor.remove(textarea.id);
					} catch (err) {}
				}
				editorIds.delete(textarea.id);
			}
			function setTextValue(row, value) {
				const textarea = getTextInput(row);
				if (!textarea) return;
				textarea.value = value || '';
				if (window.tinymce && textarea.id) {
					const editor = window.tinymce.get(textarea.id);
					if (editor) {
						editor.setContent(value || '');
						editor.save();
					}
				}
			}
			function triggerSave() {
				if (window.tinymce && typeof window.tinymce.triggerSave === 'function') {
					window.tinymce.triggerSave();
				}
			}
			function addRow(seed){
				const idx = list.querySelectorAll('.cmx-intern-notiz-row').length;
				const html = tpl.innerHTML.replace(/__INDEX__/g, String(idx));
				const wrap = document.createElement('div');
				wrap.innerHTML = html.trim();
				const row = wrap.firstElementChild;
				if (!row) return;
				const subjectInput = row.querySelector('select');
				const dateInput = row.querySelector('input[type="date"]');
				const timeInput = row.querySelector('input[type="time"]');
				const textInput = row.querySelector('textarea');
				if (subjectInput) subjectInput.value = seed && seed.betreff ? seed.betreff : '';
				if (dateInput) dateInput.value = seed && seed.datum ? seed.datum : today();
				if (timeInput) timeInput.value = seed && seed.zeit ? seed.zeit : nowTime();
				if (textInput) textInput.value = seed && seed.text ? seed.text : '';
				list.appendChild(row);
				initEditor(row);
				if (!hasEditorApi()) {
					scheduleEditorBoot(150);
				}
			}
			initAllEditors();
			if (!hasEditorApi()) {
				if (document.readyState === 'complete') {
					scheduleEditorBoot(0);
				} else {
					window.addEventListener('load', function(){ scheduleEditorBoot(0); }, {once:true});
				}
			}
			const form = list.closest('form');
			if (form && form.dataset.cmxNotizenSubmitBound !== '1') {
				form.dataset.cmxNotizenSubmitBound = '1';
				form.addEventListener('submit', triggerSave);
			}

			list.addEventListener('click', function(e){
				const addBtn = e.target && e.target.closest ? e.target.closest('.cmx-notiz-add') : null;
				if (addBtn) {
					e.preventDefault();
					addRow({});
					return;
				}

				const removeBtn = e.target && e.target.closest ? e.target.closest('.cmx-notiz-remove') : null;
				if (removeBtn) {
					e.preventDefault();
						const row = removeBtn.closest('.cmx-intern-notiz-row');
						if (!row) return;
						const allRows = list.querySelectorAll('.cmx-intern-notiz-row');
						if (allRows.length <= 1) {
							const subjectInput = row.querySelector('select');
							const dateInput = row.querySelector('input[type="date"]');
							const timeInput = row.querySelector('input[type="time"]');
							if (subjectInput) subjectInput.value = '';
							if (dateInput) dateInput.value = today();
							if (timeInput) timeInput.value = nowTime();
							setTextValue(row, '');
						return;
					}
					destroyEditor(row);
					row.remove();
					return;
				}

				const heuteBtn = e.target && e.target.closest ? e.target.closest('.cmx-notiz-heute') : null;
				if (heuteBtn) {
					e.preventDefault();
					const row = heuteBtn.closest('.cmx-intern-notiz-row');
					if (!row) return;
					const dateInput = row.querySelector('input[type="date"]');
					if (dateInput) dateInput.value = today();
					return;
				}

				const jetztBtn = e.target && e.target.closest ? e.target.closest('.cmx-notiz-jetzt') : null;
				if (jetztBtn) {
					e.preventDefault();
					const row = jetztBtn.closest('.cmx-intern-notiz-row');
					if (!row) return;
					const timeInput = row.querySelector('input[type="time"]');
					if (timeInput) timeInput.value = nowTime();
				}
			});
		})();
		</script>
		<?php
	}
}

\add_action('add_meta_boxes', function ($post_type) {
	$post_type = (string) $post_type;
	if (!\in_array($post_type, cmx_notizen_supported_post_types(), true)) {
		return;
	}

	\add_meta_box(
		CMX_NOTIZEN_MB_ID,
		__('Interne Notizen', 'cmx'),
		__NAMESPACE__ . '\\cmx_render_central_notizen_box',
		$post_type,
		'normal',
		'low'
	);
}, 10, 1);

\add_action('admin_enqueue_scripts', function (): void {
	if (!\function_exists('get_current_screen') || !\function_exists('wp_enqueue_editor')) {
		return;
	}

	$screen = \get_current_screen();
	if (!$screen instanceof \WP_Screen) {
		return;
	}
	if (!\in_array((string) $screen->base, ['post', 'post-new'], true)) {
		return;
	}
	if (!\in_array((string) ($screen->post_type ?? ''), cmx_notizen_supported_post_types(), true)) {
		return;
	}

	\wp_enqueue_editor();
});

\add_action('save_post', function ($post_id, $post, $update) {
	if (!($post instanceof \WP_Post)) {
		return;
	}
	$post_type = (string) $post->post_type;
	if (!\in_array($post_type, cmx_notizen_supported_post_types(), true)) {
		return;
	}
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) return;
	if (!\current_user_can('edit_post', $post_id)) return;
	if (!isset($_POST['cmx_intern_notizen_nonce']) || !\wp_verify_nonce((string) $_POST['cmx_intern_notizen_nonce'], 'cmx_intern_notizen_save')) {
		return;
	}

	$rows = $_POST['cmx_intern_notizen_rows'] ?? [];
	if (!\is_array($rows)) {
		$rows = [];
	} else {
		$rows = \wp_unslash($rows);
	}

	$clean = [];
	foreach ($rows as $row) {
		$norm = cmx_notizen_normalize_row($row);
		if ($norm === null) {
			continue;
		}
		if ($norm['text'] !== '' && $norm['datum'] === '') {
			$norm['datum'] = cmx_notizen_now_date();
		}
		if ($norm['text'] !== '' && $norm['zeit'] === '') {
			$norm['zeit'] = cmx_notizen_now_time();
		}
		$clean[] = $norm;
	}

	$meta_key = cmx_notizen_meta_key_for_post_type($post_type);
	if (empty($clean)) {
		\delete_post_meta($post_id, $meta_key);
	} else {
		\update_post_meta($post_id, $meta_key, $clean);
	}

	foreach (cmx_notizen_legacy_meta_keys($post_type) as $legacy_key) {
		\delete_post_meta($post_id, $legacy_key);
	}
}, 10, 3);

\add_action('do_meta_boxes', function ($post_type, $context) {
	$post_type = (string) $post_type;
	if ($context !== 'normal' || !\in_array($post_type, cmx_notizen_supported_post_types(), true)) {
		return;
	}

	$user_id = \get_current_user_id();
	if ($user_id <= 0) {
		return;
	}

	$opt_key = 'meta-box-order_' . $post_type;
	$order = \get_user_option($opt_key);
	if (!\is_array($order)) {
		$order = ['normal' => '', 'advanced' => '', 'side' => ''];
	} else {
		$order += ['normal' => '', 'advanced' => '', 'side' => ''];
	}

	$ids = \array_filter(\array_map('trim', \explode(',', (string) $order['normal'])));
	$ids = \array_values(\array_filter($ids, static function ($id): bool {
		return (string) $id !== CMX_NOTIZEN_MB_ID;
	}));
	$ids[] = CMX_NOTIZEN_MB_ID;

	$order['normal'] = \implode(',', $ids);
	\update_user_option($user_id, $opt_key, $order, true);
}, 99, 2);
