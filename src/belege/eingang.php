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
				'currency' => (string) ($currency ?: 'CHF'),
				'seller_name' => \trim((string) $seller_name),
				'seller_address' => \implode("\n", $address_lines),
				'gross_total' => (float) ($grand_total ?: $due_payable ?: 0),
				'tax_total' => (float) ($tax_total ?: 0),
				'net_total' => (float) ($tax_basis ?: $line_total ?: 0),
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
		$uploads = \wp_get_upload_dir();
		$base_dir = \trailingslashit((string) ($uploads['basedir'] ?? '')) . 'misbuero/archiv/' . $year . '/belegeingang/';
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

		$rel = 'misbuero/archiv/' . $year . '/belegeingang/' . $unique;
		return [$path, $rel];
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

	$title_parts = \array_filter([
		(string) ($facturx['document_no'] ?? ''),
		(string) ($facturx['seller_name'] ?? ''),
	]);
	$title = !empty($title_parts) ? \implode(' – ', $title_parts) : 'Belegeingang ' . \wp_date('ymd-His');

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
	\update_post_meta($post_id, '_cmx_belegeingang_exclude_stats', '1');
	\update_post_meta($post_id, CMX_BELEGEINGANG_PDF_META, $pdf_rel);
	\update_post_meta($post_id, '_cmx_belege_uploads', [$pdf_rel]);
	\update_post_meta($post_id, '_cmx_beleg_upload_prefix', \sanitize_title((string) \get_the_title($post_id)));
	\update_post_meta($post_id, '_cmx_belegeingang_source_beleg_id', $source_id);
	\update_post_meta($post_id, '_cmx_belegeingang_facturx', $facturx);
	\update_post_meta($post_id, '_cmx_beleg_kontakt_label', (string) ($facturx['seller_name'] ?? ''));
	\update_post_meta($post_id, '_cmx_beleg_kontakt_addr', (string) ($facturx['seller_address'] ?? ''));
	\update_post_meta($post_id, '_cmx_beleg_rng_datum', (string) ($facturx['document_date'] ?? ''));
	\update_post_meta($post_id, '_cmx_beleg_summe_override', (string) \number_format((float) ($facturx['gross_total'] ?? 0), 2, '.', ''));

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
	$instance = \function_exists(__NAMESPACE__ . '\\cmx_normalize_url_for_href') ? (string) cmx_normalize_url_for_href($instance) : $instance;
	if ($instance === '') {
		\wp_safe_redirect(\add_query_arg('cmx_belegeingang_sent', 'missing_instance', $redirect));
		exit;
	}

	if (!\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_pdf_paths')) {
		\wp_safe_redirect(\add_query_arg('cmx_belegeingang_sent', 'missing_pdf_helper', $redirect));
		exit;
	}

	$post = \get_post($post_id);
	[, $pdf_path] = cmxbu_get_beleg_pdf_paths($post);
	if (!\is_readable($pdf_path)) {
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
			'pdf_base64' => \base64_encode((string) \file_get_contents($pdf_path)),
		]),
	]);

	if (\is_wp_error($response)) {
		\error_log('[CMX Belegeingang] Versand fehlgeschlagen: ' . $response->get_error_message());
		\wp_safe_redirect(\add_query_arg('cmx_belegeingang_sent', 'error', $redirect));
		exit;
	}

	$code = (int) \wp_remote_retrieve_response_code($response);
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
	if (!empty($_GET['cmx_belegeingang'])) {
		$meta_query[] = [
			'key' => CMX_BELEGEINGANG_SOURCE_META,
			'value' => 'rest',
		];
		$query->set('post_status', ['pending']);
		$query->set('meta_query', $meta_query);
		return;
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
	];
	$query->set('meta_query', $meta_query);
}, 5);

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

\add_action('all_admin_notices', function (): void {
	global $typenow;
	if ($typenow !== 'belege' || empty($_GET['cmx_belegeingang'])) {
		return;
	}

	$items = \get_posts([
		'post_type' => 'belege',
		'post_status' => 'pending',
		'posts_per_page' => 50,
		'orderby' => 'date',
		'order' => 'DESC',
		'meta_query' => [
			['key' => CMX_BELEGEINGANG_SOURCE_META, 'value' => 'rest'],
		],
	]);

	echo '<div class="wrap cmx-belegeingang-wrap"><h2>' . \esc_html__('Belegeingang', 'cmx') . '</h2>';
	if (empty($items)) {
		echo '<p>' . \esc_html__('Noch keine eingegangenen Belege.', 'cmx') . '</p></div>';
		return;
	}
	echo '<style>.cmx-belegeingang-card{background:#fff;border:1px solid #ccd0d4;border-radius:4px;margin:12px 0;padding:12px}.cmx-belegeingang-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px}.cmx-belegeingang-preview{width:100%;height:360px;border:1px solid #ccd0d4;background:#f6f7f7}</style>';
	foreach ($items as $item) {
		$post_id = (int) $item->ID;
		$data = (array) \get_post_meta($post_id, '_cmx_belegeingang_facturx', true);
		$status = (string) \get_post_meta($post_id, CMX_BELEGEINGANG_STATUS_META, true);
		$confirm_url = (string) \wp_nonce_url(\add_query_arg(['action' => 'cmx_belegeingang_confirm', 'post_id' => $post_id], \admin_url('admin-post.php')), 'cmx_belegeingang_confirm_' . $post_id);
		echo '<div class="cmx-belegeingang-card" id="post-' . (int) $post_id . '">';
		echo '<div class="cmx-belegeingang-head"><strong>' . \esc_html((string) \get_the_title($post_id)) . '</strong><span>';
		echo '<a class="button" href="' . \esc_url(cmx_belegeingang_pdf_url($post_id)) . '" target="_blank" rel="noopener noreferrer">' . \esc_html__('PDF öffnen', 'cmx') . '</a> ';
		if ($status === 'pending') {
			echo '<a class="button button-primary" href="' . \esc_url($confirm_url) . '">' . \esc_html__('als Lieferantenrechnung anlegen', 'cmx') . '</a>';
		} else {
			echo '<span class="button disabled">' . \esc_html__('übernommen', 'cmx') . '</span>';
		}
		echo '</span></div>';
		echo '<p>' . \esc_html(\trim((string) ($data['seller_name'] ?? ''))) . ' – ' . \esc_html((string) ($data['currency'] ?? 'CHF')) . ' ' . \esc_html(\number_format((float) ($data['gross_total'] ?? 0), 2, '.', "'")) . '</p>';
		echo '<iframe class="cmx-belegeingang-preview" src="' . \esc_url(cmx_belegeingang_pdf_url($post_id)) . '"></iframe>';
		echo '</div>';
	}
	echo '</div>';
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

\add_action('admin_post_cmx_belegeingang_confirm', function (): void {
	$post_id = isset($_GET['post_id']) ? (int) \wp_unslash($_GET['post_id']) : 0;
	if ($post_id <= 0 || !\current_user_can('edit_post', $post_id)) {
		\wp_die('Keine Berechtigung.');
	}
	if (!isset($_GET['_wpnonce']) || !\wp_verify_nonce((string) \wp_unslash($_GET['_wpnonce']), 'cmx_belegeingang_confirm_' . $post_id)) {
		\wp_die('Ungültige Anfrage.');
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

	$tax = \function_exists(__NAMESPACE__ . '\\cmx_belege_kategorie_taxonomy') ? (string) cmx_belege_kategorie_taxonomy() : '';
	if ($tax !== '') {
		$term = \get_term_by('slug', 'rechnung', $tax);
		if ($term && !\is_wp_error($term)) {
			\wp_set_object_terms($post_id, [(int) $term->term_id], $tax, false);
		}
	}

	\wp_safe_redirect(cmx_belegeingang_admin_url($post_id));
	exit;
});
