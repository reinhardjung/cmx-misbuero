<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


// cmx_create_taxo('artikel', 'Marke', 'Marken');
function cmx_create_taxo(string $cpt_name, string $taxo_single, string $taxo_plural, $metabox = null, $hierarchisch = true, array $overrides = []): void {
	$tax = cmx_tax_key($cpt_name, cmx_no_umlaute($taxo_plural));
	if (\taxonomy_exists($tax)) return;

	// $is_hierarchical	= is_bool($hierarchisch) ? $hierarchisch : (strtolower((string)$hierarchisch) !== 'nein'); // Standardmäßig hierarchisch (true)

	$labels = ['name' => cmx_no_umlaute($taxo_plural),'singular_name' => $taxo_single,'search_items' => 'Suchen','all_items' => 'Alle','edit_item' => 'Bearbeiten','view_item' => 'Anzeigen','update_item' => 'Aktualisieren','add_new_item' => 'Hinzufügen','new_item_name' => 'Neu','parent_item' => 'Übergeordnet','parent_item_colon' => 'Übergeordnet:','not_found' => 'Keine ' .cmx_no_umlaute($taxo_plural). ' gefunden','no_terms' => 'Keine ' .cmx_no_umlaute($taxo_plural),'menu_name' => $taxo_plural];
	$args_default = ['meta_box_cb' => null, 'labels'  => $labels,'label' => $taxo_single,'public' => false,'meta_box_cb' => $metabox, 'show_ui' => true,'show_admin_column' => false,'hierarchical' => $hierarchisch,'show_in_rest' => true,'rewrite' => false,'query_var' => false,];
	// \remove_meta_box('tagsdiv-cmx_komm_phone', 'kontakte', 'side');

	$args = array_replace_recursive($args_default, $overrides);

	\register_taxonomy($tax, [$cpt_name], $args);
}


// cmx_const_taxos('ARTIKEL','artikel', 'lala,lulu,bubu');
function cmx_const_taxos(string $prefix_const, string $prefix_value, string $tax_enties): void {
	$tax_suffixes = array_filter(array_map('trim', explode(',', $tax_enties)));
	foreach ($tax_suffixes as $suffix) {
		$suffix = trim(strtolower($suffix));
		if ($suffix === '') continue;

		$const_name  = 'TAX_' .$prefix_const .'_' . strtoupper($suffix);
		$const_value = $prefix_value .'_' . $suffix;

		if (!defined(__NAMESPACE__ . '\\' . $const_name)) {
			define(__NAMESPACE__ . '\\' . $const_name, $const_value);
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_seed_taxo_ini_key_candidates')) {
	function cmx_seed_taxo_ini_key_candidates(string $label): array {
		$key = cmx_sani_key($label, 'lower');
		if ($key === '') {
			return [];
		}

		$candidates = [$key];

		if (\str_ends_with($key, 'ie')) {
			$candidates[] = $key . 'n';
		} elseif (!\str_ends_with($key, 'en')) {
			$candidates[] = $key . 'en';
		}
		if (\str_ends_with($key, 'e') && !\str_ends_with($key, 'en')) {
			$candidates[] = $key . 'n';
		}
		if (\str_ends_with($key, 'ien') && \strlen($key) > 1) {
			$candidates[] = \substr($key, 0, -1);
		} elseif (\str_ends_with($key, 'en') && \strlen($key) > 2) {
			$candidates[] = \substr($key, 0, -2);
		}
		if (\str_ends_with($key, 'n') && !\str_ends_with($key, 'en') && \strlen($key) > 1) {
			$candidates[] = \substr($key, 0, -1);
		}

		return \array_values(\array_unique(\array_filter($candidates, static fn($candidate) => $candidate !== '')));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_seed_taxo_ini_terms')) {
	function cmx_seed_taxo_ini_terms(string $section, string $label): array {
		if (!\function_exists(__NAMESPACE__ . '\\cmx_ini_get_value')) {
			return [];
		}

		foreach (cmx_seed_taxo_ini_key_candidates($label) as $candidate) {
			$terms = (array) cmx_ini_get_value($section, $candidate);
			$terms = \array_values(\array_filter(\array_map(static fn($value) => \trim((string) $value), $terms), static fn($value) => $value !== ''));
			if (!empty($terms)) {
				return $terms;
			}
		}

		return [];
	}
}


// cmx_seed_taxo('Artikel','Marken,Farben,Einheiten,Typen,Kategorien');
function cmx_seed_taxo(string $base = 'NameDesCPTs', string $myTaxos = ''): void {
	$labels = array_filter(array_map('trim', explode(',', $myTaxos)));
	if (empty($labels)) return;

	$constBase = cmx_sani_key($base,'upper');
	$slugBase  = cmx_sani_key($base,'lower');
	$iniAll = [];
	$iniFile = __DIR__ . '/globales.ini';
	if (\is_file($iniFile)) {
		$parsed = \parse_ini_file($iniFile, true, INI_SCANNER_TYPED);
		if (\is_array($parsed)) $iniAll = $parsed;
	}

	foreach ($labels as $label) {
		$upper    = cmx_sani_key($label,'upper');
		$constFqn = __NAMESPACE__ . '\\TAX_' . $constBase . '_' . $upper; // \NS\TAX_ARTIKEL_MARKEN

		$taxonomy = defined($constFqn) ? constant($constFqn) : $slugBase . '_' . cmx_sani_key($label, 'lower');
		if (!taxonomy_exists($taxonomy)) continue;

		$have = get_terms(['taxonomy'=>$taxonomy,'hide_empty'=>false,'fields'=>'ids','number'=>1]);
		if (is_wp_error($have)) continue;

		$terms = cmx_seed_taxo_ini_terms($slugBase, $label);

		// Erweiterung: Section [<TaxonomieLabel>] als Name=>Beschreibung lesen
		// Beispiel:
		// [BelegeTextbausteine]
		// Barbetrieb="Frontdesk - Barbereich"
		$sectionEntries = [];
		if (!empty($iniAll)) {
			foreach ($iniAll as $sectionName => $sectionData) {
				if (!\is_array($sectionData)) continue;
				if (\strcasecmp((string) $sectionName, (string) $label) !== 0) continue;
				foreach ($sectionData as $name => $descRaw) {
					$name = \trim((string) $name);
					if ($name === '') continue;
					if (\is_array($descRaw)) {
						$desc = \implode(', ', \array_values(\array_filter(\array_map(static fn($v) => \trim((string) $v), $descRaw), static fn($v) => $v !== '')));
					} else {
						$desc = \trim((string) $descRaw);
					}
					$sectionEntries[$name] = $desc;
				}
				break;
			}
		}

		// Falls die Taxonomie schon Werte hat, nur dann weitermachen, wenn Section-Mapping vorhanden ist.
		// So bleibt das bisherige Verhalten für Standard-Listen unverändert.
		if (!empty($have) && empty($sectionEntries)) continue;

		// Falls sowohl Listenwerte als auch Section-Mapping existieren:
		// Listenwerte als Namen ohne Beschreibung ergänzen.
		if (!empty($sectionEntries) && !empty($terms)) {
			foreach ($terms as $name) {
				if ($name !== '' && !isset($sectionEntries[$name])) $sectionEntries[$name] = '';
			}
		}

		// Name=>Beschreibung Seeding (upsert)
		if (!empty($sectionEntries)) {
			wp_defer_term_counting(true);
			foreach ($sectionEntries as $name => $description) {
				$name = \trim((string) $name);
				if ($name === '') continue;

				$exists = \term_exists($name, $taxonomy);
				if (!$exists) {
					$args = [];
					$description = \trim((string) $description);
					if ($description !== '') $args['description'] = $description;
					wp_insert_term($name, $taxonomy, $args);
					continue;
				}

				$term_id = 0;
				if (\is_array($exists)) {
					$term_id = (int) ($exists['term_id'] ?? 0);
				} elseif (\is_numeric($exists)) {
					$term_id = (int) $exists;
				}

				$description = \trim((string) $description);
				if ($term_id > 0 && $description !== '') {
					$term = \get_term($term_id, $taxonomy);
					if ($term && !\is_wp_error($term) && (string) $term->description !== $description) {
						\wp_update_term($term_id, $taxonomy, ['description' => $description]);
					}
				}
			}
			wp_defer_term_counting(false);
			continue;
		}

		if (!empty($have)) continue;
		if (empty($terms)) continue;

		wp_defer_term_counting(true);
		foreach ($terms as $name) {
			if ($name !== '' && !term_exists($name, $taxonomy)) {
				wp_insert_term($name, $taxonomy);
			}
		}
		wp_defer_term_counting(false);
	}
}


// add_action('admin_notices', function () {
//     if (!is_admin()) return;

//     $test = wp_remote_get('https://ipapi.co/31.165.222.102/json/');

//     echo '<div class="notice notice-info"><p><strong>GEO-API TEST:</strong><br>';

//     if (is_wp_error($test)) {
//         echo 'WP ERROR: ' . $test->get_error_message();
//     } else {
//         echo 'RESPONSE: ' . wp_remote_retrieve_body($test);
//     }

//     echo '</p></div>';
// });


	// $rows = $wpdb->get_results("
	// 	SELECT option_name, option_value
	// 	FROM {$wpdb->options}
	// 	WHERE option_name LIKE 'beleg_%'
