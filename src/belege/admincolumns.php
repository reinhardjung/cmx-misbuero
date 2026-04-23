<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

include_once 'ac_projekte.php';
include_once 'ac_leistungszeitraum.php';
include_once 'ac_kontakte.php';
include_once 'ac_kategorie.php';
include_once 'ac_konditionen.php';
include_once 'ac_summe.php';
include_once 'ac_actions.php';

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_query_post_type')) {
	function cmx_beleg_admin_query_post_type(\WP_Query $query): string {
		$post_type = $query->get('post_type');
		if (\is_array($post_type)) {
			$post_type = (string) \reset($post_type);
		}

		$post_type = \sanitize_key((string) $post_type);
		if ($post_type !== '') {
			return $post_type;
		}

		if (isset($_GET['post_type'])) {
			$post_type = \sanitize_key((string) \wp_unslash($_GET['post_type']));
			if ($post_type !== '') {
				return $post_type;
			}
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_system_current_admin_post_type')) {
			$post_type = (string) cmx_system_current_admin_post_type();
			if ($post_type !== '') {
				return \sanitize_key($post_type);
			}
		}

		if (\function_exists('get_current_screen')) {
			$screen = \get_current_screen();
			if ($screen && !empty($screen->post_type)) {
				return \sanitize_key((string) $screen->post_type);
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_requested_ids')) {
	function cmx_beleg_admin_requested_ids(): array {
		$raw = isset($_GET['cmx_beleg_ids']) ? (string) \wp_unslash($_GET['cmx_beleg_ids']) : '';
		if ($raw === '') {
			return [];
		}

		$ids = [];
		foreach (\preg_split('/[\s,;]+/', $raw) ?: [] as $value) {
			$post_id = (int) $value;
			if ($post_id > 0) {
				$ids[] = $post_id;
			}
		}

		return \array_values(\array_unique($ids));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_search_date_value')) {
	function cmx_beleg_admin_search_date_value(string $term): string {
		$term = \trim($term);
		if ($term === '') {
			return '';
		}

		if (\preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2}|\d{4})$/', $term, $matches)) {
			$day = (int) ($matches[1] ?? 0);
			$month = (int) ($matches[2] ?? 0);
			$year = (int) ($matches[3] ?? 0);
			if ($year > 0 && $year < 100) {
				$year += 2000;
			}
			if ($year > 0 && \checkdate($month, $day, $year)) {
				return \sprintf('%04d-%02d-%02d', $year, $month, $day);
			}
			return '';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_normalize_date_value')) {
			$normalized = (string) cmx_beleg_admin_normalize_date_value($term);
			if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
				return $normalized;
			}
		}

		if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $term)) {
			return $term;
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_search_date_meta_keys')) {
	function cmx_beleg_admin_search_date_meta_keys(): array {
		$keys = [
			\defined(__NAMESPACE__ . '\\CMX_BELEG_META_DATUM')
				? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_DATUM')
				: '_cmx_beleg_rng_datum',
			\defined(__NAMESPACE__ . '\\CMX_BELEG_META_FAELLIG')
				? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_FAELLIG')
				: '_cmx_beleg_faelligkeitsdatum',
			\defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT')
				? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT')
				: (
					\defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM')
						? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM')
						: '_cmx_beleg_bezahlt_am'
				),
			'_cmx_rechnungsdatum',
			'_invoice_date',
			'_date',
			'_cmx_beleg_faellig_am',
			'cmx_beleg_bezahlt_am',
		];

		return \array_values(\array_unique(\array_filter(\array_map('strval', $keys))));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_search_day_month_pattern')) {
	function cmx_beleg_admin_search_day_month_pattern(string $term): string {
		$term = \trim($term);
		if ($term === '') {
			return '';
		}

		if (!\preg_match('/^(\d{1,2})[.\/-](\d{1,2})\.?$/', $term, $matches)) {
			return '';
		}

		$day = (int) ($matches[1] ?? 0);
		$month = (int) ($matches[2] ?? 0);
		if ($day < 1 || $day > 31 || $month < 1 || $month > 12) {
			return '';
		}

		return \sprintf('____-%02d-%02d', $month, $day);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_search_contact_ids')) {
	function cmx_beleg_admin_search_contact_ids(string $term): array {
		$term = \trim($term);
		if ($term === '') {
			return [];
		}

		$search_terms = \function_exists(__NAMESPACE__ . '\\cmx_kontakte_search_terms')
			? (array) cmx_kontakte_search_terms($term)
			: [$term];
		$search_terms = \array_values(\array_unique(\array_filter(\array_map('strval', $search_terms))));
		if ($search_terms === []) {
			return [];
		}

		$kontakt_post_type = \defined(__NAMESPACE__ . '\\CMX_PT_KONTAKTE')
			? (string) \constant(__NAMESPACE__ . '\\CMX_PT_KONTAKTE')
			: 'kontakte';

		$lookup_args = [
			'post_type'              => $kontakt_post_type,
			'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'suppress_filters'       => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];

		$matched_ids = [];
		foreach ($search_terms as $lookup_term) {
			$matched_ids = \array_merge($matched_ids, (array) \get_posts(\array_merge($lookup_args, [
				's' => $lookup_term,
			])));
		}

		$meta_keys = [
			\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_FIRMA')
				? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_FIRMA')
				: '_cmx_kontakte_firma',
			\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_VORNAME')
				? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_VORNAME')
				: '_cmx_kontakte_vorname',
			\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_NACHNAME')
				? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_NACHNAME')
				: '_cmx_kontakte_nachname',
		];
		$meta_keys = \array_values(\array_unique(\array_filter(\array_map('strval', $meta_keys))));

		if ($meta_keys !== []) {
			$meta_query = ['relation' => 'OR'];
			foreach ($search_terms as $lookup_term) {
				foreach ($meta_keys as $meta_key) {
					$meta_query[] = [
						'key'     => $meta_key,
						'value'   => $lookup_term,
						'compare' => 'LIKE',
					];
				}
			}

			$matched_ids = \array_merge($matched_ids, (array) \get_posts(\array_merge($lookup_args, [
				's'          => '',
				'meta_query' => $meta_query,
			])));
		}

		return \array_values(\array_unique(\array_map('intval', $matched_ids)));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_search_amount_ids')) {
	function cmx_beleg_admin_search_amount_ids(string $term): array {
		static $cache = [];
		static $total_cache = [];

		$term = \trim($term);
		if ($term === '') {
			return [];
		}
		if (isset($cache[$term])) {
			return $cache[$term];
		}

		$raw_amount = (string) \preg_replace('/\s*(chf|fr\.?)\s*/iu', '', $term);
		$raw_amount = \trim($raw_amount);
		if ($raw_amount === '' || !\preg_match('/\d/u', $raw_amount)) {
			return $cache[$term] = [];
		}
		if (!\preg_match('/^[\d\s\'.’,`´′+\-]+$/u', $raw_amount)) {
			return $cache[$term] = [];
		}

		$search_amount = \function_exists(__NAMESPACE__ . '\\cmx_parse_number')
			? (float) cmx_parse_number($raw_amount)
			: (float) \str_replace(',', '.', \str_replace(["'", ' '], '', $raw_amount));
		$search_amount = \round($search_amount, 2);

		$post_ids = (array) \get_posts([
			'post_type'              => 'belege',
			'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'suppress_filters'       => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		]);

		$matched_ids = [];
		foreach ($post_ids as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0) {
				continue;
			}

			if (!isset($total_cache[$post_id])) {
				if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
					$calc = (array) cmxbu_get_beleg_positionen_calc($post_id);
					$total_cache[$post_id] = \round((float) ($calc['total'] ?? 0.0), 2);
				} else {
					$total_cache[$post_id] = 0.0;
				}
			}

			if (\abs((float) $total_cache[$post_id] - $search_amount) < 0.00001) {
				$matched_ids[] = $post_id;
			}
		}

		return $cache[$term] = \array_values(\array_unique(\array_map('intval', $matched_ids)));
	}
}

/**
 * Zentraler Filter-Handler für die Belege-Liste.
 * Greift alle Selects ab (Kontakt, Kategorie, Zahlstatus, Projekt) und baut
 * eine konsistente meta_query/tax_query, damit sich die Filter nicht gegenseitig überschreiben.
 */
add_action('pre_get_posts', function(\WP_Query $q) {
	if (!is_admin() || !$q->is_main_query()) {
		return;
	}

	if (cmx_beleg_admin_query_post_type($q) !== 'belege') {
		return;
	}

	// ---- Parameter einsammeln (sanitizen) ----
	$kontakt_id   = \function_exists(__NAMESPACE__ . '\\cmx_kontakt_request_kontakt_id')
		? (int) cmx_kontakt_request_kontakt_id()
		: (isset($_GET['cmx_kontakt_id']) ? (int) $_GET['cmx_kontakt_id'] : 0);
	$proj_id      = isset($_GET['cmx_proj_id']) ? (int) $_GET['cmx_proj_id'] : 0;
	$woo_only     = isset($_GET['cmx_woo']) && (string) \wp_unslash($_GET['cmx_woo']) === '1';

	// Kategorie (Taxonomie kann belege_kategorien oder beleg_kategorie heißen)
	$tax = '';
	if (function_exists(__NAMESPACE__ . '\\cmx_belege_taxonomy')) {
		$tax = cmx_belege_taxonomy();
	}
	$cat_slug = '';
	if ($tax && !empty($_GET[$tax]) && $_GET[$tax] !== '0') {
		$cat_slug = sanitize_text_field(wp_unslash($_GET[$tax]));
	}

	// ---- meta_query aufbauen ----
	$meta_query = ['relation' => 'AND'];

	// Kontakt-Filter
	if ($kontakt_id > 0) {
		if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_kontakt_meta_query')) {
			$kontakt_filter = cmx_beleg_kontakt_meta_query($kontakt_id);
		} else {
			$kontakt_filter = [
				'key'     => defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT') ? CMX_BELEG_META_KONTAKT : '_cmx_beleg_kontakt_id',
				'value'   => $kontakt_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			];
		}
		if (!empty($kontakt_filter)) {
			$meta_query[] = $kontakt_filter;
		}
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

	if ($woo_only) {
		$meta_key = \function_exists(__NAMESPACE__ . '\\cmx_woocommerce_webhook_beleg_meta_key')
			? cmx_woocommerce_webhook_beleg_meta_key()
			: 'cmx_woo_webhook';
		$meta_query[] = [
			'key'     => $meta_key,
			'value'   => '1',
			'compare' => '=',
		];
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

	$requested_ids = cmx_beleg_admin_requested_ids();
	if ($requested_ids !== []) {
		$q->set('post__in', $requested_ids);
		$q->set('orderby', 'post__in');
		$q->set('order', 'ASC');
	}
}, 20);

\add_action('restrict_manage_posts', function ($post_type = '', $which = ''): void {
	if ($post_type !== 'belege') {
		return;
	}
	if ($which !== 'top') {
		return;
	}
	if (!\current_user_can('edit_posts')) {
		return;
	}

	$checked = isset($_GET['cmx_woo']) && (string) \wp_unslash($_GET['cmx_woo']) === '1';
	$classes = 'cmx-belege-woo-filter' . ($checked ? ' is-checked' : '');

	echo '<label for="cmx_woo" class="' . \esc_attr($classes) . '">';
	echo '<input type="checkbox" name="cmx_woo" id="cmx_woo" value="1" ' . \checked($checked, true, false) . '>';
	echo '<span>' . \esc_html__('Woo', 'cmx-misbuero') . '</span>';
	echo '</label>';
}, 999, 2);

\add_action('admin_head-edit.php', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) $screen->id !== 'edit-belege') {
		return;
	}

	echo '<style>
		#posts-filter .tablenav.top .actions {
			display: flex;
			align-items: center;
			gap: 8px;
			flex-wrap: wrap;
		}
		#posts-filter .tablenav.top .actions > * {
			float: none !important;
			margin: 0 !important;
			align-self: center;
		}
		#posts-filter .tablenav.top .actions select,
		#posts-filter .tablenav.top .actions .button {
			height: 32px;
			min-height: 32px;
			line-height: 30px;
			border-radius: 6px;
			margin: 0;
			vertical-align: middle;
		}
		#posts-filter .tablenav.top .actions .button {
			padding-top: 0;
			padding-bottom: 0;
		}
		#posts-filter .tablenav.top .actions .cmx-belege-woo-filter {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			height: 32px;
			min-height: 32px;
			padding: 0 12px;
			margin: 0;
			border: 1px solid #c3c4c7;
			border-radius: 6px;
			background: linear-gradient(180deg, #ffffff 0%, #f6f7f7 100%);
			box-shadow: 0 1px 0 rgba(15, 23, 42, 0.04);
			color: #1d2327;
			cursor: pointer;
			vertical-align: middle;
		}
		#posts-filter .tablenav.top .actions .cmx-belege-woo-filter.is-checked {
			border-color: #2271b1;
			background: #eef6ff;
			color: #0a4b78;
		}
			#posts-filter .tablenav.top .actions .cmx-belege-woo-filter input[type="checkbox"] {
				width: 16px;
				height: 16px;
				margin: 0;
				accent-color: #2271b1;
			}
			#posts-filter .tablenav.top .actions .cmx-belege-woo-filter span {
				font-weight: 600;
				line-height: 1;
				white-space: nowrap;
			}
			.wp-list-table th#title,
			.wp-list-table th.manage-column.column-title,
			.wp-list-table th.manage-column.column-title > a,
			.wp-list-table th.manage-column.column-title > span,
			.wp-list-table td.title.column-title,
			.wp-list-table td.column-title strong,
			.wp-list-table td.column-title .row-title {
				white-space: nowrap !important;
			}
			.wp-list-table th#title,
			.wp-list-table th.manage-column.column-title,
			.wp-list-table td.title.column-title {
				width: 15ch !important;
				min-width: 15ch !important;
				max-width: 15ch !important;
			}
			.wp-list-table td.column-title strong,
			.wp-list-table td.column-title .row-title {
				display: inline-block;
				max-width: 15ch;
				overflow: hidden;
				text-overflow: ellipsis;
				vertical-align: bottom;
			}
		</style>';
	});

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
	$kontakt_meta_keys = \function_exists(__NAMESPACE__ . '\\cmx_beleg_kontakt_meta_keys')
		? (array) cmx_beleg_kontakt_meta_keys()
		: [
			\defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT')
				? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT')
				: '_cmx_beleg_kontakt_id',
		];
	$kontakt_meta_keys = \array_values(\array_unique(\array_filter(\array_map('strval', $kontakt_meta_keys))));
	$kontakt_meta_label = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_LABEL')
		? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_LABEL')
		: '_cmx_beleg_kontakt_label';
	$contact_conditions = [];
	$contact_conditions[] = $wpdb->prepare(
		"EXISTS (
			SELECT 1
			FROM {$wpdb->postmeta} AS cmx_klabel
			WHERE cmx_klabel.post_id = {$wpdb->posts}.ID
				AND cmx_klabel.meta_key = %s
				AND cmx_klabel.meta_value LIKE %s
		)",
		$kontakt_meta_label,
		$like
	);

	$matching_contact_ids = \function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_search_contact_ids')
		? (array) cmx_beleg_admin_search_contact_ids($term)
		: [];
	if ($matching_contact_ids !== [] && $kontakt_meta_keys !== []) {
		$meta_placeholders = \implode(', ', \array_fill(0, \count($kontakt_meta_keys), '%s'));
		$id_placeholders = \implode(', ', \array_fill(0, \count($matching_contact_ids), '%d'));
		$contact_conditions[] = $wpdb->prepare(
			"EXISTS (
				SELECT 1
				FROM {$wpdb->postmeta} AS cmx_kid
				WHERE cmx_kid.post_id = {$wpdb->posts}.ID
					AND cmx_kid.meta_key IN ({$meta_placeholders})
					AND CAST(cmx_kid.meta_value AS UNSIGNED) IN ({$id_placeholders})
			)",
			\array_merge($kontakt_meta_keys, \array_map('intval', $matching_contact_ids))
		);
	}

	$contact_sql = $contact_conditions !== []
		? '(' . \implode(' OR ', \array_filter($contact_conditions)) . ')'
		: '';
	$date_sql = '';
	$date_conditions = [];
	$date_meta_keys = \function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_search_date_meta_keys')
		? (array) cmx_beleg_admin_search_date_meta_keys()
		: [];
	$date_value = cmx_beleg_admin_search_date_value($term);
	if ($date_value !== '' && $date_meta_keys !== []) {
		foreach ($date_meta_keys as $meta_key) {
			$date_conditions[] = $wpdb->prepare(
				"EXISTS (
					SELECT 1
					FROM {$wpdb->postmeta} AS cmx_bdate
					WHERE cmx_bdate.post_id = {$wpdb->posts}.ID
						AND cmx_bdate.meta_key = %s
						AND cmx_bdate.meta_value = %s
				)",
				(string) $meta_key,
				$date_value
			);
		}
	}
	$date_day_month_pattern = \function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_search_day_month_pattern')
		? (string) cmx_beleg_admin_search_day_month_pattern($term)
		: '';
	if ($date_day_month_pattern !== '' && $date_meta_keys !== []) {
		foreach ($date_meta_keys as $meta_key) {
			$date_conditions[] = $wpdb->prepare(
				"EXISTS (
					SELECT 1
					FROM {$wpdb->postmeta} AS cmx_bdate_partial
					WHERE cmx_bdate_partial.post_id = {$wpdb->posts}.ID
						AND cmx_bdate_partial.meta_key = %s
						AND cmx_bdate_partial.meta_value LIKE %s
				)",
				(string) $meta_key,
				$date_day_month_pattern
			);
		}
	}
	$date_sql = $date_conditions !== []
		? '(' . \implode(' OR ', \array_values(\array_unique(\array_filter($date_conditions)))) . ')'
		: '';

	$amount_sql = '';
	$matching_amount_ids = \function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_search_amount_ids')
		? (array) cmx_beleg_admin_search_amount_ids($term)
		: [];
	if ($matching_amount_ids !== []) {
		$id_placeholders = \implode(', ', \array_fill(0, \count($matching_amount_ids), '%d'));
		$amount_sql = $wpdb->prepare(
			"{$wpdb->posts}.ID IN ({$id_placeholders})",
			\array_map('intval', $matching_amount_ids)
		);
	}

	$extra_conditions = \array_values(\array_filter([$contact_sql, $date_sql, $amount_sql]));
	$extra_sql = \implode(' OR ', $extra_conditions);

	$search_sql = \trim((string) $search);
	$search_sql = (string) \preg_replace('/^\s*AND\s*/i', '', $search_sql);
	if ($search_sql === '') {
		return $extra_sql !== '' ? " AND ({$extra_sql}) " : $search;
	}

	if ($extra_sql === '') {
		return " AND ({$search_sql}) ";
	}

	return " AND (({$search_sql}) OR {$extra_sql}) ";
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

\add_filter('manage_edit-belege_columns', function(array $columns): array {
	if (isset($columns['title'])) {
		$columns['title'] = 'Nummer';
	}
	return $columns;
}, 9999);

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
