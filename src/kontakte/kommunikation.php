<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/** Verbindliche Taxonomien (unverändert) */
const CMX_TAX_PHONE_LABELS = 'kontakte_telefone';
const CMX_TAX_MAIL_LABELS  = 'kontakte_emails';

/** Hilfsfunktionen (unverändert) */
function cmx_get_terms_normalized(string $taxonomy): array {
	$terms = \get_terms([
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
		'fields'     => 'all',
	]);
	if (\is_wp_error($terms) || empty($terms)) return [];
	$out = [];
	foreach ($terms as $t) {
		if (\is_object($t) && isset($t->slug, $t->name)) {
			$out[] = ['slug' => (string)$t->slug, 'name' => (string)$t->name];
		} elseif (\is_array($t)) {
			$slug = isset($t['slug']) ? (string)$t['slug'] : '';
			$name = isset($t['name']) ? (string)$t['name'] : $slug;
			if ($slug !== '' || $name !== '') $out[] = ['slug' => $slug, 'name' => $name];
		}
	}
	return $out;
}
function cmx_term_slug_exists(string $taxonomy, string $slug): bool {
	if (!$taxonomy || !$slug) return false;
	$t = \get_term_by('slug', $slug, $taxonomy);
	return ($t && !\is_wp_error($t));
}
function cmx_label_dropdown(array $terms, string $name, array $meta, string $taxonomy): string {
	$current = isset($meta[$name]) ? (string)$meta[$name] : '';
	$html  = '<select name="cmx_kommunikation[' . \esc_attr($name) . ']" data-taxonomy="'.\esc_attr($taxonomy).'">';
	$html .= '<option value="">auswählen</option>';
	foreach ($terms as $t) {
		$slug = (string)($t['slug'] ?? '');
		$txt  = (string)($t['name'] ?? $slug);
		$html .= '<option value="' . \esc_attr($slug) . '"' . \selected($current, $slug, false) . '>' . \esc_html($txt) . '</option>';
	}
	$html .= '</select>';
	return $html;
}

function cmx_kommunikation_taxonomy_label_html(string $taxonomy, string $label): string {
	if ($taxonomy === '' || !\taxonomy_exists($taxonomy)) {
		return \esc_html($label);
	}

	$url = \admin_url('edit-tags.php?taxonomy=' . \rawurlencode($taxonomy) . '&post_type=kontakte');

	return '<a href="' . \esc_url($url) . '" target="_blank" rel="noopener noreferrer" style="color:inherit;text-decoration:none;font:inherit;font-size:inherit;font-weight:inherit;line-height:inherit;">' . \esc_html($label) . '</a>';
}

/** Metabox registrieren (unverändert) */
\add_action('add_meta_boxes', function () {
	if (!\post_type_exists('kontakte')) return;
	\add_meta_box(
		'cmx_kommunikation_box',
		'Kommunikation',
		__NAMESPACE__ . '\\cmx_kommunikation_box_html',
		'kontakte',
		'normal',
		'default'
	);
});

/**
 * Metabox-HTML
 * NUR das **Lesen** der Daten wurde angepasst:
 * - Werte für telefon_1..3 und email_1..3 kommen vorrangig aus _cmx_telefon_1..3 / _cmx_email_1..3
 * - Labels kommen weiterhin aus dem Bündel _cmx_kommunikation (Kompatibilität)
 * - Fallback: alte Struktur in _cmx_kommunikation
 */
function cmx_kommunikation_box_html($post): void {
	// 1) Basis: vorhandenes Bündel (für Labels/Fallback)
	$bundle = \get_post_meta($post->ID, '_cmx_kommunikation', true);
	if (!\is_array($bundle)) $bundle = [];

	// 2) Startwert für $meta aus Bündel (erlaubt vorhandene Labels zu übernehmen)
	$meta = [];

	// Aus Bündel bekannte Keys in $meta spiegeln (nur Labels/Fallback)
	for ($i = 1; $i <= 3; $i++) {
		// Telefon
		if (isset($bundle['telefon'][$i]['value'])) {
			$meta["telefon_{$i}"] = (string)$bundle['telefon'][$i]['value'];
		}
		if (isset($bundle['telefon'][$i]['label'])) {
			$meta["telefon_label_{$i}"] = (string)$bundle['telefon'][$i]['label'];
		}
		// E-Mail
		if (isset($bundle['email'][$i]['value'])) {
			$meta["email_{$i}"] = (string)$bundle['email'][$i]['value'];
		}
		if (isset($bundle['email'][$i]['label'])) {
			$meta["email_label_{$i}"] = (string)$bundle['email'][$i]['label'];
		}
	}

	// 3) NEUE Einzelspalten haben Vorrang (nur Werte, keine Labels)
	for ($i = 1; $i <= 3; $i++) {
		$tel  = \get_post_meta($post->ID, "_cmx_telefon_{$i}", true);
		$mail = \get_post_meta($post->ID, "_cmx_email_{$i}", true);
		if ($tel !== '' && $tel !== null)  { $meta["telefon_{$i}"] = (string)$tel; }
		if ($mail !== '' && $mail !== null){ $meta["email_{$i}"]   = (string)$mail; }
	}

	$phone_terms = \taxonomy_exists(CMX_TAX_PHONE_LABELS) ? cmx_get_terms_normalized(CMX_TAX_PHONE_LABELS) : [];
	$mail_terms  = \taxonomy_exists(CMX_TAX_MAIL_LABELS)  ? cmx_get_terms_normalized(CMX_TAX_MAIL_LABELS)  : [];
	$phone_label = cmx_kommunikation_taxonomy_label_html(CMX_TAX_PHONE_LABELS, 'Telefon');
	$mail_label = cmx_kommunikation_taxonomy_label_html(CMX_TAX_MAIL_LABELS, 'E-Mail');

	\wp_nonce_field('cmx_kommunikation_save', 'cmx_kommunikation_nonce');

	if (!\taxonomy_exists(CMX_TAX_PHONE_LABELS) || !\taxonomy_exists(CMX_TAX_MAIL_LABELS)) {
		echo '<div class="notice notice-warning"><p><strong>Hinweis:</strong> '
		   . (!\taxonomy_exists(CMX_TAX_PHONE_LABELS) ? 'Taxonomie <code>'.\esc_html(CMX_TAX_PHONE_LABELS).'</code> fehlt. ' : '')
		   . (!\taxonomy_exists(CMX_TAX_MAIL_LABELS)  ? 'Taxonomie <code>'.\esc_html(CMX_TAX_MAIL_LABELS).'</code> fehlt.' : '')
		   . '</p></div>';
	}

	/* A) TH-Spalte kompakter, B) 3 Felder + Icons mit ausreichend Platz (unverändert) */
	echo '<style>
		#cmx_kommunikation_box .form-table th{width:120px;padding-right:10px;white-space:nowrap}

		.cmx-kommu-row{display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap}
		.cmx-kommu-group{display:flex;gap:6px;align-items:center;flex:1 1 calc(33.333% - 10px);min-width:320px}
		.cmx-kommu-group select{min-width:120px;max-width:160px}
		.cmx-kommu-group input[type=\"text\"],
		.cmx-kommu-group input[type=\"email\"]{flex:1 1 auto;min-width:0;max-width:none}

		.cmx-icon-slot{display:inline-flex;align-items:center;justify-content:center;opacity:0.6}
		.cmx-icon-slot a{text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
		.cmx-icon-slot .dashicons{font-size:18px;width:18px;height:18px;line-height:18px}
		.cmx-icon-slot.empty{opacity:0.25}

		@media (max-width:1200px){.cmx-kommu-group{flex:1 1 calc(50% - 10px)}}
		@media (max-width:700px){.cmx-kommu-group{flex:1 1 100%}}
	</style>';

	echo '<div id="cmx_kommunikation_box">';
	echo '<table class="form-table"><tbody>';

	// Telefone (1–3) – unveränderte Ausgabe
	echo '<tr><th scope="row">' . $phone_label . '</th><td><div class="cmx-kommu-row">';
	for ($i = 1; $i <= 3; $i++) {
		$val = isset($meta["telefon_$i"]) ? \esc_attr($meta["telefon_$i"]) : '';
		$ddl = cmx_label_dropdown($phone_terms, "telefon_label_$i", $meta, CMX_TAX_PHONE_LABELS);
		echo '<div class="cmx-kommu-group">'.$ddl.
		     '<input type="text" class="cmx-input cmx-input-phone" data-cmx-kind="phone" name="cmx_kommunikation[telefon_' . $i . ']" value="'.$val.'" placeholder="Telefon '.$i.'" />'.
		     '<span class="cmx-icon-slot empty" aria-hidden="true"></span>'.
		'</div>';
	}
	echo '</div></td></tr>';

	// E-Mails (1–3) – unveränderte Ausgabe
	echo '<tr><th scope="row">' . $mail_label . '</th><td><div class="cmx-kommu-row">';
	for ($i = 1; $i <= 3; $i++) {
		$val = isset($meta["email_$i"]) ? \esc_attr($meta["email_$i"]) : '';
		$ddl = cmx_label_dropdown($mail_terms, "email_label_$i", $meta, CMX_TAX_MAIL_LABELS);
		echo '<div class="cmx-kommu-group">'.$ddl.
		     '<input type="email" class="cmx-input cmx-input-email" data-cmx-kind="email" name="cmx_kommunikation[email_' . $i . ']" value="'.$val.'" placeholder="E-Mail '.$i.'" />'.
		     '<span class="cmx-icon-slot empty" aria-hidden="true"></span>'.
		'</div>';
	}
	echo '</div></td></tr>';

	echo '</tbody></table>';
	echo '</div>';

	// JS – unverändert (nur Anzeige der Icons)
	echo '<script>
	(function(){
	  function isValidEmail(v){ v=(v||"").trim(); return /^[^\\s@]+@[^\\s@]+\\.[^\\s@]{2,}$/.test(v); }
	  function normalizePhone(v){ return (v||"").replace(/[^0-9+()\\-\\.\\s]/g,""); }
	  function onlyDigits(v){ return (v||"").replace(/\\D/g,""); }
	  function clearSlot(s){ while(s.firstChild){ s.removeChild(s.firstChild);} }
	  function makeIcon(cls, href, label){ var a=document.createElement("a"); a.href=href;a.target="_blank";a.rel="noopener";a.title=label; var i=document.createElement("span"); i.className="dashicons "+cls; a.appendChild(i); return a; }

	  function refreshGroup(g){
	    var input=g.querySelector(".cmx-input"), slot=g.querySelector(".cmx-icon-slot");
	    if(!input||!slot) return;
	    clearSlot(slot);
	    var val=(input.value||"").trim(), kind=input.dataset.cmxKind;
	    if(!val){ slot.classList.add("empty"); return; }
	    slot.classList.remove("empty");

	    if(kind==="phone"){
	      var tel = normalizePhone(val);
	      if(!tel){ slot.classList.add("empty"); return; }
	      var telDigits = onlyDigits(tel);

	      slot.appendChild(document.createTextNode(" "));
	      slot.appendChild(makeIcon("dashicons-phone", "tel:"+encodeURI(tel), "Anrufen"));
	      if(telDigits){
	        slot.appendChild(makeIcon("dashicons-whatsapp", "https://api.whatsapp.com/send?phone=" + telDigits, "WhatsApp öffnen"));
	      }
	      slot.appendChild(makeIcon("dashicons-testimonial", "sms:"+encodeURI(tel), "SMS senden"));
	      slot.appendChild(document.createTextNode(" "));
	    } else if(kind==="email"){
	      if(!isValidEmail(val)){ slot.classList.add("empty"); return; }
	      slot.appendChild(document.createTextNode(" "));
	      slot.appendChild(makeIcon("dashicons-email-alt", "mailto:"+encodeURIComponent(val), "E-Mail schreiben"));
	      slot.appendChild(document.createTextNode(" "));
	    }
	  }

	  document.querySelectorAll(".cmx-kommu-group").forEach(function(g){
	    var i=g.querySelector(".cmx-input"); if(!i) return;
	    ["input","change","blur"].forEach(function(evt){ i.addEventListener(evt,function(){ refreshGroup(g); }); });
	    refreshGroup(g);
	  });
	})();
	</script>';
}

/**
 * SPEICHERN
 * NUR angepasst:
 * - Werte werden in EINZELNEN Post-Metas gesichert:
 *   _cmx_telefon_1..3 und _cmx_email_1..3
 * - Zusätzlich bleibt das Bündel _cmx_kommunikation zur Label-/Kompatibilitätsnutzung erhalten
 * - Markup/JS/Struktur sonst unverändert
 */
\add_action('save_post', function ($post_id) {
	if (!isset($_POST['cmx_kommunikation_nonce']) || !\wp_verify_nonce($_POST['cmx_kommunikation_nonce'], 'cmx_kommunikation_save')) return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\wp_is_post_autosave($post_id) || \wp_is_post_revision($post_id)) return;
	if (!\current_user_can('edit_post', $post_id)) return;
	if (!isset($_POST['cmx_kommunikation']) || !\is_array($_POST['cmx_kommunikation'])) return;

	if (isset($_POST['_cmx_rechnung_land'])) {
			\update_post_meta($post_id, '_cmx_rechnung_land', \sanitize_text_field($_POST['_cmx_rechnung_land']));
	}
	if (isset($_POST['_cmx_liefer_land'])) {
			\update_post_meta($post_id, '_cmx_liefer_land', \sanitize_text_field($_POST['_cmx_liefer_land']));
	}


	$in = $_POST['cmx_kommunikation'];

	$sanitize_txt  = static fn($v) => \sanitize_text_field($v ?? '');
	$sanitize_slug = static fn($v) => \sanitize_title($v ?? '');

	// Vorhandenes Bündel laden (damit Labels nicht verloren gehen)
	$bundle = \get_post_meta($post_id, '_cmx_kommunikation', true);
	if (!\is_array($bundle)) $bundle = ['telefon'=>[], 'email'=>[]];

	// Telefone 1–3: Einzelspalten + Bündel aktualisieren
	for ($i = 1; $i <= 3; $i++) {
		$val        = $sanitize_txt($in["telefon_{$i}"] ?? '');
		$label_slug = $sanitize_slug($in["telefon_label_{$i}"] ?? '');

		if ($label_slug && !cmx_term_slug_exists(CMX_TAX_PHONE_LABELS, $label_slug)) {
			$label_slug = '';
		}

		// Einzelspalte (konkretes Feld)
		\update_post_meta($post_id, "_cmx_telefon_{$i}", $val);

		// Bündel (Kompatibilität/Labels)
		$bundle['telefon'][$i] = [
			'label' => $label_slug,
			'value' => $val,
		];
	}

	// E-Mails 1–3: Einzelspalten + Bündel aktualisieren
	for ($i = 1; $i <= 3; $i++) {
		$val        = $sanitize_txt($in["email_{$i}"] ?? '');
		$label_slug = $sanitize_slug($in["email_label_{$i}"] ?? '');

		if ($label_slug && !cmx_term_slug_exists(CMX_TAX_MAIL_LABELS, $label_slug)) {
			$label_slug = '';
		}

		// Einzelspalte (konkretes Feld)
		\update_post_meta($post_id, "_cmx_email_{$i}", $val);

		// Bündel (Kompatibilität/Labels)
		$bundle['email'][$i] = [
			'label' => $label_slug,
			'value' => $val,
			'valid' => (bool)\is_email($val) ? '1' : '0',
		];
	}

	// Bündel speichern (nur für Labels/Kompatibilität)
	\update_post_meta($post_id, '_cmx_kommunikation', $bundle);
});
