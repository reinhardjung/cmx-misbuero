<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Plugin Name: Mis Büro – Admin Skin
 * Description: Mis Büro Farbdesign für WordPress-Administratoren – Menü-Highlight in Rot/Gelb.
 * Version:     1.2.0
 * Author:      CLOUD Meister
 */


add_action('admin_init', __NAMESPACE__ . '\\register_scheme');
function register_scheme(): void {
	$slug = 'misbuero_admin_red';
	wp_admin_css_color($slug,'Mis Büro',false,['#C9362C', '#A42C24', '#3D3D3D', '#F7F7F7']);
}


add_action('admin_head', __NAMESPACE__ . '\\inject_styles');
function inject_styles(): void {
	$user_id = get_current_user_id();
	if (!$user_id) return;
	if (get_user_option('admin_color', $user_id) !== 'misbuero_admin_red') return;
	?>
	<style id="misbuero-admin-skin">
	body.admin-color-misbuero_admin_red {
		--mb-primary: #A42C24;   /* Rot */
		--mb-primary-dark: #A42C24;
		--mb-yellow: #FFEB3B;    /* Gelb für Schrift */
		--mb-bg-light: #F7F7F7;
		--mb-border: #E0E0E0;
		--mb-text: #3D3D3D;
		--mb-muted: #BFBFBF;
		--mb-success: #4BB572;
		--mb-error: #e53836;

		--wp-admin-theme-color: var(--mb-primary);
		--wp-admin-theme-color-darker-10: var(--mb-primary-dark);
		--wp-admin-theme-color-darker-20: #7f1f1a;
		--wp-admin-theme-color-text: #ffffff;
		--wp-admin-border-color: var(--mb-border);
	}


	/* Adminbar bleibt Rot/Weiss */
	#wpadminbar { background: var(--mb-primary); }
	#wpadminbar .ab-item,
	#wpadminbar a.ab-item { color:#fff; }
	#wpadminbar .ab-item:hover,
	#wpadminbar .ab-item:focus { background: var(--mb-primary-dark); color:#fff; }

	/* === NUR aktive & hovernde Menüpunkte === */
	#adminmenu .wp-has-current-submenu > a.menu-top,
	#adminmenu .current a.menu-top,
	#adminmenu .wp-menu-open > a.menu-top,
	#adminmenu a:hover,
	#adminmenu .wp-submenu a:hover {
		background: var(--mb-primary) !important;
		color: var(--mb-yellow) !important;
	}


	/* Menü-Icons (Dashicons) bei Hover/Aktiv gelb */
	#adminmenu li.menu-top:hover .wp-menu-image:before,
	#adminmenu li.wp-has-current-submenu .wp-menu-image:before,
	#adminmenu li.current .wp-menu-image:before {
		color: var(--mb-yellow) !important;
	}


	/* Submenu Hover */
	#adminmenu .wp-submenu a:hover {
		background: var(--mb-primary-dark) !important;
		color: var(--mb-yellow) !important;
	}


	/* Restliches Menü neutral (kein roter Hintergrund mehr) */
	#adminmenu,
	#adminmenu .wp-submenu,
	#adminmenu .wp-submenu.sub-open {
		background: #2E2E2E !important;
	}


	#adminmenu a,
	#adminmenu div.wp-menu-name,
	#adminmenu .wp-submenu a {
		color: #e0e0e0 !important;
	}


	/* Zähler/Badges bei aktiven Menüpunkten */
	#adminmenu li.current .update-plugins,
	#adminmenu li.wp-has-current-submenu .update-plugins {
		background: var(--mb-primary-dark) !important;
		color: var(--mb-yellow) !important;
	}


	/* Restliche Bereiche (Buttons, Content etc.) unverändert */
	a { color: var(--mb-primary); }
	a:hover { color: var(--mb-primary-dark); }
	.wp-core-ui .button-primary {
		background: var(--mb-primary);
		border-color: var(--mb-primary-dark);
		color: #fff;
	}

	.wp-core-ui .button-primary:hover,
	.wp-core-ui .button-primary:focus {
		background: var(--mb-primary-dark);
		border-color: var(--mb-primary-dark);
	}


	.notice-success { border-left: 4px solid var(--mb-success); }
	.notice-error { border-left: 4px solid var(--mb-error); }
	</style>
	<?php
}
