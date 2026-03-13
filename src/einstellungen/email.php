<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_tab_is_active')) {
	function cmx_email_tab_is_active(): bool {
		if (!\is_admin()) {
			return false;
		}
		$page = isset($_GET['page']) ? \sanitize_key((string) \wp_unslash($_GET['page'])) : '';
		$tab = isset($_GET['tab']) ? \sanitize_key((string) \wp_unslash($_GET['tab'])) : 'general';
		return ($page === CMX_SETTINGS_SLUG && $tab === 'email');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_option_value')) {
	function cmx_email_option_value(string $key, string $default = ''): string {
		$options = (array) \get_option(CMX_SETTINGS_MAIN, []);
		$value = $options[$key] ?? $default;
		return \is_scalar($value) ? (string) $value : $default;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_agb_link')) {
	function cmx_email_agb_link(): string {
		$link = \trim((string) cmx_email_option_value('agb_link'));
		if ($link === '') {
			return '';
		}
		return \esc_url_raw($link);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_agb_footer_html')) {
	function cmx_email_agb_footer_html(string $link_style = ''): string {
		$link = cmx_email_agb_link();
		if ($link === '') {
			return '';
		}

		$link_attr = $link_style !== '' ? ' style="' . \esc_attr($link_style) . '"' : '';
		return 'Es gelten unsere Allgemeinen Geschäftsbedingungen: <a href="' . \esc_url($link) . '"' . $link_attr . '>' . \esc_html($link) . '</a>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_agb_footer_text')) {
	function cmx_email_agb_footer_text(): string {
		$link = cmx_email_agb_link();
		if ($link === '') {
			return '';
		}

		return 'Es gelten unsere Allgemeinen Geschäftsbedingungen: ' . $link;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_agb_placeholder')) {
	function cmx_email_agb_placeholder(): string {
		$fallback = 'https://beispiel.ch/agb';
		$posts = \get_posts([
			'post_type'        => ['kontakte', 'kontakt', 'contact'],
			'post_status'      => ['publish', 'private'],
			'posts_per_page'   => 1,
			'tax_query'        => [
				'relation' => 'OR',
				['taxonomy' => 'kontakte_kategorien', 'field' => 'slug', 'terms' => ['das-bin-ich', 'ich']],
				['taxonomy' => 'kontakte_kategorien', 'field' => 'name', 'terms' => ['Das bin ich']],
			],
			'no_found_rows'    => true,
			'suppress_filters' => true,
		]);

		if (empty($posts) || empty($posts[0])) {
			return $fallback;
		}

		$raw_url = \trim((string) \get_post_meta((int) $posts[0]->ID, '_cmx_kontakte_url', true));
		if ($raw_url === '') {
			return $fallback;
		}

		if (!\preg_match('~^https?://~i', $raw_url)) {
			$raw_url = 'https://' . \ltrim($raw_url, '/');
		}

		$parts = \wp_parse_url($raw_url);
		if (!empty($parts['host'])) {
			$scheme = \strtolower((string) ($parts['scheme'] ?? 'https'));
			$base = $scheme . '://' . $parts['host'];
			if (!empty($parts['port'])) {
				$base .= ':' . (int) $parts['port'];
			}
			return \rtrim($base, '/') . '/agb';
		}

	return \rtrim($raw_url, '/') . '/agb';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_kundenportal_link_enabled')) {
	function cmx_email_kundenportal_link_enabled(): bool {
		return cmx_email_option_value('kundenportal_link') === '1';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_kundenportal_url')) {
	function cmx_email_kundenportal_url(int $kontakt_id): string {
		$kontakt_id = (int) $kontakt_id;
		if ($kontakt_id <= 0 || !cmx_email_kundenportal_link_enabled()) {
			return '';
		}

		if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_belege_share_url')) {
			return '';
		}

		$url = (string) cmx_kontakt_belege_share_url($kontakt_id);
		return $url !== '' ? \esc_url_raw($url) : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_kundenportal_footer_html')) {
	function cmx_email_kundenportal_footer_html(int $kontakt_id, string $link_style = ''): string {
		$link = cmx_email_kundenportal_url($kontakt_id);
		if ($link === '') {
			return '';
		}

		$link_attr = $link_style !== '' ? ' style="' . \esc_attr($link_style) . '"' : '';
		return '<a href="' . \esc_url($link) . '"' . $link_attr . '>Link zum Kundenportal</a>';
	}
}

\add_action('admin_init', function (): void {
	$page = 'cmx_tab_email';

	\add_settings_section(
		'cmx_sec_email_account',
		'Dein E-Mail Konto',
		static function (): void {
			echo '<p class="description">Zugangsdaten zum senden und empfangen Deiner E-Mails.</p>';
		},
		$page
	);

	\add_settings_field(
		'cmx_email_address',
		'E-Mail Adresse*',
		static function (): void {
			$value = \esc_attr(cmx_email_option_value('email_address'));
			echo '<input type="email" class="regular-text" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[email_address]" value="' . $value . '" autocomplete="username">';
		},
		$page,
		'cmx_sec_email_account'
	);

	\add_settings_field(
		'cmx_email_password',
		'Kennwort*',
		static function (): void {
			$value = \esc_attr(cmx_email_option_value('email_password'));
			echo '<input type="password" class="regular-text" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[email_password]" value="' . $value . '" autocomplete="current-password">';
		},
		$page,
		'cmx_sec_email_account'
	);

	\add_settings_field(
		'cmx_email_alias',
		'Für allgemeine E-Mails',
		static function (): void {
			$value = \esc_attr(cmx_email_option_value('email_alias'));
			echo '<input type="email" class="regular-text" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[email_alias]" value="' . $value . '" placeholder="office@beispiel.ch" autocomplete="off">';
			echo '<span style="margin-left:8px;">(optional) <i>Dieser <code>Alias</code> ist dann zusätzlich im Mailserver einzurichten</i></span>';
		},
		$page,
		'cmx_sec_email_account'
	);

	\add_settings_field(
		'cmx_email_alias_belege',
		'E-Mail für Belegversand',
		static function (): void {
			$value = \esc_attr(cmx_email_option_value('email_alias_belege'));
			echo '<input type="email" class="regular-text" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[email_alias_belege]" value="' . $value . '" placeholder="belege@beispiel.ch" autocomplete="off">';
			echo '<span style="margin-left:8px;">(optional) <i>Dieser <code>Alias</code> ist dann zusätzlich im Mailserver einzurichten</i></span>';
		},
		$page,
		'cmx_sec_email_account'
	);

	\add_settings_field(
		'cmx_email_reply',
		'Antwortadresse',
		static function (): void {
			$value = \esc_attr(cmx_email_option_value('reply'));
			echo '<input type="email" class="regular-text" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[reply]" value="' . $value . '" placeholder="antwort@beispiel.ch" autocomplete="off">';
		},
		$page,
		'cmx_sec_email_account'
	);

	\add_settings_field(
		'cmx_email_supplier',
		'Lieferantenrechnung',
		static function (): void {
			$value = \esc_attr(cmx_email_option_value('supplier'));
			$email_address = \sanitize_email(cmx_email_option_value('email_address'));
			if (!\is_email($email_address)) {
				$email_address = 'E-Mail-Adresse';
			}
			echo '<input type="email" class="regular-text" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[supplier]" value="' . $value . '" placeholder="rechnung@beispiel.ch" autocomplete="off">';
			echo '<span style="margin-left:8px;">(optional) muss dann an die <code>' . \esc_html($email_address) . '</code> weitergeleitet werden</span>';
		},
		$page,
		'cmx_sec_email_account'
	);

	\add_settings_field(
		'cmx_email_bcc',
		'',
		static function (): void {
			$value = \esc_attr(cmx_email_option_value('email_bcc'));
			echo '<input type="email" class="regular-text" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[email_bcc]" value="' . $value . '" placeholder="a@beispiel.ch, b@beispiel.ch" autocomplete="off" aria-label="E-Mail Adresse BCC" multiple>';
			echo '<span style="margin-left:8px;" title="Sendet eine versteckte Kopie der E-Mail an zusätzliche Empfänger"><strong>BCC</strong> (Blind Carbon Copy) <i>mehrere möglich, durch KOMMA separiert</i></span>';
		},
		$page,
		'cmx_sec_email_account'
	);

	\add_settings_section(
		'cmx_sec_email_smtp',
		'Server',
		static function (): void {},
		$page
	);

	\add_settings_field(
		'cmx_email_smtp_host',
		'SMTP (587)',
		static function (): void {
			$value = \esc_attr(cmx_email_option_value('smtp_host'));
			echo '<input type="text" class="regular-text" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[smtp_host]" value="' . $value . '" placeholder="smtp.infomaniak.com" autocomplete="off">';
			echo '<button type="button" class="button button-secondary" id="cmx-email-smtp-test" disabled style="margin-left:8px;">SMTP Verbindung testen</button>';
			echo '<div style="margin-top:5px;height:22px;overflow:hidden;"><span id="cmx-email-smtp-result" class="cmx-email-test-result" aria-live="polite" style="display:inline-block;max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></span></div>';
		},
		$page,
		'cmx_sec_email_smtp'
	);

	\add_settings_section(
		'cmx_sec_email_imap',
		'',
		static function (): void {},
		$page
	);

	\add_settings_field(
		'cmx_email_imap_host',
		'IMAP (993)',
		static function (): void {
			$value = \esc_attr(cmx_email_option_value('imap_host'));
			echo '<input type="text" class="regular-text" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[imap_host]" value="' . $value . '" placeholder="imap.infomaniak.com" autocomplete="off">';
			echo '<button type="button" class="button button-secondary" id="cmx-email-imap-test" disabled style="margin-left:8px;">IMAP Verbindung testen</button>';
			echo '<div style="margin-top:5px;height:22px;overflow:hidden;"><span id="cmx-email-imap-result" class="cmx-email-test-result" aria-live="polite" style="display:inline-block;max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></span></div>';
		},
		$page,
		'cmx_sec_email_imap'
	);

	\add_settings_section(
		'cmx_sec_email_links',
		'Links',
		static function (): void {},
		$page
	);

	\add_settings_field(
		'cmx_email_agb_link',
		'AGB',
		static function (): void {
			$value = \esc_attr(cmx_email_option_value('agb_link'));
			$placeholder = \esc_attr(cmx_email_agb_placeholder());
			echo '<input type="url" class="regular-text" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[agb_link]" value="' . $value . '" placeholder="' . $placeholder . '" autocomplete="off">';
			echo '<span style="margin-left:8px;">Link zu Deinen AGB.</span>';
		},
		$page,
		'cmx_sec_email_links'
	);

	\add_settings_field(
		'cmx_email_kundenportal_link',
		'',
		static function (): void {
			$checked = cmx_email_option_value('kundenportal_link') === '1';
			echo '<input type="hidden" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[kundenportal_link]" value="0">';
			echo '<label><input type="checkbox" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[kundenportal_link]" value="1" ' . \checked($checked, true, false) . '> Link zum Kunden Portal</label>';
		},
		$page,
		'cmx_sec_email_links'
	);
});

\add_filter('pre_update_option_' . CMX_SETTINGS_MAIN, function ($new, $old) {
	if (!\is_array($new)) {
		return $new;
	}

	$new['email_address'] = \sanitize_email((string) ($new['email_address'] ?? ''));
	$new['email_password'] = (string) ($new['email_password'] ?? '');
	$new['email_alias'] = \sanitize_email((string) ($new['email_alias'] ?? ''));
	$new['email_alias_belege'] = \sanitize_email((string) ($new['email_alias_belege'] ?? ''));
	$new['reply'] = \sanitize_email((string) ($new['reply'] ?? ''));
	$new['supplier'] = \sanitize_email((string) ($new['supplier'] ?? ''));
	$bcc_raw = (string) ($new['email_bcc'] ?? '');
	$bcc_parts = \preg_split('/[,\s;]+/', $bcc_raw) ?: [];
	$bcc_clean = [];
	foreach ($bcc_parts as $bcc_part) {
		$candidate = \sanitize_email((string) $bcc_part);
		if (\is_email($candidate)) {
			$bcc_clean[$candidate] = $candidate;
		}
	}
	$new['email_bcc'] = \implode(', ', \array_values($bcc_clean));
	$new['smtp_host'] = \sanitize_text_field((string) ($new['smtp_host'] ?? ''));
	$new['imap_host'] = \sanitize_text_field((string) ($new['imap_host'] ?? ''));
	$new['agb_link'] = \esc_url_raw((string) ($new['agb_link'] ?? ''));
	$new['kundenportal_link'] = !empty($new['kundenportal_link']) ? '1' : '0';
	$new['smtp_port'] = '587';
	$new['imap_port'] = '993';

	return $new;
}, 20, 2);

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_ajax_guard')) {
	function cmx_email_ajax_guard(): void {
		if (!\current_user_can('manage_options')) {
			\wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
		}
		$nonce = isset($_POST['_ajax_nonce']) ? (string) \wp_unslash($_POST['_ajax_nonce']) : '';
		if (!\wp_verify_nonce($nonce, 'cmx_email_test_conn')) {
			\wp_send_json_error(['message' => 'Ungueltige Anfrage.'], 403);
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_test_smtp_connection')) {
	function cmx_email_test_smtp_connection(string $host, int $port, string $username, string $password): array {
		if (\trim($host) === '') {
			return [false, ' Bitte SMTP-Host eintragen.'];
		}

		if (!\class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
			$base = \trailingslashit(ABSPATH . WPINC . '/PHPMailer');
			require_once $base . 'Exception.php';
			require_once $base . 'PHPMailer.php';
			require_once $base . 'SMTP.php';
		}

		try {
			$mail = new \PHPMailer\PHPMailer\PHPMailer(true);
			$mail->isSMTP();
			$mail->Host = $host;
			$mail->Port = $port;
			$mail->Timeout = 12;
			$mail->SMTPAutoTLS = true;
			$mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
			$mail->SMTPOptions = [
				'ssl' => [
					'verify_peer' => false,
					'verify_peer_name' => false,
					'allow_self_signed' => true,
				],
			];

			if ($username !== '' || $password !== '') {
				$mail->SMTPAuth = true;
				$mail->Username = $username;
				$mail->Password = $password;
			} else {
				$mail->SMTPAuth = false;
			}

			$connected = $mail->smtpConnect();
			$error = \trim((string) $mail->ErrorInfo);
			$mail->smtpClose();

			if ($connected) {
				return [true, 'SMTP-Verbindung erfolgreich. Du kannst nun E-Mails versenden!'];
			}
			return [false, $error !== '' ? 'SMTP-Test fehlgeschlagen: ' . $error : 'SMTP-Test fehlgeschlagen.'];
		} catch (\Throwable $e) {
			return [false, 'SMTP-Test fehlgeschlagen: ' . $e->getMessage()];
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_test_imap_connection')) {
	function cmx_email_test_imap_connection(string $host, int $port, string $username, string $password): array {
		if (\trim($host) === '') {
			return [false, ' Bitte IMAP-Host eintragen.'];
		}

		if (\function_exists('imap_open') && $username !== '' && $password !== '') {
			$mailboxes = [
				'{' . $host . ':' . $port . '/imap/ssl}INBOX',
				'{' . $host . ':' . $port . '/imap/ssl/novalidate-cert}INBOX',
			];
			foreach ($mailboxes as $mailbox) {
				$imap = @\imap_open($mailbox, $username, $password, OP_HALFOPEN, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);
				if ($imap !== false) {
					@\imap_close($imap);
					return [true, 'IMAP-Verbindung erfolgreich. Du kannst nun E-Mails empfangen!'];
				}
			}
			$err = \trim((string) \imap_last_error());
			return [false, $err !== '' ? 'IMAP-Test fehlgeschlagen: ' . $err : 'IMAP-Test fehlgeschlagen.'];
		}

		$errno = 0;
		$errstr = '';
		$fp = @\fsockopen('ssl://' . $host, $port, $errno, $errstr, 10.0);
		if (!$fp) {
			return [false, 'IMAP-Server nicht erreichbar: ' . $errstr . ' (' . $errno . ')'];
		}
		\fclose($fp);

		if (\function_exists('imap_open')) {
			return [true, 'IMAP-Server erreichbar.'];
		}
		return [true, 'IMAP-Server via SSL erreichbar (Login-Test nicht moeglich, PHP-IMAP fehlt).'];
	}
}

\add_action('wp_ajax_cmx_email_test_smtp', function (): void {
	cmx_email_ajax_guard();

	$host = \sanitize_text_field((string) (\wp_unslash($_POST['host'] ?? '')));
	$email = \sanitize_email((string) (\wp_unslash($_POST['email'] ?? '')));
	$password = (string) (\wp_unslash($_POST['password'] ?? ''));

	[$ok, $message] = cmx_email_test_smtp_connection($host, 587, $email, $password);
	if ($ok) {
		\wp_send_json_success(['message' => $message]);
	}
	\wp_send_json_error(['message' => $message], 400);
});

\add_action('wp_ajax_cmx_email_test_imap', function (): void {
	cmx_email_ajax_guard();

	$host = \sanitize_text_field((string) (\wp_unslash($_POST['host'] ?? '')));
	$email = \sanitize_email((string) (\wp_unslash($_POST['email'] ?? '')));
	$password = (string) (\wp_unslash($_POST['password'] ?? ''));

	[$ok, $message] = cmx_email_test_imap_connection($host, 993, $email, $password);
	if ($ok) {
		\wp_send_json_success(['message' => $message]);
	}
	\wp_send_json_error(['message' => $message], 400);
});

\add_action('admin_footer', function (): void {
	if (!cmx_email_tab_is_active()) {
		return;
	}

	$ajax_nonce = \wp_create_nonce('cmx_email_test_conn');
	?>
	<script>
	(function(){
		var ajaxUrl = window.ajaxurl || '';
		var nonce = <?php echo \wp_json_encode($ajax_nonce); ?>;
		var settingsKey = <?php echo \wp_json_encode((string) CMX_SETTINGS_MAIN); ?>;

		function byName(name){
			return document.querySelector('[name="' + settingsKey + '[' + name + ']"]');
		}
		function setResult(el, ok, message){
			if (!el) return;
			el.textContent = message || '';
			el.style.color = ok ? '#00a32a' : '#d63638';
		}
		function setPending(el, message){
			if (!el) return;
			el.textContent = message || '';
			el.style.color = '#50575e';
		}
		function runTest(action, hostKey, buttonId, resultId){
			var btn = document.getElementById(buttonId);
			var result = document.getElementById(resultId);
			var emailEl = byName('email_address');
			var passEl = byName('email_password');
			var hostEl = byName(hostKey);
			if (!btn || !result || !emailEl || !passEl || !hostEl) return;

			function hasRequiredData(){
				var email = String(emailEl.value || '').trim();
				var password = String(passEl.value || '').trim();
				var host = String(hostEl.value || '').trim();
				return (email !== '' && password !== '' && host !== '');
			}

			function updateButtonState(){
				btn.disabled = !hasRequiredData();
			}

			[emailEl, passEl, hostEl].forEach(function(el){
				el.addEventListener('input', updateButtonState);
				el.addEventListener('change', updateButtonState);
			});

			hostEl.addEventListener('click', function(){
				if (String(hostEl.value || '').trim() !== '') {
					return;
				}
				var placeholder = String(hostEl.getAttribute('placeholder') || '').trim();
				if (placeholder === '') {
					return;
				}
				hostEl.value = placeholder;
				hostEl.dispatchEvent(new Event('input', { bubbles: true }));
			});

			updateButtonState();

			btn.addEventListener('click', function(){
				if (!hasRequiredData()) {
					setResult(result, false, 'Bitte alle Felder ausfuellen.');
					updateButtonState();
					return;
				}
				var email = String(emailEl.value || '').trim();
				var password = String(passEl.value || '').trim();
				var host = String(hostEl.value || '').trim();

				setPending(result, 'Teste Verbindung...');
				btn.disabled = true;

				var fd = new FormData();
				fd.append('action', action);
				fd.append('_ajax_nonce', nonce);
				fd.append('email', email);
				fd.append('password', password);
				fd.append('host', host);

				fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: fd
				})
				.then(function(res){ return res.json(); })
				.then(function(json){
					var ok = !!(json && json.success);
					var message = json && json.data && json.data.message ? String(json.data.message) : (ok ? 'OK' : 'Fehlgeschlagen.');
					setResult(result, ok, message);
				})
				.catch(function(){
					setResult(result, false, 'Verbindungstest fehlgeschlagen.');
				})
				.finally(function(){
					updateButtonState();
				});
			});
		}

		runTest('cmx_email_test_smtp', 'smtp_host', 'cmx-email-smtp-test', 'cmx-email-smtp-result');
		runTest('cmx_email_test_imap', 'imap_host', 'cmx-email-imap-test', 'cmx-email-imap-result');
	})();
	</script>
	<?php
});
