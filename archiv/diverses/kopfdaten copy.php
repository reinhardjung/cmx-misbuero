<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') or die('Oxytocin!');


/** ========= Meta-Keys ========= */
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF'))       define(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF', '_cmx_beleg_betreff');
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG'))  define(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG', '_cmx_beleg_beschreibung');
if (!defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ID'))    define(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ID', '_cmx_beleg_kontakt_id');

/** ========= Helpers ========= */
/** Taxonomie für Beleg-Kategorien robust ermitteln */
function cmx_belege_tax(): ?string {
	$candidates = [
		'belege_kategorien','belege_kategorie',
		'beleg_kategorien','beleg_kategorie',
		'belege_categories','beleg_category',
		'belege_typ','belege_themen'
	];
	foreach ($candidates as $tax) {
		if (\taxonomy_exists($tax)) return $tax;
	}
	return null;
}


/** Metabox registrieren */
\add_action('add_meta_boxes', function () {
	$post_type = 'belege';
	if (!\post_type_exists($post_type)) return;

	\add_meta_box(
		'cmx_beleg_details',
		'Kopfdaten',
		__NAMESPACE__.'\\cmx_render_beleg_metabox',
		$post_type,
		'normal',
		'high'
	);
});

/** Metabox rendern (2-spaltig) */
function cmx_render_beleg_metabox(\WP_Post $post): void {
	$tax = cmx_belege_tax();

	$betreff       = \get_post_meta($post->ID, CMX_BELEG_META_BETREFF, true);
	$beschreibung  = \get_post_meta($post->ID, CMX_BELEG_META_BESCHREIBUNG, true);
	$kontakt_id    = (int) \get_post_meta($post->ID, CMX_BELEG_META_KONTAKT_ID, true);

	\wp_nonce_field('cmx_beleg_details_save', 'cmx_beleg_details_nonce');

	// schlichte 2-Spalten-CSS (flex, bricht unter 900px um) + inline-Radios
	echo '<style>
		.cmx-grid{display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap}
		.cmx-col{flex:1 1 420px;min-width:320px}
		.cmx-col label{display:inline-block;margin-bottom:4px}
		.cmx-col input[type=text], .cmx-col textarea{width:100%}
		.cmx-radio-inline label{display:inline-block;margin-right:12px;margin-bottom:6px}
		.cmx-inline-help{color:#666;font-size:12px;margin-top:4px}
		.cmx-muted{color:#6a737d;font-size:12px}
		.cmx-addr{white-space:pre-wrap}
	</style>';

	echo '<div class="cmx-grid">';

	// Linke Spalte: Kategorie, Betreff, Beschreibung
	echo '<div class="cmx-col">';

	// A) Kategorie – NUR Radioboxen (inline)
	echo '<p><label><strong>Kategorie</strong> (Belegtyp)</label><br>';
	if ($tax) {
		$current_term_ids = \wp_get_post_terms($post->ID, $tax, ['fields' => 'ids']);
		$current_term_id  = is_array($current_term_ids) && !empty($current_term_ids) ? (int) $current_term_ids[0] : 0;

		$terms = \get_terms(['taxonomy' => $tax, 'hide_empty' => false]);
		echo '<div class="cmx-radio-inline" role="radiogroup">';
		if (!is_wp_error($terms)) {
			foreach ($terms as $term) {
				$id = 'cmx_beleg_kategorie_' . (int) $term->term_id;
				echo '<label for="'.esc_attr($id).'">
						<input type="radio" name="cmx_beleg_kategorie" id="'.esc_attr($id).'" value="'.esc_attr($term->term_id).'" '.checked($current_term_id, $term->term_id, false).' />
						'.esc_html($term->name).'
					</label>';
			}
		} else {
			echo '<span class="cmx-muted">Keine Begriffe vorhanden.</span>';
		}
		echo '</div>';
	} else {
		echo '<em>Keine passende Beleg-Taxonomie gefunden. Bitte eine Taxonomie (z. B. „belege_kategorien“) registrieren.</em>';
	}
	echo '</p>';

	// B) Betreff
	echo '<p><label for="cmx_beleg_betreff"><strong>Betreff</strong></label><br>';
	echo '<input type="text" id="cmx_beleg_betreff" name="cmx_beleg_betreff" value="'.esc_attr($betreff).'" />';
	echo '</p>';

	// C) Beschreibung
	echo '<p><label for="cmx_beleg_beschreibung"><strong>Beschreibung</strong></label><br>';
	echo '<textarea id="cmx_beleg_beschreibung" name="cmx_beleg_beschreibung" rows="6">'
		. esc_textarea($beschreibung)
		. '</textarea>';
	echo '<div class="cmx-inline-help">Basis-HTML (z. B. <strong>&lt;strong&gt;</strong>, <em>&lt;em&gt;</em>, <ul>) ist erlaubt.</div>';
	echo '</p>';

	echo '</div>'; // .cmx-col (links)

	// Rechte Spalte: Projekt-Suche (Taxonomie) + Kontakt-Suche + Kontakt-Textarea
	echo '<div class="cmx-col">';

	// --- Projekt-Suche (TAXONOMIE: belege_projekte) ---
	$proj_tax = \taxonomy_exists('belege_projekte') ? 'belege_projekte' : null;
	$current_proj_term = '';
	if ($proj_tax) {
		$assigned = \wp_get_post_terms($post->ID, $proj_tax, ['fields' => 'all']);
		if (!is_wp_error($assigned) && !empty($assigned)) {
			$current_proj_term = $assigned[0]->name;
		}
		$proj_terms = \get_terms(['taxonomy' => $proj_tax, 'hide_empty' => false]);
	} else {
		$proj_terms = [];
	}

	$projekt_datalist_id = 'cmx_projekt_list';

	echo '<p><label for="cmx_projekt_search"><strong>Projekt suchen</strong></label><br>';
	echo '<input type="text" id="cmx_projekt_search" list="'.esc_attr($projekt_datalist_id).'" placeholder="Projekttitel eingeben..." value="'.esc_attr($current_proj_term).'" />';
	echo '<input type="hidden" id="cmx_projekt_term_id" name="cmx_projekt_term_id" value="">';
	echo '<datalist id="'.esc_attr($projekt_datalist_id).'">';
	$projects_map = [];
	if (!empty($proj_terms) && !is_wp_error($proj_terms)) {
		foreach ($proj_terms as $t) {
			$projects_map[$t->name] = (int) $t->term_id;
			echo '<option value="'.esc_attr($t->name).'"></option>';
		}
	}
	echo '</datalist>';
	if (!$proj_tax) {
		echo '<div class="cmx-inline-help">Hinweis: Taxonomie <code>belege_projekte</code> existiert nicht.</div>';
	}
	echo '</p>';

	// --- Kontakt: Suchfeld + Textarea mit Adressdaten (kein Select mehr) ---
	echo '<p><label for="cmx_kontakt_search"><strong>Kontakt suchen</strong></label><br>';
	echo '<input type="text" id="cmx_kontakt_search" list="cmx_kontakt_list" placeholder="Name (#ID) eingeben..." autocomplete="off" />';
	echo '<input type="hidden" id="cmx_kontakt_id" name="cmx_kontakt_id" value="'.esc_attr($kontakt_id ?: '').'">';
	echo '<datalist id="cmx_kontakt_list">';

	$kontakte = \get_posts([
		'post_type'      => 'kontakte',
		'posts_per_page' => 500,
		'post_status'    => ['publish'],
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'suppress_filters' => false,
	]);

	$contacts_map = [];   // label -> id
	$contacts_data = [];  // id -> {firma,str,plz,ort}
	if (!empty($kontakte)) {
		foreach ($kontakte as $kid) {
			$title = get_the_title($kid);
			$label = trim(($title ?: ('#'.$kid)) . ' (#'.$kid.')');
			$contacts_map[$label] = (int) $kid;

			$str = (string) get_post_meta($kid, '_cmx_rechnung_strasse', true);
			$plz = (string) get_post_meta($kid, '_cmx_rechnung_plz', true);
			$ort = (string) get_post_meta($kid, '_cmx_rechnung_ort', true);
			$contacts_data[(int)$kid] = [
				'firma' => (string) ($title ?: ''),
				'str'   => $str,
				'plz'   => $plz,
				'ort'   => $ort,
			];

			echo '<option value="'.esc_attr($label).'"></option>';
		}
	}
	echo '</datalist>';

	// Textarea mit Kontakt-Daten (readonly visual, speicherstabil – Inhalt selbst wird nicht gespeichert)
	$kontakt_text = '';
	if ($kontakt_id) {
		$f = $contacts_data[$kontakt_id]['firma'] ?? get_the_title($kontakt_id);
		$s = $contacts_data[$kontakt_id]['str']   ?? (string) get_post_meta($kontakt_id, '_cmx_rechnung_strasse', true);
		$p = $contacts_data[$kontakt_id]['plz']   ?? (string) get_post_meta($kontakt_id, '_cmx_rechnung_plz', true);
		$o = $contacts_data[$kontakt_id]['ort']   ?? (string) get_post_meta($kontakt_id, '_cmx_rechnung_ort', true);
		$kontakt_text = trim($f."\n".$s."\n".trim($p.' '.$o));
	}

	echo '<p><label for="cmx_kontakt_addr"><strong>Kontakt-Adresse</strong></label><br>';
	echo '<textarea id="cmx_kontakt_addr" class="cmx-addr" rows="5" readonly placeholder="Kontakt wählen ...">'
		. esc_textarea($kontakt_text)
		. '</textarea>';
	echo '<div class="cmx-inline-help">Die Kontakt-ID wird gespeichert; der Text dient zur Kontrolle.</div>';
	echo '</p>';

	// === JS: funktionale Suche (Projekt-Term + Kontakt) ===
	echo '<script>';
	echo 'const CMX_PROJECTS_MAP = '.wp_json_encode($projects_map, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).';';
	echo 'const CMX_CONTACTS_MAP = '.wp_json_encode($contacts_map, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).';';
	echo 'const CMX_CONTACTS_DATA = '.wp_json_encode($contacts_data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).';';
	echo '(function(){';
	// Projekte (Taxonomie)
	echo 'const projInput = document.getElementById("cmx_projekt_search");';
	echo 'const projHidden = document.getElementById("cmx_projekt_term_id");';
	echo 'if (projInput && projHidden){';
	echo '  function syncProj(){ const t=projInput.value||""; projHidden.value = CMX_PROJECTS_MAP[t] || ""; }';
	echo '  projInput.addEventListener("change", syncProj);';
	echo '  projInput.addEventListener("input", function(){ if(!(this.value in CMX_PROJECTS_MAP)) projHidden.value=""; });';
	echo '}';

	// Kontakte
	echo 'const kInput = document.getElementById("cmx_kontakt_search");';
	echo 'const kHidden= document.getElementById("cmx_kontakt_id");';
	echo 'const kAddr  = document.getElementById("cmx_kontakt_addr");';
	echo 'function fillAddr(id){';
	echo '  const d = CMX_CONTACTS_DATA[id];';
	echo '  if (!d){ kAddr.value=""; return; }';
	echo '  const lines = [];';
	echo '  if (d.firma) lines.push(d.firma);';
	echo '  if (d.str)   lines.push(d.str);';
	echo '  const plzOrt = ((d.plz||"") + " " + (d.ort||"")).trim();';
	echo '  if (plzOrt) lines.push(plzOrt);';
	echo '  kAddr.value = lines.join("\\n");';
	echo '}';
	echo 'if (kInput && kHidden && kAddr){';
	echo '  kInput.addEventListener("change", function(){';
	echo '    const label = this.value || "";';
	echo '    const id = CMX_CONTACTS_MAP[label];';
	echo '    if (id){ kHidden.value = id; fillAddr(id); } else { kHidden.value=""; kAddr.value=""; }';
	echo '  });';
	echo '  kInput.addEventListener("input", function(){';
	echo '    if (!(this.value in CMX_CONTACTS_MAP)){ kHidden.value=""; /* nicht hart filtern, da Datalist */ }';
	echo '  });';
	echo '}';
	echo '})();';
	echo '</script>';

	echo '</div>'; // .cmx-col (rechts)

	echo '</div>'; // .cmx-grid
}

/** Speichern */
\add_action('save_post_belege', function (int $post_id, \WP_Post $post) {
	// Nonce / Autosave / Rechte prüfen
	if (!isset($_POST['cmx_beleg_details_nonce']) || !\wp_verify_nonce($_POST['cmx_beleg_details_nonce'], 'cmx_beleg_details_save')) return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!\current_user_can('edit_post', $post_id)) return;

	// A) Kategorie als Taxonomie
	$tax = cmx_belege_tax();
	if ($tax && isset($_POST['cmx_beleg_kategorie'])) {
		$term_id = (int) $_POST['cmx_beleg_kategorie'];
		\wp_set_post_terms($post_id, $term_id > 0 ? [$term_id] : [], $tax, false);
	}

	// B) Betreff
	if (isset($_POST['cmx_beleg_betreff'])) {
		$betreff = \sanitize_text_field(\wp_unslash($_POST['cmx_beleg_betreff']));
		\update_post_meta($post_id, CMX_BELEG_META_BETREFF, $betreff);
	}

	// C) Beschreibung (Basis-HTML zulassen)
	if (isset($_POST['cmx_beleg_beschreibung'])) {
		$beschreibung = \wp_kses_post(\wp_unslash($_POST['cmx_beleg_beschreibung']));
		\update_post_meta($post_id, CMX_BELEG_META_BESCHREIBUNG, $beschreibung);
	}

	// D) Kontakt-ID (aus Hidden)
	if (isset($_POST['cmx_kontakt_id'])) {
		$kid = (int) $_POST['cmx_kontakt_id'];
		if ($kid > 0) {
			\update_post_meta($post_id, CMX_BELEG_META_KONTAKT_ID, $kid);
		} else {
			\delete_post_meta($post_id, CMX_BELEG_META_KONTAKT_ID);
		}
	}

	// E) Projekt-Zuordnung als Taxonomie-Term (belege_projekte)
	if (\taxonomy_exists('belege_projekte') && isset($_POST['cmx_projekt_term_id'])) {
		$tid = (int) $_POST['cmx_projekt_term_id'];
		\wp_set_post_terms($post_id, $tid > 0 ? [$tid] : [], 'belege_projekte', false);
	}
}, 10, 2);


/* ADD: ISO2 aus Land ableiten – gegen Doppeldefinition geschützt */
if (!function_exists(__NAMESPACE__.'\\cmx_iso2_from_land')) {
	function cmx_iso2_from_land(int $post_id): string {
		$meta_land = strtoupper(trim((string)\get_post_meta($post_id, '_cmx_rechnung_land', true)));
		if (preg_match('/^[A-Z]{2}$/', $meta_land)) return $meta_land;

		if (\taxonomy_exists('kontakte_laender')) {
			$terms = \wp_get_post_terms($post_id, 'kontakte_laender', ['fields'=>'all']);
			if (!\is_wp_error($terms) && !empty($terms)) {
				$slug = strtoupper(trim((string)$terms[0]->slug));
				if (preg_match('/^[A-Z]{2}$/', $slug)) return $slug;
				$name = strtoupper(trim((string)$terms[0]->name));
				$map = [
					'CH'=>['SCHWEIZ','SWITZERLAND','SUI','CH'],
					'DE'=>['DEUTSCHLAND','GERMANY','DE'],
					'AT'=>['ÖSTERREICH','OESTERREICH','AUSTRIA','AT'],
					'IT'=>['ITALIEN','ITALY','IT'],
					'FR'=>['FRANKREICH','FRANCE','FR'],
				];
				foreach ($map as $k=>$arr) if (in_array($name,$arr,true)) return $k;
			}
		}
		return 'CH';
	}
}
