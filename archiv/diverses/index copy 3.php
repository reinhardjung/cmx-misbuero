<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') or die('Oxytocin!');


const CMX_SETTINGS_SLUG = 'cmx-einstellungen';
const CMX_SETTINGS_KEY  = 'cmx_einstellungen';


function cmx_add_settings_menu(): void {
    add_menu_page('Einstellungen','Einstellungen','manage_options',CMX_SETTINGS_SLUG,__NAMESPACE__ . '\\cmx_render_settings_page','dashicons-admin-generic',999);
}
add_action('admin_menu', __NAMESPACE__ . '\\cmx_add_settings_menu');



// Tabs definieren (per Filter erweiterbar)
function cmx_get_tabs(): array {
    $tabs = [
        'general'  => __('Allgemein', 'default'),
        'banken'   => __('Banken', 'default'),
        'advanced' => __('Erweitert', 'default'),
    ];
    /** Dritte können Tabs ergänzen/ändern */
    return apply_filters('cmx_settings_tabs', $tabs);
}



// Settings registrieren
add_action('admin_init', __NAMESPACE__ . '\\cmx_register_settings');
function cmx_register_settings(): void {
    register_setting(CMX_SETTINGS_KEY, CMX_SETTINGS_KEY, [
        'type'              => 'array',
        'sanitize_callback' => __NAMESPACE__ . '\\cmx_sanitize_settings',
        'default'           => [],
    ]);

    // Sektion: Allgemein
    add_settings_section('cmx_sec_general', __('Allgemein', 'default'), '__return_false', 'cmx_tab_general');
    add_settings_field('site_label', __('Bezeichner', 'default'), __NAMESPACE__ . '\\cmx_field_text', 'cmx_tab_general', 'cmx_sec_general', [
        'key' => 'site_label',
        'placeholder' => 'Mein Projekt',
    ]);
    add_settings_field('enable_feature', __('Funktion aktivieren', 'default'), __NAMESPACE__ . '\\cmx_field_checkbox', 'cmx_tab_general', 'cmx_sec_general', [
        'key' => 'enable_feature',
        'label' => __('Ja, aktivieren', 'default'),
    ]);


 function cmx_section_banken_desc() {
    // echo '<h3 style="margin-top:10px;">' . esc_html__('Bankverbindung für Rechnungen', 'default') . '</h3>';
    echo '<p class="description">';
    echo esc_html__('Hier kannst du deine Kontoinformationen hinterlegen. Diese erscheinen automatisch auf deinen Rechnungen und Angeboten.', 'default');
    echo '</p>';
};



    add_settings_section('cmx_sec_banken', __('Banken', 'default'), '__return_false', 'cmx_tab_banken');


    add_settings_section(
        'cmx_sec_banken',
        __('Banken', 'default'),
        __NAMESPACE__ . '\\cmx_section_banken_desc',
        'cmx_tab_banken'
    );


    // NEUES Feld: "Bank" als Ja/Nein-Checkbox
add_settings_field(
    'bank_enabled',
    __('Revolut', 'default'),
    __NAMESPACE__ . '\\cmx_field_checkbox',
    'cmx_tab_banken',
    'cmx_sec_banken',
    [
        'key'   => 'bank_enabled',
        'label' => __('Ja, aktivieren', 'default'),
    ]
);

// (Optional) – Sanitizer ergänzen, falls noch nicht vorhanden:
add_filter('cmx_sanitize_settings', function(array $out, array $input){
    $out['bank_enabled'] = !empty($input['bank_enabled']) ? 1 : 0;
    // $out['bank_api'] wird bereits in Deiner Textfeld-Logik gesäubert, falls vorhanden:
    if (isset($input['bank_api'])) {
        $out['bank_api'] = sanitize_text_field($input['bank_api']);
    }
    return $out;
}, 10, 2);




add_settings_field('bank_name',__('API', 'default'),__NAMESPACE__ . '\\cmx_field_text','cmx_tab_banken','cmx_sec_banken',
    [
        'key'         => 'bank_api',
        'title'       => 'Allgemeine Bankdaten',
        'placeholder' => 'z. B. Zürcher Kantonalbank',
    ]
);



// Bestehendes Feld (API) – unverändert
add_settings_field('bank_name',__('API', 'default'),__NAMESPACE__ . '\\cmx_field_text','cmx_tab_banken','cmx_sec_banken',
    [
        'key'         => 'bank_api',
        'title'       => 'Allgemeine Bankdaten',
        'placeholder' => 'z. B. Zürcher Kantonalbank',
    ]
);




    add_settings_field('iban',__('IBAN', 'default'),__NAMESPACE__ . '\\cmx_field_text','cmx_tab_banken','cmx_sec_banken',
        [
            'key' => 'iban',
            'placeholder' => 'CHxx xxxx xxxx xxxx xxxx x',
        ]
    );


    // Sektion: Erweitert
    add_settings_section('cmx_sec_advanced', __('Erweitert', 'default'), '__return_false', 'cmx_tab_advanced');
    add_settings_field('debug_mode', __('Debug-Modus', 'default'), __NAMESPACE__ . '\\cmx_field_checkbox', 'cmx_tab_advanced', 'cmx_sec_advanced', [
        'key' => 'debug_mode',
        'label' => __('Protokollierung einschalten', 'default'),
    ]);

    do_action('cmx_after_register_settings');
}

// Sanitizer
function cmx_sanitize_settings(array $input): array {
    $out = [];
    $out['site_label']    = isset($input['site_label']) ? sanitize_text_field($input['site_label']) : '';
    $out['enable_feature']= !empty($input['enable_feature']) ? 1 : 0;
    $out['brand_color']   = isset($input['brand_color']) ? sanitize_hex_color($input['brand_color']) : '';
    $out['logo_url']      = isset($input['logo_url']) ? esc_url_raw($input['logo_url']) : '';
    $out['debug_mode']    = !empty($input['debug_mode']) ? 1 : 0;

    /**
     * Erlaube Drittcode, zusätzliche Keys zu säubern.
     * Rückgabe MUSS ein Array sein.
     */
    $out = apply_filters('cmx_sanitize_settings', $out, $input);
    return is_array($out) ? $out : [];
}

// Helper: Optionen laden
function cmx_get_option(string $key, $default = '') {
    $opts = get_option(CMX_SETTINGS_KEY, []);
    return $opts[$key] ?? $default;
}

// Fields: Text
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

// Fields: Checkbox
function cmx_field_checkbox(array $args): void {
    $key   = $args['key'];
    $label = isset($args['label']) ? $args['label'] : '';
    $val   = cmx_get_option($key, 0);
    printf(
        '<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s> %4$s</label>',
        esc_attr(CMX_SETTINGS_KEY),
        esc_attr($key),
        checked(1, (int)$val, false),
        esc_html($label)
    );
}

// Fields: Color
function cmx_field_color(array $args): void {
    $key = $args['key'];
    $def = isset($args['default']) ? $args['default'] : '#000000';
    $val = cmx_get_option($key, $def);
    printf(
        '<input type="text" class="cmx-color-field" name="%1$s[%2$s]" value="%3$s" data-default-color="%4$s" />',
        esc_attr(CMX_SETTINGS_KEY),
        esc_attr($key),
        esc_attr($val),
        esc_attr($def)
    );
    // Color Picker laden
    add_action('admin_enqueue_scripts', function($hook) {
        if ($hook !== 'toplevel_page_' . CMX_SETTINGS_SLUG) return;
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('cmx-color-picker', plugins_url('cmx-color.js', __FILE__), ['wp-color-picker'], false, true);
    });
}

// Fallback für Color-Picker Inline (falls Datei nicht existiert)
add_action('admin_print_footer_scripts', __NAMESPACE__ . '\\cmx_inline_colorpicker_script');
function cmx_inline_colorpicker_script(): void {
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'toplevel_page_' . CMX_SETTINGS_SLUG) return;
    ?>
    <script>
    (function($){$(function(){ $('.cmx-color-field').wpColorPicker(); });})(jQuery);
    </script>
    <style>
        .cmx-tabs{margin-top:20px;border-bottom:1px solid #ccd0d4;display:flex;gap:6px}
        .cmx-tabs a{padding:8px 12px;text-decoration:none;border:1px solid #ccd0d4;border-bottom:none;background:#f6f7f7;border-radius:4px 4px 0 0}
        .cmx-tabs a.nav-tab-active{background:#fff;font-weight:600}
        .cmx-tabpanel{background:#fff;border:1px solid #ccd0d4;border-top:none;padding:16px}
    </style>
    <?php
}

// Settings-Seite rendern
function cmx_render_settings_page(): void {
    if (!current_user_can('manage_options')) {
        wp_die(__('Nicht erlaubt.', 'default'));
    }

    $tabs = cmx_get_tabs();
    $tab  = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : array_key_first($tabs);
    if (!isset($tabs[$tab])) {
        $tab = array_key_first($tabs);
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('CMX Einstellungen', 'default'); ?></h1>

        <h2 class="nav-tab-wrapper cmx-tabs">
            <?php foreach ($tabs as $key => $label): ?>
                <a class="nav-tab <?php echo $key === $tab ? 'nav-tab-active' : ''; ?>"
                   href="<?php echo esc_url(add_query_arg(['page' => CMX_SETTINGS_SLUG, 'tab' => $key], admin_url('admin.php'))); ?>">
                    <?php echo esc_html($label); ?>
                </a>
            <?php endforeach; ?>
        </h2>

        <div class="cmx-tabpanel">
            <form method="post" action="options.php">
                <?php
                    settings_fields(CMX_SETTINGS_KEY);
                    /**
                     * Pro Tab eine eigene "Page" Kennung:
                     *   - cmx_tab_<tabkey>
                     *   - Dort hängen die Sections/Felder (siehe cmx_register_settings)
                     */
                    do_settings_sections('cmx_tab_' . $tab);
                    submit_button(__('Änderungen speichern', 'default'));
                ?>
            </form>
        </div>
    </div>
    <?php
}

/**
 * Beispiel: Tabs per Filter erweitern
 *
 * add_filter('cmx_settings_tabs', function($tabs){
 *     $tabs['security'] = 'Sicherheit';
 *     return $tabs;
 * });
 *
 * add_action('admin_init', function(){
 *     add_settings_section('cmx_sec_security', 'Sicherheit', '__return_false', 'cmx_tab_security');
 *     add_settings_field('nonce_lifetime', 'Nonce Lifetime', '\\CLOUDMEISTER\\CMX\\Buero\\cmx_field_text', 'cmx_tab_security', 'cmx_sec_security', ['key'=>'nonce_lifetime','placeholder'=>'3600']);
 * });
 */
