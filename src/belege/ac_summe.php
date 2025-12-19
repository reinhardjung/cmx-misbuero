<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!defined(__NAMESPACE__.'\\CMX_PT_BELEGE')) {
	define(__NAMESPACE__.'\\CMX_PT_BELEGE', 'belege');
}

// Spalte anhängen
add_filter('manage_edit-' . CMX_PT_BELEGE . '_columns', function(array $cols): array {
	$cols['beleg_summe'] = 'Summe';
	return $cols;
}, 30);

// Summe kalkulieren und ausgeben
add_action('manage_' . CMX_PT_BELEGE . '_posts_custom_column', function(string $column, int $post_id) {
	if ($column !== 'beleg_summe') return;

	if (!function_exists(__NAMESPACE__.'\\cmxbu_get_beleg_positionen_calc')) {
		echo '';
		return;
	}

	$calc = cmxbu_get_beleg_positionen_calc($post_id);
	$total = isset($calc['total']) ? (float)$calc['total'] : 0.0;
	echo esc_html(number_format_i18n($total, 2));
}, 10, 2);
