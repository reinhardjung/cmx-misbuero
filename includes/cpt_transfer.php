<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_supported_post_types')) {
	function cmx_cpt_transfer_supported_post_types(): array {
		$types = \get_post_types(['show_ui' => true], 'names');
		unset($types['attachment']);
		return \array_values(\array_filter(\array_map('sanitize_key', (array) $types)));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_can')) {
	function cmx_cpt_transfer_can(): bool {
		return \current_user_can('edit_posts');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_stamp')) {
	function cmx_cpt_transfer_stamp(): string {
		return \function_exists('\\wp_date') ? (string) \wp_date('Ymd-His') : (string) \date('Ymd-His');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_filename')) {
	function cmx_cpt_transfer_filename(string $post_type): string {
		$site = \sanitize_file_name((string) \parse_url(\home_url(), PHP_URL_HOST));
		if ($site === '') {
			$site = 'misbuero';
		}
		return $site . '-' . \sanitize_file_name($post_type) . '-transfer-' . cmx_cpt_transfer_stamp() . '.zip';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_backup_filename')) {
	function cmx_cpt_transfer_backup_filename(): string {
		$site = \sanitize_file_name((string) \parse_url(\home_url(), PHP_URL_HOST));
		if ($site === '') {
			$site = 'misbuero';
		}
		return $site . '-cpt-backup-' . cmx_cpt_transfer_stamp() . '.zip';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_post_type_label')) {
	function cmx_cpt_transfer_post_type_label(string $post_type): string {
		$obj = \get_post_type_object($post_type);
		if ($obj && !empty($obj->labels->name)) {
			return (string) $obj->labels->name;
		}
		return $post_type;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_current_post_type')) {
	function cmx_cpt_transfer_current_post_type(): string {
		$post_type = isset($_REQUEST['post_type']) ? \sanitize_key((string) \wp_unslash($_REQUEST['post_type'])) : '';
		if ($post_type === '' && \function_exists('get_current_screen')) {
			$screen = \get_current_screen();
			if ($screen && !empty($screen->post_type)) {
				$post_type = \sanitize_key((string) $screen->post_type);
			}
		}
		if ($post_type === '') {
			$post_type = 'post';
		}
		return \in_array($post_type, cmx_cpt_transfer_supported_post_types(), true) ? $post_type : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_requested_post_types')) {
	function cmx_cpt_transfer_requested_post_types(): array {
		$supported = cmx_cpt_transfer_supported_post_types();
		$raw = isset($_REQUEST['post_types']) ? (array) \wp_unslash($_REQUEST['post_types']) : [];
		$selected = [];
		foreach ($raw as $post_type) {
			$post_type = \sanitize_key((string) $post_type);
			if ($post_type !== '' && \in_array($post_type, $supported, true)) {
				$selected[] = $post_type;
			}
		}
		return \array_values(\array_unique($selected));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_selected_ids')) {
	function cmx_cpt_transfer_selected_ids(): array {
		return isset($_REQUEST['post'])
			? \array_values(\array_unique(\array_filter(\array_map('intval', (array) $_REQUEST['post']))))
			: [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_collect_ids')) {
	function cmx_cpt_transfer_collect_ids(string $post_type): array {
		$selected = cmx_cpt_transfer_selected_ids();
		$request = (array) $_REQUEST;
		$args = [
			'post_type' => $post_type,
			'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'orderby' => 'ID',
			'order' => 'ASC',
			'suppress_filters' => false,
		];
		if ($selected !== []) {
			$args['post__in'] = $selected;
			$args['orderby'] = 'post__in';
			$args['post_status'] = 'any';
		} else {
			$post_status = isset($request['post_status']) ? \sanitize_key((string) \wp_unslash($request['post_status'])) : '';
			if ($post_status !== '' && $post_status !== 'all') {
				$args['post_status'] = $post_status;
			}

			foreach (['s', 'author', 'author_name', 'm', 'year', 'monthnum', 'day'] as $key) {
				if (!isset($request[$key]) || $request[$key] === '' || $request[$key] === '0' || $request[$key] === '-1') {
					continue;
				}
				$args[$key] = \is_array($request[$key])
					? \array_map('sanitize_text_field', \wp_unslash($request[$key]))
					: \sanitize_text_field((string) \wp_unslash($request[$key]));
			}

			$tax_query = [];
			foreach (\get_object_taxonomies($post_type, 'objects') as $taxonomy) {
				$candidates = \array_values(\array_unique(\array_filter([
					(string) ($taxonomy->query_var ?? ''),
					(string) ($taxonomy->name ?? ''),
					'filter_' . (string) ($taxonomy->name ?? ''),
					(!empty($taxonomy->query_var) ? ('filter_' . (string) $taxonomy->query_var) : ''),
				])));
				foreach ($candidates as $candidate) {
					if (!isset($request[$candidate]) || $request[$candidate] === '' || $request[$candidate] === '0' || $request[$candidate] === '-1') {
						continue;
					}
					$value = \wp_unslash($request[$candidate]);
					$terms = \is_array($value) ? \array_values(\array_filter($value)) : [$value];
					if ($terms === []) {
						continue;
					}
					$first = (string) \reset($terms);
					$tax_query[] = [
						'taxonomy' => (string) $taxonomy->name,
						'field' => \is_numeric($first) ? 'term_id' : 'slug',
						'terms' => \array_map(static fn($term): string => \sanitize_text_field((string) $term), $terms),
					];
					break;
				}
			}
			if ($tax_query !== []) {
				$args['tax_query'] = \array_merge(['relation' => 'AND'], $tax_query);
			}
		}

		$backup_get = $_GET ?? [];
		foreach ($request as $key => $value) {
			if (!\in_array((string) $key, ['action', '_wpnonce', '_wp_http_referer'], true)) {
				$_GET[$key] = $value;
			}
		}

		try {
			$query = new \WP_Query($args);
			return \array_values(\array_filter(\array_map('intval', (array) $query->posts)));
		} finally {
			$_GET = $backup_get;
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_upload_rel_from_abs')) {
	function cmx_cpt_transfer_upload_rel_from_abs(string $abs): string {
		$uploads = \wp_get_upload_dir();
		$root = \trailingslashit(\wp_normalize_path((string) ($uploads['basedir'] ?? '')));
		$abs = \wp_normalize_path($abs);
		if ($root === '' || $abs === '' || !\str_starts_with($abs, $root)) {
			return '';
		}
		return \ltrim(\substr($abs, \strlen($root)), '/');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_upload_abs_from_rel')) {
	function cmx_cpt_transfer_upload_abs_from_rel(string $rel): string {
		$rel = \ltrim(\str_replace('\\', '/', $rel), '/');
		if ($rel === '' || \str_contains($rel, '..')) {
			return '';
		}
		$uploads = \wp_get_upload_dir();
		$root = \trailingslashit(\wp_normalize_path((string) ($uploads['basedir'] ?? '')));
		$abs = \wp_normalize_path($root . $rel);
		return \str_starts_with($abs, $root) ? $abs : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_collect_file_rels_from_value')) {
	function cmx_cpt_transfer_collect_file_rels_from_value($value, array &$rels): void {
		if (\is_array($value)) {
			foreach ($value as $child) {
				cmx_cpt_transfer_collect_file_rels_from_value($child, $rels);
			}
			return;
		}

		$text = \trim((string) $value);
		if ($text === '') {
			return;
		}

		$uploads = \wp_get_upload_dir();
		$baseurl = \trailingslashit((string) ($uploads['baseurl'] ?? ''));
		if ($baseurl !== '') {
			$quoted = \preg_quote($baseurl, '~');
			if (\preg_match_all('~' . $quoted . '([^\\s"\'<>]+)~', $text, $matches)) {
				foreach ((array) ($matches[1] ?? []) as $candidate) {
					cmx_cpt_transfer_collect_file_rels_from_value((string) $candidate, $rels);
				}
			}
		}

		if ($baseurl !== '' && \str_starts_with($text, $baseurl)) {
			$text = \substr($text, \strlen($baseurl));
		}
		$text = \ltrim(\str_replace('\\', '/', $text), '/');
		if ($text === '' || \str_contains($text, '..')) {
			return;
		}

		$abs = cmx_cpt_transfer_upload_abs_from_rel($text);
		if ($abs !== '' && \is_file($abs)) {
			$rels[$text] = true;
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_meta_keys_for_post')) {
	function cmx_cpt_transfer_meta_keys_for_post(int $post_id): array {
		$raw = \get_post_meta($post_id);
		$keys = [];
		foreach (\array_keys((array) $raw) as $key) {
			$key = (string) $key;
			if ($key === '' || \str_contains($key, '__') || \in_array($key, ['_edit_lock', '_edit_last'], true)) {
				continue;
			}
			$keys[$key] = true;
		}
		\natcasesort($keys);
		return \array_keys($keys);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_value_export')) {
	function cmx_cpt_transfer_value_export($value): string {
		if (\is_bool($value)) {
			return $value ? '1' : '0';
		}
		if ($value === null) {
			return '';
		}
		return (string) $value;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_meta_rows')) {
	function cmx_cpt_transfer_meta_rows(string $original_id, string $meta_key, $value, array $path = []): array {
		if (\is_object($value)) {
			$value = (array) $value;
		}
		if (\is_array($value)) {
			$rows = [];
			foreach ($value as $child_key => $child_value) {
				$rows = \array_merge($rows, cmx_cpt_transfer_meta_rows($original_id, $meta_key, $child_value, \array_merge($path, [(string) $child_key])));
			}
			return $rows;
		}

		return [[
			'original_id' => $original_id,
			'meta_key' => $meta_key,
			'meta_path' => \implode('/', \array_map(static fn($part): string => \rawurlencode((string) $part), $path)),
			'meta_value' => cmx_cpt_transfer_value_export($value),
		]];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_assign_meta_path')) {
	function cmx_cpt_transfer_assign_meta_path(array &$target, string $path, string $value): void {
		$path = \trim($path);
		if ($path === '') {
			$target = $value;
			return;
		}

		$parts = \array_map('rawurldecode', \explode('/', $path));
		$cursor =& $target;
		$last = \count($parts) - 1;
		foreach ($parts as $index => $part) {
			$key = \ctype_digit((string) $part) ? (int) $part : (string) $part;
			if ($index === $last) {
				$cursor[$key] = $value;
				return;
			}
			if (!isset($cursor[$key]) || !\is_array($cursor[$key])) {
				$cursor[$key] = [];
			}
			$cursor =& $cursor[$key];
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_relation_targets')) {
	function cmx_cpt_transfer_relation_targets(string $meta_key, string $meta_path = ''): array {
		$direct = [
			'_cmx_beleg_kontakt_id' => ['kontakte'],
			'cmx_beleg_kontakt_id' => ['kontakte'],
			'_cmx_beleg_absender_kontakt_id' => ['kontakte'],
			'_cmx_beleg_empfaenger_kontakt_id' => ['kontakte'],
			'_cmx_belegeingang_sender_kontakt_id' => ['kontakte'],
			'_cmx_belegeingang_recipient_kontakt_id' => ['kontakte'],
			'_cmx_beleg_projekt_id' => ['projekte'],
			'_cmx_projekt_id' => ['projekte'],
			'_projekt_id' => ['projekte'],
			'_cmx_projekt_kontakt_id' => ['kontakte'],
			'cmx_projekt_kontakt_id' => ['kontakte'],
			'_cmx_budget_kontakt_id' => ['kontakte'],
			'_cmx_carent_kontakt_id' => ['kontakte'],
			'_cmx_carent_fahrzeug_id' => ['artikel'],
			'_cmx_carent_buchung_id' => ['buchungen'],
			'_cmx_carent_vermietung_id' => ['carent'],
			'_cmx_carent_vertrag_pdf_dokument_id' => ['dokumente'],
			'_cmx_buchung_kontakt_id' => ['kontakte'],
			'_cmx_buchung_artikel_id' => ['artikel'],
			'_cmx_buchung_beleg_id' => ['belege'],
			'_cmx_buchung_id' => ['buchungen'],
			'_cmx_buchung_fuehrerausweis_document_id' => ['dokumente'],
			'_cmx_scanner_rel_artikel_id' => ['artikel'],
			'_cmx_scanner_rel_dokumente_id' => ['dokumente'],
			'_cmx_scanner_rel_kontakte_id' => ['kontakte'],
			'_cmx_scanner_rel_projekte_id' => ['projekte'],
			'_cmx_scanner_rel_belege_id' => ['belege'],
			'_cmx_art_lieferant_id' => ['kontakte'],
			'_cmx_artikel_lieferant_id' => ['kontakte'],
			'_cmx_kontakte_zu_kontakt_id' => ['kontakte'],
			'cmx_dokumente_artikel' => ['artikel'],
			'cmx_dokumente_kunden' => ['kontakte'],
			'cmx_dokumente_projekte' => ['projekte'],
			'cmx_dokumente_belege' => ['belege'],
		];
		if (isset($direct[$meta_key])) {
			return $direct[$meta_key];
		}
		if (\preg_match('/^_cmx_art_lieferant_\\d+_id$/', $meta_key)) {
			return ['kontakte'];
		}

		$parts = $meta_path !== '' ? \array_map('rawurldecode', \explode('/', $meta_path)) : [];
		$leaf = $parts !== [] ? (string) \end($parts) : '';
		if ($meta_key === '_cmx_projekt_tasks' && \in_array($leaf, ['artikel_id', 'produkt_id'], true)) {
			return ['artikel'];
		}
		if ($meta_key === '_cmx_beleg_positionen' && \in_array($leaf, ['artikel_id', 'produkt_id'], true)) {
			return ['artikel'];
		}
		if ($meta_key === '_cmx_art_lieferanten_liste' && \in_array($leaf, ['lieferant_id', 'id'], true)) {
			return ['kontakte'];
		}
		if ($meta_key === '_cmx_kontakte_zu_kontakt_rows' && \in_array($leaf, ['id', 'kontakt_id'], true)) {
			return ['kontakte'];
		}
		return [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_term_relation_taxonomy')) {
	function cmx_cpt_transfer_term_relation_taxonomy(string $meta_key, string $meta_path = ''): string {
		$direct = [
			'_cmx_buchung_mitarbeiter_term_id' => 'buchungen_mitarbeiter',
			'_cmx_buchung_ressource_term_id' => 'buchungen_ressource',
			'_cmx_beleg_mwst_term' => 'belege_mwst',
		];
		if (isset($direct[$meta_key])) {
			return $direct[$meta_key];
		}
		$parts = $meta_path !== '' ? \array_map('rawurldecode', \explode('/', $meta_path)) : [];
		$leaf = $parts !== [] ? (string) \end($parts) : '';
		if ($leaf !== 'term_id') {
			return '';
		}
		if (\in_array($meta_key, ['_cmx_carent_fotos_rows', '_cmx_carent_uebernahme_fotos_rows', '_cmx_carent_rueckgabe_fotos_rows'], true)) {
			return \function_exists(__NAMESPACE__ . '\\cmx_carent_fotos_taxonomy') ? (string) cmx_carent_fotos_taxonomy() : '';
		}
		if ($meta_key === '_cmx_carent_schadenprotokoll_rows') {
			return \function_exists(__NAMESPACE__ . '\\cmx_carent_schaden_taxonomy') ? (string) cmx_carent_schaden_taxonomy() : '';
		}
		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_first_meta')) {
	function cmx_cpt_transfer_first_meta(int $post_id, array $keys): string {
		foreach ($keys as $key) {
			$value = \trim((string) \get_post_meta($post_id, (string) $key, true));
			if ($value !== '') {
				return $value;
			}
		}
		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_first_email')) {
	function cmx_cpt_transfer_first_email(int $post_id): string {
		$meta = \get_post_meta($post_id);
		foreach ((array) $meta as $key => $values) {
			if (!\str_contains((string) $key, 'email') && !\str_contains((string) $key, 'mail')) {
				continue;
			}
			foreach ((array) $values as $value) {
				$text = \is_scalar($value) ? (string) $value : (string) \wp_json_encode($value);
				if (\preg_match('/[A-Z0-9._%+\\-]+@[A-Z0-9.\\-]+\\.[A-Z]{2,}/i', $text, $matches)) {
					return \sanitize_email((string) $matches[0]);
				}
			}
		}
		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_term_reference_row')) {
	function cmx_cpt_transfer_term_reference_row(string $original_id, string $meta_key, string $meta_path, string $value): array {
		$term_id = (int) $value;
		if ($term_id <= 0) {
			return [];
		}
		$taxonomy = cmx_cpt_transfer_term_relation_taxonomy($meta_key, $meta_path);
		if ($taxonomy === '' || !\taxonomy_exists($taxonomy)) {
			return [];
		}
		$term = \get_term($term_id, $taxonomy);
		if (!$term instanceof \WP_Term) {
			return [];
		}
		return [
			'original_id' => $original_id,
			'meta_key' => $meta_key,
			'meta_path' => $meta_path,
			'target_type' => 'term:' . $taxonomy,
			'old_target_id' => (string) $term_id,
			'target_slug' => (string) $term->slug,
			'target_title' => (string) $term->name,
			'target_code' => '',
			'target_email' => '',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_reference_row')) {
	function cmx_cpt_transfer_reference_row(string $original_id, string $meta_key, string $meta_path, string $value): array {
		$old_target_id = (int) $value;
		if ($old_target_id <= 0) {
			return [];
		}
		$post = \get_post($old_target_id);
		if (!$post instanceof \WP_Post) {
			return [];
		}
		$targets = cmx_cpt_transfer_relation_targets($meta_key, $meta_path);
		if ($targets === [] || !\in_array((string) $post->post_type, $targets, true)) {
			return [];
		}
		$target_type = (string) $post->post_type;
		$code = '';
		if ($target_type === 'kontakte') {
			$code = cmx_cpt_transfer_first_meta($old_target_id, ['_cmx_kontakte_kunden_nr', '_cmx_kontakte_muh', '_cmx_kontakte_hr_uid']);
		} elseif ($target_type === 'artikel') {
			$code = cmx_cpt_transfer_first_meta($old_target_id, ['_cmx_artikel_sku', 'cmx_artikel_sku', '_cmx_artikel_nr', '_sku']);
		}
		return [
			'original_id' => $original_id,
			'meta_key' => $meta_key,
			'meta_path' => $meta_path,
			'target_type' => $target_type,
			'old_target_id' => (string) $old_target_id,
			'target_slug' => (string) $post->post_name,
			'target_title' => (string) $post->post_title,
			'target_code' => $code,
			'target_email' => $target_type === 'kontakte' ? cmx_cpt_transfer_first_email($old_target_id) : '',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_find_post_by_meta')) {
	function cmx_cpt_transfer_find_post_by_meta(string $post_type, array $keys, string $value): int {
		$value = \trim($value);
		if ($post_type === '' || $value === '') {
			return 0;
		}
		foreach ($keys as $key) {
			$ids = \get_posts([
				'post_type' => $post_type,
				'post_status' => 'any',
				'posts_per_page' => 2,
				'fields' => 'ids',
				'no_found_rows' => true,
				'suppress_filters' => true,
				'meta_query' => [[
					'key' => (string) $key,
					'value' => $value,
					'compare' => '=',
				]],
			]);
			if (\count($ids) === 1) {
				return (int) $ids[0];
			}
		}
		return 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_find_post_by_title')) {
	function cmx_cpt_transfer_find_post_by_title(string $post_type, string $title): int {
		$title = \trim($title);
		if ($post_type === '' || $title === '') {
			return 0;
		}
		$query = new \WP_Query([
			'post_type' => $post_type,
			'post_status' => 'any',
			'title' => $title,
			'posts_per_page' => 2,
			'fields' => 'ids',
			'no_found_rows' => true,
			'suppress_filters' => true,
		]);
		$ids = \array_values(\array_map('intval', (array) $query->posts));
		return \count($ids) === 1 ? (int) $ids[0] : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_resolve_reference')) {
	function cmx_cpt_transfer_resolve_reference(array $ref, array $id_map): int {
		$old_id = (string) ($ref['old_target_id'] ?? '');
		if ($old_id !== '' && isset($id_map[$old_id])) {
			return (int) $id_map[$old_id];
		}

		$raw_type = (string) ($ref['target_type'] ?? '');
		if (\str_starts_with($raw_type, 'term:')) {
			$taxonomy = \sanitize_key(\substr($raw_type, 5));
			if ($taxonomy === '' || !\taxonomy_exists($taxonomy)) {
				return 0;
			}
			$slug = \sanitize_title((string) ($ref['target_slug'] ?? ''));
			$term = $slug !== '' ? \get_term_by('slug', $slug, $taxonomy) : false;
			if (!$term) {
				$name = \sanitize_text_field((string) ($ref['target_title'] ?? ''));
				$term = $name !== '' ? \get_term_by('name', $name, $taxonomy) : false;
				if (!$term && $name !== '') {
					$created = \wp_insert_term($name, $taxonomy, $slug !== '' ? ['slug' => $slug] : []);
					if (!\is_wp_error($created) && \is_array($created)) {
						return (int) ($created['term_id'] ?? 0);
					}
				}
			}
			return $term instanceof \WP_Term ? (int) $term->term_id : 0;
		}

		$type = \sanitize_key($raw_type);
		if ($type === '' || !\post_type_exists($type)) {
			return 0;
		}
		$slug = \sanitize_title((string) ($ref['target_slug'] ?? ''));
		if ($slug !== '') {
			$found = \get_page_by_path($slug, OBJECT, $type);
			if ($found instanceof \WP_Post) {
				return (int) $found->ID;
			}
		}

		$code = \trim((string) ($ref['target_code'] ?? ''));
		if ($type === 'kontakte') {
			$id = cmx_cpt_transfer_find_post_by_meta($type, ['_cmx_kontakte_kunden_nr', '_cmx_kontakte_muh', '_cmx_kontakte_hr_uid'], $code);
			if ($id > 0) return $id;
			$email = \sanitize_email((string) ($ref['target_email'] ?? ''));
			if ($email !== '') {
				$id = cmx_cpt_transfer_find_post_by_meta($type, ['_cmx_email_1', '_cmx_kontakte_email', '_cmx_kontakt_email'], $email);
				if ($id > 0) return $id;
			}
		} elseif ($type === 'artikel') {
			$id = cmx_cpt_transfer_find_post_by_meta($type, ['_cmx_artikel_sku', 'cmx_artikel_sku', '_cmx_artikel_nr', '_sku'], $code);
			if ($id > 0) return $id;
		}

		return cmx_cpt_transfer_find_post_by_title($type, (string) ($ref['target_title'] ?? ''));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_unresolved_reference_detail')) {
	function cmx_cpt_transfer_unresolved_reference_detail(array $ref, array $meta_row, int $post_id, array $source_posts): array {
		$original_id = (string) ($meta_row['original_id'] ?? ($ref['original_id'] ?? ''));
		$source_post = \is_array($source_posts[$original_id] ?? null) ? $source_posts[$original_id] : [];
		$current_post = $post_id > 0 ? \get_post($post_id) : null;
		return [
			'post_id' => (string) $post_id,
			'post_type' => $current_post instanceof \WP_Post ? (string) $current_post->post_type : (string) ($source_post['post_type'] ?? ''),
			'post_title' => $current_post instanceof \WP_Post ? (string) $current_post->post_title : (string) ($source_post['post_title'] ?? ''),
			'original_id' => $original_id,
			'meta_key' => (string) ($meta_row['meta_key'] ?? ($ref['meta_key'] ?? '')),
			'meta_path' => (string) ($meta_row['meta_path'] ?? ($ref['meta_path'] ?? '')),
			'old_target_id' => (string) ($ref['old_target_id'] ?? ''),
			'target_type' => (string) ($ref['target_type'] ?? ''),
			'target_title' => (string) ($ref['target_title'] ?? ''),
			'target_slug' => (string) ($ref['target_slug'] ?? ''),
			'target_code' => (string) ($ref['target_code'] ?? ''),
			'target_email' => (string) ($ref['target_email'] ?? ''),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_csv')) {
	function cmx_cpt_transfer_csv(array $headers, array $rows): string {
		$handle = \fopen('php://temp', 'r+');
		if (!$handle) {
			return '';
		}
		\fwrite($handle, "\xEF\xBB\xBF");
		\fputcsv($handle, $headers, ';', '"', '\\');
		foreach ($rows as $row) {
			$line = [];
			foreach ($headers as $header) {
				$line[] = (string) ($row[$header] ?? '');
			}
			\fputcsv($handle, $line, ';', '"', '\\');
		}
		\rewind($handle);
		$out = (string) \stream_get_contents($handle);
		\fclose($handle);
		return $out;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_dataset')) {
	function cmx_cpt_transfer_dataset(string $post_type, array $post_ids): array {
		$posts = [];
		$meta = [];
		$terms = [];
		$files = [];
		$refs = [];

		$file_rels = [];
		$taxonomies = \get_object_taxonomies($post_type, 'names');

		foreach ($post_ids as $post_id) {
			$post = \get_post((int) $post_id);
			if (!$post instanceof \WP_Post || $post->post_type !== $post_type) {
				continue;
			}

			cmx_cpt_transfer_collect_file_rels_from_value($post->post_content, $file_rels);
			cmx_cpt_transfer_collect_file_rels_from_value($post->post_excerpt, $file_rels);

			$thumb_id = \get_post_thumbnail_id($post->ID);
			if ($thumb_id > 0) {
				$thumb_rel = (string) \get_post_meta($thumb_id, '_wp_attached_file', true);
				cmx_cpt_transfer_collect_file_rels_from_value($thumb_rel, $file_rels);
			}

			$posts[] = [
				'original_id' => (string) $post->ID,
				'post_type' => $post->post_type,
				'post_status' => $post->post_status,
				'post_title' => $post->post_title,
				'post_name' => $post->post_name,
				'post_date' => $post->post_date,
				'post_content' => $post->post_content,
				'post_excerpt' => $post->post_excerpt,
				'menu_order' => (string) $post->menu_order,
				'thumbnail_rel' => $thumb_id > 0 ? (string) \get_post_meta($thumb_id, '_wp_attached_file', true) : '',
			];

			foreach (cmx_cpt_transfer_meta_keys_for_post($post->ID) as $meta_key) {
				$value = \get_post_meta($post->ID, $meta_key, true);
				cmx_cpt_transfer_collect_file_rels_from_value($value, $file_rels);
				$rows = cmx_cpt_transfer_meta_rows((string) $post->ID, $meta_key, $value);
				foreach ($rows as $row) {
					$ref = cmx_cpt_transfer_reference_row(
						(string) $post->ID,
						$meta_key,
						(string) ($row['meta_path'] ?? ''),
						(string) ($row['meta_value'] ?? '')
					);
					if ($ref === []) {
						$ref = cmx_cpt_transfer_term_reference_row(
							(string) $post->ID,
							$meta_key,
							(string) ($row['meta_path'] ?? ''),
							(string) ($row['meta_value'] ?? '')
						);
					}
					if ($ref !== []) {
						$refs[] = $ref;
					}
				}
				$meta = \array_merge($meta, $rows);
			}

			foreach ($taxonomies as $taxonomy) {
				$post_terms = \wp_get_post_terms($post->ID, $taxonomy);
				if (\is_wp_error($post_terms)) {
					continue;
				}
				foreach ($post_terms as $term) {
					$terms[] = [
						'original_id' => (string) $post->ID,
						'taxonomy' => (string) $taxonomy,
						'slug' => (string) $term->slug,
						'name' => (string) $term->name,
					];
				}
			}

			if ($post_type === 'belege' && \function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_pdf_paths')) {
				[, $pdf_abs] = cmxbu_get_beleg_pdf_paths($post);
				$pdf_rel = cmx_cpt_transfer_upload_rel_from_abs((string) $pdf_abs);
				cmx_cpt_transfer_collect_file_rels_from_value($pdf_rel, $file_rels);
			}
		}

		foreach (\array_keys($file_rels) as $rel) {
			$files[] = [
				'rel_path' => (string) $rel,
				'zip_path' => 'files/' . \ltrim((string) $rel, '/'),
			];
		}

		return [
			'posts' => $posts,
			'meta' => $meta,
			'terms' => $terms,
			'files' => $files,
			'refs' => $refs,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_add_dataset_to_zip')) {
	function cmx_cpt_transfer_add_dataset_to_zip(\ZipArchive $zip, string $base_path, array $data): void {
		$base_path = \trim(\str_replace('\\', '/', $base_path), '/');
		$base_path = $base_path !== '' ? $base_path . '/' : '';
		$zip->addFromString($base_path . 'posts.csv', cmx_cpt_transfer_csv(['original_id','post_type','post_status','post_title','post_name','post_date','post_content','post_excerpt','menu_order','thumbnail_rel'], (array) ($data['posts'] ?? [])));
		$zip->addFromString($base_path . 'meta.csv', cmx_cpt_transfer_csv(['original_id','meta_key','meta_path','meta_value'], (array) ($data['meta'] ?? [])));
		$zip->addFromString($base_path . 'terms.csv', cmx_cpt_transfer_csv(['original_id','taxonomy','slug','name'], (array) ($data['terms'] ?? [])));
		$zip->addFromString($base_path . 'files.csv', cmx_cpt_transfer_csv(['rel_path','zip_path'], (array) ($data['files'] ?? [])));
		$zip->addFromString($base_path . 'refs.csv', cmx_cpt_transfer_csv(['original_id','meta_key','meta_path','target_type','old_target_id','target_slug','target_title','target_code','target_email'], (array) ($data['refs'] ?? [])));

		foreach ((array) ($data['files'] ?? []) as $file) {
			$rel = (string) ($file['rel_path'] ?? '');
			$zip_path = (string) ($file['zip_path'] ?? '');
			$abs = cmx_cpt_transfer_upload_abs_from_rel($rel);
			if ($rel === '' || $zip_path === '' || $abs === '' || !\is_file($abs)) {
				continue;
			}
			$zip->addFile($abs, $base_path . $zip_path);
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_stream_file')) {
	function cmx_cpt_transfer_stream_file(string $path, string $name): void {
		$size = @\filesize($path);
		\header('Content-Type: application/zip');
		\header('Content-Disposition: attachment; filename="' . \sanitize_file_name($name) . '"');
		if (\is_int($size) && $size > 0) {
			\header('Content-Length: ' . $size);
		}
		\header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		\readfile($path);
		@\unlink($path);
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_stream_backup_download')) {
	function cmx_cpt_transfer_stream_backup_download(string $path, string $name, bool $delete_after): void {
		if ($path === '' || !\is_file($path)) {
			\wp_die('Backup-Datei wurde nicht gefunden.');
		}
		$size = @\filesize($path);
		\header('Content-Type: application/zip');
		\header('Content-Disposition: attachment; filename="' . \sanitize_file_name($name) . '"');
		if (\is_int($size) && $size > 0) {
			\header('Content-Length: ' . $size);
		}
		\header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		\readfile($path);
		if ($delete_after) {
			@\unlink($path);
		}
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_create_zip')) {
	function cmx_cpt_transfer_create_zip(callable $writer): string {
		if (!\class_exists('\\ZipArchive')) {
			\wp_die('ZIP ist nicht verfügbar.');
		}
		$tmp = \wp_tempnam('cmx-cpt-transfer-');
		if (!\is_string($tmp) || $tmp === '') {
			\wp_die('ZIP konnte nicht erstellt werden.');
		}
		if (\is_file($tmp)) {
			@unlink($tmp);
		}

		$zip = new \ZipArchive();
		if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
			@unlink($tmp);
			\wp_die('ZIP konnte nicht geöffnet werden.');
		}

		$writer($zip);
		$zip->close();
		return $tmp;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_stream_zip')) {
	function cmx_cpt_transfer_stream_zip(string $post_type): void {
		$post_ids = cmx_cpt_transfer_collect_ids($post_type);
		$data = cmx_cpt_transfer_dataset($post_type, $post_ids);
		$tmp = cmx_cpt_transfer_create_zip(static function (\ZipArchive $zip) use ($data): void {
			cmx_cpt_transfer_add_dataset_to_zip($zip, '', $data);
		});
		$name = cmx_cpt_transfer_filename($post_type);
		cmx_cpt_transfer_stream_file($tmp, $name);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_stream_backup_zip')) {
	function cmx_cpt_transfer_create_backup_zip_file(array $post_types): array {
		$supported = cmx_cpt_transfer_supported_post_types();
		$post_types = \array_values(\array_filter(\array_unique(\array_map('sanitize_key', $post_types)), static function (string $post_type) use ($supported): bool {
			return \in_array($post_type, $supported, true);
		}));
		if ($post_types === []) {
			\wp_die('Bitte mindestens ein Modul auswählen.');
		}

		$tmp = cmx_cpt_transfer_create_zip(static function (\ZipArchive $zip) use ($post_types): void {
			$manifest = [];
			foreach ($post_types as $post_type) {
				$post_ids = cmx_cpt_transfer_collect_ids($post_type);
				$data = cmx_cpt_transfer_dataset($post_type, $post_ids);
				$base = 'cpts/' . \sanitize_key($post_type);
				cmx_cpt_transfer_add_dataset_to_zip($zip, $base, $data);
				$manifest[] = [
					'post_type' => $post_type,
					'label' => cmx_cpt_transfer_post_type_label($post_type),
					'posts' => (string) \count((array) ($data['posts'] ?? [])),
					'meta' => (string) \count((array) ($data['meta'] ?? [])),
					'files' => (string) \count((array) ($data['files'] ?? [])),
					'refs' => (string) \count((array) ($data['refs'] ?? [])),
				];
			}
			$zip->addFromString('manifest.csv', cmx_cpt_transfer_csv(['post_type','label','posts','meta','files','refs'], $manifest));
		});

		return [
			'path' => $tmp,
			'name' => cmx_cpt_transfer_backup_filename(),
		];
	}

	function cmx_cpt_transfer_stream_backup_zip(array $post_types): void {
		$file = cmx_cpt_transfer_create_backup_zip_file($post_types);
		cmx_cpt_transfer_stream_file((string) ($file['path'] ?? ''), (string) ($file['name'] ?? cmx_cpt_transfer_backup_filename()));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_backup_webdav_rel_dir')) {
	function cmx_cpt_transfer_backup_webdav_rel_dir(): string {
		return 'backups';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_backup_settings_option')) {
	function cmx_cpt_transfer_backup_settings_option(): string {
		return \defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN') ? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN') : 'cmx_einstellungen';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_backup_webdav_settings')) {
	function cmx_cpt_transfer_backup_webdav_settings(): array {
		$options = (array) \get_option(cmx_cpt_transfer_backup_settings_option(), []);
		$path = \trim((string) ($options['backup_webdav_path'] ?? cmx_cpt_transfer_backup_webdav_rel_dir()), " \t\n\r\0\x0B/");
		if ($path === '') {
			$path = cmx_cpt_transfer_backup_webdav_rel_dir();
		}
		return [
			'url' => \untrailingslashit(\esc_url_raw((string) ($options['backup_webdav_url'] ?? ''))),
			'user' => \sanitize_text_field((string) ($options['backup_webdav_user'] ?? '')),
			'password' => (string) ($options['backup_webdav_password'] ?? ''),
			'path' => $path,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_backup_webdav_ready')) {
	function cmx_cpt_transfer_backup_webdav_ready(array $settings = []): bool {
		$settings = $settings !== [] ? $settings : cmx_cpt_transfer_backup_webdav_settings();
		$has_required_fields = (string) ($settings['url'] ?? '') !== ''
			&& (string) ($settings['user'] ?? '') !== ''
			&& (string) ($settings['password'] ?? '') !== '';
		if (!$has_required_fields) {
			return false;
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_webdav_local_archive_candidate_rel')
			&& cmx_cpt_transfer_webdav_local_archive_candidate_rel($settings) !== '') {
			return \function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_webdav_local_credentials_valid')
				&& cmx_cpt_transfer_webdav_local_credentials_valid($settings);
		}
		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_webdav_join_url')) {
	function cmx_cpt_transfer_webdav_join_url(array $settings, string $filename = ''): string {
		$url = \untrailingslashit((string) ($settings['url'] ?? ''));
		$parts = [];
		$path = \trim((string) ($settings['path'] ?? ''), '/');
		if ($path !== '') {
			$parts = \array_merge($parts, \explode('/', $path));
		}
		if ($filename !== '') {
			$parts[] = $filename;
		}
		foreach ($parts as $part) {
			$part = \trim((string) $part, '/');
			if ($part === '') {
				continue;
			}
			$url .= '/' . \rawurlencode($part);
		}
		return $url;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_webdav_request')) {
	function cmx_cpt_transfer_webdav_request(string $method, string $url, array $settings, array $args = []): array {
		$method = \strtoupper($method);
		$headers = (array) ($args['headers'] ?? []);
		$status = 0;
		$body = '';
		$error = '';

		if (\function_exists('\\curl_init')) {
			$ch = \curl_init($url);
			if (!$ch) {
				return ['status' => 0, 'body' => '', 'error' => 'curl_init fehlgeschlagen'];
			}
			$header_lines = [];
			foreach ($headers as $key => $value) {
				$header_lines[] = (string) $key . ': ' . (string) $value;
			}
			\curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			\curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			\curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
			\curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
			\curl_setopt($ch, CURLOPT_TIMEOUT, 0);
			\curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
			\curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
			\curl_setopt($ch, CURLOPT_USERPWD, (string) ($settings['user'] ?? '') . ':' . (string) ($settings['password'] ?? ''));
			if ($header_lines !== []) {
				\curl_setopt($ch, CURLOPT_HTTPHEADER, $header_lines);
			}
			if (isset($args['body'])) {
				\curl_setopt($ch, CURLOPT_POSTFIELDS, (string) $args['body']);
			}
			if (!empty($args['upload_file'])) {
				$upload_file = (string) $args['upload_file'];
				$fh = @\fopen($upload_file, 'rb');
				if (!$fh) {
					\curl_close($ch);
					return ['status' => 0, 'body' => '', 'error' => 'Upload-Datei konnte nicht geöffnet werden'];
				}
				\curl_setopt($ch, CURLOPT_UPLOAD, true);
				\curl_setopt($ch, CURLOPT_INFILE, $fh);
				\curl_setopt($ch, CURLOPT_INFILESIZE, (int) @\filesize($upload_file));
			}
			if (!empty($args['download_file'])) {
				$download_file = (string) $args['download_file'];
				$dfh = @\fopen($download_file, 'wb');
				if (!$dfh) {
					if (isset($fh) && \is_resource($fh)) {
						\fclose($fh);
					}
					\curl_close($ch);
					return ['status' => 0, 'body' => '', 'error' => 'Download-Datei konnte nicht geöffnet werden'];
				}
				\curl_setopt($ch, CURLOPT_FILE, $dfh);
				\curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
			}
			$response = \curl_exec($ch);
			$status = (int) \curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
			if ($response === false) {
				$error = (string) \curl_error($ch);
			} elseif (empty($args['download_file'])) {
				$body = (string) $response;
			}
			if (isset($fh) && \is_resource($fh)) {
				\fclose($fh);
			}
			if (isset($dfh) && \is_resource($dfh)) {
				\fclose($dfh);
			}
			\curl_close($ch);
			return ['status' => $status, 'body' => $body, 'error' => $error];
		}

		$request_args = [
			'method' => $method,
			'timeout' => 300,
			'redirection' => 0,
			'headers' => \array_merge($headers, [
				'Authorization' => 'Basic ' . \base64_encode((string) ($settings['user'] ?? '') . ':' . (string) ($settings['password'] ?? '')),
			]),
		];
		if (isset($args['body'])) {
			$request_args['body'] = (string) $args['body'];
		}
		if (!empty($args['upload_file'])) {
			$request_args['body'] = (string) @\file_get_contents((string) $args['upload_file']);
		}
		$response = \wp_remote_request($url, $request_args);
		if (\is_wp_error($response)) {
			return ['status' => 0, 'body' => '', 'error' => $response->get_error_message()];
		}
		$status = (int) \wp_remote_retrieve_response_code($response);
		$body = (string) \wp_remote_retrieve_body($response);
		if (!empty($args['download_file'])) {
			@\file_put_contents((string) $args['download_file'], $body);
			$body = '';
		}
		return ['status' => $status, 'body' => $body, 'error' => ''];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_webdav_ensure_dir')) {
	function cmx_cpt_transfer_webdav_ensure_dir(array $settings): bool {
		if (!cmx_cpt_transfer_backup_webdav_ready($settings)) {
			return false;
		}
		$current = \untrailingslashit((string) ($settings['url'] ?? ''));
		$segments = \array_filter(\explode('/', \trim((string) ($settings['path'] ?? ''), '/')), static fn($part): bool => $part !== '');
		foreach ($segments as $segment) {
			$current .= '/' . \rawurlencode((string) $segment);
			$response = cmx_cpt_transfer_webdav_request('MKCOL', $current, $settings);
			$status = (int) ($response['status'] ?? 0);
			if (!\in_array($status, [200, 201, 204, 301, 302, 405], true)) {
				return false;
			}
		}
		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_save_backup_to_webdav')) {
	function cmx_cpt_transfer_save_backup_to_webdav(array $post_types): array {
		$settings = cmx_cpt_transfer_backup_webdav_settings();
		if (!cmx_cpt_transfer_backup_webdav_ready($settings)) {
			return ['saved' => false, 'name' => '', 'path' => '', 'url' => '', 'error' => 'WebDAV ist nicht vollständig eingerichtet.'];
		}
		$file = cmx_cpt_transfer_create_backup_zip_file($post_types);
		$source = (string) ($file['path'] ?? '');
		$name = \sanitize_file_name((string) ($file['name'] ?? cmx_cpt_transfer_backup_filename()));
		if ($source === '' || !\is_file($source)) {
			return ['saved' => false, 'name' => $name, 'path' => '', 'url' => '', 'error' => 'Modul-Backup konnte nicht erzeugt werden.'];
		}
		$local_archive_dir = cmx_cpt_transfer_webdav_local_archive_dir($settings);
		if ($local_archive_dir !== '') {
			\wp_mkdir_p($local_archive_dir);
			$target = \wp_normalize_path(\trailingslashit($local_archive_dir) . $name);
			$saved = @\rename($source, $target);
			if (!$saved) {
				$saved = @\copy($source, $target);
				@\unlink($source);
			}
			$archive_rel = cmx_cpt_transfer_webdav_local_archive_rel($settings);
			return [
				'saved' => $saved && \is_file($target),
				'name' => $name,
				'path' => $target,
				'url' => $archive_rel !== '' && \function_exists(__NAMESPACE__ . '\\cmx_dav_archive_file_url') ? (string) cmx_dav_archive_file_url(\trim($archive_rel, '/') . '/' . $name) : '',
				'error' => $saved ? '' : 'WebDAV-Backup konnte nicht lokal im Archiv gespeichert werden.',
			];
		}
		if (!cmx_cpt_transfer_webdav_ensure_dir($settings)) {
			@\unlink($source);
			return ['saved' => false, 'name' => $name, 'path' => '', 'url' => '', 'error' => 'WebDAV-Zielordner konnte nicht angelegt werden.'];
		}
		$url = cmx_cpt_transfer_webdav_join_url($settings, $name);
		$response = cmx_cpt_transfer_webdav_request('PUT', $url, $settings, ['upload_file' => $source]);
		@\unlink($source);
		$status = (int) ($response['status'] ?? 0);
		$saved = \in_array($status, [200, 201, 204], true);
		return [
			'saved' => $saved,
			'name' => $name,
			'path' => '',
			'url' => $url,
			'error' => $saved ? '' : ((string) ($response['error'] ?? '') ?: 'WebDAV-Upload fehlgeschlagen.'),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_backup_local_dir')) {
	function cmx_cpt_transfer_backup_local_dir(): string {
		$rel = cmx_cpt_transfer_backup_webdav_rel_dir();
		if (\function_exists(__NAMESPACE__ . '\\cmx_dav_archive_file_path')) {
			$dir = (string) cmx_dav_archive_file_path($rel);
		} else {
			$uploads = \wp_get_upload_dir();
			$dir = \trailingslashit((string) ($uploads['basedir'] ?? '')) . 'misbuero/archiv/' . $rel;
		}
		$dir = \wp_normalize_path($dir);
		\wp_mkdir_p($dir);
		return $dir;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_save_backup_to_local')) {
	function cmx_cpt_transfer_save_backup_to_local(array $post_types): array {
		$file = cmx_cpt_transfer_create_backup_zip_file($post_types);
		$source = (string) ($file['path'] ?? '');
		$name = \sanitize_file_name((string) ($file['name'] ?? cmx_cpt_transfer_backup_filename()));
		$dir = cmx_cpt_transfer_backup_local_dir();
		$target = \wp_normalize_path(\trailingslashit($dir) . $name);
		if ($source === '' || !\is_file($source) || $dir === '' || !\is_dir($dir)) {
			if ($source !== '') {
				@\unlink($source);
			}
			return ['saved' => false, 'name' => $name, 'path' => '', 'error' => 'Lokales Backup konnte nicht erzeugt werden.'];
		}
		$saved = @\rename($source, $target);
		if (!$saved) {
			$saved = @\copy($source, $target);
			@\unlink($source);
		}
		return [
			'saved' => $saved && \is_file($target),
			'name' => $name,
			'path' => $target,
			'error' => $saved ? '' : 'Lokales Backup konnte nicht gespeichert werden.',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_save_uploaded_backup_to_local')) {
	function cmx_cpt_transfer_save_uploaded_backup_to_local(array $file): array {
		if (!\class_exists('\\ZipArchive')) {
			return ['saved' => false, 'name' => '', 'path' => '', 'error' => 'ZIP ist nicht verfügbar.'];
		}
		$error = (int) ($file['error'] ?? \UPLOAD_ERR_NO_FILE);
		if ($error !== \UPLOAD_ERR_OK) {
			return ['saved' => false, 'name' => '', 'path' => '', 'error' => 'Backup-Datei konnte nicht hochgeladen werden.'];
		}

		$tmp_name = (string) ($file['tmp_name'] ?? '');
		$original_name = \sanitize_file_name((string) ($file['name'] ?? ''));
		if ($tmp_name === '' || !\is_uploaded_file($tmp_name) || $original_name === '') {
			return ['saved' => false, 'name' => '', 'path' => '', 'error' => 'Backup-Datei fehlt.'];
		}
		if (!\str_ends_with(\strtolower($original_name), '.zip')) {
			return ['saved' => false, 'name' => $original_name, 'path' => '', 'error' => 'Bitte eine ZIP-Datei hochladen.'];
		}

		$zip = new \ZipArchive();
		if ($zip->open($tmp_name) !== true) {
			return ['saved' => false, 'name' => $original_name, 'path' => '', 'error' => 'Die hochgeladene ZIP-Datei konnte nicht gelesen werden.'];
		}
		$zip->close();

		$dir = cmx_cpt_transfer_backup_local_dir();
		if ($dir === '' || !\is_dir($dir) || !\is_writable($dir)) {
			return ['saved' => false, 'name' => $original_name, 'path' => '', 'error' => 'Lokaler Backup-Ordner ist nicht beschreibbar.'];
		}

		$name = \wp_unique_filename($dir, $original_name);
		$target = \wp_normalize_path(\trailingslashit($dir) . $name);
		if (!\move_uploaded_file($tmp_name, $target)) {
			return ['saved' => false, 'name' => $name, 'path' => '', 'error' => 'Backup konnte nicht im lokalen Backup-Ordner gespeichert werden.'];
		}

		@chmod($target, \defined('FS_CHMOD_FILE') ? \FS_CHMOD_FILE : 0644);
		return [
			'saved' => \is_file($target),
			'name' => $name,
			'path' => $target,
			'error' => \is_file($target) ? '' : 'Backup wurde hochgeladen, ist aber im Zielordner nicht auffindbar.',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_backup_file_list_from_dir')) {
	function cmx_cpt_transfer_backup_file_list_from_dir(string $dir): array {
		$dir = \wp_normalize_path($dir);
		if ($dir === '' || !\is_dir($dir) || !\is_readable($dir)) {
			return [];
		}
		$items = \scandir($dir);
		if (!\is_array($items)) {
			return [];
		}
		$files = [];
		foreach ($items as $item) {
			$name = \sanitize_file_name((string) $item);
			if ($name === '' || $name !== $item || !\str_ends_with(\strtolower($name), '.zip')) {
				continue;
			}
			$path = \wp_normalize_path(\trailingslashit($dir) . $name);
			if (!\is_file($path)) {
				continue;
			}
			$files[] = [
				'name' => $name,
				'path' => $path,
				'url' => '',
				'size' => (int) @\filesize($path),
				'modified' => (int) @\filemtime($path),
			];
		}
		\usort($files, static function (array $left, array $right): int {
			return ((int) ($right['modified'] ?? 0)) <=> ((int) ($left['modified'] ?? 0));
		});
		return $files;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_local_backup_files')) {
	function cmx_cpt_transfer_local_backup_files(): array {
		return cmx_cpt_transfer_backup_file_list_from_dir(cmx_cpt_transfer_backup_local_dir());
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_local_backup_path')) {
	function cmx_cpt_transfer_local_backup_path(string $name): string {
		$name = \sanitize_file_name($name);
		if ($name === '' || !\str_ends_with(\strtolower($name), '.zip')) {
			return '';
		}
		$path = \wp_normalize_path(\trailingslashit(cmx_cpt_transfer_backup_local_dir()) . $name);
		return \is_file($path) ? $path : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_delete_local_backup')) {
	function cmx_cpt_transfer_delete_local_backup(string $name): array {
		$name = \sanitize_file_name($name);
		$path = cmx_cpt_transfer_local_backup_path($name);
		if ($path === '') {
			return ['deleted' => false, 'name' => $name, 'error' => 'Bitte ein lokales Backup auswählen.'];
		}
		return [
			'deleted' => @\unlink($path),
			'name' => $name,
			'error' => \is_file($path) ? 'Backup konnte nicht gelöscht werden.' : '',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_webdav_local_credentials_valid')) {
	function cmx_cpt_transfer_webdav_local_credentials_valid(array $settings): bool {
		$user_login = (string) ($settings['user'] ?? '');
		$password = (string) ($settings['password'] ?? '');
		if ($user_login === '' || $password === '') {
			return false;
		}
		$user = \get_user_by('login', $user_login);
		return $user instanceof \WP_User && \wp_check_password($password, $user->user_pass, $user->ID);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_webdav_local_archive_candidate_rel')) {
	function cmx_cpt_transfer_webdav_local_archive_candidate_rel(array $settings): string {
		$url = \trim((string) ($settings['url'] ?? ''));
		if ($url === '') {
			return '';
		}
		$url_host = \strtolower((string) \parse_url($url, \PHP_URL_HOST));
		$home_host = \strtolower((string) \parse_url((string) \home_url('/'), \PHP_URL_HOST));
		if ($url_host === '' || $home_host === '' || $url_host !== $home_host) {
			return '';
		}

		$url_path = '/' . \trim((string) \parse_url($url, \PHP_URL_PATH), '/');
		$home_path = '/' . \trim((string) \parse_url((string) \home_url('/'), \PHP_URL_PATH), '/');
		if ($home_path !== '/' && \str_starts_with($url_path . '/', \rtrim($home_path, '/') . '/')) {
			$url_path = '/' . \ltrim(\substr($url_path, \strlen(\rtrim($home_path, '/'))), '/');
		}

		$url_rel = \trim($url_path, '/');
		if ($url_rel === 'archiv') {
			$archive_rel = \trim((string) ($settings['path'] ?? cmx_cpt_transfer_backup_webdav_rel_dir()), '/');
		} elseif (\str_starts_with($url_rel . '/', 'archiv/')) {
			$archive_rel = \trim((string) \substr($url_rel, \strlen('archiv/')), '/');
		} else {
			return '';
		}
		if ($archive_rel === '') {
			$archive_rel = cmx_cpt_transfer_backup_webdav_rel_dir();
		}
		return $archive_rel;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_webdav_local_archive_rel')) {
	function cmx_cpt_transfer_webdav_local_archive_rel(array $settings): string {
		$archive_rel = cmx_cpt_transfer_webdav_local_archive_candidate_rel($settings);
		if ($archive_rel === '' || !cmx_cpt_transfer_webdav_local_credentials_valid($settings)) {
			return '';
		}
		return $archive_rel;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_webdav_local_archive_dir')) {
	function cmx_cpt_transfer_webdav_local_archive_dir(array $settings): string {
		$archive_rel = cmx_cpt_transfer_webdav_local_archive_rel($settings);
		if ($archive_rel === '') {
			return '';
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_dav_archive_file_path')) {
			$dir = (string) cmx_dav_archive_file_path($archive_rel);
		} else {
			$uploads = \wp_get_upload_dir();
			$dir = \trailingslashit((string) ($uploads['basedir'] ?? '')) . 'misbuero/archiv/' . $archive_rel;
		}
		return \wp_normalize_path($dir);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_webdav_backup_files')) {
	function cmx_cpt_transfer_webdav_backup_files(): array {
		$settings = cmx_cpt_transfer_backup_webdav_settings();
		if (!cmx_cpt_transfer_backup_webdav_ready($settings)) {
			return [];
		}
		$local_archive_dir = cmx_cpt_transfer_webdav_local_archive_dir($settings);
		if ($local_archive_dir !== '') {
			$files = cmx_cpt_transfer_backup_file_list_from_dir($local_archive_dir);
			$archive_rel = cmx_cpt_transfer_webdav_local_archive_rel($settings);
			foreach ($files as &$file) {
				$name = \sanitize_file_name((string) ($file['name'] ?? ''));
				$file['url'] = $archive_rel !== '' && $name !== '' && \function_exists(__NAMESPACE__ . '\\cmx_dav_archive_file_url')
					? (string) cmx_dav_archive_file_url(\trim($archive_rel, '/') . '/' . $name)
					: cmx_cpt_transfer_webdav_join_url($settings, $name);
				$file['source'] = 'webdav';
			}
			unset($file);
			return $files;
		}
		$url = cmx_cpt_transfer_webdav_join_url($settings);
		$body = '<?xml version="1.0" encoding="utf-8" ?><d:propfind xmlns:d="DAV:"><d:prop><d:getcontentlength/><d:getlastmodified/><d:resourcetype/></d:prop></d:propfind>';
		$response = cmx_cpt_transfer_webdav_request('PROPFIND', $url, $settings, [
			'headers' => ['Depth' => '1', 'Content-Type' => 'application/xml; charset=utf-8'],
			'body' => $body,
		]);
		$status = (int) ($response['status'] ?? 0);
		if (!\in_array($status, [207, 200], true)) {
			return [];
		}
		$xml_body = (string) ($response['body'] ?? '');
		$files = [];
		if ($xml_body !== '' && \function_exists('\\simplexml_load_string')) {
			$xml = @\simplexml_load_string($xml_body);
			if ($xml) {
				$xml->registerXPathNamespace('d', 'DAV:');
				foreach ((array) $xml->xpath('//d:response') as $response_node) {
					$href_nodes = $response_node->xpath('d:href');
					$href = isset($href_nodes[0]) ? (string) $href_nodes[0] : '';
					$name = \sanitize_file_name((string) \basename((string) \parse_url(\rawurldecode($href), PHP_URL_PATH)));
					if ($name === '' || !\str_ends_with(\strtolower($name), '.zip')) {
						continue;
					}
					$length_nodes = $response_node->xpath('.//d:getcontentlength');
					$modified_nodes = $response_node->xpath('.//d:getlastmodified');
					$modified_raw = isset($modified_nodes[0]) ? (string) $modified_nodes[0] : '';
					$files[] = [
						'name' => $name,
						'path' => '',
						'url' => cmx_cpt_transfer_webdav_join_url($settings, $name),
						'size' => isset($length_nodes[0]) ? (int) $length_nodes[0] : 0,
						'modified' => $modified_raw !== '' ? (int) \strtotime($modified_raw) : 0,
					];
				}
			}
		}
		\usort($files, static function (array $left, array $right): int {
			return ((int) ($right['modified'] ?? 0)) <=> ((int) ($left['modified'] ?? 0));
		});
		return $files;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_webdav_backup_path')) {
	function cmx_cpt_transfer_webdav_backup_path(string $name): string {
		$name = \sanitize_file_name($name);
		if ($name === '' || !\str_ends_with(\strtolower($name), '.zip')) {
			return '';
		}
		$settings = cmx_cpt_transfer_backup_webdav_settings();
		if (!cmx_cpt_transfer_backup_webdav_ready($settings)) {
			return '';
		}
		$tmp = \wp_tempnam('cmx-webdav-backup-');
		if (!\is_string($tmp) || $tmp === '') {
			return '';
		}
		if (\is_file($tmp)) {
			@\unlink($tmp);
		}
		$local_archive_dir = cmx_cpt_transfer_webdav_local_archive_dir($settings);
		if ($local_archive_dir !== '') {
			$local_path = \wp_normalize_path(\trailingslashit($local_archive_dir) . $name);
			if (!\is_file($local_path) || !@\copy($local_path, $tmp)) {
				@\unlink($tmp);
				return '';
			}
			return $tmp;
		}
		$response = cmx_cpt_transfer_webdav_request('GET', cmx_cpt_transfer_webdav_join_url($settings, $name), $settings, ['download_file' => $tmp]);
		$status = (int) ($response['status'] ?? 0);
		if (!\in_array($status, [200, 206], true) || !\is_file($tmp)) {
			@\unlink($tmp);
			return '';
		}
		return $tmp;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_delete_webdav_backup')) {
	function cmx_cpt_transfer_delete_webdav_backup(string $name): array {
		$name = \sanitize_file_name($name);
		if ($name === '' || !\str_ends_with(\strtolower($name), '.zip')) {
			return ['deleted' => false, 'name' => $name, 'error' => 'Bitte ein WebDAV-Backup auswählen.'];
		}

		$settings = cmx_cpt_transfer_backup_webdav_settings();
		if (!cmx_cpt_transfer_backup_webdav_ready($settings)) {
			return ['deleted' => false, 'name' => $name, 'error' => 'WebDAV ist nicht vollständig eingerichtet.'];
		}

		$local_archive_dir = cmx_cpt_transfer_webdav_local_archive_dir($settings);
		if ($local_archive_dir !== '') {
			$local_path = \wp_normalize_path(\trailingslashit($local_archive_dir) . $name);
			if (!\is_file($local_path)) {
				return ['deleted' => false, 'name' => $name, 'error' => 'WebDAV-Backup wurde nicht gefunden.'];
			}
			return [
				'deleted' => @\unlink($local_path),
				'name' => $name,
				'error' => \is_file($local_path) ? 'WebDAV-Backup konnte nicht gelöscht werden.' : '',
			];
		}

		$response = cmx_cpt_transfer_webdav_request('DELETE', cmx_cpt_transfer_webdav_join_url($settings, $name), $settings);
		$status = (int) ($response['status'] ?? 0);
		$deleted = \in_array($status, [200, 202, 204, 404], true);
		return [
			'deleted' => $deleted,
			'name' => $name,
			'error' => $deleted ? '' : ((string) ($response['error'] ?? '') ?: 'WebDAV-Backup konnte nicht gelöscht werden.'),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_read_csv')) {
	function cmx_cpt_transfer_read_csv(string $path): array {
		$rows = [];
		$handle = @\fopen($path, 'r');
		if (!$handle) {
			return [];
		}
		$first = \fread($handle, 3);
		if ($first !== "\xEF\xBB\xBF") {
			\fseek($handle, 0);
		}
		$headers = \fgetcsv($handle, 0, ';', '"', '\\');
		if (!$headers) {
			\fclose($handle);
			return [];
		}
		$headers = \array_map('trim', $headers);
		while (($line = \fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
			$row = @\array_combine($headers, $line);
			if (\is_array($row)) {
				$rows[] = $row;
			}
		}
		\fclose($handle);
		return $rows;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_rm_dir')) {
	function cmx_cpt_transfer_rm_dir(string $dir): void {
		if ($dir === '' || !\is_dir($dir)) {
			return;
		}
		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($it as $item) {
			$path = $item instanceof \SplFileInfo ? $item->getPathname() : '';
			if ($path === '') continue;
			$item->isDir() ? @\rmdir($path) : @\unlink($path);
		}
		@\rmdir($dir);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_import_zip')) {
	function cmx_cpt_transfer_ensure_attachment(string $rel): int {
		$rel = \ltrim(\str_replace('\\', '/', $rel), '/');
		$abs = cmx_cpt_transfer_upload_abs_from_rel($rel);
		if ($rel === '' || $abs === '' || !\is_file($abs)) {
			return 0;
		}

		global $wpdb;
		$existing = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s ORDER BY post_id ASC LIMIT 1",
			$rel
		));
		if ($existing > 0 && (string) \get_post_type($existing) === 'attachment') {
			return $existing;
		}

		$filetype = \wp_check_filetype(\basename($abs));
		$attachment_id = \wp_insert_attachment([
			'post_mime_type' => (string) ($filetype['type'] ?? 'application/octet-stream'),
			'post_title' => \sanitize_file_name(\pathinfo($abs, \PATHINFO_FILENAME)),
			'post_status' => 'inherit',
		], $abs);
		if (\is_wp_error($attachment_id) || (int) $attachment_id <= 0) {
			return 0;
		}

		\update_post_meta((int) $attachment_id, '_wp_attached_file', $rel);
		if (\str_starts_with((string) ($filetype['type'] ?? ''), 'image/')) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$metadata = \wp_generate_attachment_metadata((int) $attachment_id, $abs);
			if (\is_array($metadata)) {
				\wp_update_attachment_metadata((int) $attachment_id, $metadata);
			}
		}

		return (int) $attachment_id;
	}

	function cmx_cpt_transfer_clear_post_types(array $post_types): int {
		$supported = cmx_cpt_transfer_supported_post_types();
		$post_types = \array_values(\array_filter(\array_unique(\array_map('sanitize_key', $post_types)), static function (string $post_type) use ($supported): bool {
			return \in_array($post_type, $supported, true);
		}));
		if ($post_types === []) {
			return 0;
		}
		$ids = \get_posts([
			'post_type' => $post_types,
			'post_status' => 'any',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'suppress_filters' => true,
		]);
		$deleted = 0;
		foreach ((array) $ids as $post_id) {
			if (\wp_delete_post((int) $post_id, true)) {
				$deleted++;
			}
		}
		return $deleted;
	}

	function cmx_cpt_transfer_import_post_types_from_dir(string $dir): array {
		$post_types = [];
		foreach (cmx_cpt_transfer_read_csv(\wp_normalize_path($dir) . '/posts.csv') as $row) {
			$post_type = \sanitize_key((string) ($row['post_type'] ?? ''));
			if ($post_type !== '') {
				$post_types[] = $post_type;
			}
		}
		return \array_values(\array_unique($post_types));
	}

	function cmx_cpt_transfer_import_dirs(array $dirs, bool $update_existing, array $selected_post_types = [], bool $clear_first = false): array {
		$result = ['imported' => 0, 'updated' => 0, 'files' => 0, 'refs' => 0, 'unresolved_refs' => 0, 'deleted' => 0, 'unresolved_ref_details' => []];
		$dirs = \array_values(\array_filter(\array_map(static function ($dir): string {
			return \wp_normalize_path((string) $dir);
		}, $dirs), static function (string $dir): bool {
			return $dir !== '' && \is_dir($dir) && \is_file($dir . '/posts.csv');
		}));
		if ($dirs === []) {
			return $result;
		}
		$supported = cmx_cpt_transfer_supported_post_types();
		$selected_post_types = \array_values(\array_filter(\array_unique(\array_map('sanitize_key', $selected_post_types)), static function (string $post_type) use ($supported): bool {
			return \in_array($post_type, $supported, true);
		}));

		if ($selected_post_types !== []) {
			$dirs = \array_values(\array_filter($dirs, static function (string $dir) use ($selected_post_types): bool {
				return \array_intersect(cmx_cpt_transfer_import_post_types_from_dir($dir), $selected_post_types) !== [];
			}));
			if ($dirs === []) {
				return $result;
			}
		}

		$available_post_types = [];
		foreach ($dirs as $dir) {
			$available_post_types = \array_merge($available_post_types, cmx_cpt_transfer_import_post_types_from_dir($dir));
		}
		$available_post_types = \array_values(\array_unique($available_post_types));
		$effective_post_types = $selected_post_types !== []
			? \array_values(\array_intersect($selected_post_types, $available_post_types))
			: $available_post_types;
		if ($clear_first) {
			$result['deleted'] = cmx_cpt_transfer_clear_post_types($effective_post_types);
		}

		$attachment_by_rel = [];
		foreach ($dirs as $dir) {
			foreach (cmx_cpt_transfer_read_csv($dir . '/files.csv') as $file) {
				$rel = \ltrim(\str_replace('\\', '/', (string) ($file['rel_path'] ?? '')), '/');
				$zip_path = \ltrim(\str_replace('\\', '/', (string) ($file['zip_path'] ?? '')), '/');
				if ($rel === '' || $zip_path === '' || \str_contains($rel, '..') || \str_contains($zip_path, '..')) {
					continue;
				}
				$source = \wp_normalize_path($dir . '/' . $zip_path);
				$target = cmx_cpt_transfer_upload_abs_from_rel($rel);
				if ($target === '' || !\is_file($source)) {
					continue;
				}
				\wp_mkdir_p(\dirname($target));
				if (@\copy($source, $target)) {
					$result['files']++;
					$attachment_id = cmx_cpt_transfer_ensure_attachment($rel);
					if ($attachment_id > 0) {
						$attachment_by_rel[$rel] = $attachment_id;
					}
				}
			}
		}

		$id_map = [];
		$source_posts_by_original = [];
		foreach ($dirs as $dir) {
			foreach (cmx_cpt_transfer_read_csv($dir . '/posts.csv') as $row) {
				$post_type = \sanitize_key((string) ($row['post_type'] ?? ''));
				if (!\in_array($post_type, cmx_cpt_transfer_supported_post_types(), true)) {
					continue;
				}
				if ($selected_post_types !== [] && !\in_array($post_type, $selected_post_types, true)) {
					continue;
				}
				$title = \sanitize_text_field((string) ($row['post_title'] ?? ''));
				if ($title === '') {
					continue;
				}
				$original_id = (string) ($row['original_id'] ?? '');
				if ($original_id !== '') {
					$source_posts_by_original[$original_id] = $row;
				}
				$post_name = \sanitize_title((string) ($row['post_name'] ?? $title));
				$existing = 0;
				if ($update_existing && $post_name !== '') {
					$found = \get_page_by_path($post_name, OBJECT, $post_type);
					$existing = $found instanceof \WP_Post ? (int) $found->ID : 0;
				}
				$postarr = [
					'post_type' => $post_type,
					'post_status' => \sanitize_key((string) ($row['post_status'] ?? 'publish')),
					'post_title' => $title,
					'post_name' => $post_name,
					'post_date' => (string) ($row['post_date'] ?? \current_time('mysql')),
					'post_content' => (string) ($row['post_content'] ?? ''),
					'post_excerpt' => (string) ($row['post_excerpt'] ?? ''),
					'menu_order' => (int) ($row['menu_order'] ?? 0),
				];
				if ($existing > 0) {
					$postarr['ID'] = $existing;
				}
				$post_id = \wp_insert_post($postarr, true);
				if (\is_wp_error($post_id) || (int) $post_id <= 0) {
					continue;
				}
				$thumbnail_rel = \ltrim(\str_replace('\\', '/', (string) ($row['thumbnail_rel'] ?? '')), '/');
				if ($thumbnail_rel !== '') {
					$thumbnail_id = (int) ($attachment_by_rel[$thumbnail_rel] ?? cmx_cpt_transfer_ensure_attachment($thumbnail_rel));
					if ($thumbnail_id > 0) {
						\set_post_thumbnail((int) $post_id, $thumbnail_id);
					}
				}
				$id_map[$original_id] = (int) $post_id;
				$existing > 0 ? $result['updated']++ : $result['imported']++;
			}
		}

		$refs_by_meta = [];
		foreach ($dirs as $dir) {
			foreach (cmx_cpt_transfer_read_csv($dir . '/refs.csv') as $row) {
				$original_id = (string) ($row['original_id'] ?? '');
				$key = (string) ($row['meta_key'] ?? '');
				$path = (string) ($row['meta_path'] ?? '');
				if ($original_id === '' || $key === '') {
					continue;
				}
				$refs_by_meta[$original_id . "\0" . $key . "\0" . $path] = $row;
			}
		}

		$meta_by_post = [];
		foreach ($dirs as $dir) {
			foreach (cmx_cpt_transfer_read_csv($dir . '/meta.csv') as $row) {
				$original_id = (string) ($row['original_id'] ?? '');
				$post_id = $id_map[$original_id] ?? 0;
				$key = (string) ($row['meta_key'] ?? '');
				if ($post_id <= 0 || $key === '' || \in_array($key, ['_edit_lock', '_edit_last'], true)) {
					continue;
				}
				$path = (string) ($row['meta_path'] ?? '');
				$value = (string) ($row['meta_value'] ?? '');
				$ref_key = $original_id . "\0" . $key . "\0" . $path;
				if (isset($refs_by_meta[$ref_key])) {
					$resolved = cmx_cpt_transfer_resolve_reference((array) $refs_by_meta[$ref_key], $id_map);
					if ($resolved > 0) {
						$value = (string) $resolved;
						$result['refs']++;
					} else {
						$result['unresolved_refs']++;
						if (\count((array) ($result['unresolved_ref_details'] ?? [])) < 20) {
							$result['unresolved_ref_details'][] = cmx_cpt_transfer_unresolved_reference_detail((array) $refs_by_meta[$ref_key], $row, (int) $post_id, $source_posts_by_original);
						}
					}
				}
				$meta_by_post[$post_id][$key][] = [
					'path' => $path,
					'value' => $value,
				];
			}
		}

		foreach ($meta_by_post as $post_id => $meta_items) {
			foreach ($meta_items as $key => $rows) {
				$value = [];
				$has_path = false;
				foreach ((array) $rows as $row) {
					$path = (string) ($row['path'] ?? '');
					if ($path !== '') {
						$has_path = true;
					}
					cmx_cpt_transfer_assign_meta_path($value, $path, (string) ($row['value'] ?? ''));
				}
				\update_post_meta((int) $post_id, (string) $key, $has_path ? $value : (string) ($rows[0]['value'] ?? ''));
			}
		}

		foreach ($dirs as $dir) {
			foreach (cmx_cpt_transfer_read_csv($dir . '/terms.csv') as $row) {
				$post_id = $id_map[(string) ($row['original_id'] ?? '')] ?? 0;
				$taxonomy = \sanitize_key((string) ($row['taxonomy'] ?? ''));
				if ($post_id <= 0 || $taxonomy === '' || !\taxonomy_exists($taxonomy)) {
					continue;
				}
				$name = \sanitize_text_field((string) ($row['name'] ?? ''));
				$slug = \sanitize_title((string) ($row['slug'] ?? $name));
				if ($name === '') continue;
				$term = \term_exists($slug, $taxonomy);
				if (!$term) {
					$term = \wp_insert_term($name, $taxonomy, ['slug' => $slug]);
				}
				if (!\is_wp_error($term)) {
					$term_id = \is_array($term) ? (int) ($term['term_id'] ?? 0) : (int) $term;
					if ($term_id > 0) {
						\wp_set_object_terms($post_id, [$term_id], $taxonomy, true);
					}
				}
			}
		}

		return $result;
	}

	function cmx_cpt_transfer_import_zip(string $zip_file, bool $update_existing, array $selected_post_types = [], bool $clear_first = false): array {
		$result = ['imported' => 0, 'updated' => 0, 'files' => 0, 'refs' => 0, 'unresolved_refs' => 0, 'deleted' => 0, 'unresolved_ref_details' => []];
		if (!\class_exists('\\ZipArchive')) {
			return $result;
		}
		$tmp = \wp_tempnam('cmx-cpt-import-');
		if (!\is_string($tmp) || $tmp === '') {
			return $result;
		}
		if (\is_file($tmp)) @\unlink($tmp);
		\wp_mkdir_p($tmp);

		$zip = new \ZipArchive();
		if ($zip->open($zip_file) !== true) {
			cmx_cpt_transfer_rm_dir($tmp);
			return $result;
		}
		if (!$zip->extractTo($tmp)) {
			$zip->close();
			cmx_cpt_transfer_rm_dir($tmp);
			return $result;
		}
		$zip->close();

		$dirs = [];
		if (\is_file($tmp . '/posts.csv')) {
			$dirs[] = $tmp;
		} elseif (\is_dir($tmp . '/cpts')) {
			$items = \scandir($tmp . '/cpts');
			if (\is_array($items)) {
				foreach ($items as $item) {
					if ($item === '.' || $item === '..') {
						continue;
					}
					$dir = \wp_normalize_path($tmp . '/cpts/' . $item);
					if (\is_dir($dir) && \is_file($dir . '/posts.csv')) {
						$dirs[] = $dir;
					}
				}
			}
		}

		$result = cmx_cpt_transfer_import_dirs($dirs, $update_existing, $selected_post_types, $clear_first);
		cmx_cpt_transfer_rm_dir($tmp);
		return $result;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cpt_transfer_views')) {
	function cmx_cpt_transfer_views(array $views): array {
		$post_type = cmx_cpt_transfer_current_post_type();
		if ($post_type === '' || !cmx_cpt_transfer_can()) {
			return $views;
		}
		$export = \wp_nonce_url(\add_query_arg(['action' => 'cmx_cpt_transfer_export', 'post_type' => $post_type], \admin_url('admin-post.php')), 'cmx_cpt_transfer_export');
		$import = \add_query_arg(['post_type' => $post_type, 'cmx_cpt_transfer_import' => 1], \admin_url('edit.php'));
		$links = [
			'exportieren' => '<a href="' . \esc_url($export) . '">exportieren</a>',
			'importieren' => '<a href="' . \esc_url($import) . '">importieren</a>',
		];

		foreach ($links as $label => $link) {
			$found = false;
			foreach ($views as $key => $html) {
				if (\strtolower(\trim(\wp_strip_all_tags((string) $html))) !== $label) {
					continue;
				}
				$views[$key] = $link;
				$found = true;
				break;
			}
			if (!$found) {
				$views['cmx_cpt_transfer_' . $label] = $link;
			}
		}

		return $views;
	}
}

\add_action('admin_init', static function (): void {
	foreach (cmx_cpt_transfer_supported_post_types() as $post_type) {
		\add_filter('views_edit-' . $post_type, __NAMESPACE__ . '\\cmx_cpt_transfer_views', 1000);
	}
}, 99);

\add_action('admin_post_cmx_cpt_transfer_export', function (): void {
	if (!cmx_cpt_transfer_can() || !\check_admin_referer('cmx_cpt_transfer_export')) {
		\wp_die('Keine Berechtigung.');
	}
	$post_type = cmx_cpt_transfer_current_post_type();
	if ($post_type === '') {
		\wp_die('Post Type fehlt.');
	}
	cmx_cpt_transfer_stream_zip($post_type);
	});

	\add_action('admin_post_cmx_cpt_transfer_backup_export', function (): void {
		$can = \function_exists(__NAMESPACE__ . '\\cmx_settings_current_user_can_access')
			? cmx_settings_current_user_can_access()
			: \current_user_can('manage_options');
			if (!$can || !\check_admin_referer('cmx_cpt_transfer_backup_export')) {
				\wp_die('Keine Berechtigung.');
			}
			$target = isset($_POST['backup_target']) ? \sanitize_key((string) \wp_unslash($_POST['backup_target'])) : 'local';
			if ($target === 'local') {
				$saved = cmx_cpt_transfer_save_backup_to_local(cmx_cpt_transfer_requested_post_types());
				\update_user_meta(\get_current_user_id(), 'cmx_cpt_transfer_backup_notice', [
					'type' => !empty($saved['saved']) ? 'success' : 'error',
					'message' => !empty($saved['saved'])
						? 'Backup wurde lokal gespeichert: ' . (string) ($saved['name'] ?? '')
						: ((string) ($saved['error'] ?? '') ?: 'Backup konnte lokal nicht gespeichert werden.'),
				]);
				$page = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG') ? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG') : 'cmx-einstellungen';
				\wp_safe_redirect(\add_query_arg(['page' => $page, 'tab' => 'system', 'sub' => 'backup', 'cmx_backup_notice' => 1], \admin_url('admin.php')));
				exit;
			}
			if ($target === 'webdav') {
				$saved = cmx_cpt_transfer_save_backup_to_webdav(cmx_cpt_transfer_requested_post_types());
				\update_user_meta(\get_current_user_id(), 'cmx_cpt_transfer_backup_notice', [
				'type' => !empty($saved['saved']) ? 'success' : 'error',
				'message' => !empty($saved['saved'])
					? 'Backup wurde in WebDAV gespeichert: ' . (string) ($saved['name'] ?? '')
					: ((string) ($saved['error'] ?? '') ?: 'Backup konnte nicht in WebDAV gespeichert werden.'),
			]);
			$page = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG') ? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG') : 'cmx-einstellungen';
			\wp_safe_redirect(\add_query_arg(['page' => $page, 'tab' => 'system', 'sub' => 'backup', 'cmx_backup_notice' => 1], \admin_url('admin.php')));
			exit;
		}
		cmx_cpt_transfer_stream_backup_zip(cmx_cpt_transfer_requested_post_types());
	});

	\add_action('admin_post_cmx_cpt_transfer_backup_download', function (): void {
		$can = \function_exists(__NAMESPACE__ . '\\cmx_settings_current_user_can_access')
			? cmx_settings_current_user_can_access()
			: \current_user_can('manage_options');
		if (!$can || !\check_admin_referer('cmx_cpt_transfer_backup_download')) {
			\wp_die('Keine Berechtigung.');
		}
		$source = isset($_GET['backup_source']) ? \sanitize_key((string) \wp_unslash($_GET['backup_source'])) : 'local';
		$name = isset($_GET['backup_name']) ? \sanitize_file_name((string) \wp_unslash($_GET['backup_name'])) : '';
		if ($source === 'webdav') {
			$zip_file = cmx_cpt_transfer_webdav_backup_path($name);
			if ($zip_file === '') {
				\wp_die('Bitte ein WebDAV-Backup auswählen.');
			}
			cmx_cpt_transfer_stream_backup_download($zip_file, $name, true);
		}
		if ($source !== 'local') {
			\wp_die('Unbekannte Backup-Quelle.');
		}
		$zip_file = cmx_cpt_transfer_local_backup_path($name);
		if ($zip_file === '') {
			\wp_die('Bitte ein lokales Backup auswählen.');
		}
		cmx_cpt_transfer_stream_backup_download($zip_file, $name, false);
	});

	\add_action('admin_post_cmx_cpt_transfer_backup_upload', function (): void {
		$can = \function_exists(__NAMESPACE__ . '\\cmx_settings_current_user_can_access')
			? cmx_settings_current_user_can_access()
			: \current_user_can('manage_options');
		if (!$can || !\check_admin_referer('cmx_cpt_transfer_backup_upload')) {
			\wp_die('Keine Berechtigung.');
		}

		$file = isset($_FILES['cmx_cpt_transfer_backup_zip']) && \is_array($_FILES['cmx_cpt_transfer_backup_zip'])
			? (array) $_FILES['cmx_cpt_transfer_backup_zip']
			: [];
		$saved = cmx_cpt_transfer_save_uploaded_backup_to_local($file);
		\update_user_meta(\get_current_user_id(), 'cmx_cpt_transfer_backup_notice', [
			'type' => !empty($saved['saved']) ? 'success' : 'error',
			'message' => !empty($saved['saved'])
				? 'Backup wurde hochgeladen: ' . (string) ($saved['name'] ?? '')
				: ((string) ($saved['error'] ?? '') ?: 'Backup konnte nicht hochgeladen werden.'),
		]);
		$page = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG') ? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG') : 'cmx-einstellungen';
		\wp_safe_redirect(\add_query_arg(['page' => $page, 'tab' => 'system', 'sub' => 'backup', 'cmx_backup_notice' => 1, 'cmx_backup_source' => 'local'], \admin_url('admin.php')));
		exit;
	});

	\add_action('admin_post_cmx_cpt_transfer_backup_delete', function (): void {
		$can = \function_exists(__NAMESPACE__ . '\\cmx_settings_current_user_can_access')
			? cmx_settings_current_user_can_access()
			: \current_user_can('manage_options');
		if (!$can || !\check_admin_referer('cmx_cpt_transfer_backup_delete')) {
			\wp_die('Keine Berechtigung.');
		}

		$source = isset($_POST['backup_source']) ? \sanitize_key((string) \wp_unslash($_POST['backup_source'])) : 'local';
		$name = isset($_POST['backup_name']) ? \sanitize_file_name((string) \wp_unslash($_POST['backup_name'])) : '';
		$deleted = $source === 'webdav'
			? cmx_cpt_transfer_delete_webdav_backup($name)
			: cmx_cpt_transfer_delete_local_backup($name);

		\update_user_meta(\get_current_user_id(), 'cmx_cpt_transfer_backup_notice', [
			'type' => !empty($deleted['deleted']) ? 'success' : 'error',
			'message' => !empty($deleted['deleted'])
				? 'Backup wurde gelöscht: ' . (string) ($deleted['name'] ?? '')
				: ((string) ($deleted['error'] ?? '') ?: 'Backup konnte nicht gelöscht werden.'),
		]);
		$page = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG') ? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG') : 'cmx-einstellungen';
		\wp_safe_redirect(\add_query_arg(['page' => $page, 'tab' => 'system', 'sub' => 'backup', 'cmx_backup_notice' => 1, 'cmx_backup_source' => $source === 'webdav' ? 'webdav' : 'local'], \admin_url('admin.php')));
		exit;
	});

	\add_action('all_admin_notices', function (): void {
		$post_type = cmx_cpt_transfer_current_post_type();
		if ($post_type === '' || empty($_GET['cmx_cpt_transfer_import']) || !cmx_cpt_transfer_can()) {
			return;
	}
	?>
	<div class="notice notice-info" style="padding:18px;margin-top:15px;">
			<h2>Modul-Import: <?php echo \esc_html($post_type); ?></h2>
		<form method="post" enctype="multipart/form-data">
			<?php \wp_nonce_field('cmx_cpt_transfer_import'); ?>
			<input type="hidden" name="cmx_cpt_transfer_do_import" value="1">
			<p><input type="file" name="cmx_cpt_transfer_zip" accept=".zip" required></p>
			<p><label><input type="checkbox" name="update_existing" value="1"> Existierende Beiträge mit gleichem Slug aktualisieren</label></p>
			<p><button type="submit" class="button button-primary">Import starten</button></p>
		</form>
	</div>
	<?php
});

\add_action('load-edit.php', function (): void {
	if (empty($_POST['cmx_cpt_transfer_do_import']) || !\check_admin_referer('cmx_cpt_transfer_import')) {
		return;
	}
	if (!cmx_cpt_transfer_can() || empty($_FILES['cmx_cpt_transfer_zip']['tmp_name'])) {
		return;
	}
	$result = cmx_cpt_transfer_import_zip((string) $_FILES['cmx_cpt_transfer_zip']['tmp_name'], !empty($_POST['update_existing']));
	\update_user_meta(\get_current_user_id(), 'cmx_cpt_transfer_notice', $result);
	$post_type = cmx_cpt_transfer_current_post_type();
		\wp_safe_redirect(\add_query_arg(['post_type' => $post_type, 'cmx_cpt_transfer_notice' => 1], \admin_url('edit.php')));
		exit;
	});

	\add_action('admin_post_cmx_cpt_transfer_backup_import', function (): void {
		$can = \function_exists(__NAMESPACE__ . '\\cmx_settings_current_user_can_access')
			? cmx_settings_current_user_can_access()
			: \current_user_can('manage_options');
		if (!$can || !\check_admin_referer('cmx_cpt_transfer_backup_import')) {
			\wp_die('Keine Berechtigung.');
			}
			$source = isset($_POST['backup_source']) ? \sanitize_key((string) \wp_unslash($_POST['backup_source'])) : 'local';
			$import_all = !empty($_POST['import_all_modules']);
			$selected_post_types = $import_all ? [] : cmx_cpt_transfer_requested_post_types();
			if (!$import_all && $selected_post_types === []) {
				\wp_die('Bitte mindestens ein Modul für den Import auswählen.');
			}
			$import_mode = isset($_POST['import_mode']) ? \sanitize_key((string) \wp_unslash($_POST['import_mode'])) : 'update';
			$clear_first = $import_mode === 'clear';
			$update_existing = $import_mode === 'update';
			$zip_file = '';
			if ($source === 'webdav') {
				$name = isset($_POST['webdav_backup']) ? \sanitize_file_name((string) \wp_unslash($_POST['webdav_backup'])) : '';
				$zip_file = cmx_cpt_transfer_webdav_backup_path($name);
				if ($zip_file === '') {
					\wp_die('Bitte ein WebDAV-Backup auswählen.');
				}
			} elseif ($source === 'local') {
				$name = isset($_POST['local_backup']) ? \sanitize_file_name((string) \wp_unslash($_POST['local_backup'])) : '';
				$zip_file = cmx_cpt_transfer_local_backup_path($name);
				if ($zip_file === '') {
					\wp_die('Bitte ein lokales Backup auswählen.');
				}
			} else {
				if (empty($_FILES['cmx_cpt_transfer_zip']['tmp_name'])) {
					\wp_die('Bitte eine ZIP-Datei auswählen.');
				}
				$zip_file = (string) $_FILES['cmx_cpt_transfer_zip']['tmp_name'];
			}
			$result = cmx_cpt_transfer_import_zip($zip_file, $update_existing, $selected_post_types, $clear_first);
			if ($source === 'webdav') {
				@\unlink($zip_file);
			}
		\update_user_meta(\get_current_user_id(), 'cmx_cpt_transfer_notice', $result);
		$page = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG') ? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG') : 'cmx-einstellungen';
		\wp_safe_redirect(\add_query_arg(['page' => $page, 'tab' => 'system', 'sub' => 'backup', 'cmx_cpt_transfer_notice' => 1], \admin_url('admin.php')));
		exit;
	});

	\add_action('admin_post_cmx_cpt_transfer_backup_settings', function (): void {
		$can = \function_exists(__NAMESPACE__ . '\\cmx_settings_current_user_can_access')
			? cmx_settings_current_user_can_access()
			: \current_user_can('manage_options');
		if (!$can || !\check_admin_referer('cmx_cpt_transfer_backup_settings')) {
			\wp_die('Keine Berechtigung.');
		}
		$option = cmx_cpt_transfer_backup_settings_option();
		$options = (array) \get_option($option, []);
		$options['backup_webdav_url'] = isset($_POST['backup_webdav_url'])
			? \esc_url_raw(\trim((string) \wp_unslash($_POST['backup_webdav_url'])))
			: '';
		$options['backup_webdav_user'] = isset($_POST['backup_webdav_user'])
			? \sanitize_text_field((string) \wp_unslash($_POST['backup_webdav_user']))
			: '';
		if (!empty($_POST['backup_webdav_password'])) {
			$options['backup_webdav_password'] = (string) \wp_unslash($_POST['backup_webdav_password']);
		}
		if (!empty($_POST['backup_webdav_clear_password'])) {
			$options['backup_webdav_password'] = '';
		}
		$path = isset($_POST['backup_webdav_path'])
			? \trim(\sanitize_text_field((string) \wp_unslash($_POST['backup_webdav_path'])), " \t\n\r\0\x0B/")
			: cmx_cpt_transfer_backup_webdav_rel_dir();
		$options['backup_webdav_path'] = $path !== '' ? $path : cmx_cpt_transfer_backup_webdav_rel_dir();
		\update_option($option, $options, false);
		\update_user_meta(\get_current_user_id(), 'cmx_cpt_transfer_backup_notice', [
			'type' => 'success',
			'message' => 'WebDAV-Backup-Einstellungen gespeichert.',
		]);
		$page = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG') ? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG') : 'cmx-einstellungen';
		\wp_safe_redirect(\add_query_arg(['page' => $page, 'tab' => 'system', 'sub' => 'backup', 'cmx_backup_notice' => 1], \admin_url('admin.php')));
		exit;
	});

			\add_action('all_admin_notices', function (): void {
			if (empty($_GET['cmx_cpt_transfer_notice'])) {
				return;
		}
		$result = (array) \get_user_meta(\get_current_user_id(), 'cmx_cpt_transfer_notice', true);
		\delete_user_meta(\get_current_user_id(), 'cmx_cpt_transfer_notice');
		$notice_class = ((int) ($result['unresolved_refs'] ?? 0) > 0) ? 'notice-warning' : 'notice-success';
			echo '<div class="notice ' . \esc_attr($notice_class) . ' is-dismissible"><p>Modul-Import abgeschlossen: '
				. \esc_html((string) ($result['imported'] ?? 0)) . ' importiert, '
				. \esc_html((string) ($result['updated'] ?? 0)) . ' aktualisiert, '
				. \esc_html((string) ($result['deleted'] ?? 0)) . ' zuvor gelöscht, '
				. \esc_html((string) ($result['files'] ?? 0)) . ' Dateien kopiert, '
			. \esc_html((string) ($result['refs'] ?? 0)) . ' Referenzen zugeordnet, '
			. \esc_html((string) ($result['unresolved_refs'] ?? 0)) . ' Referenzen nicht gefunden.</p>';
		$details = \array_values(\array_filter((array) ($result['unresolved_ref_details'] ?? []), 'is_array'));
		if ($details !== []) {
			echo '<p><strong>Nicht gefundene Referenzen:</strong></p><ul style="margin:0 0 .5em 1.2em;list-style:disc;">';
			foreach ($details as $detail) {
				$meta = (string) ($detail['meta_key'] ?? '');
				$path = (string) ($detail['meta_path'] ?? '');
				if ($path !== '') {
					$meta .= '/' . $path;
				}
				$post_label = '#' . (string) ($detail['post_id'] ?? '') . ' ' . (string) ($detail['post_title'] ?? '');
				$target_label = (string) ($detail['target_type'] ?? '') . ' #' . (string) ($detail['old_target_id'] ?? '');
				$target_title = (string) ($detail['target_title'] ?? '');
				if ($target_title !== '') {
					$target_label .= ' "' . $target_title . '"';
				}
				echo '<li>'
					. \esc_html($post_label)
					. ': '
					. \esc_html($meta)
					. ' -> '
					. \esc_html($target_label)
					. '</li>';
			}
			if ((int) ($result['unresolved_refs'] ?? 0) > \count($details)) {
				echo '<li>' . \esc_html((string) ((int) ($result['unresolved_refs'] ?? 0) - \count($details))) . ' weitere nicht angezeigt.</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	});

\add_action('admin_footer-edit.php', function (): void {
	$post_type = cmx_cpt_transfer_current_post_type();
	if ($post_type === '') return;
	$action = \esc_js(\admin_url('admin-post.php'));
	$nonce = \esc_js(\wp_create_nonce('cmx_cpt_transfer_export'));
	?>
	<script>
	document.addEventListener('click', function(event){
		const link = event.target && event.target.closest ? event.target.closest('a[href*="action=cmx_cpt_transfer_export"]') : null;
		if (!link) return;
		event.preventDefault();
		const form = document.createElement('form');
		form.method = 'POST';
		form.action = '<?php echo $action; ?>';
		const add = function(name, value){ const input=document.createElement('input'); input.type='hidden'; input.name=name; input.value=value; form.appendChild(input); };
		add('action', 'cmx_cpt_transfer_export');
		add('_wpnonce', '<?php echo $nonce; ?>');
		add('post_type', '<?php echo \esc_js($post_type); ?>');
		const skip = {action:1, action2:1, _wpnonce:1, _wp_http_referer:1, cmx_cpt_transfer_do_import:1};
		const params = new URLSearchParams(window.location.search);
		params.forEach(function(value, key){
			if (!key || skip[key] || key === 'post_type') return;
			add(key, value);
		});
		const table = document.getElementById('posts-filter');
		if (table) {
			const data = new FormData(table);
			for (const pair of data.entries()) {
				if (!pair[0] || skip[pair[0]]) continue;
				add(pair[0], pair[1]);
			}
		}
		document.body.appendChild(form);
		form.submit();
	});
	</script>
	<?php
});
