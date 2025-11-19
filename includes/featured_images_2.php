<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


define('CMX_FEATURED_IMG_DIR', WP_CONTENT_DIR . '/uploads/misbuero/bilder');
define('CMX_FEATURED_IMG_URL', content_url('uploads/misbuero/bilder'));
// <img src="<?php echo CMX_FEATURED_IMG_URL; >/meinbild.jpg" alt="">


/**
 * Leitet nur das Featured Image (Post Thumbnail) in einen separaten Ordner um.
 */
add_filter('upload_dir', function($dirs) {
	// Prüfen, ob es sich um den Upload eines Beitragsbilds handelt
	if (isset($_REQUEST['post_id']) && has_filter('wp_handle_upload_prefilter', 'wp_handle_upload_prefilter')) {
		$post_id = (int) $_REQUEST['post_id'];

		// Wenn der Upload aus der Mediathek kommt und für das Beitragsbild bestimmt ist:
		if (isset($_REQUEST['context']) && $_REQUEST['context'] === 'featured-image') {
			$subdir = '/misbuero/bilder';

			$dirs['subdir'] = $subdir;
			$dirs['path']   = $dirs['basedir'] . $subdir;
			$dirs['url']    = $dirs['baseurl'] . $subdir;

			// Ordner anlegen, falls nicht vorhanden
			if (!file_exists($dirs['path'])) {
				wp_mkdir_p($dirs['path']);
			}
		}
	}

	return $dirs;
});
