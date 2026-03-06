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
		'cmx_email_bcc',
		'',
		static function (): void {
			$value = \esc_attr(cmx_email_option_value('email_bcc'));
			echo '<input type="email" class="regular-text" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[email_bcc]" value="' . $value . '" placeholder="DeineArchivMail@gmail.com" autocomplete="off" aria-label="E-Mail Adresse BCC">';
			echo '<span style="margin-left:8px;"><strong>BCC</strong> (Blind Carbon Copy)</span>';
		},
		$page,
		'cmx_sec_email_account'
	);

	\add_settings_section(
		'cmx_sec_email_smtp',
		'SMTP',
		static function (): void {},
		$page
	);

	\add_settings_field(
		'cmx_email_smtp_host',
		'Host*',
		static function (): void {
			$value = \esc_attr(cmx_email_option_value('smtp_host'));
			echo '<input type="text" class="regular-text" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[smtp_host]" value="' . $value . '" placeholder="smtp.infomaniak.com" autocomplete="off">';
			echo '<input type="hidden" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[smtp_port]" value="587">';
			echo '<label style="margin-left:8px;">Port <input type="text" class="small-text" value="587" readonly></label>';
		},
		$page,
		'cmx_sec_email_smtp'
	);

	\add_settings_field(
		'cmx_email_smtp_test',
		'',
		static function (): void {
			echo '<button type="button" class="button button-secondary" id="cmx-email-smtp-test" disabled>SMTP Verbindung testen</button>';
			echo '<div style="margin-top:5px;"><span id="cmx-email-smtp-result" class="cmx-email-test-result" aria-live="polite"></span></div>';
		},
		$page,
		'cmx_sec_email_smtp'
	);

	\add_settings_section(
		'cmx_sec_email_imap',
		'IMAP',
		static function (): void {},
		$page
	);

	\add_settings_field(
		'cmx_email_imap_host',
		'Host*',
		static function (): void {
			$value = \esc_attr(cmx_email_option_value('imap_host'));
			echo '<input type="text" class="regular-text" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[imap_host]" value="' . $value . '" placeholder="imap.infomaniak.com" autocomplete="off">';
			echo '<input type="hidden" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[imap_port]" value="143">';
			echo '<label style="margin-left:8px;">Port <input type="text" class="small-text" value="143" readonly></label>';
		},
		$page,
		'cmx_sec_email_imap'
	);

	\add_settings_field(
		'cmx_email_imap_test',
		'',
		static function (): void {
			echo '<button type="button" class="button button-secondary" id="cmx-email-imap-test" disabled>IMAP Verbindung testen</button>';
			echo '<div style="margin-top:5px;"><span id="cmx-email-imap-result" class="cmx-email-test-result" aria-live="polite"></span></div>';
		},
		$page,
		'cmx_sec_email_imap'
	);
});

\add_filter('pre_update_option_' . CMX_SETTINGS_MAIN, function ($new, $old) {
	if (!\is_array($new)) {
		return $new;
	}

	$new['email_address'] = \sanitize_email((string) ($new['email_address'] ?? ''));
	$new['email_password'] = (string) ($new['email_password'] ?? '');
	$new['email_bcc'] = \sanitize_email((string) ($new['email_bcc'] ?? ''));
	$new['smtp_host'] = \sanitize_text_field((string) ($new['smtp_host'] ?? ''));
	$new['imap_host'] = \sanitize_text_field((string) ($new['imap_host'] ?? ''));
	$new['smtp_port'] = '587';
	$new['imap_port'] = '143';

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
				return [true, 'SMTP-Verbindung erfolgreich. Du kannst nun E-Mails versenden.'];
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
			$mailbox = '{' . $host . ':' . $port . '/imap/notls}INBOX';
			$imap = @\imap_open($mailbox, $username, $password, OP_HALFOPEN, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);
			if ($imap !== false) {
				@\imap_close($imap);
				return [true, 'IMAP-Verbindung erfolgreich.'];
			}
			$err = \trim((string) \imap_last_error());
			return [false, $err !== '' ? 'IMAP-Test fehlgeschlagen: ' . $err : 'IMAP-Test fehlgeschlagen.'];
		}

		$errno = 0;
		$errstr = '';
		$fp = @\fsockopen($host, $port, $errno, $errstr, 10.0);
		if (!$fp) {
			return [false, 'IMAP-Server nicht erreichbar: ' . $errstr . ' (' . $errno . ')'];
		}
		@\stream_set_timeout($fp, 5);
		$banner = (string) \fgets($fp, 512);
		\fclose($fp);

		if ($banner !== '' && \stripos($banner, 'OK') !== false) {
			if (\function_exists('imap_open')) {
				return [true, 'IMAP-Server erreichbar.'];
			}
			return [true, 'IMAP-Server erreichbar (Login-Test nicht moeglich, PHP-IMAP fehlt).'];
		}
		return [true, 'IMAP-Server erreichbar.'];
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

	[$ok, $message] = cmx_email_test_imap_connection($host, 143, $email, $password);
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
