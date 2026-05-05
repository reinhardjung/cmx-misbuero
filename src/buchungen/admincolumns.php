<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

\add_filter('manage_' . CMX_BUCHUNGEN_CPT . '_posts_columns', function (array $columns): array {
	$new = [];
	foreach ($columns as $key => $label) {
		if ($key === 'title') {
			$label = 'Name';
		}
		$new[$key] = $label;
		if ($key === 'title') {
			$new['cmx_buchung_start'] = 'Start';
			$new['cmx_buchung_kontakt'] = 'Kontakt';
			$new['cmx_buchung_leistung'] = 'Leistung';
			$new['cmx_buchung_status'] = 'Status';
			$new['cmx_buchung_resource'] = 'Ressource';
		}
	}
	unset($new['date']);
	return $new;
});

\add_action('manage_' . CMX_BUCHUNGEN_CPT . '_posts_custom_column', function (string $column, int $post_id): void {
	if ($column === 'cmx_buchung_start') {
		echo \esc_html(\trim((string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_START_DATE, true) . ' ' . (string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_START_TIME, true)));
		return;
	}
	if ($column === 'cmx_buchung_kontakt') {
		$id = (int) \get_post_meta($post_id, CMX_BUCHUNGEN_META_KONTAKT, true);
		echo $id > 0 ? '<a href="' . \esc_url((string) \get_edit_post_link($id)) . '">' . \esc_html((string) \get_the_title($id)) . '</a>' : '';
		return;
	}
	if ($column === 'cmx_buchung_leistung') {
		$id = (int) \get_post_meta($post_id, CMX_BUCHUNGEN_META_ARTIKEL, true);
		echo $id > 0 ? '<a href="' . \esc_url((string) \get_edit_post_link($id)) . '">' . \esc_html((string) \get_the_title($id)) . '</a>' : '';
		return;
	}
	if ($column === 'cmx_buchung_status') {
		$status = \sanitize_key((string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_STATUS, true));
		echo \esc_html(cmx_buchungen_status_options()[$status] ?? $status);
		return;
	}
	if ($column === 'cmx_buchung_resource') {
		$term_id = (int) \get_post_meta($post_id, CMX_BUCHUNGEN_META_RESSOURCE, true);
		$term = $term_id > 0 ? \get_term($term_id, CMX_BUCHUNGEN_TAX_RESSOURCE) : null;
		if ($term instanceof \WP_Term) {
			echo \esc_html($term->name);
		}
	}
}, 10, 2);

\add_filter('manage_edit-' . CMX_BUCHUNGEN_CPT . '_sortable_columns', function (array $columns): array {
	$columns['cmx_buchung_start'] = 'cmx_buchung_start';
	$columns['cmx_buchung_status'] = 'cmx_buchung_status';
	return $columns;
});

\add_action('pre_get_posts', function (\WP_Query $query): void {
	if (!\is_admin() || !$query->is_main_query() || (string) $query->get('post_type') !== CMX_BUCHUNGEN_CPT) {
		return;
	}
	$orderby = (string) $query->get('orderby');
	if ($orderby === 'cmx_buchung_start') {
		$query->set('meta_key', CMX_BUCHUNGEN_META_START_DATE);
		$query->set('orderby', 'meta_value');
	}
	if ($orderby === 'cmx_buchung_status') {
		$query->set('meta_key', CMX_BUCHUNGEN_META_STATUS);
		$query->set('orderby', 'meta_value');
	}
});
