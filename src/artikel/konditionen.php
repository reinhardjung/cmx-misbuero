<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/* ===== Neue Meta-Felder: Aufwand ===== */
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_AUFWAND')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_AUFWAND', '_cmx_artikel_aufwand');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_MEHRWERT')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_MEHRWERT', '_cmx_artikel_mehrwert');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_SELBSTKOSTEN')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_SELBSTKOSTEN', '_cmx_artikel_selbstkosten');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_DECKUNGSBEITRAG')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_DECKUNGSBEITRAG', '_cmx_artikel_deckungsbeitrag');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_KATALOG')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_KATALOG', '_cmx_artikel_katalog');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VARIANT_TAXONOMY')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VARIANT_TAXONOMY', '_cmx_artikel_variant_taxonomy');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VARIANT_FARBEN_TAXONOMY')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VARIANT_FARBEN_TAXONOMY', '_cmx_artikel_variant_farben_taxonomy');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VARIANT_ROWS')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VARIANT_ROWS', '_cmx_artikel_variant_rows');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VARIANT_COUNT')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VARIANT_COUNT', '_cmx_artikel_variant_count');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_ART')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_ART', '_cmx_artikel_art');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_WOO_ID')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_WOO_ID', '_cmx_artikel_woo_id');
}

\add_action('add_meta_boxes', function () {
	\add_meta_box('cmx_artikel_waehrung_preise', 'Stammdaten', __NAMESPACE__ . '\\cmx_artikel_waehrung_preise_box_html', 'artikel', 'normal', 'default');
	\add_meta_box('cmx_artikel_waehrung_side', 'Art', __NAMESPACE__ . '\\cmx_artikel_waehrung_side_box_html', 'artikel', 'side', 'default');
});

function cmx_artikel_render_save_nonce_once(): void {
	static $rendered = false;
	if ($rendered) return;
	\wp_nonce_field('cmx_artikel_save', 'cmx_artikel_nonce');
	$rendered = true;
}

	function cmx_artikel_waehrung_optionen(): array {
		return [
			'CHF' => 'Schweizer Franken',
			'EUR' => 'Euro',
			'USD' => 'US-Dollar',
		];
	}

	function cmx_artikel_woo_product_link_template(): string {
		$example_url = \function_exists(__NAMESPACE__ . '\\cmx_woocommerce_get_setting')
			? (string) cmx_woocommerce_get_setting('misbuero_order_example_url', '')
			: '';
		if ($example_url === '') return '';
		$example_url = \function_exists(__NAMESPACE__ . '\\cmx_woocommerce_sanitize_order_example_url')
			? (string) cmx_woocommerce_sanitize_order_example_url($example_url)
			: (string) \esc_url_raw($example_url, ['http', 'https']);
		if ($example_url === '') return '';

		$parts = \wp_parse_url($example_url);
		if (!\is_array($parts) || empty($parts['host'])) return '';

		$path = (string) ($parts['path'] ?? '');
		if ($path === '') return '';

		$admin_dir = \str_replace('\\', '/', \dirname($path));
		if ($admin_dir === '.' || $admin_dir === '') return '';
		$parts['path'] = \rtrim($admin_dir, '/') . '/post.php';
		unset($parts['query'], $parts['fragment']);

		$template = \function_exists(__NAMESPACE__ . '\\cmx_woocommerce_build_url_from_parts')
			? (string) cmx_woocommerce_build_url_from_parts($parts, [
				'post'   => '__CMX_WOO_PRODUCT_ID__',
				'action' => 'edit',
			])
			: (string) \add_query_arg(
				[
					'post'   => '__CMX_WOO_PRODUCT_ID__',
					'action' => 'edit',
				],
				((string) ($parts['scheme'] ?? 'https')) . '://' . ((string) $parts['host']) . ((isset($parts['port']) && (int) $parts['port'] > 0) ? ':' . (int) $parts['port'] : '') . (string) $parts['path']
			);

		$validated = \esc_url_raw(\str_replace('__CMX_WOO_PRODUCT_ID__', '123', $template), ['http', 'https']);
		return \is_string($validated) && $validated !== '' ? $template : '';
	}

	function cmx_artikel_woo_product_link_url(string $woo_id): string {
		$woo_id = \trim($woo_id);
		if (!\preg_match('/^\d+$/', $woo_id)) return '';

		$template = cmx_artikel_woo_product_link_template();
		if ($template === '') return '';

		$url = \str_replace('__CMX_WOO_PRODUCT_ID__', \rawurlencode($woo_id), $template);
		$url = \esc_url_raw($url, ['http', 'https']);
		return \is_string($url) ? $url : '';
	}

	function cmx_artikel_format_quantity_display(mixed $value): string {
		if ($value === '' || $value === null) return '';
		$normalized = cmx_parse_number($value);
		if (!\is_finite($normalized)) return '';
	if (\abs($normalized - \round($normalized)) < 0.0005) return (string) (int) \round($normalized);
	return \rtrim(\rtrim(\number_format($normalized, 3, '.', "'"), '0'), '.');
}

function cmx_artikel_normalize_quantity_value(mixed $value): string {
	$raw = \trim((string) $value);
	if ($raw === '') return '';
	$normalized = cmx_parse_number($raw);
	if (!\is_finite($normalized)) return '';
	$normalized = \max(0, $normalized);
	if (\abs($normalized - \round($normalized)) < 0.0005) return (string) (int) \round($normalized);
	return \rtrim(\rtrim(\number_format($normalized, 3, '.', ''), '0'), '.');
}

function cmx_artikel_variant_taxonomy_choices(string $preferred_label = ''): array {
	$labels_raw = \defined(__NAMESPACE__ . '\\CMX_TAX_ARTIKEL')
		? (string) \constant(__NAMESPACE__ . '\\CMX_TAX_ARTIKEL')
		: '';
	$labels = \array_values(\array_filter(\array_map('trim', \explode(',', $labels_raw)), static fn($value) => $value !== ''));
	if ($labels === []) return [];

	$exclude = [];
	if (\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_EINHEITEN')) {
		$exclude[] = (string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_EINHEITEN');
	}
	if (\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_TYPEN')) {
		$exclude[] = (string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_TYPEN');
	}
	if (\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_KATEGORIEN')) {
		$exclude[] = (string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_KATEGORIEN');
	}
	if (\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_MARKEN')) {
		$exclude[] = (string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_MARKEN');
	}

	$out = [];
	foreach ($labels as $label) {
		$taxonomy = cmx_tax_key('artikel', cmx_no_umlaute($label));
		if ($taxonomy === '' || \in_array($taxonomy, $exclude, true) || !\taxonomy_exists($taxonomy)) continue;
		$terms = [];
		foreach (cmx_get_terms_safe($taxonomy) as $term) {
			$terms[] = [
				'id' => (int) ($term->term_id ?? 0),
				'name' => (string) ($term->name ?? ''),
			];
		}
		$out[$taxonomy] = [
			'label' => (string) $label,
			'taxonomy' => $taxonomy,
			'terms' => $terms,
		];
	}

	$preferred = $preferred_label !== '' ? cmx_tax_key('artikel', cmx_no_umlaute($preferred_label)) : '';
	if ($preferred !== '' && isset($out[$preferred])) {
		$current = [$preferred => $out[$preferred]];
		unset($out[$preferred]);
		$out = $current + $out;
	}

	return $out;
}

function cmx_artikel_variant_taxonomy_current(int $post_id, array $choices, string $meta_key, string $preferred_taxonomy = ''): string {
	if ($choices === []) return '';

	$stored = (string) \get_post_meta($post_id, $meta_key, true);
	if ($stored !== '' && isset($choices[$stored])) return $stored;

	if ($preferred_taxonomy !== '' && isset($choices[$preferred_taxonomy]) && cmx_get_single_term_id($post_id, $preferred_taxonomy) > 0) {
		return $preferred_taxonomy;
	}

	foreach (\array_keys($choices) as $taxonomy) {
		if (cmx_get_single_term_id($post_id, (string) $taxonomy) > 0) {
			return (string) $taxonomy;
		}
	}

	return (string) \array_key_first($choices);
}

function cmx_artikel_variant_money_normalize(mixed $value, bool $allow_negative = false): string {
	$raw = \trim((string) $value);
	if ($raw === '') return '';
	$normalized = cmx_parse_number($raw);
	if (!\is_finite($normalized)) return '';
	if (!$allow_negative) {
		$normalized = \max(0, $normalized);
	}
	return \number_format($normalized, 2, '.', '');
}

function cmx_artikel_variant_calculate_derived(string $ek, string $aufwand, string $vk): array {
	$ek_value = $ek !== '' ? cmx_parse_number($ek) : 0.0;
	$aufwand_value = $aufwand !== '' ? cmx_parse_number($aufwand) : 0.0;
	$vk_value = $vk !== '' ? cmx_parse_number($vk) : 0.0;

	if (!\is_finite($ek_value)) $ek_value = 0.0;
	if (!\is_finite($aufwand_value)) $aufwand_value = 0.0;
	if (!\is_finite($vk_value)) $vk_value = 0.0;

	$selbstkosten = \round($ek_value + $aufwand_value, 2);
	$deckungsbeitrag = \round($vk_value - $selbstkosten, 2);
	$marge = \round($vk_value - $ek_value, 2);

	return [
		'selbstkosten' => \number_format($selbstkosten, 2, '.', ''),
		'deckungsbeitrag' => \number_format($deckungsbeitrag, 2, '.', ''),
		'marge' => \number_format($marge, 2, '.', ''),
	];
}

function cmx_artikel_variant_row_default(array $left_choices, array $right_choices, array $overrides = []): array {
	$row = [
		'sku' => '',
		'anzahl' => '',
		'left_taxonomy' => (string) \array_key_first($left_choices),
		'left_term_id' => 0,
		'right_taxonomy' => (string) \array_key_first($right_choices),
		'right_term_id' => 0,
		'einheit_term_id' => 0,
		'ek' => '',
		'aufwand' => '',
		'vk' => '',
		'selbstkosten' => '0.00',
		'deckungsbeitrag' => '0.00',
		'marge' => '0.00',
		'belegtext' => '',
		'verkaufbar' => 1,
		'katalog' => 1,
		'woo_product_id' => 0,
		'woo_variation_id' => 0,
		'woo_variation_sku' => '',
	];

	return \array_replace($row, $overrides);
}

function cmx_artikel_variant_row_fields(): array {
	return [
		'sku',
		'anzahl',
		'left_taxonomy',
		'left_term_id',
		'right_taxonomy',
		'right_term_id',
		'einheit_term_id',
		'ek',
		'aufwand',
		'vk',
		'belegtext',
		'verkaufbar',
		'katalog',
		'woo_product_id',
		'woo_variation_id',
		'woo_variation_sku',
	];
}

function cmx_artikel_variant_row_meta_key(int $slot, string $field): string {
	return '_cmx_artikel_variant_' . \max(1, $slot) . '_' . \sanitize_key($field);
}

function cmx_artikel_variant_flat_slots(int $post_id): array {
	$slots = [];
	$count = (int) \get_post_meta($post_id, CMX_ARTIKEL_META_VARIANT_COUNT, true);
	if ($count > 0) {
		for ($slot = 1; $slot <= $count; $slot++) {
			$slots[$slot] = $slot;
		}
	}

	foreach (\array_keys((array) \get_post_meta($post_id)) as $meta_key) {
		if (!\preg_match('/^_cmx_artikel_variant_(\d+)_(sku|anzahl|left_taxonomy|left_term_id|right_taxonomy|right_term_id|einheit_term_id|ek|aufwand|vk|belegtext|verkaufbar|katalog|woo_product_id|woo_variation_id|woo_variation_sku)$/', (string) $meta_key, $matches)) {
			continue;
		}
		$slot = (int) ($matches[1] ?? 0);
		if ($slot > 0) {
			$slots[$slot] = $slot;
		}
	}

	\ksort($slots, \SORT_NUMERIC);
	return \array_values($slots);
}

function cmx_artikel_variant_clear_flat_rows(int $post_id): void {
	foreach (cmx_artikel_variant_flat_slots($post_id) as $slot) {
		foreach (cmx_artikel_variant_row_fields() as $field) {
			\delete_post_meta($post_id, cmx_artikel_variant_row_meta_key((int) $slot, $field));
		}
	}
	\delete_post_meta($post_id, CMX_ARTIKEL_META_VARIANT_COUNT);
}

function cmx_artikel_variant_row_normalize(array $row, array $left_choices, array $right_choices, array $base = []): array {
	$default = cmx_artikel_variant_row_default($left_choices, $right_choices, $base);

	$left_taxonomy = \sanitize_key((string) ($row['left_taxonomy'] ?? $default['left_taxonomy']));
	if ($left_taxonomy === '' || !isset($left_choices[$left_taxonomy])) {
		$left_taxonomy = (string) ($default['left_taxonomy'] ?? '');
	}

	$right_taxonomy = \sanitize_key((string) ($row['right_taxonomy'] ?? $default['right_taxonomy']));
	if ($right_taxonomy === '' || !isset($right_choices[$right_taxonomy])) {
		$right_taxonomy = (string) ($default['right_taxonomy'] ?? '');
	}

	$left_term_id = isset($row['left_term_id']) ? (int) $row['left_term_id'] : (int) ($default['left_term_id'] ?? 0);
	$right_term_id = isset($row['right_term_id']) ? (int) $row['right_term_id'] : (int) ($default['right_term_id'] ?? 0);

	$left_term = ($left_taxonomy !== '' && $left_term_id > 0) ? \get_term($left_term_id, $left_taxonomy) : null;
	if (!$left_term || \is_wp_error($left_term)) $left_term_id = 0;

	$right_term = ($right_taxonomy !== '' && $right_term_id > 0) ? \get_term($right_term_id, $right_taxonomy) : null;
	if (!$right_term || \is_wp_error($right_term)) $right_term_id = 0;

	$einheit_term_id = isset($row['einheit_term_id']) ? (int) $row['einheit_term_id'] : (int) ($default['einheit_term_id'] ?? 0);
	if ($einheit_term_id > 0) {
		$einheit_term = \taxonomy_exists(TAX_ARTIKEL_EINHEITEN) ? \get_term($einheit_term_id, TAX_ARTIKEL_EINHEITEN) : null;
		if (!$einheit_term || \is_wp_error($einheit_term)) {
			$einheit_term_id = 0;
		}
	}

	$ek = \array_key_exists('ek', $row)
		? cmx_artikel_variant_money_normalize($row['ek'])
		: (string) ($default['ek'] ?? '');
	$aufwand = \array_key_exists('aufwand', $row)
		? cmx_artikel_variant_money_normalize($row['aufwand'], true)
		: (string) ($default['aufwand'] ?? '');
	$vk = \array_key_exists('vk', $row)
		? cmx_artikel_variant_money_normalize($row['vk'])
		: (string) ($default['vk'] ?? '');
	$derived = cmx_artikel_variant_calculate_derived($ek, $aufwand, $vk);

	return [
		'sku' => \sanitize_text_field((string) ($row['sku'] ?? $default['sku'] ?? '')),
		'anzahl' => \array_key_exists('anzahl', $row)
			? cmx_artikel_normalize_quantity_value($row['anzahl'])
			: (string) ($default['anzahl'] ?? ''),
		'left_taxonomy' => $left_taxonomy,
		'left_term_id' => $left_term_id,
		'right_taxonomy' => $right_taxonomy,
		'right_term_id' => $right_term_id,
		'einheit_term_id' => $einheit_term_id,
		'ek' => $ek,
		'aufwand' => $aufwand,
		'vk' => $vk,
		'selbstkosten' => $derived['selbstkosten'],
		'deckungsbeitrag' => $derived['deckungsbeitrag'],
		'marge' => $derived['marge'],
		'belegtext' => \sanitize_textarea_field((string) ($row['belegtext'] ?? $default['belegtext'] ?? '')),
		'verkaufbar' => \array_key_exists('verkaufbar', $row)
			? (!empty($row['verkaufbar']) ? 1 : 0)
			: (int) ($default['verkaufbar'] ?? 1),
		'katalog' => \array_key_exists('katalog', $row)
			? (!empty($row['katalog']) ? 1 : 0)
			: (int) ($default['katalog'] ?? 1),
		'woo_product_id' => isset($row['woo_product_id'])
			? \max(0, (int) $row['woo_product_id'])
			: \max(0, (int) ($default['woo_product_id'] ?? 0)),
		'woo_variation_id' => isset($row['woo_variation_id'])
			? \max(0, (int) $row['woo_variation_id'])
			: \max(0, (int) ($default['woo_variation_id'] ?? 0)),
		'woo_variation_sku' => \sanitize_text_field((string) ($row['woo_variation_sku'] ?? $default['woo_variation_sku'] ?? '')),
	];
}

function cmx_artikel_variant_row_legacy(int $post_id, array $left_choices, array $right_choices): array {
	$size_preferred_taxonomy = cmx_tax_key('artikel', cmx_no_umlaute('Grössen'));
	$farben_preferred_taxonomy = cmx_tax_key('artikel', cmx_no_umlaute('Farben'));
	$left_taxonomy = cmx_artikel_variant_taxonomy_current($post_id, $left_choices, CMX_ARTIKEL_META_VARIANT_TAXONOMY, $size_preferred_taxonomy);
	$right_taxonomy = cmx_artikel_variant_taxonomy_current($post_id, $right_choices, CMX_ARTIKEL_META_VARIANT_FARBEN_TAXONOMY, $farben_preferred_taxonomy);
	$katalog_raw = (string) \get_post_meta($post_id, CMX_ARTIKEL_META_KATALOG, true);
	$katalog_exists = \metadata_exists('post', $post_id, CMX_ARTIKEL_META_KATALOG);

	return cmx_artikel_variant_row_normalize([
		'sku' => (string) cmx_meta_get($post_id, CMX_ARTIKEL_META_SKU, ''),
		'anzahl' => (string) cmx_meta_get($post_id, CMX_ARTIKEL_META_ANZAHL, ''),
		'left_taxonomy' => $left_taxonomy,
		'left_term_id' => $left_taxonomy !== '' ? cmx_get_single_term_id($post_id, $left_taxonomy) : 0,
		'right_taxonomy' => $right_taxonomy,
		'right_term_id' => $right_taxonomy !== '' ? cmx_get_single_term_id($post_id, $right_taxonomy) : 0,
		'einheit_term_id' => cmx_get_single_term_id($post_id, TAX_ARTIKEL_EINHEITEN),
		'ek' => (string) cmx_meta_get($post_id, CMX_ARTIKEL_META_EK, ''),
		'aufwand' => (string) cmx_meta_get($post_id, CMX_ARTIKEL_META_AUFWAND, ''),
		'vk' => (string) cmx_meta_get($post_id, CMX_ARTIKEL_META_VK, ''),
		'belegtext' => (string) \get_post_meta($post_id, CMX_META_ARTIKEL_BELEG, true),
		'verkaufbar' => (int) cmx_meta_get($post_id, CMX_ARTIKEL_META_VERKAUFBAR, 0) === 1 ? 0 : 1,
		'katalog' => (!$katalog_exists || $katalog_raw === '' || (int) $katalog_raw === 1) ? 1 : 0,
	], $left_choices, $right_choices);
}

function cmx_artikel_variant_rows_load(int $post_id, array $left_choices, array $right_choices): array {
	$rows = [];
	$legacy_base = cmx_artikel_variant_row_legacy($post_id, $left_choices, $right_choices);

	foreach (cmx_artikel_variant_flat_slots($post_id) as $slot) {
		$row = [];
		foreach (cmx_artikel_variant_row_fields() as $field) {
			$row[$field] = \get_post_meta($post_id, cmx_artikel_variant_row_meta_key((int) $slot, $field), true);
		}
		$rows[] = cmx_artikel_variant_row_normalize((array) $row, $left_choices, $right_choices, $legacy_base);
	}
	if ($rows !== []) {
		return \array_values($rows);
	}

	$stored = \get_post_meta($post_id, CMX_ARTIKEL_META_VARIANT_ROWS, true);

	if (\is_array($stored)) {
		foreach ($stored as $row) {
			if (!\is_array($row)) continue;
			$rows[] = cmx_artikel_variant_row_normalize($row, $left_choices, $right_choices, $legacy_base);
		}
	}

	if ($rows === []) {
		$rows[] = $legacy_base;
	}

	return \array_values($rows);
}

function cmx_artikel_variant_rows_persist(int $post_id, array $variant_rows, array $left_choices = [], array $right_choices = []): array {
	if ($left_choices === []) {
		$left_choices = cmx_artikel_variant_taxonomy_choices('Grössen');
	}
	if ($right_choices === []) {
		$right_choices = cmx_artikel_variant_taxonomy_choices('Farben');
	}

	$legacy_base = cmx_artikel_variant_row_legacy($post_id, $left_choices, $right_choices);
	$normalized_rows = [];
	foreach ($variant_rows as $row) {
		if (!\is_array($row)) continue;
		$normalized_rows[] = cmx_artikel_variant_row_normalize($row, $left_choices, $right_choices, $legacy_base);
	}

	if ($normalized_rows === []) {
		$normalized_rows[] = $legacy_base;
	}

	$normalized_rows = \array_values($normalized_rows);
	cmx_artikel_variant_clear_flat_rows($post_id);
	foreach ($normalized_rows as $index => $row) {
		$slot = $index + 1;
		foreach (cmx_artikel_variant_row_fields() as $field) {
			$value = $row[$field] ?? '';
			if ($field === 'verkaufbar' || $field === 'katalog' || $field === 'woo_product_id' || $field === 'woo_variation_id' || $field === 'left_term_id' || $field === 'right_term_id' || $field === 'einheit_term_id') {
				\update_post_meta($post_id, cmx_artikel_variant_row_meta_key($slot, $field), (int) $value);
				continue;
			}
			if ((string) $value === '') {
				continue;
			}
			\update_post_meta($post_id, cmx_artikel_variant_row_meta_key($slot, $field), (string) $value);
		}
	}
	\update_post_meta($post_id, CMX_ARTIKEL_META_VARIANT_ROWS, $normalized_rows);
	\update_post_meta($post_id, CMX_ARTIKEL_META_VARIANT_COUNT, \count($normalized_rows));

	$first_variant_row = $normalized_rows[0] ?? cmx_artikel_variant_row_default($left_choices, $right_choices);
	$first_left_taxonomy = (string) ($first_variant_row['left_taxonomy'] ?? '');
	$first_right_taxonomy = (string) ($first_variant_row['right_taxonomy'] ?? '');

	if ($first_left_taxonomy === '') {
		\delete_post_meta($post_id, CMX_ARTIKEL_META_VARIANT_TAXONOMY);
	} else {
		\update_post_meta($post_id, CMX_ARTIKEL_META_VARIANT_TAXONOMY, $first_left_taxonomy);
	}
	if ($first_right_taxonomy === '') {
		\delete_post_meta($post_id, CMX_ARTIKEL_META_VARIANT_FARBEN_TAXONOMY);
	} else {
		\update_post_meta($post_id, CMX_ARTIKEL_META_VARIANT_FARBEN_TAXONOMY, $first_right_taxonomy);
	}

	$first_sku = \sanitize_text_field((string) ($first_variant_row['sku'] ?? ''));
	\update_post_meta($post_id, CMX_ARTIKEL_META_SKU, $first_sku);

	$first_anzahl = \trim((string) ($first_variant_row['anzahl'] ?? ''));
	if ($first_anzahl === '') {
		\delete_post_meta($post_id, CMX_ARTIKEL_META_ANZAHL);
	} else {
		\update_post_meta($post_id, CMX_ARTIKEL_META_ANZAHL, $first_anzahl);
	}

	$first_belegtext = \sanitize_textarea_field((string) ($first_variant_row['belegtext'] ?? ''));
	if ($first_belegtext === '') {
		\delete_post_meta($post_id, CMX_META_ARTIKEL_BELEG);
	} else {
		\update_post_meta($post_id, CMX_META_ARTIKEL_BELEG, $first_belegtext);
	}

	$first_ek = \round(cmx_parse_number((string) ($first_variant_row['ek'] ?? '')), 2);
	if (!\is_finite($first_ek) || $first_ek < 0) $first_ek = 0.00;

	$first_aufwand = \round(cmx_parse_number((string) ($first_variant_row['aufwand'] ?? '')), 2);
	if (!\is_finite($first_aufwand)) $first_aufwand = 0.00;

	$first_vk = \round(cmx_parse_number((string) ($first_variant_row['vk'] ?? '')), 2);
	if (!\is_finite($first_vk) || $first_vk < 0) $first_vk = 0.00;

	$first_selbstkosten = \round($first_ek + $first_aufwand, 2);
	$first_deckungsbeitrag = \round($first_vk - $first_selbstkosten, 2);

	\update_post_meta($post_id, CMX_ARTIKEL_META_EK, $first_ek);
	\update_post_meta($post_id, CMX_ARTIKEL_META_AUFWAND, $first_aufwand);
	\update_post_meta($post_id, CMX_ARTIKEL_META_VK, $first_vk);
	\update_post_meta($post_id, CMX_ARTIKEL_META_SELBSTKOSTEN, $first_selbstkosten);
	\update_post_meta($post_id, CMX_ARTIKEL_META_DECKUNGSBEITRAG, $first_deckungsbeitrag);
	\update_post_meta($post_id, CMX_ARTIKEL_META_MARGE, \round($first_vk - $first_ek, 2));
	\delete_post_meta($post_id, CMX_ARTIKEL_META_MEHRWERT);
	\delete_post_meta($post_id, '_cmx_artikel_vk_lock');
	\update_post_meta($post_id, CMX_ARTIKEL_META_VERKAUFBAR, !empty($first_variant_row['verkaufbar']) ? 0 : 1);
	\update_post_meta($post_id, CMX_ARTIKEL_META_KATALOG, !empty($first_variant_row['katalog']) ? 1 : 0);

	if (\taxonomy_exists(TAX_ARTIKEL_EINHEITEN)) {
		$first_einheit_id = (int) ($first_variant_row['einheit_term_id'] ?? 0);
		\wp_set_post_terms($post_id, $first_einheit_id > 0 ? [$first_einheit_id] : [], TAX_ARTIKEL_EINHEITEN, false);
	}

	$all_variant_taxonomies = \array_values(\array_unique(\array_merge(\array_keys($left_choices), \array_keys($right_choices))));
	$term_ids_by_taxonomy = [];
	foreach ($all_variant_taxonomies as $taxonomy) {
		$term_ids_by_taxonomy[(string) $taxonomy] = [];
	}

	foreach ($normalized_rows as $row) {
		$selections = [
			[
				'taxonomy' => (string) ($row['left_taxonomy'] ?? ''),
				'term_id' => (int) ($row['left_term_id'] ?? 0),
			],
			[
				'taxonomy' => (string) ($row['right_taxonomy'] ?? ''),
				'term_id' => (int) ($row['right_term_id'] ?? 0),
			],
		];
		foreach ($selections as $selection) {
			$taxonomy = (string) ($selection['taxonomy'] ?? '');
			$term_id = (int) ($selection['term_id'] ?? 0);
			if ($taxonomy === '' || $term_id <= 0 || !isset($term_ids_by_taxonomy[$taxonomy])) continue;
			$term_ids_by_taxonomy[$taxonomy][] = $term_id;
		}
	}

	foreach ($all_variant_taxonomies as $taxonomy) {
		$taxonomy = (string) $taxonomy;
		$term_ids = \array_values(\array_unique(\array_map('intval', $term_ids_by_taxonomy[$taxonomy] ?? [])));
		\wp_set_post_terms($post_id, $term_ids, $taxonomy, false);
	}

	return $normalized_rows;
}

function cmx_artikel_variant_row_markup(
	int|string $index,
	array $row,
	array $left_choices,
	array $right_choices,
	array $einheiten,
	string $einheiten_url = ''
): string {
	$index_attr = (string) $index;
	$left_taxonomy = (string) ($row['left_taxonomy'] ?? '');
	$left_term_id = (int) ($row['left_term_id'] ?? 0);
	$right_taxonomy = (string) ($row['right_taxonomy'] ?? '');
	$right_term_id = (int) ($row['right_term_id'] ?? 0);
	$left_label = $left_taxonomy !== '' && isset($left_choices[$left_taxonomy]['label'])
		? (string) $left_choices[$left_taxonomy]['label']
		: 'Grössen';
	$right_label = $right_taxonomy !== '' && isset($right_choices[$right_taxonomy]['label'])
		? (string) $right_choices[$right_taxonomy]['label']
		: 'Farben';
	$left_terms = $left_taxonomy !== '' && isset($left_choices[$left_taxonomy]['terms'])
		? (array) $left_choices[$left_taxonomy]['terms']
		: [];
	$right_terms = $right_taxonomy !== '' && isset($right_choices[$right_taxonomy]['terms'])
		? (array) $right_choices[$right_taxonomy]['terms']
		: [];
	$anzahl_display = cmx_artikel_format_quantity_display($row['anzahl'] ?? '');
	$ek_display = ($row['ek'] ?? '') !== '' ? cmx_format_swiss_number((string) $row['ek'], 2) : '';
	$aufwand_display = ($row['aufwand'] ?? '') !== '' ? cmx_format_swiss_number((string) $row['aufwand'], 2) : '';
	$vk_display = ($row['vk'] ?? '') !== '' ? cmx_format_swiss_number((string) $row['vk'], 2) : '';
	$selbstkosten_display = cmx_format_swiss_number((string) ($row['selbstkosten'] ?? '0.00'), 2);
	$deckungsbeitrag_display = cmx_format_swiss_number((string) ($row['deckungsbeitrag'] ?? '0.00'), 2);
	$marge_display = cmx_format_swiss_number((string) ($row['marge'] ?? '0.00'), 2);
	$einheit_term_id = (int) ($row['einheit_term_id'] ?? 0);

	\ob_start();
	echo '<div class="cmx-variant-block" data-variant-index="' . \esc_attr($index_attr) . '">';
	echo '<div class="cmx-price-row" role="group" aria-label="Variante">';

	echo '<div class="cmx-f cmx-f--sku">';
	echo '<label for="cmx_artikel_variant_sku_' . \esc_attr($index_attr) . '">Artikel-Nr.</label>';
	echo '<input type="text" id="cmx_artikel_variant_sku_' . \esc_attr($index_attr) . '" name="cmx_artikel_variants[' . \esc_attr($index_attr) . '][sku]" class="cmx-variant-sku" data-variant-field-key="sku" value="' . \esc_attr((string) ($row['sku'] ?? '')) . '" autocomplete="off">';
	echo '</div>';

	echo '<div class="cmx-f cmx-f--xxs">';
	echo '<label for="cmx_artikel_variant_anzahl_' . \esc_attr($index_attr) . '">Anzahl</label>';
	echo '<input type="text" inputmode="decimal" id="cmx_artikel_variant_anzahl_' . \esc_attr($index_attr) . '" name="cmx_artikel_variants[' . \esc_attr($index_attr) . '][anzahl]" class="cmx-variant-anzahl" data-variant-field-key="anzahl" value="' . \esc_attr($anzahl_display) . '" autocomplete="off">';
	echo '</div>';

	echo '<div class="cmx-f cmx-f--half">';
	echo '<label id="cmx_artikel_variant_term_label_' . \esc_attr($index_attr) . '" class="cmx-taxonomy-label cmx-variant-label" data-variant-slot="left" for="cmx_artikel_variant_term_' . \esc_attr($index_attr) . '" role="button" tabindex="0" title="Klicken, um eine andere Taxonomie auszuwählen">' . \esc_html($left_label) . '</label>';
	echo '<select id="cmx_artikel_variant_taxonomy_' . \esc_attr($index_attr) . '" name="cmx_artikel_variants[' . \esc_attr($index_attr) . '][left_taxonomy]" class="cmx-taxonomy-picker cmx-variant-taxonomy" data-variant-slot="left" data-variant-field-key="left_taxonomy" aria-label="Taxonomie auswählen">';
	foreach ($left_choices as $taxonomy => $config) {
		echo '<option value="' . \esc_attr((string) $taxonomy) . '" ' . \selected($left_taxonomy, (string) $taxonomy, false) . '>' . \esc_html((string) ($config['label'] ?? $taxonomy)) . '</option>';
	}
	echo '</select>';
	echo '<select id="cmx_artikel_variant_term_' . \esc_attr($index_attr) . '" name="cmx_artikel_variants[' . \esc_attr($index_attr) . '][left_term_id]" class="cmx-variant-term" data-variant-slot="left" data-variant-field-key="left_term_id">';
	if ($left_terms === []) {
		echo '<option value="0">— keine Werte —</option>';
	} else {
		echo '<option value="0">— auswählen —</option>';
		foreach ($left_terms as $term) {
			$term_id = (int) ($term['id'] ?? 0);
			$term_name = (string) ($term['name'] ?? '');
			if ($term_id <= 0 || $term_name === '') continue;
			echo '<option value="' . $term_id . '" ' . \selected($left_term_id, $term_id, false) . '>' . \esc_html($term_name) . '</option>';
		}
	}
	echo '</select>';
	echo '</div>';

	echo '<div class="cmx-f cmx-f--half">';
	echo '<label id="cmx_artikel_variant_farben_term_label_' . \esc_attr($index_attr) . '" class="cmx-taxonomy-label cmx-variant-label" data-variant-slot="right" for="cmx_artikel_variant_farben_term_' . \esc_attr($index_attr) . '" role="button" tabindex="0" title="Klicken, um eine andere Taxonomie auszuwählen">' . \esc_html($right_label) . '</label>';
	echo '<select id="cmx_artikel_variant_farben_taxonomy_' . \esc_attr($index_attr) . '" name="cmx_artikel_variants[' . \esc_attr($index_attr) . '][right_taxonomy]" class="cmx-taxonomy-picker cmx-variant-taxonomy" data-variant-slot="right" data-variant-field-key="right_taxonomy" aria-label="Taxonomie auswählen">';
	foreach ($right_choices as $taxonomy => $config) {
		echo '<option value="' . \esc_attr((string) $taxonomy) . '" ' . \selected($right_taxonomy, (string) $taxonomy, false) . '>' . \esc_html((string) ($config['label'] ?? $taxonomy)) . '</option>';
	}
	echo '</select>';
	echo '<select id="cmx_artikel_variant_farben_term_' . \esc_attr($index_attr) . '" name="cmx_artikel_variants[' . \esc_attr($index_attr) . '][right_term_id]" class="cmx-variant-term" data-variant-slot="right" data-variant-field-key="right_term_id">';
	if ($right_terms === []) {
		echo '<option value="0">— keine Werte —</option>';
	} else {
		echo '<option value="0">— auswählen —</option>';
		foreach ($right_terms as $term) {
			$term_id = (int) ($term['id'] ?? 0);
			$term_name = (string) ($term['name'] ?? '');
			if ($term_id <= 0 || $term_name === '') continue;
			echo '<option value="' . $term_id . '" ' . \selected($right_term_id, $term_id, false) . '>' . \esc_html($term_name) . '</option>';
		}
	}
	echo '</select>';
	echo '</div>';

	echo '<div class="cmx-f cmx-f--half">';
	echo '<label for="cmx_artikel_variant_einheit_' . \esc_attr($index_attr) . '">';
	if ($einheiten_url !== '') {
		echo '<a href="' . \esc_url($einheiten_url) . '" target="_blank" rel="noopener noreferrer" style="text-decoration:none;" title="Einheiten verwalten">Einheit</a>';
	} else {
		echo 'Einheit';
	}
	echo '</label>';
	echo '<select id="cmx_artikel_variant_einheit_' . \esc_attr($index_attr) . '" name="cmx_artikel_variants[' . \esc_attr($index_attr) . '][einheit_term_id]" class="cmx-variant-einheit" data-variant-field-key="einheit_term_id">';
	echo '<option value="0">— auswählen —</option>';
	foreach ($einheiten as $t) {
		$name = (string) ($t->name ?? '');
		echo '<option value="' . (int) $t->term_id . '" ' . \selected($einheit_term_id, $t->term_id, false) . '>' . \esc_html($name) . '</option>';
	}
	echo '</select>';
	echo '</div>';

	echo '<div class="cmx-f cmx-f--xs">';
	echo '<label for="cmx_artikel_variant_ek_' . \esc_attr($index_attr) . '">Einkaufspreis</label>';
	echo '<input type="text" inputmode="decimal" id="cmx_artikel_variant_ek_' . \esc_attr($index_attr) . '" name="cmx_artikel_variants[' . \esc_attr($index_attr) . '][ek]" class="cmx-variant-ek" data-variant-field-key="ek" value="' . \esc_attr($ek_display) . '">';
	echo '</div>';

	echo '<div class="cmx-f cmx-f--xs">';
	echo '<label for="cmx_artikel_variant_aufwand_' . \esc_attr($index_attr) . '">Aufwand</label>';
	echo '<input type="text" inputmode="decimal" id="cmx_artikel_variant_aufwand_' . \esc_attr($index_attr) . '" name="cmx_artikel_variants[' . \esc_attr($index_attr) . '][aufwand]" class="cmx-variant-aufwand" data-variant-field-key="aufwand" value="' . \esc_attr($aufwand_display) . '">';
	echo '</div>';

	echo '<div class="cmx-f cmx-f--xs">';
	echo '<label for="cmx_artikel_variant_selbstkosten_' . \esc_attr($index_attr) . '">Selbstkosten</label>';
	echo '<input type="text" inputmode="decimal" id="cmx_artikel_variant_selbstkosten_' . \esc_attr($index_attr) . '" name="cmx_artikel_variants[' . \esc_attr($index_attr) . '][selbstkosten]" class="cmx-variant-selbstkosten" data-variant-field-key="selbstkosten" value="' . \esc_attr($selbstkosten_display) . '" readonly>';
	echo '</div>';

	echo '<div class="cmx-f cmx-f--xs">';
	echo '<label for="cmx_artikel_variant_vk_' . \esc_attr($index_attr) . '" class="cmx-variant-vk-label" style="cursor:pointer;" title="Klicken, um den Vorgabe-Deckungsbeitrag als Vorschlag zu übernehmen">Verkaufspreis</label>';
	echo '<input type="text" inputmode="decimal" id="cmx_artikel_variant_vk_' . \esc_attr($index_attr) . '" name="cmx_artikel_variants[' . \esc_attr($index_attr) . '][vk]" class="cmx-variant-vk" data-variant-field-key="vk" value="' . \esc_attr($vk_display) . '">';
	echo '</div>';

	echo '<div class="cmx-f cmx-f--xs">';
	echo '<label for="cmx_artikel_variant_deckungsbeitrag_' . \esc_attr($index_attr) . '">Deckungsbeitrag</label>';
	echo '<input type="text" inputmode="decimal" id="cmx_artikel_variant_deckungsbeitrag_' . \esc_attr($index_attr) . '" name="cmx_artikel_variants[' . \esc_attr($index_attr) . '][deckungsbeitrag]" class="cmx-variant-deckungsbeitrag" data-variant-field-key="deckungsbeitrag" value="' . \esc_attr($deckungsbeitrag_display) . '" readonly>';
	echo '</div>';

	echo '<div class="cmx-f cmx-f--xs">';
	echo '<label for="cmx_artikel_variant_marge_' . \esc_attr($index_attr) . '">Marge (VK − EK)</label>';
	echo '<input type="text" inputmode="decimal" id="cmx_artikel_variant_marge_' . \esc_attr($index_attr) . '" name="cmx_artikel_variants[' . \esc_attr($index_attr) . '][marge]" class="cmx-variant-marge" data-variant-field-key="marge" value="' . \esc_attr($marge_display) . '" readonly>';
	echo '</div>';

	echo '<div class="cmx-f cmx-f--full">';
	echo '<textarea id="cmx_artikel_variant_beleg_text_' . \esc_attr($index_attr) . '" name="cmx_artikel_variants[' . \esc_attr($index_attr) . '][belegtext]" class="cmx-variant-belegtext" data-variant-field-key="belegtext" rows="1" aria-label="Belegtext" placeholder="Hier die weitere Beschreibung für den Text im Beleg...">' . \esc_textarea((string) ($row['belegtext'] ?? '')) . '</textarea>';
	echo '</div>';

	echo '<div class="cmx-check">';
	echo '<div class="cmx-check-left">';
	echo '<label><input type="checkbox" name="cmx_artikel_variants[' . \esc_attr($index_attr) . '][verkaufbar]" class="cmx-variant-verkaufbar" data-variant-field-key="verkaufbar" value="1" ' . \checked((int) ($row['verkaufbar'] ?? 1), 1, false) . '> verkaufbar</label>';
	echo '<label><input type="checkbox" name="cmx_artikel_variants[' . \esc_attr($index_attr) . '][katalog]" class="cmx-variant-katalog" data-variant-field-key="katalog" value="1" ' . \checked((int) ($row['katalog'] ?? 1), 1, false) . '> Katalog</label>';
	echo '</div>';
	echo '<div class="cmx-variant-actions">';
	echo '<button type="button" class="button button-secondary cmx-variant-add">Variante hinzufügen</button>';
	echo '<button type="button" class="button button-secondary cmx-variant-del" title="Variante löschen" aria-label="Variante löschen"><span class="dashicons dashicons-trash"></span></button>';
	echo '</div>';
	echo '</div>';

	echo '</div>';
	echo '</div>';

	return (string) \ob_get_clean();
}

function cmx_artikel_waehrung_preise_box_html(\WP_Post $post): void {
	cmx_artikel_render_save_nonce_once();
	echo '<input type="hidden" name="cmx_artikel_konditionen_payload" value="1">';

	$settings = (array) \get_option(\CLOUDMEISTER\CMX\Buero\CMX_SETTINGS_MAIN, []);
	$deckungsbeitrag_percent = isset($settings['artikel_deckungsbeitrag']) ? (string) $settings['artikel_deckungsbeitrag'] : '';
	$variant_taxonomies = cmx_artikel_variant_taxonomy_choices('Grössen');
	$farben_variant_taxonomies = cmx_artikel_variant_taxonomy_choices('Farben');
	$einheiten = cmx_get_terms_safe(TAX_ARTIKEL_EINHEITEN);
	$einheiten_url = \taxonomy_exists(TAX_ARTIKEL_EINHEITEN)
		? \admin_url('edit-tags.php?taxonomy=' . \rawurlencode((string) TAX_ARTIKEL_EINHEITEN) . '&post_type=artikel')
		: '';
	$variant_rows = cmx_artikel_variant_rows_load((int) $post->ID, $variant_taxonomies, $farben_variant_taxonomies);
	$variant_template = cmx_artikel_variant_row_markup(
		'__INDEX__',
		cmx_artikel_variant_row_default($variant_taxonomies, $farben_variant_taxonomies),
		$variant_taxonomies,
		$farben_variant_taxonomies,
		$einheiten,
		$einheiten_url
	);
	$variant_taxonomies_json = \wp_json_encode($variant_taxonomies) ?: '{}';
	$farben_variant_taxonomies_json = \wp_json_encode($farben_variant_taxonomies) ?: '{}';

	echo '<style>
		#cmx-artikel-variant-rows{display:flex;flex-direction:column;gap:14px}
		.cmx-variant-block{padding:8px 8px 10px;margin:0;border:1px solid #ddd;border-radius:6px;background:#fafafa}
		.cmx-variant-block:last-child{margin-bottom:0}
		.cmx-price-row{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}
		.cmx-price-row .cmx-f{display:flex;flex-direction:column;min-width:140px;flex:1 1 180px;max-width:320px}
		.cmx-price-row .cmx-f--xs{min-width:96px;max-width:132px;flex:1 1 112px}
		.cmx-price-row .cmx-f--xxs{min-width:48px;max-width:66px;flex:1 1 56px}
		.cmx-price-row .cmx-f--sm{min-width:160px;max-width:200px;flex:1 1 180px}
		.cmx-price-row .cmx-f--sku{min-width:112px;max-width:138px;flex:1 1 120px}
		.cmx-price-row .cmx-f--md{min-width:220px;max-width:320px;flex:1 1 220px}
		.cmx-price-row .cmx-f--lg{min-width:260px;max-width:420px;flex:1 1 260px}
		.cmx-price-row .cmx-f--full{min-width:100%;max-width:100%;flex:1 1 100%}
		.cmx-price-row .cmx-f--half{min-width:118px;max-width:150px;flex:1 1 124px}
		.cmx-price-row .cmx-f label{font-weight:600;margin-bottom:4px}
		.cmx-price-row .cmx-f .cmx-taxonomy-label{display:inline-flex;align-items:center;gap:4px;cursor:pointer;text-decoration:underline dotted}
		.cmx-price-row .cmx-f .cmx-taxonomy-label::after{content:"▾";font-size:10px;opacity:.75}
		.cmx-price-row .cmx-f .cmx-taxonomy-picker{display:none;margin:0 0 6px}
		.cmx-price-row .cmx-f input[type="number"],
		.cmx-price-row .cmx-f input[type="text"],
		.cmx-price-row .cmx-f select,
		.cmx-price-row .cmx-f textarea{width:100%}
		.cmx-price-row .cmx-f input[readonly]{background:#f6f7f7;color:#2c3338}
		.cmx-price-row .cmx-f textarea{min-height:38px;height:38px;resize:vertical}
		.cmx-price-row .cmx-check{display:flex;align-items:center;justify-content:space-between;gap:14px;flex:0 0 100%;margin:2px 0 0}
		.cmx-price-row .cmx-check-left{display:flex;align-items:center;gap:14px;white-space:nowrap;flex-wrap:wrap}
		.cmx-price-row .cmx-check-left label{position:relative;top:-5px}
		.cmx-price-row .cmx-variant-actions{display:flex;align-items:center;gap:8px;margin-left:auto}
		.cmx-price-row .cmx-variant-actions .cmx-variant-add{display:none;min-width:170px;width:170px;height:36px;box-sizing:border-box;align-items:center;justify-content:center;text-align:center;padding:0 10px;border-radius:6px}
		.cmx-price-row .cmx-variant-actions .cmx-variant-del{min-width:36px;height:36px;display:inline-flex;align-items:center;justify-content:center;padding:0 8px;border-radius:6px}
		.cmx-price-row .cmx-variant-actions .cmx-variant-del .dashicons{color:#d63638;transform:translateY(5px)}
		.cmx-price-row .cmx-variant-actions button[disabled]{opacity:.45;cursor:not-allowed;pointer-events:none}
		@media (max-width: 1200px){
			.cmx-price-row{gap:10px}
			.cmx-price-row .cmx-f{max-width:100%}
			.cmx-price-row .cmx-check{margin-left:0;margin-top:6px}
		}
		@media (max-width: 782px){
			.cmx-price-row .cmx-check{align-items:flex-start;flex-direction:column}
			.cmx-price-row .cmx-variant-actions{margin-left:0}
		}
	</style>';

	echo '<div id="cmx-artikel-variant-rows">';
	foreach ($variant_rows as $i => $variant_row) {
		echo cmx_artikel_variant_row_markup((int) $i, $variant_row, $variant_taxonomies, $farben_variant_taxonomies, $einheiten, $einheiten_url);
	}
	echo '</div>';
	echo '<script type="text/html" id="cmx-artikel-variant-row-template">' . $variant_template . '</script>';

	echo '<script>
	document.addEventListener("DOMContentLoaded", function(){
		const variantRows = document.getElementById("cmx-artikel-variant-rows");
		const variantTemplate = document.getElementById("cmx-artikel-variant-row-template");
		const variantTaxonomies = ' . $variant_taxonomies_json . ';
		const farbenVariantTaxonomies = ' . $farben_variant_taxonomies_json . ';
		const defaultDeckungsbeitragPercent = num(' . \wp_json_encode($deckungsbeitrag_percent) . ');

		function num(v){
			let s = (v ?? "0").toString().trim();
			if (s === "") return 0;
			s = s.replace(/[\s\u00A0\u202F]+/g, "").replace(/[\u0027’‘`´′]/g, "");
			const hasComma = s.indexOf(",") > -1;
			const hasDot = s.indexOf(".") > -1;
			if (hasComma && hasDot) {
				if (s.lastIndexOf(",") > s.lastIndexOf(".")) {
					s = s.replace(/\./g, "").replace(/,/g, ".");
				} else {
					s = s.replace(/,/g, "");
				}
			} else {
				s = s.replace(/,/g, ".");
			}
			const n = parseFloat(s);
			return isFinite(n) ? n : 0;
		}
		function formatCH(v){
			const parts = (Number(v) || 0).toFixed(2).split(".");
			let left = parts[0];
			let out = "";
			while (left.length > 3) {
				out = "\'" + left.slice(-3) + out;
				left = left.slice(0, -3);
			}
			return left + out + "." + parts[1];
		}
		function formatQty(v){
			const parts = (Math.max(0, Number(num(v)) || 0)).toFixed(3).split(".");
			let left = parts[0];
			let out = "";
			while (left.length > 3) {
				out = "\'" + left.slice(-3) + out;
				left = left.slice(0, -3);
			}
			const frac = parts[1].replace(/0+$/, "");
			return left + out + (frac !== "" ? "." + frac : "");
		}
		function updateVariantTerms(labelEl, termEl, taxonomyMap, selectedTaxonomy, selectedTermId){
			if (!termEl) return;
			const config = taxonomyMap && taxonomyMap[selectedTaxonomy] ? taxonomyMap[selectedTaxonomy] : null;
			const terms = config && Array.isArray(config.terms) ? config.terms : [];
			const target = String(selectedTermId || "0");
			if (labelEl && config && config.label) {
				labelEl.textContent = config.label;
			}
			if (!terms.length) {
				termEl.innerHTML = "<option value=\"0\">— keine Werte —</option>";
				termEl.value = "0";
				termEl.disabled = true;
				return;
			}
			var html = "<option value=\"0\">— auswählen —</option>";
			terms.forEach(function(term){
				const id = parseInt(term && term.id ? term.id : 0, 10) || 0;
				const name = term && term.name ? String(term.name) : "";
				if (!id || !name) return;
				const selected = String(id) === target ? " selected" : "";
				html += "<option value=\"" + id + "\"" + selected + ">" + name.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;") + "</option>";
			});
			termEl.innerHTML = html;
			termEl.disabled = false;
			if (!termEl.querySelector("option[selected]")) {
				termEl.value = "0";
			}
		}
		function initVariantSelector(labelEl, taxonomyEl, termEl, taxonomyMap){
			if (!labelEl || !taxonomyEl || !termEl) return;
			if (taxonomyEl.dataset.variantInit === "1") {
				updateVariantTerms(labelEl, termEl, taxonomyMap, taxonomyEl.value, termEl.value);
				return;
			}
			const toggleTaxonomyPicker = function(e){
				if (e) e.preventDefault();
				taxonomyEl.style.display = taxonomyEl.style.display === "block" ? "none" : "block";
				if (taxonomyEl.style.display === "block") {
					taxonomyEl.focus();
				}
			};
			labelEl.addEventListener("click", toggleTaxonomyPicker);
			labelEl.addEventListener("keydown", function(e){
				if (e.key === "Enter" || e.key === " ") {
					toggleTaxonomyPicker(e);
				}
			});
			taxonomyEl.addEventListener("change", function(){
				updateVariantTerms(labelEl, termEl, taxonomyMap, this.value, 0);
				this.style.display = "none";
				termEl.focus();
			});
			taxonomyEl.addEventListener("blur", function(){
				window.setTimeout(function(){
					if (document.activeElement !== taxonomyEl) {
						taxonomyEl.style.display = "none";
					}
				}, 120);
			});
			taxonomyEl.dataset.variantInit = "1";
			updateVariantTerms(labelEl, termEl, taxonomyMap, taxonomyEl.value, termEl.value);
		}
		function getVariantBlocks(){
			return variantRows ? Array.from(variantRows.querySelectorAll(".cmx-variant-block")) : [];
		}
		function getVariantFields(block){
			return {
				anzahl: block.querySelector(".cmx-variant-anzahl"),
				leftLabel: block.querySelector(".cmx-variant-label[data-variant-slot=\"left\"]"),
				leftTaxonomy: block.querySelector(".cmx-variant-taxonomy[data-variant-slot=\"left\"]"),
				leftTerm: block.querySelector(".cmx-variant-term[data-variant-slot=\"left\"]"),
				rightLabel: block.querySelector(".cmx-variant-label[data-variant-slot=\"right\"]"),
				rightTaxonomy: block.querySelector(".cmx-variant-taxonomy[data-variant-slot=\"right\"]"),
				rightTerm: block.querySelector(".cmx-variant-term[data-variant-slot=\"right\"]"),
				ek: block.querySelector(".cmx-variant-ek"),
				aufwand: block.querySelector(".cmx-variant-aufwand"),
				vk: block.querySelector(".cmx-variant-vk"),
				selbstkosten: block.querySelector(".cmx-variant-selbstkosten"),
				deckungsbeitrag: block.querySelector(".cmx-variant-deckungsbeitrag"),
				marge: block.querySelector(".cmx-variant-marge"),
				vkLabel: block.querySelector(".cmx-variant-vk-label"),
				add: block.querySelector(".cmx-variant-add"),
				del: block.querySelector(".cmx-variant-del")
			};
		}
		function recalcDerived(block){
			const fields = getVariantFields(block);
			const selbstkosten = num(fields.ek?.value) + num(fields.aufwand?.value);
			const deckungsbeitrag = num(fields.vk?.value) - selbstkosten;
			const marge = num(fields.vk?.value) - num(fields.ek?.value);
			if (fields.selbstkosten) fields.selbstkosten.value = formatCH(selbstkosten);
			if (fields.deckungsbeitrag) fields.deckungsbeitrag.value = formatCH(deckungsbeitrag);
			if (fields.marge) fields.marge.value = formatCH(marge);
		}
		function suggestVkFromDefaults(block){
			const fields = getVariantFields(block);
			const selbstkosten = num(fields.ek?.value) + num(fields.aufwand?.value);
			if (!fields.vk || selbstkosten <= 0 || defaultDeckungsbeitragPercent <= 0) {
				return false;
			}
			const vkSuggestion = selbstkosten + (selbstkosten * defaultDeckungsbeitragPercent / 100);
			fields.vk.value = formatCH(vkSuggestion);
			recalcDerived(block);
			window.setTimeout(function(){
				try { fields.vk.focus(); fields.vk.select(); } catch(e) {}
			}, 0);
			return true;
		}
		function maybeSuggestVkOnEmpty(block){
			const fields = getVariantFields(block);
			if (!fields.vk) return false;
			const raw = (fields.vk.value ?? "").toString().trim();
			if (raw !== "" && num(raw) !== 0) {
				return false;
			}
			return suggestVkFromDefaults(block);
		}
		function enableAutoSelect(el){
			if (!el || el.dataset.autoSelectInit === "1") return;
			el.dataset.autoSelectInit = "1";
			el.addEventListener("focus", function(){ this.select(); });
			el.addEventListener("click", function(){ this.select(); });
			el.addEventListener("mouseup", function(e){ e.preventDefault(); });
		}
		function syncVariantTerms(block){
			const fields = getVariantFields(block);
			initVariantSelector(fields.leftLabel, fields.leftTaxonomy, fields.leftTerm, variantTaxonomies);
			initVariantSelector(fields.rightLabel, fields.rightTaxonomy, fields.rightTerm, farbenVariantTaxonomies);
		}
		function formatVariantBlock(block){
			const fields = getVariantFields(block);
			if (fields.anzahl && fields.anzahl.value !== "") {
				fields.anzahl.value = formatQty(fields.anzahl.value);
			}
			[fields.ek, fields.aufwand, fields.vk].forEach(function(el){
				if (!el) return;
				if (el.value !== "") el.value = formatCH(num(el.value));
			});
			recalcDerived(block);
		}
		function nextVariantIndex(){
			let max = -1;
			getVariantBlocks().forEach(function(block){
				const index = parseInt(block.dataset.variantIndex || "-1", 10);
				if (index > max) max = index;
			});
			return max + 1;
		}
		function buildVariantBlock(index){
			if (!variantTemplate) return null;
			const html = (variantTemplate.innerHTML || "").replace(/__INDEX__/g, String(index)).trim();
			if (!html) return null;
			const wrapper = document.createElement("div");
			wrapper.innerHTML = html;
			return wrapper.firstElementChild;
		}
		function copyVariantBlockValues(source, target){
			const sourceValues = {};
			source.querySelectorAll("[data-variant-field-key]").forEach(function(el){
				sourceValues[el.dataset.variantFieldKey] = el.type === "checkbox" ? !!el.checked : (el.value ?? "");
			});
			target.querySelectorAll("[data-variant-field-key]").forEach(function(el){
				const key = el.dataset.variantFieldKey;
				if (!(key in sourceValues)) return;
				if (el.type === "checkbox") {
					el.checked = !!sourceValues[key];
				} else {
					el.value = sourceValues[key];
				}
			});
			syncVariantTerms(target);
			formatVariantBlock(target);
		}
		function syncVariantActionState(){
			const blocks = getVariantBlocks();
			blocks.forEach(function(block, index){
				const fields = getVariantFields(block);
				if (fields.add) {
					fields.add.style.display = index === blocks.length - 1 ? "inline-flex" : "none";
				}
				if (fields.del) {
					const disabled = blocks.length <= 1;
					fields.del.disabled = disabled;
					fields.del.setAttribute("aria-disabled", disabled ? "true" : "false");
				}
			});
		}
			function insertVariantBlock(sourceBlock){
				const block = buildVariantBlock(nextVariantIndex());
				if (!block || !variantRows) return;
				if (sourceBlock) {
					sourceBlock.insertAdjacentElement("afterend", block);
				} else {
					variantRows.appendChild(block);
				}
				bindVariantBlock(block);
			syncVariantActionState();
			const sku = block.querySelector(".cmx-variant-sku");
			if (sku) {
				sku.focus();
				try { sku.select(); } catch(e) {}
			}
		}
		function removeVariantBlock(block){
			const blocks = getVariantBlocks();
			if (!block || blocks.length <= 1) return;
			block.remove();
			syncVariantActionState();
		}
		function bindVariantBlock(block){
			if (!block) return;
			syncVariantTerms(block);
			formatVariantBlock(block);
			const fields = getVariantFields(block);
			if (block.dataset.variantBlockInit === "1") return;
			block.dataset.variantBlockInit = "1";
			[fields.ek, fields.aufwand, fields.vk].forEach(function(el){
				if (!el) return;
				["input","change"].forEach(function(evt){
					el.addEventListener(evt, function(){ recalcDerived(block); }, {passive:true});
				});
				el.addEventListener("blur", function(){
					const raw = (this.value ?? "").toString().trim();
					if (raw !== "") {
						this.value = formatCH(num(raw));
					}
					recalcDerived(block);
				});
				enableAutoSelect(el);
			});
			const sku = block.querySelector(".cmx-variant-sku");
			if (sku) enableAutoSelect(sku);
			if (fields.anzahl) {
				enableAutoSelect(fields.anzahl);
				fields.anzahl.addEventListener("blur", function(){
					const raw = (this.value ?? "").toString().trim();
					if (raw !== "") {
						this.value = formatQty(raw);
					}
				});
			}
			if (fields.vkLabel) {
				fields.vkLabel.addEventListener("click", function(){
					suggestVkFromDefaults(block);
				});
			}
			if (fields.vk) {
				fields.vk.addEventListener("mousedown", function(){
					maybeSuggestVkOnEmpty(block);
				});
				fields.vk.addEventListener("focus", function(){
					maybeSuggestVkOnEmpty(block);
				});
			}
			if (fields.add) {
				fields.add.addEventListener("click", function(){
					insertVariantBlock(block);
				});
			}
			if (fields.del) {
				fields.del.addEventListener("click", function(){
					removeVariantBlock(block);
				});
			}
		}

		getVariantBlocks().forEach(bindVariantBlock);
		syncVariantActionState();
	});
	</script>';
}

	function cmx_artikel_waehrung_side_box_html(\WP_Post $post): void {
		cmx_artikel_render_save_nonce_once();
		echo '<input type="hidden" name="cmx_artikel_waehrung_payload" value="1">';
		$art = (string) cmx_meta_get($post->ID, CMX_ARTIKEL_META_ART, 'produkt');
		$waehrung = cmx_meta_get($post->ID, CMX_ARTIKEL_META_WAEHRUNGEN, 'CHF');
		$woo_id = (string) cmx_meta_get($post->ID, CMX_ARTIKEL_META_WOO_ID, '');
		$woo_id_trimmed = \trim($woo_id);
		$woo_product_id = \preg_match('/^\d+$/', $woo_id_trimmed) ? (int) $woo_id_trimmed : 0;
		$woo_product_link_template = cmx_artikel_woo_product_link_template();
		$woo_product_url = $woo_product_id > 0 ? cmx_artikel_woo_product_link_url((string) $woo_product_id) : '';

		echo '<p style="margin:0 0 8px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">';
		echo '<label style="display:inline-flex;align-items:center;gap:4px;margin:0;"><input type="radio" name="cmx_artikel_art" value="produkt" ' . \checked($art, 'produkt', false) . '> Produkt</label>';
		echo '<label style="display:inline-flex;align-items:center;gap:4px;margin:0;"><input type="radio" name="cmx_artikel_art" value="dienstleistung" ' . \checked($art, 'dienstleistung', false) . '> Dienstleistung</label></p>';
	echo '<p style="margin:0;"><select id="cmx_artikel_waehrung" name="cmx_artikel_waehrung" class="widefat" aria-label="Währung auswählen">';
		foreach (cmx_artikel_waehrung_optionen() as $val => $label) {
			echo '<option value="' . esc_attr($val) . '" ' . selected($waehrung, $val, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select></p>';
		echo '<p style="margin:8px 0 0;display:flex;align-items:center;gap:6px;">';
		echo '<a id="cmx_artikel_woo_id_link" href="' . \esc_url($woo_product_url !== '' ? $woo_product_url : '#') . '" target="_blank" rel="noopener noreferrer" aria-label="WooCommerce Produkt öffnen" title="' . \esc_attr($woo_product_id > 0 ? ('WooCommerce Produkt #' . $woo_product_id . ' öffnen') : 'WooCommerce Produkt öffnen') . '" style="' . ($woo_product_url !== '' ? 'display:inline-flex;' : 'display:none;') . 'align-items:center;justify-content:center;width:30px;height:30px;border:1px solid #ccd0d4;border-radius:4px;text-decoration:none;color:#2271b1;background:#fff;flex:0 0 30px;">';
		echo '<span class="dashicons dashicons-products" style="font-size:16px;line-height:16px;width:16px;height:16px;"></span>';
		echo '</a>';
		echo '<input type="text" id="cmx_artikel_woo_id" name="cmx_artikel_woo_id" class="widefat" aria-label="Woo-ID" placeholder="WooCommerce: Produkt-ID" value="' . \esc_attr($woo_id) . '" style="flex:1 1 auto;min-width:0;">';
		echo '</p>';
		echo '<script>(function(){';
		echo 'var input=document.getElementById("cmx_artikel_woo_id");';
		echo 'var link=document.getElementById("cmx_artikel_woo_id_link");';
		echo 'if(!input||!link) return;';
		echo 'var template=' . \wp_json_encode($woo_product_link_template) . ';';
		echo 'function sync(){';
		echo 'var value=(input.value||"").trim();';
		echo 'if(!/^\\d+$/.test(value)||!template){link.style.display="none";link.removeAttribute("href");link.removeAttribute("title");link.setAttribute("aria-label","WooCommerce Produkt öffnen");return;}';
		echo 'link.href=template.replace("__CMX_WOO_PRODUCT_ID__", encodeURIComponent(value));';
		echo 'link.title="WooCommerce Produkt #"+value+" öffnen";';
		echo 'link.setAttribute("aria-label","WooCommerce Produkt #"+value+" öffnen");';
		echo 'link.style.display="inline-flex";';
		echo '}';
		echo 'input.addEventListener("input", sync);';
		echo 'input.addEventListener("change", sync);';
		echo 'input.addEventListener("focus", function(){ this.select(); });';
		echo 'input.addEventListener("click", function(){ this.select(); });';
		echo 'sync();';
		echo '})();</script>';
	}

// Save-Handler:
// EK, Aufwand und VK werden manuell gespeichert.
// Selbstkosten, Deckungsbeitrag und Marge werden daraus serverseitig berechnet.
// ZUSÄTZLICH: Artikel-Nr. (SKU) und Währung speichern.
\add_action('save_post_artikel', function (int $post_id, \WP_Post $post) {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) return;
	if ($post->post_type !== 'artikel') return;
	if (!\current_user_can('edit_post', $post_id)) return;
	if (!isset($_POST['cmx_artikel_nonce']) || !\wp_verify_nonce($_POST['cmx_artikel_nonce'], 'cmx_artikel_save')) return;
	if (!isset($_POST['cmx_artikel_konditionen_payload']) && !isset($_POST['cmx_artikel_waehrung_payload'])) return;

	$in        = static fn($k, $d = '') => $_POST[$k] ?? $d;
	$norm      = static fn($v) => cmx_parse_number((string) $v);
	$is_finite = static fn($v) => \is_finite($v);
	$has       = static fn($k) => \array_key_exists($k, $_POST);

	// --- SKU & Währung speichern ---
	if ($has('cmx_artikel_art')) {
		$art = \sanitize_key((string) $in('cmx_artikel_art', 'produkt'));
		if (!\in_array($art, ['produkt', 'dienstleistung'], true)) {
			$art = 'produkt';
		}
		\update_post_meta($post_id, CMX_ARTIKEL_META_ART, $art);
	}
	if ($has('cmx_artikel_waehrung')) {
		$waehrung = \strtoupper(\sanitize_text_field($in('cmx_artikel_waehrung', 'CHF')));
		$allowed  = \array_keys(cmx_artikel_waehrung_optionen());
		if (!\in_array($waehrung, $allowed, true)) {
			$waehrung = 'CHF';
		}
		\update_post_meta($post_id, CMX_ARTIKEL_META_WAEHRUNGEN, $waehrung);
	}
	if ($has('cmx_artikel_woo_id')) {
		$woo_id = \sanitize_text_field((string) $in('cmx_artikel_woo_id', ''));
		if ($woo_id === '') {
			\delete_post_meta($post_id, CMX_ARTIKEL_META_WOO_ID);
		} else {
			\update_post_meta($post_id, CMX_ARTIKEL_META_WOO_ID, $woo_id);
		}
	}
	// --- Ende SKU & Währung ---

	$variant_taxonomies = cmx_artikel_variant_taxonomy_choices('Grössen');
	$farben_variant_taxonomies = cmx_artikel_variant_taxonomy_choices('Farben');
	if (isset($_POST['cmx_artikel_konditionen_payload'])) {
		$existing_variant_rows = cmx_artikel_variant_rows_load($post_id, $variant_taxonomies, $farben_variant_taxonomies);
		$variant_rows = [];
		$raw_variant_rows = $_POST['cmx_artikel_variants'] ?? null;
		$legacy_base = cmx_artikel_variant_row_legacy($post_id, $variant_taxonomies, $farben_variant_taxonomies);

		if (\is_array($raw_variant_rows)) {
			foreach ($raw_variant_rows as $index => $row) {
				if (!\is_array($row)) continue;
				$row = \wp_unslash($row);
				$base_row = isset($existing_variant_rows[(int) $index]) && \is_array($existing_variant_rows[(int) $index])
					? (array) $existing_variant_rows[(int) $index]
					: $legacy_base;
				$variant_rows[] = cmx_artikel_variant_row_normalize([
					'sku' => \sanitize_text_field((string) ($row['sku'] ?? '')),
					'anzahl' => (string) ($row['anzahl'] ?? ''),
					'left_taxonomy' => \sanitize_key((string) ($row['left_taxonomy'] ?? '')),
					'left_term_id' => (int) ($row['left_term_id'] ?? 0),
					'right_taxonomy' => \sanitize_key((string) ($row['right_taxonomy'] ?? '')),
					'right_term_id' => (int) ($row['right_term_id'] ?? 0),
					'einheit_term_id' => (int) ($row['einheit_term_id'] ?? 0),
					'ek' => (string) ($row['ek'] ?? ''),
					'aufwand' => (string) ($row['aufwand'] ?? ''),
					'vk' => (string) ($row['vk'] ?? ''),
					'belegtext' => \sanitize_textarea_field((string) ($row['belegtext'] ?? '')),
					'verkaufbar' => isset($row['verkaufbar']) ? 1 : 0,
					'katalog' => isset($row['katalog']) ? 1 : 0,
				], $variant_taxonomies, $farben_variant_taxonomies, $base_row);
			}
		}

		if ($variant_rows === [] && ($has('cmx_artikel_variant_taxonomy') || $has('cmx_artikel_variant_farben_taxonomy'))) {
			$legacy_row = [
				'left_taxonomy' => \sanitize_key((string) $in('cmx_artikel_variant_taxonomy', '')),
				'left_term_id' => (int) $in('cmx_artikel_variant_term', 0),
				'right_taxonomy' => \sanitize_key((string) $in('cmx_artikel_variant_farben_taxonomy', '')),
				'right_term_id' => (int) $in('cmx_artikel_variant_farben_term', 0),
			];
			$variant_rows[] = cmx_artikel_variant_row_normalize($legacy_row, $variant_taxonomies, $farben_variant_taxonomies, $legacy_base);
		}

		if ($variant_rows === []) {
			$variant_rows[] = $legacy_base;
		}

		cmx_artikel_variant_rows_persist($post_id, $variant_rows, $variant_taxonomies, $farben_variant_taxonomies);
	}
}, 20, 2);
