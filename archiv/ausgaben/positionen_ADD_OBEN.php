<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;


/* =========================================================
 * Metabox registrieren
 * ========================================================= */
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

/* =========================================================
 * Metabox: Render
 * ========================================================= */
function cmx_render_beleg_positionen(\WP_Post $post) {
	wp_nonce_field('cmx_save_beleg_positionen', 'cmx_beleg_positionen_nonce');

	$positionen = get_post_meta($post->ID, '_cmx_beleg_positionen', true);
	$positionen = $positionen ? json_decode($positionen, true) : [];

	// Nonce für AJAX
	$ajax_nonce = wp_create_nonce('cmx_artikel_vk_nonce');

	echo '<div id="cmx-positionen-wrap" class="cmx-positionen">';

	// Steuerleiste
	echo '<p><button type="button" class="button button-secondary" id="cmx-add-pos">+ Position hinzufügen</button></p>';

	// Tabelle
	echo '<table class="widefat striped" id="cmx-positionen-table">
		<thead>
			<tr>
				<th style="width:28px;"></th>
				<th>Artikel</th>
				<th style="width:90px;">Menge</th>
				<th style="width:140px;">Einzelpreis (CHF)</th>
				<th style="width:120px;">Gesamt</th>
				<th>Beschreibung</th>
				<th style="width:28px;"></th>
			</tr>
		</thead>
		<tbody>';

	if (!empty($positionen)) {
		foreach ($positionen as $i => $pos) {
			cmx_render_position_row($i, $pos);
		}
	} else {
		cmx_render_position_row(0, []);
	}

	echo '</tbody></table>';
	echo '</div>';

	// Inline Assets (JS/CSS) – inkl. AJAX + Sortable
	cmx_beleg_positionen_js_css($ajax_nonce);
}

/* =========================================================
 * Einzelzeile rendern
 * ========================================================= */
function cmx_render_position_row($i, $pos) {
	$artikel_id   = (int)($pos['artikel_id'] ?? 0);
	$menge        = isset($pos['menge']) ? (float)$pos['menge'] : 1;
	$preis        = isset($pos['preis']) ? (float)$pos['preis'] : 0.0;
	$beschreibung = $pos['beschreibung'] ?? '';

	// Falls Preis leer/0, versuche VK vom Artikel zu laden (nur zur Anzeige)
	if ($artikel_id && ($preis <= 0)) {
		$vk = get_post_meta($artikel_id, '_cmx_artikel_vk', true);
		if ($vk !== '') $preis = (float)$vk;
	}

	$ges = (float)$menge * (float)$preis;
	$ges_fmt   = number_format($ges, 2, '.', "'");   // 1'234.56
	$preis_fmt = ($preis > 0) ? number_format($preis, 2, '.', "'") : '';

	echo '<tr class="cmx-pos-row" data-index="'.esc_attr($i).'">';

	// Drag Handle
	echo '<td class="cmx-handle" title="Ziehen zum Sortieren" style="cursor:move;">≡</td>';

	// Artikel Select
	echo '<td>';
	echo '<select name="cmx_positionen['.$i.'][artikel_id]" class="cmx-artikel-select" data-idx="'.$i.'">';
	echo '<option value="">— Artikel wählen —</option>';

	$artikel_query = new \WP_Query([
		'post_type'      => 'artikel',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	]);
	foreach ($artikel_query->posts as $id) {
		$title = get_the_title($id);
		printf('<option value="%d"%s>%s</option>', $id, selected($artikel_id, $id, false), esc_html($title));
	}
	wp_reset_postdata();

	echo '</select>';
	echo '</td>';

	// Menge
	echo '<td><input type="number" min="1" step="1" name="cmx_positionen['.$i.'][menge]" value="'.esc_attr($menge).'" class="cmx-menge" style="width:90px"></td>';

	// Preis (auto VK)
	echo '<td><input type="text" name="cmx_positionen['.$i.'][preis]" value="'.esc_attr($preis_fmt).'" class="cmx-preis" placeholder="0.00" style="width:120px"></td>';

	// Gesamt
	echo '<td class="cmx-pos-total" style="text-align:right;"><span>'.$ges_fmt.'</span> CHF</td>';

	// Beschreibung
	echo '<td><textarea name="cmx_positionen['.$i.'][beschreibung]" rows="1" class="cmx-beschr" style="width:100%;">'.esc_textarea($beschreibung).'</textarea></td>';

	// Löschen
	echo '<td><button type="button" class="button-link-delete cmx-del-pos" title="Zeile entfernen">✕</button></td>';

	echo '</tr>';
}

/* =========================================================
 * Speichern
 * ========================================================= */
add_action('save_post_belege', function($post_id) {
	if (!isset($_POST['cmx_beleg_positionen_nonce']) || !wp_verify_nonce($_POST['cmx_beleg_positionen_nonce'], 'cmx_save_beleg_positionen')) return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

	$raw = $_POST['cmx_positionen'] ?? [];
	if (!is_array($raw)) return;

	$positionen = [];
	foreach ($raw as $row) {
		$artikel_id   = isset($row['artikel_id']) ? (int)$row['artikel_id'] : 0;
		$menge        = isset($row['menge']) ? (float)$row['menge'] : 0;
		$preis        = isset($row['preis']) ? (float)str_replace(["'", ' '], ['', ''], (string)$row['preis']) : 0.0;
		$beschreibung = isset($row['beschreibung']) ? wp_kses_post($row['beschreibung']) : '';

		// Nur sinnvolle Zeilen speichern
		if ($artikel_id > 0 && $menge > 0) {
			$positionen[] = [
				'artikel_id'   => $artikel_id,
				'menge'        => $menge,
				'preis'        => $preis,
				'beschreibung' => $beschreibung,
			];
		}
	}

	update_post_meta($post_id, '_cmx_beleg_positionen', wp_json_encode($positionen));
}, 10, 1);

/* =========================================================
 * AJAX: VK-Preis eines Artikels holen
 * ========================================================= */
add_action('wp_ajax_cmx_get_artikel_vk', function() {
	check_ajax_referer('cmx_artikel_vk_nonce', 'nonce');

	if (!current_user_can('edit_posts')) wp_send_json_error(['msg' => 'no_cap'], 403);

	$artikel_id = isset($_POST['artikel_id']) ? (int)$_POST['artikel_id'] : 0;
	if ($artikel_id <= 0) wp_send_json_error(['msg' => 'no_id'], 400);

	$vk = get_post_meta($artikel_id, '_cmx_artikel_vk', true);
	$vk = ($vk === '' || $vk === null) ? '' : (string)$vk;

	wp_send_json_success([
		'artikel_id' => $artikel_id,
		'vk'         => $vk, // raw string; Frontend formatiert
	]);
});

/* =========================================================
 * Inline JS/CSS: Add/Remove, VK-Autofill, Recalc, Sortable
 * ========================================================= */
function cmx_beleg_positionen_js_css(string $ajax_nonce) {
	$ajax_url = admin_url('admin-ajax.php');
	?>
	<style>
		#cmx-positionen-table th, #cmx-positionen-table td { vertical-align: middle; }
		#cmx-positionen-table td textarea { resize: vertical; }
		#cmx-positionen-table .cmx-handle { font-weight:600; text-align:center; }
		#cmx-positionen-table tbody tr { background:#fff; }
		#cmx-positionen-table tbody tr.ui-sortable-helper { box-shadow:0 6px 18px rgba(0,0,0,.15); }
		#cmx-positionen-table tbody tr td { border-top:1px solid #f0f0f0; }
	</style>
	<script>
	jQuery(function($){
		const $wrap  = $('#cmx-positionen-wrap');
		const $tbody = $('#cmx-positionen-table tbody');
		const AJAX   = {
			url: <?php echo wp_json_encode($ajax_url); ?>,
			nonce: <?php echo wp_json_encode($ajax_nonce); ?>
		};

		// --- Helpers ---
		function fmt2(n){ n = parseFloat(n||0); return n.toLocaleString('en-CH', {minimumFractionDigits:2, maximumFractionDigits:2}); }
		function recalcRow($tr){
			let menge = parseFloat(($tr.find('.cmx-menge').val()||'').toString().replace("'","")) || 0;
			let preis = parseFloat(($tr.find('.cmx-preis').val()||'').toString().replace("'","")) || 0;
			$tr.find('.cmx-pos-total span').text( fmt2(menge*preis) );
		}
		function reindex(){
			$tbody.find('tr').each(function(i){
				$(this).attr('data-index', i);
				$(this).find('input,select,textarea').each(function(){
					let name = $(this).attr('name');
					name = name.replace(/\[\d+\]/, '['+i+']');
					$(this).attr('name', name);
				});
			});
		}

		// --- Add Row ---
		$('#cmx-add-pos').on('click', function(){
			let $first = $tbody.find('tr:first');
			let $new   = $first.clone();
			$new.find('input,select,textarea').each(function(){
				let $el=$(this);
				let nm=$el.attr('name');
				nm = nm.replace(/\[\d+\]/, '['+ $tbody.find('tr').length +']');
				$el.attr('name', nm);
				if ($el.is('select')) $el.val('');
				else $el.val('');
			});
			$new.find('.cmx-pos-total span').text('0.00');
			$tbody.append($new);
		});

		// --- Delete Row ---
		$tbody.on('click', '.cmx-del-pos', function(){
			if ($tbody.find('tr').length > 1) {
				$(this).closest('tr').remove();
				reindex();
			}
		});

		// --- Recalc on input ---
		$tbody.on('input change', '.cmx-menge, .cmx-preis', function(){
			recalcRow($(this).closest('tr'));
		});

		// --- VK autofill on article change ---
		$tbody.on('change', '.cmx-artikel-select', function(){
			const $tr = $(this).closest('tr');
			const id  = parseInt($(this).val() || '0', 10);
			if (!id) { return; }

			$.ajax({
				method: 'POST',
				url: AJAX.url,
				dataType: 'json',
				data: {
					action: 'cmx_get_artikel_vk',
					nonce: AJAX.nonce,
					artikel_id: id
				}
			}).done(function(resp){
				if (resp && resp.success) {
					let vk = resp.data.vk;
					if (vk !== '') {
						$tr.find('.cmx-preis').val( fmt2(vk) );
						recalcRow($tr);
					}
				}
			});
		});

		// --- Sortable (jQuery UI) ---
		try {
			$tbody.sortable({
				handle: '.cmx-handle',
				helper: function(e,tr){
					var $originals = tr.children();
					var $helper = tr.clone();
					$helper.children().each(function(index){
						$(this).width($originals.eq(index).width());
					});
					return $helper;
				},
				stop: function(){ reindex(); }
			}).disableSelection();
		} catch(e){ console.warn('Sortable nicht verfügbar:', e); }

		// Initial Recalc
		$tbody.find('tr').each(function(){ recalcRow($(this)); });
	});
	</script>
	<?php
}
