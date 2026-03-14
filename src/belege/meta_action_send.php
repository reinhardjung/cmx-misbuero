<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

require_once __DIR__ . '/vorlage_mail.php';

if (!\function_exists(__NAMESPACE__ . '\\cmxbu_get_mail_subject_beleg_date')) {
	function cmxbu_get_mail_subject_beleg_date(int $post_id): string {
		$raw = '';
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_first_meta')) {
			$raw = (string) cmxbu_first_meta($post_id, ['_cmx_beleg_rng_datum', '_cmx_rechnungsdatum', '_invoice_date', '_date']);
		} else {
			foreach (['_cmx_beleg_rng_datum', '_cmx_rechnungsdatum', '_invoice_date', '_date'] as $meta_key) {
				$candidate = \trim((string) \get_post_meta($post_id, $meta_key, true));
				if ($candidate !== '') {
					$raw = $candidate;
					break;
				}
			}
		}

		if ($raw === '') {
			return '';
		}

		$ts = \strtotime($raw);
		if (!$ts) {
			return '';
		}

		return \date('d.m.Y', $ts);
	}
}


/**
 * Metabox-Teil: "versenden"-Button
 */
function cmxbu_render_beleg_send_metabox(\WP_Post $post): void {

	[, $pdf_abs_path] = cmxbu_get_beleg_pdf_paths($post);
	$has_pdf          = is_file($pdf_abs_path);
	$post_id          = (int) $post->ID;

	if ($has_pdf) {
		$href = \esc_url(\admin_url("admin-post.php?action=cmxbu_beleg_send&post_id={$post_id}"));
		// echo '<a href="' . $href . '" class="button button-secondary alignleft">versenden</a>';
		echo '<a href="' . esc_url( $href ) . '" title="PDF-Link per Mail versenden" class="button button-secondary alignleft"><span style="margin-top:5px;" class="dashicons dashicons-email"></span></a>';
	}
	// else {
	// 	echo '<a href="#" class="button button-secondary alignleft disabled" style="pointer-events:none;opacity:0.5;">versenden</a>';
	// }
}


/**
 * Handler: Beleg per E-Mail versenden
 * URL: /wp-admin/admin-post.php?action=cmxbu_beleg_send&post_id=123
 */
function cmxbu_handle_beleg_send(): void {

	if (empty($_GET['post_id'])) {
		\wp_die('Beleg-ID fehlt.');
	}

	$post_id = (int) $_GET['post_id'];
	$post    = \get_post($post_id);

	if (!$post || $post->post_type !== 'belege') {
		\wp_die('Beleg nicht gefunden.');
	}
	$redirect_base = \get_edit_post_link($post_id, '');
	if (!\is_string($redirect_base) || $redirect_base === '') {
		$redirect_base = \admin_url('post.php?post=' . (int) $post_id . '&action=edit');
	}
	$opts_general = (array) \get_option('cmx_einstellungen', []);
	$configured_sender = \sanitize_email((string) ($opts_general['email_address'] ?? ''));
	if (!\is_email($configured_sender)) {
		$redirect = \add_query_arg(
			['cmx_beleg_mail_missing_sender' => '1'],
			$redirect_base
		);
		\wp_safe_redirect($redirect);
		exit;
	}
	if (function_exists(__NAMESPACE__ . '\\cmxbu_log')) {
		cmxbu_log('MAIL: start', ['post_id' => $post_id]);
	}
	$beleg_id = (string) ($post->post_title ?? '');

	// Für Bearbeiter vor dem Versand neu generieren, damit aktuelle Berechnung/Layout gilt.
	if (\current_user_can('edit_post', $post_id) && \function_exists(__NAMESPACE__ . '\\cmxbu_generate_document_on_save')) {
		cmxbu_generate_document_on_save($post_id, $post, true);
	}

	// PDF-Pfade
	[, $pdf_abs_path] = cmxbu_get_beleg_pdf_paths($post);
	if (!is_file($pdf_abs_path)) {
		if (function_exists(__NAMESPACE__ . '\\cmxbu_log')) {
			cmxbu_log('MAIL: pdf not found', ['post_id' => $post_id, 'pdf' => $pdf_abs_path]);
		}
		\wp_die('PDF nicht gefunden.');
	}

	// Token → Download-Link
	$token        = cmxbu_get_stable_token($post_id);
	$download_url = \add_query_arg('beleg', $token, \home_url('/'));


	/**
	 * --------------------------
	 * K O N T A K T   I D
	 * warningsicher
	 * --------------------------
	 */
	$kontakt_id = get_post_meta($post_id, '_cmx_beleg_kontakt_id', true);
	if (empty($kontakt_id)) {
		if (function_exists(__NAMESPACE__ . '\\cmxbu_log')) {
			cmxbu_log('MAIL: missing kontakt_id', ['post_id' => $post_id]);
		}

		add_action('admin_notices', function () {
			?>
			<div class="notice notice-error is-dismissible">
				<p><strong>Kontakt / Adresse fehlt.</strong></p>
			</div>
			<?php
		});

		\wp_safe_redirect(\get_edit_post_link($post_id, ''));
		exit;
	}


	/**
	 * E-Mail-Adresse des Kontakts
	 */
	$to = \get_post_meta($kontakt_id, '_cmx_email_1', true);

	$anrede_key = defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_ANREDE')
		? CMX_KONTAKTE_META_ANREDE
		: '_cmx_kontakte_anrede';
	$anrede = trim((string) get_post_meta($kontakt_id, $anrede_key, true));
	$vorname = trim((string) get_post_meta($kontakt_id, '_cmx_kontakte_vorname', true));
	$nachname = trim((string) get_post_meta($kontakt_id, '_cmx_kontakte_nachname', true));
	$faellig_bis = cmxbu_get_beleg_due_date_display($post_id);
	$betrag = cmxbu_get_beleg_amount_display($post_id);

	if (empty($to) || !\is_email($to)) {
		if (function_exists(__NAMESPACE__ . '\\cmxbu_log')) {
			cmxbu_log('MAIL: invalid email', ['post_id' => $post_id, 'email' => (string) $to]);
		}
		\wp_die('Keine gültige Empfänger-E-Mailadresse hinterlegt.');
	}
	[, $beleg_slug] = cmx_get_beleg_type($post);
	$beleg_label = [
		'rechnung'     => 'Rechnung',
		'offerte'      => 'Offerte',
		'lieferschein' => 'Lieferschein',
		'gutschrift'   => 'Gutschrift',
	][$beleg_slug] ?? ($beleg_slug !== '' ? ucfirst($beleg_slug) : 'Beleg');
	$subject = $beleg_label . ': ' . $beleg_id;
	$beleg_mail_date = cmxbu_get_mail_subject_beleg_date($post_id);
	if ($beleg_mail_date !== '') {
		$subject .= ' vom ' . $beleg_mail_date;
	}

	$catalog_url = \function_exists(__NAMESPACE__ . '\\cmx_katalog_online') && cmx_katalog_online()
		? \home_url('/katalog/')
		: '';
	$custom_message = cmx_get_belegmail($beleg_slug, $kontakt_id);
	$message = $custom_message;
	if (\trim($message) === '') {
		$message = cmxbu_render_belegmail_template([
			'anrede' => $anrede,
			'vorname' => $vorname,
			'nachname' => $nachname,
			'kontakt_id' => (int) $kontakt_id,
			'beleg_label' => $beleg_label,
			'beleg_id' => $beleg_id,
			'beleg_date' => $beleg_mail_date,
			'download_url' => $download_url,
			'faellig_bis' => $faellig_bis,
			'betrag' => $betrag,
			'site_name' => \get_bloginfo('name'),
			'catalog_url' => $catalog_url,
		]);
	}
	if ($faellig_bis !== '') {
		$message = cmxbu_replace_placeholder_with_spacing($message, '{faellig_bis}', esc_html($faellig_bis));
	}
	if ($betrag !== '') {
		$message = cmxbu_replace_placeholder_with_spacing($message, '{betrag}', esc_html($betrag));
	}
	if ($beleg_mail_date !== '') {
		$message = cmxbu_replace_placeholder_with_spacing($message, '{beleg_datum}', esc_html($beleg_mail_date));
	}
	$beleg_link = '<a href="' . esc_url($download_url) . '">' . esc_html($beleg_id) . '</a>';
	if (strpos($message, '{beleg}') !== false) {
		$message = cmxbu_replace_placeholder_with_spacing($message, '{beleg}', $beleg_link);
	}
	// var_dump($message); exit;
	// cmx_get_belegfuss($beleg_type);
	$headers = ['Content-Type: text/html; charset=UTF-8'];
	$message = cmxbu_prepare_belegmail_html($message);
	if (\trim($custom_message) !== '') {
		$message = cmxbu_append_belegmail_footer_html($message, [
			'kontakt_id' => (int) $kontakt_id,
			'site_name' => \get_bloginfo('name'),
			'catalog_url' => $catalog_url,
		]);
	}
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
		$data = $error->get_error_data();
		if (\is_array($data) && !empty($data['phpmailer_exception_code'])) {
			$msg = $msg !== ''
				? ($msg . ' (Code ' . (string) $data['phpmailer_exception_code'] . ')')
				: ('PHPMailer-Fehler (Code ' . (string) $data['phpmailer_exception_code'] . ')');
		}
		$wp_mail_failed_message = $msg;
	};
	$GLOBALS['cmx_force_current_user_mail_sender'] = true;
	$GLOBALS['cmx_mail_context'] = 'beleg_send';
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

	if (function_exists(__NAMESPACE__ . '\\cmxbu_log')) {
		cmxbu_log('MAIL: result', ['post_id' => $post_id, 'sent' => (bool) $sent, 'to' => (string) $to, 'subject' => (string) $subject]);
	}

	if (!$sent) {
		if (function_exists(__NAMESPACE__ . '\\cmxbu_log')) {
			cmxbu_log('MAIL: failed reason', ['post_id' => $post_id, 'reason' => (string) $wp_mail_failed_message]);
		}
		$args = ['cmx_beleg_mail_error' => '1'];
		$error_message = \trim((string) $wp_mail_failed_message);
		if ($error_message !== '') {
			$token = \wp_generate_password(12, false, false);
			$key = 'cmx_beleg_mail_error_' . (int) \get_current_user_id() . '_' . $token;
			\set_transient($key, \substr($error_message, 0, 400), 10 * MINUTE_IN_SECONDS);
			$args['cmx_beleg_mail_error_token'] = $token;
		}
		\wp_safe_redirect(\add_query_arg($args, $redirect_base));
		exit;
	}

	$redirect = \add_query_arg(
		[
			'cmx_beleg_mail_sent' => '1',
			'cmx_beleg_mail_to'   => \sanitize_email((string) $to),
		],
		\get_edit_post_link($post_id, '')
	);
	\wp_safe_redirect($redirect);
	exit;
}
\add_action('admin_post_cmxbu_beleg_send', __NAMESPACE__ . '\\cmxbu_handle_beleg_send');

\add_action('all_admin_notices', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || $screen->post_type !== 'belege' || $screen->base !== 'post') {
		return;
	}
	if (!empty($_GET['cmx_beleg_mail_missing_sender'])) {
		$settings_url = \admin_url('admin.php?page=cmx-einstellungen&tab=email');
		echo '<div class="notice notice-error is-dismissible"><p><strong>Bitte hinterlege zuerst Deine E-Mail-Adresse.</strong> Ohne Absender-Adresse kann kein Beleg versendet werden. <a href="' . \esc_url($settings_url) . '" class="button button-secondary" style="margin-left:8px;" target="_blank" rel="noopener noreferrer">Einstellungen / E-Mail</a></p></div>';
		return;
	}
	if (!empty($_GET['cmx_beleg_mail_error'])) {
		$settings_url = \admin_url('admin.php?page=cmx-einstellungen&tab=email');
		$detail = '';
		$token = isset($_GET['cmx_beleg_mail_error_token']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_beleg_mail_error_token'])) : '';
		if ($token !== '') {
			$key = 'cmx_beleg_mail_error_' . (int) \get_current_user_id() . '_' . $token;
			$stored = \get_transient($key);
			if (\is_string($stored) && $stored !== '') {
				$detail = $stored;
			}
			\delete_transient($key);
		}
		echo '<div class="notice notice-error is-dismissible"><p><strong>E-Mail konnte nicht gesendet werden.</strong> Bitte prüfe Deine SMTP-/Alias-Einstellungen. <a href="' . \esc_url($settings_url) . '" class="button button-secondary" style="margin-left:8px;" target="_blank" rel="noopener noreferrer">Einstellungen / E-Mail</a></p>';
		if ($detail !== '') {
			echo '<p style="margin-top:6px;"><small>Technischer Hinweis: ' . \esc_html($detail) . '</small></p>';
		}
		echo '</div>';
		return;
	}
	if (empty($_GET['cmx_beleg_mail_sent'])) {
		return;
	}
	$mail_to = isset($_GET['cmx_beleg_mail_to'])
		? \sanitize_email((string) \wp_unslash($_GET['cmx_beleg_mail_to']))
		: '';
	if ($mail_to !== '') {
		$opts = (array) \get_option('cmx_einstellungen', []);
		$sender_mail = \sanitize_email((string) ($opts['email_alias_belege'] ?? ''));
		if (!\is_email($sender_mail)) {
			$sender_mail = \sanitize_email((string) ($opts['email_address'] ?? ''));
		}
		$sender_hint = '';
		if (\is_email($sender_mail)) {
			$sender_hint = ' <i style="color:silver;">(von: ' . \esc_html($sender_mail) . ')</i>';
		}
		$link = '<a href="' . \esc_url('mailto:' . $mail_to) . '">' . \esc_html($mail_to) . '</a>';
		echo '<div class="notice notice-success is-dismissible"><p>E-Mail wurde versendet an: ' . $link . $sender_hint . '</p></div>';
		return;
	}
	echo '<div class="notice notice-success is-dismissible"><p>E-Mail wurde versendet.</p></div>';
});



function cmx_get_belegmail(string $key, ?int $kontakt_id = null): string {
	// cmx_belege[belegfuss_rechnung], cmx_belege[mail_rechnung]
	$key = 'mail_' . strtolower(trim($key));
	$options = get_option('cmx_belege', []); // var_dump($options['belegfuss_rechnung']); exit;
	$message = '';

	if (isset($options[$key]) && is_string($options[$key])) {
		$message = $options[$key];
	}

	if ($kontakt_id) {
		$anrede_key = defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_ANREDE')
			? CMX_KONTAKTE_META_ANREDE
			: '_cmx_kontakte_anrede';
		$anrede = trim((string) get_post_meta($kontakt_id, $anrede_key, true));
		$message = cmxbu_replace_placeholder_with_spacing($message, '{anrede}', esc_html($anrede));
	}

	return $message;
}

function cmxbu_replace_placeholder_with_spacing(string $message, string $placeholder, string $replacement): string {
	if ($placeholder === '' || strpos($message, $placeholder) === false) {
		return $message;
	}

	$parts = explode($placeholder, $message);
	$out = array_shift($parts);
	foreach ($parts as $part) {
		$before = $out !== '' ? substr($out, -1) : '';
		$after = $part !== '' ? substr($part, 0, 1) : '';
		if ($before !== '' && !preg_match('/\\s/', $before)) {
			$out .= ' ';
		}
		$out .= $replacement;
		if ($after !== '' && !preg_match('/\\s/', $after)) {
			$out .= ' ';
		}
		$out .= $part;
	}

	return $out;
}

function cmxbu_prepare_belegmail_html(string $message): string {
	// If message already contains HTML, leave it as-is.
	if (preg_match('/<[^>]+>/', $message)) {
		return $message;
	}

	// Plain text: preserve new lines.
	$message = esc_html($message);
	return nl2br($message);
}

function cmxbu_append_belegmail_footer_html(string $message, array $args = []): string {
	if (\strpos($message, 'Erstellt mit <a href="https://misbuero.ch/"') !== false) {
		return $message;
	}

	$kontakt_id = (int) ($args['kontakt_id'] ?? 0);
	$portal_html = \function_exists(__NAMESPACE__ . '\\cmx_email_kundenportal_footer_html')
		? (string) cmx_email_kundenportal_footer_html($kontakt_id, 'color:#8b98a5;text-decoration:underline;')
		: '';
	$agb_html = \function_exists(__NAMESPACE__ . '\\cmx_email_agb_footer_html')
		? (string) cmx_email_agb_footer_html('color:#8b98a5;text-decoration:underline;')
		: '';

	$footer = '<div style="margin-top:24px;">';
	if ($portal_html !== '') {
		$footer .= '<p style="margin:0 0 10px 0;font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:12px;color:#8b98a5;line-height:1.5;">' . $portal_html . '</p>';
	}
	$footer .= '<hr style="border:none;border-top:1px solid #e5e7eb;margin:18px 0;">';
	if ($agb_html !== '') {
		$footer .= '<p style="margin:0 0 6px 0;font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:12px;color:#8b98a5;line-height:1.5;">' . $agb_html . '</p>';
	}
	$footer .= '<p style="margin:0;font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:12px;color:#8b98a5;line-height:1.5;">Erstellt mit <a href="https://misbuero.ch/" style="color:#8b98a5;text-decoration:underline;">MisBüro</a> – der einfachen Bürosoftware für Selbständige.</p></div>';

	return $message . $footer;
}

if (!function_exists(__NAMESPACE__ . '\\cmxbu_contact_category_taxonomies')) {
	function cmxbu_contact_category_taxonomies(): array {
		$taxes = [];
		if (\function_exists(__NAMESPACE__ . '\\cmx_kundenkategorie_tax')) {
			$taxes[] = (string) cmx_kundenkategorie_tax();
		}
		$taxes = \array_merge($taxes, ['kontakte_kategorien', 'kontakte_kategorie', 'kundenkategorie', 'kontakt_kategorie']);
		$taxes = \array_values(\array_unique(\array_filter(\array_map('strval', $taxes))));
		$existing = [];
		foreach ($taxes as $tax) {
			if (\taxonomy_exists($tax)) {
				$existing[] = $tax;
			}
		}
		return $existing;
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmxbu_get_contact_primary_email')) {
	function cmxbu_get_contact_primary_email(int $post_id): string {
		$bundle = \get_post_meta($post_id, '_cmx_kommunikation', true);
		if (\is_array($bundle)) {
			$candidate = \sanitize_email((string) ($bundle['email'][1]['value'] ?? ''));
			if (\is_email($candidate)) {
				return $candidate;
			}
		}

		$direct = \sanitize_email((string) \get_post_meta($post_id, '_cmx_email_1', true));
		if (\is_email($direct)) {
			return $direct;
		}

		$fallback_keys = (array) \apply_filters('cmx_kontakte_email1_meta_keys', [
			'cmx_email_1', 'email_1', 'e_mail_1', 'kontakt_email', 'email', 'e_mail', 'mail',
		]);
		foreach ($fallback_keys as $key) {
			$key = \trim((string) $key);
			if ($key === '') {
				continue;
			}
			$candidate = \sanitize_email((string) \get_post_meta($post_id, $key, true));
			if (\is_email($candidate)) {
				return $candidate;
			}
		}

		return '';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmxbu_collect_contact_reply_emails')) {
	function cmxbu_collect_contact_reply_emails(int $post_id): array {
		$candidates = [];
		$add_email = static function (string $raw, int $priority = 0) use (&$candidates): void {
			$raw = \trim((string) $raw);
			if ($raw === '') {
				return;
			}
			$parts = \preg_split('/[;,\\r\\n]+/', $raw);
			if (!\is_array($parts)) {
				$parts = [$raw];
			}
			foreach ($parts as $part) {
				$email = \sanitize_email((string) $part);
				if (!\is_email($email)) {
					continue;
				}
				if (!isset($candidates[$email]) || $priority > (int) $candidates[$email]) {
					$candidates[$email] = $priority;
				}
			}
		};

		$bundle = \get_post_meta($post_id, '_cmx_kommunikation', true);
		if (\is_array($bundle)) {
			$preferred_labels = ['direkt', 'direct', 'geschaeft', 'geschäft', 'privat', 'private', 'haupt', 'main'];
			for ($i = 1; $i <= 3; $i++) {
				$email_val = (string) ($bundle['email'][$i]['value'] ?? '');
				$label = \sanitize_key((string) ($bundle['email'][$i]['label'] ?? ''));
				$prio = \in_array($label, $preferred_labels, true) ? 220 - $i : 200 - $i;
				$add_email($email_val, $prio);
			}
		}

		$direct_keys = ['_cmx_email_1', '_cmx_email_2', '_cmx_email_3'];
		foreach ($direct_keys as $idx => $key) {
			$add_email((string) \get_post_meta($post_id, $key, true), 170 - $idx);
		}

		$fallback_keys = (array) \apply_filters('cmx_kontakte_email1_meta_keys', [
			'cmx_email_1', 'email_1', 'e_mail_1', 'kontakt_email', 'email', 'e_mail', 'mail',
		]);
		foreach ($fallback_keys as $key) {
			$key = \trim((string) $key);
			if ($key === '') {
				continue;
			}
			$add_email((string) \get_post_meta($post_id, $key, true), 100);
		}

		if (empty($candidates)) {
			return [];
		}
		\arsort($candidates, \SORT_NUMERIC);
		return \array_keys($candidates);
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmxbu_get_me_contact_reply_to')) {
	function cmxbu_get_me_contact_reply_to(string $from_email = ''): array {
		$posts_by_id = [];
		$taxes = \function_exists(__NAMESPACE__ . '\\cmxbu_contact_category_taxonomies')
			? cmxbu_contact_category_taxonomies()
			: [];
		foreach ($taxes as $tax) {
			$queries = [
				['field' => 'slug', 'terms' => ['das-bin-ich', 'ich']],
				['field' => 'name', 'terms' => ['Das bin ich']],
			];
			foreach ($queries as $query) {
				$posts = \get_posts([
					'post_type' => ['kontakte', 'kontakt', 'contact'],
					'post_status' => ['publish', 'private'],
					'posts_per_page' => 25,
					'orderby' => 'modified',
					'order' => 'DESC',
					'tax_query' => [[
						'taxonomy' => $tax,
						'field' => $query['field'],
						'terms' => $query['terms'],
					]],
					'no_found_rows' => true,
					'suppress_filters' => true,
				]);
				foreach ((array) $posts as $post) {
					if (!$post instanceof \WP_Post) {
						continue;
					}
					$posts_by_id[(int) $post->ID] = $post;
				}
			}
		}

		if (empty($posts_by_id)) {
			return [];
		}

		$current_user_id = \get_current_user_id();
		$posts = \array_values($posts_by_id);
		\usort($posts, static function (\WP_Post $a, \WP_Post $b) use ($current_user_id): int {
			$wa = 0;
			$wb = 0;
			if ($current_user_id > 0) {
				if ((int) $a->post_author === $current_user_id) $wa += 1000;
				if ((int) $b->post_author === $current_user_id) $wb += 1000;
			}
			if ($a->post_status === 'publish') $wa += 50;
			if ($b->post_status === 'publish') $wb += 50;
			if ($wa !== $wb) return $wb <=> $wa;

			$am = \strtotime((string) $a->post_modified_gmt) ?: 0;
			$bm = \strtotime((string) $b->post_modified_gmt) ?: 0;
			if ($am !== $bm) return $bm <=> $am;
			return ((int) $b->ID) <=> ((int) $a->ID);
		});

		$from_email = \sanitize_email($from_email);
		foreach ($posts as $post) {
			$post_id = (int) $post->ID;
			$primary_email = \function_exists(__NAMESPACE__ . '\\cmxbu_get_contact_primary_email')
				? \sanitize_email(cmxbu_get_contact_primary_email($post_id))
				: '';
			if (\is_email($primary_email) && ($from_email === '' || \strcasecmp($primary_email, $from_email) !== 0)) {
				return [
					'email' => $primary_email,
					'name' => \trim((string) $post->post_title),
					'post_id' => $post_id,
					'source' => 'das_bin_ich_primary_email',
				];
			}

			$emails = \function_exists(__NAMESPACE__ . '\\cmxbu_collect_contact_reply_emails')
				? cmxbu_collect_contact_reply_emails($post_id)
				: [];
			if (empty($emails)) {
				continue;
			}

			$selected = '';
			if ($from_email !== '') {
				foreach ($emails as $candidate) {
					if (\strcasecmp((string) $candidate, $from_email) !== 0) {
						$selected = (string) $candidate;
						break;
					}
				}
			}
			if ($selected === '') {
				$selected = (string) ($emails[0] ?? '');
			}
			$selected = \sanitize_email($selected);
			if (!\is_email($selected)) {
				continue;
			}

			return [
				'email' => $selected,
				'name' => \trim((string) $post->post_title),
				'post_id' => $post_id,
				'source' => 'das_bin_ich_kontakt',
			];
		}

		return [];
	}
}

function cmxbu_get_beleg_due_date_display(int $post_id): string {
	$keys = [];
	if (defined(__NAMESPACE__ . '\\CMX_BELEG_META_FAELLIG')) {
		$keys[] = CMX_BELEG_META_FAELLIG;
	}
	$keys[] = '_cmx_beleg_faelligkeitsdatum';
	$keys[] = '_cmx_beleg_faellig_am';

	$raw = null;
	if (function_exists(__NAMESPACE__ . '\\cmxbu_first_meta')) {
		$raw = cmxbu_first_meta($post_id, $keys);
	} else {
		foreach ($keys as $key) {
			$val = get_post_meta($post_id, $key, true);
			if ($val !== '' && $val !== null) {
				$raw = (string) $val;
				break;
			}
		}
	}

	$raw = trim((string) $raw);
	if ($raw === '') return '';
	if (preg_match('~^\\d{1,2}\\.\\d{1,2}\\.\\d{4}$~', $raw)) return $raw;

	$ts = strtotime($raw);
	if ($ts) return date('d.m.Y', $ts);

	return $raw;
}

function cmxbu_get_beleg_amount_display(int $post_id): string {
	if (!function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
		return '';
	}

	$currency = '';
	if (defined(__NAMESPACE__ . '\\CMX_BELEG_META_WAEHRUNG')) {
		$currency = (string) get_post_meta($post_id, CMX_BELEG_META_WAEHRUNG, true);
	}
	if ($currency === '') {
		$currency = (string) get_post_meta($post_id, '_cmx_beleg_waehrung', true);
	}
	if ($currency === '') {
		$currency = 'CHF';
	}

	$mwst_rate = 0.0;
	if (function_exists(__NAMESPACE__ . '\\cmxbu_get_mwst_term_data')) {
		$mwst_term_id = (int) get_post_meta($post_id, '_cmx_beleg_mwst_term', true);
		$mwst = cmxbu_get_mwst_term_data($mwst_term_id);
		$mwst_rate = (float)($mwst['rate'] ?? 0.0);
	}
	$beleg_type = '';
	if (function_exists(__NAMESPACE__ . '\\cmx_get_beleg_type')) {
		$post = get_post($post_id);
		if ($post instanceof \WP_Post) {
			[, $beleg_type] = cmx_get_beleg_type($post);
		}
	}
	if ($beleg_type !== '' && function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_pdf_effective_type')) {
		$beleg_type = (string) cmxbu_get_beleg_pdf_effective_type($post_id, (string) $beleg_type);
	}

	$is_brutto = get_post_meta($post_id, '_cmx_beleg_is_brutto', true) === '1';
	$opts_general = (array) get_option('cmx_einstellungen', []);
	$is_mwst_pflichtig = \function_exists(__NAMESPACE__ . '\\cmx_belege_is_mwst_pflichtig')
		? cmx_belege_is_mwst_pflichtig($opts_general)
		: !empty($opts_general['mwst_pflichtig']);
	$mwst_allowed_for_type = \function_exists(__NAMESPACE__ . '\\cmx_belege_allows_mwst_for_type')
		? cmx_belege_allows_mwst_for_type((string) $beleg_type, $opts_general)
		: $is_mwst_pflichtig;
	if (!$mwst_allowed_for_type) {
		$mwst_rate = 0.0;
		$is_brutto = false;
	}

	$calc = cmxbu_get_beleg_positionen_calc($post_id, [
		'currency' => $currency,
		'tax_rate' => $mwst_rate,
		'is_brutto' => $is_brutto,
	]);

	$total = (float)($calc['total'] ?? 0.0);
	$formatted = cmx_format_swiss_number($total, 2);

	return trim($formatted . ' ' . $currency);
}
