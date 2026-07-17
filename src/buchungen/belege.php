<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

function cmx_buchungen_create_beleg(int $post_id) {
	if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_BUCHUNGEN_CPT || !\post_type_exists('belege')) {
		return new \WP_Error('invalid_booking', 'Buchung oder Belege-CPT nicht verfügbar.');
	}

	$existing_id = (int) \get_post_meta($post_id, CMX_BUCHUNGEN_META_BELEG_ID, true);
	if ($existing_id > 0 && (string) \get_post_type($existing_id) === 'belege' && (string) \get_post_status($existing_id) !== 'trash') {
		return $existing_id;
	}
	if ($existing_id > 0) {
		\delete_post_meta($post_id, CMX_BUCHUNGEN_META_BELEG_ID);
	}

	$title = 'Buchung ' . (string) \get_the_title($post_id);
	$beleg_id = \wp_insert_post([
		'post_type' => 'belege',
		'post_status' => 'draft',
		'post_title' => $title,
	], true);
	if (\is_wp_error($beleg_id)) {
		return $beleg_id;
	}

	\update_post_meta((int) $beleg_id, '_cmx_buchung_id', $post_id);
	\update_post_meta($post_id, CMX_BUCHUNGEN_META_BELEG_ID, (int) $beleg_id);

	$kontakt_id = (int) \get_post_meta($post_id, CMX_BUCHUNGEN_META_KONTAKT, true);
	if ($kontakt_id > 0) {
		\update_post_meta((int) $beleg_id, '_cmx_beleg_kontakt_id', $kontakt_id);
	}

	return (int) $beleg_id;
}

\add_action('post_submitbox_misc_actions', function (): void {
	$post = \get_post();
	if (!$post instanceof \WP_Post || (string) $post->post_type !== CMX_BUCHUNGEN_CPT || !\current_user_can('edit_post', $post->ID)) {
		return;
	}

	$beleg_id = (int) \get_post_meta($post->ID, CMX_BUCHUNGEN_META_BELEG_ID, true);
	$beleg_exists = $beleg_id > 0 && (string) \get_post_type($beleg_id) === 'belege' && (string) \get_post_status($beleg_id) !== 'trash';
	if ($beleg_id > 0 && !$beleg_exists) {
		\delete_post_meta($post->ID, CMX_BUCHUNGEN_META_BELEG_ID);
	}

	echo '<div class="misc-pub-section">';
	if ($beleg_exists) {
		echo '<a class="button" href="' . \esc_url((string) \get_edit_post_link($beleg_id)) . '">Beleg öffnen</a>';
	} else {
		$kontakt_id = (int) \get_post_meta($post->ID, CMX_BUCHUNGEN_META_KONTAKT, true);
		$artikel_id = (int) \get_post_meta($post->ID, CMX_BUCHUNGEN_META_ARTIKEL, true);
		$can_create = $kontakt_id > 0 && $artikel_id > 0;
		$url = \wp_nonce_url(\admin_url('admin-post.php?action=cmx_buchungen_create_beleg&post_id=' . $post->ID), 'cmx_buchungen_create_beleg_' . $post->ID);
		echo '<a id="cmx-buchungen-create-beleg-button" class="button' . ($can_create ? '' : ' disabled') . '" href="' . \esc_url($can_create ? $url : '#') . '" data-create-url="' . \esc_url($url) . '" aria-disabled="' . ($can_create ? 'false' : 'true') . '">Beleg erstellen</a>';
	}
	echo '</div>';
});

\add_action('admin_post_cmx_buchungen_create_beleg', function (): void {
	$post_id = isset($_GET['post_id']) ? (int) \wp_unslash($_GET['post_id']) : 0;
	if ($post_id <= 0 || !\current_user_can('edit_post', $post_id) || !\check_admin_referer('cmx_buchungen_create_beleg_' . $post_id)) {
		\wp_die('Keine Berechtigung.');
	}
	if ((int) \get_post_meta($post_id, CMX_BUCHUNGEN_META_KONTAKT, true) <= 0 || (int) \get_post_meta($post_id, CMX_BUCHUNGEN_META_ARTIKEL, true) <= 0) {
		\wp_safe_redirect((string) \get_edit_post_link($post_id, 'raw'));
		exit;
	}

	$beleg_id = cmx_buchungen_create_beleg($post_id);
	$target = \is_wp_error($beleg_id)
		? (string) \get_edit_post_link($post_id, 'raw')
		: (string) \get_edit_post_link((int) $beleg_id, 'raw');
	\wp_safe_redirect($target !== '' ? $target : \admin_url('edit.php?post_type=' . CMX_BUCHUNGEN_CPT));
	exit;
});
