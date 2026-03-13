<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_PT_BELEGE')) {
	\define(__NAMESPACE__ . '\\CMX_PT_BELEGE', 'belege');
}

if (!\defined(__NAMESPACE__ . '\\CMX_PT_KONTAKTE')) {
	\define(__NAMESPACE__ . '\\CMX_PT_KONTAKTE', 'kontakte');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_belege_uuid_meta_key')) {
	function cmx_kontakt_belege_uuid_meta_key(): string {
		return '_cmx_kontakt_belege_uuid';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_get_stable_uuid')) {
	function cmx_kontakt_get_stable_uuid(int $kontakt_id): string {
		$kontakt_id = (int) $kontakt_id;
		if ($kontakt_id <= 0) {
			return '';
		}

		$meta_key = cmx_kontakt_belege_uuid_meta_key();
		$uuid = (string) \get_post_meta($kontakt_id, $meta_key, true);
		$uuid = \trim($uuid);
		if ($uuid !== '') {
			return $uuid;
		}

		$uuid = \function_exists('\\wp_generate_uuid4')
			? (string) \wp_generate_uuid4()
			: '';
		if ($uuid === '') {
			return '';
		}

		\update_post_meta($kontakt_id, $meta_key, $uuid);
		return $uuid;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_find_by_uuid')) {
	function cmx_kontakt_find_by_uuid(string $uuid): int {
		$uuid = \trim($uuid);
		if ($uuid === '') {
			return 0;
		}

		$ids = \get_posts([
			'post_type' => CMX_PT_KONTAKTE,
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'fields' => 'ids',
			'posts_per_page' => 1,
			'no_found_rows' => true,
			'suppress_filters' => true,
			'meta_query' => [[
				'key' => cmx_kontakt_belege_uuid_meta_key(),
				'value' => $uuid,
				'compare' => '=',
			]],
		]);

		return !empty($ids) ? (int) $ids[0] : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_belege_share_url')) {
	function cmx_kontakt_belege_share_url(int $kontakt_id): string {
		$uuid = cmx_kontakt_get_stable_uuid($kontakt_id);
		if ($uuid === '') {
			return (string) \home_url('/');
		}
		return (string) \add_query_arg(['kontakt' => $uuid], \home_url('/'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_request_kontakt_id')) {
	function cmx_kontakt_request_kontakt_id(): int {
		$kontakt_id = isset($_GET['cmx_kontakt_id']) ? (int) $_GET['cmx_kontakt_id'] : 0;
		if ($kontakt_id > 0) {
			return $kontakt_id;
		}

		$uuid = isset($_GET['kontakt']) ? \sanitize_text_field((string) \wp_unslash($_GET['kontakt'])) : '';
		if ($uuid === '') {
			return 0;
		}

		$kontakt_id = cmx_kontakt_find_by_uuid($uuid);
		if ($kontakt_id > 0) {
			$_GET['cmx_kontakt_id'] = (string) $kontakt_id;
			$_REQUEST['cmx_kontakt_id'] = (string) $kontakt_id;
		}

		return $kontakt_id;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_type_label')) {
	function cmx_kontakt_beleg_type_label(int $beleg_id): string {
		$tax = \function_exists(__NAMESPACE__ . '\\cmx_belege_taxonomy')
			? (string) cmx_belege_taxonomy()
			: '';
		if ($tax !== '') {
			$names = \wp_get_post_terms($beleg_id, $tax, ['fields' => 'names']);
			if (!\is_wp_error($names) && !empty($names)) {
				return \trim((string) ($names[0] ?? ''));
			}
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_get_beleg_type')) {
			$post = \get_post($beleg_id);
			if ($post instanceof \WP_Post) {
				[, $slug] = cmx_get_beleg_type($post);
				$slug = \trim((string) $slug);
				if ($slug !== '') {
					return \ucfirst($slug);
				}
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_type_slug')) {
	function cmx_kontakt_beleg_type_slug(int $beleg_id): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_get_beleg_type')) {
			$post = \get_post($beleg_id);
			if ($post instanceof \WP_Post) {
				[, $slug] = cmx_get_beleg_type($post);
				return \sanitize_key((string) $slug);
			}
		}
		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_date_label')) {
	function cmx_kontakt_beleg_date_label(int $beleg_id): string {
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_mail_subject_beleg_date')) {
			$date = \trim((string) cmxbu_get_mail_subject_beleg_date($beleg_id));
			if ($date !== '') {
				return $date;
			}
		}

		$post = \get_post($beleg_id);
		if (!$post instanceof \WP_Post) {
			return '';
		}

		$ts = \strtotime((string) ($post->post_date ?: ''));
		return $ts ? \date_i18n('d.m.Y', $ts) : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_date_sort_value')) {
	function cmx_kontakt_beleg_date_sort_value(int $beleg_id): int {
		$date = cmx_kontakt_beleg_date_label($beleg_id);
		if ($date !== '' && \preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $date, $m)) {
			$ts = \strtotime($m[3] . '-' . $m[2] . '-' . $m[1] . ' 00:00:00');
			if ($ts) {
				return (int) $ts;
			}
		}
		$post = \get_post($beleg_id);
		if (!$post instanceof \WP_Post) {
			return 0;
		}
		$ts = \strtotime((string) ($post->post_date ?: ''));
		return $ts ? (int) $ts : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_currency')) {
	function cmx_kontakt_beleg_currency(int $beleg_id): string {
		$currency = '';
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_WAEHRUNG')) {
			$currency = (string) \get_post_meta($beleg_id, \constant(__NAMESPACE__ . '\\CMX_BELEG_META_WAEHRUNG'), true);
		}
		if ($currency === '') {
			$currency = (string) \get_post_meta($beleg_id, '_cmx_beleg_waehrung', true);
		}
		$currency = \strtoupper(\trim($currency));
		return $currency !== '' ? $currency : 'CHF';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_amount_raw')) {
	function cmx_kontakt_beleg_amount_raw(int $beleg_id): float {
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
			$currency = cmx_kontakt_beleg_currency($beleg_id);
			$mwst_rate = 0.0;
			if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_mwst_term_data')) {
				$mwst_term_id = (int) \get_post_meta($beleg_id, '_cmx_beleg_mwst_term', true);
				$mwst = cmxbu_get_mwst_term_data($mwst_term_id);
				$mwst_rate = (float) ($mwst['rate'] ?? 0.0);
			}
			$is_brutto = \get_post_meta($beleg_id, '_cmx_beleg_is_brutto', true) === '1';
			$opts_general = (array) \get_option('cmx_einstellungen', []);
			$is_mwst_pflichtig = \function_exists(__NAMESPACE__ . '\\cmx_belege_is_mwst_pflichtig')
				? cmx_belege_is_mwst_pflichtig($opts_general)
				: !empty($opts_general['mwst_pflichtig']);
			$beleg_type = cmx_kontakt_beleg_type_slug($beleg_id);
			$mwst_allowed_for_type = \function_exists(__NAMESPACE__ . '\\cmx_belege_allows_mwst_for_type')
				? cmx_belege_allows_mwst_for_type((string) $beleg_type, $opts_general)
				: $is_mwst_pflichtig;
			if (!$mwst_allowed_for_type) {
				$mwst_rate = 0.0;
				$is_brutto = false;
			}
			$calc = (array) cmxbu_get_beleg_positionen_calc($beleg_id, [
				'currency' => $currency,
				'tax_rate' => $mwst_rate,
				'is_brutto' => $is_brutto,
			]);
			if (isset($calc['total']) && \is_numeric($calc['total'])) {
				return (float) $calc['total'];
			}
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_kontakt_belege_parse_decimal')) {
			$amount_label = \function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_amount_label')
				? (string) cmx_kontakt_beleg_amount_label($beleg_id)
				: '';
			return (float) cmx_kontakt_belege_parse_decimal($amount_label);
		}

		return 0.0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_is_outgoing_invoice_open')) {
	function cmx_kontakt_beleg_is_outgoing_invoice_open(int $beleg_id): bool {
		$type_slug = cmx_kontakt_beleg_type_slug($beleg_id);
		if (!\in_array($type_slug, ['rechnung', 'rechnungen'], true)) {
			return false;
		}

		$richtung_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_RICHTUNG')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_RICHTUNG')
			: '_cmx_beleg_richtung';
		$richtung = \sanitize_key((string) \get_post_meta($beleg_id, $richtung_key, true));
		if ($richtung !== 'ausgang') {
			return false;
		}

		$state = \function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_paid_label')
			? (array) cmx_kontakt_beleg_paid_label($beleg_id)
			: [];
		return (string) ($state['slug'] ?? 'offen') !== 'bezahlt';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_payrexx_url')) {
	function cmx_kontakt_beleg_payrexx_url(int $beleg_id): string {
		if (!cmx_kontakt_beleg_is_outgoing_invoice_open($beleg_id)) {
			return '';
		}
		if (!\function_exists(__NAMESPACE__ . '\\cmx_get_payrexx_vpos_url')) {
			return '';
		}

		$base_url = (string) cmx_get_payrexx_vpos_url();
		if ($base_url === '') {
			return '';
		}

		$kontakt_id = \function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_kontakt_id')
			? (int) cmxbu_get_beleg_kontakt_id($beleg_id)
			: 0;
		$contact = \function_exists(__NAMESPACE__ . '\\cmxbu_get_payrexx_contact_data')
			? (array) cmxbu_get_payrexx_contact_data($kontakt_id)
			: ['company' => '', 'forename' => '', 'surname' => '', 'email' => ''];
		$terminal_id = \function_exists(__NAMESPACE__ . '\\cmx_get_payrexx_terminal_id')
			? \trim((string) cmx_get_payrexx_terminal_id())
			: '';
		$title = \trim((string) \get_the_title($beleg_id));
		$amount = \number_format(cmx_kontakt_beleg_amount_raw($beleg_id), 2, '.', '');
		$currency = cmx_kontakt_beleg_currency($beleg_id);

		$query = \http_build_query([
			'tid' => $terminal_id,
			'amount' => $amount,
			'currency' => $currency,
			'purpose' => $title,
			'contact_company' => (string) ($contact['company'] ?? ''),
			'contact_forename' => (string) ($contact['forename'] ?? ''),
			'contact_surname' => (string) ($contact['surname'] ?? ''),
			'contact_email' => (string) ($contact['email'] ?? ''),
		], '', '&', \PHP_QUERY_RFC3986);

		return $base_url . $query;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_all_belege_rows')) {
	function cmx_kontakt_all_belege_rows(int $kontakt_id): array {
		if ($kontakt_id <= 0) {
			return [];
		}

		$kontakt_keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID')) {
			$kontakt_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID');
		}
		$kontakt_keys = \array_merge($kontakt_keys, ['_cmx_beleg_kontakt_id', 'cmx_beleg_kontakt_id']);
		$kontakt_keys = \array_values(\array_unique(\array_filter($kontakt_keys)));

		$meta_or = ['relation' => 'OR'];
		foreach ($kontakt_keys as $key) {
			$meta_or[] = [
				'key'     => $key,
				'value'   => $kontakt_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			];
		}

		$q = new \WP_Query([
			'post_type'               => CMX_PT_BELEGE,
			'post_status'             => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'          => -1,
			'fields'                  => 'ids',
			'no_found_rows'           => true,
			'update_post_meta_cache'  => false,
			'update_post_term_cache'  => false,
			'orderby'                 => 'date',
			'order'                   => 'DESC',
			'meta_query'              => [$meta_or],
		]);

		$rows = [];
		foreach ((array) $q->posts as $beleg_id) {
			$beleg_id = (int) $beleg_id;
			if ($beleg_id <= 0) {
				continue;
			}

			$title = \trim((string) \get_the_title($beleg_id));
			if ($title === '') {
				$title = '#' . $beleg_id;
			}

			$public_url = '';
			if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_stable_token')) {
				$token = (string) cmxbu_get_stable_token($beleg_id);
				if ($token !== '') {
					$public_url = (string) \add_query_arg('beleg', $token, \home_url('/'));
				}
			}

			$amount = \function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_amount_label')
				? (string) cmx_kontakt_beleg_amount_label($beleg_id)
				: '';
			$amount_raw = cmx_kontakt_beleg_amount_raw($beleg_id);
			$state = \function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_paid_label')
				? (array) cmx_kontakt_beleg_paid_label($beleg_id)
				: ['slug' => 'offen', 'label' => 'Offen'];
			$due_label = \function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_due_label')
				? (string) cmx_kontakt_beleg_due_label($beleg_id)
				: '';
			$payrexx_url = cmx_kontakt_beleg_payrexx_url($beleg_id);

			$rows[] = [
				'id'         => $beleg_id,
				'title'      => $title,
				'public_url' => $public_url,
				'type'       => cmx_kontakt_beleg_type_label($beleg_id),
				'type_slug'  => cmx_kontakt_beleg_type_slug($beleg_id),
				'date'       => cmx_kontakt_beleg_date_label($beleg_id),
				'date_sort'  => cmx_kontakt_beleg_date_sort_value($beleg_id),
				'amount'     => $amount,
				'amount_raw' => $amount_raw,
				'state'      => $state,
				'due_label'  => $due_label,
				'payrexx_url' => $payrexx_url,
				'online_sort' => $payrexx_url !== '' ? 1 : 0,
			];
		}

		return $rows;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_is_kontakt_belege_share_request')) {
	function cmx_is_kontakt_belege_share_request(): bool {
		if (\is_admin()) {
			return false;
		}
		return isset($_GET['kontakt']) && \trim((string) \wp_unslash($_GET['kontakt'])) !== '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_kontakt_belege_share_page')) {
	function cmx_render_kontakt_belege_share_page(): void {
		if (!cmx_is_kontakt_belege_share_request()) {
			return;
		}

		$uuid = \sanitize_text_field((string) \wp_unslash($_GET['kontakt']));
		$kontakt_id = cmx_kontakt_find_by_uuid($uuid);
		if ($kontakt_id <= 0) {
			\wp_die('Kontakt nicht gefunden.');
		}

		$kontakt_name = \trim((string) \get_the_title($kontakt_id));
		if ($kontakt_name === '') {
			$kontakt_name = 'Kontakt';
		}
		$rows = cmx_kontakt_all_belege_rows($kontakt_id);
		$show_online_column = false;
		foreach ($rows as $row) {
			if (\trim((string) ($row['payrexx_url'] ?? '')) !== '') {
				$show_online_column = true;
				break;
			}
		}

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
		echo '<title>' . \esc_html($kontakt_name . ' - Belege') . '</title>';
		echo '<style>
			:root{color-scheme:light}
			*{box-sizing:border-box}
			body{margin:0;font-family:Segoe UI,Roboto,Arial,sans-serif;background:#efefef;color:#1d2327}
			.cmx-kontakt-page{max-width:1120px;margin:0 auto;padding:32px 18px 40px}
			.cmx-kontakt-card{background:#fff;border:1px solid #ddd;border-radius:14px;box-shadow:0 18px 40px rgba(0,0,0,.06);overflow:hidden}
			.cmx-kontakt-head{padding:24px 28px 18px;background:linear-gradient(135deg,#f7f7f7 0%,#ededed 100%);border-bottom:1px solid #e2e2e2}
			.cmx-kontakt-kicker{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;margin:0 0 8px}
			.cmx-kontakt-title{margin:0;font-size:30px;line-height:1.1}
			.cmx-kontakt-sub{margin:8px 0 0;color:#6b7280;font-size:14px}
			.cmx-kontakt-tools{padding:18px 28px 0}
			.cmx-kontakt-tools input{width:100%;max-width:340px;padding:10px 12px;border:1px solid #c8c8c8;border-radius:10px;font:inherit}
			.cmx-kontakt-table-wrap{padding:18px 28px 28px;overflow-x:auto}
			.cmx-kontakt-table{width:100%;border-collapse:collapse;min-width:760px}
			.cmx-kontakt-table th,.cmx-kontakt-table td{padding:12px 10px;border-bottom:1px solid #ececec;text-align:left;vertical-align:top}
			.cmx-kontakt-table th{font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:#6b7280;background:#fafafa}
			.cmx-kontakt-table th button{all:unset;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:inherit}
			.cmx-kontakt-table th button:hover{color:#1d2327}
			.cmx-kontakt-sort-indicator{font-size:11px;opacity:.55;min-width:10px;text-align:center}
			.cmx-kontakt-table tbody tr:nth-child(even){background:#fcfcfc}
			.cmx-kontakt-table a{color:#135e96;text-decoration:none;font-weight:600}
			.cmx-kontakt-table a:hover{color:#0a4b79}
			.cmx-kontakt-amount,.cmx-kontakt-date,.cmx-kontakt-status{white-space:nowrap}
			.cmx-kontakt-status .is-paid{color:#2f7d32;font-weight:700}
			.cmx-kontakt-status .is-open{color:#b32d2e;font-weight:700}
			.cmx-kontakt-online{white-space:nowrap}
			.cmx-kontakt-pay-btn{display:inline-block;padding:8px 12px;border-radius:9px;background:#135e96;color:#fff!important;font-weight:700;text-decoration:none}
			.cmx-kontakt-pay-btn:hover{background:#0f4d7b;color:#fff!important}
			.cmx-kontakt-empty{padding:28px;color:#6b7280}
			@media (max-width:720px){
				.cmx-kontakt-page{padding:18px 12px 24px}
				.cmx-kontakt-head,.cmx-kontakt-tools,.cmx-kontakt-table-wrap{padding-left:16px;padding-right:16px}
				.cmx-kontakt-title{font-size:24px}
			}
		</style>';
		echo '</head><body>';
		echo '<div class="cmx-kontakt-page"><div class="cmx-kontakt-card">';
		echo '<div class="cmx-kontakt-head">';
		echo '<p class="cmx-kontakt-kicker">Belegübersicht</p>';
		echo '<h1 class="cmx-kontakt-title">' . \esc_html($kontakt_name) . '</h1>';
		echo '<p class="cmx-kontakt-sub">' . \esc_html(\count($rows) . ' Belege') . '</p>';
		echo '</div>';

		if (empty($rows)) {
			echo '<div class="cmx-kontakt-empty">Aktuell keine Belege gefunden.</div>';
			echo '</div></div></body></html>';
			exit;
		}

		echo '<div class="cmx-kontakt-tools">';
		echo '<input type="search" id="cmx-kontakt-search" placeholder="Belege durchsuchen">';
		echo '</div>';
		echo '<div class="cmx-kontakt-table-wrap">';
		echo '<table class="cmx-kontakt-table"><thead><tr>';
		echo '<th><button type="button" data-sort-key="date" data-sort-type="number">Datum<span class="cmx-kontakt-sort-indicator"> </span></button></th>';
		echo '<th><button type="button" data-sort-key="title" data-sort-type="string">Beleg<span class="cmx-kontakt-sort-indicator"> </span></button></th>';
		echo '<th><button type="button" data-sort-key="type" data-sort-type="string">Typ<span class="cmx-kontakt-sort-indicator"> </span></button></th>';
		echo '<th><button type="button" data-sort-key="amount" data-sort-type="number">Betrag<span class="cmx-kontakt-sort-indicator"> </span></button></th>';
		echo '<th><button type="button" data-sort-key="status" data-sort-type="string">Status<span class="cmx-kontakt-sort-indicator"> </span></button></th>';
		if ($show_online_column) {
			echo '<th><button type="button" data-sort-key="online" data-sort-type="number">online<span class="cmx-kontakt-sort-indicator"> </span></button></th>';
		}
		echo '</tr></thead><tbody id="cmx-kontakt-table-body">';

		foreach ($rows as $row) {
			$title = (string) ($row['title'] ?? '');
			$public_url = (string) ($row['public_url'] ?? '');
			$type = (string) ($row['type'] ?? '');
			$date = (string) ($row['date'] ?? '');
			$amount = (string) ($row['amount'] ?? '');
			$amount_raw = (float) ($row['amount_raw'] ?? 0.0);
			$state = (array) ($row['state'] ?? []);
			$due_label = (string) ($row['due_label'] ?? '');
			$payrexx_url = (string) ($row['payrexx_url'] ?? '');
			$type_slug = (string) ($row['type_slug'] ?? '');
			$date_sort = (int) ($row['date_sort'] ?? 0);
			$online_sort = (int) ($row['online_sort'] ?? 0);
			$state_slug = (string) ($state['slug'] ?? 'offen');
			$state_label = (string) ($state['label'] ?? 'Offen');
			$display_status = $state_slug === 'bezahlt'
				? $state_label
				: ($due_label !== '' ? $due_label : $state_label);
			$status_class = $state_slug === 'bezahlt' ? 'is-paid' : 'is-open';
			$search_blob = \implode(' ', \array_filter([
				$date,
				$title,
				$type,
				$type_slug,
				$amount,
				(string) $amount_raw,
				$display_status,
				$state_label,
				$due_label,
				$payrexx_url !== '' ? 'bezahlen online payrexx' : '',
			]));

			echo '<tr data-search="' . \esc_attr(\function_exists('mb_strtolower') ? \mb_strtolower($search_blob, 'UTF-8') : \strtolower($search_blob)) . '"'
				. ' data-sort-date="' . \esc_attr((string) $date_sort) . '"'
				. ' data-sort-title="' . \esc_attr(\function_exists('mb_strtolower') ? \mb_strtolower($title, 'UTF-8') : \strtolower($title)) . '"'
				. ' data-sort-type="' . \esc_attr(\function_exists('mb_strtolower') ? \mb_strtolower($type, 'UTF-8') : \strtolower($type)) . '"'
				. ' data-sort-amount="' . \esc_attr(\number_format($amount_raw, 2, '.', '')) . '"'
				. ' data-sort-status="' . \esc_attr(\function_exists('mb_strtolower') ? \mb_strtolower($display_status, 'UTF-8') : \strtolower($display_status)) . '"'
				. ' data-sort-online="' . \esc_attr((string) $online_sort) . '">';
			echo '<td class="cmx-kontakt-date">' . \esc_html($date) . '</td>';
			echo '<td>';
			if ($public_url !== '') {
				echo '<a href="' . \esc_url($public_url) . '" target="_blank" rel="noopener noreferrer">' . \esc_html($title) . '</a>';
			} else {
				echo \esc_html($title);
			}
			echo '</td>';
			echo '<td>' . \esc_html($type) . '</td>';
			echo '<td class="cmx-kontakt-amount">' . \esc_html($amount) . '</td>';
			echo '<td class="cmx-kontakt-status"><span class="' . \esc_attr($status_class) . '">' . \esc_html($display_status) . '</span></td>';
			if ($show_online_column) {
				echo '<td class="cmx-kontakt-online">';
				if ($payrexx_url !== '') {
					echo '<a href="' . \esc_url($payrexx_url) . '" class="cmx-kontakt-pay-btn" target="_blank" rel="noopener noreferrer">bezahlen</a>';
				}
				echo '</td>';
			}
			echo '</tr>';
		}

		echo '</tbody></table></div>';
		echo '</div></div>';
		echo '<script>
			(function(){
				var input=document.getElementById("cmx-kontakt-search");
				var body=document.getElementById("cmx-kontakt-table-body");
				var sortButtons=document.querySelectorAll(".cmx-kontakt-table thead button[data-sort-key]");
				if(!body){return;}
				function normalize(txt){return String(txt||"").toLowerCase().trim();}
				function getRows(){return Array.prototype.slice.call(body.querySelectorAll("tr[data-search]"));}
				function applyFilter(){
					var term=normalize(input?input.value:"");
					getRows().forEach(function(row){
						var hay=(row.getAttribute("data-search")||"") + " " + normalize(row.textContent||"");
						row.style.display=(term===""||hay.indexOf(term)!==-1)?"":"none";
					});
				}
				function sortRows(key,type,dir){
					var rows=getRows();
					rows.sort(function(a,b){
						var av=a.getAttribute("data-sort-"+key)||"";
						var bv=b.getAttribute("data-sort-"+key)||"";
						if(type==="number"){
							av=parseFloat(av||"0")||0;
							bv=parseFloat(bv||"0")||0;
						}else{
							av=normalize(av);
							bv=normalize(bv);
						}
						if(av===bv){ return 0; }
						if(dir==="desc"){ return av < bv ? 1 : -1; }
						return av > bv ? 1 : -1;
					});
					rows.forEach(function(row){ body.appendChild(row); });
					applyFilter();
				}
				if(input){
					input.addEventListener("input",applyFilter);
				}
				Array.prototype.forEach.call(sortButtons,function(btn){
					btn.addEventListener("click",function(){
						var key=btn.getAttribute("data-sort-key")||"";
						var type=btn.getAttribute("data-sort-type")||"string";
						var current=btn.getAttribute("data-sort-dir")||"";
						var next=current==="asc"?"desc":"asc";
						Array.prototype.forEach.call(sortButtons,function(other){
							other.setAttribute("data-sort-dir", other===btn ? next : "");
							var indicator=other.querySelector(".cmx-kontakt-sort-indicator");
							if(indicator){ indicator.textContent = other===btn ? (next==="asc" ? "▲" : "▼") : " "; }
						});
						sortRows(key,type,next);
					});
				});
				sortRows("date","number","desc");
				Array.prototype.forEach.call(sortButtons,function(btn){
					if((btn.getAttribute("data-sort-key")||"")==="date"){
						btn.setAttribute("data-sort-dir","desc");
						var indicator=btn.querySelector(".cmx-kontakt-sort-indicator");
						if(indicator){ indicator.textContent="▼"; }
					}
				});
			})();
		</script>';
		echo '</body></html>';
		exit;
	}
}

\add_action('template_redirect', __NAMESPACE__ . '\\cmx_render_kontakt_belege_share_page', 1);
