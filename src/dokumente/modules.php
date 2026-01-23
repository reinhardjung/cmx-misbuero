<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Metabox: Zugehörige Datensätze (1..n) aus den Modulen auswählen.
 */
const CMX_DOK_REL_META = [
	'artikel'     => 'cmx_dokumente_artikel',
	'kontakte'    => 'cmx_dokumente_kunden',
	'projekte'    => 'cmx_dokumente_projekte',
	'kassenbuch'  => 'cmx_dokumente_kassenbuch',
	'belege'      => 'cmx_dokumente_belege',
];

\add_action('add_meta_boxes', function() {
	$cpt = basename(__DIR__);
	\add_meta_box(
		'cmx_dokumente_modules',
		'Zuordnung',
		__NAMESPACE__ . '\\cmx_render_dokumente_modules_metabox',
		$cpt,
		'side',
		'default'
	);
});

function cmx_render_dokumente_modules_metabox(\WP_Post $post): void {
	wp_nonce_field('cmx_dokumente_modules_save', 'cmx_dokumente_modules_nonce');

	$map = [
		'artikel'     => ['label' => 'Artikel',     'meta' => CMX_DOK_REL_META['artikel']],
		'kontakte'    => ['label' => 'Kontakte',    'meta' => CMX_DOK_REL_META['kontakte']],
		'projekte'    => ['label' => 'Projekte',    'meta' => CMX_DOK_REL_META['projekte']],
		'kassenbuch'  => ['label' => 'Kassenbuch',  'meta' => CMX_DOK_REL_META['kassenbuch']],
		'belege'      => ['label' => 'Belege',      'meta' => CMX_DOK_REL_META['belege']],
	];

	$printed_js = false;

	foreach ($map as $cpt => $cfg) {
		$selected = array_map('intval', (array) get_post_meta($post->ID, $cfg['meta'], true));
		if ($cpt === 'kassenbuch' && empty($selected)) {
			$selected = array_map('intval', (array) get_post_meta($post->ID, 'cmx_dokumente_buchhaltung', true));
		}
		$options  = cmx_dok_fetch_related_posts($cpt);
		$list_url = admin_url('edit.php?post_type=' . $cpt);
		$select_id = 'cmx_dok_select_' . esc_attr($cpt);
		?>
		<p style="margin: 12px 0 6px; display:flex; justify-content:space-between; align-items:center; font-size;larger;">
			<a href="<?php echo esc_url($list_url); ?>" style="font-weight:600;"><?php echo esc_html($cfg['label']); ?></a>
			<a href="#" class="cmx-dok-reset" data-target="<?php echo esc_attr($select_id); ?>">reset</a>
		</p>
		<select id="<?php echo $select_id; ?>" class="cmx-dok-module-select" data-cpt="<?php echo esc_attr($cpt); ?>" name="cmx_dokumente_rel[<?php echo esc_attr($cpt); ?>][]" multiple style="width:100%;height:200px;overflow:auto;">
			<?php foreach ($options as $opt): ?>
				<option value="<?php echo esc_attr($opt['id']); ?>"
					data-edit-url="<?php echo esc_attr(get_edit_post_link($opt['id'], 'raw')); ?>"
					<?php selected(in_array($opt['id'], $selected, true)); ?>>
					<?php echo esc_html($opt['title']); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php

		if (!$printed_js) {
			$printed_js = true;
			?>
			<script>
			document.addEventListener('DOMContentLoaded', function() {
				// Doppelklick auf Option => Datensatz öffnen
				document.querySelectorAll('.cmx-dok-module-select').forEach(function(sel){
					sel.addEventListener('dblclick', function(e){
						const opt = e.target;
						if (opt && opt.tagName === 'OPTION') {
							const url = opt.dataset.editUrl;
							if (url) {
								window.location.href = url;
							}
						}
					});
				});

				// Reset-Link: Auswahl leeren
				document.querySelectorAll('.cmx-dok-reset').forEach(function(link){
					link.addEventListener('click', function(e){
						e.preventDefault();
						const targetId = this.dataset.target;
						if (!targetId) return;
						const sel = document.getElementById(targetId);
						if (!sel) return;
						Array.from(sel.options).forEach(function(o){ o.selected = false; });
					});
				});
			});
			</script>
			<?php
		}
	}
}

/**
 * Liefert eine flache Optionsliste für einen CPT (begrenzte Anzahl für Performance).
 */
function cmx_dok_fetch_related_posts(string $cpt): array {
	$posts = get_posts([
		'post_type'      => $cpt,
		'posts_per_page' => 200,
		'post_status'    => ['publish', 'draft', 'pending', 'private'],
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
		'no_found_rows'  => true,
	]);

	$out = [];
	foreach ($posts as $pid) {
		$out[] = [
			'id'    => $pid,
			'title' => get_the_title($pid) ?: ('#' . $pid),
		];
	}
	return $out;
}

\add_action('save_post_' . basename(__DIR__), function(int $post_id) {
	if (!isset($_POST['cmx_dokumente_modules_nonce']) || !wp_verify_nonce($_POST['cmx_dokumente_modules_nonce'], 'cmx_dokumente_modules_save')) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (!current_user_can('edit_post', $post_id)) {
		return;
	}

	$rel = isset($_POST['cmx_dokumente_rel']) && is_array($_POST['cmx_dokumente_rel'])
		? $_POST['cmx_dokumente_rel']
		: [];

	$uploads_meta_key = \defined(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
		? \constant(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
		: '_cmx_dokumente_uploads';

	$prev_map = [];
	foreach (CMX_DOK_REL_META as $cpt => $meta_key) {
		$prev_ids = (array) get_post_meta($post_id, $meta_key, true);
		$prev_map[$cpt] = array_values(array_filter(array_map('intval', $prev_ids)));
	}

	foreach (CMX_DOK_REL_META as $cpt => $meta_key) {
		$ids = isset($rel[$cpt]) && is_array($rel[$cpt]) ? array_map('intval', $rel[$cpt]) : [];
		$ids = array_values(array_filter(array_unique($ids)));

		if (empty($ids)) {
			delete_post_meta($post_id, $meta_key);
		} else {
			update_post_meta($post_id, $meta_key, $ids);
		}

		$prev_ids = $prev_map[$cpt] ?? [];
		$added = array_values(array_diff($ids, $prev_ids));
		$removed = array_values(array_diff($prev_ids, $ids));

		foreach ($added as $target_id) {
			if ($target_id <= 0) {
				continue;
			}
			$existing = (array) get_post_meta($target_id, $uploads_meta_key, true);
			$existing = array_values(array_filter(array_map('intval', $existing)));
			$existing[] = $post_id;
			$existing = array_values(array_unique($existing));
			update_post_meta($target_id, $uploads_meta_key, $existing);
		}

		foreach ($removed as $target_id) {
			if ($target_id <= 0) {
				continue;
			}
			$existing = (array) get_post_meta($target_id, $uploads_meta_key, true);
			$existing = array_values(array_filter(array_map('intval', $existing)));
			$existing = array_values(array_diff($existing, [$post_id]));
			if (empty($existing)) {
				delete_post_meta($target_id, $uploads_meta_key);
			} else {
				update_post_meta($target_id, $uploads_meta_key, $existing);
			}
		}
	}
});
