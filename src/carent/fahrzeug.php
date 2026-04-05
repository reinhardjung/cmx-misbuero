<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_META', '_cmx_carent_fahrzeug_id');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_post_type')) {
	function cmx_carent_fahrzeug_post_type(): string {
		return \post_type_exists('artikel') ? 'artikel' : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_list_url')) {
	function cmx_carent_fahrzeug_list_url(): string {
		$post_type = cmx_carent_fahrzeug_post_type();
		return (string) \admin_url('edit.php?post_type=' . ($post_type !== '' ? $post_type : 'artikel'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_display_label')) {
	function cmx_carent_fahrzeug_display_label(int $artikel_id): string {
		if ($artikel_id <= 0 || !\get_post_status($artikel_id)) {
			return '';
		}

		$title = \function_exists(__NAMESPACE__ . '\\cmx_normalize_minus_sign')
			? cmx_normalize_minus_sign((string) \get_the_title($artikel_id))
			: (string) \get_the_title($artikel_id);
		$nr = \function_exists(__NAMESPACE__ . '\\cmx_get_artikel_nr')
			? (string) cmx_get_artikel_nr($artikel_id)
			: '';

		return \trim(($nr !== '' ? $nr . ' – ' : '') . $title);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_header_url')) {
	function cmx_carent_fahrzeug_header_url(int $carent_id = 0): string {
		$list_url = cmx_carent_fahrzeug_list_url();
		if ($carent_id <= 0) {
			return $list_url;
		}

		$artikel_id = (int) \get_post_meta($carent_id, CMX_CARENT_FAHRZEUG_META, true);
		if ($artikel_id <= 0 || (string) \get_post_type($artikel_id) !== cmx_carent_fahrzeug_post_type()) {
			return $list_url;
		}

		return (string) \admin_url('post.php?post=' . $artikel_id . '&action=edit');
	}
}

\add_action('add_meta_boxes', function (): void {
	$carent_id = 0;
	if (isset($_GET['post'])) {
		$carent_id = (int) $_GET['post'];
	} elseif (isset($_POST['post_ID'])) {
		$carent_id = (int) $_POST['post_ID'];
	}

	$target_url = cmx_carent_fahrzeug_header_url($carent_id);
	$box_title = '<a id="cmx_carent_fahrzeug_box_link" href="' . \esc_url($target_url) . '" target="_blank" rel="noopener noreferrer" onclick="if(window.cmxCarentFahrzeugOpen){return window.cmxCarentFahrzeugOpen(event);}event.stopPropagation();" style="font-size:13px;font-weight:inherit;line-height:inherit;color:#2271b1;text-decoration:none;">' . \esc_html__('Fahrzeug', 'cmx-misbuero') . '</a>';

	\add_meta_box(
		'cmx_carent_fahrzeug_box',
		$box_title,
		__NAMESPACE__ . '\\cmx_render_carent_fahrzeug_metabox',
		'carent',
		'side',
		'default'
	);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_carent_fahrzeug_metabox')) {
	function cmx_render_carent_fahrzeug_metabox(\WP_Post $post): void {
		$selected = (int) \get_post_meta($post->ID, CMX_CARENT_FAHRZEUG_META, true);
		$selected_label = $selected > 0 ? cmx_carent_fahrzeug_display_label($selected) : '';
		$list_url = cmx_carent_fahrzeug_list_url();
		$edit_prefix = (string) \admin_url('post.php?post=');
		$ajax_url = (string) \admin_url('admin-ajax.php');
		$post_type = cmx_carent_fahrzeug_post_type();
		$has_artikel = ($post_type !== '') && !empty(\get_posts([
			'post_type' => $post_type,
			'post_status' => 'any',
			'posts_per_page' => 1,
			'fields' => 'ids',
		]));
		$box_id = 'cmx-carent-fahrzeug-box-' . (int) $post->ID;
		$search_id = 'cmx_carent_fahrzeug_search_' . (int) $post->ID;
		$hidden_id = 'cmx_carent_fahrzeug_id_' . (int) $post->ID;
		$list_id = 'cmx_carent_fahrzeug_suggest_' . (int) $post->ID;
		$link_id = 'cmx_carent_fahrzeug_box_link';

		\wp_nonce_field('cmx_carent_fahrzeug_save', 'cmx_carent_fahrzeug_nonce');

		echo '<style>
		#' . \esc_attr('cmx_carent_fahrzeug_box') . ',
		#' . \esc_attr('cmx_carent_fahrzeug_box') . ' .inside,
		#' . \esc_attr($box_id) . '{position:relative;overflow:visible}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-suggest{position:relative;overflow:visible}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-row{display:flex;align-items:center;gap:6px}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-row input[type=search]{flex:1 1 auto;min-width:0}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-results{
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
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-results li{margin:0;padding:6px 8px;cursor:pointer}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-results li.active,
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-results li:hover{background:#e5f3ff}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-results small{display:block;color:#646970}
		</style>';

		echo '<div id="' . \esc_attr($box_id) . '" class="cmx-carent-fahrzeug-box">';
		echo '<div class="cmx-carent-fahrzeug-suggest">';
		echo '<div class="cmx-carent-fahrzeug-row">';
		echo '<input type="search" id="' . \esc_attr($search_id) . '" class="widefat" autocomplete="off" placeholder="' . \esc_attr__('suchen...', 'cmx-misbuero') . '" value="' . \esc_attr($selected_label) . '">';
		echo '<input type="hidden" name="cmx_carent_fahrzeug_id" id="' . \esc_attr($hidden_id) . '" value="' . \esc_attr((string) $selected) . '">';
		echo '</div>';
		echo '<ul id="' . \esc_attr($list_id) . '" class="cmx-carent-fahrzeug-results" style="display:none"></ul>';
		echo '</div>';
		echo '</div>';

		echo '<script>
		(function(){
			var root = document.getElementById(' . \wp_json_encode($box_id) . ');
			if (!root || root.dataset.cmxBound === "1") return;
			root.dataset.cmxBound = "1";
			var headerLink = document.getElementById(' . \wp_json_encode($link_id) . ');
			var searchInput = document.getElementById(' . \wp_json_encode($search_id) . ');
			var hiddenInput = document.getElementById(' . \wp_json_encode($hidden_id) . ');
			var listEl = document.getElementById(' . \wp_json_encode($list_id) . ');
			if (!headerLink || !searchInput || !hiddenInput || !listEl) return;

			var ajaxUrl = ' . \wp_json_encode($ajax_url) . ';
			var listUrl = ' . \wp_json_encode($list_url) . ';
			var editPrefix = ' . \wp_json_encode($edit_prefix) . ';
			var timer = null;
			var active = -1;
			var items = [];

			function selectedArtikelId(){
				var id = parseInt(hiddenInput.value || "0", 10);
				return (isNaN(id) || id <= 0) ? 0 : id;
			}
			function targetUrl(){
				var artikelId = selectedArtikelId();
				return artikelId > 0 ? (editPrefix + artikelId + "&action=edit") : listUrl;
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
					listEl.innerHTML = "<li style=\"color:#646970;cursor:default;\">Keine Artikel gefunden.</li>";
					listEl.style.display = "block";
					active = -1;
					return;
				}
				listEl.innerHTML = items.map(function(it, i){
					var title = it && it.title ? String(it.title) : "";
					var nr = it && it.nr ? String(it.nr) : "";
					var label = it && it.label ? String(it.label) : ((nr ? nr + " – " : "") + title);
					return "<li data-index=\"" + i + "\"><span>" + esc(label) + "</span>" + (title && label !== title ? "<small>" + esc(title) + "</small>" : "") + "</li>";
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
			function formatItemLabel(item){
				var nr = item && item.nr ? String(item.nr) : "";
				var title = item && item.title ? String(item.title) : "";
				var label = item && item.label ? String(item.label) : ((nr ? nr + " – " : "") + title);
				return label || title;
			}
			function choose(item){
				hiddenInput.value = item && item.id ? String(item.id) : "";
				searchInput.value = formatItemLabel(item);
				syncHref();
				closeList();
				searchInput.focus();
			}
			function search(q){
				var url = ajaxUrl + "?action=cmx_search_artikel&term=" + encodeURIComponent(q || "");
				fetch(url, { credentials: "same-origin" }).then(function(r){
					return r.json();
				}).then(function(json){
					var rows = Array.isArray(json) ? json : [];
					render(rows.map(function(item){
						return {
							id: item && item.value ? item.value : 0,
							title: item && item.title ? item.title : "",
							nr: item && item.nr ? item.nr : "",
							label: item && item.label ? item.label : ""
						};
					}));
				}).catch(function(){
					closeList();
				});
			}

			window.cmxCarentFahrzeugOpen = openCurrent;
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

		if (!$has_artikel) {
			$new_url = (string) \admin_url('post-new.php?post_type=artikel');
			$anlegen_link = '<a href="' . \esc_url($new_url) . '" target="_blank" rel="noopener noreferrer">' . \esc_html__('anlegen', 'cmx-misbuero') . '</a>';
			$hint_html = \sprintf(\__('Keine Artikel - zuerst einen %s.', 'cmx-misbuero'), $anlegen_link);
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

	if (!isset($_POST['cmx_carent_fahrzeug_nonce']) || !\wp_verify_nonce((string) \wp_unslash($_POST['cmx_carent_fahrzeug_nonce']), 'cmx_carent_fahrzeug_save')) {
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

	$artikel_id = isset($_POST['cmx_carent_fahrzeug_id']) ? (int) \wp_unslash($_POST['cmx_carent_fahrzeug_id']) : 0;
	if ($artikel_id <= 0) {
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_META);
		return;
	}

	if ((string) \get_post_type($artikel_id) !== cmx_carent_fahrzeug_post_type() || !\get_post_status($artikel_id)) {
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_META);
		return;
	}

	\update_post_meta($post_id, CMX_CARENT_FAHRZEUG_META, $artikel_id);
}, 20, 3);
