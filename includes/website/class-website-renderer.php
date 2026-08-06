<?php
namespace CLOUDMEISTER\CMX\Buero\Website;

defined('ABSPATH') || exit;

final class Renderer {
	public static function init(): void {
		self::ensure_public_cache_support();
		\add_action('init', [self::class, 'maybe_handle_public_contact_form'], 1);
		\add_action('init', [self::class, 'maybe_render_early'], 20);
		\add_action('template_redirect', [self::class, 'maybe_render'], 40);
		\add_action('wp_ajax_cmx_website_contact', [self::class, 'handle_contact_form']);
		\add_action('wp_ajax_nopriv_cmx_website_contact', [self::class, 'handle_contact_form']);
	}

	public static function contact_form_token(): string {
		return \hash_hmac('sha256', 'cmx-website-contact', \wp_salt('nonce'));
	}

	private static function contact_response_is_json(): bool {
		if (\wp_doing_ajax()) {
			return true;
		}
		$requested_with = \strtolower(\trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
		$accept = \strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
		return $requested_with === 'xmlhttprequest' || \str_contains($accept, 'application/json');
	}

	private static function contact_success(string $message): void {
		if (self::contact_response_is_json()) {
			\wp_send_json_success(['message' => $message]);
		}
		$url = (string) \add_query_arg('cmx_contact_status', 'sent', \home_url('/'));
		\wp_safe_redirect($url . '#kontakt', 303);
		exit;
	}

	private static function contact_error(string $message, int $status): void {
		if (self::contact_response_is_json()) {
			\wp_send_json_error(['message' => $message], $status);
		}
		$url = (string) \add_query_arg('cmx_contact_status', 'error', \home_url('/'));
		\wp_safe_redirect($url . '#kontakt', 303);
		exit;
	}

	public static function maybe_handle_public_contact_form(): void {
		if ((string) ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
			return;
		}
		$route = isset($_GET['cmx_website_contact']) && !\is_array($_GET['cmx_website_contact'])
			? \sanitize_text_field((string) \wp_unslash($_GET['cmx_website_contact']))
			: '';
		if ($route !== '1') {
			return;
		}

		if (!\defined('DONOTCACHEPAGE')) {
			\define('DONOTCACHEPAGE', true);
		}
		\nocache_headers();
		self::handle_contact_form();
	}

	public static function handle_contact_form(): void {
		if ((string) ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
			self::contact_error(__('Ungültige Anfrage.', 'cmx-misbuero'), 405);
		}

		$token = isset($_POST['token']) && !\is_array($_POST['token'])
			? \sanitize_text_field((string) \wp_unslash($_POST['token']))
			: '';
		if ($token === '' || !\hash_equals(self::contact_form_token(), $token)) {
			self::contact_error(__('Die Anfrage konnte nicht geprüft werden. Bitte laden Sie die Seite neu.', 'cmx-misbuero'), 403);
		}

		$settings = Settings::get();
		$contact = isset($settings['contact']) && \is_array($settings['contact']) ? $settings['contact'] : [];
		$thank_you = \sanitize_text_field((string) ($contact['thank_you_text'] ?? __('Vielen Dank für die Nachricht – wir kümmern uns darum.', 'cmx-misbuero')));
		if ($thank_you === '') {
			$thank_you = __('Vielen Dank für die Nachricht – wir kümmern uns darum.', 'cmx-misbuero');
		}

		$honeypot = isset($_POST['company_website']) && !\is_array($_POST['company_website'])
			? \trim((string) \wp_unslash($_POST['company_website']))
			: '';
		if ($honeypot !== '') {
			self::contact_success($thank_you);
		}

		$first_name = isset($_POST['first_name']) && !\is_array($_POST['first_name'])
			? \sanitize_text_field((string) \wp_unslash($_POST['first_name']))
			: '';
		$last_name = isset($_POST['last_name']) && !\is_array($_POST['last_name'])
			? \sanitize_text_field((string) \wp_unslash($_POST['last_name']))
			: '';
		$email = isset($_POST['email']) && !\is_array($_POST['email'])
			? \sanitize_email((string) \wp_unslash($_POST['email']))
			: '';
		$phone = isset($_POST['phone']) && !\is_array($_POST['phone'])
			? \sanitize_text_field((string) \wp_unslash($_POST['phone']))
			: '';
		$message = isset($_POST['message']) && !\is_array($_POST['message'])
			? \sanitize_textarea_field((string) \wp_unslash($_POST['message']))
			: '';

		if ($first_name === '' || $last_name === '' || !\is_email($email) || $message === '') {
			self::contact_error(__('Bitte füllen Sie Vorname, Nachname, E-Mail und Nachricht vollständig aus.', 'cmx-misbuero'), 400);
		}
		if (\strlen($first_name) > 100 || \strlen($last_name) > 100 || \strlen($phone) > 100 || \strlen($message) > 10000) {
			self::contact_error(__('Die Eingabe ist zu lang.', 'cmx-misbuero'), 400);
		}

		$ip = \sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
		// Versioned key immediately releases visitors who were incorrectly locked
		// out by failed mail attempts in the former implementation.
		$rate_key = 'cmx_web_contact_v2_' . \substr(\hash_hmac('sha256', $ip, \wp_salt('auth')), 0, 32);
		$rate_count = (int) \get_transient($rate_key);
		if ($rate_count >= 5) {
			self::contact_error(__('Zu viele Anfragen. Bitte versuchen Sie es später erneut.', 'cmx-misbuero'), 429);
		}

		$to = \sanitize_email((string) ($settings['email'] ?? ''));
		if (!\is_email($to) && \function_exists('CLOUDMEISTER\\CMX\\Buero\\cmx_email_self_contact_primary_email')) {
			$to = \sanitize_email((string) \CLOUDMEISTER\CMX\Buero\cmx_email_self_contact_primary_email());
		}
		if (!\is_email($to)) {
			$to = \sanitize_email((string) \get_option('admin_email', ''));
		}
		if (!\is_email($to)) {
			self::contact_error(__('Das Kontaktformular ist momentan nicht verfügbar.', 'cmx-misbuero'), 500);
		}

		$name = \trim($first_name . ' ' . $last_name);
		$company = \sanitize_text_field((string) ($settings['company_name'] ?? \get_bloginfo('name')));
		$subject = \sprintf(__('Neue Website-Nachricht von %s', 'cmx-misbuero'), $name);
		$body = "Neue Nachricht über das Website-Kontaktformular\n\n"
			. "Vorname: {$first_name}\n"
			. "Nachname: {$last_name}\n"
			. "E-Mail: {$email}\n"
			. "Telefon: " . ($phone !== '' ? $phone : '-') . "\n\n"
			. "Nachricht:\n{$message}\n\n"
			. 'Website: ' . \home_url('/');
		$headers_with_reply_to = [
			'Content-Type: text/plain; charset=UTF-8',
			'Reply-To: ' . $name . ' <' . $email . '>',
		];
		$headers_without_reply_to = ['Content-Type: text/plain; charset=UTF-8'];
		$mail_subject = ($company !== '' ? '[' . $company . '] ' : '') . $subject;
		$mail_attempts = [
			[
				'to' => $to,
				'headers' => $headers_with_reply_to,
				'label' => 'website recipient with visitor reply-to',
			],
			[
				'to' => $to,
				'headers' => $headers_without_reply_to,
				'label' => 'website recipient without visitor reply-to',
			],
		];

		// If the public website address is rejected, use the mailbox from the
		// central E-Mail settings as a last-resort recipient. This is the same
		// mailbox/transport that is used successfully for magic-login messages.
		$mail_settings = (array) \get_option('cmx_einstellungen', []);
		$fallback_to = \sanitize_email((string) ($mail_settings['email_address'] ?? ''));
		if (\is_email($fallback_to) && \strtolower($fallback_to) !== \strtolower($to)) {
			$mail_attempts[] = [
				'to' => $fallback_to,
				'headers' => $headers_without_reply_to,
				'label' => 'configured SMTP mailbox fallback',
			];
		}

		$wp_mail_failed_message = '';
		$wp_mail_failed_messages = [];
		$wp_mail_failed_listener = static function ($error) use (&$wp_mail_failed_message): void {
			if ($error instanceof \WP_Error) {
				$message = \trim((string) $error->get_error_message());
				if ($message === '') {
					$message = \trim(\implode(' | ', \array_filter(\array_map('strval', $error->get_error_messages()))));
				}
				$wp_mail_failed_message = $message;
			}
		};
		$had_mail_context = \array_key_exists('cmx_mail_context', $GLOBALS);
		$previous_mail_context = $had_mail_context ? $GLOBALS['cmx_mail_context'] : null;
		$GLOBALS['cmx_mail_context'] = 'website_contact';
		\add_action('wp_mail_failed', $wp_mail_failed_listener, 10, 1);
		try {
			$sent = false;
			foreach ($mail_attempts as $attempt) {
				$wp_mail_failed_message = '';
				$sent = (bool) \wp_mail(
					(string) $attempt['to'],
					$mail_subject,
					$body,
					(array) $attempt['headers']
				);
				if ($sent) {
					break;
				}
				$wp_mail_failed_messages[] = (string) $attempt['label'] . ': '
					. ($wp_mail_failed_message !== '' ? $wp_mail_failed_message : 'unknown wp_mail failure');
			}
		} finally {
			\remove_action('wp_mail_failed', $wp_mail_failed_listener, 10);
			if ($had_mail_context) {
				$GLOBALS['cmx_mail_context'] = $previous_mail_context;
			} else {
				unset($GLOBALS['cmx_mail_context']);
			}
		}

		if (!$sent) {
			if (!empty($wp_mail_failed_messages)) {
				\error_log('[CMX Website Contact] all wp_mail attempts failed: ' . \implode(' || ', $wp_mail_failed_messages));
			}
			$error_message = __('Die Nachricht konnte nicht gesendet werden. Bitte prüfen Sie die E-Mail-Einstellungen.', 'cmx-misbuero');
			$failure = \strtolower(\implode(' || ', $wp_mail_failed_messages));
			if (\str_contains($failure, 'auth') || \str_contains($failure, 'password') || \str_contains($failure, 'credential')) {
				$error_message = __('Die SMTP-Anmeldung ist fehlgeschlagen. Bitte prüfen Sie E-Mail-Adresse und Kennwort.', 'cmx-misbuero');
			} elseif (\str_contains($failure, 'connect') || \str_contains($failure, 'timed out') || \str_contains($failure, 'connection')) {
				$error_message = __('Der SMTP-Server ist nicht erreichbar. Bitte prüfen Sie den SMTP-Host.', 'cmx-misbuero');
			} elseif (\str_contains($failure, 'recipient') || \str_contains($failure, 'address')) {
				$error_message = __('Der Mailserver hat die Empfängeradresse abgelehnt. Bitte prüfen Sie die Website-E-Mail-Adresse.', 'cmx-misbuero');
			}
			self::contact_error($error_message, 500);
		}

		// Only successfully accepted messages count towards the anti-spam limit.
		// A temporary SMTP problem must never lock a genuine visitor out.
		\set_transient($rate_key, $rate_count + 1, 10 * MINUTE_IN_SECONDS);
		self::contact_success($thank_you);
	}

	public static function maybe_render_early(): void {
		if (\defined('WP_CLI') && WP_CLI) {
			return;
		}
		if (\is_admin() || \wp_doing_ajax() || \wp_doing_cron() || (\defined('REST_REQUEST') && REST_REQUEST)) {
			return;
		}
			if (!\in_array((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'HEAD'], true)) {
				return;
			}
			if (self::maybe_redirect_call_query()) {
				return;
			}
			if (!self::is_public_home_path_request()) {
				return;
			}

		$settings = Settings::get();
		if (empty($settings['enabled'])) {
			\status_header(200);
			\nocache_headers();
			self::render_disabled_placeholder_response();
			exit;
		}

		\status_header(200);
		\nocache_headers();
		self::render_response($settings);
		exit;
	}

	public static function maybe_render(): void {
		if (\defined('WP_CLI') && WP_CLI) {
			return;
		}
			if (\is_admin() || \wp_doing_ajax() || \wp_doing_cron()) {
				return;
			}
			if (self::maybe_redirect_call_query()) {
				return;
			}
			if (!self::is_public_home_request()) {
				return;
			}

		$settings = Settings::get();
		if (empty($settings['enabled'])) {
			\status_header(200);
			\nocache_headers();
			self::render_disabled_placeholder_response();
			exit;
		}

		\status_header(200);
		\nocache_headers();
		self::render_response($settings);
		exit;
	}

	private static function render_response(array $settings): void {
		\ob_start();
		self::render($settings);
		$html = (string) \ob_get_clean();
		self::write_public_home_cache($html);
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private static function render_disabled_placeholder_response(): void {
		\ob_start();
		self::render_disabled_placeholder();
		$html = (string) \ob_get_clean();
		self::write_public_home_cache($html);
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function render(array $settings): void {
		$colors = Settings::color_variants((string) ($settings['primary_color'] ?? '#0F63F6'));
		$css_url = \plugins_url('assets/css/website.css', \dirname(__DIR__, 2) . '/cmx-misbuero.php');
		$css_path = \dirname(__DIR__, 2) . '/assets/css/website.css';
		$css_version = \is_file($css_path) ? (string) \filemtime($css_path) : '1';
		$css_inline = \is_readable($css_path) ? (string) \file_get_contents($css_path) : '';
		$template = \dirname(__DIR__, 2) . '/templates/website/home.php';

		if (!\is_file($template)) {
			return;
		}

		include $template;
	}

	private static function render_disabled_placeholder(): void {
		$image_url = \plugins_url('assets/favicon.png', \dirname(__DIR__, 2) . '/cmx-misbuero.php');
		$image_path = \dirname(__DIR__, 2) . '/assets/favicon.png';
		if (\is_file($image_path)) {
			$image_url = (string) \add_query_arg('ver', (string) \filemtime($image_path), $image_url);
		}
		?>
<!doctype html>
<html <?php \language_attributes(); ?>>
<head>
	<meta charset="<?php echo \esc_attr((string) \get_bloginfo('charset')); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php echo \function_exists('CLOUDMEISTER\\CMX\\Buero\\cmx_misbuero_favicon_links') ? \CLOUDMEISTER\CMX\Buero\cmx_misbuero_favicon_links() : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<style>
		html,body{width:100%;min-height:100vh;margin:0;background:#fff;}
		body{background-image:url("<?php echo \esc_url($image_url); ?>");background-repeat:no-repeat;background-position:center center;background-size:min(56vw,620px) auto;}
		@media(max-width:782px){body{background-size:78vw auto;}}
	</style>
</head>
<body></body>
</html>
		<?php
	}

	public static function asset_image(string $rel_path, string $class, string $alt = ''): string {
		$rel_path = self::normalize_file_path($rel_path);
		if ($rel_path === '') {
			return '';
		}

		$url = self::module_file_url($rel_path);
		if ($url === '') {
			return '';
		}

		return '<img src="' . \esc_url($url) . '" class="' . \esc_attr($class) . '" alt="' . \esc_attr($alt) . '" loading="lazy" decoding="async">';
	}

	public static function logo_image(string $rel_path, string $class, string $alt = ''): string {
		$image = self::asset_image($rel_path, $class, $alt);
		if ($image !== '') {
			return $image;
		}

		$url = \function_exists('CLOUDMEISTER\\CMX\\Buero\\cmx_email_self_logo_url')
			? \trim((string) \CLOUDMEISTER\CMX\Buero\cmx_email_self_logo_url())
			: '';
		if (\str_contains($url, '/wp-content/plugins/cmx-misbuero/assets/logo_fallback.png')) {
			return '';
		}
		if ($url === '') {
			return '';
		}

		return '<img src="' . \esc_url($url) . '" class="' . \esc_attr($class) . '" alt="' . \esc_attr($alt) . '" loading="lazy" decoding="async">';
	}

	public static function preview_image_url(string $rel_path = ''): string {
		$self_logo = \function_exists('CLOUDMEISTER\\CMX\\Buero\\cmx_email_self_logo_url')
			? \trim((string) \CLOUDMEISTER\CMX\Buero\cmx_email_self_logo_url())
			: '';
		if ($self_logo !== '' && !\str_contains($self_logo, '/wp-content/plugins/cmx-misbuero/assets/logo_fallback.png')) {
			return $self_logo;
		}

		$asset_url = self::module_file_url($rel_path);
		if ($asset_url !== '') {
			return $asset_url;
		}

		return \function_exists('CLOUDMEISTER\\CMX\\Buero\\cmx_misbuero_favicon_url')
			? \trim((string) \CLOUDMEISTER\CMX\Buero\cmx_misbuero_favicon_url())
			: '';
	}

	public static function module_file_url(string $rel_path): string {
		$rel_path = self::normalize_file_path($rel_path);
		if ($rel_path === '') {
			return '';
		}
		if (\function_exists('CLOUDMEISTER\\CMX\\Buero\\cmx_dav_module_file_path')) {
			$file_path = (string) \CLOUDMEISTER\CMX\Buero\cmx_dav_module_file_path('website', $rel_path);
			if ($file_path === '' || !\is_file($file_path) || !\is_readable($file_path)) {
				return '';
			}
		}
		if (\function_exists('CLOUDMEISTER\\CMX\\Buero\\cmx_dav_module_file_url')) {
			return (string) \CLOUDMEISTER\CMX\Buero\cmx_dav_module_file_url('website', $rel_path);
		}
		return (string) \home_url('/webssite/' . \implode('/', \array_map('rawurlencode', \explode('/', $rel_path))));
	}

	public static function website_articles(int $limit = 12): array {
		$query = new \WP_Query([
			'post_type'              => 'artikel',
			'post_status'            => 'publish',
			'posts_per_page'         => 160,
			'orderby'                => ['menu_order' => 'ASC', 'title' => 'ASC'],
			'order'                  => 'ASC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
			'suppress_filters'       => true,
		]);

		$items = [];
		foreach ((array) $query->posts as $post) {
			if (!$post instanceof \WP_Post || \post_password_required($post)) {
				continue;
			}

			$post_id = (int) $post->ID;
			$currency = self::article_currency($post_id);
			$image_url = self::article_image_url($post_id);
			$post_excerpt = self::compact_text((string) ($post->post_excerpt ?: $post->post_content), 240, true);
			foreach (self::article_variant_rows($post_id) as $index => $row) {
				if (empty($row['website']) || !empty($row['archiviert']) || !empty($row['nicht_mehr_lieferbar'])) {
					continue;
				}

				$description = self::compact_text((string) ($row['belegtext'] ?? ''), 220, true);
				if ($description === '') {
					$description = $post_excerpt;
				}
				$detail = self::compact_text((string) ($post->post_content ?? ''), 1200, true);
				if ($detail === '') {
					$detail = $description;
				}
				$belegtext = self::compact_text((string) ($row['belegtext'] ?? ''), 900, true);
				if ($belegtext !== '' && $belegtext !== $detail) {
					$detail .= ($detail !== '' ? "\n\n" : '') . $belegtext;
				}

				$items[] = [
					'id' => 'mib-artikel-' . $post_id . '-' . ((int) $index + 1),
					'post_id' => $post_id,
					'title' => (string) \get_the_title($post_id),
					'sku' => \sanitize_text_field((string) ($row['sku'] ?? '')),
					'description' => $description,
					'detail' => $detail,
					'price_label' => self::article_price_label((string) ($row['vk'] ?? ''), $currency),
					'image_url' => $image_url,
				];

				if (\count($items) >= $limit) {
					return $items;
				}
			}
		}

		return $items;
	}

	private static function article_variant_rows(int $post_id): array {
		$fields = [
			'sku',
			'anzahl',
			'vk',
			'belegtext',
			'verkaufbar',
			'katalog',
			'website',
			'onlineshop',
			'pos',
			'nicht_mehr_lieferbar',
			'archiviert',
		];
		$count = (int) \get_post_meta($post_id, '_cmx_artikel_variant_count', true);
		$rows = [];

		for ($slot = 1; $slot <= $count; $slot++) {
			$row = [];
			$has_value = false;
			foreach ($fields as $field) {
				$value = \get_post_meta($post_id, '_cmx_artikel_variant_' . $slot . '_' . $field, true);
				$row[$field] = $value;
				if ((string) $value !== '') {
					$has_value = true;
				}
			}
			if ($has_value) {
				$rows[] = self::normalize_public_variant_row($row);
			}
		}

		if ($rows !== []) {
			return $rows;
		}

		$stored = \get_post_meta($post_id, '_cmx_artikel_variant_rows', true);
		if (\is_array($stored)) {
			foreach ($stored as $row) {
				if (\is_array($row)) {
					$rows[] = self::normalize_public_variant_row($row);
				}
			}
		}

		return $rows;
	}

	private static function normalize_public_variant_row(array $row): array {
		foreach (['verkaufbar', 'katalog', 'website', 'onlineshop', 'pos', 'nicht_mehr_lieferbar', 'archiviert'] as $field) {
			$row[$field] = !empty($row[$field]) ? 1 : 0;
		}
		foreach (['sku', 'vk', 'belegtext'] as $field) {
			$row[$field] = isset($row[$field]) ? (string) $row[$field] : '';
		}
		return $row;
	}

	private static function article_currency(int $post_id): string {
		$currency = \strtoupper(\sanitize_text_field((string) \get_post_meta($post_id, '_cmx_artikel_waehrungen', true)));
		return \preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'CHF';
	}

	private static function article_price_label(string $raw, string $currency): string {
		$value = self::parse_decimal($raw);
		if ($value === null || $value <= 0) {
			return '';
		}
		return \trim($currency . ' ' . \number_format_i18n($value, 2));
	}

	private static function parse_decimal(string $raw): ?float {
		$raw = \trim($raw);
		if ($raw === '') {
			return null;
		}
		$value = \str_replace(["'", ' '], '', $raw);
		if (\str_contains($value, ',') && !\str_contains($value, '.')) {
			$value = \str_replace(',', '.', $value);
		}
		if (!\is_numeric($value)) {
			return null;
		}
		$number = (float) $value;
		return \is_finite($number) ? $number : null;
	}

	private static function article_image_url(int $post_id): string {
		$gallery = \get_post_meta($post_id, '_cmx_local_image_artikel_gallery', true);
		if (\is_array($gallery)) {
			foreach ($gallery as $item) {
				$url = \trim((string) ($item['url'] ?? ''));
				if ($url !== '') {
					return $url;
				}
			}
		}

		$url = \trim((string) \get_post_meta($post_id, '_cmx_local_image_artikel_url', true));
		if ($url !== '') {
			return $url;
		}

		$thumb_id = (int) \get_post_thumbnail_id($post_id);
		if ($thumb_id > 0) {
			return (string) \wp_get_attachment_image_url($thumb_id, 'large');
		}

		return '';
	}

	private static function compact_text(string $text, int $limit, bool $preserve_breaks = false): string {
		$text = \strip_shortcodes($text);
		$text = \wp_strip_all_tags($text);
		if ($preserve_breaks) {
			$text = \str_replace(["\r\n", "\r"], "\n", $text);
			$text = (string) \preg_replace('/[ \t\x0B\f]+/u', ' ', $text);
			$text = (string) \preg_replace('/ *\n */u', "\n", $text);
			$text = (string) \preg_replace('/\n{3,}/u', "\n\n", $text);
			$text = \trim($text);
		} else {
			$text = \trim((string) \preg_replace('/\s+/u', ' ', $text));
		}
		if ($text === '' || \strlen($text) <= $limit) {
			return $text;
		}
		if (\function_exists('\\mb_substr')) {
			return \rtrim((string) \mb_substr($text, 0, $limit - 1, 'UTF-8')) . '…';
		}
		return \rtrim(\substr($text, 0, $limit - 1)) . '...';
	}

	private static function normalize_file_path(string $rel_path): string {
		$rel_path = \str_replace('\\', '/', \trim($rel_path));
		$parts = [];
		foreach (\explode('/', $rel_path) as $part) {
			$part = \sanitize_file_name($part);
			if ($part === '' || $part === '.' || $part === '..') {
				continue;
			}
			$parts[] = $part;
		}
		return \implode('/', $parts);
	}

	public static function link_url(string $url, string $fallback = '#'): string {
		$url = \trim($url);
		return $url !== '' ? $url : $fallback;
	}

	public static function phone_url(string $phone): string {
		$clean = \preg_replace('/[^\d+]+/', '', $phone);
		return $clean ? 'tel:' . $clean : '#kontakt';
	}

	public static function opening_hours_rows(array $section): array {
		$days = isset($section['days']) && \is_array($section['days']) ? $section['days'] : [];
		$rows = [];
		foreach (self::opening_hour_day_labels() as $day_key => $day_label) {
			$slots = isset($days[$day_key]) && \is_array($days[$day_key]) ? $days[$day_key] : [];
			$clean_slots = [];
			foreach ($slots as $slot) {
				if (!\is_array($slot)) {
					continue;
				}
				$start = self::opening_hour_time_label((string) ($slot['start'] ?? ''));
				$end = self::opening_hour_time_label((string) ($slot['end'] ?? ''));
				if ($start === '' || $end === '') {
					continue;
				}
				$clean_slots[] = $start . ' - ' . $end;
			}
			if ($clean_slots === []) {
				continue;
			}
			$rows[] = [
				'key' => $day_key,
				'label' => $day_label,
				'slots' => $clean_slots,
			];
		}
		return $rows;
	}

	public static function booking_services(int $limit = 0): array {
		$fn = 'CLOUDMEISTER\\CMX\\Buero\\cmx_buchungen_frontend_services';
		if (!\function_exists($fn)) {
			return [];
		}

		$services = (array) \call_user_func($fn);
		$out = [];
		foreach ($services as $service) {
			if (!\is_array($service)) {
				continue;
			}
			$unit = (string) ($service['unit'] ?? 'minutes');
			if (!\in_array($unit, ['minutes', 'hours', 'days'], true)) {
				$unit = 'minutes';
			}
			$duration = \max(1, (int) ($service['duration'] ?? ($unit === 'days' ? 1 : 60)));
			$duration_minutes = (int) ($service['duration_minutes'] ?? 0);
			if ($duration_minutes <= 0 && \function_exists('CLOUDMEISTER\\CMX\\Buero\\cmx_buchungen_frontend_duration_minutes')) {
				$duration_minutes = (int) \CLOUDMEISTER\CMX\Buero\cmx_buchungen_frontend_duration_minutes($unit, $duration);
			}
			if ($duration_minutes <= 0) {
				$duration_minutes = $unit === 'days' ? $duration * 1440 : ($unit === 'hours' ? $duration * 60 : $duration);
			}
			$out[] = [
				'id' => \sanitize_key((string) ($service['id'] ?? '')),
				'artikel_id' => \max(0, (int) ($service['artikel_id'] ?? 0)),
				'title' => self::compact_text((string) ($service['title'] ?? ''), 90),
				'person' => self::compact_text((string) ($service['person'] ?? ''), 70),
				'duration' => self::booking_duration_label($service),
				'duration_value' => $duration,
				'duration_minutes' => $duration_minutes,
				'unit' => $unit,
				'period' => (string) ($service['period'] ?? 'all'),
				'date_from' => (string) ($service['date_from'] ?? ''),
				'date_to' => (string) ($service['date_to'] ?? ''),
				'weekdays' => self::booking_weekdays((array) ($service['weekdays'] ?? [1, 2, 3, 4, 5])),
				'cr' => \in_array($service['cr'] ?? false, [true, 1, '1'], true),
				'image' => (string) ($service['image'] ?? ''),
				'avatar_label' => self::compact_text((string) ($service['avatar_label'] ?? ($service['title'] ?? '?')), 20),
				'color' => self::sanitize_service_color((string) ($service['color'] ?? '#2563eb')),
				'url' => self::booking_url(),
			];
			if ($limit > 0 && \count($out) >= $limit) {
				break;
			}
		}
		return $out;
	}

	public static function booking_calendar_data(array $services): array {
		$slots_fn = 'CLOUDMEISTER\\CMX\\Buero\\cmx_buchungen_frontend_slots';
		$day_dates_fn = 'CLOUDMEISTER\\CMX\\Buero\\cmx_buchungen_frontend_day_dates';
		$hour_options_fn = 'CLOUDMEISTER\\CMX\\Buero\\cmx_buchungen_frontend_hour_amount_options_for_slots';
		$interval_fn = 'CLOUDMEISTER\\CMX\\Buero\\cmx_buchungen_frontend_slot_interval';
		$slots = [];
		$day_dates = [];
		$amount_options = [];
		$slot_interval = \function_exists($interval_fn) ? \max(15, (int) \call_user_func($interval_fn)) : 15;
		if (!\in_array($slot_interval, [15, 30, 60], true)) {
			$slot_interval = 15;
		}

		foreach ($services as $service) {
			if (!\is_array($service)) {
				continue;
			}
			$service_id = (string) ($service['id'] ?? '');
			$artikel_id = \max(0, (int) ($service['artikel_id'] ?? 0));
			$weekdays = self::booking_weekdays((array) ($service['weekdays'] ?? [1, 2, 3, 4, 5]));
			$date_from = (string) ($service['date_from'] ?? '');
			$date_to = (string) ($service['date_to'] ?? '');
			if ($service_id === '' || $artikel_id <= 0) {
				continue;
			}
			if ((string) ($service['unit'] ?? 'minutes') === 'days') {
				if (!\function_exists($day_dates_fn)) {
					continue;
				}
				$service_day_dates = [];
				for ($days = 1; $days <= 60; $days++) {
					$service_day_dates[(string) $days] = (array) \call_user_func($day_dates_fn, $days, 90, $weekdays, $artikel_id, $date_from, $date_to);
				}
				$day_dates[$service_id] = $service_day_dates;
				continue;
			}
			if (!\function_exists($slots_fn)) {
				continue;
			}
			$service_slots = (array) \call_user_func(
				$slots_fn,
				\max(1, (int) ($service['duration_minutes'] ?? 60)),
				42,
				$weekdays,
				$artikel_id,
				$slot_interval,
				$date_from,
				$date_to
			);
			$slots[$service_id] = $service_slots;
			if ((string) ($service['unit'] ?? 'minutes') === 'hours' && \function_exists($hour_options_fn)) {
				$amount_options[$service_id] = (array) \call_user_func(
					$hour_options_fn,
					$service_slots,
					60,
					(string) ($service['period'] ?? 'all'),
					$weekdays,
					$artikel_id,
					$date_from,
					$date_to
				);
			}
		}

		return [
			'slots' => $slots,
			'day_dates' => $day_dates,
			'amount_options' => $amount_options,
		];
	}

	public static function booking_url(): string {
		$fn = 'CLOUDMEISTER\\CMX\\Buero\\cmx_buchungen_frontend_url';
		return \function_exists($fn) ? (string) \call_user_func($fn) : (string) \home_url('/buchungen/');
	}

	private static function booking_duration_label(array $service): string {
		$fn = 'CLOUDMEISTER\\CMX\\Buero\\cmx_buchungen_frontend_duration_label';
		$duration = (int) ($service['duration_minutes'] ?? $service['duration'] ?? 0);
		$unit = (string) ($service['unit'] ?? '');
		if ($duration > 0 && \function_exists($fn)) {
			return (string) \call_user_func($fn, $duration, $unit);
		}
		return $duration > 0 ? (string) $duration . ' Minuten' : '';
	}

	private static function sanitize_service_color(string $hex): string {
		return \preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1 ? $hex : '#2563eb';
	}

	private static function booking_weekdays(array $weekdays): array {
		$out = [];
		foreach ($weekdays as $weekday) {
			$weekday = (int) $weekday;
			if ($weekday >= 1 && $weekday <= 7) {
				$out[] = $weekday;
			}
		}
		$out = \array_values(\array_unique($out));
		return $out !== [] ? $out : [1, 2, 3, 4, 5];
	}

	private static function opening_hour_day_labels(): array {
		return [
			'mon' => __('Montag', 'cmx-misbuero'),
			'tue' => __('Dienstag', 'cmx-misbuero'),
			'wed' => __('Mittwoch', 'cmx-misbuero'),
			'thu' => __('Donnerstag', 'cmx-misbuero'),
			'fri' => __('Freitag', 'cmx-misbuero'),
			'sat' => __('Samstag', 'cmx-misbuero'),
			'sun' => __('Sonntag', 'cmx-misbuero'),
		];
	}

	private static function opening_hour_time_label(string $time): string {
		$time = \trim($time);
		if (\preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
			return '';
		}
		return $time;
	}

		private static function is_public_home_request(): bool {
		if (!self::is_public_home_path_request()) {
			return false;
		}

		return \is_front_page() || \is_home();
		}

		private static function maybe_redirect_call_query(): bool {
			$path = (string) \wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), \PHP_URL_PATH);
			$path = \trim($path, '/');
			if ($path === 'call' || \str_starts_with($path, 'call/')) {
				return false;
			}

			$query = isset($_SERVER['QUERY_STRING']) ? \trim((string) $_SERVER['QUERY_STRING']) : '';
			if ($query === '') {
				return false;
			}

			$params = [];
			\parse_str($query, $params);
			if (\trim((string) ($params['number'] ?? '')) === '') {
				return false;
			}

			$allowed = [];
			foreach (['number', 'account', 'dnid', 'callid'] as $key) {
				if (isset($params[$key]) && !\is_array($params[$key])) {
					$allowed[$key] = \sanitize_text_field((string) $params[$key]);
				}
			}
			if ($allowed === []) {
				return false;
			}

			\wp_safe_redirect((string) \add_query_arg($allowed, \home_url('/call')), 302);
			exit;
		}

		private static function is_public_home_path_request(): bool {
		$path = (string) \wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), \PHP_URL_PATH);
		$home_path = (string) \wp_parse_url(\home_url('/'), \PHP_URL_PATH);
		$home_path = '/' . \trim($home_path, '/');
		if ($home_path === '/') {
			$home_path = '';
		}
		$relative_path = '/' . \trim(\substr($path, \strlen($home_path)), '/');
		if ($relative_path !== '/') {
			return false;
		}

		$query = isset($_SERVER['QUERY_STRING']) ? \trim((string) $_SERVER['QUERY_STRING']) : '';
		if ($query !== '') {
			\parse_str($query, $query_args);
			if (\count($query_args) !== 1) {
				return false;
			}
			if (isset($query_args['cmx_magic_login_status'])) {
				$status = \sanitize_key((string) $query_args['cmx_magic_login_status']);
				if (!\in_array($status, ['sent', 'wait', 'expired', 'invalid', 'error'], true)) {
					return false;
				}
			} elseif (isset($query_args['cmx_contact_status'])) {
				$status = \sanitize_key((string) $query_args['cmx_contact_status']);
				if (!\in_array($status, ['sent', 'error'], true)) {
					return false;
				}
			} else {
				return false;
			}
		}

		return true;
	}

	private static function public_cache_dir(): string {
		return \trailingslashit((string) \WP_CONTENT_DIR) . 'cache/cmx-misbuero-public';
	}

	private static function public_cache_file(): string {
		$host = \strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
		$host = (string) \preg_replace('~:\d+$~', '', $host);
		$host = \sanitize_file_name($host);
		if ($host === '') {
			$host = 'default';
		}

		return self::public_cache_dir() . '/' . $host . '/index.html';
	}

	private static function public_cache_signature(): string {
		$root = \dirname(__DIR__, 2);
		$dependencies = [
			$root . '/cmx-misbuero.php',
			$root . '/assets/css/website.css',
			$root . '/includes/website/class-website-renderer.php',
			$root . '/includes/website/advanced-cache-dropin.php',
			$root . '/templates/website/home.php',
			$root . '/includes/login_manager.php',
		];
		$context = \hash_init('sha256');
		foreach ($dependencies as $dependency) {
			if (!\is_file($dependency) || !\is_readable($dependency)) {
				continue;
			}
			$digest = \hash_file('sha256', $dependency);
			if (\is_string($digest)) {
				\hash_update($context, $dependency . "\0" . $digest . "\0");
			}
		}
		return \hash_final($context);
	}

	private static function write_public_home_cache(string $html): void {
		if ($html === '' || \is_user_logged_in() || !self::is_public_home_path_request()) {
			return;
		}
		if (\str_contains($html, 'cmx_buchungen_frontend_nonce')) {
			return;
		}
		if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
			return;
		}
		if (\trim((string) ($_SERVER['QUERY_STRING'] ?? '')) !== '') {
			return;
		}

		$file = self::public_cache_file();
		$dir = \dirname($file);
		if (!\wp_mkdir_p($dir) || !\is_writable($dir)) {
			return;
		}

		$tmp = $file . '.' . \uniqid('tmp-', true);
		if (@\file_put_contents($tmp, $html, LOCK_EX) === false) {
			return;
		}
		if (!@\rename($tmp, $file)) {
			return;
		}

		$signature = self::public_cache_signature();
		$signature_file = $file . '.signature';
		$signature_tmp = $signature_file . '.' . \uniqid('tmp-', true);
		if (@\file_put_contents($signature_tmp, $signature, LOCK_EX) !== false) {
			@\rename($signature_tmp, $signature_file);
		}
	}

	public static function clear_public_cache(): void {
		$dir = self::public_cache_dir();
		if (!\is_dir($dir)) {
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($iterator as $item) {
			if (!$item instanceof \SplFileInfo) {
				continue;
			}
			$item->isDir() ? @\rmdir($item->getPathname()) : @\unlink($item->getPathname());
		}
		@\rmdir($dir);
	}

	private static function ensure_public_cache_support(): void {
		if (\defined('WP_INSTALLING') && WP_INSTALLING) {
			return;
		}

		self::install_public_cache_dropin();
		self::enable_wp_cache_constant();
	}

	private static function install_public_cache_dropin(): bool {
		$source = \dirname(__DIR__, 2) . '/includes/website/advanced-cache-dropin.php';
		$target = \trailingslashit((string) \WP_CONTENT_DIR) . 'advanced-cache.php';
		if (!\is_readable($source)) {
			return false;
		}

		$source_contents = (string) \file_get_contents($source);
		if ($source_contents === '') {
			return false;
		}
		$marker = 'CMX Mis Buero public-page cache drop-in';
		$current = \is_readable($target) ? (string) \file_get_contents($target) : '';
		if ($current === $source_contents) {
			return true;
		}
		$is_cmx_dropin = $current === ''
			|| \str_contains($current, $marker)
			|| \str_contains($current, 'Early public-page cache for Mis Buero onepager instances');
		if (!$is_cmx_dropin) {
			return false;
		}

		$dir = \dirname($target);
		if (!\wp_mkdir_p($dir) || !\is_writable($dir)) {
			return false;
		}

		$tmp = $target . '.' . \uniqid('tmp-', true);
		if (@\file_put_contents($tmp, $source_contents, LOCK_EX) === false) {
			return false;
		}

		return @\rename($tmp, $target);
	}

	private static function enable_wp_cache_constant(): bool {
		$config = self::wp_config_path();
		if ($config === '' || !\is_readable($config) || !\is_writable($config)) {
			return false;
		}

		$contents = (string) \file_get_contents($config);
		if ($contents === '') {
			return false;
		}
		if (\preg_match("~define\s*\(\s*['\"]WP_CACHE['\"]\s*,\s*true\s*\)~i", $contents)
			|| \preg_match("~defined\s*\(\s*['\"]WP_CACHE['\"]\s*\)\s*\|\|\s*define\s*\(\s*['\"]WP_CACHE['\"]\s*,\s*true\s*\)~i", $contents)
		) {
			return true;
		}

		$line = "defined( 'WP_CACHE' ) || define( 'WP_CACHE', true );";
		$updated = (string) \preg_replace(
			"~(?:defined\s*\(\s*['\"]WP_CACHE['\"]\s*\)\s*\|\|\s*)?define\s*\(\s*['\"]WP_CACHE['\"]\s*,\s*(?:false|0)\s*\)\s*;?~i",
			$line,
			$contents,
			1,
			$count
		);
		if ($count === 0) {
			$patterns = [
				'~\R/\*\s*That\'s all, stop editing!? Happy publishing\.\s*\*/~i',
				'~\R/\*\*\s*Absolute path to the WordPress directory\.\s*\*/~i',
			];
			foreach ($patterns as $pattern) {
				if (\preg_match($pattern, $contents)) {
					$updated = (string) \preg_replace($pattern, "\n" . $line . "\n$0", $contents, 1);
					break;
				}
			}
			if ($updated === $contents) {
				$updated = \rtrim($contents) . "\n\n" . $line . "\n";
			}
		}

		if ($updated === $contents) {
			return true;
		}

		$tmp = $config . '.' . \uniqid('tmp-', true);
		if (@\file_put_contents($tmp, $updated, LOCK_EX) === false) {
			return false;
		}

		return @\rename($tmp, $config);
	}

	private static function wp_config_path(): string {
		$candidates = [
			\trailingslashit((string) \ABSPATH) . 'wp-config.php',
			\dirname((string) \ABSPATH) . '/wp-config.php',
		];
		foreach ($candidates as $candidate) {
			if (\is_file($candidate)) {
				return $candidate;
			}
		}

		return '';
	}
}
