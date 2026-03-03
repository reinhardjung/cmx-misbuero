<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_DOK_REL_META = [
	'artikel'    => 'cmx_dokumente_artikel',
	'kontakte'   => 'cmx_dokumente_kunden',
	'projekte'   => 'cmx_dokumente_projekte',
	'kassenbuch' => 'cmx_dokumente_kassenbuch',
	'belege'     => 'cmx_dokumente_belege',
];
const CMX_DOK_ZUORDNUNG_TYP_META = '_cmx_dokumente_zuordnung_typ';
const CMX_DOK_ZUORDNUNG_TYPES = [
	'kontakte' => 'Kontakte',
	'artikel'  => 'Artikel',
	'projekte' => 'Projekte',
	'belege'   => 'Belege',
];

function cmx_dok_default_zuordnung_type(): string {
	if (\array_key_exists('belege', CMX_DOK_ZUORDNUNG_TYPES)) {
		return 'belege';
	}
	$keys = \array_keys(CMX_DOK_ZUORDNUNG_TYPES);
	return isset($keys[0]) ? (string) $keys[0] : '';
}

function cmx_dok_cpt_slug(): string {
	return basename(__DIR__);
}

function cmx_dok_rel_ui_map(): array {
	return [
		'artikel' => [
			'label'      => 'Artikel',
			'empty'      => 'Kein Artikel',
			'meta'       => CMX_DOK_REL_META['artikel'],
			'post_types' => ['artikel'],
		],
		'kontakte' => [
			'label'      => 'Kontakt',
			'empty'      => 'Kein Kontakt',
			'meta'       => CMX_DOK_REL_META['kontakte'],
			'post_types' => ['kontakte', 'kontakt'],
		],
		'projekte' => [
			'label'      => 'Projekt',
			'empty'      => 'Kein Projekt',
			'meta'       => CMX_DOK_REL_META['projekte'],
			'post_types' => ['projekte'],
		],
		'belege' => [
			'label'      => 'Beleg',
			'empty'      => 'Kein Beleg',
			'meta'       => CMX_DOK_REL_META['belege'],
			'post_types' => ['belege'],
		],
	];
}

function cmx_dok_relation_metabox_ids(): array {
	return [
		'artikel'  => 'cmx_dok_rel_artikel',
		'kontakte' => 'cmx_dok_rel_kontakte',
		'projekte' => 'cmx_dok_rel_projekte',
		'belege'   => 'cmx_dok_rel_belege',
	];
}

function cmx_dok_get_rel_meta_keys(): array {
	$map = [];
	foreach (cmx_dok_rel_ui_map() as $type => $cfg) {
		$meta_key = (string) ($cfg['meta'] ?? '');
		if ($meta_key === '') {
			continue;
		}
		$map[$type] = $meta_key;
	}
	return $map;
}

function cmx_dok_get_selected_zuordnung_type(int $post_id): string {
	if ($post_id <= 0) {
		return cmx_dok_default_zuordnung_type();
	}
	$type = (string) \get_post_meta($post_id, CMX_DOK_ZUORDNUNG_TYP_META, true);
	if (\array_key_exists($type, CMX_DOK_ZUORDNUNG_TYPES)) {
		return $type;
	}
	return cmx_dok_default_zuordnung_type();
}

function cmx_dok_get_requested_zuordnung_type(int $post_id = 0): string {
	$type = isset($_POST['cmx_dok_zuordnung_typ'])
		? \sanitize_key((string) \wp_unslash($_POST['cmx_dok_zuordnung_typ']))
		: '';
	if ($type === '' && $post_id > 0) {
		$type = cmx_dok_get_selected_zuordnung_type($post_id);
	}
	return \array_key_exists($type, CMX_DOK_ZUORDNUNG_TYPES) ? $type : cmx_dok_default_zuordnung_type();
}

function cmx_dok_resolve_target_post_type(string $target_type): string {
	$target_type = \sanitize_key($target_type);
	if ($target_type === 'kontakte') {
		if (\post_type_exists('kontakte')) {
			return 'kontakte';
		}
		if (\post_type_exists('kontakt')) {
			return 'kontakt';
		}
		return '';
	}
	return \post_type_exists($target_type) ? $target_type : '';
}

function cmx_dok_current_user_can_create_post_type(string $post_type): bool {
	$obj = \get_post_type_object($post_type);
	if (!$obj || !isset($obj->cap) || !\is_object($obj->cap)) {
		return false;
	}

	$create_cap = isset($obj->cap->create_posts) && \is_string($obj->cap->create_posts) && $obj->cap->create_posts !== ''
		? $obj->cap->create_posts
		: (isset($obj->cap->edit_posts) && \is_string($obj->cap->edit_posts) ? $obj->cap->edit_posts : 'edit_posts');

	return \current_user_can($create_cap);
}

function cmx_dok_default_target_title(int $doc_id, string $target_type): string {
	$title = \trim((string) \get_the_title($doc_id));
	if ($title !== '') {
		return $title;
	}

	$labels = [
		'kontakte' => 'Neuer Kontakt',
		'artikel'  => 'Neuer Artikel',
		'projekte' => 'Neues Projekt',
		'belege'   => 'Neuer Beleg',
	];

	return (string) ($labels[$target_type] ?? 'Neuer Eintrag');
}

function cmx_dok_create_related_entry(int $doc_id, string $target_type): int {
	$post_type = cmx_dok_resolve_target_post_type($target_type);
	if ($doc_id <= 0 || $post_type === '') {
		return 0;
	}
	if (!cmx_dok_current_user_can_create_post_type($post_type)) {
		return 0;
	}

	$title = cmx_dok_default_target_title($doc_id, $target_type);
	$inserted = \wp_insert_post([
		'post_type'   => $post_type,
		'post_title'  => $title,
		'post_status' => 'draft',
	], true);
	if (\is_wp_error($inserted) || (int) $inserted <= 0) {
		return 0;
	}

	return (int) $inserted;
}

function cmx_dok_mark_redirect_to_target_edit_after_save(int $doc_id, int $target_id): void {
	if ($doc_id <= 0 || $target_id <= 0) {
		return;
	}

	if (!isset($GLOBALS['cmx_dok_redirect_to_target_map']) || !\is_array($GLOBALS['cmx_dok_redirect_to_target_map'])) {
		$GLOBALS['cmx_dok_redirect_to_target_map'] = [];
	}
	$GLOBALS['cmx_dok_redirect_to_target_map'][(int) $doc_id] = (int) $target_id;

	static $filter_added = false;
	if (!$filter_added) {
		$filter_added = true;
		\add_filter('redirect_post_location', __NAMESPACE__ . '\\cmx_dok_redirect_to_target_edit_after_save', 99, 2);
	}
}

function cmx_dok_redirect_to_target_edit_after_save(string $location, int $post_id): string {
	$map = isset($GLOBALS['cmx_dok_redirect_to_target_map']) && \is_array($GLOBALS['cmx_dok_redirect_to_target_map'])
		? $GLOBALS['cmx_dok_redirect_to_target_map']
		: [];

	$target_id = isset($map[$post_id]) ? (int) $map[$post_id] : 0;
	if ($target_id > 0) {
		return (string) \admin_url('post.php?post=' . $target_id . '&action=edit');
	}

	return $location;
}

function cmx_dok_uploads_meta_key(): string {
	return \defined(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
		? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
		: '_cmx_dokumente_uploads';
}

function cmx_dok_int_list($value): array {
	$out = [];
	foreach ((array) $value as $item) {
		$id = (int) $item;
		if ($id > 0) {
			$out[] = $id;
		}
	}
	return \array_values(\array_unique($out));
}

function cmx_dok_cleanup_legacy_kassenbuch_links(): int {
	$kassen_meta = (string) (CMX_DOK_REL_META['kassenbuch'] ?? 'cmx_dokumente_kassenbuch');
	$legacy_meta = 'cmx_dokumente_buchhaltung';

	$dok_ids = \get_posts([
		'post_type'      => cmx_dok_cpt_slug(),
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => [
			'relation' => 'OR',
			['key' => $kassen_meta, 'compare' => 'EXISTS'],
			['key' => $legacy_meta, 'compare' => 'EXISTS'],
		],
	]);

	if (empty($dok_ids)) {
		return 0;
	}

	$uploads_meta_key = cmx_dok_uploads_meta_key();
	$cleaned = 0;

	foreach ($dok_ids as $dok_id) {
		$dok_id = (int) $dok_id;
		if ($dok_id <= 0) {
			continue;
		}

		$target_ids = [];
		$target_ids = \array_merge(
			$target_ids,
			cmx_dok_int_list(\get_post_meta($dok_id, $kassen_meta, true)),
			cmx_dok_int_list(\get_post_meta($dok_id, $legacy_meta, true))
		);
		$target_ids = \array_values(\array_unique($target_ids));

		foreach ($target_ids as $target_id) {
			$existing = cmx_dok_int_list(\get_post_meta((int) $target_id, $uploads_meta_key, true));
			if (empty($existing)) {
				continue;
			}
			$updated = \array_values(\array_diff($existing, [$dok_id]));
			if ($updated === $existing) {
				continue;
			}
			if (empty($updated)) {
				\delete_post_meta((int) $target_id, $uploads_meta_key);
			} else {
				\update_post_meta((int) $target_id, $uploads_meta_key, $updated);
			}
		}

		\delete_post_meta($dok_id, $kassen_meta);
		\delete_post_meta($dok_id, $legacy_meta);
		$cleaned++;
	}

	return $cleaned;
}

\add_action('admin_init', function (): void {
	$cleanup_done_key = 'cmx_dok_cleanup_kassenbuch_links_v1';
	if ((string) \get_option($cleanup_done_key, '') === '1') {
		return;
	}
	cmx_dok_cleanup_legacy_kassenbuch_links();
	\update_option($cleanup_done_key, '1', false);
}, 20);

function cmx_dok_beleg_contact_option_label(int $beleg_id): string {
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

function cmx_dok_beleg_amount_option_label(int $beleg_id): string {
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

function cmx_dok_text_length(string $value): int {
	return \function_exists('mb_strlen') ? (int) \mb_strlen($value, 'UTF-8') : (int) \strlen($value);
}

function cmx_dok_text_substr(string $value, int $start, int $length): string {
	return \function_exists('mb_substr')
		? (string) \mb_substr($value, $start, $length, 'UTF-8')
		: (string) \substr($value, $start, $length);
}

function cmx_dok_fixed_width_cell(string $text, int $width, bool $align_right = false): string {
	$text = (string) \preg_replace('~\\s+~', ' ', \trim($text));
	if ($text === '') {
		$text = '-';
	}

	$len = cmx_dok_text_length($text);
	if ($len > $width) {
		if ($width <= 3) {
			$text = cmx_dok_text_substr($text, 0, $width);
		} else {
			$text = cmx_dok_text_substr($text, 0, $width - 3) . '...';
		}
		$len = cmx_dok_text_length($text);
	}

	if ($len < $width) {
		$pad = \str_repeat("\u{2007}", $width - $len);
		$text = $align_right ? ($pad . $text) : ($text . $pad);
	}

	return $text;
}

function cmx_dok_beleg_two_col_option_label(int $beleg_id): string {
	$contact = cmx_dok_fixed_width_cell((string) cmx_dok_beleg_contact_option_label($beleg_id), 20, false);
	$amount = cmx_dok_fixed_width_cell((string) cmx_dok_beleg_amount_option_label($beleg_id), 10, true);
	return $contact . '|' . $amount;
}

function cmx_dok_fetch_relation_options(string $target_type, int $limit = 200): array {
	$cfg = cmx_dok_rel_ui_map()[$target_type] ?? null;
	if (!\is_array($cfg)) {
		return [];
	}

	$post_types = [];
	foreach ((array) ($cfg['post_types'] ?? []) as $post_type) {
		$post_type = \sanitize_key((string) $post_type);
		if ($post_type !== '' && \post_type_exists($post_type)) {
			$post_types[] = $post_type;
		}
	}
	$post_types = \array_values(\array_unique($post_types));
	if (empty($post_types)) {
		return [];
	}

	$ids = \get_posts([
		'post_type'      => $post_types,
		'posts_per_page' => $limit,
		'post_status'    => ['publish', 'draft', 'pending', 'private'],
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

		if ($target_type === 'belege') {
			$options[] = [
				'id'      => $id,
				'label'   => cmx_dok_beleg_two_col_option_label($id),
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
	}

	return $options;
}

function cmx_dok_render_relation_select_box(\WP_Post $post, string $target_type, string $meta_key, string $empty_label, bool $allow_multiple = false): void {
	if ($post->post_type !== cmx_dok_cpt_slug()) {
		return;
	}

	$current_ids = cmx_dok_int_list(\get_post_meta($post->ID, $meta_key, true));
	$has_current = !empty($current_ids);
	$options = cmx_dok_fetch_relation_options($target_type);
	$id_suffix = \preg_replace('~[^a-z0-9_]+~', '_', \strtolower($target_type . '_' . $meta_key));
	$select_id = 'cmx_dok_rel_select_' . $id_suffix;
	$search_id = 'cmx_dok_rel_search_' . $id_suffix;
	$nohit_id = 'cmx_dok_rel_nohit_' . $id_suffix;
	$touched_id = 'cmx_dok_rel_touched_' . $id_suffix;
	$touched_name = 'cmx_dok_rel_touched[' . $meta_key . ']';
	$select_name = $allow_multiple ? ($meta_key . '[]') : $meta_key;
	$multiple_attr = $allow_multiple ? ' multiple data-cmx-multiple="1"' : '';
	$select_style = 'width:100%;appearance:none;-webkit-appearance:none;-moz-appearance:none;background-image:none;box-sizing:border-box;';
	if ($target_type === 'belege') {
		$select_style .= 'font-family:Consolas,Monaco,Courier,monospace;font-size:12px;white-space:pre;font-variant-numeric:tabular-nums;letter-spacing:0;padding-right:24px;';
	}

	echo '<label for="' . \esc_attr($search_id) . '" class="screen-reader-text">Suchen</label>';
	echo '<input type="hidden" id="' . \esc_attr($touched_id) . '" name="' . \esc_attr($touched_name) . '" value="0" />';
	echo '<input type="search" id="' . \esc_attr($search_id) . '" class="cmx-dok-rel-search" data-target-select="' . \esc_attr($select_id) . '" data-target-nohit="' . \esc_attr($nohit_id) . '" placeholder="Suchen..." style="width:100%;margin:0 0 8px;" autocomplete="off" />';
	echo '<select id="' . \esc_attr($select_id) . '" class="cmx-dok-rel-select" data-target-touched="' . \esc_attr($touched_id) . '" data-cmx-no-selection="' . ($has_current ? '0' : '1') . '" name="' . \esc_attr($select_name) . '" style="' . \esc_attr($select_style) . '" size="10"' . $multiple_attr . '>';
	echo '<option value="0"' . ($has_current ? '' : ' selected') . '>' . \esc_html($empty_label) . '</option>';
	foreach ($options as $opt) {
		$id = (int) ($opt['id'] ?? 0);
		$selected = \in_array($id, $current_ids, true) ? ' selected' : '';
		$tooltip = isset($opt['tooltip']) ? \trim((string) $opt['tooltip']) : '';
		$title_attr = $tooltip !== '' ? ' title="' . \esc_attr($tooltip) . '"' : '';
		echo '<option value="' . \esc_attr((string) $id) . '"' . $selected . $title_attr . '>' . \esc_html((string) $opt['label']) . '</option>';
	}
	echo '</select>';
	echo '<p id="' . \esc_attr($nohit_id) . '" style="display:none;margin:8px 0 0;"><em>Keine Treffer.</em></p>';
	if ($allow_multiple) {
		echo '<p style="margin:8px 0 0;"><em>Mehrfachauswahl: Strg/Cmd gedrückt halten und mehrere Einträge wählen.</em></p>';
	}

	if (empty($options)) {
		echo '<p style="margin:8px 0 0;"><em>Keine Datensaetze gefunden.</em></p>';
	}

	static $printed_search_js = false;
	if ($printed_search_js) {
		return;
	}
	$printed_search_js = true;

	echo '<script>
		(function(){
			if (window.cmxDokRelSearchInit) return;
			window.cmxDokRelSearchInit = true;

			var setTouchState = function(select, state){
				if (!select) return;
				var touchedId = select.getAttribute("data-target-touched") || "";
				if (!touchedId) return;
				var touched = document.getElementById(touchedId);
				if (!touched) return;
				touched.value = String(state || "0");
			};

			var markTouched = function(select){
				setTouchState(select, "1");
			};

			var normalizeSelection = function(select){
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
				if (hasPositiveSelection && firstOption.selected) {
					firstOption.selected = false;
				}
			};

			var clearSelection = function(select){
				if (!select) return;
				for (var i = 0; i < select.options.length; i++) {
					select.options[i].selected = false;
				}
				select.selectedIndex = -1;
				var firstOption = select.options.length > 0 ? select.options[0] : null;
				if (firstOption && String(firstOption.value || "") === "0") {
					firstOption.selected = true;
				}
				setTouchState(select, "2");
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
				if (!t || !t.classList || !t.classList.contains("cmx-dok-rel-search")) return;
				filter(t);
			});

			document.addEventListener("change", function(e){
				var t = e.target;
				if (!t || !t.classList || !t.classList.contains("cmx-dok-rel-select")) return;
				normalizeSelection(t);
				markTouched(t);
			});

			document.addEventListener("keydown", function(e){
				var t = e.target;
				if (!t || !t.classList || !t.classList.contains("cmx-dok-rel-search")) return;
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
					normalizeSelection(select);
					break;
					}
				}
			});

			document.addEventListener("keydown", function(e){
				var t = e.target;
				if (!t || !t.classList || !t.classList.contains("cmx-dok-rel-select")) return;
				if (e.key !== "Escape") return;
				e.preventDefault();
				clearSelection(t);
				var searchId = t.id ? t.id.replace("cmx_dok_rel_select_", "cmx_dok_rel_search_") : "";
				var search = searchId ? document.getElementById(searchId) : null;
				if (search) {
					search.focus();
				}
			});

			var boot = function(){
				var selects = document.querySelectorAll(".cmx-dok-rel-select[data-cmx-no-selection=\'1\']");
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
				var inputs = document.querySelectorAll(".cmx-dok-rel-search");
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

function cmx_dok_render_relation_metabox(\WP_Post $post, string $target_type): void {
	$cfg = cmx_dok_rel_ui_map()[$target_type] ?? null;
	if (!\is_array($cfg)) {
		return;
	}
	$meta_key = (string) ($cfg['meta'] ?? '');
	if ($meta_key === '') {
		return;
	}

	$empty_label = (string) ($cfg['empty'] ?? 'Kein Eintrag');
	cmx_dok_render_relation_select_box($post, $target_type, $meta_key, $empty_label, true);
}

function cmx_dok_render_zuordnung_metabox(\WP_Post $post): void {
	\wp_nonce_field('cmx_dok_zuordnung_save', 'cmx_dok_zuordnung_nonce');
	$current = cmx_dok_get_selected_zuordnung_type((int) $post->ID);
	$metabox_map_json = \wp_json_encode(cmx_dok_relation_metabox_ids());

	echo '<label for="cmx_dok_zuordnung_typ" class="screen-reader-text">Zuordnung</label>';
	echo '<select id="cmx_dok_zuordnung_typ" name="cmx_dok_zuordnung_typ" style="width:100%;appearance:none;-webkit-appearance:none;-moz-appearance:none;background-image:none;">';
	foreach (CMX_DOK_ZUORDNUNG_TYPES as $key => $label) {
		echo '<option value="' . \esc_attr($key) . '" ' . \selected($current, $key, false) . '>' . \esc_html($label) . '</option>';
	}
	echo '</select>';

	if (\is_string($metabox_map_json) && $metabox_map_json !== '') {
		echo '<script>
		(function(){
			var init = function(){
				var select = document.getElementById("cmx_dok_zuordnung_typ");
				if (!select) return;
				var map = ' . $metabox_map_json . ';
				window.cmxDokToggleZuordnung = function(selected, focusSearch){
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
						var search = activeBox.querySelector(".cmx-dok-rel-search");
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
					window.cmxDokToggleZuordnung(select.value || "", true);
				});
				document.addEventListener("click", function(e){
					var trigger = e.target && e.target.closest ? e.target.closest(".postbox .handlediv, .postbox .hndle") : null;
					if (!trigger) return;
					setTimeout(function(){
						window.cmxDokToggleZuordnung(select.value || "", false);
					}, 0);
				});
				window.cmxDokToggleZuordnung(select.value || "", false);
				setTimeout(function(){
					window.cmxDokToggleZuordnung(select.value || "", false);
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

function cmx_dok_print_relation_metabox_hide_style(): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== cmx_dok_cpt_slug()) {
		return;
	}

	$ids = cmx_dok_relation_metabox_ids();
	if (empty($ids)) {
		return;
	}

	$post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
	$selected = cmx_dok_get_selected_zuordnung_type($post_id);
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
\add_action('admin_head-post.php', __NAMESPACE__ . '\\cmx_dok_print_relation_metabox_hide_style');
\add_action('admin_head-post-new.php', __NAMESPACE__ . '\\cmx_dok_print_relation_metabox_hide_style');

\add_action('add_meta_boxes', function (): void {
	$cpt = cmx_dok_cpt_slug();
	\add_meta_box(
		'cmx_dok_zuordnung',
		'Zuordnung',
		__NAMESPACE__ . '\\cmx_dok_render_zuordnung_metabox',
		$cpt,
		'side',
		'default'
	);

	$metabox_ids = cmx_dok_relation_metabox_ids();

	foreach (cmx_dok_rel_ui_map() as $type => $cfg) {
		$box_id = (string) ($metabox_ids[$type] ?? '');
		if ($box_id === '') {
			continue;
		}
		$label = (string) ($cfg['label'] ?? \ucfirst($type));
		\add_meta_box(
			$box_id,
			$label,
			function (\WP_Post $post) use ($type): void {
				cmx_dok_render_relation_metabox($post, (string) $type);
			},
			$cpt,
			'side',
			'default'
		);
	}
});

\add_action('save_post_' . basename(__DIR__), function (int $post_id, \WP_Post $post): void {
	if ($post->post_type !== cmx_dok_cpt_slug()) {
		return;
	}
	if (!isset($_POST['cmx_dok_zuordnung_nonce']) || !\wp_verify_nonce((string) $_POST['cmx_dok_zuordnung_nonce'], 'cmx_dok_zuordnung_save')) {
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

	$type = cmx_dok_get_requested_zuordnung_type($post_id);
	if ($type === '') {
		\delete_post_meta($post_id, CMX_DOK_ZUORDNUNG_TYP_META);
	} else {
		\update_post_meta($post_id, CMX_DOK_ZUORDNUNG_TYP_META, $type);
	}
	$ui_map = cmx_dok_rel_ui_map();
	$prev_map = [];
	$new_map = [];
	$create_on_empty_map = [];
	$touched_map = [];
	if (isset($_POST['cmx_dok_rel_touched']) && \is_array($_POST['cmx_dok_rel_touched'])) {
		$touched_map = (array) \wp_unslash($_POST['cmx_dok_rel_touched']);
	}

	foreach ($ui_map as $rel_type => $cfg) {
		$meta_key = (string) ($cfg['meta'] ?? '');
		if ($meta_key === '') {
			continue;
		}
		$prev_map[$rel_type] = cmx_dok_int_list(\get_post_meta($post_id, $meta_key, true));
		$new_map[$rel_type] = [];

		$allowed_post_types = \array_values(\array_unique(\array_filter(\array_map('strval', (array) ($cfg['post_types'] ?? [])))));
		if (empty($allowed_post_types) || !\array_key_exists($meta_key, $_POST)) {
			continue;
		}

		$raw_value = $_POST[$meta_key];
		$candidate_ids = [];
		$has_empty_option = false;
		if (\is_array($raw_value)) {
			foreach ($raw_value as $item) {
				$value = (int) \wp_unslash((string) $item);
				$candidate_ids[] = $value;
				if ($value === 0) {
					$has_empty_option = true;
				}
			}
		} else {
			$value = (int) \wp_unslash((string) $raw_value);
			$candidate_ids[] = $value;
			if ($value === 0) {
				$has_empty_option = true;
			}
		}

		$valid_ids = [];
		foreach ($candidate_ids as $selected_id) {
			if ($selected_id <= 0) {
				continue;
			}
			$selected_post_type = (string) \get_post_type($selected_id);
			if (!\in_array($selected_post_type, $allowed_post_types, true)) {
				continue;
			}
			$valid_ids[] = $selected_id;
		}
		$new_map[$rel_type] = \array_values(\array_unique($valid_ids));

		$is_touched = isset($touched_map[$meta_key]) && (int) $touched_map[$meta_key] === 1;
		if ($rel_type === $type && $is_touched && $has_empty_option && empty($new_map[$rel_type])) {
			$create_on_empty_map[$rel_type] = true;
		}
	}

	if ($type !== '' && !empty($create_on_empty_map[$type])) {
		$new_target_id = cmx_dok_create_related_entry($post_id, $type);
		if ($new_target_id > 0) {
			$new_map[$type] = [$new_target_id];
			cmx_dok_mark_redirect_to_target_edit_after_save($post_id, $new_target_id);
		}
	}

	$uploads_meta_key = cmx_dok_uploads_meta_key();
	foreach ($ui_map as $rel_type => $cfg) {
		$meta_key = (string) ($cfg['meta'] ?? '');
		if ($meta_key === '') {
			continue;
		}

		$ids = cmx_dok_int_list($new_map[$rel_type] ?? []);
		if (empty($ids)) {
			\delete_post_meta($post_id, $meta_key);
		} else {
			\update_post_meta($post_id, $meta_key, $ids);
		}

		$prev_ids = cmx_dok_int_list($prev_map[$rel_type] ?? []);
		$added = \array_values(\array_diff($ids, $prev_ids));
		$removed = \array_values(\array_diff($prev_ids, $ids));

		foreach ($added as $target_id) {
			if ($target_id <= 0) {
				continue;
			}
			$existing = cmx_dok_int_list(\get_post_meta($target_id, $uploads_meta_key, true));
			$existing[] = $post_id;
			$existing = \array_values(\array_unique($existing));
			\update_post_meta($target_id, $uploads_meta_key, $existing);
		}

		foreach ($removed as $target_id) {
			if ($target_id <= 0) {
				continue;
			}
			$existing = cmx_dok_int_list(\get_post_meta($target_id, $uploads_meta_key, true));
			$existing = \array_values(\array_diff($existing, [$post_id]));
			if (empty($existing)) {
				\delete_post_meta($target_id, $uploads_meta_key);
			} else {
				\update_post_meta($target_id, $uploads_meta_key, $existing);
			}
		}
	}
}, 10, 2);
