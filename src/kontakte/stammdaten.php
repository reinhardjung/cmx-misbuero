<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * ------------------------------------------------------------
 * Konstanten / Metaschlüssel
 * ------------------------------------------------------------
 */
const CMX_KONTAKTE_META_VORNAME  = '_cmx_kontakte_vorname';
const CMX_KONTAKTE_META_NACHNAME = '_cmx_kontakte_nachname';
const CMX_KONTAKTE_META_ANREDE   = '_cmx_kontakte_anrede';
const CMX_KONTAKTE_META_URL      = '_cmx_kontakte_url';
const CMX_KONTAKTE_META_PRIVAT   = '_cmx_kontakte_privat';
const CMX_KONTAKTE_META_DATUM    = '_cmx_kontakte_datum';

/**
 * ------------------------------------------------------------
 * Metas registrieren
 * ------------------------------------------------------------
 */
\add_action('init', __NAMESPACE__ . '\\cmx_register_kontakte_stammdaten_meta');
function cmx_register_kontakte_stammdaten_meta() {
	// Text
	foreach ([CMX_KONTAKTE_META_VORNAME, CMX_KONTAKTE_META_NACHNAME, CMX_KONTAKTE_META_ANREDE] as $key) {
		\register_post_meta('kontakte', $key, [
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => fn() => \current_user_can('edit_posts'),
		]);
	}
	// URL
	\register_post_meta('kontakte', CMX_KONTAKTE_META_URL, [
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'esc_url_raw',
		'auth_callback'     => fn() => \current_user_can('edit_posts'),
	]);
	// PRIVAT (bool)
	\register_post_meta('kontakte', CMX_KONTAKTE_META_PRIVAT, [
		'type'              => 'boolean',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => fn($v) => (bool)$v,
		'auth_callback'     => fn() => \current_user_can('edit_posts'),
	]);
	// DATUM (YYYY-MM-DD)
	\register_post_meta('kontakte', CMX_KONTAKTE_META_DATUM, [
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => __NAMESPACE__ . '\\cmx_sanitize_date_ymd',
		'auth_callback'     => fn() => \current_user_can('edit_posts'),
	]);
}

/** Sanitizer: exakt YYYY-MM-DD, sonst '' */
function cmx_sanitize_date_ymd($v): string {
	$v = \is_string($v) ? \trim($v) : '';
	if ($v === '') return '';
	$dt = \DateTime::createFromFormat('Y-m-d', $v);
	return ($dt && $dt->format('Y-m-d') === $v) ? $v : '';
}

/**
 * ------------------------------------------------------------
 * Metabox registrieren
 * ------------------------------------------------------------
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
 * ------------------------------------------------------------
 * Metabox-Ausgabe (Grid-Layout, schmale PRIVAT-Spalte, URL-Label ist Link)
 * + Datumsfeld rechts neben URL inkl. dynamischem Label
 * ------------------------------------------------------------
 */
function cmx_render_stammdaten_metabox(\WP_Post $post) {
	\wp_nonce_field('cmx_kontakte_save_meta', 'cmx_kontakte_nonce');

	$vorname  = (string) \get_post_meta($post->ID, CMX_KONTAKTE_META_VORNAME, true);
	$nachname = (string) \get_post_meta($post->ID, CMX_KONTAKTE_META_NACHNAME, true);
	$anrede   = (string) \get_post_meta($post->ID, CMX_KONTAKTE_META_ANREDE, true);
	$privat   = (bool)   \get_post_meta($post->ID, CMX_KONTAKTE_META_PRIVAT, true);
	$url_raw  = (string) \get_post_meta($post->ID, CMX_KONTAKTE_META_URL, true);
	$datum    = (string) \get_post_meta($post->ID, CMX_KONTAKTE_META_DATUM, true);

	// Für das Label (nur Anzeige) https:// ergänzen
	$url_disp = \trim($url_raw);
	if ($url_disp !== '' && !\preg_match('~^https?://~i', $url_disp)) {
		$url_disp = 'https://' . \ltrim($url_disp, '/');
	}

	echo '<style>
	#cmx-stammdaten .grid {
		display: grid !important;
		grid-template-columns:
			minmax(240px, 1fr)
			minmax(240px, 1fr)
			minmax(180px, 0.8fr);
		column-gap: 16px;
		row-gap: 16px;
		align-items: start;
	}
	#cmx-stammdaten .field {margin:0; display:block !important; flex:none !important;}
	#cmx-stammdaten .field--privat{width:120px !important}
	#cmx-stammdaten .field--anrede{min-width:200px;}
	#cmx-stammdaten .text,
	#cmx-stammdaten .date {width:100% !important; max-width:100% !important}
	#cmx-stammdaten .field--datum .date {width:100% !important; max-width:100% !important}
	#cmx-stammdaten .url-label a{text-decoration:none}
	#cmx-stammdaten .url-label a:hover{text-decoration:underline}
	#cmx-stammdaten input[type=checkbox]{
		appearance:auto !important; -webkit-appearance:checkbox !important; -moz-appearance:checkbox !important;
		width:auto !important; height:auto !important; display:inline-block !important; margin:6px 6px 0 0 !important; vertical-align:middle !important;
	}
	#cmx-stammdaten .field--privat label{display:block;margin-bottom:4px}
</style>';

	echo '<div id="cmx-stammdaten"><div class="grid">';

	// Vorname
	echo '<p class="field">
		<label for="cmx_vorname"><strong>Vorname</strong></label><br>
		<input id="cmx_vorname" name="cmx_vorname" type="text" class="text" value="' . \esc_attr($vorname) . '">
	</p>';

	// Nachname
	echo '<p class="field">
		<label for="cmx_nachname"><strong>Nachname</strong></label><br>
		<input id="cmx_nachname" name="cmx_nachname" type="text" class="text" value="' . \esc_attr($nachname) . '">
	</p>';

	// Privat (schmal)
	echo '<p class="field field--privat" style="text-align:center;">
		<label for="cmx_privat"><strong>Privat</strong></label>
		<input id="cmx_privat" name="cmx_privat" type="checkbox" value="1" ' . \checked($privat, true, false) . '>
	</p>';

	// Anrede
	echo '<p class="field field--anrede">
		<label for="cmx_anrede"><strong>Anrede</strong></label><br>
		<input id="cmx_anrede" name="cmx_anrede" type="text" class="text" placeholder="Sehr geehrte Frau B&auml;rtschi" value="' . \esc_attr($anrede) . '">
	</p>';

	// URL (Label ist Link)
	echo '<p class="field">
		<label class="url-label" for="cmx_url">';
			if ($url_disp !== '') {
				echo '<a href="' . \esc_url($url_disp) . '" target="_blank" rel="noopener noreferrer"><strong>URL</strong></a>';
			} else {
				echo '<strong>URL</strong>';
			}
	echo '	</label><br>
		<input id="cmx_url" name="cmx_url" type="url" class="text" placeholder="https://example.ch" value="' . \esc_attr($url_raw) . '"
			onblur="if(this.value && !/^https?:\\/\\//i.test(this.value)){this.value=\'https://\'+this.value.trim().replace(/^\\/+/ , \'\');}">
	</p>';

	// Datum (rechts neben URL) mit dynamischem Label
	$label_text = $privat ? 'Geburtsdatum' : 'Firmengründung';
	echo '<p class="field field--datum">
	<label for="cmx_datum"><strong id="cmx_datum_label">' . \esc_html($label_text) . '</strong></label><br>
	<input id="cmx_datum" name="cmx_datum" type="date" class="date" value="' . \esc_attr($datum) . '">
	</p>';

	echo '</div></div>';

	// Inline-JS für dynamisches Label
	echo '<script>
	(function(){
		const cb   = document.getElementById("cmx_privat");
		const lab  = document.getElementById("cmx_datum_label");
		if(!cb || !lab) return;
		function syncLabel(){
			lab.textContent = cb.checked ? "Geburtsdatum" : "Firmengründung";
		}
		cb.addEventListener("change", syncLabel, {passive:true});
		document.addEventListener("DOMContentLoaded", syncLabel, {once:true});
		syncLabel();
	})();
	</script>';
}

/**
 * ------------------------------------------------------------
 * Save-Handler (Metabox speichern)
 * ------------------------------------------------------------
 */
\add_action('save_post_kontakte', __NAMESPACE__ . '\\cmx_save_kontakte_meta');
\add_action('save_post_kontakt',  __NAMESPACE__ . '\\cmx_save_kontakte_meta'); // falls Singular-CPT existiert
function cmx_save_kontakte_meta(int $post_id): void {
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!isset($_POST['cmx_kontakte_nonce']) || !\wp_verify_nonce($_POST['cmx_kontakte_nonce'], 'cmx_kontakte_save_meta')) return;
	if (!\current_user_can('edit_post', $post_id)) return;

	// Vorname / Nachname
	if (isset($_POST['cmx_vorname'])) {
		\update_post_meta($post_id, CMX_KONTAKTE_META_VORNAME, \sanitize_text_field((string)$_POST['cmx_vorname']));
	}
	if (isset($_POST['cmx_nachname'])) {
		\update_post_meta($post_id, CMX_KONTAKTE_META_NACHNAME, \sanitize_text_field((string)$_POST['cmx_nachname']));
	}

	// Privat
	$privat_val = isset($_POST['cmx_privat']) ? 1 : 0;
	\update_post_meta($post_id, CMX_KONTAKTE_META_PRIVAT, $privat_val);

	// Anrede
	if (isset($_POST['cmx_anrede'])) {
		\update_post_meta($post_id, CMX_KONTAKTE_META_ANREDE, \sanitize_text_field((string) $_POST['cmx_anrede']));
	}

	// URL
	if (isset($_POST['cmx_url'])) {
		$url = (string) $_POST['cmx_url'];
		if ($url !== '' && !\preg_match('~^https?://~i', $url)) {
			$url = 'https://' . \ltrim($url, '/');
		}
		\update_post_meta($post_id, CMX_KONTAKTE_META_URL, \esc_url_raw($url));
	}

	// Datum (YYYY-MM-DD)
	if (isset($_POST['cmx_datum'])) {
		$in = (string) $_POST['cmx_datum'];
		$dt = \DateTime::createFromFormat('Y-m-d', $in);
		$val = ($dt && $dt->format('Y-m-d') === $in) ? $in : '';
		if ($val === '') {
			\delete_post_meta($post_id, CMX_KONTAKTE_META_DATUM);
		} else {
			\update_post_meta($post_id, CMX_KONTAKTE_META_DATUM, $val);
		}
	}
}

/**
 * ------------------------------------------------------------
 * Helper: URL normalisieren + Domain ermitteln (für Admin-Column)
 * ------------------------------------------------------------
 */
function cmx_normalize_url_for_href(?string $url): string {
	$url = \trim((string)$url);
	if ($url === '') return '';
	if (!\preg_match('~^https?://~i', $url)) {
		$url = 'https://' . \ltrim($url, '/');
	}
	return $url;
}

/** Ursprungsdomain (ohne www, mit TLD-Intelligenz) */
function cmx_domain_core_from_url(string $url): string {
	$url = \trim($url);
	if ($url === '') return '';

	if (!\preg_match('~^https?://~i', $url)) {
		$url = 'https://' . \ltrim($url, '/');
	}

	$host = (string)(\parse_url($url, PHP_URL_HOST) ?? '');
	if ($host === '') return '';

	// IDN → ASCII (für Umlaute)
	if (\function_exists('idn_to_ascii')) {
		$ascii = @\idn_to_ascii($host, 0, \defined('INTL_IDNA_VARIANT_UTS46') ? \INTL_IDNA_VARIANT_UTS46 : 0);
		if ($ascii) $host = $ascii;
	}

	$host   = \preg_replace('~^www\.~i', '', $host);
	$labels = \array_values(\array_filter(\explode('.', \strtolower($host))));
	$n      = \count($labels);
	if ($n === 0) return '';

	$twoPart = [
		'co.uk','org.uk','ac.uk','gov.uk',
		'com.au','net.au','org.au','gov.au',
		'co.jp','ne.jp','or.jp',
		'com.br','com.ar','com.mx','com.tr',
		'co.nz','org.nz'
	];

	if ($n >= 3) {
		$lastTwo = $labels[$n-2] . '.' . $labels[$n-1];
		if (\in_array($lastTwo, $twoPart, true)) return $labels[$n-3];
	}
	if ($n >= 2) return $labels[$n-2];
	return $labels[0];
}
