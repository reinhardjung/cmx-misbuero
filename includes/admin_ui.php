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

add_filter('admin_footer_text', __NAMESPACE__ . '\\cmx_admin_footer_text');
function cmx_admin_footer_text(string $text): string {
	return 'Managend by <a href="https://misbuero.ch/" target="_blank" rel="noopener noreferrer">Mis Büro</a>';
}

add_filter('update_footer', __NAMESPACE__ . '\\cmx_admin_footer_version', 11);
function cmx_admin_footer_version(string $text): string {
	$plugin_file = __DIR__ . '/../cmx-misbuero.php';
	if (!is_readable($plugin_file)) {
		return $text;
	}
	$data = get_file_data($plugin_file, ['Version' => 'Version'], 'plugin');
	$version = isset($data['Version']) ? trim((string) $data['Version']) : '';
	if ($version === '') {
		return $text;
	}
	return '<span class="cmx-admin-version" translate="no">Version ' . esc_html($version) . '</span>';
}

add_filter('plugin_row_meta', __NAMESPACE__ . '\\cmx_plugin_row_meta_version', 10, 4);
function cmx_plugin_row_meta_version(array $plugin_meta, string $plugin_file, array $plugin_data, string $status): array {
	if (strpos($plugin_file, 'cmx-misbuero.php') === false) {
		return $plugin_meta;
	}
	$version = isset($plugin_data['Version']) ? trim((string) $plugin_data['Version']) : '';
	if ($version === '') {
		return $plugin_meta;
	}

	$replacement = '<span class="cmx-plugin-version" translate="no">Version ' . esc_html($version) . '</span>';
	$replaced = false;
	foreach ($plugin_meta as $index => $meta) {
		if (!is_string($meta)) {
			continue;
		}
		if (stripos($meta, 'Version ') === 0 || stripos($meta, 'Version&nbsp;') === 0) {
			$plugin_meta[$index] = $replacement;
			$replaced = true;
			break;
		}
	}
	if (!$replaced) {
		array_unshift($plugin_meta, $replacement);
	}

	return $plugin_meta;
}

add_action('admin_head', __NAMESPACE__ . '\\cmx_admin_footer_no_tel');
function cmx_admin_footer_no_tel(): void {
	echo '<meta name="format-detection" content="telephone=no">' . "\n";
	echo '<style>
	a[x-apple-data-detectors],
	a[href^="tel"],
	a[href^="tel:"] {
		color: inherit !important;
		text-decoration: none !important;
		pointer-events: none !important;
		cursor: default !important;
	}
	</style>' . "\n";
}

add_action('admin_footer', __NAMESPACE__ . '\\cmx_admin_unlink_tel_numbers');
function cmx_admin_unlink_tel_numbers(): void {
	if (!function_exists('get_current_screen')) {
		return;
	}
	$screen = get_current_screen();
	if (!$screen || $screen->id !== 'plugins') {
		return;
	}
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('#the-list a[href^="tel:"], #the-list a[href^="tel"]').forEach(function(link) {
			var span = document.createElement('span');
			span.textContent = link.textContent || '';
			span.className = link.className;
			link.replaceWith(span);
		});
	});
	</script>
	<?php
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
	body.post-php .cmx-dup-link {
		position: relative;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 36px;
		height: 36px;
		text-indent: 0;
		padding: 0;
		border: none;
		background: transparent;
		box-shadow: none;
		color: #d63638;
	}
	body.post-php .cmx-dup-link::before {
		content: "\f105"; /* dashicons-controls-repeat */
		font-size: 18px;
		color: #d63638; /* Rot passend zum Papierkorb */
	}
	body.post-php .cmx-dup-link:hover::before,
	body.post-php .cmx-dup-link:focus::before {
		color: #b42527;
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
