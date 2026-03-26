<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * CPT "artikel": keine Auto-Entwürfe, immer publish.
 */

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_should_force_publish_status')) {
	function cmx_artikel_should_force_publish_status(string $status): bool {
		$status = \sanitize_key($status);
		return \in_array($status, ['draft', 'pending', 'future', 'private'], true);
	}
}

// Bereits vor dem Schreiben echte Entwürfe/Pending/etc. auf publish ziehen.
\add_filter('wp_insert_post_data', function (array $data, array $postarr): array {
	$post_type = \sanitize_key((string) ($data['post_type'] ?? $postarr['post_type'] ?? ''));
	if ($post_type !== 'artikel') {
		return $data;
	}

	$status = \sanitize_key((string) ($data['post_status'] ?? ''));
	if (!cmx_artikel_should_force_publish_status($status)) {
		return $data;
	}

	$data['post_status'] = 'publish';
	return $data;
}, 100, 2);

// Autosave-Script für Artikel deaktivieren.
\add_action('admin_enqueue_scripts', function(): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || ($screen->post_type ?? '') !== 'artikel') {
		return;
	}
	\wp_dequeue_script('autosave');
}, 20);

// Beim Speichern immer publish erzwingen (keine Entwürfe).
\add_action('save_post_artikel', function(int $post_id, \WP_Post $post): void {
	static $is_updating = false;
	if ($is_updating) {
		return;
	}
	if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) {
		return;
	}
	if (in_array($post->post_status, ['trash', 'auto-draft'], true)) {
		return;
	}
	if (!cmx_artikel_should_force_publish_status((string) $post->post_status)) {
		return;
	}
	$is_updating = true;
	\wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
	$is_updating = false;
}, 10, 2);

// Nicht veröffentlichte Artikel im Listen-Screen entfernen.
\add_action('admin_init', function(): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || ($screen->base ?? '') !== 'edit' || ($screen->post_type ?? '') !== 'artikel') {
		return;
	}
	$ids = \get_posts([
		'post_type'      => 'artikel',
		'post_status'    => ['auto-draft', 'draft', 'pending', 'future', 'private'],
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
	]);
	foreach ($ids as $id) {
		\wp_delete_post((int)$id, true);
	}
});
