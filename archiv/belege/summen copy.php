<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * Sidebar-Metabox: Summe aller Belegpositionen
 */
\add_action('add_meta_boxes', function () {
	add_meta_box(
		'cmx_beleg_summe_box',
		__('Gesamtsumme', 'cmx'),
		__NAMESPACE__ . '\\cmx_beleg_summe_box_render',
		'belege',          // CPT: anpassen falls nötig
		'side',            // Position
		'high'             // Priorität
	);
});

/**
 * Ausgabe der Metabox
 */
function cmx_beleg_summe_box_render(\WP_Post $post): void {
	$positionen = get_post_meta($post->ID, '_cmx_beleg_positionen', true);

	$summe = 0.0;
	if (is_array($positionen)) {
		foreach ($positionen as $pos) {
			$menge = isset($pos['menge']) ? (float)$pos['menge'] : 0;
			$preis = isset($pos['preis']) ? (float)$pos['preis'] : 0;
			$summe += $menge * $preis;
		}
	}

	echo '<div style="font-size:14px; line-height:1.6; padding:6px 4px;">';
	if ($summe > 0) {
		echo '<strong>' . esc_html(number_format($summe, 2, ',', "'")) . ' CHF</strong>';
	} else {
		echo '<em>Keine Positionen erfasst</em>';
	}
	echo '</div>';
}
