<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_ZUGANGSDATEN_CPT')) {
	\define(__NAMESPACE__ . '\\CMX_ZUGANGSDATEN_CPT', 'zugangsdaten');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ZUGANGSDATEN_CATEGORY_TAX')) {
	\define(__NAMESPACE__ . '\\CMX_ZUGANGSDATEN_CATEGORY_TAX', 'zugangsdaten_kategorie');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ZUGANGSDATEN_ISSUER_TAX')) {
	\define(__NAMESPACE__ . '\\CMX_ZUGANGSDATEN_ISSUER_TAX', 'zugangsdaten_herausgeber');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ZUGANGSDATEN_GROUP_TAX')) {
	\define(__NAMESPACE__ . '\\CMX_ZUGANGSDATEN_GROUP_TAX', 'zugangsdaten_gruppe');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ZUGANGSDATEN_PROVIDER_TAX')) {
	\define(__NAMESPACE__ . '\\CMX_ZUGANGSDATEN_PROVIDER_TAX', 'zugangsdaten_provider');
}

function cmx_zugangsdaten_categories(): array {
	return [
		'api-keys'     => 'API-Keys',
		'bash'         => 'Bash',
		'ftp'          => 'Datentransfer',
		'email'        => 'E-Mail',
		'kreditkarten' => 'Kreditkarten',
		'lizenzen'     => 'Lizenzen',
		'notizen'      => 'Notizen',
		'passwoerter'  => 'Passwörter',
		'server'       => 'Server',
		'ssh-keys'     => 'SSH Keys',
		'wlan'         => 'WLAN',
	];
}

function cmx_zugangsdaten_provider_categories(): array {
	return ['ssh-keys', 'server', 'email', 'api-keys'];
}

function cmx_zugangsdaten_ini_terms(string $key): array {
	if (!\function_exists(__NAMESPACE__ . '\\cmx_ini_get_value')) {
		return [];
	}

	$values = cmx_ini_get_value('Zugang', $key);
	$values = \is_array($values) ? $values : [$values];
	return \array_values(\array_unique(\array_filter(\array_map(static function ($value): string {
		return \sanitize_text_field((string) $value);
	}, $values))));
}

\add_action('init', function (): void {
	\register_taxonomy(CMX_ZUGANGSDATEN_CATEGORY_TAX, [CMX_ZUGANGSDATEN_CPT], [
		'labels' => [
			'name'          => 'Kategorien',
			'singular_name' => 'Kategorie',
			'menu_name'     => 'Kategorien',
			'all_items'     => 'Alle Kategorien',
			'edit_item'     => 'Kategorie bearbeiten',
			'add_new_item'  => 'Kategorie hinzufügen',
		],
		'public'            => false,
		'publicly_queryable'=> false,
		'show_ui'           => false,
		'show_admin_column' => false,
		'show_in_rest'      => false,
		'hierarchical'      => true,
		'rewrite'           => false,
		'query_var'         => false,
	]);

	\register_taxonomy(CMX_ZUGANGSDATEN_ISSUER_TAX, [CMX_ZUGANGSDATEN_CPT], [
		'labels' => [
			'name'          => 'Herausgeber',
			'singular_name' => 'Herausgeber',
			'menu_name'     => 'Herausgeber',
			'all_items'     => 'Alle Herausgeber',
			'edit_item'     => 'Herausgeber bearbeiten',
			'add_new_item'  => 'Herausgeber hinzufügen',
		],
		'public'            => false,
		'publicly_queryable'=> false,
		'show_ui'           => true,
		'show_admin_column' => false,
		'show_in_rest'      => false,
		'hierarchical'      => false,
		'rewrite'           => false,
		'query_var'         => false,
	]);

	\register_taxonomy(CMX_ZUGANGSDATEN_GROUP_TAX, [CMX_ZUGANGSDATEN_CPT], [
		'labels' => [
			'name'          => 'Gruppen',
			'singular_name' => 'Gruppe',
			'menu_name'     => 'Gruppen',
			'all_items'     => 'Alle Gruppen',
			'edit_item'     => 'Gruppe bearbeiten',
			'add_new_item'  => 'Gruppe hinzufügen',
		],
		'public'            => false,
		'publicly_queryable'=> false,
		'show_ui'           => true,
		'show_admin_column' => false,
		'show_in_rest'      => false,
		'hierarchical'      => true,
		'rewrite'           => false,
		'query_var'         => false,
	]);

	\register_taxonomy(CMX_ZUGANGSDATEN_PROVIDER_TAX, [CMX_ZUGANGSDATEN_CPT], [
		'labels' => [
			'name'          => 'Provider',
			'singular_name' => 'Provider',
			'menu_name'     => 'Provider',
			'all_items'     => 'Alle Provider',
			'edit_item'     => 'Provider bearbeiten',
			'add_new_item'  => 'Provider hinzufügen',
		],
		'public'            => false,
		'publicly_queryable'=> false,
		'show_ui'           => true,
		'show_admin_column' => false,
		'show_in_rest'      => false,
		'hierarchical'      => false,
		'rewrite'           => false,
		'query_var'         => false,
	]);
}, 15);

\add_action('admin_init', function (): void {
	if (!\taxonomy_exists(CMX_ZUGANGSDATEN_CATEGORY_TAX)) {
		return;
	}

	foreach (cmx_zugangsdaten_categories() as $slug => $label) {
		if (\term_exists($slug, CMX_ZUGANGSDATEN_CATEGORY_TAX)) {
			continue;
		}
		\wp_insert_term($label, CMX_ZUGANGSDATEN_CATEGORY_TAX, ['slug' => $slug]);
	}

	$seed_option = 'cmx_zugangsdaten_ini_terms_seeded_v1';
	if (!\get_option($seed_option, false)) {
		foreach ([
			CMX_ZUGANGSDATEN_ISSUER_TAX => 'Herausgeber',
			CMX_ZUGANGSDATEN_GROUP_TAX  => 'Gruppen',
		] as $taxonomy => $ini_key) {
			if (!\taxonomy_exists($taxonomy)) {
				continue;
			}
			foreach (cmx_zugangsdaten_ini_terms($ini_key) as $label) {
				if (!\term_exists($label, $taxonomy)) {
					\wp_insert_term($label, $taxonomy);
				}
			}
		}
		\update_option($seed_option, 1, false);
	}
});

function cmx_zugangsdaten_category_slug(int $post_id): string {
	if ($post_id <= 0 || !\taxonomy_exists(CMX_ZUGANGSDATEN_CATEGORY_TAX)) {
		return '';
	}
	$terms = \wp_get_object_terms($post_id, CMX_ZUGANGSDATEN_CATEGORY_TAX, ['fields' => 'slugs']);
	if (\is_wp_error($terms) || empty($terms[0])) {
		return '';
	}
	return \sanitize_key((string) $terms[0]);
}

function cmx_zugangsdaten_category_label(int $post_id): string {
	$slug = cmx_zugangsdaten_category_slug($post_id);
	$categories = cmx_zugangsdaten_categories();
	return (string) ($categories[$slug] ?? '');
}
