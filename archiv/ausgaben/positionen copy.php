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
		'💼 Positionen',
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
add_action('save_post_belege', function($post_id) {
	if (!isset($_POST['cmx_beleg_positionen_nonce']) || !wp_verify_nonce($_POST['cmx_beleg_positionen_nonce'], 'cmx_save_beleg_positionen')) return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

	$positionen = $_POST['cmx_positionen'] ?? [];
	if (!is_array($positionen)) return;

	update_post_meta($post_id, '_cmx_beleg_positionen', wp_json_encode($positionen));
});

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
 * AJAX: Positionen nach Sortierung speichern
 * ------------------------------ */
add_action('wp_ajax_cmx_save_beleg_positionen_order', function() {
	if (!current_user_can('edit_posts')) wp_send_json_error(['msg'=>'forbidden'],403);
	$post_id = (int)($_POST['post_id'] ?? 0);
	$rows = $_POST['rows'] ?? [];
	if (!$post_id || !is_array($rows)) wp_send_json_error(['msg'=>'invalid'],400);
	update_post_meta($post_id, '_cmx_beleg_positionen', wp_json_encode($rows));
	wp_send_json_success(['saved'=>count($rows)]);
});


// === jQuery UI Sortable nur im Beleg-Editor laden ===
add_action('admin_enqueue_scripts', function($hook){
	if ($hook !== 'post.php' && $hook !== 'post-new.php') return;
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || $screen->post_type !== 'belege') return;
	wp_enqueue_script('jquery-ui-sortable');
});

// === Inline-Sortable + AJAX-Save initialisieren (unabhängig vom bestehenden JS) ===
add_action('admin_print_footer_scripts', function () {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || $screen->post_type !== 'belege') return;
	$ajax_url = admin_url('admin-ajax.php');
	?>
	<script>
	jQuery(function($){
		const $tbody = $('#cmx-positionen-table tbody');
		if(!$tbody.length) return;

		function reindex(){
			$tbody.find('tr').each(function(i){
				$(this).find('input,select,textarea').each(function(){
					let n=$(this).attr('name'); if(!n) return;
					$(this).attr('name', n.replace(/\[\d+\]/, '['+i+']'));
				});
			});
		}
		function collectRows(){
			const out=[];
			$tbody.find('tr').each(function(){
				out.push({
					artikel_id: $(this).find('select[name*="[artikel_id]"]').val() || '',
					menge: $(this).find('input[name*="[menge]"]').val() || '',
					preis: $(this).find('input[name*="[preis]"]').val() || '',
					beschreibung: $(this).find('textarea[name*="[beschreibung]"]').val() || ''
				});
			});
			return out;
		}
		function postId(){ return parseInt($('#post_ID').val()||'0',10); }

		// Sortable aktivieren (auf <tbody>)
		if (typeof $tbody.sortable === 'function') {
			try{
				$tbody.sortable({
					items: '> tr',
					axis: 'y',
					cancel: 'a,button,input,textarea,select,option',
					helper: function(e,tr){
						var $orig = tr.children(), $help = tr.clone();
						$help.children().each(function(i){ $(this).width($orig.eq(i).width()); });
						return $help;
					},
					stop: function(){
						reindex(); // Namen an neue Reihenfolge anpassen
						const rows = collectRows(), pid = postId(); if(!pid) return;
						$.post(<?php echo wp_json_encode($ajax_url); ?>, {
							action: 'cmx_save_beleg_positionen_order',
							post_id: pid,
							rows: rows
						});
					}
				}).disableSelection();
			}catch(e){ console.warn('Sortable nicht verfügbar:', e); }
		}
	});
	</script>
	<style>
		/* Optional: visuelle Sortier-Platzhalter */
		#cmx-positionen-table tbody tr.ui-sortable-placeholder { visibility: visible !important; background: #f2f8ff; border: 2px dashed #c5d9ff; }
	</style>
	<?php
});
