<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_settings_option_name')) {
	function cmx_emails_settings_option_name(): string {
		if (\defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')) {
			$name = (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN');
			if ($name !== '') {
				return $name;
			}
		}
		return 'cmx_einstellungen';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_normalize_client_rows')) {
	function cmx_emails_normalize_client_rows($rows): array {
		if (\function_exists(__NAMESPACE__ . '\\cmx_email_normalize_client_list')) {
			return (array) cmx_email_normalize_client_list($rows);
		}

		$rows = \is_array($rows) ? $rows : [];
		$list = [];
		$index = 1;

		foreach ($rows as $row) {
			if (!\is_array($row)) {
				continue;
			}

			$id = \sanitize_key((string) ($row['id'] ?? ''));
			$name = \sanitize_text_field((string) ($row['name'] ?? ''));
			$email = \sanitize_email((string) ($row['email'] ?? ''));
			$password = (string) ($row['password'] ?? '');
			$smtp_host = \sanitize_text_field((string) ($row['smtp_host'] ?? ''));
			$imap_host = \sanitize_text_field((string) ($row['imap_host'] ?? ''));
			$client = \sanitize_text_field((string) ($row['client'] ?? ''));

			if ($id === '') {
				$id = 'client_' . $index;
			}

			if ($name === '' && $email === '' && $password === '' && $smtp_host === '' && $imap_host === '' && $client === '') {
				continue;
			}

			$list[] = [
				'id'        => $id,
				'client'    => $client,
				'name'      => $name,
				'email'     => $email,
				'password'  => $password,
				'smtp_host' => $smtp_host,
				'smtp_port' => '587',
				'imap_host' => $imap_host,
				'imap_port' => '993',
			];
			$index++;
		}

		return $list;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_legacy_client_row')) {
	function cmx_emails_legacy_client_row(array $options): array {
		$name = \sanitize_text_field((string) ($options['email_name'] ?? ''));
		$email = \sanitize_email((string) ($options['email_address'] ?? ''));
		$password = (string) ($options['email_password'] ?? '');
		$smtp_host = \sanitize_text_field((string) ($options['smtp_host'] ?? ''));
		$imap_host = \sanitize_text_field((string) ($options['imap_host'] ?? ''));

		if ($name === '' && $email === '' && $password === '' && $smtp_host === '' && $imap_host === '') {
			return [];
		}

		return [
			'id'        => 'default',
			'client'    => 'Standard',
			'name'      => $name,
			'email'     => $email,
			'password'  => $password,
			'smtp_host' => $smtp_host,
			'smtp_port' => '587',
			'imap_host' => $imap_host,
			'imap_port' => '993',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_client_list')) {
	function cmx_emails_client_list(): array {
		$options = (array) \get_option(cmx_emails_settings_option_name(), []);
		$list = \function_exists(__NAMESPACE__ . '\\cmx_email_client_list')
			? (array) cmx_email_client_list()
			: cmx_emails_normalize_client_rows($options['email_clients'] ?? []);

		$legacy = cmx_emails_legacy_client_row($options);
		if ($legacy !== []) {
			$list[] = $legacy;
		}

		$unique = [];
		foreach ($list as $client) {
			$client = \is_array($client) ? $client : [];
			if ($client === []) {
				continue;
			}
			$id = \sanitize_key((string) ($client['id'] ?? ''));
			$email = \sanitize_email((string) ($client['email'] ?? ''));
			$key = $email !== '' ? 'mail:' . \strtolower($email) : ($id !== '' ? 'id:' . $id : '');
			if ($key === '' || isset($unique[$key])) {
				continue;
			}
			$unique[$key] = $client;
		}

		return \array_values($unique);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_get_client')) {
	function cmx_emails_get_client(string $client_id = ''): array {
		$client_id = \sanitize_key($client_id);
		$clients = cmx_emails_client_list();
		if ($clients === []) {
			return [];
		}

		if ($client_id !== '') {
			foreach ($clients as $client) {
				$id = \sanitize_key((string) ($client['id'] ?? ''));
				if ($id === $client_id) {
					return (array) $client;
				}
			}
		}

		return (array) $clients[0];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_default_client_id')) {
	function cmx_emails_default_client_id(): string {
		$client = cmx_emails_get_client();
		return \sanitize_key((string) ($client['id'] ?? ''));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_client_label')) {
	function cmx_emails_client_label(array $client): string {
		$name = \trim((string) ($client['name'] ?? ''));
		$email = \sanitize_email((string) ($client['email'] ?? ''));
		$fallback = \trim((string) ($client['client'] ?? ''));

		if ($name !== '' && $email !== '') {
			return $name . ' <' . $email . '>';
		}
		if ($email !== '') {
			return $email;
		}
		if ($name !== '') {
			return $name;
		}
		if ($fallback !== '') {
			return $fallback;
		}
		return \sanitize_key((string) ($client['id'] ?? 'client'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_settings_url')) {
	function cmx_emails_settings_url(): string {
		if (\defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')) {
			return \add_query_arg([
				'page' => (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG'),
				'tab'  => 'email',
				'sub'  => 'clients',
			], \admin_url('admin.php'));
		}

		return \admin_url('options-general.php');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_mailbox_url')) {
	function cmx_emails_mailbox_url(array $args = []): string {
		$base = [
			'page'      => \defined(__NAMESPACE__ . '\\CMX_EMAILS_PAGE_SLUG') ? (string) \constant(__NAMESPACE__ . '\\CMX_EMAILS_PAGE_SLUG') : 'cmx-emails-mailbox',
			'post_type' => \defined(__NAMESPACE__ . '\\CMX_EMAILS_CPT') ? (string) \constant(__NAMESPACE__ . '\\CMX_EMAILS_CPT') : 'emails',
		];
		return \add_query_arg(\array_merge($base, $args), \admin_url('edit.php'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_admin_list_url')) {
	function cmx_emails_admin_list_url(array $args = []): string {
		$base = [
			'post_type' => \defined(__NAMESPACE__ . '\\CMX_EMAILS_CPT') ? (string) \constant(__NAMESPACE__ . '\\CMX_EMAILS_CPT') : 'emails',
		];
		return \add_query_arg(\array_merge($base, $args), \admin_url('edit.php'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_folder_map')) {
	function cmx_emails_folder_map(): array {
		return [
			'inbox' => [
				'label' => 'Posteingang',
				'candidates' => ['INBOX'],
			],
			'sent' => [
				'label' => 'Gesendet',
				'candidates' => ['Sent', 'INBOX.Sent', 'INBOX/Sent', 'Sent Messages', 'Gesendet'],
			],
			'drafts' => [
				'label' => 'Entwürfe',
				'candidates' => ['Drafts', 'INBOX.Drafts', 'INBOX/Drafts', 'Entwuerfe', 'Draft'],
			],
			'spam' => [
				'label' => 'Spam',
				'candidates' => ['Spam', 'Junk', 'Junk E-Mail', 'Bulk Mail', 'INBOX.Spam', 'INBOX.Junk', 'INBOX/Junk', 'INBOX/Bulk'],
			],
			'archive' => [
				'label' => 'Archiv',
				'candidates' => ['Archive', 'INBOX.Archive', 'INBOX/Archive', 'Archives', 'Archiv'],
			],
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_folder_label')) {
	function cmx_emails_folder_label(string $folder): string {
		$folder = \sanitize_key($folder);
		$map = cmx_emails_folder_map();
		return (string) ($map[$folder]['label'] ?? 'Posteingang');
	}
}
