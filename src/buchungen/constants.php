<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

if (!\defined(__NAMESPACE__ . '\\CMX_BUCHUNGEN_CPT')) {
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_CPT', 'buchungen');
}

if (!\defined(__NAMESPACE__ . '\\CMX_BUCHUNGEN_TAX_STANDORT')) {
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_TAX_STANDORT', 'buchungen_standort');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_TAX_TYP', 'buchungen_buchungstyp');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_TAX_MITARBEITER', 'buchungen_mitarbeiter');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_TAX_RESSOURCE', 'buchungen_ressource');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_TAX_LEISTUNGSKATEGORIE', 'buchungen_leistungskategorie');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_TAX_DAUER', 'buchungen_dauern');
}

if (!\defined(__NAMESPACE__ . '\\CMX_TAX_BUCHUNGEN')) {
	\define(__NAMESPACE__ . '\\CMX_TAX_BUCHUNGEN', 'Standorte,Buchungstypen,Mitarbeiter,Ressourcen,Leistungskategorien,Dauern');
	\define(__NAMESPACE__ . '\\TAX_BUCHUNGEN_STANDORTE', CMX_BUCHUNGEN_TAX_STANDORT);
	\define(__NAMESPACE__ . '\\TAX_BUCHUNGEN_BUCHUNGSTYPEN', CMX_BUCHUNGEN_TAX_TYP);
	\define(__NAMESPACE__ . '\\TAX_BUCHUNGEN_MITARBEITER', CMX_BUCHUNGEN_TAX_MITARBEITER);
	\define(__NAMESPACE__ . '\\TAX_BUCHUNGEN_RESSOURCEN', CMX_BUCHUNGEN_TAX_RESSOURCE);
	\define(__NAMESPACE__ . '\\TAX_BUCHUNGEN_LEISTUNGSKATEGORIEN', CMX_BUCHUNGEN_TAX_LEISTUNGSKATEGORIE);
	\define(__NAMESPACE__ . '\\TAX_BUCHUNGEN_DAUERN', CMX_BUCHUNGEN_TAX_DAUER);
}

if (!\defined(__NAMESPACE__ . '\\CMX_BUCHUNGEN_META_KONTAKT')) {
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_META_KONTAKT', '_cmx_buchung_kontakt_id');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_META_ARTIKEL', '_cmx_buchung_artikel_id');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_META_START_DATE', '_cmx_buchung_start_date');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_META_START_TIME', '_cmx_buchung_start_time');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_META_DURATION', '_cmx_buchung_duration');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_META_STATUS', '_cmx_buchung_status');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_META_MITARBEITER', '_cmx_buchung_mitarbeiter_term_id');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_META_RESSOURCE', '_cmx_buchung_ressource_term_id');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_META_BUFFER_BEFORE', '_cmx_buchung_buffer_before');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_META_BUFFER_AFTER', '_cmx_buchung_buffer_after');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_META_DEPOSIT', '_cmx_buchung_deposit');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_META_NO_SHOW_FEE', '_cmx_buchung_no_show_fee');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_META_NOTES', '_cmx_buchung_notes');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_META_CONFIRMATION_SENT_AT', '_cmx_buchung_confirmation_sent_at');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_META_REMINDER_SENT_AT', '_cmx_buchung_reminder_sent_at');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_META_BOOKING_TOKEN', '_cmx_buchung_online_token');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_META_CANCEL_TOKEN', '_cmx_buchung_cancel_token');
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_META_BELEG_ID', '_cmx_buchung_beleg_id');
}

if (!\defined(__NAMESPACE__ . '\\CMX_BUCHUNGEN_REMINDER_HOOK')) {
	\define(__NAMESPACE__ . '\\CMX_BUCHUNGEN_REMINDER_HOOK', 'cmx_buchungen_send_reminder');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_status_options')) {
	function cmx_buchungen_status_options(): array {
		return [
			'angefragt'  => 'angefragt',
			'bestaetigt' => 'bestätigt',
			'erledigt'   => 'erledigt',
			'abgesagt'   => 'abgesagt',
			'no-show'    => 'no-show',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_active_statuses')) {
	function cmx_buchungen_active_statuses(): array {
		return ['angefragt', 'bestaetigt'];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_sanitize_date')) {
	function cmx_buchungen_sanitize_date($value): string {
		$value = \trim((string) $value);
		if ($value === '') {
			return '';
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_sanitize_date_ymd')) {
			return (string) cmx_sanitize_date_ymd($value);
		}
		$date = \DateTime::createFromFormat('Y-m-d', $value);
		return $date && $date->format('Y-m-d') === $value ? $value : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_sanitize_time')) {
	function cmx_buchungen_sanitize_time($value): string {
		$value = \trim((string) $value);
		return \preg_match('/^\d{2}:\d{2}$/', $value) ? $value : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_start_ts')) {
	function cmx_buchungen_start_ts(string $date, string $time): int {
		$date = cmx_buchungen_sanitize_date($date);
		$time = cmx_buchungen_sanitize_time($time);
		if ($date === '' || $time === '') {
			return 0;
		}
		$dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $time, \wp_timezone());
		return $dt instanceof \DateTimeImmutable ? $dt->getTimestamp() : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_token')) {
	function cmx_buchungen_token(): string {
		return \wp_generate_password(32, false, false);
	}
}
