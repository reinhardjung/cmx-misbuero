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
	'artikel'  => 'Artikel',
	'kontakte' => 'Kontakte',
	'projekte' => 'Projekte',
	'belege'   => 'Belege',
];

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

function cmx_dok_get_relation_metabox_ids(): array {
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
	$type = (string) \get_post_meta($post_id, CMX_DOK_ZUORDNUNG_TYP_META, true);
	return \array_key_exists($type, CMX_DOK_ZUORDNUNG_TYPES) ? $type : '';
}

function cmx_dok_get_requested_zuordnung_type(int $post_id = 0): string {
	$type = isset($_POST['cmx_dok_zuordnung_typ'])
		? \sanitize_key((string) $_POST['cmx_dok_zuordnung_typ'])
		: '';

	if ($type === '' && $post_id > 0) {
		$type = cmx_dok_get_selected_zuordnung_type($post_id);
	}

	return \array_key_exists($type, CMX_DOK_ZUORDNUNG_TYPES) ? $type : '';
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

function cmx_dok_render_relation_select_box(\WP_Post $post, string $target_type, string $meta_key, string $empty_label): void {
	if ($post->post_type !== cmx_dok_cpt_slug()) {
		return;
	}

	$current_ids = cmx_dok_int_list(\get_post_meta($post->ID, $meta_key, true));
	$current = !empty($current_ids) ? (int) $current_ids[0] : 0;
	$options = cmx_dok_fetch_relation_options($target_type);
	$id_suffix = \preg_replace('~[^a-z0-9_]+~', '_', \strtolower($target_type . '_' . $meta_key));
	$select_id = 'cmx_dok_rel_select_' . $id_suffix;
	$search_id = 'cmx_dok_rel_search_' . $id_suffix;
	$nohit_id = 'cmx_dok_rel_nohit_' . $id_suffix;

	echo '<label for="' . \esc_attr($search_id) . '" class="screen-reader-text">Suchen</label>';
	echo '<input type="search" id="' . \esc_attr($search_id) . '" class="cmx-dok-rel-search" data-target-select="' . \esc_attr($select_id) . '" data-target-nohit="' . \esc_attr($nohit_id) . '" placeholder="Suchen..." style="width:100%;margin:0 0 8px;" autocomplete="off" />';
	echo '<select id="' . \esc_attr($select_id) . '" name="' . \esc_attr($meta_key) . '" style="width:100%;appearance:none;-webkit-appearance:none;-moz-appearance:none;background-image:none;" size="10">';
	echo '<option value="0">' . \esc_html($empty_label) . '</option>';
	foreach ($options as $opt) {
		echo '<option value="' . \esc_attr((string) $opt['id']) . '" ' . \selected($current, (int) $opt['id'], false) . '>' . \esc_html((string) $opt['label']) . '</option>';
	}
	echo '</select>';
	echo '<p id="' . \esc_attr($nohit_id) . '" style="display:none;margin:8px 0 0;"><em>Keine Treffer.</em></p>';

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

		document.addEventListener("keydown", function(e){
			var t = e.target;
			if (!t || !t.classList || !t.classList.contains("cmx-dok-rel-search")) return;
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

		var boot = function(){
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
	cmx_dok_render_relation_select_box($post, $target_type, $meta_key, $empty_label);
}

function cmx_dok_render_zuordnung_metabox(\WP_Post $post): void {
	\wp_nonce_field('cmx_dok_zuordnung_save', 'cmx_dok_zuordnung_nonce');
	$current = cmx_dok_get_selected_zuordnung_type((int) $post->ID);
	$metabox_map_json = \wp_json_encode(cmx_dok_get_relation_metabox_ids());

	echo '<label for="cmx_dok_zuordnung_typ" class="screen-reader-text">Zuordnung</label>';
	echo '<select id="cmx_dok_zuordnung_typ" name="cmx_dok_zuordnung_typ" style="width:100%;appearance:none;-webkit-appearance:none;-moz-appearance:none;background-image:none;">';
	echo '<option value="">- bitte waehlen -</option>';
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

	$ids = cmx_dok_get_relation_metabox_ids();
	if (empty($ids)) {
		return;
	}

	$post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
	$selected = $post_id > 0 ? cmx_dok_get_selected_zuordnung_type($post_id) : '';
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

	$box_ids = cmx_dok_get_relation_metabox_ids();
	foreach (cmx_dok_rel_ui_map() as $type => $cfg) {
		$box_id = (string) ($box_ids[$type] ?? '');
		if ($box_id === '') {
			continue;
		}
		$label = (string) ($cfg['label'] ?? ucfirst($type));
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

	foreach ($ui_map as $rel_type => $cfg) {
		$meta_key = (string) ($cfg['meta'] ?? '');
		if ($meta_key === '') {
			continue;
		}
		$prev_map[$rel_type] = cmx_dok_int_list(\get_post_meta($post_id, $meta_key, true));
		$new_map[$rel_type] = [];
	}

	if ($type !== '' && isset($ui_map[$type])) {
		$cfg = $ui_map[$type];
		$meta_key = (string) ($cfg['meta'] ?? '');
		$selected_id = $meta_key !== '' && isset($_POST[$meta_key]) ? (int) $_POST[$meta_key] : 0;
		if ($selected_id > 0) {
			$allowed_post_types = \array_values(\array_unique(\array_filter(\array_map('strval', (array) ($cfg['post_types'] ?? [])))));
			$selected_post_type = (string) \get_post_type($selected_id);
			if (\in_array($selected_post_type, $allowed_post_types, true)) {
				$new_map[$type] = [$selected_id];
			}
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
