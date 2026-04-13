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
				'km_stand_rueckgabe'   => \trim((string) ($details['km_stand_rueckgabe'] ?? '')),
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
				'km_stand_rueckgabe'  => '',
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
			'km_stand_rueckgabe'  => \trim((string) ($defaults['km_stand_rueckgabe'] ?? '')),
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
				'km_stand_rueckgabe' => \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_STAND_RUECKGABE_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_STAND_RUECKGABE_META')
					: '_cmx_carent_fahrzeug_km_stand_rueckgabe',
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_signature_meta_key')) {
	function cmx_vermietung_signature_meta_key(string $transfer_key, string $role): string {
		$transfer_key = \sanitize_key($transfer_key);
		$role = \sanitize_key($role);

		if ($transfer_key === 'rueckgabe') {
			return $role === 'mieter'
				? (\defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_MIETER_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_MIETER_META')
					: '_cmx_carent_rueckgabe_mieter_attachment_id')
				: (\defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_VERMIETER_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_VERMIETER_META')
					: '_cmx_carent_rueckgabe_vermieter_attachment_id');
		}

		return $role === 'mieter'
			? (\defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_MIETER_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_MIETER_META')
				: '_cmx_carent_uebernahme_mieter_attachment_id')
			: (\defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_VERMIETER_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_VERMIETER_META')
				: '_cmx_carent_uebernahme_vermieter_attachment_id');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_signature_attachment_url')) {
	function cmx_vermietung_signature_attachment_url(int $attachment_id): string {
		if ($attachment_id <= 0) {
			return '';
		}

		$url = (string) \wp_get_attachment_image_url($attachment_id, 'large');
		if ($url === '') {
			$url = (string) \wp_get_attachment_url($attachment_id);
		}

		return $url;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_store_signature_attachment')) {
	function cmx_vermietung_store_signature_attachment(int $post_id, string $transfer_key, string $role, string $data_url, int $existing_attachment_id = 0) {
		$data_url = \trim($data_url);
		if ($post_id <= 0 || $data_url === '') {
			return 0;
		}

		if (!\preg_match('#^data:image/png;base64,(.+)$#', $data_url, $matches)) {
			return new \WP_Error('invalid_signature_data', 'Ungültige Signaturdaten.');
		}

		$raw = \str_replace(' ', '+', (string) ($matches[1] ?? ''));
		$binary = \base64_decode($raw, true);
		if (!\is_string($binary) || $binary === '') {
			return new \WP_Error('invalid_signature_data', 'Signatur konnte nicht dekodiert werden.');
		}

		require_once \ABSPATH . 'wp-admin/includes/file.php';
		require_once \ABSPATH . 'wp-admin/includes/media.php';
		require_once \ABSPATH . 'wp-admin/includes/image.php';

		$filename = 'carent-' . $post_id . '-' . \sanitize_file_name($transfer_key . '-' . $role . '-signature') . '-' . \gmdate('YmdHis') . '.png';
		$upload = \wp_upload_bits($filename, null, $binary);
		if (!empty($upload['error'])) {
			return new \WP_Error('signature_upload_failed', (string) $upload['error']);
		}

		$file = (string) ($upload['file'] ?? '');
		$url = (string) ($upload['url'] ?? '');
		if ($file === '' || $url === '') {
			return new \WP_Error('signature_upload_failed', 'Datei konnte nicht geschrieben werden.');
		}

		$attachment = [
			'post_mime_type' => 'image/png',
			'post_title'     => 'Unterschrift ' . \ucfirst($transfer_key) . ' ' . \ucfirst($role) . ' #' . $post_id,
			'post_status'    => 'inherit',
			'post_parent'    => $post_id,
			'guid'           => $url,
		];
		$attachment_id = \wp_insert_attachment($attachment, $file, $post_id, true);
		if (\is_wp_error($attachment_id) || (int) $attachment_id <= 0) {
			return new \WP_Error('signature_upload_failed', 'Anhang konnte nicht angelegt werden.');
		}

		$attachment_id = (int) $attachment_id;
		$metadata = \wp_generate_attachment_metadata($attachment_id, $file);
		if (!\is_wp_error($metadata) && \is_array($metadata)) {
			\wp_update_attachment_metadata($attachment_id, $metadata);
		}

		if ($existing_attachment_id > 0 && $existing_attachment_id !== $attachment_id) {
			\wp_delete_attachment($existing_attachment_id, true);
		}

		return $attachment_id;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_save_transfer_signature_values')) {
	function cmx_vermietung_save_transfer_signature_values(int $post_id, string $transfer_key): bool {
		$transfer_key = \sanitize_key($transfer_key);
		if ($post_id <= 0 || !\in_array($transfer_key, ['uebernahme', 'rueckgabe'], true)) {
			return true;
		}

		foreach (['vermieter', 'mieter'] as $role) {
			$meta_key = cmx_vermietung_signature_meta_key($transfer_key, $role);
			$existing_attachment_id = (int) \get_post_meta($post_id, $meta_key, true);
			$data_field = 'cmx_vermietung_' . $transfer_key . '_' . $role . '_signature';
			$clear_field = 'cmx_vermietung_' . $transfer_key . '_' . $role . '_signature_clear';
			$data_url = isset($_POST[$data_field]) ? \trim((string) \wp_unslash($_POST[$data_field])) : '';
			$clear_requested = isset($_POST[$clear_field]) && (string) \wp_unslash($_POST[$clear_field]) === '1';

			if ($data_url !== '') {
				$attachment_id = cmx_vermietung_store_signature_attachment($post_id, $transfer_key, $role, $data_url, $existing_attachment_id);
				if (\is_wp_error($attachment_id) || (int) $attachment_id <= 0) {
					return false;
				}
				\update_post_meta($post_id, $meta_key, (int) $attachment_id);
				continue;
			}

			if ($clear_requested) {
				if ($existing_attachment_id > 0) {
					\wp_delete_attachment($existing_attachment_id, true);
				}
				\delete_post_meta($post_id, $meta_key);
			}
		}

		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_render_signature_pad')) {
	function cmx_vermietung_render_signature_pad(string $transfer_key, string $role, int $attachment_id, bool $enabled): void {
		$transfer_key = \sanitize_key($transfer_key);
		$role = \sanitize_key($role);
		$prefix = 'cmx-vermietung-signature-' . $transfer_key . '-' . $role;
		$input_name = 'cmx_vermietung_' . $transfer_key . '_' . $role . '_signature';
		$clear_name = 'cmx_vermietung_' . $transfer_key . '_' . $role . '_signature_clear';
		$label = $role === 'mieter' ? 'Mieter' : 'Vermieter';
		$image_url = cmx_vermietung_signature_attachment_url($attachment_id);
		$filename = $attachment_id > 0 ? (string) \basename((string) \get_attached_file($attachment_id)) : '';

		echo '<div class="cmx-vermietung-signature-item' . ($enabled ? '' : ' is-disabled') . '" data-signature-item="' . \esc_attr($prefix) . '">';
		echo '<div class="cmx-vermietung-signature-head">';
		echo '<h3 class="cmx-vermietung-signature-title">' . \esc_html($label) . '</h3>';
		echo '<button type="button" class="cmx-vermietung-signature-clear" data-signature-clear="' . \esc_attr($prefix) . '"' . ($enabled ? '' : ' disabled') . '>Leeren</button>';
		echo '</div>';
		echo '<div class="cmx-vermietung-signature-pad">';
		echo '<canvas class="cmx-vermietung-signature-canvas" id="' . \esc_attr($prefix . '-canvas') . '" width="640" height="220" data-signature-canvas="' . \esc_attr($prefix) . '" data-existing-src="' . \esc_url($image_url) . '"' . ($enabled ? '' : ' data-disabled="1"') . '></canvas>';
		echo '</div>';
		echo '<input type="hidden" name="' . \esc_attr($input_name) . '" id="' . \esc_attr($prefix . '-input') . '" value="">';
		echo '<input type="hidden" name="' . \esc_attr($clear_name) . '" id="' . \esc_attr($prefix . '-clear') . '" value="0">';
		echo '<p class="cmx-vermietung-signature-meta" id="' . \esc_attr($prefix . '-meta') . '">' . \esc_html($filename !== '' ? $filename : 'Mit Finger unterschreiben.') . '</p>';
		echo '</div>';
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_save_rueckgabe_values')) {
	function cmx_vermietung_save_rueckgabe_values(int $post_id): void {
		$ort_meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_ORT_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_ORT_META')
			: '_cmx_carent_rueckgabe_ort';
		$datum_meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_DATUM_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_DATUM_META')
			: '_cmx_carent_rueckgabe_datum';
		$uhrzeit_meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_UHRZEIT_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_UHRZEIT_META')
			: '_cmx_carent_rueckgabe_uhrzeit';
		$km_meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_STAND_RUECKGABE_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_STAND_RUECKGABE_META')
			: '_cmx_carent_fahrzeug_km_stand_rueckgabe';

		$ort = isset($_POST['cmx_vermietung_rueckgabe_ort'])
			? \trim((string) \wp_unslash($_POST['cmx_vermietung_rueckgabe_ort']))
			: '';
		$datum = isset($_POST['cmx_vermietung_rueckgabe_datum'])
			? \trim((string) \wp_unslash($_POST['cmx_vermietung_rueckgabe_datum']))
			: '';
		$uhrzeit = isset($_POST['cmx_vermietung_rueckgabe_uhrzeit'])
			? \trim((string) \wp_unslash($_POST['cmx_vermietung_rueckgabe_uhrzeit']))
			: '';
		$km_stand = isset($_POST['cmx_vermietung_fahrzeug_km_stand_rueckgabe'])
			? cmx_carent_fahrzeug_normalize_int(\wp_unslash($_POST['cmx_vermietung_fahrzeug_km_stand_rueckgabe']))
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

			$post_title = \trim((string) \get_the_title($post_id));
			$title = \function_exists(__NAMESPACE__ . '\\cmx_carent_display_title')
				? \trim((string) cmx_carent_display_title($post_id))
				: $post_title;
			if ($title === '') {
				$title = $post_title !== '' ? $post_title : 'Vertrag #' . $post_id;
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
			if ($post_title !== '' && $post_title !== $title) {
				$meta_parts[] = $post_title;
			}

			$search_text = \implode(' ', \array_filter([$title, $post_title, $contact_title, $vehicle_title, (string) $post_id], static fn(string $value): bool => \trim($value) !== ''));

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
			'invalid_inventory_video' => 'Bitte nur Videodateien für das Übernahmevideo hochladen.',
			'upload_failed'   => 'Der Führerausweis konnte nicht hochgeladen werden.',
			'identity_upload_failed' => 'Die Identitätskarte konnte nicht hochgeladen werden.',
			'inventory_upload_failed' => 'Das Übernahmevideo konnte nicht hochgeladen werden.',
			'signature_upload_failed' => 'Die Unterschrift konnte nicht gespeichert werden.',
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_fotos_section_config')) {
	function cmx_vermietung_fotos_section_config(string $section): array {
		if (\function_exists(__NAMESPACE__ . '\\cmx_carent_fotos_section_config')) {
			return (array) cmx_carent_fotos_section_config($section);
		}

		return $section === 'rueckgabe'
			? [
				'meta_key' => '_cmx_carent_rueckgabe_fotos_rows',
				'legacy_meta_key' => '',
				'field_name' => 'cmx_carent_rueckgabe_fotos_rows',
			]
			: [
				'meta_key' => '_cmx_carent_uebernahme_fotos_rows',
				'legacy_meta_key' => '_cmx_carent_fotos_rows',
				'field_name' => 'cmx_carent_uebernahme_fotos_rows',
			];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_fotos_taxonomy')) {
	function cmx_vermietung_fotos_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_carent_fotos_taxonomy')) {
			return (string) cmx_carent_fotos_taxonomy();
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_fotos_term_options')) {
	function cmx_vermietung_fotos_term_options(): array {
		if (\function_exists(__NAMESPACE__ . '\\cmx_carent_fotos_term_options')) {
			return (array) cmx_carent_fotos_term_options();
		}

		return [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_fotos_attachment_payload')) {
	function cmx_vermietung_fotos_attachment_payload(int $attachment_id): array {
		if (\function_exists(__NAMESPACE__ . '\\cmx_carent_fotos_attachment_payload')) {
			return (array) cmx_carent_fotos_attachment_payload($attachment_id);
		}

		return [
			'id' => 0,
			'preview_url' => '',
			'file_url' => '',
			'label' => '',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_fotos_rows')) {
	function cmx_vermietung_fotos_rows(int $post_id, string $section): array {
		$config = cmx_vermietung_fotos_section_config($section);
		if ($post_id <= 0) {
			return [];
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_carent_fotos_rows')) {
			return (array) cmx_carent_fotos_rows(
				$post_id,
				(string) ($config['meta_key'] ?? ''),
				(string) ($config['legacy_meta_key'] ?? '')
			);
		}

		$rows = \get_post_meta($post_id, (string) ($config['meta_key'] ?? ''), true);
		return \is_array($rows) ? $rows : [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_fotos_remove_icon')) {
	function cmx_vermietung_fotos_remove_icon(): string {
		return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M9 3h6l1 2h4v2H4V5h4l1-2Zm1 7h2v7h-2v-7Zm4 0h2v7h-2v-7ZM7 10h2v7H7v-7Z"/></svg>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_fotos_empty_markup')) {
	function cmx_vermietung_fotos_empty_markup(): string {
		return '<div class="cmx-vermietung-fotos-empty">' . \esc_html__('Foto hier ablegen oder anklicken.', 'cmx-misbuero') . '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_fotos_row_markup')) {
	function cmx_vermietung_fotos_row_markup(string $index, array $row, array $term_options, string $field_name, bool $enabled): string {
		$term_id = isset($row['term_id']) ? (int) $row['term_id'] : 0;
		$attachment_id = isset($row['attachment_id']) ? (int) $row['attachment_id'] : 0;
		$attachment = cmx_vermietung_fotos_attachment_payload($attachment_id);
		$remove_icon = cmx_vermietung_fotos_remove_icon();
		$disabled_attr = $enabled ? '' : ' disabled';

		\ob_start();
		?>
		<div class="cmx-vermietung-fotos-row" data-index="<?php echo \esc_attr($index); ?>">
			<div class="cmx-vermietung-fotos-row-head">
				<div class="cmx-vermietung-fotos-row-title"><?php echo \esc_html__('Foto', 'cmx-misbuero'); ?></div>
				<button type="button" class="cmx-vermietung-fotos-row-remove"<?php echo $disabled_attr; ?>><?php echo \esc_html__('Entfernen', 'cmx-misbuero'); ?></button>
			</div>
			<div class="cmx-vermietung-fotos-row-grid">
				<div class="cmx-vermietung-fotos-field">
					<select class="cmx-vermietung-fotos-select" name="<?php echo \esc_attr($field_name); ?>[<?php echo \esc_attr($index); ?>][term_id]"<?php echo $disabled_attr; ?>>
						<option value="0"><?php echo \esc_html__('Typ wählen', 'cmx-misbuero'); ?></option>
						<?php foreach ($term_options as $option) : ?>
							<option value="<?php echo (int) ($option['id'] ?? 0); ?>"<?php selected($term_id, (int) ($option['id'] ?? 0)); ?>>
								<?php echo \esc_html((string) ($option['label'] ?? '')); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="cmx-vermietung-fotos-field">
					<input type="hidden" class="cmx-vermietung-fotos-attachment-id" name="<?php echo \esc_attr($field_name); ?>[<?php echo \esc_attr($index); ?>][attachment_id]" value="<?php echo \esc_attr((string) ($attachment['id'] ?? 0)); ?>">
					<input type="file" class="cmx-vermietung-fotos-file-input" accept="image/*" style="display:none;"<?php echo $disabled_attr; ?>>
					<div class="cmx-vermietung-fotos-media">
						<div class="cmx-vermietung-fotos-preview<?php echo $attachment['preview_url'] !== '' ? ' is-has-image' : ''; ?>" role="button" tabindex="<?php echo $enabled ? '0' : '-1'; ?>" aria-label="<?php echo \esc_attr__('Foto hochladen', 'cmx-misbuero'); ?>">
							<button type="button" class="cmx-vermietung-fotos-image-remove"<?php echo ($attachment['id'] ?? 0) > 0 ? '' : ' style="display:none;"'; ?><?php echo $disabled_attr; ?> aria-label="<?php echo \esc_attr__('Bild entfernen', 'cmx-misbuero'); ?>">
								<?php echo $remove_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</button>
							<?php if (($attachment['preview_url'] ?? '') !== '') : ?>
								<img src="<?php echo \esc_url((string) $attachment['preview_url']); ?>" alt="" class="cmx-vermietung-fotos-preview-image">
							<?php else : ?>
								<?php echo cmx_vermietung_fotos_empty_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endif; ?>
						</div>
						<p class="cmx-vermietung-fotos-file">
							<?php if (($attachment['file_url'] ?? '') !== '' && ($attachment['label'] ?? '') !== '') : ?>
								<a href="<?php echo \esc_url((string) $attachment['file_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html((string) $attachment['label']); ?></a>
							<?php endif; ?>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php

		return (string) \ob_get_clean();
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_render_fotos_section')) {
	function cmx_vermietung_render_fotos_section(string $section, int $post_id, bool $enabled): void {
		$config = cmx_vermietung_fotos_section_config($section);
		$field_name = (string) ($config['field_name'] ?? '');
		if ($field_name === '') {
			return;
		}

		$rows = cmx_vermietung_fotos_rows($post_id, $section);
		if ($rows === []) {
			$rows = [['term_id' => 0, 'attachment_id' => 0]];
		}

		$term_options = cmx_vermietung_fotos_term_options();
		$host_id = 'cmx-vermietung-fotos-' . $section;
		$template_markup = cmx_vermietung_fotos_row_markup('__INDEX__', ['term_id' => 0, 'attachment_id' => 0], $term_options, $field_name, $enabled);

		echo '<div class="cmx-vermietung-transfer-fotos-wrap" id="' . \esc_attr($host_id) . '" data-post-id="' . (int) $post_id . '" data-enabled="' . ($enabled ? '1' : '0') . '">';
		echo '<h3 class="cmx-vermietung-transfer-section-title">Fotos</h3>';
		echo '<p class="cmx-vermietung-transfer-section-sub">Beliebige Fotos hinzufügen. Pro Zeile ein Typ und dann das passende Bild wählen.</p>';
		echo '<div class="cmx-vermietung-fotos-rows" id="' . \esc_attr($host_id . '-rows') . '">';
		foreach ($rows as $index => $row) {
			echo cmx_vermietung_fotos_row_markup((string) $index, (array) $row, $term_options, $field_name, $enabled); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
		echo '<div class="cmx-vermietung-fotos-footer"><button type="button" class="cmx-vermietung-fotos-add"' . ($enabled ? '' : ' disabled') . ' id="' . \esc_attr($host_id . '-add') . '">' . \esc_html__('Weitere Fotos hinzufügen', 'cmx-misbuero') . '</button></div>';
		echo '<template id="' . \esc_attr($host_id . '-template') . '">' . $template_markup . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_transfer_video_meta_key')) {
	function cmx_vermietung_transfer_video_meta_key(string $section): string {
		if ($section === 'rueckgabe') {
			return \defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_BESTANDSAUFNAHME_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_BESTANDSAUFNAHME_META')
				: '_cmx_carent_rueckgabe_bestandsaufnahme_attachment_id';
		}

		return \defined(__NAMESPACE__ . '\\CMX_CARENT_BESTANDSAUFNAHME_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_BESTANDSAUFNAHME_META')
			: '_cmx_carent_bestandsaufnahme_attachment_id';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_render_transfer_video_field')) {
	function cmx_vermietung_render_transfer_video_field(string $section, int $attachment_id, bool $enabled): void {
		$video_url = $attachment_id > 0 ? (string) \wp_get_attachment_url($attachment_id) : '';
		$filename = $attachment_id > 0 ? (string) \basename((string) \get_attached_file($attachment_id)) : '';
		$field_name = 'cmx_carent_' . $section . '_bestandsaufnahme_attachment_id';
		$prefix = 'cmx-vermietung-video-' . $section;
		$title = $section === 'rueckgabe' ? 'Rückgabevideos' : 'Übernahmevideo';
		$replace_label = $section === 'rueckgabe' ? 'Rückgabevideos ersetzen' : 'Übernahmevideo ersetzen';
		$choose_label = $section === 'rueckgabe' ? 'Rückgabevideos wählen' : 'Übernahmevideo wählen';
		$remove_icon = cmx_vermietung_fotos_remove_icon();
		$disabled_attr = $enabled ? '' : ' disabled';

		echo '<div class="cmx-vermietung-transfer-video-wrap" id="' . \esc_attr($prefix) . '" data-enabled="' . ($enabled ? '1' : '0') . '">';
		echo '<h3 class="cmx-vermietung-transfer-section-title">' . \esc_html($title) . '</h3>';
		echo '<div class="cmx-vermietung-upload-body cmx-vermietung-upload-body--embedded">';
		echo '<input type="hidden" name="' . \esc_attr($field_name) . '" id="' . \esc_attr($prefix . '-attachment-id') . '" value="' . (int) $attachment_id . '">';
		echo '<label class="cmx-vermietung-upload-dropzone" for="' . \esc_attr($prefix . '-file') . '" id="' . \esc_attr($prefix . '-dropzone') . '">';
		echo '<input type="file" class="cmx-vermietung-upload-input" id="' . \esc_attr($prefix . '-file') . '" accept="video/*"' . $disabled_attr . '>';
		echo '<button type="button" class="cmx-vermietung-upload-remove"' . ($attachment_id > 0 ? '' : ' style="display:none;"') . $disabled_attr . ' id="' . \esc_attr($prefix . '-remove') . '" aria-label="' . \esc_attr__('Video entfernen', 'cmx-misbuero') . '">';
		echo $remove_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</button>';
		echo '<video class="cmx-vermietung-upload-video' . ($video_url !== '' ? ' is-active' : '') . '" id="' . \esc_attr($prefix . '-preview') . '" controls preload="metadata"' . ($video_url !== '' ? ' src="' . \esc_url($video_url) . '"' : '') . '></video>';
		echo '<span class="cmx-vermietung-upload-copy">';
		echo '<span class="cmx-vermietung-upload-title">' . \esc_html($video_url !== '' ? $replace_label : $choose_label) . '</span>';
		echo '<span class="cmx-vermietung-upload-hint">MP4, MOV, WebM oder anderes Video hochladen.</span>';
		echo '</span>';
		echo '</label>';
		echo '<p class="cmx-vermietung-upload-meta" id="' . \esc_attr($prefix . '-meta') . '">';
		if ($filename !== '' && $video_url !== '') {
			echo '<a href="' . \esc_url($video_url) . '" target="_blank" rel="noopener noreferrer">' . \esc_html($filename) . '</a>';
		}
		echo '</p>';
		echo '</div>';
		echo '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_sync_attachment_parent')) {
	function cmx_vermietung_sync_attachment_parent(int $attachment_id, int $post_id): void {
		if ($attachment_id <= 0 || $post_id <= 0 || \get_post_type($attachment_id) !== 'attachment') {
			return;
		}

		if ((int) \wp_get_post_parent_id($attachment_id) === $post_id) {
			return;
		}

		\wp_update_post([
			'ID' => $attachment_id,
			'post_parent' => $post_id,
		]);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_save_fotos_rows_values')) {
	function cmx_vermietung_save_fotos_rows_values(int $post_id): void {
		$taxonomy = cmx_vermietung_fotos_taxonomy();
		$term_ids = [];

		foreach (['uebernahme', 'rueckgabe'] as $section) {
			$config = cmx_vermietung_fotos_section_config($section);
			$field_name = (string) ($config['field_name'] ?? '');
			$meta_key = (string) ($config['meta_key'] ?? '');
			$legacy_meta_key = (string) ($config['legacy_meta_key'] ?? '');
			if ($field_name === '' || $meta_key === '') {
				continue;
			}

			$raw_rows = isset($_POST[$field_name]) ? \wp_unslash($_POST[$field_name]) : [];
			$rows = [];

			foreach ((array) $raw_rows as $raw_row) {
				if (!\is_array($raw_row)) {
					continue;
				}

				$current_term_id = isset($raw_row['term_id']) ? (int) $raw_row['term_id'] : 0;
				$current_attachment_id = isset($raw_row['attachment_id']) ? (int) $raw_row['attachment_id'] : 0;

				if ($current_term_id > 0 && $taxonomy !== '') {
					$term = \get_term($current_term_id, $taxonomy);
					if (!$term || \is_wp_error($term)) {
						$current_term_id = 0;
					}
				}

				if ($current_attachment_id > 0) {
					$mime = (string) \get_post_mime_type($current_attachment_id);
					if (!\str_starts_with($mime, 'image/')) {
						$current_attachment_id = 0;
					} else {
						cmx_vermietung_sync_attachment_parent($current_attachment_id, $post_id);
					}
				}

				if ($current_term_id <= 0 && $current_attachment_id <= 0) {
					continue;
				}

				$rows[] = [
					'term_id' => $current_term_id,
					'attachment_id' => $current_attachment_id,
				];

				if ($current_term_id > 0) {
					$term_ids[] = $current_term_id;
				}
			}

			if ($rows === []) {
				\delete_post_meta($post_id, $meta_key);
			} else {
				\update_post_meta($post_id, $meta_key, $rows);
			}

			if ($legacy_meta_key !== '') {
				\delete_post_meta($post_id, $legacy_meta_key);
			}
		}

		if ($taxonomy !== '') {
			$term_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $term_ids))));
			\wp_set_post_terms($post_id, $term_ids, $taxonomy, false);
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_vermietung_save_transfer_video_values')) {
	function cmx_vermietung_save_transfer_video_values(int $post_id): void {
		foreach (['uebernahme', 'rueckgabe'] as $section) {
			$field_name = 'cmx_carent_' . $section . '_bestandsaufnahme_attachment_id';
			$meta_key = cmx_vermietung_transfer_video_meta_key($section);
			$attachment_id = isset($_POST[$field_name]) ? (int) \wp_unslash($_POST[$field_name]) : 0;

			if ($attachment_id <= 0) {
				\delete_post_meta($post_id, $meta_key);
				continue;
			}

			$mime = (string) \get_post_mime_type($attachment_id);
			if (!\str_starts_with($mime, 'video/')) {
				continue;
			}

			cmx_vermietung_sync_attachment_parent($attachment_id, $post_id);
			\update_post_meta($post_id, $meta_key, $attachment_id);
		}
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
		cmx_vermietung_save_rueckgabe_values($post_id);
		cmx_vermietung_save_fotos_rows_values($post_id);
		cmx_vermietung_save_transfer_video_values($post_id);
		if (!cmx_vermietung_save_transfer_signature_values($post_id, 'uebernahme')) {
			\wp_safe_redirect(cmx_vermietung_manage_url($post_id, ['cmx_vermietung_error' => 'signature_upload_failed']));
			exit;
		}
		if (!cmx_vermietung_save_transfer_signature_values($post_id, 'rueckgabe')) {
			\wp_safe_redirect(cmx_vermietung_manage_url($post_id, ['cmx_vermietung_error' => 'signature_upload_failed']));
			exit;
		}
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

			if (\function_exists(__NAMESPACE__ . '\\cmx_carent_apply_auto_title')) {
			cmx_carent_apply_auto_title($post_id);
		} elseif (\function_exists(__NAMESPACE__ . '\\cmx_carent_composed_title')) {
			$title = \trim((string) cmx_carent_composed_title($post_id));
			if ($title !== '') {
				\wp_update_post([
					'ID'         => $post_id,
					'post_title' => $title,
					'post_name'  => \sanitize_title($title),
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
		$uebernahme_video_attachment_id = $current_post_id > 0 ? (int) \get_post_meta($current_post_id, cmx_vermietung_transfer_video_meta_key('uebernahme'), true) : 0;
		$rueckgabe_video_attachment_id = $current_post_id > 0 ? (int) \get_post_meta($current_post_id, cmx_vermietung_transfer_video_meta_key('rueckgabe'), true) : 0;
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
		$uebernahme_fotos_rows = cmx_vermietung_fotos_rows($current_post_id, 'uebernahme');
		$rueckgabe_fotos_rows = cmx_vermietung_fotos_rows($current_post_id, 'rueckgabe');
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
		$selected_uebernahme_signatures = [
			'vermieter' => $current_post_id > 0 ? (int) \get_post_meta($current_post_id, cmx_vermietung_signature_meta_key('uebernahme', 'vermieter'), true) : 0,
			'mieter'    => $current_post_id > 0 ? (int) \get_post_meta($current_post_id, cmx_vermietung_signature_meta_key('uebernahme', 'mieter'), true) : 0,
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
		$selected_rueckgabe_values = [
			'ort'      => '',
			'datum'    => '',
			'uhrzeit'  => '',
			'km_stand' => \trim((string) ($selected_vehicle_details['km_stand_rueckgabe'] ?? '')),
		];
		$selected_rueckgabe_signatures = [
			'vermieter' => $current_post_id > 0 ? (int) \get_post_meta($current_post_id, cmx_vermietung_signature_meta_key('rueckgabe', 'vermieter'), true) : 0,
			'mieter'    => $current_post_id > 0 ? (int) \get_post_meta($current_post_id, cmx_vermietung_signature_meta_key('rueckgabe', 'mieter'), true) : 0,
		];
		if ($current_post_id > 0) {
			$selected_rueckgabe_values['ort'] = \trim((string) \get_post_meta(
				$current_post_id,
				\defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_ORT_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_ORT_META')
					: '_cmx_carent_rueckgabe_ort',
				true
			));
			$selected_rueckgabe_values['datum'] = \trim((string) \get_post_meta(
				$current_post_id,
				\defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_DATUM_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_DATUM_META')
					: '_cmx_carent_rueckgabe_datum',
				true
			));
			$selected_rueckgabe_values['uhrzeit'] = \trim((string) \get_post_meta(
				$current_post_id,
				\defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_UHRZEIT_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_UHRZEIT_META')
					: '_cmx_carent_rueckgabe_uhrzeit',
				true
			));
			$stored_km_stand_rueckgabe = \trim((string) \get_post_meta(
				$current_post_id,
				\defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_STAND_RUECKGABE_META')
					? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KM_STAND_RUECKGABE_META')
					: '_cmx_carent_fahrzeug_km_stand_rueckgabe',
				true
			));
			if ($stored_km_stand_rueckgabe !== '') {
				$selected_rueckgabe_values['km_stand'] = $stored_km_stand_rueckgabe;
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
		$current_contract_edit_url = $current_post_id > 0 ? (string) \admin_url('post.php?post=' . $current_post_id . '&action=edit') : '';
		$license_required = $license_attachment_id <= 0;
		$ajax_url = (string) \admin_url('admin-ajax.php');
		$photo_upload_nonce = (string) \wp_create_nonce('cmx_carent_fotos_upload');
		$video_upload_nonce = (string) \wp_create_nonce('cmx_carent_transfer_video_upload');

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
			.cmx-vermietung-title-link{color:inherit;text-decoration:none}
			.cmx-vermietung-title-link:hover{text-decoration:none}
			.cmx-vermietung-sub{margin:8px 0 0;color:#6b7280;font-size:14px}
			.cmx-vermietung-body{position:relative;padding:22px 28px 28px;overflow:visible}
			.cmx-vermietung-notice{margin:0 0 18px;padding:12px 14px;border-radius:12px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;font-weight:600;transition:opacity .28s ease,transform .28s ease}
			.cmx-vermietung-notice.is-success{background:#ecfdf3;border-color:#abefc6;color:#027a48}
			.cmx-vermietung-notice.is-hidden{opacity:0;transform:translateY(-6px);pointer-events:none}
			.cmx-vermietung-summary{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:16px 18px;border:1px solid #e4e7ec;border-radius:14px;background:#fafafa;margin-bottom:18px}
			.cmx-vermietung-summary-copy{display:flex;flex-wrap:wrap;gap:14px 18px;align-items:center;color:#667085}
			.cmx-vermietung-summary-pill{display:inline-flex;align-items:center;gap:6px}
			.cmx-vermietung-summary-label{font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#98a2b3}
			.cmx-vermietung-summary-value{font-size:15px;font-weight:700;color:#1d2327}
			.cmx-vermietung-summary-actions{display:flex;align-items:center;justify-content:flex-end;gap:12px;min-width:0}
			.cmx-vermietung-summary-picker{position:relative;flex:0 1 468px;min-width:364px;z-index:30}
			.cmx-vermietung-summary-search{width:100%;min-height:42px;padding:10px 12px;border:1px solid #c8c8c8;border-radius:10px;background:#fff;font:inherit}
			.cmx-vermietung-summary-results{z-index:9200}
			.cmx-vermietung-submit{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 18px;border:0;border-radius:10px;background:#a42c24;color:#fff;font:inherit;font-weight:700;text-decoration:none;cursor:pointer}
			.cmx-vermietung-submit:hover{background:#8f211b}
			.cmx-vermietung-submit:disabled{background:#d0d5dd;color:#fff;cursor:not-allowed}
			.cmx-vermietung-icon-button{display:inline-flex;align-items:center;justify-content:center;flex:0 0 42px;width:42px;height:42px;border:1px solid #d0d5dd;border-radius:10px;background:#fff;color:#135e96;text-decoration:none}
			.cmx-vermietung-icon-button:hover{background:#eef6ff;border-color:#135e96}
			.cmx-vermietung-icon-button.is-disabled{background:#f8fafc;border-color:#d0d5dd;color:#98a2b3;pointer-events:none}
			.cmx-vermietung-icon-button svg{display:block;width:20px;height:20px;color:currentColor}
			.cmx-vermietung-grid{position:relative;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px;overflow:visible;z-index:2;isolation:isolate}
			.cmx-vermietung-panel{position:relative;min-width:0;border:1px solid #e4e7ec;border-radius:14px;background:#fff;overflow:visible;z-index:1}
			.cmx-vermietung-panel.is-locked{background:#fbfcfe}
			.cmx-vermietung-panel.is-open{z-index:9000}
			.cmx-vermietung-panel-head{padding:16px 18px 0}
			.cmx-vermietung-panel-head.is-collapsible{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
			.cmx-vermietung-panel-title{margin:0;font-size:20px;line-height:1.2}
			.cmx-vermietung-panel-toggle{display:inline-flex;align-items:center;gap:10px;margin:0;padding:0;border:0;background:transparent;color:#1d2327;font:inherit;text-align:left;cursor:pointer}
			.cmx-vermietung-panel-toggle:hover{color:#135e96}
			.cmx-vermietung-panel-toggle-icon{display:inline-flex;align-items:center;justify-content:center;flex:0 0 24px;width:24px;height:24px;border-radius:999px;border:1px solid #d0d5dd;background:#fff;color:#667085;font-size:12px;line-height:1;transition:transform .18s ease,color .18s ease,border-color .18s ease}
			.cmx-vermietung-panel-toggle:hover .cmx-vermietung-panel-toggle-icon{color:#135e96;border-color:#135e96}
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
			.cmx-vermietung-selected-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
			.cmx-vermietung-selected-copy{min-width:0;flex:1 1 auto}
			.cmx-vermietung-selected-label{display:block;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#98a2b3}
			.cmx-vermietung-selected-title{display:block;margin-top:4px;font-size:15px;font-weight:700;color:#1d2327;word-break:break-word}
			.cmx-vermietung-selected-meta{display:block;margin-top:4px;font-size:13px;color:#667085;word-break:break-word}
			.cmx-vermietung-selected-meta.is-empty{display:none}
			.cmx-vermietung-selected-clear{display:inline-flex;align-items:center;justify-content:center;flex:0 0 28px;width:28px;height:28px;margin:0;padding:0;border:1px solid #d0d5dd;border-radius:999px;background:#fff;color:#667085;font-size:18px;line-height:1;cursor:pointer}
			.cmx-vermietung-selected-clear:hover{border-color:#b42318;color:#b42318;background:#fef3f2}
			.cmx-vermietung-lock{margin-top:12px;color:#667085;font-size:13px}
			.cmx-vermietung-empty{padding:8px 10px;color:#667085}
			.cmx-vermietung-info-panel{margin-top:18px;border:1px solid #e4e7ec;border-radius:14px;background:#fff;overflow:hidden}
			.cmx-vermietung-info-panel.is-hidden{display:none}
			.cmx-vermietung-info-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;padding:16px 18px 18px}
			.cmx-vermietung-transfer-panel{margin-top:18px;border:1px solid #e4e7ec;border-radius:14px;background:#fff;overflow:hidden}
			.cmx-vermietung-transfer-panel.is-hidden{display:none}
			.cmx-vermietung-transfer-panel.is-collapsed .cmx-vermietung-collapsible-body,.cmx-vermietung-upload-panel.is-collapsed .cmx-vermietung-collapsible-body{display:none}
			.cmx-vermietung-transfer-panel.is-collapsed .cmx-vermietung-panel-toggle-icon,.cmx-vermietung-upload-panel.is-collapsed .cmx-vermietung-panel-toggle-icon{transform:rotate(-90deg)}
			.cmx-vermietung-transfer-panel.is-collapsed .cmx-vermietung-panel-head{padding-bottom:14px}
			.cmx-vermietung-transfer-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;padding:16px 18px 18px}
			.cmx-vermietung-signature-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;padding:0 18px 18px}
			.cmx-vermietung-transfer-fotos-wrap,.cmx-vermietung-transfer-video-wrap{padding:0 18px 18px}
			.cmx-vermietung-transfer-section-title{margin:0;font-size:18px;line-height:1.25}
			.cmx-vermietung-transfer-section-sub{margin:6px 0 0;font-size:13px;color:#667085}
			.cmx-vermietung-fotos-rows{display:grid;gap:14px;margin-top:12px}
			.cmx-vermietung-fotos-row{border:1px solid #e4e7ec;border-radius:12px;background:#fafafa;padding:14px}
			.cmx-vermietung-fotos-row-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px}
			.cmx-vermietung-fotos-row-title{font-size:14px;font-weight:700;color:#1d2327}
			.cmx-vermietung-fotos-row-remove,.cmx-vermietung-fotos-add{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:0 14px;border:1px solid #d0d5dd;border-radius:10px;background:#fff;color:#b42318;font:inherit;cursor:pointer}
			.cmx-vermietung-fotos-row-remove:hover,.cmx-vermietung-fotos-add:hover{background:#fff5f5;border-color:#f04438}
			.cmx-vermietung-fotos-row-remove:disabled,.cmx-vermietung-fotos-add:disabled{opacity:.5;cursor:not-allowed}
			.cmx-vermietung-fotos-add{margin-top:12px;background:#a42c24;border-color:#a42c24;color:#fff}
			.cmx-vermietung-fotos-add:hover{background:#8f211b;border-color:#8f211b}
			.cmx-vermietung-fotos-row-grid{display:grid;grid-template-columns:minmax(220px,320px) minmax(0,1fr);gap:14px}
			.cmx-vermietung-fotos-select{display:block;width:100%;padding:10px 12px;border:1px solid #c8c8c8;border-radius:10px;background:#fff;font:inherit}
			.cmx-vermietung-fotos-select:disabled{background:#f8fafc;color:#98a2b3;cursor:not-allowed}
			.cmx-vermietung-fotos-media{display:grid;gap:10px}
			.cmx-vermietung-fotos-preview{position:relative;display:flex;align-items:center;justify-content:center;min-height:180px;padding:12px;border:1px dashed #c8d1dc;border-radius:12px;background:#f8fafc;cursor:pointer;transition:border-color .15s ease,background-color .15s ease}
			.cmx-vermietung-fotos-preview:hover{border-color:#135e96;background:#eef6ff}
			.cmx-vermietung-fotos-preview.is-busy{opacity:.65}
			.cmx-vermietung-fotos-preview.is-dragover{border-color:#135e96;background:#eef6ff}
			.cmx-vermietung-fotos-preview.is-disabled{cursor:not-allowed;background:#f8fafc}
			.cmx-vermietung-fotos-preview-image{display:block;max-width:100%;max-height:240px;height:auto;border-radius:8px}
			.cmx-vermietung-fotos-empty{font-size:14px;color:#667085;text-align:center}
			.cmx-vermietung-fotos-image-remove,.cmx-vermietung-upload-remove{position:absolute;top:10px;right:10px;display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border:0;border-radius:999px;background:rgba(255,255,255,.96);color:#b42318;box-shadow:0 2px 6px rgba(0,0,0,.14);cursor:pointer;z-index:2}
			.cmx-vermietung-fotos-image-remove:hover,.cmx-vermietung-upload-remove:hover{background:#fff5f5}
			.cmx-vermietung-fotos-image-remove:disabled,.cmx-vermietung-upload-remove:disabled{opacity:.55;cursor:not-allowed}
			.cmx-vermietung-fotos-image-remove svg,.cmx-vermietung-upload-remove svg{display:block;width:18px;height:18px}
			.cmx-vermietung-fotos-file{margin:0;font-size:13px;color:#667085;word-break:break-word}
			.cmx-vermietung-upload-body--embedded{padding:0;margin-top:12px}
			.cmx-vermietung-signature-item{min-width:0;padding:12px 14px;border:1px solid #e4e7ec;border-radius:12px;background:#fafafa}
			.cmx-vermietung-signature-item.is-disabled{opacity:.6}
			.cmx-vermietung-signature-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px}
			.cmx-vermietung-signature-title{margin:0;font-size:15px;line-height:1.2}
			.cmx-vermietung-signature-clear{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:0 12px;border:1px solid #d0d5dd;border-radius:10px;background:#fff;color:#475467;font:inherit;cursor:pointer}
			.cmx-vermietung-signature-clear:disabled{opacity:.5;cursor:not-allowed}
			.cmx-vermietung-signature-pad{border:1px dashed #c8d1dc;border-radius:12px;background:#fff;overflow:hidden}
			.cmx-vermietung-signature-canvas{display:block;width:100%;height:220px;touch-action:none;background:#fff;cursor:crosshair}
			.cmx-vermietung-signature-item.is-disabled .cmx-vermietung-signature-canvas{cursor:not-allowed;background:#f8fafc}
			.cmx-vermietung-signature-meta{margin:10px 0 0;font-size:13px;color:#667085}
			.cmx-vermietung-info-item{min-width:0;padding:12px 14px;border:1px solid #e4e7ec;border-radius:12px;background:#fafafa}
			.cmx-vermietung-info-label{display:block;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#98a2b3}
			.cmx-vermietung-info-label.is-actionable{cursor:pointer;text-decoration:underline dotted}
			.cmx-vermietung-info-value{display:block;width:100%;margin-top:6px;padding:10px 12px;border:1px solid #c8c8c8;border-radius:10px;background:#fff;font:inherit;font-size:15px;font-weight:700;color:#1d2327}
			.cmx-vermietung-info-value:disabled{background:#f8fafc;color:#98a2b3;cursor:not-allowed}
			.cmx-vermietung-upload-panel{margin-top:18px;border:1px solid #e4e7ec;border-radius:14px;background:#fff;overflow:hidden}
			.cmx-vermietung-upload-panel.is-hidden{display:none}
			.cmx-vermietung-upload-body{padding:0 18px 18px}
			.cmx-vermietung-upload-dropzone{display:flex;align-items:center;gap:16px;min-height:168px;padding:16px;border:1px dashed #c8d1dc;border-radius:14px;background:#f8fafc;cursor:pointer;transition:border-color .15s ease,background-color .15s ease}
			.cmx-vermietung-upload-dropzone:hover{border-color:#135e96;background:#eef6ff}
			.cmx-vermietung-upload-input{display:none}
			.cmx-vermietung-upload-preview,.cmx-vermietung-upload-video{display:none;flex:0 0 180px;max-width:180px;width:100%;height:132px;border:1px solid #d0d5dd;border-radius:12px;object-fit:cover;background:#fff}
			.cmx-vermietung-upload-preview.is-active,.cmx-vermietung-upload-video.is-active{display:block}
			.cmx-vermietung-upload-copy{display:flex;flex-direction:column;gap:6px;min-width:0}
			.cmx-vermietung-upload-title{font-size:18px;font-weight:700;color:#1d2327}
			.cmx-vermietung-upload-hint{font-size:14px;color:#667085}
			.cmx-vermietung-upload-meta{margin:10px 0 0;font-size:13px;color:#667085}
			@media (max-width:960px){
				.cmx-vermietung-grid{grid-template-columns:minmax(0,1fr)}
				.cmx-vermietung-info-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
				.cmx-vermietung-transfer-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
				.cmx-vermietung-signature-grid{grid-template-columns:minmax(0,1fr)}
				.cmx-vermietung-fotos-row-grid{grid-template-columns:minmax(0,1fr)}
			}
			@media (max-width:720px){
				.cmx-vermietung-page{padding:18px 12px 24px}
				.cmx-vermietung-head-inner{flex-direction:column}
				.cmx-vermietung-head-brand{display:none}
				.cmx-vermietung-head,.cmx-vermietung-body{padding-left:16px;padding-right:16px}
				.cmx-vermietung-title{font-size:24px}
				.cmx-vermietung-summary{flex-direction:column;align-items:stretch}
				.cmx-vermietung-summary-actions{display:grid;grid-template-columns:minmax(0,1fr) 42px;align-items:stretch}
				.cmx-vermietung-summary-picker{grid-column:1 / -1;flex-basis:auto;min-width:0}
				.cmx-vermietung-submit{grid-column:1;width:100%}
				.cmx-vermietung-icon-button{grid-column:2;justify-self:stretch;align-self:stretch}
				.cmx-vermietung-results{max-height:320px}
				.cmx-vermietung-info-grid{grid-template-columns:minmax(0,1fr)}
				.cmx-vermietung-transfer-grid{grid-template-columns:minmax(0,1fr)}
				.cmx-vermietung-signature-grid{grid-template-columns:minmax(0,1fr)}
				.cmx-vermietung-upload-dropzone{flex-direction:column;align-items:flex-start}
				.cmx-vermietung-upload-preview,.cmx-vermietung-upload-preview.is-active,.cmx-vermietung-upload-video,.cmx-vermietung-upload-video.is-active{max-width:100%;width:100%;height:auto}
			}
		</style>';
		echo '</head><body>';
		echo '<div class="cmx-vermietung-page"><div class="cmx-vermietung-card">';
		echo '<div class="cmx-vermietung-head"><div class="cmx-vermietung-head-inner">';
		echo '<div class="cmx-vermietung-head-copy">';
		echo '<p class="cmx-vermietung-kicker">CaRent</p>';
		echo '<h1 class="cmx-vermietung-title"><a class="cmx-vermietung-title-link" href="' . \esc_url(cmx_vermietung_url()) . '">Vermietung</a></h1>';
		echo '<p class="cmx-vermietung-sub">Fahr vorsichtig und chumm gsund zrugg!</p>';
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
			echo '<div class="cmx-vermietung-notice is-success" id="cmx-vermietung-status-notice">' . \esc_html($status_message) . '</div>';
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
		echo '<a class="cmx-vermietung-icon-button' . ($current_contract_edit_url === '' ? ' is-disabled' : '') . '" id="cmx-vermietung-contract-edit" href="' . \esc_url($current_contract_edit_url !== '' ? $current_contract_edit_url : '#') . '"' . ($current_contract_edit_url !== '' ? ' target="_blank" rel="noopener noreferrer"' : ' aria-disabled="true" tabindex="-1"') . ' title="Vertrag im WP-Admin öffnen" style="color:' . \esc_attr($current_contract_edit_url === '' ? '#98a2b3' : '#135e96') . '"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4 3h16a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm1 2v3h14V5H5Zm0 5v3h4v-3H5Zm6 0v3h3v-3h-3Zm5 0v3h3v-3h-3ZM5 15v4h4v-4H5Zm6 0v4h3v-4h-3Zm5 0v4h3v-4h-3Z"/></svg></a>';
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
			$selected_meta = $key === 'kontakt'
				? \trim((string) ($selected_row['meta'] ?? ''))
				: (\trim((string) ($selected_vehicle_details['kennzeichen'] ?? ($selected_row['kennzeichen'] ?? ''))) !== ''
					? 'Kennzeichen ' . \trim((string) ($selected_vehicle_details['kennzeichen'] ?? ($selected_row['kennzeichen'] ?? '')))
					: \trim((string) ($selected_row['meta'] ?? '')));
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
					$item_selected_meta = $key === 'kontakt'
						? $meta
						: (\trim((string) ($details_source['kennzeichen'] ?? '')) !== ''
							? 'Kennzeichen ' . \trim((string) ($details_source['kennzeichen'] ?? ''))
							: $meta);

					echo '<button type="button" class="cmx-vermietung-item" data-id="' . $id . '" data-title="' . \esc_attr($row_title) . '" data-search="' . \esc_attr($search) . '" data-selected-meta="' . \esc_attr($item_selected_meta) . '" data-kennzeichen="' . \esc_attr((string) ($details_source['kennzeichen'] ?? '')) . '" data-km-stand-uebernahme="' . \esc_attr((string) ($details_source['km_stand_uebernahme'] ?? '')) . '" data-km-stand-rueckgabe="' . \esc_attr((string) ($details_source['km_stand_rueckgabe'] ?? '')) . '" data-begrenzung="' . \esc_attr((string) ($details_source['begrenzung'] ?? '')) . '" data-mehrpreis="' . \esc_attr((string) ($details_source['mehrpreis'] ?? '')) . '" data-kasko-min="' . \esc_attr((string) ($details_source['kasko_min'] ?? '')) . '" data-kasko-max="' . \esc_attr((string) ($details_source['kasko_max'] ?? '')) . '" data-mietpreis="' . \esc_attr((string) ($details_source['mietpreis'] ?? '')) . '">';
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
			echo '<div class="cmx-vermietung-selected-head">';
			echo '<div class="cmx-vermietung-selected-copy">';
			echo '<span class="cmx-vermietung-selected-label">Gewählt</span>';
			echo '<span class="cmx-vermietung-selected-title" id="cmx-vermietung-selected-title-' . \esc_attr($key) . '">' . \esc_html($selected_title) . '</span>';
			echo '<span class="cmx-vermietung-selected-meta' . ($selected_meta !== '' ? '' : ' is-empty') . '" id="cmx-vermietung-selected-meta-' . \esc_attr($key) . '">' . \esc_html($selected_meta) . '</span>';
			echo '</div>';
			echo '<button type="button" class="cmx-vermietung-selected-clear" id="cmx-vermietung-selected-clear-' . \esc_attr($key) . '" aria-label="' . \esc_attr($title . '-Auswahl entfernen') . '" title="' . \esc_attr($title . '-Auswahl entfernen') . '">&times;</button>';
			echo '</div>';
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
		echo '<div class="cmx-vermietung-signature-grid">';
		cmx_vermietung_render_signature_pad('uebernahme', 'vermieter', (int) ($selected_uebernahme_signatures['vermieter'] ?? 0), $selected_vehicle_id > 0);
		cmx_vermietung_render_signature_pad('uebernahme', 'mieter', (int) ($selected_uebernahme_signatures['mieter'] ?? 0), $selected_vehicle_id > 0);
		echo '</div>';
		cmx_vermietung_render_fotos_section('uebernahme', $current_post_id, $selected_vehicle_id > 0);
		cmx_vermietung_render_transfer_video_field('uebernahme', $uebernahme_video_attachment_id, $selected_vehicle_id > 0);
		echo '</section>';
		echo '<section class="cmx-vermietung-upload-panel' . ($selected_contact_id > 0 ? '' : ' is-hidden') . '" id="cmx-vermietung-fuehrerausweis-panel">';
		echo '<div class="cmx-vermietung-panel-head">';
		echo '<h2 class="cmx-vermietung-panel-title" style="padding-bottom:15px;">Führerausweis</h2>';
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
		echo '<section class="cmx-vermietung-upload-panel is-collapsed' . ($selected_contact_id > 0 ? '' : ' is-hidden') . '" id="cmx-vermietung-identitaetskarte-panel">';
		echo '<div class="cmx-vermietung-panel-head is-collapsible">';
		echo '<div class="cmx-vermietung-panel-copy">';
		echo '<h2 class="cmx-vermietung-panel-title" style="padding-bottom:15px;">';
		echo '<button type="button" class="cmx-vermietung-panel-toggle" id="cmx-vermietung-identitaetskarte-toggle" aria-expanded="false" aria-controls="cmx-vermietung-identitaetskarte-body">';
		echo '<span class="cmx-vermietung-panel-toggle-label">Identitätskarte</span>';
		echo '<span class="cmx-vermietung-panel-toggle-icon" aria-hidden="true">&#9662;</span>';
		echo '</button>';
		echo '</h2>';
		echo '</div>';
		echo '</div>';
		echo '<div class="cmx-vermietung-collapsible-body" id="cmx-vermietung-identitaetskarte-body">';
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
		echo '</div>';
		echo '</section>';
		echo '<section class="cmx-vermietung-transfer-panel is-collapsed' . ($selected_vehicle_id > 0 ? '' : ' is-hidden') . '" id="cmx-vermietung-rueckgabe-panel">';
		echo '<div class="cmx-vermietung-panel-head is-collapsible">';
		echo '<div class="cmx-vermietung-panel-copy">';
		echo '<h2 class="cmx-vermietung-panel-title">';
		echo '<button type="button" class="cmx-vermietung-panel-toggle" id="cmx-vermietung-rueckgabe-toggle" aria-expanded="false" aria-controls="cmx-vermietung-rueckgabe-body">';
		echo '<span class="cmx-vermietung-panel-toggle-label">Rückgabe</span>';
		echo '<span class="cmx-vermietung-panel-toggle-icon" aria-hidden="true">&#9662;</span>';
		echo '</button>';
		echo '</h2>';
		echo '<p class="cmx-vermietung-panel-sub">Daten für die Rückgabe des gewählten Fahrzeugs.</p>';
		echo '</div>';
		echo '</div>';
		echo '<div class="cmx-vermietung-collapsible-body" id="cmx-vermietung-rueckgabe-body">';
		echo '<div class="cmx-vermietung-transfer-grid">';
		$rueckgabe_fields = [
			'ort' => ['label' => 'Ort', 'name' => 'cmx_vermietung_rueckgabe_ort', 'type' => 'text', 'step' => ''],
			'datum' => ['label' => 'Datum', 'name' => 'cmx_vermietung_rueckgabe_datum', 'type' => 'date', 'step' => ''],
			'uhrzeit' => ['label' => 'Uhrzeit', 'name' => 'cmx_vermietung_rueckgabe_uhrzeit', 'type' => 'time', 'step' => ''],
			'km_stand' => ['label' => 'KM-Stand', 'name' => 'cmx_vermietung_fahrzeug_km_stand_rueckgabe', 'type' => 'number', 'step' => '1'],
		];
		foreach ($rueckgabe_fields as $field_key => $field_config) {
			$field_value = \trim((string) ($selected_rueckgabe_values[$field_key] ?? ''));
			$label_id = 'cmx-vermietung-rueckgabe-label-' . $field_key;
			$is_actionable_label = \in_array($field_key, ['ort', 'datum', 'uhrzeit'], true);
			echo '<div class="cmx-vermietung-info-item">';
			echo '<label class="cmx-vermietung-info-label' . ($is_actionable_label ? ' is-actionable' : '') . '" id="' . \esc_attr($label_id) . '" for="cmx-vermietung-rueckgabe-' . \esc_attr($field_key) . '"' . ($is_actionable_label ? ' title="' . \esc_attr((string) $field_config['label'] . ' automatisch einsetzen') . '"' : '') . '>' . \esc_html((string) $field_config['label']) . '</label>';
			echo '<input class="cmx-vermietung-info-value" id="cmx-vermietung-rueckgabe-' . \esc_attr($field_key) . '" name="' . \esc_attr((string) $field_config['name']) . '" type="' . \esc_attr((string) $field_config['type']) . '" value="' . \esc_attr($field_value) . '"' . (((string) $field_config['step']) !== '' ? ' step="' . \esc_attr((string) $field_config['step']) . '"' : '') . ($selected_vehicle_id > 0 ? '' : ' disabled') . '>';
			echo '</div>';
		}
		echo '</div>';
		echo '<div class="cmx-vermietung-signature-grid">';
		cmx_vermietung_render_signature_pad('rueckgabe', 'vermieter', (int) ($selected_rueckgabe_signatures['vermieter'] ?? 0), $selected_vehicle_id > 0);
		cmx_vermietung_render_signature_pad('rueckgabe', 'mieter', (int) ($selected_rueckgabe_signatures['mieter'] ?? 0), $selected_vehicle_id > 0);
		echo '</div>';
		cmx_vermietung_render_fotos_section('rueckgabe', $current_post_id, $selected_vehicle_id > 0);
		cmx_vermietung_render_transfer_video_field('rueckgabe', $rueckgabe_video_attachment_id, $selected_vehicle_id > 0);
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
				var licensePanel=document.getElementById("cmx-vermietung-fuehrerausweis-panel");
				var statusNotice=document.getElementById("cmx-vermietung-status-notice");
				var licenseRequired=licenseFile && String(licenseFile.getAttribute("data-required")||"0")==="1";
				var identityFile=document.getElementById("cmx-vermietung-identitaetskarte-file");
				var identityPreview=document.getElementById("cmx-vermietung-identitaetskarte-preview");
				var identityMeta=document.getElementById("cmx-vermietung-identitaetskarte-meta");
				var identityPanel=document.getElementById("cmx-vermietung-identitaetskarte-panel");
				var identityToggle=document.getElementById("cmx-vermietung-identitaetskarte-toggle");
				var ajaxUrl=' . \wp_json_encode($ajax_url) . ';
				var photoUploadNonce=' . \wp_json_encode($photo_upload_nonce) . ';
				var videoUploadNonce=' . \wp_json_encode($video_upload_nonce) . ';
				var emptyPhotoMarkup=' . \wp_json_encode(cmx_vermietung_fotos_empty_markup()) . ';
				var removePhotoIcon=' . \wp_json_encode(cmx_vermietung_fotos_remove_icon()) . ';
				var contractPicker=document.getElementById("cmx-vermietung-vertrag-picker");
				var contractSearch=document.getElementById("cmx-vermietung-vertrag-search");
				var contractResults=document.getElementById("cmx-vermietung-vertrag-results");
				var contractEmpty=document.getElementById("cmx-vermietung-vertrag-empty");
				var contractActive=null;
				var vehicleInfoPanel=document.getElementById("cmx-vermietung-info-panel");
				var transferPanel=document.getElementById("cmx-vermietung-uebernahme-panel");
				var returnPanel=document.getElementById("cmx-vermietung-rueckgabe-panel");
				var returnToggle=document.getElementById("cmx-vermietung-rueckgabe-toggle");
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
				var returnNodes={
					ort:document.getElementById("cmx-vermietung-rueckgabe-ort"),
					datum:document.getElementById("cmx-vermietung-rueckgabe-datum"),
					uhrzeit:document.getElementById("cmx-vermietung-rueckgabe-uhrzeit"),
					km_stand:document.getElementById("cmx-vermietung-rueckgabe-km_stand")
				};
				var transferLabels={
					ort:document.getElementById("cmx-vermietung-uebernahme-label-ort"),
					datum:document.getElementById("cmx-vermietung-uebernahme-label-datum"),
					uhrzeit:document.getElementById("cmx-vermietung-uebernahme-label-uhrzeit")
				};
				var returnLabels={
					ort:document.getElementById("cmx-vermietung-rueckgabe-label-ort"),
					datum:document.getElementById("cmx-vermietung-rueckgabe-label-datum"),
					uhrzeit:document.getElementById("cmx-vermietung-rueckgabe-label-uhrzeit")
				};
				var signaturePads=[];
				var transferPhotoSections={};
				var transferVideoSections={};
				var pickers={};
				function normalize(value){return String(value||"").toLowerCase().trim();}
				function escapeHtml(value){
					return String(value||"").replace(/[&<>"]/g, function(char){
						return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[char] || char;
					});
				}
				function currentPostId(){
					var postField=document.getElementById("cmx-vermietung-post-id");
					return postField ? Number(postField.value || 0) : 0;
				}
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
				function updateContactUploadPanels(){
					var hasContact=Number(kontaktInput.value||0)>0;
					if(licensePanel){
						licensePanel.classList.toggle("is-hidden", !hasContact);
					}
					if(identityPanel){
						identityPanel.classList.toggle("is-hidden", !hasContact);
					}
					if(licenseFile){
						licenseFile.disabled=!hasContact;
					}
					if(identityFile){
						identityFile.disabled=!hasContact;
					}
				}
				function syncReturnPanelState(){
					if(!returnPanel || !returnToggle){return;}
					returnToggle.setAttribute("aria-expanded", returnPanel.classList.contains("is-collapsed") ? "false" : "true");
				}
				function syncIdentityPanelState(){
					if(!identityPanel || !identityToggle){return;}
					identityToggle.setAttribute("aria-expanded", identityPanel.classList.contains("is-collapsed") ? "false" : "true");
				}
				function scrollPageToBottom(){
					var target=Math.max(
						document.body ? document.body.scrollHeight : 0,
						document.documentElement ? document.documentElement.scrollHeight : 0
					);
					window.setTimeout(function(){
						try{
							window.scrollTo({top:target,behavior:"smooth"});
						}catch(err){
							window.scrollTo(0,target);
						}
					}, 120);
				}
				function initStatusNotice(){
					if(!statusNotice){return;}
					window.setTimeout(function(){
						statusNotice.classList.add("is-hidden");
						window.setTimeout(function(){
							if(statusNotice && statusNotice.parentNode){
								statusNotice.parentNode.removeChild(statusNotice);
							}
						}, 320);
					}, 5000);
				}
				function updateUploadPreview(file, previewNode, metaNode){
					if(!previewNode || !metaNode){return;}
					if(!file){
						previewNode.classList.remove("is-active");
						previewNode.removeAttribute("src");
						if(previewNode.tagName==="VIDEO"){
							try{previewNode.load();}catch(err){}
						}
						metaNode.textContent="Noch kein Foto gewählt.";
						return;
					}
					metaNode.textContent=String(file.name||"");
					if(previewNode.tagName==="VIDEO"){
						if(String(file.type||"").indexOf("video/")!==0){
							previewNode.classList.remove("is-active");
							previewNode.removeAttribute("src");
							try{previewNode.load();}catch(err){}
							return;
						}
						previewNode.src=URL.createObjectURL(file);
						previewNode.classList.add("is-active");
						try{previewNode.load();}catch(err){}
						return;
					}
					if(String(file.type||"").indexOf("image/")!==0){
						previewNode.classList.remove("is-active");
						previewNode.removeAttribute("src");
						return;
					}
					previewNode.src=URL.createObjectURL(file);
					previewNode.classList.add("is-active");
				}
				function initTransferPhotoSection(sectionId){
					var host=document.getElementById(sectionId);
					var rowsHost=document.getElementById(sectionId+"-rows");
					var addButton=document.getElementById(sectionId+"-add");
					var template=document.getElementById(sectionId+"-template");
					var enabled=host && String(host.getAttribute("data-enabled")||"0")==="1";
					var rowIndex=rowsHost ? rowsHost.querySelectorAll(".cmx-vermietung-fotos-row").length : 0;
					if(!host || !rowsHost || !addButton || !template){return null;}
					if(host.dataset.cmxFotosBound==="1"){
						return {
							setEnabled:function(nextEnabled){
								host.dataset.enabled=nextEnabled ? "1" : "0";
							}
						};
					}
					host.dataset.cmxFotosBound="1";

					function attachmentPayloadFromResponse(json){
						return {
							id:json && json.data ? Number(json.data.id||0) : 0,
							preview_url:json && json.data ? String(json.data.url||"") : "",
							file_url:json && json.data ? String((json.data.file_url||json.data.url||"")) : "",
							label:json && json.data ? String(json.data.label||"") : ""
						};
					}
					function renderAttachment(row, payload){
						var attachmentInput=row.querySelector(".cmx-vermietung-fotos-attachment-id");
						var preview=row.querySelector(".cmx-vermietung-fotos-preview");
						var fileNode=row.querySelector(".cmx-vermietung-fotos-file");
						var data=payload || {id:0,preview_url:"",file_url:"",label:""};
						var attachmentId=String(data.id||"");
						var previewUrl=String(data.preview_url||"");
						var fileUrl=String(data.file_url||"");
						var label=String(data.label||"");
						if(attachmentInput){
							attachmentInput.value=attachmentId;
						}
						if(preview){
							if(previewUrl!==""){
								preview.innerHTML="<button type=\"button\" class=\"cmx-vermietung-fotos-image-remove\" aria-label=\"Bild entfernen\"" + (enabled ? "" : " disabled") + ">" + removePhotoIcon + "</button><img src=\"" + escapeHtml(previewUrl) + "\" alt=\"\" class=\"cmx-vermietung-fotos-preview-image\">";
							}else{
								preview.innerHTML="<button type=\"button\" class=\"cmx-vermietung-fotos-image-remove\" aria-label=\"Bild entfernen\" style=\"display:none;\"" + (enabled ? "" : " disabled") + ">" + removePhotoIcon + "</button>" + emptyPhotoMarkup;
							}
							preview.classList.remove("is-busy");
							preview.classList.remove("is-dragover");
							preview.classList.toggle("is-disabled", !enabled);
							preview.setAttribute("tabindex", enabled ? "0" : "-1");
						}
						if(fileNode){
							if(fileUrl!=="" && label!==""){
								fileNode.innerHTML="<a href=\"" + escapeHtml(fileUrl) + "\" target=\"_blank\" rel=\"noopener noreferrer\">" + escapeHtml(label) + "</a>";
							}else{
								fileNode.textContent="";
							}
						}
						var removeImageButton=row.querySelector(".cmx-vermietung-fotos-image-remove");
						if(removeImageButton){
							removeImageButton.style.display=attachmentId!=="" && attachmentId!=="0" ? "" : "none";
							removeImageButton.disabled=!enabled;
						}
					}
					function setBusy(row, text){
						var preview=row.querySelector(".cmx-vermietung-fotos-preview");
						var fileNode=row.querySelector(".cmx-vermietung-fotos-file");
						if(preview){
							preview.classList.add("is-busy");
						}
						if(fileNode){
							fileNode.textContent=text || "Upload läuft...";
						}
					}
					function setIdle(row){
						var preview=row.querySelector(".cmx-vermietung-fotos-preview");
						if(preview){
							preview.classList.remove("is-busy");
							preview.classList.remove("is-dragover");
						}
					}
					function updateRowEnabled(row){
						var select=row.querySelector(".cmx-vermietung-fotos-select");
						var fileInput=row.querySelector(".cmx-vermietung-fotos-file-input");
						var rowRemove=row.querySelector(".cmx-vermietung-fotos-row-remove");
						var preview=row.querySelector(".cmx-vermietung-fotos-preview");
						var imageRemove=row.querySelector(".cmx-vermietung-fotos-image-remove");
						if(select){select.disabled=!enabled;}
						if(fileInput){fileInput.disabled=!enabled;}
						if(rowRemove){rowRemove.disabled=!enabled;}
						if(preview){
							preview.classList.toggle("is-disabled", !enabled);
							preview.setAttribute("tabindex", enabled ? "0" : "-1");
						}
						if(imageRemove){imageRemove.disabled=!enabled;}
					}
					function setEnabled(nextEnabled){
						enabled=!!nextEnabled;
						host.setAttribute("data-enabled", enabled ? "1" : "0");
						addButton.disabled=!enabled;
						rowsHost.querySelectorAll(".cmx-vermietung-fotos-row").forEach(updateRowEnabled);
					}
					function uploadFile(row, file){
						if(!enabled || !row || !file){return;}
						if(String(file.type||"").indexOf("image/")!==0){
							var fileNode=row.querySelector(".cmx-vermietung-fotos-file");
							if(fileNode){fileNode.textContent="Bitte nur Bilddateien hochladen.";}
							return;
						}
						var data=new FormData();
						data.append("action", "cmx_carent_fotos_upload");
						data.append("nonce", photoUploadNonce);
						data.append("post_id", String(currentPostId()));
						data.append("file", file);
						setBusy(row, "Upload läuft: " + (file.name||""));
						fetch(ajaxUrl, {
							method:"POST",
							credentials:"same-origin",
							body:data
						}).then(function(response){
							return response.json();
						}).then(function(json){
							if(!json || !json.success || !json.data){
								var msg=(json && json.data && json.data.message) ? String(json.data.message) : "Upload fehlgeschlagen.";
								var fileNode=row.querySelector(".cmx-vermietung-fotos-file");
								if(fileNode){fileNode.textContent=msg;}
								setIdle(row);
								return;
							}
							renderAttachment(row, attachmentPayloadFromResponse(json));
						}).catch(function(){
							var fileNode=row.querySelector(".cmx-vermietung-fotos-file");
							if(fileNode){fileNode.textContent="Upload fehlgeschlagen.";}
							setIdle(row);
						});
					}
					function initRow(row){
						if(!row || row.dataset.cmxFotosRowBound==="1"){return;}
						row.dataset.cmxFotosRowBound="1";
						var fileInput=row.querySelector(".cmx-vermietung-fotos-file-input");
						var preview=row.querySelector(".cmx-vermietung-fotos-preview");
						var removeButton=row.querySelector(".cmx-vermietung-fotos-row-remove");
						if(preview && fileInput){
							preview.addEventListener("click", function(event){
								if(!enabled){return;}
								if(event.target && event.target.closest(".cmx-vermietung-fotos-image-remove")){return;}
								event.preventDefault();
								fileInput.click();
							});
							preview.addEventListener("keydown", function(event){
								if(!enabled){return;}
								if(event.key!=="Enter" && event.key!==" "){return;}
								event.preventDefault();
								fileInput.click();
							});
							preview.addEventListener("dragover", function(event){
								if(!enabled){return;}
								event.preventDefault();
								preview.classList.add("is-dragover");
							});
							preview.addEventListener("dragleave", function(event){
								event.preventDefault();
								preview.classList.remove("is-dragover");
							});
							preview.addEventListener("drop", function(event){
								if(!enabled){return;}
								event.preventDefault();
								preview.classList.remove("is-dragover");
								var files=event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files : [];
								if(files.length){uploadFile(row, files[0]);}
							});
						}
						if(fileInput){
							fileInput.addEventListener("change", function(){
								if(fileInput.files && fileInput.files[0]){
									uploadFile(row, fileInput.files[0]);
								}
								fileInput.value="";
							});
						}
						row.addEventListener("click", function(event){
							var removeImageButton=event.target && event.target.closest ? event.target.closest(".cmx-vermietung-fotos-image-remove") : null;
							if(!removeImageButton){return;}
							event.preventDefault();
							event.stopPropagation();
							if(!enabled){return;}
							renderAttachment(row, {id:0,preview_url:"",file_url:"",label:""});
						});
						if(removeButton){
							removeButton.addEventListener("click", function(event){
								event.preventDefault();
								if(!enabled){return;}
								row.remove();
								if(!rowsHost.querySelector(".cmx-vermietung-fotos-row")){
									addRow();
								}
							});
						}
						updateRowEnabled(row);
					}
					function addRow(){
						var html=String(template.innerHTML || "").replace(/__INDEX__/g, String(rowIndex));
						rowIndex += 1;
						var wrapper=document.createElement("div");
						wrapper.innerHTML=html.trim();
						var row=wrapper.firstElementChild;
						if(!row){return;}
						rowsHost.appendChild(row);
						initRow(row);
						renderAttachment(row, {id:0,preview_url:"",file_url:"",label:""});
					}
					rowsHost.querySelectorAll(".cmx-vermietung-fotos-row").forEach(function(row){
						initRow(row);
					});
					addButton.addEventListener("click", function(event){
						event.preventDefault();
						if(!enabled){return;}
						addRow();
					});
					setEnabled(enabled);
					return {setEnabled:setEnabled};
				}
				function initTransferVideoSection(prefix){
					var host=document.getElementById(prefix);
					var attachmentInput=document.getElementById(prefix+"-attachment-id");
					var fileInput=document.getElementById(prefix+"-file");
					var dropzone=document.getElementById(prefix+"-dropzone");
					var preview=document.getElementById(prefix+"-preview");
					var status=document.getElementById(prefix+"-meta");
					var removeButton=document.getElementById(prefix+"-remove");
					var enabled=host && String(host.getAttribute("data-enabled")||"0")==="1";
					if(!host || !attachmentInput || !fileInput || !dropzone || !preview || !status || !removeButton){return null;}
					if(host.dataset.cmxVideoBound==="1"){
						return {
							setEnabled:function(nextEnabled){
								host.setAttribute("data-enabled", nextEnabled ? "1" : "0");
							}
						};
					}
					host.dataset.cmxVideoBound="1";
					function updateEnabledState(){
						fileInput.disabled=!enabled;
						removeButton.disabled=!enabled;
						dropzone.classList.toggle("is-disabled", !enabled);
					}
					function renderStatus(label, fileUrl){
						var safeLabel=String(label||"");
						var safeUrl=String(fileUrl||"");
						if(!safeLabel){
							status.textContent="";
							return;
						}
						if(!safeUrl){
							status.textContent=safeLabel;
							return;
						}
						status.innerHTML="<a href=\"" + escapeHtml(safeUrl) + "\" target=\"_blank\" rel=\"noopener noreferrer\">" + escapeHtml(safeLabel) + "</a>";
					}
					function setEnabled(nextEnabled){
						enabled=!!nextEnabled;
						host.setAttribute("data-enabled", enabled ? "1" : "0");
						updateEnabledState();
					}
					function renderEmpty(){
						attachmentInput.value="";
						preview.classList.remove("is-active");
						preview.removeAttribute("src");
						try{preview.load();}catch(err){}
						status.textContent="";
						removeButton.style.display="none";
					}
					function renderVideo(payload){
						var data=payload || {id:0,url:"",file_url:"",label:""};
						attachmentInput.value=String(data.id||"");
						if(String(data.url||"")!==""){
							preview.src=String(data.url||"");
							preview.classList.add("is-active");
							try{preview.load();}catch(err){}
							removeButton.style.display="";
							renderStatus(data.label||"", data.file_url||data.url||"");
						}else{
							renderEmpty();
						}
					}
					function uploadFile(file){
						if(!enabled || !file){return;}
						if(String(file.type||"").indexOf("video/")!==0){
							status.textContent="Bitte nur Videodateien hochladen.";
							return;
						}
						var data=new FormData();
						data.append("action", "cmx_carent_transfer_video_upload");
						data.append("nonce", videoUploadNonce);
						data.append("post_id", String(currentPostId()));
						data.append("file", file);
						status.textContent="Upload läuft: " + (file.name||"");
						dropzone.style.opacity=".6";
						fetch(ajaxUrl, {
							method:"POST",
							credentials:"same-origin",
							body:data
						}).then(function(response){
							return response.json();
						}).then(function(json){
							dropzone.style.opacity="1";
							if(!json || !json.success || !json.data){
								status.textContent=(json && json.data && json.data.message) ? String(json.data.message) : "Upload fehlgeschlagen.";
								return;
							}
							renderVideo({
								id:Number(json.data.id||0),
								url:String(json.data.url||""),
								file_url:String(json.data.file_url||json.data.url||""),
								label:String(json.data.label||"")
							});
						}).catch(function(){
							dropzone.style.opacity="1";
							status.textContent="Upload fehlgeschlagen.";
						});
					}
					dropzone.addEventListener("click", function(event){
						if(!enabled){return;}
						if(event.target && event.target.closest && event.target.closest("#" + prefix + "-remove")){return;}
						fileInput.click();
					});
					fileInput.addEventListener("change", function(){
						if(fileInput.files && fileInput.files[0]){
							uploadFile(fileInput.files[0]);
						}
						fileInput.value="";
					});
					dropzone.addEventListener("dragover", function(event){
						if(!enabled){return;}
						event.preventDefault();
						dropzone.style.opacity=".75";
					});
					dropzone.addEventListener("dragleave", function(event){
						event.preventDefault();
						dropzone.style.opacity="1";
					});
					dropzone.addEventListener("drop", function(event){
						if(!enabled){return;}
						event.preventDefault();
						dropzone.style.opacity="1";
						var files=event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files : [];
						if(files.length){uploadFile(files[0]);}
					});
					removeButton.addEventListener("click", function(event){
						event.preventDefault();
						event.stopPropagation();
						if(!enabled){return;}
						renderEmpty();
					});
					updateEnabledState();
					return {setEnabled:setEnabled};
				}
				function resetSignatureSurface(pad, dropExisting){
					if(!pad || !pad.ctx || !pad.canvas){return;}
					pad.ctx.setTransform(1,0,0,1,0,0);
					pad.ctx.clearRect(0,0,pad.canvas.width,pad.canvas.height);
					pad.ctx.fillStyle="#ffffff";
					pad.ctx.fillRect(0,0,pad.canvas.width,pad.canvas.height);
					pad.ctx.strokeStyle="#1d2327";
					pad.ctx.lineWidth=2.5;
					pad.ctx.lineCap="round";
					pad.ctx.lineJoin="round";
					pad.drawing=false;
					pad.dirty=false;
					if(dropExisting){
						pad.hasExistingImage=false;
						pad.existingSrc="";
					}
				}
				function drawExistingSignature(pad, src){
					if(!pad || !src){return;}
					var image=new Image();
					image.onload=function(){
						resetSignatureSurface(pad, false);
						var scale=Math.min(pad.canvas.width / image.width, pad.canvas.height / image.height);
						var drawWidth=image.width * scale;
						var drawHeight=image.height * scale;
						var offsetX=(pad.canvas.width - drawWidth) / 2;
						var offsetY=(pad.canvas.height - drawHeight) / 2;
						pad.ctx.drawImage(image, offsetX, offsetY, drawWidth, drawHeight);
						pad.hasExistingImage=true;
					};
					image.src=src;
				}
				function getSignaturePoint(canvas, event){
					var rect=canvas.getBoundingClientRect();
					if(!rect.width || !rect.height){return {x:0,y:0};}
					return {
						x:(event.clientX - rect.left) * (canvas.width / rect.width),
						y:(event.clientY - rect.top) * (canvas.height / rect.height)
					};
				}
				function setSignaturePadEnabled(enabled){
					signaturePads.forEach(function(pad){
						pad.disabled=!enabled;
						pad.item.classList.toggle("is-disabled", !enabled);
						if(pad.clearButton){
							pad.clearButton.disabled=!enabled;
						}
						if(!enabled){
							pad.canvas.setAttribute("data-disabled","1");
						}else{
							pad.canvas.removeAttribute("data-disabled");
						}
					});
				}
				function initSignaturePad(item){
					if(!item){return;}
					var prefix=String(item.getAttribute("data-signature-item")||"");
					if(prefix===""){return;}
					var canvas=document.getElementById(prefix+"-canvas");
					if(!canvas || typeof canvas.getContext!=="function"){return;}
					var ctx=canvas.getContext("2d");
					if(!ctx){return;}
					var clearButton=item.querySelector("[data-signature-clear]");
					var hiddenInput=document.getElementById(prefix+"-input");
					var clearInput=document.getElementById(prefix+"-clear");
					var metaNode=document.getElementById(prefix+"-meta");
					var existingSrc=String(canvas.getAttribute("data-existing-src")||"").trim();
					var pad={
						item:item,
						prefix:prefix,
						canvas:canvas,
						ctx:ctx,
						hiddenInput:hiddenInput,
						clearInput:clearInput,
						clearButton:clearButton,
						metaNode:metaNode,
						existingSrc:existingSrc,
						hasExistingImage:false,
						dirty:false,
						drawing:false,
						disabled:String(canvas.getAttribute("data-disabled")||"0")==="1"
					};
					resetSignatureSurface(pad, false);
					if(existingSrc!==""){
						drawExistingSignature(pad, existingSrc);
					}
					canvas.addEventListener("pointerdown", function(event){
						if(pad.disabled){return;}
						event.preventDefault();
						if(pad.hasExistingImage && !pad.dirty){
							resetSignatureSurface(pad, true);
							if(pad.clearInput){pad.clearInput.value="1";}
						}
						var point=getSignaturePoint(canvas, event);
						pad.drawing=true;
						pad.dirty=true;
						if(pad.clearInput){pad.clearInput.value="0";}
						pad.ctx.beginPath();
						pad.ctx.moveTo(point.x, point.y);
						if(typeof canvas.setPointerCapture==="function"){
							try{canvas.setPointerCapture(event.pointerId);}catch(err){}
						}
					});
					canvas.addEventListener("pointermove", function(event){
						if(!pad.drawing || pad.disabled){return;}
						event.preventDefault();
						var point=getSignaturePoint(canvas, event);
						pad.ctx.lineTo(point.x, point.y);
						pad.ctx.stroke();
					});
					["pointerup","pointerleave","pointercancel"].forEach(function(eventName){
						canvas.addEventListener(eventName, function(event){
							if(!pad.drawing){return;}
							event.preventDefault();
							pad.drawing=false;
							pad.ctx.beginPath();
							if(typeof canvas.releasePointerCapture==="function" && event.pointerId !== undefined){
								try{canvas.releasePointerCapture(event.pointerId);}catch(err){}
							}
						});
					});
					if(clearButton){
						clearButton.addEventListener("click", function(event){
							event.preventDefault();
							resetSignatureSurface(pad, true);
							if(pad.hiddenInput){pad.hiddenInput.value="";}
							if(pad.clearInput){pad.clearInput.value="1";}
							if(pad.metaNode){pad.metaNode.textContent="Unterschrift geleert.";}
						});
					}
					signaturePads.push(pad);
				}
				function updateVehicleInfo(item){
					var hasVehicle=!!item;
					var values=item ? {
						kennzeichen:String(item.getAttribute("data-kennzeichen")||"").trim(),
						km_stand_uebernahme:String(item.getAttribute("data-km-stand-uebernahme")||"").trim(),
						km_stand_rueckgabe:String(item.getAttribute("data-km-stand-rueckgabe")||"").trim(),
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
					if(returnPanel){
						returnPanel.classList.toggle("is-hidden", !hasVehicle);
						if(!hasVehicle){
							returnPanel.classList.add("is-collapsed");
						}
					}
					syncReturnPanelState();
					setSignaturePadEnabled(hasVehicle);
					Object.keys(transferPhotoSections).forEach(function(key){
						if(transferPhotoSections[key] && typeof transferPhotoSections[key].setEnabled==="function"){
							transferPhotoSections[key].setEnabled(hasVehicle);
						}
					});
					Object.keys(transferVideoSections).forEach(function(key){
						if(transferVideoSections[key] && typeof transferVideoSections[key].setEnabled==="function"){
							transferVideoSections[key].setEnabled(hasVehicle);
						}
					});
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
					Object.keys(returnNodes).forEach(function(key){
						if(!returnNodes[key]){return;}
						returnNodes[key].disabled=!hasVehicle;
					});
					if(returnNodes.km_stand){
						returnNodes.km_stand.value=hasVehicle ? values.km_stand_rueckgabe : "";
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
				if(returnToggle && returnPanel){
					returnToggle.addEventListener("click", function(){
						if(returnPanel.classList.contains("is-hidden")){return;}
						returnPanel.classList.toggle("is-collapsed");
						syncReturnPanelState();
						scrollPageToBottom();
					});
					syncReturnPanelState();
				}
				if(identityToggle && identityPanel){
					identityToggle.addEventListener("click", function(){
						if(identityPanel.classList.contains("is-hidden")){return;}
						identityPanel.classList.toggle("is-collapsed");
						syncIdentityPanelState();
						scrollPageToBottom();
					});
					syncIdentityPanelState();
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
					var selectedMeta=document.getElementById("cmx-vermietung-selected-meta-"+key);
					var selectedClear=document.getElementById("cmx-vermietung-selected-clear-"+key);
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
					function clear(keepInput, focusAfter){
						hidden.value="";
						valueNode.textContent=(key==="kontakt") ? "Noch keiner gewählt" : "Noch keines gewählt";
						if(selectedNode){selectedNode.classList.remove("is-active");}
						if(selectedTitle){selectedTitle.textContent="";}
						if(selectedMeta){
							selectedMeta.textContent="";
							selectedMeta.classList.add("is-empty");
						}
						if(keepInput!==true){
							input.value="";
						}
						markActive(null);
						close();
						if(key==="kontakt"){
							setArtikelLocked(true);
						}
						if(key==="artikel"){
							updateVehicleInfo(null);
						}
						updateSubmit();
						updateContactUploadPanels();
						if(focusAfter===true && !input.disabled){
							window.setTimeout(function(){
								input.focus();
								try{input.select();}catch(err){}
								open();
							}, 30);
						}
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
						var meta=item ? String(item.getAttribute("data-selected-meta")||"").trim() : "";
						hidden.value=currentId;
						valueNode.textContent=title!=="" ? title : ((key==="kontakt") ? "Noch keiner gewählt" : "Noch keines gewählt");
						if(selectedNode){
							selectedNode.classList.toggle("is-active", title!=="");
						}
						if(selectedTitle){
							selectedTitle.textContent=title;
						}
						if(selectedMeta){
							selectedMeta.textContent=meta;
							selectedMeta.classList.toggle("is-empty", meta==="");
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
						updateContactUploadPanels();
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
						if(input.value==="" && hidden.value!==""){
							clear(true, true);
							return;
						}
						open();
					});
					input.addEventListener("search", function(){
						if(input.value==="" && hidden.value!==""){
							clear(true, true);
							return;
						}
						open();
					});
					input.addEventListener("focus", open);
					input.addEventListener("click", open);
					function closeWhenPickerFocusLeaves(){
						window.setTimeout(function(){
							if(panel.contains(document.activeElement)){return;}
							close();
						}, 120);
					}
					input.addEventListener("blur", closeWhenPickerFocusLeaves);
					panel.addEventListener("focusout", closeWhenPickerFocusLeaves);
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
					if(selectedClear){
						selectedClear.addEventListener("click", function(event){
							event.preventDefault();
							clear(false, true);
						});
					}
					pickers[key]={
						close: close,
						clear: clear
					};
				}
				bindPicker("kontakt");
				bindPicker("artikel");
				transferPhotoSections.uebernahme=initTransferPhotoSection("cmx-vermietung-fotos-uebernahme");
				transferPhotoSections.rueckgabe=initTransferPhotoSection("cmx-vermietung-fotos-rueckgabe");
				transferVideoSections.uebernahme=initTransferVideoSection("cmx-vermietung-video-uebernahme");
				transferVideoSections.rueckgabe=initTransferVideoSection("cmx-vermietung-video-rueckgabe");
				Array.prototype.slice.call(document.querySelectorAll("[data-signature-item]")).forEach(initSignaturePad);
				setArtikelLocked(String(kontaktInput.value||"")==="");
				setSignaturePadEnabled(Number(artikelInput.value||0)>0);
				updateVehicleInfo(Number(artikelInput.value||0)>0 ? document.querySelector(\'#cmx-vermietung-list-artikel .cmx-vermietung-item[data-id="\'+String(artikelInput.value||"")+\'"]\') : null);
				updateContactUploadPanels();
				syncIdentityPanelState();
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
				if(returnNodes.ort && returnLabels.ort){
					returnLabels.ort.addEventListener("click", function(event){
						if(returnNodes.ort.disabled){return;}
						event.preventDefault();
						getCurrentLocation(function(location){
							if(!location){return;}
							var value=String(location.address || "").trim();
							if(!value && location.lat && location.lon){
								value=String(location.lat)+", "+String(location.lon);
							}
							if(!value){return;}
							returnNodes.ort.value=value;
							triggerInputEvents(returnNodes.ort);
							returnNodes.ort.focus();
						});
					});
				}
				if(returnNodes.datum && returnLabels.datum){
					returnLabels.datum.addEventListener("click", function(event){
						if(returnNodes.datum.disabled){return;}
						event.preventDefault();
						returnNodes.datum.value=getTodayValue();
						triggerInputEvents(returnNodes.datum);
						returnNodes.datum.focus();
					});
				}
				if(returnNodes.uhrzeit && returnLabels.uhrzeit){
					returnLabels.uhrzeit.addEventListener("click", function(event){
						if(returnNodes.uhrzeit.disabled){return;}
						event.preventDefault();
						returnNodes.uhrzeit.value=getCurrentTimeValue();
						triggerInputEvents(returnNodes.uhrzeit);
						returnNodes.uhrzeit.focus();
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
						return;
					}
					signaturePads.forEach(function(pad){
						if(!pad.hiddenInput){return;}
						if(pad.dirty){
							pad.hiddenInput.value=pad.canvas.toDataURL("image/png");
							if(pad.clearInput){pad.clearInput.value="0";}
						}else{
							pad.hiddenInput.value="";
						}
					});
				});
				initStatusNotice();
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
