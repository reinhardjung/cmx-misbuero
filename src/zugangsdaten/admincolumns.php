<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

function cmx_zugangsdaten_admin_detail_rows(int $post_id): array {
	$category = cmx_zugangsdaten_category_slug($post_id);
	$fields_by_category = [
		'server' => [
			'hostname'       => 'Hostname',
			'fqdn'           => 'FQDN',
			'ssh_public_key' => 'SSH-Key',
			'ip_address'     => 'IP-Adresse',
			'server_id'      => 'ID',
		],
		'ftp' => [
			'hostname'       => 'Hostname',
			'url'            => 'URL',
			'protocol'       => 'Protokoll',
			'username'       => 'Benutzername',
			'ssh_public_key' => 'SSH-Key',
		],
		'email' => [
			'email_address'   => 'E-Mail-Adresse',
			'account_type'    => 'Kontotyp',
			'incoming_server' => 'Posteingangsserver',
			'smtp_server'     => 'SMTP-Server',
			'webmail_url'     => 'Webmail',
		],
		'ssh-keys' => [
			'servername' => 'Server',
			'hostname'   => 'Hostname',
			'username'   => 'Username',
			'ip_address' => 'IP-Adresse',
			'public_key_name' => 'SSH-Key',
		],
		'wlan' => [
			'ssid'            => 'Name (SSID)',
			'encryption_type' => 'Verschlüsselung',
			'router'          => 'Router',
			'network'         => 'Netzwerk',
			'router_ip'       => 'Router-IP',
			'web_interface'   => 'Weboberfläche',
		],
		'passwoerter' => [
			'username' => 'Benutzername',
			'website'  => 'Website',
		],
		'kreditkarten' => [
			'holder'    => 'Inhaber',
			'website'   => 'Website',
		],
		'notizen' => [
			'subject' => 'Betreff',
		],
		'lizenzen' => [
			'username' => 'Benutzername',
			'email'    => 'E-Mail',
			'website'  => 'Website',
		],
		'api-keys' => [
			'url'     => 'URL',
			'website' => 'Website',
		],
	];

	$rows = [];
	foreach ((array) ($fields_by_category[$category] ?? []) as $field => $label) {
		$value = \trim((string) \get_post_meta($post_id, cmx_zugangsdaten_meta_key((string) $field), true));
		if ($field === 'ssh_public_key' && $value !== '') {
			$key_name = '';
			foreach (cmx_get_admin_public_keys() as $key) {
				if (\hash_equals($value, cmx_ssh_public_key_id($key['key']))) {
					$key_name = $key['name'];
					break;
				}
			}
			$value = $key_name;
		}
		if ($field === 'account_type') {
			$value = (string) (['imap' => 'IMAP', 'pop3' => 'POP3', 'exchange' => 'Exchange'][$value] ?? '');
		}
		if ($field === 'protocol') {
			$value = (string) (['ftp' => 'FTP', 'ftps' => 'FTPS', 'sftp' => 'SFTP', 'webdav' => 'WebDAV', 's3' => 'S3'][$value] ?? '');
		}
		if ($value !== '') {
			$rows[] = [
				'label'  => (string) $label,
				'value'  => $value,
				'is_url' => \in_array((string) $field, ['url', 'website', 'web_interface', 'webmail_url'], true),
			];
		}
	}

	if ($category === 'kreditkarten') {
		$terms = \wp_get_object_terms($post_id, CMX_ZUGANGSDATEN_ISSUER_TAX, ['fields' => 'names']);
		if (!\is_wp_error($terms) && !empty($terms[0])) {
			$rows[] = ['label' => 'Herausgeber', 'value' => (string) $terms[0]];
		}
	}

	return $rows;
}

function cmx_zugangsdaten_contact_filter_meta_query(int $contact_id): array {
	$meta_key = cmx_zugangsdaten_meta_key('contact_id');
	return [
		'relation' => 'OR',
		[
			'key'     => $meta_key,
			'value'   => $contact_id,
			'compare' => '=',
		],
		[
			'key'     => $meta_key,
			'value'   => 'i:' . $contact_id . ';',
			'compare' => 'LIKE',
		],
		[
			'key'         => '^' . \preg_quote($meta_key, '/') . '__[0-9]+$',
			'compare_key' => 'REGEXP',
			'value'       => (string) $contact_id,
			'compare'     => '=',
		],
	];
}

function cmx_zugangsdaten_group_label(int $post_id): string {
	$terms = \wp_get_object_terms($post_id, CMX_ZUGANGSDATEN_GROUP_TAX, ['fields' => 'names']);
	if (\is_wp_error($terms) || empty($terms)) {
		return '';
	}
	return \implode(', ', \array_map('strval', $terms));
}

function cmx_zugangsdaten_latest_image_url(int $post_id): string {
	$doc_ids = (array) \get_post_meta($post_id, CMX_DOK_UPLOADS_META, true);
	$doc_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $doc_ids))));

	for ($i = \count($doc_ids) - 1; $i >= 0; $i--) {
		$doc_id = (int) $doc_ids[$i];
		if ((string) \get_post_type($doc_id) !== 'dokumente') {
			continue;
		}

		$file_rel = (string) \get_post_meta($doc_id, '_cmx_dokumente_file_path', true);
		if ($file_rel === '') {
			$attachment_id = (int) \get_post_meta($doc_id, '_cmx_dokumente_attachment_id', true);
			$file_rel = $attachment_id > 0
				? (string) \get_post_meta($attachment_id, '_wp_attached_file', true)
				: '';
		}
		$file_rel = \ltrim(\str_replace('\\', '/', $file_rel), '/');
		if ($file_rel === '') {
			continue;
		}

		$file_type = \wp_check_filetype($file_rel);
		if (!\str_starts_with((string) ($file_type['type'] ?? ''), 'image/')) {
			continue;
		}

		$url = cmx_dok_admin_upload_url_from_rel($file_rel);
		if ($url !== '') {
			return $url;
		}
	}

	return '';
}

\add_filter('manage_' . CMX_ZUGANGSDATEN_CPT . '_posts_columns', function (array $columns): array {
	return [
		'cb'                       => $columns['cb'] ?? '<input type="checkbox">',
		'title'                    => 'Bezeichnung',
		'cmx_zugangsdaten_category'=> 'Kategorie',
		'cmx_zugangsdaten_group'   => 'Gruppe',
		'cmx_zugangsdaten_fqdn'    => 'FQDN',
		'cmx_zugangsdaten_contact' => 'Kontakt',
		'cmx_zugangsdaten_links'   => 'Verknüpft',
		'cmx_zugangsdaten_modified'=> 'Geändert',
	];
}, 100);

\add_action('manage_' . CMX_ZUGANGSDATEN_CPT . '_posts_custom_column', function (string $column, int $post_id): void {
	if ($column === 'cmx_zugangsdaten_category') {
		$category = cmx_zugangsdaten_category_slug($post_id);
		$label = cmx_zugangsdaten_category_label($post_id);
		if ($category === '' || $label === '') {
			echo \esc_html($label);
			return;
		}
		$url = \add_query_arg([
			'post_type'                   => CMX_ZUGANGSDATEN_CPT,
			'cmx_zugangsdaten_category'   => $category,
		], \admin_url('edit.php'));
		echo '<a href="' . \esc_url($url) . '">' . \esc_html($label) . '</a>';
		return;
	}

	if ($column === 'cmx_zugangsdaten_group') {
		echo \esc_html(cmx_zugangsdaten_group_label($post_id));
		return;
	}

	if ($column === 'cmx_zugangsdaten_fqdn') {
		$category = cmx_zugangsdaten_category_slug($post_id);
		$field = $category === 'ssh-keys' ? 'servername' : ($category === 'ftp' ? 'hostname' : 'fqdn');
		$fqdn = \trim((string) \get_post_meta($post_id, cmx_zugangsdaten_meta_key($field), true));
		echo $fqdn === '' ? '' : \esc_html($fqdn);
		return;
	}

	if ($column === 'cmx_zugangsdaten_contact') {
		$contact_ids = \get_post_meta($post_id, cmx_zugangsdaten_meta_key('contact_id'), true);
		$contact_ids = \array_values(\array_unique(\array_filter(\array_map('intval', (array) $contact_ids))));
		foreach ($contact_ids as $contact_id) {
			if ((string) \get_post_type($contact_id) !== 'kontakte') {
				continue;
			}
			$title = \trim((string) \get_the_title($contact_id));
			$url = (string) \get_edit_post_link($contact_id, 'raw');
			echo '<div><a href="' . \esc_url($url) . '">' . \esc_html($title !== '' ? $title : ('Kontakt #' . $contact_id)) . '</a></div>';
		}
		return;
	}

	if ($column === 'cmx_zugangsdaten_links') {
		$ids = (array) \get_post_meta($post_id, CMX_ZUGANGSDATEN_LINKS_META, true);
		$ids = \array_values(\array_unique(\array_filter(\array_map('intval', $ids))));
		if ($ids === []) {
			return;
		}
		foreach ($ids as $linked_id) {
			if ((string) \get_post_type($linked_id) !== CMX_ZUGANGSDATEN_CPT) {
				continue;
			}
			$title = \trim((string) \get_the_title($linked_id));
			$url = (string) \get_edit_post_link($linked_id, 'raw');
			echo '<div><a href="' . \esc_url($url) . '">' . \esc_html($title !== '' ? $title : ('Zugangsdaten #' . $linked_id)) . '</a></div>';
		}
		return;
	}

	if ($column === 'cmx_zugangsdaten_modified') {
		$timestamp = \get_post_modified_time('U', false, $post_id);
		if ($timestamp) {
			echo \esc_html((string) \wp_date('d.m.Y H:i', $timestamp));
		}
	}
}, 10, 2);

\add_filter('manage_' . CMX_ZUGANGSDATEN_CPT . '_posts_columns', function (array $columns): array {
	$pdf_column = \defined(__NAMESPACE__ . '\\CMX_DOK_ADMIN_PDF_COLUMN')
		? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_ADMIN_PDF_COLUMN')
		: 'cmx_related_doc_pdf';
	$image_column = 'cmx_zugangsdaten_image';
	$ordered = [];

	foreach ($columns as $key => $label) {
		$ordered[$key] = $label;
		if ($key === $pdf_column) {
			$ordered[$image_column] = 'Bild';
		}
	}

	if (!isset($ordered[$image_column])) {
		$ordered[$image_column] = 'Bild';
	}
	return $ordered;
}, 3000);

\add_action('manage_' . CMX_ZUGANGSDATEN_CPT . '_posts_custom_column', function (string $column, int $post_id): void {
	if ($column !== 'cmx_zugangsdaten_image') {
		return;
	}

	$url = cmx_zugangsdaten_latest_image_url($post_id);
	if ($url === '') {
		return;
	}

	echo '<a href="' . \esc_url($url) . '" target="_blank" rel="noopener noreferrer" title="Zugeordnetes Bild anzeigen" class="cmx-zugangsdaten-image-icon" aria-label="Zugeordnetes Bild anzeigen"><span class="dashicons dashicons-format-image" aria-hidden="true"></span></a>';
}, 10, 2);

\add_filter('manage_edit-' . CMX_ZUGANGSDATEN_CPT . '_sortable_columns', function (array $columns): array {
	$columns['cmx_zugangsdaten_modified'] = 'modified';
	return $columns;
});

\add_filter('bulk_actions-edit-' . CMX_ZUGANGSDATEN_CPT, function (array $actions): array {
	unset($actions['edit']);
	return $actions;
});

\add_filter('disable_months_dropdown', function (bool $disabled, string $post_type): bool {
	return $post_type === CMX_ZUGANGSDATEN_CPT ? true : $disabled;
}, 10, 2);

\add_filter('admin_body_class', function (string $classes): string {
	$screen = \get_current_screen();
	$query = $GLOBALS['wp_query'] ?? null;
	if (
		$screen
		&& (string) $screen->post_type === CMX_ZUGANGSDATEN_CPT
		&& $query instanceof \WP_Query
		&& (int) $query->post_count === 0
	) {
		$classes .= ' cmx-zugangsdaten-empty-list';
	}
	return $classes;
});

\add_action('restrict_manage_posts', function (string $post_type, string $which): void {
	if ($post_type !== CMX_ZUGANGSDATEN_CPT || $which !== 'top') {
		return;
	}
	$current = \sanitize_key((string) ($_GET['cmx_zugangsdaten_category'] ?? ''));
	echo '<label class="screen-reader-text" for="cmx-zugangsdaten-category-filter">Nach Kategorie filtern</label>';
	echo '<select id="cmx-zugangsdaten-category-filter" name="cmx_zugangsdaten_category">';
	echo '<option value="">Alle Kategorien</option>';
	foreach (cmx_zugangsdaten_categories() as $slug => $label) {
		echo '<option value="' . \esc_attr($slug) . '"' . \selected($current, $slug, false) . '>' . \esc_html($label) . '</option>';
	}
	echo '</select>';

	$current_contact = (int) ($_GET['cmx_zugangsdaten_contact'] ?? 0);
	echo '<label class="screen-reader-text" for="cmx-zugangsdaten-contact-filter">Nach Kontakt filtern</label>';
	echo '<select id="cmx-zugangsdaten-contact-filter" name="cmx_zugangsdaten_contact">';
	echo '<option value="">Alle Kontakte</option>';
	foreach (cmx_zugangsdaten_contacts() as $contact_id) {
		$title = \trim((string) \get_the_title($contact_id));
		echo '<option value="' . \esc_attr((string) $contact_id) . '"' . \selected($current_contact, $contact_id, false) . '>' . \esc_html($title !== '' ? $title : ('Kontakt #' . $contact_id)) . '</option>';
	}
	echo '</select>';
}, 10, 2);

\add_action('pre_get_posts', function (\WP_Query $query): void {
	if (
		!\is_admin()
		|| !$query->is_main_query()
		|| (string) $query->get('post_type') !== CMX_ZUGANGSDATEN_CPT
	) {
		return;
	}

	$category = \sanitize_key((string) ($_GET['cmx_zugangsdaten_category'] ?? ''));
	if (isset(cmx_zugangsdaten_categories()[$category])) {
		$query->set('tax_query', [[
			'taxonomy' => CMX_ZUGANGSDATEN_CATEGORY_TAX,
			'field'    => 'slug',
			'terms'    => [$category],
		]]);
	}

	$contact_id = (int) ($_GET['cmx_zugangsdaten_contact'] ?? 0);
	if ($contact_id <= 0 || (string) \get_post_type($contact_id) !== 'kontakte') {
		return;
	}

	$meta_query = $query->get('meta_query');
	$meta_query = \is_array($meta_query) ? $meta_query : [];
	$meta_query[] = cmx_zugangsdaten_contact_filter_meta_query($contact_id);
	$query->set('meta_query', $meta_query);
});

\add_filter('posts_search', function (string $search, \WP_Query $query): string {
	if (
		!\is_admin()
		|| !$query->is_main_query()
		|| (string) $query->get('post_type') !== CMX_ZUGANGSDATEN_CPT
		|| \trim((string) $query->get('s')) === ''
		|| $search === ''
	) {
		return $search;
	}

	global $wpdb;

	$meta_key = cmx_zugangsdaten_meta_key('contact_id');
	$flat_key_pattern = '^' . \preg_quote($meta_key, '/') . '__[0-9]+$';
	$contact_title_like = '%' . $wpdb->esc_like(\trim((string) $query->get('s'))) . '%';
	$core_search = \preg_replace('/^\\s*AND\\s+/i', '', $search, 1);
	if (!\is_string($core_search) || $core_search === '') {
		return $search;
	}

	$contact_search = $wpdb->prepare(
		"EXISTS (
			SELECT 1
			FROM {$wpdb->postmeta} AS cmx_zugangsdaten_contact_meta
			INNER JOIN {$wpdb->posts} AS cmx_zugangsdaten_contact
				ON cmx_zugangsdaten_contact.ID = CAST(cmx_zugangsdaten_contact_meta.meta_value AS UNSIGNED)
			WHERE cmx_zugangsdaten_contact_meta.post_id = {$wpdb->posts}.ID
				AND cmx_zugangsdaten_contact_meta.meta_key REGEXP %s
				AND cmx_zugangsdaten_contact.post_type = 'kontakte'
				AND cmx_zugangsdaten_contact.post_title LIKE %s
		)",
		$flat_key_pattern,
		$contact_title_like
	);

	return ' AND ((' . $core_search . ') OR ' . $contact_search . ')';
}, 20, 2);

\add_action('admin_head-edit.php', function (): void {
	$screen = \get_current_screen();
	if (!$screen || (string) $screen->post_type !== CMX_ZUGANGSDATEN_CPT) {
		return;
	}
	echo '<style>
		body.post-type-' . \esc_html(CMX_ZUGANGSDATEN_CPT) . ' .tablenav.top .actions{position:relative;top:-3px}
		body.post-type-' . \esc_html(CMX_ZUGANGSDATEN_CPT) . '.cmx-zugangsdaten-empty-list .tablenav.top{margin-top:30px}
		body.post-type-' . \esc_html(CMX_ZUGANGSDATEN_CPT) . '.cmx-zugangsdaten-empty-list .tablenav.top .actions{top:0}
		body.post-type-' . \esc_html(CMX_ZUGANGSDATEN_CPT) . ' .wp-list-table .column-title{width:18%;min-width:180px;white-space:nowrap}
		body.post-type-' . \esc_html(CMX_ZUGANGSDATEN_CPT) . ' .wp-list-table td.column-title strong,
		body.post-type-' . \esc_html(CMX_ZUGANGSDATEN_CPT) . ' .wp-list-table td.column-title .row-title{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
		.column-cmx_zugangsdaten_category{width:9%}
		.column-cmx_zugangsdaten_group{width:8%}
		.column-cmx_zugangsdaten_fqdn{width:17%}
		.column-cmx_zugangsdaten_contact{width:12%}
		.column-cmx_zugangsdaten_links{width:12%}
		.column-cmx_zugangsdaten_modified{width:9%}
		.column-cmx_zugangsdaten_image{width:42px;text-align:center}
		body.post-type-' . \esc_html(CMX_ZUGANGSDATEN_CPT) . ' .wp-list-table td.column-cmx_related_doc_pdf,
		body.post-type-' . \esc_html(CMX_ZUGANGSDATEN_CPT) . ' .wp-list-table td.column-cmx_zugangsdaten_image{text-align:center;vertical-align:middle}
		body.post-type-' . \esc_html(CMX_ZUGANGSDATEN_CPT) . ' .cmx-related-doc-pdf-icon,
		.cmx-zugangsdaten-image-icon{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;min-height:22px;color:#111;text-decoration:none;vertical-align:middle}
		body.post-type-' . \esc_html(CMX_ZUGANGSDATEN_CPT) . ' .cmx-related-doc-pdf-icon .dashicons,
		.cmx-zugangsdaten-image-icon .dashicons{width:20px;height:20px;font-size:20px;line-height:20px}
	</style>';
});

\add_action('admin_footer-edit.php', function (): void {
	$screen = \get_current_screen();
	if (!$screen || (string) $screen->post_type !== CMX_ZUGANGSDATEN_CPT) {
		return;
	}
	?>
	<script>
	(function(){
		document.querySelectorAll('.subsubsub a[href*="cmx_zugangsdaten_export=1"]').forEach(function(link){
			link.addEventListener('click', function(){
				var selected = Array.prototype.map.call(
					document.querySelectorAll('input[name="post[]"]:checked'),
					function(input){ return input.value; }
				).filter(Boolean);
				if (!selected.length) return;
				var url = new URL(link.href, window.location.href);
				url.searchParams.delete('post[]');
				selected.forEach(function(postId){ url.searchParams.append('post[]', postId); });
				link.href = url.toString();
			});
		});
	})();
	</script>
	<?php
});
