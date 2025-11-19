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

require_once 'meta_action_download.php';
require_once 'meta_action_send.php';
