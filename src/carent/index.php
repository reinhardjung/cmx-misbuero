<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


// Define: Custom-Post-Type based on DIR
register_post_type(basename(__DIR__), ['labels' => ['name' => 'CaRent', 'singular_name' => cmx_sani_key(basename(__DIR__), 'title'), 'add_new_item' => 'Hinzufügen', 'edit_item' => 'Bearbeiten',],
	'menu_position' => 105, 'supports' => ['title', 'editor'], 'public' => true, 'menu_icon' => 'dashicons-car', 'show_in_rest' => true, 'has_archive' => true, 'rewrite' => ['slug' => basename(__DIR__)],
]);

\add_filter('wp_editor_settings', function (array $settings, string $editor_id): array {
	if ($editor_id !== 'content') {
		return $settings;
	}

	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== basename(__DIR__)) {
		return $settings;
	}

	$settings['textarea_rows'] = 5;
	$settings['editor_height'] = 120;
	if (($settings['tinymce'] ?? true) !== false) {
		$settings['tinymce'] = \is_array($settings['tinymce'] ?? null) ? $settings['tinymce'] : [];
		$settings['tinymce']['height'] = 120;
	}

	return $settings;
}, 10, 2);

\add_action('admin_head', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== basename(__DIR__)) {
		return;
	}
	echo '<style>
		#postdivrich{
			margin-top:0 !important;
		}
		#postdivrich #wp-content-wrap .mce-edit-area{
			height:120px !important;
			min-height:120px !important;
		}
		#postdivrich #wp-content-editor-container textarea.wp-editor-area,
		#postdivrich #content,
		#postdivrich .mce-edit-area iframe{
			height:120px !important;
			min-height:120px !important;
		}
	</style>';
});

\add_action('admin_print_footer_scripts', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== basename(__DIR__)) {
		return;
	}
	?>
	<script>
	(function(){
		const placeholder = "Besondere Abmachungen";
		const height = 120;

		function getTextarea(){
			return document.getElementById("content");
		}

		function getEditorWrap(){
			return document.getElementById("wp-content-wrap");
		}

		function normalizeEditorValue(value){
			return String(value || "")
				.replace(/<!--[\s\S]*?-->/g, "")
				.replace(/<p>(?:\s|&nbsp;|<br\s*\/?>)*<\/p>/gi, "")
				.replace(/<(br|hr)\b[^>]*>/gi, "")
				.replace(/&nbsp;/gi, "")
				.replace(/\s+/g, "")
				.trim();
		}

		function getEditor(){
			return window.tinymce ? window.tinymce.get("content") : null;
		}

		function ensureVisualPlaceholderStyle(editor){
			if (!editor || !editor.getDoc || !editor.getBody) {
				return null;
			}

			var doc = null;
			try {
				doc = editor.getDoc();
			} catch (err) {
				doc = null;
			}
			if (!doc || !doc.head) {
				return null;
			}

			var styleEl = doc.getElementById("cmx-carent-editor-placeholder-style");
			if (!styleEl) {
				styleEl = doc.createElement("style");
				styleEl.id = "cmx-carent-editor-placeholder-style";
				styleEl.textContent =
					'body.mce-content-body{position:relative;}' +
					'body.mce-content-body.cmx-carent-placeholder-empty::before{' +
						'content:attr(data-cmx-placeholder);' +
						'position:absolute;' +
						'left:0;' +
						'top:0;' +
						'color:#8c8f94;' +
						'font-style:italic;' +
						'pointer-events:none;' +
						'white-space:nowrap;' +
						'overflow:hidden;' +
						'text-overflow:ellipsis;' +
						'max-width:100%;' +
						'z-index:2;' +
					'}' +
					'body.mce-content-body.cmx-carent-placeholder-empty p:first-child::before{' +
						'content:attr(data-cmx-placeholder);' +
						'color:#8c8f94;' +
						'font-style:italic;' +
						'pointer-events:none;' +
					'}' +
					'body.mce-content-body.cmx-carent-placeholder-empty p:first-child{' +
						'margin-top:0;' +
					'}' +
					'body.mce-content-body.cmx-carent-placeholder-empty p:first-child br[data-mce-bogus]{display:none;}' +
					'body.mce-content-body.cmx-carent-placeholder-empty p:first-child[data-mce-caret]{color:transparent;}' +
					'body.mce-content-body.cmx-carent-placeholder-empty p:first-child[data-mce-caret]::selection{background:transparent;}' +
					'}';
				doc.head.appendChild(styleEl);
			}

			return editor.getBody ? editor.getBody() : null;
		}

		function isEditorEmpty(){
			var editor = getEditor();
			if (editor) {
				try {
					var body = editor.getBody ? editor.getBody() : null;
					if (body && editor.dom && typeof editor.dom.isEmpty === "function" && editor.dom.isEmpty(body)) {
						return true;
					}
				} catch (err) {}

				try {
					var plainText = String(editor.getContent({ format: "text" }) || "")
						.replace(/\u00a0/g, "")
						.replace(/\u200B/g, "")
						.replace(/\uFEFF/g, "")
						.trim();
					if (plainText !== "") {
						return false;
					}
				} catch (err) {}

				try {
					return normalizeEditorValue(
						String(editor.getContent({ format: "raw" }) || "")
							.replace(/<p\b[^>]*>(?:\s|&nbsp;|&#160;|&#8203;|&#65279;|<br[^>]*>)*<\/p>/gi, "")
					) === "";
				} catch (err) {}
			}
			var textarea = getTextarea();
			return !textarea || String(textarea.value || "").trim() === "";
		}

		function applyEditorPlaceholder(){
			var textarea = getTextarea();
			if (textarea) {
				textarea.setAttribute("placeholder", placeholder);
			}

			var wrap = getEditorWrap();
			var editor = getEditor();
			if (wrap && wrap.classList.contains("tmce-active") && editor && !editor.isHidden()) {
				var body = ensureVisualPlaceholderStyle(editor);
				if (body) {
					body.setAttribute("data-cmx-placeholder", placeholder);
					body.classList.toggle("cmx-carent-placeholder-empty", isEditorEmpty());
				}
			}
		}

		function applyEditorHeight() {
			document.querySelectorAll('#postdivrich #wp-content-editor-container textarea.wp-editor-area, #postdivrich #content, #postdivrich .mce-edit-area iframe').forEach(function(el){
				el.style.height = height + 'px';
				el.style.minHeight = height + 'px';
			});
			if (window.tinymce) {
				const editor = window.tinymce.get('content');
				if (editor) {
					if (editor.iframeElement) {
						editor.iframeElement.style.height = height + 'px';
						editor.iframeElement.style.minHeight = height + 'px';
					}
					if (editor.getContainer && editor.getContainer()) {
						var container = editor.getContainer();
						container.style.maxWidth = "100%";
						var editArea = container.querySelector(".mce-edit-area");
						if (editArea) {
							editArea.style.height = height + 'px';
							editArea.style.minHeight = height + 'px';
						}
					}
					if (editor.theme && typeof editor.theme.resizeTo === "function") {
						try { editor.theme.resizeTo(null, height); } catch (err) {}
					}
				}
			}
		}

		function applyEditorUi(){
			applyEditorHeight();
			applyEditorPlaceholder();
		}

		function bindTextarea(){
			var textarea = getTextarea();
			if (!textarea || textarea.dataset.cmxCarentPlaceholderBound === "1") {
				return;
			}
			textarea.dataset.cmxCarentPlaceholderBound = "1";
			textarea.addEventListener("input", applyEditorPlaceholder);
			textarea.addEventListener("focus", applyEditorPlaceholder);
			textarea.addEventListener("blur", applyEditorPlaceholder);
		}

		function bindTinyMceEditor(){
			var editor = getEditor();
			if (!editor || editor.__cmxCarentPlaceholderBound) {
				return;
			}
			editor.__cmxCarentPlaceholderBound = true;

			function refresh(){
				window.setTimeout(applyEditorUi, 0);
			}

			if (typeof editor.on === "function") {
				["init", "focus", "blur", "keyup", "input", "change", "SetContent", "ExecCommand", "Undo", "Redo", "NodeChange"].forEach(function(name){
					editor.on(name, refresh);
				});
			}

			refresh();
		}

		document.addEventListener('DOMContentLoaded', applyEditorUi, {once:true});
		window.addEventListener('load', applyEditorUi, {once:true});
		document.addEventListener("click", function(event){
			if (event.target && event.target.closest && event.target.closest("#content-tmce, #content-html")) {
				window.setTimeout(applyEditorUi, 0);
			}
		});
		let runs = 0;
		const timer = window.setInterval(function(){
			bindTextarea();
			bindTinyMceEditor();
			applyEditorUi();
			runs += 1;
			if (runs >= 20) {
				window.clearInterval(timer);
			}
		}, 250);
	})();
	</script>
	<?php
});


// Define: CONST 4 @ll Taxos
define(__NAMESPACE__ . '\\CMX_TAX_'.strtoupper(basename(__DIR__)),'Kategorien');


// Define: CONST 4 each Taxo
cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), CMX_TAX_CARENT);
// cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), define('\\CMX_TAX_',strtoupper(basename(__DIR__))));


// Create: @ll Taxos
\add_action('init', function () {
	// cmx_create_taxo(basename(__DIR__), 'Kategorie', 'Kategorien');
}, 15);


// Refill: Taxo with defaults if removed
\add_action('admin_init', function () {
	cmx_seed_taxo(cmx_sani_key(basename(__DIR__),'title'),CMX_TAX_CARENT);
});


// Define: Const 4 @ll CPT Fields
// cmx_define_meta_constants(basename(__DIR__), ['umsatz']);


// Include: @ll metaboxes
cmx_require_files(__DIR__,'stammdaten,admincolumns,kontakt,status,fahrzeug,uebernahme_rueckgabe,ausweis_fahrer,ausweis_id');


if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_composed_title')) {
	function cmx_carent_composed_title(int $post_id): string {
		$parts = [];

		$artikel_meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_META')
			: '_cmx_carent_fahrzeug_id';
		$artikel_id = (int) \get_post_meta($post_id, $artikel_meta_key, true);
		if ($artikel_id > 0 && \get_post_status($artikel_id)) {
			$artikel_title = '';
			if (\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_display_label')) {
				$artikel_title = \trim((string) cmx_carent_fahrzeug_display_label($artikel_id));
			}
			if ($artikel_title === '') {
				$artikel_title = \trim((string) \get_the_title($artikel_id));
				if ($artikel_title !== '' && \function_exists(__NAMESPACE__ . '\\cmx_normalize_minus_sign')) {
					$artikel_title = (string) cmx_normalize_minus_sign($artikel_title);
				}
			}
			if ($artikel_title !== '') {
				$parts[] = $artikel_title;
			}
		}

		$kontakt_meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_KONTAKT_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_KONTAKT_META')
			: '_cmx_carent_kontakt_id';
		$kontakt_id = (int) \get_post_meta($post_id, $kontakt_meta_key, true);
		if ($kontakt_id > 0 && \get_post_status($kontakt_id)) {
			$valid_kontakt = true;
			if (\function_exists(__NAMESPACE__ . '\\cmx_carent_kontakt_post_types')) {
				$valid_types = (array) cmx_carent_kontakt_post_types();
				if ($valid_types !== []) {
					$valid_kontakt = \in_array((string) \get_post_type($kontakt_id), $valid_types, true);
				}
			}
			if ($valid_kontakt) {
				$kontakt_title = \trim((string) \get_the_title($kontakt_id));
				if ($kontakt_title !== '' && \function_exists(__NAMESPACE__ . '\\cmx_normalize_minus_sign')) {
					$kontakt_title = (string) cmx_normalize_minus_sign($kontakt_title);
				}
				if ($kontakt_title !== '') {
					$parts[] = $kontakt_title;
				}
			}
		}

		return \implode(' - ', \array_values(\array_filter($parts, static fn($value): bool => \trim((string) $value) !== '')));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_sync_post_title')) {
	function cmx_carent_sync_post_title(int $post_id, \WP_Post $post, bool $update): void {
		unset($update);
		static $running = false;
		if ($running) {
			return;
		}
		if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) {
			return;
		}
		if ((string) $post->post_type !== 'carent') {
			return;
		}
		if (!\current_user_can('edit_post', $post_id)) {
			return;
		}

		$title = \trim((string) cmx_carent_composed_title($post_id));
		if ($title === '' || $title === \trim((string) $post->post_title)) {
			return;
		}

		$running = true;
		\wp_update_post([
			'ID'         => $post_id,
			'post_title' => $title,
		]);
		$running = false;
	}
}

\add_action('save_post_carent', __NAMESPACE__ . '\\cmx_carent_sync_post_title', 999, 3);
