<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_SCANNER_ZUORDNUNG_TYP_META = '_cmx_scanner_zuordnung_typ';
const CMX_SCANNER_ZUORDNUNG_TYPES = [
	'kontakte'  => 'Kontakte',
	'artikel'   => 'Artikel',
	'dokumente' => 'Dokumente',
	'projekte'  => 'Projekte',
	'belege'    => 'Belege',
];

function cmx_scanner_cpt_slug(): string {
	return basename(__DIR__);
}

function cmx_scanner_get_selected_zuordnung_type(int $post_id): string {
	$type = (string) \get_post_meta($post_id, CMX_SCANNER_ZUORDNUNG_TYP_META, true);
	return \array_key_exists($type, CMX_SCANNER_ZUORDNUNG_TYPES) ? $type : '';
}

function cmx_scanner_get_requested_zuordnung_type(int $post_id = 0): string {
	$type = isset($_POST['cmx_scanner_zuordnung_typ'])
		? \sanitize_key((string) $_POST['cmx_scanner_zuordnung_typ'])
		: '';

	if ($type === '' && $post_id > 0) {
		$type = cmx_scanner_get_selected_zuordnung_type($post_id);
	}

	return \array_key_exists($type, CMX_SCANNER_ZUORDNUNG_TYPES) ? $type : '';
}

function cmx_scanner_relation_was_touched(string $meta_key): bool {
	if ($meta_key === '') {
		return false;
	}
	if (!isset($_POST['cmx_scanner_rel_touched']) || !\is_array($_POST['cmx_scanner_rel_touched'])) {
		return false;
	}
	$map = (array) $_POST['cmx_scanner_rel_touched'];
	return isset($map[$meta_key]) && (int) $map[$meta_key] === 1;
}

function cmx_scanner_get_rel_meta_keys(): array {
	return [
		'kontakte'  => '_cmx_scanner_rel_kontakte_id',
		'artikel'   => '_cmx_scanner_rel_artikel_id',
		'dokumente' => '_cmx_scanner_rel_dokumente_id',
		'projekte'  => '_cmx_scanner_rel_projekte_id',
		'belege'    => '_cmx_scanner_rel_belege_id',
	];
}

function cmx_scanner_get_relation_metabox_ids(): array {
	return [
		'kontakte'  => 'cmx_scanner_rel_kontakte',
		'artikel'   => 'cmx_scanner_rel_artikel',
		'dokumente' => 'cmx_scanner_rel_dokumente',
		'projekte'  => 'cmx_scanner_rel_projekte',
		'belege'    => 'cmx_scanner_rel_belege',
	];
}

function cmx_scanner_fetch_relation_options(string $target_post_type, int $limit = 200): array {
	$ids = \get_posts([
		'post_type'      => $target_post_type,
		'post_status'    => ['publish', 'draft', 'pending', 'private'],
		'posts_per_page' => $limit,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'fields'         => 'ids',
		'no_found_rows'  => true,
	]);

	$options = [];
	foreach ($ids as $id) {
		$id = (int) $id;
		if ($id <= 0) {
			continue;
		}
		$title = \get_the_title($id);
		if ($title === '') {
			$title = '(ohne Titel)';
		}
		$options[] = [
			'id'    => $id,
			'label' => $title,
		];
	}
	return $options;
}

function cmx_scanner_render_relation_select_box(\WP_Post $post, string $target_type, string $meta_key, string $nonce_action, string $nonce_name, string $empty_label = 'Kein Eintrag'): void {
	if ($post->post_type !== cmx_scanner_cpt_slug()) {
		return;
	}

	\wp_nonce_field($nonce_action, $nonce_name);
	$current = (int) \get_post_meta($post->ID, $meta_key, true);
	$has_current = $current > 0;
	$options = cmx_scanner_fetch_relation_options($target_type);
	$target_label = CMX_SCANNER_ZUORDNUNG_TYPES[$target_type] ?? ucfirst($target_type);
	$id_suffix = preg_replace('~[^a-z0-9_]+~', '_', strtolower($target_type . '_' . $meta_key));
	$select_id = 'cmx_scanner_rel_select_' . $id_suffix;
	$search_id = 'cmx_scanner_rel_search_' . $id_suffix;
	$nohit_id  = 'cmx_scanner_rel_nohit_' . $id_suffix;
	$touched_id = 'cmx_scanner_rel_touched_' . $id_suffix;
	$touched_name = 'cmx_scanner_rel_touched[' . $meta_key . ']';

	// echo '<p style="margin:0 0 8px;"><em>Zuordnung zu: ' . \esc_html($target_label) . '</em></p>';
	echo '<label for="' . \esc_attr($search_id) . '" class="screen-reader-text">Suchen</label>';
	echo '<input type="hidden" id="' . \esc_attr($touched_id) . '" name="' . \esc_attr($touched_name) . '" value="0" />';
	echo '<input type="search" id="' . \esc_attr($search_id) . '" class="cmx-scanner-rel-search" data-target-select="' . \esc_attr($select_id) . '" data-target-nohit="' . \esc_attr($nohit_id) . '" placeholder="Suchen..." style="width:100%;margin:0 0 8px;" autocomplete="off" />';
	echo '<select id="' . \esc_attr($select_id) . '" class="cmx-scanner-rel-select" data-target-touched="' . \esc_attr($touched_id) . '" data-cmx-no-selection="' . ($has_current ? '0' : '1') . '" name="' . \esc_attr($meta_key) . '" style="width:100%;appearance:none;-webkit-appearance:none;-moz-appearance:none;background-image:none;" size="10">';
	echo '<option value="0"' . ($has_current ? '' : ' selected') . '>' . \esc_html($empty_label) . '</option>';
	foreach ($options as $opt) {
		echo '<option value="' . \esc_attr((string) $opt['id']) . '" ' . \selected($current, (int) $opt['id'], false) . '>' . \esc_html((string) $opt['label']) . '</option>';
	}
	echo '</select>';
	echo '<p id="' . \esc_attr($nohit_id) . '" style="display:none;margin:8px 0 0;"><em>Keine Treffer.</em></p>';

	if (empty($options)) {
		echo '<p style="margin:8px 0 0;"><em>Keine Datensätze gefunden.</em></p>';
	}

	static $printed_search_js = false;
		if (!$printed_search_js) {
			$printed_search_js = true;
			echo '<script>
			(function(){
				if (window.cmxScannerRelSearchInit) return;
				window.cmxScannerRelSearchInit = true;

				var markTouched = function(select){
					if (!select) return;
					var touchedId = select.getAttribute("data-target-touched") || "";
					if (!touchedId) return;
					var touched = document.getElementById(touchedId);
					if (!touched) return;
					touched.value = "1";
				};

				var clearSelection = function(select){
					if (!select) return;
					for (var i = 0; i < select.options.length; i++) {
						select.options[i].selected = false;
					}
					select.selectedIndex = -1;
					markTouched(select);
				};

				var filter = function(input){
					var selectId = input.getAttribute("data-target-select") || "";
					var nohitId = input.getAttribute("data-target-nohit") || "";
				var select = document.getElementById(selectId);
				if (!select) return;
				var nohit = nohitId ? document.getElementById(nohitId) : null;
				var term = (input.value || "").toLowerCase().trim();
				var visible = 0;

				for (var i = 0; i < select.options.length; i++) {
					var opt = select.options[i];
					if (i === 0) {
						opt.hidden = false;
						continue;
					}
					var txt = (opt.textContent || opt.innerText || "").toLowerCase();
					var match = term === "" || txt.indexOf(term) !== -1;
					opt.hidden = !match;
					if (!match && opt.selected) {
						opt.selected = false;
					}
					if (match) {
						visible++;
					}
				}

				if (nohit) {
					nohit.style.display = (term !== "" && visible === 0) ? "" : "none";
				}
			};

				document.addEventListener("input", function(e){
					var t = e.target;
					if (!t || !t.classList || !t.classList.contains("cmx-scanner-rel-search")) return;
					filter(t);
				});

				document.addEventListener("change", function(e){
					var t = e.target;
					if (!t || !t.classList || !t.classList.contains("cmx-scanner-rel-select")) return;
					markTouched(t);
				});

				document.addEventListener("keydown", function(e){
					var t = e.target;
					if (!t || !t.classList || !t.classList.contains("cmx-scanner-rel-search")) return;
					if (e.key === "Escape") {
						var selectId = t.getAttribute("data-target-select") || "";
						var nohitId = t.getAttribute("data-target-nohit") || "";
						var select = document.getElementById(selectId);
						var nohit = nohitId ? document.getElementById(nohitId) : null;
						e.preventDefault();
						t.value = "";
						filter(t);
						clearSelection(select);
						if (nohit) {
							nohit.style.display = "none";
						}
						return;
					}
					if (e.key !== "ArrowDown") return;
					var selectId = t.getAttribute("data-target-select") || "";
					var select = document.getElementById(selectId);
				if (!select) return;
				e.preventDefault();
				select.focus();
				for (var i = 1; i < select.options.length; i++) {
					if (!select.options[i].hidden) {
						select.options[i].selected = true;
						break;
						}
					}
				});

				document.addEventListener("keydown", function(e){
					var t = e.target;
					if (!t || !t.classList || !t.classList.contains("cmx-scanner-rel-select")) return;
					if (e.key !== "Escape") return;
					e.preventDefault();
					clearSelection(t);
					var searchId = t.id ? t.id.replace("cmx_scanner_rel_select_", "cmx_scanner_rel_search_") : "";
					var search = searchId ? document.getElementById(searchId) : null;
					if (search) {
						search.focus();
					}
				});

				var boot = function(){
					var selects = document.querySelectorAll(".cmx-scanner-rel-select[data-cmx-no-selection=\'1\']");
					for (var si = 0; si < selects.length; si++) {
						var select = selects[si];
						var hasSelected = false;
						for (var oi = 0; oi < select.options.length; oi++) {
							if (select.options[oi].selected) {
								hasSelected = true;
								break;
							}
						}
						if (hasSelected) {
							select.selectedIndex = -1;
						}
					}
					var inputs = document.querySelectorAll(".cmx-scanner-rel-search");
					for (var i = 0; i < inputs.length; i++) {
						filter(inputs[i]);
				}
			};
			if (document.readyState === "loading") {
				document.addEventListener("DOMContentLoaded", boot);
			} else {
				boot();
			}
		})();
		</script>';
	}
}

function cmx_scanner_print_relation_metabox_hide_style(): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== cmx_scanner_cpt_slug()) {
		return;
	}

	$ids = cmx_scanner_get_relation_metabox_ids();
	if (empty($ids)) {
		return;
	}

	$post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
	$selected = $post_id > 0 ? cmx_scanner_get_selected_zuordnung_type($post_id) : '';
	$selected_box_id = ($selected !== '' && isset($ids[$selected])) ? (string) $ids[$selected] : '';

	$selectors = [];
	foreach ($ids as $id) {
		$selectors[] = '#' . $id;
	}
	$css = \implode(',', $selectors) . '{display:none;}';
	if ($selected_box_id !== '') {
		$css .= '#' . $selected_box_id . '{display:block;}';
	}
	echo '<style>' . $css . '</style>';
}
\add_action('admin_head-post.php', __NAMESPACE__ . '\\cmx_scanner_print_relation_metabox_hide_style');
\add_action('admin_head-post-new.php', __NAMESPACE__ . '\\cmx_scanner_print_relation_metabox_hide_style');

\add_action('add_meta_boxes', function (): void {
	\add_meta_box(
		'cmx_scanner_zuordnung',
		'Zuordnung',
		__NAMESPACE__ . '\\cmx_scanner_render_zuordnung_metabox',
		cmx_scanner_cpt_slug(),
		'side',
		'default'
	);
});

function cmx_scanner_render_zuordnung_metabox(\WP_Post $post): void {
	\wp_nonce_field('cmx_scanner_zuordnung_save', 'cmx_scanner_zuordnung_nonce');
	$current = cmx_scanner_get_selected_zuordnung_type((int) $post->ID);
	$metaboxMapJson = \wp_json_encode(cmx_scanner_get_relation_metabox_ids());

	echo '<label for="cmx_scanner_zuordnung_typ" class="screen-reader-text">Zuordnung</label>';
	echo '<select id="cmx_scanner_zuordnung_typ" name="cmx_scanner_zuordnung_typ" style="width:100%;appearance:none;-webkit-appearance:none;-moz-appearance:none;background-image:none;">';
	echo '<option value="">- bitte wählen -</option>';
	foreach (CMX_SCANNER_ZUORDNUNG_TYPES as $key => $label) {
		echo '<option value="' . \esc_attr($key) . '" ' . \selected($current, $key, false) . '>' . \esc_html($label) . '</option>';
	}
	echo '</select>';
	// echo '<p style="margin:8px 0 0;"><em>Die passende Detail-Metabox erscheint direkt nach der Auswahl.</em></p>';
	if (\is_string($metaboxMapJson) && $metaboxMapJson !== '') {
		echo '<script>
		(function(){
			var init = function(){
				var select = document.getElementById("cmx_scanner_zuordnung_typ");
				if (!select) return;
				var map = ' . $metaboxMapJson . ';
					window.cmxScannerToggleZuordnung = function(selected, focusSearch){
						selected = selected || "";
						focusSearch = !!focusSearch;
						var activeBox = null;
						Object.keys(map).forEach(function(type){
							var boxId = map[type];
							var box = document.getElementById(boxId);
							if (!box) return;
							var isActive = (selected !== "" && selected === type);
							box.style.display = isActive ? "block" : "none";
							if (isActive) {
								activeBox = box;
							}
						});
						if (activeBox) {
							activeBox.classList.remove("closed");
							var inside = activeBox.querySelector(".inside");
							if (inside) {
								inside.style.display = "";
							}
							var toggles = activeBox.querySelectorAll(".handlediv, .hndle");
							for (var ti = 0; ti < toggles.length; ti++) {
								toggles[ti].setAttribute("aria-expanded", "true");
							}
						}
						if (focusSearch && activeBox) {
							var search = activeBox.querySelector(".cmx-scanner-rel-search");
							if (search) {
								setTimeout(function(){
									search.focus();
									if (typeof search.select === "function") {
										search.select();
									}
								}, 0);
							}
						}
					};
					select.addEventListener("change", function(){
						window.cmxScannerToggleZuordnung(select.value || "", true);
					});
					document.addEventListener("click", function(e){
						var trigger = e.target && e.target.closest ? e.target.closest(".postbox .handlediv, .postbox .hndle") : null;
						if (!trigger) return;
						setTimeout(function(){
							window.cmxScannerToggleZuordnung(select.value || "", false);
						}, 0);
					});
					window.cmxScannerToggleZuordnung(select.value || "", false);
					setTimeout(function(){
						window.cmxScannerToggleZuordnung(select.value || "", false);
					}, 60);
			};
			if (document.readyState === "loading") {
				document.addEventListener("DOMContentLoaded", init);
			} else {
				init();
			}
		})();
		</script>';
	}
}

\add_action('save_post_scanner', function (int $post_id, \WP_Post $post): void {
	if ($post->post_type !== cmx_scanner_cpt_slug()) {
		return;
	}
	if (!isset($_POST['cmx_scanner_zuordnung_nonce']) || !\wp_verify_nonce((string) $_POST['cmx_scanner_zuordnung_nonce'], 'cmx_scanner_zuordnung_save')) {
		return;
	}
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (\wp_is_post_revision($post_id)) {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

	$type = cmx_scanner_get_requested_zuordnung_type($post_id);

	if ($type === '') {
		\delete_post_meta($post_id, CMX_SCANNER_ZUORDNUNG_TYP_META);
	} else {
		\update_post_meta($post_id, CMX_SCANNER_ZUORDNUNG_TYP_META, $type);
	}

	$rel_meta_keys = cmx_scanner_get_rel_meta_keys();
	foreach ($rel_meta_keys as $key => $meta_key) {
		if ($key !== $type) {
			\delete_post_meta($post_id, $meta_key);
		}
	}
}, 10, 2);
