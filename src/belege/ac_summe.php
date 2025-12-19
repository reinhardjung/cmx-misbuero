<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!defined(__NAMESPACE__.'\\CMX_PT_BELEGE')) {
	define(__NAMESPACE__.'\\CMX_PT_BELEGE', 'belege');
}

// Spalte anhängen
add_filter('manage_edit-' . CMX_PT_BELEGE . '_columns', function(array $cols): array {
	$cols['beleg_summe'] = 'Summe';
	return $cols;
}, 30);

// Sortierbar machen
add_filter('manage_edit-' . CMX_PT_BELEGE . '_sortable_columns', function(array $cols): array {
	$cols['beleg_summe'] = 'beleg_summe';
	return $cols;
});

// Sortierung abfangen: sortiere nach berechnetem Total (Meta nicht vorhanden, daher SQL-Sortierung überschrieben)
add_action('pre_get_posts', function(\WP_Query $q) {
	if (!\is_admin() || !$q->is_main_query()) return;
	$post_types = (array) $q->get('post_type');
	if (!\in_array(CMX_PT_BELEGE, $post_types, true)) return;
	if ($q->get('orderby') !== 'beleg_summe') return;

	// Wir können nicht direkt SQL-sortieren, da Total berechnet wird. Also auf PHP-Sort fallback: WP_Query Ergebnisse holen und per usort sortieren.
	$q->set('orderby', 'ID'); // Dummy, damit Query läuft
	$q->set('order', $q->get('order') ?: 'ASC');
	add_filter('the_posts', function(array $posts) use ($q) {
		if (!function_exists(__NAMESPACE__.'\\cmxbu_get_beleg_positionen_calc')) return $posts;

		$want_desc = strtoupper($q->get('order')) === 'DESC';
		usort($posts, function($a, $b) use ($want_desc) {
			$calcA = cmxbu_get_beleg_positionen_calc($a->ID);
			$calcB = cmxbu_get_beleg_positionen_calc($b->ID);
			$totA  = isset($calcA['total']) ? (float)$calcA['total'] : 0.0;
			$totB  = isset($calcB['total']) ? (float)$calcB['total'] : 0.0;
			if ($totA === $totB) return 0;
			return ($totA < $totB) ? ($want_desc ? 1 : -1) : ($want_desc ? -1 : 1);
		});
		return $posts;
	}, 10, 1);
});

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
