<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_abschnitt_is_row')) {
	function cmx_beleg_abschnitt_is_row($row): bool {
		if (!\is_array($row)) return false;
		$typ = \sanitize_key((string) ($row['typ'] ?? ''));
		return $typ === 'abschnitt';
	}
}

add_action('cmx_beleg_positionen_after_add_button', function (): void {
	echo ' <button type="button" class="button button-secondary" id="cmx-add-abschnitt">Abschnitt hinzufügen</button>';
}, 10);

add_filter('cmx_beleg_positionen_render_custom_row', function ($handled, int $i, $pos) {
	if ($handled) return true;
	if (!cmx_beleg_abschnitt_is_row($pos)) return false;

	$titel = isset($pos['abschnitt_titel']) ? (string) $pos['abschnitt_titel'] : '';
	$titel = \trim($titel);
	$text = isset($pos['abschnitt_text']) ? (string) $pos['abschnitt_text'] : '';
	$text = \trim($text);

	echo '<tr class="cmx-pos-row cmx-pos-row-abschnitt">';
	echo '<td colspan="6">';
	echo '<input type="hidden" name="cmx_positionen[' . $i . '][typ]" value="abschnitt">';
	echo '<input type="text" class="regular-text cmx-abschnitt-titel" name="cmx_positionen[' . $i . '][abschnitt_titel]" value="' . \esc_attr($titel) . '" placeholder="Abschnitt" style="width:100%;">';
	echo '<textarea class="cmx-abschnitt-text" name="cmx_positionen[' . $i . '][abschnitt_text]" rows="2" placeholder="Beschreibender Text" style="width:100%; margin-top:6px;">' . \esc_textarea($text) . '</textarea>';
	echo '</td>';
	echo '<td><button type="button" class="button-link-delete cmx-del-pos">✕</button></td>';
	echo '</tr>';

	return true;
}, 10, 3);

add_filter('cmx_beleg_positionen_clean_custom_row', function ($custom, $row) {
	if (!\is_array($row)) return $custom;
	$typ = \sanitize_key((string) ($row['typ'] ?? ''));
	if ($typ !== 'abschnitt') return $custom;

	$titel_raw = isset($row['abschnitt_titel']) ? (string) $row['abschnitt_titel'] : '';
	$titel_raw = \wp_unslash($titel_raw);
	$titel = \trim(\preg_replace('/\s+/', ' ', $titel_raw));
	$titel = \sanitize_text_field($titel);
	$text_raw = isset($row['abschnitt_text']) ? (string) $row['abschnitt_text'] : '';
	$text_raw = \wp_unslash($text_raw);
	$text_raw = \str_replace(["\r\n", "\r"], "\n", $text_raw);
	$text = \sanitize_textarea_field($text_raw);
	$text = \trim($text);

	if ($titel === '' && $text === '') {
		// Komplett leere Abschnittszeilen nicht speichern.
		return false;
	}

	return [
		'typ'             => 'abschnitt',
		'abschnitt_titel' => $titel,
		'abschnitt_text'  => $text,
	];
}, 10, 2);

function cmx_beleg_abschnitt_admin_footer(): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || $screen->post_type !== 'belege' || $screen->base !== 'post') {
		return;
	}
	?>
	<script>
	jQuery(function($){
		const table = $('#cmx-positionen-table tbody');
		if (!table.length) return;

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

			$(document).on('click', '#cmx-add-abschnitt', function(){
				const i = nextRowIndex();
				const rowHtml = '' +
					'<tr class="cmx-pos-row cmx-pos-row-abschnitt">' +
						'<td colspan="6">' +
							'<input type="hidden" name="cmx_positionen[' + i + '][typ]" value="abschnitt">' +
							'<input type="text" class="regular-text cmx-abschnitt-titel" name="cmx_positionen[' + i + '][abschnitt_titel]" value="" placeholder="Abschnitt" style="width:100%;">' +
							'<textarea class="cmx-abschnitt-text" name="cmx_positionen[' + i + '][abschnitt_text]" rows="2" placeholder="Beschreibender Text" style="width:100%; margin-top:6px;"></textarea>' +
						'</td>' +
						'<td><button type="button" class="button-link-delete cmx-del-pos">✕</button></td>' +
					'</tr>';

			const $row = $(rowHtml);
			table.append($row);
			setTimeout(function(){
				$row.find('.cmx-abschnitt-titel').focus().select();
			}, 0);
		});
	});
	</script>
	<style>
		.cmx-pos-actions #cmx-add-abschnitt { margin-left: 8px; }
		#cmx-positionen-table .cmx-pos-row-abschnitt td { background: #f8f9fa; }
		#cmx-positionen-table .cmx-abschnitt-titel {
			font-weight: 600;
			border-left: 3px solid #2271b1;
			padding-left: 8px;
		}
		#cmx-positionen-table .cmx-abschnitt-text {
			border-left: 3px solid #dcdcde;
			padding-left: 8px;
		}
	</style>
	<?php
}
add_action('admin_footer-post.php', __NAMESPACE__ . '\\cmx_beleg_abschnitt_admin_footer');
add_action('admin_footer-post-new.php', __NAMESPACE__ . '\\cmx_beleg_abschnitt_admin_footer');
