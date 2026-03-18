<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_SCANNER_ZUORDNUNG_TYP_META = '_cmx_scanner_zuordnung_typ';
const CMX_SCANNER_ZUORDNUNG_TYPES = [
	'kontakte'  => 'Kontakte',
	'artikel'   => 'Artikel',
	'dokumente' => 'Dokumente',
	'projekte'  => 'Projekte',
	'belege'    => 'Belege',
];

function cmx_scanner_default_zuordnung_type(): string {
	if (\array_key_exists('belege', CMX_SCANNER_ZUORDNUNG_TYPES)) {
		return 'belege';
	}
	$keys = \array_keys(CMX_SCANNER_ZUORDNUNG_TYPES);
	return isset($keys[0]) ? (string) $keys[0] : '';
}

function cmx_scanner_cpt_slug(): string {
	return basename(__DIR__);
}

function cmx_scanner_get_selected_zuordnung_type(int $post_id): string {
	if ($post_id <= 0) {
		return cmx_scanner_default_zuordnung_type();
	}
	$type = (string) \get_post_meta($post_id, CMX_SCANNER_ZUORDNUNG_TYP_META, true);
	if ($type === '0') {
		return '';
	}
	return \array_key_exists($type, CMX_SCANNER_ZUORDNUNG_TYPES) ? $type : cmx_scanner_default_zuordnung_type();
}

function cmx_scanner_get_requested_zuordnung_type(int $post_id = 0): string {
	$type = isset($_POST['cmx_scanner_zuordnung_typ'])
		? \sanitize_key((string) \wp_unslash($_POST['cmx_scanner_zuordnung_typ']))
		: '';

	if ($type === '0') {
		return '';
	}

	if ($type === '' && $post_id > 0) {
		return cmx_scanner_get_selected_zuordnung_type($post_id);
	}

	if (\array_key_exists($type, CMX_SCANNER_ZUORDNUNG_TYPES)) {
		return $type;
	}

	return $post_id > 0 ? cmx_scanner_get_selected_zuordnung_type($post_id) : cmx_scanner_default_zuordnung_type();
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

function cmx_scanner_normalize_relation_ids($value): array {
	if (\is_scalar($value) || $value === null) {
		$value = [$value];
	}

	$ids = [];
	foreach ((array) $value as $item) {
		$id = (int) $item;
		if ($id > 0) {
			$ids[] = $id;
		}
	}
	return \array_values(\array_unique($ids));
}

function cmx_scanner_get_relation_ids(int $post_id, string $meta_key): array {
	if ($post_id <= 0 || $meta_key === '') {
		return [];
	}
	return cmx_scanner_normalize_relation_ids(\get_post_meta($post_id, $meta_key, true));
}

function cmx_scanner_store_relation_ids(int $post_id, string $meta_key, array $ids): void {
	if ($post_id <= 0 || $meta_key === '') {
		return;
	}
	$ids = cmx_scanner_normalize_relation_ids($ids);
	if (empty($ids)) {
		\delete_post_meta($post_id, $meta_key);
		return;
	}
	if (\count($ids) === 1) {
		\update_post_meta($post_id, $meta_key, (int) $ids[0]);
		return;
	}
	\update_post_meta($post_id, $meta_key, $ids);
}

function cmx_scanner_has_posted_relation_value(string $meta_key): bool {
	return $meta_key !== '' && \array_key_exists($meta_key, $_POST);
}

function cmx_scanner_get_posted_relation_ids(string $meta_key): array {
	if (!cmx_scanner_has_posted_relation_value($meta_key)) {
		return [];
	}
	return cmx_scanner_normalize_relation_ids($_POST[$meta_key]);
}

function cmx_scanner_posted_relation_has_zero(string $meta_key): bool {
	if (!cmx_scanner_has_posted_relation_value($meta_key)) {
		return false;
	}

	$raw_values = $_POST[$meta_key];
	if (\is_scalar($raw_values) || $raw_values === null) {
		$raw_values = [$raw_values];
	}

	foreach ((array) $raw_values as $raw) {
		$text = \trim((string) $raw);
		if ($text === '0') {
			return true;
		}
	}

	return false;
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

function cmx_scanner_beleg_contact_option_label(int $beleg_id): string {
	$label = \trim((string) \get_post_meta($beleg_id, '_cmx_beleg_kontakt_label', true));
	if ($label !== '') {
		return $label;
	}

	$kontakt_id = (int) \get_post_meta($beleg_id, '_cmx_beleg_kontakt_id', true);
	if ($kontakt_id > 0) {
		$kontakt_title = \trim((string) \get_the_title($kontakt_id));
		if ($kontakt_title !== '') {
			return $kontakt_title;
		}
	}

	return '(kein Kontakt)';
}

function cmx_scanner_beleg_amount_option_label(int $beleg_id): string {
	$total = null;
	if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
		$calc = (array) cmxbu_get_beleg_positionen_calc($beleg_id);
		if (isset($calc['total'])) {
			$total = (float) $calc['total'];
		}
	}

	if ($total === null) {
		$override_raw = \trim((string) \get_post_meta($beleg_id, '_cmx_beleg_summe_override', true));
		if ($override_raw !== '') {
			if (\function_exists(__NAMESPACE__ . '\\cmx_parse_number')) {
				$total = (float) cmx_parse_number($override_raw);
			} else {
				$total = (float) \str_replace(',', '.', $override_raw);
			}
		}
	}

	if ($total === null) {
		return '-';
	}

	$formatted = \function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')
		? cmx_format_swiss_number($total, 2)
		: \number_format($total, 2, '.', "'");

	return \trim($formatted);
}

function cmx_scanner_text_length(string $value): int {
	return \function_exists('mb_strlen') ? (int) \mb_strlen($value, 'UTF-8') : (int) \strlen($value);
}

function cmx_scanner_text_substr(string $value, int $start, int $length): string {
	return \function_exists('mb_substr')
		? (string) \mb_substr($value, $start, $length, 'UTF-8')
		: (string) \substr($value, $start, $length);
}

function cmx_scanner_fixed_width_cell(string $text, int $width, bool $align_right = false): string {
	$text = (string) \preg_replace('~\\s+~', ' ', \trim($text));
	if ($text === '') {
		$text = '-';
	}

	$len = cmx_scanner_text_length($text);
	if ($len > $width) {
		if ($width <= 3) {
			$text = cmx_scanner_text_substr($text, 0, $width);
		} else {
			$text = cmx_scanner_text_substr($text, 0, $width - 3) . '...';
		}
		$len = cmx_scanner_text_length($text);
	}

	if ($len < $width) {
		$pad = \str_repeat("\u{2007}", $width - $len);
		$text = $align_right ? ($pad . $text) : ($text . $pad);
	}

	return $text;
}

function cmx_scanner_beleg_two_col_option_label(int $beleg_id): string {
	$contact = cmx_scanner_fixed_width_cell((string) cmx_scanner_beleg_contact_option_label($beleg_id), 20, false);
	$amount = cmx_scanner_fixed_width_cell((string) cmx_scanner_beleg_amount_option_label($beleg_id), 10, true);
	return $contact . '|' . $amount;
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

		if ($target_post_type === 'belege') {
			$options[] = [
				'id'      => $id,
				'label'   => cmx_scanner_beleg_two_col_option_label($id),
				'tooltip' => 'Beleg-ID: ' . $id,
			];
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
		if ($target_post_type === 'artikel' && \function_exists(__NAMESPACE__ . '\\cmx_artikel_search_variant_entries')) {
			$seen_variant_labels = [];
			foreach ((array) cmx_artikel_search_variant_entries($id, '') as $entry) {
				if (!\is_array($entry)) {
					continue;
				}
				$variant_label = \function_exists(__NAMESPACE__ . '\\cmx_artikel_search_variant_label')
					? (string) cmx_artikel_search_variant_label($entry)
					: '';
				if ($variant_label === '') {
					continue;
				}
				$variant_key = \function_exists('mb_strtolower')
					? (string) \mb_strtolower($variant_label, 'UTF-8')
					: (string) \strtolower($variant_label);
				if ($variant_key === '' || isset($seen_variant_labels[$variant_key])) {
					continue;
				}
				$seen_variant_labels[$variant_key] = true;
				$options[] = [
					'id'      => $id,
					'post_id' => $id,
					'value'   => $id . '__variant_' . (int) ($entry['index'] ?? \count($seen_variant_labels)),
					'label'   => $variant_label,
				];
			}
		}
	}
	return $options;
}

function cmx_scanner_render_relation_select_box(\WP_Post $post, string $target_type, string $meta_key, string $nonce_action, string $nonce_name, string $empty_label = 'Kein Eintrag', bool $allow_multiple = false): void {
	if ($post->post_type !== cmx_scanner_cpt_slug()) {
		return;
	}

	\wp_nonce_field($nonce_action, $nonce_name);
	$current_ids = cmx_scanner_get_relation_ids((int) $post->ID, $meta_key);
	$has_current = !empty($current_ids);
	$options = cmx_scanner_fetch_relation_options($target_type);
	$id_suffix = \preg_replace('~[^a-z0-9_]+~', '_', \strtolower($target_type . '_' . $meta_key));
	$select_id = 'cmx_scanner_rel_select_' . $id_suffix;
	$search_id = 'cmx_scanner_rel_search_' . $id_suffix;
	$results_id = 'cmx_scanner_rel_results_' . $id_suffix;
	$selected_id = 'cmx_scanner_rel_selected_' . $id_suffix;
	$ui_id = 'cmx_scanner_rel_ui_' . $id_suffix;
	$touched_id = 'cmx_scanner_rel_touched_' . $id_suffix;
	$touched_name = 'cmx_scanner_rel_touched[' . $meta_key . ']';
	$select_name = $allow_multiple ? ($meta_key . '[]') : $meta_key;
	$multiple_attr = $allow_multiple ? ' multiple data-cmx-multiple="1"' : '';
	$target_label = (string) (CMX_SCANNER_ZUORDNUNG_TYPES[$target_type] ?? \ucfirst($target_type));
	$placeholder = $target_label . ' suchen...';
	$is_belege = ($target_type === 'belege');
	$ui_classes = 'cmx-scanner-rel-ui' . ($is_belege ? ' cmx-scanner-rel-ui--belege' : '');
	$metabox_ids = cmx_scanner_get_relation_metabox_ids();
	$metabox_id = (string) ($metabox_ids[$target_type] ?? '');
	$metabox_id_attr = \esc_attr($metabox_id);
	$ui_id_attr = \esc_attr($ui_id);
	$edit_prefix_json = \wp_json_encode(\admin_url('post.php?post='));

	echo '<label for="' . \esc_attr($search_id) . '" class="screen-reader-text">Suchen</label>';
	echo '<style>
	' . ($metabox_id_attr !== '' ? ('#' . $metabox_id_attr . ',#' . $metabox_id_attr . ' .inside,') : '') . '#' . $ui_id_attr . '{position:relative;overflow:visible}
	#' . $ui_id_attr . ' .cmx-scanner-rel-suggest{position:relative;overflow:visible}
	#' . $ui_id_attr . ' .cmx-scanner-rel-results{
		position:absolute;
		z-index:100002;
		left:0;
		right:0;
		max-height:240px;
		overflow:auto;
		margin:2px 0 0;
		padding:0;
		border:1px solid #ccd0d4;
		border-radius:4px;
		background:#fff;
		box-shadow:0 10px 24px rgba(0,0,0,.10);
		list-style:none;
	}
	#' . $ui_id_attr . ' .cmx-scanner-rel-results li{margin:0;padding:6px 8px;cursor:pointer}
	#' . $ui_id_attr . ' .cmx-scanner-rel-results li.active,
	#' . $ui_id_attr . ' .cmx-scanner-rel-results li:hover{background:#e5f3ff}
	#' . $ui_id_attr . ' .cmx-scanner-rel-results li.cmx-scanner-rel-results-empty{color:#646970;cursor:default}
	#' . $ui_id_attr . ' .cmx-scanner-rel-selected{margin:8px 0 0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px}
	#' . $ui_id_attr . ' .cmx-scanner-rel-selected li{display:flex;align-items:flex-start;justify-content:space-between;gap:8px}
	#' . $ui_id_attr . ' .cmx-scanner-rel-selected-main{min-width:0;flex:1 1 auto}
	#' . $ui_id_attr . ' .cmx-scanner-rel-selected-main a{display:block;text-decoration:none}
	#' . $ui_id_attr . ' .cmx-scanner-rel-remove{line-height:1}
	#' . $ui_id_attr . '.cmx-scanner-rel-ui--belege .cmx-scanner-rel-results,
	#' . $ui_id_attr . '.cmx-scanner-rel-ui--belege .cmx-scanner-rel-selected-main a{
		font-family:Consolas,Monaco,Courier,monospace;
		font-size:12px;
		white-space:pre;
		font-variant-numeric:tabular-nums;
		letter-spacing:0;
	}
	</style>';
	echo '<input type="hidden" id="' . \esc_attr($touched_id) . '" name="' . \esc_attr($touched_name) . '" value="0" />';
	echo '<div id="' . \esc_attr($ui_id) . '" class="' . \esc_attr($ui_classes) . '">';
	echo '<div class="cmx-scanner-rel-suggest">';
	echo '<input type="search" id="' . \esc_attr($search_id) . '" class="widefat cmx-scanner-rel-search" data-target-select="' . \esc_attr($select_id) . '" data-target-results="' . \esc_attr($results_id) . '" data-target-selected="' . \esc_attr($selected_id) . '" placeholder="' . \esc_attr($placeholder) . '" autocomplete="off" aria-label="' . \esc_attr($placeholder) . '">';
	echo '<ul id="' . \esc_attr($results_id) . '" class="cmx-scanner-rel-results" style="display:none"></ul>';
	echo '</div>';
	echo '<select id="' . \esc_attr($select_id) . '" class="cmx-scanner-rel-select" data-target-touched="' . \esc_attr($touched_id) . '" data-cmx-no-selection="' . ($has_current ? '0' : '1') . '" name="' . \esc_attr($select_name) . '" style="display:none"' . $multiple_attr . '>';
	echo '<option value="0"' . ($has_current ? '' : ' selected') . '>' . \esc_html($empty_label) . '</option>';
	foreach ($options as $opt) {
		$id = (int) ($opt['id'] ?? 0);
		$option_value = isset($opt['value']) ? (string) $opt['value'] : (string) $id;
		$option_post_id = isset($opt['post_id']) ? (int) $opt['post_id'] : $id;
		$selected = ($option_value === (string) $id && \in_array($id, $current_ids, true)) ? ' selected' : '';
		$tooltip = isset($opt['tooltip']) ? \trim((string) $opt['tooltip']) : '';
		$title_attr = $tooltip !== '' ? ' title="' . \esc_attr($tooltip) . '"' : '';
		echo '<option value="' . \esc_attr($option_value) . '" data-post-id="' . \esc_attr((string) $option_post_id) . '"' . $selected . $title_attr . '>' . \esc_html((string) $opt['label']) . '</option>';
	}
	echo '</select>';
	echo '<ul id="' . \esc_attr($selected_id) . '" class="cmx-scanner-rel-selected"></ul>';
	echo '</div>';

	if (empty($options)) {
		echo '<p style="margin:8px 0 0;"><em>Keine Datensätze gefunden.</em></p>';
	}

	static $printed_search_js = false;
	if ($printed_search_js) {
		return;
	}
	$printed_search_js = true;

	$script = <<<HTML
<script>
	(function(){
		if (window.cmxScannerRelSearchInit) return;
		window.cmxScannerRelSearchInit = true;
		var editPrefix = {$edit_prefix_json};

		function escHtml(str){
			return String(str || "").replace(/[&<>"']/g, function(c){
				if (c === "&") return "&amp;";
				if (c === "<") return "&lt;";
				if (c === ">") return "&gt;";
				if (c.charCodeAt(0) === 34) return "&quot;";
				return "&#039;";
			});
		}

		function setTouchState(select, state){
			if (!select) return;
			var touchedId = select.getAttribute("data-target-touched") || "";
			if (!touchedId) return;
			var touched = document.getElementById(touchedId);
			if (!touched) return;
			touched.value = String(state || "0");
		}

		function syncEmptyOption(select){
			if (!select) return;
			var firstOption = select.options.length > 0 ? select.options[0] : null;
			if (!firstOption || String(firstOption.value || "") !== "0") return;
			var hasPositiveSelection = false;
			for (var i = 1; i < select.options.length; i++) {
				if (select.options[i].selected) {
					hasPositiveSelection = true;
					break;
				}
			}
			firstOption.selected = !hasPositiveSelection;
		}

		function getSelectedOptions(select){
			var out = [];
			if (!select) return out;
			for (var i = 1; i < select.options.length; i++) {
				var opt = select.options[i];
				if (!opt.selected) continue;
				out.push({
					id: String(opt.value || ""),
					postId: String(opt.getAttribute("data-post-id") || opt.value || ""),
					label: String(opt.textContent || opt.innerText || ""),
					title: String(opt.getAttribute("title") || "")
				});
			}
			return out;
		}

		function findOption(select, id){
			if (!select) return null;
			var idStr = String(id || "");
			for (var i = 1; i < select.options.length; i++) {
				if (String(select.options[i].value || "") === idStr) {
					return select.options[i];
				}
			}
			return null;
		}

		function closeResults(root){
			if (!root) return;
			var results = root.querySelector(".cmx-scanner-rel-results");
			if (!results) return;
			results.style.display = "none";
			results.innerHTML = "";
			root._cmxItems = [];
			root._cmxActive = -1;
		}

		function resultItems(root){
			var results = root ? root.querySelector(".cmx-scanner-rel-results") : null;
			return results ? Array.prototype.slice.call(results.querySelectorAll("li[data-id]")) : [];
		}

		function setActive(root, next){
			var items = resultItems(root);
			if (!items.length) {
				root._cmxActive = -1;
				return;
			}
			if (next < 0) next = items.length - 1;
			if (next >= items.length) next = 0;
			root._cmxActive = next;
			items.forEach(function(item, idx){
				item.classList.toggle("active", idx === next);
				if (idx === next) {
					try { item.scrollIntoView({ block: "nearest" }); } catch (err) {}
				}
			});
		}

		function renderSelected(root){
			var select = root ? root.querySelector(".cmx-scanner-rel-select") : null;
			var list = root ? root.querySelector(".cmx-scanner-rel-selected") : null;
			if (!select || !list) return;
			syncEmptyOption(select);
			var items = getSelectedOptions(select);
			if (!items.length) {
				list.innerHTML = "";
				return;
			}
			list.innerHTML = items.map(function(item){
				var titleAttr = item.title ? ' title="' + escHtml(item.title) + '"' : "";
				return '<li data-id="' + escHtml(item.id) + '"><div class="cmx-scanner-rel-selected-main"><a href="' + escHtml(editPrefix + encodeURIComponent(item.postId || item.id) + "&action=edit") + '" target="_blank" rel="noopener noreferrer"' + titleAttr + '>' + escHtml(item.label) + '</a></div><button type="button" class="button-link-delete cmx-scanner-rel-remove" data-id="' + escHtml(item.id) + '" aria-label="Auswahl entfernen"><span class="dashicons dashicons-trash" style="color:#d63638;"></span></button></li>';
			}).join("");
		}

		function buildMatches(select, term){
			var items = [];
			if (!select) return items;
			var normalizedTerm = String(term || "").toLowerCase().trim();
			for (var i = 1; i < select.options.length; i++) {
				var opt = select.options[i];
				if (opt.selected) continue;
				var label = String(opt.textContent || opt.innerText || "");
				if (normalizedTerm !== "" && label.toLowerCase().indexOf(normalizedTerm) === -1) {
					continue;
				}
				items.push({
					id: String(opt.value || ""),
					label: label,
					title: String(opt.getAttribute("title") || "")
				});
			}
			return items;
		}

		function renderResults(root, term){
			var select = root ? root.querySelector(".cmx-scanner-rel-select") : null;
			var results = root ? root.querySelector(".cmx-scanner-rel-results") : null;
			if (!select || !results) return;
			var items = buildMatches(select, term);
			root._cmxItems = items;
			root._cmxActive = -1;
			if (!items.length) {
				results.innerHTML = '<li class="cmx-scanner-rel-results-empty">Keine Treffer.</li>';
				results.style.display = "block";
				return;
			}
			results.innerHTML = items.map(function(item, idx){
				var titleAttr = item.title ? ' title="' + escHtml(item.title) + '"' : "";
				return '<li data-id="' + escHtml(item.id) + '" data-index="' + idx + '"' + titleAttr + '>' + escHtml(item.label) + '</li>';
			}).join("");
			results.style.display = "block";
			setActive(root, 0);
		}

		function chooseItem(root, id){
			var select = root ? root.querySelector(".cmx-scanner-rel-select") : null;
			var input = root ? root.querySelector(".cmx-scanner-rel-search") : null;
			if (!select || !input) return;
			var option = findOption(select, id);
			if (!option) return;
			if (!select.hasAttribute("multiple")) {
				for (var i = 1; i < select.options.length; i++) {
					select.options[i].selected = false;
				}
			}
			option.selected = true;
			syncEmptyOption(select);
			setTouchState(select, "1");
			renderSelected(root);
			closeResults(root);
			select.dispatchEvent(new Event("change", { bubbles: true }));
			input.value = "";
			input.focus();
		}

		function removeItem(root, id){
			var select = root ? root.querySelector(".cmx-scanner-rel-select") : null;
			if (!select) return;
			var option = findOption(select, id);
			if (!option) return;
			option.selected = false;
			syncEmptyOption(select);
			var remaining = getSelectedOptions(select).length;
			setTouchState(select, remaining > 0 ? "1" : "2");
			renderSelected(root);
			select.dispatchEvent(new Event("change", { bubbles: true }));
		}

		function initRoot(root){
			if (!root || root.dataset.cmxBound === "1") return;
			root.dataset.cmxBound = "1";
			var input = root.querySelector(".cmx-scanner-rel-search");
			var results = root.querySelector(".cmx-scanner-rel-results");
			var selected = root.querySelector(".cmx-scanner-rel-selected");
			var select = root.querySelector(".cmx-scanner-rel-select");
			if (!input || !results || !selected || !select) return;
			root._cmxItems = [];
			root._cmxActive = -1;
			syncEmptyOption(select);
			renderSelected(root);

			input.addEventListener("input", function(){
				renderResults(root, (input.value || "").trim());
			});
			input.addEventListener("focus", function(){
				renderResults(root, (input.value || "").trim());
			});
			input.addEventListener("click", function(){
				renderResults(root, (input.value || "").trim());
			});
			input.addEventListener("keydown", function(e){
				var isOpen = results.style.display === "block";
				if ((e.key === "ArrowDown" || e.key === "ArrowUp") && !isOpen) {
					renderResults(root, (input.value || "").trim());
					isOpen = true;
				}
				if (e.key === "ArrowDown") {
					e.preventDefault();
					setActive(root, (root._cmxActive || 0) + 1);
					return;
				}
				if (e.key === "ArrowUp") {
					e.preventDefault();
					setActive(root, (typeof root._cmxActive === "number" ? root._cmxActive : 0) - 1);
					return;
				}
				if (e.key === "Enter") {
					var items = Array.isArray(root._cmxItems) ? root._cmxItems : [];
					if (root._cmxActive > -1 && items[root._cmxActive]) {
						e.preventDefault();
						chooseItem(root, items[root._cmxActive].id);
					}
					return;
				}
				if (e.key === "Escape") {
					e.preventDefault();
					closeResults(root);
				}
			});

			results.addEventListener("mousedown", function(e){
				var item = e.target && e.target.closest ? e.target.closest("li[data-id]") : null;
				if (!item) return;
				e.preventDefault();
				chooseItem(root, item.getAttribute("data-id") || "");
			});
			results.addEventListener("mousemove", function(e){
				var item = e.target && e.target.closest ? e.target.closest("li[data-id]") : null;
				if (!item) return;
				var items = resultItems(root);
				var idx = items.indexOf(item);
				if (idx > -1) setActive(root, idx);
			});
			selected.addEventListener("click", function(e){
				var btn = e.target && e.target.closest ? e.target.closest(".cmx-scanner-rel-remove") : null;
				if (!btn) return;
				e.preventDefault();
				removeItem(root, btn.getAttribute("data-id") || "");
			});
			select.addEventListener("change", function(){
				renderSelected(root);
			});
			document.addEventListener("click", function(e){
				if (root.contains(e.target)) return;
				closeResults(root);
			});
		}

		var boot = function(){
			var roots = document.querySelectorAll(".cmx-scanner-rel-ui");
			for (var i = 0; i < roots.length; i++) {
				initRoot(roots[i]);
			}
		};
		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", boot);
		} else {
			boot();
		}
	})();
</script>
HTML;
	echo $script;
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
	$selected = cmx_scanner_get_selected_zuordnung_type($post_id);
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
	echo '<option value="0" ' . \selected($current, '', false) . '>&mdash; auswählen &mdash;</option>';
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
		\update_post_meta($post_id, CMX_SCANNER_ZUORDNUNG_TYP_META, '0');
		foreach (cmx_scanner_get_rel_meta_keys() as $relation_meta_key) {
			if (!\is_string($relation_meta_key) || $relation_meta_key === '') {
				continue;
			}
			\delete_post_meta($post_id, $relation_meta_key);
		}
		if (\defined(__NAMESPACE__ . '\\CMX_SCANNER_REL_BELEGE_AS_DOC_META')) {
			\delete_post_meta($post_id, (string) \constant(__NAMESPACE__ . '\\CMX_SCANNER_REL_BELEGE_AS_DOC_META'));
		}
	} else {
		\update_post_meta($post_id, CMX_SCANNER_ZUORDNUNG_TYP_META, $type);
	}
}, 10, 2);
