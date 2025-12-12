<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/* --------------------------------------------------------------------------
 * Admin-Bar (Link auf Support-Tab)
 * -------------------------------------------------------------------------- */

add_action('admin_bar_menu', __NAMESPACE__ . '\\cmx65_adminbar', 999);
function cmx65_adminbar($wp_admin_bar) {

	$wp_admin_bar->remove_node('wp-logo');
	$wp_admin_bar->remove_node('new-content');
	$wp_admin_bar->remove_node('comments');

	echo '
		<style>
        #wpadminbar .cmx-nohover > .ab-item {
            cursor: default !important;
            pointer-events: none !important;
            background: none !important;
            color: #fff !important;
        }
        #wpadminbar .cmx-nohover > .ab-item:hover {
            background: none !important;
            color: yellow !important;
        }
    </style>';

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_name_id',
		'title' => '<span class="ab-label" style="cursor:default; pointer-events:none;">Mis Büro</span>',
		'href'  => false,
		'meta'  => [
			'title'  => '',
			'class' => 'cmx-nohover',
		],
	]);

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_handbuch_id',
		'title' => 'Online-Handbuch',
		'href'  => 'https://misbuero.ch/handbuch/',
		'meta'  => [
			'title'  => __('Mein Handbuch für Dich Online', 'textdomain'),
			'target' => '_blank',
		],
	]);

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_faq_id',
		'title' => 'FAQ',
		'href'  => 'https://misbuero.ch/faq/',
		'meta'  => [
			'title'  => __('Du hast allgemeines Fragen?', 'textdomain'),
			'target' => '_blank',
		],
	]);

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_videos_id',
		'title' => 'YouTube',
		'href'  => 'https://www.youtube.com/@MisBuero',
		'meta'  => [
			'title'  => __('Mehr über Mis Büro erfahren...', 'textdomain'),
			'target' => '_blank',
		],
	]);

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_roadmap',
		'title' => 'Roadmap',
		'href'  => 'https://misbuero.ch/roadmap/',
		'meta'  => [
			'title'  => __('Wie geht es weiter mit Mis Büro?', 'textdomain'),
			'target' => '_blank',
		],
	]);

	// Support-URL: wenn Konstante vorhanden, nutze sie, sonst Fallback
	if (defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')) {
		$support_url = add_query_arg(
			[
				'page' => CMX_SETTINGS_SLUG,
				'tab'  => 'support',
			],
			admin_url('admin.php')
		);
	} else {
		$support_url = admin_url('admin.php?page=cmx-einstellungen&tab=support');
	}

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_support_id',
		'title' => 'Support-Ticket',
		'href'  => esc_url($support_url),
		'meta'  => [
			'title' => __('Hier kannst ein Support Ticket erstellen', 'textdomain'),
		],
	]);
}
