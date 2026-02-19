<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/* ===== ZIP Link ===== */
\add_action('admin_post_cmx_export_belege_list', function(){
	if (!\current_user_can('edit_posts')) \wp_die('Keine Berechtigung.');
	if (!cmxbu_belege_export_verify_nonce('cmx_export_belege_list')) \wp_die('Ungültige Anfrage.');
	cmxbu_belege_export_require_date_range_or_redirect();

	$post_ids = cmxbu_belege_export_collect_ids();
	cmxbu_stream_belege_csv_from_ids($post_ids);
});
