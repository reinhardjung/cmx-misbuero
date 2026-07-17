<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_query_key')) {
	function cmx_telefonbuch_detail_query_key(): string {
		return 'telefonbuch_kontakt';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_is_call_request')) {
	function cmx_is_call_request(): bool {
		if (\is_admin()) {
			return false;
		}

		$req_path = \parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), \PHP_URL_PATH);
		$req_path = \is_string($req_path) ? \trim($req_path, '/') : '';
		if ($req_path === 'call' || \str_starts_with($req_path, 'call/')) {
			return true;
		}

		$number = isset($_GET['number']) ? \trim((string) \wp_unslash($_GET['number'])) : '';
		if ($number !== '') {
			return true;
		}

		$query = \parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), \PHP_URL_QUERY);
		if (!\is_string($query) || $query === '') {
			return false;
		}

		$params = [];
		\parse_str($query, $params);
		return \trim((string) ($params['number'] ?? '')) !== '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_redirect_home_call_query_to_call_path')) {
	function cmx_redirect_home_call_query_to_call_path(): void {
		if (\is_admin() || \wp_doing_ajax() || \wp_doing_cron()) {
			return;
		}

		$req_path = \parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), \PHP_URL_PATH);
		$req_path = \is_string($req_path) ? \trim($req_path, '/') : '';
		if ($req_path === 'call' || \str_starts_with($req_path, 'call/')) {
			return;
		}

		$query = \parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), \PHP_URL_QUERY);
		if (!\is_string($query) || $query === '') {
			return;
		}

		$params = [];
		\parse_str($query, $params);
		if (\trim((string) ($params['number'] ?? '')) === '') {
			return;
		}

		$allowed = [];
		foreach (['number', 'account', 'dnid', 'callid'] as $key) {
			if (isset($params[$key]) && !\is_array($params[$key])) {
				$allowed[$key] = \sanitize_text_field((string) $params[$key]);
			}
		}
		if ($allowed === []) {
			return;
		}

		\wp_safe_redirect((string) \add_query_arg($allowed, \home_url('/call')), 302);
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_is_request')) {
	function cmx_telefonbuch_detail_is_request(): bool {
		$is_telefonbuch = \function_exists(__NAMESPACE__ . '\\cmx_is_telefonbuch_request') && cmx_is_telefonbuch_request();
		return $is_telefonbuch || cmx_is_call_request();
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_requested_contact_id')) {
	function cmx_telefonbuch_detail_requested_contact_id(): int {
		$keys = \array_values(\array_unique([cmx_telefonbuch_detail_query_key(), 'kontakt', 'contact', 'contact_id', 'id']));
		foreach ($keys as $key) {
			if (isset($_GET[$key])) {
				$value = (int) \wp_unslash($_GET[$key]);
				if ($value > 0) {
					return $value;
				}
			}
			if (isset($_REQUEST[$key])) {
				$value = (int) \wp_unslash($_REQUEST[$key]);
				if ($value > 0) {
					return $value;
				}
			}
		}

		$query = \parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), \PHP_URL_QUERY);
		if (\is_string($query) && $query !== '') {
			$params = [];
			\parse_str($query, $params);
			foreach ($keys as $key) {
				$value = isset($params[$key]) ? (int) $params[$key] : 0;
				if ($value > 0) {
					return $value;
				}
			}
		}

		if (\function_exists('get_query_var')) {
			$value = (int) \get_query_var(cmx_telefonbuch_detail_query_key());
			if ($value > 0) {
				return $value;
			}
		}

		return 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_valid_contact_id')) {
	function cmx_telefonbuch_detail_valid_contact_id(int $kontakt_id): int {
		if ($kontakt_id <= 0) {
			return 0;
		}

		$post = \get_post($kontakt_id);
		if (!($post instanceof \WP_Post) || (string) ($post->post_type ?? '') !== 'kontakte') {
			return 0;
		}

		return !empty($post->post_status) ? $kontakt_id : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_phone_lookup_key')) {
	function cmx_telefonbuch_detail_phone_lookup_key(string $phone): string {
		$phone = \trim($phone);
		if ($phone === '') {
			return '';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_normalize_phone')) {
			$phone = (string) cmx_kommunikation_normalize_phone($phone);
		}

		$digits = (string) \preg_replace('/\D+/', '', $phone);
		if (\str_starts_with($digits, '00')) {
			$digits = \substr($digits, 2);
		}
		if (\str_starts_with($digits, '0') && \strlen($digits) > 9) {
			$digits = \ltrim($digits, '0');
		}

		return $digits;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_phone_matches')) {
	function cmx_telefonbuch_detail_phone_matches(string $needle, string $candidate): bool {
		$needle_key = cmx_telefonbuch_detail_phone_lookup_key($needle);
		$candidate_key = cmx_telefonbuch_detail_phone_lookup_key($candidate);
		if ($needle_key === '' || $candidate_key === '') {
			return false;
		}

		if ($needle_key === $candidate_key) {
			return true;
		}

		$min_len = 7;
		return \strlen($needle_key) >= $min_len
			&& \strlen($candidate_key) >= $min_len
			&& (\str_ends_with($needle_key, $candidate_key) || \str_ends_with($candidate_key, $needle_key));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_find_contact_by_phone')) {
	function cmx_telefonbuch_detail_find_contact_by_phone(string $number): array {
		if (!\class_exists('\\WP_Query') || !\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_read_contacts')) {
			return [];
		}

		$number = \trim($number);
		if ($number === '') {
			return [];
		}

		$query = new \WP_Query([
			'post_type' => 'kontakte',
			'post_status' => ['publish', 'private', 'draft', 'pending'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'orderby' => 'title',
			'order' => 'ASC',
		]);

		foreach ((array) ($query->posts ?? []) as $kontakt_id) {
			$kontakt_id = (int) $kontakt_id;
			foreach ((array) cmx_kommunikation_read_contacts($kontakt_id) as $index => $row) {
				if (!\is_array($row) || !cmx_telefonbuch_detail_phone_matches($number, (string) ($row['telefon'] ?? ''))) {
					continue;
				}

				return [
					'kontakt_id' => $kontakt_id,
					'row_index' => (int) $index,
					'row' => $row,
				];
			}
		}

		return [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_context')) {
	function cmx_telefonbuch_detail_context(): array {
		static $context = null;
		if (\is_array($context)) {
			return $context;
		}

		$number = isset($_GET['number']) ? \sanitize_text_field((string) \wp_unslash($_GET['number'])) : '';
		$requested_id = cmx_telefonbuch_detail_requested_contact_id();
		$context = [
			'is_call' => cmx_is_call_request(),
			'number' => $number,
			'account' => isset($_GET['account']) ? \sanitize_text_field((string) \wp_unslash($_GET['account'])) : '',
			'dnid' => isset($_GET['dnid']) ? \sanitize_text_field((string) \wp_unslash($_GET['dnid'])) : '',
			'callid' => isset($_GET['callid']) ? \sanitize_text_field((string) \wp_unslash($_GET['callid'])) : '',
			'kontakt_id' => cmx_telefonbuch_detail_valid_contact_id($requested_id),
			'matched_row_index' => null,
			'matched_row' => [],
		];

		if ($context['kontakt_id'] <= 0 && $number !== '') {
			$match = cmx_telefonbuch_detail_find_contact_by_phone($number);
			$context['kontakt_id'] = (int) ($match['kontakt_id'] ?? 0);
			$context['matched_row_index'] = isset($match['row_index']) ? (int) $match['row_index'] : null;
			$context['matched_row'] = \is_array($match['row'] ?? null) ? (array) $match['row'] : [];
		}

		return $context;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_redirect_telefonbuch_legacy_detail_url')) {
	function cmx_redirect_telefonbuch_legacy_detail_url(): void {
		if (!\function_exists(__NAMESPACE__ . '\\cmx_is_telefonbuch_request') || !cmx_is_telefonbuch_request()) {
			return;
		}

		$query_key = cmx_telefonbuch_detail_query_key();
		if (isset($_GET[$query_key])) {
			return;
		}

		$legacy_kontakt = isset($_GET['kontakt']) ? (int) \wp_unslash($_GET['kontakt']) : 0;
		if ($legacy_kontakt <= 0) {
			return;
		}

		\wp_safe_redirect(cmx_telefonbuch_detail_url($legacy_kontakt), 302);
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_contact_id')) {
	function cmx_telefonbuch_detail_contact_id(): int {
		if (!cmx_telefonbuch_detail_is_request()) {
			return 0;
		}

		$kontakt_id = cmx_telefonbuch_detail_valid_contact_id(cmx_telefonbuch_detail_requested_contact_id());
		if ($kontakt_id > 0) {
			return $kontakt_id;
		}

		$context = cmx_telefonbuch_detail_context();
		return cmx_telefonbuch_detail_valid_contact_id((int) ($context['kontakt_id'] ?? 0));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_url')) {
	function cmx_telefonbuch_detail_url(int $kontakt_id): string {
		$kontakt_id = (int) $kontakt_id;
		if ($kontakt_id <= 0) {
			return (string) \home_url('/telefonbuch/');
		}

		return (string) \add_query_arg([cmx_telefonbuch_detail_query_key() => $kontakt_id], \home_url('/telefonbuch/'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_call_url')) {
	function cmx_telefonbuch_call_url(int $kontakt_id): string {
		$kontakt_id = (int) $kontakt_id;
		if ($kontakt_id <= 0) {
			return (string) \home_url('/telefonbuch/');
		}

		return (string) \add_query_arg(['kontakt' => $kontakt_id], \home_url('/call'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_note_status')) {
	function cmx_telefonbuch_detail_note_status(): string {
		return isset($_GET['cmx_note_status']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_note_status'])) : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_save_note')) {
	function cmx_telefonbuch_detail_save_note(int $kontakt_id, string $text): bool {
		if (
			$kontakt_id <= 0
			|| !\function_exists(__NAMESPACE__ . '\\cmx_notizen_load_rows')
			|| !\function_exists(__NAMESPACE__ . '\\cmx_notizen_normalize_row')
			|| !\function_exists(__NAMESPACE__ . '\\cmx_notizen_now_date')
			|| !\function_exists(__NAMESPACE__ . '\\cmx_notizen_now_time')
			|| !\function_exists(__NAMESPACE__ . '\\cmx_notizen_meta_key_for_post_type')
			|| !\function_exists(__NAMESPACE__ . '\\cmx_notizen_legacy_meta_keys')
		) {
			return false;
		}

		$rows = (array) cmx_notizen_load_rows($kontakt_id, 'kontakte');
		$row = cmx_notizen_normalize_row([
			'betreff' => '',
			'datum'   => cmx_notizen_now_date(),
			'zeit'    => cmx_notizen_now_time(),
			'text'    => $text,
			'quelle'  => '',
		]);
		if (!\is_array($row) || $row === []) {
			return false;
		}

		\array_unshift($rows, $row);

		$meta_key = (string) cmx_notizen_meta_key_for_post_type('kontakte');
		\update_post_meta($kontakt_id, $meta_key, $rows);
		foreach ((array) cmx_notizen_legacy_meta_keys('kontakte') as $legacy_key) {
			\delete_post_meta($kontakt_id, (string) $legacy_key);
		}

		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_handle_telefonbuch_detail_post_actions')) {
	function cmx_handle_telefonbuch_detail_post_actions(): void {
		if (!cmx_telefonbuch_detail_is_request()) {
			return;
		}

		if (\strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
			return;
		}

		$kontakt_id = cmx_telefonbuch_detail_contact_id();
		if ($kontakt_id <= 0) {
			return;
		}

		$action = isset($_POST['cmx_telefonbuch_detail_action']) ? \sanitize_key((string) \wp_unslash($_POST['cmx_telefonbuch_detail_action'])) : '';
		if (!\in_array($action, ['add_note'], true)) {
			return;
		}

		$redirect_url = cmx_telefonbuch_detail_url($kontakt_id);
		if (!\current_user_can('edit_post', $kontakt_id)) {
			\wp_safe_redirect((string) \add_query_arg('cmx_note_status', 'denied', $redirect_url), 303);
			exit;
		}

		$nonce = isset($_POST['cmx_telefonbuch_note_nonce']) ? (string) \wp_unslash($_POST['cmx_telefonbuch_note_nonce']) : '';
		if (!\wp_verify_nonce($nonce, 'cmx_telefonbuch_add_note_' . $kontakt_id)) {
			\wp_safe_redirect((string) \add_query_arg('cmx_note_status', 'invalid', $redirect_url), 303);
			exit;
		}

		$text = isset($_POST['cmx_telefonbuch_note_text']) ? (string) \wp_unslash($_POST['cmx_telefonbuch_note_text']) : '';
		$text = \trim($text);
		if ($text === '') {
			\wp_safe_redirect((string) \add_query_arg('cmx_note_status', 'empty', $redirect_url), 303);
			exit;
		}

		$status = cmx_telefonbuch_detail_save_note($kontakt_id, $text) ? 'saved' : 'error';
		\wp_safe_redirect((string) \add_query_arg('cmx_note_status', $status, $redirect_url), 303);
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_format_date')) {
	function cmx_telefonbuch_detail_format_date(string $date): string {
		$date = \trim($date);
		if ($date === '') {
			return '';
		}

		$dt = \DateTime::createFromFormat('Y-m-d', $date);
		return $dt instanceof \DateTime ? $dt->format('d.m.Y') : $date;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_render_html_text')) {
	function cmx_telefonbuch_detail_render_html_text(string $text): string {
		$text = \trim($text);
		if ($text === '') {
			return '';
		}

		$allowed = [
			'a' => [
				'href' => true,
				'title' => true,
				'target' => true,
				'rel' => true,
			],
			'br' => [],
			'strong' => [],
			'em' => [],
			'b' => [],
			'i' => [],
			'u' => [],
		];

		return \nl2br(\wp_kses($text, $allowed));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_categories_taxonomy')) {
	function cmx_telefonbuch_detail_categories_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_kundenkategorie_tax')) {
			$tax = \trim((string) cmx_kundenkategorie_tax());
			if ($tax !== '' && \taxonomy_exists($tax)) {
				return $tax;
			}
		}

		foreach (['kontakte_kategorien', 'kontakte_kategorie', 'kundenkategorie', 'kontakt_kategorie'] as $tax) {
			if (\taxonomy_exists($tax)) {
				return $tax;
			}
		}

		foreach ((array) \get_object_taxonomies('kontakte', 'names') as $tax) {
			$tax = (string) $tax;
			if ($tax !== '' && \stripos($tax, 'kategorie') !== false) {
				return $tax;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_stufe_taxonomy')) {
	function cmx_telefonbuch_detail_stufe_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_stufen_tax')) {
			$tax = \trim((string) cmx_stufen_tax());
			if ($tax !== '' && \taxonomy_exists($tax)) {
				return $tax;
			}
		}

		foreach (['kontakte_stufen', 'stufen', 'kontakte_stufe', 'kontakt_stufen'] as $tax) {
			if (\taxonomy_exists($tax)) {
				return $tax;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_term_names')) {
	function cmx_telefonbuch_detail_term_names(int $post_id, string $taxonomy): array {
		$taxonomy = \trim($taxonomy);
		if ($post_id <= 0 || $taxonomy === '' || !\taxonomy_exists($taxonomy)) {
			return [];
		}

		$terms = \get_the_terms($post_id, $taxonomy);
		if (\is_wp_error($terms) || empty($terms)) {
			return [];
		}

		$names = [];
		foreach ((array) $terms as $term) {
			$name = \trim((string) ($term->name ?? ''));
			if ($name !== '') {
				$names[] = $name;
			}
		}

		return \array_values(\array_unique($names));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_people')) {
	function cmx_telefonbuch_detail_people(int $post_id, ?int $only_index = null): array {
		$rows = \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_read_contacts')
			? (array) cmx_kommunikation_read_contacts($post_id)
			: [];
		$primary_phone = '';
		if (
			\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_phone')
			&& \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_normalize_phone')
		) {
			$primary_phone = (string) cmx_kommunikation_normalize_phone((string) cmx_kommunikation_primary_phone($post_id));
		}

		$people = [];
		$has_primary_match = false;
		foreach ($rows as $index => $row) {
			if (!\is_array($row)) {
				continue;
			}
			if ($only_index !== null && (int) $index !== $only_index) {
				continue;
			}

			$vorname = \trim((string) ($row['vorname'] ?? ''));
			$nachname = \trim((string) ($row['nachname'] ?? ''));
			$name = \trim($vorname . ' ' . $nachname);
			$birthdate = cmx_telefonbuch_detail_format_date((string) ($row['geburtsdatum'] ?? ''));
			$phone = '';
			if (\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_normalize_phone')) {
				$phone = (string) cmx_kommunikation_normalize_phone((string) ($row['telefon'] ?? ''));
			}
			$phone_display = \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_format_phone_display')
				? (string) cmx_kommunikation_format_phone_display($phone !== '' ? $phone : (string) ($row['telefon'] ?? ''))
				: \trim((string) ($row['telefon'] ?? $phone));
			$email = \sanitize_email((string) ($row['email'] ?? ''));
			$is_primary = $primary_phone !== '' && $phone !== '' && $phone === $primary_phone;
			if ($is_primary) {
				$has_primary_match = true;
			}

			if ($name === '' && $birthdate === '') {
				continue;
			}

			$people[] = [
				'name' => $name !== '' ? $name : '(ohne Namen)',
				'birthdate' => $birthdate,
				'phone_href' => $phone !== '' ? 'tel:' . \preg_replace('/\s+/', '', $phone) : '',
				'phone_display' => $phone_display,
				'email' => $email,
				'email_href' => $email !== '' ? 'mailto:' . $email : '',
				'is_primary' => $only_index !== null || $is_primary,
				'index' => (int) $index,
			];
		}

		if (!$has_primary_match && isset($people[0])) {
			$people[0]['is_primary'] = true;
		}

		return $people;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_latest_note')) {
	function cmx_telefonbuch_detail_latest_note(int $post_id): array {
		if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_load_rows')) {
			return [];
		}

		$rows = (array) cmx_notizen_load_rows($post_id, 'kontakte');
		if ($rows === []) {
			return [];
		}

		$items = [];
		foreach (\array_slice($rows, 0, 5, true) as $index => $row) {
			$row = \is_array($row) ? (array) $row : [];
			if ($row === []) {
				continue;
			}

			$item = [
				'index' => (int) $index,
				'betreff' => \trim((string) ($row['betreff'] ?? '')),
				'datum' => cmx_telefonbuch_detail_format_date((string) ($row['datum'] ?? '')),
				'zeit' => \trim((string) ($row['zeit'] ?? '')),
				'text' => \trim((string) ($row['text'] ?? '')),
			];

			if ($item['betreff'] === '' && $item['datum'] === '' && $item['zeit'] === '' && $item['text'] === '') {
				continue;
			}

			$items[] = $item;
		}

		return $items;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_project_ids')) {
	function cmx_telefonbuch_detail_project_ids(int $kontakt_id): array {
		if (!\class_exists('\\WP_Query') || $kontakt_id <= 0 || !\post_type_exists('projekte')) {
			return [];
		}

		$kontakt_keys = ['_cmx_projekt_kontakt_id', 'cmx_projekt_kontakt_id', '_cmx_kontakt_id', 'kontakt_id'];
		if (\defined(__NAMESPACE__ . '\\CMX_KONTAKT_META')) {
			$kontakt_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKT_META');
		}
		$kontakt_keys = \array_values(\array_unique(\array_filter($kontakt_keys)));

		$meta_or = ['relation' => 'OR'];
		foreach ($kontakt_keys as $key) {
			$meta_or[] = [
				'key' => $key,
				'value' => $kontakt_id,
				'compare' => '=',
				'type' => 'NUMERIC',
			];
		}

		$query = new \WP_Query([
			'post_type' => 'projekte',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'orderby' => 'date',
			'order' => 'DESC',
			'meta_query' => [$meta_or],
		]);

		return \array_values(\array_filter(\array_map('intval', (array) ($query->posts ?? []))));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_projects')) {
	function cmx_telefonbuch_detail_projects(int $kontakt_id): array {
		$projects = [];
		foreach (cmx_telefonbuch_detail_project_ids($kontakt_id) as $project_id) {
			$title = \trim((string) \get_the_title($project_id));
			$projects[] = [
				'id' => $project_id,
				'title' => $title !== '' ? $title : ('#' . $project_id),
				'url' => (string) \get_edit_post_link($project_id, ''),
				'date' => cmx_telefonbuch_detail_format_date((string) \get_the_date('Y-m-d', $project_id)),
				'status' => \trim((string) \get_post_status($project_id)),
			];
		}

		return $projects;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_normalize_tasks_for_source')) {
	function cmx_telefonbuch_detail_normalize_tasks_for_source(int $source_id, string $source_title = '', string $source_url = ''): array {
		$tasks = \get_post_meta($source_id, '_cmx_projekt_tasks', true);
		if (!\is_array($tasks) || $tasks === []) {
			return [];
		}

		$normalized = [];
		foreach ($tasks as $index => $task) {
			if (!\is_array($task)) {
				continue;
			}

			$date = \trim((string) ($task['datum'] ?? ''));
			$time = \trim((string) ($task['zeit'] ?? ''));
			$info = \trim((string) ($task['info'] ?? ''));
			$artikel_id = (int) ($task['artikel_id'] ?? 0);
			$artikel = $artikel_id > 0 ? \trim((string) \get_the_title($artikel_id)) : '';
			$produkt_id = (int) ($task['produkt_id'] ?? 0);
			$produkt = $produkt_id > 0 ? \trim((string) \get_the_title($produkt_id)) : '';
			$dauer = \trim((string) ($task['dauer'] ?? ''));

			if ($date === '' && $time === '' && $info === '' && $artikel === '' && $produkt === '' && $dauer === '') {
				continue;
			}

			$normalized[] = [
				'index' => (int) $index,
				'source_id' => $source_id,
				'source_title' => $source_title,
				'source_url' => $source_url,
				'sort' => ($date !== '' ? $date : '0000-00-00') . ' ' . ($time !== '' ? $time : '00:00'),
				'datum' => cmx_telefonbuch_detail_format_date($date),
				'zeit' => $time,
				'info' => $info,
				'artikel' => $artikel,
				'produkt' => $produkt,
				'dauer' => $dauer,
			];
		}

		return $normalized;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_latest_task')) {
	function cmx_telefonbuch_detail_latest_task(int $post_id): array {
		$normalized = cmx_telefonbuch_detail_normalize_tasks_for_source($post_id, 'Kontakt', '');
		foreach (cmx_telefonbuch_detail_projects($post_id) as $project) {
			$project_id = (int) ($project['id'] ?? 0);
			$normalized = \array_merge(
				$normalized,
				cmx_telefonbuch_detail_normalize_tasks_for_source(
					$project_id,
					(string) ($project['title'] ?? ''),
					(string) ($project['url'] ?? '')
				)
			);
		}

		if ($normalized === []) {
			return [];
		}

		\usort($normalized, static function (array $a, array $b): int {
			$cmp = \strcmp((string) ($b['sort'] ?? ''), (string) ($a['sort'] ?? ''));
			if ($cmp !== 0) {
				return $cmp;
			}
			return ((int) ($a['index'] ?? 0)) <=> ((int) ($b['index'] ?? 0));
		});

		return \array_slice($normalized, 0, 5);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_beleg_currency')) {
	function cmx_telefonbuch_detail_beleg_currency(int $beleg_id): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_currency')) {
			$currency = \trim((string) cmx_kontakt_beleg_currency($beleg_id));
			if ($currency !== '') {
				return $currency;
			}
		}

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

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_beleg_amount_value')) {
	function cmx_telefonbuch_detail_beleg_amount_value(int $beleg_id): ?float {
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_amount_display')) {
			$display = \trim((string) cmxbu_get_beleg_amount_display($beleg_id));
			if ($display !== '') {
				$normalized = (string) \preg_replace('/[^0-9,.\'-]+/u', '', $display);
				$normalized = \str_replace([' ', "'"], '', $normalized);
				if (\substr_count($normalized, ',') === 1 && \substr_count($normalized, '.') === 0) {
					$normalized = \str_replace(',', '.', $normalized);
				} else {
					$normalized = \str_replace(',', '', $normalized);
				}
				if (\is_numeric($normalized)) {
					return (float) $normalized;
				}
			}
		}

		$total = null;
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
			$calc = (array) cmxbu_get_beleg_positionen_calc($beleg_id);
			if (isset($calc['total']) && \is_numeric($calc['total'])) {
				$total = (float) $calc['total'];
			}
		}

		if ($total === null) {
			$override = \trim((string) \get_post_meta($beleg_id, '_cmx_beleg_summe_override', true));
			if ($override !== '') {
				$normalized = \str_replace([' ', "'"], '', $override);
				$normalized = \str_replace(',', '.', $normalized);
				if (\is_numeric($normalized)) {
					$total = (float) $normalized;
				}
			}
		}

		return $total;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_beleg_amount_label')) {
	function cmx_telefonbuch_detail_beleg_amount_label(int $beleg_id): string {
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_amount_display')) {
			$display = \trim((string) cmxbu_get_beleg_amount_display($beleg_id));
			if ($display !== '') {
				$display = (string) \preg_replace('/\s+[A-Z]{3}$/u', '', $display);
				return \trim($display);
			}
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_amount_label')) {
			$amount = \trim((string) cmx_kontakt_beleg_amount_label($beleg_id));
			if ($amount !== '') {
				return \trim($amount . ' ' . cmx_telefonbuch_detail_beleg_currency($beleg_id));
			}
		}

		$total = cmx_telefonbuch_detail_beleg_amount_value($beleg_id);
		if ($total === null) {
			return '';
		}

		$formatted = \function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')
			? (string) cmx_format_swiss_number($total, 2)
			: \number_format($total, 2, '.', "'");

		return \trim($formatted . ' ' . cmx_telefonbuch_detail_beleg_currency($beleg_id));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_beleg_send_url')) {
	function cmx_telefonbuch_detail_beleg_send_url(int $beleg_id): string {
		$beleg_id = (int) $beleg_id;
		if ($beleg_id <= 0) {
			return '';
		}

		return (string) \add_query_arg(
			[
				'action' => 'cmxbu_beleg_send',
				'post_id' => $beleg_id,
			],
			\admin_url('admin-post.php')
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_latest_belege')) {
	function cmx_telefonbuch_detail_latest_belege(int $post_id): array {
		if (!\class_exists('\\WP_Query') || $post_id <= 0) {
			return [];
		}

		$kontakt_keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID')) {
			$kontakt_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID');
		}
		$kontakt_keys = \array_values(\array_unique(\array_filter(\array_merge($kontakt_keys, ['_cmx_beleg_kontakt_id', 'cmx_beleg_kontakt_id']))));
		if ($kontakt_keys === []) {
			return [];
		}

		$meta_or = ['relation' => 'OR'];
		foreach ($kontakt_keys as $key) {
			$meta_or[] = [
				'key' => $key,
				'value' => $post_id,
				'compare' => '=',
				'type' => 'NUMERIC',
			];
		}

		$query = new \WP_Query([
			'post_type' => \defined(__NAMESPACE__ . '\\CMX_PT_BELEGE') ? (string) \constant(__NAMESPACE__ . '\\CMX_PT_BELEGE') : 'belege',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'orderby' => 'date',
			'order' => 'DESC',
			'meta_query' => [$meta_or],
		]);

		$beleg_ids = \array_values(\array_filter(\array_map('intval', (array) ($query->posts ?? []))));
		if ($beleg_ids === []) {
			return [];
		}

		$paid_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM')
			: '_cmx_beleg_bezahlt_am';

		$items = [];
		foreach ($beleg_ids as $beleg_id) {
			$paid_raw = \trim((string) \get_post_meta($beleg_id, $paid_key, true));
			if ($paid_raw === '' && $paid_key !== '_cmx_beleg_bezahlt_am') {
				$paid_raw = \trim((string) \get_post_meta($beleg_id, '_cmx_beleg_bezahlt_am', true));
			}

			$items[] = [
				'id' => $beleg_id,
				'title' => \trim((string) \get_the_title($beleg_id)) ?: ('#' . $beleg_id),
				'url' => (string) \get_edit_post_link($beleg_id, ''),
				'send_url' => cmx_telefonbuch_detail_beleg_send_url($beleg_id),
				'date' => cmx_telefonbuch_detail_format_date((string) \get_the_date('Y-m-d', $beleg_id)),
				'amount' => cmx_telefonbuch_detail_beleg_amount_label($beleg_id),
				'amount_value' => cmx_telefonbuch_detail_beleg_amount_value($beleg_id),
				'currency' => cmx_telefonbuch_detail_beleg_currency($beleg_id),
				'is_paid' => $paid_raw !== '',
			];
		}

		$open_items = [];
		$paid_items = [];
		foreach ($items as $item) {
			if (!empty($item['is_paid'])) {
				$paid_items[] = $item;
				continue;
			}
			$open_items[] = $item;
		}

		return \array_merge($open_items, $paid_items);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_belege_total_label')) {
	function cmx_telefonbuch_detail_belege_total_label(array $belege): string {
		$totals = [];
		foreach ($belege as $beleg) {
			if (!isset($beleg['amount_value']) || !\is_numeric($beleg['amount_value'])) {
				continue;
			}
			$currency = \strtoupper(\trim((string) ($beleg['currency'] ?? 'CHF')));
			if ($currency === '') {
				$currency = 'CHF';
			}
			if (!isset($totals[$currency])) {
				$totals[$currency] = 0.0;
			}
			$totals[$currency] += (float) $beleg['amount_value'];
		}

		if ($totals === []) {
			return '';
		}

		$parts = [];
		foreach ($totals as $currency => $total) {
			$formatted = \function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')
				? (string) cmx_format_swiss_number((float) $total, 2)
				: \number_format((float) $total, 2, '.', "'");
			$parts[] = \trim($formatted . ' ' . $currency);
		}

		return \implode(' / ', $parts);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_telefonbuch_detail_not_found_page')) {
	function cmx_render_telefonbuch_detail_not_found_page(): void {
		$context = cmx_telefonbuch_detail_context();
		$number = \trim((string) ($context['number'] ?? ''));
		$back_url = (string) \home_url('/telefonbuch/');

		while (\ob_get_level()) {
			\ob_end_clean();
		}

		if (!\defined('DONOTCACHEPAGE')) {
			\define('DONOTCACHEPAGE', true);
		}
		\nocache_headers();
		\status_header(404);

		echo '<!doctype html><html lang="de"><head><meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>Kontakt nicht gefunden - Telefonbuch</title>';
		echo '<style>
			*{box-sizing:border-box}
			body{margin:0;font-family:Segoe UI,Roboto,Arial,sans-serif;background:#efefef;color:#1d2327}
			a{color:#135e96}
			.cmx-telefonbuch-detail-page{max-width:860px;margin:0 auto;padding:32px 18px 40px}
			.cmx-telefonbuch-detail-card{background:#fff;border:1px solid #ddd;border-radius:14px;box-shadow:0 18px 40px rgba(0,0,0,.06);overflow:hidden}
			.cmx-telefonbuch-detail-head{padding:24px 28px;background:linear-gradient(135deg,#f7f7f7 0%,#ededed 100%);border-bottom:1px solid #e2e2e2}
			.cmx-telefonbuch-detail-kicker{margin:0 0 8px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280}
			.cmx-telefonbuch-detail-kicker a{color:inherit;text-decoration:none}
			.cmx-telefonbuch-detail-title{margin:0;font-size:30px;line-height:1.1}
			.cmx-telefonbuch-detail-body{padding:22px 28px 28px;display:grid;gap:14px}
			.cmx-telefonbuch-detail-empty{color:#6b7280;line-height:1.55}
		</style>';
		echo '</head><body>';
		echo '<div class="cmx-telefonbuch-detail-page"><div class="cmx-telefonbuch-detail-card">';
		echo '<div class="cmx-telefonbuch-detail-head">';
		echo '<p class="cmx-telefonbuch-detail-kicker"><a href="' . \esc_url($back_url) . '">Telefonbuch</a></p>';
		echo '<h1 class="cmx-telefonbuch-detail-title">Kontakt nicht gefunden</h1>';
		echo '</div>';
		echo '<div class="cmx-telefonbuch-detail-body">';
		if ($number !== '') {
			echo '<p class="cmx-telefonbuch-detail-empty">Zur Telefonnummer ' . \esc_html($number) . ' wurde kein Kontakt gefunden.</p>';
		} else {
			echo '<p class="cmx-telefonbuch-detail-empty">Es wurde kein Kontakt fuer diesen Aufruf gefunden.</p>';
		}
		echo '<p><a href="' . \esc_url($back_url) . '">Zurueck zum Telefonbuch</a></p>';
		echo '</div></div></div>';
		echo '</body></html>';
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_telefonbuch_detail_page')) {
	function cmx_render_telefonbuch_detail_page(): void {
		if (!cmx_telefonbuch_detail_is_request()) {
			return;
		}

		$kontakt_id = cmx_telefonbuch_detail_contact_id();
		if ($kontakt_id <= 0) {
			if (cmx_is_call_request()) {
				cmx_render_telefonbuch_detail_not_found_page();
			}
			return;
		}

		$title = \trim((string) \get_the_title($kontakt_id));
		if ($title === '') {
			$title = '#' . $kontakt_id;
		}

		$firmengruendung_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_FIRMENGRUENDUNG')
			? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_FIRMENGRUENDUNG')
			: '_cmx_kontakte_firmengruendung';
		$firmengruendung = cmx_telefonbuch_detail_format_date((string) \get_post_meta($kontakt_id, $firmengruendung_key, true));
		$kunde_seit_raw = \function_exists(__NAMESPACE__ . '\\cmx_kontakt_kunde_seit_value')
			? (string) cmx_kontakt_kunde_seit_value($kontakt_id)
			: (string) \get_post_meta(
				$kontakt_id,
				\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_KUNDE_SEIT')
					? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_KUNDE_SEIT')
					: '_cmx_kontakte_kunde_seit',
				true
			);
		$kunde_seit = cmx_telefonbuch_detail_format_date($kunde_seit_raw);
		$geburtsdatum_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_GEBURTSDATUM')
			? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_GEBURTSDATUM')
			: '_cmx_kontakte_geburtsdatum';
		$geburtsdatum = cmx_telefonbuch_detail_format_date((string) \get_post_meta($kontakt_id, $geburtsdatum_key, true));
		$stufen = cmx_telefonbuch_detail_term_names($kontakt_id, cmx_telefonbuch_detail_stufe_taxonomy());
		$kategorien = cmx_telefonbuch_detail_term_names($kontakt_id, cmx_telefonbuch_detail_categories_taxonomy());
		$context = cmx_telefonbuch_detail_context();
		$is_call_page = !empty($context['is_call']);
		$call_number = \trim((string) ($context['number'] ?? ''));
		$matched_index = $is_call_page && $call_number !== '' && isset($context['matched_row_index']) ? (int) $context['matched_row_index'] : null;
		$people = cmx_telefonbuch_detail_people($kontakt_id);
		$latest_notes = cmx_telefonbuch_detail_latest_note($kontakt_id);
		$latest_tasks = cmx_telefonbuch_detail_latest_task($kontakt_id);
		$all_belege = cmx_telefonbuch_detail_latest_belege($kontakt_id);
		$latest_belege = $is_call_page ? $all_belege : \array_slice($all_belege, 0, 5);
		$belege_total = $is_call_page ? cmx_telefonbuch_detail_belege_total_label($all_belege) : '';
		$projects = $is_call_page ? cmx_telefonbuch_detail_projects($kontakt_id) : [];
		$back_url = (string) \home_url('/telefonbuch/');
		$edit_url = (string) \get_edit_post_link($kontakt_id, '');
		$can_add_note = \current_user_can('edit_post', $kontakt_id);
		$note_status = cmx_telefonbuch_detail_note_status();
		$maps_address = \function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_address_string')
			? (string) cmx_telefonbuch_address_string($kontakt_id)
			: '';
		$maps_url = \function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_maps_url')
			? (string) cmx_telefonbuch_maps_url($maps_address)
			: '';
		$switch_url = $is_call_page ? cmx_telefonbuch_detail_url($kontakt_id) : cmx_telefonbuch_call_url($kontakt_id);
		$switch_label = $is_call_page ? 'Zur Telefonbuch-Ansicht wechseln' : 'Zur Call-Ansicht wechseln';
		$switch_icon = \function_exists(__NAMESPACE__ . '\\cmx_icon') ? (string) cmx_icon('swatch-book') : '';
		if ($switch_icon !== '') {
			$switch_icon = (string) \preg_replace('/<svg\b/', '<svg aria-hidden="true" focusable="false"', $switch_icon, 1);
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
		echo '<title>' . \esc_html($title) . ' - Telefonbuch</title>';
		echo '<style>
			:root{color-scheme:light}
			*{box-sizing:border-box}
			body{margin:0;font-family:Segoe UI,Roboto,Arial,sans-serif;background:#efefef;color:#1d2327}
			a{color:#135e96}
			.cmx-telefonbuch-detail-page{max-width:1180px;margin:0 auto;padding:32px 18px 40px}
			.cmx-telefonbuch-detail-card{background:#fff;border:1px solid #ddd;border-radius:14px;box-shadow:0 18px 40px rgba(0,0,0,.06);overflow:hidden}
			.cmx-telefonbuch-detail-head{padding:24px 28px 22px;background:linear-gradient(135deg,#f7f7f7 0%,#ededed 100%);border-bottom:1px solid #e2e2e2;display:flex;justify-content:space-between;gap:24px;align-items:flex-start}
			.cmx-telefonbuch-detail-kicker{margin:0 0 8px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280}
			.cmx-telefonbuch-detail-kicker a{color:inherit;text-decoration:none}
			.cmx-telefonbuch-detail-title{margin:0;font-size:30px;line-height:1.1}
			.cmx-telefonbuch-detail-title a{color:inherit;text-decoration:none}
			.cmx-telefonbuch-detail-title a:hover{text-decoration:underline}
			.cmx-telefonbuch-detail-sub{margin:8px 0 0;color:#6b7280;font-size:14px}
			.cmx-telefonbuch-detail-head-actions{display:flex;align-items:flex-start;justify-content:flex-end;gap:10px;flex:0 0 auto;flex-wrap:wrap}
			.cmx-telefonbuch-detail-action{display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;padding:0;border-radius:999px;border:1px solid #d7e3ee;background:#f7fbff;color:#135e96;text-decoration:none;flex:0 0 auto}
			.cmx-telefonbuch-detail-action:hover{background:#e9f4ff;border-color:#bdd7ee;color:#0a4b79}
			.cmx-telefonbuch-detail-action svg{display:block;width:30px;height:30px;color:currentColor}
			.cmx-telefonbuch-map-link{display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:999px;border:1px solid #d7e3ee;background:#f7fbff;color:#135e96;text-decoration:none;flex:0 0 auto}
			.cmx-telefonbuch-map-link:hover{background:#e9f4ff;color:#0a4b79;border-color:#bdd7ee}
			.cmx-telefonbuch-map-icon{display:block;width:30px;height:30px;fill:currentColor}
			.cmx-telefonbuch-detail-body{padding:22px 28px 28px;display:grid;gap:18px}
			.cmx-telefonbuch-detail-meta{display:flex;flex-wrap:wrap;gap:10px}
			.cmx-telefonbuch-detail-pill{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:999px;background:#f4f6f8;border:1px solid #dde3e8;font-size:13px}
			.cmx-telefonbuch-detail-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:18px}
			.cmx-telefonbuch-detail-box{border:1px solid #e8ecef;border-radius:12px;background:#fafbfc;padding:16px}
			.cmx-telefonbuch-detail-box h3{margin:0 0 10px;font-size:15px}
			.cmx-telefonbuch-detail-box-title-button{appearance:none;border:0;background:none;padding:0;margin:0;font:inherit;font-weight:inherit;color:inherit;cursor:pointer}
			.cmx-telefonbuch-detail-box-title-button:hover{text-decoration:underline}
			.cmx-telefonbuch-detail-box-head{display:flex;justify-content:space-between;gap:12px;align-items:baseline;margin:0 0 10px}
			.cmx-telefonbuch-detail-box-head-note{margin-bottom:2px}
			.cmx-telefonbuch-detail-box-head h3{margin:0;font-size:15px}
			.cmx-telefonbuch-detail-empty{color:#6b7280}
			.cmx-telefonbuch-detail-notice{padding:12px 14px;border-radius:10px;border:1px solid #dbe6d2;background:#f3fbef;color:#137333;font-weight:600}
			.cmx-telefonbuch-detail-notice.is-error{border-color:#f2d1d1;background:#fff5f5;color:#c62828}
			.cmx-telefonbuch-detail-people{margin:0;padding:0;list-style:none;display:grid;gap:8px}
			.cmx-telefonbuch-detail-people li{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:8px 10px;border-radius:10px;background:#fff;border:1px solid #e9edf0}
			.cmx-telefonbuch-detail-people li.is-current{background:#f5ebe1;border-color:#ead9c7}
			.cmx-telefonbuch-detail-people a{color:inherit;text-decoration:none}
			.cmx-telefonbuch-detail-people a:hover{text-decoration:underline}
			.cmx-telefonbuch-detail-people strong{font-weight:700}
			.cmx-telefonbuch-detail-person-name{min-width:0}
			.cmx-telefonbuch-detail-person-actions{display:inline-flex;align-items:center;gap:8px;margin-left:auto;flex:0 0 auto}
			.cmx-telefonbuch-detail-person-action{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;border:1px solid #cfd7df;background:#fff;color:#135e96;text-decoration:none}
			.cmx-telefonbuch-detail-person-action:hover{background:#eef5fb;border-color:#bdd7ee;color:#0a4b79}
			.cmx-telefonbuch-detail-person-action svg{display:block;width:15px;height:15px;fill:currentColor}
			.cmx-telefonbuch-detail-date{color:#667085;white-space:nowrap}
			.cmx-telefonbuch-detail-text{color:#344054;line-height:1.55}
			.cmx-telefonbuch-detail-entry-list{display:grid;gap:12px}
			.cmx-telefonbuch-detail-entry + .cmx-telefonbuch-detail-entry{padding-top:12px;border-top:1px solid #e8ecef}
			.cmx-telefonbuch-detail-entry-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:6px}
			.cmx-telefonbuch-detail-entry-date{display:block;text-align:right}
			.cmx-telefonbuch-detail-line{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
			.cmx-telefonbuch-detail-date-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
			.cmx-telefonbuch-detail-date-card{padding:12px;border-radius:10px;background:#fff;border:1px solid #e9edf0}
			.cmx-telefonbuch-detail-date-card strong{display:block;margin-bottom:5px;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#667085}
			.cmx-telefonbuch-detail-date-card span{display:block;min-height:20px;font-weight:700}
			.cmx-telefonbuch-detail-task-row{display:flex;justify-content:space-between;gap:12px;align-items:baseline}
			.cmx-telefonbuch-detail-task-info{color:#344054;line-height:1.55;min-width:0;padding-right:12px}
			.cmx-telefonbuch-detail-line + .cmx-telefonbuch-detail-line{margin-top:8px}
			.cmx-telefonbuch-detail-label{color:#667085;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
			.cmx-telefonbuch-detail-state-paid{color:#137333;font-weight:700}
			.cmx-telefonbuch-detail-state-open{color:#c62828;font-weight:700}
			.cmx-telefonbuch-detail-belege-list{display:grid;gap:10px}
			.cmx-telefonbuch-detail-beleg-row{display:flex;justify-content:space-between;gap:14px;align-items:center;font-weight:700}
			.cmx-telefonbuch-detail-beleg-meta{display:flex;flex-wrap:wrap;gap:12px;align-items:center;min-width:0}
			.cmx-telefonbuch-detail-beleg-id{min-width:0}
			.cmx-telefonbuch-detail-beleg-id a{font-weight:700;text-decoration:none}
			.cmx-telefonbuch-detail-beleg-date{white-space:nowrap}
			.cmx-telefonbuch-detail-beleg-actions{display:flex;align-items:center;gap:10px;margin-left:auto}
			.cmx-telefonbuch-detail-beleg-amount{white-space:nowrap;text-align:right}
			.cmx-telefonbuch-detail-beleg-send{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;border:1px solid #135e96;color:#135e96;background:#fff;text-decoration:none;transition:background-color .15s ease,border-color .15s ease,color .15s ease,opacity .15s ease}
			.cmx-telefonbuch-detail-beleg-send:hover{background:#eef5fb;border-color:#0f4d7d;color:#0f4d7d}
			.cmx-telefonbuch-detail-beleg-send.is-sending{opacity:.45;pointer-events:none}
			.cmx-telefonbuch-detail-beleg-send svg{display:block;width:15px;height:15px;fill:currentColor}
			.cmx-telefonbuch-detail-beleg-feedback{display:none;margin-top:10px;font-size:13px;font-weight:600}
			.cmx-telefonbuch-detail-beleg-feedback.is-visible{display:block}
			.cmx-telefonbuch-detail-beleg-feedback.is-success{color:#137333}
			.cmx-telefonbuch-detail-beleg-feedback.is-error{color:#c62828}
			.cmx-telefonbuch-detail-beleg-feedback a{color:inherit;text-decoration:underline;font-weight:700}
			.cmx-telefonbuch-detail-note-modal[hidden]{display:none !important}
			.cmx-telefonbuch-detail-note-modal{position:fixed;inset:0;z-index:999999;display:flex;align-items:center;justify-content:center;padding:20px}
			.cmx-telefonbuch-detail-note-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.46)}
			.cmx-telefonbuch-detail-note-dialog{position:relative;width:min(560px,100%);background:#fff;border:1px solid #d0d7de;border-radius:14px;box-shadow:0 24px 60px rgba(0,0,0,.22);padding:18px}
			.cmx-telefonbuch-detail-note-dialog h3{margin:0 0 12px;font-size:18px}
			.cmx-telefonbuch-detail-note-dialog textarea{width:100%;min-height:180px;padding:12px;border:1px solid #c8c8c8;border-radius:10px;font:inherit;resize:vertical}
			.cmx-telefonbuch-detail-note-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:14px}
			.cmx-telefonbuch-detail-note-actions .button{
				display:inline-flex;
				align-items:center;
				justify-content:center;
				min-height:36px;
				padding:0 14px;
				border-radius:8px;
				border:1px solid #c3c4c7;
				background:#f6f7f7;
				color:#1d2327;
				box-shadow:none;
				font:inherit;
				font-size:14px;
				font-weight:500;
				line-height:1;
				text-decoration:none;
				cursor:pointer;
				transition:background-color .15s ease,border-color .15s ease,color .15s ease;
			}
			.cmx-telefonbuch-detail-note-actions .button:hover,
			.cmx-telefonbuch-detail-note-actions .button:focus{
				background:#fff;
				border-color:#8c8f94;
				color:#1d2327;
				outline:none;
			}
			.cmx-telefonbuch-detail-note-actions .button.button-primary{
				padding:0 16px;
				border-color:#a42c24;
				background:#a42c24;
				color:#fff;
			}
			.cmx-telefonbuch-detail-note-actions .button.button-primary:hover,
			.cmx-telefonbuch-detail-note-actions .button.button-primary:focus{
				background:#8f2420;
				border-color:#8f2420;
				color:#fff;
			}
			@media (max-width:820px){
				.cmx-telefonbuch-detail-page{padding:18px 12px 24px}
				.cmx-telefonbuch-detail-head{padding:18px 16px;align-items:flex-start}
				.cmx-telefonbuch-detail-head-actions{margin-left:auto;align-self:flex-start}
				.cmx-telefonbuch-detail-body{padding:16px}
				.cmx-telefonbuch-detail-grid{grid-template-columns:1fr}
				.cmx-telefonbuch-detail-title{font-size:24px}
				.cmx-telefonbuch-detail-date-grid{grid-template-columns:1fr}
				.cmx-telefonbuch-detail-line,.cmx-telefonbuch-detail-beleg-row,.cmx-telefonbuch-detail-task-row,.cmx-telefonbuch-detail-box-head{flex-direction:column;align-items:flex-start}
				.cmx-telefonbuch-detail-entry-head{align-items:flex-start}
				.cmx-telefonbuch-detail-beleg-actions{
					margin-left:0;
					width:auto;
					align-self:flex-end;
					justify-content:flex-end;
					flex-wrap:nowrap;
					white-space:nowrap;
				}
			}
		</style>';
		echo '</head><body>';
		echo '<div class="cmx-telefonbuch-detail-page"><div class="cmx-telefonbuch-detail-card">';
		echo '<div class="cmx-telefonbuch-detail-head">';
		echo '<div>';
		echo '<p class="cmx-telefonbuch-detail-kicker"><a href="' . \esc_url($back_url) . '">Telefonbuch</a></p>';
		if ($edit_url !== '') {
			echo '<h1 class="cmx-telefonbuch-detail-title"><a href="' . \esc_url($edit_url) . '">' . \esc_html($title) . '</a></h1>';
		} else {
			echo '<h1 class="cmx-telefonbuch-detail-title">' . \esc_html($title) . '</h1>';
		}
		if ($firmengruendung !== '') {
			echo '<p class="cmx-telefonbuch-detail-sub">Firmengründung: ' . \esc_html($firmengruendung) . '</p>';
		}
		echo '</div>';
		if ($is_call_page && ($edit_url !== '' || $maps_url !== '' || $switch_url !== '')) {
			echo '<div class="cmx-telefonbuch-detail-head-actions">';
			if ($edit_url !== '') {
				echo '<a class="cmx-telefonbuch-detail-action" href="' . \esc_url($edit_url) . '" title="' . \esc_attr('Kontakt bearbeiten') . '" aria-label="' . \esc_attr('Kontakt bearbeiten') . '">';
				echo '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 17.25V20h2.75L17.81 8.94l-2.75-2.75L4 17.25ZM19.71 7.04a1 1 0 0 0 0-1.41l-1.34-1.34a1 1 0 0 0-1.41 0l-1.05 1.05 2.75 2.75 1.05-1.05Z"/></svg>';
				echo '</a>';
			}
			if ($maps_url !== '') {
				echo '<a class="cmx-telefonbuch-detail-action" href="' . \esc_url($maps_url) . '" target="_blank" rel="noopener noreferrer" title="' . \esc_attr('Route in Google Maps öffnen: ' . $maps_address) . '" aria-label="' . \esc_attr('Route in Google Maps öffnen') . '">';
				echo '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.34 6.1 12.15 6.36 12.44a.86.86 0 0 0 1.28 0C12.9 21.15 19 14.34 19 9a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5Z"/></svg>';
				echo '</a>';
			}
			if ($switch_url !== '') {
				echo '<a class="cmx-telefonbuch-detail-action" href="' . \esc_url($switch_url) . '" title="' . \esc_attr($switch_label) . '" aria-label="' . \esc_attr($switch_label) . '">';
				echo $switch_icon !== '' ? $switch_icon : '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 4h14v5H5V4Zm0 7h14v9H5v-9Z"/></svg>';
				echo '</a>';
			}
			echo '</div>';
		} elseif ($maps_url !== '' || $switch_url !== '') {
			echo '<div class="cmx-telefonbuch-detail-head-actions">';
			if ($maps_url !== '') {
				echo '<a class="cmx-telefonbuch-map-link" href="' . \esc_url($maps_url) . '" target="_blank" rel="noopener noreferrer" title="' . \esc_attr('In Google Maps öffnen: ' . $maps_address) . '" aria-label="' . \esc_attr('Adresse in Google Maps öffnen') . '">';
				echo '<svg class="cmx-telefonbuch-map-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.34 6.1 12.15 6.36 12.44a.86.86 0 0 0 1.28 0C12.9 21.15 19 14.34 19 9a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5Z"/></svg>';
				echo '</a>';
			}
			if ($switch_url !== '') {
				echo '<a class="cmx-telefonbuch-detail-action" href="' . \esc_url($switch_url) . '" title="' . \esc_attr($switch_label) . '" aria-label="' . \esc_attr($switch_label) . '">';
				echo $switch_icon !== '' ? $switch_icon : '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 4h14v5H5V4Zm0 7h14v9H5v-9Z"/></svg>';
				echo '</a>';
			}
			echo '</div>';
		}
		echo '</div>';
		echo '<div class="cmx-telefonbuch-detail-body">';

		echo '<div class="cmx-telefonbuch-detail-meta">';
		if (!empty($context['is_call']) && \trim((string) ($context['number'] ?? '')) !== '') {
			echo '<span class="cmx-telefonbuch-detail-pill"><strong>Anruf</strong> ' . \esc_html((string) $context['number']) . '</span>';
		}
		if ($stufen !== []) {
			echo '<span class="cmx-telefonbuch-detail-pill"><strong>Stufe</strong> ' . \esc_html(\implode(', ', $stufen)) . '</span>';
		}
		if ($kategorien !== []) {
			echo '<span class="cmx-telefonbuch-detail-pill"><strong>Kategorien</strong> ' . \esc_html(\implode(', ', $kategorien)) . '</span>';
		}
		if ($stufen === [] && $kategorien === []) {
			echo '<span class="cmx-telefonbuch-detail-empty">Keine Stufe oder Kategorien hinterlegt.</span>';
		}
		echo '</div>';

		if ($is_call_page) {
			echo '<div class="cmx-telefonbuch-detail-box">';
			echo '<h3>Stammdaten</h3>';
			echo '<div class="cmx-telefonbuch-detail-date-grid">';
			echo '<div class="cmx-telefonbuch-detail-date-card"><strong>Kunde seit</strong><span>' . \esc_html($kunde_seit) . '</span></div>';
			echo '<div class="cmx-telefonbuch-detail-date-card"><strong>Firmengründung</strong><span>' . \esc_html($firmengruendung) . '</span></div>';
			echo '<div class="cmx-telefonbuch-detail-date-card"><strong>Geburtsdatum</strong><span>' . \esc_html($geburtsdatum) . '</span></div>';
			echo '</div>';
			echo '</div>';
		}

		if ($is_call_page && $maps_address !== '') {
			echo '<div class="cmx-telefonbuch-detail-box">';
			echo '<h3>Rechnungsanschrift</h3>';
			echo '<div class="cmx-telefonbuch-detail-text">' . \nl2br(\esc_html($maps_address)) . '</div>';
			echo '</div>';
		}

		if ($projects !== []) {
			echo '<div class="cmx-telefonbuch-detail-box">';
			echo '<h3>Projekte</h3>';
			echo '<div class="cmx-telefonbuch-detail-entry-list">';
			foreach ($projects as $project) {
				$url = \trim((string) ($project['url'] ?? ''));
				$title_html = \esc_html((string) ($project['title'] ?? ''));
				if ($url !== '') {
					$title_html = '<a href="' . \esc_url($url) . '">' . $title_html . '</a>';
				}
				echo '<div class="cmx-telefonbuch-detail-line">';
				echo '<strong>' . $title_html . '</strong>';
				if (\trim((string) ($project['date'] ?? '')) !== '') {
					echo '<span class="cmx-telefonbuch-detail-date">' . \esc_html((string) $project['date']) . '</span>';
				}
				echo '</div>';
			}
			echo '</div>';
			echo '</div>';
		}

		if ($note_status !== '') {
			$is_note_error = \in_array($note_status, ['denied', 'invalid', 'empty', 'error'], true);
			$note_notice_text = 'Interne Notiz wurde gespeichert.';
			if ($note_status === 'empty') {
				$note_notice_text = 'Bitte gib zuerst einen Text für die interne Notiz ein.';
			} elseif ($note_status === 'denied') {
				$note_notice_text = 'Du darfst diesen Kontakt nicht bearbeiten.';
			} elseif ($note_status === 'invalid') {
				$note_notice_text = 'Die Notiz konnte wegen einer ungueltigen Anfrage nicht gespeichert werden.';
			} elseif ($note_status === 'error') {
				$note_notice_text = 'Die interne Notiz konnte nicht gespeichert werden.';
			}
			echo '<div class="cmx-telefonbuch-detail-notice' . ($is_note_error ? ' is-error' : '') . '">' . \esc_html($note_notice_text) . '</div>';
		}

		echo '<div class="cmx-telefonbuch-detail-grid">';

		echo '<div class="cmx-telefonbuch-detail-box">';
		echo '<h3>Ansprechpartner</h3>';
		if ($people === []) {
			echo '<p class="cmx-telefonbuch-detail-empty">Keine Ansprechpartner mit Namen gefunden.</p>';
		} else {
			echo '<ul class="cmx-telefonbuch-detail-people">';
			foreach ($people as $person) {
				$is_call_match = $matched_index !== null && (int) ($person['index'] ?? -1) === $matched_index;
				$item_class = $is_call_match ? ' class="is-current"' : '';
				$name = (string) ($person['name'] ?? '');
				$phone_href = \trim((string) ($person['phone_href'] ?? ''));
				if ($phone_href !== '') {
					$name_html = '<a href="' . \esc_url($phone_href) . '">' . \esc_html($name) . '</a>';
				} else {
					$name_html = \esc_html($name);
				}
				echo '<li' . $item_class . '>';
				if ($is_call_match) {
					echo '<strong class="cmx-telefonbuch-detail-person-name">' . $name_html . '</strong>';
				} else {
					echo '<span class="cmx-telefonbuch-detail-person-name">' . $name_html . '</span>';
				}
				$phone_display = \trim((string) ($person['phone_display'] ?? ''));
				$email = \trim((string) ($person['email'] ?? ''));
				$email_href = \trim((string) ($person['email_href'] ?? ''));
				if ($phone_href !== '' || $email_href !== '') {
					echo '<span class="cmx-telefonbuch-detail-person-actions">';
					if ($phone_href !== '') {
						echo '<a class="cmx-telefonbuch-detail-person-action" href="' . \esc_url($phone_href) . '" title="' . \esc_attr($phone_display !== '' ? $phone_display : $phone_href) . '" aria-label="' . \esc_attr('Anrufen: ' . ($phone_display !== '' ? $phone_display : $phone_href)) . '">';
						echo '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.62 10.79c1.44 2.83 3.76 5.15 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24c1.12.37 2.33.56 3.57.56a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.61 21 3 13.39 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.24.19 2.45.56 3.57a1 1 0 0 1-.24 1.02l-2.2 2.2Z"/></svg>';
						echo '</a>';
					}
					if ($email_href !== '') {
						echo '<a class="cmx-telefonbuch-detail-person-action" href="' . \esc_url($email_href) . '" title="' . \esc_attr($email) . '" aria-label="' . \esc_attr('E-Mail schreiben: ' . $email) . '">';
						echo '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2Zm0 2v.2l8 5.2 8-5.2V8H4Zm16 8v-5.4l-7.5 4.9a1 1 0 0 1-1 0L4 10.6V16h16Z"/></svg>';
						echo '</a>';
					}
					echo '</span>';
				}
				$birthdate = \trim((string) ($person['birthdate'] ?? ''));
				if ($birthdate !== '') {
					echo '<span class="cmx-telefonbuch-detail-date">' . \esc_html($birthdate) . '</span>';
				}
				echo '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';

		echo '<div class="cmx-telefonbuch-detail-box">';
		echo '<h3>' . ($is_call_page ? 'Belege' : 'Letzte Belege') . ($belege_total !== '' ? ' · Total ' . \esc_html($belege_total) : '') . '</h3>';
		if ($latest_belege === []) {
			echo '<p class="cmx-telefonbuch-detail-empty">Kein Beleg verknüpft.</p>';
		} else {
			echo '<div class="cmx-telefonbuch-detail-belege-list">';
			foreach ($latest_belege as $latest_beleg) {
				$url = \trim((string) ($latest_beleg['url'] ?? ''));
				$title_html = \esc_html((string) ($latest_beleg['title'] ?? ''));
				if ($url !== '') {
					$title_html = '<a href="' . \esc_url($url) . '">' . $title_html . '</a>';
				}
				$amount = \trim((string) ($latest_beleg['amount'] ?? ''));
				$send_url = \trim((string) ($latest_beleg['send_url'] ?? ''));
				$state_class = !empty($latest_beleg['is_paid']) ? 'cmx-telefonbuch-detail-state-paid' : 'cmx-telefonbuch-detail-state-open';
				echo '<div class="cmx-telefonbuch-detail-beleg-row ' . \esc_attr($state_class) . '">';
				echo '<div class="cmx-telefonbuch-detail-beleg-meta">';
				echo '<span class="cmx-telefonbuch-detail-beleg-id">' . $title_html . '</span>';
				echo '<span class="cmx-telefonbuch-detail-beleg-date">' . \esc_html((string) ($latest_beleg['date'] ?? '')) . '</span>';
				echo '</div>';
				if ($amount !== '' || $send_url !== '') {
					echo '<div class="cmx-telefonbuch-detail-beleg-actions">';
					if ($amount !== '') {
						echo '<span class="cmx-telefonbuch-detail-beleg-amount">' . \esc_html($amount) . '</span>';
					}
					if ($send_url !== '') {
						echo '<a class="cmx-telefonbuch-detail-beleg-send" data-cmx-beleg-send="1" href="' . \esc_url($send_url) . '" title="Beleg erneut per E-Mail senden" aria-label="Beleg erneut per E-Mail senden">';
						echo '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 6h16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm0 2v.2l8 5.2 8-5.2V8H4zm16 8V10.6l-7.5 4.9a1 1 0 0 1-1 0L4 10.6V16h16z"/></svg>';
						echo '</a>';
					}
					echo '</div>';
				}
				echo '</div>';
			}
			echo '</div>';
			echo '<div class="cmx-telefonbuch-detail-beleg-feedback" data-cmx-beleg-feedback="1"></div>';
		}
		echo '</div>';

		echo '<div class="cmx-telefonbuch-detail-box">';
		if ($can_add_note) {
			echo '<h3><button type="button" class="cmx-telefonbuch-detail-box-title-button" data-cmx-note-open="1">' . ($is_call_page ? 'Interne Notizen' : 'Neueste Interne Notiz') . '</button></h3>';
		} else {
			echo '<h3>' . ($is_call_page ? 'Interne Notizen' : 'Neueste Interne Notiz') . '</h3>';
		}
		if ($latest_notes === []) {
			echo '<p class="cmx-telefonbuch-detail-empty">Keine interne Notiz vorhanden.</p>';
		} else {
			echo '<div class="cmx-telefonbuch-detail-entry-list">';
			foreach ($latest_notes as $latest_note) {
				$latest_note_date = \trim((string) (($latest_note['datum'] ?? '') . ' ' . ($latest_note['zeit'] ?? '')));
				echo '<div class="cmx-telefonbuch-detail-entry">';
				if ($latest_note_date !== '') {
					echo '<div class="cmx-telefonbuch-detail-entry-head">';
					echo '<div class="cmx-telefonbuch-detail-date cmx-telefonbuch-detail-entry-date">' . \esc_html($latest_note_date) . '</div>';
					echo '</div>';
				}
				echo '<div class="cmx-telefonbuch-detail-text">' . cmx_telefonbuch_detail_render_html_text((string) ($latest_note['text'] ?? '')) . '</div>';
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';

		echo '<div class="cmx-telefonbuch-detail-box">';
		echo '<h3>' . ($is_call_page ? 'Tätigkeiten' : 'Neueste Tätigkeit') . '</h3>';
		if ($latest_tasks === []) {
			echo '<p class="cmx-telefonbuch-detail-empty">Keine Tätigkeit vorhanden.</p>';
		} else {
			echo '<div class="cmx-telefonbuch-detail-entry-list">';
			foreach ($latest_tasks as $latest_task) {
				$latest_task_date = \trim((string) (($latest_task['datum'] ?? '') . ' ' . ($latest_task['zeit'] ?? '')));
				$task_meta = [];
				if (\trim((string) ($latest_task['artikel'] ?? '')) !== '') {
					$task_meta[] = \esc_html((string) $latest_task['artikel']);
				}
				if (\trim((string) ($latest_task['produkt'] ?? '')) !== '') {
					$task_meta[] = \esc_html((string) $latest_task['produkt']);
				}
				if (\trim((string) ($latest_task['dauer'] ?? '')) !== '') {
					$task_meta[] = \esc_html('Dauer: ' . (string) $latest_task['dauer'] . ' h');
				}
				if (\trim((string) ($latest_task['source_title'] ?? '')) !== '' && (string) ($latest_task['source_title'] ?? '') !== 'Kontakt') {
					$source_title = (string) $latest_task['source_title'];
					$source_url = \trim((string) ($latest_task['source_url'] ?? ''));
					$task_meta[] = $source_url !== ''
						? '<a href="' . \esc_url($source_url) . '">' . \esc_html($source_title) . '</a>'
						: \esc_html($source_title);
				}
				echo '<div class="cmx-telefonbuch-detail-entry">';
				if ($task_meta !== [] || $latest_task_date !== '') {
					echo '<div class="cmx-telefonbuch-detail-task-row">';
					echo '<div>';
					if ($task_meta !== []) {
						echo '<div>' . \implode(' · ', $task_meta) . '</div>';
					}
					echo '</div>';
					if ($latest_task_date !== '') {
						echo '<div class="cmx-telefonbuch-detail-date">' . \esc_html($latest_task_date) . '</div>';
					}
					echo '</div>';
				}
				echo '<div class="cmx-telefonbuch-detail-task-info">' . cmx_telefonbuch_detail_render_html_text((string) ($latest_task['info'] ?? '')) . '</div>';
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';

		echo '</div>';
		if ($can_add_note) {
			echo '<div class="cmx-telefonbuch-detail-note-modal" data-cmx-note-modal="1" hidden>';
			echo '<div class="cmx-telefonbuch-detail-note-backdrop" data-cmx-note-close="1"></div>';
			echo '<div class="cmx-telefonbuch-detail-note-dialog" role="dialog" aria-modal="true" aria-labelledby="cmx-telefonbuch-note-title">';
			echo '<form method="post" action="' . \esc_url(cmx_telefonbuch_detail_url($kontakt_id)) . '">';
			echo '<h3 id="cmx-telefonbuch-note-title">Neue Interne Notiz</h3>';
			echo '<input type="hidden" name="cmx_telefonbuch_detail_action" value="add_note">';
			echo '<textarea name="cmx_telefonbuch_note_text" data-cmx-note-text="1" placeholder="Text eingeben ..." required></textarea>';
			echo '<div class="cmx-telefonbuch-detail-note-actions">';
			echo '<button type="button" class="button button-secondary" data-cmx-note-close="1">Abbrechen</button>';
			\wp_nonce_field('cmx_telefonbuch_add_note_' . $kontakt_id, 'cmx_telefonbuch_note_nonce');
			echo '<button type="submit" class="button button-primary">Speichern</button>';
			echo '</div>';
			echo '</form>';
			echo '</div>';
			echo '</div>';
		}
		echo '</div></div></div>';
		echo '<script>
			document.addEventListener("click", function(event) {
				const belegeSettingsUrl = ' . \wp_json_encode((string) \admin_url('admin.php?page=cmx-einstellungen&tab=belege')) . ';
				const trigger = event.target.closest("[data-cmx-beleg-send]");
				if (!trigger) {
					return;
				}
				const url = trigger.getAttribute("href") || "";
				if (!url || trigger.classList.contains("is-sending")) {
					return;
				}
				event.preventDefault();
				const box = trigger.closest(".cmx-telefonbuch-detail-box");
				const feedback = box ? box.querySelector("[data-cmx-beleg-feedback]") : null;
				const setFeedback = function(text, type, allowHtml) {
					if (!feedback) {
						return;
					}
					if (allowHtml) {
						feedback.innerHTML = text;
					} else {
						feedback.textContent = text;
					}
					feedback.className = "cmx-telefonbuch-detail-beleg-feedback is-visible";
					if (type === "success") {
						feedback.classList.add("is-success");
					} else if (type === "error") {
						feedback.classList.add("is-error");
					}
				};
				const normalizeText = function(text) {
					return (text || "").replace(/\\s+/g, " ").trim();
				};
				const escapeHtml = function(value) {
					return String(value || "")
						.replace(/&/g, "&amp;")
						.replace(/</g, "&lt;")
						.replace(/>/g, "&gt;")
						.replace(/"/g, "&quot;")
						.split(String.fromCharCode(39)).join("&#039;");
				};
				const linkifyErrorText = function(text) {
					let html = escapeHtml(normalizeText(text));
					html = html.replace(
						/Einstellungen &gt; Belege/g,
						"<a href=\\"" + belegeSettingsUrl + "\\" target=\\"_blank\\" rel=\\"noopener noreferrer\\">Einstellungen &gt; Belege</a>"
					);
					return html;
				};
				const extractNoticeText = function(html, selector) {
					if (!html) {
						return "";
					}
					const parser = new window.DOMParser();
					const doc = parser.parseFromString(html, "text/html");
					const node = doc.querySelector(selector);
					if (!node) {
						return "";
					}
					const clone = node.cloneNode(true);
					clone.querySelectorAll("a,button,script,style").forEach(function(child) {
						child.remove();
					});
					return normalizeText(clone.textContent || "");
				};
				trigger.classList.add("is-sending");
				setFeedback("Beleg wird versendet ...", "");
				window.fetch(url, {
					credentials: "same-origin",
					redirect: "follow"
				}).then(function(response) {
					const finalUrl = response.url || url;
					const params = new URL(finalUrl, window.location.origin).searchParams;
					return response.text().then(function(html) {
						if (params.get("cmx_beleg_mail_sent") === "1") {
							const successText = extractNoticeText(html, ".notice-success p");
							setFeedback(successText || "Beleg per E-Mail versendet.", "success");
							return;
						}
						if (params.get("cmx_beleg_mail_missing_sender") === "1") {
							setFeedback("Bitte hinterlege zuerst Deine E-Mail-Adresse.", "error");
							return;
						}
						if (params.get("cmx_beleg_mail_error") === "1") {
							const errorText = extractNoticeText(html, ".notice-error p");
							setFeedback(
								linkifyErrorText(errorText || "E-Mail konnte nicht vorbereitet oder gesendet werden. Bitte prüfe Deine Vorlagen sowie Deine SMTP-/Alias-Einstellungen."),
								"error",
								true
							);
							return;
						}
						if (!response.ok) {
							throw new Error("send_failed");
						}
						const successText = extractNoticeText(html, ".notice-success p");
						setFeedback(successText || "Versand ausgelöst.", "success");
					});
				}).catch(function() {
					setFeedback("E-Mail konnte nicht vorbereitet oder gesendet werden.", "error");
				}).finally(function() {
					trigger.classList.remove("is-sending");
				});
			});
		</script>';
		if ($can_add_note) {
			echo '<script>
				(function() {
					const modal = document.querySelector("[data-cmx-note-modal]");
					if (!modal) {
						return;
					}
					const textarea = modal.querySelector("[data-cmx-note-text]");
					const openModal = function() {
						modal.hidden = false;
						document.body.style.overflow = "hidden";
						window.setTimeout(function() {
							if (textarea) {
								textarea.focus();
								textarea.setSelectionRange(textarea.value.length, textarea.value.length);
							}
						}, 10);
					};
					const closeModal = function() {
						modal.hidden = true;
						document.body.style.overflow = "";
					};
					document.addEventListener("click", function(event) {
						const openTrigger = event.target.closest("[data-cmx-note-open]");
						if (openTrigger) {
							event.preventDefault();
							openModal();
							return;
						}
						const closeTrigger = event.target.closest("[data-cmx-note-close]");
						if (closeTrigger) {
							event.preventDefault();
							closeModal();
						}
					});
					document.addEventListener("keydown", function(event) {
						if (event.key === "Escape" && !modal.hidden) {
							event.preventDefault();
							closeModal();
						}
					});
				})();
			</script>';
		}
		echo '</body></html>';
		exit;
	}
}

\add_action('init', __NAMESPACE__ . '\\cmx_redirect_home_call_query_to_call_path', 1);
\add_action('template_redirect', __NAMESPACE__ . '\\cmx_redirect_home_call_query_to_call_path', -100);
\add_action('template_redirect', __NAMESPACE__ . '\\cmx_redirect_telefonbuch_legacy_detail_url', 0);
\add_action('template_redirect', __NAMESPACE__ . '\\cmx_handle_telefonbuch_detail_post_actions', 2);
\add_action('template_redirect', __NAMESPACE__ . '\\cmx_render_telefonbuch_detail_page', 2);
