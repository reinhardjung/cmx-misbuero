<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

require_once __DIR__ . '/vorlage_mail.php';


/**
 * Metabox-Teil: "versenden"-Button
 */
function cmxbu_render_beleg_send_metabox(\WP_Post $post): void {

	[, $pdf_abs_path] = cmxbu_get_beleg_pdf_paths($post);
	$has_pdf          = is_file($pdf_abs_path);
	$post_id          = (int) $post->ID;

	if ($has_pdf) {
		$href = \esc_url(\admin_url("admin-post.php?action=cmxbu_beleg_send&post_id={$post_id}"));
		// echo '<a href="' . $href . '" class="button button-secondary alignleft">versenden</a>';
		echo '<a href="' . esc_url( $href ) . '" title="PDF-Link per Mail versenden" class="button button-secondary alignleft"><span style="margin-top:5px;" class="dashicons dashicons-email"></span></a>';
	}
	// else {
	// 	echo '<a href="#" class="button button-secondary alignleft disabled" style="pointer-events:none;opacity:0.5;">versenden</a>';
	// }
}


/**
 * Handler: Beleg per E-Mail versenden
 * URL: /wp-admin/admin-post.php?action=cmxbu_beleg_send&post_id=123
 */
function cmxbu_handle_beleg_send(): void {

	if (empty($_GET['post_id'])) {
		\wp_die('Beleg-ID fehlt.');
	}

	$post_id = (int) $_GET['post_id'];
	$post    = \get_post($post_id);

	if (!$post || $post->post_type !== 'belege') {
		\wp_die('Beleg nicht gefunden.');
	}
	if (function_exists(__NAMESPACE__ . '\\cmxbu_log')) {
		cmxbu_log('MAIL: start', ['post_id' => $post_id]);
	}
	$beleg_id = (string) ($post->post_title ?? '');

	// PDF-Pfade
	[, $pdf_abs_path] = cmxbu_get_beleg_pdf_paths($post);
	if (!is_file($pdf_abs_path)) {
		if (function_exists(__NAMESPACE__ . '\\cmxbu_log')) {
			cmxbu_log('MAIL: pdf not found', ['post_id' => $post_id, 'pdf' => $pdf_abs_path]);
		}
		\wp_die('PDF nicht gefunden.');
	}

	// Token → Download-Link
	$token        = cmxbu_get_stable_token($post_id);
	$download_url = \add_query_arg('beleg', $token, \home_url('/'));


	/**
	 * --------------------------
	 * K O N T A K T   I D
	 * warningsicher
	 * --------------------------
	 */
	$kontakt_id = get_post_meta($post_id, '_cmx_beleg_kontakt_id', true);
	if (empty($kontakt_id)) {
		if (function_exists(__NAMESPACE__ . '\\cmxbu_log')) {
			cmxbu_log('MAIL: missing kontakt_id', ['post_id' => $post_id]);
		}

		add_action('admin_notices', function () {
			?>
			<div class="notice notice-error is-dismissible">
				<p><strong>Kontakt / Adresse fehlt.</strong></p>
			</div>
			<?php
		});

		\wp_safe_redirect(\get_edit_post_link($post_id, ''));
		exit;
	}


	/**
	 * E-Mail-Adresse des Kontakts
	 */
	$to = \get_post_meta($kontakt_id, '_cmx_email_1', true);

	$anrede_key = defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_ANREDE')
		? CMX_KONTAKTE_META_ANREDE
		: '_cmx_kontakte_anrede';
	$anrede = trim((string) get_post_meta($kontakt_id, $anrede_key, true));
	$faellig_bis = cmxbu_get_beleg_due_date_display($post_id);
	$betrag = cmxbu_get_beleg_amount_display($post_id);

	if (empty($to) || !\is_email($to)) {
		if (function_exists(__NAMESPACE__ . '\\cmxbu_log')) {
			cmxbu_log('MAIL: invalid email', ['post_id' => $post_id, 'email' => (string) $to]);
		}
		\wp_die('Keine gültige Empfänger-E-Mailadresse hinterlegt.');
	}
	[, $beleg_slug] = cmx_get_beleg_type($post);
	$beleg_label = [
		'rechnung'     => 'Rechnung',
		'angebot'      => 'Angebot',
		'lieferschein' => 'Lieferschein',
		'gutschrift'   => 'Gutschrift',
	][$beleg_slug] ?? ($beleg_slug !== '' ? ucfirst($beleg_slug) : 'Beleg');
	$subject = $beleg_label . ': ' . $beleg_id;

	$message = cmx_get_belegmail($beleg_slug, $kontakt_id);
	if (trim($message) === '') {
		$message = cmxbu_render_belegmail_template([
			'anrede' => $anrede,
			'beleg_label' => $beleg_label,
			'beleg_id' => $beleg_id,
			'download_url' => $download_url,
			'faellig_bis' => $faellig_bis,
			'betrag' => $betrag,
			'site_name' => \get_bloginfo('name'),
			'catalog_url' => \home_url('/katalog/'),
		]);
	}
	if ($faellig_bis !== '') {
		$message = cmxbu_replace_placeholder_with_spacing($message, '{faellig_bis}', esc_html($faellig_bis));
	}
	if ($betrag !== '') {
		$message = cmxbu_replace_placeholder_with_spacing($message, '{betrag}', esc_html($betrag));
	}
	$beleg_link = '<a href="' . esc_url($download_url) . '">' . esc_html($beleg_id) . '</a>';
	if (strpos($message, '{beleg}') !== false) {
		$message = cmxbu_replace_placeholder_with_spacing($message, '{beleg}', $beleg_link);
	}
	// var_dump($message); exit;
	// cmx_get_belegfuss($beleg_type);
	$headers = ['Content-Type: text/html; charset=UTF-8'];
	$message = cmxbu_prepare_belegmail_html($message);
	$sent = \wp_mail($to, $subject, $message, $headers);

	if (function_exists(__NAMESPACE__ . '\\cmxbu_log')) {
		cmxbu_log('MAIL: result', ['post_id' => $post_id, 'sent' => (bool) $sent, 'to' => (string) $to, 'subject' => (string) $subject]);
	}

	if (!$sent) {
		\wp_die('E-Mail konnte nicht gesendet werden.');
	}

	\wp_safe_redirect(\get_edit_post_link($post_id, ''));
	exit;
}
\add_action('admin_post_cmxbu_beleg_send', __NAMESPACE__ . '\\cmxbu_handle_beleg_send');



function cmx_get_belegmail(string $key, ?int $kontakt_id = null): string {
	// cmx_belege[belegfuss_rechnung], cmx_belege[mail_rechnung]
	$key = 'mail_' . strtolower(trim($key));
	$options = get_option('cmx_belege', []); // var_dump($options['belegfuss_rechnung']); exit;
	$message = '';

	if (isset($options[$key]) && is_string($options[$key])) {
		$message = $options[$key];
	}

	if ($kontakt_id) {
		$anrede_key = defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_ANREDE')
			? CMX_KONTAKTE_META_ANREDE
			: '_cmx_kontakte_anrede';
		$anrede = trim((string) get_post_meta($kontakt_id, $anrede_key, true));
		$message = cmxbu_replace_placeholder_with_spacing($message, '{anrede}', esc_html($anrede));
	}

	return $message;
}

function cmxbu_replace_placeholder_with_spacing(string $message, string $placeholder, string $replacement): string {
	if ($placeholder === '' || strpos($message, $placeholder) === false) {
		return $message;
	}

	$parts = explode($placeholder, $message);
	$out = array_shift($parts);
	foreach ($parts as $part) {
		$before = $out !== '' ? substr($out, -1) : '';
		$after = $part !== '' ? substr($part, 0, 1) : '';
		if ($before !== '' && !preg_match('/\\s/', $before)) {
			$out .= ' ';
		}
		$out .= $replacement;
		if ($after !== '' && !preg_match('/\\s/', $after)) {
			$out .= ' ';
		}
		$out .= $part;
	}

	return $out;
}

function cmxbu_prepare_belegmail_html(string $message): string {
	// If message already contains HTML, leave it as-is.
	if (preg_match('/<[^>]+>/', $message)) {
		return $message;
	}

	// Plain text: preserve new lines.
	$message = esc_html($message);
	return nl2br($message);
}

function cmxbu_get_beleg_due_date_display(int $post_id): string {
	$keys = [];
	if (defined(__NAMESPACE__ . '\\CMX_BELEG_META_FAELLIG')) {
		$keys[] = CMX_BELEG_META_FAELLIG;
	}
	$keys[] = '_cmx_beleg_faelligkeitsdatum';
	$keys[] = '_cmx_beleg_faellig_am';

	$raw = null;
	if (function_exists(__NAMESPACE__ . '\\cmxbu_first_meta')) {
		$raw = cmxbu_first_meta($post_id, $keys);
	} else {
		foreach ($keys as $key) {
			$val = get_post_meta($post_id, $key, true);
			if ($val !== '' && $val !== null) {
				$raw = (string) $val;
				break;
			}
		}
	}

	$raw = trim((string) $raw);
	if ($raw === '') return '';
	if (preg_match('~^\\d{1,2}\\.\\d{1,2}\\.\\d{4}$~', $raw)) return $raw;

	$ts = strtotime($raw);
	if ($ts) return date('d.m.Y', $ts);

	return $raw;
}

function cmxbu_get_beleg_amount_display(int $post_id): string {
	if (!function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
		return '';
	}

	$currency = '';
	if (defined(__NAMESPACE__ . '\\CMX_BELEG_META_WAEHRUNG')) {
		$currency = (string) get_post_meta($post_id, CMX_BELEG_META_WAEHRUNG, true);
	}
	if ($currency === '') {
		$currency = (string) get_post_meta($post_id, '_cmx_beleg_waehrung', true);
	}
	if ($currency === '') {
		$currency = 'CHF';
	}

	$mwst_rate = 0.0;
	if (function_exists(__NAMESPACE__ . '\\cmxbu_get_mwst_term_data')) {
		$mwst_term_id = (int) get_post_meta($post_id, '_cmx_beleg_mwst_term', true);
		$mwst = cmxbu_get_mwst_term_data($mwst_term_id);
		$mwst_rate = (float)($mwst['rate'] ?? 0.0);
	}

	$is_brutto = get_post_meta($post_id, '_cmx_beleg_is_brutto', true) === '1';
	$opts_general = (array) get_option('cmx_einstellungen', []);
	$is_mwst_pflichtig = !empty($opts_general['mwst_pflichtig'])
		|| !empty($opts_general['mwst_pfl'])
		|| !empty($opts_general['mwstpflichtig']);
	if (!$is_mwst_pflichtig) {
		$mwst_rate = 0.0;
		$is_brutto = false;
	}

	$calc = cmxbu_get_beleg_positionen_calc($post_id, [
		'currency' => $currency,
		'tax_rate' => $mwst_rate,
		'is_brutto' => $is_brutto,
	]);

	$total = (float)($calc['total'] ?? 0.0);
	$formatted = number_format_i18n($total, 2);

	return trim($formatted . ' ' . $currency);
}
