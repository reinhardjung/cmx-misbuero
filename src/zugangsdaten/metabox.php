<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_ZUGANGSDATEN_NOTES_META')) {
	\define(__NAMESPACE__ . '\\CMX_ZUGANGSDATEN_NOTES_META', '_cmx_zugangsdaten_notizen');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ZUGANGSDATEN_LINKS_META')) {
	\define(__NAMESPACE__ . '\\CMX_ZUGANGSDATEN_LINKS_META', '_cmx_zugangsdaten_verknuepfungen');
}

\add_action('wp_ajax_cmx_zugangsdaten_signature_image_proxy', function (): void {
	if (!\current_user_can('edit_posts')) {
		\status_header(403);
		\wp_die('Keine Berechtigung.');
	}
	\check_ajax_referer('cmx_zugangsdaten_signature_image_proxy', 'nonce');

	$url = isset($_GET['url']) ? \trim((string) \wp_unslash($_GET['url'])) : '';
	$url = \esc_url_raw($url, ['http', 'https']);
	$parts = $url !== '' ? \wp_parse_url($url) : false;
	if ($url === '' || !\is_array($parts) || empty($parts['host'])) {
		\status_header(400);
		\wp_die('Ungültige Bild-URL.');
	}

	$scheme = \strtolower((string) ($parts['scheme'] ?? 'https'));
	$referer = $scheme . '://' . (string) $parts['host'] . '/';
	$response = \wp_safe_remote_get($url, [
		'timeout'             => 15,
		'redirection'         => 3,
		'limit_response_size' => 5 * MB_IN_BYTES,
		'headers'             => [
			'Accept'     => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
			'Referer'    => $referer,
			'User-Agent' => 'Mozilla/5.0 (compatible; CMX-Signature-Preview/1.0; +' . \home_url('/') . ')',
		],
	]);
	if (\is_wp_error($response) || (int) \wp_remote_retrieve_response_code($response) !== 200) {
		\status_header(502);
		\wp_die('Bild konnte nicht geladen werden.');
	}

	$body = (string) \wp_remote_retrieve_body($response);
	$content_type = \strtolower(\trim((string) \wp_remote_retrieve_header($response, 'content-type')));
	$content_type = \trim((string) \strtok($content_type, ';'));
	$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/apng', 'image/bmp', 'image/x-icon', 'image/vnd.microsoft.icon'];
	if ($body === '' || !\in_array($content_type, $allowed_types, true)) {
		\status_header(415);
		\wp_die('Nicht unterstütztes Bildformat.');
	}

	\header('Content-Type: ' . $content_type);
	\header('Content-Length: ' . (string) \strlen($body));
	\header('Cache-Control: private, max-age=3600');
	\header('X-Content-Type-Options: nosniff');
	echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Validierte Binärdaten.
	\wp_die();
});

\add_filter('enter_title_here', function (string $placeholder, \WP_Post $post): string {
	return (string) $post->post_type === CMX_ZUGANGSDATEN_CPT ? 'Bezeichnung' : $placeholder;
}, 10, 2);

\add_filter('wp_insert_post_data', function (array $data, array $postarr): array {
	if (
		(string) ($data['post_type'] ?? '') !== CMX_ZUGANGSDATEN_CPT
		|| \trim((string) ($data['post_title'] ?? '')) !== ''
	) {
		return $data;
	}

	$category = \sanitize_key((string) \wp_unslash($_POST['cmx_zugangsdaten_category'] ?? ''));
	$groups = cmx_zugangsdaten_field_groups();
	$fields = (array) ($groups[$category] ?? []);
	$title_field = $category === 'ssh-keys' ? 'public_key_name' : \array_key_first($fields);
	if ($title_field === null || !isset($fields[$title_field])) {
		return $data;
	}

	$posted_groups = isset($_POST['cmx_zugangsdaten_fields']) && \is_array($_POST['cmx_zugangsdaten_fields'])
		? (array) $_POST['cmx_zugangsdaten_fields']
		: [];
	$posted_fields = isset($posted_groups[$category]) && \is_array($posted_groups[$category])
		? (array) $posted_groups[$category]
		: [];
	$title = \sanitize_text_field((string) \wp_unslash($posted_fields[$title_field] ?? ''));
	if ($category === 'ssh-keys' && $title === '') {
		$public_key = \trim((string) \wp_unslash($posted_fields['public_key'] ?? ''));
		$title = \sanitize_text_field(cmx_ssh_public_key_comment($public_key));
	}
	if ($title !== '') {
		$data['post_title'] = $title;
	}

	return $data;
}, 10, 2);

function cmx_zugangsdaten_field_groups(): array {
	return [
		'server' => [
			'hostname'       => ['label' => 'Hostname', 'type' => 'text'],
			'fqdn'           => ['label' => 'FQDN', 'type' => 'text'],
			'username'       => ['label' => 'Username', 'type' => 'text'],
			'password'       => ['label' => 'Passwort', 'type' => 'password', 'sensitive' => true],
			'ssh_public_key' => ['label' => 'SSH-Key (public)', 'type' => 'ssh_public_key'],
			'ip_address'     => ['label' => 'IP-Adresse', 'type' => 'ip_lookup'],
			'server_id'      => ['label' => 'ID', 'type' => 'text'],
			'cpu'            => ['label' => 'CPU', 'type' => 'number', 'layout' => 'server-cpu'],
			'ram'            => ['label' => 'RAM', 'type' => 'number', 'layout' => 'server-ram'],
			'storage'        => ['label' => 'Festplatte', 'type' => 'number', 'layout' => 'server-storage'],
		],
		'ftp' => [
			'hostname'       => ['label' => 'Hostname', 'type' => 'text'],
			'url'            => ['label' => 'URL', 'type' => 'url'],
			'protocol'       => ['label' => 'Protokoll', 'type' => 'select', 'options' => ['ftp' => 'FTP', 'ftps' => 'FTPS', 'sftp' => 'SFTP', 'webdav' => 'WebDAV', 's3' => 'S3']],
			'username'       => ['label' => 'Benutzername', 'type' => 'text'],
			'password'       => ['label' => 'Passwort', 'type' => 'password', 'sensitive' => true],
			'ssh_public_key' => ['label' => 'SSH-Key', 'type' => 'ssh_public_key'],
		],
		'email' => [
			'email_address'       => ['label' => 'E-Mail-Adresse', 'type' => 'email'],
			'password'            => ['label' => 'Passwort', 'type' => 'password', 'sensitive' => true],
			'account_type'        => ['label' => 'Kontotyp', 'type' => 'select', 'options' => ['imap' => 'IMAP', 'pop3' => 'POP3', 'exchange' => 'Exchange']],
			'incoming_server'     => ['label' => 'Posteingangsserver', 'type' => 'text'],
			'incoming_port'       => ['label' => 'Port', 'type' => 'number'],
			'incoming_encryption' => ['label' => 'Verschlüsselung', 'type' => 'select', 'options' => ['ssl-tls' => 'SSL/TLS', 'starttls' => 'STARTTLS', 'none' => 'Keine']],
			'smtp_server'         => ['label' => 'SMTP-Server', 'type' => 'text'],
			'smtp_port'           => ['label' => 'SMTP-Port', 'type' => 'number'],
			'smtp_encryption'     => ['label' => 'SMTP-Verschlüsselung', 'type' => 'select', 'options' => ['ssl-tls' => 'SSL/TLS', 'starttls' => 'STARTTLS', 'none' => 'Keine']],
			'webmail_url'         => ['label' => 'Webmail-URL', 'type' => 'url'],
			'recovery_email'      => ['label' => 'Wiederherstellungs-E-Mail', 'type' => 'email'],
			'two_factor'          => ['label' => '2FA aktiviert', 'type' => 'toggle'],
			'signature'           => ['label' => 'Signature', 'type' => 'textarea', 'rows' => 8, 'layout' => 'email-signature', 'allow_html' => true, 'preserve_raw_html' => true],
		],
		'ssh-keys' => [
			'hostname'        => ['label' => 'Hostname', 'type' => 'text'],
			'servername'      => ['label' => 'FQDN', 'type' => 'text'],
			'username'        => ['label' => 'Username', 'type' => 'text', 'placeholder' => 'root / ubuntu'],
			'password'        => ['label' => 'Passwort', 'type' => 'password', 'sensitive' => true],
			'gesperrt'        => ['label' => 'Gesperrt', 'type' => 'toggle'],
			'ip_address'      => ['label' => 'IP-Adresse', 'type' => 'ip_lookup'],
			'public_key_name' => ['label' => 'Name', 'type' => 'text', 'layout' => 'public-key-name'],
			'public_key'      => ['label' => 'Public Key', 'type' => 'textarea', 'rows' => 3, 'layout' => 'public-key-value', 'key_file_kind' => 'public'],
			'private_key'     => ['label' => 'Private Key', 'type' => 'textarea', 'rows' => 8, 'sensitive' => true, 'layout' => 'private-key-value', 'key_file_kind' => 'private'],
		],
		'wlan' => [
			'ssid'            => ['label' => 'Name (SSID)', 'type' => 'text'],
			'password'        => ['label' => 'Passwort', 'type' => 'password', 'sensitive' => true],
			'encryption_type' => ['label' => 'Verschlüsselung', 'type' => 'select', 'options' => [
				'wpa3'      => 'WPA3',
				'wpa2-wpa3' => 'WPA2/WPA3 (Mixed)',
				'wpa2'      => 'WPA2',
				'wpa-wpa2'  => 'WPA/WPA2 (Mixed)',
				'wpa'       => 'WPA',
				'wep'       => 'WEP',
				'none'      => 'Offen (keine Verschlüsselung)',
			]],
			'router'          => ['label' => 'Router', 'type' => 'text'],
			'network'         => ['label' => 'Netzwerk', 'type' => 'text'],
			'router_ip'       => ['label' => 'Router-IP', 'type' => 'text'],
			'web_interface'   => ['label' => 'Weboberfläche', 'type' => 'text'],
		],
		'passwoerter' => [
			'username' => ['label' => 'Benutzername', 'type' => 'text'],
			'email'    => ['label' => 'E-Mail', 'type' => 'email'],
			'password' => ['label' => 'Passwort', 'type' => 'password', 'sensitive' => true],
			'website'  => ['label' => 'Website', 'type' => 'text'],
		],
		'kreditkarten' => [
			'holder'      => ['label' => 'Inhaber', 'type' => 'text'],
			'iban'        => ['label' => 'IBAN', 'type' => 'text', 'sensitive' => true],
			'card_number' => ['label' => 'Kartennummer', 'type' => 'password', 'sensitive' => true],
			'valid_until' => ['label' => 'Gültig bis', 'type' => 'month'],
			'cvv'         => ['label' => 'CVV', 'type' => 'password', 'sensitive' => true],
			'website'     => ['label' => 'Website', 'type' => 'text'],
		],
		'notizen' => [
			'subject' => ['label' => 'Betreff', 'type' => 'text'],
		],
		'lizenzen' => [
			'username' => ['label' => 'Benutzername', 'type' => 'text'],
			'email'    => ['label' => 'E-Mail', 'type' => 'email'],
			'key'      => ['label' => 'Key', 'type' => 'password', 'sensitive' => true],
			'website'  => ['label' => 'Website', 'type' => 'text'],
		],
		'api-keys' => [
			'url'     => ['label' => 'URL', 'type' => 'text'],
			'access'  => ['label' => 'Access', 'type' => 'password', 'sensitive' => true],
			'secret'  => ['label' => 'Secret', 'type' => 'password', 'sensitive' => true],
			'website' => ['label' => 'Website', 'type' => 'text'],
		],
		'bash' => [
			'code' => ['label' => 'Code', 'type' => 'textarea', 'rows' => 18, 'code' => true, 'preserve_raw_html' => true],
		],
	];
}

function cmx_zugangsdaten_meta_key(string $field): string {
	return '_cmx_zugangsdaten_' . \sanitize_key($field);
}

function cmx_zugangsdaten_encryption_key(): string {
	return \hash('sha256', (string) \wp_salt('auth') . '|cmx-zugangsdaten-v1', true);
}

function cmx_zugangsdaten_encrypt(string $value): string {
	if ($value === '') {
		return '';
	}

	$key = cmx_zugangsdaten_encryption_key();
	try {
		if (\function_exists('sodium_crypto_secretbox') && \defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES')) {
			$nonce = \random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
			$cipher = \sodium_crypto_secretbox($value, $nonce, $key);
			return 'cmxenc:s:' . \base64_encode($nonce . $cipher);
		}
		if (\function_exists('openssl_encrypt')) {
			$iv = \random_bytes(12);
			$tag = '';
			$cipher = \openssl_encrypt($value, 'aes-256-gcm', $key, \OPENSSL_RAW_DATA, $iv, $tag);
			if (\is_string($cipher) && $cipher !== '' && $tag !== '') {
				return 'cmxenc:o:' . \base64_encode($iv . $tag . $cipher);
			}
		}
	} catch (\Throwable $e) {
		return '';
	}

	return '';
}

function cmx_zugangsdaten_decrypt(string $value): string {
	if ($value === '' || !\str_starts_with($value, 'cmxenc:')) {
		return $value;
	}

	$key = cmx_zugangsdaten_encryption_key();
	try {
		if (\str_starts_with($value, 'cmxenc:s:') && \function_exists('sodium_crypto_secretbox_open')) {
			$raw = \base64_decode(\substr($value, 9), true);
			if (!\is_string($raw) || \strlen($raw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
				return '';
			}
			$nonce = \substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
			$cipher = \substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
			$plain = \sodium_crypto_secretbox_open($cipher, $nonce, $key);
			return \is_string($plain) ? $plain : '';
		}
		if (\str_starts_with($value, 'cmxenc:o:') && \function_exists('openssl_decrypt')) {
			$raw = \base64_decode(\substr($value, 9), true);
			if (!\is_string($raw) || \strlen($raw) <= 28) {
				return '';
			}
			$iv = \substr($raw, 0, 12);
			$tag = \substr($raw, 12, 16);
			$cipher = \substr($raw, 28);
			$plain = \openssl_decrypt($cipher, 'aes-256-gcm', $key, \OPENSSL_RAW_DATA, $iv, $tag);
			return \is_string($plain) ? $plain : '';
		}
	} catch (\Throwable $e) {
		return '';
	}

	return '';
}

function cmx_zugangsdaten_meta_value(int $post_id, string $field, bool $sensitive = false): string {
	$value = (string) \get_post_meta($post_id, cmx_zugangsdaten_meta_key($field), true);
	return $sensitive ? cmx_zugangsdaten_decrypt($value) : $value;
}

function cmx_zugangsdaten_contacts(): array {
	$ids = \get_posts([
		'post_type'      => 'kontakte',
		'post_status'    => ['publish', 'private', 'draft', 'pending', 'future'],
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'fields'         => 'ids',
		'no_found_rows'  => true,
	]);
	return \array_values(\array_filter(\array_map('intval', (array) $ids)));
}

function cmx_zugangsdaten_entries(int $exclude_id = 0): array {
	$ids = \get_posts([
		'post_type'      => CMX_ZUGANGSDATEN_CPT,
		'post_status'    => ['publish', 'private', 'draft', 'pending', 'future'],
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'fields'         => 'ids',
		'post__not_in'   => $exclude_id > 0 ? [$exclude_id] : [],
		'no_found_rows'  => true,
	]);
	return \array_values(\array_filter(\array_map('intval', (array) $ids)));
}

\add_action('add_meta_boxes_' . CMX_ZUGANGSDATEN_CPT, function (): void {
	$group_url = \admin_url('edit-tags.php?taxonomy=' . \rawurlencode(CMX_ZUGANGSDATEN_GROUP_TAX) . '&post_type=' . \rawurlencode(CMX_ZUGANGSDATEN_CPT));
	$group_title = '<a class="cmx-zugangsdaten-metabox-title-link" href="' . \esc_url($group_url) . '" target="_blank" rel="noopener noreferrer" style="color:#1d508f;text-decoration:none;font-weight:700;font-size:14px;line-height:1.2;" onclick="event.stopPropagation();">Gruppe</a><span class="cmx-zugangsdaten-screen-option-label">Gruppe</span>';
	$provider_url = \admin_url('edit-tags.php?taxonomy=' . \rawurlencode(CMX_ZUGANGSDATEN_PROVIDER_TAX) . '&post_type=' . \rawurlencode(CMX_ZUGANGSDATEN_CPT));
	$provider_title = '<a class="cmx-zugangsdaten-metabox-title-link" href="' . \esc_url($provider_url) . '" target="_blank" rel="noopener noreferrer" style="color:#1d508f;text-decoration:none;font-weight:700;font-size:14px;line-height:1.2;" onclick="event.stopPropagation();">Provider</a><span class="cmx-zugangsdaten-screen-option-label">Provider</span>';
	$contacts_url = \admin_url('edit.php?post_type=kontakte');
	$contact_title = '<a class="cmx-zugangsdaten-metabox-title-link" href="' . \esc_url($contacts_url) . '" target="_blank" rel="noopener noreferrer" style="color:#1d508f;text-decoration:none;font-weight:700;font-size:14px;line-height:1.2;" onclick="event.stopPropagation();">Kontakte</a><span class="cmx-zugangsdaten-screen-option-label">Kontakte</span>';

	\add_meta_box(
		'cmx_zugangsdaten_details',
		'Zugangsdaten',
		__NAMESPACE__ . '\\cmx_zugangsdaten_render_metabox',
		CMX_ZUGANGSDATEN_CPT,
		'normal',
		'high'
	);
	\add_meta_box(
		'cmx_zugangsdaten_category',
		'Kategorie',
		__NAMESPACE__ . '\\cmx_zugangsdaten_render_category_metabox',
		CMX_ZUGANGSDATEN_CPT,
		'side',
		'high'
	);
	\add_meta_box(
		'cmx_zugangsdaten_group',
		$group_title,
		__NAMESPACE__ . '\\cmx_zugangsdaten_render_group_metabox',
		CMX_ZUGANGSDATEN_CPT,
		'side',
		'high'
	);
	\add_meta_box(
		'cmx_zugangsdaten_provider',
		$provider_title,
		__NAMESPACE__ . '\\cmx_zugangsdaten_render_provider_metabox',
		CMX_ZUGANGSDATEN_CPT,
		'side',
		'high'
	);
	\add_meta_box(
		'cmx_zugangsdaten_contact',
		$contact_title,
		__NAMESPACE__ . '\\cmx_zugangsdaten_render_contact_metabox',
		CMX_ZUGANGSDATEN_CPT,
		'side',
		'high'
	);
	\add_meta_box(
		'cmx_zugangsdaten_links',
		'Verknüpfte Einträge',
		__NAMESPACE__ . '\\cmx_zugangsdaten_render_links_metabox',
		CMX_ZUGANGSDATEN_CPT,
		'side',
		'default'
	);
});

\add_action('admin_head-post.php', __NAMESPACE__ . '\\cmx_zugangsdaten_metabox_title_styles');
\add_action('admin_head-post-new.php', __NAMESPACE__ . '\\cmx_zugangsdaten_metabox_title_styles');

function cmx_zugangsdaten_metabox_title_styles(): void {
	$screen = \get_current_screen();
	if (!$screen || (string) $screen->post_type !== CMX_ZUGANGSDATEN_CPT) {
		return;
	}
	echo '<style>
		.postbox .postbox-header .cmx-zugangsdaten-screen-option-label{display:none}
		#screen-options-wrap .cmx-zugangsdaten-metabox-title-link{display:none}
		#screen-options-wrap .cmx-zugangsdaten-screen-option-label{display:inline}
	</style>';
}

\add_action('add_meta_boxes_' . CMX_ZUGANGSDATEN_CPT, function (): void {
	global $wp_meta_boxes;

	foreach (['side', 'normal', 'advanced'] as $context) {
		foreach (['high', 'core', 'default', 'low'] as $priority) {
			if (!isset($wp_meta_boxes[CMX_ZUGANGSDATEN_CPT][$context][$priority]['cmx_dokumente_box']['title'])) {
				continue;
			}
			$title = (string) $wp_meta_boxes[CMX_ZUGANGSDATEN_CPT][$context][$priority]['cmx_dokumente_box']['title'];
			if (!\str_contains($title, 'cmx-zugangsdaten-screen-option-label')) {
				$wp_meta_boxes[CMX_ZUGANGSDATEN_CPT][$context][$priority]['cmx_dokumente_box']['title']
					= $title . '<span class="cmx-zugangsdaten-screen-option-label">Dokumente</span>';
			}
			return;
		}
	}
}, 100);

\add_action('add_meta_boxes_' . CMX_ZUGANGSDATEN_CPT, function (): void {
	\remove_meta_box(CMX_ZUGANGSDATEN_CATEGORY_TAX . 'div', CMX_ZUGANGSDATEN_CPT, 'side');
	\remove_meta_box('tagsdiv-' . CMX_ZUGANGSDATEN_ISSUER_TAX, CMX_ZUGANGSDATEN_CPT, 'side');
	\remove_meta_box(CMX_ZUGANGSDATEN_GROUP_TAX . 'div', CMX_ZUGANGSDATEN_CPT, 'side');
	\remove_meta_box('tagsdiv-' . CMX_ZUGANGSDATEN_PROVIDER_TAX, CMX_ZUGANGSDATEN_CPT, 'side');
}, 100);

\add_filter('get_user_option_meta-box-order_' . CMX_ZUGANGSDATEN_CPT, function ($value) {
	$order = \is_array($value) ? $value : [];
	$current_side = isset($order['side'])
		? \array_values(\array_filter(\array_map('trim', \explode(',', (string) $order['side']))))
		: [];
	$required_side = [
		'cmx_savebox',
		'cmx_zugangsdaten_category',
		'cmx_zugangsdaten_group',
		'cmx_zugangsdaten_provider',
		'cmx_zugangsdaten_links',
		'cmx_zugangsdaten_contact',
		'cmx_dokumente_box',
		'cmx_li_box_zugangsdaten',
	];
	$remaining_side = \array_values(\array_diff($current_side, $required_side));
	$order['side'] = \implode(',', \array_merge($required_side, $remaining_side));
	foreach ($order as $context => $box_ids) {
		if ($context === 'side') {
			continue;
		}
		$context_ids = \array_values(\array_filter(\array_map('trim', \explode(',', (string) $box_ids))));
		$order[$context] = \implode(',', \array_diff($context_ids, $required_side));
	}
	return $order;
});

function cmx_zugangsdaten_render_field(\WP_Post $post, string $category, string $field, array $config): void {
	$label = (string) ($config['label'] ?? $field);
	$type = (string) ($config['type'] ?? 'text');
	$sensitive = !empty($config['sensitive']);
	$key_file_kind = \sanitize_key((string) ($config['key_file_kind'] ?? ''));
	$name = 'cmx_zugangsdaten_fields[' . $category . '][' . $field . ']';
	$id = 'cmx-zugangsdaten-' . $category . '-' . $field;

	$layout = \sanitize_html_class((string) ($config['layout'] ?? ''));
	echo '<div class="cmx-zugangsdaten-field' . ($layout !== '' ? ' cmx-zugangsdaten-field-' . \esc_attr($layout) : '') . '">';
	if ($type === 'ssh_public_key') {
		$settings_url = \admin_url(
			'admin.php?page=' . \rawurlencode(CMX_SETTINGS_SLUG) . '&tab=system&sub=security'
		) . '#cmx-admin-public-keys';
		echo '<label for="' . \esc_attr($id) . '"><a class="cmx-zugangsdaten-ssh-key-label-link" href="' . \esc_url($settings_url) . '" target="_blank" rel="noopener noreferrer">' . \esc_html($label) . '</a></label>';
	} elseif ($type === 'ip_lookup') {
		$ip_value = cmx_zugangsdaten_meta_value($post->ID, $field, false);
		$ip_url = $ip_value === '' ? '#' : cmx_infrastruktur_ip_lookup_url($ip_value);
		echo '<label for="' . \esc_attr($id) . '"><a id="' . \esc_attr($id . '-lookup') . '" class="cmx-zugangsdaten-ip-label-link" href="' . \esc_url($ip_url) . '" target="_blank" rel="noopener noreferrer"' . ($ip_value === '' ? ' aria-disabled="true"' : '') . '>' . \esc_html($label) . '</a></label>';
	} elseif ($type === 'url') {
		$url_value = cmx_zugangsdaten_meta_value($post->ID, $field, false);
		$url = $url_value === '' ? '#' : \esc_url($url_value, cmx_zugangsdaten_url_protocols());
		echo '<label for="' . \esc_attr($id) . '"><a id="' . \esc_attr($id . '-open') . '" class="cmx-zugangsdaten-url-label-link" href="' . \esc_url($url, cmx_zugangsdaten_url_protocols()) . '" target="_blank" rel="noopener noreferrer"' . ($url === '' || $url === '#' ? ' aria-disabled="true"' : '') . '>' . \esc_html($label) . '</a></label>';
	} elseif ($type === 'textarea' && $key_file_kind !== '') {
		echo '<label id="' . \esc_attr($id . '-label') . '" class="cmx-zugangsdaten-key-file-label" for="' . \esc_attr($id . '-file') . '" title="' . \esc_attr__('Key-Datei auswählen', 'cmx-misbuero') . '">' . \esc_html($label) . '</label>';
	} else {
		echo '<label for="' . \esc_attr($id) . '">' . \esc_html($label) . '</label>';
	}

	if ($type === 'ssh_public_key') {
		$keys = cmx_get_admin_public_keys();
		$current = \sanitize_key((string) \get_post_meta($post->ID, cmx_zugangsdaten_meta_key($field), true));
		$available_ids = [];
		foreach ($keys as $key) {
			$available_ids[] = cmx_ssh_public_key_id($key['key']);
		}
		if (!\in_array($current, $available_ids, true)) {
			$current = (string) ($available_ids[0] ?? '');
		}

		echo '<select id="' . \esc_attr($id) . '" name="' . \esc_attr($name) . '">';
		if (!$keys) {
			echo '<option value="">Keine SSH-Schlüssel hinterlegt</option>';
		} else {
			foreach ($keys as $key) {
				$key_id = cmx_ssh_public_key_id($key['key']);
				echo '<option value="' . \esc_attr($key_id) . '"' . \selected($current, $key_id, false) . '>' . \esc_html($key['name']) . '</option>';
			}
		}
		echo '</select>';
		echo '</div>';
		return;
	}

	if ($type === 'contact') {
		$current = (int) \get_post_meta($post->ID, cmx_zugangsdaten_meta_key($field), true);
		echo '<select id="' . \esc_attr($id) . '" name="' . \esc_attr($name) . '">';
		echo '<option value="">– Kontakt auswählen –</option>';
		foreach (cmx_zugangsdaten_contacts() as $contact_id) {
			$title = \trim((string) \get_the_title($contact_id));
			echo '<option value="' . \esc_attr((string) $contact_id) . '"' . \selected($current, $contact_id, false) . '>' . \esc_html($title !== '' ? $title : ('Kontakt #' . $contact_id)) . '</option>';
		}
		echo '</select>';
		echo '</div>';
		return;
	}

	if ($type === 'select') {
		$current = cmx_zugangsdaten_meta_value($post->ID, $field, $sensitive);
		$options = isset($config['options']) && \is_array($config['options']) ? $config['options'] : [];
		echo '<select id="' . \esc_attr($id) . '" name="' . \esc_attr($name) . '">';
		echo '<option value="">– Auswählen –</option>';
		foreach ($options as $option_value => $option_label) {
			echo '<option value="' . \esc_attr((string) $option_value) . '"' . \selected($current, (string) $option_value, false) . '>' . \esc_html((string) $option_label) . '</option>';
		}
		echo '</select>';
		echo '</div>';
		return;
	}

	if ($type === 'toggle') {
		$current_value = (string) \get_post_meta($post->ID, cmx_zugangsdaten_meta_key($field), true);
		$current = \in_array($current_value, ['1', 'yes'], true);
		echo '<div class="cmx-zugangsdaten-toggle-row">';
		echo '<label class="cmx-zugangsdaten-toggle" for="' . \esc_attr($id) . '">';
		echo '<input type="checkbox" id="' . \esc_attr($id) . '" name="' . \esc_attr($name) . '" value="1" role="switch"' . \checked($current, true, false) . '>';
		echo '<span class="cmx-zugangsdaten-toggle-ui" aria-hidden="true"></span>';
		echo '</label>';
		echo '<span class="cmx-zugangsdaten-toggle-state" data-cmx-toggle-state>' . ($current ? 'Ja' : 'Nein') . '</span>';
		if ($category === 'ssh-keys' && $field === 'gesperrt') {
			echo '<span class="cmx-zugangsdaten-ssh-command-menu">';
			echo '<button type="button" class="button-link cmx-zugangsdaten-ssh-config-button" data-cmx-ssh-config-open aria-label="SSH-Befehl kopieren" title="SSH-Befehl kopieren">';
			echo '<span class="dashicons dashicons-migrate" aria-hidden="true"></span>';
			echo '</button>';
			echo '<select class="cmx-zugangsdaten-ssh-config-select" data-cmx-ssh-config-os aria-label="Betriebssystem auswählen" hidden>';
			echo '<option value="">– Windows oder Mac –</option>';
			echo '<option value="windows">Windows</option>';
			echo '<option value="mac">Mac</option>';
			echo '</select>';
			echo '<span class="cmx-zugangsdaten-ssh-config-status" data-cmx-ssh-config-status aria-live="polite"></span>';
			echo '</span>';
		}
		echo '</div>';
		echo '</div>';
		return;
	}

	if ($type === 'issuer') {
		$assigned = \wp_get_object_terms($post->ID, CMX_ZUGANGSDATEN_ISSUER_TAX, ['fields' => 'ids']);
		$current = (!\is_wp_error($assigned) && !empty($assigned[0])) ? (int) $assigned[0] : 0;
		$terms = \get_terms([
			'taxonomy'   => CMX_ZUGANGSDATEN_ISSUER_TAX,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]);
		echo '<select id="' . \esc_attr($id) . '" name="' . \esc_attr($name) . '">';
		echo '<option value="">– Herausgeber auswählen –</option>';
		if (!\is_wp_error($terms)) {
			foreach ((array) $terms as $term) {
				echo '<option value="' . \esc_attr((string) $term->term_id) . '"' . \selected($current, (int) $term->term_id, false) . '>' . \esc_html((string) $term->name) . '</option>';
			}
		}
		echo '</select>';
		$url = \admin_url('edit-tags.php?taxonomy=' . \rawurlencode(CMX_ZUGANGSDATEN_ISSUER_TAX) . '&post_type=' . \rawurlencode(CMX_ZUGANGSDATEN_CPT));
		echo '<a class="cmx-zugangsdaten-manage-link" href="' . \esc_url($url) . '">Herausgeber verwalten</a>';
		echo '</div>';
		return;
	}

	$value = cmx_zugangsdaten_meta_value($post->ID, $field, $sensitive);
	$placeholder = (string) ($config['placeholder'] ?? '');
	if ($type === 'textarea') {
		$rows = \max(2, (int) ($config['rows'] ?? 4));
		$allow_html = !empty($config['allow_html']);
		$textarea_classes = [];
		if ($key_file_kind !== '') {
			$textarea_classes[] = 'cmx-zugangsdaten-key-dropzone';
		}
		if (!empty($config['code'])) {
			$textarea_classes[] = 'code';
		}
		if ($allow_html) {
			$textarea_classes[] = 'code';
			$textarea_classes[] = 'cmx-zugangsdaten-html-code';
		}
		$textarea_classes = \array_values(\array_unique($textarea_classes));
		$class_attr = $textarea_classes !== [] ? ' class="' . \esc_attr(\implode(' ', $textarea_classes)) . '"' : '';
		$key_attrs = $key_file_kind !== '' ? ' data-cmx-key-kind="' . \esc_attr($key_file_kind) . '" aria-labelledby="' . \esc_attr($id . '-label') . '"' : '';
		if ($allow_html) {
			echo '<div class="cmx-zugangsdaten-html-editor" data-cmx-html-editor>';
			echo '<div class="cmx-zugangsdaten-html-tabs" role="group" aria-label="Darstellung wählen">';
			echo '<button type="button" class="button is-active" data-cmx-html-mode="code" aria-pressed="true">Code</button>';
			echo '<button type="button" class="button" data-cmx-html-mode="html" aria-pressed="false">HTML</button>';
			echo '</div>';
		}
		echo '<textarea id="' . \esc_attr($id) . '" name="' . \esc_attr($name) . '" rows="' . \esc_attr((string) $rows) . '" spellcheck="false" autocapitalize="none" autocomplete="off"' . $class_attr . $key_attrs . '>' . \esc_textarea($value) . '</textarea>';
		if ($allow_html) {
			echo '<div class="cmx-zugangsdaten-html-visual" data-cmx-html-visual contenteditable="true" role="textbox" aria-multiline="true" aria-label="Signature HTML-Ansicht" hidden></div>';
			echo '</div>';
			echo '<script>(function(){';
			echo 'var textarea=document.getElementById(' . \wp_json_encode($id) . ');';
			echo 'var proxyUrl=' . \wp_json_encode((string) \admin_url('admin-ajax.php')) . ';';
			echo 'var proxyNonce=' . \wp_json_encode((string) \wp_create_nonce('cmx_zugangsdaten_signature_image_proxy')) . ';';
			echo 'var editor=textarea?textarea.closest("[data-cmx-html-editor]"):null;';
			echo 'var visual=editor?editor.querySelector("[data-cmx-html-visual]"):null;';
			echo 'var buttons=editor?Array.prototype.slice.call(editor.querySelectorAll("[data-cmx-html-mode]")):[];';
			echo 'if(!textarea||!editor||!visual||!buttons.length)return;var visualDirty=false;';
			echo 'function proxyImageUrl(url){return proxyUrl+"?action=cmx_zugangsdaten_signature_image_proxy&nonce="+encodeURIComponent(proxyNonce)+"&url="+encodeURIComponent(url);}';
			echo 'function safeHtml(value){var template=document.createElement("template");template.innerHTML=String(value||"");template.content.querySelectorAll("script,style,iframe,object,embed,link,meta,base").forEach(function(node){node.remove();});template.content.querySelectorAll("*").forEach(function(node){Array.prototype.slice.call(node.attributes||[]).forEach(function(attribute){var name=String(attribute.name||"").toLowerCase();var val=String(attribute.value||"").trim();if(name.indexOf("on")===0||((name==="href"||name==="src"||name==="xlink:href")&&/^javascript:/i.test(val)))node.removeAttribute(attribute.name);});});template.content.querySelectorAll("img").forEach(function(image){var original=String(image.getAttribute("src")||"").trim();image.removeAttribute("srcset");image.removeAttribute("sizes");if(/^https?:\\/\\//i.test(original)){image.setAttribute("data-cmx-original-src",original);image.setAttribute("src",proxyImageUrl(original));}});return template.innerHTML;}';
			echo 'function sourceHtml(){var clone=visual.cloneNode(true);clone.querySelectorAll("img[data-cmx-original-src]").forEach(function(image){image.setAttribute("src",String(image.getAttribute("data-cmx-original-src")||""));image.removeAttribute("data-cmx-original-src");});return clone.innerHTML;}';
			echo 'function setMode(mode){var htmlMode=mode==="html";if(htmlMode){visual.innerHTML=safeHtml(textarea.value);visualDirty=false;}else if(visualDirty){textarea.value=sourceHtml();}textarea.hidden=htmlMode;visual.hidden=!htmlMode;buttons.forEach(function(button){var active=button.getAttribute("data-cmx-html-mode")===mode;button.classList.toggle("is-active",active);button.setAttribute("aria-pressed",active?"true":"false");});(htmlMode?visual:textarea).focus();}';
			echo 'buttons.forEach(function(button){button.addEventListener("click",function(){setMode(String(button.getAttribute("data-cmx-html-mode")||"code"));});});';
			echo 'visual.addEventListener("input",function(){visualDirty=true;textarea.value=sourceHtml();});';
			echo 'var form=textarea.closest("form");if(form)form.addEventListener("submit",function(){if(!visual.hidden&&visualDirty)textarea.value=sourceHtml();});';
			echo '})();</script>';
		}
		if ($key_file_kind !== '') {
			$file_input_id = $id . '-file';
			echo '<input type="file" id="' . \esc_attr($file_input_id) . '" class="cmx-zugangsdaten-key-file-input" data-cmx-key-file-input hidden multiple>';
			echo '<span class="cmx-zugangsdaten-key-file-status" data-cmx-key-file-status role="status" aria-live="polite"></span>';
		}
		echo '</div>';
		return;
	}
	if ($type === 'month') {
		$display_value = '';
		$picker_year = (int) \wp_date('Y');
		$picker_month = 0;
		if (\preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $matches)) {
			$display_value = $matches[2] . '/' . \substr($matches[1], -2);
			$picker_year = (int) $matches[1];
			$picker_month = (int) $matches[2];
		}
		$picker_id = $id . '-picker';
		$button_id = $id . '-button';
		echo '<div class="cmx-zugangsdaten-month-wrap" style="position:relative;">';
		echo '<input type="text" id="' . \esc_attr($id) . '" name="' . \esc_attr($name) . '" value="' . \esc_attr($display_value) . '" placeholder="MM/JJ" inputmode="numeric" maxlength="5" pattern="(0[1-9]|1[0-2])/[0-9]{2}" autocomplete="off" style="padding-right:42px;">';
		echo '<button type="button" id="' . \esc_attr($button_id) . '" aria-label="Monat und Jahr auswählen" title="Monat und Jahr auswählen" style="position:absolute;right:1px;top:1px;width:38px;height:calc(100% - 2px);padding:0;border:0;background:transparent;cursor:pointer;"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span></button>';
		echo '<div id="' . \esc_attr($picker_id) . '" class="cmx-zugangsdaten-month-picker" hidden style="position:absolute;z-index:100001;top:calc(100% + 4px);right:0;width:250px;padding:12px;border:1px solid #8c8f94;border-radius:8px;background:#fff;box-shadow:0 4px 10px rgba(0,0,0,.14);">';
		echo '<label for="' . \esc_attr($picker_id . '-year') . '" style="display:block;margin-bottom:6px;font-weight:600;">Jahr</label>';
		echo '<select id="' . \esc_attr($picker_id . '-year') . '" style="width:100%;margin-bottom:10px;">';
		for ($year = 2000; $year <= 2099; $year++) {
			echo '<option value="' . \esc_attr((string) $year) . '"' . \selected($picker_year, $year, false) . '>' . \esc_html((string) $year) . '</option>';
		}
		echo '</select>';
		echo '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;">';
		foreach (['Jan.', 'Feb.', 'März', 'Apr.', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sept.', 'Okt.', 'Nov.', 'Dez.'] as $month_index => $month_label) {
			$month = $month_index + 1;
			$selected_style = $picker_month === $month ? 'background:#2271b1;color:#fff;' : 'background:#fff;color:#1d2327;';
			echo '<button type="button" class="cmx-zugangsdaten-month-option" data-month="' . \esc_attr((string) $month) . '" style="padding:7px 3px;border:1px solid #c3c4c7;border-radius:5px;' . \esc_attr($selected_style) . 'cursor:pointer;">' . \esc_html($month_label) . '</button>';
		}
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '<script>(function(){';
		echo 'var input=document.getElementById(' . \wp_json_encode($id) . ');';
		echo 'var picker=document.getElementById(' . \wp_json_encode($picker_id) . ');';
		echo 'var button=document.getElementById(' . \wp_json_encode($button_id) . ');';
		echo 'var yearSelect=document.getElementById(' . \wp_json_encode($picker_id . '-year') . ');';
		echo 'if(!input||!picker||!button||!yearSelect)return;';
		echo 'var postbox=picker.closest(".postbox");';
		echo 'function setPickerOpen(open){picker.hidden=!open;if(postbox)postbox.classList.toggle("cmx-zugangsdaten-month-picker-open",open);}';
		echo 'function formatManual(){';
		echo 'var raw=String(input.value||"").trim();';
		echo 'if(raw==="")return;';
		echo 'var match=raw.match(/^(\\d{1,2})[\\.\\/\\-](\\d{2}|\\d{4})$/);';
		echo 'if(!match){var digits=raw.replace(/\\D/g,"");if(digits.length===4){match=["",digits.slice(0,2),digits.slice(2)];}}';
		echo 'if(!match)return;';
		echo 'var month=Number(match[1]);if(month<1||month>12)return;';
		echo 'var year=String(match[2]).slice(-2);';
		echo 'input.value=String(month).padStart(2,"0")+"/"+year.padStart(2,"0");';
		echo 'yearSelect.value="20"+year;';
		echo '}';
		echo 'button.addEventListener("click",function(){';
		echo 'formatManual();';
		echo 'setPickerOpen(picker.hidden);';
		echo 'if(!picker.hidden)yearSelect.focus();';
		echo '});';
		echo 'picker.addEventListener("click",function(event){';
		echo 'var monthButton=event.target.closest(".cmx-zugangsdaten-month-option");';
		echo 'if(!monthButton)return;';
		echo 'var month=Number(monthButton.getAttribute("data-month")||0);';
		echo 'var selectedYear=String(yearSelect.value||"");';
		echo 'if(month<1||month>12||!/^[0-9]{4}$/.test(selectedYear))return;';
		echo 'input.value=String(month).padStart(2,"0")+"/"+selectedYear.slice(-2);';
		echo 'setPickerOpen(false);';
		echo '});';
		echo 'document.addEventListener("click",function(event){if(event.target!==button&&!button.contains(event.target)&&!picker.contains(event.target))setPickerOpen(false);});';
		echo 'document.addEventListener("keydown",function(event){if(event.key==="Escape")setPickerOpen(false);});';
		echo 'input.addEventListener("blur",formatManual);';
		echo 'input.addEventListener("change",formatManual);';
		echo '})();</script>';
		echo '</div>';
		return;
	}
	$input_type = \in_array($type, ['password', 'email', 'month', 'number', 'url'], true) ? $type : 'text';
	$autocomplete = $type === 'password' ? ' autocomplete="new-password"' : ' autocomplete="off"';
	if ($type === 'password') {
		echo '<span class="cmx-zugangsdaten-password-wrap">';
		echo '<input type="password" class="cmx-zugangsdaten-password-input" id="' . \esc_attr($id) . '" name="' . \esc_attr($name) . '" value="' . \esc_attr($value) . '"' . $autocomplete . '>';
		echo '<button type="button" class="button-link cmx-zugangsdaten-password-toggle" data-field-label="' . \esc_attr($label) . '" aria-label="' . \esc_attr($label) . ' anzeigen" aria-pressed="false" title="' . \esc_attr($label) . ' anzeigen">';
		echo '<span class="cmx-zugangsdaten-password-icon is-show" aria-hidden="true"></span>';
		echo '</button>';
		echo '</span>';
	} else {
		echo '<input type="' . \esc_attr($input_type) . '" id="' . \esc_attr($id) . '" name="' . \esc_attr($name) . '" value="' . \esc_attr($value) . '" placeholder="' . \esc_attr($placeholder) . '"' . $autocomplete . '>';
	}
	if ($type === 'ip_lookup') {
		$ip_lookup_base_url = cmx_infrastruktur_ip_lookup_url();
		echo '<script>(function(){';
		echo 'var input=document.getElementById(' . \wp_json_encode($id) . ');';
		echo 'var link=document.getElementById(' . \wp_json_encode($id . '-lookup') . ');';
		echo 'var base=' . \wp_json_encode($ip_lookup_base_url) . ';';
		echo 'if(!input||!link)return;';
		echo 'function effectiveIp(){return String(input.value||"").trim()||String(input.placeholder||"").trim();}';
		echo 'function sync(){var ip=effectiveIp();link.href=ip===""?"#":base+"&ip="+encodeURIComponent(ip);link.setAttribute("aria-disabled",ip===""?"true":"false");}';
		echo 'link.addEventListener("click",function(event){if(effectiveIp()==="")event.preventDefault();});';
		echo 'input.addEventListener("input",sync);sync();';
		echo 'if(String(input.placeholder||"").trim()===""){window.cmxPublicIpPromise=window.cmxPublicIpPromise||fetch("https://api.ipify.org?format=json",{cache:"no-store"}).then(function(response){return response.ok?response.json():null;}).then(function(data){return data&&typeof data.ip==="string"?data.ip.trim():"";}).catch(function(){return "";});window.cmxPublicIpPromise.then(function(ip){if(ip!==""){input.placeholder=ip;sync();}});}';
		echo '})();</script>';
	}
	if ($type === 'url') {
		echo '<script>(function(){';
		echo 'var input=document.getElementById(' . \wp_json_encode($id) . ');';
		echo 'var link=document.getElementById(' . \wp_json_encode($id . '-open') . ');';
		echo 'if(!input||!link)return;';
		echo 'function effectiveUrl(){var value=String(input.value||"").trim();return /^(https?|ftp|ftps|sftp|webdav|webdavs|s3):\\/\\//i.test(value)?value:"";}';
		echo 'function sync(){var url=effectiveUrl();link.href=url||"#";link.setAttribute("aria-disabled",url===""?"true":"false");}';
		echo 'link.addEventListener("click",function(event){if(effectiveUrl()==="")event.preventDefault();});';
		echo 'input.addEventListener("input",sync);sync();';
		echo '})();</script>';
	}
	echo '</div>';
}

function cmx_zugangsdaten_render_category_metabox(\WP_Post $post): void {
	$current_category = cmx_zugangsdaten_category_slug($post->ID);
	$assigned = \wp_get_object_terms($post->ID, CMX_ZUGANGSDATEN_ISSUER_TAX, ['fields' => 'ids']);
	$current_issuer = (!\is_wp_error($assigned) && !empty($assigned[0])) ? (int) $assigned[0] : 0;
	$issuer_terms = \get_terms([
		'taxonomy'   => CMX_ZUGANGSDATEN_ISSUER_TAX,
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	]);
	$issuer_url = \admin_url('edit-tags.php?taxonomy=' . \rawurlencode(CMX_ZUGANGSDATEN_ISSUER_TAX) . '&post_type=' . \rawurlencode(CMX_ZUGANGSDATEN_CPT));

	echo '<label class="screen-reader-text" for="cmx-zugangsdaten-category">Kategorie</label>';
	echo '<select id="cmx-zugangsdaten-category" name="cmx_zugangsdaten_category" required style="width:100%;">';
	echo '<option value="">– Kategorie auswählen –</option>';
	foreach (cmx_zugangsdaten_categories() as $slug => $label) {
		echo '<option value="' . \esc_attr($slug) . '"' . \selected($current_category, $slug, false) . '>' . \esc_html($label) . '</option>';
	}
	echo '</select>';

	echo '<div id="cmx-zugangsdaten-issuer-wrap" style="margin-top:12px;"' . ($current_category === 'kreditkarten' ? '' : ' hidden') . '>';
	echo '<label for="cmx-zugangsdaten-issuer" style="display:block;margin-bottom:6px;font-weight:600;"><a class="cmx-zugangsdaten-issuer-label-link" href="' . \esc_url($issuer_url) . '" target="_blank" rel="noopener noreferrer">Herausgeber</a></label>';
	echo '<select id="cmx-zugangsdaten-issuer" name="cmx_zugangsdaten_fields[kreditkarten][issuer]" style="width:100%;">';
	echo '<option value="">– Herausgeber auswählen –</option>';
	if (!\is_wp_error($issuer_terms)) {
		foreach ((array) $issuer_terms as $term) {
			echo '<option value="' . \esc_attr((string) $term->term_id) . '"' . \selected($current_issuer, (int) $term->term_id, false) . '>' . \esc_html((string) $term->name) . '</option>';
		}
	}
	echo '</select>';
	echo '</div>';
}

function cmx_zugangsdaten_render_group_metabox(\WP_Post $post): void {
	$assigned = \wp_get_object_terms($post->ID, CMX_ZUGANGSDATEN_GROUP_TAX, ['fields' => 'ids']);
	$current = (!\is_wp_error($assigned) && !empty($assigned[0])) ? (int) $assigned[0] : 0;
	$terms = \get_terms([
		'taxonomy'   => CMX_ZUGANGSDATEN_GROUP_TAX,
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	]);

	echo '<label class="screen-reader-text" for="cmx-zugangsdaten-group">Gruppe</label>';
	echo '<select id="cmx-zugangsdaten-group" name="cmx_zugangsdaten_group" style="width:100%;">';
	echo '<option value="">– Gruppe auswählen –</option>';
	if (!\is_wp_error($terms)) {
		foreach ((array) $terms as $term) {
			echo '<option value="' . \esc_attr((string) $term->term_id) . '"' . \selected($current, (int) $term->term_id, false) . '>' . \esc_html((string) $term->name) . '</option>';
		}
	}
	echo '</select>';
}

function cmx_zugangsdaten_render_provider_metabox(\WP_Post $post): void {
	$current_category = cmx_zugangsdaten_category_slug((int) $post->ID);
	$assigned = \wp_get_object_terms($post->ID, CMX_ZUGANGSDATEN_PROVIDER_TAX, ['fields' => 'ids']);
	$current = (!\is_wp_error($assigned) && !empty($assigned[0])) ? (int) $assigned[0] : 0;
	$terms = \get_terms([
		'taxonomy'   => CMX_ZUGANGSDATEN_PROVIDER_TAX,
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	]);

	echo '<div data-cmx-provider-fields' . (\in_array($current_category, cmx_zugangsdaten_provider_categories(), true) ? '' : ' hidden') . '>';
	echo '<label class="screen-reader-text" for="cmx-zugangsdaten-provider">Provider</label>';
	echo '<select id="cmx-zugangsdaten-provider" name="cmx_zugangsdaten_provider" style="width:100%;">';
	echo '<option value="">– Provider auswählen –</option>';
	if (!\is_wp_error($terms)) {
		foreach ((array) $terms as $term) {
			echo '<option value="' . \esc_attr((string) $term->term_id) . '"' . \selected($current, (int) $term->term_id, false) . '>' . \esc_html((string) $term->name) . '</option>';
		}
	}
	echo '</select>';
	echo '</div>';
}

function cmx_zugangsdaten_render_contact_metabox(\WP_Post $post): void {
	$current = \get_post_meta($post->ID, cmx_zugangsdaten_meta_key('contact_id'), true);
	$current_ids = \array_values(\array_unique(\array_filter(\array_map('intval', (array) $current))));
	$items = [];
	foreach (cmx_zugangsdaten_contacts() as $contact_id) {
		$title = \trim((string) \get_the_title($contact_id));
		$items[] = [
			'id'    => $contact_id,
			'label' => $title !== '' ? $title : ('Kontakt #' . $contact_id),
			'url'   => (string) \get_edit_post_link($contact_id, 'raw'),
		];
	}

	echo '<div id="cmx-zugangsdaten-contact-picker" class="cmx-zugangsdaten-contact-picker">';
	echo '<label class="screen-reader-text" for="cmx-zugangsdaten-contact-search">Kontakt suchen</label>';
	echo '<div class="cmx-zugangsdaten-contact-search-wrap">';
	echo '<input type="search" id="cmx-zugangsdaten-contact-search" placeholder="Kontakt suchen …" autocomplete="off">';
	echo '<div class="cmx-zugangsdaten-contact-results" role="listbox" hidden></div>';
	echo '</div>';
	echo '<ul class="cmx-zugangsdaten-contact-selected">';
	foreach ($items as $item) {
		if (!\in_array((int) $item['id'], $current_ids, true)) {
			continue;
		}
		echo '<li data-id="' . \esc_attr((string) $item['id']) . '">';
		echo '<a href="' . \esc_url((string) $item['url']) . '">' . \esc_html((string) $item['label']) . '</a>';
		echo '<input type="hidden" name="cmx_zugangsdaten_contact_ids[]" value="' . \esc_attr((string) $item['id']) . '">';
		echo '<button type="button" class="button-link-delete cmx-zugangsdaten-contact-remove" aria-label="Kontaktzuordnung entfernen" title="Kontaktzuordnung entfernen"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>';
		echo '</li>';
	}
	echo '</ul>';
	echo '</div>';

	echo '<style>
		#cmx_zugangsdaten_contact.cmx-zugangsdaten-contact-open{position:relative;z-index:1000;overflow:visible}
		.cmx-zugangsdaten-contact-search-wrap{position:relative}
		#cmx-zugangsdaten-contact-search{width:100%;border-radius:8px}
		.cmx-zugangsdaten-contact-results{position:absolute;z-index:1001;top:calc(100% + 4px);left:0;right:0;max-height:220px;overflow-y:auto;background:#fff;border:1px solid #8c8f94;border-radius:8px;box-shadow:0 4px 10px rgba(0,0,0,.12)}
		.cmx-zugangsdaten-contact-results[hidden]{display:none}
		.cmx-zugangsdaten-contact-result{display:block;width:100%;padding:8px 10px;border:0;border-bottom:1px solid #f0f0f1;background:#fff;color:#1d2327;text-align:left;cursor:pointer}
		.cmx-zugangsdaten-contact-result:last-child{border-bottom:0}
		.cmx-zugangsdaten-contact-result:hover,
		.cmx-zugangsdaten-contact-result:focus,
		.cmx-zugangsdaten-contact-result.is-active{background:#f0f6fc;color:#135e96;outline:0}
		.cmx-zugangsdaten-contact-empty{padding:8px 10px;color:#646970}
		.cmx-zugangsdaten-contact-selected{display:flex;flex-direction:column;gap:6px;margin:10px 0 0;padding:0;list-style:none}
		.cmx-zugangsdaten-contact-selected li{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:7px 8px;border:1px solid #dcdcde;border-radius:8px;background:#fff}
		.cmx-zugangsdaten-contact-selected a{min-width:0;overflow-wrap:anywhere}
		.cmx-zugangsdaten-contact-remove{display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;flex:0 0 36px;width:36px;height:36px;padding:0;border:1px solid #c3c4c7!important;border-radius:6px!important;background:#fff!important;box-shadow:none!important;color:#d63638!important;line-height:1;text-decoration:none!important}
		.cmx-zugangsdaten-contact-remove:hover,
		.cmx-zugangsdaten-contact-remove:focus{border-color:#8c8f94!important;background:#f6f7f7!important;color:#b32d2e!important;outline:0}
	</style>';

	echo '<script>
		document.addEventListener("DOMContentLoaded",function(){
			var root=document.getElementById("cmx-zugangsdaten-contact-picker");
			if(!root)return;
			var search=root.querySelector("#cmx-zugangsdaten-contact-search");
			var results=root.querySelector(".cmx-zugangsdaten-contact-results");
			var selected=root.querySelector(".cmx-zugangsdaten-contact-selected");
			var metabox=root.closest(".postbox");
			var items=' . \wp_json_encode($items) . ';
			var activeIndex=-1;
			if(!search||!results||!selected||!Array.isArray(items))return;

			function isSelected(id){
				return !!selected.querySelector("li[data-id=\""+String(id)+"\"]");
			}
			function resultButtons(){
				return Array.prototype.slice.call(results.querySelectorAll(".cmx-zugangsdaten-contact-result"));
			}
			function setActiveIndex(index){
				var buttons=resultButtons();
				if(!buttons.length){
					activeIndex=-1;
					return;
				}
				if(index<0)index=buttons.length-1;
				if(index>=buttons.length)index=0;
				activeIndex=index;
				buttons.forEach(function(button,buttonIndex){
					button.classList.toggle("is-active",buttonIndex===activeIndex);
				});
				buttons[activeIndex].scrollIntoView({block:"nearest"});
			}
			function closeResults(){
				results.hidden=true;
				activeIndex=-1;
				if(metabox)metabox.classList.remove("cmx-zugangsdaten-contact-open");
			}
			function renderResults(){
				activeIndex=-1;
				var query=String(search.value||"").trim().toLocaleLowerCase();
				var matches=items.filter(function(item){
					return !isSelected(item.id)&&(query===""||String(item.label||"").toLocaleLowerCase().includes(query));
				}).slice(0,query===""?5:20);
				results.innerHTML="";
				if(!matches.length){
					var empty=document.createElement("div");
					empty.className="cmx-zugangsdaten-contact-empty";
					empty.textContent=query===""?"Keine weiteren Kontakte vorhanden.":"Keine Kontakte gefunden.";
					results.appendChild(empty);
				}else{
					matches.forEach(function(item){
						var button=document.createElement("button");
						button.type="button";
						button.className="cmx-zugangsdaten-contact-result";
						button.setAttribute("data-id",String(item.id));
						button.textContent=String(item.label||"");
						results.appendChild(button);
					});
				}
				results.hidden=false;
				if(metabox)metabox.classList.add("cmx-zugangsdaten-contact-open");
			}
			function addSelected(item){
				if(!item||isSelected(item.id))return;
				var li=document.createElement("li");
				li.setAttribute("data-id",String(item.id));
				var link=document.createElement("a");
				link.href=String(item.url||"");
				link.textContent=String(item.label||"");
				var hidden=document.createElement("input");
				hidden.type="hidden";
				hidden.name="cmx_zugangsdaten_contact_ids[]";
				hidden.value=String(item.id);
				var remove=document.createElement("button");
				remove.type="button";
				remove.className="button-link-delete cmx-zugangsdaten-contact-remove";
				remove.setAttribute("aria-label","Kontaktzuordnung entfernen");
				remove.setAttribute("title","Kontaktzuordnung entfernen");
				remove.innerHTML="<span class=\"dashicons dashicons-trash\" aria-hidden=\"true\"></span>";
				li.appendChild(link);
				li.appendChild(hidden);
				li.appendChild(remove);
				selected.appendChild(li);
			}

			search.addEventListener("focus",renderResults);
			search.addEventListener("click",renderResults);
			search.addEventListener("input",renderResults);
			results.addEventListener("mousedown",function(event){
				event.preventDefault();
			});
			results.addEventListener("click",function(event){
				var button=event.target.closest(".cmx-zugangsdaten-contact-result");
				if(!button)return;
				var id=Number(button.getAttribute("data-id")||0);
				var item=items.find(function(candidate){return Number(candidate.id)===id;});
				addSelected(item);
				search.value="";
				closeResults();
			});
			results.addEventListener("mousemove",function(event){
				var button=event.target.closest(".cmx-zugangsdaten-contact-result");
				if(!button)return;
				var index=resultButtons().indexOf(button);
				if(index>=0)setActiveIndex(index);
			});
			selected.addEventListener("click",function(event){
				var button=event.target.closest(".cmx-zugangsdaten-contact-remove");
				if(!button)return;
				event.preventDefault();
				var row=button.closest("li[data-id]");
				if(row)row.remove();
			});
			document.addEventListener("click",function(event){
				if(!root.contains(event.target))closeResults();
			});
			search.addEventListener("keydown",function(event){
				if(event.key==="ArrowDown"||event.key==="ArrowUp"){
					event.preventDefault();
					if(results.hidden)renderResults();
					setActiveIndex(event.key==="ArrowDown"?activeIndex+1:activeIndex-1);
					return;
				}
				if(event.key==="Enter"&&activeIndex>=0){
					var buttons=resultButtons();
					if(buttons[activeIndex]){
						event.preventDefault();
						buttons[activeIndex].click();
					}
					return;
				}
				if(event.key==="Escape")closeResults();
			});
		});
	</script>';
}

function cmx_zugangsdaten_render_links_metabox(\WP_Post $post): void {
	$related = (array) \get_post_meta($post->ID, CMX_ZUGANGSDATEN_LINKS_META, true);
	$related = \array_values(\array_unique(\array_filter(\array_map('intval', $related))));
	$items = [];
	foreach (cmx_zugangsdaten_entries($post->ID) as $entry_id) {
		$title = \trim((string) \get_the_title($entry_id));
		$category_label = cmx_zugangsdaten_category_label($entry_id);
		$label = $title !== '' ? $title : ('Zugangsdaten #' . $entry_id);
		if ($category_label !== '') {
			$label .= ' — ' . $category_label;
		}
		$items[] = [
			'id'    => $entry_id,
			'label' => $label,
			'url'   => (string) \get_edit_post_link($entry_id, 'raw'),
		];
	}

	echo '<div id="cmx-zugangsdaten-links-picker" class="cmx-zugangsdaten-links-picker">';
	echo '<label class="screen-reader-text" for="cmx-zugangsdaten-links-search">Verknüpfte Einträge durchsuchen</label>';
	echo '<div class="cmx-zugangsdaten-links-search-wrap">';
	echo '<input type="search" id="cmx-zugangsdaten-links-search" placeholder="Einträge suchen …" autocomplete="off">';
	echo '<div class="cmx-zugangsdaten-links-results" role="listbox" hidden></div>';
	echo '</div>';
	echo '<ul class="cmx-zugangsdaten-links-selected">';
	foreach ($items as $item) {
		$entry_id = (int) $item['id'];
		if (!\in_array($entry_id, $related, true)) {
			continue;
		}
		echo '<li data-id="' . \esc_attr((string) $entry_id) . '">';
		echo '<a href="' . \esc_url((string) $item['url']) . '">' . \esc_html((string) $item['label']) . '</a>';
		echo '<input type="hidden" name="cmx_zugangsdaten_links[]" value="' . \esc_attr((string) $entry_id) . '">';
		echo '<button type="button" class="button-link-delete cmx-zugangsdaten-link-remove" aria-label="Verknüpfung entfernen" title="Verknüpfung entfernen"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>';
		echo '</li>';
	}
	echo '</ul>';
	echo '<p class="description">Verknüpfungen gelten gegenseitig.</p>';
	echo '</div>';

	echo '<style>
		#cmx_zugangsdaten_links.cmx-zugangsdaten-links-open{position:relative;z-index:1000;overflow:visible}
		.cmx-zugangsdaten-links-search-wrap{position:relative}
		#cmx-zugangsdaten-links-search{width:100%;border-radius:8px}
		.cmx-zugangsdaten-links-results{position:absolute;z-index:1001;top:calc(100% + 4px);left:0;right:0;max-height:220px;overflow-y:auto;background:#fff;border:1px solid #8c8f94;border-radius:8px;box-shadow:0 4px 10px rgba(0,0,0,.12)}
		.cmx-zugangsdaten-links-results[hidden]{display:none}
		.cmx-zugangsdaten-link-result{display:block;width:100%;padding:8px 10px;border:0;border-bottom:1px solid #f0f0f1;background:#fff;color:#1d2327;text-align:left;cursor:pointer}
		.cmx-zugangsdaten-link-result:last-child{border-bottom:0}
		.cmx-zugangsdaten-link-result:hover,
		.cmx-zugangsdaten-link-result:focus,
		.cmx-zugangsdaten-link-result.is-active{background:#f0f6fc;color:#135e96;outline:0}
		.cmx-zugangsdaten-links-empty{padding:8px 10px;color:#646970}
		.cmx-zugangsdaten-links-selected{display:flex;flex-direction:column;gap:6px;margin:10px 0 0;padding:0;list-style:none}
		.cmx-zugangsdaten-links-selected li{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:7px 8px;border:1px solid #dcdcde;border-radius:8px;background:#fff}
		.cmx-zugangsdaten-links-selected a{min-width:0;overflow-wrap:anywhere}
		.cmx-zugangsdaten-link-remove{display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;flex:0 0 36px;width:36px;height:36px;padding:0;border:1px solid #c3c4c7!important;border-radius:6px!important;background:#fff!important;box-shadow:none!important;color:#d63638!important;line-height:1;text-decoration:none!important}
		.cmx-zugangsdaten-link-remove:hover,
		.cmx-zugangsdaten-link-remove:focus{border-color:#8c8f94!important;background:#f6f7f7!important;color:#b32d2e!important;outline:0}
	</style>';

	echo '<script>
		document.addEventListener("DOMContentLoaded",function(){
			var root=document.getElementById("cmx-zugangsdaten-links-picker");
			if(!root)return;
			var search=root.querySelector("#cmx-zugangsdaten-links-search");
			var results=root.querySelector(".cmx-zugangsdaten-links-results");
			var selected=root.querySelector(".cmx-zugangsdaten-links-selected");
			var metabox=root.closest(".postbox");
			var items=' . \wp_json_encode($items) . ';
			var activeIndex=-1;
			if(!search||!results||!selected||!Array.isArray(items))return;

			function isSelected(id){
				return !!selected.querySelector("li[data-id=\""+String(id)+"\"]");
			}
			function resultButtons(){
				return Array.prototype.slice.call(results.querySelectorAll(".cmx-zugangsdaten-link-result"));
			}
			function setActiveIndex(index){
				var buttons=resultButtons();
				if(!buttons.length){
					activeIndex=-1;
					return;
				}
				if(index<0)index=buttons.length-1;
				if(index>=buttons.length)index=0;
				activeIndex=index;
				buttons.forEach(function(button,buttonIndex){
					button.classList.toggle("is-active",buttonIndex===activeIndex);
				});
				buttons[activeIndex].scrollIntoView({block:"nearest"});
			}
			function closeResults(){
				results.hidden=true;
				activeIndex=-1;
				if(metabox)metabox.classList.remove("cmx-zugangsdaten-links-open");
			}
			function renderResults(){
				activeIndex=-1;
				var query=String(search.value||"").trim().toLocaleLowerCase();
				var matches=items.filter(function(item){
					return !isSelected(item.id)&&(query===""||String(item.label||"").toLocaleLowerCase().includes(query));
				}).slice(0,query===""?5:20);
				results.innerHTML="";
				if(!matches.length){
					var empty=document.createElement("div");
					empty.className="cmx-zugangsdaten-links-empty";
					empty.textContent=query===""?"Keine weiteren Einträge vorhanden.":"Keine Einträge gefunden.";
					results.appendChild(empty);
				}else{
					matches.forEach(function(item){
						var button=document.createElement("button");
						button.type="button";
						button.className="cmx-zugangsdaten-link-result";
						button.setAttribute("data-id",String(item.id));
						button.textContent=String(item.label||"");
						results.appendChild(button);
					});
				}
				results.hidden=false;
				if(metabox)metabox.classList.add("cmx-zugangsdaten-links-open");
			}
			function addSelected(item){
				if(!item||isSelected(item.id))return;
				var li=document.createElement("li");
				li.setAttribute("data-id",String(item.id));
				var link=document.createElement("a");
				link.href=String(item.url||"");
				link.textContent=String(item.label||"");
				var hidden=document.createElement("input");
				hidden.type="hidden";
				hidden.name="cmx_zugangsdaten_links[]";
				hidden.value=String(item.id);
				var remove=document.createElement("button");
				remove.type="button";
				remove.className="button-link-delete cmx-zugangsdaten-link-remove";
				remove.setAttribute("aria-label","Verknüpfung entfernen");
				remove.setAttribute("title","Verknüpfung entfernen");
				remove.innerHTML="<span class=\"dashicons dashicons-trash\" aria-hidden=\"true\"></span>";
				li.appendChild(link);
				li.appendChild(hidden);
				li.appendChild(remove);
				selected.appendChild(li);
			}

			search.addEventListener("focus",renderResults);
			search.addEventListener("click",renderResults);
			search.addEventListener("input",renderResults);
			results.addEventListener("mousedown",function(event){
				event.preventDefault();
			});
			results.addEventListener("click",function(event){
				var button=event.target.closest(".cmx-zugangsdaten-link-result");
				if(!button)return;
				var id=Number(button.getAttribute("data-id")||0);
				var item=items.find(function(candidate){return Number(candidate.id)===id;});
				addSelected(item);
				search.value="";
				closeResults();
			});
			results.addEventListener("mousemove",function(event){
				var button=event.target.closest(".cmx-zugangsdaten-link-result");
				if(!button)return;
				var index=resultButtons().indexOf(button);
				if(index>=0)setActiveIndex(index);
			});
			selected.addEventListener("click",function(event){
				var button=event.target.closest(".cmx-zugangsdaten-link-remove");
				if(!button)return;
				event.preventDefault();
				var row=button.closest("li[data-id]");
				if(row)row.remove();
			});
			document.addEventListener("click",function(event){
				if(!root.contains(event.target))closeResults();
			});
			search.addEventListener("keydown",function(event){
				if(event.key==="ArrowDown"||event.key==="ArrowUp"){
					event.preventDefault();
					if(results.hidden)renderResults();
					setActiveIndex(event.key==="ArrowDown"?activeIndex+1:activeIndex-1);
					return;
				}
				if(event.key==="Enter"&&activeIndex>=0){
					var buttons=resultButtons();
					if(buttons[activeIndex]){
						event.preventDefault();
						buttons[activeIndex].click();
					}
					return;
				}
				if(event.key==="Escape")closeResults();
			});
		});
	</script>';
}

function cmx_zugangsdaten_render_metabox(\WP_Post $post): void {
	\wp_nonce_field('cmx_zugangsdaten_save', 'cmx_zugangsdaten_nonce');

	$current_category = cmx_zugangsdaten_category_slug($post->ID);
	$icon_show_url = \defined('CMX_PLUGIN_URL') ? (string) \constant('CMX_PLUGIN_URL') . 'assets/see.png' : '';
	$icon_hide_url = \defined('CMX_PLUGIN_URL') ? (string) \constant('CMX_PLUGIN_URL') . 'assets/hide.png' : '';
	$dark_blue = \function_exists(__NAMESPACE__ . '\\cmx_admin_global_color')
		? cmx_admin_global_color('dunkelblau', '#1D508F')
		: '#1D508F';

	if ($current_category === '') {
		echo '<script>(function(){var box=document.getElementById("cmx_zugangsdaten_details");if(box)box.style.display="none";})();</script>';
	}

	echo '<div class="cmx-zugangsdaten-form">';
	foreach (cmx_zugangsdaten_field_groups() as $category => $fields) {
		echo '<div class="cmx-zugangsdaten-category-fields" data-category="' . \esc_attr($category) . '"' . ($current_category === $category ? '' : ' hidden') . '>';
		foreach ($fields as $field => $config) {
			cmx_zugangsdaten_render_field($post, $category, (string) $field, (array) $config);
		}
		echo '</div>';
	}

	echo '</div>';
	echo '<style>
		.cmx-zugangsdaten-form{display:grid;gap:18px}
		.cmx-zugangsdaten-category-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
		.cmx-zugangsdaten-category-fields[data-category="server"]{grid-template-columns:repeat(7,minmax(0,1fr))}
		.cmx-zugangsdaten-category-fields[data-category="ftp"]{grid-template-columns:repeat(6,minmax(0,1fr))}
		.cmx-zugangsdaten-category-fields[data-category="email"]{grid-template-columns:repeat(5,minmax(0,1fr))}
		.cmx-zugangsdaten-category-fields[data-category="ssh-keys"]{grid-template-columns:repeat(6,minmax(0,1fr))}
		.cmx-zugangsdaten-category-fields[data-category="wlan"]{grid-template-columns:repeat(4,minmax(0,1fr))}
		.cmx-zugangsdaten-category-fields[data-category="api-keys"]{grid-template-columns:repeat(4,minmax(0,1fr))}
		.cmx-zugangsdaten-category-fields[data-category="passwoerter"]{grid-template-columns:repeat(4,minmax(0,1fr))}
		.cmx-zugangsdaten-category-fields[data-category="kreditkarten"]{grid-template-columns:repeat(6,minmax(0,1fr))}
		.cmx-zugangsdaten-category-fields[data-category="bash"]{grid-template-columns:minmax(0,1fr)}
		.cmx-zugangsdaten-category-fields[data-category="server"] .cmx-zugangsdaten-field-server-cpu{grid-column:span 2}
		.cmx-zugangsdaten-category-fields[data-category="server"] .cmx-zugangsdaten-field-server-ram{grid-column:span 2}
		.cmx-zugangsdaten-category-fields[data-category="server"] .cmx-zugangsdaten-field-server-storage{grid-column:span 3}
		.cmx-zugangsdaten-category-fields[data-category="email"] .cmx-zugangsdaten-field-email-signature{grid-column:1/-1}
		.cmx-zugangsdaten-category-fields[data-category="ssh-keys"] .cmx-zugangsdaten-field-public-key-value{grid-column:span 5}
		.cmx-zugangsdaten-category-fields[data-category="ssh-keys"] .cmx-zugangsdaten-field-private-key-value{grid-column:1/-1}
		.cmx-zugangsdaten-category-fields[hidden]{display:none}
		#poststuff .postbox.cmx-zugangsdaten-month-picker-open{position:relative;z-index:100000;overflow:visible}
		#poststuff .postbox.cmx-zugangsdaten-month-picker-open .inside{overflow:visible}
		.cmx-zugangsdaten-month-picker[hidden]{display:none!important}
		.cmx-zugangsdaten-field{display:flex;flex-direction:column;gap:6px}
		.cmx-zugangsdaten-field label{font-weight:600}
		.cmx-zugangsdaten-field input,
		.cmx-zugangsdaten-field select,
		.cmx-zugangsdaten-field textarea{width:100%;max-width:none;border-radius:8px}
		.cmx-zugangsdaten-html-editor{display:flex;flex-direction:column;gap:6px}
		.cmx-zugangsdaten-html-tabs{display:flex;align-items:center;gap:4px}
		.cmx-zugangsdaten-html-tabs .button{min-height:30px;line-height:28px}
		.cmx-zugangsdaten-html-tabs .button.is-active{border-color:#2271b1;background:#2271b1;color:#fff}
		.cmx-zugangsdaten-html-editor textarea[hidden],.cmx-zugangsdaten-html-visual[hidden]{display:none!important}
		.cmx-zugangsdaten-html-visual{box-sizing:border-box;min-height:190px;padding:10px 12px;overflow:auto;border:1px solid #8c8f94;border-radius:8px;background:#fff;color:#1d2327}
		.cmx-zugangsdaten-html-visual:focus{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1;outline:2px solid transparent}
		.cmx-zugangsdaten-key-dropzone{transition:border-color .15s ease,box-shadow .15s ease,background-color .15s ease}
		.cmx-zugangsdaten-key-dropzone.is-dragover{border-color:' . \esc_attr($dark_blue) . '!important;background:#f0f6fc;box-shadow:0 0 0 1px ' . \esc_attr($dark_blue) . '!important}
		#poststuff .cmx-zugangsdaten-field label.cmx-zugangsdaten-key-file-label{width:fit-content;color:' . \esc_attr($dark_blue) . '!important;text-decoration:none;cursor:pointer}
		#poststuff .cmx-zugangsdaten-field label.cmx-zugangsdaten-key-file-label:hover,
		#poststuff .cmx-zugangsdaten-field label.cmx-zugangsdaten-key-file-label:focus{color:' . \esc_attr($dark_blue) . '!important;text-decoration:underline;text-underline-offset:2px}
		.cmx-zugangsdaten-key-file-status{margin-top:2px;color:#646970;font-size:12px}
		.cmx-zugangsdaten-key-file-status:empty{display:none}
		.cmx-zugangsdaten-key-file-status.is-error{color:#b32d2e}
		.cmx-zugangsdaten-toggle-row{display:flex;align-items:center;gap:10px;min-height:40px}
		.cmx-zugangsdaten-field label.cmx-zugangsdaten-toggle{position:relative;display:inline-flex;align-items:center;flex:0 0 auto;width:auto;margin:0;cursor:pointer}
		.cmx-zugangsdaten-field .cmx-zugangsdaten-toggle input{position:absolute;width:1px;height:1px;overflow:hidden;opacity:0}
		.cmx-zugangsdaten-toggle-ui{position:relative;display:block;width:42px;height:24px;border-radius:999px;background:#a7aaad;transition:background .15s ease}
		.cmx-zugangsdaten-toggle-ui:after{position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);content:"";transition:transform .15s ease}
		.cmx-zugangsdaten-toggle input:checked+.cmx-zugangsdaten-toggle-ui{background:#2271b1}
		.cmx-zugangsdaten-toggle input:checked+.cmx-zugangsdaten-toggle-ui:after{transform:translateX(18px)}
		.cmx-zugangsdaten-toggle input:focus-visible+.cmx-zugangsdaten-toggle-ui{box-shadow:0 0 0 2px #fff,0 0 0 4px #2271b1}
		.cmx-zugangsdaten-toggle-state{font-weight:400}
		.cmx-zugangsdaten-ssh-command-menu{position:relative;display:inline-flex;align-items:center;margin-left:24px}
		.cmx-zugangsdaten-ssh-config-button{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;margin:0;padding:0;border:0!important;background:transparent!important;box-shadow:none!important;color:' . \esc_attr($dark_blue) . '!important;text-decoration:none!important}
		.cmx-zugangsdaten-ssh-config-button:hover,.cmx-zugangsdaten-ssh-config-button:focus,.cmx-zugangsdaten-ssh-config-button:active{border:0!important;background:transparent!important;box-shadow:none!important;color:' . \esc_attr($dark_blue) . '!important;text-decoration:none!important}
		.cmx-zugangsdaten-ssh-config-button .dashicons{width:22px;height:22px;font-size:22px}
		#poststuff .cmx-zugangsdaten-field .cmx-zugangsdaten-ssh-config-select{position:absolute;z-index:1001;top:calc(100% + 4px);left:0;width:190px!important;min-width:190px!important;max-width:190px!important;margin:0}
		#poststuff .cmx-zugangsdaten-field .cmx-zugangsdaten-ssh-config-select[hidden]{display:none!important}
		.cmx-zugangsdaten-ssh-config-status{position:absolute;top:calc(100% + 6px);left:0;width:max-content;color:#646970;font-size:12px;white-space:nowrap}
		.cmx-zugangsdaten-password-wrap{position:relative;display:flex;width:100%}
		.cmx-zugangsdaten-password-wrap .cmx-zugangsdaten-password-input{padding-right:62px}
		.cmx-zugangsdaten-password-toggle{
			position:absolute;
			right:4px;
			top:50%;
			transform:translateY(-50%);
			display:inline-flex;
			align-items:center;
			justify-content:center;
			width:52px;
			height:calc(100% - 4px);
			margin:0;
			padding:0;
			border:0;
			border-radius:5px;
			background:transparent;
			box-shadow:none;
			text-decoration:none
		}
		.cmx-zugangsdaten-password-toggle:hover,
		.cmx-zugangsdaten-password-toggle:focus{border:0;background:transparent;box-shadow:none;outline:none}
		.cmx-zugangsdaten-password-toggle:focus-visible{box-shadow:0 0 0 2px #2271b1}
		.cmx-zugangsdaten-password-icon{
			display:block;
			width:48px;
			height:32px;
			background-repeat:no-repeat;
			background-position:center;
			background-size:contain
		}
		.cmx-zugangsdaten-password-icon.is-show{background-image:url("' . \esc_url($icon_show_url) . '")}
		.cmx-zugangsdaten-password-icon.is-hide{background-image:url("' . \esc_url($icon_hide_url) . '")}
		.cmx-zugangsdaten-issuer-label-link,
		.cmx-zugangsdaten-issuer-label-link:hover,
		.cmx-zugangsdaten-issuer-label-link:focus{color:' . \esc_attr($dark_blue) . ';text-decoration:none}
		.cmx-zugangsdaten-ssh-key-label-link,
		.cmx-zugangsdaten-ssh-key-label-link:visited,
		.cmx-zugangsdaten-ssh-key-label-link:focus{color:' . \esc_attr($dark_blue) . ';text-decoration:none}
		.cmx-zugangsdaten-ssh-key-label-link:hover{color:' . \esc_attr($dark_blue) . ';text-decoration:underline}
		.cmx-zugangsdaten-ip-label-link,
		.cmx-zugangsdaten-ip-label-link:visited,
		.cmx-zugangsdaten-ip-label-link:focus{color:' . \esc_attr($dark_blue) . ';text-decoration:none}
		.cmx-zugangsdaten-ip-label-link:hover{color:' . \esc_attr($dark_blue) . ';text-decoration:underline;text-underline-offset:2px}
		.cmx-zugangsdaten-url-label-link,
		.cmx-zugangsdaten-url-label-link:visited,
		.cmx-zugangsdaten-url-label-link:focus{color:' . \esc_attr($dark_blue) . ';text-decoration:none}
		.cmx-zugangsdaten-url-label-link:hover{color:' . \esc_attr($dark_blue) . ';text-decoration:underline;text-underline-offset:2px}
		body.post-type-zugangsdaten #cmx_dokumente_box .postbox-header .hndle a,
		body.post-type-zugangsdaten #cmx_dokumente_box .postbox-header .hndle a:hover,
		body.post-type-zugangsdaten #cmx_dokumente_box .postbox-header .hndle a:focus{color:' . \esc_attr($dark_blue) . '}
		.cmx-zugangsdaten-manage-link{align-self:flex-start}
		@media(max-width:782px){.cmx-zugangsdaten-category-fields,.cmx-zugangsdaten-category-fields[data-category="server"],.cmx-zugangsdaten-category-fields[data-category="ftp"],.cmx-zugangsdaten-category-fields[data-category="email"],.cmx-zugangsdaten-category-fields[data-category="ssh-keys"],.cmx-zugangsdaten-category-fields[data-category="wlan"],.cmx-zugangsdaten-category-fields[data-category="api-keys"],.cmx-zugangsdaten-category-fields[data-category="passwoerter"],.cmx-zugangsdaten-category-fields[data-category="kreditkarten"]{grid-template-columns:1fr}.cmx-zugangsdaten-category-fields[data-category="server"] .cmx-zugangsdaten-field-server-cpu,.cmx-zugangsdaten-category-fields[data-category="server"] .cmx-zugangsdaten-field-server-ram,.cmx-zugangsdaten-category-fields[data-category="server"] .cmx-zugangsdaten-field-server-storage,.cmx-zugangsdaten-category-fields[data-category="email"] .cmx-zugangsdaten-field-email-signature,.cmx-zugangsdaten-category-fields[data-category="ssh-keys"] .cmx-zugangsdaten-field-public-key-value,.cmx-zugangsdaten-category-fields[data-category="ssh-keys"] .cmx-zugangsdaten-field-private-key-value{grid-column:auto}}
	</style>';

	echo '<script>
		document.addEventListener("DOMContentLoaded",function(){
			var select=document.getElementById("cmx-zugangsdaten-category");
			if(!select)return;
			var groups=Array.prototype.slice.call(document.querySelectorAll(".cmx-zugangsdaten-category-fields"));
			var metabox=document.getElementById("cmx_zugangsdaten_details");
			var issuer=document.getElementById("cmx-zugangsdaten-issuer-wrap");
			var providerBox=document.getElementById("cmx_zugangsdaten_provider");
			var providerFields=providerBox?providerBox.querySelector("[data-cmx-provider-fields]"):null;
			var providerCategories=["ssh-keys","server","email","api-keys"];
			function update(){
				var selected=String(select.value||"");
				if(metabox)metabox.style.display=selected===""?"none":"";
				if(issuer)issuer.hidden=selected!=="kreditkarten";
				var providerVisible=providerCategories.indexOf(selected)!==-1;
				if(providerBox)providerBox.style.display=providerVisible?"":"none";
				if(providerFields)providerFields.hidden=!providerVisible;
				groups.forEach(function(group){
					group.hidden=String(group.getAttribute("data-category")||"")!==selected;
				});
			}
			select.addEventListener("change",update);
			update();

			document.querySelectorAll(".cmx-zugangsdaten-toggle-row").forEach(function(row){
				var input=row.querySelector("input[type=checkbox]");
				var state=row.querySelector("[data-cmx-toggle-state]");
				if(!input||!state)return;
				function sync(){state.textContent=input.checked?"Ja":"Nein";}
				input.addEventListener("change",sync);sync();
			});

			var sshConfigOpen=document.querySelector("[data-cmx-ssh-config-open]");
			var sshConfigSelect=document.querySelector("[data-cmx-ssh-config-os]");
			var sshConfigStatus=document.querySelector("[data-cmx-ssh-config-status]");
			if(sshConfigOpen&&sshConfigSelect){
				function fieldElement(field){return document.getElementById("cmx-zugangsdaten-ssh-keys-"+field);}
				function fieldValue(field,usePlaceholder){var input=fieldElement(field);if(!input)return "";var value=String(input.value||"").trim();return value||((usePlaceholder&&input.placeholder)?String(input.placeholder).trim():"");}
				function safeValue(value,fallback){var cleaned=String(value||"").trim().replace(/[^A-Za-z0-9._@:-]+/g,"-").replace(/^-+|-+$/g,"");return cleaned||fallback;}
				function commandValues(){
					var locked=fieldElement("gesperrt");
					return {
						keyName:safeValue(fieldValue("public_key_name",false),""),
						username:safeValue(fieldValue("username",false),""),
						password:fieldValue("password",false),
						locked:!!(locked&&locked.checked),
						target:safeValue(fieldValue("ip_address",true),"")
					};
				}
				function missingFields(values){
					var missing=[];
					if(!values.username)missing.push("Username");
					if(!values.target)missing.push("IP-Adresse");
					if(values.locked&&!values.keyName)missing.push("Name des Public Keys");
					if(!values.locked&&!values.password)missing.push("Passwort");
					return missing;
				}
				function buildCommand(os,values){
					if(!values.locked)return "ssh "+values.username+"@"+values.target;
					if(os==="windows"){
						var bs=String.fromCharCode(92);
						var keyPath="$env:USERPROFILE"+bs+".ssh"+bs+values.keyName;
						return "ssh -i \""+keyPath+"\" "+values.username+"@"+values.target;
					}
					return "ssh -i ~/.ssh/"+values.keyName+" "+values.username+"@"+values.target;
				}
				function fallbackCopy(text){var area=document.createElement("textarea");area.value=text;area.setAttribute("readonly","");area.style.position="fixed";area.style.top="0";area.style.left="0";area.style.width="1px";area.style.height="1px";area.style.opacity="0";document.body.appendChild(area);area.focus();area.select();area.setSelectionRange(0,area.value.length);var copied=false;try{copied=document.execCommand("copy");}catch(error){}document.body.removeChild(area);return copied;}
				function copyCommand(command){
					if(!command)return Promise.resolve(false);
					if(navigator.clipboard&&typeof navigator.clipboard.writeText==="function")return navigator.clipboard.writeText(command).then(function(){return true;}).catch(function(){return fallbackCopy(command);});
					return Promise.resolve(fallbackCopy(command));
				}
				sshConfigOpen.addEventListener("click",function(event){
					event.preventDefault();
					sshConfigSelect.hidden=false;
					sshConfigSelect.value="";
					sshConfigSelect.focus();
					if(typeof sshConfigSelect.showPicker==="function"){try{sshConfigSelect.showPicker();}catch(error){}}
				});
				sshConfigSelect.addEventListener("change",function(){
					var os=String(sshConfigSelect.value||"");
					if(!os)return;
					var values=commandValues();
					var missing=missingFields(values);
					if(missing.length){sshConfigSelect.hidden=true;sshConfigSelect.value="";window.alert("Bitte folgende Felder ausfüllen: "+missing.join(", ")+".");return;}
					var command=buildCommand(os,values);
					var copyPromise=copyCommand(command);
					sshConfigSelect.hidden=true;
					sshConfigSelect.value="";
					copyPromise.then(function(copied){
						if(sshConfigStatus)sshConfigStatus.textContent=copied?"Befehl kopiert.":"Kopieren fehlgeschlagen.";
						window.setTimeout(function(){if(sshConfigStatus)sshConfigStatus.textContent="";},1800);
					});
				});
				document.addEventListener("click",function(event){if(sshConfigSelect.hidden)return;var menu=sshConfigOpen.closest(".cmx-zugangsdaten-ssh-command-menu");if(menu&&!menu.contains(event.target)){sshConfigSelect.hidden=true;sshConfigSelect.value="";}});
			}

			document.querySelectorAll(".cmx-zugangsdaten-password-wrap").forEach(function(wrap){
				var input=wrap.querySelector(".cmx-zugangsdaten-password-input");
				var toggle=wrap.querySelector(".cmx-zugangsdaten-password-toggle");
				var icon=toggle?toggle.querySelector(".cmx-zugangsdaten-password-icon"):null;
				if(!input||!toggle)return;
				function sync(){
					var visible=input.type==="text";
					var fieldLabel=String(toggle.getAttribute("data-field-label")||"Passwort");
					var label=fieldLabel+(visible?" ausblenden":" anzeigen");
					toggle.setAttribute("aria-label",label);
					toggle.setAttribute("title",label);
					toggle.setAttribute("aria-pressed",visible?"true":"false");
					if(icon)icon.className="cmx-zugangsdaten-password-icon "+(visible?"is-hide":"is-show");
				}
				toggle.addEventListener("click",function(){
					input.type=input.type==="password"?"text":"password";
					sync();
				});
				sync();
			});

			var publicKeyName=document.getElementById("cmx-zugangsdaten-ssh-keys-public_key_name");
			var publicKey=document.getElementById("cmx-zugangsdaten-ssh-keys-public_key");
			if(publicKeyName&&publicKey){
				function keyComment(){var parts=String(publicKey.value||"").trim().split(/\\s+/);return parts.length>2?parts.slice(2).join(" "):"";}
				if(publicKeyName.value.trim()!==keyComment())publicKeyName.dataset.manual="1";
				publicKeyName.addEventListener("input",function(){publicKeyName.dataset.manual=publicKeyName.value.trim()?"1":"";});
				publicKey.addEventListener("input",function(){if(publicKeyName.dataset.manual!=="1")publicKeyName.value=keyComment();});
				if(publicKeyName.value.trim()==="")publicKeyName.value=keyComment();
			}

			var keyDropzones=Array.prototype.slice.call(document.querySelectorAll(".cmx-zugangsdaten-key-dropzone"));
			if(keyDropzones.length){
				var publicKeyField=document.querySelector(".cmx-zugangsdaten-key-dropzone[data-cmx-key-kind=public]");
				var privateKeyField=document.querySelector(".cmx-zugangsdaten-key-dropzone[data-cmx-key-kind=private]");
				function keyKind(content,fileName){
					var text=String(content||"").trim();
					if(/-----BEGIN (?:(?:OPENSSH|RSA|EC|DSA|ENCRYPTED|SSH2 ENCRYPTED) )?PRIVATE KEY-----/.test(text)||/^PuTTY-User-Key-File-/m.test(text))return "private";
					if(/^(?:ssh-(?:rsa|dss|ed25519)|ecdsa-sha2-|sk-(?:ssh-ed25519|ecdsa-sha2-)|\\S+-cert-v01@openssh\\.com)\\S*\\s+\\S+/m.test(text))return "public";
					return /\\.pub$/i.test(String(fileName||""))?"public":"";
				}
				function statusFor(field){var wrap=field?field.parentNode:null;return wrap?wrap.querySelector("[data-cmx-key-file-status]"):null;}
				function showStatus(field,message,isError){var status=statusFor(field);if(!status)return;status.textContent=message;status.classList.toggle("is-error",!!isError);}
				function publicNameFromFile(fileName){return String(fileName||"").replace(/\\.pub$/i,"").trim();}
				function placeKey(kind,content,fileName){
					var field=kind==="public"?publicKeyField:privateKeyField;
					if(!field)return false;
					field.value=String(content||"").replace(/\\r\\n?/g,"\\n").trim();
					field.dispatchEvent(new Event("input",{bubbles:true}));
					if(kind==="public"&&publicKeyName&&publicKeyName.dataset.manual!=="1"&&publicKeyName.value.trim()==="")publicKeyName.value=publicNameFromFile(fileName);
					return true;
				}
				function importFiles(files,sourceField){
					var list=Array.prototype.slice.call(files||[]);
					if(!list.length)return;
					Promise.all(list.map(function(file){
						if(file.size>1048576)return Promise.resolve({file:file,error:"Datei ist zu groß."});
						return file.text().then(function(content){return {file:file,content:content};}).catch(function(){return {file:file,error:"Datei konnte nicht gelesen werden."};});
					})).then(function(items){
						var imported=[];var errors=[];
						items.forEach(function(item){
							if(item.error){errors.push(item.file.name+": "+item.error);return;}
							var kind=keyKind(item.content,item.file.name);
							if(kind===""||!placeKey(kind,item.content,item.file.name)){errors.push(item.file.name+": Kein gültiger SSH-Key erkannt.");return;}
							imported.push(kind);
						});
						var unique=imported.filter(function(kind,index){return imported.indexOf(kind)===index;});
						var message=unique.length===2?"Public und Private Key wurden eingelesen.":(unique[0]==="public"?"Public Key wurde eingelesen.":(unique[0]==="private"?"Private Key wurde eingelesen.":""));
						if(errors.length)message=(message?message+" ":"")+errors.join(" ");
						showStatus(sourceField,message,errors.length>0);
					});
				}
				keyDropzones.forEach(function(field){
					["dragenter","dragover"].forEach(function(name){field.addEventListener(name,function(event){event.preventDefault();event.dataTransfer.dropEffect="copy";field.classList.add("is-dragover");});});
					["dragleave","drop"].forEach(function(name){field.addEventListener(name,function(){field.classList.remove("is-dragover");});});
					field.addEventListener("drop",function(event){event.preventDefault();importFiles(event.dataTransfer.files,field);});
				});
				document.querySelectorAll("[data-cmx-key-file-input]").forEach(function(input){input.addEventListener("change",function(){var field=input.closest(".cmx-zugangsdaten-field").querySelector(".cmx-zugangsdaten-key-dropzone");importFiles(input.files,field);input.value="";});});
			}
		});
	</script>';
}

function cmx_zugangsdaten_url_protocols(): array {
	return \array_values(\array_unique(\array_merge(\wp_allowed_protocols(), ['sftp', 'webdav', 'webdavs', 's3'])));
}

function cmx_zugangsdaten_sanitize_field(string $field, string $type, $raw, bool $allow_html = false, bool $preserve_raw_html = false): string {
	$value = \is_scalar($raw) ? (string) \wp_unslash($raw) : '';
	$value = \str_replace("\0", '', $value);
	if ($type === 'email') {
		return \sanitize_email($value);
	}
	if ($type === 'url') {
		return \esc_url_raw($value, cmx_zugangsdaten_url_protocols());
	}
	if ($type === 'number') {
		$value = \trim($value);
		if (!\preg_match('/^\d+$/', $value)) {
			return '';
		}
		$number = (int) $value;
		return $number >= 1 && $number <= 65535 ? (string) $number : '';
	}
	if ($type === 'month') {
		$value = \trim($value);
		if (\preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
			return $value;
		}
		if (\preg_match('/^(0?[1-9]|1[0-2])[\.\/\-](\d{2}|\d{4})$/', $value, $matches)) {
			$year = \strlen($matches[2]) === 2 ? ('20' . $matches[2]) : $matches[2];
			return $year . '-' . \str_pad($matches[1], 2, '0', \STR_PAD_LEFT);
		}
		return '';
	}
	if ($type === 'password') {
		return $value;
	}
	if ($type === 'toggle') {
		return $value === '1' ? '1' : '';
	}
	if ($type === 'textarea') {
		if ($preserve_raw_html) {
			return $value;
		}
		return \trim($allow_html ? \wp_kses_post($value) : \sanitize_textarea_field($value));
	}
	if ($field === 'iban') {
		return \strtoupper(\sanitize_text_field($value));
	}
	return \sanitize_text_field($value);
}

function cmx_zugangsdaten_update_links(int $post_id, array $new_links): void {
	$old_links = (array) \get_post_meta($post_id, CMX_ZUGANGSDATEN_LINKS_META, true);
	$old_links = \array_values(\array_unique(\array_filter(\array_map('intval', $old_links))));
	$new_links = \array_values(\array_unique(\array_filter(\array_map('intval', $new_links), static function (int $linked_id) use ($post_id): bool {
		return $linked_id > 0
			&& $linked_id !== $post_id
			&& (string) \get_post_type($linked_id) === CMX_ZUGANGSDATEN_CPT;
	})));

	if ($new_links === []) {
		\delete_post_meta($post_id, CMX_ZUGANGSDATEN_LINKS_META);
	} else {
		\update_post_meta($post_id, CMX_ZUGANGSDATEN_LINKS_META, $new_links);
	}

	foreach (\array_diff($old_links, $new_links) as $linked_id) {
		$backlinks = (array) \get_post_meta((int) $linked_id, CMX_ZUGANGSDATEN_LINKS_META, true);
		$backlinks = \array_values(\array_diff(\array_unique(\array_filter(\array_map('intval', $backlinks))), [$post_id]));
		if ($backlinks === []) {
			\delete_post_meta((int) $linked_id, CMX_ZUGANGSDATEN_LINKS_META);
		} else {
			\update_post_meta((int) $linked_id, CMX_ZUGANGSDATEN_LINKS_META, $backlinks);
		}
	}

	foreach ($new_links as $linked_id) {
		$backlinks = (array) \get_post_meta($linked_id, CMX_ZUGANGSDATEN_LINKS_META, true);
		$backlinks = \array_values(\array_unique(\array_filter(\array_map('intval', $backlinks))));
		if (!\in_array($post_id, $backlinks, true)) {
			$backlinks[] = $post_id;
			\update_post_meta($linked_id, CMX_ZUGANGSDATEN_LINKS_META, $backlinks);
		}
	}
}

\add_action('save_post_' . CMX_ZUGANGSDATEN_CPT, function (int $post_id): void {
	static $updating_title = false;
	if ($updating_title) {
		return;
	}

	if (
		!isset($_POST['cmx_zugangsdaten_nonce'])
		|| !\wp_verify_nonce((string) \wp_unslash($_POST['cmx_zugangsdaten_nonce']), 'cmx_zugangsdaten_save')
		|| (\defined('DOING_AUTOSAVE') && \DOING_AUTOSAVE)
		|| !\current_user_can('edit_post', $post_id)
	) {
		return;
	}

	$categories = cmx_zugangsdaten_categories();
	$category = \sanitize_key((string) \wp_unslash($_POST['cmx_zugangsdaten_category'] ?? ''));
	if (!isset($categories[$category])) {
		return;
	}

	$term = \get_term_by('slug', $category, CMX_ZUGANGSDATEN_CATEGORY_TAX);
	if ($term && !\is_wp_error($term)) {
		\wp_set_object_terms($post_id, [(int) $term->term_id], CMX_ZUGANGSDATEN_CATEGORY_TAX, false);
	}

	$group_id = (int) ($_POST['cmx_zugangsdaten_group'] ?? 0);
	$group_term = $group_id > 0 ? \get_term($group_id, CMX_ZUGANGSDATEN_GROUP_TAX) : null;
	if (
		$group_term instanceof \WP_Term
		&& (string) $group_term->taxonomy === CMX_ZUGANGSDATEN_GROUP_TAX
	) {
		\wp_set_object_terms($post_id, [$group_id], CMX_ZUGANGSDATEN_GROUP_TAX, false);
	} else {
		\wp_set_object_terms($post_id, [], CMX_ZUGANGSDATEN_GROUP_TAX, false);
	}

	$provider_id = (int) ($_POST['cmx_zugangsdaten_provider'] ?? 0);
	$provider_term = $provider_id > 0 ? \get_term($provider_id, CMX_ZUGANGSDATEN_PROVIDER_TAX) : null;
	if (
		\in_array($category, cmx_zugangsdaten_provider_categories(), true)
		&& $provider_term instanceof \WP_Term
		&& (string) $provider_term->taxonomy === CMX_ZUGANGSDATEN_PROVIDER_TAX
	) {
		\wp_set_object_terms($post_id, [$provider_id], CMX_ZUGANGSDATEN_PROVIDER_TAX, false);
	} else {
		\wp_set_object_terms($post_id, [], CMX_ZUGANGSDATEN_PROVIDER_TAX, false);
	}

	$contact_ids = isset($_POST['cmx_zugangsdaten_contact_ids']) && \is_array($_POST['cmx_zugangsdaten_contact_ids'])
		? \array_values(\array_unique(\array_filter(\array_map('intval', (array) $_POST['cmx_zugangsdaten_contact_ids']), static function (int $contact_id): bool {
			return $contact_id > 0 && (string) \get_post_type($contact_id) === 'kontakte';
		})))
		: [];
	if ($contact_ids !== []) {
		\update_post_meta($post_id, cmx_zugangsdaten_meta_key('contact_id'), $contact_ids);
	} else {
		\delete_post_meta($post_id, cmx_zugangsdaten_meta_key('contact_id'));
	}

	$groups = cmx_zugangsdaten_field_groups();
	$active_fields = (array) ($groups[$category] ?? []);
	$posted_groups = isset($_POST['cmx_zugangsdaten_fields']) && \is_array($_POST['cmx_zugangsdaten_fields'])
		? (array) $_POST['cmx_zugangsdaten_fields']
		: [];
	$posted_fields = isset($posted_groups[$category]) && \is_array($posted_groups[$category])
		? (array) $posted_groups[$category]
		: [];
	if ($category === 'ssh-keys') {
		$public_key = isset($posted_fields['public_key'])
			? \trim((string) \wp_unslash($posted_fields['public_key']))
			: '';
		$public_key_name = isset($posted_fields['public_key_name'])
			? \trim((string) \wp_unslash($posted_fields['public_key_name']))
			: '';
		if ($public_key_name === '' && $public_key !== '') {
			$posted_fields['public_key_name'] = cmx_ssh_public_key_comment($public_key);
		}

		$submitted_title = \trim((string) \wp_unslash($_POST['post_title'] ?? ''));
		$public_key_name = \sanitize_text_field((string) ($posted_fields['public_key_name'] ?? ''));
		if ($submitted_title === '' && $public_key_name !== '') {
			$current_title = \trim((string) \get_the_title($post_id));
			if ($current_title !== $public_key_name) {
				$updating_title = true;
				try {
					\wp_update_post([
						'ID'         => $post_id,
						'post_title' => $public_key_name,
					]);
				} finally {
					$updating_title = false;
				}
			}
		}
	}

	$all_meta_fields = [];
	foreach ($groups as $fields) {
		foreach ((array) $fields as $field => $config) {
			if ((string) ($config['type'] ?? '') !== 'issuer') {
				$all_meta_fields[(string) $field] = true;
			}
		}
	}

	foreach (\array_keys($all_meta_fields) as $field) {
		if (!isset($active_fields[$field])) {
			\delete_post_meta($post_id, cmx_zugangsdaten_meta_key($field));
			continue;
		}

		$config = (array) $active_fields[$field];
		$type = (string) ($config['type'] ?? 'text');
		if ($type === 'issuer') {
			continue;
		}
		if ($type === 'contact') {
			$contact_id = (int) ($posted_fields[$field] ?? 0);
			if ($contact_id > 0 && (string) \get_post_type($contact_id) === 'kontakte') {
				\update_post_meta($post_id, cmx_zugangsdaten_meta_key($field), $contact_id);
			} else {
				\delete_post_meta($post_id, cmx_zugangsdaten_meta_key($field));
			}
			continue;
		}
		if ($type === 'ssh_public_key') {
			$key_id = \sanitize_key((string) \wp_unslash($posted_fields[$field] ?? ''));
			$valid_key_ids = [];
			foreach (cmx_get_admin_public_keys() as $key) {
				$valid_key_ids[] = cmx_ssh_public_key_id($key['key']);
			}
			if (\in_array($key_id, $valid_key_ids, true)) {
				\update_post_meta($post_id, cmx_zugangsdaten_meta_key($field), $key_id);
			} else {
				\delete_post_meta($post_id, cmx_zugangsdaten_meta_key($field));
			}
			continue;
		}
		if ($type === 'select') {
			$value = \sanitize_key((string) \wp_unslash($posted_fields[$field] ?? ''));
			$options = isset($config['options']) && \is_array($config['options']) ? $config['options'] : [];
			if ($value !== '' && \array_key_exists($value, $options)) {
				\update_post_meta($post_id, cmx_zugangsdaten_meta_key($field), $value);
			} else {
				\delete_post_meta($post_id, cmx_zugangsdaten_meta_key($field));
			}
			continue;
		}

		$value = cmx_zugangsdaten_sanitize_field(
			$field,
			$type,
			$posted_fields[$field] ?? '',
			!empty($config['allow_html']),
			!empty($config['preserve_raw_html'])
		);
		if (!empty($config['sensitive']) && $value !== '') {
			$value = cmx_zugangsdaten_encrypt($value);
		}
		if ($value === '') {
			\delete_post_meta($post_id, cmx_zugangsdaten_meta_key($field));
		} else {
			\update_post_meta(
				$post_id,
				cmx_zugangsdaten_meta_key($field),
				!empty($config['preserve_raw_html']) ? \wp_slash($value) : $value
			);
		}
	}

	if ($category === 'kreditkarten') {
		$issuer_id = (int) ($posted_fields['issuer'] ?? 0);
		$issuer_term = $issuer_id > 0 ? \get_term($issuer_id, CMX_ZUGANGSDATEN_ISSUER_TAX) : null;
		if ($issuer_term && !\is_wp_error($issuer_term)) {
			\wp_set_object_terms($post_id, [$issuer_id], CMX_ZUGANGSDATEN_ISSUER_TAX, false);
		} else {
			\wp_set_object_terms($post_id, [], CMX_ZUGANGSDATEN_ISSUER_TAX, false);
		}
	} else {
		\wp_set_object_terms($post_id, [], CMX_ZUGANGSDATEN_ISSUER_TAX, false);
	}

	$links = isset($_POST['cmx_zugangsdaten_links']) && \is_array($_POST['cmx_zugangsdaten_links'])
		? (array) $_POST['cmx_zugangsdaten_links']
		: [];
	cmx_zugangsdaten_update_links($post_id, $links);
}, 10);

\add_action('before_delete_post', function (int $post_id): void {
	if ((string) \get_post_type($post_id) !== CMX_ZUGANGSDATEN_CPT) {
		return;
	}
	cmx_zugangsdaten_update_links($post_id, []);
});
