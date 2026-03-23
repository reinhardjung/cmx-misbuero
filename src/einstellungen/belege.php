<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit;

/* ------------------------------------------------------------
 * HILFSFUNKTIONEN
 * ------------------------------------------------------------ */

/** PHP 7 kompatibles starts_with */
function cmx_starts_with(string $haystack, string $needle): bool {
	return substr($haystack, 0, strlen($needle)) === $needle;
}

/**
 * Liefert den korrekten INI-Wert anhand des Keys:
 *   mail_offerte      → (E-Mails, Offerte)
 *   belegfuss_rechnung → (Belegfuss, Rechnung)
 */
// var_dump(get_option('cmx_belege')['belegfuss_rechnung']); exit;
// function cmx_get_beleg_default(string $key): string {
// 	if (cmx_starts_with($key, 'mail_')) {
// 		$section = 'E-Mails';
// 		$name    = ucfirst(substr($key, 5));
// 	}
// 	elseif (cmx_starts_with($key, 'belegfuss_')) {
// 		$section = 'Belegfuss';
// 		$name    = ucfirst(substr($key, 10));
// 	}
// 	else {
// 		return '';
// 	}

// 	$val = cmx_ini_get_value($section, $name);
// 	// var_dump(cmx_ini_get_value($section, $name)); exit;


// 	if (!is_string($val)) {
// 		return '';
// 	}

// 	return str_replace(['<br>', '<br/>', '<br />'], "\n", $val);
// }

function cmx_get_beleg_default(string $key): string {

	// Nur gespeicherte Optionen nutzen – nichts anderes
	$options = get_option('cmx_belege', []);

	if (isset($options[$key]) && is_string($options[$key])) {
		// var_dump($options[$key]); exit;
		return str_replace(['<br>', '<br/>', '<br />'], "\n", $options[$key]);
	}

	// Bestehende Einzelvorlagen beim Umstieg als Standard fuer "Sie" anzeigen.
	if (preg_match('/^mail_([a-z0-9_]+)_sie$/', $key, $matches)) {
		$legacy_key = 'mail_' . ((string) ($matches[1] ?? ''));
		if (isset($options[$legacy_key]) && is_string($options[$legacy_key])) {
			return str_replace(['<br>', '<br/>', '<br />'], "\n", $options[$legacy_key]);
		}
	}

	return '';
}

function cmx_beleg_field_input_id(string $key): string {
	return 'cmx_belege_' . $key;
}

function cmx_render_beleg_placeholder_buttons(string $field_id, array $placeholders): void {
	if ($field_id === '' || empty($placeholders)) {
		return;
	}

	$buttons = [];
	foreach ($placeholders as $placeholder) {
		$placeholder = trim((string) $placeholder);
		if ($placeholder === '') {
			continue;
		}
		$buttons[] = '<button type="button" class="button-link cmx-insert-placeholder" data-editor="' . esc_attr($field_id) . '" data-placeholder="' . esc_attr($placeholder) . '">' . esc_html($placeholder) . '</button>';
	}

	if (empty($buttons)) {
		return;
	}

	echo '<p class="description">Platzhalter: ' . implode(' · ', $buttons) . '</p>';

	static $script_printed = false;
	if ($script_printed) {
		return;
	}
	$script_printed = true;

	echo '<script>
	(function(){
		function needsSpace(ch) {
			return !(ch && /\\s/.test(ch));
		}
		function withSmartSpacing(text, beforeChar, afterChar) {
			var out = text;
			if (needsSpace(beforeChar)) out = " " + out;
			if (needsSpace(afterChar)) out = out + " ";
			return out;
		}
		function insertAtCursor(el, text) {
			if (!el) return;
			var start = typeof el.selectionStart === "number" ? el.selectionStart : (el.value || "").length;
			var end = typeof el.selectionEnd === "number" ? el.selectionEnd : start;
			var beforeChar = start > 0 ? (el.value || "").charAt(start - 1) : "";
			var afterChar = end < (el.value || "").length ? (el.value || "").charAt(end) : "";
			text = withSmartSpacing(text, beforeChar, afterChar);
			var val = el.value || "";
			el.value = val.slice(0, start) + text + val.slice(end);
			var pos = start + text.length;
			if (typeof el.selectionStart === "number") {
				el.selectionStart = pos;
				el.selectionEnd = pos;
			}
			el.focus();
		}
		function insertPlaceholder(editorId, text) {
			if (window.tinyMCE && tinyMCE.get(editorId) && !tinyMCE.get(editorId).isHidden()) {
				var editor = tinyMCE.get(editorId);
				editor.focus();
				var beforeChar = "";
				var afterChar = "";
				var rng = editor.selection.getRng();
				if (rng && rng.collapsed && rng.startContainer && rng.startContainer.nodeType === 3) {
					var nodeText = rng.startContainer.textContent || "";
					var offset = rng.startOffset || 0;
					beforeChar = offset > 0 ? nodeText.charAt(offset - 1) : "";
					afterChar = offset < nodeText.length ? nodeText.charAt(offset) : "";
				}
				text = withSmartSpacing(text, beforeChar, afterChar);
				editor.selection.setContent(text);
				return;
			}
			var field = document.getElementById(editorId);
			insertAtCursor(field, text);
		}
		document.addEventListener("click", function(e){
			var btn = e.target.closest ? e.target.closest(".cmx-insert-placeholder") : null;
			if (!btn) return;
			e.preventDefault();
			insertPlaceholder(btn.getAttribute("data-editor"), btn.getAttribute("data-placeholder"));
		});
	})();
	</script>';
}



/* ------------------------------------------------------------
 * TEXTAREA – MIT INI PLACEHOLDER
 * ------------------------------------------------------------ */
function cmx_field_textarea_beleg(array $args): void {

	$key  = $args['key'];
	$rows = intval($args['rows'] ?? 5);

	$options = get_option('cmx_belege', []);
	$value   = $options[$key] ?? '';

	$is_mail_field = cmx_starts_with($key, 'mail_');
	$is_belegfuss_field = cmx_starts_with($key, 'belegfuss_');
	if ($is_mail_field || $is_belegfuss_field) {
		$default = cmx_get_beleg_default($key);
		$display = ($value === '') ? (string) $default : (string) $value;
		$editor_id = cmx_beleg_field_input_id($key);
		\wp_editor($display, $editor_id, [
			'textarea_name' => 'cmx_belege[' . $key . ']',
			'textarea_rows' => $rows,
			'media_buttons' => false,
			'quicktags' => [
				'buttons' => 'strong,em,link,ul,ol,li,close',
			],
			'tinymce' => [
				'menubar' => false,
				'statusbar' => true,
				'resize' => true,
				'toolbar1' => 'bold,italic,link,unlink,bullist,numlist,undo,redo',
				'toolbar2' => '',
				'toolbar3' => '',
				'toolbar4' => '',
			],
		]);
		if ($is_mail_field) {
			cmx_render_beleg_placeholder_buttons($editor_id, ['{anrede}', '{beleg}', '{beleg_datum}', '{faellig_bis}', '{betrag}', '{logo}']);
		}
		return;
	}

	// Wenn leer -> Placeholder aus INI
	$default = cmx_get_beleg_default($key);
	$display = ($value === '') ? $default : $value;

	echo '<textarea
		name="cmx_belege[' . esc_attr($key) . ']"
		rows="' . esc_attr($rows) . '"
		style="width:100%;resize:both;">' . esc_textarea($display) . '</textarea>';
}

function cmx_field_select_briefbogen(array $args): void {
	$key = (string)($args['key'] ?? '');
	$options = (array) get_option('cmx_belege', []);
	$current = strtolower(trim((string)($options[$key] ?? 'dl_left')));
	$choices = [
		'dl_left' => 'DL (DIN lang) - Fenster links',
		'c5_left' => 'C5 gefalzt - Fenster links',
		'c4_left' => 'C4 ungefalzt - Fenster links',
		'dl_right' => 'DL (DIN lang) - Fenster rechts',
		'c5_right' => 'C5 gefalzt - Fenster rechts',
		'c4_right' => 'C4 ungefalzt - Fenster rechts',
	];
	$legacy_map = [
		'dl' => 'dl_left',
		'c5' => 'c5_left',
		'c4' => 'c4_left',
	];
	if (isset($legacy_map[$current])) {
		$current = $legacy_map[$current];
	}
	if (!isset($choices[$current])) {
		$current = 'dl_left';
	}

	echo '<select name="cmx_belege[' . esc_attr($key) . ']" style="min-width:320px;">';
	foreach ($choices as $val => $label) {
		echo '<option value="' . esc_attr($val) . '" ' . selected($current, $val, false) . '>' . esc_html($label) . '</option>';
	}
	echo '</select>';
	echo '<p class="description">Standard-Briefbogen für diese Belegart.</p>';
}


/* ------------------------------------------------------------
 * FELDER REGISTRIEREN
 * ------------------------------------------------------------ */
add_action('admin_init', function() {

	$add = function($page,$section,$label,$key,$rows) {
		add_settings_field(
			$key,
			$label,
			__NAMESPACE__ . '\\cmx_field_textarea_beleg',
			$page,
			$section,
			[
				'key'  => $key,
				'rows' => $rows,
			]
		);
	};
	$tabs = [
		'offerte'      => 'Offerte',
		'gutschrift'   => 'Gutschrift',
		'lieferschein' => 'Lieferschein',
		'rechnung'     => 'Rechnung',
		'mahnung'      => 'Mahnung',
	];

	foreach ($tabs as $sub => $label) {

		$page = "cmx_tab_belege__{$sub}";

		add_settings_section(
			"sec_{$sub}",
			$label,
			'__return_false',
			$page
		);

		$add($page, "sec_{$sub}", 'Belegfuss',            "belegfuss_{$sub}",        4);
		$add($page, "sec_{$sub}", 'E-Mail Text Sie',      "mail_{$sub}_sie",         8);
		$add($page, "sec_{$sub}", 'E-Mail Text Du',       "mail_{$sub}_du",          8);
	}
});

add_filter('pre_update_option_cmx_belege', function($new, $old) {
	if (!is_array($new)) {
		return is_array($old) ? $old : [];
	}

	$allowed = ['dl_left', 'c5_left', 'c4_left', 'dl_right', 'c5_right', 'c4_right'];
	$legacy_map = [
		'dl' => 'dl_left',
		'c5' => 'c5_left',
		'c4' => 'c4_left',
	];
	foreach (['offerte', 'gutschrift', 'lieferschein', 'rechnung'] as $type) {
		$key = 'briefbogen_' . $type;
		if (!array_key_exists($key, $new)) {
			if (is_array($old) && array_key_exists($key, $old)) {
				$new[$key] = $old[$key];
			}
			continue;
		}
		$val = strtolower(trim((string)($new[$key] ?? 'dl_left')));
		if (isset($legacy_map[$val])) {
			$val = $legacy_map[$val];
		}
		$new[$key] = in_array($val, $allowed, true) ? $val : 'dl_left';
	}

	return $new;
}, 10, 2);
