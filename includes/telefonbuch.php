<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

require_once __DIR__ . '/telefonbuch-detail.php';

if (!\function_exists(__NAMESPACE__ . '\\cmx_is_telefonbuch_request')) {
	function cmx_is_telefonbuch_request(): bool {
		if (\is_admin()) {
			return false;
		}

		$req_path = \parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), \PHP_URL_PATH);
		$req_path = \is_string($req_path) ? \trim($req_path, '/') : '';

		return $req_path === 'telefonbuch' || \str_starts_with($req_path, 'telefonbuch/') || \is_page('telefonbuch');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_term_label')) {
	function cmx_telefonbuch_term_label(string $taxonomy, string $slug): string {
		$taxonomy = \trim($taxonomy);
		$slug = \sanitize_title($slug);
		if ($taxonomy === '' || $slug === '' || !\taxonomy_exists($taxonomy)) {
			return '';
		}

		$term = \get_term_by('slug', $slug, $taxonomy);
		if (!$term || \is_wp_error($term) || empty($term->name)) {
			return '';
		}

		return \trim((string) $term->name);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_first_meta_value')) {
	function cmx_telefonbuch_first_meta_value(int $post_id, array $keys): string {
		foreach ($keys as $key) {
			$key = \trim((string) $key);
			if ($key === '') {
				continue;
			}
			$value = \trim((string) \get_post_meta($post_id, $key, true));
			if ($value !== '') {
				return $value;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_address_string')) {
	function cmx_telefonbuch_address_string(int $post_id): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_billing_address_string')) {
			$existing = \trim((string) cmx_billing_address_string($post_id));
			if ($existing !== '') {
				return $existing;
			}
		}

		$street_key = \defined(__NAMESPACE__ . '\\CMX_RECHNUNG_META_STRASSE')
			? (string) \constant(__NAMESPACE__ . '\\CMX_RECHNUNG_META_STRASSE')
			: '_cmx_rechnung_strasse';
		$zip_key = \defined(__NAMESPACE__ . '\\CMX_RECHNUNG_META_PLZ')
			? (string) \constant(__NAMESPACE__ . '\\CMX_RECHNUNG_META_PLZ')
			: '_cmx_rechnung_plz';
		$city_key = \defined(__NAMESPACE__ . '\\CMX_RECHNUNG_META_ORT')
			? (string) \constant(__NAMESPACE__ . '\\CMX_RECHNUNG_META_ORT')
			: '_cmx_rechnung_ort';
		$country_key = \defined(__NAMESPACE__ . '\\CMX_RECHNUNG_META_LAND')
			? (string) \constant(__NAMESPACE__ . '\\CMX_RECHNUNG_META_LAND')
			: '_cmx_rechnung_land';

		$street = \trim((string) \get_post_meta($post_id, $street_key, true));
		$zip = \trim((string) \get_post_meta($post_id, $zip_key, true));
		$city = \trim((string) \get_post_meta($post_id, $city_key, true));
		$country = \trim((string) \get_post_meta($post_id, $country_key, true));

		$parts = \array_filter([
			$street,
			\trim($zip . ' ' . $city),
			$country,
		], static function (string $value): bool {
			return \trim($value) !== '';
		});
		$address = \trim(\implode(', ', $parts));

		if ($address !== '') {
			return $address;
		}

		foreach (['cmx_rechnung', 'rechnung', '_cmx_rechnung', 'cmx_billing'] as $key) {
			$raw = \get_post_meta($post_id, $key, true);
			if (!\is_array($raw)) {
				continue;
			}

			$street = \trim((string) ($raw['strasse'] ?? $raw['street'] ?? ''));
			$zip = \trim((string) ($raw['plz'] ?? $raw['zip'] ?? ''));
			$city = \trim((string) ($raw['ort'] ?? $raw['city'] ?? ''));
			$country = \trim((string) ($raw['land'] ?? $raw['country'] ?? ''));
			$parts = \array_filter([
				$street,
				\trim($zip . ' ' . $city),
				$country,
			], static function (string $value): bool {
				return \trim($value) !== '';
			});
			$address = \trim(\implode(', ', $parts));
			if ($address !== '') {
				return $address;
			}
		}

		$street = cmx_telefonbuch_first_meta_value($post_id, [
			'rechnung_strasse',
			'billing_street',
			'liefer_strasse',
			'shipping_street',
			'strasse',
			'street',
		]);
		$zip = cmx_telefonbuch_first_meta_value($post_id, [
			'rechnung_plz',
			'billing_zip',
			'liefer_plz',
			'shipping_zip',
			'plz',
			'zip',
		]);
		$city = cmx_telefonbuch_first_meta_value($post_id, [
			'rechnung_ort',
			'billing_city',
			'liefer_ort',
			'shipping_city',
			'ort',
			'city',
		]);
		$country = cmx_telefonbuch_first_meta_value($post_id, [
			'rechnung_land',
			'billing_country',
			'liefer_land',
			'shipping_country',
			'land',
			'country',
		]);

		$parts = \array_filter([
			$street,
			\trim($zip . ' ' . $city),
			$country,
		], static function (string $value): bool {
			return \trim($value) !== '';
		});

		return \trim(\implode(', ', $parts));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_maps_url')) {
	function cmx_telefonbuch_maps_url(string $address): string {
		$address = \trim($address);
		if ($address === '') {
			return '';
		}

		return 'https://www.google.com/maps/dir/?api=1&destination=' . \rawurlencode($address);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_contact_channels')) {
	function cmx_telefonbuch_contact_channels(int $post_id): array {
		$phones = [];
		$emails = [];

		if (\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_read_contacts')) {
			foreach ((array) cmx_kommunikation_read_contacts($post_id) as $row) {
				if (!\is_array($row)) {
					continue;
				}

				$phone_raw = \trim((string) ($row['telefon'] ?? ''));
				if ($phone_raw !== '') {
					$phone_key = \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_normalize_phone')
						? (string) cmx_kommunikation_normalize_phone($phone_raw)
						: $phone_raw;
					$phone_display = \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_format_phone_display')
						? (string) cmx_kommunikation_format_phone_display($phone_key)
						: $phone_raw;
					$phone_label = cmx_telefonbuch_term_label(
						\defined(__NAMESPACE__ . '\\CMX_TAX_PHONE_LABELS') ? (string) \constant(__NAMESPACE__ . '\\CMX_TAX_PHONE_LABELS') : 'kontakte_telefone',
						(string) ($row['telefon_label'] ?? '')
					);
					$phones[$phone_key !== '' ? $phone_key : $phone_display] = [
						'label' => $phone_label,
						'display' => $phone_display !== '' ? $phone_display : $phone_raw,
						'href' => $phone_key !== '' ? 'tel:' . \preg_replace('/\s+/', '', $phone_key) : '',
					];
				}

				$email_raw = \sanitize_email((string) ($row['email'] ?? ''));
				if (\is_email($email_raw)) {
					$email_label = cmx_telefonbuch_term_label(
						\defined(__NAMESPACE__ . '\\CMX_TAX_MAIL_LABELS') ? (string) \constant(__NAMESPACE__ . '\\CMX_TAX_MAIL_LABELS') : 'kontakte_emails',
						(string) ($row['email_label'] ?? '')
					);
					$emails[$email_raw] = [
						'label' => $email_label,
						'display' => $email_raw,
						'href' => 'mailto:' . $email_raw,
					];
				}
			}
		}

		if ($phones === [] && \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_phone')) {
			$phone_raw = \trim((string) cmx_kommunikation_primary_phone($post_id));
			if ($phone_raw !== '') {
				$phone_key = \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_normalize_phone')
					? (string) cmx_kommunikation_normalize_phone($phone_raw)
					: $phone_raw;
				$phone_display = \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_format_phone_display')
					? (string) cmx_kommunikation_format_phone_display($phone_key)
					: $phone_raw;
				$phones[$phone_key !== '' ? $phone_key : $phone_display] = [
					'label' => '',
					'display' => $phone_display !== '' ? $phone_display : $phone_raw,
					'href' => $phone_key !== '' ? 'tel:' . \preg_replace('/\s+/', '', $phone_key) : '',
				];
			}
		}

		if ($emails === [] && \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_email')) {
			$email_raw = \sanitize_email((string) cmx_kommunikation_primary_email($post_id));
			if (\is_email($email_raw)) {
				$emails[$email_raw] = [
					'label' => '',
					'display' => $email_raw,
					'href' => 'mailto:' . $email_raw,
				];
			}
		}

		return [
			'phones' => \array_values($phones),
			'emails' => \array_values($emails),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_contact_row')) {
	function cmx_telefonbuch_contact_row(int $post_id): array {
		$title = \trim((string) \get_the_title($post_id));
		$firma_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_FIRMA')
			? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_FIRMA')
			: '_cmx_kontakte_firma';
		$vorname_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_VORNAME')
			? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_VORNAME')
			: '_cmx_kontakte_vorname';
		$nachname_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_NACHNAME')
			? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_NACHNAME')
			: '_cmx_kontakte_nachname';

		$firma = \trim((string) \get_post_meta($post_id, $firma_key, true));
		$vorname = \trim((string) \get_post_meta($post_id, $vorname_key, true));
		$nachname = \trim((string) \get_post_meta($post_id, $nachname_key, true));
		$person = \trim($vorname . ' ' . $nachname);

		if ($title === '') {
			$title = $firma !== '' ? $firma : ($person !== '' ? $person : '#' . $post_id);
		}

		$subtitle_parts = [];
		foreach ([$firma, $person] as $part) {
			$part = \trim((string) $part);
			if ($part === '' || $part === $title || \in_array($part, $subtitle_parts, true)) {
				continue;
			}
			$subtitle_parts[] = $part;
		}

		$channels = cmx_telefonbuch_contact_channels($post_id);
		$phones = (array) ($channels['phones'] ?? []);
		$emails = (array) ($channels['emails'] ?? []);
		$website = \function_exists(__NAMESPACE__ . '\\cmx_contact_homepage_url')
			? (string) cmx_contact_homepage_url($post_id)
			: '';
		$website_label = '';
		if ($website !== '') {
			$host = \parse_url($website, \PHP_URL_HOST);
			$website_label = \is_string($host) && $host !== '' ? $host : $website;
		}
		$maps_address = cmx_telefonbuch_address_string($post_id);
		$maps_url = cmx_telefonbuch_maps_url($maps_address);

		$search_parts = [$title, \implode(' ', $subtitle_parts), $website_label, $maps_address];
		foreach ($phones as $phone) {
			$search_parts[] = (string) ($phone['display'] ?? '');
			$search_parts[] = (string) ($phone['label'] ?? '');
		}
		foreach ($emails as $email) {
			$search_parts[] = (string) ($email['display'] ?? '');
			$search_parts[] = (string) ($email['label'] ?? '');
		}

		return [
			'id' => $post_id,
			'title' => $title,
			'subtitle' => \implode(' · ', $subtitle_parts),
			'phones' => $phones,
			'emails' => $emails,
			'website' => $website,
			'website_label' => $website_label,
			'maps_address' => $maps_address,
			'maps_url' => $maps_url,
			'detail_url' => \function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_url')
				? (string) cmx_telefonbuch_detail_url($post_id)
				: '',
			'image_url' => \function_exists(__NAMESPACE__ . '\\cmx_contact_logo_url')
				? (string) cmx_contact_logo_url($post_id)
				: '',
			'edit_url' => (string) \get_edit_post_link($post_id, ''),
			'search' => \implode(' ', \array_filter($search_parts, static function (string $value): bool {
				return \trim($value) !== '';
			})),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_rows')) {
	function cmx_telefonbuch_rows(): array {
		if (!\class_exists('\\WP_Query')) {
			return [];
		}

		$query = new \WP_Query([
			'post_type' => 'kontakte',
			'post_status' => ['publish', 'private'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
			'orderby' => 'title',
			'order' => 'ASC',
		]);

		$rows = [];
		foreach ((array) $query->posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0) {
				continue;
			}

			$row = cmx_telefonbuch_contact_row($post_id);
			if (
				((array) ($row['phones'] ?? [])) === []
				&& ((array) ($row['emails'] ?? [])) === []
				&& \trim((string) ($row['website'] ?? '')) === ''
			) {
				continue;
			}

			$rows[] = $row;
		}

		return $rows;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_telefonbuch_page')) {
	function cmx_render_telefonbuch_page(): void {
		if (!cmx_is_telefonbuch_request()) {
			return;
		}

		$rows = cmx_telefonbuch_rows();
		$reload_url = (string) \home_url('/telefonbuch/');
		$me_logo_url = \function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_url')
			? (string) cmx_email_self_logo_url()
			: '';
		$me_contact_url = \function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_url')
			? (string) cmx_email_self_contact_url()
			: '';
		$me_contact_title = \function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_branding_text')
			? (string) cmx_email_self_contact_branding_text()
			: '';

		while (\ob_get_level()) {
			\ob_end_clean();
		}

		if (!\defined('DONOTCACHEPAGE')) {
			\define('DONOTCACHEPAGE', true);
		}
		\nocache_headers();
		\status_header(200);

		echo '<!doctype html><html lang="de"><head><meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>Telefonbuch</title>';
		echo '<link rel="stylesheet" href="' . \esc_url(\includes_url('css/dashicons.min.css')) . '">';
		echo '<style>
			:root{color-scheme:light}
			*{box-sizing:border-box}
			body{margin:0;font-family:Segoe UI,Roboto,Arial,sans-serif;background:#efefef;color:#1d2327}
			.cmx-telefonbuch-page{max-width:1570px;margin:0 auto;padding:32px 18px 40px}
			.cmx-telefonbuch-card{background:#fff;border:1px solid #ddd;border-radius:14px;box-shadow:0 18px 40px rgba(0,0,0,.06);overflow:hidden}
			.cmx-telefonbuch-head{padding:24px 28px 18px;background:linear-gradient(135deg,#f7f7f7 0%,#ededed 100%);border-bottom:1px solid #e2e2e2}
			.cmx-telefonbuch-head-inner{display:flex;align-items:flex-start;justify-content:space-between;gap:24px}
			.cmx-telefonbuch-head-copy{flex:1 1 auto;min-width:0}
			.cmx-telefonbuch-head-brand{flex:0 0 auto;display:flex;align-items:flex-start;justify-content:flex-end;min-height:84px}
			.cmx-telefonbuch-head-logo{display:block;max-width:190px;max-height:84px;width:auto;height:auto;object-fit:contain;object-position:right top}
			.cmx-telefonbuch-kicker{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;margin:0 0 8px}
			.cmx-telefonbuch-kicker a,.cmx-telefonbuch-sub a{color:inherit;text-decoration:none}
			.cmx-telefonbuch-kicker a:hover,.cmx-telefonbuch-sub a:hover{color:#1d2327}
			.cmx-telefonbuch-title{margin:0;font-size:30px;line-height:1.1}
			.cmx-telefonbuch-sub{margin:8px 0 0;color:#6b7280;font-size:14px}
			.cmx-telefonbuch-tools{padding:18px 28px 0}
			.cmx-telefonbuch-tools input{width:100%;max-width:360px;padding:10px 12px;border:1px solid #c8c8c8;border-radius:10px;font:inherit}
			.cmx-telefonbuch-table-wrap{padding:18px 28px 28px;overflow-x:auto}
			.cmx-telefonbuch-table{width:100%;border-collapse:collapse;min-width:940px}
			.cmx-telefonbuch-table th,.cmx-telefonbuch-table td{padding:12px 10px;border-bottom:1px solid #ececec;text-align:left;vertical-align:top;line-height:1.35}
			.cmx-telefonbuch-table th{font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:#6b7280;background:#fafafa}
			.cmx-telefonbuch-table tbody tr:nth-child(even){background:#fcfcfc}
			.cmx-telefonbuch-table tbody tr:hover{background:#eaf5ff}
			.cmx-telefonbuch-row-active,.cmx-telefonbuch-row-active:hover{background:#dfefff !important}
			.cmx-telefonbuch-thumb-wrap{width:86px}
			.cmx-telefonbuch-thumb{display:block;width:64px;height:64px;object-fit:contain;border-radius:12px;border:1px solid #e0e0e0;background:#fff;padding:4px}
			.cmx-telefonbuch-thumb-placeholder{display:block;width:64px;height:64px;border-radius:12px;border:1px dashed #d4d4d4;background:#f4f4f4}
			.cmx-telefonbuch-title-row{display:flex;align-items:center;gap:8px;min-width:0}
			.cmx-telefonbuch-title-link,.cmx-telefonbuch-title-text{display:block;font-weight:700;font-size:16px;color:#135e96;text-decoration:none}
			.cmx-telefonbuch-title-link:hover{color:#0a4b79}
			.cmx-telefonbuch-map-link{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:999px;border:1px solid #d7e3ee;background:#f7fbff;color:#135e96;text-decoration:none;flex:0 0 auto}
			.cmx-telefonbuch-map-link:hover{background:#e9f4ff;color:#0a4b79;border-color:#bdd7ee}
			.cmx-telefonbuch-map-icon{display:block;width:15px;height:15px;fill:currentColor}
			.cmx-telefonbuch-detail-link{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:999px;border:1px solid #d7e3ee;background:#fff6eb;color:#b45309;text-decoration:none;flex:0 0 auto}
			.cmx-telefonbuch-detail-link:hover{background:#ffedd5;color:#92400e;border-color:#fdba74}
			.cmx-telefonbuch-detail-link .dashicons{width:16px;height:16px;font-size:16px;line-height:16px}
			.cmx-telefonbuch-subtitle{display:block;margin-top:3px;color:#667085;font-size:13px}
			.cmx-telefonbuch-list{display:flex;flex-direction:column;gap:8px}
			.cmx-telefonbuch-item{display:flex;flex-direction:column;gap:2px}
			.cmx-telefonbuch-item-label{font-size:11px;line-height:1.2;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#98a2b3}
			.cmx-telefonbuch-item a{color:#135e96;text-decoration:none}
			.cmx-telefonbuch-item a:hover{color:#0a4b79}
			.cmx-telefonbuch-empty{padding:28px;color:#6b7280}
			@media (max-width:720px){
				.cmx-telefonbuch-page{padding:18px 12px 24px}
				.cmx-telefonbuch-head-inner{flex-direction:column}
				.cmx-telefonbuch-head-brand{justify-content:flex-start;min-height:0}
				.cmx-telefonbuch-head,.cmx-telefonbuch-tools,.cmx-telefonbuch-table-wrap{padding-left:16px;padding-right:16px}
				.cmx-telefonbuch-title{font-size:24px}
				.cmx-telefonbuch-thumb-wrap{width:72px}
				.cmx-telefonbuch-thumb,.cmx-telefonbuch-thumb-placeholder{width:56px;height:56px}
			}
		</style>';
		echo '</head><body>';
		echo '<div class="cmx-telefonbuch-page"><div class="cmx-telefonbuch-card">';
		echo '<div class="cmx-telefonbuch-head"><div class="cmx-telefonbuch-head-inner">';
		echo '<div class="cmx-telefonbuch-head-copy">';
		echo '<p class="cmx-telefonbuch-kicker"><a href="' . \esc_url($reload_url) . '">Kontaktübersicht</a></p>';
		echo '<h1 class="cmx-telefonbuch-title">Telefonbuch</h1>';
		echo '<p class="cmx-telefonbuch-sub"><a href="' . \esc_url($reload_url) . '" id="cmx-telefonbuch-count">' . \esc_html(\count($rows) . ' Kontakte') . '</a></p>';
		echo '</div>';
		if ($me_logo_url !== '') {
			echo '<div class="cmx-telefonbuch-head-brand">';
			if ($me_contact_url !== '') {
				echo '<a href="' . \esc_url($me_contact_url) . '" target="_blank" rel="noopener noreferrer" title="' . \esc_attr($me_contact_title) . '">';
			}
			echo '<img class="cmx-telefonbuch-head-logo" src="' . \esc_url($me_logo_url) . '" alt="Logo">';
			if ($me_contact_url !== '') {
				echo '</a>';
			}
			echo '</div>';
		}
		echo '</div></div>';

		if ($rows === []) {
			echo '<div class="cmx-telefonbuch-empty">Aktuell keine Kontakte mit Kommunikationsdaten gefunden.</div>';
			echo '</div></div></body></html>';
			exit;
		}

		echo '<div class="cmx-telefonbuch-tools">';
		echo '<input type="search" id="cmx-telefonbuch-search" placeholder="Kontakt durchsuchen">';
		echo '</div>';
		echo '<div class="cmx-telefonbuch-table-wrap">';
		echo '<table class="cmx-telefonbuch-table"><thead><tr>';
		echo '<th>Logo</th>';
		echo '<th>Kontakt</th>';
		echo '<th>Telefon</th>';
		echo '<th>E-Mail</th>';
		echo '<th>Website</th>';
		echo '</tr></thead><tbody id="cmx-telefonbuch-table-body">';

		foreach ($rows as $row) {
			$title = (string) ($row['title'] ?? '');
			$subtitle = (string) ($row['subtitle'] ?? '');
			$image_url = (string) ($row['image_url'] ?? '');
			$edit_url = (string) ($row['edit_url'] ?? '');
			$website = (string) ($row['website'] ?? '');
			$website_label = (string) ($row['website_label'] ?? '');
			$maps_address = (string) ($row['maps_address'] ?? '');
			$maps_url = (string) ($row['maps_url'] ?? '');
			$detail_url = (string) ($row['detail_url'] ?? '');
			$search = (string) ($row['search'] ?? '');
			$phones = (array) ($row['phones'] ?? []);
			$emails = (array) ($row['emails'] ?? []);

			echo '<tr data-search="' . \esc_attr(\function_exists('mb_strtolower') ? \mb_strtolower($search, 'UTF-8') : \strtolower($search)) . '">';
			echo '<td class="cmx-telefonbuch-thumb-wrap">';
			if ($image_url !== '') {
				echo '<img src="' . \esc_url($image_url) . '" alt="' . \esc_attr($title) . '" class="cmx-telefonbuch-thumb">';
			} else {
				echo '<span class="cmx-telefonbuch-thumb-placeholder" aria-hidden="true"></span>';
			}
			echo '</td>';

			echo '<td>';
			echo '<div class="cmx-telefonbuch-title-row">';
			if ($maps_url !== '') {
				echo '<a class="cmx-telefonbuch-map-link" href="' . \esc_url($maps_url) . '" target="_blank" rel="noopener noreferrer" title="' . \esc_attr('In Google Maps öffnen: ' . $maps_address) . '" aria-label="' . \esc_attr('Adresse in Google Maps öffnen') . '">';
				echo '<svg class="cmx-telefonbuch-map-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.34 6.1 12.15 6.36 12.44a.86.86 0 0 0 1.28 0C12.9 21.15 19 14.34 19 9a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5Z"/></svg>';
				echo '</a>';
			}
			if ($detail_url !== '') {
				echo '<a class="cmx-telefonbuch-detail-link" href="' . \esc_url($detail_url) . '" data-detail-url="' . \esc_url($detail_url) . '" title="Kontaktdetails öffnen" aria-label="Kontaktdetails öffnen">';
				echo '<span class="dashicons dashicons-carrot" aria-hidden="true"></span>';
				echo '</a>';
			}
			if ($edit_url !== '') {
				echo '<a class="cmx-telefonbuch-title-link" href="' . \esc_url($edit_url) . '">' . \esc_html($title) . '</a>';
			} else {
				echo '<span class="cmx-telefonbuch-title-text">' . \esc_html($title) . '</span>';
			}
			echo '</div>';
			if ($subtitle !== '') {
				echo '<span class="cmx-telefonbuch-subtitle">' . \esc_html($subtitle) . '</span>';
			}
			echo '</td>';

			echo '<td>';
			if ($phones === []) {
				echo '<span class="cmx-telefonbuch-subtitle">-</span>';
			} else {
				echo '<div class="cmx-telefonbuch-list">';
				foreach ($phones as $phone) {
					$label = \trim((string) ($phone['label'] ?? ''));
					$display = (string) ($phone['display'] ?? '');
					$href = (string) ($phone['href'] ?? '');
					echo '<div class="cmx-telefonbuch-item">';
					if ($label !== '') {
						echo '<span class="cmx-telefonbuch-item-label">' . \esc_html($label) . '</span>';
					}
					if ($href !== '') {
						echo '<a href="' . \esc_attr($href) . '">' . \esc_html($display) . '</a>';
					} else {
						echo '<span>' . \esc_html($display) . '</span>';
					}
					echo '</div>';
				}
				echo '</div>';
			}
			echo '</td>';

			echo '<td>';
			if ($emails === []) {
				echo '<span class="cmx-telefonbuch-subtitle">-</span>';
			} else {
				echo '<div class="cmx-telefonbuch-list">';
				foreach ($emails as $email) {
					$label = \trim((string) ($email['label'] ?? ''));
					$display = (string) ($email['display'] ?? '');
					$href = (string) ($email['href'] ?? '');
					echo '<div class="cmx-telefonbuch-item">';
					if ($label !== '') {
						echo '<span class="cmx-telefonbuch-item-label">' . \esc_html($label) . '</span>';
					}
					if ($href !== '') {
						echo '<a href="' . \esc_attr($href) . '">' . \esc_html($display) . '</a>';
					} else {
						echo '<span>' . \esc_html($display) . '</span>';
					}
					echo '</div>';
				}
				echo '</div>';
			}
			echo '</td>';

			echo '<td>';
			if ($website !== '') {
				echo '<a href="' . \esc_url($website) . '" target="_blank" rel="noopener noreferrer">' . \esc_html($website_label !== '' ? $website_label : $website) . '</a>';
			} else {
				echo '<span class="cmx-telefonbuch-subtitle">-</span>';
			}
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div></div></div>';
		echo '<script>
			(function(){
				var input=document.getElementById("cmx-telefonbuch-search");
				var body=document.getElementById("cmx-telefonbuch-table-body");
				var countNode=document.getElementById("cmx-telefonbuch-count");
				var activeRow=null;
				if(!input||!body){return;}
				function getRows(){return [].slice.call(body.querySelectorAll("tr[data-search]"));}
				function getVisibleRows(){return getRows().filter(function(row){return row.style.display!=="none";});}
				function getDetailLink(row){return row ? row.querySelector("a[data-detail-url]") : null;}
				function getSelectableRows(){return getVisibleRows().filter(function(row){return !!getDetailLink(row);});}
				function normalize(txt){return String(txt||"").toLowerCase().trim();}
				function updateCount(visible){
					if(!countNode){return;}
					countNode.textContent=visible + " Kontakte";
				}
				function setActiveRow(row, scrollIntoView){
					if(activeRow && activeRow!==row){
						activeRow.classList.remove("cmx-telefonbuch-row-active");
						activeRow.removeAttribute("aria-selected");
					}
					activeRow=row||null;
					if(!activeRow){return;}
					activeRow.classList.add("cmx-telefonbuch-row-active");
					activeRow.setAttribute("aria-selected","true");
					if(scrollIntoView!==false){
						try{
							activeRow.scrollIntoView({block:"nearest",inline:"nearest"});
						}catch(e){
							activeRow.scrollIntoView(false);
						}
					}
				}
				function syncActiveRow(){
					var rows=getSelectableRows();
					if(!rows.length){
						setActiveRow(null,false);
						return;
					}
					if(activeRow && rows.indexOf(activeRow)!==-1){
						return;
					}
					if(normalize(input.value)!==""){
						setActiveRow(rows[0],false);
						return;
					}
					setActiveRow(null,false);
				}
				function moveActiveRow(delta){
					var rows=getSelectableRows();
					var nextIndex;
					if(!rows.length){return;}
					nextIndex=rows.indexOf(activeRow);
					if(nextIndex===-1){
						setActiveRow(delta<0 ? rows[rows.length-1] : rows[0]);
						return;
					}
					nextIndex+=delta;
					if(nextIndex<0){nextIndex=0;}
					if(nextIndex>=rows.length){nextIndex=rows.length-1;}
					setActiveRow(rows[nextIndex]);
				}
				function openActiveRow(){
					var row=activeRow;
					var link;
					if(!row){
						var rows=getSelectableRows();
						if(!rows.length){return;}
						if(rows.length===1 || normalize(input.value)!==""){
							row=rows[0];
						}
					}
					link=getDetailLink(row);
					if(link && link.href){
						window.location.href=link.href;
					}
				}
				function filterRows(){
					var term=normalize(input.value);
					var visible=0;
					getRows().forEach(function(row){
						var haystack=normalize(row.getAttribute("data-search")||"");
						var match=term==="" || haystack.indexOf(term)!==-1;
						row.style.display=match ? "" : "none";
						if(match){visible++;}
					});
					syncActiveRow();
					updateCount(visible);
				}
				input.addEventListener("input", filterRows);
				input.addEventListener("keydown", function(event){
					if(event.key==="ArrowDown"){
						event.preventDefault();
						moveActiveRow(1);
						return;
					}
					if(event.key==="ArrowUp"){
						event.preventDefault();
						moveActiveRow(-1);
						return;
					}
					if(event.key==="Enter"){
						if(activeRow || getSelectableRows().length===1 || normalize(input.value)!==""){
							event.preventDefault();
							openActiveRow();
						}
						return;
					}
					if(event.key==="Escape"){
						event.preventDefault();
						input.value="";
						filterRows();
						input.focus();
					}
				});
				filterRows();
			})();
		</script>';
		echo '</body></html>';
		exit;
	}
}

\add_action('template_redirect', function (): void {
	if (!cmx_is_telefonbuch_request()) {
		return;
	}

	if (!\is_user_logged_in()) {
		if (!\defined('DONOTCACHEPAGE')) {
			\define('DONOTCACHEPAGE', true);
		}
		\nocache_headers();
		\auth_redirect();
		exit;
	}
}, 1);

\add_action('template_redirect', __NAMESPACE__ . '\\cmx_render_telefonbuch_page', 5);

\add_action('admin_bar_menu', function (\WP_Admin_Bar $bar): void {
	if (!cmx_is_telefonbuch_request()) {
		return;
	}

	$bar->remove_node('site-editor');
	$bar->remove_node('edit');
	$bar->remove_node('updates');
}, 999);

\add_filter('wp_get_nav_menu_items', function (array $items, $menu, $args) {
	if (\is_user_logged_in()) {
		return $items;
	}

	$filtered = [];
	foreach ($items as $item) {
		$url = isset($item->url) ? (string) $item->url : '';
		$path = \parse_url($url, \PHP_URL_PATH);
		$path = \is_string($path) ? \trim($path, '/') : '';
		if ($path === 'telefonbuch' || \str_starts_with($path, 'telefonbuch/')) {
			continue;
		}
		$filtered[] = $item;
	}

	return $filtered;
}, 10, 3);
