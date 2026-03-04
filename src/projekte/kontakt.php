<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Meta Box (SIDE): Kontakt-Auswahl für Projekte (Mis Büro)
 * - CPT Projekte:   projekte
 * - CPT Kontakte:   kontakte
 * - Speichert Meta: _cmx_projekt_kontakt_id (int)
 */


/**
 * Meta Box registrieren (SIDE)
 */
function cmx_projekt_kontakt_header_url(int $projekt_id = 0): string {
	$list_url = \admin_url('edit.php?post_type=kontakte');
	if ($projekt_id <= 0) {
		return $list_url;
	}

	$kontakt_id = (int) \get_post_meta($projekt_id, '_cmx_projekt_kontakt_id', true);
	if ($kontakt_id <= 0) {
		return $list_url;
	}

	$kontakt_type = (string) \get_post_type($kontakt_id);
	if (!\in_array($kontakt_type, ['kontakte', 'kontakt'], true)) {
		return $list_url;
	}

	return (string) \admin_url('post.php?post=' . $kontakt_id . '&action=edit');
}

add_action('add_meta_boxes', function () {
	$projekt_id = 0;
	if (isset($_GET['post'])) {
		$projekt_id = (int) $_GET['post'];
	} elseif (isset($_POST['post_ID'])) {
		$projekt_id = (int) $_POST['post_ID'];
	}
	$kontakt_target_url = cmx_projekt_kontakt_header_url($projekt_id);
	$box_title = '<a id="cmx_projekt_kontakt_box_link" href="' . \esc_url($kontakt_target_url) . '" target="_blank" rel="noopener noreferrer" onclick="if(window.cmxProjektKontaktOpen){return window.cmxProjektKontaktOpen(event);}event.stopPropagation();" style="font-size:13px;font-weight:inherit;line-height:inherit;color:#2271b1;text-decoration:none;">' . \esc_html__('Zugehöriger Kontakt', 'cmx') . '</a>';
	add_meta_box(
		'cmx_projekt_kontakt_box',
		$box_title,
		__NAMESPACE__ . '\\cmx_render_projekt_kontakt_box',
		'projekte',
		'side',
		'default'
	);
});

/**
 * Render: Dropdown mit Kontakten
 */
function cmx_render_projekt_kontakt_box(\WP_Post $post): void {
	$selected = (int) get_post_meta($post->ID, '_cmx_projekt_kontakt_id', true);
	$list_url = \admin_url('edit.php?post_type=kontakte');
	$edit_prefix = \admin_url('post.php?post=');

	// Kontakte laden (alphabetisch)
	$kontakte = get_posts([
		'post_type'      => 'kontakte',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	]);

	wp_nonce_field('cmx_save_projekt_kontakt', 'cmx_projekt_kontakt_nonce');

	echo '<select name="cmx_projekt_kontakt_id" id="cmx_projekt_kontakt_select" style="width:100%;">';
	echo '<option value="">' . esc_html__('— Kein Kontakt —', 'cmx') . '</option>';

	foreach ($kontakte as $kontakt) {
		printf(
			'<option value="%d" %s>%s</option>',
			(int) $kontakt->ID,
			selected($selected, (int) $kontakt->ID, false),
			esc_html($kontakt->post_title)
		);
	}
	echo '</select>';
	echo '<script>
	(function(){
		var headerLink = document.getElementById("cmx_projekt_kontakt_box_link");
		var select = document.getElementById("cmx_projekt_kontakt_select");
		if (!headerLink || !select) return;

		var listUrl = ' . \wp_json_encode($list_url) . ';
		var editPrefix = ' . \wp_json_encode($edit_prefix) . ';

		var selectedKontaktId = function(){
			var id = parseInt(select.value || "0", 10);
			return (isNaN(id) || id <= 0) ? 0 : id;
		};

		var targetUrl = function(){
			var kontaktId = selectedKontaktId();
			return kontaktId > 0 ? (editPrefix + kontaktId + "&action=edit") : listUrl;
		};

		var syncHref = function(){
			var href = targetUrl();
			headerLink.href = href;
		};
		var openCurrent = function(e){
			if (e) {
				e.preventDefault();
				e.stopPropagation();
			}
			syncHref();
			var href = headerLink.href || listUrl;
			if (!href) return false;
			var w = window.open(href, "_blank", "noopener,noreferrer");
			if (w) { w.opener = null; }
			return false;
		};
		window.cmxProjektKontaktOpen = openCurrent;

		select.addEventListener("change", syncHref);
		select.addEventListener("input", syncHref);

		var linkClick = function(e){
			openCurrent(e);
		};
		var linkMouseDown = function(e){ e.stopPropagation(); };

		if (headerLink) {
			headerLink.addEventListener("mousedown", linkMouseDown, true);
			headerLink.addEventListener("click", linkClick, true);
			headerLink.addEventListener("auxclick", linkClick, true);
		}
		syncHref();
	})();
	</script>';

	// Kleiner Hinweis
	if (empty($kontakte)) {
		$new_kontakt_url = admin_url('post-new.php?post_type=kontakte');
		$anlegen_link = '<a href="' . esc_url($new_kontakt_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('anlegen', 'cmx') . '</a>';
		$hint_html = sprintf(__('Keine Kontakte - zuerst einen %s.', 'cmx'), $anlegen_link);
		echo '<p style="margin-top:8px;color:#666;">' . wp_kses($hint_html, [
			'a' => [
				'href'   => [],
				'target' => [],
				'rel'    => [],
			],
		]) . '</p>';
	}
}

/**
 * Save: Auswahl persistieren
 */
add_action('save_post_projekte', __NAMESPACE__ . '\\cmx_save_projekt_kontakt', 10, 1);
function cmx_save_projekt_kontakt(int $post_id): void {

	// Nonce prüfen
	if (
		! isset($_POST['cmx_projekt_kontakt_nonce']) ||
		! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cmx_projekt_kontakt_nonce'])), 'cmx_save_projekt_kontakt')
	) {
		return;
	}

	// Autosave / Rechte prüfen
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (! current_user_can('edit_post', $post_id)) return;

	// Wert aus POST
	$val = isset($_POST['cmx_projekt_kontakt_id']) ? (int) $_POST['cmx_projekt_kontakt_id'] : 0;

	// Nur existierende Kontakte akzeptieren
	if ($val > 0 && 'kontakte' === get_post_type($val)) {
		update_post_meta($post_id, '_cmx_projekt_kontakt_id', $val);
	} else {
		delete_post_meta($post_id, '_cmx_projekt_kontakt_id');
	}
}
