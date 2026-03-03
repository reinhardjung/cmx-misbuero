<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


// Define: Custom-Post-Type based on DIR
register_post_type(basename(__DIR__), ['labels' => ['name' => cmx_sani_key(basename(__DIR__), 'title'), 'singular_name' => cmx_sani_key(basename(__DIR__), 'title'), 'add_new_item' => 'Hinzufügen', 'edit_item' => 'Bearbeiten',],
	'menu_position' => 60, 'supports' => ['title', 'editor'], 'public' => true, 'menu_icon' => 'dashicons-media-document', 'show_in_rest' => true, 'has_archive' => true, 'rewrite' => ['slug' => basename(__DIR__)],
]);


// Define: CONST 4 @ll Taxos
define(__NAMESPACE__ . '\\CMX_TAX_'.strtoupper(basename(__DIR__)),'Kategorien');


// Define: CONST 4 each Taxo
cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), CMX_TAX_DOKUMENTE);
// cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), const('\\CMX_TAX_'.strtoupper(basename(__DIR__))));
// var_dump(CMX_TAX_DOKUMENTE); exit;


// Create: @ll Taxos
\add_action('init', function () {
	cmx_create_taxo(basename(__DIR__), 'Kategorie', 'Kategorien');
}, 15);


// Refill: Taxo with defaults if removed
\add_action('admin_init', function () {
	cmx_seed_taxo(cmx_sani_key(basename(__DIR__),'title'),CMX_TAX_DOKUMENTE);
});


// Define: Const 4 @ll CPT Fields
// cmx_define_meta_constants(basename(__DIR__), ['umsatz']);


// Include: @ll metaboxes
cmx_require_files(__DIR__,'modules,status,validity,admincolumns,features_image');

/**
 * Scanner-Eingangsdateien werden im CPT "scanner" verwaltet und
 * sollen nicht in der Dokumente-Adminliste auftauchen.
 */
\add_action('pre_get_posts', function (\WP_Query $query): void {
	if (!\is_admin() || !$query->is_main_query()) {
		return;
	}

	$post_type = $query->get('post_type');
	$is_dokumente_query = false;
	if (\is_string($post_type)) {
		$is_dokumente_query = ($post_type === 'dokumente');
	} elseif (\is_array($post_type)) {
		$is_dokumente_query = \in_array('dokumente', $post_type, true);
	}
	if (!$is_dokumente_query) {
		return;
	}
	$post_status = $query->get('post_status');
	$is_trash_view = false;
	if (\is_string($post_status)) {
		$is_trash_view = (\strtolower($post_status) === 'trash');
	} elseif (\is_array($post_status)) {
		$is_trash_view = \in_array('trash', \array_map('strtolower', \array_map('strval', $post_status)), true);
	}
	if (!$is_trash_view && isset($_GET['post_status']) && \is_string($_GET['post_status'])) {
		$is_trash_view = (\strtolower((string) $_GET['post_status']) === 'trash');
	}
	if ($is_trash_view) {
		return;
	}

	$meta_query = (array) $query->get('meta_query');
	$meta_query[] = [
		'relation' => 'OR',
		[
			'key'     => '_cmx_dokumente_file_path',
			'compare' => 'NOT EXISTS',
		],
		[
			'relation' => 'AND',
			['key' => '_cmx_dokumente_file_path', 'value' => 'misbuero/scanner/',        'compare' => 'NOT LIKE'],
			['key' => '_cmx_dokumente_file_path', 'value' => 'misbuero/Scanner/',        'compare' => 'NOT LIKE'],
			['key' => '_cmx_dokumente_file_path', 'value' => 'scanner/',                 'compare' => 'NOT LIKE'],
			['key' => '_cmx_dokumente_file_path', 'value' => 'Scanner/',                 'compare' => 'NOT LIKE'],
			['key' => '_cmx_dokumente_file_path', 'value' => 'misbuero/archiv/scanner/', 'compare' => 'NOT LIKE'],
			['key' => '_cmx_dokumente_file_path', 'value' => 'misbuero/archiv/Scanner/', 'compare' => 'NOT LIKE'],
		],
	];
	$query->set('meta_query', $meta_query);
}, 20);

function cmx_dokumente_sanitized_title_from_file(int $post_id): string {
	if ($post_id <= 0) {
		return '';
	}

	$file_rel = (string) \get_post_meta($post_id, '_cmx_dokumente_file_path', true);
	if ($file_rel === '') {
		$self_meta_key = \defined(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
			: '_cmx_dokumente_files';
		$self_files = (array) \get_post_meta($post_id, $self_meta_key, true);
		$self_files = \array_values(\array_filter($self_files, static function ($value): bool {
			return \is_string($value) && $value !== '';
		}));
		if (!empty($self_files)) {
			$file_rel = (string) $self_files[\count($self_files) - 1];
		}
	}

	if ($file_rel === '') {
		$attachment_id = (int) \get_post_meta($post_id, '_cmx_dokumente_attachment_id', true);
		if ($attachment_id > 0) {
			$attached_path = (string) \get_attached_file($attachment_id);
			if ($attached_path !== '') {
				$file_rel = (string) \basename($attached_path);
			}
		}
	}

	$file_rel = \ltrim(\str_replace('\\', '/', $file_rel), '/');
	if ($file_rel === '') {
		return '';
	}

	$filename = (string) \basename($file_rel);
	if ($filename === '') {
		return '';
	}

	if (\function_exists(__NAMESPACE__ . '\\cmx_dok_sanitize_title_from_filename')) {
		$title = (string) cmx_dok_sanitize_title_from_filename($filename);
		if ($title !== '') {
			return $title;
		}
	}

	$fallback = (string) \pathinfo($filename, \PATHINFO_FILENAME);
	$fallback = \wp_strip_all_tags($fallback);
	$fallback = (string) \preg_replace('/[_\-]+/', ' ', $fallback);
	$fallback = (string) \preg_replace('/\s+/', ' ', $fallback);
	return \trim(\sanitize_text_field($fallback));
}

// Dokumente-Titel immer aus dem Dateinamen ableiten (sanitized).
\add_action('save_post_dokumente', function (int $post_id, \WP_Post $post): void {
	if ($post->post_type !== 'dokumente') return;
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\wp_is_post_revision($post_id)) return;
	if (!\current_user_can('edit_post', $post_id)) return;

	$new_title = cmx_dokumente_sanitized_title_from_file($post_id);
	if ($new_title === '') {
		$new_title = \trim((string) $post->post_title);
		if ($new_title === '') {
			$new_title = \wp_date('ymd-His');
		}
	}

	$current_title = \trim((string) $post->post_title);
	$new_slug = \sanitize_title($new_title);
	$current_slug = (string) $post->post_name;
	if ($current_title === $new_title && $current_slug === $new_slug) {
		return;
	}

	\wp_update_post([
		'ID'         => $post_id,
		'post_title' => $new_title,
		'post_name'  => $new_slug,
	]);
}, 10, 2);


// /**
//  * Wenn ein Dokument ein Beitragsbild erhält und noch keinen Titel hat,
//  * wird der (sanitisierte, kleingeschriebene) Dateiname des Bildes als Titel gesetzt.
//  */
// function cmx_dokumente_autofill_title(int $post_id, \WP_Post $post): void {
// 	if ($post->post_type !== 'dokumente') return;
// 	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
// 	if (wp_is_post_revision($post_id)) return;
// 	if (!current_user_can('edit_post', $post_id)) return;

// 	if (trim((string) $post->post_title) !== '') return; // Titel existiert bereits

// 	$thumb_id = (int) get_post_thumbnail_id($post_id);
// 	if (!$thumb_id) return;

// 	$filename = '';
// 	$path = get_attached_file($thumb_id);
// 	if ($path) {
// 		$filename = pathinfo($path, PATHINFO_FILENAME);
// 	}
// 	if ($filename === '') {
// 		$att = get_post($thumb_id);
// 		$filename = $att?->post_title ?? '';
// 	}

// 	$filename = strtolower($filename);
// 	$filename = str_replace(['_', '-'], ' ', $filename);
// 	$filename = sanitize_text_field($filename);
// 	if ($filename === '') return;

// 	remove_action('save_post_dokumente', __NAMESPACE__ . '\\cmx_dokumente_autofill_title', 10);
// 	wp_update_post([
// 		'ID'         => $post_id,
// 		'post_title' => $filename,
// 	]);
// 	add_action('save_post_dokumente', __NAMESPACE__ . '\\cmx_dokumente_autofill_title', 10, 2);
// }
// \add_action('save_post_dokumente', __NAMESPACE__ . '\\cmx_dokumente_autofill_title', 10, 2);
