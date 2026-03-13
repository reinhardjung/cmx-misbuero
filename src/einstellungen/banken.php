<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit;

if (!\function_exists(__NAMESPACE__ . '\\cmx_get_das_bin_ich_company_name')) {
	function cmx_get_das_bin_ich_company_name(): string {
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
			return '';
		}

		$post = $posts[0];
		$company = \trim((string) ($post->post_title ?? ''));
		if ($company === '') {
			$company = \trim((string) \get_post_meta((int) $post->ID, '_company', true));
		}

		return $company;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_bank_legacy_definitions')) {
	function cmx_bank_legacy_definitions(): array {
		return [
			'rev' => [
				'enabled'   => 'rev_enabled',
				'bank_name' => 'rev_bank_name',
				'recipient' => 'rev_recipient',
				'iban'      => 'rev_iban',
				'qr_iban'   => 'rev_qr_iban',
				'bic'       => 'rev_bic',
				'api'       => 'rev_api',
				'label'     => 'Revolut',
			],
			'zkb' => [
				'enabled'   => 'zkb_enabled',
				'bank_name' => 'zkb_bank_name',
				'recipient' => 'zkb_recipient',
				'iban'      => 'zkb_iban',
				'qr_iban'   => 'zkb_qr_iban',
				'bic'       => 'zkb_bic',
				'api'       => 'zkb_api',
				'label'     => 'ZKB',
			],
			'ubs' => [
				'enabled'   => 'ubs_enabled',
				'bank_name' => 'ubs_bank_name',
				'recipient' => 'ubs_recipient',
				'iban'      => 'ubs_iban',
				'qr_iban'   => 'ubs_qr_iban',
				'bic'       => 'ubs_bic',
				'api'       => 'ubs_api',
				'label'     => 'UBS',
			],
			'migros' => [
				'enabled'   => 'migros_enabled',
				'bank_name' => 'migros_bank_name',
				'recipient' => 'migros_recipient',
				'iban'      => 'migros_iban',
				'qr_iban'   => 'migros_qr_iban',
				'bic'       => 'migros_bic',
				'api'       => 'migros_api',
				'label'     => 'Migros Bank',
			],
			'eisen' => [
				'enabled'   => 'eisen_enabled',
				'bank_name' => 'eisen_bank_name',
				'recipient' => 'eisen_recipient',
				'iban'      => 'eisen_iban',
				'qr_iban'   => 'eisen_qr_iban',
				'bic'       => 'eisen_bic',
				'api'       => 'eisen_api',
				'label'     => 'Raiffeisen',
			],
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_bank_default_presets')) {
	function cmx_bank_default_presets(): array {
		return [
			[
				'id'        => 'legacy_zkb',
				'bank_name' => 'ZKB',
				'recipient' => '',
				'iban'      => '',
				'qr_iban'   => '',
				'bic'       => '',
				'api'       => '',
			],
			[
				'id'        => 'legacy_ubs',
				'bank_name' => 'UBS',
				'recipient' => '',
				'iban'      => '',
				'qr_iban'   => '',
				'bic'       => '',
				'api'       => '',
			],
			[
				'id'        => 'legacy_migros',
				'bank_name' => 'Migros Bank',
				'recipient' => '',
				'iban'      => '',
				'qr_iban'   => '',
				'bic'       => '',
				'api'       => '',
			],
			[
				'id'        => 'legacy_eisen',
				'bank_name' => 'Raiffeisen',
				'recipient' => '',
				'iban'      => '',
				'qr_iban'   => '',
				'bic'       => '',
				'api'       => '',
			],
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_normalize_bank_list')) {
	function cmx_normalize_bank_list($raw): array {
		$rows = \is_array($raw) ? $raw : [];
		$normalized = [];
		$index = 1;

		foreach ($rows as $row) {
			if (!\is_array($row)) {
				continue;
			}

			$bank_name = \trim((string) ($row['bank_name'] ?? ''));
			$recipient = \trim((string) ($row['recipient'] ?? ''));
			$iban = \trim((string) ($row['iban'] ?? ''));
			$qr_iban = \trim((string) ($row['qr_iban'] ?? ''));
			$bic = \trim((string) ($row['bic'] ?? ''));
			$api = \trim((string) ($row['api'] ?? ''));

			if ($bank_name === '' && $recipient === '' && $iban === '' && $qr_iban === '' && $bic === '' && $api === '') {
				continue;
			}

			$id = \sanitize_key((string) ($row['id'] ?? ''));
			if ($id === '') {
				$id = 'bank_' . $index;
			}

			$normalized[] = [
				'id'        => $id,
				'bank_name' => $bank_name,
				'recipient' => $recipient,
				'iban'      => $iban,
				'qr_iban'   => $qr_iban,
				'bic'       => $bic,
				'api'       => $api,
			];
			$index++;
		}

		return $normalized;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_get_legacy_bank_list')) {
	function cmx_get_legacy_bank_list(): array {
		$options = (array) \get_option(CMX_SETTINGS_MAIN, []);
		$definitions = cmx_bank_legacy_definitions();
		$active = [];
		$inactive = [];

		foreach ($definitions as $legacy_key => $definition) {
			$raw_bank_name = \trim((string) ($options[$definition['bank_name']] ?? ''));
			$recipient = \trim((string) ($options[$definition['recipient']] ?? ''));
			$iban = \trim((string) ($options[$definition['iban']] ?? ''));
			$qr_iban = \trim((string) ($options[$definition['qr_iban']] ?? ''));
			$bic = \trim((string) ($options[$definition['bic']] ?? ''));
			$api = \trim((string) ($options[$definition['api']] ?? ''));
			$is_enabled = !empty($options[$definition['enabled']]);
			$is_configured = $is_enabled || $raw_bank_name !== '' || $recipient !== '' || $iban !== '' || $qr_iban !== '' || $bic !== '' || $api !== '';

			if (!$is_configured) {
				continue;
			}

			$bank_name = $raw_bank_name !== '' ? $raw_bank_name : (string) $definition['label'];

			$entry = [
				'id'        => 'legacy_' . $legacy_key,
				'bank_name' => $bank_name,
				'recipient' => $recipient,
				'iban'      => $iban,
				'qr_iban'   => $qr_iban,
				'bic'       => $bic,
				'api'       => $api,
			];

			if ($is_enabled) {
				$active[] = $entry;
			} else {
				$inactive[] = $entry;
			}
		}

		return \array_merge($active, $inactive);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_get_bank_list')) {
	function cmx_get_bank_list(): array {
		$options = (array) \get_option(CMX_SETTINGS_MAIN, []);
		$stored = cmx_normalize_bank_list($options['banken_liste'] ?? []);
		if (!empty($stored)) {
			return $stored;
		}
		$legacy = cmx_get_legacy_bank_list();
		if (!empty($legacy)) {
			return $legacy;
		}
		return cmx_bank_default_presets();
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_get_payrexx_instanz')) {
	function cmx_get_payrexx_instanz(): string {
		$options = (array) \get_option(CMX_SETTINGS_MAIN, []);
		$value = $options['payrexx_instanz'] ?? '';
		return \is_scalar($value) ? (string) $value : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_get_payrexx_instanz_slug')) {
	function cmx_get_payrexx_instanz_slug(): string {
		$value = \trim(cmx_get_payrexx_instanz());
		if ($value === '') {
			return '';
		}

		$value = (string) \preg_replace('~^https?://~i', '', $value);
		$value = (string) \preg_replace('~\.payrexx\.com.*$~i', '', $value);
		$value = \trim($value, " \t\n\r\0\x0B./");
		$value = \strtolower($value);
		$value = (string) \preg_replace('~[^a-z0-9-]+~', '-', $value);
		$value = \trim($value, '-');

		return $value;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_get_payrexx_vpos_url')) {
	function cmx_get_payrexx_vpos_url(): string {
		$instanz = cmx_get_payrexx_instanz_slug();
		if ($instanz === '') {
			return '';
		}

		return 'https://' . $instanz . '.payrexx.com/de/vpos?';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_payrexx_instanz_field')) {
	function cmx_render_payrexx_instanz_field(): void {
		$value = \esc_attr(cmx_get_payrexx_instanz());
		echo '<input type="text" class="regular-text" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[payrexx_instanz]" value="' . $value . '" placeholder="Name-Deiner-Instanz">';
		echo '<span style="margin-left:8px;">Der "Name-Deiner-Instanz" ist in der Regel Dein Firmenname</span>';
		echo '<p class="description">Wenn Du im Payrexx korrekt angemeldet bist, siehst Du in der URL (<i>der Adressleiste im Browser</i>) <br><strong>https://<code>Name-Deiner-Instanz</code>.payrexx.com/...</strong></p>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_bank_row_markup')) {
	function cmx_render_bank_row_markup(string $index, array $bank, string $recipient_placeholder): string {
		$defaults = [
			'id'        => '',
			'bank_name' => '',
			'recipient' => '',
			'iban'      => '',
			'qr_iban'   => '',
			'bic'       => '',
			'api'       => '',
		];
		$bank = \array_merge($defaults, $bank);
		$row_title = \trim((string) $bank['bank_name']);
		if ($row_title === '') {
			$row_title = 'Neue Bank';
		}
		$name_base = \esc_attr(CMX_SETTINGS_MAIN . '[banken_liste][' . $index . ']');

		ob_start();
		?>
		<div class="cmx-bank-item" data-index="<?php echo \esc_attr($index); ?>">
			<div class="cmx-bank-item-head">
				<span class="dashicons dashicons-move cmx-bank-handle" title="Ziehen zum Verschieben"></span>
				<strong class="cmx-bank-item-title"><?php echo \esc_html($row_title); ?></strong>
				<span class="cmx-bank-active-badge">Aktiv</span>
				<button type="button" class="button-link-delete cmx-bank-remove">Entfernen</button>
			</div>
			<input type="hidden" data-field="id" name="<?php echo $name_base; ?>[id]" value="<?php echo \esc_attr((string) $bank['id']); ?>">
			<input type="hidden" data-field="api" name="<?php echo $name_base; ?>[api]" value="<?php echo \esc_attr((string) $bank['api']); ?>">
			<div class="cmx-bank-grid">
				<p>
					<label>Bankname</label>
					<input type="text" class="regular-text cmx-bank-title-input" data-field="bank_name" name="<?php echo $name_base; ?>[bank_name]" value="<?php echo \esc_attr((string) $bank['bank_name']); ?>" placeholder="Bankname">
				</p>
				<p>
					<label>Empfänger</label>
					<input type="text" class="regular-text" data-field="recipient" name="<?php echo $name_base; ?>[recipient]" value="<?php echo \esc_attr((string) $bank['recipient']); ?>" placeholder="<?php echo \esc_attr($recipient_placeholder); ?>">
				</p>
				<p>
					<label>IBAN</label>
					<input type="text" class="regular-text" data-field="iban" name="<?php echo $name_base; ?>[iban]" value="<?php echo \esc_attr((string) $bank['iban']); ?>" placeholder="CHxx xxxx xxxx xxxx xxxx x">
				</p>
				<p>
					<label>QR-IBAN</label>
					<input type="text" class="regular-text" data-field="qr_iban" name="<?php echo $name_base; ?>[qr_iban]" value="<?php echo \esc_attr((string) $bank['qr_iban']); ?>" placeholder="CHxx xxxx xxxx xxxx xxxx x">
				</p>
				<p class="cmx-bank-grid-wide">
					<label>BIC / SWIFT</label>
					<input type="text" class="regular-text" data-field="bic" name="<?php echo $name_base; ?>[bic]" value="<?php echo \esc_attr((string) $bank['bic']); ?>" placeholder="BIC / SWIFT">
				</p>
			</div>
		</div>
		<?php
		return (string) \ob_get_clean();
	}
}

\add_action('admin_enqueue_scripts', function (): void {
	$page = isset($_GET['page']) ? \sanitize_key((string) \wp_unslash($_GET['page'])) : '';
	$tab = isset($_GET['tab']) ? \sanitize_key((string) \wp_unslash($_GET['tab'])) : '';
	if ($page !== CMX_SETTINGS_SLUG || $tab !== 'banken') {
		return;
	}
	\wp_enqueue_script('jquery-ui-sortable');
	\wp_enqueue_style('dashicons');
});

\add_action('admin_init', __NAMESPACE__ . '\\cmx_register_banken_tab');
function cmx_register_banken_tab(): void {
	\add_settings_section(
		'cmx_sec_payrexx',
		'Payrexx',
		'__return_false',
		'cmx_tab_banken'
	);

	\add_settings_field(
		'payrexx_instanz',
		'Instanz',
		__NAMESPACE__ . '\\cmx_render_payrexx_instanz_field',
		'cmx_tab_banken',
		'cmx_sec_payrexx'
	);

	\add_settings_section(
		'cmx_sec_banken',
		__('Finanzinstitut', 'default'),
		'__return_false',
		'cmx_tab_banken'
	);

	\add_settings_field(
		'banken_liste',
		'Deine Banken',
		__NAMESPACE__ . '\\cmx_render_banken_list_field',
		'cmx_tab_banken',
		'cmx_sec_banken'
	);
}

\add_filter('pre_update_option_' . CMX_SETTINGS_MAIN, static function ($new, $old) {
	if (!\is_array($new)) {
		return $new;
	}
	if (\array_key_exists('payrexx_instanz', $new)) {
		$new['payrexx_instanz'] = \sanitize_text_field((string) $new['payrexx_instanz']);
	}
	return $new;
}, 20, 2);

function cmx_render_banken_list_field(): void {
	$recipient_placeholder = cmx_get_das_bin_ich_company_name();
	if ($recipient_placeholder === '') {
		$recipient_placeholder = 'Dein Firmenname';
	}

	$options = (array) \get_option(CMX_SETTINGS_MAIN, []);
	$stored_list = cmx_normalize_bank_list($options['banken_liste'] ?? []);
	$bank_list = cmx_get_bank_list();
	if (empty($bank_list)) {
		$bank_list = [[
			'id'        => 'bank_1',
			'bank_name' => '',
			'recipient' => $recipient_placeholder,
			'iban'      => '',
			'qr_iban'   => '',
			'bic'       => '',
			'api'       => '',
		]];
	}

	echo '<div class="cmx-bank-list-root">';
	echo '<input type="hidden" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[banken_liste_present]" value="1">';
	echo '<p class="description">Du kannst beliebig viele Banken hinterlegen. Die <strong>erste Bank in der Liste</strong> <code>aktiv</code> wird jeweils verwendet. Die Reihenfolge kannst Du per Drag & Drop ändern.</p>';
	// if (empty($stored_list) && !empty(cmx_get_legacy_bank_list())) {
	// 	echo '<p class="description"><em>Die unten angezeigten Banken stammen aus der bisherigen Konfiguration. Beim nächsten Speichern werden sie in die neue Liste übernommen.</em></p>';
	// }
	echo '<div id="cmx-bank-list" class="cmx-bank-list">';
	foreach ($bank_list as $index => $bank) {
		echo cmx_render_bank_row_markup((string) $index, (array) $bank, $recipient_placeholder);
	}
	echo '</div>';
	echo '<p><button type="button" class="button button-secondary" id="cmx-bank-add">Bank hinzufügen</button></p>';
	echo '<template id="cmx-bank-row-template">' . cmx_render_bank_row_markup('__INDEX__', [
		'id'        => '',
		'bank_name' => '',
		'recipient' => $recipient_placeholder,
		'iban'      => '',
		'qr_iban'   => '',
		'bic'       => '',
		'api'       => '',
	], $recipient_placeholder) . '</template>';

	echo '<style>
		.cmx-bank-list-root{max-width:1400px}
		.cmx-bank-list{display:block;margin-top:12px}
		.cmx-bank-item{border:1px solid #dcdcde;border-radius:10px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.03);margin:0 0 12px}
		.cmx-bank-item:last-child{margin-bottom:0}
		.cmx-bank-item-head{display:flex;align-items:center;gap:10px;padding:12px 14px;border-bottom:1px solid #eef0f2}
		.cmx-bank-handle{cursor:move;color:#667085}
		.cmx-bank-item-title{flex:1 1 auto}
		.cmx-bank-active-badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;background:#e8f3ff;color:#1858a8;font-size:11px;font-weight:600}
		.cmx-bank-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;padding:14px}
		.cmx-bank-grid p{margin:0}
		.cmx-bank-grid label{display:block;margin-bottom:4px;font-weight:600}
		.cmx-bank-grid .regular-text{width:100%;max-width:none}
		.cmx-bank-sort-placeholder{border:1px dashed #9aa4b2;border-radius:10px;background:#f8fafc;height:112px;margin-bottom:12px}
		.cmx-bank-item.ui-sortable-helper{box-shadow:0 8px 18px rgba(15,23,42,.12)}
		@media (max-width: 1200px){
			.cmx-bank-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
		}
		@media (max-width: 782px){
			.cmx-bank-grid{grid-template-columns:1fr}
		}
	</style>';

	echo '<script>
	(function($){
		function initBankList() {
			const root = document.querySelector(".cmx-bank-list-root");
			if (!root) return;
			const list = root.querySelector("#cmx-bank-list");
			const template = root.querySelector("#cmx-bank-row-template");
			const addBtn = root.querySelector("#cmx-bank-add");
			if (!list || !template || !addBtn) return;

			function fieldName(index, field) {
				return ' . \wp_json_encode(CMX_SETTINGS_MAIN) . ' + "[banken_liste][" + index + "][" + field + "]";
			}

			function updateRowState(item, index) {
				item.dataset.index = String(index);
				item.querySelectorAll("[data-field]").forEach(function(input){
					input.name = fieldName(index, input.dataset.field || "");
				});
				const titleInput = item.querySelector(".cmx-bank-title-input");
				const title = titleInput && titleInput.value.trim() !== "" ? titleInput.value.trim() : "Bank " + (index + 1);
				const titleEl = item.querySelector(".cmx-bank-item-title");
				if (titleEl) titleEl.textContent = title;
				const badge = item.querySelector(".cmx-bank-active-badge");
				if (badge) badge.style.display = index === 0 ? "inline-flex" : "none";
			}

			function renumber() {
				Array.prototype.forEach.call(list.querySelectorAll(".cmx-bank-item"), function(item, index){
					updateRowState(item, index);
				});
			}

			function bindRow(item) {
				const removeBtn = item.querySelector(".cmx-bank-remove");
				if (removeBtn) {
					removeBtn.addEventListener("click", function(){
						item.remove();
						if (!list.querySelector(".cmx-bank-item")) {
							addRow();
						}
						renumber();
					});
				}
				const titleInput = item.querySelector(".cmx-bank-title-input");
				if (titleInput) {
					titleInput.addEventListener("input", function(){
						const rowIndex = Number(item.dataset.index || 0);
						updateRowState(item, rowIndex);
					});
				}
			}

			function addRow() {
				const fragment = template.content.cloneNode(true);
				const item = fragment.querySelector(".cmx-bank-item");
				list.appendChild(fragment);
				if (item) {
					bindRow(item);
				}
				renumber();
			}

			Array.prototype.forEach.call(list.querySelectorAll(".cmx-bank-item"), bindRow);

			addBtn.addEventListener("click", function(){
				addRow();
			});

			if ($ && $.fn && $.fn.sortable) {
				if ($(list).data("ui-sortable")) {
					$(list).sortable("destroy");
				}
				$(list).sortable({
					axis: "y",
					handle: ".cmx-bank-handle",
					items: "> .cmx-bank-item",
					placeholder: "cmx-bank-sort-placeholder",
					tolerance: "pointer",
					forcePlaceholderSize: true,
					stop: function(){
						renumber();
					}
				});
			}

			renumber();
		}

		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", initBankList);
		} else {
			initBankList();
		}
	})(window.jQuery);
	</script>';
	echo '</div>';
}

function cmx_get_active_bank(): ?array {
	$banks = cmx_get_bank_list();
	if (empty($banks)) {
		return null;
	}

	$active = (array) $banks[0];
	$name = \trim((string) ($active['bank_name'] ?? ''));
	if ($name === '') {
		$name = 'Bank';
	}

	$iban = \trim((string) ($active['iban'] ?? ''));
	$qr_iban = \trim((string) ($active['qr_iban'] ?? ''));

	return [
		'key'          => (string) ($active['id'] ?? 'bank_1'),
		'name'         => $name,
		'recipient'    => (string) ($active['recipient'] ?? ''),
		'iban'         => $iban,
		'qr_iban'      => $qr_iban,
		'bic'          => (string) ($active['bic'] ?? ''),
		'api'          => (string) ($active['api'] ?? ''),
		'label'        => $name,
		'qr_supported' => ($qr_iban !== '' || (\preg_match('/^CH/i', $iban) === 1)),
	];
}
