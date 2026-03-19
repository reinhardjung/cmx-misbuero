<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_KONTAKTE_ZU_KONTAKT_MB_ID = 'cmx_kontakte_zu_kontakt_box';
const CMX_KONTAKTE_ZU_KONTAKT_META  = '_cmx_kontakte_zu_kontakt_id';

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
		$selected_id = (int) \get_post_meta($current_id, CMX_KONTAKTE_ZU_KONTAKT_META, true);
		if ($selected_id === $current_id) {
			$selected_id = 0;
		}
		$selected_title = $selected_id > 0 ? cmx_kontakte_zu_kontakt_title($selected_id) : '';
		$ajax_url = \admin_url('admin-ajax.php');
		$ajax_nonce = \wp_create_nonce('cmx_search_kontakte');
		$box_id = 'cmx-kontakte-zu-kontakt-box-' . $current_id;
		$search_id = 'cmx_kontakte_zu_kontakt_search_' . $current_id;
		$hidden_id = 'cmx_kontakte_zu_kontakt_id_' . $current_id;
		$list_id = 'cmx_kontakte_zu_kontakt_suggest_' . $current_id;

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
		#' . \esc_attr($box_id) . ' .cmx-kontakte-zu-kontakt-line2{display:block;margin-top:2px;font-size:11px;line-height:1.35;color:#646970}
		</style>';

		echo '<div id="' . \esc_attr($box_id) . '" class="cmx-kontakte-zu-kontakt-box">';
		echo '<div class="cmx-kontakte-zu-kontakt-suggest">';
		echo '<input type="search" id="' . \esc_attr($search_id) . '" class="widefat" autocomplete="off" placeholder="Kontakt suchen..." value="' . \esc_attr($selected_title) . '">';
		echo '<input type="hidden" name="cmx_kontakte_zu_kontakt_id" id="' . \esc_attr($hidden_id) . '" value="' . \esc_attr((string) $selected_id) . '">';
		echo '<ul id="' . \esc_attr($list_id) . '" class="cmx-kontakte-zu-kontakt-results" style="display:none"></ul>';
		echo '</div>';
		echo '</div>';

		echo '<script>
		(function(){
			var root = document.getElementById(' . \wp_json_encode($box_id) . ');
			if (!root || root.dataset.cmxBound === "1") return;
			root.dataset.cmxBound = "1";

			var currentId = ' . (int) $current_id . ';
			var searchInput = document.getElementById(' . \wp_json_encode($search_id) . ');
			var hiddenInput = document.getElementById(' . \wp_json_encode($hidden_id) . ');
			var listEl = document.getElementById(' . \wp_json_encode($list_id) . ');
			if (!searchInput || !hiddenInput || !listEl) return;

			var ajaxUrl = ' . \wp_json_encode($ajax_url) . ';
			var ajaxNonce = ' . \wp_json_encode($ajax_nonce) . ';
			var timer = null;
			var active = -1;
			var items = [];

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
			function render(arr){
				items = Array.isArray(arr) ? arr.filter(function(it){
					return Number(it && it.id || 0) > 0 && Number(it.id) !== currentId;
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
					return "<li data-index=\"" + i + "\"><span>" + title + "</span>" + (addr ? "<span class=\"cmx-kontakte-zu-kontakt-line2\">" + addr + "</span>" : "") + "</li>";
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
			function choose(item){
				hiddenInput.value = item && item.id ? String(item.id) : "";
				searchInput.value = item && item.title ? String(item.title) : "";
				closeList();
				searchInput.focus();
			}
			function search(q){
				var url = ajaxUrl + "?action=cmx_search_kontakte&_ajax_nonce=" + encodeURIComponent(ajaxNonce) + "&q=" + encodeURIComponent(q || "");
				fetch(url, { credentials: "same-origin" }).then(function(r){
					return r.json();
				}).then(function(json){
					if (!json || !json.success || !json.data || !Array.isArray(json.data.items)) {
						closeList();
						return;
					}
					render(json.data.items || []);
				}).catch(function(){
					closeList();
				});
			}

			searchInput.addEventListener("input", function(){
				hiddenInput.value = "";
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

	$val = isset($_POST['cmx_kontakte_zu_kontakt_id']) ? (int) $_POST['cmx_kontakte_zu_kontakt_id'] : 0;
	if ($val > 0 && $val !== $post_id && \get_post_type($val) === 'kontakte') {
		\update_post_meta($post_id, CMX_KONTAKTE_ZU_KONTAKT_META, $val);
		return;
	}

	\delete_post_meta($post_id, CMX_KONTAKTE_ZU_KONTAKT_META);
}, 10, 1);
