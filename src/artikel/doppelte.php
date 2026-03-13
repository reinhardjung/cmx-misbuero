<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_duplicate_cleanup_url')) {
	function cmx_artikel_duplicate_cleanup_url(): string {
		return \wp_nonce_url(
			\add_query_arg([
				'action' => 'cmx_artikel_trash_duplicates',
			], \admin_url('admin-post.php')),
			'cmx_artikel_trash_duplicates'
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_duplicate_notice_base_url')) {
	function cmx_artikel_duplicate_notice_base_url(): string {
		return (string) \admin_url('edit.php?post_type=artikel');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_duplicate_normalize_title')) {
	function cmx_artikel_duplicate_normalize_title(string $title): string {
		$title = \wp_strip_all_tags($title);
		$title = \trim(\preg_replace('/\s+/u', ' ', $title) ?? $title);
		if ($title === '') {
			return '';
		}
		return \function_exists('\\mb_strtolower')
			? (string) \mb_strtolower($title, 'UTF-8')
			: (string) \strtolower($title);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_duplicate_posts')) {
	function cmx_artikel_duplicate_posts(): array {
		$posts = \get_posts([
			'post_type'              => 'artikel',
			'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'         => -1,
			'orderby'                => ['title' => 'ASC', 'ID' => 'ASC'],
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => true,
		]);

		return \is_array($posts) ? $posts : [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_duplicate_groups')) {
	function cmx_artikel_duplicate_groups(): array {
		$groups = [];
		foreach (cmx_artikel_duplicate_posts() as $post) {
			if (!$post instanceof \WP_Post) continue;
			$key = cmx_artikel_duplicate_normalize_title((string) $post->post_title);
			if ($key === '') continue;
			if (!isset($groups[$key])) {
				$groups[$key] = [];
			}
			$groups[$key][] = $post;
		}

		return \array_filter($groups, static function(array $posts): bool {
			return \count($posts) > 1;
		});
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_duplicate_normalize_id_list')) {
	function cmx_artikel_duplicate_normalize_id_list($value): array {
		if (\is_string($value) && $value !== '') {
			$json = \json_decode($value, true);
			if (\json_last_error() === \JSON_ERROR_NONE && \is_array($json)) {
				$value = $json;
			} else {
				$maybe = @\maybe_unserialize($value);
				if (\is_array($maybe)) {
					$value = $maybe;
				}
			}
		}

		$out = [];
		foreach ((array) $value as $item) {
			$id = (int) $item;
			if ($id > 0) {
				$out[$id] = true;
			}
		}
		return \array_map('intval', \array_keys($out));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_duplicate_collect_beleg_refs')) {
	function cmx_artikel_duplicate_collect_beleg_refs(): array {
		if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_artikel_usage_counts')) {
			return \array_map('intval', \array_keys((array) cmx_beleg_artikel_usage_counts()));
		}

		$refs = [];
		$beleg_ids = \get_posts([
			'post_type'              => 'belege',
			'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => true,
		]);
		foreach ((array) $beleg_ids as $beleg_id) {
			$rows = \get_post_meta((int) $beleg_id, '_cmx_beleg_positionen', true);
			if (\is_string($rows) && $rows !== '') {
				$tmp = \json_decode($rows, true);
				if (\json_last_error() === \JSON_ERROR_NONE && \is_array($tmp)) {
					$rows = $tmp;
				} else {
					$tmp = @\maybe_unserialize($rows);
					$rows = \is_array($tmp) ? $tmp : [];
				}
			}
			foreach ((array) $rows as $row) {
				if (!\is_array($row)) continue;
				$artikel_id = isset($row['artikel_id']) ? (int) $row['artikel_id'] : 0;
				if ($artikel_id > 0) {
					$refs[$artikel_id] = true;
				}
			}
		}
		return \array_map('intval', \array_keys($refs));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_duplicate_collect_task_refs')) {
	function cmx_artikel_duplicate_collect_task_refs(): array {
		$post_types = \defined(__NAMESPACE__ . '\\CMX_TASK_POST_TYPES')
			? (array) \constant(__NAMESPACE__ . '\\CMX_TASK_POST_TYPES')
			: ['projekte', 'kontakte'];
		$post_types = \array_values(\array_filter(\array_map('strval', $post_types), static function(string $post_type): bool {
			return $post_type !== '' && $post_type !== 'artikel';
		}));

		$meta_key = \defined(__NAMESPACE__ . '\\CMX_PROJEKT_TASK_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_PROJEKT_TASK_META')
			: '_cmx_projekt_tasks';

		$refs = [];
		foreach ($post_types as $post_type) {
			$post_ids = \get_posts([
				'post_type'              => $post_type,
				'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'suppress_filters'       => true,
			]);

			foreach ((array) $post_ids as $post_id) {
				$rows = \get_post_meta((int) $post_id, $meta_key, true);
				foreach ((array) $rows as $row) {
					if (!\is_array($row)) continue;
					$artikel_id = isset($row['artikel_id']) ? (int) $row['artikel_id'] : 0;
					if ($artikel_id > 0) {
						$refs[$artikel_id] = true;
					}
				}
			}
		}

		return \array_map('intval', \array_keys($refs));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_duplicate_collect_scanner_refs')) {
	function cmx_artikel_duplicate_collect_scanner_refs(): array {
		$meta_key = \defined(__NAMESPACE__ . '\\CMX_SCANNER_REL_ARTIKEL_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_SCANNER_REL_ARTIKEL_META')
			: '_cmx_scanner_rel_artikel_id';

		$refs = [];
		$scanner_ids = \get_posts([
			'post_type'              => 'scanner',
			'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => true,
		]);

		foreach ((array) $scanner_ids as $scanner_id) {
			$ids = cmx_artikel_duplicate_normalize_id_list(\get_post_meta((int) $scanner_id, $meta_key, true));
			foreach ($ids as $artikel_id) {
				$refs[(int) $artikel_id] = true;
			}
		}

		return \array_map('intval', \array_keys($refs));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_duplicate_collect_dokumente_refs')) {
	function cmx_artikel_duplicate_collect_dokumente_refs(): array {
		$relation_key = 'cmx_dokumente_artikel';
		if (\defined(__NAMESPACE__ . '\\CMX_DOK_REL_META')) {
			$map = (array) \constant(__NAMESPACE__ . '\\CMX_DOK_REL_META');
			if (!empty($map['artikel']) && \is_string($map['artikel'])) {
				$relation_key = (string) $map['artikel'];
			}
		}

		$uploads_key = \function_exists(__NAMESPACE__ . '\\cmx_dok_uploads_meta_key')
			? (string) cmx_dok_uploads_meta_key()
			: (\defined(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
				? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
				: '_cmx_dokumente_uploads');

		$refs = [];

		$dok_ids = \get_posts([
			'post_type'              => 'dokumente',
			'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => true,
		]);
		foreach ((array) $dok_ids as $dok_id) {
			$ids = cmx_artikel_duplicate_normalize_id_list(\get_post_meta((int) $dok_id, $relation_key, true));
			foreach ($ids as $artikel_id) {
				$refs[(int) $artikel_id] = true;
			}
		}

		$artikel_ids = \get_posts([
			'post_type'              => 'artikel',
			'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => true,
			'meta_query'             => [
				[
					'key'     => $uploads_key,
					'compare' => 'EXISTS',
				],
			],
		]);
		foreach ((array) $artikel_ids as $artikel_id) {
			$uploads = cmx_artikel_duplicate_normalize_id_list(\get_post_meta((int) $artikel_id, $uploads_key, true));
			if ($uploads !== []) {
				$refs[(int) $artikel_id] = true;
			}
		}

		return \array_map('intval', \array_keys($refs));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_duplicate_linked_ids')) {
	function cmx_artikel_duplicate_linked_ids(): array {
		$refs = [];
		foreach ([
			cmx_artikel_duplicate_collect_beleg_refs(),
			cmx_artikel_duplicate_collect_task_refs(),
			cmx_artikel_duplicate_collect_scanner_refs(),
			cmx_artikel_duplicate_collect_dokumente_refs(),
		] as $ids) {
			foreach ((array) $ids as $artikel_id) {
				$artikel_id = (int) $artikel_id;
				if ($artikel_id > 0) {
					$refs[$artikel_id] = true;
				}
			}
		}
		return \array_map('intval', \array_keys($refs));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_duplicate_trash_candidates')) {
	function cmx_artikel_duplicate_trash_candidates(): array {
		$groups = cmx_artikel_duplicate_groups();
		$linked_map = \array_fill_keys(cmx_artikel_duplicate_linked_ids(), true);
		$candidates = [];
		$protected = [];

		foreach ($groups as $posts) {
			$ids = \array_map(static function(\WP_Post $post): int {
				return (int) $post->ID;
			}, $posts);
			$linked_ids = \array_values(\array_filter($ids, static function(int $artikel_id) use ($linked_map): bool {
				return isset($linked_map[$artikel_id]);
			}));

			if ($linked_ids !== []) {
				foreach ($linked_ids as $artikel_id) {
					$protected[$artikel_id] = true;
				}
				foreach ($ids as $artikel_id) {
					if (!isset($linked_map[$artikel_id])) {
						$candidates[$artikel_id] = true;
					}
				}
				continue;
			}

			$keep_id = (int) \reset($ids);
			if ($keep_id > 0) {
				$protected[$keep_id] = true;
			}
			foreach ($ids as $artikel_id) {
				if ($artikel_id !== $keep_id) {
					$candidates[$artikel_id] = true;
				}
			}
		}

		return [
			'trash_ids'      => \array_map('intval', \array_keys($candidates)),
			'protected_ids'  => \array_map('intval', \array_keys($protected)),
			'duplicate_sets' => \count($groups),
		];
	}
}

\add_filter('views_edit-artikel', function(array $views): array {
	if (!\current_user_can('delete_posts')) {
		return $views;
	}

	$url = \function_exists(__NAMESPACE__ . '\\cmx_artikel_duplicate_cleanup_url')
		? (string) cmx_artikel_duplicate_cleanup_url()
		: '';
	if ($url === '') {
		return $views;
	}

	$link = '<a href="' . \esc_url($url) . '">doppelt löschen</a>';
	$new_views = [];
	$inserted = false;
	foreach ($views as $key => $html) {
		$new_views[$key] = $html;
		if ($key === 'cmx_deckungsbeitrag' && !$inserted) {
			$new_views['cmx_artikel_trash_duplicates'] = $link;
			$inserted = true;
		}
	}
	if (!$inserted) {
		$new_views['cmx_artikel_trash_duplicates'] = $link;
	}
	return $new_views;
}, 40);

\add_action('admin_post_cmx_artikel_trash_duplicates', function (): void {
	if (!\current_user_can('delete_posts')) {
		\wp_die('Keine Berechtigung.');
	}
	if (!\wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'cmx_artikel_trash_duplicates')) {
		\wp_die('Ungültige Anfrage.');
	}

	$result = \function_exists(__NAMESPACE__ . '\\cmx_artikel_duplicate_trash_candidates')
		? (array) cmx_artikel_duplicate_trash_candidates()
		: ['trash_ids' => [], 'protected_ids' => [], 'duplicate_sets' => 0];

	$trashed = 0;
	foreach ((array) ($result['trash_ids'] ?? []) as $artikel_id) {
		$artikel_id = (int) $artikel_id;
		if ($artikel_id <= 0 || !\current_user_can('delete_post', $artikel_id)) {
			continue;
		}
		$trashed_post = \wp_trash_post($artikel_id);
		if ($trashed_post) {
			$trashed++;
		}
	}

	$redirect = \add_query_arg([
		'cmx_artikel_dedup_done'      => 1,
		'cmx_artikel_dedup_trashed'   => $trashed,
		'cmx_artikel_dedup_protected' => \count((array) ($result['protected_ids'] ?? [])),
		'cmx_artikel_dedup_groups'    => (int) ($result['duplicate_sets'] ?? 0),
	], cmx_artikel_duplicate_notice_base_url());

	\wp_safe_redirect($redirect);
	exit;
});

\add_action('admin_notices', function (): void {
	global $typenow;
	if ($typenow !== 'artikel' || empty($_GET['cmx_artikel_dedup_done'])) {
		return;
	}

	$trashed = isset($_GET['cmx_artikel_dedup_trashed']) ? (int) $_GET['cmx_artikel_dedup_trashed'] : 0;
	$protected = isset($_GET['cmx_artikel_dedup_protected']) ? (int) $_GET['cmx_artikel_dedup_protected'] : 0;
	$groups = isset($_GET['cmx_artikel_dedup_groups']) ? (int) $_GET['cmx_artikel_dedup_groups'] : 0;

	if ($trashed > 0) {
		$text = $trashed . ' doppelte Artikel wurden in den Papierkorb verschoben.';
		if ($protected > 0) {
			$text .= ' ' . $protected . ' verlinkte Artikel blieben erhalten.';
		}
	} elseif ($groups > 0) {
		$text = 'Es wurden keine unverlinkten doppelten Artikel gelöscht.';
		if ($protected > 0) {
			$text .= ' Verlinkte Dubletten blieben erhalten.';
		}
	} else {
		$text = 'Es wurden keine doppelten Artikel gefunden.';
	}

	echo '<div class="notice notice-success is-dismissible"><p>' . \esc_html($text) . '</p></div>';
});
