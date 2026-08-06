<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

require_once __DIR__ . '/taxonomies.php';
require_once __DIR__ . '/metabox.php';
require_once __DIR__ . '/admincolumns.php';

\add_filter('cmx_duplicate_allowed_post_types', function (array $post_types): array {
	$post_types[] = CMX_ZUGANGSDATEN_CPT;
	return \array_values(\array_unique($post_types));
});

\add_action('cmx_duplicated_post', function (int $new_post_id, int $source_post_id, \WP_Post $source_post): void {
	if ((string) $source_post->post_type !== CMX_ZUGANGSDATEN_CPT) {
		return;
	}

	$links = (array) \get_post_meta($new_post_id, CMX_ZUGANGSDATEN_LINKS_META, true);
	cmx_zugangsdaten_update_links($new_post_id, $links);
}, 10, 3);

function cmx_zugangsdaten_transfer_sensitive_meta_keys(): array {
	$keys = [CMX_ZUGANGSDATEN_NOTES_META => true];
	foreach (cmx_zugangsdaten_field_groups() as $fields) {
		foreach ($fields as $field => $config) {
			if (!empty($config['sensitive'])) {
				$keys[cmx_zugangsdaten_meta_key((string) $field)] = true;
			}
		}
	}
	return $keys;
}

function cmx_zugangsdaten_notes_text(int $post_id, bool $plain_text = false): string {
	if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_load_rows')) {
		return cmx_zugangsdaten_decrypt((string) \get_post_meta($post_id, CMX_ZUGANGSDATEN_NOTES_META, true));
	}

	$texts = [];
	foreach (cmx_notizen_load_rows($post_id, CMX_ZUGANGSDATEN_CPT) as $row) {
		$text = \trim((string) ($row['text'] ?? ''));
		if ($text === '') {
			continue;
		}
		if ($plain_text) {
			$text = \trim((string) \wp_strip_all_tags(\preg_replace('/<br\s*\/?\s*>/i', "\n", $text)));
		}
		$texts[] = $text;
	}

	return \implode("\n\n", $texts);
}

function cmx_zugangsdaten_notes_replace_from_text(int $post_id, string $text): void {
	$meta_key = \function_exists(__NAMESPACE__ . '\\cmx_notizen_meta_key_for_post_type')
		? cmx_notizen_meta_key_for_post_type(CMX_ZUGANGSDATEN_CPT)
		: '_cmx_zugangsdaten_intern_notizen';
	$text = \function_exists(__NAMESPACE__ . '\\cmx_notizen_sanitize_text')
		? cmx_notizen_sanitize_text($text)
		: \sanitize_textarea_field($text);

	if ($text === '') {
		\delete_post_meta($post_id, $meta_key);
	} else {
		$row = [
			'betreff' => 'Allgemein',
			'datum'   => \function_exists(__NAMESPACE__ . '\\cmx_notizen_now_date') ? cmx_notizen_now_date() : (string) \current_time('Y-m-d'),
			'zeit'    => \function_exists(__NAMESPACE__ . '\\cmx_notizen_now_time') ? cmx_notizen_now_time() : (string) \current_time('H:i'),
			'text'    => $text,
			'quelle'  => '',
		];
		\update_post_meta($post_id, $meta_key, [$row]);
	}
	\delete_post_meta($post_id, CMX_ZUGANGSDATEN_NOTES_META);
}

\add_filter('cmx_cpt_transfer_relation_targets', function (array $targets, string $meta_key): array {
	if ($meta_key === cmx_zugangsdaten_meta_key('contact_id')) {
		return ['kontakte'];
	}
	if ($meta_key === CMX_ZUGANGSDATEN_LINKS_META) {
		return [CMX_ZUGANGSDATEN_CPT];
	}
	return $targets;
}, 10, 2);

\add_filter('cmx_cpt_transfer_export_meta_value', function (
	string $value,
	string $post_type,
	int $post_id,
	string $meta_key
): string {
	if ($post_type !== CMX_ZUGANGSDATEN_CPT || !isset(cmx_zugangsdaten_transfer_sensitive_meta_keys()[$meta_key])) {
		return $value;
	}
	return cmx_zugangsdaten_decrypt($value);
}, 10, 4);

\add_filter('cmx_cpt_transfer_import_meta_value', function (
	$value,
	string $post_type,
	int $post_id,
	string $meta_key
) {
	if (
		$post_type !== CMX_ZUGANGSDATEN_CPT
		|| !isset(cmx_zugangsdaten_transfer_sensitive_meta_keys()[$meta_key])
		|| !\is_scalar($value)
	) {
		return $value;
	}

	$value = (string) $value;
	if ($value === '' || \str_starts_with($value, 'cmxenc:')) {
		return $value;
	}
	return cmx_zugangsdaten_encrypt($value);
}, 10, 4);

\add_filter('cmx_cpt_transfer_collect_args', function (
	array $args,
	string $post_type,
	array $request,
	array $selected
): array {
	if ($post_type !== CMX_ZUGANGSDATEN_CPT || $selected !== []) {
		return $args;
	}

	$category = \sanitize_key((string) \wp_unslash($request['cmx_zugangsdaten_category'] ?? ''));
	if (isset(cmx_zugangsdaten_categories()[$category])) {
		$tax_query = isset($args['tax_query']) && \is_array($args['tax_query']) ? $args['tax_query'] : [];
		$tax_query[] = [
			'taxonomy' => CMX_ZUGANGSDATEN_CATEGORY_TAX,
			'field'    => 'slug',
			'terms'    => [$category],
		];
		$args['tax_query'] = $tax_query;
	}

	$contact_id = (int) ($request['cmx_zugangsdaten_contact'] ?? 0);
	if ($contact_id > 0 && (string) \get_post_type($contact_id) === 'kontakte') {
		$meta_query = isset($args['meta_query']) && \is_array($args['meta_query']) ? $args['meta_query'] : [];
		$meta_query[] = cmx_zugangsdaten_contact_filter_meta_query($contact_id);
		$args['meta_query'] = $meta_query;
	}

	return $args;
}, 10, 4);

\add_filter('cmx_cpt_transfer_append_term', function (bool $append, string $taxonomy): bool {
	return \in_array($taxonomy, [CMX_ZUGANGSDATEN_CATEGORY_TAX, CMX_ZUGANGSDATEN_ISSUER_TAX, CMX_ZUGANGSDATEN_GROUP_TAX, CMX_ZUGANGSDATEN_PROVIDER_TAX], true)
		? false
		: $append;
}, 10, 2);

\add_action('cmx_cpt_transfer_import_completed', function (array $id_map, array $post_types): void {
	if (!\in_array(CMX_ZUGANGSDATEN_CPT, $post_types, true)) {
		return;
	}

	foreach ($id_map as $new_post_id) {
		$new_post_id = (int) $new_post_id;
		if ($new_post_id <= 0 || (string) \get_post_type($new_post_id) !== CMX_ZUGANGSDATEN_CPT) {
			continue;
		}
		$links = (array) \get_post_meta($new_post_id, CMX_ZUGANGSDATEN_LINKS_META, true);
		cmx_zugangsdaten_update_links($new_post_id, $links);
	}
}, 10, 2);

function cmx_zugangsdaten_password_csv_header(string $header): string {
	$header = \preg_replace('/^\xEF\xBB\xBF/', '', $header);
	$header = \strtolower(\trim((string) $header));
	return \preg_replace('/[^a-z0-9_]+/', '_', \remove_accents($header)) ?: '';
}

function cmx_zugangsdaten_password_csv_rows(string $path): array {
	$handle = @\fopen($path, 'rb');
	if (!$handle) {
		return [];
	}

	$sample = (string) \fgets($handle);
	\rewind($handle);
	$delimiters = [',' => \substr_count($sample, ','), ';' => \substr_count($sample, ';'), "\t" => \substr_count($sample, "\t")];
	\arsort($delimiters);
	$delimiter = (string) \array_key_first($delimiters);
	if (($delimiters[$delimiter] ?? 0) <= 0) {
		\fclose($handle);
		return [];
	}

	$headers = \fgetcsv($handle, 0, $delimiter, '"', '\\');
	if (!\is_array($headers)) {
		\fclose($handle);
		return [];
	}
	$headers = \array_map(__NAMESPACE__ . '\\cmx_zugangsdaten_password_csv_header', $headers);
	$rows = [];
	while (($values = \fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
		if (\count($values) < \count($headers)) {
			$values = \array_pad($values, \count($headers), '');
		} elseif (\count($values) > \count($headers)) {
			$values = \array_slice($values, 0, \count($headers));
		}
		$row = \array_combine($headers, $values);
		if (\is_array($row) && \array_filter($row, static fn($value): bool => \trim((string) $value) !== '') !== []) {
			$rows[] = $row;
		}
		if (\count($rows) >= 10000) {
			break;
		}
	}
	\fclose($handle);
	return $rows;
}

function cmx_zugangsdaten_password_csv_value(array $row, array $keys): string {
	foreach ($keys as $key) {
		$key = cmx_zugangsdaten_password_csv_header((string) $key);
		if (isset($row[$key]) && \trim((string) $row[$key]) !== '') {
			return \trim((string) $row[$key]);
		}
	}
	return '';
}

function cmx_zugangsdaten_password_csv_category(array $row): string {
	$value = cmx_zugangsdaten_password_csv_value($row, ['category', 'kategorie', 'grouping', 'group', 'folder', 'type']);
	$slug = \sanitize_key($value);
	if (isset(cmx_zugangsdaten_categories()[$slug])) {
		return $slug;
	}
	$normalized = \strtolower(\trim(\remove_accents($value)));
	foreach (cmx_zugangsdaten_categories() as $category_slug => $category_label) {
		if ($normalized !== '' && $normalized === \strtolower(\trim(\remove_accents((string) $category_label)))) {
			return (string) $category_slug;
		}
	}

	$category_aliases = [
		'ftp' => [
			'ftp', 'ftps', 'sftp', 'webdav', 'web dav', 's3', 'file transfer', 'dateitransfer', 'datentransfer', 'ftp-zugang', 'ftp-zugange',
		],
		'ssh-keys' => [
			'ssh', 'ssh key', 'ssh keys', 'ssh-key', 'ssh-keys',
			'server', 'servers', 'serverzugang', 'serverzugange',
		],
		'wlan' => [
			'wlan', 'wi-fi', 'wifi', 'wireless', 'wireless network',
			'wireless networks', 'network', 'netzwerk', 'funknetz',
		],
		'passwoerter' => [
			'password', 'passwords', 'passwort', 'passworter',
			'login', 'logins', 'website login', 'website logins',
		],
		'kreditkarten' => [
			'credit card', 'credit cards', 'creditcard', 'creditcards',
			'payment card', 'payment cards', 'bank card', 'bank cards',
			'kreditkarte', 'kreditkarten', 'karte', 'karten',
		],
		'notizen' => [
			'note', 'notes', 'secure note', 'secure notes',
			'notiz', 'notizen', 'sichere notiz', 'sichere notizen',
		],
		'lizenzen' => [
			'license', 'licenses', 'licence', 'licences',
			'software license', 'software licenses', 'software licence', 'software licences',
			'lizenz', 'lizenzen',
		],
		'api-keys' => [
			'api', 'api key', 'api keys', 'api-key', 'api-keys',
			'access key', 'access keys', 'token', 'tokens',
		],
	];
	$canonical_alias = static function (string $text): string {
		return \trim((string) \preg_replace('/[^a-z0-9]+/', ' ', \strtolower(\remove_accents($text))));
	};
	$alias_candidates = [$normalized, $canonical_alias($normalized)];
	foreach (\preg_split('/[\\\\\\/|>:]+/', $normalized) ?: [] as $part) {
		$part = \trim((string) $part);
		if ($part !== '') {
			$alias_candidates[] = $part;
			$alias_candidates[] = $canonical_alias($part);
		}
	}
	foreach ($category_aliases as $category_slug => $aliases) {
		$aliases = \array_merge($aliases, \array_map($canonical_alias, $aliases));
		if (\array_intersect(\array_unique($alias_candidates), \array_unique($aliases)) !== []) {
			return $category_slug;
		}
	}

	if (cmx_zugangsdaten_password_csv_value($row, ['cardnumber', 'card_number', 'kartennummer', 'cvc', 'cvv', 'cardholdername']) !== '') {
		return 'kreditkarten';
	}
	if (cmx_zugangsdaten_password_csv_value($row, ['private_key', 'public_key', 'ssh_key', 'hostname', 'servername', 'ip_address']) !== '') {
		return 'ssh-keys';
	}
	if (cmx_zugangsdaten_password_csv_value($row, ['ssid', 'wifi_ssid', 'wireless_ssid', 'router_ip', 'routerip']) !== '') {
		return 'wlan';
	}
	if (cmx_zugangsdaten_password_csv_value($row, ['api_key', 'access_key', 'secret_key', 'client_secret', 'client_id']) !== '') {
		return 'api-keys';
	}
	if (cmx_zugangsdaten_password_csv_value($row, ['license_key', 'licence_key', 'product_key', 'serial_number', 'serial']) !== '') {
		return 'lizenzen';
	}
	$has_login = cmx_zugangsdaten_password_csv_value($row, ['url', 'website', 'username', 'login', 'password']) !== '';
	$has_note = cmx_zugangsdaten_password_csv_value($row, ['note', 'notes', 'extra']) !== '';
	if (!$has_login && $has_note) {
		return 'notizen';
	}
	return 'passwoerter';
}

function cmx_zugangsdaten_password_csv_expiry(string $value): string {
	$value = \trim($value);
	if (\preg_match('/^(20\\d{2})[-\\/.](0?[1-9]|1[0-2])$/', $value, $matches)) {
		return (string) $matches[1] . '-' . \str_pad((string) $matches[2], 2, '0', \STR_PAD_LEFT);
	}
	if (\preg_match('/^(0?[1-9]|1[0-2])[-\\/.](\\d{2}|20\\d{2})$/', $value, $matches)) {
		$year = (string) $matches[2];
		$year = \strlen($year) === 2 ? '20' . $year : $year;
		return $year . '-' . \str_pad((string) $matches[1], 2, '0', \STR_PAD_LEFT);
	}
	return '';
}

function cmx_zugangsdaten_password_csv_fields(array $row, string $category): array {
	$name = cmx_zugangsdaten_password_csv_value($row, ['name', 'title', 'bezeichnung']);
	$url = cmx_zugangsdaten_password_csv_value($row, ['url', 'website', 'website_address']);
	$username = cmx_zugangsdaten_password_csv_value($row, ['username', 'login', 'benutzername', 'email']);
	$password = cmx_zugangsdaten_password_csv_value($row, ['password', 'passwort']);
	if ($category === 'ftp') {
		$protocol = \sanitize_key(cmx_zugangsdaten_password_csv_value($row, ['protocol', 'protokoll', 'type']));
		return [
			'hostname'       => cmx_zugangsdaten_password_csv_value($row, ['hostname', 'host', 'server', 'name']),
			'url'            => $url,
			'protocol'       => \in_array($protocol, ['ftp', 'ftps', 'sftp', 'webdav', 's3'], true) ? $protocol : '',
			'username'       => $username,
			'password'       => $password,
			'ssh_public_key' => cmx_zugangsdaten_password_csv_value($row, ['ssh_public_key_id', 'ssh_key_id', 'ssh_public_key']),
		];
	}

	if ($category === 'ssh-keys') {
		$public_key = cmx_zugangsdaten_password_csv_value($row, ['public_key', 'ssh_key']);
		$public_key_name = cmx_zugangsdaten_password_csv_value($row, ['public_key_name', 'key_name']);
		if ($public_key_name === '' && $public_key !== '') {
			$public_key_name = cmx_ssh_public_key_comment($public_key);
		}
		return [
			'hostname'        => cmx_zugangsdaten_password_csv_value($row, ['hostname', 'host', 'name']),
			'servername'      => cmx_zugangsdaten_password_csv_value($row, ['servername', 'server_name']),
			'username'        => $username,
			'ip_address'      => cmx_zugangsdaten_password_csv_value($row, ['ip_address', 'ip']),
			'public_key_name' => $public_key_name,
			'public_key'      => $public_key,
			'private_key'     => cmx_zugangsdaten_password_csv_value($row, ['private_key']),
		];
	}
	if ($category === 'wlan') {
		return [
			'ssid' => cmx_zugangsdaten_password_csv_value($row, ['ssid', 'wifi_ssid', 'wireless_ssid', 'name']),
			'password' => $password,
			'encryption_type' => cmx_zugangsdaten_password_csv_value($row, ['encryption_type', 'encryption', 'security', 'verschluesselungsart']),
			'router' => cmx_zugangsdaten_password_csv_value($row, ['router', 'router_name', 'routername']),
			'network' => cmx_zugangsdaten_password_csv_value($row, ['network', 'network_name', 'netzwerk']),
			'router_ip' => cmx_zugangsdaten_password_csv_value($row, ['router_ip', 'routerip', 'gateway', 'gateway_ip']),
			'web_interface' => cmx_zugangsdaten_password_csv_value($row, ['web_interface', 'webinterface', 'weboberflaeche', 'url', 'website']),
		];
	}
	if ($category === 'kreditkarten') {
		return [
			'card_name' => $name,
			'holder' => cmx_zugangsdaten_password_csv_value($row, ['cardholdername', 'cardholder_name', 'holder', 'inhaber']),
			'iban' => cmx_zugangsdaten_password_csv_value($row, ['iban']),
			'card_number' => cmx_zugangsdaten_password_csv_value($row, ['cardnumber', 'card_number', 'kartennummer']),
			'valid_until' => cmx_zugangsdaten_password_csv_expiry(cmx_zugangsdaten_password_csv_value($row, ['expirydate', 'expiry_date', 'valid_until', 'gultig_bis'])),
			'cvv' => cmx_zugangsdaten_password_csv_value($row, ['cvc', 'cvv']),
			'website' => $url,
		];
	}
	if ($category === 'notizen') {
		return ['subject' => $name];
	}
	if ($category === 'lizenzen') {
		return [
			'username' => $username,
			'email' => \is_email($username) ? $username : cmx_zugangsdaten_password_csv_value($row, ['email']),
			'key' => cmx_zugangsdaten_password_csv_value($row, ['key', 'license_key', 'licence_key', 'password']),
			'website' => $url,
		];
	}
	if ($category === 'api-keys') {
		return [
			'url' => $url,
			'access' => cmx_zugangsdaten_password_csv_value($row, ['access', 'access_key', 'api_key', 'client_id', 'username']),
			'secret' => cmx_zugangsdaten_password_csv_value($row, ['secret', 'secret_key', 'client_secret', 'password']),
			'website' => $url,
		];
	}
	return [
		'username' => $username,
		'password' => $password,
		'website' => $url,
	];
}

function cmx_zugangsdaten_password_csv_import(string $path, string $provider, bool $update_existing): array {
	$result = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'error' => ''];
	$rows = cmx_zugangsdaten_password_csv_rows($path);
	if ($rows === []) {
		$result['error'] = 'Die CSV-Datei enthält keine lesbaren Einträge.';
		return $result;
	}

	$required = ['url', 'username', 'password'];
	$headers = \array_keys($rows[0]);
	if (\array_diff($required, $headers) !== []) {
		$result['error'] = 'Das gewählte Format wurde nicht erkannt. Erforderlich sind mindestens URL, Benutzername und Passwort.';
		return $result;
	}

	foreach ($rows as $row) {
		$category = cmx_zugangsdaten_password_csv_category($row);
		$fields = cmx_zugangsdaten_password_csv_fields($row, $category);
		$title = cmx_zugangsdaten_password_csv_value($row, ['name', 'title', 'bezeichnung']);
		if ($title === '') {
			$title = cmx_zugangsdaten_password_csv_value($row, ['url', 'website', 'website_address', 'username', 'login']);
		}
		$title = \sanitize_text_field($title);
		if ($title === '') {
			$result['skipped']++;
			continue;
		}

		$post_name = \sanitize_title($title);
		$existing = 0;
		if ($update_existing && $post_name !== '') {
			$found = \get_page_by_path($post_name, OBJECT, CMX_ZUGANGSDATEN_CPT);
			$existing = $found instanceof \WP_Post ? (int) $found->ID : 0;
		}
		$postarr = [
			'post_type' => CMX_ZUGANGSDATEN_CPT,
			'post_status' => 'publish',
			'post_title' => $title,
			'post_name' => $post_name,
		];
		if ($existing > 0) {
			$postarr['ID'] = $existing;
		}
		$post_id = \wp_insert_post($postarr, true);
		if (\is_wp_error($post_id) || (int) $post_id <= 0) {
			$result['skipped']++;
			continue;
		}
		$post_id = (int) $post_id;
		\wp_set_object_terms($post_id, [$category], CMX_ZUGANGSDATEN_CATEGORY_TAX, false);

		$config = (array) (cmx_zugangsdaten_field_groups()[$category] ?? []);
		foreach ($config as $field => $field_config) {
			$value = (string) ($fields[$field] ?? '');
			$meta_key = cmx_zugangsdaten_meta_key((string) $field);
			if ($value === '') {
				\delete_post_meta($post_id, $meta_key);
				continue;
			}
			if (!empty($field_config['sensitive'])) {
				$value = cmx_zugangsdaten_encrypt($value);
			} elseif (!empty($field_config['preserve_raw_html'])) {
				$value = \wp_slash($value);
			} elseif (!empty($field_config['allow_html'])) {
				$value = \wp_kses_post($value);
			} else {
				$value = \sanitize_text_field($value);
			}
			\update_post_meta($post_id, $meta_key, $value);
		}

		$notes = cmx_zugangsdaten_password_csv_value($row, ['note', 'notes', 'extra']);
		cmx_zugangsdaten_notes_replace_from_text($post_id, $notes);
		\update_post_meta($post_id, '_cmx_zugangsdaten_import_source', \sanitize_key($provider));
		$existing > 0 ? $result['updated']++ : $result['imported']++;
	}

	return $result;
}

\add_action('all_admin_notices', function (): void {
	if (
		(string) ($_GET['post_type'] ?? '') !== CMX_ZUGANGSDATEN_CPT
		|| empty($_GET['cmx_cpt_transfer_import'])
		|| !\current_user_can('edit_posts')
	) {
		return;
	}
	?>
	<div class="notice notice-info" style="padding:18px;margin-top:15px;">
		<h2>Zugangsdaten importieren</h2>
		<p>Mis-Büro-Exporte als ZIP oder CSV-Dateien aus einem Passwortmanager importieren.</p>
		<form method="post" enctype="multipart/form-data">
			<?php \wp_nonce_field('cmx_zugangsdaten_password_csv_import'); ?>
			<input type="hidden" name="cmx_zugangsdaten_password_csv_do_import" value="1">
			<p>
				<label for="cmx-zugangsdaten-import-provider">Anbieter</label><br>
				<select id="cmx-zugangsdaten-import-provider" name="cmx_zugangsdaten_import_provider" required>
					<option value="misbuero">Mis Büro</option>
					<option value="google">Google Passwortmanager</option>
					<option value="lastpass">LastPass</option>
					<option value="nordpass">NordPass</option>
				</select>
			</p>
			<p><input type="file" name="cmx_zugangsdaten_password_csv" accept=".zip,.csv,application/zip,text/csv" required></p>
			<p><label><input type="checkbox" name="cmx_zugangsdaten_update_existing" value="1"> Existierende Zugangsdaten mit gleichem Slug aktualisieren</label></p>
			<p>
				<button type="submit" class="button button-primary">Import starten</button>
				<a href="<?php echo \esc_url(\add_query_arg(['post_type' => CMX_ZUGANGSDATEN_CPT], \admin_url('edit.php'))); ?>" style="margin-left:8px;">Abbrechen</a>
			</p>
		</form>
	</div>
	<?php
});

\add_action('load-edit.php', function (): void {
	if (
		empty($_POST['cmx_zugangsdaten_password_csv_do_import'])
		|| !\check_admin_referer('cmx_zugangsdaten_password_csv_import')
		|| !\current_user_can('edit_posts')
	) {
		return;
	}

	$post_type = \sanitize_key((string) ($_GET['post_type'] ?? ''));
	if ($post_type !== CMX_ZUGANGSDATEN_CPT) {
		return;
	}
	$file = isset($_FILES['cmx_zugangsdaten_password_csv']) && \is_array($_FILES['cmx_zugangsdaten_password_csv'])
		? (array) $_FILES['cmx_zugangsdaten_password_csv']
		: [];
	$name = \sanitize_file_name((string) ($file['name'] ?? ''));
	$tmp_name = (string) ($file['tmp_name'] ?? '');
	$error = (int) ($file['error'] ?? \UPLOAD_ERR_NO_FILE);
	$size = (int) ($file['size'] ?? 0);
	$provider = \sanitize_key((string) \wp_unslash($_POST['cmx_zugangsdaten_import_provider'] ?? ''));
	$extension = \strtolower((string) \pathinfo($name, \PATHINFO_EXTENSION));
	$valid_extension = $provider === 'misbuero' ? $extension === 'zip' : $extension === 'csv';
	$max_size = $provider === 'misbuero' ? 200 * 1024 * 1024 : 20 * 1024 * 1024;
	if (
		$error !== \UPLOAD_ERR_OK
		|| $tmp_name === ''
		|| !$valid_extension
		|| $size <= 0
		|| $size > $max_size
		|| !\in_array($provider, ['misbuero', 'google', 'lastpass', 'nordpass'], true)
	) {
		$result = [
			'imported' => 0,
			'updated' => 0,
			'skipped' => 0,
			'error' => $provider === 'misbuero'
				? 'Bitte eine gültige Mis-Büro-ZIP-Datei bis 200 MB auswählen.'
				: 'Bitte eine gültige CSV-Datei bis 20 MB auswählen.',
		];
	} elseif ($provider === 'misbuero') {
		$result = cmx_zugangsdaten_import_misbuero_zip($tmp_name, !empty($_POST['cmx_zugangsdaten_update_existing']));
	} else {
		$result = cmx_zugangsdaten_password_csv_import($tmp_name, $provider, !empty($_POST['cmx_zugangsdaten_update_existing']));
	}
	\update_user_meta(\get_current_user_id(), 'cmx_zugangsdaten_password_csv_notice', $result);
	\wp_safe_redirect(\add_query_arg([
		'post_type' => CMX_ZUGANGSDATEN_CPT,
		'cmx_zugangsdaten_password_csv_notice' => 1,
	], \admin_url('edit.php')));
	exit;
});

\add_action('admin_notices', function (): void {
	if (
		(string) ($_GET['post_type'] ?? '') !== CMX_ZUGANGSDATEN_CPT
		|| empty($_GET['cmx_zugangsdaten_password_csv_notice'])
	) {
		return;
	}
	$result = (array) \get_user_meta(\get_current_user_id(), 'cmx_zugangsdaten_password_csv_notice', true);
	\delete_user_meta(\get_current_user_id(), 'cmx_zugangsdaten_password_csv_notice');
	$error = (string) ($result['error'] ?? '');
	$class = $error !== '' ? 'notice notice-error is-dismissible' : 'notice notice-success is-dismissible';
	$message = $error !== ''
		? $error
		: \sprintf(
			'CSV-Import abgeschlossen: %d neu, %d aktualisiert, %d übersprungen.',
			(int) ($result['imported'] ?? 0),
			(int) ($result['updated'] ?? 0),
			(int) ($result['skipped'] ?? 0)
		);
	echo '<div class="' . \esc_attr($class) . '"><p>' . \esc_html($message) . '</p></div>';
});

function cmx_zugangsdaten_export_scope_args(array $request): array {
	if (!empty($request['post'])) {
		$post_ids = \array_values(\array_unique(\array_filter(\array_map('intval', (array) \wp_unslash($request['post'])))));
		if ($post_ids !== []) {
			return ['post' => $post_ids];
		}
	}

	$args = [];
	foreach (['cmx_zugangsdaten_category', 'cmx_zugangsdaten_contact', 's', 'post_status'] as $key) {
		if (!isset($request[$key]) || $request[$key] === '' || $request[$key] === '0' || $request[$key] === '-1') {
			continue;
		}
		$args[$key] = \sanitize_text_field((string) \wp_unslash($request[$key]));
	}
	return $args;
}

\add_action('load-edit.php', function (): void {
	if (
		\sanitize_key((string) \wp_unslash($_REQUEST['post_type'] ?? '')) !== CMX_ZUGANGSDATEN_CPT
		|| !\current_user_can('edit_posts')
	) {
		return;
	}

	$action = \sanitize_key((string) \wp_unslash($_REQUEST['action'] ?? ''));
	if ($action === '' || $action === '-1') {
		$action = \sanitize_key((string) \wp_unslash($_REQUEST['action2'] ?? ''));
	}
	if ($action !== 'cmx_cpt_transfer_export') {
		return;
	}

	\check_admin_referer('bulk-posts');
	$url = (string) \add_query_arg(\array_merge([
		'post_type' => CMX_ZUGANGSDATEN_CPT,
		'cmx_zugangsdaten_export' => 1,
	], cmx_zugangsdaten_export_scope_args((array) $_REQUEST)), \admin_url('edit.php'));

	\wp_safe_redirect($url);
	exit;
}, 1);

\add_filter('cmx_cpt_transfer_export_url', function (string $url, string $post_type): string {
	if ($post_type !== CMX_ZUGANGSDATEN_CPT) {
		return $url;
	}
	$url_query = [];
	$url_query_string = (string) \wp_parse_url($url, \PHP_URL_QUERY);
	if ($url_query_string !== '') {
		\parse_str($url_query_string, $url_query);
	}
	return (string) \add_query_arg(\array_merge([
		'post_type' => CMX_ZUGANGSDATEN_CPT,
		'cmx_zugangsdaten_export' => 1,
	], cmx_zugangsdaten_export_scope_args(\array_merge((array) $_GET, $url_query))), \admin_url('edit.php'));
}, 10, 2);

function cmx_zugangsdaten_google_export_ids(): array {
	return \array_values(\array_filter(cmx_cpt_transfer_collect_ids(CMX_ZUGANGSDATEN_CPT), static function (int $post_id): bool {
		return cmx_zugangsdaten_category_slug($post_id) === 'passwoerter';
	}));
}

function cmx_zugangsdaten_stream_google_csv(): void {
	$filename = 'mis-buero-google-passwortmanager-' . \wp_date('Ymd-His') . '.csv';
	\nocache_headers();
	\header('Content-Type: text/csv; charset=UTF-8');
	\header('Content-Disposition: attachment; filename="' . \sanitize_file_name($filename) . '"');

	$handle = \fopen('php://output', 'wb');
	if (!$handle) {
		exit;
	}
	\fwrite($handle, "\xEF\xBB\xBF");
	\fputcsv($handle, ['name', 'url', 'username', 'password', 'note'], ',', '"', '\\');
	foreach (cmx_zugangsdaten_google_export_ids() as $post_id) {
		\fputcsv($handle, [
			(string) \get_the_title($post_id),
			(string) \get_post_meta($post_id, cmx_zugangsdaten_meta_key('website'), true),
			(string) \get_post_meta($post_id, cmx_zugangsdaten_meta_key('username'), true),
			cmx_zugangsdaten_decrypt((string) \get_post_meta($post_id, cmx_zugangsdaten_meta_key('password'), true)),
			cmx_zugangsdaten_notes_text($post_id, true),
		], ',', '"', '\\');
	}
	\fclose($handle);
	exit;
}

function cmx_zugangsdaten_misbuero_export_data(): array {
	$headers = [
		'id', 'status', 'bezeichnung', 'slug', 'kategorie', 'gruppe', 'provider', 'herausgeber',
		'hostname', 'servername', 'username', 'ip_address', 'password', 'website',
		'ssid', 'encryption_type', 'router', 'network', 'router_ip', 'web_interface',
		'card_name', 'holder', 'iban', 'card_number', 'valid_until', 'cvv',
		'subject', 'email', 'key', 'url', 'access', 'secret', 'notizen',
		'kontakte', 'verknuepfungen', 'dokumente',
	];
	$rows = [];
	$files = [];
	foreach (cmx_cpt_transfer_collect_ids(CMX_ZUGANGSDATEN_CPT) as $post_id) {
		$post = \get_post($post_id);
		if (!$post instanceof \WP_Post) {
			continue;
		}
		$row = \array_fill_keys($headers, '');
		$row['id'] = (string) $post_id;
		$row['status'] = (string) $post->post_status;
		$row['bezeichnung'] = (string) $post->post_title;
		$row['slug'] = (string) $post->post_name;
		$row['kategorie'] = cmx_zugangsdaten_category_slug($post_id);

		$group = \wp_get_object_terms($post_id, CMX_ZUGANGSDATEN_GROUP_TAX, ['fields' => 'names']);
		if (!\is_wp_error($group) && !empty($group[0])) {
			$row['gruppe'] = (string) $group[0];
		}

		$provider = \wp_get_object_terms($post_id, CMX_ZUGANGSDATEN_PROVIDER_TAX, ['fields' => 'names']);
		if (!\is_wp_error($provider) && !empty($provider[0])) {
			$row['provider'] = (string) $provider[0];
		}

		$issuer = \wp_get_object_terms($post_id, CMX_ZUGANGSDATEN_ISSUER_TAX, ['fields' => 'names']);
		if (!\is_wp_error($issuer) && !empty($issuer[0])) {
			$row['herausgeber'] = (string) $issuer[0];
		}

		foreach (cmx_zugangsdaten_field_groups() as $group_fields) {
			foreach ($group_fields as $field => $config) {
				$value = (string) \get_post_meta($post_id, cmx_zugangsdaten_meta_key((string) $field), true);
				if (!empty($config['sensitive'])) {
					$value = cmx_zugangsdaten_decrypt($value);
				}
				$row[(string) $field] = $value;
			}
		}
		$row['notizen'] = cmx_zugangsdaten_notes_text($post_id);

		$contacts = [];
		foreach (\array_values(\array_unique(\array_filter(\array_map('intval', (array) \get_post_meta($post_id, cmx_zugangsdaten_meta_key('contact_id'), true))))) as $contact_id) {
			if ((string) \get_post_type($contact_id) === 'kontakte') {
				$contacts[] = [
					'slug' => (string) \get_post_field('post_name', $contact_id),
					'name' => (string) \get_the_title($contact_id),
				];
			}
		}
		$row['kontakte'] = (string) \wp_json_encode($contacts, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

		$links = [];
		foreach (\array_values(\array_unique(\array_filter(\array_map('intval', (array) \get_post_meta($post_id, CMX_ZUGANGSDATEN_LINKS_META, true))))) as $linked_id) {
			if ((string) \get_post_type($linked_id) === CMX_ZUGANGSDATEN_CPT) {
				$links[] = [
					'id' => $linked_id,
					'slug' => (string) \get_post_field('post_name', $linked_id),
					'name' => (string) \get_the_title($linked_id),
				];
			}
		}
		$row['verknuepfungen'] = (string) \wp_json_encode($links, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

		$documents = [];
		$doc_ids = \array_values(\array_unique(\array_filter(\array_map('intval', (array) \get_post_meta($post_id, CMX_DOK_UPLOADS_META, true)))));
		foreach ($doc_ids as $doc_id) {
			if ((string) \get_post_type($doc_id) !== 'dokumente') {
				continue;
			}
			$doc_files = [];
			$stored_files = (array) \get_post_meta($doc_id, CMX_DOK_SELF_META, true);
			$primary = (string) \get_post_meta($doc_id, '_cmx_dokumente_file_path', true);
			if ($primary !== '') {
				$stored_files[] = $primary;
			}
			$seen_file_rels = [];
			foreach ($stored_files as $stored_file) {
				$file_rel = \is_numeric($stored_file)
					? (string) \get_post_meta((int) $stored_file, '_wp_attached_file', true)
					: (string) $stored_file;
				$file_rel = \ltrim(\str_replace('\\', '/', $file_rel), '/');
				if ($file_rel === '' || isset($seen_file_rels[$file_rel])) {
					continue;
				}
				$seen_file_rels[$file_rel] = true;
				$abs = cmx_cpt_transfer_upload_abs_from_rel($file_rel);
				if ($file_rel === '' || $abs === '' || !\is_file($abs)) {
					continue;
				}
				$zip_path = 'dokumente/' . $post_id . '/' . $doc_id . '/' . \sanitize_file_name(\basename($file_rel));
				$files[$zip_path] = $abs;
				$doc_files[] = ['zip' => $zip_path, 'rel' => $file_rel];
			}
			$documents[] = [
				'title' => (string) \get_the_title($doc_id),
				'slug' => (string) \get_post_field('post_name', $doc_id),
				'files' => $doc_files,
			];
		}
		$row['dokumente'] = (string) \wp_json_encode($documents, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
		$rows[] = $row;
	}
	return ['headers' => $headers, 'rows' => $rows, 'files' => $files];
}

function cmx_zugangsdaten_stream_misbuero_zip(): void {
	$data = cmx_zugangsdaten_misbuero_export_data();
	$tmp = cmx_cpt_transfer_create_zip(static function (\ZipArchive $zip) use ($data): void {
		$zip->addFromString('zugangsdaten.csv', cmx_cpt_transfer_csv((array) $data['headers'], (array) $data['rows']));
		foreach ((array) $data['files'] as $zip_path => $abs) {
			if (\is_file((string) $abs)) {
				$zip->addFile((string) $abs, (string) $zip_path);
			}
		}
	});
	cmx_cpt_transfer_stream_file($tmp, 'mis-buero-zugangsdaten-' . \wp_date('Ymd-His') . '.zip');
}

function cmx_zugangsdaten_import_resolve_post(array $reference, string $post_type): int {
	$slug = \sanitize_title((string) ($reference['slug'] ?? ''));
	if ($slug !== '') {
		$post = \get_page_by_path($slug, OBJECT, $post_type);
		if ($post instanceof \WP_Post) {
			return (int) $post->ID;
		}
	}
	return cmx_cpt_transfer_find_post_by_title($post_type, (string) ($reference['name'] ?? ''));
}

function cmx_zugangsdaten_import_misbuero_zip(string $zip_file, bool $update_existing): array {
	$result = ['imported' => 0, 'updated' => 0, 'files' => 0, 'refs' => 0, 'unresolved_refs' => 0, 'skipped' => 0, 'error' => ''];
	if (!\class_exists('\\ZipArchive')) {
		$result['error'] = 'ZIP ist auf diesem System nicht verfügbar.';
		return $result;
	}
	$zip = new \ZipArchive();
	if ($zip->open($zip_file) !== true) {
		$result['error'] = 'Die ZIP-Datei konnte nicht geöffnet werden.';
		return $result;
	}
	$csv = $zip->getFromName('zugangsdaten.csv');
	if (!\is_string($csv)) {
		$zip->close();
		return cmx_cpt_transfer_import_zip($zip_file, $update_existing, [CMX_ZUGANGSDATEN_CPT]);
	}
	$csv_file = \wp_tempnam('cmx-zugangsdaten-csv-');
	if (!\is_string($csv_file) || $csv_file === '' || \file_put_contents($csv_file, $csv) === false) {
		$zip->close();
		$result['error'] = 'Die Zugangsdaten-CSV konnte nicht gelesen werden.';
		return $result;
	}
	$rows = cmx_cpt_transfer_read_csv($csv_file);
	@\unlink($csv_file);
	if ($rows === []) {
		$zip->close();
		$result['error'] = 'Die Zugangsdaten-CSV enthält keine Einträge.';
		return $result;
	}

	$id_map = [];
	$pending_links = [];
	foreach ($rows as $row) {
		$title = \sanitize_text_field((string) ($row['bezeichnung'] ?? ''));
		if ($title === '') {
			$result['skipped']++;
			continue;
		}
		$slug = \sanitize_title((string) ($row['slug'] ?? $title));
		$existing = 0;
		if ($update_existing && $slug !== '') {
			$found = \get_page_by_path($slug, OBJECT, CMX_ZUGANGSDATEN_CPT);
			$existing = $found instanceof \WP_Post ? (int) $found->ID : 0;
		}
		$postarr = [
			'post_type' => CMX_ZUGANGSDATEN_CPT,
			'post_status' => \sanitize_key((string) ($row['status'] ?? 'publish')) ?: 'publish',
			'post_title' => $title,
			'post_name' => $slug,
		];
		if ($existing > 0) {
			$postarr['ID'] = $existing;
		}
		$post_id = \wp_insert_post($postarr, true);
		if (\is_wp_error($post_id) || (int) $post_id <= 0) {
			$result['skipped']++;
			continue;
		}
		$post_id = (int) $post_id;
		$old_id = (int) ($row['id'] ?? 0);
		if ($old_id > 0) {
			$id_map[$old_id] = $post_id;
		}
		$existing > 0 ? $result['updated']++ : $result['imported']++;

		$category = \sanitize_key((string) ($row['kategorie'] ?? ''));
		if (!isset(cmx_zugangsdaten_categories()[$category])) {
			$category = 'passwoerter';
		}
		\wp_set_object_terms($post_id, [$category], CMX_ZUGANGSDATEN_CATEGORY_TAX, false);
		$group = \sanitize_text_field((string) ($row['gruppe'] ?? ''));
		\wp_set_object_terms($post_id, $group !== '' ? [$group] : [], CMX_ZUGANGSDATEN_GROUP_TAX, false);
		$provider = \sanitize_text_field((string) ($row['provider'] ?? ''));
		\wp_set_object_terms(
			$post_id,
			$provider !== '' && \in_array($category, cmx_zugangsdaten_provider_categories(), true) ? [$provider] : [],
			CMX_ZUGANGSDATEN_PROVIDER_TAX,
			false
		);
		$issuer = \sanitize_text_field((string) ($row['herausgeber'] ?? ''));
		\wp_set_object_terms($post_id, $issuer !== '' ? [$issuer] : [], CMX_ZUGANGSDATEN_ISSUER_TAX, false);

		foreach ((array) (cmx_zugangsdaten_field_groups()[$category] ?? []) as $field => $config) {
			$value = (string) ($row[$field] ?? '');
			$meta_key = cmx_zugangsdaten_meta_key((string) $field);
			if ($value === '') {
				\delete_post_meta($post_id, $meta_key);
				continue;
			}
			if (!empty($config['sensitive'])) {
				$value = cmx_zugangsdaten_encrypt($value);
			} elseif (!empty($config['preserve_raw_html'])) {
				$value = \wp_slash($value);
			} elseif (!empty($config['allow_html'])) {
				$value = \wp_kses_post($value);
			} else {
				$value = \sanitize_text_field($value);
			}
			\update_post_meta($post_id, $meta_key, $value);
		}
		$notes = (string) ($row['notizen'] ?? '');
		cmx_zugangsdaten_notes_replace_from_text($post_id, $notes);

		$contact_ids = [];
		$contacts = \json_decode((string) ($row['kontakte'] ?? ''), true);
		foreach (\is_array($contacts) ? $contacts : [] as $reference) {
			$contact_id = cmx_zugangsdaten_import_resolve_post((array) $reference, 'kontakte');
			if ($contact_id > 0) {
				$contact_ids[] = $contact_id;
				$result['refs']++;
			} else {
				$result['unresolved_refs']++;
			}
		}
		if ($contact_ids !== []) {
			\update_post_meta($post_id, cmx_zugangsdaten_meta_key('contact_id'), \array_values(\array_unique($contact_ids)));
		} else {
			\delete_post_meta($post_id, cmx_zugangsdaten_meta_key('contact_id'));
		}
		$pending_links[$post_id] = \json_decode((string) ($row['verknuepfungen'] ?? ''), true);

		$document_ids = [];
		$documents = \json_decode((string) ($row['dokumente'] ?? ''), true);
		foreach (\is_array($documents) ? $documents : [] as $document) {
			$doc_title = \sanitize_text_field((string) ($document['title'] ?? 'Dokument'));
			$doc_id = \wp_insert_post([
				'post_type' => 'dokumente',
				'post_status' => 'publish',
				'post_title' => $doc_title !== '' ? $doc_title : 'Dokument',
				'post_name' => \sanitize_title((string) ($document['slug'] ?? $doc_title)),
			], true);
			if (\is_wp_error($doc_id) || (int) $doc_id <= 0) {
				continue;
			}
			$doc_id = (int) $doc_id;
			$imported_files = [];
			foreach ((array) ($document['files'] ?? []) as $file) {
				$zip_path = \ltrim(\str_replace('\\', '/', (string) ($file['zip'] ?? '')), '/');
				$source_rel = \ltrim(\str_replace('\\', '/', (string) ($file['rel'] ?? '')), '/');
				if (!\str_starts_with($zip_path, 'dokumente/') || \str_contains($zip_path, '..') || \str_contains($source_rel, '..')) {
					continue;
				}
				$binary = $zip->getFromName($zip_path);
				if (!\is_string($binary)) {
					continue;
				}
				$uploads = \wp_get_upload_dir();
				$target_dir = \trailingslashit((string) ($uploads['basedir'] ?? '')) . 'misbuero/archiv/' . \wp_date('Y') . '/dokumente';
				\wp_mkdir_p($target_dir);
				$filename = \wp_unique_filename($target_dir, \sanitize_file_name(\basename($source_rel !== '' ? $source_rel : $zip_path)));
				$target = \trailingslashit($target_dir) . $filename;
				if (\file_put_contents($target, $binary) === false) {
					continue;
				}
				$imported_files[] = 'misbuero/archiv/' . \wp_date('Y') . '/dokumente/' . $filename;
				$result['files']++;
			}
			if ($imported_files !== []) {
				\update_post_meta($doc_id, CMX_DOK_SELF_META, $imported_files);
				\update_post_meta($doc_id, '_cmx_dokumente_file_path', (string) $imported_files[0]);
			}
			\update_post_meta($doc_id, 'cmx_dokumente_rel_' . CMX_ZUGANGSDATEN_CPT, [$post_id]);
			$document_ids[] = $doc_id;
		}
		if ($document_ids !== []) {
			\update_post_meta($post_id, CMX_DOK_UPLOADS_META, $document_ids);
		}
	}
	$zip->close();

	foreach ($pending_links as $post_id => $references) {
		$link_ids = [];
		foreach (\is_array($references) ? $references : [] as $reference) {
			$old_id = (int) ($reference['id'] ?? 0);
			$linked_id = $old_id > 0 ? (int) ($id_map[$old_id] ?? 0) : 0;
			if ($linked_id <= 0) {
				$linked_id = cmx_zugangsdaten_import_resolve_post((array) $reference, CMX_ZUGANGSDATEN_CPT);
			}
			if ($linked_id > 0 && $linked_id !== (int) $post_id) {
				$link_ids[] = $linked_id;
				$result['refs']++;
			} else {
				$result['unresolved_refs']++;
			}
		}
		cmx_zugangsdaten_update_links((int) $post_id, \array_values(\array_unique($link_ids)));
	}
	return $result;
}

\add_action('all_admin_notices', function (): void {
	if (
		(string) ($_GET['post_type'] ?? '') !== CMX_ZUGANGSDATEN_CPT
		|| empty($_GET['cmx_zugangsdaten_export'])
		|| !\current_user_can('edit_posts')
	) {
		return;
	}
	?>
	<div class="notice notice-info" style="padding:18px;margin-top:15px;">
		<h2>Zugangsdaten exportieren</h2>
		<form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>">
			<?php \wp_nonce_field('cmx_zugangsdaten_export'); ?>
			<input type="hidden" name="action" value="cmx_zugangsdaten_export">
			<?php foreach (cmx_zugangsdaten_export_scope_args((array) $_GET) as $scope_key => $scope_value) : ?>
				<?php if (\is_array($scope_value)) : ?>
					<?php foreach ($scope_value as $scope_item) : ?>
						<input type="hidden" name="<?php echo \esc_attr((string) $scope_key); ?>[]" value="<?php echo \esc_attr((string) $scope_item); ?>">
					<?php endforeach; ?>
				<?php else : ?>
					<input type="hidden" name="<?php echo \esc_attr((string) $scope_key); ?>" value="<?php echo \esc_attr((string) $scope_value); ?>">
				<?php endif; ?>
			<?php endforeach; ?>
			<p>
				<label for="cmx-zugangsdaten-export-format">Format</label><br>
				<select id="cmx-zugangsdaten-export-format" name="cmx_zugangsdaten_export_format" required>
					<option value="misbuero">Mis Büro</option>
					<option value="google">Google Passwortmanager</option>
				</select>
			</p>
			<p>
				<button type="submit" class="button button-primary">Export starten</button>
				<a href="<?php echo \esc_url(\add_query_arg(['post_type' => CMX_ZUGANGSDATEN_CPT], \admin_url('edit.php'))); ?>" style="margin-left:8px;">Abbrechen</a>
			</p>
		</form>
	</div>
	<?php
});

\add_action('admin_post_cmx_zugangsdaten_export', function (): void {
	if (!\current_user_can('edit_posts') || !\check_admin_referer('cmx_zugangsdaten_export')) {
		\wp_die('Keine Berechtigung.');
	}
	$format = \sanitize_key((string) \wp_unslash($_POST['cmx_zugangsdaten_export_format'] ?? ''));
	if ($format === 'misbuero') {
		cmx_zugangsdaten_stream_misbuero_zip();
	}
	if ($format === 'google') {
		cmx_zugangsdaten_stream_google_csv();
	}
	\wp_die('Unbekanntes Exportformat.');
});
