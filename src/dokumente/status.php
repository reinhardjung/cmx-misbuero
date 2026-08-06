<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_DOK_STATUS_META = 'cmx_dokumente_status';
const CMX_DOK_STATUS_OPTIONS = [
	'in_arbeit'  => 'in Arbeit',
	'pruefen'    => 'prüfen',
	'freigegeben'=> 'freigegeben',
	'abgeschlossen'=> 'abgeschlossen',
	'archiviert' => 'archiviert',
];

\add_action('add_meta_boxes', function() {
	\add_meta_box(
		'cmx_dokumente_status',
		'Status',
		__NAMESPACE__ . '\\cmx_render_dokumente_status_metabox',
		'dokumente',
		'side',
		'default'
	);
});

function cmx_render_dokumente_status_metabox(\WP_Post $post): void {
	wp_nonce_field('cmx_dokumente_status_save', 'cmx_dokumente_status_nonce');
	$current = get_post_meta($post->ID, CMX_DOK_STATUS_META, true);
	?>
	<label for="cmx_dokumente_status_select" class="screen-reader-text">Status</label>
	<select name="cmx_dokumente_status" id="cmx_dokumente_status_select" style="width:100%;">
		<option value="">– bitte wählen –</option>
		<?php foreach (CMX_DOK_STATUS_OPTIONS as $key => $label): ?>
			<option value="<?php echo esc_attr($key); ?>" <?php selected($current, $key); ?>>
				<?php echo esc_html($label); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<?php
}

\add_action('save_post_dokumente', function(int $post_id) {
	if (!isset($_POST['cmx_dokumente_status_nonce']) || !wp_verify_nonce($_POST['cmx_dokumente_status_nonce'], 'cmx_dokumente_status_save')) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (wp_is_post_revision($post_id)) return;
	if (!current_user_can('edit_post', $post_id)) return;

	$value = isset($_POST['cmx_dokumente_status']) ? sanitize_key($_POST['cmx_dokumente_status']) : '';
	if ($value === '' || !array_key_exists($value, CMX_DOK_STATUS_OPTIONS)) {
		delete_post_meta($post_id, CMX_DOK_STATUS_META);
		return;
	}
	update_post_meta($post_id, CMX_DOK_STATUS_META, $value);
}, 10, 1);
