<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Dashboard-Widget: Wichtiges Datum
 * Zeigt die nächsten 5 Kontakte mit gesetztem Datum (aufsteigend sortiert).
 */

if (!defined(__NAMESPACE__.'\\CMX_KONTAKTE_META_DATUM')) {
	define(__NAMESPACE__.'\\CMX_KONTAKTE_META_DATUM', '_cmx_kontakte_datum');
}
if (!defined(__NAMESPACE__.'\\CMX_KONTAKTE_META_PRIVAT')) {
	define(__NAMESPACE__.'\\CMX_KONTAKTE_META_PRIVAT', '_cmx_kontakte_privat');
}
if (!defined(__NAMESPACE__.'\\CMX_KONTAKTE_META_FIRMENGRUENDUNG')) {
	define(__NAMESPACE__.'\\CMX_KONTAKTE_META_FIRMENGRUENDUNG', '_cmx_kontakte_firmengruendung');
}
if (!defined(__NAMESPACE__.'\\CMX_KONTAKTE_META_GEBURTSDATUM')) {
	define(__NAMESPACE__.'\\CMX_KONTAKTE_META_GEBURTSDATUM', '_cmx_kontakte_geburtsdatum');
}

add_action('wp_dashboard_setup', function () {
	\wp_add_dashboard_widget(
		'cmx_kontakt_wichtige_daten',
		'Erinnerungen',
		__NAMESPACE__ . '\\cmx_render_kontakt_wichtige_daten'
	);
});

function cmx_render_kontakt_wichtige_daten(): void {
	if (!current_user_can('edit_posts')) {
		echo '<p>' . esc_html__('Keine Berechtigung.', 'default') . '</p>';
		return;
	}

	$q = new \WP_Query([
		'post_type'               => 'kontakte',
		'post_status'             => ['publish','private'],
		'posts_per_page'          => -1,
		'fields'                  => 'ids',
		'no_found_rows'           => true,
		'update_post_meta_cache'  => false,
		'update_post_term_cache'  => false,
		'meta_query'              => [
			'relation' => 'OR',
			[
				'key'     => CMX_KONTAKTE_META_DATUM,
				'value'   => '',
				'compare' => '!=',
			],
			[
				'key'     => CMX_KONTAKTE_META_FIRMENGRUENDUNG,
				'value'   => '',
				'compare' => '!=',
			],
			[
				'key'     => CMX_KONTAKTE_META_GEBURTSDATUM,
				'value'   => '',
				'compare' => '!=',
			],
		],
	]);

	echo '<style>
		#cmx_kontakt_wichtige_daten .cmx-wd-list { margin:0; padding:0; list-style:none; }
		#cmx_kontakt_wichtige_daten .cmx-wd-item { padding:6px 0; border-bottom:1px solid #f0f0f0; }
		#cmx_kontakt_wichtige_daten .cmx-wd-item:last-child { border-bottom:none; }
		#cmx_kontakt_wichtige_daten .cmx-wd-row { display:grid; grid-template-columns:minmax(0,1fr) auto auto; gap:10px; align-items:start; }
		#cmx_kontakt_wichtige_daten .cmx-wd-main { min-width:0; }
		#cmx_kontakt_wichtige_daten .cmx-wd-title { font-weight:600; display:block; }
		#cmx_kontakt_wichtige_daten .cmx-wd-date { color:#555; font-size:12px; display:block; margin-top:2px; }
		#cmx_kontakt_wichtige_daten .cmx-wd-type { font-size:12px; color:#777; white-space:nowrap; }
		#cmx_kontakt_wichtige_daten .cmx-wd-actions { display:inline-flex; align-items:center; justify-content:flex-end; gap:4px; min-width:36px; }
		#cmx_kontakt_wichtige_daten .cmx-wd-action { text-decoration:none; color:#2271b1; }
		#cmx_kontakt_wichtige_daten .cmx-wd-action:hover { color:#135e96; }
		#cmx_kontakt_wichtige_daten .cmx-wd-action .dashicons { width:16px; height:16px; font-size:16px; line-height:16px; }
	</style>';

	if (!$q->have_posts()) {
		echo '<p><em>' . esc_html__('Kein Datum hinterlegt.', 'default') . '</em></p>';
		return;
	}

	$format_date = static function(string $date): string {
		$disp = $date;
		$tz = wp_timezone();
		$dt = \DateTime::createFromFormat('Y-m-d', $date, $tz);
		if ($dt) {
			$disp = wp_date('d.m.Y', $dt->getTimestamp(), $tz);
		}
		return $disp;
	};
	$next_occurrence_ts = static function(string $date): int {
		$date = trim($date);
		if ($date === '') return PHP_INT_MAX;

		$tz = wp_timezone();
		$dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
		if (!$dt) {
			$dt_fallback = strtotime($date);
			if (!$dt_fallback) return PHP_INT_MAX;
			$dt = (new \DateTimeImmutable('@' . (int) $dt_fallback))->setTimezone($tz);
		}

		$month = (int) $dt->format('m');
		$day   = (int) $dt->format('d');
		$today = new \DateTimeImmutable('today', $tz);
		$year  = (int) $today->format('Y');

		for ($offset = 0; $offset <= 8; $offset++) {
			$candidate = \DateTimeImmutable::createFromFormat(
				'!Y-m-d',
				sprintf('%04d-%02d-%02d', $year + $offset, $month, $day),
				$tz
			);
			if (!$candidate) continue;
			if ((int) $candidate->format('m') !== $month || (int) $candidate->format('d') !== $day) continue;
			if ($candidate < $today) continue;
			return (int) $candidate->getTimestamp();
		}

		$ts = strtotime($date);
		return $ts ? (int) $ts : PHP_INT_MAX;
	};
	$get_first_meta = static function(int $post_id, array $keys): string {
		foreach ($keys as $key) {
			$value = get_post_meta($post_id, (string) $key, true);
			if (!is_scalar($value)) continue;
			$value = trim((string) $value);
			if ($value !== '') return $value;
		}
		return '';
	};

	$rows = [];
	foreach ((array) $q->posts as $pid) {
		$pid = (int) $pid;
		if ($pid <= 0) continue;

		$title = (string) get_the_title($pid);
		$edit_url = (string) get_edit_post_link($pid);
			$legacy = (string) get_post_meta($pid, CMX_KONTAKTE_META_DATUM, true);
			$firm   = (string) get_post_meta($pid, CMX_KONTAKTE_META_FIRMENGRUENDUNG, true);
			$birth  = (string) get_post_meta($pid, CMX_KONTAKTE_META_GEBURTSDATUM, true);
			$email  = $get_first_meta($pid, ['_cmx_email_1','cmx_email_1','email_1','e_mail_1','kontakt_email','email','e_mail','mail']);
			$phone  = $get_first_meta($pid, ['_cmx_telefon_1','cmx_telefon_1','telefon_1','tel_1','phone_1','telefon','tel','phone']);

			$privat = (bool) get_post_meta($pid, CMX_KONTAKTE_META_PRIVAT, true);
			if (!$privat) {
			$privat = (bool) get_post_meta($pid, '_cmx_privat', true);
		}
		if ($legacy !== '') {
			if ($privat && $birth === '') $birth = $legacy;
			if (!$privat && $firm === '') $firm = $legacy;
			if ($firm === '' && $birth === '') $firm = $legacy;
		}

		$entries = [];
		if ($firm !== '') {
			$entries[] = ['label' => 'Firmengründung', 'raw' => $firm];
		}
		if ($birth !== '') {
			$entries[] = ['label' => 'Geburtsdatum', 'raw' => $birth];
		}
		if (empty($entries) && $legacy !== '') {
			$entries[] = ['label' => $privat ? 'Geburtsdatum' : 'Firmengründung', 'raw' => $legacy];
		}

		foreach ($entries as $entry) {
			$raw = (string) ($entry['raw'] ?? '');
			$rows[] = [
				'pid'      => $pid,
				'title'    => $title,
				'edit_url' => $edit_url,
					'type'     => (string) ($entry['label'] ?? ''),
					'disp'     => $format_date($raw),
					'sort_ts'  => $next_occurrence_ts($raw),
					'email'    => $email,
					'phone'    => $phone,
				];
			}
		}

	if (empty($rows)) {
		echo '<p><em>' . esc_html__('Kein Datum hinterlegt.', 'default') . '</em></p>';
		return;
	}

	usort($rows, static function(array $a, array $b): int {
		// Naechstes anstehendes Datum zuerst
		$cmp = ((int) ($a['sort_ts'] ?? PHP_INT_MAX)) <=> ((int) ($b['sort_ts'] ?? PHP_INT_MAX));
		if ($cmp !== 0) return $cmp;
		$cmp = strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
		if ($cmp !== 0) return $cmp;
		return strcasecmp((string) ($a['type'] ?? ''), (string) ($b['type'] ?? ''));
	});
	$rows = array_slice($rows, 0, 5);

	echo '<ul class="cmx-wd-list">';
	foreach ($rows as $row) {
		$title = (string) ($row['title'] ?? '');
		$type_label = (string) ($row['type'] ?? '');
		$disp = (string) ($row['disp'] ?? '');
		$edit_url = (string) ($row['edit_url'] ?? '');
		$email = (string) ($row['email'] ?? '');
		$phone = (string) ($row['phone'] ?? '');
		$email_href = is_email($email) ? ('mailto:' . sanitize_email($email)) : '';
		$phone_href = '';
		if ($phone !== '') {
			$tel = preg_replace('/\s+/', '', $phone);
			if (is_string($tel) && $tel !== '') {
				$phone_href = 'tel:' . $tel;
			}
		}

		echo '<li class="cmx-wd-item">';
		echo '<div class="cmx-wd-row">';
		echo '<div class="cmx-wd-main">';
		echo '<a class="cmx-wd-title" href="' . esc_url($edit_url) . '">' . esc_html($title) . '</a>';
		echo '<span class="cmx-wd-date">' . esc_html($disp) . '</span>';
		echo '</div>';
		echo '<span class="cmx-wd-type">' . esc_html($type_label) . '</span>';
		echo '<span class="cmx-wd-actions">';
		if ($email_href !== '') {
			echo '<a class="cmx-wd-action" href="' . esc_url($email_href) . '" title="E-Mail" aria-label="E-Mail"><span class="dashicons dashicons-email-alt"></span></a>';
		}
		if ($phone_href !== '') {
			echo '<a class="cmx-wd-action" href="' . esc_url($phone_href) . '" title="Anrufen" aria-label="Anrufen"><span class="dashicons dashicons-phone"></span></a>';
		}
		echo '</span>';
		echo '</div>';
		echo '</li>';
	}
	echo '</ul>';
}
