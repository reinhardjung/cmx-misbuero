<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


require_once 'allgemein.php';
require_once 'banken.php';
require_once 'kontakte.php';
require_once 'belege.php';
require_once 'erweitert.php';
require_once 'support.php';

require_once 'adminbar.php';

/* --------------------------------------------------------------------------
 * 0) Konstante & Slug
 * -------------------------------------------------------------------------- */

const CMX_SETTINGS_SLUG = 'cmx-einstellungen';
const CMX_SETTINGS_KEY  = 'cmx_einstellungen';


/* --------------------------------------------------------------------------
 * 1) Menü-Eintrag für Einstellungsseite
 * -------------------------------------------------------------------------- */

add_action('admin_menu', __NAMESPACE__ . '\\cmx_add_settings_menu');
function cmx_add_settings_menu(): void {
	add_menu_page(
		'Einstellungen',
		'Einstellungen',
		'manage_options',
		CMX_SETTINGS_SLUG,
		__NAMESPACE__ . '\\cmx_render_settings_page',
		'dashicons-admin-generic',
		999
	);
}


/* --------------------------------------------------------------------------
 * 2) Tabs & Subtabs
 * -------------------------------------------------------------------------- */

function cmx_get_tabs(): array {
	$tabs = [
		'general'  => __('Allgemein', 'default'),
		'kontakte' => __('Kontakte', 'default'),
		'banken'   => __('Banken', 'default'),
		'belege'   => __('Belege', 'default'),
		'advanced' => __('Erweitert', 'default'),
		'support'  => __('Support', 'default'),
	];

	return apply_filters('cmx_settings_tabs', $tabs);
}

function cmx_get_subtabs(string $tab): array {
	$map = [
		'banken' => [
			'rev'    => __('Revolut', 'default'),
			'zkb'    => __('ZKB', 'default'),
			'ubs'    => __('UBS', 'default'),
			'migros' => __('Migros', 'default'),
			'eisen'  => __('Raiffeisen', 'default'),
		],
		'kontakte' => [
			'ms' => __('Microsoft', 'default'),
			'oo' => __('Google', 'default'),
			'ic' => __('iCloud', 'default'),
		],
		'belege' => [
			'angebot'     => __('Angebot', 'default'),
			'gutschrift'  => __('Gutschrift', 'default'),
			'lieferschein'=> __('Lieferschein', 'default'),
			'rechnung'    => __('Rechnung', 'default'),
		],
		// support ohne Subtabs
	];

	return $map[$tab] ?? [];
}


/* --------------------------------------------------------------------------
 * 3) Setting registrieren (Array-Option)
 * -------------------------------------------------------------------------- */

add_action('admin_init', __NAMESPACE__ . '\\cmx_register_settings');
function cmx_register_settings(): void {
	register_setting(
		CMX_SETTINGS_KEY,
		CMX_SETTINGS_KEY,
		[
			'type'              => 'array',
			'sanitize_callback' => __NAMESPACE__ . '\\cmx_sanitize_settings',
			'default'           => [],
		]
	);
}


function cmx_field_textarea(array $args): void {
	$key   = $args['key'];
	$val   = cmx_get_option($key, '');
	$rows  = isset($args['rows']) ? (int) $args['rows'] : 6;
	$ph    = isset($args['placeholder']) ? esc_attr($args['placeholder']) : '';

	printf(
		'<textarea name="%1$s[%2$s]" rows="%3$d" class="large-text" placeholder="%4$s">%5$s</textarea>',
		esc_attr(CMX_SETTINGS_KEY),
		esc_attr($key),
		$rows,
		$ph,
		esc_textarea($val)
	);
}


/* --------------------------------------------------------------------------
 * 4) Sanitizer (unverändert, nur ausgelagert)
 * -------------------------------------------------------------------------- */

function cmx_sanitize_settings($input) {
	if (!is_array($input)) {
		$input = [];
	}

	$out = [];

	// HTML-fähige E-Mail-Texte (Textarea, HTML erlaubt)
	$keys_textarea = [
		'mail_angebot',
		'mail_gutschrift',
		'mail_lieferschein',
		'mail_rechnung',
	];

	foreach ($keys_textarea as $k) {
		if (isset($input[$k])) {
			// HTML erlauben, aber durch wp_kses_post filtern
			$out[$k] = wp_kses_post($input[$k]);
		}
	}

	// Bestehende Text-Felder (plain text / URL)
	$keys_text = [
		'site_label','rev_iban','rev_recipient','rev_bank_api',
		'zkb_iban','zkb_recipient','zkb_bank_api',
		'ubs_iban','ubs_recipient','ubs_bank_api',
		'migros_iban','migros_recipient','migros_bank_api',
		'bank_rev','bank_name','iban',
		'rev_bank_name','rev_api',
		'zkb_bank_name','zkb_api',
		'ubs_bank_name','ubs_api',
		'migros_bank_name','ubs_api',
		'eisen_bank_name','eisen_api',
		'eisen_recipient','eisen_iban',
		'beleg_logo_url',
	];

	foreach ($keys_text as $k) {
		if (isset($input[$k])) {
			$out[$k] = ($k === 'beleg_logo_url')
				? esc_url_raw(trim((string) $input[$k]))
				: sanitize_text_field($input[$k]);
		}
	}

	$keys_bool = [
		'enable_feature','rev_bank_enabled','zkb_bank_enabled','ubs_bank_enabled',
		'migros_bank_enabled','debug_mode','bank_enabled','bank_enabled_eisen',
		'rev_enabled','zkb_enabled','ubs_enabled','migros_enabled','eisen_enabled',
	];

	foreach ($keys_bool as $k) {
		$out[$k] = !empty($input[$k]) ? 1 : 0;
	}

	$existing = get_option(CMX_SETTINGS_KEY, []);
	if (is_array($existing)) {
		$out = array_merge($existing, $out);
	}

	return $out;
}



/* --------------------------------------------------------------------------
 * 5) Helper: Option lesen & Felder rendern
 * -------------------------------------------------------------------------- */

function cmx_get_option(string $key, $default = '') {
	$opts = get_option(CMX_SETTINGS_KEY, []);
	return $opts[$key] ?? $default;
}

function cmx_field_text(array $args): void {
	$key = $args['key'];
	$val = cmx_get_option($key, '');
	$ph  = isset($args['placeholder']) ? esc_attr($args['placeholder']) : '';

	printf(
		'<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" placeholder="%4$s" />',
		esc_attr(CMX_SETTINGS_KEY),
		esc_attr($key),
		esc_attr($val),
		$ph
	);
}

function cmx_field_checkbox(array $args): void {
	$key   = $args['key'];
	$label = $args['label'] ?? '';
	$val   = cmx_get_option($key, 0);

	printf(
		'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s> %4$s</label>',
		esc_attr(CMX_SETTINGS_KEY),
		esc_attr($key),
		checked(1, (int) $val, false),
		esc_html($label)
	);
}

function cmx_field_url(array $args): void {
	$key   = $args['key'];
	$label = $args['label'] ?? '';
	$value = cmx_get_option($key, '');

	echo '<input type="url" name="' . esc_attr(CMX_SETTINGS_KEY) . '[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://example.com">';
	if ($label) {
		echo '<p class="description">' . esc_html($label) . '</p>';
	}
}


/* --------------------------------------------------------------------------
 * 6) Render der Einstellungsseite (Tabs + Tab-Content)
 * -------------------------------------------------------------------------- */

function cmx_render_settings_page(): void {
	if (!current_user_can('manage_options')) {
		wp_die(__('Nicht erlaubt.', 'default'));
	}

	$tabs = cmx_get_tabs();
	$tab  = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : array_key_first($tabs);

	if (!isset($tabs[$tab])) {
		$tab = array_key_first($tabs);
	}

	$subtabs = cmx_get_subtabs($tab);
	$sub     = isset($_GET['sub']) ? sanitize_key($_GET['sub']) : (array_key_first($subtabs) ?: '');

	if ($sub && !isset($subtabs[$sub])) {
		$sub = array_key_first($subtabs);
	}

	$page_id = $sub ? 'cmx_tab_' . $tab . '__' . $sub : 'cmx_tab_' . $tab;

	?>
	<div class="wrap">
		<h1><?php echo esc_html__('Einstellungen', 'default'); ?></h1>

		<h2 class="nav-tab-wrapper cmx-tabs">
			<?php foreach ($tabs as $key => $label) : ?>
				<a class="nav-tab <?php echo $key === $tab ? 'nav-tab-active' : ''; ?>"
				   href="<?php echo esc_url(add_query_arg(['page' => CMX_SETTINGS_SLUG, 'tab' => $key], admin_url('admin.php'))); ?>">
					<?php echo esc_html($label); ?>
				</a>
			<?php endforeach; ?>
		</h2>

		<?php if (!empty($subtabs)) : ?>
			<ul class="subsubsub cmx-subtabs">
				<?php
				$i = 0;
				$n = count($subtabs);
				foreach ($subtabs as $slug => $label) :
					$url = add_query_arg(
						[
							'page' => CMX_SETTINGS_SLUG,
							'tab'  => $tab,
							'sub'  => $slug,
						],
						admin_url('admin.php')
					);
					?>
					<li>
						<a href="<?php echo esc_url($url); ?>" class="<?php echo $slug === $sub ? 'current' : ''; ?>">
							<?php echo esc_html($label); ?>
						</a><?php echo (++$i < $n) ? ' | ' : ''; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<br class="clear" />
		<?php endif; ?>

		<div class="cmx-tabpanel">
			<?php
			if ($tab === 'support') {
				// Implementierung liegt in settings-tab-support.php
				if (function_exists(__NAMESPACE__ . '\\cmx_render_support_tab')) {
					cmx_render_support_tab();
				}
			} else {
				?>
				<form method="post" action="options.php">
					<?php
					settings_fields(CMX_SETTINGS_KEY);
					do_settings_sections($page_id);
					submit_button(__('Änderungen speichern', 'default'));
					?>
				</form>
				<?php
			}
			?>
		</div>
	</div>
	<?php
}


/* --------------------------------------------------------------------------
 * 7) Persistenz-Helfer (Merge aller Felder)
 * -------------------------------------------------------------------------- */

add_filter('pre_update_option_' . CMX_SETTINGS_KEY, __NAMESPACE__ . '\\cmx_settings_merge_all_fields', 10, 3);
add_filter('pre_update_option_' . CMX_SETTINGS_KEY, __NAMESPACE__ . '\\cmx_settings_merge_all_fields', 10, 3);
add_filter('pre_update_option_' . CMX_SETTINGS_KEY, __NAMESPACE__ . '\\cmx_settings_merge_all_fields', 10, 3);
function cmx_settings_merge_all_fields($new_value, $old_value, $option) {
	if (!current_user_can('manage_options')) {
		return $new_value;
	}

	if (empty($_POST[CMX_SETTINGS_KEY]) || !is_array($_POST[CMX_SETTINGS_KEY])) {
		return $new_value;
	}

	$post    = $_POST[CMX_SETTINGS_KEY];
	$merged  = is_array($new_value) ? $new_value : [];
	$defaults = cmx_get_email_defaults_from_ini();

	// 1) E-Mail HTML-Texte: leere Felder mit INI-Defaults füllen
	$textarea_keys = [
		'mail_angebot',
		'mail_gutschrift',
		'mail_lieferschein',
		'mail_rechnung',
	];

	foreach ($textarea_keys as $k) {
		if (array_key_exists($k, $post)) {
			$raw = (string) $post[$k];

			if ($raw === '' && isset($defaults[$k]) && $defaults[$k] !== '') {
				// Benutzer hat leer gelassen -> Default aus INI einsetzen
				$merged[$k] = wp_kses_post($defaults[$k]);
			} elseif (isset($new_value[$k])) {
				// Benutzer hat etwas eingegeben -> bereits sanitizte Version übernehmen
				$merged[$k] = $new_value[$k];
			}
		}
	}

	// 2) Bestehende Text-Felder weiter mergen
	$text_keys = [
		'rev_bank_name','rev_recipient','rev_iban','rev_api',
		'zkb_bank_name','zkb_recipient','zkb_iban','zkb_api',
		'ubs_bank_name','ubs_recipient','ubs_iban','ubs_api',
		'migros_bank_name','migros_recipient','migros_iban','migros_api',
		'eisen_bank_name','eisen_recipient','eisen_iban','eisen_api',
		'site_label','bank_rev','bank_name','iban',
		'beleg_logo_url',
	];

	foreach ($text_keys as $k) {
		if (isset($new_value[$k])) {
			$merged[$k] = $new_value[$k];
		}
	}

	// 3) Boolean-Felder mergen
	$bool_keys = [
		'rev_enabled','zkb_enabled','ubs_enabled','migros_enabled','eisen_enabled',
		'debug_mode','enable_feature','bank_enabled','bank_enabled_eisen',
	];

	foreach ($bool_keys as $k) {
		if (isset($new_value[$k])) {
			$merged[$k] = !empty($new_value[$k]) ? 1 : 0;
		}
	}

	return $merged;
}
