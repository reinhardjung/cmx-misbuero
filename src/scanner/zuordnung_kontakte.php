<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_SCANNER_REL_KONTAKTE_META = '_cmx_scanner_rel_kontakte_id';
const CMX_SCANNER_FINALIZE_DELETE_USER_META = '_cmx_scanner_finalize_delete_ids';

function cmx_scanner_queue_finalize_delete_after_save(int $post_id): void {
	if ($post_id <= 0) {
		return;
	}

	$user_id = \get_current_user_id();
	if ($user_id > 0) {
		$queued = cmx_scanner_normalize_id_list(\get_user_meta($user_id, CMX_SCANNER_FINALIZE_DELETE_USER_META, true));
		$queued[] = $post_id;
		$queued = \array_values(\array_unique($queued));
		\update_user_meta($user_id, CMX_SCANNER_FINALIZE_DELETE_USER_META, $queued);
	}
}

function cmx_scanner_mark_redirect_to_list_after_save(int $post_id): void {
	if ($post_id <= 0) {
		return;
	}

	cmx_scanner_queue_finalize_delete_after_save($post_id);

	if (!isset($GLOBALS['cmx_scanner_redirect_to_list_ids']) || !\is_array($GLOBALS['cmx_scanner_redirect_to_list_ids'])) {
		$GLOBALS['cmx_scanner_redirect_to_list_ids'] = [];
	}
	$GLOBALS['cmx_scanner_redirect_to_list_ids'][] = $post_id;
	$GLOBALS['cmx_scanner_redirect_to_list_ids'] = \array_values(\array_unique(\array_map('intval', (array) $GLOBALS['cmx_scanner_redirect_to_list_ids'])));

	static $filter_added = false;
	if (!$filter_added) {
		$filter_added = true;
		\add_filter('redirect_post_location', __NAMESPACE__ . '\\cmx_scanner_redirect_to_list_after_save', 100, 2);
	}
}

function cmx_scanner_mark_redirect_to_target_edit_after_save(int $scanner_id, int $target_id): void {
	if ($scanner_id <= 0 || $target_id <= 0) {
		return;
	}

	cmx_scanner_queue_finalize_delete_after_save($scanner_id);

	if (!isset($GLOBALS['cmx_scanner_redirect_to_target_map']) || !\is_array($GLOBALS['cmx_scanner_redirect_to_target_map'])) {
		$GLOBALS['cmx_scanner_redirect_to_target_map'] = [];
	}
	$GLOBALS['cmx_scanner_redirect_to_target_map'][(int) $scanner_id] = (int) $target_id;

	static $filter_added = false;
	if (!$filter_added) {
		$filter_added = true;
		\add_filter('redirect_post_location', __NAMESPACE__ . '\\cmx_scanner_redirect_to_target_edit_after_save', 99, 2);
	}
}

function cmx_scanner_redirect_to_target_edit_after_save(string $location, int $post_id): string {
	$map = isset($GLOBALS['cmx_scanner_redirect_to_target_map']) && \is_array($GLOBALS['cmx_scanner_redirect_to_target_map'])
		? $GLOBALS['cmx_scanner_redirect_to_target_map']
		: [];

	$target_id = isset($map[$post_id]) ? (int) $map[$post_id] : 0;
	if ($target_id > 0) {
		return (string) \admin_url('post.php?post=' . $target_id . '&action=edit');
	}

	return $location;
}

function cmx_scanner_redirect_to_list_after_save(string $location, int $post_id): string {
	$ids = isset($GLOBALS['cmx_scanner_redirect_to_list_ids']) && \is_array($GLOBALS['cmx_scanner_redirect_to_list_ids'])
		? \array_values(\array_unique(\array_map('intval', (array) $GLOBALS['cmx_scanner_redirect_to_list_ids'])))
		: [];

	if ($post_id > 0 && \in_array($post_id, $ids, true)) {
		return (string) \admin_url('edit.php?post_type=scanner');
	}

	return $location;
}

function cmx_scanner_finalize_delete_after_redirect(): void {
	if (!\is_admin()) {
		return;
	}

	$request_method = \strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
	// Niemals im gleichen POST-Save-Request löschen, sonst fehlen Core später
	// die Post-Daten und es entstehen Warnungen/Redirect-Probleme.
	if ($request_method !== 'GET') {
		return;
	}

	$pagenow = (string) ($GLOBALS['pagenow'] ?? '');
	if ($pagenow === 'post.php') {
		$editing_post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
		if ($editing_post_id > 0 && (string) \get_post_type($editing_post_id) === 'scanner') {
			return;
		}
	}

	if ($pagenow !== 'edit.php' && $pagenow !== 'post.php') {
		return;
	}

	$user_id = \get_current_user_id();
	if ($user_id <= 0) {
		return;
	}

	$queued_ids = cmx_scanner_normalize_id_list(\get_user_meta($user_id, CMX_SCANNER_FINALIZE_DELETE_USER_META, true));
	if (empty($queued_ids)) {
		return;
	}

	\delete_user_meta($user_id, CMX_SCANNER_FINALIZE_DELETE_USER_META);

	foreach ($queued_ids as $delete_id) {
		if ($delete_id <= 0) {
			continue;
		}
		if ((string) \get_post_type($delete_id) !== 'scanner') {
			continue;
		}
		if (!\current_user_can('delete_post', $delete_id)) {
			continue;
		}

		$doc_ids = cmx_scanner_get_doc_ids_for_post($delete_id);
		$kontakt_id = (int) \get_post_meta($delete_id, CMX_SCANNER_REL_KONTAKTE_META, true);
		if ($kontakt_id > 0) {
			cmx_scanner_link_docs_to_kontakt($kontakt_id, $doc_ids);
		}
		$artikel_id = (int) \get_post_meta($delete_id, '_cmx_scanner_rel_artikel_id', true);
		if ($artikel_id > 0) {
			cmx_scanner_link_docs_to_artikel($artikel_id, $doc_ids);
		}
		$projekt_id = (int) \get_post_meta($delete_id, '_cmx_scanner_rel_projekte_id', true);
		if ($projekt_id > 0) {
			cmx_scanner_link_docs_to_projekte($projekt_id, $doc_ids);
		}
		$beleg_id = (int) \get_post_meta($delete_id, '_cmx_scanner_rel_belege_id', true);
		if ($beleg_id > 0) {
			cmx_scanner_link_docs_to_belege($beleg_id, $doc_ids);
		}
		cmx_scanner_move_doc_files_to_archive($delete_id, $doc_ids);

		// Datei(en) robust vorab entfernen, auch wenn before_delete_post in diesem
		// Request nicht greifen sollte.
		if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_sync_collect_delete_rels') && \function_exists(__NAMESPACE__ . '\\cmx_scanner_sync_delete_file_from_rel')) {
			$rels = cmx_scanner_sync_collect_delete_rels($delete_id);
			foreach ($rels as $rel) {
				cmx_scanner_sync_delete_file_from_rel((string) $rel);
			}
		}

		\wp_delete_post($delete_id, true);
	}
}
\add_action('load-edit.php', __NAMESPACE__ . '\\cmx_scanner_finalize_delete_after_redirect', 6);
\add_action('load-post.php', __NAMESPACE__ . '\\cmx_scanner_finalize_delete_after_redirect', 6);

function cmx_scanner_dok_uploads_meta_key(): string {
	return \defined(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
		? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
		: '_cmx_dokumente_uploads';
}

function cmx_scanner_dok_file_meta_key(): string {
	return '_cmx_dokumente_file_path';
}

function cmx_scanner_dok_self_meta_key(): string {
	return \defined(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
		? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
		: '_cmx_dokumente_files';
}

function cmx_scanner_normalize_rel_path(string $rel): string {
	return \strtolower(\ltrim(\str_replace('\\', '/', $rel), '/'));
}

function cmx_scanner_is_scanner_rel_path(string $rel): bool {
	return \str_starts_with(cmx_scanner_normalize_rel_path($rel), 'misbuero/scanner/');
}

function cmx_scanner_archive_year_for_post(int $scanner_id): int {
	$ts = (int) \get_post_meta($scanner_id, '_cmx_scanner_uploaded_ts', true);
	if ($ts <= 0) {
		$ts = (int) \get_post_time('U', true, $scanner_id);
	}
	if ($ts <= 0) {
		$ts = (int) \current_time('timestamp');
	}
	$year = (int) \wp_date('Y', $ts);
	return $year > 0 ? $year : (int) \wp_date('Y');
}

function cmx_scanner_archive_target_dir(int $year): string {
	$year = $year > 0 ? $year : (int) \wp_date('Y');
	if (\function_exists(__NAMESPACE__ . '\\cmx_dok_upload_target_dir')) {
		[$base] = cmx_dok_upload_target_dir('dokumente', $year);
		if (\is_string($base) && $base !== '') {
			return (string) $base;
		}
	}
	$base = (string) (\WP_CONTENT_DIR . '/uploads/misbuero/' . $year . '/dokumente');
	if (!\is_dir($base)) {
		\wp_mkdir_p($base);
	}
	return $base;
}

function cmx_scanner_move_doc_files_to_archive(int $scanner_id, array $doc_ids): void {
	$doc_ids = cmx_scanner_normalize_id_list($doc_ids);
	if (empty($doc_ids)) {
		return;
	}

	$target_dir = cmx_scanner_archive_target_dir(cmx_scanner_archive_year_for_post($scanner_id));
	if ($target_dir === '' || !\is_dir($target_dir)) {
		return;
	}

	$uploads_root = \trailingslashit(\wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads')));
	$file_meta_key = cmx_scanner_dok_file_meta_key();
	$self_meta_key = cmx_scanner_dok_self_meta_key();

	foreach ($doc_ids as $doc_id) {
		if ($doc_id <= 0 || (string) \get_post_type($doc_id) !== 'dokumente') {
			continue;
		}

		$old_rel = \ltrim(\str_replace('\\', '/', (string) \get_post_meta($doc_id, $file_meta_key, true)), '/');
		if ($old_rel === '' || !cmx_scanner_is_scanner_rel_path($old_rel)) {
			continue;
		}

		$source_abs = \wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads/' . $old_rel));
		if ($source_abs === '' || !\str_starts_with($source_abs, $uploads_root) || !\is_file($source_abs)) {
			continue;
		}

		$base_name = \sanitize_file_name((string) \basename($source_abs));
		if ($base_name === '') {
			$base_name = \wp_date('ymd-His') . '-dokument';
		}
		$target_name = \wp_unique_filename($target_dir, $base_name);
		$target_abs = \wp_normalize_path((string) ($target_dir . '/' . $target_name));

		$moved = @\rename($source_abs, $target_abs);
		if (!$moved) {
			$copied = @\copy($source_abs, $target_abs);
			if ($copied) {
				@\unlink($source_abs);
				$moved = true;
			}
		}
		if (!$moved) {
			continue;
		}
		if (!\str_starts_with($target_abs, $uploads_root)) {
			continue;
		}

		$new_rel = \ltrim((string) \substr($target_abs, \strlen($uploads_root)), '/');
		if ($new_rel === '') {
			continue;
		}

		\update_post_meta($doc_id, $file_meta_key, $new_rel);

		$self_files = (array) \get_post_meta($doc_id, $self_meta_key, true);
		$updated_self = [];
		$old_key = cmx_scanner_normalize_rel_path($old_rel);
		$replaced = false;
		foreach ($self_files as $self_rel) {
			if (!\is_string($self_rel) || $self_rel === '') {
				continue;
			}
			$current = \ltrim(\str_replace('\\', '/', $self_rel), '/');
			if (cmx_scanner_normalize_rel_path($current) === $old_key) {
				$updated_self[] = $new_rel;
				$replaced = true;
				continue;
			}
			$updated_self[] = $current;
		}
		if (!$replaced) {
			$updated_self[] = $new_rel;
		}
		$updated_self = \array_values(\array_unique(\array_filter($updated_self, static function ($value): bool {
			return \is_string($value) && $value !== '';
		})));
		\update_post_meta($doc_id, $self_meta_key, $updated_self);
	}
}

function cmx_scanner_dok_kontakt_rel_meta_key(): string {
	if (\defined(__NAMESPACE__ . '\\CMX_DOK_REL_META')) {
		$map = \constant(__NAMESPACE__ . '\\CMX_DOK_REL_META');
		if (\is_array($map) && isset($map['kontakte']) && \is_string($map['kontakte']) && $map['kontakte'] !== '') {
			return $map['kontakte'];
		}
	}
	return 'cmx_dokumente_kunden';
}

function cmx_scanner_dok_artikel_rel_meta_key(): string {
	if (\defined(__NAMESPACE__ . '\\CMX_DOK_REL_META')) {
		$map = \constant(__NAMESPACE__ . '\\CMX_DOK_REL_META');
		if (\is_array($map) && isset($map['artikel']) && \is_string($map['artikel']) && $map['artikel'] !== '') {
			return $map['artikel'];
		}
	}
	return 'cmx_dokumente_artikel';
}

function cmx_scanner_dok_projekte_rel_meta_key(): string {
	if (\defined(__NAMESPACE__ . '\\CMX_DOK_REL_META')) {
		$map = \constant(__NAMESPACE__ . '\\CMX_DOK_REL_META');
		if (\is_array($map) && isset($map['projekte']) && \is_string($map['projekte']) && $map['projekte'] !== '') {
			return $map['projekte'];
		}
	}
	return 'cmx_dokumente_projekte';
}

function cmx_scanner_dok_belege_rel_meta_key(): string {
	if (\defined(__NAMESPACE__ . '\\CMX_DOK_REL_META')) {
		$map = \constant(__NAMESPACE__ . '\\CMX_DOK_REL_META');
		if (\is_array($map) && isset($map['belege']) && \is_string($map['belege']) && $map['belege'] !== '') {
			return $map['belege'];
		}
	}
	return 'cmx_dokumente_belege';
}

function cmx_scanner_normalize_id_list($value): array {
	$ids = [];
	foreach ((array) $value as $item) {
		$id = (int) $item;
		if ($id > 0) {
			$ids[] = $id;
		}
	}
	return \array_values(\array_unique($ids));
}

function cmx_scanner_ensure_doc_for_post(int $scanner_id): void {
	if (!\function_exists(__NAMESPACE__ . '\\cmx_scanner_sync_get_source_rel_for_post') || !\function_exists(__NAMESPACE__ . '\\cmx_scanner_sync_ensure_doc_link')) {
		return;
	}

	$rel = (string) cmx_scanner_sync_get_source_rel_for_post($scanner_id);
	if ($rel === '') {
		return;
	}

	$title = (string) \get_the_title($scanner_id);
	if ($title === '' && \function_exists(__NAMESPACE__ . '\\cmx_scanner_sync_make_title')) {
		$title = (string) cmx_scanner_sync_make_title($rel);
	}
	if ($title === '') {
		$title = \wp_date('ymd-His') . ' scanner';
	}

	cmx_scanner_sync_ensure_doc_link($scanner_id, $rel, $title);
}

function cmx_scanner_get_doc_ids_for_post(int $scanner_id): array {
	$uploads_meta_key = cmx_scanner_dok_uploads_meta_key();
	return cmx_scanner_normalize_id_list(\get_post_meta($scanner_id, $uploads_meta_key, true));
}

function cmx_scanner_resolve_target_post_type(string $target_type): string {
	$target_type = \sanitize_key($target_type);
	if ($target_type === 'kontakte') {
		if (\post_type_exists('kontakte')) {
			return 'kontakte';
		}
		if (\post_type_exists('kontakt')) {
			return 'kontakt';
		}
		return '';
	}

	return \post_type_exists($target_type) ? $target_type : '';
}

function cmx_scanner_current_user_can_create_post_type(string $post_type): bool {
	$obj = \get_post_type_object($post_type);
	if (!$obj || !isset($obj->cap) || !\is_object($obj->cap)) {
		return false;
	}

	$create_cap = isset($obj->cap->create_posts) && \is_string($obj->cap->create_posts) && $obj->cap->create_posts !== ''
		? $obj->cap->create_posts
		: (isset($obj->cap->edit_posts) && \is_string($obj->cap->edit_posts) ? $obj->cap->edit_posts : 'edit_posts');

	return \current_user_can($create_cap);
}

function cmx_scanner_default_target_title(int $scanner_id, string $target_type): string {
	$title = \trim((string) \get_the_title($scanner_id));
	if ($title !== '') {
		return $title;
	}

	$labels = [
		'kontakte' => 'Neuer Kontakt',
		'artikel'  => 'Neuer Artikel',
		'projekte' => 'Neues Projekt',
	];

	return (string) ($labels[$target_type] ?? 'Neuer Eintrag');
}

function cmx_scanner_create_related_entry(int $scanner_id, string $target_type): int {
	$post_type = cmx_scanner_resolve_target_post_type($target_type);
	if ($scanner_id <= 0 || $post_type === '') {
		return 0;
	}

	if (!cmx_scanner_current_user_can_create_post_type($post_type)) {
		return 0;
	}

	$title = cmx_scanner_default_target_title($scanner_id, $target_type);
	$inserted = \wp_insert_post([
		'post_type'   => $post_type,
		'post_title'  => $title,
		'post_status' => 'draft',
	], true);

	if (\is_wp_error($inserted) || (int) $inserted <= 0) {
		return 0;
	}

	return (int) $inserted;
}

function cmx_scanner_link_docs_to_kontakt(int $kontakt_id, array $doc_ids): void {
	$doc_ids = cmx_scanner_normalize_id_list($doc_ids);
	if ($kontakt_id <= 0 || empty($doc_ids)) {
		return;
	}

	$uploads_meta_key = cmx_scanner_dok_uploads_meta_key();
	$existing_uploads = cmx_scanner_normalize_id_list(\get_post_meta($kontakt_id, $uploads_meta_key, true));
	$merged_uploads = \array_values(\array_unique(\array_merge($existing_uploads, $doc_ids)));
	if ($merged_uploads !== $existing_uploads) {
		\update_post_meta($kontakt_id, $uploads_meta_key, $merged_uploads);
	}

	$kontakt_rel_meta_key = cmx_scanner_dok_kontakt_rel_meta_key();
	foreach ($doc_ids as $doc_id) {
		if ((string) \get_post_type($doc_id) !== 'dokumente') {
			continue;
		}
		$doc_kontakte = cmx_scanner_normalize_id_list(\get_post_meta($doc_id, $kontakt_rel_meta_key, true));
		if (\in_array($kontakt_id, $doc_kontakte, true)) {
			continue;
		}
		$doc_kontakte[] = $kontakt_id;
		$doc_kontakte = \array_values(\array_unique($doc_kontakte));
		\update_post_meta($doc_id, $kontakt_rel_meta_key, $doc_kontakte);
	}
}

function cmx_scanner_link_docs_to_artikel(int $artikel_id, array $doc_ids): void {
	$doc_ids = cmx_scanner_normalize_id_list($doc_ids);
	if ($artikel_id <= 0 || empty($doc_ids)) {
		return;
	}

	if ((string) \get_post_type($artikel_id) !== 'artikel') {
		return;
	}

	$uploads_meta_key = cmx_scanner_dok_uploads_meta_key();
	$existing_uploads = cmx_scanner_normalize_id_list(\get_post_meta($artikel_id, $uploads_meta_key, true));
	$merged_uploads = \array_values(\array_unique(\array_merge($existing_uploads, $doc_ids)));
	if ($merged_uploads !== $existing_uploads) {
		\update_post_meta($artikel_id, $uploads_meta_key, $merged_uploads);
	}

	$artikel_rel_meta_key = cmx_scanner_dok_artikel_rel_meta_key();
	foreach ($doc_ids as $doc_id) {
		if ((string) \get_post_type($doc_id) !== 'dokumente') {
			continue;
		}
		$doc_artikel = cmx_scanner_normalize_id_list(\get_post_meta($doc_id, $artikel_rel_meta_key, true));
		if (\in_array($artikel_id, $doc_artikel, true)) {
			continue;
		}
		$doc_artikel[] = $artikel_id;
		$doc_artikel = \array_values(\array_unique($doc_artikel));
		\update_post_meta($doc_id, $artikel_rel_meta_key, $doc_artikel);
	}
}

function cmx_scanner_link_docs_to_projekte(int $projekt_id, array $doc_ids): void {
	$doc_ids = cmx_scanner_normalize_id_list($doc_ids);
	if ($projekt_id <= 0 || empty($doc_ids)) {
		return;
	}

	if ((string) \get_post_type($projekt_id) !== 'projekte') {
		return;
	}

	$uploads_meta_key = cmx_scanner_dok_uploads_meta_key();
	$existing_uploads = cmx_scanner_normalize_id_list(\get_post_meta($projekt_id, $uploads_meta_key, true));
	$merged_uploads = \array_values(\array_unique(\array_merge($existing_uploads, $doc_ids)));
	if ($merged_uploads !== $existing_uploads) {
		\update_post_meta($projekt_id, $uploads_meta_key, $merged_uploads);
	}

	$projekte_rel_meta_key = cmx_scanner_dok_projekte_rel_meta_key();
	foreach ($doc_ids as $doc_id) {
		if ((string) \get_post_type($doc_id) !== 'dokumente') {
			continue;
		}
		$doc_projekte = cmx_scanner_normalize_id_list(\get_post_meta($doc_id, $projekte_rel_meta_key, true));
		if (\in_array($projekt_id, $doc_projekte, true)) {
			continue;
		}
		$doc_projekte[] = $projekt_id;
		$doc_projekte = \array_values(\array_unique($doc_projekte));
		\update_post_meta($doc_id, $projekte_rel_meta_key, $doc_projekte);
	}
}

function cmx_scanner_link_docs_to_belege(int $beleg_id, array $doc_ids): void {
	$doc_ids = cmx_scanner_normalize_id_list($doc_ids);
	if ($beleg_id <= 0 || empty($doc_ids)) {
		return;
	}
	if ((string) \get_post_type($beleg_id) !== 'belege') {
		return;
	}

	$uploads_meta_key = cmx_scanner_dok_uploads_meta_key();
	$existing_uploads = cmx_scanner_normalize_id_list(\get_post_meta($beleg_id, $uploads_meta_key, true));
	$merged_uploads = \array_values(\array_unique(\array_merge($existing_uploads, $doc_ids)));
	if ($merged_uploads !== $existing_uploads) {
		\update_post_meta($beleg_id, $uploads_meta_key, $merged_uploads);
	}

	$belege_rel_meta_key = cmx_scanner_dok_belege_rel_meta_key();
	foreach ($doc_ids as $doc_id) {
		if ((string) \get_post_type($doc_id) !== 'dokumente') {
			continue;
		}
		$doc_belege = cmx_scanner_normalize_id_list(\get_post_meta($doc_id, $belege_rel_meta_key, true));
		if (\in_array($beleg_id, $doc_belege, true)) {
			continue;
		}
		$doc_belege[] = $beleg_id;
		$doc_belege = \array_values(\array_unique($doc_belege));
		\update_post_meta($doc_id, $belege_rel_meta_key, $doc_belege);
	}
}

\add_action('add_meta_boxes_scanner', function (\WP_Post $post): void {
	\add_meta_box(
		'cmx_scanner_rel_kontakte',
		'Kontakt',
		__NAMESPACE__ . '\\cmx_scanner_render_rel_kontakte_metabox',
		'scanner',
		'side',
		'default'
	);
});

function cmx_scanner_render_rel_kontakte_metabox(\WP_Post $post): void {
	cmx_scanner_render_relation_select_box(
		$post,
		'kontakte',
		CMX_SCANNER_REL_KONTAKTE_META,
		'cmx_scanner_rel_kontakte_save',
		'cmx_scanner_rel_kontakte_nonce',
		'Kein Kontakt'
	);
}

\add_action('save_post_scanner', function (int $post_id): void {
	if (!isset($_POST['cmx_scanner_rel_kontakte_nonce']) || !\wp_verify_nonce((string) $_POST['cmx_scanner_rel_kontakte_nonce'], 'cmx_scanner_rel_kontakte_save')) {
		return;
	}
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

	$selected_type = cmx_scanner_get_requested_zuordnung_type($post_id);
	$value = isset($_POST[CMX_SCANNER_REL_KONTAKTE_META]) ? (int) $_POST[CMX_SCANNER_REL_KONTAKTE_META] : 0;
	if ($selected_type !== 'kontakte') {
		\delete_post_meta($post_id, CMX_SCANNER_REL_KONTAKTE_META);
		return;
	}

	$kontakt_post_type = (string) \get_post_type($value);
	if ($value > 0 && !\in_array($kontakt_post_type, ['kontakte', 'kontakt'], true)) {
		\delete_post_meta($post_id, CMX_SCANNER_REL_KONTAKTE_META);
		return;
	}

	cmx_scanner_ensure_doc_for_post($post_id);
	$doc_ids = cmx_scanner_get_doc_ids_for_post($post_id);

	if ($value > 0) {
		\update_post_meta($post_id, CMX_SCANNER_REL_KONTAKTE_META, $value);
		cmx_scanner_link_docs_to_kontakt($value, $doc_ids);

		// Nach erfolgreicher Verarbeitung auf die Scanner-Liste springen.
		// Das Löschen (CPT + Datei) erfolgt dort im Folge-Request.
		cmx_scanner_mark_redirect_to_list_after_save($post_id);
		return;
	}

	$new_kontakt_id = cmx_scanner_create_related_entry($post_id, 'kontakte');
	if ($new_kontakt_id <= 0) {
		\delete_post_meta($post_id, CMX_SCANNER_REL_KONTAKTE_META);
		return;
	}

	\update_post_meta($post_id, CMX_SCANNER_REL_KONTAKTE_META, $new_kontakt_id);
	cmx_scanner_link_docs_to_kontakt($new_kontakt_id, $doc_ids);
	cmx_scanner_mark_redirect_to_target_edit_after_save($post_id, $new_kontakt_id);
});
