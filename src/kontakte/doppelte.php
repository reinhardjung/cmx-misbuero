<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_duplicate_cleanup_url')) {
	function cmx_kontakte_duplicate_cleanup_url(): string {
		return \wp_nonce_url(
			\add_query_arg([
				'action' => 'cmx_kontakte_trash_duplicates',
			], \admin_url('admin-post.php')),
			'cmx_kontakte_trash_duplicates'
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_duplicate_notice_base_url')) {
	function cmx_kontakte_duplicate_notice_base_url(): string {
		return (string) \admin_url('edit.php?post_type=kontakte');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_duplicate_normalize_title')) {
	function cmx_kontakte_duplicate_normalize_title(string $title): string {
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_duplicate_posts')) {
	function cmx_kontakte_duplicate_posts(): array {
		$posts = \get_posts([
			'post_type'              => 'kontakte',
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_duplicate_groups')) {
	function cmx_kontakte_duplicate_groups(): array {
		$groups = [];
		foreach (cmx_kontakte_duplicate_posts() as $post) {
			if (!$post instanceof \WP_Post) continue;
			$key = cmx_kontakte_duplicate_normalize_title((string) $post->post_title);
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_duplicate_normalize_id_list')) {
	function cmx_kontakte_duplicate_normalize_id_list($value): array {
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_duplicate_collect_beleg_refs')) {
	function cmx_kontakte_duplicate_collect_beleg_refs(): array {
		$refs = [];
		$meta_keys = ['_cmx_beleg_kontakt_id', 'cmx_beleg_kontakt_id'];
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
			foreach ($meta_keys as $meta_key) {
				$kontakt_id = (int) \get_post_meta((int) $beleg_id, $meta_key, true);
				if ($kontakt_id > 0) {
					$refs[$kontakt_id] = true;
				}
			}
		}
		return \array_map('intval', \array_keys($refs));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_duplicate_collect_projekt_refs')) {
	function cmx_kontakte_duplicate_collect_projekt_refs(): array {
		$refs = [];
		$meta_keys = ['_cmx_projekt_kontakt_id', 'cmx_projekt_kontakt_id'];
		$projekt_ids = \get_posts([
			'post_type'              => 'projekte',
			'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => true,
		]);
		foreach ((array) $projekt_ids as $projekt_id) {
			foreach ($meta_keys as $meta_key) {
				$kontakt_id = (int) \get_post_meta((int) $projekt_id, $meta_key, true);
				if ($kontakt_id > 0) {
					$refs[$kontakt_id] = true;
				}
			}
		}
		return \array_map('intval', \array_keys($refs));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_duplicate_collect_scanner_refs')) {
	function cmx_kontakte_duplicate_collect_scanner_refs(): array {
		$meta_key = \defined(__NAMESPACE__ . '\\CMX_SCANNER_REL_KONTAKTE_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_SCANNER_REL_KONTAKTE_META')
			: '_cmx_scanner_rel_kontakte_id';

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
			$ids = cmx_kontakte_duplicate_normalize_id_list(\get_post_meta((int) $scanner_id, $meta_key, true));
			foreach ($ids as $kontakt_id) {
				$refs[(int) $kontakt_id] = true;
			}
		}

		return \array_map('intval', \array_keys($refs));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_duplicate_collect_dokumente_refs')) {
	function cmx_kontakte_duplicate_collect_dokumente_refs(): array {
		$relation_key = 'cmx_dokumente_kunden';
		if (\defined(__NAMESPACE__ . '\\CMX_DOK_REL_META')) {
			$map = (array) \constant(__NAMESPACE__ . '\\CMX_DOK_REL_META');
			if (!empty($map['kontakte']) && \is_string($map['kontakte'])) {
				$relation_key = (string) $map['kontakte'];
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
			$ids = cmx_kontakte_duplicate_normalize_id_list(\get_post_meta((int) $dok_id, $relation_key, true));
			foreach ($ids as $kontakt_id) {
				$refs[(int) $kontakt_id] = true;
			}
		}

		$kontakt_ids = \get_posts([
			'post_type'              => 'kontakte',
			'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => true,
			'meta_query'             => [[
				'key'     => $uploads_key,
				'compare' => 'EXISTS',
			]],
		]);
		foreach ((array) $kontakt_ids as $kontakt_id) {
			$uploads = cmx_kontakte_duplicate_normalize_id_list(\get_post_meta((int) $kontakt_id, $uploads_key, true));
			if ($uploads !== []) {
				$refs[(int) $kontakt_id] = true;
			}
		}

		return \array_map('intval', \array_keys($refs));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_duplicate_collect_reverse_project_refs')) {
	function cmx_kontakte_duplicate_collect_reverse_project_refs(): array {
		$refs = [];
		$kontakt_ids = \get_posts([
			'post_type'              => 'kontakte',
			'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => true,
			'meta_query'             => [[
				'key'     => '_cmx_kontakt_projekte',
				'compare' => 'EXISTS',
			]],
		]);
		foreach ((array) $kontakt_ids as $kontakt_id) {
			$projekt_ids = cmx_kontakte_duplicate_normalize_id_list(\get_post_meta((int) $kontakt_id, '_cmx_kontakt_projekte', true));
			if ($projekt_ids !== []) {
				$refs[(int) $kontakt_id] = true;
			}
		}
		return \array_map('intval', \array_keys($refs));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_duplicate_linked_ids')) {
	function cmx_kontakte_duplicate_linked_ids(): array {
		$refs = [];
		foreach ([
			cmx_kontakte_duplicate_collect_beleg_refs(),
			cmx_kontakte_duplicate_collect_projekt_refs(),
			cmx_kontakte_duplicate_collect_scanner_refs(),
			cmx_kontakte_duplicate_collect_dokumente_refs(),
			cmx_kontakte_duplicate_collect_reverse_project_refs(),
		] as $ids) {
			foreach ((array) $ids as $kontakt_id) {
				$kontakt_id = (int) $kontakt_id;
				if ($kontakt_id > 0) {
					$refs[$kontakt_id] = true;
				}
			}
		}
		return \array_map('intval', \array_keys($refs));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_duplicate_trash_candidates')) {
	function cmx_kontakte_duplicate_trash_candidates(): array {
		$groups = cmx_kontakte_duplicate_groups();
		$linked_map = \array_fill_keys(cmx_kontakte_duplicate_linked_ids(), true);
		$candidates = [];
		$protected = [];

		foreach ($groups as $posts) {
			$ids = \array_map(static function(\WP_Post $post): int {
				return (int) $post->ID;
			}, $posts);
			$linked_ids = \array_values(\array_filter($ids, static function(int $kontakt_id) use ($linked_map): bool {
				return isset($linked_map[$kontakt_id]);
			}));

			if ($linked_ids !== []) {
				foreach ($linked_ids as $kontakt_id) {
					$protected[$kontakt_id] = true;
				}
				foreach ($ids as $kontakt_id) {
					if (!isset($linked_map[$kontakt_id])) {
						$candidates[$kontakt_id] = true;
					}
				}
				continue;
			}

			$keep_id = (int) \reset($ids);
			if ($keep_id > 0) {
				$protected[$keep_id] = true;
			}
			foreach ($ids as $kontakt_id) {
				if ($kontakt_id !== $keep_id) {
					$candidates[$kontakt_id] = true;
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

\add_filter('views_edit-kontakte', function(array $views): array {
	if (!\current_user_can('delete_posts')) {
		return $views;
	}

	$url = \function_exists(__NAMESPACE__ . '\\cmx_kontakte_duplicate_cleanup_url')
		? (string) cmx_kontakte_duplicate_cleanup_url()
		: '';
	if ($url === '') {
		return $views;
	}

	$link = '<a href="' . \esc_url($url) . '">doppelte löschen</a>';
	$new_views = [];
	$inserted = false;
	foreach ($views as $key => $html) {
		$new_views[$key] = $html;
		if ($key === 'cmx_deckungsbeitrag' && !$inserted) {
			$new_views['cmx_kontakte_trash_duplicates'] = $link;
			$inserted = true;
		}
	}
	if (!$inserted) {
		$new_views['cmx_kontakte_trash_duplicates'] = $link;
	}
	return $new_views;
}, 40);

\add_action('admin_post_cmx_kontakte_trash_duplicates', function (): void {
	if (!\current_user_can('delete_posts')) {
		\wp_die('Keine Berechtigung.');
	}
	if (!\wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'cmx_kontakte_trash_duplicates')) {
		\wp_die('Ungültige Anfrage.');
	}

	$result = \function_exists(__NAMESPACE__ . '\\cmx_kontakte_duplicate_trash_candidates')
		? (array) cmx_kontakte_duplicate_trash_candidates()
		: ['trash_ids' => [], 'protected_ids' => [], 'duplicate_sets' => 0];

	$trashed = 0;
	foreach ((array) ($result['trash_ids'] ?? []) as $kontakt_id) {
		$kontakt_id = (int) $kontakt_id;
		if ($kontakt_id <= 0 || !\current_user_can('delete_post', $kontakt_id)) {
			continue;
		}
		$trashed_post = \wp_trash_post($kontakt_id);
		if ($trashed_post) {
			$trashed++;
		}
	}

	$redirect = \add_query_arg([
		'cmx_kontakte_dedup_done'      => 1,
		'cmx_kontakte_dedup_trashed'   => $trashed,
		'cmx_kontakte_dedup_protected' => \count((array) ($result['protected_ids'] ?? [])),
		'cmx_kontakte_dedup_groups'    => (int) ($result['duplicate_sets'] ?? 0),
	], cmx_kontakte_duplicate_notice_base_url());

	\wp_safe_redirect($redirect);
	exit;
});

\add_action('admin_notices', function (): void {
	global $typenow;
	if ($typenow !== 'kontakte' || empty($_GET['cmx_kontakte_dedup_done'])) {
		return;
	}

	$trashed = isset($_GET['cmx_kontakte_dedup_trashed']) ? (int) $_GET['cmx_kontakte_dedup_trashed'] : 0;
	$protected = isset($_GET['cmx_kontakte_dedup_protected']) ? (int) $_GET['cmx_kontakte_dedup_protected'] : 0;
	$groups = isset($_GET['cmx_kontakte_dedup_groups']) ? (int) $_GET['cmx_kontakte_dedup_groups'] : 0;

	if ($trashed > 0) {
		$text = $trashed . ' doppelte Kontakte wurden in den Papierkorb verschoben.';
		if ($protected > 0) {
			$text .= ' ' . $protected . ' verlinkte Kontakte blieben erhalten.';
		}
	} elseif ($groups > 0) {
		$text = 'Es wurden keine unverlinkten doppelten Kontakte gelöscht.';
		if ($protected > 0) {
			$text .= ' Verlinkte Dubletten blieben erhalten.';
		}
	} else {
		$text = 'Es wurden keine doppelten Kontakte gefunden.';
	}

	echo '<div class="notice notice-success is-dismissible"><p>' . \esc_html($text) . '</p></div>';
});
