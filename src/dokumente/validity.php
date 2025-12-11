<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_DOK_VALID_FROM = 'cmx_dokumente_valid_from';
const CMX_DOK_VALID_TO   = 'cmx_dokumente_valid_to';

\add_action('add_meta_boxes', function() {
	\add_meta_box(
		'cmx_dokumente_validity',
		'Gültigkeit',
		__NAMESPACE__ . '\\cmx_render_dokumente_validity_metabox',
		'dokumente',
		'side',
		'default'
	);
});

function cmx_render_dokumente_validity_metabox(\WP_Post $post): void {
	wp_nonce_field('cmx_dokumente_validity_save', 'cmx_dokumente_validity_nonce');
	$from = get_post_meta($post->ID, CMX_DOK_VALID_FROM, true);
	$to   = get_post_meta($post->ID, CMX_DOK_VALID_TO, true);
	?>
	<p>
		<label for="cmx_dok_valid_from">
			<strong>Gültig von</strong>
			<a href="#" id="cmx_dok_valid_heute" style="float:right;font-weight:normal;">(heute)</a>
		</label><br>
		<input type="date" name="cmx_dok_valid[from]" id="cmx_dok_valid_from" value="<?php echo esc_attr($from); ?>" style="width:100%;">
	</p>
	<p>
		<label for="cmx_dok_valid_to"><strong>Gültig bis</strong></label><br>
		<input type="date" name="cmx_dok_valid[to]" id="cmx_dok_valid_to" value="<?php echo esc_attr($to); ?>" style="width:100%;">
	</p>
	<script>
	document.addEventListener('DOMContentLoaded', function(){
		const btn = document.getElementById('cmx_dok_valid_heute');
		const input = document.getElementById('cmx_dok_valid_from');
		if (btn && input) {
			btn.addEventListener('click', function(e){
				e.preventDefault();
				const today = new Date();
				const m = (today.getMonth()+1).toString().padStart(2,'0');
				const d = today.getDate().toString().padStart(2,'0');
				input.value = today.getFullYear() + '-' + m + '-' + d;
			});
		}
	});
	</script>
	<?php
}

\add_action('save_post_dokumente', function(int $post_id) {
	if (!isset($_POST['cmx_dokumente_validity_nonce']) || !wp_verify_nonce($_POST['cmx_dokumente_validity_nonce'], 'cmx_dokumente_validity_save')) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (wp_is_post_revision($post_id)) return;
	if (!current_user_can('edit_post', $post_id)) return;

	$data = $_POST['cmx_dok_valid'] ?? [];
	$from = isset($data['from']) ? sanitize_text_field($data['from']) : '';
	$to   = isset($data['to']) ? sanitize_text_field($data['to']) : '';

	$from = preg_match('~^\\d{4}-\\d{2}-\\d{2}$~', $from) ? $from : '';
	$to   = preg_match('~^\\d{4}-\\d{2}-\\d{2}$~', $to) ? $to : '';

	if ($from === '') {
		delete_post_meta($post_id, CMX_DOK_VALID_FROM);
	} else {
		update_post_meta($post_id, CMX_DOK_VALID_FROM, $from);
	}

	if ($to === '') {
		delete_post_meta($post_id, CMX_DOK_VALID_TO);
	} else {
		update_post_meta($post_id, CMX_DOK_VALID_TO, $to);
	}
}, 10, 1);
