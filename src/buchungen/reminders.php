<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

function cmx_buchungen_schedule_reminder(int $post_id): bool {
	if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_BUCHUNGEN_CPT) {
		return false;
	}
	\wp_clear_scheduled_hook(CMX_BUCHUNGEN_REMINDER_HOOK, [$post_id]);

	$status = \sanitize_key((string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_STATUS, true));
	if (!\in_array($status, cmx_buchungen_active_statuses(), true)) {
		return false;
	}
	if (\trim((string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_REMINDER_SENT_AT, true)) !== '') {
		return false;
	}

	$start_ts = cmx_buchungen_start_ts(
		(string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_START_DATE, true),
		(string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_START_TIME, true)
	);
	if ($start_ts <= 0) {
		return false;
	}

	$reminder_ts = $start_ts - (\defined('DAY_IN_SECONDS') ? (int) \DAY_IN_SECONDS : 86400);
	if ($reminder_ts <= \time()) {
		$reminder_ts = \time() + 300;
	}

	return \wp_schedule_single_event($reminder_ts, CMX_BUCHUNGEN_REMINDER_HOOK, [$post_id]) !== false;
}

function cmx_buchungen_contact_email(int $kontakt_id): string {
	if ($kontakt_id <= 0) {
		return '';
	}
	if (\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_email')) {
		$email = \sanitize_email((string) cmx_kommunikation_primary_email($kontakt_id));
		if (\is_email($email)) {
			return $email;
		}
	}
	return \sanitize_email((string) \get_post_meta($kontakt_id, '_cmx_email_1', true));
}

function cmx_buchungen_send_reminder(int $post_id) {
	$post_id = (int) $post_id;
	if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_BUCHUNGEN_CPT) {
		return false;
	}
	if (\trim((string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_REMINDER_SENT_AT, true)) !== '') {
		return true;
	}

	$kontakt_id = (int) \get_post_meta($post_id, CMX_BUCHUNGEN_META_KONTAKT, true);
	$to = cmx_buchungen_contact_email($kontakt_id);
	if (!\is_email($to)) {
		return false;
	}

	$date = (string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_START_DATE, true);
	$time = (string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_START_TIME, true);
	$artikel_id = (int) \get_post_meta($post_id, CMX_BUCHUNGEN_META_ARTIKEL, true);
	$service = $artikel_id > 0 ? \trim((string) \get_the_title($artikel_id)) : 'Termin';
	$subject = 'Erinnerung: ' . $service;
	$message = '<p>Guten Tag</p><p>Dies ist eine Erinnerung an Ihre Buchung.</p><p><strong>' . \esc_html($service) . '</strong><br>' . \esc_html($date . ' ' . $time) . '</p>';

	$sent = \wp_mail($to, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
	if ($sent) {
		\update_post_meta($post_id, CMX_BUCHUNGEN_META_REMINDER_SENT_AT, \current_time('mysql'));
	}

	return $sent;
}

\add_action(CMX_BUCHUNGEN_REMINDER_HOOK, __NAMESPACE__ . '\\cmx_buchungen_send_reminder', 10, 1);
