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

/**
 * Rechnungsnummer im Format YYMMTT-HHMM erzeugen
 */
if (!\function_exists(__NAMESPACE__ . '\\cmx_generate_rechnungsnummer')) {
	function cmx_generate_rechnungsnummer(): string {
		$dt = new \DateTime('now', new \DateTimeZone('Europe/Zurich'));
		return $dt->format(cmx_ini_get_value('Belege','Format'));
	}
}

/**
 * Sorgt dafür, dass _cmx_rechnungsnummer existiert (einmalig)
 * @return string die existierende oder neu erzeugte Rechnungsnummer
 */
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

/**
 * Initialen aus einem Namen bilden:
 * - je Wort (und Bindestrich-Teil) den ersten Buchstaben
 * - Unicode-fähig (Umlaute etc.)
 * - Rückgabe in Großbuchstaben, Fallback 'XXX'
 */
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

/**
 * Ermittelt Präfix-Initialen aus Kontakt (aus POST-Label oder Kontakt-Posttitel)
 */
if (!\function_exists(__NAMESPACE__.'\\cmx_contact_prefix')) {
	function cmx_contact_prefix(int $post_id): string {
		// 1) Bevorzugt den im Formular eingegebenen Kontakt-Suchwert
		if (isset($_POST['cmx_kontakt_search'])) {
			$label = \sanitize_text_field(\wp_unslash($_POST['cmx_kontakt_search']));
			$initials = cmx_initials_from_string($label);
			if ($initials !== 'XXX') return $initials;
		}
		// 2) Kontakt-ID (POST) oder gespeichertes Meta
		$kid = 0;
		if (isset($_POST['cmx_kontakt_id'])) {
			$kid = (int)$_POST['cmx_kontakt_id'];
		}
		if ($kid <= 0) {
			$kid = (int)\get_post_meta($post_id, CMX_BELEG_META_KONTAKT_ID, true);
		}
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
 * Metabox registrieren
 * ========================================================= */
\add_action('add_meta_boxes', function () {
	if (!\post_type_exists('belege')) return;
	\add_meta_box('cmx_beleg_details', 'Kopfdaten', __NAMESPACE__.'\\cmx_render_beleg_metabox', 'belege', 'normal', 'high');
});

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

	\wp_nonce_field('cmx_beleg_details_save', 'cmx_beleg_details_nonce');

	echo '<style>
		.cmx-grid{display:flex;gap:16px;flex-wrap:wrap}
		.cmx-col{flex:1 1 420px;min-width:320px}
		.cmx-col input[type=text],.cmx-col textarea{width:100%}
		.cmx-radio-inline label{display:inline-block;margin-right:12px}
		.cmx-addr{white-space:pre-wrap}
	</style>';

	echo '<div class="cmx-grid">';

	// ---- linke Spalte ----
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

	// ---- rechte Spalte ----
	echo '<div class="cmx-col">';

	// Projektsuche
	$proj_tax = \taxonomy_exists('belege_projekte') ? 'belege_projekte' : null;
	$current_proj = '';
	if ($proj_tax) {
		$terms = \wp_get_post_terms($post->ID, $proj_tax, ['fields'=>'all']);
		if (!empty($terms) && !\is_wp_error($terms)) $current_proj = $terms[0]->name;
	}
	$proj_terms = $proj_tax ? \get_terms(['taxonomy'=>$proj_tax,'hide_empty'=>false]) : [];
	echo '<p><label><strong>Projekt</strong></label><br>';
	echo '<input type="text" id="cmx_projekt_search" name="cmx_projekt_search" list="cmx_projekt_list" value="'.\esc_attr($projekt_label ?: $current_proj).'" placeholder="Projekttitel eingeben...">';
	echo '<input type="hidden" id="cmx_projekt_term_id" name="cmx_projekt_term_id" value="">';
	echo '<datalist id="cmx_projekt_list">';
	$projects_map=[];
	foreach($proj_terms as $t){$projects_map[$t->name]=(int)$t->term_id;echo '<option value="'.\esc_attr($t->name).'"></option>';}
	echo '</datalist></p>';

	// Kontaktsuche
	$kontakte_pt = cmx_kontakte_cpt();
	echo '<p><label><strong>Kontakt</strong></label><br>';
	echo '<input type="text" id="cmx_kontakt_search" name="cmx_kontakt_search" list="cmx_kontakt_list" value="'.\esc_attr($kontakt_label).'" placeholder="Name...">';
	echo '<input type="hidden" id="cmx_kontakt_id" name="cmx_kontakt_id" value="'.\esc_attr($kontakt_id).'">';
	echo '<datalist id="cmx_kontakt_list">';
	$kontakte = \get_posts(['post_type'=>$kontakte_pt,'posts_per_page'=>500,'fields'=>'ids']);
	$contacts_map=[];$contacts_data=[];
	foreach($kontakte as $kid){
		$title=\get_the_title($kid);
		$label=$title;
		$str=\get_post_meta($kid,'_cmx_rechnung_strasse',true);
		$plz=\get_post_meta($kid,'_cmx_rechnung_plz',true);
		$ort=\get_post_meta($kid,'_cmx_rechnung_ort',true);
		$iso2=cmx_iso2_from_land($kid);
		$addr=\trim(\implode("\n",\array_filter([$title,$str,$iso2.'-'.$plz.' '.$ort])));
		$contacts_map[$label]=(int)$kid;
		$contacts_data[(int)$kid]=['addr'=>$addr];
		echo '<option value="'.\esc_attr($label).'"></option>';
	}
	echo '</datalist>';

	if ($addr_text === '' && $kontakt_id && isset($contacts_data[$kontakt_id])) $addr_text=$contacts_data[$kontakt_id]['addr'];
	echo '<p><label><strong>Postanschrift</strong></label><br>';
	echo '<textarea id="cmx_kontakt_addr" name="cmx_kontakt_addr" class="cmx-addr" rows="5">'.\esc_textarea($addr_text).'</textarea></p>';

	// JS Sync
	echo '<script>';
	echo 'const CMX_PROJECTS_MAP='. \wp_json_encode($projects_map) .';';
	echo 'const CMX_CONTACTS_MAP='. \wp_json_encode($contacts_map) .';';
	echo 'const CMX_CONTACTS_DATA='. \wp_json_encode($contacts_data) .';';
	echo '(function(){';
	echo 'const pI=document.getElementById("cmx_projekt_search"),pH=document.getElementById("cmx_projekt_term_id");if(pI&&pH){pI.addEventListener("change",()=>{pH.value=CMX_PROJECTS_MAP[pI.value]||""});pI.addEventListener("input",()=>{if(!(pI.value in CMX_PROJECTS_MAP))pH.value="";});}';
	echo 'const kI=document.getElementById("cmx_kontakt_search"),kH=document.getElementById("cmx_kontakt_id"),kA=document.getElementById("cmx_kontakt_addr");if(kI&&kH&&kA){kI.addEventListener("change",()=>{const id=CMX_CONTACTS_MAP[kI.value];if(id){kH.value=id;kA.value=CMX_CONTACTS_DATA[id]?.addr||"";}else{kH.value="";}});}';
	echo '})();</script>';

	echo '</div></div>';
}


/* =========================================================
 * EIN Hook für alles: Felder speichern + Titel-/Rechnungsnr.-Logik
 * - vermeidet Rekursion via globalem Flag
 * - speichert nur für CPT "belege"
 * - verarbeitet Felder nur, wenn unser Nonce vorhanden ist
 * ========================================================= */
\add_action('save_post_belege', function (int $post_id, \WP_Post $post, bool $update) {
	// 0) Grund-Guards
	if ($post->post_type !== 'belege') return;
	if (\wp_is_post_autosave($post_id) || \wp_is_post_revision($post_id)) return;
	if (!\current_user_can('edit_post', $post_id)) return;

	// 1) Reentrancy-Guard: wenn wir gerade durch wp_update_post() hierher zurückkommen, Abbruch
	if (!empty($GLOBALS['cmx_belege_title_updating'])) return;

	// 2) Rechnungsnummer sicherstellen (immer – einmalig)
	$inv_no = cmx_ensure_rechnungsnummer($post_id);

	// 3) Felder nur speichern, wenn unser Metabox-Submit stattfand (Nonce vorhanden)
	$has_nonce = isset($_POST['cmx_beleg_details_nonce']) && \wp_verify_nonce($_POST['cmx_beleg_details_nonce'], 'cmx_beleg_details_save');

	if ($has_nonce) {
		// Taxonomie (Kategorie)
		$tax = \function_exists(__NAMESPACE__.'\\cmx_belege_tax') ? cmx_belege_tax() : '';
		if ($tax && isset($_POST['cmx_beleg_kategorie'])) {
			$term_id = (int) $_POST['cmx_beleg_kategorie'];
			\wp_set_post_terms($post_id, $term_id > 0 ? [$term_id] : [], $tax, false);
		}

		// Betreff / Beschreibung
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF') && isset($_POST['cmx_beleg_betreff'])) {
			\update_post_meta($post_id, CMX_BELEG_META_BETREFF, \sanitize_text_field(\wp_unslash($_POST['cmx_beleg_betreff'])));
		}
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG') && isset($_POST['cmx_beleg_beschreibung'])) {
			\update_post_meta($post_id, CMX_BELEG_META_BESCHREIBUNG, \wp_kses_post(\wp_unslash($_POST['cmx_beleg_beschreibung'])));
		}

		// Kontakt-ID + Adresse
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ID') && isset($_POST['cmx_kontakt_id'])) {
			$kid = (int) $_POST['cmx_kontakt_id'];
			if ($kid > 0) \update_post_meta($post_id, CMX_BELEG_META_KONTAKT_ID, $kid);
			else \delete_post_meta($post_id, CMX_BELEG_META_KONTAKT_ID);
		}
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ADDR') && isset($_POST['cmx_kontakt_addr'])) {
			\update_post_meta($post_id, CMX_BELEG_META_KONTAKT_ADDR, \wp_kses_post(\wp_unslash($_POST['cmx_kontakt_addr'])));
		}

		// Projekt (optional)
		if (\taxonomy_exists('belege_projekte') && isset($_POST['cmx_projekt_term_id'])) {
			$tid = (int) $_POST['cmx_projekt_term_id'];
			\wp_set_post_terms($post_id, $tid > 0 ? [$tid] : [], 'belege_projekte', false);
		}

		// Labels speichern
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_PROJEKT_LABEL') && isset($_POST['cmx_projekt_search'])) {
			$proj_label = \sanitize_text_field(\wp_unslash($_POST['cmx_projekt_search']));
			if ($proj_label !== '') \update_post_meta($post_id, CMX_BELEG_META_PROJEKT_LABEL, $proj_label);
			else \delete_post_meta($post_id, CMX_BELEG_META_PROJEKT_LABEL);
		}
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_LABEL') && isset($_POST['cmx_kontakt_search'])) {
			$k_label = \sanitize_text_field(\wp_unslash($_POST['cmx_kontakt_search']));
			if ($k_label !== '') \update_post_meta($post_id, CMX_BELEG_META_KONTAKT_LABEL, $k_label);
			else \delete_post_meta($post_id, CMX_BELEG_META_KONTAKT_LABEL);
		}
	}

	// 4) Titel-Logik (gilt sowohl beim ersten Speichern als auch später)
	$prefix_new = cmx_contact_prefix($post_id);
	$is_auto    = (int)\get_post_meta($post_id, '_cmx_title_auto', true) === 1;
	$prefix_old = (string)\get_post_meta($post_id, '_cmx_title_prefix', true);
	$current    = \get_post($post_id);

	$should_set_title =
		empty($current->post_title)           // Erstvergaben
		|| ($is_auto && $prefix_old !== $prefix_new); // Auto-Titel + Präfixwechsel

	if ($should_set_title) {
		$new_title = $prefix_new . '-' . $inv_no;

		// Reentrancy-Flag setzen, Update, Flag löschen
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
		// Nutzer pflegt Titel manuell -> Auto-Flags entfernen
		if (!$is_auto) {
			\delete_post_meta($post_id, '_cmx_title_auto');
			\delete_post_meta($post_id, '_cmx_title_prefix');
		}
	}
}, 10, 3);
