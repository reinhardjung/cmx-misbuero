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

	/* Papierkorb-Icon statt Text in Listen-Aktionen (alle CPT) */
	/* Nur im Edit-Formular (post.php) – Papierkorb-Icon statt Text im Delete-Button */
	body.post-php #delete-action .submitdelete {
		position: relative;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 36px;
		height: 36px;
		text-indent: -9999px;
		padding: 0;
		border: none;
		background: transparent;
		box-shadow: none;
	}
	body.post-php #delete-action .submitdelete::before {
		content: "\f182";
		font-family: "dashicons";
		font-size: 18px;
		color: #d63638; /* klassisches WP-Rot für Papierkorb */
		text-indent: 0;
		display: inline-block;
		vertical-align: middle;
	}
	body.post-php #delete-action .submitdelete:hover::before,
	body.post-php #delete-action .submitdelete:focus::before {
		color: #b42527;
	}


	.notice-success { border-left: 4px solid var(--mb-success); }
	.notice-error { border-left: 4px solid var(--mb-error); }
	</style>
	<?php if (!is_network_admin() && isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'post.php') : ?>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('#delete-action .submitdelete').forEach(function(el){
			var text = (el.textContent || '').trim();
			if (text && !el.getAttribute('title')) {
				el.setAttribute('title', text);
			}
		});
	});
	</script>
	<?php endif; ?>
	<?php
}

// Globale Fallback-Styles: Papierkorb-Icon statt Text im Edit-Modus für alle CPTs.
add_action('admin_head', function () {
	if (is_network_admin()) return;
	?>
	<style id="cmx-trash-icon-global">
	/* Classic + Block Editor: nur im Edit-Modus (post.php) alle CPTs */
	body.post-php .submitdelete,
	body.post-php .editor-post-trash .components-button {
		position: relative;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 36px;
		height: 36px;
		text-indent: -9999px;
		padding: 0;
		border: none;
		background: transparent;
		box-shadow: none;
		color: transparent !important;
	}
	body.post-php .submitdelete::before,
	body.post-php .editor-post-trash .components-button::before {
		content: "\f182";
		font-family: "dashicons";
		font-size: 18px;
		color: #d63638;
		text-indent: 0;
		display: inline-block;
		vertical-align: middle;
	}
	body.post-php .submitdelete:hover::before,
	body.post-php .submitdelete:focus::before,
	body.post-php .editor-post-trash .components-button:hover::before,
	body.post-php .editor-post-trash .components-button:focus::before {
		color: #b42527;
	}

	/* Duplizieren-Icon im Custom-Aktions-Block */
	body.post-php .cmx-dup-icon {
		position: relative;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 36px;
		height: 36px;
		text-indent: -9999px;
		padding: 0;
		border: none;
		background: transparent;
		box-shadow: none;
		color: transparent !important;
	}
	body.post-php .cmx-dup-icon::before {
		content: "\f316"; /* dashicons-admin-page (Duplicate) */
		font-family: "dashicons";
		font-size: 18px;
		color: #2271b1; /* WP-primärblau */
		text-indent: 0;
		display: inline-block;
		vertical-align: middle;
	}
	body.post-php .cmx-dup-icon:hover::before,
	body.post-php .cmx-dup-icon:focus::before {
		color: #1a5a8c;
	}
	</style>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		if (document.body && !document.body.classList.contains('post-php')) return;
		document.querySelectorAll('#delete-action .submitdelete, .editor-post-trash .components-button, .submitdelete').forEach(function(el){
			var text = (el.textContent || '').trim();
			if (text && !el.getAttribute('title')) {
				el.setAttribute('title', text);
			}
		});
	});
	</script>
	<?php
});
