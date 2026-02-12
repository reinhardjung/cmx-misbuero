<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Aufgaben / Tätigkeiten für Projekte (wiederholbare Zeilen)
 * Felder pro Zeile: Datum, Uhrzeit, Dauer (h), Artikel (CPT artikel), Info
 */

const CMX_PROJEKT_TASK_META = '_cmx_projekt_tasks';

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

// Metabox registrieren
\add_action('add_meta_boxes', function() {
	\add_meta_box(
		'cmx_projekt_tasks',
		'Tätigkeiten',
		__NAMESPACE__ . '\\cmx_render_projekt_tasks_box',
		'projekte',
		'normal',
		'default'
	);
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
		$tasks[] = ['datum'=>'', 'zeit'=>'', 'dauer'=>'', 'artikel_id'=>'', 'info'=>''];
	}

	echo '<div id="cmx-projekt-tasks" style="display:flex;flex-direction:column;gap:8px;">';
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
				rowEl.querySelector('input[name*="[dauer]"]').value  = data?.dauer  || '';
				rowEl.querySelector('select[name*="[artikel_id]"]').value = data?.artikel_id || '';
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
		<?php cmx_render_task_row('__INDEX__', ['datum'=>'','zeit'=>'','dauer'=>'','artikel_id'=>'','info'=>''], $artikel_options, true); ?>
	</script>
	<?php
}

/**
 * Einzelne Zeile rendern
 */
function cmx_render_task_row($idx, array $row, array $artikel_options, bool $is_template = false): void {
	$datum  = esc_attr($row['datum'] ?? '');
	$zeit   = esc_attr($row['zeit'] ?? '');
	$dauer  = esc_attr($row['dauer'] ?? '');
	$art_id = (int) ($row['artikel_id'] ?? 0);
	$info   = esc_textarea($row['info'] ?? '');
	$task_uid_raw = (string) ($row['uid'] ?? '');
	$task_uid = '';
	if (!$is_template) {
		$task_uid = \function_exists(__NAMESPACE__ . '\\cmx_projekt_task_uid')
			? cmx_projekt_task_uid($task_uid_raw)
			: $task_uid_raw;
	}

	$name_base = $is_template ? '__INDEX__' : (string)$idx;

	echo '<div class="cmx-task-row" style="display:flex;flex-wrap:wrap;gap:8px;padding:8px;border:1px solid #ddd;border-radius:6px;background:#fafafa;">';
	echo '<input type="hidden" name="cmx_tasks['.$name_base.'][uid]" value="'.\esc_attr($task_uid).'">';
	echo '<label style="display:flex;flex-direction:column;gap:4px;min-width:140px;">';
	echo '<span style="display:flex;align-items:center;gap:6px;">Datum <a href="#" class="cmx-task-today" style="color:#d63638;text-decoration:none;">heute</a></span>';
	echo '<input type="date" name="cmx_tasks['.$name_base.'][datum]" value="'.$datum.'" />';
	echo '</label>';
	echo '<label style="display:flex;flex-direction:column;gap:4px;min-width:120px;">';
	echo '<span style="display:flex;align-items:center;gap:6px;">Uhrzeit <a href="#" class="cmx-task-now" style="color:#d63638;text-decoration:none;">jetzt</a></span>';
	echo '<input type="time" name="cmx_tasks['.$name_base.'][zeit]" value="'.$zeit.'" />';
	echo '</label>';
	echo '<label style="display:flex;flex-direction:column;gap:4px;min-width:100px;"><span>Dauer (h)</span><input type="number" step="0.25" min="0" name="cmx_tasks['.$name_base.'][dauer]" value="'.$dauer.'" /></label>';

	echo '<label style="display:flex;flex-direction:column;gap:4px;min-width:220px;flex:1 1 220px;"><span>&nbsp;Artikel</span><select name="cmx_tasks['.$name_base.'][artikel_id]">';
	echo '<option value="">— auswählen —</option>';
	foreach ($artikel_options as $opt) {
		printf('<option value="%d"%s>%s</option>', (int)$opt['id'], selected($art_id, $opt['id'], false), esc_html($opt['label']));
	}
	echo '</select></label>';

	$checked = !empty($row['abgerechnet']) ? 'checked' : '';
	echo '<label style="display:flex;flex-direction:column;gap:4px;align-items:flex-start;min-width:120px;"><span>Verrechnet</span><input type="checkbox" name="cmx_tasks['.$name_base.'][abgerechnet]" value="1" '.$checked.' style="margin:6px 0 0 6px;"> </label>';
	echo '<div style="display:flex;align-items:flex-start;gap:8px;flex:1 1 100%;">';
	echo '<label style="display:flex;flex-direction:column;gap:4px;flex:1 1 auto;"><span>Info</span><textarea name="cmx_tasks['.$name_base.'][info]" rows="2" style="width:100%;">'.$info.'</textarea></label>';
	echo '<button type="button" class="button cmx-task-remove" aria-label="Zeile entfernen" style="margin-top:22px; color:red; font-size:large;">x</button>';
	echo '</div>';
	echo '</div>';
}

// Speichern
\add_action('save_post_projekte', function($post_id, $post, $update) {
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
		$dauer  = sanitize_text_field($row['dauer'] ?? '');
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
			'abgerechnet'=> !empty($row['abgerechnet']) ? 1 : 0,
			'info'       => $info,
		];
	}

	if (empty($clean)) {
		\delete_post_meta($post_id, CMX_PROJEKT_TASK_META);
	} else {
		\update_post_meta($post_id, CMX_PROJEKT_TASK_META, $clean);
	}
}, 10, 3);
