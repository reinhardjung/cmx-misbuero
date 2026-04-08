<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!defined(__NAMESPACE__ . '\\CMX_PT_BELEGE')) {
	define(__NAMESPACE__ . '\\CMX_PT_BELEGE', 'belege');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_latest_related_doc_url')) {
	function cmx_beleg_latest_related_doc_url(int $post_id): string {
		if ($post_id <= 0) {
			return '';
		}

		$uploads_meta_key = \defined(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
			: '_cmx_dokumente_uploads';
		$self_meta_key = \defined(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
			: '_cmx_dokumente_files';

		$doc_ids = [];
		foreach ((array) \get_post_meta($post_id, $uploads_meta_key, true) as $raw_doc_id) {
			$doc_id = (int) $raw_doc_id;
			if ($doc_id > 0) {
				$doc_ids[] = $doc_id;
			}
		}
		$doc_ids = \array_values(\array_unique($doc_ids));
		if (empty($doc_ids)) {
			return '';
		}

		for ($i = \count($doc_ids) - 1; $i >= 0; $i--) {
			$doc_id = (int) $doc_ids[$i];
			if ($doc_id <= 0 || (string) \get_post_type($doc_id) !== 'dokumente') {
				continue;
			}

			$file_rel = (string) \get_post_meta($doc_id, '_cmx_dokumente_file_path', true);
			if ($file_rel === '') {
				$self_files = (array) \get_post_meta($doc_id, $self_meta_key, true);
				$self_files = \array_values(\array_filter($self_files, static function ($value): bool {
					return \is_string($value) && $value !== '';
				}));
				if (!empty($self_files)) {
					$file_rel = (string) $self_files[\count($self_files) - 1];
				}
			}

			$file_rel = \ltrim(\str_replace('\\', '/', $file_rel), '/');
			if ($file_rel !== '') {
				$abs = \wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads/' . $file_rel));
				if ($abs !== '' && \is_file($abs)) {
					return (string) \content_url('/uploads/' . $file_rel);
				}
			}

			$edit_url = (string) \get_edit_post_link($doc_id, 'raw');
			if ($edit_url !== '') {
				return $edit_url;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_has_recurring_run')) {
	function cmx_beleg_has_recurring_run(int $post_id): bool {
		if ($post_id <= 0) {
			return false;
		}

		$frequency = \sanitize_key((string) \get_post_meta($post_id, '_cmx_abo_frequency', true));

		return $frequency !== '' && $frequency !== 'never';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_recurring_stop_url')) {
	function cmx_beleg_recurring_stop_url(int $post_id): string {
		if ($post_id <= 0) {
			return '';
		}

		$redirect_to = \admin_url('edit.php?post_type=' . CMX_PT_BELEGE);
		if (isset($_SERVER['REQUEST_URI'])) {
			$request_uri = (string) \wp_unslash($_SERVER['REQUEST_URI']);
			if ($request_uri !== '' && \strpos($request_uri, '/wp-admin/') === 0) {
				$redirect_to = (string) \home_url($request_uri);
			}
		}

		$url = \admin_url('admin-post.php?action=cmx_beleg_abo_stop&post_id=' . (int) $post_id);
		if ($redirect_to !== '') {
			$url .= '&redirect_to=' . \rawurlencode($redirect_to);
		}

		return (string) \wp_nonce_url($url, 'cmx_beleg_abo_stop_' . $post_id);
	}
}

$add_action_columns = static function (array $columns): array {
	unset($columns['cmx_beleg_repeat_action'], $columns['cmx_beleg_pdf_action'], $columns['cmx_beleg_mail_action']);

	$columns['cmx_beleg_repeat_action'] = 'Abo';
	$columns['cmx_beleg_pdf_action'] = 'PDF';

	return $columns;
};

\add_filter('manage_edit-' . CMX_PT_BELEGE . '_columns', $add_action_columns, 999);
\add_filter('manage_' . CMX_PT_BELEGE . '_posts_columns', $add_action_columns, 999);

\add_action('manage_' . CMX_PT_BELEGE . '_posts_custom_column', function (string $column, int $post_id): void {
	if ($column === 'cmx_beleg_repeat_action') {
		if (!cmx_beleg_has_recurring_run($post_id)) {
			echo '<span class="cmx-beleg-action-placeholder" aria-hidden="true"></span>';
			return;
		}

		$stop_url = cmx_beleg_recurring_stop_url($post_id);
		if ($stop_url === '') {
			echo '<span class="cmx-beleg-action-placeholder" aria-hidden="true"></span>';
			return;
		}

		echo '<a href="' . \esc_url($stop_url) . '" class="cmx-beleg-repeat-action" title="Wiederkehrenden Lauf stoppen" aria-label="Wiederkehrenden Lauf stoppen" onclick="return window.confirm(\'Wiederkehrenden Lauf fuer diesen Beleg stoppen?\');"><span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span></a>';
		return;
	}

	if ($column !== 'cmx_beleg_pdf_action') {
		return;
	}

	$post = \get_post($post_id);
	if (!$post instanceof \WP_Post || $post->post_type !== CMX_PT_BELEGE) {
		echo '';
		return;
	}

	$has_pdf = false;
	if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_pdf_paths')) {
		[, $pdf_abs_path] = cmxbu_get_beleg_pdf_paths($post);
		$has_pdf = \is_file((string) $pdf_abs_path);
	}

	$has_upload = false;
	if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_primary_upload_abs_path')) {
		$upload_abs_path = (string) cmxbu_get_beleg_primary_upload_abs_path($post_id);
		$has_upload = \is_file($upload_abs_path);
	}

	$token = \function_exists(__NAMESPACE__ . '\\cmxbu_get_stable_token')
		? (string) cmxbu_get_stable_token($post_id)
		: '';
	$pdf_url = $token !== '' ? (string) \add_query_arg('beleg', $token, \home_url('/')) : '';
	$upload_url = $token !== '' ? (string) \add_query_arg(['beleg' => $token, 'quelle' => 'upload'], \home_url('/')) : '';
	$disabled_title = $has_pdf ? 'PDF nicht verfügbar' : 'PDF nicht vorhanden';
	$related_doc_url = \function_exists(__NAMESPACE__ . '\\cmx_beleg_latest_related_doc_url')
		? (string) cmx_beleg_latest_related_doc_url($post_id)
		: '';

	echo '<span class="cmx-beleg-action-icons">';
	if ($related_doc_url !== '') {
		echo '<a href="' . \esc_url($related_doc_url) . '" target="_blank" rel="noopener noreferrer" title="Zugeordnetes Dokument anzeigen" class="cmx-beleg-action-icon cmx-beleg-action-related" aria-label="Zugeordnetes Dokument anzeigen"><span class="dashicons dashicons-pdf" aria-hidden="true"></span></a>';
	} else {
		echo '<span class="cmx-beleg-action-placeholder" aria-hidden="true"></span>';
	}

	if ($upload_url !== '' && $has_upload) {
		echo '<a href="' . \esc_url($upload_url) . '" target="_blank" rel="noopener noreferrer" title="Upload-Dokument anzeigen" class="cmx-beleg-action-icon cmx-beleg-action-upload" aria-label="Upload-Dokument anzeigen"><span class="dashicons dashicons-pdf" aria-hidden="true"></span></a>';
	} else {
		echo '<span class="cmx-beleg-action-placeholder" aria-hidden="true"></span>';
	}

	if ($pdf_url !== '' && $has_pdf) {
		echo '<a href="' . \esc_url($pdf_url) . '" target="_blank" rel="noopener noreferrer" title="Anzeigen als PDF (DL/C5/C4)" class="cmx-beleg-action-icon cmx-beleg-action-pdf" aria-label="Anzeigen als PDF (DL/C5/C4)"><span class="dashicons dashicons-pdf" aria-hidden="true"></span></a>';
	} else {
		echo '<span class="cmx-beleg-action-icon cmx-beleg-action-disabled cmx-beleg-action-pdf" title="' . \esc_attr($disabled_title) . '"><span class="dashicons dashicons-pdf" aria-hidden="true"></span></span>';
	}
	echo '</span>';
}, 20, 2);

\add_action('admin_head-edit.php', function (): void {
	if (!isset($_GET['post_type']) || (string) $_GET['post_type'] !== CMX_PT_BELEGE) {
		return;
	}

	\wp_enqueue_style('dashicons');

		echo '<style>
			.wp-list-table th.column-cmx_beleg_repeat_action {
				width: 46px;
				text-align: center;
			}
			.wp-list-table td.column-cmx_beleg_repeat_action {
				text-align: center;
				vertical-align: top;
			}
			.wp-list-table th.column-cmx_beleg_pdf_action {
				width: 98px;
				text-align: center;
			}
			.wp-list-table td.column-cmx_beleg_pdf_action {
				text-align: center;
				vertical-align: top;
			}
			.cmx-beleg-action-icons {
				display: inline-grid;
				grid-template-columns: 18px 18px 18px;
				column-gap: 6px;
				align-items: start;
				justify-items: center;
			}
			.cmx-beleg-action-icon {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				text-decoration: none;
				min-height: 20px;
				vertical-align: top;
			}
		.cmx-beleg-action-icon .dashicons {
			width: 18px;
			height: 18px;
			font-size: 18px;
			line-height: 18px;
		}
			.cmx-beleg-repeat-action {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				color: #cc4b00;
				text-decoration: none;
				min-height: 20px;
			}
			.cmx-beleg-repeat-action:hover,
			.cmx-beleg-repeat-action:focus {
				color: #8a3200;
			}
			.cmx-beleg-action-pdf {
				color: #a42c24;
			}
			.cmx-beleg-action-upload {
				color: #2271b1;
			}
			.cmx-beleg-action-related {
				color: #111111;
			}
			.cmx-beleg-action-disabled {
				opacity: 0.35;
			}
			.cmx-beleg-action-placeholder {
				display: inline-block;
				width: 18px;
				height: 18px;
			}
		</style>';
});
