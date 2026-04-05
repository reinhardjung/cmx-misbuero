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
	function cmx_carent_admin_article_label(int $artikel_id): string {
		if ($artikel_id <= 0 || !\get_post_status($artikel_id)) {
			return '';
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
		$label = cmx_carent_admin_article_label($artikel_id);
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
