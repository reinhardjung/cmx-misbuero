<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


function cmxbu_render_beleg_download_metabox(\WP_Post $post) {
	$post_id   = (int) $post->ID;

	$href = $has_pdf ? esc_url(admin_url("admin-post.php?action=cmxbu_beleg_download&post_id={$post_id}")) : '#';
	$disable_attr = $has_pdf ? '' : 'pointer-events:none; opacity:0.5;';
	?>

		<a href="<?php echo $href; ?>"class="button button-secondary alignleft "style="<?php echo $disable_attr; ?>">verdownloaden</a>
	<?php

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
