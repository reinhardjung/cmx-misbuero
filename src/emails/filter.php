<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_EMAIL_TRUSTED_SENDERS_OPTION')) {
	\define(__NAMESPACE__ . '\\CMX_EMAIL_TRUSTED_SENDERS_OPTION', 'cmx_email_trusted_senders');
}

if (!\defined(__NAMESPACE__ . '\\CMX_EMAIL_TRUSTED_DOMAINS_OPTION')) {
	\define(__NAMESPACE__ . '\\CMX_EMAIL_TRUSTED_DOMAINS_OPTION', 'cmx_email_trusted_domains');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_filter_statuses')) {
	function cmx_email_filter_statuses(): array {
		return [
			'posteingang' => 'Posteingang',
			'pruefen'     => 'Prüfen',
			'spam'        => 'Spam',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_filter_normalize_result')) {
	function cmx_email_filter_normalize_result(string $value): string {
		$value = \sanitize_key(\strtolower(\trim($value)));
		if ($value === '') {
			return 'unknown';
		}

		return \in_array($value, ['pass', 'fail', 'softfail', 'neutral', 'none', 'temperror', 'permerror', 'unknown'], true)
			? $value
			: 'unknown';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_filter_normalize_status')) {
	function cmx_email_filter_normalize_status(string $status): string {
		$status = \sanitize_key($status);
		return \array_key_exists($status, cmx_email_filter_statuses()) ? $status : 'pruefen';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_filter_extract_header_value')) {
	function cmx_email_filter_extract_header_value(string $headers_raw, string $header_name): string {
		$header_name = \preg_quote($header_name, '/');
		if (\preg_match('/^' . $header_name . ':\s*(.+(?:\r?\n[ \t].+)*)$/mi', $headers_raw, $matches) !== 1) {
			return '';
		}

		return \trim((string) \preg_replace('/\r?\n[ \t]+/', ' ', (string) ($matches[1] ?? '')));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_filter_extract_auth_result')) {
	function cmx_email_filter_extract_auth_result(string $headers_raw, string $type): string {
		$type = \strtolower(\trim($type));
		$haystacks = [];
		foreach (['Authentication-Results', 'ARC-Authentication-Results'] as $header_name) {
			$value = cmx_email_filter_extract_header_value($headers_raw, $header_name);
			if ($value !== '') {
				$haystacks[] = $value;
			}
		}
		$haystacks[] = $headers_raw;

		foreach ($haystacks as $haystack) {
			if (\preg_match('/(?:^|[\\s;])' . \preg_quote($type, '/') . '\s*=\s*([a-z]+)/i', $haystack, $matches) === 1) {
				return cmx_email_filter_normalize_result((string) ($matches[1] ?? ''));
			}
		}

		return 'unknown';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_filter_sender_domain')) {
	function cmx_email_filter_sender_domain(string $email): string {
		$email = \sanitize_email($email);
		if (!\is_email($email) || !\str_contains($email, '@')) {
			return '';
		}

		[, $domain] = \explode('@', \strtolower($email), 2);
		$domain = \trim((string) $domain);
		return \preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain) === 1 ? $domain : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_filter_normalize_option_list')) {
	function cmx_email_filter_normalize_option_list($value, bool $domains = false): array {
		$items = \is_array($value) ? $value : [];
		$out = [];

		foreach ($items as $item) {
			$item = \strtolower(\trim((string) $item));
			if ($item === '') {
				continue;
			}
			if ($domains) {
				$item = \sanitize_text_field($item);
				if (\preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $item) !== 1) {
					continue;
				}
			} else {
				$item = \sanitize_email($item);
				if (!\is_email($item)) {
					continue;
				}
			}
			$out[$item] = $item;
		}

		return \array_values($out);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_filter_trusted_senders')) {
	function cmx_email_filter_trusted_senders(): array {
		return cmx_email_filter_normalize_option_list(\get_option(CMX_EMAIL_TRUSTED_SENDERS_OPTION, []), false);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_filter_trusted_domains')) {
	function cmx_email_filter_trusted_domains(): array {
		return cmx_email_filter_normalize_option_list(\get_option(CMX_EMAIL_TRUSTED_DOMAINS_OPTION, []), true);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_filter_sender_is_trusted')) {
	function cmx_email_filter_sender_is_trusted(string $sender_email, string $sender_domain = ''): bool {
		$sender_email = \strtolower(\sanitize_email($sender_email));
		$sender_domain = $sender_domain !== '' ? \strtolower($sender_domain) : cmx_email_filter_sender_domain($sender_email);

		return ($sender_email !== '' && \in_array($sender_email, cmx_email_filter_trusted_senders(), true))
			|| ($sender_domain !== '' && \in_array($sender_domain, cmx_email_filter_trusted_domains(), true));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_filter_add_trusted_sender')) {
	function cmx_email_filter_add_trusted_sender(string $sender_email, bool $include_domain = true): array {
		$sender_email = \strtolower(\sanitize_email($sender_email));
		$sender_domain = cmx_email_filter_sender_domain($sender_email);

		if (\is_email($sender_email)) {
			$senders = \array_fill_keys(cmx_email_filter_trusted_senders(), true);
			$senders[$sender_email] = $sender_email;
			\update_option(CMX_EMAIL_TRUSTED_SENDERS_OPTION, \array_keys($senders), false);
		}

		if ($include_domain && $sender_domain !== '') {
			$domains = \array_fill_keys(cmx_email_filter_trusted_domains(), true);
			$domains[$sender_domain] = $sender_domain;
			\update_option(CMX_EMAIL_TRUSTED_DOMAINS_OPTION, \array_keys($domains), false);
		}

		return [
			'email' => \is_email($sender_email) ? $sender_email : '',
			'domain' => $sender_domain,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_filter_decide_status')) {
	function cmx_email_filter_decide_status(string $spf, string $dkim, string $dmarc, bool $trusted): string {
		$spf = cmx_email_filter_normalize_result($spf);
		$dkim = cmx_email_filter_normalize_result($dkim);
		$dmarc = cmx_email_filter_normalize_result($dmarc);

		if ($dkim === 'fail' || $dmarc === 'fail') {
			return 'spam';
		}

		if ($spf === 'pass' && $dkim === 'pass' && $dmarc === 'pass') {
			return $trusted ? 'posteingang' : 'pruefen';
		}

		return 'pruefen';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_filter_analyze_headers')) {
	function cmx_email_filter_analyze_headers(string $headers_raw, string $sender_email): array {
		$sender_email = \strtolower(\sanitize_email($sender_email));
		$sender_domain = cmx_email_filter_sender_domain($sender_email);
		$trusted = cmx_email_filter_sender_is_trusted($sender_email, $sender_domain);
		$spf = cmx_email_filter_extract_auth_result($headers_raw, 'spf');
		$dkim = cmx_email_filter_extract_auth_result($headers_raw, 'dkim');
		$dmarc = cmx_email_filter_extract_auth_result($headers_raw, 'dmarc');

		return [
			'spf' => $spf,
			'dkim' => $dkim,
			'dmarc' => $dmarc,
			'sender_email' => $sender_email,
			'sender_domain' => $sender_domain,
			'sender_trusted' => $trusted ? '1' : '0',
			'filter_status' => cmx_email_filter_decide_status($spf, $dkim, $dmarc, $trusted),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_filter_apply_to_post')) {
	function cmx_email_filter_apply_to_post(int $post_id, string $headers_raw = '', string $sender_email = '', bool $update_folder = true): string {
		$post_id = (int) $post_id;
		if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_EMAILS_CPT) {
			return 'pruefen';
		}

		$headers_raw = $headers_raw !== '' ? $headers_raw : (string) \get_post_meta($post_id, cmx_emails_meta_key('headers_raw'), true);
		$sender_email = $sender_email !== '' ? $sender_email : (string) \get_post_meta($post_id, cmx_emails_meta_key('sender_email'), true);
		$analysis = cmx_email_filter_analyze_headers($headers_raw, $sender_email);

		\update_post_meta($post_id, '_cmx_email_spf_result', $analysis['spf']);
		\update_post_meta($post_id, '_cmx_email_dkim_result', $analysis['dkim']);
		\update_post_meta($post_id, '_cmx_email_dmarc_result', $analysis['dmarc']);
		\update_post_meta($post_id, '_cmx_email_filter_status', $analysis['filter_status']);
		\update_post_meta($post_id, '_cmx_email_sender_email', $analysis['sender_email']);
		\update_post_meta($post_id, '_cmx_email_sender_domain', $analysis['sender_domain']);
		\update_post_meta($post_id, '_cmx_email_sender_trusted', $analysis['sender_trusted']);

		if ($update_folder && \function_exists(__NAMESPACE__ . '\\cmx_email_filter_set_status')) {
			cmx_email_filter_set_status($post_id, (string) $analysis['filter_status']);
		}

		return (string) $analysis['filter_status'];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_filter_set_status')) {
	function cmx_email_filter_set_status(int $post_id, string $status): void {
		$status = cmx_email_filter_normalize_status($status);
		\update_post_meta($post_id, '_cmx_email_filter_status', $status);
		if ($status === 'spam') {
			\update_post_meta($post_id, cmx_emails_meta_key('folder'), 'spam');
			\update_post_meta($post_id, cmx_emails_meta_key('spam_status'), 'spam');
			\delete_post_meta($post_id, cmx_emails_meta_key('archive_year'));
			\delete_post_meta($post_id, cmx_emails_meta_key('archive_month'));
		} elseif ($status === 'posteingang') {
			\update_post_meta($post_id, cmx_emails_meta_key('folder'), 'inbox');
			\update_post_meta($post_id, cmx_emails_meta_key('spam_status'), 'clean');
			\delete_post_meta($post_id, cmx_emails_meta_key('archive_year'));
			\delete_post_meta($post_id, cmx_emails_meta_key('archive_month'));
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_filter_action_url')) {
	function cmx_email_filter_action_url(int $post_id, string $action): string {
		$args = [
			'action' => $action,
			'post_id' => (int) $post_id,
		];
		$filters = \function_exists(__NAMESPACE__ . '\\cmx_emails_current_filters') ? cmx_emails_current_filters() : [];
		if ((string) ($filters['account_id'] ?? '') !== '') {
			$args['account_id'] = (string) $filters['account_id'];
		}
		if ((string) ($filters['folder'] ?? '') !== '') {
			$args['folder'] = (string) $filters['folder'];
		}
		if ((string) ($filters['archive_year'] ?? '') !== '') {
			$args['archive_year'] = (string) $filters['archive_year'];
		}
		if ((string) ($filters['archive_month'] ?? '') !== '') {
			$args['archive_month'] = (string) $filters['archive_month'];
		}

		return \wp_nonce_url(\add_query_arg($args, \admin_url('admin-post.php')), $action . '_' . (int) $post_id);
	}
}

\add_action('admin_post_cmx_email_allow_sender', function (): void {
	if (!\current_user_can('manage_options')) {
		\wp_die('Keine Berechtigung.');
	}

	$post_id = isset($_REQUEST['post_id']) ? (int) \wp_unslash($_REQUEST['post_id']) : 0;
	\check_admin_referer('cmx_email_allow_sender_' . $post_id);
	$context = \function_exists(__NAMESPACE__ . '\\cmx_emails_action_context') ? cmx_emails_action_context($post_id) : [];

	if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_EMAILS_CPT) {
		cmx_emails_redirect_with_notice($context, 'E-Mail wurde nicht gefunden.', 'error');
	}

	$sender_email = \sanitize_email((string) \get_post_meta($post_id, cmx_emails_meta_key('sender_email'), true));
	if (!\is_email($sender_email)) {
		cmx_emails_redirect_with_notice($context, 'Absender-Adresse ist ungültig.', 'error');
	}

	$trusted = cmx_email_filter_add_trusted_sender($sender_email, true);
	\update_post_meta($post_id, '_cmx_email_sender_email', (string) ($trusted['email'] ?? ''));
	\update_post_meta($post_id, '_cmx_email_sender_domain', (string) ($trusted['domain'] ?? ''));
	\update_post_meta($post_id, '_cmx_email_sender_trusted', '1');
	cmx_email_filter_set_status($post_id, 'posteingang');

	cmx_emails_redirect_with_notice($context, 'Absender wurde zugelassen.', 'success');
});

\add_action('admin_post_cmx_email_mark_spam', function (): void {
	if (!\current_user_can('manage_options')) {
		\wp_die('Keine Berechtigung.');
	}

	$post_id = isset($_REQUEST['post_id']) ? (int) \wp_unslash($_REQUEST['post_id']) : 0;
	\check_admin_referer('cmx_email_mark_spam_' . $post_id);
	$context = \function_exists(__NAMESPACE__ . '\\cmx_emails_action_context') ? cmx_emails_action_context($post_id) : [];

	if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_EMAILS_CPT) {
		cmx_emails_redirect_with_notice($context, 'E-Mail wurde nicht gefunden.', 'error');
	}

	cmx_email_filter_set_status($post_id, 'spam');
	cmx_emails_redirect_with_notice($context, 'E-Mail wurde als Spam markiert.', 'success');
});
