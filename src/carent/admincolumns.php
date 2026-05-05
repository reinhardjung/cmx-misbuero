<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_insert_columns_after')) {
	function cmx_carent_admin_insert_columns_after(array $columns, string $after_key, array $new_columns): array {
		if (empty($new_columns)) {
			return $columns;
		}

		$reordered = [];
		$inserted = false;

		foreach ($columns as $key => $label) {
			$reordered[$key] = $label;
			if ($key === $after_key) {
				foreach ($new_columns as $new_key => $new_label) {
					$reordered[$new_key] = $new_label;
				}
				$inserted = true;
			}
		}

		if (!$inserted) {
			foreach ($new_columns as $new_key => $new_label) {
				$reordered[$new_key] = $new_label;
			}
		}

		return $reordered;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_linked_article_id')) {
	function cmx_carent_admin_linked_article_id(int $post_id): int {
		$meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_META')
			: '_cmx_carent_fahrzeug_id';

		return (int) \get_post_meta($post_id, $meta_key, true);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_linked_contact_id')) {
	function cmx_carent_admin_linked_contact_id(int $post_id): int {
		$meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_KONTAKT_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_KONTAKT_META')
			: '_cmx_carent_kontakt_id';

		return (int) \get_post_meta($post_id, $meta_key, true);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_article_label')) {
	function cmx_carent_admin_article_label(int $artikel_id, int $carent_id = 0): string {
		if ($artikel_id <= 0 || !\get_post_status($artikel_id)) {
			return '';
		}

		$variant_index = null;
		if ($carent_id > 0) {
			$variant_index = \get_post_meta($carent_id, \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_VARIANT_INDEX_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_VARIANT_INDEX_META')
				: '_cmx_carent_fahrzeug_variant_index', true);
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_selection_label')) {
			$label = (string) cmx_carent_fahrzeug_selection_label($artikel_id, $variant_index);
			if ($label !== '') {
				return $label;
			}
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_display_label')) {
			return (string) cmx_carent_fahrzeug_display_label($artikel_id);
		}

		$title = (string) \get_the_title($artikel_id);
		return \trim($title);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_contact_label')) {
	function cmx_carent_admin_contact_label(int $kontakt_id): string {
		if ($kontakt_id <= 0 || !\get_post_status($kontakt_id)) {
			return '';
		}

		$title = (string) \get_the_title($kontakt_id);
		if (\function_exists(__NAMESPACE__ . '\\cmx_normalize_minus_sign')) {
			$title = (string) cmx_normalize_minus_sign($title);
		}

		return \trim($title);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_kennzeichen_label')) {
	function cmx_carent_admin_kennzeichen_label(int $post_id): string {
		$saved_meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KENNZEICHEN_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KENNZEICHEN_META')
			: '_cmx_carent_fahrzeug_kennzeichen';
		$saved = \trim((string) \get_post_meta($post_id, $saved_meta_key, true));
		if ($saved !== '') {
			return $saved;
		}

		$artikel_id = cmx_carent_admin_linked_article_id($post_id);
		if ($artikel_id <= 0 || !\get_post_status($artikel_id)) {
			return '';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_article_meta_defaults')) {
			$defaults = (array) cmx_carent_fahrzeug_article_meta_defaults($artikel_id);
			return \trim((string) ($defaults['kennzeichen'] ?? ''));
		}

		$meta_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KENNZEICHEN')
			? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KENNZEICHEN')
			: '_cmx_artikel_carent_kennzeichen';

		return \trim((string) \get_post_meta($artikel_id, $meta_key, true));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_date_value')) {
	function cmx_carent_admin_date_value(int $post_id, string $meta_key): string {
		$value = \trim((string) \get_post_meta($post_id, $meta_key, true));
		if ($value === '') {
			return '';
		}

		if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
			$timestamp = \strtotime($value);
			if ($timestamp !== false) {
				return (string) \date_i18n('d.m.Y', $timestamp);
			}
		}

		return $value;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_search_normalize')) {
	function cmx_carent_admin_search_normalize(string $value): string {
		$value = \trim($value);
		if ($value === '') {
			return '';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_normalize_minus_sign')) {
			$value = (string) cmx_normalize_minus_sign($value);
		}

		$value = \preg_replace('/\s+/u', ' ', $value);
		$value = \is_string($value) ? $value : '';

		return \function_exists('mb_strtolower')
			? \mb_strtolower($value, 'UTF-8')
			: \strtolower($value);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_search_compact')) {
	function cmx_carent_admin_search_compact(string $value): string {
		$value = cmx_carent_admin_search_normalize($value);
		if ($value === '') {
			return '';
		}

		$compact = \preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
		return \is_string($compact) ? $compact : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_search_terms')) {
	function cmx_carent_admin_search_terms(\WP_Query $query): array {
		$terms = $query->query_vars['search_terms'] ?? [];
		if (\is_array($terms) && $terms !== []) {
			return \array_values(\array_filter(\array_map(static function ($value): string {
				return \trim((string) $value);
			}, $terms), static fn(string $value): bool => $value !== ''));
		}

		$raw = \trim((string) $query->get('s'));
		return $raw !== '' ? [$raw] : [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_search_haystack')) {
	function cmx_carent_admin_search_haystack(int $post_id): string {
		if ($post_id <= 0 || !\get_post_status($post_id)) {
			return '';
		}

		$parts = [];
		$post_title = \trim((string) \get_the_title($post_id));
		$display_title = \function_exists(__NAMESPACE__ . '\\cmx_carent_display_title')
			? \trim((string) cmx_carent_display_title($post_id))
			: $post_title;
		$nummer = \trim((string) \get_post_meta($post_id, '_cmx_carent_nummer', true));

		foreach ([$post_title, $display_title, $nummer, (string) $post_id] as $part) {
			$part = \trim((string) $part);
			if ($part !== '') {
				$parts[] = $part;
			}
		}

		$post_date = \trim((string) \get_the_date('Y-m-d', $post_id));
		if ($post_date !== '') {
			$parts[] = $post_date;
			$timestamp = \strtotime($post_date);
			if ($timestamp !== false) {
				$parts[] = (string) \date_i18n('d.m.Y', $timestamp);
				$parts[] = (string) \date_i18n('d.m.', $timestamp);
			}
		}

		$date_meta_keys = [
			\defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_DATUM_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_DATUM_META')
				: '_cmx_carent_uebernahme_datum',
			\defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_DATUM_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_DATUM_META')
				: '_cmx_carent_rueckgabe_datum',
			\defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_DATUM_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_DATUM_META')
				: '_cmx_carent_schadenprotokoll_datum',
		];
		foreach (\array_values(\array_unique(\array_filter($date_meta_keys))) as $date_meta_key) {
			$date_value = \trim((string) \get_post_meta($post_id, (string) $date_meta_key, true));
			if ($date_value === '') {
				continue;
			}
			$parts[] = $date_value;
			if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_value)) {
				$timestamp = \strtotime($date_value);
				if ($timestamp !== false) {
					$parts[] = (string) \date_i18n('d.m.Y', $timestamp);
					$parts[] = (string) \date_i18n('d.m.', $timestamp);
				}
			}
		}

		$kontakt_id = cmx_carent_admin_linked_contact_id($post_id);
		if ($kontakt_id > 0 && \get_post_status($kontakt_id)) {
			$parts[] = cmx_carent_admin_contact_label($kontakt_id);

			if (\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_contact_row')) {
				$row = (array) cmx_telefonbuch_contact_row($kontakt_id);
				foreach (['title', 'subtitle', 'search'] as $field) {
					$value = \trim((string) ($row[$field] ?? ''));
					if ($value !== '') {
						$parts[] = $value;
					}
				}
			}

			if (\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_email')) {
				$email = \sanitize_email((string) cmx_kommunikation_primary_email($kontakt_id));
				if ($email !== '') {
					$parts[] = $email;
				}
			}

			if (\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_phone')) {
				$phone = \trim((string) cmx_kommunikation_primary_phone($kontakt_id));
				if ($phone !== '') {
					$parts[] = $phone;
				}
			}
		}

		$artikel_id = cmx_carent_admin_linked_article_id($post_id);
		if ($artikel_id > 0 && \get_post_status($artikel_id)) {
			$artikel_label = cmx_carent_admin_article_label($artikel_id, $post_id);
			$artikel_title = \trim((string) \get_the_title($artikel_id));
			$artikel_nr = \function_exists(__NAMESPACE__ . '\\cmx_get_artikel_nr')
				? \trim((string) cmx_get_artikel_nr($artikel_id))
				: '';
			$kennzeichen = cmx_carent_admin_kennzeichen_label($post_id);

			foreach ([$artikel_label, $artikel_title, $artikel_nr, $kennzeichen] as $part) {
				$part = \trim((string) $part);
				if ($part !== '') {
					$parts[] = $part;
				}
			}
		}

		return \implode(' ', \array_values(\array_filter($parts, static fn(string $value): bool => \trim($value) !== '')));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_search_matches_terms')) {
	function cmx_carent_admin_search_matches_terms(string $haystack, array $terms): bool {
		$haystack = cmx_carent_admin_search_normalize($haystack);
		if ($haystack === '') {
			return false;
		}

		$haystack_compact = cmx_carent_admin_search_compact($haystack);
		foreach ($terms as $term) {
			$term = cmx_carent_admin_search_normalize((string) $term);
			if ($term === '') {
				continue;
			}

			if (\strpos($haystack, $term) !== false) {
				continue;
			}

			$term_compact = cmx_carent_admin_search_compact($term);
			if ($term_compact !== '' && $haystack_compact !== '' && \strpos($haystack_compact, $term_compact) !== false) {
				continue;
			}

			return false;
		}

		return true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_is_search_query')) {
	function cmx_carent_admin_is_search_query(\WP_Query $query): bool {
		if (!\is_admin() || !$query->is_main_query() || (bool) $query->get('cmx_carent_admin_search_index_query')) {
			return false;
		}

		$post_type = $query->get('post_type');
		if (\is_array($post_type)) {
			$post_type = (string) ($post_type[0] ?? '');
		}

		return (string) $post_type === 'carent' && \trim((string) $query->get('s')) !== '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_search_matching_ids')) {
	function cmx_carent_admin_search_matching_ids(\WP_Query $query): array {
		$terms = cmx_carent_admin_search_terms($query);
		if ($terms === []) {
			return [];
		}

		$carent_ids = \get_posts([
			'post_type'              => 'carent',
			'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
			'cmx_carent_admin_search_index_query' => true,
		]);

		$matched_ids = [];
		foreach ((array) $carent_ids as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0) {
				continue;
			}

			$haystack = cmx_carent_admin_search_haystack($post_id);
			if (cmx_carent_admin_search_matches_terms($haystack, $terms)) {
				$matched_ids[] = $post_id;
			}
		}

		return \array_values(\array_unique(\array_filter(\array_map('intval', $matched_ids))));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_edit_link')) {
	function cmx_carent_admin_edit_link(int $post_id, string $label): string {
		if ($post_id <= 0 || $label === '' || !\get_post_status($post_id)) {
			return '<span aria-hidden="true"></span>';
		}

		$url = (string) \admin_url('post.php?post=' . $post_id . '&action=edit');

		return '<a href="' . \esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . \esc_html($label) . '</a>';
	}
}

\add_filter('manage_carent_posts_columns', function (array $columns): array {
	$new_columns = [
		'cmx_carent_artikel'     => \__('Artikel', 'cmx-misbuero'),
		'cmx_carent_kontakt'     => \__('Kontakt', 'cmx-misbuero'),
		'cmx_carent_kennzeichen' => \__('Kennzeichen', 'cmx-misbuero'),
		'cmx_carent_uebernahme'  => \__('Übernahme', 'cmx-misbuero'),
		'cmx_carent_rueckgabe'   => \__('Rückgabe', 'cmx-misbuero'),
	];

	return cmx_carent_admin_insert_columns_after($columns, 'title', $new_columns);
}, 20);

\add_action('manage_carent_posts_custom_column', function (string $column, int $post_id): void {
	if ($column === 'cmx_carent_artikel') {
		$artikel_id = cmx_carent_admin_linked_article_id($post_id);
		$label = cmx_carent_admin_article_label($artikel_id, $post_id);
		echo cmx_carent_admin_edit_link($artikel_id, $label); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}

	if ($column === 'cmx_carent_kontakt') {
		$kontakt_id = cmx_carent_admin_linked_contact_id($post_id);
		$label = cmx_carent_admin_contact_label($kontakt_id);
		echo cmx_carent_admin_edit_link($kontakt_id, $label); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}

	if ($column === 'cmx_carent_kennzeichen') {
		$label = cmx_carent_admin_kennzeichen_label($post_id);
		echo $label !== '' ? \esc_html($label) : '<span aria-hidden="true"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}

	if ($column === 'cmx_carent_uebernahme') {
		$meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_DATUM_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_DATUM_META')
			: '_cmx_carent_uebernahme_datum';
		$label = cmx_carent_admin_date_value($post_id, $meta_key);
		echo $label !== '' ? \esc_html($label) : '<span aria-hidden="true"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}

	if ($column === 'cmx_carent_rueckgabe') {
		$meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_DATUM_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_DATUM_META')
			: '_cmx_carent_rueckgabe_datum';
		$label = cmx_carent_admin_date_value($post_id, $meta_key);
		echo $label !== '' ? \esc_html($label) : '<span aria-hidden="true"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}, 10, 2);

\add_action('admin_head-edit.php', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== 'carent') {
		return;
	}

	echo '<style>
		.wp-list-table .column-cmx_carent_artikel{width:28%;white-space:nowrap}
		.wp-list-table .column-cmx_carent_kontakt{width:24%;white-space:nowrap}
		.wp-list-table .column-cmx_carent_kennzeichen{width:110px;white-space:nowrap}
		.wp-list-table .column-cmx_carent_uebernahme{width:100px;white-space:nowrap}
		.wp-list-table .column-cmx_carent_rueckgabe{width:100px;white-space:nowrap}
	</style>';
});

\add_action('pre_get_posts', function (\WP_Query $query): void {
	if (!cmx_carent_admin_is_search_query($query)) {
		return;
	}

	$matching_ids = cmx_carent_admin_search_matching_ids($query);
	$query->set('cmx_carent_admin_custom_search', true);
	$query->set('post__in', $matching_ids !== [] ? $matching_ids : [0]);
});

\add_filter('posts_search', function (string $search, \WP_Query $query): string {
	if (!(bool) $query->get('cmx_carent_admin_custom_search')) {
		return $search;
	}

	return '';
}, 10, 2);
