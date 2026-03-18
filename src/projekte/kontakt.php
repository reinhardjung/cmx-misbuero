<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Meta Box (SIDE): Kontakt-Auswahl für Projekte (Mis Büro)
 * - CPT Projekte:   projekte
 * - CPT Kontakte:   kontakte
 * - Speichert Meta: _cmx_projekt_kontakt_id (int)
 */


/**
 * Meta Box registrieren (SIDE)
 */
function cmx_projekt_kontakt_header_url(int $projekt_id = 0): string {
	$list_url = \admin_url('edit.php?post_type=kontakte');
	if ($projekt_id <= 0) {
		return $list_url;
	}

	$kontakt_id = (int) \get_post_meta($projekt_id, '_cmx_projekt_kontakt_id', true);
	if ($kontakt_id <= 0) {
		return $list_url;
	}

	$kontakt_type = (string) \get_post_type($kontakt_id);
	if (!\in_array($kontakt_type, ['kontakte', 'kontakt'], true)) {
		return $list_url;
	}

	return (string) \admin_url('post.php?post=' . $kontakt_id . '&action=edit');
}

add_action('add_meta_boxes', function () {
	$projekt_id = 0;
	if (isset($_GET['post'])) {
		$projekt_id = (int) $_GET['post'];
	} elseif (isset($_POST['post_ID'])) {
		$projekt_id = (int) $_POST['post_ID'];
	}
	$kontakt_target_url = cmx_projekt_kontakt_header_url($projekt_id);
	$box_title = '<a id="cmx_projekt_kontakt_box_link" href="' . \esc_url($kontakt_target_url) . '" target="_blank" rel="noopener noreferrer" onclick="if(window.cmxProjektKontaktOpen){return window.cmxProjektKontaktOpen(event);}event.stopPropagation();" style="font-size:13px;font-weight:inherit;line-height:inherit;color:#2271b1;text-decoration:none;">' . \esc_html__('Zugehöriger Kontakt', 'cmx') . '</a>';
	add_meta_box(
		'cmx_projekt_kontakt_box',
		$box_title,
		__NAMESPACE__ . '\\cmx_render_projekt_kontakt_box',
		'projekte',
		'side',
		'default'
	);
});

/**
 * Render: Dropdown mit Kontakten
 */
function cmx_render_projekt_kontakt_box(\WP_Post $post): void {
	$selected = (int) get_post_meta($post->ID, '_cmx_projekt_kontakt_id', true);
	$selected_title = $selected > 0 ? cmx_normalize_minus_sign((string) \get_the_title($selected)) : '';
	$list_url = \admin_url('edit.php?post_type=kontakte');
	$edit_prefix = \admin_url('post.php?post=');
	$ajax_url = \admin_url('admin-ajax.php');
	$ajax_nonce = \wp_create_nonce('cmx_search_kontakte');
	$has_kontakte = !empty(\get_posts([
		'post_type'      => 'kontakte',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	]));
	$box_id = 'cmx-projekt-kontakt-box-' . (int) $post->ID;

	wp_nonce_field('cmx_save_projekt_kontakt', 'cmx_projekt_kontakt_nonce');

	echo '<style>
	#' . \esc_attr('cmx_projekt_kontakt_box') . ',
	#' . \esc_attr('cmx_projekt_kontakt_box') . ' .inside,
	#' . \esc_attr($box_id) . '{position:relative;overflow:visible}
	#' . \esc_attr($box_id) . ' .cmx-projekt-kontakt-suggest{position:relative;overflow:visible}
	#' . \esc_attr($box_id) . ' .cmx-projekt-kontakt-row{display:flex;align-items:center;gap:6px}
	#' . \esc_attr($box_id) . ' .cmx-projekt-kontakt-row input[type=search]{flex:1 1 auto;min-width:0}
	#' . \esc_attr($box_id) . ' .cmx-projekt-kontakt-results{
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
	#' . \esc_attr($box_id) . ' .cmx-projekt-kontakt-results li{margin:0;padding:6px 8px;cursor:pointer}
	#' . \esc_attr($box_id) . ' .cmx-projekt-kontakt-results li.active,
	#' . \esc_attr($box_id) . ' .cmx-projekt-kontakt-results li:hover{background:#e5f3ff}
	</style>';
	echo '<div id="' . \esc_attr($box_id) . '" class="cmx-projekt-kontakt-box">';
	echo '<div class="cmx-projekt-kontakt-suggest">';
	echo '<div class="cmx-projekt-kontakt-row">';
	echo '<input type="search" id="cmx_projekt_kontakt_search" class="widefat" autocomplete="off" placeholder="Kontakt suchen..." value="' . \esc_attr($selected_title) . '">';
	echo '<input type="hidden" name="cmx_projekt_kontakt_id" id="cmx_projekt_kontakt_id" value="' . \esc_attr((string) $selected) . '">';
	echo '</div>';
	echo '<ul id="cmx_projekt_kontakt_suggest" class="cmx-projekt-kontakt-results" style="display:none"></ul>';
	echo '</div>';
	echo '</div>';
	echo '<script>
	(function(){
		var root = document.getElementById(' . \wp_json_encode($box_id) . ');
		if (!root || root.dataset.cmxBound === "1") return;
		root.dataset.cmxBound = "1";
		var headerLink = document.getElementById("cmx_projekt_kontakt_box_link");
		var searchInput = document.getElementById("cmx_projekt_kontakt_search");
		var hiddenInput = document.getElementById("cmx_projekt_kontakt_id");
		var listEl = document.getElementById("cmx_projekt_kontakt_suggest");
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

		window.cmxProjektKontaktOpen = openCurrent;
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

	// Kleiner Hinweis
	if (!$has_kontakte) {
		$new_kontakt_url = admin_url('post-new.php?post_type=kontakte');
		$anlegen_link = '<a href="' . esc_url($new_kontakt_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('anlegen', 'cmx') . '</a>';
		$hint_html = sprintf(__('Keine Kontakte - zuerst einen %s.', 'cmx'), $anlegen_link);
		echo '<p style="margin-top:8px;color:#666;">' . wp_kses($hint_html, [
			'a' => [
				'href'   => [],
				'target' => [],
				'rel'    => [],
			],
		]) . '</p>';
	}
}

/**
 * Save: Auswahl persistieren
 */
add_action('save_post_projekte', __NAMESPACE__ . '\\cmx_save_projekt_kontakt', 10, 1);
function cmx_save_projekt_kontakt(int $post_id): void {

	// Nonce prüfen
	if (
		! isset($_POST['cmx_projekt_kontakt_nonce']) ||
		! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cmx_projekt_kontakt_nonce'])), 'cmx_save_projekt_kontakt')
	) {
		return;
	}

	// Autosave / Rechte prüfen
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (! current_user_can('edit_post', $post_id)) return;

	// Wert aus POST
	$val = isset($_POST['cmx_projekt_kontakt_id']) ? (int) $_POST['cmx_projekt_kontakt_id'] : 0;

	// Nur existierende Kontakte akzeptieren
	if ($val > 0 && 'kontakte' === get_post_type($val)) {
		update_post_meta($post_id, '_cmx_projekt_kontakt_id', $val);
	} else {
		delete_post_meta($post_id, '_cmx_projekt_kontakt_id');
	}
}
