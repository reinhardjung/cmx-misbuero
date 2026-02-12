<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!function_exists(__NAMESPACE__ . '\\cmx_projekte_status_tax')) {
	function cmx_projekte_status_tax(): string {
		foreach (['projekte_status', 'projekt_status', 'status'] as $t) {
			if (\taxonomy_exists($t) && \is_object_in_taxonomy('projekte', $t)) return $t;
		}
		$const = __NAMESPACE__ . '\\TAX_PROJEKTE_STATUS';
		if (\defined($const)) {
			$tax = (string) \constant($const);
			if ($tax !== '' && \taxonomy_exists($tax) && \is_object_in_taxonomy('projekte', $tax)) {
				return $tax;
			}
		}
		return 'projekte_status';
	}
}

/**
 * Status-Metabox als Radio (einzelne Auswahl) statt Core-Checkbox-Liste
 * - Aufbau analog zu kontakte/stufen.php
 */
\add_action('add_meta_boxes', function () {
	if (!\post_type_exists('projekte')) return;
	$tax = cmx_projekte_status_tax();
	if (!\taxonomy_exists($tax)) return;

	// Core-Metabox entfernen (falls dennoch vorhanden)
	\remove_meta_box($tax . 'div', 'projekte', 'side');
	\remove_meta_box($tax . 'div', 'projekte', 'normal');
	\remove_meta_box($tax . 'div', 'projekte', 'advanced');
	\remove_meta_box('tagsdiv-' . $tax, 'projekte', 'side');
	\remove_meta_box('tagsdiv-' . $tax, 'projekte', 'normal');
	\remove_meta_box('tagsdiv-' . $tax, 'projekte', 'advanced');

	\add_meta_box(
		'cmx_projekte_status_side',
		'Status',
		__NAMESPACE__ . '\\cmx_projekte_status_side_html',
		'projekte',
		'side',
		'default',
		['taxonomy' => $tax]
	);
}, 99);

function cmx_projekte_status_side_html($post, array $box): void {
	$tax = isset($box['args']['taxonomy']) ? (string)$box['args']['taxonomy'] : cmx_projekte_status_tax();
	if (!\taxonomy_exists($tax)) {
		echo '<p><em>Taxonomie nicht gefunden: </em><code>'.\esc_html($tax).'</code></p>';
		return;
	}

	\wp_nonce_field('cmx_projekte_status_save', 'cmx_projekte_status_nonce');

	// Aktuell gewählter Status (einzelner Term)
	$current_term_id = '';
	$assigned = \get_the_terms($post->ID, $tax);
	if (!\is_wp_error($assigned) && !empty($assigned)) {
		$first = \reset($assigned);
		$current_term_id = (string) $first->term_id;
	}

	$terms = \get_terms([
		'taxonomy'   => $tax,
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	]);
	if (\is_wp_error($terms) || empty($terms)) {
		echo '<p><em>Noch keine Status-Werte angelegt.</em></p>';
		return;
	}

	// Sichtbare Beschreibung wie bei Stufe
	echo '<style>
		.cmx-proj-status-radio { margin: 6px 0; }
		.cmx-proj-status-desc  { display:block; margin-left:22px; font-size:11px; color:#555; }
		#cmx_projekte_status_side .inside { max-height: 340px; overflow:auto; }
	</style>';

	// "Kein Status" entfernt die Zuweisung
	echo '<p class="cmx-proj-status-radio">
		<label>
			<input type="radio" name="cmx_projekte_status_term_id" value="" '.\checked($current_term_id, '', false).'>
			<strong>Kein Status</strong>
		</label>
	</p>';

	foreach ($terms as $t) {
		$tid   = (string) $t->term_id;
		$desc  = \trim((string) $t->description);
		$title = $desc !== '' ? ' title="'.\esc_attr(\wp_strip_all_tags($desc)).'"' : '';

		echo '<p class="cmx-proj-status-radio"'.$title.'>
			<label>
				<input type="radio" name="cmx_projekte_status_term_id" value="'.\esc_attr($tid).'" '.\checked($current_term_id, $tid, false).'>
				<span>'.\esc_html($t->name).'</span>
			</label>';

		if ($desc !== '') {
			echo '<small class="cmx-proj-status-desc">'.\esc_html(\wp_strip_all_tags(\wp_trim_words($desc, 20, '…'))).'</small>';
		}
		echo '</p>';
	}
}

/**
 * Speichern: genau einen Status-Term setzen (oder alle entfernen).
 */
\add_action('save_post_projekte', function ($post_id) {
	if (!isset($_POST['cmx_projekte_status_nonce']) || !\wp_verify_nonce($_POST['cmx_projekte_status_nonce'], 'cmx_projekte_status_save')) return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\wp_is_post_autosave($post_id) || \wp_is_post_revision($post_id)) return;
	if (!\current_user_can('edit_post', $post_id)) return;

	$tax = cmx_projekte_status_tax();
	if (!\taxonomy_exists($tax)) return;

	$incoming = isset($_POST['cmx_projekte_status_term_id'])
		? (string) \sanitize_text_field(\wp_unslash($_POST['cmx_projekte_status_term_id']))
		: '';

	if ($incoming === '') {
		\wp_set_object_terms($post_id, [], $tax, false);
		return;
	}

	$term_id = (int) $incoming;
	$term = \get_term($term_id, $tax);
	if ($term && !\is_wp_error($term)) {
		\wp_set_object_terms($post_id, [$term_id], $tax, false);
	}
}, 15);
