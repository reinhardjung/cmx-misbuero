<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_posted_person_title')) {
	function cmx_kontakte_posted_person_title(): string {
		$communication = $_POST['cmx_kommunikation'] ?? null;
		$contacts = \is_array($communication) ? ($communication['kontakte'] ?? null) : null;
		if (\is_array($contacts)) {
			foreach ($contacts as $row) {
				if (!\is_array($row)) {
					continue;
				}
				$vorname = \trim(\sanitize_text_field((string) \wp_unslash($row['vorname'] ?? '')));
				$nachname = \trim(\sanitize_text_field((string) \wp_unslash($row['nachname'] ?? '')));
				$title = \trim($vorname . ' ' . $nachname);
				if ($title !== '') {
					return $title;
				}
			}
		}

		return '';
	}
}

/** ======================================================================
 * LEEREN POST-TITEL bei Kontakte auffüllen
 * ====================================================================== */
\add_filter('wp_insert_post_data', __NAMESPACE__.'\\cmx_kontakte_fill_empty_post_title', 20, 2);
function cmx_kontakte_fill_empty_post_title(array $data, array $postarr): array {
	if ((string) ($data['post_type'] ?? ($postarr['post_type'] ?? '')) !== 'kontakte') return $data;
	if ((string) ($data['post_status'] ?? '') === 'auto-draft') return $data;

	$title = \trim((string) ($data['post_title'] ?? ''));
	if ($title !== '') return $data;

	$firma = isset($_POST['cmx_firma'])
		? \trim(\sanitize_text_field((string) \wp_unslash($_POST['cmx_firma'])))
		: '';
	$person_title = cmx_kontakte_posted_person_title();
	$fallback_title = $firma !== ''
		? $firma
		: ($person_title !== '' ? $person_title : 'Firmenname fehlt...');
	$data['post_title'] = $fallback_title;
	if (\trim((string) ($data['post_name'] ?? '')) === '') {
		$data['post_name'] = \sanitize_title($fallback_title);
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
		$url = isset($_POST['cmx_url']) ? \trim((string) \wp_unslash($_POST['cmx_url'])) : null;
		if ($url !== null && $url !== '' && !\preg_match('~^https?://~i', $url)) {
			$url = 'https://'.\ltrim($url, '/');
		}

		// Firmengründung / Kunde seit (YYYY-MM-DD), mit serverseitiger Validierung
		$firmengruendung = isset($_POST['cmx_firmengruendung']) ? (string) \wp_unslash($_POST['cmx_firmengruendung']) : null;
		$kunde_seit      = isset($_POST['cmx_kunde_seit']) ? (string) \wp_unslash($_POST['cmx_kunde_seit']) : null;
		if (function_exists(__NAMESPACE__ . '\\cmx_sanitize_date_ymd')) {
			if ($firmengruendung !== null) {
				$firmengruendung = \call_user_func(__NAMESPACE__ . '\\cmx_sanitize_date_ymd', $firmengruendung);
			}
			if ($kunde_seit !== null) {
				$kunde_seit = \call_user_func(__NAMESPACE__ . '\\cmx_sanitize_date_ymd', $kunde_seit);
			}
		} else {
			if ($firmengruendung !== null) {
				$dt = \DateTime::createFromFormat('Y-m-d', $firmengruendung);
				$firmengruendung = ($dt && $dt->format('Y-m-d') === $firmengruendung) ? $firmengruendung : '';
			}
			if ($kunde_seit !== null) {
				$dt = \DateTime::createFromFormat('Y-m-d', $kunde_seit);
				$kunde_seit = ($dt && $dt->format('Y-m-d') === $kunde_seit) ? $kunde_seit : '';
			}
		}

		if ($url !== null) {
			\update_post_meta($post_id, CMX_KONTAKTE_META_URL, \esc_url_raw($url));
		}
		if ($firmengruendung !== null) {
			if ($firmengruendung === '') {
				\delete_post_meta($post_id, CMX_KONTAKTE_META_FIRMENGRUENDUNG);
			} else {
				\update_post_meta($post_id, CMX_KONTAKTE_META_FIRMENGRUENDUNG, $firmengruendung);
			}
		}
		if ($kunde_seit !== null) {
			if ($kunde_seit === '') {
				\delete_post_meta($post_id, CMX_KONTAKTE_META_KUNDE_SEIT);
			} else {
				\update_post_meta($post_id, CMX_KONTAKTE_META_KUNDE_SEIT, $kunde_seit);
			}
		}

		if ($firmengruendung !== null) {
			$existing_birth = \function_exists(__NAMESPACE__ . '\\cmx_sanitize_date_ymd')
				? \call_user_func(__NAMESPACE__ . '\\cmx_sanitize_date_ymd', (string) \get_post_meta($post_id, CMX_KONTAKTE_META_GEBURTSDATUM, true))
				: (string) \get_post_meta($post_id, CMX_KONTAKTE_META_GEBURTSDATUM, true);
			$legacy_val = $firmengruendung !== '' ? $firmengruendung : $existing_birth;
			if ($legacy_val === '') {
				\delete_post_meta($post_id, CMX_KONTAKTE_META_DATUM);
			} else {
				\update_post_meta($post_id, CMX_KONTAKTE_META_DATUM, $legacy_val);
			}
		}
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
