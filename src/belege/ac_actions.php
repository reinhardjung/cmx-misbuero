<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!defined(__NAMESPACE__ . '\\CMX_PT_BELEGE')) {
	define(__NAMESPACE__ . '\\CMX_PT_BELEGE', 'belege');
}

$add_action_columns = static function (array $columns): array {
	unset($columns['cmx_beleg_pdf_action'], $columns['cmx_beleg_mail_action']);

	$columns['cmx_beleg_pdf_action'] = 'PDF';

	return $columns;
};

\add_filter('manage_edit-' . CMX_PT_BELEGE . '_columns', $add_action_columns, 999);
\add_filter('manage_' . CMX_PT_BELEGE . '_posts_columns', $add_action_columns, 999);

\add_action('manage_' . CMX_PT_BELEGE . '_posts_custom_column', function (string $column, int $post_id): void {
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

	echo '<span class="cmx-beleg-action-icons">';
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
			.wp-list-table th.column-cmx_beleg_pdf_action {
				width: 74px;
				text-align: center;
			}
			.wp-list-table td.column-cmx_beleg_pdf_action {
				text-align: center;
				vertical-align: top;
			}
			.cmx-beleg-action-icons {
				display: inline-grid;
				grid-template-columns: 18px 18px;
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
			.cmx-beleg-action-pdf {
				color: #a42c24;
			}
			.cmx-beleg-action-upload {
				color: #2271b1;
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
