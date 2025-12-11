<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Metabox: Verlinkt alle Dokumente (CPT "dokumente") für schnellen Sprung.
 */
\add_action('add_meta_boxes', function() {
	$title = sprintf(
		'<a href="%s"><strong style="font-size:larger;">Dokumente</strong></a>',
		esc_url(admin_url('edit.php?post_type=dokumente'))
	);

	\add_meta_box(
		'cmx_belege_dokumente',
		$title,
		__NAMESPACE__ . '\\cmx_render_belege_dokumente_metabox',
		'belege',
		'side',
		'default'
	);
});

function cmx_render_belege_dokumente_metabox(\WP_Post $post): void {
	$meta_key = defined(__NAMESPACE__ . '\\CMX_DOK_REL_META')
		? (CMX_DOK_REL_META['belege'] ?? 'cmx_dokumente_belege')
		: 'cmx_dokumente_belege';

	$docs = get_posts([
		'post_type'      => 'dokumente',
		'post_status'    => ['publish', 'draft', 'pending', 'private'],
		'posts_per_page' => 200,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
		'fields'         => 'ids',
		'meta_query'     => [
			[
				'key'     => $meta_key,
				'value'   => $post->ID,
				'compare' => 'LIKE', // Array-serialisiert, daher LIKE auf ID
			],
		],
	]);

	if (!$docs) {
		echo '<p>wurden noch nicht zugeordnet.</p>';
		return;
	}

	echo '<ul style="margin:0;padding-left:16px;max-height:240px;overflow:auto;">';
	foreach ($docs as $doc_id) {
		// Double-check: Nur wenn Zuordnung wirklich die aktuelle belege-ID enthält.
		$ids = (array) get_post_meta($doc_id, $meta_key, true);
		if (!in_array((int) $post->ID, array_map('intval', $ids), true)) {
			continue;
		}

		$title = get_the_title($doc_id) ?: ('#' . $doc_id);
		$link  = get_edit_post_link($doc_id, 'true');
		$tax   = cmx_tax_key('dokumente', 'Kategorien');
		$terms = get_the_terms($doc_id, $tax);
		$cats  = [];
		if (!is_wp_error($terms) && !empty($terms)) {
			foreach ($terms as $t) {
				$cats[] = $t->name;
			}
		}
		// $tooltip = $cats ? ($title . ' - ' . implode(', ', $cats)) : $title;
		// evtl. noch ohne den Dateinamen im Tooltip
		$tooltip = $cats ? ($title . ' - ' . implode(', ', $cats)) : $title;
		printf(
			'<li style="margin:4px -10px;"><a href="%s" title="%s">%s</a></li>',
			esc_url($link ?: '#'),
			esc_attr($tooltip),
			esc_html($title)
		);
	}
	echo '</ul>';
}
