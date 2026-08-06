<?php namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_create_beleg_action_url')) {
	function cmx_carent_create_beleg_action_url(int $post_id): string {
		if ($post_id <= 0 || (string) \get_post_type($post_id) !== 'carent') {
			return '';
		}

		return (string) \wp_nonce_url(
			\add_query_arg([
				'action'    => 'cmx_carent_create_beleg',
				'carent_id' => $post_id,
			], \admin_url('admin-post.php')),
			'cmx_carent_create_beleg_' . $post_id
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_existing_beleg_id')) {
	function cmx_carent_existing_beleg_id(int $post_id): int {
		if ($post_id <= 0 || !\post_type_exists('belege')) {
			return 0;
		}

		$ids = \get_posts([
			'post_type' => 'belege',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'fields' => 'ids',
			'posts_per_page' => 1,
			'no_found_rows' => true,
			'suppress_filters' => true,
			'orderby' => ['date' => 'DESC', 'ID' => 'DESC'],
			'order' => 'DESC',
			'meta_query' => [[
				'key' => '_cmx_carent_vermietung_id',
				'value' => $post_id,
				'compare' => '=',
			]],
		]);

		return !empty($ids[0]) ? (int) $ids[0] : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_user_can_create_beleg')) {
	function cmx_carent_user_can_create_beleg(): bool {
		if (!\post_type_exists('belege')) {
			return false;
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_post_type_can_create') && !cmx_post_type_can_create('belege')) {
			return false;
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_post_type_can_publish') && !cmx_post_type_can_publish('belege')) {
			return false;
		}
		$obj = \get_post_type_object('belege');
		$cap = $obj && isset($obj->cap->edit_posts) ? (string) $obj->cap->edit_posts : 'edit_posts';
		return \current_user_can($cap);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_beleg_float')) {
	function cmx_carent_beleg_float(string $value): float {
		$value = \trim($value);
		if ($value === '') {
			return 0.0;
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_norm_decimal')) {
			$value = (string) cmx_norm_decimal($value);
		} else {
			$value = \str_replace(["\xc2\xa0", ' ', "'"], '', $value);
			$value = \str_replace(',', '.', $value);
		}
		$number = (float) $value;
		return \is_finite($number) ? (float) \round($number, 2) : 0.0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_beleg_format_date')) {
	function cmx_carent_beleg_format_date(string $value): string {
		$value = \trim($value);
		if ($value === '') {
			return '';
		}
		if (\preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match)) {
			return $match[3] . '.' . $match[2] . '.' . $match[1];
		}
		$timestamp = \strtotime($value);
		return $timestamp ? \wp_date('d.m.Y', $timestamp) : $value;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_beleg_position_row')) {
	function cmx_carent_beleg_position_row(array $data): array {
		$vehicle = (array) ($data['vehicle'] ?? []);
		$artikel_id = (int) ($vehicle['article_id'] ?? 0);
		$variant_index = (int) ($vehicle['variant_index'] ?? 0);
		$menge = cmx_carent_beleg_float((string) ($vehicle['anzahl'] ?? ''));
		$preis = cmx_carent_beleg_float((string) ($vehicle['mietpreis'] ?? ''));

		$row = \function_exists(__NAMESPACE__ . '\\cmx_beleg_position_row_from_artikel')
			? (array) cmx_beleg_position_row_from_artikel($artikel_id)
			: [];
		if ($row === []) {
			$row = [
				'artikel_id' => $artikel_id,
				'artikel_name' => \trim((string) ($vehicle['label'] ?? \get_the_title($artikel_id))),
				'artikel_variant_index' => '',
				'menge' => 1,
				'einheit_id' => 0,
				'unit' => '',
				'preis' => '',
				'rabatt' => '',
				'beschreibung' => '',
			];
		}

		$label = \trim((string) ($vehicle['label'] ?? ''));
		if ($label !== '') {
			$row['artikel_name'] = $label;
		}
		$row['artikel_id'] = $artikel_id;
		$row['artikel_variant_index'] = $variant_index > 0 ? $variant_index : '';
		$row['menge'] = $menge > 0 ? $menge : 1;
		if ($preis > 0) {
			$row['preis'] = \number_format($preis, 2, '.', '');
		}

		$uebernahme = (array) (($data['transfer'] ?? [])['uebernahme'] ?? []);
		$rueckgabe = (array) (($data['transfer'] ?? [])['rueckgabe'] ?? []);
		$period = [];
		$uebernahme_datum = cmx_carent_beleg_format_date((string) ($uebernahme['datum'] ?? ''));
		$rueckgabe_datum = cmx_carent_beleg_format_date((string) ($rueckgabe['datum'] ?? ''));
		if ($uebernahme_datum !== '') {
			$period[] = 'Übernahme ' . $uebernahme_datum;
		}
		if ($rueckgabe_datum !== '') {
			$period[] = 'Rückgabe ' . $rueckgabe_datum;
		}
		$row['beschreibung'] = \implode(' | ', $period);

		return $row;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_beleg_extra_position')) {
	function cmx_carent_beleg_extra_position(string $label, float $amount): array {
		return [
			'artikel_id' => 0,
			'artikel_name' => $label,
			'artikel_variant_index' => '',
			'menge' => 1,
			'einheit_id' => 0,
			'unit' => '',
			'preis' => \number_format($amount, 2, '.', ''),
			'rabatt' => '',
			'beschreibung' => '',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_beleg_positions')) {
	function cmx_carent_beleg_positions(array $data): array {
		$positions = [cmx_carent_beleg_position_row($data)];
		$billing = (array) ($data['billing'] ?? []);
		$mehrkilometer = cmx_carent_beleg_float((string) ($billing['mehrkilometer'] ?? ''));
		$schadenkosten = cmx_carent_beleg_float((string) ($billing['schadenkosten'] ?? ''));
		if ($mehrkilometer > 0) {
			$positions[] = cmx_carent_beleg_extra_position('Mehrkilometer', $mehrkilometer);
		}
		if ($schadenkosten > 0) {
			$positions[] = cmx_carent_beleg_extra_position('Schadenkosten', $schadenkosten);
		}
		return $positions;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_attach_contract_upload_to_beleg')) {
	function cmx_carent_attach_contract_upload_to_beleg(int $beleg_id, array $contract_pdf): void {
		if ($beleg_id <= 0 || (string) \get_post_type($beleg_id) !== 'belege') {
			return;
		}

		$rel_path = \ltrim((string) ($contract_pdf['rel_path'] ?? ''), '/');
		if ($rel_path !== '' && \strpos($rel_path, 'misbuero/') !== 0 && \strpos($rel_path, 'carent/') === 0) {
			$rel_path = 'misbuero/' . $rel_path;
		} elseif ($rel_path !== '' && \strpos($rel_path, 'misbuero/') !== 0) {
			$rel_path = 'misbuero/carent/' . $rel_path;
		}
		if ($rel_path === '') {
			$source_abs = \wp_normalize_path((string) ($contract_pdf['abs_path'] ?? ''));
			$uploads_root = \trailingslashit(\wp_normalize_path((string) \WP_CONTENT_DIR . '/uploads'));
			if ($source_abs !== '' && \strpos($source_abs, $uploads_root) === 0) {
				$rel_path = \ltrim((string) \substr($source_abs, \strlen($uploads_root)), '/');
			}
		}
		if ($rel_path === '') {
			return;
		}

		$uploads_meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_UPLOADS_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_UPLOADS_META')
			: '_cmx_belege_uploads';
		$uploads = \array_values(\array_filter((array) \get_post_meta($beleg_id, $uploads_meta_key, true), static function ($value): bool {
			return $value !== '' && $value !== null;
		}));
		$uploads[] = $rel_path;
		\update_post_meta($beleg_id, $uploads_meta_key, \array_values(\array_unique(\array_map('strval', $uploads))));
		\update_post_meta($beleg_id, '_cmx_beleg_upload_prefix', \sanitize_title((string) \get_the_title($beleg_id)));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_assign_beleg_invoice_category')) {
	function cmx_carent_assign_beleg_invoice_category(int $beleg_id): void {
		$tax = \function_exists(__NAMESPACE__ . '\\cmx_belege_kategorie_taxonomy')
			? (string) cmx_belege_kategorie_taxonomy()
			: '';
		if ($tax === '') {
			foreach (['belege_kategorien', 'belege_kategorie'] as $candidate) {
				if (\taxonomy_exists($candidate)) {
					$tax = $candidate;
					break;
				}
			}
		}
		if ($tax === '') {
			return;
		}

		$term = \get_term_by('slug', 'rechnung', $tax);
		if (!$term instanceof \WP_Term || \is_wp_error($term)) {
			$term = \get_term_by('name', 'Rechnung', $tax);
		}
		if (!$term instanceof \WP_Term || \is_wp_error($term)) {
			$created = \wp_insert_term('Rechnung', $tax, ['slug' => 'rechnung']);
			if (!\is_wp_error($created) && !empty($created['term_id'])) {
				$term = \get_term((int) $created['term_id'], $tax);
			}
		}
		if ($term instanceof \WP_Term && !\is_wp_error($term)) {
			\wp_set_post_terms($beleg_id, [(int) $term->term_id], $tax, false);
		}
	}
}

\add_action('admin_post_cmx_carent_create_beleg', function (): void {
	$post_id = isset($_GET['carent_id']) ? (int) \wp_unslash($_GET['carent_id']) : 0;
	if ($post_id <= 0 || (string) \get_post_type($post_id) !== 'carent' || !\current_user_can('edit_post', $post_id)) {
		\wp_die(\esc_html__('Vertrag nicht gefunden.', 'cmx-misbuero'), 404);
	}
	if (!isset($_GET['_wpnonce']) || !\wp_verify_nonce((string) \wp_unslash($_GET['_wpnonce']), 'cmx_carent_create_beleg_' . $post_id)) {
		\wp_die(\esc_html__('Ungültige Anfrage.', 'cmx-misbuero'), 403);
	}
	if (!cmx_carent_user_can_create_beleg()) {
		\wp_die(\esc_html__('Du darfst keine Belege erstellen.', 'cmx-misbuero'), 403);
	}
	$status_meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_STATUS_META')
		? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_STATUS_META')
		: '_cmx_carent_status';
	if (\sanitize_key((string) \get_post_meta($post_id, $status_meta_key, true)) !== 'abgeschlossen') {
		\wp_safe_redirect(\add_query_arg('cmx_carent_beleg_error', 'status', (string) \get_edit_post_link($post_id, 'raw')));
		exit;
	}
	$existing_beleg_id = cmx_carent_existing_beleg_id($post_id);
	if ($existing_beleg_id > 0) {
		\wp_safe_redirect((string) \add_query_arg([
			'cmx_carent_existing_beleg' => '1',
			'cmx_carent_source' => $post_id,
		], \admin_url('post.php?post=' . $existing_beleg_id . '&action=edit')));
		exit;
	}

	$data = \function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_collect_data')
		? (array) cmx_carent_vertrag_collect_data($post_id)
		: [];
	$kontakt_id = (int) (($data['contact'] ?? [])['id'] ?? 0);
	$artikel_id = (int) (($data['vehicle'] ?? [])['article_id'] ?? 0);
	if ($data === [] || $kontakt_id <= 0 || $artikel_id <= 0) {
		\wp_safe_redirect(\add_query_arg('cmx_carent_beleg_error', 'missing_data', (string) \get_edit_post_link($post_id, 'raw')));
		exit;
	}

	$contract_pdf = \function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_generate_pdf')
		? cmx_carent_vertrag_generate_pdf($post_id, ['data' => $data, 'sync_dokument' => false])
		: new \WP_Error('missing_pdf_generator', 'Vertrags-PDF konnte nicht erstellt werden.');
	if (\is_wp_error($contract_pdf)) {
		\wp_safe_redirect(\add_query_arg('cmx_carent_beleg_error', 'pdf', (string) \get_edit_post_link($post_id, 'raw')));
		exit;
	}

	$today = (string) \wp_date('Y-m-d');
	$contact = (array) ($data['contact'] ?? []);
	$kontakt_label = \trim((string) ($contact['title'] ?? \get_the_title($kontakt_id)));
	$kontakt_addr = \function_exists(__NAMESPACE__ . '\\cmx_build_kontakt_postanschrift')
		? (string) cmx_build_kontakt_postanschrift($kontakt_id)
		: \trim((string) ($contact['address'] ?? ''));
	$positions = cmx_carent_beleg_positions($data);

	$beleg_id = \wp_insert_post([
		'post_type'   => 'belege',
		'post_status' => 'publish',
		'post_title'  => 'Rechnung ' . $today,
		'post_author' => (int) \get_current_user_id(),
		'meta_input'  => [
			'_cmx_title_auto'          => 1,
			'_cmx_beleg_richtung'      => 'ausgang',
			'_cmx_beleg_status'        => 'offen',
			'_cmx_beleg_rng_datum'     => $today,
			'_cmx_beleg_waehrung'      => 'CHF',
			'_cmx_beleg_kontakt_id'    => $kontakt_id,
			'_cmx_beleg_kontakt_label' => $kontakt_label,
			'_cmx_beleg_kontakt_addr'  => $kontakt_addr,
			'_cmx_beleg_positionen'    => $positions,
			'_cmx_carent_vermietung_id'=> $post_id,
		],
	], true);
	if (\is_wp_error($beleg_id) || (int) $beleg_id <= 0) {
		\wp_safe_redirect(\add_query_arg('cmx_carent_beleg_error', 'create', (string) \get_edit_post_link($post_id, 'raw')));
		exit;
	}
	$beleg_id = (int) $beleg_id;
	cmx_carent_assign_beleg_invoice_category($beleg_id);

	cmx_carent_attach_contract_upload_to_beleg($beleg_id, (array) $contract_pdf);

	if (\function_exists(__NAMESPACE__ . '\\cmxbu_generate_document_on_save')) {
		cmxbu_generate_document_on_save($beleg_id, \get_post($beleg_id), true);
	}

	\wp_safe_redirect((string) \add_query_arg([
		'cmx_carent_beleg_created' => '1',
		'cmx_carent_source' => $post_id,
	], \admin_url('post.php?post=' . $beleg_id . '&action=edit')));
	exit;
});

\add_action('admin_notices', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen) {
		return;
	}
	if ((string) ($screen->post_type ?? '') === 'belege' && !empty($_GET['cmx_carent_beleg_created'])) {
		echo '<div class="notice notice-success is-dismissible"><p>' . \esc_html__('Beleg aus Carent-Vertrag erstellt und Vertrag als Upload hinterlegt.', 'cmx-misbuero') . '</p></div>';
		return;
	}
	if ((string) ($screen->post_type ?? '') === 'belege' && !empty($_GET['cmx_carent_existing_beleg'])) {
		echo '<div class="notice notice-info is-dismissible"><p>' . \esc_html__('Für diesen Carent-Vertrag gibt es bereits einen Beleg. Bestehender Beleg wurde geöffnet.', 'cmx-misbuero') . '</p></div>';
		return;
	}
	if ((string) ($screen->post_type ?? '') !== 'carent' || empty($_GET['cmx_carent_beleg_error'])) {
		return;
	}

	$code = \sanitize_key((string) \wp_unslash($_GET['cmx_carent_beleg_error']));
	$messages = [
		'status' => 'Der Beleg kann erst erstellt werden, wenn der Vertrag abgeschlossen ist.',
		'missing_data' => 'Bitte zuerst Kontakt und Fahrzeug im Vertrag hinterlegen.',
		'pdf' => 'Das Vertrags-PDF konnte nicht erstellt werden.',
		'create' => 'Der Beleg konnte nicht erstellt werden.',
	];
	echo '<div class="notice notice-error is-dismissible"><p>' . \esc_html((string) ($messages[$code] ?? 'Der Beleg konnte nicht erstellt werden.')) . '</p></div>';
});
