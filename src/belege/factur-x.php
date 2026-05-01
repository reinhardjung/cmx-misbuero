<?php namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

use DateTimeImmutable;
use DateTimeInterface;
use horstoeko\zugferd\codelists\ZugferdCountryCodes;
use horstoeko\zugferd\codelists\ZugferdCurrencyCodes;
use horstoeko\zugferd\codelists\ZugferdElectronicAddressScheme;
use horstoeko\zugferd\codelists\ZugferdInvoiceType;
use horstoeko\zugferd\codelists\ZugferdUnitCodes;
use horstoeko\zugferd\codelists\ZugferdVatCategoryCodes;
use horstoeko\zugferd\codelists\ZugferdVatTypeCodes;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfMerger;
use horstoeko\zugferd\ZugferdProfiles;

function cmx_misbuero_embed_facturx_xml_into_pdf(string $pdf_path, int $beleg_id): bool {
	$pdf_path = \wp_normalize_path($pdf_path);
	$beleg_id = (int) $beleg_id;

	if ($beleg_id <= 0 || $pdf_path === '' || !\is_file($pdf_path) || !\is_readable($pdf_path) || !\is_writable($pdf_path)) {
		return false;
	}
	if (!\class_exists(ZugferdDocumentBuilder::class) || !\class_exists(ZugferdDocumentPdfMerger::class)) {
		\error_log('[CMX Factur-X] horstoeko/zugferd ist nicht verfügbar.');
		return false;
	}

	try {
		$xml = cmx_misbuero_generate_facturx_xml($beleg_id);
		if ($xml === '') {
			return false;
		}

		$tmp_path = \tempnam(\sys_get_temp_dir(), 'cmx-facturx-' . $beleg_id . '-');
		if (!\is_string($tmp_path) || $tmp_path === '') {
			\error_log('[CMX Factur-X] Temporäre PDF-Datei konnte nicht erstellt werden.');
			return false;
		}

		(new ZugferdDocumentPdfMerger($xml, $pdf_path))
			->setAdditionalCreatorTool('CMX Mis Büro')
			->generateDocument()
			->saveDocument($tmp_path);

		if (!\is_file($tmp_path) || \filesize($tmp_path) <= 0) {
			@\unlink($tmp_path);
			\error_log('[CMX Factur-X] Gemergtes PDF ist leer.');
			return false;
		}

		$written = @\copy($tmp_path, $pdf_path);
		@\unlink($tmp_path);
		if (!$written) {
			\error_log('[CMX Factur-X] Gemergtes PDF konnte nicht geschrieben werden: ' . $pdf_path);
			return false;
		}

		return true;
	} catch (\Throwable $e) {
		\error_log('[CMX Factur-X] PDF bleibt ohne XML-Anhang: ' . $e->getMessage());
		return false;
	}
}

function cmx_misbuero_generate_facturx_xml($beleg_id): string {
	$beleg_id = (int) $beleg_id;
	$post = \get_post($beleg_id);
	if (!$post instanceof \WP_Post || $post->post_type !== 'belege') {
		return '';
	}

	try {
		$data = cmx_misbuero_facturx_collect_data($beleg_id, $post);
		$builder = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_EN16931);

		$builder->setDocumentInformation(
			(string) $data['number'],
			ZugferdInvoiceType::INVOICE,
			$data['invoice_date'],
			(string) $data['currency']
		);
		$builder->setDocumentBuyerReference((string) $data['buyer_reference']);
		$builder->setDocumentSupplyChainEvent($data['invoice_date']);

		$seller = (array) $data['seller'];
		$builder->setDocumentSeller((string) $seller['name'], null);
		$builder->setDocumentSellerAddress((string) $seller['street'], '', '', (string) $seller['postcode'], (string) $seller['city'], (string) $seller['country']);
		if ((string) $seller['phone'] !== '' || (string) $seller['email'] !== '') {
			$builder->setDocumentSellerContact('', '', (string) $seller['phone'], '', (string) $seller['email']);
		}
		if ((string) $seller['email'] !== '') {
			$builder->setDocumentSellerCommunication(ZugferdElectronicAddressScheme::UNECE3155_EM, (string) $seller['email']);
		}

		$buyer = (array) $data['buyer'];
		$builder->setDocumentBuyer((string) $buyer['name'], null);
		$builder->setDocumentBuyerAddress((string) $buyer['street'], '', '', (string) $buyer['postcode'], (string) $buyer['city'], (string) $buyer['country']);
		if ((string) $buyer['phone'] !== '' || (string) $buyer['email'] !== '') {
			$builder->setDocumentBuyerContact('', '', (string) $buyer['phone'], '', (string) $buyer['email']);
		}
		if ((string) $buyer['email'] !== '') {
			$builder->setDocumentBuyerCommunication(ZugferdElectronicAddressScheme::UNECE3155_EM, (string) $buyer['email']);
		}
		$contact_email_note = cmx_misbuero_facturx_contact_email_note((array) ($seller['emails'] ?? []), (array) ($buyer['emails'] ?? []));
		if ($contact_email_note !== '') {
			$builder->addDocumentNote($contact_email_note);
		}

		if ((string) $data['payment_iban'] !== '') {
			$builder->addDocumentPaymentMeanToCreditTransfer(
				(string) $data['payment_iban'],
				(string) $seller['name'],
				null,
				(string) $data['payment_bic'],
				(string) $data['reference']
			);
		}
		if ($data['due_date'] instanceof DateTimeInterface) {
			$builder->addDocumentPaymentTerm('', $data['due_date']);
		}

		$tax_rate_percent = (float) $data['tax_rate_percent'];
		$vat_category = $tax_rate_percent > 0.0 ? ZugferdVatCategoryCodes::STAN_RATE : ZugferdVatCategoryCodes::ZERO_RATE_GOOD;
		foreach ((array) $data['positions'] as $index => $position) {
			$builder->addNewPosition((string) ((int) $index + 1));
			$builder->setDocumentPositionProductDetails(
				(string) $position['name'],
				(string) $position['description'],
				(string) $position['article_number']
			);
			$builder->setDocumentPositionNetPrice((float) $position['unit_price']);
			$builder->setDocumentPositionQuantity((float) $position['qty'], (string) $position['unit_code']);
			$builder->addDocumentPositionTax($vat_category, ZugferdVatTypeCodes::VALUE_ADDED_TAX, $tax_rate_percent);
			$builder->setDocumentPositionLineSummation((float) $position['line_total']);
		}

		$builder->addDocumentTax(
			$vat_category,
			ZugferdVatTypeCodes::VALUE_ADDED_TAX,
			(float) $data['net_amount'],
			(float) $data['tax_amount'],
			$tax_rate_percent
		);
		$builder->setDocumentSummation(
			(float) $data['gross_amount'],
			(float) $data['gross_amount'],
			(float) $data['net_amount'],
			0.0,
			0.0,
			(float) $data['net_amount'],
			(float) $data['tax_amount']
		);

		return (string) $builder->getContent();
	} catch (\Throwable $e) {
		\error_log('[CMX Factur-X] XML konnte nicht erzeugt werden: ' . $e->getMessage());
		return '';
	}
}

function cmx_misbuero_facturx_collect_data(int $beleg_id, \WP_Post $post): array {
	$currency = \strtoupper(\trim((string) \get_post_meta($beleg_id, '_cmx_beleg_waehrung', true)));
	if ($currency === '') {
		$currency = ZugferdCurrencyCodes::SWISS_FRANC;
	}

	$dates = \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_get_dates') ? (array) cmxbu_beleg_get_dates($beleg_id) : [];
	$invoice_date = cmx_misbuero_facturx_date((string) ($dates['date_invoice'] ?? ''));
	if (!$invoice_date instanceof DateTimeInterface) {
		$invoice_date = cmx_misbuero_facturx_date((string) \get_post_meta($beleg_id, '_cmx_beleg_rng_datum', true));
	}
	if (!$invoice_date instanceof DateTimeInterface) {
		$invoice_date = new DateTimeImmutable((string) \get_the_date('Y-m-d', $beleg_id));
	}
	$due_date = cmx_misbuero_facturx_date((string) ($dates['date_due'] ?? ''));
	if (!$due_date instanceof DateTimeInterface) {
		$due_date = cmx_misbuero_facturx_date((string) \get_post_meta($beleg_id, '_cmx_beleg_faelligkeitsdatum', true));
	}

	$mwst = \function_exists(__NAMESPACE__ . '\\cmxbu_get_mwst_term_data')
		? (array) cmxbu_get_mwst_term_data((int) \get_post_meta($beleg_id, '_cmx_beleg_mwst_term', true))
		: ['rate' => 0.0];
	$tax_rate = (float) ($mwst['rate'] ?? 0.0);
	$is_brutto = \get_post_meta($beleg_id, '_cmx_beleg_is_brutto', true) === '1';
	$calc = \function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')
		? (array) cmxbu_get_beleg_positionen_calc($beleg_id, [
			'currency'  => $currency,
			'tax_rate'  => $tax_rate,
			'is_brutto' => $is_brutto,
		])
		: [];

	$bank = \function_exists(__NAMESPACE__ . '\\cmxbu_get_preferred_bank') ? (array) cmxbu_get_preferred_bank() : [];
	$qr_iban = \trim((string) ($bank['qr_iban'] ?? ''));
	$iban = \trim((string) ($bank['iban'] ?? ''));

	return [
		'number'           => \trim((string) ($post->post_title !== '' ? $post->post_title : $beleg_id)),
		'buyer_reference'  => \trim((string) ($post->post_title !== '' ? $post->post_title : $beleg_id)),
		'invoice_date'     => $invoice_date,
		'due_date'         => $due_date,
		'currency'         => $currency,
		'seller'           => cmx_misbuero_facturx_seller(),
		'buyer'            => cmx_misbuero_facturx_buyer($beleg_id),
		'positions'        => cmx_misbuero_facturx_positions((array) ($calc['positionen'] ?? []), $tax_rate, $is_brutto, (float) ($calc['net'] ?? 0.0)),
		'net_amount'       => (float) ($calc['net'] ?? $calc['subtotal'] ?? 0.0),
		'tax_amount'       => (float) ($calc['tax_amount'] ?? 0.0),
		'gross_amount'     => (float) ($calc['gross'] ?? $calc['total'] ?? 0.0),
		'tax_rate_percent' => $tax_rate * 100,
		'reference'        => \trim((string) \get_post_meta($beleg_id, '_cmx_beleg_qrr', true)),
		'payment_iban'     => $qr_iban !== '' ? $qr_iban : $iban,
		'payment_bic'      => \trim((string) ($bank['bic'] ?? '')),
	];
}

function cmx_misbuero_facturx_positions(array $rows, float $tax_rate, bool $is_brutto, float $fallback_net): array {
	$positions = [];
	foreach ($rows as $row) {
		if (!\is_array($row) || (string) ($row['row_type'] ?? '') === 'abschnitt') {
			continue;
		}
		$qty = (float) ($row['qty'] ?? 0.0);
		if ($qty == 0.0) {
			$qty = 1.0;
		}
		$unit_price = (float) ($row['unit_price'] ?? 0.0);
		$line_total = (float) ($row['line_total'] ?? 0.0);
		if ($is_brutto && $tax_rate > 0.0) {
			$unit_price = $unit_price / (1 + $tax_rate);
			$line_total = $line_total / (1 + $tax_rate);
		}
		$positions[] = [
			'name'           => \trim((string) ($row['title'] ?? 'Position')),
			'description'    => \trim((string) ($row['desc_text'] ?? '')),
			'article_number' => \trim((string) ($row['article_number'] ?? '')),
			'qty'            => $qty,
			'unit_code'      => cmx_misbuero_facturx_unit_code((string) ($row['unit'] ?? '')),
			'unit_price'     => \round($unit_price, 4),
			'line_total'     => \round($line_total, 2),
		];
	}

	if (empty($positions)) {
		$positions[] = [
			'name'           => 'Rechnung',
			'description'    => '',
			'article_number' => '',
			'qty'            => 1.0,
			'unit_code'      => ZugferdUnitCodes::REC20_PIECE,
			'unit_price'     => \round($fallback_net, 4),
			'line_total'     => \round($fallback_net, 2),
		];
	}

	return $positions;
}

function cmx_misbuero_facturx_seller(): array {
	$me = \function_exists(__NAMESPACE__ . '\\cmxbu_get_me_contact') ? (array) cmxbu_get_me_contact() : [];
	$contact_id = cmx_misbuero_facturx_self_contact_id();
	$emails = $contact_id > 0 ? cmx_misbuero_facturx_contact_emails($contact_id) : [];
	$primary_email = \sanitize_email((string) ($me['email'] ?? ''));
	if (\is_email($primary_email)) {
		$emails = cmx_misbuero_facturx_normalize_emails(\array_merge([$primary_email], $emails));
	}
	return [
		'name'     => cmx_misbuero_facturx_non_empty((string) ($me['company'] ?? ''), 'Mis Büro'),
		'street'   => \trim((string) ($me['strasse'] ?? '')),
		'postcode' => \trim((string) ($me['plz'] ?? '')),
		'city'     => \trim((string) ($me['ort'] ?? '')),
		'country'  => cmx_misbuero_facturx_country((string) ($me['land'] ?? 'CH')),
		'email'    => (string) ($emails[0] ?? ''),
		'emails'   => $emails,
		'phone'    => \trim((string) ($me['phone'] ?? '')),
	];
}

function cmx_misbuero_facturx_buyer(int $beleg_id): array {
	$kontakt_id = \function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_kontakt_id')
		? (int) cmxbu_get_beleg_kontakt_id($beleg_id)
		: (int) \get_post_meta($beleg_id, '_cmx_beleg_kontakt_id', true);
	$address = cmx_misbuero_facturx_parse_address((string) \get_post_meta($beleg_id, '_cmx_beleg_kontakt_addr', true));
	$title = $kontakt_id > 0 ? (string) \get_the_title($kontakt_id) : '';

	$emails = $kontakt_id > 0 ? cmx_misbuero_facturx_contact_emails($kontakt_id) : [];
	$primary_email = $kontakt_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_email')
		? \sanitize_email((string) cmx_kommunikation_primary_email($kontakt_id))
		: '';
	if (\is_email($primary_email)) {
		$emails = cmx_misbuero_facturx_normalize_emails(\array_merge([$primary_email], $emails));
	}

	return [
		'name'     => cmx_misbuero_facturx_non_empty($title !== '' ? $title : (string) ($address['name'] ?? ''), 'Kunde'),
		'street'   => \trim((string) ($address['street'] ?? '')),
		'postcode' => \trim((string) ($address['postcode'] ?? '')),
		'city'     => \trim((string) ($address['city'] ?? '')),
		'country'  => cmx_misbuero_facturx_country((string) ($address['country'] ?? 'CH')),
		'email'    => (string) ($emails[0] ?? ''),
		'emails'   => $emails,
		'phone'    => $kontakt_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_phone') ? \trim((string) cmx_kommunikation_primary_phone($kontakt_id)) : '',
	];
}

function cmx_misbuero_facturx_normalize_emails(array $emails): array {
	$out = [];
	foreach ($emails as $email) {
		$email = \sanitize_email((string) $email);
		if (\is_email($email)) {
			$out[\strtolower($email)] = $email;
		}
	}
	return \array_values($out);
}

function cmx_misbuero_facturx_contact_emails(int $kontakt_id): array {
	$kontakt_id = (int) $kontakt_id;
	if ($kontakt_id <= 0) {
		return [];
	}
	$emails = \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_collect_emails')
		? (array) cmx_kommunikation_collect_emails($kontakt_id)
		: [];
	foreach (['_cmx_email_1', '_cmx_email_2', '_cmx_email_3', 'email', 'mail'] as $key) {
		$emails[] = (string) \get_post_meta($kontakt_id, $key, true);
	}
	return cmx_misbuero_facturx_normalize_emails($emails);
}

function cmx_misbuero_facturx_self_contact_id(): int {
	$q = \get_posts([
		'post_type' => ['kontakte', 'kontakt', 'contact'],
		'post_status' => ['publish', 'private'],
		'posts_per_page' => 1,
		'fields' => 'ids',
		'tax_query' => [
			'relation' => 'OR',
			['taxonomy' => 'kontakte_kategorien', 'field' => 'slug', 'terms' => ['das-bin-ich', 'ich']],
			['taxonomy' => 'kontakte_kategorien', 'field' => 'name', 'terms' => ['Das bin ich']],
		],
		'no_found_rows' => true,
		'suppress_filters' => true,
	]);
	return !empty($q[0]) ? (int) $q[0] : 0;
}

function cmx_misbuero_facturx_contact_email_note(array $seller_emails, array $buyer_emails): string {
	$payload = [
		'seller_emails' => cmx_misbuero_facturx_normalize_emails($seller_emails),
		'buyer_emails' => cmx_misbuero_facturx_normalize_emails($buyer_emails),
	];
	if ($payload['seller_emails'] === [] && $payload['buyer_emails'] === []) {
		return '';
	}
	return 'CMX_CONTACT_EMAILS:' . (string) \wp_json_encode($payload);
}

function cmx_misbuero_facturx_parse_address(string $address): array {
	$lines = \array_values(\array_filter(\array_map('trim', \preg_split('~\\R+~u', \wp_strip_all_tags($address)))));
	$name = (string) ($lines[0] ?? '');
	$street = (string) ($lines[1] ?? '');
	$postcode = '';
	$city = '';
	$country = (string) ($lines[3] ?? 'CH');
	if (!empty($lines[2]) && \preg_match('~^(\\d{4,6})\\s+(.+)$~u', (string) $lines[2], $matches)) {
		$postcode = (string) $matches[1];
		$city = (string) $matches[2];
	}
	return compact('name', 'street', 'postcode', 'city', 'country');
}

function cmx_misbuero_facturx_date(string $value): ?DateTimeImmutable {
	$value = \trim($value);
	if ($value === '') {
		return null;
	}
	if (\preg_match('~^(\\d{1,2})\\.(\\d{1,2})\\.(\\d{4})$~', $value, $matches)) {
		$value = \sprintf('%04d-%02d-%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1]);
	}
	try {
		return new DateTimeImmutable($value);
	} catch (\Throwable $e) {
		return null;
	}
}

function cmx_misbuero_facturx_country(string $country): string {
	$country = \strtoupper(\trim($country));
	if ($country === '' || $country === 'SCHWEIZ' || $country === 'SWITZERLAND' || $country === 'SUISSE') {
		return ZugferdCountryCodes::SWITZERLAND;
	}
	return \preg_match('~^[A-Z]{2}$~', $country) ? $country : ZugferdCountryCodes::SWITZERLAND;
}

function cmx_misbuero_facturx_unit_code(string $unit): string {
	$unit = \strtolower(\trim($unit));
	if (\in_array($unit, ['h', 'std', 'stunde', 'stunden'], true)) {
		return ZugferdUnitCodes::REC20_HOUR;
	}
	if (\in_array($unit, ['kg', 'kilogramm'], true)) {
		return ZugferdUnitCodes::REC20_KILOGRAM;
	}
	if (\in_array($unit, ['m', 'meter'], true)) {
		return ZugferdUnitCodes::REC20_METRE;
	}
	return ZugferdUnitCodes::REC20_PIECE;
}

function cmx_misbuero_facturx_non_empty(string $value, string $fallback): string {
	$value = \trim($value);
	return $value !== '' ? $value : $fallback;
}

/*
 * Annahmen und offene Punkte:
 * - Das sichtbare Rechnungs-PDF wird weiter durch Dompdf erzeugt.
 * - horstoeko/zugferd bettet danach die EN16931-XML als factur-x.xml in das bestehende PDF ein.
 * - Wenn XML-Erzeugung oder PDF-Merge fehlschlägt, bleibt das normale Dompdf-PDF unverändert bestehen.
 * - Es wird keine separate Sidecar-XML-Datei gespeichert.
 */
