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

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_insert_views_after')) {
	function cmx_carent_admin_insert_views_after(array $views, string $after_key, array $new_views): array {
		if (empty($new_views)) {
			return $views;
		}

		$reordered = [];
		$inserted = false;

		foreach ($views as $key => $html) {
			$reordered[$key] = $html;
			if ($key === $after_key) {
				foreach ($new_views as $new_key => $new_html) {
					$reordered[$new_key] = $new_html;
				}
				$inserted = true;
			}
		}

		if (!$inserted) {
			foreach ($new_views as $new_key => $new_html) {
				$reordered[$new_key] = $new_html;
			}
		}

		return $reordered;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_external_view_link')) {
	function cmx_carent_admin_external_view_link(string $url, string $label): string {
		if ($url === '') {
			return '';
		}

		return '<a href="' . \esc_url($url) . '">' . \esc_html($label) . '</a>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_linked_article_id')) {
	function cmx_carent_admin_linked_article_id(int $post_id): int {
		static $cache = [];
		if (isset($cache[$post_id])) {
			return (int) $cache[$post_id];
		}

		$meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_META')
			: '_cmx_carent_fahrzeug_id';

		$cache[$post_id] = (int) \get_post_meta($post_id, $meta_key, true);
		return (int) $cache[$post_id];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_linked_contact_id')) {
	function cmx_carent_admin_linked_contact_id(int $post_id): int {
		static $cache = [];
		if (isset($cache[$post_id])) {
			return (int) $cache[$post_id];
		}

		$meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_KONTAKT_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_KONTAKT_META')
			: '_cmx_carent_kontakt_id';

		$cache[$post_id] = (int) \get_post_meta($post_id, $meta_key, true);
		return (int) $cache[$post_id];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_article_label')) {
	function cmx_carent_admin_article_label(int $artikel_id, int $carent_id = 0): string {
		static $cache = [];
		if ($artikel_id <= 0 || !\get_post_status($artikel_id)) {
			return '';
		}

		$variant_index = null;
		if ($carent_id > 0) {
			$variant_index = \get_post_meta($carent_id, \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_VARIANT_INDEX_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_VARIANT_INDEX_META')
				: '_cmx_carent_fahrzeug_variant_index', true);
		}

		$cache_key = $artikel_id . ':' . (string) $variant_index;
		if (isset($cache[$cache_key])) {
			return (string) $cache[$cache_key];
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_selection_label')) {
			$label = (string) cmx_carent_fahrzeug_selection_label($artikel_id, $variant_index);
			if ($label !== '') {
				$cache[$cache_key] = $label;
				return $label;
			}
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_display_label')) {
			$cache[$cache_key] = (string) cmx_carent_fahrzeug_display_label($artikel_id);
			return (string) $cache[$cache_key];
		}

		$title = (string) \get_the_title($artikel_id);
		$cache[$cache_key] = \trim($title);
		return (string) $cache[$cache_key];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_contact_label')) {
	function cmx_carent_admin_contact_label(int $kontakt_id): string {
		static $cache = [];
		if (isset($cache[$kontakt_id])) {
			return (string) $cache[$kontakt_id];
		}

		if ($kontakt_id <= 0 || !\get_post_status($kontakt_id)) {
			return '';
		}

		$title = (string) \get_the_title($kontakt_id);
		if (\function_exists(__NAMESPACE__ . '\\cmx_normalize_minus_sign')) {
			$title = (string) cmx_normalize_minus_sign($title);
		}

		$cache[$kontakt_id] = \trim($title);
		return (string) $cache[$kontakt_id];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_kennzeichen_label')) {
	function cmx_carent_admin_kennzeichen_label(int $post_id): string {
		static $cache = [];
		if (isset($cache[$post_id])) {
			return (string) $cache[$post_id];
		}

		$saved_meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KENNZEICHEN_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_KENNZEICHEN_META')
			: '_cmx_carent_fahrzeug_kennzeichen';
		$saved = \trim((string) \get_post_meta($post_id, $saved_meta_key, true));
		if ($saved !== '') {
			$cache[$post_id] = $saved;
			return $saved;
		}

		$artikel_id = cmx_carent_admin_linked_article_id($post_id);
		if ($artikel_id <= 0 || !\get_post_status($artikel_id)) {
			return '';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_carent_fahrzeug_article_meta_defaults')) {
			$defaults = (array) cmx_carent_fahrzeug_article_meta_defaults($artikel_id);
			$cache[$post_id] = \trim((string) ($defaults['kennzeichen'] ?? ''));
			return (string) $cache[$post_id];
		}

		$meta_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KENNZEICHEN')
			? (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KENNZEICHEN')
			: '_cmx_artikel_carent_kennzeichen';

		$cache[$post_id] = \trim((string) \get_post_meta($artikel_id, $meta_key, true));
		return (string) $cache[$post_id];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_status_meta_key')) {
	function cmx_carent_admin_status_meta_key(): string {
		return \defined(__NAMESPACE__ . '\\CMX_CARENT_STATUS_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_STATUS_META')
			: '_cmx_carent_status';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_status_options')) {
	function cmx_carent_admin_status_options(): array {
		if (\function_exists(__NAMESPACE__ . '\\cmx_carent_status_options')) {
			$options = (array) cmx_carent_status_options();
			if ($options !== []) {
				return $options;
			}
		}

		return [
			'offen'         => 'offen',
			'abgeschlossen' => 'abgeschlossen',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_status_value')) {
	function cmx_carent_admin_status_value(int $post_id): string {
		static $cache = [];
		if (isset($cache[$post_id])) {
			return (string) $cache[$post_id];
		}

		$value = \sanitize_key((string) \get_post_meta($post_id, cmx_carent_admin_status_meta_key(), true));
		$options = cmx_carent_admin_status_options();

		$cache[$post_id] = isset($options[$value]) ? $value : 'offen';
		return (string) $cache[$post_id];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_status_label')) {
	function cmx_carent_admin_status_label(string $value): string {
		$options = cmx_carent_admin_status_options();
		return (string) ($options[$value] ?? ($value !== '' ? \ucfirst($value) : 'offen'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_date_value')) {
	function cmx_carent_admin_date_value(int $post_id, string $meta_key): string {
		static $cache = [];
		$cache_key = $post_id . ':' . $meta_key;
		if (isset($cache[$cache_key])) {
			return (string) $cache[$cache_key];
		}

		$value = \trim((string) \get_post_meta($post_id, $meta_key, true));
		if ($value === '') {
			return '';
		}

		if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
			$timestamp = \strtotime($value);
			if ($timestamp !== false) {
				$cache[$cache_key] = (string) \date_i18n('d.m.Y', $timestamp);
				return (string) $cache[$cache_key];
			}
		}

		$cache[$cache_key] = $value;
		return (string) $cache[$cache_key];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_filter_date_value')) {
	function cmx_carent_admin_filter_date_value(): string {
		$value = isset($_GET['cmx_carent_stichtag']) ? \trim((string) \wp_unslash($_GET['cmx_carent_stichtag'])) : '';
		if ($value === '') {
			return '';
		}

		if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
			return $value;
		}

		if (\preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2}|\d{4})$/', $value, $matches) === 1) {
			$day = (int) ($matches[1] ?? 0);
			$month = (int) ($matches[2] ?? 0);
			$year = (int) ($matches[3] ?? 0);
			if ($year > 0 && $year < 100) {
				$year += 2000;
			}
			if (\checkdate($month, $day, $year)) {
				return \sprintf('%04d-%02d-%02d', $year, $month, $day);
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_used_article_ids')) {
	function cmx_carent_admin_used_article_ids(): array {
		static $cache = null;
		if (\is_array($cache)) {
			return $cache;
		}

		global $wpdb;

		$meta_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_META')
			: '_cmx_carent_fahrzeug_id';
		$cache_key = 'cmx_carent_admin_used_article_ids_' . \md5($meta_key);
		$cached = \wp_cache_get($cache_key, 'cmx_carent');
		if (\is_array($cached)) {
			$cache = \array_values(\array_filter(\array_map('intval', $cached)));
			return $cache;
		}

		$ids = (array) $wpdb->get_col($wpdb->prepare(
			"SELECT DISTINCT CAST(pm.meta_value AS UNSIGNED)
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE p.post_type = 'carent'
				AND p.post_status NOT IN ('trash', 'auto-draft')
				AND pm.meta_key = %s
				AND pm.meta_value REGEXP '^[0-9]+$'
				AND CAST(pm.meta_value AS UNSIGNED) > 0
			ORDER BY CAST(pm.meta_value AS UNSIGNED) ASC",
			$meta_key
		));

		$cache = \array_values(\array_unique(\array_filter(\array_map('intval', $ids))));
		\wp_cache_set($cache_key, $cache, 'cmx_carent', 300);
		return $cache;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_admin_prime_list_caches')) {
	function cmx_carent_admin_prime_list_caches(array $posts): void {
		$carent_ids = [];
		foreach ($posts as $post) {
			if ($post instanceof \WP_Post && (string) $post->post_type === 'carent') {
				$carent_ids[] = (int) $post->ID;
			}
		}
		$carent_ids = \array_values(\array_unique(\array_filter($carent_ids)));
		if ($carent_ids === []) {
			return;
		}

		\update_meta_cache('post', $carent_ids);

		$article_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_META')
			: '_cmx_carent_fahrzeug_id';
		$contact_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_KONTAKT_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_KONTAKT_META')
			: '_cmx_carent_kontakt_id';

		$related_ids = [];
		foreach ($carent_ids as $post_id) {
			$article_id = (int) \get_post_meta($post_id, $article_key, true);
			$contact_id = (int) \get_post_meta($post_id, $contact_key, true);
			if ($article_id > 0) {
				$related_ids[] = $article_id;
			}
			if ($contact_id > 0) {
				$related_ids[] = $contact_id;
			}
		}

		$related_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $related_ids))));
		if ($related_ids === []) {
			return;
		}

		if (\function_exists('_prime_post_caches')) {
			\_prime_post_caches($related_ids, false, true);
		} else {
			\update_meta_cache('post', $related_ids);
		}
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

\add_action('admin_menu', function (): void {
	\remove_submenu_page('edit.php?post_type=carent', 'cmx-carent-dashboard');
	\remove_submenu_page('edit.php?post_type=carent', 'cmx-carent-vermietung');
}, 99);

\add_action('current_screen', function ($screen): void {
	if (!$screen || (string) ($screen->id ?? '') !== 'carent_page_cmx-carent-dashboard') {
		return;
	}

	global $title;
	$title = \__('CaRent Übersicht', 'cmx-misbuero');
});

\add_filter('views_edit-carent', function (array $views): array {
	$new_views = [];

	$dashboard_url = (string) \admin_url('edit.php?post_type=carent&page=cmx-carent-dashboard');
	$dashboard_cap = \function_exists(__NAMESPACE__ . '\\cmx_carent_dashboard_capability')
		? (string) cmx_carent_dashboard_capability()
		: 'edit_posts';
	if (\current_user_can($dashboard_cap)) {
		$new_views['cmx_carent_dashboard'] = cmx_carent_admin_external_view_link($dashboard_url, \__('Übersicht', 'cmx-misbuero'));
	}

	$vermietung_url = \function_exists(__NAMESPACE__ . '\\cmx_vermietung_url')
		? (string) cmx_vermietung_url()
		: '';
	if ($vermietung_url !== '' && \current_user_can('edit_posts')) {
		$new_views['cmx_carent_vermietung'] = cmx_carent_admin_external_view_link($vermietung_url, \__('Vermietung', 'cmx-misbuero'));
	}

	return cmx_carent_admin_insert_views_after($views, 'trash', $new_views);
}, 20);

\add_filter('the_posts', function (array $posts, \WP_Query $query): array {
	if (!\is_admin() || !$query->is_main_query()) {
		return $posts;
	}

	$post_type = $query->get('post_type');
	if (\is_array($post_type)) {
		$post_type = (string) ($post_type[0] ?? '');
	}
	if ((string) $post_type !== 'carent') {
		return $posts;
	}

	cmx_carent_admin_prime_list_caches($posts);
	return $posts;
}, 10, 2);

\add_filter('manage_carent_posts_columns', function (array $columns): array {
	$new_columns = [
		'cmx_carent_artikel'     => \__('Artikel', 'cmx-misbuero'),
		'cmx_carent_kontakt'     => \__('Kontakt', 'cmx-misbuero'),
		'cmx_carent_status'      => \__('Status', 'cmx-misbuero'),
		'cmx_carent_kennzeichen' => \__('Kennzeichen', 'cmx-misbuero'),
		'cmx_carent_uebernahme'  => \__('Übernahme', 'cmx-misbuero'),
		'cmx_carent_rueckgabe'   => \__('Rückgabe', 'cmx-misbuero'),
	];

	$columns = cmx_carent_admin_insert_columns_after($columns, 'title', $new_columns);
	$columns['cmx_carent_vertrag'] = \__('Vertrag', 'cmx-misbuero');

	return $columns;
}, 20);

\add_action('manage_carent_posts_custom_column', function (string $column, int $post_id): void {
	if ($column === 'cmx_carent_vertrag') {
		$live_icon = \function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_live_icon_link')
			? (string) cmx_carent_vertrag_live_icon_link($post_id, 'cmx-carent-vertrag-live-link is-list')
			: '';
		$pdf_icon = \function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_pdf_icon_link')
			? (string) cmx_carent_vertrag_pdf_icon_link($post_id, 'cmx-carent-vertrag-pdf-link is-list')
			: '';

		echo ($live_icon !== '' || $pdf_icon !== '') // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			? '<span class="cmx-carent-vertrag-actions">' . $live_icon . $pdf_icon . '</span>'
			: '<span aria-hidden="true"></span>';
		return;
	}

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

	if ($column === 'cmx_carent_status') {
		$status = cmx_carent_admin_status_value($post_id);
		$options = cmx_carent_admin_status_options();
		echo '<span class="cmx-carent-inline-status" data-post-id="' . (int) $post_id . '">';
		echo '<button type="button" class="cmx-carent-status-badge is-' . \esc_attr($status) . '" data-status-label>' . \esc_html(cmx_carent_admin_status_label($status)) . '</button>';
		echo '<select class="cmx-carent-inline-status-select" data-status-select hidden>';
		foreach ($options as $value => $label) {
			echo '<option value="' . \esc_attr((string) $value) . '"' . \selected($status, (string) $value, false) . '>' . \esc_html((string) $label) . '</option>';
		}
		echo '</select>';
		echo '<span class="spinner" data-status-spinner></span>';
		echo '</span>';
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
		.wp-list-table .column-cmx_carent_vertrag{width:82px;text-align:center;white-space:nowrap}
		.wp-list-table .column-cmx_carent_artikel{width:25%;white-space:nowrap}
		.wp-list-table .column-cmx_carent_kontakt{width:22%;white-space:nowrap}
		.wp-list-table .column-cmx_carent_status{width:120px;white-space:nowrap}
		.wp-list-table th.column-cmx_carent_status{padding-left:3ch}
		.wp-list-table .column-cmx_carent_kennzeichen{width:110px;white-space:nowrap}
		.wp-list-table .column-cmx_carent_uebernahme{width:100px;white-space:nowrap}
		.wp-list-table .column-cmx_carent_rueckgabe{width:100px;white-space:nowrap}
		.wp-list-table .cmx-carent-inline-status{display:inline-flex;align-items:center;width:98px;height:30px;position:relative;vertical-align:middle}
		.wp-list-table .cmx-carent-inline-status [hidden]{display:none!important}
		.wp-list-table .cmx-carent-status-badge{display:inline-flex;align-items:center;justify-content:center;width:98px;min-width:98px;padding:2px 8px;border:0;border-radius:999px;font-size:11px;font-weight:700;line-height:1.5;cursor:pointer}
		.wp-list-table .cmx-carent-status-badge.is-offen{background:#fef3f2;color:#b42318}
		.wp-list-table .cmx-carent-status-badge.is-abgeschlossen{background:#ecfdf3;color:#027a48}
		.wp-list-table .cmx-carent-inline-status-select{position:absolute;left:0;top:0;width:98px;max-width:98px;height:30px;z-index:4}
		.wp-list-table .cmx-carent-inline-status .spinner{position:absolute;left:106px;top:5px;float:none;margin:0;visibility:hidden}
		.wp-list-table .cmx-carent-inline-status.is-saving .spinner{visibility:visible}
	</style>';
});

\add_action('wp_ajax_cmx_carent_inline_status', function (): void {
	$post_id = isset($_POST['post_id']) ? (int) \wp_unslash($_POST['post_id']) : 0;
	$status = isset($_POST['status']) ? \sanitize_key((string) \wp_unslash($_POST['status'])) : '';
	$options = cmx_carent_admin_status_options();

	if (!\check_ajax_referer('cmx_carent_inline_status', 'nonce', false)) {
		\wp_send_json_error(['message' => 'Sicherheitsprüfung fehlgeschlagen.'], 403);
	}
	if ($post_id <= 0 || (string) \get_post_type($post_id) !== 'carent' || !\current_user_can('edit_post', $post_id)) {
		\wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
	}
	if (!isset($options[$status])) {
		\wp_send_json_error(['message' => 'Ungültiger Status.'], 400);
	}
	if ($status === 'abgeschlossen' && \function_exists(__NAMESPACE__ . '\\cmx_carent_status_tracking_errors')) {
		$errors = (array) cmx_carent_status_tracking_errors($post_id);
		if ($errors !== []) {
			\wp_send_json_error(['message' => 'Der Status kann noch nicht auf abgeschlossen gesetzt werden: ' . \implode(' ', \array_map('strval', $errors))], 400);
		}
	}

	\update_post_meta($post_id, cmx_carent_admin_status_meta_key(), $status);
	\wp_send_json_success([
		'status' => $status,
		'label'  => (string) $options[$status],
	]);
});

\add_action('admin_footer-edit.php', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== 'carent') {
		return;
	}

	$nonce = \wp_create_nonce('cmx_carent_inline_status');
	?>
	<script>
	(function(){
		const nonce = <?php echo \wp_json_encode($nonce); ?>;
		function hideSelect(wrap){
			const button = wrap.querySelector("[data-status-label]");
			const select = wrap.querySelector("[data-status-select]");
			if(!button || !select) return;
			select.hidden = true;
			select.style.display = "none";
			button.hidden = false;
			button.style.display = "inline-flex";
		}
		function showSelect(wrap){
			const button = wrap.querySelector("[data-status-label]");
			const select = wrap.querySelector("[data-status-select]");
			if(!button || !select) return;
			button.hidden = true;
			button.style.display = "none";
			select.hidden = false;
			select.style.display = "block";
			select.focus();
		}
		function setButtonStatus(button, status, label){
			button.textContent = label;
			button.className = "cmx-carent-status-badge is-" + status;
			button.setAttribute("data-status-label", "");
		}
		function saveStatus(wrap, status){
			const button = wrap.querySelector("[data-status-label]");
			const select = wrap.querySelector("[data-status-select]");
			if(!button || !select || !wrap.dataset.postId) return;
			const previous = select.dataset.current || select.value;
			wrap.classList.add("is-saving");
			select.disabled = true;
			window.fetch(window.ajaxurl, {
				method: "POST",
				credentials: "same-origin",
				headers: {"Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"},
				body: new URLSearchParams({
					action: "cmx_carent_inline_status",
					nonce: nonce,
					post_id: wrap.dataset.postId,
					status: status
				})
			}).then(function(response){
				return response.json();
			}).then(function(payload){
				if(!payload || !payload.success){
					throw new Error(payload && payload.data && payload.data.message ? payload.data.message : "Status konnte nicht gespeichert werden.");
				}
				select.dataset.current = payload.data.status;
				select.value = payload.data.status;
				setButtonStatus(button, payload.data.status, payload.data.label);
				hideSelect(wrap);
			}).catch(function(error){
				select.value = previous;
				window.alert(error && error.message ? error.message : "Status konnte nicht gespeichert werden.");
				hideSelect(wrap);
			}).finally(function(){
				select.disabled = false;
				wrap.classList.remove("is-saving");
			});
		}
		document.querySelectorAll(".cmx-carent-inline-status").forEach(function(wrap){
			const button = wrap.querySelector("[data-status-label]");
			const select = wrap.querySelector("[data-status-select]");
			if(!button || !select) return;
			select.dataset.current = select.value;
			hideSelect(wrap);
			button.addEventListener("click", function(event){
				event.preventDefault();
				showSelect(wrap);
			});
			select.addEventListener("change", function(){
				if(select.value === select.dataset.current){
					hideSelect(wrap);
					return;
				}
				saveStatus(wrap, select.value);
			});
			select.addEventListener("keydown", function(event){
				if(event.key === "Escape"){
					select.value = select.dataset.current || select.value;
					hideSelect(wrap);
				}
			});
			select.addEventListener("blur", function(){
				window.setTimeout(function(){
					if(!wrap.classList.contains("is-saving")){
						select.value = select.dataset.current || select.value;
						hideSelect(wrap);
					}
				}, 150);
			});
		});
	})();
	</script>
	<?php
});

\add_filter('months_dropdown_results', function (array $months, string $post_type): array {
	return $post_type === 'carent' ? [] : $months;
}, 10, 2);

\add_action('restrict_manage_posts', function (string $post_type, string $which = 'top'): void {
	if ($post_type !== 'carent' || $which !== 'top') {
		return;
	}
	if (!\current_user_can('edit_posts')) {
		return;
	}

	$selected_article_id = isset($_GET['cmx_carent_artikel_id']) ? (int) \wp_unslash($_GET['cmx_carent_artikel_id']) : 0;
	$selected_status = isset($_GET['cmx_carent_status']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_carent_status'])) : '';
	$status_options = cmx_carent_admin_status_options();
	if ($selected_status !== '' && !isset($status_options[$selected_status])) {
		$selected_status = '';
	}
	$selected_date = cmx_carent_admin_filter_date_value();
	$article_ids = cmx_carent_admin_used_article_ids();

	echo '<label for="cmx_carent_artikel_id" class="screen-reader-text">' . \esc_html__('Nach Artikel filtern', 'cmx-misbuero') . '</label>';
	echo '<select name="cmx_carent_artikel_id" id="cmx_carent_artikel_id">';
	echo '<option value="0">' . \esc_html__('Alle Artikel', 'cmx-misbuero') . '</option>';
	foreach ($article_ids as $article_id) {
		if ($article_id <= 0 || (string) \get_post_type($article_id) !== 'artikel') {
			continue;
		}
		$label = cmx_carent_admin_article_label($article_id);
		if ($label === '') {
			$label = '#' . $article_id;
		}
		echo '<option value="' . (int) $article_id . '"' . \selected($selected_article_id, $article_id, false) . '>' . \esc_html($label) . '</option>';
	}
	echo '</select>';

	echo '<label for="cmx_carent_status" class="screen-reader-text">' . \esc_html__('Nach Status filtern', 'cmx-misbuero') . '</label>';
	echo '<select name="cmx_carent_status" id="cmx_carent_status">';
	echo '<option value="">' . \esc_html__('Alle Status', 'cmx-misbuero') . '</option>';
	foreach ($status_options as $value => $label) {
		echo '<option value="' . \esc_attr((string) $value) . '"' . \selected($selected_status, (string) $value, false) . '>' . \esc_html((string) $label) . '</option>';
	}
	echo '</select>';

	echo '<label for="cmx_carent_stichtag" class="screen-reader-text">' . \esc_html__('Vermietet am Datum', 'cmx-misbuero') . '</label>';
	echo '<input type="date" name="cmx_carent_stichtag" id="cmx_carent_stichtag" value="' . \esc_attr($selected_date) . '" title="' . \esc_attr__('Vermietet am Datum', 'cmx-misbuero') . '">';
}, 10, 2);

\add_action('pre_get_posts', function (\WP_Query $query): void {
	if (!\is_admin() || !$query->is_main_query()) {
		return;
	}

	$post_type = $query->get('post_type');
	if (\is_array($post_type)) {
		$post_type = (string) ($post_type[0] ?? '');
	}
	if ((string) $post_type !== 'carent') {
		return;
	}

	$article_id = isset($_GET['cmx_carent_artikel_id']) ? (int) \wp_unslash($_GET['cmx_carent_artikel_id']) : 0;
	$selected_status = isset($_GET['cmx_carent_status']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_carent_status'])) : '';
	if ($selected_status !== '' && !isset(cmx_carent_admin_status_options()[$selected_status])) {
		$selected_status = '';
	}
	$filter_date = cmx_carent_admin_filter_date_value();
	if ($article_id <= 0 && $selected_status === '' && $filter_date === '') {
		return;
	}

	$raw_meta_query = $query->get('meta_query');
	$meta_query = \is_array($raw_meta_query) ? $raw_meta_query : [];
	if (!isset($meta_query['relation'])) {
		$meta_query['relation'] = 'AND';
	}

	if ($article_id > 0) {
		$meta_query[] = [
			'key'     => \defined(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_FAHRZEUG_META')
				: '_cmx_carent_fahrzeug_id',
			'value'   => $article_id,
			'compare' => '=',
			'type'    => 'NUMERIC',
		];
	}

	if ($selected_status !== '') {
		$status_key = cmx_carent_admin_status_meta_key();
		if ($selected_status === 'offen') {
			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'     => $status_key,
					'value'   => 'offen',
					'compare' => '=',
				],
				[
					'key'     => $status_key,
					'value'   => '',
					'compare' => '=',
				],
				[
					'key'     => $status_key,
					'compare' => 'NOT EXISTS',
				],
			];
		} else {
			$meta_query[] = [
				'key'     => $status_key,
				'value'   => $selected_status,
				'compare' => '=',
			];
		}
	}

	if ($filter_date !== '') {
		$start_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_DATUM_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_UEBERNAHME_DATUM_META')
			: '_cmx_carent_uebernahme_datum';
		$end_key = \defined(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_DATUM_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_CARENT_RUECKGABE_DATUM_META')
			: '_cmx_carent_rueckgabe_datum';

		$meta_query[] = [
			'relation' => 'AND',
			[
				'key'     => $start_key,
				'value'   => $filter_date,
				'compare' => '<=',
				'type'    => 'DATE',
			],
			[
				'relation' => 'OR',
				[
					'key'     => $end_key,
					'value'   => $filter_date,
					'compare' => '>=',
					'type'    => 'DATE',
				],
				[
					'key'     => $end_key,
					'value'   => '',
					'compare' => '=',
				],
				[
					'key'     => $end_key,
					'compare' => 'NOT EXISTS',
				],
			],
		];
	}

	$query->set('meta_query', $meta_query);
}, 20);

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
