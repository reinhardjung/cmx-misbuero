<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/* =========================================================
 * Meta Keys (Defensive Definition)
 * ========================================================= */
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF'))        \define(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF', '_cmx_beleg_betreff');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG'))   \define(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG', '_cmx_beleg_beschreibung');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ID'))     \define(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ID', '_cmx_beleg_kontakt_id');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ADDR'))   \define(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ADDR', '_cmx_beleg_kontakt_addr');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_PROJEKT_LABEL'))  \define(__NAMESPACE__.'\\CMX_BELEG_META_PROJEKT_LABEL', '_cmx_beleg_projekt_label');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_LABEL'))  \define(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_LABEL', '_cmx_beleg_kontakt_label');
/* NEU: Projekt-ID (CPT projekte) */
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_PROJEKT_ID'))     \define(__NAMESPACE__.'\\CMX_BELEG_META_PROJEKT_ID', '_cmx_beleg_projekt_id');

/* =========================================================
 * Helpers (unverändert)
 * ========================================================= */
if (!\function_exists(__NAMESPACE__.'\\cmx_belege_tax')) {
	function cmx_belege_tax(): ?string {
		foreach (['belege_kategorien','belege_kategorie','beleg_kategorien','beleg_kategorie','belege_categories','belege_typ','belege_themen'] as $tax) {
			if (\taxonomy_exists($tax)) return $tax;
		}
		return null;
	}
}
if (!\function_exists(__NAMESPACE__.'\\cmx_kontakte_cpt')) {
	function cmx_kontakte_cpt(): string {
		if (\post_type_exists('kontakte')) return 'kontakte';
		if (\post_type_exists('kontakt'))  return 'kontakt';
		return 'kontakte';
	}
}
if (!\function_exists(__NAMESPACE__.'\\cmx_iso2_from_land')) {
	function cmx_iso2_from_land(int $kontakt_id): string {
		$meta_land = \strtoupper(\trim((string)\get_post_meta($kontakt_id, '_cmx_rechnung_land', true)));
		if (\preg_match('/^[A-Z]{2}$/', $meta_land)) return $meta_land;
		return 'CH';
	}
}

/* Rechnungsnummer + Titel-Logik wie gehabt ... */
if (!\function_exists(__NAMESPACE__ . '\\cmx_generate_rechnungsnummer')) {
	function cmx_generate_rechnungsnummer(): string {
		$dt = new \DateTime('now', new \DateTimeZone('Europe/Zurich'));
		return $dt->format(cmx_ini_get_value('Belege','Format'));
	}
}
if (!\function_exists(__NAMESPACE__.'\\cmx_ensure_rechnungsnummer')) {
	function cmx_ensure_rechnungsnummer(int $post_id): string {
		$no = (string)\get_post_meta($post_id, '_cmx_rechnungsnummer', true);
		if ($no === '') {
			$no = cmx_generate_rechnungsnummer();
			\update_post_meta($post_id, '_cmx_rechnungsnummer', $no);
		}
		return $no;
	}
}
if (!\function_exists(__NAMESPACE__.'\\cmx_initials_from_string')) {
	function cmx_initials_from_string(string $name): string {
		$clean = \preg_replace('/[^\p{L}\s-]+/u', '', $name ?? '');
		$parts = \preg_split('/[\s-]+/u', \trim((string)$clean), -1, PREG_SPLIT_NO_EMPTY);
		$letters = [];
		if (\is_array($parts)) {
			foreach ($parts as $p) {
				$first = \function_exists('mb_substr') ? \mb_substr($p, 0, 1, 'UTF-8') : \substr($p, 0, 1);
				if ($first !== '' && $first !== false) {
					$letters[] = \function_exists('mb_strtoupper') ? \mb_strtoupper($first, 'UTF-8') : \strtoupper($first);
				}
			}
		}
		$initials = \implode('', $letters);
		return $initials !== '' ? $initials : 'XXX';
	}
}
if (!\function_exists(__NAMESPACE__.'\\cmx_contact_prefix')) {
	function cmx_contact_prefix(int $post_id): string {
		if (isset($_POST['cmx_kontakt_search'])) {
			$label = \sanitize_text_field(\wp_unslash($_POST['cmx_kontakt_search']));
			$initials = cmx_initials_from_string($label);
			if ($initials !== 'XXX') return $initials;
		}
		$kid = 0;
		if (isset($_POST['cmx_kontakt_id'])) $kid = (int)$_POST['cmx_kontakt_id'];
		if ($kid <= 0) $kid = (int)\get_post_meta($post_id, CMX_BELEG_META_KONTAKT_ID, true);
		if ($kid > 0) {
			$kontakt = \get_post($kid);
			if ($kontakt && !\is_wp_error($kontakt)) {
				$initials = cmx_initials_from_string((string)$kontakt->post_title);
				if ($initials !== 'XXX') return $initials;
			}
		}
		return 'XXX';
	}
}

/* =========================================================
 * Metabox
 * ========================================================= */
\add_action('add_meta_boxes', function () {
	if (!\post_type_exists('belege')) return;
	\add_meta_box('cmx_beleg_details', 'Kopfdaten', __NAMESPACE__.'\\cmx_render_beleg_metabox', 'belege', 'normal', 'high');
});

/* =========================================================
 * AJAX: Projekte suchen (CPT projekte)
 * ========================================================= */
\add_action('wp_ajax_cmx_search_projekte', __NAMESPACE__.'\\cmx_ajax_search_projekte');
function cmx_ajax_search_projekte(): void {
	if (!\current_user_can('edit_posts')) \wp_send_json_error(['message'=>'forbidden'], 403);
	$nonce = isset($_GET['_ajax_nonce']) ? (string)$_GET['_ajax_nonce'] : '';
	if (!\wp_verify_nonce($nonce, 'cmx_search_projekte')) \wp_send_json_error(['message'=>'bad_nonce'], 403);

	$q = isset($_GET['q']) ? \sanitize_text_field(\wp_unslash($_GET['q'])) : '';
	$args = [
		'post_type'      => 'projekte',
		'post_status'    => 'any',
		's'              => $q,
		'posts_per_page' => 20,
		'fields'         => 'ids',
	];
	$ids = \get_posts($args);
	$out = [];
	foreach ($ids as $id) {
		$out[] = [
			'id'    => (int)$id,
			'title' => \get_the_title($id),
			'link'  => \get_edit_post_link($id, ''),
		];
	}
	\wp_send_json_success(['items'=>$out]);
}

/* =========================================================
 * Render-Funktion
 * ========================================================= */
function cmx_render_beleg_metabox(\WP_Post $post): void {
	$tax            = cmx_belege_tax();
	$betreff        = (string)\get_post_meta($post->ID, CMX_BELEG_META_BETREFF, true);
	$beschreibung   = (string)\get_post_meta($post->ID, CMX_BELEG_META_BESCHREIBUNG, true);
	$kontakt_id     = (int)\get_post_meta($post->ID, CMX_BELEG_META_KONTAKT_ID, true);
	$addr_text      = (string)\get_post_meta($post->ID, CMX_BELEG_META_KONTAKT_ADDR, true);
	$projekt_label  = (string)\get_post_meta($post->ID, CMX_BELEG_META_PROJEKT_LABEL, true);
	$kontakt_label  = (string)\get_post_meta($post->ID, CMX_BELEG_META_KONTAKT_LABEL, true);
	$projekt_id     = (int)\get_post_meta($post->ID, CMX_BELEG_META_PROJEKT_ID, true);

	\wp_nonce_field('cmx_beleg_details_save', 'cmx_beleg_details_nonce');

	$ajax_nonce = \wp_create_nonce('cmx_search_projekte');

	echo '<style>
		.cmx-grid{display:flex;gap:16px;flex-wrap:wrap}
		.cmx-col{flex:1 1 420px;min-width:320px}
		.cmx-col input[type=text],.cmx-col textarea{width:100%}
		.cmx-radio-inline label{display:inline-block;margin-right:12px}
		.cmx-addr{white-space:pre-wrap}
		/* Projektsuche Dropdown */
		.cmx-suggest{position:relative}
		.cmx-suggest ul{position:absolute;z-index:1000;left:0;right:0;max-height:240px;overflow:auto;margin:2px 0 0;padding:0;border:1px solid #ccd0d4;background:#fff;list-style:none}
		.cmx-suggest li{margin:0;padding:6px 8px;cursor:pointer}
		.cmx-suggest li:hover{background:#f3f4f5}
		.cmx-inline-actions{display:flex;gap:8px;align-items:center;margin-top:4px}
		.cmx-inline-actions small{opacity:.8}
	</style>';

	echo '<div class="cmx-grid">';

	/* --- linke Spalte --- */
	echo '<div class="cmx-col">';
	if ($tax) {
		$current_terms = \wp_get_post_terms($post->ID, $tax, ['fields'=>'ids']);
		$current_id = $current_terms[0] ?? 0;
		$terms = \get_terms(['taxonomy'=>$tax,'hide_empty'=>false]);
		echo '<p><strong>Kategorie</strong><br><div class="cmx-radio-inline">';
		foreach ($terms as $term) {
			echo '<label><input type="radio" name="cmx_beleg_kategorie" value="'.\esc_attr($term->term_id).'" '.\checked($current_id,$term->term_id,false).'> '.\esc_html($term->name).'</label>';
		}
		echo '</div></p>';
	}

	echo '<p><label><strong>Betreff</strong></label><br>';
	echo '<input type="text" name="cmx_beleg_betreff" value="'.\esc_attr($betreff).'"></p>';

	echo '<p><label><strong>Beschreibung</strong></label><br>';
	echo '<textarea name="cmx_beleg_beschreibung" rows="5">'.\esc_textarea($beschreibung).'</textarea></p>';
	echo '</div>';

	/* --- rechte Spalte --- */
	echo '<div class="cmx-col">';

	/* NEU: Projektsuche (CPT projekte) */
	$current_proj_title = $projekt_id ? \get_the_title($projekt_id) : '';
	$display_proj = $projekt_label ?: $current_proj_title;

	echo '<p><label><strong>Projekt (CPT „projekte“)</strong></label><br>';
	echo '<div class="cmx-suggest">';
	echo '<input type="text" id="cmx_projekt_search" name="cmx_projekt_search" autocomplete="off" ';
	echo 'value="'.\esc_attr($display_proj).'" placeholder="Projekt suchen...">';
	echo '<input type="hidden" id="cmx_projekt_id" name="cmx_projekt_id" value="'.\esc_attr((string)$projekt_id).'">';
	echo '<ul id="cmx_projekt_suggest" style="display:none"></ul>';
	echo '</div>';
	echo '<div class="cmx-inline-actions">';
	if ($projekt_id) {
		$edit = \get_edit_post_link($projekt_id, '');
		echo '<small>Gewählt: <strong>'.\esc_html($current_proj_title).'</strong></small>';
		if ($edit) echo '<a class="button button-small" href="'.\esc_url($edit).'">Öffnen</a>';
	}
	echo '<button type="button" class="button button-small" id="cmx_projekt_clear">Löschen</button>';
	echo '</div>';
	echo '</p>';

	/* Kontakt-Suche (wie gehabt) */
	$kontakte_pt = cmx_kontakte_cpt();
	echo '<p><label><strong>Kontakt</strong></label><br>';
	echo '<input type="text" id="cmx_kontakt_search" name="cmx_kontakt_search" list="cmx_kontakt_list" value="'.\esc_attr($kontakt_label).'" placeholder="Name...">';
	echo '<input type="hidden" id="cmx_kontakt_id" name="cmx_kontakt_id" value="'.\esc_attr($kontakt_id).'">';
	echo '<datalist id="cmx_kontakt_list">';
	$kontakte = \get_posts(['post_type'=>$kontakte_pt,'posts_per_page'=>500,'fields'=>'ids']);
	$contacts_map=[];$contacts_data=[];
	foreach($kontakte as $kid){
		$title=\get_the_title($kid);
		$str=\get_post_meta($kid,'_cmx_rechnung_strasse',true);
		$plz=\get_post_meta($kid,'_cmx_rechnung_plz',true);
		$ort=\get_post_meta($kid,'_cmx_rechnung_ort',true);
		$iso2=cmx_iso2_from_land($kid);
		$addr=\trim(\implode("\n",\array_filter([$title,$str,$iso2.'-'.$plz.' '.$ort])));
		$contacts_map[$title]=(int)$kid;
		$contacts_data[(int)$kid]=['addr'=>$addr];
		echo '<option value="'.\esc_attr($title).'"></option>';
	}
	echo '</datalist>';

	if ($addr_text === '' && $kontakt_id && isset($contacts_data[$kontakt_id])) $addr_text=$contacts_data[$kontakt_id]['addr'];
	echo '<p><label><strong>Postanschrift</strong></label><br>';
	echo '<textarea id="cmx_kontakt_addr" name="cmx_kontakt_addr" class="cmx-addr" rows="5">'.\esc_textarea($addr_text).'</textarea></p>';

	/* Inline JS (nur für diese Box) */
	$ajax_url = \admin_url('admin-ajax.php');
	echo '<script>
		const CMX_CONTACTS_MAP = '.\wp_json_encode($contacts_map).';
		const CMX_CONTACTS_DATA = '.\wp_json_encode($contacts_data).';
		(function(){
			/* Kontakt-Sync wie gehabt */
			const kI=document.getElementById("cmx_kontakt_search"),kH=document.getElementById("cmx_kontakt_id"),kA=document.getElementById("cmx_kontakt_addr");
			if(kI&&kH&&kA){kI.addEventListener("change",()=>{const id=CMX_CONTACTS_MAP[kI.value];if(id){kH.value=id;kA.value=(CMX_CONTACTS_DATA[id]||{}).addr||"";}else{kH.value="";}});}

			/* Projektsuche (AJAX) */
			const pI=document.getElementById("cmx_projekt_search");
			const pH=document.getElementById("cmx_projekt_id");
			const list=document.getElementById("cmx_projekt_suggest");
			const clearBtn=document.getElementById("cmx_projekt_clear");
			let timer=null;

			function hideList(){list.style.display="none"; list.innerHTML="";}
			function showList(){list.style.display="block";}

			function choose(item){
				pI.value=item.title;
				pH.value=item.id;
				hideList();
			}

			function render(items){
				if(!items || !items.length){hideList();return;}
				let html="";
				for(const it of items){
					html+=`<li data-id="${it.id}">${it.title.replace(/</g,"&lt;")}</li>`;
				}
				list.innerHTML=html;
				showList();
			}

			function search(q){
				const url = "'.\esc_js($ajax_url).'?action=cmx_search_projekte&_ajax_nonce='.\esc_js($ajax_nonce).'&q="+encodeURIComponent(q);
				fetch(url,{credentials:"same-origin"}).then(r=>r.json()).then(j=>{
					if(!j || !j.success){hideList();return;}
					render(j.data.items||[]);
				}).catch(()=>hideList());
			}

			if(pI && pH && list){
				pI.addEventListener("input", ()=>{
					pH.value=""; // solange keine Auswahl, keine ID
					const q=pI.value.trim();
					if(timer) clearTimeout(timer);
					if(q.length<2){hideList();return;}
					timer=setTimeout(()=>search(q), 200);
				});
				pI.addEventListener("keydown",(e)=>{
					if(e.key==="Escape"){hideList();}
				});
				list.addEventListener("click",(e)=>{
					const li=e.target.closest("li");
					if(!li) return;
					const id=parseInt(li.getAttribute("data-id"),10);
					const title=li.textContent;
					choose({id,title});
				});
				document.addEventListener("click",(e)=>{
					if(!list.contains(e.target) && e.target!==pI){hideList();}
				});
			}

			if(clearBtn && pI && pH){
				clearBtn.addEventListener("click", ()=>{
					pI.value=""; pH.value=""; hideList();
				});
			}
		})();
	</script>';

	echo '</div></div>';
}

/* =========================================================
 * Speichern
 * ========================================================= */
\add_action('save_post_belege', function (int $post_id, \WP_Post $post, bool $update) {
	if ($post->post_type !== 'belege') return;
	if (\wp_is_post_autosave($post_id) || \wp_is_post_revision($post_id)) return;
	if (!\current_user_can('edit_post', $post_id)) return;

	if (!empty($GLOBALS['cmx_belege_title_updating'])) return;

	$inv_no = cmx_ensure_rechnungsnummer($post_id);

	$has_nonce = isset($_POST['cmx_beleg_details_nonce']) && \wp_verify_nonce($_POST['cmx_beleg_details_nonce'], 'cmx_beleg_details_save');

	if ($has_nonce) {
		/* Kategorie (Taxonomie) */
		$tax = \function_exists(__NAMESPACE__.'\\cmx_belege_tax') ? cmx_belege_tax() : '';
		if ($tax && isset($_POST['cmx_beleg_kategorie'])) {
			$term_id = (int) $_POST['cmx_beleg_kategorie'];
			\wp_set_post_terms($post_id, $term_id > 0 ? [$term_id] : [], $tax, false);
		}

		/* Betreff / Beschreibung */
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF') && isset($_POST['cmx_beleg_betreff'])) {
			\update_post_meta($post_id, CMX_BELEG_META_BETREFF, \sanitize_text_field(\wp_unslash($_POST['cmx_beleg_betreff'])));
		}
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG') && isset($_POST['cmx_beleg_beschreibung'])) {
			\update_post_meta($post_id, CMX_BELEG_META_BESCHREIBUNG, \wp_kses_post(\wp_unslash($_POST['cmx_beleg_beschreibung'])));
		}

		/* Kontakt-ID + Adresse */
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ID') && isset($_POST['cmx_kontakt_id'])) {
			$kid = (int) $_POST['cmx_kontakt_id'];
			if ($kid > 0) \update_post_meta($post_id, CMX_BELEG_META_KONTAKT_ID, $kid);
			else \delete_post_meta($post_id, CMX_BELEG_META_KONTAKT_ID);
		}
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ADDR') && isset($_POST['cmx_kontakt_addr'])) {
			\update_post_meta($post_id, CMX_BELEG_META_KONTAKT_ADDR, \wp_kses_post(\wp_unslash($_POST['cmx_kontakt_addr'])));
		}

		/* NEU: Projekt (CPT projekte) */
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_PROJEKT_ID') && isset($_POST['cmx_projekt_id'])) {
			$pid = (int) $_POST['cmx_projekt_id'];
			if ($pid > 0) \update_post_meta($post_id, CMX_BELEG_META_PROJEKT_ID, $pid);
			else \delete_post_meta($post_id, CMX_BELEG_META_PROJEKT_ID);
		}
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_PROJEKT_LABEL') && isset($_POST['cmx_projekt_search'])) {
			$proj_label = \sanitize_text_field(\wp_unslash($_POST['cmx_projekt_search']));
			if ($proj_label !== '') \update_post_meta($post_id, CMX_BELEG_META_PROJEKT_LABEL, $proj_label);
			else \delete_post_meta($post_id, CMX_BELEG_META_PROJEKT_LABEL);
		}

		/* Label für Kontakt speichern (falls gewünscht) */
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_LABEL') && isset($_POST['cmx_kontakt_search'])) {
			$k_label = \sanitize_text_field(\wp_unslash($_POST['cmx_kontakt_search']));
			if ($k_label !== '') \update_post_meta($post_id, CMX_BELEG_META_KONTAKT_LABEL, $k_label);
			else \delete_post_meta($post_id, CMX_BELEG_META_KONTAKT_LABEL);
		}
	}

	/* Titel-Logik */
	$prefix_new = cmx_contact_prefix($post_id);
	$is_auto    = (int)\get_post_meta($post_id, '_cmx_title_auto', true) === 1;
	$prefix_old = (string)\get_post_meta($post_id, '_cmx_title_prefix', true);
	$current    = \get_post($post_id);

	$should_set_title =
		empty($current->post_title) || ($is_auto && $prefix_old !== $prefix_new);

	if ($should_set_title) {
		$new_title = $prefix_new . '-' . $inv_no;
		$GLOBALS['cmx_belege_title_updating'] = true;
		\wp_update_post([
			'ID'         => $post_id,
			'post_title' => $new_title,
			'post_name'  => \sanitize_title($new_title),
		]);
		unset($GLOBALS['cmx_belege_title_updating']);
		\update_post_meta($post_id, '_cmx_title_auto', 1);
		\update_post_meta($post_id, '_cmx_title_prefix', $prefix_new);
	} else {
		if (!$is_auto) {
			\delete_post_meta($post_id, '_cmx_title_auto');
			\delete_post_meta($post_id, '_cmx_title_prefix');
		}
	}
}, 10, 3);
