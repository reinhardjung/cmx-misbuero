<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

include_once 'ac_projekte.php';
include_once 'ac_leistungszeitraum.php';
include_once 'ac_kontakte.php';
include_once 'ac_kategorie.php';
include_once 'ac_konditionen.php';
include_once 'ac_summe.php';
include_once 'ac_actions.php';

/**
 * Zentraler Filter-Handler für die Belege-Liste.
 * Greift alle Selects ab (Kontakt, Kategorie, Zahlstatus, Projekt) und baut
 * eine konsistente meta_query/tax_query, damit sich die Filter nicht gegenseitig überschreiben.
 */
add_action('pre_get_posts', function(\WP_Query $q) {
	if (!is_admin() || !$q->is_main_query()) {
		return;
	}

	$post_type = $q->get('post_type');
	if (
		(is_array($post_type) && !in_array('belege', $post_type, true))
		|| (!is_array($post_type) && $post_type !== 'belege')
	) {
		return;
	}

	// ---- Parameter einsammeln (sanitizen) ----
	$kontakt_id   = isset($_GET['cmx_kontakt_id']) ? (int) $_GET['cmx_kontakt_id'] : 0;
	$proj_id      = isset($_GET['cmx_proj_id']) ? (int) $_GET['cmx_proj_id'] : 0;

	// Kategorie (Taxonomie kann belege_kategorien oder beleg_kategorie heißen)
	$tax = '';
	if (function_exists(__NAMESPACE__ . '\\cmx_belege_taxonomy')) {
		$tax = cmx_belege_taxonomy();
	}
	$cat_slug = '';
	if ($tax && !empty($_GET[$tax]) && $_GET[$tax] !== '0') {
		$cat_slug = sanitize_text_field(wp_unslash($_GET[$tax]));
	}

	if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_column_is_visible')) {
		if (!cmx_beleg_admin_column_is_visible('cmx_kontakt')) {
			$kontakt_id = 0;
		}
		if (!cmx_beleg_admin_column_is_visible('cmx_beleg_projekt')) {
			$proj_id = 0;
		}
		if (!cmx_beleg_admin_column_is_visible('cmx_belege_kategorie')) {
			$cat_slug = '';
		}
	}

	// ---- meta_query aufbauen ----
	$meta_query = ['relation' => 'AND'];

	// Kontakt-Filter
	if ($kontakt_id > 0) {
		$meta_query[] = [
			'key'     => defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT') ? CMX_BELEG_META_KONTAKT : '_cmx_beleg_kontakt_id',
			'value'   => $kontakt_id,
			'compare' => '=',
			'type'    => 'NUMERIC',
		];
	}

	// Projekt-Filter (nutzt Helper falls vorhanden)
	if ($proj_id > 0) {
		if (function_exists(__NAMESPACE__ . '\\cmx_build_project_meta_or')) {
			$meta_query[] = cmx_build_project_meta_or($proj_id);
		} else {
			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'     => '_cmx_beleg_projekt_id',
					'value'   => $proj_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
				[
					'key'     => '_cmx_projekt_id',
					'value'   => $proj_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
				[
					'key'     => '_projekt_id',
					'value'   => $proj_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
			];
		}
	}

	// Nur setzen, wenn Bedingungen existieren (mind. eine zusätzliche Klausel)
	if (count($meta_query) > 1) {
		$q->set('meta_query', $meta_query);
	}

	// ---- tax_query für Kategorie ----
	if ($cat_slug && $tax) {
		$tax_query = [
			'relation' => 'AND',
			[
				'taxonomy' => $tax,
				'field'    => 'slug',
				'terms'    => [$cat_slug],
				'operator' => 'IN',
			],
		];
		$q->set('tax_query', $tax_query);
	}
}, 20);

/**
 * Oben-rechts-Suche in der Belege-Liste um Kontakt erweitern.
 * Treffer, wenn Suchbegriff im Kontakt-Namen oder im gespeicherten Kontakt-Label vorkommt.
 */
add_filter('posts_search', function(string $search, \WP_Query $q): string {
	if (!\is_admin() || !$q->is_main_query()) {
		return $search;
	}

	$post_type = $q->get('post_type');
	if (
		(\is_array($post_type) && !\in_array('belege', $post_type, true))
		|| (!\is_array($post_type) && $post_type !== 'belege')
	) {
		return $search;
	}

	$term = \trim((string) $q->get('s'));
	if ($term === '') {
		return $search;
	}

	global $wpdb;
	$like = '%' . $wpdb->esc_like($term) . '%';
	$kontakt_meta_id = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT')
		? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT')
		: '_cmx_beleg_kontakt_id';
	$kontakt_meta_label = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_LABEL')
		? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_LABEL')
		: '_cmx_beleg_kontakt_label';
	$kontakt_post_type = \defined(__NAMESPACE__ . '\\CMX_PT_KONTAKTE')
		? (string) \constant(__NAMESPACE__ . '\\CMX_PT_KONTAKTE')
		: 'kontakte';

	$contact_sql = $wpdb->prepare(
		"(
			EXISTS (
				SELECT 1
				FROM {$wpdb->postmeta} AS cmx_klabel
				WHERE cmx_klabel.post_id = {$wpdb->posts}.ID
					AND cmx_klabel.meta_key = %s
					AND cmx_klabel.meta_value LIKE %s
			)
			OR EXISTS (
				SELECT 1
				FROM {$wpdb->postmeta} AS cmx_kid
				INNER JOIN {$wpdb->posts} AS cmx_kpost
					ON cmx_kpost.ID = CAST(cmx_kid.meta_value AS UNSIGNED)
				WHERE cmx_kid.post_id = {$wpdb->posts}.ID
					AND cmx_kid.meta_key = %s
					AND cmx_kpost.post_type = %s
					AND cmx_kpost.post_status <> 'trash'
					AND cmx_kpost.post_title LIKE %s
			)
		)",
		$kontakt_meta_label,
		$like,
		$kontakt_meta_id,
		$kontakt_post_type,
		$like
	);

	$search_sql = \trim((string) $search);
	$search_sql = (string) \preg_replace('/^\s*AND\s*/i', '', $search_sql);
	if ($search_sql === '') {
		return " AND {$contact_sql} ";
	}

	return " AND (({$search_sql}) OR {$contact_sql}) ";
}, 20, 2);

\add_filter('views_edit-belege', function(array $views): array {
	if (!\function_exists(__NAMESPACE__ . '\\cmx_admin_deckungsbeitrag_add_view')) {
		return $views;
	}

	return cmx_admin_deckungsbeitrag_add_view($views, 'belege', 'belege');
}, 30);

\add_filter('manage_edit-belege_columns', function(array $columns): array {
	if (!\function_exists(__NAMESPACE__ . '\\cmx_admin_deckungsbeitrag_view_active') || !\function_exists(__NAMESPACE__ . '\\cmx_admin_deckungsbeitrag_insert_column')) {
		return $columns;
	}
	if (!cmx_admin_deckungsbeitrag_view_active('belege')) {
		return $columns;
	}

	return cmx_admin_deckungsbeitrag_insert_column($columns, 'cmx_deckungsbeitrag', 'Deckungsbeitrag');
}, 900);

\add_action('manage_belege_posts_custom_column', function(string $column, int $post_id): void {
	if ($column !== 'cmx_deckungsbeitrag') {
		return;
	}
	if (!\function_exists(__NAMESPACE__ . '\\cmx_admin_deckungsbeitrag_view_active') || !\function_exists(__NAMESPACE__ . '\\cmx_admin_deckungsbeitrag_render_value')) {
		return;
	}
	if (!cmx_admin_deckungsbeitrag_view_active('belege')) {
		return;
	}

	cmx_admin_deckungsbeitrag_render_value('belege', $post_id);
}, 20, 2);

\add_action('pre_get_posts', function(\WP_Query $query): void {
	if (!\function_exists(__NAMESPACE__ . '\\cmx_admin_deckungsbeitrag_apply_query_sort')) {
		return;
	}

	cmx_admin_deckungsbeitrag_apply_query_sort($query, 'belege', 'belege');
}, 999);
