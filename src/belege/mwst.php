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

    add_meta_box(
        'cmx_belege_qr_box',
        'QR-Rechnung',
        __NAMESPACE__ . '\\cmx_belege_render_qr_metabox',
        'belege',
        'side',
        'low'
    );
});


/**
 * Metabox-Inhalt
 */
function cmx_belege_render_mwst_metabox($post) {

    wp_nonce_field('cmx_belege_mwst_save', 'cmx_belege_mwst_nonce');

    $opts_general = (array) get_option('cmx_einstellungen', []);
    $is_mwst_pflichtig = !empty($opts_general['mwst_pflichtig']) || !empty($opts_general['mwst_pfl']) || !empty($opts_general['mwstpflichtig']);

    // Werte laden
    $is_brutto = get_post_meta($post->ID, '_cmx_beleg_is_brutto', true);
    $mwst_term_id = get_post_meta($post->ID, '_cmx_beleg_mwst_term', true);
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
 * QR-Metabox-Inhalt
 */
function cmx_belege_render_qr_metabox($post) {
    wp_nonce_field('cmx_belege_qr_save', 'cmx_belege_qr_nonce');
    $qr_enabled = get_post_meta($post->ID, '_cmx_beleg_qr_enabled', true);
    ?>
    <p>
        <label>
            <input type="checkbox"
                   name="cmx_beleg_qr_enabled"
                   value="1"
                <?php checked($qr_enabled, '1'); ?> />
            QR-Code auf Beleg drucken
        </label><br>
        <!-- <small>Nur möglich, wenn eine QR-IBAN hinterlegt ist.</small> -->
    </p>
    <?php
}


/**
 * Speichern
 */
add_action('save_post_belege', function($post_id) {

    $mwst_nonce_ok = isset($_POST['cmx_belege_mwst_nonce'])
        && wp_verify_nonce($_POST['cmx_belege_mwst_nonce'], 'cmx_belege_mwst_save');
    $qr_nonce_ok = isset($_POST['cmx_belege_qr_nonce'])
        && wp_verify_nonce($_POST['cmx_belege_qr_nonce'], 'cmx_belege_qr_save');

    // Sicherheitsprüfung
    if (!$mwst_nonce_ok && !$qr_nonce_ok) {
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

    if ($qr_nonce_ok || $mwst_nonce_ok) {
        $qr_enabled = isset($_POST['cmx_beleg_qr_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_cmx_beleg_qr_enabled', $qr_enabled);
    }
});
