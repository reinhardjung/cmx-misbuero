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
if (!\defined(__NAMESPACE__ . '\\CMX_BELEGEINGANG_DISMISSED_META')) {
	\define(__NAMESPACE__ . '\\CMX_BELEGEINGANG_DISMISSED_META', '_cmx_belegeingang_dismissed_notices');
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

			$seller_email = '';
			$seller_phone = '';
			if ($reader->firstDocumentSellerContact()) {
				$seller_contact_name = $seller_contact_department = $seller_contact_fax = null;
				$seller_contact_phone = $seller_contact_email = null;
				$reader->getDocumentSellerContact($seller_contact_name, $seller_contact_department, $seller_contact_phone, $seller_contact_fax, $seller_contact_email);
				$seller_email = \sanitize_email((string) $seller_contact_email);
				$seller_phone = \trim((string) $seller_contact_phone);
			}
			$seller_communication_scheme = $seller_communication_uri = null;
			$reader->getDocumentSellerCommunication($seller_communication_scheme, $seller_communication_uri);
			$seller_communication_email = \sanitize_email((string) $seller_communication_uri);
			if ($seller_email === '' && \is_email($seller_communication_email)) {
				$seller_email = $seller_communication_email;
			}
			$seller_emails = cmx_belegeingang_normalize_emails([$seller_email, $seller_communication_email]);

			$buyer_name = $buyer_description = null;
			$buyer_ids = null;
			$reader->getDocumentBuyer($buyer_name, $buyer_ids, $buyer_description);

			$buyer_line_one = $buyer_line_two = $buyer_line_three = $buyer_post_code = $buyer_city = $buyer_country = null;
			$buyer_sub_division = null;
			$reader->getDocumentBuyerAddress($buyer_line_one, $buyer_line_two, $buyer_line_three, $buyer_post_code, $buyer_city, $buyer_country, $buyer_sub_division);

			$buyer_email = '';
			$buyer_phone = '';
			if ($reader->firstDocumentBuyerContact()) {
				$buyer_contact_name = $buyer_contact_department = $buyer_contact_fax = null;
				$buyer_contact_phone = $buyer_contact_email = null;
				$reader->getDocumentBuyerContact($buyer_contact_name, $buyer_contact_department, $buyer_contact_phone, $buyer_contact_fax, $buyer_contact_email);
				$buyer_email = \sanitize_email((string) $buyer_contact_email);
				$buyer_phone = \trim((string) $buyer_contact_phone);
			}
			$buyer_communication_scheme = $buyer_communication_uri = null;
			$reader->getDocumentBuyerCommunication($buyer_communication_scheme, $buyer_communication_uri);
			$buyer_communication_email = \sanitize_email((string) $buyer_communication_uri);
			if ($buyer_email === '' && \is_email($buyer_communication_email)) {
				$buyer_email = $buyer_communication_email;
			}
			$buyer_emails = cmx_belegeingang_normalize_emails([$buyer_email, $buyer_communication_email]);

			$notes = [];
			$reader->getDocumentNotes($notes);
			$contact_email_note = cmx_belegeingang_contact_email_note_data((array) $notes);
			$seller_emails = cmx_belegeingang_normalize_emails(\array_merge($seller_emails, (array) ($contact_email_note['seller_emails'] ?? [])));
			$buyer_emails = cmx_belegeingang_normalize_emails(\array_merge($buyer_emails, (array) ($contact_email_note['buyer_emails'] ?? [])));
			$seller_email = (string) ($seller_emails[0] ?? '');
			$buyer_email = (string) ($buyer_emails[0] ?? '');

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
			$buyer_address_lines = \array_values(\array_filter([
				(string) $buyer_name,
				(string) $buyer_line_one,
				(string) $buyer_line_two,
				(string) $buyer_line_three,
				\trim((string) $buyer_post_code . ' ' . (string) $buyer_city),
				(string) $buyer_country,
			]));

			return [
				'xml' => $xml,
				'document_no' => (string) $document_no,
				'document_date' => $document_date instanceof \DateTimeInterface ? $document_date->format('Y-m-d') : '',
				'due_date' => $due_date instanceof \DateTimeInterface ? $due_date->format('Y-m-d') : '',
				'currency' => (string) ($currency ?: 'CHF'),
				'seller_name' => \trim((string) $seller_name),
				'seller_email' => \is_email($seller_email) ? $seller_email : '',
				'seller_emails' => $seller_emails,
				'seller_phone' => $seller_phone,
				'seller_address' => \implode("\n", $address_lines),
				'buyer_name' => \trim((string) $buyer_name),
				'buyer_email' => \is_email($buyer_email) ? $buyer_email : '',
				'buyer_emails' => $buyer_emails,
				'buyer_phone' => $buyer_phone,
				'buyer_address' => \implode("\n", $buyer_address_lines),
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_track_source_beleg')) {
	function cmx_belegeingang_track_source_beleg(int $post_id, string $event): void {
		$post_id = (int) $post_id;
		$event = \sanitize_key($event);
		if ($post_id <= 0 || $event === '') {
			return;
		}

		$source_url = cmx_belegeingang_instance_url((string) \get_post_meta($post_id, '_cmx_belegeingang_source_url', true));
		$token = \sanitize_text_field((string) \get_post_meta($post_id, '_cmx_belegeingang_source_beleg_token', true));
		$source_beleg_id = \sanitize_text_field((string) \get_post_meta($post_id, '_cmx_belegeingang_source_beleg_id', true));
		$source_beleg_post_id = \sanitize_text_field((string) \get_post_meta($post_id, '_cmx_belegeingang_source_beleg_post_id', true));
		if ($source_url === '' || ($token === '' && $source_beleg_post_id === '')) {
			\error_log('[CMX Belegeingang] Source-Tracking uebersprungen: source_url und Beleg-Referenz unvollstaendig fuer Zielbeleg ' . $post_id . ' / Event ' . $event);
			return;
		}

		$endpoint = \trailingslashit($source_url) . 'wp-json/cmx-misbuero/v1/beleg/source-track';
		$response = \wp_remote_post($endpoint, [
			'timeout' => 4,
			'headers' => ['Content-Type' => 'application/json'],
			'body' => \wp_json_encode([
				'token' => $token,
				'event' => $event,
				'source_beleg_id' => $source_beleg_id,
				'source_beleg_post_id' => $source_beleg_post_id,
				'source_url' => $source_url,
				'target_url' => \home_url('/'),
				'target_beleg_id' => (string) $post_id,
			]),
		]);
		if (\is_wp_error($response)) {
			\error_log('[CMX Belegeingang] Source-Tracking fehlgeschlagen fuer Zielbeleg ' . $post_id . ' / Event ' . $event . ': ' . $response->get_error_message());
			return;
		}
		$code = (int) \wp_remote_retrieve_response_code($response);
		if ($code < 200 || $code >= 300) {
			\error_log('[CMX Belegeingang] Source-Tracking abgelehnt fuer Zielbeleg ' . $post_id . ' / Event ' . $event . ' HTTP ' . $code . ': ' . \wp_strip_all_tags((string) \wp_remote_retrieve_body($response)));
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_normalize_emails')) {
	function cmx_belegeingang_normalize_emails(array $emails): array {
		$out = [];
		foreach ($emails as $email) {
			$email = \sanitize_email((string) $email);
			if (\is_email($email)) {
				$out[\strtolower($email)] = $email;
			}
		}
		return \array_values($out);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_contact_email_note_data')) {
	function cmx_belegeingang_contact_email_note_data(array $notes): array {
		foreach ($notes as $note) {
			$content = \trim((string) (\is_array($note) ? ($note['content'] ?? '') : ''));
			if (!\str_starts_with($content, 'CMX_CONTACT_EMAILS:')) {
				continue;
			}
			$json = \trim(\substr($content, \strlen('CMX_CONTACT_EMAILS:')));
			$data = \json_decode($json, true);
			if (!\is_array($data)) {
				continue;
			}
			return [
				'seller_emails' => cmx_belegeingang_normalize_emails((array) ($data['seller_emails'] ?? [])),
				'buyer_emails' => cmx_belegeingang_normalize_emails((array) ($data['buyer_emails'] ?? [])),
			];
		}
		return ['seller_emails' => [], 'buyer_emails' => []];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_find_kontakt_id_by_email')) {
	function cmx_belegeingang_find_kontakt_id_by_email(string $email): int {
		$email = \sanitize_email($email);
		if (!\is_email($email)) {
			return 0;
		}

		$cpt = \function_exists(__NAMESPACE__ . '\\cmx_kontakte_cpt') ? (string) cmx_kontakte_cpt() : 'kontakte';
		if (!\post_type_exists($cpt)) {
			return 0;
		}

		$meta_query = ['relation' => 'OR'];
		foreach (['_cmx_email_1', '_cmx_email_2', '_cmx_email_3', 'cmx_email_1', 'email_1', 'e_mail_1', 'kontakt_email', 'email', 'e_mail', 'mail'] as $key) {
			$meta_query[] = [
				'key' => $key,
				'value' => $email,
				'compare' => '=',
			];
		}
		for ($slot = 1; $slot <= 10; $slot++) {
			$meta_query[] = [
				'key' => '_cmx_kommunikation_' . $slot . '_email',
				'value' => $email,
				'compare' => '=',
			];
		}
		foreach (['_cmx_kommunikation', 'cmx_kommunikation', 'kommunikation', 'cmx_kommunikation_data'] as $key) {
			$meta_query[] = [
				'key' => $key,
				'value' => $email,
				'compare' => 'LIKE',
			];
		}

		$ids = \get_posts([
			'post_type' => $cpt,
			'post_status' => 'any',
			'posts_per_page' => 20,
			'fields' => 'ids',
			'no_found_rows' => true,
			'suppress_filters' => true,
			'meta_query' => $meta_query,
		]);

		foreach ((array) $ids as $id) {
			$id = (int) $id;
			if ($id <= 0) {
				continue;
			}
			$emails = \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_collect_emails')
				? (array) cmx_kommunikation_collect_emails($id)
				: [];
			foreach (\array_merge($emails, [(string) \get_post_meta($id, '_cmx_email_1', true)]) as $candidate) {
				if (\strtolower(\sanitize_email((string) $candidate)) === \strtolower($email)) {
					return $id;
				}
			}
		}

		return 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_find_kontakt_id_by_emails')) {
	function cmx_belegeingang_find_kontakt_id_by_emails(array $emails): int {
		foreach (cmx_belegeingang_normalize_emails($emails) as $email) {
			$id = cmx_belegeingang_find_kontakt_id_by_email($email);
			if ($id > 0) {
				return $id;
			}
		}
		return 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_merge_contact_emails')) {
	function cmx_belegeingang_merge_contact_emails(int $kontakt_id, array $emails): void {
		$kontakt_id = (int) $kontakt_id;
		$emails = cmx_belegeingang_normalize_emails($emails);
		if ($kontakt_id <= 0 || $emails === []) {
			return;
		}

		$existing = \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_collect_emails')
			? (array) cmx_kommunikation_collect_emails($kontakt_id)
			: [];
		$merged = cmx_belegeingang_normalize_emails(\array_merge($existing, $emails));
		if ($merged === []) {
			return;
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_read_contacts') && \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_persist_contacts')) {
			$rows = \array_values(\array_filter((array) cmx_kommunikation_read_contacts($kontakt_id), static fn($row): bool => \is_array($row)));
			$row_emails = [];
			foreach ($rows as $row) {
				$email = \sanitize_email((string) ($row['email'] ?? ''));
				if (\is_email($email)) {
					$row_emails[\strtolower($email)] = true;
				}
			}
			foreach ($merged as $email) {
				if (isset($row_emails[\strtolower($email)])) {
					continue;
				}
				$rows[] = [
					'vorname' => '',
					'nachname' => '',
					'telefon_label' => '',
					'telefon' => '',
					'email_label' => $rows === [] ? 'E-Mail' : 'Weitere E-Mail',
					'email' => $email,
					'geburtsdatum' => '',
					'anrede' => '',
					'duzis' => '0',
				];
			}
			cmx_kommunikation_persist_contacts($kontakt_id, $rows);
		}

		if (\sanitize_email((string) \get_post_meta($kontakt_id, '_cmx_email_1', true)) === '') {
			\update_post_meta($kontakt_id, '_cmx_email_1', (string) ($merged[0] ?? ''));
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_belegeingang_contact_id_for_party')) {
	function cmx_belegeingang_contact_id_for_party(string $name, string $address, array $emails, string $phone = ''): int {
		$name = \trim($name);
		$emails = cmx_belegeingang_normalize_emails($emails);
		if ($name === '' && $emails !== []) {
			$name = (string) $emails[0];
		}
		$kontakt_id = cmx_belegeingang_find_kontakt_id_by_emails($emails);

		if ($kontakt_id <= 0 && $name !== '' && \function_exists(__NAMESPACE__ . '\\cmx_beleg_find_existing_kontakt_id_from_label')) {
			$kontakt_id = (int) cmx_beleg_find_existing_kontakt_id_from_label($name);
		}
		if ($kontakt_id <= 0 && $name !== '' && \function_exists(__NAMESPACE__ . '\\cmx_beleg_create_kontakt_from_label')) {
			$kontakt_id = (int) cmx_beleg_create_kontakt_from_label($name);
		}
		if ($kontakt_id <= 0) {
			return 0;
		}

		cmx_belegeingang_merge_contact_emails($kontakt_id, $emails);
		if (\trim((string) \get_post_meta($kontakt_id, '_cmx_telefon_1', true)) === '' && \trim($phone) !== '') {
			\update_post_meta($kontakt_id, '_cmx_telefon_1', \sanitize_text_field($phone));
		}
		if (\trim((string) \get_post_meta($kontakt_id, '_cmx_rechnung_strasse', true)) === '' && \trim($address) !== '') {
			\update_post_meta($kontakt_id, '_cmx_belegeingang_import_addr', $address);
		}
		return $kontakt_id;
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
	$source_post_id = \sanitize_text_field((string) ($params['source_beleg_post_id'] ?? ''));
	$source_url = cmx_belegeingang_instance_url((string) ($params['source_url'] ?? ''));
	$source_token = \sanitize_text_field((string) ($params['source_beleg_token'] ?? ''));
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
	\update_post_meta($post_id, '_cmx_belegeingang_source_beleg_post_id', $source_post_id);
	\update_post_meta($post_id, '_cmx_belegeingang_source_url', $source_url);
	\update_post_meta($post_id, '_cmx_belegeingang_source_beleg_token', $source_token);
	\update_post_meta($post_id, '_cmx_belegeingang_facturx', $facturx);
	\update_post_meta($post_id, '_cmx_belegeingang_cmx_meta', $transfer_meta);
	cmx_belegeingang_apply_facturx_meta($post_id, $facturx);
	cmx_belegeingang_apply_transfer_meta($post_id, $transfer_meta);
	cmx_belegeingang_track_source_beleg($post_id, 'target_received');

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
	$source_token = \function_exists(__NAMESPACE__ . '\\cmxbu_get_stable_token') ? (string) cmxbu_get_stable_token($post_id) : '';
	$response = \wp_remote_post($endpoint, [
		'timeout' => 30,
		'headers' => ['Content-Type' => 'application/json'],
		'body' => \wp_json_encode([
			'source_url' => \home_url('/'),
			'source_beleg_id' => (string) \get_the_title($post_id),
			'source_beleg_post_id' => (string) $post_id,
			'source_beleg_token' => $source_token,
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
	} else {
		$host = (string) \wp_parse_url($instance, PHP_URL_HOST);
		$host = $host !== '' ? $host : (string) \preg_replace('~^https?://~i', '', $instance);
		\set_transient(cmx_belegeingang_send_error_key(), $host, 60);
	}
	\wp_safe_redirect(\add_query_arg('cmx_belegeingang_sent', $code >= 200 && $code < 300 ? 'ok' : 'rejected', $redirect));
	exit;
});

\add_action('all_admin_notices', function (): void {
	if (empty($_GET['cmx_belegeingang_sent'])) {
		return;
	}
	$status = \sanitize_key((string) \wp_unslash($_GET['cmx_belegeingang_sent']));
	$messages = [
		'ok' => ['success', 'Beleg wurde an {instanz}.misbuero.ch gesendet.'],
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
		if ($status === 'ok') {
			$host = \sanitize_text_field($detail);
			if (!\str_contains($host, '.')) {
				$host .= '.misbuero.ch';
			}
			$text = \str_replace('{instanz}.misbuero.ch', $host, $text);
		} else {
			$text .= ' ' . $detail;
		}
	} elseif ($status === 'ok') {
		$text = \str_replace('{instanz}.misbuero.ch', 'die Zielinstanz', $text);
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

\add_action('pre_get_posts', function (\WP_Query $query): void {
	if (!\is_admin() || !$query->is_main_query()) {
		return;
	}
	$post_type = $query->get('post_type');
	$post_type = \is_array($post_type) ? (string) \reset($post_type) : (string) $post_type;
	if ($post_type !== 'belege') {
		return;
	}

	$requested_status = isset($_GET['post_status']) ? \sanitize_key((string) \wp_unslash($_GET['post_status'])) : '';
	if (empty($_GET['cmx_belegeingang']) && $requested_status !== 'pending') {
		return;
	}

	foreach ([
		'cmx_kontakt_id',
		'cmx_proj_id',
		'cmx_bezahlfilter',
		'cmx_richtungfilter',
		'cmx_zahlungsartfilter',
		'cmx_zahlungsgrundfilter',
		'cmx_leistungsmonat',
		'cmx_zeitraumfilter',
		'cmx_woo',
	] as $query_var) {
		$query->set($query_var, '');
	}

	$query->set('post_status', ['pending']);
	$query->set('tax_query', []);
	$query->set('meta_query', [
		[
			'key' => CMX_BELEGEINGANG_SOURCE_META,
			'value' => 'rest',
		],
		[
			'key' => CMX_BELEGEINGANG_STATUS_META,
			'value' => 'pending',
		],
	]);
}, 999);

\add_action('admin_head-edit.php', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) $screen->id !== 'edit-belege') {
		return;
	}

	$requested_status = isset($_GET['post_status']) ? \sanitize_key((string) \wp_unslash($_GET['post_status'])) : '';
	if (empty($_GET['cmx_belegeingang']) && $requested_status !== 'pending') {
		return;
	}

	echo '<style>
		#posts-filter .tablenav.top .alignleft.actions select:not([name="action"]),
		#posts-filter .tablenav.top .alignleft.actions #post-query-submit,
		#posts-filter .tablenav.top .alignleft.actions .cmx-belege-woo-filter,
		#posts-filter .tablenav.top .alignleft.actions #cmx-projekt-filter-wrap {
			display: none !important;
		}
	</style>';
});

\add_filter('display_post_states', function (array $post_states, \WP_Post $post): array {
	if ((string) $post->post_type !== 'belege') {
		return $post_states;
	}

	unset($post_states['pending']);
	return $post_states;
}, 20, 2);

\add_action('all_admin_notices', function (): void {
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
	$user_id = (int) \get_current_user_id();
	$dismissed = $user_id > 0 ? (array) \get_user_meta($user_id, CMX_BELEGEINGANG_DISMISSED_META, true) : [];
	$dismissed = \array_map('intval', $dismissed);
	if (\in_array($post_id, $dismissed, true)) {
		return;
	}

	$data = (array) \get_post_meta($post_id, '_cmx_belegeingang_facturx', true);
	$seller = \trim((string) ($data['seller_name'] ?? 'Unbekannt'));
	$total = \number_format((float) ($data['gross_total'] ?? 0), 2, '.', "'");
	$currency = \trim((string) ($data['currency'] ?? 'CHF'));
	$document_no = \trim((string) ($data['document_no'] ?? ''));
	$link_label = $document_no !== '' ? $document_no : (string) \get_the_title($post_id);
	$link = '<a href="' . \esc_url(cmx_belegeingang_admin_url($post_id)) . '">' . \esc_html($link_label) . '</a>';
	echo '<div class="notice notice-info is-dismissible cmx-belegeingang-notice" data-cmx-belegeingang-post-id="' . \esc_attr((string) $post_id) . '" data-cmx-belegeingang-nonce="' . \esc_attr(\wp_create_nonce('cmx_belegeingang_dismiss_' . $post_id)) . '"><p>Neuer Beleg im Eingang: ' . $link . ' von ' . \esc_html($seller) . ' – ' . \esc_html($currency . ' ' . $total) . '</p></div>';
});

\add_action('admin_footer', function (): void {
	if (!\current_user_can('edit_posts')) {
		return;
	}
	?>
	<script>
	(function() {
		document.addEventListener('click', function(event) {
			var button = event.target && event.target.closest ? event.target.closest('.cmx-belegeingang-notice .notice-dismiss') : null;
			if (!button) return;
			var notice = button.closest('.cmx-belegeingang-notice');
			if (!notice) return;
			var postId = notice.getAttribute('data-cmx-belegeingang-post-id') || '';
			var nonce = notice.getAttribute('data-cmx-belegeingang-nonce') || '';
			if (!postId || !nonce || typeof ajaxurl === 'undefined') return;
			var data = new FormData();
			data.append('action', 'cmx_belegeingang_dismiss_notice');
			data.append('post_id', postId);
			data.append('_wpnonce', nonce);
			fetch(ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data
			}).catch(function() {});
		});
	})();
	</script>
	<?php
});

\add_action('wp_ajax_cmx_belegeingang_dismiss_notice', function (): void {
	$post_id = isset($_POST['post_id']) ? (int) \wp_unslash($_POST['post_id']) : 0;
	if ($post_id <= 0 || !\current_user_can('edit_posts')) {
		\wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
	}
	if (!isset($_POST['_wpnonce']) || !\wp_verify_nonce((string) \wp_unslash($_POST['_wpnonce']), 'cmx_belegeingang_dismiss_' . $post_id)) {
		\wp_send_json_error(['message' => 'Ungültige Anfrage.'], 403);
	}

	$user_id = (int) \get_current_user_id();
	$dismissed = $user_id > 0 ? (array) \get_user_meta($user_id, CMX_BELEGEINGANG_DISMISSED_META, true) : [];
	$dismissed = \array_values(\array_unique(\array_filter(\array_map('intval', $dismissed))));
	$dismissed[] = $post_id;
	$dismissed = \array_slice(\array_values(\array_unique($dismissed)), -50);
	\update_user_meta($user_id, CMX_BELEGEINGANG_DISMISSED_META, $dismissed);

	\wp_send_json_success();
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
	cmx_belegeingang_track_source_beleg($post_id, 'target_pdf_view');
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
		$seller_emails = cmx_belegeingang_normalize_emails(\array_merge(
			[(string) ($data['seller_email'] ?? '')],
			(array) ($data['seller_emails'] ?? [])
		));
		$buyer = \trim((string) ($data['buyer_name'] ?? ''));
		$buyer_emails = cmx_belegeingang_normalize_emails(\array_merge(
			[(string) ($data['buyer_email'] ?? '')],
			(array) ($data['buyer_emails'] ?? [])
		));

		$kontakt_id = cmx_belegeingang_contact_id_for_party($seller, (string) ($data['seller_address'] ?? ''), $seller_emails, (string) ($data['seller_phone'] ?? ''));
		$recipient_kontakt_id = cmx_belegeingang_contact_id_for_party($buyer, (string) ($data['buyer_address'] ?? ''), $buyer_emails, (string) ($data['buyer_phone'] ?? ''));

		if ($kontakt_id > 0) {
			\update_post_meta($post_id, '_cmx_beleg_kontakt_id', $kontakt_id);
			\update_post_meta($post_id, '_cmx_beleg_absender_kontakt_id', $kontakt_id);
			\update_post_meta($post_id, '_cmx_belegeingang_sender_kontakt_id', $kontakt_id);
			$contact_title = \trim((string) \get_the_title($kontakt_id));
			if ($contact_title !== '') {
				$seller = $contact_title;
			}
		}
		if ($recipient_kontakt_id > 0) {
			\update_post_meta($post_id, '_cmx_beleg_empfaenger_kontakt_id', $recipient_kontakt_id);
			\update_post_meta($post_id, '_cmx_belegeingang_recipient_kontakt_id', $recipient_kontakt_id);
		}

		\update_post_meta($post_id, '_cmx_beleg_richtung', 'eingang');
		\update_post_meta($post_id, '_cmx_beleg_kontakt_label', $seller);
		\update_post_meta($post_id, '_cmx_beleg_kontakt_addr', (string) ($data['seller_address'] ?? ''));
		\update_post_meta($post_id, '_cmx_belegeingang_sender_emails', $seller_emails);
		\update_post_meta($post_id, '_cmx_belegeingang_recipient_emails', $buyer_emails);
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

		cmx_belegeingang_track_source_beleg($post_id, 'target_imported');

		return true;
	}
}

\add_action('load-post.php', function (): void {
	$post_id = isset($_GET['post']) ? (int) \wp_unslash($_GET['post']) : 0;
	if ($post_id <= 0 || !\current_user_can('edit_post', $post_id)) {
		return;
	}
	$post = \get_post($post_id);
	if (!$post instanceof \WP_Post || (string) $post->post_type !== 'belege') {
		return;
	}
	if ((string) \get_post_meta($post_id, CMX_BELEGEINGANG_SOURCE_META, true) !== 'rest') {
		return;
	}
	cmx_belegeingang_track_source_beleg($post_id, 'target_admin_view');
});

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
