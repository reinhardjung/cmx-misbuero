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

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_status_meta_key')) {
	function cmx_carent_status_meta_key(string $constant, string $fallback): string {
		return \defined(__NAMESPACE__ . '\\' . $constant)
			? (string) \constant(__NAMESPACE__ . '\\' . $constant)
			: $fallback;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_status_clean_date')) {
	function cmx_carent_status_clean_date(mixed $value): string {
		$value = \trim((string) $value);
		if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && \strtotime($value) !== false) {
			return $value;
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_status_clean_int_string')) {
	function cmx_carent_status_clean_int_string(mixed $value): string {
		$value = \trim((string) $value);
		if ($value === '') {
			return '';
		}

		$value = \preg_replace('/[^\d-]+/', '', $value);
		$value = \is_string($value) ? \trim($value) : '';
		if ($value === '' || $value === '-') {
			return '';
		}

		return (string) (int) $value;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_status_tracking_errors')) {
	function cmx_carent_status_tracking_errors(int $post_id): array {
		$start_date_key = cmx_carent_status_meta_key('CMX_CARENT_UEBERNAHME_DATUM_META', '_cmx_carent_uebernahme_datum');
		$end_date_key = cmx_carent_status_meta_key('CMX_CARENT_RUECKGABE_DATUM_META', '_cmx_carent_rueckgabe_datum');
		$km_start_key = cmx_carent_status_meta_key('CMX_CARENT_FAHRZEUG_KM_STAND_UEBERNAHME_META', '_cmx_carent_fahrzeug_km_stand_uebernahme');
		$km_end_key = cmx_carent_status_meta_key('CMX_CARENT_FAHRZEUG_KM_STAND_RUECKGABE_META', '_cmx_carent_fahrzeug_km_stand_rueckgabe');

		$start_date = cmx_carent_status_clean_date(\get_post_meta($post_id, $start_date_key, true));
		$end_date = cmx_carent_status_clean_date(\get_post_meta($post_id, $end_date_key, true));
		$km_start = cmx_carent_status_clean_int_string(\get_post_meta($post_id, $km_start_key, true));
		$km_end = cmx_carent_status_clean_int_string(\get_post_meta($post_id, $km_end_key, true));

		$errors = [];
		if ($start_date === '') {
			$errors[] = 'Übernahme-Datum fehlt.';
		}
		if ($end_date === '') {
			$errors[] = 'Rückgabe-Datum fehlt.';
		}
		if ($start_date !== '' && $end_date !== '' && \strtotime($end_date) < \strtotime($start_date)) {
			$errors[] = 'Rückgabe-Datum darf nicht vor dem Übernahme-Datum liegen.';
		}
		if ($km_start === '') {
			$errors[] = 'KM-Stand Übernahme fehlt.';
		}
		if ($km_end === '') {
			$errors[] = 'KM-Stand Rückgabe fehlt.';
		}
		if ($km_start !== '' && $km_end !== '' && (int) $km_end < (int) $km_start) {
			$errors[] = 'KM-Stand Rückgabe darf nicht kleiner als KM-Stand Übernahme sein.';
		}

		return $errors;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_status_store_notice')) {
	function cmx_carent_status_store_notice(int $post_id, array $errors): void {
		if ($errors === []) {
			return;
		}

		$user_id = (int) \get_current_user_id();
		if ($user_id <= 0) {
			return;
		}

		\set_transient('cmx_carent_status_tracking_notice_' . $user_id, [
			'post_id' => $post_id,
			'errors' => \array_values(\array_map('strval', $errors)),
		], 60);
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

\add_action('save_post_carent', function (int $post_id, \WP_Post $post): void {
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

	$current = \sanitize_key((string) \get_post_meta($post_id, CMX_CARENT_STATUS_META, true));
	if ($current !== 'abgeschlossen') {
		return;
	}

	$errors = cmx_carent_status_tracking_errors($post_id);
	if ($errors === []) {
		return;
	}

	\update_post_meta($post_id, CMX_CARENT_STATUS_META, 'offen');
	cmx_carent_status_store_notice($post_id, $errors);
}, 900, 2);

\add_action('admin_notices', function (): void {
	$user_id = (int) \get_current_user_id();
	if ($user_id <= 0) {
		return;
	}

	$key = 'cmx_carent_status_tracking_notice_' . $user_id;
	$notice = \get_transient($key);
	if (!\is_array($notice)) {
		return;
	}

	\delete_transient($key);
	$post_id = (int) ($notice['post_id'] ?? 0);
	$errors = \array_filter(\array_map('strval', (array) ($notice['errors'] ?? [])));
	if ($errors === []) {
		return;
	}

	echo '<div class="notice notice-error is-dismissible"><p><strong>CaRent konnte nicht abgeschlossen werden.</strong> Der Status wurde wieder auf offen gesetzt.</p><ul style="margin-left:18px;list-style:disc;">';
	foreach ($errors as $error) {
		echo '<li>' . \esc_html($error) . '</li>';
	}
	if ($post_id > 0) {
		echo '<li><a href="' . \esc_url((string) \get_edit_post_link($post_id, '')) . '">Vermietung bearbeiten</a></li>';
	}
	echo '</ul></div>';
});
