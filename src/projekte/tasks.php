<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Aufgaben / Tätigkeiten (wiederholbare Zeilen)
 * Felder pro Zeile: Datum, Uhrzeit, Dauer (h), Artikel (CPT artikel), Verrechenbar, Info
 */

const CMX_PROJEKT_TASK_META = '_cmx_projekt_tasks';
const CMX_TASK_POST_TYPES   = ['projekte', 'kontakte'];

if (!\function_exists(__NAMESPACE__ . '\\cmx_projekt_task_uid')) {
	function cmx_projekt_task_uid($raw = ''): string {
		$uid = (string) $raw;
		$uid = (string) \preg_replace('/[^A-Za-z0-9_-]/', '', $uid);
		$uid = \substr($uid, 0, 80);
		if ($uid !== '') return $uid;

		$seed = '';
		if (\function_exists('\\wp_generate_uuid4')) {
			$seed = (string) \wp_generate_uuid4();
		}
		if ($seed === '') {
			$seed = \uniqid('', true);
		}
		$seed = (string) \preg_replace('/[^A-Za-z0-9]/', '', $seed);
		if ($seed === '') {
			$seed = (string) \mt_rand(100000, 999999) . (string) \time();
		}
		return 'tsk_' . \substr($seed, 0, 64);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_projekt_decimal_normalize')) {
	function cmx_projekt_decimal_normalize($value): string {
		$s = \trim((string) $value);
		if ($s === '') return '';
		$s = \str_replace([" ", "'"], '', $s);
		$has_comma = \strpos($s, ',') !== false;
		$has_dot   = \strpos($s, '.') !== false;
		if ($has_comma && $has_dot) {
			if (\strrpos($s, ',') > \strrpos($s, '.')) {
				$s = \str_replace('.', '', $s);
				$s = \str_replace(',', '.', $s);
			} else {
				$s = \str_replace(',', '', $s);
			}
		} elseif ($has_comma) {
			$s = \str_replace(',', '.', $s);
		}
		return \is_numeric($s) ? $s : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_projekt_decimal_to_float')) {
	function cmx_projekt_decimal_to_float($value): float {
		$norm = \function_exists(__NAMESPACE__ . '\\cmx_projekt_decimal_normalize')
			? cmx_projekt_decimal_normalize($value)
			: \trim((string) $value);
		return $norm !== '' ? (float) $norm : 0.0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_projekt_format_swiss_number')) {
	function cmx_projekt_format_swiss_number($value, int $decimals = 2): string {
		return \number_format((float) $value, $decimals, '.', "'");
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_projekt_truthy')) {
	function cmx_projekt_truthy($value): bool {
		if ($value === true) return true;
		if ($value === 1 || $value === '1') return true;
		$s = \strtolower(\trim((string) $value));
		return \in_array($s, ['true', 'yes', 'on'], true);
	}
}

// Metabox registrieren
\add_action('add_meta_boxes', function() {
	foreach (CMX_TASK_POST_TYPES as $post_type) {
		\add_meta_box(
			'cmx_projekt_tasks',
			'Tätigkeiten',
			__NAMESPACE__ . '\\cmx_render_projekt_tasks_box',
			$post_type,
			'normal',
			'default'
		);
	}
});

/**
 * Metabox-Renderer
 */
function cmx_render_projekt_tasks_box(\WP_Post $post): void {
	\wp_nonce_field('cmx_projekt_tasks_nonce', 'cmx_projekt_tasks_nonce');

	$tasks = \get_post_meta($post->ID, CMX_PROJEKT_TASK_META, true);
	if (!is_array($tasks)) {
		$tasks = [];
	}

	// Artikel-Liste laden
	$artikel = new \WP_Query([
		'post_type'      => 'artikel',
		'post_status'    => ['publish','draft','pending','future','private'],
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	]);

	$artikel_options = [];
	if ($artikel->have_posts()) {
		foreach ($artikel->posts as $aid) {
			$titel = \get_the_title($aid) ?: '(#' . $aid . ')';
			$sku   = (string) \get_post_meta($aid, \defined(__NAMESPACE__.'\\CMX_ARTIKEL_META_SKU') ? CMX_ARTIKEL_META_SKU : '_cmx_artikel_sku', true);
			$label = $sku !== '' ? $sku . ' – ' . $titel : $titel;
			$artikel_options[] = ['id' => (int)$aid, 'label' => $label];
		}
	}
	\wp_reset_postdata();

	// Mindestens eine leere Zeile anzeigen
	if (empty($tasks)) {
		$tasks[] = ['datum'=>'', 'zeit'=>'', 'dauer'=>'', 'artikel_id'=>'', 'verrechenbar'=>1, 'info'=>''];
	}

	echo '<style>
	#cmx-projekt-tasks{display:flex;flex-direction:column;gap:8px;}
	#cmx-projekt-tasks .cmx-task-row{display:grid;grid-template-columns:minmax(0,1fr) 220px;gap:10px;padding:8px;border:1px solid #ddd;border-radius:6px;background:#fafafa;}
	#cmx-projekt-tasks .cmx-task-main{min-width:0;}
	#cmx-projekt-tasks .cmx-task-side{display:flex;flex-direction:column;gap:8px;}
	#cmx-projekt-tasks .cmx-task-label{display:flex;flex-direction:column;gap:4px;min-width:0;}
	#cmx-projekt-tasks .cmx-task-label-inline{display:flex;align-items:center;gap:6px;}
	#cmx-projekt-tasks .cmx-task-checkboxes{display:flex;flex-wrap:wrap;align-items:center;gap:16px;}
	#cmx-projekt-tasks .cmx-task-check{display:flex;justify-content:flex-start;align-items:center;}
	#cmx-projekt-tasks .cmx-task-check-label{display:inline-flex;align-items:center;gap:6px;font-weight:600;cursor:pointer;}
	#cmx-projekt-tasks .cmx-task-check-label input[type=checkbox]{margin:0;}
	#cmx-projekt-tasks select,#cmx-projekt-tasks textarea{width:100%;}
	#cmx-projekt-tasks textarea{min-height:120px;}
	#cmx-projekt-tasks .cmx-task-footer{grid-column:1 / -1;display:flex;justify-content:space-between;align-items:center;gap:10px;}
	#cmx-projekt-tasks .cmx-task-actions{display:flex;justify-content:flex-end;align-items:center;gap:8px;margin-top:0;}
	#cmx-projekt-tasks .cmx-task-remove{color:#a00;font-size:18px;line-height:1;min-width:36px;}
	#cmx-task-add{min-width:140px;}
	@media (max-width:782px){
		#cmx-projekt-tasks .cmx-task-row{grid-template-columns:1fr;}
	}
	</style>';
	echo '<div id="cmx-projekt-tasks">';
	foreach ($tasks as $idx => $row) {
		cmx_render_task_row($idx, $row, $artikel_options);
	}
	echo '</div>';

	echo '<p><button type="button" class="button" id="cmx-task-add">+ Zeile hinzufügen</button></p>';

	?>
	<script>
		(function(){
			const container = document.getElementById('cmx-projekt-tasks');
			if (!container) return;

		function parseSwissDecimal(value){
			let s = (value ?? '').toString().trim();
			if (s === '') return NaN;
			s = s.replace(/\s+/g, '').replace(/'/g, '');
			const hasComma = s.indexOf(',') > -1;
			const hasDot = s.indexOf('.') > -1;
			if (hasComma && hasDot) {
				if (s.lastIndexOf(',') > s.lastIndexOf('.')) {
					s = s.replace(/\./g, '').replace(/,/g, '.');
				} else {
					s = s.replace(/,/g, '');
				}
			} else if (hasComma) {
				s = s.replace(/,/g, '.');
			}
			const n = parseFloat(s);
			return Number.isFinite(n) ? n : NaN;
		}
		function formatSwissNumber(value){
			const n = Number(value);
			if (!Number.isFinite(n)) return '';
			const fixed = n.toFixed(2).split('.');
			let left = fixed[0];
			let out = '';
			while (left.length > 3) {
				out = "'" + left.slice(-3) + out;
				left = left.slice(0, -3);
			}
			return left + out + '.' + fixed[1];
		}
		function formatDauerInput(input){
			if (!input) return;
			const raw = (input.value || '').toString().trim();
			if (raw === '') {
				input.value = '';
				return;
			}
			const n = parseSwissDecimal(raw);
			if (!Number.isFinite(n)) return;
			input.value = formatSwissNumber(n);
		}

		function today() {
			const d = new Date();
			return d.toISOString().slice(0,10);
		}
			function nowTime() {
				const d = new Date();
				const hh = String(d.getHours()).padStart(2, '0');
				const mm = String(d.getMinutes()).padStart(2, '0');
				return hh + ':' + mm;
			}
			function newUid() {
				return 'tsk_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
			}

		function addRow(data){
			const idx = container.querySelectorAll('.cmx-task-row').length;
			const tpl = document.getElementById('cmx-task-template').innerHTML.replace(/__INDEX__/g, idx);
			const wrapper = document.createElement('div');
			wrapper.innerHTML = tpl;
			const rowEl = wrapper.firstElementChild;
			// Vorbelegte Werte
			rowEl.querySelector('input[name*="[datum]"]').value  = data?.datum  || '';
				rowEl.querySelector('input[name*="[zeit]"]').value   = data?.zeit   || '';
				const dauerInput = rowEl.querySelector('input[name*="[dauer]"]');
				if (dauerInput) {
					dauerInput.value = data?.dauer || '';
					formatDauerInput(dauerInput);
				}
				rowEl.querySelector('select[name*="[artikel_id]"]').value = data?.artikel_id || '';
				const verrechenbarInput = rowEl.querySelector('input[name*="[verrechenbar]"]');
				if (verrechenbarInput && data && Object.prototype.hasOwnProperty.call(data, 'verrechenbar')) {
					const raw = data.verrechenbar;
					const isOff = raw === 0 || raw === '0' || raw === false || raw === 'false' || raw === 'off' || raw === 'no';
					verrechenbarInput.checked = !isOff;
				}
				rowEl.querySelector('textarea[name*="[info]"]').value = data?.info || '';
				const uidInput = rowEl.querySelector('input[name*="[uid]"]');
				if (uidInput && !uidInput.value) {
					uidInput.value = newUid();
				}
				container.appendChild(rowEl);
			}

			container.querySelectorAll('input[name*="[uid]"]').forEach(function(el){
				if (!el.value) el.value = newUid();
			});
			container.querySelectorAll('input[name*="[dauer]"]').forEach(function(el){
				formatDauerInput(el);
			});

		container.addEventListener('focusin', function(e){
			const t = e.target;
			if (!t || !t.matches || !t.matches('input[name*="[dauer]"]')) return;
			setTimeout(function(){
				try { t.select(); } catch(err) {}
			}, 0);
		});
		container.addEventListener('blur', function(e){
			const t = e.target;
			if (!t || !t.matches || !t.matches('input[name*="[dauer]"]')) return;
			formatDauerInput(t);
		}, true);

		document.getElementById('cmx-task-add')?.addEventListener('click', function(){
			addRow({});
		});

		container.addEventListener('click', function(e){
			if (e.target.classList.contains('cmx-task-remove')) {
				const row = e.target.closest('.cmx-task-row');
				if (row && container.children.length > 1) {
					row.remove();
				}
			}
				if (e.target.classList.contains('cmx-task-today')) {
					e.preventDefault();
					const row = e.target.closest('.cmx-task-row');
					if (!row) return;
					const dateInput = row.querySelector('input[type="date"]');
					if (dateInput) {
						dateInput.value = today();
					}
				}
				if (e.target.classList.contains('cmx-task-now')) {
					e.preventDefault();
					const row = e.target.closest('.cmx-task-row');
					if (!row) return;
					const timeInput = row.querySelector('input[type="time"]');
					if (timeInput) {
						timeInput.value = nowTime();
					}
				}
			});
		})();
		</script>

	<script type="text/template" id="cmx-task-template">
		<?php cmx_render_task_row('__INDEX__', ['datum'=>'','zeit'=>'','dauer'=>'','artikel_id'=>'','verrechenbar'=>1,'info'=>''], $artikel_options, true); ?>
	</script>
	<?php
}

/**
 * Einzelne Zeile rendern
 */
function cmx_render_task_row($idx, array $row, array $artikel_options, bool $is_template = false): void {
	$datum  = esc_attr($row['datum'] ?? '');
	$zeit   = esc_attr($row['zeit'] ?? '');
	$dauer_raw = (string) ($row['dauer'] ?? '');
	$dauer_norm = \function_exists(__NAMESPACE__ . '\\cmx_projekt_decimal_normalize')
		? cmx_projekt_decimal_normalize($dauer_raw)
		: '';
	$dauer = '';
	if ($dauer_raw !== '') {
		if ($dauer_norm !== '' && \function_exists(__NAMESPACE__ . '\\cmx_projekt_format_swiss_number')) {
			$dauer = \esc_attr(cmx_projekt_format_swiss_number((float) $dauer_norm, 2));
		} else {
			$dauer = \esc_attr($dauer_raw);
		}
	}
	$art_id = (int) ($row['artikel_id'] ?? 0);
	$info   = esc_textarea($row['info'] ?? '');
	$is_verrechenbar = \array_key_exists('verrechenbar', $row)
		? (\function_exists(__NAMESPACE__ . '\\cmx_projekt_truthy') ? cmx_projekt_truthy($row['verrechenbar']) : !empty($row['verrechenbar']))
		: true;
	$task_uid_raw = (string) ($row['uid'] ?? '');
	$task_uid = '';
	if (!$is_template) {
		$task_uid = \function_exists(__NAMESPACE__ . '\\cmx_projekt_task_uid')
			? cmx_projekt_task_uid($task_uid_raw)
			: $task_uid_raw;
	}

	$name_base = $is_template ? '__INDEX__' : (string)$idx;

	echo '<div class="cmx-task-row">';
	echo '<input type="hidden" name="cmx_tasks['.$name_base.'][uid]" value="'.\esc_attr($task_uid).'">';
	echo '<div class="cmx-task-main">';
	echo '<label class="cmx-task-label"><span>Artikel</span><select name="cmx_tasks['.$name_base.'][artikel_id]" class="widefat cmx-task-artikel">';
	echo '<option value="">— auswählen —</option>';
	foreach ($artikel_options as $opt) {
		printf('<option value="%d"%s>%s</option>', (int)$opt['id'], selected($art_id, $opt['id'], false), esc_html($opt['label']));
	}
	echo '</select></label>';
	echo '<label class="cmx-task-label" style="margin-top:8px;"><span>Info</span><textarea name="cmx_tasks['.$name_base.'][info]" rows="4">'.$info.'</textarea></label>';
	echo '</div>';
	echo '<div class="cmx-task-side">';
	echo '<label class="cmx-task-label"><span>Dauer (h)</span><input type="text" inputmode="decimal" class="cmx-task-dauer" name="cmx_tasks['.$name_base.'][dauer]" value="'.$dauer.'" /></label>';
	echo '<label class="cmx-task-label">';
	echo '<span class="cmx-task-label-inline">Datum <a href="#" class="cmx-task-today" style="color:#d63638;text-decoration:none;">heute</a></span>';
	echo '<input type="date" name="cmx_tasks['.$name_base.'][datum]" value="'.$datum.'" />';
	echo '</label>';
	echo '<label class="cmx-task-label">';
	echo '<span class="cmx-task-label-inline">Uhrzeit <a href="#" class="cmx-task-now" style="color:#d63638;text-decoration:none;">jetzt</a></span>';
	echo '<input type="time" name="cmx_tasks['.$name_base.'][zeit]" value="'.$zeit.'" />';
	echo '</label>';
	echo '</div>';
	$verrechenbar_checked = $is_verrechenbar ? 'checked' : '';
	echo '<div class="cmx-task-footer">';
	echo '<div class="cmx-task-checkboxes">';
	echo '<div class="cmx-task-check"><input type="hidden" name="cmx_tasks['.$name_base.'][verrechenbar]" value="0"><label class="cmx-task-check-label"><input type="checkbox" name="cmx_tasks['.$name_base.'][verrechenbar]" value="1" '.$verrechenbar_checked.'><span>Verrechenbar</span></label></div>';
	$checked = !empty($row['abgerechnet']) ? 'checked' : '';
	echo '<div class="cmx-task-check"><label class="cmx-task-check-label"><input type="checkbox" name="cmx_tasks['.$name_base.'][abgerechnet]" value="1" '.$checked.'><span>Verrechnet</span></label></div>';
	echo '</div>';
	echo '<div class="cmx-task-actions">';
	echo '<button type="button" class="button cmx-task-remove" aria-label="Zeile entfernen"><span class="dashicons dashicons-trash cmx-task-remove"></span></button>';
	echo '</div>';
	echo '</div>';
	echo '</div>';
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_save_tasks_metabox')) {
	function cmx_save_tasks_metabox($post_id, $post, $update): void {
		if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
		if (!\current_user_can('edit_post', $post_id)) return;
		if (empty($_POST['cmx_projekt_tasks_nonce']) || !\wp_verify_nonce($_POST['cmx_projekt_tasks_nonce'], 'cmx_projekt_tasks_nonce')) return;

		$rows = $_POST['cmx_tasks'] ?? [];
		if (!is_array($rows)) $rows = [];
		$seen_uids = [];

		$clean = [];
		foreach ($rows as $row) {
			if (!is_array($row)) continue;
			$uid = \function_exists(__NAMESPACE__ . '\\cmx_projekt_task_uid')
				? cmx_projekt_task_uid($row['uid'] ?? '')
				: (string) ($row['uid'] ?? '');
			while ($uid === '' || isset($seen_uids[$uid])) {
				$uid = \function_exists(__NAMESPACE__ . '\\cmx_projekt_task_uid')
					? cmx_projekt_task_uid('')
					: ('tsk_' . \uniqid());
			}
			$seen_uids[$uid] = true;
			$datum  = sanitize_text_field($row['datum'] ?? '');
			$zeit   = sanitize_text_field($row['zeit'] ?? '');
			$dauer_raw = sanitize_text_field($row['dauer'] ?? '');
			$dauer_norm = \function_exists(__NAMESPACE__ . '\\cmx_projekt_decimal_normalize')
				? cmx_projekt_decimal_normalize($dauer_raw)
				: '';
			$dauer  = ($dauer_raw === '' || $dauer_norm === '') ? '' : \number_format((float) $dauer_norm, 2, '.', '');
			$art_id = (int) ($row['artikel_id'] ?? 0);
			$info   = sanitize_textarea_field($row['info'] ?? '');

			// ignorieren, wenn alles leer
			if ($datum === '' && $zeit === '' && $dauer === '' && $art_id === 0 && $info === '') continue;

			// Default: aktuelles Datum, falls keins gesetzt
			if ($datum === '') {
				$datum = current_time('Y-m-d');
			}

			$clean[] = [
				'uid'        => $uid,
				'datum'      => $datum,
				'zeit'       => $zeit,
				'dauer'      => $dauer,
				'artikel_id' => $art_id,
				'verrechenbar' => \array_key_exists('verrechenbar', $row) ? (!empty($row['verrechenbar']) ? 1 : 0) : 1,
				'abgerechnet'=> !empty($row['abgerechnet']) ? 1 : 0,
				'info'       => $info,
			];
		}

		if (empty($clean)) {
			\delete_post_meta($post_id, CMX_PROJEKT_TASK_META);
		} else {
			\update_post_meta($post_id, CMX_PROJEKT_TASK_META, $clean);
		}
	}
}

// Speichern für Projekte, Kontakte und Artikel.
foreach (CMX_TASK_POST_TYPES as $cmx_task_post_type) {
	\add_action('save_post_' . $cmx_task_post_type, __NAMESPACE__ . '\\cmx_save_tasks_metabox', 10, 3);
}
