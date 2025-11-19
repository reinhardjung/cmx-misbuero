<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


global $wpdb;

$myPost = $_GET['post'];  // Holt nur die ID

$getPost = [
	'post_id'			=> $myPost,
	'beleg_nr'		=> get_the_title($myPost),
	// 'beleg_type'	=> (string) $wpdb->get_var( $wpdb->prepare("SELECT slug FROM {$wpdb->terms} WHERE term_id = %d",$myPost['cmx_beleg_kategorie']) ) ?: '',
	'beleg_type'	=> (string) $wpdb->get_var( $wpdb->prepare("SELECT slug FROM {$wpdb->terms} WHERE term_id = %d",491) ),
];


// var_dump('<br><br><br><br><br>');

var_dump($getPost); exit;

require_once CMX_PLUGIN_DIR . 'vorlagen/' .$getPost['beleg_type'] . '.php';


// ["_cmx_beleg_pdf_path"]=> array(1) { [0]=> string(73) "/Volumes/Daten/localwp/misbuero/app/public/dav/251104-071548_rechnung.pdf"
