<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * Tab: Kontakte
 * Aktuell im Original-Code keine Felder definiert – hier kannst Du später
 * pro Subtab (ms / oo / ic) eigene Sections und Fields ergänzen.
 */
add_action('admin_init', __NAMESPACE__ . '\\cmx_register_kontakte_tab');
function cmx_register_kontakte_tab(): void {
	// Beispiel-Section für Microsoft (noch ohne Felder):
	/*
	add_settings_section(
		'cmx_sec_kontakte_ms',
		__('Microsoft', 'default'),
		'__return_false',
		'cmx_tab_kontakte__ms'
	);
	*/
}
