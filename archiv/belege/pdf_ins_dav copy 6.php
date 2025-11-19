<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


global $wpdb;

$myPost = $_GET['post'];  // Holt nur die ID

var_dump($myPost);
// var_dump(get_post($_GET['post'])); // alle CMX Felder
var_dump('<br><br><br><br><br>');
var_dump(get_post($_GET['post']));

var_dump('<br><br><br><br><br>');
var_dump(get_post_meta($_GET['post']));

var_dump('<br><br><br><br><br>');
var_dump(get_post_meta($_GET['post']));


var_dump('<br><br><br><br><br>');
var_dump($wpdb->get_var( $wpdb->prepare("SELECT slug FROM {$wpdb->terms} WHERE term_id = %d",491) )); exit;



exit;


var_dump(wp_get_post_terms(get_post($_GET['post'])->ID, 'beleg_kategorie', ['fields' => 'all']));


// $_POST['cmx_beleg_kategorie']


$getPost = [
	'post_id'			=> $_GET['post'],
	'beleg_nr'		=> get_the_title($myPost),
	// 'beleg_type'	=> (string) $wpdb->get_var( $wpdb->prepare("SELECT slug FROM {$wpdb->terms} WHERE term_id = %d",$myPost['cmx_beleg_kategorie']) ) ?: '',
	'beleg_type'	=> (string) $wpdb->get_var( $wpdb->prepare("SELECT slug FROM {$wpdb->terms} WHERE term_id = %d",491) ),
];


var_dump('<br><br><br><br><br>');

var_dump($getPost); exit;

require_once CMX_PLUGIN_DIR . 'vorlagen/' .$getPost['beleg_type'] . '.php';


// ["_cmx_beleg_pdf_path"]=> array(1) { [0]=> string(73) "/Volumes/Daten/localwp/misbuero/app/public/dav/251104-071548_rechnung.pdf"
