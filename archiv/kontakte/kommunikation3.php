<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit;

/** Verbindliche Taxonomien */
const CMX_TAX_PHONE_LABELS = 'kontakte_telefone';
const CMX_TAX_MAIL_LABELS  = 'kontakte_emails';

/** Hilfsfunktionen */
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

/** Metabox registrieren */
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

/** Metabox-HTML */
function cmx_kommunikation_box_html($post): void {
	$meta  = \get_post_meta($post->ID, '_cmx_kommunikation', true);
	if (!\is_array($meta)) $meta = [];

	$phone_terms = \taxonomy_exists(CMX_TAX_PHONE_LABELS) ? cmx_get_terms_normalized(CMX_TAX_PHONE_LABELS) : [];
	$mail_terms  = \taxonomy_exists(CMX_TAX_MAIL_LABELS)  ? cmx_get_terms_normalized(CMX_TAX_MAIL_LABELS)  : [];

	\wp_nonce_field('cmx_kommunikation_save', 'cmx_kommunikation_nonce');

	if (!\taxonomy_exists(CMX_TAX_PHONE_LABELS) || !\taxonomy_exists(CMX_TAX_MAIL_LABELS)) {
		echo '<div class="notice notice-warning"><p><strong>Hinweis:</strong> '
		   . (!\taxonomy_exists(CMX_TAX_PHONE_LABELS) ? 'Taxonomie <code>'.\esc_html(CMX_TAX_PHONE_LABELS).'</code> fehlt. ' : '')
		   . (!\taxonomy_exists(CMX_TAX_MAIL_LABELS)  ? 'Taxonomie <code>'.\esc_html(CMX_TAX_MAIL_LABELS).'</code> fehlt.' : '')
		   . '</p></div>';
	}

	echo '<style>
		.cmx-kommu-row{display:flex;gap:12px;align-items:flex-start}
		.cmx-kommu-group{display:flex;gap:6px;align-items:center;flex:0 0 auto}
		.cmx-kommu-group select{min-width:130px;max-width:170px}
		.cmx-kommu-group input[type="text"],.cmx-kommu-group input[type="email"]{width:190px;max-width:210px}
		.cmx-icon-slot{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;margin-left:4px;margin-right:20px;opacity:0.5}
		.cmx-icon-slot a{text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
		.cmx-icon-slot .dashicons{font-size:18px;width:18px;height:18px;line-height:18px}
		.cmx-icon-slot.empty{opacity:0.2}
		@media (max-width:1200px){.cmx-kommu-row{flex-wrap:wrap}.cmx-kommu-group{flex:1 1 260px}}
	</style>';

	echo '<table class="form-table"><tbody>';

	// Telefone (1–3)
	echo '<tr><th scope="row">direkt anrufen</th><td><div class="cmx-kommu-row">';
	for ($i = 1; $i <= 3; $i++) {
		$val = isset($meta["telefon_$i"]) ? \esc_attr($meta["telefon_$i"]) : '';
		$ddl = cmx_label_dropdown($phone_terms, "telefon_label_$i", $meta, CMX_TAX_PHONE_LABELS);
		echo '<div class="cmx-kommu-group">'.$ddl.
		     '<input type="text" class="cmx-input cmx-input-phone" data-cmx-kind="phone" name="cmx_kommunikation[telefon_' . $i . ']" value="'.$val.'" placeholder="Telefon '.$i.'" />'.
		     '<span class="cmx-icon-slot empty" aria-hidden="true"></span>'.
		'</div>';
	}
	echo '</div></td></tr>';

	// E-Mails (1–3)
	echo '<tr><th scope="row">Mail schreiben</th><td><div class="cmx-kommu-row">';
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

	// JS: Icon nur anzeigen, wenn Wert existiert
	echo '<script>
	(function(){
	  function isValidEmail(v){ v=(v||"").trim(); return /^[^\\s@]+@[^\\s@]+\\.[^\\s@]{2,}$/.test(v); }
	  function normalizePhone(v){ return (v||"").replace(/[^0-9+()\\-\\.\\s]/g,""); }
	  function clearSlot(s){ while(s.firstChild){ s.removeChild(s.firstChild);} }
	  function makeIcon(cls, href, label){ var a=document.createElement("a"); a.href=href;a.target="_blank";a.rel="noopener";a.title=label; var i=document.createElement("span"); i.className="dashicons "+cls; a.appendChild(i); return a; }
	  function refreshGroup(g){
	    var input=g.querySelector(".cmx-input"), slot=g.querySelector(".cmx-icon-slot");
	    if(!input||!slot)return;
	    clearSlot(slot);
	    var val=(input.value||"").trim(), kind=input.dataset.cmxKind;
	    if(!val){ slot.classList.add("empty"); return; }
	    slot.classList.remove("empty");
	    if(kind==="phone"){
	      var tel=normalizePhone(val); if(!tel)return;
	      slot.appendChild(makeIcon("dashicons-phone","tel:"+encodeURI(tel),"Anrufen"));
				slot.appendChild(makeIcon("dashicons-whatsapp","https://api.whatsapp.com/send?phone=" + tel,"WhatsApp öffnen")
	      slot.appendChild(makeIcon("dashicons-phone","sms:"+encodeURI(tel),"Anrufen"));
	);


				} else if(kind==="email"){
	      if(!isValidEmail(val))return;
	      slot.appendChild(makeIcon("dashicons-email-alt","mailto:"+encodeURIComponent(val),"E-Mail schreiben"));
	    }
	  }
	  document.querySelectorAll(".cmx-kommu-group").forEach(function(g){
	    var i=g.querySelector(".cmx-input"); if(!i)return;
	    ["input","change","blur"].forEach(evt=>i.addEventListener(evt,()=>refreshGroup(g)));
	    refreshGroup(g);
	  });
	})();
	</script>';
}

/** Speichern mit Validierung der Label-Slugs gegen die fixen Taxonomien */
\add_action('save_post', function ($post_id) {
	if (!isset($_POST['cmx_kommunikation_nonce']) || !\wp_verify_nonce($_POST['cmx_kommunikation_nonce'], 'cmx_kommunikation_save')) return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\wp_is_post_autosave($post_id) || \wp_is_post_revision($post_id)) return;
	if (!\current_user_can('edit_post', $post_id)) return;
	if (!isset($_POST['cmx_kommunikation']) || !\is_array($_POST['cmx_kommunikation'])) return;

	$incoming = $_POST['cmx_kommunikation'];
	$stored   = \get_post_meta($post_id, '_cmx_kommunikation', true);
	if (!\is_array($stored)) $stored = [];

	$allowed_prefixes = ['telefon_', 'email_', 'telefon_label_', 'email_label_'];
	foreach ($incoming as $k => $v) {
		$k = \sanitize_key($k);
		$allowed = false;
		foreach ($allowed_prefixes as $pref) if (strpos($k, $pref) === 0) { $allowed = true; break; }
		if (!$allowed) continue;

		if (strpos($k, 'telefon_') === 0 && strpos($k, 'telefon_label_') !== 0) {
			$stored[$k] = \sanitize_text_field($v);
		} elseif (strpos($k, 'email_') === 0 && strpos($k, 'email_label_') !== 0) {
			$val = \sanitize_text_field($v);
			$stored[$k] = $val;
			$stored[$k.'_valid'] = (bool)\is_email($val) ? '1':'0';
		} elseif (strpos($k, 'telefon_label_') === 0) {
			$slug = \sanitize_title($v);
			$stored[$k] = cmx_term_slug_exists(CMX_TAX_PHONE_LABELS, $slug) ? $slug : '';
		} elseif (strpos($k, 'email_label_') === 0) {
			$slug = \sanitize_title($v);
			$stored[$k] = cmx_term_slug_exists(CMX_TAX_MAIL_LABELS,  $slug) ? $slug : '';
		}
	}
	\update_post_meta($post_id, '_cmx_kommunikation', $stored);
});
