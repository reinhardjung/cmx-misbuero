<?php
/**
 * Plugin Name: CMX Projekt Stammdaten
 * Description: Fügt im CPT "projekte" eine Meta-Box "Stammdaten" hinzu (Beginn, Ende, URL, Auto-https & Schnell-Link „heute“)
 * Author: CLOUDMEISTER
 */

namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit;

/** === Einstellungen === */
const CMX_PROJ_BEG_META = '_cmx_projekt_beginn';
const CMX_PROJ_END_META = '_cmx_projekt_ende';
const CMX_PROJ_NONCE    = 'cmx_projekt_zeitraum_nonce';
if (!defined(__NAMESPACE__ . '\CMX_PROJ_URL_META')) {
	define(__NAMESPACE__ . '\CMX_PROJ_URL_META', '_cmx_projekt_url');
}

/** === Helper === */
if (!function_exists('cmx_sanitize_iso_date')) {
	function cmx_sanitize_iso_date($value) {
		$value = trim((string)$value);
		if ($value === '') return '';
		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
	}
}

/** === Meta-Box === */
add_action('add_meta_boxes', function() {

	// Alte Box ggf. entfernen
	remove_meta_box('cmx_projekt_zeitraum', 'projekte', 'side');
	remove_meta_box('cmx_projekt_zeitraum', 'projekte', 'normal');
	remove_meta_box('cmx_projekt_zeitraum', 'projekte', 'advanced');

	add_meta_box(
		'cmx_projekt_stammdaten',
		'Stammdaten',
		function($post) {
			$beginn = get_post_meta($post->ID, CMX_PROJ_BEG_META, true);
			$ende   = get_post_meta($post->ID, CMX_PROJ_END_META, true);
			$url    = get_post_meta($post->ID, CMX_PROJ_URL_META, true);

			wp_nonce_field(CMX_PROJ_NONCE, CMX_PROJ_NONCE . '_field');
			?>
			<p>
				<label for="cmx_projekt_beginn">
					<strong>Beginn</strong>
					<small>
						<a href="#" id="cmx_set_today" style="font-size:11px; text-decoration:none; margin-left:6px;">(heute)</a>
					</small>
				</label><br>
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

			<!-- JS: https:// Ergänzung + "heute"-Link -->
			<script>
			document.addEventListener('DOMContentLoaded', function() {
				const urlInput   = document.getElementById('cmx_projekt_url');
				const beginnInput = document.getElementById('cmx_projekt_beginn');
				const todayLink  = document.getElementById('cmx_set_today');

				// Auto-https bei URL
				if (urlInput) {
					urlInput.addEventListener('blur', function() {
						let val = urlInput.value.trim();
						if (val !== '' && !/^https?:\/\//i.test(val)) {
							urlInput.value = 'https://' + val.replace(/^\/+/, '');
						}
					});
				}

				// Heute-Link setzt aktuelles Datum
				if (todayLink && beginnInput) {
					todayLink.addEventListener('click', function(e) {
						e.preventDefault();
						const now = new Date();
						const iso = now.toISOString().split('T')[0];
						beginnInput.value = iso;
					});
				}
			});
			</script>
			<?php
		},
		'projekte',
		'side',
		'high'
	);
}, 50);

/** Alte Boxen bereinigen */
add_action('do_meta_boxes', function() {
	global $wp_meta_boxes;
	$post_type = 'projekte';
	if (empty($wp_meta_boxes[$post_type])) return;

	foreach (['side','normal','advanced'] as $ctx) {
		foreach (['high','core','default','low'] as $prio) {
			if (empty($wp_meta_boxes[$post_type][$ctx][$prio])) continue;
			foreach ($wp_meta_boxes[$post_type][$ctx][$prio] as $id => $box) {
				if ($id === 'cmx_projekt_zeitraum' || (isset($box['title']) && $box['title'] === 'Projektzeitraum')) {
					unset($wp_meta_boxes[$post_type][$ctx][$prio][$id]);
				}
			}
		}
	}
}, 999);

/** Speichern */
add_action('save_post_projekte', function($post_id) {

	if (
		!isset($_POST[CMX_PROJ_NONCE . '_field']) ||
		!wp_verify_nonce($_POST[CMX_PROJ_NONCE . '_field'], CMX_PROJ_NONCE)
	) return;

	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (wp_is_post_revision($post_id)) return;
	if (!current_user_can('edit_post', $post_id)) return;

	$beginn = cmx_sanitize_iso_date($_POST['cmx_projekt_beginn'] ?? '');
	$ende   = cmx_sanitize_iso_date($_POST['cmx_projekt_ende'] ?? '');
	update_post_meta($post_id, CMX_PROJ_BEG_META, $beginn);
	update_post_meta($post_id, CMX_PROJ_END_META, $ende);

	$raw_url = isset($_POST['cmx_projekt_url']) ? trim((string)$_POST['cmx_projekt_url']) : '';
	$clean   = $raw_url !== '' ? esc_url_raw($raw_url) : '';
	if ($clean === '') {
		delete_post_meta($post_id, CMX_PROJ_URL_META);
	} else {
		update_post_meta($post_id, CMX_PROJ_URL_META, $clean);
	}
});
