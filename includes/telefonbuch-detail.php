<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_query_key')) {
	function cmx_telefonbuch_detail_query_key(): string {
		return 'telefonbuch_kontakt';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_redirect_telefonbuch_legacy_detail_url')) {
	function cmx_redirect_telefonbuch_legacy_detail_url(): void {
		if (!\function_exists(__NAMESPACE__ . '\\cmx_is_telefonbuch_request') || !cmx_is_telefonbuch_request()) {
			return;
		}

		$query_key = cmx_telefonbuch_detail_query_key();
		if (isset($_GET[$query_key])) {
			return;
		}

		$legacy_kontakt = isset($_GET['kontakt']) ? (int) \wp_unslash($_GET['kontakt']) : 0;
		if ($legacy_kontakt <= 0) {
			return;
		}

		\wp_safe_redirect(cmx_telefonbuch_detail_url($legacy_kontakt), 302);
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_contact_id')) {
	function cmx_telefonbuch_detail_contact_id(): int {
		if (!\function_exists(__NAMESPACE__ . '\\cmx_is_telefonbuch_request') || !cmx_is_telefonbuch_request()) {
			return 0;
		}

		$query_key = cmx_telefonbuch_detail_query_key();
		$kontakt_id = 0;
		if (isset($_GET[$query_key])) {
			$kontakt_id = (int) \wp_unslash($_GET[$query_key]);
		}
		if ($kontakt_id <= 0 && isset($_REQUEST[$query_key])) {
			$kontakt_id = (int) \wp_unslash($_REQUEST[$query_key]);
		}
		if ($kontakt_id <= 0 && \function_exists('get_query_var')) {
			$kontakt_id = (int) \get_query_var($query_key);
		}
		if ($kontakt_id <= 0) {
			$query = \parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), \PHP_URL_QUERY);
			if (\is_string($query) && $query !== '') {
				$params = [];
				\parse_str($query, $params);
				$kontakt_id = isset($params[$query_key]) ? (int) $params[$query_key] : 0;
			}
		}
		if ($kontakt_id <= 0) {
			return 0;
		}

		$post = \get_post($kontakt_id);
		if (!($post instanceof \WP_Post)) {
			return 0;
		}

		if ((string) ($post->post_type ?? '') !== 'kontakte') {
			return 0;
		}

		return !empty($post->post_status) ? $kontakt_id : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_url')) {
	function cmx_telefonbuch_detail_url(int $kontakt_id): string {
		$kontakt_id = (int) $kontakt_id;
		if ($kontakt_id <= 0) {
			return (string) \home_url('/telefonbuch/');
		}

		return (string) \add_query_arg([cmx_telefonbuch_detail_query_key() => $kontakt_id], \home_url('/telefonbuch/'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_format_date')) {
	function cmx_telefonbuch_detail_format_date(string $date): string {
		$date = \trim($date);
		if ($date === '') {
			return '';
		}

		$dt = \DateTime::createFromFormat('Y-m-d', $date);
		return $dt instanceof \DateTime ? $dt->format('d.m.Y') : $date;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_categories_taxonomy')) {
	function cmx_telefonbuch_detail_categories_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_kundenkategorie_tax')) {
			$tax = \trim((string) cmx_kundenkategorie_tax());
			if ($tax !== '' && \taxonomy_exists($tax)) {
				return $tax;
			}
		}

		foreach (['kontakte_kategorien', 'kontakte_kategorie', 'kundenkategorie', 'kontakt_kategorie'] as $tax) {
			if (\taxonomy_exists($tax)) {
				return $tax;
			}
		}

		foreach ((array) \get_object_taxonomies('kontakte', 'names') as $tax) {
			$tax = (string) $tax;
			if ($tax !== '' && \stripos($tax, 'kategorie') !== false) {
				return $tax;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_stufe_taxonomy')) {
	function cmx_telefonbuch_detail_stufe_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_stufen_tax')) {
			$tax = \trim((string) cmx_stufen_tax());
			if ($tax !== '' && \taxonomy_exists($tax)) {
				return $tax;
			}
		}

		foreach (['kontakte_stufen', 'stufen', 'kontakte_stufe', 'kontakt_stufen'] as $tax) {
			if (\taxonomy_exists($tax)) {
				return $tax;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_term_names')) {
	function cmx_telefonbuch_detail_term_names(int $post_id, string $taxonomy): array {
		$taxonomy = \trim($taxonomy);
		if ($post_id <= 0 || $taxonomy === '' || !\taxonomy_exists($taxonomy)) {
			return [];
		}

		$terms = \get_the_terms($post_id, $taxonomy);
		if (\is_wp_error($terms) || empty($terms)) {
			return [];
		}

		$names = [];
		foreach ((array) $terms as $term) {
			$name = \trim((string) ($term->name ?? ''));
			if ($name !== '') {
				$names[] = $name;
			}
		}

		return \array_values(\array_unique($names));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_people')) {
	function cmx_telefonbuch_detail_people(int $post_id): array {
		$rows = \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_read_contacts')
			? (array) cmx_kommunikation_read_contacts($post_id)
			: [];
		$primary_phone = '';
		if (
			\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_phone')
			&& \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_normalize_phone')
		) {
			$primary_phone = (string) cmx_kommunikation_normalize_phone((string) cmx_kommunikation_primary_phone($post_id));
		}

		$people = [];
		$has_primary_match = false;
		foreach ($rows as $index => $row) {
			if (!\is_array($row)) {
				continue;
			}

			$vorname = \trim((string) ($row['vorname'] ?? ''));
			$nachname = \trim((string) ($row['nachname'] ?? ''));
			$name = \trim($vorname . ' ' . $nachname);
			$birthdate = cmx_telefonbuch_detail_format_date((string) ($row['geburtsdatum'] ?? ''));
			$phone = '';
			if (\function_exists(__NAMESPACE__ . '\\cmx_kommunikation_normalize_phone')) {
				$phone = (string) cmx_kommunikation_normalize_phone((string) ($row['telefon'] ?? ''));
			}
			$is_primary = $primary_phone !== '' && $phone !== '' && $phone === $primary_phone;
			if ($is_primary) {
				$has_primary_match = true;
			}

			if ($name === '' && $birthdate === '') {
				continue;
			}

			$people[] = [
				'name' => $name !== '' ? $name : '(ohne Namen)',
				'birthdate' => $birthdate,
				'is_primary' => $is_primary,
				'index' => (int) $index,
			];
		}

		if (!$has_primary_match && isset($people[0])) {
			$people[0]['is_primary'] = true;
		}

		return $people;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_latest_note')) {
	function cmx_telefonbuch_detail_latest_note(int $post_id): array {
		if (!\function_exists(__NAMESPACE__ . '\\cmx_notizen_load_rows')) {
			return [];
		}

		$rows = (array) cmx_notizen_load_rows($post_id, 'kontakte');
		$row = \is_array($rows[0] ?? null) ? (array) $rows[0] : [];
		if ($row === []) {
			return [];
		}

		return [
			'betreff' => \trim((string) ($row['betreff'] ?? '')),
			'datum' => cmx_telefonbuch_detail_format_date((string) ($row['datum'] ?? '')),
			'zeit' => \trim((string) ($row['zeit'] ?? '')),
			'text' => \trim((string) ($row['text'] ?? '')),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_latest_task')) {
	function cmx_telefonbuch_detail_latest_task(int $post_id): array {
		$tasks = \get_post_meta($post_id, '_cmx_projekt_tasks', true);
		if (!\is_array($tasks) || $tasks === []) {
			return [];
		}

		$normalized = [];
		foreach ($tasks as $index => $task) {
			if (!\is_array($task)) {
				continue;
			}

			$date = \trim((string) ($task['datum'] ?? ''));
			$time = \trim((string) ($task['zeit'] ?? ''));
			$info = \trim((string) ($task['info'] ?? ''));
			$artikel_id = (int) ($task['artikel_id'] ?? 0);
			$artikel = $artikel_id > 0 ? \trim((string) \get_the_title($artikel_id)) : '';
			$produkt_id = (int) ($task['produkt_id'] ?? 0);
			$produkt = $produkt_id > 0 ? \trim((string) \get_the_title($produkt_id)) : '';
			$dauer = \trim((string) ($task['dauer'] ?? ''));

			if ($date === '' && $time === '' && $info === '' && $artikel === '' && $produkt === '' && $dauer === '') {
				continue;
			}

			$normalized[] = [
				'index' => (int) $index,
				'sort' => ($date !== '' ? $date : '0000-00-00') . ' ' . ($time !== '' ? $time : '00:00'),
				'datum' => cmx_telefonbuch_detail_format_date($date),
				'zeit' => $time,
				'info' => $info,
				'artikel' => $artikel,
				'produkt' => $produkt,
				'dauer' => $dauer,
			];
		}

		if ($normalized === []) {
			return [];
		}

		\usort($normalized, static function (array $a, array $b): int {
			$cmp = \strcmp((string) ($b['sort'] ?? ''), (string) ($a['sort'] ?? ''));
			if ($cmp !== 0) {
				return $cmp;
			}
			return ((int) ($a['index'] ?? 0)) <=> ((int) ($b['index'] ?? 0));
		});

		return (array) ($normalized[0] ?? []);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_beleg_currency')) {
	function cmx_telefonbuch_detail_beleg_currency(int $beleg_id): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_currency')) {
			$currency = \trim((string) cmx_kontakt_beleg_currency($beleg_id));
			if ($currency !== '') {
				return $currency;
			}
		}

		$currency = '';
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_WAEHRUNG')) {
			$currency = (string) \get_post_meta($beleg_id, \constant(__NAMESPACE__ . '\\CMX_BELEG_META_WAEHRUNG'), true);
		}
		if ($currency === '') {
			$currency = (string) \get_post_meta($beleg_id, '_cmx_beleg_waehrung', true);
		}

		$currency = \strtoupper(\trim($currency));
		return $currency !== '' ? $currency : 'CHF';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_beleg_amount_label')) {
	function cmx_telefonbuch_detail_beleg_amount_label(int $beleg_id): string {
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_amount_display')) {
			$display = \trim((string) cmxbu_get_beleg_amount_display($beleg_id));
			if ($display !== '') {
				$display = (string) \preg_replace('/\s+[A-Z]{3}$/u', '', $display);
				return \trim($display);
			}
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_amount_label')) {
			$amount = \trim((string) cmx_kontakt_beleg_amount_label($beleg_id));
			if ($amount !== '') {
				return \trim($amount . ' ' . cmx_telefonbuch_detail_beleg_currency($beleg_id));
			}
		}

		$total = null;
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
			$calc = (array) cmxbu_get_beleg_positionen_calc($beleg_id);
			if (isset($calc['total']) && \is_numeric($calc['total'])) {
				$total = (float) $calc['total'];
			}
		}

		if ($total === null) {
			$override = \trim((string) \get_post_meta($beleg_id, '_cmx_beleg_summe_override', true));
			if ($override !== '') {
				$normalized = \str_replace([' ', "'"], '', $override);
				$normalized = \str_replace(',', '.', $normalized);
				if (\is_numeric($normalized)) {
					$total = (float) $normalized;
				}
			}
		}

		if ($total === null) {
			return '';
		}

		$formatted = \function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')
			? (string) cmx_format_swiss_number($total, 2)
			: \number_format($total, 2, '.', "'");

		return \trim($formatted . ' ' . cmx_telefonbuch_detail_beleg_currency($beleg_id));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_latest_beleg')) {
	function cmx_telefonbuch_detail_latest_beleg(int $post_id): array {
		if (!\class_exists('\\WP_Query') || $post_id <= 0) {
			return [];
		}

		$kontakt_keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID')) {
			$kontakt_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID');
		}
		$kontakt_keys = \array_values(\array_unique(\array_filter(\array_merge($kontakt_keys, ['_cmx_beleg_kontakt_id', 'cmx_beleg_kontakt_id']))));
		if ($kontakt_keys === []) {
			return [];
		}

		$meta_or = ['relation' => 'OR'];
		foreach ($kontakt_keys as $key) {
			$meta_or[] = [
				'key' => $key,
				'value' => $post_id,
				'compare' => '=',
				'type' => 'NUMERIC',
			];
		}

		$query = new \WP_Query([
			'post_type' => \defined(__NAMESPACE__ . '\\CMX_PT_BELEGE') ? (string) \constant(__NAMESPACE__ . '\\CMX_PT_BELEGE') : 'belege',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page' => 1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'orderby' => 'date',
			'order' => 'DESC',
			'meta_query' => [$meta_or],
		]);

		$beleg_id = (int) ($query->posts[0] ?? 0);
		if ($beleg_id <= 0) {
			return [];
		}

		$paid_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM')
			: '_cmx_beleg_bezahlt_am';
		$paid_raw = \trim((string) \get_post_meta($beleg_id, $paid_key, true));
		if ($paid_raw === '' && $paid_key !== '_cmx_beleg_bezahlt_am') {
			$paid_raw = \trim((string) \get_post_meta($beleg_id, '_cmx_beleg_bezahlt_am', true));
		}

		return [
			'id' => $beleg_id,
			'title' => \trim((string) \get_the_title($beleg_id)) ?: ('#' . $beleg_id),
			'url' => (string) \get_edit_post_link($beleg_id, ''),
			'date' => cmx_telefonbuch_detail_format_date((string) \get_the_date('Y-m-d', $beleg_id)),
			'amount' => cmx_telefonbuch_detail_beleg_amount_label($beleg_id),
			'is_paid' => $paid_raw !== '',
			'paid_label' => $paid_raw !== '' ? ('Bezahlt am ' . cmx_telefonbuch_detail_format_date($paid_raw)) : 'Noch nicht bezahlt',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_telefonbuch_detail_page')) {
	function cmx_render_telefonbuch_detail_page(): void {
		$kontakt_id = cmx_telefonbuch_detail_contact_id();
		if ($kontakt_id <= 0) {
			return;
		}

		$title = \trim((string) \get_the_title($kontakt_id));
		if ($title === '') {
			$title = '#' . $kontakt_id;
		}

		$firmengruendung_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_FIRMENGRUENDUNG')
			? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_FIRMENGRUENDUNG')
			: '_cmx_kontakte_firmengruendung';
		$firmengruendung = cmx_telefonbuch_detail_format_date((string) \get_post_meta($kontakt_id, $firmengruendung_key, true));
		$stufen = cmx_telefonbuch_detail_term_names($kontakt_id, cmx_telefonbuch_detail_stufe_taxonomy());
		$kategorien = cmx_telefonbuch_detail_term_names($kontakt_id, cmx_telefonbuch_detail_categories_taxonomy());
		$people = cmx_telefonbuch_detail_people($kontakt_id);
		$latest_note = cmx_telefonbuch_detail_latest_note($kontakt_id);
		$latest_task = cmx_telefonbuch_detail_latest_task($kontakt_id);
		$latest_beleg = cmx_telefonbuch_detail_latest_beleg($kontakt_id);
		$back_url = (string) \home_url('/telefonbuch/');
		$contact_logo = \function_exists(__NAMESPACE__ . '\\cmx_contact_logo_url')
			? (string) cmx_contact_logo_url($kontakt_id)
			: '';

		while (\ob_get_level()) {
			\ob_end_clean();
		}

		if (!\defined('DONOTCACHEPAGE')) {
			\define('DONOTCACHEPAGE', true);
		}
		\nocache_headers();
		\status_header(200);

		echo '<!doctype html><html lang="de"><head><meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>' . \esc_html($title) . ' - Telefonbuch</title>';
		echo '<style>
			:root{color-scheme:light}
			*{box-sizing:border-box}
			body{margin:0;font-family:Segoe UI,Roboto,Arial,sans-serif;background:#efefef;color:#1d2327}
			a{color:#135e96}
			.cmx-telefonbuch-detail-page{max-width:1180px;margin:0 auto;padding:32px 18px 40px}
			.cmx-telefonbuch-detail-card{background:#fff;border:1px solid #ddd;border-radius:14px;box-shadow:0 18px 40px rgba(0,0,0,.06);overflow:hidden}
			.cmx-telefonbuch-detail-head{padding:24px 28px 22px;background:linear-gradient(135deg,#f7f7f7 0%,#ededed 100%);border-bottom:1px solid #e2e2e2;display:flex;justify-content:space-between;gap:24px;align-items:flex-start}
			.cmx-telefonbuch-detail-kicker{margin:0 0 8px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280}
			.cmx-telefonbuch-detail-kicker a{color:inherit;text-decoration:none}
			.cmx-telefonbuch-detail-title{margin:0;font-size:30px;line-height:1.1}
			.cmx-telefonbuch-detail-sub{margin:8px 0 0;color:#6b7280;font-size:14px}
			.cmx-telefonbuch-detail-logo{display:block;max-width:110px;max-height:110px;border-radius:14px;border:1px solid #e0e0e0;background:#fff;padding:6px;object-fit:contain}
			.cmx-telefonbuch-detail-body{padding:22px 28px 28px;display:grid;gap:18px}
			.cmx-telefonbuch-detail-meta{display:flex;flex-wrap:wrap;gap:10px}
			.cmx-telefonbuch-detail-pill{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:999px;background:#f4f6f8;border:1px solid #dde3e8;font-size:13px}
			.cmx-telefonbuch-detail-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:18px}
			.cmx-telefonbuch-detail-box{border:1px solid #e8ecef;border-radius:12px;background:#fafbfc;padding:16px}
			.cmx-telefonbuch-detail-box h3{margin:0 0 10px;font-size:15px}
			.cmx-telefonbuch-detail-box-head{display:flex;justify-content:space-between;gap:12px;align-items:baseline;margin:0 0 10px}
			.cmx-telefonbuch-detail-box-head-note{margin-bottom:2px}
			.cmx-telefonbuch-detail-box-head h3{margin:0;font-size:15px}
			.cmx-telefonbuch-detail-empty{color:#6b7280}
			.cmx-telefonbuch-detail-people{margin:0;padding:0;list-style:none;display:grid;gap:8px}
			.cmx-telefonbuch-detail-people li{display:flex;justify-content:space-between;gap:12px;padding:8px 10px;border-radius:10px;background:#fff;border:1px solid #e9edf0}
			.cmx-telefonbuch-detail-people li.is-current{background:#f5ebe1;border-color:#ead9c7}
			.cmx-telefonbuch-detail-people strong{font-weight:700}
			.cmx-telefonbuch-detail-date{color:#667085;white-space:nowrap}
			.cmx-telefonbuch-detail-text{color:#344054;line-height:1.55}
			.cmx-telefonbuch-detail-line{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
			.cmx-telefonbuch-detail-task-row{display:flex;justify-content:space-between;gap:12px;align-items:baseline}
			.cmx-telefonbuch-detail-task-info{color:#344054;line-height:1.55;min-width:0;padding-right:12px}
			.cmx-telefonbuch-detail-line + .cmx-telefonbuch-detail-line{margin-top:8px}
			.cmx-telefonbuch-detail-label{color:#667085;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
			.cmx-telefonbuch-detail-state-paid{color:#137333;font-weight:700}
			.cmx-telefonbuch-detail-state-open{color:#c62828;font-weight:700}
			.cmx-telefonbuch-detail-beleg-row{display:flex;justify-content:space-between;gap:14px;align-items:center;font-weight:700}
			.cmx-telefonbuch-detail-beleg-meta{display:flex;flex-wrap:wrap;gap:12px;align-items:center;min-width:0}
			.cmx-telefonbuch-detail-beleg-id{min-width:0}
			.cmx-telefonbuch-detail-beleg-id a{font-weight:700;text-decoration:none}
			.cmx-telefonbuch-detail-beleg-date{white-space:nowrap}
			.cmx-telefonbuch-detail-beleg-amount{white-space:nowrap;margin-left:auto;text-align:right}
			@media (max-width:820px){
				.cmx-telefonbuch-detail-page{padding:18px 12px 24px}
				.cmx-telefonbuch-detail-head{padding:18px 16px;flex-direction:column}
				.cmx-telefonbuch-detail-body{padding:16px}
				.cmx-telefonbuch-detail-grid{grid-template-columns:1fr}
				.cmx-telefonbuch-detail-title{font-size:24px}
				.cmx-telefonbuch-detail-people li,.cmx-telefonbuch-detail-line,.cmx-telefonbuch-detail-beleg-row,.cmx-telefonbuch-detail-task-row,.cmx-telefonbuch-detail-box-head{flex-direction:column;align-items:flex-start}
			}
		</style>';
		echo '</head><body>';
		echo '<div class="cmx-telefonbuch-detail-page"><div class="cmx-telefonbuch-detail-card">';
		echo '<div class="cmx-telefonbuch-detail-head">';
		echo '<div>';
		echo '<p class="cmx-telefonbuch-detail-kicker"><a href="' . \esc_url($back_url) . '">Zurück zum Telefonbuch</a></p>';
		echo '<h1 class="cmx-telefonbuch-detail-title">' . \esc_html($title) . '</h1>';
		if ($firmengruendung !== '') {
			echo '<p class="cmx-telefonbuch-detail-sub">Firmengründung: ' . \esc_html($firmengruendung) . '</p>';
		}
		echo '</div>';
		if ($contact_logo !== '') {
			echo '<img class="cmx-telefonbuch-detail-logo" src="' . \esc_url($contact_logo) . '" alt="' . \esc_attr($title) . '">';
		}
		echo '</div>';
		echo '<div class="cmx-telefonbuch-detail-body">';

		echo '<div class="cmx-telefonbuch-detail-meta">';
		if ($stufen !== []) {
			echo '<span class="cmx-telefonbuch-detail-pill"><strong>Stufe</strong> ' . \esc_html(\implode(', ', $stufen)) . '</span>';
		}
		if ($kategorien !== []) {
			echo '<span class="cmx-telefonbuch-detail-pill"><strong>Kategorien</strong> ' . \esc_html(\implode(', ', $kategorien)) . '</span>';
		}
		if ($stufen === [] && $kategorien === []) {
			echo '<span class="cmx-telefonbuch-detail-empty">Keine Stufe oder Kategorien hinterlegt.</span>';
		}
		echo '</div>';

		echo '<div class="cmx-telefonbuch-detail-grid">';

		echo '<div class="cmx-telefonbuch-detail-box">';
		echo '<h3>Ansprechpartner</h3>';
		if ($people === []) {
			echo '<p class="cmx-telefonbuch-detail-empty">Keine Ansprechpartner mit Namen gefunden.</p>';
		} else {
			echo '<ul class="cmx-telefonbuch-detail-people">';
			foreach ($people as $person) {
				$item_class = !empty($person['is_primary']) ? ' class="is-current"' : '';
				echo '<li' . $item_class . '>';
				if (!empty($person['is_primary'])) {
					echo '<strong>' . \esc_html((string) ($person['name'] ?? '')) . '</strong>';
				} else {
					echo '<span>' . \esc_html((string) ($person['name'] ?? '')) . '</span>';
				}
				$birthdate = \trim((string) ($person['birthdate'] ?? ''));
				if ($birthdate !== '') {
					echo '<span class="cmx-telefonbuch-detail-date">' . \esc_html($birthdate) . '</span>';
				}
				echo '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';

		echo '<div class="cmx-telefonbuch-detail-box">';
		$latest_note_date = \trim((string) (($latest_note['datum'] ?? '') . ' ' . ($latest_note['zeit'] ?? '')));
		echo '<div class="cmx-telefonbuch-detail-box-head cmx-telefonbuch-detail-box-head-note"><h3>Neueste Interne Notiz</h3>';
		if ($latest_note_date !== '') {
			echo '<div class="cmx-telefonbuch-detail-date">' . \esc_html($latest_note_date) . '</div>';
		}
		echo '</div>';
		if ($latest_note === []) {
			echo '<p class="cmx-telefonbuch-detail-empty">Keine interne Notiz vorhanden.</p>';
		} else {
			echo '<div class="cmx-telefonbuch-detail-text">' . \nl2br(\esc_html((string) ($latest_note['text'] ?? ''))) . '</div>';
		}
		echo '</div>';

		echo '<div class="cmx-telefonbuch-detail-box">';
		$latest_task_date = \trim((string) (($latest_task['datum'] ?? '') . ' ' . ($latest_task['zeit'] ?? '')));
		echo '<div class="cmx-telefonbuch-detail-box-head"><h3>Neueste Tätigkeit</h3>';
		if ($latest_task_date !== '') {
			echo '<div class="cmx-telefonbuch-detail-date">' . \esc_html($latest_task_date) . '</div>';
		}
		echo '</div>';
		if ($latest_task === []) {
			echo '<p class="cmx-telefonbuch-detail-empty">Keine Tätigkeit vorhanden.</p>';
		} else {
			$task_meta = [];
			if (\trim((string) ($latest_task['artikel'] ?? '')) !== '') {
				$task_meta[] = (string) $latest_task['artikel'];
			}
			if (\trim((string) ($latest_task['produkt'] ?? '')) !== '') {
				$task_meta[] = (string) $latest_task['produkt'];
			}
			if (\trim((string) ($latest_task['dauer'] ?? '')) !== '') {
				$task_meta[] = 'Dauer: ' . (string) $latest_task['dauer'] . ' h';
			}
			echo '<div class="cmx-telefonbuch-detail-line"><div>';
			if ($task_meta !== []) {
				echo '<div>' . \esc_html(\implode(' · ', $task_meta)) . '</div>';
			}
			echo '</div></div>';
			echo '<div class="cmx-telefonbuch-detail-task-info">' . \nl2br(\esc_html((string) ($latest_task['info'] ?? ''))) . '</div>';
		}
		echo '</div>';

		echo '<div class="cmx-telefonbuch-detail-box">';
		echo '<h3>Letzter Beleg</h3>';
		if ($latest_beleg === []) {
			echo '<p class="cmx-telefonbuch-detail-empty">Kein Beleg verknüpft.</p>';
		} else {
			$url = \trim((string) ($latest_beleg['url'] ?? ''));
			$title_html = \esc_html((string) ($latest_beleg['title'] ?? ''));
			if ($url !== '') {
				$title_html = '<a href="' . \esc_url($url) . '">' . $title_html . '</a>';
			}
			$amount = \trim((string) ($latest_beleg['amount'] ?? ''));
			$state_class = !empty($latest_beleg['is_paid']) ? 'cmx-telefonbuch-detail-state-paid' : 'cmx-telefonbuch-detail-state-open';
			echo '<div class="cmx-telefonbuch-detail-beleg-row ' . \esc_attr($state_class) . '">';
			echo '<div class="cmx-telefonbuch-detail-beleg-meta">';
			echo '<span class="cmx-telefonbuch-detail-beleg-id">' . $title_html . '</span>';
			echo '<span class="cmx-telefonbuch-detail-beleg-date">' . \esc_html((string) ($latest_beleg['date'] ?? '')) . '</span>';
			echo '</div>';
			if ($amount !== '') {
				echo '<span class="cmx-telefonbuch-detail-beleg-amount">' . \esc_html($amount) . '</span>';
			}
			echo '</div>';
		}
		echo '</div>';

		echo '</div>';
		echo '</div></div></div>';
		echo '</body></html>';
		exit;
	}
}

\add_action('template_redirect', __NAMESPACE__ . '\\cmx_redirect_telefonbuch_legacy_detail_url', 0);
\add_action('template_redirect', __NAMESPACE__ . '\\cmx_render_telefonbuch_detail_page', 4);
