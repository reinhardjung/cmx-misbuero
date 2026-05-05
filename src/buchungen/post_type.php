<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

\add_action('init', function (): void {
	if (\function_exists(__NAMESPACE__ . '\\cmx_const_taxos')) {
		cmx_const_taxos('BUCHUNGEN', CMX_BUCHUNGEN_CPT, CMX_TAX_BUCHUNGEN);
	}

	\register_post_type(CMX_BUCHUNGEN_CPT, [
		'labels' => [
			'name' => 'Buchungen',
			'singular_name' => 'Buchung',
			'add_new' => 'Hinzufügen',
			'add_new_item' => 'Hinzufügen',
			'edit_item' => 'Bearbeiten',
		],
		'menu_position' => 72,
		'supports' => ['title', 'editor'],
		'public' => true,
		'menu_icon' => 'dashicons-calendar-alt',
		'show_in_rest' => true,
		'has_archive' => true,
		'rewrite' => ['slug' => CMX_BUCHUNGEN_CPT],
	]);

	$taxonomies = [
		CMX_BUCHUNGEN_TAX_STANDORT => ['Standort', 'Standorte', true],
		CMX_BUCHUNGEN_TAX_TYP => ['Buchungstyp', 'Buchungstypen', true],
		CMX_BUCHUNGEN_TAX_MITARBEITER => ['Mitarbeiter', 'Mitarbeiter', false],
		CMX_BUCHUNGEN_TAX_RESSOURCE => ['Ressource', 'Ressourcen', false],
		CMX_BUCHUNGEN_TAX_LEISTUNGSKATEGORIE => ['Leistungskategorie', 'Leistungskategorien', true],
		CMX_BUCHUNGEN_TAX_DAUER => ['Dauer', 'Dauern', false],
	];

	foreach ($taxonomies as $taxonomy => [$single, $plural, $hierarchical]) {
		$hide_default_metabox = \in_array($taxonomy, [
			CMX_BUCHUNGEN_TAX_STANDORT,
			CMX_BUCHUNGEN_TAX_TYP,
			CMX_BUCHUNGEN_TAX_MITARBEITER,
			CMX_BUCHUNGEN_TAX_RESSOURCE,
			CMX_BUCHUNGEN_TAX_LEISTUNGSKATEGORIE,
			CMX_BUCHUNGEN_TAX_DAUER,
		], true);
		\register_taxonomy($taxonomy, [CMX_BUCHUNGEN_CPT], [
			'labels' => [
				'name' => $plural,
				'singular_name' => $single,
				'add_new_item' => $single . ' hinzufügen',
				'edit_item' => $single . ' bearbeiten',
				'search_items' => $plural . ' suchen',
			],
			'public' => false,
			'show_ui' => true,
			'show_admin_column' => false,
			'meta_box_cb' => $hide_default_metabox ? false : null,
			'show_in_rest' => true,
			'hierarchical' => (bool) $hierarchical,
			'rewrite' => false,
			'query_var' => false,
		]);
	}
}, 15);

\add_action('admin_init', function (): void {
	if (!\function_exists(__NAMESPACE__ . '\\cmx_seed_taxo')) {
		return;
	}
	cmx_seed_taxo('Buchungen', CMX_TAX_BUCHUNGEN);
	if (\taxonomy_exists(CMX_BUCHUNGEN_TAX_DAUER)) {
		foreach (['15', '30', '60'] as $dauer) {
			if (!\term_exists($dauer, CMX_BUCHUNGEN_TAX_DAUER)) {
				\wp_insert_term($dauer, CMX_BUCHUNGEN_TAX_DAUER, ['slug' => $dauer]);
			}
		}
	}
});

function cmx_buchungen_is_empty_auto_draft_title(string $title): bool {
	$title = \trim($title);
	return $title === '' || \in_array($title, ['Automatisch gespeicherter Entwurf', 'Auto Draft'], true);
}

\add_action('admin_init', function (): void {
	$ids = \get_posts([
		'post_type' => CMX_BUCHUNGEN_CPT,
		'post_status' => ['auto-draft', 'draft', 'publish'],
		'fields' => 'ids',
		'posts_per_page' => 50,
		'no_found_rows' => true,
		's' => 'Automatisch gespeicherter Entwurf',
	]);

	foreach ((array) $ids as $id) {
		$id = (int) $id;
		$post = \get_post($id);
		if (!$post instanceof \WP_Post || !cmx_buchungen_is_empty_auto_draft_title((string) $post->post_title)) {
			continue;
		}

		$has_booking_data = (int) \get_post_meta($id, CMX_BUCHUNGEN_META_KONTAKT, true) > 0
			|| (int) \get_post_meta($id, CMX_BUCHUNGEN_META_ARTIKEL, true) > 0
			|| \trim((string) \get_post_meta($id, CMX_BUCHUNGEN_META_START_DATE, true)) !== '';
		if (!$has_booking_data) {
			\wp_delete_post($id, true);
		}
	}
}, 30);

\add_filter('wp_insert_post_data', function (array $data, array $postarr): array {
	$post_type = \sanitize_key((string) ($data['post_type'] ?? ($postarr['post_type'] ?? '')));
	if ($post_type !== CMX_BUCHUNGEN_CPT) {
		return $data;
	}

	$post_id = (int) ($postarr['ID'] ?? 0);
	if ($post_id > 0 && (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id))) {
		return $data;
	}

	$status = \sanitize_key((string) ($data['post_status'] ?? ''));
	if (\in_array($status, ['auto-draft', 'trash', 'inherit'], true)) {
		return $data;
	}

	$data['post_status'] = 'publish';
	return $data;
}, 20, 2);

\add_action('admin_enqueue_scripts', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== CMX_BUCHUNGEN_CPT) {
		return;
	}
	\wp_dequeue_script('autosave');
});

\add_filter('wp_editor_settings', function (array $settings, string $editor_id): array {
	if ($editor_id !== 'content') {
		return $settings;
	}

	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== CMX_BUCHUNGEN_CPT) {
		return $settings;
	}

	$settings['textarea_rows'] = 5;
	$settings['editor_height'] = 120;
	if (($settings['tinymce'] ?? true) !== false) {
		$settings['tinymce'] = \is_array($settings['tinymce'] ?? null) ? $settings['tinymce'] : [];
		$settings['tinymce']['height'] = 120;
	}

	return $settings;
}, 10, 2);

\add_action('admin_head', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || (string) ($screen->post_type ?? '') !== CMX_BUCHUNGEN_CPT) {
		return;
	}
	echo '<style>
		body.post-type-' . \esc_html(CMX_BUCHUNGEN_CPT) . ' #postdivrich #wp-content-editor-container textarea.wp-editor-area,
		body.post-type-' . \esc_html(CMX_BUCHUNGEN_CPT) . ' #postdivrich #content,
		body.post-type-' . \esc_html(CMX_BUCHUNGEN_CPT) . ' #postdivrich .mce-edit-area iframe{
			height:120px !important;
			min-height:120px !important;
		}
		body.post-type-' . \esc_html(CMX_BUCHUNGEN_CPT) . ' #submitdiv .inside{
			margin:0;
			padding:0;
		}
		body.post-type-' . \esc_html(CMX_BUCHUNGEN_CPT) . ' #submitdiv #minor-publishing-actions,
		body.post-type-' . \esc_html(CMX_BUCHUNGEN_CPT) . ' #submitdiv .misc-pub-post-status,
		body.post-type-' . \esc_html(CMX_BUCHUNGEN_CPT) . ' #submitdiv .misc-pub-visibility,
		body.post-type-' . \esc_html(CMX_BUCHUNGEN_CPT) . ' #submitdiv .misc-pub-curtime{
			display:none !important;
		}
		body.post-type-' . \esc_html(CMX_BUCHUNGEN_CPT) . ' #submitdiv #minor-publishing{
			padding:12px 12px 14px;
		}
		body.post-type-' . \esc_html(CMX_BUCHUNGEN_CPT) . ' #submitdiv #minor-publishing-actions{
			display:flex;
			align-items:center;
			justify-content:space-between;
			gap:10px;
			padding:0 0 12px;
		}
		body.post-type-' . \esc_html(CMX_BUCHUNGEN_CPT) . ' #submitdiv #save-action,
		body.post-type-' . \esc_html(CMX_BUCHUNGEN_CPT) . ' #submitdiv #preview-action{
			float:none;
			margin:0;
		}
		body.post-type-' . \esc_html(CMX_BUCHUNGEN_CPT) . ' #submitdiv .misc-pub-section{
			padding:8px 0;
			border-top:0;
		}
		body.post-type-' . \esc_html(CMX_BUCHUNGEN_CPT) . ' #submitdiv .misc-pub-section .button{
			margin-top:2px;
		}
		body.post-type-' . \esc_html(CMX_BUCHUNGEN_CPT) . ' #submitdiv #major-publishing-actions{
			display:flex;
			align-items:center;
			justify-content:flex-end;
			gap:10px;
			padding:12px;
			background:#f8fafc;
			border-top:1px solid #e6ebf0;
		}
		body.post-type-' . \esc_html(CMX_BUCHUNGEN_CPT) . ' #submitdiv #publishing-action{
			float:none;
			margin:0;
			text-align:right;
		}
		body.post-type-' . \esc_html(CMX_BUCHUNGEN_CPT) . ' #submitdiv #delete-action{
			float:none;
			margin:0 auto 0 0;
		}
		body.post-type-' . \esc_html(CMX_BUCHUNGEN_CPT) . ' #submitdiv .spinner{
			margin:8px 0 0;
		}
	</style>';
	echo '<script>
		document.addEventListener("DOMContentLoaded", function(){
			var title = document.querySelector("body.post-type-' . \esc_js(CMX_BUCHUNGEN_CPT) . ' #submitdiv .postbox-header .hndle, body.post-type-' . \esc_js(CMX_BUCHUNGEN_CPT) . ' #submitdiv .postbox-header h2, body.post-type-' . \esc_js(CMX_BUCHUNGEN_CPT) . ' #submitdiv h2.hndle");
			if(title){ title.textContent = "Buchung"; }
		});
	</script>';
});
