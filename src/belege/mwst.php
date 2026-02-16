<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * Metabox registrieren
 */
add_action('add_meta_boxes', function() {
    add_meta_box(
        'cmx_belege_mwst_box',
        'Preisangaben',
        __NAMESPACE__ . '\\cmx_belege_render_mwst_metabox',
        'belege',
        'side',
        'default'
    );
});

// Ensure MWST metabox isn't hidden via screen options/user meta.
add_filter('get_user_option_metaboxhidden_belege', function ($hidden) {
    $hidden = is_array($hidden) ? $hidden : (array) $hidden;
    return array_values(array_diff($hidden, ['cmx_belege_mwst_box']));
});
add_filter('hidden_meta_boxes', function ($hidden, $screen) {
    if ($screen && $screen->post_type === 'belege') {
        $hidden = is_array($hidden) ? $hidden : (array) $hidden;
        $hidden = array_values(array_diff($hidden, ['cmx_belege_mwst_box']));
    }
    return $hidden;
}, 10, 2);


/**
 * Metabox-Inhalt
 */
function cmx_belege_render_mwst_metabox($post) {

    wp_nonce_field('cmx_belege_mwst_save', 'cmx_belege_mwst_nonce');

    $opts_general = (array) get_option('cmx_einstellungen', []);
    $is_mwst_pflichtig = !empty($opts_general['mwst_pflichtig']) || !empty($opts_general['mwst_pfl']) || !empty($opts_general['mwstpflichtig']);
    $default_is_brutto = !empty($opts_general['belege_default_is_brutto']) ? '1' : '0';
    $default_mwst_term_id = isset($opts_general['belege_default_mwst_term']) ? (int) $opts_general['belege_default_mwst_term'] : 0;

    // Werte laden
    $is_brutto = get_post_meta($post->ID, '_cmx_beleg_is_brutto', true);
    $mwst_term_id = get_post_meta($post->ID, '_cmx_beleg_mwst_term', true);
    $is_new_autodraft = ($post instanceof \WP_Post) && ((string) $post->post_status === 'auto-draft');
    if ($is_new_autodraft) {
        if ($is_brutto === '') {
            $is_brutto = $default_is_brutto;
        }
        if ($mwst_term_id === '' || $mwst_term_id === null) {
            $mwst_term_id = $default_mwst_term_id > 0 ? (string) $default_mwst_term_id : '';
        }
    }

    $mwst_term_id = (int) $mwst_term_id;
    // Taxonomie-Terme laden
    $terme = get_terms([
        'taxonomy'   => 'belege_mwst',
        'hide_empty' => false,
    ]);

    if (!$is_mwst_pflichtig) {
        echo '<p><em>MwSt-pflichtig ist in den Einstellungen deaktiviert. MwSt-Felder ausgeblendet.</em></p>';
    } else {
        ?>
        <p>
            <label>
                <input type="checkbox"
                       name="cmx_beleg_is_brutto"
                       value="1"
                    <?php checked($is_brutto, '1'); ?> />
                Brutto (inkl.) / Netto (ohne MWST)
            </label>
        </p>

        <p>
            <label id="cmx_beleg_mwst_label" for="cmx_beleg_mwst_term" style="color:#a42c24;"><strong>MWST-Satz</strong></label><br>
            <select name="cmx_beleg_mwst_term" id="cmx_beleg_mwst_term" style="width:100%;">
                <option value="">— auswählen —</option>

                <?php foreach ($terme as $term): ?>
                    <option value="<?php echo esc_attr($term->term_id); ?>"
                        <?php selected($mwst_term_id, $term->term_id); ?>>
                        <?php echo esc_html($term->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }
    ?>

    <?php if ($is_mwst_pflichtig): ?>
    <script>
    (function() {
        const label  = document.getElementById('cmx_beleg_mwst_label');
        const select = document.getElementById('cmx_beleg_mwst_term');
        if (!label || !select) return;

        const overviewUrl = <?php echo wp_json_encode(admin_url('edit-tags.php?taxonomy=belege_mwst&post_type=belege')); ?>;
        const termUrlBase = <?php echo wp_json_encode(admin_url('term.php?taxonomy=belege_mwst&post_type=belege&tag_ID=')); ?>;

        label.style.cursor = 'pointer';
        label.addEventListener('click', function(event) {
            event.preventDefault();
            const termId = select.value;
            const target = termId ? termUrlBase + termId : overviewUrl;
            window.open(target, '_blank', 'noopener');
        });
    })();
    </script>
    <?php endif;
}

/**
 * Ausgaben-Metabox (nur für Lieferantenrechnung)
 */
function cmx_belege_render_ausgaben_metabox($post) {
    wp_nonce_field('cmx_belege_ausgaben_save', 'cmx_belege_ausgaben_nonce');

    $tax = function_exists(__NAMESPACE__ . '\\cmx_tax_key') ? cmx_tax_key('michbuechli', 'Kategorien') : 'michbuechli_kategorien';
    if (!taxonomy_exists($tax)) {
        echo '<em>Michbuechli-Kategorien nicht gefunden.</em>';
        return;
    }

    $current = get_post_meta($post->ID, '_cmx_beleg_michbuechli_kategorien', true);
    if (!is_array($current)) {
        $current = $current !== '' ? [(int)$current] : [];
    }
    $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => false]);

    if (is_wp_error($terms) || empty($terms)) {
        echo '<em>Keine Michbuechli-Kategorien vorhanden.</em>';
        return;
    }

    // echo '<p><strong>Ausgaben-Kategorie</strong></p>';
    foreach ($terms as $term) {
        echo '<label style="display:block;margin-bottom:4px;">';
        echo '<input type="checkbox" name="cmx_beleg_michbuechli_kategorien[]" value="'.esc_attr($term->term_id).'" '.checked(in_array((int)$term->term_id, $current, true), true, false).'> ';
        echo esc_html($term->name);
        echo '</label>';
    }
}

add_action('admin_footer', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== 'belege') return;
    $opts_general = (array) get_option('cmx_einstellungen', []);
    $is_mwst_pflichtig = !empty($opts_general['mwst_pflichtig']) || !empty($opts_general['mwst_pfl']) || !empty($opts_general['mwstpflichtig']);
    ?>
    <script>
    (function(){
        const box = document.getElementById('cmx_belege_mwst_box');
        const anzahlungenBox = document.getElementById('cmx_beleg_anzahlungen');
        const isMwstPflichtig = <?php echo wp_json_encode((bool) $is_mwst_pflichtig); ?>;
        const mwstSlugs = new Set([
            'rechnung','rechnungen',
            'offerte','offerten',
            'gutschrift','gutschriften',
            'quittung','quittungen',
        ]);
        function getSelectedSlug(){
            const selected = document.querySelector('input[name="cmx_beleg_kategorie"]:checked');
            return selected ? (selected.getAttribute('data-slug') || '') : '';
        }
        function sync(){
            const slug = getSelectedSlug();
            if (box) {
                const showMwst = isMwstPflichtig && (slug === '' || mwstSlugs.has(slug));
                box.style.display = showMwst ? '' : 'none';
            }
            if (anzahlungenBox) {
                anzahlungenBox.style.display = (slug === 'lieferschein' || slug === 'gutschrift') ? 'none' : '';
            }
        }
        document.addEventListener('change', function(e){
            if (e.target && e.target.name === 'cmx_beleg_kategorie') {
                sync();
            }
        });
        sync();
    })();
    </script>
    <?php
});

/**
 * Speichern
 */
add_action('save_post_belege', function($post_id) {

    $mwst_nonce_ok = isset($_POST['cmx_belege_mwst_nonce'])
        && wp_verify_nonce($_POST['cmx_belege_mwst_nonce'], 'cmx_belege_mwst_save');
    $ausgaben_nonce_ok = isset($_POST['cmx_belege_ausgaben_nonce'])
        && wp_verify_nonce($_POST['cmx_belege_ausgaben_nonce'], 'cmx_belege_ausgaben_save');
    // Sicherheitsprüfung
    if (!$mwst_nonce_ok && !$ausgaben_nonce_ok) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $opts_general = (array) get_option('cmx_einstellungen', []);
    $is_mwst_pflichtig = !empty($opts_general['mwst_pflichtig']) || !empty($opts_general['mwst_pfl']) || !empty($opts_general['mwstpflichtig']);

    if ($mwst_nonce_ok) {
        if ($is_mwst_pflichtig) {
            $is_brutto = isset($_POST['cmx_beleg_is_brutto']) ? '1' : '0';
            update_post_meta($post_id, '_cmx_beleg_is_brutto', $is_brutto);

            $mwst_term_id = isset($_POST['cmx_beleg_mwst_term']) ? intval($_POST['cmx_beleg_mwst_term']) : '';
            update_post_meta($post_id, '_cmx_beleg_mwst_term', $mwst_term_id);
        } else {
            update_post_meta($post_id, '_cmx_beleg_is_brutto', '0');
            update_post_meta($post_id, '_cmx_beleg_mwst_term', '');
        }
    }

    if ($ausgaben_nonce_ok) {
        $terms = isset($_POST['cmx_beleg_michbuechli_kategorien']) && is_array($_POST['cmx_beleg_michbuechli_kategorien'])
            ? array_map('intval', (array) $_POST['cmx_beleg_michbuechli_kategorien'])
            : [];
        $terms = array_values(array_filter(array_unique($terms), fn($v) => $v > 0));
        update_post_meta($post_id, '_cmx_beleg_michbuechli_kategorien', $terms);
    }

    if ($mwst_nonce_ok) {
        // keine QR-Checkbox mehr: QR wird automatisch je nach Bank-QR-IBAN genutzt
    }
});
