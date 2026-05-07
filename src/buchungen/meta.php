<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_select_posts')) {
	function cmx_buchungen_select_posts(string $post_type): array {
		if (!\post_type_exists($post_type)) {
			return [];
		}

		return \get_posts([
			'post_type' => $post_type,
			'post_status' => ['publish', 'private', 'draft'],
			'posts_per_page' => 250,
			'orderby' => 'title',
			'order' => 'ASC',
			'fields' => 'ids',
			'no_found_rows' => true,
		]);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_term_select')) {
	function cmx_buchungen_term_select(string $taxonomy, string $name, int $current): void {
		$terms = \taxonomy_exists($taxonomy) ? \get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]) : [];
		echo '<select class="widefat" name="' . \esc_attr($name) . '">';
		echo '<option value="">- auswählen -</option>';
		if (!\is_wp_error($terms)) {
			foreach ((array) $terms as $term) {
				if (!$term instanceof \WP_Term) {
					continue;
				}
				echo '<option value="' . \esc_attr((string) $term->term_id) . '"' . \selected($current, (int) $term->term_id, false) . '>' . \esc_html($term->name) . '</option>';
			}
		}
		echo '</select>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_post_search_field')) {
	function cmx_buchungen_post_search_field(string $type, string $name, int $current, string $placeholder): void {
		$title = $current > 0 ? \trim((string) \get_the_title($current)) : '';
		$input_id = 'cmx_buchung_' . $type . '_search';
		$hidden_id = 'cmx_buchung_' . $type . '_id';
		$list_id = 'cmx_buchung_' . $type . '_suggest';

		echo '<div class="cmx-buchungen-post-search" data-cmx-buchung-search="' . \esc_attr($type) . '">';
		echo '<input type="search" id="' . \esc_attr($input_id) . '" class="widefat cmx-buchungen-search-input" autocomplete="off" placeholder="' . \esc_attr($placeholder) . '" value="' . \esc_attr($title) . '">';
		echo '<input type="hidden" id="' . \esc_attr($hidden_id) . '" name="' . \esc_attr($name) . '" value="' . \esc_attr((string) $current) . '">';
		echo '<ul id="' . \esc_attr($list_id) . '" class="cmx-buchungen-search-results" style="display:none"></ul>';
		echo '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_term_search_field')) {
	function cmx_buchungen_term_search_field(string $taxonomy, string $name, int $current, string $placeholder, string $search_type = 'term'): void {
		$current_term = $current > 0 && \taxonomy_exists($taxonomy) ? \get_term($current, $taxonomy) : null;
		$title = $current_term instanceof \WP_Term ? (string) $current_term->name : '';
		$terms = \taxonomy_exists($taxonomy) ? \get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]) : [];
		$items = [];
		if (!\is_wp_error($terms)) {
			foreach ((array) $terms as $term) {
				if ($term instanceof \WP_Term) {
					$items[] = ['id' => (int) $term->term_id, 'label' => (string) $term->name];
				}
			}
		}

		echo '<div class="cmx-buchungen-post-search" data-cmx-buchung-search="' . \esc_attr($search_type) . '" data-cmx-term-items="' . \esc_attr((string) \wp_json_encode($items)) . '">';
		echo '<input type="search" class="widefat cmx-buchungen-search-input" autocomplete="off" placeholder="' . \esc_attr($placeholder) . '" value="' . \esc_attr($title) . '">';
		echo '<input type="hidden" name="' . \esc_attr($name) . '" value="' . \esc_attr((string) $current) . '">';
		echo '<ul class="cmx-buchungen-search-results" style="display:none"></ul>';
		echo '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_first_term_id')) {
	function cmx_buchungen_first_term_id(int $post_id, string $taxonomy): int {
		if ($post_id <= 0 || !\taxonomy_exists($taxonomy)) {
			return 0;
		}
		$terms = \wp_get_post_terms($post_id, $taxonomy, ['fields' => 'ids']);
		return !\is_wp_error($terms) ? (int) ($terms[0] ?? 0) : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_duration_term_id')) {
	function cmx_buchungen_duration_term_id(int $post_id): int {
		$current = cmx_buchungen_first_term_id($post_id, CMX_BUCHUNGEN_TAX_DAUER);
		if ($current > 0) {
			return $current;
		}

		$duration = \trim((string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_DURATION, true));
		if ($duration === '') {
			$duration = '60';
		}

		$term = \get_term_by('name', $duration, CMX_BUCHUNGEN_TAX_DAUER);
		if (!$term instanceof \WP_Term) {
			$term = \get_term_by('slug', $duration, CMX_BUCHUNGEN_TAX_DAUER);
		}

		return $term instanceof \WP_Term ? (int) $term->term_id : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_duration_from_term_id')) {
	function cmx_buchungen_duration_from_term_id(int $term_id): int {
		$term = $term_id > 0 ? \get_term($term_id, CMX_BUCHUNGEN_TAX_DAUER) : null;
		if (!$term instanceof \WP_Term || \is_wp_error($term)) {
			return 60;
		}

		$duration = (int) $term->name;
		if ($duration <= 0) {
			$duration = (int) $term->slug;
		}

		return $duration > 0 ? $duration : 60;
	}
}

\add_action('add_meta_boxes_' . CMX_BUCHUNGEN_CPT, function (): void {
	\add_meta_box('cmx_buchungen_details', 'Buchungsdaten', __NAMESPACE__ . '\\cmx_buchungen_render_details_box', CMX_BUCHUNGEN_CPT, 'normal', 'high');
	\add_meta_box('cmx_buchungen_status', 'Status', __NAMESPACE__ . '\\cmx_buchungen_render_status_box', CMX_BUCHUNGEN_CPT, 'side', 'high');
	\add_meta_box('cmx_buchungen_mitarbeiter', 'Mitarbeiter', __NAMESPACE__ . '\\cmx_buchungen_render_mitarbeiter_box', CMX_BUCHUNGEN_CPT, 'side', 'default');
	\add_meta_box('cmx_buchungen_tokens', 'Online Buchung', __NAMESPACE__ . '\\cmx_buchungen_render_tokens_box', CMX_BUCHUNGEN_CPT, 'side', 'default');
});

function cmx_buchungen_render_details_box(\WP_Post $post): void {
	\wp_nonce_field('cmx_buchungen_save', 'cmx_buchungen_nonce');

	$kontakt_id = (int) \get_post_meta($post->ID, CMX_BUCHUNGEN_META_KONTAKT, true);
	$artikel_id = (int) \get_post_meta($post->ID, CMX_BUCHUNGEN_META_ARTIKEL, true);
	$standort_id = cmx_buchungen_first_term_id($post->ID, CMX_BUCHUNGEN_TAX_STANDORT);
	$buchungstyp_id = cmx_buchungen_first_term_id($post->ID, CMX_BUCHUNGEN_TAX_TYP);
	$leistungskategorie_id = cmx_buchungen_first_term_id($post->ID, CMX_BUCHUNGEN_TAX_LEISTUNGSKATEGORIE);
	$dauer_id = cmx_buchungen_duration_term_id($post->ID);
	$fields = [
		'date' => \get_post_meta($post->ID, CMX_BUCHUNGEN_META_START_DATE, true),
		'time' => \get_post_meta($post->ID, CMX_BUCHUNGEN_META_START_TIME, true),
		'buffer_before' => \get_post_meta($post->ID, CMX_BUCHUNGEN_META_BUFFER_BEFORE, true) ?: '0',
		'buffer_after' => \get_post_meta($post->ID, CMX_BUCHUNGEN_META_BUFFER_AFTER, true) ?: '0',
	];

	echo '<style>
		#cmx_buchungen_details,
		#cmx_buchungen_details .inside{overflow:visible}
		.cmx-buchungen-grid{display:grid;grid-template-columns:1fr;gap:14px}
		.cmx-buchungen-top-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
		.cmx-buchungen-second-row{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:14px}
		.cmx-buchungen-field label{display:block;font-weight:600;margin:0 0 5px}
		.cmx-buchungen-label-link{display:inline-block;font-weight:600;margin:0 0 5px;color:#2271b1;text-decoration:none}
		.cmx-buchungen-label-link:hover{text-decoration:underline}
		.cmx-buchungen-span-4{grid-column:1/-1}
		.cmx-buchungen-post-search{position:relative;overflow:visible}
		.cmx-buchungen-search-results{position:absolute;z-index:100002;left:0;right:0;max-height:240px;overflow:auto;margin:2px 0 0;padding:0;border:1px solid #ccd0d4;border-radius:8px;background:#fff;box-shadow:0 10px 24px rgba(0,0,0,.12);list-style:none}
		.cmx-buchungen-search-results li{margin:0;padding:8px 10px;cursor:pointer}
		.cmx-buchungen-search-results li.active,
		.cmx-buchungen-search-results li:hover{background:#e5f3ff}
		.cmx-buchungen-search-results small{display:block;color:#646970}
		@media(max-width:1300px){.cmx-buchungen-second-row{grid-template-columns:repeat(4,minmax(0,1fr))}}
		@media(max-width:1100px){.cmx-buchungen-top-row,.cmx-buchungen-second-row{grid-template-columns:repeat(2,minmax(0,1fr))}.cmx-buchungen-span-4{grid-column:1/-1}}
		@media(max-width:782px){.cmx-buchungen-grid,.cmx-buchungen-top-row,.cmx-buchungen-second-row{display:block}.cmx-buchungen-field{margin-bottom:12px}}
	</style>';
	echo '<div class="cmx-buchungen-grid">';
	echo '<div class="cmx-buchungen-top-row">';

	$kontakt_link = $kontakt_id > 0 ? (string) \get_edit_post_link($kontakt_id, 'raw') : \admin_url('edit.php?post_type=kontakte');
	echo '<div class="cmx-buchungen-field"><a class="cmx-buchungen-label-link" href="' . \esc_url($kontakt_link) . '" target="_blank" rel="noopener noreferrer">Kontakt</a>';
	cmx_buchungen_post_search_field('kontakt', 'cmx_buchung_kontakt_id', $kontakt_id, 'Kontakt suchen...');
	echo '</div>';

	$artikel_link = $artikel_id > 0 ? (string) \get_edit_post_link($artikel_id, 'raw') : \admin_url('edit.php?post_type=artikel');
	echo '<div class="cmx-buchungen-field"><a class="cmx-buchungen-label-link" href="' . \esc_url($artikel_link) . '" target="_blank" rel="noopener noreferrer">Artikel</a>';
	cmx_buchungen_post_search_field('artikel', 'cmx_buchung_artikel_id', $artikel_id, 'Artikel suchen...');
	echo '</div>';

	$ressource_id = (int) \get_post_meta($post->ID, CMX_BUCHUNGEN_META_RESSOURCE, true);
	$ressource_link = $ressource_id > 0
		? (string) \get_edit_term_link($ressource_id, CMX_BUCHUNGEN_TAX_RESSOURCE, CMX_BUCHUNGEN_CPT)
		: \admin_url('edit-tags.php?taxonomy=' . \rawurlencode(CMX_BUCHUNGEN_TAX_RESSOURCE) . '&post_type=' . \rawurlencode(CMX_BUCHUNGEN_CPT));
	echo '<div class="cmx-buchungen-field"><a class="cmx-buchungen-label-link" href="' . \esc_url($ressource_link) . '" target="_blank" rel="noopener noreferrer">Ressource</a>';
	cmx_buchungen_term_search_field(CMX_BUCHUNGEN_TAX_RESSOURCE, 'cmx_buchung_ressource_term_id', $ressource_id, 'Ressource suchen...', 'ressource');
	echo '</div>';

	$standort_link = $standort_id > 0
		? (string) \get_edit_term_link($standort_id, CMX_BUCHUNGEN_TAX_STANDORT, CMX_BUCHUNGEN_CPT)
		: \admin_url('edit-tags.php?taxonomy=' . \rawurlencode(CMX_BUCHUNGEN_TAX_STANDORT) . '&post_type=' . \rawurlencode(CMX_BUCHUNGEN_CPT));
	echo '<div class="cmx-buchungen-field"><a class="cmx-buchungen-label-link" href="' . \esc_url($standort_link) . '" target="_blank" rel="noopener noreferrer">Standort</a>';
	cmx_buchungen_term_search_field(CMX_BUCHUNGEN_TAX_STANDORT, 'cmx_buchung_standort_term_id', $standort_id, 'Standort suchen...', 'standort');
	echo '</div>';
	echo '</div>';
	echo '<div class="cmx-buchungen-second-row">';

	$buchungstyp_link = $buchungstyp_id > 0
		? (string) \get_edit_term_link($buchungstyp_id, CMX_BUCHUNGEN_TAX_TYP, CMX_BUCHUNGEN_CPT)
		: \admin_url('edit-tags.php?taxonomy=' . \rawurlencode(CMX_BUCHUNGEN_TAX_TYP) . '&post_type=' . \rawurlencode(CMX_BUCHUNGEN_CPT));
	echo '<div class="cmx-buchungen-field"><a class="cmx-buchungen-label-link" href="' . \esc_url($buchungstyp_link) . '" target="_blank" rel="noopener noreferrer">Buchungstyp</a>';
	cmx_buchungen_term_search_field(CMX_BUCHUNGEN_TAX_TYP, 'cmx_buchung_typ_term_id', $buchungstyp_id, 'Buchungstyp suchen...', 'buchungstyp');
	echo '</div>';

	$leistungskategorie_link = $leistungskategorie_id > 0
		? (string) \get_edit_term_link($leistungskategorie_id, CMX_BUCHUNGEN_TAX_LEISTUNGSKATEGORIE, CMX_BUCHUNGEN_CPT)
		: \admin_url('edit-tags.php?taxonomy=' . \rawurlencode(CMX_BUCHUNGEN_TAX_LEISTUNGSKATEGORIE) . '&post_type=' . \rawurlencode(CMX_BUCHUNGEN_CPT));
	echo '<div class="cmx-buchungen-field"><a class="cmx-buchungen-label-link" href="' . \esc_url($leistungskategorie_link) . '" target="_blank" rel="noopener noreferrer">Leistungskategorie</a>';
	cmx_buchungen_term_search_field(CMX_BUCHUNGEN_TAX_LEISTUNGSKATEGORIE, 'cmx_buchung_leistungskategorie_term_id', $leistungskategorie_id, 'Leistungskategorie suchen...', 'leistungskategorie');
	echo '</div>';

	echo '<div class="cmx-buchungen-field"><label>Dauer Minuten</label>';
	cmx_buchungen_term_select(CMX_BUCHUNGEN_TAX_DAUER, 'cmx_buchung_dauer_term_id', $dauer_id);
	echo '</div>';
	echo '<div class="cmx-buchungen-field"><label>Startdatum</label><input class="widefat" type="date" name="cmx_buchung_start_date" value="' . \esc_attr((string) $fields['date']) . '"></div>';
	echo '<div class="cmx-buchungen-field"><label>Startzeit</label><input class="widefat" type="time" name="cmx_buchung_start_time" value="' . \esc_attr((string) $fields['time']) . '"></div>';
	echo '<div class="cmx-buchungen-field"><label>Puffer vorher</label><input class="widefat" type="number" min="0" step="5" name="cmx_buchung_buffer_before" value="' . \esc_attr((string) $fields['buffer_before']) . '"></div>';
	echo '<div class="cmx-buchungen-field"><label>Puffer nachher</label><input class="widefat" type="number" min="0" step="5" name="cmx_buchung_buffer_after" value="' . \esc_attr((string) $fields['buffer_after']) . '"></div>';
	echo '</div>';
	echo '</div>';
	echo '<script>
	(function(){
		var ajaxUrl = ' . \wp_json_encode(\admin_url('admin-ajax.php')) . ';
		var kontaktNonce = ' . \wp_json_encode(\wp_create_nonce('cmx_search_kontakte')) . ';

		function esc(str){
			return String(str || "").replace(/[&<>"\x27]/g, function(c){
				if(c === "&") return "&amp;";
				if(c === "<") return "&lt;";
				if(c === ">") return "&gt;";
				if(c.charCodeAt(0) === 34) return "&quot;";
				return "&#039;";
			});
		}
		function normalizeItems(type, payload){
			if(type === "kontakt"){
				var arr = payload && payload.success && payload.data ? payload.data.items : [];
				return Array.isArray(arr) ? arr.map(function(item){
					return {id: parseInt(item.id || 0, 10), label: item.title || "", sub: item.addr || ""};
				}) : [];
			}
			var artikel = Array.isArray(payload) ? payload : [];
			return artikel.map(function(item){
				return {id: parseInt(item.value || item.id || 0, 10), label: item.label || item.title || "", sub: item.nr || ""};
			});
		}
		function requestUrl(type, q){
			if(type === "kontakt"){
				return ajaxUrl + "?action=cmx_search_kontakte&_ajax_nonce=" + encodeURIComponent(kontaktNonce) + "&q=" + encodeURIComponent(q || "");
			}
			return ajaxUrl + "?action=cmx_search_artikel&term=" + encodeURIComponent(q || "");
		}
		function focusDateInput(){
			var dateInput = document.querySelector("input[name=\"cmx_buchung_start_date\"]");
			if(dateInput){
				window.setTimeout(function(){ dateInput.focus(); }, 40);
			}
		}
		function focusAndOpenSelect(select){
			if(!select) return;
			select.focus();
			if(typeof select.showPicker === "function"){
				try{ select.showPicker(); }catch(err){}
				return;
			}
			try{ select.dispatchEvent(new MouseEvent("mousedown", {bubbles:true, cancelable:true, view:window})); }catch(err){}
			try{ select.click(); }catch(err){}
		}
		function initSearch(root){
			if(!root || root.dataset.cmxBound === "1") return;
			root.dataset.cmxBound = "1";
			var type = root.getAttribute("data-cmx-buchung-search") || "";
			var input = root.querySelector(".cmx-buchungen-search-input");
			var hidden = root.querySelector("input[type=hidden]");
			var list = root.querySelector(".cmx-buchungen-search-results");
			if(!type || !input || !hidden || !list) return;
			var localItems = [];
			if(root.getAttribute("data-cmx-term-items") !== null){
				try{ localItems = JSON.parse(root.getAttribute("data-cmx-term-items") || "[]"); }catch(err){ localItems = []; }
			}

			var timer = null;
			var active = -1;
			var items = [];
			var selectedLabel = input.value || "";

			function closeList(){
				list.style.display = "none";
				list.innerHTML = "";
				active = -1;
				items = [];
			}
			function render(arr){
				items = Array.isArray(arr) ? arr.filter(function(item){ return item && item.id > 0 && item.label; }) : [];
				if(!items.length){ closeList(); return; }
				list.innerHTML = items.map(function(item, i){
					var sub = item.sub ? "<small>" + esc(item.sub) + "</small>" : "";
					return "<li data-index=\"" + i + "\">" + esc(item.label) + sub + "</li>";
				}).join("");
				list.style.display = "block";
				active = -1;
			}
			function search(q){
				if(localItems.length){
					var needle = String(q || "").toLowerCase();
					render(localItems.filter(function(item){
						return !needle || String(item.label || "").toLowerCase().indexOf(needle) !== -1;
					}).slice(0, 20));
					return;
				}
				window.fetch(requestUrl(type, q), {credentials:"same-origin"})
					.then(function(resp){ return resp.json(); })
					.then(function(payload){ render(normalizeItems(type, payload)); })
					.catch(closeList);
			}
			function schedule(){
				var q = input.value || "";
				if(q !== selectedLabel && hidden.value !== ""){
					hidden.value = "";
					hidden.dispatchEvent(new Event("change", {bubbles:true}));
				}
				window.clearTimeout(timer);
				timer = window.setTimeout(function(){ search(q); }, 180);
			}
			function choose(index){
				var item = items[index];
				if(!item) return;
				input.value = item.label;
				selectedLabel = item.label;
				hidden.value = String(item.id);
				hidden.dispatchEvent(new Event("change", {bubbles:true}));
				closeList();
				input.dispatchEvent(new Event("change", {bubbles:true}));
				function searchInputByHiddenName(name){
					var hiddenByName = document.querySelector("input[type=\"hidden\"][name=\"" + name + "\"]");
					var wrap = hiddenByName ? hiddenByName.closest(".cmx-buchungen-post-search") : null;
					return wrap ? wrap.querySelector(".cmx-buchungen-search-input") : null;
				}
				var nextType = type === "kontakt" ? "artikel" : (type === "artikel" ? "ressource" : (type === "ressource" ? "standort" : (type === "standort" ? "buchungstyp" : (type === "buchungstyp" ? "leistungskategorie" : ""))));
				var explicitNextInput = type === "ressource" ? searchInputByHiddenName("cmx_buchung_standort_term_id") : null;
				if(nextType){
					var nextRoot = document.querySelector(".cmx-buchungen-post-search[data-cmx-buchung-search=\"" + nextType + "\"]");
					var nextInput = explicitNextInput || (nextRoot ? nextRoot.querySelector(".cmx-buchungen-search-input") : null);
					if(nextInput){
						window.setTimeout(function(){
							nextInput.focus();
							nextInput.select();
							nextInput.dispatchEvent(new Event("focus", {bubbles:true}));
						}, 40);
				}
			}else if(type === "leistungskategorie"){
					var durationSelect = document.querySelector("select[name=\"cmx_buchung_dauer_term_id\"]");
					focusAndOpenSelect(durationSelect);
				}
			}
			function clearSelectionAndShowChoices(){
				window.clearTimeout(timer);
				input.value = "";
				selectedLabel = "";
				hidden.value = "";
				hidden.dispatchEvent(new Event("change", {bubbles:true}));
				input.dispatchEvent(new Event("change", {bubbles:true}));
				search("");
			}
			function move(delta){
				if(!items.length) return;
				active = (active + delta + items.length) % items.length;
				Array.prototype.forEach.call(list.children, function(li, i){
					li.classList.toggle("active", i === active);
				});
			}

			input.addEventListener("focus", function(){ search(input.value || ""); });
			input.addEventListener("input", schedule);
			input.addEventListener("keydown", function(e){
				if(e.key === "ArrowDown"){ e.preventDefault(); move(1); }
				else if(e.key === "ArrowUp"){ e.preventDefault(); move(-1); }
				else if(e.key === "Enter" && active >= 0){ e.preventDefault(); choose(active); }
				else if(e.key === "Escape"){
					e.preventDefault();
					if((hidden.value || "").trim() !== "" || (input.value || "").trim() !== ""){
						clearSelectionAndShowChoices();
					}else{
						closeList();
						input.blur();
					}
				}
			});
			list.addEventListener("mousedown", function(e){
				var li = e.target.closest ? e.target.closest("li") : null;
				if(!li) return;
				e.preventDefault();
				choose(parseInt(li.getAttribute("data-index") || "-1", 10));
			});
			document.addEventListener("mousedown", function(e){
				if(!root.contains(e.target)){ closeList(); }
			});
		}

		document.querySelectorAll(".cmx-buchungen-post-search").forEach(initSearch);
		var durationSelect = document.querySelector("select[name=\"cmx_buchung_dauer_term_id\"]");
		if(durationSelect && durationSelect.dataset.cmxNextFocus !== "1"){
			durationSelect.dataset.cmxNextFocus = "1";
			durationSelect.addEventListener("change", focusDateInput);
		}
		document.querySelectorAll("input[name=\"cmx_buchung_start_date\"],input[name=\"cmx_buchung_start_time\"]").forEach(function(input){
			if(input.dataset.cmxEscClear === "1") return;
			input.dataset.cmxEscClear = "1";
			input.addEventListener("keydown", function(e){
				if(e.key !== "Escape") return;
				if(input.value === "") return;
				e.preventDefault();
				input.value = "";
				input.dispatchEvent(new Event("change", {bubbles:true}));
			});
		});
		(function(){
			var dateInput = document.querySelector("input[name=\"cmx_buchung_start_date\"]");
			var timeInput = document.querySelector("input[name=\"cmx_buchung_start_time\"]");
			if(!dateInput || !timeInput) return;
			dateInput.addEventListener("change", function(){
				if(!dateInput.value) return;
				window.setTimeout(function(){ timeInput.focus(); }, 40);
			});
		})();
		(function(){
			var contactHidden = document.querySelector("input[name=\"cmx_buchung_kontakt_id\"]");
			var artikelHidden = document.querySelector("input[name=\"cmx_buchung_artikel_id\"]");
			var button = document.getElementById("cmx-buchungen-create-beleg-button");
			if(!contactHidden || !artikelHidden || !button) return;

			function hasId(input){
				var id = parseInt(input.value || "0", 10);
				return !isNaN(id) && id > 0;
			}
			function syncButton(){
				var active = hasId(contactHidden) && hasId(artikelHidden);
				button.classList.toggle("disabled", !active);
				button.setAttribute("aria-disabled", active ? "false" : "true");
				button.href = active ? (button.getAttribute("data-create-url") || "#") : "#";
			}
			[contactHidden, artikelHidden].forEach(function(input){
				input.addEventListener("change", syncButton);
			});
			button.addEventListener("click", function(e){
				if(button.classList.contains("disabled")){
					e.preventDefault();
				}
			});
			syncButton();
		})();
	})();
	</script>';
}

function cmx_buchungen_render_status_box(\WP_Post $post): void {
	$status = \sanitize_key((string) \get_post_meta($post->ID, CMX_BUCHUNGEN_META_STATUS, true));
	$status = isset(cmx_buchungen_status_options()[$status]) ? $status : 'angefragt';

	echo '<select class="widefat" name="cmx_buchung_status">';
	foreach (cmx_buchungen_status_options() as $value => $label) {
		echo '<option value="' . \esc_attr($value) . '"' . \selected($status, $value, false) . '>' . \esc_html($label) . '</option>';
	}
	echo '</select>';
}

function cmx_buchungen_render_mitarbeiter_box(\WP_Post $post): void {
	cmx_buchungen_term_select(CMX_BUCHUNGEN_TAX_MITARBEITER, 'cmx_buchung_mitarbeiter_term_id', (int) \get_post_meta($post->ID, CMX_BUCHUNGEN_META_MITARBEITER, true));
}

function cmx_buchungen_render_tokens_box(\WP_Post $post): void {
	$cancel_token = \trim((string) \get_post_meta($post->ID, CMX_BUCHUNGEN_META_CANCEL_TOKEN, true));
	$cancel_url = $cancel_token !== '' ? \home_url('/?cmx_buchung_cancel=' . \rawurlencode($cancel_token)) : '';

	echo '<p><strong>Storno-Link</strong><br>';
	if ($cancel_url !== '') {
		echo '<a href="' . \esc_url($cancel_url) . '" target="_blank" rel="noopener noreferrer">Storno-Link öffnen</a>';
	} else {
		echo 'wird beim Speichern erstellt';
	}
	echo '</p>';
}

\add_action('save_post_' . CMX_BUCHUNGEN_CPT, function (int $post_id, \WP_Post $post, bool $update): void {
	unset($update);
	if (\defined('DOING_AUTOSAVE') && \DOING_AUTOSAVE) {
		return;
	}
	if (!isset($_POST['cmx_buchungen_nonce']) || !\wp_verify_nonce((string) \wp_unslash($_POST['cmx_buchungen_nonce']), 'cmx_buchungen_save')) {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

	$map = [
		CMX_BUCHUNGEN_META_KONTAKT => ['cmx_buchung_kontakt_id', 'int'],
		CMX_BUCHUNGEN_META_ARTIKEL => ['cmx_buchung_artikel_id', 'int'],
		CMX_BUCHUNGEN_META_START_DATE => ['cmx_buchung_start_date', 'date'],
		CMX_BUCHUNGEN_META_START_TIME => ['cmx_buchung_start_time', 'time'],
		CMX_BUCHUNGEN_META_STATUS => ['cmx_buchung_status', 'status'],
		CMX_BUCHUNGEN_META_MITARBEITER => ['cmx_buchung_mitarbeiter_term_id', 'int'],
		CMX_BUCHUNGEN_META_RESSOURCE => ['cmx_buchung_ressource_term_id', 'int'],
		CMX_BUCHUNGEN_META_BUFFER_BEFORE => ['cmx_buchung_buffer_before', 'int'],
		CMX_BUCHUNGEN_META_BUFFER_AFTER => ['cmx_buchung_buffer_after', 'int'],
	];

	foreach ($map as $meta_key => [$field, $type]) {
		$raw = $_POST[$field] ?? '';
		$raw = \is_array($raw) ? '' : \wp_unslash($raw);
		$value = '';
		if ($type === 'int') {
			$value = (string) \max(0, (int) $raw);
		} elseif ($type === 'date') {
			$value = cmx_buchungen_sanitize_date($raw);
		} elseif ($type === 'time') {
			$value = cmx_buchungen_sanitize_time($raw);
		} elseif ($type === 'status') {
			$status = \sanitize_key((string) $raw);
			$value = isset(cmx_buchungen_status_options()[$status]) ? $status : 'angefragt';
		} elseif ($type === 'textarea') {
			$value = \sanitize_textarea_field((string) $raw);
		} else {
			$value = \sanitize_text_field((string) $raw);
		}

		if ($value === '' || $value === '0') {
			\delete_post_meta($post_id, (string) $meta_key);
		} else {
			\update_post_meta($post_id, (string) $meta_key, $value);
		}
	}

	$standort_id = isset($_POST['cmx_buchung_standort_term_id']) ? \max(0, (int) \wp_unslash($_POST['cmx_buchung_standort_term_id'])) : 0;
	if ($standort_id > 0 && \taxonomy_exists(CMX_BUCHUNGEN_TAX_STANDORT)) {
		\wp_set_object_terms($post_id, [$standort_id], CMX_BUCHUNGEN_TAX_STANDORT, false);
	} elseif (\taxonomy_exists(CMX_BUCHUNGEN_TAX_STANDORT)) {
		\wp_set_object_terms($post_id, [], CMX_BUCHUNGEN_TAX_STANDORT, false);
	}
	$buchungstyp_id = isset($_POST['cmx_buchung_typ_term_id']) ? \max(0, (int) \wp_unslash($_POST['cmx_buchung_typ_term_id'])) : 0;
	if ($buchungstyp_id > 0 && \taxonomy_exists(CMX_BUCHUNGEN_TAX_TYP)) {
		\wp_set_object_terms($post_id, [$buchungstyp_id], CMX_BUCHUNGEN_TAX_TYP, false);
	} elseif (\taxonomy_exists(CMX_BUCHUNGEN_TAX_TYP)) {
		\wp_set_object_terms($post_id, [], CMX_BUCHUNGEN_TAX_TYP, false);
	}
	$leistungskategorie_id = isset($_POST['cmx_buchung_leistungskategorie_term_id']) ? \max(0, (int) \wp_unslash($_POST['cmx_buchung_leistungskategorie_term_id'])) : 0;
	if ($leistungskategorie_id > 0 && \taxonomy_exists(CMX_BUCHUNGEN_TAX_LEISTUNGSKATEGORIE)) {
		\wp_set_object_terms($post_id, [$leistungskategorie_id], CMX_BUCHUNGEN_TAX_LEISTUNGSKATEGORIE, false);
	} elseif (\taxonomy_exists(CMX_BUCHUNGEN_TAX_LEISTUNGSKATEGORIE)) {
		\wp_set_object_terms($post_id, [], CMX_BUCHUNGEN_TAX_LEISTUNGSKATEGORIE, false);
	}
	$dauer_id = isset($_POST['cmx_buchung_dauer_term_id']) ? \max(0, (int) \wp_unslash($_POST['cmx_buchung_dauer_term_id'])) : cmx_buchungen_duration_term_id($post_id);
	if ($dauer_id > 0 && \taxonomy_exists(CMX_BUCHUNGEN_TAX_DAUER)) {
		\wp_set_object_terms($post_id, [$dauer_id], CMX_BUCHUNGEN_TAX_DAUER, false);
		\update_post_meta($post_id, CMX_BUCHUNGEN_META_DURATION, (string) cmx_buchungen_duration_from_term_id($dauer_id));
	} elseif (\taxonomy_exists(CMX_BUCHUNGEN_TAX_DAUER)) {
		\wp_set_object_terms($post_id, [], CMX_BUCHUNGEN_TAX_DAUER, false);
		\update_post_meta($post_id, CMX_BUCHUNGEN_META_DURATION, '60');
	}

	if (\trim((string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_BOOKING_TOKEN, true)) === '') {
		\update_post_meta($post_id, CMX_BUCHUNGEN_META_BOOKING_TOKEN, cmx_buchungen_token());
	}
	if (\trim((string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_CANCEL_TOKEN, true)) === '') {
		\update_post_meta($post_id, CMX_BUCHUNGEN_META_CANCEL_TOKEN, cmx_buchungen_token());
	}

	cmx_buchungen_sync_title($post_id);
	cmx_buchungen_schedule_reminder($post_id);
	cmx_buchungen_maybe_send_confirmation($post_id);
}, 20, 3);

function cmx_buchungen_generate_nummer(): string {
	$generator = __NAMESPACE__ . '\\cmx_generate_rechnungsnummer';
	if (\function_exists($generator)) {
		$number = \trim((string) \call_user_func($generator));
		if ($number !== '') {
			return $number;
		}
	}

	$format = \function_exists(__NAMESPACE__ . '\\cmx_ini_get_value')
		? \trim((string) cmx_ini_get_value('Belege', 'Format'))
		: '';
	if ($format === '') {
		$format = 'ymd-His';
	}

	return (string) \wp_date($format);
}

function cmx_buchungen_ensure_nummer(int $post_id): string {
	$number = \trim((string) \get_post_meta($post_id, '_cmx_buchung_nummer', true));
	if ($number === '') {
		$number = cmx_buchungen_generate_nummer();
		\update_post_meta($post_id, '_cmx_buchung_nummer', $number);
	}

	return $number;
}

function cmx_buchungen_sync_title(int $post_id): void {
	static $running = false;
	if ($running) {
		return;
	}
	$post = \get_post($post_id);
	if (!$post instanceof \WP_Post || (string) $post->post_type !== CMX_BUCHUNGEN_CPT) {
		return;
	}

	$title = \trim((string) cmx_buchungen_ensure_nummer($post_id));
	if ($title === '') {
		return;
	}
	$slug = \sanitize_title($title);
	if (\trim((string) $post->post_title) === $title && \trim((string) $post->post_name) === $slug) {
		return;
	}

	$running = true;
	\wp_update_post([
		'ID'         => $post_id,
		'post_title' => $title,
		'post_name'  => $slug,
	]);
	$running = false;
}
