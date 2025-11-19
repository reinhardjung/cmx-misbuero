<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

use Dompdf\Dompdf;
use Dompdf\Options;

require_once CMX_PLUGIN_DIR .'vendor/autoload.php';

$post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;


$slugs = wp_get_post_terms($post_id, 'beleg_kategorie', ['fields' => 'slugs']);
$beleg_type = (!empty($slugs) && !is_wp_error($slugs)) ? $slugs[0] : 'rechnung';

$getPost = [
	'post_id'			=> $post_id,
	'beleg_nr'		=> get_the_title($post_id),
	'beleg_type'	=> $beleg_type,
	'dateiname'		=> get_the_title($post_id) .'_' .$beleg_type,
	// 'betreff'			=> get_post_meta( $_POST['post_ID'], array_keys( get_post_meta( $_POST['post_ID']) )[4], true ),
];

// var_dump(get_post_meta( $_POST['post_ID'], array_keys( get_post_meta( $_POST['post_ID']) )[4], true )); exit;




var_dump(get_post_meta( $post_id, array_keys( get_post_meta( $post_id) )[4], true )); exit;

// var_dump($getPost);
// var_dump(get_post_meta($post_id, '_cmx_beleg_beschreibung', true)); exit;
// exit;

require_once CMX_PLUGIN_DIR . 'vorlagen/' .$getPost['beleg_type'] . '.php';
