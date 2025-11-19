<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * Metaboxen "Belege" & "Dokumente" für CPT "kontakte"/"kontakt"
 * - getrennte Boxen mit eigener Suche/Filter/Reset
 * - Speicherung als Meta: Arrays von Post-IDs
 * - nutzt bestehende Helper/Constants, ansonsten Fallbacks
 */

/* =========================================================
 * Meta-Keys (für Kontakte)
 * ========================================================= */
if (!defined(__NAMESPACE__ . '\\CMX_KONTAKT_META_BELEGE_IDS')) {
	define(__NAMESPACE__ . '\\CMX_KONTAKT_META_BELEGE_IDS', '_cmx_kontakt_belege_ids');
}
if (!defined(__NAMESPACE__ . '\\CMX_KONTAKT_META_DOKUMENT_IDS')) {
	define(__NAMESPACE__ . '\\CMX_KONTAKT_META_DOKUMENT_IDS', '_cmx_kontakt_dokument_ids');
}

/* =========================================================
 * Kandidaten-PTs (nur falls nicht bereits definiert)
 * ========================================================= */
if (!defined(__NAMESPACE__ . '\\CMX_CANDIDATE_CPTS_KONTAKT')) {
	define(__NAMESPACE__ . '\\CMX_CANDIDATE_CPTS_KONTAKT', serialize(['kontakte', 'kontakt']));
}
if (!defined(__NAMESPACE__ . '\\CMX_CANDIDATE_CPTS_BELEG')) {
	define(__NAMESPACE__ . '\\CMX_CANDIDATE_CPTS_BELEG', serialize(['beleg', 'belege']));
}
if (!defined(__NAMESPACE__ . '\\CMX_CANDIDATE_CPTS_DOKUMENT')) {
	define(__NAMESPACE__ . '\\CMX_CANDIDATE_CPTS_DOKUMENT', serialize(['dokument', 'dokumente']));
}

/* =========================================================
 * Fallback-Helper (werden nur genutzt, wenn nicht vorhanden)
 * ========================================================= */
// if (!function_exists(__NAMESPACE__ . '\\cmx_first_existing_cpt')) {
// 	function cmx_first_existing_cpt(array $candidates): ?string {
// 		foreach ($candidates as $pt) {
// 			if (\post_type_exists($pt)) return $pt;
// 		}
// 		return null;
// 	}
// }
// if (!function_exists(__NAMESPACE__ . '\\cmx_fetch_posts_list')) {
// 	function cmx_fetch_posts_list(string $post_type): array {
// 		return \get_posts([
// 			'post_type'        => $post_type,
// 			'numberposts'      => -1,
// 			'orderby'          => 'title',
// 			'order'            => 'ASC',
// 			'post_status'      => ['publish', 'private'],
// 			'suppress_filters' => true,
// 			'fields'           => 'all',
// 		]);
// 	}
// }
// if (!function_exists(__NAMESPACE__ . '\\cmx_meta_get_ids')) {
// 	function cmx_meta_get_ids(int $post_id, string $key): array {
// 		$val = \get_post_meta($post_id, $key, true);
// 		if (empty($val)) return [];
// 		if (is_string($val)) {
// 			$try = array_filter(array_map('absint', explode(',', $val)));
// 			return array_values(array_unique($try));
// 		}
// 		if (is_array($val)) {
// 			return array_values(array_unique(array_map('absint', $val)));
// 		}
// 		return [];
// 	}
// }

/** Kontakt-PT ermitteln (unterstützt "kontakte" oder "kontakt") */
function cmx_kontakt_cpt(): ?string {
	$cands = @unserialize(CMX_CANDIDATE_CPTS_KONTAKT) ?: ['kontakte','kontakt'];
	return cmx_first_existing_cpt($cands);
}

/** Beleg/Dokument-PT ermitteln */
function cmx_beleg_pt(): ?string {
	$cands = @unserialize(CMX_CANDIDATE_CPTS_BELEG) ?: ['beleg','belege'];
	return cmx_first_existing_cpt($cands);
}
function cmx_dokument_pt(): ?string {
	$cands = @unserialize(CMX_CANDIDATE_CPTS_DOKUMENT) ?: ['dokument','dokumente'];
	return cmx_first_existing_cpt($cands);
}

/* =========================================================
 * Einmaliges Inline-CSS/JS für beide Boxen
 * ========================================================= */
\add_action('admin_print_footer_scripts', function () {
	$screen = function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || !in_array($screen->base, ['post','edit'], true)) return;

	static $printed = false;
	if ($printed) return;
	$printed = true;

	echo '<style id="cmx-anh-styles">
		.cmx-anh p{margin:0 0 8px}
		.cmx-anh .cmx-head{display:flex;gap:6px;align-items:center;margin:4px 0 6px}
		.cmx-anh .cmx-head input[type="search"]{flex:1;min-width:0}
		.cmx-anh .cmx-tools{display:flex;gap:8px;align-items:center;justify-content:space-between;margin:6px 0 8px}
		.cmx-anh .cmx-tools label{display:flex;gap:6px;align-items:center;font-size:12px;color:#555}
		.cmx-anh .cmx-badge{font-size:11px;background:#eef;border:1px solid #dde;padding:2px 6px;border-radius:10px}
		.cmx-anh .button-link{border:none;background:none;color:#2271b1;cursor:pointer;padding:0;font-size:12px;text-decoration:underline}
		.cmx-anh select{min-height:120px}
	</style>';

	echo '<script id="cmx-anh-script">
	(function(){
		function setupFilter(cfg){
			var $input = document.getElementById(cfg.searchId);
			var $only  = document.getElementById(cfg.onlyId);
			var $sel   = document.getElementById(cfg.selectId);
			var $cnt   = document.getElementById(cfg.countId);
			if(!$sel) return;

			function norm(s){ return (s||"").toLowerCase().trim(); }

			function apply(){
				var term = norm($input ? $input.value : "");
				var onlySelected = ($only && $only.checked);
				var shown = 0, total = 0;

				for (var i=0; i<$sel.options.length; i++){
					var opt = $sel.options[i];
					total++;
					var text = norm(opt.text);
					var match = (!term || text.indexOf(term) !== -1);
					var keep  = match && (!onlySelected || opt.selected);
					opt.hidden = !keep;
					if (keep) shown++;
				}
				if ($cnt) $cnt.textContent = shown + " / " + total;
			}

			function resetAll(){
				if ($input) $input.value = "";
				if ($only)  $only.checked = false;
				for (var i=0; i<$sel.options.length; i++){
					$sel.options[i].selected = false;
					$sel.options[i].hidden   = false;
				}
				if ($cnt) $cnt.textContent = $sel.options.length + " / " + $sel.options.length;
				$sel.dispatchEvent(new Event("change", {bubbles:true}));
			}

			if ($input) $input.addEventListener("input",  apply, {passive:true});
			if ($only)  $only.addEventListener("change", apply, {passive:true});

			var resetBtn = cfg.resetId && document.getElementById(cfg.resetId);
			if (resetBtn) resetBtn.addEventListener("click", function(e){ e.preventDefault(); resetAll(); }, false);

			$sel.addEventListener("change", apply, {passive:true});
			apply();
		}

		// Init aus data-cmx-init Attributen
		document.querySelectorAll("[data-cmx-init=\\"cmx-filter\\"]").forEach(function(node){
			try {
				var cfg = JSON.parse(node.getAttribute("data-cmx-config") || "{}");
				setupFilter(cfg);
			} catch(e){}
		});
	})();
	</script>';
});

/* =========================================================
 * Metaboxen registrieren
 * ========================================================= */
\add_action('add_meta_boxes', function () {
	$kontakt_pt = cmx_kontakt_cpt();
	if (!$kontakt_pt) return;

	// Belege
	\add_meta_box(
		'cmx_kont_belege',
		'Belege',
		__NAMESPACE__ . '\\cmx_kont_belege_box_html',
		$kontakt_pt,
		'side',
		'default'
	);

	// Dokumente
	\add_meta_box(
		'cmx_kont_dokumente',
		'Dokumente',
		__NAMESPACE__ . '\\cmx_kont_dokumente_box_html',
		$kontakt_pt,
		'side',
		'default'
	);
});

/* =========================================================
 * Render: Metabox "Belege"
 * ========================================================= */
function cmx_kont_belege_box_html(\WP_Post $post): void {
	\wp_nonce_field('cmx_kont_belege_save', 'cmx_kont_belege_nonce');

	$beleg_pt   = cmx_beleg_pt();
	if (!$beleg_pt) {
		echo '<p><em>Kein CPT für Belege gefunden (erwartet: <code>beleg</code> oder <code>belege</code>).</em></p>';
		return;
	}

	$belege       = cmx_fetch_posts_list($beleg_pt);
	$sel_belege   = cmx_meta_get_ids($post->ID, CMX_KONTAKT_META_BELEGE_IDS);
	$count_total  = count($belege);
	$size         = (int) min(12, max(6, $count_total ?: 6));

	echo '<div class="cmx-anh" data-cmx-init="cmx-filter" data-cmx-config=\'' . \esc_attr(json_encode([
		'searchId' => 'cmx_search_belege',
		'onlyId'   => 'cmx_only_selected_belege',
		'selectId' => 'cmx_kont_belege',
		'countId'  => 'cmx_count_belege',
		'resetId'  => 'cmx_reset_belege',
	])) . '\'>';

	echo '<p><strong>Belege zuordnen</strong></p>';

	echo '<div class="cmx-head">';
	echo '<input type="search" id="cmx_search_belege" class="regular-text" placeholder="Belege durchsuchen …" autocomplete="off">';
	echo '</div>';

	echo '<div class="cmx-tools">';
	echo '<label><input type="checkbox" id="cmx_only_selected_belege"> Nur ausgewählte</label>';
	echo '<span class="cmx-badge" id="cmx_count_belege">' . \esc_html($count_total) . ' / ' . \esc_html($count_total) . '</span>';
	echo '<button type="button" class="button-link" id="cmx_reset_belege">reset</button>';
	echo '</div>';

	echo '<select id="cmx_kont_belege" name="cmx_kont_belege[]" class="widefat" multiple size="' . (int)$size . '">';
	foreach ($belege as $b) {
		$sel = in_array((int)$b->ID, $sel_belege, true) ? ' selected' : '';
		echo '<option value="' . (int)$b->ID . '"' . $sel . '>' . \esc_html(\get_the_title($b)) . '</option>';
	}
	echo '</select>';

	if (!empty($sel_belege)) {
		echo '<br><small>Ausgewählt: ';
		$links = [];
		foreach ($sel_belege as $id) {
			$links[] = '<a href="' . \esc_url(\get_edit_post_link($id)) . '">' . \esc_html(\get_the_title($id) ?: ('#' . $id)) . '</a>';
		}
		echo implode(', ', $links) . '</small>';
	}

	echo '</div>'; // .cmx-anh
}

/* =========================================================
 * Render: Metabox "Dokumente"
 * ========================================================= */
function cmx_kont_dokumente_box_html(\WP_Post $post): void {
	\wp_nonce_field('cmx_kont_dokumente_save', 'cmx_kont_dokumente_nonce');

	$dokument_pt = cmx_dokument_pt();
	if (!$dokument_pt) {
		echo '<p><em>Kein CPT für Dokumente gefunden (erwartet: <code>dokument</code> oder <code>dokumente</code>).</em></p>';
		return;
	}

	$doks          = cmx_fetch_posts_list($dokument_pt);
	$sel_dokument  = cmx_meta_get_ids($post->ID, CMX_KONTAKT_META_DOKUMENT_IDS);
	$count_total   = count($doks);
	$size          = (int) min(12, max(6, $count_total ?: 6));

	echo '<div class="cmx-anh" data-cmx-init="cmx-filter" data-cmx-config=\'' . \esc_attr(json_encode([
		'searchId' => 'cmx_search_dokumente',
		'onlyId'   => 'cmx_only_selected_dokumente',
		'selectId' => 'cmx_kont_dokumente',
		'countId'  => 'cmx_count_dokumente',
		'resetId'  => 'cmx_reset_dokumente',
	])) . '\'>';

	echo '<p><strong>Dokumente zuordnen</strong></p>';

	echo '<div class="cmx-head">';
	echo '<input type="search" id="cmx_search_dokumente" class="regular-text" placeholder="Dokumente durchsuchen …" autocomplete="off">';
	echo '</div>';

	echo '<div class="cmx-tools">';
	echo '<label><input type="checkbox" id="cmx_only_selected_dokumente"> Nur ausgewählte</label>';
	echo '<span class="cmx-badge" id="cmx_count_dokumente">' . \esc_html($count_total) . ' / ' . \esc_html($count_total) . '</span>';
	echo '<button type="button" class="button-link" id="cmx_reset_dokumente">reset</button>';
	echo '</div>';

	echo '<select id="cmx_kont_dokumente" name="cmx_kont_dokumente[]" class="widefat" multiple size="' . (int)$size . '">';
	foreach ($doks as $d) {
		$sel = in_array((int)$d->ID, $sel_dokument, true) ? ' selected' : '';
		echo '<option value="' . (int)$d->ID . '"' . $sel . '>' . \esc_html(\get_the_title($d)) . '</option>';
	}
	echo '</select>';

	if (!empty($sel_dokument)) {
		echo '<br><small>Ausgewählt: ';
		$links = [];
		foreach ($sel_dokument as $id) {
			$links[] = '<a href="' . \esc_url(\get_edit_post_link($id)) . '">' . \esc_html(\get_the_title($id) ?: ('#' . $id)) . '</a>';
		}
		echo implode(', ', $links) . '</small>';
	}

	echo '</div>'; // .cmx-anh
}

/* =========================================================
 * Speichern
 * ========================================================= */
\add_action('save_post', function (int $post_id, \WP_Post $post) {
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	$kontakt_pt = cmx_kontakt_cpt();
	if (!$kontakt_pt || $post->post_type !== $kontakt_pt) return;
	if (!\current_user_can('edit_post', $post_id)) return;

	$beleg_pt    = cmx_beleg_pt();
	$dokument_pt = cmx_dokument_pt();

	$sanitize_ids = static function($key, $allowed_pt): array {
		$raw = isset($_POST[$key]) && is_array($_POST[$key]) ? $_POST[$key] : [];
		$ids = array_values(array_unique(array_map('absint', $raw)));
		if (!$allowed_pt || empty($ids)) return [];
		$valid = [];
		foreach ($ids as $id) {
			$p = \get_post($id);
			if ($p && $p->post_type === $allowed_pt) $valid[] = $id;
		}
		return $valid;
	};

	// Belege speichern (nur wenn Belege-Nonce vorhanden/valide)
	if (isset($_POST['cmx_kont_belege_nonce']) && \wp_verify_nonce($_POST['cmx_kont_belege_nonce'], 'cmx_kont_belege_save')) {
		$belege_ids = $sanitize_ids('cmx_kont_belege', $beleg_pt);
		\update_post_meta($post_id, CMX_KONTAKT_META_BELEGE_IDS, $belege_ids);
	}

	// Dokumente speichern (nur wenn Dokumente-Nonce vorhanden/valide)
	if (isset($_POST['cmx_kont_dokumente_nonce']) && \wp_verify_nonce($_POST['cmx_kont_dokumente_nonce'], 'cmx_kont_dokumente_save')) {
		$dokument_ids = $sanitize_ids('cmx_kont_dokumente', $dokument_pt);
		\update_post_meta($post_id, CMX_KONTAKT_META_DOKUMENT_IDS, $dokument_ids);
	}
}, 10, 2);
