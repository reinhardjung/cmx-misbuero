<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/* --------------------------------------------------------------------------
 * Admin-Bar (Link auf Support-Tab)
 * -------------------------------------------------------------------------- */

function cmx65_is_home_request_path(): bool {
	$request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
	$request_path = (string) \parse_url($request_uri, \PHP_URL_PATH);
	if ($request_path === '') {
		$request_path = '/';
	}
	$request_path = '/' . \ltrim($request_path, '/');
	$request_path = \rtrim($request_path, '/');
	if ($request_path === '') {
		$request_path = '/';
	}

	$home_path = (string) \parse_url(\home_url('/'), \PHP_URL_PATH);
	if ($home_path === '') {
		$home_path = '/';
	}
	$home_path = '/' . \ltrim($home_path, '/');
	$home_path = \rtrim($home_path, '/');
	if ($home_path === '') {
		$home_path = '/';
	}

	return $request_path === $home_path;
}

add_filter('show_admin_bar', __NAMESPACE__ . '\\cmx65_filter_show_adminbar_on_instance_home', 50);
function cmx65_filter_show_adminbar_on_instance_home($show): bool {
	if (!(bool) $show) {
		return false;
	}
	if (\is_admin() || \wp_doing_ajax()) {
		return (bool) $show;
	}
	if (!\is_user_logged_in()) {
		return (bool) $show;
	}
	if (cmx65_is_home_request_path()) {
		return false;
	}
	return (bool) $show;
}

function cmx65_is_instance_home_request(): bool {
	if (\is_admin() || \wp_doing_ajax()) {
		return false;
	}
	if (!\is_user_logged_in()) {
		return false;
	}
	return (\is_front_page() || \is_home() || cmx65_is_home_request_path());
}

if (!\function_exists(__NAMESPACE__ . '\\cmx65_adminbar_fallback_avatar_url')) {
	function cmx65_adminbar_fallback_avatar_url(): string {
		return (string) \plugins_url('assets/login.png', \dirname(__DIR__, 2) . '/cmx-misbuero.php');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx65_adminbar_fallback_avatar_markup')) {
	function cmx65_adminbar_fallback_avatar_markup(int $size, string $fallback_url): string {
		$size = $size > 0 ? $size : 26;
		return '<img alt="" src="' . \esc_url($fallback_url) . '" class="avatar avatar-' . $size . ' photo cmx-adminbar-fallback-avatar" height="' . $size . '" width="' . $size . '" decoding="async" loading="lazy" />';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx65_adminbar_force_avatar_in_title')) {
	function cmx65_adminbar_force_avatar_in_title(string $title, int $size, string $fallback_url): string {
		$fallback_img = cmx65_adminbar_fallback_avatar_markup($size, $fallback_url);
		if (\preg_match('/<img\b[^>]*>/i', $title)) {
			return (string) \preg_replace('/<img\b[^>]*>/i', $fallback_img, $title, 1);
		}
		return $fallback_img . ' ' . $title;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx65_adminbar_pdf_upload_url')) {
	function cmx65_adminbar_pdf_upload_url(): string {
		$token = (string) \get_option(MIS_BUERO_BELEG_UPLOAD::OPTION_TOKEN, '');
		if ($token === '') {
			$token = \wp_generate_uuid4();
			\update_option(MIS_BUERO_BELEG_UPLOAD::OPTION_TOKEN, $token, false);
		}

		return (string) \home_url('/mis-upload/?token=' . \rawurlencode($token));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx65_adminbar_can_create_post_type')) {
	function cmx65_adminbar_can_create_post_type(string $post_type): bool {
		$obj = \get_post_type_object($post_type);
		if (!$obj) {
			return false;
		}
		$cap = (string) ($obj->cap->create_posts ?? '');
		if ($cap === '') {
			return \current_user_can('edit_posts');
		}
		return \current_user_can($cap);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx65_adminbar_can_publish_post_type')) {
	function cmx65_adminbar_can_publish_post_type(string $post_type): bool {
		$obj = \get_post_type_object($post_type);
		if (!$obj) {
			return false;
		}
		$cap = (string) ($obj->cap->publish_posts ?? '');
		if ($cap === '') {
			return \current_user_can('publish_posts');
		}
		return \current_user_can($cap);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx65_adminbar_kontakt_post_types')) {
	function cmx65_adminbar_kontakt_post_types(): array {
		$types = ['kontakte', 'kontakt'];
		if (\function_exists(__NAMESPACE__ . '\\cmx_kontakte_cpt')) {
			$types[] = (string) cmx_kontakte_cpt();
		}
		return \array_values(\array_unique(\array_filter(\array_map('strval', $types))));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx65_adminbar_quickcreate_enabled')) {
	function cmx65_adminbar_quickcreate_enabled(): bool {
		$options = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			? (array) \get_option(CMX_SETTINGS_MAIN, [])
			: [];
		return !empty($options['quick_edit']);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx65_adminbar_beleg_quickcreate_allowed')) {
	function cmx65_adminbar_beleg_quickcreate_allowed(): bool {
		if (!cmx65_adminbar_quickcreate_enabled()) {
			return false;
		}
		if (!\is_admin() || !\is_user_logged_in() || !\is_admin_bar_showing()) {
			return false;
		}
		if (!\current_user_can('edit_posts')) {
			return false;
		}
		if (!\post_type_exists('belege') || !cmx65_adminbar_can_create_post_type('belege')) {
			return false;
		}
		if (!\post_type_exists('artikel')) {
			return false;
		}
		foreach (cmx65_adminbar_kontakt_post_types() as $post_type) {
			if (\post_type_exists($post_type)) {
				return true;
			}
		}
		return false;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx65_adminbar_beleg_position_row_from_artikel')) {
	function cmx65_adminbar_beleg_position_row_from_artikel(int $artikel_id, string $artikel_name = '', $artikel_variant_index = ''): array {
		$artikel_id = (int) $artikel_id;
		if ($artikel_id <= 0) {
			return [];
		}

		$artikel_name = \sanitize_text_field($artikel_name);
		if ($artikel_name === '') {
			$artikel_name = (string) \get_the_title($artikel_id);
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_decode_label_text')) {
			$artikel_name = (string) cmx_beleg_decode_label_text($artikel_name);
		} elseif (\function_exists(__NAMESPACE__ . '\\cmx_normalize_minus_sign')) {
			$artikel_name = (string) cmx_normalize_minus_sign($artikel_name);
		}
		$artikel_name = \trim($artikel_name);
		if ($artikel_name === '') {
			$artikel_name = '#' . $artikel_id;
		}

		$vk_meta_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VK') ? CMX_ARTIKEL_META_VK : '_cmx_artikel_vk';
		$vk_raw = (string) \get_post_meta($artikel_id, $vk_meta_key, true);
		$vk = 0.0;
		if ($vk_raw !== '') {
			if (\function_exists(__NAMESPACE__ . '\\cmx_norm_decimal')) {
				$vk = (float) cmx_norm_decimal($vk_raw);
			} else {
				$vk = (float) \str_replace(',', '.', $vk_raw);
			}
		}
		if (!\is_finite($vk) || $vk < 0) {
			$vk = 0.0;
		}

		$einheit_id = 0;
		$unit = '';
		if (\function_exists(__NAMESPACE__ . '\\cmx_artikel_default_einheit')) {
			$default_unit = (array) cmx_artikel_default_einheit($artikel_id);
			$einheit_id = (int) ($default_unit['id'] ?? 0);
			$unit = \sanitize_text_field((string) ($default_unit['name'] ?? ''));
		}

		$variant_index = '';
		if ($artikel_variant_index !== '' && $artikel_variant_index !== null && \is_numeric((string) $artikel_variant_index)) {
			$variant_index = (int) $artikel_variant_index;
		}

		return [
			'artikel_id'            => $artikel_id,
			'artikel_name'          => $artikel_name,
			'artikel_variant_index' => $variant_index,
			'menge'                 => 1,
			'einheit_id'            => $einheit_id > 0 ? $einheit_id : 0,
			'unit'                  => $unit,
			'preis'                 => $vk > 0 ? (string) \round($vk, 2) : '',
			'rabatt'                => '',
			'beschreibung'          => '',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx65_adminbar_beleg_default_dates')) {
	function cmx65_adminbar_beleg_default_dates(): array {
		$opts = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			? (array) \get_option(CMX_SETTINGS_MAIN, [])
			: [];
		$timezone = \function_exists('wp_timezone')
			? \wp_timezone()
			: new \DateTimeZone('UTC');
		$invoice_date = (string) \current_time('Y-m-d');
		$invoice_dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $invoice_date, $timezone);
		if (!$invoice_dt instanceof \DateTimeImmutable) {
			$invoice_dt = new \DateTimeImmutable('now', $timezone);
		}
		$due_date = \function_exists(__NAMESPACE__ . '\\cmx_belege_default_due_date')
			? (string) cmx_belege_default_due_date($invoice_dt->format('Y-m-d'), $opts)
			: '';
		if ($due_date !== '' && \preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
			$due_dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $due_date, $timezone);
		} else {
			$due_days = \function_exists(__NAMESPACE__ . '\\cmx_belege_default_due_days')
				? (int) cmx_belege_default_due_days($opts)
				: ((isset($opts['belege_faelligkeit_tage']) && $opts['belege_faelligkeit_tage'] !== '') ? (int) $opts['belege_faelligkeit_tage'] : 30);
			if ($due_days < 0) {
				$due_days = 0;
			}
			if ($due_days > 3650) {
				$due_days = 3650;
			}
			if (!empty($opts['belege_faelligkeit_monatsende'])) {
				$due_dt = $invoice_dt->modify('last day of this month');
			} else {
				$due_dt = $invoice_dt->modify('+' . $due_days . ' days');
			}
		}
		if (!$due_dt instanceof \DateTimeImmutable) {
			$due_dt = $invoice_dt;
		}

		return [
			'invoice' => $invoice_dt->format('Y-m-d'),
			'due'     => $due_dt->format('Y-m-d'),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx65_adminbar_beleg_quickcreate_markup')) {
	function cmx65_adminbar_beleg_quickcreate_markup(): string {
		if (!cmx65_adminbar_beleg_quickcreate_allowed()) {
			return '';
		}

		$ajax_url = (string) \admin_url('admin-ajax.php');
		$action_url = (string) \admin_url('admin-post.php');
		$kontakt_nonce = (string) \wp_create_nonce('cmx_search_kontakte');
		$create_nonce = (string) \wp_create_nonce('cmx65_adminbar_create_beleg');

		$html  = '<form id="cmx65-adminbar-beleg-create-form" class="cmx-adminbar-beleg-create-form" method="post" action="' . \esc_url($action_url) . '" data-ajax-url="' . \esc_attr($ajax_url) . '" data-kontakt-nonce="' . \esc_attr($kontakt_nonce) . '">';
		$html .= '<input type="hidden" name="action" value="cmx65_adminbar_create_beleg">';
		$html .= '<input type="hidden" name="_wpnonce" value="' . \esc_attr($create_nonce) . '">';
		$html .= '<input type="hidden" id="cmx65-adminbar-kontakt-id" name="kontakt_id" value="">';
		$html .= '<input type="hidden" id="cmx65-adminbar-artikel-id" name="artikel_id" value="">';
		$html .= '<input type="hidden" id="cmx65-adminbar-artikel-name" name="artikel_name" value="">';
		$html .= '<input type="hidden" id="cmx65-adminbar-artikel-variant-index" name="artikel_variant_index" value="">';
		$html .= '<label class="screen-reader-text" for="cmx65-adminbar-kontakt-search">Kontakt</label>';
		$html .= '<div class="cmx-adminbar-pick">';
		$html .= '<input type="text" id="cmx65-adminbar-kontakt-search" class="cmx-adminbar-search-input" autocomplete="off" spellcheck="false" placeholder="Kontakt suchen...">';
		$html .= '<ul id="cmx65-adminbar-kontakt-suggest" class="cmx-adminbar-suggest" style="display:none"></ul>';
		$html .= '</div>';
		$html .= '<label class="screen-reader-text" for="cmx65-adminbar-artikel-search">Artikel</label>';
		$html .= '<div class="cmx-adminbar-pick">';
		$html .= '<input type="text" id="cmx65-adminbar-artikel-search" class="cmx-adminbar-search-input" autocomplete="off" spellcheck="false" placeholder="Artikel suchen...">';
		$html .= '<ul id="cmx65-adminbar-artikel-suggest" class="cmx-adminbar-suggest" style="display:none"></ul>';
		$html .= '</div>';
		$html .= '<button type="submit" class="cmx-adminbar-create-btn" disabled>Beleg erstellen</button>';
		$html .= '</form>';

		return $html;
	}
}

\add_action('admin_post_cmx65_adminbar_create_beleg', __NAMESPACE__ . '\\cmx65_adminbar_create_beleg_handler');
if (!\function_exists(__NAMESPACE__ . '\\cmx65_adminbar_create_beleg_handler')) {
	function cmx65_adminbar_create_beleg_handler(): void {
		$redirect_url = (string) (\wp_get_referer() ?: \admin_url());
		if (!cmx65_adminbar_quickcreate_enabled()) {
			\wp_safe_redirect($redirect_url);
			exit;
		}
		if (!\current_user_can('edit_posts')) {
			\wp_safe_redirect($redirect_url);
			exit;
		}
		if (!isset($_POST['_wpnonce']) || !\wp_verify_nonce((string) \wp_unslash($_POST['_wpnonce']), 'cmx65_adminbar_create_beleg')) {
			\wp_die('Ungültige Anfrage.');
		}

		$kontakt_id = isset($_POST['kontakt_id']) ? (int) \wp_unslash($_POST['kontakt_id']) : 0;
		$artikel_id = isset($_POST['artikel_id']) ? (int) \wp_unslash($_POST['artikel_id']) : 0;
		$artikel_name = isset($_POST['artikel_name']) ? (string) \wp_unslash($_POST['artikel_name']) : '';
		$artikel_variant_index = isset($_POST['artikel_variant_index']) ? (string) \wp_unslash($_POST['artikel_variant_index']) : '';

		if ($kontakt_id <= 0 || $artikel_id <= 0) {
			\wp_safe_redirect($redirect_url);
			exit;
		}

		$kontakt_post = \get_post($kontakt_id);
		$kontakt_types = cmx65_adminbar_kontakt_post_types();
		$artikel_post = \get_post($artikel_id);

			if (
				!$kontakt_post instanceof \WP_Post
				|| !\in_array((string) $kontakt_post->post_type, $kontakt_types, true)
				|| !\current_user_can('edit_post', $kontakt_id)
				|| !$artikel_post instanceof \WP_Post
				|| (string) $artikel_post->post_type !== 'artikel'
				|| !\current_user_can('edit_post', $artikel_id)
				|| !\post_type_exists('belege')
				|| !cmx65_adminbar_can_create_post_type('belege')
				|| !cmx65_adminbar_can_publish_post_type('belege')
			) {
				\wp_safe_redirect($redirect_url);
				exit;
			}

		$kontakt_label = (string) \get_the_title($kontakt_id);
		if (\function_exists(__NAMESPACE__ . '\\cmx_normalize_minus_sign')) {
			$kontakt_label = (string) cmx_normalize_minus_sign($kontakt_label);
		}
		$kontakt_label = \trim($kontakt_label);
		$kontakt_addr = \function_exists(__NAMESPACE__ . '\\cmx_build_kontakt_postanschrift')
			? (string) cmx_build_kontakt_postanschrift($kontakt_id)
			: '';

			$position_row = cmx65_adminbar_beleg_position_row_from_artikel($artikel_id, $artikel_name, $artikel_variant_index);
			if ($position_row === []) {
				\wp_safe_redirect($redirect_url);
				exit;
			}
			$default_dates = cmx65_adminbar_beleg_default_dates();
			$invoice_meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_RNG_DATUM')
				? CMX_BELEG_META_RNG_DATUM
				: '_cmx_beleg_rng_datum';
			$due_meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_FAELLIG')
				? CMX_BELEG_META_FAELLIG
				: '_cmx_beleg_faelligkeitsdatum';

				$beleg_id = \wp_insert_post([
					'post_type'   => 'belege',
				'post_status' => 'publish',
				'post_title'  => '',
				'post_author' => (int) \get_current_user_id(),
				'meta_input'  => [
						'_cmx_title_auto'         => 1,
					'_cmx_beleg_kontakt_id'   => $kontakt_id,
					'_cmx_beleg_kontakt_label'=> $kontakt_label,
					'_cmx_beleg_kontakt_addr' => $kontakt_addr,
					$invoice_meta_key         => (string) ($default_dates['invoice'] ?? ''),
					$due_meta_key             => (string) ($default_dates['due'] ?? ''),
					'_cmx_beleg_positionen'   => [$position_row],
				],
			], true);

			if (\is_wp_error($beleg_id) || (int) $beleg_id <= 0) {
				\wp_safe_redirect($redirect_url);
				exit;
			}
			$beleg_id = (int) $beleg_id;
			if ((string) \get_post_status($beleg_id) !== 'publish') {
				$publish_result = \wp_update_post([
					'ID'          => $beleg_id,
					'post_status' => 'publish',
				], true);
				if (\is_wp_error($publish_result)) {
					\wp_safe_redirect($redirect_url);
					exit;
				}
			}

			$edit_url = (string) \get_edit_post_link($beleg_id, '');
		if ($edit_url === '') {
			$edit_url = (string) \admin_url('post.php?post=' . (int) $beleg_id . '&action=edit');
		}

		\wp_safe_redirect($edit_url);
		exit;
	}
}

add_action('admin_head', __NAMESPACE__ . '\\cmx65_adminbar_beleg_quickcreate_styles', 20);
if (!\function_exists(__NAMESPACE__ . '\\cmx65_adminbar_beleg_quickcreate_styles')) {
	function cmx65_adminbar_beleg_quickcreate_styles(): void {
		if (!cmx65_adminbar_beleg_quickcreate_allowed()) {
			return;
		}
		echo '<style id="cmx65-adminbar-beleg-create-styles">'
			. '#wpadminbar .ab-top-menu>li.cmx-adminbar-beleg-create-node{position:absolute;left:50%;top:0;transform:translateX(-50%);margin:0;overflow:visible;z-index:100001;}'
			. '#wpadminbar #wp-admin-bar-cmx65_beleg_create_id>.ab-item{display:flex;align-items:center;height:32px;padding:0;background:transparent !important;box-shadow:none;overflow:visible;}'
			. '#wpadminbar #wp-admin-bar-cmx65_beleg_create_id>.ab-item:hover,'
			. '#wpadminbar #wp-admin-bar-cmx65_beleg_create_id>.ab-item:focus,'
			. '#wpadminbar #wp-admin-bar-cmx65_beleg_create_id.hover>.ab-item{background:transparent !important;color:inherit !important;}'
			. '#wpadminbar .cmx-adminbar-beleg-create-form{display:flex;align-items:center;gap:8px;height:32px;margin:0;}'
			. '#wpadminbar .cmx-adminbar-pick{position:relative;display:flex;align-items:center;}'
			. '#wpadminbar .cmx-adminbar-search-input{width:180px;height:26px;min-height:26px;margin:0;padding:3px 8px;border:1px solid rgba(255,255,255,.24);border-radius:4px;background:#fff;color:#1d2327;font-size:12px;line-height:1.2;box-shadow:none;outline:none;}'
			. '#wpadminbar .cmx-adminbar-search-input::placeholder{color:#646970;opacity:1;}'
			. '#wpadminbar .cmx-adminbar-search-input:focus{border-color:#ffeb3b;box-shadow:0 0 0 1px #ffeb3b;}'
			. '#wpadminbar .cmx-adminbar-create-btn{height:26px;min-height:26px;padding:0 12px;border:1px solid #7e1c16;border-radius:4px;background:#8f211b;color:#fff;font-size:12px;font-weight:700;line-height:24px;cursor:pointer;}'
			. '#wpadminbar .cmx-adminbar-create-btn:hover,#wpadminbar .cmx-adminbar-create-btn:focus{background:#771813;color:#ffeb3b;}'
			. '#wpadminbar .cmx-adminbar-create-btn[disabled]{opacity:.6;cursor:not-allowed;color:#fff;}'
			. '#wpadminbar .cmx-adminbar-suggest{position:absolute;top:calc(100% + 4px);left:0;width:100%;min-width:240px;max-height:280px;margin:0;padding:4px 0;list-style:none;background:#fff;border:1px solid #ccd0d4;border-radius:6px;box-shadow:0 14px 28px rgba(0,0,0,.22);overflow:auto;z-index:100003;}'
			. '#wpadminbar .cmx-adminbar-suggest li{display:block;width:100%;box-sizing:border-box;margin:0;padding:7px 10px;cursor:pointer;color:#1d2327;line-height:1.25;white-space:normal;}'
			. '#wpadminbar .cmx-adminbar-suggest li:hover,#wpadminbar .cmx-adminbar-suggest li.active{background:#f5d6cf;color:#1d2327;}'
			. '#wpadminbar .cmx-adminbar-suggest-title{display:block;font-weight:600;color:#1d2327;}'
			. '#wpadminbar .cmx-adminbar-suggest-meta{display:block;margin-top:2px;font-size:11px;line-height:1.25;color:#646970;white-space:normal;}'
			. '@media screen and (max-width:1320px){#wpadminbar .ab-top-menu>li.cmx-adminbar-beleg-create-node{display:none !important;}}'
			. '</style>';
	}
}

add_action('admin_footer', __NAMESPACE__ . '\\cmx65_adminbar_beleg_quickcreate_script', 20);
if (!\function_exists(__NAMESPACE__ . '\\cmx65_adminbar_beleg_quickcreate_script')) {
	function cmx65_adminbar_beleg_quickcreate_script(): void {
		if (!cmx65_adminbar_beleg_quickcreate_allowed()) {
			return;
		}
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function () {
			var form = document.getElementById('cmx65-adminbar-beleg-create-form');
			if (!form) return;

			var ajaxUrl = String(form.getAttribute('data-ajax-url') || '');
			var kontaktNonce = String(form.getAttribute('data-kontakt-nonce') || '');
			var kontaktInput = document.getElementById('cmx65-adminbar-kontakt-search');
			var kontaktIdInput = document.getElementById('cmx65-adminbar-kontakt-id');
			var kontaktList = document.getElementById('cmx65-adminbar-kontakt-suggest');
			var artikelInput = document.getElementById('cmx65-adminbar-artikel-search');
			var artikelIdInput = document.getElementById('cmx65-adminbar-artikel-id');
			var artikelNameInput = document.getElementById('cmx65-adminbar-artikel-name');
			var artikelVariantIndexInput = document.getElementById('cmx65-adminbar-artikel-variant-index');
			var artikelList = document.getElementById('cmx65-adminbar-artikel-suggest');
			var submitBtn = form.querySelector('.cmx-adminbar-create-btn');
			var kontaktTimer = null;
			var artikelTimer = null;

			if (!kontaktInput || !kontaktIdInput || !kontaktList || !artikelInput || !artikelIdInput || !artikelNameInput || !artikelVariantIndexInput || !artikelList || !submitBtn) {
				return;
			}

			['mousedown', 'click'].forEach(function (eventName) {
				form.addEventListener(eventName, function (event) {
					event.stopPropagation();
				});
			});

			function esc(value) {
				return String(value || '')
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;')
					.replace(/"/g, '&quot;');
			}

			function normalizeInline(value) {
				return esc(String(value || '').replace(/\s*\n+\s*/g, ' · ').replace(/\s{2,}/g, ' ').trim());
			}

			function toInt(value) {
				var parsed = parseInt(String(value || ''), 10);
				return isNaN(parsed) ? 0 : parsed;
			}

			function updateSubmit() {
				submitBtn.disabled = !(toInt(kontaktIdInput.value) > 0 && toInt(artikelIdInput.value) > 0);
			}

			function focusArtikelPicker(openList) {
				window.setTimeout(function () {
					artikelInput.focus();
					try { artikelInput.select(); } catch (err) {}
					if (openList) {
						fetchArtikel('', artikelNav.render);
					}
				}, 0);
			}

			function focusSubmitButton() {
				window.setTimeout(function () {
					submitBtn.focus();
				}, 0);
			}

			function buildUrl(params) {
				var qs = new URLSearchParams(params);
				return ajaxUrl + (ajaxUrl.indexOf('?') === -1 ? '?' : '&') + qs.toString();
			}

			function makeNavigator(inputEl, listEl, chooseCb, renderCb) {
				var items = [];
				var active = -1;

				function closeList() {
					listEl.style.display = 'none';
					listEl.innerHTML = '';
					active = -1;
				}

				function markActive() {
					Array.prototype.forEach.call(listEl.children, function (li, index) {
						li.classList.toggle('active', index === active);
					});
				}

				function render(arr) {
					items = Array.isArray(arr) ? arr : [];
					if (!items.length) {
						closeList();
						return;
					}
					listEl.innerHTML = items.map(function (item, index) {
						return '<li data-index="' + index + '">' + renderCb(item) + '</li>';
					}).join('');
					listEl.style.display = 'block';
					active = -1;
				}

				function move(direction) {
					if (!items.length) return;
					active = (active + direction + items.length) % items.length;
					markActive();
				}

				function choose(index) {
					if (index < 0 || index >= items.length) return;
					chooseCb(items[index]);
					closeList();
				}

				listEl.addEventListener('mousedown', function (event) {
					var li = event.target.closest('li');
					if (!li) return;
					event.preventDefault();
					choose(parseInt(li.getAttribute('data-index') || '-1', 10));
				});

				inputEl.addEventListener('keydown', function (event) {
					if (event.key === 'ArrowDown') {
						event.preventDefault();
						move(1);
						return;
					}
					if (event.key === 'ArrowUp') {
						event.preventDefault();
						move(-1);
						return;
					}
					if (event.key === 'Enter') {
						event.preventDefault();
						if (active > -1) {
							choose(active);
						}
						return;
					}
					if (event.key === 'Escape') {
						closeList();
					}
				});

				inputEl.addEventListener('blur', function () {
					window.setTimeout(function () {
						var activeEl = document.activeElement;
						if (activeEl === inputEl || listEl.contains(activeEl)) return;
						closeList();
					}, 120);
				});

				document.addEventListener('click', function (event) {
					if (!listEl.contains(event.target) && event.target !== inputEl) {
						closeList();
					}
				});

				return {
					render: render,
					reset: function () {
						items = [];
						active = -1;
						closeList();
					}
				};
			}

			function fetchContacts(query, cb) {
				fetch(buildUrl({
					action: 'cmx_search_kontakte',
					_ajax_nonce: kontaktNonce,
					q: query || ''
				}), { credentials: 'same-origin' })
					.then(function (response) { return response.json(); })
					.then(function (json) {
						var items = (json && json.success && json.data && Array.isArray(json.data.items)) ? json.data.items : [];
						cb(items);
					})
					.catch(function () {
						cb([]);
					});
			}

			function fetchArtikel(query, cb) {
				fetch(buildUrl({
					action: 'cmx_search_artikel',
					term: query || ''
				}), { credentials: 'same-origin' })
					.then(function (response) { return response.json(); })
					.then(function (json) {
						var items = Array.isArray(json) ? json : [];
						cb(items.map(function (item) {
							return {
								id: item.value || 0,
								title: item.title || '',
								nr: item.nr || '',
								label: item.label || ((item.nr ? item.nr + ' – ' : '') + (item.title || '')),
								variant_index: (item.variant_index ?? '')
							};
						}));
					})
					.catch(function () {
						cb([]);
					});
			}

			var kontaktNav = makeNavigator(kontaktInput, kontaktList, function (item) {
				kontaktInput.value = String(item.title || '');
				kontaktIdInput.value = String(item.id || '');
				updateSubmit();
				focusArtikelPicker(true);
			}, function (item) {
				var html = '<span class="cmx-adminbar-suggest-title">' + esc(item.title || '') + '</span>';
				if (item.addr) {
					html += '<span class="cmx-adminbar-suggest-meta">' + normalizeInline(item.addr) + '</span>';
				}
				return html;
			});

			var artikelNav = makeNavigator(artikelInput, artikelList, function (item) {
				artikelInput.value = String((item.nr ? item.nr + ' – ' : '') + (item.title || ''));
				artikelIdInput.value = String(item.id || '');
				artikelNameInput.value = String(item.title || '');
				artikelVariantIndexInput.value = String(item.variant_index ?? '');
				updateSubmit();
				focusSubmitButton();
			}, function (item) {
				return '<span class="cmx-adminbar-suggest-title">' + esc(item.label || '') + '</span>';
			});

			kontaktInput.addEventListener('input', function () {
				kontaktIdInput.value = '';
				updateSubmit();
				if (kontaktTimer) window.clearTimeout(kontaktTimer);
				var query = kontaktInput.value.trim();
				if (query.length === 0) {
					fetchContacts('', kontaktNav.render);
					return;
				}
				if (query.length < 2) {
					kontaktNav.reset();
					return;
				}
				kontaktTimer = window.setTimeout(function () {
					fetchContacts(query, kontaktNav.render);
				}, 180);
			});

			artikelInput.addEventListener('input', function () {
				artikelIdInput.value = '';
				artikelNameInput.value = '';
				artikelVariantIndexInput.value = '';
				updateSubmit();
				if (artikelTimer) window.clearTimeout(artikelTimer);
				var query = artikelInput.value.trim();
				if (query.length === 0) {
					fetchArtikel('', artikelNav.render);
					return;
				}
				if (query.length < 2) {
					artikelNav.reset();
					return;
				}
				artikelTimer = window.setTimeout(function () {
					fetchArtikel(query, artikelNav.render);
				}, 180);
			});

			[kontaktInput, artikelInput].forEach(function (inputEl) {
				inputEl.addEventListener('click', function () {
					if (inputEl === kontaktInput) {
						if (kontaktTimer) window.clearTimeout(kontaktTimer);
						fetchContacts('', kontaktNav.render);
						return;
					}
					if (artikelTimer) window.clearTimeout(artikelTimer);
					fetchArtikel('', artikelNav.render);
				});
				inputEl.addEventListener('focus', function () {
					if (inputEl === kontaktInput) {
						if (kontaktTimer) window.clearTimeout(kontaktTimer);
						fetchContacts(kontaktInput.value.trim().length >= 2 ? kontaktInput.value.trim() : '', kontaktNav.render);
						return;
					}
					if (artikelTimer) window.clearTimeout(artikelTimer);
					fetchArtikel(artikelInput.value.trim().length >= 2 ? artikelInput.value.trim() : '', artikelNav.render);
				});
			});

			form.addEventListener('submit', function (event) {
				if (toInt(kontaktIdInput.value) > 0 && toInt(artikelIdInput.value) > 0) {
					return;
				}
				event.preventDefault();
				if (toInt(kontaktIdInput.value) <= 0) {
					kontaktInput.focus();
					return;
				}
				artikelInput.focus();
			});

			updateSubmit();
		});
		</script>
		<?php
	}
}

add_action('admin_bar_menu', __NAMESPACE__ . '\\cmx65_adminbar_my_account_avatar_fallback', 99999);
function cmx65_adminbar_my_account_avatar_fallback(\WP_Admin_Bar $wp_admin_bar): void {
	if (!\is_user_logged_in() || !\is_admin_bar_showing()) {
		return;
	}

	$fallback_url = \esc_url(cmx65_adminbar_fallback_avatar_url());
	if ($fallback_url === '') {
		return;
	}

	$current_user = \wp_get_current_user();
	$user_id = ($current_user instanceof \WP_User && $current_user->exists()) ? (int) $current_user->ID : 0;
	$should_force_fallback = false;
	if ($user_id > 0 && \function_exists('\\get_avatar_data')) {
		$avatar_data = (array) \get_avatar_data($user_id, ['size' => 64]);
		$should_force_fallback = empty($avatar_data['found_avatar']);
	}

	$targets = [
		'my-account' => ['size' => 26],
		'user-info'  => ['size' => 64],
	];

	foreach ($targets as $node_id => $config) {
		$node = $wp_admin_bar->get_node($node_id);
		if (!$node) {
			continue;
		}

		$title = (string) ($node->title ?? '');
		if ($title === '') {
			continue;
		}

		$size = (int) ($config['size'] ?? 26);
		if ($should_force_fallback || \stripos($title, '<img') === false) {
			$node->title = cmx65_adminbar_force_avatar_in_title($title, $size, $fallback_url);
			$wp_admin_bar->add_node((array) $node);
		}
	}
}

add_action('admin_footer', __NAMESPACE__ . '\\cmx65_adminbar_avatar_fallback_script', 20);
add_action('wp_footer', __NAMESPACE__ . '\\cmx65_adminbar_avatar_fallback_script', 20);
function cmx65_adminbar_avatar_fallback_script(): void {
	if (!\is_user_logged_in() || !\is_admin_bar_showing()) {
		return;
	}

	$fallback_url = (string) cmx65_adminbar_fallback_avatar_url();
	if ($fallback_url === '') {
		return;
	}
	?>
	<script>
	(function(){
		var fallbackUrl = <?php echo \wp_json_encode($fallback_url); ?>;
		if (!fallbackUrl) return;

		function applyFallback(img) {
			if (!img) return;
			img.setAttribute('src', fallbackUrl);
			img.removeAttribute('srcset');
			img.classList.add('cmx-adminbar-fallback-avatar');
		}

		function isDefaultAvatar(img) {
			if (!img) return false;
			if (img.classList.contains('avatar-default')) return true;
			var src = String(img.getAttribute('src') || '');
			return /[?&]d=/.test(src) || /gravatar\.com\/avatar/i.test(src);
		}

		function ensureAvatar(selector, size) {
			var item = document.querySelector(selector);
			if (!item) return;

			var img = item.querySelector('img.avatar');
			if (!img) {
				img = document.createElement('img');
				img.alt = '';
				img.width = size;
				img.height = size;
				img.decoding = 'async';
				img.loading = 'lazy';
				img.className = 'avatar avatar-' + String(size) + ' photo cmx-adminbar-fallback-avatar';
				item.insertBefore(img, item.firstChild);
				applyFallback(img);
				return;
			}

			img.addEventListener('error', function handleError() {
				applyFallback(img);
			}, { once: true });

			if (!img.getAttribute('src') || isDefaultAvatar(img)) {
				applyFallback(img);
			}
		}

		function ensureAvatars() {
			ensureAvatar('#wp-admin-bar-my-account > .ab-item', 26);
			ensureAvatar('#wp-admin-bar-user-info > .ab-item', 64);
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', ensureAvatars, { once: true });
		} else {
			ensureAvatars();
		}
	})();
	</script>
	<?php
}

add_action('wp_head', __NAMESPACE__ . '\\cmx65_render_front_quicklinks_css', 20);
function cmx65_render_front_quicklinks_css(): void {
	if (!cmx65_is_instance_home_request()) {
		return;
	}
		echo '<style id="cmx-front-quicklinks-css">'
			. '.cmx-front-quicklinks{display:flex;align-items:center;gap:12px;padding:8px 14px;background:#a42c24;color:#fff;font-size:13px;line-height:1.3;}'
			. '.cmx-front-quicklinks-main{display:flex;flex-wrap:wrap;align-items:center;gap:10px;}'
			. '.cmx-front-quicklinks a{color:#fff;text-decoration:none;font-weight:700;}'
			. '.cmx-front-quicklinks a.cmx-front-home-link{color:#ffeb3b;}'
			. '.cmx-front-quicklinks a:hover,.cmx-front-quicklinks a:focus{text-decoration:underline;color:#fff;}'
			. '.cmx-front-quicklinks .cmx-front-dropdown{position:relative;display:inline-flex;align-items:center;}'
			. '.cmx-front-quicklinks .cmx-front-dropdown-toggle{display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:700;list-style:none;}'
			. '.cmx-front-quicklinks .cmx-front-dropdown-toggle::-webkit-details-marker{display:none;}'
			. '.cmx-front-quicklinks .cmx-front-dropdown-toggle::after{content:"▾";font-size:11px;opacity:.85;}'
			. '.cmx-front-quicklinks .cmx-front-dropdown-menu{position:absolute;top:calc(100% + 8px);left:0;min-width:180px;padding:8px 0;border-radius:12px;background:#8f211b;box-shadow:0 18px 38px rgba(0,0,0,.24);display:flex;flex-direction:column;z-index:1000;}'
			. '.cmx-front-quicklinks .cmx-front-dropdown-menu a{padding:8px 14px;white-space:nowrap;}'
			. '.cmx-front-quicklinks .cmx-front-dropdown-menu a:hover,.cmx-front-quicklinks .cmx-front-dropdown-menu a:focus{background:rgba(255,255,255,.08);text-decoration:none;}'
			. '.cmx-front-quicklinks .cmx-front-sep{opacity:.55;}'
			. '.cmx-front-quicklinks-logout{margin-left:auto;white-space:nowrap;}'
		. 'html{margin-top:0 !important;}* html body{margin-top:0 !important;}'
		. '@media (max-width: 900px){.cmx-front-quicklinks{flex-wrap:wrap;}.cmx-front-quicklinks-logout{margin-left:0;}}'
		. '@media screen and (max-width:782px){html{margin-top:0 !important;}}'
		. '</style>';
}

add_action('wp_body_open', __NAMESPACE__ . '\\cmx65_render_front_quicklinks', 5);
function cmx65_render_front_quicklinks(): void {
	if (!cmx65_is_instance_home_request()) {
		return;
	}

	$support_url = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
		? \add_query_arg(
			[
				'page' => CMX_SETTINGS_SLUG,
				'tab'  => 'support',
			],
			\admin_url('admin.php')
		)
		: \admin_url('admin.php?page=cmx-einstellungen&tab=support');

	$links = [
		['label' => 'Home', 'href' => \home_url('/wp-admin/')],
		['label' => 'Mis Büro'],
		['label' => 'Archiv', 'href' => \home_url('/archiv/')],
		['label' => 'Scanner', 'href' => \home_url('/scanner/')],
	];

	$company_links = [
		['label' => 'Website', 'href' => 'https://misbuero.ch/', 'target' => '_blank'],
		['label' => 'FAQ', 'href' => 'https://misbuero.ch/faq/', 'target' => '_blank'],
		['label' => 'Aktuelles', 'href' => 'https://misbuero.ch/aktuelles/', 'target' => '_blank'],
		['label' => 'YouTube', 'href' => 'https://www.youtube.com/@MisBuero', 'target' => '_blank'],
	];

	$apps_links = [];

	if (\current_user_can('manage_options')) {
		$token = \get_option(MIS_BUERO_BELEG_UPLOAD::OPTION_TOKEN);
		if (empty($token)) {
			$token = \wp_generate_uuid4();
			\update_option(MIS_BUERO_BELEG_UPLOAD::OPTION_TOKEN, $token, false);
		}
		$apps_links[] = ['label' => 'PDF Upload', 'href' => \home_url('/mis-upload/?token=' . $token), 'target' => '_blank'];
		if (\function_exists(__NAMESPACE__ . '\\cmx_ext_time_stopwatch_url')) {
			$stopwatch_url = (string) cmx_ext_time_stopwatch_url();
			if ($stopwatch_url !== '') {
				$apps_links[] = ['label' => 'Stoppuhr', 'href' => $stopwatch_url, 'target' => '_blank'];
			}
		}
		$apps_links[] = ['label' => 'Monitoring', 'href' => 'https://anyboard.io/', 'target' => '_blank'];
	} else {
		$links[] = ['label' => 'Monitoring', 'href' => 'https://anyboard.io/', 'target' => '_blank'];
	}

	$links[] = ['label' => 'Support-Ticket', 'href' => $support_url];

	echo '<nav class="cmx-front-quicklinks" aria-label="Schnellnavigation">';
	echo '<div class="cmx-front-quicklinks-main">';
	$first = true;
	foreach ($links as $link) {
		if (!$first) {
			echo '<span class="cmx-front-sep">|</span>';
		}
		$first = false;
			$href = (string) ($link['href'] ?? '');
			$label = (string) ($link['label'] ?? '');
			$target = (string) ($link['target'] ?? '');
			$rel = ($target === '_blank') ? ' rel="noopener noreferrer"' : '';
			$target_attr = ($target !== '') ? ' target="' . \esc_attr($target) . '"' : '';
			$class_attr = ($label === 'Home') ? ' class="cmx-front-home-link"' : '';
			if ($label === 'Mis Büro' && !empty($company_links)) {
				echo '<details class="cmx-front-dropdown">';
				echo '<summary class="cmx-front-dropdown-toggle">Mis Büro</summary>';
				echo '<div class="cmx-front-dropdown-menu">';
				foreach ($company_links as $company_link) {
					$company_href = (string) ($company_link['href'] ?? '');
					$company_label = (string) ($company_link['label'] ?? '');
					$company_target = (string) ($company_link['target'] ?? '');
					$company_rel = ($company_target === '_blank') ? ' rel="noopener noreferrer"' : '';
					$company_target_attr = ($company_target !== '') ? ' target="' . \esc_attr($company_target) . '"' : '';
					echo '<a href="' . \esc_url($company_href) . '"' . $company_target_attr . $company_rel . '>' . \esc_html($company_label) . '</a>';
				}
				echo '</div>';
				echo '</details>';
				continue;
			}
			echo '<a href="' . \esc_url($href) . '"' . $class_attr . $target_attr . $rel . '>' . \esc_html($label) . '</a>';
		}
	if (!empty($apps_links)) {
		if (!$first) {
			echo '<span class="cmx-front-sep">|</span>';
		}
		echo '<details class="cmx-front-dropdown">';
		echo '<summary class="cmx-front-dropdown-toggle">Apps</summary>';
		echo '<div class="cmx-front-dropdown-menu">';
		foreach ($apps_links as $link) {
			$href = (string) ($link['href'] ?? '');
			$label = (string) ($link['label'] ?? '');
			$target = (string) ($link['target'] ?? '');
			$rel = ($target === '_blank') ? ' rel="noopener noreferrer"' : '';
			$target_attr = ($target !== '') ? ' target="' . \esc_attr($target) . '"' : '';
			echo '<a href="' . \esc_url($href) . '"' . $target_attr . $rel . '>' . \esc_html($label) . '</a>';
		}
		echo '</div>';
		echo '</details>';
	}
	echo '</div>';
	echo '<div class="cmx-front-quicklinks-logout"><a href="' . \esc_url(\wp_logout_url(\home_url('/'))) . '">Abmelden</a></div>';
	echo '</nav>';
}

add_action('admin_bar_menu', __NAMESPACE__ . '\\cmx65_adminbar', 999);
function cmx65_adminbar($wp_admin_bar) {

	$wp_admin_bar->remove_node('wp-logo');
	$wp_admin_bar->remove_node('new-content');
	$wp_admin_bar->remove_node('comments');

	if (\is_admin() && \function_exists(__NAMESPACE__ . '\\cmx65_adminbar_beleg_quickcreate_allowed') && cmx65_adminbar_beleg_quickcreate_allowed()) {
		$quickcreate_markup = (string) cmx65_adminbar_beleg_quickcreate_markup();
		if ($quickcreate_markup !== '') {
			$wp_admin_bar->add_menu([
				'id'    => 'cmx65_beleg_create_id',
				'title' => $quickcreate_markup,
				'href'  => false,
				'meta'  => [
					'title' => 'Beleg Schnell-Erfassung',
					'class' => 'cmx-adminbar-beleg-create-node',
				],
			]);
		}
	}

	if (\is_admin()) {
			echo '
			<style>
        #wpadminbar [id^="wp-admin-bar-cmx65_"] > .ab-item,
        #wpadminbar [id^="wp-admin-bar-cmx65_"] > .ab-item:focus,
        #wpadminbar [id^="wp-admin-bar-cmx65_"]:hover > .ab-item,
        #wpadminbar [id^="wp-admin-bar-cmx65_"].hover > .ab-item {
            background: #a42c24 !important;
            color: #fff !important;
        }
        #wpadminbar [id^="wp-admin-bar-cmx65_"] > .ab-item:hover,
        #wpadminbar [id^="wp-admin-bar-cmx65_"] > .ab-item:focus,
        #wpadminbar [id^="wp-admin-bar-cmx65_"]:hover > .ab-item,
        #wpadminbar [id^="wp-admin-bar-cmx65_"].hover > .ab-item {
            background: #a42c24 !important;
            color: #ffeb3b !important;
        }
        #wpadminbar [id^="wp-admin-bar-cmx65_"] .ab-sub-wrapper,
        #wpadminbar [id^="wp-admin-bar-cmx65_"] .ab-sub-wrapper .ab-submenu,
        #wpadminbar [id^="wp-admin-bar-cmx65_"] .ab-sub-wrapper .ab-submenu .ab-item {
            background: #a42c24 !important;
            color: #fff !important;
        }
        #wpadminbar [id^="wp-admin-bar-cmx65_"] .ab-sub-wrapper .ab-item:hover,
        #wpadminbar [id^="wp-admin-bar-cmx65_"] .ab-sub-wrapper .ab-item:focus,
        #wpadminbar [id^="wp-admin-bar-cmx65_"] .ab-sub-wrapper .ab-item:active {
            background: #a42c24 !important;
            color: #ffeb3b !important;
        }
        #wpadminbar .cmx-nohover > .ab-item {
            cursor: default !important;
            pointer-events: none !important;
            background: #a42c24 !important;
            color: #fff !important;
        }
        #wpadminbar .cmx-nohover > .ab-item:hover {
            background: #a42c24 !important;
            color: #ffeb3b !important;
        }
        #wpadminbar #wp-admin-bar-cmx65_monitoring_id > .ab-item {
            cursor: copy !important;
        }
    </style>';
	}

	// $wp_admin_bar->add_menu([
	// 	'id'    => 'cmx65_name_id',
	// 	'title' => '<span class="ab-label" style="cursor:default; pointer-events:none;">Mis Büro</span>',
	// 	'href'  => false,
	// 	'meta'  => [
	// 		'title'  => '',
	// 		'class' => 'cmx-nohover',
	// 	],
	// ]);


	// fixme rju 2026-02-15: Evtl. spöter zur eignen homePage springen?
	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_name_id',
		'title' => '<span class="ab-label" style="cursor:default; pointer-events:none;">Mis Büro</span>',
		'href'  => 'https://misbuero.ch/',
		'meta'  => [
			'title'  => __('Zur Mis Büro Homepage', 'textdomain'),
			'target' => '_blank',
			'rel'    => 'noopener',
		],
	]);


	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_menu1_id',
		'title' => '<span class="ab-label" style="cursor:default; pointer-events:none; color:yellow;">–</span>',
		'href'  => false,
		'meta'  => [
			'title'  => '',
			'class' => 'cmx-nohover',
		],
	]);

	if ( ! current_user_can( 'manage_options' ) ) {
		$wp_admin_bar->add_menu([
			'id'    => 'cmx65_monitoring_id',
			'title' => 'Monitoring',
			'href'  => 'https://anyboard.io/',
			'meta'  => [
				'title'  => __('Monitoring für Apple TV', 'textdomain'),
				'target' => '_blank',
				'rel'    => 'noopener',
			],
		]);
	}

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_katalog_id',
		'title' => 'Katalog',
		'href'  => \function_exists(__NAMESPACE__ . '\\cmx_artikel_liste_url') ? cmx_artikel_liste_url() : \home_url('/katalog/'),
		'meta'  => [
			'title'  => __('Dein Online Katalog', 'textdomain'),
			'target' => '_blank',
		],
	]);

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_telefon_id',
		'title' => 'Telefonbuch',
		'href'  => '/telefonbuch/',
		'meta'  => [
			'title'  => __('Dein Telefonbuch', 'textdomain'),
			'target' => '_blank',
		],
	]);

	// $wp_admin_bar->add_menu([
	// 	'id'    => 'cmx65_archiv_id',
	// 	'title' => 'Archiv',
	// 	'href'  => '/archiv/',
	// 	'meta'  => [
	// 		'title'  => __('Dein Archiv', 'textdomain'),
	// 		'target' => '_blank',
	// 	],
	// ]);

	// $wp_admin_bar->add_menu([
	// 	'id'    => 'cmx65_scanner_id',
	// 	'title' => 'Scanner',
	// 	'href'  => '/scanner/',
	// 	'meta'  => [
	// 		'title'  => __('Deine digitale Post', 'textdomain'),
	// 		'target' => '_blank',
	// 	],
	// ]);


	// $wp_admin_bar->add_menu([
	// 	'id'    => 'cmx65_menu2_id',
	// 	'title' => '<span class="ab-label" style="cursor:default; pointer-events:none; color:yellow;">–</span>',
	// 	'href'  => false,
	// 	'meta'  => [
	// 		'title'  => '',
	// 		'class' => 'cmx-nohover',
	// 	],
	// ]);

	if ( current_user_can( 'manage_options' ) ) {
		$url = cmx65_adminbar_pdf_upload_url();
		$stopwatch_url = \function_exists(__NAMESPACE__ . '\\cmx_ext_time_stopwatch_url')
			? (string) cmx_ext_time_stopwatch_url()
			: '';

		$wp_admin_bar->add_menu( [
			'id'    => 'cmx65_apps_id',
			'title' => 'Apps',
			'href'  => false,
			'meta'  => [
				'title' => 'Apps',
			],
		] );

		$wp_admin_bar->add_menu( [
			'id'     => 'cmx65_apps_pdf_upload_id',
			'parent' => 'cmx65_apps_id',
			'title'  => 'PDF Upload',
			'href'   => esc_url( $url ),
			'meta'   => [
				'title'  => 'PDF Upload',
				'target' => '_blank',
				'rel'    => 'noopener noreferrer',
			],
		] );

		if ( $stopwatch_url !== '' ) {
			$wp_admin_bar->add_menu( [
				'id'     => 'cmx65_apps_stopwatch_id',
				'parent' => 'cmx65_apps_id',
				'title'  => 'Stoppuhr',
				'href'   => esc_url( $stopwatch_url ),
				'meta'   => [
					'title'  => 'Stoppuhr',
					'target' => '_blank',
					'rel'    => 'noopener noreferrer',
				],
			] );
		}

		$wp_admin_bar->add_menu( [
			'id'     => 'cmx65_monitoring_id',
			'parent' => 'cmx65_apps_id',
			'title'  => 'Monitoring',
			'href'   => 'https://anyboard.io/',
			'meta'   => [
				'title'  => __('Monitoring für Apple TV', 'textdomain'),
				'target' => '_blank',
				'rel'    => 'noopener',
			],
		] );
	}


	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_menu23_id',
		'title' => '<span class="ab-label" style="cursor:default; pointer-events:none; color:yellow;">–</span>',
		'href'  => false,
		'meta'  => [
			'title'  => '',
			'class' => 'cmx-nohover',
		],
	]);

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_faq_id',
		'parent' => 'cmx65_name_id',
		'title' => 'FAQ',
		'href'  => 'https://misbuero.ch/faq/',
		'meta'  => [
			'title'  => __('Du hast allgemeines Fragen?', 'textdomain'),
			'target' => '_blank',
		],
	]);

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_aktuelles_id',
		'parent' => 'cmx65_name_id',
		'title' => 'Aktuelles',
		'href'  => 'https://misbuero.ch/aktuelles/',
		'meta'  => [
			'title'  => __('Aktuelles für Dich Online', 'textdomain'),
			'target' => '_blank',
		],
	]);

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_videos_id',
		'parent' => 'cmx65_name_id',
		'title' => 'YouTube',
		'href'  => 'https://www.youtube.com/@MisBuero',
		'meta'  => [
			'title'  => __('Mehr über Mis Büro erfahren...', 'textdomain'),
			'target' => '_blank',
		],
	]);

	// $wp_admin_bar->add_menu([
	// 	'id'    => 'cmx65_roadmap',
	// 	'title' => 'Roadmap',
	// 	'href'  => 'https://misbuero.ch/roadmap/',
	// 	'meta'  => [
	// 		'title'  => __('Wie geht es weiter mit Mis Büro?', 'textdomain'),
	// 		'target' => '_blank',
	// 	],
	// ]);

	// $wp_admin_bar->add_menu([
	// 	'id'    => 'cmx65_menux_id',
	// 	'title' => '<span class="ab-label" style="cursor:default; pointer-events:none; color:yellow;">–</span>',
	// 	'href'  => false,
	// 	'meta'  => [
	// 		'title'  => '',
	// 		'class' => 'cmx-nohover',
	// 	],
	// ]);

	// Support-URL: wenn Konstante vorhanden, nutze sie, sonst Fallback
	if (defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')) {
		$support_url = add_query_arg(
			[
				'page' => CMX_SETTINGS_SLUG,
				'tab'  => 'support',
			],
			admin_url('admin.php')
		);
	} else {
		$support_url = admin_url('admin.php?page=cmx-einstellungen&tab=support');
	}

	$wp_admin_bar->add_menu([
		'id'    => 'cmx65_support_id',
		'title' => 'Support-Ticket',
		'href'  => esc_url($support_url),
		'meta'  => [
			'title' => __('Hier kannst ein Support Ticket erstellen', 'textdomain'),
		],
	]);

	add_action('admin_footer', __NAMESPACE__ . '\\cmx65_anyboard_copy_script');
	add_action('wp_footer', __NAMESPACE__ . '\\cmx65_anyboard_copy_script');
	add_action('admin_footer', __NAMESPACE__ . '\\cmx65_katalog_copy_script');
	add_action('wp_footer', __NAMESPACE__ . '\\cmx65_katalog_copy_script');
	add_action('admin_footer', __NAMESPACE__ . '\\cmx65_apps_pdf_upload_script');
	add_action('wp_footer', __NAMESPACE__ . '\\cmx65_apps_pdf_upload_script');
}

function cmx65_anyboard_copy_script(): void
{
	$current_user = wp_get_current_user();
	$user = $current_user && $current_user->exists() ? $current_user->user_login : '';
	$pw = $current_user && $current_user->exists()
		? (string) get_user_meta($current_user->ID, 'cmx_anyboard_pw', true)
		: '';

	$args = [
		'user' => $user,
		'pw' => '{DeinPassword}',
	];

	$anyboard_url = add_query_arg($args, home_url('/wp-json/cmx-misbuero/v1/anyboard'));

	echo '
		<script>
        document.addEventListener("DOMContentLoaded", function () {
            var link = document.querySelector("#wp-admin-bar-cmx65_monitoring_id > .ab-item");
            if (!link) return;
            link.addEventListener("click", function (event) {
                event.preventDefault();
                var url = ' . json_encode($anyboard_url) . ';
                var openAnyboard = function () {
                    window.open("https://anyboard.io/", "_blank", "noopener");
                };
                if (!url) {
                    openAnyboard();
                    return;
                }
                var done = function () {
                    alert("URL für Anyboard wurde in die Zeischenablage kopiert.");
                    openAnyboard();
                };
                var fallbackCopy = function () {
                    var textarea = document.createElement("textarea");
                    textarea.value = url;
                    textarea.setAttribute("readonly", "");
                    textarea.style.position = "fixed";
                    textarea.style.top = "-1000px";
                    document.body.appendChild(textarea);
                    textarea.select();
                    try {
                        document.execCommand("copy");
                        done();
                    } catch (e) {
                        window.prompt("URL kopieren:", url);
                        openAnyboard();
                    } finally {
                        document.body.removeChild(textarea);
                    }
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(done, fallbackCopy);
                } else {
                    fallbackCopy();
                }
            });
        });
        </script>';
}

function cmx65_apps_pdf_upload_script(): void
{
	echo '
		<script>
        document.addEventListener("DOMContentLoaded", function () {
            var link = document.querySelector("#wp-admin-bar-cmx65_apps_pdf_upload_id > .ab-item");
            if (!link) return;
            link.addEventListener("click", function (event) {
                var url = link.getAttribute("href");
                if (!url) return;
                var fallbackCopy = function () {
                    var textarea = document.createElement("textarea");
                    textarea.value = url;
                    textarea.setAttribute("readonly", "");
                    textarea.style.position = "fixed";
                    textarea.style.top = "-1000px";
                    document.body.appendChild(textarea);
                    textarea.select();
                    try {
                        document.execCommand("copy");
                    } catch (e) {
                    } finally {
                        document.body.removeChild(textarea);
                    }
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).catch(fallbackCopy);
                } else {
                    fallbackCopy();
                }
            });
        });
        </script>';
}

function cmx65_katalog_copy_script(): void
{
	echo '
		<script>
        document.addEventListener("DOMContentLoaded", function () {
            var link = document.querySelector("#wp-admin-bar-cmx65_katalog_id > .ab-item");
            if (!link) return;
            link.addEventListener("click", function (event) {
                event.preventDefault();
                var url = link.getAttribute("href");
                if (!url) return;
                var target = link.getAttribute("target") || "";
                var openLink = function () {
                    if (target === "_blank") {
                        window.open(url, "_blank", "noopener");
                        return;
                    }
                    window.location.href = url;
                };
                var fallbackCopy = function () {
                    var textarea = document.createElement("textarea");
                    textarea.value = url;
                    textarea.setAttribute("readonly", "");
                    textarea.style.position = "fixed";
                    textarea.style.top = "-1000px";
                    document.body.appendChild(textarea);
                    textarea.select();
                    try {
                        document.execCommand("copy");
                        openLink();
                    } catch (e) {
                        openLink();
                    } finally {
                        document.body.removeChild(textarea);
                    }
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(openLink, fallbackCopy);
                } else {
                    fallbackCopy();
                }
            });
        });
        </script>';
}
