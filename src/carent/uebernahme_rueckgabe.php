<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_ORT_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_ORT_META', '_cmx_carent_uebernahme_ort');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_DATUM_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_DATUM_META', '_cmx_carent_uebernahme_datum');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_UHRZEIT_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_UHRZEIT_META', '_cmx_carent_uebernahme_uhrzeit');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_VERMIETER_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_VERMIETER_META', '_cmx_carent_uebernahme_vermieter_attachment_id');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_MIETER_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_MIETER_META', '_cmx_carent_uebernahme_mieter_attachment_id');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_BESONDERE_ABMACHUNGEN_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_BESONDERE_ABMACHUNGEN_META', '_cmx_carent_uebernahme_besondere_abmachungen');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_MIETER_AGB_AKZEPTIERT_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_MIETER_AGB_AKZEPTIERT_META', '_cmx_carent_uebernahme_mieter_agb_akzeptiert');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_ORT_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_ORT_META', '_cmx_carent_rueckgabe_ort');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_DATUM_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_DATUM_META', '_cmx_carent_rueckgabe_datum');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_UHRZEIT_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_UHRZEIT_META', '_cmx_carent_rueckgabe_uhrzeit');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_VERMIETER_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_VERMIETER_META', '_cmx_carent_rueckgabe_vermieter_attachment_id');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_MIETER_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_MIETER_META', '_cmx_carent_rueckgabe_mieter_attachment_id');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_BESTANDSAUFNAHME_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_BESTANDSAUFNAHME_META', '_cmx_carent_bestandsaufnahme_attachment_id');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_BESTANDSAUFNAHME_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_BESTANDSAUFNAHME_META', '_cmx_carent_rueckgabe_bestandsaufnahme_attachment_id');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_transfer_box_configs')) {
	function cmx_carent_transfer_box_configs(): array {
		return [
			'uebernahme' => [
				'id' => 'cmx_carent_uebernahme_box',
				'title' => \__('Übernahme', 'cmx-misbuero'),
				'box_key' => 'uebernahme',
				'datum_meta' => CMX_CARENT_UEBERNAHME_DATUM_META,
				'uhrzeit_meta' => CMX_CARENT_UEBERNAHME_UHRZEIT_META,
				'ort_meta' => CMX_CARENT_UEBERNAHME_ORT_META,
				'vermieter_meta' => CMX_CARENT_UEBERNAHME_VERMIETER_META,
				'mieter_meta' => CMX_CARENT_UEBERNAHME_MIETER_META,
				'inventory_meta' => CMX_CARENT_BESTANDSAUFNAHME_META,
			],
			'rueckgabe' => [
				'id' => 'cmx_carent_rueckgabe_box',
				'title' => \__('Rückgabe', 'cmx-misbuero'),
				'box_key' => 'rueckgabe',
				'datum_meta' => CMX_CARENT_RUECKGABE_DATUM_META,
				'uhrzeit_meta' => CMX_CARENT_RUECKGABE_UHRZEIT_META,
				'ort_meta' => CMX_CARENT_RUECKGABE_ORT_META,
				'vermieter_meta' => CMX_CARENT_RUECKGABE_VERMIETER_META,
				'mieter_meta' => CMX_CARENT_RUECKGABE_MIETER_META,
				'inventory_meta' => CMX_CARENT_RUECKGABE_BESTANDSAUFNAHME_META,
			],
		];
	}
}

\add_action('admin_enqueue_scripts', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== 'carent') {
		return;
	}

	$asset_path = \dirname(__DIR__, 2) . '/assets/geolocation.js';
	$asset_url = (string) \plugins_url('assets/geolocation.js', \dirname(__DIR__, 2) . '/cmx-misbuero.php');
	$asset_version = \file_exists($asset_path) ? (string) \filemtime($asset_path) : '1.0.0';

	\wp_enqueue_script('cmx-geolocation', $asset_url, [], $asset_version, true);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_transfer_upload_empty_markup')) {
	function cmx_carent_transfer_upload_empty_markup(string $preview_id): string {
		return '<div id="' . \esc_attr($preview_id) . '" style="display:flex;align-items:center;justify-content:center;min-height:140px;padding:12px;text-align:center;border:1px dashed #c3c4c7;border-radius:6px;color:#646970;background:#f6f7f7;">' . \esc_html__('Foto hier ablegen oder anklicken.', 'cmx-misbuero') . '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_transfer_video_empty_markup')) {
	function cmx_carent_transfer_video_empty_markup(string $preview_id): string {
		return '<div id="' . \esc_attr($preview_id) . '" style="display:flex;align-items:center;justify-content:center;min-height:180px;padding:12px;text-align:center;border:1px dashed #c3c4c7;border-radius:6px;color:#646970;background:#f6f7f7;">' . \esc_html__('Video hier ablegen oder anklicken.', 'cmx-misbuero') . '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_render_transfer_upload_field')) {
	function cmx_carent_render_transfer_upload_field(string $prefix, string $label, int $attachment_id): void {
		$image_url = $attachment_id > 0 ? (string) \wp_get_attachment_image_url($attachment_id, 'medium') : '';
		$filename = $attachment_id > 0 ? (string) \basename((string) \get_attached_file($attachment_id)) : '';
		$preview_id = $prefix . '_preview';

		echo '<div class="cmx-carent-transfer-upload" data-prefix="' . \esc_attr($prefix) . '">';
		echo '<label for="' . \esc_attr($prefix . '_file') . '" style="display:block;margin:0 0 6px;font-weight:600;">' . \esc_html($label) . '</label>';
		echo '<input type="hidden" name="' . \esc_attr($prefix . '_attachment_id') . '" id="' . \esc_attr($prefix . '_attachment_id') . '" value="' . \esc_attr((string) $attachment_id) . '">';
		echo '<input type="file" id="' . \esc_attr($prefix . '_file') . '" accept="image/*" style="display:none;">';
		echo '<div id="' . \esc_attr($prefix . '_dropzone') . '" style="display:block;width:100%;cursor:pointer;">';
		if ($image_url !== '') {
			echo '<img id="' . \esc_attr($preview_id) . '" src="' . \esc_url($image_url) . '" alt="" style="display:block;width:100%;height:auto;border:1px solid #dcdcde;border-radius:6px;">';
		} else {
			echo cmx_carent_transfer_upload_empty_markup($preview_id);
		}
		echo '</div>';
		echo '<p id="' . \esc_attr($prefix . '_status') . '" style="margin:8px 0 0;color:#50575e;min-height:18px;">' . \esc_html($filename) . '</p>';
		echo '<p style="margin:8px 0 0;">';
		echo '<button type="button" class="button button-link-delete" id="' . \esc_attr($prefix . '_remove') . '"' . ($attachment_id > 0 ? '' : ' style="display:none;"') . '>' . \esc_html__('Entfernen', 'cmx-misbuero') . '</button>';
		echo '</p>';
		echo '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_render_transfer_video_field')) {
	function cmx_carent_render_transfer_video_field(string $prefix, string $label, int $attachment_id): void {
		$video_url = $attachment_id > 0 ? (string) \wp_get_attachment_url($attachment_id) : '';
		$filename = $attachment_id > 0 ? (string) \basename((string) \get_attached_file($attachment_id)) : '';
		$preview_id = $prefix . '_preview';

		echo '<div class="cmx-carent-transfer-video" data-prefix="' . \esc_attr($prefix) . '">';
		echo '<label for="' . \esc_attr($prefix . '_file') . '" style="display:block;margin:0 0 6px;font-weight:600;">' . \esc_html($label) . '</label>';
		echo '<input type="hidden" name="' . \esc_attr($prefix . '_attachment_id') . '" id="' . \esc_attr($prefix . '_attachment_id') . '" value="' . \esc_attr((string) $attachment_id) . '">';
		echo '<input type="file" id="' . \esc_attr($prefix . '_file') . '" accept="video/*" style="display:none;">';
		echo '<div id="' . \esc_attr($prefix . '_dropzone') . '" style="display:block;width:100%;cursor:pointer;">';
		if ($video_url !== '') {
			echo '<video id="' . \esc_attr($preview_id) . '" controls preload="metadata" src="' . \esc_url($video_url) . '" style="display:block;width:100%;max-width:100%;height:auto;min-height:180px;border:1px solid #dcdcde;border-radius:6px;background:#000;"></video>';
		} else {
			echo cmx_carent_transfer_video_empty_markup($preview_id);
		}
		echo '</div>';
		echo '<p id="' . \esc_attr($prefix . '_status') . '" style="margin:8px 0 0;color:#50575e;min-height:18px;">';
		if ($filename !== '' && $video_url !== '') {
			echo '<a href="' . \esc_url($video_url) . '" target="_blank" rel="noopener noreferrer">' . \esc_html($filename) . '</a>';
		} elseif ($filename !== '') {
			echo \esc_html($filename);
		}
		echo '</p>';
		echo '<p style="margin:8px 0 0;">';
		echo '<button type="button" class="button button-link-delete" id="' . \esc_attr($prefix . '_remove') . '"' . ($attachment_id > 0 ? '' : ' style="display:none;"') . '>' . \esc_html__('Entfernen', 'cmx-misbuero') . '</button>';
		echo '</p>';
		echo '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_carent_transfer_metabox')) {
	function cmx_render_carent_transfer_metabox(\WP_Post $post, array $config): void {
		$box_key = (string) ($config['box_key'] ?? '');
		if ($box_key === '') {
			return;
		}
		$is_uebernahme = $box_key === 'uebernahme';

		$box_id = 'cmx-carent-transfer-box-' . $box_key . '-' . (int) $post->ID;
		$datum_name = 'cmx_carent_' . $box_key . '_datum';
		$datum_label_id = $datum_name . '_label';
		$uhrzeit_name = 'cmx_carent_' . $box_key . '_uhrzeit';
		$uhrzeit_label_id = $uhrzeit_name . '_label';
		$ort_name = 'cmx_carent_' . $box_key . '_ort';
		$ort_label_id = $ort_name . '_label';
		$vermieter_prefix = 'cmx_carent_' . $box_key . '_vermieter';
		$mieter_prefix = 'cmx_carent_' . $box_key . '_mieter';
		$inventory_prefix = 'cmx_carent_' . $box_key . '_bestandsaufnahme';
		$km_stand_uebernahme_id = 'cmx_carent_fahrzeug_km_stand_uebernahme_' . (int) $post->ID;
		$km_stand_rueckgabe_id = 'cmx_carent_fahrzeug_km_stand_rueckgabe_' . (int) $post->ID;
		$km_stand_sync_id = 'cmx_carent_fahrzeug_km_stand_sync_' . (int) $post->ID;

		$datum = (string) \get_post_meta($post->ID, (string) $config['datum_meta'], true);
		$uhrzeit = (string) \get_post_meta($post->ID, (string) $config['uhrzeit_meta'], true);
		$ort = (string) \get_post_meta($post->ID, (string) $config['ort_meta'], true);
		$vermieter_attachment_id = (int) \get_post_meta($post->ID, (string) $config['vermieter_meta'], true);
		$mieter_attachment_id = (int) \get_post_meta($post->ID, (string) $config['mieter_meta'], true);
		$inventory_attachment_id = (int) \get_post_meta($post->ID, (string) ($config['inventory_meta'] ?? CMX_CARENT_BESTANDSAUFNAHME_META), true);
		$artikel_id = \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_META')
			? (int) \get_post_meta($post->ID, CMX_CARENT_FAHRZEUG_META, true)
			: 0;
		$artikel_defaults = \function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_article_meta_defaults')
			? cmx_carent_fahrzeug_article_meta_defaults($artikel_id)
			: ['km_stand_uebernahme' => '', 'km_stand_rueckgabe' => ''];
		$km_stand_uebernahme = \function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_meta_value')
			? cmx_carent_fahrzeug_meta_value($post->ID, CMX_CARENT_FAHRZEUG_KM_STAND_UEBERNAHME_META)
			: \trim((string) \get_post_meta($post->ID, CMX_CARENT_FAHRZEUG_KM_STAND_UEBERNAHME_META, true));
		if ($km_stand_uebernahme === '') {
			$km_stand_uebernahme = (string) ($artikel_defaults['km_stand_uebernahme'] ?? '');
		}
		$km_stand_rueckgabe = \function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_meta_value')
			? cmx_carent_fahrzeug_meta_value($post->ID, CMX_CARENT_FAHRZEUG_KM_STAND_RUECKGABE_META)
			: \trim((string) \get_post_meta($post->ID, CMX_CARENT_FAHRZEUG_KM_STAND_RUECKGABE_META, true));
		if ($km_stand_rueckgabe === '') {
			$km_stand_rueckgabe = (string) ($artikel_defaults['km_stand_rueckgabe'] ?? '');
		}
		$ajax_nonce = (string) \wp_create_nonce('cmx_carent_transfer_upload');
		$ajax_video_nonce = (string) \wp_create_nonce('cmx_carent_transfer_video_upload');
		$ajax_url = (string) \admin_url('admin-ajax.php');

		\wp_nonce_field('cmx_carent_transfer_save', 'cmx_carent_transfer_nonce');

		echo '<style>
		#' . \esc_attr($box_id) . ' .cmx-carent-transfer-stack{display:grid;gap:14px}
		#' . \esc_attr($box_id) . ' .cmx-carent-transfer-number label{display:block;margin:0 0 6px;font-weight:600}
		#' . \esc_attr($box_id) . ' .cmx-carent-transfer-top-row{display:grid;gap:10px;grid-template-columns:minmax(220px,1.8fr) minmax(150px,1fr) minmax(120px,.8fr) minmax(160px,1fr);align-items:end}
		#' . \esc_attr($box_id) . ' .cmx-carent-transfer-upload-row{display:grid;gap:14px;grid-template-columns:minmax(0,1fr) minmax(0,1fr);align-items:start}
		#' . \esc_attr($box_id) . ' .cmx-carent-transfer-datetime-row{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(88px,104px);gap:8px}
		#' . \esc_attr($box_id) . ' .cmx-carent-transfer-datetime-row .cmx-carent-transfer-number{min-width:0}
		#' . \esc_attr($box_id) . ' .cmx-carent-transfer-datetime-row input{width:100%;min-width:0}
		#' . \esc_attr($box_id) . ' .cmx-carent-transfer-inline-row{display:flex;align-items:center;gap:6px}
		#' . \esc_attr($box_id) . ' .cmx-carent-transfer-inline-row input{flex:1 1 auto;min-width:0}
		#' . \esc_attr($box_id) . ' .cmx-carent-transfer-inline-row .button{display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;padding:0 8px}
		#' . \esc_attr($box_id) . ' .cmx-carent-transfer-inline-row .dashicons{width:16px;height:16px;font-size:16px;line-height:16px}
		#' . \esc_attr($box_id) . ' .cmx-carent-transfer-upload p{font-size:12px}
		#' . \esc_attr($box_id) . ' .cmx-carent-transfer-video p{font-size:12px}
		#' . \esc_attr($box_id) . ' .cmx-carent-transfer-fotos-wrap{display:grid;gap:8px}
		#' . \esc_attr($box_id) . ' .cmx-carent-transfer-fotos-wrap > label{display:block;margin:0;font-weight:600}
		#' . \esc_attr($box_id) . ' .cmx-carent-transfer-video-wrap{display:grid;gap:8px}
		#' . \esc_attr($box_id) . ' .cmx-carent-transfer-upload .cmx-carent-transfer-upload{display:block}
		@media (max-width: 1080px){
			#' . \esc_attr($box_id) . ' .cmx-carent-transfer-top-row{grid-template-columns:1fr 1fr}
		}
		@media (max-width: 782px){
			#' . \esc_attr($box_id) . ' .cmx-carent-transfer-top-row,
			#' . \esc_attr($box_id) . ' .cmx-carent-transfer-upload-row{grid-template-columns:1fr}
		}
		</style>';

		echo '<div id="' . \esc_attr($box_id) . '" class="cmx-carent-transfer-box">';
		echo '<div class="cmx-carent-transfer-stack">';

		echo '<div class="cmx-carent-transfer-top-row">';
		echo '<div class="cmx-carent-transfer-number">';
		echo '<label id="' . \esc_attr($ort_label_id) . '" for="' . \esc_attr($ort_name) . '">' . \esc_html__('Ort', 'cmx-misbuero') . '</label>';
		echo '<input type="text" class="widefat" name="' . \esc_attr($ort_name) . '" id="' . \esc_attr($ort_name) . '" value="' . \esc_attr($ort) . '">';
		echo '</div>';
		echo '<div class="cmx-carent-transfer-number">';
		echo '<label id="' . \esc_attr($datum_label_id) . '" for="' . \esc_attr($datum_name) . '">' . \esc_html__('Datum', 'cmx-misbuero') . '</label>';
		echo '<input type="date" class="widefat" name="' . \esc_attr($datum_name) . '" id="' . \esc_attr($datum_name) . '" value="' . \esc_attr($datum) . '">';
		echo '</div>';
		echo '<div class="cmx-carent-transfer-number">';
		echo '<label id="' . \esc_attr($uhrzeit_label_id) . '" for="' . \esc_attr($uhrzeit_name) . '">' . \esc_html__('Uhrzeit', 'cmx-misbuero') . '</label>';
		echo '<input type="time" class="widefat" name="' . \esc_attr($uhrzeit_name) . '" id="' . \esc_attr($uhrzeit_name) . '" value="' . \esc_attr($uhrzeit) . '">';
		echo '</div>';
		echo '<div class="cmx-carent-transfer-number">';
		if ($is_uebernahme) {
			echo '<label for="' . \esc_attr($km_stand_uebernahme_id) . '">KM-Stand Übernahme</label>';
			echo '<input type="number" min="0" step="1" id="' . \esc_attr($km_stand_uebernahme_id) . '" name="cmx_carent_fahrzeug_km_stand_uebernahme" class="widefat" value="' . \esc_attr($km_stand_uebernahme) . '">';
		} else {
			echo '<label for="' . \esc_attr($km_stand_rueckgabe_id) . '">KM-Stand Rückgabe</label>';
			echo '<div class="cmx-carent-transfer-inline-row">';
			echo '<input type="number" min="0" step="1" id="' . \esc_attr($km_stand_rueckgabe_id) . '" name="cmx_carent_fahrzeug_km_stand_rueckgabe" class="widefat" value="' . \esc_attr($km_stand_rueckgabe) . '">';
			echo '<button type="button" class="button" id="' . \esc_attr($km_stand_sync_id) . '" title="KM-Stand in Artikel übertragen" aria-label="KM-Stand in Artikel übertragen"' . ($artikel_id > 0 && $km_stand_rueckgabe !== '' ? '' : ' disabled') . '><span class="dashicons dashicons-dashboard" aria-hidden="true"></span></button>';
			echo '</div>';
		}
		echo '</div>';
		echo '</div>';
		echo '<div class="cmx-carent-transfer-upload-row">';
		cmx_carent_render_transfer_upload_field($vermieter_prefix, (string) \__('Vermieter', 'cmx-misbuero'), $vermieter_attachment_id);
		cmx_carent_render_transfer_upload_field($mieter_prefix, (string) \__('Mieter', 'cmx-misbuero'), $mieter_attachment_id);
		echo '</div>';
		if (\function_exists(__NAMESPACE__ . '\\cmx_render_carent_fotos_section')) {
			echo '<div class="cmx-carent-transfer-fotos-wrap">';
			echo '<label>' . \esc_html__('Fotos', 'cmx-misbuero') . '</label>';
			cmx_render_carent_fotos_section($post, 'cmx-carent-transfer-fotos-' . $box_key . '-' . (int) $post->ID, false, ['section' => $box_key]);
			echo '</div>';
		}
		echo '<div class="cmx-carent-transfer-video-wrap">';
		cmx_carent_render_transfer_video_field(
			$inventory_prefix,
			(string) ($is_uebernahme ? \__('Übernahmevideo', 'cmx-misbuero') : \__('Rückgabevideos', 'cmx-misbuero')),
			$inventory_attachment_id
		);
		echo '</div>';

		echo '</div>';
		echo '</div>';

		echo '<script>
		(function(){
			var root = document.getElementById(' . \wp_json_encode($box_id) . ');
			if (!root || root.dataset.cmxTransferBound === "1") return;
			root.dataset.cmxTransferBound = "1";

			var ajaxUrl = ' . \wp_json_encode($ajax_url) . ';
			var ajaxNonce = ' . \wp_json_encode($ajax_nonce) . ';
			var ajaxVideoNonce = ' . \wp_json_encode($ajax_video_nonce) . ';
			var postId = ' . (int) $post->ID . ';
			var emptyText = ' . \wp_json_encode((string) \__('Foto hier ablegen oder anklicken.', 'cmx-misbuero')) . ';
			var emptyVideoText = ' . \wp_json_encode((string) \__('Video hier ablegen oder anklicken.', 'cmx-misbuero')) . ';
			var ortInput = document.getElementById(' . \wp_json_encode($ort_name) . ');
			var ortLabel = document.getElementById(' . \wp_json_encode($ort_label_id) . ');
			var dateInput = document.getElementById(' . \wp_json_encode($datum_name) . ');
			var dateLabel = document.getElementById(' . \wp_json_encode($datum_label_id) . ');
			var timeInput = document.getElementById(' . \wp_json_encode($uhrzeit_name) . ');
			var timeLabel = document.getElementById(' . \wp_json_encode($uhrzeit_label_id) . ');

			function getTodayValue(){
				var now = new Date();
				var local = new Date(now.getTime() - (now.getTimezoneOffset() * 60000));
				return local.toISOString().slice(0, 10);
			}

			function getCurrentTimeValue(){
				var now = new Date();
				var hours = String(now.getHours()).padStart(2, "0");
				var minutes = String(now.getMinutes()).padStart(2, "0");
				return hours + ":" + minutes;
			}

			function triggerInputEvents(input){
				if (!input) return;
				try {
					input.dispatchEvent(new Event("input", { bubbles: true }));
					input.dispatchEvent(new Event("change", { bubbles: true }));
				} catch (err) {}
			}

			function initUpload(prefix){
				var attachmentInput = document.getElementById(prefix + "_attachment_id");
				var fileInput = document.getElementById(prefix + "_file");
				var dropzone = document.getElementById(prefix + "_dropzone");
				var preview = document.getElementById(prefix + "_preview");
				var status = document.getElementById(prefix + "_status");
				var removeButton = document.getElementById(prefix + "_remove");
				if (!attachmentInput || !fileInput || !dropzone || !preview || !status || !removeButton) return;

				function setIdle(){
					dropzone.style.opacity = "1";
				}

				function setBusy(text){
					status.textContent = text || "Upload läuft...";
					dropzone.style.opacity = ".6";
				}

				function renderEmpty(){
					attachmentInput.value = "";
					preview.outerHTML = "<div id=\"" + prefix + "_preview\" style=\"display:flex;align-items:center;justify-content:center;min-height:140px;padding:12px;text-align:center;border:1px dashed #c3c4c7;border-radius:6px;color:#646970;background:#f6f7f7;\">" + emptyText + "</div>";
					preview = document.getElementById(prefix + "_preview");
					status.textContent = "";
					removeButton.style.display = "none";
					setIdle();
				}

				function renderImage(id, url, label){
					attachmentInput.value = String(id || "");
					preview.outerHTML = "<img id=\"" + prefix + "_preview\" src=\"" + String(url || "").replace(/\"/g, "&quot;") + "\" alt=\"\" style=\"display:block;width:100%;height:auto;border:1px solid #dcdcde;border-radius:6px;\">";
					preview = document.getElementById(prefix + "_preview");
					status.textContent = String(label || "");
					removeButton.style.display = "";
					setIdle();
				}

				function uploadFile(file){
					if (!file) return;
					if (String(file.type || "").indexOf("image/") !== 0) {
						status.textContent = "Bitte nur Bilddateien hochladen.";
						return;
					}

					var data = new FormData();
					data.append("action", "cmx_carent_transfer_upload");
					data.append("nonce", ajaxNonce);
					data.append("post_id", String(postId || 0));
					data.append("file", file);

					setBusy("Upload läuft: " + (file.name || ""));

					fetch(ajaxUrl, {
						method: "POST",
						credentials: "same-origin",
						body: data
					}).then(function(r){
						return r.json();
					}).then(function(json){
						if (!json || !json.success || !json.data) {
							var msg = (json && json.data && json.data.message) ? String(json.data.message) : "Upload fehlgeschlagen.";
							status.textContent = msg;
							setIdle();
							return;
						}
						renderImage(json.data.id || "", json.data.url || "", json.data.label || "");
					}).catch(function(){
						status.textContent = "Upload fehlgeschlagen.";
						setIdle();
					});
				}

				dropzone.addEventListener("click", function(e){
					if (e.target && e.target.id === prefix + "_remove") return;
					fileInput.click();
				});

				fileInput.addEventListener("change", function(){
					if (fileInput.files && fileInput.files[0]) {
						uploadFile(fileInput.files[0]);
					}
					fileInput.value = "";
				});

				dropzone.addEventListener("dragover", function(e){
					e.preventDefault();
					dropzone.style.opacity = ".75";
				});

				dropzone.addEventListener("dragleave", function(e){
					e.preventDefault();
					setIdle();
				});

				dropzone.addEventListener("drop", function(e){
					e.preventDefault();
					setIdle();
					var files = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files : [];
					if (files.length) {
						uploadFile(files[0]);
					}
				});

				removeButton.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					renderEmpty();
				});
			}

			function initVideoUpload(prefix){
				var attachmentInput = document.getElementById(prefix + "_attachment_id");
				var fileInput = document.getElementById(prefix + "_file");
				var dropzone = document.getElementById(prefix + "_dropzone");
				var preview = document.getElementById(prefix + "_preview");
				var status = document.getElementById(prefix + "_status");
				var removeButton = document.getElementById(prefix + "_remove");
				if (!attachmentInput || !fileInput || !dropzone || !preview || !status || !removeButton) return;

				function setIdle(){
					dropzone.style.opacity = "1";
				}

				function setBusy(text){
					status.textContent = text || "Upload läuft...";
					dropzone.style.opacity = ".6";
				}

				function renderStatus(label, fileUrl){
					var safeLabel = String(label || "");
					var safeUrl = String(fileUrl || "");
					if (!safeLabel) {
						status.textContent = "";
						return;
					}
					if (!safeUrl) {
						status.textContent = safeLabel;
						return;
					}
					status.innerHTML = "";
					var link = document.createElement("a");
					link.href = safeUrl;
					link.target = "_blank";
					link.rel = "noopener noreferrer";
					link.textContent = safeLabel;
					status.appendChild(link);
				}

				function renderEmpty(){
					attachmentInput.value = "";
					preview.outerHTML = "<div id=\"" + prefix + "_preview\" style=\"display:flex;align-items:center;justify-content:center;min-height:180px;padding:12px;text-align:center;border:1px dashed #c3c4c7;border-radius:6px;color:#646970;background:#f6f7f7;\">" + emptyVideoText + "</div>";
					preview = document.getElementById(prefix + "_preview");
					status.textContent = "";
					removeButton.style.display = "none";
					setIdle();
				}

				function renderVideo(id, url, label, fileUrl){
					attachmentInput.value = String(id || "");
					preview.outerHTML = "<video id=\"" + prefix + "_preview\" controls preload=\"metadata\" src=\"" + String(url || "").replace(/\"/g, "&quot;") + "\" style=\"display:block;width:100%;max-width:100%;height:auto;min-height:180px;border:1px solid #dcdcde;border-radius:6px;background:#000;\"></video>";
					preview = document.getElementById(prefix + "_preview");
					renderStatus(label, fileUrl);
					removeButton.style.display = "";
					setIdle();
				}

				function uploadFile(file){
					if (!file) return;
					if (String(file.type || "").indexOf("video/") !== 0) {
						status.textContent = "Bitte nur Videodateien hochladen.";
						return;
					}

					var data = new FormData();
					data.append("action", "cmx_carent_transfer_video_upload");
					data.append("nonce", ajaxVideoNonce);
					data.append("post_id", String(postId || 0));
					data.append("file", file);

					setBusy("Upload läuft: " + (file.name || ""));

					fetch(ajaxUrl, {
						method: "POST",
						credentials: "same-origin",
						body: data
					}).then(function(r){
						return r.json();
					}).then(function(json){
						if (!json || !json.success || !json.data) {
							var msg = (json && json.data && json.data.message) ? String(json.data.message) : "Upload fehlgeschlagen.";
							status.textContent = msg;
							setIdle();
							return;
						}
						renderVideo(json.data.id || "", json.data.url || "", json.data.label || "", json.data.file_url || "");
					}).catch(function(){
						status.textContent = "Upload fehlgeschlagen.";
						setIdle();
					});
				}

				dropzone.addEventListener("click", function(e){
					if (e.target && e.target.id === prefix + "_remove") return;
					fileInput.click();
				});

				fileInput.addEventListener("change", function(){
					if (fileInput.files && fileInput.files[0]) {
						uploadFile(fileInput.files[0]);
					}
					fileInput.value = "";
				});

				dropzone.addEventListener("dragover", function(e){
					e.preventDefault();
					dropzone.style.opacity = ".75";
				});

				dropzone.addEventListener("dragleave", function(e){
					e.preventDefault();
					setIdle();
				});

				dropzone.addEventListener("drop", function(e){
					e.preventDefault();
					setIdle();
					var files = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files : [];
					if (files.length) {
						uploadFile(files[0]);
					}
				});

				removeButton.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					renderEmpty();
				});
			}

			initUpload(' . \wp_json_encode($vermieter_prefix) . ');
			initUpload(' . \wp_json_encode($mieter_prefix) . ');
			initVideoUpload(' . \wp_json_encode($inventory_prefix) . ');
			if (ortInput && ortLabel) {
				ortLabel.addEventListener("click", function(e){
					if (typeof window.cmxGetLocation !== "function") return;
					e.preventDefault();
					window.cmxGetLocation(function(location){
						if (!location) return;
						var value = String(location.address || "").trim();
						if (!value && location.lat && location.lon) {
							value = String(location.lat) + ", " + String(location.lon);
						}
						if (!value) return;
						ortInput.value = value;
						triggerInputEvents(ortInput);
						ortInput.focus();
					});
				});
			}
			if (dateInput && dateLabel) {
				dateLabel.addEventListener("click", function(e){
					e.preventDefault();
					dateInput.value = getTodayValue();
					triggerInputEvents(dateInput);
					dateInput.focus();
				});
			}
			if (timeInput && timeLabel) {
				timeLabel.addEventListener("click", function(e){
					e.preventDefault();
					timeInput.value = getCurrentTimeValue();
					triggerInputEvents(timeInput);
					timeInput.focus();
				});
			}
		})();
		</script>';
	}
}

\add_action('add_meta_boxes', function (): void {
	foreach (cmx_carent_transfer_box_configs() as $config) {
		$box_key = (string) ($config['box_key'] ?? '');
		$context = 'normal';
		$priority = $box_key === 'uebernahme' ? 'high' : 'default';

		\add_meta_box(
			(string) $config['id'],
			(string) $config['title'],
			static function (\WP_Post $post) use ($config): void {
				cmx_render_carent_transfer_metabox($post, $config);
			},
			'carent',
			$context,
			$priority
		);
	}
});

\add_action('wp_ajax_cmx_carent_transfer_upload', function (): void {
	if (!\current_user_can('upload_files')) {
		\wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
	}

	$nonce = isset($_POST['nonce']) ? (string) \wp_unslash($_POST['nonce']) : '';
	if (!\wp_verify_nonce($nonce, 'cmx_carent_transfer_upload')) {
		\wp_send_json_error(['message' => 'Sicherheitsprüfung fehlgeschlagen.'], 403);
	}

	$post_id = isset($_POST['post_id']) ? (int) \wp_unslash($_POST['post_id']) : 0;
	if ($post_id > 0 && \get_post_type($post_id) !== 'carent') {
		\wp_send_json_error(['message' => 'Ungültiger Eintrag.'], 400);
	}

	if (empty($_FILES['file']) || !isset($_FILES['file']['tmp_name'])) {
		\wp_send_json_error(['message' => 'Keine Datei empfangen.'], 400);
	}

	$file_type = \wp_check_filetype_and_ext((string) $_FILES['file']['tmp_name'], (string) $_FILES['file']['name']);
	$mime_type = (string) ($file_type['type'] ?? '');
	if (!\str_starts_with($mime_type, 'image/')) {
		\wp_send_json_error(['message' => 'Bitte nur Bilddateien hochladen.'], 400);
	}

	require_once \ABSPATH . 'wp-admin/includes/file.php';
	require_once \ABSPATH . 'wp-admin/includes/media.php';
	require_once \ABSPATH . 'wp-admin/includes/image.php';

	$attachment_id = \media_handle_upload('file', $post_id > 0 ? $post_id : 0);
	if (\is_wp_error($attachment_id)) {
		\wp_send_json_error(['message' => (string) $attachment_id->get_error_message()], 500);
	}

	$image_url = (string) \wp_get_attachment_image_url((int) $attachment_id, 'medium');
	if ($image_url === '') {
		$image_url = (string) \wp_get_attachment_url((int) $attachment_id);
	}

	\wp_send_json_success([
		'id' => (int) $attachment_id,
		'url' => $image_url,
		'label' => (string) \basename((string) \get_attached_file((int) $attachment_id)),
	]);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_transfer_video_save_poster')) {
	function cmx_carent_transfer_video_save_poster(int $video_attachment_id, int $post_id, string $data_uri): int {
		if ($video_attachment_id <= 0 || $data_uri === '') {
			return 0;
		}
		if (!\preg_match('#^data:image/(png|jpe?g|webp);base64,(.+)$#i', $data_uri, $matches)) {
			return 0;
		}

		$extension = \strtolower((string) $matches[1]);
		$extension = $extension === 'jpeg' ? 'jpg' : $extension;
		$binary = \base64_decode((string) $matches[2], true);
		if (!\is_string($binary) || $binary === '') {
			return 0;
		}

		$uploads = \wp_upload_dir();
		$upload_dir = (string) ($uploads['path'] ?? '');
		$upload_url = (string) ($uploads['url'] ?? '');
		if ($upload_dir === '' || $upload_url === '') {
			return 0;
		}

		$base_name = \sanitize_file_name(\pathinfo((string) \get_attached_file($video_attachment_id), \PATHINFO_FILENAME));
		$base_name = $base_name !== '' ? $base_name : ('video-' . $video_attachment_id);
		$filename = \wp_unique_filename($upload_dir, $base_name . '-preview.' . $extension);
		$path = \trailingslashit($upload_dir) . $filename;
		if (\file_put_contents($path, $binary) === false) {
			return 0;
		}

		$mime = (string) \wp_check_filetype($filename)['type'];
		if ($mime === '') {
			$mime = 'image/' . ($extension === 'jpg' ? 'jpeg' : $extension);
		}
		$attachment_id = (int) \wp_insert_attachment([
			'post_mime_type' => $mime,
			'post_title' => \sanitize_text_field(\pathinfo($filename, \PATHINFO_FILENAME)),
			'post_content' => '',
			'post_status' => 'inherit',
		], $path, $post_id);
		if ($attachment_id <= 0) {
			return 0;
		}

		require_once \ABSPATH . 'wp-admin/includes/image.php';
		$metadata = \wp_generate_attachment_metadata($attachment_id, $path);
		if (\is_array($metadata)) {
			\wp_update_attachment_metadata($attachment_id, $metadata);
		}
		\set_post_thumbnail($video_attachment_id, $attachment_id);

		return $attachment_id;
	}
}

\add_action('wp_ajax_cmx_carent_transfer_video_upload', function (): void {
	if (!\current_user_can('upload_files')) {
		\wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
	}

	$nonce = isset($_POST['nonce']) ? (string) \wp_unslash($_POST['nonce']) : '';
	if (!\wp_verify_nonce($nonce, 'cmx_carent_transfer_video_upload')) {
		\wp_send_json_error(['message' => 'Sicherheitsprüfung fehlgeschlagen.'], 403);
	}

	$post_id = isset($_POST['post_id']) ? (int) \wp_unslash($_POST['post_id']) : 0;
	if ($post_id > 0 && \get_post_type($post_id) !== 'carent') {
		\wp_send_json_error(['message' => 'Ungültiger Eintrag.'], 400);
	}

	if (empty($_FILES['file']) || !isset($_FILES['file']['tmp_name'])) {
		\wp_send_json_error(['message' => 'Keine Datei empfangen.'], 400);
	}

	$file_type = \wp_check_filetype_and_ext((string) $_FILES['file']['tmp_name'], (string) $_FILES['file']['name']);
	$mime_type = (string) ($file_type['type'] ?? '');
	if (!\str_starts_with($mime_type, 'video/')) {
		\wp_send_json_error(['message' => 'Bitte nur Videodateien hochladen.'], 400);
	}

	require_once \ABSPATH . 'wp-admin/includes/file.php';
	require_once \ABSPATH . 'wp-admin/includes/media.php';
	require_once \ABSPATH . 'wp-admin/includes/image.php';

	$attachment_id = \media_handle_upload('file', $post_id > 0 ? $post_id : 0);
	if (\is_wp_error($attachment_id)) {
		\wp_send_json_error(['message' => (string) $attachment_id->get_error_message()], 500);
	}

	$file_url = (string) \wp_get_attachment_url((int) $attachment_id);
	$poster_data = isset($_POST['poster_data']) ? (string) \wp_unslash($_POST['poster_data']) : '';
	$poster_id = cmx_carent_transfer_video_save_poster((int) $attachment_id, $post_id > 0 ? $post_id : 0, $poster_data);

	\wp_send_json_success([
		'id' => (int) $attachment_id,
		'url' => $file_url,
		'file_url' => $file_url,
		'label' => (string) \basename((string) \get_attached_file((int) $attachment_id)),
		'poster_id' => $poster_id,
	]);
});

\add_action('save_post_carent', function (int $post_id, \WP_Post $post): void {
	if ($post->post_type !== 'carent') {
		return;
	}
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (!isset($_POST['cmx_carent_transfer_nonce']) || !\wp_verify_nonce((string) $_POST['cmx_carent_transfer_nonce'], 'cmx_carent_transfer_save')) {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

		foreach (cmx_carent_transfer_box_configs() as $config) {
			$box_key = (string) ($config['box_key'] ?? '');
			if ($box_key === '') {
				continue;
			}

			$datum_field = 'cmx_carent_' . $box_key . '_datum';
			$datum_value = isset($_POST[$datum_field]) ? (string) \wp_unslash($_POST[$datum_field]) : '';
			$datum_value = \preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum_value) ? $datum_value : '';
			if ($datum_value === '') {
				\delete_post_meta($post_id, (string) $config['datum_meta']);
			} else {
				\update_post_meta($post_id, (string) $config['datum_meta'], $datum_value);
			}

			$uhrzeit_field = 'cmx_carent_' . $box_key . '_uhrzeit';
			$uhrzeit_value = isset($_POST[$uhrzeit_field]) ? (string) \wp_unslash($_POST[$uhrzeit_field]) : '';
			$uhrzeit_value = \preg_match('/^\d{2}:\d{2}$/', $uhrzeit_value) ? $uhrzeit_value : '';
			if ($uhrzeit_value === '') {
				\delete_post_meta($post_id, (string) $config['uhrzeit_meta']);
			} else {
				\update_post_meta($post_id, (string) $config['uhrzeit_meta'], $uhrzeit_value);
			}

			$ort_field = 'cmx_carent_' . $box_key . '_ort';
			$ort_raw = isset($_POST[$ort_field]) ? (string) \wp_unslash($_POST[$ort_field]) : '';
			$ort_value = \sanitize_text_field($ort_raw);
			if ($ort_value === '') {
				\delete_post_meta($post_id, (string) $config['ort_meta']);
			} else {
				\update_post_meta($post_id, (string) $config['ort_meta'], $ort_value);
			}

		foreach ([
			'vermieter' => (string) $config['vermieter_meta'],
			'mieter' => (string) $config['mieter_meta'],
		] as $field_key => $meta_key) {
			$attachment_field = 'cmx_carent_' . $box_key . '_' . $field_key . '_attachment_id';
			$attachment_id = isset($_POST[$attachment_field]) ? (int) \wp_unslash($_POST[$attachment_field]) : 0;

			if ($attachment_id <= 0) {
				\delete_post_meta($post_id, $meta_key);
				continue;
			}

			$mime = (string) \get_post_mime_type($attachment_id);
			if (!\str_starts_with($mime, 'image/')) {
				continue;
			}

			\update_post_meta($post_id, $meta_key, $attachment_id);
		}

		$inventory_field = 'cmx_carent_' . $box_key . '_bestandsaufnahme_attachment_id';
		$inventory_meta = (string) ($config['inventory_meta'] ?? '');
		$inventory_attachment_id = isset($_POST[$inventory_field]) ? (int) \wp_unslash($_POST[$inventory_field]) : 0;
		if ($inventory_meta !== '') {
			if ($inventory_attachment_id <= 0) {
				\delete_post_meta($post_id, $inventory_meta);
			} else {
				$mime = (string) \get_post_mime_type($inventory_attachment_id);
				if (\str_starts_with($mime, 'video/')) {
					\update_post_meta($post_id, $inventory_meta, $inventory_attachment_id);
				}
			}
		}
	}
}, 10, 2);
