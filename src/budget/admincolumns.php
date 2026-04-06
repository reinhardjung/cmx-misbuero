<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_admin_insert_columns_after')) {
	function cmx_budget_admin_insert_columns_after(array $columns, string $after_key, array $new_columns): array {
		if (empty($new_columns)) {
			return $columns;
		}

		$reordered = [];
		$inserted = false;

		foreach ($columns as $key => $label) {
			$reordered[$key] = $label;
			if ($key === $after_key) {
				foreach ($new_columns as $new_key => $new_label) {
					$reordered[$new_key] = $new_label;
				}
				$inserted = true;
			}
		}

		if (!$inserted) {
			foreach ($new_columns as $new_key => $new_label) {
				$reordered[$new_key] = $new_label;
			}
		}

		return $reordered;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_admin_cost_type')) {
	function cmx_budget_admin_cost_type(int $post_id): string {
		$value = (string) \get_post_meta($post_id, CMX_BUDGET_KOSTEN_TYP_META, true);
		return $value === 'ausgabe' ? 'ausgabe' : 'einnahme';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_admin_meta_value')) {
	function cmx_budget_admin_meta_value(int $post_id, string $meta_key): string {
		return \trim((string) \get_post_meta($post_id, $meta_key, true));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_admin_amount_display')) {
	function cmx_budget_admin_amount_display(int $post_id, string $type): string {
		if (cmx_budget_admin_cost_type($post_id) !== $type) {
			return '';
		}

		$value = cmx_budget_admin_meta_value($post_id, CMX_BUDGET_KOSTEN_BETRAG_META);
		return $value !== '' ? cmx_budget_kosten_format_display($value) : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_admin_anteil_display')) {
	function cmx_budget_admin_anteil_display(int $post_id): string {
		return cmx_budget_admin_meta_value($post_id, CMX_BUDGET_KOSTEN_ANTEIL_META);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_admin_anteil_betrag_display')) {
	function cmx_budget_admin_anteil_betrag_display(int $post_id): string {
		$stored = cmx_budget_admin_meta_value($post_id, CMX_BUDGET_KOSTEN_ANTEIL_BETRAG_META);
		if ($stored !== '') {
			return cmx_budget_kosten_format_display($stored);
		}

		$betrag = cmx_budget_admin_meta_value($post_id, CMX_BUDGET_KOSTEN_BETRAG_META);
		$anteil = cmx_budget_admin_meta_value($post_id, CMX_BUDGET_KOSTEN_ANTEIL_META);
		if ($betrag === '' || $anteil === '') {
			return '';
		}

		$calculated = cmx_budget_kosten_calculate_anteil_betrag($betrag, $anteil);
		return $calculated !== '' ? cmx_budget_kosten_format_display($calculated) : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_admin_categories_display')) {
	function cmx_budget_admin_categories_display(int $post_id): string {
		$taxonomy = \defined(__NAMESPACE__ . '\\CMX_TAX_BUDGET')
			? (string) \constant(__NAMESPACE__ . '\\CMX_TAX_BUDGET')
			: '';
		if ($taxonomy === '') {
			return '';
		}

		$terms = \get_the_terms($post_id, $taxonomy);
		if (!\is_array($terms) || \is_wp_error($terms) || $terms === []) {
			return '';
		}

		$labels = [];
		foreach ($terms as $term) {
			if (!$term instanceof \WP_Term) {
				continue;
			}
			$name = \trim((string) $term->name);
			if ($name !== '') {
				$labels[] = $name;
			}
		}

		return \implode(', ', \array_unique($labels));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_admin_taxonomy')) {
	function cmx_budget_admin_taxonomy(): string {
		return \defined(__NAMESPACE__ . '\\CMX_TAX_BUDGET')
			? (string) \constant(__NAMESPACE__ . '\\CMX_TAX_BUDGET')
			: '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_admin_contact_meta_key')) {
	function cmx_budget_admin_contact_meta_key(): string {
		return \defined(__NAMESPACE__ . '\\CMX_BUDGET_KONTAKT_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BUDGET_KONTAKT_META')
			: '_cmx_budget_kontakt_id';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_admin_contact_post_types')) {
	function cmx_budget_admin_contact_post_types(): array {
		if (\function_exists(__NAMESPACE__ . '\\cmx_budget_kontakt_post_types')) {
			return (array) cmx_budget_kontakt_post_types();
		}

		$types = [];
		foreach (['kontakte', 'kontakt', 'contact'] as $post_type) {
			if (\post_type_exists($post_type)) {
				$types[] = $post_type;
			}
		}
		return $types;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_admin_contact_filter_options')) {
	function cmx_budget_admin_contact_filter_options(): array {
		$post_types = \array_values(\array_filter(cmx_budget_admin_contact_post_types(), 'post_type_exists'));
		if ($post_types === []) {
			return [];
		}

		$posts = \get_posts([
			'post_type'              => $post_types,
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		]);

		$options = [];
		foreach ($posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0) {
				continue;
			}
			$title = \trim((string) \get_the_title($post_id));
			$options[$post_id] = $title !== '' ? cmx_normalize_minus_sign($title) : ('#' . $post_id);
		}

		\natcasesort($options);
		return $options;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_admin_is_list_query')) {
	function cmx_budget_admin_is_list_query(\WP_Query $query): bool {
		if (!\is_admin() || !$query->is_main_query()) {
			return false;
		}

		$post_type = $query->get('post_type');
		if (\is_array($post_type)) {
			return \in_array('budget', $post_type, true);
		}

		return (string) $post_type === 'budget';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_admin_doc_upload_url_from_rel')) {
	function cmx_budget_admin_doc_upload_url_from_rel(string $file_rel): string {
		$file_rel = \ltrim(\str_replace('\\', '/', $file_rel), '/');
		if ($file_rel === '') {
			return '';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_dok_admin_upload_url_from_rel')) {
			return (string) cmx_dok_admin_upload_url_from_rel($file_rel);
		}

		$abs = \wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads/' . $file_rel));
		if ($abs === '' || !\is_file($abs)) {
			return '';
		}

		return (string) \content_url('/uploads/' . $file_rel);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_admin_doc_files_for_doc')) {
	function cmx_budget_admin_doc_files_for_doc(int $doc_id): array {
		if ($doc_id <= 0 || (string) \get_post_type($doc_id) !== 'dokumente') {
			return [];
		}

		$self_meta_key = \defined(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
			: '_cmx_dokumente_files';
		$self_files = (array) \get_post_meta($doc_id, $self_meta_key, true);
		$self_files = \array_values(\array_filter($self_files, static function ($value): bool {
			return (\is_string($value) && $value !== '') || \is_numeric($value);
		}));

		$items = [];
		for ($i = \count($self_files) - 1; $i >= 0; $i--) {
			$entry = $self_files[$i];
			$file_rel = '';
			if (\is_numeric($entry)) {
				$file_rel = (string) \get_post_meta((int) $entry, '_wp_attached_file', true);
			} else {
				$file_rel = (string) $entry;
			}

			$url = cmx_budget_admin_doc_upload_url_from_rel($file_rel);
			if ($url === '') {
				continue;
			}

			$ext = \strtolower((string) \pathinfo((string) $file_rel, \PATHINFO_EXTENSION));
			$items[] = [
				'url' => $url,
				'ext' => $ext,
			];
		}

		if ($items === []) {
			$file_rel = (string) \get_post_meta($doc_id, '_cmx_dokumente_file_path', true);
			$url = cmx_budget_admin_doc_upload_url_from_rel($file_rel);
			if ($url !== '') {
				$items[] = [
					'url' => $url,
					'ext' => \strtolower((string) \pathinfo((string) $file_rel, \PATHINFO_EXTENSION)),
				];
			}
		}

		return $items;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_admin_document_icon_data')) {
	function cmx_budget_admin_document_icon_data(int $post_id): array {
		$uploads_meta_key = \defined(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
			: '_cmx_dokumente_uploads';
		$doc_ids = (array) \get_post_meta($post_id, $uploads_meta_key, true);
		$doc_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $doc_ids))));
		if ($doc_ids === []) {
			return ['icon' => '', 'url' => ''];
		}

		$image_exts = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg'];
		$image_url = '';
		$pdf_url = '';

		for ($i = \count($doc_ids) - 1; $i >= 0; $i--) {
			$files = cmx_budget_admin_doc_files_for_doc((int) $doc_ids[$i]);
			foreach ($files as $file) {
				$url = (string) ($file['url'] ?? '');
				$ext = \strtolower((string) ($file['ext'] ?? ''));
				if ($url === '' || $ext === '') {
					continue;
				}

				if ($image_url === '' && \in_array($ext, $image_exts, true)) {
					$image_url = $url;
				}
				if ($pdf_url === '' && $ext === 'pdf') {
					$pdf_url = $url;
				}

				if ($image_url !== '' && $pdf_url !== '') {
					break 2;
				}
			}
		}

		if ($image_url !== '') {
			return ['icon' => 'image', 'url' => $image_url];
		}
		if ($pdf_url !== '') {
			return ['icon' => 'pdf', 'url' => $pdf_url];
		}

		return ['icon' => '', 'url' => ''];
	}
}

\add_filter('manage_edit-budget_columns', function (array $columns): array {
	if (isset($columns['title'])) {
		$columns['title'] = 'Name';
	}

	$new_columns = [
		'cmx_budget_kategorien'     => 'Kategorien',
		'cmx_budget_einnahme'       => 'Einnahme',
		'cmx_budget_ausgabe'        => 'Ausgabe',
		'cmx_budget_anteil'         => 'Anteil',
		'cmx_budget_anteil_betrag'  => 'Anteil Betrag',
	];

	$columns = cmx_budget_admin_insert_columns_after($columns, 'title', $new_columns);
	unset($columns['cmx_budget_datei']);
	$columns['cmx_budget_datei'] = 'Datei';
	return $columns;
}, 20);

\add_filter('manage_edit-budget_sortable_columns', function (array $columns): array {
	$columns['cmx_budget_einnahme'] = 'cmx_budget_einnahme';
	$columns['cmx_budget_ausgabe'] = 'cmx_budget_ausgabe';
	$columns['cmx_budget_anteil'] = 'cmx_budget_anteil';
	$columns['cmx_budget_anteil_betrag'] = 'cmx_budget_anteil_betrag';
	return $columns;
});

\add_action('restrict_manage_posts', function (string $post_type, string $which = 'top'): void {
	if ($post_type !== 'budget' || $which !== 'top') {
		return;
	}

	$taxonomy = cmx_budget_admin_taxonomy();
	if ($taxonomy !== '' && \taxonomy_exists($taxonomy)) {
		$selected_term = isset($_GET['cmx_budget_kategorie']) ? (int) $_GET['cmx_budget_kategorie'] : 0;
		\wp_dropdown_categories([
			'show_option_all' => 'Alle Kategorien',
			'taxonomy'        => $taxonomy,
			'name'            => 'cmx_budget_kategorie',
			'orderby'         => 'name',
			'selected'        => $selected_term,
			'hierarchical'    => true,
			'hide_empty'      => false,
			'value_field'     => 'term_id',
		]);
	}

	$selected_contact = isset($_GET['cmx_budget_kontakt_filter']) ? (int) $_GET['cmx_budget_kontakt_filter'] : 0;
	$options = cmx_budget_admin_contact_filter_options();
	if ($selected_contact > 0 && !isset($options[$selected_contact]) && \get_post_status($selected_contact)) {
		$title = \trim((string) \get_the_title($selected_contact));
		$options[$selected_contact] = $title !== '' ? cmx_normalize_minus_sign($title) : ('#' . $selected_contact);
		\natcasesort($options);
	}

	if ($options !== []) {
		echo '<select name="cmx_budget_kontakt_filter">';
		echo '<option value="0">Alle Kontakte</option>';
		foreach ($options as $contact_id => $label) {
			printf(
				'<option value="%d"%s>%s</option>',
				(int) $contact_id,
				\selected($selected_contact, (int) $contact_id, false),
				\esc_html((string) $label)
			);
		}
		echo '</select>';
	}
}, 10, 2);

\add_action('pre_get_posts', function (\WP_Query $query): void {
	if (!cmx_budget_admin_is_list_query($query)) {
		return;
	}

	$taxonomy = cmx_budget_admin_taxonomy();
	$selected_term = isset($_GET['cmx_budget_kategorie']) ? (int) $_GET['cmx_budget_kategorie'] : 0;
	if ($taxonomy !== '' && \taxonomy_exists($taxonomy) && $selected_term > 0) {
		$tax_query = (array) $query->get('tax_query');
		$tax_query[] = [
			'taxonomy'         => $taxonomy,
			'field'            => 'term_id',
			'terms'            => [$selected_term],
			'include_children' => true,
		];
		if (!isset($tax_query['relation'])) {
			$tax_query = \array_merge(['relation' => 'AND'], $tax_query);
		}
		$query->set('tax_query', $tax_query);
	}

	$selected_contact = isset($_GET['cmx_budget_kontakt_filter']) ? (int) $_GET['cmx_budget_kontakt_filter'] : 0;
	if ($selected_contact > 0) {
		$meta_query = (array) $query->get('meta_query');
		$meta_query[] = [
			'key'     => cmx_budget_admin_contact_meta_key(),
			'value'   => $selected_contact,
			'compare' => '=',
			'type'    => 'NUMERIC',
		];
		if (!isset($meta_query['relation'])) {
			$meta_query = \array_merge(['relation' => 'AND'], $meta_query);
		}
		$query->set('meta_query', $meta_query);
	}
});

\add_filter('posts_clauses', function (array $clauses, \WP_Query $query): array {
	if (!cmx_budget_admin_is_list_query($query)) {
		return $clauses;
	}

	$orderby = (string) $query->get('orderby');
	if (!\in_array($orderby, ['cmx_budget_einnahme', 'cmx_budget_ausgabe', 'cmx_budget_anteil', 'cmx_budget_anteil_betrag'], true)) {
		return $clauses;
	}

	global $wpdb;

	$type_key = \esc_sql(CMX_BUDGET_KOSTEN_TYP_META);
	$betrag_key = \esc_sql(CMX_BUDGET_KOSTEN_BETRAG_META);
	$anteil_key = \esc_sql(CMX_BUDGET_KOSTEN_ANTEIL_META);
	$anteil_betrag_key = \esc_sql(CMX_BUDGET_KOSTEN_ANTEIL_BETRAG_META);

	if (\strpos($clauses['join'], 'cmx_budget_type_pm') === false) {
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} cmx_budget_type_pm ON ({$wpdb->posts}.ID = cmx_budget_type_pm.post_id AND cmx_budget_type_pm.meta_key = '{$type_key}')";
	}
	if (\strpos($clauses['join'], 'cmx_budget_betrag_pm') === false) {
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} cmx_budget_betrag_pm ON ({$wpdb->posts}.ID = cmx_budget_betrag_pm.post_id AND cmx_budget_betrag_pm.meta_key = '{$betrag_key}')";
	}
	if (\strpos($clauses['join'], 'cmx_budget_anteil_pm') === false) {
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} cmx_budget_anteil_pm ON ({$wpdb->posts}.ID = cmx_budget_anteil_pm.post_id AND cmx_budget_anteil_pm.meta_key = '{$anteil_key}')";
	}
	if (\strpos($clauses['join'], 'cmx_budget_anteil_betrag_pm') === false) {
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} cmx_budget_anteil_betrag_pm ON ({$wpdb->posts}.ID = cmx_budget_anteil_betrag_pm.post_id AND cmx_budget_anteil_betrag_pm.meta_key = '{$anteil_betrag_key}')";
	}

	$order = \strtoupper((string) $query->get('order')) === 'DESC' ? 'DESC' : 'ASC';
	$betrag_expr = "CAST(COALESCE(NULLIF(cmx_budget_betrag_pm.meta_value, ''), '0') AS DECIMAL(20,2))";
	$anteil_expr = "CAST(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(NULLIF(cmx_budget_anteil_pm.meta_value, ''), '0'), '%', ''), ',', '.'), CHAR(39), ''), ' ', '') AS DECIMAL(20,2))";
	$anteil_betrag_expr = "CAST(COALESCE(NULLIF(cmx_budget_anteil_betrag_pm.meta_value, ''), '0') AS DECIMAL(20,2))";

	if ($orderby === 'cmx_budget_einnahme') {
		$clauses['orderby'] = "CASE WHEN cmx_budget_type_pm.meta_value = 'einnahme' THEN 0 ELSE 1 END ASC, CASE WHEN cmx_budget_type_pm.meta_value = 'einnahme' THEN {$betrag_expr} ELSE NULL END {$order}, {$wpdb->posts}.post_title ASC";
		return $clauses;
	}

	if ($orderby === 'cmx_budget_ausgabe') {
		$clauses['orderby'] = "CASE WHEN cmx_budget_type_pm.meta_value = 'ausgabe' THEN 0 ELSE 1 END ASC, CASE WHEN cmx_budget_type_pm.meta_value = 'ausgabe' THEN {$betrag_expr} ELSE NULL END {$order}, {$wpdb->posts}.post_title ASC";
		return $clauses;
	}

	if ($orderby === 'cmx_budget_anteil') {
		$clauses['orderby'] = "{$anteil_expr} {$order}, {$wpdb->posts}.post_title ASC";
		return $clauses;
	}

	if ($orderby === 'cmx_budget_anteil_betrag') {
		$clauses['orderby'] = "{$anteil_betrag_expr} {$order}, {$wpdb->posts}.post_title ASC";
	}

	return $clauses;
}, 10, 2);

\add_action('manage_budget_posts_custom_column', function (string $column, int $post_id): void {
	if ($column === 'cmx_budget_kategorien') {
		$label = cmx_budget_admin_categories_display($post_id);
		echo $label !== '' ? \esc_html($label) : '<span aria-hidden="true"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}

	if ($column === 'cmx_budget_einnahme') {
		$label = cmx_budget_admin_amount_display($post_id, 'einnahme');
		echo $label !== '' ? \esc_html($label) : '<span aria-hidden="true"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}

	if ($column === 'cmx_budget_ausgabe') {
		$label = cmx_budget_admin_amount_display($post_id, 'ausgabe');
		echo $label !== '' ? \esc_html($label) : '<span aria-hidden="true"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}

	if ($column === 'cmx_budget_anteil') {
		$label = cmx_budget_admin_anteil_display($post_id);
		echo $label !== '' ? \esc_html($label) : '<span aria-hidden="true"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}

	if ($column === 'cmx_budget_anteil_betrag') {
		$label = cmx_budget_admin_anteil_betrag_display($post_id);
		echo $label !== '' ? \esc_html($label) : '<span aria-hidden="true"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}

	if ($column === 'cmx_budget_datei') {
		$data = cmx_budget_admin_document_icon_data($post_id);
		$icon = (string) ($data['icon'] ?? '');
		$url = (string) ($data['url'] ?? '');
		if ($icon === '' || $url === '') {
			echo '<span class="cmx-budget-doc-placeholder" aria-hidden="true"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$dashicon = $icon === 'image' ? 'dashicons-format-image' : 'dashicons-pdf';
		$title = $icon === 'image' ? 'Zugeordnetes Bild anzeigen' : 'Zugeordnetes PDF anzeigen';
		echo '<a href="' . \esc_url($url) . '" target="_blank" rel="noopener noreferrer" title="' . \esc_attr($title) . '" class="cmx-budget-doc-icon" aria-label="' . \esc_attr($title) . '"><span class="dashicons ' . \esc_attr($dashicon) . '" aria-hidden="true"></span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}, 10, 2);

\add_action('admin_head-edit.php', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== 'budget') {
		return;
	}

	echo '<style>
		.wp-list-table .column-cmx_budget_kategorien{width:180px}
		.wp-list-table .column-cmx_budget_einnahme{width:120px;white-space:nowrap}
		.wp-list-table .column-cmx_budget_ausgabe{width:120px;white-space:nowrap}
		.wp-list-table .column-cmx_budget_anteil{width:110px;white-space:nowrap}
		.wp-list-table .column-cmx_budget_anteil_betrag{width:130px;white-space:nowrap}
		.wp-list-table .column-cmx_budget_datei{width:46px;text-align:center}
		select[name="cmx_budget_kategorie"]{max-width:180px}
		select[name="cmx_budget_kontakt_filter"]{max-width:220px}
		.wp-list-table td.column-cmx_budget_datei{text-align:center;vertical-align:top}
		.cmx-budget-doc-icon{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;color:#111}
		.cmx-budget-doc-icon .dashicons{width:18px;height:18px;font-size:18px;line-height:18px}
		.cmx-budget-doc-placeholder{display:inline-block;width:18px;height:18px}
	</style>';
});
