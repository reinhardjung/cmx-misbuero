<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_STATUS_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_STATUS_META', '_cmx_carent_status');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_status_options')) {
	function cmx_carent_status_options(): array {
		return [
			'offen' => 'offen',
			'abgeschlossen' => 'abgeschlossen',
		];
	}
}

\add_action('add_meta_boxes', function (): void {
	if (!\post_type_exists('carent')) {
		return;
	}

	\add_meta_box(
		'cmx_carent_status_side',
		'Status',
		__NAMESPACE__ . '\\cmx_render_carent_status_metabox',
		'carent',
		'side',
		'default'
	);
}, 25);

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_carent_status_metabox')) {
	function cmx_render_carent_status_metabox(\WP_Post $post): void {
		\wp_nonce_field('cmx_carent_status_save', 'cmx_carent_status_nonce');

		$current = \sanitize_key((string) \get_post_meta($post->ID, CMX_CARENT_STATUS_META, true));
		$options = cmx_carent_status_options();
		if (!isset($options[$current])) {
			$current = 'offen';
		}

		echo '<style>
			.cmx-carent-status-options{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
			.cmx-carent-status-radio{margin:0}
			.cmx-carent-status-radio label{display:flex;align-items:center;gap:6px}
		</style>';

		echo '<div class="cmx-carent-status-options">';
		foreach ($options as $value => $label) {
			echo '<p class="cmx-carent-status-radio">';
			echo '<label>';
			echo '<input type="radio" name="cmx_carent_status" value="' . \esc_attr($value) . '" ' . \checked($current, $value, false) . '>';
			echo '<span>' . \esc_html($label) . '</span>';
			echo '</label>';
			echo '</p>';
		}
		echo '</div>';
	}
}

\add_action('save_post_carent', function (int $post_id, \WP_Post $post, bool $update): void {
	unset($update);

	if (!isset($_POST['cmx_carent_status_nonce']) || !\wp_verify_nonce((string) \wp_unslash($_POST['cmx_carent_status_nonce']), 'cmx_carent_status_save')) {
		return;
	}
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) {
		return;
	}
	if ((string) $post->post_type !== 'carent') {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

	$value = isset($_POST['cmx_carent_status'])
		? \sanitize_key((string) \wp_unslash($_POST['cmx_carent_status']))
		: '';
	$options = cmx_carent_status_options();

	if ($value === '' || !isset($options[$value])) {
		\delete_post_meta($post_id, CMX_CARENT_STATUS_META);
		return;
	}

	\update_post_meta($post_id, CMX_CARENT_STATUS_META, $value);
}, 20, 3);
