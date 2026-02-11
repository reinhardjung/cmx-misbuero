<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * ===============================
 * Konfiguration / Hilfsfunktionen
 * ===============================
 */

function cmx_dup_allowed_post_types(): array {
	$pts = get_post_types(['public' => true], 'names');
	return (array) apply_filters('cmx_duplicate_allowed_post_types', array_values($pts));
}

function cmx_dup_get_action_url(int $post_id): string {
	return wp_nonce_url(
		add_query_arg([
			'action'  => 'cmx_duplicate_post',
			'post'    => $post_id,
			'cmx_dup_redirect' => 'edit', // optional: nach Duplikat direkt in den Edit-Screen
		], admin_url('admin-post.php')),
		'cmx_dup_nonce_' . $post_id
	);
}

function cmx_dup_meta_blacklist(): array {
	return (array) apply_filters('cmx_duplicate_meta_blacklist', [
		'_edit_lock',
		'_edit_last',
		'_cmx_rechnungsnummer', // Beleg-Nr neu vergeben
		'_cmx_beleg_qrr',       // QR-Referenz neu generieren
		'_cmx_beleg_views',      // Aufrufe nicht übernehmen
		'_cmx_beleg_views_log',  // Aufrufe-Log nicht übernehmen
	]);
}

/**
 * ============
 * Row-Actions
 * ============
 */
add_filter('post_row_actions', __NAMESPACE__.'\\cmx_dup_row_action', 10, 2);
add_filter('page_row_actions', __NAMESPACE__.'\\cmx_dup_row_action', 10, 2);
function cmx_dup_row_action(array $actions, \WP_Post $post): array {
	if (!in_array($post->post_type, cmx_dup_allowed_post_types(), true)) return $actions;
	if (!current_user_can('edit_post', $post->ID)) return $actions;

	$url = cmx_dup_get_action_url($post->ID);
	$actions['cmx_duplicate'] = '<a href="'.esc_url($url).'">'.esc_html__('Duplizieren', 'default').'</a>';
	return $actions;
}

/**
 * =============
 * Bulk-Aktion
 * =============
 */
add_action('admin_init', __NAMESPACE__.'\\cmx_dup_register_bulk_actions');
function cmx_dup_register_bulk_actions(): void {
	foreach (cmx_dup_allowed_post_types() as $pt) {
		add_filter("bulk_actions-edit-{$pt}", function(array $actions) {
			$actions['cmx_duplicate_bulk'] = __('Duplizieren', 'default');
			return $actions;
		});

		add_filter("handle_bulk_actions-edit-{$pt}", function(string $redirect, string $action, array $post_ids) {
			if ($action !== 'cmx_duplicate_bulk') return $redirect;
			if (!current_user_can('edit_posts')) return $redirect;

			$ok = 0; $fail = 0;
			foreach ($post_ids as $pid) {
				$res = cmx_duplicate_do((int)$pid);
				if (is_wp_error($res)) { $fail++; } else { $ok++; }
			}
			$redirect = remove_query_arg(['cmx_dup_ok','cmx_dup_fail'], $redirect);
			$redirect = add_query_arg([
				'cmx_dup_ok'   => $ok,
				'cmx_dup_fail' => $fail,
			], $redirect);
			return $redirect;
		}, 10, 3);
	}
}

/**
 * ======================
 * Single-Action Handler
 * ======================
 */
add_action('admin_post_cmx_duplicate_post', __NAMESPACE__.'\\cmx_duplicate_post_handler');
function cmx_duplicate_post_handler(): void {
	$post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
	if (!$post_id) wp_die(__('Ungültige Anfrage.', 'default'));

	check_admin_referer('cmx_dup_nonce_' . $post_id);

	$orig = get_post($post_id);
	if (!$orig || !in_array($orig->post_type, cmx_dup_allowed_post_types(), true)) {
		wp_die(__('Nicht erlaubt für diesen Post Type.', 'default'));
	}
	if (!current_user_can('edit_post', $post_id)) {
		wp_die(__('Keine Berechtigung.', 'default'));
	}

	$res = cmx_duplicate_do($post_id);
	$redirect_mode = isset($_GET['cmx_dup_redirect']) ? sanitize_key($_GET['cmx_dup_redirect']) : '';

	if (!is_wp_error($res) && $redirect_mode === 'edit') {
		// Direkt in den Edit-Screen des neuen Beitrags
		$target = add_query_arg([
			'cmx_dup_new' => 1,
			'message'     => 1, // WP-Standard-Meldung "Beitrag aktualisiert."
		], admin_url('post.php?action=edit&post=' . (int) $res));
		wp_safe_redirect($target);
		exit;
	}

	$redirect = wp_get_referer() ?: admin_url('edit.php?post_type=' . $orig->post_type);
	$redirect = add_query_arg(['cmx_dup_single' => is_wp_error($res) ? '0' : '1'], $redirect);
	wp_safe_redirect($redirect);
	exit;
}

/**
 * ==============================
 * Kernlogik: Post duplizieren
 * ==============================
 *
 * - Kopiert Content/Excerpt/Author (aktueller User)
 * - Status = publish
 * - Kopiert Taxonomien, Metadaten, Beitragsbild
 */
function cmx_duplicate_do(int $post_id) {
	$orig = get_post($post_id);
	if (!$orig) return new \WP_Error('cmx_dup_not_found', 'Original nicht gefunden.');
	$had_skip_pdf_generation = !empty($GLOBALS['cmx_skip_beleg_pdf_generation']);
	$GLOBALS['cmx_skip_beleg_pdf_generation'] = true;

	// Spezielle Behandlung für Belege: neue Beleg-Nr (Titel) generieren
	$beleg_no = null;
	if ($orig->post_type === 'belege') {
		$fn = __NAMESPACE__ . '\\cmx_generate_rechnungsnummer';
		if (is_callable($fn)) {
			$beleg_no = $fn();
		} else {
			// Fallback: simple timestamp-basierte Nummer
			$beleg_no = 'BELEG-' . gmdate('Ymd-His') . '-' . wp_generate_password(4, false, false);
		}
	}

	$new_postarr = [
		'post_type'    => $orig->post_type,
		'post_status'  => 'publish', // ✅ Direkt veröffentlicht
		'post_author'  => get_current_user_id(),
		'post_title'   => $beleg_no ?: cmx_dup_make_title($orig->post_title),
		'post_content' => $orig->post_content,
		'post_excerpt' => $orig->post_excerpt,
		'comment_status' => $orig->comment_status,
		'ping_status'    => $orig->ping_status,
		'menu_order'     => $orig->menu_order,
	];

	$new_id = wp_insert_post($new_postarr, true);
	if (is_wp_error($new_id)) {
		if (!$had_skip_pdf_generation) {
			unset($GLOBALS['cmx_skip_beleg_pdf_generation']);
		}
		return $new_id;
	}

	// Neue Beleg-Nr auch als Meta ablegen
	if ($beleg_no) {
		update_post_meta($new_id, '_cmx_rechnungsnummer', $beleg_no);
	}
	// Sicherstellen, dass ggf. weitere Nummern-Logik greift
	// Taxonomien kopieren
	$taxes = get_object_taxonomies($orig->post_type);
	foreach ($taxes as $tax) {
		$terms = wp_get_object_terms($post_id, $tax, ['fields' => 'ids']);
		if (!is_wp_error($terms) && !empty($terms)) {
			wp_set_object_terms($new_id, $terms, $tax, false);
		}
	}

	// Metadaten kopieren
	$blacklist = cmx_dup_meta_blacklist();
	$all_meta  = get_post_meta($post_id);
	foreach ($all_meta as $key => $values) {
		if (in_array($key, $blacklist, true)) continue;
		delete_post_meta($new_id, $key);
		foreach ((array) $values as $val) {
			add_post_meta($new_id, $key, maybe_unserialize($val));
		}
	}

	// Beitragsbild übernehmen
	$thumb_id = get_post_thumbnail_id($post_id);
	if ($thumb_id) {
		set_post_thumbnail($new_id, $thumb_id);
	}

	if (!$had_skip_pdf_generation) {
		unset($GLOBALS['cmx_skip_beleg_pdf_generation']);
	}

	// Nach dem Kopieren: Nummer/Title sicherstellen + PDF generieren
	$ensure_fn = __NAMESPACE__ . '\\cmx_ensure_rechnungsnummer';
	if ($orig->post_type === 'belege' && is_callable($ensure_fn)) {
		update_post_meta($new_id, '_cmx_title_auto', 1); // Titel darf auto-überschrieben werden
		$ensure_fn($new_id);
		$final_no = (string) get_post_meta($new_id, '_cmx_rechnungsnummer', true);
		if ($final_no !== '') {
			wp_update_post([
				'ID'         => $new_id,
				'post_title' => $final_no,
				'post_name'  => sanitize_title($final_no),
			]);
		}

		$gen_fn = __NAMESPACE__ . '\\cmxbu_generate_document_on_save';
		if (!$had_skip_pdf_generation && is_callable($gen_fn)) {
			$post_obj = get_post($new_id);
			if ($post_obj) {
				$gen_fn($new_id, $post_obj, true);
			}
		}
	}

	do_action('cmx_duplicated_post', $new_id, $post_id, $orig);

	return $new_id;
}

/** Titel-Helper */
function cmx_dup_make_title(string $title): string {
	$suffix = __(' (Kopie)', 'default');
	return (mb_substr($title, -mb_strlen($suffix)) === $suffix) ? $title : $title . $suffix;
}

/**
 * ===========================
 * Admin-Hinweis nach Aktion
 * ===========================
 */
add_action('admin_notices', function() {
	if (isset($_GET['cmx_dup_ok']) || isset($_GET['cmx_dup_fail'])) {
		$ok   = intval($_GET['cmx_dup_ok'] ?? 0);
		$fail = intval($_GET['cmx_dup_fail'] ?? 0);
		echo '<div class="notice notice-success"><p>'.
			sprintf(esc_html__('%d Kopien veröffentlicht, %d Fehler.', 'default'), $ok, $fail).
		'</p></div>';
	}
	if (isset($_GET['cmx_dup_single']) && $_GET['cmx_dup_single'] === '1') {
		echo '<div class="notice notice-success"><p>'.
			esc_html__('Kopie wurde veröffentlicht.', 'default').
		'</p></div>';
	}
	if (isset($_GET['cmx_dup_new']) && (int)$_GET['cmx_dup_new'] === 1) {
		echo '<div class="notice notice-success is-dismissible"><p>'.
			esc_html__('Beleg wurde dupliziert, gespeichert und kann jetzt versendet werden.', 'default').
		'</p></div>';
	}
});
