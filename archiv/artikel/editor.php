<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') or die('Oxytocin!');


/**
 * Editor unter die Metabox "Stammdaten" verschieben (CPT: artikel, Classic Editor)
 * Nutzt eine eigene Callback-Funktion, die den Content-Editor rendert.
 */

/** 1) Für CPT 'artikel' den Block-Editor deaktivieren (Classic-Layout erzwingen) */
// \add_filter('use_block_editor_for_post_type', function ($use_block_editor, $post_type) {
// 	return ($post_type === 'artikel') ? false : $use_block_editor;
// }, 10, 2);

/** 2) Original-Editor entfernen und eigene Editor-Box unten hinzufügen */
\add_action('do_meta_boxes', function () {
	$post_type = 'artikel';

	// Sicherstellen, dass Classic aktiv ist
	// if (function_exists('use_block_editor_for_post_type') && \use_block_editor_for_post_type($post_type)) {
	// 	return;
	// }

	// Originale Editor-Box entfernen
	// \remove_meta_box('postdivrich', $post_type, 'normal');

	// Eigene Editor-Box mit niedrigerer Priorität (unter "Stammdaten")
// 	\add_meta_box(
// 		'cmx_postdivrich',                    // eigene ID, um Konflikte zu vermeiden
// 		__('Beschreibung', 'default'),
// 		__NAMESPACE__ . '\\cmx_content_editor_box',
// 		$post_type,
// 		'normal',
// 		'low'
// 	);
// }, 20);

/** 3) Eigene Callback-Funktion: rendert den Standard-Content-Editor */
// function cmx_content_editor_box(\WP_Post $post): void {
// 	// Gleiche Feldnamen/IDs wie WordPress-Standard, damit der Inhalt normal gespeichert wird.
// 	\wp_editor(
// 		$post->post_content,
// 		'content', // Editor-ID (Standard)
// 		[
// 			'textarea_name' => 'content', // wichtig: Standard-Feldname
// 			'textarea_rows' => 12,
// 			'media_buttons' => true,
// 			'editor_height' => 260,
// 		]
// 	);
// }
