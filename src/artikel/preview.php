<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


// Shortcode: [cmx_artikel_bild id="123" alt="" class=""] → gibt das lokale Artikelbild aus
\add_shortcode('cmx_artikel_bild', function ($atts = []) {
	$atts = shortcode_atts([
		'id'    => 0,
		'alt'   => '',
		'class' => '',
	], $atts, 'cmx_artikel_bild');

	$post_id = (int) ($atts['id'] ?: get_the_ID());
	if (!$post_id || get_post_type($post_id) !== 'artikel') return '';

	$url = (string) get_post_meta($post_id, '_cmx_local_image_artikel_url', true);
	if ($url === '') return '';

	$alt_text = trim($atts['alt']) !== '' ? $atts['alt'] : get_the_title($post_id);
	$extra    = trim(preg_replace('~[^a-z0-9_\\-\\s]+~i', ' ', (string) $atts['class']));
	$class    = trim('cmx-artikel-bild ' . $extra);

	return sprintf(
		'<img src="%s" alt="%s" class="%s" loading="lazy" width="500rem" />',
		esc_url($url),
		esc_attr($alt_text),
		esc_attr($class)
	);
});


// Shortcode: [cmx_artikel_preis id="123" currency="CHF"] → gibt den VK-Preis aus
\add_shortcode('cmx_artikel_preis', function ($atts = []) {
	$atts = shortcode_atts([
		'id'       => 0,
		'currency' => 'CHF',
	], $atts, 'cmx_artikel_preis');

	$post_id = (int) ($atts['id'] ?: get_the_ID());
	if (!$post_id || get_post_type($post_id) !== 'artikel') return '';

	$vk_meta_key = \defined(__NAMESPACE__.'\\CMX_ARTIKEL_META_VK') ? CMX_ARTIKEL_META_VK : '_cmx_artikel_vk';
	$raw         = \get_post_meta($post_id, $vk_meta_key, true);
	$value       = (float) str_replace(',', '.', (string) $raw);
	if ($value <= 0) return '';

	$currency = trim((string) $atts['currency']);
	$number   = number_format($value, 2, ',', "'");

	return esc_html(trim($number . ' ' . $currency));
});


// Shortcode: [cmx_artikel_einheit id="123"] → zeigt die erste Einheit aus der Taxonomie
\add_shortcode('cmx_artikel_einheit', function ($atts = []) {
	$atts = shortcode_atts([
		'id' => 0,
	], $atts, 'cmx_artikel_einheit');

	$post_id = (int) ($atts['id'] ?: get_the_ID());
	if (!$post_id || get_post_type($post_id) !== 'artikel') return '';

	if (!\defined(__NAMESPACE__.'\\TAX_ARTIKEL_EINHEITEN')) {
		return '';
	}

	$terms = \wp_get_post_terms($post_id, TAX_ARTIKEL_EINHEITEN);
	if (\is_wp_error($terms) || empty($terms) || empty($terms[0]->name)) {
		return '';
	}

	return esc_html((string) $terms[0]->name);
});

// CMX_TAX_BELEGE_KATEGORIEN?
// cmx_show_consts(); exit;
