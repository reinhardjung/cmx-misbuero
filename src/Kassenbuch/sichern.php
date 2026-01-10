<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Setzt den Dateinamen des Beitragsbildes als Titel, falls beim Speichern kein Titel vorhanden ist.
 */
function cmx_kassenbuch_autofill_title(int $post_id, \WP_Post $post): void {
	if ($post->post_type !== 'kassenbuch') return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (wp_is_post_revision($post_id)) return;
	if (!current_user_can('edit_post', $post_id)) return;

	if (trim((string) $post->post_title) !== '') return; // Titel existiert bereits

	$thumb_id = (int) get_post_thumbnail_id($post_id);
	if (!$thumb_id) return;

	$filename = '';
	$path = get_attached_file($thumb_id);
	if ($path) {
		$filename = pathinfo($path, PATHINFO_FILENAME);
	}
	if ($filename === '') {
		$att = get_post($thumb_id);
		$filename = $att?->post_title ?? '';
	}

	$filename = strtolower($filename);
	$filename = str_replace(['_', '-'], ' ', $filename);
	$filename = sanitize_text_field($filename);
	if ($filename === '') return;

	remove_action('save_post_kassenbuch', __NAMESPACE__ . '\\cmx_kassenbuch_autofill_title', 10);
	wp_update_post([
		'ID'         => $post_id,
		'post_title' => $filename,
	]);
	add_action('save_post_kassenbuch', __NAMESPACE__ . '\\cmx_kassenbuch_autofill_title', 10, 2);
}
\add_action('save_post_kassenbuch', __NAMESPACE__ . '\\cmx_kassenbuch_autofill_title', 10, 2);
