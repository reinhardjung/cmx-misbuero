<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_ZU_PROJEKT_MB_ID = 'cmx_zu_projekt';
const CMX_ZU_PROJEKT_NONCE_ACTION = 'cmx_zu_projekt_save';
const CMX_ZU_PROJEKT_NONCE_NAME = 'cmx_zu_projekt_nonce';
const CMX_ZU_PROJEKT_AJAX_ACTION = 'cmx_search_zu_projekt';
const CMX_ZU_PROJEKT_AJAX_NONCE_ACTION = 'cmx_zu_projekt_search';

if (!\function_exists(__NAMESPACE__ . '\\cmx_zu_projekt_supported_post_types')) {
	function cmx_zu_projekt_supported_post_types(): array {
		return ['kontakte', 'artikel', 'belege', 'dokumente'];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_zu_projekt_meta_key')) {
	function cmx_zu_projekt_meta_key(string $post_type): string {
		$map = [
			'kontakte'  => '_cmx_kontakt_projekte',
			'artikel'   => '_cmx_artikel_projekte',
			'belege'    => '_cmx_beleg_projekte',
			'dokumente' => 'cmx_dokumente_projekte',
		];
		return $map[$post_type] ?? '_cmx_projekte';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_zu_projekt_legacy_meta_keys')) {
	function cmx_zu_projekt_legacy_meta_keys(string $post_type): array {
		$map = [
			'belege' => ['_cmx_beleg_projekt_id', '_cmx_projekt_id', '_projekt_id'],
		];
		return $map[$post_type] ?? [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_zu_projekt_normalize_ids')) {
	/**
	 * @param mixed $value
	 * @return array<int,int>
	 */
	function cmx_zu_projekt_normalize_ids($value): array {
		$out = [];
		if (\is_array($value)) {
			foreach ($value as $raw) {
				$id = (int) $raw;
				if ($id > 0) {
					$out[] = $id;
				}
			}
		} else {
			$id = (int) $value;
			if ($id > 0) {
				$out[] = $id;
			}
		}
		return \array_values(\array_unique($out));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_zu_projekt_load_ids')) {
	/**
	 * @return array<int,int>
	 */
	function cmx_zu_projekt_load_ids(int $post_id, string $post_type): array {
		$meta_key = cmx_zu_projekt_meta_key($post_type);
		$ids = cmx_zu_projekt_normalize_ids(\get_post_meta($post_id, $meta_key, true));

		if (!empty($ids)) {
			return $ids;
		}

		foreach (cmx_zu_projekt_legacy_meta_keys($post_type) as $legacy_key) {
			$legacy_ids = cmx_zu_projekt_normalize_ids(\get_post_meta($post_id, $legacy_key, true));
			if (!empty($legacy_ids)) {
				return $legacy_ids;
			}
		}

		return [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_zu_projekt_metabox')) {
	function cmx_render_zu_projekt_metabox(\WP_Post $post): void {
		$post_type = (string) $post->post_type;
		$selected_ids = cmx_zu_projekt_load_ids((int) $post->ID, $post_type);
		$ajax_nonce = \wp_create_nonce(CMX_ZU_PROJEKT_AJAX_NONCE_ACTION);
		$box_id = 'cmx-zu-projekt-' . (int) $post->ID;

		\wp_nonce_field(CMX_ZU_PROJEKT_NONCE_ACTION, CMX_ZU_PROJEKT_NONCE_NAME);

		echo '<div id="' . \esc_attr($box_id) . '" class="cmx-zu-projekt-box">';
		// echo '<p style="margin:0 0 8px;">Projekt suchen und zuordnen.</p>';
		echo '<input type="search" class="widefat cmx-zu-projekt-search" placeholder="Projekt suchen...">';
		echo '<div class="cmx-zu-projekt-results" style="margin-top:8px;max-height:160px;overflow:auto;border:1px solid #dcdcde;border-radius:4px;display:none;"></div>';
		// echo '<p style="margin:10px 0 6px;font-weight:600;">Zugeordnete Projekte</p>';
		echo '<p style="margin:10px 0 6px;"></p>';
		echo '<ul class="cmx-zu-projekt-selected" style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px;">';
		foreach ($selected_ids as $projekt_id) {
			$projekt_id = (int) $projekt_id;
			if ($projekt_id <= 0 || \get_post_type($projekt_id) !== 'projekte' || !\get_post_status($projekt_id)) {
				continue;
			}
			$title = \get_the_title($projekt_id);
			if (!\is_string($title) || $title === '') {
				$title = '#' . $projekt_id;
			}
			$edit_url = \get_edit_post_link($projekt_id, 'raw');
			echo '<li data-id="' . (int) $projekt_id . '" style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;padding:2px 0;">';
			echo '<div style="min-width:0;">';
			if ($edit_url) {
				echo '<a href="' . \esc_url($edit_url) . '" target="_blank" rel="noopener noreferrer">' . \esc_html($title) . '</a>';
			} else {
				echo '<span>' . \esc_html($title) . '</span>';
			}
			echo '<input type="hidden" name="cmx_zu_projekt_ids[]" value="' . (int) $projekt_id . '">';
			echo '</div>';
			echo '<button type="button" class="button-link-delete cmx-zu-projekt-remove" style="line-height:1;">x</button>';
			echo '</li>';
		}
		echo '</ul>';

		echo '<script>(function(){';
		echo 'const root=document.getElementById(' . \wp_json_encode($box_id) . ');';
		echo 'if(!root||root.dataset.cmxBound==="1"){return;}root.dataset.cmxBound="1";';
		echo 'const searchInput=root.querySelector(".cmx-zu-projekt-search");';
		echo 'const results=root.querySelector(".cmx-zu-projekt-results");';
		echo 'const selected=root.querySelector(".cmx-zu-projekt-selected");';
		echo 'const ajaxUrl=' . \wp_json_encode(\admin_url('admin-ajax.php')) . ';';
		echo 'const ajaxNonce=' . \wp_json_encode($ajax_nonce) . ';';
		echo 'if(!searchInput||!results||!selected){return;}';
		echo 'let timer=null;';
		echo 'let activeIndex=-1;';
		echo 'let suppressBlurHide=false;';

		echo 'function escHtml(str){return String(str).replace(/[&<>"\']/g,function(c){if(c==="&"){return "&amp;";}if(c==="<"){return "&lt;";}if(c===">"){return "&gt;";}if(c.charCodeAt(0)===34){return "&quot;";}return "&#039;";});}';
		echo 'function hasSelected(id){return !!selected.querySelector(\'li[data-id="\'+String(id)+\'"]\');}';
		echo 'function ensureResultsVisible(show){results.style.display=show?"block":"none";}';
		echo 'function resultButtons(){return Array.prototype.slice.call(results.querySelectorAll(".cmx-zu-projekt-add"));}';
		echo 'function setActiveIndex(next){const btns=resultButtons();if(!btns.length){activeIndex=-1;return;}if(next<0){next=btns.length-1;}if(next>=btns.length){next=0;}activeIndex=next;btns.forEach(function(btn,idx){if(idx===activeIndex){btn.style.background="#e5f3ff";btn.setAttribute("aria-selected","true");try{btn.scrollIntoView({block:"nearest"});}catch(err){}}else{btn.style.background="#fff";btn.setAttribute("aria-selected","false");}});}';
		echo 'function renderResults(items){';
		echo 'if(!Array.isArray(items)||!items.length){activeIndex=-1;results.innerHTML=\'<div style="padding:6px 8px;color:#646970;">Keine Projekte gefunden.</div>\';ensureResultsVisible(true);return;}';
		echo 'const rows=[];';
		echo 'items.forEach(function(item){';
		echo 'const id=Number(item&&item.id||0);if(!id||hasSelected(id)){return;}';
		echo 'const title=(item&&item.title)?String(item.title):("#"+String(id));';
		echo 'rows.push(\'<button type="button" class="button-link cmx-zu-projekt-add" data-id="\'+id+\'" data-title="\'+escHtml(title)+\'" style="display:block;width:100%;text-align:left;padding:6px 8px;border:0;border-bottom:1px solid #f0f0f1;background:#fff;cursor:pointer;">\'+escHtml(title)+\'</button>\');';
		echo '});';
		echo 'results.innerHTML=rows.length?rows.join(""):\'<div style="padding:6px 8px;color:#646970;">Alle Treffer sind bereits zugeordnet.</div>\';';
		echo 'ensureResultsVisible(true);';
		echo 'if(rows.length){setActiveIndex(0);}else{activeIndex=-1;}';
		echo '}';

		echo 'function addSelected(id,title){';
		echo 'if(!id||hasSelected(id)){return;}';
		echo 'const safeTitle=title&&String(title).trim()!==""?String(title):("#"+String(id));';
		echo 'const li=document.createElement("li");';
		echo 'li.setAttribute("data-id",String(id));';
		echo 'li.style.cssText="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;padding:2px 0;";';
		echo 'const left=document.createElement("div");left.style.minWidth="0";';
		echo 'const link=document.createElement("a");link.href=' . \wp_json_encode(\admin_url('post.php')) . '+"?post="+encodeURIComponent(String(id))+"&action=edit";link.target="_blank";link.rel="noopener noreferrer";link.textContent=safeTitle;';
		echo 'const hidden=document.createElement("input");hidden.type="hidden";hidden.name="cmx_zu_projekt_ids[]";hidden.value=String(id);';
		echo 'left.appendChild(link);left.appendChild(hidden);';
		echo 'const btn=document.createElement("button");btn.type="button";btn.className="button-link-delete cmx-zu-projekt-remove";btn.style.lineHeight="1";btn.textContent="x";';
		echo 'li.appendChild(left);li.appendChild(btn);selected.appendChild(li);';
		echo '}';

		echo 'function search(q){';
		echo 'const url=new URL(ajaxUrl,window.location.origin);';
		echo 'url.searchParams.set("action",' . \wp_json_encode(CMX_ZU_PROJEKT_AJAX_ACTION) . ');';
		echo 'url.searchParams.set("_ajax_nonce",ajaxNonce);';
		echo 'url.searchParams.set("q",q||"");';
		echo 'fetch(url.toString(),{credentials:"same-origin"}).then(function(r){return r.json();}).then(function(json){';
		echo 'if(!json||!json.success||!json.data||!Array.isArray(json.data.items)){renderResults([]);return;}';
		echo 'renderResults(json.data.items);';
		echo '}).catch(function(){renderResults([]);});';
		echo '}';

		echo 'searchInput.addEventListener("input",function(){';
		echo 'const q=(searchInput.value||"").trim();';
		echo 'if(timer){clearTimeout(timer);}';
		echo 'timer=setTimeout(function(){search(q);},180);';
		echo '});';
		echo 'searchInput.addEventListener("focus",function(){if(results.style.display==="none"){search((searchInput.value||"").trim());}});';
		echo 'searchInput.addEventListener("blur",function(){setTimeout(function(){if(suppressBlurHide){suppressBlurHide=false;return;}ensureResultsVisible(false);},20);});';
		echo 'searchInput.addEventListener("keydown",function(e){';
		echo 'if(e.key==="ArrowDown"||e.key==="ArrowUp"){const btns=resultButtons();if(!btns.length){return;}e.preventDefault();if(e.key==="ArrowDown"){setActiveIndex(activeIndex+1);}else{setActiveIndex(activeIndex-1);}return;}';
		echo 'if(e.key==="Enter"){const btns=resultButtons();if(activeIndex>=0&&btns[activeIndex]){e.preventDefault();btns[activeIndex].click();}return;}';
		echo 'if(e.key==="Escape"){ensureResultsVisible(false);}';
		echo '});';
		echo 'results.addEventListener("mousedown",function(e){const btn=e.target&&e.target.closest?e.target.closest(".cmx-zu-projekt-add"):null;if(btn){suppressBlurHide=true;}});';
		echo 'results.addEventListener("mousemove",function(e){const btn=e.target&&e.target.closest?e.target.closest(".cmx-zu-projekt-add"):null;if(!btn){return;}const btns=resultButtons();const idx=btns.indexOf(btn);if(idx>=0){setActiveIndex(idx);}});';

		echo 'root.addEventListener("click",function(e){';
		echo 'const addBtn=e.target&&e.target.closest?e.target.closest(".cmx-zu-projekt-add"):null;';
		echo 'if(addBtn){e.preventDefault();const id=Number(addBtn.getAttribute("data-id")||0);const title=addBtn.getAttribute("data-title")||"";if(id){addSelected(id,title);addBtn.remove();if(!results.querySelector(".cmx-zu-projekt-add")){activeIndex=-1;results.innerHTML=\'<div style="padding:6px 8px;color:#646970;">Alle Treffer sind bereits zugeordnet.</div>\';}else{setActiveIndex(0);}}return;}';
		echo 'const removeBtn=e.target&&e.target.closest?e.target.closest(".cmx-zu-projekt-remove"):null;';
		echo 'if(removeBtn){e.preventDefault();const li=removeBtn.closest("li[data-id]");if(li){li.remove();}}';
		echo '});';
		echo '})();</script>';
		echo '</div>';
	}
}

\add_action('add_meta_boxes', function ($post_type) {
	$post_type = (string) $post_type;
	if (!\in_array($post_type, cmx_zu_projekt_supported_post_types(), true)) {
		return;
	}
	\add_meta_box(
		CMX_ZU_PROJEKT_MB_ID,
		'Zuordnen zum Projekt',
		__NAMESPACE__ . '\\cmx_render_zu_projekt_metabox',
		$post_type,
		'side',
		'default'
	);
}, 10, 1);

\add_action('wp_ajax_' . CMX_ZU_PROJEKT_AJAX_ACTION, function (): void {
	if (!\current_user_can('edit_posts')) {
		\wp_send_json_error(['message' => 'forbidden'], 403);
	}

	$nonce = isset($_GET['_ajax_nonce']) ? (string) $_GET['_ajax_nonce'] : '';
	if (!\wp_verify_nonce($nonce, CMX_ZU_PROJEKT_AJAX_NONCE_ACTION)) {
		\wp_send_json_error(['message' => 'bad_nonce'], 403);
	}

	$q = isset($_GET['q']) ? \sanitize_text_field(\wp_unslash((string) $_GET['q'])) : '';
	$args = [
		'post_type'      => 'projekte',
		'post_status'    => ['publish', 'draft', 'pending', 'private'],
		'posts_per_page' => 20,
		'fields'         => 'ids',
		'orderby'        => $q === '' ? 'modified' : 'title',
		'order'          => $q === '' ? 'DESC' : 'ASC',
		'no_found_rows'  => true,
	];
	if ($q !== '') {
		$args['s'] = $q;
	}

	$ids = \get_posts($args);
	$items = [];
	foreach ((array) $ids as $id) {
		$id = (int) $id;
		if ($id <= 0) {
			continue;
		}
		$title = \get_the_title($id);
		if (!\is_string($title) || $title === '') {
			$title = '#' . $id;
		}
		$items[] = [
			'id'    => $id,
			'title' => $title,
			'link'  => \get_edit_post_link($id, ''),
		];
	}

	\wp_send_json_success(['items' => $items]);
});

\add_action('save_post', function ($post_id, $post): void {
	if (!($post instanceof \WP_Post)) {
		return;
	}

	$post_type = (string) $post->post_type;
	if (!\in_array($post_type, cmx_zu_projekt_supported_post_types(), true)) {
		return;
	}

	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}
	if (!isset($_POST[CMX_ZU_PROJEKT_NONCE_NAME]) || !\wp_verify_nonce((string) $_POST[CMX_ZU_PROJEKT_NONCE_NAME], CMX_ZU_PROJEKT_NONCE_ACTION)) {
		return;
	}

	$raw = $_POST['cmx_zu_projekt_ids'] ?? [];
	$ids = cmx_zu_projekt_normalize_ids($raw);
	$clean = [];
	foreach ($ids as $id) {
		$id = (int) $id;
		if ($id > 0 && \get_post_type($id) === 'projekte') {
			$clean[] = $id;
		}
	}
	$clean = \array_values(\array_unique($clean));

	$meta_key = cmx_zu_projekt_meta_key($post_type);
	if (empty($clean)) {
		\delete_post_meta($post_id, $meta_key);
	} else {
		\update_post_meta($post_id, $meta_key, $clean);
	}
}, 10, 2);
