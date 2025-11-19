<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/** Debug-Flag (bei Bedarf auf false) */
if (!defined(__NAMESPACE__.'\\CMX_VCARD_DEBUG')) {
	define(__NAMESPACE__.'\\CMX_VCARD_DEBUG', true);
}

/**
 * UI: Link "vcard" in der Kontakte-Liste – öffnet direkt Dateidialog (.vcf) und submitted
 */
add_filter('views_edit-kontakte', __NAMESPACE__ . '\\cmx_kontakte_add_vcard_view_link');
function cmx_kontakte_add_vcard_view_link(array $views): array {
	$views['cmx_vcard'] = '<a href="#" class="cmx-vcard-link">vcard</a>';
	return $views;
}

add_action('admin_footer-edit.php', __NAMESPACE__ . '\\cmx_kontakte_vcard_uploader_footer');
function cmx_kontakte_vcard_uploader_footer(): void {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || $screen->post_type !== 'kontakte') return;

	$action_url  = admin_url('admin-post.php');
	$nonce_field = wp_create_nonce('cmx_kontakte_vcard_import'); ?>
	<form id="cmx-vcard-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url($action_url); ?>" style="display:none">
		<input type="hidden" name="action" value="cmx_kontakte_vcard_import">
		<input type="hidden" name="cmx_kontakte_vcard_nonce" value="<?php echo esc_attr($nonce_field); ?>">
		<input type="file" id="cmx_vcf_file" name="cmx_vcf_file" accept=".vcf,text/vcard,text/x-vcard">
	</form>
	<script>
	(function(){
		const link = document.querySelector('a.cmx-vcard-link');
		if (!link) return;
		link.addEventListener('click', function(e){
			e.preventDefault();
			const input = document.getElementById('cmx_vcf_file');
			if (!input) return;
			input.value = '';
			input.click();
		});
		const input = document.getElementById('cmx_vcf_file');
		if (!input) return;
		input.addEventListener('change', function(){
			if (input.files && input.files.length > 0) {
				document.getElementById('cmx-vcard-form').submit();
			}
		});
	})();
	</script><?php
}

/**
 * Import-Handler
 */
add_action('admin_post_cmx_kontakte_vcard_import', __NAMESPACE__ . '\\cmx_kontakte_vcard_handle');
function cmx_kontakte_vcard_handle(): void {
	if (!current_user_can('manage_options')) wp_die(__('Keine Berechtigung.'));
	if (empty($_POST['cmx_kontakte_vcard_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cmx_kontakte_vcard_nonce'])), 'cmx_kontakte_vcard_import')) {
		wp_die(__('Sicherheitsprüfung fehlgeschlagen.'));
	}
	if (empty($_FILES['cmx_vcf_file']['tmp_name']) || !is_uploaded_file($_FILES['cmx_vcf_file']['tmp_name'])) {
		cmx_kontakte_vcard_redirect('Keine Datei empfangen.');
		return;
	}

	$d = cmx_parse_single_vcard($_FILES['cmx_vcf_file']['tmp_name']);
	if (is_wp_error($d)) {
		cmx_kontakte_vcard_redirect($d->get_error_message());
		return;
	}
	if (CMX_VCARD_DEBUG) error_log('[VCARD parsed] '. print_r($d, true));

	// --- Titel-Logik: nur ORG als Titel, sonst FN
	$company = trim($d['company'] ?? '');
	$first   = trim($d['first_name'] ?? '');
	$last    = trim($d['last_name'] ?? '');

	if ($company !== '') {
		$post_title = $company;
	} elseif (($first.$last) !== '') {
		$post_title = trim($first . ' ' . $last);
	} else {
		$post_title = 'Kontakt ' . current_time('Y-m-d H:i:s');
	}

	// --- NOTE in interne Notizen übernehmen
	if (!empty($d['notes'])) {
		update_post_meta($post_id, '_cmx_interne_notizen', wp_kses_post($d['notes']));
	}

	$post_id = wp_insert_post([
		'post_type'   => 'kontakte',
		'post_status' => 'publish',
		'post_title'  => $post_title,
	], true);
	if (is_wp_error($post_id) || !$post_id) {
		cmx_kontakte_vcard_redirect('Kontakt konnte nicht angelegt werden.');
		return;
	}

	/** ---------------- Stammdaten (DEIN CPT) ---------------- */
	// Vorname/Nachname (N/FN)
	if (!empty($d['first_name'])) update_post_meta($post_id, CMX_KONTAKTE_META_VORNAME, sanitize_text_field($d['first_name']));
	if (!empty($d['last_name']))  update_post_meta($post_id, CMX_KONTAKTE_META_NACHNAME, sanitize_text_field($d['last_name']));

	// URL
	if (!empty($d['website']))     update_post_meta($post_id, CMX_KONTAKTE_META_URL, esc_url_raw($d['website']));

	// BDAY → YYYY-MM-DD
	if (!empty($d['bday']))        update_post_meta($post_id, CMX_KONTAKTE_META_DATUM, $d['bday']);

	// OPTIONAL (heuristisch): PRIVAT = true, wenn keine ORG vorhanden
	if (empty($d['company']))      update_post_meta($post_id, CMX_KONTAKTE_META_PRIVAT, 1);

	/** ---------------- Kommunikation ---------------- */
	// Term-Slugs ermitteln, die es bei Dir gibt
	$email_label_map  = cmx_find_label_slugs(['privat','geschaeft','home','work','other'], CMX_TAX_MAIL_LABELS);
	$phone_label_map  = cmx_find_label_slugs(['privat','geschaeft','mobile','home','work','other'], CMX_TAX_PHONE_LABELS);

	// Emails → _cmx_email_1..3 + Bündel _cmx_kommunikation[email]
	$bundle = get_post_meta($post_id, '_cmx_kommunikation', true);
	if (!is_array($bundle)) $bundle = ['telefon'=>[], 'email'=>[]];

	$emails = array_slice($d['emails'] ?? [], 0, 3);
	foreach ($emails as $i => $row) {
		$n = $i + 1;
		$val  = sanitize_email($row['value'] ?? '');
		$type = strtolower($row['type'] ?? 'other');

		// Einzelspalte
		update_post_meta($post_id, "_cmx_email_{$n}", $val);

		// Label-Slug (Taxonomien)
		$label = cmx_pick_slug_for_type($type, $email_label_map); // 'privat'/'geschaeft' oder 'home'/'work'

		// Bündel
		$bundle['email'][$n] = [
			'label' => $label,
			'value' => $val,
			'valid' => (bool)is_email($val) ? '1' : '0',
		];
	}
	// Telefone → _cmx_telefon_1..3 + Bündel _cmx_kommunikation[telefon]
	$tels = array_slice($d['tels'] ?? [], 0, 3);
	foreach ($tels as $i => $row) {
		$n = $i + 1;
		$val  = sanitize_text_field($row['value'] ?? '');
		$type = strtolower($row['type'] ?? 'other');

		update_post_meta($post_id, "_cmx_telefon_{$n}", $val);

		$label = cmx_pick_slug_for_type($type, $phone_label_map); // 'privat'/'geschaeft'/'mobile' …

		$bundle['telefon'][$n] = [
			'label' => $label,
			'value' => $val,
		];
	}
	update_post_meta($post_id, '_cmx_kommunikation', $bundle);

	/** ---------------- Adressen (1 = Rechnung, 2 = Liefer) ---------------- */
	$addr1 = $d['addresses'][0] ?? null;
	$addr2 = $d['addresses'][1] ?? null;

	if ($addr1) {
		update_post_meta($post_id, CMX_RECHNUNG_META_STRASSE, sanitize_text_field($addr1['street']  ?? ''));
		update_post_meta($post_id, CMX_RECHNUNG_META_ZUSATZ,  ''); // vCard hat ggf. Extended → hier leer lassen oder ergänzen
		update_post_meta($post_id, CMX_RECHNUNG_META_PLZ,     sanitize_text_field($addr1['zip']     ?? ''));
		update_post_meta($post_id, CMX_RECHNUNG_META_ORT,     sanitize_text_field($addr1['city']    ?? ''));
		update_post_meta($post_id, CMX_RECHNUNG_META_LAND,    cmx_normalize_country_slug($addr1['country'] ?? ''));
	}
	if ($addr2) {
		update_post_meta($post_id, CMX_LIEFER_META_STRASSE,   sanitize_text_field($addr2['street']  ?? ''));
		update_post_meta($post_id, CMX_LIEFER_META_ZUSATZ,    '');
		update_post_meta($post_id, CMX_LIEFER_META_PLZ,       sanitize_text_field($addr2['zip']     ?? ''));
		update_post_meta($post_id, CMX_LIEFER_META_ORT,       sanitize_text_field($addr2['city']    ?? ''));
		update_post_meta($post_id, CMX_LIEFER_META_LAND,      cmx_normalize_country_slug($addr2['country'] ?? ''));
	}

	if (CMX_VCARD_DEBUG) {
		error_log('[VCARD saved] post_id='.$post_id);
		foreach ([
			CMX_KONTAKTE_META_VORNAME, CMX_KONTAKTE_META_NACHNAME, CMX_KONTAKTE_META_URL, CMX_KONTAKTE_META_DATUM,
			'_cmx_email_1','_cmx_email_2','_cmx_email_3',
			'_cmx_telefon_1','_cmx_telefon_2','_cmx_telefon_3',
			CMX_RECHNUNG_META_STRASSE, CMX_RECHNUNG_META_PLZ, CMX_RECHNUNG_META_ORT, CMX_RECHNUNG_META_LAND,
			CMX_LIEFER_META_STRASSE, CMX_LIEFER_META_PLZ, CMX_LIEFER_META_ORT, CMX_LIEFER_META_LAND,
			'_cmx_kommunikation',
		] as $k) {
			error_log("  meta[$k]=". print_r(get_post_meta($post_id, $k, true), true));
		}
	}

	// Redirect
	cmx_kontakte_vcard_redirect('Import erfolgreich – Kontakt angelegt.', intval($post_id));
}

/** ---------- Helpers (Label-Slugs, Länder, Redirect, Parser) ---------- */

/** ermittelt vorhandene Slugs aus Taxonomie in gegebener Präferenzreihenfolge */
function cmx_find_label_slugs(array $candidates, string $taxonomy): array {
	$out = [];
	foreach ($candidates as $slug) {
		$slug = sanitize_title($slug);
		if (cmx_term_slug_exists($taxonomy, $slug)) $out[$slug] = $slug;
	}
	return $out; // z.B. ['privat'=>'privat','geschaeft'=>'geschaeft','mobile'=>'mobile']
}

/** wählt für einen TYPE den besten vorhandenen Slug */
function cmx_pick_slug_for_type(string $type, array $available): string {
	$type = strtolower($type);
	// Mapping: bevorzuge deutsch, fallback englisch
	$pref = [];
	if ($type === 'home')     $pref = ['privat','home'];
	elseif ($type === 'work') $pref = ['geschaeft','work'];
	elseif ($type === 'cell' || $type === 'mobile') $pref = ['mobile','handy'];
	else $pref = ['other','sonstiges'];

	foreach ($pref as $want) {
		$want = sanitize_title($want);
		if (isset($available[$want])) return $available[$want];
	}
	// gar kein passender Term → leer lassen
	return '';
}

/** Land aus ADR → slug (bevorzugt 2-letter) */
function cmx_normalize_country_slug(string $country): string {
	$country = trim($country);
	if ($country === '') return '';
	// 2-letter code
	if (preg_match('/^[A-Za-z]{2}$/', $country)) return strtolower($country);
	// Schweiz-Varianten
	$map = [
		'switzerland' => 'ch', 'schweiz' => 'ch', 'suisse' => 'ch', 'svizzera' => 'ch',
		'germany' => 'de', 'deutschland' => 'de',
		'austria' => 'at', 'österreich' => 'at', 'oesterreich' => 'at',
	];
	$key = strtolower(remove_accents($country));
	return $map[$key] ?? strtolower($country);
}

/** Redirect in Edit-Ansicht oder zurück zur Liste */
function cmx_kontakte_vcard_redirect(string $msg, ?int $post_id = null): void {
	if ($post_id && $post_id > 0) {
		$edit_url = get_edit_post_link($post_id, '');
		if ($edit_url) {
			$edit_url = add_query_arg(['cmx_notice' => rawurlencode($msg)], $edit_url);
			wp_safe_redirect($edit_url);
			exit;
		}
	}
	$url = add_query_arg(['cmx_notice' => rawurlencode($msg)], admin_url('edit.php?post_type=kontakte'));
	wp_safe_redirect($url);
	exit;
}

/** vCard-Parser (ein Kontakt) – N, FN, ORG, EMAIL, TEL, ADR, URL, NOTE, BDAY */
function cmx_parse_single_vcard(string $filepath) {
	$raw = file_get_contents($filepath);
	if ($raw === false || $raw === '') return new \WP_Error('vcf_empty', 'vCard ist leer oder nicht lesbar.');

	// normalize & unfold
	$raw = str_replace(["\r\n", "\r"], "\n", $raw);
	$lines = explode("\n", $raw);
	$unfolded = [];
	foreach ($lines as $line) {
		if ($line === '') continue;
		if (!empty($unfolded) && (isset($line[0]) && ($line[0] === ' ' || $line[0] === "\t"))) {
			$unfolded[count($unfolded) - 1] .= substr($line, 1);
		} else {
			$unfolded[] = $line;
		}
	}
	$begin = array_keys(array_filter($unfolded, fn($l) => strtoupper(trim($l)) === 'BEGIN:VCARD'));
	$end   = array_keys(array_filter($unfolded, fn($l) => strtoupper(trim($l)) === 'END:VCARD'));
	if (count($begin) !== 1 || count($end) !== 1 || ($end[0] <= $begin[0])) {
		return new \WP_Error('vcf_count', 'Die vCard-Datei muss genau einen Kontakt enthalten.');
	}
	$block = array_slice($unfolded, $begin[0] + 1, $end[0] - $begin[0] - 1);

	$out = [
		'company'    => '',
		'first_name' => '',
		'last_name'  => '',
		'website'    => '',
		'notes'      => '',
		'bday'       => '',
		'emails'     => [], // [['type'=>'home|work|other', 'value'=>'...']]
		'tels'       => [], // [['type'=>'home|work|mobile|other', 'value'=>'...']]
		'addresses'  => [], // [['street','zip','city','country']]
	];

	foreach ($block as $line) {
		$pos = strpos($line, ':');
		if ($pos === false) continue;
		$left  = substr($line, 0, $pos);
		$value = substr($line, $pos + 1);

		// "item1.EMAIL" → "EMAIL"
		$parts = explode(';', $left);
		$prop  = strtoupper(array_shift($parts));
		$prop  = preg_replace('/^ITEM\d+\./i', '', $prop);

		$params = ['TYPE' => []];
		foreach ($parts as $p) {
			if (strpos($p, '=') === false) {
				$params['TYPE'][] = strtoupper(trim($p));
				continue;
			}
			[$k, $v] = array_map('trim', explode('=', $p, 2));
			$kU = strtoupper($k);
			if ($kU === 'TYPE') {
				foreach (explode(',', $v) as $vv) {
					$vv = strtoupper(trim($vv));
					if ($vv !== '') $params['TYPE'][] = $vv;
				}
			} else {
				$params[$kU] = $v;
			}
		}

		$value = cmx_vcard_decode_value($value, $params);

		switch ($prop) {
			case 'N': {
				$bits = explode(';', $value);
				if ($out['last_name']  === '') $out['last_name']  = sanitize_text_field($bits[0] ?? '');
				if ($out['first_name'] === '') $out['first_name'] = sanitize_text_field($bits[1] ?? '');
				break;
			}
			case 'FN': {
				if ($out['first_name'] === '' && $out['last_name'] === '') {
					$fn = trim($value);
					if ($fn !== '') {
						$names = preg_split('/\s+/', $fn);
						$out['first_name'] = sanitize_text_field(array_shift($names) ?? '');
						$out['last_name']  = sanitize_text_field(implode(' ', $names));
					}
				}
				break;
			}
			case 'ORG':
				$out['company'] = sanitize_text_field(str_replace(';', ' ', $value));
				break;

			case 'BDAY':
				$out['bday'] = cmx_normalize_bday($value);
				break;

			case 'EMAIL': {
				$email = sanitize_email($value);
				if (!$email) break;
				$type = 'other';
				$t = strtoupper(implode(',', $params['TYPE']));
				if (str_contains($t, 'HOME')) $type = 'home';
				elseif (str_contains($t, 'WORK')) $type = 'work';
				$out['emails'][] = ['type' => $type, 'value' => $email];
				break;
			}

			case 'TEL': {
				$tel = sanitize_text_field($value);
				if (!$tel) break;
				$type = 'other';
				$t = strtoupper(implode(',', $params['TYPE']));
				if (str_contains($t, 'HOME')) $type = 'home';
				elseif (str_contains($t, 'WORK')) $type = 'work';
				elseif (str_contains($t, 'CELL') || str_contains($t, 'MOBILE')) $type = 'mobile';
				$out['tels'][] = ['type' => $type, 'value' => $tel];
				break;
			}

			case 'URL':
				if ($out['website'] === '') $out['website'] = esc_url_raw($value);
				break;

			case 'NOTE':
				if ($out['notes'] === '') $out['notes'] = wp_kses_post($value);
				break;

			case 'ADR': {
				// ADR: PO Box;Extended;Street;City;Region;PostalCode;Country
				$adr = explode(';', $value);
				$street  = trim($adr[2] ?? '');
				$city    = trim($adr[3] ?? '');
				$zip     = trim($adr[5] ?? '');
				$country = trim($adr[6] ?? '');
				if ($street !== '' || $zip !== '' || $city !== '' || $country !== '') {
					if (count($out['addresses']) < 2) {
						$out['addresses'][] = [
							'street'  => sanitize_text_field($street),
							'zip'     => sanitize_text_field($zip),
							'city'    => sanitize_text_field($city),
							'country' => sanitize_text_field($country),
						];
					}
				}
				break;
			}
		}
	}

	// Begrenzen
	if (count($out['emails']) > 3)    $out['emails']    = array_slice($out['emails'], 0, 3);
	if (count($out['addresses']) > 2) $out['addresses'] = array_slice($out['addresses'], 0, 2);

	return $out;
}

/** Value-Decoding (Quoted-Printable/Charset) + vCard-Unescapes */
function cmx_vcard_decode_value(string $value, array $params): string {
	$enc = strtoupper($params['ENCODING'] ?? '');
	$cs  = $params['CHARSET'] ?? '';

	if ($enc === 'QUOTED-PRINTABLE') {
		$value = preg_replace("/=\n/", '', $value);
		$value = quoted_printable_decode($value);
	}
	if ($cs && strtoupper($cs) !== 'UTF-8') {
		if (function_exists('mb_convert_encoding')) {
			$value = @mb_convert_encoding($value, 'UTF-8', $cs);
		} elseif (function_exists('iconv')) {
			$v = @iconv($cs, 'UTF-8//IGNORE', $value);
			if ($v !== false) $value = $v;
		}
	}
	// vCard Escapes
	$value = str_replace(['\\n', '\\N', '\\,', '\\;', '\\\\'], ["\n", "\n", ',', ';', '\\'], $value);

	return trim($value);
}

/** BDAY → YYYY-MM-DD */
function cmx_normalize_bday(string $raw): string {
	$raw = trim($raw);
	if ($raw === '') return '';
	if (preg_match('/^\d{8}$/', $raw)) {
		return substr($raw,0,4) . '-' . substr($raw,4,2) . '-' . substr($raw,6,2);
	}
	if (preg_match('/^(\d{4})[-\.\/](\d{2})[-\.\/](\d{2})$/', $raw, $m)) {
		return $m[1] . '-' . $m[2] . '-' . $m[3];
	}
	return '';
}
