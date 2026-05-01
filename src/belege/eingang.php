<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

use horstoeko\zugferd\ZugferdDocumentPdfReader;

if (!\defined(__NAMESPACE__ . '\\CMX_BELEGEINGANG_SOURCE_META')) {
	\define(__NAMESPACE__ . '\\CMX_BELEGEINGANG_SOURCE_META', '_cmx_belegeingang_source');
}
if (!\defined(__NAMESPACE__ . '\\CMX_BELEGEINGANG_STATUS_META')) {
	\define(__NAMESPACE__ . '\\CMX_BELEGEINGANG_STATUS_META', '_cmx_belegeingang_status');
}
if (!\defined(__NAMESPACE__ . '\\CMX_BELEGEINGANG_PDF_META')) {
	\define(__NAMESPACE__ . '\\CMX_BELEGEINGANG_PDF_META', '_cmx_belegeingang_pdf_rel');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_admin_url')) {
	function cmx_belegeingang_admin_url(int $post_id = 0): string {
		$url = \add_query_arg(['post_type' => 'belege', 'cmx_belegeingang' => 1], \admin_url('edit.php'));
		return $post_id > 0 ? $url . '#post-' . (int) $post_id : $url;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_instance_url')) {
	function cmx_belegeingang_instance_url(string $raw): string {
		$raw = \trim($raw);
		if ($raw === '') {
			return '';
		}
		if (\preg_match('~^https?://~i', $raw)) {
			return \rtrim($raw, '/');
		}
		$raw = \trim($raw, '/');
		if (\str_contains($raw, '.') || \str_contains($raw, ':')) {
			return 'https://' . $raw;
		}
		$slug = \sanitize_title($raw);
		return $slug !== '' ? 'https://' . $slug . '.misbuero.ch' : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_send_error_key')) {
	function cmx_belegeingang_send_error_key(): string {
		return 'cmx_belegeingang_send_error_' . (int) \get_current_user_id();
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_pdf_path_from_rel')) {
	function cmx_belegeingang_pdf_path_from_rel(string $rel): string {
		$rel = \ltrim(\str_replace('\\', '/', $rel), '/');
		if ($rel === '' || \str_contains($rel, '..')) {
			return '';
		}
		$uploads = \wp_get_upload_dir();
		return \trailingslashit((string) ($uploads['basedir'] ?? '')) . $rel;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_pdf_url')) {
	function cmx_belegeingang_pdf_url(int $post_id): string {
		return (string) \wp_nonce_url(
			\add_query_arg(['action' => 'cmx_belegeingang_pdf', 'post_id' => (int) $post_id], \admin_url('admin-post.php')),
			'cmx_belegeingang_pdf_' . (int) $post_id
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_parse_facturx_pdf')) {
	function cmx_belegeingang_parse_facturx_pdf(string $pdf_path): array {
		if (!\is_readable($pdf_path) || !\class_exists(ZugferdDocumentPdfReader::class)) {
			return [];
		}

		try {
			$xml = ZugferdDocumentPdfReader::getXmlFromFile($pdf_path);
			if (\trim($xml) === '') {
				return [];
			}

			$reader = ZugferdDocumentPdfReader::readAndGuessFromFile($pdf_path);
			$document_no = $document_type = $currency = $tax_currency = $document_name = $language = null;
			$document_date = $period = null;
			$reader->getDocumentInformation($document_no, $document_type, $document_date, $currency, $tax_currency, $document_name, $language, $period);

			$seller_name = $seller_description = null;
			$seller_ids = null;
			$reader->getDocumentSeller($seller_name, $seller_ids, $seller_description);

			$line_one = $line_two = $line_three = $post_code = $city = $country = null;
			$sub_division = null;
			$reader->getDocumentSellerAddress($line_one, $line_two, $line_three, $post_code, $city, $country, $sub_division);

			$grand_total = $due_payable = $line_total = $charge_total = $allowance_total = $tax_basis = $tax_total = $rounding = $prepaid = null;
			$reader->getDocumentSummation($grand_total, $due_payable, $line_total, $charge_total, $allowance_total, $tax_basis, $tax_total, $rounding, $prepaid);

			$due_date = null;
			if ($reader->firstDocumentPaymentTerms()) {
				$payment_description = $direct_debit_mandate_id = null;
				$reader->getDocumentPaymentTerm($payment_description, $due_date, $direct_debit_mandate_id);
			}

			$positions = [];
			if ($reader->firstDocumentPosition()) {
				do {
					$name = $description = $seller_assigned_id = $buyer_assigned_id = $global_id_type = $global_id = null;
					$reader->getDocumentPositionProductDetails($name, $description, $seller_assigned_id, $buyer_assigned_id, $global_id_type, $global_id);

					$quantity = $charge_free_quantity = $package_quantity = null;
					$quantity_unit = $charge_free_unit = $package_unit = null;
					$reader->getDocumentPositionQuantity($quantity, $quantity_unit, $charge_free_quantity, $charge_free_unit, $package_quantity, $package_unit);

					$unit_price = $price_basis_quantity = null;
					$price_basis_unit = null;
					$reader->getDocumentPositionNetPrice($unit_price, $price_basis_quantity, $price_basis_unit);

					$line_amount = $allowance_charge_amount = null;
					$reader->getDocumentPositionLineSummation($line_amount, $allowance_charge_amount);

					$title = \trim((string) ($name ?: $description ?: $seller_assigned_id ?: $buyer_assigned_id));
					if ($title === '' && (float) $line_amount <= 0) {
						continue;
					}

					$positions[] = [
						'artikel_name' => $title !== '' ? $title : 'Factur-X Position',
						'menge' => (string) \number_format((float) ($quantity ?: 1), 2, '.', ''),
						'einheit' => (string) ($quantity_unit ?: $price_basis_unit ?: ''),
						'preis' => (string) \number_format((float) ($unit_price ?: $line_amount ?: 0), 2, '.', ''),
						'rabatt' => '',
						'beschreibung' => \trim((string) $description),
					];
				} while ($reader->nextDocumentPosition());
			}

			$address_lines = \array_values(\array_filter([
				(string) $seller_name,
				(string) $line_one,
				(string) $line_two,
				(string) $line_three,
				\trim((string) $post_code . ' ' . (string) $city),
				(string) $country,
			]));

			return [
				'xml' => $xml,
				'document_no' => (string) $document_no,
				'document_date' => $document_date instanceof \DateTimeInterface ? $document_date->format('Y-m-d') : '',
				'due_date' => $due_date instanceof \DateTimeInterface ? $due_date->format('Y-m-d') : '',
				'currency' => (string) ($currency ?: 'CHF'),
				'seller_name' => \trim((string) $seller_name),
				'seller_address' => \implode("\n", $address_lines),
				'gross_total' => (float) ($grand_total ?: $due_payable ?: 0),
				'tax_total' => (float) ($tax_total ?: 0),
				'net_total' => (float) ($tax_basis ?: $line_total ?: 0),
				'positions' => $positions,
			];
		} catch (\Throwable $e) {
			\error_log('[CMX Belegeingang] Factur-X lesen fehlgeschlagen: ' . $e->getMessage());
			return [];
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_store_pdf')) {
	function cmx_belegeingang_store_pdf(string $pdf_binary, string $filename): array {
		$year = (int) \wp_date('Y');
		if (\function_exists(__NAMESPACE__ . '\\cmx_belege_upload_dir')) {
			[$base_dir] = cmx_belege_upload_dir($year);
			$base_dir = \trailingslashit((string) $base_dir);
		} else {
			$uploads = \wp_get_upload_dir();
			$base_dir = \trailingslashit((string) ($uploads['basedir'] ?? '')) . 'misbuero/archiv/' . $year . '/belege/';
		}
		if (!\wp_mkdir_p($base_dir)) {
			return ['', ''];
		}

		$filename = \sanitize_file_name($filename);
		if ($filename === '' || !\str_ends_with(\strtolower($filename), '.pdf')) {
			$filename = 'beleg-eingang-' . \wp_date('ymd-His') . '.pdf';
		}

		$unique = \wp_unique_filename($base_dir, $filename);
		$path = $base_dir . $unique;
		if (\file_put_contents($path, $pdf_binary) === false) {
			return ['', ''];
		}

		$rel = 'misbuero/archiv/' . $year . '/belege/' . $unique;
		return [$path, $rel];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_set_term_by_slug')) {
	function cmx_belegeingang_set_term_by_slug(int $post_id, string $taxonomy, string $slug): void {
		if ($post_id <= 0 || $taxonomy === '' || $slug === '' || !\taxonomy_exists($taxonomy)) {
			return;
		}

		$term = \get_term_by('slug', $slug, $taxonomy);
		if ($term instanceof \WP_Term) {
			\wp_set_object_terms($post_id, [(int) $term->term_id], $taxonomy, false);
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_collect_transfer_meta')) {
	function cmx_belegeingang_collect_transfer_meta(int $post_id): array {
		$post_id = (int) $post_id;
		if ($post_id <= 0) {
			return [];
		}

		$data = [];
		$leistungsmonat = \trim((string) \get_post_meta($post_id, '_cmx_beleg_leistungsmonat', true));
		if (\preg_match('/^(0[1-9]|1[0-2])$/', $leistungsmonat)) {
			$data['leistungsmonat'] = $leistungsmonat;
		}

		$tax = \function_exists(__NAMESPACE__ . '\\cmx_beleg_zahlungsgrund_tax') ? (string) cmx_beleg_zahlungsgrund_tax() : '';
		if ($tax !== '' && \taxonomy_exists($tax)) {
			$terms = \wp_get_post_terms($post_id, $tax);
			if (!\is_wp_error($terms) && !empty($terms) && $terms[0] instanceof \WP_Term) {
				$data['zahlungsgrund'] = [
					'slug' => (string) $terms[0]->slug,
					'name' => (string) $terms[0]->name,
				];
			}
		}

		return $data;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_apply_transfer_meta')) {
	function cmx_belegeingang_apply_transfer_meta(int $post_id, array $data): void {
		$post_id = (int) $post_id;
		if ($post_id <= 0) {
			return;
		}

		$leistungsmonat = \trim((string) ($data['leistungsmonat'] ?? ''));
		if (\preg_match('/^(0[1-9]|1[0-2])$/', $leistungsmonat)) {
			\update_post_meta($post_id, '_cmx_beleg_leistungsmonat', $leistungsmonat);
		}

		$reason = \is_array($data['zahlungsgrund'] ?? null) ? (array) $data['zahlungsgrund'] : [];
		$slug = \sanitize_title((string) ($reason['slug'] ?? ''));
		$name = \trim(\sanitize_text_field((string) ($reason['name'] ?? '')));
		$tax = \function_exists(__NAMESPACE__ . '\\cmx_beleg_zahlungsgrund_tax') ? (string) cmx_beleg_zahlungsgrund_tax() : '';
		if ($tax === '' || !\taxonomy_exists($tax) || ($slug === '' && $name === '')) {
			return;
		}

		$term = $slug !== '' ? \get_term_by('slug', $slug, $tax) : false;
		if (!$term && $name !== '') {
			$term = \get_term_by('name', $name, $tax);
		}
		if ($term instanceof \WP_Term) {
			\wp_set_post_terms($post_id, [(int) $term->term_id], $tax, false);
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_apply_facturx_meta')) {
	function cmx_belegeingang_apply_facturx_meta(int $post_id, array $facturx): void {
		if ($post_id <= 0) {
			return;
		}

		$seller_name = \trim((string) ($facturx['seller_name'] ?? ''));
		$seller_address = \trim((string) ($facturx['seller_address'] ?? ''));
		$document_date = \trim((string) ($facturx['document_date'] ?? ''));
		$due_date = \trim((string) ($facturx['due_date'] ?? ''));
		$currency = \trim((string) ($facturx['currency'] ?? 'CHF'));
		$gross_total = (float) ($facturx['gross_total'] ?? 0);

		\update_post_meta($post_id, '_cmx_belegeingang_exclude_stats', '1');
		\update_post_meta($post_id, '_cmx_beleg_richtung', 'eingang');
		\update_post_meta($post_id, '_cmx_beleg_status', 'offen');
		\update_post_meta($post_id, '_cmx_beleg_kontakt_label', $seller_name);
		\update_post_meta($post_id, '_cmx_beleg_kontakt_addr', $seller_address);
		\update_post_meta($post_id, '_cmx_beleg_waehrung', $currency !== '' ? $currency : 'CHF');

		if ($document_date !== '') {
			\update_post_meta($post_id, '_cmx_beleg_rng_datum', $document_date);
		}
		if ($due_date !== '') {
			\update_post_meta($post_id, '_cmx_beleg_faelligkeitsdatum', $due_date);
		}
		if ($gross_total > 0) {
			\update_post_meta($post_id, '_cmx_beleg_summe_override', (string) \number_format($gross_total, 2, '.', ''));
		}
		$tax = \function_exists(__NAMESPACE__ . '\\cmx_belege_kategorie_taxonomy') ? (string) cmx_belege_kategorie_taxonomy() : '';
		cmx_belegeingang_set_term_by_slug($post_id, $tax, 'rechnung');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_title_from_facturx')) {
	function cmx_belegeingang_title_from_facturx(array $facturx): string {
		$document_no = \trim((string) ($facturx['document_no'] ?? ''));
		return $document_no !== '' ? $document_no : 'Belegeingang ' . \wp_date('ymd-His');
	}
}

\add_action('rest_api_init', function (): void {
	\register_rest_route('cmx-misbuero/v1', '/beleg/eingang', [
		'methods' => 'POST',
		'permission_callback' => '__return_true',
		'callback' => __NAMESPACE__ . '\\cmx_belegeingang_rest_receive',
	]);
});

function cmx_belegeingang_rest_receive(\WP_REST_Request $request): \WP_REST_Response {
	$params = (array) $request->get_json_params();
	$pdf_base64 = (string) ($params['pdf_base64'] ?? '');
	$source_id = \sanitize_text_field((string) ($params['source_beleg_id'] ?? ''));
	$filename = \sanitize_file_name((string) ($params['filename'] ?? 'beleg.pdf'));
	$transfer_meta = \is_array($params['cmx_meta'] ?? null) ? (array) $params['cmx_meta'] : [];

	$pdf_binary = $pdf_base64 !== '' ? \base64_decode($pdf_base64, true) : false;
	if (!\is_string($pdf_binary) || $pdf_binary === '' || !\str_starts_with($pdf_binary, '%PDF-')) {
		return new \WP_REST_Response(['success' => false, 'message' => 'Keine gültige PDF-Datei empfangen.'], 400);
	}
	if (\strlen($pdf_binary) > 25 * 1024 * 1024) {
		return new \WP_REST_Response(['success' => false, 'message' => 'PDF ist zu gross.'], 413);
	}

	[$pdf_path, $pdf_rel] = cmx_belegeingang_store_pdf($pdf_binary, $filename);
	if ($pdf_path === '') {
		return new \WP_REST_Response(['success' => false, 'message' => 'PDF konnte nicht gespeichert werden.'], 500);
	}

	$facturx = cmx_belegeingang_parse_facturx_pdf($pdf_path);
	if (empty($facturx['xml'])) {
		@\unlink($pdf_path);
		return new \WP_REST_Response(['success' => false, 'message' => 'Das PDF enthält keine lesbare Factur-X/ZUGFeRD-XML-Datei.'], 422);
	}

	$title = cmx_belegeingang_title_from_facturx($facturx);

	$GLOBALS['cmx_skip_beleg_pdf_generation'] = true;
	$post_id = (int) \wp_insert_post([
		'post_type' => 'belege',
		'post_status' => 'pending',
		'post_title' => $title,
	], true);
	unset($GLOBALS['cmx_skip_beleg_pdf_generation']);

	if ($post_id <= 0 || \is_wp_error($post_id)) {
		@\unlink($pdf_path);
		return new \WP_REST_Response(['success' => false, 'message' => 'Belegeingang konnte nicht angelegt werden.'], 500);
	}

	\update_post_meta($post_id, CMX_BELEGEINGANG_SOURCE_META, 'rest');
	\update_post_meta($post_id, CMX_BELEGEINGANG_STATUS_META, 'pending');
	\update_post_meta($post_id, CMX_BELEGEINGANG_PDF_META, $pdf_rel);
	\update_post_meta($post_id, '_cmx_belege_uploads', [$pdf_rel]);
	\update_post_meta($post_id, '_cmx_beleg_upload_prefix', \sanitize_title((string) \get_the_title($post_id)));
	\update_post_meta($post_id, '_cmx_belegeingang_source_beleg_id', $source_id);
	\update_post_meta($post_id, '_cmx_belegeingang_facturx', $facturx);
	\update_post_meta($post_id, '_cmx_belegeingang_cmx_meta', $transfer_meta);
	cmx_belegeingang_apply_facturx_meta($post_id, $facturx);
	cmx_belegeingang_apply_transfer_meta($post_id, $transfer_meta);

	return new \WP_REST_Response([
		'success' => true,
		'post_id' => $post_id,
		'url' => cmx_belegeingang_admin_url($post_id),
	], 201);
}

\add_action('admin_post_cmx_beleg_send_eingang', function (): void {
	$post_id = isset($_GET['post_id']) ? (int) \wp_unslash($_GET['post_id']) : 0;
	if ($post_id <= 0 || !\current_user_can('edit_post', $post_id)) {
		\wp_die('Keine Berechtigung.');
	}
	if (!isset($_GET['_wpnonce']) || !\wp_verify_nonce((string) \wp_unslash($_GET['_wpnonce']), 'cmx_beleg_send_eingang_' . $post_id)) {
		\wp_die('Ungültige Anfrage.');
	}

	$redirect = (string) \get_edit_post_link($post_id, 'raw');
	$kontakt_id = \function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_contact_id')
		? (int) cmxbu_get_beleg_contact_id($post_id)
		: (int) \get_post_meta($post_id, '_cmx_beleg_kontakt_id', true);
	$muh_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_MUH') ? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_MUH') : '_cmx_kontakte_muh';
	$instance = $kontakt_id > 0 ? \trim((string) \get_post_meta($kontakt_id, $muh_key, true)) : '';
	$instance = cmx_belegeingang_instance_url($instance);
	if ($instance === '') {
		\set_transient(cmx_belegeingang_send_error_key(), 'Beim Kontakt ist keine Muh-Instanz hinterlegt.', 60);
		\wp_safe_redirect(\add_query_arg('cmx_belegeingang_sent', 'missing_instance', $redirect));
		exit;
	}

	if (!\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_pdf_paths')) {
		\set_transient(cmx_belegeingang_send_error_key(), 'PDF-Pfad konnte nicht ermittelt werden.', 60);
		\wp_safe_redirect(\add_query_arg('cmx_belegeingang_sent', 'missing_pdf_helper', $redirect));
		exit;
	}

	$post = \get_post($post_id);
	[, $pdf_path] = cmxbu_get_beleg_pdf_paths($post);
	if (!\is_readable($pdf_path)) {
		\set_transient(cmx_belegeingang_send_error_key(), 'Für diesen Beleg wurde kein lesbares PDF gefunden: ' . $pdf_path, 60);
		\wp_safe_redirect(\add_query_arg('cmx_belegeingang_sent', 'missing_pdf', $redirect));
		exit;
	}

	$endpoint = \trailingslashit($instance) . 'wp-json/cmx-misbuero/v1/beleg/eingang';
	$response = \wp_remote_post($endpoint, [
		'timeout' => 30,
		'headers' => ['Content-Type' => 'application/json'],
		'body' => \wp_json_encode([
			'source_url' => \home_url('/'),
			'source_beleg_id' => (string) \get_the_title($post_id),
			'filename' => \basename($pdf_path),
			'cmx_meta' => cmx_belegeingang_collect_transfer_meta($post_id),
			'pdf_base64' => \base64_encode((string) \file_get_contents($pdf_path)),
		]),
	]);

	if (\is_wp_error($response)) {
		$error_message = 'Versand an ' . $endpoint . ' fehlgeschlagen: ' . $response->get_error_message();
		\set_transient(cmx_belegeingang_send_error_key(), $error_message, 60);
		\error_log('[CMX Belegeingang] ' . $error_message);
		\wp_safe_redirect(\add_query_arg('cmx_belegeingang_sent', 'error', $redirect));
		exit;
	}

	$code = (int) \wp_remote_retrieve_response_code($response);
	if ($code < 200 || $code >= 300) {
		$body = \trim((string) \wp_remote_retrieve_body($response));
		\set_transient(cmx_belegeingang_send_error_key(), 'Zielinstanz hat abgelehnt (' . $code . '): ' . \wp_strip_all_tags($body), 60);
	}
	\wp_safe_redirect(\add_query_arg('cmx_belegeingang_sent', $code >= 200 && $code < 300 ? 'ok' : 'rejected', $redirect));
	exit;
});

\add_action('admin_notices', function (): void {
	if (empty($_GET['cmx_belegeingang_sent'])) {
		return;
	}
	$status = \sanitize_key((string) \wp_unslash($_GET['cmx_belegeingang_sent']));
	$messages = [
		'ok' => ['success', 'Beleg wurde an die Zielinstanz gesendet.'],
		'rejected' => ['error', 'Die Zielinstanz hat den Beleg abgelehnt.'],
		'missing_instance' => ['error', 'Beim Kontakt ist keine Muh-Instanz hinterlegt.'],
		'missing_pdf' => ['error', 'Für diesen Beleg wurde kein PDF gefunden.'],
		'missing_pdf_helper' => ['error', 'PDF-Pfad konnte nicht ermittelt werden.'],
		'error' => ['error', 'Beleg konnte nicht an die Zielinstanz gesendet werden.'],
	];
	if (empty($messages[$status])) {
		return;
	}
	[$type, $text] = $messages[$status];
	$detail = (string) \get_transient(cmx_belegeingang_send_error_key());
	if ($detail !== '') {
		\delete_transient(cmx_belegeingang_send_error_key());
		$text .= ' ' . $detail;
	}
	echo '<div class="notice notice-' . \esc_attr($type) . ' is-dismissible"><p>' . \esc_html($text) . '</p></div>';
});

\add_action('pre_get_posts', function (\WP_Query $query): void {
	if (!\is_admin() || !$query->is_main_query()) {
		return;
	}
	$post_type = $query->get('post_type');
	$post_type = \is_array($post_type) ? (string) \reset($post_type) : (string) $post_type;
	if ($post_type !== 'belege') {
		return;
	}

	$meta_query = (array) $query->get('meta_query');
	$requested_status = isset($_GET['post_status']) ? \sanitize_key((string) \wp_unslash($_GET['post_status'])) : '';
	if (!empty($_GET['cmx_belegeingang']) || $requested_status === 'pending') {
		$meta_query[] = [
			'key' => CMX_BELEGEINGANG_SOURCE_META,
			'value' => 'rest',
		];
		$query->set('post_status', ['pending']);
		$query->set('meta_query', $meta_query);
		return;
	}

	if ($requested_status !== 'trash') {
		$query->set('post_status', ['publish']);
	}

	$meta_query[] = [
		'relation' => 'OR',
		[
			'key' => CMX_BELEGEINGANG_SOURCE_META,
			'compare' => 'NOT EXISTS',
		],
		[
			'key' => CMX_BELEGEINGANG_SOURCE_META,
			'value' => 'rest',
			'compare' => '!=',
		],
		[
			'relation' => 'AND',
			[
				'key' => CMX_BELEGEINGANG_SOURCE_META,
				'value' => 'rest',
			],
			[
				'key' => CMX_BELEGEINGANG_STATUS_META,
				'value' => 'pending',
				'compare' => '!=',
			],
		],
	];
	$query->set('meta_query', $meta_query);
}, 5);

\add_filter('display_post_states', function (array $post_states, \WP_Post $post): array {
	if ((string) $post->post_type !== 'belege') {
		return $post_states;
	}

	unset($post_states['pending']);
	return $post_states;
}, 20, 2);

\add_action('admin_notices', function (): void {
	if (!\current_user_can('edit_posts')) {
		return;
	}

	$pending = \get_posts([
		'post_type' => 'belege',
		'post_status' => 'pending',
		'posts_per_page' => 1,
		'fields' => 'ids',
		'meta_query' => [
			['key' => CMX_BELEGEINGANG_SOURCE_META, 'value' => 'rest'],
			['key' => CMX_BELEGEINGANG_STATUS_META, 'value' => 'pending'],
		],
	]);
	if (empty($pending)) {
		return;
	}

	$post_id = (int) $pending[0];
	$data = (array) \get_post_meta($post_id, '_cmx_belegeingang_facturx', true);
	$seller = \trim((string) ($data['seller_name'] ?? 'Unbekannt'));
	$total = \number_format((float) ($data['gross_total'] ?? 0), 2, '.', "'");
	$currency = \trim((string) ($data['currency'] ?? 'CHF'));
	$document_no = \trim((string) ($data['document_no'] ?? ''));
	$link_label = $document_no !== '' ? $document_no : (string) \get_the_title($post_id);
	$link = '<a href="' . \esc_url(cmx_belegeingang_admin_url($post_id)) . '">' . \esc_html($link_label) . '</a>';
	echo '<div class="notice notice-info"><p>Neuer Beleg im Eingang: ' . $link . ' von ' . \esc_html($seller) . ' – ' . \esc_html($currency . ' ' . $total) . '</p></div>';
});

\add_action('admin_head-edit.php', function (): void {
	if (empty($_GET['cmx_belegeingang']) || !isset($_GET['post_type']) || (string) $_GET['post_type'] !== 'belege') {
		return;
	}

	echo '<style>
		.wp-list-table th.manage-column.column-title,
		.wp-list-table td.title.column-title {
			width: 17ch !important;
			min-width: 17ch !important;
			max-width: 17ch !important;
		}
		.wp-list-table td.column-title strong,
		.wp-list-table td.column-title .row-title {
			max-width: 17ch !important;
			text-overflow: clip !important;
		}
	</style>';
});

\add_action('admin_init', function (): void {
	if (!\is_admin() || !\current_user_can('edit_posts')) {
		return;
	}

	$post_ids = \get_posts([
		'post_type' => 'belege',
		'post_status' => 'pending',
		'posts_per_page' => 50,
		'fields' => 'ids',
		'no_found_rows' => true,
		'meta_query' => [
			['key' => CMX_BELEGEINGANG_SOURCE_META, 'value' => 'rest'],
			['key' => CMX_BELEGEINGANG_STATUS_META, 'value' => 'pending'],
		],
	]);

	foreach ($post_ids as $post_id) {
		$post_id = (int) $post_id;
		$facturx = (array) \get_post_meta($post_id, '_cmx_belegeingang_facturx', true);
		if (empty($facturx)) {
			continue;
		}

		if ((string) \get_post_meta($post_id, '_cmx_beleg_richtung', true) === '') {
			cmx_belegeingang_apply_facturx_meta($post_id, $facturx);
		}
		$transfer_meta = (array) \get_post_meta($post_id, '_cmx_belegeingang_cmx_meta', true);
		if (!empty($transfer_meta)) {
			cmx_belegeingang_apply_transfer_meta($post_id, $transfer_meta);
		}

		$title = cmx_belegeingang_title_from_facturx($facturx);
		if ($title !== '' && $title !== (string) \get_the_title($post_id)) {
			\wp_update_post([
				'ID' => $post_id,
				'post_title' => $title,
			]);
		}
	}
});

\add_action('admin_post_cmx_belegeingang_pdf', function (): void {
	$post_id = isset($_GET['post_id']) ? (int) \wp_unslash($_GET['post_id']) : 0;
	if ($post_id <= 0 || !\current_user_can('edit_post', $post_id)) {
		\wp_die('Keine Berechtigung.');
	}
	if (!isset($_GET['_wpnonce']) || !\wp_verify_nonce((string) \wp_unslash($_GET['_wpnonce']), 'cmx_belegeingang_pdf_' . $post_id)) {
		\wp_die('Ungültige Anfrage.');
	}
	$rel = (string) \get_post_meta($post_id, CMX_BELEGEINGANG_PDF_META, true);
	$path = cmx_belegeingang_pdf_path_from_rel($rel);
	if ($path === '' || !\is_readable($path)) {
		\wp_die('PDF nicht gefunden.');
	}
	\nocache_headers();
	\header('Content-Type: application/pdf');
	\header('Content-Disposition: inline; filename="' . \basename($path) . '"');
	\header('Content-Length: ' . (string) \filesize($path));
	\readfile($path);
	exit;
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_import_as_supplier_invoice')) {
	function cmx_belegeingang_import_as_supplier_invoice(int $post_id): bool {
		$post = \get_post($post_id);
		if (!$post instanceof \WP_Post || (string) $post->post_type !== 'belege') {
			return false;
		}
		if ((string) \get_post_meta($post_id, CMX_BELEGEINGANG_SOURCE_META, true) !== 'rest') {
			return false;
		}

		$data = (array) \get_post_meta($post_id, '_cmx_belegeingang_facturx', true);
		$seller = \trim((string) ($data['seller_name'] ?? ''));
		if ($seller !== '' && \function_exists(__NAMESPACE__ . '\\cmx_beleg_find_existing_kontakt_id_from_label')) {
			$kontakt_id = (int) cmx_beleg_find_existing_kontakt_id_from_label($seller);
			if ($kontakt_id <= 0 && \function_exists(__NAMESPACE__ . '\\cmx_beleg_create_kontakt_from_label')) {
				$kontakt_id = (int) cmx_beleg_create_kontakt_from_label($seller);
			}
			if ($kontakt_id > 0) {
				\update_post_meta($post_id, '_cmx_beleg_kontakt_id', $kontakt_id);
			}
		}

		\update_post_meta($post_id, '_cmx_beleg_richtung', 'eingang');
		\update_post_meta($post_id, '_cmx_beleg_kontakt_label', $seller);
		\update_post_meta($post_id, '_cmx_beleg_kontakt_addr', (string) ($data['seller_address'] ?? ''));
		\update_post_meta($post_id, CMX_BELEGEINGANG_STATUS_META, 'imported');
		$result = \wp_update_post([
			'ID' => $post_id,
			'post_status' => 'publish',
		], true);
		if (\is_wp_error($result)) {
			\error_log('[CMX Belegeingang] Uebernahme fehlgeschlagen: ' . $result->get_error_message());
			return false;
		}

		$tax = \function_exists(__NAMESPACE__ . '\\cmx_belege_kategorie_taxonomy') ? (string) cmx_belege_kategorie_taxonomy() : '';
		if ($tax !== '') {
			$term = \get_term_by('slug', 'rechnung', $tax);
			if ($term && !\is_wp_error($term)) {
				\wp_set_object_terms($post_id, [(int) $term->term_id], $tax, false);
			}
		}

		return true;
	}
}

\add_action('admin_post_cmx_belegeingang_confirm', function (): void {
	$post_id = isset($_GET['post_id']) ? (int) \wp_unslash($_GET['post_id']) : 0;
	if ($post_id <= 0 || !\current_user_can('edit_post', $post_id)) {
		\wp_die('Keine Berechtigung.');
	}
	if (!isset($_GET['_wpnonce']) || !\wp_verify_nonce((string) \wp_unslash($_GET['_wpnonce']), 'cmx_belegeingang_confirm_' . $post_id)) {
		\wp_die('Ungültige Anfrage.');
	}

	if (!cmx_belegeingang_import_as_supplier_invoice($post_id)) {
		\wp_die('Beleg konnte nicht übernommen werden.');
	}

	$redirect_to = isset($_GET['redirect_to']) ? (string) \wp_unslash($_GET['redirect_to']) : '';
	$redirect_to = $redirect_to !== '' ? \rawurldecode($redirect_to) : '';
	$redirect_to = $redirect_to !== '' ? $redirect_to : cmx_belegeingang_admin_url($post_id);
	\wp_safe_redirect($redirect_to);
	exit;
});
