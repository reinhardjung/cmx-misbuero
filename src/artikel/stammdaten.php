<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/* =========================================================
 * Helpers
 * ======================================================= */
function cmx_meta_get(int $post_id, string $key, $default = '') {
	$val = \get_post_meta($post_id, $key, true);
	return ($val === '' ? $default : $val);
}
function cmx_get_single_term_id(int $post_id, string $taxonomy): int {
	if (!\taxonomy_exists($taxonomy)) return 0;
	$terms = \wp_get_post_terms($post_id, $taxonomy, ['fields' => 'ids']);
	if (\is_wp_error($terms) || empty($terms)) return 0;
	return (int) $terms[0];
}
function cmx_get_terms_safe(string $taxonomy): array {
	if (!\taxonomy_exists($taxonomy)) return [];
	$terms = \get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
	return \is_wp_error($terms) ? [] : $terms;
}

function cmx_artikel_make_taxonomy_metabox_title_link(string $taxonomy, string $box_id, string $fallback_label): void {
	if ($taxonomy === '' || !\taxonomy_exists($taxonomy)) {
		return;
	}

	$url = \admin_url('edit-tags.php?taxonomy=' . \rawurlencode($taxonomy) . '&post_type=artikel');
	echo '<script>';
	echo 'document.addEventListener("DOMContentLoaded",function(){';
	echo 'var box=document.getElementById(' . \wp_json_encode($box_id) . ');';
	echo 'if(!box)return;';
	echo 'var title=box.querySelector(".postbox-header .hndle, .postbox-header h2, .hndle, h2.hndle");';
	echo 'if(!title||title.querySelector("a[data-cmx-tax-link=\\"1\\"]"))return;';
	echo 'var text=(title.textContent||"").trim()||' . \wp_json_encode($fallback_label) . ';';
	echo 'title.textContent="";';
	echo 'var link=document.createElement("a");';
	echo 'link.href=' . \wp_json_encode($url) . ';';
	echo 'link.target="_blank";';
	echo 'link.rel="noopener noreferrer";';
	echo 'link.dataset.cmxTaxLink="1";';
	echo 'link.style.textDecoration="none";';
	echo 'link.style.color="inherit";';
	echo 'link.style.font="inherit";';
	echo 'link.style.fontSize="inherit";';
	echo 'link.style.fontWeight="inherit";';
	echo 'link.style.lineHeight="inherit";';
	echo 'link.textContent=text;';
	echo 'title.appendChild(link);';
	echo '});';
	echo '</script>';
}

\add_action('admin_print_footer_scripts', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== 'artikel' || (string) ($screen->base ?? '') !== 'post') {
		return;
	}

	if (\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_KATEGORIEN')) {
		$taxonomy = (string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_KATEGORIEN');
		cmx_artikel_make_taxonomy_metabox_title_link($taxonomy, $taxonomy . 'div', 'Kategorien');
		cmx_artikel_make_taxonomy_metabox_title_link($taxonomy, 'tagsdiv-' . $taxonomy, 'Kategorien');
	}

	if (\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_TYPEN')) {
		$taxonomy = (string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_TYPEN');
		cmx_artikel_make_taxonomy_metabox_title_link($taxonomy, $taxonomy . 'div', 'Typen');
		cmx_artikel_make_taxonomy_metabox_title_link($taxonomy, 'tagsdiv-' . $taxonomy, 'Typen');
	}

	if (\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_FARBEN')) {
		cmx_artikel_make_taxonomy_metabox_title_link((string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_FARBEN'), 'cmx_artikel_farbe_side', 'Farben');
	}

	if (\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_MARKEN')) {
		cmx_artikel_make_taxonomy_metabox_title_link((string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_MARKEN'), 'cmx_artikel_marke_side', 'Marke');
	}
});

/** CSV → eindeutige Integer-IDs */
function cmx_csv_ids_to_array(string $csv): array {
	$out = [];
	foreach (explode(',', $csv) as $p) {
		$id = (int) trim($p);
		if ($id > 0) $out[$id] = $id;
	}
	return array_values($out);
}

/* =========================================================
 * Titel-Fallback ohne Save-Loop (bleibt erhalten)
 * ======================================================= */
\add_filter('wp_insert_post_data', function(array $data, array $postarr) {
	if (($postarr['post_type'] ?? '') !== 'artikel') return $data;

	$title      = isset($data['post_title']) ? trim(wp_strip_all_tags((string) $data['post_title'])) : '';
	$artikel_nr = isset($_POST['cmx_artikel_sku']) ? trim(wp_strip_all_tags((string) $_POST['cmx_artikel_sku'])) : '';

	if ($title === '') {
		$data['post_title'] = ($artikel_nr !== '' ? $artikel_nr : 'Artikelname fehlt');
	}
	if ($title === 'Artikelname fehlt' && $artikel_nr !== '') {
		$data['post_title'] = $artikel_nr;
	}
	return $data;
}, 10, 2);


/* =========================================================
 * Core-Taxo-Boxen ausblenden (UNVERÄNDERT)
 * ======================================================= */
\add_action('admin_menu', function () {
	\remove_meta_box('tagsdiv-'.TAX_ARTIKEL_MARKEN,    'artikel', 'side');
	\remove_meta_box(TAX_ARTIKEL_MARKEN.'div',         'artikel', 'side');
	\remove_meta_box('tagsdiv-'.TAX_ARTIKEL_FARBEN,    'artikel', 'side');
	\remove_meta_box(TAX_ARTIKEL_FARBEN.'div',         'artikel', 'side');
	\remove_meta_box('tagsdiv-'.TAX_ARTIKEL_EINHEITEN, 'artikel', 'side');
	\remove_meta_box(TAX_ARTIKEL_EINHEITEN.'div',      'artikel', 'side');

	// ALT: Stammdaten-Metabox entfernen (nur UI, KEINE Taxonomien!)
	\remove_meta_box('cmx_artikel_stammdaten', 'artikel', 'normal');
}, 50);

/* =========================================================
 * NEUE/BEIBEBLIEBENE Metaboxen (ohne Artikel-Nr.-Sidebox)
 * ======================================================= */
\add_action('add_meta_boxes', function () {
	// KEINE Artikel-Nr.-Metabox mehr hier!

	// Farben (Mehrfach) in SIDE
	\add_meta_box('cmx_artikel_farbe_side', 'Farben', __NAMESPACE__.'\\cmx_artikel_farbe_side_box', 'artikel', 'side', 'default');

	// Marke in SIDE
	\add_meta_box('cmx_artikel_marke_side', 'Marke', __NAMESPACE__.'\\cmx_artikel_marke_side_box', 'artikel', 'side', 'default');
});

/* =========================================================
 * Metabox: Farben (SIDE, Mehrfach)
 * ======================================================= */
function cmx_artikel_farbe_side_box(\WP_Post $post): void {
	$terms = cmx_get_terms_safe(TAX_ARTIKEL_FARBEN);

	$selected_ids = [];
	if (\taxonomy_exists(TAX_ARTIKEL_FARBEN)) {
		$selected_ids = \wp_get_post_terms($post->ID, TAX_ARTIKEL_FARBEN, ['fields' => 'ids']);
		if (\is_wp_error($selected_ids)) $selected_ids = [];
	}

	echo '<div class="cmx-art-side">';
	echo '<input type="hidden" name="cmx_artikel_farbe_payload" value="1">';
	if (empty($terms)) {
		echo '<p><em>Keine Farben definiert.</em></p>';
	} else {
		echo '<ul style="margin:0;padding-left:0;list-style:none;max-height:220px;overflow:auto">';
		foreach ($terms as $t) {
			$id = (int)$t->term_id;
			$checked = in_array($id, array_map('intval',$selected_ids), true) ? ' checked' : '';
			echo '<li style="margin:2px 0;"><label>';
			echo '<input type="checkbox" name="cmx_artikel_farbe_ids[]" value="'.$id.'"'.$checked.'> ';
			echo esc_html($t->name);
			if (!empty($t->description)) {
				echo '<br><small>'.esc_html(wp_strip_all_tags((string)$t->description)).'</small>';
			}
			echo '</label></li>';
		}
		echo '</ul>';
	}
	echo '</div>';
}

/* =========================================================
 * Metabox: Marke (SIDE, Single)
 * ======================================================= */
function cmx_artikel_marke_side_box(\WP_Post $post): void {
	$sel_id = cmx_get_single_term_id($post->ID, TAX_ARTIKEL_MARKEN);
	$terms  = cmx_get_terms_safe(TAX_ARTIKEL_MARKEN);

	echo '<input type="hidden" name="cmx_artikel_marke_payload" value="1">';
	echo '<p><label for="cmx_artikel_marke"><strong>Marke auswählen</strong></label><br>';
	echo '<select id="cmx_artikel_marke" name="cmx_artikel_marke" class="widefat">';
	echo '<option value="0">— auswählen —</option>';
	foreach ($terms as $t) {
		echo '<option value="'.(int)$t->term_id.'" '.selected($sel_id, $t->term_id, false).'>'.esc_html($t->name).'</option>';
	}
	echo '</select></p>';
	echo '<a href="/wp-admin/edit-tags.php?taxonomy=artikel_marken&post_type=artikel" target="_blank">verwalten</a>';
}

/* =========================================================
 * Speichern NUR für Farben/Marke (SKU wird in preise.php gespeichert)
 * ======================================================= */
\add_action('save_post_artikel', function (int $post_id, \WP_Post $post) {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) return;
	if ($post->post_type !== 'artikel') return;
	if (!\current_user_can('edit_post', $post_id)) return;

	if (!isset($_POST['cmx_artikel_nonce']) || !\wp_verify_nonce($_POST['cmx_artikel_nonce'], 'cmx_artikel_save')) return;

	$in = fn($k, $d='') => ($_POST[$k] ?? $d);

	// Farben (Mehrfach)
	if (\taxonomy_exists(TAX_ARTIKEL_FARBEN) && isset($_POST['cmx_artikel_farbe_payload'])) {
		$ids = array_map('intval', (array)($in('cmx_artikel_farbe_ids', [])));
		if (empty($ids) && isset($_POST['cmx_artikel_farben_csv'])) {
			$ids = cmx_csv_ids_to_array((string)$in('cmx_artikel_farben_csv', ''));
		}
		$ids = array_values(array_filter($ids, fn($v)=>$v>0));
		\wp_set_post_terms($post_id, $ids, TAX_ARTIKEL_FARBEN, false);
	}

	// Marke (Single)
	if (\taxonomy_exists(TAX_ARTIKEL_MARKEN) && isset($_POST['cmx_artikel_marke_payload'])) {
		$marke_id = (int) $in('cmx_artikel_marke', 0);
		\wp_set_post_terms($post_id, $marke_id ? [$marke_id] : [], TAX_ARTIKEL_MARKEN, false);
	}
}, 10, 2);
