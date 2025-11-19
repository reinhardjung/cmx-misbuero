<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') or die('Oxytocin!');


/* ============================================================================
 * 0) OPTIONALE KONSTANTEN (IDs/Slugs für "Lieferant" in kontak­te_kategorien)
 *    → Wenn Deine IDs anders sind, kannst Du diese Konstanten hier überschreiben.
 * ========================================================================== */
if (!\defined(__NAMESPACE__ . '\\CMX_TAX_KONTAKTE_KATEGORIEN')) {
	\define(__NAMESPACE__ . '\\CMX_TAX_KONTAKTE_KATEGORIEN', 'kontakte_kategorien');
}
if (!\defined(__NAMESPACE__ . '\\CMX_TERMID_KONTAKT_LIEFERANT')) {
	// Aus Deinem HTML-Beispiel: <input value="399" ...> Lieferant
	\define(__NAMESPACE__ . '\\CMX_TERMID_KONTAKT_LIEFERANT', 399);
}
if (!\defined(__NAMESPACE__ . '\\CMX_TERMSLUG_KONTAKT_LIEFERANT')) {
	\define(__NAMESPACE__ . '\\CMX_TERMSLUG_KONTAKT_LIEFERANT', 'lieferant');
}


/* ============================================================================
 * 1) KOMPATIBILITÄT: Meta-Keys aliasieren (neue ↔ alte Bezeichner)
 * ========================================================================== */
if (!function_exists(__NAMESPACE__ . '\\cmx_define_meta_alias')) {
	function cmx_define_meta_alias(string $target, array $candidates, string $default): void {
		$nsTarget = __NAMESPACE__ . '\\' . $target;
		if (\defined($nsTarget)) return;

		foreach ($candidates as $cand) {
			$nsCand = __NAMESPACE__ . '\\' . $cand;
			if (\defined($nsCand)) { \define($nsTarget, \constant($nsCand)); return; }
		}
		\define($nsTarget, $default);
	}
}

// cmx_define_meta_alias('CMX_ARTIKEL_META_LAGERBESTAND', ['CMX_ART_META_LAGERBESTAND'], '_cmx_art_lagerbestand');
// cmx_define_meta_alias('CMX_ARTIKEL_META_LIEFERANT_ID', ['CMX_ART_META_LIEFERANT_ID'], '_cmx_art_lieferant_id');
// cmx_define_meta_alias('CMX_ARTIKEL_META_LIEFERZEIT',   ['CMX_ART_META_LIEFERZEIT'],   '_cmx_art_lieferzeit_tage');
// cmx_define_meta_alias('CMX_ARTIKEL_META_BEZUGSQUELLE', ['CMX_ART_META_BEZUGSQUELLE'], '_cmx_art_bezugsquelle_url');
// cmx_define_meta_alias('CMX_ARTIKEL_META_LIEFERANT_NR', ['CMX_ART_META_LIEFERANT_NR'], '_cmx_art_lieferant_nr');


/* ============================================================================
 * 2) KONTAKT-CPT Kandidaten
 * ========================================================================== */
if (!\defined(__NAMESPACE__ . '\\CMX_CANDIDATE_CPTS_KONTAKT')) {
	\define(__NAMESPACE__ . '\\CMX_CANDIDATE_CPTS_KONTAKT', ['kontakte', 'kontakt']);
}


function cmx_kontakt_candidates(): array {
	$val = \defined(__NAMESPACE__ . '\\CMX_CANDIDATE_CPTS_KONTAKT')
		? \constant(__NAMESPACE__ . '\\CMX_CANDIDATE_CPTS_KONTAKT')
		: ['kontakte', 'kontakt'];

	if (\is_string($val)) {
		$tmp = @\unserialize($val);
		$val = \is_array($tmp) ? $tmp : \preg_split('/[,;|\s]+/', $val, -1, PREG_SPLIT_NO_EMPTY);
	}
	if (!\is_array($val)) $val = ['kontakte', 'kontakt'];

	$val = \array_values(\array_unique(\array_filter($val, 'is_string')));
	return !empty($val) ? $val : ['kontakte', 'kontakt'];
}


function cmx_first_existing_kontakt_cpt(): ?string {
	foreach (cmx_kontakt_candidates() as $pt) {
		if (\post_type_exists($pt)) return $pt;
	}
	return null;
}


/* ============================================================================
 * 3) Lieferanten-Ermittlung (Query + Fallback) – erweitert um kontak­te_kategorien:Lieferant
 * ========================================================================== */

/**
 * Liefert ein tax_query-Fragment für die Taxonomie "kontakte_kategorien" → Term "Lieferant".
 * Nutzt bevorzugt die Term-ID (399), prüft aber auch den Slug "lieferant".
 */
function cmx_taxq_kontakte_kategorien_lieferant(): ?array {
	$tax = CMX_TAX_KONTAKTE_KATEGORIEN;
	if (!\taxonomy_exists($tax)) return null;

	// 1) Term nach ID prüfen (falls die ID existiert)
	$term_id = (int) CMX_TERMID_KONTAKT_LIEFERANT;
	$by_id_ok = false;
	if ($term_id > 0) {
		$exists = \term_exists($term_id, $tax);
		$by_id_ok = !empty($exists);
	}

	// 2) Term via Slug auflösen
	$slug = (string) CMX_TERMSLUG_KONTAKT_LIEFERANT;
	$term_by_slug = $slug !== '' ? \get_term_by('slug', $slug, $tax) : false;

	if ($by_id_ok) {
		return ['taxonomy' => $tax, 'field' => 'term_id', 'terms' => [$term_id]];
	}
	if ($term_by_slug && !\is_wp_error($term_by_slug)) {
		return ['taxonomy' => $tax, 'field' => 'term_id', 'terms' => [(int)$term_by_slug->term_id]];
	}
	// Falls weder ID noch Slug gefunden: kein Fragment
	return null;
}


function cmx_lieferanten_query_args(string $post_type): array {
	$args = ['post_type' => $post_type,'numberposts' => -1,'orderby' => 'title','order' => 'ASC','post_status' => ['publish', 'private'],'suppress_filters' => true,'fields' => 'ids',];

	$tax_query = [];

	// (a) Spezifische Taxonomie "lieferant"
	if (\taxonomy_exists('lieferant')) {
		$tax_query[] = ['taxonomy' => 'lieferant', 'field' => 'slug', 'terms' => ['lieferant']];
	}

	// (b) Generische Kontakt-Typen
	foreach (['kontakt_type', 'kundenart'] as $maybe_tax) {
		if (\taxonomy_exists($maybe_tax)) {
			$tax_query[] = ['taxonomy' => $maybe_tax, 'field' => 'slug', 'terms' => ['lieferant']];
			break;
		}
	}

	// (c) NEU: kontak­te_kategorien ⇒ "Lieferant" (ID 399 oder Slug 'lieferant')
	$kfrag = cmx_taxq_kontakte_kategorien_lieferant();
	if ($kfrag) $tax_query[] = $kfrag;

	if (!empty($tax_query)) {
		$args['tax_query'] = (count($tax_query) > 1)
			? array_merge(['relation' => 'OR'], $tax_query)
			: $tax_query;
	} else  $args['meta_query'] = [['key' => 'is_supplier','value' => ['1', 1, 'true', true],'compare' => 'IN',]];

	return $args;
}


/** Truthy-Helfer */
function cmx_truthy($v): bool {
	if (\is_bool($v)) return $v;
	$v = \strtolower(\trim((string)$v));
	return \in_array($v, ['1','true','yes','on','y','ja','wahr'], true);
}


/** Term-Prüfung über mehrere Taxonomien/Slugs/IDs (inkl. kontak­te_kategorien:Lieferant) */
function cmx_post_has_lieferant_term(int $post_id): bool {
	$tax_candidates = ['lieferant','kontakt_type','kundenart','stufen','kontakt_kategorie', CMX_TAX_KONTAKTE_KATEGORIEN];
	$term_slugs     = ['lieferant','supplier','lieferanten','vendor','lieferfirma'];
	$term_id_pref   = (int) CMX_TERMID_KONTAKT_LIEFERANT;

	foreach ($tax_candidates as $tax) {
		if (!\taxonomy_exists($tax)) continue;
		$terms = \get_the_terms($post_id, $tax);
		if (\is_wp_error($terms) || empty($terms)) continue;

		foreach ($terms as $t) {
			$slug = \is_object($t) ? \strtolower((string)$t->slug) : '';
			$id   = \is_object($t) ? (int)$t->term_id : 0;

			// a) exakte ID (z. B. 399) – aus Deinem HTML
			if ($term_id_pref > 0 && $id === $term_id_pref) return true;

			// b) bekannte Slugs
			if ($slug && \in_array($slug, $term_slugs, true)) return true;

			// c) Slug exakt 'lieferant' (falls Liste erweitert wurde)
			if ($slug === CMX_TERMSLUG_KONTAKT_LIEFERANT) return true;
		}
	}
	return false;
}


/** Gesamt-Check: Gilt Post als Lieferant? (Taxonomie-ODER-Meta) */
function cmx_is_lieferant(int $post_id): bool {
	if (cmx_post_has_lieferant_term($post_id)) return true;
	foreach (['is_supplier','_is_supplier','lieferant','_lieferant'] as $k) {
		$val = \get_post_meta($post_id, $k, true);
		if (cmx_truthy($val)) return true;
	}
	return false;
}


/** IDs der *echten Lieferanten* holen (mit Fallback) */
function cmx_fetch_lieferanten_ids(string $post_type): array {
	$ids = \get_posts(cmx_lieferanten_query_args($post_type)); // 'fields' => 'ids'
	$ids = \is_array($ids) ? \array_map('intval', $ids) : [];
	if (!empty($ids)) return $ids;

	// Fallback: Alle Kontakte holen und in PHP filtern
	$all_ids = \get_posts(['post_type' => $post_type,'post_status' => ['publish','private'],'posts_per_page' => -1,'fields' => 'ids','suppress_filters' => true,]);
	$all_ids = \is_array($all_ids) ? \array_map('intval', $all_ids) : [];
	if (empty($all_ids)) return [];

	$out = [];
	foreach ($all_ids as $pid) {
		if (cmx_is_lieferant($pid)) $out[] = $pid;
	}
	return $out;
}

/* ============================================================================
 * 4) UI: Metabox registrieren + Render + Save
 * ========================================================================== */
\add_action('add_meta_boxes', function () {
	\add_meta_box('cmx_artikel_lieferanten','Lieferanten',__NAMESPACE__.'\\cmx_artikel_lieferanten_box_html','artikel','normal','default');
});


function cmx_normalize_url_for_label(string $url): string {
	$u = \trim($url);
	if ($u === '') return '';
	if (!\preg_match('~^https?://~i', $u)) $u = 'https://' . $u;
	return \filter_var($u, \FILTER_VALIDATE_URL) ? $u : '';
}


function cmx_artikel_lieferanten_box_html(\WP_Post $post): void {
	\wp_nonce_field('cmx_artikel_lieferanten_save', 'cmx_artikel_lieferanten_nonce');

	$lager     = (int) \get_post_meta($post->ID, CMX_ARTIKEL_META_LAGERBESTAND, true);
	$lieferant = (int) \get_post_meta($post->ID, CMX_ARTIKEL_META_LIEFERANT_ID, true);
	$ltage     = (int) \get_post_meta($post->ID, CMX_ARTIKEL_META_LIEFERZEIT, true);
	$quelle    = (string) \get_post_meta($post->ID, CMX_ARTIKEL_META_BEZUGSQUELLE, true);
	$lfnr      = (string) \get_post_meta($post->ID, CMX_ARTIKEL_META_LIEFERANT_NR, true);

	$kontakt_pt      = cmx_first_existing_kontakt_cpt();
	$lieferanten_ids = $kontakt_pt ? cmx_fetch_lieferanten_ids($kontakt_pt) : [];

	// Linkziel fürs Label „Kontakt“
	$label_href = '';
	if ($lieferant && \get_post_status($lieferant)) {
		$label_href = \get_edit_post_link($lieferant, '');
	} elseif ($kontakt_pt) {
		$label_href = \add_query_arg(['post_type' => $kontakt_pt], \admin_url('edit.php'));
	}

	$bez_label_href = cmx_normalize_url_for_label($quelle);

	echo '<style>
		.cmx-lief-row{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap}
		.cmx-lief-row p{margin:0;flex:1 1 160px}
		.cmx-lief-row p.lager{flex:0 0 120px}
		.cmx-lief-row p.lieferant{flex:2 1 260px}
		.cmx-lief-row p.ltage{flex:0 0 140px}
		.cmx-lief-row p.quelle{flex:2 1 260px;min-width:220px}
		.cmx-lief-row p.lfnr{flex:1 1 180px}
		.cmx-lief-row label{display:block}
		.cmx-inline-help{font-size:11px;color:#666;margin-top:4px;display:block}
	</style>';

	echo '<div class="cmx-lief-row">';

	echo '<p class="lieferant"><label for="cmx_artikel_lieferant"><strong>';
	echo $label_href
		? 'Lieferant (<a href="'.\esc_url($label_href).'">Kontakt</a>)'
		: 'Lieferant (Kontakt)';
	echo '</strong></label>
		<select id="cmx_artikel_lieferant" name="cmx_artikel_lieferant" class="widefat">
			<option value="0">— auswählen —</option>';

	if (!empty($lieferanten_ids)) {
		$_posts = \get_posts(['post_type' => $kontakt_pt ?: 'post','posts_per_page' => -1,'post__in' => $lieferanten_ids,'orderby' => 'title','order' => 'ASC','post_status' => ['publish','private'],'suppress_filters' => true]);

		foreach ($_posts as $k) {
			$title = \get_the_title($k->ID);
			if ($title === '') $title = '(#'.(int)$k->ID.')';
			echo '<option value="'.(int)$k->ID.'" '.\selected($lieferant, $k->ID, false).'>'.\esc_html($title).'</option>';
		}
	} else echo '<option value="0" disabled>(Keine als Lieferant gekennzeichneten Kontakte gefunden)</option>';

	echo '</select>';

	if (!$kontakt_pt) echo '<span class="cmx-inline-help">Kein Kontakte-CPT gefunden (<code>kontakt</code> / <code>kontakte</code>).</span>';

	echo '</p>';


	echo '<p class="lfnr"><label for="cmx_artikel_lieferant_nr"><strong>Lieferanten-Artikelnummer</strong></label>
		<input type="text" id="cmx_artikel_lieferant_nr" name="cmx_artikel_lieferant_nr" class="widefat" value="'.\esc_attr($lfnr).'"></p>';


	echo '<p class="quelle"><label for="cmx_artikel_bezugsquelle"><strong>';
	if ($bez_label_href !== '') {
		echo 'Bezugsquelle (<a id="cmx_bezugsquelle_label" href="'.\esc_url($bez_label_href).'" target="_blank" rel="noopener noreferrer">URL</a>)';
	} else {
		echo 'Bezugsquelle <span id="cmx_bezugsquelle_label">(URL)</span>';
	}
	echo '</strong></label>
		<input type="url" id="cmx_artikel_bezugsquelle" name="cmx_artikel_bezugsquelle" class="widefat" placeholder="https://…" value="'.\esc_attr($quelle).'"></p>';

	// Live-Update Script fürs URL-Label
	echo '<script>
	(function(){
		var input = document.getElementById("cmx_artikel_bezugsquelle");
		var lab   = document.getElementById("cmx_bezugsquelle_label");
		if(!input || !lab) return;
		function norm(u){
			u = (u || "").trim();
			if(!u) return "";
			if(!/^https?:\\/\\//i.test(u)) u = "https://" + u;
			try { new URL(u); return u; } catch(e){ return ""; }
		}
		function update(){
			var u = norm(input.value);
			if(u){
				if(lab.tagName !== "A"){
					var a = document.createElement("a");
					a.id = lab.id;
					a.textContent = lab.textContent || "Bezugsquelle (URL)";
					lab.replaceWith(a);
					lab = a;
				}
				lab.href   = u;
				lab.target = "_blank";
				lab.rel    = "noopener noreferrer";
			} else {
				if(lab.tagName === "A"){
					var s = document.createElement("span");
					s.id = lab.id;
					s.textContent = "Bezugsquelle (URL)";
					lab.replaceWith(s);
					lab = s;
				}
			}
		}
		input.addEventListener("input", update);
	})();
	</script>';


	echo '<p class="ltage"><label for="cmx_artikel_lieferzeit"><strong>Lieferzeit (Tage)</strong></label>
		<input type="number" min="0" step="1" id="cmx_artikel_lieferzeit" name="cmx_artikel_lieferzeit" class="widefat" value="'.\esc_attr((string)$ltage).'"></p>';


	echo '<p class="lager"><label for="cmx_artikel_lager"><strong>Lagerbestand</strong></label>
		<input type="number" min="0" step="1" id="cmx_artikel_lager" name="cmx_artikel_lager" class="widefat" value="'.\esc_attr((string)$lager).'"></p>';

	echo '</div>';

	// Auto-Select bei Fokus/Klick
	echo '<script>
	(function(){
		function autoSelect(el){
			if(!el) return;
			el.addEventListener("focus", function(){ this.select(); });
			el.addEventListener("click", function(){ this.select(); });
		}
		autoSelect(document.getElementById("cmx_artikel_lieferzeit"));
		autoSelect(document.getElementById("cmx_artikel_lager"));
	})();
	</script>';
}


/* SAVE */
\add_action('save_post_artikel', function (int $post_id, \WP_Post $post) {
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if ($post->post_type !== 'artikel') return;
	if (!\current_user_can('edit_post', $post_id)) return;
	if (!isset($_POST['cmx_artikel_lieferanten_nonce']) || !\wp_verify_nonce($_POST['cmx_artikel_lieferanten_nonce'], 'cmx_artikel_lieferanten_save')) return;


	$lager = isset($_POST['cmx_artikel_lager']) ? (int) $_POST['cmx_artikel_lager'] : 0;
	\update_post_meta($post_id, CMX_ARTIKEL_META_LAGERBESTAND, max(0, $lager));

	$kontakt_pt   = cmx_first_existing_kontakt_cpt();
	$allowed_ids  = $kontakt_pt ? cmx_fetch_lieferanten_ids($kontakt_pt) : [];
	$lieferant_id = isset($_POST['cmx_artikel_lieferant']) ? (int) $_POST['cmx_artikel_lieferant'] : 0;

	if ($lieferant_id && !empty($allowed_ids) && \in_array($lieferant_id, $allowed_ids, true)) {
		\update_post_meta($post_id, CMX_ARTIKEL_META_LIEFERANT_ID, $lieferant_id);
	} else {
		if (empty($allowed_ids) && $lieferant_id > 0) {
			$p = \get_post($lieferant_id);
			if ($p && $kontakt_pt && $p->post_type === $kontakt_pt) {
				\update_post_meta($post_id, CMX_ARTIKEL_META_LIEFERANT_ID, $lieferant_id);
			} else {
				\update_post_meta($post_id, CMX_ARTIKEL_META_LIEFERANT_ID, 0);
			}
		} else {
			\update_post_meta($post_id, CMX_ARTIKEL_META_LIEFERANT_ID, 0);
		}
	}


	$ltage = isset($_POST['cmx_artikel_lieferzeit']) ? (int) $_POST['cmx_artikel_lieferzeit'] : 0;
	\update_post_meta($post_id, CMX_ARTIKEL_META_LIEFERZEIT, max(0, $ltage));


	$url = isset($_POST['cmx_artikel_bezugsquelle']) ? \trim((string) $_POST['cmx_artikel_bezugsquelle']) : '';
	$url = $url !== '' ? \esc_url_raw($url) : '';
	\update_post_meta($post_id, CMX_ARTIKEL_META_BEZUGSQUELLE, $url);


	$lfnr = isset($_POST['cmx_artikel_lieferant_nr']) ? \sanitize_text_field((string) $_POST['cmx_artikel_lieferant_nr']) : '';
	\update_post_meta($post_id, CMX_ARTIKEL_META_LIEFERANT_NR, $lfnr);
}, 10, 2);
