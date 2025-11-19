<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') or die('Oxytocin!');

/**
 * Meta-Key für Umsatz
 */
if (!defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_UMSATZ')) {
	define(__NAMESPACE__ . '\\CMX_KONTAKTE_META_UMSATZ', '_cmx_kontakte_umsatz');
}

/**
 * Metabox "Umsatz" an der Seite für CPT "kontakte"
 */
\add_action('add_meta_boxes', __NAMESPACE__ . '\\cmx_add_umsatz_metabox');
function cmx_add_umsatz_metabox() {
	\add_meta_box(
		'cmx_kontakte_umsatz',
		'Umsatz',
		__NAMESPACE__ . '\\cmx_render_umsatz_metabox',
		'kontakte',
		'side',
		'default'
	);
}

/**
 * Render der Metabox
 */
function cmx_render_umsatz_metabox(\WP_Post $post) {
	\wp_nonce_field('cmx_kontakte_umsatz_save', 'cmx_kontakte_umsatz_nonce');
	$raw   = (string) \get_post_meta($post->ID, CMX_KONTAKTE_META_UMSATZ, true);
	$value = $raw !== '' ? $raw : '';
	echo '<p style="margin-top:0">
		<label for="cmx_kontakte_umsatz_field"><strong>Jahresumsatz</strong></label>
		<input type="number" step="0.01" min="0" class="widefat"
			name="cmx_kontakte_umsatz_field" id="cmx_kontakte_umsatz_field"
			placeholder="z. B. 125000.00"
			value="' . \esc_attr($value) . '">
		<small style="color:#666">Bitte als Zahl mit Punkt als Dezimaltrenner eingeben (z. B. 1999.95).</small>
	</p>';
}

/**
 * Save-Handler (normalisiert und speichert als Dezimalzahl mit Punkt)
 */
\add_action('save_post_kontakte', __NAMESPACE__ . '\\cmx_save_kontakte_umsatz');
\add_action('save_post_kontakt',  __NAMESPACE__ . '\\cmx_save_kontakte_umsatz'); // falls CPT "kontakt" heisst
function cmx_save_kontakte_umsatz(int $post_id) {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!isset($_POST['cmx_kontakte_umsatz_nonce']) || !\wp_verify_nonce($_POST['cmx_kontakte_umsatz_nonce'], 'cmx_kontakte_umsatz_save')) return;

	// Berechtigung prüfen
	if (!\current_user_can('edit_post', $post_id)) return;

	// Feld vorhanden?
	if (!isset($_POST['cmx_kontakte_umsatz_field'])) return;

	$in = (string) $_POST['cmx_kontakte_umsatz_field'];

	// Normalisieren: Leerzeichen/CHF/' entfernen, Komma -> Punkt, nur Ziffern & Punkt behalten
	$in = \preg_replace('~\s|CHF|\'~i', '', $in);
	$in = \str_replace(',', '.', $in);
	$in = \preg_replace('~[^0-9\.]~', '', $in);

	// Leere Werte löschen, sonst speichern
	if ($in === '') {
		\delete_post_meta($post_id, CMX_KONTAKTE_META_UMSATZ);
	} else {
		// auf 2 Nachkommastellen begrenzen
		$val = \number_format((float)$in, 2, '.', '');
		\update_post_meta($post_id, CMX_KONTAKTE_META_UMSATZ, $val);
	}
}

/**
 * CHF-Format (Fallback, wenn bereits cmx_chf() existiert, wird diese verwendet)
 */
if (!\function_exists(__NAMESPACE__ . '\\cmx_chf')) {
	function cmx_chf(float $amount): string {
		return \number_format($amount, 2, '.', "'");
	}
}

/**
 * Admin-Columns: Spalte "Umsatz" vor "Karte" einschieben
 */
\add_filter('manage_edit-kontakte_columns', __NAMESPACE__ . '\\cmx_kontakte_columns_umsatz');
\add_filter('manage_edit-kontakt_columns',  __NAMESPACE__ . '\\cmx_kontakte_columns_umsatz'); // falls CPT "kontakt"
function cmx_kontakte_columns_umsatz(array $columns): array {
	$new = [];
	$umsatz_key = 'cmx_umsatz';

	foreach ($columns as $key => $label) {
		// Vor "Karte" einfügen
		if ($key === 'karte') {
			$new[$umsatz_key] = 'Umsatz';
		}
		$new[$key] = $label;
	}

	// Fallback: wenn keine "Karte"-Spalte existiert, hinten anhängen
	if (!isset($new[$umsatz_key])) {
		$new[$umsatz_key] = 'Umsatz';
	}

	return $new;
}

/**
 * Admin-Columns: Inhalt für "Umsatz"
 */
\add_action('manage_kontakte_posts_custom_column', __NAMESPACE__ . '\\cmx_kontakte_column_umsatz', 10, 2);
\add_action('manage_kontakt_posts_custom_column',  __NAMESPACE__ . '\\cmx_kontakte_column_umsatz', 10, 2);
function cmx_kontakte_column_umsatz(string $column, int $post_id): void {
	if ($column !== 'cmx_umsatz') return;

	$raw = (string) \get_post_meta($post_id, CMX_KONTAKTE_META_UMSATZ, true);
	if ($raw === '') {
		echo '—';
		return;
	}

	$val = (float) $raw;

	// Wenn global bereits eine eigene cmx_chf() existiert, diese nutzen
	if (\function_exists(__NAMESPACE__ . '\\cmx_chf')) {
		echo \esc_html(cmx_chf($val));
	} else {
		// Fallback
		echo \esc_html('CHF ' . \number_format($val, 2, '.', "'"));
	}
}



/**
 * Spalte "Umsatz" als sortierbar registrieren (wie bei Dir)
 */
\add_filter('manage_edit-kontakte_sortable_columns', __NAMESPACE__.'\\cmx_kontakte_sortable_umsatz');
\add_filter('manage_edit-kontakt_sortable_columns',  __NAMESPACE__.'\\cmx_kontakte_sortable_umsatz');
function cmx_kontakte_sortable_umsatz(array $columns): array {
    $columns['cmx_umsatz'] = 'cmx_umsatz';
    return $columns;
}

/**
 * Saubere Sortierung per SQL-Clauses:
 * - Leere/fehlende Umsätze immer ans Ende
 * - Numerische Sortierung (ASC/DESC) über (meta_value+0)
 */
\add_filter('posts_clauses', __NAMESPACE__.'\\cmx_kontakte_umsatz_clauses', 10, 2);
function cmx_kontakte_umsatz_clauses(array $clauses, \WP_Query $q): array {
    if (!\is_admin() || !$q->is_main_query()) return $clauses;

    // Post-Type robust ermitteln
    $pt = $q->get('post_type') ?: (isset($_GET['post_type']) ? (string) $_GET['post_type'] : '');
    if (\is_array($pt)) $pt = reset($pt);
    if (!\in_array($pt, ['kontakte','kontakt'], true)) return $clauses;

    // Nur reagieren, wenn nach unserer Spalte sortiert wird
    $orderby = (string) $q->get('orderby');
    if ($orderby !== 'cmx_umsatz') return $clauses;

    if (!\defined(__NAMESPACE__.'\\CMX_KONTAKTE_META_UMSATZ')) return $clauses;

    global $wpdb;
    $order = strtoupper((string) $q->get('order')) === 'ASC' ? 'ASC' : 'DESC';

    // Eindeutiger LEFT JOIN auf unser Meta (eigener Alias)
    $clauses['join'] .= $wpdb->prepare(
        " LEFT JOIN {$wpdb->postmeta} AS cmx_ums
          ON cmx_ums.post_id = {$wpdb->posts}.ID
         AND cmx_ums.meta_key = %s",
        CMX_KONTAKTE_META_UMSATZ
    );

    // Leere ans Ende, dann numerisch sortieren, stabilisieren mit Post-ID
    $clauses['orderby'] =
        "CASE WHEN cmx_ums.meta_value IS NULL OR cmx_ums.meta_value = '' THEN 1 ELSE 0 END ASC, " .
        "(cmx_ums.meta_value+0) {$order}, {$wpdb->posts}.ID ASC";

    return $clauses;
}
