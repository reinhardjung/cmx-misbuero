<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


function cmxbu_render_beleg_send_metabox(\WP_Post $post): void {
	$post_id   = (int) $post->ID;
	$post_info = cmxbu_get_beleg_pdf_for_send($post_id);
	$has_pdf   = $post_info['found'];

	$href = $has_pdf ? esc_url(admin_url("admin-post.php?action=cmxbu_beleg_send&post_id={$post_id}")) : '#';
	$disable_attr = $has_pdf ? '' : 'pointer-events:none; opacity:0.5;';
	?>
		<a href="<?php echo $href; ?>"class="button button-secondary alignleft <?php echo !$has_pdf ? 'disabled' : ''; ?>"style="<?php echo $disable_attr; ?>">versenden</a>
	<?php
}


function cmxbu_get_beleg_pdf_for_send(int $post_id): array {
	$thePath = get_post_meta($post_id, '_cmx_beleg_pdf_path', true);

	if (is_string($thePath) && $thePath !== '' && is_file($thePath)) {
		return [
			'found'    => true,
			'type'     => 'local',
			'path'     => $thePath,
			'filename' => basename($thePath),
		];
	}

	return [
		'found'    => false,
		'type'     => 'none',
		'path'     => '',
		'filename' => '',
	];
}
