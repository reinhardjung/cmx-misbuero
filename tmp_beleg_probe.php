<?php
require __DIR__ . '/../../../wp-load.php';

$ids = get_posts([
	'post_type' => 'belege',
	'post_status' => ['publish', 'private'],
	'posts_per_page' => 8,
	'orderby' => 'ID',
	'order' => 'DESC',
	'fields' => 'ids',
]);

foreach ($ids as $id) {
	$id = (int) $id;
	echo "ID={$id}\n";
	echo 'title=' . get_the_title($id) . "\n";
	echo 'paid=' . get_post_meta($id, '_cmx_beleg_bezahlt_am', true) . "\n";
	echo 'status=' . get_post_meta($id, '_cmx_beleg_status', true) . "\n";
	echo 'richtung=' . get_post_meta($id, '_cmx_beleg_richtung', true) . "\n";
	echo 'kontakt=' . get_post_meta($id, '_cmx_beleg_kontakt_id', true) . "\n";
	echo 'freq=' . get_post_meta($id, '_cmx_abo_frequency', true) . "\n";
	echo 'src=' . get_post_meta($id, '_cmx_abo_source_post', true) . "\n";
	echo 'run=' . get_post_meta($id, '_cmx_abo_run_key', true) . "\n";
	$terms = wp_get_post_terms($id, 'beleg_kategorie', ['fields' => 'slugs']);
	echo 'terms=' . (is_wp_error($terms) ? 'ERR' : implode(',', $terms)) . "\n";
	echo 'faellig=' . get_post_meta($id, '_cmx_beleg_faellig', true) . "\n";
	echo 'belegdatum=' . get_post_meta($id, '_cmx_beleg_rng_datum', true) . "\n";
	echo 'anzahlungen=' . wp_json_encode(get_post_meta($id, '_cmx_beleg_anzahlungen', true)) . "\n";
	$all_meta = get_post_meta($id);
	$abo_meta = [];
	foreach ($all_meta as $meta_key => $meta_values) {
		if (strpos((string) $meta_key, '_cmx_abo_') === 0) {
			$abo_meta[$meta_key] = $meta_values;
		}
	}
	echo 'abo_meta=' . wp_json_encode($abo_meta) . "\n";
	echo "\n";
}
