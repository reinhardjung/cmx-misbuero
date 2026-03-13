<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/** ======================================================================
 * TITEL VOR dem Speichern setzen (keine Schleife!)
 * ====================================================================== */
\add_filter('wp_insert_post_data', __NAMESPACE__.'\\cmx_prepare_kontakt_title_before_save', 20, 2);
function cmx_prepare_kontakt_title_before_save(array $data, array $postarr) : array {
	if (($data['post_type'] ?? '') !== 'kontakte') return $data;

	$incoming_title = trim((string)($data['post_title'] ?? ''));
	$is_missing     = (mb_strtolower($incoming_title) === mb_strtolower('Firmenname fehlt'));

	$vor  = isset($_POST['cmx_vorname'])  ? sanitize_text_field(wp_unslash($_POST['cmx_vorname']))  : '';
	$nach = isset($_POST['cmx_nachname']) ? sanitize_text_field(wp_unslash($_POST['cmx_nachname'])) : '';
	$priv = !empty($_POST['cmx_privat']);

	$url  = isset($_POST['cmx_url']) ? trim((string) wp_unslash($_POST['cmx_url'])) : '';
	if ($url !== '' && !preg_match('~^https?://~i', $url)) {
		$url = 'https://'.ltrim($url, '/');
	}

	$person_title      = trim($vor . ' ' . $nach);
	$is_person_title   = ($person_title !== '' && mb_strtolower($incoming_title) === mb_strtolower($person_title));
	$url_core          = ($url !== '') ? cmx_domain_core_from_url($url) : '';
	$url_company_title = ($url_core !== '') ? mb_strtoupper($url_core) : '';
	$is_company_title  = ($url_company_title !== '' && mb_strtolower($incoming_title) === mb_strtolower($url_company_title));
	$should_fill       = (
		$incoming_title === ''
		|| $is_missing
		|| (!$priv && $url !== '' && $is_person_title)
		|| ($priv && $person_title !== '' && $is_company_title)
	);

	if (!$should_fill) return $data;

	if ($priv && ($vor !== '' || $nach !== '')) {
		$new_title = trim($vor.' '.$nach);
	} elseif ($url !== '') {
		$new_title = ($url_company_title !== '') ? $url_company_title : 'Firmenname fehlt';
	} else {
		$new_title = 'Firmenname fehlt';
	}

	$data['post_title'] = $new_title;
	if (empty($postarr['ID']) || ($postarr['ID'] && $is_missing)) {
		$data['post_name'] = sanitize_title($new_title);
	}
	return $data;
}

/** ======================================================================
 * ZENTRALER SAVE-HANDLER – speichert NUR Metas (kein wp_update_post!)
 * ====================================================================== */
\add_action('save_post_kontakte', __NAMESPACE__.'\\cmx_save_kontakte_all', 10, 3);
function cmx_save_kontakte_all($post_id, $post, $update) {
	if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if ($post->post_type !== 'kontakte') return;
	if (!current_user_can('edit_post', $post_id)) return;

	/* --- Stammdaten (per Nonce) --- */
	if (isset($_POST['cmx_kontakte_nonce']) && \wp_verify_nonce($_POST['cmx_kontakte_nonce'], 'cmx_kontakte_save_meta')) {
		$vor  = isset($_POST['cmx_vorname'])  ? sanitize_text_field(wp_unslash($_POST['cmx_vorname']))  : '';
		$nach = isset($_POST['cmx_nachname']) ? sanitize_text_field(wp_unslash($_POST['cmx_nachname'])) : '';
		$priv = !empty($_POST['cmx_privat']) ? 1 : 0;

		$url  = isset($_POST['cmx_url']) ? trim((string) wp_unslash($_POST['cmx_url'])) : '';
		if ($url !== '' && !preg_match('~^https?://~i', $url)) {
			$url = 'https://'.ltrim($url, '/');
		}

		// NEU: Firmengründung / Geburtsdatum (YYYY-MM-DD), mit serverseitiger Validierung
		$firmengruendung = isset($_POST['cmx_firmengruendung']) ? (string) \wp_unslash($_POST['cmx_firmengruendung']) : '';
		$geburtsdatum    = isset($_POST['cmx_geburtsdatum']) ? (string) \wp_unslash($_POST['cmx_geburtsdatum']) : '';
		if (function_exists(__NAMESPACE__ . '\\cmx_sanitize_date_ymd')) {
			$firmengruendung = \call_user_func(__NAMESPACE__ . '\\cmx_sanitize_date_ymd', $firmengruendung);
			$geburtsdatum    = \call_user_func(__NAMESPACE__ . '\\cmx_sanitize_date_ymd', $geburtsdatum);
		} else {
			$dt = \DateTime::createFromFormat('Y-m-d', $firmengruendung);
			$firmengruendung = ($dt && $dt->format('Y-m-d') === $firmengruendung) ? $firmengruendung : '';
			$dt = \DateTime::createFromFormat('Y-m-d', $geburtsdatum);
			$geburtsdatum = ($dt && $dt->format('Y-m-d') === $geburtsdatum) ? $geburtsdatum : '';
		}

		\update_post_meta($post_id, CMX_KONTAKTE_META_VORNAME,  $vor);
		\update_post_meta($post_id, CMX_KONTAKTE_META_NACHNAME, $nach);
		\update_post_meta($post_id, CMX_KONTAKTE_META_PRIVAT,   $priv);
		\update_post_meta($post_id, CMX_KONTAKTE_META_URL,      esc_url_raw($url));
		\update_post_meta($post_id, CMX_KONTAKTE_META_FIRMENGRUENDUNG, $firmengruendung);
		\update_post_meta($post_id, CMX_KONTAKTE_META_GEBURTSDATUM,    $geburtsdatum);
		$legacy_val = $geburtsdatum !== '' ? $geburtsdatum : $firmengruendung;
		\update_post_meta($post_id, CMX_KONTAKTE_META_DATUM,    $legacy_val);
	}

	/* --- Umsatz-Metabox (optional) --- */
	if (isset($_POST['cmx_kontakte_umsatz_nonce']) && \wp_verify_nonce($_POST['cmx_kontakte_umsatz_nonce'], 'cmx_kontakte_umsatz_save')) {
		if (isset($_POST['cmx_kontakte_umsatz_field'])) {
			$in = (string) $_POST['cmx_kontakte_umsatz_field'];
			$in = preg_replace('~[^0-9\.,]~', '', $in);
			$in = str_replace(',', '.', $in);
			if (substr_count($in, '.') > 1) {
				$parts = explode('.', $in);
				$dec   = array_pop($parts);
				$int   = preg_replace('~[^0-9]~', '', implode('', $parts));
				$in    = $int . '.' . preg_replace('~[^0-9]~', '', $dec);
			}
			$num = is_numeric($in) ? (float)$in : 0.0;
			$normalized = number_format($num, 2, '.', '');
			\update_post_meta($post_id, CMX_KONTAKTE_META_UMSATZ, $normalized);
		}
	}

	/* --- Adressen-Metabox (ohne Land) --- */
	if (isset($_POST['cmx_address_nonce']) && \wp_verify_nonce($_POST['cmx_address_nonce'], 'cmx_save_address_meta')) {
		$map = [
			'_cmx_rechnung_strasse' => CMX_RECHNUNG_META_STRASSE,
			'_cmx_rechnung_zusatz'  => CMX_RECHNUNG_META_ZUSATZ,
			'_cmx_rechnung_plz'     => CMX_RECHNUNG_META_PLZ,
			'_cmx_rechnung_ort'     => CMX_RECHNUNG_META_ORT,
			'_cmx_liefer_strasse'   => CMX_LIEFER_META_STRASSE,
			'_cmx_liefer_zusatz'    => CMX_LIEFER_META_ZUSATZ,
			'_cmx_liefer_plz'       => CMX_LIEFER_META_PLZ,
			'_cmx_liefer_ort'       => CMX_LIEFER_META_ORT,
		];
		foreach ($map as $field => $meta_key) {
			if (isset($_POST[$field])) {
				\update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$field]));
			}
		}
	}

	/* --- Optionale Auto-Befüllung Rechnungsadresse (ohne Land) --- */
	if (\apply_filters('cmx_auto_fill_billing_on_save', false) && function_exists(__NAMESPACE__.'\\cmx_fill_billing_from_url')) {
		$has_billing = (
			\get_post_meta($post_id, CMX_RECHNUNG_META_STRASSE, true)
			|| \get_post_meta($post_id, CMX_RECHNUNG_META_PLZ, true)
			|| \get_post_meta($post_id, CMX_RECHNUNG_META_ORT, true)
		);
		if (!$has_billing) {
			try {
				\call_user_func(__NAMESPACE__.'\\cmx_fill_billing_from_url', (int)$post_id);
			} catch (\Throwable $e) {
				if (function_exists('error_log')) {
					error_log('[CMX] cmx_fill_billing_from_url error: '.$e->getMessage());
				}
			}
		}
	}
}
