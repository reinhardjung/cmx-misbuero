<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

require_once __DIR__ . '/../belege/vorlage_mail.php';
require_once __DIR__ . '/../belege/vorlage_mail mahnung.php';

/**
 * Dashboard-Widget: Offene Belege
 * - zeigt alle offenen, faelligen Rechnungen und Gutschriften
 * - Titel enthaelt die Gesamtanzahl aller offenen, faelligen Rechnungen und Gutschriften
 * - Klick auf Widget-Titel springt in die Belege-Liste mit aktivem Filter:
 *   offen + Rechnungen/Gutschriften
 */

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_taxonomy')) {
	function cmx_cockpit_beleg_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_belege_taxonomy')) {
			$tax = (string) cmx_belege_taxonomy();
			if ($tax !== '' && \taxonomy_exists($tax)) {
				return $tax;
			}
		}
		foreach (['belege_kategorien', 'beleg_kategorie'] as $tax) {
			if (\taxonomy_exists($tax)) {
				return $tax;
			}
		}
		return '';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_rechnung_term_slug')) {
	function cmx_cockpit_rechnung_term_slug(string $taxonomy): string {
		foreach (['rechnung', 'rechnungen'] as $slug) {
			$exists = \term_exists($slug, $taxonomy);
			if ($exists !== 0 && $exists !== null) {
				return $slug;
			}
		}
		return 'rechnung';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_is_unpaid_beleg')) {
	function cmx_cockpit_is_unpaid_beleg(int $post_id): bool {
		$paid_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT')
			: '_cmx_beleg_bezahlt_am';
		$keys = \array_values(\array_unique(\array_filter([$paid_key, \ltrim($paid_key, '_')])));

		foreach ($keys as $key) {
			$val = \trim((string) \get_post_meta($post_id, $key, true));
			if ($val === '' || $val === '0' || $val === '0000-00-00' || $val === '0000-00-00 00:00:00') {
				continue;
			}
			// Nur gueltige Datumswerte als "bezahlt" behandeln; kaputte/legacy Werte bleiben offen.
			if (cmx_cockpit_parse_date_to_ts($val) > 0) {
				return false;
			}
		}
		return true;
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_due_raw')) {
	function cmx_cockpit_due_raw(int $post_id): string {
		$keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_FAELLIG')) {
			$keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_FAELLIG');
		}
		$keys = \array_merge($keys, [
			'_cmx_beleg_faelligkeitsdatum',
			'_cmx_beleg_faellig_am',
			'cmx_beleg_faelligkeitsdatum',
			'cmx_beleg_faellig_am',
		]);

		foreach (\array_values(\array_unique($keys)) as $key) {
			$val = \trim((string) \get_post_meta($post_id, $key, true));
			if ($val !== '') {
				return $val;
			}
		}
		return '';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_kontakt_data')) {
	function cmx_cockpit_beleg_kontakt_data(int $post_id): array {
		$meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT')
			: '_cmx_beleg_kontakt_id';
		$keys = \array_values(\array_unique(\array_filter([$meta_key, \ltrim($meta_key, '_')])));

		$kontakt_id = 0;
		foreach ($keys as $key) {
			$val = (int) \get_post_meta($post_id, $key, true);
			if ($val > 0) {
				$kontakt_id = $val;
				break;
			}
		}
		if ($kontakt_id <= 0) {
			return ['name' => '', 'url' => ''];
		}

		$name = \trim((string) \get_the_title($kontakt_id));
		if ($name === '') {
			$name = '#' . $kontakt_id;
		}
		$url = (string) \get_edit_post_link($kontakt_id, '');
		return ['name' => $name, 'url' => $url];
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_parse_date_to_ts')) {
	function cmx_cockpit_parse_date_to_ts(string $raw): int {
		$raw = \trim($raw);
		if ($raw === '') {
			return 0;
		}

		if (\ctype_digit($raw) && \strlen($raw) >= 9 && \strlen($raw) <= 11) {
			return (int) $raw;
		}

		if (\ctype_digit($raw) && \strlen($raw) === 8) {
			$y = \substr($raw, 0, 4);
			$m = \substr($raw, 4, 2);
			$d = \substr($raw, 6, 2);
			$ts = \strtotime($y . '-' . $m . '-' . $d . ' 00:00:00');
			return $ts ? (int) $ts : 0;
		}

		foreach (['Y-m-d', 'd.m.Y', 'Y/m/d', 'd/m/Y'] as $fmt) {
			$dt = \DateTime::createFromFormat($fmt, $raw);
			if ($dt instanceof \DateTime) {
				return $dt->getTimestamp();
			}
		}

		$ts = \strtotime($raw);
		return $ts ? (int) $ts : 0;
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_parse_decimal')) {
	function cmx_cockpit_parse_decimal(string $raw): float {
		$raw = \trim($raw);
		if ($raw === '') {
			return 0.0;
		}
		$txt = \str_replace(["\xc2\xa0", ' '], '', $raw);
		$txt = \preg_replace('/[^0-9,.\-]/', '', $txt);
		if (!\is_string($txt) || $txt === '') {
			return 0.0;
		}
		if (\strpos($txt, ',') !== false && \strpos($txt, '.') !== false) {
			$txt = \str_replace("'", '', $txt);
			$txt = \str_replace(',', '.', \str_replace('.', '', $txt));
		} elseif (\strpos($txt, ',') !== false) {
			$txt = \str_replace(',', '.', $txt);
		} else {
			$txt = \str_replace("'", '', $txt);
		}
		return \is_numeric($txt) ? (float) $txt : 0.0;
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_amount_tooltip')) {
	function cmx_cockpit_beleg_amount_display(int $post_id): string {
		$total = null;

		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
			$calc = (array) cmxbu_get_beleg_positionen_calc($post_id);
			if (isset($calc['total']) && \is_numeric($calc['total'])) {
				$total = (float) $calc['total'];
			}
		}

		$override_raw = \trim((string) \get_post_meta($post_id, '_cmx_beleg_summe_override', true));
		if ($override_raw !== '') {
			$total = cmx_cockpit_parse_decimal($override_raw);
		}

		if ($total === null) {
			return '';
		}

		return \function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')
			? (string) cmx_format_swiss_number((float) $total, 2)
			: \number_format((float) $total, 2, '.', "'");
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_amount_tooltip')) {
	function cmx_cockpit_beleg_amount_tooltip(int $post_id): string {
		$formatted = cmx_cockpit_beleg_amount_display($post_id);
		return $formatted !== '' ? ('Betrag: CHF ' . $formatted) : '';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_type_label')) {
	function cmx_cockpit_beleg_type_label(int $post_id): string {
		$type_label = '';

		if (\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_raw_type')) {
			$post = \get_post($post_id);
			if ($post instanceof \WP_Post) {
				$type = (string) cmxbu_beleg_export_raw_type($post);
				if (\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_normalize_type')) {
					$type = (string) cmxbu_beleg_export_normalize_type($type);
				}
				if ($type !== '') {
					$type_label = \function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_ucfirst')
						? (string) cmxbu_beleg_export_ucfirst($type)
						: \ucfirst($type);
				}
			}
		}

		if ($type_label === '') {
			$tax = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_taxonomy')
				? (string) cmx_cockpit_beleg_taxonomy()
				: '';
			if ($tax !== '' && \taxonomy_exists($tax)) {
				$terms = \wp_get_post_terms($post_id, $tax, ['fields' => 'names']);
				if (!\is_wp_error($terms) && !empty($terms[0])) {
					$type_label = (string) $terms[0];
				}
			}
		}

		return $type_label;
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_type_tooltip')) {
	function cmx_cockpit_beleg_type_tooltip(int $post_id): string {
		$type_label = cmx_cockpit_beleg_type_label($post_id);
		return $type_label !== '' ? ('Belegtyp: ' . $type_label) : '';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_direction_label')) {
	function cmx_cockpit_beleg_direction_label(int $post_id): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_admin_richtung_short_label')) {
			$label = (string) cmx_beleg_admin_richtung_short_label($post_id);
			if ($label !== '') {
				return \function_exists('mb_strtolower')
					? (string) \mb_strtolower($label, 'UTF-8')
					: \strtolower($label);
			}
		}

		$raw = \sanitize_key((string) \get_post_meta($post_id, '_cmx_beleg_richtung', true));
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_normalize_richtung')) {
			$raw = (string) cmxbu_beleg_export_normalize_richtung($raw);
		}

		if ($raw === 'ausgang') {
			return 'ausgang';
		}
		if ($raw === 'eingang') {
			return 'eingang';
		}

		return '';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_mahnwesen_contact_id')) {
	function cmx_cockpit_mahnwesen_contact_id(int $post_id): int {
		$keys = ['_cmx_beleg_kontakt_id', 'cmx_beleg_kontakt_id'];
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID')) {
			$keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID');
		}

		foreach (\array_values(\array_unique($keys)) as $key) {
			$val = (int) \get_post_meta($post_id, $key, true);
			if ($val > 0) {
				return $val;
			}
		}

		return 0;
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_mahnwesen_contact_email')) {
	function cmx_cockpit_mahnwesen_contact_email(int $post_id): string {
		$kontakt_id = cmx_cockpit_mahnwesen_contact_id($post_id);
		if ($kontakt_id <= 0) {
			return '';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_contact_primary_email')) {
			$email = \sanitize_email((string) cmxbu_get_contact_primary_email($kontakt_id));
			if (\is_email($email)) {
				return $email;
			}
		}

		$email = \sanitize_email((string) \get_post_meta($kontakt_id, '_cmx_email_1', true));
		return \is_email($email) ? $email : '';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_mahnwesen_amount_value')) {
	function cmx_cockpit_mahnwesen_amount_value(int $post_id): float {
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
			$calc = (array) cmxbu_get_beleg_positionen_calc($post_id);
			if (isset($calc['total']) && \is_numeric($calc['total'])) {
				return (float) $calc['total'];
			}
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_parse_decimal')) {
			return (float) cmx_cockpit_parse_decimal((string) \get_post_meta($post_id, '_cmx_beleg_summe_override', true));
		}

		return 0.0;
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_mahnwesen_amount_display')) {
	function cmx_cockpit_mahnwesen_amount_display(int $post_id, float $amount_value): string {
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_amount_display')) {
			$display = \trim((string) cmxbu_get_beleg_amount_display($post_id));
			if ($display !== '') {
				return $display;
			}
		}

		return \function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')
			? (string) cmx_format_swiss_number($amount_value, 2)
			: \number_format($amount_value, 2, '.', "'");
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_mahnwesen_due_display')) {
	function cmx_cockpit_mahnwesen_due_display(int $post_id, string $due_raw, int $due_ts): string {
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_due_date_display')) {
			$display = \trim((string) cmxbu_get_beleg_due_date_display($post_id));
			if ($display !== '') {
				return $display;
			}
		}

		if ($due_ts > 0) {
			return (string) \date_i18n('d.m.Y', $due_ts);
		}

		return \trim($due_raw);
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_mahnwesen_is_outgoing_invoice')) {
	function cmx_cockpit_mahnwesen_is_outgoing_invoice(int $post_id): bool {
		$richtung = \sanitize_key((string) \get_post_meta($post_id, '_cmx_beleg_richtung', true));
		if ($richtung !== 'ausgang') {
			return false;
		}

		if (\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_raw_type')) {
			$post = \get_post($post_id);
			if ($post instanceof \WP_Post) {
				$type = (string) cmxbu_beleg_export_raw_type($post);
				if (\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_normalize_type')) {
					$type = (string) cmxbu_beleg_export_normalize_type($type);
				}
				if ($type !== '') {
					return ($type === 'rechnung');
				}
			}
		}

		$tax = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_taxonomy')
			? (string) cmx_cockpit_beleg_taxonomy()
			: '';
		if ($tax !== '' && \taxonomy_exists($tax)) {
			$terms = \wp_get_post_terms($post_id, $tax, ['fields' => 'slugs']);
			if (!\is_wp_error($terms)) {
				foreach ((array) $terms as $slug) {
					$slug = \sanitize_key((string) $slug);
					if ($slug === 'rechnung' || $slug === 'rechnungen') {
						return true;
					}
				}
			}
		}

		return false;
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_mahnwesen_is_overdue')) {
	function cmx_cockpit_mahnwesen_is_overdue(int $post_id): bool {
		$due_raw = cmx_cockpit_due_raw($post_id);
		$due_ts = cmx_cockpit_parse_date_to_ts($due_raw);
		$today_start_ts = (int) \strtotime(\current_time('Y-m-d') . ' 00:00:00');

		return $due_ts > 0 && $today_start_ts > 0 && $due_ts < $today_start_ts;
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_mahnwesen_send_mail')) {
	function cmx_cockpit_mahnwesen_send_mail(int $post_id) {
		$post = \get_post($post_id);
		if (!$post instanceof \WP_Post || $post->post_type !== 'belege') {
			return new \WP_Error('invalid_post', 'Beleg nicht gefunden.');
		}

		if (!cmx_cockpit_mahnwesen_is_outgoing_invoice($post_id)) {
			return new \WP_Error('invalid_type', 'Versand nur für Ausgangs-Rechnungen möglich.');
		}

		$to = cmx_cockpit_mahnwesen_contact_email($post_id);
		if (!\is_email($to)) {
			return new \WP_Error('missing_email', 'Keine gültige Empfänger-E-Mail gefunden.');
		}

		$opts_general = (array) \get_option('cmx_einstellungen', []);
		$configured_sender = \sanitize_email((string) ($opts_general['email_address'] ?? ''));
		if (!\is_email($configured_sender)) {
			return new \WP_Error('missing_sender', 'Bitte hinterlege zuerst eine gültige Absender-E-Mail in den Einstellungen.');
		}

		$kontakt_id = cmx_cockpit_mahnwesen_contact_id($post_id);
		$anrede_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_ANREDE')
			? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_ANREDE')
			: '_cmx_kontakte_anrede';
		$anrede = $kontakt_id > 0 ? \trim((string) \get_post_meta($kontakt_id, $anrede_key, true)) : '';
		$vorname = $kontakt_id > 0 ? \trim((string) \get_post_meta($kontakt_id, '_cmx_kontakte_vorname', true)) : '';
		$nachname = $kontakt_id > 0 ? \trim((string) \get_post_meta($kontakt_id, '_cmx_kontakte_nachname', true)) : '';

		$beleg_id = (string) ($post->post_title ?? '');
		$beleg_label = 'Rechnung';
		$token = \function_exists(__NAMESPACE__ . '\\cmxbu_get_stable_token')
			? (string) cmxbu_get_stable_token($post_id)
			: '';
		$download_url = $token !== '' ? (string) \add_query_arg('beleg', $token, \home_url('/')) : '';
		$faellig_bis = \function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_due_date_display')
			? (string) cmxbu_get_beleg_due_date_display($post_id)
			: '';
		$betrag = \function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_amount_display')
			? (string) cmxbu_get_beleg_amount_display($post_id)
			: cmx_cockpit_mahnwesen_amount_display($post_id, cmx_cockpit_mahnwesen_amount_value($post_id));
		$is_overdue = cmx_cockpit_mahnwesen_is_overdue($post_id);
		$beleg_mail_date = \function_exists(__NAMESPACE__ . '\\cmxbu_get_mail_subject_beleg_date')
			? (string) cmxbu_get_mail_subject_beleg_date($post_id)
			: '';

		$mail_data = [
			'anrede' => $anrede,
			'vorname' => $vorname,
			'nachname' => $nachname,
			'beleg_label' => $beleg_label,
			'beleg_id' => $beleg_id,
			'download_url' => $download_url,
			'faellig_bis' => $faellig_bis,
			'betrag' => $betrag,
			'site_name' => \get_bloginfo('name'),
			'catalog_url' => \function_exists(__NAMESPACE__ . '\\cmx_katalog_online') && cmx_katalog_online()
				? \home_url('/katalog/')
				: '',
		];

		if ($is_overdue) {
			$subject = 'Zahlungserinnerung: ' . $beleg_label . ($beleg_id !== '' ? ' ' . $beleg_id : '');
			if ($beleg_mail_date !== '') {
				$subject .= ' vom ' . $beleg_mail_date;
			}
			$message = cmxbu_render_belegmail_mahnung_template($mail_data);
			$mail_action_label = 'Zahlungserinnerung';
		} else {
			$subject = 'Rechnung erneut: ' . $beleg_label . ($beleg_id !== '' ? ' ' . $beleg_id : '');
			if ($beleg_mail_date !== '') {
				$subject .= ' vom ' . $beleg_mail_date;
			}
			$message = cmxbu_render_belegmail_template($mail_data);
			$mail_action_label = 'Rechnung erneut';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmxbu_prepare_belegmail_html')) {
			$message = cmxbu_prepare_belegmail_html($message);
		}

		$headers = ['Content-Type: text/html; charset=UTF-8'];
		$had_sender_override = \array_key_exists('cmx_force_current_user_mail_sender', $GLOBALS);
		$previous_sender_override = $had_sender_override ? $GLOBALS['cmx_force_current_user_mail_sender'] : null;
		$had_mail_context = \array_key_exists('cmx_mail_context', $GLOBALS);
		$previous_mail_context = $had_mail_context ? $GLOBALS['cmx_mail_context'] : null;
		$wp_mail_failed_message = '';
		$wp_mail_failed_listener = static function ($error) use (&$wp_mail_failed_message): void {
			if (!$error instanceof \WP_Error) {
				return;
			}
			$msg = \trim((string) $error->get_error_message());
			if ($msg === '') {
				$all = \array_map('strval', (array) $error->get_error_messages());
				$msg = \trim(\implode(' | ', \array_filter($all, static function (string $item): bool {
					return $item !== '';
				})));
			}
			$wp_mail_failed_message = $msg;
		};

		$GLOBALS['cmx_force_current_user_mail_sender'] = true;
		$GLOBALS['cmx_mail_context'] = 'beleg_mahnung';
		\add_action('wp_mail_failed', $wp_mail_failed_listener, 10, 1);
		try {
			$sent = \wp_mail($to, $subject, $message, $headers);
		} finally {
			\remove_action('wp_mail_failed', $wp_mail_failed_listener, 10);
			if ($had_sender_override) {
				$GLOBALS['cmx_force_current_user_mail_sender'] = $previous_sender_override;
			} else {
				unset($GLOBALS['cmx_force_current_user_mail_sender']);
			}
			if ($had_mail_context) {
				$GLOBALS['cmx_mail_context'] = $previous_mail_context;
			} else {
				unset($GLOBALS['cmx_mail_context']);
			}
		}

		if (!$sent) {
			return new \WP_Error('mail_failed', $wp_mail_failed_message !== '' ? $wp_mail_failed_message : 'E-Mail konnte nicht gesendet werden.');
		}

		return [
			'email' => $to,
			'subject' => $subject,
			'action_label' => $mail_action_label,
		];
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_mahnwesen_ajax_send_mail')) {
	function cmx_cockpit_mahnwesen_ajax_send_mail(): void {
		if (!\current_user_can('edit_posts')) {
			\wp_send_json_error('Keine Berechtigung.', 403);
		}

		\check_ajax_referer('cmx_mahnwesen_send_mail');

		$post_id = isset($_POST['post_id']) ? (int) \wp_unslash($_POST['post_id']) : 0;
		if ($post_id <= 0) {
			\wp_send_json_error('Beleg-ID fehlt.', 400);
		}

		$result = cmx_cockpit_mahnwesen_send_mail($post_id);
		if ($result instanceof \WP_Error) {
			\wp_send_json_error((string) $result->get_error_message(), 400);
		}

		\wp_send_json_success($result);
	}
}
\add_action('wp_ajax_cmx_mahnwesen_send_mail', __NAMESPACE__ . '\\cmx_cockpit_mahnwesen_ajax_send_mail');

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_faellige_rechnungen_data')) {
	function cmx_cockpit_faellige_rechnungen_data(): array {
		static $cache = [];
		$preset = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_requested_preset')
			? (string) cmx_cockpit_requested_preset()
			: 'dieses_jahr';
		if (isset($cache[$preset]) && \is_array($cache[$preset])) {
			return $cache[$preset];
		}
		$range = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_requested_range')
			? (array) cmx_cockpit_requested_range()
			: ['from' => '', 'to' => ''];

		$tax = cmx_cockpit_beleg_taxonomy();
		$term_slugs = [];
		if ($tax !== '') {
			$terms = \get_terms([
				'taxonomy'   => $tax,
				'hide_empty' => false,
			]);
			if (!\is_wp_error($terms) && \is_array($terms)) {
				foreach ($terms as $term) {
					if (!($term instanceof \WP_Term)) {
						continue;
					}
					$slug = \sanitize_title((string) ($term->slug ?? ''));
					$name = \trim((string) ($term->name ?? ''));
					$name_lc = \function_exists('mb_strtolower')
						? \mb_strtolower($name, 'UTF-8')
						: \strtolower($name);

					$is_invoice_slug = ($slug === 'rechnung' || $slug === 'rechnungen');
					$is_invoice_name = ($name_lc === 'rechnung' || $name_lc === 'rechnungen');
					$is_invoice_variant = (\strpos($slug, 'rechnung-') === 0 && \strpos($slug, 'lieferanten') === false);
					$is_credit_slug = ($slug === 'gutschrift' || $slug === 'gutschriften');
					$is_credit_name = ($name_lc === 'gutschrift' || $name_lc === 'gutschriften');

					if ($is_invoice_slug || $is_invoice_name || $is_invoice_variant || $is_credit_slug || $is_credit_name) {
						$term_slugs[] = $slug;
					}
				}
			}
		}
		if (empty($term_slugs)) {
			$term_slugs = ['rechnung', 'rechnungen', 'gutschrift', 'gutschriften'];
		} else {
			$term_slugs = \array_values(\array_unique($term_slugs));
		}
		$term_slug_for_link = \implode(',', $term_slugs);

		$list_args = [
			'post_type'        => 'belege',
			'cmx_bezahlfilter' => 'offen',
		];
		if ($tax !== '') {
			$list_args[$tax] = $term_slug_for_link;
		}

		$list_url = \add_query_arg($list_args, \admin_url('edit.php'));

		if ($tax === '') {
			$cache[$preset] = [
				'total'    => 0,
				'items'    => [],
				'list_url' => $list_url,
			];
			return $cache[$preset];
		}

		$q = new \WP_Query([
			'post_type'               => 'belege',
			'post_status'             => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'          => -1,
			'fields'                  => 'ids',
			'no_found_rows'           => true,
			'update_post_meta_cache'  => false,
			'update_post_term_cache'  => false,
			'tax_query'               => [
				[
					'taxonomy' => $tax,
					'field'    => 'slug',
					'terms'    => $term_slugs,
					'operator' => 'IN',
				],
			],
		]);

		$rows = [];

		foreach ((array) $q->posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0) {
				continue;
			}
			if (!cmx_cockpit_is_unpaid_beleg($post_id)) {
				continue;
			}

			$due_raw = cmx_cockpit_due_raw($post_id);
			$due_ts  = cmx_cockpit_parse_date_to_ts($due_raw);
			if (
				\function_exists(__NAMESPACE__ . '\\cmx_cockpit_date_in_range')
				&& !cmx_cockpit_date_in_range((string) ($due_raw !== '' ? $due_raw : ''), $range)
			) {
				continue;
			}
			$due_sort = $due_ts > 0 ? $due_ts : PHP_INT_MAX;
			$today_start_ts = (int) \strtotime(\current_time('Y-m-d') . ' 00:00:00');
			$is_overdue = $due_ts > 0 && $today_start_ts > 0 && $due_ts < $today_start_ts;

			$title = \trim((string) \get_the_title($post_id));
			if ($title === '') {
				$title = '#' . $post_id;
			}

					$kontakt_data = cmx_cockpit_beleg_kontakt_data($post_id);
					$amount_tooltip = cmx_cockpit_beleg_amount_tooltip($post_id);
					$amount_display = cmx_cockpit_beleg_amount_display($post_id);
					$type_label = cmx_cockpit_beleg_type_label($post_id);
					$direction_label = cmx_cockpit_beleg_direction_label($post_id);
					$kontakt_email = cmx_cockpit_mahnwesen_contact_email($post_id);
					$can_send_reminder = $kontakt_email !== '' && cmx_cockpit_mahnwesen_is_outgoing_invoice($post_id);
					$type_tooltip = $type_label !== '' ? ('Belegtyp: ' . $type_label) : '';
					$group_label = $type_label !== '' ? $type_label : 'Ohne Belegtyp';
					if ($direction_label !== '') {
						$group_label .= ' / ' . $direction_label;
					}

				$rows[] = [
					'id'       => $post_id,
				'title'    => $title,
				'kontakt'  => (string) ($kontakt_data['name'] ?? ''),
				'kontakt_url' => (string) ($kontakt_data['url'] ?? ''),
				'due_sort' => $due_sort,
					'due_ts'   => $due_ts,
					'due_date' => cmx_cockpit_mahnwesen_due_display($post_id, $due_raw, $due_ts),
						'is_overdue' => $is_overdue,
						'edit_url' => (string) \get_edit_post_link($post_id, ''),
						'amount_tooltip' => $amount_tooltip,
						'amount_display' => $amount_display,
						'kontakt_email' => $kontakt_email,
						'can_send_reminder' => $can_send_reminder,
						'type_label' => $type_label,
						'direction_label' => $direction_label,
						'group_label' => $group_label,
						'type_tooltip' => $type_tooltip,
					];
				}

		\usort($rows, static function (array $a, array $b): int {
			$cmp = ((int) ($a['due_sort'] ?? PHP_INT_MAX)) <=> ((int) ($b['due_sort'] ?? PHP_INT_MAX));
			if ($cmp !== 0) {
				return $cmp;
			}
			return \strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
		});

		$total = \count($rows);
		$items = $rows;

		$cache[$preset] = [
			'total'    => $total,
			'items'    => $items,
			'list_url' => $list_url,
		];
		return $cache[$preset];
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_render_faellige_rechnungen_rows')) {
	function cmx_render_faellige_rechnungen_rows(array $items): void {
		$grouped_items = [];
		foreach ($items as $row) {
			$type_label = \trim((string) ($row['type_label'] ?? ''));
			if ($type_label === '') {
				$type_label = 'Ohne Belegtyp';
			}
			$direction_label = \trim((string) ($row['direction_label'] ?? ''));
			$group_key = $type_label . '|' . $direction_label;
			if (!isset($grouped_items[$group_key])) {
				$grouped_items[$group_key] = [
					'type_label' => $type_label,
					'direction_label' => $direction_label,
					'rows' => [],
				];
			}
			$grouped_items[$group_key]['rows'][] = $row;
		}

		foreach ($grouped_items as $group) {
			$group = (array) $group;
			$group_type = (string) ($group['type_label'] ?? '');
			$group_direction = (string) ($group['direction_label'] ?? '');
			$group_rows = (array) ($group['rows'] ?? []);
			echo '<tr class="cmx-faellig-group-row"><td colspan="4">';
			echo '<span class="cmx-faellig-group-type">' . \esc_html($group_type) . '</span>';
			if ($group_direction !== '') {
				echo ' / <span class="cmx-faellig-group-direction">' . \esc_html($group_direction) . '</span>';
			}
			echo '</td></tr>';
			foreach ($group_rows as $row) {
				$post_id = (int) ($row['id'] ?? 0);
				$title   = (string) ($row['title'] ?? ('#' . $post_id));
				$due     = (string) ($row['due_date'] ?? '');
				$kontakt = (string) ($row['kontakt'] ?? '');
				$kontakt_url = (string) ($row['kontakt_url'] ?? '');
				$edit    = (string) ($row['edit_url'] ?? '');
				$amount_tooltip = (string) ($row['amount_tooltip'] ?? '');
				$amount_display = (string) ($row['amount_display'] ?? '');
				$kontakt_email = (string) ($row['kontakt_email'] ?? '');
				$can_send_reminder = !empty($row['can_send_reminder']);
				$is_overdue = !empty($row['is_overdue']);
				$type_tooltip = (string) ($row['type_tooltip'] ?? '');
				if ($amount_display === '' && $amount_tooltip !== '') {
					$amount_display = (string) \str_replace('Betrag: CHF ', '', $amount_tooltip);
				}

				echo '<tr>';
				echo '<td class="cmx-faellig-title-cell" style="padding:4px 10px 4px 5px;vertical-align:top;white-space:nowrap;">';
				if ($edit !== '') {
					$title_attr = $type_tooltip !== '' ? (' title="' . \esc_attr($type_tooltip) . '"') : '';
					echo '<a class="cmx-faellig-title-link" href="' . \esc_url($edit) . '"' . $title_attr . '>' . \esc_html($title) . '</a>';
				} else {
					$title_attr = $type_tooltip !== '' ? (' title="' . \esc_attr($type_tooltip) . '"') : '';
					echo '<span class="cmx-faellig-title-text"' . $title_attr . '>' . \esc_html($title) . '</span>';
				}
				echo '</td>';
				echo '<td style="padding:4px 10px 4px 0;vertical-align:top;white-space:nowrap;">';
				$due_class = $is_overdue ? ' cmx-faellig-due-overdue' : '';
				$due_title = $is_overdue
					? ('Klicken um Zahlungserinnerung zu senden an ' . $kontakt_email)
					: ('Klicken um Rechnung erneut zu senden an ' . $kontakt_email);
				if ($can_send_reminder) {
					echo '<button type="button" class="cmx-faellig-due-btn' . $due_class . '" data-post-id="' . (int) $post_id . '" title="' . \esc_attr($due_title) . '">';
					echo \esc_html($due);
					echo '</button>';
				} else {
					$due_attr = $amount_tooltip !== '' ? (' title="' . \esc_attr($amount_tooltip) . '"') : '';
					echo '<span class="cmx-faellig-due-text' . $due_class . '"' . $due_attr . '>' . \esc_html($due) . '</span>';
				}
				echo '</td>';
				echo '<td style="padding:4px 0;vertical-align:top;">';
				$kontakt_attr = $kontakt !== '' ? (' title="' . \esc_attr($kontakt) . '"') : '';
				if ($kontakt !== '' && $kontakt_url !== '') {
					echo '<a class="cmx-faellig-contact-link" href="' . \esc_url($kontakt_url) . '"' . $kontakt_attr . '>' . \esc_html($kontakt) . '</a>';
				} else {
					echo '<span class="cmx-faellig-contact"' . $kontakt_attr . '>' . \esc_html($kontakt) . '</span>';
				}
				echo '</td>';
				echo '<td class="cmx-faellig-pay-cell" style="padding:4px 5px 4px 0;vertical-align:top;">';
				if ($post_id > 0) {
					echo '<span class="cmx-faellig-pay-wrap">';
					echo '<button type="button" class="cmx-faellig-mark-paid cmx-faellig-pay-btn" data-beleg="' . (int) $post_id . '" title="Bezahlt am wählen" aria-label="Bezahlt am wählen">';
					echo \esc_html($amount_display);
					echo '</button>';
					echo '<input type="date" class="cmx-faellig-pay-date" data-beleg="' . (int) $post_id . '" aria-label="Bezahlt am wählen">';
					echo '</span>';
				}
				echo '</td>';
				echo '</tr>';
			}
		}
	}
}

\add_action('wp_dashboard_setup', __NAMESPACE__ . '\\cmx_register_rechnungen_faellig_widget');
function cmx_register_rechnungen_faellig_widget(): void {
	if (!\current_user_can('edit_posts')) {
		return;
	}

	$data = cmx_cockpit_faellige_rechnungen_data();
	$title = 'Offene Belege (' . (int) ($data['total'] ?? 0) . ')';
	$title_link = '<a href="' . \esc_url((string) ($data['list_url'] ?? '')) . '" style="font-weight:700;font-size:14px;text-decoration:none;">' . \esc_html($title) . '</a>';

	\wp_add_dashboard_widget(
		'cmx_rechnungen_faellig_widget',
		$title_link,
		__NAMESPACE__ . '\\cmx_render_rechnungen_faellig_widget'
	);
}

function cmx_render_rechnungen_faellig_widget(): void {
	if (!\current_user_can('edit_posts')) {
		echo '<p>' . \esc_html__('Keine Berechtigung.', 'default') . '</p>';
		return;
	}

	$data = cmx_cockpit_faellige_rechnungen_data();
	$total = (int) ($data['total'] ?? 0);
	$items = (array) ($data['items'] ?? []);

	if ($total <= 0) {
		echo '<p>Keine offenen Belege</p>';
		return;
	}

			echo '<style>
				#cmx_rechnungen_faellig_widget .cmx-faellig-title-link{display:block;white-space:nowrap;text-decoration:none;overflow:hidden;text-overflow:ellipsis}
				#cmx_rechnungen_faellig_widget .cmx-faellig-title-link:hover{text-decoration:underline}
				#cmx_rechnungen_faellig_widget .cmx-faellig-table{width:100%;border-collapse:collapse;table-layout:fixed}
				#cmx_rechnungen_faellig_widget .cmx-faellig-table tbody tr:not(.cmx-faellig-group-row) td{transition:background-color .15s ease}
				#cmx_rechnungen_faellig_widget .cmx-faellig-table tbody tr:not(.cmx-faellig-group-row):hover td{background:#f7fbff}
				#cmx_rechnungen_faellig_widget .cmx-faellig-title-cell{overflow:hidden}
				#cmx_rechnungen_faellig_widget .cmx-faellig-title-text{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
				#cmx_rechnungen_faellig_widget .cmx-faellig-pay-cell{text-align:right}
			#cmx_rechnungen_faellig_widget .cmx-faellig-pay-wrap{position:relative;display:inline-block}
			#cmx_rechnungen_faellig_widget .cmx-faellig-pay-btn{cursor:pointer;border:1px solid #dcdcde;background:#f6f7f7;border-radius:4px;padding:2px 8px;line-height:1.4;font-weight:600;color:#1d2327}
			#cmx_rechnungen_faellig_widget .cmx-faellig-pay-btn:hover{border-color:#2271b1;color:#2271b1;background:#fff}
			#cmx_rechnungen_faellig_widget .cmx-faellig-pay-date{position:absolute;inset:0;opacity:0;pointer-events:none;width:100%;height:100%;border:0;padding:0;margin:0}
			#cmx_rechnungen_faellig_widget .cmx-faellig-group-row td{padding:8px 0 4px;font-size:11px;font-weight:700;color:#646970;letter-spacing:.04em}
			#cmx_rechnungen_faellig_widget .cmx-faellig-group-type{text-transform:uppercase}
			#cmx_rechnungen_faellig_widget .cmx-faellig-group-direction{text-transform:none}
			#cmx_rechnungen_faellig_widget .cmx-faellig-contact,
			#cmx_rechnungen_faellig_widget .cmx-faellig-contact-link{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
			#cmx_rechnungen_faellig_widget .cmx-faellig-contact-link{text-decoration:none}
			#cmx_rechnungen_faellig_widget .cmx-faellig-contact-link:hover{text-decoration:underline}
			#cmx_rechnungen_faellig_widget .cmx-faellig-due-btn{display:inline-block;border:0;background:transparent;padding:0;margin:0;color:#2271b1;cursor:pointer;text-decoration:none;font:inherit}
			#cmx_rechnungen_faellig_widget .cmx-faellig-due-btn:hover{text-decoration:underline}
			#cmx_rechnungen_faellig_widget .cmx-faellig-due-btn[disabled]{color:#8c8f94;cursor:default;text-decoration:none}
			#cmx_rechnungen_faellig_widget .cmx-faellig-due-overdue{color:#b32d2e}
			#cmx_rechnungen_faellig_widget .cmx-faellig-due-btn.cmx-faellig-due-overdue:hover{color:#8f2424}
		</style>';
		echo '<table class="cmx-faellig-table">';
		echo '<colgroup>';
		echo '<col style="width:118px;">';
		echo '<col style="width:86px;">';
		echo '<col>';
		echo '<col style="width:88px;">';
		echo '</colgroup>';
		echo '<tbody>';
		cmx_render_faellige_rechnungen_rows($items);
		echo '</tbody></table>';
}

\add_action('admin_footer-index.php', function (): void {
	if (!\current_user_can('edit_posts')) {
		return;
	}

	$data = cmx_cockpit_faellige_rechnungen_data();
	$list_url = (string) ($data['list_url'] ?? '');
	if ($list_url === '') {
		return;
	}
	$paid_nonce = \wp_create_nonce('cmx_mark_paid');
	$reminder_nonce = \wp_create_nonce('cmx_mahnwesen_send_mail');
	$ajax_url = \admin_url('admin-ajax.php');
	?>
		<script>
		(function(){
			var box = document.getElementById('cmx_rechnungen_faellig_widget');
		if (!box) return;
		var hndle = box.querySelector('.hndle, .postbox-header h2');
		if (!hndle) return;
		hndle.style.cursor = 'pointer';
		hndle.addEventListener('click', function(e){
			if (e.target && e.target.closest('a')) return;
			e.preventDefault();
			e.stopPropagation();
			window.location.href = <?php echo \wp_json_encode($list_url); ?>;
		});

			function submitPaidDate(belegId, paidDate, btn){
				var body = new URLSearchParams();
				body.set('action', 'cmx_mark_beleg_paid');
				body.set('post_id', String(belegId));
				body.set('paid_date', paidDate);
				body.set('_ajax_nonce', <?php echo \wp_json_encode($paid_nonce); ?>);

				fetch(<?php echo \wp_json_encode($ajax_url); ?>, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: body.toString()
			}).then(function(resp){
				return resp.json();
			}).then(function(resp){
				if (resp && resp.success) {
					window.location.reload();
					return;
				}
				throw new Error((resp && resp.data) ? String(resp.data) : 'Fehler beim Speichern.');
				}).catch(function(err){
					alert(err && err.message ? err.message : 'Fehler beim Speichern.');
					btn.dataset.loading = '';
					btn.disabled = false;
				});
			}

			function submitReminderMail(postId, btn){
				var body = new URLSearchParams();
				body.set('action', 'cmx_mahnwesen_send_mail');
				body.set('post_id', String(postId));
				body.set('_ajax_nonce', <?php echo \wp_json_encode($reminder_nonce); ?>);

				fetch(<?php echo \wp_json_encode($ajax_url); ?>, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
					body: body.toString()
				}).then(function(resp){
					return resp.json();
				}).then(function(resp){
					if (resp && resp.success) {
						var email = resp.data && resp.data.email ? String(resp.data.email) : '';
						var actionLabel = resp.data && resp.data.action_label ? String(resp.data.action_label) : 'E-Mail';
						window.alert(email !== '' ? (actionLabel + ' gesendet an ' + email) : (actionLabel + ' wurde gesendet.'));
						btn.disabled = false;
						btn.dataset.loading = '';
						return;
					}
					throw new Error(resp && resp.data ? String(resp.data) : 'E-Mail konnte nicht gesendet werden.');
				}).catch(function(err){
					window.alert(err && err.message ? err.message : 'E-Mail konnte nicht gesendet werden.');
					btn.disabled = false;
					btn.dataset.loading = '';
				});
			}

			document.addEventListener('click', function(e){
				var dueBtn = e.target && e.target.closest ? e.target.closest('.cmx-faellig-due-btn') : null;
				if (dueBtn) {
					e.preventDefault();
					e.stopPropagation();
					if (dueBtn.dataset.loading === '1') return;
					var reminderPostId = parseInt(dueBtn.getAttribute('data-post-id') || '0', 10);
					if (!reminderPostId) return;
					dueBtn.dataset.loading = '1';
					dueBtn.disabled = true;
					submitReminderMail(reminderPostId, dueBtn);
					return;
				}

				var btn = e.target && e.target.closest ? e.target.closest('.cmx-faellig-mark-paid') : null;
				if (!btn) return;
				e.preventDefault();
				e.stopPropagation();

				var wrap = btn.closest('.cmx-faellig-pay-wrap');
				var input = wrap ? wrap.querySelector('.cmx-faellig-pay-date') : null;
				if (!input) return;

				var now = new Date();
				var month = String(now.getMonth() + 1).padStart(2, '0');
				var day = String(now.getDate()).padStart(2, '0');
				input.value = input.value || (String(now.getFullYear()) + '-' + month + '-' + day);

				if (typeof input.showPicker === 'function') {
					input.showPicker();
					return;
				}

				input.focus();
				input.click();
			});

			document.addEventListener('change', function(e){
				var input = e.target && e.target.closest ? e.target.closest('.cmx-faellig-pay-date') : null;
				if (!input) return;

				var belegId = parseInt(input.getAttribute('data-beleg') || '0', 10);
				var paidDate = String(input.value || '');
				var wrap = input.closest('.cmx-faellig-pay-wrap');
				var btn = wrap ? wrap.querySelector('.cmx-faellig-mark-paid') : null;
				if (!belegId || !btn || btn.dataset.loading === '1' || !/^\d{4}-\d{2}-\d{2}$/.test(paidDate)) return;

				btn.dataset.loading = '1';
				btn.disabled = true;
				submitPaidDate(belegId, paidDate, btn);
			});
		})();
		</script>
	<?php
});
