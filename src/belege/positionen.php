<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/* ------------------------------
 * Admin-Skripte nur im Belege-Editor laden
 * ------------------------------ */
add_action('admin_enqueue_scripts', function() {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if ($screen && $screen->post_type === 'belege') {
		wp_enqueue_script('jquery-ui-autocomplete');
		wp_enqueue_script('jquery-ui-sortable');
		wp_enqueue_style('wp-jquery-ui-dialog'); // Basis-Styles für jQuery UI
	}
});

/* ---------------------------------
 * Zentrale Helper: Artikel-Nr (SKU) – robust mit Fallbacks
 * --------------------------------- */
if (!function_exists(__NAMESPACE__ . '\cmx_get_artikel_nr')) {
	function cmx_get_artikel_nr($post_id){
		$nr = get_post_meta($post_id, 'cmx_artikel_sku', true);
		if ($nr === '' || $nr === null) {
			$tmp = get_post_meta($post_id, '_cmx_artikel_sku', true);
			if (is_string($tmp) && $tmp !== '' && strpos($tmp, 'field_') !== 0) {
				$nr = $tmp;
			}
		}
		if ($nr === '' || $nr === null) $nr = get_post_meta($post_id, '_cmx_artikel_nr', true);
		if ($nr === '' || $nr === null) $nr = get_post_meta($post_id, '_sku', true);

		if (($nr === '' || $nr === null) && function_exists('wc_get_product')) {
			$p = wc_get_product($post_id);
			if ($p && method_exists($p, 'get_sku')) $nr = $p->get_sku();
		}
		return is_string($nr) ? trim($nr) : '';
	}
}

/* ---------------------------------
 * Taxonomie-Erkennung: Belege-Textbausteine
 * --------------------------------- */
if (!function_exists(__NAMESPACE__ . '\cmx_beleg_textbaustein_taxonomy')) {
	function cmx_beleg_textbaustein_taxonomy(): string {
		$candidates = [];
		$const = __NAMESPACE__ . '\\TAX_BELEGE_BELEGETEXTBAUSTEINE';
		if (\defined($const)) {
			$candidates[] = (string) \constant($const);
		}
		$candidates = \array_merge($candidates, [
			'belege_belegetextbausteine',
			'belege_textbausteine',
			'belege_textbaustein',
			'beleg_textbausteine',
			'beleg_textbaustein',
		]);
		foreach ($candidates as $tax) {
			$tax = \sanitize_key((string) $tax);
			if ($tax !== '' && \taxonomy_exists($tax)) return $tax;
		}
		return '';
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_beleg_textbaustein_items')) {
	function cmx_beleg_textbaustein_items(string $search = '', int $limit = 30): array {
		$items = [];
		$seen  = [];
		$search = trim((string) $search);
		$search_lc = $search !== '' ? (function_exists('mb_strtolower') ? mb_strtolower($search, 'UTF-8') : strtolower($search)) : '';

		$add_item = static function(string $name, string $desc = '', int $id = 0) use (&$items, &$seen, $search_lc): void {
			$name = trim($name);
			$desc = trim($desc);
			if ($name === '' && $desc === '') return;

			$name_lc = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
			$desc_lc = function_exists('mb_strtolower') ? mb_strtolower($desc, 'UTF-8') : strtolower($desc);
			if ($search_lc !== '' && strpos($name_lc, $search_lc) === false && strpos($desc_lc, $search_lc) === false) {
				return;
			}

			$key = $name_lc . '|' . $desc_lc;
			if (isset($seen[$key])) return;
			$seen[$key] = true;

			$items[] = [
				'label' => $name,
				'value' => $id,
				'nr'    => $name,
				'title' => $desc,
				'text'  => ($desc !== '' ? $desc : $name),
			];
		};

		$tax = cmx_beleg_textbaustein_taxonomy();
		if ($tax !== '') {
			$terms = get_terms([
				'taxonomy'   => $tax,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'number'     => $limit,
			]);
			if (!is_wp_error($terms) && is_array($terms)) {
				foreach ($terms as $t) {
					$add_item((string)($t->name ?? ''), (string)($t->description ?? ''), (int)($t->term_id ?? 0));
				}
			}
		}

		// Fallback/Ergaenzung aus INI-Section [BelegeTextbausteine]
		$ini_file = \dirname(__DIR__, 2) . '/includes/globales.ini';
		if (\is_file($ini_file)) {
			$ini = \parse_ini_file($ini_file, true, INI_SCANNER_TYPED);
			if (\is_array($ini)) {
				foreach ($ini as $section_name => $section_data) {
					if (!\is_array($section_data)) continue;
					if (\strcasecmp((string) $section_name, 'BelegeTextbausteine') !== 0) continue;
					foreach ($section_data as $name => $desc_raw) {
						$desc = \is_array($desc_raw)
							? \implode(', ', \array_values(\array_filter(\array_map(static fn($v) => \trim((string) $v), $desc_raw), static fn($v) => $v !== '')))
							: \trim((string) $desc_raw);
						$add_item((string) $name, $desc, 0);
					}
					break;
				}
			}
		}

		if (\count($items) > $limit) {
			$items = \array_slice($items, 0, $limit);
		}
		return $items;
	}
}

if (!function_exists(__NAMESPACE__ . '\cmx_beleg_textbaustein_admin_url')) {
	function cmx_beleg_textbaustein_admin_url(): string {
		$tax = cmx_beleg_textbaustein_taxonomy();
		if ($tax === '') {
			return '';
		}
		return \admin_url('edit-tags.php?taxonomy=' . \rawurlencode($tax) . '&post_type=belege');
	}
}

/* ------------------------------
 * Locale-robust: String -> Float normalisieren (Punkt/Komma/tausender)
 * ------------------------------ */
if (!function_exists(__NAMESPACE__ . '\cmx_norm_decimal')) {
	function cmx_norm_decimal($val){
		$s = (string)$val;
		// Tausendertrennzeichen entfernen
		$s = str_replace([" ", "'"], '', $s);
		$hasComma = strpos($s, ',') !== false;
		$hasDot   = strpos($s, '.') !== false;

		if ($hasComma && $hasDot) {
			// Das LETZTE Vorkommen bestimmt das Dezimaltrennzeichen
			if (strrpos($s, ',') > strrpos($s, '.')) {
				// Komma ist Dezimal → Punkte sind Tausender
				$s = str_replace('.', '', $s);
				$s = str_replace(',', '.', $s);
			} else {
				// Punkt ist Dezimal → Kommas sind Tausender
				$s = str_replace(',', '', $s);
			}
		} else {
			// Nur Komma vorhanden → als Dezimalpunkt behandeln
			$s = str_replace(',', '.', $s);
		}
		return $s;
	}
}

/* ------------------------------
 * Metabox registrieren
 * ------------------------------ */
add_action('add_meta_boxes', function() {
	add_meta_box(
		'cmx_beleg_positionen',
		'Positionen',
		__NAMESPACE__ . '\\cmx_render_beleg_positionen',
		'belege',
		'normal',
		'default'
	);
});

/* ------------------------------
 * Metabox Inhalt
 * ------------------------------ */
function cmx_render_beleg_positionen(\WP_Post $post) {
	wp_nonce_field('cmx_save_beleg_positionen', 'cmx_beleg_positionen_nonce');

	$positionen = get_post_meta($post->ID, '_cmx_beleg_positionen', true);
	if (is_string($positionen) && $positionen !== '') {
		$tmp = json_decode($positionen, true);
		$positionen = (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) ? $tmp : [];
	} elseif (!is_array($positionen)) {
		$positionen = [];
	}
	echo '<div id="cmx-positionen-wrap">';
	echo '<table class="widefat striped" id="cmx-positionen-table">
			<thead><tr>
				<th><a href="/wp-admin/edit.php?post_type=artikel" target="_blank" rel="noopener noreferrer">Artikel</a></th>
				<th>Menge</th>
				<th>Einzelpreis</th>
				<th>Rabatt</th>
				<th style="text-align:right;">Gesamt</th>
				<th>zus&auml;tzliche Notiz</th>
				<th></th>
			</tr></thead>
			<tbody>';

	$has_article_rows = false;
	if (!empty($positionen)) {
		foreach ($positionen as $i => $pos) {
			$custom_rendered = (bool) \apply_filters('cmx_beleg_positionen_render_custom_row', false, (int) $i, $pos);
			if ($custom_rendered) {
				continue;
			}
			$has_article_rows = true;
			cmx_render_position_row($i, $pos);
		}
	}
	if (empty($positionen) || !$has_article_rows) {
		$next_i = !empty($positionen) ? \count($positionen) : 0;
		cmx_render_position_row($next_i, []);
	}

	echo '</tbody></table>';
	echo '<p class="cmx-pos-actions"><button type="button" class="button button-secondary" id="cmx-add-pos">+ Position hinzufügen</button>';
	\do_action('cmx_beleg_positionen_after_add_button');
	echo '</p>';
	echo '</div>';

	cmx_beleg_positionen_js();
}

/* ------------------------------
 * Einzelzeile rendern (Autocomplete für Artikel)
 * ------------------------------ */
function cmx_render_position_row($i, $pos) {
	$artikel_id   = isset($pos['artikel_id']) ? (int)$pos['artikel_id'] : 0;
	$title        = $artikel_id ? get_the_title($artikel_id) : '';
	$nr           = $artikel_id ? cmx_get_artikel_nr($artikel_id) : '';
	$display      = esc_html( ($nr ? $nr.' – ' : '') . ($title ?: ($pos['artikel_name'] ?? '')) );
	$textbaustein_edit_url = \function_exists(__NAMESPACE__ . '\\cmx_beleg_textbaustein_admin_url')
		? (string) cmx_beleg_textbaustein_admin_url()
		: '';

	$menge        = (string)($pos['menge'] ?? '');
	$preis        = (string)($pos['preis'] ?? '');
	$beschreibung = esc_textarea($pos['beschreibung'] ?? '');
	$rabatt_raw   = trim((string)($pos['rabatt'] ?? ''));

	$menge_display = $menge !== '' ? cmx_format_swiss_number(cmx_norm_decimal($menge), 2) : '';
	$preis_display = $preis !== '' ? cmx_format_swiss_number(cmx_norm_decimal($preis), 2) : '';
	$rabatt_display = $rabatt_raw;
	if ($rabatt_raw !== '') {
		$is_percent = str_ends_with($rabatt_raw, '%');
		$raw = $is_percent ? substr($rabatt_raw, 0, -1) : $rabatt_raw;
		$raw = trim((string) preg_replace('/\s*(chf|fr\.?)\s*/i', '', $raw));
		if ($raw !== '' && is_numeric(cmx_norm_decimal($raw))) {
			$rabatt_display = cmx_format_swiss_number(cmx_norm_decimal($raw), 2) . ($is_percent ? '%' : '');
		}
	}
	$rabatt = esc_attr($rabatt_display);

	echo '<tr class="cmx-pos-row">';

	echo '<td style="min-width:260px">';
	$edit_link = $artikel_id ? get_edit_post_link($artikel_id, '') : '';
	echo '<a href="'.esc_url($edit_link).'" class="cmx-artikel-edit" data-cmx-help-key="beleg_artikel_edit" aria-label="Artikel bearbeiten" title="Artikel im neuen Tab bearbeiten" target="_blank" rel="noopener noreferrer" style="'.($edit_link ? '' : 'pointer-events:none; opacity:0.35;').'">✎</a>';
	echo '<input type="hidden" name="cmx_positionen['.$i.'][artikel_id]" class="cmx-artikel-id" value="'.esc_attr($artikel_id).'">';
	echo '<input type="text" class="regular-text cmx-artikel-autocomplete" data-cmx-help-key="beleg_artikel_suche" placeholder="Artikel suchen …" title="Artikel suchen" value="'.esc_attr($display).'" autocomplete="off" style="width:100%">';
	echo '</td>';

	// negative Mengen zulassen (Komma/Punkt erlaubt)
	echo '<td><input type="text" name="cmx_positionen['.$i.'][menge]" value="'.esc_attr($menge_display).'" style="width:90px"></td>';

	// Preis als Text (Komma/Punkt erlaubt)
	echo '<td><input type="text" name="cmx_positionen['.$i.'][preis]" value="'.esc_attr($preis_display).'" style="width:100px"></td>';

	echo '<td class="cmx-pos-rabatt-td" style="width:100px;">';
	echo '<input type="text" name="cmx_positionen['.$i.'][rabatt]" value="'.$rabatt.'" placeholder="" style="width:100px">';
	echo '</td>';

	// Initial total (robust normalisiert)
	$menge_f = (float)\CLOUDMEISTER\CMX\Buero\cmx_norm_decimal($menge);
	$preis_f = (float)\CLOUDMEISTER\CMX\Buero\cmx_norm_decimal($preis);
	$total_init = $menge_f * $preis_f;

	echo '<td class="cmx-pos-total" style="width:90px;text-align:right;">'.esc_html(cmx_format_swiss_number($total_init, 2)).'</td>';

	echo '<td class="cmx-pos-beschr-cell">';
	if ($textbaustein_edit_url !== '') {
		echo '<a href="'.esc_url($textbaustein_edit_url).'" class="cmx-textbaustein-edit" aria-label="Textbausteine bearbeiten" title="Textbausteine im neuen Tab bearbeiten" target="_blank" rel="noopener noreferrer">✎</a>';
	}
	echo '<textarea name="cmx_positionen['.$i.'][beschreibung]" rows="1" style="width:100%">'.$beschreibung.'</textarea>';
	echo '</td>';
	echo '<td class="cmx-pos-controls">';
	echo '<span class="cmx-pos-drag-handle" title="Zeile verschieben" aria-label="Zeile verschieben">↕</span>';
	echo '<button type="button" class="button-link-delete cmx-del-pos">✕</button>';
	echo '</td>';

	echo '</tr>';
}

/* ------------------------------
 * Speichern der Positionen (gehärtet)
 * ------------------------------ */
add_action('save_post_belege', function($post_id, \WP_Post $post, $update) {

	if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
	if ($post->post_type !== 'belege') return;
	if (!current_user_can('edit_post', $post_id)) return;
	if (!isset($_POST['cmx_beleg_positionen_nonce']) || !wp_verify_nonce($_POST['cmx_beleg_positionen_nonce'], 'cmx_save_beleg_positionen')) return;
	if (defined('DOING_AJAX') && DOING_AJAX) return;

	$positionen = $_POST['cmx_positionen'] ?? [];
	if (!is_array($positionen)) return;

	$max_rows = 500;
	if (count($positionen) > $max_rows) $positionen = array_slice($positionen, 0, $max_rows);

	$clean = [];
	foreach ($positionen as $row) {
		if (!is_array($row)) continue;
		$custom = \apply_filters('cmx_beleg_positionen_clean_custom_row', null, $row, (int) $post_id);
		if (\is_array($custom)) {
			$clean[] = $custom;
			continue;
		}
		if ($custom === false) {
			continue;
		}

		$artikel_id   = isset($row['artikel_id']) ? (int)$row['artikel_id'] : 0;

		$menge_raw    = isset($row['menge']) ? (string)$row['menge'] : '';
		$menge        = (float)\CLOUDMEISTER\CMX\Buero\cmx_norm_decimal($menge_raw);

		$preis_raw    = isset($row['preis']) ? (string)$row['preis'] : '';
		$preis        = (float)\CLOUDMEISTER\CMX\Buero\cmx_norm_decimal($preis_raw);

		$rabatt_raw   = isset($row['rabatt']) ? (string)$row['rabatt'] : '';
		$rabatt       = sanitize_text_field($rabatt_raw);

		$beschreibung_raw = isset($row['beschreibung']) ? (string)$row['beschreibung'] : '';
		$beschreibung_raw = wp_unslash($beschreibung_raw);
		$beschreibung_raw = str_replace(["\r\n", "\r"], "\n", $beschreibung_raw);
		$beschreibung = trim($beschreibung_raw);
		$task_idx     = isset($row['task_idx']) ? (int)$row['task_idx'] : null;

		// negative Mengen zulassen; nur 0 verwerfen
		if ($artikel_id <= 0 || $menge == 0.0) continue;
		if (strlen($beschreibung) > 10000) $beschreibung = substr($beschreibung, 0, 10000);

		$clean[] = [
			'artikel_id'   => $artikel_id,
			'menge'        => $menge,
			'preis'        => $preis,
			'rabatt'       => $rabatt,
			'beschreibung' => $beschreibung,
			'task_idx'     => $task_idx,
		];
	}

	// Altdaten angleichen (können als JSON-String oder Array vorliegen)
	$old_raw  = get_post_meta($post_id, '_cmx_beleg_positionen', true);
	if (is_string($old_raw) && $old_raw !== '') {
		$tmp = json_decode($old_raw, true);
		if (json_last_error() === JSON_ERROR_NONE) {
			$old_data = $tmp;
		} else {
			$old_data = @maybe_unserialize($old_raw);
			if (!is_array($old_data)) $old_data = [];
		}
	} elseif (is_array($old_raw)) {
		$old_data = $old_raw;
	} else {
		$old_data = [];
	}

	if ($old_data !== $clean) {
		update_post_meta($post_id, '_cmx_beleg_positionen', $clean);
	}

}, 10, 3);

/* ------------------------------
 * AJAX: VK-Preis aus Artikel (_cmx_artikel_vk)
 * ------------------------------ */
add_action('wp_ajax_cmx_get_artikel_vk', function() {
	if (!current_user_can('edit_posts')) wp_send_json_error(['msg' => 'forbidden'], 403);
	$artikel_id = isset($_POST['artikel_id']) ? (int) $_POST['artikel_id'] : 0;
	if ($artikel_id <= 0) wp_send_json_error(['msg' => 'no_id'], 400);
	$vk = get_post_meta($artikel_id, '_cmx_artikel_vk', true);
	wp_send_json_success(['vk' => ($vk === '' || $vk === null) ? '' : (string)$vk]);
});

/* ------------------------------
 * AJAX: Artikel-Suche
 * ------------------------------ */
add_action('wp_ajax_cmx_search_artikel', function() {
	if (!current_user_can('edit_posts')) wp_send_json_error(['msg' => 'forbidden'], 403);

	global $wpdb;

	$term  = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';
	$limit = 20;

	$post_type = 'artikel';
	$post_tbl  = $wpdb->posts;
	$meta_tbl  = $wpdb->postmeta;

	$ids = [];

	if ($term === '') {
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$post_tbl}
				 WHERE post_type=%s AND post_status<>'trash'
				 ORDER BY post_modified_gmt DESC, post_title ASC
				 LIMIT %d",
				$post_type, $limit
			)
		);
	} else {
		$like = '%' . $wpdb->esc_like($term) . '%';
		$norm      = preg_replace('/[\s\.\-_:]/', '', $term);
		$norm_like = '%' . $wpdb->esc_like($norm) . '%';

		$title_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$post_tbl}
				 WHERE post_type=%s AND post_status<>'trash'
				   AND post_title LIKE %s
				 ORDER BY post_title ASC
				 LIMIT %d",
				$post_type, $like, $limit
			)
		);

		$meta_keys = ['cmx_artikel_sku', '_cmx_artikel_sku', '_cmx_artikel_nr', '_sku'];
		$in_keys   = implode(',', array_fill(0, count($meta_keys), '%s'));

		$meta_sql  = $wpdb->prepare(
			"SELECT p.ID
			   FROM {$post_tbl} p
			   JOIN {$meta_tbl} m ON m.post_id = p.ID
			  WHERE p.post_type=%s
			    AND p.post_status<>'trash'
			    AND m.meta_key IN ($in_keys)
			    AND (
					m.meta_value LIKE %s
					OR REPLACE(REPLACE(REPLACE(REPLACE(m.meta_value,' ',''),'-',''),'.',''),':','') LIKE %s
				)
			  GROUP BY p.ID
			  ORDER BY MAX(p.post_title) ASC
			  LIMIT %d",
			array_merge([$post_type], $meta_keys, [$like, $norm_like, $limit])
		);
		$meta_ids = $wpdb->get_col($meta_sql);

		$ids = array_slice(array_values(array_unique(array_merge($title_ids, $meta_ids))), 0, $limit);
	}

	$items = [];
	foreach ($ids as $id) {
		$title = (string) get_the_title($id);
		$nr    = cmx_get_artikel_nr($id);

		$items[] = [
			'label' => trim(($nr !== '' ? $nr . ' – ' : '') . $title),
			'value' => (int) $id,
			'nr'    => $nr,
			'title' => $title,
		];
	}

	wp_send_json($items);
});

/* ------------------------------
 * AJAX: Belege-Textbausteine-Suche (Taxonomie)
 * ------------------------------ */
add_action('wp_ajax_cmx_search_beleg_textbausteine', function() {
	if (!current_user_can('edit_posts')) wp_send_json_error(['msg' => 'forbidden'], 403);

	$term = isset($_GET['term']) ? \sanitize_text_field(\wp_unslash($_GET['term'])) : '';
	$items = \function_exists(__NAMESPACE__ . '\\cmx_beleg_textbaustein_items')
		? cmx_beleg_textbaustein_items($term, 30)
		: [];
	wp_send_json($items);
});

/* ------------------------------
 * JS – Suggest + Berechnung (inkl. negative Beträge & Gesamtsumme)
 * ------------------------------ */
function cmx_beleg_positionen_js() {
	$ajax_url = admin_url('admin-ajax.php');
	?>
	<script>
	jQuery(function($){
		const table   = $('#cmx-positionen-table tbody');
		const AJAX_URL = <?php echo wp_json_encode($ajax_url); ?>;
		let dragMode = 'row';

		// ROBUSTER Parser: akzeptiert 1'234.56, 1'234,56, 1234,56, 1234.56
		function parseNumberFlexible(val){
			if(typeof val!=='string') val=(val??'').toString();
			let s = val.replace(/\s+/g,'').replace(/'/g,'');
			const hasComma = s.indexOf(',')>-1, hasDot = s.indexOf('.')>-1;
			if(hasComma && hasDot){
				if(s.lastIndexOf(',') > s.lastIndexOf('.')){
					// Komma ist Dezimal → Punkte sind Tausender
					s = s.replace(/\./g,'').replace(/,/g,'.');
				}else{
					// Punkt ist Dezimal → Kommas sind Tausender
					s = s.replace(/,/g,'');
				}
			}else{
				// Nur Komma vorhanden → als Dezimal interpretieren
				s = s.replace(/,/g,'.');
			}
			const n = parseFloat(s);
			return isNaN(n)?0:n;
		}

		function parseRabattOnSubtotal(subtotal, rabattRaw){
			if(!rabattRaw) return 0;
			const base = Math.abs(subtotal); // Rabattgrundlage = Betrag
			const txt=(rabattRaw+'').trim().toLowerCase();
			if(txt.endsWith('%')){
				const pct=parseNumberFlexible(txt.replace('%',''));
				return pct>0 ? base*(pct/100) : 0;
			}
			const betrag=parseNumberFlexible(txt.replace(/chf|fr\.?/g,''));
			return betrag>0?betrag:0;
		}

		function roundTo5Rp(amount){ return Math.round((amount + Number.EPSILON) * 20) / 20; }
		function formatSwiss(n){
			const parts = (Number(n) || 0).toFixed(2).split('.');
			let left = parts[0];
			let out = '';
			while (left.length > 3) {
				out = "'" + left.slice(-3) + out;
				left = left.slice(0, -3);
			}
			return left + out + '.' + parts[1];
		}
		function formatRabattValue(raw){
			let txt = (raw ?? '').toString().trim();
			if (!txt) return '';
			const isPercent = txt.endsWith('%');
			if (isPercent) txt = txt.slice(0, -1);
			txt = txt.replace(/chf|fr\.?/gi, '').trim();
			if (txt === '') return '';
			return formatSwiss(parseNumberFlexible(txt)) + (isPercent ? '%' : '');
		}
			function nextRowIndex(){
				let max = -1;
				table.find('input[name^="cmx_positionen["], textarea[name^="cmx_positionen["]').each(function(){
					const m = ((this.name || '') + '').match(/^cmx_positionen\[(\d+)\]/);
				if (!m) return;
				const idx = parseInt(m[1], 10);
				if (!isNaN(idx) && idx > max) max = idx;
				});
				return max + 1;
			}

			function initSortable(){
				if (!$.fn.sortable || !table.length) return;
				if (table.data('ui-sortable')) {
					table.sortable('destroy');
				}
				table.sortable({
					items: '> tr.cmx-pos-row',
					handle: '.cmx-pos-drag-handle, .cmx-section-drag-handle',
					cancel: 'input, textarea, a, button:not(.cmx-section-drag-handle)',
					placeholder: 'cmx-pos-sort-placeholder',
					forcePlaceholderSize: true,
					tolerance: 'pointer',
					helper: function(e, tr){
						const $originals = tr.children();
						const $helper = tr.clone();
						$helper.children().each(function(index){
							$(this).width($originals.eq(index).outerWidth());
						});
						return $helper;
					},
					start: function(e, ui){
						const colCount = $('#cmx-positionen-table thead th').length || 7;
						ui.placeholder
							.empty()
							.append('<td class="cmx-pos-sort-placeholder-cell" colspan="' + colCount + '"></td>');

						const moveWholeSection = dragMode === 'section' && ui.item.hasClass('cmx-pos-row-abschnitt');
						ui.item.data('cmx-move-whole-section', moveWholeSection ? 1 : 0);
						if (moveWholeSection) {
							const $followers = ui.item.nextUntil('tr.cmx-pos-row-abschnitt', 'tr.cmx-pos-row');
							const followersCount = $followers.length;
							ui.item.data('cmx-section-followers', $followers.detach());
							table.addClass('cmx-section-drag-active');
							ui.helper.addClass('cmx-sorting-section');
							ui.placeholder.addClass('cmx-pos-sort-placeholder-section');
							ui.placeholder.find('.cmx-pos-sort-placeholder-cell').html(
								'<span class="cmx-pos-sort-placeholder-label">Abschnitt verschieben' +
								(followersCount > 0 ? ' (' + (followersCount + 1) + ' Zeilen)' : '') +
								'</span>'
							);
							ui.placeholder.height(ui.helper.outerHeight());
							return;
						}
						ui.placeholder.removeClass('cmx-pos-sort-placeholder-section');
						ui.placeholder.find('.cmx-pos-sort-placeholder-cell')
							.html('<span class="cmx-pos-sort-placeholder-label">Position verschieben</span>');
						ui.placeholder.height(ui.helper.outerHeight());
					},
					sort: function(e, ui){
						if (!ui.item.data('cmx-move-whole-section')) return;
						ui.placeholder.height(ui.helper.outerHeight());
					},
					stop: function(e, ui){
						if (ui.item.data('cmx-move-whole-section')) {
							const $followers = ui.item.data('cmx-section-followers');
							if ($followers && $followers.length) {
								ui.item.after($followers);
							}
						}
						table.removeClass('cmx-section-drag-active');
						ui.item.removeClass('cmx-sorting-section');
						ui.item.removeData('cmx-move-whole-section');
						ui.item.removeData('cmx-section-followers');
						table.children('tr.ui-sortable-placeholder, tr.cmx-pos-sort-placeholder').remove();
						dragMode = 'row';
						table.trigger('cmx_positionen_rows_changed');
					}
				});
			}

			function refreshSortable(){
				if ($.fn.sortable && table.data('ui-sortable')) {
					table.sortable('refresh');
				}
			}

			table.on('mousedown touchstart', '.cmx-pos-drag-handle', function(){
				dragMode = 'row';
			});
			table.on('mousedown touchstart', '.cmx-section-drag-handle', function(){
				dragMode = 'section';
			});
			table.on('pointerdown', '.cmx-pos-drag-handle, .cmx-section-drag-handle', function(e){
				// Global help modal listens on document pointerdown (long-press).
				// Ignore drag handles so no help popup appears during sorting.
				e.stopPropagation();
			});

		function recalcRowTotal($row){
			const menge=parseNumberFlexible($row.find('input[name*="[menge]"]').val());
			const preis=parseNumberFlexible($row.find('input[name*="[preis]"]').val());
			const rabattRaw=$row.find('input[name*="[rabatt]"]').val();

			let subtotal=menge*preis;
			let rabatt=parseRabattOnSubtotal(subtotal, rabattRaw);

			const cap = Math.abs(subtotal);
			if(rabatt>cap) rabatt=cap;

			const signedRabatt = Math.sign(subtotal) * rabatt;
			const total = roundTo5Rp(subtotal - signedRabatt);

			$row.find('.cmx-pos-total').text(formatSwiss(total));
			return total;
		}

		function recalcAll(){
			let sum=0;
			table.find('tr').each(function(){ sum += recalcRowTotal($(this)); });
			// Optional: Boxen im UI aktualisieren
			$('#cmx-gesamtsumme, .cmx-gesamtbox .sum').text(formatSwiss(sum));
			$(document).trigger('cmx_total_updated', [sum]);
		}

		/* ========= Suggest (unverändert) ========= */
		function makeNavigator(inputEl, listEl, chooseCb){
			let active=-1, items=[];
			function esc(s){ return (s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
			function render(arr){
				items = Array.isArray(arr) ? arr : [];
				if(!items.length){ listEl.style.display='none'; listEl.innerHTML=''; active=-1; return; }
				listEl.innerHTML = items.map((it,i)=>(
					`<li data-index="${i}">
						<div class="cmx-ac-row">
							<span class="cmx-ac-nr">${esc(it.nr||'')}</span>
							<span class="cmx-ac-title">${esc(it.title||'')}</span>
						</div>
					</li>`
				)).join('');
				listEl.style.display='block'; active=-1;
			}
			function move(d){
				if(!items.length) return;
				active = (active + d + items.length) % items.length;
				[...listEl.children].forEach((li,i)=>li.classList.toggle('active', i===active));
			}
			function choose(i){
				if(i<0||i>=items.length) return;
				chooseCb(items[i]);
				listEl.style.display='none'; listEl.innerHTML=''; active=-1;
			}
			listEl.addEventListener('mousedown', e=>{
				const li=e.target.closest('li'); if(!li) return;
				e.preventDefault();
				choose(parseInt(li.dataset.index,10));
			});
			inputEl.addEventListener('keydown', e=>{
				if(e.key==='ArrowDown'){
					if(listEl.style.display!=='block'){
						e.preventDefault();
						inputEl.dispatchEvent(new Event('focus'));
						setTimeout(()=>{ move(1); }, 0);
					}else{
						e.preventDefault(); move(1);
					}
				}else if(e.key==='ArrowUp'){
					if(listEl.style.display==='block'){ e.preventDefault(); move(-1); }
				}else if(e.key==='Enter' || e.key==='Tab'){
					if(listEl.style.display==='block'){
						const idx = active>-1 ? active : 0;
						e.preventDefault(); choose(idx);
					}
				}else if(e.key==='Escape'){
					if(listEl.style.display==='block'){ e.preventDefault(); listEl.style.display='none'; listEl.innerHTML=''; active=-1; }
				}
			});
			document.addEventListener('click', e=>{
				if(!listEl.contains(e.target) && e.target!==inputEl){
					listEl.style.display='none'; listEl.innerHTML=''; active=-1;
				}
			});
			return { render, reset:()=>{ items=[]; active=-1; } };
		}

			function fetchArtikel(term, cb){
				$.getJSON(<?php echo wp_json_encode($ajax_url); ?>, { action:'cmx_search_artikel', term: term||'' }, function(data){
					const rows = Array.isArray(data) ? data.map(it=>({ id: it.value, title: it.title||'', nr: it.nr||'' })) : [];
					cb(rows);
				});
			}

			function fetchTextbausteine(term, cb){
				$.getJSON(<?php echo wp_json_encode($ajax_url); ?>, { action:'cmx_search_beleg_textbausteine', term: term||'' }, function(data){
					const rows = Array.isArray(data)
						? data.map(it=>({
							id: it.value || 0,
							nr: it.nr || '',
							title: it.title || '',
							text: it.text || ''
						}))
						: [];
					cb(rows);
				});
			}

			function initArtikelSuggest($ctx){
			$ctx.find('.cmx-artikel-autocomplete').each(function(){
				const $input = $(this);
				try{ if($.ui && $.ui.autocomplete && $input.data('ui-autocomplete')) $input.autocomplete('destroy'); }catch(e){}
				$input.off('.autocomplete');
				if($input.data('cmx-suggest-ready')) return;

				const $cell = $input.closest('td');
				if($cell.css('position')==='static'){ $cell.css('position','relative'); }
				const $ul = $('<ul class="cmx-art-suggest" style="display:none"></ul>');
				$input.after($ul);

				const nav = makeNavigator($input[0], $ul[0], chooseItem);
				let t=null;

				function chooseItem(it){
					const $row = $input.closest('tr');
					$row.find('.cmx-artikel-id').val(it.id||0);
					$input.val((it.nr?it.nr+' – ':'') + (it.title||''));
					const $edit = $row.find('.cmx-artikel-edit');
					const $qty = $row.find('input[name*="[menge]"]').first();
					if (it.id) {
						$edit.attr('href', <?php echo wp_json_encode(admin_url('post.php?post=')); ?> + it.id + '&action=edit');
						$edit.css({ 'pointer-events':'auto', 'opacity':'1' });
					} else {
						$edit.removeAttr('href');
						$edit.css({ 'pointer-events':'none', 'opacity':'0.35' });
					}
					if ($qty.length) {
						const qtyRaw = ($qty.val() ?? '').toString().trim();
						if (qtyRaw === '') {
							$qty.val(formatSwiss(1)).trigger('input');
						}
					}
					if(it.id){
						$.post(<?php echo wp_json_encode($ajax_url); ?>, { action:'cmx_get_artikel_vk', artikel_id: it.id }, function(resp){
							if(resp && resp.success && resp.data && resp.data.vk!==undefined){
								$row.find('input[name*="[preis]"]').val(formatSwiss(parseNumberFlexible(resp.data.vk))).trigger('input');
							}
						}, 'json');
					}
					setTimeout(function(){
						$qty.focus().select();
					}, 0);
				}
				function doSearch(q){ fetchArtikel(q, (rows)=>{ nav.render(rows); }); }

				$input.on('input', function(){
					if(t) clearTimeout(t);
					const q = $input.val().trim();
					if(q.length<1){ doSearch(''); return; }
					t = setTimeout(()=>doSearch(q), 120);
				});
				$input.on('focus click', function(){ doSearch($input.val()); });

					$input.data('cmx-suggest-ready', true);
				});
			}

				function initTextbausteinSuggest($ctx){
					$ctx.find('textarea[name*="[beschreibung]"], input[name*="[abschnitt_titel]"]').each(function(){
						const $input = $(this);
						if($input.data('cmx-text-suggest-ready')) return;
						const isAbschnittTitel = $input.is('input[name*="[abschnitt_titel]"]');

						const $cell = $input.closest('td');
						if($cell.css('position')==='static'){ $cell.css('position','relative'); }
						const $ul = $('<ul class="cmx-art-suggest cmx-text-suggest" style="display:none"></ul>');
						$input.after($ul);

						const nav = makeNavigator($input[0], $ul[0], chooseItem);
						let t = null;
						let querySeq = 0;

						function closeList(){
							$ul.hide().empty();
							if (nav && typeof nav.reset === 'function') nav.reset();
						}

						function chooseItem(it){
							if (t) {
								clearTimeout(t);
								t = null;
							}
							// Invalidate pending AJAX callbacks from previous keystrokes.
							querySeq++;
							if (isAbschnittTitel) {
								const titel = (it.nr || it.label || '').toString().trim();
								const beschreibung = (it.title || it.text || '').toString().trim();
								if (titel !== '') {
									$input.val(titel).trigger('change');
								}
								const $row = $input.closest('tr');
								const $abschnittText = $row.find('textarea[name*="[abschnitt_text]"]').first();
								if ($abschnittText.length && beschreibung !== '') {
									$abschnittText.val(beschreibung).trigger('change');
								}
								$input.data('cmx-text-selected', 1);
							} else {
								const txt = (it.text || it.title || it.nr || '').toString().trim();
								if (txt !== '') {
									$input.val(txt).trigger('change');
								}
							}
							$input.data('cmx-text-suppress-open', 1);
							closeList();
							setTimeout(function(){
								$input.focus();
							}, 0);
						}

					function doSearch(q){
						const reqSeq = ++querySeq;
						fetchTextbausteine(q, function(rows){
							if (reqSeq !== querySeq) return;
							nav.render(rows);
						});
					}

						$input.on('input', function(){
							if(t) clearTimeout(t);
							const q = ($input.val() || '').toString().trim();
							if (isAbschnittTitel) {
								if (q.length < 1) {
									$input.removeData('cmx-text-selected');
									doSearch('');
									return;
								}
								if ($input.data('cmx-text-selected')) {
									closeList();
									return;
								}
							}
							if(q.length < 1){ doSearch(''); return; }
							t = setTimeout(()=>doSearch(q), 120);
						});
							$input.on('focus click', function(){
								if ($input.data('cmx-text-suppress-open')) {
									$input.removeData('cmx-text-suppress-open');
									closeList();
									return;
							}
							if (isAbschnittTitel) {
								const q = ($input.val() || '').toString().trim();
								if (q !== '') {
									closeList();
									return;
								}
								}
								doSearch('');
							});
							$input.on('blur', function(){
								setTimeout(function(){ closeList(); }, 120);
							});

						$input.data('cmx-text-suggest-ready', true);
					});
				}

		/* ========= Eingabe-Events ========= */
		const selectorMRP = 'input[name*="[menge]"], input[name*="[preis]"], input[name*="[rabatt]"]';
		table.on('focus', selectorMRP, function(){ const el=this; setTimeout(()=>{ try{ el.select(); }catch(e){} }, 0); });
		table.on('mouseup', selectorMRP, function(e){ e.preventDefault(); });

			initArtikelSuggest(table);
			initTextbausteinSuggest(table);
			initSortable();

			table.on('input change', selectorMRP, function(){ recalcAll(); });
		table.on('blur', 'input[name*="[menge]"], input[name*="[preis]"]', function(){
			const raw = ($(this).val() ?? '').toString().trim();
			if (raw === '') return;
			const num = parseNumberFlexible(raw);
			$(this).val(formatSwiss(num));
			recalcAll();
		});
		table.on('blur', 'input[name*="[rabatt]"]', function(){
			const raw = ($(this).val() ?? '').toString().trim();
			if (raw === '') return;
			$(this).val(formatRabattValue(raw));
			recalcAll();
		});

				table.on('cmx_positionen_rows_changed', function(){
					initArtikelSuggest(table);
					initTextbausteinSuggest(table);
					refreshSortable();
					recalcAll();
				});

			// Neue Zeile
			$('#cmx-add-pos').on('click', function(){
			let i = nextRowIndex();
			let $template = table.find('tr.cmx-pos-row:not(.cmx-pos-row-abschnitt):first');
			if (!$template.length) {
				$template = table.find('tr:first');
			}
			let newRow=$template.clone();

				newRow.find('input, textarea').each(function(){
					let $el=$(this), name=$el.attr('name');
					if(name) $el.attr('name', name.replace(/\[\d+\]/,'['+i+']'));
					if($el.hasClass('cmx-artikel-id')){ $el.val(''); }
					else if($el.hasClass('cmx-artikel-autocomplete')){ $el.val('').removeData('cmx-suggest-ready'); }
				else if($el.is('[name*="[menge]"]')){ $el.val(''); }
				else if($el.is('[name*="[preis]"]')){ $el.val(''); }
				else if($el.is('[name*="[rabatt]"]')){ $el.val(''); }
					else if($el.is('textarea')){ $el.val('').removeData('cmx-text-suggest-ready'); } else { $el.val(''); }
				});
				newRow.find('.cmx-art-suggest').remove();
				newRow.find('.cmx-pos-total').text('0,00');

				table.append(newRow);
				initArtikelSuggest(newRow);
				initTextbausteinSuggest(newRow);
				table.trigger('cmx_positionen_rows_changed');

				setTimeout(()=>{ newRow.find('.cmx-artikel-autocomplete').trigger('focus'); }, 0);
			});

		// Entfernen
		table.on('click','.cmx-del-pos',function(){
			const $row = $(this).closest('tr');
			if (table.find('tr').length > 1) {
				$row.remove();
			} else {
				// Letzte Zeile: Inhalte leeren, damit kein zusätzlicher Platzhalter nötig ist
				$row.find('input, textarea').each(function(){
					const $el = $(this);
					if ($el.hasClass('cmx-artikel-id')) { $el.val(''); }
					else if ($el.hasClass('cmx-artikel-autocomplete')) { $el.val(''); }
					else { $el.val(''); }
				});
				$row.find('.cmx-pos-total').text('0,00');
			}
				table.trigger('cmx_positionen_rows_changed');
			});

		// Initial
		recalcAll();
	});
	</script>
	<style>
		.cmx-art-suggest{ position:absolute; z-index:1000; left:0; right:0; max-height:280px; overflow:auto; margin:2px 0 0; padding:0; border:1px solid #ccd0d4; background:#fff; list-style:none; }
		.cmx-art-suggest li{ margin:0; padding:0; cursor:pointer; }
		.cmx-art-suggest li.active, .cmx-art-suggest li:hover{ background:#e5f3ff; }
			.cmx-ac-row{ display:grid; grid-template-columns: 140px 1fr; gap:8px; align-items:center; padding:6px 8px; }
			.cmx-ac-nr{ font-weight:600; white-space:nowrap; }
			.cmx-ac-title{ overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

			#cmx-positionen-table th, #cmx-positionen-table td { vertical-align: middle; }
				#cmx-positionen-table th:first-child,
				#cmx-positionen-table td:first-child{ padding-right:20px; }
				#cmx-positionen-table td textarea { resize: vertical; }
				#cmx-positionen-table td.cmx-pos-beschr-cell{
					position:relative;
					padding-left:26px;
				}
				#cmx-positionen-table .cmx-textbaustein-edit{
					position:absolute;
					left:6px;
					top:8px;
					text-decoration:none;
					font-size:12px;
					color:#2271b1;
					line-height:1;
				}
				#cmx-positionen-table .cmx-textbaustein-edit:hover{
					color:#135e96;
				}
				.cmx-pos-row td:first-child{ position:relative; padding-right:8px; }
			.cmx-pos-total{ font-weight:600; text-align:right; }
			#cmx-positionen-table td.cmx-pos-controls{
				white-space:nowrap;
				width:1%;
				text-align:right;
				vertical-align:top;
				padding-left:4px;
				padding-right:6px;
			}
			#cmx-positionen-table .cmx-pos-controls .cmx-del-pos,
			#cmx-positionen-table .cmx-pos-controls .cmx-pos-drag-handle,
			#cmx-positionen-table .cmx-pos-controls .cmx-section-drag-handle{
				vertical-align:top;
			}
			#cmx-positionen-table .cmx-pos-controls .cmx-del-pos{ margin-left:4px; }
			#cmx-positionen-table .cmx-pos-drag-handle{
				cursor:move;
				display:inline-flex;
				align-items:center;
				justify-content:center;
				width:20px;
				height:20px;
				margin-right:4px;
				color:#646970;
				border-radius:3px;
				user-select:none;
				font-weight:600;
			}
			#cmx-positionen-table .cmx-section-drag-handle{
				cursor:move;
				margin-right:4px;
				display:inline-flex;
				align-items:center;
				justify-content:center;
				width:20px;
				min-width:20px;
				height:20px;
				min-height:20px;
				line-height:1;
				padding:0;
				font-weight:600;
			}
			#cmx-positionen-table .cmx-pos-drag-handle:hover{ background:#f0f0f1; color:#1d2327; }
			#cmx-positionen-table tr.ui-sortable-helper td{
				background:#fff;
				box-shadow: inset 0 0 0 1px #c3c4c7;
			}
			#cmx-positionen-table tr.ui-sortable-helper.cmx-sorting-section td{
				background:#eef6ff;
				box-shadow: inset 0 0 0 1px #72aee6;
			}
			#cmx-positionen-table tr.cmx-pos-sort-placeholder td.cmx-pos-sort-placeholder-cell{
				background:#f6f7f7 !important;
				border:1px dashed #8c8f94;
				height:34px;
				padding:4px 8px;
			}
			#cmx-positionen-table tr.cmx-pos-sort-placeholder-section td.cmx-pos-sort-placeholder-cell{
				background:#eaf4ff !important;
				border-color:#72aee6;
			}
			#cmx-positionen-table .cmx-pos-sort-placeholder-label{
				display:inline-block;
				font-size:11px;
				font-weight:600;
				color:#1d2327;
			}
			#cmx-positionen-table.cmx-section-drag-active tr.cmx-pos-row-abschnitt td{
				background:#f0f6fc;
			}
			.cmx-artikel-edit{
				position:absolute;
			left:6px;
			top:50%;
			transform:translateY(-50%);
			text-decoration:none;
			font-size:12px;
			color:#2271b1;
			padding-right:6px;
		}
		.cmx-pos-row td:first-child .cmx-artikel-autocomplete{
			padding-left:8px;
			margin-left:22px;
			width: calc(100% - 22px);
		}
	</style>
	<?php
}

/* ------------------------------
 * AJAX: Positionen nach Sortierung speichern (gehärtet)
 * ------------------------------ */
add_action('wp_ajax_cmx_save_beleg_positionen_order', function() {

	if (!current_user_can('edit_posts')) wp_send_json_error(['msg'=>'forbidden'],403);

	$post_id = (int)($_POST['post_id'] ?? 0);
	if ($post_id <= 0) wp_send_json_error(['msg'=>'no_post_id'],400);

	$rows = $_POST['rows'] ?? [];
	if (!is_array($rows)) wp_send_json_error(['msg'=>'invalid_rows'],400);

	$max_rows = 500;
	if (count($rows) > $max_rows) $rows = array_slice($rows, 0, $max_rows);

	$clean = [];
	foreach ($rows as $r) {
		if (!\is_array($r)) continue;
		$custom = \apply_filters('cmx_beleg_positionen_clean_custom_row', null, $r, (int) $post_id);
		if (\is_array($custom)) {
			$clean[] = $custom;
			continue;
		}
		if ($custom === false) {
			continue;
		}
		$artikel_id   = isset($r['artikel_id']) ? (int)$r['artikel_id'] : 0;

		$menge        = (float)\CLOUDMEISTER\CMX\Buero\cmx_norm_decimal((string)($r['menge'] ?? ''));
		$preis        = (float)\CLOUDMEISTER\CMX\Buero\cmx_norm_decimal((string)($r['preis'] ?? ''));

		$rabatt_raw   = isset($r['rabatt']) ? (string)$r['rabatt'] : '';
		$rabatt       = sanitize_text_field($rabatt_raw);

		$beschreibung_raw = isset($r['beschreibung']) ? (string)$r['beschreibung'] : '';
		$beschreibung_raw = wp_unslash($beschreibung_raw);
		$beschreibung_raw = str_replace(["\r\n", "\r"], "\n", $beschreibung_raw);
		$beschreibung = trim($beschreibung_raw);

		// negative Mengen zulassen; nur 0 verwerfen
		if ($artikel_id <= 0 || $menge == 0.0) continue;
		if (strlen($beschreibung) > 10000) $beschreibung = substr($beschreibung, 0, 10000);

		$clean[] = [
			'artikel_id'   => $artikel_id,
			'menge'        => $menge,
			'preis'        => $preis,
			'rabatt'       => $rabatt,
			'beschreibung' => $beschreibung,
		];
	}

	$old = get_post_meta($post_id, '_cmx_beleg_positionen', true);
	if ($old !== $clean) update_post_meta($post_id, '_cmx_beleg_positionen', $clean);

	wp_send_json_success(['saved'=>count($clean)]);
});
