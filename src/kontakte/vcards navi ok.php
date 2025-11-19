<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

/**
 * A) "vcard"-Link in der Admin-Liste (Ansicht "Kontakte")
 *    – Kein Menüpunkt, nur Link neben "Alle | exportieren | importieren"
 *    – Klick öffnet direkt den lokalen Dateidialog (.vcf) und submitet automatisch
 */
add_filter('views_edit-kontakte', __NAMESPACE__ . '\\cmx_kontakte_add_vcard_view_link');
function cmx_kontakte_add_vcard_view_link(array $views): array {
	$views['cmx_vcard'] = '<a href="#" class="cmx-vcard-link">vcard</a>';
	return $views;
}

/**
 * Verstecktes Formular + JS nur auf der Kontakte-Liste einbinden
 */
add_action('admin_footer-edit.php', __NAMESPACE__ . '\\cmx_kontakte_vcard_uploader_footer');
function cmx_kontakte_vcard_uploader_footer(): void {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || $screen->post_type !== 'kontakte') return;

	$action_url = admin_url('admin-post.php');
	$nonce_field = wp_create_nonce('cmx_kontakte_vcard_import');
	?>
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
			input.value = ''; // reset evtl. vorige Auswahl
			input.click();
		});
		const input = document.getElementById('cmx_vcf_file');
		if (input) {
			input.addEventListener('change', function(){
				if (input.files && input.files.length > 0) {
					document.getElementById('cmx-vcard-form').submit();
				}
			});
		}
	})();
	</script>
	<?php
}

/**
 * B) Upload-Handler: VCF → Kontakt anlegen → Redirect in Edit
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

	$parsed = cmx_parse_single_vcard($_FILES['cmx_vcf_file']['tmp_name']);
	if (is_wp_error($parsed)) {
		cmx_kontakte_vcard_redirect($parsed->get_error_message());
		return;
	}
	$d = $parsed;

	// Titel: "Firma – Vorname Nachname" (Fallback: Zeitstempel)
	$company = $d['company'] ?? '';
	$first   = $d['first_name'] ?? '';
	$last    = $d['last_name'] ?? '';
	$post_title = trim(($company ? $company . ' – ' : '') . trim($first . ' ' . $last));
	if ($post_title === '') $post_title = 'Kontakt ' . current_time('Y-m-d H:i:s');

	$post_id = wp_insert_post([
		'post_type'   => 'kontakte',
		'post_status' => 'publish',
		'post_title'  => $post_title,
	], true);

	if (is_wp_error($post_id) || !$post_id) {
		cmx_kontakte_vcard_redirect('Kontakt konnte nicht angelegt werden.');
		return;
	}

	// Standardfelder
	$meta_map = [
		'company'    => 'cmx_company',     // D: ORG
		'first_name' => 'cmx_first_name',  // E: FN/N → Vorname
		'last_name'  => 'cmx_last_name',   // E: FN/N → Nachname
		'website'    => 'cmx_website',     // I: URL
		'notes'      => 'cmx_notes',
	];
	foreach ($meta_map as $k => $meta_key) {
		if (!empty($d[$k])) update_post_meta($post_id, $meta_key, wp_kses_post($d[$k]));
	}

	// C) Geburtstag (BDAY → cmx_datum) als YYYY-MM-DD speichern
	if (!empty($d['bday'])) {
		update_post_meta($post_id, 'cmx_datum', $d['bday']); // bereits normalisiert
	}

	// F) E-Mails 1–3 + Typ-Mapping (HOME=Privat, WORK=Geschäft)
	if (!empty($d['emails'])) {
		$flat = [];
		$home = '';
		$work = '';
		foreach ($d['emails'] as $row) {
			$val  = sanitize_email($row['value'] ?? '');
			$type = strtolower($row['type'] ?? 'other');
			if (!$val) continue;
			$flat[] = ['type' => $type, 'value' => $val];
			if ($type === 'home' && !$home) $home = $val;
			if ($type === 'work' && !$work) $work = $val;
			if (count($flat) >= 3) break;
		}
		if ($flat) {
			update_post_meta($post_id, 'cmx_emails', $flat);
			update_post_meta($post_id, 'cmx_email', $flat[0]['value']); // Kompatibilität
			if ($home) update_post_meta($post_id, 'cmx_email_private', $home);
			if ($work) update_post_meta($post_id, 'cmx_email_business', $work);
		}
	}

	// G) Telefon/Handy mit Typen (HOME/WORK/MOBILE)
	//    Zusätzlich cmx_phone / cmx_mobile zur Kompatibilität
	if (!empty($d['tels'])) {
		$flatTel = [];
		$homeTel = '';
		$workTel = '';
		$mobile  = '';
		foreach ($d['tels'] as $row) {
			$val  = sanitize_text_field($row['value'] ?? '');
			$type = strtolower($row['type'] ?? 'other');
			if (!$val) continue;
			$flatTel[] = ['type' => $type, 'value' => $val];
			if ($type === 'home' && !$homeTel) $homeTel = $val;
			if ($type === 'work' && !$workTel) $workTel = $val;
			if (($type === 'cell' || $type === 'mobile') && !$mobile) $mobile = $val;
		}
		if ($flatTel) {
			update_post_meta($post_id, 'cmx_phones', $flatTel);
			if ($homeTel) update_post_meta($post_id, 'cmx_phone_private', $homeTel);
			if ($workTel) update_post_meta($post_id, 'cmx_phone_business', $workTel);
			if ($mobile)  update_post_meta($post_id, 'cmx_mobile', $mobile);
			// Legacy-Ersttelefon:
			update_post_meta($post_id, 'cmx_phone', $homeTel ?: ($workTel ?: ($flatTel[0]['value'] ?? '')));
		}
	}

	// H) Adressen: erste → Rechnung, zweite → Lieferung
	if (!empty($d['addresses'])) {
		$addr1 = $d['addresses'][0] ?? null;
		$addr2 = $d['addresses'][1] ?? null;

		if ($addr1) {
			update_post_meta($post_id, 'cmx_address_billing', [
				'street'  => sanitize_text_field($addr1['street']  ?? ''),
				'zip'     => sanitize_text_field($addr1['zip']     ?? ''),
				'city'    => sanitize_text_field($addr1['city']    ?? ''),
				'country' => sanitize_text_field($addr1['country'] ?? ''),
			]);
		}
		if ($addr2) {
			update_post_meta($post_id, 'cmx_address_shipping', [
				'street'  => sanitize_text_field($addr2['street']  ?? ''),
				'zip'     => sanitize_text_field($addr2['zip']     ?? ''),
				'city'    => sanitize_text_field($addr2['city']    ?? ''),
				'country' => sanitize_text_field($addr2['country'] ?? ''),
			]);
		}
		// Optional Altstruktur:
		$compact = array_values(array_slice($d['addresses'], 0, 2));
		if ($compact) update_post_meta($post_id, 'cmx_addresses', $compact);
	}

	// Redirect in den Edit-Modus
	cmx_kontakte_vcard_redirect('Import erfolgreich – Kontakt angelegt.', intval($post_id));
}

/**
 * C-I) vCard Parser – EIN Kontakt
 *     Liest: N, FN, ORG, EMAIL, TEL, ADR, URL, NOTE, BDAY
 *     Typ-Mapping: EMAIL/TEL TYPE=HOME→home, WORK→work, CELL/MOBILE→mobile
 *     BDAY normalisiert zu YYYY-MM-DD (cmx_datum)
 */
function cmx_parse_single_vcard(string $filepath) {
	$raw = file_get_contents($filepath);
	if ($raw === false || $raw === '') return new \WP_Error('vcf_empty', 'vCard ist leer oder nicht lesbar.');

	// Zeilen normalisieren + Unfold
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

	// Genau ein Datensatz
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
		'bday'       => '',        // YYYY-MM-DD
		'emails'     => [],        // [['type'=>'home|work|other', 'value'=>'...']]
		'tels'       => [],        // [['type'=>'home|work|cell|mobile|other','value'=>'...']]
		'addresses'  => [],        // [['street'=>..., 'zip'=>..., 'city'=>..., 'country'=>...]]
	];

	foreach ($block as $line) {
		$pos = strpos($line, ':');
		if ($pos === false) continue;
		$left  = substr($line, 0, $pos);
		$value = substr($line, $pos + 1);

		$parts  = explode(';', $left);
		$prop   = strtoupper(array_shift($parts));
		$types  = [];
		foreach ($parts as $p) {
			[$k, $v] = array_map('trim', array_pad(explode('=', $p, 2), 2, ''));
			if (strtoupper($k ?: 'TYPE') === 'TYPE') {
				// TYPE kann mehrfach sein: TYPE=HOME,WORK oder ;TYPE=HOME;TYPE=INTERNET
				foreach (explode(',', ($v === '' ? $p : $v)) as $tv) {
					$tv = strtoupper(trim($tv));
					if ($tv !== '' && $tv !== 'TYPE') $types[] = $tv;
				}
			}
		}
		$type_str = implode(',', array_unique($types));

		switch ($prop) {
			case 'N': { // Nachname;Vorname;Mittel;Prefix;Suffix
				$bits = explode(';', $value);
				if ($out['last_name']  === '') $out['last_name']  = sanitize_text_field($bits[0] ?? '');
				if ($out['first_name'] === '') $out['first_name'] = sanitize_text_field($bits[1] ?? '');
				break;
			}
			case 'FN': { // Vollname – falls N fehlt, grob splitten
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
				$out['bday'] = cmx_normalize_bday($value); // YYYY-MM-DD oder ''
				break;

			case 'EMAIL': {
				$email = sanitize_email($value);
				if (!$email) break;
				$type = 'other';
				if (str_contains($type_str, 'HOME')) $type = 'home';
				elseif (str_contains($type_str, 'WORK')) $type = 'work';
				$out['emails'][] = ['type' => $type, 'value' => $email];
				break;
			}

			case 'TEL': {
				$tel = sanitize_text_field($value);
				if (!$tel) break;
				$type = 'other';
				if (str_contains($type_str, 'HOME'))   $type = 'home';
				elseif (str_contains($type_str, 'WORK'))   $type = 'work';
				elseif (str_contains($type_str, 'CELL') || str_contains($type_str, 'MOBILE')) $type = 'mobile';
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

	// Begrenzen laut Vorgabe
	if (count($out['emails']) > 3)    $out['emails']    = array_slice($out['emails'], 0, 3);
	if (count($out['addresses']) > 2) $out['addresses'] = array_slice($out['addresses'], 0, 2);

	return $out;
}

/** BDAY normalisieren zu YYYY-MM-DD, akzeptiert YYYYMMDD, YYYY-MM-DD, YYYY.MM.DD */
function cmx_normalize_bday(string $raw): string {
	$raw = trim($raw);
	if ($raw === '') return '';
	// YYYYMMDD
	if (preg_match('/^\d{8}$/', $raw)) {
		return substr($raw,0,4) . '-' . substr($raw,4,2) . '-' . substr($raw,6,2);
	}
	// YYYY-MM-DD oder YYYY.MM.DD oder YYYY/MM/DD
	if (preg_match('/^(\d{4})[-\.\/](\d{2})[-\.\/](\d{2})$/', $raw, $m)) {
		return $m[1] . '-' . $m[2] . '-' . $m[3];
	}
	// Fallback: keine sichere Erkennung
	return '';
}

/** Redirect in die Edit-Ansicht; Fallback zurück in die Kontakte-Liste */
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

/** Erfolgsmeldung sowohl auf Edit- als auch auf Listenansicht zeigen */
add_action('admin_notices', function () {
	if (empty($_GET['cmx_notice'])) return;
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen) return;
	if (($screen->base === 'post' && $screen->post_type === 'kontakte') || ($screen->base === 'edit' && $screen->post_type === 'kontakte')) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(wp_unslash($_GET['cmx_notice'])) . '</p></div>';
	}
});
