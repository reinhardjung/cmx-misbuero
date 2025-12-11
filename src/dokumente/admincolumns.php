<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

\add_filter('manage_dokumente_posts_columns', function($cols) {
	$after_title = [
		'cmx_status'     => 'Status',
		'cmx_valid_from' => 'Gültig von',
		'cmx_valid_to'   => 'Gültig bis',
		'cmx_modules'    => 'Zugeordnete Module',
		'cmx_cats'       => 'Kategorien',
	];
	$preview = ['cmx_thumb' => 'Vorschau'];

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
	switch ($col) {
		case 'cmx_thumb':
			$thumb = get_the_post_thumbnail($post_id, [60,60], ['style'=>'width:60px;height:60px;object-fit:cover;']);
			// echo $thumb ?: '&mdash;';
			echo $thumb ?: '';
			break;
		case 'cmx_status':
			$val = get_post_meta($post_id, CMX_DOK_STATUS_META, true);
			$options = CMX_DOK_STATUS_OPTIONS ?? [];
			echo $val && isset($options[$val]) ? esc_html($options[$val]) : '&mdash;';
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
