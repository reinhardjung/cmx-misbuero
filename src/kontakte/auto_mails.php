<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

if (!\class_exists(__NAMESPACE__ . '\\CMX_Kontakt_Auto_Mails')) {
	final class CMX_Kontakt_Auto_Mails {
		const CRON_HOOK = 'cmx_kontakt_auto_mail_process';
		const LOCK_TRANSIENT = 'cmx_kontakt_auto_mail_lock';
		const LAST_RUN_OPTION = 'cmx_kontakt_auto_mail_last_run';
		const META_BIRTHDAY_PREFIX = '_cmx_kontakt_auto_mail_geburtstag_';
		const META_JUBILEE_LAST = '_cmx_kontakt_auto_mail_firmenjubilaeum_last';
		const MORNING_HOUR = 9;

		public static function init(): void {
			\add_action(self::CRON_HOOK, [__CLASS__, 'process_due_mails']);

			if (\did_action('init')) {
				self::ensure_cron_event();
				self::maybe_process_due_on_request();
			} else {
				\add_action('init', [__CLASS__, 'ensure_cron_event'], 30);
				\add_action('init', [__CLASS__, 'maybe_process_due_on_request'], 31);
			}
		}

		public static function ensure_cron_event(): void {
			$next = (int) \wp_next_scheduled(self::CRON_HOOK);
			$target = self::next_run_timestamp();

			if ($next <= 0) {
				\wp_schedule_single_event($target, self::CRON_HOOK);
				return;
			}

			if (\abs($next - $target) > (\DAY_IN_SECONDS + \HOUR_IN_SECONDS)) {
				\wp_clear_scheduled_hook(self::CRON_HOOK);
				\wp_schedule_single_event($target, self::CRON_HOOK);
			}
		}

		public static function maybe_process_due_on_request(): void {
			if ((\function_exists('wp_doing_cron') && \wp_doing_cron()) || (\defined('DOING_AJAX') && DOING_AJAX)) {
				return;
			}

			if (!self::is_after_cutoff() || self::last_run_date() === self::today_date()) {
				return;
			}

			self::process_due_mails();
		}

		public static function process_due_mails(): void {
			if (!self::acquire_lock()) {
				return;
			}

			try {
				$today = self::today_date();
				if (self::last_run_date() === $today) {
					return;
				}

				self::process_contacts($today);
				\update_option(self::LAST_RUN_OPTION, $today, false);
			} finally {
				self::release_lock();
				self::reschedule_next_event();
			}
		}

		public static function send_manual_thank_you_mail(int $post_id, int $row_index = 0) {
			$post_id = (int) $post_id;
			$row_index = \max(0, (int) $row_index);

			if ($post_id <= 0) {
				return new \WP_Error('missing_post', 'Kontakt fehlt.');
			}

			$post = \get_post($post_id);
			if (!$post instanceof \WP_Post || (string) $post->post_type !== 'kontakte') {
				return new \WP_Error('invalid_post', 'Kontakt nicht gefunden.');
			}
			if (!self::mail_type_enabled('thanks')) {
				return new \WP_Error('mail_disabled', 'Danke-Mails sind deaktiviert.');
			}

			if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_read_contacts')) {
				return new \WP_Error('missing_contacts', 'Kommunikation ist nicht verfügbar.');
			}

			$rows = \array_values(\array_filter((array) cmx_kommunikation_read_contacts($post_id), static function ($row): bool {
				return \is_array($row);
			}));
			$row = (array) ($rows[$row_index] ?? []);
			if ($row === []) {
				return new \WP_Error('missing_row', 'Ansprechpartner nicht gefunden. Bitte zuerst speichern.');
			}

			$anrede = \trim((string) ($row['anrede'] ?? ''));
			if ($anrede === '') {
				return new \WP_Error('missing_salutation', 'Bitte zuerst eine Anrede speichern.');
			}

			$email = \sanitize_email((string) ($row['email'] ?? ''));
			if (!\is_email($email)) {
				return new \WP_Error('missing_email', 'Für diesen Ansprechpartner ist keine gültige E-Mail gespeichert.');
			}

			$payload = self::build_mail_payload('thanks', $post_id, $row, '');
			$result = self::send_contact_mail($email, $payload, 'kontakt_danke');
			if ($result instanceof \WP_Error) {
				self::log_mail_result('thanks', $post_id, $email, false, (string) $result->get_error_message());
				return $result;
			}

			self::log_mail_result('thanks', $post_id, $email, true, '');

			return [
				'email' => $email,
				'subject' => (string) ($payload['subject'] ?? ''),
			];
		}

		private static function process_contacts(string $today): void {
			$query = new \WP_Query(self::query_args());

			foreach ((array) $query->posts as $post_id) {
				$post_id = (int) $post_id;
				if ($post_id <= 0) {
					continue;
				}

				self::process_birthdays_for_contact($post_id, $today);
				self::process_company_anniversary_for_contact($post_id, $today);
			}
		}

		private static function query_args(): array {
			$meta_query = [
				'relation' => 'OR',
				[
					'key' => \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_FIRMENGRUENDUNG')
						? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_FIRMENGRUENDUNG')
						: '_cmx_kontakte_firmengruendung',
					'compare' => 'EXISTS',
				],
				[
					'key' => \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_GEBURTSDATUM')
						? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_GEBURTSDATUM')
						: '_cmx_kontakte_geburtsdatum',
					'compare' => 'EXISTS',
				],
			];

			if (\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_flat_field_meta_keys')) {
				foreach ((array) cmx_kommunikation_flat_field_meta_keys('geburtsdatum', 10) as $meta_key) {
					$meta_key = (string) $meta_key;
					if ($meta_key === '') {
						continue;
					}
					$meta_query[] = [
						'key' => $meta_key,
						'compare' => 'EXISTS',
					];
				}
			}

			return [
				'post_type' => 'kontakte',
				'post_status' => ['publish', 'private'],
				'posts_per_page' => -1,
				'fields' => 'ids',
				'no_found_rows' => true,
				'update_post_term_cache' => false,
				'suppress_filters' => true,
				'meta_query' => $meta_query,
			];
		}

		private static function process_birthdays_for_contact(int $post_id, string $today): void {
			if (!self::mail_type_enabled('birthday')) {
				return;
			}

			if (!\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_read_contacts')) {
				return;
			}

			$birthday_seen = [];

			foreach ((array) cmx_kommunikation_read_contacts($post_id) as $row) {
				if (!\is_array($row)) {
					continue;
				}

				$birthdate = self::sanitize_date_ymd((string) ($row['geburtsdatum'] ?? ''));
				if ($birthdate === '' || !self::matches_today($birthdate, $today)) {
					continue;
				}

				$email = \sanitize_email((string) ($row['email'] ?? ''));
				if (!\is_email($email)) {
					continue;
				}

				$signature = self::birthday_signature($row, $birthdate, $email);
				if ($signature === '' || isset($birthday_seen[$signature])) {
					continue;
				}

				$meta_key = self::birthday_meta_key($signature);
				if ((string) \get_post_meta($post_id, $meta_key, true) === $today) {
					$birthday_seen[$signature] = true;
					continue;
				}

				$payload = self::build_mail_payload('birthday', $post_id, $row, $birthdate);
				$result = self::send_contact_mail($email, $payload, 'kontakt_geburtstag');
				if ($result instanceof \WP_Error) {
					self::log_mail_result('birthday', $post_id, $email, false, (string) $result->get_error_message());
					continue;
				}

				\update_post_meta($post_id, $meta_key, $today);
				$birthday_seen[$signature] = true;
				self::log_mail_result('birthday', $post_id, $email, true, '');
			}
		}

		private static function process_company_anniversary_for_contact(int $post_id, string $today): void {
			if (!self::mail_type_enabled('anniversary')) {
				return;
			}

			$founding_date = self::contact_founding_date($post_id);
			if ($founding_date === '' || !self::matches_today($founding_date, $today)) {
				return;
			}

			if ((string) \get_post_meta($post_id, self::META_JUBILEE_LAST, true) === $today) {
				return;
			}

			$row = self::primary_contact_row($post_id);
			$email = self::resolve_recipient_email($post_id, $row);
			if (!\is_email($email)) {
				return;
			}

			$payload = self::build_mail_payload('anniversary', $post_id, $row, $founding_date);
			$result = self::send_contact_mail($email, $payload, 'kontakt_firmenjubilaeum');
			if ($result instanceof \WP_Error) {
				self::log_mail_result('anniversary', $post_id, $email, false, (string) $result->get_error_message());
				return;
			}

			\update_post_meta($post_id, self::META_JUBILEE_LAST, $today);
			self::log_mail_result('anniversary', $post_id, $email, true, '');
		}

		private static function build_mail_payload(string $type, int $post_id, array $row, string $event_date): array {
			$title = \trim((string) \get_the_title($post_id));
			if ($title === '') {
				$title = '#' . $post_id;
			}

			$firma_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_FIRMA')
				? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_FIRMA')
				: '_cmx_kontakte_firma';
			$anrede_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_ANREDE')
				? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_ANREDE')
				: '_cmx_kontakte_anrede';
			$vorname_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_VORNAME')
				? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_VORNAME')
				: '_cmx_kontakte_vorname';
			$nachname_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_NACHNAME')
				? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_NACHNAME')
				: '_cmx_kontakte_nachname';

			$firma = \trim((string) \get_post_meta($post_id, $firma_key, true));
			if ($firma === '') {
				$firma = $title;
			}

			$vorname = \trim((string) ($row['vorname'] ?? ''));
			if ($vorname === '') {
				$vorname = \trim((string) \get_post_meta($post_id, $vorname_key, true));
			}

			$nachname = \trim((string) ($row['nachname'] ?? ''));
			if ($nachname === '') {
				$nachname = \trim((string) \get_post_meta($post_id, $nachname_key, true));
			}

			$anrede = \trim((string) ($row['anrede'] ?? ''));
			if ($anrede === '') {
				$anrede = \trim((string) \get_post_meta($post_id, $anrede_key, true));
			}

			$birthdate = $type === 'birthday' ? $event_date : '';
			$founding = $type === 'anniversary' ? $event_date : self::contact_founding_date($post_id);
			$age = $birthdate !== '' ? self::years_since($birthdate) : 0;
			$years = $founding !== '' ? self::years_since($founding) : 0;

			$data = [
				'anrede' => $anrede,
				'anrede_text' => $anrede,
				'mail_mode' => !empty($row['duzis']) ? 'du' : 'sie',
				'duzis' => !empty($row['duzis']) ? '1' : '0',
				'vorname' => $vorname,
				'nachname' => $nachname,
				'firma' => $firma,
				'firma_bezeichnung' => $firma,
				'bezeichnung' => $title,
			];

			$subject = self::mail_subject($type, $years);
			$template = self::mail_template($type);
			$body = self::replace_template_tokens($template, $data, [
				'{geburtsdatum}' => self::format_date($birthdate),
				'{Geburtsdatum}' => self::format_date($birthdate),
				'{firmengruendung}' => self::format_date($founding),
				'{Firmengruendung}' => self::format_date($founding),
				'{alter}' => $age > 0 ? (string) $age : '',
				'{Alter}' => $age > 0 ? (string) $age : '',
				'{jahre}' => $years > 0 ? (string) $years : '',
				'{Jahre}' => $years > 0 ? (string) $years : '',
			]);

			if (\function_exists(__NAMESPACE__ . '\\cmxbu_prepare_belegmail_html')) {
				$body = (string) cmxbu_prepare_belegmail_html($body);
			} else {
				$body = self::prepare_html($body);
			}
			$body = self::replace_html_template_tokens($body);

			$message = \function_exists(__NAMESPACE__ . '\\cmx_passwort_mails_build_html')
				? (string) cmx_passwort_mails_build_html($subject, $body)
				: $body;

			return [
				'subject' => $subject,
				'message' => $message,
			];
		}

		private static function send_contact_mail(string $to, array $payload, string $context) {
			$to = \sanitize_email($to);
			if (!\is_email($to)) {
				return new \WP_Error('invalid_email', 'Keine gültige Empfänger-E-Mail gefunden.');
			}

			$subject = \trim((string) ($payload['subject'] ?? ''));
			$message = (string) ($payload['message'] ?? '');
			if ($subject === '' || $message === '') {
				return new \WP_Error('missing_payload', 'E-Mail-Inhalt fehlt.');
			}

			$wp_mail_failed_message = '';
			$wp_mail_failed_listener = static function ($error) use (&$wp_mail_failed_message): void {
				if (!$error instanceof \WP_Error) {
					return;
				}
				$msg = \trim((string) $error->get_error_message());
				if ($msg === '') {
					$messages = \array_map('strval', (array) $error->get_error_messages());
					$msg = \trim(\implode(' | ', \array_filter($messages, static function (string $item): bool {
						return $item !== '';
					})));
				}
				$wp_mail_failed_message = $msg;
			};

			$had_mail_context = \array_key_exists('cmx_mail_context', $GLOBALS);
			$previous_mail_context = $had_mail_context ? $GLOBALS['cmx_mail_context'] : null;

			$GLOBALS['cmx_mail_context'] = $context;
			\add_action('wp_mail_failed', $wp_mail_failed_listener, 10, 1);
			$embedded_logo_listener = static function ($phpmailer): void {
				if (!\function_exists(__NAMESPACE__ . '\\cmx_email_embed_self_logo_for_phpmailer')) {
					self::embed_thank_you_logo_for_phpmailer($phpmailer);
					return;
				}
				cmx_email_embed_self_logo_for_phpmailer($phpmailer);
				self::embed_thank_you_logo_for_phpmailer($phpmailer);
			};
			\add_action('phpmailer_init', $embedded_logo_listener, 100, 1);

			try {
				$sent = \wp_mail($to, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
			} finally {
				\remove_action('wp_mail_failed', $wp_mail_failed_listener, 10);
				\remove_action('phpmailer_init', $embedded_logo_listener, 100);
				if ($had_mail_context) {
					$GLOBALS['cmx_mail_context'] = $previous_mail_context;
				} else {
					unset($GLOBALS['cmx_mail_context']);
				}
			}

			if (!$sent) {
				return new \WP_Error('mail_failed', $wp_mail_failed_message !== '' ? $wp_mail_failed_message : 'E-Mail konnte nicht gesendet werden.');
			}

			return true;
		}

		private static function replace_template_tokens(string $template, array $data, array $extra_tokens): string {
			$message = \strtr($template, [
				'{das_bin_ich_url}' => self::self_contact_url_marker(),
				'{Das_bin_ich_url}' => self::self_contact_url_marker(),
				'{das_bin_ich_email}' => self::self_contact_email_marker(),
				'{Das_bin_ich_email}' => self::self_contact_email_marker(),
			]);
			if (\function_exists(__NAMESPACE__ . '\\cmxbu_belegmail_replace_content_tokens')) {
				$message = (string) cmxbu_belegmail_replace_content_tokens($message, $data);
			} else {
				$message = \strtr($message, [
					'{anrede}' => (string) ($data['anrede'] ?? ''),
					'{vorname}' => (string) ($data['vorname'] ?? ''),
					'{nachname}' => (string) ($data['nachname'] ?? ''),
					'{firma}' => (string) ($data['firma'] ?? ''),
					'{bezeichnung}' => (string) ($data['bezeichnung'] ?? ''),
				]);
			}

			return \strtr($message, $extra_tokens);
		}

		private static function replace_html_template_tokens(string $message): string {
			$thank_you_logo_html = self::thank_you_logo_html();
			$self_contact_url_html = self::self_contact_url_html();
			$self_contact_email_html = self::self_contact_email_html();

			return \strtr($message, [
				'{Danke-Logo}' => $thank_you_logo_html,
				'{danke-logo}' => $thank_you_logo_html,
				'{das_bin_ich_url}' => $self_contact_url_html,
				'{Das_bin_ich_url}' => $self_contact_url_html,
				'{das_bin_ich_email}' => $self_contact_email_html,
				'{Das_bin_ich_email}' => $self_contact_email_html,
				self::self_contact_url_marker() => $self_contact_url_html,
				self::self_contact_email_marker() => $self_contact_email_html,
			]);
		}

		private static function thank_you_logo_html(): string {
			$img_style = 'display:block;margin:0 auto;max-width:220px;height:auto;border:0;outline:none;text-decoration:none;';
			$dimension_attrs = \function_exists(__NAMESPACE__ . '\\cmx_email_inline_img_dimension_attributes')
				? (string) cmx_email_inline_img_dimension_attributes($img_style)
				: '';
			$src = self::thank_you_logo_src();
			if ($src === '') {
				return '';
			}

			$src_attr = \str_starts_with($src, 'cid:')
				? \esc_attr($src)
				: \esc_url($src);

			return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 16px auto;border-collapse:collapse;"><tr><td align="center" style="padding:0;text-align:center;"><img src="' . $src_attr . '" alt="Danke" align="center"' . $dimension_attrs . ' style="' . \esc_attr($img_style) . '"></td></tr></table>';
		}

		private static function thank_you_logo_path(): string {
			$path = \dirname(__DIR__, 2) . '/assets/merci_company.png';
			return \is_readable($path) ? $path : '';
		}

		private static function thank_you_logo_cid(): string {
			return 'cmx-thank-you-logo@cmx-misbuero';
		}

		private static function thank_you_logo_src(): string {
			$path = self::thank_you_logo_path();
			if ($path !== '') {
				return 'cid:' . self::thank_you_logo_cid();
			}

			$src = \plugins_url('assets/merci_company.png', \dirname(__DIR__, 2) . '/cmx-misbuero.php');
			return \esc_url($src);
		}

		private static function embed_thank_you_logo_for_phpmailer($phpmailer): void {
			if (!$phpmailer instanceof \PHPMailer\PHPMailer\PHPMailer) {
				return;
			}

			$logo_path = self::thank_you_logo_path();
			if ($logo_path === '') {
				return;
			}

			$filetype = \wp_check_filetype($logo_path);
			$mime = \trim((string) ($filetype['type'] ?? ''));
			if ($mime === '') {
				$mime = 'image/' . \strtolower((string) \pathinfo($logo_path, PATHINFO_EXTENSION));
			}

			try {
				$phpmailer->addEmbeddedImage($logo_path, self::thank_you_logo_cid(), \basename($logo_path), 'base64', $mime);
			} catch (\Throwable $e) {
				return;
			}
		}

		private static function self_contact_url(): string {
			if (!\function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_url')) {
				return '';
			}

			return \trim((string) cmx_email_self_contact_url());
		}

		private static function self_contact_url_marker(): string {
			return '[[CMX_SELF_CONTACT_URL_HTML]]';
		}

		private static function self_contact_email_marker(): string {
			return '[[CMX_SELF_CONTACT_EMAIL_HTML]]';
		}

		private static function self_contact_url_label(): string {
			$url = self::self_contact_url();
			if ($url === '') {
				return '';
			}

			$parts = \wp_parse_url($url);
			$host = \trim((string) ($parts['host'] ?? ''));
			$path = (string) ($parts['path'] ?? '');
			$query = (string) ($parts['query'] ?? '');
			$fragment = (string) ($parts['fragment'] ?? '');

			if ($host === '') {
				$label = \preg_replace('~^https?://~i', '', $url);
				$label = \ltrim((string) $label, '/');
				if ($label !== '' && !\preg_match('~^www\.~i', $label)) {
					$label = 'www.' . $label;
				}
				return $label;
			}

			if (!\preg_match('~^www\.~i', $host)) {
				$host = 'www.' . $host;
			}

			$label = $host . $path;
			if ($query !== '') {
				$label .= '?' . $query;
			}
			if ($fragment !== '') {
				$label .= '#' . $fragment;
			}

			return $label;
		}

		private static function self_contact_url_html(): string {
			$url = self::self_contact_url();
			$label = self::self_contact_url_label();
			if ($url === '' || $label === '') {
				return '';
			}

			return '<a href="' . \esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . \esc_html($label) . '</a>';
		}

		private static function self_contact_email(): string {
			if (!\function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_primary_email')) {
				return '';
			}

			$email = \sanitize_email((string) cmx_email_self_contact_primary_email());
			return \is_email($email) ? $email : '';
		}

		private static function self_contact_email_html(): string {
			$email = self::self_contact_email();
			if ($email === '') {
				return '';
			}

			return '<a href="' . \esc_attr('mailto:' . $email) . '">' . \esc_html($email) . '</a>';
		}

		private static function mail_template(string $type): string {
			$options = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
				? (array) \get_option((string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN'), [])
				: [];

			$key = match ($type) {
				'birthday' => 'email_kontakt_geburtstag',
				'thanks' => 'email_kontakt_dankeschoen',
				default => 'email_kontakt_firmenjubilaeum',
			};
			$template = isset($options[$key]) && \is_string($options[$key]) ? \trim((string) $options[$key]) : '';
			if ($template !== '') {
				return $template;
			}

			if ($type === 'birthday') {
				return '<p>{anrede},</p><p>ich wünsche Dir von Herzen alles Gute zum Geburtstag und einen wunderbaren Tag.</p><p>{logo}</p>';
			}
			if ($type === 'thanks') {
				return '<p>{anrede},</p><p>Danke schön für die angenehme Zusammenarbeit.</p><p>{Danke-Logo}</p>';
			}

			return '<p>{anrede},</p><p>herzliche Gratulation zum Firmenjubiläum und weiterhin viel Freude und Erfolg.</p><p>{logo}</p>';
		}

		private static function mail_type_enabled(string $type): bool {
			if (!\defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')) {
				return true;
			}

			$template_key = match ($type) {
				'birthday' => 'email_kontakt_geburtstag',
				'thanks' => 'email_kontakt_dankeschoen',
				default => 'email_kontakt_firmenjubilaeum',
			};
			$enabled_key = \function_exists(__NAMESPACE__ . '\\cmx_email_contact_template_enabled_key')
				? (string) cmx_email_contact_template_enabled_key($template_key)
				: (\sanitize_key($template_key) . '_enabled');
			$options = (array) \get_option((string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN'), []);
			if (!\array_key_exists($enabled_key, $options)) {
				return true;
			}

			return (string) ($options[$enabled_key] ?? '1') === '1';
		}

		private static function mail_subject(string $type, int $years): string {
			if ($type === 'birthday') {
				return 'Alles Gute zum Geburtstag';
			}
			if ($type === 'thanks') {
				return 'Danke schön';
			}

			if ($years > 0) {
				return 'Herzlichen Glückwunsch zum ' . $years . '. Firmenjubiläum';
			}

			return 'Herzlichen Glückwunsch zum Firmenjubiläum';
		}

		private static function resolve_recipient_email(int $post_id, array $row): string {
			$email = \sanitize_email((string) ($row['email'] ?? ''));
			if (\is_email($email)) {
				return $email;
			}

			if (\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_email')) {
				$email = \sanitize_email((string) cmx_kommunikation_primary_email($post_id));
				if (\is_email($email)) {
					return $email;
				}
			}

			return '';
		}

		private static function primary_contact_row(int $post_id): array {
			if (\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_contact')) {
				$row = (array) cmx_kommunikation_primary_contact($post_id);
				if ($row !== []) {
					return $row;
				}
			}

			if (\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_read_contacts')) {
				foreach ((array) cmx_kommunikation_read_contacts($post_id) as $row) {
					if (\is_array($row)) {
						return $row;
					}
				}
			}

			return [];
		}

		private static function contact_founding_date(int $post_id): string {
			$key = \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_FIRMENGRUENDUNG')
				? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_FIRMENGRUENDUNG')
				: '_cmx_kontakte_firmengruendung';
			return self::sanitize_date_ymd((string) \get_post_meta($post_id, $key, true));
		}

		private static function birthday_signature(array $row, string $birthdate, string $email): string {
			$parts = [
				\strtolower(\sanitize_email($email)),
				self::sanitize_date_ymd($birthdate),
				\sanitize_text_field((string) ($row['vorname'] ?? '')),
				\sanitize_text_field((string) ($row['nachname'] ?? '')),
			];

			$signature = \trim(\implode('|', $parts), '|');
			return $signature;
		}

		private static function birthday_meta_key(string $signature): string {
			return self::META_BIRTHDAY_PREFIX . \substr(\md5($signature), 0, 16);
		}

		private static function matches_today(string $raw_date, string $today = ''): bool {
			$raw_date = self::sanitize_date_ymd($raw_date);
			if ($raw_date === '') {
				return false;
			}

			$today = $today !== '' ? $today : self::today_date();
			$timezone = \wp_timezone();

			if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_pendenzen_recurring_occurrence_ts')) {
				$today_ts = (new \DateTimeImmutable($today . ' 12:00:00', $timezone))->getTimestamp();
				$year = (int) \wp_date('Y', $today_ts, $timezone);
				$occurrence_ts = (int) cmx_cockpit_pendenzen_recurring_occurrence_ts($raw_date, $year);
				if ($occurrence_ts > 0) {
					return \wp_date('Y-m-d', $occurrence_ts, $timezone) === $today;
				}
			}

			[$year, $month, $day] = \array_map('intval', \explode('-', $raw_date));
			$current_year = (int) \substr($today, 0, 4);
			if (!\checkdate($month, $day, $current_year)) {
				$day = \cal_days_in_month(\CAL_GREGORIAN, $month, $current_year);
			}

			return \sprintf('%04d-%02d-%02d', $current_year, $month, $day) === $today;
		}

		private static function years_since(string $raw_date): int {
			$raw_date = self::sanitize_date_ymd($raw_date);
			if ($raw_date === '') {
				return 0;
			}

			try {
				$timezone = \wp_timezone();
				$event_date = new \DateTimeImmutable($raw_date . ' 12:00:00', $timezone);
				$today = new \DateTimeImmutable('now', $timezone);
				$years = (int) $today->format('Y') - (int) $event_date->format('Y');
				$today_md = $today->format('md');
				$event_md = $event_date->format('md');
				if ($today_md < $event_md) {
					$years--;
				}
				return \max(0, $years);
			} catch (\Throwable $e) {
				return 0;
			}
		}

		private static function format_date(string $raw_date): string {
			$raw_date = self::sanitize_date_ymd($raw_date);
			if ($raw_date === '') {
				return '';
			}

			try {
				$date = new \DateTimeImmutable($raw_date . ' 12:00:00', \wp_timezone());
				return $date->format('d.m.Y');
			} catch (\Throwable $e) {
				return '';
			}
		}

		private static function prepare_html(string $message): string {
			if (\preg_match('/<(?:p|div|ul|ol|li|table|thead|tbody|tr|td|th|h[1-6]|blockquote|br)\b/i', $message)) {
				return $message;
			}

			if (\preg_match('/<[^>]+>/', $message)) {
				return \nl2br($message);
			}

			return \nl2br(\esc_html($message));
		}

		private static function sanitize_date_ymd(string $value): string {
			$value = \trim($value);
			if ($value === '') {
				return '';
			}

			if (\function_exists(__NAMESPACE__ . '\\cmx_sanitize_date_ymd')) {
				return (string) cmx_sanitize_date_ymd($value);
			}

			$date = \DateTime::createFromFormat('Y-m-d', $value);
			return ($date && $date->format('Y-m-d') === $value) ? $value : '';
		}

		private static function next_run_timestamp(int $from_ts = 0): int {
			$timezone = \wp_timezone();
			$base_ts = $from_ts > 0 ? $from_ts : \time();
			$base = (new \DateTimeImmutable('@' . $base_ts))->setTimezone($timezone);
			$target = $base->setTime(self::MORNING_HOUR, 0, 0);

			if ($target <= $base) {
				$target = $target->modify('+1 day');
			}

			return $target->getTimestamp();
		}

		private static function is_after_cutoff(): bool {
			$now = new \DateTimeImmutable('now', \wp_timezone());
			$cutoff = $now->setTime(self::MORNING_HOUR, 0, 0);
			return $now >= $cutoff;
		}

		private static function today_date(): string {
			return \wp_date('Y-m-d', \time(), \wp_timezone());
		}

		private static function last_run_date(): string {
			return self::sanitize_date_ymd((string) \get_option(self::LAST_RUN_OPTION, ''));
		}

		private static function acquire_lock(): bool {
			if (\get_transient(self::LOCK_TRANSIENT)) {
				return false;
			}

			return \set_transient(self::LOCK_TRANSIENT, '1', 10 * \MINUTE_IN_SECONDS);
		}

		private static function release_lock(): void {
			\delete_transient(self::LOCK_TRANSIENT);
		}

		private static function reschedule_next_event(): void {
			\wp_clear_scheduled_hook(self::CRON_HOOK);
			\wp_schedule_single_event(self::next_run_timestamp(\time() + 60), self::CRON_HOOK);
		}

		private static function log_mail_result(string $type, int $post_id, string $email, bool $success, string $message): void {
			if (!\function_exists(__NAMESPACE__ . '\\cmxbu_log')) {
				return;
			}

			cmxbu_log('KONTAKT-MAIL: ' . ($success ? 'sent' : 'failed'), [
				'type' => $type,
				'post_id' => $post_id,
				'email' => $email,
				'message' => $message,
			]);
		}
	}

	CMX_Kontakt_Auto_Mails::init();
}
