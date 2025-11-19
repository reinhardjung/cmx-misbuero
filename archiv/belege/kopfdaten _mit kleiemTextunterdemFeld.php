<?php
/**
 * Plugin Name: CMX Belege – Kopfdaten mit Projektsuche & Kontaktsuche (AJAX, Inline-Buttons, Keyboard-Navigation)
 * Description: Metabox für CPT "belege" mit AJAX-Suche (Projekte & Kontakte), Inline-"Löschen", Keyboard-Navigation (↑/↓/Enter/Esc), Auto-Fill: Kontakt/Adresse aus Projekt, Betreff aus Projekt-URL. Titel = nur Rechnungsnummer.
 * Author: CLOUDMEISTER
 */

namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

/* =========================================================
 * Meta Keys (Defensive Definition)
 * ========================================================= */
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF'))        \define(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF', '_cmx_beleg_betreff');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG'))   \define(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG', '_cmx_beleg_beschreibung');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ID'))     \define(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ID', '_cmx_beleg_kontakt_id');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ADDR'))   \define(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ADDR', '_cmx_beleg_kontakt_addr');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_PROJEKT_LABEL'))  \define(__NAMESPACE__.'\\CMX_BELEG_META_PROJEKT_LABEL', '_cmx_beleg_projekt_label');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_LABEL'))  \define(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_LABEL', '_cmx_beleg_kontakt_label');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_PROJEKT_ID'))     \define(__NAMESPACE__.'\\CMX_BELEG_META_PROJEKT_ID', '_cmx_beleg_projekt_id');

/* =========================================================
 * Helpers
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

/** Rechnungsnummer (Format aus INI, externe Helperfunktion vorausgesetzt) */
if (!\function_exists(__NAMESPACE__ . '\\cmx_generate_rechnungsnummer')) {
	function cmx_generate_rechnungsnummer(): string {
		$dt = new \DateTime('now', new \DateTimeZone('Europe/Zurich'));
		return $dt->format(cmx_ini_get_value('Belege','Format'));
	}
}

/** Sicherstellen, dass _cmx_rechnungsnummer vorhanden ist */
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

/* =========================================================
 * Metabox registrieren
 * ========================================================= */
\add_action('add_meta_boxes', function () {
	if (!\post_type_exists('belege')) return;
	\add_meta_box('cmx_beleg_details', 'Kopfdaten', __NAMESPACE__.'\\cmx_render_beleg_metabox', 'belege', 'normal', 'high');
});

/* =========================================================
 * AJAX: Projekte & Kontakte suchen
 * - Projekte liefern zusätzlich: kontakt_id, kontakt_title, kontakt_addr, url (falls vorhanden)
 * ========================================================= */
\add_action('wp_ajax_cmx_search_projekte', __NAMESPACE__.'\\cmx_ajax_search_projekte');
function cmx_ajax_search_projekte(): void {
	if (!\current_user_can('edit_posts')) \wp_send_json_error(['message'=>'forbidden'], 403);
	$nonce = isset($_GET['_ajax_nonce']) ? (string)$_GET['_ajax_nonce'] : '';
	if (!\wp_verify_nonce($nonce, 'cmx_search_projekte')) \wp_send_json_error(['message'=>'bad_nonce'], 403);

	$q = isset($_GET['q']) ? \sanitize_text_field(\wp_unslash($_GET['q'])) : '';
	$ids = \get_posts([
		'post_type'      => 'projekte',
		'post_status'    => 'any',
		's'              => $q,
		'posts_per_page' => 20,
		'fields'         => 'ids',
	]);
	$out = [];
	foreach ($ids as $id) {
		$title = \get_the_title($id);

		// Verknüpfter Kontakt (mehrere mögliche Keys absichern)
		$kontakt_id = (int) (
			\get_post_meta($id, '_cmx_projekt_kontakt_id', true) ?:
			\get_post_meta($id, '_cmx_kontakt_id', true) ?:
			\get_post_meta($id, 'kontakt_id', true)
		);
		$kontakt_title = $kontakt_id ? \get_the_title($kontakt_id) : '';
		$kontakt_addr  = '';
		if ($kontakt_id) {
			$str  = \get_post_meta($kontakt_id, '_cmx_rechnung_strasse', true);
			$plz  = \get_post_meta($kontakt_id, '_cmx_rechnung_plz', true);
			$ort  = \get_post_meta($kontakt_id, '_cmx_rechnung_ort', true);
			$iso2 = cmx_iso2_from_land($kontakt_id);
			$kontakt_addr = \trim(\implode("\n", \array_filter([$kontakt_title, $str, $iso2.'-'.$plz.' '.$ort])));
		}

		// Projekt-URL (mehrere mögliche Keys absichern)
		$url = (string) (
			\get_post_meta($id, '_cmx_projekt_url', true) ?:
			\get_post_meta($id, 'projekt_url', true) ?:
			\get_post_meta($id, '_cmx_url', true)
		);

		$out[] = [
			'id'            => (int)$id,
			'title'         => $title,
			'link'          => \get_edit_post_link($id, ''),
			'kontakt_id'    => $kontakt_id,
			'kontakt_title' => $kontakt_title,
			'kontakt_addr'  => $kontakt_addr,
			'url'           => $url,
		];
	}
	\wp_send_json_success(['items'=>$out]);
}

\add_action('wp_ajax_cmx_search_kontakte', __NAMESPACE__.'\\cmx_ajax_search_kontakte');
function cmx_ajax_search_kontakte(): void {
	if (!\current_user_can('edit_posts')) \wp_send_json_error(['message'=>'forbidden'], 403);
	$nonce = isset($_GET['_ajax_nonce']) ? (string)$_GET['_ajax_nonce'] : '';
	if (!\wp_verify_nonce($nonce, 'cmx_search_kontakte')) \wp_send_json_error(['message'=>'bad_nonce'], 403);

	$q   = isset($_GET['q']) ? \sanitize_text_field(\wp_unslash($_GET['q'])) : '';
	$cpt = cmx_kontakte_cpt();
	$ids = \get_posts([
		'post_type'      => $cpt,
		'post_status'    => 'any',
		's'              => $q,
		'posts_per_page' => 20,
		'fields'         => 'ids',
	]);
	$out = [];
	foreach ($ids as $id) {
		$title = \get_the_title($id);
		$str   = \get_post_meta($id,'_cmx_rechnung_strasse',true);
		$plz   = \get_post_meta($id,'_cmx_rechnung_plz',true);
		$ort   = \get_post_meta($id,'_cmx_rechnung_ort',true);
		$iso2  = cmx_iso2_from_land($id);
		$addr  = \trim(\implode("\n", \array_filter([$title, $str, $iso2.'-'.$plz.' '.$ort])));
		$out[] = [
			'id'    => (int)$id,
			'title' => $title,
			'addr'  => $addr,
			'link'  => \get_edit_post_link($id, ''),
		];
	}
	\wp_send_json_success(['items'=>$out]);
}

/* =========================================================
 * Render-Funktion Metabox
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

	$ajax_nonce_proj = \wp_create_nonce('cmx_search_projekte');
	$ajax_nonce_kont = \wp_create_nonce('cmx_search_kontakte');

	echo '<style>
		.cmx-grid{display:flex;gap:16px;flex-wrap:wrap}
		.cmx-col{flex:1 1 420px;min-width:320px}
		.cmx-col input[type=text],.cmx-col textarea{width:100%}
		.cmx-radio-inline label{display:inline-block;margin-right:12px}
		.cmx-addr{white-space:pre-wrap}
		/* Suggest UX */
		.cmx-suggest{position:relative}
		.cmx-input-row{display:flex;align-items:center;gap:6px}
		.cmx-input-row input[type=text]{flex:1 1 auto;min-width:0}
		.cmx-suggest ul{position:absolute;z-index:1000;left:0;right:0;max-height:240px;overflow:auto;margin:2px 0 0;padding:0;border:1px solid #ccd0d4;background:#fff;list-style:none}
		.cmx-suggest li{margin:0;padding:6px 8px;cursor:pointer}
		.cmx-suggest li.active{background:#e5f3ff}
		.cmx-suggest li:hover{background:#f3f4f5}
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
	echo '<input type="text" id="cmx_beleg_betreff" name="cmx_beleg_betreff" value="'.\esc_attr($betreff).'"></p>';

	echo '<p><label><strong>Beschreibung</strong></label><br>';
	echo '<textarea name="cmx_beleg_beschreibung" rows="5">'.\esc_textarea($beschreibung).'</textarea></p>';
	echo '</div>';

	/* --- rechte Spalte --- */
	echo '<div class="cmx-col">';

	/* Projektsuche */
	$current_proj_title = $projekt_id ? \get_the_title($projekt_id) : '';
	$display_proj = $projekt_label ?: $current_proj_title;

	echo '<p><label><strong>Projekt (CPT „projekte“)</strong></label><br>';
	echo '<div class="cmx-suggest">';
	echo '  <div class="cmx-input-row">';
	echo '    <input type="text" id="cmx_projekt_search" name="cmx_projekt_search" autocomplete="off" value="'.\esc_attr($display_proj).'" placeholder="Projekt suchen...">';
	echo '    <input type="hidden" id="cmx_projekt_id" name="cmx_projekt_id" value="'.\esc_attr((string)$projekt_id).'">';
	echo '    <button type="button" class="button button-small" id="cmx_projekt_clear" title="Auswahl löschen">Löschen</button>';
	echo '  </div>';
	echo '  <ul id="cmx_projekt_suggest" style="display:none"></ul>';
	echo '</div>';
	if ($projekt_id) {
		$edit = \get_edit_post_link($projekt_id, '');
		echo '<small>Gewählt: <strong>'.\esc_html($current_proj_title).'</strong> ';
		if ($edit) echo '– <a href="'.\esc_url($edit).'">Öffnen</a>';
		echo '</small>';
	}
	echo '</p>';

	/* Kontaktsuche (AJAX) – mit Adress-Fill */
	$kontakte_title = $kontakt_id ? \get_the_title($kontakt_id) : '';
	$display_kontakt = $kontakt_label ?: $kontakte_title;

	echo '<p><label><strong>Kontakt</strong></label><br>';
	echo '<div class="cmx-suggest">';
	echo '  <div class="cmx-input-row">';
	echo '    <input type="text" id="cmx_kontakt_search" name="cmx_kontakt_search" autocomplete="off" value="'.\esc_attr($display_kontakt).'" placeholder="Kontakt suchen...">';
	echo '    <input type="hidden" id="cmx_kontakt_id" name="cmx_kontakt_id" value="'.\esc_attr((string)$kontakt_id).'">';
	echo '    <button type="button" class="button button-small" id="cmx_kontakt_clear" title="Auswahl löschen">Löschen</button>';
	echo '  </div>';
	echo '  <ul id="cmx_kontakt_suggest" style="display:none"></ul>';
	echo '</div>';
	if ($kontakt_id) {
		$edit = \get_edit_post_link($kontakt_id, '');
		echo '<small>Gewählt: <strong>'.\esc_html($kontakte_title).'</strong> ';
		if ($edit) echo '– <a href="'.\esc_url($edit).'">Öffnen</a>';
		echo '</small>';
	}

	/* Adresse (Textarea) */
	if ($addr_text === '' && $kontakt_id) {
		$str   = \get_post_meta($kontakt_id,'_cmx_rechnung_strasse',true);
		$plz   = \get_post_meta($kontakt_id,'_cmx_rechnung_plz',true);
		$ort   = \get_post_meta($kontakt_id,'_cmx_rechnung_ort',true);
		$iso2  = cmx_iso2_from_land($kontakt_id);
		$addr_text = \trim(\implode("\n", \array_filter([$kontakte_title, $str, $iso2.'-'.$plz.' '.$ort])));
	}
	echo '<p><label><strong>Postanschrift</strong></label><br>';
	echo '<textarea id="cmx_kontakt_addr" name="cmx_kontakt_addr" class="cmx-addr" rows="5">'.\esc_textarea($addr_text).'</textarea></p>';

	/* Inline JS */
	$ajax_url = \admin_url('admin-ajax.php');
	echo '<script>
		(function(){
			/* ===== Utility ===== */
			function esc(s){return (s||"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");}
			function makeNavigator(inputEl, listEl, chooseCb){
				let active = -1;
				let items  = [];
				function render(arr){
					items = arr||[];
					if(!items.length){listEl.style.display="none"; listEl.innerHTML=""; active=-1; return;}
					listEl.innerHTML = items.map((it,i)=>`<li data-index="${i}">${esc(it.title)}</li>`).join("");
					listEl.style.display="block"; active=-1;
				}
				function move(delta){
					if(!items.length) return;
					active = (active + delta + items.length) % items.length;
					[...listEl.children].forEach((li,i)=>li.classList.toggle("active", i===active));
				}
				function choose(idx){
					if(idx<0 || idx>=items.length) return;
					chooseCb(items[idx]);
					listEl.style.display="none"; listEl.innerHTML=""; active=-1;
				}
				// Mouse selection
				listEl.addEventListener("mousedown", (e)=>{
					const li=e.target.closest("li"); if(!li) return;
					e.preventDefault();
					choose(parseInt(li.dataset.index,10));
				});
				// Keyboard
				inputEl.addEventListener("keydown",(e)=>{
					if(listEl.style.display!=="block" && (e.key==="ArrowDown"||e.key==="ArrowUp")) return; // wenn zu, ignorieren
					if(e.key==="ArrowDown"){ e.preventDefault(); move(1); }
					else if(e.key==="ArrowUp"){ e.preventDefault(); move(-1); }
					else if(e.key==="Enter"){ if(active>-1){ e.preventDefault(); choose(active);} }
					else if(e.key==="Escape"){ listEl.style.display="none"; listEl.innerHTML=""; active=-1; }
				});
				// Click outside
				document.addEventListener("click",(e)=>{
					if(!listEl.contains(e.target) && e.target!==inputEl){ listEl.style.display="none"; listEl.innerHTML=""; active=-1; }
				});
				return { render, chooseIndex: choose, reset: ()=>{items=[];active=-1;} };
			}

			/* ===== Elemente ===== */
			const betInput = document.getElementById("cmx_beleg_betreff");

			/* --- Projekte --- */
			const pI=document.getElementById("cmx_projekt_search");
			const pH=document.getElementById("cmx_projekt_id");
			const pL=document.getElementById("cmx_projekt_suggest");
			const pC=document.getElementById("cmx_projekt_clear");
			let pT=null;
			const projNav = makeNavigator(pI, pL, chooseProject);

			function chooseProject(it){
				pI.value=it.title||""; pH.value=it.id||""; pI.focus();
				// B: Kontakt aus Projekt übernehmen
				if(it.kontakt_id){
					const kI=document.getElementById("cmx_kontakt_search");
					const kH=document.getElementById("cmx_kontakt_id");
					const kA=document.getElementById("cmx_kontakt_addr");
					if(kI&&kH){ kI.value = it.kontakt_title||""; kH.value = it.kontakt_id||""; }
					if(kA){ kA.value = it.kontakt_addr||""; }
				}
				// C: Betreff aus Projekt-URL wenn leer
				if(betInput && (!betInput.value || betInput.value.trim()==="") && it.url){
					// betInput.value = it.url;
					betInput.value = (it.url||"").replace(/^\s*[a-z][a-z0-9+\-.]*:\/\//i, "");
				}
			}
			function pSearch(q){
				const url = "'.\esc_js($ajax_url).'?action=cmx_search_projekte&_ajax_nonce='.\esc_js($ajax_nonce_proj).'&q="+encodeURIComponent(q);
				fetch(url,{credentials:"same-origin"}).then(r=>r.json()).then(j=>{
					if(!j||!j.success){pL.style.display="none"; pL.innerHTML=""; projNav.reset(); return;}
					projNav.render(j.data.items||[]);
				}).catch(()=>{pL.style.display="none"; pL.innerHTML=""; projNav.reset();});
			}
			if(pI&&pH&&pL){
				pI.addEventListener("input", ()=>{
					pH.value=""; if(pT) clearTimeout(pT);
					const q=pI.value.trim();
					if(q.length<2){ pL.style.display="none"; pL.innerHTML=""; projNav.reset(); return; }
					pT=setTimeout(()=>pSearch(q),200);
				});
			}
			// A: Clear + Fokus in das Eingabefeld
			if(pC){ pC.addEventListener("click",()=>{ pI.value=""; pH.value=""; pL.style.display="none"; pL.innerHTML=""; projNav.reset(); pI.focus(); }); }

			/* --- Kontakte --- */
			const kI=document.getElementById("cmx_kontakt_search");
			const kH=document.getElementById("cmx_kontakt_id");
			const kL=document.getElementById("cmx_kontakt_suggest");
			const kC=document.getElementById("cmx_kontakt_clear");
			const kA=document.getElementById("cmx_kontakt_addr");
			let kT=null;
			const kontNav = makeNavigator(kI, kL, chooseKontakt);

			function chooseKontakt(it){
				kI.value = it.title||""; kH.value = it.id||""; if(kA){kA.value = it.addr||"";} kI.focus();
			}
			function kSearch(q){
				const url = "'.\esc_js($ajax_url).'?action=cmx_search_kontakte&_ajax_nonce='.\esc_js($ajax_nonce_kont).'&q="+encodeURIComponent(q);
				fetch(url,{credentials:"same-origin"}).then(r=>r.json()).then(j=>{
					if(!j||!j.success){kL.style.display="none"; kL.innerHTML=""; kontNav.reset(); return;}
					kontNav.render(j.data.items||[]);
				}).catch(()=>{kL.style.display="none"; kL.innerHTML=""; kontNav.reset();});
			}
			if(kI&&kH&&kL){
				kI.addEventListener("input", ()=>{
					kH.value=""; if(kT) clearTimeout(kT);
					const q=kI.value.trim();
					if(q.length<2){ kL.style.display="none"; kL.innerHTML=""; kontNav.reset(); return; }
					kT=setTimeout(()=>kSearch(q),200);
				});
			}
			// A: Clear + Fokus in das Eingabefeld
			if(kC){ kC.addEventListener("click",()=>{ kI.value=""; kH.value=""; if(kA) kA.value=""; kL.style.display="none"; kL.innerHTML=""; kontNav.reset(); kI.focus(); }); }
		})();
	</script>';

	echo '</div></div>';
}

/* =========================================================
 * Speichern (inkl. Titel = nur Rechnungsnummer)
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

		/* Kontakt-ID + Adresse + Label */
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ID') && isset($_POST['cmx_kontakt_id'])) {
			$kid = (int) $_POST['cmx_kontakt_id'];
			if ($kid > 0) \update_post_meta($post_id, CMX_BELEG_META_KONTAKT_ID, $kid);
			else \delete_post_meta($post_id, CMX_BELEG_META_KONTAKT_ID);
		}
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ADDR') && isset($_POST['cmx_kontakt_addr'])) {
			\update_post_meta($post_id, CMX_BELEG_META_KONTAKT_ADDR, \wp_kses_post(\wp_unslash($_POST['cmx_kontakt_addr'])));
		}
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_LABEL') && isset($_POST['cmx_kontakt_search'])) {
			$k_label = \sanitize_text_field(\wp_unslash($_POST['cmx_kontakt_search']));
			if ($k_label !== '') \update_post_meta($post_id, CMX_BELEG_META_KONTAKT_LABEL, $k_label);
			else \delete_post_meta($post_id, CMX_BELEG_META_KONTAKT_LABEL);
		}

		/* Projekt (ID + Label) */
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
	}

	/* Titel-Logik: NUR Rechnungsnummer (ohne Kontakt-Kürzel) */
	$current = \get_post($post_id);
	$should_set_title = empty($current->post_title) || ((int)\get_post_meta($post_id, '_cmx_title_auto', true) === 1);

	if ($should_set_title) {
		$new_title = $inv_no; // kein Präfix mehr
		$GLOBALS['cmx_belege_title_updating'] = true;
		\wp_update_post([
			'ID'         => $post_id,
			'post_title' => $new_title,
			'post_name'  => \sanitize_title($new_title),
		]);
		unset($GLOBALS['cmx_belege_title_updating']);
		\update_post_meta($post_id, '_cmx_title_auto', 1);
	} else {
		\delete_post_meta($post_id, '_cmx_title_auto'); // Nutzer hat bewusst überschrieben
	}
}, 10, 3);
