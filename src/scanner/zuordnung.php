<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_SCANNER_ZUORDNUNG_TYP_META = '_cmx_scanner_zuordnung_typ';
const CMX_SCANNER_ZUORDNUNG_TYPES = [
	'kontakte'  => 'Kontakte',
	'artikel'   => 'Artikel',
	'dokumente' => 'Dokumente',
	'projekte'  => 'Projekte',
	'belege'    => 'Belege',
];

function cmx_scanner_cpt_slug(): string {
	return basename(__DIR__);
}

function cmx_scanner_get_selected_zuordnung_type(int $post_id): string {
	$type = (string) \get_post_meta($post_id, CMX_SCANNER_ZUORDNUNG_TYP_META, true);
	return \array_key_exists($type, CMX_SCANNER_ZUORDNUNG_TYPES) ? $type : '';
}

function cmx_scanner_get_rel_meta_keys(): array {
	return [
		'kontakte'  => '_cmx_scanner_rel_kontakte_id',
		'artikel'   => '_cmx_scanner_rel_artikel_id',
		'dokumente' => '_cmx_scanner_rel_dokumente_id',
		'projekte'  => '_cmx_scanner_rel_projekte_id',
		'belege'    => '_cmx_scanner_rel_belege_id',
	];
}

function cmx_scanner_fetch_relation_options(string $target_post_type, int $limit = 200): array {
	$ids = \get_posts([
		'post_type'      => $target_post_type,
		'post_status'    => ['publish', 'draft', 'pending', 'private'],
		'posts_per_page' => $limit,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'fields'         => 'ids',
		'no_found_rows'  => true,
	]);

	$options = [];
	foreach ($ids as $id) {
		$id = (int) $id;
		if ($id <= 0) {
			continue;
		}
		$title = \get_the_title($id);
		if ($title === '') {
			$title = '#' . $id;
		}
		$options[] = [
			'id'    => $id,
			'label' => $title . ' (#' . $id . ')',
		];
	}
	return $options;
}

function cmx_scanner_render_relation_select_box(\WP_Post $post, string $target_type, string $meta_key, string $nonce_action, string $nonce_name, string $empty_label = 'Kein Eintrag'): void {
	if ($post->post_type !== cmx_scanner_cpt_slug()) {
		return;
	}

	\wp_nonce_field($nonce_action, $nonce_name);
	$current = (int) \get_post_meta($post->ID, $meta_key, true);
	$options = cmx_scanner_fetch_relation_options($target_type);

	echo '<p style="margin:0 0 8px;"><em>Zuordnung zu: ' . \esc_html(ucfirst($target_type)) . '</em></p>';
	echo '<select name="' . \esc_attr($meta_key) . '" style="width:100%;">';
	echo '<option value="0">' . \esc_html($empty_label) . '</option>';
	foreach ($options as $opt) {
		echo '<option value="' . \esc_attr((string) $opt['id']) . '" ' . \selected($current, (int) $opt['id'], false) . '>' . \esc_html((string) $opt['label']) . '</option>';
	}
	echo '</select>';

	if (empty($options)) {
		echo '<p style="margin:8px 0 0;"><em>Keine Datensätze gefunden.</em></p>';
	}
}

\add_action('add_meta_boxes', function (): void {
	\add_meta_box(
		'cmx_scanner_zuordnung',
		'Zuordnung',
		__NAMESPACE__ . '\\cmx_scanner_render_zuordnung_metabox',
		cmx_scanner_cpt_slug(),
		'side',
		'default'
	);
});

function cmx_scanner_render_zuordnung_metabox(\WP_Post $post): void {
	\wp_nonce_field('cmx_scanner_zuordnung_save', 'cmx_scanner_zuordnung_nonce');
	$current = cmx_scanner_get_selected_zuordnung_type((int) $post->ID);

	echo '<label for="cmx_scanner_zuordnung_typ" class="screen-reader-text">Zuordnung</label>';
	echo '<select id="cmx_scanner_zuordnung_typ" name="cmx_scanner_zuordnung_typ" style="width:100%;">';
	echo '<option value="">- bitte wählen -</option>';
	foreach (CMX_SCANNER_ZUORDNUNG_TYPES as $key => $label) {
		echo '<option value="' . \esc_attr($key) . '" ' . \selected($current, $key, false) . '>' . \esc_html($label) . '</option>';
	}
	echo '</select>';
	echo '<p style="margin:8px 0 0;"><em>Nach dem Speichern erscheint die passende Detail-Metabox.</em></p>';
}

\add_action('save_post_scanner', function (int $post_id, \WP_Post $post): void {
	if ($post->post_type !== cmx_scanner_cpt_slug()) {
		return;
	}
	if (!isset($_POST['cmx_scanner_zuordnung_nonce']) || !\wp_verify_nonce((string) $_POST['cmx_scanner_zuordnung_nonce'], 'cmx_scanner_zuordnung_save')) {
		return;
	}
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (\wp_is_post_revision($post_id)) {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

	$type = isset($_POST['cmx_scanner_zuordnung_typ']) ? \sanitize_key((string) $_POST['cmx_scanner_zuordnung_typ']) : '';
	if (!\array_key_exists($type, CMX_SCANNER_ZUORDNUNG_TYPES)) {
		$type = '';
	}

	if ($type === '') {
		\delete_post_meta($post_id, CMX_SCANNER_ZUORDNUNG_TYP_META);
	} else {
		\update_post_meta($post_id, CMX_SCANNER_ZUORDNUNG_TYP_META, $type);
	}

	$rel_meta_keys = cmx_scanner_get_rel_meta_keys();
	foreach ($rel_meta_keys as $key => $meta_key) {
		if ($key !== $type) {
			\delete_post_meta($post_id, $meta_key);
		}
	}
}, 10, 2);
