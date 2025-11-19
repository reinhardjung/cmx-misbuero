<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Meta-Box "Stammdaten" (Beginn, Ende & URL) für CPT "projekte"
 * - Anzeige: SIDE
 * - Nutzt dieselbe ID wie früher, damit keine zweite Box entsteht
 */

// === Einstellungen ===
const CMX_PROJ_BEG_META = '_cmx_projekt_beginn';
const CMX_PROJ_END_META = '_cmx_projekt_ende';
const CMX_PROJ_NONCE    = 'cmx_projekt_zeitraum_nonce';

// URL-Metafeld
if (!defined(__NAMESPACE__ . '\CMX_PROJ_URL_META')) {
	define(__NAMESPACE__ \ '\CMX_PROJ_URL_META', '_cmx_projekt_url');
}

// Helper: ISO-Datum (YYYY-MM-DD) strikt prüfen
if (!function_exists('cmx_sanitize_iso_date')) {
	function cmx_sanitize_iso_date($value) {
		$value = trim((string)$value);
		if ($value === '') return '';
		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
	}
}

/**
 * Meta-Box registrieren (SIDE)
 * - Entfernt alte Einträge in allen Kontexten und ersetzt sie mit neuem Titel
 */
add_action('add_meta_boxes', function() {
	// ggf. vorhandene Box mit gleicher ID in allen Kontexten entfernen
	remove_meta_box('cmx_projekt_zeitraum', 'projekte', 'side');
	remove_meta_box('cmx_projekt_zeitraum', 'projekte', 'normal');
	remove_meta_box('cmx_projekt_zeitraum', 'projekte', 'advanced');

	// gleiche ID wie früher => überschreibt statt zu duplizieren
	add_meta_box(
		'cmx_projekt_zeitraum',   // BEIBEHALTENE ID!
		'Stammdaten',             // NEUER TITEL
		function($post) {
			$beginn = get_post_meta($post->ID, CMX_PROJ_BEG_META, true);
			$ende   = get_post_meta($post->ID, CMX_PROJ_END_META, true);
			$url    = get_post_meta($post->ID, CMX_PROJ_URL_META, true);

			wp_nonce_field(CMX_PROJ_NONCE, CMX_PROJ_NONCE . '_field');
			?>
			<p>
				<label for="cmx_projekt_beginn"><strong>Beginn</strong></label><br>
				<input type="date" id="cmx_projekt_beginn" name="cmx_projekt_beginn"
					value="<?php echo esc_attr($beginn); ?>" style="width:100%;">
			</p>
			<p>
				<label for="cmx_projekt_ende"><strong>Ende</strong></label><br>
				<input type="date" id="cmx_projekt_ende" name="cmx_projekt_ende"
					value="<?php echo esc_attr($ende); ?>" style="width:100%;">
			</p>
			<p>
				<?php if (!empty($url)) : ?>
					<label for="cmx_projekt_url">
						<a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><strong>URL</strong></a>
					</label><br>
				<?php else : ?>
					<label for="cmx_projekt_url"><strong>URL</strong></label><br>
				<?php endif; ?>
				<input type="url" id="cmx_projekt_url" name="cmx_projekt_url"
					placeholder="https://example.ch"
					value="<?php echo esc_attr($url); ?>" style="width:100%;">
			</p>
			<?php
		},
		'projekte',
		'side',
		'high'
	);
}, 99);

// Fallback: falls ein anderes Plugin später erneut hinzufügt, hier nochmal entfernen
add_action('do_meta_boxes', function() {
	remove_meta_box('cmx_projekt_zeitraum', 'projekte', 'normal');
	remove_meta_box('cmx_projekt_zeitraum', 'projekte', 'advanced');
}, 99);

/**
 * Speichern
 */
add_action('save_post_projekte', function($post_id) {
	// Nonce prüfen
	if (
		!isset($_POST[CMX_PROJ_NONCE . '_field']) ||
		!wp_verify_nonce($_POST[CMX_PROJ_NONCE . '_field'], CMX_PROJ_NONCE)
	) {
		return;
	}

	// Autosave/Revision/Capability prüfen
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (wp_is_post_revision($post_id)) return;
	if (!current_user_can('edit_post', $post_id)) return;

	$beginn = cmx_sanitize_iso_date($_POST['cmx_projekt_beginn'] ?? '');
	$ende   = cmx_sanitize_iso_date($_POST['cmx_projekt_ende'] ?? '');

	update_post_meta($post_id, CMX_PROJ_BEG_META, $beginn);
	update_post_meta($post_id, CMX_PROJ_END_META, $ende);

	// URL speichern (leer = löschen)
	$raw_url = isset($_POST['cmx_projekt_url']) ? trim((string)$_POST['cmx_projekt_url']) : '';
	$clean   = $raw_url !== '' ? esc_url_raw($raw_url) : '';
	if ($clean === '') {
		delete_post_meta($post_id, CMX_PROJ_URL_META);
	} else {
		update_post_meta($post_id, CMX_PROJ_URL_META, $clean);
	}
});
