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

if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_normalize_row')) {
	/**
	 * @param mixed $row
	 * @return array{datum:string,zeit:string,text:string}|null
	 */
	function cmx_notizen_normalize_row($row): ?array {
		$datum = '';
		$zeit  = '';
		$text  = '';

		if (\is_array($row)) {
			$datum = \sanitize_text_field((string) ($row['datum'] ?? ($row['date'] ?? '')));
			$zeit  = \sanitize_text_field((string) ($row['zeit'] ?? ($row['time'] ?? ($row['uhrzeit'] ?? ''))));
			$text  = \sanitize_textarea_field((string) ($row['text'] ?? ($row['notiz'] ?? ($row['note'] ?? ($row['info'] ?? '')))));
		} elseif (\is_string($row)) {
			$text = \sanitize_textarea_field($row);
		}

		$datum = \trim($datum);
		$zeit  = \trim($zeit);
		$text  = \trim($text);

		if ($datum !== '' && !cmx_notizen_is_valid_date($datum)) {
			$datum = '';
		}
		if ($zeit !== '' && !cmx_notizen_is_valid_time($zeit)) {
			$zeit = '';
		}

		if ($datum === '' && $zeit === '' && $text === '') {
			return null;
		}

		return ['datum' => $datum, 'zeit' => $zeit, 'text' => $text];
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
	 * @return array<int, array{datum:string,zeit:string,text:string}>
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
			$is_single_row = isset($raw['datum']) || isset($raw['date']) || isset($raw['zeit']) || isset($raw['time']) || isset($raw['text']) || isset($raw['notiz']) || isset($raw['note']) || isset($raw['info']);
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
		$datum = \esc_attr((string) ($row['datum'] ?? ''));
		$zeit  = \esc_attr((string) ($row['zeit'] ?? ''));
		$text  = \esc_textarea((string) ($row['text'] ?? ''));

		echo '<div class="cmx-intern-notiz-row">';
		echo '<div class="cmx-intern-notiz-main">';
		echo '<label class="cmx-intern-notiz-label"><span>Notiz</span><textarea rows="4" name="cmx_intern_notizen_rows[' . $name_index . '][text]">' . $text . '</textarea></label>';
		echo '</div>';

		echo '<div class="cmx-intern-notiz-side">';
		echo '<label class="cmx-intern-notiz-label">';
		echo '<span class="cmx-intern-notiz-label-inline">Datum <a href="#" class="cmx-notiz-heute">heute</a></span>';
		echo '<input type="date" name="cmx_intern_notizen_rows[' . $name_index . '][datum]" value="' . $datum . '" />';
		echo '</label>';

		echo '<label class="cmx-intern-notiz-label">';
		echo '<span class="cmx-intern-notiz-label-inline">Uhrzeit <a href="#" class="cmx-notiz-jetzt">jetzt</a></span>';
		echo '<input type="time" name="cmx_intern_notizen_rows[' . $name_index . '][zeit]" value="' . $zeit . '" />';
		echo '</label>';
		echo '<p class="cmx-intern-notiz-remove-wrap"><button type="button" class="button cmx-notiz-remove" aria-label="Zeile entfernen">x</button></p>';
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
		echo '#cmx-intern-notizen-list textarea{width:100%;min-height:120px;}';
		echo '#cmx-intern-notizen-list .cmx-notiz-heute,#cmx-intern-notizen-list .cmx-notiz-jetzt{color:#d63638;text-decoration:none;}';
		echo '#cmx-intern-notizen-list .cmx-intern-notiz-remove-wrap{margin:0;text-align:right;}';
		echo '#cmx-intern-notizen-list .cmx-notiz-remove{color:#a00;font-size:18px;line-height:1;min-width:36px;}';
		echo '#cmx-intern-notizen-actions{display:flex;justify-content:flex-end;margin-top:10px;}';
		echo '#cmx-intern-notizen-actions .button{min-width:220px;text-align:center;}';
		echo '@media (max-width:782px){#cmx-intern-notizen-list .cmx-intern-notiz-row{grid-template-columns:1fr;}#cmx-intern-notizen-actions{justify-content:flex-start;}#cmx-intern-notizen-actions .button{min-width:0;}}';
		echo '</style>';
		echo '<div id="cmx-intern-notizen-list">';
		foreach ($rows as $idx => $row) {
			cmx_notizen_render_row((int) $idx, $row, false);
		}
		echo '</div>';
		echo '<div id="cmx-intern-notizen-actions"><button type="button" class="button" id="cmx-intern-notizen-add">' . \esc_html__('Notiz hinzufügen', 'cmx') . '</button></div>';
		?>
		<script type="text/template" id="cmx-intern-notizen-template">
			<?php cmx_notizen_render_row('__INDEX__', ['datum' => '', 'zeit' => '', 'text' => ''], true); ?>
		</script>
		<script>
		(function(){
			const list = document.getElementById('cmx-intern-notizen-list');
			const addBtn = document.getElementById('cmx-intern-notizen-add');
			const tpl = document.getElementById('cmx-intern-notizen-template');
			if (!list || !addBtn || !tpl || list.dataset.cmxBound === '1') return;
			list.dataset.cmxBound = '1';

			function today() {
				const d = new Date();
				return d.toISOString().slice(0, 10);
			}
			function nowTime() {
				const d = new Date();
				return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
			}
			function addRow(seed){
				const idx = list.querySelectorAll('.cmx-intern-notiz-row').length;
				const html = tpl.innerHTML.replace(/__INDEX__/g, String(idx));
				const wrap = document.createElement('div');
				wrap.innerHTML = html.trim();
				const row = wrap.firstElementChild;
				if (!row) return;
				const dateInput = row.querySelector('input[type="date"]');
				const timeInput = row.querySelector('input[type="time"]');
				const textInput = row.querySelector('textarea');
				if (dateInput) dateInput.value = seed && seed.datum ? seed.datum : today();
				if (timeInput) timeInput.value = seed && seed.zeit ? seed.zeit : nowTime();
				if (textInput) textInput.value = seed && seed.text ? seed.text : '';
				list.appendChild(row);
			}

			addBtn.addEventListener('click', function(){
				addRow({});
			});

			list.addEventListener('click', function(e){
				const removeBtn = e.target && e.target.closest ? e.target.closest('.cmx-notiz-remove') : null;
				if (removeBtn) {
					e.preventDefault();
					const row = removeBtn.closest('.cmx-intern-notiz-row');
					if (!row) return;
					const allRows = list.querySelectorAll('.cmx-intern-notiz-row');
					if (allRows.length <= 1) {
						const dateInput = row.querySelector('input[type="date"]');
						const timeInput = row.querySelector('input[type="time"]');
						const textInput = row.querySelector('textarea');
						if (dateInput) dateInput.value = today();
						if (timeInput) timeInput.value = nowTime();
						if (textInput) textInput.value = '';
						return;
					}
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
