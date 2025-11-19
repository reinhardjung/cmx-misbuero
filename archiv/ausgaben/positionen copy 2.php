<?php
/**
 * File: belege-metabox-positionen.php
 * Zweck: Positionen (Artikel) zu einem Beleg hinzufügen und speichern
 */

namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit;

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
	$positionen = $positionen ? json_decode($positionen, true) : [];

	echo '<div id="cmx-positionen-wrap">';
	echo '<table class="widefat striped" id="cmx-positionen-table">
			<thead><tr>
				<th>Artikel</th>
				<th>Menge</th>
				<th>Einzelpreis (CHF)</th>
				<th>Gesamt</th>
				<th>Beschreibung</th>
				<th></th>
			</tr></thead>
			<tbody>';

	if (!empty($positionen)) {
		foreach ($positionen as $i => $pos) {
			cmx_render_position_row($i, $pos);
		}
	} else {
		cmx_render_position_row(0, []);
	}

	echo '</tbody></table>';
	echo '<p><button type="button" class="button button-secondary" id="cmx-add-pos">+ Position hinzufügen</button></p>';
	echo '</div>';

	// Inline JS
	cmx_beleg_positionen_js();
}

/* ------------------------------
 * Einzelzeile rendern
 * ------------------------------ */
function cmx_render_position_row($i, $pos) {
	$artikel_id   = esc_attr($pos['artikel_id'] ?? '');
	$artikel_name = esc_html(get_the_title($artikel_id) ?: ($pos['artikel_name'] ?? ''));
	$menge        = esc_attr($pos['menge'] ?? 1);
	$preis        = esc_attr($pos['preis'] ?? '');
	$beschreibung = esc_textarea($pos['beschreibung'] ?? '');

	echo '<tr class="cmx-pos-row">';
	echo '<td><select name="cmx_positionen['.$i.'][artikel_id]" class="cmx-artikel-select">';
	echo '<option value="">— Artikel wählen —</option>';

	$artikel_query = new \WP_Query([
		'post_type' => 'artikel',
		'posts_per_page' => -1,
		'post_status' => 'publish',
		'orderby' => 'title',
		'order' => 'ASC',
		'fields' => 'ids',
	]);
	foreach ($artikel_query->posts as $id) {
		$title = get_the_title($id);
		printf('<option value="%d"%s>%s</option>', $id, selected($artikel_id, $id, false), esc_html($title));
	}
	wp_reset_postdata();

	echo '</select></td>';

	echo '<td><input type="number" name="cmx_positionen['.$i.'][menge]" value="'.$menge.'" min="1" step="1" style="width:70px"></td>';
	echo '<td><input type="text" name="cmx_positionen['.$i.'][preis]" value="'.$preis.'" style="width:100px"></td>';
	echo '<td class="cmx-pos-total" style="width:90px;text-align:right;">'.number_format((float)$preis * (int)$menge, 2).' CHF</td>';
	echo '<td><textarea name="cmx_positionen['.$i.'][beschreibung]" rows="1" style="width:100%">'.$beschreibung.'</textarea></td>';
	echo '<td><button type="button" class="button-link-delete cmx-del-pos">✕</button></td>';
	echo '</tr>';
}

/* ------------------------------
 * Speichern der Positionen
 * ------------------------------ */
/* ------------------------------
 * SICHERES Speichern der Positionen (ersetzt deinen bisherigen Save-Block)
 * ------------------------------ */
add_action('save_post_belege', function($post_id, \WP_Post $post, $update) {

	// 1) Guards gegen Rekursion/Autosave/Revisionen/Fremdtypen
	if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
	if ($post->post_type !== 'belege') return;

	// 2) Rechte prüfen
	if (!current_user_can('edit_post', $post_id)) return;

	// 3) Nonce prüfen
	if (!isset($_POST['cmx_beleg_positionen_nonce']) || !wp_verify_nonce($_POST['cmx_beleg_positionen_nonce'], 'cmx_save_beleg_positionen')) return;

	// 4) Nur bei normalem Formular-Submit (nicht bei AJAX) speichern
	if (defined('DOING_AJAX') && DOING_AJAX) return;

	// 5) Eingabedaten holen
	$positionen = $_POST['cmx_positionen'] ?? [];
	if (!is_array($positionen)) return;

	// 6) HARTES Limit, um Memory-Spikes zu verhindern (z. B. 500 Zeilen)
	$max_rows = 500;
	if (count($positionen) > $max_rows) {
		$positionen = array_slice($positionen, 0, $max_rows);
	}

	// 7) Sanitisieren + normalisieren (nur primitive Strings/Zahlen speichern)
	$clean = [];
	foreach ($positionen as $row) {
		if (!is_array($row)) continue;

		$artikel_id   = isset($row['artikel_id']) ? (int)$row['artikel_id'] : 0;
		$menge        = isset($row['menge']) ? (float)$row['menge'] : 0.0;
		$preis_raw    = isset($row['preis']) ? (string)$row['preis'] : '';
		$preis        = (float)str_replace(["'", ' '], ['', ''], $preis_raw);
		$beschreibung = isset($row['beschreibung']) ? wp_kses_post($row['beschreibung']) : '';

		// Leere/defekte Zeilen überspringen
		if ($artikel_id <= 0 || $menge <= 0) continue;

		// Beschreibung auf sinnvolle Länge begrenzen (z. B. 10k)
		if (strlen($beschreibung) > 10000) {
			$beschreibung = substr($beschreibung, 0, 10000);
		}

		$clean[] = [
			'artikel_id'   => $artikel_id,
			'menge'        => $menge,
			'preis'        => $preis,
			'beschreibung' => $beschreibung,
		];
	}

	// 8) JSON speichern (nur wenn sich was geändert hat → weniger Writes)
	$new_json = wp_json_encode($clean);
	$old_json = (string)get_post_meta($post_id, '_cmx_beleg_positionen', true);

	if ($new_json !== $old_json) {
		update_post_meta($post_id, '_cmx_beleg_positionen', $new_json);
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
 * JS für dynamische Zeilen + VK + Sortierung
 * ------------------------------ */
function cmx_beleg_positionen_js() {
	$ajax_url = admin_url('admin-ajax.php');
	?>
	<script>
	jQuery(function($) {
		const table = $('#cmx-positionen-table tbody');
		let post_id = $('#post_ID').val();

		// === A) VK-Autofill ===
		table.on('change', '.cmx-artikel-select', function() {
			const row = $(this).closest('tr');
			const artikelID = $(this).val();
			if (!artikelID) return;
			$.post(<?php echo wp_json_encode($ajax_url); ?>, {
				action: 'cmx_get_artikel_vk',
				artikel_id: artikelID
			}, function(resp) {
				if (resp && resp.success && resp.data.vk) {
					row.find('input[name*="[preis]"]').val(resp.data.vk).trigger('input');
				}
			}, 'json');
		});

		// === B) Drag&Drop Sortierung + AJAX Save ===
		if (typeof $.fn.sortable === 'function') {
			table.sortable({
				axis: 'y',
				stop: function() {
					const rows = [];
					table.find('tr').each(function() {
						const r = $(this);
						rows.push({
							artikel_id: r.find('select[name*="[artikel_id]"]').val(),
							menge: r.find('input[name*="[menge]"]').val(),
							preis: r.find('input[name*="[preis]"]').val(),
							beschreibung: r.find('textarea[name*="[beschreibung]"]').val()
						});
					});
					$.post(<?php echo wp_json_encode($ajax_url); ?>, {
						action: 'cmx_save_beleg_positionen_order',
						post_id: post_id,
						rows: rows
					});
				}
			}).disableSelection();
		}

		// === C) Menge=1 Default beim Hinzufügen ===
		$('#cmx-add-pos').on('click', function() {
			let i = table.find('tr').length;
			let newRow = table.find('tr:first').clone();
			newRow.find('input, select, textarea').each(function() {
				let name = $(this).attr('name').replace(/\[\d+\]/, '['+i+']');
				$(this).attr('name', name).val('');
			});
			newRow.find('input[name*="[menge]"]').val('1');
			newRow.find('.cmx-pos-total').text('0.00 CHF');
			table.append(newRow);
		});

		// === D) Focus -> Wert markieren ===
		table.on('focus', 'input[name*="[menge]"], input[name*="[preis]"]', function() {
			let el = this;
			setTimeout(() => { el.select(); }, 0);
		});

		// === Bestehende Kalkulation ===
		table.on('click', '.cmx-del-pos', function() {
			if (table.find('tr').length > 1) $(this).closest('tr').remove();
		});

		table.on('input change', 'input[name*="[menge]"], input[name*="[preis]"]', function() {
			let row = $(this).closest('tr');
			let menge = parseFloat(row.find('input[name*="[menge]"]').val()) || 0;
			let preis = parseFloat(row.find('input[name*="[preis]"]').val()) || 0;
			row.find('.cmx-pos-total').text((menge * preis).toFixed(2) + ' CHF');
		});
	});
	</script>
	<style>
		#cmx-positionen-table th, #cmx-positionen-table td { vertical-align: middle; }
		#cmx-positionen-table td textarea { resize: vertical; }
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

	// Limit + Sanitize (analog oben)
	$max_rows = 500;
	if (count($rows) > $max_rows) {
		$rows = array_slice($rows, 0, $max_rows);
	}

	$clean = [];
	foreach ($rows as $r) {
		$artikel_id   = isset($r['artikel_id']) ? (int)$r['artikel_id'] : 0;
		$menge        = isset($r['menge']) ? (float)$r['menge'] : 0.0;
		$preis_raw    = isset($r['preis']) ? (string)$r['preis'] : '';
		$preis        = (float)str_replace(["'", ' '], ['', ''], $preis_raw);
		$beschreibung = isset($r['beschreibung']) ? wp_kses_post($r['beschreibung']) : '';

		if ($artikel_id <= 0 || $menge <= 0) continue;
		if (strlen($beschreibung) > 10000) $beschreibung = substr($beschreibung, 0, 10000);

		$clean[] = [
			'artikel_id'   => $artikel_id,
			'menge'        => $menge,
			'preis'        => $preis,
			'beschreibung' => $beschreibung,
		];
	}

	$new_json = wp_json_encode($clean);
	$old_json = (string)get_post_meta($post_id, '_cmx_beleg_positionen', true);

	if ($new_json !== $old_json) {
		update_post_meta($post_id, '_cmx_beleg_positionen', $new_json);
	}

	wp_send_json_success(['saved'=>count($clean)]);
});
