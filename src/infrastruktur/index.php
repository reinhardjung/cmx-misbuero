<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_TAX_INFRASTRUKTUR = 'Playbooks';
const CMX_TAX_INFRASTRUKTUR_SERVER_TYPE = 'infrastruktur_server_type';
const CMX_INFRASTRUKTUR_KONTAKT_META = '_cmx_infrastruktur_kontakt_id';
const CMX_INFRASTRUKTUR_PLAYBOOK_META = '_cmx_infrastruktur_playbook';
const CMX_INFRASTRUKTUR_VERSION_META = '_cmx_infrastruktur_version';
const CMX_INFRASTRUKTUR_HOSTNAME_META = '_cmx_infrastruktur_hostname';
const CMX_INFRASTRUKTUR_FQDN_META = '_cmx_infrastruktur_fqdn';
const CMX_INFRASTRUKTUR_ADMIN_USERNAME_META = '_cmx_infrastruktur_admin_username';
const CMX_INFRASTRUKTUR_ADMIN_EMAIL_META = '_cmx_infrastruktur_admin_email';
const CMX_INFRASTRUKTUR_ADMIN_PASSWORD_META = '_cmx_infrastruktur_admin_password';
const CMX_INFRASTRUKTUR_CLOUD_INIT_META = '_cmx_infrastruktur_cloud_init';
const CMX_INFRASTRUKTUR_SKU_META = '_cmx_infrastruktur_sku';
const CMX_INFRASTRUKTUR_EK_META = '_cmx_infrastruktur_ek';
const CMX_INFRASTRUKTUR_VK_META = '_cmx_infrastruktur_vk';
const CMX_INFRASTRUKTUR_EINHEIT_META = '_cmx_infrastruktur_einheit';
const CMX_INFRASTRUKTUR_SERVER_SYSTEM_META = '_cmx_infrastruktur_server_system';
const CMX_INFRASTRUKTUR_SERVER_CPU_META = '_cmx_infrastruktur_server_cpu';
const CMX_INFRASTRUKTUR_SERVER_RAM_META = '_cmx_infrastruktur_server_ram';
const CMX_INFRASTRUKTUR_SERVER_STORAGE_META = '_cmx_infrastruktur_server_storage';
const CMX_INFRASTRUKTUR_SERVER_STORAGE_TYPE_META = '_cmx_infrastruktur_server_storage_type';
const CMX_INFRASTRUKTUR_SERVER_ID_META = '_cmx_infrastruktur_server_id';
const CMX_INFRASTRUKTUR_SERVER_IP_META = '_cmx_infrastruktur_server_ip';
const CMX_INFRASTRUKTUR_SERVER_FIREWALL_META = '_cmx_infrastruktur_server_firewall';
const CMX_INFRASTRUKTUR_SERVER_NETWORK_META = '_cmx_infrastruktur_server_network';
const CMX_INFRASTRUKTUR_SERVER_BACKUPS_META = '_cmx_infrastruktur_server_backups';
const CMX_INFRASTRUKTUR_SERVER_SSH_KEY_META = '_cmx_infrastruktur_server_ssh_key';
const CMX_INFRASTRUKTUR_ASSIGNED_META = '_cmx_infrastruktur_assigned_ids';
const CMX_INFRASTRUKTUR_INSTANCE_SOURCE_META = '_cmx_infrastruktur_instance_source_id';

require_once __DIR__ . '/instances.php';

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_server_types')) {
	function cmx_infrastruktur_server_types(): array {
		return [
			'vps'     => 'VPS',
			'vds'     => 'VDS',
			'bare'    => 'Bare',
			'storage' => 'Storage',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_server_type_tooltips')) {
	function cmx_infrastruktur_server_type_tooltips(): array {
		return [
			'vps'     => __('Virtueller Server mit flexibel geteilten Ressourcen', 'cmx-misbuero'),
			'vds'     => __('Virtueller Server mit fest zugewiesenen Ressourcen', 'cmx-misbuero'),
			'bare'    => __('Physischer Server exklusiv für Dich', 'cmx-misbuero'),
			'storage' => __('Speicherplatz für Backups, Dateien und große Datenmengen', 'cmx-misbuero'),
		];
	}
}

cmx_const_taxos('INFRASTRUKTUR', 'infrastruktur', CMX_TAX_INFRASTRUKTUR);

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_template_select_meta_box')) {
	function cmx_infrastruktur_template_select_meta_box(\WP_Post $post): void {
		$taxonomy = 'infrastruktur_playbooks';
		$term_ids = \wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'ids']);
		$selected = !\is_wp_error($term_ids) ? (int) ($term_ids[0] ?? 0) : 0;

		echo '<input type="hidden" name="tax_input[' . \esc_attr($taxonomy) . '][]" value="0">';
		\wp_dropdown_categories([
			'taxonomy'          => $taxonomy,
			'name'              => 'tax_input[' . $taxonomy . '][]',
			'id'                => 'infrastruktur-playbook',
			'class'             => 'widefat',
			'show_option_none'  => '– Vorlage auswählen –',
			'option_none_value' => '0',
			'hide_empty'        => false,
			'hierarchical'      => true,
			'orderby'           => 'name',
			'selected'          => $selected,
			'value_field'       => 'term_id',
		]);
	}
}

\add_action('init', function (): void {
	cmx_create_taxo(
		'infrastruktur',
		'Vorlage',
		'Playbooks',
		__NAMESPACE__ . '\\cmx_infrastruktur_template_select_meta_box',
		true,
		[
			'label'        => 'Vorlage',
			'show_ui'      => true,
			'show_in_menu' => false,
			'capabilities' => [
				'manage_terms' => 'do_not_allow',
				'edit_terms'   => 'do_not_allow',
				'delete_terms' => 'do_not_allow',
				'assign_terms' => 'edit_posts',
			],
			'labels'       => [
				'name'          => 'Vorlagen',
				'singular_name' => 'Vorlage',
				'menu_name'     => 'Vorlagen',
				'not_found'     => 'Keine Vorlagen gefunden',
				'no_terms'      => 'Keine Vorlagen',
			],
		]
	);
}, 15);

\add_action('admin_init', function (): void {
	cmx_seed_taxo('Infrastruktur', CMX_TAX_INFRASTRUKTUR, 'infrastruktur');
});

\add_action('init', function (): void {
	if (!\taxonomy_exists(CMX_TAX_INFRASTRUKTUR_SERVER_TYPE)) {
		\register_taxonomy(CMX_TAX_INFRASTRUKTUR_SERVER_TYPE, ['infrastruktur'], [
			'labels' => [
				'name'          => __('Server-Typen', 'cmx-misbuero'),
				'singular_name' => __('Server-Typ', 'cmx-misbuero'),
				'menu_name'     => __('Server-Typen', 'cmx-misbuero'),
			],
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => false,
			'show_admin_column' => false,
			'show_in_rest'      => true,
			'hierarchical'      => true,
			'meta_box_cb'       => false,
			'rewrite'           => false,
			'query_var'         => false,
			'capabilities'      => [
				'manage_terms' => 'do_not_allow',
				'edit_terms'   => 'do_not_allow',
				'delete_terms' => 'do_not_allow',
				'assign_terms' => 'edit_posts',
			],
		]);
	}

	$dedicated = \get_term_by('slug', 'dedicated', CMX_TAX_INFRASTRUKTUR_SERVER_TYPE);
	$bare = \get_term_by('slug', 'bare', CMX_TAX_INFRASTRUKTUR_SERVER_TYPE);
	if ($dedicated instanceof \WP_Term && !($bare instanceof \WP_Term)) {
		// Bestehende Zuordnungen bleiben beim Umbenennen über dieselbe Term-ID erhalten.
		\wp_update_term($dedicated->term_id, CMX_TAX_INFRASTRUKTUR_SERVER_TYPE, [
			'name' => 'Bare',
			'slug' => 'bare',
		]);
	}

	foreach (cmx_infrastruktur_server_types() as $slug => $name) {
		if (!\term_exists($slug, CMX_TAX_INFRASTRUKTUR_SERVER_TYPE)) {
			\wp_insert_term($name, CMX_TAX_INFRASTRUKTUR_SERVER_TYPE, ['slug' => $slug]);
		}
	}
}, 16);

\add_action('add_meta_boxes_infrastruktur', function (): void {
	\add_meta_box(
		'cmx_infrastruktur_server_box',
		__('Server', 'cmx-misbuero'),
		__NAMESPACE__ . '\\cmx_infrastruktur_render_server_metabox',
		'infrastruktur',
		'normal',
		'high'
	);
}, 1);

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_ip_lookup_url')) {
	function cmx_infrastruktur_ip_lookup_url(string $ip = ''): string {
		$url = \add_query_arg([
			'action'   => 'cmx_infrastruktur_ip_lookup',
			'_wpnonce' => \wp_create_nonce('cmx_infrastruktur_ip_lookup'),
		], \admin_url('admin-ajax.php'));

		return $ip === '' ? $url : \add_query_arg('ip', $ip, $url);
	}
}

\add_action('wp_ajax_cmx_infrastruktur_ip_lookup', function (): void {
	if (!\current_user_can('edit_posts')) {
		\wp_send_json(['status' => 'fail', 'message' => 'Keine Berechtigung.'], 403);
	}

	\nocache_headers();
	if (!\check_ajax_referer('cmx_infrastruktur_ip_lookup', '_wpnonce', false)) {
		\wp_send_json(['status' => 'fail', 'message' => 'Sicherheitsprüfung fehlgeschlagen.'], 403);
	}

	$ip = isset($_GET['ip']) ? \trim((string) \wp_unslash($_GET['ip'])) : '';
	if (\filter_var($ip, \FILTER_VALIDATE_IP) === false) {
		\wp_send_json(['status' => 'fail', 'message' => 'Ungültige IP-Adresse.'], 400);
	}

	$response = \wp_remote_get('http://ip-api.com/json/' . \rawurlencode($ip), [
		'timeout'     => 10,
		'redirection' => 0,
	]);
	if (\is_wp_error($response)) {
		\wp_send_json(['status' => 'fail', 'message' => 'IP-Abfrage fehlgeschlagen.'], 502);
	}

	$status = (int) \wp_remote_retrieve_response_code($response);
	$body = (string) \wp_remote_retrieve_body($response);
	if ($body === '') {
		\wp_send_json(['status' => 'fail', 'message' => 'IP-Abfrage lieferte keine Antwort.'], 502);
	}

	\status_header($status >= 100 && $status <= 599 ? $status : 200);
	\header('Content-Type: application/json; charset=utf-8');
	echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted JSON response from the fixed ip-api.com endpoint.
	\wp_die();
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_server_numeric_value')) {
	function cmx_infrastruktur_server_numeric_value($value): string {
		$value = \trim((string) $value);
		if ($value === '') {
			return '';
		}
		if (!\preg_match('/-?\d+(?:[.,]\d+)?/', $value, $match)) {
			return '';
		}

		$number = (float) \str_replace(',', '.', $match[0]);
		$number = \max(0.0, $number);

		return \rtrim(\rtrim(\number_format($number, 3, '.', ''), '0'), '.');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_render_server_metabox')) {
	function cmx_infrastruktur_render_server_metabox(\WP_Post $post): void {
		$type_ids = \wp_get_object_terms($post->ID, CMX_TAX_INFRASTRUKTUR_SERVER_TYPE, ['fields' => 'ids']);
		$selected_type = !\is_wp_error($type_ids) ? (int) ($type_ids[0] ?? 0) : 0;
		$server_types = [];
		$server_type_tooltips = cmx_infrastruktur_server_type_tooltips();
		$default_type_id = 0;
		foreach (cmx_infrastruktur_server_types() as $slug => $label) {
			$term = \get_term_by('slug', $slug, CMX_TAX_INFRASTRUKTUR_SERVER_TYPE);
			if ($term instanceof \WP_Term) {
				$server_types[] = [
					'id'      => (int) $term->term_id,
					'label'   => $label,
					'tooltip' => (string) ($server_type_tooltips[$slug] ?? ''),
				];
				if ($slug === 'vps') {
					$default_type_id = (int) $term->term_id;
				}
			}
		}
		$selected_type_term = $selected_type > 0
			? \get_term($selected_type, CMX_TAX_INFRASTRUKTUR_SERVER_TYPE)
			: null;
		if ($selected_type_term instanceof \WP_Term && $selected_type_term->slug === 'dedicated') {
			$bare_term = \get_term_by('slug', 'bare', CMX_TAX_INFRASTRUKTUR_SERVER_TYPE);
			$selected_type = $bare_term instanceof \WP_Term ? (int) $bare_term->term_id : $selected_type;
		}
		if (!\in_array($selected_type, \array_column($server_types, 'id'), true)) {
			$selected_type = $default_type_id;
		}
		$system = \sanitize_key((string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_SERVER_SYSTEM_META, true));
		if (!\in_array($system, ['linux', 'windows'], true)) {
			$system = 'linux';
		}
		$stored_storage = (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_SERVER_STORAGE_META, true);
		$values = [
			'cpu'      => cmx_infrastruktur_server_numeric_value(\get_post_meta($post->ID, CMX_INFRASTRUKTUR_SERVER_CPU_META, true)),
			'ram'      => cmx_infrastruktur_server_numeric_value(\get_post_meta($post->ID, CMX_INFRASTRUKTUR_SERVER_RAM_META, true)),
			'storage'  => cmx_infrastruktur_server_numeric_value($stored_storage),
			'server_id'=> (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_SERVER_ID_META, true),
			'ip'       => (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_SERVER_IP_META, true),
		];
		$storage_type = \strtolower(\sanitize_key((string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_SERVER_STORAGE_TYPE_META, true)));
		if (!\in_array($storage_type, ['hdd', 'ssd', 'nvme'], true)) {
			$stored_storage_lower = \strtolower($stored_storage);
			$storage_type = \str_contains($stored_storage_lower, 'nvme')
				? 'nvme'
				: (\str_contains($stored_storage_lower, 'ssd')
					? 'ssd'
					: (\str_contains($stored_storage_lower, 'hdd') ? 'hdd' : ''));
		}
		$firewall = (int) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_SERVER_FIREWALL_META, true) === 1;
		$networks = \get_post_meta($post->ID, CMX_INFRASTRUKTUR_SERVER_NETWORK_META, true);
		$networks = \is_array($networks)
			? \array_values(\array_intersect(['public', 'private'], \array_map('sanitize_key', $networks)))
			: [];
		$backups = \get_post_meta($post->ID, CMX_INFRASTRUKTUR_SERVER_BACKUPS_META, true);
		$backups = \is_array($backups) ? $backups : [];
		foreach (['taeglich', 'woechentlich', 'monatlich'] as $interval) {
			$backups[$interval] = [
				'aktiv'  => !empty($backups[$interval]['aktiv']) ? 1 : 0,
				'anzahl' => isset($backups[$interval]['anzahl']) ? \max(0, (int) $backups[$interval]['anzahl']) : 0,
			];
		}
		$dark_blue = \function_exists(__NAMESPACE__ . '\\cmx_admin_global_color')
			? cmx_admin_global_color('dunkelblau', '#1D508F')
			: '#1D508F';
		$server_url = $values['server_id'] === ''
			? '#'
			: 'https://hosttech.cloud/Server/' . \rawurlencode($values['server_id']);
		$ip_lookup_url = $values['ip'] === ''
			? '#'
			: cmx_infrastruktur_ip_lookup_url($values['ip']);
		$ip_lookup_base_url = cmx_infrastruktur_ip_lookup_url();

		\wp_nonce_field('cmx_infrastruktur_server_save', 'cmx_infrastruktur_server_nonce');
		?>
		<style>
			#cmx_infrastruktur_server_box .inside {
				margin-top: 0;
				padding: 18px 20px 20px;
			}

			.cmx-infrastruktur-server-grid {
				display: grid;
				grid-template-columns: repeat(4, minmax(180px, 1fr));
				gap: 16px;
			}

			.cmx-infrastruktur-server-field {
				display: grid;
				gap: 5px;
				margin: 0;
			}

			.cmx-infrastruktur-server-field > span,
			.cmx-infrastruktur-server-field > label,
			.cmx-infrastruktur-server-firewall-card > span,
			.cmx-infrastruktur-server-network-card > span,
			.cmx-infrastruktur-server-backup-name {
				color: #1d2327;
				font-weight: 600;
			}

			.cmx-infrastruktur-server-id-link,
			.cmx-infrastruktur-server-ip-link {
				width: fit-content;
				color: <?php echo \esc_attr($dark_blue); ?>;
				font-weight: 600;
				text-decoration: none;
				cursor: pointer;
			}

			.cmx-infrastruktur-server-settings-link,
			.cmx-infrastruktur-server-settings-link:visited,
			.cmx-infrastruktur-server-settings-link:focus {
				color: <?php echo \esc_attr($dark_blue); ?>;
				font-weight: 600;
				text-decoration: none;
			}

			.cmx-infrastruktur-server-settings-link:hover {
				color: <?php echo \esc_attr($dark_blue); ?>;
				text-decoration: underline;
			}

			.cmx-infrastruktur-server-id-link:hover,
			.cmx-infrastruktur-server-id-link:focus,
			.cmx-infrastruktur-server-ip-link:hover,
			.cmx-infrastruktur-server-ip-link:focus {
				color: <?php echo \esc_attr($dark_blue); ?>;
				text-decoration: underline;
				text-underline-offset: 2px;
			}

			.cmx-infrastruktur-server-field input,
			.cmx-infrastruktur-server-field select,
			.cmx-infrastruktur-server-backup-count {
				width: 100%;
				min-height: 40px;
				margin: 0;
				padding-right: 12px;
				padding-left: 12px;
				border: 1px solid #c3c4c7;
				border-radius: 8px;
				background-color: #fff;
				box-shadow: none;
			}

			.cmx-infrastruktur-server-field input:focus,
			.cmx-infrastruktur-server-field select:focus,
			.cmx-infrastruktur-server-backup-count:focus {
				border-color: #2271b1;
				box-shadow: 0 0 0 1px #2271b1;
				outline: 0;
			}

			.cmx-infrastruktur-server-hardware-row {
				display: grid;
				grid-template-columns: 1fr 1fr 2fr;
				gap: 12px;
			}

			.cmx-infrastruktur-server-storage-row {
				display: grid;
				grid-template-columns: minmax(100px, 1fr) auto;
				align-items: stretch;
				gap: 8px;
			}

			.cmx-infrastruktur-server-storage-types {
				display: inline-flex;
				align-items: stretch;
				overflow: hidden;
				border: 1px solid #c3c4c7;
				border-radius: 8px;
				background: #fff;
			}

			.cmx-infrastruktur-server-radio-group {
				display: flex;
				width: 100%;
			}

			.cmx-infrastruktur-server-radio-group .cmx-infrastruktur-server-storage-type {
				flex: 1 1 0;
			}

			.cmx-infrastruktur-server-storage-type {
				position: relative;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				min-width: 50px;
				padding: 0 9px;
				cursor: pointer;
			}

			.cmx-infrastruktur-server-storage-type + .cmx-infrastruktur-server-storage-type {
				border-left: 1px solid #dcdcde;
			}

			.cmx-infrastruktur-server-storage-type input {
				position: absolute;
				width: 1px;
				height: 1px;
				min-width: 0;
				min-height: 0;
				margin: 0;
				padding: 0;
				overflow: hidden;
				opacity: 0;
			}

			.cmx-infrastruktur-server-storage-type span {
				font-size: 12px;
				font-weight: 600;
			}

			.cmx-infrastruktur-server-storage-type:has(input:checked) {
				color: #fff;
				background: #2271b1;
			}

			.cmx-infrastruktur-server-storage-type:has(input:focus-visible) {
				box-shadow: inset 0 0 0 2px #fff, 0 0 0 1px #2271b1;
			}

			.cmx-infrastruktur-server-firewall {
				display: flex;
				align-items: center;
				gap: 10px;
				min-height: 40px;
			}

			.cmx-infrastruktur-server-ssh-command-menu {
				position: relative;
				display: inline-flex;
				align-items: center;
				margin-left: 24px;
			}

			.cmx-infrastruktur-server-ssh-config-button {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 32px;
				height: 32px;
				margin: 0;
				padding: 0;
				border: 0 !important;
				background: transparent !important;
				box-shadow: none !important;
				color: <?php echo \esc_attr($dark_blue); ?> !important;
				text-decoration: none !important;
			}

			.cmx-infrastruktur-server-ssh-config-button:hover,
			.cmx-infrastruktur-server-ssh-config-button:focus,
			.cmx-infrastruktur-server-ssh-config-button:active {
				border: 0 !important;
				background: transparent !important;
				box-shadow: none !important;
				color: <?php echo \esc_attr($dark_blue); ?> !important;
				text-decoration: none !important;
			}

			.cmx-infrastruktur-server-ssh-config-button .dashicons {
				width: 22px;
				height: 22px;
				font-size: 22px;
			}

			.cmx-infrastruktur-server-ssh-config-select {
				position: absolute;
				z-index: 1001;
				top: calc(100% + 4px);
				left: 0;
				width: 190px;
				min-width: 190px;
				max-width: 190px;
				margin: 0;
			}

			.cmx-infrastruktur-server-ssh-config-select[hidden] {
				display: none !important;
			}

			.cmx-infrastruktur-server-ssh-config-status {
				position: absolute;
				top: calc(100% + 6px);
				left: 0;
				width: max-content;
				color: #646970;
				font-size: 12px;
				white-space: nowrap;
			}

			.cmx-infrastruktur-server-toggle {
				position: relative;
				display: inline-flex;
				align-items: center;
				flex: 0 0 auto;
				cursor: pointer;
			}

			.cmx-infrastruktur-server-toggle input {
				position: absolute;
				width: 1px;
				height: 1px;
				overflow: hidden;
				opacity: 0;
			}

			.cmx-infrastruktur-server-toggle-ui {
				position: relative;
				display: block;
				width: 42px;
				height: 24px;
				border-radius: 999px;
				background: #a7aaad;
				transition: background .15s ease;
			}

			.cmx-infrastruktur-server-toggle-ui::after {
				position: absolute;
				top: 3px;
				left: 3px;
				width: 18px;
				height: 18px;
				border-radius: 50%;
				background: #fff;
				box-shadow: 0 1px 3px rgba(0, 0, 0, .25);
				content: "";
				transition: transform .15s ease;
			}

			.cmx-infrastruktur-server-toggle input:checked + .cmx-infrastruktur-server-toggle-ui {
				background: #2271b1;
			}

			.cmx-infrastruktur-server-toggle input:checked + .cmx-infrastruktur-server-toggle-ui::after {
				transform: translateX(18px);
			}

			.cmx-infrastruktur-server-toggle input:focus-visible + .cmx-infrastruktur-server-toggle-ui {
				box-shadow: 0 0 0 2px #fff, 0 0 0 4px #2271b1;
			}

			.cmx-infrastruktur-server-details-row {
				display: grid;
				grid-template-columns: 2fr 1fr;
				gap: 12px;
				margin-top: 16px;
				padding-top: 16px;
			}

			.cmx-infrastruktur-server-firewall-card,
			.cmx-infrastruktur-server-network-card {
				display: grid;
				align-content: start;
				gap: 8px;
			}

			.cmx-infrastruktur-server-security-column {
				display: grid;
				grid-template-columns: auto minmax(0, 1fr);
				align-items: start;
				gap: 32px;
				margin-left: 3ch;
			}

			.cmx-infrastruktur-server-network-options {
				display: flex;
				align-items: center;
				gap: 16px;
				min-height: 40px;
			}

			.cmx-infrastruktur-server-network-option {
				display: inline-flex;
				align-items: center;
				gap: 7px;
				font-weight: 600;
				cursor: pointer;
			}

			.cmx-infrastruktur-server-network-option input {
				width: 16px;
				height: 16px;
				margin: 0;
				border-radius: 4px;
				accent-color: #2271b1;
			}

			.cmx-infrastruktur-server-backup-grid {
				display: grid;
				grid-template-columns: repeat(3, minmax(180px, 1fr));
				gap: 12px;
				margin-top: 20px;
			}

			.cmx-infrastruktur-server-backup {
				display: grid;
				grid-template-columns: auto 1fr;
				align-items: center;
				gap: 8px 10px;
				padding: 12px;
				border: 1px solid #dcdcde;
				border-radius: 8px;
				background: #f6f7f7;
			}

			.cmx-infrastruktur-server-backup-count {
				grid-column: 1 / -1;
			}

			.cmx-infrastruktur-server-backup-count:disabled {
				color: #8c8f94;
				background: #f0f0f1;
			}

			@media (max-width: 960px) {
				.cmx-infrastruktur-server-grid,
				.cmx-infrastruktur-server-details-row,
				.cmx-infrastruktur-server-backup-grid {
					grid-template-columns: 1fr 1fr;
				}

				.cmx-infrastruktur-server-hardware-row {
					grid-column: 1 / -1;
					grid-template-columns: 1fr 1fr 2fr;
				}

			}

			@media (max-width: 782px) {
				.cmx-infrastruktur-server-grid,
				.cmx-infrastruktur-server-details-row,
				.cmx-infrastruktur-server-backup-grid {
					grid-template-columns: 1fr;
				}

				.cmx-infrastruktur-server-hardware-row {
					grid-template-columns: 1fr 1fr;
				}

				.cmx-infrastruktur-server-hardware-storage {
					grid-column: 1 / -1;
				}
			}
		</style>

		<div class="cmx-infrastruktur-server">
			<div class="cmx-infrastruktur-server-grid">
				<div class="cmx-infrastruktur-server-field">
					<span><?php echo \esc_html__('Typ', 'cmx-misbuero'); ?></span>
					<div class="cmx-infrastruktur-server-storage-types cmx-infrastruktur-server-radio-group" role="radiogroup" aria-label="<?php echo \esc_attr__('Server-Typ', 'cmx-misbuero'); ?>">
						<?php foreach ($server_types as $server_type) : ?>
							<label class="cmx-infrastruktur-server-storage-type" title="<?php echo \esc_attr($server_type['tooltip']); ?>">
								<input type="radio" name="cmx_infrastruktur_server_type" value="<?php echo \esc_attr((string) $server_type['id']); ?>" aria-label="<?php echo \esc_attr($server_type['label'] . ': ' . $server_type['tooltip']); ?>" <?php \checked($selected_type, $server_type['id']); ?>>
								<span><?php echo \esc_html($server_type['label']); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="cmx-infrastruktur-server-field">
					<span><?php echo \esc_html__('System', 'cmx-misbuero'); ?></span>
					<div class="cmx-infrastruktur-server-storage-types cmx-infrastruktur-server-radio-group" role="radiogroup" aria-label="<?php echo \esc_attr__('System', 'cmx-misbuero'); ?>">
						<?php foreach (['linux' => 'Linux', 'windows' => 'Windows'] as $system_value => $system_label) : ?>
							<label class="cmx-infrastruktur-server-storage-type">
								<input type="radio" name="cmx_infrastruktur_server_system" value="<?php echo \esc_attr($system_value); ?>" <?php \checked($system, $system_value); ?>>
								<span><?php echo \esc_html($system_label); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="cmx-infrastruktur-server-field">
					<a
						id="cmx-infrastruktur-server-id-link"
						class="cmx-infrastruktur-server-id-link"
						href="<?php echo \esc_url($server_url); ?>"
						target="_blank"
						rel="noopener noreferrer"
					><?php echo \esc_html__('ID', 'cmx-misbuero'); ?></a>
					<input type="text" id="cmx-infrastruktur-server-id" name="cmx_infrastruktur_server_id" value="<?php echo \esc_attr($values['server_id']); ?>" autocomplete="off">
				</div>

				<div class="cmx-infrastruktur-server-field">
					<a
						id="cmx-infrastruktur-server-ip-link"
						class="cmx-infrastruktur-server-ip-link"
						href="<?php echo \esc_url($ip_lookup_url); ?>"
						target="_blank"
						rel="noopener noreferrer"
					><?php echo \esc_html__('IP-Adresse', 'cmx-misbuero'); ?></a>
					<input type="text" id="cmx-infrastruktur-server-ip" name="cmx_infrastruktur_server_ip" value="<?php echo \esc_attr($values['ip']); ?>" placeholder="" spellcheck="false" autocapitalize="none" autocomplete="off">
				</div>
			</div>

			<div class="cmx-infrastruktur-server-details-row">
				<div class="cmx-infrastruktur-server-hardware-row">
					<label class="cmx-infrastruktur-server-field" for="cmx-infrastruktur-server-cpu">
						<span><?php echo \esc_html__('CPU', 'cmx-misbuero'); ?></span>
						<input type="number" id="cmx-infrastruktur-server-cpu" name="cmx_infrastruktur_server_cpu" value="<?php echo \esc_attr($values['cpu']); ?>" placeholder="4" min="0" step="any" inputmode="decimal" autocomplete="off">
					</label>

					<label class="cmx-infrastruktur-server-field" for="cmx-infrastruktur-server-ram">
						<span><?php echo \esc_html__('RAM', 'cmx-misbuero'); ?></span>
						<input type="number" id="cmx-infrastruktur-server-ram" name="cmx_infrastruktur_server_ram" value="<?php echo \esc_attr($values['ram']); ?>" placeholder="16" min="0" step="any" inputmode="decimal" autocomplete="off">
					</label>

					<div class="cmx-infrastruktur-server-field cmx-infrastruktur-server-hardware-storage">
						<label for="cmx-infrastruktur-server-storage"><?php echo \esc_html__('Festplatte', 'cmx-misbuero'); ?></label>
						<div class="cmx-infrastruktur-server-storage-row">
							<input type="number" id="cmx-infrastruktur-server-storage" name="cmx_infrastruktur_server_storage" value="<?php echo \esc_attr($values['storage']); ?>" placeholder="200" min="0" step="any" inputmode="decimal" autocomplete="off">
							<div class="cmx-infrastruktur-server-storage-types" role="radiogroup" aria-label="<?php echo \esc_attr__('Festplattentyp', 'cmx-misbuero'); ?>">
								<?php foreach (['hdd' => 'HDD', 'ssd' => 'SSD', 'nvme' => 'NVMe'] as $type_value => $type_label) : ?>
									<label class="cmx-infrastruktur-server-storage-type">
										<input type="radio" name="cmx_infrastruktur_server_storage_type" value="<?php echo \esc_attr($type_value); ?>" <?php \checked($storage_type, $type_value); ?>>
										<span><?php echo \esc_html($type_label); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				</div>

				<div class="cmx-infrastruktur-server-security-column">
					<div class="cmx-infrastruktur-server-network-card">
						<span><?php echo \esc_html__('Netzwerke', 'cmx-misbuero'); ?></span>
						<div class="cmx-infrastruktur-server-network-options">
							<?php foreach (['public' => 'Public', 'private' => 'Private'] as $network_value => $network_label) : ?>
								<label class="cmx-infrastruktur-server-network-option">
									<input
										type="checkbox"
										name="cmx_infrastruktur_server_network[]"
										value="<?php echo \esc_attr($network_value); ?>"
										<?php \checked(\in_array($network_value, $networks, true)); ?>
									>
									<span><?php echo \esc_html($network_label); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="cmx-infrastruktur-server-firewall-card">
						<span><?php echo \esc_html__('Firewall', 'cmx-misbuero'); ?></span>
						<div class="cmx-infrastruktur-server-firewall">
							<label class="cmx-infrastruktur-server-toggle">
								<input type="checkbox" id="cmx-infrastruktur-server-firewall" name="cmx_infrastruktur_server_firewall" value="1" <?php \checked($firewall); ?>>
								<span class="cmx-infrastruktur-server-toggle-ui" aria-hidden="true"></span>
							</label>
							<span id="cmx-infrastruktur-server-firewall-label"><?php echo $firewall ? \esc_html__('Ja', 'cmx-misbuero') : \esc_html__('Nein', 'cmx-misbuero'); ?></span>
							<span class="cmx-infrastruktur-server-ssh-command-menu">
								<button type="button" class="button-link cmx-infrastruktur-server-ssh-config-button" data-cmx-infrastruktur-ssh-config-open aria-label="<?php echo \esc_attr__('SSH-Befehl kopieren', 'cmx-misbuero'); ?>" title="<?php echo \esc_attr__('SSH-Befehl kopieren', 'cmx-misbuero'); ?>">
									<span class="dashicons dashicons-migrate" aria-hidden="true"></span>
								</button>
								<select class="cmx-infrastruktur-server-ssh-config-select" data-cmx-infrastruktur-ssh-config-os aria-label="<?php echo \esc_attr__('Betriebssystem auswählen', 'cmx-misbuero'); ?>" hidden>
									<option value=""><?php echo \esc_html__('– Windows oder Mac –', 'cmx-misbuero'); ?></option>
									<option value="windows"><?php echo \esc_html__('Windows', 'cmx-misbuero'); ?></option>
									<option value="mac"><?php echo \esc_html__('Mac', 'cmx-misbuero'); ?></option>
								</select>
								<span class="cmx-infrastruktur-server-ssh-config-status" data-cmx-infrastruktur-ssh-config-status aria-live="polite"></span>
							</span>
						</div>
					</div>
				</div>

			</div>

			<div class="cmx-infrastruktur-server-backup-grid">
				<?php foreach ([
					'taeglich'      => __('Täglich', 'cmx-misbuero'),
					'woechentlich'  => __('Wöchentlich', 'cmx-misbuero'),
					'monatlich'     => __('Monatlich', 'cmx-misbuero'),
				] as $interval => $label) : ?>
					<div class="cmx-infrastruktur-server-backup" data-cmx-server-backup>
						<label class="cmx-infrastruktur-server-toggle">
							<input
								type="checkbox"
								name="cmx_infrastruktur_server_backups[<?php echo \esc_attr($interval); ?>][aktiv]"
								value="1"
								data-cmx-server-backup-toggle
								<?php \checked(!empty($backups[$interval]['aktiv'])); ?>
							>
							<span class="cmx-infrastruktur-server-toggle-ui" aria-hidden="true"></span>
						</label>
						<span class="cmx-infrastruktur-server-backup-name"><?php echo \esc_html($label); ?></span>
						<input
							type="number"
							class="cmx-infrastruktur-server-backup-count"
							name="cmx_infrastruktur_server_backups[<?php echo \esc_attr($interval); ?>][anzahl]"
							value="<?php echo \esc_attr((string) $backups[$interval]['anzahl']); ?>"
							min="0"
							step="1"
							aria-label="<?php echo \esc_attr(sprintf(__('Anzahl %s', 'cmx-misbuero'), $label)); ?>"
							data-cmx-server-backup-count
							<?php echo !empty($backups[$interval]['aktiv']) ? '' : 'disabled'; ?>
						>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<script>
		(function () {
			var serverId = document.getElementById("cmx-infrastruktur-server-id");
			var serverIdLink = document.getElementById("cmx-infrastruktur-server-id-link");
			if (serverId) {
				var syncServerIdLink = function () {
					var id = serverId.value.trim();
					if (serverIdLink) {
						serverIdLink.href = id === ""
							? "#"
							: "https://hosttech.cloud/Server/" + encodeURIComponent(id);
						serverIdLink.setAttribute("aria-disabled", id === "" ? "true" : "false");
					}
				};
				if (serverIdLink) {
					serverIdLink.addEventListener("click", function (event) {
						if (serverId.value.trim() === "") {
							event.preventDefault();
						}
					});
				}
				serverId.addEventListener("input", syncServerIdLink);
				syncServerIdLink();
			}

			var serverIp = document.getElementById("cmx-infrastruktur-server-ip");
			var serverIpLink = document.getElementById("cmx-infrastruktur-server-ip-link");
			if (serverIp && serverIpLink) {
				var ipLookupBaseUrl = <?php echo \wp_json_encode($ip_lookup_base_url); ?>;
				var syncServerIpLink = function () {
					var ip = serverIp.value.trim() || serverIp.placeholder.trim();
					serverIpLink.href = ip === ""
						? "#"
						: ipLookupBaseUrl + "&ip=" + encodeURIComponent(ip);
					serverIpLink.setAttribute("aria-disabled", ip === "" ? "true" : "false");
				};
				serverIpLink.addEventListener("click", function (event) {
					if ((serverIp.value.trim() || serverIp.placeholder.trim()) === "") {
						event.preventDefault();
					}
				});
				serverIp.addEventListener("input", syncServerIpLink);
				syncServerIpLink();
				if (serverIp.placeholder.trim() === "") {
					fetch("https://api.ipify.org?format=json", {cache: "no-store"})
						.then(function (response) { return response.ok ? response.json() : null; })
						.then(function (data) {
							if (data && typeof data.ip === "string") {
								serverIp.placeholder = data.ip.trim();
								syncServerIpLink();
							}
						})
						.catch(function () {});
				}
			}

			var firewall = document.getElementById("cmx-infrastruktur-server-firewall");
			var firewallLabel = document.getElementById("cmx-infrastruktur-server-firewall-label");
			if (firewall && firewallLabel) {
				var syncFirewall = function () {
					firewallLabel.textContent = firewall.checked ? "Ja" : "Nein";
				};
				firewall.addEventListener("change", syncFirewall);
				syncFirewall();
			}

			var sshConfigOpen = document.querySelector("[data-cmx-infrastruktur-ssh-config-open]");
			var sshConfigSelect = document.querySelector("[data-cmx-infrastruktur-ssh-config-os]");
			var sshConfigStatus = document.querySelector("[data-cmx-infrastruktur-ssh-config-status]");
			if (sshConfigOpen && sshConfigSelect) {
				var sshUsername = document.getElementById("cmx-cloud-init-admin-username");
				var sshTarget = document.getElementById("cmx-infrastruktur-server-ip");
				var sshPublicKey = document.getElementById("cmx-cloud-init-admin-public-key");
				var safeSshValue = function (value, fallback) {
					var cleaned = String(value || "").trim().replace(/[^A-Za-z0-9._@:-]+/g, "-").replace(/^-+|-+$/g, "");
					return cleaned || fallback;
				};
				var sshFieldValue = function (input, usePlaceholder) {
					if (!input) {
						return "";
					}
					var value = String(input.value || "").trim();
					return value || (usePlaceholder ? String(input.placeholder || "").trim() : "");
				};
				var sshKeyName = function () {
					if (!sshPublicKey || sshPublicKey.selectedIndex < 0 || !sshPublicKey.value) {
						return "";
					}
					return safeSshValue(sshPublicKey.options[sshPublicKey.selectedIndex].text, "");
				};
				var buildSshCommand = function (os, values) {
					if (os === "windows") {
						var backslash = String.fromCharCode(92);
						var keyPath = "$env:USERPROFILE" + backslash + ".ssh" + backslash + values.keyName;
						return "ssh -i \"" + keyPath + "\" " + values.username + "@" + values.target;
					}
					return "ssh -i ~/.ssh/" + values.keyName + " " + values.username + "@" + values.target;
				};
				var fallbackSshCopy = function (text) {
					var area = document.createElement("textarea");
					area.value = text;
					area.setAttribute("readonly", "");
					area.style.position = "fixed";
					area.style.top = "0";
					area.style.left = "0";
					area.style.width = "1px";
					area.style.height = "1px";
					area.style.opacity = "0";
					document.body.appendChild(area);
					area.focus();
					area.select();
					area.setSelectionRange(0, area.value.length);
					var copied = false;
					try {
						copied = document.execCommand("copy");
					} catch (error) {}
					document.body.removeChild(area);
					return copied;
				};
				var copySshCommand = function (command) {
					if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
						return navigator.clipboard.writeText(command).then(function () { return true; }).catch(function () { return fallbackSshCopy(command); });
					}
					return Promise.resolve(fallbackSshCopy(command));
				};
				sshConfigOpen.addEventListener("click", function (event) {
					event.preventDefault();
					sshConfigSelect.hidden = false;
					sshConfigSelect.value = "";
					sshConfigSelect.focus();
					if (typeof sshConfigSelect.showPicker === "function") {
						try {
							sshConfigSelect.showPicker();
						} catch (error) {}
					}
				});
				sshConfigSelect.addEventListener("change", function () {
					var os = String(sshConfigSelect.value || "");
					if (!os) {
						return;
					}
					var values = {
						username: safeSshValue(sshFieldValue(sshUsername, true), ""),
						target: safeSshValue(sshFieldValue(sshTarget, true), ""),
						keyName: sshKeyName()
					};
					var missing = [];
					if (!values.username) missing.push("Benutzername");
					if (!values.target) missing.push("IP-Adresse");
					if (!values.keyName) missing.push("Public Key");
					sshConfigSelect.hidden = true;
					sshConfigSelect.value = "";
					if (missing.length) {
						window.alert("Bitte folgende Felder ausfüllen: " + missing.join(", ") + ".");
						return;
					}
					copySshCommand(buildSshCommand(os, values)).then(function (copied) {
						if (sshConfigStatus) {
							sshConfigStatus.textContent = copied ? "Befehl kopiert." : "Kopieren fehlgeschlagen.";
							window.setTimeout(function () { sshConfigStatus.textContent = ""; }, 1800);
						}
					});
				});
				document.addEventListener("click", function (event) {
					if (sshConfigSelect.hidden) {
						return;
					}
					var menu = sshConfigOpen.closest(".cmx-infrastruktur-server-ssh-command-menu");
					if (menu && !menu.contains(event.target)) {
						sshConfigSelect.hidden = true;
						sshConfigSelect.value = "";
					}
				});
			}

			document.querySelectorAll("[data-cmx-server-backup]").forEach(function (row) {
				var toggle = row.querySelector("[data-cmx-server-backup-toggle]");
				var count = row.querySelector("[data-cmx-server-backup-count]");
				if (!toggle || !count) {
					return;
				}
				var syncBackup = function () {
					count.disabled = !toggle.checked;
					if (toggle.checked && parseInt(count.value || "0", 10) < 1) {
						count.value = "1";
					} else if (!toggle.checked) {
						count.value = "0";
					}
				};
				toggle.addEventListener("change", syncBackup);
				syncBackup();
			});
		})();
		</script>
		<?php
	}
}

\add_action('save_post_infrastruktur', function (int $post_id): void {
	if (
		!isset($_POST['cmx_infrastruktur_server_nonce'])
		|| !\wp_verify_nonce(
			\sanitize_text_field(\wp_unslash($_POST['cmx_infrastruktur_server_nonce'])),
			'cmx_infrastruktur_server_save'
		)
		|| (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
		|| \wp_is_post_revision($post_id)
		|| \wp_is_post_autosave($post_id)
		|| !\current_user_can('edit_post', $post_id)
	) {
		return;
	}

	$type_id = isset($_POST['cmx_infrastruktur_server_type'])
		? (int) $_POST['cmx_infrastruktur_server_type']
		: 0;
	$type_term = $type_id > 0 ? \get_term($type_id, CMX_TAX_INFRASTRUKTUR_SERVER_TYPE) : null;
	if ($type_term instanceof \WP_Term && $type_term->slug === 'dedicated') {
		$type_term = \get_term_by('slug', 'bare', CMX_TAX_INFRASTRUKTUR_SERVER_TYPE);
	}
	if (
		$type_term instanceof \WP_Term
		&& !\array_key_exists($type_term->slug, cmx_infrastruktur_server_types())
	) {
		$type_term = null;
	}
	\wp_set_object_terms(
		$post_id,
		$type_term instanceof \WP_Term ? [$type_term->term_id] : [],
		CMX_TAX_INFRASTRUKTUR_SERVER_TYPE,
		false
	);

	$system = isset($_POST['cmx_infrastruktur_server_system'])
		? \sanitize_key((string) \wp_unslash($_POST['cmx_infrastruktur_server_system']))
		: 'linux';
	if (!\in_array($system, ['linux', 'windows'], true)) {
		$system = 'linux';
	}
	\update_post_meta($post_id, CMX_INFRASTRUKTUR_SERVER_SYSTEM_META, $system);

	foreach ([
		'cmx_infrastruktur_server_cpu'     => CMX_INFRASTRUKTUR_SERVER_CPU_META,
		'cmx_infrastruktur_server_ram'     => CMX_INFRASTRUKTUR_SERVER_RAM_META,
		'cmx_infrastruktur_server_storage' => CMX_INFRASTRUKTUR_SERVER_STORAGE_META,
	] as $field_name => $meta_key) {
		$value = isset($_POST[$field_name])
			? cmx_infrastruktur_server_numeric_value(\wp_unslash($_POST[$field_name]))
			: '';
		if ($value === '') {
			\delete_post_meta($post_id, $meta_key);
		} else {
			\update_post_meta($post_id, $meta_key, $value);
		}
	}

	$storage_type = isset($_POST['cmx_infrastruktur_server_storage_type'])
		? \sanitize_key((string) \wp_unslash($_POST['cmx_infrastruktur_server_storage_type']))
		: '';
	if (!\in_array($storage_type, ['hdd', 'ssd', 'nvme'], true)) {
		\delete_post_meta($post_id, CMX_INFRASTRUKTUR_SERVER_STORAGE_TYPE_META);
	} else {
		\update_post_meta($post_id, CMX_INFRASTRUKTUR_SERVER_STORAGE_TYPE_META, $storage_type);
	}

	foreach ([
		'cmx_infrastruktur_server_id'      => CMX_INFRASTRUKTUR_SERVER_ID_META,
		'cmx_infrastruktur_server_ip'      => CMX_INFRASTRUKTUR_SERVER_IP_META,
	] as $field_name => $meta_key) {
		$value = isset($_POST[$field_name])
			? \sanitize_text_field(\wp_unslash($_POST[$field_name]))
			: '';
		if ($value === '') {
			\delete_post_meta($post_id, $meta_key);
		} else {
			\update_post_meta($post_id, $meta_key, $value);
		}
	}

	\update_post_meta(
		$post_id,
		CMX_INFRASTRUKTUR_SERVER_FIREWALL_META,
		!empty($_POST['cmx_infrastruktur_server_firewall']) ? 1 : 0
	);

	$posted_networks = isset($_POST['cmx_infrastruktur_server_network']) && \is_array($_POST['cmx_infrastruktur_server_network'])
		? \array_map('sanitize_key', \wp_unslash($_POST['cmx_infrastruktur_server_network']))
		: [];
	$networks = \array_values(\array_intersect(['public', 'private'], $posted_networks));
	if ($networks === []) {
		\delete_post_meta($post_id, CMX_INFRASTRUKTUR_SERVER_NETWORK_META);
	} else {
		\update_post_meta($post_id, CMX_INFRASTRUKTUR_SERVER_NETWORK_META, $networks);
	}

	$ssh_key_id = isset($_POST['cmx_infrastruktur_server_ssh_key'])
		? \sanitize_key((string) \wp_unslash($_POST['cmx_infrastruktur_server_ssh_key']))
		: '';
	$valid_ssh_key_ids = [];
	foreach (cmx_get_admin_public_keys() as $ssh_public_key) {
		$valid_ssh_key_ids[] = cmx_ssh_public_key_id($ssh_public_key['key']);
	}
	if (!\in_array($ssh_key_id, $valid_ssh_key_ids, true)) {
		\delete_post_meta($post_id, CMX_INFRASTRUKTUR_SERVER_SSH_KEY_META);
	} else {
		\update_post_meta($post_id, CMX_INFRASTRUKTUR_SERVER_SSH_KEY_META, $ssh_key_id);
	}

	$posted_backups = isset($_POST['cmx_infrastruktur_server_backups']) && \is_array($_POST['cmx_infrastruktur_server_backups'])
		? \wp_unslash($_POST['cmx_infrastruktur_server_backups'])
		: [];
	$backups = [];
	foreach (['taeglich', 'woechentlich', 'monatlich'] as $interval) {
		$row = isset($posted_backups[$interval]) && \is_array($posted_backups[$interval])
			? $posted_backups[$interval]
			: [];
		$aktiv = !empty($row['aktiv']) ? 1 : 0;
		$anzahl = $aktiv ? \max(1, (int) ($row['anzahl'] ?? 1)) : 0;
		$backups[$interval] = [
			'aktiv'  => $aktiv,
			'anzahl' => $anzahl,
		];
	}
	\update_post_meta($post_id, CMX_INFRASTRUKTUR_SERVER_BACKUPS_META, $backups);
}, 10);

\add_action('do_meta_boxes', function ($post_type, $context): void {
	if ((string) $post_type !== 'infrastruktur' || (string) $context !== 'normal') {
		return;
	}

	$user_id = \get_current_user_id();
	if ($user_id <= 0) {
		return;
	}

	$order = \get_user_option('meta-box-order_infrastruktur');
	$order = \is_array($order) ? $order : [];
	$order += ['normal' => '', 'advanced' => '', 'side' => ''];
	$normal_ids = \array_values(\array_filter(\array_map('trim', \explode(',', (string) $order['normal']))));
	$normal_ids = \array_values(\array_filter($normal_ids, static function ($id): bool {
		return !\in_array((string) $id, [
			'cmx_infrastruktur_server_box',
			'cmx-infrastruktur-cloud-init',
		], true);
	}));
	\array_unshift($normal_ids, 'cmx-infrastruktur-cloud-init');
	\array_unshift($normal_ids, 'cmx_infrastruktur_server_box');
	$order['normal'] = \implode(',', $normal_ids);
	\update_user_option($user_id, 'meta-box-order_infrastruktur', $order, true);
}, 90, 2);

\add_filter('get_user_option_meta-box-order_infrastruktur', function ($order) {
	$order = \is_array($order) ? $order : [];
	$order += ['normal' => '', 'advanced' => '', 'side' => ''];

	$normal_ids = \array_values(\array_filter(\array_map(
		'trim',
		\explode(',', (string) $order['normal'])
	)));
	$normal_ids = \array_values(\array_filter($normal_ids, static function ($id): bool {
		return !\in_array((string) $id, [
			'cmx_infrastruktur_server_box',
			'cmx-infrastruktur-cloud-init',
		], true);
	}));
	\array_unshift($normal_ids, 'cmx-infrastruktur-cloud-init');
	\array_unshift($normal_ids, 'cmx_infrastruktur_server_box');
	$order['normal'] = \implode(',', $normal_ids);

	return $order;
}, 100);

\add_action('add_meta_boxes_infrastruktur', function (\WP_Post $post): void {
	\remove_meta_box('infrastruktur_playbooksdiv', 'infrastruktur', 'side');
	\remove_meta_box('tagsdiv-infrastruktur_playbooks', 'infrastruktur', 'side');

	$kontakt_id = (int) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_KONTAKT_META, true);
	$kontakt_url = $kontakt_id > 0 && \get_post_type($kontakt_id) === 'kontakte'
		? \admin_url('post.php?post=' . $kontakt_id . '&action=edit')
		: \admin_url('edit.php?post_type=kontakte');
	$title = '<a id="cmx_infrastruktur_kontakt_box_link" href="' . \esc_url($kontakt_url) . '" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation();" style="font-size:13px;font-weight:inherit;line-height:inherit;color:#2271b1;text-decoration:none;">'
		. \esc_html__('Kontakt', 'cmx-misbuero')
		. '</a>';

	\add_meta_box(
		'cmx_infrastruktur_kontakt_box',
		$title,
		__NAMESPACE__ . '\\cmx_infrastruktur_render_kontakt_metabox',
		'infrastruktur',
		'side',
		'default',
		[
			'__widget_basename' => __('Kontakt', 'cmx-misbuero'),
		]
	);
}, 100);

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_render_kontakt_metabox')) {
	function cmx_infrastruktur_render_kontakt_metabox(\WP_Post $post): void {
		$selected = (int) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_KONTAKT_META, true);
		$selected_title = $selected > 0 ? cmx_normalize_minus_sign((string) \get_the_title($selected)) : '';
		$box_id = 'cmx-infrastruktur-kontakt-' . (int) $post->ID;

		\wp_nonce_field('cmx_infrastruktur_kontakt_save', 'cmx_infrastruktur_kontakt_nonce');
		?>
		<style>
			#cmx_infrastruktur_kontakt_box,
			#cmx_infrastruktur_kontakt_box .inside,
			#<?php echo \esc_attr($box_id); ?> {
				position: relative;
				overflow: visible;
			}

			#<?php echo \esc_attr($box_id); ?> .cmx-infrastruktur-kontakt-search {
				width: 100%;
				min-height: 38px;
				margin: 0;
				padding: 0 12px;
				border: 1px solid #c3c4c7;
				border-radius: 8px;
				background: #fff;
				box-shadow: none;
			}

			#<?php echo \esc_attr($box_id); ?> .cmx-infrastruktur-kontakt-search:focus {
				border-color: #2271b1;
				box-shadow: 0 0 0 1px #2271b1;
				outline: 0;
			}

			#<?php echo \esc_attr($box_id); ?> .cmx-infrastruktur-kontakt-results {
				position: absolute;
				z-index: 100002;
				top: calc(100% + 4px);
				right: 0;
				left: 0;
				max-height: 240px;
				overflow: auto;
				margin: 0;
				padding: 4px 0;
				border: 1px solid #c3c4c7;
				border-radius: 8px;
				background: #fff;
				box-shadow: 0 10px 24px rgba(0, 0, 0, .12);
				list-style: none;
			}

			#<?php echo \esc_attr($box_id); ?> .cmx-infrastruktur-kontakt-results li {
				margin: 0;
				padding: 8px 12px;
				cursor: pointer;
			}

			#<?php echo \esc_attr($box_id); ?> .cmx-infrastruktur-kontakt-results li:hover,
			#<?php echo \esc_attr($box_id); ?> .cmx-infrastruktur-kontakt-results li.is-active {
				background: #e5f3ff;
			}

			#<?php echo \esc_attr($box_id); ?> .cmx-infrastruktur-kontakt-results li.is-empty {
				color: #646970;
				cursor: default;
			}
		</style>
		<div id="<?php echo \esc_attr($box_id); ?>">
			<input
				type="search"
				id="cmx_infrastruktur_kontakt_search"
				class="cmx-infrastruktur-kontakt-search"
				autocomplete="off"
				placeholder="<?php echo \esc_attr__('Kontakt suchen …', 'cmx-misbuero'); ?>"
				value="<?php echo \esc_attr($selected_title); ?>"
			>
			<input
				type="hidden"
				id="cmx_infrastruktur_kontakt_id"
				name="cmx_infrastruktur_kontakt_id"
				value="<?php echo \esc_attr((string) $selected); ?>"
			>
			<ul id="cmx_infrastruktur_kontakt_results" class="cmx-infrastruktur-kontakt-results" hidden></ul>
		</div>
		<script>
		(function () {
			var root = document.getElementById(<?php echo \wp_json_encode($box_id); ?>);
			if (!root || root.dataset.cmxBound === "1") {
				return;
			}
			root.dataset.cmxBound = "1";

			var searchInput = document.getElementById("cmx_infrastruktur_kontakt_search");
			var hiddenInput = document.getElementById("cmx_infrastruktur_kontakt_id");
			var results = document.getElementById("cmx_infrastruktur_kontakt_results");
			var headerLink = document.getElementById("cmx_infrastruktur_kontakt_box_link");
			var ajaxUrl = <?php echo \wp_json_encode((string) \admin_url('admin-ajax.php')); ?>;
			var ajaxNonce = <?php echo \wp_json_encode((string) \wp_create_nonce('cmx_search_kontakte')); ?>;
			var contactsUrl = <?php echo \wp_json_encode((string) \admin_url('edit.php?post_type=kontakte')); ?>;
			var editUrl = <?php echo \wp_json_encode((string) \admin_url('post.php?post=')); ?>;
			var items = [];
			var active = -1;
			var timer = null;

			if (!searchInput || !hiddenInput || !results || !headerLink) {
				return;
			}

			function escapeHtml(value) {
				return String(value || "").replace(/[&<>"']/g, function (character) {
					return {
						"&": "&amp;",
						"<": "&lt;",
						">": "&gt;",
						'"': "&quot;",
						"'": "&#039;"
					}[character];
				});
			}

			function syncHeaderLink() {
				var contactId = parseInt(hiddenInput.value || "0", 10);
				headerLink.href = contactId > 0
					? editUrl + contactId + "&action=edit"
					: contactsUrl;
			}

			function closeResults() {
				results.hidden = true;
				results.innerHTML = "";
				items = [];
				active = -1;
			}

			function render(found) {
				items = Array.isArray(found) ? found : [];
				active = -1;
				if (!items.length) {
					results.innerHTML = '<li class="is-empty"><?php echo \esc_js(__('Keine Kontakte gefunden.', 'cmx-misbuero')); ?></li>';
				} else {
					results.innerHTML = items.map(function (item, index) {
						return '<li data-index="' + index + '">' + escapeHtml(item.title) + "</li>";
					}).join("");
				}
				results.hidden = false;
			}

			function setActive(index) {
				if (!items.length) {
					return;
				}
				if (index < 0) {
					index = items.length - 1;
				}
				if (index >= items.length) {
					index = 0;
				}
				active = index;
				Array.prototype.forEach.call(results.querySelectorAll("li[data-index]"), function (item, itemIndex) {
					item.classList.toggle("is-active", itemIndex === active);
				});
			}

			function choose(item) {
				hiddenInput.value = item && item.id ? String(item.id) : "";
				searchInput.value = item && item.title ? String(item.title) : "";
				syncHeaderLink();
				closeResults();
				searchInput.focus();
			}

			function search(query) {
				var url = ajaxUrl
					+ "?action=cmx_search_kontakte&_ajax_nonce="
					+ encodeURIComponent(ajaxNonce)
					+ "&q="
					+ encodeURIComponent(query || "");

				fetch(url, {credentials: "same-origin"})
					.then(function (response) {
						return response.json();
					})
					.then(function (response) {
						if (!response || !response.success || !response.data || !Array.isArray(response.data.items)) {
							closeResults();
							return;
						}
						render(response.data.items);
					})
					.catch(closeResults);
			}

			searchInput.addEventListener("input", function () {
				hiddenInput.value = "";
				syncHeaderLink();
				window.clearTimeout(timer);
				var query = searchInput.value.trim();
				if (query.length === 1) {
					closeResults();
					return;
				}
				timer = window.setTimeout(function () {
					search(query);
				}, query === "" ? 0 : 200);
			});

			searchInput.addEventListener("focus", function () {
				search(searchInput.value.trim());
			});

			searchInput.addEventListener("keydown", function (event) {
				if (event.key === "ArrowDown") {
					event.preventDefault();
					setActive(active + 1);
				} else if (event.key === "ArrowUp") {
					event.preventDefault();
					setActive(active - 1);
				} else if (event.key === "Enter" && active >= 0 && items[active]) {
					event.preventDefault();
					choose(items[active]);
				} else if (event.key === "Escape") {
					closeResults();
				}
			});

			results.addEventListener("mousedown", function (event) {
				var row = event.target.closest("li[data-index]");
				if (!row) {
					return;
				}
				event.preventDefault();
				var index = parseInt(row.dataset.index || "-1", 10);
				if (items[index]) {
					choose(items[index]);
				}
			});

			results.addEventListener("mousemove", function (event) {
				var row = event.target.closest("li[data-index]");
				if (!row) {
					return;
				}
				setActive(parseInt(row.dataset.index || "-1", 10));
			});

			document.addEventListener("mousedown", function (event) {
				if (!root.contains(event.target)) {
					closeResults();
				}
			});

			syncHeaderLink();
		})();
		</script>
		<?php
	}
}

\add_action('save_post_infrastruktur', function (int $post_id): void {
	if (
		!isset($_POST['cmx_infrastruktur_kontakt_nonce'])
		|| !\wp_verify_nonce(
			\sanitize_text_field(\wp_unslash($_POST['cmx_infrastruktur_kontakt_nonce'])),
			'cmx_infrastruktur_kontakt_save'
		)
		|| (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
		|| \wp_is_post_revision($post_id)
		|| !\current_user_can('edit_post', $post_id)
	) {
		return;
	}

	$kontakt_id = isset($_POST['cmx_infrastruktur_kontakt_id'])
		? (int) $_POST['cmx_infrastruktur_kontakt_id']
		: 0;

	if ($kontakt_id > 0 && \get_post_type($kontakt_id) === 'kontakte') {
		\update_post_meta($post_id, CMX_INFRASTRUKTUR_KONTAKT_META, $kontakt_id);
		return;
	}

	\delete_post_meta($post_id, CMX_INFRASTRUKTUR_KONTAKT_META);
}, 10);

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_assigned_ids')) {
	function cmx_infrastruktur_assigned_ids(int $post_id): array {
		$raw_ids = \get_post_meta($post_id, CMX_INFRASTRUKTUR_ASSIGNED_META, true);
		$ids = [];
		foreach ((array) $raw_ids as $raw_id) {
			$id = (int) $raw_id;
			if ($id <= 0 || $id === $post_id || \get_post_type($id) !== 'infrastruktur') {
				continue;
			}
			$ids[$id] = $id;
		}

		return \array_values($ids);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_store_assigned_ids')) {
	function cmx_infrastruktur_store_assigned_ids(int $post_id, array $ids): void {
		$clean = [];
		foreach ($ids as $raw_id) {
			$id = (int) $raw_id;
			if ($id <= 0 || $id === $post_id || \get_post_type($id) !== 'infrastruktur') {
				continue;
			}
			$clean[$id] = $id;
		}
		$clean = \array_values($clean);
		\sort($clean, \SORT_NUMERIC);

		if ($clean === []) {
			\delete_post_meta($post_id, CMX_INFRASTRUKTUR_ASSIGNED_META);
			return;
		}
		\update_post_meta($post_id, CMX_INFRASTRUKTUR_ASSIGNED_META, $clean);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_sync_assignments')) {
	function cmx_infrastruktur_sync_assignments(int $post_id, array $new_ids): void {
		$old_ids = cmx_infrastruktur_assigned_ids($post_id);
		$clean_new_ids = [];
		foreach ($new_ids as $raw_id) {
			$id = (int) $raw_id;
			if ($id > 0 && $id !== $post_id && \get_post_type($id) === 'infrastruktur') {
				$clean_new_ids[$id] = $id;
			}
		}
		$clean_new_ids = \array_values($clean_new_ids);

		foreach (\array_diff($old_ids, $clean_new_ids) as $removed_id) {
			$reverse_ids = \array_values(\array_diff(cmx_infrastruktur_assigned_ids((int) $removed_id), [$post_id]));
			cmx_infrastruktur_store_assigned_ids((int) $removed_id, $reverse_ids);
		}
		foreach (\array_diff($clean_new_ids, $old_ids) as $added_id) {
			$reverse_ids = cmx_infrastruktur_assigned_ids((int) $added_id);
			$reverse_ids[] = $post_id;
			cmx_infrastruktur_store_assigned_ids((int) $added_id, $reverse_ids);
		}

		cmx_infrastruktur_store_assigned_ids($post_id, $clean_new_ids);
	}
}

\add_filter('cmx_duplicate_meta_blacklist', function (array $meta_keys): array {
	$meta_keys[] = CMX_INFRASTRUKTUR_ASSIGNED_META;
	$meta_keys[] = CMX_INFRASTRUKTUR_INSTANCE_SOURCE_META;
	return \array_values(\array_unique($meta_keys));
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_duplicate_for_instance')) {
	/**
	 * Erstellt nach einer bestaetigten externen create-Aktion den lokalen Instanz-CPT.
	 * Der Aufrufer muss Berechtigung, Nonce und den Erfolg der externen API pruefen.
	 *
	 * @return int|\WP_Error
	 */
	function cmx_infrastruktur_duplicate_for_instance(int $source_id, string $domain, string $email = '') {
		$source = \get_post($source_id);
		if (!$source instanceof \WP_Post || $source->post_type !== 'infrastruktur') {
			return new \WP_Error('cmx_instance_source_invalid', __('Der Ausgangsserver wurde nicht gefunden.', 'cmx-misbuero'));
		}

		$domain = \strtolower(\trim(\sanitize_text_field($domain)));
		$domain = (string) \preg_replace('~^https?://~i', '', $domain);
		$domain = \rtrim((string) \strtok($domain, '/?#'), '.');
		if (
			$domain === ''
			|| \strlen($domain) > 253
			|| !\preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,63}$/', $domain)
		) {
			return new \WP_Error('cmx_instance_domain_invalid', __('Die Instanz-Domain ist ungültig.', 'cmx-misbuero'));
		}

		$existing = \get_posts([
			'post_type'      => 'infrastruktur',
			'post_status'    => ['publish', 'draft', 'private', 'pending'],
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'exclude'        => [$source_id],
			'meta_query'     => [
				'relation' => 'AND',
				[
					'key'   => CMX_INFRASTRUKTUR_INSTANCE_SOURCE_META,
					'value' => $source_id,
					'type'  => 'NUMERIC',
				],
				[
					'key'   => CMX_INFRASTRUKTUR_FQDN_META,
					'value' => $domain,
				],
			],
		]);
		if ($existing !== []) {
			$existing_id = (int) $existing[0];
			$source_ids = cmx_infrastruktur_assigned_ids($source_id);
			$source_ids[] = $existing_id;
			cmx_infrastruktur_sync_assignments($source_id, $source_ids);
			return $existing_id;
		}

		if (!\function_exists(__NAMESPACE__ . '\\cmx_duplicate_do')) {
			return new \WP_Error('cmx_instance_duplicate_unavailable', __('Die Duplizierfunktion ist nicht verfügbar.', 'cmx-misbuero'));
		}
		$new_id = cmx_duplicate_do($source_id);
		if (\is_wp_error($new_id)) {
			return $new_id;
		}
		$new_id = (int) $new_id;
		$hostname = (string) \strtok($domain, '.');

		$updated = \wp_update_post([
			'ID'         => $new_id,
			'post_title' => $domain,
			'post_name'  => \sanitize_title($domain),
		], true);
		if (\is_wp_error($updated)) {
			\wp_delete_post($new_id, true);
			return $updated;
		}

		\update_post_meta($new_id, CMX_INFRASTRUKTUR_INSTANCE_SOURCE_META, $source_id);
		\update_post_meta($new_id, CMX_INFRASTRUKTUR_FQDN_META, $domain);
		\update_post_meta($new_id, CMX_INFRASTRUKTUR_HOSTNAME_META, $hostname);
		$email = \sanitize_email($email);
		if ($email !== '' && \is_email($email)) {
			\update_post_meta($new_id, CMX_INFRASTRUKTUR_ADMIN_EMAIL_META, $email);
		}

		$source_ids = cmx_infrastruktur_assigned_ids($source_id);
		$source_ids[] = $new_id;
		cmx_infrastruktur_sync_assignments($source_id, $source_ids);
		\do_action('cmx_infrastruktur_instance_cpt_created', $new_id, $source_id, $domain);

		return $new_id;
	}
}

/**
 * Die spaetere API-Anbindung feuert diesen Hook ausschliesslich nach einem
 * erfolgreich bestaetigten create. Update, pause und delete duplizieren nichts.
 */
\add_action('cmx_infrastruktur_instance_created', function (int $source_id, string $domain, string $email = ''): void {
	cmx_infrastruktur_duplicate_for_instance($source_id, $domain, $email);
}, 10, 3);

\add_action('add_meta_boxes_infrastruktur', function (): void {
	\add_meta_box(
		'cmx_infrastruktur_assigned_box',
		__('Zugeordnet', 'cmx-misbuero'),
		__NAMESPACE__ . '\\cmx_infrastruktur_render_assigned_metabox',
		'infrastruktur',
		'side',
		'default'
	);
}, 101);

\add_action('wp_ajax_cmx_search_infrastrukturen', function (): void {
	if (!\current_user_can('edit_posts')) {
		\wp_send_json_error(['message' => __('Keine Berechtigung.', 'cmx-misbuero')], 403);
	}
	if (!\check_ajax_referer('cmx_search_infrastrukturen', '_ajax_nonce', false)) {
		\wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen.', 'cmx-misbuero')], 403);
	}

	$query = isset($_GET['q']) ? \sanitize_text_field(\wp_unslash($_GET['q'])) : '';
	$exclude_id = isset($_GET['exclude']) ? (int) $_GET['exclude'] : 0;
	$args = [
		'post_type'              => 'infrastruktur',
		'post_status'            => ['publish', 'draft', 'private', 'pending', 'future'],
		'posts_per_page'         => 30,
		'orderby'                => 'title',
		'order'                  => 'ASC',
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	];
	if ($query !== '') {
		$args['s'] = $query;
	}
	if ($exclude_id > 0) {
		$args['post__not_in'] = [$exclude_id];
	}

	$items = [];
	foreach ((array) \get_posts($args) as $post_id) {
		$post_id = (int) $post_id;
		if ($post_id <= 0 || !\current_user_can('edit_post', $post_id)) {
			continue;
		}
		$title = cmx_normalize_minus_sign((string) \get_the_title($post_id));
		$items[] = [
			'id'    => $post_id,
			'title' => $title !== '' ? $title : ('#' . $post_id),
		];
	}

	\wp_send_json_success(['items' => $items]);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_render_assigned_metabox')) {
	function cmx_infrastruktur_render_assigned_metabox(\WP_Post $post): void {
		$selected_ids = cmx_infrastruktur_assigned_ids((int) $post->ID);
		$option_ids = $selected_ids;
		$box_id = 'cmx-infrastruktur-assigned-' . (int) $post->ID;
		\wp_nonce_field('cmx_infrastruktur_assigned_save', 'cmx_infrastruktur_assigned_nonce');
		?>
		<style>
			#cmx_infrastruktur_assigned_box,#cmx_infrastruktur_assigned_box .inside,#<?php echo \esc_attr($box_id); ?>{position:relative;overflow:visible}
			#<?php echo \esc_attr($box_id); ?> .cmx-infrastruktur-assigned-search{width:100%;min-height:38px;margin:0;padding:0 12px;border:1px solid #c3c4c7;border-radius:8px;background:#fff;box-shadow:none}
			#<?php echo \esc_attr($box_id); ?> .cmx-infrastruktur-assigned-search:focus{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1;outline:0}
			#<?php echo \esc_attr($box_id); ?> .cmx-infrastruktur-assigned-results{position:absolute;z-index:100002;right:0;left:0;max-height:240px;overflow:auto;margin:4px 0 0;padding:4px 0;border:1px solid #c3c4c7;border-radius:8px;background:#fff;box-shadow:0 10px 24px rgba(0,0,0,.12);list-style:none}
			#<?php echo \esc_attr($box_id); ?> .cmx-infrastruktur-assigned-results li{margin:0;padding:8px 12px;cursor:pointer}
			#<?php echo \esc_attr($box_id); ?> .cmx-infrastruktur-assigned-results li:hover,#<?php echo \esc_attr($box_id); ?> .cmx-infrastruktur-assigned-results li.is-active{background:#e5f3ff}
			#<?php echo \esc_attr($box_id); ?> .cmx-infrastruktur-assigned-results li.is-empty{color:#646970;cursor:default}
			#<?php echo \esc_attr($box_id); ?> .cmx-infrastruktur-assigned-selected{display:grid;gap:6px;margin:10px 0 0;padding:0;list-style:none}
			#<?php echo \esc_attr($box_id); ?> .cmx-infrastruktur-assigned-selected li{display:flex;align-items:center;gap:8px;min-width:0;padding:7px 8px;border:1px solid #dcdcde;border-radius:7px;background:#f6f7f7}
			#<?php echo \esc_attr($box_id); ?> .cmx-infrastruktur-assigned-selected a{min-width:0;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-decoration:none}
			#<?php echo \esc_attr($box_id); ?> .cmx-infrastruktur-assigned-remove{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;padding:0;border:0;background:transparent;color:#d63638;cursor:pointer}
			#<?php echo \esc_attr($box_id); ?> .cmx-infrastruktur-assigned-remove:hover,#<?php echo \esc_attr($box_id); ?> .cmx-infrastruktur-assigned-remove:focus{color:#b32d2e}
		</style>
		<div id="<?php echo \esc_attr($box_id); ?>">
			<input type="search" class="cmx-infrastruktur-assigned-search" autocomplete="off" placeholder="<?php echo \esc_attr__('Infrastruktur suchen …', 'cmx-misbuero'); ?>" aria-label="<?php echo \esc_attr__('Infrastruktur suchen', 'cmx-misbuero'); ?>">
			<ul class="cmx-infrastruktur-assigned-results" hidden></ul>
			<select name="cmx_infrastruktur_assigned_ids[]" class="cmx-infrastruktur-assigned-select" multiple hidden>
				<?php foreach ($option_ids as $option_id) :
					$option_id = (int) $option_id;
					$title = cmx_normalize_minus_sign((string) \get_the_title($option_id));
					if ($title === '') {
						$title = '#' . $option_id;
					}
				?>
					<option value="<?php echo \esc_attr((string) $option_id); ?>"<?php \selected(\in_array($option_id, $selected_ids, true)); ?>><?php echo \esc_html($title); ?></option>
				<?php endforeach; ?>
			</select>
			<ul class="cmx-infrastruktur-assigned-selected"></ul>
		</div>
		<script>
		(function(){
			var root=document.getElementById(<?php echo \wp_json_encode($box_id); ?>);if(!root||root.dataset.cmxBound==="1")return;root.dataset.cmxBound="1";
			var input=root.querySelector(".cmx-infrastruktur-assigned-search"),results=root.querySelector(".cmx-infrastruktur-assigned-results"),select=root.querySelector(".cmx-infrastruktur-assigned-select"),selected=root.querySelector(".cmx-infrastruktur-assigned-selected");
			var editPrefix=<?php echo \wp_json_encode((string) \admin_url('post.php?action=edit&post=')); ?>,ajaxUrl=<?php echo \wp_json_encode((string) \admin_url('admin-ajax.php')); ?>,ajaxNonce=<?php echo \wp_json_encode((string) \wp_create_nonce('cmx_search_infrastrukturen')); ?>,excludeId=<?php echo (int) $post->ID; ?>,items=[],active=-1,timer=null,requestNumber=0;
			if(!input||!results||!select||!selected)return;
			function esc(value){return String(value||"").replace(/[&<>\"']/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'\"':"&quot;","'":"&#039;"}[c];});}
			function options(onlyAvailable,term){var q=String(term||"").toLowerCase().trim(),found=[];Array.prototype.forEach.call(select.options,function(option){if(onlyAvailable&&option.selected)return;var label=String(option.textContent||"");if(q&&label.toLowerCase().indexOf(q)===-1)return;found.push({id:String(option.value||""),label:label});});return found;}
			function close(){results.hidden=true;results.innerHTML="";items=[];active=-1;}
			function renderSelected(){var chosen=options(false,"").filter(function(item){var option=Array.prototype.find.call(select.options,function(candidate){return candidate.value===item.id;});return option&&option.selected;});selected.innerHTML=chosen.map(function(item){return '<li><a href="'+esc(editPrefix+encodeURIComponent(item.id))+'" target="_blank" rel="noopener noreferrer">'+esc(item.label)+'</a><button type="button" class="cmx-infrastruktur-assigned-remove" data-id="'+esc(item.id)+'" title="Zuordnung entfernen" aria-label="Zuordnung entfernen"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></li>';}).join("");}
			function activate(index){var rows=results.querySelectorAll("li[data-index]");if(!rows.length){active=-1;return;}if(index<0)index=rows.length-1;if(index>=rows.length)index=0;active=index;Array.prototype.forEach.call(rows,function(row,i){row.classList.toggle("is-active",i===active);});}
			function render(found){var selectedIds={};Array.prototype.forEach.call(select.options,function(option){if(option.selected)selectedIds[String(option.value||"")]=true;});items=(Array.isArray(found)?found:[]).map(function(item){return{id:String(item.id||""),label:String(item.title||"")};}).filter(function(item){return item.id&&!selectedIds[item.id];});active=-1;if(!items.length){results.innerHTML='<li class="is-empty">Keine Treffer.</li>';results.hidden=false;return;}results.innerHTML=items.map(function(item,index){return '<li data-index="'+index+'">'+esc(item.label)+'</li>';}).join("");results.hidden=false;activate(0);}
			function search(){var currentRequest=++requestNumber,url=ajaxUrl+"?action=cmx_search_infrastrukturen&_ajax_nonce="+encodeURIComponent(ajaxNonce)+"&exclude="+encodeURIComponent(String(excludeId))+"&q="+encodeURIComponent(input.value.trim());fetch(url,{credentials:"same-origin"}).then(function(response){return response.json();}).then(function(response){if(currentRequest!==requestNumber)return;if(!response||response.success!==true||!response.data||!Array.isArray(response.data.items)){render([]);return;}render(response.data.items);}).catch(function(){if(currentRequest===requestNumber)render([]);});}
			function choose(index){if(!items[index])return;var item=items[index],option=Array.prototype.find.call(select.options,function(candidate){return candidate.value===item.id;});if(!option){option=document.createElement("option");option.value=item.id;option.textContent=item.label;select.appendChild(option);}option.selected=true;input.value="";renderSelected();close();input.focus();}
			input.addEventListener("input",function(){window.clearTimeout(timer);timer=window.setTimeout(search,150);});input.addEventListener("focus",search);input.addEventListener("click",search);input.addEventListener("keydown",function(event){if(event.key==="ArrowDown"){event.preventDefault();activate(active+1);}else if(event.key==="ArrowUp"){event.preventDefault();activate(active-1);}else if(event.key==="Enter"&&active>=0){event.preventDefault();choose(active);}else if(event.key==="Escape"){close();}});
			results.addEventListener("mousedown",function(event){var row=event.target.closest("li[data-index]");if(!row)return;event.preventDefault();choose(parseInt(row.dataset.index||"-1",10));});
			results.addEventListener("mousemove",function(event){var row=event.target.closest("li[data-index]");if(row)activate(parseInt(row.dataset.index||"-1",10));});
			selected.addEventListener("click",function(event){var button=event.target.closest(".cmx-infrastruktur-assigned-remove");if(!button)return;var id=button.getAttribute("data-id")||"";Array.prototype.forEach.call(select.options,function(option){if(option.value===id)option.selected=false;});renderSelected();input.focus();});
			document.addEventListener("mousedown",function(event){if(!root.contains(event.target))close();});renderSelected();
		})();
		</script>
		<?php
	}
}

\add_action('save_post_infrastruktur', function (int $post_id): void {
	if (
		!isset($_POST['cmx_infrastruktur_assigned_nonce'])
		|| !\wp_verify_nonce(\sanitize_text_field(\wp_unslash($_POST['cmx_infrastruktur_assigned_nonce'])), 'cmx_infrastruktur_assigned_save')
		|| (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
		|| \wp_is_post_revision($post_id)
		|| \wp_is_post_autosave($post_id)
		|| !\current_user_can('edit_post', $post_id)
	) {
		return;
	}

	$ids = isset($_POST['cmx_infrastruktur_assigned_ids'])
		? (array) \wp_unslash($_POST['cmx_infrastruktur_assigned_ids'])
		: [];
	cmx_infrastruktur_sync_assignments($post_id, $ids);
}, 20);

\add_action('add_meta_boxes_infrastruktur', function (): void {
	\add_meta_box(
		'cmx_infrastruktur_konditionen_box',
		__('Konditionen', 'cmx-misbuero'),
		__NAMESPACE__ . '\\cmx_infrastruktur_render_konditionen_metabox',
		'infrastruktur',
		'side',
		'default'
	);
}, 100);

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_render_konditionen_metabox')) {
	function cmx_infrastruktur_render_konditionen_metabox(\WP_Post $post): void {
		$sku = (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_SKU_META, true);
		$ek = (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_EK_META, true);
		$vk = (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_VK_META, true);
		$ek_anzeige = $ek === '' ? '' : cmx_format_swiss_number($ek, 2);
		$vk_anzeige = $vk === '' ? '' : cmx_format_swiss_number($vk, 2);
		$einheit = (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_EINHEIT_META, true);
		if (!\in_array($einheit, ['monatlich', 'stuendlich', 'jaehrlich'], true)) {
			$einheit = 'monatlich';
		}

		\wp_nonce_field('cmx_infrastruktur_konditionen_save', 'cmx_infrastruktur_konditionen_nonce');
		?>
		<style>
			#cmx_infrastruktur_konditionen_box .inside {
				margin-top: 0;
				padding: 12px;
			}

			.cmx-infrastruktur-konditionen {
				display: grid;
				gap: 12px;
			}

			.cmx-infrastruktur-konditionen label {
				display: grid;
				gap: 5px;
				margin: 0;
				font-weight: 600;
			}

			.cmx-infrastruktur-konditionen input,
			.cmx-infrastruktur-konditionen select {
				width: 100%;
				min-height: 38px;
				margin: 0;
				padding-right: 10px;
				padding-left: 10px;
				border: 1px solid #c3c4c7;
				border-radius: 8px;
				background-color: #fff;
				box-shadow: none;
				font-weight: 400;
			}

			.cmx-infrastruktur-konditionen input:focus,
			.cmx-infrastruktur-konditionen select:focus {
				border-color: #2271b1;
				box-shadow: 0 0 0 1px #2271b1;
				outline: 0;
			}

			.cmx-infrastruktur-konditionen-preise {
				display: grid;
				grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
				gap: 10px;
			}

			.cmx-infrastruktur-konditionen-preis input {
				padding-right: 10px;
			}
		</style>
		<div class="cmx-infrastruktur-konditionen">
			<label for="cmx-infrastruktur-sku">
				<span><?php echo \esc_html__('SKU (Artikel-Nr.)', 'cmx-misbuero'); ?></span>
				<input type="text" id="cmx-infrastruktur-sku" name="cmx_infrastruktur_sku" value="<?php echo \esc_attr($sku); ?>" autocomplete="off">
			</label>

			<div class="cmx-infrastruktur-konditionen-preise">
				<label class="cmx-infrastruktur-konditionen-preis" for="cmx-infrastruktur-ek">
					<span><?php echo \esc_html__('EK', 'cmx-misbuero'); ?></span>
					<input type="text" inputmode="decimal" id="cmx-infrastruktur-ek" name="cmx_infrastruktur_ek" value="<?php echo \esc_attr($ek_anzeige); ?>" placeholder="0.00" autocomplete="off">
				</label>
				<label class="cmx-infrastruktur-konditionen-preis" for="cmx-infrastruktur-vk">
					<span><?php echo \esc_html__('VK', 'cmx-misbuero'); ?></span>
					<input type="text" inputmode="decimal" id="cmx-infrastruktur-vk" name="cmx_infrastruktur_vk" value="<?php echo \esc_attr($vk_anzeige); ?>" placeholder="0.00" autocomplete="off">
				</label>
			</div>

			<label for="cmx-infrastruktur-einheit">
				<span><?php echo \esc_html__('Einheit', 'cmx-misbuero'); ?></span>
				<select id="cmx-infrastruktur-einheit" name="cmx_infrastruktur_einheit">
					<option value="monatlich" <?php \selected($einheit, 'monatlich'); ?>><?php echo \esc_html__('Monatlich', 'cmx-misbuero'); ?></option>
					<option value="stuendlich" <?php \selected($einheit, 'stuendlich'); ?>><?php echo \esc_html__('Stündlich', 'cmx-misbuero'); ?></option>
					<option value="jaehrlich" <?php \selected($einheit, 'jaehrlich'); ?>><?php echo \esc_html__('Jährlich', 'cmx-misbuero'); ?></option>
				</select>
			</label>
		</div>
		<script>
			(function () {
				function parseSwissNumber(value) {
					var normalized = String(value || '')
						.trim()
						.replace(/[\s\u00a0\u202f'’‘`´′]/g, '');
					var comma = normalized.lastIndexOf(',');
					var dot = normalized.lastIndexOf('.');

					if (comma !== -1 && dot !== -1) {
						normalized = comma > dot
							? normalized.replace(/\./g, '').replace(',', '.')
							: normalized.replace(/,/g, '');
					} else if (comma !== -1) {
						normalized = normalized.replace(',', '.');
					}

					var number = Number(normalized);
					return Number.isFinite(number) ? Math.max(0, number) : null;
				}

				function formatSwissNumber(input) {
					if (!input || input.value.trim() === '') {
						return;
					}

					var number = parseSwissNumber(input.value);
					if (number === null) {
						return;
					}

					var parts = number.toFixed(2).split('.');
					parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, "'");
					input.value = parts.join('.');
				}

				['cmx-infrastruktur-ek', 'cmx-infrastruktur-vk'].forEach(function (id) {
					var input = document.getElementById(id);
					if (input) {
						input.addEventListener('blur', function () {
							formatSwissNumber(input);
						});
					}
				});
			}());
		</script>
		<?php
	}
}

\add_action('save_post_infrastruktur', function (int $post_id): void {
	if (
		!isset($_POST['cmx_infrastruktur_konditionen_nonce'])
		|| !\wp_verify_nonce(
			\sanitize_text_field(\wp_unslash($_POST['cmx_infrastruktur_konditionen_nonce'])),
			'cmx_infrastruktur_konditionen_save'
		)
		|| (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
		|| \wp_is_post_revision($post_id)
		|| \wp_is_post_autosave($post_id)
		|| !\current_user_can('edit_post', $post_id)
	) {
		return;
	}

	$sku = isset($_POST['cmx_infrastruktur_sku'])
		? \sanitize_text_field(\wp_unslash($_POST['cmx_infrastruktur_sku']))
		: '';
	if ($sku === '') {
		\delete_post_meta($post_id, CMX_INFRASTRUKTUR_SKU_META);
	} else {
		\update_post_meta($post_id, CMX_INFRASTRUKTUR_SKU_META, $sku);
	}

	foreach ([
		'cmx_infrastruktur_ek' => CMX_INFRASTRUKTUR_EK_META,
		'cmx_infrastruktur_vk' => CMX_INFRASTRUKTUR_VK_META,
	] as $field_name => $meta_key) {
		$raw_value = isset($_POST[$field_name])
			? \trim((string) \wp_unslash($_POST[$field_name]))
			: '';
		if ($raw_value === '') {
			\delete_post_meta($post_id, $meta_key);
			continue;
		}

		$value = \function_exists(__NAMESPACE__ . '\\cmx_parse_number')
			? (float) cmx_parse_number($raw_value)
			: (float) \str_replace([',', "'"], ['.', ''], $raw_value);
		$value = \is_finite($value) ? \max(0.0, $value) : 0.0;
		\update_post_meta($post_id, $meta_key, \number_format($value, 2, '.', ''));
	}

	$einheit = isset($_POST['cmx_infrastruktur_einheit'])
		? \sanitize_key((string) \wp_unslash($_POST['cmx_infrastruktur_einheit']))
		: 'monatlich';
	if (!\in_array($einheit, ['monatlich', 'stuendlich', 'jaehrlich'], true)) {
		$einheit = 'monatlich';
	}
	\update_post_meta($post_id, CMX_INFRASTRUKTUR_EINHEIT_META, $einheit);
}, 10);

\add_filter('bulk_actions-edit-infrastruktur', function (array $actions): array {
	unset($actions['edit']);

	return $actions;
}, 1000);

\add_filter('disable_months_dropdown', function (bool $disabled, string $post_type): bool {
	return $post_type === 'infrastruktur' ? true : $disabled;
}, 10, 2);

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_filter_query_args')) {
	function cmx_infrastruktur_filter_query_args(array $args, array $request): array {
		$type = isset($request['cmx_infrastruktur_filter_type'])
			? \sanitize_key((string) \wp_unslash($request['cmx_infrastruktur_filter_type']))
			: '';
		if (!\array_key_exists($type, cmx_infrastruktur_server_types())) {
			$type = '';
		}

		$system = isset($request['cmx_infrastruktur_filter_system'])
			? \sanitize_key((string) \wp_unslash($request['cmx_infrastruktur_filter_system']))
			: '';
		if (!\in_array($system, ['linux', 'windows'], true)) {
			$system = '';
		}

		$firewall = isset($request['cmx_infrastruktur_filter_firewall'])
			? \sanitize_key((string) \wp_unslash($request['cmx_infrastruktur_filter_firewall']))
			: '';
		if (!\in_array($firewall, ['yes', 'no'], true)) {
			$firewall = '';
		}

		if ($type !== '') {
			$tax_query = isset($args['tax_query']) && \is_array($args['tax_query']) ? $args['tax_query'] : [];
			$type_clause = [
				'taxonomy' => CMX_TAX_INFRASTRUKTUR_SERVER_TYPE,
				'field'    => 'slug',
				'terms'    => [$type],
			];
			// Ältere Einträge ohne Typ werden in der Liste als VPS dargestellt.
			$tax_query[] = $type === 'vps'
				? [
					'relation' => 'OR',
					$type_clause,
					[
						'taxonomy' => CMX_TAX_INFRASTRUKTUR_SERVER_TYPE,
						'operator' => 'NOT EXISTS',
					],
				]
				: $type_clause;
			$args['tax_query'] = $tax_query;
		}

		$meta_query = isset($args['meta_query']) && \is_array($args['meta_query']) ? $args['meta_query'] : [];
		if ($system === 'windows') {
			$meta_query[] = [
				'key'   => CMX_INFRASTRUKTUR_SERVER_SYSTEM_META,
				'value' => 'windows',
			];
		} elseif ($system === 'linux') {
			// Ältere Einträge ohne System werden in der Liste als Linux dargestellt.
			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'   => CMX_INFRASTRUKTUR_SERVER_SYSTEM_META,
					'value' => 'linux',
				],
				[
					'key'     => CMX_INFRASTRUKTUR_SERVER_SYSTEM_META,
					'compare' => 'NOT EXISTS',
				],
			];
		}

		if ($firewall === 'yes') {
			$meta_query[] = [
				'key'   => CMX_INFRASTRUKTUR_SERVER_FIREWALL_META,
				'value' => '1',
			];
		} elseif ($firewall === 'no') {
			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'   => CMX_INFRASTRUKTUR_SERVER_FIREWALL_META,
					'value' => '0',
				],
				[
					'key'     => CMX_INFRASTRUKTUR_SERVER_FIREWALL_META,
					'compare' => 'NOT EXISTS',
				],
			];
		}

		if ($meta_query !== []) {
			$args['meta_query'] = $meta_query;
		}

		return $args;
	}
}

\add_action('restrict_manage_posts', function (string $post_type, string $which): void {
	if ($post_type !== 'infrastruktur' || $which !== 'top') {
		return;
	}

	$selected_type = \sanitize_key((string) \wp_unslash($_GET['cmx_infrastruktur_filter_type'] ?? ''));
	$selected_system = \sanitize_key((string) \wp_unslash($_GET['cmx_infrastruktur_filter_system'] ?? ''));
	$selected_firewall = \sanitize_key((string) \wp_unslash($_GET['cmx_infrastruktur_filter_firewall'] ?? ''));

	echo '<label class="screen-reader-text" for="cmx-infrastruktur-filter-type">' . \esc_html__('Typ filtern', 'cmx-misbuero') . '</label>';
	echo '<select id="cmx-infrastruktur-filter-type" name="cmx_infrastruktur_filter_type">';
	echo '<option value="">' . \esc_html__('Typ', 'cmx-misbuero') . '</option>';
	foreach (cmx_infrastruktur_server_types() as $slug => $label) {
		echo '<option value="' . \esc_attr($slug) . '"' . \selected($selected_type, $slug, false) . '>' . \esc_html($label) . '</option>';
	}
	echo '</select>';

	echo '<label class="screen-reader-text" for="cmx-infrastruktur-filter-system">' . \esc_html__('System filtern', 'cmx-misbuero') . '</label>';
	echo '<select id="cmx-infrastruktur-filter-system" name="cmx_infrastruktur_filter_system">';
	echo '<option value="">' . \esc_html__('System', 'cmx-misbuero') . '</option>';
	echo '<option value="linux"' . \selected($selected_system, 'linux', false) . '>Linux</option>';
	echo '<option value="windows"' . \selected($selected_system, 'windows', false) . '>Windows</option>';
	echo '</select>';

	echo '<label class="screen-reader-text" for="cmx-infrastruktur-filter-firewall">' . \esc_html__('Firewall filtern', 'cmx-misbuero') . '</label>';
	echo '<select id="cmx-infrastruktur-filter-firewall" name="cmx_infrastruktur_filter_firewall">';
	echo '<option value="">' . \esc_html__('Firewall', 'cmx-misbuero') . '</option>';
	echo '<option value="yes"' . \selected($selected_firewall, 'yes', false) . '>' . \esc_html__('Ja', 'cmx-misbuero') . '</option>';
	echo '<option value="no"' . \selected($selected_firewall, 'no', false) . '>' . \esc_html__('Nein', 'cmx-misbuero') . '</option>';
	echo '</select>';
}, 10, 2);

\add_action('pre_get_posts', function (\WP_Query $query): void {
	if (!\is_admin() || !$query->is_main_query()) {
		return;
	}

	$post_type = $query->get('post_type');
	if (\is_array($post_type)) {
		$post_type = (string) \reset($post_type);
	}
	if ((string) $post_type !== 'infrastruktur') {
		return;
	}

	$args = cmx_infrastruktur_filter_query_args([
		'tax_query'  => (array) $query->get('tax_query'),
		'meta_query' => (array) $query->get('meta_query'),
	], (array) $_GET);
	if (!empty($args['tax_query'])) {
		$query->set('tax_query', $args['tax_query']);
	}
	if (!empty($args['meta_query'])) {
		$query->set('meta_query', $args['meta_query']);
	}
});

\add_filter('cmx_cpt_transfer_collect_args', function (array $args, string $post_type, array $request): array {
	return $post_type === 'infrastruktur'
		? cmx_infrastruktur_filter_query_args($args, $request)
		: $args;
}, 10, 3);

\add_filter('manage_infrastruktur_posts_columns', function (array $columns): array {
	return [
		'cb'                              => $columns['cb'] ?? '<input type="checkbox">',
		'title'                           => __('Name des Templates', 'cmx-misbuero'),
		'cmx_infrastruktur_kontakt'       => __('Kontakt', 'cmx-misbuero'),
		'cmx_infrastruktur_server_type'   => __('Typ', 'cmx-misbuero'),
		'cmx_infrastruktur_server_system' => __('System', 'cmx-misbuero'),
		'cmx_infrastruktur_server_ip'     => __('IP-Adresse', 'cmx-misbuero'),
		'cmx_infrastruktur_server_cpu'    => __('CPU', 'cmx-misbuero'),
		'cmx_infrastruktur_server_ram'    => __('RAM', 'cmx-misbuero'),
		'cmx_infrastruktur_server_storage'=> __('Festplatte', 'cmx-misbuero'),
		'cmx_infrastruktur_network_b'     => 'B',
		'cmx_infrastruktur_network_p'     => 'P',
		'cmx_infrastruktur_firewall'      => __('Firewall', 'cmx-misbuero'),
		'cmx_infrastruktur_einheit'       => __('Einheit', 'cmx-misbuero'),
		'cmx_infrastruktur_sku'           => __('SKU', 'cmx-misbuero'),
		'cmx_infrastruktur_ek'            => __('EK', 'cmx-misbuero'),
		'cmx_infrastruktur_vk'            => __('VK', 'cmx-misbuero'),
	];
}, 100);

\add_action('manage_infrastruktur_posts_custom_column', function (string $column, int $post_id): void {
	switch ($column) {
		case 'cmx_infrastruktur_kontakt':
			$kontakt_id = (int) \get_post_meta($post_id, CMX_INFRASTRUKTUR_KONTAKT_META, true);
			echo $kontakt_id > 0 ? \esc_html((string) \get_the_title($kontakt_id)) : '';
			break;

		case 'cmx_infrastruktur_server_type':
			$types = \wp_get_object_terms($post_id, CMX_TAX_INFRASTRUKTUR_SERVER_TYPE, ['fields' => 'names']);
			echo \esc_html(!\is_wp_error($types) && $types !== [] ? \implode(', ', $types) : 'VPS');
			break;

		case 'cmx_infrastruktur_server_system':
			$system = \sanitize_key((string) \get_post_meta($post_id, CMX_INFRASTRUKTUR_SERVER_SYSTEM_META, true));
			echo \esc_html($system === 'windows' ? 'Windows' : 'Linux');
			break;

		case 'cmx_infrastruktur_server_ip':
			$ip = (string) \get_post_meta($post_id, CMX_INFRASTRUKTUR_SERVER_IP_META, true);
			echo $ip === '' ? '' : \esc_html($ip);
			break;

		case 'cmx_infrastruktur_server_cpu':
			$cpu = cmx_infrastruktur_server_numeric_value(\get_post_meta($post_id, CMX_INFRASTRUKTUR_SERVER_CPU_META, true));
			echo $cpu === '' ? '' : \esc_html($cpu);
			break;

		case 'cmx_infrastruktur_server_ram':
			$ram = cmx_infrastruktur_server_numeric_value(\get_post_meta($post_id, CMX_INFRASTRUKTUR_SERVER_RAM_META, true));
			echo $ram === '' ? '' : \esc_html($ram);
			break;

		case 'cmx_infrastruktur_server_storage':
			$storage = cmx_infrastruktur_server_numeric_value(\get_post_meta($post_id, CMX_INFRASTRUKTUR_SERVER_STORAGE_META, true));
			$storage_type_key = \sanitize_key((string) \get_post_meta($post_id, CMX_INFRASTRUKTUR_SERVER_STORAGE_TYPE_META, true));
			$storage_type = [
				'hdd'  => 'HDD',
				'ssd'  => 'SSD',
				'nvme' => 'NVMe',
			][$storage_type_key] ?? '';
			$value = \trim($storage . ' ' . $storage_type);
			echo $value === '' ? '' : \esc_html($value);
			break;

		case 'cmx_infrastruktur_network_b':
		case 'cmx_infrastruktur_network_p':
			$networks = \get_post_meta($post_id, CMX_INFRASTRUKTUR_SERVER_NETWORK_META, true);
			$networks = \is_array($networks) ? \array_map('sanitize_key', $networks) : [];
			$network = $column === 'cmx_infrastruktur_network_b' ? 'public' : 'private';
			if (\in_array($network, $networks, true)) {
				echo '<input type="checkbox" checked disabled aria-label="'
					. \esc_attr($network === 'public' ? __('Public', 'cmx-misbuero') : __('Private', 'cmx-misbuero'))
					. '">';
			}
			break;

		case 'cmx_infrastruktur_firewall':
			$firewall = (int) \get_post_meta($post_id, CMX_INFRASTRUKTUR_SERVER_FIREWALL_META, true) === 1;
			echo \esc_html($firewall ? __('Ja', 'cmx-misbuero') : __('Nein', 'cmx-misbuero'));
			break;

		case 'cmx_infrastruktur_einheit':
			$einheit = \sanitize_key((string) \get_post_meta($post_id, CMX_INFRASTRUKTUR_EINHEIT_META, true));
			$einheiten = [
				'monatlich'   => __('Monatlich', 'cmx-misbuero'),
				'stuendlich'  => __('Stündlich', 'cmx-misbuero'),
				'jaehrlich'   => __('Jährlich', 'cmx-misbuero'),
			];
			echo isset($einheiten[$einheit]) ? \esc_html($einheiten[$einheit]) : '';
			break;

		case 'cmx_infrastruktur_sku':
			$sku = (string) \get_post_meta($post_id, CMX_INFRASTRUKTUR_SKU_META, true);
			echo $sku === '' ? '' : \esc_html($sku);
			break;

		case 'cmx_infrastruktur_ek':
		case 'cmx_infrastruktur_vk':
			$meta_key = $column === 'cmx_infrastruktur_ek'
				? CMX_INFRASTRUKTUR_EK_META
				: CMX_INFRASTRUKTUR_VK_META;
			$price = (string) \get_post_meta($post_id, $meta_key, true);
			echo $price === '' ? '' : \esc_html(cmx_format_swiss_number($price, 2));
			break;
	}
}, 10, 2);

\add_action('admin_head-edit.php', function (): void {
	$screen = \get_current_screen();
	if (!$screen instanceof \WP_Screen || $screen->post_type !== 'infrastruktur') {
		return;
	}
	?>
	<style>
		.post-type-infrastruktur .wp-list-table .column-title {
			width: 16%;
		}
		.post-type-infrastruktur .wp-list-table .column-cmx_infrastruktur_kontakt {
			width: 12%;
		}
		.post-type-infrastruktur .wp-list-table .column-cmx_infrastruktur_server_type,
		.post-type-infrastruktur .wp-list-table .column-cmx_infrastruktur_server_system,
		.post-type-infrastruktur .wp-list-table .column-cmx_infrastruktur_server_cpu,
		.post-type-infrastruktur .wp-list-table .column-cmx_infrastruktur_server_ram,
		.post-type-infrastruktur .wp-list-table .column-cmx_infrastruktur_firewall {
			width: 5%;
		}
		.post-type-infrastruktur .wp-list-table .column-cmx_infrastruktur_network_b,
		.post-type-infrastruktur .wp-list-table .column-cmx_infrastruktur_network_p {
			width: 3%;
			text-align: center;
		}
		.post-type-infrastruktur .wp-list-table .column-cmx_infrastruktur_network_b input,
		.post-type-infrastruktur .wp-list-table .column-cmx_infrastruktur_network_p input {
			margin: 0;
			border-radius: 4px;
		}
		.post-type-infrastruktur .wp-list-table .column-cmx_infrastruktur_server_ip {
			width: 10%;
		}
		.post-type-infrastruktur .wp-list-table .column-cmx_infrastruktur_server_storage {
			width: 9%;
		}
		.post-type-infrastruktur .wp-list-table .column-cmx_infrastruktur_einheit {
			width: 7%;
		}
		.post-type-infrastruktur .wp-list-table .column-cmx_infrastruktur_sku {
			width: 7%;
		}
		.post-type-infrastruktur .wp-list-table .column-cmx_infrastruktur_ek,
		.post-type-infrastruktur .wp-list-table .column-cmx_infrastruktur_vk {
			width: 5%;
			text-align: right;
		}
	</style>
	<?php
});

const CMX_INFRASTRUKTUR_CLOUD_INIT_SOURCE = 'https://gitlab.com/cloud-meister/cloudmeister-systems/-/blob/main/cloud-init/bootstrap.yml';
const CMX_INFRASTRUKTUR_CLOUD_INIT_RAW = 'https://gitlab.com/cloud-meister/cloudmeister-systems/-/raw/main/cloud-init/bootstrap.yml';
const CMX_INFRASTRUKTUR_DOCS_SOURCE = 'https://gitlab.com/cloud-meister/cloudmeister-systems/-/tree/main/docs';
const CMX_INFRASTRUKTUR_DOCS_API = 'https://gitlab.com/api/v4/projects/84764261/repository/tree?path=docs&ref=main&per_page=100';
const CMX_INFRASTRUKTUR_DOCS_RAW_BASE = 'https://gitlab.com/cloud-meister/cloudmeister-systems/-/raw/main/';
const CMX_INFRASTRUKTUR_CONFIG_RAW = 'https://gitlab.com/cloud-meister/cloudmeister-systems/-/raw/main/config.ini';

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_replace_cloud_init_placeholder')) {
	function cmx_infrastruktur_replace_cloud_init_placeholder(string $text, string $name, string $value): string {
		$pattern = '~{{\h*' . \preg_quote($name, '~') . '\h*}}~u';
		$value = \str_replace(["\r\n", "\r"], "\n", $value);
		$lines = \explode("\n", $text);

		foreach ($lines as &$line) {
			$offset = 0;
			while (\preg_match($pattern, $line, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
				$placeholder = (string) $matches[0][0];
				$position = (int) $matches[0][1];
				$prefix = \substr($line, 0, $position);
				$multiline_prefix = \preg_match('/^[\t ]*(?:# ?)?$/', $prefix) ? $prefix : '';
				$replacement = $multiline_prefix !== ''
					? \str_replace("\n", "\n" . $multiline_prefix, $value)
					: $value;

				$line = \substr($line, 0, $position)
					. $replacement
					. \substr($line, $position + \strlen($placeholder));
				$offset = $position + \strlen($replacement);
			}
		}
		unset($line);

		return \implode("\n", $lines);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_normalize_fqdn')) {
	function cmx_infrastruktur_normalize_fqdn(string $fqdn): string {
		$fqdn = \trim($fqdn);
		$fqdn = (string) \preg_replace('~^[a-z][a-z0-9+.-]*://~i', '', $fqdn);
		$fqdn = (string) \preg_replace('~^[/\\\\]+~', '', $fqdn);
		$fqdn = (string) \preg_replace('~[/\\\\?#].*$~', '', $fqdn);

		return \rtrim($fqdn, "./\\ \t\n\r\0\x0B");
	}
}

\add_filter('wp_insert_post_data', function (array $data, array $postarr): array {
	if (
		(string) ($data['post_type'] ?? '') !== 'infrastruktur'
		|| \trim((string) ($data['post_title'] ?? '')) !== ''
	) {
		return $data;
	}

	$fqdn = isset($postarr['cmx_infrastruktur_fqdn'])
		? (string) $postarr['cmx_infrastruktur_fqdn']
		: (isset($_POST['cmx_infrastruktur_fqdn']) ? (string) \wp_unslash($_POST['cmx_infrastruktur_fqdn']) : '');
	$fqdn = cmx_infrastruktur_normalize_fqdn($fqdn);
	if ($fqdn !== '') {
		$data['post_title'] = $fqdn;
	}

	return $data;
}, 5, 2);

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_generate_admin_password')) {
	function cmx_infrastruktur_generate_admin_password(int $length = 24): string {
		$length = \max(4, $length);
		$groups = [
			'abcdefghijklmnopqrstuvwxyz',
			'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
			'0123456789',
			'-!?',
		];
		$characters = [];

		foreach ($groups as $group) {
			$characters[] = $group[\random_int(0, \strlen($group) - 1)];
		}

		$allowed = \implode('', $groups);
		while (\count($characters) < $length) {
			$characters[] = $allowed[\random_int(0, \strlen($allowed) - 1)];
		}

		for ($index = \count($characters) - 1; $index > 0; $index--) {
			$swap_index = \random_int(0, $index);
			[$characters[$index], $characters[$swap_index]] = [$characters[$swap_index], $characters[$index]];
		}

		return \implode('', $characters);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_cloud_init_admin_defaults')) {
	/**
	 * @return array{username:string,email:string,password:string}
	 */
	function cmx_infrastruktur_cloud_init_admin_defaults(): array {
		$contact_id = \function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_id')
			? (int) cmx_email_self_contact_id()
			: 0;
		$email = '';
		$username = '';

		if ($contact_id > 0) {
			$email = \function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_primary_email')
				? \sanitize_email((string) cmx_email_self_contact_primary_email())
				: '';

			$assigned_user_meta = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_ASSIGNED_USER_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_ASSIGNED_USER_META')
				: '_cmx_assigned_wp_user';
			$assigned_user_id = (int) \get_post_meta($contact_id, $assigned_user_meta, true);
			$assigned_user = $assigned_user_id > 0 ? \get_user_by('id', $assigned_user_id) : false;
			if ($assigned_user instanceof \WP_User) {
				$username = (string) $assigned_user->user_login;
			}

			if ($username === '' && \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_contact')) {
				$primary_contact = (array) cmx_kommunikation_primary_contact($contact_id);
				$username = (string) ($primary_contact['vorname'] ?? '');
			}
		}

		if ($username === '' && $email !== '' && \str_contains($email, '@')) {
			$username = (string) \strstr($email, '@', true);
		}

		$username = \strtolower(\remove_accents(\trim($username)));
		$username = (string) \preg_replace('/\s+/', '.', $username);
		$username = \trim(\sanitize_user($username, true), '._-');
		$username = \substr($username, 0, 32);

		return [
			'username' => $username,
			'email'    => \is_email($email) ? $email : '',
			'password' => cmx_infrastruktur_generate_admin_password(),
		];
	}
}

\add_action('wp_ajax_cmx_replace_cloud_init_placeholders', function (): void {
	\check_ajax_referer('cmx_replace_cloud_init_placeholders', 'cmx_cloud_init_ajax_nonce');

	if (!\current_user_can('edit_posts')) {
		\wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
	}

	$result = isset($_POST['cloud-init'])
		? (string) \wp_unslash($_POST['cloud-init'])
		: '';
	$version = isset($_POST['VERSION']) ? \trim((string) \wp_unslash($_POST['VERSION'])) : '';
	$admin_username = isset($_POST['ADMIN_USERNAME'])
		? \trim((string) \wp_unslash($_POST['ADMIN_USERNAME']))
		: '';
	$admin_email = isset($_POST['ADMIN_EMAIL'])
		? \sanitize_email((string) \wp_unslash($_POST['ADMIN_EMAIL']))
		: '';
	$admin_password = isset($_POST['ADMIN_PASSWORD'])
		? (string) \wp_unslash($_POST['ADMIN_PASSWORD'])
		: '';
	$playbook = isset($_POST['PLAYBOOK'])
		? \trim((string) \wp_unslash($_POST['PLAYBOOK']))
		: '';
	$ssh_key_id = isset($_POST['ADMIN_PUBLIC_KEY_ID'])
		? \sanitize_key((string) \wp_unslash($_POST['ADMIN_PUBLIC_KEY_ID']))
		: '';

	if ($playbook === '') {
		\wp_send_json_error(['message' => 'Bitte eine Vorlage auswählen.'], 400);
	}
	if (!\preg_match('/^[a-zA-Z0-9_.-]{1,32}$/', $admin_username)) {
		\wp_send_json_error([
			'message' => 'Der Benutzername darf nur Buchstaben, Zahlen, Punkt, Bindestrich und Unterstrich enthalten.',
		], 400);
	}
	if ($admin_email === '' || !\is_email($admin_email)) {
		\wp_send_json_error(['message' => 'Bitte eine gültige E-Mail-Adresse eingeben.'], 400);
	}
	if ($admin_password === '') {
		\wp_send_json_error(['message' => 'Bitte ein Kennwort eingeben.'], 400);
	}
	$valid_ssh_key_ids = [];
	foreach (cmx_get_admin_public_keys() as $ssh_public_key) {
		$valid_ssh_key_ids[] = cmx_ssh_public_key_id($ssh_public_key['key']);
	}
	if ($ssh_key_id === '' || !\in_array($ssh_key_id, $valid_ssh_key_ids, true)) {
		\wp_send_json_error(['message' => 'Bitte einen SSH-Schlüssel auswählen.'], 400);
	}

	$admin_public_keys_yaml = cmx_admin_public_keys_cloud_init('      ', $ssh_key_id);
	$result = (string) \preg_replace_callback(
		'/^[\t ]*(?:-\s*["\']?)?{{\s*ADMIN_PUBLIC_KEYS?\s*}}["\']?[\t ]*(\r?)$/m',
		static fn(array $matches): string => $admin_public_keys_yaml . (string) ($matches[1] ?? ''),
		$result
	);
	if (\preg_match('/{{\s*ADMIN_PUBLIC_KEYS?\s*}}/', $result)) {
		\wp_send_json_error(['message' => 'Der Public-Key-Platzhalter konnte nicht ersetzt werden.'], 400);
	}
	$maxmind_account_id = \trim((string) \get_option('MAXMIND_ACCOUNT_ID', ''));
	$maxmind_license_key = \trim((string) \get_option('MAXMIND_LICENSE_KEY', ''));
	if ($maxmind_account_id === '' && \preg_match('/{{\s*MAXMIND_ACCOUNT_ID\s*}}/', $result)) {
		\wp_send_json_error(['message' => 'Bitte die MaxMind Account ID in den Einstellungen hinterlegen.'], 400);
	}
	if ($maxmind_license_key === '' && \preg_match('/{{\s*MAXMIND_LICENSE_KEY\s*}}/', $result)) {
		\wp_send_json_error(['message' => 'Bitte den MaxMind License Key in den Einstellungen hinterlegen.'], 400);
	}

	$values = [
		'HOSTNAME'           => isset($_POST['HOSTNAME']) ? (string) \wp_unslash($_POST['HOSTNAME']) : '',
		'FQDN'               => isset($_POST['FQDN'])
			? cmx_infrastruktur_normalize_fqdn((string) \wp_unslash($_POST['FQDN']))
			: '',
		'ADMIN_USERNAME'     => $admin_username,
		'ADMIN_EMAIL'        => $admin_email,
		'ADMIN_PASSWORD'     => $admin_password,
		'VERSION'            => $version !== '' ? $version : 'main',
		'PLAYBOOK'           => $playbook,
		'MAXMIND_ACCOUNT_ID' => $maxmind_account_id,
		'MAXMIND_LICENSE_KEY' => $maxmind_license_key,
	];

	foreach ($values as $name => $value) {
		$result = cmx_infrastruktur_replace_cloud_init_placeholder($result, $name, $value);
	}
	if (\preg_match('~{{\h*MAXMIND_(?:ACCOUNT_ID|LICENSE_KEY)\h*}}~u', $result)) {
		\wp_send_json_error(['message' => 'Die MaxMind-Platzhalter konnten nicht ersetzt werden.'], 400);
	}

	\wp_send_json_success(['cloud_init' => $result]);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_cloud_init_template')) {
	/**
	 * Lädt nach Möglichkeit die aktuelle GitLab-Datei. Da das Repository privat
	 * sein kann, steht die beim Release aktuelle Datei zusätzlich lokal bereit.
	 *
	 * @return array{content:string,remote:bool}
	 */
	function cmx_infrastruktur_cloud_init_template(): array {
		$response = \wp_remote_get(CMX_INFRASTRUKTUR_CLOUD_INIT_RAW, [
			'timeout'     => 8,
			'redirection' => 2,
			'headers'     => [
				'Accept' => 'text/plain, text/yaml, application/yaml',
			],
		]);

		if (!\is_wp_error($response) && (int) \wp_remote_retrieve_response_code($response) === 200) {
			$content = \str_replace(["\r\n", "\r"], "\n", (string) \wp_remote_retrieve_body($response));
			$is_yaml = (
				\str_starts_with(\ltrim($content), '#cloud-config')
				|| \str_contains($content, '# cloud-init')
			)
				&& \str_contains($content, '{{ ADMIN_USERNAME }}')
				&& \str_contains($content, '{{ ADMIN_EMAIL }}')
				&& \str_contains($content, '{{ ADMIN_PASSWORD }}')
				&& !\str_contains(\strtolower($content), '<html');

			if ($is_yaml) {
				return [
					'content' => $content,
					'remote'  => true,
				];
			}
		}

		$fallback_path = __DIR__ . '/bootstrap.yml';
		$fallback = \is_readable($fallback_path) ? (string) \file_get_contents($fallback_path) : '';

		return [
			'content' => \str_replace(["\r\n", "\r"], "\n", $fallback),
			'remote'  => false,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_markdown_inline')) {
	function cmx_infrastruktur_markdown_inline(string $text): string {
		$parts = \preg_split(
			'/(\[[^\]]+\]\(https?:\/\/[^)\s]+\))/',
			$text,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);
		$html = '';

		foreach ($parts ?: [$text] as $part) {
			if (\preg_match('/^\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)$/', $part, $match)) {
				$html .= '<a href="' . \esc_url($match[2]) . '" target="_blank" rel="noopener noreferrer">'
					. \esc_html($match[1])
					. '</a>';
				continue;
			}

			$html .= \esc_html($part);
		}

		return $html;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_markdown_html')) {
	/**
	 * Rendert die in den Infrastruktur-Vorlagen verwendeten Markdown-Elemente.
	 * Nicht erkannte Zeilen bleiben als normaler Text vollständig sichtbar.
	 */
	function cmx_infrastruktur_markdown_html(string $markdown): string {
		$lines = \explode("\n", \str_replace(["\r\n", "\r"], "\n", $markdown));
		$html = '';
		$count = \count($lines);
		$index = 0;

		while ($index < $count) {
			$line = $lines[$index];

			if (\trim($line) === '') {
				$index++;
				continue;
			}

			if (\preg_match('/^```(?:[a-z0-9_-]+)?\s*$/i', \trim($line))) {
				$index++;
				$code = [];
				while ($index < $count && !\preg_match('/^```\s*$/', \trim($lines[$index]))) {
					$code[] = $lines[$index];
					$index++;
				}
				if ($index < $count) {
					$index++;
				}
				$html .= '<pre><code>' . \esc_html(\implode("\n", $code)) . '</code></pre>';
				continue;
			}

			if (
				$index + 1 < $count
				&& \str_contains($line, '|')
				&& \preg_match('/^\s*\|?\s*:?-{3,}:?\s*(?:\|\s*:?-{3,}:?\s*)+\|?\s*$/', $lines[$index + 1])
			) {
				$header_cells = \array_map('trim', \explode('|', \trim($line, " \t|")));
				$html .= '<div class="cmx-cloud-init-doc-table"><table class="widefat striped"><thead><tr>';
				foreach ($header_cells as $cell) {
					$html .= '<th>' . cmx_infrastruktur_markdown_inline($cell) . '</th>';
				}
				$html .= '</tr></thead><tbody>';
				$index += 2;

				while ($index < $count && \str_contains($lines[$index], '|') && \trim($lines[$index]) !== '') {
					$cells = \array_map('trim', \explode('|', \trim($lines[$index], " \t|")));
					$html .= '<tr>';
					foreach ($cells as $cell) {
						$html .= '<td>' . cmx_infrastruktur_markdown_inline($cell) . '</td>';
					}
					$html .= '</tr>';
					$index++;
				}

				$html .= '</tbody></table></div>';
				continue;
			}

			if (\preg_match('/^(#{1,6})\s+(.+)$/', $line, $match)) {
				$level = \min(6, \strlen($match[1]) + 2);
				$html .= '<h' . $level . '>' . cmx_infrastruktur_markdown_inline(\trim($match[2])) . '</h' . $level . '>';
				$index++;
				continue;
			}

			if (\preg_match('/^\s*[-*+]\s+(.+)$/', $line)) {
				$html .= '<ul>';
				while ($index < $count && \preg_match('/^\s*[-*+]\s+(.+)$/', $lines[$index], $match)) {
					$html .= '<li>' . cmx_infrastruktur_markdown_inline(\trim($match[1])) . '</li>';
					$index++;
				}
				$html .= '</ul>';
				continue;
			}

			if (\preg_match('/^\s*\d+[.)]\s+(.+)$/', $line)) {
				$html .= '<ol>';
				while ($index < $count && \preg_match('/^\s*\d+[.)]\s+(.+)$/', $lines[$index], $match)) {
					$html .= '<li>' . cmx_infrastruktur_markdown_inline(\trim($match[1])) . '</li>';
					$index++;
				}
				$html .= '</ol>';
				continue;
			}

			$paragraph = [\trim($line)];
			$index++;
			while (
				$index < $count
				&& \trim($lines[$index]) !== ''
				&& !\preg_match('/^(?:#{1,6}\s+|```|\s*[-*+]\s+|\s*\d+[.)]\s+)/', $lines[$index])
				&& !(
					$index + 1 < $count
					&& \str_contains($lines[$index], '|')
					&& \preg_match('/^\s*\|?\s*:?-{3,}:?\s*(?:\|\s*:?-{3,}:?\s*)+\|?\s*$/', $lines[$index + 1])
				)
			) {
				$paragraph[] = \trim($lines[$index]);
				$index++;
			}
			$html .= '<p>' . cmx_infrastruktur_markdown_inline(\implode(' ', $paragraph)) . '</p>';
		}

		return $html;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_cloud_init_playbooks')) {
	/**
	 * Liest die für Mis Büro freigegebenen Playbooks aus config.ini.
	 *
	 * @return array<string,string> Normalisierter Name => Name aus der Konfiguration.
	 */
	function cmx_infrastruktur_cloud_init_playbooks(): array {
		$response = \wp_remote_get(CMX_INFRASTRUKTUR_CONFIG_RAW, [
			'timeout'     => 8,
			'redirection' => 2,
			'headers'     => ['Accept' => 'text/plain'],
		]);
		if (\is_wp_error($response) || (int) \wp_remote_retrieve_response_code($response) !== 200) {
			return [];
		}

		$content = (string) \wp_remote_retrieve_body($response);
		if ($content === '' || \str_contains(\strtolower($content), '<html')) {
			return [];
		}

		$config = \parse_ini_string($content, true, \INI_SCANNER_RAW);
		if (!\is_array($config)) {
			return [];
		}

		$misbuero = [];
		foreach ($config as $section => $values) {
			if (\strcasecmp((string) $section, 'misbuero') === 0 && \is_array($values)) {
				$misbuero = $values;
				break;
			}
		}

		$configured = [];
		foreach ($misbuero as $key => $value) {
			if (\strcasecmp((string) $key, 'playbooks') !== 0) {
				continue;
			}

			foreach ((array) $value as $list) {
				foreach (\preg_split('/\s*,\s*/', \trim((string) $list)) ?: [] as $playbook) {
					$playbook = \trim($playbook);
					$playbook = (string) \preg_replace('/\.md$/i', '', \basename($playbook));
					$normalized = \sanitize_title(\str_replace('_', '-', $playbook));
					if ($playbook !== '' && $normalized !== '') {
						$configured[$normalized] = $playbook;
					}
				}
			}
		}

		return $configured;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_cloud_init_documents')) {
	/**
	 * @return array<int,array{filename:string,slug:string,playbook:string,title:string,content:string,source:string}>
	 */
	function cmx_infrastruktur_cloud_init_documents(): array {
		$playbooks = cmx_infrastruktur_cloud_init_playbooks();
		if (empty($playbooks)) {
			return [];
		}

		$response = \wp_remote_get(CMX_INFRASTRUKTUR_DOCS_API, [
			'timeout'     => 8,
			'redirection' => 2,
			'headers'     => ['Accept' => 'application/json'],
		]);

		if (\is_wp_error($response) || (int) \wp_remote_retrieve_response_code($response) !== 200) {
			return [];
		}

		$files = \json_decode((string) \wp_remote_retrieve_body($response), true);
		if (!\is_array($files)) {
			return [];
		}

		$documents = [];
		foreach ($files as $file) {
			$path = isset($file['path']) ? (string) $file['path'] : '';
			if (($file['type'] ?? '') !== 'blob' || !\preg_match('/\.md$/i', $path)) {
				continue;
			}

			$filename = \basename($path);
			$slug = \sanitize_key((string) \preg_replace('/\.md$/i', '', $filename));
			$document_key = \sanitize_title(\str_replace('_', '-', $slug));
			if (!isset($playbooks[$document_key])) {
				continue;
			}

			$path_parts = \array_map('rawurlencode', \explode('/', $path));
			$raw_url = CMX_INFRASTRUKTUR_DOCS_RAW_BASE . \implode('/', $path_parts);
			$file_response = \wp_remote_get($raw_url, [
				'timeout'     => 8,
				'redirection' => 2,
				'headers'     => ['Accept' => 'text/markdown, text/plain'],
			]);
			if (\is_wp_error($file_response) || (int) \wp_remote_retrieve_response_code($file_response) !== 200) {
				continue;
			}

			$content = \str_replace(["\r\n", "\r"], "\n", (string) \wp_remote_retrieve_body($file_response));
			if ($content === '' || \str_contains(\strtolower($content), '<html')) {
				continue;
			}

			$title = \preg_match('/^#\s+(.+)$/m', $content, $title_match)
				? \trim($title_match[1])
				: \ucwords(\str_replace(['-', '_'], ' ', $slug));

			$documents[] = [
				'filename' => $filename,
				'slug'     => $slug,
				'playbook' => $playbooks[$document_key],
				'title'    => $title,
				'content'  => $content,
				'source'   => 'https://gitlab.com/cloud-meister/cloudmeister-systems/-/blob/main/' . \implode('/', $path_parts),
			];
		}

		\usort($documents, static fn(array $left, array $right): int => \strnatcasecmp($left['title'], $right['title']));

		return $documents;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_is_new_post')) {
	function cmx_infrastruktur_is_new_post(\WP_Post $post): bool {
		return $post->post_status === 'auto-draft';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_render_saved_cloud_init_metabox')) {
	function cmx_infrastruktur_render_saved_cloud_init_metabox(\WP_Post $post): void {
		$values = [
			'playbook'       => (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_PLAYBOOK_META, true),
			'version'        => (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_VERSION_META, true),
			'hostname'       => (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_HOSTNAME_META, true),
			'fqdn'           => (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_FQDN_META, true),
			'admin_username' => (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_ADMIN_USERNAME_META, true),
			'admin_email'    => (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_ADMIN_EMAIL_META, true),
			'admin_password' => (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_ADMIN_PASSWORD_META, true),
			'cloud_init'     => (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_CLOUD_INIT_META, true),
		];
		$icon_show_url = \defined('CMX_PLUGIN_URL') ? (string) \constant('CMX_PLUGIN_URL') . 'assets/see.png' : '';
		$icon_hide_url = \defined('CMX_PLUGIN_URL') ? (string) \constant('CMX_PLUGIN_URL') . 'assets/hide.png' : '';

		\wp_nonce_field('cmx_infrastruktur_cloud_init_save', 'cmx_infrastruktur_cloud_init_nonce');
		?>
		<style>
			.cmx-infrastruktur-saved-cloud-init .form-table th {
				width: 160px;
			}

			.cmx-infrastruktur-saved-cloud-init input[type="text"],
			.cmx-infrastruktur-saved-cloud-init input[type="email"],
			.cmx-infrastruktur-saved-cloud-init input[type="password"] {
				width: 100%;
				max-width: 680px;
				min-height: 40px;
				border-radius: 8px;
			}

			.cmx-infrastruktur-saved-cloud-init textarea {
				width: 100%;
				min-height: 340px;
				border-radius: 8px;
				resize: vertical;
				white-space: pre;
			}

			.cmx-infrastruktur-saved-password {
				display: flex;
				align-items: stretch;
				gap: 6px;
				width: 100%;
				max-width: 720px;
			}

			.cmx-infrastruktur-saved-password input {
				flex: 1 1 auto;
				min-width: 0;
			}

			.cmx-infrastruktur-saved-password-toggle {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				flex: 0 0 34px;
				width: 34px;
				margin: 0;
				padding: 0;
				border: 0;
				border-radius: 6px;
				background: transparent;
				box-shadow: none;
				cursor: pointer;
			}

			.cmx-infrastruktur-saved-password-toggle:hover,
			.cmx-infrastruktur-saved-password-toggle:focus,
			.cmx-infrastruktur-saved-password-toggle:active {
				border: 0;
				background: #f0f6fc;
				box-shadow: none;
				outline: none;
			}

			.cmx-infrastruktur-saved-password-icon {
				display: block;
				width: 32px;
				height: 32px;
				background-position: center;
				background-repeat: no-repeat;
				background-size: contain;
			}

			.cmx-infrastruktur-saved-password-icon.is-show {
				background-image: url("<?php echo \esc_url($icon_show_url); ?>");
			}

			.cmx-infrastruktur-saved-password-icon.is-hide {
				background-image: url("<?php echo \esc_url($icon_hide_url); ?>");
			}
		</style>
		<div class="cmx-infrastruktur-saved-cloud-init">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="cmx-infrastruktur-saved-playbook"><?php echo \esc_html__('Vorlage / Playbook', 'cmx-misbuero'); ?></label></th>
						<td><input type="text" id="cmx-infrastruktur-saved-playbook" name="cmx_infrastruktur_playbook" value="<?php echo \esc_attr($values['playbook']); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="cmx-infrastruktur-saved-version"><?php echo \esc_html__('Version', 'cmx-misbuero'); ?></label></th>
						<td><input type="text" id="cmx-infrastruktur-saved-version" name="cmx_infrastruktur_version" value="<?php echo \esc_attr($values['version']); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="cmx-infrastruktur-saved-hostname"><?php echo \esc_html__('Hostname', 'cmx-misbuero'); ?></label></th>
						<td><input type="text" id="cmx-infrastruktur-saved-hostname" name="cmx_infrastruktur_hostname" value="<?php echo \esc_attr($values['hostname']); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="cmx-infrastruktur-saved-fqdn"><?php echo \esc_html__('FQDN', 'cmx-misbuero'); ?></label></th>
						<td><input type="text" id="cmx-infrastruktur-saved-fqdn" name="cmx_infrastruktur_fqdn" value="<?php echo \esc_attr($values['fqdn']); ?>" spellcheck="false" autocapitalize="none"></td>
					</tr>
					<tr>
						<th scope="row"><label for="cmx-infrastruktur-saved-username"><?php echo \esc_html__('Benutzername', 'cmx-misbuero'); ?></label></th>
						<td><input type="text" id="cmx-infrastruktur-saved-username" name="cmx_infrastruktur_admin_username" value="<?php echo \esc_attr($values['admin_username']); ?>" autocomplete="username"></td>
					</tr>
					<tr>
						<th scope="row"><label for="cmx-infrastruktur-saved-email"><?php echo \esc_html__('E-Mail', 'cmx-misbuero'); ?></label></th>
						<td><input type="email" id="cmx-infrastruktur-saved-email" name="cmx_infrastruktur_admin_email" value="<?php echo \esc_attr($values['admin_email']); ?>" autocomplete="email"></td>
					</tr>
					<tr>
						<th scope="row"><label for="cmx-infrastruktur-saved-password"><?php echo \esc_html__('Kennwort', 'cmx-misbuero'); ?></label></th>
						<td>
							<span class="cmx-infrastruktur-saved-password">
								<input type="password" id="cmx-infrastruktur-saved-password" name="cmx_infrastruktur_admin_password" value="<?php echo \esc_attr($values['admin_password']); ?>" autocomplete="new-password">
								<button type="button" id="cmx-infrastruktur-saved-password-toggle" class="cmx-infrastruktur-saved-password-toggle" aria-label="<?php echo \esc_attr__('Kennwort anzeigen', 'cmx-misbuero'); ?>" title="<?php echo \esc_attr__('Kennwort anzeigen', 'cmx-misbuero'); ?>">
									<span class="cmx-infrastruktur-saved-password-icon is-show" aria-hidden="true"></span>
								</button>
							</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cmx-infrastruktur-saved-cloud-init"><?php echo \esc_html__('cloud-Ini', 'cmx-misbuero'); ?></label></th>
						<td><textarea id="cmx-infrastruktur-saved-cloud-init" name="cmx_infrastruktur_cloud_init" class="large-text code" rows="17" spellcheck="false"><?php echo \esc_textarea($values['cloud_init']); ?></textarea></td>
					</tr>
				</tbody>
			</table>
		</div>
		<script>
		(function () {
			var password = document.getElementById("cmx-infrastruktur-saved-password");
			var toggle = document.getElementById("cmx-infrastruktur-saved-password-toggle");
			var fqdn = document.getElementById("cmx-infrastruktur-saved-fqdn");
			if (fqdn) {
				var normalizeFqdn = function () {
					fqdn.value = fqdn.value.trim()
						.replace(/^[a-z][a-z0-9+.-]*:\/\//i, "")
						.replace(/^[/\\]+/, "")
						.replace(/[/\\?#].*$/, "")
						.replace(/[./\\\s]+$/, "");
				};
				fqdn.addEventListener("blur", normalizeFqdn);
				fqdn.addEventListener("change", normalizeFqdn);
				fqdn.addEventListener("paste", function () {
					window.setTimeout(normalizeFqdn, 0);
				});
			}
			if (password && toggle) {
				var icon = toggle.querySelector(".cmx-infrastruktur-saved-password-icon");
				toggle.addEventListener("click", function () {
					var visible = password.type === "text";
					password.type = visible ? "password" : "text";
					var label = visible ? "Kennwort anzeigen" : "Kennwort ausblenden";
					toggle.setAttribute("aria-label", label);
					toggle.setAttribute("title", label);
					if (icon) {
						icon.className = "cmx-infrastruktur-saved-password-icon " + (visible ? "is-show" : "is-hide");
					}
					password.focus();
				});
			}
		})();
		</script>
		<?php
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_render_cloud_init_metabox')) {
	function cmx_infrastruktur_render_cloud_init_metabox(\WP_Post $post): void {
		\wp_nonce_field('cmx_infrastruktur_cloud_init_save', 'cmx_infrastruktur_cloud_init_nonce');
		echo '<div id="cmx-cloud-init-metabox-target"></div>';
	}
}

\add_action('add_meta_boxes_infrastruktur', function (\WP_Post $post): void {
	\add_meta_box(
		'cmx-infrastruktur-cloud-init',
		__('cloud-Ini', 'cmx-misbuero'),
		__NAMESPACE__ . '\\cmx_infrastruktur_render_cloud_init_metabox',
		'infrastruktur',
		'normal',
		'default'
	);
});

\add_action('save_post_infrastruktur', function (int $post_id): void {
	if (
		!isset($_POST['cmx_infrastruktur_cloud_init_nonce'])
		|| !\wp_verify_nonce(
			\sanitize_text_field(\wp_unslash($_POST['cmx_infrastruktur_cloud_init_nonce'])),
			'cmx_infrastruktur_cloud_init_save'
		)
		|| (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
		|| \wp_is_post_revision($post_id)
		|| !\current_user_can('edit_post', $post_id)
	) {
		return;
	}

	$text_fields = [
		'cmx_infrastruktur_playbook'       => CMX_INFRASTRUKTUR_PLAYBOOK_META,
		'cmx_infrastruktur_version'        => CMX_INFRASTRUKTUR_VERSION_META,
		'cmx_infrastruktur_hostname'       => CMX_INFRASTRUKTUR_HOSTNAME_META,
		'cmx_infrastruktur_admin_username' => CMX_INFRASTRUKTUR_ADMIN_USERNAME_META,
	];
	foreach ($text_fields as $field_name => $meta_key) {
		$value = isset($_POST[$field_name])
			? \sanitize_text_field(\wp_unslash($_POST[$field_name]))
			: '';
		if ($value === '') {
			\delete_post_meta($post_id, $meta_key);
		} else {
			\update_post_meta($post_id, $meta_key, $value);
		}
	}

	$fqdn = isset($_POST['cmx_infrastruktur_fqdn'])
		? cmx_infrastruktur_normalize_fqdn((string) \wp_unslash($_POST['cmx_infrastruktur_fqdn']))
		: '';
	if ($fqdn === '') {
		\delete_post_meta($post_id, CMX_INFRASTRUKTUR_FQDN_META);
	} else {
		\update_post_meta($post_id, CMX_INFRASTRUKTUR_FQDN_META, $fqdn);
	}

	$email = isset($_POST['cmx_infrastruktur_admin_email'])
		? \sanitize_email((string) \wp_unslash($_POST['cmx_infrastruktur_admin_email']))
		: '';
	if ($email === '') {
		\delete_post_meta($post_id, CMX_INFRASTRUKTUR_ADMIN_EMAIL_META);
	} else {
		\update_post_meta($post_id, CMX_INFRASTRUKTUR_ADMIN_EMAIL_META, $email);
	}

	$password = isset($_POST['cmx_infrastruktur_admin_password'])
		? (string) \wp_unslash($_POST['cmx_infrastruktur_admin_password'])
		: '';
	if ($password === '') {
		\delete_post_meta($post_id, CMX_INFRASTRUKTUR_ADMIN_PASSWORD_META);
	} else {
		\update_post_meta($post_id, CMX_INFRASTRUKTUR_ADMIN_PASSWORD_META, $password);
	}

	$cloud_init = isset($_POST['cmx_infrastruktur_cloud_init'])
		? (string) \wp_unslash($_POST['cmx_infrastruktur_cloud_init'])
		: '';
	if ($cloud_init === '') {
		\delete_post_meta($post_id, CMX_INFRASTRUKTUR_CLOUD_INIT_META);
	} else {
		\update_post_meta($post_id, CMX_INFRASTRUKTUR_CLOUD_INIT_META, $cloud_init);
	}
}, 10);

if (!\function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_render_cloud_init_footer')) {
	function cmx_infrastruktur_render_cloud_init_footer(): void {
	global $typenow, $post;

	if ($typenow !== 'infrastruktur') {
		return;
	}
	if (!\current_user_can('edit_posts')) {
		return;
	}
	if (!$post instanceof \WP_Post) {
		return;
	}

	$is_new = cmx_infrastruktur_is_new_post($post);
	$saved_values = [
		'playbook'       => (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_PLAYBOOK_META, true),
		'version'        => (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_VERSION_META, true),
		'hostname'       => (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_HOSTNAME_META, true),
		'fqdn'           => (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_FQDN_META, true),
		'admin_username' => (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_ADMIN_USERNAME_META, true),
		'admin_email'    => (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_ADMIN_EMAIL_META, true),
		'admin_password' => (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_ADMIN_PASSWORD_META, true),
		'cloud_init'     => (string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_CLOUD_INIT_META, true),
	];
	if (!$is_new && $saved_values['fqdn'] === '') {
		$title_fqdn = cmx_infrastruktur_normalize_fqdn((string) \get_the_title($post));
		if (\str_contains($title_fqdn, '.')) {
			$saved_values['fqdn'] = $title_fqdn;
		}
	}
	if (!$is_new && $saved_values['hostname'] === '' && $saved_values['fqdn'] !== '') {
		$saved_values['hostname'] = (string) \strstr($saved_values['fqdn'], '.', true);
	}
	$template = $is_new
		? cmx_infrastruktur_cloud_init_template()
		: ['content' => $saved_values['cloud_init'], 'remote' => false];
	$documents = $is_new
		? cmx_infrastruktur_cloud_init_documents()
		: ($saved_values['playbook'] !== ''
			? [[
				'filename' => $saved_values['playbook'],
				'slug'     => \sanitize_key($saved_values['playbook']),
				'playbook' => $saved_values['playbook'],
				'title'    => $saved_values['playbook'],
				'content'  => '',
				'source'   => CMX_INFRASTRUKTUR_DOCS_SOURCE,
			]]
			: []);
	$admin_defaults = cmx_infrastruktur_cloud_init_admin_defaults();
	$ssh_public_keys = cmx_get_admin_public_keys();
	$selected_ssh_key = \sanitize_key((string) \get_post_meta($post->ID, CMX_INFRASTRUKTUR_SERVER_SSH_KEY_META, true));
	$available_ssh_key_ids = [];
	foreach ($ssh_public_keys as $ssh_public_key) {
		$available_ssh_key_ids[] = cmx_ssh_public_key_id($ssh_public_key['key']);
	}
	if (!\in_array($selected_ssh_key, $available_ssh_key_ids, true)) {
		$selected_ssh_key = (string) ($available_ssh_key_ids[0] ?? '');
	}
	$ssh_settings_url = \admin_url(
		'admin.php?page=' . \rawurlencode(CMX_SETTINGS_SLUG) . '&tab=system&sub=security'
	) . '#cmx-admin-public-keys';
	$selected_template_title = $saved_values['playbook'];
	foreach ($documents as $document) {
		if ((string) $document['playbook'] === $saved_values['playbook']) {
			$selected_template_title = (string) $document['title'];
			break;
		}
	}
	$icon_show_url = \defined('CMX_PLUGIN_URL') ? (string) \constant('CMX_PLUGIN_URL') . 'assets/see.png' : '';
	$icon_hide_url = \defined('CMX_PLUGIN_URL') ? (string) \constant('CMX_PLUGIN_URL') . 'assets/hide.png' : '';
	?>
	<div id="cmx-cloud-init-view" hidden>
		<div class="cmx-cloud-init-form">
			<input type="hidden" id="cmx-cloud-init-ajax-nonce" value="<?php echo \esc_attr(\wp_create_nonce('cmx_replace_cloud_init_placeholders')); ?>">
			<?php if ($is_new && !$documents) : ?>
				<div class="notice notice-warning inline">
					<p><?php echo \esc_html__('Die Markdown-Vorlagen aus GitLab konnten nicht geladen werden.', 'cmx-misbuero'); ?></p>
				</div>
			<?php endif; ?>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="cmx-cloud-init-template-trigger"><?php echo \esc_html__('Vorlage', 'cmx-misbuero'); ?></label>
						</th>
						<td>
							<div class="cmx-cloud-init-template-fields">
								<div class="cmx-cloud-init-template-picker">
									<input type="hidden" id="cmx-cloud-init-playbook" name="cmx_infrastruktur_playbook" value="<?php echo \esc_attr($saved_values['playbook']); ?>">
									<button
										type="button"
										id="cmx-cloud-init-template-trigger"
										class="button cmx-cloud-init-template-trigger"
										aria-haspopup="listbox"
										aria-expanded="false"
										aria-required="true"
										aria-controls="cmx-cloud-init-template-options"
										<?php echo $documents ? '' : 'disabled'; ?>
									>
										<span><?php
											echo \esc_html(
												$selected_template_title !== ''
													? $selected_template_title
													: __('– Vorlage auswählen –', 'cmx-misbuero')
											);
										?></span>
										<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
									</button>
									<div id="cmx-cloud-init-template-options" class="cmx-cloud-init-template-options" role="listbox" hidden>
										<?php foreach ($documents as $index => $document) : ?>
											<?php $is_selected_document = (string) $document['playbook'] === $saved_values['playbook']; ?>
											<button
												type="button"
												class="cmx-cloud-init-template-option"
												role="option"
												aria-selected="<?php echo $is_selected_document ? 'true' : 'false'; ?>"
												data-document="<?php echo \esc_attr((string) $index); ?>"
												data-playbook="<?php echo \esc_attr($document['playbook']); ?>"
												data-title="<?php echo \esc_attr($document['title']); ?>"
											>
												<span><?php echo \esc_html($document['title']); ?></span>
												<code><?php echo \esc_html($document['filename']); ?></code>
											</button>
										<?php endforeach; ?>
									</div>
								</div>
								<label for="cmx-cloud-init-version"><?php echo \esc_html__('Version', 'cmx-misbuero'); ?></label>
								<span>
									<input type="text" id="cmx-cloud-init-version" name="cmx_infrastruktur_version" value="<?php echo \esc_attr($saved_values['version']); ?>" placeholder="v1.2.3" aria-describedby="cmx-cloud-init-version-description" spellcheck="false" autocapitalize="none">
									<span id="cmx-cloud-init-version-description" class="description"><?php echo \esc_html__('Wenn leer; wird die neueste Version „main“ verwendet.', 'cmx-misbuero'); ?></span>
								</span>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="cmx-cloud-init-hostname"><?php echo \esc_html__('Hostname', 'cmx-misbuero'); ?></label>
						</th>
						<td>
							<div class="cmx-cloud-init-host-fields">
								<input type="text" id="cmx-cloud-init-hostname" name="cmx_infrastruktur_hostname" value="<?php echo \esc_attr($saved_values['hostname']); ?>" placeholder="mail" spellcheck="false" autocapitalize="none">
								<label for="cmx-cloud-init-fqdn">FQDN</label>
								<span>
									<input type="text" id="cmx-cloud-init-fqdn" name="cmx_infrastruktur_fqdn" value="<?php echo \esc_attr($saved_values['fqdn']); ?>" placeholder="mail.example.com" aria-describedby="cmx-cloud-init-fqdn-description" spellcheck="false" autocapitalize="none">
									<span id="cmx-cloud-init-fqdn-description" class="description"><?php echo \esc_html__('Fully Qualified Domain Name', 'cmx-misbuero'); ?></span>
								</span>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="cloud-init"><?php echo \esc_html__('cloud-Ini', 'cmx-misbuero'); ?></label>
						</th>
						<td>
							<div class="cmx-cloud-init-admin-fields">
								<label for="cmx-cloud-init-admin-username">
									<span><?php echo \esc_html__('Benutzername', 'cmx-misbuero'); ?></span>
									<input type="text" id="cmx-cloud-init-admin-username" name="cmx_infrastruktur_admin_username" value="<?php echo \esc_attr($saved_values['admin_username']); ?>" maxlength="32" pattern="[a-zA-Z0-9_.-]+" autocomplete="username" spellcheck="false" autocapitalize="none" placeholder="<?php echo \esc_attr($admin_defaults['username']); ?>">
								</label>
								<label for="cmx-cloud-init-admin-email">
									<span><?php echo \esc_html__('E-Mail', 'cmx-misbuero'); ?></span>
									<input type="email" id="cmx-cloud-init-admin-email" name="cmx_infrastruktur_admin_email" value="<?php echo \esc_attr($saved_values['admin_email']); ?>" autocomplete="email" spellcheck="false" autocapitalize="none" placeholder="<?php echo \esc_attr($admin_defaults['email']); ?>">
								</label>
								<label for="cmx-cloud-init-admin-public-key">
									<span><a class="cmx-cloud-init-settings-link" href="<?php echo \esc_url($ssh_settings_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html__('Public Key', 'cmx-misbuero'); ?></a></span>
									<select id="cmx-cloud-init-admin-public-key" name="cmx_infrastruktur_server_ssh_key">
										<?php if (!$ssh_public_keys) : ?>
											<option value=""><?php echo \esc_html__('Keine SSH-Schlüssel hinterlegt', 'cmx-misbuero'); ?></option>
										<?php else : ?>
											<?php foreach ($ssh_public_keys as $ssh_public_key) : ?>
												<?php $ssh_public_key_id = cmx_ssh_public_key_id($ssh_public_key['key']); ?>
												<option value="<?php echo \esc_attr($ssh_public_key_id); ?>" <?php \selected($selected_ssh_key, $ssh_public_key_id); ?>><?php echo \esc_html($ssh_public_key['name']); ?></option>
											<?php endforeach; ?>
										<?php endif; ?>
									</select>
								</label>
								<div class="cmx-cloud-init-admin-field">
									<label for="cmx-cloud-init-admin-password"><?php echo \esc_html__('Kennwort', 'cmx-misbuero'); ?></label>
									<span class="cmx-cloud-init-password-controls">
										<span class="cmx-cloud-init-password-wrap">
											<input type="password" id="cmx-cloud-init-admin-password" name="cmx_infrastruktur_admin_password" value="<?php echo \esc_attr($saved_values['admin_password']); ?>" autocomplete="new-password" placeholder="<?php echo \esc_attr(\str_repeat('•', \strlen($admin_defaults['password']))); ?>" data-placeholder-value="<?php echo \esc_attr($admin_defaults['password']); ?>">
											<button type="button" id="cmx-cloud-init-password-toggle" class="button-link cmx-cloud-init-password-toggle" aria-label="<?php echo \esc_attr__('Kennwort anzeigen', 'cmx-misbuero'); ?>" aria-pressed="false" title="<?php echo \esc_attr__('Kennwort anzeigen', 'cmx-misbuero'); ?>">
												<span class="cmx-cloud-init-password-icon is-show" aria-hidden="true"></span>
											</button>
										</span>
										<button type="button" id="cmx-cloud-init-password-generate" class="button-link cmx-cloud-init-password-generate" aria-label="<?php echo \esc_attr__('Neues Kennwort erzeugen', 'cmx-misbuero'); ?>" title="<?php echo \esc_attr($is_new ? __('Neues Kennwort erzeugen', 'cmx-misbuero') : __('Im Bearbeitungsmodus deaktiviert', 'cmx-misbuero')); ?>"<?php echo $is_new ? '' : ' disabled aria-disabled="true"'; ?>>
											<span class="dashicons dashicons-update" aria-hidden="true"></span>
										</button>
									</span>
								</div>
							</div>
							<textarea id="cloud-init" name="cmx_infrastruktur_cloud_init" class="large-text code" rows="17" spellcheck="false"><?php
								echo \esc_textarea((string) $template['content']);
							?></textarea>
							<p class="description cmx-cloud-init-source">
								<?php if (!$is_new) : ?>
									<?php echo \esc_html__('Gespeicherte cloud-ini', 'cmx-misbuero'); ?>
								<?php elseif ($template['remote']) : ?>
									<?php echo \esc_html__('Aktuelle Datei von', 'cmx-misbuero'); ?>
								<?php else : ?>
									<?php echo \esc_html__('Lokale Kopie der privaten GitLab-Datei von', 'cmx-misbuero'); ?>
								<?php endif; ?>
								<?php if ($is_new) : ?>
									<a href="<?php echo \esc_url(CMX_INFRASTRUKTUR_CLOUD_INIT_SOURCE); ?>" target="_blank" rel="noopener noreferrer">GitLab</a>
								<?php endif; ?>
							</p>
							<p class="cmx-cloud-init-actions">
								<button type="button" id="cmx-cloud-init-replace" class="button button-primary">
									<?php echo \esc_html__('Platzhalter ersetzen', 'cmx-misbuero'); ?>
								</button>
								<span id="cmx-cloud-init-status" class="cmx-cloud-init-status" role="status" aria-live="polite"></span>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
			<?php if ($is_new && $documents) : ?>
				<aside id="cmx-cloud-init-template-preview" class="cmx-cloud-init-template-preview" role="dialog" aria-modal="false" aria-labelledby="cmx-cloud-init-template-preview-title" hidden>
					<header class="cmx-cloud-init-template-preview-header">
						<div>
							<strong id="cmx-cloud-init-template-preview-title"><?php echo \esc_html__('Vorlagen-Vorschau', 'cmx-misbuero'); ?></strong>
							<code id="cmx-cloud-init-template-preview-file"></code>
						</div>
						<button type="button" id="cmx-cloud-init-template-preview-close" class="button-link" aria-label="<?php echo \esc_attr__('Vorschau schließen', 'cmx-misbuero'); ?>">
							<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
						</button>
					</header>
					<div class="cmx-cloud-init-template-preview-body">
						<?php foreach ($documents as $index => $document) : ?>
							<article class="cmx-cloud-init-document-content" data-document="<?php echo \esc_attr((string) $index); ?>" hidden>
								<?php echo cmx_infrastruktur_markdown_html($document['content']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<p>
									<a href="<?php echo \esc_url($document['source']); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html__('Datei in GitLab öffnen', 'cmx-misbuero'); ?></a>
								</p>
							</article>
						<?php endforeach; ?>
					</div>
				</aside>
			<?php endif; ?>
		</div>
	</div>
	<style>
		#cmx-cloud-init-view {
			clear: both;
			padding: 0;
		}
		.cmx-cloud-init-form {
			max-width: none;
		}
		.cmx-cloud-init-documents {
			margin: 20px 0 8px;
		}
		.cmx-cloud-init-template-picker {
			position: relative;
			width: 100%;
		}
		.cmx-cloud-init-form .cmx-cloud-init-template-trigger {
			display: flex !important;
			justify-content: space-between;
			align-items: center;
			width: 100%;
			min-height: 32px;
			border-radius: 8px !important;
			text-align: left;
		}
		.cmx-cloud-init-template-trigger.is-error {
			border-color: #d63638;
			box-shadow: 0 0 0 1px #d63638;
		}
		.cmx-cloud-init-template-trigger .dashicons {
			display: flex;
			align-items: center;
			justify-content: center;
			width: 20px;
			height: 20px;
			margin: 0;
			line-height: 1;
		}
		.cmx-cloud-init-template-options {
			position: absolute;
			z-index: 100110;
			top: calc(100% + 4px);
			left: 0;
			width: 100%;
			overflow: hidden;
			border: 1px solid #c3c4c7;
			border-radius: 8px;
			background: #fff;
			box-shadow: 0 8px 24px rgba(0, 0, 0, .16);
		}
		.cmx-cloud-init-template-option {
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 16px;
			width: 100%;
			padding: 10px 12px;
			border: 0;
			border-bottom: 1px solid #f0f0f1;
			background: #fff;
			color: #1d2327;
			text-align: left;
			cursor: pointer;
		}
		.cmx-cloud-init-template-option:last-child {
			border-bottom: 0;
		}
		.cmx-cloud-init-template-option:hover,
		.cmx-cloud-init-template-option:focus,
		.cmx-cloud-init-template-option[aria-selected="true"] {
			outline: 0;
			background: #f0f6fc;
			color: #135e96;
		}
		.cmx-cloud-init-template-option code {
			color: #646970;
			font-size: 11px;
		}
		.cmx-cloud-init-template-preview {
			position: fixed;
			z-index: 100100;
			top: 92px;
			right: 28px;
			width: min(680px, calc(100vw - 56px));
			max-height: calc(100vh - 124px);
			overflow: hidden;
			border: 1px solid #8c8f94;
			border-radius: 8px;
			background: #fff;
			box-shadow: 0 18px 55px rgba(0, 0, 0, .28);
		}
		.cmx-cloud-init-template-preview-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 18px;
			padding: 14px 18px;
			border-bottom: 1px solid #dcdcde;
			background: #f6f7f7;
		}
		.cmx-cloud-init-template-preview-header strong,
		.cmx-cloud-init-template-preview-header code {
			display: block;
		}
		.cmx-cloud-init-template-preview-header code {
			margin-top: 3px;
			color: #646970;
		}
		#cmx-cloud-init-template-preview-close {
			color: #50575e;
			cursor: pointer;
			border: 0;
			text-decoration: none !important;
		}
		#cmx-cloud-init-template-preview-close:hover,
		#cmx-cloud-init-template-preview-close:focus,
		#cmx-cloud-init-template-preview-close:active {
			border: 0;
			box-shadow: none;
			outline: none;
			text-decoration: none !important;
		}
		#cmx-cloud-init-template-preview-close:focus-visible {
			border-radius: 4px;
			box-shadow: 0 0 0 2px #2271b1;
		}
		#cmx-cloud-init-template-preview-close .dashicons {
			width: 28px;
			height: 28px;
			font-size: 28px;
		}
		.cmx-cloud-init-template-preview-body {
			max-height: calc(100vh - 190px);
			overflow: auto;
			padding: 4px 22px 22px;
		}
		.cmx-cloud-init-document-content h3 {
			margin-top: 20px;
		}
		.cmx-cloud-init-document-content h4 {
			margin: 18px 0 8px;
		}
		.cmx-cloud-init-doc-table {
			overflow-x: auto;
			margin: 10px 0 18px;
		}
		.cmx-cloud-init-doc-table table {
			min-width: 620px;
		}
		.cmx-cloud-init-host-fields,
		.cmx-cloud-init-template-fields {
			display: grid;
			grid-template-columns: minmax(220px, 1fr) auto minmax(220px, 1fr);
			align-items: start;
			gap: 8px 16px;
			max-width: 800px;
		}
		.cmx-cloud-init-host-fields > label,
		.cmx-cloud-init-template-fields > label {
			padding-top: 6px;
			font-weight: 600;
		}
		.cmx-cloud-init-host-fields input,
		.cmx-cloud-init-template-fields input,
		.cmx-cloud-init-template-fields select {
			width: 100%;
		}
		.cmx-cloud-init-host-fields .description,
		.cmx-cloud-init-template-fields .description {
			display: block;
			margin-top: 4px;
		}
		.cmx-cloud-init-admin-fields {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
			gap: 16px;
			width: 100%;
			margin-bottom: 12px;
		}
		.cmx-cloud-init-admin-fields label,
		.cmx-cloud-init-admin-fields > label > span {
			display: block;
		}
		.cmx-cloud-init-admin-fields > label > span,
		.cmx-cloud-init-admin-field > label {
			display: block;
			margin-bottom: 4px;
			font-weight: 600;
		}
		.cmx-cloud-init-admin-fields input,
		.cmx-cloud-init-admin-fields select {
			width: 100%;
		}
		.cmx-cloud-init-settings-link,
		.cmx-cloud-init-settings-link:visited,
		.cmx-cloud-init-settings-link:focus {
			color: inherit;
			text-decoration: none;
		}
		.cmx-cloud-init-settings-link:hover {
			text-decoration: underline;
			text-underline-offset: 2px;
		}
		.cmx-cloud-init-password-controls {
			display: flex;
			align-items: stretch;
			gap: 6px;
			width: 100%;
		}
		.cmx-cloud-init-password-wrap {
			position: relative;
			display: flex;
			flex: 1 1 auto;
			min-width: 0;
		}
		.cmx-cloud-init-password-wrap #cmx-cloud-init-admin-password {
			padding-right: 62px;
		}
		.cmx-cloud-init-password-toggle {
			position: absolute;
			top: 50%;
			right: 4px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 52px;
			height: calc(100% - 4px);
			margin: 0;
			padding: 0;
			transform: translateY(-50%);
			border: 0;
			border-radius: 5px;
			background: transparent;
			box-shadow: none;
			text-decoration: none !important;
		}
		.cmx-cloud-init-password-generate {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			align-self: stretch;
			flex: 0 0 34px;
			width: 34px;
			min-width: 34px;
			height: auto !important;
			margin: 0;
			padding: 0;
			color: #2271b1;
			cursor: pointer;
			border: 0;
			border-radius: 4px;
			background: transparent;
			box-shadow: none;
			line-height: 1;
			text-decoration: none !important;
		}
		.cmx-cloud-init-password-generate .dashicons {
			display: block;
			width: 20px;
			height: 20px;
			font-size: 20px;
			line-height: 20px;
		}
		.cmx-cloud-init-password-generate:disabled {
			color: #a7aaad;
			cursor: not-allowed;
			opacity: .55;
			background: transparent;
		}
		.cmx-cloud-init-password-toggle:hover,
		.cmx-cloud-init-password-toggle:focus,
		.cmx-cloud-init-password-toggle:active {
			border: 0;
			background: transparent;
			box-shadow: none;
			outline: none;
			text-decoration: none !important;
		}
		.cmx-cloud-init-password-generate:hover,
		.cmx-cloud-init-password-generate:focus,
		.cmx-cloud-init-password-generate:active {
			color: #135e96;
			border: 0;
			background: #f0f6fc;
			box-shadow: none;
			outline: none;
			text-decoration: none !important;
		}
		.cmx-cloud-init-password-toggle .cmx-cloud-init-password-icon,
		.cmx-cloud-init-password-generate .dashicons {
			text-decoration: none !important;
		}
		.cmx-cloud-init-password-toggle:focus-visible,
		.cmx-cloud-init-password-generate:focus-visible {
			box-shadow: 0 0 0 2px #2271b1;
		}
		.cmx-cloud-init-password-icon {
			display: block;
			width: 48px;
			height: 32px;
			background-repeat: no-repeat;
			background-position: center;
			background-size: contain;
		}
		.cmx-cloud-init-password-icon.is-show {
			background-image: url("<?php echo \esc_url($icon_show_url); ?>");
		}
		.cmx-cloud-init-password-icon.is-hide {
			background-image: url("<?php echo \esc_url($icon_hide_url); ?>");
		}
		#cloud-init {
			width: 100%;
			min-height: 300px;
			resize: vertical;
			white-space: pre;
		}
		.cmx-cloud-init-source {
			width: 100%;
			margin: 4px 0 8px;
			text-align: right;
		}
		.cmx-cloud-init-actions {
			display: flex;
			align-items: center;
			gap: 10px;
			margin: 0;
		}
		.cmx-cloud-init-status {
			color: #2271b1;
		}
		.cmx-cloud-init-status.is-error {
			color: #b32d2e;
		}
		@media (max-width: 782px) {
			#cloud-init {
				width: 100%;
			}
			.cmx-cloud-init-admin-fields {
				grid-template-columns: 1fr;
				width: 100%;
			}
			.cmx-cloud-init-source {
				width: 100%;
			}
			.cmx-cloud-init-template-preview {
				top: 52px;
				right: 12px;
				width: calc(100vw - 24px);
				max-height: calc(100vh - 64px);
			}
			.cmx-cloud-init-template-preview-body {
				max-height: calc(100vh - 130px);
			}
			.cmx-cloud-init-host-fields,
			.cmx-cloud-init-template-fields {
				grid-template-columns: 1fr;
			}
			.cmx-cloud-init-host-fields > label,
			.cmx-cloud-init-template-fields > label {
				padding-top: 0;
			}
		}
	</style>
	<script>
		(function () {
			function copyText(text) {
				function fallbackCopy() {
					var copyField = document.createElement('textarea');
					copyField.value = text;
					copyField.setAttribute('readonly', '');
					copyField.style.position = 'fixed';
					copyField.style.opacity = '0';
					document.body.appendChild(copyField);
					copyField.select();

					var copied = false;
					try {
						copied = document.execCommand('copy');
					} catch (error) {
						copied = false;
					}

					document.body.removeChild(copyField);
					return copied;
				}

				if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function' && window.isSecureContext) {
					return navigator.clipboard.writeText(text).then(function () {
						return true;
					}).catch(fallbackCopy);
				}

				return Promise.resolve(fallbackCopy());
			}

			function bindCloudInitActions() {
				var container = document.querySelector('.cmx-cloud-init-form');
				var cloudInit = document.getElementById('cloud-init');
				var button = document.getElementById('cmx-cloud-init-replace');
				var status = document.getElementById('cmx-cloud-init-status');
				var hostname = document.getElementById('cmx-cloud-init-hostname');
				var fqdn = document.getElementById('cmx-cloud-init-fqdn');
				var ajaxNonce = document.getElementById('cmx-cloud-init-ajax-nonce');
				var fqdnLabel = document.querySelector('label[for="cmx-cloud-init-fqdn"]');
				var version = document.getElementById('cmx-cloud-init-version');
				var versionLabel = document.querySelector('label[for="cmx-cloud-init-version"]');
				var playbook = document.getElementById('cmx-cloud-init-playbook');
				var adminUsername = document.getElementById('cmx-cloud-init-admin-username');
				var adminUsernameLabel = document.querySelector('label[for="cmx-cloud-init-admin-username"]');
				var adminEmail = document.getElementById('cmx-cloud-init-admin-email');
				var adminEmailLabel = document.querySelector('label[for="cmx-cloud-init-admin-email"]');
				var password = document.getElementById('cmx-cloud-init-admin-password');
				var passwordLabel = document.querySelector('label[for="cmx-cloud-init-admin-password"]');
				var passwordGenerate = document.getElementById('cmx-cloud-init-password-generate');
				var passwordToggle = document.getElementById('cmx-cloud-init-password-toggle');
				var sshPublicKey = document.getElementById('cmx-cloud-init-admin-public-key');
				var templatePicker = document.querySelector('.cmx-cloud-init-template-picker');
				var templateTrigger = document.getElementById('cmx-cloud-init-template-trigger');
				var templateOptionsBox = document.getElementById('cmx-cloud-init-template-options');
				var templateOptions = Array.from(document.querySelectorAll('.cmx-cloud-init-template-option'));
				var preview = document.getElementById('cmx-cloud-init-template-preview');
				var previewTitle = document.getElementById('cmx-cloud-init-template-preview-title');
				var previewFile = document.getElementById('cmx-cloud-init-template-preview-file');
				var previewClose = document.getElementById('cmx-cloud-init-template-preview-close');
				var previewDocuments = Array.from(document.querySelectorAll('.cmx-cloud-init-document-content'));

				if (!container || !cloudInit || !button || !status || !ajaxNonce) {
					return;
				}

				if (password && passwordToggle) {
					var passwordIcon = passwordToggle.querySelector('.cmx-cloud-init-password-icon');
					var syncPasswordToggle = function () {
						var visible = password.type === 'text';
						var label = visible ? 'Kennwort ausblenden' : 'Kennwort anzeigen';
						passwordToggle.setAttribute('aria-label', label);
						passwordToggle.setAttribute('title', label);
						passwordToggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
						if (passwordIcon) {
							passwordIcon.className = 'cmx-cloud-init-password-icon ' + (visible ? 'is-hide' : 'is-show');
						}
					};

					passwordToggle.addEventListener('click', function () {
						password.type = password.type === 'password' ? 'text' : 'password';
						syncPasswordToggle();
						password.focus();
					});
					syncPasswordToggle();
				}

				if (password && passwordGenerate && !passwordGenerate.disabled) {
					var secureRandomIndex = function (length) {
						if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
							var values = new Uint32Array(1);
							var maximum = Math.floor(0x100000000 / length) * length;
							do {
								window.crypto.getRandomValues(values);
							} while (values[0] >= maximum);
							return values[0] % length;
						}
						return Math.floor(Math.random() * length);
					};
					var generatePassword = function (length) {
						var groups = [
							'abcdefghijklmnopqrstuvwxyz',
							'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
							'0123456789',
							'-!?'
						];
						var characters = groups.map(function (group) {
							return group.charAt(secureRandomIndex(group.length));
						});
						var allowed = groups.join('');
						while (characters.length < length) {
							characters.push(allowed.charAt(secureRandomIndex(allowed.length)));
						}
						for (var index = characters.length - 1; index > 0; index--) {
							var swapIndex = secureRandomIndex(index + 1);
							var character = characters[index];
							characters[index] = characters[swapIndex];
							characters[swapIndex] = character;
						}
						return characters.join('');
					};

					passwordGenerate.addEventListener('click', function () {
						var generatedPassword = generatePassword(24);
						password.setAttribute('data-placeholder-value', generatedPassword);
						password.placeholder = '•'.repeat(generatedPassword.length);
						password.value = generatedPassword;
						password.dispatchEvent(new Event('input', {bubbles: true}));
						password.focus();
						password.select();
						status.classList.remove('is-error');
						status.textContent = 'Neues Kennwort erzeugt.';
					});
				}

				if (version && versionLabel) {
					versionLabel.addEventListener('click', function () {
						version.value = 'main';
					});
				}

				var bindPlaceholderLabel = function (field, label) {
					if (!field || !label) {
						return;
					}
					label.addEventListener('click', function (event) {
						var placeholderValue = field.getAttribute('data-placeholder-value') || field.placeholder;
						if (event.target === field || !placeholderValue) {
							return;
						}
						field.value = placeholderValue;
						field.dispatchEvent(new Event('input', {bubbles: true}));
					});
				};
				bindPlaceholderLabel(adminUsername, adminUsernameLabel);
				bindPlaceholderLabel(adminEmail, adminEmailLabel);
				bindPlaceholderLabel(password, passwordLabel);
				if (password && passwordLabel) {
					passwordLabel.addEventListener('click', function () {
						password.type = 'text';
						if (typeof syncPasswordToggle === 'function') {
							syncPasswordToggle();
						}
					});
				}

				if (hostname && fqdn) {
					var normalizeFqdn = function (value) {
						return value.trim()
							.replace(/^[a-z][a-z0-9+.-]*:\/\//i, '')
							.replace(/^[/\\]+/, '')
							.replace(/[/\\?#].*$/, '')
							.replace(/[./\\\s]+$/, '');
					};

					var updateFqdnPlaceholder = function () {
						var hostnameValue = hostname.value.trim() || hostname.placeholder || 'mail';
						fqdn.placeholder = hostnameValue + '.example.com';
					};

					hostname.addEventListener('input', updateFqdnPlaceholder);
					updateFqdnPlaceholder();
					var normalizeFqdnField = function () {
						var normalizedFqdn = normalizeFqdn(fqdn.value);
						if (normalizedFqdn !== fqdn.value) {
							fqdn.value = normalizedFqdn;
						}
					};
					fqdn.addEventListener('blur', normalizeFqdnField);
					fqdn.addEventListener('change', normalizeFqdnField);
					fqdn.addEventListener('paste', function () {
						window.setTimeout(normalizeFqdnField, 0);
					});

					var activateFqdn = function () {
						if (!fqdn.value) {
							fqdn.value = fqdn.placeholder;
						}

						var prefix = hostname.value.trim() + '.';
						var selectionStart = prefix !== '.' && fqdn.value.indexOf(prefix) === 0
							? prefix.length
							: fqdn.value.indexOf('.') + 1;

						fqdn.focus();
						fqdn.setSelectionRange(Math.max(0, selectionStart), fqdn.value.length);
					};

					fqdn.addEventListener('click', function () {
						activateFqdn();
					});

					if (fqdnLabel) {
						fqdnLabel.addEventListener('click', function () {
							if (!fqdn.value) {
								fqdn.value = fqdn.placeholder;
							}
						});
					}

				}

				var applyDefaultValues = function () {
					if (hostname && hostname.value.trim() === '') {
						hostname.value = hostname.placeholder || '';
					}
					if (fqdn && fqdn.value.trim() === '') {
						fqdn.value = fqdn.placeholder || '';
					}
					if (fqdn && typeof normalizeFqdn === 'function') {
						fqdn.value = normalizeFqdn(fqdn.value);
					}
					if (version && version.value.trim() === '') {
						version.value = 'main';
					}
					if (adminUsername && adminUsername.value.trim() === '') {
						adminUsername.value = adminUsername.placeholder || '';
					}
					if (adminEmail && adminEmail.value.trim() === '') {
						adminEmail.value = adminEmail.placeholder || '';
					}
					if (password && password.value === '') {
						password.value = password.getAttribute('data-placeholder-value') || '';
					}
				};

				var postForm = document.getElementById('post');
				if (postForm) {
					postForm.addEventListener('submit', function () {
						applyDefaultValues();
					});
				}

				if (playbook && templatePicker && templateTrigger && templateOptionsBox && templateOptions.length) {
					var showTemplatePreview = function (option) {
						var documentId = option.getAttribute('data-document');
						var documentContent = previewDocuments.find(function (item) {
							return item.getAttribute('data-document') === documentId;
						});
						if (!preview || !documentContent) {
							return;
						}

						previewDocuments.forEach(function (item) {
							item.hidden = item !== documentContent;
						});
						if (previewTitle) {
							previewTitle.textContent = option.getAttribute('data-title') || 'Vorlagen-Vorschau';
						}
						if (previewFile) {
							var filename = option.querySelector('code');
							previewFile.textContent = filename ? filename.textContent : '';
						}
						preview.hidden = false;
					};

					var hideTemplatePreview = function () {
						if (preview) {
							preview.hidden = true;
						}
					};

					var closeTemplateOptions = function () {
						templateOptionsBox.hidden = true;
						templateTrigger.setAttribute('aria-expanded', 'false');
					};

					var selectTemplate = function (option) {
						playbook.value = option.getAttribute('data-playbook') || '';
						templateTrigger.classList.remove('is-error');
						templateTrigger.removeAttribute('aria-invalid');
						templateOptions.forEach(function (otherOption) {
							otherOption.setAttribute('aria-selected', otherOption === option ? 'true' : 'false');
						});
						var triggerLabel = templateTrigger.querySelector('span');
						if (triggerLabel) {
							triggerLabel.textContent = option.getAttribute('data-title') || option.textContent.trim();
						}
						if (hostname) {
							var fqdnSuffix = '.example.com';
							if (fqdn) {
								var currentFqdn = normalizeFqdn(fqdn.value) || fqdn.placeholder || '';
								var firstDot = currentFqdn.indexOf('.');
								if (firstDot >= 0 && firstDot < currentFqdn.length - 1) {
									fqdnSuffix = currentFqdn.substring(firstDot);
								}
							}
							hostname.value = playbook.value;
							if (typeof updateFqdnPlaceholder === 'function') {
								updateFqdnPlaceholder();
							}
							if (fqdn) {
								fqdn.value = hostname.value.trim() + fqdnSuffix;
							}
						}
						closeTemplateOptions();
						hideTemplatePreview();
						if (fqdn) {
							if (typeof activateFqdn === 'function') {
								activateFqdn();
							} else {
								fqdn.focus();
							}
						}
						status.classList.remove('is-error');
						status.textContent = 'Vorlage „' + (option.getAttribute('data-title') || '') + '“ ausgewählt.';
					};

					templateTrigger.addEventListener('click', function () {
						var willOpen = templateOptionsBox.hidden;
						templateOptionsBox.hidden = !willOpen;
						templateTrigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
						if (willOpen) {
							var selectedOption = templateOptions.find(function (option) {
								return option.getAttribute('aria-selected') === 'true';
							});
							(selectedOption || templateOptions[0]).focus();
						}
					});

					templateTrigger.addEventListener('mouseenter', function () {
						var selectedOption = templateOptions.find(function (option) {
							return option.getAttribute('aria-selected') === 'true';
						});
						if (selectedOption) {
							showTemplatePreview(selectedOption);
						}
					});

					templateOptions.forEach(function (option, optionIndex) {
						option.addEventListener('mouseenter', function () {
							showTemplatePreview(option);
						});
						option.addEventListener('focus', function () {
							showTemplatePreview(option);
						});
						option.addEventListener('click', function () {
							selectTemplate(option);
						});
						option.addEventListener('keydown', function (event) {
							if (event.key === 'Enter') {
								event.preventDefault();
								event.stopImmediatePropagation();
								return;
							}
							if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
								event.preventDefault();
								var direction = event.key === 'ArrowDown' ? 1 : -1;
								var nextIndex = (optionIndex + direction + templateOptions.length) % templateOptions.length;
								templateOptions[nextIndex].focus();
							}
							if (event.key === 'Escape') {
								event.preventDefault();
								closeTemplateOptions();
								hideTemplatePreview();
								templateTrigger.focus();
							}
						});
						option.addEventListener('keypress', function (event) {
							if (event.key === 'Enter') {
								event.preventDefault();
								event.stopImmediatePropagation();
							}
						});
						option.addEventListener('keyup', function (event) {
							if (event.key === 'Enter') {
								event.preventDefault();
								event.stopImmediatePropagation();
								selectTemplate(option);
							}
						});
					});

					if (previewClose) {
						previewClose.addEventListener('click', hideTemplatePreview);
					}

					document.addEventListener('click', function (event) {
						if (!templatePicker.contains(event.target)) {
							closeTemplateOptions();
						}
						if (preview && !preview.hidden && !preview.contains(event.target)) {
							hideTemplatePreview();
						}
					});

					document.addEventListener('keydown', function (event) {
						if (event.key === 'Escape' && preview && !preview.hidden) {
							hideTemplatePreview();
						}
					});
				}

				button.addEventListener('click', function () {
					if (!playbook || playbook.value.trim() === '') {
						status.classList.add('is-error');
						status.textContent = 'Bitte eine Vorlage auswählen.';
						if (templateTrigger) {
							templateTrigger.classList.add('is-error');
							templateTrigger.setAttribute('aria-invalid', 'true');
							templateTrigger.focus();
						}
						return;
					}
					applyDefaultValues();
					if (!adminUsername || !/^[a-zA-Z0-9_.-]{1,32}$/.test(adminUsername.value)) {
						status.classList.add('is-error');
						status.textContent = 'Bitte einen gültigen Benutzernamen eingeben.';
						if (adminUsername) {
							adminUsername.focus();
						}
						return;
					}
					if (!adminEmail || !adminEmail.value || !adminEmail.checkValidity()) {
						status.classList.add('is-error');
						status.textContent = 'Bitte eine gültige E-Mail-Adresse eingeben.';
						if (adminEmail) {
							adminEmail.focus();
						}
						return;
					}
					if (!password || password.value === '') {
						status.classList.add('is-error');
						status.textContent = 'Bitte ein Kennwort eingeben.';
						if (password) {
							password.focus();
						}
						return;
					}
					if (!sshPublicKey || sshPublicKey.value === '') {
						status.classList.add('is-error');
						status.textContent = 'Bitte einen SSH-Schlüssel auswählen.';
						if (sshPublicKey) {
							sshPublicKey.focus();
						}
						return;
					}

					status.classList.remove('is-error');
					status.textContent = 'Platzhalter werden ersetzt …';
					button.disabled = true;

					var requestData = new FormData();
					requestData.append('action', 'cmx_replace_cloud_init_placeholders');
					requestData.append('cmx_cloud_init_ajax_nonce', ajaxNonce.value);
					requestData.append('cloud-init', cloudInit.value);
					requestData.append('PLAYBOOK', playbook.value);
					requestData.append('VERSION', version ? version.value : '');
					requestData.append('HOSTNAME', hostname ? hostname.value : '');
					requestData.append('FQDN', fqdn ? fqdn.value : '');
					requestData.append('ADMIN_USERNAME', adminUsername.value);
					requestData.append('ADMIN_EMAIL', adminEmail.value);
					requestData.append('ADMIN_PASSWORD', password.value);
					requestData.append('ADMIN_PUBLIC_KEY_ID', sshPublicKey ? sshPublicKey.value : '');

					fetch(window.ajaxurl, {
						method: 'POST',
						credentials: 'same-origin',
						body: requestData
					}).then(function (response) {
						return response.json();
					}).then(function (response) {
						if (!response.success || !response.data || typeof response.data.cloud_init !== 'string') {
							throw new Error(response.data && response.data.message ? response.data.message : '');
						}

						cloudInit.value = response.data.cloud_init;
						return copyText(cloudInit.value);
					}).then(function (copied) {
						status.classList.toggle('is-error', !copied);
						status.textContent = copied
							? 'Platzhalter ersetzt und cloud-Ini in die Zwischenablage kopiert.'
							: 'Platzhalter ersetzt. Der Text konnte nicht automatisch kopiert werden.';
					}).catch(function (error) {
						status.classList.add('is-error');
						status.textContent = error.message || 'Die Platzhalter konnten nicht ersetzt werden.';
					}).finally(function () {
						button.disabled = false;
					});
				});
			}

			function mountCloudInitForm() {
				var view = document.getElementById('cmx-cloud-init-view');
				var target = document.getElementById('cmx-cloud-init-metabox-target');
				if (!view || !target) {
					return;
				}

				target.appendChild(view);
				view.hidden = false;
				bindCloudInitActions();
			}

			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', mountCloudInitForm);
			} else {
				mountCloudInitForm();
			}
		}());
	</script>
	<?php
	}
}

\add_action('admin_footer-post.php', __NAMESPACE__ . '\\cmx_infrastruktur_render_cloud_init_footer');
\add_action('admin_footer-post-new.php', __NAMESPACE__ . '\\cmx_infrastruktur_render_cloud_init_footer');
