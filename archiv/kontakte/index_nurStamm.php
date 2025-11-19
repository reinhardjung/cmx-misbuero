<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') or die('Oxytocin!');

/**
 * ==========================================================
 * CPT "kontakte"
 * ==========================================================
 */
\register_post_type('kontakte', [
	'label'               => 'Kontakte',
	'labels'              => [
		'name'          => 'Kontakte',
		'singular_name' => 'Kontakt',
		'menu_name'     => 'Kontakte',
		'all_items'     => 'Übersicht',
		'add_new'       => 'Kontakt hinzufügen',
		'add_new_item'  => 'Neuen Kontakt',
		'edit_item'     => 'Kontakt bearbeiten',
		'new_item'      => 'Neuer Kontakt',
		'view_item'     => 'Kontakt ansehen',
		'search_items'  => 'Kontakte suchen',
		'not_found'     => 'Keine Kontakte gefunden',
	],
	'public'              => true,
	'show_ui'             => true,
	'show_in_rest'        => true,
	'menu_position'       => 10,
	'publicly_queryable'  => true,
	'query_var'           => true,
	'has_archive'         => true,
	'supports'            => ['title','thumbnail','excerpt'], // Titel = Firmenname / Privatname
	'menu_icon'           => 'dashicons-businessman',
	'rewrite'             => ['slug' => 'kontakte', 'with_front' => true],
	'capability_type'     => 'post',
	// GraphQL (falls aktiv):
	'show_in_graphql'     => true,
	'graphql_single_name' => 'Kontakt',
	'graphql_plural_name' => 'Kontakte',
]);

/**
 * ==========================================================
 * Taxonomien (Beispiel)
 * ==========================================================
 */
\register_taxonomy('kontakt_type', ['kontakte'], [
	'hierarchical'      => true,
	'public'            => true,
	'show_ui'           => true,
	'show_in_rest'      => true,
	'show_admin_column' => false,
	'labels'            => [
		'name'          => 'Beziehungen',
		'singular_name' => 'Beziehung',
		'all_items'     => 'Alle Beziehungen',
		'add_new_item'  => 'Neue Beziehung hinzufügen',
		'edit_item'     => 'Beziehung bearbeiten',
	],
]);

/**
 * ==========================================================
 * Metadaten
 * ==========================================================
 */
const CMX_KONTAKTE_META_VORNAME  = '_cmx_kontakte_vorname';
const CMX_KONTAKTE_META_NACHNAME = '_cmx_kontakte_nachname';
const CMX_KONTAKTE_META_URL      = '_cmx_kontakte_url';
const CMX_KONTAKTE_META_PRIVAT   = '_cmx_kontakte_privat'; // 1/0

\add_action('init', __NAMESPACE__ . '\\cmx_register_kontakte_meta');
function cmx_register_kontakte_meta() {
	// Text-Metas
	foreach ([CMX_KONTAKTE_META_VORNAME, CMX_KONTAKTE_META_NACHNAME] as $key) {
		\register_post_meta('kontakte', $key, [
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => fn() => current_user_can('edit_posts'),
		]);
	}
	// URL
	\register_post_meta('kontakte', CMX_KONTAKTE_META_URL, [
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'esc_url_raw',
		'auth_callback'     => fn() => current_user_can('edit_posts'),
	]);
	// PRIVAT (Checkbox)
	\register_post_meta('kontakte', CMX_KONTAKTE_META_PRIVAT, [
		'type'              => 'integer',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'absint',
		'auth_callback'     => fn() => current_user_can('edit_posts'),
	]);
}

/**
 * ==========================================================
 * Metabox: Stammdaten (Vorname, Nachname, PRIVAT, URL)
 * ==========================================================
 */
\add_action('add_meta_boxes', __NAMESPACE__ . '\\cmx_add_stammdaten_metabox');
function cmx_add_stammdaten_metabox() {
	\add_meta_box('cmx_kontakte_stammdaten', 'Stammdaten', __NAMESPACE__ . '\\cmx_render_stammdaten_metabox', 'kontakte', 'normal', 'core');
}

function cmx_render_stammdaten_metabox(\WP_Post $post) {
	\wp_nonce_field('cmx_kontakte_save_meta', 'cmx_kontakte_nonce');

	$vorname   = \get_post_meta($post->ID, CMX_KONTAKTE_META_VORNAME, true);
	$nachname  = \get_post_meta($post->ID, CMX_KONTAKTE_META_NACHNAME, true);
	$privat    = (int) \get_post_meta($post->ID, CMX_KONTAKTE_META_PRIVAT, true);
	$url_raw   = (string) \get_post_meta($post->ID, CMX_KONTAKTE_META_URL, true);
	$url_disp  = trim($url_raw);
	if ($url_disp !== '' && !preg_match('~^https?://~i', $url_disp)) {
		$url_disp = 'https://' . ltrim($url_disp, '/');
	}

	echo '<style>
		.cmx-row{display:flex;gap:16px;flex-wrap:wrap}
		.cmx-col{flex:1 1 280px;min-width:220px}
		.cmx-text{width:100%;max-width:100%}
		.cmx-url-label a{text-decoration:none}
		.cmx-url-label a:hover{text-decoration:underline}
		.cmx-checkbox{display:flex;align-items:center;gap:8px;margin-top:5px}
	</style>';

	echo '<div class="cmx-row">
		<p class="cmx-col">
			<label for="cmx_vorname"><strong>Vorname</strong></label><br>
			<input id="cmx_vorname" name="cmx_vorname" type="text" class="cmx-text" value="'.esc_attr($vorname).'">
		</p>
		<p class="cmx-col">
			<label for="cmx_nachname"><strong>Nachname</strong></label><br>
			<input id="cmx_nachname" name="cmx_nachname" type="text" class="cmx-text" value="'.esc_attr($nachname).'">
		</p>
		<p class="cmx-col">
			<label for="cmx_privat"><strong>Privat</strong></label><br>
			<label class="cmx-checkbox">
				<input id="cmx_privat" name="cmx_privat" type="checkbox" value="1" '.checked($privat,1,false).' />
				<span>Kontakt ist privat (Titel = Vorname Nachname, wenn leer)</span>
			</label>
		</p>
		<p class="cmx-col">
			<label class="cmx-url-label" for="cmx_url">';
				if ($url_disp !== '') {
					echo '<a id="cmx_url_label_link" href="'.esc_url($url_disp).'" target="_blank" rel="noopener noreferrer"><strong>URL</strong></a>';
				} else {
					echo '<strong id="cmx_url_label_text">URL</strong>';
				}
	echo '		</label><br>
			<input id="cmx_url" name="cmx_url" type="url" class="cmx-text" placeholder="https://example.ch" value="'.esc_attr($url_raw).'"
				onblur="(function(el){
					var v=el.value.trim();
					if(v && !/^https?:\/\//i.test(v)){ v=\'https://\'+v.replace(/^\/+/, \'\'); }
					el.value=v;
					var labelLink=document.getElementById(\'cmx_url_label_link\');
					var labelText=document.getElementById(\'cmx_url_label_text\');
					if(v){
						if(!labelLink){
							var a=document.createElement(\'a\'); a.id=\'cmx_url_label_link\'; a.target=\'_blank\'; a.rel=\'noopener noreferrer\';
							a.innerHTML=\'<strong>URL</strong>\';
							labelText && labelText.replaceWith(a);
							labelLink=a;
						}
						labelLink.href=v;
					}
				})(this);">
		</p>
	</div>';
}

/**
 * Speichern: Vorname, Nachname, PRIVAT, URL + Titel-Autofill
 */
\add_action('save_post_kontakte', __NAMESPACE__ . '\\cmx_save_stammdaten_meta', 10);
function cmx_save_stammdaten_meta($post_id) {
	if (!isset($_POST['cmx_kontakte_nonce']) || !\wp_verify_nonce($_POST['cmx_kontakte_nonce'], 'cmx_kontakte_save_meta')) return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\wp_is_post_revision($post_id)) return;
	if (!current_user_can('edit_post', $post_id)) return;

	// Vorname / Nachname
	if (isset($_POST['cmx_vorname']))  \update_post_meta($post_id, CMX_KONTAKTE_META_VORNAME,  sanitize_text_field($_POST['cmx_vorname']));
	if (isset($_POST['cmx_nachname'])) \update_post_meta($post_id, CMX_KONTAKTE_META_NACHNAME, sanitize_text_field($_POST['cmx_nachname']));

	// PRIVAT
	$privat = isset($_POST['cmx_privat']) ? 1 : 0;
	\update_post_meta($post_id, CMX_KONTAKTE_META_PRIVAT, $privat);

	// URL normalisieren + speichern
	$url = '';
	if (isset($_POST['cmx_url'])) {
		$url = trim((string)$_POST['cmx_url']);
		if ($url !== '' && !preg_match('~^https?://~i', $url)) {
			$url = 'https://' . ltrim($url, '/');
		}
		\update_post_meta($post_id, CMX_KONTAKTE_META_URL, esc_url_raw($url));
	}

	// Titel nur setzen, wenn aktuell leer
	$current_title = (string)\get_post_field('post_title', $post_id);
	if (trim($current_title) === '') {
		$new_title = '';

		if ($privat === 1) {
			$vn = trim((string)\get_post_meta($post_id, CMX_KONTAKTE_META_VORNAME, true));
			$nn = trim((string)\get_post_meta($post_id, CMX_KONTAKTE_META_NACHNAME, true));
			$new_title = trim($vn . ' ' . $nn);
		}

		if ($new_title === '' && $url !== '') {
			$core = cmx_domain_core_from_url($url);
			if ($core !== '') $new_title = strtoupper($core); // Domainkern OHNE TLD, in Grossbuchstaben
		}

		if ($new_title === '') {
			$new_title = 'FIRMENNAME FEHLT';
		}

		\wp_update_post([
			'ID'         => $post_id,
			'post_title' => $new_title,
			'post_name'  => \sanitize_title($new_title),
		]);
	}
}

/**
 * Helper: Domain-Kern ohne TLD (z. B. "susi" aus "https://www.susi.ch")
 * mit Guard gegen Doppeldeklaration.
 */
if (!function_exists(__NAMESPACE__ . '\\cmx_domain_core_from_url')) {
	function cmx_domain_core_from_url(string $url) : string {
		if ($url !== '' && !preg_match('~^https?://~i', $url)) {
			$url = 'https://' . ltrim($url, '/');
		}
		$host = parse_url($url, PHP_URL_HOST);
		if (!$host) return '';
		$host  = strtolower(preg_replace('~^www\.~i', '', $host));
		$parts = explode('.', $host);
		if (count($parts) < 2) return $parts[0] ?? '';

		// Multi-Level-Suffixe (vereinfachte Liste)
		$multi_suffixes = [
			'co.uk','org.uk','gov.uk','ac.uk',
			'com.au','net.au','org.au',
			'co.nz','org.nz',
			'com.br','com.mx','com.tr','com.ar','com.pl','com.cn','com.tw','com.hk','com.sg','com.my','com.sa','com.eg',
			'co.jp','co.kr','co.in','co.za',
		];

		$last2 = implode('.', array_slice($parts, -2));
		if (!in_array($last2, $multi_suffixes, true)) {
			// Standard: letzter Teil = TLD, davor = Kern
			return $parts[count($parts)-2];
		}
		// Multi-Level: Kern sitzt eins weiter links
		return (count($parts) >= 3) ? $parts[count($parts)-3] : $parts[count($parts)-2];
	}
}

/**
 * Editor-UX & Permalink
 */
\add_filter('enter_title_here', function ($placeholder, $post) {
	return ($post && $post->post_type === 'kontakte') ? 'Firmenname (optional – wird automatisch gesetzt, wenn leer)' : $placeholder;
}, 10, 2);

\add_filter('gettext', function ($translated, $text) {
	$screen = function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen || $screen->post_type !== 'kontakte') return $translated;
	if ($text === 'Titel' || $text === 'Add title' || $text === 'Title') return 'Firmenname';
	return $translated;
}, 10, 2);

\add_filter('get_sample_permalink_html', function ($html, $post_id, $new_title, $new_slug, $post) {
	return ($post && $post->post_type === 'kontakte') ? '' : $html;
}, 10, 5);

\add_action('save_post_kontakte', __NAMESPACE__ . '\\cmx_update_slug_on_title', 20, 3);
function cmx_update_slug_on_title($post_ID, $post, $update) {
	if (\wp_is_post_revision($post_ID) || $post->post_status === 'auto-draft') return;
	$new_title = \get_post_field('post_title', $post_ID);
	$new_slug  = \sanitize_title($new_title);
	if ($new_slug !== $post->post_name) {
		\wp_update_post(['ID' => $post_ID, 'post_name' => $new_slug]);
	}
}
