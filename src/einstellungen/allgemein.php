<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * Tab: Allgemein
 */
add_action('admin_init', __NAMESPACE__ . '\\cmx_register_general_tab');
function cmx_register_general_tab(): void {
	add_settings_section(
		'cmx_sec_general',
		__('Allgemein', 'default'),
		'__return_false',
		'cmx_tab_general'
	);

	add_settings_field(
		'beleg_logo_url',
		__('Link zum Logo', 'default'),
		__NAMESPACE__ . '\\cmx_field_url',
		'cmx_tab_general',
		'cmx_sec_general',
		[
			'key'   => 'beleg_logo_url',
			'label' => __('f&uuml;r den Beleg', 'default'),
		]
	);
}
