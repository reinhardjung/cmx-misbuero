<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');




$post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
$tax     = 'beleg_kategorie'; // anpassen, falls Dein Tax-Name anders ist

$slugs = wp_get_post_terms($post_id, $tax, ['fields' => 'slugs']);
$beleg_type = (!empty($slugs) && !is_wp_error($slugs)) ? $slugs[0] : 'rechnung';

$getPost = [
	'post_id'    => $post_id,
	'beleg_nr'   => get_the_title($post_id),
	'beleg_type' => sanitize_file_name($beleg_type),
];
var_dump($getPost); exit;

exit;
require_once trailingslashit(CMX_PLUGIN_DIR) . 'vorlagen/' . $getPost['beleg_type'] . '.php';




global $wpdb;

$myPost = $_GET['post'];  // Holt nur die ID

$getPost = [
	'post_id'			=> $myPost,
	'beleg_nr'		=> get_the_title($myPost),
	'beleg_type'	=> (string) $wpdb->get_var( $wpdb->prepare("SELECT slug FROM {$wpdb->terms} WHERE term_id = %d",491) ),
];

var_dump($getPost); exit;

require_once CMX_PLUGIN_DIR . 'vorlagen/' .$getPost['beleg_type'] . '.php';


// ["_cmx_beleg_pdf_path"]=> array(1) { [0]=> string(73) "/Volumes/Daten/localwp/misbuero/app/public/dav/251104-071548_rechnung.pdf"
