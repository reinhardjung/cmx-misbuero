<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * Sidebar-Metabox: Summe aller Belegpositionen
 */
\add_action('add_meta_boxes', function () {
	add_meta_box(
		'cmx_beleg_summe_box',
		__('Gesamtsumme', 'cmx'),
		__NAMESPACE__ . '\\cmx_beleg_summe_box_render',
		'belege',
		'side',
		'high'
	);
});

/**
 * Berechnung und Anzeige der Gesamtsumme
 */
function cmx_beleg_summe_box_render(\WP_Post $post): void {
	$raw = get_post_meta($post->ID, '_cmx_beleg_positionen', true);
	if (!$raw) {
		echo '<div style="padding:6px;">Keine Positionen erfasst</div>';
		return;
	}

	// Alle möglichen Formate abfangen
	$positionen = maybe_unserialize($raw);
	if (is_string($positionen)) {
		$decoded = json_decode($positionen, true);
		if (json_last_error() === JSON_ERROR_NONE) {
			$positionen = $decoded;
		}
	}

	if (!is_array($positionen)) {
		echo '<div style="padding:6px;">Keine gültigen Positionen gefunden</div>';
		return;
	}

	$summe = 0.0;
	foreach ($positionen as $pos) {
		$menge = isset($pos['menge']) ? (float)$pos['menge'] : 0;
		$preis = isset($pos['preis']) ? (float)$pos['preis'] : 0;
		$rabatt = isset($pos['rabatt']) ? (float)$pos['rabatt'] : 0;

		$gesamt = ($menge * $preis) - $rabatt;
		$summe += $gesamt;
	}

	echo '<div style="font-size:14px; line-height:1.6; padding:6px 4px;">';
	echo '<strong>' . esc_html(number_format($summe, 2, ',', "'")) . ' CHF</strong>';
	echo '</div>';
}
