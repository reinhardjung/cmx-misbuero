<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_KONTAKTE_CARDDAV_TOKEN_OPTION = 'cmx_kontakte_carddav_token';
const CMX_KONTAKTE_CARDDAV_EXTRA_META = '_cmx_carddav_extra_vcard_lines';
const CMX_KONTAKTE_CARDDAV_CARD_META = '_cmx_carddav_card_filename';

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_require_dependencies')) {
	function cmx_kontakte_carddav_require_dependencies(): void {
		foreach (['stammdaten.php', 'kommunikation.php', 'adressen.php'] as $file) {
			$path = __DIR__ . '/' . $file;
			if (\is_file($path)) {
				require_once $path;
			}
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_token')) {
	function cmx_kontakte_carddav_token(): string {
		$token = \trim((string) \get_option(CMX_KONTAKTE_CARDDAV_TOKEN_OPTION, ''));
		if ($token !== '') {
			return $token;
		}

		$token = \wp_generate_password(48, false, false);
		\update_option(CMX_KONTAKTE_CARDDAV_TOKEN_OPTION, $token, false);
		return $token;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_url')) {
	function cmx_kontakte_carddav_url(): string {
		return (string) \trailingslashit(\home_url('/cmx-carddav/' . \rawurlencode(cmx_kontakte_carddav_token())));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_account_url')) {
	function cmx_kontakte_carddav_account_url(): string {
		return (string) \trailingslashit(\home_url('/cmx-carddav/'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_principal_url')) {
	function cmx_kontakte_carddav_principal_url(): string {
		return (string) \trailingslashit(\home_url('/cmx-carddav/principals/kontakte/'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_url_path')) {
	function cmx_kontakte_carddav_url_path(string $url): string {
		$path = (string) \wp_parse_url($url, \PHP_URL_PATH);
		return \trailingslashit('/' . \trim($path, '/'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_account_path')) {
	function cmx_kontakte_carddav_account_path(): string {
		return cmx_kontakte_carddav_url_path(cmx_kontakte_carddav_account_url());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_principal_path')) {
	function cmx_kontakte_carddav_principal_path(): string {
		return cmx_kontakte_carddav_url_path(cmx_kontakte_carddav_principal_url());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_card_path')) {
	function cmx_kontakte_carddav_card_path(int $post_id): string {
		return cmx_kontakte_carddav_addressbook_path() . cmx_kontakte_carddav_card_filename($post_id);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_addressbook_path')) {
	function cmx_kontakte_carddav_addressbook_path(): string {
		return cmx_kontakte_carddav_account_path() . 'kontakte/';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_category_account_path')) {
	function cmx_kontakte_carddav_category_account_path(int $term_id): string {
		return cmx_kontakte_carddav_account_path() . 'category-' . $term_id . '/';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_category_card_path')) {
	function cmx_kontakte_carddav_category_card_path(int $term_id, int $post_id): string {
		return cmx_kontakte_carddav_category_account_path($term_id) . cmx_kontakte_carddav_card_filename($post_id);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_response_card_path')) {
	function cmx_kontakte_carddav_response_card_path(int $post_id, int $category_term_id = 0): string {
		return $category_term_id > 0
			? cmx_kontakte_carddav_category_card_path($category_term_id, $post_id)
			: cmx_kontakte_carddav_card_path($post_id);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_group_path')) {
	function cmx_kontakte_carddav_group_path(int $term_id): string {
		return cmx_kontakte_carddav_addressbook_path() . 'category-' . $term_id . '.vcf';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_username')) {
	function cmx_kontakte_carddav_username(): string {
		return 'kontakte';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_host')) {
	function cmx_kontakte_carddav_host(): string {
		$host = (string) \wp_parse_url(cmx_kontakte_carddav_account_url(), \PHP_URL_HOST);
		if ($host !== '') {
			return $host;
		}
		return \strtolower(\trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '')));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_mobileconfig_url')) {
	function cmx_kontakte_carddav_mobileconfig_url(): string {
		return (string) \wp_nonce_url(
			\admin_url('admin-post.php?action=cmx_kontakte_carddav_mobileconfig'),
			'cmx_kontakte_carddav_mobileconfig'
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_card_url')) {
	function cmx_kontakte_carddav_card_url(int $post_id): string {
		return (string) \trailingslashit(cmx_kontakte_carddav_account_url()) . 'kontakte/' . cmx_kontakte_carddav_card_filename($post_id);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_handle_request')) {
	function cmx_kontakte_carddav_handle_request(): void {
		$request = cmx_kontakte_carddav_request();
		if ($request === []) {
			return;
		}

		cmx_kontakte_carddav_require_dependencies();

		if (!empty($request['well_known'])) {
			\wp_safe_redirect(cmx_kontakte_carddav_account_url(), 301);
			exit;
		}

		$method = \strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
		cmx_kontakte_carddav_common_headers();

		if ($method === 'OPTIONS') {
			\status_header(204);
			exit;
		}

		$provided = \trim((string) ($request['token'] ?? ''));
		if ($provided === '') {
			$provided = cmx_kontakte_carddav_basic_password();
		}
		$expected = cmx_kontakte_carddav_token();
		if ($provided === '' || !\hash_equals($expected, $provided)) {
			\status_header($provided === '' ? 401 : 403);
			\header('WWW-Authenticate: Basic realm="Mis Buero Kontakte"');
			\header('Content-Type: text/plain; charset=utf-8');
			\header('Content-Length: 0');
			exit;
		}

		$card = \sanitize_file_name((string) ($request['card'] ?? ''));

		if (!empty($request['principal'])) {
			if ($method === 'PROPFIND') {
				cmx_kontakte_carddav_send_principal_multistatus();
				exit;
			}
			\status_header(405);
			exit;
		}

		if (!empty($request['root'])) {
			if ($method === 'PROPFIND' || $method === 'REPORT') {
				cmx_kontakte_carddav_send_root_multistatus();
				exit;
			}
			if ($method === 'HEAD') {
				\status_header(204);
				exit;
			}
		}

		if (!empty($request['home'])) {
			if ($method === 'PROPFIND' || $method === 'REPORT') {
				cmx_kontakte_carddav_send_home_multistatus();
				exit;
			}
			if ($method === 'HEAD') {
				\status_header(204);
				exit;
			}
			\status_header(405);
			exit;
		}

		$category_term_id = (int) ($request['category'] ?? 0);

		if ($method === 'PROPFIND' || $method === 'REPORT') {
			cmx_kontakte_carddav_send_multistatus($card, $method === 'REPORT', $category_term_id);
			exit;
		}

		if ($method === 'PUT') {
			if ($card === '') {
				\status_header(405);
				\header('Allow: OPTIONS, GET, HEAD, PUT, POST, DELETE, PROPFIND, REPORT');
				exit;
			}

			$post_id = cmx_kontakte_carddav_card_id($card);
			$term_id = cmx_kontakte_carddav_group_id($card);
			if ($term_id > 0 && $category_term_id <= 0) {
				$body = (string) \file_get_contents('php://input');
				if (\trim($body) === '' || !cmx_kontakte_carddav_save_group_vcard($term_id, $body)) {
					\status_header(400);
					exit;
				}

				\status_header(204);
				\header('ETag: "' . cmx_kontakte_carddav_group_etag($term_id) . '"');
				exit;
			}
			if ($post_id <= 0 || \get_post_type($post_id) !== 'kontakte') {
				$body = (string) \file_get_contents('php://input');
				$post_id = cmx_kontakte_carddav_create_contact_from_vcard($body, $card, $category_term_id);
				if ($post_id <= 0) {
					\status_header(400);
					exit;
				}

				\clean_post_cache($post_id);
				\status_header(201);
				\header('Location: ' . cmx_kontakte_carddav_response_card_path($post_id, $category_term_id));
				\header('Content-Location: ' . cmx_kontakte_carddav_response_card_path($post_id, $category_term_id));
				\header('ETag: "' . cmx_kontakte_carddav_etag($post_id) . '"');
				exit;
			}

			$body = (string) \file_get_contents('php://input');
			if (\trim($body) === '' || !cmx_kontakte_carddav_save_vcard($post_id, $body)) {
				\status_header(400);
				exit;
			}

			\clean_post_cache($post_id);
			\status_header(204);
			\header('ETag: "' . cmx_kontakte_carddav_etag($post_id) . '"');
			exit;
		}

		if ($method === 'POST') {
			if ($card !== '') {
				\status_header(405);
				\header('Allow: OPTIONS, GET, HEAD, PUT, POST, DELETE, PROPFIND, REPORT');
				exit;
			}

			$body = (string) \file_get_contents('php://input');
			$slug = cmx_kontakte_carddav_post_slug_header();
			$post_id = cmx_kontakte_carddav_create_contact_from_vcard($body, $slug, $category_term_id);
			if ($post_id <= 0) {
				\status_header(400);
				exit;
			}

			\clean_post_cache($post_id);
			\status_header(201);
			\header('Location: ' . cmx_kontakte_carddav_response_card_path($post_id, $category_term_id));
			\header('Content-Location: ' . cmx_kontakte_carddav_response_card_path($post_id, $category_term_id));
			\header('ETag: "' . cmx_kontakte_carddav_etag($post_id) . '"');
			exit;
		}

		if ($method === 'DELETE') {
			if ($card === '') {
				\status_header(405);
				\header('Allow: OPTIONS, GET, HEAD, PUT, POST, DELETE, PROPFIND, REPORT');
				exit;
			}

			$post_id = cmx_kontakte_carddav_card_id($card);
			if ($post_id <= 0 || \get_post_type($post_id) !== 'kontakte') {
				\status_header(404);
				exit;
			}

			if ($category_term_id > 0) {
				cmx_kontakte_carddav_remove_contact_category($post_id, $category_term_id);
			} else {
				\wp_trash_post($post_id);
			}

			\status_header(204);
			exit;
		}

		if ($method !== 'GET' && $method !== 'HEAD') {
			\status_header(405);
			\header('Allow: OPTIONS, GET, HEAD, PUT, POST, DELETE, PROPFIND, REPORT');
			exit;
		}

		if ($card !== '') {
			$post_id = cmx_kontakte_carddav_card_id($card);
			$term_id = cmx_kontakte_carddav_group_id($card);
			if ($term_id > 0 && $category_term_id <= 0) {
				$vcard = cmx_kontakte_carddav_group_vcard($term_id);
				if ($vcard === '') {
					\status_header(404);
					exit;
				}

				\header('Content-Type: text/vcard; charset=utf-8');
				\header('Content-Disposition: inline; filename="' . \sanitize_file_name($card) . '"');
				\header('ETag: "' . cmx_kontakte_carddav_group_etag($term_id) . '"');
				if ($method !== 'HEAD') {
					echo $vcard;
				}
				exit;
			}
			if ($post_id <= 0 || \get_post_type($post_id) !== 'kontakte') {
				\status_header(404);
				exit;
			}
			if ($category_term_id > 0 && !\in_array($post_id, cmx_kontakte_carddav_group_contact_ids($category_term_id), true)) {
				\status_header(404);
				exit;
			}

			\header('Content-Type: text/vcard; charset=utf-8');
			\header('Content-Disposition: inline; filename="' . cmx_kontakte_carddav_card_filename($post_id) . '"');
			\header('ETag: "' . cmx_kontakte_carddav_etag($post_id) . '"');
			if ($method !== 'HEAD') {
				echo cmx_kontakte_carddav_vcard($post_id);
			}
			exit;
		}

		\header('Content-Type: text/vcard; charset=utf-8');
		\header('Content-Disposition: inline; filename="misbuero-kontakte.vcf"');
		if ($method !== 'HEAD') {
			echo cmx_kontakte_carddav_all_vcards($category_term_id);
		}
		exit;
	}
}
\add_action('init', __NAMESPACE__ . '\\cmx_kontakte_carddav_handle_request', 30);
\add_action('template_redirect', __NAMESPACE__ . '\\cmx_kontakte_carddav_handle_request', 0);
\add_action('admin_post_cmx_kontakte_carddav_mobileconfig', __NAMESPACE__ . '\\cmx_kontakte_carddav_send_mobileconfig');

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_request')) {
	function cmx_kontakte_carddav_request(): array {
		$path = (string) \wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), \PHP_URL_PATH);
		$path = \trim($path, '/');
		$home_path = (string) \wp_parse_url(\home_url('/'), \PHP_URL_PATH);
		$home_path = \trim($home_path, '/');
		if ($home_path !== '' && ($path === $home_path || \str_starts_with($path, $home_path . '/'))) {
			$path = \trim((string) \substr($path, \strlen($home_path)), '/');
		}

		$method = \strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
		if ($path === '' && \in_array($method, ['OPTIONS', 'HEAD', 'PROPFIND', 'REPORT'], true)) {
			return [
				'token' => '',
				'root'  => true,
			];
		}

		if ($path === '.well-known/carddav') {
			return ['well_known' => true];
		}

		if ($path === 'cmx-carddav') {
			return [
				'token' => '',
				'home'  => true,
			];
		}

		if ($path === 'cmx-carddav/kontakte') {
			return [
				'token' => '',
				'card'  => '',
			];
		}

		if (\preg_match('#^cmx-carddav/category-(\d+)(?:/([^/]+))?/?$#', $path, $matches)) {
			return [
				'token'    => '',
				'category' => (int) ($matches[1] ?? 0),
				'card'     => isset($matches[2]) ? \sanitize_file_name((string) $matches[2]) : '',
			];
		}

		if ($path === 'cmx-carddav/principals/kontakte') {
			return [
				'token'     => '',
				'principal' => true,
			];
		}

		if (\preg_match('#^cmx-carddav/([^/]+)/?$#', $path, $matches)) {
			$first = \rawurldecode((string) ($matches[1] ?? ''));
			if (\preg_match('/\.vcf$/i', $first)) {
				return [
					'token' => '',
					'card'  => \sanitize_file_name($first),
				];
			}
			return [
				'token' => $first,
				'card'  => '',
			];
		}

		if (\preg_match('#^cmx-carddav/kontakte/([^/]+)(?:/([^/]+))?/?$#', $path, $matches)) {
			$first = \rawurldecode((string) ($matches[1] ?? ''));
			$second = \sanitize_file_name((string) ($matches[2] ?? ''));
			if (\preg_match('/\.vcf$/i', $first)) {
				return [
					'token' => '',
					'card'  => \sanitize_file_name($first),
				];
			}
			return [
				'token' => $first,
				'card'  => $second,
			];
		}

		if (isset($_GET['cmx_carddav']) && (string) \wp_unslash($_GET['cmx_carddav']) === 'kontakte') {
			return [
				'token' => isset($_GET['token']) ? \trim((string) \wp_unslash($_GET['token'])) : '',
				'card'  => isset($_GET['card']) ? \sanitize_file_name((string) \wp_unslash($_GET['card'])) : '',
			];
		}

		return [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_basic_password')) {
	function cmx_kontakte_carddav_basic_password(): string {
		if (isset($_SERVER['PHP_AUTH_PW']) && \trim((string) $_SERVER['PHP_AUTH_PW']) !== '') {
			return \trim((string) $_SERVER['PHP_AUTH_PW']);
		}

		$header = cmx_kontakte_carddav_authorization_header();
		if (\stripos($header, 'Basic ') !== 0) {
			return '';
		}

		$decoded = \base64_decode(\trim(\substr($header, 6)), true);
		if (!\is_string($decoded) || !\str_contains($decoded, ':')) {
			return '';
		}

		return \trim((string) \substr($decoded, \strpos($decoded, ':') + 1));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_authorization_header')) {
	function cmx_kontakte_carddav_authorization_header(): string {
		foreach ([
			'HTTP_AUTHORIZATION',
			'REDIRECT_HTTP_AUTHORIZATION',
			'PHP_AUTH_DIGEST',
			'HTTP_X_ORIGINAL_AUTHORIZATION',
			'HTTP_X_FORWARDED_AUTHORIZATION',
		] as $key) {
			$value = \trim((string) ($_SERVER[$key] ?? ''));
			if ($value !== '') {
				return $value;
			}
		}

		foreach (['apache_request_headers', 'getallheaders'] as $function) {
			if (!\function_exists($function)) {
				continue;
			}
			$headers = $function();
			if (!\is_array($headers)) {
				continue;
			}
			foreach ($headers as $name => $value) {
				if (\strtolower((string) $name) === 'authorization') {
					return \trim((string) $value);
				}
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_common_headers')) {
	function cmx_kontakte_carddav_common_headers(): void {
		\header('DAV: 1, 3, addressbook');
		\header('Allow: OPTIONS, GET, HEAD, PUT, POST, DELETE, PROPFIND, REPORT');
		\header('MS-Author-Via: DAV');
		\header('X-Robots-Tag: noindex, nofollow', true);
		\header('Cache-Control: private, no-store, no-cache, must-revalidate', true);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_post_slug_header')) {
	function cmx_kontakte_carddav_post_slug_header(): string {
		$slug = '';
		foreach (['HTTP_SLUG', 'HTTP_X_CARDAV_SLUG'] as $key) {
			$value = \trim((string) ($_SERVER[$key] ?? ''));
			if ($value !== '') {
				$slug = $value;
				break;
			}
		}

		$slug = cmx_kontakte_carddav_sanitize_card_filename($slug);
		return $slug !== '' ? $slug : \wp_generate_uuid4() . '.vcf';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_send_mobileconfig')) {
	function cmx_kontakte_carddav_send_mobileconfig(): void {
		if (!\current_user_can('manage_options')) {
			\wp_die('Nicht erlaubt.', 403);
		}
		\check_admin_referer('cmx_kontakte_carddav_mobileconfig');

		$profile_uuid = \wp_generate_uuid4();
		$payload_uuid = \wp_generate_uuid4();
		$host = cmx_kontakte_carddav_host();
		$principal_path = cmx_kontakte_carddav_principal_path();
		$account_url = cmx_kontakte_carddav_account_url();
		$scheme = \strtolower((string) \wp_parse_url($account_url, \PHP_URL_SCHEME));
		$use_ssl = $scheme !== 'http';
		$port = (int) \wp_parse_url($account_url, \PHP_URL_PORT);
		if ($port <= 0) {
			$port = $use_ssl ? 443 : 80;
		}
		$name = 'Mis Buero Kontakte';

		\header('Content-Type: application/x-apple-aspen-config; charset=utf-8');
		\header('Content-Disposition: attachment; filename="misbuero-kontakte-carddav.mobileconfig"');
		\header('X-Content-Type-Options: nosniff');

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">' . "\n";
		echo '<plist version="1.0"><dict>';
		echo '<key>PayloadContent</key><array><dict>';
		echo '<key>PayloadType</key><string>com.apple.carddav.account</string>';
		echo '<key>PayloadVersion</key><integer>1</integer>';
		echo '<key>PayloadIdentifier</key><string>ch.misbuero.carddav.account</string>';
		echo '<key>PayloadUUID</key><string>' . \esc_xml($payload_uuid) . '</string>';
		echo '<key>PayloadDisplayName</key><string>' . \esc_xml($name) . '</string>';
		echo '<key>CardDAVAccountDescription</key><string>' . \esc_xml($name) . '</string>';
		echo '<key>CardDAVHostName</key><string>' . \esc_xml($host) . '</string>';
		echo '<key>CardDAVPort</key><integer>' . $port . '</integer>';
		echo '<key>CardDAVPrincipalURL</key><string>' . \esc_xml($principal_path) . '</string>';
		echo '<key>CardDAVUseSSL</key>' . ($use_ssl ? '<true/>' : '<false/>');
		echo '<key>CardDAVUsername</key><string>' . \esc_xml(cmx_kontakte_carddav_username()) . '</string>';
		echo '<key>CardDAVPassword</key><string>' . \esc_xml(cmx_kontakte_carddav_token()) . '</string>';
		echo '</dict></array>';
		echo '<key>PayloadType</key><string>Configuration</string>';
		echo '<key>PayloadVersion</key><integer>1</integer>';
		echo '<key>PayloadIdentifier</key><string>ch.misbuero.carddav.profile</string>';
		echo '<key>PayloadUUID</key><string>' . \esc_xml($profile_uuid) . '</string>';
		echo '<key>PayloadDisplayName</key><string>' . \esc_xml($name) . '</string>';
		echo '<key>PayloadDescription</key><string>Installiert den Mis Buero CardDAV Kontakt-Sync.</string>';
		echo '<key>PayloadOrganization</key><string>Mis Buero</string>';
		echo '</dict></plist>';
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_contact_ids')) {
	function cmx_kontakte_carddav_contact_ids(): array {
		$query = new \WP_Query([
			'post_type'              => 'kontakte',
			'post_status'            => ['publish', 'private'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		]);

		return \array_values(\array_map('intval', (array) $query->posts));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_all_vcards')) {
	function cmx_kontakte_carddav_all_vcards(int $category_term_id = 0): string {
		$out = [];
		$contact_ids = $category_term_id > 0
			? cmx_kontakte_carddav_group_contact_ids($category_term_id)
			: cmx_kontakte_carddav_contact_ids();
		foreach ($contact_ids as $post_id) {
			$vcard = \trim(cmx_kontakte_carddav_vcard($post_id));
			if ($vcard !== '') {
				$out[] = $vcard;
			}
		}
		if ($category_term_id <= 0) {
			foreach (cmx_kontakte_carddav_category_terms() as $term) {
				$vcard = \trim(cmx_kontakte_carddav_group_vcard((int) $term->term_id));
				if ($vcard !== '') {
					$out[] = $vcard;
				}
			}
		}
		return \implode("\r\n", $out) . "\r\n";
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_vcard')) {
	function cmx_kontakte_carddav_vcard(int $post_id): string {
		$post = \get_post($post_id);
		if (!$post instanceof \WP_Post || (string) $post->post_type !== 'kontakte') {
			return '';
		}

		$title = \trim((string) \get_the_title($post_id));
		$firma = \trim((string) \get_post_meta($post_id, cmx_kontakte_carddav_meta_key('CMX_KONTAKTE_META_FIRMA', '_cmx_kontakte_firma'), true));
		$vorname = \trim((string) \get_post_meta($post_id, cmx_kontakte_carddav_meta_key('CMX_KONTAKTE_META_VORNAME', '_cmx_kontakte_vorname'), true));
		$nachname = \trim((string) \get_post_meta($post_id, cmx_kontakte_carddav_meta_key('CMX_KONTAKTE_META_NACHNAME', '_cmx_kontakte_nachname'), true));
		$website = \trim((string) \get_post_meta($post_id, cmx_kontakte_carddav_meta_key('CMX_KONTAKTE_META_URL', '_cmx_kontakte_url'), true));
		$fn = \trim($vorname . ' ' . $nachname);
		if ($fn === '') {
			$fn = $firma !== '' ? $firma : ($title !== '' ? $title : 'Kontakt ' . $post_id);
		}

		$uid = cmx_kontakte_carddav_contact_uid($post_id);
		$modified = (string) ($post->post_modified_gmt ?: $post->post_modified);
		$timestamp = \strtotime($modified);
		$rev = $timestamp ? \gmdate('Ymd\THis\Z', $timestamp) : \gmdate('Ymd\THis\Z');

		$lines = [
			'BEGIN:VCARD',
			'VERSION:3.0',
			'PRODID:-//Mis Buero//Kontakte//DE',
			'UID:' . cmx_kontakte_carddav_escape($uid),
			'REV:' . $rev,
			'FN:' . cmx_kontakte_carddav_escape($fn),
			'N:' . cmx_kontakte_carddav_escape($nachname) . ';' . cmx_kontakte_carddav_escape($vorname) . ';;;',
		];

		if ($firma !== '') {
			$lines[] = 'ORG:' . cmx_kontakte_carddav_escape($firma);
		}
		if ($website !== '') {
			$lines[] = 'URL:' . cmx_kontakte_carddav_escape($website);
		}
		$category_names = cmx_kontakte_carddav_contact_category_names($post_id);
		if ($category_names !== []) {
			$lines[] = 'CATEGORIES:' . \implode(',', \array_map(__NAMESPACE__ . '\\cmx_kontakte_carddav_escape', $category_names));
		}
		foreach (cmx_kontakte_carddav_extra_lines($post_id) as $extra_line) {
			$lines[] = $extra_line;
		}

		foreach (cmx_kontakte_carddav_channels($post_id) as $channel) {
			$type = (string) ($channel['type'] ?? '');
			$value = \trim((string) ($channel['value'] ?? ''));
			if ($value === '') {
				continue;
			}
			$label = cmx_kontakte_carddav_vcard_type((string) ($channel['label'] ?? ''), $type);
			if ($type === 'email' && \is_email($value)) {
				$lines[] = 'EMAIL;TYPE=INTERNET;TYPE=' . $label . ':' . cmx_kontakte_carddav_escape($value);
			}
			if ($type === 'phone') {
				$lines[] = 'TEL;TYPE=' . $label . ':' . cmx_kontakte_carddav_escape($value);
			}
		}

		$birthday = \trim((string) \get_post_meta($post_id, cmx_kontakte_carddav_meta_key('CMX_KONTAKTE_META_GEBURTSDATUM', '_cmx_kontakte_geburtsdatum'), true));
		if ($birthday !== '' && \preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
			$lines[] = 'BDAY:' . \str_replace('-', '', $birthday);
		}

		foreach (cmx_kontakte_carddav_addresses($post_id) as $address) {
			$type = (string) ($address['type'] ?? 'WORK');
			$lines[] = 'ADR;TYPE=' . $type . ':;'
				. cmx_kontakte_carddav_escape((string) ($address['extra'] ?? ''))
				. ';' . cmx_kontakte_carddav_escape((string) ($address['street'] ?? ''))
				. ';' . cmx_kontakte_carddav_escape((string) ($address['city'] ?? ''))
				. ';;' . cmx_kontakte_carddav_escape((string) ($address['zip'] ?? ''))
				. ';' . cmx_kontakte_carddav_escape((string) ($address['country'] ?? ''));
		}

		$lines[] = 'END:VCARD';
		return \implode("\r\n", \array_map(__NAMESPACE__ . '\\cmx_kontakte_carddav_fold_line', $lines)) . "\r\n";
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_host_id')) {
	function cmx_kontakte_carddav_host_id(): string {
		$host = (string) \wp_parse_url(\home_url('/'), \PHP_URL_HOST);
		return $host !== '' ? $host : 'local';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_contact_uid')) {
	function cmx_kontakte_carddav_contact_uid(int $post_id): string {
		return cmx_kontakte_carddav_stable_uuid('misbuero-kontakt-' . $post_id . '@' . cmx_kontakte_carddav_host_id());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_group_uid')) {
	function cmx_kontakte_carddav_group_uid(int $term_id): string {
		return cmx_kontakte_carddav_stable_uuid('misbuero-kategorie-' . $term_id . '@' . cmx_kontakte_carddav_host_id());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_stable_uuid')) {
	function cmx_kontakte_carddav_stable_uuid(string $seed): string {
		$hash = \md5($seed);
		return \substr($hash, 0, 8) . '-'
			. \substr($hash, 8, 4) . '-'
			. \substr($hash, 12, 4) . '-'
			. \substr($hash, 16, 4) . '-'
			. \substr($hash, 20, 12);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_uid_uri')) {
	function cmx_kontakte_carddav_uid_uri(string $uid): string {
		$uid = \trim($uid);
		return \stripos($uid, 'urn:uuid:') === 0 ? $uid : 'urn:uuid:' . $uid;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_category_taxonomy')) {
	function cmx_kontakte_carddav_category_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_kontakte_primary_category_taxonomy')) {
			$taxonomy = (string) cmx_kontakte_primary_category_taxonomy();
			if ($taxonomy !== '' && \taxonomy_exists($taxonomy)) {
				return $taxonomy;
			}
		}

		foreach (['kontakte_kategorien', 'kontakte_kategorie', 'kundenkategorie', 'kontakt_kategorie'] as $taxonomy) {
			if (\taxonomy_exists($taxonomy)) {
				return $taxonomy;
			}
		}
		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_category_terms')) {
	function cmx_kontakte_carddav_category_terms(): array {
		$taxonomy = cmx_kontakte_carddav_category_taxonomy();
		if ($taxonomy === '') {
			return [];
		}

		$terms = \get_terms([
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]);
		if (!\is_array($terms) || \is_wp_error($terms)) {
			return [];
		}
		return \array_values(\array_filter($terms, static function ($term): bool {
			return $term instanceof \WP_Term && cmx_kontakte_carddav_group_contact_ids((int) $term->term_id) !== [];
		}));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_category_term')) {
	function cmx_kontakte_carddav_category_term(int $term_id): ?\WP_Term {
		$taxonomy = cmx_kontakte_carddav_category_taxonomy();
		if ($taxonomy === '' || $term_id <= 0) {
			return null;
		}

		$term = \get_term($term_id, $taxonomy);
		return ($term instanceof \WP_Term && !\is_wp_error($term)) ? $term : null;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_contact_category_names')) {
	function cmx_kontakte_carddav_contact_category_names(int $post_id): array {
		$taxonomy = cmx_kontakte_carddav_category_taxonomy();
		if ($taxonomy === '') {
			return [];
		}

		$terms = \wp_get_post_terms($post_id, $taxonomy);
		if (!\is_array($terms) || \is_wp_error($terms)) {
			return [];
		}

		$names = [];
		foreach ($terms as $term) {
			if ($term instanceof \WP_Term && (string) $term->name !== '') {
				$names[] = (string) $term->name;
			}
		}
		return \array_values(\array_unique($names));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_save_contact_categories')) {
	function cmx_kontakte_carddav_save_contact_categories(int $post_id, array $category_names): void {
		$taxonomy = cmx_kontakte_carddav_category_taxonomy();
		if ($taxonomy === '' || $post_id <= 0) {
			return;
		}

		$term_ids = [];
		foreach ($category_names as $name) {
			$name = \trim(\sanitize_text_field((string) $name));
			if ($name === '') {
				continue;
			}

			$term = \get_term_by('name', $name, $taxonomy);
			if (!$term || \is_wp_error($term)) {
				$created = \wp_insert_term($name, $taxonomy);
				if (\is_wp_error($created)) {
					continue;
				}
				$term_ids[] = (int) ($created['term_id'] ?? 0);
			} else {
				$term_ids[] = (int) $term->term_id;
			}
		}

		$term_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $term_ids))));
		\wp_set_object_terms($post_id, $term_ids, $taxonomy, false);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_remove_contact_category')) {
	function cmx_kontakte_carddav_remove_contact_category(int $post_id, int $term_id): void {
		$taxonomy = cmx_kontakte_carddav_category_taxonomy();
		if ($taxonomy === '' || $post_id <= 0 || $term_id <= 0) {
			return;
		}

		$current = \wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
		if (!\is_array($current) || \is_wp_error($current)) {
			return;
		}

		$current = \array_values(\array_filter(\array_map('intval', $current), static function (int $id) use ($term_id): bool {
			return $id > 0 && $id !== $term_id;
		}));
		\wp_set_object_terms($post_id, $current, $taxonomy, false);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_group_contact_ids')) {
	function cmx_kontakte_carddav_group_contact_ids(int $term_id): array {
		$taxonomy = cmx_kontakte_carddav_category_taxonomy();
		if ($taxonomy === '' || $term_id <= 0) {
			return [];
		}

		$query = new \WP_Query([
			'post_type'              => 'kontakte',
			'post_status'            => ['publish', 'private'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => [[
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => [$term_id],
			]],
		]);

		return \array_values(\array_map('intval', (array) $query->posts));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_group_vcard')) {
	function cmx_kontakte_carddav_group_vcard(int $term_id): string {
		$term = cmx_kontakte_carddav_category_term($term_id);
		if (!$term instanceof \WP_Term) {
			return '';
		}

		$lines = [
			'BEGIN:VCARD',
			'VERSION:3.0',
			'PRODID:-//Mis Buero//Kontakt-Kategorien//DE',
			'UID:' . cmx_kontakte_carddav_escape(cmx_kontakte_carddav_group_uid($term_id)),
			'FN:' . cmx_kontakte_carddav_escape((string) $term->name),
			'N:' . cmx_kontakte_carddav_escape((string) $term->name) . ';;;;',
			'X-ADDRESSBOOKSERVER-KIND:group',
			'KIND:group',
			'CATEGORIES:' . cmx_kontakte_carddav_escape((string) $term->name),
		];

		foreach (cmx_kontakte_carddav_group_contact_ids($term_id) as $post_id) {
			$uid = cmx_kontakte_carddav_contact_uid((int) $post_id);
			$lines[] = 'X-ADDRESSBOOKSERVER-MEMBER:' . cmx_kontakte_carddav_uid_uri($uid);
			$lines[] = 'MEMBER:' . cmx_kontakte_carddav_uid_uri($uid);
		}

		$lines[] = 'END:VCARD';
		return \implode("\r\n", \array_map(__NAMESPACE__ . '\\cmx_kontakte_carddav_fold_line', $lines)) . "\r\n";
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_save_group_vcard')) {
	function cmx_kontakte_carddav_save_group_vcard(int $term_id, string $vcard): bool {
		$term = cmx_kontakte_carddav_category_term($term_id);
		$taxonomy = cmx_kontakte_carddav_category_taxonomy();
		if (!$term instanceof \WP_Term || $taxonomy === '') {
			return false;
		}

		$member_ids = [];
		$saw_member_property = false;
		foreach (cmx_kontakte_carddav_unfold_lines($vcard) as $line) {
			$line = \trim((string) $line);
			if ($line === '' || !\str_contains($line, ':')) {
				continue;
			}
			[$raw_name, $raw_value] = \explode(':', $line, 2);
			$name = \strtoupper((string) \strtok($raw_name, ';'));
			if ($name === 'FN') {
				$new_name = \trim(\sanitize_text_field(cmx_kontakte_carddav_unescape($raw_value)));
				if ($new_name !== '' && $new_name !== (string) $term->name) {
					\wp_update_term($term_id, $taxonomy, ['name' => $new_name]);
				}
				continue;
			}
			if (!\in_array($name, ['MEMBER', 'X-ADDRESSBOOKSERVER-MEMBER'], true)) {
				continue;
			}
			$saw_member_property = true;

			$post_id = cmx_kontakte_carddav_contact_id_from_member(cmx_kontakte_carddav_unescape($raw_value));
			if ($post_id > 0) {
				$member_ids[] = $post_id;
			}
		}

		if (!$saw_member_property) {
			return true;
		}

		$member_ids = \array_values(\array_unique(\array_map('intval', $member_ids)));
		$member_lookup = \array_fill_keys($member_ids, true);
		$all_ids = \array_values(\array_unique(\array_merge(cmx_kontakte_carddav_contact_ids(), cmx_kontakte_carddav_group_contact_ids($term_id))));

		foreach ($all_ids as $post_id) {
			$current = \wp_get_object_terms((int) $post_id, $taxonomy, ['fields' => 'ids']);
			if (!\is_array($current) || \is_wp_error($current)) {
				$current = [];
			}
			$current = \array_values(\array_unique(\array_filter(\array_map('intval', $current))));
			$has_term = \in_array($term_id, $current, true);
			$should_have = isset($member_lookup[(int) $post_id]);
			if ($should_have && !$has_term) {
				$current[] = $term_id;
			} elseif (!$should_have && $has_term) {
				$current = \array_values(\array_filter($current, static fn(int $id): bool => $id !== $term_id));
			} else {
				continue;
			}
			\wp_set_object_terms((int) $post_id, $current, $taxonomy, false);
		}

		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_contact_id_from_uid')) {
	function cmx_kontakte_carddav_contact_id_from_uid(string $uid): int {
		$uid = \trim($uid);
		foreach (cmx_kontakte_carddav_contact_ids() as $post_id) {
			if (\hash_equals(cmx_kontakte_carddav_contact_uid((int) $post_id), $uid)) {
				return (int) $post_id;
			}
		}
		return 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_contact_id_from_member')) {
	function cmx_kontakte_carddav_contact_id_from_member(string $member): int {
		$member = \trim($member);
		if ($member === '') {
			return 0;
		}

		foreach (cmx_kontakte_carddav_contact_ids() as $post_id) {
			$uid = cmx_kontakte_carddav_contact_uid((int) $post_id);
			if (\hash_equals($uid, $member) || \hash_equals(cmx_kontakte_carddav_uid_uri($uid), $member)) {
				return (int) $post_id;
			}
		}
		return 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_channels')) {
	function cmx_kontakte_carddav_channels(int $post_id): array {
		$out = [];
		if (\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_read_contacts')) {
			foreach ((array) cmx_kommunikation_read_contacts($post_id) as $row) {
				if (!\is_array($row)) {
					continue;
				}
				$phone = \trim((string) ($row['telefon'] ?? ''));
				if ($phone !== '') {
					$out[] = ['type' => 'phone', 'value' => $phone, 'label' => (string) ($row['telefon_label'] ?? '')];
				}
				$email = \sanitize_email((string) ($row['email'] ?? ''));
				if (\is_email($email)) {
					$out[] = ['type' => 'email', 'value' => $email, 'label' => (string) ($row['email_label'] ?? '')];
				}
			}
		}

		return $out;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_addresses')) {
	function cmx_kontakte_carddav_addresses(int $post_id): array {
		$groups = [
			'WORK' => [
				'street' => 'CMX_RECHNUNG_META_STRASSE',
				'extra' => 'CMX_RECHNUNG_META_ZUSATZ',
				'zip' => 'CMX_RECHNUNG_META_PLZ',
				'city' => 'CMX_RECHNUNG_META_ORT',
				'country' => 'CMX_RECHNUNG_META_LAND',
			],
			'HOME' => [
				'street' => 'CMX_LIEFER_META_STRASSE',
				'extra' => 'CMX_LIEFER_META_ZUSATZ',
				'zip' => 'CMX_LIEFER_META_PLZ',
				'city' => 'CMX_LIEFER_META_ORT',
				'country' => 'CMX_LIEFER_META_LAND',
			],
		];

		$out = [];
		foreach ($groups as $type => $map) {
			$address = ['type' => $type];
			foreach ($map as $field => $constant) {
				$address[$field] = \trim((string) \get_post_meta($post_id, cmx_kontakte_carddav_meta_key((string) $constant, ''), true));
			}
			if (\trim((string) \implode('', [$address['street'], $address['extra'], $address['zip'], $address['city'], $address['country']])) !== '') {
				$out[] = $address;
			}
		}
		return $out;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_save_vcard')) {
	function cmx_kontakte_carddav_save_vcard(int $post_id, string $vcard): bool {
		$data = cmx_kontakte_carddav_parse_vcard($vcard);
		if ($data === []) {
			return false;
		}

		$first = (string) ($data['first_name'] ?? '');
		$last = (string) ($data['last_name'] ?? '');
		$fn = (string) ($data['fn'] ?? '');
		$org = (string) ($data['org'] ?? '');
		$title = $org !== '' ? $org : \trim($first . ' ' . $last);
		if ($title === '') {
			$title = $fn;
		}
		if ($title !== '') {
			\wp_update_post([
				'ID' => $post_id,
				'post_title' => \sanitize_text_field($title),
			]);
		}

		foreach ([
			'CMX_KONTAKTE_META_VORNAME' => $first,
			'CMX_KONTAKTE_META_NACHNAME' => $last,
			'CMX_KONTAKTE_META_FIRMA' => $org,
			'CMX_KONTAKTE_META_URL' => (string) ($data['url'] ?? ''),
			'CMX_KONTAKTE_META_GEBURTSDATUM' => (string) ($data['birthday'] ?? ''),
		] as $constant => $value) {
			$key = cmx_kontakte_carddav_meta_key($constant, '');
			if ($key === '') {
				continue;
			}
			$value = \trim((string) $value);
			if ($value === '') {
				\delete_post_meta($post_id, $key);
			} else {
				\update_post_meta($post_id, $key, $constant === 'CMX_KONTAKTE_META_URL' ? \esc_url_raw($value) : \sanitize_text_field($value));
			}
		}

		cmx_kontakte_carddav_save_addresses($post_id, (array) ($data['addresses'] ?? []));
		if (\array_key_exists('categories', $data)) {
			cmx_kontakte_carddav_save_contact_categories($post_id, (array) $data['categories']);
		}
		cmx_kontakte_carddav_save_extra_lines($post_id, (array) ($data['extra_lines'] ?? []));

		if (\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_persist_contacts')) {
			cmx_kommunikation_persist_contacts($post_id, cmx_kontakte_carddav_communication_rows($data));
		}

		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_create_contact_from_vcard')) {
	function cmx_kontakte_carddav_create_contact_from_vcard(string $vcard, string $card, int $category_term_id = 0): int {
		$card = cmx_kontakte_carddav_sanitize_card_filename($card);
		if ($card === '' || !\preg_match('/\.vcf$/i', $card)) {
			return 0;
		}
		if (\trim($vcard) === '' || !\preg_match('/BEGIN:VCARD/i', $vcard)) {
			return 0;
		}

		$data = cmx_kontakte_carddav_parse_vcard($vcard);
		if (!cmx_kontakte_carddav_parsed_vcard_has_content($data)) {
			return 0;
		}

		$title = cmx_kontakte_carddav_title_from_data($data);
		if ($title === '') {
			$title = 'Neuer Kontakt';
		}

		$post_id = \wp_insert_post([
			'post_type'   => 'kontakte',
			'post_status' => 'publish',
			'post_title'  => \sanitize_text_field($title),
		], true);
		if (\is_wp_error($post_id) || (int) $post_id <= 0) {
			return 0;
		}

		$post_id = (int) $post_id;
		\update_post_meta($post_id, CMX_KONTAKTE_CARDDAV_CARD_META, $card);
		if (!cmx_kontakte_carddav_save_vcard($post_id, $vcard)) {
			\wp_delete_post($post_id, true);
			return 0;
		}

		if ($category_term_id > 0) {
			$taxonomy = cmx_kontakte_carddav_category_taxonomy();
			if ($taxonomy !== '') {
				$current = \wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
				$current = \is_array($current) && !\is_wp_error($current) ? \array_map('intval', $current) : [];
				$current[] = $category_term_id;
				\wp_set_object_terms($post_id, \array_values(\array_unique(\array_filter($current))), $taxonomy, false);
			}
		}

		return $post_id;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_parsed_vcard_has_content')) {
	function cmx_kontakte_carddav_parsed_vcard_has_content(array $data): bool {
		foreach (['fn', 'first_name', 'last_name', 'org', 'url', 'birthday'] as $key) {
			if (\trim((string) ($data[$key] ?? '')) !== '') {
				return true;
			}
		}

		foreach (['emails', 'phones', 'addresses'] as $key) {
			if (!empty($data[$key])) {
				return true;
			}
		}

		return false;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_title_from_data')) {
	function cmx_kontakte_carddav_title_from_data(array $data): string {
		$org = \trim((string) ($data['org'] ?? ''));
		if ($org !== '') {
			return $org;
		}

		$name = \trim(\trim((string) ($data['first_name'] ?? '')) . ' ' . \trim((string) ($data['last_name'] ?? '')));
		if ($name !== '') {
			return $name;
		}

		return \trim((string) ($data['fn'] ?? ''));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_parse_vcard')) {
	function cmx_kontakte_carddav_parse_vcard(string $vcard): array {
		$lines = cmx_kontakte_carddav_unfold_lines($vcard);
		$data = [
			'emails' => [],
			'phones' => [],
			'addresses' => [],
			'extra_lines' => [],
		];
		$known_once = [];

		foreach ($lines as $line) {
			$line = \trim($line);
			if ($line === '' || !\str_contains($line, ':')) {
				continue;
			}
			[$raw_name, $raw_value] = \explode(':', $line, 2);
			$parts = \explode(';', $raw_name);
			$name = \strtoupper((string) \array_shift($parts));
			if (\str_contains($name, '.')) {
				$name = \substr($name, (int) \strrpos($name, '.') + 1);
			}
			$types = cmx_kontakte_carddav_property_types($parts);
			$value = cmx_kontakte_carddav_unescape($raw_value);

			if (\in_array($name, ['BEGIN', 'END', 'VERSION', 'PRODID', 'UID', 'REV'], true)) {
				continue;
			}

			$known = true;
			if ($name === 'FN') {
				$data['fn'] = \sanitize_text_field($value);
			} elseif ($name === 'N') {
				$n = \array_map(__NAMESPACE__ . '\\cmx_kontakte_carddav_unescape', \explode(';', $raw_value));
				$data['last_name'] = \sanitize_text_field((string) ($n[0] ?? ''));
				$data['first_name'] = \sanitize_text_field((string) ($n[1] ?? ''));
				$data['name_prefix'] = \sanitize_text_field((string) ($n[3] ?? ''));
				$data['name_suffix'] = \sanitize_text_field((string) ($n[4] ?? ''));
			} elseif ($name === 'ORG') {
				$org_parts = \explode(';', $value);
				$data['org'] = \sanitize_text_field((string) ($org_parts[0] ?? $value));
			} elseif ($name === 'URL') {
				if (empty($data['url'])) {
					$data['url'] = \esc_url_raw($value);
				} else {
					$known = false;
				}
			} elseif ($name === 'BDAY') {
				$data['birthday'] = cmx_kontakte_carddav_parse_date($value);
			} elseif ($name === 'CATEGORIES') {
				foreach (cmx_kontakte_carddav_parse_text_list($raw_value) as $category) {
					$category = \sanitize_text_field(cmx_kontakte_carddav_unescape($category));
					if ($category !== '') {
						$data['categories'][] = $category;
					}
				}
			} elseif ($name === 'EMAIL') {
				$email = \sanitize_email($value);
				if (\is_email($email)) {
					$data['emails'][] = ['value' => $email, 'types' => $types];
				}
			} elseif ($name === 'TEL') {
				$phone = \trim(\sanitize_text_field($value));
				if ($phone !== '') {
					$data['phones'][] = ['value' => $phone, 'types' => $types];
				}
			} elseif ($name === 'ADR') {
				$adr = \array_map(__NAMESPACE__ . '\\cmx_kontakte_carddav_unescape', \explode(';', $raw_value));
				$data['addresses'][] = [
					'extra' => \sanitize_text_field((string) ($adr[1] ?? '')),
					'street' => \sanitize_text_field((string) ($adr[2] ?? '')),
					'city' => \sanitize_text_field((string) ($adr[3] ?? '')),
					'region' => \sanitize_text_field((string) ($adr[4] ?? '')),
					'zip' => \sanitize_text_field((string) ($adr[5] ?? '')),
					'country' => \sanitize_text_field((string) ($adr[6] ?? '')),
					'types' => $types,
				];
			} else {
				$known = false;
			}

			if (!$known || \in_array($name, ['TITLE', 'ROLE', 'NICKNAME', 'NOTE', 'IMPP', 'X-SOCIALPROFILE', 'X-ABRELATEDNAMES', 'X-ABDATE'], true)) {
				$data['extra_lines'][] = $line;
				continue;
			}
			if (\in_array($name, ['FN', 'N', 'ORG', 'URL', 'BDAY', 'CATEGORIES'], true)) {
				if (isset($known_once[$name])) {
					$data['extra_lines'][] = $line;
				}
				$known_once[$name] = true;
			}
		}

		if (($data['first_name'] ?? '') === '' && ($data['last_name'] ?? '') === '' && !empty($data['fn'])) {
			$parts = \preg_split('/\s+/', \trim((string) $data['fn']));
			if (\is_array($parts) && \count($parts) > 1) {
				$data['last_name'] = (string) \array_pop($parts);
				$data['first_name'] = \implode(' ', $parts);
			}
		}

		return $data;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_save_addresses')) {
	function cmx_kontakte_carddav_save_addresses(int $post_id, array $addresses): void {
		$billing = [];
		$shipping = [];
		foreach ($addresses as $address) {
			if (!\is_array($address)) {
				continue;
			}
			$types = \array_map('strtoupper', (array) ($address['types'] ?? []));
			if ($billing === [] || \in_array('WORK', $types, true)) {
				$billing = $address;
			}
			if ($shipping === [] && \in_array('HOME', $types, true)) {
				$shipping = $address;
			}
		}
		if ($shipping === [] && isset($addresses[1]) && \is_array($addresses[1])) {
			$shipping = $addresses[1];
		}

		cmx_kontakte_carddav_save_address_group($post_id, $billing, [
			'street' => 'CMX_RECHNUNG_META_STRASSE',
			'extra' => 'CMX_RECHNUNG_META_ZUSATZ',
			'zip' => 'CMX_RECHNUNG_META_PLZ',
			'city' => 'CMX_RECHNUNG_META_ORT',
			'country' => 'CMX_RECHNUNG_META_LAND',
		]);
		cmx_kontakte_carddav_save_address_group($post_id, $shipping, [
			'street' => 'CMX_LIEFER_META_STRASSE',
			'extra' => 'CMX_LIEFER_META_ZUSATZ',
			'zip' => 'CMX_LIEFER_META_PLZ',
			'city' => 'CMX_LIEFER_META_ORT',
			'country' => 'CMX_LIEFER_META_LAND',
		]);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_save_address_group')) {
	function cmx_kontakte_carddav_save_address_group(int $post_id, array $address, array $map): void {
		foreach ($map as $field => $constant) {
			$key = cmx_kontakte_carddav_meta_key((string) $constant, '');
			if ($key === '') {
				continue;
			}
			$value = \trim((string) ($address[$field] ?? ''));
			if ($field === 'country' && $value !== '') {
				if (\function_exists(__NAMESPACE__ . '\\cmx_kontakte_resolve_country_option_value')) {
					$value = (string) cmx_kontakte_resolve_country_option_value($value);
				} elseif (\function_exists(__NAMESPACE__ . '\\cmx_kontakte_normalize_country_meta_value')) {
					$value = (string) cmx_kontakte_normalize_country_meta_value($value);
				}
			}
			if ($value === '') {
				\delete_post_meta($post_id, $key);
			} else {
				\update_post_meta($post_id, $key, \sanitize_text_field($value));
			}
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_extra_lines')) {
	function cmx_kontakte_carddav_extra_lines(int $post_id): array {
		$raw = \get_post_meta($post_id, CMX_KONTAKTE_CARDDAV_EXTRA_META, true);
		$lines = \is_array($raw) ? $raw : [];
		$out = [];
		foreach ($lines as $line) {
			$line = \trim((string) $line);
			if ($line !== '' && \str_contains($line, ':') && !\preg_match('/^(BEGIN|END|VERSION|PRODID|UID|REV):/i', $line)) {
				$out[] = $line;
			}
		}
		return \array_values(\array_unique($out));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_save_extra_lines')) {
	function cmx_kontakte_carddav_save_extra_lines(int $post_id, array $lines): void {
		$out = [];
		foreach ($lines as $line) {
			$line = \trim((string) $line);
			if ($line !== '' && \str_contains($line, ':') && !\preg_match('/^(BEGIN|END|VERSION|PRODID|UID|REV):/i', $line)) {
				$out[] = $line;
			}
		}
		$out = \array_values(\array_unique($out));
		if ($out === []) {
			\delete_post_meta($post_id, CMX_KONTAKTE_CARDDAV_EXTRA_META);
		} else {
			\update_post_meta($post_id, CMX_KONTAKTE_CARDDAV_EXTRA_META, $out);
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_unfold_lines')) {
	function cmx_kontakte_carddav_unfold_lines(string $vcard): array {
		$raw_lines = \preg_split('/\r\n|\r|\n/', $vcard) ?: [];
		$lines = [];
		foreach ($raw_lines as $line) {
			$line = (string) $line;
			if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t") && $lines !== []) {
				$lines[\array_key_last($lines)] .= \substr($line, 1);
			} else {
				$lines[] = $line;
			}
		}
		return $lines;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_property_types')) {
	function cmx_kontakte_carddav_property_types(array $parts): array {
		$types = [];
		foreach ($parts as $part) {
			$part = \trim((string) $part);
			if ($part === '') {
				continue;
			}
			if (\stripos($part, 'TYPE=') === 0) {
				$part = \substr($part, 5);
			}
			foreach (\explode(',', $part) as $type) {
				$type = \strtoupper(\trim($type, '" '));
				if ($type !== '') {
					$types[] = $type;
				}
			}
		}
		return \array_values(\array_unique($types));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_unescape')) {
	function cmx_kontakte_carddav_unescape(string $value): string {
		return \strtr($value, [
			'\\n' => "\n",
			'\\N' => "\n",
			'\\,' => ',',
			'\\;' => ';',
			'\\\\' => '\\',
		]);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_parse_text_list')) {
	function cmx_kontakte_carddav_parse_text_list(string $value): array {
		$items = [];
		$current = '';
		$escaped = false;
		$length = \strlen($value);
		for ($i = 0; $i < $length; $i++) {
			$char = $value[$i];
			if ($escaped) {
				$current .= '\\' . $char;
				$escaped = false;
				continue;
			}
			if ($char === '\\') {
				$escaped = true;
				continue;
			}
			if ($char === ',') {
				$items[] = $current;
				$current = '';
				continue;
			}
			$current .= $char;
		}
		if ($escaped) {
			$current .= '\\';
		}
		$items[] = $current;
		return \array_values(\array_filter(\array_map('trim', $items), static fn(string $item): bool => $item !== ''));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_parse_date')) {
	function cmx_kontakte_carddav_parse_date(string $value): string {
		$value = \trim($value);
		if (\preg_match('/^\d{8}$/', $value)) {
			$value = \substr($value, 0, 4) . '-' . \substr($value, 4, 2) . '-' . \substr($value, 6, 2);
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_sanitize_date_ymd')) {
			return (string) cmx_sanitize_date_ymd($value);
		}
		return \preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_communication_rows')) {
	function cmx_kontakte_carddav_communication_rows(array $data): array {
		$phones = (array) ($data['phones'] ?? []);
		$emails = (array) ($data['emails'] ?? []);
		$count = \max(\count($phones), \count($emails), 1);
		$rows = [];
		for ($i = 0; $i < $count; $i++) {
			$phone = (array) ($phones[$i] ?? []);
			$email = (array) ($emails[$i] ?? []);
			$rows[] = [
				'vorname' => $i === 0 ? (string) ($data['first_name'] ?? '') : '',
				'nachname' => $i === 0 ? (string) ($data['last_name'] ?? '') : '',
				'telefon_label' => cmx_kontakte_carddav_label_slug((array) ($phone['types'] ?? []), CMX_TAX_PHONE_LABELS),
				'telefon' => (string) ($phone['value'] ?? ''),
				'email_label' => cmx_kontakte_carddav_label_slug((array) ($email['types'] ?? []), CMX_TAX_MAIL_LABELS),
				'email' => (string) ($email['value'] ?? ''),
				'geburtsdatum' => $i === 0 ? (string) ($data['birthday'] ?? '') : '',
			];
		}
		return $rows;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_label_slug')) {
	function cmx_kontakte_carddav_label_slug(array $types, string $taxonomy): string {
		$candidates = [];
		$types = \array_map('strtoupper', $types);
		if (\in_array('CELL', $types, true) || \in_array('MOBILE', $types, true)) {
			$candidates[] = 'Mobil';
		}
		if (\in_array('HOME', $types, true)) {
			$candidates[] = 'Privat';
		}
		if (\in_array('WORK', $types, true)) {
			$candidates[] = 'Geschäft';
			$candidates[] = 'Geschaeft';
		}
		$candidates[] = '';

		foreach ($candidates as $candidate) {
			if ($candidate === '') {
				return '';
			}
			if (\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_resolve_label_term_slug')) {
				$slug = (string) cmx_kommunikation_resolve_label_term_slug($candidate, $taxonomy);
				if ($slug !== '') {
					return $slug;
				}
			}
		}
		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_meta_key')) {
	function cmx_kontakte_carddav_meta_key(string $constant, string $fallback): string {
		return \defined(__NAMESPACE__ . '\\' . $constant) ? (string) \constant(__NAMESPACE__ . '\\' . $constant) : $fallback;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_escape')) {
	function cmx_kontakte_carddav_escape(string $value): string {
		$value = \strtr($value, [
			'\\' => '\\\\',
			';'  => '\;',
			','  => '\,',
		]);
		return \trim((string) \preg_replace('/\r\n|\r|\n/', '\\n', $value));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_fold_line')) {
	function cmx_kontakte_carddav_fold_line(string $line): string {
		return $line;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_vcard_type')) {
	function cmx_kontakte_carddav_vcard_type(string $label, string $type): string {
		$label = \strtolower(\sanitize_title($label));
		if (\str_contains($label, 'mobil') || \str_contains($label, 'mobile') || \str_contains($label, 'whatsapp')) {
			return 'CELL';
		}
		if (\str_contains($label, 'privat') || \str_contains($label, 'home')) {
			return 'HOME';
		}
		return $type === 'email' ? 'WORK' : 'WORK';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_card_id')) {
	function cmx_kontakte_carddav_card_id(string $card): int {
		$card = cmx_kontakte_carddav_sanitize_card_filename($card);
		if (!\preg_match('/^(\d+)\.vcf$/', $card, $matches)) {
			return cmx_kontakte_carddav_card_id_by_filename($card);
		}
		return (int) $matches[1];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_sanitize_card_filename')) {
	function cmx_kontakte_carddav_sanitize_card_filename(string $card): string {
		$card = \sanitize_file_name($card);
		if ($card === '' || !\preg_match('/^[A-Za-z0-9._-]+\.vcf$/', $card)) {
			return '';
		}
		return $card;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_card_filename')) {
	function cmx_kontakte_carddav_card_filename(int $post_id): string {
		$post_id = (int) $post_id;
		if ($post_id <= 0) {
			return '';
		}

		$card = cmx_kontakte_carddav_sanitize_card_filename((string) \get_post_meta($post_id, CMX_KONTAKTE_CARDDAV_CARD_META, true));
		return $card !== '' ? $card : ($post_id . '.vcf');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_card_id_by_filename')) {
	function cmx_kontakte_carddav_card_id_by_filename(string $card): int {
		$card = cmx_kontakte_carddav_sanitize_card_filename($card);
		if ($card === '') {
			return 0;
		}

		$query = new \WP_Query([
			'post_type'              => 'kontakte',
			'post_status'            => ['publish', 'private'],
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => [[
				'key'   => CMX_KONTAKTE_CARDDAV_CARD_META,
				'value' => $card,
			]],
		]);

		return !empty($query->posts[0]) ? (int) $query->posts[0] : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_group_id')) {
	function cmx_kontakte_carddav_group_id(string $card): int {
		if (!\preg_match('/^category-(\d+)\.vcf$/', $card, $matches)) {
			return 0;
		}
		return (int) $matches[1];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_etag')) {
	function cmx_kontakte_carddav_etag(int $post_id): string {
		$post = \get_post($post_id);
		$signature = [
			'id' => $post_id,
			'title' => $post instanceof \WP_Post ? (string) $post->post_title : '',
			'modified' => $post instanceof \WP_Post ? (string) ($post->post_modified_gmt ?: $post->post_modified) : '',
			'meta' => cmx_kontakte_carddav_etag_meta($post_id),
		];
		return \md5((string) \wp_json_encode($signature));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_group_etag')) {
	function cmx_kontakte_carddav_group_etag(int $term_id): string {
		$term = cmx_kontakte_carddav_category_term($term_id);
		if (!$term instanceof \WP_Term) {
			return \md5('missing-group-' . $term_id);
		}

		$members = [];
		foreach (cmx_kontakte_carddav_group_contact_ids($term_id) as $post_id) {
			$members[] = $post_id . ':' . cmx_kontakte_carddav_etag((int) $post_id);
		}
		\sort($members, \SORT_STRING);

		return \md5((string) \wp_json_encode([
			'id'      => $term_id,
			'name'    => (string) $term->name,
			'slug'    => (string) $term->slug,
			'members' => $members,
		]));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_etag_meta')) {
	function cmx_kontakte_carddav_etag_meta(int $post_id): array {
		$all_meta = \get_post_meta($post_id);
		if (!\is_array($all_meta)) {
			return [];
		}

		$out = [];
		foreach ($all_meta as $key => $values) {
			$key = (string) $key;
			if (!\preg_match('/^_(?:cmx_kontakte|cmx_rechnung|cmx_liefer|cmx_kommunikation|cmx_email|cmx_telefon|cmx_carddav)_/', $key)) {
				continue;
			}
			$clean_values = [];
			foreach ((array) $values as $value) {
				if (\is_scalar($value) || $value === null) {
					$clean_values[] = (string) $value;
				} else {
					$clean_values[] = (string) \wp_json_encode($value);
				}
			}
			\sort($clean_values, \SORT_STRING);
			$out[$key] = $clean_values;
		}
		\ksort($out, \SORT_STRING);
		return $out;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_collection_ctag')) {
	function cmx_kontakte_carddav_collection_ctag(): string {
		$parts = [];
		foreach (cmx_kontakte_carddav_contact_ids() as $post_id) {
			$parts[] = $post_id . ':' . cmx_kontakte_carddav_etag((int) $post_id);
		}
		foreach (cmx_kontakte_carddav_category_terms() as $term) {
			$term_id = (int) $term->term_id;
			$parts[] = 'category-' . $term_id . ':' . cmx_kontakte_carddav_group_etag($term_id);
		}
		return \md5(\implode('|', $parts));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_category_collection_ctag')) {
	function cmx_kontakte_carddav_category_collection_ctag(int $term_id): string {
		$parts = ['category:' . $term_id . ':' . cmx_kontakte_carddav_group_etag($term_id)];
		foreach (cmx_kontakte_carddav_group_contact_ids($term_id) as $post_id) {
			$parts[] = $post_id . ':' . cmx_kontakte_carddav_etag((int) $post_id);
		}
		return \md5(\implode('|', $parts));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_send_multistatus')) {
	function cmx_kontakte_carddav_send_multistatus(string $card = '', bool $include_data = false, int $category_term_id = 0): void {
		\status_header(207);
		\header('Content-Type: application/xml; charset=utf-8');

		$responses = [];
		if ($card !== '') {
			$post_id = cmx_kontakte_carddav_card_id($card);
			if ($post_id > 0 && \get_post_type($post_id) === 'kontakte') {
				if ($category_term_id <= 0 || \in_array($post_id, cmx_kontakte_carddav_group_contact_ids($category_term_id), true)) {
					$responses[] = cmx_kontakte_carddav_xml_card_response($post_id, $include_data, $category_term_id);
				}
			}
		} else {
			$responses[] = $category_term_id > 0
				? cmx_kontakte_carddav_xml_category_collection_response($category_term_id)
				: cmx_kontakte_carddav_xml_collection_response();
			$contact_ids = $category_term_id > 0
				? cmx_kontakte_carddav_group_contact_ids($category_term_id)
				: cmx_kontakte_carddav_contact_ids();
			foreach ($contact_ids as $post_id) {
				$responses[] = cmx_kontakte_carddav_xml_card_response($post_id, $include_data, $category_term_id);
			}
			if ($category_term_id <= 0) {
				foreach (cmx_kontakte_carddav_category_terms() as $term) {
					$responses[] = cmx_kontakte_carddav_xml_group_response((int) $term->term_id, $include_data);
				}
			}
		}

		echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
		echo '<D:multistatus xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:carddav" xmlns:CS="http://calendarserver.org/ns/">' . \implode('', $responses) . '</D:multistatus>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_send_home_multistatus')) {
	function cmx_kontakte_carddav_send_home_multistatus(): void {
		\status_header(207);
		\header('Content-Type: application/xml; charset=utf-8');

		$responses = [cmx_kontakte_carddav_xml_home_response()];
		foreach (cmx_kontakte_carddav_category_terms() as $term) {
			$responses[] = cmx_kontakte_carddav_xml_category_collection_response((int) $term->term_id);
		}

		echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
		echo '<D:multistatus xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:carddav" xmlns:CS="http://calendarserver.org/ns/">' . \implode('', $responses) . '</D:multistatus>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_xml_home_response')) {
	function cmx_kontakte_carddav_xml_home_response(): string {
		return '<D:response>'
			. '<D:href>' . \esc_xml(cmx_kontakte_carddav_account_path()) . '</D:href>'
			. '<D:propstat><D:prop>'
			. '<D:displayname>CMX</D:displayname>'
			. '<D:resourcetype><D:collection/></D:resourcetype>'
			. '<D:current-user-principal><D:href>' . \esc_xml(cmx_kontakte_carddav_principal_path()) . '</D:href></D:current-user-principal>'
			. '<D:principal-URL><D:href>' . \esc_xml(cmx_kontakte_carddav_principal_path()) . '</D:href></D:principal-URL>'
			. '<C:addressbook-home-set><D:href>' . \esc_xml(cmx_kontakte_carddav_account_path()) . '</D:href></C:addressbook-home-set>'
			. '</D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat>'
			. '</D:response>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_xml_collection_response')) {
	function cmx_kontakte_carddav_xml_collection_response(): string {
		return '<D:response>'
			. '<D:href>' . \esc_xml(cmx_kontakte_carddav_addressbook_path()) . '</D:href>'
			. '<D:propstat><D:prop>'
			. '<D:displayname>Alle CMX-Kontakte</D:displayname>'
			. '<D:resourcetype><D:collection/><C:addressbook/></D:resourcetype>'
			. '<CS:getctag>' . \esc_xml(cmx_kontakte_carddav_collection_ctag()) . '</CS:getctag>'
			. '<D:current-user-principal><D:href>' . \esc_xml(cmx_kontakte_carddav_principal_path()) . '</D:href></D:current-user-principal>'
			. '<D:principal-URL><D:href>' . \esc_xml(cmx_kontakte_carddav_principal_path()) . '</D:href></D:principal-URL>'
			. '<C:addressbook-home-set><D:href>' . \esc_xml(cmx_kontakte_carddav_account_path()) . '</D:href></C:addressbook-home-set>'
			. '</D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat>'
			. '</D:response>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_xml_category_collection_response')) {
	function cmx_kontakte_carddav_xml_category_collection_response(int $term_id): string {
		$term = cmx_kontakte_carddav_category_term($term_id);
		if (!$term instanceof \WP_Term) {
			return '';
		}

		return '<D:response>'
			. '<D:href>' . \esc_xml(cmx_kontakte_carddav_category_account_path($term_id)) . '</D:href>'
			. '<D:propstat><D:prop>'
			. '<D:displayname>' . \esc_xml((string) $term->name) . '</D:displayname>'
			. '<D:resourcetype><D:collection/><C:addressbook/></D:resourcetype>'
			. '<CS:getctag>' . \esc_xml(cmx_kontakte_carddav_category_collection_ctag($term_id)) . '</CS:getctag>'
			. '<D:current-user-principal><D:href>' . \esc_xml(cmx_kontakte_carddav_principal_path()) . '</D:href></D:current-user-principal>'
			. '<D:principal-URL><D:href>' . \esc_xml(cmx_kontakte_carddav_principal_path()) . '</D:href></D:principal-URL>'
			. '<C:addressbook-home-set><D:href>' . \esc_xml(cmx_kontakte_carddav_account_path()) . '</D:href></C:addressbook-home-set>'
			. '</D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat>'
			. '</D:response>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_send_root_multistatus')) {
	function cmx_kontakte_carddav_send_root_multistatus(): void {
		\status_header(207);
		\header('Content-Type: application/xml; charset=utf-8');
		echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
		echo '<D:multistatus xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:carddav">'
			. cmx_kontakte_carddav_xml_root_response()
			. '</D:multistatus>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_xml_root_response')) {
	function cmx_kontakte_carddav_xml_root_response(): string {
		return '<D:response>'
			. '<D:href>/</D:href>'
			. '<D:propstat><D:prop>'
			. '<D:displayname>Mis Buero</D:displayname>'
			. '<D:resourcetype><D:collection/></D:resourcetype>'
			. '<D:current-user-principal><D:href>' . \esc_xml(cmx_kontakte_carddav_principal_path()) . '</D:href></D:current-user-principal>'
			. '<D:principal-URL><D:href>' . \esc_xml(cmx_kontakte_carddav_principal_path()) . '</D:href></D:principal-URL>'
			. '<C:addressbook-home-set><D:href>' . \esc_xml(cmx_kontakte_carddav_account_path()) . '</D:href></C:addressbook-home-set>'
			. '</D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat>'
			. '</D:response>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_send_principal_multistatus')) {
	function cmx_kontakte_carddav_send_principal_multistatus(): void {
		\status_header(207);
		\header('Content-Type: application/xml; charset=utf-8');
		echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
		echo '<D:multistatus xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:carddav">'
			. cmx_kontakte_carddav_xml_principal_response()
			. '</D:multistatus>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_xml_principal_response')) {
	function cmx_kontakte_carddav_xml_principal_response(): string {
		return '<D:response>'
			. '<D:href>' . \esc_xml(cmx_kontakte_carddav_principal_path()) . '</D:href>'
			. '<D:propstat><D:prop>'
			. '<D:displayname>Mis Buero Kontakte</D:displayname>'
			. '<D:resourcetype><D:principal/></D:resourcetype>'
			. '<D:current-user-principal><D:href>' . \esc_xml(cmx_kontakte_carddav_principal_path()) . '</D:href></D:current-user-principal>'
			. '<D:principal-URL><D:href>' . \esc_xml(cmx_kontakte_carddav_principal_path()) . '</D:href></D:principal-URL>'
			. '<C:addressbook-home-set><D:href>' . \esc_xml(cmx_kontakte_carddav_account_path()) . '</D:href></C:addressbook-home-set>'
			. '</D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat>'
			. '</D:response>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_xml_card_response')) {
	function cmx_kontakte_carddav_xml_card_response(int $post_id, bool $include_data, int $category_term_id = 0): string {
		$vcard = cmx_kontakte_carddav_vcard($post_id);
		$href = $category_term_id > 0
			? cmx_kontakte_carddav_category_card_path($category_term_id, $post_id)
			: cmx_kontakte_carddav_card_path($post_id);
		return '<D:response>'
			. '<D:href>' . \esc_xml($href) . '</D:href>'
			. '<D:propstat><D:prop>'
			. '<D:getetag>"' . \esc_xml(cmx_kontakte_carddav_etag($post_id)) . '"</D:getetag>'
			. '<D:getcontenttype>text/vcard; charset=utf-8</D:getcontenttype>'
			. ($include_data ? '<C:address-data>' . \esc_xml($vcard) . '</C:address-data>' : '')
			. '</D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat>'
			. '</D:response>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_carddav_xml_group_response')) {
	function cmx_kontakte_carddav_xml_group_response(int $term_id, bool $include_data): string {
		$vcard = cmx_kontakte_carddav_group_vcard($term_id);
		if ($vcard === '') {
			return '';
		}

		return '<D:response>'
			. '<D:href>' . \esc_xml(cmx_kontakte_carddav_group_path($term_id)) . '</D:href>'
			. '<D:propstat><D:prop>'
			. '<D:getetag>"' . \esc_xml(cmx_kontakte_carddav_group_etag($term_id)) . '"</D:getetag>'
			. '<D:getcontenttype>text/vcard; charset=utf-8</D:getcontenttype>'
			. ($include_data ? '<C:address-data>' . \esc_xml($vcard) . '</C:address-data>' : '')
			. '</D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat>'
			. '</D:response>';
	}
}
