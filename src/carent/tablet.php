<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_feature_enabled')) {
	function cmx_vermietung_feature_enabled(): bool {
		$module_enabled = \function_exists(__NAMESPACE__ . '\\cmx_system_is_carent_enabled')
			? (bool) cmx_system_is_carent_enabled()
			: true;

		return $module_enabled && \post_type_exists('carent');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_url')) {
	function cmx_vermietung_url(): string {
		if (!cmx_vermietung_feature_enabled()) {
			return '';
		}

		return (string) \home_url('/vermietung/');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_manage_url')) {
	function cmx_vermietung_manage_url(int $post_id = 0, array $args = []): string {
		$query_args = [];
		if ($post_id > 0) {
			$query_args['cmx_vermietung_post_id'] = $post_id;
		}

		foreach ($args as $key => $value) {
			if (!\is_scalar($value) || $value === '') {
				continue;
			}
			$query_args[(string) $key] = $value;
		}

		return \add_query_arg($query_args, cmx_vermietung_url());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_current_post_id')) {
	function cmx_vermietung_current_post_id(): int {
		$post_id = isset($_REQUEST['cmx_vermietung_post_id']) ? (int) \wp_unslash($_REQUEST['cmx_vermietung_post_id']) : 0;
		if ($post_id <= 0) {
			return 0;
		}

		$post = \get_post($post_id);
		if (!$post || $post->post_type !== 'carent') {
			return 0;
		}

		if (!\current_user_can('edit_post', $post_id)) {
			return 0;
		}

		return $post_id;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_is_vermietung_request')) {
	function cmx_is_vermietung_request(): bool {
		if (\is_admin() || !cmx_vermietung_feature_enabled()) {
			return false;
		}

		$req_path = \parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), \PHP_URL_PATH);
		$req_path = \is_string($req_path) ? \trim($req_path, '/') : '';

		return $req_path === 'vermietung' || \str_starts_with($req_path, 'vermietung/') || \is_page('vermietung');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_can_create_carent')) {
	function cmx_vermietung_can_create_carent(): bool {
		if (!cmx_vermietung_feature_enabled()) {
			return false;
		}

		$obj = \get_post_type_object('carent');
		if (!$obj) {
			return false;
		}

		$cap = (string) ($obj->cap->create_posts ?? '');
		if ($cap === '') {
			$cap = 'edit_posts';
		}

		return \current_user_can($cap);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_contact_rows')) {
	function cmx_vermietung_contact_rows(): array {
		$post_types = \function_exists(__NAMESPACE__ . '\\cmx_carent_kontakt_post_types')
			? (array) cmx_carent_kontakt_post_types()
			: ['kontakte'];
		$post_types = \array_values(\array_filter(\array_map('strval', $post_types)));
		if ($post_types === []) {
			return [];
		}

		$args = [
			'post_type'              => $post_types,
			'post_status'            => ['publish', 'private'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
			'orderby'                => 'title',
			'order'                  => 'ASC',
		];
		if (\function_exists(__NAMESPACE__ . '\\cmx_kontakte_apply_selection_query_args')) {
			$args = cmx_kontakte_apply_selection_query_args($args);
		}

		$query = new \WP_Query($args);
		$rows = [];

		foreach ((array) $query->posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0) {
				continue;
			}

			$phone = '';
			$email = '';
			$search = '';
			$title = \trim((string) \get_the_title($post_id));
			$subtitle = '';
			$image_url = '';

			if (\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_contact_row')) {
				$row = (array) cmx_telefonbuch_contact_row($post_id);
				$title = \trim((string) ($row['title'] ?? $title));
				$subtitle = \trim((string) ($row['subtitle'] ?? ''));
				$image_url = (string) ($row['image_url'] ?? '');
				$search = (string) ($row['search'] ?? '');
				$phones = (array) ($row['phones'] ?? []);
				$emails = (array) ($row['emails'] ?? []);
				$phone = \trim((string) (($phones[0]['display'] ?? '') ?: ''));
				$email = \trim((string) (($emails[0]['display'] ?? '') ?: ''));
			}

			if ($title === '') {
				$title = '#' . $post_id;
			}

			$meta = \implode(' · ', \array_values(\array_filter([$subtitle, $phone !== '' ? $phone : $email], static fn(string $value): bool => \trim($value) !== '')));
			$search_text = \trim($search !== '' ? $search : \implode(' ', [$title, $subtitle, $phone, $email]));

			$rows[] = [
				'id'        => $post_id,
				'title'     => $title,
				'meta'      => $meta,
				'image_url' => $image_url,
				'search'    => \function_exists('mb_strtolower') ? \mb_strtolower($search_text, 'UTF-8') : \strtolower($search_text),
			];
		}

		return $rows;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_vehicle_rows')) {
	function cmx_vermietung_vehicle_rows(): array {
		$post_type = \function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_post_type')
			? (string) cmx_carent_fahrzeug_post_type()
			: 'artikel';
		if ($post_type === '' || !\post_type_exists($post_type)) {
			return [];
		}

		$category_taxonomy = '';
		if (\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_TYPEN')) {
			$category_taxonomy = (string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_TYPEN');
		}
		if ($category_taxonomy === '' && \function_exists(__NAMESPACE__ . '\\cmx_tax_typen')) {
			$category_taxonomy = (string) cmx_tax_typen();
		}
		if ($category_taxonomy === '' && \function_exists(__NAMESPACE__ . '\\cmx_artikel_taxonomy_slug')) {
			$category_taxonomy = (string) cmx_artikel_taxonomy_slug('Typen');
		}
		if ($category_taxonomy === '' || !\taxonomy_exists($category_taxonomy)) {
			return [];
		}

		$category_term = \get_term_by('slug', 'mietfahrzeug', $category_taxonomy);
		if (!$category_term || \is_wp_error($category_term)) {
			$category_term = \get_term_by('name', 'Mietfahrzeug', $category_taxonomy);
		}
		if (!$category_term || \is_wp_error($category_term)) {
			return [];
		}

		$query = new \WP_Query([
			'post_type'              => $post_type,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'tax_query'              => [
				[
					'taxonomy' => $category_taxonomy,
					'field'    => 'term_id',
					'terms'    => [(int) $category_term->term_id],
				],
			],
		]);

		$kennzeichen_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KENNZEICHEN')
			? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KENNZEICHEN')
			: '_cmx_artikel_carent_kennzeichen';
		$chassi_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_CHASSI_NR')
			? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_CHASSI_NR')
			: '_cmx_artikel_carent_chassi_nr';

		$rows = [];
		foreach ((array) $query->posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0) {
				continue;
			}

			$title = \function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_display_label')
				? \trim((string) cmx_carent_fahrzeug_display_label($post_id))
				: \trim((string) \get_the_title($post_id));
			if ($title === '') {
				$title = '#' . $post_id;
			}

			$kennzeichen = \trim((string) \get_post_meta($post_id, $kennzeichen_key, true));
			$chassi = \trim((string) \get_post_meta($post_id, $chassi_key, true));
			$details = \function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_article_meta_defaults')
				? (array) cmx_carent_fahrzeug_article_meta_defaults($post_id)
				: [];
			$mietpreis_meta_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VK')
				? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VK')
				: '_cmx_artikel_vk';
			$mietpreis = \trim((string) \get_post_meta($post_id, $mietpreis_meta_key, true));
			if ($mietpreis !== '') {
				$mietpreis = cmx_carent_fahrzeug_normalize_decimal($mietpreis);
			}
			$subtitle_parts = [];
			if ($kennzeichen !== '') {
				$subtitle_parts[] = 'Kennzeichen ' . $kennzeichen;
			}
			if ($chassi !== '') {
				$subtitle_parts[] = 'Chassi ' . $chassi;
			}

			$nr = \function_exists(__NAMESPACE__ . '\\cmx_get_artikel_nr')
				? \trim((string) cmx_get_artikel_nr($post_id))
				: '';
			$search_text = \implode(' ', \array_filter([$title, $nr, $kennzeichen, $chassi], static fn(string $value): bool => \trim($value) !== ''));

			$rows[] = [
				'id'        => $post_id,
				'title'     => $title,
				'meta'      => \implode(' · ', $subtitle_parts),
				'image_url' => (string) \get_the_post_thumbnail_url($post_id, 'thumbnail'),
				'search'    => \function_exists('mb_strtolower') ? \mb_strtolower($search_text, 'UTF-8') : \strtolower($search_text),
				'kennzeichen'          => \trim((string) ($details['kennzeichen'] ?? $kennzeichen)),
				'km_stand_uebernahme'  => \trim((string) ($details['km_stand_uebernahme'] ?? '')),
				'begrenzung'           => \trim((string) ($details['begrenzung'] ?? '')),
				'mehrpreis'            => \trim((string) ($details['mehrpreis'] ?? '')),
				'kasko_min'            => \trim((string) ($details['kasko_min'] ?? '')),
				'kasko_max'            => \trim((string) ($details['kasko_max'] ?? '')),
				'mietpreis'            => $mietpreis,
			];
		}

		return $rows;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_vehicle_detail_values')) {
	function cmx_vermietung_vehicle_detail_values(int $vehicle_id, int $carent_id = 0): array {
		$mietpreis_meta_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VK')
			? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VK')
			: '_cmx_artikel_vk';
		$defaults = \function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_article_meta_defaults')
			? (array) cmx_carent_fahrzeug_article_meta_defaults($vehicle_id)
			: [
				'kennzeichen'         => '',
				'km_stand_uebernahme' => '',
				'begrenzung'          => '',
				'mehrpreis'           => '',
				'kasko_min'           => '',
				'kasko_max'           => '',
			];
		$default_mietpreis = $vehicle_id > 0
			? \trim((string) \get_post_meta($vehicle_id, $mietpreis_meta_key, true))
			: '';
		if ($default_mietpreis !== '') {
			$default_mietpreis = cmx_carent_fahrzeug_normalize_decimal($default_mietpreis);
		}

		$values = [
			'kennzeichen'         => \trim((string) ($defaults['kennzeichen'] ?? '')),
			'km_stand_uebernahme' => \trim((string) ($defaults['km_stand_uebernahme'] ?? '')),
			'begrenzung'          => \trim((string) ($defaults['begrenzung'] ?? '')),
			'mehrpreis'           => \trim((string) ($defaults['mehrpreis'] ?? '')),
			'kasko_min'           => \trim((string) ($defaults['kasko_min'] ?? '')),
			'kasko_max'           => \trim((string) ($defaults['kasko_max'] ?? '')),
			'mietpreis'           => $default_mietpreis,
		];

		if ($carent_id > 0 && (int) \get_post_meta($carent_id, CMX_CARENT_FAHRZEUG_META, true) === $vehicle_id) {
			$meta_map = [
				'kennzeichen' => '_cmx_carent_fahrzeug_kennzeichen',
				'km_stand_uebernahme' => \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_STAND_UEBERNAHME_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_STAND_UEBERNAHME_META')
					: '_cmx_carent_fahrzeug_km_stand_uebernahme',
				'begrenzung' => \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_BEGRENZUNG_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_BEGRENZUNG_META')
					: '_cmx_carent_fahrzeug_km_begrenzung',
				'mehrpreis' => \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_MEHRPREIS_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_MEHRPREIS_META')
					: '_cmx_carent_fahrzeug_km_mehrpreis',
				'kasko_min' => \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KASKO_MIN_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KASKO_MIN_META')
					: '_cmx_carent_fahrzeug_kasko_min',
				'kasko_max' => \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KASKO_MAX_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KASKO_MAX_META')
					: '_cmx_carent_fahrzeug_kasko_max',
				'mietpreis' => '_cmx_carent_mietpreis',
			];

			foreach ($meta_map as $key => $meta_key) {
				$override = \trim((string) \get_post_meta($carent_id, $meta_key, true));
				if ($override !== '') {
					$values[$key] = $override;
				}
			}
		}

		return $values;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_save_vehicle_detail_values')) {
	function cmx_vermietung_save_vehicle_detail_values(int $post_id): void {
		$kennzeichen = isset($_POST['cmx_vermietung_fahrzeug_kennzeichen'])
			? \trim((string) \wp_unslash($_POST['cmx_vermietung_fahrzeug_kennzeichen']))
			: '';
		if (\function_exists(__NAMESPACE__ . '\\cmx_artikel_carent_normalize_kennzeichen')) {
			$kennzeichen = \trim((string) cmx_artikel_carent_normalize_kennzeichen($kennzeichen));
		}
		if ($kennzeichen === '') {
			\delete_post_meta($post_id, '_cmx_carent_fahrzeug_kennzeichen');
		} else {
			\update_post_meta($post_id, '_cmx_carent_fahrzeug_kennzeichen', $kennzeichen);
		}

		$begrenzung = isset($_POST['cmx_vermietung_fahrzeug_begrenzung'])
			? cmx_carent_fahrzeug_normalize_int(\wp_unslash($_POST['cmx_vermietung_fahrzeug_begrenzung']))
			: '';
		$mehrpreis = isset($_POST['cmx_vermietung_fahrzeug_mehrpreis'])
			? cmx_carent_fahrzeug_normalize_decimal(\wp_unslash($_POST['cmx_vermietung_fahrzeug_mehrpreis']))
			: '';
		$kasko_min = isset($_POST['cmx_vermietung_fahrzeug_kasko_min'])
			? cmx_carent_fahrzeug_normalize_decimal(\wp_unslash($_POST['cmx_vermietung_fahrzeug_kasko_min']))
			: '';
		$kasko_max = isset($_POST['cmx_vermietung_fahrzeug_kasko_max'])
			? cmx_carent_fahrzeug_normalize_decimal(\wp_unslash($_POST['cmx_vermietung_fahrzeug_kasko_max']))
			: '';
		$mietpreis = isset($_POST['cmx_vermietung_fahrzeug_mietpreis'])
			? cmx_carent_fahrzeug_normalize_decimal(\wp_unslash($_POST['cmx_vermietung_fahrzeug_mietpreis']))
			: '';

		$meta_map = [
			(\defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_BEGRENZUNG_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_BEGRENZUNG_META')
				: '_cmx_carent_fahrzeug_km_begrenzung') => $begrenzung,
			(\defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_MEHRPREIS_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_MEHRPREIS_META')
				: '_cmx_carent_fahrzeug_km_mehrpreis') => $mehrpreis,
			(\defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KASKO_MIN_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KASKO_MIN_META')
				: '_cmx_carent_fahrzeug_kasko_min') => $kasko_min,
			(\defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KASKO_MAX_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KASKO_MAX_META')
				: '_cmx_carent_fahrzeug_kasko_max') => $kasko_max,
			'_cmx_carent_mietpreis' => $mietpreis,
		];

		foreach ($meta_map as $meta_key => $value) {
			if ($value === '') {
				\delete_post_meta($post_id, $meta_key);
			} else {
				\update_post_meta($post_id, $meta_key, $value);
			}
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_save_uebernahme_values')) {
	function cmx_vermietung_save_uebernahme_values(int $post_id): void {
		$ort_meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_ORT_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_ORT_META')
			: '_cmx_carent_uebernahme_ort';
		$datum_meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_DATUM_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_DATUM_META')
			: '_cmx_carent_uebernahme_datum';
		$uhrzeit_meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_UHRZEIT_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_UHRZEIT_META')
			: '_cmx_carent_uebernahme_uhrzeit';
		$km_meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_STAND_UEBERNAHME_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_STAND_UEBERNAHME_META')
			: '_cmx_carent_fahrzeug_km_stand_uebernahme';

		$ort = isset($_POST['cmx_vermietung_uebernahme_ort'])
			? \trim((string) \wp_unslash($_POST['cmx_vermietung_uebernahme_ort']))
			: '';
		$datum = isset($_POST['cmx_vermietung_uebernahme_datum'])
			? \trim((string) \wp_unslash($_POST['cmx_vermietung_uebernahme_datum']))
			: '';
		$uhrzeit = isset($_POST['cmx_vermietung_uebernahme_uhrzeit'])
			? \trim((string) \wp_unslash($_POST['cmx_vermietung_uebernahme_uhrzeit']))
			: '';
		$km_stand = isset($_POST['cmx_vermietung_fahrzeug_km_stand_uebernahme'])
			? cmx_carent_fahrzeug_normalize_int(\wp_unslash($_POST['cmx_vermietung_fahrzeug_km_stand_uebernahme']))
			: '';

		if ($ort === '') {
			\delete_post_meta($post_id, $ort_meta_key);
		} else {
			\update_post_meta($post_id, $ort_meta_key, $ort);
		}

		if ($datum === '' || !\preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
			\delete_post_meta($post_id, $datum_meta_key);
		} else {
			\update_post_meta($post_id, $datum_meta_key, $datum);
		}

		if ($uhrzeit === '' || !\preg_match('/^\d{2}:\d{2}$/', $uhrzeit)) {
			\delete_post_meta($post_id, $uhrzeit_meta_key);
		} else {
			\update_post_meta($post_id, $uhrzeit_meta_key, $uhrzeit);
		}

		if ($km_stand === '') {
			\delete_post_meta($post_id, $km_meta_key);
		} else {
			\update_post_meta($post_id, $km_meta_key, $km_stand);
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_contract_rows')) {
	function cmx_vermietung_contract_rows(): array {
		if (!\post_type_exists('carent')) {
			return [];
		}

		$query = new \WP_Query([
			'post_type'              => 'carent',
			'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
		]);

		$rows = [];
		foreach ((array) $query->posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0 || !\current_user_can('edit_post', $post_id)) {
				continue;
			}

			$title = \trim((string) \get_the_title($post_id));
			if ($title === '') {
				$title = 'Vertrag #' . $post_id;
			}

			$contact_id = (int) \get_post_meta($post_id, CMX_CARENT_KONTAKT_META, true);
			$vehicle_id = (int) \get_post_meta($post_id, CMX_CARENT_FAHRZEUG_META, true);
			$contact_title = $contact_id > 0 ? \trim((string) \get_the_title($contact_id)) : '';
			$vehicle_title = '';
			if ($vehicle_id > 0) {
				$vehicle_title = \function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_display_label')
					? \trim((string) cmx_carent_fahrzeug_display_label($vehicle_id))
					: \trim((string) \get_the_title($vehicle_id));
			}

			$meta_parts = [];
			if ($contact_title !== '') {
				$meta_parts[] = $contact_title;
			}
			if ($vehicle_title !== '') {
				$meta_parts[] = $vehicle_title;
			}

			$search_text = \implode(' ', \array_filter([$title, $contact_title, $vehicle_title, (string) $post_id], static fn(string $value): bool => \trim($value) !== ''));

			$rows[] = [
				'id'     => $post_id,
				'title'  => $title,
				'meta'   => \implode(' · ', $meta_parts),
				'search' => \function_exists('mb_strtolower') ? \mb_strtolower($search_text, 'UTF-8') : \strtolower($search_text),
				'url'    => cmx_vermietung_manage_url($post_id),
			];
		}

		return $rows;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_error_message')) {
	function cmx_vermietung_error_message(string $code): string {
		return match ($code) {
			'unauthorized'    => 'Du darfst keine Vermietung anlegen.',
			'invalid_nonce'   => 'Die Vermietung konnte nicht gestartet werden. Bitte die Seite neu laden.',
			'missing_contact' => 'Bitte zuerst einen Kontakt auswählen.',
			'missing_vehicle' => 'Bitte zuerst ein Fahrzeug auswählen.',
			'missing_license_photo' => 'Bitte den Führerausweis hochladen.',
			'invalid_contact' => 'Der gewählte Kontakt ist nicht gültig.',
			'invalid_vehicle' => 'Das gewählte Fahrzeug ist nicht gültig.',
			'invalid_license_photo' => 'Bitte nur Bilddateien für den Führerausweis hochladen.',
			'invalid_identity_photo' => 'Bitte nur Bilddateien für die Identitätskarte hochladen.',
			'upload_failed'   => 'Der Führerausweis konnte nicht hochgeladen werden.',
			'identity_upload_failed' => 'Die Identitätskarte konnte nicht hochgeladen werden.',
			'create_failed'   => 'Die Vermietung konnte nicht angelegt werden.',
			default           => '',
		};
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_status_message')) {
	function cmx_vermietung_status_message(string $code): string {
		return match ($code) {
			'saved'   => 'Vermietung wurde gespeichert.',
			'updated' => 'Vermietung wurde aktualisiert.',
			default   => '',
		};
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_handle_vermietung_create')) {
	function cmx_handle_vermietung_create(): void {
		if (!cmx_vermietung_feature_enabled()) {
			\wp_safe_redirect((string) \admin_url('edit.php?post_type=carent'));
			exit;
		}

		if (!\is_user_logged_in()) {
			if (!\defined('DONOTCACHEPAGE')) {
				\define('DONOTCACHEPAGE', true);
			}
			\nocache_headers();
			\auth_redirect();
			exit;
		}

		if (!cmx_vermietung_can_create_carent()) {
			\wp_safe_redirect(\add_query_arg('cmx_vermietung_error', 'unauthorized', cmx_vermietung_url()));
			exit;
		}

		$nonce = isset($_POST['cmx_vermietung_nonce']) ? (string) \wp_unslash($_POST['cmx_vermietung_nonce']) : '';
		if (!\wp_verify_nonce($nonce, 'cmx_vermietung_create')) {
			\wp_safe_redirect(\add_query_arg('cmx_vermietung_error', 'invalid_nonce', cmx_vermietung_url()));
			exit;
		}

		$current_post_id = isset($_POST['cmx_vermietung_post_id']) ? (int) \wp_unslash($_POST['cmx_vermietung_post_id']) : 0;
		if ($current_post_id > 0) {
			$current_post = \get_post($current_post_id);
			if (!$current_post || $current_post->post_type !== 'carent' || !\current_user_can('edit_post', $current_post_id)) {
				\wp_safe_redirect(\add_query_arg('cmx_vermietung_error', 'unauthorized', cmx_vermietung_url()));
				exit;
			}
		}

		$kontakt_id = isset($_POST['cmx_vermietung_kontakt_id']) ? (int) $_POST['cmx_vermietung_kontakt_id'] : 0;
		$artikel_id = isset($_POST['cmx_vermietung_artikel_id']) ? (int) $_POST['cmx_vermietung_artikel_id'] : 0;
		$license_meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_FUEHRERAUSWEIS_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FUEHRERAUSWEIS_META')
			: '_cmx_carent_fuehrerausweis_attachment_id';
		$license_upload = $_FILES['cmx_vermietung_fuehrerausweis_file'] ?? null;
		$identity_upload = $_FILES['cmx_vermietung_identitaetskarte_file'] ?? null;
		$has_license_upload = \is_array($license_upload)
			&& isset($license_upload['error'], $license_upload['name'])
			&& (int) $license_upload['error'] !== \UPLOAD_ERR_NO_FILE
			&& \trim((string) $license_upload['name']) !== '';
		$existing_license_attachment_id = $current_post_id > 0
			? (int) \get_post_meta($current_post_id, $license_meta_key, true)
			: 0;
		$has_identity_upload = \is_array($identity_upload)
			&& isset($identity_upload['error'], $identity_upload['name'])
			&& (int) $identity_upload['error'] !== \UPLOAD_ERR_NO_FILE
			&& \trim((string) $identity_upload['name']) !== '';
		$redirect_post_id = $current_post_id > 0 ? $current_post_id : 0;
		if ($kontakt_id <= 0) {
			\wp_safe_redirect(cmx_vermietung_manage_url($redirect_post_id, ['cmx_vermietung_error' => 'missing_contact']));
			exit;
		}
		if ($artikel_id <= 0) {
			\wp_safe_redirect(cmx_vermietung_manage_url($redirect_post_id, ['cmx_vermietung_error' => 'missing_vehicle']));
			exit;
		}

		$kontakt_types = \function_exists(__NAMESPACE__ . '\\cmx_carent_kontakt_post_types')
			? (array) cmx_carent_kontakt_post_types()
			: ['kontakte'];
		if ($kontakt_id <= 0 || !\get_post_status($kontakt_id) || !\in_array((string) \get_post_type($kontakt_id), $kontakt_types, true)) {
			\wp_safe_redirect(cmx_vermietung_manage_url($redirect_post_id, ['cmx_vermietung_error' => 'invalid_contact']));
			exit;
		}

		$artikel_type = \function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_post_type')
			? (string) cmx_carent_fahrzeug_post_type()
			: 'artikel';
		if ($artikel_id <= 0 || !\get_post_status($artikel_id) || (string) \get_post_type($artikel_id) !== $artikel_type) {
			\wp_safe_redirect(cmx_vermietung_manage_url($redirect_post_id, ['cmx_vermietung_error' => 'invalid_vehicle']));
			exit;
		}
		if (!$has_license_upload && $existing_license_attachment_id <= 0) {
			\wp_safe_redirect(cmx_vermietung_manage_url($redirect_post_id, ['cmx_vermietung_error' => 'missing_license_photo']));
			exit;
		}
		if ($has_license_upload) {
			$license_error = (int) ($license_upload['error'] ?? \UPLOAD_ERR_NO_FILE);
			if ($license_error !== \UPLOAD_ERR_OK) {
				\wp_safe_redirect(cmx_vermietung_manage_url($redirect_post_id, ['cmx_vermietung_error' => 'upload_failed']));
				exit;
			}

			$file_type = \wp_check_filetype_and_ext((string) ($license_upload['tmp_name'] ?? ''), (string) ($license_upload['name'] ?? ''));
			$mime_type = (string) ($file_type['type'] ?? '');
			if (!\str_starts_with($mime_type, 'image/')) {
				\wp_safe_redirect(cmx_vermietung_manage_url($redirect_post_id, ['cmx_vermietung_error' => 'invalid_license_photo']));
				exit;
			}
		}
		if ($has_identity_upload) {
			$identity_error = (int) ($identity_upload['error'] ?? \UPLOAD_ERR_NO_FILE);
			if ($identity_error !== \UPLOAD_ERR_OK) {
				\wp_safe_redirect(cmx_vermietung_manage_url($redirect_post_id, ['cmx_vermietung_error' => 'identity_upload_failed']));
				exit;
			}

			$file_type = \wp_check_filetype_and_ext((string) ($identity_upload['tmp_name'] ?? ''), (string) ($identity_upload['name'] ?? ''));
			$mime_type = (string) ($file_type['type'] ?? '');
			if (!\str_starts_with($mime_type, 'image/')) {
				\wp_safe_redirect(cmx_vermietung_manage_url($redirect_post_id, ['cmx_vermietung_error' => 'invalid_identity_photo']));
				exit;
			}
		}

		if ($current_post_id > 0) {
			$post_id = $current_post_id;
		} else {
			$post_id = \wp_insert_post([
				'post_type'    => 'carent',
				'post_status'  => 'draft',
				'post_title'   => 'Vermietung',
				'post_content' => '',
				'post_author'  => (int) \get_current_user_id(),
			], true);
		}

		if (\is_wp_error($post_id) || (int) $post_id <= 0) {
			\wp_safe_redirect(cmx_vermietung_manage_url($redirect_post_id, ['cmx_vermietung_error' => 'create_failed']));
			exit;
		}

		$post_id = (int) $post_id;
		\update_post_meta($post_id, CMX_CARENT_KONTAKT_META, $kontakt_id);
		\update_post_meta($post_id, CMX_CARENT_FAHRZEUG_META, $artikel_id);
		cmx_vermietung_save_vehicle_detail_values($post_id);
		cmx_vermietung_save_uebernahme_values($post_id);
		if ($has_license_upload) {
			require_once \ABSPATH . 'wp-admin/includes/file.php';
			require_once \ABSPATH . 'wp-admin/includes/media.php';
			require_once \ABSPATH . 'wp-admin/includes/image.php';

			$attachment_id = \media_handle_upload('cmx_vermietung_fuehrerausweis_file', $post_id);
			if (\is_wp_error($attachment_id) || (int) $attachment_id <= 0) {
				\wp_safe_redirect(cmx_vermietung_manage_url($post_id, ['cmx_vermietung_error' => 'upload_failed']));
				exit;
			}

			\update_post_meta($post_id, $license_meta_key, (int) $attachment_id);
		}
		if ($has_identity_upload) {
			require_once \ABSPATH . 'wp-admin/includes/file.php';
			require_once \ABSPATH . 'wp-admin/includes/media.php';
			require_once \ABSPATH . 'wp-admin/includes/image.php';

			$attachment_id = \media_handle_upload('cmx_vermietung_identitaetskarte_file', $post_id);
			if (\is_wp_error($attachment_id) || (int) $attachment_id <= 0) {
				\wp_safe_redirect(cmx_vermietung_manage_url($post_id, ['cmx_vermietung_error' => 'identity_upload_failed']));
				exit;
			}

			$identity_meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_IDENTITAETSKARTE_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_IDENTITAETSKARTE_META')
				: '_cmx_carent_identitaetskarte_attachment_id';
			\update_post_meta($post_id, $identity_meta_key, (int) $attachment_id);
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_carent_composed_title')) {
			$title = \trim((string) cmx_carent_composed_title($post_id));
			if ($title !== '') {
				\wp_update_post([
					'ID'         => $post_id,
					'post_title' => $title,
				]);
			}
		}

		\wp_safe_redirect(cmx_vermietung_manage_url($post_id, [
			'cmx_vermietung_status' => $current_post_id > 0 ? 'updated' : 'saved',
		]));
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_vermietung_page')) {
	function cmx_render_vermietung_page(): void {
		if (!cmx_is_vermietung_request()) {
			return;
		}

		$contacts = cmx_vermietung_contact_rows();
		$vehicles = cmx_vermietung_vehicle_rows();
		$contracts = cmx_vermietung_contract_rows();
		$current_post_id = cmx_vermietung_current_post_id();
		$status_code = isset($_GET['cmx_vermietung_status']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_vermietung_status'])) : '';
		$error_code = isset($_GET['cmx_vermietung_error']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_vermietung_error'])) : '';
		$status_message = cmx_vermietung_status_message($status_code);
		$error_message = cmx_vermietung_error_message($error_code);
		$self_logo_url = \function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_url')
			? (string) cmx_email_self_logo_url()
			: '';
		$self_contact_url = \function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_url')
			? (string) cmx_email_self_contact_url()
			: '';
		$self_contact_title = \function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_branding_text')
			? (string) cmx_email_self_contact_branding_text()
			: '';
		$license_meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_FUEHRERAUSWEIS_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FUEHRERAUSWEIS_META')
			: '_cmx_carent_fuehrerausweis_attachment_id';
		$identity_meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_IDENTITAETSKARTE_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_IDENTITAETSKARTE_META')
			: '_cmx_carent_identitaetskarte_attachment_id';
		$selected_contact_id = $current_post_id > 0 ? (int) \get_post_meta($current_post_id, CMX_CARENT_KONTAKT_META, true) : 0;
		$selected_vehicle_id = $current_post_id > 0 ? (int) \get_post_meta($current_post_id, CMX_CARENT_FAHRZEUG_META, true) : 0;
		$license_attachment_id = $current_post_id > 0 ? (int) \get_post_meta($current_post_id, $license_meta_key, true) : 0;
		$identity_attachment_id = $current_post_id > 0 ? (int) \get_post_meta($current_post_id, $identity_meta_key, true) : 0;
		$license_image_url = $license_attachment_id > 0 ? (string) \wp_get_attachment_image_url($license_attachment_id, 'medium') : '';
		if ($license_attachment_id > 0 && $license_image_url === '') {
			$license_image_url = (string) \wp_get_attachment_url($license_attachment_id);
		}
		$license_filename = $license_attachment_id > 0 ? (string) \basename((string) \get_attached_file($license_attachment_id)) : '';
		$identity_image_url = $identity_attachment_id > 0 ? (string) \wp_get_attachment_image_url($identity_attachment_id, 'medium') : '';
		if ($identity_attachment_id > 0 && $identity_image_url === '') {
			$identity_image_url = (string) \wp_get_attachment_url($identity_attachment_id);
		}
		$identity_filename = $identity_attachment_id > 0 ? (string) \basename((string) \get_attached_file($identity_attachment_id)) : '';
		$selected_contact_row = null;
		foreach ($contacts as $row) {
			if ((int) ($row['id'] ?? 0) === $selected_contact_id) {
				$selected_contact_row = (array) $row;
				break;
			}
		}
		$selected_vehicle_row = null;
		foreach ($vehicles as $row) {
			if ((int) ($row['id'] ?? 0) === $selected_vehicle_id) {
				$selected_vehicle_row = (array) $row;
				break;
			}
		}
		$selected_contact_title = (string) ($selected_contact_row['title'] ?? '');
		$selected_vehicle_title = (string) ($selected_vehicle_row['title'] ?? '');
		$selected_vehicle_details = cmx_vermietung_vehicle_detail_values($selected_vehicle_id, $current_post_id);
		$selected_uebernahme_values = [
			'ort'      => '',
			'datum'    => '',
			'uhrzeit'  => '',
			'km_stand' => \trim((string) ($selected_vehicle_details['km_stand_uebernahme'] ?? '')),
		];
		if ($current_post_id > 0) {
			$selected_uebernahme_values['ort'] = \trim((string) \get_post_meta(
				$current_post_id,
				\defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_ORT_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_ORT_META')
					: '_cmx_carent_uebernahme_ort',
				true
			));
			$selected_uebernahme_values['datum'] = \trim((string) \get_post_meta(
				$current_post_id,
				\defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_DATUM_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_DATUM_META')
					: '_cmx_carent_uebernahme_datum',
				true
			));
			$selected_uebernahme_values['uhrzeit'] = \trim((string) \get_post_meta(
				$current_post_id,
				\defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_UHRZEIT_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_UHRZEIT_META')
					: '_cmx_carent_uebernahme_uhrzeit',
				true
			));
			$stored_km_stand = \trim((string) \get_post_meta(
				$current_post_id,
				\defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_STAND_UEBERNAHME_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_STAND_UEBERNAHME_META')
					: '_cmx_carent_fahrzeug_km_stand_uebernahme',
				true
			));
			if ($stored_km_stand !== '') {
				$selected_uebernahme_values['km_stand'] = $stored_km_stand;
			}
		}
		$selected_contract_row = null;
		foreach ($contracts as $row) {
			if ((int) ($row['id'] ?? 0) === $current_post_id) {
				$selected_contract_row = (array) $row;
				break;
			}
		}
		$selected_contract_title = (string) ($selected_contract_row['title'] ?? '');
		$submit_label = $current_post_id > 0 ? 'aktualisieren' : 'anlegen';
		$license_required = $license_attachment_id <= 0;

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
		echo '<title>Vermietung</title>';
			echo '<style>
			:root{color-scheme:light}
			*{box-sizing:border-box}
			body{margin:0;font-family:Segoe UI,Roboto,Arial,sans-serif;background:#efefef;color:#1d2327}
			.cmx-vermietung-page{max-width:1570px;margin:0 auto;padding:32px 18px 40px}
			.cmx-vermietung-card{position:relative;background:#fff;border:1px solid #ddd;border-radius:14px;box-shadow:0 18px 40px rgba(0,0,0,.06);overflow:visible}
			.cmx-vermietung-head{padding:24px 28px 18px;background:linear-gradient(135deg,#f7f7f7 0%,#ededed 100%);border-bottom:1px solid #e2e2e2}
			.cmx-vermietung-head-inner{display:flex;align-items:flex-start;justify-content:space-between;gap:24px}
			.cmx-vermietung-head-copy{flex:1 1 auto;min-width:0}
			.cmx-vermietung-head-brand{flex:0 0 auto;display:flex;align-items:flex-start;justify-content:flex-end;min-height:84px}
			.cmx-vermietung-head-logo{display:block;max-width:190px;max-height:84px;width:auto;height:auto;object-fit:contain;object-position:right top}
			.cmx-vermietung-kicker{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;margin:0 0 8px}
			.cmx-vermietung-title{margin:0;font-size:30px;line-height:1.1}
			.cmx-vermietung-sub{margin:8px 0 0;color:#6b7280;font-size:14px}
			.cmx-vermietung-body{position:relative;padding:22px 28px 28px;overflow:visible}
			.cmx-vermietung-notice{margin:0 0 18px;padding:12px 14px;border-radius:12px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;font-weight:600}
			.cmx-vermietung-notice.is-success{background:#ecfdf3;border-color:#abefc6;color:#027a48}
			.cmx-vermietung-summary{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:16px 18px;border:1px solid #e4e7ec;border-radius:14px;background:#fafafa;margin-bottom:18px}
			.cmx-vermietung-summary-copy{display:flex;flex-wrap:wrap;gap:14px 18px;align-items:center;color:#667085}
			.cmx-vermietung-summary-pill{display:inline-flex;align-items:center;gap:6px}
			.cmx-vermietung-summary-label{font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#98a2b3}
			.cmx-vermietung-summary-value{font-size:15px;font-weight:700;color:#1d2327}
			.cmx-vermietung-summary-actions{display:flex;align-items:center;justify-content:flex-end;gap:12px;min-width:0}
			.cmx-vermietung-summary-picker{position:relative;flex:0 1 360px;min-width:280px;z-index:30}
			.cmx-vermietung-summary-search{width:100%;min-height:42px;padding:10px 12px;border:1px solid #c8c8c8;border-radius:10px;background:#fff;font:inherit}
			.cmx-vermietung-summary-results{z-index:9200}
			.cmx-vermietung-submit{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 18px;border:0;border-radius:10px;background:#a42c24;color:#fff;font:inherit;font-weight:700;text-decoration:none;cursor:pointer}
			.cmx-vermietung-submit:hover{background:#8f211b}
			.cmx-vermietung-submit:disabled{background:#d0d5dd;color:#fff;cursor:not-allowed}
			.cmx-vermietung-grid{position:relative;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px;overflow:visible;z-index:2;isolation:isolate}
			.cmx-vermietung-panel{position:relative;min-width:0;border:1px solid #e4e7ec;border-radius:14px;background:#fff;overflow:visible;z-index:1}
			.cmx-vermietung-panel.is-locked{background:#fbfcfe}
			.cmx-vermietung-panel.is-open{z-index:9000}
			.cmx-vermietung-panel-head{padding:16px 18px 0}
			.cmx-vermietung-panel-title{margin:0;font-size:20px;line-height:1.2}
			.cmx-vermietung-panel-sub{margin:6px 0 0;font-size:13px;color:#667085}
			.cmx-vermietung-panel-tools{padding:16px 18px 18px}
			.cmx-vermietung-picker{position:relative;z-index:1}
			.cmx-vermietung-search{width:100%;padding:10px 12px;border:1px solid #c8c8c8;border-radius:10px;font:inherit}
			.cmx-vermietung-search:disabled{background:#f8fafc;color:#98a2b3;cursor:not-allowed}
			.cmx-vermietung-panel.is-open .cmx-vermietung-picker{z-index:2}
			.cmx-vermietung-results{display:none;position:absolute;left:0;right:0;top:calc(100% + 8px);max-height:420px;overflow:auto;padding:8px;border:1px solid #d0d5dd;border-radius:14px;background:#fff;box-shadow:0 18px 36px rgba(0,0,0,.12);z-index:9100}
			.cmx-vermietung-item{display:flex;align-items:center;gap:12px;width:100%;padding:10px 8px;border:0;border-radius:12px;background:transparent;text-align:left;cursor:pointer}
			.cmx-vermietung-item:hover{background:#eef6ff}
			.cmx-vermietung-item.is-active{background:#dfefff}
			.cmx-vermietung-thumb{display:flex;align-items:center;justify-content:center;flex:0 0 52px;width:52px;height:52px;border-radius:12px;border:1px solid #e0e0e0;background:#fff;overflow:hidden}
			.cmx-vermietung-thumb img{display:block;width:100%;height:100%;object-fit:contain}
			.cmx-vermietung-thumb-placeholder{display:block;width:100%;height:100%;background:#f4f4f4}
			.cmx-vermietung-item-copy{min-width:0;flex:1 1 auto}
			.cmx-vermietung-item-title{display:block;font-size:16px;font-weight:700;color:#135e96;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
			.cmx-vermietung-item-meta{display:block;margin-top:4px;font-size:13px;color:#667085;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
			.cmx-vermietung-selected{display:none;margin-top:12px;padding:12px 14px;border:1px solid #e4e7ec;border-radius:12px;background:#fafafa}
			.cmx-vermietung-selected.is-active{display:block}
			.cmx-vermietung-selected-label{display:block;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#98a2b3}
			.cmx-vermietung-selected-title{display:block;margin-top:4px;font-size:15px;font-weight:700;color:#1d2327}
			.cmx-vermietung-lock{margin-top:12px;color:#667085;font-size:13px}
			.cmx-vermietung-empty{padding:8px 10px;color:#667085}
			.cmx-vermietung-info-panel{margin-top:18px;border:1px solid #e4e7ec;border-radius:14px;background:#fff;overflow:hidden}
			.cmx-vermietung-info-panel.is-hidden{display:none}
			.cmx-vermietung-info-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;padding:16px 18px 18px}
			.cmx-vermietung-transfer-panel{margin-top:18px;border:1px solid #e4e7ec;border-radius:14px;background:#fff;overflow:hidden}
			.cmx-vermietung-transfer-panel.is-hidden{display:none}
			.cmx-vermietung-transfer-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;padding:16px 18px 18px}
			.cmx-vermietung-info-item{min-width:0;padding:12px 14px;border:1px solid #e4e7ec;border-radius:12px;background:#fafafa}
			.cmx-vermietung-info-label{display:block;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#98a2b3}
			.cmx-vermietung-info-label.is-actionable{cursor:pointer;text-decoration:underline dotted}
			.cmx-vermietung-info-value{display:block;width:100%;margin-top:6px;padding:10px 12px;border:1px solid #c8c8c8;border-radius:10px;background:#fff;font:inherit;font-size:15px;font-weight:700;color:#1d2327}
			.cmx-vermietung-info-value:disabled{background:#f8fafc;color:#98a2b3;cursor:not-allowed}
			.cmx-vermietung-upload-panel{margin-top:18px;border:1px solid #e4e7ec;border-radius:14px;background:#fff;overflow:hidden}
			.cmx-vermietung-upload-body{padding:0 18px 18px}
			.cmx-vermietung-upload-dropzone{display:flex;align-items:center;gap:16px;min-height:168px;padding:16px;border:1px dashed #c8d1dc;border-radius:14px;background:#f8fafc;cursor:pointer;transition:border-color .15s ease,background-color .15s ease}
			.cmx-vermietung-upload-dropzone:hover{border-color:#135e96;background:#eef6ff}
			.cmx-vermietung-upload-input{display:none}
			.cmx-vermietung-upload-preview{display:none;flex:0 0 180px;max-width:180px;width:100%;height:132px;border:1px solid #d0d5dd;border-radius:12px;object-fit:cover;background:#fff}
			.cmx-vermietung-upload-preview.is-active{display:block}
			.cmx-vermietung-upload-copy{display:flex;flex-direction:column;gap:6px;min-width:0}
			.cmx-vermietung-upload-title{font-size:18px;font-weight:700;color:#1d2327}
			.cmx-vermietung-upload-hint{font-size:14px;color:#667085}
			.cmx-vermietung-upload-meta{margin:10px 0 0;font-size:13px;color:#667085}
			@media (max-width:960px){
				.cmx-vermietung-grid{grid-template-columns:minmax(0,1fr)}
				.cmx-vermietung-info-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
				.cmx-vermietung-transfer-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
			}
			@media (max-width:720px){
				.cmx-vermietung-page{padding:18px 12px 24px}
				.cmx-vermietung-head-inner{flex-direction:column}
				.cmx-vermietung-head-brand{display:none}
				.cmx-vermietung-head,.cmx-vermietung-body{padding-left:16px;padding-right:16px}
				.cmx-vermietung-title{font-size:24px}
				.cmx-vermietung-summary{flex-direction:column;align-items:stretch}
				.cmx-vermietung-summary-actions{flex-direction:column;align-items:stretch}
				.cmx-vermietung-summary-picker{flex-basis:auto;min-width:0}
				.cmx-vermietung-submit{width:100%}
				.cmx-vermietung-results{max-height:320px}
				.cmx-vermietung-info-grid{grid-template-columns:minmax(0,1fr)}
				.cmx-vermietung-transfer-grid{grid-template-columns:minmax(0,1fr)}
				.cmx-vermietung-upload-dropzone{flex-direction:column;align-items:flex-start}
				.cmx-vermietung-upload-preview,.cmx-vermietung-upload-preview.is-active{max-width:100%;width:100%;height:auto}
			}
		</style>';
		echo '</head><body>';
		echo '<div class="cmx-vermietung-page"><div class="cmx-vermietung-card">';
		echo '<div class="cmx-vermietung-head"><div class="cmx-vermietung-head-inner">';
		echo '<div class="cmx-vermietung-head-copy">';
		echo '<p class="cmx-vermietung-kicker">CaRent</p>';
		echo '<h1 class="cmx-vermietung-title">Vermietung</h1>';
		echo '<p class="cmx-vermietung-sub">Kontakt und Fahrzeug auswählen, danach direkt einen neuen CaRent-Datensatz öffnen.</p>';
		echo '</div>';
		if ($self_logo_url !== '') {
			echo '<div class="cmx-vermietung-head-brand">';
			if ($self_contact_url !== '') {
				echo '<a href="' . \esc_url($self_contact_url) . '" target="_blank" rel="noopener noreferrer" title="' . \esc_attr($self_contact_title) . '">';
			}
			echo '<img class="cmx-vermietung-head-logo" src="' . \esc_url($self_logo_url) . '" alt="Logo">';
			if ($self_contact_url !== '') {
				echo '</a>';
			}
			echo '</div>';
		}
		echo '</div></div>';
		echo '<div class="cmx-vermietung-body">';
		if ($status_message !== '') {
			echo '<div class="cmx-vermietung-notice is-success">' . \esc_html($status_message) . '</div>';
		}
		if ($error_message !== '') {
			echo '<div class="cmx-vermietung-notice">' . \esc_html($error_message) . '</div>';
		}
		echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '" id="cmx-vermietung-form" enctype="multipart/form-data">';
		echo '<input type="hidden" name="action" value="cmx_create_vermietung">';
		echo '<input type="hidden" name="cmx_vermietung_post_id" id="cmx-vermietung-post-id" value="' . (int) $current_post_id . '">';
		echo '<input type="hidden" name="cmx_vermietung_kontakt_id" id="cmx-vermietung-kontakt-id" value="' . (int) $selected_contact_id . '">';
		echo '<input type="hidden" name="cmx_vermietung_artikel_id" id="cmx-vermietung-artikel-id" value="' . (int) $selected_vehicle_id . '">';
		\wp_nonce_field('cmx_vermietung_create', 'cmx_vermietung_nonce');
		echo '<div class="cmx-vermietung-summary">';
		echo '<div class="cmx-vermietung-summary-copy">';
		echo '<span class="cmx-vermietung-summary-pill"><span class="cmx-vermietung-summary-label">Kontakt</span><span class="cmx-vermietung-summary-value" id="cmx-vermietung-kontakt-value">' . \esc_html($selected_contact_title !== '' ? $selected_contact_title : 'Noch keiner gewählt') . '</span></span>';
		echo '<span class="cmx-vermietung-summary-pill"><span class="cmx-vermietung-summary-label">Fahrzeug</span><span class="cmx-vermietung-summary-value" id="cmx-vermietung-artikel-value">' . \esc_html($selected_vehicle_title !== '' ? $selected_vehicle_title : 'Noch keines gewählt') . '</span></span>';
		echo '</div>';
		echo '<div class="cmx-vermietung-summary-actions">';
		echo '<div class="cmx-vermietung-summary-picker" id="cmx-vermietung-vertrag-picker">';
		echo '<input type="search" class="cmx-vermietung-summary-search" id="cmx-vermietung-vertrag-search" placeholder="Bestehenden Vertrag suchen" value="' . \esc_attr($selected_contract_title) . '">';
		echo '<div class="cmx-vermietung-results cmx-vermietung-summary-results" id="cmx-vermietung-vertrag-results">';
		if ($contracts !== []) {
			foreach ($contracts as $row) {
				$contract_id = (int) ($row['id'] ?? 0);
				$contract_title = (string) ($row['title'] ?? '');
				$contract_meta = (string) ($row['meta'] ?? '');
				$contract_search = (string) ($row['search'] ?? '');
				$contract_url = (string) ($row['url'] ?? '');

				echo '<button type="button" class="cmx-vermietung-item" data-id="' . $contract_id . '" data-title="' . \esc_attr($contract_title) . '" data-search="' . \esc_attr($contract_search) . '" data-url="' . \esc_url($contract_url) . '">';
				echo '<span class="cmx-vermietung-thumb"><span class="cmx-vermietung-thumb-placeholder" aria-hidden="true"></span></span>';
				echo '<span class="cmx-vermietung-item-copy">';
				echo '<span class="cmx-vermietung-item-title">' . \esc_html($contract_title) . '</span>';
				if ($contract_meta !== '') {
					echo '<span class="cmx-vermietung-item-meta">' . \esc_html($contract_meta) . '</span>';
				}
				echo '</span>';
				echo '</button>';
			}
		}
		echo '<div class="cmx-vermietung-empty" id="cmx-vermietung-vertrag-empty"' . ($contracts === [] ? '' : ' style="display:none"') . '>' . \esc_html($contracts === [] ? 'Aktuell keine Verträge gefunden.' : 'Keine Treffer.') . '</div>';
		echo '</div>';
		echo '</div>';
		echo '<button type="submit" class="cmx-vermietung-submit" id="cmx-vermietung-submit"' . (($selected_contact_id > 0 && $selected_vehicle_id > 0) ? '' : ' disabled') . '>' . \esc_html($submit_label) . '</button>';
		echo '</div>';
		echo '</div>';
		echo '<div class="cmx-vermietung-grid">';

		$panels = [
			[
				'key'   => 'kontakt',
				'title' => 'Kontakt',
				'sub'   => \count($contacts) . ' auswählbar',
				'rows'  => $contacts,
			],
			[
				'key'   => 'artikel',
				'title' => 'Fahrzeug',
				'sub'   => \count($vehicles) . ' auswählbar',
				'rows'  => $vehicles,
			],
		];

		foreach ($panels as $panel) {
			$key = (string) $panel['key'];
			$title = (string) $panel['title'];
			$sub = (string) $panel['sub'];
			$rows = (array) $panel['rows'];
			$selected_row = $key === 'kontakt' ? $selected_contact_row : $selected_vehicle_row;
			$selected_title = (string) ($selected_row['title'] ?? '');
			$is_locked = ($key === 'artikel' && $selected_contact_id <= 0);

			echo '<section class="cmx-vermietung-panel' . ($is_locked ? ' is-locked' : '') . '" data-picker="' . \esc_attr($key) . '">';
			echo '<div class="cmx-vermietung-panel-head">';
			echo '<h2 class="cmx-vermietung-panel-title">' . \esc_html($title) . '</h2>';
			echo '<p class="cmx-vermietung-panel-sub">' . \esc_html($sub) . '</p>';
			echo '</div>';
			echo '<div class="cmx-vermietung-panel-tools">';
			echo '<div class="cmx-vermietung-picker">';
			echo '<input type="search" class="cmx-vermietung-search" id="cmx-vermietung-search-' . \esc_attr($key) . '" placeholder="' . \esc_attr($title . ' durchsuchen') . '" value="' . \esc_attr($selected_title) . '"' . ($is_locked ? ' disabled' : '') . '>';
			echo '<div class="cmx-vermietung-results" id="cmx-vermietung-list-' . \esc_attr($key) . '">';
			if ($rows !== []) {
				foreach ($rows as $row) {
					$id = (int) ($row['id'] ?? 0);
					$row_title = (string) ($row['title'] ?? '');
					$meta = (string) ($row['meta'] ?? '');
					$image_url = (string) ($row['image_url'] ?? '');
					$search = (string) ($row['search'] ?? '');
					$details_source = ($key === 'artikel' && $id === $selected_vehicle_id) ? $selected_vehicle_details : $row;

					echo '<button type="button" class="cmx-vermietung-item" data-id="' . $id . '" data-title="' . \esc_attr($row_title) . '" data-search="' . \esc_attr($search) . '" data-kennzeichen="' . \esc_attr((string) ($details_source['kennzeichen'] ?? '')) . '" data-km-stand-uebernahme="' . \esc_attr((string) ($details_source['km_stand_uebernahme'] ?? '')) . '" data-begrenzung="' . \esc_attr((string) ($details_source['begrenzung'] ?? '')) . '" data-mehrpreis="' . \esc_attr((string) ($details_source['mehrpreis'] ?? '')) . '" data-kasko-min="' . \esc_attr((string) ($details_source['kasko_min'] ?? '')) . '" data-kasko-max="' . \esc_attr((string) ($details_source['kasko_max'] ?? '')) . '" data-mietpreis="' . \esc_attr((string) ($details_source['mietpreis'] ?? '')) . '">';
					echo '<span class="cmx-vermietung-thumb">';
					if ($image_url !== '') {
						echo '<img src="' . \esc_url($image_url) . '" alt="">';
					} else {
						echo '<span class="cmx-vermietung-thumb-placeholder" aria-hidden="true"></span>';
					}
					echo '</span>';
					echo '<span class="cmx-vermietung-item-copy">';
					echo '<span class="cmx-vermietung-item-title">' . \esc_html($row_title) . '</span>';
					if ($meta !== '') {
						echo '<span class="cmx-vermietung-item-meta">' . \esc_html($meta) . '</span>';
					}
					echo '</span>';
					echo '</button>';
				}
			}
			echo '<div class="cmx-vermietung-empty" id="cmx-vermietung-empty-' . \esc_attr($key) . '"' . ($rows === [] ? '' : ' style="display:none"') . '>' . \esc_html($rows === [] ? 'Aktuell keine ' . \strtolower($title) . ' gefunden.' : 'Keine Treffer.') . '</div>';
			echo '</div>';
			echo '</div>';
			echo '<div class="cmx-vermietung-selected' . ($selected_title !== '' ? ' is-active' : '') . '" id="cmx-vermietung-selected-' . \esc_attr($key) . '">';
			echo '<span class="cmx-vermietung-selected-label">Gewählt</span>';
			echo '<span class="cmx-vermietung-selected-title" id="cmx-vermietung-selected-title-' . \esc_attr($key) . '">' . \esc_html($selected_title) . '</span>';
			echo '</div>';
			if ($key === 'artikel') {
				echo '<div class="cmx-vermietung-lock" id="cmx-vermietung-lock-' . \esc_attr($key) . '"' . ($is_locked ? '' : ' style="display:none"') . '>Bitte zuerst einen Kontakt auswählen.</div>';
			}
			echo '</div>';
			echo '</section>';
		}

		echo '</div>';
		echo '<section class="cmx-vermietung-info-panel' . ($selected_vehicle_id > 0 ? '' : ' is-hidden') . '" id="cmx-vermietung-info-panel">';
		echo '<div class="cmx-vermietung-panel-head">';
		echo '<h2 class="cmx-vermietung-panel-title">Fahrzeugdaten</h2>';
		echo '<p class="cmx-vermietung-panel-sub">Kennzeichen und Konditionen des gewählten Fahrzeugs.</p>';
		echo '</div>';
		echo '<div class="cmx-vermietung-info-grid">';
		$info_fields = [
			'kennzeichen' => ['label' => 'Kennzeichen', 'name' => 'cmx_vermietung_fahrzeug_kennzeichen', 'type' => 'text', 'step' => ''],
			'begrenzung'  => ['label' => 'Begrenzung', 'name' => 'cmx_vermietung_fahrzeug_begrenzung', 'type' => 'number', 'step' => '1'],
			'mehrpreis'   => ['label' => 'Mehrpreis', 'name' => 'cmx_vermietung_fahrzeug_mehrpreis', 'type' => 'number', 'step' => '0.01'],
			'kasko_min'   => ['label' => 'Kasko min', 'name' => 'cmx_vermietung_fahrzeug_kasko_min', 'type' => 'number', 'step' => '0.01'],
			'kasko_max'   => ['label' => 'Kasko max', 'name' => 'cmx_vermietung_fahrzeug_kasko_max', 'type' => 'number', 'step' => '0.01'],
			'mietpreis'   => ['label' => 'Mietpreis', 'name' => 'cmx_vermietung_fahrzeug_mietpreis', 'type' => 'number', 'step' => '0.01'],
		];
		foreach ($info_fields as $field_key => $field_config) {
			$field_value = \trim((string) ($selected_vehicle_details[$field_key] ?? ''));
			echo '<div class="cmx-vermietung-info-item">';
			echo '<label class="cmx-vermietung-info-label" for="cmx-vermietung-info-' . \esc_attr($field_key) . '">' . \esc_html((string) $field_config['label']) . '</label>';
			echo '<input class="cmx-vermietung-info-value" id="cmx-vermietung-info-' . \esc_attr($field_key) . '" name="' . \esc_attr((string) $field_config['name']) . '" type="' . \esc_attr((string) $field_config['type']) . '" value="' . \esc_attr($field_value) . '"' . (((string) $field_config['step']) !== '' ? ' step="' . \esc_attr((string) $field_config['step']) . '"' : '') . ($selected_vehicle_id > 0 ? '' : ' disabled') . '>';
			echo '</div>';
		}
		echo '</div>';
		echo '</section>';
		echo '<section class="cmx-vermietung-transfer-panel' . ($selected_vehicle_id > 0 ? '' : ' is-hidden') . '" id="cmx-vermietung-uebernahme-panel">';
		echo '<div class="cmx-vermietung-panel-head">';
		echo '<h2 class="cmx-vermietung-panel-title">Übernahme</h2>';
		echo '<p class="cmx-vermietung-panel-sub">Daten für die Übergabe des gewählten Fahrzeugs.</p>';
		echo '</div>';
		echo '<div class="cmx-vermietung-transfer-grid">';
		$uebernahme_fields = [
			'ort' => ['label' => 'Ort', 'name' => 'cmx_vermietung_uebernahme_ort', 'type' => 'text', 'step' => ''],
			'datum' => ['label' => 'Datum', 'name' => 'cmx_vermietung_uebernahme_datum', 'type' => 'date', 'step' => ''],
			'uhrzeit' => ['label' => 'Uhrzeit', 'name' => 'cmx_vermietung_uebernahme_uhrzeit', 'type' => 'time', 'step' => ''],
			'km_stand' => ['label' => 'KM-Stand', 'name' => 'cmx_vermietung_fahrzeug_km_stand_uebernahme', 'type' => 'number', 'step' => '1'],
		];
		foreach ($uebernahme_fields as $field_key => $field_config) {
			$field_value = \trim((string) ($selected_uebernahme_values[$field_key] ?? ''));
			$label_id = 'cmx-vermietung-uebernahme-label-' . $field_key;
			$is_actionable_label = \in_array($field_key, ['ort', 'datum', 'uhrzeit'], true);
			echo '<div class="cmx-vermietung-info-item">';
			echo '<label class="cmx-vermietung-info-label' . ($is_actionable_label ? ' is-actionable' : '') . '" id="' . \esc_attr($label_id) . '" for="cmx-vermietung-uebernahme-' . \esc_attr($field_key) . '"' . ($is_actionable_label ? ' title="' . \esc_attr((string) $field_config['label'] . ' automatisch einsetzen') . '"' : '') . '>' . \esc_html((string) $field_config['label']) . '</label>';
			echo '<input class="cmx-vermietung-info-value" id="cmx-vermietung-uebernahme-' . \esc_attr($field_key) . '" name="' . \esc_attr((string) $field_config['name']) . '" type="' . \esc_attr((string) $field_config['type']) . '" value="' . \esc_attr($field_value) . '"' . (((string) $field_config['step']) !== '' ? ' step="' . \esc_attr((string) $field_config['step']) . '"' : '') . ($selected_vehicle_id > 0 ? '' : ' disabled') . '>';
			echo '</div>';
		}
		echo '</div>';
		echo '</section>';
		echo '<section class="cmx-vermietung-upload-panel">';
		echo '<div class="cmx-vermietung-panel-head">';
		echo '<h2 class="cmx-vermietung-panel-title">Führerausweis</h2>';
		// echo '<p class="cmx-vermietung-panel-sub">Foto hochladen</p>';
		echo '</div>';
		echo '<div class="cmx-vermietung-upload-body">';
		echo '<label class="cmx-vermietung-upload-dropzone" for="cmx-vermietung-fuehrerausweis-file" id="cmx-vermietung-fuehrerausweis-dropzone">';
		echo '<input type="file" class="cmx-vermietung-upload-input" name="cmx_vermietung_fuehrerausweis_file" id="cmx-vermietung-fuehrerausweis-file" accept="image/*" data-required="' . ($license_required ? '1' : '0') . '">';
		echo '<img class="cmx-vermietung-upload-preview' . ($license_image_url !== '' ? ' is-active' : '') . '" id="cmx-vermietung-fuehrerausweis-preview" alt=""' . ($license_image_url !== '' ? ' src="' . \esc_url($license_image_url) . '"' : '') . '>';
		echo '<span class="cmx-vermietung-upload-copy">';
		echo '<span class="cmx-vermietung-upload-title">' . \esc_html($license_image_url !== '' ? 'Führerausweis ersetzen' : 'Führerausweis wählen') . '</span>';
		echo '<span class="cmx-vermietung-upload-hint">JPG, PNG, WebP oder HEIC als Foto hochladen' . ($license_required ? ' · Pflichtfeld.' : '.') . '</span>';
		echo '</span>';
		echo '</label>';
		echo '<p class="cmx-vermietung-upload-meta" id="cmx-vermietung-fuehrerausweis-meta">' . \esc_html($license_filename !== '' ? $license_filename : '') . '</p>'; // Noch kein Foto gewählt.
		echo '</div>';
		echo '</section>';
		echo '<section class="cmx-vermietung-upload-panel">';
		echo '<div class="cmx-vermietung-panel-head">';
		echo '<h2 class="cmx-vermietung-panel-title">Identitätskarte</h2>';
		// echo '<p class="cmx-vermietung-panel-sub">Foto hochladen</p>';
		echo '</div>';
		echo '<div class="cmx-vermietung-upload-body">';
		echo '<label class="cmx-vermietung-upload-dropzone" for="cmx-vermietung-identitaetskarte-file" id="cmx-vermietung-identitaetskarte-dropzone">';
		echo '<input type="file" class="cmx-vermietung-upload-input" name="cmx_vermietung_identitaetskarte_file" id="cmx-vermietung-identitaetskarte-file" accept="image/*">';
		echo '<img class="cmx-vermietung-upload-preview' . ($identity_image_url !== '' ? ' is-active' : '') . '" id="cmx-vermietung-identitaetskarte-preview" alt=""' . ($identity_image_url !== '' ? ' src="' . \esc_url($identity_image_url) . '"' : '') . '>';
		echo '<span class="cmx-vermietung-upload-copy">';
		echo '<span class="cmx-vermietung-upload-title">' . \esc_html($identity_image_url !== '' ? 'Identitätskarte ersetzen' : 'Identitätskarte wählen') . '</span>';
		echo '<span class="cmx-vermietung-upload-hint">JPG, PNG, WebP oder HEIC als Foto hochladen.</span>';
		echo '</span>';
		echo '</label>';
		echo '<p class="cmx-vermietung-upload-meta" id="cmx-vermietung-identitaetskarte-meta">' . \esc_html($identity_filename !== '' ? $identity_filename : '') . '</p>'; // Noch kein Foto gewählt.
		echo '</div>';
		echo '</section>';
		echo '</form></div></div></div>';
		echo '<script>
			(function(){
				var form=document.getElementById("cmx-vermietung-form");
				if(!form){return;}
				var kontaktInput=document.getElementById("cmx-vermietung-kontakt-id");
				var artikelInput=document.getElementById("cmx-vermietung-artikel-id");
				var kontaktValue=document.getElementById("cmx-vermietung-kontakt-value");
				var artikelValue=document.getElementById("cmx-vermietung-artikel-value");
				var submit=document.getElementById("cmx-vermietung-submit");
				var artikelPanel=document.querySelector(\'[data-picker="artikel"]\');
				var artikelSearch=document.getElementById("cmx-vermietung-search-artikel");
				var artikelLock=document.getElementById("cmx-vermietung-lock-artikel");
				var licenseFile=document.getElementById("cmx-vermietung-fuehrerausweis-file");
				var licensePreview=document.getElementById("cmx-vermietung-fuehrerausweis-preview");
				var licenseMeta=document.getElementById("cmx-vermietung-fuehrerausweis-meta");
				var licenseRequired=licenseFile && String(licenseFile.getAttribute("data-required")||"0")==="1";
				var identityFile=document.getElementById("cmx-vermietung-identitaetskarte-file");
				var identityPreview=document.getElementById("cmx-vermietung-identitaetskarte-preview");
				var identityMeta=document.getElementById("cmx-vermietung-identitaetskarte-meta");
				var contractPicker=document.getElementById("cmx-vermietung-vertrag-picker");
				var contractSearch=document.getElementById("cmx-vermietung-vertrag-search");
				var contractResults=document.getElementById("cmx-vermietung-vertrag-results");
				var contractEmpty=document.getElementById("cmx-vermietung-vertrag-empty");
				var contractActive=null;
				var vehicleInfoPanel=document.getElementById("cmx-vermietung-info-panel");
				var transferPanel=document.getElementById("cmx-vermietung-uebernahme-panel");
				var vehicleInfoNodes={
					kennzeichen:document.getElementById("cmx-vermietung-info-kennzeichen"),
					begrenzung:document.getElementById("cmx-vermietung-info-begrenzung"),
					mehrpreis:document.getElementById("cmx-vermietung-info-mehrpreis"),
					kasko_min:document.getElementById("cmx-vermietung-info-kasko_min"),
					kasko_max:document.getElementById("cmx-vermietung-info-kasko_max"),
					mietpreis:document.getElementById("cmx-vermietung-info-mietpreis")
				};
				var transferNodes={
					ort:document.getElementById("cmx-vermietung-uebernahme-ort"),
					datum:document.getElementById("cmx-vermietung-uebernahme-datum"),
					uhrzeit:document.getElementById("cmx-vermietung-uebernahme-uhrzeit"),
					km_stand:document.getElementById("cmx-vermietung-uebernahme-km_stand")
				};
				var transferLabels={
					ort:document.getElementById("cmx-vermietung-uebernahme-label-ort"),
					datum:document.getElementById("cmx-vermietung-uebernahme-label-datum"),
					uhrzeit:document.getElementById("cmx-vermietung-uebernahme-label-uhrzeit")
				};
				var pickers={};
				function normalize(value){return String(value||"").toLowerCase().trim();}
				function getTodayValue(){
					var now=new Date();
					var local=new Date(now.getTime()-(now.getTimezoneOffset()*60000));
					return local.toISOString().slice(0,10);
				}
				function getCurrentTimeValue(){
					var now=new Date();
					var hours=String(now.getHours()).padStart(2,"0");
					var minutes=String(now.getMinutes()).padStart(2,"0");
					return hours+":"+minutes;
				}
				function triggerInputEvents(input){
					if(!input){return;}
					try{
						input.dispatchEvent(new Event("input",{bubbles:true}));
						input.dispatchEvent(new Event("change",{bubbles:true}));
					}catch(err){}
				}
				function getCurrentLocation(callback){
					if(typeof callback!=="function" || !navigator.geolocation){return;}
					function formatAddress(payload){
						var address=payload && payload.address ? payload.address : {};
						var street=[address.road || address.pedestrian || address.footway || address.cycleway || "", address.house_number || ""].join(" ").trim();
						var locality=address.city || address.town || address.village || address.hamlet || address.municipality || "";
						var postal=address.postcode || "";
						var region=address.state || address.county || "";
						var parts=[];
						if(street){parts.push(street);}
						if(postal || locality){parts.push([postal,locality].join(" ").trim());}
						if(region && parts.indexOf(region)===-1){parts.push(region);}
						return parts.join(", ").trim();
					}
					navigator.geolocation.getCurrentPosition(function(pos){
						var result={
							lat:pos.coords.latitude,
							lon:pos.coords.longitude,
							address:""
						};
						if(typeof fetch!=="function"){
							callback(result);
							return;
						}
						var url="https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&zoom=18&lat="
							+ encodeURIComponent(String(result.lat))
							+ "&lon="
							+ encodeURIComponent(String(result.lon));
						fetch(url,{headers:{"Accept":"application/json"}}).then(function(response){
							return response.ok ? response.json() : null;
						}).then(function(payload){
							result.address=formatAddress(payload) || String((payload && payload.display_name) || "").trim();
							callback(result);
						}).catch(function(){
							callback(result);
						});
					}, function(){
						callback(null);
					}, {
						enableHighAccuracy:true,
						timeout:10000,
						maximumAge:0
					});
				}
				function updateSubmit(){
					var enabled=Number(kontaktInput.value||0)>0 && Number(artikelInput.value||0)>0;
					submit.disabled=!enabled;
				}
				function updateUploadPreview(file, previewNode, metaNode){
					if(!previewNode || !metaNode){return;}
					if(!file){
						previewNode.classList.remove("is-active");
						previewNode.removeAttribute("src");
						metaNode.textContent="Noch kein Foto gewählt.";
						return;
					}
					metaNode.textContent=String(file.name||"");
					if(String(file.type||"").indexOf("image/")!==0){
						previewNode.classList.remove("is-active");
						previewNode.removeAttribute("src");
						return;
					}
					previewNode.src=URL.createObjectURL(file);
					previewNode.classList.add("is-active");
				}
				function updateVehicleInfo(item){
					var hasVehicle=!!item;
					var values=item ? {
						kennzeichen:String(item.getAttribute("data-kennzeichen")||"").trim(),
						km_stand_uebernahme:String(item.getAttribute("data-km-stand-uebernahme")||"").trim(),
						begrenzung:String(item.getAttribute("data-begrenzung")||"").trim(),
						mehrpreis:String(item.getAttribute("data-mehrpreis")||"").trim(),
						kasko_min:String(item.getAttribute("data-kasko-min")||"").trim(),
						kasko_max:String(item.getAttribute("data-kasko-max")||"").trim(),
						mietpreis:String(item.getAttribute("data-mietpreis")||"").trim()
					} : {
						kennzeichen:"",
						km_stand_uebernahme:"",
						begrenzung:"",
						mehrpreis:"",
						kasko_min:"",
						kasko_max:"",
						mietpreis:""
					};
					if(vehicleInfoPanel){
						vehicleInfoPanel.classList.toggle("is-hidden", !hasVehicle);
					}
					if(transferPanel){
						transferPanel.classList.toggle("is-hidden", !hasVehicle);
					}
					Object.keys(vehicleInfoNodes).forEach(function(key){
						if(!vehicleInfoNodes[key]){return;}
						vehicleInfoNodes[key].value=values[key];
						vehicleInfoNodes[key].disabled=!hasVehicle;
					});
					Object.keys(transferNodes).forEach(function(key){
						if(!transferNodes[key]){return;}
						transferNodes[key].disabled=!hasVehicle;
					});
					if(transferNodes.km_stand){
						transferNodes.km_stand.value=hasVehicle ? values.km_stand_uebernahme : "";
					}
				}
				function setArtikelLocked(locked){
					if(!artikelPanel || !artikelSearch){return;}
					artikelPanel.classList.toggle("is-locked", !!locked);
					artikelSearch.disabled=!!locked;
					if(artikelLock){artikelLock.style.display=locked ? "" : "none";}
					if(locked && pickers.artikel){pickers.artikel.clear(true);}
				}
				function closeAll(exceptKey){
					Object.keys(pickers).forEach(function(key){
						if(key===exceptKey){return;}
						pickers[key].close();
					});
				}
				function contractItems(){
					return contractResults ? Array.prototype.slice.call(contractResults.querySelectorAll(".cmx-vermietung-item")) : [];
				}
				function contractVisibleItems(){
					return contractItems().filter(function(item){return item.style.display!== "none";});
				}
				function markContractActive(item){
					contractItems().forEach(function(row){row.classList.toggle("is-active", row===item);});
					contractActive=item||null;
				}
				function closeContractPicker(){
					if(!contractResults || !contractPicker){return;}
					contractResults.style.display="none";
					contractPicker.classList.remove("is-open");
					markContractActive(null);
				}
				function filterContractPicker(){
					var term=normalize(contractSearch ? contractSearch.value : "");
					var visible=0;
					var firstVisible=null;
					contractItems().forEach(function(item){
						var match=term==="" || normalize(item.getAttribute("data-search")).indexOf(term)!==-1;
						item.style.display=match ? "" : "none";
						if(match){
							visible++;
							if(!firstVisible){firstVisible=item;}
						}
					});
					if(contractEmpty){contractEmpty.style.display=visible===0 ? "" : "none";}
					if(contractActive && contractActive.style.display==="none"){
						markContractActive(firstVisible);
					}
					if(!contractActive && firstVisible){
						markContractActive(firstVisible);
					}
				}
				function openContractPicker(){
					if(!contractResults || !contractPicker || !contractSearch){return;}
					filterContractPicker();
					closeAll();
					contractResults.style.display="block";
					contractPicker.classList.add("is-open");
				}
				function moveContractPicker(delta){
					var rows=contractVisibleItems();
					var index;
					if(!rows.length){return;}
					index=rows.indexOf(contractActive);
					if(index===-1){
						markContractActive(rows[delta < 0 ? rows.length - 1 : 0]);
						return;
					}
					index+=delta;
					if(index<0){index=0;}
					if(index>=rows.length){index=rows.length-1;}
					markContractActive(rows[index]);
					try{rows[index].scrollIntoView({block:"nearest"});}catch(e){}
				}
				function chooseContract(item){
					if(!item){return;}
					var url=String(item.getAttribute("data-url")||"");
					if(url===""){return;}
					window.location.href=url;
				}
				function bindPicker(key){
					var panel=document.querySelector(\'[data-picker="\'+key+\'"]\');
					var input=document.getElementById("cmx-vermietung-search-"+key);
					var list=document.getElementById("cmx-vermietung-list-"+key);
					var empty=document.getElementById("cmx-vermietung-empty-"+key);
					var hidden=(key==="kontakt") ? kontaktInput : artikelInput;
					var valueNode=(key==="kontakt") ? kontaktValue : artikelValue;
					var selectedNode=document.getElementById("cmx-vermietung-selected-"+key);
					var selectedTitle=document.getElementById("cmx-vermietung-selected-title-"+key);
					var active=null;
					if(!input||!list||!panel){return;}
					function items(){
						return Array.prototype.slice.call(list.querySelectorAll(".cmx-vermietung-item"));
					}
					function visibleItems(){
						return items().filter(function(item){return item.style.display!=="none";});
					}
					function markActive(item){
						items().forEach(function(row){row.classList.toggle("is-active", row===item);});
						active=item||null;
					}
					function close(){
						list.style.display="none";
						panel.classList.remove("is-open");
						markActive(null);
					}
					function open(){
						if(input.disabled){return;}
						filter();
						closeAll(key);
						list.style.display="block";
						panel.classList.add("is-open");
					}
					function clear(keepInput){
						hidden.value="";
						valueNode.textContent=(key==="kontakt") ? "Noch keiner gewählt" : "Noch keines gewählt";
						if(selectedNode){selectedNode.classList.remove("is-active");}
						if(selectedTitle){selectedTitle.textContent="";}
						if(keepInput!==true){
							input.value="";
						}
						markActive(null);
						if(key==="artikel"){
							updateVehicleInfo(null);
						}
						updateSubmit();
					}
					function filter(){
						var term=normalize(input.value);
						var visible=0;
						var firstVisible=null;
						items().forEach(function(item){
							var match=term==="" || normalize(item.getAttribute("data-search")).indexOf(term)!==-1;
							item.style.display=match ? "" : "none";
							if(match){
								visible++;
								if(!firstVisible){firstVisible=item;}
							}
						});
						if(empty){empty.style.display=visible===0 ? "" : "none";}
						if(active && active.style.display==="none"){
							markActive(firstVisible);
						}
						if(!active && firstVisible){
							markActive(firstVisible);
						}
					}
					function choose(item){
						var previous=hidden.value;
						var currentId=item ? String(item.getAttribute("data-id")||"") : "";
						var title=item ? String(item.getAttribute("data-title")||"") : "";
						hidden.value=currentId;
						valueNode.textContent=title!=="" ? title : ((key==="kontakt") ? "Noch keiner gewählt" : "Noch keines gewählt");
						if(selectedNode){
							selectedNode.classList.toggle("is-active", title!=="");
						}
						if(selectedTitle){
							selectedTitle.textContent=title;
						}
						input.value=title;
						updateSubmit();
						close();
						if(key==="kontakt"){
							setArtikelLocked(currentId==="");
							if(currentId!=="" && artikelSearch){
								window.setTimeout(function(){artikelSearch.focus();}, 30);
							}
						} else if(key==="artikel"){
							updateVehicleInfo(item);
						}
					}
					function move(delta){
						var rows=visibleItems();
						var index;
						if(!rows.length){return;}
						index=rows.indexOf(active);
						if(index===-1){
							markActive(rows[delta < 0 ? rows.length - 1 : 0]);
							return;
						}
						index+=delta;
						if(index<0){index=0;}
						if(index>=rows.length){index=rows.length-1;}
						markActive(rows[index]);
						try{rows[index].scrollIntoView({block:"nearest"});}catch(e){}
					}
					input.addEventListener("input", function(){
						open();
					});
					input.addEventListener("focus", open);
					input.addEventListener("click", open);
					input.addEventListener("keydown", function(event){
						if(event.key==="ArrowDown"){event.preventDefault();move(1);return;}
						if(event.key==="ArrowUp"){event.preventDefault();move(-1);return;}
						if(event.key==="Enter"){
							if(active){event.preventDefault();choose(active);}
							return;
						}
						if(event.key==="Escape"){
							event.preventDefault();
							close();
							return;
						}
					});
					items().forEach(function(item){
						item.addEventListener("click", function(){choose(item);});
						item.addEventListener("dblclick", function(){choose(item);});
					});
					pickers[key]={
						close: close,
						clear: clear
					};
				}
				bindPicker("kontakt");
				bindPicker("artikel");
				setArtikelLocked(String(kontaktInput.value||"")==="");
				if(contractSearch && contractResults){
					contractSearch.addEventListener("input", openContractPicker);
					contractSearch.addEventListener("focus", openContractPicker);
					contractSearch.addEventListener("click", openContractPicker);
					contractSearch.addEventListener("keydown", function(event){
						if(event.key==="ArrowDown"){event.preventDefault();moveContractPicker(1);return;}
						if(event.key==="ArrowUp"){event.preventDefault();moveContractPicker(-1);return;}
						if(event.key==="Enter"){
							if(contractActive){event.preventDefault();chooseContract(contractActive);}
							return;
						}
						if(event.key==="Escape"){
							event.preventDefault();
							closeContractPicker();
							return;
						}
					});
					contractItems().forEach(function(item){
						item.addEventListener("click", function(){chooseContract(item);});
						item.addEventListener("dblclick", function(){chooseContract(item);});
					});
				}
				if(licenseFile){
					licenseFile.addEventListener("change", function(){
						updateUploadPreview(licenseFile.files && licenseFile.files[0] ? licenseFile.files[0] : null, licensePreview, licenseMeta);
					});
				}
				if(identityFile){
					identityFile.addEventListener("change", function(){
						updateUploadPreview(identityFile.files && identityFile.files[0] ? identityFile.files[0] : null, identityPreview, identityMeta);
					});
				}
				if(transferNodes.ort && transferLabels.ort){
					transferLabels.ort.addEventListener("click", function(event){
						if(transferNodes.ort.disabled){return;}
						event.preventDefault();
						getCurrentLocation(function(location){
							if(!location){return;}
							var value=String(location.address || "").trim();
							if(!value && location.lat && location.lon){
								value=String(location.lat)+", "+String(location.lon);
							}
							if(!value){return;}
							transferNodes.ort.value=value;
							triggerInputEvents(transferNodes.ort);
							transferNodes.ort.focus();
						});
					});
				}
				if(transferNodes.datum && transferLabels.datum){
					transferLabels.datum.addEventListener("click", function(event){
						if(transferNodes.datum.disabled){return;}
						event.preventDefault();
						transferNodes.datum.value=getTodayValue();
						triggerInputEvents(transferNodes.datum);
						transferNodes.datum.focus();
					});
				}
				if(transferNodes.uhrzeit && transferLabels.uhrzeit){
					transferLabels.uhrzeit.addEventListener("click", function(event){
						if(transferNodes.uhrzeit.disabled){return;}
						event.preventDefault();
						transferNodes.uhrzeit.value=getCurrentTimeValue();
						triggerInputEvents(transferNodes.uhrzeit);
						transferNodes.uhrzeit.focus();
					});
				}
				document.addEventListener("click", function(event){
					Object.keys(pickers).forEach(function(key){
						var panel=document.querySelector(\'[data-picker="\'+key+\'"]\');
						if(!panel || panel.contains(event.target)){return;}
						pickers[key].close();
					});
					if(contractPicker && !contractPicker.contains(event.target)){
						closeContractPicker();
					}
				});
				form.addEventListener("submit", function(event){
					var hasLicenseFile=licenseFile && licenseFile.files && licenseFile.files[0];
					if(licenseRequired && !hasLicenseFile){
						event.preventDefault();
						window.alert("Bitte zuerst einen Führerausweis hochladen.");
					}
				});
				updateSubmit();
			})();
		</script>';
		echo '</body></html>';
		exit;
	}
}

\add_action('admin_post_cmx_create_vermietung', __NAMESPACE__ . '\\cmx_handle_vermietung_create');

\add_action('template_redirect', function (): void {
	if (!cmx_is_vermietung_request()) {
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

\add_action('template_redirect', __NAMESPACE__ . '\\cmx_render_vermietung_page', 5);

\add_action('admin_menu', function (): void {
	if (!cmx_vermietung_feature_enabled()) {
		return;
	}

	$submenu_cap = 'edit_posts';
	$obj = \get_post_type_object('carent');
	if ($obj) {
		$submenu_cap = (string) ($obj->cap->create_posts ?? $obj->cap->edit_posts ?? 'edit_posts');
		if ($submenu_cap === '') {
			$submenu_cap = 'edit_posts';
		}
	}

	\add_submenu_page(
		'edit.php?post_type=carent',
		'Vermietung',
		'Vermietung',
		$submenu_cap,
		'cmx-carent-vermietung',
		static function (): void {
			\wp_safe_redirect(cmx_vermietung_url());
			exit;
		}
	);
}, 30);

\add_action('admin_head', function (): void {
	if (!\is_admin() || !cmx_vermietung_feature_enabled()) {
		return;
	}

	$target_url = cmx_vermietung_url();
	if ($target_url === '') {
		return;
	}

	echo '<script>
		document.addEventListener("DOMContentLoaded", function () {
			var link = document.querySelector(\'#menu-posts-carent a[href*="page=cmx-carent-vermietung"]\');
			if (!link) return;
			link.href = ' . \wp_json_encode($target_url) . ';
			link.target = "_blank";
			link.rel = "noopener noreferrer";
		});
	</script>';
});

\add_filter('cmx65_front_pages_links', function (array $pages_links): array {
	if (!cmx_vermietung_feature_enabled()) {
		return $pages_links;
	}

	$target_url = cmx_vermietung_url();
	if ($target_url === '') {
		return $pages_links;
	}

	$pages_links[] = [
		'label'  => 'Vermietung',
		'href'   => $target_url,
		'target' => '_blank',
	];

	return $pages_links;
});

\add_action('cmx65_adminbar_pages_menu', function (\WP_Admin_Bar $wp_admin_bar): void {
	if (!cmx_vermietung_feature_enabled()) {
		return;
	}

	$target_url = cmx_vermietung_url();
	if ($target_url === '') {
		return;
	}

	$wp_admin_bar->add_menu([
		'id'     => 'cmx65_vermietung_id',
		'parent' => 'cmx65_pages_id',
		'title'  => 'Vermietung',
		'href'   => $target_url,
		'meta'   => [
			'title'  => __('Deine Vermietung', 'textdomain'),
			'target' => '_blank',
		],
	]);
});
