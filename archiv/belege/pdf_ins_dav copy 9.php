<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


$post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;

$slugs = wp_get_post_terms($post_id, 'beleg_kategorie', ['fields' => 'slugs']);
$beleg_type = (!empty($slugs) && !is_wp_error($slugs)) ? $slugs[0] : 'rechnung';

$getPost = [
	'post_id'			=> $post_id,
	'beleg_nr'		=> get_the_title($post_id),
	'beleg_type'	=> $beleg_type,
	'dateiname'		=> sanitize_file_name($beleg_type),
];

require_once CMX_PLUGIN_DIR . 'vorlagen/' .$getPost['beleg_type'] . '.php';
