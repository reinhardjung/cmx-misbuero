<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_KONTAKTE_ZU_KONTAKT_MB_ID = 'cmx_kontakte_zu_kontakt_box';
const CMX_KONTAKTE_ZU_KONTAKT_META  = '_cmx_kontakte_zu_kontakt_id';
const CMX_KONTAKTE_ZU_KONTAKT_BEZIEHUNG_META = '_cmx_kontakte_zu_kontakt_beziehung';
const CMX_KONTAKTE_ZU_KONTAKT_ROWS_META = '_cmx_kontakte_zu_kontakt_rows';

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_zu_kontakt_title')) {
	function cmx_kontakte_zu_kontakt_title(int $kontakt_id): string {
		$title = (string) \get_the_title($kontakt_id);
		if ($title === '') {
			$title = '#' . $kontakt_id;
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_normalize_minus_sign')) {
			$title = (string) cmx_normalize_minus_sign($title);
		}
		return $title;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_zu_kontakt_beziehung_taxonomy')) {
	function cmx_kontakte_zu_kontakt_beziehung_taxonomy(): string {
		foreach ((array) \get_object_taxonomies('kontakte', 'names') as $taxonomy) {
			$taxonomy = (string) $taxonomy;
			if ($taxonomy !== '' && \stripos($taxonomy, 'bezieh') !== false) {
				return $taxonomy;
			}
		}
		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_zu_kontakt_beziehung_options')) {
	/**
	 * @return array<int,array{value:string,label:string}>
	 */
	function cmx_kontakte_zu_kontakt_beziehung_options(): array {
		$taxonomy = cmx_kontakte_zu_kontakt_beziehung_taxonomy();
		if ($taxonomy === '') {
			return [];
		}

		$terms = \get_terms([
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]);
		if (\is_wp_error($terms) || empty($terms)) {
			return [];
		}

		$options = [];
		foreach ($terms as $term) {
			$options[] = [
				'value' => (string) $term->slug,
				'label' => (string) $term->name,
			];
		}
		return $options;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_zu_kontakt_allowed_beziehungen')) {
	/**
	 * @return array<int,string>
	 */
	function cmx_kontakte_zu_kontakt_allowed_beziehungen(): array {
		return \array_values(\array_filter(\array_map('strval', \array_column(cmx_kontakte_zu_kontakt_beziehung_options(), 'value'))));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_zu_kontakt_beziehung_labels')) {
	/**
	 * @return array<string,string>
	 */
	function cmx_kontakte_zu_kontakt_beziehung_labels(): array {
		$labels = [];
		foreach (cmx_kontakte_zu_kontakt_beziehung_options() as $option) {
			$value = (string) ($option['value'] ?? '');
			if ($value === '') {
				continue;
			}
			$labels[$value] = (string) ($option['label'] ?? $value);
		}
		return $labels;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_zu_kontakt_normalize_rows')) {
	/**
	 * @param array<mixed> $rows
	 * @return array<int,array{id:int,beziehung:string}>
	 */
	function cmx_kontakte_zu_kontakt_normalize_rows(array $rows, int $self_id = 0): array {
		$allowed = cmx_kontakte_zu_kontakt_allowed_beziehungen();
		$out = [];
		$seen = [];

		foreach ($rows as $row) {
			if (\is_numeric($row)) {
				$row = ['id' => (int) $row, 'beziehung' => ''];
			}
			if (!\is_array($row)) {
				continue;
			}

			$id = isset($row['id']) ? (int) $row['id'] : 0;
			$beziehung = isset($row['beziehung']) ? \sanitize_title((string) $row['beziehung']) : '';
			if ($id <= 0 || $id === $self_id || isset($seen[$id])) {
				continue;
			}
			if (\get_post_type($id) !== 'kontakte' || !\get_post_status($id)) {
				continue;
			}
			if ($beziehung !== '' && !\in_array($beziehung, $allowed, true)) {
				$beziehung = '';
			}

			$seen[$id] = true;
			$out[] = [
				'id' => $id,
				'beziehung' => $beziehung,
			];
		}

		return $out;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_zu_kontakt_rows')) {
	/**
	 * @return array<int,array{id:int,beziehung:string}>
	 */
	function cmx_kontakte_zu_kontakt_rows(int $post_id): array {
		$raw = \get_post_meta($post_id, CMX_KONTAKTE_ZU_KONTAKT_ROWS_META, true);
		if (\is_array($raw) && $raw !== []) {
			return cmx_kontakte_zu_kontakt_normalize_rows($raw, $post_id);
		}

		$legacy_id = (int) \get_post_meta($post_id, CMX_KONTAKTE_ZU_KONTAKT_META, true);
		$legacy_beziehung = \sanitize_title((string) \get_post_meta($post_id, CMX_KONTAKTE_ZU_KONTAKT_BEZIEHUNG_META, true));
		if ($legacy_id > 0) {
			return cmx_kontakte_zu_kontakt_normalize_rows([[
				'id' => $legacy_id,
				'beziehung' => $legacy_beziehung,
			]], $post_id);
		}

		return [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_zu_kontakt_store_rows')) {
	/**
	 * @param array<int,array{id:int,beziehung:string}> $rows
	 */
	function cmx_kontakte_zu_kontakt_store_rows(int $post_id, array $rows): void {
		$rows = cmx_kontakte_zu_kontakt_normalize_rows($rows, $post_id);
		if ($rows === []) {
			\delete_post_meta($post_id, CMX_KONTAKTE_ZU_KONTAKT_ROWS_META);
			\delete_post_meta($post_id, CMX_KONTAKTE_ZU_KONTAKT_META);
			\delete_post_meta($post_id, CMX_KONTAKTE_ZU_KONTAKT_BEZIEHUNG_META);
			return;
		}

		\update_post_meta($post_id, CMX_KONTAKTE_ZU_KONTAKT_ROWS_META, \array_values($rows));
		\update_post_meta($post_id, CMX_KONTAKTE_ZU_KONTAKT_META, (int) $rows[0]['id']);
		if ((string) $rows[0]['beziehung'] !== '') {
			\update_post_meta($post_id, CMX_KONTAKTE_ZU_KONTAKT_BEZIEHUNG_META, (string) $rows[0]['beziehung']);
		} else {
			\delete_post_meta($post_id, CMX_KONTAKTE_ZU_KONTAKT_BEZIEHUNG_META);
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_zu_kontakt_sync_reverse_rows')) {
	/**
	 * @param array<int,array{id:int,beziehung:string}> $old_rows
	 * @param array<int,array{id:int,beziehung:string}> $new_rows
	 */
	function cmx_kontakte_zu_kontakt_sync_reverse_rows(int $post_id, array $old_rows, array $new_rows): void {
		$affected = [];
		foreach (\array_merge($old_rows, $new_rows) as $row) {
			$id = (int) ($row['id'] ?? 0);
			if ($id > 0 && $id !== $post_id) {
				$affected[$id] = true;
			}
		}

		$new_map = [];
		foreach ($new_rows as $row) {
			$id = (int) ($row['id'] ?? 0);
			if ($id > 0 && $id !== $post_id) {
				$new_map[$id] = (string) ($row['beziehung'] ?? '');
			}
		}

		foreach (\array_keys($affected) as $related_id) {
			$related_id = (int) $related_id;
			$related_rows = \array_values(\array_filter(
				cmx_kontakte_zu_kontakt_rows($related_id),
				static fn(array $row): bool => (int) ($row['id'] ?? 0) !== $post_id
			));

			if (isset($new_map[$related_id])) {
				$related_rows[] = [
					'id' => $post_id,
					'beziehung' => (string) $new_map[$related_id],
				];
			}

			cmx_kontakte_zu_kontakt_store_rows($related_id, $related_rows);
		}
	}
}

\add_action('add_meta_boxes', function (): void {
	\add_meta_box(
		CMX_KONTAKTE_ZU_KONTAKT_MB_ID,
		'Zuordnen zu Kontakt',
		__NAMESPACE__ . '\\cmx_render_kontakte_zu_kontakt_box',
		'kontakte',
		'side',
		'default'
	);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_kontakte_zu_kontakt_box')) {
	function cmx_render_kontakte_zu_kontakt_box(\WP_Post $post): void {
		$current_id = (int) $post->ID;
		$selected_rows = cmx_kontakte_zu_kontakt_rows($current_id);
		$beziehung_options = cmx_kontakte_zu_kontakt_beziehung_options();
		$beziehung_labels = cmx_kontakte_zu_kontakt_beziehung_labels();
		$ajax_url = \admin_url('admin-ajax.php');
		$ajax_nonce = \wp_create_nonce('cmx_search_kontakte');
		$box_id = 'cmx-kontakte-zu-kontakt-box-' . $current_id;
		$search_id = 'cmx_kontakte_zu_kontakt_search_' . $current_id;
		$list_id = 'cmx_kontakte_zu_kontakt_suggest_' . $current_id;
		$selected_id_attr = 'cmx_kontakte_zu_kontakt_selected_' . $current_id;
		$beziehung_id = 'cmx_kontakte_zu_kontakt_beziehung_' . $current_id;

		\wp_nonce_field('cmx_save_kontakte_zu_kontakt', 'cmx_kontakte_zu_kontakt_nonce');

		echo '<style>
		#' . \esc_attr(CMX_KONTAKTE_ZU_KONTAKT_MB_ID) . ',
		#' . \esc_attr(CMX_KONTAKTE_ZU_KONTAKT_MB_ID) . ' .inside,
		#' . \esc_attr($box_id) . '{position:relative;overflow:visible}
		#' . \esc_attr($box_id) . ' .cmx-kontakte-zu-kontakt-suggest{position:relative;overflow:visible}
		#' . \esc_attr($box_id) . ' .cmx-kontakte-zu-kontakt-results{
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
			#' . \esc_attr($box_id) . ' .cmx-kontakte-zu-kontakt-results li{margin:0;padding:6px 8px;cursor:pointer}
			#' . \esc_attr($box_id) . ' .cmx-kontakte-zu-kontakt-results li.active,
			#' . \esc_attr($box_id) . ' .cmx-kontakte-zu-kontakt-results li:hover{background:#e5f3ff}
			#' . \esc_attr($box_id) . ' .cmx-kontakte-zu-kontakt-type{display:block;margin:0 0 2px;font-size:11px;line-height:1.35;font-weight:600;color:#3858e9}
			#' . \esc_attr($box_id) . ' .cmx-kontakte-zu-kontakt-line2{display:block;margin-top:2px;font-size:11px;line-height:1.35;color:#646970}
			#' . \esc_attr($box_id) . ' .cmx-kontakte-zu-kontakt-selected{margin:10px 0 0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px}
			#' . \esc_attr($box_id) . ' .cmx-kontakte-zu-kontakt-selected li{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;padding:2px 0}
			#' . \esc_attr($box_id) . ' .cmx-kontakte-zu-kontakt-selected-main{min-width:0}
			#' . \esc_attr($box_id) . ' .cmx-kontakte-zu-kontakt-remove{line-height:1}
			</style>';

		echo '<div id="' . \esc_attr($box_id) . '" class="cmx-kontakte-zu-kontakt-box">';
		echo '<select id="' . \esc_attr($beziehung_id) . '" class="widefat" name="cmx_kontakte_zu_kontakt_beziehung" style="margin-bottom:8px;">';
		echo '<option value="">' . \esc_html__('Beziehung wählen', 'cmx') . '</option>';
		foreach ($beziehung_options as $option) {
			echo '<option value="' . \esc_attr((string) $option['value']) . '">' . \esc_html((string) $option['label']) . '</option>';
		}
		echo '</select>';
		echo '<div class="cmx-kontakte-zu-kontakt-suggest">';
		echo '<input type="search" id="' . \esc_attr($search_id) . '" class="widefat" autocomplete="off" placeholder="Kontakt suchen..." value="">';
		echo '<ul id="' . \esc_attr($list_id) . '" class="cmx-kontakte-zu-kontakt-results" style="display:none"></ul>';
		echo '</div>';
		echo '<ul id="' . \esc_attr($selected_id_attr) . '" class="cmx-kontakte-zu-kontakt-selected">';
		foreach ($selected_rows as $row) {
			$selected_id = (int) ($row['id'] ?? 0);
			if ($selected_id <= 0 || \get_post_type($selected_id) !== 'kontakte' || !\get_post_status($selected_id)) {
				continue;
			}
			$selected_title = cmx_kontakte_zu_kontakt_title($selected_id);
			$selected_addr = \function_exists(__NAMESPACE__ . '\\cmx_build_kontakt_postanschrift')
				? (string) cmx_build_kontakt_postanschrift($selected_id)
				: '';
			$selected_delivery_addr = \function_exists(__NAMESPACE__ . '\\cmx_build_kontakt_lieferanschrift')
				? (string) cmx_build_kontakt_lieferanschrift($selected_id)
				: '';
			$selected_beziehung = (string) ($row['beziehung'] ?? '');
			$selected_beziehung_label = $selected_beziehung !== '' ? (string) ($beziehung_labels[$selected_beziehung] ?? $selected_beziehung) : '';
			$edit_url = \get_edit_post_link($selected_id, 'raw');
			echo '<li data-id="' . (int) $selected_id . '" data-beziehung="' . \esc_attr($selected_beziehung) . '">';
			echo '<div class="cmx-kontakte-zu-kontakt-selected-main">';
			if ($selected_beziehung_label !== '') {
				echo '<span class="cmx-kontakte-zu-kontakt-type">' . \esc_html($selected_beziehung_label) . '</span>';
			}
			if ($edit_url) {
				echo '<a href="' . \esc_url($edit_url) . '">' . \esc_html($selected_title) . '</a>';
			} else {
				echo '<span>' . \esc_html($selected_title) . '</span>';
			}
			if ($selected_addr !== '') {
				echo '<span class="cmx-kontakte-zu-kontakt-line2">' . \esc_html($selected_addr) . '</span>';
			}
			if ($selected_delivery_addr !== '' && $selected_delivery_addr !== $selected_addr) {
				echo '<span class="cmx-kontakte-zu-kontakt-line2">' . \esc_html($selected_delivery_addr) . '</span>';
			}
			echo '<input type="hidden" name="cmx_kontakte_zu_kontakt_ids[]" value="' . (int) $selected_id . '">';
			echo '<input type="hidden" name="cmx_kontakte_zu_kontakt_beziehungen[]" value="' . \esc_attr($selected_beziehung) . '">';
			echo '</div>';
			echo '<button type="button" class="button-link-delete cmx-kontakte-zu-kontakt-remove"><span class="dashicons dashicons-trash" style="color:#d63638;"></span></button>';
			echo '</li>';
		}
		echo '</ul>';
		echo '</div>';

		echo '<script>
		(function(){
			var root = document.getElementById(' . \wp_json_encode($box_id) . ');
			if (!root || root.dataset.cmxBound === "1") return;
			root.dataset.cmxBound = "1";

			var currentId = ' . (int) $current_id . ';
			var searchInput = document.getElementById(' . \wp_json_encode($search_id) . ');
			var listEl = document.getElementById(' . \wp_json_encode($list_id) . ');
			var selectedEl = document.getElementById(' . \wp_json_encode($selected_id_attr) . ');
			var beziehungSelect = document.getElementById(' . \wp_json_encode($beziehung_id) . ');
			if (!searchInput || !listEl || !selectedEl || !beziehungSelect) return;

			var ajaxUrl = ' . \wp_json_encode($ajax_url) . ';
			var ajaxNonce = ' . \wp_json_encode($ajax_nonce) . ';
			var timer = null;
			var active = -1;
			var items = [];
			var requestSeq = 0;
			var requestCtrl = null;

			function esc(str){
				return String(str || "").replace(/[&<>"\']/g, function(c){
					if (c === "&") return "&amp;";
					if (c === "<") return "&lt;";
					if (c === ">") return "&gt;";
					if (c.charCodeAt(0) === 34) return "&quot;";
					return "&#039;";
				});
			}
				function closeList(){
					listEl.style.display = "none";
					listEl.innerHTML = "";
					active = -1;
					items = [];
				}
				function hasSelected(id){
					return !!selectedEl.querySelector(\'li[data-id="\'+String(id)+\'"]\');
				}
				function render(arr){
					items = Array.isArray(arr) ? arr.filter(function(it){
						var id = Number(it && it.id || 0);
						return id > 0 && id !== currentId && !hasSelected(id);
					}) : [];
					if (!items.length) {
						listEl.innerHTML = "<li style=\"color:#646970;cursor:default;\">Keine Kontakte gefunden.</li>";
					listEl.style.display = "block";
					active = -1;
					return;
					}
					listEl.innerHTML = items.map(function(it, i){
						var title = esc(it && it.title ? it.title : "");
						var addr = esc(it && it.addr ? it.addr : "");
						var delivery = esc(it && it.delivery_addr ? it.delivery_addr : "");
						return "<li data-index=\"" + i + "\"><span>" + title + "</span>" + (addr ? "<span class=\"cmx-kontakte-zu-kontakt-line2\">" + addr + "</span>" : "") + ((delivery && delivery !== addr) ? "<span class=\"cmx-kontakte-zu-kontakt-line2\">" + delivery + "</span>" : "") + "</li>";
					}).join("");
					listEl.style.display = "block";
					active = -1;
			}
			function setActive(next){
				if (!items.length) { active = -1; return; }
				if (next < 0) next = items.length - 1;
				if (next >= items.length) next = 0;
				active = next;
				Array.prototype.forEach.call(listEl.children, function(li, idx){
					li.classList.toggle("active", idx === active);
					if (idx === active) {
						try { li.scrollIntoView({ block: "nearest" }); } catch (err) {}
						}
					});
				}
				function renderSelected(item, beziehungValue, beziehungLabel){
					if (!item || !item.id || hasSelected(item.id)) return;
					var li = document.createElement("li");
					li.setAttribute("data-id", String(item.id));
					li.setAttribute("data-beziehung", String(beziehungValue || ""));
					var main = document.createElement("div");
					main.className = "cmx-kontakte-zu-kontakt-selected-main";
					if (beziehungLabel) {
						var type = document.createElement("span");
						type.className = "cmx-kontakte-zu-kontakt-type";
						type.textContent = String(beziehungLabel);
						main.appendChild(type);
					}
					var link = document.createElement("a");
					link.href = ' . \wp_json_encode(\admin_url('post.php')) . ' + "?post=" + encodeURIComponent(String(item.id)) + "&action=edit";
					link.textContent = item.title || ("#" + String(item.id));
					main.appendChild(link);
					if (item.addr) {
						var line2 = document.createElement("span");
						line2.className = "cmx-kontakte-zu-kontakt-line2";
						line2.textContent = item.addr;
						main.appendChild(line2);
					}
					if (item.delivery_addr && item.delivery_addr !== item.addr) {
						var line3 = document.createElement("span");
						line3.className = "cmx-kontakte-zu-kontakt-line2";
						line3.textContent = item.delivery_addr;
						main.appendChild(line3);
					}
					var hidden = document.createElement("input");
					hidden.type = "hidden";
					hidden.name = "cmx_kontakte_zu_kontakt_ids[]";
					hidden.value = String(item.id);
					main.appendChild(hidden);
					var hiddenType = document.createElement("input");
					hiddenType.type = "hidden";
					hiddenType.name = "cmx_kontakte_zu_kontakt_beziehungen[]";
					hiddenType.value = String(beziehungValue || "");
					main.appendChild(hiddenType);
					var remove = document.createElement("button");
					remove.type = "button";
					remove.className = "button-link-delete cmx-kontakte-zu-kontakt-remove";
					remove.innerHTML = "<span class=\"dashicons dashicons-trash\" style=\"color:#d63638;\"></span>";
					li.appendChild(main);
					li.appendChild(remove);
					selectedEl.appendChild(li);
				}
				function choose(item){
					var selectedOption = beziehungSelect.options[beziehungSelect.selectedIndex] || null;
					var beziehungValue = selectedOption ? String(selectedOption.value || "") : "";
					var beziehungLabel = beziehungValue !== "" && selectedOption ? String(selectedOption.text || "") : "";
					renderSelected(item, beziehungValue, beziehungLabel);
					searchInput.value = "";
					closeList();
					searchInput.focus();
				}
				function search(q){
				requestSeq += 1;
				var seq = requestSeq;
				if (requestCtrl && typeof requestCtrl.abort === "function") {
					try { requestCtrl.abort(); } catch (err) {}
					}
					requestCtrl = typeof AbortController !== "undefined" ? new AbortController() : null;
					var url = ajaxUrl + "?action=cmx_search_kontakte&_ajax_nonce=" + encodeURIComponent(ajaxNonce) + "&include_liefer=1&q=" + encodeURIComponent(q || "");
					var fetchOptions = { credentials: "same-origin" };
					if (requestCtrl) {
						fetchOptions.signal = requestCtrl.signal;
				}
				fetch(url, fetchOptions).then(function(r){
					return r.json();
				}).then(function(json){
					if (seq !== requestSeq) {
						return;
					}
					if (!json || !json.success || !json.data || !Array.isArray(json.data.items)) {
						closeList();
						return;
					}
					render(json.data.items || []);
				}).catch(function(){
					if (seq !== requestSeq) {
						return;
					}
					closeList();
				});
				}

				searchInput.addEventListener("input", function(){
					if (timer) clearTimeout(timer);
					var q = (searchInput.value || "").trim();
					if (q.length === 0) {
					search("");
					return;
				}
				if (q.length < 2) {
					closeList();
					return;
				}
				timer = setTimeout(function(){ search(q); }, 200);
			});
			searchInput.addEventListener("focus", function(){
				if (timer) clearTimeout(timer);
				search((searchInput.value || "").trim());
			});
			searchInput.addEventListener("click", function(){
				if (timer) clearTimeout(timer);
				search((searchInput.value || "").trim());
			});
			searchInput.addEventListener("keydown", function(e){
				if (listEl.style.display !== "block" && (e.key === "ArrowDown" || e.key === "ArrowUp")) return;
				if (e.key === "ArrowDown") {
					e.preventDefault();
					setActive(active + 1);
				} else if (e.key === "ArrowUp") {
					e.preventDefault();
					setActive(active - 1);
				} else if (e.key === "Enter") {
					if (active > -1 && items[active]) {
						e.preventDefault();
						choose(items[active]);
					}
				} else if (e.key === "Escape") {
					closeList();
				}
			});
			listEl.addEventListener("mousedown", function(e){
				var li = e.target && e.target.closest ? e.target.closest("li[data-index]") : null;
				if (!li) return;
				e.preventDefault();
				var index = parseInt(li.getAttribute("data-index") || "-1", 10);
				if (!isNaN(index) && items[index]) {
					choose(items[index]);
				}
			});
			listEl.addEventListener("mousemove", function(e){
				var li = e.target && e.target.closest ? e.target.closest("li[data-index]") : null;
				if (!li) return;
				var index = parseInt(li.getAttribute("data-index") || "-1", 10);
				if (!isNaN(index)) setActive(index);
			});
				document.addEventListener("click", function(e){
					if (e.target === searchInput || listEl.contains(e.target)) return;
					closeList();
				});
				root.addEventListener("click", function(e){
					var removeBtn = e.target && e.target.closest ? e.target.closest(".cmx-kontakte-zu-kontakt-remove") : null;
					if (!removeBtn) return;
					e.preventDefault();
					var li = removeBtn.closest("li[data-id]");
					if (li) {
						li.remove();
					}
				});
			})();
			</script>';
		}
}

\add_action('save_post_kontakte', function (int $post_id): void {
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!isset($_POST['cmx_kontakte_zu_kontakt_nonce']) || !\wp_verify_nonce((string) $_POST['cmx_kontakte_zu_kontakt_nonce'], 'cmx_save_kontakte_zu_kontakt')) {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) return;

	$old_rows = cmx_kontakte_zu_kontakt_rows($post_id);
	$ids = isset($_POST['cmx_kontakte_zu_kontakt_ids']) && \is_array($_POST['cmx_kontakte_zu_kontakt_ids'])
		? (array) $_POST['cmx_kontakte_zu_kontakt_ids']
		: [];
	$beziehungen = isset($_POST['cmx_kontakte_zu_kontakt_beziehungen']) && \is_array($_POST['cmx_kontakte_zu_kontakt_beziehungen'])
		? (array) $_POST['cmx_kontakte_zu_kontakt_beziehungen']
		: [];

	$new_rows = [];
	foreach ($ids as $index => $id_raw) {
		$new_rows[] = [
			'id' => (int) $id_raw,
			'beziehung' => isset($beziehungen[$index]) ? \sanitize_title((string) $beziehungen[$index]) : '',
		];
	}

	$new_rows = cmx_kontakte_zu_kontakt_normalize_rows($new_rows, $post_id);
	cmx_kontakte_zu_kontakt_store_rows($post_id, $new_rows);
	cmx_kontakte_zu_kontakt_sync_reverse_rows($post_id, $old_rows, $new_rows);
}, 10, 1);
