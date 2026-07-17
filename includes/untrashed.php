<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * 1) Primärer Weg: Core-Filter 'wp_untrash_post_status'
 *    Setzt den Zielstatus beim Untrash auf 'publish' – bevor WP den Status tatsächlich schreibt.
 */
add_filter('wp_untrash_post_status', __NAMESPACE__ . '\\cmx_force_publish_on_untrash', 10, 3);
function cmx_force_publish_on_untrash(string $new_status, int $post_id, ?string $previous_status): string {
	$post = get_post($post_id);
	if (!$post) return $new_status;

	$post_type_obj = get_post_type_object($post->post_type);
	// Nur Custom Post Types (nicht builtin)
	if (!$post_type_obj || !isset($post_type_obj->_builtin) || $post_type_obj->_builtin) {
		return $new_status; // Core-Typen unverändert lassen
	}

	// Immer auf 'publish' setzen
	return 'publish';
}

/**
 * 2) Fallback: Falls ein anderes Plugin 'wp_untrash_post_status' umgeht,
 *    prüfen wir nach dem Untrash und korrigieren den Status notfalls auf 'publish'.
 */
add_action('untrashed_post', __NAMESPACE__ . '\\cmx_fallback_publish_after_untrash', 20);
function cmx_fallback_publish_after_untrash(int $post_id): void {
	$post = get_post($post_id);
	if (!$post) return;

	$pto = get_post_type_object($post->post_type);
	if (!$pto || !isset($pto->_builtin) || $pto->_builtin) return;

	if ($post->post_status !== 'publish') {
		// Kein Loop-Risiko, 'untrashed_post' feuert nicht erneut durch dieses Update.
		wp_update_post([
			'ID'          => $post_id,
			'post_status' => 'publish',
		]);
	}
}


require_once 'dublicate.php';
