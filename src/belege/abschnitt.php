<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_abschnitt_is_row')) {
	function cmx_beleg_abschnitt_is_row($row): bool {
		if (!\is_array($row)) return false;
		$typ = \sanitize_key((string) ($row['typ'] ?? ''));
		return $typ === 'abschnitt';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_textbaustein_admin_url')) {
	function cmx_beleg_textbaustein_admin_url(): string {
		$tax = '';
		if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_textbaustein_taxonomy')) {
			$tax = (string) \call_user_func(__NAMESPACE__ . '\\cmx_beleg_textbaustein_taxonomy');
		}
		if ($tax === '') {
			foreach (['belege_belegetextbausteine', 'belege_textbausteine', 'belege_textbaustein'] as $candidate) {
				if (\taxonomy_exists($candidate)) {
					$tax = $candidate;
					break;
				}
			}
		}
		if ($tax === '') return '';
		return \admin_url('edit-tags.php?taxonomy=' . \rawurlencode($tax) . '&post_type=belege');
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
	$textbaustein_edit_url = \function_exists(__NAMESPACE__ . '\\cmx_beleg_textbaustein_admin_url')
		? (string) \call_user_func(__NAMESPACE__ . '\\cmx_beleg_textbaustein_admin_url')
		: '';

	echo '<tr class="cmx-pos-row cmx-pos-row-abschnitt">';
	echo '<td colspan="7" class="cmx-pos-abschnitt-cell">';
	echo '<input type="hidden" name="cmx_positionen[' . $i . '][typ]" value="abschnitt">';
	echo '<div class="cmx-abschnitt-controls"><button type="button" class="button button-small cmx-section-drag-handle" title="Gesamten Abschnitt verschieben" aria-label="Gesamten Abschnitt verschieben">↕</button><span class="cmx-pos-drag-handle" title="Zeile verschieben" aria-label="Zeile verschieben">↕</span><button type="button" class="button-link-delete cmx-del-pos"><span class="dashicons dashicons-trash" style=""></span></button></div>';
	echo '<input type="text" class="regular-text cmx-abschnitt-titel" name="cmx_positionen[' . $i . '][abschnitt_titel]" value="' . \esc_attr($titel) . '" placeholder="Abschnitt" style="width:calc(100% - 14px); margin-left:20px; box-sizing:border-box;">';
	echo '<div class="cmx-abschnitt-text-wrap">';
	if ($textbaustein_edit_url !== '') {
		echo '<a href="' . \esc_url($textbaustein_edit_url) . '" class="cmx-textbaustein-edit cmx-abschnitt-text-edit" aria-label="Textbausteine bearbeiten" title="Textbausteine im neuen Tab bearbeiten" target="_blank" rel="noopener noreferrer">✎</a>';
	}
	echo '<textarea class="cmx-abschnitt-text" name="cmx_positionen[' . $i . '][abschnitt_text]" rows="2" placeholder="Beschreibender Text" style="width:calc(100% - 14px); margin-top:6px; margin-left:20px; box-sizing:border-box;">' . \esc_textarea($text) . '</textarea>';
	echo '</div>';
	echo '</td>';
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
	$textbaustein_edit_link_html = '';
	if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_textbaustein_admin_url')) {
		$url = (string) \call_user_func(__NAMESPACE__ . '\\cmx_beleg_textbaustein_admin_url');
		if ($url !== '') {
			$textbaustein_edit_link_html = '<a href="' . \esc_url($url) . '" class="cmx-textbaustein-edit cmx-abschnitt-text-edit" aria-label="Textbausteine bearbeiten" title="Textbausteine im neuen Tab bearbeiten" target="_blank" rel="noopener noreferrer">✎</a>';
		}
	}
	?>
	<script>
	jQuery(function($){
		const table = $('#cmx-positionen-table tbody');
		if (!table.length) return;
		const TEXTBAUSTEIN_EDIT_LINK = <?php echo \wp_json_encode($textbaustein_edit_link_html); ?>;

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
								'<td colspan="7" class="cmx-pos-abschnitt-cell">' +
									'<input type="hidden" name="cmx_positionen[' + i + '][typ]" value="abschnitt">' +
									'<div class="cmx-abschnitt-controls"><button type="button" class="button button-small cmx-section-drag-handle" title="Gesamten Abschnitt verschieben" aria-label="Gesamten Abschnitt verschieben">↕</button><span class="cmx-pos-drag-handle" title="Zeile verschieben" aria-label="Zeile verschieben">↕</span><button type="button" class="button-link-delete cmx-del-pos"><span class="dashicons dashicons-trash" style=""></span></button></div>' +
									'<input type="text" class="regular-text cmx-abschnitt-titel" name="cmx_positionen[' + i + '][abschnitt_titel]" value="" placeholder="Abschnitt" style="width:calc(100% - 14px); margin-left:20px; box-sizing:border-box;">' +
									'<div class="cmx-abschnitt-text-wrap">' +
										TEXTBAUSTEIN_EDIT_LINK +
										'<textarea class="cmx-abschnitt-text" name="cmx_positionen[' + i + '][abschnitt_text]" rows="2" placeholder="Beschreibender Text" style="width:calc(100% - 14px); margin-top:6px; margin-left:20px; box-sizing:border-box;"></textarea>' +
									'</div>' +
								'</td>' +
							'</tr>';

				const $row = $(rowHtml);
				table.append($row);
				table.trigger('cmx_positionen_rows_changed');
				setTimeout(function(){
					const $title = $row.find('.cmx-abschnitt-titel').first();
					if ($title.length) {
						$title.trigger('focus').trigger('click').select();
					} else {
						$row.find('.cmx-abschnitt-text').first().trigger('focus').trigger('click');
					}
				}, 0);
			});
	});
	</script>
		<style>
			.cmx-pos-actions #cmx-add-abschnitt { margin-left: 8px; }
			#cmx-positionen-table .cmx-pos-row-abschnitt td { background: #f8f9fa; }
			#cmx-positionen-table .cmx-pos-row-abschnitt > td.cmx-pos-abschnitt-cell {
				position: relative;
				padding-right: 78px !important;
			}
			#cmx-positionen-table .cmx-abschnitt-controls{
				position:absolute;
				top:0;
				right:0;
				display:flex;
				align-items:flex-start;
				gap:4px;
			}
			#cmx-positionen-table .cmx-abschnitt-titel {
				font-weight: 600;
				border-left: 3px solid #2271b1;
				padding-left: 8px;
			}
			#cmx-positionen-table .cmx-abschnitt-text-wrap{
				position:relative;
				padding-left:26px;
			}
			#cmx-positionen-table .cmx-abschnitt-text-edit{
				left:26px;
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
