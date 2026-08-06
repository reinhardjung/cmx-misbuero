<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_KONTAKT_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_KONTAKT_META', '_cmx_carent_kontakt_id');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_kontakt_post_types')) {
	function cmx_carent_kontakt_post_types(): array {
		$types = [];
		foreach (['kontakte', 'kontakt', 'contact'] as $post_type) {
			if (\post_type_exists($post_type)) {
				$types[] = $post_type;
			}
		}
		return $types;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_kontakt_list_url')) {
	function cmx_carent_kontakt_list_url(): string {
		$post_types = cmx_carent_kontakt_post_types();
		$post_type = (string) ($post_types[0] ?? 'kontakte');
		return (string) \admin_url('edit.php?post_type=' . $post_type);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_kontakt_header_url')) {
	function cmx_carent_kontakt_header_url(int $carent_id = 0): string {
		$list_url = cmx_carent_kontakt_list_url();
		if ($carent_id <= 0) {
			return $list_url;
		}

		$kontakt_id = (int) \get_post_meta($carent_id, CMX_CARENT_KONTAKT_META, true);
		if ($kontakt_id <= 0) {
			return $list_url;
		}

		$kontakt_type = (string) \get_post_type($kontakt_id);
		if (!\in_array($kontakt_type, cmx_carent_kontakt_post_types(), true)) {
			return $list_url;
		}

		return (string) \admin_url('post.php?post=' . $kontakt_id . '&action=edit');
	}
}

\add_action('add_meta_boxes', function (): void {
	$carent_id = 0;
	if (isset($_GET['post'])) {
		$carent_id = (int) $_GET['post'];
	} elseif (isset($_POST['post_ID'])) {
		$carent_id = (int) $_POST['post_ID'];
	}

	$kontakt_target_url = cmx_carent_kontakt_header_url($carent_id);
	$box_title = '<a id="cmx_carent_kontakt_box_link" href="' . \esc_url($kontakt_target_url) . '" target="_blank" rel="noopener noreferrer" onclick="if(window.cmxCarentKontaktOpen){return window.cmxCarentKontaktOpen(event);}event.stopPropagation();" style="font-size:13px;font-weight:inherit;line-height:inherit;color:#2271b1;text-decoration:none;">' . \esc_html__('Kontakt', 'cmx-misbuero') . '</a>';

	\add_meta_box(
		'cmx_carent_kontakt_box',
		$box_title,
		__NAMESPACE__ . '\\cmx_render_carent_kontakt_metabox',
		'carent',
		'side',
		'default'
	);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_carent_kontakt_metabox')) {
	function cmx_render_carent_kontakt_metabox(\WP_Post $post): void {
		$selected = (int) \get_post_meta($post->ID, CMX_CARENT_KONTAKT_META, true);
		$selected_title = $selected > 0 ? cmx_normalize_minus_sign((string) \get_the_title($selected)) : '';
		$list_url = cmx_carent_kontakt_list_url();
		$edit_prefix = (string) \admin_url('post.php?post=');
		$ajax_url = (string) \admin_url('admin-ajax.php');
		$ajax_nonce = (string) \wp_create_nonce('cmx_search_kontakte');
		$has_kontakte_args = [
			'post_type' => cmx_carent_kontakt_post_types(),
			'post_status' => 'any',
			'posts_per_page' => 1,
			'fields' => 'ids',
		];
		if (\function_exists(__NAMESPACE__ . '\\cmx_kontakte_apply_selection_query_args')) {
			$has_kontakte_args = cmx_kontakte_apply_selection_query_args($has_kontakte_args);
		}
		$has_kontakte = !empty(\get_posts($has_kontakte_args));
		$box_id = 'cmx-carent-kontakt-box-' . (int) $post->ID;

		\wp_nonce_field('cmx_carent_kontakt_save', 'cmx_carent_kontakt_nonce');

		echo '<style>
		#' . \esc_attr('cmx_carent_kontakt_box') . ',
		#' . \esc_attr('cmx_carent_kontakt_box') . ' .inside,
		#' . \esc_attr($box_id) . '{position:relative;overflow:visible}
		#' . \esc_attr($box_id) . ' .cmx-carent-kontakt-suggest{position:relative;overflow:visible}
		#' . \esc_attr($box_id) . ' .cmx-carent-kontakt-row{display:flex;align-items:center;gap:6px}
		#' . \esc_attr($box_id) . ' .cmx-carent-kontakt-row input[type=search]{flex:1 1 auto;min-width:0}
		#' . \esc_attr($box_id) . ' .cmx-carent-kontakt-results{
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
		#' . \esc_attr($box_id) . ' .cmx-carent-kontakt-results li{margin:0;padding:6px 8px;cursor:pointer}
		#' . \esc_attr($box_id) . ' .cmx-carent-kontakt-results li.active,
		#' . \esc_attr($box_id) . ' .cmx-carent-kontakt-results li:hover{background:#e5f3ff}
		</style>';

		echo '<div id="' . \esc_attr($box_id) . '" class="cmx-carent-kontakt-box">';
		echo '<div class="cmx-carent-kontakt-suggest">';
		echo '<div class="cmx-carent-kontakt-row">';
		echo '<input type="search" id="cmx_carent_kontakt_search" class="widefat" autocomplete="off" placeholder="' . \esc_attr__('suchen...', 'cmx-misbuero') . '" value="' . \esc_attr($selected_title) . '">';
		echo '<input type="hidden" name="cmx_carent_kontakt_id" id="cmx_carent_kontakt_id" value="' . \esc_attr((string) $selected) . '">';
		echo '</div>';
		echo '<ul id="cmx_carent_kontakt_suggest" class="cmx-carent-kontakt-results" style="display:none"></ul>';
		echo '</div>';
		echo '</div>';

		echo '<script>
		(function(){
			var root = document.getElementById(' . \wp_json_encode($box_id) . ');
			if (!root || root.dataset.cmxBound === "1") return;
			root.dataset.cmxBound = "1";
			var headerLink = document.getElementById("cmx_carent_kontakt_box_link");
			var searchInput = document.getElementById("cmx_carent_kontakt_search");
			var hiddenInput = document.getElementById("cmx_carent_kontakt_id");
			var listEl = document.getElementById("cmx_carent_kontakt_suggest");
			if (!headerLink || !searchInput || !hiddenInput || !listEl) return;

			var ajaxUrl = ' . \wp_json_encode($ajax_url) . ';
			var ajaxNonce = ' . \wp_json_encode($ajax_nonce) . ';
			var listUrl = ' . \wp_json_encode($list_url) . ';
			var editPrefix = ' . \wp_json_encode($edit_prefix) . ';
			var timer = null;
			var active = -1;
			var items = [];

			function selectedKontaktId(){
				var id = parseInt(hiddenInput.value || "0", 10);
				return (isNaN(id) || id <= 0) ? 0 : id;
			}
			function targetUrl(){
				var kontaktId = selectedKontaktId();
				return kontaktId > 0 ? (editPrefix + kontaktId + "&action=edit") : listUrl;
			}
			function syncHref(){
				headerLink.href = targetUrl();
			}
			function openCurrent(e){
				if (e) {
					e.preventDefault();
					e.stopPropagation();
				}
				syncHref();
				var href = headerLink.href || listUrl;
				if (!href) return false;
				var w = window.open(href, "_blank", "noopener,noreferrer");
				if (w) { w.opener = null; }
				return false;
			}
			function esc(str){
				return String(str || "").replace(/[&<>"\x27]/g, function(c){
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
				items = Array.isArray(arr) ? arr : [];
				if (!items.length) {
					listEl.innerHTML = "<li style=\"color:#646970;cursor:default;\">Keine Kontakte gefunden.</li>";
					listEl.style.display = "block";
					active = -1;
					return;
				}
				listEl.innerHTML = items.map(function(it, i){
					return "<li data-index=\"" + i + "\">" + esc(it && it.title ? it.title : "") + "</li>";
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
				syncHref();
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

			window.cmxCarentKontaktOpen = openCurrent;
			headerLink.addEventListener("mousedown", function(e){ e.stopPropagation(); }, true);
			headerLink.addEventListener("click", openCurrent, true);
			headerLink.addEventListener("auxclick", openCurrent, true);

			searchInput.addEventListener("input", function(){
				hiddenInput.value = "";
				syncHref();
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
			syncHref();
		})();
		</script>';

		if (!$has_kontakte) {
			$post_types = cmx_carent_kontakt_post_types();
			$new_kontakt_url = (string) \admin_url('post-new.php?post_type=' . (string) ($post_types[0] ?? 'kontakte'));
			$anlegen_link = '<a href="' . \esc_url($new_kontakt_url) . '" target="_blank" rel="noopener noreferrer">' . \esc_html__('anlegen', 'cmx-misbuero') . '</a>';
			$hint_html = \sprintf(\__('Keine Kontakte - zuerst einen %s.', 'cmx-misbuero'), $anlegen_link);
			echo '<p style="margin-top:8px;color:#666;">' . \wp_kses($hint_html, [
				'a' => [
					'href' => [],
					'target' => [],
					'rel' => [],
				],
			]) . '</p>';
		}
	}
}

\add_action('save_post_carent', function (int $post_id, \WP_Post $post, bool $update): void {
	unset($update);

	if (!isset($_POST['cmx_carent_kontakt_nonce']) || !\wp_verify_nonce((string) \wp_unslash($_POST['cmx_carent_kontakt_nonce']), 'cmx_carent_kontakt_save')) {
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

	$kontakt_id = isset($_POST['cmx_carent_kontakt_id']) ? (int) \wp_unslash($_POST['cmx_carent_kontakt_id']) : 0;
	if ($kontakt_id <= 0) {
		\delete_post_meta($post_id, CMX_CARENT_KONTAKT_META);
		return;
	}

	$valid_post_types = cmx_carent_kontakt_post_types();
	if ($valid_post_types === [] || !\in_array((string) \get_post_type($kontakt_id), $valid_post_types, true) || !\get_post_status($kontakt_id)) {
		\delete_post_meta($post_id, CMX_CARENT_KONTAKT_META);
		return;
	}

	\update_post_meta($post_id, CMX_CARENT_KONTAKT_META, $kontakt_id);
}, 20, 3);
