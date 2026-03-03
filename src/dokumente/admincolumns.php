<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

\add_filter('manage_dokumente_posts_columns', function($cols) {
	$after_title = [
		'cmx_status'     => 'Status',
		'cmx_valid_from' => 'Gültig von',
		'cmx_valid_to'   => 'Gültig bis',
		'cmx_modules'    => 'Zugeordnete Module',
		'cmx_cats'       => 'Kategorien',
		'cmx_rel_k'      => 'K',
		'cmx_rel_a'      => 'A',
		'cmx_rel_b'      => 'B',
		'cmx_rel_p'      => 'P',
	];
	$preview = ['cmx_thumb' => 'Anhang'];

	$new = [];
	foreach ($cols as $key => $label) {
		$new[$key] = $label;
		if ($key === 'title') {
			$new += $after_title;
		}
	}
	// Vorschau am Ende anhängen
	$new += $preview;
	return $new;
});

\add_action('manage_dokumente_posts_custom_column', function($col, $post_id) {
	$relation_cols = [
		'cmx_rel_k' => ['type' => 'kontakte', 'fallback' => 'cmx_dokumente_kunden'],
		'cmx_rel_a' => ['type' => 'artikel',  'fallback' => 'cmx_dokumente_artikel'],
		'cmx_rel_b' => ['type' => 'belege',   'fallback' => 'cmx_dokumente_belege'],
		'cmx_rel_p' => ['type' => 'projekte', 'fallback' => 'cmx_dokumente_projekte'],
	];

	if (isset($relation_cols[$col])) {
		$type = (string) ($relation_cols[$col]['type'] ?? '');
		$meta_key = (string) ($relation_cols[$col]['fallback'] ?? '');
		$map = \defined(__NAMESPACE__ . '\\CMX_DOK_REL_META') ? CMX_DOK_REL_META : [];
		if ($type !== '' && \is_array($map) && isset($map[$type]) && \is_string($map[$type]) && $map[$type] !== '') {
			$meta_key = (string) $map[$type];
		}

		$ids = (array) get_post_meta($post_id, $meta_key, true);
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		$ids = array_values(array_filter($ids, static function (int $id): bool {
			return $id > 0 && (string) get_post_status($id) !== '';
		}));
		$count = count($ids);
		if ($count <= 0) {
			echo '';
			return;
		}
		if ($count === 1) {
			$target_id = (int) $ids[0];
			$edit_url = (string) get_edit_post_link($target_id, '');
			if ($edit_url !== '') {
				$target_title = trim((string) get_the_title($target_id));
				if ($target_title === '') {
					$target_title = '(ohne Titel #' . $target_id . ')';
				}
				echo '<a href="' . esc_url($edit_url) . '" title="' . esc_attr($target_title) . '" aria-label="' . esc_attr($target_title) . '">' . esc_html('1') . '</a>';
				return;
			}
		}
		echo esc_html((string) $count);
		return;
	}

	switch ($col) {
		case 'cmx_thumb':
			$local = get_post_meta($post_id, '_cmx_local_image_dokumente_url', true);
			$thumb = get_the_post_thumbnail($post_id, [60,60], ['style'=>'width:60px;height:60px;object-fit:cover;']);
			if ($local) {
				// echo '<img src="'.esc_url($local).'" style="width:60px;height:60px;object-fit:cover;border:1px solid #ddd;padding:2px;background:#fff;" alt="" />';
				echo ' <span class="dashicons dashicons-yes" title="Bild vorhanden"></span>';
			} elseif ($thumb) {
				echo $thumb;
				echo ' <span class="dashicons dashicons-yes" title="Bild vorhanden"></span>';
			} else {
				echo '';
			}
			break;
		case 'cmx_status':
			$val = get_post_meta($post_id, CMX_DOK_STATUS_META, true);
			$options = CMX_DOK_STATUS_OPTIONS ?? [];
			echo $val && isset($options[$val]) ? esc_html($options[$val]) : '';
			break;
		case 'cmx_valid_from':
			$val = get_post_meta($post_id, CMX_DOK_VALID_FROM, true);
			if ($val) {
				$ts = strtotime($val);
				$fmt = get_option('date_format') ?: 'Y-m-d';
				echo esc_html($ts ? date_i18n($fmt, $ts) : $val);
			} // else { echo '&mdash;'; }
			break;
		case 'cmx_valid_to':
			$val = get_post_meta($post_id, CMX_DOK_VALID_TO, true);
			if ($val) {
				$ts = strtotime($val);
				$fmt = get_option('date_format') ?: 'Y-m-d';
				echo esc_html($ts ? date_i18n($fmt, $ts) : $val);
			} // else { echo '&mdash;'; }
			break;
		case 'cmx_cats':
			$tax = cmx_tax_key('dokumente', 'Kategorien');
			$terms = get_the_terms($post_id, $tax);
			if (!is_wp_error($terms) && !empty($terms)) {
				echo esc_html(implode(', ', wp_list_pluck($terms, 'name')));
			} // else { echo '&mdash;'; }
			break;
		case 'cmx_modules':
			$map = defined(__NAMESPACE__ . '\\CMX_DOK_REL_META') ? CMX_DOK_REL_META : [];
			$total = 0;
			foreach ($map as $meta_key) {
				$ids = (array) get_post_meta($post_id, $meta_key, true);
				$total += count(array_filter(array_map('intval', $ids)));
			}
			// echo $total ? esc_html($total) : '&mdash;';
			echo $total ? esc_html($total) : '';
			break;
	}
}, 10, 2);

\add_filter('manage_edit-dokumente_sortable_columns', function($cols) {
	$cols['cmx_status'] = 'cmx_status';
	$cols['cmx_valid_from'] = 'cmx_valid_from';
	$cols['cmx_valid_to'] = 'cmx_valid_to';
	return $cols;
});

\add_action('admin_head-edit.php', function () {
	if (!isset($_GET['post_type']) || (string) $_GET['post_type'] !== 'dokumente') {
		return;
	}

	echo '<style>
		.wp-list-table th.column-cmx_rel_k,
		.wp-list-table th.column-cmx_rel_a,
		.wp-list-table th.column-cmx_rel_b,
		.wp-list-table th.column-cmx_rel_p {
			width: 42px;
			text-align: center;
		}
		.wp-list-table td.column-cmx_rel_k,
		.wp-list-table td.column-cmx_rel_a,
		.wp-list-table td.column-cmx_rel_b,
		.wp-list-table td.column-cmx_rel_p {
			text-align: center;
		}
	</style>';
});
