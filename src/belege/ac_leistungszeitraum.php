<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_PT_BELEGE')) {
	\define(__NAMESPACE__ . '\\CMX_PT_BELEGE', 'belege');
}
if (!\defined(__NAMESPACE__ . '\\CMX_BELEG_META_LEISTUNGSMONAT')) {
	\define(__NAMESPACE__ . '\\CMX_BELEG_META_LEISTUNGSMONAT', '_cmx_beleg_leistungsmonat');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_leistungsmonat_labels')) {
	function cmx_beleg_leistungsmonat_labels(): array {
		return [
			'01' => 'Januar',
			'02' => 'Februar',
			'03' => 'März',
			'04' => 'April',
			'05' => 'Mai',
			'06' => 'Juni',
			'07' => 'Juli',
			'08' => 'August',
			'09' => 'September',
			'10' => 'Oktober',
			'11' => 'November',
			'12' => 'Dezember',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_leistungsmonat_value')) {
	function cmx_beleg_leistungsmonat_value(string $raw): string {
		$raw = \trim($raw);
		return \preg_match('/^(0[1-9]|1[0-2])$/', $raw) ? $raw : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_leistungsmonat_label')) {
	function cmx_beleg_leistungsmonat_label(string $raw): string {
		$month = cmx_beleg_leistungsmonat_value($raw);
		if ($month === '') {
			return '';
		}
		$labels = cmx_beleg_leistungsmonat_labels();
		return (string) ($labels[$month] ?? $month);
	}
}

$add_col = static function(array $columns): array {
	$key = 'cmx_beleg_leistungszeitraum';
	if (isset($columns[$key])) {
		return $columns;
	}

	$new = [];
	$inserted = false;
	foreach ($columns as $col_key => $label) {
		$new[$col_key] = $label;
		if ($col_key === 'cmx_beleg_projekt') {
			$new[$key] = 'Leistung';
			$inserted = true;
		}
	}
	if (!$inserted) {
		$new[$key] = 'Leistung';
	}
	return $new;
};
\add_filter('manage_edit-' . CMX_PT_BELEGE . '_columns', $add_col, 60);
\add_filter('manage_' . CMX_PT_BELEGE . '_posts_columns', $add_col, 60);

\add_action('manage_' . CMX_PT_BELEGE . '_posts_custom_column', static function(string $column, int $post_id): void {
	if ($column !== 'cmx_beleg_leistungszeitraum') {
		return;
	}

	$month = cmx_beleg_leistungsmonat_value((string) \get_post_meta($post_id, CMX_BELEG_META_LEISTUNGSMONAT, true));
	if ($month === '') {
		echo '';
		return;
	}

	$label = cmx_beleg_leistungsmonat_label($month);
	$url = \add_query_arg(
		[
			'post_type'          => CMX_PT_BELEGE,
			'cmx_leistungsmonat' => $month,
		],
		\admin_url('edit.php')
	);
	echo '<a href="' . \esc_url($url) . '">' . \esc_html($label) . '</a>';
}, 10, 2);

\add_filter('manage_edit-' . CMX_PT_BELEGE . '_sortable_columns', static function(array $columns): array {
	$columns['cmx_beleg_leistungszeitraum'] = 'cmx_beleg_leistungszeitraum';
	return $columns;
}, 20);

\add_filter('query_vars', static function(array $vars): array {
	if (!\in_array('cmx_leistungsmonat', $vars, true)) {
		$vars[] = 'cmx_leistungsmonat';
	}
	return $vars;
});

\add_action('restrict_manage_posts', static function($post_type): void {
	if ($post_type !== CMX_PT_BELEGE) {
		return;
	}
	if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_column_is_visible') && !cmx_beleg_admin_column_is_visible('cmx_beleg_leistungszeitraum')) {
		return;
	}

	$selected = isset($_GET['cmx_leistungsmonat']) ? cmx_beleg_leistungsmonat_value((string) \wp_unslash($_GET['cmx_leistungsmonat'])) : '';
	$labels = cmx_beleg_leistungsmonat_labels();

	echo '<select name="cmx_leistungsmonat" id="cmx_leistungsmonat" class="postform">';
	echo '<option value="">' . \esc_html__('Alle Leistungszeiträume', 'cmx') . '</option>';
	foreach ($labels as $value => $label) {
		echo '<option value="' . \esc_attr($value) . '" ' . \selected($selected, $value, false) . '>' . \esc_html($label) . '</option>';
	}
	echo '</select>';
}, 12, 1);

\add_action('pre_get_posts', static function(\WP_Query $q): void {
	if (!\is_admin() || !$q->is_main_query()) {
		return;
	}
	$post_type = $q->get('post_type');
	if (
		($post_type !== CMX_PT_BELEGE)
		&& (!\is_array($post_type) || !\in_array(CMX_PT_BELEGE, $post_type, true))
	) {
		return;
	}
	if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_column_is_visible') && !cmx_beleg_admin_column_is_visible('cmx_beleg_leistungszeitraum')) {
		return;
	}

	$month = (string) $q->get('cmx_leistungsmonat');
	if ($month === '' && isset($_GET['cmx_leistungsmonat'])) {
		$month = (string) \wp_unslash($_GET['cmx_leistungsmonat']);
	}
	$month = cmx_beleg_leistungsmonat_value($month);
	$q->set('cmx_leistungsmonat', $month);

	if ($month !== '') {
		$meta_query = $q->get('meta_query');
		if (!\is_array($meta_query)) {
			$meta_query = [];
		}
		if (!isset($meta_query['relation'])) {
			$meta_query['relation'] = 'AND';
		}
		$meta_query[] = [
			'key'     => CMX_BELEG_META_LEISTUNGSMONAT,
			'value'   => $month,
			'compare' => '=',
		];
		$q->set('meta_query', $meta_query);
	}

	if ((string) $q->get('orderby') === 'cmx_beleg_leistungszeitraum') {
		$q->set('meta_key', CMX_BELEG_META_LEISTUNGSMONAT);
		$q->set('orderby', 'meta_value');
	}
}, 55);
