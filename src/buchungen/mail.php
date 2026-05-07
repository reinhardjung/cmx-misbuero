<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

function cmx_buchungen_mail_headers(): array {
	return ['Content-Type: text/html; charset=UTF-8'];
}

function cmx_buchungen_mail_datetime(int $post_id): string {
	$date = (string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_START_DATE, true);
	$time = (string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_START_TIME, true);
	return \trim($date . ' ' . $time);
}

function cmx_buchungen_mail_service(int $post_id): string {
	$artikel_id = (int) \get_post_meta($post_id, CMX_BUCHUNGEN_META_ARTIKEL, true);
	$title = $artikel_id > 0 ? \trim((string) \get_the_title($artikel_id)) : '';
	return $title !== '' ? $title : 'Termin';
}

function cmx_buchungen_cancel_url(int $post_id): string {
	$token = \trim((string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_CANCEL_TOKEN, true));
	return $token !== '' ? \home_url('/?cmx_buchung_cancel=' . \rawurlencode($token)) : '';
}

function cmx_buchungen_maybe_send_confirmation(int $post_id): bool {
	if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_BUCHUNGEN_CPT) {
		return false;
	}
	if (\trim((string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_CONFIRMATION_SENT_AT, true)) !== '') {
		return false;
	}
	$status = \sanitize_key((string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_STATUS, true));
	if ($status !== 'bestaetigt') {
		return false;
	}

	$kontakt_id = (int) \get_post_meta($post_id, CMX_BUCHUNGEN_META_KONTAKT, true);
	$to = cmx_buchungen_contact_email($kontakt_id);
	if (!\is_email($to)) {
		return false;
	}

	$service = cmx_buchungen_mail_service($post_id);
	$datetime = cmx_buchungen_mail_datetime($post_id);
	$booking_url = \function_exists(__NAMESPACE__ . '\\cmx_buchungen_booking_url') ? cmx_buchungen_booking_url($post_id) : '';
	$cancel_url = cmx_buchungen_cancel_url($post_id);
	$message = '<p>Guten Tag</p><p>Ihre Buchungsanfrage wurde bestätigt.</p><p><strong>' . \esc_html($service) . '</strong><br>' . \esc_html($datetime) . '</p>';
	if ($booking_url !== '') {
		$message .= '<p>Buchung zeigen: <a href="' . \esc_url($booking_url) . '">' . \esc_html($booking_url) . '</a></p>';
	}
	if ($cancel_url !== '') {
		$message .= '<p>Storno-Link: <a href="' . \esc_url($cancel_url) . '">' . \esc_html($cancel_url) . '</a></p>';
	}

	$sent = \wp_mail($to, 'Buchung bestätigt: ' . $service, $message, cmx_buchungen_mail_headers());
	if ($sent) {
		\update_post_meta($post_id, CMX_BUCHUNGEN_META_CONFIRMATION_SENT_AT, \current_time('mysql'));
	}
	return $sent;
}

function cmx_buchungen_send_status_mail(int $post_id, string $status): bool {
	if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_BUCHUNGEN_CPT) {
		return false;
	}
	$status = \sanitize_key($status);
	$kontakt_id = (int) \get_post_meta($post_id, CMX_BUCHUNGEN_META_KONTAKT, true);
	$to = cmx_buchungen_contact_email($kontakt_id);
	if (!\is_email($to)) {
		return false;
	}

	$service = cmx_buchungen_mail_service($post_id);
	$datetime = cmx_buchungen_mail_datetime($post_id);
	$booking_url = \function_exists(__NAMESPACE__ . '\\cmx_buchungen_booking_url') ? cmx_buchungen_booking_url($post_id) : '';
	$label = $status === 'abgesagt' ? 'abgesagt' : 'aktualisiert';
	$message = '<p>Guten Tag</p><p>Ihre Buchung wurde ' . \esc_html($label) . '.</p><p><strong>' . \esc_html($service) . '</strong><br>' . \esc_html($datetime) . '</p>';
	if ($booking_url !== '') {
		$message .= '<p>Buchung zeigen: <a href="' . \esc_url($booking_url) . '">' . \esc_html($booking_url) . '</a></p>';
	}

	return \wp_mail($to, 'Buchung ' . $label . ': ' . $service, $message, cmx_buchungen_mail_headers());
}
