<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

require_once __DIR__ . '/../belege/vorlage_mail mahnung.php';

/**
 * Dashboard-Widget: Mahnwesen
 * - listet offene Rechnungen an Kunden
 * - nur Ausgangs-Rechnungen
 * - nur unbezahlte Belege
 */

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_mahnwesen_taxonomy')) {
	function cmx_cockpit_mahnwesen_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_taxonomy')) {
			return (string) cmx_cockpit_beleg_taxonomy();
		}

		foreach (['belege_kategorien', 'beleg_kategorie'] as $tax) {
			if (\taxonomy_exists($tax)) {
				return $tax;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_mahnwesen_amount_value')) {
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_mahnwesen_is_outgoing_invoice')) {
	function cmx_cockpit_mahnwesen_is_outgoing_invoice(int $post_id): bool {
		$richtung = \sanitize_key((string) \get_post_meta($post_id, '_cmx_beleg_richtung', true));
		return $richtung === 'ausgang';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_mahnwesen_contact_id')) {
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_mahnwesen_contact_email')) {
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_mahnwesen_due_display')) {
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_mahnwesen_amount_display')) {
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_mahnwesen_send_mail')) {
	function cmx_cockpit_mahnwesen_send_mail(int $post_id) {
		$post = \get_post($post_id);
		if (!$post instanceof \WP_Post || $post->post_type !== 'belege') {
			return new \WP_Error('invalid_post', 'Beleg nicht gefunden.');
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

		$subject = 'Zahlungserinnerung: ' . $beleg_label . ($beleg_id !== '' ? ' ' . $beleg_id : '');
		$message = cmxbu_render_belegmail_mahnung_template([
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
		]);

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
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_mahnwesen_ajax_send_mail')) {
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_mahnwesen_data')) {
	function cmx_cockpit_mahnwesen_data(): array {
		static $cache = null;
		if (\is_array($cache)) {
			return $cache;
		}

		$taxonomy = cmx_cockpit_mahnwesen_taxonomy();
		$term_slugs = ['rechnung', 'rechnungen'];
		$list_args = [
			'post_type' => 'belege',
			'cmx_bezahlfilter' => 'offen',
			'cmx_richtungfilter' => 'ausgang',
		];
		if ($taxonomy !== '') {
			$list_args[$taxonomy] = \implode(',', $term_slugs);
		}
		$list_url = \add_query_arg($list_args, \admin_url('edit.php'));

		if ($taxonomy === '' || !\taxonomy_exists($taxonomy)) {
			$cache = [
				'total' => 0,
				'sum' => 0.0,
				'items' => [],
				'list_url' => $list_url,
			];
			return $cache;
		}

		$q = new \WP_Query([
			'post_type' => 'belege',
			'post_status' => ['publish', 'private'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query' => [
				[
					'taxonomy' => $taxonomy,
					'field' => 'slug',
					'terms' => $term_slugs,
					'operator' => 'IN',
				],
			],
		]);

		$rows = [];
		$sum = 0.0;

		foreach ((array) $q->posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0) {
				continue;
			}

			if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_is_unpaid_beleg') && !cmx_cockpit_is_unpaid_beleg($post_id)) {
				continue;
			}
			if (!cmx_cockpit_mahnwesen_is_outgoing_invoice($post_id)) {
				continue;
			}

			$title = \trim((string) \get_the_title($post_id));
			if ($title === '') {
				$title = '#' . $post_id;
			}

			$kontakt_data = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_kontakt_data')
				? (array) cmx_cockpit_beleg_kontakt_data($post_id)
				: ['name' => '', 'url' => ''];
			$kontakt_email = cmx_cockpit_mahnwesen_contact_email($post_id);

			$due_raw = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_due_raw')
				? (string) cmx_cockpit_due_raw($post_id)
				: '';
			$due_ts = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_parse_date_to_ts')
				? (int) cmx_cockpit_parse_date_to_ts($due_raw)
				: 0;
			if ($due_ts <= 0) {
				continue;
			}

			$post_date = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_post_date')
				? (string) cmx_cockpit_post_date($post_id)
				: '';
			$post_ts = \function_exists(__NAMESPACE__ . '\\cmx_cockpit_parse_date_to_ts')
				? (int) cmx_cockpit_parse_date_to_ts($post_date)
				: 0;

			$amount_value = cmx_cockpit_mahnwesen_amount_value($post_id);
			if ($amount_value <= 0) {
				continue;
			}
			$sum += $amount_value;

			$amount_display = cmx_cockpit_mahnwesen_amount_display($post_id, $amount_value);

			$rows[] = [
				'id' => $post_id,
				'title' => $title,
				'edit_url' => (string) \get_edit_post_link($post_id, ''),
				'due_sort' => $due_ts > 0 ? $due_ts : ($post_ts > 0 ? $post_ts : PHP_INT_MAX),
				'due_date' => cmx_cockpit_mahnwesen_due_display($post_id, $due_raw, $due_ts),
				'kontakt' => (string) ($kontakt_data['name'] ?? ''),
				'kontakt_url' => (string) ($kontakt_data['url'] ?? ''),
				'kontakt_email' => $kontakt_email,
				'amount_display' => $amount_display,
			];
		}

		\usort($rows, static function (array $a, array $b): int {
			$cmp = ((int) ($a['due_sort'] ?? PHP_INT_MAX)) <=> ((int) ($b['due_sort'] ?? PHP_INT_MAX));
			if ($cmp !== 0) {
				return $cmp;
			}

			return \strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
		});

		$cache = [
			'total' => \count($rows),
			'sum' => $sum,
			'items' => $rows,
			'list_url' => $list_url,
		];

		return $cache;
	}
}

\add_action('wp_dashboard_setup', __NAMESPACE__ . '\\cmx_register_mahnwesen_widget');
function cmx_register_mahnwesen_widget(): void {
	if (!\current_user_can('edit_posts')) {
		return;
	}

	$data = cmx_cockpit_mahnwesen_data();
	$title = 'Mahnwesen (' . (int) ($data['total'] ?? 0) . ')';
	$list_url = (string) ($data['list_url'] ?? '');
	$title_link = $list_url !== ''
		? '<a href="' . \esc_url($list_url) . '" style="font-weight:700;font-size:14px;text-decoration:none;">' . \esc_html($title) . '</a>'
		: \esc_html($title);

	\wp_add_dashboard_widget(
		'cmx_mahnwesen_widget',
		$title_link,
		__NAMESPACE__ . '\\cmx_render_mahnwesen_widget'
	);
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_mahnwesen_widget')) {
	function cmx_render_mahnwesen_widget(): void {
		if (!\current_user_can('edit_posts')) {
			echo '<p>' . \esc_html__('Keine Berechtigung.', 'default') . '</p>';
			return;
		}

		$data = cmx_cockpit_mahnwesen_data();
		$total = (int) ($data['total'] ?? 0);
		$sum = (float) ($data['sum'] ?? 0.0);
		$items = (array) ($data['items'] ?? []);

		if ($total <= 0) {
			echo '<p>Keine offenen Kundenrechnungen</p>';
			return;
		}

		$sum_display = \function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')
			? (string) cmx_format_swiss_number($sum, 2)
			: \number_format($sum, 2, '.', "'");

		echo '<style>
			#cmx_mahnwesen_widget .cmx-mahn-meta{display:flex;justify-content:space-between;gap:12px;margin:0 0 10px;padding:0 0 8px;border-bottom:1px solid #eef0f2}
			#cmx_mahnwesen_widget .cmx-mahn-meta strong{font-weight:700}
			#cmx_mahnwesen_widget .cmx-mahn-table{width:100%;border-collapse:collapse;table-layout:fixed}
			#cmx_mahnwesen_widget .cmx-mahn-table th{padding:0 0 8px;text-align:left;font-size:11px;font-weight:700;letter-spacing:.04em;color:#646970;text-transform:uppercase}
			#cmx_mahnwesen_widget .cmx-mahn-table td{padding:6px 0;vertical-align:top}
			#cmx_mahnwesen_widget .cmx-mahn-table tbody tr td{transition:background-color .15s ease}
			#cmx_mahnwesen_widget .cmx-mahn-table tbody tr:hover td{background:#f7fbff}
			#cmx_mahnwesen_widget .cmx-mahn-title-link,
			#cmx_mahnwesen_widget .cmx-mahn-contact-link{display:block;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
			#cmx_mahnwesen_widget .cmx-mahn-title-link:hover,
			#cmx_mahnwesen_widget .cmx-mahn-contact-link:hover{text-decoration:underline}
			#cmx_mahnwesen_widget .cmx-mahn-date{white-space:nowrap}
			#cmx_mahnwesen_widget .cmx-mahn-date-btn{display:inline-block;border:0;background:transparent;padding:0;margin:0;color:#2271b1;cursor:pointer;text-decoration:none;font:inherit}
			#cmx_mahnwesen_widget .cmx-mahn-date-btn:hover{text-decoration:underline}
			#cmx_mahnwesen_widget .cmx-mahn-date-btn[disabled]{color:#8c8f94;cursor:default;text-decoration:none}
			#cmx_mahnwesen_widget .cmx-mahn-amount{text-align:right;white-space:nowrap;font-weight:600}
		</style>';

		echo '<p class="cmx-mahn-meta">';
		echo '<span><strong>' . \esc_html((string) $total) . '</strong> offene Rechnungen</span>';
		echo '<span><strong>CHF ' . \esc_html($sum_display) . '</strong></span>';
		echo '</p>';

		echo '<table class="cmx-mahn-table">';
		echo '<colgroup>';
		echo '<col style="width:140px;">';
		echo '<col style="width:88px;">';
		echo '<col>';
		echo '<col style="width:96px;">';
		echo '</colgroup>';
		echo '<thead><tr>';
		echo '<th>Rechnung</th>';
		echo '<th>Fällig am</th>';
		echo '<th>Kontakt</th>';
		echo '<th style="text-align:right;">Betrag</th>';
		echo '</tr></thead><tbody>';

		foreach ($items as $row) {
			$title = (string) ($row['title'] ?? '');
			$edit_url = (string) ($row['edit_url'] ?? '');
			$due_date = (string) ($row['due_date'] ?? '');
			$kontakt = (string) ($row['kontakt'] ?? '');
			$kontakt_url = (string) ($row['kontakt_url'] ?? '');
			$kontakt_email = (string) ($row['kontakt_email'] ?? '');
			$amount_display = (string) ($row['amount_display'] ?? '');

			echo '<tr>';
			echo '<td>';
			if ($edit_url !== '') {
				echo '<a class="cmx-mahn-title-link" href="' . \esc_url($edit_url) . '">' . \esc_html($title) . '</a>';
			} else {
				echo '<span class="cmx-mahn-title-link">' . \esc_html($title) . '</span>';
			}
			echo '</td>';
			echo '<td class="cmx-mahn-date">';
			if ($kontakt_email !== '') {
				echo '<button type="button" class="cmx-mahn-date-btn" data-post-id="' . (int) ($row['id'] ?? 0) . '" title="' . \esc_attr('Klicken um Zahlungserinnerung zu senden an ' . $kontakt_email) . '">';
				echo \esc_html($due_date !== '' ? $due_date : '-');
				echo '</button>';
			} else {
				echo '<span title="' . \esc_attr('Keine Empfänger-E-Mail hinterlegt') . '">' . \esc_html($due_date !== '' ? $due_date : '-') . '</span>';
			}
			echo '</td>';
			echo '<td>';
			if ($kontakt !== '' && $kontakt_url !== '') {
				echo '<a class="cmx-mahn-contact-link" href="' . \esc_url($kontakt_url) . '">' . \esc_html($kontakt) . '</a>';
			} else {
				echo '<span class="cmx-mahn-contact-link">' . \esc_html($kontakt) . '</span>';
			}
			echo '</td>';
			echo '<td class="cmx-mahn-amount">' . \esc_html($amount_display) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}

\add_action('admin_footer-index.php', function (): void {
	if (!\current_user_can('edit_posts')) {
		return;
	}
	?>
	<script>
	(function(){
		var box = document.getElementById('cmx_mahnwesen_widget');
		if (!box) return;
		var ajaxUrl = <?php echo \wp_json_encode(\admin_url('admin-ajax.php')); ?>;
		var nonce = <?php echo \wp_json_encode(\wp_create_nonce('cmx_mahnwesen_send_mail')); ?>;

		document.addEventListener('click', function(e){
			var btn = e.target && e.target.closest ? e.target.closest('.cmx-mahn-date-btn') : null;
			if (!btn || !box.contains(btn)) return;
			e.preventDefault();

			if (btn.disabled) return;

			var postId = parseInt(btn.getAttribute('data-post-id') || '0', 10);
			if (!postId) return;

			btn.disabled = true;

			var body = new URLSearchParams();
			body.set('action', 'cmx_mahnwesen_send_mail');
			body.set('post_id', String(postId));
			body.set('_ajax_nonce', nonce);

			fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: body.toString()
			}).then(function(resp){
				return resp.json();
			}).then(function(resp){
				if (resp && resp.success) {
					var email = resp.data && resp.data.email ? String(resp.data.email) : '';
					window.alert(email !== '' ? ('Zahlungserinnerung gesendet an ' + email) : 'Zahlungserinnerung wurde gesendet.');
					btn.disabled = false;
					return;
				}
				throw new Error(resp && resp.data ? String(resp.data) : 'E-Mail konnte nicht gesendet werden.');
			}).catch(function(err){
				window.alert(err && err.message ? err.message : 'E-Mail konnte nicht gesendet werden.');
				btn.disabled = false;
			});
		});
	})();
	</script>
	<?php
});
