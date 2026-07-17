<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_META', '_cmx_carent_fahrzeug_id');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KENNZEICHEN_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KENNZEICHEN_META', '_cmx_carent_fahrzeug_kennzeichen');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_VARIANT_INDEX_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_VARIANT_INDEX_META', '_cmx_carent_fahrzeug_variant_index');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_STAND_UEBERNAHME_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_STAND_UEBERNAHME_META', '_cmx_carent_fahrzeug_km_stand_uebernahme');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_STAND_RUECKGABE_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_STAND_RUECKGABE_META', '_cmx_carent_fahrzeug_km_stand_rueckgabe');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_BEGRENZUNG_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_BEGRENZUNG_META', '_cmx_carent_fahrzeug_km_begrenzung');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_MEHRPREIS_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_MEHRPREIS_META', '_cmx_carent_fahrzeug_km_mehrpreis');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KASKO_MIN_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KASKO_MIN_META', '_cmx_carent_fahrzeug_kasko_min');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KASKO_MAX_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KASKO_MAX_META', '_cmx_carent_fahrzeug_kasko_max');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_ANZAHL_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_ANZAHL_META', '_cmx_carent_fahrzeug_anzahl');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_MIETPREIS_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_MIETPREIS_META', '_cmx_carent_mietpreis');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_ABRECHNUNG_MEHRKILOMETER_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_ABRECHNUNG_MEHRKILOMETER_META', '_cmx_carent_abrechnung_mehrkilometer');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_ABRECHNUNG_SCHADENKOSTEN_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_ABRECHNUNG_SCHADENKOSTEN_META', '_cmx_carent_abrechnung_schadenkosten');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_post_type')) {
	function cmx_carent_fahrzeug_post_type(): string {
		return \post_type_exists('artikel') ? 'artikel' : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_list_url')) {
	function cmx_carent_fahrzeug_list_url(): string {
		$post_type = cmx_carent_fahrzeug_post_type();
		return (string) \admin_url('edit.php?post_type=' . ($post_type !== '' ? $post_type : 'artikel'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_display_label')) {
	function cmx_carent_fahrzeug_display_label(int $artikel_id): string {
		if ($artikel_id <= 0 || !\get_post_status($artikel_id)) {
			return '';
		}

		$title = \function_exists(__NAMESPACE__ . '\\cmx_normalize_minus_sign')
			? cmx_normalize_minus_sign((string) \get_the_title($artikel_id))
			: (string) \get_the_title($artikel_id);
		$nr = \function_exists(__NAMESPACE__ . '\\cmx_get_artikel_nr')
			? (string) cmx_get_artikel_nr($artikel_id)
			: '';

		return \trim(($nr !== '' ? $nr . ' – ' : '') . $title);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_variant_entries')) {
	function cmx_carent_fahrzeug_variant_entries(int $artikel_id): array {
		if ($artikel_id <= 0 || !\get_post_status($artikel_id) || (string) \get_post_type($artikel_id) !== cmx_carent_fahrzeug_post_type()) {
			return [];
		}
		if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_admin_variant_entries')) {
			return [];
		}
		$entries = (array) cmx_artikel_admin_variant_entries($artikel_id);
		return \array_values(\array_filter($entries, static function ($entry): bool {
			if (!\is_array($entry)) {
				return false;
			}
			return !\function_exists(__NAMESPACE__ . '\\cmx_artikel_variant_entry_is_selectable')
				|| cmx_artikel_variant_entry_is_selectable((array) $entry);
		}));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_variant_entry')) {
	function cmx_carent_fahrzeug_variant_entry(int $artikel_id, int $variant_index): array {
		foreach (cmx_carent_fahrzeug_variant_entries($artikel_id) as $entry) {
			if ((int) ($entry['index'] ?? -1) === $variant_index) {
				return (array) $entry;
			}
		}

		return [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_variant_label')) {
	function cmx_carent_fahrzeug_variant_label(int $artikel_id, int $variant_index, bool $fallback_to_article = true): string {
		$entry = cmx_carent_fahrzeug_variant_entry($artikel_id, $variant_index);
		if ($entry !== []) {
			if (\function_exists(__NAMESPACE__ . '\\cmx_artikel_search_variant_label')) {
				$label = \trim((string) cmx_artikel_search_variant_label($entry));
				if ($label !== '') {
					return $label;
				}
			}

			$label = \trim((string) ($entry['title'] ?? ''));
			if ($label !== '') {
				return $label;
			}
		}

		return $fallback_to_article ? cmx_carent_fahrzeug_display_label($artikel_id) : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_selection_label')) {
	function cmx_carent_fahrzeug_selection_label(int $artikel_id, $variant_index = null): string {
		if ($variant_index !== null && $variant_index !== '' && \is_numeric((string) $variant_index)) {
			return cmx_carent_fahrzeug_variant_label($artikel_id, (int) $variant_index, true);
		}

		return cmx_carent_fahrzeug_display_label($artikel_id);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_post_selection_label')) {
	function cmx_carent_fahrzeug_post_selection_label(int $carent_id): string {
		if ($carent_id <= 0 || !\get_post_status($carent_id)) {
			return '';
		}

		$artikel_id = (int) \get_post_meta($carent_id, CMX_CARENT_FAHRZEUG_META, true);
		if ($artikel_id <= 0) {
			return '';
		}

		$variant_index = \get_post_meta($carent_id, CMX_CARENT_FAHRZEUG_VARIANT_INDEX_META, true);
		return cmx_carent_fahrzeug_selection_label($artikel_id, $variant_index);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_header_url')) {
	function cmx_carent_fahrzeug_header_url(int $carent_id = 0): string {
		$list_url = cmx_carent_fahrzeug_list_url();
		if ($carent_id <= 0) {
			return $list_url;
		}

		$artikel_id = (int) \get_post_meta($carent_id, CMX_CARENT_FAHRZEUG_META, true);
		if ($artikel_id <= 0 || (string) \get_post_type($artikel_id) !== cmx_carent_fahrzeug_post_type()) {
			return $list_url;
		}

		return (string) \admin_url('post.php?post=' . $artikel_id . '&action=edit');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_meta_value')) {
	function cmx_carent_fahrzeug_meta_value(int $post_id, string $meta_key): string {
		return \trim((string) \get_post_meta($post_id, $meta_key, true));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_normalize_int')) {
	function cmx_carent_fahrzeug_normalize_int(mixed $value): string {
		$raw = \trim((string) $value);
		if ($raw === '') {
			return '';
		}

		$number = \function_exists(__NAMESPACE__ . '\\cmx_parse_number')
			? cmx_parse_number($raw)
			: (float) \str_replace(',', '.', $raw);
		if (!\is_finite($number)) {
			return '';
		}

		return (string) \max(0, (int) \round($number));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_article_meta_defaults')) {
	function cmx_carent_fahrzeug_article_meta_defaults(int $artikel_id): array {
		if ($artikel_id <= 0 || !\get_post_status($artikel_id) || (string) \get_post_type($artikel_id) !== cmx_carent_fahrzeug_post_type()) {
			return [
				'kennzeichen' => '',
				'km_stand_uebernahme' => '',
				'km_stand_rueckgabe'  => '',
				'begrenzung'  => '',
				'mehrpreis'   => '',
				'kasko_min'   => '',
				'kasko_max'   => '',
			];
		}

		$kennzeichen_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KENNZEICHEN')
			? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KENNZEICHEN')
			: '_cmx_artikel_carent_kennzeichen';
		$km_stand_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KM_STAND')
			? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KM_STAND')
			: '_cmx_artikel_carent_km_stand';
		$begrenzung_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KM_BEGRENZUNG')
			? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KM_BEGRENZUNG')
			: '_cmx_artikel_carent_km_begrenzung';
		$mehrpreis_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KM_MEHRPREIS')
			? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KM_MEHRPREIS')
			: '_cmx_artikel_carent_km_mehrpreis';
		$kasko_min_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KASKO')
			? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KASKO')
			: '_cmx_artikel_carent_kasko';
		$kasko_max_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KASKO_MAX')
			? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KASKO_MAX')
			: '_cmx_artikel_carent_kasko_max';

		$kennzeichen = \trim((string) \get_post_meta($artikel_id, $kennzeichen_key, true));
		if (\function_exists(__NAMESPACE__ . '\\cmx_artikel_carent_normalize_kennzeichen')) {
			$kennzeichen = cmx_artikel_carent_normalize_kennzeichen($kennzeichen);
		}

		return [
			'kennzeichen' => $kennzeichen,
			'km_stand_uebernahme' => \trim((string) \get_post_meta($artikel_id, $km_stand_key, true)),
			'km_stand_rueckgabe'  => '',
			'begrenzung'  => \trim((string) \get_post_meta($artikel_id, $begrenzung_key, true)),
			'mehrpreis'   => \trim((string) \get_post_meta($artikel_id, $mehrpreis_key, true)),
			'kasko_min'   => \trim((string) \get_post_meta($artikel_id, $kasko_min_key, true)),
			'kasko_max'   => \trim((string) \get_post_meta($artikel_id, $kasko_max_key, true)),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_selection_meta_defaults')) {
	function cmx_carent_fahrzeug_selection_meta_defaults(int $artikel_id, $variant_index = null): array {
		$defaults = cmx_carent_fahrzeug_article_meta_defaults($artikel_id);
		$mietpreis_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VK')
			? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VK')
			: '_cmx_artikel_vk';
		$mietpreis = $artikel_id > 0 ? \trim((string) \get_post_meta($artikel_id, $mietpreis_key, true)) : '';

		if ($variant_index !== null && $variant_index !== '' && \is_numeric((string) $variant_index)) {
			$variant_index = \max(0, (int) $variant_index);
			if (\function_exists(__NAMESPACE__ . '\\cmx_vermietung_vehicle_variant_price')) {
				$variant_mietpreis = (string) cmx_vermietung_vehicle_variant_price($artikel_id, $variant_index);
				if ($variant_mietpreis !== '') {
					$mietpreis = $variant_mietpreis;
				}
			} elseif (\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_variant_entry')) {
				$entry = (array) cmx_carent_fahrzeug_variant_entry($artikel_id, $variant_index);
				$variant_mietpreis = \trim((string) ($entry['vk'] ?? ''));
				if ($variant_mietpreis !== '') {
					$mietpreis = $variant_mietpreis;
				}
			}
		}

		$defaults['anzahl'] = $artikel_id > 0 ? '1' : '';
		$defaults['mietpreis'] = $mietpreis !== '' ? cmx_carent_fahrzeug_normalize_decimal($mietpreis) : '';

		return $defaults;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_normalize_decimal')) {
	function cmx_carent_fahrzeug_normalize_decimal(mixed $value, int $decimals = 2): string {
		$raw = \trim((string) $value);
		if ($raw === '') {
			return '';
		}

		$number = \function_exists(__NAMESPACE__ . '\\cmx_parse_number')
			? cmx_parse_number($raw)
			: (float) \str_replace(',', '.', $raw);
		if (!\is_finite($number)) {
			return '';
		}

		return \number_format(\max(0, $number), \max(0, $decimals), '.', '');
	}
}

\add_action('add_meta_boxes', function (): void {
	$carent_id = 0;
	if (isset($_GET['post'])) {
		$carent_id = (int) $_GET['post'];
	} elseif (isset($_POST['post_ID'])) {
		$carent_id = (int) $_POST['post_ID'];
	}

	$target_url = cmx_carent_fahrzeug_header_url($carent_id);
	$box_title = '<a id="cmx_carent_fahrzeug_box_link" href="' . \esc_url($target_url) . '" target="_blank" rel="noopener noreferrer" onclick="if(window.cmxCarentFahrzeugOpen){return window.cmxCarentFahrzeugOpen(event);}event.stopPropagation();" style="font-size:13px;font-weight:inherit;line-height:inherit;color:#2271b1;text-decoration:none;">' . \esc_html__('Fahrzeug', 'cmx-misbuero') . '</a>';

	\add_meta_box(
		'cmx_carent_fahrzeug_box',
		$box_title,
		__NAMESPACE__ . '\\cmx_render_carent_fahrzeug_metabox',
		'carent',
		'side',
		'default'
	);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_abrechnung_order_last')) {
	function cmx_carent_abrechnung_order_last($value) {
		if (!\is_array($value)) {
			return $value;
		}

		$box_id = 'cmx_carent_abrechnung_box';
		$normal = isset($value['normal']) && \is_string($value['normal'])
			? \array_filter(\array_map('trim', \explode(',', $value['normal'])))
			: [];
		$normal = \array_values(\array_filter($normal, static fn($id): bool => $id !== $box_id));
		$normal[] = $box_id;
		$value['normal'] = \implode(',', $normal);

		return $value;
	}
}

\add_filter('get_user_option_meta-box-order_carent', __NAMESPACE__ . '\\cmx_carent_abrechnung_order_last', 20);

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_carent_fahrzeug_metabox')) {
	function cmx_render_carent_fahrzeug_metabox(\WP_Post $post): void {
		$selected = (int) \get_post_meta($post->ID, CMX_CARENT_FAHRZEUG_META, true);
		$selected_variant_index = \get_post_meta($post->ID, CMX_CARENT_FAHRZEUG_VARIANT_INDEX_META, true);
		$selected_variant_index = $selected_variant_index !== '' && \is_numeric((string) $selected_variant_index) ? (string) \max(0, (int) $selected_variant_index) : '';
		$selected_label = $selected > 0 ? cmx_carent_fahrzeug_post_selection_label($post->ID) : '';
		$artikel_defaults = cmx_carent_fahrzeug_selection_meta_defaults($selected, $selected_variant_index);
		$kennzeichen = cmx_carent_fahrzeug_meta_value($post->ID, CMX_CARENT_FAHRZEUG_KENNZEICHEN_META);
		if ($kennzeichen === '') {
			$kennzeichen = (string) ($artikel_defaults['kennzeichen'] ?? '');
		}
		$km_stand_uebernahme = cmx_carent_fahrzeug_meta_value($post->ID, CMX_CARENT_FAHRZEUG_KM_STAND_UEBERNAHME_META);
		if ($km_stand_uebernahme === '') {
			$km_stand_uebernahme = (string) ($artikel_defaults['km_stand_uebernahme'] ?? '');
		}
		$km_stand_rueckgabe = cmx_carent_fahrzeug_meta_value($post->ID, CMX_CARENT_FAHRZEUG_KM_STAND_RUECKGABE_META);
		if ($km_stand_rueckgabe === '') {
			$km_stand_rueckgabe = (string) ($artikel_defaults['km_stand_rueckgabe'] ?? '');
		}
		$begrenzung = cmx_carent_fahrzeug_meta_value($post->ID, CMX_CARENT_FAHRZEUG_KM_BEGRENZUNG_META);
		if ($begrenzung === '') {
			$begrenzung = (string) ($artikel_defaults['begrenzung'] ?? '');
		}
		$mehrpreis = cmx_carent_fahrzeug_meta_value($post->ID, CMX_CARENT_FAHRZEUG_KM_MEHRPREIS_META);
		if ($mehrpreis === '') {
			$mehrpreis = (string) ($artikel_defaults['mehrpreis'] ?? '');
		}
		$kasko_min = cmx_carent_fahrzeug_meta_value($post->ID, CMX_CARENT_FAHRZEUG_KASKO_MIN_META);
		if ($kasko_min === '') {
			$kasko_min = (string) ($artikel_defaults['kasko_min'] ?? '');
		}
		$kasko_max = cmx_carent_fahrzeug_meta_value($post->ID, CMX_CARENT_FAHRZEUG_KASKO_MAX_META);
		if ($kasko_max === '') {
			$kasko_max = (string) ($artikel_defaults['kasko_max'] ?? '');
		}

		$list_url = cmx_carent_fahrzeug_list_url();
		$edit_prefix = (string) \admin_url('post.php?post=');
		$ajax_url = (string) \admin_url('admin-ajax.php');
		$ajax_nonce = (string) \wp_create_nonce('cmx_carent_fahrzeug_details');
		$sync_nonce = (string) \wp_create_nonce('cmx_carent_fahrzeug_sync_km_stand');
		$post_type = cmx_carent_fahrzeug_post_type();
		$has_artikel = ($post_type !== '') && !empty(\get_posts([
			'post_type' => $post_type,
			'post_status' => 'any',
			'posts_per_page' => 1,
			'fields' => 'ids',
		]));

		$box_id = 'cmx-carent-fahrzeug-box-' . (int) $post->ID;
		$search_id = 'cmx_carent_fahrzeug_search_' . (int) $post->ID;
		$hidden_id = 'cmx_carent_fahrzeug_id_' . (int) $post->ID;
		$hidden_variant_id = 'cmx_carent_fahrzeug_variant_index_' . (int) $post->ID;
		$list_id = 'cmx_carent_fahrzeug_suggest_' . (int) $post->ID;
		$details_id = 'cmx_carent_fahrzeug_details_' . (int) $post->ID;
		$kennzeichen_id = 'cmx_carent_fahrzeug_kennzeichen_' . (int) $post->ID;
		$km_stand_uebernahme_id = 'cmx_carent_fahrzeug_km_stand_uebernahme_' . (int) $post->ID;
		$km_stand_rueckgabe_id = 'cmx_carent_fahrzeug_km_stand_rueckgabe_' . (int) $post->ID;
		$km_stand_sync_id = 'cmx_carent_fahrzeug_km_stand_sync_' . (int) $post->ID;
		$begrenzung_id = 'cmx_carent_fahrzeug_begrenzung_' . (int) $post->ID;
		$mehrpreis_id = 'cmx_carent_fahrzeug_mehrpreis_' . (int) $post->ID;
		$kasko_min_id = 'cmx_carent_fahrzeug_kasko_min_' . (int) $post->ID;
		$kasko_max_id = 'cmx_carent_fahrzeug_kasko_max_' . (int) $post->ID;
		$link_id = 'cmx_carent_fahrzeug_box_link';

		\wp_nonce_field('cmx_carent_fahrzeug_save', 'cmx_carent_fahrzeug_nonce');

		echo '<style>
		#' . \esc_attr('cmx_carent_fahrzeug_box') . ',
		#' . \esc_attr('cmx_carent_fahrzeug_box') . ' .inside,
		#' . \esc_attr($box_id) . '{position:relative;overflow:visible}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-suggest{position:relative;overflow:visible}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-row{display:flex;align-items:center;gap:6px}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-row input[type=search]{flex:1 1 auto;min-width:0}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-inline-row{display:flex;align-items:center;gap:6px}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-inline-row input{flex:1 1 auto;min-width:0}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-inline-row .button{display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;padding:0 8px}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-inline-row .dashicons{width:16px;height:16px;font-size:16px;line-height:16px}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-results{
			position:absolute;
			z-index:100002;
			left:0;
			right:0;
			max-height:240px;
			overflow:auto;
			margin:2px 0 0;
			padding:0;
			border:1px solid #ccd0d4;
			border-radius:4px;
			background:#fff;
			box-shadow:0 10px 24px rgba(0,0,0,.10);
			list-style:none;
		}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-results li{margin:0;padding:6px 8px;cursor:pointer}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-results li.active,
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-results li:hover{background:#e5f3ff}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-results small{display:block;color:#646970}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-details{margin-top:10px;padding-top:10px;border-top:1px solid #e2e4e7}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-details.is-hidden{display:none}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-field{margin-top:8px}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-field:first-child{margin-top:0}
		#' . \esc_attr($box_id) . ' .cmx-carent-fahrzeug-field label{display:block;margin:0 0 4px;font-weight:600}
		#' . \esc_attr($box_id) . ' input[readonly]{
			background:#f6f7f7 !important;
			color:#50575e !important;
			border-color:#dcdcde !important;
			opacity:1 !important;
		}
		</style>';

		echo '<div id="' . \esc_attr($box_id) . '" class="cmx-carent-fahrzeug-box">';
		echo '<div class="cmx-carent-fahrzeug-suggest">';
		echo '<div class="cmx-carent-fahrzeug-row">';
		echo '<input type="search" id="' . \esc_attr($search_id) . '" class="widefat" autocomplete="off" placeholder="' . \esc_attr__('suchen...', 'cmx-misbuero') . '" value="' . \esc_attr($selected_label) . '">';
		echo '<input type="hidden" name="cmx_carent_fahrzeug_id" id="' . \esc_attr($hidden_id) . '" value="' . \esc_attr((string) $selected) . '">';
		echo '<input type="hidden" name="cmx_carent_fahrzeug_variant_index" id="' . \esc_attr($hidden_variant_id) . '" value="' . \esc_attr($selected_variant_index) . '">';
		echo '</div>';
		echo '<ul id="' . \esc_attr($list_id) . '" class="cmx-carent-fahrzeug-results" style="display:none"></ul>';
		echo '<div id="' . \esc_attr($details_id) . '" class="cmx-carent-fahrzeug-details' . ($selected > 0 ? '' : ' is-hidden') . '">';
		echo '<div class="cmx-carent-fahrzeug-field">';
		echo '<label for="' . \esc_attr($kennzeichen_id) . '">Kennzeichen</label>';
		echo '<input type="text" id="' . \esc_attr($kennzeichen_id) . '" name="cmx_carent_fahrzeug_kennzeichen" class="widefat" value="' . \esc_attr($kennzeichen) . '" readonly>';
		echo '</div>';
		echo '<div class="cmx-carent-fahrzeug-field">';
		echo '<label for="' . \esc_attr($km_stand_uebernahme_id) . '">KM Übernahme</label>';
		echo '<input type="number" min="0" step="1" id="' . \esc_attr($km_stand_uebernahme_id) . '" name="cmx_carent_fahrzeug_km_stand_uebernahme" class="widefat" value="' . \esc_attr($km_stand_uebernahme) . '">';
		echo '</div>';
		echo '<div class="cmx-carent-fahrzeug-field">';
		echo '<label for="' . \esc_attr($km_stand_rueckgabe_id) . '">KM Rückgabe</label>';
		echo '<div class="cmx-carent-fahrzeug-inline-row">';
		echo '<input type="number" min="0" step="1" id="' . \esc_attr($km_stand_rueckgabe_id) . '" name="cmx_carent_fahrzeug_km_stand_rueckgabe" class="widefat" value="' . \esc_attr($km_stand_rueckgabe) . '">';
		echo '<button type="button" class="button" id="' . \esc_attr($km_stand_sync_id) . '" title="' . \esc_attr__('KM-Stand am Fahrzeug aktualisieren', 'cmx-misbuero') . '"><span class="dashicons dashicons-update" aria-hidden="true"></span></button>';
		echo '</div>';
		echo '</div>';
		echo '<div class="cmx-carent-fahrzeug-field">';
		echo '<label for="' . \esc_attr($begrenzung_id) . '">Begrenzung</label>';
		echo '<input type="number" min="0" step="1" id="' . \esc_attr($begrenzung_id) . '" name="cmx_carent_fahrzeug_km_begrenzung" class="widefat" value="' . \esc_attr($begrenzung) . '">';
		echo '</div>';
		echo '<div class="cmx-carent-fahrzeug-field">';
		echo '<label for="' . \esc_attr($mehrpreis_id) . '">Mehrpreis</label>';
		echo '<input type="number" min="0" step="0.01" id="' . \esc_attr($mehrpreis_id) . '" name="cmx_carent_fahrzeug_km_mehrpreis" class="widefat" value="' . \esc_attr($mehrpreis) . '">';
		echo '</div>';
		echo '<div class="cmx-carent-fahrzeug-field">';
		echo '<label for="' . \esc_attr($kasko_min_id) . '">Kasko min</label>';
		echo '<input type="number" min="0" step="0.01" id="' . \esc_attr($kasko_min_id) . '" name="cmx_carent_fahrzeug_kasko_min" class="widefat" value="' . \esc_attr($kasko_min) . '">';
		echo '</div>';
		echo '<div class="cmx-carent-fahrzeug-field">';
		echo '<label for="' . \esc_attr($kasko_max_id) . '">Kasko max</label>';
		echo '<input type="number" min="0" step="0.01" id="' . \esc_attr($kasko_max_id) . '" name="cmx_carent_fahrzeug_kasko_max" class="widefat" value="' . \esc_attr($kasko_max) . '">';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		echo '<script>
		(function(){
			function initCarentFahrzeugBox(){
			var root = document.getElementById(' . \wp_json_encode($box_id) . ');
			if (!root || root.dataset.cmxBound === "1") return;

			var headerLink = document.getElementById(' . \wp_json_encode($link_id) . ');
			var searchInput = document.getElementById(' . \wp_json_encode($search_id) . ');
			var hiddenInput = document.getElementById(' . \wp_json_encode($hidden_id) . ');
			var hiddenVariantInput = document.getElementById(' . \wp_json_encode($hidden_variant_id) . ');
			var listEl = document.getElementById(' . \wp_json_encode($list_id) . ');
			var detailsEl = document.getElementById(' . \wp_json_encode($details_id) . ');
			var kennzeichenInput = document.getElementById(' . \wp_json_encode($kennzeichen_id) . ');
			var kmStandUebernahmeInput = document.getElementById(' . \wp_json_encode($km_stand_uebernahme_id) . ');
			var kmStandRueckgabeInput = document.getElementById(' . \wp_json_encode($km_stand_rueckgabe_id) . ');
			var kmStandSyncButton = document.getElementById(' . \wp_json_encode($km_stand_sync_id) . ');
			var begrenzungInput = document.getElementById(' . \wp_json_encode($begrenzung_id) . ');
			var mehrpreisInput = document.getElementById(' . \wp_json_encode($mehrpreis_id) . ');
			var kaskoMinInput = document.getElementById(' . \wp_json_encode($kasko_min_id) . ');
			var kaskoMaxInput = document.getElementById(' . \wp_json_encode($kasko_max_id) . ');
			if (!headerLink || !searchInput || !hiddenInput || !hiddenVariantInput || !listEl || !detailsEl || !kennzeichenInput || !kmStandUebernahmeInput || !kmStandRueckgabeInput || !kmStandSyncButton || !begrenzungInput || !mehrpreisInput || !kaskoMinInput || !kaskoMaxInput) return;
			root.dataset.cmxBound = "1";

			var ajaxUrl = ' . \wp_json_encode($ajax_url) . ';
			var ajaxNonce = ' . \wp_json_encode($ajax_nonce) . ';
			var syncNonce = ' . \wp_json_encode($sync_nonce) . ';
			var listUrl = ' . \wp_json_encode($list_url) . ';
			var editPrefix = ' . \wp_json_encode($edit_prefix) . ';
			var timer = null;
			var active = -1;
			var items = [];

			function selectedArtikelId(){
				var id = parseInt(hiddenInput.value || "0", 10);
				return (isNaN(id) || id <= 0) ? 0 : id;
			}

			function selectedVariantIndex(){
				var raw = String(hiddenVariantInput.value || "").trim();
				if (raw === "") return "";
				var index = parseInt(raw, 10);
				return isNaN(index) || index < 0 ? "" : String(index);
			}

			function targetUrl(){
				var artikelId = selectedArtikelId();
				return artikelId > 0 ? (editPrefix + artikelId + "&action=edit") : listUrl;
			}

			function syncHref(){
				headerLink.href = targetUrl();
			}

			function openCurrent(e){
				if (e) {
					e.preventDefault();
					e.stopPropagation();
				}
				syncHref();
				var href = headerLink.href || listUrl;
				if (!href) return false;
				var w = window.open(href, "_blank", "noopener,noreferrer");
				if (w) { w.opener = null; }
				return false;
			}

			function esc(str){
				return String(str || "").replace(/[&<>"\x27]/g, function(c){
					if (c === "&") return "&amp;";
					if (c === "<") return "&lt;";
					if (c === ">") return "&gt;";
					if (c.charCodeAt(0) === 34) return "&quot;";
					return "&#039;";
				});
			}

			function closeList(){
				listEl.style.display = "none";
				listEl.innerHTML = "";
				active = -1;
				items = [];
			}

			function setDetailsVisibility(visible){
				detailsEl.classList.toggle("is-hidden", !visible);
			}

			function updateKmStandSyncState(){
				var hasArtikel = selectedArtikelId() > 0;
				var hasRueckgabe = (kmStandRueckgabeInput.value || "").trim() !== "";
				kmStandSyncButton.disabled = !(hasArtikel && hasRueckgabe);
			}

			function setFieldValues(data){
				var payload = data || {};
				kennzeichenInput.value = payload.kennzeichen || "";
				kmStandUebernahmeInput.value = payload.km_stand_uebernahme || "";
				kmStandRueckgabeInput.value = payload.km_stand_rueckgabe || "";
				begrenzungInput.value = payload.begrenzung || "";
				mehrpreisInput.value = payload.mehrpreis || "";
				kaskoMinInput.value = payload.kasko_min || "";
				kaskoMaxInput.value = payload.kasko_max || "";
				updateKmStandSyncState();
				try {
					document.dispatchEvent(new CustomEvent("cmxCarentFahrzeugDetailsChanged", { detail: payload }));
				} catch (err) {}
			}

			function clearSelectionFields(){
				hiddenVariantInput.value = "";
				setFieldValues({});
				setDetailsVisibility(false);
			}

			function render(arr){
				items = Array.isArray(arr) ? arr : [];
				if (!items.length) {
					listEl.innerHTML = "<li style=\"color:#646970;cursor:default;\">Keine Artikel gefunden.</li>";
					listEl.style.display = "block";
					active = -1;
					return;
				}
				listEl.innerHTML = items.map(function(it, i){
					var title = it && it.title ? String(it.title) : "";
					var nr = it && it.nr ? String(it.nr) : "";
					var label = it && it.label ? String(it.label) : ((nr ? nr + " – " : "") + title);
					return "<li data-index=\"" + i + "\"><span>" + esc(label) + "</span>" + (title && label !== title ? "<small>" + esc(title) + "</small>" : "") + "</li>";
				}).join("");
				listEl.style.display = "block";
				active = -1;
			}

			function setActive(next){
				if (!items.length) { active = -1; return; }
				if (next < 0) next = items.length - 1;
				if (next >= items.length) next = 0;
				active = next;
				Array.prototype.forEach.call(listEl.children, function(li, idx){
					li.classList.toggle("active", idx === active);
					if (idx === active) {
						try { li.scrollIntoView({ block: "nearest" }); } catch (err) {}
					}
				});
			}

			function formatItemLabel(item){
				var nr = item && item.nr ? String(item.nr) : "";
				var title = item && item.title ? String(item.title) : "";
				var label = item && item.label ? String(item.label) : ((nr ? nr + " – " : "") + title);
				return label || title;
			}

			function loadArtikelDetails(artikelId, variantIndex){
				artikelId = parseInt(artikelId || "0", 10);
				if (!artikelId) {
					clearSelectionFields();
					return;
				}
				var variant = String(variantIndex !== undefined && variantIndex !== null ? variantIndex : selectedVariantIndex()).trim();
				var url = ajaxUrl + "?action=cmx_carent_get_artikel_fahrzeug_meta&_ajax_nonce=" + encodeURIComponent(ajaxNonce) + "&artikel_id=" + encodeURIComponent(String(artikelId)) + "&variant_index=" + encodeURIComponent(variant);
				fetch(url, { credentials: "same-origin" }).then(function(r){
					return r.json();
				}).then(function(json){
					if (!json || !json.success || !json.data) {
						clearSelectionFields();
						return;
					}
					setFieldValues(json.data);
					setDetailsVisibility(true);
				}).catch(function(){
					clearSelectionFields();
				});
			}

			function choose(item){
				hiddenInput.value = item && item.id ? String(item.id) : "";
				hiddenVariantInput.value = item && item.variant_index !== undefined && item.variant_index !== null ? String(item.variant_index) : "";
				searchInput.value = formatItemLabel(item);
				syncHref();
				loadArtikelDetails(item && item.id ? item.id : 0, hiddenVariantInput.value);
				closeList();
				searchInput.focus();
			}

			function search(q){
				var url = ajaxUrl + "?action=cmx_search_artikel&term=" + encodeURIComponent(q || "");
				fetch(url, { credentials: "same-origin" }).then(function(r){
					return r.json();
				}).then(function(json){
					var rows = Array.isArray(json) ? json : [];
					render(rows.map(function(item){
						return {
							id: item && item.value ? item.value : 0,
							title: item && item.title ? item.title : "",
							nr: item && item.nr ? item.nr : "",
							label: item && item.label ? item.label : "",
							variant_index: item && item.variant_index !== undefined && item.variant_index !== null ? item.variant_index : ""
						};
					}));
				}).catch(function(){
					closeList();
				});
			}

			window.cmxCarentFahrzeugOpen = openCurrent;
			headerLink.addEventListener("mousedown", function(e){ e.stopPropagation(); }, true);
			headerLink.addEventListener("click", openCurrent, true);
			headerLink.addEventListener("auxclick", openCurrent, true);

			searchInput.addEventListener("input", function(){
				hiddenInput.value = "";
				hiddenVariantInput.value = "";
				syncHref();
				clearSelectionFields();
				if (timer) clearTimeout(timer);
				var q = (searchInput.value || "").trim();
				if (q.length === 0) {
					search("");
					return;
				}
				if (q.length < 2) {
					closeList();
					return;
				}
				timer = setTimeout(function(){ search(q); }, 200);
			});

			searchInput.addEventListener("focus", function(){
				if (timer) clearTimeout(timer);
				search((searchInput.value || "").trim());
			});

			searchInput.addEventListener("click", function(){
				if (timer) clearTimeout(timer);
				search((searchInput.value || "").trim());
			});

			kmStandRueckgabeInput.addEventListener("input", updateKmStandSyncState);
			kmStandSyncButton.addEventListener("click", function(){
				var artikelId = selectedArtikelId();
				var kmStand = (kmStandRueckgabeInput.value || "").trim();
				if (!artikelId || !kmStand) {
					updateKmStandSyncState();
					return;
				}

				kmStandSyncButton.disabled = true;
				var formData = new FormData();
				formData.append("action", "cmx_carent_sync_artikel_km_stand");
				formData.append("_ajax_nonce", syncNonce);
				formData.append("artikel_id", String(artikelId));
				formData.append("km_stand", kmStand);

				fetch(ajaxUrl, {
					method: "POST",
					credentials: "same-origin",
					body: formData
				}).then(function(r){
					return r.json();
				}).then(function(json){
					if (!json || !json.success || !json.data) {
						updateKmStandSyncState();
						return;
					}
					kmStandRueckgabeInput.value = json.data.km_stand || kmStand;
					updateKmStandSyncState();
				}).catch(function(){
					updateKmStandSyncState();
				});
			});

			searchInput.addEventListener("keydown", function(e){
				if (listEl.style.display !== "block" && (e.key === "ArrowDown" || e.key === "ArrowUp")) return;
				if (e.key === "ArrowDown") {
					e.preventDefault();
					setActive(active + 1);
				} else if (e.key === "ArrowUp") {
					e.preventDefault();
					setActive(active - 1);
				} else if (e.key === "Enter") {
					if (active > -1 && items[active]) {
						e.preventDefault();
						choose(items[active]);
					}
				} else if (e.key === "Escape") {
					closeList();
				}
			});

			listEl.addEventListener("mousedown", function(e){
				var li = e.target && e.target.closest ? e.target.closest("li[data-index]") : null;
				if (!li) return;
				e.preventDefault();
				var index = parseInt(li.getAttribute("data-index") || "-1", 10);
				if (!isNaN(index) && items[index]) {
					choose(items[index]);
				}
			});

			listEl.addEventListener("mousemove", function(e){
				var li = e.target && e.target.closest ? e.target.closest("li[data-index]") : null;
				if (!li) return;
				var index = parseInt(li.getAttribute("data-index") || "-1", 10);
				if (!isNaN(index)) setActive(index);
			});

			document.addEventListener("click", function(e){
				if (e.target === searchInput || listEl.contains(e.target)) return;
				closeList();
			});

			syncHref();
			setDetailsVisibility(selectedArtikelId() > 0);
			updateKmStandSyncState();
			}

			if (document.readyState === "loading") {
				document.addEventListener("DOMContentLoaded", initCarentFahrzeugBox, { once: true });
			} else {
				initCarentFahrzeugBox();
			}
		})();
		</script>';

		if (!$has_artikel) {
			$new_url = (string) \admin_url('post-new.php?post_type=artikel');
			$anlegen_link = '<a href="' . \esc_url($new_url) . '" target="_blank" rel="noopener noreferrer">' . \esc_html__('anlegen', 'cmx-misbuero') . '</a>';
			$hint_html = \sprintf(\__('Keine Artikel - zuerst einen %s.', 'cmx-misbuero'), $anlegen_link);
			echo '<p style="margin-top:8px;color:#666;">' . \wp_kses($hint_html, [
				'a' => [
					'href' => [],
					'target' => [],
					'rel' => [],
				],
			]) . '</p>';
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_abrechnung_parse_number')) {
	function cmx_carent_abrechnung_parse_number(mixed $value): float {
		if (\function_exists(__NAMESPACE__ . '\\cmx_parse_number')) {
			return (float) cmx_parse_number((string) $value);
		}

		$raw = \trim((string) $value);
		if ($raw === '') {
			return 0.0;
		}

		$raw = \str_replace(["'", ' '], '', $raw);
		$raw = \str_replace(',', '.', $raw);

		return \is_numeric($raw) ? (float) $raw : 0.0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_abrechnung_money')) {
	function cmx_carent_abrechnung_money(mixed $value): string {
		return \number_format(\max(0.0, cmx_carent_abrechnung_parse_number($value)), 2, '.', '');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_abrechnung_display_money')) {
	function cmx_carent_abrechnung_display_money(mixed $value): string {
		return \number_format(\max(0.0, cmx_carent_abrechnung_parse_number($value)), 2, ',', "'");
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_abrechnung_damage_total')) {
	function cmx_carent_abrechnung_damage_total(int $post_id): string {
		$stored = \trim((string) \get_post_meta($post_id, CMX_CARENT_ABRECHNUNG_SCHADENKOSTEN_META, true));
		if ($stored !== '') {
			return cmx_carent_abrechnung_money($stored);
		}

		$rows = \function_exists(__NAMESPACE__ . '\\cmx_carent_schadenprotokoll_rows')
			? (array) cmx_carent_schadenprotokoll_rows($post_id)
			: [];
		$total = 0.0;
		foreach ($rows as $row) {
			$row = (array) $row;
			$total += cmx_carent_abrechnung_parse_number((string) ($row['kosten'] ?? ''));
		}

		return $total > 0 ? cmx_carent_abrechnung_money((string) $total) : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_abrechnung_unit_label')) {
	function cmx_carent_abrechnung_unit_label(int $artikel_id, $variant_index): string {
		if ($artikel_id > 0 && $variant_index !== '' && \is_numeric((string) $variant_index) && \function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_variant_entry')) {
			$entry = (array) cmx_carent_fahrzeug_variant_entry($artikel_id, (int) $variant_index);
			$unit = \trim((string) ($entry['einheit'] ?? ''));
			if ($unit !== '') {
				return $unit;
			}
		}

		return 'Tag';
	}
}

\add_action('add_meta_boxes', function (): void {
	\add_meta_box(
		'cmx_carent_abrechnung_box',
		\__('Abrechnung', 'cmx-misbuero'),
		__NAMESPACE__ . '\\cmx_render_carent_abrechnung_metabox',
		'carent',
		'normal',
		'low'
	);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_carent_abrechnung_metabox')) {
	function cmx_render_carent_abrechnung_metabox(\WP_Post $post): void {
		$post_id = (int) $post->ID;
		$artikel_id = (int) \get_post_meta($post_id, CMX_CARENT_FAHRZEUG_META, true);
		$variant_index = \get_post_meta($post_id, CMX_CARENT_FAHRZEUG_VARIANT_INDEX_META, true);
		$variant_index = $variant_index !== '' && \is_numeric((string) $variant_index) ? (string) \max(0, (int) $variant_index) : '';
		$defaults = cmx_carent_fahrzeug_selection_meta_defaults($artikel_id, $variant_index);

		$mietdauer = \trim((string) \get_post_meta($post_id, CMX_CARENT_FAHRZEUG_ANZAHL_META, true));
		if ($mietdauer === '') {
			$mietdauer = (string) ($defaults['anzahl'] ?? '');
		}
		$mietpreis = \trim((string) \get_post_meta($post_id, CMX_CARENT_MIETPREIS_META, true));
		if ($mietpreis === '') {
			$mietpreis = (string) ($defaults['mietpreis'] ?? '');
		}
		$mehrkilometer = \trim((string) \get_post_meta($post_id, CMX_CARENT_ABRECHNUNG_MEHRKILOMETER_META, true));
		if ($mehrkilometer === '') {
			$mehrkilometer = '0';
		}
		$mehrpreis = \trim((string) \get_post_meta($post_id, CMX_CARENT_FAHRZEUG_KM_MEHRPREIS_META, true));
		if ($mehrpreis === '') {
			$mehrpreis = (string) ($defaults['mehrpreis'] ?? '');
		}
		$schadenkosten = cmx_carent_abrechnung_damage_total($post_id);
		if ($schadenkosten === '') {
			$schadenkosten = '0.00';
		}

		$mietdauer_total = \round(cmx_carent_abrechnung_parse_number($mietdauer) * cmx_carent_abrechnung_parse_number($mietpreis), 0);
		$mehrkilometer_total = cmx_carent_abrechnung_parse_number($mehrkilometer) * cmx_carent_abrechnung_parse_number($mehrpreis);
		$gesamt = $mietdauer_total + $mehrkilometer_total + cmx_carent_abrechnung_parse_number($schadenkosten);
		$unit_label = cmx_carent_abrechnung_unit_label($artikel_id, $variant_index);
		$show_schadenkosten = cmx_carent_abrechnung_parse_number($schadenkosten) > 0;

		\wp_nonce_field('cmx_carent_abrechnung_save', 'cmx_carent_abrechnung_nonce');
		?>
		<style>
			#cmx-carent-abrechnung{overflow-x:auto}
			#cmx-carent-abrechnung table{margin:0}
			#cmx-carent-abrechnung th,#cmx-carent-abrechnung td{vertical-align:middle}
			#cmx-carent-abrechnung .num,#cmx-carent-abrechnung .money{text-align:right}
			#cmx-carent-abrechnung input{width:110px;text-align:right}
			#cmx-carent-abrechnung .cmx-abrechnung-total-label{text-align:right;font-weight:600}
			#cmx-carent-abrechnung .cmx-abrechnung-total-value{font-weight:600;text-align:right}
			#cmx-carent-abrechnung .cmx-abrechnung-unit{display:inline-block;min-width:42px;margin-left:6px;color:#50575e;text-align:left}
		</style>
		<div id="cmx-carent-abrechnung">
			<table class="widefat striped" role="presentation">
				<thead>
					<tr>
						<th><?php echo \esc_html__('Position', 'cmx-misbuero'); ?></th>
						<th class="num"><?php echo \esc_html__('Menge', 'cmx-misbuero'); ?></th>
						<th class="num"><?php echo \esc_html__('Einheitspreis', 'cmx-misbuero'); ?></th>
						<th class="money"><?php echo \esc_html__('Total', 'cmx-misbuero'); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php echo \esc_html__('Mietdauer', 'cmx-misbuero'); ?></td>
						<td class="num"><input type="number" min="0" step="1" name="cmx_carent_abrechnung_mietdauer" class="cmx-abrechnung-input" data-role="mietdauer" value="<?php echo \esc_attr($mietdauer); ?>"><span class="cmx-abrechnung-unit"><?php echo \esc_html($unit_label); ?></span></td>
						<td class="num"><input type="number" min="0" step="0.01" name="cmx_carent_abrechnung_mietpreis" class="cmx-abrechnung-input" data-role="mietpreis" value="<?php echo \esc_attr($mietpreis); ?>"> CHF</td>
						<td class="money"><span data-total="mietdauer"><?php echo \esc_html(cmx_carent_abrechnung_display_money((string) $mietdauer_total)); ?></span> CHF</td>
					</tr>
					<tr>
						<td><?php echo \esc_html__('Mehrkilometer', 'cmx-misbuero'); ?></td>
						<td class="num"><input type="number" min="0" step="1" name="cmx_carent_abrechnung_mehrkilometer" class="cmx-abrechnung-input" data-role="mehrkilometer" value="<?php echo \esc_attr($mehrkilometer); ?>"><span class="cmx-abrechnung-unit">km</span></td>
						<td class="num"><input type="number" min="0" step="0.01" name="cmx_carent_abrechnung_mehrkilometer_preis" class="cmx-abrechnung-input" data-role="mehrpreis" value="<?php echo \esc_attr($mehrpreis); ?>"> CHF / km</td>
						<td class="money"><span data-total="mehrkilometer"><?php echo \esc_html(cmx_carent_abrechnung_display_money((string) $mehrkilometer_total)); ?></span> CHF</td>
					</tr>
					<tr data-row="schadenkosten"<?php echo $show_schadenkosten ? '' : ' style="display:none"'; ?>>
						<td><?php echo \esc_html__('Zusatzkosten (Schaden)', 'cmx-misbuero'); ?></td>
						<td class="num">-</td>
						<td class="num">-</td>
						<td class="money"><input type="number" min="0" step="0.01" name="cmx_carent_abrechnung_schadenkosten" class="cmx-abrechnung-input" data-role="schadenkosten" value="<?php echo \esc_attr($schadenkosten); ?>"> CHF</td>
					</tr>
					<tr>
						<td colspan="3" class="cmx-abrechnung-total-label"><?php echo \esc_html__('TOTAL', 'cmx-misbuero'); ?></td>
						<td class="cmx-abrechnung-total-value"><span data-total="gesamt"><?php echo \esc_html(cmx_carent_abrechnung_display_money((string) $gesamt)); ?></span> CHF</td>
					</tr>
				</tbody>
			</table>
		</div>
		<script>
		(function(){
			var root = document.getElementById("cmx-carent-abrechnung");
			if (!root) return;
			function parse(value){
				var raw = String(value || "").replace(/'/g, "").replace(/\s/g, "").replace(",", ".");
				var number = parseFloat(raw);
				return isNaN(number) || number < 0 ? 0 : number;
			}
			function money(value){
				return parse(value).toFixed(2).replace(".", ",");
			}
			function field(role){
				return root.querySelector("[data-role=\"" + role + "\"]");
			}
			function output(name, value){
				var target = root.querySelector("[data-total=\"" + name + "\"]");
				if (target) target.textContent = money(value);
			}
			function toggleDamageRow(value){
				var row = root.querySelector("[data-row=\"schadenkosten\"]");
				if (row) row.style.display = parse(value) > 0 ? "" : "none";
			}
			function recalc(){
				var mietdauer = parse(field("mietdauer") && field("mietdauer").value);
				var mietpreis = parse(field("mietpreis") && field("mietpreis").value);
				var mehrkilometer = parse(field("mehrkilometer") && field("mehrkilometer").value);
				var mehrpreis = parse(field("mehrpreis") && field("mehrpreis").value);
				var schadenkosten = parse(field("schadenkosten") && field("schadenkosten").value);
				var mietdauerTotal = Math.round(mietdauer * mietpreis);
				var mehrkilometerTotal = mehrkilometer * mehrpreis;
				toggleDamageRow(schadenkosten);
				output("mietdauer", mietdauerTotal);
				output("mehrkilometer", mehrkilometerTotal);
				output("gesamt", mietdauerTotal + mehrkilometerTotal + schadenkosten);
			}
			root.querySelectorAll(".cmx-abrechnung-input").forEach(function(input){
				input.addEventListener("input", recalc);
			});
			document.addEventListener("cmxCarentFahrzeugDetailsChanged", function(event){
				var data = event && event.detail ? event.detail : {};
				if (data.anzahl !== undefined && field("mietdauer")) {
					field("mietdauer").value = data.anzahl || "";
				}
				if (data.mietpreis !== undefined && field("mietpreis")) {
					field("mietpreis").value = data.mietpreis || "";
				}
				if (data.mehrpreis !== undefined && field("mehrpreis")) {
					field("mehrpreis").value = data.mehrpreis || "";
				}
				recalc();
			});
		})();
		</script>
		<?php
	}
}

\add_action('wp_ajax_cmx_carent_get_artikel_fahrzeug_meta', function (): void {
	if (!\current_user_can('edit_posts')) {
		\wp_send_json_error(['msg' => 'forbidden'], 403);
	}
	if (!isset($_GET['_ajax_nonce']) || !\wp_verify_nonce((string) \wp_unslash($_GET['_ajax_nonce']), 'cmx_carent_fahrzeug_details')) {
		\wp_send_json_error(['msg' => 'bad_nonce'], 403);
	}

	$artikel_id = isset($_GET['artikel_id']) ? (int) \wp_unslash($_GET['artikel_id']) : 0;
	$variant_index = isset($_GET['variant_index']) && $_GET['variant_index'] !== '' && \is_numeric((string) \wp_unslash($_GET['variant_index']))
		? \max(0, (int) \wp_unslash($_GET['variant_index']))
		: '';
	\wp_send_json_success(cmx_carent_fahrzeug_selection_meta_defaults($artikel_id, $variant_index));
});

\add_action('wp_ajax_cmx_carent_sync_artikel_km_stand', function (): void {
	if (!\current_user_can('edit_posts')) {
		\wp_send_json_error(['msg' => 'forbidden'], 403);
	}
	if (!isset($_POST['_ajax_nonce']) || !\wp_verify_nonce((string) \wp_unslash($_POST['_ajax_nonce']), 'cmx_carent_fahrzeug_sync_km_stand')) {
		\wp_send_json_error(['msg' => 'bad_nonce'], 403);
	}

	$artikel_id = isset($_POST['artikel_id']) ? (int) \wp_unslash($_POST['artikel_id']) : 0;
	if ($artikel_id <= 0 || (string) \get_post_type($artikel_id) !== cmx_carent_fahrzeug_post_type() || !\get_post_status($artikel_id) || !\current_user_can('edit_post', $artikel_id)) {
		\wp_send_json_error(['msg' => 'bad_artikel'], 400);
	}

	$km_stand = cmx_carent_fahrzeug_normalize_int(\wp_unslash($_POST['km_stand'] ?? ''));
	if ($km_stand === '') {
		\wp_send_json_error(['msg' => 'empty_km_stand'], 400);
	}

	$artikel_km_stand_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KM_STAND')
		? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KM_STAND')
		: '_cmx_artikel_carent_km_stand';
	\update_post_meta($artikel_id, $artikel_km_stand_key, $km_stand);

	\wp_send_json_success([
		'km_stand' => $km_stand,
	]);
});

\add_action('save_post_carent', function (int $post_id, \WP_Post $post, bool $update): void {
	unset($update);

	if (!isset($_POST['cmx_carent_fahrzeug_nonce']) || !\wp_verify_nonce((string) \wp_unslash($_POST['cmx_carent_fahrzeug_nonce']), 'cmx_carent_fahrzeug_save')) {
		return;
	}
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) {
		return;
	}
	if ((string) $post->post_type !== 'carent') {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

	$previous_artikel_id = (int) \get_post_meta($post_id, CMX_CARENT_FAHRZEUG_META, true);
	$previous_variant_index = \get_post_meta($post_id, CMX_CARENT_FAHRZEUG_VARIANT_INDEX_META, true);
	$previous_variant_index = $previous_variant_index !== '' && \is_numeric((string) $previous_variant_index) ? \max(0, (int) $previous_variant_index) : '';
	$artikel_id = isset($_POST['cmx_carent_fahrzeug_id']) ? (int) \wp_unslash($_POST['cmx_carent_fahrzeug_id']) : 0;
	if ($artikel_id <= 0 || (string) \get_post_type($artikel_id) !== cmx_carent_fahrzeug_post_type() || !\get_post_status($artikel_id)) {
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_META);
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_KENNZEICHEN_META);
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_VARIANT_INDEX_META);
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_KM_STAND_UEBERNAHME_META);
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_KM_STAND_RUECKGABE_META);
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_KM_BEGRENZUNG_META);
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_KM_MEHRPREIS_META);
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_KASKO_MIN_META);
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_KASKO_MAX_META);
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_ANZAHL_META);
		\delete_post_meta($post_id, CMX_CARENT_MIETPREIS_META);
		return;
	}

	$variant_index = isset($_POST['cmx_carent_fahrzeug_variant_index']) && $_POST['cmx_carent_fahrzeug_variant_index'] !== '' && \is_numeric((string) \wp_unslash($_POST['cmx_carent_fahrzeug_variant_index']))
		? \max(0, (int) \wp_unslash($_POST['cmx_carent_fahrzeug_variant_index']))
		: '';
	$variant_entries = cmx_carent_fahrzeug_variant_entries($artikel_id);
	if ($variant_entries !== [] && $variant_index !== '' && cmx_carent_fahrzeug_variant_entry($artikel_id, (int) $variant_index) === []) {
		$variant_index = '';
	}
	if ($variant_entries === []) {
		$variant_index = '';
	}
	$selection_changed = $previous_artikel_id !== $artikel_id || (string) $previous_variant_index !== (string) $variant_index;
	$selection_defaults = cmx_carent_fahrzeug_selection_meta_defaults($artikel_id, $variant_index);

	\update_post_meta($post_id, CMX_CARENT_FAHRZEUG_META, $artikel_id);
	if ($variant_index === '') {
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_VARIANT_INDEX_META);
	} else {
		\update_post_meta($post_id, CMX_CARENT_FAHRZEUG_VARIANT_INDEX_META, (int) $variant_index);
	}
	if ($selection_changed) {
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_ANZAHL_META);
		\delete_post_meta($post_id, CMX_CARENT_MIETPREIS_META);
	}

	$kennzeichen = isset($_POST['cmx_carent_fahrzeug_kennzeichen'])
		? \trim((string) \wp_unslash($_POST['cmx_carent_fahrzeug_kennzeichen']))
		: '';
	if (\function_exists(__NAMESPACE__ . '\\cmx_artikel_carent_normalize_kennzeichen')) {
		$kennzeichen = \trim((string) cmx_artikel_carent_normalize_kennzeichen($kennzeichen));
	}
	if ($kennzeichen === '') {
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_KENNZEICHEN_META);
	} else {
		\update_post_meta($post_id, CMX_CARENT_FAHRZEUG_KENNZEICHEN_META, $kennzeichen);
	}

	$km_stand_uebernahme = cmx_carent_fahrzeug_normalize_int(\wp_unslash($_POST['cmx_carent_fahrzeug_km_stand_uebernahme'] ?? ''));
	$km_stand_rueckgabe = cmx_carent_fahrzeug_normalize_int(\wp_unslash($_POST['cmx_carent_fahrzeug_km_stand_rueckgabe'] ?? ''));
	$begrenzung_raw = \trim((string) \wp_unslash($_POST['cmx_carent_fahrzeug_km_begrenzung'] ?? ''));
	$begrenzung = $begrenzung_raw === ''
		? ''
		: (string) \max(0, (int) \round(\function_exists(__NAMESPACE__ . '\\cmx_parse_number') ? cmx_parse_number($begrenzung_raw) : (float) \str_replace(',', '.', $begrenzung_raw)));
	$mehrpreis = cmx_carent_fahrzeug_normalize_decimal(\wp_unslash($_POST['cmx_carent_fahrzeug_km_mehrpreis'] ?? ''));
	$kasko_min = cmx_carent_fahrzeug_normalize_decimal(\wp_unslash($_POST['cmx_carent_fahrzeug_kasko_min'] ?? ''));
	$kasko_max = cmx_carent_fahrzeug_normalize_decimal(\wp_unslash($_POST['cmx_carent_fahrzeug_kasko_max'] ?? ''));

	if ($km_stand_uebernahme === '') {
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_KM_STAND_UEBERNAHME_META);
	} else {
		\update_post_meta($post_id, CMX_CARENT_FAHRZEUG_KM_STAND_UEBERNAHME_META, $km_stand_uebernahme);
	}
	if ($km_stand_rueckgabe === '') {
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_KM_STAND_RUECKGABE_META);
	} else {
		\update_post_meta($post_id, CMX_CARENT_FAHRZEUG_KM_STAND_RUECKGABE_META, $km_stand_rueckgabe);
	}

	if ($begrenzung === '') {
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_KM_BEGRENZUNG_META);
	} else {
		\update_post_meta($post_id, CMX_CARENT_FAHRZEUG_KM_BEGRENZUNG_META, $begrenzung);
	}
	if ($mehrpreis === '') {
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_KM_MEHRPREIS_META);
	} else {
		\update_post_meta($post_id, CMX_CARENT_FAHRZEUG_KM_MEHRPREIS_META, $mehrpreis);
	}
	if ($kasko_min === '') {
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_KASKO_MIN_META);
	} else {
		\update_post_meta($post_id, CMX_CARENT_FAHRZEUG_KASKO_MIN_META, $kasko_min);
	}
	if ($kasko_max === '') {
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_KASKO_MAX_META);
	} else {
		\update_post_meta($post_id, CMX_CARENT_FAHRZEUG_KASKO_MAX_META, $kasko_max);
	}

	if ($selection_changed) {
		$anzahl = cmx_carent_fahrzeug_normalize_int($selection_defaults['anzahl'] ?? '');
		$mietpreis = cmx_carent_fahrzeug_normalize_decimal($selection_defaults['mietpreis'] ?? '');
		if ($anzahl !== '') {
			\update_post_meta($post_id, CMX_CARENT_FAHRZEUG_ANZAHL_META, $anzahl);
		}
		if ($mietpreis !== '') {
			\update_post_meta($post_id, CMX_CARENT_MIETPREIS_META, $mietpreis);
		}
	}
}, 20, 3);

\add_action('save_post_carent', function (int $post_id, \WP_Post $post, bool $update): void {
	unset($update);

	if (!isset($_POST['cmx_carent_abrechnung_nonce']) || !\wp_verify_nonce((string) \wp_unslash($_POST['cmx_carent_abrechnung_nonce']), 'cmx_carent_abrechnung_save')) {
		return;
	}
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) {
		return;
	}
	if ((string) $post->post_type !== 'carent') {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

	$mietdauer = cmx_carent_fahrzeug_normalize_int(\wp_unslash($_POST['cmx_carent_abrechnung_mietdauer'] ?? ''));
	$mietpreis = cmx_carent_fahrzeug_normalize_decimal(\wp_unslash($_POST['cmx_carent_abrechnung_mietpreis'] ?? ''));
	$mehrkilometer = cmx_carent_fahrzeug_normalize_int(\wp_unslash($_POST['cmx_carent_abrechnung_mehrkilometer'] ?? ''));
	$mehrpreis = cmx_carent_fahrzeug_normalize_decimal(\wp_unslash($_POST['cmx_carent_abrechnung_mehrkilometer_preis'] ?? ''));
	$schadenkosten = cmx_carent_fahrzeug_normalize_decimal(\wp_unslash($_POST['cmx_carent_abrechnung_schadenkosten'] ?? ''));

	if ($mietdauer === '') {
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_ANZAHL_META);
	} else {
		\update_post_meta($post_id, CMX_CARENT_FAHRZEUG_ANZAHL_META, $mietdauer);
	}
	if ($mietpreis === '') {
		\delete_post_meta($post_id, CMX_CARENT_MIETPREIS_META);
	} else {
		\update_post_meta($post_id, CMX_CARENT_MIETPREIS_META, $mietpreis);
	}
	if ($mehrkilometer === '') {
		\delete_post_meta($post_id, CMX_CARENT_ABRECHNUNG_MEHRKILOMETER_META);
	} else {
		\update_post_meta($post_id, CMX_CARENT_ABRECHNUNG_MEHRKILOMETER_META, $mehrkilometer);
	}
	if ($mehrpreis === '') {
		\delete_post_meta($post_id, CMX_CARENT_FAHRZEUG_KM_MEHRPREIS_META);
	} else {
		\update_post_meta($post_id, CMX_CARENT_FAHRZEUG_KM_MEHRPREIS_META, $mehrpreis);
	}
	if ($schadenkosten === '') {
		\delete_post_meta($post_id, CMX_CARENT_ABRECHNUNG_SCHADENKOSTEN_META);
	} else {
		\update_post_meta($post_id, CMX_CARENT_ABRECHNUNG_SCHADENKOSTEN_META, $schadenkosten);
	}
}, 30, 3);
