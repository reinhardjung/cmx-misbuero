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

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_active_subtab')) {
	function cmx_email_active_subtab(): string {
		if (!cmx_email_tab_is_active()) {
			return '';
		}
		$sub = isset($_GET['sub']) ? \sanitize_key((string) \wp_unslash($_GET['sub'])) : 'belege';
		return $sub !== '' ? $sub : 'belege';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_option_value')) {
	function cmx_email_option_value(string $key, string $default = ''): string {
		$options = (array) \get_option(CMX_SETTINGS_MAIN, []);
		$value = $options[$key] ?? $default;
		return \is_scalar($value) ? (string) $value : $default;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_normalize_client_list')) {
	function cmx_email_normalize_client_list($raw): array {
		$rows = \is_array($raw) ? $raw : [];
		$normalized = [];
		$index = 1;

		foreach ($rows as $row) {
			if (!\is_array($row)) {
				continue;
			}

			$client = \sanitize_text_field((string) ($row['client'] ?? ''));
			$name = \sanitize_text_field((string) ($row['name'] ?? ''));
			$email = \sanitize_email((string) ($row['email'] ?? ''));
			$password = \trim((string) ($row['password'] ?? ''));
			$smtp_host = \sanitize_text_field((string) ($row['smtp_host'] ?? ''));
			$smtp_port = (int) ($row['smtp_port'] ?? 587);
			$imap_host = \sanitize_text_field((string) ($row['imap_host'] ?? ''));
			$imap_port = (int) ($row['imap_port'] ?? 993);
			$kontakt_kategorien_enabled = !empty($row['kontakt_kategorien_enabled']) ? '1' : '0';
			$kontakt_kategorien = \function_exists(__NAMESPACE__ . '\\cmx_email_contact_category_selected_slugs')
				? cmx_email_contact_category_selected_slugs($row['kontakt_kategorien'] ?? [])
				: [];

			if ($smtp_port <= 0 || $smtp_port > 65535) {
				$smtp_port = 587;
			}
			if ($imap_port <= 0 || $imap_port > 65535) {
				$imap_port = 993;
			}

			if ($client === '' && $name === '' && $email === '' && $password === '' && $smtp_host === '' && $imap_host === '') {
				continue;
			}

			$id = \sanitize_key((string) ($row['id'] ?? ''));
			if ($id === '') {
				$id = 'client_' . $index;
			}

			$normalized[] = [
				'id'        => $id,
				'client'    => $client,
				'name'      => $name,
				'email'     => $email,
				'password'  => $password,
				'smtp_host' => $smtp_host,
				'smtp_port' => (string) $smtp_port,
				'imap_host' => $imap_host,
				'imap_port' => (string) $imap_port,
				'kontakt_kategorien_enabled' => $kontakt_kategorien_enabled,
				'kontakt_kategorien' => $kontakt_kategorien,
			];
			$index++;
		}

		return $normalized;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_contact_category_taxonomy')) {
	function cmx_email_contact_category_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_kontakte_notice_category_taxonomies')) {
			foreach ((array) cmx_kontakte_notice_category_taxonomies() as $taxonomy) {
				$taxonomy = (string) $taxonomy;
				if ($taxonomy !== '' && \taxonomy_exists($taxonomy)) {
					return $taxonomy;
				}
			}
		}

		$candidates = ['kontakte_kategorien', 'kontakte_kategorie', 'kundenkategorie', 'kontakt_kategorie'];
		if (\post_type_exists('kontakte')) {
			foreach ((array) \get_object_taxonomies('kontakte', 'names') as $taxonomy) {
				$taxonomy = (string) $taxonomy;
				if ($taxonomy !== '' && \stripos($taxonomy, 'kategorie') !== false) {
					$candidates[] = $taxonomy;
				}
			}
		}

		foreach (\array_values(\array_unique($candidates)) as $taxonomy) {
			$taxonomy = (string) $taxonomy;
			if ($taxonomy !== '' && \taxonomy_exists($taxonomy)) {
				return $taxonomy;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_contact_category_options')) {
	function cmx_email_contact_category_options(): array {
		static $options = null;
		if (\is_array($options)) {
			return $options;
		}

		$options = [];
		$taxonomy = cmx_email_contact_category_taxonomy();
		if ($taxonomy === '') {
			return $options;
		}

		$terms = \get_terms([
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]);
		if (\is_wp_error($terms)) {
			return $options;
		}

		foreach ((array) $terms as $term) {
			if (!$term instanceof \WP_Term) {
				continue;
			}
			$slug = \sanitize_title((string) $term->slug);
			if ($slug === '') {
				continue;
			}
			$options[$slug] = \wp_strip_all_tags((string) $term->name);
		}

		return $options;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_contact_category_selected_slugs')) {
	function cmx_email_contact_category_selected_slugs($raw): array {
		$values = \is_array($raw) ? $raw : [$raw];
		$selected = [];

		foreach ($values as $value) {
			$slug = \sanitize_title((string) $value);
			if ($slug === '') {
				continue;
			}
			$selected[$slug] = $slug;
		}

		return \array_values($selected);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_contact_category_label_list')) {
	function cmx_email_contact_category_label_list(array $selected_slugs, array $options = []): array {
		$options = $options !== [] ? $options : cmx_email_contact_category_options();
		$labels = [];

		foreach ($selected_slugs as $slug) {
			$slug = \sanitize_title((string) $slug);
			if ($slug === '') {
				continue;
			}
			$labels[] = (string) ($options[$slug] ?? $slug);
		}

		return $labels;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_contact_category_button_label')) {
	function cmx_email_contact_category_button_label(array $selected_slugs, array $options = []): string {
		$labels = cmx_email_contact_category_label_list($selected_slugs, $options);
		if ($labels === []) {
			return 'Kontakt-Kategorien wählen';
		}
		if (\count($labels) <= 2) {
			return \implode(', ', $labels);
		}
		return $labels[0] . ', ' . $labels[1] . ' +' . (\count($labels) - 2);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_client_list')) {
	function cmx_email_client_list(): array {
		$options = (array) \get_option(CMX_SETTINGS_MAIN, []);
		return cmx_email_normalize_client_list($options['email_clients'] ?? []);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_render_client_item')) {
	function cmx_email_render_client_item(array $client, string $index): void {
		$name_base = \esc_attr(CMX_SETTINGS_MAIN . '[email_clients][' . $index . ']');
		$id = \esc_attr((string) ($client['id'] ?? ''));
		$name = \esc_attr((string) ($client['name'] ?? ''));
		$email = \esc_attr((string) ($client['email'] ?? ''));
		$password = \esc_attr((string) ($client['password'] ?? ''));
		$smtp_host = \esc_attr((string) ($client['smtp_host'] ?? ''));
		$imap_host = \esc_attr((string) ($client['imap_host'] ?? ''));
		$contact_category_options = cmx_email_contact_category_options();
		$contact_category_slugs = cmx_email_contact_category_selected_slugs($client['kontakt_kategorien'] ?? []);
		$contact_category_enabled = !empty($client['kontakt_kategorien_enabled']);
		$contact_category_labels = cmx_email_contact_category_label_list($contact_category_slugs, $contact_category_options);
		$contact_category_summary = 'Keine Kontakt-Kategorien vorhanden.';
		if ($contact_category_options !== []) {
			if ($contact_category_enabled) {
				$contact_category_summary = $contact_category_labels === []
					? 'Keine Kategorie gewählt'
					: 'Kategorien: ' . \implode(', ', $contact_category_labels);
			} else {
				$contact_category_summary = $contact_category_labels === []
					? 'Filter aus'
					: 'Filter aus: ' . \implode(', ', $contact_category_labels);
			}
		}
		$contact_category_toggle_title = $contact_category_enabled
			? 'Kontakt-Kategorien aktiv'
			: 'Kontakt-Kategorien inaktiv';

		echo '<div class="cmx-email-client-item">';
		echo '<input type="hidden" name="' . $name_base . '[id]" value="' . $id . '">';
		echo '<input type="hidden" name="' . $name_base . '[client]" value="' . \esc_attr((string) ($client['client'] ?? '')) . '">';
		echo '<div class="cmx-email-client-grid">';
		echo '<label class="cmx-email-client-field"><span>E-Mail Name</span><input type="text" class="regular-text" name="' . $name_base . '[name]" value="' . $name . '" placeholder="Max Muster" autocomplete="organization"></label>';
		echo '<label class="cmx-email-client-field"><span>E-Mail Adresse</span><input type="email" class="regular-text" name="' . $name_base . '[email]" value="' . $email . '" placeholder="mail@beispiel.ch" autocomplete="username"></label>';
		echo '<label class="cmx-email-client-field"><span>Kennwort</span><span class="cmx-email-password-wrap"><input type="password" class="regular-text cmx-email-password-input" name="' . $name_base . '[password]" value="' . $password . '" autocomplete="current-password"><button type="button" class="button-link cmx-email-password-toggle" aria-label="Kennwort einblenden" aria-pressed="false" title="Kennwort einblenden"><span class="cmx-email-password-icon is-show" aria-hidden="true"></span></button></span></label>';
		echo '<label class="cmx-email-client-field"><span>SMTP Host (587)</span><input type="text" class="regular-text" name="' . $name_base . '[smtp_host]" value="' . $smtp_host . '" placeholder="smtp.infomaniak.com" autocomplete="off"></label>';
		echo '<label class="cmx-email-client-field"><span>IMAP Host (993)</span><input type="text" class="regular-text" name="' . $name_base . '[imap_host]" value="' . $imap_host . '" placeholder="imap.infomaniak.com" autocomplete="off"></label>';
		echo '<div class="cmx-email-client-inline-action"><button type="button" class="button button-secondary cmx-email-client-test">Testen</button></div>';
		echo '<div class="cmx-email-client-actions">';
		echo '<input type="hidden" class="cmx-email-client-category-enabled" name="' . $name_base . '[kontakt_kategorien_enabled]" value="' . ($contact_category_enabled ? '1' : '0') . '">';
		echo '<div class="cmx-email-client-action-buttons">';
		echo '<div class="cmx-email-client-category-group">';
		echo '<button type="button" class="button button-secondary cmx-email-client-category-toggle' . ($contact_category_enabled ? ' is-active' : '') . '" aria-pressed="' . ($contact_category_enabled ? 'true' : 'false') . '" title="' . \esc_attr($contact_category_toggle_title) . '"><span class="dashicons dashicons-businessman" aria-hidden="true"></span></button>';
		echo '<div class="cmx-email-client-category-picker' . ($contact_category_enabled ? '' : ' is-disabled') . '">';
		echo '<button type="button" class="button button-secondary cmx-email-client-category-button" title="' . \esc_attr($contact_category_summary) . '"' . (($contact_category_options === [] || !$contact_category_enabled) ? ' disabled' : '') . '>';
		echo '<span class="cmx-email-client-category-button-text">Kategorien</span>';
		echo '</button>';
		echo '<div class="cmx-email-client-category-popover" hidden>';
		if ($contact_category_options === []) {
			echo '<div class="cmx-email-client-category-empty">Keine Kontakt-Kategorien vorhanden.</div>';
		} else {
			echo '<input type="search" class="cmx-email-client-category-search" placeholder="Kategorien suchen" autocomplete="off">';
			echo '<div class="cmx-email-client-category-options">';
			foreach ($contact_category_options as $slug => $label) {
				$slug = \sanitize_title((string) $slug);
				if ($slug === '') {
					continue;
				}
				$label = \wp_strip_all_tags((string) $label);
				$checked = \in_array($slug, $contact_category_slugs, true);
				$search_label = \function_exists('mb_strtolower') ? \mb_strtolower($label) : \strtolower($label);
				echo '<label class="cmx-email-client-category-option" data-label="' . \esc_attr($search_label) . '">';
				echo '<input type="checkbox" class="cmx-email-client-category-checkbox" name="' . $name_base . '[kontakt_kategorien][]" value="' . \esc_attr($slug) . '"' . \checked($checked, true, false) . ' data-label="' . \esc_attr($label) . '"> ';
				echo '<span>' . \esc_html($label) . '</span>';
				echo '</label>';
			}
			echo '</div>';
			echo '<div class="cmx-email-client-category-empty is-search-empty" hidden>Keine passenden Kategorien.</div>';
		}
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '<button type="button" class="button-link-delete cmx-email-client-remove" aria-label="Client entfernen" title="Client entfernen"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '<div class="cmx-email-client-test-result" aria-live="polite"></div>';
		echo '</div>';
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_agb_belege_enabled')) {
	function cmx_email_agb_belege_enabled(): bool {
		return cmx_email_option_value('AGB_Belege') === '1';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_agb_footer_html')) {
	function cmx_email_agb_footer_html(string $link_style = ''): string {
		$link = cmx_email_agb_link();
		if ($link === '') {
			return '';
		}

		$link_attr = $link_style !== '' ? ' style="' . \esc_attr($link_style) . '"' : '';
		return 'Es gelten unsere <a href="' . \esc_url($link) . '"' . $link_attr . '>Allgemeinen Geschäftsbedingungen</a>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_agb_footer_text')) {
	function cmx_email_agb_footer_text(): string {
		$link = cmx_email_agb_link();
		if ($link === '') {
			return '';
		}

		return 'Es gelten unsere Allgemeinen Geschäftsbedingungen';
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_sender_mailbox_text')) {
	function cmx_email_sender_mailbox_text(): string {
		$name = \trim((string) cmx_email_option_value('email_name'));
		$email = \sanitize_email((string) cmx_email_option_value('email_address'));
		if (\is_email($email) && $name !== '') {
			return '"' . $name . '" <' . $email . '>';
		}
		if (\is_email($email)) {
			return $email;
		}
		if ($name !== '') {
			return $name;
		}
		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_sender_mailto_html')) {
	function cmx_email_sender_mailto_html(string $link_style = ''): string {
		$label = cmx_email_sender_mailbox_text();
		if ($label === '') {
			return '';
		}

		$email = \sanitize_email((string) cmx_email_option_value('email_address'));
		$attr = $link_style !== '' ? ' style="' . \esc_attr($link_style) . '"' : '';
		if (\is_email($email)) {
			return '<a href="mailto:' . \esc_attr($email) . '"' . $attr . '>' . \esc_html($label) . '</a>';
		}

		return '<span' . $attr . '>' . \esc_html($label) . '</span>';
	}
}

\add_action('admin_init', function (): void {
	$page = 'cmx_tab_email__belege';
	$clients_page = 'cmx_tab_email__clients';

	\add_settings_section(
		'cmx_sec_email_account',
		'Dein E-Mail Konto',
		static function (): void {
			echo '<p class="description">Zugangsdaten zum senden und empfangen Deiner E-Mails.</p>';
		},
		$page
	);

	\add_settings_field(
		'cmx_email_name',
		'E-Mail Name',
		static function (): void {
			$value = \esc_attr(cmx_email_option_value('email_name'));
			echo '<input type="text" class="regular-text" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[email_name]" value="' . $value . '" autocomplete="organization">';
		},
		$page,
		'cmx_sec_email_account'
	);

	\add_settings_field(
		'cmx_email_address',
		'E-Mail Adresse*',
		static function (): void {
			$value = \esc_attr(cmx_email_option_value('email_address'));
			echo '<input type="email" class="regular-text" id="cmx-email-address-input" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[email_address]" value="' . $value . '" autocomplete="username">';
		},
		$page,
		'cmx_sec_email_account'
	);

		\add_settings_field(
			'cmx_email_password',
			'Kennwort*',
			static function (): void {
				$value = \esc_attr(cmx_email_option_value('email_password'));
				echo '<span class="cmx-email-password-wrap cmx-email-password-wrap-main">';
				echo '<input type="password" class="regular-text cmx-email-password-input" id="cmx-email-password-input" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[email_password]" value="' . $value . '" autocomplete="current-password">';
				echo '<button type="button" class="button-link cmx-email-password-toggle" aria-label="Kennwort einblenden" aria-pressed="false" title="Kennwort einblenden">';
				echo '<span class="cmx-email-password-icon is-show" aria-hidden="true"></span>';
				echo '</button>';
				echo '</span>';
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
			echo '<span style="margin-left:8px;">(optional) muss dann an die <code id="cmx-email-supplier-forward-target">' . \esc_html($email_address) . '</code> weitergeleitet werden</span>';
			echo '<script>(function(){var source=document.getElementById("cmx-email-address-input");var target=document.getElementById("cmx-email-supplier-forward-target");if(!source||!target||target.dataset.bound==="1"){return;}var fallback="E-Mail-Adresse";var update=function(){var value=(source.value||"").trim();target.textContent=value!==""?value:fallback;};target.dataset.bound="1";source.addEventListener("input",update);source.addEventListener("change",update);update();})();</script>';
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
			$raw_value = (string) cmx_email_option_value('agb_link');
			$value = \esc_attr($raw_value);
			$placeholder = \esc_attr((string) cmx_email_agb_placeholder());
			echo '<input type="url" class="regular-text" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[agb_link]" value="' . $value . '" placeholder="' . $placeholder . '" autocomplete="off">';
			if (\trim($raw_value) !== '') {
				echo '<a href="' . \esc_url($raw_value) . '" target="_blank" rel="noopener noreferrer" style="margin-left:8px;">Link zu Deinen AGB.</a>';
			} else {
				echo '<span style="margin-left:8px;">Link zu Deinen AGB.</span>';
			}
		},
		$page,
		'cmx_sec_email_links'
	);

	\add_settings_field(
		'cmx_email_agb_belege',
		'',
		static function (): void {
			$checked = cmx_email_option_value('AGB_Belege') === '1';
			echo '<input type="hidden" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[AGB_Belege]" value="0">';
			echo '<label><input type="checkbox" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[AGB_Belege]" value="1" ' . \checked($checked, true, false) . '> auch in den Belegen anzeigen</label>';
		},
		$page,
		'cmx_sec_email_links'
	);

	\add_settings_field(
		'cmx_email_kundenportal_link',
		'Kunden Portal',
		static function (): void {
			$checked = cmx_email_option_value('kundenportal_link') === '1';
			echo '<input type="hidden" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[kundenportal_link]" value="0">';
			echo '<label><input type="checkbox" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[kundenportal_link]" value="1" ' . \checked($checked, true, false) . '> Link hinzufügen</label>';
		},
		$page,
		'cmx_sec_email_links'
	);

	\add_settings_section(
		'cmx_sec_email_clients',
		'E-Mail Clients',
		static function (): void {
			echo '<p class="description">Hier kannst Du beliebig viele zusätzliche Mail-Zugangsdaten pro Client hinterlegen.</p>';
		},
		$clients_page
	);

	\add_settings_field(
		'cmx_email_clients',
		'',
		static function (): void {
			$clients = cmx_email_client_list();
			$next_index = \count($clients);

			echo '<input type="hidden" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[email_clients_present]" value="1">';
			echo '<div class="cmx-email-client-list-wrap">';
			echo '<div class="cmx-email-client-list" id="cmx-email-client-list" data-next-index="' . (int) $next_index . '">';
			if (!empty($clients)) {
				foreach ($clients as $index => $client) {
					cmx_email_render_client_item((array) $client, (string) $index);
				}
			}
			echo '</div>';
			echo '<p class="cmx-email-client-empty" id="cmx-email-client-empty"' . (!empty($clients) ? ' style="display:none;"' : '') . '>Noch kein Client erfasst.</p>';
			echo '<p><button type="button" class="button button-secondary" id="cmx-email-client-add">Client hinzufügen</button></p>';

			$template_client = [
				'id'        => '',
				'client'    => '',
				'name'      => '',
				'email'     => '',
				'password'  => '',
				'smtp_host' => '',
				'smtp_port' => '587',
				'imap_host' => '',
				'imap_port' => '993',
				'kontakt_kategorien_enabled' => '0',
				'kontakt_kategorien' => [],
			];
			\ob_start();
			cmx_email_render_client_item($template_client, '__index__');
			$template = (string) \ob_get_clean();
			echo '<template id="cmx-email-client-template">' . $template . '</template>';
			echo '</div>';
		},
		$clients_page,
		'cmx_sec_email_clients'
	);
	});

	\add_action('admin_head', function (): void {
		if (!cmx_email_tab_is_active()) {
			return;
		}
		$icon_show_url = \esc_url(\plugins_url('../../assets/see.png', __FILE__));
		$icon_hide_url = \esc_url(\plugins_url('../../assets/hide.png', __FILE__));
		?>
		<style>
			.cmx-email-password-wrap {
				position: relative;
				display: inline-flex;
				align-items: center;
				width: 100%;
				height: var(--cmx-email-client-control-height, 44px);
			}
			.cmx-email-password-wrap-main {
				width: min(100%, 25em);
				max-width: 100%;
			}
			.cmx-email-password-wrap .cmx-email-password-input {
				width: 100%;
				max-width: none;
				padding-right: 62px;
				height: var(--cmx-email-client-control-height, 44px);
				min-height: var(--cmx-email-client-control-height, 44px);
				box-sizing: border-box;
			}
			.cmx-email-password-wrap .cmx-email-password-toggle {
				position: absolute;
				right: 4px;
				top: 50%;
				transform: translateY(-50%);
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 52px;
				height: 36px;
				margin: 2px 0 0 0;
				padding: 0;
				color: #50575e;
				text-decoration: none;
			}
			.cmx-email-password-wrap .cmx-email-password-icon {
				display: block;
				width: 48px;
				height: 32px;
				background-repeat: no-repeat;
				background-position: center;
				background-size: contain;
			}
			.cmx-email-password-wrap .cmx-email-password-icon.is-show {
				background-image: url('<?php echo $icon_show_url; ?>');
			}
			.cmx-email-password-wrap .cmx-email-password-icon.is-hide {
				background-image: url('<?php echo $icon_hide_url; ?>');
			}
			.cmx-email-password-wrap .cmx-email-password-toggle:hover,
			.cmx-email-password-wrap .cmx-email-password-toggle:focus {
				color: #1d2327;
			}
			.cmx-email-password-wrap .cmx-email-password-toggle:focus {
				outline: none;
				box-shadow: 0 0 0 2px #2271b1;
				border-radius: 3px;
			}
			.cmx-email-client-list {
				display: flex;
				flex-direction: column;
				gap: 14px;
			}
			.cmx-email-client-list-wrap {
				width: 100%;
				max-width: none;
				margin: 0;
			}
			.cmx-email-clients-row th {
				display: none;
			}
			.cmx-email-clients-row td {
				padding-left: 0;
				padding-right: 0;
			}
			.cmx-email-client-item {
				--cmx-email-client-control-height: 44px;
				border: 1px solid #d7dce3;
				border-radius: 12px;
				background: #fff;
				padding: 14px;
				position: relative;
				overflow: visible;
				z-index: 0;
			}
			.cmx-email-client-item.is-category-open {
				z-index: 50;
			}
			.cmx-email-client-grid {
				display: grid;
				grid-template-columns: 0.72fr 0.83fr 0.94fr 0.8fr 0.8fr auto minmax(270px, 1fr);
				gap: 12px;
				align-items: end;
			}
			.cmx-email-client-field {
				display: flex;
				flex-direction: column;
				gap: 6px;
				min-width: 0;
			}
			.cmx-email-client-field span {
				font-weight: 600;
			}
			.cmx-email-client-field .regular-text,
			.cmx-email-client-field .small-text {
				width: 100%;
				max-width: none;
				height: var(--cmx-email-client-control-height);
				min-height: var(--cmx-email-client-control-height);
				box-sizing: border-box;
				margin: 0;
			}
			.cmx-email-client-actions {
				display: flex;
				flex-direction: column;
				align-self: end;
				min-width: 270px;
				justify-self: end;
				width: 100%;
			}
			.cmx-email-client-inline-action {
				display: flex;
				align-items: flex-end;
			}
			.cmx-email-client-action-buttons {
				display: flex;
				align-items: center;
				gap: 10px;
				justify-content: flex-end;
				flex-wrap: nowrap;
				width: 100%;
			}
			.cmx-email-client-category-group {
				display: flex;
				align-items: stretch;
				gap: 8px;
				flex: 0 0 210px;
				min-width: 210px;
			}
			.cmx-email-client-category-toggle {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 40px;
				min-width: 40px;
				height: var(--cmx-email-client-control-height);
				padding: 0;
				border-radius: 12px;
				box-shadow: inset 0 0 0 1px rgba(34, 113, 177, .08);
			}
			.cmx-email-client-category-toggle .dashicons {
				width: 18px;
				height: 18px;
				font-size: 18px;
				line-height: 18px;
				position: relative;
				top: 3px;
			}
			.cmx-email-client-category-toggle.is-active {
				background: #2271b1;
				border-color: #2271b1;
				color: #fff;
			}
			.cmx-email-client-category-toggle:not(.is-active) {
				color: #50575e;
			}
			.cmx-email-client-category-picker {
				position: relative;
				flex: 1 1 auto;
				min-width: 0;
			}
			.cmx-email-client-category-picker.is-disabled {
				opacity: 1;
			}
			.cmx-email-client-category-button {
				display: flex;
				align-items: center;
				justify-content: space-between;
				gap: 8px;
				width: 100%;
				height: var(--cmx-email-client-control-height);
				min-height: var(--cmx-email-client-control-height);
				padding-inline: 14px 12px;
				border-radius: 12px;
				background: linear-gradient(180deg, #fff 0%, #f7f9fc 100%);
				border-color: #c7d4e3;
				box-shadow: inset 0 1px 0 rgba(255,255,255,.6);
			}
			.cmx-email-client-category-button .dashicons {
				flex: 0 0 auto;
				width: 18px;
				height: 18px;
				font-size: 18px;
				line-height: 18px;
			}
			.cmx-email-client-category-button-text {
				display: block;
				min-width: 0;
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
				font-weight: 500;
			}
			.cmx-email-client-category-picker.is-disabled .cmx-email-client-category-button {
				color: #8c8f94;
				background: #f6f7f7;
				border-style: dashed;
				border-color: #d0d4da;
				box-shadow: none;
			}
			.cmx-email-client-category-popover {
				position: absolute;
				top: calc(100% + 6px);
				left: 0;
				z-index: 1000;
				width: 210px;
				min-width: 210px;
				max-width: calc(100vw - 80px);
				padding: 12px;
				border: 1px solid #d8e2ec;
				border-radius: 16px;
				background: #fff;
				box-shadow: 0 18px 38px rgba(15, 23, 42, .16);
			}
			.cmx-email-client-category-search {
				width: 100%;
				margin: 0 0 10px;
				padding: 8px 11px;
				border: 1px solid #c7d4e3;
				border-radius: 10px;
				font-size: 13px;
			}
			.cmx-email-client-category-options {
				display: flex;
				flex-direction: column;
				gap: 6px;
				max-height: 220px;
				overflow: auto;
				padding-right: 2px;
			}
			.cmx-email-client-category-option {
				display: flex;
				align-items: center;
				gap: 8px;
				padding: 8px 10px;
				border: 1px solid #e9edf2;
				border-radius: 11px;
				background: #fafbfd;
				transition: border-color .12s ease, background-color .12s ease;
			}
			.cmx-email-client-category-option:hover {
				background: #f3f7fb;
				border-color: #cbd9e8;
			}
			.cmx-email-client-category-option.is-hidden {
				display: none;
			}
			.cmx-email-client-category-option input {
				margin: 0;
			}
			.cmx-email-client-category-empty {
				font-size: 12px;
				line-height: 1.4;
				color: #646970;
			}
			.cmx-email-client-remove {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 38px;
				height: var(--cmx-email-client-control-height);
				min-height: var(--cmx-email-client-control-height);
				padding: 0;
				border-radius: 12px;
				border: 1px solid #d63638;
				background: #fff;
				color: #d63638;
			}
			.cmx-email-client-remove:hover,
			.cmx-email-client-remove:focus {
				border-color: #b32d2e;
				color: #b32d2e;
				background: #fff;
			}
			.cmx-email-client-test {
				height: var(--cmx-email-client-control-height);
				min-height: var(--cmx-email-client-control-height);
				padding-top: 0;
				padding-bottom: 0;
			}
			.cmx-email-client-remove .dashicons {
				width: 18px;
				height: 18px;
				font-size: 18px;
				line-height: 18px;
			}
			.cmx-email-client-test-result {
				display: block;
				margin-top: 10px;
				font-size: 12px;
				line-height: 1.35;
				color: #646970;
				white-space: normal;
			}
			.cmx-email-client-empty {
				margin: 0 0 14px;
				color: #646970;
			}
			@media (max-width: 782px) {
				.cmx-email-client-grid {
					grid-template-columns: 1fr;
				}
				.cmx-email-client-actions {
					min-width: 0;
				}
				.cmx-email-client-action-buttons {
					flex-wrap: wrap;
				}
				.cmx-email-client-category-group {
					min-width: 0;
					width: 100%;
				}
				.cmx-email-client-category-picker {
					width: 100%;
				}
				.cmx-email-client-category-popover {
					position: static;
					width: 100%;
					max-width: none;
					margin-top: 8px;
				}
			}
		</style>
		<?php
	});

	\add_filter('pre_update_option_' . CMX_SETTINGS_MAIN, function ($new, $old) {
		if (!\is_array($new)) {
			return $new;
		}

		$normalize_client_list = static function (array $data): array {
			if (!\array_key_exists('email_clients_present', $data) && !\array_key_exists('email_clients', $data)) {
				return $data;
			}
			$data['email_clients'] = \function_exists(__NAMESPACE__ . '\\cmx_email_normalize_client_list')
				? cmx_email_normalize_client_list($data['email_clients'] ?? [])
				: [];
			unset($data['email_clients_present']);
			return $data;
		};

		$new = $normalize_client_list($new);

	$new['email_name'] = \sanitize_text_field((string) ($new['email_name'] ?? ''));
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
	$new['AGB_Belege'] = !empty($new['AGB_Belege']) ? '1' : '0';
	$new['kundenportal_link'] = !empty($new['kundenportal_link']) ? '1' : '0';
		$new['email_theme'] = \function_exists(__NAMESPACE__ . '\\cmx_email_theme_sanitize')
			? (string) cmx_email_theme_sanitize((string) ($new['email_theme'] ?? 'rot'))
			: 'rot';
		$new['email_button_text'] = \sanitize_text_field((string) ($new['email_button_text'] ?? ''));
		$new['email_hide_logo'] = !empty($new['email_hide_logo']) ? '1' : '0';
		$new['smtp_port'] = '587';
		$new['imap_port'] = '993';

	return $new;
}, 20, 2);

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_ajax_guard')) {
	function cmx_email_ajax_guard(): void {
		if (!\current_user_can('manage_options')) {
			\wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
		}

		$nonce_fields = ['_ajax_nonce', '_wpnonce', 'nonce', 'security'];
		$valid_nonce = false;
		foreach ($nonce_fields as $field) {
			$value = isset($_REQUEST[$field]) ? (string) \wp_unslash($_REQUEST[$field]) : '';
			if ($value !== '' && \wp_verify_nonce($value, 'cmx_email_test_conn')) {
				$valid_nonce = true;
				break;
			}
		}

		if (!$valid_nonce && \function_exists('check_ajax_referer')) {
			$valid_nonce = \check_ajax_referer('cmx_email_test_conn', false, false) !== false;
		}

		if (!$valid_nonce) {
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
				return [true, 'SMTP: Du kannst nun E-Mails versenden!'];
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
					return [true, ' IMAP: Du kannst nun E-Mails empfangen!'];
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

		function initClientSettingsRow(){
			var list = document.getElementById('cmx-email-client-list');
			if (!list) return;
			var row = list.closest('tr');
			if (row) {
				row.classList.add('cmx-email-clients-row');
			}
		}

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
		function postConnectionTest(action, host, email, password){
			var fd = new FormData();
			fd.append('action', action);
			fd.append('_ajax_nonce', nonce);
			fd.append('_wpnonce', nonce);
			fd.append('nonce', nonce);
			fd.append('security', nonce);
			fd.append('email', String(email || '').trim());
			fd.append('password', String(password || ''));
			fd.append('host', String(host || '').trim());

			return fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: fd
			})
			.then(function(res){ return res.json(); })
			.then(function(json){
				var ok = !!(json && json.success);
				var message = json && json.data && json.data.message ? String(json.data.message) : (ok ? 'OK' : 'Fehlgeschlagen.');
				return { ok: ok, message: message };
			});
		}
		function initPasswordToggles(scope){
			var root = scope || document;
			root.querySelectorAll('.cmx-email-password-wrap').forEach(function(wrap){
				var input = wrap.querySelector('.cmx-email-password-input');
				var toggle = wrap.querySelector('.cmx-email-password-toggle');
				if (!input || !toggle || toggle.dataset.bound === '1') return;
				toggle.dataset.bound = '1';
				var icon = toggle.querySelector('.cmx-email-password-icon');

				function syncPasswordToggle(){
					var isVisible = input.type === 'text';
					var label = isVisible ? 'Kennwort ausblenden' : 'Kennwort einblenden';
					toggle.setAttribute('aria-label', label);
					toggle.setAttribute('title', label);
					toggle.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
					if (icon) {
						icon.className = 'cmx-email-password-icon ' + (isVisible ? 'is-hide' : 'is-show');
					}
				}

				toggle.addEventListener('click', function(){
					input.type = input.type === 'password' ? 'text' : 'password';
					syncPasswordToggle();
				});

				syncPasswordToggle();
			});
		}
		function initClientCategoryPickers(scope){
			var root = scope || document;
			root.querySelectorAll('.cmx-email-client-item').forEach(function(item){
				var toggle = item.querySelector('.cmx-email-client-category-toggle');
				var enabledInput = item.querySelector('.cmx-email-client-category-enabled');
				var picker = item.querySelector('.cmx-email-client-category-picker');
				var button = item.querySelector('.cmx-email-client-category-button');
				var buttonText = item.querySelector('.cmx-email-client-category-button-text');
				var popover = item.querySelector('.cmx-email-client-category-popover');
				var search = item.querySelector('.cmx-email-client-category-search');
				var options = Array.prototype.slice.call(item.querySelectorAll('.cmx-email-client-category-checkbox'));
				var searchEmpty = item.querySelector('.cmx-email-client-category-empty.is-search-empty');
				if (!toggle || !enabledInput || !picker || !button || !buttonText || !popover || toggle.dataset.bound === '1') {
					return;
				}
				toggle.dataset.bound = '1';

				function getSelectedLabels(){
					return options.filter(function(input){
						return !!input.checked;
					}).map(function(input){
						return String(input.getAttribute('data-label') || input.value || '').trim();
					}).filter(Boolean);
				}

				function fullSummary(labels, enabled){
					if (!options.length) {
						return 'Keine Kontakt-Kategorien vorhanden.';
					}
					if (!enabled) {
						return labels.length ? 'Filter aus: ' + labels.join(', ') : 'Filter aus';
					}
					return labels.length ? 'Kategorien: ' + labels.join(', ') : 'Keine Kategorie gewählt';
				}

				function closePopover(){
					popover.hidden = true;
					item.classList.remove('is-category-open');
				}

				function applySearch(){
					if (!search) {
						return;
					}
					var needle = String(search.value || '').trim().toLowerCase();
					var visibleCount = 0;
					Array.prototype.forEach.call(item.querySelectorAll('.cmx-email-client-category-option'), function(option){
						var haystack = String(option.getAttribute('data-label') || '').toLowerCase();
						var visible = needle === '' || haystack.indexOf(needle) !== -1;
						option.classList.toggle('is-hidden', !visible);
						if (visible) {
							visibleCount++;
						}
					});
					if (searchEmpty) {
						searchEmpty.hidden = visibleCount > 0;
					}
				}

				function syncState(){
					var enabled = String(enabledInput.value || '0') === '1';
					var labels = getSelectedLabels();
					toggle.classList.toggle('is-active', enabled);
					toggle.setAttribute('aria-pressed', enabled ? 'true' : 'false');
					toggle.setAttribute('title', enabled ? 'Kontakt-Kategorien aktiv' : 'Kontakt-Kategorien inaktiv');
					picker.classList.toggle('is-disabled', !enabled);
					button.disabled = !enabled || !options.length;
					buttonText.textContent = 'Kategorien';
					button.setAttribute('title', fullSummary(labels, enabled));
					if (!enabled) {
						closePopover();
					}
					applySearch();
				}

				toggle.addEventListener('click', function(event){
					event.preventDefault();
					enabledInput.value = String(enabledInput.value || '0') === '1' ? '0' : '1';
					syncState();
				});

				button.addEventListener('click', function(event){
					if (button.disabled) {
						return;
					}
					event.preventDefault();
					popover.hidden = !popover.hidden;
					item.classList.toggle('is-category-open', !popover.hidden);
					if (!popover.hidden && search) {
						window.setTimeout(function(){
							search.focus();
							search.select();
						}, 0);
					}
				});

				if (search) {
					search.addEventListener('input', applySearch);
				}

				options.forEach(function(input){
					input.addEventListener('change', syncState);
				});

				document.addEventListener('click', function(event){
					if (!item.contains(event.target)) {
						closePopover();
					}
				});

				document.addEventListener('keydown', function(event){
					if (event.key === 'Escape') {
						closePopover();
					}
				});

				syncState();
			});
		}
		function initClientList(){
			var list = document.getElementById('cmx-email-client-list');
			var addBtn = document.getElementById('cmx-email-client-add');
			var template = document.getElementById('cmx-email-client-template');
			var empty = document.getElementById('cmx-email-client-empty');
			if (!list || !addBtn || !template || addBtn.dataset.bound === '1') return;
			addBtn.dataset.bound = '1';

			function syncEmpty(){
				if (!empty) return;
				empty.style.display = list.children.length ? 'none' : '';
			}

			function nextIndex(){
				var current = parseInt(list.getAttribute('data-next-index') || '0', 10);
				if (isNaN(current) || current < 0) {
					current = list.children.length;
				}
				list.setAttribute('data-next-index', String(current + 1));
				return current;
			}

			function addRow(){
				var index = nextIndex();
				var html = String(template.innerHTML || '').replace(/__index__/g, String(index));
				if (html.trim() === '') return;
				var holder = document.createElement('div');
				holder.innerHTML = html.trim();
				var item = holder.firstElementChild;
				if (!item) return;
				list.appendChild(item);
				initPasswordToggles(item);
				initClientCategoryPickers(item);
				syncEmpty();
			}

			addBtn.addEventListener('click', function(event){
				event.preventDefault();
				addRow();
			});

			list.addEventListener('click', function(event){
				var testBtn = event.target.closest('.cmx-email-client-test');
				if (testBtn) {
					event.preventDefault();
					var item = testBtn.closest('.cmx-email-client-item');
					if (!item || testBtn.disabled) return;
					var emailEl = item.querySelector('input[name$="[email]"]');
					var passEl = item.querySelector('input[name$="[password]"]');
					var smtpEl = item.querySelector('input[name$="[smtp_host]"]');
					var imapEl = item.querySelector('input[name$="[imap_host]"]');
					var resultEl = item.querySelector('.cmx-email-client-test-result');
					var email = emailEl ? String(emailEl.value || '').trim() : '';
					var password = passEl ? String(passEl.value || '') : '';
					var smtpHost = smtpEl ? String(smtpEl.value || '').trim() : '';
					var imapHost = imapEl ? String(imapEl.value || '').trim() : '';

					if (!email || !password || !smtpHost || !imapHost) {
						setResult(resultEl, false, 'Bitte E-Mail, Kennwort, SMTP-Host und IMAP-Host ausfüllen.');
						return;
					}

					testBtn.disabled = true;
					setPending(resultEl, 'Teste SMTP und IMAP...');

					Promise.all([
						postConnectionTest('cmx_email_test_smtp', smtpHost, email, password),
						postConnectionTest('cmx_email_test_imap', imapHost, email, password)
					])
					.then(function(results){
						var smtpResult = results[0] || { ok: false, message: 'SMTP-Test fehlgeschlagen.' };
						var imapResult = results[1] || { ok: false, message: 'IMAP-Test fehlgeschlagen.' };
						var ok = !!(smtpResult.ok && imapResult.ok);
						setResult(resultEl, ok, '' + smtpResult.message + '  ' + imapResult.message);
					})
					.catch(function(){
						setResult(resultEl, false, 'Verbindungstest fehlgeschlagen.');
					})
					.finally(function(){
						testBtn.disabled = false;
					});
					return;
				}

				var removeBtn = event.target.closest('.cmx-email-client-remove');
				if (!removeBtn) return;
				event.preventDefault();
				var item = removeBtn.closest('.cmx-email-client-item');
				if (item) {
					item.remove();
					syncEmpty();
				}
			});

			syncEmpty();
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
					fd.append('_wpnonce', nonce);
					fd.append('nonce', nonce);
					fd.append('security', nonce);
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

		initClientSettingsRow();
		initPasswordToggles(document);
		initClientCategoryPickers(document);
		initClientList();
		runTest('cmx_email_test_smtp', 'smtp_host', 'cmx-email-smtp-test', 'cmx-email-smtp-result');
		runTest('cmx_email_test_imap', 'imap_host', 'cmx-email-imap-test', 'cmx-email-imap-result');
	})();
	</script>
	<?php
});
