<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') or die('Oxytocin!');


/**
 * Konstanten (guarded)
 */
if (!defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_VORNAME')) define(__NAMESPACE__ . '\\CMX_KONTAKTE_META_VORNAME', '_cmx_kontakte_vorname');
if (!defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_NACHNAME')) define(__NAMESPACE__ . '\\CMX_KONTAKTE_META_NACHNAME', '_cmx_kontakte_nachname');
if (!defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_URL'))     define(__NAMESPACE__ . '\\CMX_KONTAKTE_META_URL',     '_cmx_kontakte_url');
if (!defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_PRIVAT'))  define(__NAMESPACE__ . '\\CMX_KONTAKTE_META_PRIVAT',  '_cmx_kontakte_privat');

/**
 * Metas registrieren (nur Stammdaten)
 */
\add_action('init', __NAMESPACE__ . '\\cmx_register_stammdaten_metas');
function cmx_register_stammdaten_metas() {
	$auth = fn() => current_user_can('edit_posts');

	foreach ([CMX_KONTAKTE_META_VORNAME, CMX_KONTAKTE_META_NACHNAME] as $key) {
		\register_post_meta('kontakte', $key, [
			'type' => 'string', 'single' => true, 'show_in_rest' => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback' => $auth,
		]);
	}
	\register_post_meta('kontakte', CMX_KONTAKTE_META_URL, [
		'type' => 'string', 'single' => true, 'show_in_rest' => true,
		'sanitize_callback' => 'esc_url_raw',
		'auth_callback' => $auth,
	]);
	\register_post_meta('kontakte', CMX_KONTAKTE_META_PRIVAT, [
		'type' => 'integer', 'single' => true, 'show_in_rest' => true,
		'sanitize_callback' => 'absint',
		'auth_callback' => $auth,
	]);
}

/**
 * Metabox registrieren
 */
\add_action('add_meta_boxes', __NAMESPACE__ . '\\cmx_add_stammdaten_metabox');
function cmx_add_stammdaten_metabox() {
	\add_meta_box(
		'cmx_kontakte_stammdaten',
		'Stammdaten',
		__NAMESPACE__ . '\\cmx_render_stammdaten_metabox',
		'kontakte',
		'normal',
		'core'
	);
}

/**
 * Metabox-Ausgabe
 */
function cmx_render_stammdaten_metabox(\WP_Post $post) {
	\wp_nonce_field('cmx_kontakte_save_meta', 'cmx_kontakte_nonce');

	$vorname  = \get_post_meta($post->ID, CMX_KONTAKTE_META_VORNAME, true);
	$nachname = \get_post_meta($post->ID, CMX_KONTAKTE_META_NACHNAME, true);
	$privat   = (int) \get_post_meta($post->ID, CMX_KONTAKTE_META_PRIVAT, true);
	$url_raw  = (string) \get_post_meta($post->ID, CMX_KONTAKTE_META_URL, true);

	$url_disp = trim($url_raw);
	if ($url_disp !== '' && !preg_match('~^https?://~i', $url_disp)) {
		$url_disp = 'https://' . ltrim($url_disp, '/');
	}


// … vorher: $vorname, $nachname, $url_raw, $url_disp berechnet …

// … vorher: $vorname, $nachname, $url_raw, $url_disp berechnet …

echo '<style>
	.cmx-row{display:flex;gap:16px;flex-wrap:wrap}
	/* Standard-Spalten */
	.cmx-col{flex:1 1 280px;min-width:220px}
	.cmx-text{width:100%;max-width:100%}

	/* Privat halb so groß */
	.cmx-col--privat{flex:0 0 120px;min-width:110px}
	.cmx-col--privat input[type=checkbox]{width:auto!important;height:auto!important;display:inline-block;margin:6px 6px 0 0}
	.cmx-col--privat .description{display:block;font-size:12px;color:#50575e;line-height:1.4;margin-top:2px}

	/* URL bekommt den gewonnenen Platz (wächst stärker) */
	.cmx-col--url{flex:2 1 440px;min-width:280px}
	.cmx-url-label a{text-decoration:none}
	.cmx-url-label a:hover{text-decoration:underline}
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

	<p class="cmx-col cmx-col--privat">
		<label for="cmx_privat"><strong>Privat</strong></label><br>
		<input id="cmx_privat" name="cmx_privat" type="checkbox" value="1" '.checked( (bool) get_post_meta($post->ID, "_cmx_kontakte_privat", true), true, false ).'>
		<span class="description">Kontakt ist privat (Titel = Vorname Nachname, wenn leer)</span>
	</p>

	<p class="cmx-col cmx-col--url">
		<label class="cmx-url-label" for="cmx_url">';
			if ($url_disp !== '') {
				echo '<a id="cmx_url_label_link" href="'.esc_url($url_disp).'" target="_blank" rel="noopener noreferrer"><strong>URL</strong></a>';
			} else {
				echo '<strong id="cmx_url_label_text">URL</strong>';
			}
echo '		</label><br>
		<input id="cmx_url" name="cmx_url" type="url" class="cmx-text" placeholder="https://example.ch" value="'.esc_attr($url_raw).'"
			onblur="if(this.value && !/^https?:\/\//i.test(this.value)){this.value=\'https://\'+this.value.trim().replace(/^\/+/, \'\');}">
	</p>
</div>';


}

/**
 * Speichern (Stammdaten) + Titel-Autofill
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

	// URL
	$url = '';
	if (isset($_POST['cmx_url'])) {
		$url = trim((string)$_POST['cmx_url']);
		if ($url !== '' && !preg_match('~^https?://~i', $url)) {
			$url = 'https://' . ltrim($url, '/');
		}
		\update_post_meta($post_id, CMX_KONTAKTE_META_URL, esc_url_raw($url));
	}

	// Titel setzen, wenn leer
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
			if ($core !== '') $new_title = strtoupper($core); // Domainkern OHNE TLD
		}
		if ($new_title === '') $new_title = 'FIRMENNAME FEHLT';

		\wp_update_post([
			'ID'         => $post_id,
			'post_title' => $new_title,
			'post_name'  => \sanitize_title($new_title),
		]);
	}
}
