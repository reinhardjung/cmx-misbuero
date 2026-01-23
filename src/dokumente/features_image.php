<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Dokumente sollen das gleiche lokale Beitragsbild-Handling wie die Artikel erhalten.
 * Wir hängen den CPT an die bestehende Infrastruktur aus includes/featured_images.php an.
 */
\add_filter('cmx_local_image_cpt_map', function(array $map): array {
	return $map;
});
