<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/** Falls noch nicht vorhanden: Helper für Stufen-Taxo */
if (!function_exists(__NAMESPACE__.'\\cmx_stufen_tax')) {
	function cmx_stufen_tax(): string {
		foreach (['kontakte_stufen','stufen','kontakte_stufe','kontakt_stufen'] as $t) {
			if (\taxonomy_exists($t)) return $t;
		}
		return 'kontakte_stufen';
	}
}

/** === SIDE-Metabox registrieren (CPT: kontakte) ========================== */
\add_action('add_meta_boxes', function () {
	if (!\post_type_exists('kontakte')) return;
	$tax = cmx_stufen_tax();
	if (!\taxonomy_exists($tax)) return;

	\add_meta_box(
		'cmx_kontakte_stufe_side',
		'Stufe',
		__NAMESPACE__ . '\\cmx_kontakte_stufe_side_html',
		'kontakte',
		'side',
		'default',
		['taxonomy' => $tax]
	);
});

/** === Metabox-Callback: Radio-Liste der Stufen =========================== */
function cmx_kontakte_stufe_side_html($post, array $box): void {
	$tax = isset($box['args']['taxonomy']) ? (string)$box['args']['taxonomy'] : cmx_stufen_tax();
	if (!\taxonomy_exists($tax)) {
		echo '<p><em>Taxonomie nicht gefunden: </em><code>'.esc_html($tax).'</code></p>';
		return;
	}

	\wp_nonce_field('cmx_stufe_save', 'cmx_stufe_nonce');

	// Aktuell gewählte Stufe (einzelner Term)
	$current_term_id = '';
	$assigned = \get_the_terms($post->ID, $tax);
	if (!\is_wp_error($assigned) && !empty($assigned)) {
		// Nimm den ersten, da wir nur Einzelzuweisung erlauben
		$first = reset($assigned);
		$current_term_id = (string)$first->term_id;
	}

	$terms = \get_terms([
		'taxonomy'   => $tax,
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	]);
	if (\is_wp_error($terms) || empty($terms)) {
		echo '<p><em>Noch keine Stufen angelegt.</em></p>';
		return;
	}

	// Kleines CSS für lange Beschreibungen
	echo '<style>
		.cmx-stufe-radio { margin: 6px 0; }
		.cmx-stufe-desc  { display:block; margin-left:22px; font-size:11px; color:#555; }
		#cmx_kontakte_stufe_side .inside { max-height: 340px; overflow:auto; }
	</style>';

	// „Keine Stufe“ (entfernt Zuweisung)
	echo '<p class="cmx-stufe-radio">
		<label>
			<input type="radio" name="cmx_stufe_term_id" value="" '.checked($current_term_id, '', false).'>
			<strong>Keine Stufe</strong>
		</label>
	</p>';

	// Alle Stufen als Radios (Name + optionale Beschreibung)
	foreach ($terms as $t) {
		$tid   = (string)$t->term_id;
		$name  = (string)$t->name;
		$desc  = trim((string)$t->description);
		$title = $desc !== '' ? ' title="'.esc_attr(\wp_strip_all_tags($desc)).'"' : '';

		echo '<p class="cmx-stufe-radio"'.$title.'>
			<label>
				<input type="radio" name="cmx_stufe_term_id" value="'.esc_attr($tid).'" '.checked($current_term_id, $tid, false).'>
				<span>'.esc_html($name).'</span>
			</label>';

		if ($desc !== '') {
			// Kurzfassung sichtbar, kompletter Text im title (oben)
			echo '<small class="cmx-stufe-desc">'.esc_html(\wp_strip_all_tags(\wp_trim_words($desc, 20, '…'))).'</small>';
		}
		echo '</p>';
	}
}

/** === Speichern: exakt eine Stufe setzen (oder entfernen) ================ */
\add_action('save_post_kontakte', function ($post_id) {
	// Nonce & Autosave/Capability prüfen
	if (!isset($_POST['cmx_stufe_nonce']) || !\wp_verify_nonce($_POST['cmx_stufe_nonce'], 'cmx_stufe_save')) return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\wp_is_post_autosave($post_id) || \wp_is_post_revision($post_id)) return;
	if (!\current_user_can('edit_post', $post_id)) return;

	$tax = cmx_stufen_tax();
	if (!\taxonomy_exists($tax)) return;

	$incoming = isset($_POST['cmx_stufe_term_id']) ? (string)\sanitize_text_field(\wp_unslash($_POST['cmx_stufe_term_id'])) : '';

	if ($incoming === '') {
		// Auswahl „Keine Stufe“ → alle Stufen-Zuweisungen entfernen
		\wp_set_object_terms($post_id, [], $tax, false);
		return;
	}

	$term_id = (int)$incoming;
	$term    = \get_term_by('id', $term_id, $tax);
	if ($term && !\is_wp_error($term)) {
		// Einzelauswahl (replace = false für vollständiges Ersetzen)
		\wp_set_object_terms($post_id, [$term_id], $tax, false);
	}
});
