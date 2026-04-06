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

\add_filter('manage_edit-budget_columns', function (array $columns): array {
	$new_columns = [
		'cmx_budget_einnahme'       => 'Einnahme',
		'cmx_budget_ausgabe'        => 'Ausgabe',
		'cmx_budget_anteil'         => 'Anteil',
		'cmx_budget_anteil_betrag'  => 'Anteil Betrag',
	];

	return cmx_budget_admin_insert_columns_after($columns, 'title', $new_columns);
}, 20);

\add_action('manage_budget_posts_custom_column', function (string $column, int $post_id): void {
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
	}
}, 10, 2);

\add_action('admin_head-edit.php', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== 'budget') {
		return;
	}

	echo '<style>
		.wp-list-table .column-cmx_budget_einnahme{width:120px;white-space:nowrap}
		.wp-list-table .column-cmx_budget_ausgabe{width:120px;white-space:nowrap}
		.wp-list-table .column-cmx_budget_anteil{width:110px;white-space:nowrap}
		.wp-list-table .column-cmx_budget_anteil_betrag{width:130px;white-space:nowrap}
	</style>';
});
