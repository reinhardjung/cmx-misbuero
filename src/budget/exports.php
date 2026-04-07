<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

if (!\defined(__NAMESPACE__ . '\\CMX_PT_BUDGET')) {
	\define(__NAMESPACE__ . '\\CMX_PT_BUDGET', 'budget');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_export_insert_view_after')) {
	function cmx_budget_export_insert_view_after(array $views, string $after_key, string $new_key, string $html): array {
		$out = [];
		$inserted = false;

		foreach ($views as $key => $value) {
			$out[$key] = $value;
			if ($key === $after_key) {
				$out[$new_key] = $html;
				$inserted = true;
			}
		}

		if (!$inserted) {
			$out[$new_key] = $html;
		}

		return $out;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_export_taxonomy')) {
	function cmx_budget_export_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_budget_admin_taxonomy')) {
			return (string) cmx_budget_admin_taxonomy();
		}
		if (\defined(__NAMESPACE__ . '\\CMX_TAX_BUDGET')) {
			return (string) \constant(__NAMESPACE__ . '\\CMX_TAX_BUDGET');
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_tax_key')) {
			return (string) cmx_tax_key('budget', 'Kategorien');
		}
		return 'budget_kategorien';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_export_contact_label')) {
	function cmx_budget_export_contact_label(int $post_id): string {
		$contact_id = \defined(__NAMESPACE__ . '\\CMX_BUDGET_KONTAKT_META')
			? (int) \get_post_meta($post_id, CMX_BUDGET_KONTAKT_META, true)
			: 0;
		if ($contact_id <= 0) {
			return '';
		}

		return \trim((string) \get_the_title($contact_id));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_export_collect_ids')) {
	function cmx_budget_export_collect_ids(): array {
		$selected_ids = isset($_REQUEST['post']) ? \array_filter(\array_map('intval', (array) $_REQUEST['post'])) : [];
		$query_args = [
			'post_type'              => CMX_PT_BUDGET,
			'post_status'            => ['publish', 'future', 'draft', 'pending', 'private'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];

		if ($selected_ids !== []) {
			$query_args['post__in'] = $selected_ids;
			$query_args['orderby'] = 'post__in';
			$query_args['post_status'] = 'any';
		} else {
			$post_status = isset($_REQUEST['post_status']) ? \sanitize_key((string) $_REQUEST['post_status']) : '';
			if ($post_status !== '' && $post_status !== 'all') {
				$query_args['post_status'] = $post_status;
			}

			$search = isset($_REQUEST['s']) ? \sanitize_text_field((string) \wp_unslash($_REQUEST['s'])) : '';
			if ($search !== '') {
				$query_args['s'] = $search;
			}

			$term_id = isset($_REQUEST['cmx_budget_kategorie']) ? (int) $_REQUEST['cmx_budget_kategorie'] : 0;
			$taxonomy = cmx_budget_export_taxonomy();
			if ($term_id > 0 && $taxonomy !== '' && \taxonomy_exists($taxonomy)) {
				$query_args['tax_query'] = [[
					'taxonomy'         => $taxonomy,
					'field'            => 'term_id',
					'terms'            => [$term_id],
					'include_children' => true,
				]];
			}
		}

		$query = new \WP_Query($query_args);
		return \array_values(\array_filter(\array_map('intval', (array) $query->posts)));
	}
}

\add_filter('views_edit-' . CMX_PT_BUDGET, function (array $views): array {
	if (!\current_user_can('edit_posts')) {
		return $views;
	}

	$args = $_GET ?? [];
	unset(
		$args['paged'],
		$args['action'],
		$args['action2'],
		$args['_wpnonce'],
		$args['_wp_http_referer'],
		$args['orderby'],
		$args['order']
	);
	$args['action'] = 'cmx_export_budget_list';

	$url = \wp_nonce_url(\add_query_arg($args, \admin_url('admin-post.php')), 'cmx_export_budget_list');
	$link = '<a href="' . \esc_url($url) . '">exportieren</a>';

	return cmx_budget_export_insert_view_after($views, 'all', 'cmx_export_budget_list', $link);
}, 20);

\add_action('admin_post_cmx_export_budget_list', function (): void {
	if (!\current_user_can('edit_posts')) {
		\wp_die('Keine Berechtigung.');
	}
	if (!\wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'cmx_export_budget_list')) {
		\wp_die('Ungültige Anfrage.');
	}

	$ids = cmx_budget_export_collect_ids();
	$taxonomy = cmx_budget_export_taxonomy();
	$stamp = \function_exists(__NAMESPACE__ . '\\cmx_export_now_stamp')
		? (string) cmx_export_now_stamp()
		: (\function_exists('\\wp_date') ? (string) \wp_date('Ymd-His') : (string) \gmdate('Ymd-His'));
	$filename = 'budget-export-' . $stamp . '.csv';

	\header('Content-Type: text/csv; charset=utf-8');
	\header('Content-Disposition: attachment; filename=' . $filename);

	$output = \fopen('php://output', 'w');
	if (!$output) {
		exit;
	}

	\fputs($output, "\xEF\xBB\xBF");
	\fputcsv($output, [
		'post_title',
		'post_status',
		'post_date',
		'post_content',
		'kategorien',
		'kontakt',
		'kosten_typ',
		'betrag',
		'anteil',
		'anteil_betrag',
		'zahlbar_pro',
	], ';', '"', '\\');

	foreach ($ids as $post_id) {
		$terms = $taxonomy !== '' ? \get_the_terms($post_id, $taxonomy) : [];
		$categories = [];
		if (\is_array($terms) && !\is_wp_error($terms)) {
			foreach ($terms as $term) {
				if ($term instanceof \WP_Term) {
					$name = \trim((string) $term->name);
					if ($name !== '') {
						$categories[] = $name;
					}
				}
			}
		}

		\fputcsv($output, [
			(string) \get_the_title($post_id),
			(string) \get_post_status($post_id),
			(string) \get_post_field('post_date', $post_id),
			(string) \get_post_field('post_content', $post_id),
			\implode('|', \array_unique($categories)),
			cmx_budget_export_contact_label($post_id),
			(string) \get_post_meta($post_id, CMX_BUDGET_KOSTEN_TYP_META, true),
			(string) \get_post_meta($post_id, CMX_BUDGET_KOSTEN_BETRAG_META, true),
			(string) \get_post_meta($post_id, CMX_BUDGET_KOSTEN_ANTEIL_META, true),
			(string) \get_post_meta($post_id, CMX_BUDGET_KOSTEN_ANTEIL_BETRAG_META, true),
			(string) \get_post_meta($post_id, CMX_BUDGET_KOSTEN_ZAHLBAR_PRO_META, true),
		], ';', '"', '\\');
	}

	\fclose($output);
	exit;
});
