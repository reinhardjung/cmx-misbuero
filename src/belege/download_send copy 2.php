<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


add_action('add_meta_boxes', __NAMESPACE__ . '\\cmxbu_add_beleg_metabox');
function cmxbu_add_beleg_metabox(): void {
	add_meta_box('cmx_beleg_download',__('Beleg...', 'default'),__NAMESPACE__ . '\\cmxbu_render_beleg_metabox','belege','side','high');
}


function cmxbu_render_beleg_metabox(\WP_Post $post) {
	?>
	<style>
		.cmx-beleg-actions { overflow:hidden; padding-top:8px; } /* verhindert das Hochrutschen der Buttons */
		.cmx-beleg-actions form { margin: 0; }
		.cmx-beleg-actions .alignleft { float: left; }
		.cmx-beleg-actions .alignright { float: right; }
	</style>

	<div class="cmx-beleg-actions">
		<?php
		cmxbu_render_beleg_download_metabox($post);
		cmxbu_render_beleg_send_metabox($post);
		?>
	</div>
	<?php
}

function cmxbu_render_beleg_download_metabox(\WP_Post $post): void {
	$post_id = (int) $post->ID;
	$nonce   = wp_create_nonce('cmx_beleg_download_' . $post_id);

	$info    = cmxbu_get_beleg_pdf_for_download($post_id);
	$has_pdf = $info['found'];
	?>

	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<input type="hidden" name="action" value="cmxbu_beleg_download">
		<input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">
		<input type="hidden" name="cmx_download_nonce" value="<?php echo esc_attr($nonce); ?>">

		<button type="submit" class="button button-secondary alignleft" <?php disabled(!$has_pdf); ?>>
			<?php echo esc_html__('Download', 'default'); ?>
		</button>
	</form>

	<?php
}

function cmxbu_render_beleg_send_metabox(\WP_Post $post): void {
	$post_id = (int) $post->ID;
	$nonce   = wp_create_nonce('cmx_beleg_send_' . $post_id);

	$info    = cmxbu_get_beleg_pdf_for_send($post_id);
	$has_pdf = $info['found'];
	?>

	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<input type="hidden" name="action" value="cmxbu_beleg_send">
		<input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">
		<input type="hidden" name="cmx_nonce_nonce" value="<?php echo esc_attr($nonce); ?>">

		<button type="submit" class="button button-primary alignright" <?php disabled(!$has_pdf); ?>>
			<?php echo esc_html__('Senden', 'default'); ?>
		</button>
	</form>

	<?php
}


function cmxbu_get_beleg_pdf_for_send(int $post_id): array {
	var_dump(get_post_meta($post_id)['_cmx_beleg_pdf_path']); exit;
	// $thePath = get_post_meta($post_id)['_cmx_beleg_pdf_path'][0];
	// $thePath = get_post_meta($post_id)['_cmx_rechnungsnummer'][0];
		// var_dump(get_post($post_id)->post_title); exit;
		if(is_file($thePath)) {

		return ['found' => true,'type' => 'local','path' => '$path','filename' => basename('$path'),];
	} else {

		return ['found' => false,'type' => 'local','path' => '$path','filename' => basename('$path'),];
	}
}

function cmxbu_get_beleg_pdf_for_download(int $post_id): array {
	// var_dump(get_post_meta($post_id)['_cmx_beleg_pdf_path']); exit;
	// $thePath = get_post_meta($post_id)['_cmx_beleg_pdf_path'][0];
	$thePath = get_post_meta($post_id)['_cmx_rechnungsnummer'][0];
		if(is_file($thePath)) {

		return ['found' => true,'type' => 'local','path' => '$path','filename' => basename('$path'),];
	} else {

		return ['found' => false,'type' => 'local','path' => '$path','filename' => basename('$path'),];
	}
}


// object(WP_Post)#1808 (24) { ["ID"]=> int(1333) ["post_author"]=> string(1) "1" ["post_date"]=> string(19) "2025-11-04 07:17:33" ["post_date_gmt"]=> string(19) "2025-11-04 06:17:33" ["post_content"]=> string(0) "" ["post_title"]=> string(13) "251104-071548" ["post_excerpt"]=> string(0) "" ["post_status"]=> string(7) "publish" ["comment_status"]=> string(6) "closed" ["ping_status"]=> string(6) "closed" ["post_password"]=> string(0) "" ["post_name"]=> string(13) "251104-071548" ["to_ping"]=> string(0) "" ["pinged"]=> string(0) "" ["post_modified"]=> string(19) "2025-11-18 14:34:44" ["post_modified_gmt"]=> string(19) "2025-11-18 13:34:44" ["post_content_filtered"]=> string(0) "" ["post_parent"]=> int(0) ["guid"]=> string(51) "http://misbuero.local/?post_type=belege&p=1333" ["menu_order"]=> int(0) ["post_type"]=> string(6) "belege" ["post_mime_type"]=> string(0) "" ["comment_count"]=> string(1) "0" ["filter"]=> string(3) "raw" }
